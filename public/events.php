<?php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/db.php';

requireLogin();

$pageTitle = 'Eventi';
$basePath = $config['app']['base_path'];

// Get filters
$statusFilter = $_GET['status'] ?? '';
$modeFilter = $_GET['mode'] ?? '';
$monthFilter = $_GET['month'] ?? '';

// Build filters array
$filters = [];
if ($statusFilter) {
    $filters['status'] = $statusFilter;
}
if ($modeFilter) {
    $filters['event_mode'] = $modeFilter;
}
if ($monthFilter) {
    $filters['from_date'] = date('Y-m-01', strtotime($monthFilter . '-01'));
    $filters['to_date'] = date('Y-m-t', strtotime($monthFilter . '-01'));
}

// Get events
$events = getEvents($filters, 100, 0);

// Get registration counts for each event
$eventRegistrations = [];
foreach ($events as $event) {
    $registrations = getEventRegistrations($event['id']);
    $eventRegistrations[$event['id']] = [
        'total' => count($registrations),
        'confirmed' => count(array_filter($registrations, function($r) { 
            return $r['attendance_status'] == 'registered' || $r['attendance_status'] == 'confirmed'; 
        }))
    ];
}

include __DIR__ . '/inc/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="bi bi-calendar-event"></i> Eventi Associazione</h1>
    <?php if (isAdmin()): ?>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="<?php echo h($basePath); ?>event_edit.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Nuovo Evento
        </a>
    </div>
    <?php endif; ?>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Stato</label>
                <select name="status" class="form-select">
                    <option value="">Tutti gli stati</option>
                    <option value="draft" <?php echo $statusFilter === 'draft' ? 'selected' : ''; ?>>Bozza</option>
                    <option value="published" <?php echo $statusFilter === 'published' ? 'selected' : ''; ?>>Pubblicato</option>
                    <option value="cancelled" <?php echo $statusFilter === 'cancelled' ? 'selected' : ''; ?>>Annullato</option>
                    <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Completato</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Modalità</label>
                <select name="mode" class="form-select">
                    <option value="">Tutte le modalità</option>
                    <option value="in_person" <?php echo $modeFilter === 'in_person' ? 'selected' : ''; ?>>🏢 Di Persona</option>
                    <option value="online" <?php echo $modeFilter === 'online' ? 'selected' : ''; ?>>💻 Online</option>
                    <option value="hybrid" <?php echo $modeFilter === 'hybrid' ? 'selected' : ''; ?>>🔄 Ibrido</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Mese</label>
                <input type="month" name="month" class="form-control" value="<?php echo h($monthFilter); ?>">
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="bi bi-search"></i> Filtra
                </button>
                <a href="<?php echo h($basePath); ?>events.php" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> Reset
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Events List -->
<?php if (empty($events)): ?>
<div class="alert alert-info">
    <i class="bi bi-info-circle"></i> Nessun evento trovato.
</div>
<?php else: ?>
<div class="row">
    <?php foreach ($events as $event): 
        $regCount = $eventRegistrations[$event['id']]['confirmed'];
        $maxPart = $event['max_participants'];
        $spotsText = $maxPart > 0 ? "$regCount/$maxPart iscritti" : "$regCount iscritti";
        
        // Mode icon and label
        $modeIcon = '🏢';
        $modeLabel = 'Di Persona';
        if ($event['event_mode'] == 'online') {
            $modeIcon = '💻';
            $modeLabel = 'Online';
        } elseif ($event['event_mode'] == 'hybrid') {
            $modeIcon = '🔄';
            $modeLabel = 'Ibrido';
        }
        
        // Status badge
        $statusClass = 'secondary';
        $statusText = 'Bozza';
        switch ($event['status']) {
            case 'published':
                $statusClass = 'success';
                $statusText = 'Pubblicato';
                break;
            case 'cancelled':
                $statusClass = 'danger';
                $statusText = 'Annullato';
                break;
            case 'completed':
                $statusClass = 'info';
                $statusText = 'Completato';
                break;
        }
    ?>
    <div class="col-md-6 mb-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h5 class="card-title mb-0">
                        <span style="font-size: 1.5em;"><?php echo $modeIcon; ?></span>
                        <?php echo h($event['title']); ?>
                    </h5>
                    <span class="badge bg-<?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                </div>
                
                <p class="text-muted small mb-2">
                    <i class="bi bi-tag"></i> <?php echo $modeLabel; ?>
                </p>
                
                <p class="mb-2">
                    <i class="bi bi-calendar3"></i> <strong><?php echo formatDate($event['event_date']); ?></strong>
                    <?php if ($event['event_time']): ?>
                        ore <strong><?php echo substr($event['event_time'], 0, 5); ?></strong>
                    <?php endif; ?>
                </p>
                
                <?php if ($event['event_mode'] == 'in_person' && $event['location']): ?>
                <p class="mb-2">
                    <i class="bi bi-geo-alt"></i> <?php echo h($event['location']); ?>
                    <?php if ($event['city']): ?>
                        - <?php echo h($event['city']); ?>
                    <?php endif; ?>
                </p>
                <?php elseif ($event['event_mode'] == 'online' && $event['online_platform']): ?>
                <p class="mb-2">
                    <i class="bi bi-camera-video"></i> <?php echo h($event['online_platform']); ?> - Link disponibile agli iscritti
                </p>
                <?php elseif ($event['event_mode'] == 'hybrid'): ?>
                <p class="mb-2">
                    <i class="bi bi-geo-alt"></i> <?php echo h($event['location'] ?? 'Sede'); ?> 
                    + <i class="bi bi-camera-video"></i> Streaming Online
                </p>
                <?php endif; ?>
                
                <p class="mb-2">
                    <i class="bi bi-people"></i> <?php echo $spotsText; ?>
                    <?php if ($event['cost'] > 0): ?>
                        | <i class="bi bi-currency-euro"></i> <?php echo formatCurrency($event['cost']); ?>
                    <?php else: ?>
                        | <i class="bi bi-gift"></i> Gratuito
                    <?php endif; ?>
                </p>
                
                <?php if ($event['description']): ?>
                <p class="card-text text-muted small">
                    <?php echo h(mb_substr($event['description'], 0, 100)); ?>
                    <?php if (mb_strlen($event['description']) > 100): ?>...<?php endif; ?>
                </p>
                <?php endif; ?>
                
                <div class="btn-group btn-group-sm mt-2" role="group">
                    <a href="<?php echo h($basePath); ?>event_view.php?id=<?php echo $event['id']; ?>" class="btn btn-outline-primary">
                        <i class="bi bi-eye"></i> Dettagli
                    </a>
                    <?php if (isAdmin()): ?>
                    <a href="<?php echo h($basePath); ?>event_registrations.php?id=<?php echo $event['id']; ?>" class="btn btn-outline-info">
                        <i class="bi bi-list-check"></i> Iscrizioni
                    </a>
                    <a href="<?php echo h($basePath); ?>event_edit.php?id=<?php echo $event['id']; ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-pencil"></i> Modifica
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/inc/footer.php'; ?>
