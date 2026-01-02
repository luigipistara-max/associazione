<?php
/**
 * AssoLife Installation Wizard
 * 3-step installation process with table prefix support
 */

session_start();

// Check if already installed
if (file_exists(__DIR__ . '/../src/config.generated.php')) {
    $installed = true;
} else {
    $installed = false;
}

$step = $_GET['step'] ?? 1;
$error = null;
$success = null;
$debugMode = isset($_GET['debug']) && $_GET['debug'] == '1';
$debugInfo = [];

// Auto-detect base path
function detectBasePath() {
    $scriptName = $_SERVER['SCRIPT_NAME'];
    $basePath = dirname(dirname($scriptName));
    if ($basePath === '/' || $basePath === '\\') {
        return '/';
    }
    return rtrim($basePath, '/') . '/';
}

// Step 1: Database Configuration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step == 1) {
    $dbHost = $_POST['db_host'] ?? '';
    $dbName = $_POST['db_name'] ?? '';
    $dbUser = $_POST['db_user'] ?? '';
    $dbPass = $_POST['db_pass'] ?? '';
    $dbPrefix = $_POST['db_prefix'] ?? '';
    
    // Validate database name (alphanumeric and underscore only)
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $dbName)) {
        $error = "Il nome del database può contenere solo lettere, numeri e underscore";
    }
    // Validate and sanitize prefix (only alphanumeric and underscore)
    elseif (!empty($dbPrefix) && !preg_match('/^[a-zA-Z0-9_]+$/', $dbPrefix)) {
        $error = "Il prefisso può contenere solo lettere, numeri e underscore";
    } else {
        try {
            // Test connection
            $dsn = "mysql:host=" . $dbHost . ";charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            
            // Create database with fallback for compatibility
            try {
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . $dbName . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                if ($debugMode) $debugInfo[] = "Database created with utf8mb4";
            } catch (PDOException $e) {
                // Fallback to utf8 for compatibility
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . $dbName . "` CHARACTER SET utf8 COLLATE utf8_general_ci");
                if ($debugMode) $debugInfo[] = "Database created with utf8 (fallback)";
            }
            
            $pdo->exec("USE `" . $dbName . "`");
            
            // Read schema and apply prefix
            $schema = file_get_contents(__DIR__ . '/../schema.sql');
            
            // Apply prefix to table names in CREATE TABLE statements
            if (!empty($dbPrefix)) {
                $schema = preg_replace('/CREATE TABLE IF NOT EXISTS (\w+)/i', 'CREATE TABLE IF NOT EXISTS ' . $dbPrefix . '$1', $schema);
                
                // Apply prefix to INSERT INTO statements
                $schema = preg_replace('/INSERT INTO (\w+)/i', 'INSERT INTO ' . $dbPrefix . '$1', $schema);
            }
            
            // Execute schema with individual error handling
            $statements = array_filter(array_map('trim', explode(';', $schema)));
            $failedStatements = [];
            
            foreach ($statements as $statement) {
                // Skip empty statements
                if (empty($statement)) {
                    continue;
                }
                
                // Remove comment lines from the statement
                $lines = explode("\n", $statement);
                $cleanLines = [];
                foreach ($lines as $line) {
                    $trimmedLine = trim($line);
                    if (!empty($trimmedLine) && !preg_match('/^--/', $trimmedLine)) {
                        $cleanLines[] = $line;
                    }
                }
                $cleanStatement = implode("\n", $cleanLines);
                
                // Skip if nothing left after removing comments
                if (empty(trim($cleanStatement))) {
                    continue;
                }
                
                try {
                    $pdo->exec($cleanStatement);
                    if ($debugMode) {
                        $debugInfo[] = "✓ Executed: " . substr($cleanStatement, 0, 100) . "...";
                    }
                } catch (PDOException $e) {
                    // Try fallback without ENGINE=InnoDB for compatibility
                    if (stripos($cleanStatement, 'ENGINE=InnoDB') !== false || stripos($cleanStatement, 'ENGINE = InnoDB') !== false) {
                        $fallbackStatement = preg_replace('/ENGINE\s*=\s*InnoDB/i', '', $cleanStatement);
                        try {
                            $pdo->exec($fallbackStatement);
                            if ($debugMode) {
                                $debugInfo[] = "✓ Executed (no InnoDB): " . substr($fallbackStatement, 0, 100) . "...";
                            }
                        } catch (PDOException $e2) {
                            $failedStatements[] = [
                                'statement' => substr($cleanStatement, 0, 200),
                                'error' => $e2->getMessage()
                            ];
                            if ($debugMode) {
                                $debugInfo[] = "✗ Failed: " . $e2->getMessage();
                            }
                        }
                    } else {
                        $failedStatements[] = [
                            'statement' => substr($cleanStatement, 0, 200),
                            'error' => $e->getMessage()
                        ];
                        if ($debugMode) {
                            $debugInfo[] = "✗ Failed: " . $e->getMessage();
                        }
                    }
                }
            }
            
            // Verify tables were created
            $requiredTables = ['users', 'password_resets', 'members', 'social_years', 'income_categories', 'expense_categories', 'income', 'expenses', 'member_fees', 'email_templates', 'email_queue', 'email_log', 'audit_log'];
            $missingTables = [];
            
            foreach ($requiredTables as $table) {
                $tableName = $dbPrefix . $table;
                $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
                $stmt->execute([$tableName]);
                if ($stmt->rowCount() === 0) {
                    $missingTables[] = $tableName;
                } else {
                    if ($debugMode) {
                        $debugInfo[] = "✓ Table exists: $tableName";
                    }
                }
            }
            
            // Check if installation was successful
            if (!empty($missingTables)) {
                $escapedTables = array_map('htmlspecialchars', $missingTables);
                $error = "Errore: le seguenti tabelle non sono state create: " . implode(', ', $escapedTables);
                if (!empty($failedStatements)) {
                    $error .= "<br><br><strong>Errori SQL:</strong><ul>";
                    foreach ($failedStatements as $failed) {
                        $error .= "<li><code>" . htmlspecialchars($failed['statement']) . "</code><br>";
                        $error .= "<small>Errore: " . htmlspecialchars($failed['error']) . "</small></li>";
                    }
                    $error .= "</ul>";
                }
            } else {
                // All tables created successfully
                $_SESSION['install_db'] = [
                    'host' => $dbHost,
                    'name' => $dbName,
                    'user' => $dbUser,
                    'pass' => $dbPass,
                    'prefix' => $dbPrefix
                ];
                
                $redirectUrl = 'install.php?step=2';
                if ($debugMode) {
                    $redirectUrl .= '&debug=1';
                }
                header('Location: ' . $redirectUrl);
                exit;
            }
            
        } catch (PDOException $e) {
            $error = "Errore database: " . $e->getMessage();
        }
    }
}

// Step 2: Site Configuration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step == 2) {
    $siteName = $_POST['site_name'] ?? 'Associazione';
    $basePath = $_POST['base_path'] ?? detectBasePath();
    $forceHttps = isset($_POST['force_https']) ? 'true' : 'false';
    
    $_SESSION['install_site'] = [
        'name' => $siteName,
        'base_path' => $basePath,
        'force_https' => $forceHttps
    ];
    
    header('Location: install.php?step=3');
    exit;
}

// Step 3: Create Admin User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step == 3) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    $fullName = $_POST['full_name'] ?? '';
    $email = $_POST['email'] ?? '';
    
    if (empty($username) || empty($password) || empty($fullName)) {
        $error = "Username, password e nome completo sono obbligatori";
    } elseif ($password !== $passwordConfirm) {
        $error = "Le password non corrispondono";
    } elseif (strlen($password) < 8) {
        $error = "La password deve essere di almeno 8 caratteri";
    } else {
        try {
            $db = $_SESSION['install_db'];
            $site = $_SESSION['install_site'];
            
            // Connect to database
            $dsn = "mysql:host={$db['host']};dbname={$db['name']};charset=utf8mb4";
            $pdo = new PDO($dsn, $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            
            // Create admin user
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $tableName = $db['prefix'] . 'users';
            $stmt = $pdo->prepare("INSERT INTO `$tableName` (username, password, full_name, email, role) VALUES (?, ?, ?, ?, 'admin')");
            $stmt->execute([$username, $hashedPassword, $fullName, $email]);
            
            // Create config.generated.php
            $configContent = "<?php\n";
            $configContent .= "/**\n";
            $configContent .= " * AssoLife Generated Configuration\n";
            $configContent .= " * DO NOT EDIT MANUALLY - Generated by installer\n";
            $configContent .= " */\n\n";
            $configContent .= "return [\n";
            $configContent .= "    'db' => [\n";
            $configContent .= "        'host'     => " . var_export($db['host'], true) . ",\n";
            $configContent .= "        'dbname'   => " . var_export($db['name'], true) . ",\n";
            $configContent .= "        'username' => " . var_export($db['user'], true) . ",\n";
            $configContent .= "        'password' => " . var_export($db['pass'], true) . ",\n";
            $configContent .= "        'charset'  => 'utf8mb4',\n";
            $configContent .= "        'prefix'   => " . var_export($db['prefix'], true) . ",\n";
            $configContent .= "    ],\n";
            $configContent .= "    'app' => [\n";
            $configContent .= "        'name'         => " . var_export($site['name'], true) . ",\n";
            $configContent .= "        'version'      => '1.0.0',\n";
            $configContent .= "        'base_path'    => " . var_export($site['base_path'], true) . ",\n";
            $configContent .= "        'force_https'  => " . $site['force_https'] . ",\n";
            $configContent .= "        'session_name' => 'assolife_session',\n";
            $configContent .= "        'timezone'     => 'Europe/Rome',\n";
            $configContent .= "    ],\n";
            $configContent .= "];\n";
            
            file_put_contents(__DIR__ . '/../src/config.generated.php', $configContent);
            
            // Clear session data
            unset($_SESSION['install_db']);
            unset($_SESSION['install_site']);
            
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
    <title>Installazione AssoLife</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px 0;
        }
        .install-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        .step {
            flex: 1;
            text-align: center;
            padding: 10px;
            position: relative;
        }
        .step.active {
            color: #667eea;
            font-weight: bold;
        }
        .step.completed {
            color: #28a745;
        }
        .step::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 100%;
            height: 2px;
            background: #ddd;
            z-index: -1;
        }
        .step:last-child::after {
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="install-card">
                    <div class="card-header bg-gradient text-white text-center py-4" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <h3 class="mb-0">
                            <i class="bi bi-stars"></i> Installazione AssoLife
                        </h3>
                        <p class="mb-0 mt-2">Sistema di Gestione Associativa</p>
                    </div>
                    <div class="card-body p-4">
                        
                        <?php if ($installed && $step != 4): ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle"></i> <strong>AssoLife</strong> è già installato.
                                <a href="login.php" class="alert-link">Vai al login</a>
                            </div>
                        
                        <?php elseif ($step == 1): ?>
                            <div class="step-indicator">
                                <div class="step active">
                                    <i class="bi bi-database"></i><br>
                                    <small>Database</small>
                                </div>
                                <div class="step">
                                    <i class="bi bi-gear"></i><br>
                                    <small>Sito</small>
                                </div>
                                <div class="step">
                                    <i class="bi bi-person"></i><br>
                                    <small>Admin</small>
                                </div>
                            </div>
                            
                            <h5>Step 1: Configurazione Database</h5>
                            
                            <?php if ($error): ?>
                                <div class="alert alert-danger">
                                    <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($debugMode && !empty($debugInfo)): ?>
                                <div class="alert alert-info">
                                    <strong><i class="bi bi-bug"></i> Debug Mode</strong>
                                    <ul class="mt-2 mb-0">
                                        <?php foreach ($debugInfo as $info): ?>
                                            <li><small><?php echo htmlspecialchars($info); ?></small></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
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
                                
                                <div class="mb-3">
                                    <label class="form-label">Prefisso Tabelle (opzionale)</label>
                                    <input type="text" name="db_prefix" class="form-control" placeholder="es: asso_">
                                    <small class="text-muted">Utile se condividi il database con altre applicazioni</small>
                                </div>
                                
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-arrow-right"></i> Avanti
                                </button>
                                
                                <?php if (!$debugMode): ?>
                                    <div class="text-center mt-2">
                                        <small><a href="install.php?step=1&debug=1" class="text-muted"><i class="bi bi-bug"></i> Modalità debug</a></small>
                                    </div>
                                <?php endif; ?>
                            </form>
                        
                        <?php elseif ($step == 2): ?>
                            <div class="step-indicator">
                                <div class="step completed">
                                    <i class="bi bi-check-circle"></i><br>
                                    <small>Database</small>
                                </div>
                                <div class="step active">
                                    <i class="bi bi-gear"></i><br>
                                    <small>Sito</small>
                                </div>
                                <div class="step">
                                    <i class="bi bi-person"></i><br>
                                    <small>Admin</small>
                                </div>
                            </div>
                            
                            <h5>Step 2: Configurazione Sito</h5>
                            
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Nome del Sito</label>
                                    <input type="text" name="site_name" class="form-control" value="Associazione" required>
                                    <small class="text-muted">Apparirà nell'header e nei documenti</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Percorso Base (Base Path)</label>
                                    <input type="text" name="base_path" class="form-control" value="<?php echo htmlspecialchars(detectBasePath()); ?>" required>
                                    <small class="text-muted">Rilevato automaticamente. Modifica solo se necessario.</small>
                                </div>
                                
                                <div class="mb-3 form-check">
                                    <input type="checkbox" name="force_https" class="form-check-input" id="forceHttps">
                                    <label class="form-check-label" for="forceHttps">
                                        Forza HTTPS (raccomandato in produzione)
                                    </label>
                                </div>
                                
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-arrow-right"></i> Avanti
                                </button>
                            </form>
                        
                        <?php elseif ($step == 3): ?>
                            <div class="step-indicator">
                                <div class="step completed">
                                    <i class="bi bi-check-circle"></i><br>
                                    <small>Database</small>
                                </div>
                                <div class="step completed">
                                    <i class="bi bi-check-circle"></i><br>
                                    <small>Sito</small>
                                </div>
                                <div class="step active">
                                    <i class="bi bi-person"></i><br>
                                    <small>Admin</small>
                                </div>
                            </div>
                            
                            <h5>Step 3: Crea Account Amministratore</h5>
                            
                            <?php if ($error): ?>
                                <div class="alert alert-danger">
                                    <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                                </div>
                            <?php endif; ?>
                            
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Username</label>
                                    <input type="text" name="username" class="form-control" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Nome Completo</label>
                                    <input type="text" name="full_name" class="form-control" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" required>
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
                                
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="bi bi-check-circle"></i> Completa Installazione
                                </button>
                            </form>
                        
                        <?php elseif ($step == 4): ?>
                            <div class="text-center py-4">
                                <i class="bi bi-check-circle text-success" style="font-size: 4rem;"></i>
                                <h4 class="mt-3">Installazione Completata!</h4>
                                <p class="text-muted">
                                    <strong>AssoLife</strong> è stato installato correttamente e pronto all'uso.
                                </p>
                                
                                <a href="login.php" class="btn btn-primary btn-lg mt-3">
                                    <i class="bi bi-box-arrow-in-right"></i> Vai al Login
                                </a>
                                
                                <div class="alert alert-warning mt-4 text-start">
                                    <strong>Importante:</strong> Per motivi di sicurezza, considera di eliminare o rinominare il file <code>install.php</code> dopo l'installazione.
                                </div>
                            </div>
                        <?php endif; ?>
                        
                    </div>
                    <div class="card-footer text-center text-muted py-3">
                        Powered with <strong>AssoLife</strong> by Luigi Pistarà
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
