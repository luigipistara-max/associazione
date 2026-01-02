<?php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/db.php';

requireLogin();

$pageTitle = 'Movimenti Finanziari';

// Get base path from config
$basePath = $config['app']['base_path'];

$errors = [];

$action = $_GET['action'] ?? 'list';
$movementId = $_GET['id'] ?? null;
$movementType = $_GET['type'] ?? null;

// Handle delete
if (isset($_GET['delete']) && isset($_GET['type']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_GET['delete'];
    $type = $_GET['type'];
    $token = $_POST['csrf_token'] ?? '';
    
    if (verifyCsrfToken($token)) {
        try {
            if ($type === 'income') {
                $stmt = $pdo->prepare("DELETE FROM " . table('income') . " WHERE id = ?");
            } elseif ($type === 'expense') {
                $stmt = $pdo->prepare("DELETE FROM " . table('expenses') . " WHERE id = ?");
            } else {
                throw new Exception('Tipo movimento non valido');
            }
            $stmt->execute([$id]);
            setFlashMessage('Movimento eliminato con successo');
        } catch (Exception $e) {
            setFlashMessage('Errore nell\'eliminazione: ' . $e->getMessage(), 'danger');
        }
        redirect($basePath . 'finance.php');
    }
}

// Handle add/edit
if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    
    if (!verifyCsrfToken($token)) {
        $errors[] = 'Token di sicurezza non valido';
    } else {
        $type = $_POST['type'] ?? '';
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $amount = str_replace(',', '.', $_POST['amount'] ?? '0');
        $transactionDate = $_POST['transaction_date'] ?? '';
        $socialYearId = !empty($_POST['social_year_id']) ? (int)$_POST['social_year_id'] : null;
        $memberId = !empty($_POST['member_id']) ? (int)$_POST['member_id'] : null;
        $paymentMethod = trim($_POST['payment_method'] ?? '');
        $receiptNumber = trim($_POST['receipt_number'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $editId = $_POST['movement_id'] ?? null;
        $editType = $_POST['edit_type'] ?? null;
        
        // Validation
        if (!in_array($type, ['income', 'expense'])) {
            $errors[] = 'Tipo movimento non valido';
        }
        if (empty($categoryId)) {
            $errors[] = 'La categoria è obbligatoria';
        }
        if (empty($description)) {
            $errors[] = 'La descrizione è obbligatoria';
        }
        if ($amount <= 0) {
            $errors[] = 'L\'importo deve essere maggiore di zero';
        }
        if (empty($transactionDate)) {
            $errors[] = 'La data è obbligatoria';
        }
        
        if (empty($errors)) {
            try {
                if ($editId) {
                    // Update existing record
                    if ($type === 'income') {
                        $stmt = $pdo->prepare("
                            UPDATE " . table('income') . " SET
                                category_id = ?, amount = ?, transaction_date = ?,
                                social_year_id = ?, member_id = ?, payment_method = ?, 
                                receipt_number = ?, notes = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([
                            $categoryId, $amount, $transactionDate,
                            $socialYearId, $memberId, $paymentMethod ?: null, 
                            $receiptNumber ?: null, $notes ?: null,
                            $editId
                        ]);
                    } elseif ($type === 'expense') {
                        $stmt = $pdo->prepare("
                            UPDATE " . table('expenses') . " SET
                                category_id = ?, amount = ?, transaction_date = ?,
                                social_year_id = ?, payment_method = ?, 
                                receipt_number = ?, description = ?, notes = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([
                            $categoryId, $amount, $transactionDate,
                            $socialYearId, $paymentMethod ?: null, 
                            $receiptNumber ?: null, $description ?: null, $notes ?: null,
                            $editId
                        ]);
                    }
                    setFlashMessage('Movimento aggiornato con successo');
                } else {
                    // Insert new record
                    if ($type === 'income') {
                        $stmt = $pdo->prepare("
                            INSERT INTO " . table('income') . " (
                                category_id, member_id, amount, payment_method, 
                                receipt_number, transaction_date, social_year_id, notes
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $categoryId, $memberId, $amount, $paymentMethod ?: null,
                            $receiptNumber ?: null, $transactionDate, $socialYearId, $notes ?: null
                        ]);
                    } elseif ($type === 'expense') {
                        $stmt = $pdo->prepare("
                            INSERT INTO " . table('expenses') . " (
                                category_id, amount, payment_method, 
                                receipt_number, transaction_date, social_year_id, 
                                description, notes
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $categoryId, $amount, $paymentMethod ?: null,
                            $receiptNumber ?: null, $transactionDate, $socialYearId,
                            $description ?: null, $notes ?: null
                        ]);
                    }
                    setFlashMessage('Movimento aggiunto con successo');
                }
                redirect($basePath . 'finance.php');
            } catch (PDOException $e) {
                $errors[] = 'Errore nel salvataggio: ' . $e->getMessage();
            }
        }
    }
}

// Load movement for editing
$movement = null;
if ($action === 'edit' && $movementId && $movementType) {
    if ($movementType === 'income') {
        $stmt = $pdo->prepare("SELECT *, 'income' as type, NULL as description FROM " . table('income') . " WHERE id = ?");
    } elseif ($movementType === 'expense') {
        $stmt = $pdo->prepare("SELECT *, 'expense' as type, NULL as member_id FROM " . table('expenses') . " WHERE id = ?");
    }
    
    if (isset($stmt)) {
        $stmt->execute([$movementId]);
        $movement = $stmt->fetch();
        if (!$movement) {
            setFlashMessage('Movimento non trovato', 'danger');
            redirect($basePath . 'finance.php');
        }
    }
}

// Get filters for list
$typeFilter = $_GET['type_filter'] ?? '';
$yearFilter = $_GET['year'] ?? '';
$categoryFilter = $_GET['category'] ?? '';

// Build query for list
if ($action === 'list') {
    $params = [];
    
    // Case 1: Only expenses
    if ($typeFilter === 'expense') {
        $sql = "
            SELECT 
                e.id,
                'expense' as type,
                e.category_id,
                ec.name as category_name,
                e.amount,
                e.transaction_date,
                e.payment_method,
                e.receipt_number,
                e.notes,
                e.social_year_id,
                sy.name as year_name,
                NULL as member_id,
                NULL as first_name,
                NULL as last_name,
                e.description
            FROM " . table('expenses') . " e
            LEFT JOIN " . table('expense_categories') . " ec ON e.category_id = ec.id
            LEFT JOIN " . table('social_years') . " sy ON e.social_year_id = sy.id
            WHERE 1=1
        ";
        
        if ($yearFilter) {
            $sql .= " AND e.social_year_id = ?";
            $params[] = $yearFilter;
        }
        
        if ($categoryFilter) {
            $sql .= " AND e.category_id = ?";
            $params[] = $categoryFilter;
        }
    }
    // Case 2: Only income
    elseif ($typeFilter === 'income') {
        $sql = "
            SELECT 
                i.id,
                'income' as type,
                i.category_id,
                ic.name as category_name,
                i.amount,
                i.transaction_date,
                i.payment_method,
                i.receipt_number,
                i.notes,
                i.social_year_id,
                sy.name as year_name,
                i.member_id,
                mem.first_name,
                mem.last_name,
                NULL as description
            FROM " . table('income') . " i
            LEFT JOIN " . table('income_categories') . " ic ON i.category_id = ic.id
            LEFT JOIN " . table('social_years') . " sy ON i.social_year_id = sy.id
            LEFT JOIN " . table('members') . " mem ON i.member_id = mem.id
            WHERE 1=1
        ";
        
        if ($yearFilter) {
            $sql .= " AND i.social_year_id = ?";
            $params[] = $yearFilter;
        }
        
        if ($categoryFilter) {
            $sql .= " AND i.category_id = ?";
            $params[] = $categoryFilter;
        }
    }
    // Case 3: Both (default)
    else {
        $sql = "
            SELECT 
                i.id,
                'income' as type,
                i.category_id,
                ic.name as category_name,
                i.amount,
                i.transaction_date,
                i.payment_method,
                i.receipt_number,
                i.notes,
                i.social_year_id,
                sy.name as year_name,
                i.member_id,
                mem.first_name,
                mem.last_name,
                NULL as description
            FROM " . table('income') . " i
            LEFT JOIN " . table('income_categories') . " ic ON i.category_id = ic.id
            LEFT JOIN " . table('social_years') . " sy ON i.social_year_id = sy.id
            LEFT JOIN " . table('members') . " mem ON i.member_id = mem.id
            WHERE 1=1
        ";
        
        if ($yearFilter) {
            $sql .= " AND i.social_year_id = ?";
            $params[] = $yearFilter;
        }
        
        if ($categoryFilter) {
            $sql .= " AND i.category_id = ?";
            $params[] = $categoryFilter;
        }
        
        $sql .= " UNION ALL 
            SELECT 
                e.id,
                'expense' as type,
                e.category_id,
                ec.name as category_name,
                e.amount,
                e.transaction_date,
                e.payment_method,
                e.receipt_number,
                e.notes,
                e.social_year_id,
                sy.name as year_name,
                NULL as member_id,
                NULL as first_name,
                NULL as last_name,
                e.description
            FROM " . table('expenses') . " e
            LEFT JOIN " . table('expense_categories') . " ec ON e.category_id = ec.id
            LEFT JOIN " . table('social_years') . " sy ON e.social_year_id = sy.id
            WHERE 1=1
        ";
        
        if ($yearFilter) {
            $sql .= " AND e.social_year_id = ?";
            $params[] = $yearFilter;
        }
        
        if ($categoryFilter) {
            $sql .= " AND e.category_id = ?";
            $params[] = $categoryFilter;
        }
    }
    
    $sql .= " ORDER BY transaction_date DESC, id DESC LIMIT 100";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $movements = $stmt->fetchAll();
    
    // Calculate totals
    $totalIncome = 0;
    $totalExpense = 0;
    foreach ($movements as $mov) {
        if ($mov['type'] === 'income') {
            $totalIncome += $mov['amount'];
        } else {
            $totalExpense += $mov['amount'];
        }
    }
}

// Get data for forms
$socialYears = getSocialYears();
$incomeCategories = getIncomeCategories();
$expenseCategories = getExpenseCategories();

// Get members for dropdown
$stmt = $pdo->query("SELECT id, first_name, last_name FROM " . table('members') . " WHERE status = 'attivo' ORDER BY last_name, first_name");
$members = $stmt->fetchAll();

include __DIR__ . '/inc/header.php';
?>

<?php if ($action === 'list'): ?>
    
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><i class="bi bi-cash-coin"></i> Movimenti Finanziari</h2>
        <a href="<?php echo $basePath; ?>finance.php?action=add" class="btn btn-primary">
            <i class="bi bi-plus"></i> Nuovo Movimento
        </a>
    </div>
    
    <!-- Filters -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Tipo</label>
                    <select name="type_filter" class="form-select">
                        <option value="">Tutti</option>
                        <option value="income" <?php echo $typeFilter === 'income' ? 'selected' : ''; ?>>Entrate</option>
                        <option value="expense" <?php echo $typeFilter === 'expense' ? 'selected' : ''; ?>>Uscite</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Anno Sociale</label>
                    <select name="year" class="form-select">
                        <option value="">Tutti</option>
                        <?php foreach ($socialYears as $year): ?>
                            <option value="<?php echo $year['id']; ?>" <?php echo $yearFilter == $year['id'] ? 'selected' : ''; ?>>
                                <?php echo e($year['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="bi bi-search"></i> Filtra
                    </button>
                    <a href="<?php echo $basePath; ?>finance.php" class="btn btn-secondary">
                        <i class="bi bi-x"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Summary -->
    <div class="row mb-3">
        <div class="col-md-4">
            <div class="card border-success">
                <div class="card-body">
                    <h6 class="text-success">Totale Entrate</h6>
                    <h4><?php echo formatAmount($totalIncome); ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-danger">
                <div class="card-body">
                    <h6 class="text-danger">Totale Uscite</h6>
                    <h4><?php echo formatAmount($totalExpense); ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-<?php echo ($totalIncome - $totalExpense) >= 0 ? 'success' : 'danger'; ?>">
                <div class="card-body">
                    <h6>Saldo</h6>
                    <h4><?php echo formatAmount($totalIncome - $totalExpense); ?></h4>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Movements Table -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($movements)): ?>
                <p class="text-muted text-center">Nessun movimento trovato.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Tipo</th>
                                <th>Categoria</th>
                                <th>Descrizione</th>
                                <th>Socio</th>
                                <th>Anno</th>
                                <th class="text-end">Importo</th>
                                <th class="text-end">Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($movements as $mov): ?>
                            <tr>
                                <td><?php echo formatDate($mov['transaction_date']); ?></td>
                                <td>
                                    <?php if ($mov['type'] === 'income'): ?>
                                        <span class="badge bg-success">Entrata</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Uscita</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo e($mov['category_name']); ?></td>
                                <td><?php echo e($mov['description'] ?? '-'); ?></td>
                                <td>
                                    <?php if ($mov['member_id']): ?>
                                        <?php echo e($mov['first_name'] . ' ' . $mov['last_name']); ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?php echo e($mov['year_name'] ?? '-'); ?></td>
                                <td class="text-end">
                                    <strong class="text-<?php echo $mov['type'] === 'income' ? 'success' : 'danger'; ?>">
                                        <?php echo formatAmount($mov['amount']); ?>
                                    </strong>
                                </td>
                                <td class="text-end">
                                    <a href="<?php echo $basePath; ?>finance.php?action=edit&id=<?php echo $mov['id']; ?>&type=<?php echo $mov['type']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                            onclick="confirmDelete(<?php echo $mov['id']; ?>, '<?php echo $mov['type']; ?>')">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="text-muted mt-2">Visualizzati: <?php echo count($movements); ?> movimenti (max 100)</p>
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
                    Sei sicuro di voler eliminare questo movimento?
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
    function confirmDelete(id, type) {
        document.getElementById('deleteForm').action = '<?php echo $basePath; ?>finance.php?delete=' + id + '&type=' + type;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    }
    </script>

<?php else: // Add/Edit form ?>
    
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>
            <i class="bi bi-<?php echo $action === 'edit' ? 'pencil' : 'plus'; ?>"></i> 
            <?php echo $action === 'edit' ? 'Modifica' : 'Nuovo'; ?> Movimento
        </h2>
        <a href="<?php echo $basePath; ?>finance.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Torna alla lista
        </a>
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
    
    <form method="POST" action="<?php echo $basePath; ?>finance.php?action=save">
        <input type="hidden" name="csrf_token" value="<?php echo e(generateCsrfToken()); ?>">
        <input type="hidden" name="movement_id" value="<?php echo e($movement['id'] ?? ''); ?>">
        <input type="hidden" name="edit_type" value="<?php echo e($movement['type'] ?? ''); ?>">
        
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0">Informazioni Principali</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Tipo <span class="text-danger">*</span></label>
                        <select name="type" id="movementType" class="form-select" required onchange="updateCategories()" <?php echo $action === 'edit' ? 'disabled' : ''; ?>>
                            <option value="">Seleziona...</option>
                            <option value="income" <?php echo ($movement['type'] ?? '') === 'income' ? 'selected' : ''; ?>>Entrata</option>
                            <option value="expense" <?php echo ($movement['type'] ?? '') === 'expense' ? 'selected' : ''; ?>>Uscita</option>
                        </select>
                        <?php if ($action === 'edit'): ?>
                            <input type="hidden" name="type" value="<?php echo e($movement['type']); ?>">
                            <small class="text-muted">Il tipo di movimento non può essere modificato</small>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Categoria <span class="text-danger">*</span></label>
                        <select name="category_id" id="categoryId" class="form-select" required>
                            <option value="">Seleziona tipo prima...</option>
                        </select>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Importo <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">€</span>
                            <input type="text" name="amount" class="form-control" 
                                   value="<?php echo e($movement['amount'] ?? ''); ?>" 
                                   placeholder="0.00" required>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Data <span class="text-danger">*</span></label>
                        <input type="date" name="transaction_date" class="form-control" 
                               value="<?php echo e($movement['transaction_date'] ?? date('Y-m-d')); ?>" required>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Descrizione <span class="text-danger">*</span></label>
                    <input type="text" name="description" class="form-control" 
                           value="<?php echo e($movement['description'] ?? ''); ?>" required>
                </div>
            </div>
        </div>
        
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0">Dettagli Aggiuntivi</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Anno Sociale</label>
                        <select name="social_year_id" class="form-select">
                            <option value="">Nessuno</option>
                            <?php foreach ($socialYears as $year): ?>
                                <option value="<?php echo $year['id']; ?>" 
                                        <?php echo ($movement['social_year_id'] ?? '') == $year['id'] ? 'selected' : ''; ?>>
                                    <?php echo e($year['name']); ?>
                                    <?php if ($year['is_current']): ?>(Corrente)<?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3" id="memberField">
                        <label class="form-label">Socio (solo per entrate)</label>
                        <select name="member_id" class="form-select">
                            <option value="">Nessuno</option>
                            <?php foreach ($members as $member): ?>
                                <option value="<?php echo $member['id']; ?>" 
                                        <?php echo ($movement['member_id'] ?? '') == $member['id'] ? 'selected' : ''; ?>>
                                    <?php echo e($member['last_name'] . ' ' . $member['first_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Metodo di Pagamento</label>
                        <input type="text" name="payment_method" class="form-control" 
                               value="<?php echo e($movement['payment_method'] ?? ''); ?>" 
                               placeholder="Es: Contanti, Bonifico, Carta">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Numero Ricevuta</label>
                        <input type="text" name="receipt_number" class="form-control" 
                               value="<?php echo e($movement['receipt_number'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Note</label>
                    <textarea name="notes" class="form-control" rows="3"><?php echo e($movement['notes'] ?? ''); ?></textarea>
                </div>
            </div>
        </div>
        
        <div class="d-flex justify-content-between mb-4">
            <a href="<?php echo $basePath; ?>finance.php" class="btn btn-secondary">
                <i class="bi bi-x"></i> Annulla
            </a>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check"></i> Salva
            </button>
        </div>
    </form>
    
    <script>
    const incomeCategories = <?php echo json_encode($incomeCategories); ?>;
    const expenseCategories = <?php echo json_encode($expenseCategories); ?>;
    const currentCategoryId = <?php echo json_encode($movement['category_id'] ?? null); ?>;
    
    function updateCategories() {
        const type = document.getElementById('movementType').value;
        const categorySelect = document.getElementById('categoryId');
        const memberField = document.getElementById('memberField');
        
        categorySelect.innerHTML = '<option value="">Seleziona...</option>';
        
        const categories = type === 'income' ? incomeCategories : (type === 'expense' ? expenseCategories : []);
        
        categories.forEach(cat => {
            const option = document.createElement('option');
            option.value = cat.id;
            option.textContent = cat.name;
            if (currentCategoryId && cat.id == currentCategoryId) {
                option.selected = true;
            }
            categorySelect.appendChild(option);
        });
        
        // Show/hide member field based on type
        if (type === 'expense') {
            memberField.style.display = 'none';
        } else {
            memberField.style.display = 'block';
        }
    }
    
    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        updateCategories();
    });
    </script>

<?php endif; ?>

<?php include __DIR__ . '/inc/footer.php'; ?>
