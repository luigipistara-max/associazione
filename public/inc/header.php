<?php
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/functions.php';

$currentUser = getCurrentUser();
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($pageTitle ?? 'Gestione Associazione'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .sidebar {
            min-height: calc(100vh - 56px);
            background-color: #f8f9fa;
        }
        .nav-link.active {
            background-color: #0d6efd;
            color: white !important;
        }
        .nav-link:hover {
            background-color: #e9ecef;
        }
        .nav-link.active:hover {
            background-color: #0b5ed7;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="/index.php">
                <i class="bi bi-people-fill"></i> Gestione Associazione
            </a>
            <?php if (isLoggedIn()): ?>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> <?php echo e($currentUser['username']); ?>
                            <span class="badge bg-light text-dark"><?php echo e(ucfirst($currentUser['role'])); ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="/logout.php"><i class="bi bi-box-arrow-right"></i> Esci</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
            <?php endif; ?>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <?php if (isLoggedIn()): ?>
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage === 'index.php' ? 'active' : ''; ?>" href="/index.php">
                                <i class="bi bi-house-door"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage === 'members.php' || $currentPage === 'member_edit.php' ? 'active' : ''; ?>" href="/members.php">
                                <i class="bi bi-people"></i> Soci
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage === 'finance.php' ? 'active' : ''; ?>" href="/finance.php">
                                <i class="bi bi-cash-coin"></i> Movimenti
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage === 'reports.php' ? 'active' : ''; ?>" href="/reports.php">
                                <i class="bi bi-file-earmark-bar-graph"></i> Rendiconto
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage === 'years.php' ? 'active' : ''; ?>" href="/years.php">
                                <i class="bi bi-calendar-range"></i> Anni Sociali
                            </a>
                        </li>
                        <?php if (isAdmin()): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage === 'categories.php' ? 'active' : ''; ?>" href="/categories.php">
                                <i class="bi bi-tags"></i> Categorie
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage === 'users.php' ? 'active' : ''; ?>" href="/users.php">
                                <i class="bi bi-person-gear"></i> Utenti
                            </a>
                        </li>
                        <?php endif; ?>
                        <hr>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage === 'import_members.php' ? 'active' : ''; ?>" href="/import_members.php">
                                <i class="bi bi-upload"></i> Importa Soci
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage === 'import_movements.php' ? 'active' : ''; ?>" href="/import_movements.php">
                                <i class="bi bi-upload"></i> Importa Movimenti
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/export_excel.php">
                                <i class="bi bi-download"></i> Esporta Excel
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="pt-3 pb-2 mb-3">
                    <?php 
                    $flash = getFlashMessage();
                    if ($flash): 
                    ?>
                    <div class="alert alert-<?php echo e($flash['type']); ?> alert-dismissible fade show" role="alert">
                        <?php echo e($flash['message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
            <?php else: ?>
            <main class="col-12">
            <?php endif; ?>
