<?php
/**
 * Installation Wizard
 * Creates database tables and initial admin user
 */

session_start();

// Check if already installed
if (file_exists(__DIR__ . '/../src/config_local.php')) {
    $installed = true;
} else {
    $installed = false;
}

$step = $_GET['step'] ?? 1;
$error = null;
$success = null;

// Step 2: Test database connection and create tables
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step == 2) {
    $dbHost = $_POST['db_host'] ?? '';
    $dbName = $_POST['db_name'] ?? '';
    $dbUser = $_POST['db_user'] ?? '';
    $dbPass = $_POST['db_pass'] ?? '';
    
    try {
        $dsn = "mysql:host=$dbHost;charset=utf8mb4";
        $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        
        // Create database if not exists
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$dbName`");
        
        // Read and execute schema
        $schema = file_get_contents(__DIR__ . '/../schema.sql');
        $statements = array_filter(array_map('trim', explode(';', $schema)));
        
        foreach ($statements as $statement) {
            if (!empty($statement) && !preg_match('/^--/', $statement)) {
                $pdo->exec($statement);
            }
        }
        
        // Store database credentials
        $_SESSION['install_db'] = [
            'host' => $dbHost,
            'name' => $dbName,
            'user' => $dbUser,
            'pass' => $dbPass
        ];
        
        header('Location: install.php?step=3');
        exit;
        
    } catch (PDOException $e) {
        $error = "Errore database: " . $e->getMessage();
    }
}

// Step 3: Create admin user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step == 3) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = "Username e password sono obbligatori";
    } elseif ($password !== $passwordConfirm) {
        $error = "Le password non corrispondono";
    } elseif (strlen($password) < 8) {
        $error = "La password deve essere di almeno 8 caratteri";
    } else {
        try {
            $db = $_SESSION['install_db'];
            $dsn = "mysql:host={$db['host']};dbname={$db['name']};charset=utf8mb4";
            $pdo = new PDO($dsn, $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            
            // Create admin user
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'admin')");
            $stmt->execute([$username, $hashedPassword]);
            
            // Create config_local.php
            $configContent = "<?php\n";
            $configContent .= "define('DB_HOST', " . var_export($db['host'], true) . ");\n";
            $configContent .= "define('DB_NAME', " . var_export($db['name'], true) . ");\n";
            $configContent .= "define('DB_USER', " . var_export($db['user'], true) . ");\n";
            $configContent .= "define('DB_PASS', " . var_export($db['pass'], true) . ");\n";
            $configContent .= "define('DB_CHARSET', 'utf8mb4');\n";
            
            file_put_contents(__DIR__ . '/../src/config_local.php', $configContent);
            
            unset($_SESSION['install_db']);
            header('Location: install.php?step=4');
            exit;
            
        } catch (PDOException $e) {
            $error = "Errore nella creazione dell'utente: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installazione - Gestione Associazione</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="bi bi-gear"></i> Installazione Gestione Associazione</h4>
                    </div>
                    <div class="card-body">
                        
                        <?php if ($installed && $step != 4): ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle"></i> L'applicazione è già stata installata.
                                <a href="index.php" class="alert-link">Vai alla dashboard</a>
                            </div>
                        
                        <?php elseif ($step == 1): ?>
                            <h5>Benvenuto!</h5>
                            <p>Questa procedura guidata ti aiuterà a installare il sistema di gestione dell'associazione.</p>
                            <p>Assicurati di avere:</p>
                            <ul>
                                <li>Un database MySQL o MariaDB pronto</li>
                                <li>Le credenziali di accesso al database</li>
                                <li>PHP 7.4 o superiore</li>
                            </ul>
                            
                            <div class="alert alert-info">
                                <strong>Nota per AlterVista:</strong> Il nome del database è solitamente <code>my_nomeUtente</code>.
                                Le credenziali sono disponibili nel pannello di controllo.
                            </div>
                            
                            <a href="install.php?step=2" class="btn btn-primary">
                                <i class="bi bi-arrow-right"></i> Avanti
                            </a>
                        
                        <?php elseif ($step == 2): ?>
                            <h5>Configurazione Database</h5>
                            
                            <?php if ($error): ?>
                                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                            <?php endif; ?>
                            
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Host Database</label>
                                    <input type="text" name="db_host" class="form-control" value="localhost" required>
                                    <small class="text-muted">Di solito: localhost</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Nome Database</label>
                                    <input type="text" name="db_name" class="form-control" required>
                                    <small class="text-muted">Es: associazione o my_username su AlterVista</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Username Database</label>
                                    <input type="text" name="db_user" class="form-control" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Password Database</label>
                                    <input type="password" name="db_pass" class="form-control">
                                    <small class="text-muted">Lascia vuoto se non c'è password</small>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-arrow-right"></i> Crea Tabelle
                                </button>
                            </form>
                        
                        <?php elseif ($step == 3): ?>
                            <h5>Crea Amministratore</h5>
                            
                            <?php if ($error): ?>
                                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                            <?php endif; ?>
                            
                            <p>Crea l'account amministratore principale:</p>
                            
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Username</label>
                                    <input type="text" name="username" class="form-control" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Password</label>
                                    <input type="password" name="password" class="form-control" required minlength="8">
                                    <small class="text-muted">Minimo 8 caratteri</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Conferma Password</label>
                                    <input type="password" name="password_confirm" class="form-control" required minlength="8">
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check"></i> Completa Installazione
                                </button>
                            </form>
                        
                        <?php elseif ($step == 4): ?>
                            <div class="alert alert-success">
                                <h5 class="alert-heading"><i class="bi bi-check-circle"></i> Installazione Completata!</h5>
                                <p>Il sistema è stato installato correttamente.</p>
                                <hr>
                                <p class="mb-0">
                                    <a href="login.php" class="btn btn-primary">
                                        <i class="bi bi-box-arrow-in-right"></i> Vai al Login
                                    </a>
                                </p>
                            </div>
                            
                            <div class="alert alert-warning mt-3">
                                <strong>Importante:</strong> Per motivi di sicurezza, considera di eliminare o rinominare il file <code>install.php</code> dopo l'installazione.
                            </div>
                        <?php endif; ?>
                        
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
