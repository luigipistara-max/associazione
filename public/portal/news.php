<?php
/**
 * News List (Portal Soci)
 * Display news visible to the logged-in member
 */

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

$memberId = $member['id'];
$pageTitle = 'Notizie';

// Pagination
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$perPage = 5;

// Get news for this member
$newsList = getNewsForMember($memberId, $page, $perPage);
$totalNews = countNewsForMember($memberId);
$totalPages = ceil($totalNews / $perPage);

require_once __DIR__ . '/inc/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <h1 class="mb-4">
                <i class="bi bi-newspaper"></i> Notizie
            </h1>
            
            <?php if (empty($newsList)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Non ci sono notizie disponibili al momento.
                </div>
            <?php else: ?>
                <!-- News List -->
                <?php foreach ($newsList as $news): ?>
                    <div class="card mb-4">
                        <?php if ($news['cover_image']): ?>
                            <img src="<?php echo h($news['cover_image']); ?>" 
                                 class="card-img-top" 
                                 alt="<?php echo h($news['title']); ?>"
                                 style="max-height: 300px; object-fit: cover;">
                        <?php endif; ?>
                        
                        <div class="card-body">
                            <h5 class="card-title">
                                <a href="news_view.php?slug=<?php echo h($news['slug']); ?>" class="text-decoration-none">
                                    <?php echo h($news['title']); ?>
                                </a>
                            </h5>
                            
                            <p class="card-text text-muted small">
                                <i class="bi bi-calendar3"></i> <?php echo formatDate($news['published_at']); ?>
                                <i class="bi bi-person ms-2"></i> <?php echo h($news['author_name']); ?>
                                <i class="bi bi-eye ms-2"></i> <?php echo number_format($news['views_count']); ?> visualizzazioni
                            </p>
                            
                            <?php if ($news['excerpt']): ?>
                                <p class="card-text"><?php echo h($news['excerpt']); ?></p>
                            <?php else: ?>
                                <p class="card-text">
                                    <?php 
                                    // Show first 200 characters of content
                                    $plainContent = strip_tags($news['content']);
                                    echo h(mb_substr($plainContent, 0, 200)) . (mb_strlen($plainContent) > 200 ? '...' : '');
                                    ?>
                                </p>
                            <?php endif; ?>
                            
                            <a href="news_view.php?slug=<?php echo h($news['slug']); ?>" class="btn btn-primary">
                                Leggi tutto <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <nav aria-label="Paginazione notizie">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>">
                                        <i class="bi bi-chevron-left"></i> Precedente
                                    </a>
                                </li>
                            <?php else: ?>
                                <li class="page-item disabled">
                                    <span class="page-link">
                                        <i class="bi bi-chevron-left"></i> Precedente
                                    </span>
                                </li>
                            <?php endif; ?>
                            
                            <li class="page-item active">
                                <span class="page-link">
                                    Pagina <?php echo $page; ?> di <?php echo $totalPages; ?>
                                </span>
                            </li>
                            
                            <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>">
                                        Successiva <i class="bi bi-chevron-right"></i>
                                    </a>
                                </li>
                            <?php else: ?>
                                <li class="page-item disabled">
                                    <span class="page-link">
                                        Successiva <i class="bi bi-chevron-right"></i>
                                    </span>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
