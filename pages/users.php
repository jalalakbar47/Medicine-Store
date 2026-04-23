<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin(); // User management is for Admins only
require_once __DIR__ . '/../includes/functions.php';

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        // CSRF Verification
        if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            die("CSRF Token Validation Failed.");
        }

        try {
            if ($_POST['action'] === 'add' || $_POST['action'] === 'edit') {
                $username = cleanInput($_POST['username']);
                $full_name = cleanInput($_POST['full_name']);
                $role = $_POST['role'];
                $password = $_POST['password'];

                if ($_POST['action'] === 'add') {
                    if (empty($password)) throw new Exception("Password is required for new users.");
                    
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$username, $hashed_password, $full_name, $role]);
                    setFlash("User '{$username}' created successfully.");
                } else {
                    $id = $_POST['id'];
                    // If password is provided, update it too
                    if (!empty($password)) {
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE users SET username=?, full_name=?, role=?, password=? WHERE id=?");
                        $stmt->execute([$username, $full_name, $role, $hashed_password, $id]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE users SET username=?, full_name=?, role=? WHERE id=?");
                        $stmt->execute([$username, $full_name, $role, $id]);
                    }
                    setFlash("User updated successfully.");
                }
            } elseif ($_POST['action'] === 'delete') {
                $id = $_POST['id'];
                // Prevent deleting self
                if ($id == $_SESSION['user_id']) {
                    throw new Exception("You cannot delete your own account.");
                }
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$id]);
                setFlash("User deleted successfully.", "danger");
            }
        } catch (Exception $e) {
            setFlash($e->getMessage(), "danger");
        }
        header("Location: users.php");
        exit();
    }
}

// Fetch Users
$users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();

$pageTitle = 'User Management';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800">User Management</h1>
        <p class="text-muted small">Manage staff accounts and system permissions.</p>
    </div>
    <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#userModal">
        <i class="fas fa-user-plus me-2"></i> Add New User
    </button>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light text-uppercase small fw-bold">
                    <tr>
                        <th class="ps-4">Full Name</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Created At</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center">
                                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($user['full_name']) ?>&background=random" class="rounded-circle me-3" width="35">
                                    <div>
                                        <div class="fw-bold text-dark"><?= $user['full_name'] ?></div>
                                        <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                            <span class="badge bg-success-subtle text-success x-small">You</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td><?= $user['username'] ?></td>
                            <td>
                                <span class="badge <?= $user['role'] === 'admin' ? 'bg-primary' : 'bg-info' ?> rounded-pill">
                                    <?= ucfirst($user['role']) ?>
                                </span>
                            </td>
                            <td class="text-muted small"><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                            <td class="text-end pe-4">
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-secondary edit-btn" 
                                            data-id="<?= $user['id'] ?>"
                                            data-username="<?= htmlspecialchars($user['username']) ?>"
                                            data-name="<?= htmlspecialchars($user['full_name']) ?>"
                                            data-role="<?= $user['role'] ?>"
                                            data-bs-toggle="modal" data-bs-target="#userModal">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <button class="btn btn-outline-danger delete-btn" data-id="<?= $user['id'] ?>" data-name="<?= htmlspecialchars($user['username']) ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit User Modal -->
<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <form action="users.php" method="POST">
                <?= csrfField() ?>
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="modalTitle">Add New User</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="userId">
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Full Name</label>
                        <input type="text" name="full_name" id="fullName" class="form-control" placeholder="John Doe" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Username</label>
                        <input type="text" name="username" id="userName" class="form-control" placeholder="johndoe123" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Role</label>
                        <select name="role" id="userRole" class="form-select" required>
                            <option value="staff">Staff</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Password</label>
                        <input type="password" name="password" id="userPassword" class="form-control" placeholder="Enter password...">
                        <small class="text-muted" id="passwordHint">Leave blank to keep current password when editing.</small>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">Save User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Form (Hidden) -->
<form id="deleteForm" method="POST" style="display:none;">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteId">
</form>

<script>
$(document).ready(function() {
    $('.edit-btn').on('click', function() {
        $('#modalTitle').text('Edit User Settings');
        $('#formAction').val('edit');
        $('#userId').val($(this).data('id'));
        $('#fullName').val($(this).data('name'));
        $('#userName').val($(this).data('username'));
        $('#userRole').val($(this).data('role'));
        $('#userPassword').attr('required', false);
        $('#passwordHint').show();
    });

    $('#userModal').on('hidden.bs.modal', function () {
        $('#modalTitle').text('Add New User');
        $('#formAction').val('add');
        $('#userId').val('');
        $('#userModal form')[0].reset();
        $('#userPassword').attr('required', true);
        $('#passwordHint').hide();
    });

    $('.delete-btn').on('click', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');
        if (confirm('Are you sure you want to delete user "' + name + '"? This action cannot be undone.')) {
            $('#deleteId').val(id);
            $('#deleteForm').submit();
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
