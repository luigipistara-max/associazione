<?php
/**
 * Portal - Receipts Page
 * Display member's payment receipts
 */

require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/functions.php';
require_once __DIR__ . '/inc/auth.php';

session_start();
$member = requirePortalLogin();
$basePath = $config['app']['base_path'];

$success = isset($_GET['success']) && $_GET['success'] == '1';

// Get member's receipts (paid fees with receipt number)
$stmt = $pdo->prepare("
    SELECT f.*, sy.name as year_name
    FROM " . table('member_fees') . " f
    LEFT JOIN " . table('social_years') . " sy ON f.social_year_id = sy.id
    WHERE f.member_id = ? 
      AND f.status = 'paid' 
      AND f.receipt_number IS NOT NULL
    ORDER BY f.paid_date DESC
");
$stmt->execute([$member['id']]);
$receipts = $stmt->fetchAll();

$pageTitle = 'Ricevute';
require_once __DIR__ . '/inc/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-receipt"></i> Ricevute</h2>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle"></i> 
                Pagamento completato con successo! La tua ricevuta è disponibile qui sotto.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (empty($receipts)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> 
                Non hai ancora ricevute. Le ricevute verranno generate automaticamente dopo il pagamento delle quote.
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Numero ricevuta</th>
                                    <th>Anno sociale</th>
                                    <th>Importo</th>
                                    <th>Data pagamento</th>
                                    <th>Metodo</th>
                                    <th>Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($receipts as $receipt): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo h($receipt['receipt_number']); ?></strong>
                                        </td>
                                        <td><?php echo h($receipt['year_name']); ?></td>
                                        <td><?php echo formatAmount($receipt['amount']); ?></td>
                                        <td><?php echo formatDate($receipt['paid_date']); ?></td>
                                        <td>
                                            <?php if ($receipt['payment_method']): ?>
                                                <span class="badge bg-secondary">
                                                    <?php echo h($receipt['payment_method']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                            
                                            <?php if ($receipt['paypal_transaction_id']): ?>
                                                <br>
                                                <small class="text-muted">
                                                    PayPal: <?php echo h(substr($receipt['paypal_transaction_id'], 0, 20)); ?>...
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="<?php echo h($basePath); ?>receipt.php?id=<?php echo $receipt['id']; ?>&token=<?php echo h(generateReceiptToken($receipt['id'], $member['id'])); ?>" 
                                               class="btn btn-sm btn-primary" 
                                               target="_blank">
                                                <i class="bi bi-file-pdf"></i> Visualizza PDF
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="alert alert-light mt-4">
                <i class="bi bi-info-circle"></i> 
                <strong>Nota:</strong> Le ricevute sono generate automaticamente al momento del pagamento.
                Puoi scaricare e stampare ogni ricevuta in formato PDF cliccando sul pulsante "Visualizza PDF".
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
