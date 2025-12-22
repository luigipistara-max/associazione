<?php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/db.php';

requireLogin();

$pageTitle = 'Rendiconto';


// Get year filter
$yearId = $_GET['year'] ?? '';
$socialYears = getSocialYears();

// Build query
$yearFilter = '';
$yearName = 'Tutti gli anni';
if ($yearId) {
    $yearFilter = "AND m.social_year_id = " . (int)$yearId;
    foreach ($socialYears as $y) {
        if ($y['id'] == $yearId) {
            $yearName = $y['name'];
            break;
        }
    }
}

// Get income by category
$stmt = $pdo->query("
    SELECT ic.name, COALESCE(SUM(m.amount), 0) as total, COUNT(m.id) as count
    FROM " . table('income_categories') . " ic
    LEFT JOIN movements m ON ic.id = m.category_id AND m.type = 'income' $yearFilter
    WHERE ic.is_active = 1
    GROUP BY ic.id, ic.name, ic.sort_order
    ORDER BY ic.sort_order, ic.name
");
$incomeByCategory = $stmt->fetchAll();
$totalIncome = array_sum(array_column($incomeByCategory, 'total'));

// Get expense by category
$stmt = $pdo->query("
    SELECT ec.name, COALESCE(SUM(m.amount), 0) as total, COUNT(m.id) as count
    FROM " . table('expense_categories') . " ec
    LEFT JOIN movements m ON ec.id = m.category_id AND m.type = 'expense' $yearFilter
    WHERE ec.is_active = 1
    GROUP BY ec.id, ec.name, ec.sort_order
    ORDER BY ec.sort_order, ec.name
");
$expenseByCategory = $stmt->fetchAll();
$totalExpense = array_sum(array_column($expenseByCategory, 'total'));

$balance = $totalIncome - $totalExpense;

include __DIR__ . '/inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="bi bi-file-earmark-bar-graph"></i> Rendiconto</h2>
    <div>
        <a href="/export_excel.php?year=<?php echo $yearId; ?>" class="btn btn-success">
            <i class="bi bi-file-earmark-excel"></i> Esporta Excel
        </a>
    </div>
</div>

<!-- Year Filter -->
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Anno Sociale</label>
                <select name="year" class="form-select">
                    <option value="">Tutti gli anni</option>
                    <?php foreach ($socialYears as $year): ?>
                        <option value="<?php echo $year['id']; ?>" <?php echo $yearId == $year['id'] ? 'selected' : ''; ?>>
                            <?php echo e($year['name']); ?>
                            <?php if ($year['is_current']): ?>(Corrente)<?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-search"></i> Visualizza
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Report Header -->
<div class="card mb-3">
    <div class="card-body text-center">
        <h3>Rendiconto Economico/Finanziario</h3>
        <h5><?php echo e($yearName); ?></h5>
        <p class="text-muted mb-0">Generato il <?php echo date('d/m/Y H:i'); ?></p>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-3">
    <div class="col-md-4">
        <div class="card border-success h-100">
            <div class="card-body text-center">
                <h6 class="text-success">TOTALE ENTRATE</h6>
                <h2 class="text-success"><?php echo formatAmount($totalIncome); ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-danger h-100">
            <div class="card-body text-center">
                <h6 class="text-danger">TOTALE USCITE</h6>
                <h2 class="text-danger"><?php echo formatAmount($totalExpense); ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-<?php echo $balance >= 0 ? 'success' : 'danger'; ?> h-100">
            <div class="card-body text-center">
                <h6>RISULTATO D'ESERCIZIO</h6>
                <h2 class="text-<?php echo $balance >= 0 ? 'success' : 'danger'; ?>">
                    <?php echo formatAmount($balance); ?>
                </h2>
                <?php if ($balance >= 0): ?>
                    <small class="text-success">Avanzo di gestione</small>
                <?php else: ?>
                    <small class="text-danger">Disavanzo di gestione</small>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Detailed Report -->
<div class="row">
    <!-- Income Details -->
    <div class="col-md-6 mb-3">
        <div class="card h-100">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-arrow-down-circle"></i> Dettaglio Entrate</h5>
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Categoria</th>
                            <th class="text-center">N°</th>
                            <th class="text-end">Importo</th>
                            <th class="text-end">%</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($incomeByCategory as $cat): ?>
                        <?php 
                        $percentage = $totalIncome > 0 ? ($cat['total'] / $totalIncome * 100) : 0;
                        ?>
                        <tr>
                            <td><?php echo e($cat['name']); ?></td>
                            <td class="text-center"><?php echo $cat['count']; ?></td>
                            <td class="text-end"><?php echo formatAmount($cat['total']); ?></td>
                            <td class="text-end"><?php echo number_format($percentage, 1); ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th>TOTALE</th>
                            <th class="text-center">
                                <?php echo array_sum(array_column($incomeByCategory, 'count')); ?>
                            </th>
                            <th class="text-end"><?php echo formatAmount($totalIncome); ?></th>
                            <th class="text-end">100%</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Expense Details -->
    <div class="col-md-6 mb-3">
        <div class="card h-100">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="bi bi-arrow-up-circle"></i> Dettaglio Uscite</h5>
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Categoria</th>
                            <th class="text-center">N°</th>
                            <th class="text-end">Importo</th>
                            <th class="text-end">%</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expenseByCategory as $cat): ?>
                        <?php 
                        $percentage = $totalExpense > 0 ? ($cat['total'] / $totalExpense * 100) : 0;
                        ?>
                        <tr>
                            <td><?php echo e($cat['name']); ?></td>
                            <td class="text-center"><?php echo $cat['count']; ?></td>
                            <td class="text-end"><?php echo formatAmount($cat['total']); ?></td>
                            <td class="text-end"><?php echo number_format($percentage, 1); ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th>TOTALE</th>
                            <th class="text-center">
                                <?php echo array_sum(array_column($expenseByCategory, 'count')); ?>
                            </th>
                            <th class="text-end"><?php echo formatAmount($totalExpense); ?></th>
                            <th class="text-end">100%</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Charts Section -->
<div class="row">
    <div class="col-12">
        <div class="card mb-3">
            <div class="card-body">
                <h5><i class="bi bi-bar-chart"></i> Visualizzazione Grafica</h5>
                
                <!-- Income Chart -->
                <h6 class="mt-3">Entrate per Categoria</h6>
                <?php foreach ($incomeByCategory as $cat): ?>
                <?php if ($cat['total'] > 0): ?>
                <?php $percentage = $totalIncome > 0 ? ($cat['total'] / $totalIncome * 100) : 0; ?>
                <div class="mb-2">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <small><?php echo e($cat['name']); ?></small>
                        <small><strong><?php echo formatAmount($cat['total']); ?></strong> (<?php echo number_format($percentage, 1); ?>%)</small>
                    </div>
                    <div class="progress" style="height: 25px;">
                        <div class="progress-bar bg-success" role="progressbar" 
                             style="width: <?php echo $percentage; ?>%"
                             aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100">
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <?php endforeach; ?>
                
                <!-- Expense Chart -->
                <h6 class="mt-4">Uscite per Categoria</h6>
                <?php foreach ($expenseByCategory as $cat): ?>
                <?php if ($cat['total'] > 0): ?>
                <?php $percentage = $totalExpense > 0 ? ($cat['total'] / $totalExpense * 100) : 0; ?>
                <div class="mb-2">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <small><?php echo e($cat['name']); ?></small>
                        <small><strong><?php echo formatAmount($cat['total']); ?></strong> (<?php echo number_format($percentage, 1); ?>%)</small>
                    </div>
                    <div class="progress" style="height: 25px;">
                        <div class="progress-bar bg-danger" role="progressbar" 
                             style="width: <?php echo $percentage; ?>%"
                             aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100">
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Print Button -->
<div class="text-center mb-4 d-print-none">
    <button onclick="window.print()" class="btn btn-outline-primary">
        <i class="bi bi-printer"></i> Stampa Rendiconto
    </button>
</div>

<style>
@media print {
    .sidebar, .navbar, .d-print-none, .card-header { 
        display: none !important; 
    }
    .col-md-9 {
        width: 100% !important;
        max-width: 100% !important;
    }
    body {
        font-size: 12px;
    }
}
</style>

<?php include __DIR__ . '/inc/footer.php'; ?>
