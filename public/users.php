<?php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/db.php';

requireAdmin();

$config = require __DIR__ . '/../src/config.php';
$basePath = $config['app']['base_path'];
$pageTitle = 'Gestione Utenti';

$errors = [];

// Handle delete
if (isset($_GET['delete']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_GET['delete'];
    $token = $_POST['csrf_token'] ?? '';
    
    if (verifyCsrfToken($token)) {
        // Prevent deleting own account
        if ($id != $_SESSION['user_id']) {
            $stmt = $pdo->prepare("DELETE FROM " . table('users') . " WHERE id = ?");
            $stmt->execute([$id]);
            setFlashMessage('Utente eliminato con successo');
        } else {
            setFlashMessage('Non puoi eliminare il tuo account', 'danger');
        }
        redirect($basePath . 'users.php');
    }
}

// Handle add/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $token = $_POST['csrf_token'] ?? '';
    
    if (!verifyCsrfToken($token)) {
        $errors[] = 'Token di sicurezza non valido';
    } else {
        $username = trim($_POST['username'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'operatore';
        $userId = $_POST['user_id'] ?? null;
        
        if (empty($username)) {
            $errors[] = 'Username obbligatorio';
        }
        
        if (empty($fullName)) {
            $errors[] = 'Nome completo obbligatorio';
        }
        
        if (empty($email)) {
            $errors[] = 'Email obbligatoria';
        }
        
        if ($userId) {
            // Update
            if (!empty($password)) {
                if (strlen($password) < 8) {
                    $errors[] = 'La password deve essere di almeno 8 caratteri';
                } else {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE " . table('users') . " SET username = ?, password = ?, full_name = ?, email = ?, role = ? WHERE id = ?");
                    $stmt->execute([$username, $hashedPassword, $fullName, $email, $role, $userId]);
                }
            } else {
                $stmt = $pdo->prepare("UPDATE " . table('users') . " SET username = ?, full_name = ?, email = ?, role = ? WHERE id = ?");
                $stmt->execute([$username, $fullName, $email, $role, $userId]);
            }
            if (empty($errors)) {
                setFlashMessage('Utente aggiornato con successo');
                redirect($basePath . 'users.php');
            }
        } else {
            // Create
            if (empty($password)) {
                $errors[] = 'Password obbligatoria per nuovo utente';
            } elseif (strlen($password) < 8) {
                $errors[] = 'La password deve essere di almeno 8 caratteri';
            }
            
            if (empty($errors)) {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO " . table('users') . " (username, password, full_name, email, role) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$username, $hashedPassword, $fullName, $email, $role]);
                setFlashMessage('Utente creato con successo');
                redirect($basePath . 'users.php');
            }
        }
    }
}

// Get all users
$stmt = $pdo->query("SELECT * FROM " . table('users') . " ORDER BY username");
$users = $stmt->fetchAll();

include __DIR__ . '/inc/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="bi bi-person-gear"></i> Gestione Utenti</h2>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal" onclick="resetUserForm()">
        <i class="bi bi-plus"></i> Nuovo Utente
    </button>
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

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
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
                        <td>
                            <strong><?php echo h($user['username']); ?></strong>
                            <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                <span class="badge bg-info">Tu</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo h($user['full_name'] ?? '-'); ?></td>
                        <td><?php echo h($user['email'] ?? '-'); ?></td>
                        <td>
                            <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'danger' : 'primary'; ?>">
                                <?php echo h(ucfirst($user['role'])); ?>
                            </span>
                        </td>
                        <td><?php echo formatDate($user['created_at']); ?></td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                    onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                    onclick="confirmDelete(<?php echo $user['id']; ?>, '<?php echo e($user['username']); ?>')">
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
                <div class="modal-header">
                    <h5 class="modal-title" id="userModalTitle">Nuovo Utente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo e(generateCsrfToken()); ?>">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="user_id" id="userId">
                    
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" id="username" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Nome Completo</label>
                        <input type="text" name="full_name" id="full_name" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" id="email" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" id="password" class="form-control" minlength="8">
                        <small class="text-muted" id="passwordHelp">Minimo 8 caratteri. Lascia vuoto per non modificare.</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Ruolo</label>
                        <select name="role" id="role" class="form-select" required>
                            <option value="operatore">Operatore</option>
                            <option value="admin">Amministratore</option>
                        </select>
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
            <div class="modal-header">
                <h5 class="modal-title">Conferma Eliminazione</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Sei sicuro di voler eliminare l'utente <strong id="deleteUserName"></strong>?
            </div>
            <div class="modal-footer">
                <form method="POST" id="deleteForm">
                    <input type="hidden" name="csrf_token" value="<?php echo e(generateCsrfToken()); ?>">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="submit" class="btn btn-danger">Elimina</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function resetUserForm() {
    document.getElementById('userModalTitle').textContent = 'Nuovo Utente';
    document.getElementById('userId').value = '';
    document.getElementById('username').value = '';
    document.getElementById('full_name').value = '';
    document.getElementById('email').value = '';
    document.getElementById('password').value = '';
    document.getElementById('password').required = true;
    document.getElementById('passwordHelp').textContent = 'Minimo 8 caratteri';
    document.getElementById('role').value = 'operatore';
}

function editUser(user) {
    document.getElementById('userModalTitle').textContent = 'Modifica Utente';
    document.getElementById('userId').value = user.id;
    document.getElementById('username').value = user.username;
    document.getElementById('full_name').value = user.full_name || '';
    document.getElementById('email').value = user.email || '';
    document.getElementById('password').value = '';
    document.getElementById('password').required = false;
    document.getElementById('passwordHelp').textContent = 'Minimo 8 caratteri. Lascia vuoto per non modificare.';
    document.getElementById('role').value = user.role;
    new bootstrap.Modal(document.getElementById('userModal')).show();
}

function confirmDelete(id, username) {
    document.getElementById('deleteUserName').textContent = username;
    document.getElementById('deleteForm').action = '<?php echo $basePath; ?>users.php?delete=' + id;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>

<?php include __DIR__ . '/inc/footer.php'; ?>
