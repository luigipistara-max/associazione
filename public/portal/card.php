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

// Get logo URL
$logoUrl = getLogoUrl($assocInfo['logo'] ?? null, $basePath);

// Validate photo URL to prevent XSS
$photoUrl = validateImageUrl($member['photo_url'] ?? null);

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
    
    /* Card flip animation */
    .card-flip-container {
        perspective: 1000px;
        cursor: pointer;
    }
    
    .card-flip-inner {
        position: relative;
        width: 100%;
        transition: transform 0.6s;
        transform-style: preserve-3d;
    }
    
    .card-flip-container.flipped .card-flip-inner {
        transform: rotateY(180deg);
    }
    
    .member-card-front,
    .member-card-back {
        backface-visibility: hidden;
        -webkit-backface-visibility: hidden;
    }
    
    .member-card-back {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        transform: rotateY(180deg);
        margin-top: 0;
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
        .card-flip-container.flipped .card-flip-inner {
            transform: none;
        }
        .member-card-back {
            position: static;
            transform: none;
            page-break-before: always;
            margin-top: 20px;
        }
    }
</style>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card mb-4 no-print">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h5 class="mb-1"><i class="bi bi-card-heading"></i> Il tuo Tesserino Digitale</h5>
                        <p class="text-muted mb-0">Mostra questo tesserino per identificarti come socio</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button id="flipCardBtn" class="btn btn-secondary">
                            <i class="bi bi-arrow-repeat"></i> Gira
                        </button>
                        <button id="fullscreenBtn" class="btn btn-primary">
                            <i class="bi bi-arrows-fullscreen"></i> Schermo Intero
                        </button>
                        <button onclick="window.print()" class="btn btn-outline-primary">
                            <i class="bi bi-printer"></i> Stampa
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="text-center mb-4">
            <div class="card-flip-container" id="cardFlipContainer">
                <div class="card-flip-inner">
                    <!-- FRONTE TESSERA -->
                    <div class="member-card member-card-front">
                <div class="card-header-section">
                    <?php if ($logoUrl): ?>
                        <img src="<?php echo h($logoUrl); ?>" alt="Logo">
                    <?php endif; ?>
                    <h6 class="mt-2 mb-0"><?php echo h($assocInfo['name'] ?? 'Associazione'); ?></h6>
                    <small style="opacity: 0.8;">Tessera Socio <?php echo h($yearDisplay); ?></small>
                </div>
                
                <div class="card-body-section">
                    <div class="card-photo">
                        <?php if ($photoUrl): ?>
                            <img src="<?php echo h($photoUrl); ?>" alt="Foto" class="member-photo">
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
                    
                    <!-- RETRO TESSERA -->
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

<script>
// Card flip and fullscreen functionality
(function() {
    const flipContainer = document.getElementById('cardFlipContainer');
    const flipBtn = document.getElementById('flipCardBtn');
    const fullscreenBtn = document.getElementById('fullscreenBtn');
    let isFlipped = false;
    let lastTap = 0;
    
    // Flip card function
    function flipCard() {
        isFlipped = !isFlipped;
        flipContainer.classList.toggle('flipped');
    }
    
    // Button click to flip
    if (flipBtn) {
        flipBtn.addEventListener('click', flipCard);
    }
    
    // Touch to flip (tap on card)
    if (flipContainer) {
        flipContainer.addEventListener('click', function(e) {
            // Don't flip if clicking on buttons
            if (e.target.closest('.btn')) return;
            flipCard();
        });
        
        // Double tap for fullscreen on mobile
        flipContainer.addEventListener('touchend', function(e) {
            const currentTime = new Date().getTime();
            const tapLength = currentTime - lastTap;
            
            if (tapLength < 300 && tapLength > 0) {
                // Double tap detected
                e.preventDefault();
                toggleFullscreen();
            }
            lastTap = currentTime;
        });
    }
    
    // Fullscreen functionality
    function toggleFullscreen() {
        if (!document.fullscreenElement && 
            !document.webkitFullscreenElement && 
            !document.mozFullScreenElement &&
            !document.msFullscreenElement) {
            enterFullscreen();
        } else {
            exitFullscreen();
        }
    }
    
    function enterFullscreen() {
        const elem = flipContainer;
        
        if (elem.requestFullscreen) {
            elem.requestFullscreen();
        } else if (elem.webkitRequestFullscreen) {
            elem.webkitRequestFullscreen();
        } else if (elem.mozRequestFullScreen) {
            elem.mozRequestFullScreen();
        } else if (elem.msRequestFullscreen) {
            elem.msRequestFullscreen();
        } else {
            // Fallback: manual fullscreen simulation
            enterFallbackFullscreen();
        }
    }
    
    function exitFullscreen() {
        if (document.exitFullscreen) {
            document.exitFullscreen();
        } else if (document.webkitExitFullscreen) {
            document.webkitExitFullscreen();
        } else if (document.mozCancelFullScreen) {
            document.mozCancelFullScreen();
        } else if (document.msExitFullscreen) {
            document.msExitFullscreen();
        } else {
            // Fallback: exit manual fullscreen
            exitFallbackFullscreen();
        }
    }
    
    // Fallback fullscreen for browsers/devices that don't support Fullscreen API
    function enterFallbackFullscreen() {
        const cardClone = flipContainer.cloneNode(true);
        cardClone.id = 'fullscreenCardClone';
        
        const fullscreenDiv = document.createElement('div');
        fullscreenDiv.id = 'fallbackFullscreen';
        fullscreenDiv.className = 'fullscreen-container';
        
        const exitBtn = document.createElement('button');
        exitBtn.className = 'fullscreen-exit-btn';
        exitBtn.innerHTML = '×';
        exitBtn.onclick = exitFallbackFullscreen;
        
        fullscreenDiv.appendChild(exitBtn);
        fullscreenDiv.appendChild(cardClone);
        document.body.appendChild(fullscreenDiv);
        
        // Make clone flippable
        const cloneFlipContainer = cardClone;
        cloneFlipContainer.addEventListener('click', function(e) {
            if (e.target.closest('.fullscreen-exit-btn')) return;
            cloneFlipContainer.classList.toggle('flipped');
        });
        
        // Prevent body scroll
        document.body.style.overflow = 'hidden';
    }
    
    function exitFallbackFullscreen() {
        const fullscreenDiv = document.getElementById('fallbackFullscreen');
        if (fullscreenDiv) {
            fullscreenDiv.remove();
            document.body.style.overflow = '';
        }
    }
    
    // Fullscreen button click
    if (fullscreenBtn) {
        fullscreenBtn.addEventListener('click', toggleFullscreen);
    }
    
    // Handle fullscreen change events
    document.addEventListener('fullscreenchange', handleFullscreenChange);
    document.addEventListener('webkitfullscreenchange', handleFullscreenChange);
    document.addEventListener('mozfullscreenchange', handleFullscreenChange);
    document.addEventListener('MSFullscreenChange', handleFullscreenChange);
    
    function handleFullscreenChange() {
        if (!document.fullscreenElement && 
            !document.webkitFullscreenElement &&
            !document.mozFullScreenElement &&
            !document.msFullscreenElement) {
            // Exited fullscreen
            if (fullscreenBtn) {
                fullscreenBtn.innerHTML = '<i class="bi bi-arrows-fullscreen"></i> Schermo Intero';
            }
        } else {
            // Entered fullscreen
            if (fullscreenBtn) {
                fullscreenBtn.innerHTML = '<i class="bi bi-fullscreen-exit"></i> Esci';
            }
        }
    }
    
    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // F key to flip
        if (e.key === 'f' || e.key === 'F') {
            if (!e.target.matches('input, textarea')) {
                e.preventDefault();
                flipCard();
            }
        }
        
        // ESC to exit fullscreen (handled by browser, but also cleanup fallback)
        if (e.key === 'Escape') {
            exitFallbackFullscreen();
        }
    });
})();
</script>

<?php include __DIR__ . '/inc/footer.php'; ?>
