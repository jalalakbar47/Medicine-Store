<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$pageTitle = $id ? "Edit Medicine" : "Add New Medicine";

$medicine = [
    'name' => '',
    'category_id' => '',
    'batch_number' => '',
    'expiry_date' => '',
    'quantity' => 0,
    'purchase_price' => '',
    'selling_price' => '',
    'low_stock_threshold' => 10
];

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM medicines WHERE id = ?");
    $stmt->execute([$id]);
    $medicine = $stmt->fetch();
    if (!$medicine) {
        setFlash('danger', 'Medicine not found.');
        header("Location: medicines.php");
        exit();
    }
}

// Fetch Categories
$stmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
$categories = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = cleanInput($_POST['name']);
    $category_id = (int)$_POST['category_id'];
    $batch_number = cleanInput($_POST['batch_number']);
    $expiry_date = $_POST['expiry_date'];
    $quantity = (int)$_POST['quantity'];
    $purchase_price = (float)$_POST['purchase_price'];
    $selling_price = (float)$_POST['selling_price'];
    $low_stock_threshold = (int)$_POST['low_stock_threshold'];

    if (empty($name) || empty($expiry_date)) {
        setFlash('danger', 'Please fill in all required fields.');
    } else {
        if ($id) {
            $sql = "UPDATE medicines SET name=?, category_id=?, batch_number=?, expiry_date=?, quantity=?, purchase_price=?, selling_price=?, low_stock_threshold=? WHERE id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$name, $category_id, $batch_number, $expiry_date, $quantity, $purchase_price, $selling_price, $low_stock_threshold, $id]);
            setFlash('success', 'Medicine updated successfully!');
        } else {
            $sql = "INSERT INTO medicines (name, category_id, batch_number, expiry_date, quantity, purchase_price, selling_price, low_stock_threshold) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$name, $category_id, $batch_number, $expiry_date, $quantity, $purchase_price, $selling_price, $low_stock_threshold]);
            setFlash('success', 'Medicine added successfully!');
        }
        header("Location: medicines.php");
        exit();
    }
}

include_once __DIR__ . '/../includes/header.php';
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800 fw-bold"><?= $pageTitle ?></h1>
    <a href="medicines.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-2"></i> Back to List
    </a>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 fw-bold text-primary">Medicine Details</h6>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label fw-bold">Medicine Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($medicine['name']) ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Category</label>
                            <select name="category_id" class="form-select">
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= $medicine['category_id'] == $cat['id'] ? 'selected' : '' ?>>
                                        <?= $cat['name'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Batch Number</label>
                            <input type="text" name="batch_number" class="form-control" value="<?= htmlspecialchars($medicine['batch_number']) ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Expiry Date <span class="text-danger">*</span></label>
                            <input type="date" name="expiry_date" class="form-control" value="<?= $medicine['expiry_date'] ?>" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Current Stock</label>
                            <input type="number" name="quantity" class="form-control" value="<?= $medicine['quantity'] ?>" min="0">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Purchase Price</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" name="purchase_price" class="form-control" step="0.01" value="<?= $medicine['purchase_price'] ?>">
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Selling Price</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" name="selling_price" class="form-control" step="0.01" value="<?= $medicine['selling_price'] ?>">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Low Stock Threshold</label>
                            <input type="number" name="low_stock_threshold" class="form-control" value="<?= $medicine['low_stock_threshold'] ?>" min="1">
                            <small class="text-muted">System will alert when stock falls below this level.</small>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary px-4 fw-bold">
                            <i class="fas fa-save me-2"></i> Save Medicine
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card shadow mb-4 bg-light border-0">
            <div class="card-body p-4">
                <h5 class="fw-bold text-primary mb-3"><i class="fas fa-info-circle me-2"></i> Important Tips</h5>
                <ul class="small text-muted ps-3">
                    <li class="mb-2">Always check the <strong>Batch Number</strong> against the physical box.</li>
                    <li class="mb-2">Ensure the <strong>Expiry Date</strong> is correctly entered to receive timely alerts.</li>
                    <li class="mb-2">The <strong>Selling Price</strong> should be calculated including your profit margin and taxes.</li>
                    <li>Set a realistic <strong>Low Stock Threshold</strong> based on lead times from suppliers.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
