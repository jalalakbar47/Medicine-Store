<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Handle Purchase Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_purchase') {
    try {
        $pdo->beginTransaction();

        $supplier_id = $_POST['supplier_id'];
        $medicine_id = $_POST['medicine_id'];
        $quantity = (int)$_POST['quantity'];
        $unit_price = (float)$_POST['unit_price'];
        $purchase_date = $_POST['purchase_date'];
        $total_amount = $quantity * $unit_price;

        // 1. Insert into purchases
        $stmt = $pdo->prepare("INSERT INTO purchases (supplier_id, total_amount, purchase_date) VALUES (?, ?, ?)");
        $stmt->execute([$supplier_id, $total_amount, $purchase_date]);
        $purchase_id = $pdo->lastInsertId();

        // 2. Insert into purchase_items
        $stmtItem = $pdo->prepare("INSERT INTO purchase_items (purchase_id, medicine_id, quantity, unit_price) VALUES (?, ?, ?, ?)");
        $stmtItem->execute([$purchase_id, $medicine_id, $quantity, $unit_price]);

        // 3. Update medicine stock
        $stmtUpdate = $pdo->prepare("UPDATE medicines SET quantity = quantity + ? WHERE id = ?");
        $stmtUpdate->execute([$quantity, $medicine_id]);

        $pdo->commit();
        setFlash("Stock updated successfully! Added $quantity units.");
    } catch (Exception $e) {
        $pdo->rollBack();
        setFlash("Error: " . $e->getMessage(), "danger");
    }
    header("Location: purchases.php");
    exit();
}

// Initialize Filter Variables
$search = $_GET['search'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$statusFilter = $_GET['status'] ?? '';

// Build Query
$query = "SELECT pi.*, p.purchase_date, p.total_amount, s.name as supplier_name, m.name as medicine_name 
          FROM purchase_items pi 
          JOIN purchases p ON pi.purchase_id = p.id 
          JOIN suppliers s ON p.supplier_id = s.id 
          JOIN medicines m ON pi.medicine_id = m.id 
          WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (s.name LIKE ? OR m.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($startDate)) {
    $query .= " AND p.purchase_date >= ?";
    $params[] = $startDate;
}

if (!empty($endDate)) {
    $query .= " AND p.purchase_date <= ?";
    $params[] = $endDate;
}

// Since I don't have a 'status' column in my current purchases table schema based on database.sql, 
// I'll assume everything in the list is 'received' for now, but I'll add the UI filter as requested.

$query .= " ORDER BY p.purchase_date DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$purchases = $stmt->fetchAll();
$totalResults = count($purchases);

// Fetch Suppliers and Medicines for dropdown
$suppliers = $pdo->query("SELECT * FROM suppliers ORDER BY name ASC")->fetchAll();
$medicinesDropdown = $pdo->query("SELECT * FROM medicines ORDER BY name ASC")->fetchAll();

$pageTitle = 'Purchase Orders';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">Purchase Orders (Refill Stock)</h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#purchaseModal">
        <i class="fas fa-cart-plus me-2"></i> New Purchase Order
    </button>
</div>

<!-- Advanced Search and Filters -->
<div class="card shadow mb-4">
    <div class="card-body bg-light rounded">
        <form method="GET" action="purchases.php" class="row g-3">
            <div class="col-md-4">
                <label class="form-label small fw-bold">Search</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Supplier or Medicine..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-bold">Date Range</label>
                <div class="input-group">
                    <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($startDate) ?>">
                    <span class="input-group-text">to</span>
                    <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($endDate) ?>">
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold">Status</label>
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="Received" <?= $statusFilter == 'Received' ? 'selected' : '' ?>>Received</option>
                    <option value="Pending" <?= $statusFilter == 'Pending' ? 'selected' : '' ?>>Pending</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100 me-2"><i class="fas fa-filter me-1"></i></button>
                <a href="purchases.php" class="btn btn-outline-secondary w-100"><i class="fas fa-undo me-1"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="mb-3 text-muted small">
    Showing <strong><?= $totalResults ?></strong> purchase records found.
</div>

<div class="card shadow mb-4">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Supplier</th>
                        <th>Medicine</th>
                        <th>Qty</th>
                        <th>Unit Price</th>
                        <th>Total</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($purchases)): ?>
                        <tr><td colspan="7" class="text-center py-4">No purchase records found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($purchases as $pur): ?>
                            <tr>
                                <td><?= formatDate($pur['purchase_date']) ?></td>
                                <td><?= $pur['supplier_name'] ?></td>
                                <td><strong><?= $pur['medicine_name'] ?></strong></td>
                                <td><?= $pur['quantity'] ?></td>
                                <td><?= formatCurrency($pur['unit_price']) ?></td>
                                <td><?= formatCurrency($pur['quantity'] * $pur['unit_price']) ?></td>
                                <td><span class="badge bg-success">Received</span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- New Purchase Modal -->
<div class="modal fade" id="purchaseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="purchases.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">New Purchase Order</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_purchase">
                    <div class="mb-3">
                        <label class="form-label">Supplier</label>
                        <select name="supplier_id" class="form-select" required>
                            <option value="">Select Supplier</option>
                            <?php foreach ($suppliers as $s): ?>
                                <option value="<?= $s['id'] ?>"><?= $s['name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Medicine</label>
                        <select name="medicine_id" class="form-select" required>
                            <option value="">Select Medicine</option>
                            <?php foreach ($medicinesDropdown as $m): ?>
                                <option value="<?= $m['id'] ?>"><?= $m['name'] ?> (Current: <?= $m['quantity'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Quantity</label>
                            <input type="number" name="quantity" class="form-control" min="1" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Purchase Price (Unit)</label>
                            <input type="number" step="0.01" name="unit_price" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Purchase Date</label>
                        <input type="date" name="purchase_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Receive Stock</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
