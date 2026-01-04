<?php
/**
 * Portal - Payments Page
 * Display fees and allow members to pay online or offline
 */

require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/functions.php';
require_once __DIR__ . '/inc/auth.php';

session_start();
$member = requirePortalLogin();
$basePath = $config['app']['base_path'];

$success = '';
$error = '';

// Handle offline payment request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'request_offline_payment' && isset($_POST['fee_id'])) {
        $feeId = (int)$_POST['fee_id'];
        $reference = trim($_POST['payment_reference'] ?? '');
        
        // Verify this fee belongs to the current member
        $stmt = $pdo->prepare("SELECT * FROM " . table('member_fees') . " WHERE id = ? AND member_id = ?");
        $stmt->execute([$feeId, $member['id']]);
        $fee = $stmt->fetch();
        
        if ($fee && $fee['status'] === 'pending') {
            // Update fee to payment_pending
            $stmt = $pdo->prepare("
                UPDATE " . table('member_fees') . " 
                SET payment_pending = 1, 
                    payment_reference = ?,
                    payment_method = 'Bonifico'
                WHERE id = ?
            ");
            
            if ($stmt->execute([$reference, $feeId])) {
                $success = 'Pagamento registrato! Un amministratore verificherà il bonifico e confermerà il pagamento.';
            } else {
                $error = 'Errore nel registrare il pagamento.';
            }
        } else {
            $error = 'Quota non valida o già pagata.';
        }
    }
}

// Get member's fees
$stmt = $pdo->prepare("
    SELECT f.*, sy.name as year_name, sy.start_date, sy.end_date
    FROM " . table('member_fees') . " f
    LEFT JOIN " . table('social_years') . " sy ON f.social_year_id = sy.id
    WHERE f.member_id = ?
    ORDER BY 
        CASE f.status 
            WHEN 'pending' THEN 1 
            WHEN 'overdue' THEN 2 
            WHEN 'paid' THEN 3 
        END,
        f.due_date DESC
");
$stmt->execute([$member['id']]);
$allFees = $stmt->fetchAll();

// Separate fees by status
$pendingFees = array_filter($allFees, fn($f) => in_array($f['status'], ['pending', 'overdue']) && !$f['payment_pending']);
$waitingFees = array_filter($allFees, fn($f) => $f['payment_pending']);
$paidFees = array_filter($allFees, fn($f) => $f['status'] === 'paid');

// Get payment settings
$bankName = getSetting('bank_name', '');
$bankIban = getSetting('bank_iban', '');
$paypalEnabled = getSetting('paypal_enabled', '0') === '1';
$paypalClientId = getSetting('paypal_client_id', '');

$pageTitle = 'Pagamenti';
require_once __DIR__ . '/inc/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-credit-card"></i> Pagamenti</h2>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle"></i> <?php echo h($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-circle"></i> <?php echo h($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Pending Payments -->
        <?php if (!empty($pendingFees)): ?>
            <div class="mb-5">
                <h4 class="mb-3">Quote da pagare</h4>
                
                <?php foreach ($pendingFees as $fee): ?>
                    <?php
                    $isOverdue = $fee['status'] === 'overdue';
                    $dueDate = formatDate($fee['due_date']);
                    ?>
                    
                    <div class="card mb-3 <?php echo $isOverdue ? 'border-danger' : ''; ?>">
                        <div class="card-header <?php echo $isOverdue ? 'bg-danger text-white' : 'bg-light'; ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0">
                                        Quota associativa - <?php echo h($fee['year_name']); ?>
                                        <?php if ($isOverdue): ?>
                                            <span class="badge bg-white text-danger ms-2">SCADUTA</span>
                                        <?php endif; ?>
                                    </h6>
                                </div>
                                <div>
                                    <strong><?php echo formatAmount($fee['amount']); ?></strong>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <p class="mb-2">
                                <strong>Scadenza:</strong> <?php echo h($dueDate); ?>
                            </p>
                            
                            <hr>
                            
                            <h6>Scegli il metodo di pagamento:</h6>
                            
                            <div class="row mt-3">
                                <!-- Offline Payment (Bank Transfer) -->
                                <div class="col-md-6 mb-3">
                                    <div class="card h-100">
                                        <div class="card-body">
                                            <h6><i class="bi bi-bank"></i> Bonifico Bancario</h6>
                                            
                                            <?php if ($bankIban): ?>
                                                <p class="small mb-2">
                                                    <strong>Intestatario:</strong> <?php echo h($bankName ?: getSetting('association_name', 'Associazione')); ?><br>
                                                    <strong>IBAN:</strong> <code><?php echo h($bankIban); ?></code>
                                                </p>
                                                <p class="small text-muted mb-3">
                                                    <strong>Causale:</strong> Quota associativa <?php echo h($fee['year_name']); ?> - <?php echo h($member['first_name'] . ' ' . $member['last_name']); ?>
                                                </p>
                                                
                                                <button type="button" class="btn btn-primary btn-sm" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#bankModal<?php echo $fee['id']; ?>">
                                                    <i class="bi bi-cash"></i> Ho effettuato il bonifico
                                                </button>
                                            <?php else: ?>
                                                <p class="text-muted small">
                                                    Coordinate bancarie non configurate. Contatta l'associazione.
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Online Payment (PayPal) -->
                                <?php if ($paypalEnabled && $paypalClientId): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="card h-100">
                                            <div class="card-body">
                                                <h6><i class="bi bi-paypal"></i> PayPal</h6>
                                                <p class="small text-muted mb-3">
                                                    Paga in sicurezza con carta di credito o PayPal
                                                </p>
                                                
                                                <div id="paypal-button-container-<?php echo $fee['id']; ?>"></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Bank Transfer Modal -->
                    <div class="modal fade" id="bankModal<?php echo $fee['id']; ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form method="POST">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Conferma bonifico</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p>Confermi di aver effettuato il bonifico di <strong><?php echo formatAmount($fee['amount']); ?></strong>?</p>
                                        
                                        <div class="mb-3">
                                            <label for="reference<?php echo $fee['id']; ?>" class="form-label">
                                                Riferimento bonifico <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" 
                                                   class="form-control" 
                                                   id="reference<?php echo $fee['id']; ?>" 
                                                   name="payment_reference" 
                                                   required
                                                   placeholder="es. Bonifico del 04/01/2026">
                                            <small class="text-muted">
                                                Inserisci la data o un riferimento del bonifico per facilitare la verifica.
                                            </small>
                                        </div>
                                        
                                        <div class="alert alert-info small">
                                            <i class="bi bi-info-circle"></i> 
                                            Un amministratore verificherà il bonifico e confermerà il pagamento.
                                        </div>
                                        
                                        <input type="hidden" name="action" value="request_offline_payment">
                                        <input type="hidden" name="fee_id" value="<?php echo $fee['id']; ?>">
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                            Annulla
                                        </button>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-check-circle"></i> Conferma
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Waiting for Confirmation -->
        <?php if (!empty($waitingFees)): ?>
            <div class="mb-5">
                <h4 class="mb-3">In attesa di conferma</h4>
                
                <?php foreach ($waitingFees as $fee): ?>
                    <div class="card mb-3 border-warning">
                        <div class="card-header bg-warning">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0">
                                        Quota associativa - <?php echo h($fee['year_name']); ?>
                                    </h6>
                                </div>
                                <div>
                                    <strong><?php echo formatAmount($fee['amount']); ?></strong>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <p class="mb-2">
                                <i class="bi bi-hourglass-split"></i> 
                                Il tuo pagamento è in attesa di conferma da parte dell'amministrazione.
                            </p>
                            <?php if ($fee['payment_reference']): ?>
                                <p class="mb-0 small text-muted">
                                    <strong>Riferimento:</strong> <?php echo h($fee['payment_reference']); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- No pending fees message -->
        <?php if (empty($pendingFees) && empty($waitingFees)): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle"></i> 
                Non hai quote in sospeso. Tutte le tue quote sono state pagate!
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($paypalEnabled && $paypalClientId && !empty($pendingFees)): ?>
<script src="https://www.paypal.com/sdk/js?client-id=<?php echo h($paypalClientId); ?>&currency=EUR"></script>
<script>
<?php foreach ($pendingFees as $fee): ?>
    paypal.Buttons({
        createOrder: function(data, actions) {
            return actions.order.create({
                purchase_units: [{
                    amount: { 
                        value: '<?php echo number_format($fee['amount'], 2, '.', ''); ?>' 
                    },
                    description: 'Quota associativa <?php echo h($fee['year_name']); ?>'
                }]
            });
        },
        onApprove: function(data, actions) {
            return actions.order.capture().then(function(details) {
                // Send to server for confirmation
                fetch('<?php echo h($basePath); ?>portal/api/paypal_confirm.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        fee_id: <?php echo $fee['id']; ?>,
                        order_id: data.orderID,
                        transaction_id: details.id,
                        payer_name: details.payer.name.given_name + ' ' + details.payer.name.surname,
                        payer_email: details.payer.email_address
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = '<?php echo h($basePath); ?>portal/receipts.php?success=1';
                    } else {
                        alert('Errore nella conferma del pagamento: ' + (data.message || 'Errore sconosciuto'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Errore di comunicazione con il server');
                });
            });
        },
        onError: function(err) {
            console.error('PayPal error:', err);
            alert('Errore durante il pagamento PayPal');
        }
    }).render('#paypal-button-container-<?php echo $fee['id']; ?>');
<?php endforeach; ?>
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
