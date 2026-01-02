<?php
/**
 * Audit Log Viewer
 * Visualizzazione log delle operazioni (solo admin)
 */

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/audit.php';

requireAdmin();

$pageTitle = 'Audit Log';

// Paginazione
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Filtri
$filters = [];
if (isset($_GET['user_id']) && !empty($_GET['user_id'])) {
    $filters['user_id'] = intval($_GET['user_id']);
}
if (isset($_GET['action']) && !empty($_GET['action'])) {
    $filters['action'] = $_GET['action'];
}
if (isset($_GET['entity_type']) && !empty($_GET['entity_type'])) {
    $filters['entity_type'] = $_GET['entity_type'];
}
if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
    $filters['date_from'] = $_GET['date_from'];
}
if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
    $filters['date_to'] = $_GET['date_to'];
}

// Export CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $logs = getAuditLog($filters, 10000, 0); // Max 10000 record per export
    
    logExport('audit_log', 'Export audit log (' . count($logs) . ' record)');
    
    $headers = ['Data/Ora', 'Utente', 'Azione', 'Tipo Entità', 'Nome Entità', 'IP'];
    $data = [];
    
    foreach ($logs as $log) {
        $data[] = [
            $log['created_at'],
            $log['username'] ?? 'N/A',
            translateAction($log['action']),
            $log['entity_type'] ? translateEntityType($log['entity_type']) : 'N/A',
            $log['entity_name'] ?? 'N/A',
            $log['ip_address'] ?? 'N/A'
        ];
    }
    
    exportCsv('audit_log_' . date('Y-m-d') . '.csv', $data, $headers);
    exit;
}

// Recupera logs
$logs = getAuditLog($filters, $perPage, $offset);
$totalLogs = countAuditLog($filters);
$totalPages = ceil($totalLogs / $perPage);

// Recupera lista utenti per filtro
$stmt = $pdo->query("SELECT id, username, full_name FROM " . table('users') . " ORDER BY username");
$users = $stmt->fetchAll();

// Azioni disponibili
$actions = ['login', 'logout', 'create', 'update', 'delete', 'export', 'email'];

// Tipi entità disponibili
$entityTypes = ['member', 'fee', 'income', 'expense', 'user', 'year', 'category'];

include __DIR__ . '/inc/header.php';
?>

<h1><i class="bi bi-clock-history"></i> Audit Log</h1>

<div class="card mb-3">
    <div class="card-header">
        <h5 class="mb-0">Filtri</h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Utente</label>
                <select name="user_id" class="form-select">
                    <option value="">Tutti</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['id']; ?>" 
                                <?php echo (isset($filters['user_id']) && $filters['user_id'] == $user['id']) ? 'selected' : ''; ?>>
                            <?php echo h($user['username']); ?> (<?php echo h($user['full_name']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label class="form-label">Azione</label>
                <select name="action" class="form-select">
                    <option value="">Tutte</option>
                    <?php foreach ($actions as $act): ?>
                        <option value="<?php echo $act; ?>" 
                                <?php echo (isset($filters['action']) && $filters['action'] === $act) ? 'selected' : ''; ?>>
                            <?php echo translateAction($act); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label class="form-label">Tipo Entità</label>
                <select name="entity_type" class="form-select">
                    <option value="">Tutti</option>
                    <?php foreach ($entityTypes as $type): ?>
                        <option value="<?php echo $type; ?>" 
                                <?php echo (isset($filters['entity_type']) && $filters['entity_type'] === $type) ? 'selected' : ''; ?>>
                            <?php echo translateEntityType($type); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label class="form-label">Data Da</label>
                <input type="date" name="date_from" class="form-control" 
                       value="<?php echo h($filters['date_from'] ?? ''); ?>">
            </div>
            
            <div class="col-md-2">
                <label class="form-label">Data A</label>
                <input type="date" name="date_to" class="form-control" 
                       value="<?php echo h($filters['date_to'] ?? ''); ?>">
            </div>
            
            <div class="col-md-1">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-funnel"></i> Filtra
                </button>
            </div>
        </form>
        
        <div class="mt-3">
            <a href="?" class="btn btn-secondary btn-sm">
                <i class="bi bi-x-circle"></i> Rimuovi Filtri
            </a>
            <a href="?<?php echo http_build_query(array_merge($filters, ['export' => 'csv'])); ?>" 
               class="btn btn-success btn-sm">
                <i class="bi bi-download"></i> Esporta CSV
            </a>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Log delle Operazioni</h5>
        <span class="badge bg-primary"><?php echo number_format($totalLogs); ?> record</span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm table-hover">
                <thead>
                    <tr>
                        <th>Data/Ora</th>
                        <th>Utente</th>
                        <th>Azione</th>
                        <th>Entità</th>
                        <th>Dettagli</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted">Nessun log trovato</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td>
                                    <small><?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo h($log['username'] ?? 'N/A'); ?></strong>
                                </td>
                                <td>
                                    <?php 
                                    $actionClass = match($log['action']) {
                                        'login' => 'success',
                                        'logout' => 'secondary',
                                        'create' => 'primary',
                                        'update' => 'warning',
                                        'delete' => 'danger',
                                        'export' => 'info',
                                        default => 'secondary'
                                    };
                                    ?>
                                    <span class="badge bg-<?php echo $actionClass; ?>">
                                        <?php echo translateAction($log['action']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($log['entity_type']): ?>
                                        <span class="badge bg-light text-dark">
                                            <?php echo translateEntityType($log['entity_type']); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($log['entity_name']): ?>
                                        <small><?php echo h($log['entity_name']); ?></small>
                                    <?php endif; ?>
                                    
                                    <?php if ($log['old_values'] || $log['new_values']): ?>
                                        <button class="btn btn-sm btn-link p-0" 
                                                type="button" 
                                                data-bs-toggle="collapse" 
                                                data-bs-target="#detail-<?php echo $log['id']; ?>">
                                            <i class="bi bi-info-circle"></i> Dettagli
                                        </button>
                                        <div class="collapse mt-2" id="detail-<?php echo $log['id']; ?>">
                                            <div class="card card-body p-2">
                                                <?php 
                                                $oldVals = $log['old_values'] ? json_decode($log['old_values'], true) : null;
                                                $newVals = $log['new_values'] ? json_decode($log['new_values'], true) : null;
                                                $diff = formatValueDiff($oldVals ?: [], $newVals ?: []);
                                                ?>
                                                <?php if ($diff): ?>
                                                    <small>
                                                        <?php foreach ($diff as $key => $change): ?>
                                                            <div>
                                                                <strong><?php echo h($key); ?>:</strong>
                                                                <?php if ($change['old'] !== null): ?>
                                                                    <span class="text-danger"><?php echo h($change['old']); ?></span>
                                                                    →
                                                                <?php endif; ?>
                                                                <span class="text-success"><?php echo h($change['new']); ?></span>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </small>
                                                <?php elseif ($newVals): ?>
                                                    <small><pre class="mb-0"><?php echo h(json_encode($newVals, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre></small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small class="text-muted"><?php echo h($log['ip_address'] ?? 'N/A'); ?></small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($totalPages > 1): ?>
            <nav aria-label="Paginazione">
                <ul class="pagination pagination-sm justify-content-center">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($filters, ['page' => $i])); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/inc/footer.php'; ?>
