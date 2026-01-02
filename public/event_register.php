<?php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/email.php';

requireLogin();

$basePath = $config['app']['base_path'];
$eventId = $_GET['id'] ?? null;
$action = $_GET['action'] ?? 'register';

if (!$eventId) {
    setFlashMessage('ID evento mancante', 'danger');
    redirect($basePath . 'events.php');
}

$event = getEvent($eventId);
if (!$event) {
    setFlashMessage('Evento non trovato', 'danger');
    redirect($basePath . 'events.php');
}

$currentUser = getCurrentUser();

// Find member record for current user
$stmt = $pdo->prepare("SELECT * FROM " . table('members') . " WHERE email = ? LIMIT 1");
$stmt->execute([$currentUser['email']]);
$member = $stmt->fetch();

if (!$member) {
    setFlashMessage('Solo i soci possono iscriversi agli eventi', 'warning');
    redirect($basePath . 'event_view.php?id=' . $eventId);
}

$memberId = $member['id'];

// Handle unregistration
if ($action === 'unregister') {
    if (unregisterFromEvent($eventId, $memberId)) {
        setFlashMessage('Iscrizione annullata con successo');
    } else {
        setFlashMessage('Errore nell\'annullamento dell\'iscrizione', 'danger');
    }
    redirect($basePath . 'event_view.php?id=' . $eventId);
}

// Handle registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'register') {
    $token = $_POST['csrf_token'] ?? '';
    
    if (!verifyCsrfToken($token)) {
        setFlashMessage('Token CSRF non valido', 'danger');
        redirect($basePath . 'event_view.php?id=' . $eventId);
    }
    
    // Check if event is published
    if ($event['status'] !== 'published') {
        setFlashMessage('L\'evento non è disponibile per le iscrizioni', 'warning');
        redirect($basePath . 'event_view.php?id=' . $eventId);
    }
    
    // Check if already registered
    if (isRegisteredForEvent($eventId, $memberId)) {
        setFlashMessage('Sei già iscritto a questo evento', 'info');
        redirect($basePath . 'event_view.php?id=' . $eventId);
    }
    
    // Register
    if (registerForEvent($eventId, $memberId)) {
        // Send confirmation email
        sendEventConfirmation($eventId, $memberId);
        
        setFlashMessage('Iscrizione completata con successo!');
        redirect($basePath . 'event_view.php?id=' . $eventId);
    } else {
        setFlashMessage('Errore durante l\'iscrizione', 'danger');
    }
}

$pageTitle = 'Iscrizione a ' . $event['title'];
$availableSpots = getAvailableSpots($eventId);

include __DIR__ . '/inc/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="bi bi-person-plus"></i> Iscrizione Evento
    </h1>
</div>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><?php echo h($event['title']); ?></h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <p><strong>Data:</strong> <?php echo formatDate($event['event_date']); ?>
                    <?php if ($event['event_time']): ?>
                        alle <?php echo substr($event['event_time'], 0, 5); ?>
                    <?php endif; ?>
                    </p>
                    
                    <?php if ($event['event_mode'] == 'in_person' && $event['location']): ?>
                    <p><strong>Luogo:</strong> <?php echo h($event['location']); ?>
                    <?php if ($event['city']): ?>
                        - <?php echo h($event['city']); ?>
                    <?php endif; ?>
                    </p>
                    <?php elseif ($event['event_mode'] == 'online'): ?>
                    <p><strong>Modalità:</strong> Online - <?php echo h($event['online_platform'] ?? 'Piattaforma online'); ?></p>
                    <p class="text-muted small">Il link per partecipare ti sarà inviato via email</p>
                    <?php elseif ($event['event_mode'] == 'hybrid'): ?>
                    <p><strong>Modalità:</strong> Ibrido (In presenza e Online)</p>
                    <?php endif; ?>
                    
                    <?php if ($event['cost'] > 0): ?>
                    <p><strong>Costo:</strong> <?php echo formatCurrency($event['cost']); ?></p>
                    <?php else: ?>
                    <p><strong>Costo:</strong> <span class="text-success">Gratuito</span></p>
                    <?php endif; ?>
                    
                    <?php if ($availableSpots !== null): ?>
                    <p><strong>Posti disponibili:</strong> 
                        <span class="badge bg-<?php echo $availableSpots > 0 ? 'success' : 'warning'; ?>">
                            <?php echo $availableSpots > 0 ? $availableSpots : 'Lista d\'attesa'; ?>
                        </span>
                    </p>
                    <?php endif; ?>
                </div>
                
                <hr>
                
                <div class="mb-3">
                    <h6>I tuoi dati:</h6>
                    <p class="mb-1"><strong>Nome:</strong> <?php echo h($member['first_name'] . ' ' . $member['last_name']); ?></p>
                    <p class="mb-1"><strong>Email:</strong> <?php echo h($member['email']); ?></p>
                    <p class="mb-1"><strong>Tessera N.:</strong> <?php echo h($member['membership_number'] ?? 'N/D'); ?></p>
                </div>
                
                <?php if ($event['cost'] > 0): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    Questo evento ha un costo di partecipazione. Riceverai istruzioni per il pagamento via email.
                </div>
                <?php endif; ?>
                
                <?php if ($availableSpots !== null && $availableSpots <= 0): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i>
                    Non ci sono posti disponibili. Verrai inserito in lista d'attesa e ti contatteremo se si libera un posto.
                </div>
                <?php endif; ?>
                
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-check-circle"></i> 
                            <?php echo ($availableSpots !== null && $availableSpots <= 0) ? 'Iscriviti alla Lista d\'Attesa' : 'Conferma Iscrizione'; ?>
                        </button>
                        <a href="<?php echo h($basePath); ?>event_view.php?id=<?php echo $eventId; ?>" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Torna all'Evento
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/inc/footer.php'; ?>
