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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = 'Inserisci la tua email';
    } else {
        // Look for member with this email
        $stmt = $pdo->prepare("SELECT * FROM " . table('members') . " WHERE email = ? AND status = 'attivo'");
        $stmt->execute([$email]);
        $member = $stmt->fetch();
        
        if ($member) {
            // Send password reset email
            if (sendPortalPasswordResetEmail($member['id'])) {
                $success = true;
            } else {
                $error = 'Errore durante l\'invio dell\'email. Riprova più tardi.';
            }
        } else {
            // Don't reveal if email exists or not (security)
            $success = true;
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
    <title>Recupero Password - <?php echo h($siteName); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .forgot-card {
            max-width: 450px;
            margin: 0 auto;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            border-radius: 15px;
        }
        .forgot-header {
            background: #f8f9fa;
            border-radius: 15px 15px 0 0;
            padding: 30px;
            text-align: center;
        }
        .forgot-header img {
            max-height: 80px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card forgot-card">
            <div class="forgot-header">
                <?php if (!empty($assocInfo['logo'])): ?>
                    <img src="<?php echo h($basePath . $assocInfo['logo']); ?>" alt="Logo">
                <?php endif; ?>
                <h4>Recupero Password</h4>
                <p class="text-muted mb-0"><?php echo h($siteName); ?></p>
            </div>
            <div class="card-body p-4">
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle"></i> 
                        <strong>Email inviata!</strong><br>
                        Se l'indirizzo email è registrato, riceverai un link per reimpostare la password.
                        Controlla anche la cartella spam.
                    </div>
                    <p class="text-center">
                        <a href="<?php echo h($basePath); ?>portal/login.php" class="btn btn-primary">
                            <i class="bi bi-arrow-left"></i> Torna al Login
                        </a>
                    </p>
                <?php else: ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo h($error); ?></div>
                    <?php endif; ?>
                    
                    <p class="text-muted">
                        Inserisci il tuo indirizzo email. Ti invieremo un link per reimpostare la password.
                    </p>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" 
                                   required autofocus placeholder="tua@email.com">
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-envelope"></i> Invia Link di Recupero
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
