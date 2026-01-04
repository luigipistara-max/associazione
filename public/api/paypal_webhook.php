<?php
/**
 * PayPal Webhook Handler
 * Handles asynchronous notifications from PayPal
 * This is a backup/alternative to client-side confirmation
 */

require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/functions.php';

// Log webhook data for debugging
$webhookData = file_get_contents('php://input');
$logFile = __DIR__ . '/../../logs/paypal_webhook.log';
$logDir = dirname($logFile);

if (!file_exists($logDir)) {
    mkdir($logDir, 0755, true);
}

file_put_contents($logFile, date('Y-m-d H:i:s') . " - Webhook received:\n" . $webhookData . "\n\n", FILE_APPEND);

// Parse webhook data
$data = json_decode($webhookData, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// Get event type
$eventType = $data['event_type'] ?? '';

// Handle PAYMENT.CAPTURE.COMPLETED event
if ($eventType === 'PAYMENT.CAPTURE.COMPLETED') {
    try {
        $captureId = $data['resource']['id'] ?? '';
        $amount = $data['resource']['amount']['value'] ?? 0;
        $currency = $data['resource']['amount']['currency_code'] ?? 'EUR';
        
        // Log capture details
        file_put_contents($logFile, "Capture ID: $captureId, Amount: $amount $currency\n", FILE_APPEND);
        
        // Here you could verify the payment with PayPal API
        // and update the fee if not already updated by client-side confirmation
        
        // For now, we just acknowledge receipt
        http_response_code(200);
        echo json_encode(['status' => 'received']);
        
    } catch (Exception $e) {
        file_put_contents($logFile, "Error: " . $e->getMessage() . "\n", FILE_APPEND);
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
} else {
    // Unknown event type - acknowledge but don't process
    file_put_contents($logFile, "Unknown event type: $eventType\n", FILE_APPEND);
    http_response_code(200);
    echo json_encode(['status' => 'acknowledged']);
}
