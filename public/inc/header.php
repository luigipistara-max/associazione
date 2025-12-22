<?php
if (!isset($config)) {
    $config = require __DIR__ . '/../../src/config.php';
}
if (!isset($pageTitle)) {
    $pageTitle = 'Dashboard';
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - <?= htmlspecialchars($config['app']['name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        main {
            flex: 1;
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-house-heart-fill me-2"></i><?= htmlspecialchars($config['app']['name']) ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="members.php"><i class="bi bi-people me-1"></i>Soci</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="finance.php"><i class="bi bi-cash-coin me-1"></i>Movimenti</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reports.php"><i class="bi bi-bar-chart me-1"></i>Rendiconto</a>
                    </li>
                    <?php if (isAdmin()): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-gear me-1"></i>Impostazioni
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="users.php"><i class="bi bi-person-badge me-2"></i>Utenti</a></li>
                            <li><a class="dropdown-item" href="years.php"><i class="bi bi-calendar-event me-2"></i>Anni Sociali</a></li>
                            <li><a class="dropdown-item" href="categories.php"><i class="bi bi-tag me-2"></i>Categorie</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="import_members.php"><i class="bi bi-upload me-2"></i>Import Soci</a></li>
                            <li><a class="dropdown-item" href="import_movements.php"><i class="bi bi-upload me-2"></i>Import Movimenti</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i><?= htmlspecialchars(getCurrentUsername()) ?> 
                            <span class="badge bg-secondary"><?= htmlspecialchars(getCurrentUserRole()) ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Esci</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <main class="py-4">
        <div class="container-fluid">
