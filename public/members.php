<?php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/functions.php';

requireLogin();

$pageTitle = 'Gestione Soci';

// Handle delete
if (isset($_GET['delete']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $id = (int)$_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM " . table('members') . " WHERE id = ?");
        $stmt->execute([$id]);
        setFlash('success', 'Socio eliminato con successo');
        redirect('members.php');
    } catch (PDOException $e) {
        setFlash('error', 'Errore durante l\'eliminazione: ' . $e->getMessage());
    }
}

// Get filters
$searchTerm = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';

// Build query
$sql = "SELECT * FROM " . table('members') . " WHERE 1=1";
$params = [];

if ($searchTerm) {
    $sql .= " AND (first_name LIKE ? OR last_name LIKE ? OR fiscal_code LIKE ? OR membership_number LIKE ?)";
    $searchPattern = '%' . $searchTerm . '%';
    $params[] = $searchPattern;
    $params[] = $searchPattern;
    $params[] = $searchPattern;
    $params[] = $searchPattern;
}

if ($statusFilter) {
    $sql .= " AND status = ?";
    $params[] = $statusFilter;
}

$sql .= " ORDER BY last_name, first_name";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $members = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Errore database: " . htmlspecialchars($e->getMessage()));
}

include __DIR__ . '/inc/header.php';
?>

<?php displayFlash(); ?>

<div class="row mb-3">
    <div class="col-md-6">
        <h2><i class="bi bi-people me-2"></i>Soci</h2>
    </div>
    <div class="col-md-6 text-end">
        <a href="member_edit.php" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i>Nuovo Socio
        </a>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-6">
                <input type="text" name="search" class="form-control" placeholder="Cerca per nome, cognome, CF o tessera..." value="<?= h($searchTerm) ?>">
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">Tutti gli stati</option>
                    <option value="attivo" <?= $statusFilter === 'attivo' ? 'selected' : '' ?>>Attivo</option>
                    <option value="sospeso" <?= $statusFilter === 'sospeso' ? 'selected' : '' ?>>Sospeso</option>
                    <option value="cessato" <?= $statusFilter === 'cessato' ? 'selected' : '' ?>>Cessato</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search me-1"></i>Cerca
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <?php if (empty($members)): ?>
            <p class="text-muted text-center py-5">Nessun socio trovato</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Tessera</th>
                            <th>Nome</th>
                            <th>Cognome</th>
                            <th>Codice Fiscale</th>
                            <th>Email</th>
                            <th>Telefono</th>
                            <th>Stato</th>
                            <th>Data Iscrizione</th>
                            <th class="text-end">Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($members as $member): ?>
                        <tr>
                            <td><?= h($member['membership_number']) ?></td>
                            <td><?= h($member['first_name']) ?></td>
                            <td><?= h($member['last_name']) ?></td>
                            <td><?= h($member['fiscal_code']) ?></td>
                            <td><?= h($member['email']) ?></td>
                            <td><?= h($member['phone']) ?></td>
                            <td>
                                <span class="badge bg-<?= $member['status'] === 'attivo' ? 'success' : ($member['status'] === 'sospeso' ? 'warning' : 'secondary') ?>">
                                    <?= h($member['status']) ?>
                                </span>
                            </td>
                            <td><?= formatDate($member['registration_date']) ?></td>
                            <td class="text-end">
                                <a href="member_edit.php?id=<?= $member['id'] ?>" class="btn btn-sm btn-primary" title="Modifica">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-danger" onclick="confirmDelete(<?= $member['id'] ?>)" title="Elimina">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-3">
                <small class="text-muted">Totale: <?= count($members) ?> soci</small>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Conferma Eliminazione</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Sei sicuro di voler eliminare questo socio? L'operazione non può essere annullata.
            </div>
            <div class="modal-footer">
                <form method="POST" id="deleteForm">
                    <?= csrfField() ?>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="submit" class="btn btn-danger">Elimina</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(id) {
    const form = document.getElementById('deleteForm');
    form.action = 'members.php?delete=' + id;
    const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    modal.show();
}
</script>

<?php include __DIR__ . '/inc/footer.php'; ?>
