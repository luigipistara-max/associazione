<?php
/**
 * Reset Password - Set new password with token
 */

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/db.php';

$config = require __DIR__ . '/../src/config.php';
$siteName = $config['app']['name'] ?? 'Associazione';
$basePath = $config['app']['base_path'];

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ' . $basePath . 'index.php');
    exit;
}

$error = null;
$success = false;
$token = $_GET['token'] ?? $_POST['token'] ?? '';
$validToken = false;
$userId = null;

// Validate token
if (!empty($token)) {
    $stmt = $pdo->prepare("
        SELECT pr.*, u.username, u.email
        FROM " . table('password_resets') . " pr
        JOIN " . table('users') . " u ON pr.user_id = u.id
        WHERE pr.token = ? 
        AND pr.expires_at > NOW()
        AND pr.used_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $resetRecord = $stmt->fetch();
    
    if ($resetRecord) {
        $validToken = true;
        $userId = $resetRecord['user_id'];
    } else {
        $error = 'Token non valido o scaduto. Richiedi un nuovo link di recupero.';
    }
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    
    if (!verifyCsrfToken($csrfToken)) {
        $error = 'Token di sicurezza non valido';
    } elseif (empty($password)) {
        $error = 'La password è obbligatoria';
    } elseif (strlen($password) < 8) {
        $error = 'La password deve essere di almeno 8 caratteri';
    } elseif ($password !== $passwordConfirm) {
        $error = 'Le password non corrispondono';
    } else {
        try {
            // Update password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE " . table('users') . " SET password = ? WHERE id = ?");
            $stmt->execute([$hashedPassword, $userId]);
            
            // Mark token as used
            $stmt = $pdo->prepare("UPDATE " . table('password_resets') . " SET used_at = NOW() WHERE token = ?");
            $stmt->execute([$token]);
            
            $success = true;
        } catch (PDOException $e) {
            $error = 'Errore durante il reset della password: ' . $e->getMessage();
        }
    }
}

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reimposta Password - <?php echo h($siteName); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .reset-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            max-width: 500px;
            width: 100%;
        }
        .reset-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        .reset-header i {
            font-size: 3rem;
            margin-bottom: 15px;
        }
        .reset-body {
            padding: 40px 30px;
        }
        .btn-reset {
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        .btn-reset:hover {
            background: linear-gradient(135deg, #5568d3 0%, #6a3f8f 100%);
        }
        .password-strength {
            height: 5px;
            margin-top: 5px;
            border-radius: 3px;
            background-color: #e9ecef;
        }
        .password-strength.weak { background-color: #dc3545; }
        .password-strength.medium { background-color: #ffc107; }
        .password-strength.strong { background-color: #28a745; }
    </style>
</head>
<body>
    <div class="reset-card">
        <div class="reset-header">
            <i class="bi bi-shield-lock"></i>
            <h2 class="mb-0">Reimposta Password</h2>
            <p class="mb-0 mt-2"><?php echo h($siteName); ?></p>
        </div>
        
        <div class="reset-body">
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle"></i> <?php echo h($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <h5><i class="bi bi-check-circle"></i> Password Reimpostata</h5>
                    <p class="mb-0">
                        La tua password è stata reimpostata con successo.
                        Ora puoi effettuare il login con la nuova password.
                    </p>
                </div>
                
                <div class="text-center">
                    <a href="<?php echo h($basePath); ?>login.php" class="btn btn-primary btn-reset">
                        <i class="bi bi-box-arrow-in-right"></i> Vai al Login
                    </a>
                </div>
            <?php elseif ($validToken): ?>
                <p class="text-muted">
                    Inserisci la tua nuova password. Deve essere di almeno 8 caratteri.
                </p>

                <form method="POST" id="resetForm">
                    <input type="hidden" name="token" value="<?php echo h($token); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">

                    <div class="mb-3">
                        <label for="password" class="form-label">Nuova Password</label>
                        <input type="password" class="form-control" id="password" name="password" 
                               minlength="8" required autofocus>
                        <div class="password-strength" id="strength"></div>
                        <small class="text-muted">Minimo 8 caratteri</small>
                    </div>

                    <div class="mb-4">
                        <label for="password_confirm" class="form-label">Conferma Password</label>
                        <input type="password" class="form-control" id="password_confirm" 
                               name="password_confirm" minlength="8" required>
                    </div>

                    <button class="w-100 btn btn-primary btn-reset mb-3" type="submit">
                        <i class="bi bi-check-circle"></i> Reimposta Password
                    </button>
                    
                    <div class="text-center">
                        <a href="<?php echo h($basePath); ?>login.php" class="text-muted">
                            <i class="bi bi-arrow-left"></i> Torna al Login
                        </a>
                    </div>
                </form>
            <?php else: ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i>
                    Il link di recupero non è valido o è scaduto.
                </div>
                
                <div class="text-center">
                    <a href="<?php echo h($basePath); ?>forgot_password.php" class="btn btn-primary">
                        <i class="bi bi-envelope"></i> Richiedi Nuovo Link
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password strength indicator
        const passwordInput = document.getElementById('password');
        const strengthBar = document.getElementById('strength');
        
        if (passwordInput && strengthBar) {
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                let strength = 0;
                
                if (password.length >= 8) strength++;
                if (password.length >= 12) strength++;
                if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
                if (/[0-9]/.test(password)) strength++;
                if (/[^a-zA-Z0-9]/.test(password)) strength++;
                
                strengthBar.className = 'password-strength';
                if (strength >= 4) {
                    strengthBar.classList.add('strong');
                } else if (strength >= 2) {
                    strengthBar.classList.add('medium');
                } else if (password.length > 0) {
                    strengthBar.classList.add('weak');
                }
            });
        }
        
        // Password match validation
        const form = document.getElementById('resetForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                const password = document.getElementById('password').value;
                const confirm = document.getElementById('password_confirm').value;
                
                if (password !== confirm) {
                    e.preventDefault();
                    alert('Le password non corrispondono');
                }
            });
        }
    </script>
</body>
</html>
