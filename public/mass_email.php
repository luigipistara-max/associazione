<?php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/email.php';

requireLogin();
requireAdmin();

$config = require __DIR__ . '/../src/config.php';
$pageTitle = 'Email Massiva';
$basePath = $config['app']['base_path'];

// Get all events for filter
$allEvents = getEvents([], 500, 0);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    
    if (!verifyCsrfToken($token)) {
        setFlashMessage('Token CSRF non valido', 'danger');
    } else {
        $filterType = $_POST['filter_type'] ?? 'all';
        $subject = trim($_POST['subject'] ?? '');
        $bodyHtml = trim($_POST['body_html'] ?? '');
        $eventId = $_POST['event_id'] ?? null;
        $sendCopy = isset($_POST['send_copy']);
        
        // Validation
        $errors = [];
        if (empty($subject)) {
            $errors[] = 'L\'oggetto è obbligatorio';
        }
        if (empty($bodyHtml)) {
            $errors[] = 'Il messaggio è obbligatorio';
        }
        
        if (empty($errors)) {
            // Get recipients
            $params = [];
            if ($filterType === 'event_registered' && $eventId) {
                $params['event_id'] = $eventId;
            }
            
            $recipients = getMassEmailRecipients($filterType, $params);
            
            if (empty($recipients)) {
                setFlashMessage('Nessun destinatario trovato con i filtri selezionati', 'warning');
            } else {
                $recipientIds = array_column($recipients, 'id');
                $currentUser = getCurrentUser();
                
                // Queue emails
                $batchId = queueMassEmail($recipientIds, $subject, $bodyHtml, $currentUser['id']);
                
                // Send copy to self
                if ($sendCopy && $currentUser['email']) {
                    queueEmail($currentUser['email'], '[COPIA] ' . $subject, $bodyHtml);
                }
                
                setFlashMessage('Email accodate con successo! Totale: ' . count($recipients) . ' destinatari', 'success');
                redirect($basePath . 'mass_email.php');
            }
        } else {
            setFlashMessage(implode('<br>', $errors), 'danger');
        }
    }
}

// Get current year for filters
$currentYear = getCurrentSocialYear();

include __DIR__ . '/inc/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="bi bi-envelope-at"></i> Invio Email Massiva</h1>
</div>

<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle"></i>
    <strong>Attenzione:</strong> Su AlterVista è possibile inviare massimo 50 email al giorno. 
    Le email verranno accodate e inviate automaticamente rispettando questo limite.
</div>

<form method="POST" id="massEmailForm">
    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
    
    <!-- Recipient Selection -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-people"></i> Seleziona Destinatari</h5>
        </div>
        <div class="card-body">
            <div class="mb-3">
                <label for="filter_type" class="form-label">Gruppo Destinatari</label>
                <select class="form-select" id="filter_type" name="filter_type" required>
                    <option value="all">Tutti i soci</option>
                    <option value="active_paid">Solo soci attivi (quota pagata anno corrente)</option>
                    <option value="overdue">Soci morosi (quota scaduta)</option>
                    <option value="no_fee_current_year">Soci senza quota anno corrente</option>
                    <option value="event_registered">Iscritti a evento specifico</option>
                </select>
            </div>
            
            <div id="event_selector" style="display: none;" class="mb-3">
                <label for="event_id" class="form-label">Seleziona Evento</label>
                <select class="form-select" id="event_id" name="event_id">
                    <option value="">-- Seleziona un evento --</option>
                    <?php foreach ($allEvents as $evt): ?>
                    <option value="<?php echo $evt['id']; ?>">
                        <?php echo h($evt['title']); ?> - <?php echo formatDate($evt['event_date']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div id="recipient_count" class="alert alert-info">
                <i class="bi bi-info-circle"></i> 
                <span id="count_text">Calcolo destinatari...</span>
            </div>
        </div>
    </div>
    
    <!-- Email Composition -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-envelope-paper"></i> Componi Email</h5>
        </div>
        <div class="card-body">
            <div class="mb-3">
                <label for="subject" class="form-label">Oggetto <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="subject" name="subject" required
                       placeholder="Inserisci l'oggetto dell'email">
            </div>
            
            <div class="mb-3">
                <label for="body_html" class="form-label">Messaggio <span class="text-danger">*</span></label>
                <textarea class="form-control" id="body_html" name="body_html" rows="12" required
                          placeholder="Caro {nome},&#10;&#10;Scrivi qui il tuo messaggio...&#10;&#10;Cordiali saluti,&#10;L'Associazione"></textarea>
            </div>
            
            <div class="alert alert-secondary">
                <strong>Variabili disponibili:</strong><br>
                <code>{nome}</code> - Nome del socio<br>
                <code>{cognome}</code> - Cognome del socio<br>
                <code>{email}</code> - Email del socio<br>
                <code>{tessera}</code> - Numero tessera<br>
                <small class="text-muted">Le variabili verranno sostituite automaticamente per ogni destinatario</small>
            </div>
            
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="send_copy" name="send_copy">
                <label class="form-check-label" for="send_copy">
                    Invia una copia a me stesso
                </label>
            </div>
        </div>
    </div>
    
    <!-- Actions -->
    <div class="mb-4">
        <button type="button" class="btn btn-outline-primary" onclick="previewEmail()">
            <i class="bi bi-eye"></i> Anteprima
        </button>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-send"></i> Accoda Invio
        </button>
    </div>
</form>

<!-- Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Anteprima Email</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <strong>Oggetto:</strong>
                    <p id="preview_subject"></p>
                </div>
                <div>
                    <strong>Messaggio:</strong>
                    <div id="preview_body" style="border: 1px solid #dee2e6; padding: 15px; background: #fff;">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
            </div>
        </div>
    </div>
</div>

<script>
// Show/hide event selector
document.getElementById('filter_type').addEventListener('change', function() {
    const eventSelector = document.getElementById('event_selector');
    eventSelector.style.display = (this.value === 'event_registered') ? 'block' : 'none';
    updateRecipientCount();
});

// Update recipient count when filter changes
document.getElementById('filter_type').addEventListener('change', updateRecipientCount);
document.getElementById('event_id').addEventListener('change', updateRecipientCount);

function updateRecipientCount() {
    const filterType = document.getElementById('filter_type').value;
    const eventId = document.getElementById('event_id').value;
    const countText = document.getElementById('count_text');
    
    countText.innerHTML = '<i class="spinner-border spinner-border-sm"></i> Calcolo...';
    
    // Make AJAX request to count recipients
    fetch('<?php echo $basePath; ?>api/count_email_recipients.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            filter_type: filterType,
            event_id: eventId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.count !== undefined) {
            countText.innerHTML = '<strong>Totale destinatari:</strong> ' + data.count + ' soci';
        } else {
            countText.innerHTML = 'Errore nel calcolo dei destinatari';
        }
    })
    .catch(error => {
        countText.innerHTML = 'Errore nel calcolo dei destinatari';
    });
}

function previewEmail() {
    const subject = document.getElementById('subject').value;
    const body = document.getElementById('body_html').value;
    
    // Replace variables with example data
    const previewSubject = subject
        .replace(/{nome}/g, 'Mario')
        .replace(/{cognome}/g, 'Rossi')
        .replace(/{email}/g, 'mario.rossi@example.com')
        .replace(/{tessera}/g, '001');
    
    const previewBody = body
        .replace(/{nome}/g, 'Mario')
        .replace(/{cognome}/g, 'Rossi')
        .replace(/{email}/g, 'mario.rossi@example.com')
        .replace(/{tessera}/g, '001')
        .replace(/\n/g, '<br>');
    
    document.getElementById('preview_subject').textContent = previewSubject;
    document.getElementById('preview_body').innerHTML = previewBody;
    
    const modal = new bootstrap.Modal(document.getElementById('previewModal'));
    modal.show();
}

// Initial count
updateRecipientCount();
</script>

<?php include __DIR__ . '/inc/footer.php'; ?>
