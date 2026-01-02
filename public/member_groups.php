<?php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/db.php';

requireAdmin();

$basePath = $config['app']['base_path'];
$pageTitle = 'Gruppi Soci';

// Handle CSV export
if (isset($_GET['export']) && $_GET['export']) {
    $groupId = (int)$_GET['export'];
    exportGroupMembersCsv($groupId);
    exit;
}

// Handle delete
if (isset($_GET['delete']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_GET['delete'];
    $token = $_POST['csrf_token'] ?? '';
    
    if (verifyCsrfToken($token)) {
        deleteGroup($id);
        setFlashMessage('Gruppo eliminato con successo');
        redirect($basePath . 'member_groups.php');
    }
}

// Handle add/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $token = $_POST['csrf_token'] ?? '';
    
    if (!verifyCsrfToken($token)) {
        setFlashMessage('Token di sicurezza non valido', 'danger');
    } else {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $color = trim($_POST['color'] ?? '#6c757d');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $groupId = $_POST['group_id'] ?? null;
        
        if (empty($name)) {
            setFlashMessage('Il nome è obbligatorio', 'danger');
        } else {
            try {
                $data = [
                    'name' => $name,
                    'description' => $description,
                    'color' => $color,
                    'is_active' => $isActive
                ];
                
                if ($groupId) {
                    updateGroup($groupId, $data);
                    setFlashMessage('Gruppo aggiornato con successo');
                } else {
                    createGroup($data);
                    setFlashMessage('Gruppo creato con successo');
                }
                redirect($basePath . 'member_groups.php');
            } catch (PDOException $e) {
                setFlashMessage('Errore: ' . $e->getMessage(), 'danger');
            }
        }
    }
}

// Get all groups
$groups = getGroups(false);

// Get member counts for each group
$groupCounts = [];
foreach ($groups as $group) {
    $groupCounts[$group['id']] = getGroupMemberCount($group['id']);
}

include __DIR__ . '/inc/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="bi bi-people-fill"></i> Gruppi Soci</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#groupModal" 
                onclick="resetGroupForm()">
            <i class="bi bi-plus-circle"></i> Nuovo Gruppo
        </button>
    </div>
</div>

<?php displayFlash(); ?>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th style="width: 30px;"></th>
                        <th>Nome</th>
                        <th>Descrizione</th>
                        <th class="text-center">Soci</th>
                        <th class="text-center">Stato</th>
                        <th class="text-end">Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($groups)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted">Nessun gruppo trovato</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($groups as $group): ?>
                            <tr>
                                <td>
                                    <span class="badge" style="background-color: <?php echo h($group['color']); ?>; width: 20px; height: 20px; display: inline-block; border-radius: 3px;"></span>
                                </td>
                                <td><strong><?php echo h($group['name']); ?></strong></td>
                                <td><?php echo h($group['description'] ?? ''); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-info"><?php echo $groupCounts[$group['id']]; ?></span>
                                </td>
                                <td class="text-center">
                                    <?php if ($group['is_active']): ?>
                                        <span class="badge bg-success">Attivo</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inattivo</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="<?php echo h($basePath); ?>member_group_members.php?group_id=<?php echo $group['id']; ?>" 
                                           class="btn btn-outline-primary" title="Gestisci Membri">
                                            <i class="bi bi-people"></i>
                                        </a>
                                        <a href="?export=<?php echo $group['id']; ?>" 
                                           class="btn btn-outline-success" title="Export CSV">
                                            <i class="bi bi-download"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-warning" 
                                                onclick="editGroup(<?php echo htmlspecialchars(json_encode($group), ENT_QUOTES, 'UTF-8'); ?>)" 
                                                title="Modifica">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger" 
                                                onclick="confirmDelete(<?php echo $group['id']; ?>, '<?php echo addslashes($group['name']); ?>')" 
                                                title="Elimina">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Group Modal -->
<div class="modal fade" id="groupModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="group_id" id="group_id">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="groupModalTitle">Nuovo Gruppo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Nome <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Descrizione</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="color" class="form-label">Colore</label>
                        <input type="color" class="form-control form-control-color" id="color" name="color" value="#6c757d">
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="is_active" name="is_active" checked>
                        <label class="form-check-label" for="is_active">Attivo</label>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="submit" class="btn btn-primary">Salva</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="deleteForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Conferma Eliminazione</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <p>Sei sicuro di voler eliminare il gruppo <strong id="deleteGroupName"></strong>?</p>
                    <p class="text-muted mb-0">Questa azione eliminerà anche tutte le associazioni dei soci a questo gruppo.</p>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="submit" class="btn btn-danger">Elimina</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetGroupForm() {
    document.getElementById('groupModalTitle').textContent = 'Nuovo Gruppo';
    document.getElementById('group_id').value = '';
    document.getElementById('name').value = '';
    document.getElementById('description').value = '';
    document.getElementById('color').value = '#6c757d';
    document.getElementById('is_active').checked = true;
}

function editGroup(group) {
    document.getElementById('groupModalTitle').textContent = 'Modifica Gruppo';
    document.getElementById('group_id').value = group.id;
    document.getElementById('name').value = group.name;
    document.getElementById('description').value = group.description || '';
    document.getElementById('color').value = group.color || '#6c757d';
    document.getElementById('is_active').checked = group.is_active == 1;
    
    var modal = new bootstrap.Modal(document.getElementById('groupModal'));
    modal.show();
}

function confirmDelete(groupId, groupName) {
    document.getElementById('deleteGroupName').textContent = groupName;
    document.getElementById('deleteForm').action = '?delete=' + groupId;
    
    var modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    modal.show();
}
</script>

<?php include __DIR__ . '/inc/footer.php'; ?>
