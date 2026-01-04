<?php
// Portal header - must be included after authentication
if (!isset($member)) {
    die('Header requires $member variable');
}

$assocInfo = getAssociationInfo();
$siteName = $assocInfo['name'] ?? 'Associazione';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? h($pageTitle) . ' - ' : ''; ?><?php echo h($siteName); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --portal-primary: #667eea;
            --portal-secondary: #764ba2;
        }
        body {
            background: #f5f7fa;
            padding-top: 70px;
        }
        .navbar-portal {
            background: linear-gradient(135deg, var(--portal-primary) 0%, var(--portal-secondary) 100%);
        }
        .navbar-portal .navbar-brand,
        .navbar-portal .nav-link {
            color: white !important;
        }
        .navbar-portal .nav-link:hover {
            background: rgba(255,255,255,0.1);
            border-radius: 5px;
        }
        .navbar-portal .nav-link.active {
            background: rgba(255,255,255,0.2);
            border-radius: 5px;
        }
        .card {
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border: none;
            border-radius: 10px;
        }
        .member-photo {
            width: 150px;
            height: 200px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #ddd;
        }
        .member-photo-placeholder {
            width: 150px;
            height: 200px;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            border: 2px dashed #ccc;
            color: #999;
            flex-direction: column;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-portal navbar-expand-lg navbar-dark fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?php echo h($basePath); ?>portal/index.php">
                <?php if (!empty($assocInfo['logo'])): ?>
                    <img src="<?php echo h($basePath . $assocInfo['logo']); ?>" alt="Logo" height="30" class="d-inline-block align-text-top me-2">
                <?php endif; ?>
                <?php echo h($siteName); ?>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : ''; ?>" 
                           href="<?php echo h($basePath); ?>portal/index.php">
                            <i class="bi bi-house"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'profile.php' ? 'active' : ''; ?>" 
                           href="<?php echo h($basePath); ?>portal/profile.php">
                            <i class="bi bi-person"></i> Profilo
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'card.php' ? 'active' : ''; ?>" 
                           href="<?php echo h($basePath); ?>portal/card.php">
                            <i class="bi bi-card-heading"></i> Tesserino
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'events.php' ? 'active' : ''; ?>" 
                           href="<?php echo h($basePath); ?>portal/events.php">
                            <i class="bi bi-calendar-event"></i> Eventi
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo in_array(basename($_SERVER['PHP_SELF']), ['news.php', 'news_view.php']) ? 'active' : ''; ?>" 
                           href="<?php echo h($basePath); ?>portal/news.php">
                            <i class="bi bi-newspaper"></i> Notizie
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'groups.php' ? 'active' : ''; ?>" 
                           href="<?php echo h($basePath); ?>portal/groups.php">
                            <i class="bi bi-people"></i> Gruppi
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'payments.php' ? 'active' : ''; ?>" 
                           href="<?php echo h($basePath); ?>portal/payments.php">
                            <i class="bi bi-credit-card"></i> Pagamenti
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'receipts.php' ? 'active' : ''; ?>" 
                           href="<?php echo h($basePath); ?>portal/receipts.php">
                            <i class="bi bi-receipt"></i> Ricevute
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> <?php echo h($member['first_name']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?php echo h($basePath); ?>portal/profile.php">
                                <i class="bi bi-person"></i> Il mio profilo
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo h($basePath); ?>portal/logout.php">
                                <i class="bi bi-box-arrow-right"></i> Esci
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <div class="container-fluid py-4">
