<?php
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

$pageTitle = 'Dashboard';

// Get member fees info
$currentYear = getCurrentSocialYear();
$memberFees = [];
if ($currentYear) {
    $memberFees = getMemberFees($member['id'], $currentYear['id']);
}

// Get member groups
$stmt = $pdo->prepare("
    SELECT mg.* 
    FROM " . table('member_groups') . " mg
    INNER JOIN " . table('member_group_members') . " mgm ON mg.id = mgm.group_id
    WHERE mgm.member_id = ? AND mg.is_active = 1
    ORDER BY mg.name
");
$stmt->execute([$member['id']]);
$memberGroups = $stmt->fetchAll();

include __DIR__ . '/inc/header.php';
?>

<div class="row">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">
                    <i class="bi bi-house-heart"></i> Benvenuto/a, <?php echo h($member['first_name']); ?>!
                </h5>
                <p class="card-text text-muted">
                    Questo è il tuo portale riservato per gestire la tua partecipazione all'associazione.
                </p>
                
                <?php if ($member['last_portal_login']): ?>
                    <p class="small text-muted mb-0">
                        <i class="bi bi-clock"></i> Ultimo accesso: <?php echo formatDate($member['last_portal_login']); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-currency-euro"></i> Quote Associative</h6>
            </div>
            <div class="card-body">
                <?php if (empty($memberFees)): ?>
                    <p class="text-muted mb-0">Nessuna quota da pagare al momento.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Anno</th>
                                    <th>Importo</th>
                                    <th>Scadenza</th>
                                    <th>Stato</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($memberFees as $fee): ?>
                                    <tr>
                                        <td><?php echo h($fee['year_name'] ?? ''); ?></td>
                                        <td><?php echo formatAmount($fee['amount']); ?></td>
                                        <td><?php echo formatDate($fee['due_date']); ?></td>
                                        <td>
                                            <?php if ($fee['status'] === 'paid'): ?>
                                                <span class="badge bg-success">Pagata</span>
                                            <?php elseif ($fee['status'] === 'overdue'): ?>
                                                <span class="badge bg-danger">Scaduta</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">In attesa</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-card-heading"></i> Tesserino</h6>
            </div>
            <div class="card-body text-center">
                <?php if (!empty($member['photo_url'])): ?>
                    <img src="<?php echo h($member['photo_url']); ?>" alt="Foto" class="member-photo mb-3">
                <?php else: ?>
                    <div class="member-photo-placeholder mb-3">
                        <i class="bi bi-person-circle" style="font-size: 3rem;"></i>
                        <small>Nessuna foto</small>
                    </div>
                <?php endif; ?>
                <div>
                    <a href="<?php echo h($basePath); ?>portal/card.php" class="btn btn-primary btn-sm">
                        <i class="bi bi-card-heading"></i> Visualizza Tesserino
                    </a>
                </div>
            </div>
        </div>
        
        <?php if (!empty($memberGroups)): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-people"></i> I miei Gruppi</h6>
                </div>
                <div class="card-body">
                    <?php foreach ($memberGroups as $group): ?>
                        <span class="badge mb-2" style="background-color: <?php echo h($group['color']); ?>">
                            <?php echo h($group['name']); ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-info-circle"></i> Dati Socio</h6>
            </div>
            <div class="card-body">
                <p class="mb-1">
                    <strong>Numero tessera:</strong><br>
                    <?php echo h($member['membership_number'] ?? 'N/A'); ?>
                </p>
                <p class="mb-1">
                    <strong>Iscritto dal:</strong><br>
                    <?php echo formatDate($member['registration_date']); ?>
                </p>
                <p class="mb-0">
                    <strong>Stato:</strong><br>
                    <span class="badge bg-success"><?php echo h(ucfirst($member['status'])); ?></span>
                </p>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/inc/footer.php'; ?>
