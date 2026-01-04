<?php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/audit.php';

requireLogin();

$config = require __DIR__ . '/../src/config.php';
$pageTitle = 'Gestione Soci';

// Get base path from config
$basePath = $config['app']['base_path'];


// Handle delete
if (isset($_GET['delete']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_GET['delete'];
    $token = $_POST['csrf_token'] ?? '';
    
    if (verifyCsrfToken($token)) {
        try {
            // Get member data for audit
            $stmt = $pdo->prepare("SELECT * FROM " . table('members') . " WHERE id = ?");
            $stmt->execute([$id]);
            $oldMember = $stmt->fetch();
            
            $stmt = $pdo->prepare("DELETE FROM " . table('members') . " WHERE id = ?");
            $stmt->execute([$id]);
            
            if ($oldMember) {
                logDelete('member', $id, "{$oldMember['first_name']} {$oldMember['last_name']}", [
                    'fiscal_code' => $oldMember['fiscal_code'],
                    'status' => $oldMember['status']
                ]);
            }
            
            setFlashMessage('Socio eliminato con successo');
        } catch (PDOException $e) {
            setFlashMessage('Errore nell\'eliminazione del socio: ' . $e->getMessage(), 'danger');
        }
        redirect($basePath . 'members.php');
    }
}

// Get filters
$statusFilter = $_GET['status'] ?? '';
$feeStatusFilter = $_GET['fee_status'] ?? '';
$searchQuery = $_GET['search'] ?? '';

// Get current year for fee filtering
$currentYear = getCurrentSocialYear();

// Build query
$sql = "SELECT m.* FROM " . table('members') . " m WHERE 1=1";
$params = [];

if ($statusFilter) {
    $sql .= " AND m.status = ?";
    $params[] = $statusFilter;
}

if ($searchQuery) {
    $sql .= " AND (m.first_name LIKE ? OR m.last_name LIKE ? OR m.fiscal_code LIKE ? OR m.email LIKE ?)";
    $search = "%" . escapeLike($searchQuery) . "%";
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
}

// Fee status filter
if ($feeStatusFilter && $currentYear) {
    if ($feeStatusFilter === 'in_regola') {
        $sql .= " AND EXISTS (
            SELECT 1 FROM " . table('member_fees') . " mf 
            WHERE mf.member_id = m.id 
            AND mf.social_year_id = ? 
            AND mf.status = 'paid'
        )";
        $params[] = $currentYear['id'];
    } elseif ($feeStatusFilter === 'morosi') {
        $sql .= " AND EXISTS (
            SELECT 1 FROM " . table('member_fees') . " mf 
            WHERE mf.member_id = m.id 
            AND mf.social_year_id = ? 
            AND mf.status IN ('pending', 'overdue')
        )";
        $params[] = $currentYear['id'];
    }
}

$sql .= " ORDER BY m.last_name, m.first_name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$members = $stmt->fetchAll();

include __DIR__ . '/inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="bi bi-people"></i> Gestione Soci</h2>
    <div>
        <a href="<?php echo $basePath; ?>export_active_members.php" class="btn btn-success me-2">
            <i class="bi bi-download"></i> Esporta Soci Attivi
        </a>
        <a href="<?php echo $basePath; ?>member_edit.php" class="btn btn-primary">
            <i class="bi bi-plus"></i> Nuovo Socio
        </a>
    </div>
</div>

<!-- Filters -->
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Cerca</label>
                <input type="text" name="search" class="form-control" placeholder="Nome, cognome, CF, email..." value="<?php echo e($searchQuery); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Stato Socio</label>
                <select name="status" class="form-select">
                    <option value="">Tutti</option>
                    <option value="attivo" <?php echo $statusFilter === 'attivo' ? 'selected' : ''; ?>>Attivo</option>
                    <option value="sospeso" <?php echo $statusFilter === 'sospeso' ? 'selected' : ''; ?>>Sospeso</option>
                    <option value="cessato" <?php echo $statusFilter === 'cessato' ? 'selected' : ''; ?>>Cessato</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Stato Quote</label>
                <select name="fee_status" class="form-select">
                    <option value="">Tutti</option>
                    <option value="in_regola" <?php echo $feeStatusFilter === 'in_regola' ? 'selected' : ''; ?>>In Regola</option>
                    <option value="morosi" <?php echo $feeStatusFilter === 'morosi' ? 'selected' : ''; ?>>Morosi</option>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="bi bi-search"></i> Cerca
                </button>
                <a href="<?php echo $basePath; ?>members.php" class="btn btn-secondary">
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
                            <td><?php echo e($member['membership_number'] ?? '-'); ?></td>
                            <td>
                                <strong><?php echo e($member['first_name'] . ' ' . $member['last_name']); ?></strong>
                            </td>
                            <td><code><?php echo e($member['fiscal_code']); ?></code></td>
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
                                <a href="<?php echo $basePath; ?>member_card.php?member_id=<?php echo $member['id']; ?>" 
                                   class="btn btn-sm btn-outline-info" title="Tessera">
                                    <i class="bi bi-credit-card"></i>
                                </a>
                                <a href="<?php echo $basePath; ?>member_edit.php?id=<?php echo $member['id']; ?>" class="btn btn-sm btn-outline-primary">
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
    document.getElementById('deleteForm').action = '<?php echo $basePath; ?>members.php?delete=' + id;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>

<?php include __DIR__ . '/inc/footer.php'; ?>
