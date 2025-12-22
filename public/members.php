<?php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/db.php';

requireLogin();

$pageTitle = 'Gestione Soci';
$pdo = getDbConnection();

// Handle delete
if (isset($_GET['delete']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_GET['delete'];
    $token = $_POST['csrf_token'] ?? '';
    
    if (verifyCsrfToken($token)) {
        try {
            $stmt = $pdo->prepare("DELETE FROM members WHERE id = ?");
            $stmt->execute([$id]);
            setFlashMessage('Socio eliminato con successo');
        } catch (PDOException $e) {
            setFlashMessage('Errore nell\'eliminazione del socio: ' . $e->getMessage(), 'danger');
        }
        redirect('/members.php');
    }
}

// Get filters
$statusFilter = $_GET['status'] ?? '';
$searchQuery = $_GET['search'] ?? '';

// Build query
$sql = "SELECT * FROM members WHERE 1=1";
$params = [];

if ($statusFilter) {
    $sql .= " AND status = ?";
    $params[] = $statusFilter;
}

if ($searchQuery) {
    $sql .= " AND (first_name LIKE ? OR last_name LIKE ? OR tax_code LIKE ? OR email LIKE ?)";
    $search = "%$searchQuery%";
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
}

$sql .= " ORDER BY last_name, first_name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$members = $stmt->fetchAll();

include __DIR__ . '/inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="bi bi-people"></i> Gestione Soci</h2>
    <a href="/member_edit.php" class="btn btn-primary">
        <i class="bi bi-plus"></i> Nuovo Socio
    </a>
</div>

<!-- Filters -->
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Cerca</label>
                <input type="text" name="search" class="form-control" placeholder="Nome, cognome, CF, email..." value="<?php echo e($searchQuery); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Stato</label>
                <select name="status" class="form-select">
                    <option value="">Tutti</option>
                    <option value="attivo" <?php echo $statusFilter === 'attivo' ? 'selected' : ''; ?>>Attivo</option>
                    <option value="sospeso" <?php echo $statusFilter === 'sospeso' ? 'selected' : ''; ?>>Sospeso</option>
                    <option value="cessato" <?php echo $statusFilter === 'cessato' ? 'selected' : ''; ?>>Cessato</option>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="bi bi-search"></i> Cerca
                </button>
                <a href="/members.php" class="btn btn-secondary">
                    <i class="bi bi-x"></i> Reset
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Members Table -->
<div class="card">
    <div class="card-body">
        <?php if (empty($members)): ?>
            <p class="text-muted text-center">Nessun socio trovato.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Tessera</th>
                            <th>Nome Cognome</th>
                            <th>Codice Fiscale</th>
                            <th>Email</th>
                            <th>Telefono</th>
                            <th>Stato</th>
                            <th>Iscrizione</th>
                            <th class="text-end">Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($members as $member): ?>
                        <tr>
                            <td><?php echo e($member['card_number'] ?? '-'); ?></td>
                            <td>
                                <strong><?php echo e($member['first_name'] . ' ' . $member['last_name']); ?></strong>
                            </td>
                            <td><code><?php echo e($member['tax_code']); ?></code></td>
                            <td><?php echo e($member['email'] ?? '-'); ?></td>
                            <td><?php echo e($member['phone'] ?? '-'); ?></td>
                            <td>
                                <?php
                                $badgeClass = [
                                    'attivo' => 'success',
                                    'sospeso' => 'warning',
                                    'cessato' => 'secondary'
                                ][$member['status']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?php echo $badgeClass; ?>">
                                    <?php echo e(ucfirst($member['status'])); ?>
                                </span>
                            </td>
                            <td><?php echo formatDate($member['registration_date']); ?></td>
                            <td class="text-end">
                                <a href="/member_edit.php?id=<?php echo $member['id']; ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                        onclick="confirmDelete(<?php echo $member['id']; ?>, '<?php echo e($member['first_name'] . ' ' . $member['last_name']); ?>')">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="text-muted mt-2">Totale: <?php echo count($members); ?> soci</p>
        <?php endif; ?>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Conferma Eliminazione</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Sei sicuro di voler eliminare il socio <strong id="deleteMemberName"></strong>?
                <p class="text-danger mt-2"><small>Questa azione non può essere annullata.</small></p>
            </div>
            <div class="modal-footer">
                <form method="POST" id="deleteForm">
                    <input type="hidden" name="csrf_token" value="<?php echo e(generateCsrfToken()); ?>">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="submit" class="btn btn-danger">Elimina</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(id, name) {
    document.getElementById('deleteMemberName').textContent = name;
    document.getElementById('deleteForm').action = '/members.php?delete=' + id;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>

<?php include __DIR__ . '/inc/footer.php'; ?>
