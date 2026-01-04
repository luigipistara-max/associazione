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

// Already logged in?
if (isPortalLoggedIn()) {
    header('Location: ' . $basePath . 'portal/index.php');
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Inserisci email e password';
    } else {
        $result = portalLogin($email, $password);
        
        if ($result['success']) {
            header('Location: ' . $basePath . 'portal/index.php');
            exit;
        } else {
            $error = $result['error'];
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
    <title>Accesso Soci - <?php echo h($siteName); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-card {
            max-width: 400px;
            margin: 0 auto;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            border-radius: 15px;
        }
        .login-header {
            background: #f8f9fa;
            border-radius: 15px 15px 0 0;
            padding: 30px;
            text-align: center;
        }
        .login-header img {
            max-height: 80px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card login-card">
            <div class="login-header">
                <?php if (!empty($assocInfo['logo'])): ?>
                    <img src="<?php echo h($basePath . $assocInfo['logo']); ?>" alt="Logo">
                <?php endif; ?>
                <h4><?php echo h($siteName); ?></h4>
                <p class="text-muted mb-0">Area Riservata Soci</p>
            </div>
            <div class="card-body p-4">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo h($error); ?></div>
                <?php endif; ?>
                
                <?php if (isset($_GET['registered'])): ?>
                    <div class="alert alert-success">Account attivato con successo! Effettua il login.</div>
                <?php endif; ?>
                
                <?php if (isset($_GET['reset'])): ?>
                    <div class="alert alert-success">Password reimpostata con successo! Effettua il login.</div>
                <?php endif; ?>
                
                <?php if (isset($_GET['error']) && $_GET['error'] === 'inactive'): ?>
                    <div class="alert alert-warning">Il tuo account non è più attivo.</div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" 
                               value="<?php echo h($email); ?>" required autofocus>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100 mb-3">
                        <i class="bi bi-box-arrow-in-right"></i> Accedi
                    </button>
                </form>
                
                <div class="text-center">
                    <a href="<?php echo h($basePath); ?>portal/forgot_password.php" class="text-decoration-none">
                        Password dimenticata?
                    </a>
                </div>
            </div>
            <div class="card-footer text-center text-muted small">
                <a href="<?php echo h($basePath); ?>index.php" class="text-decoration-none">
                    <i class="bi bi-arrow-left"></i> Torna al sito
                </a>
            </div>
        </div>
    </div>
</body>
</html>
