<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            if ($_POST['action'] === 'add' || $_POST['action'] === 'edit') {
                $name = cleanInput($_POST['name']);
                $description = cleanInput($_POST['description']);

                if ($_POST['action'] === 'add') {
                    $stmt = $pdo->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
                    $stmt->execute([$name, $description]);
                    setFlash("Category added successfully.");
                } else {
                    $id = $_POST['id'];
                    $stmt = $pdo->prepare("UPDATE categories SET name=?, description=? WHERE id=?");
                    $stmt->execute([$name, $description, $id]);
                    setFlash("Category updated successfully.");
                }
            } elseif ($_POST['action'] === 'delete') {
                $id = $_POST['id'];
                // Check if medicines exist in this category
                $check = $pdo->prepare("SELECT COUNT(*) FROM medicines WHERE category_id = ?");
                $check->execute([$id]);
                if ($check->fetchColumn() > 0) {
                    setFlash("Cannot delete category. Medicines are still assigned to it.", "danger");
                } else {
                    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
                    $stmt->execute([$id]);
                    setFlash("Category deleted successfully.", "danger");
                }
            }
        } catch (Exception $e) {
            setFlash("Error: " . $e->getMessage(), "danger");
        }
        header("Location: categories.php");
        exit();
    }
}

$pageTitle = 'Medicine Categories';
require_once __DIR__ . '/../includes/header.php';

// Fetch Categories with Medicine Counts
$categories = $pdo->query("SELECT c.*, COUNT(m.id) as med_count 
                          FROM categories c 
                          LEFT JOIN medicines m ON c.id = m.category_id 
                          GROUP BY c.id 
                          ORDER BY c.name ASC")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">Medicine Categories</h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#categoryModal">
        <i class="fas fa-plus me-2"></i> Add New Category
    </button>
</div>

<div class="row">
    <div class="col-lg-12">
        <div class="card shadow mb-4">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                        <thead class="table-light">
                            <tr>
                                <th>Category Name</th>
                                <th>Description</th>
                                <th>Medicines Count</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($categories)): ?>
                                <tr><td colspan="4" class="text-center">No categories found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($categories as $cat): ?>
                                    <tr>
                                        <td><strong><?= $cat['name'] ?></strong></td>
                                        <td><?= $cat['description'] ?: '<span class="text-muted">No description</span>' ?></td>
                                        <td><span class="badge bg-info text-dark"><?= $cat['med_count'] ?> Products</span></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary edit-btn" 
                                                        data-id="<?= $cat['id'] ?>"
                                                        data-name="<?= htmlspecialchars($cat['name']) ?>"
                                                        data-desc="<?= htmlspecialchars($cat['description']) ?>"
                                                        data-bs-toggle="modal" data-bs-target="#categoryModal">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-outline-danger delete-btn" data-id="<?= $cat['id'] ?>" data-name="<?= htmlspecialchars($cat['name']) ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Category Modal -->
<div class="modal fade" id="categoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="categories.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Add New Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="categoryId">
                    <div class="mb-3">
                        <label class="form-label">Category Name</label>
                        <input type="text" name="name" id="catName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="catDesc" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Form (Hidden) -->
<form id="deleteForm" method="POST" style="display:none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteId">
</form>

<script>
$(document).ready(function() {
    $('.edit-btn').on('click', function() {
        $('#modalTitle').text('Edit Category');
        $('#formAction').val('edit');
        $('#categoryId').val($(this).data('id'));
        $('#catName').val($(this).data('name'));
        $('#catDesc').val($(this).data('desc'));
    });

    $('#categoryModal').on('hidden.bs.modal', function () {
        $('#modalTitle').text('Add New Category');
        $('#formAction').val('add');
        $('#categoryId').val('');
        $('#categoryModal form')[0].reset();
    });

    $('.delete-btn').on('click', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');
        if (confirm('Are you sure you want to delete category "' + name + '"?')) {
            $('#deleteId').val(id);
            $('#deleteForm').submit();
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
