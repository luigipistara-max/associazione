<?php
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/functions.php';
require_once __DIR__ . '/inc/auth.php';

$config = require __DIR__ . '/../../src/config.php';
$basePath = $config['app']['base_path'];

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require login
$member = requirePortalLogin();

$pageTitle = 'Tesserino Digitale';

// Get current social year
$currentYear = getCurrentSocialYear();
$yearDisplay = $currentYear ? $currentYear['name'] : date('Y');

// Build verification URL
$verifyUrl = '';
if ($member['card_token']) {
    $verifyUrl = getBaseUrl() . 'verify_member.php?token=' . urlencode($member['card_token']);
}

// Check if member is active
$isActive = true;
if ($currentYear) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM " . table('member_fees') . " 
        WHERE member_id = ? AND social_year_id = ? AND status = 'paid'
    ");
    $stmt->execute([$member['id'], $currentYear['id']]);
    $result = $stmt->fetch();
    $isActive = $result['count'] > 0;
}

// Get association info
$assocInfo = getAssociationInfo();

// Fix logo URL
$logoUrl = '';
if (!empty($assocInfo['logo'])) {
    if (preg_match('/^https?:\/\//', $assocInfo['logo'])) {
        $logoUrl = $assocInfo['logo'];
    } else {
        $logoPath = ltrim($assocInfo['logo'], '/');
        $logoUrl = $basePath . $logoPath;
    }
}

include __DIR__ . '/inc/header.php';
?>

<style>
    .member-card {
        width: 100%;
        max-width: 400px;
        border: 2px solid #ddd;
        border-radius: 15px;
        padding: 20px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        margin: 0 auto;
    }
    .card-header-section {
        text-align: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid rgba(255,255,255,0.3);
    }
    .card-header-section img {
        max-width: 80%;
        max-height: 50px;
        width: auto;
        height: auto;
        object-fit: contain;
        /* Removed filter: brightness(0) invert(1) that was making logos white */
        /* Logo now displays in original colors for better brand visibility */
    }
    .card-body-section {
        display: flex;
        gap: 20px;
        margin-bottom: 15px;
    }
    .card-photo {
        flex-shrink: 0;
    }
    .card-info {
        flex-grow: 1;
    }
    .card-qr {
        text-align: center;
        padding-top: 15px;
        border-top: 1px solid rgba(255,255,255,0.3);
    }
    .card-qr img {
        background: white;
        padding: 10px;
        border-radius: 8px;
    }
    .info-label {
        font-size: 0.75rem;
        opacity: 0.8;
        margin-bottom: 2px;
    }
    .info-value {
        font-size: 1rem;
        font-weight: bold;
        margin-bottom: 10px;
    }
    .status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: bold;
        background: rgba(255,255,255,0.2);
    }
    .status-active {
        background: #28a745;
    }
    .member-card-back {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        margin-top: 20px;
    }
    .card-back-content {
        font-size: 0.9rem;
        line-height: 1.6;
    }
    .card-status-badge {
        font-size: 9px;
    }
    @media print {
        body {
            background: white;
        }
        .navbar, footer, .no-print {
            display: none !important;
        }
        .member-card {
            box-shadow: none;
            border: 2px solid #000;
        }
    }
</style>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card mb-4 no-print">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-1"><i class="bi bi-card-heading"></i> Il tuo Tesserino Digitale</h5>
                        <p class="text-muted mb-0">Mostra questo tesserino per identificarti come socio</p>
                    </div>
                    <div>
                        <button onclick="window.print()" class="btn btn-primary">
                            <i class="bi bi-printer"></i> Stampa
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="text-center mb-4">
            <div class="member-card">
                <div class="card-header-section">
                    <?php if ($logoUrl): ?>
                        <img src="<?php echo h($logoUrl); ?>" alt="Logo">
                    <?php endif; ?>
                    <h6 class="mt-2 mb-0"><?php echo h($assocInfo['name'] ?? 'Associazione'); ?></h6>
                    <small style="opacity: 0.8;">Tessera Socio <?php echo h($yearDisplay); ?></small>
                </div>
                
                <div class="card-body-section">
                    <div class="card-photo">
                        <?php if (!empty($member['photo_url'])): ?>
                            <img src="<?php echo h($member['photo_url']); ?>" alt="Foto" class="member-photo">
                        <?php else: ?>
                            <div class="member-photo-placeholder">
                                <i class="bi bi-person-circle" style="font-size: 2rem;"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="card-info">
                        <div class="info-label">Nome</div>
                        <div class="info-value"><?php echo h($member['first_name'] . ' ' . $member['last_name']); ?></div>
                        
                        <div class="info-label">Tessera N.</div>
                        <div class="info-value"><?php echo h($member['membership_number'] ?? 'N/A'); ?></div>
                        
                        <div class="info-label">Stato</div>
                        <div>
                            <span class="status-badge <?php echo $isActive ? 'status-active' : ''; ?>">
                                <?php echo $isActive ? 'ATTIVO' : 'NON ATTIVO'; ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <?php if ($verifyUrl): ?>
                    <div class="card-qr">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=<?php echo urlencode($verifyUrl); ?>" 
                             alt="QR Code" width="120" height="120">
                        <div class="mt-2" style="font-size: 0.7rem; opacity: 0.8;">
                            Scansiona per verificare validità
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- RETRO TESSERA -->
        <div class="text-center mb-4">
            <div class="member-card member-card-back">
                <div class="card-back-content">
                    <div class="text-center mb-3">
                        <strong><?php echo h($assocInfo['name'] ?? 'Associazione'); ?></strong>
                    </div>
                    
                    <p>La presente tessera è personale e non cedibile.</p>
                    
                    <p>In caso di smarrimento comunicare tempestivamente alla segreteria.</p>
                    
                    <p>Per verificare la validità della tessera, scansionare il QR code sul fronte.</p>
                    
                    <div class="text-center mt-3" style="font-size: 0.8rem; opacity: 0.7;">
                        Powered by AssoLife
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card no-print">
            <div class="card-body">
                <h6><i class="bi bi-info-circle"></i> Informazioni sul Tesserino</h6>
                <ul class="mb-0">
                    <li>Il tesserino è valido per l'anno sociale <?php echo h($yearDisplay); ?></li>
                    <li>Puoi mostrarlo in formato digitale dal tuo smartphone</li>
                    <li>Oppure stamparlo cliccando il pulsante "Stampa" sopra</li>
                    <?php if ($verifyUrl): ?>
                        <li>Il QR code permette di verificare la validità del tesserino</li>
                    <?php endif; ?>
                    <?php if (empty($member['photo_url'])): ?>
                        <li class="text-warning">
                            <i class="bi bi-exclamation-triangle"></i> 
                            <a href="<?php echo h($basePath); ?>portal/photo.php">Carica una fototessera</a> 
                            per completare il tuo tesserino
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/inc/footer.php'; ?>
