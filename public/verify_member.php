<?php
/**
 * Verifica Tessera Socio (PAGINA PUBBLICA - NO LOGIN)
 * Verifica validità tessera tramite QR code
 */

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/functions.php';

$config = require __DIR__ . '/../src/config.php';
$siteName = $config['app']['name'] ?? 'Associazione';

$token = $_GET['token'] ?? null;
$memberData = null;
$status = 'invalid'; // invalid, active, expired

if ($token) {
    $memberData = verifyMemberCard($token);
    
    if ($memberData) {
        // Check if member has paid current year
        if ($memberData['has_paid_current'] > 0 && $memberData['status'] === 'attivo') {
            $status = 'active';
        } elseif ($memberData['status'] === 'attivo') {
            $status = 'expired';
        } else {
            $status = 'invalid';
        }
    }
}

// Get current year for display
$currentYear = getCurrentSocialYear();
$yearDisplay = $currentYear ? $currentYear['name'] : date('Y');

$pageTitle = 'Verifica Tessera Socio';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($pageTitle); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .verify-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-width: 500px;
            width: 100%;
        }
        
        .verify-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 16px 16px 0 0;
            text-align: center;
        }
        
        .verify-body {
            padding: 40px 30px;
        }
        
        .status-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }
        
        .status-active {
            color: #28a745;
        }
        
        .status-expired {
            color: #ffc107;
        }
        
        .status-invalid {
            color: #dc3545;
        }
        
        .member-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #6c757d;
        }
        
        .info-value {
            color: #212529;
        }
    </style>
</head>
<body>
    <div class="verify-card">
        <div class="verify-header">
            <h3 class="mb-0">
                <i class="bi bi-shield-check"></i><br>
                <?php echo h($siteName); ?>
            </h3>
            <p class="mb-0 mt-2">Verifica Tessera Socio</p>
        </div>
        
        <div class="verify-body text-center">
            <?php if (!$token): ?>
                <!-- No token provided -->
                <div class="status-icon status-invalid">
                    <i class="bi bi-question-circle"></i>
                </div>
                <h4>Token Mancante</h4>
                <p class="text-muted">
                    Scansiona il QR code sulla tessera per verificarne la validità.
                </p>
                
            <?php elseif ($status === 'active'): ?>
                <!-- Valid and active member -->
                <div class="status-icon status-active">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
                <h4 class="text-success">✅ TESSERA VALIDA</h4>
                <p class="text-muted">Socio attivo con quota regolare</p>
                
                <div class="member-info">
                    <div class="info-row">
                        <span class="info-label">Nome:</span>
                        <span class="info-value"><?php echo h($memberData['first_name'] . ' ' . $memberData['last_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">N. Tessera:</span>
                        <span class="info-value"><?php echo h($memberData['membership_number'] ?: sprintf('%05d', $memberData['id'])); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Stato:</span>
                        <span class="info-value"><span class="badge bg-success">ATTIVO</span></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Anno:</span>
                        <span class="info-value"><?php echo h($yearDisplay); ?></span>
                    </div>
                </div>
                
            <?php elseif ($status === 'expired'): ?>
                <!-- Valid card but expired fee -->
                <div class="status-icon status-expired">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                </div>
                <h4 class="text-warning">⚠️ QUOTA SCADUTA</h4>
                <p class="text-muted">La tessera è valida ma la quota associativa non è stata pagata</p>
                
                <div class="member-info">
                    <div class="info-row">
                        <span class="info-label">Nome:</span>
                        <span class="info-value"><?php echo h($memberData['first_name'] . ' ' . $memberData['last_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">N. Tessera:</span>
                        <span class="info-value"><?php echo h($memberData['membership_number'] ?: sprintf('%05d', $memberData['id'])); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Stato:</span>
                        <span class="info-value"><span class="badge bg-warning">QUOTA SCADUTA</span></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Anno:</span>
                        <span class="info-value"><?php echo h($yearDisplay); ?></span>
                    </div>
                </div>
                
                <div class="alert alert-warning mt-3 mb-0">
                    <small>
                        <i class="bi bi-info-circle"></i> 
                        Contattare la segreteria per regolarizzare la posizione
                    </small>
                </div>
                
            <?php else: ?>
                <!-- Invalid token or inactive member -->
                <div class="status-icon status-invalid">
                    <i class="bi bi-x-circle-fill"></i>
                </div>
                <h4 class="text-danger">❌ TESSERA NON VALIDA</h4>
                <p class="text-muted">
                    La tessera non è valida o non è più attiva.<br>
                    Contattare la segreteria per maggiori informazioni.
                </p>
                
            <?php endif; ?>
        </div>
        
        <div class="text-center pb-4">
            <small class="text-muted">
                <i class="bi bi-shield-lock"></i> Verifica sicura
            </small>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
