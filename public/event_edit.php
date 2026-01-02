<?php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/db.php';

requireLogin();
requireAdmin();

$basePath = $config['app']['base_path'];
$eventId = $_GET['id'] ?? null;
$event = null;

if ($eventId) {
    $event = getEvent($eventId);
    if (!$event) {
        setFlashMessage('Evento non trovato', 'danger');
        redirect($basePath . 'events.php');
    }
    $pageTitle = 'Modifica Evento';
} else {
    $pageTitle = 'Nuovo Evento';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    
    if (!verifyCsrfToken($token)) {
        setFlashMessage('Token CSRF non valido', 'danger');
    } else {
        $data = [
            'title' => trim($_POST['title'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'event_date' => $_POST['event_date'] ?? '',
            'event_time' => $_POST['event_time'] ?? null,
            'end_date' => $_POST['end_date'] ?: null,
            'end_time' => $_POST['end_time'] ?: null,
            'event_mode' => $_POST['event_mode'] ?? 'in_person',
            'location' => trim($_POST['location'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
            'city' => trim($_POST['city'] ?? ''),
            'online_link' => trim($_POST['online_link'] ?? ''),
            'online_platform' => trim($_POST['online_platform'] ?? ''),
            'online_instructions' => trim($_POST['online_instructions'] ?? ''),
            'online_password' => trim($_POST['online_password'] ?? ''),
            'max_participants' => intval($_POST['max_participants'] ?? 0),
            'registration_deadline' => $_POST['registration_deadline'] ?: null,
            'cost' => floatval($_POST['cost'] ?? 0),
            'status' => $_POST['status'] ?? 'draft',
            'created_by' => getCurrentUser()['id']
        ];
        
        // Validation
        $errors = [];
        if (empty($data['title'])) {
            $errors[] = 'Il titolo è obbligatorio';
        }
        if (empty($data['event_date'])) {
            $errors[] = 'La data dell\'evento è obbligatoria';
        }
        
        if (empty($errors)) {
            try {
                if ($eventId) {
                    updateEvent($eventId, $data);
                    setFlashMessage('Evento aggiornato con successo');
                } else {
                    $newEventId = createEvent($data);
                    setFlashMessage('Evento creato con successo');
                    redirect($basePath . 'event_edit.php?id=' . $newEventId);
                }
            } catch (PDOException $e) {
                setFlashMessage('Errore nel salvataggio: ' . $e->getMessage(), 'danger');
            }
        } else {
            setFlashMessage(implode('<br>', $errors), 'danger');
        }
    }
}

// Load event data or defaults
$formData = $event ?? [
    'title' => '',
    'description' => '',
    'event_date' => '',
    'event_time' => '',
    'end_date' => '',
    'end_time' => '',
    'event_mode' => 'in_person',
    'location' => '',
    'address' => '',
    'city' => '',
    'online_link' => '',
    'online_platform' => 'Zoom',
    'online_instructions' => '',
    'online_password' => '',
    'max_participants' => 0,
    'registration_deadline' => '',
    'cost' => 0,
    'status' => 'draft'
];

include __DIR__ . '/inc/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="bi bi-calendar-event"></i> <?php echo $eventId ? 'Modifica Evento' : 'Nuovo Evento'; ?>
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="<?php echo h($basePath); ?>events.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Torna agli Eventi
        </a>
    </div>
</div>

<form method="POST" class="needs-validation" novalidate>
    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
    
    <!-- Section 1: Base Information -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-info-circle"></i> Informazioni Base</h5>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-12">
                    <label for="title" class="form-label">Titolo Evento <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="title" name="title" 
                           value="<?php echo h($formData['title']); ?>" required>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-12">
                    <label for="description" class="form-label">Descrizione</label>
                    <textarea class="form-control" id="description" name="description" rows="4" placeholder="Inserisci una descrizione dettagliata dell'evento (opzionale)"><?php echo h($formData['description']); ?></textarea>
                    <div class="form-text">Campo opzionale - descrivi il contenuto, obiettivi e dettagli dell'evento</div>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-3">
                    <label for="event_date" class="form-label">Data Inizio <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="event_date" name="event_date" 
                           value="<?php echo h($formData['event_date']); ?>" required>
                </div>
                <div class="col-md-3">
                    <label for="event_time" class="form-label">Ora Inizio</label>
                    <input type="time" class="form-control" id="event_time" name="event_time" 
                           value="<?php echo h($formData['event_time']); ?>">
                </div>
                <div class="col-md-3">
                    <label for="end_date" class="form-label">Data Fine</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" 
                           value="<?php echo h($formData['end_date']); ?>">
                </div>
                <div class="col-md-3">
                    <label for="end_time" class="form-label">Ora Fine</label>
                    <input type="time" class="form-control" id="end_time" name="end_time" 
                           value="<?php echo h($formData['end_time']); ?>">
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-4">
                    <label for="status" class="form-label">Stato</label>
                    <select class="form-select" id="status" name="status">
                        <option value="draft" <?php echo $formData['status'] == 'draft' ? 'selected' : ''; ?>>Bozza</option>
                        <option value="published" <?php echo $formData['status'] == 'published' ? 'selected' : ''; ?>>Pubblicato</option>
                        <option value="cancelled" <?php echo $formData['status'] == 'cancelled' ? 'selected' : ''; ?>>Annullato</option>
                        <option value="completed" <?php echo $formData['status'] == 'completed' ? 'selected' : ''; ?>>Completato</option>
                    </select>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Section 2: Event Mode -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-geo-alt"></i> Modalità Evento</h5>
        </div>
        <div class="card-body">
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="event_mode" id="mode_in_person" 
                               value="in_person" <?php echo $formData['event_mode'] == 'in_person' ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="mode_in_person">
                            🏢 Di Persona
                        </label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="event_mode" id="mode_online" 
                               value="online" <?php echo $formData['event_mode'] == 'online' ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="mode_online">
                            💻 Online
                        </label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="event_mode" id="mode_hybrid" 
                               value="hybrid" <?php echo $formData['event_mode'] == 'hybrid' ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="mode_hybrid">
                            🔄 Ibrido
                        </label>
                    </div>
                </div>
            </div>
            
            <!-- In Person Fields -->
            <div id="location-fields" style="display: none;">
                <h6 class="mb-3"><i class="bi bi-geo-alt"></i> Dettagli Luogo (Di Persona)</h6>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="location" class="form-label">Nome Luogo</label>
                        <input type="text" class="form-control" id="location" name="location" 
                               value="<?php echo h($formData['location']); ?>" placeholder="es. Sede Associazione">
                    </div>
                    <div class="col-md-6">
                        <label for="city" class="form-label">Città</label>
                        <input type="text" class="form-control" id="city" name="city" 
                               value="<?php echo h($formData['city']); ?>" placeholder="es. Milano">
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-12">
                        <label for="address" class="form-label">Indirizzo Completo</label>
                        <input type="text" class="form-control" id="address" name="address" 
                               value="<?php echo h($formData['address']); ?>" placeholder="es. Via Roma 123">
                    </div>
                </div>
            </div>
            
            <!-- Online Fields -->
            <div id="online-fields" style="display: none;">
                <h6 class="mb-3"><i class="bi bi-camera-video"></i> Dettagli Collegamento Online</h6>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="online_platform" class="form-label">Piattaforma</label>
                        <select class="form-select" id="online_platform" name="online_platform">
                            <option value="Zoom" <?php echo $formData['online_platform'] == 'Zoom' ? 'selected' : ''; ?>>Zoom</option>
                            <option value="Google Meet" <?php echo $formData['online_platform'] == 'Google Meet' ? 'selected' : ''; ?>>Google Meet</option>
                            <option value="Microsoft Teams" <?php echo $formData['online_platform'] == 'Microsoft Teams' ? 'selected' : ''; ?>>Microsoft Teams</option>
                            <option value="Skype" <?php echo $formData['online_platform'] == 'Skype' ? 'selected' : ''; ?>>Skype</option>
                            <option value="Altro" <?php echo $formData['online_platform'] == 'Altro' ? 'selected' : ''; ?>>Altro</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="online_password" class="form-label">Password Meeting (opzionale)</label>
                        <input type="text" class="form-control" id="online_password" name="online_password" 
                               value="<?php echo h($formData['online_password']); ?>" placeholder="Password meeting">
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-12">
                        <label for="online_link" class="form-label">Link Collegamento</label>
                        <input type="url" class="form-control" id="online_link" name="online_link" 
                               value="<?php echo h($formData['online_link']); ?>" placeholder="https://zoom.us/j/...">
                        <div class="form-text">Il link sarà visibile solo agli iscritti</div>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-12">
                        <label for="online_instructions" class="form-label">Istruzioni per Collegarsi</label>
                        <textarea class="form-control" id="online_instructions" name="online_instructions" rows="3" 
                                  placeholder="es. Clicca sul link 10 minuti prima dell'inizio. Assicurati di avere webcam e microfono funzionanti."><?php echo h($formData['online_instructions']); ?></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Section 3: Registrations -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-people"></i> Gestione Iscrizioni</h5>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-4">
                    <label for="max_participants" class="form-label">Max Partecipanti</label>
                    <input type="number" class="form-control" id="max_participants" name="max_participants" 
                           value="<?php echo h($formData['max_participants']); ?>" min="0">
                    <div class="form-text">0 = illimitati</div>
                </div>
                <div class="col-md-4">
                    <label for="registration_deadline" class="form-label">Scadenza Iscrizioni</label>
                    <input type="date" class="form-control" id="registration_deadline" name="registration_deadline" 
                           value="<?php echo h($formData['registration_deadline']); ?>">
                </div>
                <div class="col-md-4">
                    <label for="cost" class="form-label">Costo Partecipazione (€)</label>
                    <input type="number" class="form-control" id="cost" name="cost" 
                           value="<?php echo h($formData['cost']); ?>" min="0" step="0.01">
                    <div class="form-text">0 = gratuito</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Submit buttons -->
    <div class="mb-4">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-save"></i> Salva Evento
        </button>
        <a href="<?php echo h($basePath); ?>events.php" class="btn btn-secondary">
            <i class="bi bi-x-circle"></i> Annulla
        </a>
    </div>
</form>

<script>
// Show/hide fields based on event mode
function updateModeFields() {
    const mode = document.querySelector('input[name="event_mode"]:checked').value;
    const locationFields = document.getElementById('location-fields');
    const onlineFields = document.getElementById('online-fields');
    
    locationFields.style.display = (mode === 'in_person' || mode === 'hybrid') ? 'block' : 'none';
    onlineFields.style.display = (mode === 'online' || mode === 'hybrid') ? 'block' : 'none';
}

document.querySelectorAll('input[name="event_mode"]').forEach(radio => {
    radio.addEventListener('change', updateModeFields);
});

// Initialize on page load
updateModeFields();
</script>

<?php include __DIR__ . '/inc/footer.php'; ?>
