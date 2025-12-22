<?php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/functions.php';

requireAdmin();

$pageTitle = 'Gestione Categorie';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    
    $action = $_POST['action'] ?? '';
    $type = $_POST['type'] ?? 'income';
    $table = $type === 'income' ? table('income_categories') : table('expense_categories');
    
    if ($action === 'create' || $action === 'update') {
        $catId = isset($_POST['cat_id']) ? (int)$_POST['cat_id'] : null;
        $name = trim($_POST['name'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $isActive = isset($_POST['is_active']);
        
        if (empty($name)) {
            setFlash('error', 'Nome categoria obbligatorio');
        } else {
            try {
                if ($action === 'create') {
                    $stmt = $pdo->prepare("INSERT INTO $table (name, sort_order, is_active) VALUES (?, ?, ?)");
                    $stmt->execute([$name, $sortOrder, $isActive ? 1 : 0]);
                    setFlash('success', 'Categoria creata con successo');
                } else {
                    $stmt = $pdo->prepare("UPDATE $table SET name = ?, sort_order = ?, is_active = ? WHERE id = ?");
                    $stmt->execute([$name, $sortOrder, $isActive ? 1 : 0, $catId]);
                    setFlash('success', 'Categoria aggiornata con successo');
                }
            } catch (PDOException $e) {
                setFlash('error', 'Errore database: ' . $e->getMessage());
            }
        }
        redirect('categories.php?tab=' . $type);
    } elseif ($action === 'delete') {
        $catId = (int)$_POST['cat_id'];
        
        try {
            // Check if category has movements
            if ($type === 'income') {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM " . table('income') . " WHERE category_id = ?");
            } else {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM " . table('expenses') . " WHERE category_id = ?");
            }
            $stmt->execute([$catId]);
            $count = $stmt->fetchColumn();
            
            if ($count > 0) {
                setFlash('error', 'Impossibile eliminare: categoria con movimenti associati');
            } else {
                $stmt = $pdo->prepare("DELETE FROM $table WHERE id = ?");
                $stmt->execute([$catId]);
                setFlash('success', 'Categoria eliminata con successo');
            }
        } catch (PDOException $e) {
            setFlash('error', 'Errore durante l\'eliminazione: ' . $e->getMessage());
        }
        redirect('categories.php?tab=' . $type);
    }
}

// Get active tab
$activeTab = $_GET['tab'] ?? 'income';

// Load categories
try {
    $stmt = $pdo->query("SELECT * FROM " . table('income_categories') . " ORDER BY sort_order, name");
    $incomeCategories = $stmt->fetchAll();
    
    $stmt = $pdo->query("SELECT * FROM " . table('expense_categories') . " ORDER BY sort_order, name");
    $expenseCategories = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Errore database: " . htmlspecialchars($e->getMessage()));
}

include __DIR__ . '/inc/header.php';
?>

<?php displayFlash(); ?>

<div class="row mb-3">
    <div class="col-md-6">
        <h2><i class="bi bi-tag me-2"></i>Gestione Categorie</h2>
    </div>
    <div class="col-md-6 text-end">
        <button type="button" class="btn btn-primary" onclick="showNewCategory()">
            <i class="bi bi-plus-circle me-1"></i>Nuova Categoria
        </button>
    </div>
</div>

<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item" role="presentation">
        <a class="nav-link <?= $activeTab === 'income' ? 'active' : '' ?>" href="?tab=income">
            <i class="bi bi-arrow-up-circle me-1"></i>Categorie Entrate
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link <?= $activeTab === 'expense' ? 'active' : '' ?>" href="?tab=expense">
            <i class="bi bi-arrow-down-circle me-1"></i>Categorie Uscite
        </a>
    </li>
</ul>

<div class="tab-content">
    <!-- Income Categories Tab -->
    <div class="tab-pane fade <?= $activeTab === 'income' ? 'show active' : '' ?>">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Ordine</th>
                                <th>Stato</th>
                                <th class="text-end">Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($incomeCategories as $cat): ?>
                            <tr>
                                <td><?= h($cat['name']) ?></td>
                                <td><?= $cat['sort_order'] ?></td>
                                <td>
                                    <span class="badge bg-<?= $cat['is_active'] ? 'success' : 'secondary' ?>">
                                        <?= $cat['is_active'] ? 'Attiva' : 'Disattiva' ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-primary" onclick="editCategory('income', <?= htmlspecialchars(json_encode($cat)) ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-danger" onclick="confirmDelete('income', <?= $cat['id'] ?>, '<?= h($cat['name']) ?>')">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Expense Categories Tab -->
    <div class="tab-pane fade <?= $activeTab === 'expense' ? 'show active' : '' ?>">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Ordine</th>
                                <th>Stato</th>
                                <th class="text-end">Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expenseCategories as $cat): ?>
                            <tr>
                                <td><?= h($cat['name']) ?></td>
                                <td><?= $cat['sort_order'] ?></td>
                                <td>
                                    <span class="badge bg-<?= $cat['is_active'] ? 'success' : 'secondary' ?>">
                                        <?= $cat['is_active'] ? 'Attiva' : 'Disattiva' ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-primary" onclick="editCategory('expense', <?= htmlspecialchars(json_encode($cat)) ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-danger" onclick="confirmDelete('expense', <?= $cat['id'] ?>, '<?= h($cat['name']) ?>')">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Category Modal -->
<div class="modal fade" id="categoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="type" id="categoryType" value="income">
                <input type="hidden" name="cat_id" id="catId">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Nuova Categoria</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Tipo *</label>
                        <select class="form-select" id="typeSelect" onchange="updateCategoryType()">
                            <option value="income">Entrata</option>
                            <option value="expense">Uscita</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nome *</label>
                        <input type="text" name="name" id="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ordine</label>
                        <input type="number" name="sort_order" id="sortOrder" class="form-control" value="0" min="0">
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" name="is_active" id="isActive" class="form-check-input" checked>
                            <label class="form-check-label" for="isActive">
                                Categoria attiva
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
                <input type="hidden" name="type" id="deleteType">
                <input type="hidden" name="cat_id" id="deleteCatId">
                
                <div class="modal-header">
                    <h5 class="modal-title">Conferma Eliminazione</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    Sei sicuro di voler eliminare la categoria <strong id="deleteCatName"></strong>?<br>
                    <small class="text-muted">Nota: non è possibile eliminare categorie con movimenti associati.</small>
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
const currentTab = '<?= $activeTab ?>';

function showNewCategory() {
    document.getElementById('formAction').value = 'create';
    document.getElementById('modalTitle').textContent = 'Nuova Categoria';
    document.getElementById('catId').value = '';
    document.getElementById('typeSelect').value = currentTab;
    document.getElementById('categoryType').value = currentTab;
    document.getElementById('typeSelect').disabled = false;
    document.getElementById('name').value = '';
    document.getElementById('sortOrder').value = '0';
    document.getElementById('isActive').checked = true;
    
    const modal = new bootstrap.Modal(document.getElementById('categoryModal'));
    modal.show();
}

function editCategory(type, category) {
    document.getElementById('formAction').value = 'update';
    document.getElementById('modalTitle').textContent = 'Modifica Categoria';
    document.getElementById('catId').value = category.id;
    document.getElementById('typeSelect').value = type;
    document.getElementById('categoryType').value = type;
    document.getElementById('typeSelect').disabled = true;
    document.getElementById('name').value = category.name;
    document.getElementById('sortOrder').value = category.sort_order;
    document.getElementById('isActive').checked = category.is_active == 1;
    
    const modal = new bootstrap.Modal(document.getElementById('categoryModal'));
    modal.show();
}

function updateCategoryType() {
    document.getElementById('categoryType').value = document.getElementById('typeSelect').value;
}

function confirmDelete(type, id, name) {
    document.getElementById('deleteType').value = type;
    document.getElementById('deleteCatId').value = id;
    document.getElementById('deleteCatName').textContent = name;
    
    const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    modal.show();
}
</script>

<?php include __DIR__ . '/inc/footer.php'; ?>
