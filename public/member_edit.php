<?php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/audit.php';
require_once __DIR__ . '/../src/email.php';

requireLogin();

$config = require __DIR__ . '/../src/config.php';
$basePath = $config['app']['base_path'];

$memberId = $_GET['id'] ?? null;
$member = null;
$errors = [];

// Handle send portal activation
if (isset($_GET['send_activation']) && $memberId) {
    $token = $_POST['csrf_token'] ?? '';
    
    if (!verifyCsrfToken($token)) {
        setFlashMessage('Token di sicurezza non valido', 'danger');
    } else {
        if (sendPortalActivationEmail($memberId)) {
            setFlashMessage('Email di attivazione inviata con successo', 'success');
        } else {
            setFlashMessage('Errore durante l\'invio dell\'email', 'danger');
        }
    }
    redirect($basePath . 'member_edit.php?id=' . $memberId);
}

// Load existing member
if ($memberId) {
    $stmt = $pdo->prepare("SELECT * FROM " . table('members') . " WHERE id = ?");
    $stmt->execute([$memberId]);
    $member = $stmt->fetch();
    
    if (!$member) {
        setFlashMessage('Socio non trovato', 'danger');
        redirect($basePath . 'members.php');
    }
    
    $pageTitle = 'Modifica Socio';
} else {
    $pageTitle = 'Nuovo Socio';
    $member = [
        'membership_number' => '',
        'first_name' => '',
        'last_name' => '',
        'fiscal_code' => '',
        'birth_date' => '',
        'birth_place' => '',
        'email' => '',
        'phone' => '',
        'address' => '',
        'city' => '',
        'postal_code' => '',
        'registration_date' => date('Y-m-d'),
        'status' => 'attivo',
        'notes' => ''
    ];
}

// Get all active groups
$allGroups = getGroups(true);

// Get member's current groups (if editing)
$memberGroupIds = [];
if ($memberId) {
    $memberGroups = getMemberGroups($memberId);
    $memberGroupIds = array_column($memberGroups, 'id');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    
    if (!verifyCsrfToken($token)) {
        $errors[] = 'Token di sicurezza non valido';
    } else {
        // Get form data
        $data = [
            'membership_number' => trim($_POST['membership_number'] ?? ''),
            'first_name' => trim($_POST['first_name'] ?? ''),
            'last_name' => trim($_POST['last_name'] ?? ''),
            'fiscal_code' => strtoupper(trim($_POST['fiscal_code'] ?? '')),
            'birth_date' => $_POST['birth_date'] ?? null,
            'birth_place' => trim($_POST['birth_place'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
            'city' => trim($_POST['city'] ?? ''),
            'postal_code' => trim($_POST['postal_code'] ?? ''),
            'registration_date' => $_POST['registration_date'] ?? null,
            'status' => $_POST['status'] ?? 'attivo',
            'notes' => trim($_POST['notes'] ?? '')
        ];
        
        // Validation
        if (empty($data['first_name'])) {
            $errors[] = 'Il nome è obbligatorio';
        }
        if (empty($data['last_name'])) {
            $errors[] = 'Il cognome è obbligatorio';
        }
        if (empty($data['fiscal_code'])) {
            $errors[] = 'Il codice fiscale è obbligatorio';
        } elseif (!validateFiscalCode($data['fiscal_code'])) {
            $errors[] = 'Il codice fiscale non è valido';
        }
        
        // Check for duplicate tax code
        if (!empty($data['fiscal_code'])) {
            $stmt = $pdo->prepare("SELECT id FROM " . table('members') . " WHERE fiscal_code = ? AND id != ?");
            $stmt->execute([$data['fiscal_code'], $memberId ?? 0]);
            if ($stmt->fetch()) {
                $errors[] = 'Codice fiscale già presente nel database';
            }
        }
        
        if (empty($errors)) {
            try {
                if ($memberId) {
                    // Get old data for audit
                    $stmt = $pdo->prepare("SELECT * FROM " . table('members') . " WHERE id = ?");
                    $stmt->execute([$memberId]);
                    $oldMember = $stmt->fetch();
                    
                    // Update
                    $stmt = $pdo->prepare("
                        UPDATE " . table('members') . " SET
                            membership_number = ?, first_name = ?, last_name = ?, fiscal_code = ?,
                            birth_date = ?, birth_place = ?, email = ?, phone = ?,
                            address = ?, city = ?, postal_code = ?, registration_date = ?,
                            status = ?, notes = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $data['membership_number'] ?: null,
                        $data['first_name'],
                        $data['last_name'],
                        $data['fiscal_code'],
                        $data['birth_date'] ?: null,
                        $data['birth_place'] ?: null,
                        $data['email'] ?: null,
                        $data['phone'] ?: null,
                        $data['address'] ?: null,
                        $data['city'] ?: null,
                        $data['postal_code'] ?: null,
                        $data['registration_date'] ?: null,
                        $data['status'],
                        $data['notes'] ?: null,
                        $memberId
                    ]);
                    
                    if ($oldMember) {
                        logUpdate('member', $memberId, "{$data['first_name']} {$data['last_name']}",
                            ['status' => $oldMember['status'], 'email' => $oldMember['email']],
                            ['status' => $data['status'], 'email' => $data['email']]
                        );
                    }
                    
                    setFlashMessage('Socio aggiornato con successo');
                } else {
                    // Insert
                    $stmt = $pdo->prepare("
                        INSERT INTO " . table('members') . " (
                            membership_number, first_name, last_name, fiscal_code,
                            birth_date, birth_place, email, phone,
                            address, city, postal_code, registration_date,
                            status, notes
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $data['membership_number'] ?: null,
                        $data['first_name'],
                        $data['last_name'],
                        $data['fiscal_code'],
                        $data['birth_date'] ?: null,
                        $data['birth_place'] ?: null,
                        $data['email'] ?: null,
                        $data['phone'] ?: null,
                        $data['address'] ?: null,
                        $data['city'] ?: null,
                        $data['postal_code'] ?: null,
                        $data['registration_date'] ?: null,
                        $data['status'],
                        $data['notes'] ?: null
                    ]);
                    
                    $newMemberId = $pdo->lastInsertId();
                    logCreate('member', $newMemberId, "{$data['first_name']} {$data['last_name']}", [
                        'fiscal_code' => $data['fiscal_code'],
                        'status' => $data['status']
                    ]);
                    
                    setFlashMessage('Socio aggiunto con successo');
                }
                
                // After saving member data, handle groups
                if ($memberId || $newMemberId) {
                    $targetMemberId = $memberId ?? $newMemberId;
                    $selectedGroups = $_POST['groups'] ?? [];
                    
                    // Validate that selected groups exist and are active
                    $validGroupIds = array_column($allGroups, 'id');
                    $selectedGroups = array_filter($selectedGroups, function($groupId) use ($validGroupIds) {
                        return in_array((int)$groupId, $validGroupIds);
                    });
                    
                    // Get current groups for this member
                    $currentGroupIds = $memberGroupIds; // Already loaded at top of file
                    
                    // Calculate groups to add and remove
                    $groupsToAdd = array_diff($selectedGroups, $currentGroupIds);
                    $groupsToRemove = array_diff($currentGroupIds, $selectedGroups);
                    
                    // Remove member from groups they're no longer in
                    foreach ($groupsToRemove as $groupId) {
                        removeMemberFromGroup($groupId, $targetMemberId);
                    }
                    
                    // Add member to new groups
                    foreach ($groupsToAdd as $groupId) {
                        addMemberToGroup($groupId, $targetMemberId);
                    }
                }
                
                redirect($basePath . 'members.php');
            } catch (PDOException $e) {
                $errors[] = 'Errore nel salvataggio: ' . $e->getMessage();
            }
        }
        
        // Keep form data on error
        $member = $data;
    }
}

include __DIR__ . '/inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2>
        <i class="bi bi-<?php echo $memberId ? 'pencil' : 'plus'; ?>"></i> 
        <?php echo e($pageTitle); ?>
    </h2>
    <a href="<?php echo h($basePath); ?>members.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Torna alla lista
    </a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <strong>Errori:</strong>
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo e($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($memberId): ?>
    <div class="card mb-3 border-info">
        <div class="card-header bg-info text-white">
            <h6 class="mb-0"><i class="bi bi-door-open"></i> Accesso Portale Soci</h6>
        </div>
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <?php if (!empty($member['portal_password'])): ?>
                        <p class="mb-2">
                            <i class="bi bi-check-circle text-success"></i> 
                            <strong>Account attivato</strong>
                        </p>
                        <?php if ($member['last_portal_login']): ?>
                            <p class="text-muted small mb-0">
                                Ultimo accesso: <?php echo formatDate($member['last_portal_login']); ?>
                            </p>
                        <?php else: ?>
                            <p class="text-muted small mb-0">
                                Account attivato ma mai utilizzato
                            </p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="mb-2">
                            <i class="bi bi-exclamation-circle text-warning"></i> 
                            <strong>Account non ancora attivato</strong>
                        </p>
                        <p class="text-muted small mb-0">
                            Il socio deve ancora impostare la password per accedere al portale
                        </p>
                    <?php endif; ?>
                </div>
                <div class="col-md-4 text-md-end">
                    <form method="POST" action="?send_activation=1&id=<?php echo $memberId; ?>" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <button type="submit" class="btn btn-info btn-sm" 
                                onclick="return confirm('Inviare email di <?php echo !empty($member['portal_password']) ? 'reset password' : 'attivazione'; ?> a <?php echo h($member['email']); ?>?')">
                            <i class="bi bi-envelope"></i> 
                            <?php echo !empty($member['portal_password']) ? 'Invia Reset Password' : 'Invia Attivazione'; ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<form method="POST">
    <input type="hidden" name="csrf_token" value="<?php echo e(generateCsrfToken()); ?>">
    
    <div class="card mb-3">
        <div class="card-header">
            <h5 class="mb-0">Dati Anagrafici</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">Numero Tessera</label>
                    <input type="text" name="membership_number" class="form-control" value="<?php echo e($member['membership_number']); ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Nome <span class="text-danger">*</span></label>
                    <input type="text" name="first_name" class="form-control" value="<?php echo e($member['first_name']); ?>" required>
                </div>
                <div class="col-md-5 mb-3">
                    <label class="form-label">Cognome <span class="text-danger">*</span></label>
                    <input type="text" name="last_name" class="form-control" value="<?php echo e($member['last_name']); ?>" required>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Codice Fiscale <span class="text-danger">*</span></label>
                    <input type="text" name="fiscal_code" class="form-control text-uppercase" 
                           value="<?php echo e($member['fiscal_code']); ?>" 
                           maxlength="16" pattern="[A-Za-z0-9]{16}" required>
                    <small class="text-muted">16 caratteri alfanumerici</small>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Data di Nascita</label>
                    <input type="date" name="birth_date" class="form-control" value="<?php echo e($member['birth_date']); ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Luogo di Nascita</label>
                    <input type="text" name="birth_place" class="form-control" value="<?php echo e($member['birth_place']); ?>">
                </div>
            </div>
        </div>
    </div>
    
    <div class="card mb-3">
        <div class="card-header">
            <h5 class="mb-0">Contatti</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?php echo e($member['email']); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Telefono</label>
                    <input type="text" name="phone" class="form-control" value="<?php echo e($member['phone']); ?>">
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Indirizzo</label>
                    <input type="text" name="address" class="form-control" value="<?php echo e($member['address']); ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Città</label>
                    <input type="text" name="city" class="form-control" value="<?php echo e($member['city']); ?>">
                </div>
                <div class="col-md-2 mb-3">
                    <label class="form-label">CAP</label>
                    <input type="text" name="postal_code" class="form-control" value="<?php echo e($member['postal_code']); ?>">
                </div>
            </div>
        </div>
    </div>
    
    <div class="card mb-3">
        <div class="card-header">
            <h5 class="mb-0">Informazioni Associazione</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Data Iscrizione</label>
                    <input type="date" name="registration_date" class="form-control" value="<?php echo e($member['registration_date']); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Stato</label>
                    <select name="status" class="form-select" required>
                        <option value="attivo" <?php echo $member['status'] === 'attivo' ? 'selected' : ''; ?>>Attivo</option>
                        <option value="sospeso" <?php echo $member['status'] === 'sospeso' ? 'selected' : ''; ?>>Sospeso</option>
                        <option value="cessato" <?php echo $member['status'] === 'cessato' ? 'selected' : ''; ?>>Cessato</option>
                    </select>
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Note</label>
                <textarea name="notes" class="form-control" rows="3"><?php echo e($member['notes']); ?></textarea>
            </div>
        </div>
    </div>
    
    <div class="card mb-3">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-diagram-3"></i> Gruppi</h5>
        </div>
        <div class="card-body">
            <?php if (empty($allGroups)): ?>
                <p class="text-muted">Nessun gruppo disponibile. <a href="member_groups.php">Crea un gruppo</a></p>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($allGroups as $group): ?>
                        <div class="col-md-4 mb-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" 
                                       name="groups[]" 
                                       value="<?php echo $group['id']; ?>" 
                                       id="group_<?php echo $group['id']; ?>"
                                       <?php echo in_array($group['id'], $memberGroupIds) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="group_<?php echo $group['id']; ?>">
                                    <span class="badge" style="background-color: <?php echo h($group['color']); ?>; width: 12px; height: 12px; display: inline-block; border-radius: 2px;"></span>
                                    <?php echo h($group['name']); ?>
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if ($memberId): ?>
    <!-- Member Fees Section -->
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Quote Associative</h5>
            <a href="<?php echo h($config['app']['base_path']); ?>member_fees.php?member=<?php echo $memberId; ?>" class="btn btn-sm btn-primary">
                <i class="bi bi-credit-card"></i> Gestisci Quote
            </a>
        </div>
        <div class="card-body">
            <?php
            $memberFees = getMemberFees($memberId);
            if (empty($memberFees)):
            ?>
                <p class="text-muted text-center py-3">Nessuna quota registrata per questo socio.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Anno Sociale</th>
                                <th>Importo</th>
                                <th>Scadenza</th>
                                <th>Pagamento</th>
                                <th>Stato</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($memberFees as $fee): ?>
                            <tr>
                                <td><?php echo h($fee['year_name'] ?? 'N/A'); ?></td>
                                <td><?php echo formatAmount($fee['amount']); ?></td>
                                <td><?php echo formatDate($fee['due_date']); ?></td>
                                <td><?php echo $fee['paid_date'] ? formatDate($fee['paid_date']) : '-'; ?></td>
                                <td>
                                    <?php
                                    $statusClass = [
                                        'paid' => 'success',
                                        'pending' => 'warning',
                                        'overdue' => 'danger'
                                    ];
                                    $statusLabel = [
                                        'paid' => 'Pagato',
                                        'pending' => 'In Attesa',
                                        'overdue' => 'Scaduto'
                                    ];
                                    ?>
                                    <span class="badge bg-<?php echo $statusClass[$fee['status']] ?? 'secondary'; ?>">
                                        <?php echo $statusLabel[$fee['status']] ?? $fee['status']; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($memberId): ?>
    <!-- Member Card Section -->
    <div class="card mb-3">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-credit-card"></i> Tessera Socio</h5>
        </div>
        <div class="card-body">
            <?php if ($member['card_token']): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle"></i> 
                    <strong>Tessera generata</strong><br>
                    <small>Generata il <?php echo formatDate(date('Y-m-d', strtotime($member['card_generated_at']))); ?> 
                    alle <?php echo date('H:i', strtotime($member['card_generated_at'])); ?></small>
                </div>
                
                <div class="d-flex gap-2">
                    <a href="<?php echo h($config['app']['base_path']); ?>member_card.php?member_id=<?php echo $memberId; ?>" 
                       class="btn btn-primary">
                        <i class="bi bi-eye"></i> Visualizza Tessera
                    </a>
                    <a href="<?php echo h($config['app']['base_path']); ?>member_card.php?member_id=<?php echo $memberId; ?>" 
                       class="btn btn-outline-warning">
                        <i class="bi bi-arrow-clockwise"></i> Rigenera Tessera
                    </a>
                </div>
            <?php else: ?>
                <p class="text-muted mb-2">
                    <i class="bi bi-info-circle"></i> 
                    Nessuna tessera generata per questo socio.
                </p>
                <a href="<?php echo h($config['app']['base_path']); ?>member_card.php?member_id=<?php echo $memberId; ?>" 
                   class="btn btn-success">
                    <i class="bi bi-plus-circle"></i> Genera Tessera
                </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="d-flex justify-content-between mb-4">
        <a href="<?php echo h($basePath); ?>members.php" class="btn btn-secondary">
            <i class="bi bi-x"></i> Annulla
        </a>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check"></i> Salva
        </button>
    </div>
</form>

<?php include __DIR__ . '/inc/footer.php'; ?>
