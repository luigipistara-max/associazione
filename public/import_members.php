<?php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/db.php';

requireLogin();

$pageTitle = 'Importa Soci da CSV';
$pdo = getDbConnection();
$errors = [];
$imported = 0;
$skipped = 0;
$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    
    if (!verifyCsrfToken($token)) {
        $errors[] = 'Token di sicurezza non valido';
    } elseif (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Errore nel caricamento del file';
    } else {
        $file = $_FILES['csv_file']['tmp_name'];
        $separator = $_POST['separator'] ?? ';';
        
        if (($handle = fopen($file, 'r')) !== false) {
            // Skip header row
            $header = fgetcsv($handle, 0, $separator);
            
            $line = 1;
            while (($data = fgetcsv($handle, 0, $separator)) !== false) {
                $line++;
                
                // Expected format: first_name;last_name;tax_code;birth_date;birth_place;email;phone;address;city;postal_code;notes
                if (count($data) < 3) {
                    $results[] = ['line' => $line, 'status' => 'error', 'message' => 'Formato non valido'];
                    $skipped++;
                    continue;
                }
                
                $firstName = trim($data[0] ?? '');
                $lastName = trim($data[1] ?? '');
                $taxCode = strtoupper(trim($data[2] ?? ''));
                $birthDate = !empty($data[3]) ? $data[3] : null;
                $birthPlace = trim($data[4] ?? '');
                $email = trim($data[5] ?? '');
                $phone = trim($data[6] ?? '');
                $address = trim($data[7] ?? '');
                $city = trim($data[8] ?? '');
                $postalCode = trim($data[9] ?? '');
                $notes = trim($data[10] ?? '');
                
                // Validation
                if (empty($firstName) || empty($lastName) || empty($taxCode)) {
                    $results[] = ['line' => $line, 'status' => 'error', 'message' => 'Campi obbligatori mancanti'];
                    $skipped++;
                    continue;
                }
                
                if (!validateTaxCode($taxCode)) {
                    $results[] = ['line' => $line, 'status' => 'error', 'message' => "CF non valido: $taxCode"];
                    $skipped++;
                    continue;
                }
                
                // Check for duplicates
                $stmt = $pdo->prepare("SELECT id FROM members WHERE tax_code = ?");
                $stmt->execute([$taxCode]);
                if ($stmt->fetch()) {
                    $results[] = ['line' => $line, 'status' => 'skipped', 'message' => "CF già presente: $taxCode"];
                    $skipped++;
                    continue;
                }
                
                // Convert date format if needed (d/m/Y to Y-m-d)
                if ($birthDate && strpos($birthDate, '/') !== false) {
                    $parts = explode('/', $birthDate);
                    if (count($parts) === 3) {
                        $birthDate = sprintf('%04d-%02d-%02d', $parts[2], $parts[1], $parts[0]);
                    }
                }
                
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO members (
                            first_name, last_name, tax_code, birth_date, birth_place,
                            email, phone, address, city, postal_code, notes,
                            registration_date, status
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), 'attivo')
                    ");
                    $stmt->execute([
                        $firstName, $lastName, $taxCode,
                        $birthDate ?: null, $birthPlace ?: null,
                        $email ?: null, $phone ?: null,
                        $address ?: null, $city ?: null, $postalCode ?: null,
                        $notes ?: null
                    ]);
                    
                    $results[] = ['line' => $line, 'status' => 'success', 'message' => "$firstName $lastName"];
                    $imported++;
                } catch (PDOException $e) {
                    $results[] = ['line' => $line, 'status' => 'error', 'message' => $e->getMessage()];
                    $skipped++;
                }
            }
            
            fclose($handle);
            
            if ($imported > 0) {
                setFlashMessage("Importati $imported soci con successo" . ($skipped > 0 ? ", $skipped saltati" : ''), 'success');
            } elseif ($skipped > 0) {
                setFlashMessage("Nessun socio importato, $skipped righe saltate", 'warning');
            }
        } else {
            $errors[] = 'Impossibile aprire il file';
        }
    }
}

include __DIR__ . '/inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="bi bi-upload"></i> Importa Soci da CSV</h2>
    <a href="/members.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Torna ai Soci
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
                            <th>Obbligatorio</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><code>first_name</code></td><td><span class="badge bg-danger">Sì</span></td></tr>
                        <tr><td><code>last_name</code></td><td><span class="badge bg-danger">Sì</span></td></tr>
                        <tr><td><code>tax_code</code></td><td><span class="badge bg-danger">Sì</span></td></tr>
                        <tr><td><code>birth_date</code></td><td>No</td></tr>
                        <tr><td><code>birth_place</code></td><td>No</td></tr>
                        <tr><td><code>email</code></td><td>No</td></tr>
                        <tr><td><code>phone</code></td><td>No</td></tr>
                        <tr><td><code>address</code></td><td>No</td></tr>
                        <tr><td><code>city</code></td><td>No</td></tr>
                        <tr><td><code>postal_code</code></td><td>No</td></tr>
                        <tr><td><code>notes</code></td><td>No</td></tr>
                    </tbody>
                </table>
                
                <div class="alert alert-info mt-3">
                    <strong>Esempio:</strong><br>
                    <small><code>first_name;last_name;tax_code;birth_date;birth_place;email;phone;address;city;postal_code;notes</code></small>
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
