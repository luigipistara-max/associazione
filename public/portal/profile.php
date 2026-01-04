<?php
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/functions.php';
require_once __DIR__ . '/inc/auth.php';

$config = require __DIR__ . '/../../src/config.php';
$basePath = $config['app']['base_path'];

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require login
$member = requirePortalLogin();

$pageTitle = 'Il mio Profilo';
$error = '';
$success = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $province = trim($_POST['province'] ?? '');
    $postalCode = trim($_POST['postal_code'] ?? '');
    
    // Validate email
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email non valida';
    } else {
        // Check if email is already used by another member
        $stmt = $pdo->prepare("SELECT id FROM " . table('members') . " WHERE email = ? AND id != ?");
        $stmt->execute([$email, $member['id']]);
        if ($stmt->fetch()) {
            $error = 'Questa email è già utilizzata da un altro socio';
        } else {
            // Update profile
            $stmt = $pdo->prepare("
                UPDATE " . table('members') . " 
                SET email = ?, phone = ?, address = ?, city = ?, province = ?, postal_code = ?
                WHERE id = ?
            ");
            if ($stmt->execute([$email, $phone, $address, $city, $province, $postalCode, $member['id']])) {
                $success = 'Profilo aggiornato con successo!';
                // Reload member data
                $member = getMember($member['id']);
            } else {
                $error = 'Errore durante l\'aggiornamento. Riprova.';
            }
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $error = 'Compila tutti i campi della password';
    } elseif (!password_verify($currentPassword, $member['portal_password'])) {
        $error = 'Password attuale non corretta';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Le nuove password non corrispondono';
    } else {
        // Validate password strength
        $passwordErrors = validatePasswordStrength($newPassword);
        if (!empty($passwordErrors)) {
            $error = implode('<br>', $passwordErrors);
        } else {
            if (setPortalPassword($member['id'], $newPassword)) {
                $success = 'Password modificata con successo!';
            } else {
                $error = 'Errore durante la modifica della password. Riprova.';
            }
        }
    }
}

include __DIR__ . '/inc/header.php';
?>

<div class="row">
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-body text-center">
                <h5 class="card-title mb-3">Fototessera</h5>
                <?php if (!empty($member['photo_url'])): ?>
                    <img src="<?php echo h($member['photo_url']); ?>" alt="Foto" class="member-photo mb-3">
                <?php else: ?>
                    <div class="member-photo-placeholder mb-3">
                        <i class="bi bi-person-circle" style="font-size: 3rem;"></i>
                        <small>Nessuna foto</small>
                    </div>
                <?php endif; ?>
                <div>
                    <a href="<?php echo h($basePath); ?>portal/photo.php" class="btn btn-primary btn-sm">
                        <i class="bi bi-camera"></i> <?php echo !empty($member['photo_url']) ? 'Cambia Foto' : 'Carica Foto'; ?>
                    </a>
                </div>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-info-circle"></i> Informazioni Socio</h6>
            </div>
            <div class="card-body">
                <p class="mb-2">
                    <strong>Numero Tessera:</strong><br>
                    <span class="text-primary"><?php echo h($member['membership_number'] ?? 'N/A'); ?></span>
                </p>
                <p class="mb-2">
                    <strong>Codice Fiscale:</strong><br>
                    <?php echo h($member['fiscal_code']); ?>
                </p>
                <p class="mb-2">
                    <strong>Data di Nascita:</strong><br>
                    <?php echo formatDate($member['birth_date']); ?>
                </p>
                <p class="mb-2">
                    <strong>Luogo di Nascita:</strong><br>
                    <?php echo h($member['birth_place'] ?? 'N/A'); ?>
                </p>
                <p class="mb-2">
                    <strong>Iscritto dal:</strong><br>
                    <?php echo formatDate($member['registration_date']); ?>
                </p>
                <p class="mb-0">
                    <strong>Stato:</strong><br>
                    <span class="badge bg-success"><?php echo h(ucfirst($member['status'])); ?></span>
                </p>
            </div>
        </div>
    </div>
    
    <div class="col-lg-8">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo h($success); ?></div>
        <?php endif; ?>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-person"></i> Dati Personali</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Nome</label>
                            <input type="text" class="form-control" value="<?php echo h($member['first_name']); ?>" disabled>
                            <small class="text-muted">Non modificabile</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Cognome</label>
                            <input type="text" class="form-control" value="<?php echo h($member['last_name']); ?>" disabled>
                            <small class="text-muted">Non modificabile</small>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-control" 
                               value="<?php echo h($member['email']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Telefono</label>
                        <input type="tel" name="phone" class="form-control" 
                               value="<?php echo h($member['phone']); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Indirizzo</label>
                        <input type="text" name="address" class="form-control" 
                               value="<?php echo h($member['address']); ?>">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Città</label>
                            <input type="text" name="city" class="form-control" 
                                   value="<?php echo h($member['city']); ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Provincia</label>
                            <input type="text" name="province" class="form-control" 
                                   value="<?php echo h($member['province']); ?>" maxlength="2">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">CAP</label>
                            <input type="text" name="postal_code" class="form-control" 
                                   value="<?php echo h($member['postal_code']); ?>" maxlength="5">
                        </div>
                    </div>
                    
                    <button type="submit" name="update_profile" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> Salva Modifiche
                    </button>
                </form>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-key"></i> Cambia Password</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Password Attuale</label>
                        <input type="password" name="current_password" class="form-control" 
                               autocomplete="current-password">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Nuova Password</label>
                        <input type="password" name="new_password" class="form-control" 
                               minlength="8" autocomplete="new-password">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Conferma Nuova Password</label>
                        <input type="password" name="confirm_password" class="form-control" 
                               minlength="8" autocomplete="new-password">
                    </div>
                    
                    <div class="alert alert-light">
                        <small>
                            <strong>Requisiti password:</strong>
                            Almeno 8 caratteri, una maiuscola, una minuscola e un numero.
                        </small>
                    </div>
                    
                    <button type="submit" name="change_password" class="btn btn-warning">
                        <i class="bi bi-key"></i> Cambia Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/inc/footer.php'; ?>
