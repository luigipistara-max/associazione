<?php
/**
 * Portal - Groups Page
 * Display groups and allow members to request to join
 */

require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/functions.php';
require_once __DIR__ . '/inc/auth.php';

session_start();
$member = requirePortalLogin();
$basePath = $config['app']['base_path'];

$success = '';
$error = '';

// Handle group request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['group_id'])) {
    $groupId = (int)$_POST['group_id'];
    $message = trim($_POST['message'] ?? '');
    
    if (createGroupRequest($member['id'], $groupId, $message)) {
        $success = 'Richiesta inviata con successo! Un amministratore la esaminerà.';
    } else {
        $error = 'Impossibile inviare la richiesta. Potresti essere già nel gruppo o avere una richiesta pendente.';
    }
}

// Get member's groups
$memberGroups = getMemberGroups($member['id']);
$memberGroupIds = array_column($memberGroups, 'id');

// Get public groups (excluding groups member is already in)
$allPublicGroups = getPublicGroups();
$availableGroups = array_filter($allPublicGroups, function($group) use ($memberGroupIds) {
    return !in_array($group['id'], $memberGroupIds);
});

// Get member's pending requests
$pendingRequests = getMemberGroupRequests($member['id']);
$pendingGroupIds = [];
foreach ($pendingRequests as $request) {
    if ($request['status'] === 'pending') {
        $pendingGroupIds[] = $request['group_id'];
    }
}

$pageTitle = 'Gruppi';
require_once __DIR__ . '/inc/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-people"></i> Gruppi</h2>
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

        <!-- Member's Groups -->
        <div class="mb-5">
            <h4 class="mb-3">I miei gruppi</h4>
            
            <?php if (empty($memberGroups)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Non fai ancora parte di nessun gruppo.
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($memberGroups as $group): ?>
                        <div class="col-md-6 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <div class="d-flex align-items-start">
                                        <div class="me-3">
                                            <span class="badge rounded-circle p-3" 
                                                  style="background-color: <?php echo h($group['color'] ?? '#6c757d'); ?>; width: 50px; height: 50px;">
                                                <i class="bi bi-people text-white" style="font-size: 1.5rem;"></i>
                                            </span>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h5 class="card-title mb-1"><?php echo h($group['name']); ?></h5>
                                            <?php if ($group['description']): ?>
                                                <p class="card-text text-muted small mb-2">
                                                    <?php echo h($group['description']); ?>
                                                </p>
                                            <?php endif; ?>
                                            <small class="text-muted">
                                                <i class="bi bi-calendar-check"></i> 
                                                Membro dal <?php echo formatDate($group['added_at']); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Available Groups -->
        <div class="mb-5">
            <h4 class="mb-3">Gruppi disponibili</h4>
            
            <?php if (empty($availableGroups)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Non ci sono altri gruppi disponibili al momento.
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($availableGroups as $group): ?>
                        <?php $hasPendingRequest = in_array($group['id'], $pendingGroupIds); ?>
                        
                        <div class="col-md-6 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <div class="d-flex align-items-start">
                                        <div class="me-3">
                                            <span class="badge rounded-circle p-3" 
                                                  style="background-color: <?php echo h($group['color'] ?? '#6c757d'); ?>; width: 50px; height: 50px;">
                                                <i class="bi bi-people text-white" style="font-size: 1.5rem;"></i>
                                            </span>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h5 class="card-title mb-1"><?php echo h($group['name']); ?></h5>
                                            <?php if ($group['description']): ?>
                                                <p class="card-text text-muted small mb-3">
                                                    <?php echo h($group['description']); ?>
                                                </p>
                                            <?php endif; ?>
                                            
                                            <?php if ($hasPendingRequest): ?>
                                                <span class="badge bg-warning">
                                                    <i class="bi bi-hourglass-split"></i> Richiesta in attesa
                                                </span>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-sm btn-primary" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#requestModal<?php echo $group['id']; ?>">
                                                    <i class="bi bi-plus-circle"></i> Richiedi di partecipare
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Request Modal -->
                        <div class="modal fade" id="requestModal<?php echo $group['id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form method="POST">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Richiesta di partecipazione</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p>Stai richiedendo di partecipare al gruppo <strong><?php echo h($group['name']); ?></strong>.</p>
                                            
                                            <div class="mb-3">
                                                <label for="message<?php echo $group['id']; ?>" class="form-label">
                                                    Messaggio (opzionale)
                                                </label>
                                                <textarea class="form-control" 
                                                          id="message<?php echo $group['id']; ?>" 
                                                          name="message" 
                                                          rows="3" 
                                                          placeholder="Motiva la tua richiesta..."></textarea>
                                                <small class="text-muted">
                                                    Spiega perché vorresti far parte di questo gruppo.
                                                </small>
                                            </div>
                                            
                                            <input type="hidden" name="group_id" value="<?php echo $group['id']; ?>">
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                Annulla
                                            </button>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-send"></i> Invia richiesta
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pending Requests -->
        <?php if (!empty($pendingRequests)): ?>
            <div class="mb-5">
                <h4 class="mb-3">Le mie richieste</h4>
                
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Gruppo</th>
                                <th>Stato</th>
                                <th>Data richiesta</th>
                                <th>Note admin</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingRequests as $request): ?>
                                <tr>
                                    <td><?php echo h($request['group_name']); ?></td>
                                    <td>
                                        <?php if ($request['status'] === 'pending'): ?>
                                            <span class="badge bg-warning">
                                                <i class="bi bi-hourglass-split"></i> In attesa
                                            </span>
                                        <?php elseif ($request['status'] === 'approved'): ?>
                                            <span class="badge bg-success">
                                                <i class="bi bi-check-circle"></i> Approvata
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">
                                                <i class="bi bi-x-circle"></i> Rifiutata
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo formatDate($request['requested_at']); ?></td>
                                    <td>
                                        <?php if ($request['admin_notes']): ?>
                                            <small class="text-muted"><?php echo h($request['admin_notes']); ?></small>
                                        <?php else: ?>
                                            <small class="text-muted">-</small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
