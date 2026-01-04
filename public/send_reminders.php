<?php
/**
 * Send Fee Reminders
 * Invio solleciti quote (solo admin)
 */

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/email.php';
require_once __DIR__ . '/../src/audit.php';

requireAdmin();

$pageTitle = 'Invio Solleciti Quote';
$step = $_GET['step'] ?? 1;

// Step 1: Configurazione
$reminderType = $_POST['reminder_type'] ?? 'expiring'; // expiring o overdue
$days = isset($_POST['days']) ? intval($_POST['days']) : 30;
$selectedFees = $_POST['fees'] ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step'])) {
    checkCsrf();
    $step = intval($_POST['step']) + 1;
}

// Send emails
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_emails'])) {
    checkCsrf();
    
    $selectedFees = $_POST['fees'] ?? [];
    $stats = ['sent' => 0, 'failed' => 0, 'queued' => 0];
    
    foreach ($selectedFees as $feeId) {
        // Recupera dati quota con socio
        $stmt = $pdo->prepare("
            SELECT mf.*, m.first_name, m.last_name, m.email, sy.name as year_name
            FROM " . table('member_fees') . " mf
            JOIN " . table('members') . " m ON mf.member_id = m.id
            LEFT JOIN " . table('social_years') . " sy ON mf.social_year_id = sy.id
            WHERE mf.id = ?
        ");
        $stmt->execute([$feeId]);
        $fee = $stmt->fetch();
        
        if (!$fee || !$fee['email']) {
            $stats['failed']++;
            continue;
        }
        
        // Determina template in base allo stato
        $templateCode = ($fee['status'] === 'overdue') ? 'fee_overdue' : 'fee_reminder';
        
        $variables = [
            'nome' => $fee['first_name'],
            'cognome' => $fee['last_name'],
            'anno' => $fee['year_name'] ?? '',
            'importo' => formatCurrency($fee['amount']),
            'scadenza' => formatDate($fee['due_date'])
        ];
        
        // Prepara contenuti PRIMA di accodare
        $stmt = $pdo->prepare("SELECT * FROM " . table('email_templates') . " WHERE code = ?");
        $stmt->execute([$templateCode]);
        $template = $stmt->fetch();
        
        if ($template) {
            $subject = replaceTemplateVariables($template['subject'], array_merge($variables, ['app_name' => $config['app']['name']]));
            $bodyHtml = replaceTemplateVariables($template['body_html'], array_merge($variables, ['app_name' => $config['app']['name']]));
            $bodyText = $template['body_text'] ? replaceTemplateVariables($template['body_text'], array_merge($variables, ['app_name' => $config['app']['name']])) : null;
            
            // Ora accoda/invia con contenuti completi
            if (sendOrQueueEmail($fee['email'], $subject, $bodyHtml, $bodyText)) {
                $stats['sent']++;
            } else {
                $stats['failed']++;
            }
        } else {
            $stats['failed']++;
        }
    }
    
    logExport('fee_reminder', 'Invio solleciti quote (' . $stats['sent'] . ' inviate)');
    
    setFlash('Solleciti inviati: ' . $stats['sent'] . ' inviate, ' . $stats['failed'] . ' fallite', 
             $stats['failed'] > 0 ? 'warning' : 'success');
    
    header('Location: ' . $config['app']['base_path'] . 'send_reminders.php?step=3&sent=' . $stats['sent'] . '&failed=' . $stats['failed']);
    exit;
}

// Recupera quote in base al tipo
$fees = [];
if ($step >= 2) {
    if ($reminderType === 'expiring') {
        // Quote in scadenza
        $fees = getFeesExpiringSoon($days);
    } else {
        // Quote scadute
        $stmt = $pdo->query("
            SELECT mf.*, m.first_name, m.last_name, m.email, m.membership_number, sy.name as year_name
            FROM " . table('member_fees') . " mf
            JOIN " . table('members') . " m ON mf.member_id = m.id
            LEFT JOIN " . table('social_years') . " sy ON mf.social_year_id = sy.id
            WHERE mf.status = 'overdue'
            ORDER BY mf.due_date ASC
        ");
        $fees = $stmt->fetchAll();
    }
}

include __DIR__ . '/inc/header.php';
?>

<h1><i class="bi bi-envelope-exclamation"></i> Invio Solleciti Quote</h1>

<?php if ($step == 1): ?>
    <!-- Step 1: Configurazione -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Step 1: Configurazione</h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <?php echo csrfField(); ?>
                <input type="hidden" name="step" value="1">
                
                <div class="mb-3">
                    <label class="form-label">Tipo Sollecito</label>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="reminder_type" 
                               id="expiring" value="expiring" checked>
                        <label class="form-check-label" for="expiring">
                            <strong>Quote in Scadenza</strong> - Sollecito preventivo per quote in scadenza
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="reminder_type" 
                               id="overdue" value="overdue">
                        <label class="form-check-label" for="overdue">
                            <strong>Quote Scadute</strong> - Sollecito per quote già scadute
                        </label>
                    </div>
                </div>
                
                <div class="mb-3" id="days-input">
                    <label class="form-label">Giorni di Preavviso</label>
                    <input type="number" class="form-control" name="days" value="30" min="1" max="365">
                    <small class="text-muted">Sollecita quote in scadenza nei prossimi X giorni</small>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-arrow-right"></i> Avanti
                </button>
            </form>
        </div>
    </div>
    
    <script>
    document.querySelectorAll('input[name="reminder_type"]').forEach(radio => {
        radio.addEventListener('change', function() {
            document.getElementById('days-input').style.display = 
                this.value === 'expiring' ? 'block' : 'none';
        });
    });
    </script>

<?php elseif ($step == 2): ?>
    <!-- Step 2: Selezione Destinatari -->
    <div class="mb-3">
        <a href="?step=1" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Indietro
        </a>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Step 2: Selezione Destinatari</h5>
        </div>
        <div class="card-body">
            <?php if (empty($fees)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> 
                    Nessuna quota trovata per i criteri selezionati.
                </div>
                <a href="?step=1" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Torna Indietro
                </a>
            <?php else: ?>
                <form method="POST">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="reminder_type" value="<?php echo h($reminderType); ?>">
                    <input type="hidden" name="days" value="<?php echo $days; ?>">
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> 
                        Trovate <strong><?php echo count($fees); ?></strong> quote. 
                        Seleziona i destinatari per l'invio del sollecito.
                    </div>
                    
                    <div class="mb-3">
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAll()">
                            Seleziona Tutti
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="deselectAll()">
                            Deseleziona Tutti
                        </button>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th width="50">
                                        <input type="checkbox" id="select-all" onchange="toggleAll(this)">
                                    </th>
                                    <th>Socio</th>
                                    <th>Email</th>
                                    <th>Anno</th>
                                    <th>Importo</th>
                                    <th>Scadenza</th>
                                    <th>Stato</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($fees as $fee): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" class="fee-checkbox" name="fees[]" 
                                                   value="<?php echo $fee['id']; ?>"
                                                   <?php echo $fee['email'] ? 'checked' : 'disabled'; ?>>
                                        </td>
                                        <td><?php echo h($fee['first_name'] . ' ' . $fee['last_name']); ?></td>
                                        <td>
                                            <?php if ($fee['email']): ?>
                                                <?php echo h($fee['email']); ?>
                                            <?php else: ?>
                                                <span class="text-danger">Nessuna email</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo h($fee['year_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo formatCurrency($fee['amount']); ?></td>
                                        <td><?php echo formatDate($fee['due_date']); ?></td>
                                        <td>
                                            <?php if ($fee['status'] === 'overdue'): ?>
                                                <span class="badge bg-danger">Scaduta</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">In Scadenza</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="mt-3">
                        <button type="submit" name="send_emails" class="btn btn-primary">
                            <i class="bi bi-send"></i> Invia Solleciti
                        </button>
                        <a href="?step=1" class="btn btn-secondary">Annulla</a>
                    </div>
                </form>
                
                <script>
                function toggleAll(checkbox) {
                    document.querySelectorAll('.fee-checkbox:not(:disabled)').forEach(cb => {
                        cb.checked = checkbox.checked;
                    });
                }
                function selectAll() {
                    document.querySelectorAll('.fee-checkbox:not(:disabled)').forEach(cb => {
                        cb.checked = true;
                    });
                    document.getElementById('select-all').checked = true;
                }
                function deselectAll() {
                    document.querySelectorAll('.fee-checkbox').forEach(cb => {
                        cb.checked = false;
                    });
                    document.getElementById('select-all').checked = false;
                }
                </script>
            <?php endif; ?>
        </div>
    </div>

<?php elseif ($step == 3): ?>
    <!-- Step 3: Riepilogo -->
    <div class="card">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0"><i class="bi bi-check-circle"></i> Invio Completato</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-success">
                <h5>Solleciti Inviati con Successo!</h5>
                <ul class="mb-0">
                    <li><strong><?php echo intval($_GET['sent'] ?? 0); ?></strong> email inviate</li>
                    <?php if (isset($_GET['failed']) && $_GET['failed'] > 0): ?>
                        <li class="text-danger"><strong><?php echo intval($_GET['failed']); ?></strong> email fallite</li>
                    <?php endif; ?>
                </ul>
            </div>
            
            <a href="<?php echo h($config['app']['base_path']); ?>member_fees.php" class="btn btn-primary">
                <i class="bi bi-credit-card"></i> Vai a Quote
            </a>
            <a href="?step=1" class="btn btn-secondary">
                <i class="bi bi-envelope"></i> Invia Altri Solleciti
            </a>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/inc/footer.php'; ?>
