<?php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/db.php';

requireLogin();

$config = require __DIR__ . '/../src/config.php';
$basePath = $config['app']['base_path'];
$pageTitle = 'Anni Sociali';

$errors = [];

// Only admin can create/modify
$canEdit = isAdmin();

// Handle delete
if ($canEdit && isset($_GET['delete']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_GET['delete'];
    $token = $_POST['csrf_token'] ?? '';
    
    if (verifyCsrfToken($token)) {
        try {
            $stmt = $pdo->prepare("DELETE FROM " . table('social_years') . " WHERE id = ?");
            $stmt->execute([$id]);
            setFlashMessage('Anno sociale eliminato con successo');
        } catch (PDOException $e) {
            setFlashMessage('Errore: ' . $e->getMessage(), 'danger');
        }
        redirect($basePath . 'years.php');
    }
}

// Handle set current
if ($canEdit && isset($_GET['set_current'])) {
    $id = (int)$_GET['set_current'];
    $token = $_GET['csrf_token'] ?? '';
    
    if (verifyCsrfToken($token)) {
        $pdo->exec("UPDATE " . table('social_years') . " SET is_current = 0");
        $stmt = $pdo->prepare("UPDATE " . table('social_years') . " SET is_current = 1 WHERE id = ?");
        $stmt->execute([$id]);
        setFlashMessage('Anno sociale corrente aggiornato');
        redirect($basePath . 'years.php');
    }
}

// Handle add/edit
if ($canEdit && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $token = $_POST['csrf_token'] ?? '';
    
    if (!verifyCsrfToken($token)) {
        $errors[] = 'Token di sicurezza non valido';
    } else {
        $name = trim($_POST['name'] ?? '');
        $startDate = $_POST['start_date'] ?? '';
        $endDate = $_POST['end_date'] ?? '';
        $feeAmount = floatval($_POST['fee_amount'] ?? 0);
        $isCurrent = isset($_POST['is_current']) ? 1 : 0;
        $yearId = $_POST['year_id'] ?? null;
        
        if (empty($name) || empty($startDate) || empty($endDate)) {
            $errors[] = 'Tutti i campi sono obbligatori';
        } elseif ($startDate >= $endDate) {
            $errors[] = 'La data di fine deve essere successiva alla data di inizio';
        } else {
            try {
                if ($isCurrent) {
                    $pdo->exec("UPDATE " . table('social_years') . " SET is_current = 0");
                }
                
                if ($yearId) {
                    $stmt = $pdo->prepare("UPDATE " . table('social_years') . " SET name = ?, start_date = ?, end_date = ?, fee_amount = ?, is_current = ? WHERE id = ?");
                    $stmt->execute([$name, $startDate, $endDate, $feeAmount, $isCurrent, $yearId]);
                    setFlashMessage('Anno sociale aggiornato con successo');
                } else {
                    $stmt = $pdo->prepare("INSERT INTO " . table('social_years') . " (name, start_date, end_date, fee_amount, is_current) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $startDate, $endDate, $feeAmount, $isCurrent]);
                    setFlashMessage('Anno sociale creato con successo');
                }
                redirect($basePath . 'years.php');
            } catch (PDOException $e) {
                $errors[] = 'Errore: ' . $e->getMessage();
            }
        }
    }
}

// Get all years
$stmt = $pdo->query("SELECT * FROM " . table('social_years') . " ORDER BY start_date DESC");
$years = $stmt->fetchAll();

include __DIR__ . '/inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="bi bi-calendar-range"></i> Anni Sociali</h2>
    <?php if ($canEdit): ?>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#yearModal" onclick="resetYearForm()">
        <i class="bi bi-plus"></i> Nuovo Anno
    </button>
    <?php endif; ?>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo e($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <?php if (empty($years)): ?>
            <p class="text-muted text-center">Nessun anno sociale configurato.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Data Inizio</th>
                            <th>Data Fine</th>
                            <th>Quota</th>
                            <th>Stato</th>
                            <th class="text-end">Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($years as $year): ?>
                        <tr>
                            <td>
                                <strong><?php echo e($year['name']); ?></strong>
                            </td>
                            <td><?php echo formatDate($year['start_date']); ?></td>
                            <td><?php echo formatDate($year['end_date']); ?></td>
                            <td><?php echo $year['fee_amount'] ? formatAmount($year['fee_amount']) : '-'; ?></td>
                            <td>
                                <?php if ($year['is_current']): ?>
                                    <span class="badge bg-success">Corrente</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if ($canEdit): ?>
                                    <?php if (!$year['is_current']): ?>
                                    <a href="<?php echo h($basePath); ?>years.php?set_current=<?php echo $year['id']; ?>&csrf_token=<?php echo urlencode(generateCsrfToken()); ?>" 
                                       class="btn btn-sm btn-outline-success" title="Imposta come corrente">
                                        <i class="bi bi-check-circle"></i>
                                    </a>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                            onclick="editYear(<?php echo htmlspecialchars(json_encode($year)); ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                            onclick="confirmDelete(<?php echo $year['id']; ?>, '<?php echo e($year['name']); ?>')">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($canEdit): ?>
<!-- Year Modal -->
<div class="modal fade" id="yearModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="yearModalTitle">Nuovo Anno Sociale</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo e(generateCsrfToken()); ?>">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="year_id" id="yearId">
                    
                    <div class="mb-3">
                        <label class="form-label">Nome</label>
                        <input type="text" name="name" id="name" class="form-control" placeholder="Es: 2024/2025" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Data Inizio</label>
                        <input type="date" name="start_date" id="startDate" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Data Fine</label>
                        <input type="date" name="end_date" id="endDate" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Importo Quota Associativa (€)</label>
                        <input type="number" name="fee_amount" id="feeAmount" class="form-control" 
                               step="0.01" min="0" placeholder="Es: 30.00">
                        <div class="form-text">Importo standard della quota per questo anno sociale</div>
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_current" id="isCurrent">
                        <label class="form-check-label" for="isCurrent">
                            Imposta come anno corrente
                        </label>
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
            <div class="modal-header">
                <h5 class="modal-title">Conferma Eliminazione</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Sei sicuro di voler eliminare l'anno sociale <strong id="deleteYearName"></strong>?
                <p class="text-warning mt-2"><small>I movimenti associati a questo anno rimarranno ma perderanno il collegamento.</small></p>
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
function resetYearForm() {
    document.getElementById('yearModalTitle').textContent = 'Nuovo Anno Sociale';
    document.getElementById('yearId').value = '';
    document.getElementById('name').value = '';
    document.getElementById('startDate').value = '';
    document.getElementById('endDate').value = '';
    document.getElementById('feeAmount').value = '';
    document.getElementById('isCurrent').checked = false;
}

function editYear(year) {
    document.getElementById('yearModalTitle').textContent = 'Modifica Anno Sociale';
    document.getElementById('yearId').value = year.id;
    document.getElementById('name').value = year.name;
    document.getElementById('startDate').value = year.start_date;
    document.getElementById('endDate').value = year.end_date;
    document.getElementById('feeAmount').value = year.fee_amount || '';
    document.getElementById('isCurrent').checked = year.is_current == 1;
    new bootstrap.Modal(document.getElementById('yearModal')).show();
}

function confirmDelete(id, name) {
    document.getElementById('deleteYearName').textContent = name;
    document.getElementById('deleteForm').action = '<?php echo $basePath; ?>years.php?delete=' + id;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>
<?php endif; ?>

<?php include __DIR__ . '/inc/footer.php'; ?>
