<?php
/**
 * Email Templates Management
 * Gestione template email (solo admin)
 */

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/audit.php';

requireAdmin();

$config = require __DIR__ . '/../src/config.php';
$pageTitle = 'Template Email';
$action = $_GET['action'] ?? 'list';
$templateId = $_GET['id'] ?? null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    
    if ($action === 'edit' && $templateId) {
        $subject = trim($_POST['subject'] ?? '');
        $bodyHtml = trim($_POST['body_html'] ?? '');
        $bodyText = trim($_POST['body_text'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($subject) || empty($bodyHtml)) {
            setFlash('Oggetto e corpo HTML sono obbligatori', 'danger');
        } else {
            // Recupera template originale per audit
            $stmt = $pdo->prepare("SELECT * FROM " . table('email_templates') . " WHERE id = ?");
            $stmt->execute([$templateId]);
            $oldTemplate = $stmt->fetch();
            
            $stmt = $pdo->prepare("
                UPDATE " . table('email_templates') . "
                SET subject = ?, body_html = ?, body_text = ?, is_active = ?
                WHERE id = ?
            ");
            
            if ($stmt->execute([$subject, $bodyHtml, $bodyText, $isActive, $templateId])) {
                logUpdate('email_template', $templateId, $oldTemplate['name'], 
                    ['subject' => $oldTemplate['subject'], 'is_active' => $oldTemplate['is_active']],
                    ['subject' => $subject, 'is_active' => $isActive]
                );
                setFlash('Template aggiornato con successo');
                header('Location: ' . $config['app']['base_path'] . 'admin_email_templates.php');
                exit;
            } else {
                setFlash('Errore durante l\'aggiornamento', 'danger');
            }
        }
    }
}

// Get template for editing
$template = null;
if ($action === 'edit' && $templateId) {
    $stmt = $pdo->prepare("SELECT * FROM " . table('email_templates') . " WHERE id = ?");
    $stmt->execute([$templateId]);
    $template = $stmt->fetch();
    
    if (!$template) {
        setFlash('Template non trovato', 'danger');
        header('Location: ' . $config['app']['base_path'] . 'admin_email_templates.php');
        exit;
    }
}

// Get all templates for list
$templates = [];
if ($action === 'list') {
    $stmt = $pdo->query("SELECT * FROM " . table('email_templates') . " ORDER BY name");
    $templates = $stmt->fetchAll();
}

// Preview template
$preview = null;
if ($action === 'preview' && $templateId) {
    $stmt = $pdo->prepare("SELECT * FROM " . table('email_templates') . " WHERE id = ?");
    $stmt->execute([$templateId]);
    $template = $stmt->fetch();
    
    if ($template) {
        // Dati di esempio per preview
        $sampleData = [
            'nome' => 'Mario',
            'cognome' => 'Rossi',
            'email' => 'mario.rossi@example.com',
            'numero_tessera' => 'SOC-001',
            'anno' => '2024/2025',
            'importo' => '50,00 €',
            'scadenza' => '31/12/2024',
            'data_pagamento' => '15/10/2024',
            'metodo_pagamento' => 'Bonifico',
            'link' => 'https://example.com/reset-password',
            'app_name' => $config['app']['name'] ?? 'Associazione',
            'subject' => 'Oggetto di esempio',
            'message' => 'Questo è un messaggio di esempio per la preview del template.'
        ];
        
        require_once __DIR__ . '/../src/email.php';
        $preview = [
            'subject' => replaceTemplateVariables($template['subject'], $sampleData),
            'body_html' => replaceTemplateVariables($template['body_html'], $sampleData),
            'body_text' => $template['body_text'] ? replaceTemplateVariables($template['body_text'], $sampleData) : null
        ];
    }
}

include __DIR__ . '/inc/header.php';
?>

<h1><i class="bi bi-envelope-paper"></i> Template Email</h1>

<?php if ($action === 'list'): ?>
    <div class="card">
        <div class="card-body">
            <p class="text-muted">
                Gestisci i template email utilizzati dal sistema per le notifiche automatiche.
                Puoi personalizzare oggetto e contenuto, usando le variabili indicate.
            </p>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Codice</th>
                            <th>Oggetto</th>
                            <th>Variabili</th>
                            <th>Stato</th>
                            <th>Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($templates as $tpl): ?>
                            <tr>
                                <td><strong><?php echo h($tpl['name']); ?></strong></td>
                                <td><code><?php echo h($tpl['code']); ?></code></td>
                                <td><?php echo h($tpl['subject']); ?></td>
                                <td>
                                    <?php 
                                    $vars = json_decode($tpl['variables'], true);
                                    if ($vars && is_array($vars)) {
                                        echo '<small class="text-muted">';
                                        foreach ($vars as $var) {
                                            echo '<span class="badge bg-secondary me-1">{' . h($var) . '}</span>';
                                        }
                                        echo '</small>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php if ($tpl['is_active']): ?>
                                        <span class="badge bg-success">Attivo</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Disattivo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="?action=preview&id=<?php echo $tpl['id']; ?>" 
                                       class="btn btn-sm btn-info" title="Preview">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="?action=edit&id=<?php echo $tpl['id']; ?>" 
                                       class="btn btn-sm btn-primary" title="Modifica">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php elseif ($action === 'edit' && $template): ?>
    <div class="mb-3">
        <a href="?action=list" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Torna alla Lista
        </a>
        <a href="?action=preview&id=<?php echo $template['id']; ?>" class="btn btn-info">
            <i class="bi bi-eye"></i> Preview
        </a>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Modifica Template: <?php echo h($template['name']); ?></h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <?php echo csrfField(); ?>
                
                <div class="alert alert-info">
                    <strong>Codice Template:</strong> <code><?php echo h($template['code']); ?></code><br>
                    <strong>Variabili disponibili:</strong>
                    <?php 
                    $vars = json_decode($template['variables'], true);
                    if ($vars && is_array($vars)) {
                        foreach ($vars as $var) {
                            echo '<span class="badge bg-secondary me-1">{' . h($var) . '}</span>';
                        }
                    }
                    ?>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Oggetto *</label>
                    <input type="text" class="form-control" name="subject" 
                           value="<?php echo h($template['subject']); ?>" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Corpo HTML *</label>
                    <textarea class="form-control" name="body_html" rows="12" required><?php echo h($template['body_html']); ?></textarea>
                    <small class="text-muted">Usa HTML per formattare il testo.</small>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Corpo Testo (opzionale)</label>
                    <textarea class="form-control" name="body_text" rows="8"><?php echo h($template['body_text'] ?? ''); ?></textarea>
                    <small class="text-muted">Versione testo semplice per client email che non supportano HTML.</small>
                </div>
                
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="is_active" name="is_active" 
                           <?php echo $template['is_active'] ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="is_active">Template attivo</label>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Salva Modifiche
                </button>
                <a href="?action=list" class="btn btn-secondary">Annulla</a>
            </form>
        </div>
    </div>

<?php elseif ($action === 'preview' && $preview): ?>
    <div class="mb-3">
        <a href="?action=list" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Torna alla Lista
        </a>
        <a href="?action=edit&id=<?php echo $templateId; ?>" class="btn btn-primary">
            <i class="bi bi-pencil"></i> Modifica Template
        </a>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Preview: <?php echo h($template['name']); ?></h5>
        </div>
        <div class="card-body">
            <div class="alert alert-warning">
                <i class="bi bi-info-circle"></i> 
                Questa è una preview con dati di esempio. I dati reali saranno sostituiti quando l'email viene inviata.
            </div>
            
            <div class="mb-4">
                <label class="form-label fw-bold">Oggetto:</label>
                <div class="p-2 bg-light border rounded">
                    <?php echo h($preview['subject']); ?>
                </div>
            </div>
            
            <div class="mb-4">
                <label class="form-label fw-bold">Corpo HTML:</label>
                <div class="p-3 bg-white border rounded">
                    <?php echo $preview['body_html']; ?>
                </div>
            </div>
            
            <?php if ($preview['body_text']): ?>
                <div class="mb-4">
                    <label class="form-label fw-bold">Corpo Testo:</label>
                    <pre class="p-3 bg-light border rounded"><?php echo h($preview['body_text']); ?></pre>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/inc/footer.php'; ?>
