<?php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/functions.php';

requireAdmin();

$pageTitle = 'Importa Movimenti';

$errors = [];
$imported = 0;
$skipped = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    checkCsrf();
    
    $file = $_FILES['csv_file'];
    $type = $_POST['movement_type'] ?? 'income';
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $delimiter = $_POST['delimiter'] ?? ';';
        $skipFirstRow = isset($_POST['skip_first_row']);
        
        $rows = parseCsvFile($file['tmp_name'], $delimiter);
        
        if (empty($rows)) {
            $errors[] = "File CSV vuoto o non valido";
        } else {
            $startRow = $skipFirstRow ? 1 : 0;
            
            for ($i = $startRow; $i < count($rows); $i++) {
                $row = $rows[$i];
                
                // Expected format for income: social_year_id, category_id, member_fiscal_code, amount, payment_method, receipt_number, transaction_date, notes
                // Expected format for expense: social_year_id, category_id, amount, payment_method, receipt_number, transaction_date, notes
                
                if (count($row) < 4) {
                    $skipped++;
                    continue;
                }
                
                $socialYearId = (int)($row[0] ?? 0);
                $categoryId = (int)($row[1] ?? 0);
                
                if ($type === 'income') {
                    $memberFiscalCode = strtoupper(trim($row[2] ?? ''));
                    $amount = (float)str_replace(',', '.', trim($row[3] ?? '0'));
                    $paymentMethod = trim($row[4] ?? '');
                    $receiptNumber = trim($row[5] ?? '');
                    $transactionDate = trim($row[6] ?? '');
                    $notes = trim($row[7] ?? '');
                } else {
                    $amount = (float)str_replace(',', '.', trim($row[2] ?? '0'));
                    $paymentMethod = trim($row[3] ?? '');
                    $receiptNumber = trim($row[4] ?? '');
                    $transactionDate = trim($row[5] ?? '');
                    $notes = trim($row[6] ?? '');
                }
                
                // Validate
                if ($socialYearId <= 0 || $categoryId <= 0 || $amount <= 0 || empty($transactionDate)) {
                    $skipped++;
                    continue;
                }
                
                try {
                    if ($type === 'income') {
                        // Find member by fiscal code if provided
                        $memberId = null;
                        if ($memberFiscalCode) {
                            $stmt = $pdo->prepare("SELECT id FROM " . table('members') . " WHERE fiscal_code = ?");
                            $stmt->execute([$memberFiscalCode]);
                            $member = $stmt->fetch();
                            if ($member) {
                                $memberId = $member['id'];
                            }
                        }
                        
                        $stmt = $pdo->prepare("
                            INSERT INTO " . table('income') . " 
                            (social_year_id, category_id, member_id, amount, payment_method, receipt_number, transaction_date, notes) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$socialYearId, $categoryId, $memberId, $amount, $paymentMethod, $receiptNumber, $transactionDate, $notes]);
                    } else {
                        $stmt = $pdo->prepare("
                            INSERT INTO " . table('expenses') . " 
                            (social_year_id, category_id, amount, payment_method, receipt_number, transaction_date, notes) 
                            VALUES (?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$socialYearId, $categoryId, $amount, $paymentMethod, $receiptNumber, $transactionDate, $notes]);
                    }
                    $imported++;
                } catch (PDOException $e) {
                    $skipped++;
                }
            }
            
            setFlash('success', "Importazione completata: $imported movimenti importati, $skipped saltati");
            redirect('finance.php?tab=' . $type);
        }
    } else {
        $errors[] = "Errore nel caricamento del file";
    }
}

// Load data for reference
try {
    $stmt = $pdo->query("SELECT id, name FROM " . table('social_years') . " ORDER BY start_date DESC");
    $socialYears = $stmt->fetchAll();
    
    $stmt = $pdo->query("SELECT id, name FROM " . table('income_categories') . " WHERE is_active = 1 ORDER BY sort_order, name");
    $incomeCategories = $stmt->fetchAll();
    
    $stmt = $pdo->query("SELECT id, name FROM " . table('expense_categories') . " WHERE is_active = 1 ORDER BY sort_order, name");
    $expenseCategories = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Errore database: " . htmlspecialchars($e->getMessage()));
}

include __DIR__ . '/inc/header.php';
?>

<?php displayFlash(); ?>

<div class="row mb-3">
    <div class="col">
        <h2><i class="bi bi-upload me-2"></i>Importa Movimenti da CSV</h2>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <strong>Errori:</strong>
    <ul class="mb-0">
        <?php foreach ($errors as $error): ?>
            <li><?= h($error) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">Carica File CSV</h5>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <?= csrfField() ?>
                    
                    <div class="mb-3">
                        <label class="form-label">Tipo Movimento *</label>
                        <select name="movement_type" class="form-select" required>
                            <option value="income">Entrate</option>
                            <option value="expense">Uscite</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">File CSV *</label>
                        <input type="file" name="csv_file" class="form-control" accept=".csv,.txt" required>
                        <div class="form-text">Seleziona un file CSV con i movimenti</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Separatore</label>
                        <select name="delimiter" class="form-select">
                            <option value=";">Punto e virgola (;)</option>
                            <option value=",">Virgola (,)</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" name="skip_first_row" class="form-check-input" id="skipFirst" checked>
                            <label class="form-check-label" for="skipFirst">
                                Salta prima riga (intestazione)
                            </label>
                        </div>
                    </div>
                    
                    <div class="alert alert-warning">
                        <strong>Importante:</strong> Assicurati che gli ID degli anni sociali e delle categorie siano corretti.
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-upload me-1"></i>Importa
                        </button>
                        <a href="finance.php" class="btn btn-secondary">
                            <i class="bi bi-x-circle me-1"></i>Annulla
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card shadow-sm border-primary">
            <div class="card-header bg-primary text-white">
                <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Formato CSV Entrate</h6>
            </div>
            <div class="card-body">
                <ol class="small mb-0">
                    <li>ID Anno Sociale *</li>
                    <li>ID Categoria *</li>
                    <li>Codice Fiscale Socio</li>
                    <li>Importo * (es: 100.50)</li>
                    <li>Metodo Pagamento</li>
                    <li>Numero Ricevuta</li>
                    <li>Data (YYYY-MM-DD) *</li>
                    <li>Note</li>
                </ol>
            </div>
        </div>
        
        <div class="card shadow-sm border-danger mt-3">
            <div class="card-header bg-danger text-white">
                <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Formato CSV Uscite</h6>
            </div>
            <div class="card-body">
                <ol class="small mb-0">
                    <li>ID Anno Sociale *</li>
                    <li>ID Categoria *</li>
                    <li>Importo * (es: 100.50)</li>
                    <li>Metodo Pagamento</li>
                    <li>Numero Ricevuta</li>
                    <li>Data (YYYY-MM-DD) *</li>
                    <li>Note</li>
                </ol>
            </div>
        </div>
        
        <!-- Reference Tables -->
        <div class="card shadow-sm mt-3">
            <div class="card-header bg-secondary text-white">
                <h6 class="mb-0"><i class="bi bi-list-ul me-2"></i>Riferimenti</h6>
            </div>
            <div class="card-body">
                <h6 class="small">Anni Sociali:</h6>
                <ul class="small mb-2">
                    <?php foreach ($socialYears as $year): ?>
                        <li>ID <?= $year['id'] ?>: <?= h($year['name']) ?></li>
                    <?php endforeach; ?>
                </ul>
                
                <h6 class="small">Categorie Entrate:</h6>
                <ul class="small mb-2">
                    <?php foreach ($incomeCategories as $cat): ?>
                        <li>ID <?= $cat['id'] ?>: <?= h($cat['name']) ?></li>
                    <?php endforeach; ?>
                </ul>
                
                <h6 class="small">Categorie Uscite:</h6>
                <ul class="small mb-0">
                    <?php foreach ($expenseCategories as $cat): ?>
                        <li>ID <?= $cat['id'] ?>: <?= h($cat['name']) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/inc/footer.php'; ?>
