<?php
/**
 * Portal - Events Page
 * Display events and allow members to set their availability
 */

require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/functions.php';
require_once __DIR__ . '/inc/auth.php';

session_start();
$member = requirePortalLogin();
$config = require __DIR__ . '/../../src/config.php';
$basePath = $config['app']['base_path'];

// Handle AJAX response submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['event_id'], $_POST['response'])) {
    header('Content-Type: application/json');
    
    $eventId = (int)$_POST['event_id'];
    $response = $_POST['response'];
    $notes = $_POST['notes'] ?? null;
    
    if (setMemberEventResponse($eventId, $member['id'], $response, $notes)) {
        // Get updated counts
        $counts = countEventResponses($eventId);
        echo json_encode([
            'success' => true, 
            'message' => 'Risposta salvata con successo',
            'counts' => $counts
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Errore nel salvare la risposta']);
    }
    exit;
}

// Get events visible to this member
$events = getMemberVisibleEvents($member['id'], true);

// Get member's responses
$responses = [];
foreach ($events as $event) {
    $memberResponse = getMemberEventResponse($event['id'], $member['id']);
    if ($memberResponse) {
        $responses[$event['id']] = $memberResponse;
    }
}

$pageTitle = 'Eventi';
require_once __DIR__ . '/inc/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-calendar-event"></i> Eventi</h2>
        </div>

        <?php if (empty($events)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> Non ci sono eventi disponibili al momento.
            </div>
        <?php else: ?>
            <?php foreach ($events as $event): ?>
                <?php 
                $eventDate = formatDate($event['event_date']);
                $eventTime = $event['event_time'] ? date('H:i', strtotime($event['event_time'])) : '';
                $memberResponse = $responses[$event['id']] ?? null;
                $responseCounts = countEventResponses($event['id']);
                ?>
                
                <div class="card mb-3">
                    <div class="card-header bg-light">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="mb-1"><?php echo h($event['title']); ?></h6>
                                <small class="text-muted">
                                    <i class="bi bi-calendar3"></i> <?php echo h($eventDate); ?>
                                    <?php if ($eventTime): ?>
                                        <i class="bi bi-clock ms-2"></i> <?php echo h($eventTime); ?>
                                    <?php endif; ?>
                                    
                                    <?php if ($event['event_mode'] === 'in_person' && $event['location']): ?>
                                        <i class="bi bi-geo-alt ms-2"></i> <?php echo h($event['location']); ?>
                                    <?php elseif ($event['event_mode'] === 'online'): ?>
                                        <i class="bi bi-camera-video ms-2"></i> Online
                                    <?php elseif ($event['event_mode'] === 'hybrid'): ?>
                                        <i class="bi bi-globe ms-2"></i> Ibrido
                                    <?php endif; ?>
                                </small>
                            </div>
                            
                            <?php if ($event['target_type'] === 'groups'): ?>
                                <span class="badge bg-primary">
                                    <i class="bi bi-people"></i> Gruppi specifici
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <?php if ($event['description']): ?>
                            <p class="mb-3"><?php echo nl2br(h($event['description'])); ?></p>
                        <?php endif; ?>
                        
                        <?php if ($event['event_mode'] === 'in_person' && ($event['address'] || $event['city'])): ?>
                            <p class="mb-2">
                                <strong>Luogo:</strong> 
                                <?php echo h($event['address'] ?? ''); ?>
                                <?php if ($event['city']): ?>
                                    <?php echo $event['address'] ? ', ' : ''; ?><?php echo h($event['city']); ?>
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                        
                        <?php if ($event['max_participants'] > 0): ?>
                            <p class="mb-2">
                                <strong>Posti disponibili:</strong> <?php echo (int)$event['max_participants']; ?>
                            </p>
                        <?php endif; ?>
                        
                        <?php if ($event['cost'] > 0): ?>
                            <p class="mb-2">
                                <strong>Costo:</strong> <?php echo formatAmount($event['cost']); ?>
                            </p>
                        <?php endif; ?>
                        
                        <hr>
                        
                        <div class="mb-3">
                            <strong class="d-block mb-2">La tua disponibilità:</strong>
                            
                            <div class="btn-group" role="group" data-event-id="<?php echo $event['id']; ?>">
                                <input type="radio" class="btn-check response-radio" 
                                       name="response_<?php echo $event['id']; ?>" 
                                       id="yes_<?php echo $event['id']; ?>" 
                                       value="yes"
                                       <?php echo ($memberResponse && $memberResponse['response'] === 'yes') ? 'checked' : ''; ?>>
                                <label class="btn btn-outline-success" for="yes_<?php echo $event['id']; ?>">
                                    ✅ Parteciperò
                                </label>
                                
                                <input type="radio" class="btn-check response-radio" 
                                       name="response_<?php echo $event['id']; ?>" 
                                       id="maybe_<?php echo $event['id']; ?>" 
                                       value="maybe"
                                       <?php echo ($memberResponse && $memberResponse['response'] === 'maybe') ? 'checked' : ''; ?>>
                                <label class="btn btn-outline-warning" for="maybe_<?php echo $event['id']; ?>">
                                    🤔 Forse
                                </label>
                                
                                <input type="radio" class="btn-check response-radio" 
                                       name="response_<?php echo $event['id']; ?>" 
                                       id="no_<?php echo $event['id']; ?>" 
                                       value="no"
                                       <?php echo ($memberResponse && $memberResponse['response'] === 'no') ? 'checked' : ''; ?>>
                                <label class="btn btn-outline-danger" for="no_<?php echo $event['id']; ?>">
                                    ❌ Non parteciperò
                                </label>
                            </div>
                            
                            <div id="response-message-<?php echo $event['id']; ?>" class="mt-2"></div>
                        </div>
                        
                        <?php 
                        $registrationStatus = getMemberEventRegistrationStatus($event['id'], $member['id']);
                        if ($registrationStatus && $registrationStatus['response'] === 'yes'): 
                        ?>
                        <div class="mt-3">
                            <strong>Stato iscrizione:</strong><br>
                            <?php if ($registrationStatus['registration_status'] === 'pending'): ?>
                                <span class="badge bg-warning">⏳ In attesa di approvazione</span>
                                <small class="text-muted d-block mt-1">La tua disponibilità è stata registrata e sarà valutata dall'organizzatore.</small>
                            <?php elseif ($registrationStatus['registration_status'] === 'approved'): ?>
                                <span class="badge bg-success">✅ Iscrizione confermata</span>
                                <small class="text-muted d-block mt-1">La tua partecipazione è stata approvata!</small>
                            <?php elseif ($registrationStatus['registration_status'] === 'rejected'): ?>
                                <span class="badge bg-danger">❌ Iscrizione rifiutata</span>
                                <?php if ($registrationStatus['rejection_reason']): ?>
                                    <small class="text-muted d-block mt-1">Motivo: <?php echo h($registrationStatus['rejection_reason']); ?></small>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="mt-3">
                            <small class="text-muted">
                                <i class="bi bi-people"></i> Risposte: 
                                <span id="counts-<?php echo $event['id']; ?>">
                                    <span class="badge bg-success"><?php echo $responseCounts['yes']; ?> Sì</span>
                                    <span class="badge bg-warning"><?php echo $responseCounts['maybe']; ?> Forse</span>
                                    <span class="badge bg-danger"><?php echo $responseCounts['no']; ?> No</span>
                                </span>
                            </small>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle response changes
    document.querySelectorAll('.response-radio').forEach(radio => {
        radio.addEventListener('change', function() {
            const eventId = this.closest('.btn-group').dataset.eventId;
            const response = this.value;
            const messageDiv = document.getElementById('response-message-' + eventId);
            
            // Show loading
            messageDiv.innerHTML = '<small class="text-muted"><i class="bi bi-hourglass-split"></i> Salvataggio...</small>';
            
            // Send AJAX request
            const formData = new FormData();
            formData.append('event_id', eventId);
            formData.append('response', response);
            
            fetch('<?php echo h($basePath); ?>portal/events.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    messageDiv.innerHTML = '<small class="text-success"><i class="bi bi-check-circle"></i> ' + data.message + '</small>';
                    
                    // Update response counts if provided
                    if (data.counts) {
                        const countsDiv = document.querySelector('#counts-' + eventId);
                        if (countsDiv) {
                            countsDiv.innerHTML = `
                                <span class="badge bg-success">${data.counts.yes} Sì</span>
                                <span class="badge bg-warning">${data.counts.maybe} Forse</span>
                                <span class="badge bg-danger">${data.counts.no} No</span>
                            `;
                        }
                    }
                    
                    // Reload page after a short delay to show updated registration status
                    // This ensures the registration status section is properly displayed
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    messageDiv.innerHTML = '<small class="text-danger"><i class="bi bi-x-circle"></i> ' + data.message + '</small>';
                }
            })
            .catch(error => {
                messageDiv.innerHTML = '<small class="text-danger"><i class="bi bi-x-circle"></i> Errore di comunicazione</small>';
                console.error('Error:', error);
            });
        });
    });
});
</script>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
