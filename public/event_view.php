<?php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/db.php';

requireLogin();

$basePath = $config['app']['base_path'];
$eventId = $_GET['id'] ?? null;

if (!$eventId) {
    setFlashMessage('ID evento mancante', 'danger');
    redirect($basePath . 'events.php');
}

$event = getEvent($eventId);
if (!$event) {
    setFlashMessage('Evento non trovato', 'danger');
    redirect($basePath . 'events.php');
}

$pageTitle = $event['title'];
$currentUser = getCurrentUser();
$currentMemberId = null;

// Try to find member record for current user (if they have one)
$stmt = $pdo->prepare("SELECT id FROM " . table('members') . " WHERE email = ? LIMIT 1");
$stmt->execute([$currentUser['email']]);
$memberRecord = $stmt->fetch();
if ($memberRecord) {
    $currentMemberId = $memberRecord['id'];
}

// Get registrations
$registrations = getEventRegistrations($eventId);
$isRegistered = $currentMemberId ? isRegisteredForEvent($eventId, $currentMemberId) : false;
$availableSpots = getAvailableSpots($eventId);

// Mode details
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

$canRegister = $event['status'] == 'published' && 
               !$isRegistered && 
               $currentMemberId &&
               ($availableSpots === null || $availableSpots > 0);

include __DIR__ . '/inc/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <span style="font-size: 1.2em;"><?php echo $modeIcon; ?></span>
        <?php echo h($event['title']); ?>
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <?php if (isAdmin()): ?>
        <a href="<?php echo h($basePath); ?>event_edit.php?id=<?php echo $event['id']; ?>" class="btn btn-secondary me-2">
            <i class="bi bi-pencil"></i> Modifica
        </a>
        <?php endif; ?>
        <a href="<?php echo h($basePath); ?>events.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Torna agli Eventi
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <!-- Event Details Card -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-info-circle"></i> Dettagli Evento</h5>
                <span class="badge bg-<?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
            </div>
            <div class="card-body">
                <?php if ($event['description']): ?>
                <div class="mb-4">
                    <p class="lead"><?php echo nl2br(h($event['description'])); ?></p>
                </div>
                <hr>
                <?php endif; ?>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6><i class="bi bi-calendar3"></i> Data e Ora</h6>
                        <p class="mb-1">
                            <strong>Inizio:</strong> <?php echo formatDate($event['event_date']); ?>
                            <?php if ($event['event_time']): ?>
                                alle <?php echo substr($event['event_time'], 0, 5); ?>
                            <?php endif; ?>
                        </p>
                        <?php if ($event['end_date']): ?>
                        <p class="mb-1">
                            <strong>Fine:</strong> <?php echo formatDate($event['end_date']); ?>
                            <?php if ($event['end_time']): ?>
                                alle <?php echo substr($event['end_time'], 0, 5); ?>
                            <?php endif; ?>
                        </p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="bi bi-tag"></i> Modalità</h6>
                        <p><?php echo $modeLabel; ?></p>
                    </div>
                </div>
                
                <hr>
                
                <!-- Location Details (In Person) -->
                <?php if (($event['event_mode'] == 'in_person' || $event['event_mode'] == 'hybrid') && $event['location']): ?>
                <div class="mb-3">
                    <h6><i class="bi bi-geo-alt"></i> Luogo</h6>
                    <p class="mb-1"><strong><?php echo h($event['location']); ?></strong></p>
                    <?php if ($event['address']): ?>
                    <p class="mb-1"><?php echo h($event['address']); ?></p>
                    <?php endif; ?>
                    <?php if ($event['city']): ?>
                    <p class="mb-1"><?php echo h($event['city']); ?></p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <!-- Online Details -->
                <?php if ($event['event_mode'] == 'online' || $event['event_mode'] == 'hybrid'): ?>
                <div class="mb-3">
                    <h6><i class="bi bi-camera-video"></i> Collegamento Online</h6>
                    <p class="mb-1"><strong>Piattaforma:</strong> <?php echo h($event['online_platform'] ?? 'Non specificata'); ?></p>
                    
                    <?php if ($isRegistered && $event['online_link']): ?>
                    <div class="alert alert-info">
                        <p class="mb-1"><strong>Link di accesso:</strong></p>
                        <p class="mb-1"><a href="<?php echo h($event['online_link']); ?>" target="_blank" class="btn btn-sm btn-primary">
                            <i class="bi bi-box-arrow-up-right"></i> Partecipa Online
                        </a></p>
                        <?php if ($event['online_password']): ?>
                        <p class="mb-1"><strong>Password:</strong> <code><?php echo h($event['online_password']); ?></code></p>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($event['online_instructions']): ?>
                    <div class="alert alert-secondary">
                        <strong>Istruzioni:</strong><br>
                        <?php echo nl2br(h($event['online_instructions'])); ?>
                    </div>
                    <?php endif; ?>
                    <?php elseif (!$isRegistered): ?>
                    <p class="text-muted"><em>Il link sarà disponibile dopo l'iscrizione</em></p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <hr>
                
                <div class="row">
                    <div class="col-md-6">
                        <h6><i class="bi bi-people"></i> Partecipazione</h6>
                        <p class="mb-1">
                            <strong>Iscritti:</strong> <?php echo count($registrations); ?>
                            <?php if ($event['max_participants'] > 0): ?>
                                / <?php echo $event['max_participants']; ?>
                            <?php endif; ?>
                        </p>
                        <?php if ($availableSpots !== null): ?>
                        <p class="mb-1">
                            <strong>Posti disponibili:</strong> 
                            <span class="badge bg-<?php echo $availableSpots > 0 ? 'success' : 'danger'; ?>">
                                <?php echo $availableSpots; ?>
                            </span>
                        </p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="bi bi-currency-euro"></i> Costo</h6>
                        <p class="mb-1">
                            <?php if ($event['cost'] > 0): ?>
                                <strong><?php echo formatCurrency($event['cost']); ?></strong>
                            <?php else: ?>
                                <span class="text-success"><strong>Gratuito</strong></span>
                            <?php endif; ?>
                        </p>
                        <?php if ($event['registration_deadline']): ?>
                        <p class="mb-1">
                            <strong>Scadenza iscrizioni:</strong> <?php echo formatDate($event['registration_deadline']); ?>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($event['target_type'] == 'groups'): 
                    $targetGroups = getEventTargetGroups($event['id']);
                    if (!empty($targetGroups)):
                ?>
                <hr>
                <div class="mb-3">
                    <h6><i class="bi bi-diagram-3"></i> Gruppi Destinatari</h6>
                    <p class="mb-1">Questo evento è destinato ai seguenti gruppi:</p>
                    <div class="mt-2">
                        <?php foreach ($targetGroups as $group): ?>
                            <span class="badge me-1" style="background-color: <?php echo h($group['color']); ?>;">
                                <?php echo h($group['name']); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <!-- Registration Card -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-person-plus"></i> Iscrizione</h5>
            </div>
            <div class="card-body">
                <?php if (!$currentMemberId): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i> 
                    Solo i soci possono iscriversi agli eventi.
                </div>
                <?php elseif ($event['status'] == 'cancelled'): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-x-circle"></i> 
                    Questo evento è stato annullato.
                </div>
                <?php elseif ($event['status'] == 'completed'): ?>
                <div class="alert alert-info">
                    <i class="bi bi-check-circle"></i> 
                    Questo evento è terminato.
                </div>
                <?php elseif ($isRegistered): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle"></i> 
                    Sei iscritto a questo evento!
                </div>
                <a href="<?php echo h($basePath); ?>event_register.php?id=<?php echo $event['id']; ?>&action=unregister" 
                   class="btn btn-danger w-100" 
                   onclick="return confirm('Sei sicuro di voler annullare l\'iscrizione?')">
                    <i class="bi bi-x-circle"></i> Annulla Iscrizione
                </a>
                <?php elseif ($canRegister): ?>
                <a href="<?php echo h($basePath); ?>event_register.php?id=<?php echo $event['id']; ?>" class="btn btn-primary w-100">
                    <i class="bi bi-person-plus"></i> Iscriviti
                </a>
                <?php elseif ($availableSpots !== null && $availableSpots <= 0): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i> 
                    Posti esauriti. Puoi iscriverti alla lista d'attesa.
                </div>
                <a href="<?php echo h($basePath); ?>event_register.php?id=<?php echo $event['id']; ?>" class="btn btn-warning w-100">
                    <i class="bi bi-list"></i> Lista d'Attesa
                </a>
                <?php else: ?>
                <div class="alert alert-secondary">
                    <i class="bi bi-info-circle"></i> 
                    Le iscrizioni non sono al momento disponibili.
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Admin Actions -->
        <?php if (isAdmin()): ?>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-gear"></i> Azioni Admin</h5>
            </div>
            <div class="card-body">
                <a href="<?php echo h($basePath); ?>event_registrations.php?id=<?php echo $event['id']; ?>" class="btn btn-info w-100 mb-2">
                    <i class="bi bi-list-check"></i> Gestisci Iscrizioni (<?php echo count($registrations); ?>)
                </a>
                
                <?php if (in_array($event['event_mode'], ['online', 'hybrid']) && count($registrations) > 0): ?>
                <a href="<?php echo h($basePath); ?>event_registrations.php?id=<?php echo $event['id']; ?>&action=send_links" 
                   class="btn btn-primary w-100 mb-2"
                   onclick="return confirm('Inviare il link online a tutti gli iscritti?')">
                    <i class="bi bi-send"></i> Invia Link Online
                </a>
                <?php endif; ?>
                
                <a href="<?php echo h($basePath); ?>event_edit.php?id=<?php echo $event['id']; ?>" class="btn btn-secondary w-100">
                    <i class="bi bi-pencil"></i> Modifica Evento
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/inc/footer.php'; ?>
