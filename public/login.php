<?php
/**
 * AssoLife Login Page
 * Modern design with gradient and branding
 */

require_once __DIR__ . '/../src/auth.php';

// Load config for site name
$config = require __DIR__ . '/../src/config.php';
$siteName = $config['app']['name'] ?? 'Associazione';

// Redirect if already logged in
if (isLoggedIn()) {
    $basePath = $config['app']['base_path'] ?? '/';
    header('Location: ' . $basePath . 'index.php');
    exit;
}

$error = null;

// Rate limiting configuration
$maxAttempts = 5;
$lockoutTime = 900; // 15 minutes in seconds

// Initialize rate limiting session variables
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}
if (!isset($_SESSION['login_lockout_until'])) {
    $_SESSION['login_lockout_until'] = 0;
}

// Check if user is locked out
$currentTime = time();
if ($_SESSION['login_lockout_until'] > $currentTime) {
    $remainingTime = ceil(($_SESSION['login_lockout_until'] - $currentTime) / 60);
    $error = "Troppi tentativi falliti. Riprova tra $remainingTime minuti.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SESSION['login_lockout_until'] <= $currentTime) {
    $token = $_POST['csrf_token'] ?? '';
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (verifyCsrfToken($token)) {
        if (loginUser($username, $password)) {
            // Reset rate limiting on successful login
            $_SESSION['login_attempts'] = 0;
            $_SESSION['login_lockout_until'] = 0;
            
            $basePath = $config['app']['base_path'] ?? '/';
            header('Location: ' . $basePath . 'index.php');
            exit;
        } else {
            // Increment failed attempts
            $_SESSION['login_attempts']++;
            
            if ($_SESSION['login_attempts'] >= $maxAttempts) {
                $_SESSION['login_lockout_until'] = $currentTime + $lockoutTime;
                $error = 'Troppi tentativi falliti. Account bloccato per 15 minuti.';
            } else {
                $remainingAttempts = $maxAttempts - $_SESSION['login_attempts'];
                $error = "Username o password non validi. Tentativi rimasti: $remainingAttempts";
            }
        }
    } else {
        $error = 'Token di sicurezza non valido';
    }
}

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo htmlspecialchars($siteName); ?></title>
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
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            max-width: 420px;
            width: 100%;
        }
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        .login-header i {
            font-size: 4rem;
            margin-bottom: 15px;
        }
        .login-body {
            padding: 40px 30px;
        }
        .form-floating > .form-control {
            border-radius: 10px;
        }
        .btn-login {
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        .btn-login:hover {
            background: linear-gradient(135deg, #5568d3 0%, #6a3f8f 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .login-footer {
            background: #f8f9fa;
            padding: 20px;
            text-align: center;
            color: #6c757d;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <i class="bi bi-people-fill"></i>
            <h2 class="mb-0"><?php echo htmlspecialchars($siteName); ?></h2>
            <p class="mb-0 mt-2">Sistema di Gestione Associativa</p>
        </div>
        
        <div class="login-body">
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-floating mb-3">
                    <input type="text" class="form-control" id="username" name="username" placeholder="Username" required autofocus>
                    <label for="username"><i class="bi bi-person"></i> Username</label>
                </div>

                <div class="form-floating mb-4">
                    <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                    <label for="password"><i class="bi bi-lock"></i> Password</label>
                </div>

                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

                <button class="w-100 btn btn-primary btn-login" type="submit">
                    <i class="bi bi-box-arrow-in-right"></i> Accedi
                </button>
            </form>
        </div>
        
        <div class="login-footer">
            © <?php echo date('Y'); ?> <?php echo htmlspecialchars($siteName); ?><br>
            Powered with <strong>AssoLife</strong> by Luigi Pistarà
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
