<?php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/functions.php';

requireLogin();

$pageTitle = 'Rendiconto';

// Get selected year
$selectedYearId = isset($_GET['year']) ? (int)$_GET['year'] : null;

// Load social years
try {
    $stmt = $pdo->query("SELECT * FROM " . table('social_years') . " ORDER BY start_date DESC");
    $socialYears = $stmt->fetchAll();
    
    // If no year selected, use current year
    if (!$selectedYearId && !empty($socialYears)) {
        foreach ($socialYears as $year) {
            if ($year['is_current']) {
                $selectedYearId = $year['id'];
                break;
            }
        }
        // If no current year, use first one
        if (!$selectedYearId) {
            $selectedYearId = $socialYears[0]['id'];
        }
    }
    
    $selectedYear = null;
    if ($selectedYearId) {
        $stmt = $pdo->prepare("SELECT * FROM " . table('social_years') . " WHERE id = ?");
        $stmt->execute([$selectedYearId]);
        $selectedYear = $stmt->fetch();
    }
    
    // Load income by category
    $incomeByCategory = [];
    $totalIncome = 0;
    if ($selectedYearId) {
        $stmt = $pdo->prepare("
            SELECT c.name, COALESCE(SUM(i.amount), 0) as total, COUNT(i.id) as count
            FROM " . table('income_categories') . " c
            LEFT JOIN " . table('income') . " i ON c.id = i.category_id AND i.social_year_id = ?
            WHERE c.is_active = 1
            GROUP BY c.id, c.name, c.sort_order
            ORDER BY c.sort_order, c.name
        ");
        $stmt->execute([$selectedYearId]);
        $incomeByCategory = $stmt->fetchAll();
        
        foreach ($incomeByCategory as $cat) {
            $totalIncome += $cat['total'];
        }
    }
    
    // Load expenses by category
    $expenseByCategory = [];
    $totalExpense = 0;
    if ($selectedYearId) {
        $stmt = $pdo->prepare("
            SELECT c.name, COALESCE(SUM(e.amount), 0) as total, COUNT(e.id) as count
            FROM " . table('expense_categories') . " c
            LEFT JOIN " . table('expenses') . " e ON c.id = e.category_id AND e.social_year_id = ?
            WHERE c.is_active = 1
            GROUP BY c.id, c.name, c.sort_order
            ORDER BY c.sort_order, c.name
        ");
        $stmt->execute([$selectedYearId]);
        $expenseByCategory = $stmt->fetchAll();
        
        foreach ($expenseByCategory as $cat) {
            $totalExpense += $cat['total'];
        }
    }
    
    $balance = $totalIncome - $totalExpense;
    
} catch (PDOException $e) {
    die("Errore database: " . htmlspecialchars($e->getMessage()));
}

include __DIR__ . '/inc/header.php';
?>

<?php displayFlash(); ?>

<div class="row mb-3">
    <div class="col-md-6">
        <h2><i class="bi bi-bar-chart me-2"></i>Rendiconto Finanziario</h2>
    </div>
    <div class="col-md-6 text-end">
        <?php if ($selectedYearId): ?>
        <a href="export_excel.php?year=<?= $selectedYearId ?>" class="btn btn-success">
            <i class="bi bi-file-earmark-excel me-1"></i>Esporta Excel
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Year Selector -->
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-8">
                <label class="form-label"><strong>Seleziona Anno Sociale</strong></label>
                <select name="year" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($socialYears as $year): ?>
                        <option value="<?= $year['id'] ?>" <?= $selectedYearId == $year['id'] ? 'selected' : '' ?>>
                            <?= h($year['name']) ?> (<?= formatDate($year['start_date']) ?> - <?= formatDate($year['end_date']) ?>)
                            <?= $year['is_current'] ? ' - CORRENTE' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<?php if (!$selectedYear): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-2"></i>Nessun anno sociale configurato. 
        <a href="years.php">Crea un anno sociale</a> per visualizzare il rendiconto.
    </div>
<?php else: ?>
    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-success shadow-sm">
                <div class="card-body text-center">
                    <h6 class="text-muted">Entrate Totali</h6>
                    <h2 class="text-success mb-0"><?= formatCurrency($totalIncome) ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-danger shadow-sm">
                <div class="card-body text-center">
                    <h6 class="text-muted">Uscite Totali</h6>
                    <h2 class="text-danger mb-0"><?= formatCurrency($totalExpense) ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-<?= $balance >= 0 ? 'primary' : 'warning' ?> shadow-sm">
                <div class="card-body text-center">
                    <h6 class="text-muted">Risultato d'Esercizio</h6>
                    <h2 class="text-<?= $balance >= 0 ? 'primary' : 'warning' ?> mb-0">
                        <?= formatCurrency($balance) ?>
                    </h2>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Income Report -->
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-arrow-up-circle me-2"></i>Entrate per Categoria</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($incomeByCategory) || $totalIncome == 0): ?>
                        <p class="text-muted text-center py-4">Nessuna entrata registrata</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Categoria</th>
                                        <th class="text-center">N. Movimenti</th>
                                        <th class="text-end">Importo</th>
                                        <th class="text-end">%</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($incomeByCategory as $cat): ?>
                                        <?php if ($cat['total'] > 0): ?>
                                        <tr>
                                            <td><?= h($cat['name']) ?></td>
                                            <td class="text-center"><?= $cat['count'] ?></td>
                                            <td class="text-end"><?= formatCurrency($cat['total']) ?></td>
                                            <td class="text-end"><?= number_format(($cat['total'] / $totalIncome) * 100, 1) ?>%</td>
                                        </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-success fw-bold">
                                    <tr>
                                        <td colspan="2">TOTALE ENTRATE</td>
                                        <td class="text-end"><?= formatCurrency($totalIncome) ?></td>
                                        <td class="text-end">100%</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        
                        <!-- Income Chart -->
                        <div class="mt-3">
                            <?php foreach ($incomeByCategory as $cat): ?>
                                <?php if ($cat['total'] > 0): ?>
                                    <?php $percentage = ($cat['total'] / $totalIncome) * 100; ?>
                                    <div class="mb-2">
                                        <div class="d-flex justify-content-between mb-1">
                                            <small><?= h($cat['name']) ?></small>
                                            <small class="text-muted"><?= formatCurrency($cat['total']) ?></small>
                                        </div>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar bg-success" role="progressbar" 
                                                 style="width: <?= $percentage ?>%;" 
                                                 aria-valuenow="<?= $percentage ?>" aria-valuemin="0" aria-valuemax="100">
                                                <?= number_format($percentage, 1) ?>%
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Expense Report -->
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="bi bi-arrow-down-circle me-2"></i>Uscite per Categoria</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($expenseByCategory) || $totalExpense == 0): ?>
                        <p class="text-muted text-center py-4">Nessuna uscita registrata</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Categoria</th>
                                        <th class="text-center">N. Movimenti</th>
                                        <th class="text-end">Importo</th>
                                        <th class="text-end">%</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($expenseByCategory as $cat): ?>
                                        <?php if ($cat['total'] > 0): ?>
                                        <tr>
                                            <td><?= h($cat['name']) ?></td>
                                            <td class="text-center"><?= $cat['count'] ?></td>
                                            <td class="text-end"><?= formatCurrency($cat['total']) ?></td>
                                            <td class="text-end"><?= number_format(($cat['total'] / $totalExpense) * 100, 1) ?>%</td>
                                        </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-danger fw-bold">
                                    <tr>
                                        <td colspan="2">TOTALE USCITE</td>
                                        <td class="text-end"><?= formatCurrency($totalExpense) ?></td>
                                        <td class="text-end">100%</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        
                        <!-- Expense Chart -->
                        <div class="mt-3">
                            <?php foreach ($expenseByCategory as $cat): ?>
                                <?php if ($cat['total'] > 0): ?>
                                    <?php $percentage = ($cat['total'] / $totalExpense) * 100; ?>
                                    <div class="mb-2">
                                        <div class="d-flex justify-content-between mb-1">
                                            <small><?= h($cat['name']) ?></small>
                                            <small class="text-muted"><?= formatCurrency($cat['total']) ?></small>
                                        </div>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar bg-danger" role="progressbar" 
                                                 style="width: <?= $percentage ?>%;" 
                                                 aria-valuenow="<?= $percentage ?>" aria-valuemin="0" aria-valuemax="100">
                                                <?= number_format($percentage, 1) ?>%
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Final Balance -->
    <div class="card shadow-sm border-<?= $balance >= 0 ? 'primary' : 'warning' ?>">
        <div class="card-header bg-<?= $balance >= 0 ? 'primary' : 'warning' ?> text-white">
            <h5 class="mb-0"><i class="bi bi-calculator me-2"></i>Riepilogo Finale</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-borderless mb-0">
                        <tr>
                            <td><strong>Anno Sociale:</strong></td>
                            <td><?= h($selectedYear['name']) ?></td>
                        </tr>
                        <tr>
                            <td><strong>Periodo:</strong></td>
                            <td><?= formatDate($selectedYear['start_date']) ?> - <?= formatDate($selectedYear['end_date']) ?></td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-sm mb-0">
                        <tr>
                            <td>Totale Entrate:</td>
                            <td class="text-end text-success"><strong><?= formatCurrency($totalIncome) ?></strong></td>
                        </tr>
                        <tr>
                            <td>Totale Uscite:</td>
                            <td class="text-end text-danger"><strong><?= formatCurrency($totalExpense) ?></strong></td>
                        </tr>
                        <tr class="border-top">
                            <td><strong>Risultato d'Esercizio:</strong></td>
                            <td class="text-end text-<?= $balance >= 0 ? 'primary' : 'warning' ?>">
                                <h4 class="mb-0"><?= formatCurrency($balance) ?></h4>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/inc/footer.php'; ?>
