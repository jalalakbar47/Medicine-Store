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
                $contact = cleanInput($_POST['contact']);
                $email = cleanInput($_POST['email']);
                $address = cleanInput($_POST['address']);

                if ($_POST['action'] === 'add') {
                    $stmt = $pdo->prepare("INSERT INTO suppliers (name, contact, email, address) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$name, $contact, $email, $address]);
                    setFlash("Supplier added successfully.");
                } else {
                    $id = $_POST['id'];
                    $stmt = $pdo->prepare("UPDATE suppliers SET name=?, contact=?, email=?, address=? WHERE id=?");
                    $stmt->execute([$name, $contact, $email, $address, $id]);
                    setFlash("Supplier details updated.");
                }
            } elseif ($_POST['action'] === 'delete') {
                $id = $_POST['id'];
                // Check if purchases exist for this supplier
                $check = $pdo->prepare("SELECT COUNT(*) FROM purchases WHERE supplier_id = ?");
                $check->execute([$id]);
                if ($check->fetchColumn() > 0) {
                    setFlash("Cannot delete supplier. Purchase records exist.", "danger");
                } else {
                    $stmt = $pdo->prepare("DELETE FROM suppliers WHERE id = ?");
                    $stmt->execute([$id]);
                    setFlash("Supplier deleted successfully.", "danger");
                }
            }
        } catch (Exception $e) {
            setFlash("Error: " . $e->getMessage(), "danger");
        }
        header("Location: suppliers.php");
        exit();
    }
}

// Initialize Filter Variables
$search = $_GET['search'] ?? '';

// Build Query
$query = "SELECT * FROM suppliers WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (name LIKE ? OR contact LIKE ? OR email LIKE ? OR address LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY name ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$suppliers = $stmt->fetchAll();
$totalResults = count($suppliers);

$pageTitle = 'Suppliers Management';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">Suppliers Management</h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#supplierModal">
        <i class="fas fa-plus me-2"></i> Add New Supplier
    </button>
</div>

<!-- Filters Bar -->
<div class="card shadow mb-4">
    <div class="card-body bg-light rounded">
        <form method="GET" action="suppliers.php" class="row g-3">
            <div class="col-md-9">
                <label class="form-label small fw-bold">Search Supplier</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Search by name, contact, email or address..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100 me-2"><i class="fas fa-search me-1"></i> Search</button>
                <a href="suppliers.php" class="btn btn-outline-secondary w-100"><i class="fas fa-undo me-1"></i> Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="mb-3 text-muted small">
    Showing <strong><?= $totalResults ?></strong> suppliers found.
</div>

<div class="card shadow mb-4">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                <thead class="table-light">
                    <tr>
                        <th>Supplier Name</th>
                        <th>Contact / Phone</th>
                        <th>Email</th>
                        <th>Address</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($suppliers)): ?>
                        <tr><td colspan="5" class="text-center py-4">No suppliers matching your search criteria.</td></tr>
                    <?php else: ?>
                        <?php foreach ($suppliers as $s): ?>
                            <tr>
                                <td><strong><?= $s['name'] ?></strong></td>
                                <td><?= $s['contact'] ?></td>
                                <td><?= $s['email'] ?: '<span class="text-muted">N/A</span>' ?></td>
                                <td><?= $s['address'] ?: '<span class="text-muted">N/A</span>' ?></td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary edit-btn" 
                                                data-id="<?= $s['id'] ?>"
                                                data-name="<?= htmlspecialchars($s['name']) ?>"
                                                data-contact="<?= htmlspecialchars($s['contact']) ?>"
                                                data-email="<?= htmlspecialchars($s['email']) ?>"
                                                data-address="<?= htmlspecialchars($s['address']) ?>"
                                                data-bs-toggle="modal" data-bs-target="#supplierModal">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-outline-danger delete-btn" data-id="<?= $s['id'] ?>" data-name="<?= htmlspecialchars($s['name']) ?>">
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

<!-- Add/Edit Supplier Modal -->
<div class="modal fade" id="supplierModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="suppliers.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Add New Supplier</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="supplierId">
                    <div class="mb-3">
                        <label class="form-label">Supplier Name</label>
                        <input type="text" name="name" id="suppName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone / Contact</label>
                        <input type="text" name="contact" id="suppContact" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" id="suppEmail" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea name="address" id="suppAddress" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Supplier</button>
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
        $('#modalTitle').text('Edit Supplier Details');
        $('#formAction').val('edit');
        $('#supplierId').val($(this).data('id'));
        $('#suppName').val($(this).data('name'));
        $('#suppContact').val($(this).data('contact'));
        $('#suppEmail').val($(this).data('email'));
        $('#suppAddress').val($(this).data('address'));
    });

    $('#supplierModal').on('hidden.bs.modal', function () {
        $('#modalTitle').text('Add New Supplier');
        $('#formAction').val('add');
        $('#supplierId').val('');
        $('#supplierModal form')[0].reset();
    });

    $('.delete-btn').on('click', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');
        if (confirm('Are you sure you want to delete supplier "' + name + '"?')) {
            $('#deleteId').val(id);
            $('#deleteForm').submit();
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
