<?php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/email.php';

requireLogin();

$config = require __DIR__ . '/../src/config.php';
$pageTitle = 'Dashboard';

// Get base path from config
$basePath = $config['app']['base_path'];

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
if ($currentYear) {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM " . table('income') . " WHERE social_year_id = ?");
    $stmt->execute([$currentYear['id']]);
    $totalIncome = $stmt->fetch()['total'];
    
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM " . table('expenses') . " WHERE social_year_id = ?");
    $stmt->execute([$currentYear['id']]);
    $totalExpense = $stmt->fetch()['total'];
} else {
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM " . table('income'));
    $totalIncome = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM " . table('expenses'));
    $totalExpense = $stmt->fetch()['total'];
}

$balance = $totalIncome - $totalExpense;

// Get fee statistics for current year
$feesExpiringSoon = [];
$morosiCount = 0;
$totalPending = 0;
$totalCollected = 0;
$recentPaidFees = [];
$morosiList = [];

if ($currentYear) {
    updateOverdueStatuses();
    $feesExpiringSoon = getFeesExpiringSoon(30);
    $morosiCount = countMorosi($currentYear['id']);
    $totalPending = getTotalPendingFees($currentYear['id']);
    $totalCollected = getTotalCollectedFees($currentYear['id']);
    
    // Get recent paid fees
    $stmt = $pdo->prepare("
        SELECT mf.*, m.first_name, m.last_name, m.membership_number
        FROM " . table('member_fees') . " mf
        JOIN " . table('members') . " m ON mf.member_id = m.id
        WHERE mf.status = 'paid' AND mf.social_year_id = ?
        ORDER BY mf.paid_date DESC
        LIMIT 5
    ");
    $stmt->execute([$currentYear['id']]);
    $recentPaidFees = $stmt->fetchAll();
    
    // Get morosi list
    $morosiList = getMorosi($currentYear['id']);
    if (count($morosiList) > 5) {
        $morosiList = array_slice($morosiList, 0, 5);
    }
}

// Get email queue count
$queuedEmails = getQueuedEmailsCount();

// Get upcoming events
$upcomingEvents = getUpcomingEvents(5);

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

<!-- Fee Statistics (if current year exists) -->
<?php if ($currentYear): ?>
<div class="row mt-3">
    <div class="col-md-3 mb-3">
        <div class="card border-warning">
            <div class="card-body">
                <h5 class="card-title text-warning">
                    <i class="bi bi-exclamation-triangle"></i> In Scadenza
                </h5>
                <h2 class="mb-0"><?php echo count($feesExpiringSoon); ?></h2>
                <small class="text-muted">prossimi 30 giorni</small>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card border-danger">
            <div class="card-body">
                <h5 class="card-title text-danger">
                    <i class="bi bi-person-x"></i> Soci Morosi
                </h5>
                <h2 class="mb-0"><?php echo $morosiCount; ?></h2>
                <small class="text-muted">quote non pagate</small>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card border-info">
            <div class="card-body">
                <h5 class="card-title text-info">
                    <i class="bi bi-hourglass-split"></i> Da Incassare
                </h5>
                <h2 class="mb-0"><?php echo formatAmount($totalPending); ?></h2>
                <small class="text-muted">quote in sospeso</small>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card border-success">
            <div class="card-body">
                <h5 class="card-title text-success">
                    <i class="bi bi-cash-stack"></i> Incassato
                </h5>
                <h2 class="mb-0"><?php echo formatAmount($totalCollected); ?></h2>
                <small class="text-muted"><?php echo e($currentYear['name']); ?></small>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

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
    <a href="<?php echo $basePath; ?>years.php" class="alert-link">Imposta un anno sociale</a>
</div>
<?php endif; ?>

<!-- Quick Actions (Admin Only) -->
<?php if (isAdmin()): ?>
<div class="row mt-3">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-lightning"></i> Azioni Rapide</h5>
            </div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?php echo $basePath; ?>bulk_fees.php" class="btn btn-primary">
                        <i class="bi bi-arrow-repeat"></i> Rinnovo Massivo Quote
                    </a>
                    <a href="<?php echo $basePath; ?>send_reminders.php" class="btn btn-warning">
                        <i class="bi bi-envelope-exclamation"></i> Invia Solleciti
                    </a>
                    <a href="<?php echo $basePath; ?>admin_email_templates.php" class="btn btn-info">
                        <i class="bi bi-envelope-paper"></i> Template Email
                    </a>
                    <?php if ($queuedEmails > 0): ?>
                        <span class="badge bg-danger rounded-pill ms-2" style="padding: 8px 12px;">
                            <?php echo $queuedEmails; ?> email in coda
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Recent Members and Movements -->
<div class="row mt-4">
    <!-- Upcoming Events -->
    <?php if (!empty($upcomingEvents)): ?>
    <div class="col-md-6 mb-3">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-calendar-event text-primary"></i> Prossimi Eventi</h5>
                    <a href="<?php echo $basePath; ?>events.php" class="btn btn-sm btn-outline-primary">Vedi Tutti</a>
                </div>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <?php foreach ($upcomingEvents as $evt): 
                        $modeIcon = '🏢';
                        if ($evt['event_mode'] == 'online') $modeIcon = '💻';
                        elseif ($evt['event_mode'] == 'hybrid') $modeIcon = '🔄';
                    ?>
                    <a href="<?php echo $basePath; ?>event_view.php?id=<?php echo $evt['id']; ?>" 
                       class="list-group-item list-group-item-action">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1">
                                <span style="font-size: 1.2em;"><?php echo $modeIcon; ?></span>
                                <?php echo h($evt['title']); ?>
                            </h6>
                            <small><?php echo formatDate($evt['event_date']); ?></small>
                        </div>
                        <p class="mb-1 small text-muted">
                            <?php if ($evt['event_time']): ?>
                                <i class="bi bi-clock"></i> <?php echo substr($evt['event_time'], 0, 5); ?>
                            <?php endif; ?>
                            <?php if ($evt['event_mode'] == 'in_person' && $evt['location']): ?>
                                | <i class="bi bi-geo-alt"></i> <?php echo h($evt['location']); ?>
                            <?php elseif ($evt['event_mode'] == 'online' && $evt['online_platform']): ?>
                                | <i class="bi bi-camera-video"></i> <?php echo h($evt['online_platform']); ?>
                            <?php endif; ?>
                        </p>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($currentYear && !empty($morosiList)): ?>
    <div class="col-md-6 mb-3">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-exclamation-circle text-danger"></i> Soci Morosi</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Socio</th>
                                <th>Scadenza</th>
                                <th>Importo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($morosiList as $moroso): ?>
                            <tr>
                                <td>
                                    <a href="<?php echo $basePath; ?>member_edit.php?id=<?php echo $moroso['id']; ?>">
                                        <?php echo h($moroso['first_name'] . ' ' . $moroso['last_name']); ?>
                                    </a>
                                </td>
                                <td>
                                    <span class="text-danger"><?php echo formatDate($moroso['due_date']); ?></span>
                                </td>
                                <td><?php echo formatAmount($moroso['amount']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-center mt-2">
                    <a href="<?php echo $basePath; ?>member_fees.php?status=overdue" class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-list"></i> Vedi Tutti i Morosi
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($currentYear && !empty($recentPaidFees)): ?>
    <div class="col-md-6 mb-3">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-check-circle text-success"></i> Ultime Quote Pagate</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Socio</th>
                                <th>Data</th>
                                <th>Importo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentPaidFees as $paidFee): ?>
                            <tr>
                                <td>
                                    <a href="<?php echo $basePath; ?>member_edit.php?id=<?php echo $paidFee['member_id']; ?>">
                                        <?php echo h($paidFee['first_name'] . ' ' . $paidFee['last_name']); ?>
                                    </a>
                                </td>
                                <td><?php echo formatDate($paidFee['paid_date']); ?></td>
                                <td class="text-success"><?php echo formatAmount($paidFee['amount']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-center mt-2">
                    <a href="<?php echo $basePath; ?>member_fees.php?status=paid" class="btn btn-sm btn-outline-success">
                        <i class="bi bi-list"></i> Vedi Tutte le Quote Pagate
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="col-md-6 mb-3">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-people"></i> Ultimi Soci Registrati</h5>
            </div>
            <div class="card-body">
                <?php if (empty($recentMembers)): ?>
                    <p class="text-muted">Nessun socio registrato.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>Stato</th>
                                    <th>Data</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentMembers as $member): ?>
                                <tr>
                                    <td><?php echo h($member['first_name'] . ' ' . $member['last_name']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $member['status'] === 'attivo' ? 'success' : 'secondary'; ?>">
                                            <?php echo h(ucfirst($member['status'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo formatDate($member['registration_date']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-center mt-2">
                        <a href="<?php echo $basePath; ?>members.php" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-list"></i> Vedi Tutti i Soci
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 mb-3">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-cash-coin"></i> Ultimi Movimenti</h5>
            </div>
            <div class="card-body">
                <ul class="nav nav-tabs mb-3" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="tab" href="#recent-income">Entrate</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#recent-expenses">Uscite</a>
                    </li>
                </ul>
                
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="recent-income">
                        <?php if (empty($recentIncome)): ?>
                            <p class="text-muted">Nessuna entrata registrata.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <tbody>
                                        <?php foreach ($recentIncome as $inc): ?>
                                        <tr>
                                            <td><?php echo formatDate($inc['transaction_date']); ?></td>
                                            <td><?php echo h($inc['category_name']); ?></td>
                                            <td class="text-end text-success fw-bold">+<?php echo formatAmount($inc['amount']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="tab-pane fade" id="recent-expenses">
                        <?php if (empty($recentExpenses)): ?>
                            <p class="text-muted">Nessuna uscita registrata.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <tbody>
                                        <?php foreach ($recentExpenses as $exp): ?>
                                        <tr>
                                            <td><?php echo formatDate($exp['transaction_date']); ?></td>
                                            <td><?php echo h($exp['category_name']); ?></td>
                                            <td class="text-end text-danger fw-bold">-<?php echo formatAmount($exp['amount']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="text-center mt-2">
                    <a href="<?php echo $basePath; ?>finance.php" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-list"></i> Vedi Tutti i Movimenti
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Section -->
<div class="row mt-4">
    <div class="col-md-12">
        <h4 class="mb-3"><i class="bi bi-graph-up"></i> Grafici e Statistiche</h4>
    </div>
</div>

<div class="row">
    <!-- Financial Trend Chart -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-graph-up"></i> Andamento Finanziario (12 mesi)</h5>
            </div>
            <div class="card-body">
                <canvas id="financialTrendChart" style="max-height: 300px;"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Income by Category Chart -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-pie-chart"></i> Entrate per Categoria</h5>
            </div>
            <div class="card-body">
                <canvas id="incomeByCategoryChart" style="max-height: 300px;"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Members by Status Chart -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bi bi-people"></i> Soci per Stato</h5>
            </div>
            <div class="card-body">
                <canvas id="membersByStatusChart" style="max-height: 300px;"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Fees Status Chart -->
    <?php if ($currentYear): ?>
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="bi bi-credit-card"></i> Stato Quote Anno Corrente</h5>
            </div>
            <div class="card-body">
                <canvas id="feesStatusChart" style="max-height: 300px;"></canvas>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Quick Actions -->
<div class="row mt-4 mb-4">
    <div class="col-md-4 mb-3">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="bi bi-person-plus text-primary" style="font-size: 3rem;"></i>
                <h5 class="mt-3">Nuovo Socio</h5>
                <a href="<?php echo $basePath; ?>member_edit.php" class="btn btn-primary">
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
                <a href="<?php echo $basePath; ?>finance.php?action=add" class="btn btn-success">
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
                <a href="<?php echo $basePath; ?>reports.php" class="btn btn-info">
                    <i class="bi bi-eye"></i> Visualizza
                </a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Fetch dashboard statistics
fetch('<?php echo $basePath; ?>api/dashboard_stats.php')
    .then(response => response.json())
    .then(data => {
        // Financial Trend Chart (Line)
        if (data.financial_trend) {
            const ftCtx = document.getElementById('financialTrendChart');
            if (ftCtx) {
                new Chart(ftCtx, {
                    type: 'line',
                    data: {
                        labels: data.financial_trend.labels,
                        datasets: [
                            {
                                label: 'Entrate',
                                data: data.financial_trend.income,
                                borderColor: '#28a745',
                                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                                tension: 0.4,
                                fill: true
                            },
                            {
                                label: 'Uscite',
                                data: data.financial_trend.expenses,
                                borderColor: '#dc3545',
                                backgroundColor: 'rgba(220, 53, 69, 0.1)',
                                tension: 0.4,
                                fill: true
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: {
                                position: 'top'
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let label = context.dataset.label || '';
                                        if (label) {
                                            label += ': ';
                                        }
                                        label += new Intl.NumberFormat('it-IT', {
                                            style: 'currency',
                                            currency: 'EUR'
                                        }).format(context.parsed.y);
                                        return label;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return '€ ' + value.toLocaleString('it-IT');
                                    }
                                }
                            }
                        }
                    }
                });
            }
        }
        
        // Income by Category Chart (Doughnut)
        if (data.income_by_category && data.income_by_category.data.length > 0) {
            const icCtx = document.getElementById('incomeByCategoryChart');
            if (icCtx) {
                // Generate colors
                const colors = [
                    '#28a745', '#007bff', '#ffc107', '#dc3545', '#17a2b8',
                    '#6f42c1', '#fd7e14', '#20c997', '#e83e8c', '#6c757d'
                ];
                
                new Chart(icCtx, {
                    type: 'doughnut',
                    data: {
                        labels: data.income_by_category.labels,
                        datasets: [{
                            data: data.income_by_category.data,
                            backgroundColor: colors.slice(0, data.income_by_category.labels.length),
                            borderWidth: 2,
                            borderColor: '#fff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: {
                                position: 'right'
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let label = context.label || '';
                                        if (label) {
                                            label += ': ';
                                        }
                                        label += new Intl.NumberFormat('it-IT', {
                                            style: 'currency',
                                            currency: 'EUR'
                                        }).format(context.parsed);
                                        
                                        // Add percentage
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = ((context.parsed / total) * 100).toFixed(1);
                                        label += ' (' + percentage + '%)';
                                        
                                        return label;
                                    }
                                }
                            }
                        }
                    }
                });
            }
        }
        
        // Members by Status Chart (Bar)
        if (data.members_by_status) {
            const mbsCtx = document.getElementById('membersByStatusChart');
            if (mbsCtx) {
                new Chart(mbsCtx, {
                    type: 'bar',
                    data: {
                        labels: data.members_by_status.labels,
                        datasets: [{
                            label: 'Numero Soci',
                            data: data.members_by_status.data,
                            backgroundColor: data.members_by_status.colors,
                            borderWidth: 1,
                            borderColor: '#fff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        }
                    }
                });
            }
        }
        
        // Fees Status Chart (Doughnut)
        if (data.fees_status && data.fees_status.data.length > 0) {
            const fsCtx = document.getElementById('feesStatusChart');
            if (fsCtx) {
                new Chart(fsCtx, {
                    type: 'doughnut',
                    data: {
                        labels: data.fees_status.labels,
                        datasets: [{
                            data: data.fees_status.data,
                            backgroundColor: data.fees_status.colors,
                            borderWidth: 2,
                            borderColor: '#fff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: {
                                position: 'right'
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let label = context.label || '';
                                        if (label) {
                                            label += ': ';
                                        }
                                        label += context.parsed;
                                        
                                        // Add percentage
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        if (total > 0) {
                                            const percentage = ((context.parsed / total) * 100).toFixed(1);
                                            label += ' (' + percentage + '%)';
                                        }
                                        
                                        return label;
                                    }
                                }
                            }
                        }
                    }
                });
            }
        }
    })
    .catch(error => {
        console.error('Error loading dashboard stats:', error);
    });
</script>

<?php include __DIR__ . '/inc/footer.php'; ?>
