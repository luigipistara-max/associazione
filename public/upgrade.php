<?php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/db.php';

requireLogin();
requireAdmin();

$config = require __DIR__ . '/../src/config.php';
$basePath = $config['app']['base_path'];
$pageTitle = 'Aggiornamento Database';

// Versione corrente del database
function getCurrentDbVersion() {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT value FROM " . table('settings') . " WHERE key_name = 'db_version'");
        $result = $stmt->fetch();
        return $result ? (int)$result['value'] : 0;
    } catch (Exception $e) {
        return 0;
    }
}

// Imposta versione database
function setDbVersion($version) {
    global $pdo;
    $stmt = $pdo->prepare("
        INSERT INTO " . table('settings') . " (key_name, value) 
        VALUES ('db_version', ?) 
        ON DUPLICATE KEY UPDATE value = ?
    ");
    return $stmt->execute([$version, $version]);
}

/**
 * Check if a column exists in a table
 */
function columnExists($table, $column) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM $table LIKE ?");
        $stmt->execute([$column]);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Check if a table exists
 */
function tableExists($table) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Safely add column if it doesn't exist
 */
function addColumnIfNotExists($table, $column, $definition) {
    global $pdo;
    if (!columnExists($table, $column)) {
        $sql = "ALTER TABLE $table ADD COLUMN $column $definition";
        $pdo->exec($sql);
        return true;
    }
    return false;
}

// Definizione aggiornamenti
$upgrades = [
    1 => [
        'description' => 'Aggiunta colonna meeting_link alla tabella events',
        'execute' => function() {
            addColumnIfNotExists(table('events'), 'meeting_link', 'VARCHAR(500) NULL');
        }
    ],
    2 => [
        'description' => 'Aggiunta tabella email_log',
        'execute' => function() {
            global $pdo;
            if (!tableExists(table('email_log'))) {
                $pdo->exec("
                    CREATE TABLE " . table('email_log') . " (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        recipient VARCHAR(255) NOT NULL,
                        subject VARCHAR(255) NOT NULL,
                        method VARCHAR(50) NOT NULL,
                        status VARCHAR(50) NOT NULL,
                        sent_at DATETIME NOT NULL,
                        INDEX idx_recipient (recipient),
                        INDEX idx_sent_at (sent_at)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");
            }
        }
    ],
    3 => [
        'description' => 'Aggiunta colonne online per eventi',
        'execute' => function() {
            addColumnIfNotExists(table('events'), 'online_platform', 'VARCHAR(100) NULL');
            addColumnIfNotExists(table('events'), 'online_link', 'VARCHAR(500) NULL');
            addColumnIfNotExists(table('events'), 'online_password', 'VARCHAR(100) NULL');
            addColumnIfNotExists(table('events'), 'online_instructions', 'TEXT NULL');
        }
    ],
    4 => [
        'description' => 'Aggiunta colonna registration_status a event_responses',
        'execute' => function() {
            addColumnIfNotExists(table('event_responses'), 'registration_status', "ENUM('pending', 'approved', 'rejected', 'revoked') DEFAULT 'pending'");
            addColumnIfNotExists(table('event_responses'), 'approved_by', 'INT NULL');
            addColumnIfNotExists(table('event_responses'), 'approved_at', 'DATETIME NULL');
            addColumnIfNotExists(table('event_responses'), 'rejection_reason', 'VARCHAR(500) NULL');
        }
    ],
    // Aggiungi altri aggiornamenti qui...
];

$currentVersion = getCurrentDbVersion();
$latestVersion = max(array_keys($upgrades));
$pendingUpgrades = array_filter($upgrades, function($key) use ($currentVersion) {
    return $key > $currentVersion;
}, ARRAY_FILTER_USE_KEY);

$messages = [];
$errors = [];

// Esegui aggiornamento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_upgrade'])) {
    $token = $_POST['csrf_token'] ?? '';
    
    if (verifyCsrfToken($token)) {
        foreach ($pendingUpgrades as $version => $upgrade) {
            try {
                $pdo->beginTransaction();
                
                // Esegui la funzione di upgrade
                if (isset($upgrade['execute']) && is_callable($upgrade['execute'])) {
                    $upgrade['execute']();
                } elseif (isset($upgrade['sql'])) {
                    // Fallback per SQL diretto (legacy)
                    foreach ($upgrade['sql'] as $sql) {
                        $pdo->exec($sql);
                    }
                }
                
                setDbVersion($version);
                $pdo->commit();
                
                $messages[] = "✅ Aggiornamento v$version completato: " . $upgrade['description'];
                logAudit('db_upgrade', "Database aggiornato alla versione $version");
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = "❌ Errore aggiornamento v$version: " . $e->getMessage();
                break;
            }
        }
        
        // Aggiorna versione corrente dopo gli upgrade
        $currentVersion = getCurrentDbVersion();
        $pendingUpgrades = array_filter($upgrades, function($key) use ($currentVersion) {
            return $key > $currentVersion;
        }, ARRAY_FILTER_USE_KEY);
    }
}

include __DIR__ . '/inc/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="bi bi-arrow-up-circle"></i> Aggiornamento Database</h4>
                </div>
                <div class="card-body">
                    
                    <?php foreach ($messages as $msg): ?>
                    <div class="alert alert-success"><?php echo h($msg); ?></div>
                    <?php endforeach; ?>
                    
                    <?php foreach ($errors as $err): ?>
                    <div class="alert alert-danger"><?php echo h($err); ?></div>
                    <?php endforeach; ?>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h5>Versione Corrente</h5>
                                    <h2 class="text-primary"><?php echo $currentVersion; ?></h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h5>Ultima Versione</h5>
                                    <h2 class="text-success"><?php echo $latestVersion; ?></h2>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (empty($pendingUpgrades)): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle"></i> Il database è aggiornato all'ultima versione!
                    </div>
                    <?php else: ?>
                    
                    <h5>Aggiornamenti Disponibili</h5>
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Versione</th>
                                <th>Descrizione</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingUpgrades as $version => $upgrade): ?>
                            <tr>
                                <td><span class="badge bg-warning">v<?php echo $version; ?></span></td>
                                <td><?php echo h($upgrade['description']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i> 
                        <strong>Attenzione:</strong> Prima di procedere, assicurati di aver effettuato un backup del database!
                    </div>
                    
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <button type="submit" name="run_upgrade" class="btn btn-primary btn-lg w-100"
                                onclick="return confirm('Sei sicuro di voler eseguire gli aggiornamenti?')">
                            <i class="bi bi-arrow-up-circle"></i> Esegui Aggiornamenti
                        </button>
                    </form>
                    <?php endif; ?>
                    
                </div>
            </div>
            
            <!-- Storico Aggiornamenti -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-clock-history"></i> Storico Aggiornamenti</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Versione</th>
                                <th>Descrizione</th>
                                <th>Stato</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($upgrades as $version => $upgrade): ?>
                            <tr>
                                <td>v<?php echo $version; ?></td>
                                <td><?php echo h($upgrade['description']); ?></td>
                                <td>
                                    <?php if ($version <= $currentVersion): ?>
                                    <span class="badge bg-success">✅ Applicato</span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary">⏳ In attesa</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
        </div>
    </div>
</div>

<?php include __DIR__ . '/inc/footer.php'; ?>
