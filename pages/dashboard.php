<?php
require_once __DIR__ . '/../includes/db.php';
$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';

// Fetch Statistics
// 1. Total Medicines
$totalMedicines = $pdo->query("SELECT COUNT(*) FROM medicines")->fetchColumn();

// 2. Today's Revenue
$todayRevenue = $pdo->query("SELECT SUM(final_amount) FROM sales WHERE DATE(sale_date) = CURDATE()")->fetchColumn() ?: 0;

// 3. Low Stock Alerts Count
$lowStockCount = $pdo->query("SELECT COUNT(*) FROM medicines WHERE quantity <= low_stock_threshold")->fetchColumn();

// 4. Expiry Alerts Count (Expiring in next 30 days)
$expiryAlertCount = $pdo->query("SELECT COUNT(*) FROM medicines WHERE expiry_date >= CURDATE() AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();

// 5. Total Patients
$totalPatients = $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();

// 6. Today's Profit (Revenue - Purchase Price)
$profitQuery = "SELECT SUM(si.quantity * (si.unit_price - m.purchase_price)) 
                FROM sale_items si 
                JOIN medicines m ON si.medicine_id = m.id 
                JOIN sales s ON si.sale_id = s.id 
                WHERE DATE(s.sale_date) = CURDATE()";
$todayProfit = $pdo->query($profitQuery)->fetchColumn() ?: 0;

// Recent Sales (Last 10)
$recentSalesQuery = "SELECT s.*, p.name as patient_name 
                    FROM sales s 
                    LEFT JOIN patients p ON s.patient_id = p.id 
                    ORDER BY s.sale_date DESC LIMIT 10";
$recentSales = $pdo->query($recentSalesQuery)->fetchAll();

// Low Stock Medicines
$lowStockMedicines = $pdo->query("SELECT * FROM medicines WHERE quantity <= low_stock_threshold ORDER BY quantity ASC LIMIT 5")->fetchAll();

// Expiring Soon Medicines
$expiringSoon = $pdo->query("SELECT * FROM medicines WHERE expiry_date >= CURDATE() AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) ORDER BY expiry_date ASC LIMIT 5")->fetchAll();
?>

<div class="row">
    <!-- Stat Cards -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Medicines</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($totalMedicines) ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-pills fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Today's Revenue</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= formatCurrency($todayRevenue) ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-danger shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Stock / Expiry Alerts</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?= number_format($lowStockCount) ?> / <?= number_format($expiryAlertCount) ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Today's Profit (Est.)</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= formatCurrency($todayProfit) ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <!-- Recent Sales Table -->
    <div class="col-lg-8">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">Recent Sales</h6>
                <a href="<?= APP_URL ?>/pages/sales_history.php" class="btn btn-sm btn-primary">View All</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Invoice</th>
                                <th>Patient</th>
                                <th>Date</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentSales)): ?>
                                <tr><td colspan="4" class="text-center">No sales records found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recentSales as $sale): ?>
                                    <tr>
                                        <td>#<?= $sale['invoice_no'] ?></td>
                                        <td><?= $sale['patient_name'] ?: 'Walk-in Customer' ?></td>
                                        <td><?= formatDate($sale['sale_date']) ?></td>
                                        <td><?= formatCurrency($sale['final_amount']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Stock & Expiry Alerts Panel -->
    <div class="col-lg-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-danger">Inventory Alerts</h6>
            </div>
            <div class="card-body">
                <ul class="nav nav-tabs id="alertTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active small py-1" id="stock-tab" data-bs-toggle="tab" data-bs-target="#stock-alerts" type="button" role="tab">Low Stock (<?= count($lowStockMedicines) ?>)</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link small py-1 text-warning" id="expiry-tab" data-bs-toggle="tab" data-bs-target="#expiry-alerts" type="button" role="tab">Expiring (<?= count($expiringSoon) ?>)</button>
                    </li>
                </ul>
                <div class="tab-content pt-3" id="alertTabsContent">
                    <!-- Low Stock Tab -->
                    <div class="tab-pane fade show active" id="stock-alerts" role="tabpanel">
                        <?php if (empty($lowStockMedicines)): ?>
                            <p class="text-center text-success mb-0 small"><i class="fas fa-check-circle"></i> Stock levels okay.</p>
                        <?php else: ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($lowStockMedicines as $med): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2 border-0 border-bottom">
                                        <div class="text-truncate" style="max-width: 140px;">
                                            <h6 class="mb-0 small fw-bold"><?= $med['name'] ?></h6>
                                            <small class="text-muted">Batch: <?= $med['batch_number'] ?></small>
                                        </div>
                                        <span class="badge bg-danger rounded-pill"><?= $med['quantity'] ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                    <!-- Expiry Tab -->
                    <div class="tab-pane fade" id="expiry-alerts" role="tabpanel">
                        <?php if (empty($expiringSoon)): ?>
                            <p class="text-center text-success mb-0 small"><i class="fas fa-check-circle"></i> No near expiry items.</p>
                        <?php else: ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($expiringSoon as $med): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2 border-0 border-bottom">
                                        <div class="text-truncate" style="max-width: 140px;">
                                            <h6 class="mb-0 small fw-bold"><?= $med['name'] ?></h6>
                                            <small class="text-muted">Exp: <?= formatDate($med['expiry_date']) ?></small>
                                        </div>
                                        <span class="badge bg-warning text-dark rounded-pill">Soon</span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
                <a href="<?= APP_URL ?>/pages/medicines.php" class="btn btn-sm btn-outline-primary w-100 mt-3">Manage Inventory</a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
