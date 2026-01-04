<?php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/audit.php';

requireLogin();

$config = require __DIR__ . '/../src/config.php';
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

// Handle remove response action (admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isAdmin()) {
    if ($_POST['action'] === 'remove_response' && isset($_POST['response_id'])) {
        $token = $_POST['csrf_token'] ?? '';
        
        if (verifyCsrfToken($token)) {
            $responseId = (int)$_POST['response_id'];
            if (deleteEventResponse($responseId)) {
                setFlashMessage('Risposta rimossa con successo');
            } else {
                setFlashMessage('Errore nella rimozione della risposta', 'danger');
            }
            redirect($basePath . 'event_view.php?id=' . $eventId);
        }
    }
}

// Handle registration approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registration_action']) && isAdmin()) {
    $token = $_POST['csrf_token'] ?? '';
    
    if (verifyCsrfToken($token)) {
        $action = $_POST['registration_action'];
        $currentUser = getCurrentUser();
        $userId = $currentUser['id'];
        
        switch ($action) {
            case 'approve':
                $responseId = (int)$_POST['response_id'];
                if (approveEventRegistration($responseId, $userId)) {
                    setFlashMessage('Iscrizione approvata con successo');
                }
                break;
                
            case 'reject':
                $responseId = (int)$_POST['response_id'];
                $reason = trim($_POST['rejection_reason'] ?? '');
                if (rejectEventRegistration($responseId, $userId, $reason)) {
                    setFlashMessage('Iscrizione rifiutata');
                }
                break;
                
            case 'approve_all':
                $count = approveAllEventRegistrations($eventId, $userId);
                setFlashMessage("$count iscrizioni approvate");
                break;
                
            case 'reject_all':
                $reason = trim($_POST['rejection_reason'] ?? '');
                $count = rejectAllEventRegistrations($eventId, $userId, $reason);
                setFlashMessage("$count iscrizioni rifiutate");
                break;
                
            case 'revoke':
                $responseId = (int)$_POST['response_id'];
                if (revokeEventRegistration($responseId)) {
                    setFlashMessage('Iscrizione revocata');
                }
                break;
        }
        
        redirect($basePath . 'event_view.php?id=' . $eventId);
    }
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

// Get registrations from event_responses (not event_registrations)
// This is needed for available spots calculation and member registration check
$approvedCount = 0;
if (isAdmin()) {
    // For admin, we'll get the full lists later
    $approvedCount = count(getApprovedEventRegistrations($eventId));
} else {
    // For non-admin, just get the count
    $approvedCount = count(getApprovedEventRegistrations($eventId));
}
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
                            <strong>Iscritti:</strong> <?php echo $approvedCount; ?>
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
        
        <!-- Event Responses (Admin View) -->
        <?php if (isAdmin()): ?>
        <?php 
        $pendingRegistrations = getPendingEventRegistrations($eventId);
        $approvedRegistrations = getApprovedEventRegistrations($eventId);
        $rejectedRegistrations = getRejectedEventRegistrations($eventId);
        $responseCounts = countEventResponses($eventId);
        ?>
        
        <!-- Pending Registrations -->
        <?php if (!empty($pendingRegistrations)): ?>
        <div class="card mb-4">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0">
                    <i class="bi bi-hourglass-split"></i> Disponibilità in Attesa 
                    <span class="badge bg-dark"><?php echo count($pendingRegistrations); ?></span>
                </h5>
            </div>
            <div class="card-body">
                <!-- Bulk Actions -->
                <div class="mb-3">
                    <button class="btn btn-sm btn-success me-2" data-bs-toggle="modal" data-bs-target="#approveAllModal">
                        <i class="bi bi-check-all"></i> Approva Tutti
                    </button>
                    <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejectAllModal">
                        <i class="bi bi-x-lg"></i> Rifiuta Tutti
                    </button>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Socio</th>
                                <th>Risposta</th>
                                <th>Data</th>
                                <th>Note</th>
                                <th class="text-end">Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingRegistrations as $reg): ?>
                            <tr>
                                <td><?php echo h($reg['first_name'] . ' ' . $reg['last_name']); ?></td>
                                <td>
                                    <?php if ($reg['response'] === 'yes'): ?>
                                        <span class="badge bg-success">Sì</span>
                                    <?php elseif ($reg['response'] === 'maybe'): ?>
                                        <span class="badge bg-warning">Forse</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">No</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo formatDate($reg['responded_at']); ?></td>
                                <td><?php echo h($reg['notes'] ?? '-'); ?></td>
                                <td class="text-end">
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Approvare questa iscrizione?')">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                        <input type="hidden" name="registration_action" value="approve">
                                        <input type="hidden" name="response_id" value="<?php echo $reg['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-success" title="Approva">
                                            <i class="bi bi-check-lg"></i>
                                        </button>
                                    </form>
                                    <button type="button" class="btn btn-sm btn-danger" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#rejectModal<?php echo $reg['id']; ?>"
                                            title="Rifiuta">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Approved Registrations -->
        <?php if (!empty($approvedRegistrations)): ?>
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">
                    <i class="bi bi-check-circle"></i> Iscritti Confermati 
                    <span class="badge bg-light text-dark"><?php echo count($approvedRegistrations); ?></span>
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Socio</th>
                                <th>Email</th>
                                <th>Tessera</th>
                                <th>Approvato da</th>
                                <th>Data approvazione</th>
                                <th class="text-end">Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($approvedRegistrations as $reg): ?>
                            <tr>
                                <td><?php echo h($reg['first_name'] . ' ' . $reg['last_name']); ?></td>
                                <td><?php echo h($reg['email']); ?></td>
                                <td><?php echo h($reg['membership_number']); ?></td>
                                <td><?php echo h($reg['approved_by_name'] ?? '-'); ?></td>
                                <td><?php echo formatDate($reg['approved_at']); ?></td>
                                <td class="text-end">
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Revocare questa approvazione?')">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                        <input type="hidden" name="registration_action" value="revoke">
                                        <input type="hidden" name="response_id" value="<?php echo $reg['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-warning" title="Revoca">
                                            <i class="bi bi-arrow-counterclockwise"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Rejected Registrations -->
        <?php if (!empty($rejectedRegistrations)): ?>
        <div class="card mb-4">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0">
                    <i class="bi bi-x-circle"></i> Rifiutati 
                    <span class="badge bg-light text-dark"><?php echo count($rejectedRegistrations); ?></span>
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Socio</th>
                                <th>Motivo</th>
                                <th>Rifiutato da</th>
                                <th>Data rifiuto</th>
                                <th class="text-end">Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rejectedRegistrations as $reg): ?>
                            <tr>
                                <td><?php echo h($reg['first_name'] . ' ' . $reg['last_name']); ?></td>
                                <td><?php echo h($reg['rejection_reason'] ?? '-'); ?></td>
                                <td><?php echo h($reg['approved_by_name'] ?? '-'); ?></td>
                                <td><?php echo formatDate($reg['approved_at']); ?></td>
                                <td class="text-end">
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Riportare in stato pending?')">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                        <input type="hidden" name="registration_action" value="revoke">
                                        <input type="hidden" name="response_id" value="<?php echo $reg['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-warning" title="Revoca">
                                            <i class="bi bi-arrow-counterclockwise"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Response Summary Card -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-calendar-check"></i> Riepilogo Disponibilità</h5>
            </div>
            <div class="card-body">
                <div class="mb-2">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span><i class="bi bi-check-circle text-success"></i> Parteciperò</span>
                        <span class="badge bg-success"><?php echo $responseCounts['yes']; ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span><i class="bi bi-question-circle text-warning"></i> Forse</span>
                        <span class="badge bg-warning"><?php echo $responseCounts['maybe']; ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-x-circle text-danger"></i> Non parteciperò</span>
                        <span class="badge bg-danger"><?php echo $responseCounts['no']; ?></span>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Admin Actions -->
        <?php if (isAdmin()): ?>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-gear"></i> Azioni Admin</h5>
            </div>
            <div class="card-body">
                <!-- Note: Registration management is shown above with pending/approved/rejected sections -->
                <a href="#" onclick="window.scrollTo({top: 0, behavior: 'smooth'}); return false;" class="btn btn-info w-100 mb-2">
                    <i class="bi bi-arrow-up-circle"></i> Vai alle Iscrizioni (<?php echo count($approvedRegistrations); ?> approvati)
                </a>
                
                <?php if (in_array($event['event_mode'], ['online', 'hybrid']) && count($approvedRegistrations) > 0): ?>
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

<!-- Modals for approval workflow -->
<?php if (isAdmin()): ?>
    <!-- Approve All Modal -->
    <div class="modal fade" id="approveAllModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <input type="hidden" name="registration_action" value="approve_all">
                    
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title">Approva Tutti</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Sei sicuro di voler approvare tutte le disponibilità "Sì" in attesa?</p>
                        <p class="mb-0"><strong>Nota:</strong> Saranno approvate solo le risposte "Sì".</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check-all"></i> Approva Tutti
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Reject All Modal -->
    <div class="modal fade" id="rejectAllModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <input type="hidden" name="registration_action" value="reject_all">
                    
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title">Rifiuta Tutti</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Sei sicuro di voler rifiutare tutte le disponibilità in attesa?</p>
                        <div class="mb-3">
                            <label for="rejectAllReason" class="form-label">Motivo (opzionale)</label>
                            <input type="text" class="form-control" id="rejectAllReason" name="rejection_reason" 
                                   placeholder="Es: Posti esauriti">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-x-lg"></i> Rifiuta Tutti
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Individual Reject Modals -->
    <?php if (!empty($pendingRegistrations)): ?>
        <?php foreach ($pendingRegistrations as $reg): ?>
        <div class="modal fade" id="rejectModal<?php echo $reg['id']; ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <input type="hidden" name="registration_action" value="reject">
                        <input type="hidden" name="response_id" value="<?php echo $reg['id']; ?>">
                        
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title">Rifiuta Iscrizione</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p>Sei sicuro di voler rifiutare la disponibilità di <strong><?php echo h($reg['first_name'] . ' ' . $reg['last_name']); ?></strong>?</p>
                            <div class="mb-3">
                                <label for="rejectReason<?php echo $reg['id']; ?>" class="form-label">Motivo (opzionale)</label>
                                <input type="text" class="form-control" id="rejectReason<?php echo $reg['id']; ?>" 
                                       name="rejection_reason" placeholder="Es: Posti esauriti">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                            <button type="submit" class="btn btn-danger">
                                <i class="bi bi-x-lg"></i> Rifiuta
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
<?php endif; ?>

<!-- Modals for removing responses -->
<?php if (isAdmin() && !empty($responses)): ?>
    <?php foreach ($responses as $response): ?>
    <!-- Modal conferma rimozione per risposta ID <?php echo $response['id']; ?> -->
    <div class="modal fade" id="removeResponseModal<?php echo $response['id']; ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <input type="hidden" name="action" value="remove_response">
                    <input type="hidden" name="response_id" value="<?php echo $response['id']; ?>">
                    
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title">Conferma rimozione</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Sei sicuro di voler rimuovere la risposta di 
                           <strong><?php echo h($response['first_name'] . ' ' . $response['last_name']); ?></strong>
                           da questo evento?</p>
                        <p class="text-muted mb-0">
                            <small>Risposta: 
                                <?php if ($response['response'] === 'yes'): ?>
                                    <span class="badge bg-success">Sì</span>
                                <?php elseif ($response['response'] === 'maybe'): ?>
                                    <span class="badge bg-warning">Forse</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">No</span>
                                <?php endif; ?>
                            </small>
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash"></i> Rimuovi
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php include __DIR__ . '/inc/footer.php'; ?>
