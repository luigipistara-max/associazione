<?php
/**
 * Export Active Members (with paid fees)
 */

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/db.php';

requireLogin();

$pageTitle = 'Export Soci Attivi';
$basePath = $config['app']['base_path'];

// Handle export
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    
    $socialYearId = $_POST['social_year_id'] ?? null;
    $format = $_POST['format'] ?? 'csv';
    $fields = $_POST['fields'] ?? [];
    
    if (empty($socialYearId)) {
        setFlashMessage('Seleziona un anno sociale', 'danger');
    } elseif (empty($fields)) {
        setFlashMessage('Seleziona almeno un campo da esportare', 'danger');
    } else {
        // Get year info for filename
        $stmt = $pdo->prepare("SELECT name FROM " . table('social_years') . " WHERE id = ?");
        $stmt->execute([$socialYearId]);
        $year = $stmt->fetch();
        $yearName = $year ? preg_replace('/[^a-zA-Z0-9_-]/', '_', $year['name']) : 'anno';
        
        // Export data
        $exportData = exportActiveMembersCsv($socialYearId, $fields);
        
        if ($format === 'csv') {
            $filename = "soci_attivi_{$yearName}_" . date('Y-m-d') . ".csv";
            exportCsv($filename, $exportData['data'], $exportData['headers']);
        }
        // Excel format could be added here in the future
    }
}

// Get social years
$socialYears = getSocialYears();
$currentYear = getCurrentSocialYear();

include __DIR__ . '/inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="bi bi-download"></i> <?php echo h($pageTitle); ?></h2>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Esporta Soci in Regola con le Quote</h5>
    </div>
    <div class="card-body">
        <p class="text-muted">
            Esporta la lista dei soci che hanno pagato la quota associativa per l'anno sociale selezionato.
        </p>
        
        <form method="POST">
            <?php echo csrfField(); ?>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Anno Sociale <span class="text-danger">*</span></label>
                    <select name="social_year_id" class="form-select" required>
                        <option value="">Seleziona anno...</option>
                        <?php foreach ($socialYears as $year): ?>
                            <option value="<?php echo $year['id']; ?>" <?php echo ($currentYear && $year['id'] == $currentYear['id']) ? 'selected' : ''; ?>>
                                <?php echo h($year['name']); ?>
                                <?php if ($year['is_current']): ?>
                                    <span class="badge bg-success">Corrente</span>
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Formato Export</label>
                    <select name="format" class="form-select">
                        <option value="csv">CSV</option>
                        <!-- <option value="excel">Excel (XLSX)</option> -->
                    </select>
                    <small class="text-muted">Il formato Excel sarà disponibile in futuro</small>
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Campi da Includere <span class="text-danger">*</span></label>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="fields[]" value="membership_number" id="field1" checked>
                            <label class="form-check-label" for="field1">Numero Tessera</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="fields[]" value="first_name" id="field2" checked>
                            <label class="form-check-label" for="field2">Nome</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="fields[]" value="last_name" id="field3" checked>
                            <label class="form-check-label" for="field3">Cognome</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="fields[]" value="fiscal_code" id="field4" checked>
                            <label class="form-check-label" for="field4">Codice Fiscale</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="fields[]" value="email" id="field5" checked>
                            <label class="form-check-label" for="field5">Email</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="fields[]" value="phone" id="field6" checked>
                            <label class="form-check-label" for="field6">Telefono</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="fields[]" value="paid_date" id="field7" checked>
                            <label class="form-check-label" for="field7">Data Pagamento Quota</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="fields[]" value="amount" id="field8" checked>
                            <label class="form-check-label" for="field8">Importo Versato</label>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="d-flex justify-content-between align-items-center">
                <a href="<?php echo h($basePath); ?>members.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Torna ai Soci
                </a>
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-download"></i> Esporta
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Preview Section -->
<?php if (!empty($socialYears)): ?>
<div class="card mt-3">
    <div class="card-header">
        <h5 class="mb-0">Statistiche Anno Corrente</h5>
    </div>
    <div class="card-body">
        <?php if ($currentYear): ?>
            <?php
            $activeMembers = getActiveMembers($currentYear['id']);
            $morosi = getMorosi($currentYear['id']);
            ?>
            <div class="row text-center">
                <div class="col-md-4">
                    <div class="p-3">
                        <h3 class="text-success"><?php echo count($activeMembers); ?></h3>
                        <p class="text-muted mb-0">Soci in Regola</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-3">
                        <h3 class="text-danger"><?php echo count($morosi); ?></h3>
                        <p class="text-muted mb-0">Soci Morosi</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-3">
                        <h3 class="text-primary"><?php echo count($activeMembers) + count($morosi); ?></h3>
                        <p class="text-muted mb-0">Totale con Quote</p>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <p class="text-muted text-center">Nessun anno sociale corrente impostato.</p>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/inc/footer.php'; ?>
