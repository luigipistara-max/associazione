<?php
/**
 * Member Fees Management
 */

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/audit.php';

requireLogin();

$pageTitle = 'Quote Associative';
$basePath = $config['app']['base_path'];

// Get filters
$filterYear = $_GET['year'] ?? null;
$filterStatus = $_GET['status'] ?? '';
$filterMember = $_GET['member'] ?? '';

// Handle actions
$action = $_GET['action'] ?? 'list';
$feeId = $_GET['id'] ?? null;

// Delete fee
if ($action === 'delete' && $feeId && $_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    
    // Get fee data for audit log
    $stmt = $pdo->prepare("SELECT * FROM " . table('member_fees') . " WHERE id = ?");
    $stmt->execute([$feeId]);
    $oldFee = $stmt->fetch();
    
    // Delete linked income movement if fee was paid
    if ($oldFee && $oldFee['status'] === 'paid') {
        deleteIncomeFromFee($feeId);
    }
    
    $stmt = $pdo->prepare("DELETE FROM " . table('member_fees') . " WHERE id = ?");
    $stmt->execute([$feeId]);
    
    if ($oldFee) {
        logDelete('fee', $feeId, "Quota ID {$feeId}", [
            'member_id' => $oldFee['member_id'],
            'amount' => $oldFee['amount'],
            'status' => $oldFee['status']
        ]);
    }
    
    setFlashMessage('Quota eliminata con successo');
    redirect($basePath . 'member_fees.php');
}

// Mark as paid
if ($action === 'mark_paid' && $feeId && $_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    
    // Get old data for audit
    $stmt = $pdo->prepare("SELECT * FROM " . table('member_fees') . " WHERE id = ?");
    $stmt->execute([$feeId]);
    $oldFee = $stmt->fetch();
    
    if ($oldFee && $oldFee['status'] !== 'paid') {
        // Get payment method from form or default to cash
        $paymentMethod = $_POST['payment_method'] ?? 'cash';
        $paymentDetails = $_POST['payment_details'] ?? null;
        
        // Update fee status and payment method
        $stmt = $pdo->prepare("
            UPDATE " . table('member_fees') . " 
            SET status = 'paid', paid_date = CURDATE(), payment_method = ?
            WHERE id = ?
        ");
        $stmt->execute([$paymentMethod, $feeId]);
        
        // Create income movement using helper function
        $feeData = [
            'id' => $feeId,
            'member_id' => $oldFee['member_id'],
            'social_year_id' => $oldFee['social_year_id'],
            'amount' => $oldFee['amount'],
            'paid_date' => date('Y-m-d'),
            'payment_method' => $paymentMethod
        ];
        createIncomeFromFee($feeData);
        
        // Generate receipt automatically
        $receiptId = generateReceipt($feeId, $paymentMethod, $paymentDetails, $_SESSION['user_id']);
        
        // Log audit
        logUpdate('fee', $feeId, "Quota ID {$feeId}", 
            ['status' => $oldFee['status'], 'paid_date' => $oldFee['paid_date']],
            ['status' => 'paid', 'paid_date' => date('Y-m-d')]
        );
        
        if ($receiptId) {
            setFlashMessage('Quota registrata come pagata, movimento creato e ricevuta generata automaticamente!');
        } else {
            setFlashMessage('Quota registrata come pagata e movimento creato', 'warning');
        }
    } else {
        setFlashMessage('Quota già registrata come pagata', 'warning');
    }
    
    redirect($basePath . 'member_fees.php');
}

// Add/Edit fee
if (in_array($action, ['add', 'edit']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    
    $memberId = $_POST['member_id'] ?? null;
    $socialYearId = $_POST['social_year_id'] ?? null;
    $feeType = $_POST['fee_type'] ?? 'quota_associativa';
    $amount = $_POST['amount'] ?? 0;
    $dueDate = $_POST['due_date'] ?? null;
    $paidDate = $_POST['paid_date'] ?? null;
    $paymentMethod = $_POST['payment_method'] ?? null;
    $receiptNumber = $_POST['receipt_number'] ?? null;
    $status = $_POST['status'] ?? 'pending';
    $notes = $_POST['notes'] ?? '';
    
    // If status is paid but no paid_date, use current date
    if ($status === 'paid' && !$paidDate) {
        $paidDate = date('Y-m-d');
    }
    
    if ($action === 'edit' && $feeId) {
        // Get old data for audit
        $stmt = $pdo->prepare("SELECT * FROM " . table('member_fees') . " WHERE id = ?");
        $stmt->execute([$feeId]);
        $oldFee = $stmt->fetch();
        
        $stmt = $pdo->prepare("
            UPDATE " . table('member_fees') . " 
            SET member_id = ?, social_year_id = ?, fee_type = ?, amount = ?, 
                due_date = ?, paid_date = ?, payment_method = ?, receipt_number = ?, 
                status = ?, notes = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $memberId, $socialYearId, $feeType, $amount, 
            $dueDate, $paidDate ?: null, $paymentMethod ?: null, $receiptNumber ?: null, 
            $status, $notes ?: null, $feeId
        ]);
        
        // Handle status change from non-paid to paid
        if ($oldFee && $oldFee['status'] !== 'paid' && $status === 'paid') {
            // Create income movement using helper function
            $feeData = [
                'id' => $feeId,
                'member_id' => $memberId,
                'social_year_id' => $socialYearId,
                'amount' => $amount,
                'paid_date' => $paidDate,
                'payment_method' => $paymentMethod
            ];
            createIncomeFromFee($feeData);
        }
        
        // Handle status change from paid to non-paid
        if ($oldFee && $oldFee['status'] === 'paid' && $status !== 'paid') {
            // Delete income movement
            deleteIncomeFromFee($feeId);
        }
        
        if ($oldFee) {
            logUpdate('fee', $feeId, "Quota ID {$feeId}",
                ['amount' => $oldFee['amount'], 'status' => $oldFee['status']],
                ['amount' => $amount, 'status' => $status]
            );
        }
        
        setFlashMessage('Quota aggiornata con successo');
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO " . table('member_fees') . " 
            (member_id, social_year_id, fee_type, amount, due_date, paid_date, payment_method, receipt_number, status, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $memberId, $socialYearId, $feeType, $amount, 
            $dueDate, $paidDate ?: null, $paymentMethod ?: null, $receiptNumber ?: null, 
            $status, $notes ?: null
        ]);
        
        $newFeeId = $pdo->lastInsertId();
        
        // If created as paid, create income movement
        if ($status === 'paid') {
            // Create income movement using helper function
            $feeData = [
                'id' => $newFeeId,
                'member_id' => $memberId,
                'social_year_id' => $socialYearId,
                'amount' => $amount,
                'paid_date' => $paidDate,
                'payment_method' => $paymentMethod
            ];
            createIncomeFromFee($feeData);
        }
        
        logCreate('fee', $newFeeId, "Quota ID {$newFeeId}", [
            'member_id' => $memberId,
            'amount' => $amount,
            'status' => $status
        ]);
        
        setFlashMessage('Quota aggiunta con successo');
    }
    
    redirect($basePath . 'member_fees.php');
}

// Load fee for editing
$fee = null;
if ($action === 'edit' && $feeId) {
    $stmt = $pdo->prepare("SELECT * FROM " . table('member_fees') . " WHERE id = ?");
    $stmt->execute([$feeId]);
    $fee = $stmt->fetch();
}

// Update overdue statuses
updateOverdueStatuses();

// Build query for listing fees
$sql = "SELECT mf.*, m.first_name, m.last_name, m.membership_number, sy.name as year_name
        FROM " . table('member_fees') . " mf
        JOIN " . table('members') . " m ON mf.member_id = m.id
        LEFT JOIN " . table('social_years') . " sy ON mf.social_year_id = sy.id
        WHERE 1=1";

$params = [];

if ($filterYear) {
    $sql .= " AND mf.social_year_id = ?";
    $params[] = $filterYear;
}

if ($filterStatus) {
    $sql .= " AND mf.status = ?";
    $params[] = $filterStatus;
}

if ($filterMember) {
    $sql .= " AND (m.first_name LIKE ? OR m.last_name LIKE ? OR m.membership_number LIKE ?)";
    $searchTerm = '%' . escapeLike($filterMember) . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$sql .= " ORDER BY mf.due_date DESC, m.last_name, m.first_name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$fees = $stmt->fetchAll();

// Get social years for dropdown
$socialYears = getSocialYears();

// Get members for dropdown
$stmt = $pdo->query("SELECT id, first_name, last_name, membership_number FROM " . table('members') . " WHERE status = 'attivo' ORDER BY last_name, first_name");
$members = $stmt->fetchAll();

include __DIR__ . '/inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="bi bi-credit-card"></i> <?php echo h($pageTitle); ?></h2>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#feeModal" onclick="resetFeeForm()">
        <i class="bi bi-plus"></i> Nuova Quota
    </button>
</div>

<!-- Filters -->
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Anno Sociale</label>
                <select name="year" class="form-select">
                    <option value="">Tutti</option>
                    <?php foreach ($socialYears as $year): ?>
                        <option value="<?php echo $year['id']; ?>" <?php echo $filterYear == $year['id'] ? 'selected' : ''; ?>>
                            <?php echo h($year['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Stato</label>
                <select name="status" class="form-select">
                    <option value="">Tutti</option>
                    <option value="pending" <?php echo $filterStatus === 'pending' ? 'selected' : ''; ?>>In Attesa</option>
                    <option value="paid" <?php echo $filterStatus === 'paid' ? 'selected' : ''; ?>>Pagato</option>
                    <option value="overdue" <?php echo $filterStatus === 'overdue' ? 'selected' : ''; ?>>Scaduto</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Cerca Socio</label>
                <input type="text" name="member" class="form-control" value="<?php echo h($filterMember); ?>" 
                       placeholder="Nome, cognome o numero tessera">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search"></i> Filtra
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Fees Table -->
<div class="card">
    <div class="card-body">
        <?php if (empty($fees)): ?>
            <p class="text-muted text-center py-4">Nessuna quota trovata.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Socio</th>
                            <th>Anno</th>
                            <th>Tipo</th>
                            <th>Importo</th>
                            <th>Scadenza</th>
                            <th>Pagamento</th>
                            <th>Stato</th>
                            <th>Ricevuta</th>
                            <th>Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fees as $f): ?>
                        <tr>
                            <td>
                                <strong><?php echo h($f['first_name'] . ' ' . $f['last_name']); ?></strong><br>
                                <small class="text-muted"><?php echo h($f['membership_number'] ?? 'N/A'); ?></small>
                            </td>
                            <td><?php echo h($f['year_name']); ?></td>
                            <td><?php echo h($f['fee_type']); ?></td>
                            <td><?php echo formatAmount($f['amount']); ?></td>
                            <td><?php echo formatDate($f['due_date']); ?></td>
                            <td>
                                <?php if ($f['paid_date']): ?>
                                    <?php echo formatDate($f['paid_date']); ?><br>
                                    <small class="text-muted"><?php echo h($f['payment_method'] ?? ''); ?></small>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $statusClass = [
                                    'paid' => 'success',
                                    'pending' => 'warning',
                                    'overdue' => 'danger'
                                ];
                                $statusLabel = [
                                    'paid' => 'Pagato',
                                    'pending' => 'In Attesa',
                                    'overdue' => 'Scaduto'
                                ];
                                ?>
                                <span class="badge bg-<?php echo $statusClass[$f['status']] ?? 'secondary'; ?>">
                                    <?php echo $statusLabel[$f['status']] ?? $f['status']; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($f['status'] === 'paid'): ?>
                                    <?php if ($f['receipt_number']): ?>
                                        <small class="text-muted"><?php echo h($f['receipt_number']); ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <?php if ($f['status'] === 'paid'): ?>
                                    <a href="<?php echo h($config['app']['base_path']); ?>receipt.php?fee_id=<?php echo $f['id']; ?>&format=html" 
                                       class="btn btn-info" title="Stampa Ricevuta" target="_blank">
                                        <i class="bi bi-printer"></i>
                                    </a>
                                    <?php else: ?>
                                    <button type="button" class="btn btn-success" 
                                            onclick="openPaymentModal(<?php echo $f['id']; ?>)" 
                                            title="Segna come pagato">
                                        <i class="bi bi-check"></i>
                                    </button>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-primary" onclick="editFee(<?php echo htmlspecialchars(json_encode($f)); ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" action="?action=delete&id=<?php echo $f['id']; ?>" class="d-inline" 
                                          onsubmit="return confirm('Eliminare questa quota?')">
                                        <?php echo csrfField(); ?>
                                        <button type="submit" class="btn btn-danger">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="feeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="feeForm">
                <?php echo csrfField(); ?>
                <input type="hidden" name="fee_id" id="fee_id">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="feeModalTitle">Nuova Quota</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Socio <span class="text-danger">*</span></label>
                            <select name="member_id" id="member_id" class="form-select" required>
                                <option value="">Seleziona socio...</option>
                                <?php foreach ($members as $m): ?>
                                    <option value="<?php echo $m['id']; ?>">
                                        <?php echo h($m['last_name'] . ' ' . $m['first_name'] . ' - ' . ($m['membership_number'] ?? 'N/A')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Anno Sociale <span class="text-danger">*</span></label>
                            <select name="social_year_id" id="social_year_id" class="form-select" required>
                                <option value="">Seleziona anno...</option>
                                <?php foreach ($socialYears as $year): ?>
                                    <option value="<?php echo $year['id']; ?>" 
                                            data-fee-amount="<?php echo $year['fee_amount'] ?? 0; ?>"
                                            <?php echo $year['is_current'] ? 'selected' : ''; ?>>
                                        <?php echo h($year['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tipo Quota</label>
                            <input type="text" name="fee_type" id="fee_type" class="form-control" value="quota_associativa">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Importo <span class="text-danger">*</span></label>
                            <input type="number" name="amount" id="amount" class="form-control" step="0.01" min="0" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Data Scadenza <span class="text-danger">*</span></label>
                            <input type="date" name="due_date" id="due_date" class="form-control" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Stato</label>
                            <select name="status" id="status" class="form-select">
                                <option value="pending">In Attesa</option>
                                <option value="paid">Pagato</option>
                                <option value="overdue">Scaduto</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Data Pagamento</label>
                            <input type="date" name="paid_date" id="paid_date" class="form-control">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Metodo Pagamento</label>
                            <input type="text" name="payment_method" id="payment_method" class="form-control" placeholder="Es: Bonifico">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Numero Ricevuta</label>
                            <input type="text" name="receipt_number" id="receipt_number" class="form-control">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Note</label>
                        <textarea name="notes" id="notes" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check"></i> Salva
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Payment Method Modal -->
<div class="modal fade" id="paymentMethodModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="markPaidForm">
                <?php echo csrfField(); ?>
                <input type="hidden" name="fee_id_paid" id="fee_id_paid">
                
                <div class="modal-header">
                    <h5 class="modal-title">Segna come Pagato</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Metodo di Pagamento <span class="text-danger">*</span></label>
                        <select name="payment_method" id="payment_method_select" class="form-select" required>
                            <option value="cash">In contanti presso la sede sociale</option>
                            <option value="bank_transfer">Bonifico bancario</option>
                            <option value="card">Pagamento con carta</option>
                            <option value="paypal">PayPal</option>
                            <option value="other">Altro</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Dettagli Pagamento (opzionale)</label>
                        <input type="text" name="payment_details" id="payment_details" class="form-control" 
                               placeholder="Es: Numero transazione, riferimento bonifico, ecc.">
                        <small class="text-muted">Se lasciato vuoto, verrà utilizzato il testo di default per il metodo selezionato</small>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> 
                        La ricevuta verrà generata automaticamente in formato ANNO/NNNN
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check"></i> Conferma Pagamento
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openPaymentModal(feeId) {
    document.getElementById('markPaidForm').action = '?action=mark_paid&id=' + feeId;
    document.getElementById('fee_id_paid').value = feeId;
    var modal = new bootstrap.Modal(document.getElementById('paymentMethodModal'));
    modal.show();
}

function resetFeeForm() {
    document.getElementById('feeForm').action = '?action=add';
    document.getElementById('feeModalTitle').textContent = 'Nuova Quota';
    document.getElementById('feeForm').reset();
    document.getElementById('fee_id').value = '';
}

function editFee(fee) {
    document.getElementById('feeForm').action = '?action=edit&id=' + fee.id;
    document.getElementById('feeModalTitle').textContent = 'Modifica Quota';
    document.getElementById('fee_id').value = fee.id;
    document.getElementById('member_id').value = fee.member_id;
    document.getElementById('social_year_id').value = fee.social_year_id;
    document.getElementById('fee_type').value = fee.fee_type;
    document.getElementById('amount').value = fee.amount;
    document.getElementById('due_date').value = fee.due_date;
    document.getElementById('paid_date').value = fee.paid_date || '';
    document.getElementById('payment_method').value = fee.payment_method || '';
    document.getElementById('receipt_number').value = fee.receipt_number || '';
    document.getElementById('status').value = fee.status;
    document.getElementById('notes').value = fee.notes || '';
    
    var modal = new bootstrap.Modal(document.getElementById('feeModal'));
    modal.show();
}

// Add event listener to social year select to pre-populate amount
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('social_year_id').addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const feeAmount = selectedOption.getAttribute('data-fee-amount');
        if (feeAmount && feeAmount > 0) {
            document.getElementById('amount').value = feeAmount;
        }
    });
});
</script>

<?php include __DIR__ . '/inc/footer.php'; ?>
