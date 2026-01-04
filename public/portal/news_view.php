<?php
/**
 * News View (Portal Soci)
 * Display full news article to member
 */

require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/functions.php';
require_once __DIR__ . '/inc/auth.php';

requireMemberLogin();

$memberId = $_SESSION['member_id'];

// Get news by slug
$slug = $_GET['slug'] ?? '';
if (empty($slug)) {
    setFlash('Notizia non trovata', 'danger');
    redirect('news.php');
}

$news = getNewsBySlug($slug);

if (!$news) {
    setFlash('Notizia non trovata', 'danger');
    redirect('news.php');
}

// Check if member can view this news
if (!canMemberViewNews($news['id'], $memberId)) {
    setFlash('Non hai accesso a questa notizia', 'danger');
    redirect('news.php');
}

// Increment view count
incrementNewsViews($news['id']);

$pageTitle = $news['title'];

require_once __DIR__ . '/inc/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-lg-10 mx-auto">
            <!-- Back button -->
            <a href="news.php" class="btn btn-sm btn-outline-secondary mb-3">
                <i class="bi bi-arrow-left"></i> Torna alle notizie
            </a>
            
            <!-- News Article -->
            <article class="card">
                <?php if ($news['cover_image']): ?>
                    <img src="<?php echo h($news['cover_image']); ?>" 
                         class="card-img-top" 
                         alt="<?php echo h($news['title']); ?>"
                         style="max-height: 400px; object-fit: cover;">
                <?php endif; ?>
                
                <div class="card-body">
                    <h1 class="card-title mb-3"><?php echo h($news['title']); ?></h1>
                    
                    <div class="text-muted mb-4">
                        <i class="bi bi-calendar3"></i> 
                        <time datetime="<?php echo h($news['published_at']); ?>">
                            <?php echo formatDate($news['published_at']); ?>
                        </time>
                        
                        <span class="mx-2">•</span>
                        
                        <i class="bi bi-person"></i> 
                        <?php echo h($news['author_name']); ?>
                        
                        <span class="mx-2">•</span>
                        
                        <i class="bi bi-eye"></i> 
                        <?php echo number_format($news['views_count']); ?> visualizzazioni
                    </div>
                    
                    <?php if ($news['excerpt']): ?>
                        <div class="lead mb-4">
                            <?php echo h($news['excerpt']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="news-content">
                        <?php echo $news['content']; ?>
                    </div>
                </div>
                
                <div class="card-footer text-muted">
                    <small>
                        Pubblicata il <?php echo date('d/m/Y \a\l\l\e H:i', strtotime($news['published_at'])); ?>
                        
                        <?php if ($news['target_type'] === 'groups'): ?>
                            <span class="mx-2">•</span>
                            <i class="bi bi-people"></i> Visibile solo a gruppi specifici
                        <?php endif; ?>
                    </small>
                </div>
            </article>
            
            <!-- Navigation -->
            <div class="d-flex justify-content-between mt-4">
                <a href="news.php" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left"></i> Tutte le notizie
                </a>
            </div>
        </div>
    </div>
</div>

<style>
.news-content {
    font-size: 1.1rem;
    line-height: 1.7;
}

.news-content img {
    max-width: 100%;
    height: auto;
    margin: 1rem 0;
}

.news-content p {
    margin-bottom: 1rem;
}

.news-content h2 {
    margin-top: 2rem;
    margin-bottom: 1rem;
    font-size: 1.75rem;
}

.news-content h3 {
    margin-top: 1.5rem;
    margin-bottom: 0.75rem;
    font-size: 1.5rem;
}

.news-content ul, .news-content ol {
    margin-bottom: 1rem;
    padding-left: 2rem;
}

.news-content blockquote {
    border-left: 4px solid #667eea;
    padding-left: 1rem;
    margin: 1.5rem 0;
    font-style: italic;
    color: #6c757d;
}

.news-content table {
    width: 100%;
    margin: 1rem 0;
    border-collapse: collapse;
}

.news-content table th,
.news-content table td {
    border: 1px solid #dee2e6;
    padding: 0.5rem;
}

.news-content table th {
    background-color: #f8f9fa;
    font-weight: bold;
}

.news-content a {
    color: #667eea;
    text-decoration: underline;
}

.news-content a:hover {
    color: #764ba2;
}
</style>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
