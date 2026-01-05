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

// Definizione aggiornamenti
$upgrades = [
    1 => [
        'description' => 'Aggiunta colonna meeting_link alla tabella events',
        'sql' => [
            "ALTER TABLE " . table('events') . " ADD COLUMN IF NOT EXISTS meeting_link VARCHAR(500) NULL",
        ]
    ],
    2 => [
        'description' => 'Aggiunta tabella email_log',
        'sql' => [
            "CREATE TABLE IF NOT EXISTS " . table('email_log') . " (
                id INT AUTO_INCREMENT PRIMARY KEY,
                recipient VARCHAR(255) NOT NULL,
                subject VARCHAR(255) NOT NULL,
                method VARCHAR(50) NOT NULL,
                status VARCHAR(50) NOT NULL,
                sent_at DATETIME NOT NULL,
                INDEX idx_recipient (recipient),
                INDEX idx_sent_at (sent_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        ]
    ],
    3 => [
        'description' => 'Aggiunta colonne online per eventi',
        'sql' => [
            "ALTER TABLE " . table('events') . " ADD COLUMN IF NOT EXISTS online_platform VARCHAR(100) NULL",
            "ALTER TABLE " . table('events') . " ADD COLUMN IF NOT EXISTS online_link VARCHAR(500) NULL",
            "ALTER TABLE " . table('events') . " ADD COLUMN IF NOT EXISTS online_password VARCHAR(100) NULL",
            "ALTER TABLE " . table('events') . " ADD COLUMN IF NOT EXISTS online_instructions TEXT NULL",
        ]
    ],
    4 => [
        'description' => 'Aggiunta colonna registration_status a event_responses',
        'sql' => [
            "ALTER TABLE " . table('event_responses') . " ADD COLUMN IF NOT EXISTS registration_status ENUM('pending', 'approved', 'rejected', 'revoked') DEFAULT 'pending'",
            "ALTER TABLE " . table('event_responses') . " ADD COLUMN IF NOT EXISTS approved_by INT NULL",
            "ALTER TABLE " . table('event_responses') . " ADD COLUMN IF NOT EXISTS approved_at DATETIME NULL",
            "ALTER TABLE " . table('event_responses') . " ADD COLUMN IF NOT EXISTS rejection_reason VARCHAR(500) NULL",
        ]
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
                
                foreach ($upgrade['sql'] as $sql) {
                    $pdo->exec($sql);
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
        
        // Aggiorna versione corrente
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
