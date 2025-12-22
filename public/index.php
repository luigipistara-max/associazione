<?php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/db.php';

requireLogin();

$pageTitle = 'Dashboard';

// Get statistics


// Count members by status
$stmt = $pdo->query("SELECT status, COUNT(*) as count FROM " . table('members') . " GROUP BY status");
$memberStats = [];
while ($row = $stmt->fetch()) {
    $memberStats[$row['status']] = $row['count'];
}

$totalMembers = array_sum($memberStats);
$activeMembers = $memberStats['attivo'] ?? 0;

// Get current year
$currentYear = getCurrentSocialYear();

// Get financial summary for current year
$yearFilter = $currentYear ? "WHERE social_year_id = " . $currentYear['id'] : "";

$stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM " . table('income') . " $yearFilter");
$totalIncome = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM " . table('expenses') . " $yearFilter");
$totalExpense = $stmt->fetch()['total'];

$balance = $totalIncome - $totalExpense;

// Get recent members
$stmt = $pdo->query("
    SELECT * FROM " . table('members') . "
    ORDER BY created_at DESC
    LIMIT 5
");
$recentMembers = $stmt->fetchAll();

// Get recent income
$stmt = $pdo->query("
    SELECT i.*, ic.name as category_name, m.first_name, m.last_name
    FROM " . table('income') . " i
    LEFT JOIN " . table('income_categories') . " ic ON i.category_id = ic.id
    LEFT JOIN " . table('members') . " m ON i.member_id = m.id
    ORDER BY i.transaction_date DESC, i.id DESC
    LIMIT 5
");
$recentIncome = $stmt->fetchAll();

// Get recent expenses
$stmt = $pdo->query("
    SELECT e.*, ec.name as category_name
    FROM " . table('expenses') . " e
    LEFT JOIN " . table('expense_categories') . " ec ON e.category_id = ec.id
    ORDER BY e.transaction_date DESC, e.id DESC
    LIMIT 5
");
$recentExpenses = $stmt->fetchAll();

include __DIR__ . '/inc/header.php';
?>

<h2><i class="bi bi-speedometer2"></i> Dashboard</h2>

<div class="row mt-4">
    <!-- Members Stats -->
    <div class="col-md-3 mb-3">
        <div class="card border-primary">
            <div class="card-body">
                <h5 class="card-title text-primary">
                    <i class="bi bi-people"></i> Soci Totali
                </h5>
                <h2 class="mb-0"><?php echo $totalMembers; ?></h2>
                <small class="text-muted">di cui <?php echo $activeMembers; ?> attivi</small>
            </div>
        </div>
    </div>

    <!-- Income Stats -->
    <div class="col-md-3 mb-3">
        <div class="card border-success">
            <div class="card-body">
                <h5 class="card-title text-success">
                    <i class="bi bi-arrow-down-circle"></i> Entrate
                </h5>
                <h2 class="mb-0"><?php echo formatAmount($totalIncome); ?></h2>
                <?php if ($currentYear): ?>
                <small class="text-muted"><?php echo e($currentYear['name']); ?></small>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Expense Stats -->
    <div class="col-md-3 mb-3">
        <div class="card border-danger">
            <div class="card-body">
                <h5 class="card-title text-danger">
                    <i class="bi bi-arrow-up-circle"></i> Uscite
                </h5>
                <h2 class="mb-0"><?php echo formatAmount($totalExpense); ?></h2>
                <?php if ($currentYear): ?>
                <small class="text-muted"><?php echo e($currentYear['name']); ?></small>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Balance Stats -->
    <div class="col-md-3 mb-3">
        <div class="card border-<?php echo $balance >= 0 ? 'success' : 'danger'; ?>">
            <div class="card-body">
                <h5 class="card-title text-<?php echo $balance >= 0 ? 'success' : 'danger'; ?>">
                    <i class="bi bi-wallet2"></i> Saldo
                </h5>
                <h2 class="mb-0"><?php echo formatAmount($balance); ?></h2>
                <?php if ($currentYear): ?>
                <small class="text-muted"><?php echo e($currentYear['name']); ?></small>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Current Year Info -->
<?php if ($currentYear): ?>
<div class="alert alert-info">
    <i class="bi bi-calendar-check"></i> <strong>Anno Sociale Corrente:</strong> 
    <?php echo e($currentYear['name']); ?> 
    (<?php echo formatDate($currentYear['start_date']); ?> - <?php echo formatDate($currentYear['end_date']); ?>)
</div>
<?php else: ?>
<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle"></i> 
    Nessun anno sociale corrente impostato. 
    <a href="/years.php" class="alert-link">Imposta un anno sociale</a>
</div>
<?php endif; ?>

<!-- Recent Movements -->
<div class="card mt-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-clock-history"></i> Ultimi Movimenti</h5>
    </div>
    <div class="card-body">
        <?php if (empty($recentMovements)): ?>
            <p class="text-muted">Nessun movimento registrato.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Tipo</th>
                            <th>Categoria</th>
                            <th>Descrizione</th>
                            <th>Socio</th>
                            <th class="text-end">Importo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentMovements as $mov): ?>
                        <tr>
                            <td><?php echo formatDate($mov['paid_at']); ?></td>
                            <td>
                                <?php if ($mov['type'] === 'income'): ?>
                                    <span class="badge bg-success">Entrata</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Uscita</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo e($mov['category_name']); ?></td>
                            <td><?php echo e($mov['description']); ?></td>
                            <td>
                                <?php if ($mov['member_id']): ?>
                                    <?php echo e($mov['first_name'] . ' ' . $mov['last_name']); ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <strong class="text-<?php echo $mov['type'] === 'income' ? 'success' : 'danger'; ?>">
                                    <?php echo formatAmount($mov['amount']); ?>
                                </strong>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="text-center">
                <a href="/finance.php" class="btn btn-outline-primary">
                    <i class="bi bi-list"></i> Vedi Tutti i Movimenti
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Quick Actions -->
<div class="row mt-4 mb-4">
    <div class="col-md-4 mb-3">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="bi bi-person-plus text-primary" style="font-size: 3rem;"></i>
                <h5 class="mt-3">Nuovo Socio</h5>
                <a href="/member_edit.php" class="btn btn-primary">
                    <i class="bi bi-plus"></i> Aggiungi
                </a>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="bi bi-cash-coin text-success" style="font-size: 3rem;"></i>
                <h5 class="mt-3">Nuovo Movimento</h5>
                <a href="/finance.php?action=add" class="btn btn-success">
                    <i class="bi bi-plus"></i> Aggiungi
                </a>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="bi bi-file-earmark-bar-graph text-info" style="font-size: 3rem;"></i>
                <h5 class="mt-3">Rendiconto</h5>
                <a href="/reports.php" class="btn btn-info">
                    <i class="bi bi-eye"></i> Visualizza
                </a>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/inc/footer.php'; ?>
