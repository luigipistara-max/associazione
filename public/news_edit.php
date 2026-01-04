<?php
/**
 * News Edit Page (Admin)
 * Create and edit news/blog posts with WYSIWYG editor
 */

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/functions.php';

requireLogin();
requireAdmin();

$newsId = isset($_GET['id']) ? (int) $_GET['id'] : null;
$news = null;
$targetGroups = [];

if ($newsId) {
    $news = getNewsById($newsId);
    if (!$news) {
        setFlash('Notizia non trovata', 'danger');
        redirect('news.php');
    }
    $targetGroups = getNewsTargetGroups($newsId);
    $pageTitle = 'Modifica Notizia';
} else {
    $pageTitle = 'Nuova Notizia';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'title' => trim($_POST['title']),
        'content' => $_POST['content'], // TinyMCE will handle sanitization
        'excerpt' => trim($_POST['excerpt'] ?? ''),
        'cover_image' => trim($_POST['cover_image'] ?? ''),
        'author_id' => $_SESSION['user_id'],
        'target_type' => $_POST['target_type'] ?? 'all',
        'status' => $_POST['status'] ?? 'draft',
    ];
    
    // Handle group IDs for group-targeted news
    if ($data['target_type'] === 'groups') {
        $data['group_ids'] = $_POST['group_ids'] ?? [];
    }
    
    // Set published_at if status is published and not set
    if ($data['status'] === 'published' && isset($_POST['publish_now'])) {
        $data['published_at'] = date('Y-m-d H:i:s');
    } elseif (isset($_POST['published_date']) && isset($_POST['published_time'])) {
        $publishDate = $_POST['published_date'];
        $publishTime = $_POST['published_time'];
        if ($publishDate && $publishTime) {
            $data['published_at'] = $publishDate . ' ' . $publishTime . ':00';
        }
    } elseif ($news && $news['published_at']) {
        // Keep existing published_at
        $data['published_at'] = $news['published_at'];
    }
    
    try {
        $savedNewsId = saveNews($data, $newsId);
        
        // Send email notification if news was just published
        if ($data['status'] === 'published' && (!$news || $news['status'] !== 'published')) {
            $emailsSent = sendNewsNotification($savedNewsId);
            if ($emailsSent > 0) {
                setFlash(($newsId ? 'Notizia aggiornata' : 'Notizia creata') . " con successo e notificata a $emailsSent destinatari", 'success');
            } else {
                setFlash($newsId ? 'Notizia aggiornata con successo' : 'Notizia creata con successo', 'success');
            }
        } else {
            setFlash($newsId ? 'Notizia aggiornata con successo' : 'Notizia creata con successo', 'success');
        }
        
        redirect('news_edit.php?id=' . $savedNewsId);
    } catch (Exception $e) {
        setFlash('Errore nel salvataggio: ' . $e->getMessage(), 'danger');
    }
}

// Get all groups for target selection
$groups = getGroups();

include __DIR__ . '/inc/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="bi bi-newspaper"></i> <?php echo $newsId ? 'Modifica' : 'Nuova'; ?> Notizia
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="news.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Torna alla lista
        </a>
    </div>
</div>

<form method="POST" id="news-form">
    <div class="row">
        <div class="col-md-8">
            <!-- Title -->
            <div class="mb-3">
                <label for="title" class="form-label">Titolo *</label>
                <input type="text" class="form-control form-control-lg" id="title" name="title" 
                       value="<?php echo h($news['title'] ?? ''); ?>" required>
            </div>
            
            <!-- Excerpt -->
            <div class="mb-3">
                <label for="excerpt" class="form-label">Estratto (Anteprima)</label>
                <textarea class="form-control" id="excerpt" name="excerpt" rows="2"
                          placeholder="Breve descrizione che apparirà nell'elenco delle notizie..."><?php echo h($news['excerpt'] ?? ''); ?></textarea>
                <div class="form-text">Opzionale - Massimo 200 caratteri consigliati</div>
            </div>
            
            <!-- Content (TinyMCE) -->
            <div class="mb-3">
                <label for="content" class="form-label">Contenuto *</label>
                <textarea id="content" name="content" required><?php echo h($news['content'] ?? ''); ?></textarea>
            </div>
        </div>
        
        <div class="col-md-4">
            <!-- Status -->
            <div class="card mb-3">
                <div class="card-header">
                    <strong>Pubblicazione</strong>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Stato</label>
                        <select class="form-select" name="status" id="status">
                            <option value="draft" <?php echo ($news['status'] ?? '') === 'draft' ? 'selected' : ''; ?>>
                                Bozza
                            </option>
                            <option value="published" <?php echo ($news['status'] ?? '') === 'published' ? 'selected' : ''; ?>>
                                Pubblicata
                            </option>
                            <option value="archived" <?php echo ($news['status'] ?? '') === 'archived' ? 'selected' : ''; ?>>
                                Archiviata
                            </option>
                        </select>
                    </div>
                    
                    <div id="publish_options" style="display: none;">
                        <?php
                        $publishDate = '';
                        $publishTime = '';
                        if ($news && $news['published_at']) {
                            $publishDate = date('Y-m-d', strtotime($news['published_at']));
                            $publishTime = date('H:i', strtotime($news['published_at']));
                        }
                        ?>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="publish_now" id="publish_now" value="1"
                                   <?php echo !$publishDate ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="publish_now">
                                Pubblica ora
                            </label>
                        </div>
                        
                        <div id="schedule_fields" style="<?php echo $publishDate ? '' : 'display: none;'; ?>">
                            <label class="form-label">Data pubblicazione</label>
                            <input type="date" class="form-control mb-2" name="published_date" 
                                   value="<?php echo $publishDate; ?>">
                            <input type="time" class="form-control" name="published_time" 
                                   value="<?php echo $publishTime; ?>">
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Cover Image -->
            <div class="card mb-3">
                <div class="card-header">
                    <strong>Immagine di copertina</strong>
                </div>
                <div class="card-body">
                    <input type="url" class="form-control" name="cover_image" id="cover_image" 
                           value="<?php echo h($news['cover_image'] ?? ''); ?>"
                           placeholder="https://esempio.it/immagine.jpg">
                    <div class="form-text">URL dell'immagine di copertina</div>
                    
                    <?php if (!empty($news['cover_image'])): ?>
                        <div class="mt-2">
                            <img src="<?php echo h($news['cover_image']); ?>" 
                                 alt="Copertina" class="img-fluid" style="max-height: 150px;">
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Target Audience -->
            <div class="card mb-3">
                <div class="card-header">
                    <strong>Destinatari</strong>
                </div>
                <div class="card-body">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="target_type" id="target_all" 
                               value="all" <?php echo ($news['target_type'] ?? 'all') === 'all' ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="target_all">
                            Tutti i soci
                        </label>
                    </div>
                    
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="target_type" id="target_groups" 
                               value="groups" <?php echo ($news['target_type'] ?? '') === 'groups' ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="target_groups">
                            Gruppi specifici
                        </label>
                    </div>
                    
                    <div id="groups_selection" style="<?php echo ($news['target_type'] ?? '') === 'groups' ? '' : 'display: none;'; ?>">
                        <label class="form-label mt-2">Seleziona gruppi:</label>
                        <?php
                        $targetGroupIds = array_column($targetGroups, 'id');
                        foreach ($groups as $group):
                        ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="group_ids[]" 
                                       value="<?php echo $group['id']; ?>"
                                       id="group_<?php echo $group['id']; ?>"
                                       <?php echo in_array($group['id'], $targetGroupIds) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="group_<?php echo $group['id']; ?>">
                                    <?php echo h($group['name']); ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Save Button -->
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-save"></i> <?php echo $newsId ? 'Aggiorna' : 'Crea'; ?> Notizia
                </button>
            </div>
        </div>
    </div>
</form>

<!-- TinyMCE Editor -->
<?php $tinymceKey = getSetting('tinymce_api_key', ''); ?>
<?php if (!empty($tinymceKey)): ?>
<script src="https://cdn.tiny.cloud/1/<?php echo h($tinymceKey); ?>/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
<?php else: ?>
<!-- Fallback to CDN without API key (with watermark) -->
<script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
<?php endif; ?>
<script>
tinymce.init({
    selector: '#content',
    height: 500,
    menubar: true,
    plugins: [
        'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
        'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
        'insertdatetime', 'media', 'table', 'help', 'wordcount'
    ],
    toolbar: 'undo redo | blocks | bold italic forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | link image | code | help',
    content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; font-size: 14px; }',
    language: 'it',
    branding: false,
    promotion: false,
    setup: function(editor) {
        editor.on('change', function() {
            editor.save(); // Sincronizza automaticamente ad ogni modifica
        });
    }
});

// Force TinyMCE to save content before form submit
document.getElementById('news-form').addEventListener('submit', function(e) {
    // Forza TinyMCE a salvare il contenuto nel textarea
    if (typeof tinymce !== 'undefined') {
        tinymce.triggerSave();
    }
});

// Toggle publish options
document.getElementById('status').addEventListener('change', function() {
    document.getElementById('publish_options').style.display = 
        this.value === 'published' ? 'block' : 'none';
});

// Initialize publish options visibility
if (document.getElementById('status').value === 'published') {
    document.getElementById('publish_options').style.display = 'block';
}

// Toggle schedule fields
document.getElementById('publish_now').addEventListener('change', function() {
    document.getElementById('schedule_fields').style.display = 
        this.checked ? 'none' : 'block';
});

// Toggle groups selection
document.querySelectorAll('input[name="target_type"]').forEach(radio => {
    radio.addEventListener('change', function() {
        document.getElementById('groups_selection').style.display = 
            this.value === 'groups' ? 'block' : 'none';
    });
});
</script>

<?php include __DIR__ . '/inc/footer.php'; ?>
