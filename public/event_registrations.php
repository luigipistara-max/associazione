<?php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/email.php';

requireLogin();
requireAdmin();

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

// Handle actions
$action = $_GET['action'] ?? '';

// Send online links to all registrants
if ($action === 'send_links' && in_array($event['event_mode'], ['online', 'hybrid'])) {
    $sent = sendOnlineLinkToRegistrants($eventId);
    setFlashMessage("Link inviati a $sent partecipanti");
    redirect($basePath . 'event_registrations.php?id=' . $eventId);
}

// Send reminder to all
if ($action === 'send_reminder') {
    $sent = sendEventReminder($eventId);
    setFlashMessage("Promemoria inviato a $sent partecipanti");
    redirect($basePath . 'event_registrations.php?id=' . $eventId);
}

// Export CSV
if ($action === 'export') {
    $registrations = getEventRegistrations($eventId);
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=iscrizioni_evento_' . $eventId . '_' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
    
    fputcsv($output, ['Tessera', 'Nome', 'Cognome', 'Email', 'Data Iscrizione', 'Stato Presenza', 'Stato Pagamento'], ';');
    
    foreach ($registrations as $reg) {
        fputcsv($output, [
            $reg['membership_number'] ?? '',
            $reg['first_name'],
            $reg['last_name'],
            $reg['email'],
            date('d/m/Y H:i', strtotime($reg['registered_at'])),
            $reg['attendance_status'],
            $reg['payment_status']
        ], ';');
    }
    
    fclose($output);
    exit;
}

// Update attendance status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_attendance'])) {
    $token = $_POST['csrf_token'] ?? '';
    
    if (verifyCsrfToken($token)) {
        $regId = $_POST['registration_id'];
        $status = $_POST['attendance_status'];
        
        $stmt = $pdo->prepare("UPDATE " . table('event_registrations') . " 
                               SET attendance_status = ? WHERE id = ?");
        $stmt->execute([$status, $regId]);
        
        setFlashMessage('Stato aggiornato');
    }
}

// Update payment status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_payment'])) {
    $token = $_POST['csrf_token'] ?? '';
    
    if (verifyCsrfToken($token)) {
        $regId = $_POST['registration_id'];
        $status = $_POST['payment_status'];
        
        $stmt = $pdo->prepare("UPDATE " . table('event_registrations') . " 
                               SET payment_status = ? WHERE id = ?");
        $stmt->execute([$status, $regId]);
        
        setFlashMessage('Stato pagamento aggiornato');
    }
}

// Remove registration
if (isset($_GET['remove']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $regId = $_GET['remove'];
    $token = $_POST['csrf_token'] ?? '';
    
    if (verifyCsrfToken($token)) {
        $stmt = $pdo->prepare("DELETE FROM " . table('event_registrations') . " WHERE id = ?");
        $stmt->execute([$regId]);
        
        setFlashMessage('Iscrizione rimossa');
        redirect($basePath . 'event_registrations.php?id=' . $eventId);
    }
}

$pageTitle = 'Iscrizioni - ' . $event['title'];
$registrations = getEventRegistrations($eventId);

// Group by status
$byAttendance = [];
foreach ($registrations as $reg) {
    $status = $reg['attendance_status'];
    if (!isset($byAttendance[$status])) {
        $byAttendance[$status] = [];
    }
    $byAttendance[$status][] = $reg;
}

include __DIR__ . '/inc/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="bi bi-list-check"></i> Iscrizioni: <?php echo h($event['title']); ?>
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="<?php echo h($basePath); ?>event_view.php?id=<?php echo $eventId; ?>" class="btn btn-secondary me-2">
            <i class="bi bi-arrow-left"></i> Torna all'Evento
        </a>
    </div>
</div>

<!-- Stats -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <h6 class="text-muted">Totale Iscritti</h6>
                <h3><?php echo count($registrations); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <h6 class="text-muted">Confermati</h6>
                <h3><?php echo count($byAttendance['confirmed'] ?? []); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <h6 class="text-muted">Lista d'Attesa</h6>
                <h3><?php echo count($byAttendance['waitlist'] ?? []); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <h6 class="text-muted">Presenti</h6>
                <h3><?php echo count($byAttendance['attended'] ?? []); ?></h3>
            </div>
        </div>
    </div>
</div>

<!-- Actions -->
<div class="card mb-4">
    <div class="card-body">
        <div class="btn-group" role="group">
            <a href="?id=<?php echo $eventId; ?>&action=export" class="btn btn-success">
                <i class="bi bi-download"></i> Esporta CSV
            </a>
            <a href="?id=<?php echo $eventId; ?>&action=send_reminder" class="btn btn-primary"
               onclick="return confirm('Inviare promemoria a tutti gli iscritti?')">
                <i class="bi bi-envelope"></i> Invia Promemoria
            </a>
            <?php if (in_array($event['event_mode'], ['online', 'hybrid'])): ?>
            <a href="?id=<?php echo $eventId; ?>&action=send_links" class="btn btn-info"
               onclick="return confirm('Inviare link online a tutti gli iscritti?')">
                <i class="bi bi-send"></i> Invia Link Online
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Registrations List -->
<?php if (empty($registrations)): ?>
<div class="alert alert-info">
    <i class="bi bi-info-circle"></i> Nessuna iscrizione presente.
</div>
<?php else: ?>
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Lista Iscritti</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Tessera</th>
                        <th>Nome</th>
                        <th>Email</th>
                        <th>Data Iscrizione</th>
                        <th>Stato Presenza</th>
                        <?php if ($event['cost'] > 0): ?>
                        <th>Pagamento</th>
                        <?php endif; ?>
                        <th>Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($registrations as $reg): 
                        $attendanceClass = 'secondary';
                        switch ($reg['attendance_status']) {
                            case 'confirmed': $attendanceClass = 'primary'; break;
                            case 'attended': $attendanceClass = 'success'; break;
                            case 'absent': $attendanceClass = 'danger'; break;
                            case 'waitlist': $attendanceClass = 'warning'; break;
                        }
                        
                        $paymentClass = 'secondary';
                        switch ($reg['payment_status']) {
                            case 'paid': $paymentClass = 'success'; break;
                            case 'pending': $paymentClass = 'warning'; break;
                            case 'refunded': $paymentClass = 'info'; break;
                        }
                    ?>
                    <tr>
                        <td><?php echo h($reg['membership_number'] ?? 'N/D'); ?></td>
                        <td><?php echo h($reg['first_name'] . ' ' . $reg['last_name']); ?></td>
                        <td><?php echo h($reg['email']); ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($reg['registered_at'])); ?></td>
                        <td>
                            <form method="POST" class="d-inline" id="attendance-form-<?php echo $reg['id']; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                <input type="hidden" name="registration_id" value="<?php echo $reg['id']; ?>">
                                <select name="attendance_status" class="form-select form-select-sm status-select" 
                                        aria-label="Stato presenza per <?php echo h($reg['first_name'] . ' ' . $reg['last_name']); ?>"
                                        style="width: auto; display: inline-block;">
                                    <option value="registered" <?php echo $reg['attendance_status'] == 'registered' ? 'selected' : ''; ?>>Registrato</option>
                                    <option value="confirmed" <?php echo $reg['attendance_status'] == 'confirmed' ? 'selected' : ''; ?>>Confermato</option>
                                    <option value="attended" <?php echo $reg['attendance_status'] == 'attended' ? 'selected' : ''; ?>>Presente</option>
                                    <option value="absent" <?php echo $reg['attendance_status'] == 'absent' ? 'selected' : ''; ?>>Assente</option>
                                    <option value="waitlist" <?php echo $reg['attendance_status'] == 'waitlist' ? 'selected' : ''; ?>>Lista d'attesa</option>
                                </select>
                                <input type="hidden" name="update_attendance" value="1">
                                <button type="submit" class="btn btn-sm btn-primary ms-1">
                                    <i class="bi bi-check"></i>
                                </button>
                            </form>
                        </td>
                        <?php if ($event['cost'] > 0): ?>
                        <td>
                            <form method="POST" class="d-inline" id="payment-form-<?php echo $reg['id']; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                <input type="hidden" name="registration_id" value="<?php echo $reg['id']; ?>">
                                <select name="payment_status" class="form-select form-select-sm status-select"
                                        aria-label="Stato pagamento per <?php echo h($reg['first_name'] . ' ' . $reg['last_name']); ?>"
                                        style="width: auto; display: inline-block;">
                                    <option value="pending" <?php echo $reg['payment_status'] == 'pending' ? 'selected' : ''; ?>>In attesa</option>
                                    <option value="paid" <?php echo $reg['payment_status'] == 'paid' ? 'selected' : ''; ?>>Pagato</option>
                                    <option value="refunded" <?php echo $reg['payment_status'] == 'refunded' ? 'selected' : ''; ?>>Rimborsato</option>
                                </select>
                                <input type="hidden" name="update_payment" value="1">
                                <button type="submit" class="btn btn-sm btn-primary ms-1">
                                    <i class="bi bi-check"></i>
                                </button>
                            </form>
                        </td>
                        <?php endif; ?>
                        <td>
                            <form method="POST" action="?id=<?php echo $eventId; ?>&remove=<?php echo $reg['id']; ?>" 
                                  class="d-inline"
                                  onsubmit="return confirm('Rimuovere questa iscrizione?')">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                <button type="submit" class="btn btn-sm btn-danger">
                                    <i class="bi bi-trash"></i>
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

<?php include __DIR__ . '/inc/footer.php'; ?>
