<?php
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/functions.php';

$config = require __DIR__ . '/../../src/config.php';
$siteName = $config['app']['name'] ?? 'Associazione';
$currentUser = getCurrentUser();
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($pageTitle ?? $siteName); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
        footer {
            background-color: #f8f9fa;
            padding: 15px 0;
            margin-top: 50px;
            border-top: 1px solid #dee2e6;
            text-align: center;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?php echo h($config['app']['base_path']); ?>index.php">
                <i class="bi bi-people-fill"></i> <?php echo h($siteName); ?>
            </a>
            <?php if (isLoggedIn()): ?>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> <?php echo h($currentUser['full_name']); ?>
                            <span class="badge bg-light text-dark"><?php echo h(ucfirst($currentUser['role'])); ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?php echo h($config['app']['base_path']); ?>logout.php"><i class="bi bi-box-arrow-right"></i> Esci</a></li>
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
                            <a class="nav-link <?php echo $currentPage === 'index.php' ? 'active' : ''; ?>" href="<?php echo h($config['app']['base_path']); ?>index.php">
                                <i class="bi bi-house-door"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage === 'members.php' || $currentPage === 'member_edit.php' ? 'active' : ''; ?>" href="<?php echo h($config['app']['base_path']); ?>members.php">
                                <i class="bi bi-people"></i> Soci
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage === 'member_fees.php' ? 'active' : ''; ?>" href="<?php echo h($config['app']['base_path']); ?>member_fees.php">
                                <i class="bi bi-credit-card"></i> Quote Associative
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage === 'finance.php' ? 'active' : ''; ?>" href="<?php echo h($config['app']['base_path']); ?>finance.php">
                                <i class="bi bi-cash-coin"></i> Movimenti
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage === 'reports.php' ? 'active' : ''; ?>" href="<?php echo h($config['app']['base_path']); ?>reports.php">
                                <i class="bi bi-file-earmark-bar-graph"></i> Rendiconto
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo in_array($currentPage, ['events.php', 'event_edit.php', 'event_view.php', 'event_registrations.php']) ? 'active' : ''; ?>" href="<?php echo h($config['app']['base_path']); ?>events.php">
                                <i class="bi bi-calendar-event"></i> Eventi
                            </a>
                        </li>
                        
                        <?php if (isAdmin()): ?>
                        <hr>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage === 'users.php' ? 'active' : ''; ?>" href="<?php echo h($config['app']['base_path']); ?>users.php">
                                <i class="bi bi-person-gear"></i> Utenti
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage === 'years.php' ? 'active' : ''; ?>" href="<?php echo h($config['app']['base_path']); ?>years.php">
                                <i class="bi bi-calendar-range"></i> Anni Sociali
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage === 'categories.php' ? 'active' : ''; ?>" href="<?php echo h($config['app']['base_path']); ?>categories.php">
                                <i class="bi bi-tags"></i> Categorie
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage === 'bulk_fees.php' ? 'active' : ''; ?>" href="<?php echo h($config['app']['base_path']); ?>bulk_fees.php">
                                <i class="bi bi-arrow-repeat"></i> Rinnovo Quote
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage === 'send_reminders.php' ? 'active' : ''; ?>" href="<?php echo h($config['app']['base_path']); ?>send_reminders.php">
                                <i class="bi bi-envelope-exclamation"></i> Invio Solleciti
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage === 'mass_email.php' ? 'active' : ''; ?>" href="<?php echo h($config['app']['base_path']); ?>mass_email.php">
                                <i class="bi bi-envelope-at"></i> Email Massiva
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage === 'admin_email_templates.php' ? 'active' : ''; ?>" href="<?php echo h($config['app']['base_path']); ?>admin_email_templates.php">
                                <i class="bi bi-envelope-paper"></i> Template Email
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage === 'audit_log.php' ? 'active' : ''; ?>" href="<?php echo h($config['app']['base_path']); ?>audit_log.php">
                                <i class="bi bi-clock-history"></i> Audit Log
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <hr>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage === 'import_members.php' ? 'active' : ''; ?>" href="<?php echo h($config['app']['base_path']); ?>import_members.php">
                                <i class="bi bi-upload"></i> Importa Soci
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage === 'import_movements.php' ? 'active' : ''; ?>" href="<?php echo h($config['app']['base_path']); ?>import_movements.php">
                                <i class="bi bi-upload"></i> Importa Movimenti
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage === 'export_active_members.php' ? 'active' : ''; ?>" href="<?php echo h($config['app']['base_path']); ?>export_active_members.php">
                                <i class="bi bi-download"></i> Export Soci Attivi
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo h($config['app']['base_path']); ?>export_excel.php">
                                <i class="bi bi-download"></i> Esporta Excel
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="pt-3 pb-2 mb-3">
                    <?php displayFlash(); ?>
            <?php else: ?>
            <main class="col-12">
            <?php endif; ?>
