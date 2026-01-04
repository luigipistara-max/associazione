<?php
/**
 * Bulk Fee Renewal
 * Rinnovo massivo quote (solo admin)
 */

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/audit.php';

requireAdmin();

$config = require __DIR__ . '/../src/config.php';
$pageTitle = 'Rinnovo Massivo Quote';
$step = $_GET['step'] ?? 1;

// Configuration from POST
$config_data = [
    'social_year_id' => $_POST['social_year_id'] ?? null,
    'amount' => isset($_POST['amount']) ? floatval($_POST['amount']) : 0,
    'due_date' => $_POST['due_date'] ?? '',
    'fee_type' => $_POST['fee_type'] ?? 'quota_associativa',
    'copy_previous' => isset($_POST['copy_previous']),
    'discount_percent' => isset($_POST['discount_percent']) ? floatval($_POST['discount_percent']) : 0,
    'send_email' => isset($_POST['send_email']),
    'selection_mode' => $_POST['selection_mode'] ?? 'all',
    'selected_members' => $_POST['selected_members'] ?? []
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step'])) {
    checkCsrf();
    $step = intval($_POST['step']) + 1;
}

// Execute bulk creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['execute'])) {
    checkCsrf();
    
    $memberIds = $_POST['selected_members'] ?? [];
    $socialYearId = intval($_POST['social_year_id']);
    $amount = floatval($_POST['amount']);
    $dueDate = $_POST['due_date'];
    $feeType = $_POST['fee_type'];
    $sendEmail = isset($_POST['send_email']);
    
    if (empty($memberIds)) {
        setFlash('Nessun socio selezionato', 'danger');
    } else {
        $stats = bulkCreateFees($memberIds, $socialYearId, $amount, $dueDate, $feeType, $sendEmail);
        
        logAudit('bulk_create_fees', 'fee', null, 
            "Quote create: {$stats['created']}, ignorate: {$stats['skipped']}, email: {$stats['emails_sent']}");
        
        $message = "Quote create: {$stats['created']}";
        if ($stats['skipped'] > 0) {
            $message .= ", ignorate (già esistenti): {$stats['skipped']}";
        }
        if ($sendEmail && $stats['emails_sent'] > 0) {
            $message .= ", email inviate: {$stats['emails_sent']}";
        }
        
        setFlash($message, 'success');
        
        header('Location: ' . $config['app']['base_path'] . 'bulk_fees.php?step=4&created=' . $stats['created'] . 
               '&skipped=' . $stats['skipped'] . '&emails=' . $stats['emails_sent']);
        exit;
    }
}

// Get social years
$socialYears = getSocialYears();

// Get members based on selection
$members = [];
if ($step >= 2 && $config_data['social_year_id']) {
    if ($config_data['selection_mode'] === 'all') {
        $members = getMembersWithoutFee($config_data['social_year_id'], 'attivo');
    } else {
        // Get all active members for manual selection
        $stmt = $pdo->query("
            SELECT * FROM " . table('members') . "
            WHERE status = 'attivo'
            ORDER BY last_name, first_name
        ");
        $members = $stmt->fetchAll();
        
        // Filter out members who already have fees for this year
        $members = array_filter($members, function($m) use ($config_data) {
            return !memberHasFeeForYear($m['id'], $config_data['social_year_id']);
        });
    }
}

// Prepare preview data
$previewData = [];
if ($step >= 3 && !empty($config_data['selected_members'])) {
    foreach ($config_data['selected_members'] as $memberId) {
        $stmt = $pdo->prepare("SELECT * FROM " . table('members') . " WHERE id = ?");
        $stmt->execute([$memberId]);
        $member = $stmt->fetch();
        
        if ($member) {
            $feeAmount = $config_data['amount'];
            
            // Apply previous amount if requested
            if ($config_data['copy_previous']) {
                $prevAmount = getPreviousFeeAmount($memberId, $config_data['social_year_id']);
                if ($prevAmount) {
                    $feeAmount = $prevAmount;
                }
            }
            
            // Apply discount/markup
            if ($config_data['discount_percent'] != 0) {
                $feeAmount = $feeAmount * (1 + ($config_data['discount_percent'] / 100));
            }
            
            $previewData[] = [
                'member' => $member,
                'amount' => $feeAmount
            ];
        }
    }
}

include __DIR__ . '/inc/header.php';
?>

<h1><i class="bi bi-arrow-repeat"></i> Rinnovo Massivo Quote</h1>

<div class="mb-3">
    <div class="progress" style="height: 25px;">
        <div class="progress-bar" role="progressbar" 
             style="width: <?php echo ($step * 25); ?>%;" 
             aria-valuenow="<?php echo $step; ?>" aria-valuemin="0" aria-valuemax="4">
            Step <?php echo $step; ?> di 4
        </div>
    </div>
</div>

<?php if ($step == 1): ?>
    <!-- Step 1: Configuration -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Step 1: Configurazione</h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <?php echo csrfField(); ?>
                <input type="hidden" name="step" value="1">
                
                <div class="mb-3">
                    <label class="form-label">Anno Sociale *</label>
                    <select name="social_year_id" class="form-select" required>
                        <option value="">Seleziona anno sociale</option>
                        <?php foreach ($socialYears as $year): ?>
                            <option value="<?php echo $year['id']; ?>" 
                                    <?php echo $year['is_current'] ? 'selected' : ''; ?>>
                                <?php echo h($year['name']); ?>
                                <?php echo $year['is_current'] ? ' (Corrente)' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Importo Quota Standard *</label>
                    <div class="input-group">
                        <span class="input-group-text">€</span>
                        <input type="number" class="form-control" name="amount" 
                               step="0.01" min="0" value="50.00" required>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Data Scadenza *</label>
                    <input type="date" class="form-control" name="due_date" 
                           value="<?php echo date('Y-12-31'); ?>" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Tipo Quota</label>
                    <select name="fee_type" class="form-select">
                        <option value="quota_associativa">Quota Associativa</option>
                        <option value="quota_straordinaria">Quota Straordinaria</option>
                        <option value="quota_iscrizione">Quota Iscrizione</option>
                    </select>
                </div>
                
                <div class="card bg-light mb-3">
                    <div class="card-body">
                        <h6>Opzioni Avanzate</h6>
                        
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="copy_previous" 
                                   name="copy_previous" value="1">
                            <label class="form-check-label" for="copy_previous">
                                Copia importo da anno precedente (se esiste)
                            </label>
                        </div>
                        
                        <div class="mb-2">
                            <label class="form-label">Sconto/Maggiorazione (%)</label>
                            <input type="number" class="form-control" name="discount_percent" 
                                   step="0.01" value="0" placeholder="Es: -10 per sconto 10%, +5 per maggiorazione 5%">
                            <small class="text-muted">Valore negativo per sconto, positivo per maggiorazione</small>
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="send_email" 
                                   name="send_email" value="1">
                            <label class="form-check-label" for="send_email">
                                Invia email notifica ai soci
                            </label>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-arrow-right"></i> Avanti
                </button>
            </form>
        </div>
    </div>

<?php elseif ($step == 2): ?>
    <!-- Step 2: Member Selection -->
    <div class="mb-3">
        <a href="?step=1" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Indietro
        </a>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Step 2: Selezione Soci</h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <?php echo csrfField(); ?>
                <input type="hidden" name="step" value="2">
                <input type="hidden" name="social_year_id" value="<?php echo h($config_data['social_year_id']); ?>">
                <input type="hidden" name="amount" value="<?php echo h($config_data['amount']); ?>">
                <input type="hidden" name="due_date" value="<?php echo h($config_data['due_date']); ?>">
                <input type="hidden" name="fee_type" value="<?php echo h($config_data['fee_type']); ?>">
                <?php if ($config_data['copy_previous']): ?>
                    <input type="hidden" name="copy_previous" value="1">
                <?php endif; ?>
                <input type="hidden" name="discount_percent" value="<?php echo h($config_data['discount_percent']); ?>">
                <?php if ($config_data['send_email']): ?>
                    <input type="hidden" name="send_email" value="1">
                <?php endif; ?>
                
                <div class="mb-3">
                    <label class="form-label">Modalità Selezione</label>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="selection_mode" 
                               id="mode_all" value="all" 
                               <?php echo $config_data['selection_mode'] === 'all' ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="mode_all">
                            <strong>Tutti i soci attivi</strong> senza quota per l'anno selezionato
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="selection_mode" 
                               id="mode_manual" value="manual"
                               <?php echo $config_data['selection_mode'] === 'manual' ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="mode_manual">
                            <strong>Selezione manuale</strong> - scegli quali soci includere
                        </label>
                    </div>
                </div>
                
                <div id="manual-selection" style="display: <?php echo $config_data['selection_mode'] === 'manual' ? 'block' : 'none'; ?>;">
                    <?php if (empty($members)): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> 
                            Nessun socio trovato senza quota per questo anno.
                        </div>
                    <?php else: ?>
                        <div class="mb-3">
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAllMembers()">
                                Seleziona Tutti
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="deselectAllMembers()">
                                Deseleziona Tutti
                            </button>
                        </div>
                        
                        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                            <table class="table table-sm table-hover">
                                <thead class="sticky-top bg-white">
                                    <tr>
                                        <th width="50">
                                            <input type="checkbox" id="select-all-members" onchange="toggleAllMembers(this)">
                                        </th>
                                        <th>Nome</th>
                                        <th>Codice Fiscale</th>
                                        <th>Email</th>
                                        <th>Tessera</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($members as $member): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" class="member-checkbox" 
                                                       name="selected_members[]" 
                                                       value="<?php echo $member['id']; ?>" checked>
                                            </td>
                                            <td><?php echo h($member['first_name'] . ' ' . $member['last_name']); ?></td>
                                            <td><?php echo h($member['fiscal_code']); ?></td>
                                            <td><?php echo h($member['email'] ?? ''); ?></td>
                                            <td><?php echo h($member['membership_number'] ?? ''); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($config_data['selection_mode'] === 'all'): ?>
                    <?php foreach ($members as $member): ?>
                        <input type="hidden" name="selected_members[]" value="<?php echo $member['id']; ?>">
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <div class="mt-3">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> 
                        <strong><?php echo count($members); ?></strong> soci saranno inclusi nel rinnovo.
                    </div>
                    
                    <button type="submit" class="btn btn-primary" <?php echo empty($members) ? 'disabled' : ''; ?>>
                        <i class="bi bi-arrow-right"></i> Avanti
                    </button>
                    <a href="?step=1" class="btn btn-secondary">Indietro</a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
    document.querySelectorAll('input[name="selection_mode"]').forEach(radio => {
        radio.addEventListener('change', function() {
            document.getElementById('manual-selection').style.display = 
                this.value === 'manual' ? 'block' : 'none';
        });
    });
    
    function toggleAllMembers(checkbox) {
        document.querySelectorAll('.member-checkbox').forEach(cb => {
            cb.checked = checkbox.checked;
        });
    }
    function selectAllMembers() {
        document.querySelectorAll('.member-checkbox').forEach(cb => {
            cb.checked = true;
        });
        document.getElementById('select-all-members').checked = true;
    }
    function deselectAllMembers() {
        document.querySelectorAll('.member-checkbox').forEach(cb => {
            cb.checked = false;
        });
        document.getElementById('select-all-members').checked = false;
    }
    </script>

<?php elseif ($step == 3): ?>
    <!-- Step 3: Preview -->
    <div class="mb-3">
        <a href="?step=2" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Indietro
        </a>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Step 3: Riepilogo e Conferma</h5>
        </div>
        <div class="card-body">
            <?php
            // Get year name
            $stmt = $pdo->prepare("SELECT name FROM " . table('social_years') . " WHERE id = ?");
            $stmt->execute([$config_data['social_year_id']]);
            $yearName = $stmt->fetchColumn();
            
            $totalAmount = array_sum(array_column($previewData, 'amount'));
            ?>
            
            <div class="alert alert-info mb-4">
                <h5><i class="bi bi-info-circle"></i> Riepilogo Operazione</h5>
                <ul class="mb-0">
                    <li><strong>Anno Sociale:</strong> <?php echo h($yearName); ?></li>
                    <li><strong>Quote da creare:</strong> <?php echo count($previewData); ?></li>
                    <li><strong>Importo totale:</strong> <?php echo formatCurrency($totalAmount); ?></li>
                    <li><strong>Data scadenza:</strong> <?php echo formatDate($config_data['due_date']); ?></li>
                    <li><strong>Tipo quota:</strong> <?php echo h(ucfirst(str_replace('_', ' ', $config_data['fee_type']))); ?></li>
                    <?php if ($config_data['send_email']): ?>
                        <li><strong>Email:</strong> Verranno inviate notifiche ai soci</li>
                    <?php endif; ?>
                </ul>
            </div>
            
            <h6>Dettaglio Quote</h6>
            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                <table class="table table-sm">
                    <thead class="sticky-top bg-white">
                        <tr>
                            <th>Socio</th>
                            <th>Email</th>
                            <th class="text-end">Importo</th>
                            <th>Scadenza</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($previewData as $item): ?>
                            <tr>
                                <td><?php echo h($item['member']['first_name'] . ' ' . $item['member']['last_name']); ?></td>
                                <td><?php echo h($item['member']['email'] ?? 'N/A'); ?></td>
                                <td class="text-end"><?php echo formatCurrency($item['amount']); ?></td>
                                <td><?php echo formatDate($config_data['due_date']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="2">Totale</th>
                            <th class="text-end"><?php echo formatCurrency($totalAmount); ?></th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <form method="POST" class="mt-4">
                <?php echo csrfField(); ?>
                <input type="hidden" name="social_year_id" value="<?php echo h($config_data['social_year_id']); ?>">
                <input type="hidden" name="amount" value="<?php echo h($config_data['amount']); ?>">
                <input type="hidden" name="due_date" value="<?php echo h($config_data['due_date']); ?>">
                <input type="hidden" name="fee_type" value="<?php echo h($config_data['fee_type']); ?>">
                <?php if ($config_data['send_email']): ?>
                    <input type="hidden" name="send_email" value="1">
                <?php endif; ?>
                <?php foreach ($config_data['selected_members'] as $memberId): ?>
                    <input type="hidden" name="selected_members[]" value="<?php echo h($memberId); ?>">
                <?php endforeach; ?>
                
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i>
                    <strong>Attenzione:</strong> Questa operazione creerà <?php echo count($previewData); ?> quote. 
                    Procedere con la creazione?
                </div>
                
                <button type="submit" name="execute" class="btn btn-success btn-lg">
                    <i class="bi bi-check-circle"></i> Genera Quote
                </button>
                <a href="?step=2" class="btn btn-secondary">Indietro</a>
            </form>
        </div>
    </div>

<?php elseif ($step == 4): ?>
    <!-- Step 4: Completion -->
    <div class="card">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0"><i class="bi bi-check-circle"></i> Operazione Completata</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-success">
                <h5>Quote Generate con Successo!</h5>
                <ul class="mb-0">
                    <li><strong><?php echo intval($_GET['created'] ?? 0); ?></strong> quote create</li>
                    <?php if (isset($_GET['skipped']) && $_GET['skipped'] > 0): ?>
                        <li><strong><?php echo intval($_GET['skipped']); ?></strong> quote ignorate (già esistenti)</li>
                    <?php endif; ?>
                    <?php if (isset($_GET['emails']) && $_GET['emails'] > 0): ?>
                        <li><strong><?php echo intval($_GET['emails']); ?></strong> email inviate</li>
                    <?php endif; ?>
                </ul>
            </div>
            
            <a href="<?php echo h($config['app']['base_path']); ?>member_fees.php" class="btn btn-primary">
                <i class="bi bi-credit-card"></i> Vai a Quote
            </a>
            <a href="?step=1" class="btn btn-secondary">
                <i class="bi bi-arrow-repeat"></i> Nuovo Rinnovo
            </a>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/inc/footer.php'; ?>
