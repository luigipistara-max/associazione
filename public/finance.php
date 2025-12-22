<?php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/functions.php';

requireLogin();

$pageTitle = 'Movimenti Finanziari';

// Handle delete
if (isset($_GET['delete_income'])) {
    checkCsrf();
    $id = (int)$_GET['delete_income'];
    try {
        $stmt = $pdo->prepare("DELETE FROM " . table('income') . " WHERE id = ?");
        $stmt->execute([$id]);
        setFlash('success', 'Entrata eliminata');
        redirect('finance.php?tab=income');
    } catch (PDOException $e) {
        setFlash('error', 'Errore: ' . $e->getMessage());
    }
}

if (isset($_GET['delete_expense'])) {
    checkCsrf();
    $id = (int)$_GET['delete_expense'];
    try {
        $stmt = $pdo->prepare("DELETE FROM " . table('expenses') . " WHERE id = ?");
        $stmt->execute([$id]);
        setFlash('success', 'Uscita eliminata');
        redirect('finance.php?tab=expense');
    } catch (PDOException $e) {
        setFlash('error', 'Errore: ' . $e->getMessage());
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    checkCsrf();
    
    $action = $_POST['action'];
    $type = $_POST['type'] ?? 'income';
    
    if ($action === 'create_income' || $action === 'update_income') {
        $id = isset($_POST['income_id']) ? (int)$_POST['income_id'] : null;
        $socialYearId = (int)$_POST['social_year_id'];
        $categoryId = (int)$_POST['category_id'];
        $memberId = !empty($_POST['member_id']) ? (int)$_POST['member_id'] : null;
        $amount = (float)str_replace(',', '.', $_POST['amount']);
        $paymentMethod = trim($_POST['payment_method'] ?? '');
        $receiptNumber = trim($_POST['receipt_number'] ?? '');
        $transactionDate = $_POST['transaction_date'] ?? '';
        $notes = trim($_POST['notes'] ?? '');
        
        if ($amount > 0 && $transactionDate) {
            try {
                if ($action === 'create_income') {
                    $stmt = $pdo->prepare("INSERT INTO " . table('income') . " 
                        (social_year_id, category_id, member_id, amount, payment_method, receipt_number, transaction_date, notes) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$socialYearId, $categoryId, $memberId, $amount, $paymentMethod, $receiptNumber, $transactionDate, $notes]);
                    setFlash('success', 'Entrata registrata');
                } else {
                    $stmt = $pdo->prepare("UPDATE " . table('income') . " SET 
                        social_year_id=?, category_id=?, member_id=?, amount=?, payment_method=?, receipt_number=?, transaction_date=?, notes=? 
                        WHERE id=?");
                    $stmt->execute([$socialYearId, $categoryId, $memberId, $amount, $paymentMethod, $receiptNumber, $transactionDate, $notes, $id]);
                    setFlash('success', 'Entrata aggiornata');
                }
                redirect('finance.php?tab=income');
            } catch (PDOException $e) {
                setFlash('error', 'Errore: ' . $e->getMessage());
            }
        } else {
            setFlash('error', 'Importo e data obbligatori');
        }
    } elseif ($action === 'create_expense' || $action === 'update_expense') {
        $id = isset($_POST['expense_id']) ? (int)$_POST['expense_id'] : null;
        $socialYearId = (int)$_POST['social_year_id'];
        $categoryId = (int)$_POST['category_id'];
        $amount = (float)str_replace(',', '.', $_POST['amount']);
        $paymentMethod = trim($_POST['payment_method'] ?? '');
        $receiptNumber = trim($_POST['receipt_number'] ?? '');
        $transactionDate = $_POST['transaction_date'] ?? '';
        $notes = trim($_POST['notes'] ?? '');
        
        if ($amount > 0 && $transactionDate) {
            try {
                if ($action === 'create_expense') {
                    $stmt = $pdo->prepare("INSERT INTO " . table('expenses') . " 
                        (social_year_id, category_id, amount, payment_method, receipt_number, transaction_date, notes) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$socialYearId, $categoryId, $amount, $paymentMethod, $receiptNumber, $transactionDate, $notes]);
                    setFlash('success', 'Uscita registrata');
                } else {
                    $stmt = $pdo->prepare("UPDATE " . table('expenses') . " SET 
                        social_year_id=?, category_id=?, amount=?, payment_method=?, receipt_number=?, transaction_date=?, notes=? 
                        WHERE id=?");
                    $stmt->execute([$socialYearId, $categoryId, $amount, $paymentMethod, $receiptNumber, $transactionDate, $notes, $id]);
                    setFlash('success', 'Uscita aggiornata');
                }
                redirect('finance.php?tab=expense');
            } catch (PDOException $e) {
                setFlash('error', 'Errore: ' . $e->getMessage());
            }
        } else {
            setFlash('error', 'Importo e data obbligatori');
        }
    }
}

// Get filter
$yearFilter = isset($_GET['year']) ? (int)$_GET['year'] : null;
$activeTab = $_GET['tab'] ?? 'income';

// Load data
try {
    $stmt = $pdo->query("SELECT * FROM " . table('social_years') . " ORDER BY start_date DESC");
    $socialYears = $stmt->fetchAll();
    
    $stmt = $pdo->query("SELECT * FROM " . table('income_categories') . " WHERE is_active = 1 ORDER BY sort_order, name");
    $incomeCategories = $stmt->fetchAll();
    
    $stmt = $pdo->query("SELECT * FROM " . table('expense_categories') . " WHERE is_active = 1 ORDER BY sort_order, name");
    $expenseCategories = $stmt->fetchAll();
    
    $stmt = $pdo->query("SELECT id, first_name, last_name FROM " . table('members') . " WHERE status = 'attivo' ORDER BY last_name, first_name");
    $members = $stmt->fetchAll();
    
    // Load income
    $incomeSql = "SELECT i.*, c.name as category_name, y.name as year_name, m.first_name, m.last_name 
                  FROM " . table('income') . " i
                  LEFT JOIN " . table('income_categories') . " c ON i.category_id = c.id
                  LEFT JOIN " . table('social_years') . " y ON i.social_year_id = y.id
                  LEFT JOIN " . table('members') . " m ON i.member_id = m.id";
    if ($yearFilter) {
        $incomeSql .= " WHERE i.social_year_id = " . $yearFilter;
    }
    $incomeSql .= " ORDER BY i.transaction_date DESC, i.created_at DESC";
    $incomeList = $pdo->query($incomeSql)->fetchAll();
    
    // Load expenses
    $expenseSql = "SELECT e.*, c.name as category_name, y.name as year_name 
                   FROM " . table('expenses') . " e
                   LEFT JOIN " . table('expense_categories') . " c ON e.category_id = c.id
                   LEFT JOIN " . table('social_years') . " y ON e.social_year_id = y.id";
    if ($yearFilter) {
        $expenseSql .= " WHERE e.social_year_id = " . $yearFilter;
    }
    $expenseSql .= " ORDER BY e.transaction_date DESC, e.created_at DESC";
    $expenseList = $pdo->query($expenseSql)->fetchAll();
    
} catch (PDOException $e) {
    die("Errore database: " . htmlspecialchars($e->getMessage()));
}

include __DIR__ . '/inc/header.php';
?>

<style>
.movement-form { display: none; }
.movement-form.active { display: block; }
</style>

<?php displayFlash(); ?>

<div class="row mb-3">
    <div class="col-md-6">
        <h2><i class="bi bi-cash-coin me-2"></i>Movimenti Finanziari</h2>
    </div>
    <div class="col-md-6 text-end">
        <div class="btn-group">
            <button type="button" class="btn btn-success" onclick="showIncomeForm()">
                <i class="bi bi-plus-circle me-1"></i>Nuova Entrata
            </button>
            <button type="button" class="btn btn-danger" onclick="showExpenseForm()">
                <i class="bi bi-plus-circle me-1"></i>Nuova Uscita
            </button>
        </div>
    </div>
</div>

<!-- Filter -->
<div class="card shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <input type="hidden" name="tab" value="<?= h($activeTab) ?>">
            <div class="col-md-6">
                <label class="form-label">Filtra per Anno Sociale</label>
                <select name="year" class="form-select" onchange="this.form.submit()">
                    <option value="">Tutti gli anni</option>
                    <?php foreach ($socialYears as $year): ?>
                        <option value="<?= $year['id'] ?>" <?= $yearFilter == $year['id'] ? 'selected' : '' ?>>
                            <?= h($year['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'income' ? 'active' : '' ?>" href="?tab=income<?= $yearFilter ? '&year='.$yearFilter : '' ?>">
            <i class="bi bi-arrow-up-circle me-1"></i>Entrate
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'expense' ? 'active' : '' ?>" href="?tab=expense<?= $yearFilter ? '&year='.$yearFilter : '' ?>">
            <i class="bi bi-arrow-down-circle me-1"></i>Uscite
        </a>
    </li>
</ul>

<div class="tab-content">
    <!-- Income Tab -->
    <div class="tab-pane fade <?= $activeTab === 'income' ? 'show active' : '' ?>">
        <div class="card shadow-sm">
            <div class="card-body">
                <?php if (empty($incomeList)): ?>
                    <p class="text-muted text-center py-5">Nessuna entrata registrata</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Anno Sociale</th>
                                    <th>Categoria</th>
                                    <th>Socio</th>
                                    <th>Metodo</th>
                                    <th>Ricevuta</th>
                                    <th class="text-end">Importo</th>
                                    <th>Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total = 0;
                                foreach ($incomeList as $income): 
                                    $total += $income['amount'];
                                ?>
                                <tr>
                                    <td><?= formatDate($income['transaction_date']) ?></td>
                                    <td><?= h($income['year_name']) ?></td>
                                    <td><?= h($income['category_name']) ?></td>
                                    <td><?= $income['member_id'] ? h($income['first_name'] . ' ' . $income['last_name']) : '-' ?></td>
                                    <td><?= h($income['payment_method']) ?></td>
                                    <td><?= h($income['receipt_number']) ?></td>
                                    <td class="text-end text-success"><strong><?= formatCurrency($income['amount']) ?></strong></td>
                                    <td>
                                        <button class="btn btn-sm btn-primary" onclick="editIncome(<?= htmlspecialchars(json_encode($income)) ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="deleteIncome(<?= $income['id'] ?>)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <tr class="table-success fw-bold">
                                    <td colspan="6" class="text-end">TOTALE:</td>
                                    <td class="text-end"><?= formatCurrency($total) ?></td>
                                    <td></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Expense Tab -->
    <div class="tab-pane fade <?= $activeTab === 'expense' ? 'show active' : '' ?>">
        <div class="card shadow-sm">
            <div class="card-body">
                <?php if (empty($expenseList)): ?>
                    <p class="text-muted text-center py-5">Nessuna uscita registrata</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Anno Sociale</th>
                                    <th>Categoria</th>
                                    <th>Metodo</th>
                                    <th>Ricevuta</th>
                                    <th class="text-end">Importo</th>
                                    <th>Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total = 0;
                                foreach ($expenseList as $expense): 
                                    $total += $expense['amount'];
                                ?>
                                <tr>
                                    <td><?= formatDate($expense['transaction_date']) ?></td>
                                    <td><?= h($expense['year_name']) ?></td>
                                    <td><?= h($expense['category_name']) ?></td>
                                    <td><?= h($expense['payment_method']) ?></td>
                                    <td><?= h($expense['receipt_number']) ?></td>
                                    <td class="text-end text-danger"><strong><?= formatCurrency($expense['amount']) ?></strong></td>
                                    <td>
                                        <button class="btn btn-sm btn-primary" onclick="editExpense(<?= htmlspecialchars(json_encode($expense)) ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="deleteExpense(<?= $expense['id'] ?>)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <tr class="table-danger fw-bold">
                                    <td colspan="5" class="text-end">TOTALE:</td>
                                    <td class="text-end"><?= formatCurrency($total) ?></td>
                                    <td></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Income Modal -->
<div class="modal fade" id="incomeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" id="incomeAction" value="create_income">
                <input type="hidden" name="type" value="income">
                <input type="hidden" name="income_id" id="incomeId">
                
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="incomeModalTitle">Nuova Entrata</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Anno Sociale *</label>
                            <select name="social_year_id" id="incomeSocialYear" class="form-select" required>
                                <?php foreach ($socialYears as $year): ?>
                                    <option value="<?= $year['id'] ?>" <?= $year['is_current'] ? 'selected' : '' ?>>
                                        <?= h($year['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Categoria *</label>
                            <select name="category_id" id="incomeCategory" class="form-select" required>
                                <?php foreach ($incomeCategories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= h($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Importo * (€)</label>
                            <input type="text" name="amount" id="incomeAmount" class="form-control" pattern="[0-9]+([.,][0-9]{1,2})?" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Data Operazione *</label>
                            <input type="date" name="transaction_date" id="incomeDate" class="form-control" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Socio (opzionale)</label>
                            <select name="member_id" id="incomeMember" class="form-select">
                                <option value="">-- Nessun socio --</option>
                                <?php foreach ($members as $member): ?>
                                    <option value="<?= $member['id'] ?>"><?= h($member['last_name'] . ' ' . $member['first_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Metodo Pagamento</label>
                            <input type="text" name="payment_method" id="incomePaymentMethod" class="form-control" placeholder="es: Bonifico, Contanti">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Numero Ricevuta</label>
                            <input type="text" name="receipt_number" id="incomeReceiptNumber" class="form-control">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Note</label>
                            <textarea name="notes" id="incomeNotes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="submit" class="btn btn-success">Salva</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Expense Modal -->
<div class="modal fade" id="expenseModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" id="expenseAction" value="create_expense">
                <input type="hidden" name="type" value="expense">
                <input type="hidden" name="expense_id" id="expenseId">
                
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="expenseModalTitle">Nuova Uscita</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Anno Sociale *</label>
                            <select name="social_year_id" id="expenseSocialYear" class="form-select" required>
                                <?php foreach ($socialYears as $year): ?>
                                    <option value="<?= $year['id'] ?>" <?= $year['is_current'] ? 'selected' : '' ?>>
                                        <?= h($year['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Categoria *</label>
                            <select name="category_id" id="expenseCategory" class="form-select" required>
                                <?php foreach ($expenseCategories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= h($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Importo * (€)</label>
                            <input type="text" name="amount" id="expenseAmount" class="form-control" pattern="[0-9]+([.,][0-9]{1,2})?" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Data Operazione *</label>
                            <input type="date" name="transaction_date" id="expenseDate" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Metodo Pagamento</label>
                            <input type="text" name="payment_method" id="expensePaymentMethod" class="form-control" placeholder="es: Bonifico, Contanti">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Numero Ricevuta</label>
                            <input type="text" name="receipt_number" id="expenseReceiptNumber" class="form-control">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Note</label>
                            <textarea name="notes" id="expenseNotes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="submit" class="btn btn-danger">Salva</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Form (hidden) -->
<form id="deleteForm" method="POST" style="display:none;">
    <?= csrfField() ?>
</form>

<script>
function showIncomeForm() {
    document.getElementById('incomeAction').value = 'create_income';
    document.getElementById('incomeModalTitle').textContent = 'Nuova Entrata';
    document.getElementById('incomeId').value = '';
    document.getElementById('incomeDate').value = new Date().toISOString().split('T')[0];
    document.getElementById('incomeAmount').value = '';
    document.getElementById('incomeMember').value = '';
    document.getElementById('incomePaymentMethod').value = '';
    document.getElementById('incomeReceiptNumber').value = '';
    document.getElementById('incomeNotes').value = '';
    
    const modal = new bootstrap.Modal(document.getElementById('incomeModal'));
    modal.show();
}

function editIncome(income) {
    document.getElementById('incomeAction').value = 'update_income';
    document.getElementById('incomeModalTitle').textContent = 'Modifica Entrata';
    document.getElementById('incomeId').value = income.id;
    document.getElementById('incomeSocialYear').value = income.social_year_id;
    document.getElementById('incomeCategory').value = income.category_id;
    document.getElementById('incomeAmount').value = income.amount;
    document.getElementById('incomeDate').value = income.transaction_date;
    document.getElementById('incomeMember').value = income.member_id || '';
    document.getElementById('incomePaymentMethod').value = income.payment_method || '';
    document.getElementById('incomeReceiptNumber').value = income.receipt_number || '';
    document.getElementById('incomeNotes').value = income.notes || '';
    
    const modal = new bootstrap.Modal(document.getElementById('incomeModal'));
    modal.show();
}

function deleteIncome(id) {
    if (confirm('Sei sicuro di voler eliminare questa entrata?')) {
        const form = document.getElementById('deleteForm');
        form.action = 'finance.php?delete_income=' + id;
        form.submit();
    }
}

function showExpenseForm() {
    document.getElementById('expenseAction').value = 'create_expense';
    document.getElementById('expenseModalTitle').textContent = 'Nuova Uscita';
    document.getElementById('expenseId').value = '';
    document.getElementById('expenseDate').value = new Date().toISOString().split('T')[0];
    document.getElementById('expenseAmount').value = '';
    document.getElementById('expensePaymentMethod').value = '';
    document.getElementById('expenseReceiptNumber').value = '';
    document.getElementById('expenseNotes').value = '';
    
    const modal = new bootstrap.Modal(document.getElementById('expenseModal'));
    modal.show();
}

function editExpense(expense) {
    document.getElementById('expenseAction').value = 'update_expense';
    document.getElementById('expenseModalTitle').textContent = 'Modifica Uscita';
    document.getElementById('expenseId').value = expense.id;
    document.getElementById('expenseSocialYear').value = expense.social_year_id;
    document.getElementById('expenseCategory').value = expense.category_id;
    document.getElementById('expenseAmount').value = expense.amount;
    document.getElementById('expenseDate').value = expense.transaction_date;
    document.getElementById('expensePaymentMethod').value = expense.payment_method || '';
    document.getElementById('expenseReceiptNumber').value = expense.receipt_number || '';
    document.getElementById('expenseNotes').value = expense.notes || '';
    
    const modal = new bootstrap.Modal(document.getElementById('expenseModal'));
    modal.show();
}

function deleteExpense(id) {
    if (confirm('Sei sicuro di voler eliminare questa uscita?')) {
        const form = document.getElementById('deleteForm');
        form.action = 'finance.php?delete_expense=' + id;
        form.submit();
    }
}
</script>

<?php include __DIR__ . '/inc/footer.php'; ?>
