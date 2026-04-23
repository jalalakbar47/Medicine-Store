<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Initialize Filter Variables
$search = $_GET['search'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$minAmount = $_GET['min_amount'] ?? '';
$maxAmount = $_GET['max_amount'] ?? '';

// Build Query
$query = "SELECT s.*, p.name as patient_name 
          FROM sales s 
          LEFT JOIN patients p ON s.patient_id = p.id 
          WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (s.invoice_no LIKE ? OR p.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($startDate)) {
    $query .= " AND DATE(s.sale_date) >= ?";
    $params[] = $startDate;
}

if (!empty($endDate)) {
    $query .= " AND DATE(s.sale_date) <= ?";
    $params[] = $endDate;
}

if (!empty($minAmount)) {
    $query .= " AND s.final_amount >= ?";
    $params[] = $minAmount;
}

if (!empty($maxAmount)) {
    $query .= " AND s.final_amount <= ?";
    $params[] = $maxAmount;
}

$query .= " ORDER BY s.sale_date DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$sales = $stmt->fetchAll();
$totalResults = count($sales);

// FEATURE 1: Export to CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = "sales_history_export_" . date('Y-m-d') . ".csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Invoice No', 'Patient Name', 'Date', 'Total', 'Discount', 'Grand Total']);
    
    foreach ($sales as $sale) {
        fputcsv($output, [
            $sale['invoice_no'],
            $sale['patient_name'] ?: 'Walk-in Customer',
            $sale['sale_date'],
            $sale['total_amount'],
            $sale['discount'],
            $sale['final_amount']
        ]);
    }
    fclose($output);
    exit();
}

$pageTitle = 'Sales History';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">Sales History</h1>
    <div>
        <a href="sales_history.php?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn btn-success me-2">
            <i class="fas fa-file-csv me-2"></i> Export CSV
        </a>
        <a href="pos.php" class="btn btn-primary"><i class="fas fa-plus me-2"></i> New Sale</a>
    </div>
</div>

<!-- Advanced Search and Filters -->
<div class="card shadow mb-4">
    <div class="card-header py-3 bg-light">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-filter me-2"></i> Search & Filters</h6>
    </div>
    <div class="card-body">
        <form method="GET" action="sales_history.php" id="filterForm">
            <div class="row g-3">
                <div class="col-md-4 text-dark">
                    <label class="form-label small fw-bold">Search</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Invoice # or Patient Name" value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>

                <div class="col-md-4 text-dark">
                    <label class="form-label small fw-bold">Date Range</label>
                    <div class="input-group">
                        <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($startDate) ?>">
                        <span class="input-group-text">to</span>
                        <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($endDate) ?>">
                    </div>
                </div>

                <div class="col-md-4 text-dark">
                    <label class="form-label small fw-bold">Amount Range</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" step="0.01" name="min_amount" class="form-control" placeholder="Min" value="<?= htmlspecialchars($minAmount) ?>">
                        <span class="input-group-text">-</span>
                        <input type="number" step="0.01" name="max_amount" class="form-control" placeholder="Max" value="<?= htmlspecialchars($maxAmount) ?>">
                    </div>
                </div>

                <div class="col-12 d-flex justify-content-between align-items-center mt-3">
                    <div class="text-muted small">
                        <i class="fas fa-info-circle me-1"></i> Showing <strong><?= $totalResults ?></strong> results found.
                    </div>
                    <div>
                        <a href="sales_history.php" class="btn btn-outline-secondary me-2">
                            <i class="fas fa-undo me-1"></i> Reset
                        </a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="fas fa-search me-1"></i> Search Records
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Active Filter Badges -->
<?php if (!empty($search) || !empty($startDate) || !empty($endDate) || !empty($minAmount) || !empty($maxAmount)): ?>
    <div class="mb-3">
        <span class="small text-muted me-2">Active Filters:</span>
        <?php if(!empty($search)): ?>
            <span class="badge bg-primary rounded-pill me-1"><?= htmlspecialchars($search) ?> <a href="sales_history.php?<?= http_build_query(array_merge($_GET, ['search' => ''])) ?>" class="text-white ms-1 text-decoration-none">&times;</a></span>
        <?php endif; ?>
        <?php if(!empty($startDate) || !empty($endDate)): ?>
            <span class="badge bg-primary rounded-pill me-1"><?= $startDate ?: 'Any' ?> to <?= $endDate ?: 'Any' ?> <a href="sales_history.php?<?= http_build_query(array_merge($_GET, ['start_date' => '', 'end_date' => ''])) ?>" class="text-white ms-1 text-decoration-none">&times;</a></span>
        <?php endif; ?>
        <?php if(!empty($minAmount) || !empty($maxAmount)): ?>
            <span class="badge bg-primary rounded-pill me-1">$<?= $minAmount ?: '0' ?> - $<?= $maxAmount ?: 'Any' ?> <a href="sales_history.php?<?= http_build_query(array_merge($_GET, ['min_amount' => '', 'max_amount' => ''])) ?>" class="text-white ms-1 text-decoration-none">&times;</a></span>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="card shadow mb-4">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                <thead class="table-light">
                    <tr>
                        <th>Date/Time</th>
                        <th>Invoice No</th>
                        <th>Patient</th>
                        <th class="text-end">Final Amount</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sales)): ?>
                        <tr><td colspan="5" class="text-center py-4">No records found matching your criteria.</td></tr>
                    <?php else: ?>
                        <?php foreach ($sales as $sale): ?>
                            <tr>
                                <td>
                                    <?= formatDate($sale['sale_date']) ?>
                                    <br><small class="text-muted"><?= date('h:i A', strtotime($sale['sale_date'])) ?></small>
                                </td>
                                <td><span class="badge bg-light text-dark border">#<?= $sale['invoice_no'] ?></span></td>
                                <td><?= $sale['patient_name'] ?: '<span class="text-muted">Walk-in Customer</span>' ?></td>
                                <td class="text-end fw-bold text-primary"><?= formatCurrency($sale['final_amount']) ?></td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <a href="generate_invoice.php?sale_id=<?= $sale['id'] ?>" target="_blank" class="btn btn-outline-danger" title="Download PDF">
                                            <i class="fas fa-file-pdf"></i>
                                        </a>
                                        <a href="invoice.php?id=<?= $sale['id'] ?>" class="btn btn-outline-primary" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="invoice.php?id=<?= $sale['id'] ?>&print=true" target="_blank" class="btn btn-outline-dark" title="Print">
                                            <i class="fas fa-print"></i>
                                        </a>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
