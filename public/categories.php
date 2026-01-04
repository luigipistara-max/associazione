<?php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/db.php';

requireAdmin();

$basePath = $config['app']['base_path'];
$pageTitle = 'Gestione Categorie';

$errors = [];

// Handle delete
if (isset($_GET['delete']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_GET['type'] ?? '';
    $id = (int)$_GET['delete'];
    $token = $_POST['csrf_token'] ?? '';
    
    if (verifyCsrfToken($token) && in_array($type, ['income', 'expense'])) {
        $table = $type === 'income' ? table('income_categories') : table('expense_categories');
        $stmt = $pdo->prepare("DELETE FROM $table WHERE id = ?");
        $stmt->execute([$id]);
        setFlashMessage('Categoria eliminata con successo');
        redirect($basePath . 'categories.php');
    }
}

// Handle add/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $token = $_POST['csrf_token'] ?? '';
    
    if (!verifyCsrfToken($token)) {
        $errors[] = 'Token di sicurezza non valido';
    } else {
        $type = $_POST['type'] ?? '';
        $name = trim($_POST['name'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $categoryId = $_POST['category_id'] ?? null;
        
        if (!in_array($type, ['income', 'expense'])) {
            $errors[] = 'Tipo categoria non valido';
        } elseif (empty($name)) {
            $errors[] = 'Il nome è obbligatorio';
        } else {
            try {
                $table = $type === 'income' ? table('income_categories') : table('expense_categories');
                
                if ($categoryId) {
                    $stmt = $pdo->prepare("UPDATE $table SET name = ?, sort_order = ?, is_active = ? WHERE id = ?");
                    $stmt->execute([$name, $sortOrder, $isActive, $categoryId]);
                    setFlashMessage('Categoria aggiornata con successo');
                } else {
                    $stmt = $pdo->prepare("INSERT INTO $table (name, sort_order, is_active) VALUES (?, ?, ?)");
                    $stmt->execute([$name, $sortOrder, $isActive]);
                    setFlashMessage('Categoria creata con successo');
                }
                redirect($basePath . 'categories.php');
            } catch (PDOException $e) {
                $errors[] = 'Errore: ' . $e->getMessage();
            }
        }
    }
}

// Get categories
$incomeCategories = getIncomeCategories(false);
$expenseCategories = getExpenseCategories(false);

include __DIR__ . '/inc/header.php';
?>

<h2><i class="bi bi-tags"></i> Gestione Categorie</h2>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo e($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row mt-4">
    <!-- Income Categories -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-arrow-down-circle"></i> Categorie Entrate</h5>
                <button type="button" class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#categoryModal" 
                        onclick="resetCategoryForm('income')">
                    <i class="bi bi-plus"></i> Nuova
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Ordine</th>
                                <th>Nome</th>
                                <th>Stato</th>
                                <th class="text-end">Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($incomeCategories as $cat): ?>
                            <tr>
                                <td><?php echo $cat['sort_order']; ?></td>
                                <td><?php echo e($cat['name']); ?></td>
                                <td>
                                    <?php if ($cat['is_active']): ?>
                                        <span class="badge bg-success">Attiva</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Disattiva</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                            onclick="editCategory('income', <?php echo htmlspecialchars(json_encode($cat)); ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                            onclick="confirmDelete('income', <?php echo $cat['id']; ?>, '<?php echo e($cat['name']); ?>')">
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
    
    <!-- Expense Categories -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-arrow-up-circle"></i> Categorie Uscite</h5>
                <button type="button" class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#categoryModal" 
                        onclick="resetCategoryForm('expense')">
                    <i class="bi bi-plus"></i> Nuova
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Ordine</th>
                                <th>Nome</th>
                                <th>Stato</th>
                                <th class="text-end">Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expenseCategories as $cat): ?>
                            <tr>
                                <td><?php echo $cat['sort_order']; ?></td>
                                <td><?php echo e($cat['name']); ?></td>
                                <td>
                                    <?php if ($cat['is_active']): ?>
                                        <span class="badge bg-success">Attiva</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Disattiva</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                            onclick="editCategory('expense', <?php echo htmlspecialchars(json_encode($cat)); ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                            onclick="confirmDelete('expense', <?php echo $cat['id']; ?>, '<?php echo e($cat['name']); ?>')">
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
                <div class="modal-header">
                    <h5 class="modal-title" id="categoryModalTitle">Nuova Categoria</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo e(generateCsrfToken()); ?>">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="type" id="categoryType">
                    <input type="hidden" name="category_id" id="categoryId">
                    
                    <div class="mb-3">
                        <label class="form-label">Nome</label>
                        <input type="text" name="name" id="categoryName" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Ordine di Visualizzazione</label>
                        <input type="number" name="sort_order" id="categorySortOrder" class="form-control" value="0" min="0">
                        <small class="text-muted">Le categorie vengono mostrate in ordine crescente</small>
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" id="categoryIsActive" checked>
                        <label class="form-check-label" for="categoryIsActive">
                            Categoria attiva
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
                Sei sicuro di voler eliminare la categoria <strong id="deleteCategoryName"></strong>?
                <p class="text-danger mt-2"><small>Attenzione: l'eliminazione potrebbe fallire se ci sono movimenti associati.</small></p>
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
function resetCategoryForm(type) {
    const typeLabels = {income: 'Entrata', expense: 'Uscita'};
    document.getElementById('categoryModalTitle').textContent = 'Nuova Categoria ' + typeLabels[type];
    document.getElementById('categoryType').value = type;
    document.getElementById('categoryId').value = '';
    document.getElementById('categoryName').value = '';
    document.getElementById('categorySortOrder').value = '0';
    document.getElementById('categoryIsActive').checked = true;
}

function editCategory(type, category) {
    const typeLabels = {income: 'Entrata', expense: 'Uscita'};
    document.getElementById('categoryModalTitle').textContent = 'Modifica Categoria ' + typeLabels[type];
    document.getElementById('categoryType').value = type;
    document.getElementById('categoryId').value = category.id;
    document.getElementById('categoryName').value = category.name;
    document.getElementById('categorySortOrder').value = category.sort_order;
    document.getElementById('categoryIsActive').checked = category.is_active == 1;
    new bootstrap.Modal(document.getElementById('categoryModal')).show();
}

function confirmDelete(type, id, name) {
    document.getElementById('deleteCategoryName').textContent = name;
    document.getElementById('deleteForm').action = '<?php echo $basePath; ?>categories.php?delete=' + id + '&type=' + type;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>

<?php include __DIR__ . '/inc/footer.php'; ?>
