<?php
/**
 * Tessera Socio con QR Code
 * Genera e visualizza tessera socio stampabile
 */

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/audit.php';

requireLogin();

$config = require __DIR__ . '/../src/config.php';
$siteName = $config['app']['name'] ?? 'Associazione';
$basePath = $config['app']['base_path'];

// Get member ID
$memberId = $_GET['member_id'] ?? null;

if (!$memberId) {
    setFlashMessage('ID socio mancante', 'danger');
    redirect($basePath . 'members.php');
}

// Get member data
$stmt = $pdo->prepare("SELECT * FROM " . table('members') . " WHERE id = ?");
$stmt->execute([$memberId]);
$member = $stmt->fetch();

if (!$member) {
    setFlashMessage('Socio non trovato', 'danger');
    redirect($basePath . 'members.php');
}

// Handle card generation/regeneration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_card'])) {
    $token = $_POST['csrf_token'] ?? '';
    
    if (verifyCsrfToken($token)) {
        // Generate new token
        $cardToken = generateCardToken();
        
        $stmt = $pdo->prepare("
            UPDATE " . table('members') . "
            SET card_token = ?, card_generated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$cardToken, $memberId]);
        
        // Log action
        logUpdate('member_card', $memberId, "{$member['first_name']} {$member['last_name']}", 
            ['card_token' => $member['card_token'] ?? null],
            ['card_token' => $cardToken]
        );
        
        // Reload member data
        $stmt = $pdo->prepare("SELECT * FROM " . table('members') . " WHERE id = ?");
        $stmt->execute([$memberId]);
        $member = $stmt->fetch();
        
        setFlashMessage('Tessera generata con successo', 'success');
    }
}

// Check if member is active (has paid current year fee)
$isActive = isMemberActive($memberId);

// Get current social year
$currentYear = getCurrentSocialYear();
$yearDisplay = $currentYear ? $currentYear['name'] : date('Y');

// Get association info
$assocInfo = getAssociationInfo();

// Get logo URL
$logoUrl = getLogoUrl($assocInfo['logo'] ?? null, $basePath);

// Build verification URL
$verifyUrl = '';
if ($member['card_token']) {
    // Use base_path from config for secure URL generation
    $verifyUrl = rtrim($basePath, '/') . '/verify_member.php?token=' . urlencode($member['card_token']);
    
    // If we need full URL for QR code, prepend with protocol and host from config or SERVER_NAME
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $serverName = $_SERVER['SERVER_NAME'] ?? 'localhost';
    $verifyUrl = $protocol . '://' . $serverName . $verifyUrl;
}

$pageTitle = 'Tessera Socio - ' . $member['first_name'] . ' ' . $member['last_name'];
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
        /* Dimensioni standard tessera (85.6mm x 54mm) */
        .member-card {
            width: 85.6mm;
            height: 54mm;
            border: 1px solid #ccc;
            border-radius: 8px;
            padding: 8mm;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .member-card-front {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .member-card-back {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        .card-logo {
            text-align: center;
            margin-bottom: 10px;
        }
        
        .card-logo img {
            max-width: 80%;
            max-height: 40px;
            width: auto;
            height: auto;
            object-fit: contain;
        }
        
        .card-logo .logo-text {
            font-size: 18px;
            font-weight: bold;
            margin-top: 5px;
        }
        
        .card-title {
            font-size: 14px;
            font-weight: bold;
            text-transform: uppercase;
            border-bottom: 2px solid rgba(255,255,255,0.3);
            padding-bottom: 4px;
            margin-bottom: 8px;
        }
        
        .card-info {
            font-size: 11px;
            line-height: 1.4;
        }
        
        .card-info strong {
            font-weight: 600;
        }
        
        .card-photo {
            width: 60px;
            height: 75px;
            background: rgba(255,255,255,0.2);
            border-radius: 4px;
            overflow: hidden;
            position: absolute;
            top: 8mm;
            right: 8mm;
        }
        
        .card-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .card-photo-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            opacity: 0.5;
        }
        
        .card-status-badge {
            font-size: 9px;
        }
        
        .card-qr {
            position: absolute;
            bottom: 8mm;
            right: 8mm;
            background: white;
            padding: 4px;
            border-radius: 4px;
        }
        
        .card-qr img {
            display: block;
            width: 80px;
            height: 80px;
        }
        
        .card-back-content {
            font-size: 10px;
            line-height: 1.5;
        }
        
        /* Print styles */
        @media print {
            body {
                margin: 0;
                padding: 0;
            }
            
            .no-print {
                display: none !important;
            }
            
            .member-card {
                box-shadow: none;
                border: 1px solid #000;
                page-break-after: always;
                margin: 0;
            }
            
            .cards-container {
                display: block;
            }
        }
        
        .cards-container {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container my-4 no-print">
        <h2><i class="bi bi-credit-card"></i> Tessera Socio</h2>
        
        <?php displayFlash(); ?>
        
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">
                    <?php echo h($member['first_name'] . ' ' . $member['last_name']); ?>
                    <span class="badge bg-<?php echo $isActive ? 'success' : 'warning'; ?>">
                        <?php echo $isActive ? 'ATTIVO' : 'QUOTA NON PAGATA'; ?>
                    </span>
                </h5>
                
                <?php if ($member['card_token']): ?>
                    <p class="mb-2">
                        <strong>Tessera generata il:</strong> 
                        <?php echo formatDate(date('Y-m-d', strtotime($member['card_generated_at']))); ?>
                        alle <?php echo date('H:i', strtotime($member['card_generated_at'])); ?>
                    </p>
                    
                    <div class="mb-3">
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                            <button type="submit" name="generate_card" class="btn btn-warning btn-sm" 
                                    onclick="return confirm('Rigenerare la tessera? Il vecchio QR code non sarà più valido.')">
                                <i class="bi bi-arrow-clockwise"></i> Rigenera Tessera
                            </button>
                        </form>
                        <button onclick="window.print()" class="btn btn-primary btn-sm">
                            <i class="bi bi-printer"></i> Stampa Tessera
                        </button>
                        <a href="<?php echo $basePath; ?>member_edit.php?id=<?php echo $memberId; ?>" class="btn btn-secondary btn-sm">
                            <i class="bi bi-arrow-left"></i> Torna al Socio
                        </a>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Nessuna tessera generata. Clicca su "Genera Tessera" per crearla.
                    </div>
                    
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <button type="submit" name="generate_card" class="btn btn-success">
                            <i class="bi bi-plus-circle"></i> Genera Tessera
                        </button>
                        <a href="<?php echo $basePath; ?>member_edit.php?id=<?php echo $memberId; ?>" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Torna al Socio
                        </a>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php if ($member['card_token']): ?>
    <div class="container">
        <div class="cards-container">
            <!-- FRONTE TESSERA -->
            <div class="member-card member-card-front">
                <div class="card-logo">
                    <?php if ($logoUrl): ?>
                        <img src="<?php echo h($logoUrl); ?>" alt="Logo <?php echo h($assocInfo['name'] ?? ''); ?>">
                    <?php endif; ?>
                    <div class="logo-text"><?php echo h($assocInfo['name'] ?? $siteName); ?></div>
                </div>
                
                <div class="card-title">TESSERA SOCIO</div>
                
                <div class="card-photo">
                    <?php 
                    // Validate photo URL to prevent XSS
                    $photoUrl = validateImageUrl($member['photo_url'] ?? null);
                    if ($photoUrl): 
                    ?>
                        <img src="<?php echo h($photoUrl); ?>" alt="Foto">
                    <?php else: ?>
                        <div class="card-photo-placeholder">
                            <i class="bi bi-person"></i>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="card-info">
                    <strong>Nome:</strong> <?php echo h($member['first_name'] . ' ' . $member['last_name']); ?><br>
                    <strong>N. Tessera:</strong> <?php echo h($member['membership_number'] ?: sprintf('%05d', $member['id'])); ?><br>
                    <?php if (!empty($member['fiscal_code'])): ?>
                        <?php 
                        // Mask fiscal code: show first 3 and last 2 characters, mask the rest
                        $fc = $member['fiscal_code'];
                        $fcLen = strlen($fc);
                        if ($fcLen >= 5) {
                            $maskedFC = substr($fc, 0, 3) . str_repeat('*', $fcLen - 5) . substr($fc, -2);
                        } else {
                            $maskedFC = str_repeat('*', $fcLen); // Fully mask if too short
                        }
                        ?>
                        <strong>C.F.:</strong> <?php echo h($maskedFC); ?><br>
                    <?php endif; ?>
                    <br>
                    <strong>Valida per:</strong> Anno <?php echo h($yearDisplay); ?>
                    <span class="badge bg-<?php echo $isActive ? 'success' : 'warning'; ?> card-status-badge">
                        <?php echo $isActive ? 'ATTIVO' : 'QUOTA NON PAGATA'; ?>
                    </span>
                </div>
                
                <?php if ($verifyUrl): ?>
                <div class="card-qr">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo urlencode($verifyUrl); ?>" 
                         alt="QR Code">
                </div>
                <?php endif; ?>
            </div>
            
            <!-- RETRO TESSERA -->
            <div class="member-card member-card-back">
                <div class="card-back-content">
                    <div class="text-center mb-3">
                        <strong style="font-size: 12px;"><?php echo h($assocInfo['name'] ?? $siteName); ?></strong>
                    </div>
                    
                    <p>
                        La presente tessera è personale e non cedibile.
                    </p>
                    
                    <p>
                        In caso di smarrimento comunicare tempestivamente alla segreteria.
                    </p>
                    
                    <p>
                        Per verificare la validità della tessera, scansionare il QR code sul fronte.
                    </p>
                    
                    <div class="text-center mt-3" style="font-size: 9px; opacity: 0.8;">
                        Powered by AssoLife
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
