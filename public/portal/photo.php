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

$pageTitle = 'Carica Fototessera';
$error = '';
$success = '';

// Handle photo upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photo'])) {
    $imgbbApiKey = getSetting('imgbb_api_key');
    
    if (empty($imgbbApiKey)) {
        $error = 'Servizio di upload non configurato. Contatta l\'amministratore.';
    } else {
        $file = $_FILES['photo'];
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error = 'Errore durante il caricamento del file.';
        } else {
            // Validate image
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $fileType = mime_content_type($file['tmp_name']);
            
            if (!in_array($fileType, $allowedTypes)) {
                $error = 'Formato non valido. Usa JPG, PNG o GIF.';
            } elseif ($file['size'] > 2 * 1024 * 1024) {
                $error = 'File troppo grande. Massimo 2MB.';
            } else {
                // Upload to ImgBB
                $imageData = base64_encode(file_get_contents($file['tmp_name']));
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, 'https://api.imgbb.com/1/upload?key=' . $imgbbApiKey);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['image' => $imageData]));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode === 200) {
                    $result = json_decode($response, true);
                    
                    if ($result && isset($result['success']) && $result['success']) {
                        $photoUrl = $result['data']['display_url'];
                        
                        // Save to member
                        $stmt = $pdo->prepare("UPDATE " . table('members') . " SET photo_url = ? WHERE id = ?");
                        if ($stmt->execute([$photoUrl, $member['id']])) {
                            $success = 'Fototessera caricata con successo!';
                            // Reload member data
                            $member = getMember($member['id']);
                        } else {
                            $error = 'Errore durante il salvataggio. Riprova.';
                        }
                    } else {
                        $error = 'Errore durante il caricamento su ImgBB. Riprova.';
                    }
                } else {
                    $error = 'Errore di comunicazione con il servizio di upload. Riprova più tardi.';
                }
            }
        }
    }
}

// Handle photo removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_photo'])) {
    $stmt = $pdo->prepare("UPDATE " . table('members') . " SET photo_url = NULL WHERE id = ?");
    if ($stmt->execute([$member['id']])) {
        $success = 'Fototessera rimossa con successo!';
        // Reload member data
        $member = getMember($member['id']);
    } else {
        $error = 'Errore durante la rimozione. Riprova.';
    }
}

include __DIR__ . '/inc/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-camera"></i> Fototessera
                </h5>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo h($error); ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo h($success); ?></div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-4 text-center mb-4 mb-md-0">
                        <h6 class="mb-3">Foto Attuale</h6>
                        <?php if (!empty($member['photo_url'])): ?>
                            <img src="<?php echo h($member['photo_url']); ?>" alt="Foto" class="member-photo">
                            <form method="POST" class="mt-3">
                                <button type="submit" name="remove_photo" class="btn btn-danger btn-sm" 
                                        onclick="return confirm('Sei sicuro di voler rimuovere la foto?')">
                                    <i class="bi bi-trash"></i> Rimuovi Foto
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="member-photo-placeholder">
                                <i class="bi bi-person-circle" style="font-size: 3rem;"></i>
                                <small>Nessuna foto</small>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-8">
                        <h6 class="mb-3">Carica Nuova Foto</h6>
                        
                        <div class="alert alert-info">
                            <strong><i class="bi bi-info-circle"></i> Consigli per una buona fototessera:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Usa una foto recente con viso ben visibile</li>
                                <li>Sfondo chiaro e uniforme</li>
                                <li>Formato JPG, PNG o GIF</li>
                                <li>Dimensione massima: 2MB</li>
                                <li>Rapporto 3:4 (es. 600x800 pixel) per migliore visualizzazione</li>
                            </ul>
                        </div>
                        
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label class="form-label">Seleziona Immagine</label>
                                <input type="file" name="photo" class="form-control" 
                                       accept="image/jpeg,image/png,image/gif" required>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-upload"></i> Carica Foto
                            </button>
                            <a href="<?php echo h($basePath); ?>portal/profile.php" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i> Annulla
                            </a>
                        </form>
                    </div>
                </div>
            </div>
            <div class="card-footer text-muted small">
                <i class="bi bi-shield-check"></i> La tua foto verrà caricata in modo sicuro tramite servizio ImgBB
            </div>
        </div>
        
        <div class="text-center mt-3">
            <a href="<?php echo h($basePath); ?>portal/profile.php" class="btn btn-link">
                <i class="bi bi-arrow-left"></i> Torna al Profilo
            </a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/inc/footer.php'; ?>
