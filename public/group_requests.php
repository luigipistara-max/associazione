<?php
/**
 * Admin - Group Requests
 * Manage member group join requests
 */

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/functions.php';

requireLogin();
$config = require __DIR__ . '/../src/config.php';
$pageTitle = 'Richieste Gruppi';

$success = '';
$error = '';

// Handle request approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['request_id'])) {
    $requestId = (int)$_POST['request_id'];
    $adminId = getCurrentUser()['id'];
    $notes = trim($_POST['admin_notes'] ?? '');
    
    if ($_POST['action'] === 'approve') {
        if (approveGroupRequest($requestId, $adminId, $notes)) {
            $success = 'Richiesta approvata con successo. Il socio è stato aggiunto al gruppo.';
        } else {
            $error = 'Errore nell\'approvare la richiesta.';
        }
    } elseif ($_POST['action'] === 'reject') {
        if (rejectGroupRequest($requestId, $adminId, $notes)) {
            $success = 'Richiesta rifiutata.';
        } else {
            $error = 'Errore nel rifiutare la richiesta.';
        }
    }
}

// Get all pending requests
$pendingRequests = getPendingGroupRequests();

require_once __DIR__ . '/inc/header.php';
?>

<div class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">
            <i class="bi bi-person-plus"></i> Richieste di partecipazione ai gruppi
        </h1>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle"></i> <?php echo h($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-circle"></i> <?php echo h($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (empty($pendingRequests)): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> Non ci sono richieste in attesa.
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Socio</th>
                                <th>Tessera</th>
                                <th>Email</th>
                                <th>Gruppo</th>
                                <th>Messaggio</th>
                                <th>Data richiesta</th>
                                <th>Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingRequests as $request): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo h($request['first_name'] . ' ' . $request['last_name']); ?></strong>
                                    </td>
                                    <td><?php echo h($request['membership_number']); ?></td>
                                    <td><?php echo h($request['email']); ?></td>
                                    <td>
                                        <span class="badge bg-primary">
                                            <?php echo h($request['group_name']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($request['message']): ?>
                                            <small class="text-muted"><?php echo h($request['message']); ?></small>
                                        <?php else: ?>
                                            <small class="text-muted">-</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo formatDate($request['requested_at']); ?></td>
                                    <td>
                                        <button type="button" 
                                                class="btn btn-sm btn-success me-1" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#approveModal<?php echo $request['id']; ?>">
                                            <i class="bi bi-check-circle"></i> Approva
                                        </button>
                                        <button type="button" 
                                                class="btn btn-sm btn-danger" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#rejectModal<?php echo $request['id']; ?>">
                                            <i class="bi bi-x-circle"></i> Rifiuta
                                        </button>
                                    </td>
                                </tr>

                                <!-- Approve Modal -->
                                <div class="modal fade" id="approveModal<?php echo $request['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Approva richiesta</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p>
                                                        Confermi di voler aggiungere <strong><?php echo h($request['first_name'] . ' ' . $request['last_name']); ?></strong>
                                                        al gruppo <strong><?php echo h($request['group_name']); ?></strong>?
                                                    </p>
                                                    
                                                    <div class="mb-3">
                                                        <label for="notes_approve_<?php echo $request['id']; ?>" class="form-label">
                                                            Note (opzionale)
                                                        </label>
                                                        <textarea class="form-control" 
                                                                  id="notes_approve_<?php echo $request['id']; ?>" 
                                                                  name="admin_notes" 
                                                                  rows="2"></textarea>
                                                    </div>
                                                    
                                                    <input type="hidden" name="action" value="approve">
                                                    <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                        Annulla
                                                    </button>
                                                    <button type="submit" class="btn btn-success">
                                                        <i class="bi bi-check-circle"></i> Approva
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- Reject Modal -->
                                <div class="modal fade" id="rejectModal<?php echo $request['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Rifiuta richiesta</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p>
                                                        Confermi di voler rifiutare la richiesta di <strong><?php echo h($request['first_name'] . ' ' . $request['last_name']); ?></strong>
                                                        di partecipare al gruppo <strong><?php echo h($request['group_name']); ?></strong>?
                                                    </p>
                                                    
                                                    <div class="mb-3">
                                                        <label for="notes_reject_<?php echo $request['id']; ?>" class="form-label">
                                                            Motivo del rifiuto (opzionale)
                                                        </label>
                                                        <textarea class="form-control" 
                                                                  id="notes_reject_<?php echo $request['id']; ?>" 
                                                                  name="admin_notes" 
                                                                  rows="2"
                                                                  placeholder="Spiega il motivo del rifiuto..."></textarea>
                                                    </div>
                                                    
                                                    <input type="hidden" name="action" value="reject">
                                                    <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                        Annulla
                                                    </button>
                                                    <button type="submit" class="btn btn-danger">
                                                        <i class="bi bi-x-circle"></i> Rifiuta
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
