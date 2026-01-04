<?php
/**
 * Portal - Receipt PDF/Print View
 * Display and print receipt for member
 */

require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/functions.php';
require_once __DIR__ . '/inc/auth.php';

session_start();
$member = requirePortalLogin();

$receiptId = (int) ($_GET['id'] ?? 0);

$receipt = getReceipt($receiptId);

// Security check - member can only see own receipts
if (!$receipt || $receipt['member_id'] !== $member['id']) {
    die('Ricevuta non trovata');
}

$assocInfo = getAssociationInfo();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Ricevuta <?php echo h($receipt['receipt_number']); ?></title>
    <style>
        /* Print-friendly CSS */
        body { 
            font-family: Arial, sans-serif; 
            max-width: 800px; 
            margin: 0 auto; 
            padding: 20px; 
        }
        .receipt-header { 
            text-align: center; 
            border-bottom: 2px solid #333; 
            padding-bottom: 20px; 
            margin-bottom: 20px; 
        }
        .receipt-number { 
            font-size: 24px; 
            font-weight: bold; 
            color: #333; 
        }
        .receipt-body { 
            margin: 30px 0; 
        }
        .receipt-row { 
            display: flex; 
            margin: 15px 0; 
        }
        .receipt-label { 
            width: 150px; 
            font-weight: bold; 
        }
        .receipt-value { 
            flex: 1; 
        }
        .receipt-amount { 
            font-size: 28px; 
            font-weight: bold; 
            color: #28a745; 
            text-align: center; 
            margin: 30px 0; 
        }
        .receipt-footer { 
            border-top: 2px solid #333; 
            padding-top: 20px; 
            margin-top: 30px; 
            text-align: center; 
            font-size: 12px; 
        }
        @media print {
            .no-print { display: none; }
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            margin: 5px;
            text-decoration: none;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        .btn-primary {
            background-color: #007bff;
            color: white;
        }
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        .btn:hover {
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 20px;">
        <button onclick="window.print()" class="btn btn-primary">🖨️ Stampa</button>
        <a href="receipts.php" class="btn btn-secondary">← Torna alle ricevute</a>
    </div>

    <div class="receipt-header">
        <h1><?php echo h($assocInfo['name'] ?? 'Associazione'); ?></h1>
        <?php if (!empty($assocInfo['address']) || !empty($assocInfo['city'])): ?>
            <p><?php echo h($assocInfo['address'] ?? ''); ?> <?php echo h($assocInfo['city'] ?? ''); ?></p>
        <?php endif; ?>
        <?php if (!empty($assocInfo['fiscal_code'])): ?>
            <p>C.F.: <?php echo h($assocInfo['fiscal_code']); ?></p>
        <?php endif; ?>
    </div>

    <div class="receipt-number">
        RICEVUTA N. <?php echo h($receipt['receipt_number']); ?>
    </div>

    <div class="receipt-body">
        <div class="receipt-row">
            <div class="receipt-label">📅 Data:</div>
            <div class="receipt-value"><?php echo date('d/m/Y', strtotime($receipt['issue_date'])); ?></div>
        </div>

        <div class="receipt-row">
            <div class="receipt-label">👤 Ricevuto da:</div>
            <div class="receipt-value">
                <strong><?php echo h($receipt['first_name'] . ' ' . $receipt['last_name']); ?></strong><br>
                <?php if (!empty($receipt['address'])): ?>
                    <?php echo h($receipt['address']); ?>, <?php echo h($receipt['city']); ?> <?php echo h($receipt['postal_code']); ?><br>
                <?php endif; ?>
                <?php if (!empty($receipt['fiscal_code'])): ?>
                    C.F.: <?php echo h($receipt['fiscal_code']); ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="receipt-amount">
            € <?php echo number_format($receipt['amount'], 2, ',', '.'); ?>
        </div>

        <div class="receipt-row">
            <div class="receipt-label">📝 Causale:</div>
            <div class="receipt-value"><?php echo h($receipt['description']); ?></div>
        </div>

        <div class="receipt-row">
            <div class="receipt-label">💳 Pagamento:</div>
            <div class="receipt-value"><?php echo h($receipt['payment_method_details']); ?></div>
        </div>
    </div>

    <div class="receipt-footer">
        <p><?php echo h($assocInfo['name'] ?? 'Associazione'); ?></p>
        <?php if (!empty($assocInfo['address']) || !empty($assocInfo['city'])): ?>
            <p><?php echo h($assocInfo['address'] ?? ''); ?> - <?php echo h($assocInfo['city'] ?? ''); ?></p>
        <?php endif; ?>
        <?php if (!empty($assocInfo['fiscal_code'])): ?>
            <p>C.F.: <?php echo h($assocInfo['fiscal_code']); ?></p>
        <?php endif; ?>
    </div>
</body>
</html>
