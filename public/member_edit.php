<?php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/functions.php';

requireLogin();

$pageTitle = 'Modifica Socio';
$isEdit = isset($_GET['id']);
$member = null;

// Load member for editing
if ($isEdit) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM " . table('members') . " WHERE id = ?");
    $stmt->execute([$id]);
    $member = $stmt->fetch();
    
    if (!$member) {
        setFlash('error', 'Socio non trovato');
        redirect('members.php');
    }
} else {
    $pageTitle = 'Nuovo Socio';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    
    $membershipNumber = trim($_POST['membership_number'] ?? '');
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $fiscalCode = strtoupper(trim($_POST['fiscal_code'] ?? ''));
    $birthDate = $_POST['birth_date'] ?? null;
    $birthPlace = trim($_POST['birth_place'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $province = strtoupper(trim($_POST['province'] ?? ''));
    $postalCode = trim($_POST['postal_code'] ?? '');
    $registrationDate = $_POST['registration_date'] ?? null;
    $status = $_POST['status'] ?? 'attivo';
    $notes = trim($_POST['notes'] ?? '');
    
    $errors = [];
    
    // Validation
    if (empty($firstName)) $errors[] = "Nome obbligatorio";
    if (empty($lastName)) $errors[] = "Cognome obbligatorio";
    if (empty($fiscalCode)) $errors[] = "Codice fiscale obbligatorio";
    if (!validateFiscalCode($fiscalCode)) $errors[] = "Codice fiscale non valido";
    if (empty($registrationDate)) $errors[] = "Data iscrizione obbligatoria";
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Email non valida";
    if ($province && strlen($province) !== 2) $errors[] = "Provincia deve essere 2 lettere";
    
    // Check unique fiscal code
    if ($isEdit) {
        $stmt = $pdo->prepare("SELECT id FROM " . table('members') . " WHERE fiscal_code = ? AND id != ?");
        $stmt->execute([$fiscalCode, $id]);
    } else {
        $stmt = $pdo->prepare("SELECT id FROM " . table('members') . " WHERE fiscal_code = ?");
        $stmt->execute([$fiscalCode]);
    }
    if ($stmt->fetch()) {
        $errors[] = "Codice fiscale già esistente";
    }
    
    // Check unique membership number if provided
    if ($membershipNumber) {
        if ($isEdit) {
            $stmt = $pdo->prepare("SELECT id FROM " . table('members') . " WHERE membership_number = ? AND id != ?");
            $stmt->execute([$membershipNumber, $id]);
        } else {
            $stmt = $pdo->prepare("SELECT id FROM " . table('members') . " WHERE membership_number = ?");
            $stmt->execute([$membershipNumber]);
        }
        if ($stmt->fetch()) {
            $errors[] = "Numero tessera già esistente";
        }
    }
    
    if (empty($errors)) {
        try {
            if ($isEdit) {
                $stmt = $pdo->prepare("
                    UPDATE " . table('members') . " SET 
                        membership_number = ?, first_name = ?, last_name = ?, fiscal_code = ?,
                        birth_date = ?, birth_place = ?, email = ?, phone = ?,
                        address = ?, city = ?, province = ?, postal_code = ?,
                        registration_date = ?, status = ?, notes = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $membershipNumber ?: null, $firstName, $lastName, $fiscalCode,
                    $birthDate ?: null, $birthPlace, $email, $phone,
                    $address, $city, $province, $postalCode,
                    $registrationDate, $status, $notes,
                    $id
                ]);
                setFlash('success', 'Socio aggiornato con successo');
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO " . table('members') . " 
                    (membership_number, first_name, last_name, fiscal_code, birth_date, birth_place,
                     email, phone, address, city, province, postal_code, registration_date, status, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $membershipNumber ?: null, $firstName, $lastName, $fiscalCode, 
                    $birthDate ?: null, $birthPlace, $email, $phone,
                    $address, $city, $province, $postalCode,
                    $registrationDate, $status, $notes
                ]);
                setFlash('success', 'Socio creato con successo');
            }
            redirect('members.php');
        } catch (PDOException $e) {
            $errors[] = "Errore database: " . $e->getMessage();
        }
    }
}

include __DIR__ . '/inc/header.php';
?>

<?php displayFlash(); ?>

<div class="row mb-3">
    <div class="col">
        <h2><i class="bi bi-person me-2"></i><?= $isEdit ? 'Modifica Socio' : 'Nuovo Socio' ?></h2>
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

<form method="POST">
    <?= csrfField() ?>
    
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white">
            <h5 class="mb-0">Dati Anagrafici</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Numero Tessera</label>
                    <input type="text" name="membership_number" class="form-control" value="<?= h($member['membership_number'] ?? $_POST['membership_number'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Nome *</label>
                    <input type="text" name="first_name" class="form-control" value="<?= h($member['first_name'] ?? $_POST['first_name'] ?? '') ?>" required>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Cognome *</label>
                    <input type="text" name="last_name" class="form-control" value="<?= h($member['last_name'] ?? $_POST['last_name'] ?? '') ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Codice Fiscale *</label>
                    <input type="text" name="fiscal_code" class="form-control" value="<?= h($member['fiscal_code'] ?? $_POST['fiscal_code'] ?? '') ?>" maxlength="16" pattern="[A-Za-z0-9]{16}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Data di Nascita</label>
                    <input type="date" name="birth_date" class="form-control" value="<?= h($member['birth_date'] ?? $_POST['birth_date'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Luogo di Nascita</label>
                    <input type="text" name="birth_place" class="form-control" value="<?= h($member['birth_place'] ?? $_POST['birth_place'] ?? '') ?>">
                </div>
            </div>
        </div>
    </div>
    
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white">
            <h5 class="mb-0">Contatti</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= h($member['email'] ?? $_POST['email'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Telefono</label>
                    <input type="tel" name="phone" class="form-control" value="<?= h($member['phone'] ?? $_POST['phone'] ?? '') ?>">
                </div>
            </div>
        </div>
    </div>
    
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white">
            <h5 class="mb-0">Indirizzo</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-12">
                    <label class="form-label">Indirizzo</label>
                    <input type="text" name="address" class="form-control" value="<?= h($member['address'] ?? $_POST['address'] ?? '') ?>">
                </div>
                <div class="col-md-5">
                    <label class="form-label">Città</label>
                    <input type="text" name="city" class="form-control" value="<?= h($member['city'] ?? $_POST['city'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Provincia</label>
                    <input type="text" name="province" class="form-control" value="<?= h($member['province'] ?? $_POST['province'] ?? '') ?>" maxlength="2">
                </div>
                <div class="col-md-5">
                    <label class="form-label">CAP</label>
                    <input type="text" name="postal_code" class="form-control" value="<?= h($member['postal_code'] ?? $_POST['postal_code'] ?? '') ?>">
                </div>
            </div>
        </div>
    </div>
    
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white">
            <h5 class="mb-0">Stato Associativo</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Data Iscrizione *</label>
                    <input type="date" name="registration_date" class="form-control" value="<?= h($member['registration_date'] ?? $_POST['registration_date'] ?? date('Y-m-d')) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Stato *</label>
                    <select name="status" class="form-select" required>
                        <option value="attivo" <?= ($member['status'] ?? $_POST['status'] ?? 'attivo') === 'attivo' ? 'selected' : '' ?>>Attivo</option>
                        <option value="sospeso" <?= ($member['status'] ?? $_POST['status'] ?? '') === 'sospeso' ? 'selected' : '' ?>>Sospeso</option>
                        <option value="cessato" <?= ($member['status'] ?? $_POST['status'] ?? '') === 'cessato' ? 'selected' : '' ?>>Cessato</option>
                    </select>
                </div>
                <div class="col-md-12">
                    <label class="form-label">Note</label>
                    <textarea name="notes" class="form-control" rows="3"><?= h($member['notes'] ?? $_POST['notes'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
    </div>
    
    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-save me-1"></i>Salva
        </button>
        <a href="members.php" class="btn btn-secondary">
            <i class="bi bi-x-circle me-1"></i>Annulla
        </a>
    </div>
</form>

<?php include __DIR__ . '/inc/footer.php'; ?>
