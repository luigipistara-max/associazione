<?php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/functions.php';

requireLogin();

$pageTitle = 'Dashboard';

// Get statistics
try {
    // Total active members
    $stmt = $pdo->query("SELECT COUNT(*) FROM " . table('members') . " WHERE status = 'attivo'");
    $activeMembers = $stmt->fetchColumn();
    
    // Total members
    $stmt = $pdo->query("SELECT COUNT(*) FROM " . table('members'));
    $totalMembers = $stmt->fetchColumn();
    
    // Get current social year
    $stmt = $pdo->query("SELECT id, name FROM " . table('social_years') . " WHERE is_current = 1 LIMIT 1");
    $currentYear = $stmt->fetch();
    
    $yearIncome = 0;
    $yearExpenses = 0;
    
    if ($currentYear) {
        // Income this year
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM " . table('income') . " WHERE social_year_id = ?");
        $stmt->execute([$currentYear['id']]);
        $yearIncome = $stmt->fetchColumn();
        
        // Expenses this year
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM " . table('expenses') . " WHERE social_year_id = ?");
        $stmt->execute([$currentYear['id']]);
        $yearExpenses = $stmt->fetchColumn();
    }
    
    $balance = $yearIncome - $yearExpenses;
    
    // Recent members
    $stmt = $pdo->query("SELECT * FROM " . table('members') . " ORDER BY created_at DESC LIMIT 5");
    $recentMembers = $stmt->fetchAll();
    
    // Recent income
    $stmt = $pdo->query("
        SELECT i.*, c.name as category_name, m.first_name, m.last_name 
        FROM " . table('income') . " i
        LEFT JOIN " . table('income_categories') . " c ON i.category_id = c.id
        LEFT JOIN " . table('members') . " m ON i.member_id = m.id
        ORDER BY i.transaction_date DESC, i.created_at DESC
        LIMIT 5
    ");
    $recentIncome = $stmt->fetchAll();
    
} catch (PDOException $e) {
    die("Errore database: " . htmlspecialchars($e->getMessage()));
}

include __DIR__ . '/inc/header.php';
?>

<?php displayFlash(); ?>

<div class="row">
    <div class="col-12">
        <h2 class="mb-4">Dashboard</h2>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-primary shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Soci Attivi</h6>
                        <h3 class="mb-0"><?= $activeMembers ?></h3>
                        <small class="text-muted">su <?= $totalMembers ?> totali</small>
                    </div>
                    <div class="text-primary">
                        <i class="bi bi-people-fill" style="font-size: 3rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-success shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Entrate Anno</h6>
                        <h3 class="mb-0 text-success"><?= formatCurrency($yearIncome) ?></h3>
                        <?php if ($currentYear): ?>
                            <small class="text-muted"><?= h($currentYear['name']) ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="text-success">
                        <i class="bi bi-arrow-up-circle-fill" style="font-size: 3rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-danger shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Uscite Anno</h6>
                        <h3 class="mb-0 text-danger"><?= formatCurrency($yearExpenses) ?></h3>
                        <?php if ($currentYear): ?>
                            <small class="text-muted"><?= h($currentYear['name']) ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="text-danger">
                        <i class="bi bi-arrow-down-circle-fill" style="font-size: 3rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-<?= $balance >= 0 ? 'info' : 'warning' ?> shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Saldo Anno</h6>
                        <h3 class="mb-0 text-<?= $balance >= 0 ? 'info' : 'warning' ?>"><?= formatCurrency($balance) ?></h3>
                        <?php if ($currentYear): ?>
                            <small class="text-muted"><?= h($currentYear['name']) ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="text-<?= $balance >= 0 ? 'info' : 'warning' ?>">
                        <i class="bi bi-piggy-bank-fill" style="font-size: 3rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-people me-2"></i>Ultimi Soci Registrati</h5>
            </div>
            <div class="card-body">
                <?php if (empty($recentMembers)): ?>
                    <p class="text-muted mb-0">Nessun socio registrato</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>Tessera</th>
                                    <th>Stato</th>
                                    <th>Data</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentMembers as $member): ?>
                                <tr>
                                    <td><?= h($member['first_name'] . ' ' . $member['last_name']) ?></td>
                                    <td><?= h($member['membership_number']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $member['status'] === 'attivo' ? 'success' : ($member['status'] === 'sospeso' ? 'warning' : 'secondary') ?>">
                                            <?= h($member['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= formatDate($member['registration_date']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-footer bg-white">
                <a href="members.php" class="btn btn-sm btn-primary">
                    <i class="bi bi-eye me-1"></i>Vedi Tutti
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-cash-coin me-2"></i>Ultimi Movimenti Entrate</h5>
            </div>
            <div class="card-body">
                <?php if (empty($recentIncome)): ?>
                    <p class="text-muted mb-0">Nessuna entrata registrata</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Categoria</th>
                                    <th>Socio</th>
                                    <th class="text-end">Importo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentIncome as $income): ?>
                                <tr>
                                    <td><?= formatDate($income['transaction_date']) ?></td>
                                    <td><?= h($income['category_name']) ?></td>
                                    <td><?= $income['member_id'] ? h($income['first_name'] . ' ' . $income['last_name']) : '-' ?></td>
                                    <td class="text-end text-success"><?= formatCurrency($income['amount']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-footer bg-white">
                <a href="finance.php" class="btn btn-sm btn-primary">
                    <i class="bi bi-eye me-1"></i>Vedi Tutti
                </a>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/inc/footer.php'; ?>
