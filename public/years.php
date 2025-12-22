<?php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/functions.php';

requireAdmin();

$pageTitle = 'Anni Sociali';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create' || $action === 'update') {
        $yearId = isset($_POST['year_id']) ? (int)$_POST['year_id'] : null;
        $name = trim($_POST['name'] ?? '');
        $startDate = $_POST['start_date'] ?? '';
        $endDate = $_POST['end_date'] ?? '';
        $isCurrent = isset($_POST['is_current']);
        
        $errors = [];
        
        if (empty($name)) $errors[] = "Nome obbligatorio";
        if (empty($startDate)) $errors[] = "Data inizio obbligatoria";
        if (empty($endDate)) $errors[] = "Data fine obbligatoria";
        if ($startDate && $endDate && $startDate > $endDate) {
            $errors[] = "Data fine deve essere successiva alla data inizio";
        }
        
        if (empty($errors)) {
            try {
                // If setting as current, unset all others
                if ($isCurrent) {
                    $pdo->exec("UPDATE " . table('social_years') . " SET is_current = FALSE");
                }
                
                if ($action === 'create') {
                    $stmt = $pdo->prepare("INSERT INTO " . table('social_years') . " (name, start_date, end_date, is_current) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$name, $startDate, $endDate, $isCurrent ? 1 : 0]);
                    setFlash('success', 'Anno sociale creato con successo');
                } else {
                    $stmt = $pdo->prepare("UPDATE " . table('social_years') . " SET name = ?, start_date = ?, end_date = ?, is_current = ? WHERE id = ?");
                    $stmt->execute([$name, $startDate, $endDate, $isCurrent ? 1 : 0, $yearId]);
                    setFlash('success', 'Anno sociale aggiornato con successo');
                }
                redirect('years.php');
            } catch (PDOException $e) {
                setFlash('error', 'Errore database: ' . $e->getMessage());
            }
        } else {
            setFlash('error', implode(', ', $errors));
        }
    } elseif ($action === 'delete') {
        $yearId = (int)$_POST['year_id'];
        
        try {
            // Check if year has movements
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM " . table('income') . " WHERE social_year_id = ?");
            $stmt->execute([$yearId]);
            $incomeCount = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM " . table('expenses') . " WHERE social_year_id = ?");
            $stmt->execute([$yearId]);
            $expenseCount = $stmt->fetchColumn();
            
            if ($incomeCount > 0 || $expenseCount > 0) {
                setFlash('error', 'Impossibile eliminare: anno sociale con movimenti associati');
            } else {
                $stmt = $pdo->prepare("DELETE FROM " . table('social_years') . " WHERE id = ?");
                $stmt->execute([$yearId]);
                setFlash('success', 'Anno sociale eliminato con successo');
            }
        } catch (PDOException $e) {
            setFlash('error', 'Errore durante l\'eliminazione: ' . $e->getMessage());
        }
        redirect('years.php');
    } elseif ($action === 'set_current') {
        $yearId = (int)$_POST['year_id'];
        
        try {
            $pdo->exec("UPDATE " . table('social_years') . " SET is_current = FALSE");
            $stmt = $pdo->prepare("UPDATE " . table('social_years') . " SET is_current = TRUE WHERE id = ?");
            $stmt->execute([$yearId]);
            setFlash('success', 'Anno corrente impostato con successo');
        } catch (PDOException $e) {
            setFlash('error', 'Errore: ' . $e->getMessage());
        }
        redirect('years.php');
    }
}

// Load social years
try {
    $stmt = $pdo->query("SELECT * FROM " . table('social_years') . " ORDER BY start_date DESC");
    $years = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Errore database: " . htmlspecialchars($e->getMessage()));
}

include __DIR__ . '/inc/header.php';
?>

<?php displayFlash(); ?>

<div class="row mb-3">
    <div class="col-md-6">
        <h2><i class="bi bi-calendar-event me-2"></i>Anni Sociali</h2>
    </div>
    <div class="col-md-6 text-end">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#yearModal" onclick="resetYearForm()">
            <i class="bi bi-plus-circle me-1"></i>Nuovo Anno Sociale
        </button>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <?php if (empty($years)): ?>
            <p class="text-muted text-center py-5">Nessun anno sociale configurato</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Data Inizio</th>
                            <th>Data Fine</th>
                            <th>Stato</th>
                            <th class="text-end">Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($years as $year): ?>
                        <tr>
                            <td>
                                <strong><?= h($year['name']) ?></strong>
                                <?php if ($year['is_current']): ?>
                                    <span class="badge bg-success ms-2">Corrente</span>
                                <?php endif; ?>
                            </td>
                            <td><?= formatDate($year['start_date']) ?></td>
                            <td><?= formatDate($year['end_date']) ?></td>
                            <td>
                                <?php
                                $today = date('Y-m-d');
                                if ($today < $year['start_date']) {
                                    echo '<span class="badge bg-info">Futuro</span>';
                                } elseif ($today > $year['end_date']) {
                                    echo '<span class="badge bg-secondary">Passato</span>';
                                } else {
                                    echo '<span class="badge bg-primary">In corso</span>';
                                }
                                ?>
                            </td>
                            <td class="text-end">
                                <?php if (!$year['is_current']): ?>
                                <form method="POST" style="display: inline;">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="set_current">
                                    <input type="hidden" name="year_id" value="<?= $year['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-success" title="Imposta come corrente">
                                        <i class="bi bi-check-circle"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                                <button type="button" class="btn btn-sm btn-primary" onclick="editYear(<?= htmlspecialchars(json_encode($year)) ?>)">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-danger" onclick="confirmDelete(<?= $year['id'] ?>, '<?= h($year['name']) ?>')">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Year Modal -->
<div class="modal fade" id="yearModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="year_id" id="yearId">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Nuovo Anno Sociale</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nome *</label>
                        <input type="text" name="name" id="name" class="form-control" placeholder="es: Anno Sociale 2024/2025" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Data Inizio *</label>
                        <input type="date" name="start_date" id="startDate" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Data Fine *</label>
                        <input type="date" name="end_date" id="endDate" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" name="is_current" id="isCurrent" class="form-check-input">
                            <label class="form-check-label" for="isCurrent">
                                Imposta come anno corrente
                            </label>
                        </div>
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

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="year_id" id="deleteYearId">
                
                <div class="modal-header">
                    <h5 class="modal-title">Conferma Eliminazione</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    Sei sicuro di voler eliminare l'anno sociale <strong id="deleteYearName"></strong>?<br>
                    <small class="text-muted">Nota: non è possibile eliminare anni con movimenti associati.</small>
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
function resetYearForm() {
    document.getElementById('formAction').value = 'create';
    document.getElementById('modalTitle').textContent = 'Nuovo Anno Sociale';
    document.getElementById('yearId').value = '';
    document.getElementById('name').value = '';
    document.getElementById('startDate').value = '';
    document.getElementById('endDate').value = '';
    document.getElementById('isCurrent').checked = false;
}

function editYear(year) {
    document.getElementById('formAction').value = 'update';
    document.getElementById('modalTitle').textContent = 'Modifica Anno Sociale';
    document.getElementById('yearId').value = year.id;
    document.getElementById('name').value = year.name;
    document.getElementById('startDate').value = year.start_date;
    document.getElementById('endDate').value = year.end_date;
    document.getElementById('isCurrent').checked = year.is_current == 1;
    
    const modal = new bootstrap.Modal(document.getElementById('yearModal'));
    modal.show();
}

function confirmDelete(id, name) {
    document.getElementById('deleteYearId').value = id;
    document.getElementById('deleteYearName').textContent = name;
    
    const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    modal.show();
}
</script>

<?php include __DIR__ . '/inc/footer.php'; ?>
