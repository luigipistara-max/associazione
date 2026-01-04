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

$error = '';
$success = false;
$member = null;
$token = $_GET['token'] ?? '';

// Verify token
if (empty($token)) {
    $error = 'Token mancante. Usa il link ricevuto via email.';
} else {
    $member = verifyPortalToken($token);
    if (!$member) {
        $error = 'Token non valido o scaduto. Richiedi un nuovo link di attivazione.';
    } elseif (!empty($member['portal_password'])) {
        $error = 'Account già attivato. Effettua il login.';
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $member) {
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($password) || empty($confirmPassword)) {
        $error = 'Inserisci la password e la conferma';
    } elseif ($password !== $confirmPassword) {
        $error = 'Le password non corrispondono';
    } else {
        // Validate password strength
        $passwordErrors = validatePasswordStrength($password);
        if (!empty($passwordErrors)) {
            $error = implode('<br>', $passwordErrors);
        } else {
            // Set password
            if (setPortalPassword($member['id'], $password)) {
                $success = true;
            } else {
                $error = 'Errore durante l\'attivazione. Riprova.';
            }
        }
    }
}

// Get association info for branding
$assocInfo = getAssociationInfo();
$siteName = $assocInfo['name'] ?? 'Associazione';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attivazione Account - <?php echo h($siteName); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .register-card {
            max-width: 500px;
            margin: 0 auto;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            border-radius: 15px;
        }
        .register-header {
            background: #f8f9fa;
            border-radius: 15px 15px 0 0;
            padding: 30px;
            text-align: center;
        }
        .register-header img {
            max-height: 80px;
            margin-bottom: 15px;
        }
        .password-requirements {
            font-size: 0.875rem;
            color: #6c757d;
        }
        .password-requirements li {
            margin-bottom: 0.25rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card register-card">
            <div class="register-header">
                <?php if (!empty($assocInfo['logo'])): ?>
                    <img src="<?php echo h($basePath . $assocInfo['logo']); ?>" alt="Logo">
                <?php endif; ?>
                <h4>Attivazione Account</h4>
                <p class="text-muted mb-0"><?php echo h($siteName); ?></p>
            </div>
            <div class="card-body p-4">
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle"></i> Account attivato con successo!
                    </div>
                    <p class="text-center">
                        <a href="<?php echo h($basePath); ?>portal/login.php" class="btn btn-primary">
                            <i class="bi bi-box-arrow-in-right"></i> Vai al Login
                        </a>
                    </p>
                <?php elseif ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php if (strpos($error, 'già attivato') !== false): ?>
                        <p class="text-center">
                            <a href="<?php echo h($basePath); ?>portal/login.php" class="btn btn-primary">
                                <i class="bi bi-box-arrow-in-right"></i> Vai al Login
                            </a>
                        </p>
                    <?php endif; ?>
                <?php elseif ($member): ?>
                    <div class="alert alert-info">
                        <strong>Benvenuto/a <?php echo h($member['first_name'] . ' ' . $member['last_name']); ?>!</strong><br>
                        Imposta una password per attivare il tuo account.
                    </div>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required 
                                   minlength="8" autocomplete="new-password">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Conferma Password</label>
                            <input type="password" name="confirm_password" class="form-control" required 
                                   minlength="8" autocomplete="new-password">
                        </div>
                        
                        <div class="alert alert-light password-requirements">
                            <strong>Requisiti password:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Almeno 8 caratteri</li>
                                <li>Almeno una lettera maiuscola</li>
                                <li>Almeno una lettera minuscola</li>
                                <li>Almeno un numero</li>
                            </ul>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-check-circle"></i> Attiva Account
                        </button>
                    </form>
                <?php endif; ?>
            </div>
            <div class="card-footer text-center text-muted small">
                <a href="<?php echo h($basePath); ?>portal/login.php" class="text-decoration-none">
                    <i class="bi bi-arrow-left"></i> Torna al login
                </a>
            </div>
        </div>
    </div>
</body>
</html>
