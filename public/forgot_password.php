<?php
/**
 * Forgot Password - Request password reset
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
$resetLink = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $email = trim($_POST['email'] ?? '');
    
    if (!verifyCsrfToken($token)) {
        $error = 'Token di sicurezza non valido';
    } elseif (empty($email)) {
        $error = 'Email obbligatoria';
    } else {
        // Find user by email
        $stmt = $pdo->prepare("SELECT id, username, email FROM " . table('users') . " WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Generate secure token
            $resetToken = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Save token to database
            $stmt = $pdo->prepare("
                INSERT INTO " . table('password_resets') . " 
                (user_id, token, expires_at) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$user['id'], $resetToken, $expiresAt]);
            
            // Build reset link
            $resetLink = $basePath . 'reset_password.php?token=' . urlencode($resetToken);
            
            // Try to send email
            require_once __DIR__ . '/../src/email.php';
            $emailConfig = $config['email'] ?? [];
            
            if ($emailConfig['enabled'] ?? false) {
                // Use email system
                $variables = [
                    'nome' => $user['username'],
                    'cognome' => '',
                    'link' => $resetLink
                ];
                
                $emailSent = sendEmailFromTemplate($email, 'password_reset', $variables);
                
                if ($emailSent) {
                    // Email sent successfully
                    $resetLink = null; // Don't show link if email was sent
                }
            }
            
            $success = true;
        } else {
            // Don't reveal if email exists or not (security best practice)
            // Just show success message
            $success = true;
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
    <title>Recupero Password - <?php echo h($siteName); ?></title>
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
        .recovery-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            max-width: 500px;
            width: 100%;
        }
        .recovery-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        .recovery-header i {
            font-size: 3rem;
            margin-bottom: 15px;
        }
        .recovery-body {
            padding: 40px 30px;
        }
        .btn-recovery {
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        .btn-recovery:hover {
            background: linear-gradient(135deg, #5568d3 0%, #6a3f8f 100%);
        }
    </style>
</head>
<body>
    <div class="recovery-card">
        <div class="recovery-header">
            <i class="bi bi-key"></i>
            <h2 class="mb-0">Recupero Password</h2>
            <p class="mb-0 mt-2"><?php echo h($siteName); ?></p>
        </div>
        
        <div class="recovery-body">
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle"></i> <?php echo h($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <h5><i class="bi bi-check-circle"></i> Richiesta Inviata</h5>
                    <p class="mb-0">
                        Se l'email è registrata nel sistema, riceverai le istruzioni per reimpostare la password.
                    </p>
                </div>
                
                <?php if ($resetLink): ?>
                    <div class="alert alert-info">
                        <strong><i class="bi bi-info-circle"></i> Modalità Development</strong><br>
                        <small>In produzione, questo link verrebbe inviato via email.</small><br>
                        <a href="<?php echo h($resetLink); ?>" class="btn btn-sm btn-primary mt-2">
                            <i class="bi bi-link-45deg"></i> Vai al Reset Password
                        </a>
                        <div class="mt-2">
                            <small class="text-muted">Oppure copia il link:</small><br>
                            <input type="text" class="form-control form-control-sm mt-1" 
                                   value="<?php echo h($resetLink); ?>" readonly 
                                   onclick="this.select()">
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="text-center mt-3">
                    <a href="<?php echo h($basePath); ?>login.php" class="btn btn-outline-primary">
                        <i class="bi bi-arrow-left"></i> Torna al Login
                    </a>
                </div>
            <?php else: ?>
                <p class="text-muted">
                    Inserisci l'indirizzo email associato al tuo account. 
                    Riceverai un link per reimpostare la password.
                </p>

                <form method="POST">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               placeholder="tua@email.com" required autofocus>
                    </div>

                    <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">

                    <button class="w-100 btn btn-primary btn-recovery mb-3" type="submit">
                        <i class="bi bi-envelope"></i> Invia Link di Recupero
                    </button>
                    
                    <div class="text-center">
                        <a href="<?php echo h($basePath); ?>login.php" class="text-muted">
                            <i class="bi bi-arrow-left"></i> Torna al Login
                        </a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
