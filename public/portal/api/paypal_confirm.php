<?php
/**
 * PayPal Payment Confirmation API
 * Called after successful PayPal payment to confirm and record the transaction
 */

require_once __DIR__ . '/../../../src/config.php';
require_once __DIR__ . '/../../../src/db.php';
require_once __DIR__ . '/../../../src/functions.php';
require_once __DIR__ . '/../inc/auth.php';

session_start();

// Set JSON response header
header('Content-Type: application/json');

// Check authentication
if (!isPortalLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Non autenticato']);
    exit;
}

$member = getPortalMember();
if (!$member) {
    echo json_encode(['success' => false, 'message' => 'Socio non trovato']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['fee_id'], $input['order_id'], $input['transaction_id'])) {
    echo json_encode(['success' => false, 'message' => 'Dati mancanti']);
    exit;
}

$feeId = (int)$input['fee_id'];
$orderId = $input['order_id'];
$transactionId = $input['transaction_id'];
$payerName = $input['payer_name'] ?? '';
$payerEmail = $input['payer_email'] ?? '';

try {
    // Verify this fee belongs to the current member
    $stmt = $pdo->prepare("
        SELECT f.*, sy.name as year_name
        FROM " . table('member_fees') . " f
        LEFT JOIN " . table('social_years') . " sy ON f.social_year_id = sy.id
        WHERE f.id = ? AND f.member_id = ?
    ");
    $stmt->execute([$feeId, $member['id']]);
    $fee = $stmt->fetch();
    
    if (!$fee) {
        echo json_encode(['success' => false, 'message' => 'Quota non trovata']);
        exit;
    }
    
    if ($fee['status'] === 'paid') {
        echo json_encode(['success' => false, 'message' => 'Quota già pagata']);
        exit;
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Update fee status
    $stmt = $pdo->prepare("
        UPDATE " . table('member_fees') . " 
        SET status = 'paid',
            paid_date = NOW(),
            payment_method = 'PayPal',
            paypal_transaction_id = ?,
            payment_pending = 0
        WHERE id = ?
    ");
    $stmt->execute([$transactionId, $feeId]);
    
    // Generate receipt using new receipts table
    // Payment details include the transaction ID
    $paymentDetails = 'Pagamento PayPal - ID: ' . $transactionId;
    generateReceipt($feeId, 'paypal', $paymentDetails, null);
    
    // Get or create income category for membership fees
    $stmt = $pdo->prepare("
        SELECT id FROM " . table('income_categories') . " 
        WHERE name = 'Quote associative' 
        LIMIT 1
    ");
    $stmt->execute();
    $category = $stmt->fetch();
    $categoryId = $category ? $category['id'] : null;
    
    // Only create financial movement if category exists
    if ($categoryId) {
        // Create financial movement (income)
        $stmt = $pdo->prepare("
            INSERT INTO " . table('income') . "
            (social_year_id, category_id, member_id, amount, payment_method, 
             receipt_number, transaction_date, notes)
            SELECT f.social_year_id, 
                   ?,
                   f.member_id,
                   f.amount,
                   'PayPal',
                   f.receipt_number,
                   f.paid_date,
                   CONCAT('Quota associativa - ', sy.name, ' - PayPal: ', ?)
            FROM " . table('member_fees') . " f
            LEFT JOIN " . table('social_years') . " sy ON f.social_year_id = sy.id
            WHERE f.id = ?
        ");
        $stmt->execute([$categoryId, $transactionId, $feeId]);
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Pagamento confermato con successo',
        'receipt_id' => $feeId
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("PayPal confirmation error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Errore nel confermare il pagamento: ' . $e->getMessage()
    ]);
}
