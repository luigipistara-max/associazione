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

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="bi bi-gear"></i> Impostazioni Associazione</h1>
</div>

<form method="POST" enctype="multipart/form-data">
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
    </ul>

    <!-- Tab Content -->
    <div class="tab-content" id="settingsTabContent">
        
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
        
        <!-- API / Integrations Tab -->
        <div class="tab-pane fade" id="api" role="tabpanel">
            <h5>ImgBB (Upload Immagini)</h5>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> Necessario per permettere ai soci di caricare le fototessere dal portale
            </div>
            <div class="mb-3">
                <label class="form-label">API Key ImgBB</label>
                <input type="text" class="form-control" name="imgbb_api_key" 
                       value="<?php echo h($currentSettings['imgbb_api_key'] ?? ''); ?>"
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
                    <option value="sandbox" <?php echo ($currentSettings['paypal_mode'] ?? '') === 'sandbox' ? 'selected' : ''; ?>>Sandbox (Test)</option>
                    <option value="live" <?php echo ($currentSettings['paypal_mode'] ?? '') === 'live' ? 'selected' : ''; ?>>Live (Produzione)</option>
                </select>
                <div class="form-text">Usa Sandbox per test, Live per pagamenti reali</div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Client ID</label>
                <input type="text" class="form-control" name="paypal_client_id" 
                       value="<?php echo h($currentSettings['paypal_client_id'] ?? ''); ?>"
                       placeholder="Client ID da PayPal Developer">
            </div>
            
            <div class="mb-3">
                <label class="form-label">Client Secret</label>
                <input type="password" class="form-control" name="paypal_client_secret" 
                       value="<?php echo h($currentSettings['paypal_client_secret'] ?? ''); ?>"
                       placeholder="Client Secret da PayPal Developer">
            </div>
            
            <div class="mb-3">
                <label class="form-label">Webhook ID</label>
                <input type="text" class="form-control" name="paypal_webhook_id" 
                       value="<?php echo h($currentSettings['paypal_webhook_id'] ?? ''); ?>"
                       placeholder="ID del webhook configurato su PayPal">
                <div class="form-text">ID del webhook configurato su PayPal Developer per ricevere notifiche di pagamento</div>
            </div>
        </div>
        
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
        </div>
        
    </div>
    
    <!-- Save Button -->
    <div class="mt-4 pt-3 border-top">
        <button type="submit" class="btn btn-primary btn-lg">
            <i class="bi bi-save"></i> Salva Impostazioni
        </button>
    </div>
</form>

<?php include __DIR__ . '/inc/footer.php'; ?>
