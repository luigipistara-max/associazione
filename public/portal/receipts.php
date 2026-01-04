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
$config = require __DIR__ . '/../../src/config.php';
$basePath = $config['app']['base_path'];

$success = isset($_GET['success']) && $_GET['success'] == '1';

// Get member's receipts using new function
$receipts = getMemberReceipts($member['id']);

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
                Non hai ancora ricevute disponibili.
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>N. Ricevuta</th>
                                    <th>Data</th>
                                    <th>Descrizione</th>
                                    <th>Importo</th>
                                    <th>Pagamento</th>
                                    <th>Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($receipts as $receipt): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo h($receipt['receipt_number']); ?></strong>
                                        </td>
                                        <td><?php echo formatDate($receipt['issue_date']); ?></td>
                                        <td><?php echo h($receipt['description']); ?></td>
                                        <td>€ <?php echo number_format($receipt['amount'], 2, ',', '.'); ?></td>
                                        <td><?php echo h($receipt['payment_method_details']); ?></td>
                                        <td>
                                            <a href="receipt_pdf.php?id=<?php echo $receipt['id']; ?>" 
                                               class="btn btn-sm btn-primary" 
                                               target="_blank">
                                                <i class="bi bi-download"></i> Scarica PDF
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
