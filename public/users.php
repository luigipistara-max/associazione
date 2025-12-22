<?php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/functions.php';

requireAdmin();

$pageTitle = 'Gestione Utenti';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create' || $action === 'update') {
        $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : null;
        $username = trim($_POST['username'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? 'operatore';
        $password = $_POST['password'] ?? '';
        
        $errors = [];
        
        if (empty($username)) $errors[] = "Username obbligatorio";
        if (empty($fullName)) $errors[] = "Nome completo obbligatorio";
        if (empty($email)) $errors[] = "Email obbligatoria";
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Email non valida";
        
        if ($action === 'create' && strlen($password) < 8) {
            $errors[] = "Password deve contenere almeno 8 caratteri";
        }
        if ($action === 'update' && $password && strlen($password) < 8) {
            $errors[] = "Password deve contenere almeno 8 caratteri";
        }
        
        // Check unique username
        if ($action === 'create') {
            $stmt = $pdo->prepare("SELECT id FROM " . table('users') . " WHERE username = ?");
            $stmt->execute([$username]);
        } else {
            $stmt = $pdo->prepare("SELECT id FROM " . table('users') . " WHERE username = ? AND id != ?");
            $stmt->execute([$username, $userId]);
        }
        if ($stmt->fetch()) {
            $errors[] = "Username già esistente";
        }
        
        if (empty($errors)) {
            try {
                if ($action === 'create') {
                    $passwordHash = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("INSERT INTO " . table('users') . " (username, password, full_name, email, role) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$username, $passwordHash, $fullName, $email, $role]);
                    setFlash('success', 'Utente creato con successo');
                } else {
                    if ($password) {
                        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
                        $stmt = $pdo->prepare("UPDATE " . table('users') . " SET username = ?, password = ?, full_name = ?, email = ?, role = ? WHERE id = ?");
                        $stmt->execute([$username, $passwordHash, $fullName, $email, $role, $userId]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE " . table('users') . " SET username = ?, full_name = ?, email = ?, role = ? WHERE id = ?");
                        $stmt->execute([$username, $fullName, $email, $role, $userId]);
                    }
                    setFlash('success', 'Utente aggiornato con successo');
                }
                redirect('users.php');
            } catch (PDOException $e) {
                setFlash('error', 'Errore database: ' . $e->getMessage());
            }
        } else {
            setFlash('error', implode(', ', $errors));
        }
    } elseif ($action === 'delete') {
        $userId = (int)$_POST['user_id'];
        
        // Prevent deleting yourself
        if ($userId === getCurrentUserId()) {
            setFlash('error', 'Non puoi eliminare il tuo account');
        } else {
            try {
                $stmt = $pdo->prepare("DELETE FROM " . table('users') . " WHERE id = ?");
                $stmt->execute([$userId]);
                setFlash('success', 'Utente eliminato con successo');
            } catch (PDOException $e) {
                setFlash('error', 'Errore durante l\'eliminazione: ' . $e->getMessage());
            }
        }
        redirect('users.php');
    }
}

// Load users
try {
    $stmt = $pdo->query("SELECT * FROM " . table('users') . " ORDER BY username");
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Errore database: " . htmlspecialchars($e->getMessage()));
}

include __DIR__ . '/inc/header.php';
?>

<?php displayFlash(); ?>

<div class="row mb-3">
    <div class="col-md-6">
        <h2><i class="bi bi-person-badge me-2"></i>Gestione Utenti</h2>
    </div>
    <div class="col-md-6 text-end">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal" onclick="resetUserForm()">
            <i class="bi bi-plus-circle me-1"></i>Nuovo Utente
        </button>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Nome Completo</th>
                        <th>Email</th>
                        <th>Ruolo</th>
                        <th>Creato il</th>
                        <th class="text-end">Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= h($user['username']) ?></td>
                        <td><?= h($user['full_name']) ?></td>
                        <td><?= h($user['email']) ?></td>
                        <td>
                            <span class="badge bg-<?= $user['role'] === 'admin' ? 'danger' : 'primary' ?>">
                                <?= h($user['role']) ?>
                            </span>
                        </td>
                        <td><?= formatDate($user['created_at'], 'd/m/Y H:i') ?></td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-primary" onclick="editUser(<?= htmlspecialchars(json_encode($user)) ?>)">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <?php if ($user['id'] !== getCurrentUserId()): ?>
                            <button type="button" class="btn btn-sm btn-danger" onclick="confirmDelete(<?= $user['id'] ?>, '<?= h($user['username']) ?>')">
                                <i class="bi bi-trash"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- User Modal -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="user_id" id="userId">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Nuovo Utente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Username *</label>
                        <input type="text" name="username" id="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nome Completo *</label>
                        <input type="text" name="full_name" id="fullName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" id="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ruolo *</label>
                        <select name="role" id="role" class="form-select" required>
                            <option value="operatore">Operatore</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password <span id="passwordHint">(min 8 caratteri)</span></label>
                        <input type="password" name="password" id="password" class="form-control" minlength="8">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="submit" class="btn btn-primary">Salva</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="user_id" id="deleteUserId">
                
                <div class="modal-header">
                    <h5 class="modal-title">Conferma Eliminazione</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    Sei sicuro di voler eliminare l'utente <strong id="deleteUsername"></strong>?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="submit" class="btn btn-danger">Elimina</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetUserForm() {
    document.getElementById('formAction').value = 'create';
    document.getElementById('modalTitle').textContent = 'Nuovo Utente';
    document.getElementById('userId').value = '';
    document.getElementById('username').value = '';
    document.getElementById('fullName').value = '';
    document.getElementById('email').value = '';
    document.getElementById('role').value = 'operatore';
    document.getElementById('password').value = '';
    document.getElementById('password').required = true;
    document.getElementById('passwordHint').textContent = '(min 8 caratteri) *';
}

function editUser(user) {
    document.getElementById('formAction').value = 'update';
    document.getElementById('modalTitle').textContent = 'Modifica Utente';
    document.getElementById('userId').value = user.id;
    document.getElementById('username').value = user.username;
    document.getElementById('fullName').value = user.full_name;
    document.getElementById('email').value = user.email;
    document.getElementById('role').value = user.role;
    document.getElementById('password').value = '';
    document.getElementById('password').required = false;
    document.getElementById('passwordHint').textContent = '(lascia vuoto per non modificare)';
    
    const modal = new bootstrap.Modal(document.getElementById('userModal'));
    modal.show();
}

function confirmDelete(id, username) {
    document.getElementById('deleteUserId').value = id;
    document.getElementById('deleteUsername').textContent = username;
    
    const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    modal.show();
}
</script>

<?php include __DIR__ . '/inc/footer.php'; ?>
