<?php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/db.php';

requireLogin();

$pageTitle = 'Importa Movimenti da CSV';

$errors = [];
$imported = 0;
$skipped = 0;
$results = [];

// Get data for form
$socialYears = getSocialYears();
$incomeCategories = getIncomeCategories();
$expenseCategories = getExpenseCategories();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    
    if (!verifyCsrfToken($token)) {
        $errors[] = 'Token di sicurezza non valido';
    } elseif (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Errore nel caricamento del file';
    } else {
        $file = $_FILES['csv_file']['tmp_name'];
        $separator = $_POST['separator'] ?? ';';
        $defaultYearId = !empty($_POST['social_year_id']) ? (int)$_POST['social_year_id'] : null;
        
        // Build category maps
        $incomeCategoryMap = [];
        foreach ($incomeCategories as $cat) {
            $incomeCategoryMap[strtolower($cat['name'])] = $cat['id'];
        }
        $expenseCategoryMap = [];
        foreach ($expenseCategories as $cat) {
            $expenseCategoryMap[strtolower($cat['name'])] = $cat['id'];
        }
        
        if (($handle = fopen($file, 'r')) !== false) {
            // Skip header row
            $header = fgetcsv($handle, 0, $separator);
            
            $line = 1;
            while (($data = fgetcsv($handle, 0, $separator)) !== false) {
                $line++;
                
                // Expected format: type;paid_at;category;description;amount;member_tax_code
                if (count($data) < 5) {
                    $results[] = ['line' => $line, 'status' => 'error', 'message' => 'Formato non valido'];
                    $skipped++;
                    continue;
                }
                
                $type = strtolower(trim($data[0] ?? ''));
                $paidAt = trim($data[1] ?? '');
                $categoryName = trim($data[2] ?? '');
                $description = trim($data[3] ?? '');
                $amount = str_replace(',', '.', trim($data[4] ?? '0'));
                $memberTaxCode = isset($data[5]) ? strtoupper(trim($data[5])) : '';
                
                // Validation
                if (!in_array($type, ['income', 'expense'])) {
                    $results[] = ['line' => $line, 'status' => 'error', 'message' => "Tipo non valido: $type"];
                    $skipped++;
                    continue;
                }
                
                if (empty($description) || empty($amount) || $amount <= 0) {
                    $results[] = ['line' => $line, 'status' => 'error', 'message' => 'Campi obbligatori mancanti o non validi'];
                    $skipped++;
                    continue;
                }
                
                // Find category ID
                $categoryMap = $type === 'income' ? $incomeCategoryMap : $expenseCategoryMap;
                $categoryId = $categoryMap[strtolower($categoryName)] ?? null;
                
                if (!$categoryId) {
                    $results[] = ['line' => $line, 'status' => 'error', 'message' => "Categoria non trovata: $categoryName"];
                    $skipped++;
                    continue;
                }
                
                // Convert date format if needed (d/m/Y to Y-m-d)
                if ($paidAt && strpos($paidAt, '/') !== false) {
                    $parts = explode('/', $paidAt);
                    if (count($parts) === 3) {
                        $paidAt = sprintf('%04d-%02d-%02d', $parts[2], $parts[1], $parts[0]);
                    }
                }
                
                // Find member by tax code if provided
                $memberId = null;
                if ($type === 'income' && !empty($memberTaxCode)) {
                    $stmt = $pdo->prepare("SELECT id FROM " . table('members') . " WHERE tax_code = ?");
                    $stmt->execute([$memberTaxCode]);
                    $member = $stmt->fetch();
                    if ($member) {
                        $memberId = $member['id'];
                    }
                }
                
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO movements (
                            type, category_id, description, amount, paid_at,
                            social_year_id, member_id
                        ) VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $type, $categoryId, $description, $amount, $paidAt ?: date('Y-m-d'),
                        $defaultYearId, $memberId
                    ]);
                    
                    $results[] = ['line' => $line, 'status' => 'success', 'message' => $description];
                    $imported++;
                } catch (PDOException $e) {
                    $results[] = ['line' => $line, 'status' => 'error', 'message' => $e->getMessage()];
                    $skipped++;
                }
            }
            
            fclose($handle);
            
            if ($imported > 0) {
                setFlashMessage("Importati $imported movimenti con successo" . ($skipped > 0 ? ", $skipped saltati" : ''), 'success');
            } elseif ($skipped > 0) {
                setFlashMessage("Nessun movimento importato, $skipped righe saltate", 'warning');
            }
        } else {
            $errors[] = 'Impossibile aprire il file';
        }
    }
}

include __DIR__ . '/inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="bi bi-upload"></i> Importa Movimenti da CSV</h2>
    <a href="/finance.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Torna ai Movimenti
    </a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo e($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Carica File CSV</h5>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo e(generateCsrfToken()); ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">File CSV</label>
                        <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                        <small class="text-muted">Max 5MB</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Anno Sociale (opzionale)</label>
                        <select name="social_year_id" class="form-select">
                            <option value="">Nessuno</option>
                            <?php foreach ($socialYears as $year): ?>
                                <option value="<?php echo $year['id']; ?>" <?php echo $year['is_current'] ? 'selected' : ''; ?>>
                                    <?php echo e($year['name']); ?>
                                    <?php if ($year['is_current']): ?>(Corrente)<?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Assegna tutti i movimenti a questo anno</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Separatore</label>
                        <select name="separator" class="form-select">
                            <option value=";">Punto e virgola (;)</option>
                            <option value=",">Virgola (,)</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-upload"></i> Importa
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Formato File</h5>
            </div>
            <div class="card-body">
                <p>Il file CSV deve contenere le seguenti colonne (con intestazione):</p>
                
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Colonna</th>
                            <th>Descrizione</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><code>type</code></td><td>income o expense</td></tr>
                        <tr><td><code>paid_at</code></td><td>Data (YYYY-MM-DD o DD/MM/YYYY)</td></tr>
                        <tr><td><code>category</code></td><td>Nome categoria (deve esistere)</td></tr>
                        <tr><td><code>description</code></td><td>Descrizione movimento</td></tr>
                        <tr><td><code>amount</code></td><td>Importo (usa . o ,)</td></tr>
                        <tr><td><code>member_tax_code</code></td><td>CF socio (opzionale, solo entrate)</td></tr>
                    </tbody>
                </table>
                
                <div class="alert alert-info mt-3">
                    <strong>Esempio:</strong><br>
                    <small><code>type;paid_at;category;description;amount;member_tax_code</code></small><br>
                    <small><code>income;2024-01-15;Quote associative;Quota 2024;50.00;RSSMRA80A01H501U</code></small>
                </div>
                
                <div class="alert alert-warning">
                    <strong>Categorie disponibili:</strong><br>
                    <small>
                        <strong>Entrate:</strong> 
                        <?php echo implode(', ', array_column($incomeCategories, 'name')); ?><br>
                        <strong>Uscite:</strong> 
                        <?php echo implode(', ', array_column($expenseCategories, 'name')); ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($results)): ?>
<div class="card mt-3">
    <div class="card-header">
        <h5 class="mb-0">Risultati Importazione</h5>
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            <strong>Riepilogo:</strong> <?php echo $imported; ?> importati, <?php echo $skipped; ?> saltati
        </div>
        
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Riga</th>
                        <th>Stato</th>
                        <th>Messaggio</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $result): ?>
                    <tr>
                        <td><?php echo $result['line']; ?></td>
                        <td>
                            <?php if ($result['status'] === 'success'): ?>
                                <span class="badge bg-success">Importato</span>
                            <?php elseif ($result['status'] === 'skipped'): ?>
                                <span class="badge bg-warning">Saltato</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Errore</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo e($result['message']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/inc/footer.php'; ?>
