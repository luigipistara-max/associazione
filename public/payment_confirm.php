<?php
/**
 * Admin - Payment Confirmation
 * Confirm offline (bank transfer) payments
 */

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/functions.php';

requireLogin();
$config = require __DIR__ . '/../src/config.php';
$pageTitle = 'Conferma Pagamenti';

$success = '';
$error = '';

// Handle payment confirmation/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['fee_id'])) {
    $feeId = (int)$_POST['fee_id'];
    $adminId = getCurrentUser()['id'];
    
    if ($_POST['action'] === 'confirm') {
        if (confirmOfflinePayment($feeId, $adminId)) {
            $success = 'Pagamento confermato con successo. La ricevuta è stata generata.';
        } else {
            $error = 'Errore nella conferma del pagamento.';
        }
    } elseif ($_POST['action'] === 'reject') {
        // Reset payment_pending flag
        global $pdo;
        $stmt = $pdo->prepare("
            UPDATE " . table('member_fees') . " 
            SET payment_pending = 0, payment_reference = NULL
            WHERE id = ?
        ");
        if ($stmt->execute([$feeId])) {
            $success = 'Pagamento rifiutato. Il socio dovrà riprovare.';
        } else {
            $error = 'Errore nel rifiutare il pagamento.';
        }
    }
}

// Get pending payments
global $pdo;
$stmt = $pdo->query("
    SELECT f.*, 
           m.first_name, m.last_name, m.email, m.membership_number,
           sy.name as year_name
    FROM " . table('member_fees') . " f
    JOIN " . table('members') . " m ON f.member_id = m.id
    LEFT JOIN " . table('social_years') . " sy ON f.social_year_id = sy.id
    WHERE f.payment_pending = 1
    ORDER BY f.updated_at DESC
");
$pendingPayments = $stmt->fetchAll();

require_once __DIR__ . '/inc/header.php';
?>

<div class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">
            <i class="bi bi-cash-coin"></i> Conferma Pagamenti Offline
        </h1>
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

    <div class="alert alert-info">
        <i class="bi bi-info-circle"></i> 
        <strong>Nota:</strong> Questi sono i pagamenti che i soci hanno dichiarato di aver effettuato tramite bonifico.
        Verifica che il bonifico sia stato ricevuto prima di confermare.
    </div>

    <?php if (empty($pendingPayments)): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle"></i> Non ci sono pagamenti in attesa di conferma.
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Socio</th>
                                <th>Tessera</th>
                                <th>Email</th>
                                <th>Anno sociale</th>
                                <th>Importo</th>
                                <th>Riferimento</th>
                                <th>Data richiesta</th>
                                <th>Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingPayments as $payment): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo h($payment['first_name'] . ' ' . $payment['last_name']); ?></strong>
                                    </td>
                                    <td><?php echo h($payment['membership_number']); ?></td>
                                    <td>
                                        <a href="mailto:<?php echo h($payment['email']); ?>">
                                            <?php echo h($payment['email']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo h($payment['year_name']); ?></td>
                                    <td>
                                        <strong><?php echo formatAmount($payment['amount']); ?></strong>
                                    </td>
                                    <td>
                                        <?php if ($payment['payment_reference']): ?>
                                            <span class="badge bg-secondary">
                                                <?php echo h($payment['payment_reference']); ?>
                                            </span>
                                        <?php else: ?>
                                            <small class="text-muted">Nessun riferimento</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo date('d/m/Y H:i', strtotime($payment['updated_at'])); ?>
                                    </td>
                                    <td>
                                        <button type="button" 
                                                class="btn btn-sm btn-success me-1" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#confirmModal<?php echo $payment['id']; ?>">
                                            <i class="bi bi-check-circle"></i> Conferma
                                        </button>
                                        <button type="button" 
                                                class="btn btn-sm btn-danger" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#rejectModal<?php echo $payment['id']; ?>">
                                            <i class="bi bi-x-circle"></i> Rifiuta
                                        </button>
                                    </td>
                                </tr>

                                <!-- Confirm Modal -->
                                <div class="modal fade" id="confirmModal<?php echo $payment['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST">
                                                <div class="modal-header bg-success text-white">
                                                    <h5 class="modal-title">Conferma pagamento</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p>
                                                        Confermi di aver ricevuto il bonifico di <strong><?php echo formatAmount($payment['amount']); ?></strong>
                                                        da <strong><?php echo h($payment['first_name'] . ' ' . $payment['last_name']); ?></strong>?
                                                    </p>
                                                    
                                                    <?php if ($payment['payment_reference']): ?>
                                                        <p class="mb-2">
                                                            <strong>Riferimento fornito:</strong> 
                                                            <?php echo h($payment['payment_reference']); ?>
                                                        </p>
                                                    <?php endif; ?>
                                                    
                                                    <div class="alert alert-info">
                                                        <i class="bi bi-info-circle"></i> 
                                                        Confermando, il sistema:
                                                        <ul class="mb-0">
                                                            <li>Segnerà la quota come pagata</li>
                                                            <li>Genererà automaticamente la ricevuta</li>
                                                            <li>Creerà il movimento finanziario</li>
                                                        </ul>
                                                    </div>
                                                    
                                                    <input type="hidden" name="action" value="confirm">
                                                    <input type="hidden" name="fee_id" value="<?php echo $payment['id']; ?>">
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                        Annulla
                                                    </button>
                                                    <button type="submit" class="btn btn-success">
                                                        <i class="bi bi-check-circle"></i> Conferma pagamento
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- Reject Modal -->
                                <div class="modal fade" id="rejectModal<?php echo $payment['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST">
                                                <div class="modal-header bg-danger text-white">
                                                    <h5 class="modal-title">Rifiuta pagamento</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p>
                                                        Confermi di voler rifiutare il pagamento dichiarato da 
                                                        <strong><?php echo h($payment['first_name'] . ' ' . $payment['last_name']); ?></strong>?
                                                    </p>
                                                    
                                                    <div class="alert alert-warning">
                                                        <i class="bi bi-exclamation-triangle"></i> 
                                                        Il socio dovrà dichiarare nuovamente il pagamento.
                                                        Si consiglia di contattare il socio prima di rifiutare.
                                                    </div>
                                                    
                                                    <input type="hidden" name="action" value="reject">
                                                    <input type="hidden" name="fee_id" value="<?php echo $payment['id']; ?>">
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                        Annulla
                                                    </button>
                                                    <button type="submit" class="btn btn-danger">
                                                        <i class="bi bi-x-circle"></i> Rifiuta pagamento
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
