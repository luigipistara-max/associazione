<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/email.php';

requireAdmin();

$config = require __DIR__ . '/../src/config.php';
$basePath = $config['app']['base_path'];
$pageTitle = 'Coda Email';

// Azioni
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $action = $_POST['action'] ?? '';
    
    if ($action === 'process_now') {
        $limit = (int) ($_POST['limit'] ?? 20);
        $stats = processEmailQueue($limit);
        setFlashMessage("Processate {$stats['sent']} email su {$stats['processed']}", 
                       $stats['failed'] > 0 ? 'warning' : 'success');
    } elseif ($action === 'delete_failed') {
        $stmt = $pdo->prepare("DELETE FROM " . table('email_queue') . " WHERE status = 'failed'");
        $stmt->execute();
        setFlashMessage('Email fallite eliminate');
    } elseif ($action === 'retry_failed') {
        $stmt = $pdo->prepare("UPDATE " . table('email_queue') . " SET status = 'pending', attempts = 0 WHERE status = 'failed'");
        $stmt->execute();
        setFlashMessage('Email fallite rimesse in coda');
    }
    
    redirect($basePath . 'admin_email_queue.php');
}

// Statistiche coda
$stats = [
    'pending' => 0,
    'processing' => 0,
    'sent' => 0,
    'failed' => 0
];

$stmt = $pdo->query("SELECT status, COUNT(*) as count FROM " . table('email_queue') . " GROUP BY status");
while ($row = $stmt->fetch()) {
    $stats[$row['status']] = $row['count'];
}

// Email recenti in coda
$stmt = $pdo->query("
    SELECT * FROM " . table('email_queue') . "
    ORDER BY 
        CASE status 
            WHEN 'pending' THEN 1 
            WHEN 'processing' THEN 2 
            WHEN 'failed' THEN 3 
            WHEN 'sent' THEN 4 
        END,
        created_at DESC
    LIMIT 50
");
$emails = $stmt->fetchAll();

// Modalità attuale
$sendMode = getSetting('email_send_mode', 'direct');
$cronToken = getSetting('cron_token', '');

include __DIR__ . '/inc/header.php';
?>

<h2><i class="bi bi-envelope-paper"></i> Coda Email</h2>

<!-- Modalità Attuale -->
<div class="alert alert-<?php echo $sendMode === 'direct' ? 'success' : 'info'; ?>">
    <strong>Modalità attuale:</strong> 
    <?php if ($sendMode === 'direct'): ?>
        <i class="bi bi-lightning"></i> Invio Diretto (le email vengono inviate subito)
    <?php else: ?>
        <i class="bi bi-clock"></i> Coda + Cron (le email vengono accodate e inviate dal cron)
    <?php endif; ?>
    <a href="settings.php?tab=email" class="btn btn-sm btn-outline-primary float-end">
        <i class="bi bi-gear"></i> Modifica
    </a>
</div>

<!-- Statistiche -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-center bg-warning text-dark">
            <div class="card-body">
                <h3><?php echo $stats['pending']; ?></h3>
                <p class="mb-0">In Attesa</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center bg-info text-white">
            <div class="card-body">
                <h3><?php echo $stats['processing']; ?></h3>
                <p class="mb-0">In Elaborazione</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center bg-success text-white">
            <div class="card-body">
                <h3><?php echo $stats['sent']; ?></h3>
                <p class="mb-0">Inviate</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center bg-danger text-white">
            <div class="card-body">
                <h3><?php echo $stats['failed']; ?></h3>
                <p class="mb-0">Fallite</p>
            </div>
        </div>
    </div>
</div>

<!-- Azioni -->
<div class="card mb-4">
    <div class="card-header">
        <i class="bi bi-gear"></i> Azioni
    </div>
    <div class="card-body">
        <form method="POST" class="d-inline">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="process_now">
            <input type="number" name="limit" value="20" min="1" max="100" class="form-control d-inline" style="width: 80px;">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-play"></i> Processa Ora
            </button>
        </form>
        
        <?php if ($stats['failed'] > 0): ?>
        <form method="POST" class="d-inline ms-2">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="retry_failed">
            <button type="submit" class="btn btn-warning">
                <i class="bi bi-arrow-repeat"></i> Riprova Fallite
            </button>
        </form>
        <form method="POST" class="d-inline ms-2">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="delete_failed">
            <button type="submit" class="btn btn-danger" onclick="return confirm('Eliminare tutte le email fallite?')">
                <i class="bi bi-trash"></i> Elimina Fallite
            </button>
        </form>
        <?php endif; ?>
    </div>
</div>

<!-- Configurazione Cron Esterno -->
<?php if ($sendMode === 'queue'): ?>
<div class="card mb-4">
    <div class="card-header">
        <i class="bi bi-clock-history"></i> Configurazione Cron Esterno
    </div>
    <div class="card-body">
        <?php if (empty($cronToken)): ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i> Token cron non configurato!
                <a href="settings.php?tab=api" class="btn btn-sm btn-warning">Configura Token</a>
            </div>
        <?php else: ?>
            <p>Configura il tuo servizio cron esterno (es. <a href="https://cron-job.org" target="_blank">cron-job.org</a>) con:</p>
            <div class="input-group mb-3">
                <span class="input-group-text">URL</span>
                <input type="text" class="form-control" readonly 
                       value="<?php echo getBaseUrl(); ?>cron/process_emails.php?token=<?php echo h($cronToken); ?>"
                       id="cronUrl">
                <button class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById('cronUrl').value); alert('Copiato!')">
                    <i class="bi bi-clipboard"></i> Copia
                </button>
            </div>
            <p class="text-muted">Frequenza consigliata: ogni 5 minuti</p>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Lista Email -->
<div class="card">
    <div class="card-header">
        <i class="bi bi-list"></i> Email Recenti (ultime 50)
    </div>
    <div class="card-body">
        <?php if (empty($emails)): ?>
            <p class="text-muted">Nessuna email in coda</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm table-striped">
                    <thead>
                        <tr>
                            <th>Stato</th>
                            <th>Destinatario</th>
                            <th>Oggetto</th>
                            <th>Creata</th>
                            <th>Tentativi</th>
                            <th>Errore</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($emails as $email): ?>
                        <tr>
                            <td>
                                <?php 
                                $badges = [
                                    'pending' => 'warning',
                                    'processing' => 'info',
                                    'sent' => 'success',
                                    'failed' => 'danger'
                                ];
                                $labels = [
                                    'pending' => 'In attesa',
                                    'processing' => 'In corso',
                                    'sent' => 'Inviata',
                                    'failed' => 'Fallita'
                                ];
                                ?>
                                <span class="badge bg-<?php echo $badges[$email['status']] ?? 'secondary'; ?>">
                                    <?php echo $labels[$email['status']] ?? $email['status']; ?>
                                </span>
                            </td>
                            <td><?php echo h($email['to_email']); ?></td>
                            <td><?php echo h(substr($email['subject'], 0, 40)); ?>...</td>
                            <td><?php echo date('d/m H:i', strtotime($email['created_at'])); ?></td>
                            <td><?php echo $email['attempts']; ?>/<?php echo $email['max_attempts']; ?></td>
                            <td>
                                <?php if ($email['error_message']): ?>
                                    <span class="text-danger" title="<?php echo h($email['error_message']); ?>">
                                        <?php echo h(substr($email['error_message'], 0, 30)); ?>...
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/inc/footer.php'; ?>
