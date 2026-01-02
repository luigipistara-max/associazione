<?php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/db.php';

requireLogin();


$memberId = $_GET['id'] ?? null;
$member = null;
$errors = [];

// Load existing member
if ($memberId) {
    $stmt = $pdo->prepare("SELECT * FROM " . table('members') . " WHERE id = ?");
    $stmt->execute([$memberId]);
    $member = $stmt->fetch();
    
    if (!$member) {
        setFlashMessage('Socio non trovato', 'danger');
        redirect('/members.php');
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
                    setFlashMessage('Socio aggiunto con successo');
                }
                redirect('/members.php');
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
    <a href="/members.php" class="btn btn-secondary">
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
    
    <div class="d-flex justify-content-between mb-4">
        <a href="/members.php" class="btn btn-secondary">
            <i class="bi bi-x"></i> Annulla
        </a>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check"></i> Salva
        </button>
    </div>
</form>

<?php include __DIR__ . '/inc/footer.php'; ?>
