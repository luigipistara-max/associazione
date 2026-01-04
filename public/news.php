<?php
/**
 * News Management Page (Admin)
 * List, filter and manage news/blog posts
 */

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/functions.php';

requireLogin();
requireAdmin();

$pageTitle = 'Gestione Notizie';

// Handle delete action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $newsId = (int) $_POST['news_id'];
    if (deleteNews($newsId)) {
        setFlash('Notizia eliminata con successo', 'success');
    } else {
        setFlash('Errore nell\'eliminazione della notizia', 'danger');
    }
    redirect('news.php');
}

// Filters
$filters = [];
if (!empty($_GET['status'])) {
    $filters['status'] = $_GET['status'];
}
if (!empty($_GET['author_id'])) {
    $filters['author_id'] = (int) $_GET['author_id'];
}
if (!empty($_GET['search'])) {
    $filters['search'] = $_GET['search'];
}

// Pagination
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$perPage = 20;

// Get news
$newsList = getNews($filters, $page, $perPage);

// Get all users for author filter
$stmt = $pdo->query("SELECT id, full_name FROM " . table('users') . " ORDER BY full_name");
$authors = $stmt->fetchAll();

include __DIR__ . '/inc/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="bi bi-newspaper"></i> Gestione Notizie</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="news_edit.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Nuova Notizia
        </a>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Cerca</label>
                <input type="text" class="form-control" name="search" 
                       value="<?php echo h($_GET['search'] ?? ''); ?>" 
                       placeholder="Titolo o contenuto...">
            </div>
            <div class="col-md-3">
                <label class="form-label">Stato</label>
                <select class="form-select" name="status">
                    <option value="">Tutti</option>
                    <option value="draft" <?php echo ($_GET['status'] ?? '') === 'draft' ? 'selected' : ''; ?>>Bozza</option>
                    <option value="published" <?php echo ($_GET['status'] ?? '') === 'published' ? 'selected' : ''; ?>>Pubblicata</option>
                    <option value="archived" <?php echo ($_GET['status'] ?? '') === 'archived' ? 'selected' : ''; ?>>Archiviata</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Autore</label>
                <select class="form-select" name="author_id">
                    <option value="">Tutti</option>
                    <?php foreach ($authors as $author): ?>
                        <option value="<?php echo $author['id']; ?>" 
                                <?php echo ($_GET['author_id'] ?? '') == $author['id'] ? 'selected' : ''; ?>>
                            <?php echo h($author['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="bi bi-search"></i> Filtra
                </button>
                <a href="news.php" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> Reset
                </a>
            </div>
        </form>
    </div>
</div>

<!-- News List -->
<?php if (empty($newsList)): ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle"></i> Nessuna notizia trovata. 
        <a href="news_edit.php" class="alert-link">Crea la prima notizia</a>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Titolo</th>
                    <th>Autore</th>
                    <th>Stato</th>
                    <th>Target</th>
                    <th>Visualizzazioni</th>
                    <th>Pubblicata</th>
                    <th>Azioni</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($newsList as $news): ?>
                    <tr>
                        <td>
                            <strong><?php echo h($news['title']); ?></strong>
                            <?php if ($news['cover_image']): ?>
                                <i class="bi bi-image text-muted" title="Con immagine"></i>
                            <?php endif; ?>
                        </td>
                        <td><?php echo h($news['author_name']); ?></td>
                        <td>
                            <?php if ($news['status'] === 'published'): ?>
                                <span class="badge bg-success">Pubblicata</span>
                            <?php elseif ($news['status'] === 'draft'): ?>
                                <span class="badge bg-secondary">Bozza</span>
                            <?php else: ?>
                                <span class="badge bg-warning">Archiviata</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($news['target_type'] === 'all'): ?>
                                <span class="badge bg-info">Tutti i soci</span>
                            <?php else: ?>
                                <span class="badge bg-primary">Gruppi specifici</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <i class="bi bi-eye"></i> <?php echo number_format($news['views_count']); ?>
                        </td>
                        <td>
                            <?php if ($news['published_at']): ?>
                                <?php echo formatDate($news['published_at']); ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm" role="group">
                                <a href="news_edit.php?id=<?php echo $news['id']; ?>" 
                                   class="btn btn-outline-primary" title="Modifica">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button type="button" class="btn btn-outline-danger" 
                                        onclick="confirmDelete(<?php echo $news['id']; ?>, '<?php echo h(addslashes($news['title'])); ?>')"
                                        title="Elimina">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <?php if (count($newsList) >= $perPage): ?>
        <nav>
            <ul class="pagination justify-content-center">
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo http_build_query(array_diff_key($_GET, ['page' => ''])) ? '&' . http_build_query(array_diff_key($_GET, ['page' => ''])) : ''; ?>">
                            Precedente
                        </a>
                    </li>
                <?php endif; ?>
                <li class="page-item active">
                    <span class="page-link">Pagina <?php echo $page; ?></span>
                </li>
                <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo http_build_query(array_diff_key($_GET, ['page' => ''])) ? '&' . http_build_query(array_diff_key($_GET, ['page' => ''])) : ''; ?>">
                        Successiva
                    </a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
<?php endif; ?>

<!-- Delete Confirmation Form -->
<form method="POST" id="deleteForm" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="news_id" id="deleteNewsId">
</form>

<script>
function confirmDelete(newsId, title) {
    if (confirm('Sei sicuro di voler eliminare la notizia "' + title + '"?\n\nQuesta azione non può essere annullata.')) {
        document.getElementById('deleteNewsId').value = newsId;
        document.getElementById('deleteForm').submit();
    }
}
</script>

<?php include __DIR__ . '/inc/footer.php'; ?>
