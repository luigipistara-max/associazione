<?php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/functions.php';

requireAdmin();

$pageTitle = 'Importa Soci';

$errors = [];
$imported = 0;
$skipped = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    checkCsrf();
    
    $file = $_FILES['csv_file'];
    
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
                
                // Expected format: membership_number, first_name, last_name, fiscal_code, birth_date, birth_place,
                //                  email, phone, address, city, province, postal_code, registration_date, status
                
                if (count($row) < 4) {
                    $skipped++;
                    continue;
                }
                
                $membershipNumber = trim($row[0] ?? '');
                $firstName = trim($row[1] ?? '');
                $lastName = trim($row[2] ?? '');
                $fiscalCode = strtoupper(trim($row[3] ?? ''));
                $birthDate = trim($row[4] ?? '') ?: null;
                $birthPlace = trim($row[5] ?? '');
                $email = trim($row[6] ?? '');
                $phone = trim($row[7] ?? '');
                $address = trim($row[8] ?? '');
                $city = trim($row[9] ?? '');
                $province = strtoupper(trim($row[10] ?? ''));
                $postalCode = trim($row[11] ?? '');
                $registrationDate = trim($row[12] ?? '') ?: date('Y-m-d');
                $status = trim($row[13] ?? 'attivo');
                
                // Validate required fields
                if (empty($firstName) || empty($lastName) || empty($fiscalCode)) {
                    $skipped++;
                    continue;
                }
                
                if (!validateFiscalCode($fiscalCode)) {
                    $skipped++;
                    continue;
                }
                
                // Check if fiscal code already exists
                try {
                    $stmt = $pdo->prepare("SELECT id FROM " . table('members') . " WHERE fiscal_code = ?");
                    $stmt->execute([$fiscalCode]);
                    if ($stmt->fetch()) {
                        $skipped++;
                        continue;
                    }
                    
                    // Insert member
                    $stmt = $pdo->prepare("
                        INSERT INTO " . table('members') . " 
                        (membership_number, first_name, last_name, fiscal_code, birth_date, birth_place,
                         email, phone, address, city, province, postal_code, registration_date, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $membershipNumber ?: null, $firstName, $lastName, $fiscalCode,
                        $birthDate, $birthPlace, $email, $phone,
                        $address, $city, $province, $postalCode,
                        $registrationDate, $status
                    ]);
                    $imported++;
                } catch (PDOException $e) {
                    $skipped++;
                }
            }
            
            setFlash('success', "Importazione completata: $imported soci importati, $skipped saltati");
            redirect('members.php');
        }
    } else {
        $errors[] = "Errore nel caricamento del file";
    }
}

include __DIR__ . '/inc/header.php';
?>

<?php displayFlash(); ?>

<div class="row mb-3">
    <div class="col">
        <h2><i class="bi bi-upload me-2"></i>Importa Soci da CSV</h2>
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
                        <label class="form-label">File CSV *</label>
                        <input type="file" name="csv_file" class="form-control" accept=".csv,.txt" required>
                        <div class="form-text">Seleziona un file CSV con i dati dei soci</div>
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
                    
                    <div class="alert alert-info">
                        <strong>Nota:</strong> I soci con codice fiscale già esistente verranno saltati.
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-upload me-1"></i>Importa
                        </button>
                        <a href="members.php" class="btn btn-secondary">
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
                <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Formato CSV</h6>
            </div>
            <div class="card-body">
                <p class="small mb-2">Il file CSV deve contenere le seguenti colonne nell'ordine indicato:</p>
                <ol class="small mb-0">
                    <li>Numero Tessera</li>
                    <li>Nome *</li>
                    <li>Cognome *</li>
                    <li>Codice Fiscale *</li>
                    <li>Data Nascita (YYYY-MM-DD)</li>
                    <li>Luogo Nascita</li>
                    <li>Email</li>
                    <li>Telefono</li>
                    <li>Indirizzo</li>
                    <li>Città</li>
                    <li>Provincia (2 lettere)</li>
                    <li>CAP</li>
                    <li>Data Iscrizione (YYYY-MM-DD)</li>
                    <li>Stato (attivo/sospeso/cessato)</li>
                </ol>
                <p class="small mt-2 mb-0">* Campi obbligatori</p>
            </div>
        </div>
        
        <div class="card shadow-sm border-secondary mt-3">
            <div class="card-header bg-secondary text-white">
                <h6 class="mb-0"><i class="bi bi-file-earmark-text me-2"></i>Esempio CSV</h6>
            </div>
            <div class="card-body">
                <pre class="small mb-0" style="font-size: 10px;">Tessera;Nome;Cognome;CF;...
001;Mario;Rossi;RSSMRA80A01H501U;...
002;Luigi;Verdi;VRDLGU85B15F205Z;...</pre>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/inc/footer.php'; ?>
