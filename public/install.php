<?php
/**
 * AssoLife Advanced Installer
 */

// Check if already installed
if (file_exists(__DIR__ . '/../src/config.php')) {
    die('AssoLife è già installato. Elimina il file src/config.php per reinstallare.');
}

$errors = [];
$success = false;

// Auto-detect installation path
$detectedPath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/';
if ($detectedPath === '//') $detectedPath = '/';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Database configuration
    $dbHost = trim($_POST['db_host'] ?? '');
    $dbName = trim($_POST['db_name'] ?? '');
    $dbUser = trim($_POST['db_user'] ?? '');
    $dbPass = $_POST['db_pass'] ?? '';
    $dbPrefix = trim($_POST['db_prefix'] ?? '');
    
    // Site configuration
    $siteName = trim($_POST['site_name'] ?? '');
    $basePath = trim($_POST['base_path'] ?? '/');
    $forceHttps = isset($_POST['force_https']);
    
    // Admin account
    $adminUsername = trim($_POST['admin_username'] ?? '');
    $adminPassword = $_POST['admin_password'] ?? '';
    $adminFullName = trim($_POST['admin_full_name'] ?? '');
    $adminEmail = trim($_POST['admin_email'] ?? '');
    
    // Validation
    if (empty($dbHost)) $errors[] = "Host database richiesto";
    if (empty($dbName)) $errors[] = "Nome database richiesto";
    if (empty($dbUser)) $errors[] = "Utente database richiesto";
    if (empty($siteName)) $errors[] = "Nome del sito richiesto";
    if (empty($basePath)) $errors[] = "Path di installazione richiesto";
    if (empty($adminUsername)) $errors[] = "Username amministratore richiesto";
    if (empty($adminPassword)) $errors[] = "Password amministratore richiesta";
    if (strlen($adminPassword) < 8) $errors[] = "La password deve contenere almeno 8 caratteri";
    if (empty($adminFullName)) $errors[] = "Nome completo amministratore richiesto";
    if (empty($adminEmail)) $errors[] = "Email amministratore richiesta";
    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) $errors[] = "Email non valida";
    
    if (empty($errors)) {
        try {
            // Test database connection
            $dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            
            // Read schema file
            $schemaPath = __DIR__ . '/../schema.sql';
            if (!file_exists($schemaPath)) {
                throw new Exception("File schema.sql non trovato");
            }
            
            $schema = file_get_contents($schemaPath);
            
            // Replace table names with prefixed versions
            $tables = [
                'users', 'members', 'social_years', 
                'income_categories', 'expense_categories', 
                'income', 'expenses'
            ];
            
            foreach ($tables as $table) {
                $schema = str_replace(
                    "CREATE TABLE IF NOT EXISTS $table",
                    "CREATE TABLE IF NOT EXISTS {$dbPrefix}$table",
                    $schema
                );
                $schema = str_replace(
                    "INSERT INTO $table",
                    "INSERT INTO {$dbPrefix}$table",
                    $schema
                );
            }
            
            // Execute schema
            $pdo->exec($schema);
            
            // Create admin user
            $passwordHash = password_hash($adminPassword, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO {$dbPrefix}users (username, password, full_name, email, role) VALUES (?, ?, ?, ?, 'admin')");
            $stmt->execute([$adminUsername, $passwordHash, $adminFullName, $adminEmail]);
            
            // Create config.php
            $configContent = "<?php\n";
            $configContent .= "return [\n";
            $configContent .= "    'db' => [\n";
            $configContent .= "        'host'     => " . var_export($dbHost, true) . ",\n";
            $configContent .= "        'dbname'   => " . var_export($dbName, true) . ",\n";
            $configContent .= "        'username' => " . var_export($dbUser, true) . ",\n";
            $configContent .= "        'password' => " . var_export($dbPass, true) . ",\n";
            $configContent .= "        'charset'  => 'utf8mb4',\n";
            $configContent .= "        'prefix'   => " . var_export($dbPrefix, true) . ",\n";
            $configContent .= "    ],\n";
            $configContent .= "    'app' => [\n";
            $configContent .= "        'name'         => " . var_export($siteName, true) . ",\n";
            $configContent .= "        'version'      => '1.0.0',\n";
            $configContent .= "        'base_path'    => " . var_export($basePath, true) . ",\n";
            $configContent .= "        'force_https'  => " . ($forceHttps ? 'true' : 'false') . ",\n";
            $configContent .= "        'session_name' => 'assolife_session',\n";
            $configContent .= "        'timezone'     => 'Europe/Rome',\n";
            $configContent .= "    ],\n";
            $configContent .= "];\n";
            
            $configPath = __DIR__ . '/../src/config.php';
            if (file_put_contents($configPath, $configContent) === false) {
                throw new Exception("Impossibile creare il file config.php");
            }
            
            $success = true;
            
        } catch (PDOException $e) {
            $errors[] = "Errore database: " . $e->getMessage();
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0"><i class="bi bi-gear-fill me-2"></i>Installazione AssoLife</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <h4 class="alert-heading"><i class="bi bi-check-circle-fill me-2"></i>Installazione completata!</h4>
                                <p>AssoLife è stato installato con successo.</p>
                                <hr>
                                <p class="mb-0">
                                    <a href="login.php" class="btn btn-primary">
                                        <i class="bi bi-box-arrow-in-right me-2"></i>Vai al Login
                                    </a>
                                </p>
                            </div>
                        <?php else: ?>
                            <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger">
                                    <h5>Errori:</h5>
                                    <ul class="mb-0">
                                        <?php foreach ($errors as $error): ?>
                                            <li><?= htmlspecialchars($error) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            
                            <form method="POST">
                                <!-- Database Configuration -->
                                <h5 class="mb-3"><i class="bi bi-database me-2"></i>1. Configurazione Database</h5>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Host Database *</label>
                                        <input type="text" name="db_host" class="form-control" value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Nome Database *</label>
                                        <input type="text" name="db_name" class="form-control" value="<?= htmlspecialchars($_POST['db_name'] ?? '') ?>" required>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Utente Database *</label>
                                        <input type="text" name="db_user" class="form-control" value="<?= htmlspecialchars($_POST['db_user'] ?? '') ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Password Database</label>
                                        <input type="password" name="db_pass" class="form-control" value="<?= htmlspecialchars($_POST['db_pass'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label">Prefisso Tabelle</label>
                                    <input type="text" name="db_prefix" class="form-control" value="<?= htmlspecialchars($_POST['db_prefix'] ?? '') ?>" placeholder="es: assolife_">
                                    <div class="form-text">Opzionale. Tutte le tabelle useranno questo prefisso.</div>
                                </div>
                                
                                <hr>
                                
                                <!-- Site Configuration -->
                                <h5 class="mb-3"><i class="bi bi-globe me-2"></i>2. Configurazione Sito</h5>
                                <div class="mb-3">
                                    <label class="form-label">Nome del Sito *</label>
                                    <input type="text" name="site_name" class="form-control" value="<?= htmlspecialchars($_POST['site_name'] ?? 'Associazione') ?>" required>
                                    <div class="form-text">Sarà visualizzato nell'header e nel titolo delle pagine.</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Path di Installazione *</label>
                                    <input type="text" name="base_path" class="form-control" value="<?= htmlspecialchars($_POST['base_path'] ?? $detectedPath) ?>" required>
                                    <div class="form-text">Rilevato automaticamente: <code><?= htmlspecialchars($detectedPath) ?></code></div>
                                </div>
                                <div class="mb-4">
                                    <div class="form-check">
                                        <input type="checkbox" name="force_https" class="form-check-input" id="forceHttps" <?= isset($_POST['force_https']) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="forceHttps">
                                            Forza HTTPS (solo se disponibile)
                                        </label>
                                    </div>
                                    <div class="form-text">Abilita redirect automatico a HTTPS se il certificato SSL è disponibile.</div>
                                </div>
                                
                                <hr>
                                
                                <!-- Admin Account -->
                                <h5 class="mb-3"><i class="bi bi-person-badge me-2"></i>3. Account Amministratore</h5>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Username *</label>
                                        <input type="text" name="admin_username" class="form-control" value="<?= htmlspecialchars($_POST['admin_username'] ?? '') ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Password * (min 8 caratteri)</label>
                                        <input type="password" name="admin_password" class="form-control" minlength="8" required>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Nome Completo *</label>
                                        <input type="text" name="admin_full_name" class="form-control" value="<?= htmlspecialchars($_POST['admin_full_name'] ?? '') ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Email *</label>
                                        <input type="email" name="admin_email" class="form-control" value="<?= htmlspecialchars($_POST['admin_email'] ?? '') ?>" required>
                                    </div>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="bi bi-download me-2"></i>Installa AssoLife
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer text-center text-muted">
                        <small>Powered with <strong>AssoLife</strong> by Luigi Pistarà</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
