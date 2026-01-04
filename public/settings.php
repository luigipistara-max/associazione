<?php
/**
 * Settings Page - Association Configuration
 * Only accessible by admin users
 */

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/functions.php';

requireLogin();
requireAdmin(); // Only admin can access settings

$pageTitle = 'Impostazioni Associazione';

// Handle SMTP test
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'test_smtp') {
    header('Content-Type: application/json');
    
    // Validate and sanitize SMTP settings before saving
    $smtpSettings = [];
    
    // Validate enabled flag
    $smtpSettings['smtp_enabled'] = isset($_POST['smtp_enabled']) && $_POST['smtp_enabled'] === '1' ? '1' : '0';
    
    // Validate and sanitize host (alphanumeric, dots, hyphens only)
    $smtpSettings['smtp_host'] = preg_replace('/[^a-zA-Z0-9.-]/', '', $_POST['smtp_host'] ?? '');
    
    // Validate port (must be 1-65535)
    $port = (int) ($_POST['smtp_port'] ?? 587);
    $smtpSettings['smtp_port'] = ($port > 0 && $port <= 65535) ? (string) $port : '587';
    
    // Validate security (must be one of allowed values)
    $security = $_POST['smtp_security'] ?? 'tls';
    $smtpSettings['smtp_security'] = in_array($security, ['none', 'ssl', 'tls']) ? $security : 'tls';
    
    // Validate emails
    $username = trim($_POST['smtp_username'] ?? '');
    $fromEmail = trim($_POST['smtp_from_email'] ?? '');
    
    if (!empty($username) && !filter_var($username, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Username SMTP non è un indirizzo email valido']);
        exit;
    }
    
    if (!empty($fromEmail) && !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Email mittente non è valida']);
        exit;
    }
    
    $smtpSettings['smtp_username'] = $username;
    // Password is stored as-is (not sanitized) to preserve exact value for SMTP authentication
    // Note: Consider implementing encryption for password storage in a future update
    $smtpSettings['smtp_password'] = $_POST['smtp_password'] ?? '';
    $smtpSettings['smtp_from_email'] = $fromEmail;
    $smtpSettings['smtp_from_name'] = htmlspecialchars(trim($_POST['smtp_from_name'] ?? ''), ENT_QUOTES, 'UTF-8');
    
    // Save settings
    foreach ($smtpSettings as $key => $value) {
        setSetting($key, $value);
    }
    
    // Invia email di test - usa smtp_from_email se disponibile, altrimenti smtp_username
    $testEmail = $fromEmail ?: $username;
    
    // Valida che sia un'email valida
    if (empty($testEmail) || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Configura un indirizzo email valido nelle impostazioni SMTP']);
        exit;
    }
    
    require_once __DIR__ . '/../src/email.php';
    $result = testSmtpConnection($testEmail);
    
    echo json_encode([
        'success' => $result,
        'message' => $result ? 'Email inviata!' : 'Errore invio email. Controlla le credenziali.'
    ]);
    exit;
}

// Handle API settings form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_api_settings') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('Token di sicurezza non valido', 'danger');
        redirect('settings.php?tab=api');
    }
    
    // Save reCAPTCHA settings
    setSetting('recaptcha_site_key', trim($_POST['recaptcha_site_key'] ?? ''), 'api');
    setSetting('recaptcha_secret_key', trim($_POST['recaptcha_secret_key'] ?? ''), 'api');
    
    // Save TinyMCE API key
    setSetting('tinymce_api_key', trim($_POST['tinymce_api_key'] ?? ''), 'api');
    
    // Save ImgBB API key
    setSetting('imgbb_api_key', trim($_POST['imgbb_api_key'] ?? ''), 'api');
    
    // Save PayPal settings
    setSetting('paypal_mode', $_POST['paypal_mode'] ?? 'sandbox', 'api');
    setSetting('paypal_client_id', trim($_POST['paypal_client_id'] ?? ''), 'api');
    setSetting('paypal_client_secret', trim($_POST['paypal_client_secret'] ?? ''), 'api');
    setSetting('paypal_webhook_id', trim($_POST['paypal_webhook_id'] ?? ''), 'api');
    
    // Save Cron Token
    setSetting('cron_token', trim($_POST['cron_token'] ?? ''), 'security');
    
    setFlash('Impostazioni API salvate con successo!', 'success');
    redirect('settings.php?tab=api');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // TODO: Add CSRF protection in production
    // Example: if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) { die('CSRF token mismatch'); }
    
    $settings = [];
    
    // Association data
    if (isset($_POST['association_name'])) {
        $settings[] = ['association_name', $_POST['association_name'], 'association'];
        $settings[] = ['association_full_name', $_POST['association_full_name'] ?? '', 'association'];
        $settings[] = ['association_slogan', $_POST['association_slogan'] ?? '', 'association'];
    }
    
    // Legal representative
    if (isset($_POST['legal_representative_name'])) {
        $settings[] = ['legal_representative_name', $_POST['legal_representative_name'], 'legal'];
        $settings[] = ['legal_representative_role', $_POST['legal_representative_role'] ?? '', 'legal'];
        $settings[] = ['legal_representative_cf', $_POST['legal_representative_cf'] ?? '', 'legal'];
    }
    
    // Address and contacts
    if (isset($_POST['address_street'])) {
        $settings[] = ['address_street', $_POST['address_street'], 'address'];
        $settings[] = ['address_cap', $_POST['address_cap'] ?? '', 'address'];
        $settings[] = ['address_city', $_POST['address_city'] ?? '', 'address'];
        $settings[] = ['address_province', $_POST['address_province'] ?? '', 'address'];
        $settings[] = ['contact_phone', $_POST['contact_phone'] ?? '', 'contacts'];
        $settings[] = ['contact_email', $_POST['contact_email'] ?? '', 'contacts'];
        $settings[] = ['contact_pec', $_POST['contact_pec'] ?? '', 'contacts'];
        $settings[] = ['contact_website', $_POST['contact_website'] ?? '', 'contacts'];
    }
    
    // Fiscal data
    if (isset($_POST['fiscal_piva'])) {
        $settings[] = ['fiscal_piva', $_POST['fiscal_piva'] ?? '', 'fiscal'];
        $settings[] = ['fiscal_cf', $_POST['fiscal_cf'] ?? '', 'fiscal'];
        $settings[] = ['fiscal_rea', $_POST['fiscal_rea'] ?? '', 'fiscal'];
        $settings[] = ['fiscal_registry', $_POST['fiscal_registry'] ?? '', 'fiscal'];
    }
    
    // Banking data
    if (isset($_POST['bank_iban'])) {
        $settings[] = ['bank_iban', $_POST['bank_iban'] ?? '', 'banking'];
        $settings[] = ['bank_holder', $_POST['bank_holder'] ?? '', 'banking'];
        $settings[] = ['bank_name', $_POST['bank_name'] ?? '', 'banking'];
        $settings[] = ['bank_bic', $_POST['bank_bic'] ?? '', 'banking'];
    }
    
    // PayPal
    if (isset($_POST['paypal_email'])) {
        $settings[] = ['paypal_email', $_POST['paypal_email'] ?? '', 'paypal'];
        $settings[] = ['paypal_me_link', $_POST['paypal_me_link'] ?? '', 'paypal'];
    }
    
    // API / Integrations
    if (isset($_POST['imgbb_api_key'])) {
        $settings[] = ['imgbb_api_key', $_POST['imgbb_api_key'] ?? '', 'api'];
    }
    if (isset($_POST['paypal_mode'])) {
        $settings[] = ['paypal_mode', $_POST['paypal_mode'] ?? 'sandbox', 'api'];
        $settings[] = ['paypal_client_id', $_POST['paypal_client_id'] ?? '', 'api'];
        $settings[] = ['paypal_client_secret', $_POST['paypal_client_secret'] ?? '', 'api'];
        $settings[] = ['paypal_webhook_id', $_POST['paypal_webhook_id'] ?? '', 'api'];
    }
    
    // Email customization
    if (isset($_POST['email_signature'])) {
        $settings[] = ['email_signature', $_POST['email_signature'] ?? '', 'email'];
        $settings[] = ['email_footer', $_POST['email_footer'] ?? '', 'email'];
    }
    
    // Security settings
    if (isset($_POST['recaptcha_enabled'])) {
        $settings[] = ['recaptcha_enabled', $_POST['recaptcha_enabled'] === '1' ? '1' : '0', 'security'];
        $settings[] = ['recaptcha_site_key', trim($_POST['recaptcha_site_key'] ?? ''), 'security'];
        $settings[] = ['recaptcha_secret_key', trim($_POST['recaptcha_secret_key'] ?? ''), 'security'];
    }
    
    if (isset($_POST['2fa_enabled'])) {
        $settings[] = ['2fa_enabled', $_POST['2fa_enabled'] === '1' ? '1' : '0', 'security'];
        $twoFaRequired = $_POST['2fa_required_for'] ?? 'none';
        $validOptions = ['none', 'admin', 'staff', 'all'];
        $settings[] = ['2fa_required_for', in_array($twoFaRequired, $validOptions) ? $twoFaRequired : 'none', 'security'];
    }
    
    if (isset($_POST['password_expiry_users'])) {
        $expiryUsers = (int) ($_POST['password_expiry_users'] ?? 0);
        $settings[] = ['password_expiry_users', $expiryUsers >= 0 ? (string) $expiryUsers : '0', 'security'];
        
        $expiryMembers = (int) ($_POST['password_expiry_members'] ?? 0);
        $settings[] = ['password_expiry_members', $expiryMembers >= 0 ? (string) $expiryMembers : '0', 'security'];
    }
    
    // SMTP settings with validation
    if (isset($_POST['smtp_enabled'])) {
        // Validate enabled flag
        $smtpEnabled = isset($_POST['smtp_enabled']) && $_POST['smtp_enabled'] === '1' ? '1' : '0';
        $settings[] = ['smtp_enabled', $smtpEnabled, 'email'];
        
        // Validate and sanitize host
        $smtpHost = preg_replace('/[^a-zA-Z0-9.-]/', '', $_POST['smtp_host'] ?? '');
        $settings[] = ['smtp_host', $smtpHost, 'email'];
        
        // Validate port
        $port = (int) ($_POST['smtp_port'] ?? 587);
        $smtpPort = ($port > 0 && $port <= 65535) ? (string) $port : '587';
        $settings[] = ['smtp_port', $smtpPort, 'email'];
        
        // Validate security
        $security = $_POST['smtp_security'] ?? 'tls';
        $smtpSecurity = in_array($security, ['none', 'ssl', 'tls']) ? $security : 'tls';
        $settings[] = ['smtp_security', $smtpSecurity, 'email'];
        
        // Validate emails
        $username = trim($_POST['smtp_username'] ?? '');
        if (!empty($username) && !filter_var($username, FILTER_VALIDATE_EMAIL)) {
            setFlash('Username SMTP non è un indirizzo email valido', 'danger');
            redirect('settings.php');
        }
        $settings[] = ['smtp_username', $username, 'email'];
        
        // Password is stored as-is to preserve exact value for SMTP authentication
        $settings[] = ['smtp_password', $_POST['smtp_password'] ?? '', 'email'];
        
        $fromEmail = trim($_POST['smtp_from_email'] ?? '');
        if (!empty($fromEmail) && !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            setFlash('Email mittente non è valida', 'danger');
            redirect('settings.php');
        }
        $settings[] = ['smtp_from_email', $fromEmail, 'email'];
        
        $fromName = htmlspecialchars(trim($_POST['smtp_from_name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $settings[] = ['smtp_from_name', $fromName, 'email'];
        
        // Email send mode
        $emailSendMode = $_POST['email_send_mode'] ?? 'direct';
        $emailSendMode = in_array($emailSendMode, ['direct', 'queue']) ? $emailSendMode : 'direct';
        $settings[] = ['email_send_mode', $emailSendMode, 'email'];
    }
    
    // Handle logo upload
    if (isset($_FILES['association_logo']) && $_FILES['association_logo']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/uploads/';
        
        // Create uploads directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Validate file size (max 2MB)
        $maxSize = 2 * 1024 * 1024;
        if ($_FILES['association_logo']['size'] > $maxSize) {
            setFlash('Il file è troppo grande. Dimensione massima: 2MB', 'danger');
        } else {
            $fileInfo = pathinfo($_FILES['association_logo']['name']);
            $extension = strtolower($fileInfo['extension']);
            
            // Validate file type by extension and MIME type
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
            $allowedMimes = ['image/jpeg', 'image/png', 'image/gif'];
            $fileMime = mime_content_type($_FILES['association_logo']['tmp_name']);
            
            if (in_array($extension, $allowedExtensions) && in_array($fileMime, $allowedMimes)) {
                $newFileName = 'logo.' . $extension;
                $uploadPath = $uploadDir . $newFileName;
                
                if (move_uploaded_file($_FILES['association_logo']['tmp_name'], $uploadPath)) {
                    $settings[] = ['association_logo', 'uploads/' . $newFileName, 'association'];
                } else {
                    setFlash('Errore durante il caricamento del logo', 'danger');
                }
            } else {
                setFlash('Tipo di file non valido. Sono ammessi solo JPG, PNG e GIF', 'danger');
            }
        }
    }
    
    // Save all settings
    setSettings($settings);
    
    setFlash('Impostazioni salvate con successo', 'success');
    redirect('settings.php');
}

// Load current settings
$currentSettings = getAllSettings();

include __DIR__ . '/inc/header.php';
?>

<style>
.nav-pills .nav-link.active {
    background-color: #0d6efd;
    color: #fff;
}

.nav-pills .nav-link {
    color: #0d6efd;
}

.nav-tabs .nav-link.active {
    background-color: #0d6efd;
    color: #fff;
    border-color: #0d6efd;
}

.nav-tabs .nav-link {
    color: #0d6efd;
}
</style>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="bi bi-gear"></i> Impostazioni Associazione</h1>
</div>

<!-- Tab Navigation -->
<ul class="nav nav-tabs mb-4" id="settingsTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="association-tab" data-bs-toggle="tab" data-bs-target="#association" type="button" role="tab">
            <i class="bi bi-building"></i> Associazione
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="legal-tab" data-bs-toggle="tab" data-bs-target="#legal" type="button" role="tab">
            <i class="bi bi-person-badge"></i> Rappresentante
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="address-tab" data-bs-toggle="tab" data-bs-target="#address" type="button" role="tab">
            <i class="bi bi-geo-alt"></i> Sede
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="fiscal-tab" data-bs-toggle="tab" data-bs-target="#fiscal" type="button" role="tab">
            <i class="bi bi-file-earmark-text"></i> Fiscali
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="banking-tab" data-bs-toggle="tab" data-bs-target="#banking" type="button" role="tab">
            <i class="bi bi-bank"></i> Banca
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="paypal-tab" data-bs-toggle="tab" data-bs-target="#paypal" type="button" role="tab">
            <i class="bi bi-paypal"></i> PayPal
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="api-tab" data-bs-toggle="tab" data-bs-target="#api" type="button" role="tab">
            <i class="bi bi-plug"></i> API
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="email-tab" data-bs-toggle="tab" data-bs-target="#email" type="button" role="tab">
            <i class="bi bi-envelope"></i> Email
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab">
            <i class="bi bi-shield-lock"></i> Sicurezza
        </button>
    </li>
</ul>

<!-- Tab Content -->
<div class="tab-content" id="settingsTabContent">

<form method="POST" enctype="multipart/form-data">
    
    <!-- Association Tab -->
    <div class="tab-pane fade show active" id="association" role="tabpanel">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="association_name" class="form-label">Nome Associazione *</label>
                        <input type="text" class="form-control" id="association_name" name="association_name" 
                               value="<?php echo h($currentSettings['association_name'] ?? ''); ?>" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="association_full_name" class="form-label">Ragione Sociale Completa</label>
                        <input type="text" class="form-control" id="association_full_name" name="association_full_name" 
                               value="<?php echo h($currentSettings['association_full_name'] ?? ''); ?>">
                    </div>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="association_slogan" class="form-label">Slogan/Motto</label>
                <input type="text" class="form-control" id="association_slogan" name="association_slogan" 
                       value="<?php echo h($currentSettings['association_slogan'] ?? ''); ?>">
            </div>
            
            <div class="mb-3">
                <label for="association_logo" class="form-label">Logo</label>
                <input type="file" class="form-control" id="association_logo" name="association_logo" accept="image/*">
                <?php if (!empty($currentSettings['association_logo'])): ?>
                    <div class="mt-2">
                        <img src="<?php echo h($currentSettings['association_logo']); ?>" alt="Logo" style="max-height: 100px;">
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Legal Representative Tab -->
        <div class="tab-pane fade" id="legal" role="tabpanel">
            <div class="row">
                <div class="col-md-8">
                    <div class="mb-3">
                        <label for="legal_representative_name" class="form-label">Nome e Cognome</label>
                        <input type="text" class="form-control" id="legal_representative_name" name="legal_representative_name" 
                               value="<?php echo h($currentSettings['legal_representative_name'] ?? ''); ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="legal_representative_role" class="form-label">Ruolo/Carica</label>
                        <input type="text" class="form-control" id="legal_representative_role" name="legal_representative_role" 
                               value="<?php echo h($currentSettings['legal_representative_role'] ?? ''); ?>" 
                               placeholder="es. Presidente">
                    </div>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="legal_representative_cf" class="form-label">Codice Fiscale</label>
                <input type="text" class="form-control" id="legal_representative_cf" name="legal_representative_cf" 
                       value="<?php echo h($currentSettings['legal_representative_cf'] ?? ''); ?>" 
                       maxlength="16" pattern="[A-Z0-9]{16}">
            </div>
        </div>
        
        <!-- Address & Contacts Tab -->
        <div class="tab-pane fade" id="address" role="tabpanel">
            <h5 class="mb-3">Sede Legale</h5>
            <div class="mb-3">
                <label for="address_street" class="form-label">Indirizzo</label>
                <input type="text" class="form-control" id="address_street" name="address_street" 
                       value="<?php echo h($currentSettings['address_street'] ?? ''); ?>">
            </div>
            
            <div class="row">
                <div class="col-md-3">
                    <div class="mb-3">
                        <label for="address_cap" class="form-label">CAP</label>
                        <input type="text" class="form-control" id="address_cap" name="address_cap" 
                               value="<?php echo h($currentSettings['address_cap'] ?? ''); ?>" 
                               maxlength="5" pattern="[0-9]{5}">
                    </div>
                </div>
                <div class="col-md-7">
                    <div class="mb-3">
                        <label for="address_city" class="form-label">Città</label>
                        <input type="text" class="form-control" id="address_city" name="address_city" 
                               value="<?php echo h($currentSettings['address_city'] ?? ''); ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="mb-3">
                        <label for="address_province" class="form-label">Provincia</label>
                        <input type="text" class="form-control" id="address_province" name="address_province" 
                               value="<?php echo h($currentSettings['address_province'] ?? ''); ?>" 
                               maxlength="2" pattern="[A-Z]{2}">
                    </div>
                </div>
            </div>
            
            <hr class="my-4">
            <h5 class="mb-3">Contatti</h5>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="contact_phone" class="form-label">Telefono</label>
                        <input type="tel" class="form-control" id="contact_phone" name="contact_phone" 
                               value="<?php echo h($currentSettings['contact_phone'] ?? ''); ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="contact_email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="contact_email" name="contact_email" 
                               value="<?php echo h($currentSettings['contact_email'] ?? ''); ?>">
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="contact_pec" class="form-label">PEC</label>
                        <input type="email" class="form-control" id="contact_pec" name="contact_pec" 
                               value="<?php echo h($currentSettings['contact_pec'] ?? ''); ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="contact_website" class="form-label">Sito Web</label>
                        <input type="url" class="form-control" id="contact_website" name="contact_website" 
                               value="<?php echo h($currentSettings['contact_website'] ?? ''); ?>" 
                               placeholder="https://www.esempio.it">
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Fiscal Data Tab -->
        <div class="tab-pane fade" id="fiscal" role="tabpanel">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="fiscal_piva" class="form-label">Partita IVA</label>
                        <input type="text" class="form-control" id="fiscal_piva" name="fiscal_piva" 
                               value="<?php echo h($currentSettings['fiscal_piva'] ?? ''); ?>" 
                               maxlength="11" pattern="[0-9]{11}">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="fiscal_cf" class="form-label">Codice Fiscale Associazione</label>
                        <input type="text" class="form-control" id="fiscal_cf" name="fiscal_cf" 
                               value="<?php echo h($currentSettings['fiscal_cf'] ?? ''); ?>" 
                               maxlength="16" pattern="[A-Z0-9]{11,16}">
                    </div>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="fiscal_rea" class="form-label">Numero REA</label>
                <input type="text" class="form-control" id="fiscal_rea" name="fiscal_rea" 
                       value="<?php echo h($currentSettings['fiscal_rea'] ?? ''); ?>">
            </div>
            
            <div class="mb-3">
                <label for="fiscal_registry" class="form-label">Registro APS/ETS</label>
                <input type="text" class="form-control" id="fiscal_registry" name="fiscal_registry" 
                       value="<?php echo h($currentSettings['fiscal_registry'] ?? ''); ?>" 
                       placeholder="es. Iscritta al RUNTS n. 12345">
                <div class="form-text">Informazioni sull'iscrizione al RUNTS o altri registri</div>
            </div>
        </div>
        
        <!-- Banking Tab -->
        <div class="tab-pane fade" id="banking" role="tabpanel">
            <div class="mb-3">
                <label for="bank_iban" class="form-label">IBAN</label>
                <input type="text" class="form-control" id="bank_iban" name="bank_iban" 
                       value="<?php echo h($currentSettings['bank_iban'] ?? ''); ?>" 
                       maxlength="27" pattern="IT[0-9]{2}[A-Z][0-9]{10}[0-9A-Z]{12}">
                <div class="form-text">Formato: IT00A0000000000000000000000</div>
            </div>
            
            <div class="mb-3">
                <label for="bank_holder" class="form-label">Intestatario Conto</label>
                <input type="text" class="form-control" id="bank_holder" name="bank_holder" 
                       value="<?php echo h($currentSettings['bank_holder'] ?? ''); ?>">
            </div>
            
            <div class="row">
                <div class="col-md-8">
                    <div class="mb-3">
                        <label for="bank_name" class="form-label">Banca/Istituto</label>
                        <input type="text" class="form-control" id="bank_name" name="bank_name" 
                               value="<?php echo h($currentSettings['bank_name'] ?? ''); ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="bank_bic" class="form-label">BIC/SWIFT</label>
                        <input type="text" class="form-control" id="bank_bic" name="bank_bic" 
                               value="<?php echo h($currentSettings['bank_bic'] ?? ''); ?>" 
                               maxlength="11">
                    </div>
                </div>
            </div>
        </div>
        
        <!-- PayPal Tab -->
        <div class="tab-pane fade" id="paypal" role="tabpanel">
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> Configura PayPal per ricevere donazioni o pagamenti online
            </div>
            
            <div class="mb-3">
                <label for="paypal_email" class="form-label">Email PayPal</label>
                <input type="email" class="form-control" id="paypal_email" name="paypal_email" 
                       value="<?php echo h($currentSettings['paypal_email'] ?? ''); ?>">
            </div>
            
            <div class="mb-3">
                <label for="paypal_me_link" class="form-label">Link PayPal.Me</label>
                <input type="url" class="form-control" id="paypal_me_link" name="paypal_me_link" 
                       value="<?php echo h($currentSettings['paypal_me_link'] ?? ''); ?>" 
                       placeholder="https://paypal.me/nomeassociazione">
            </div>
        </div>
        
        <!-- Save Button for General Settings -->
        <div class="mt-4 pt-3 border-top" id="general-save-button">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="bi bi-save"></i> Salva Impostazioni
            </button>
        </div>
    </form>
    
        <!-- API / Integrations Tab -->
        <div class="tab-pane fade" id="api" role="tabpanel">
            <form method="POST" action="settings.php">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="action" value="save_api_settings">
                
                <!-- reCAPTCHA -->
                <h5><i class="bi bi-robot"></i> Google reCAPTCHA v2</h5>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Protegge il form di login da bot e accessi automatizzati
                </div>
                <div class="mb-3">
                    <label class="form-label">reCAPTCHA Site Key</label>
                    <input type="text" class="form-control" name="recaptcha_site_key" 
                           value="<?php echo h(getSetting('recaptcha_site_key', '')); ?>"
                           placeholder="6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI">
                    <div class="form-text">Chiave pubblica per il client-side</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">reCAPTCHA Secret Key</label>
                    <input type="password" class="form-control" name="recaptcha_secret_key" 
                           value="<?php echo h(getSetting('recaptcha_secret_key', '')); ?>"
                           placeholder="6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe">
                    <div class="form-text">Chiave privata per la verifica server-side</div>
                </div>
                <div class="alert alert-secondary mb-4">
                    <strong><i class="bi bi-link-45deg"></i> Ottieni le chiavi:</strong><br>
                    <a href="https://www.google.com/recaptcha/admin" target="_blank" class="btn btn-sm btn-outline-primary mt-2">
                        <i class="bi bi-box-arrow-up-right"></i> Google reCAPTCHA Admin Console
                    </a>
                </div>
                
                <hr class="my-4">
                
                <!-- TinyMCE -->
                <h5><i class="bi bi-pencil-square"></i> TinyMCE Editor</h5>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Editor WYSIWYG per la creazione e modifica di contenuti (news, comunicazioni, ecc.)
                </div>
                <div class="mb-3">
                    <label class="form-label">TinyMCE API Key</label>
                    <input type="text" class="form-control" name="tinymce_api_key" 
                           value="<?php echo h(getSetting('tinymce_api_key', '')); ?>"
                           placeholder="Ottieni gratis da tiny.cloud">
                    <div class="form-text">
                        <a href="https://www.tiny.cloud/get-tiny/" target="_blank">
                            <i class="bi bi-box-arrow-up-right"></i> Registrati su tiny.cloud per ottenere una API key gratuita
                        </a>
                    </div>
                </div>
                
                <hr class="my-4">
                
                <h5>ImgBB (Upload Immagini)</h5>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Necessario per permettere ai soci di caricare le fototessere dal portale
                </div>
                <div class="mb-3">
                    <label class="form-label">API Key ImgBB</label>
                    <input type="text" class="form-control" name="imgbb_api_key" 
                           value="<?php echo h(getSetting('imgbb_api_key', '')); ?>"
                           placeholder="Ottieni la chiave da api.imgbb.com">
                    <div class="form-text">Necessaria per l'upload delle fototessere dei soci nel portale</div>
                </div>
                
                <hr class="my-4">
                <h5>PayPal (Pagamenti Online)</h5>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Configurazione avanzata per integrare pagamenti PayPal nel portale soci
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Modalità</label>
                    <select class="form-select" name="paypal_mode">
                        <option value="sandbox" <?php echo (getSetting('paypal_mode', '') === 'sandbox') ? 'selected' : ''; ?>>Sandbox (Test)</option>
                        <option value="live" <?php echo (getSetting('paypal_mode', '') === 'live') ? 'selected' : ''; ?>>Live (Produzione)</option>
                    </select>
                    <div class="form-text">Usa Sandbox per test, Live per pagamenti reali</div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Client ID</label>
                    <input type="text" class="form-control" name="paypal_client_id" 
                           value="<?php echo h(getSetting('paypal_client_id', '')); ?>"
                           placeholder="Client ID da PayPal Developer">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Client Secret</label>
                    <input type="password" class="form-control" name="paypal_client_secret" 
                           value="<?php echo h(getSetting('paypal_client_secret', '')); ?>"
                           placeholder="Client Secret da PayPal Developer">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Webhook ID</label>
                    <input type="text" class="form-control" name="paypal_webhook_id" 
                           value="<?php echo h(getSetting('paypal_webhook_id', '')); ?>"
                           placeholder="ID del webhook configurato su PayPal">
                    <div class="form-text">ID del webhook configurato su PayPal Developer per ricevere notifiche di pagamento</div>
                </div>
                
                <hr class="my-4">
                
                <h5 class="mt-4"><i class="bi bi-shield-lock"></i> Token Cron</h5>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Token di sicurezza per autenticare chiamate cron esterne (necessario solo se usi la modalità "Coda + Cron")
                </div>
                <div class="mb-3">
                    <label class="form-label">Token sicurezza per cron esterno</label>
                    <div class="input-group">
                        <input type="text" class="form-control" name="cron_token" 
                               value="<?php echo h(getSetting('cron_token', '')); ?>"
                               placeholder="Token segreto per autenticare chiamate cron">
                        <button type="button" class="btn btn-outline-secondary" onclick="generateToken()">
                            <i class="bi bi-shuffle"></i> Genera
                        </button>
                    </div>
                    <div class="form-text">
                        Necessario solo se usi la modalità "Coda + Cron" nelle impostazioni email. 
                        <a href="admin_email_queue.php">Gestisci coda email</a>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-save"></i> Salva Impostazioni API
                </button>
            </form>
        </div>
        
    <form method="POST" enctype="multipart/form-data">
        <!-- Email Customization Tab -->
        <div class="tab-pane fade" id="email" role="tabpanel">
            <div class="mb-3">
                <label for="email_signature" class="form-label">Firma Email</label>
                <textarea class="form-control" id="email_signature" name="email_signature" rows="3"><?php echo h($currentSettings['email_signature'] ?? ''); ?></textarea>
                <div class="form-text">Testo che appare in fondo alle email (es. "Cordiali saluti, Il Team")</div>
            </div>
            
            <div class="mb-3">
                <label for="email_footer" class="form-label">Footer Email (Informazioni Legali)</label>
                <textarea class="form-control" id="email_footer" name="email_footer" rows="4"><?php echo h($currentSettings['email_footer'] ?? ''); ?></textarea>
                <div class="form-text">Informazioni legali o disclaimer da includere in tutte le email</div>
            </div>
            
            <div class="alert alert-secondary">
                <strong>Anteprima Footer:</strong>
                <div class="mt-2 p-3 bg-white border">
                    <?php echo getEmailFooter(); ?>
                </div>
            </div>
            
            <hr class="my-4">
            
            <!-- Sezione SMTP -->
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="bi bi-envelope-at"></i> Configurazione Email / SMTP</h5>
                </div>
                <div class="card-body">
                    
                    <!-- Toggle SMTP -->
                    <div class="mb-3">
                        <label class="form-label">Metodo di invio email</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="smtp_enabled" id="smtp_disabled" value="0" 
                                   <?php echo getSetting('smtp_enabled') != '1' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="smtp_disabled">
                                <strong>PHP mail() nativo</strong> - Usa il server mail di sistema (default)
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="smtp_enabled" id="smtp_enabled_yes" value="1"
                                   <?php echo getSetting('smtp_enabled') == '1' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="smtp_enabled_yes">
                                <strong>SMTP esterno</strong> - Usa un server SMTP esterno (Gmail, Libero, ecc.)
                            </label>
                        </div>
                    </div>
                    
                    <!-- Preset rapidi -->
                    <div class="mb-3" id="smtp_presets" style="display: none;">
                        <label class="form-label">Configurazione Rapida</label>
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-outline-danger" onclick="setSmtpPreset('gmail')">
                                <i class="bi bi-google"></i> Gmail
                            </button>
                            <button type="button" class="btn btn-outline-primary" onclick="setSmtpPreset('libero')">
                                📧 Libero
                            </button>
                            <button type="button" class="btn btn-outline-info" onclick="setSmtpPreset('virgilio')">
                                📧 Virgilio
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="setSmtpPreset('mailcom')">
                                📧 Mail.com
                            </button>
                            <button type="button" class="btn btn-outline-dark" onclick="setSmtpPreset('custom')">
                                ⚙️ Altro
                            </button>
                        </div>
                    </div>
                    
                    <!-- Campi SMTP -->
                    <div id="smtp_fields" style="display: none;">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label class="form-label">Server SMTP</label>
                                    <input type="text" class="form-control" name="smtp_host" id="smtp_host"
                                           value="<?php echo h(getSetting('smtp_host')); ?>" placeholder="smtp.gmail.com">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Porta</label>
                                    <input type="number" class="form-control" name="smtp_port" id="smtp_port"
                                           value="<?php echo h(getSetting('smtp_port') ?: '587'); ?>" placeholder="587">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Sicurezza</label>
                            <select class="form-select" name="smtp_security" id="smtp_security">
                                <option value="none" <?php echo getSetting('smtp_security') == 'none' ? 'selected' : ''; ?>>Nessuna</option>
                                <option value="ssl" <?php echo getSetting('smtp_security') == 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                <option value="tls" <?php echo getSetting('smtp_security') == 'tls' || !getSetting('smtp_security') ? 'selected' : ''; ?>>TLS (consigliato)</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Username (email)</label>
                            <input type="email" class="form-control" name="smtp_username" id="smtp_username"
                                   value="<?php echo h(getSetting('smtp_username')); ?>" placeholder="tuaemail@gmail.com">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" name="smtp_password" id="smtp_password"
                                       value="<?php echo h(getSetting('smtp_password')); ?>" placeholder="••••••••">
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('smtp_password')">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="form-text" id="smtp_password_help"></div>
                        </div>
                        
                        <hr>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Email Mittente</label>
                                    <input type="email" class="form-control" name="smtp_from_email"
                                           value="<?php echo h(getSetting('smtp_from_email')); ?>" placeholder="noreply@tuaassociazione.it">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Nome Mittente</label>
                                    <input type="text" class="form-control" name="smtp_from_name"
                                           value="<?php echo h(getSetting('smtp_from_name')); ?>" placeholder="Associazione XYZ">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Modalità Invio Email -->
                        <h5 class="mt-4"><i class="bi bi-gear"></i> Modalità Invio</h5>
                        <div class="mb-3">
                            <label class="form-label">Modalità invio email</label>
                            <select name="email_send_mode" class="form-select">
                                <option value="direct" <?php echo getSetting('email_send_mode', 'direct') === 'direct' ? 'selected' : ''; ?>>
                                    ⚡ Invio Diretto - Email inviate subito (consigliato per pochi soci)
                                </option>
                                <option value="queue" <?php echo getSetting('email_send_mode', 'direct') === 'queue' ? 'selected' : ''; ?>>
                                    🕐 Coda + Cron - Email accodate, serve cron esterno (per tanti soci)
                                </option>
                            </select>
                            <div class="form-text">
                                Con "Invio Diretto" le email partono subito. Con "Coda + Cron" vengono accodate e processate da un cron job esterno.
                            </div>
                        </div>
                        
                        <!-- Alert informativi per provider -->
                        <div class="alert alert-info" id="smtp_help_gmail" style="display: none;">
                            <h6><i class="bi bi-info-circle"></i> Configurazione Gmail</h6>
                            <p class="mb-1">Per Gmail devi usare una <strong>"Password per le app"</strong>, non la password normale.</p>
                            <ol class="mb-0">
                                <li>Attiva la <a href="https://myaccount.google.com/security" target="_blank">Verifica in 2 passaggi</a></li>
                                <li>Vai su <a href="https://myaccount.google.com/apppasswords" target="_blank">Password per le app</a></li>
                                <li>Crea una nuova password per "Posta"</li>
                                <li>Usa quella password qui</li>
                            </ol>
                        </div>
                        
                        <div class="alert alert-info" id="smtp_help_libero" style="display: none;">
                            <h6><i class="bi bi-info-circle"></i> Configurazione Libero/Virgilio</h6>
                            <p class="mb-0">Usa le stesse credenziali che usi per accedere alla webmail. Assicurati che l'accesso SMTP sia abilitato nelle impostazioni del tuo account.</p>
                        </div>
                        
                        <div class="alert alert-warning" id="smtp_help_custom" style="display: none;">
                            <h6><i class="bi bi-exclamation-triangle"></i> Configurazione Personalizzata</h6>
                            <p class="mb-0">Inserisci i dati SMTP forniti dal tuo provider email. Controlla la documentazione del provider per i valori corretti.</p>
                        </div>
                        
                        <!-- Pulsante Test -->
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-primary" onclick="testSmtpConnection()">
                                <i class="bi bi-envelope-check"></i> Invia Email di Test
                            </button>
                        </div>
                    </div>
                    
                </div>
            </div>
        </div>
        
        <!-- Security Tab -->
        <div class="tab-pane fade" id="security" role="tabpanel">
            <div class="alert alert-info">
                <i class="bi bi-shield-lock"></i> Configura le impostazioni di sicurezza avanzate per proteggere l'accesso al sistema
            </div>
            
            <!-- Google reCAPTCHA v2 -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-robot"></i> Google reCAPTCHA v2</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="recaptcha_enabled" id="recaptcha_enabled" value="1"
                                   <?php echo (getSetting('recaptcha_enabled') == '1') ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="recaptcha_enabled">
                                <strong>Abilita reCAPTCHA per il login</strong>
                            </label>
                        </div>
                        <div class="form-text">Protegge il form di login da bot e accessi automatizzati</div>
                    </div>
                    
                    <div id="recaptcha_fields">
                        <div class="mb-3">
                            <label class="form-label">Site Key (Chiave Sito)</label>
                            <input type="text" class="form-control" name="recaptcha_site_key" 
                                   value="<?php echo h(getSetting('recaptcha_site_key')); ?>"
                                   placeholder="6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI">
                            <div class="form-text">Chiave pubblica per il client-side</div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Secret Key (Chiave Segreta)</label>
                            <input type="password" class="form-control" name="recaptcha_secret_key" 
                                   value="<?php echo h(getSetting('recaptcha_secret_key')); ?>"
                                   placeholder="6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe">
                            <div class="form-text">Chiave privata per la verifica server-side</div>
                        </div>
                        
                        <div class="alert alert-secondary">
                            <strong><i class="bi bi-link-45deg"></i> Ottieni le chiavi:</strong><br>
                            <a href="https://www.google.com/recaptcha/admin" target="_blank" class="btn btn-sm btn-outline-primary mt-2">
                                <i class="bi bi-box-arrow-up-right"></i> Google reCAPTCHA Admin Console
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 2FA Google Authenticator -->
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-phone"></i> Autenticazione a Due Fattori (2FA)</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="2fa_enabled" id="2fa_enabled" value="1"
                                   <?php echo (getSetting('2fa_enabled') == '1') ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="2fa_enabled">
                                <strong>Abilita 2FA con Google Authenticator</strong>
                            </label>
                        </div>
                        <div class="form-text">Richiede un secondo fattore di autenticazione tramite app TOTP</div>
                    </div>
                    
                    <div id="2fa_fields">
                        <div class="mb-3">
                            <label class="form-label">2FA Obbligatorio Per</label>
                            <select class="form-select" name="2fa_required_for">
                                <option value="none" <?php echo (getSetting('2fa_required_for') == 'none') ? 'selected' : ''; ?>>
                                    Nessuno (opzionale per tutti)
                                </option>
                                <option value="admin" <?php echo (getSetting('2fa_required_for') == 'admin') ? 'selected' : ''; ?>>
                                    Solo Amministratori
                                </option>
                                <option value="staff" <?php echo (getSetting('2fa_required_for') == 'staff') ? 'selected' : ''; ?>>
                                    Admin e Staff (operatori)
                                </option>
                                <option value="all" <?php echo (getSetting('2fa_required_for') == 'all') ? 'selected' : ''; ?>>
                                    Tutti gli utenti
                                </option>
                            </select>
                            <div class="form-text">Definisci per chi la 2FA è obbligatoria</div>
                        </div>
                        
                        <div class="alert alert-info">
                            <strong><i class="bi bi-info-circle"></i> Come funziona:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Gli utenti configurano Google Authenticator dal loro profilo</li>
                                <li>Al login viene richiesto il codice OTP a 6 cifre</li>
                                <li>Compatibile con Google Authenticator, Authy, Microsoft Authenticator</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Password Expiry -->
            <div class="card mb-4">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="bi bi-key"></i> Scadenza Password</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-secondary">
                        <i class="bi bi-info-circle"></i> Imposta dopo quanti giorni le password devono essere cambiate. Usa 0 per disabilitare.
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Scadenza per Utenti (Admin/Operatori)</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" name="password_expiry_users" min="0" 
                                           value="<?php echo h(getSetting('password_expiry_users') ?: '0'); ?>"
                                           placeholder="0">
                                    <span class="input-group-text">giorni</span>
                                </div>
                                <div class="form-text">0 = disabilitato (consigliato: 90-180 giorni)</div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Scadenza per Soci (Portale)</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" name="password_expiry_members" min="0" 
                                           value="<?php echo h(getSetting('password_expiry_members') ?: '0'); ?>"
                                           placeholder="0">
                                    <span class="input-group-text">giorni</span>
                                </div>
                                <div class="form-text">0 = disabilitato (consigliato: 180-365 giorni)</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-warning">
                        <strong><i class="bi bi-exclamation-triangle"></i> Nota:</strong>
                        Gli utenti riceveranno una notifica quando la password sta per scadere e saranno obbligati a cambiarla alla scadenza.
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Save Button for Email and Security Settings -->
        <div class="mt-4 pt-3 border-top" id="email-security-save-button">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="bi bi-save"></i> Salva Impostazioni
            </button>
        </div>
    </form>
    
</div><!-- End of tab-content -->

<script>
// Preset SMTP per provider comuni
const smtpPresets = {
    gmail: {
        host: 'smtp.gmail.com',
        port: 587,
        security: 'tls',
        help: 'gmail'
    },
    libero: {
        host: 'smtp.libero.it',
        port: 465,
        security: 'ssl',
        help: 'libero'
    },
    virgilio: {
        host: 'out.virgilio.it',
        port: 465,
        security: 'ssl',
        help: 'libero'
    },
    mailcom: {
        host: 'smtp.mail.com',
        port: 587,
        security: 'tls',
        help: 'custom'
    },
    custom: {
        host: '',
        port: 587,
        security: 'tls',
        help: 'custom'
    }
};

function setSmtpPreset(provider) {
    const preset = smtpPresets[provider];
    document.getElementById('smtp_host').value = preset.host;
    document.getElementById('smtp_port').value = preset.port;
    document.getElementById('smtp_security').value = preset.security;
    
    // Nascondi tutti gli help
    document.querySelectorAll('[id^="smtp_help_"]').forEach(el => el.style.display = 'none');
    // Mostra help corretto
    document.getElementById('smtp_help_' + preset.help).style.display = 'block';
    
    // Help password per Gmail
    if (provider === 'gmail') {
        document.getElementById('smtp_password_help').innerHTML = 
            '<a href="https://myaccount.google.com/apppasswords" target="_blank">Genera password per le app</a>';
    } else {
        document.getElementById('smtp_password_help').innerHTML = '';
    }
}

// Mostra/nascondi campi SMTP in base al toggle
document.querySelectorAll('input[name="smtp_enabled"]').forEach(radio => {
    radio.addEventListener('change', function() {
        document.getElementById('smtp_fields').style.display = this.value === '1' ? 'block' : 'none';
        document.getElementById('smtp_presets').style.display = this.value === '1' ? 'block' : 'none';
    });
});

// Inizializza visibilità
document.addEventListener('DOMContentLoaded', function() {
    const smtpEnabled = document.querySelector('input[name="smtp_enabled"]:checked');
    if (smtpEnabled && smtpEnabled.value === '1') {
        document.getElementById('smtp_fields').style.display = 'block';
        document.getElementById('smtp_presets').style.display = 'block';
    }
    
    // Initialize reCAPTCHA fields visibility
    const recaptchaCheckbox = document.getElementById('recaptcha_enabled');
    const recaptchaFields = document.getElementById('recaptcha_fields');
    if (recaptchaCheckbox) {
        recaptchaFields.style.display = recaptchaCheckbox.checked ? 'block' : 'none';
        recaptchaCheckbox.addEventListener('change', function() {
            recaptchaFields.style.display = this.checked ? 'block' : 'none';
        });
    }
    
    // Initialize 2FA fields visibility
    const twoFaCheckbox = document.getElementById('2fa_enabled');
    const twoFaFields = document.getElementById('2fa_fields');
    if (twoFaCheckbox) {
        twoFaFields.style.display = twoFaCheckbox.checked ? 'block' : 'none';
        twoFaCheckbox.addEventListener('change', function() {
            twoFaFields.style.display = this.checked ? 'block' : 'none';
        });
    }
    
    // Handle tab switching to show correct save button
    const saveButtonGeneral = document.getElementById('general-save-button');
    const saveButtonEmailSecurity = document.getElementById('email-security-save-button');
    
    // Function to update save button visibility
    function updateSaveButtonVisibility() {
        const activeTab = document.querySelector('.tab-pane.active');
        if (!activeTab) return;
        
        const tabId = activeTab.getAttribute('id');
        if (!tabId) return;
        
        // Hide all save buttons initially
        if (saveButtonGeneral) saveButtonGeneral.style.display = 'none';
        if (saveButtonEmailSecurity) saveButtonEmailSecurity.style.display = 'none';
        
        // Show appropriate save button based on active tab
        if (tabId === 'email' || tabId === 'security') {
            if (saveButtonEmailSecurity) saveButtonEmailSecurity.style.display = 'block';
        } else if (tabId !== 'api') {
            // All tabs except API and Email/Security use the general save button
            if (saveButtonGeneral) saveButtonGeneral.style.display = 'block';
        }
        // API tab has its own save button within the form, so we don't need to show any external button
    }
    
    // Initialize on page load
    updateSaveButtonVisibility();
    
    // Update on tab change
    document.querySelectorAll('button[data-bs-toggle="tab"]').forEach(button => {
        button.addEventListener('shown.bs.tab', updateSaveButtonVisibility);
    });
    
    // Handle URL hash to show the correct tab on page load
    const urlParams = new URLSearchParams(window.location.search);
    const tab = urlParams.get('tab');
    // Whitelist of allowed tab names to prevent XSS
    const allowedTabs = ['association', 'legal', 'address', 'fiscal', 'banking', 'paypal', 'api', 'email', 'security'];
    if (tab && allowedTabs.includes(tab)) {
        const tabButton = document.getElementById(tab + '-tab');
        if (tabButton && typeof bootstrap !== 'undefined') {
            const bsTab = new bootstrap.Tab(tabButton);
            bsTab.show();
        }
    }
});

function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    field.type = field.type === 'password' ? 'text' : 'password';
}

function testSmtpConnection() {
    // Salva prima le impostazioni, poi invia email di test
    const form = document.querySelector('form');
    const formData = new FormData(form);
    formData.append('action', 'test_smtp');
    
    fetch('settings.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Email di test inviata con successo!\nControlla la tua casella di posta.');
        } else {
            alert('❌ Errore: ' + data.message);
        }
    })
    .catch(error => {
        alert('❌ Errore di connessione: ' + error);
    });
}

function generateToken() {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    let token = '';
    for (let i = 0; i < 32; i++) {
        token += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.querySelector('input[name="cron_token"]').value = token;
}
</script>

<?php include __DIR__ . '/inc/footer.php'; ?>
