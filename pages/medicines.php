<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Handle Form Submissions (Add/Edit/Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        // CSRF Verification
        if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            die("CSRF Token Validation Failed.");
        }

        try {
            if ($_POST['action'] === 'add' || $_POST['action'] === 'edit') {
                $name = cleanInput($_POST['name']);
                $category_id = $_POST['category_id'];
                $batch_number = cleanInput($_POST['batch_number']);
                $expiry_date = $_POST['expiry_date'];
                $quantity = (int)$_POST['quantity'];
                $purchase_price = (float)$_POST['purchase_price'];
                $selling_price = (float)$_POST['selling_price'];
                $low_stock_threshold = (int)$_POST['low_stock_threshold'];

                if ($_POST['action'] === 'add') {
                    $stmt = $pdo->prepare("INSERT INTO medicines (name, category_id, batch_number, expiry_date, quantity, purchase_price, selling_price, low_stock_threshold) 
                                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $category_id, $batch_number, $expiry_date, $quantity, $purchase_price, $selling_price, $low_stock_threshold]);
                    setFlash("Medicine added successfully.");
                } else {
                    $id = $_POST['id'];
                    $stmt = $pdo->prepare("UPDATE medicines SET name=?, category_id=?, batch_number=?, expiry_date=?, quantity=?, purchase_price=?, selling_price=?, low_stock_threshold=? WHERE id=?");
                    $stmt->execute([$name, $category_id, $batch_number, $expiry_date, $quantity, $purchase_price, $selling_price, $low_stock_threshold, $id]);
                    setFlash("Medicine updated successfully.");
                }
            } elseif ($_POST['action'] === 'delete') {
                $id = $_POST['id'];
                $stmt = $pdo->prepare("DELETE FROM medicines WHERE id = ?");
                $stmt->execute([$id]);
                setFlash("Medicine deleted successfully.", "danger");
            }
        } catch (Exception $e) {
            setFlash("Error: " . $e->getMessage(), "danger");
        }
        header("Location: medicines.php");
        exit();
    }
}

// Initialize Filter Variables
$search = $_GET['search'] ?? '';
$categoryFilter = $_GET['category'] ?? '';
$stockStatus = $_GET['stock_status'] ?? '';
$expiryStatus = $_GET['expiry_status'] ?? '';

// Build Query
$query = "SELECT m.*, c.name as category_name 
          FROM medicines m 
          LEFT JOIN categories c ON m.category_id = c.id 
          WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND m.name LIKE ?";
    $params[] = "%$search%";
}

if (!empty($categoryFilter)) {
    $query .= " AND m.category_id = ?";
    $params[] = $categoryFilter;
}

if ($stockStatus === 'low') {
    $query .= " AND m.quantity > 0 AND m.quantity <= m.low_stock_threshold";
} elseif ($stockStatus === 'out') {
    $query .= " AND m.quantity <= 0";
} elseif ($stockStatus === 'in') {
    $query .= " AND m.quantity > m.low_stock_threshold";
}

if ($expiryStatus === 'soon') {
    $query .= " AND m.expiry_date >= CURDATE() AND m.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
} elseif ($expiryStatus === 'expired') {
    $query .= " AND m.expiry_date < CURDATE()";
}

$query .= " ORDER BY m.name ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$medicines = $stmt->fetchAll();
$totalResults = count($medicines);

// FEATURE 1: Export to CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = "medicines_export_" . date('Y-m-d') . ".csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Medicine Name', 'Category', 'Batch No', 'Expiry Date', 'Quantity', 'Purchase Price', 'Selling Price', 'Status']);
    
    foreach ($medicines as $med) {
        $status = $med['quantity'] <= 0 ? 'Out of Stock' : ($med['quantity'] <= $med['low_stock_threshold'] ? 'Low Stock' : 'In Stock');
        fputcsv($output, [
            $med['name'],
            $med['category_name'] ?: 'Uncategorized',
            $med['batch_number'],
            $med['expiry_date'],
            $med['quantity'],
            $med['purchase_price'],
            $med['selling_price'],
            $status
        ]);
    }
    fclose($output);
    exit();
}

// Fetch Categories for Dropdown
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

$pageTitle = 'Medicines Inventory';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">Medicines Inventory</h1>
    <div>
        <a href="medicines.php?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn btn-success me-2">
            <i class="fas fa-file-csv me-2"></i> Export CSV
        </a>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#medicineModal">
            <i class="fas fa-plus me-2"></i> Add New Medicine
        </button>
    </div>
</div>

<!-- Filters Bar -->
<div class="card shadow mb-4 border-0 text-dark">
    <div class="card-body bg-light rounded">
        <form method="GET" action="medicines.php" id="filterForm">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Search Product</label>
                    <input type="text" name="search" class="form-control" placeholder="Medicine name..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Category</label>
                    <select name="category" class="form-select">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $categoryFilter == $cat['id'] ? 'selected' : '' ?>><?= $cat['name'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Stock Status</label>
                    <select name="stock_status" class="form-select">
                        <option value="">All Status</option>
                        <option value="in" <?= $stockStatus == 'in' ? 'selected' : '' ?>>In Stock</option>
                        <option value="low" <?= $stockStatus == 'low' ? 'selected' : '' ?>>Low Stock</option>
                        <option value="out" <?= $stockStatus == 'out' ? 'selected' : '' ?>>Out of Stock</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Expiry</label>
                    <select name="expiry_status" class="form-select">
                        <option value="">All Expiry</option>
                        <option value="soon" <?= $expiryStatus == 'soon' ? 'selected' : '' ?>>Expiring Soon</option>
                        <option value="expired" <?= $expiryStatus == 'expired' ? 'selected' : '' ?>>Expired</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100 me-2"><i class="fas fa-search me-1"></i> Search</button>
                    <a href="medicines.php" class="btn btn-outline-secondary w-100"><i class="fas fa-undo me-1"></i> Reset</a>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="mb-3 text-muted small">
    <i class="fas fa-list me-1"></i> Showing <strong><?= $totalResults ?></strong> medicines found.
</div>

<div class="card shadow mb-4">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="medicineTable" width="100%" cellspacing="0">
                <thead class="table-light">
                    <tr>
                        <th>Medicine Info</th>
                        <th>Category</th>
                        <th>Batch</th>
                        <th>Expiry</th>
                        <th>Qty</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($medicines as $med): 
                        $status = getStockStatus($med['quantity'], $med['low_stock_threshold']);
                        $expiryStatusLabel = isExpired($med['expiry_date']);
                        $isLow = $med['quantity'] <= $med['low_stock_threshold'];
                        $isExpired = strtotime($med['expiry_date']) < time();
                    ?>
                        <tr class="<?= $isExpired ? 'table-danger' : ($isLow ? 'table-warning' : '') ?>">
                            <td>
                                <strong><?= $med['name'] ?></strong>
                                <div class="small text-muted"><?= formatCurrency($med['selling_price']) ?></div>
                            </td>
                            <td><?= $med['category_name'] ?: 'Uncategorized' ?></td>
                            <td><?= $med['batch_number'] ?></td>
                            <td>
                                <?= formatDate($med['expiry_date']) ?>
                                <div class="mt-1"><?= $expiryStatusLabel ?></div>
                            </td>
                            <td><strong><?= $med['quantity'] ?></strong></td>
                            <td><?= $status ?></td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-primary edit-btn" 
                                            data-id="<?= $med['id'] ?>"
                                            data-name="<?= htmlspecialchars($med['name']) ?>"
                                            data-category="<?= $med['category_id'] ?>"
                                            data-batch="<?= $med['batch_number'] ?>"
                                            data-expiry="<?= $med['expiry_date'] ?>"
                                            data-qty="<?= $med['quantity'] ?>"
                                            data-pprice="<?= $med['purchase_price'] ?>"
                                            data-sprice="<?= $med['selling_price'] ?>"
                                            data-threshold="<?= $med['low_stock_threshold'] ?>"
                                            data-bs-toggle="modal" data-bs-target="#medicineModal">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-outline-danger delete-btn" data-id="<?= $med['id'] ?>" data-name="<?= htmlspecialchars($med['name']) ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit Medicine Modal -->
<div class="modal fade" id="medicineModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="medicines.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Add New Medicine</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="medicineId">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Medicine Name</label>
                            <input type="text" name="name" id="medName" class="form-control" list="existingMedicines" required>
                            <datalist id="existingMedicines">
                                <?php foreach ($medicines as $m): ?>
                                    <option value="<?= htmlspecialchars($m['name']) ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Category</label>
                            <select name="category_id" id="medCategory" class="form-select" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= $cat['name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Batch Number</label>
                            <input type="text" name="batch_number" id="medBatch" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Expiry Date</label>
                            <input type="date" name="expiry_date" id="medExpiry" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Quantity</label>
                            <input type="number" name="quantity" id="medQty" class="form-control" min="0" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Purchase Price</label>
                            <input type="number" step="0.01" name="purchase_price" id="medPPrice" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Selling Price</label>
                            <input type="number" step="0.01" name="selling_price" id="medSPrice" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Low Stock Threshold</label>
                            <input type="number" name="low_stock_threshold" id="medThreshold" class="form-control" value="10" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Medicine</button>
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
        $('#modalTitle').text('Edit Medicine');
        $('#formAction').val('edit');
        $('#medicineId').val($(this).data('id'));
        $('#medName').val($(this).data('name'));
        $('#medCategory').val($(this).data('category'));
        $('#medBatch').val($(this).data('batch'));
        $('#medExpiry').val($(this).data('expiry'));
        $('#medQty').val($(this).data('qty'));
        $('#medPPrice').val($(this).data('pprice'));
        $('#medSPrice').val($(this).data('sprice'));
        $('#medThreshold').val($(this).data('threshold'));
    });

    $('#medicineModal').on('hidden.bs.modal', function () {
        $('#modalTitle').text('Add New Medicine');
        $('#formAction').val('add');
        $('#medicineId').val('');
        $('#medicineModal form')[0].reset();
    });

    $('.delete-btn').on('click', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');
        if (confirm('Are you sure you want to delete ' + name + '?')) {
            $('#deleteId').val(id);
            $('#deleteForm').submit();
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
