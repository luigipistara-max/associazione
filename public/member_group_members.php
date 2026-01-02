<?php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/db.php';

requireAdmin();

$basePath = $config['app']['base_path'];
$groupId = $_GET['group_id'] ?? null;

if (!$groupId) {
    setFlashMessage('ID gruppo mancante', 'danger');
    redirect($basePath . 'member_groups.php');
}

$group = getGroup($groupId);
if (!$group) {
    setFlashMessage('Gruppo non trovato', 'danger');
    redirect($basePath . 'member_groups.php');
}

$pageTitle = 'Gestione Membri - ' . $group['name'];

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] == 1) {
    exportGroupMembersCsv($groupId);
    exit;
}

// Handle remove member
if (isset($_GET['remove']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $memberId = (int)$_GET['remove'];
    $token = $_POST['csrf_token'] ?? '';
    
    if (verifyCsrfToken($token)) {
        removeMemberFromGroup($groupId, $memberId);
        setFlashMessage('Socio rimosso dal gruppo con successo');
        redirect($basePath . 'member_group_members.php?group_id=' . $groupId);
    }
}

// Handle add member(s)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_members'])) {
    $token = $_POST['csrf_token'] ?? '';
    
    if (!verifyCsrfToken($token)) {
        setFlashMessage('Token di sicurezza non valido', 'danger');
    } else {
        $memberIds = $_POST['member_ids'] ?? [];
        
        if (empty($memberIds)) {
            setFlashMessage('Seleziona almeno un socio da aggiungere', 'warning');
        } else {
            $added = 0;
            foreach ($memberIds as $memberId) {
                addMemberToGroup($groupId, $memberId);
                $added++;
            }
            setFlashMessage("Aggiunti $added soci al gruppo con successo");
            redirect($basePath . 'member_group_members.php?group_id=' . $groupId);
        }
    }
}

// Get members in group
$groupMembers = getGroupMembers($groupId);
$groupMemberIds = array_column($groupMembers, 'id');

// Get all active members not in group
$stmt = $pdo->prepare("
    SELECT * FROM " . table('members') . "
    WHERE status = 'attivo'
    " . (empty($groupMemberIds) ? "" : "AND id NOT IN (" . implode(',', array_map('intval', $groupMemberIds)) . ")") . "
    ORDER BY last_name, first_name
");
$stmt->execute();
$availableMembers = $stmt->fetchAll();

include __DIR__ . '/inc/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <span class="badge" style="background-color: <?php echo h($group['color']); ?>; width: 30px; height: 30px; display: inline-block; border-radius: 5px; vertical-align: middle;"></span>
        <?php echo h($group['name']); ?>
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="<?php echo h($basePath); ?>member_groups.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Torna ai Gruppi
            </a>
        </div>
        <div class="btn-group me-2">
            <a href="?group_id=<?php echo $groupId; ?>&export=1" class="btn btn-success">
                <i class="bi bi-download"></i> Export CSV
            </a>
        </div>
        <div class="btn-group">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMembersModal">
                <i class="bi bi-plus-circle"></i> Aggiungi Soci
            </button>
        </div>
    </div>
</div>

<?php displayFlash(); ?>

<?php if ($group['description']): ?>
<div class="alert alert-info">
    <i class="bi bi-info-circle"></i> <?php echo h($group['description']); ?>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="bi bi-people"></i> Soci nel Gruppo 
            <span class="badge bg-primary"><?php echo count($groupMembers); ?></span>
        </h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Cognome</th>
                        <th>Email</th>
                        <th>Telefono</th>
                        <th>N. Tessera</th>
                        <th>Aggiunto il</th>
                        <th class="text-end">Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($groupMembers)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted">Nessun socio nel gruppo</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($groupMembers as $member): ?>
                            <tr>
                                <td><?php echo h($member['first_name']); ?></td>
                                <td><?php echo h($member['last_name']); ?></td>
                                <td><?php echo h($member['email'] ?? '-'); ?></td>
                                <td><?php echo h($member['phone'] ?? '-'); ?></td>
                                <td><?php echo h($member['membership_number'] ?? '-'); ?></td>
                                <td><?php echo formatDate($member['added_at']); ?></td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                            onclick="confirmRemove(<?php echo $member['id']; ?>, '<?php echo addslashes($member['first_name'] . ' ' . $member['last_name']); ?>')" 
                                            title="Rimuovi">
                                        <i class="bi bi-x-circle"></i> Rimuovi
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Members Modal -->
<div class="modal fade" id="addMembersModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="add_members" value="1">
                
                <div class="modal-header">
                    <h5 class="modal-title">Aggiungi Soci al Gruppo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <?php if (empty($availableMembers)): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> Tutti i soci attivi sono già nel gruppo
                        </div>
                    <?php else: ?>
                        <div class="mb-3">
                            <input type="text" class="form-control" id="searchMembers" placeholder="Cerca soci..." onkeyup="filterMembers()">
                        </div>
                        
                        <div style="max-height: 400px; overflow-y: auto;">
                            <table class="table table-sm table-hover" id="membersTable">
                                <thead class="sticky-top bg-white">
                                    <tr>
                                        <th style="width: 30px;">
                                            <input type="checkbox" class="form-check-input" id="selectAll" onchange="toggleSelectAll()">
                                        </th>
                                        <th>Nome</th>
                                        <th>Cognome</th>
                                        <th>Email</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($availableMembers as $member): ?>
                                        <tr class="member-row" data-search="<?php echo strtolower($member['first_name'] . ' ' . $member['last_name'] . ' ' . ($member['email'] ?? '')); ?>">
                                            <td>
                                                <input type="checkbox" class="form-check-input member-checkbox" name="member_ids[]" value="<?php echo $member['id']; ?>">
                                            </td>
                                            <td><?php echo h($member['first_name']); ?></td>
                                            <td><?php echo h($member['last_name']); ?></td>
                                            <td><?php echo h($member['email'] ?? '-'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <?php if (!empty($availableMembers)): ?>
                        <button type="submit" class="btn btn-primary">Aggiungi Selezionati</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Remove Confirmation Modal -->
<div class="modal fade" id="removeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="removeForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                
                <div class="modal-header bg-warning">
                    <h5 class="modal-title">Conferma Rimozione</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <p>Sei sicuro di voler rimuovere <strong id="removeMemberName"></strong> dal gruppo?</p>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="submit" class="btn btn-warning">Rimuovi</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function confirmRemove(memberId, memberName) {
    document.getElementById('removeMemberName').textContent = memberName;
    document.getElementById('removeForm').action = '?group_id=<?php echo $groupId; ?>&remove=' + memberId;
    
    var modal = new bootstrap.Modal(document.getElementById('removeModal'));
    modal.show();
}

function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.member-checkbox');
    const visibleCheckboxes = Array.from(checkboxes).filter(cb => {
        const row = cb.closest('tr');
        return row.style.display !== 'none';
    });
    
    visibleCheckboxes.forEach(cb => {
        cb.checked = selectAll.checked;
    });
}

function filterMembers() {
    const searchText = document.getElementById('searchMembers').value.toLowerCase();
    const rows = document.querySelectorAll('.member-row');
    
    rows.forEach(row => {
        const searchData = row.getAttribute('data-search');
        if (searchData.includes(searchText)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
    
    // Reset select all when filtering
    document.getElementById('selectAll').checked = false;
}
</script>

<?php include __DIR__ . '/inc/footer.php'; ?>
