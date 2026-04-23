<?php
require_once __DIR__ . '/../includes/db.php';

if (!isset($_GET['id'])) {
    header("Location: sales_history.php");
    exit();
}

$sale_id = $_GET['id'];

// Fetch Sale Header
$stmt = $pdo->prepare("SELECT s.*, p.name as patient_name, p.contact as patient_contact, p.address as patient_address 
                       FROM sales s 
                       LEFT JOIN patients p ON s.patient_id = p.id 
                       WHERE s.id = ?");
$stmt->execute([$sale_id]);
$sale = $stmt->fetch();

if (!$sale) {
    die("Invoice not found.");
}

// Fetch Sale Items
$stmtItems = $pdo->prepare("SELECT si.*, m.name as medicine_name, m.batch_number 
                            FROM sale_items si 
                            JOIN medicines m ON si.medicine_id = m.id 
                            WHERE si.sale_id = ?");
$stmtItems->execute([$sale_id]);
$items = $stmtItems->fetchAll();

$pageTitle = 'Invoice #' . $sale['invoice_no'];
if (!isset($_GET['print'])) {
    require_once __DIR__ . '/../includes/header.php';
} else {
    // Basic CSS for printing
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">';
    echo '<style>body { background: #fff; padding: 20px; } @media print { .no-print { display: none; } }</style>';
}
?>

<div class="container-fluid">
    <div class="row mb-4 no-print">
        <div class="col">
            <a href="sales_history.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i> Back</a>
            <div class="float-end">
                <a href="generate_invoice.php?sale_id=<?= $sale['id'] ?>" target="_blank" class="btn btn-danger btn-sm me-2">
                    <i class="fas fa-file-pdf me-1"></i> Download PDF
                </a>
                <button onclick="window.print()" class="btn btn-dark btn-sm"><i class="fas fa-print me-1"></i> Print Invoice</button>
            </div>
        </div>
    </div>

    <div class="card shadow border-0" id="invoice">
        <div class="card-body p-5">
            <div class="row mb-5">
                <div class="col-sm-6">
                    <h2 class="text-primary mb-0"><?= APP_NAME ?></h2>
                    <p class="text-muted">123 Health Street, City Center<br>Phone: +1 234 567 890<br>Email: contact@pharmacare.com</p>
                </div>
                <div class="col-sm-6 text-sm-end">
                    <h3 class="text-uppercase">Invoice</h3>
                    <div class="mb-1"><strong>Invoice No:</strong> #<?= $sale['invoice_no'] ?></div>
                    <div><strong>Date:</strong> <?= formatDate($sale['sale_date']) ?></div>
                    <div><strong>Time:</strong> <?= date('h:i A', strtotime($sale['sale_date'])) ?></div>
                </div>
            </div>

            <hr class="my-4">

            <div class="row mb-5">
                <div class="col-sm-6">
                    <h6 class="text-muted text-uppercase mb-3">Bill To:</h6>
                    <h5 class="mb-1"><?= $sale['patient_name'] ?: 'Walk-in Customer' ?></h5>
                    <?php if($sale['patient_contact']): ?>
                        <p class="text-muted mb-0">Phone: <?= $sale['patient_contact'] ?></p>
                    <?php endif; ?>
                    <?php if($sale['patient_address']): ?>
                        <p class="text-muted mb-0"><?= $sale['patient_address'] ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-striped table-borderless">
                    <thead class="bg-primary text-white">
                        <tr>
                            <th class="py-3">#</th>
                            <th class="py-3">Medicine Name</th>
                            <th class="py-3">Batch</th>
                            <th class="py-3 text-center">Qty</th>
                            <th class="py-3 text-end">Unit Price</th>
                            <th class="py-3 text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $index => $item): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><strong><?= $item['medicine_name'] ?></strong></td>
                                <td><?= $item['batch_number'] ?></td>
                                <td class="text-center"><?= $item['quantity'] ?></td>
                                <td class="text-end"><?= formatCurrency($item['unit_price']) ?></td>
                                <td class="text-end"><?= formatCurrency($item['subtotal']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="row mt-4 justify-content-end">
                <div class="col-sm-5 col-md-4 col-lg-3">
                    <table class="table table-sm table-borderless">
                        <tr>
                            <td class="text-muted">Subtotal</td>
                            <td class="text-end fw-bold"><?= formatCurrency($sale['total_amount']) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Discount</td>
                            <td class="text-end text-danger">- <?= formatCurrency($sale['discount']) ?></td>
                        </tr>
                        <tr class="border-top">
                            <td class="h5 mb-0">Grand Total</td>
                            <td class="h5 mb-0 text-primary fw-bold text-end"><?= formatCurrency($sale['final_amount']) ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="mt-5 pt-5 text-center text-muted">
                <p>Thank you for shopping with <?= APP_NAME ?>!</p>
                <div class="small">This is a computer generated invoice.</div>
            </div>
        </div>
    </div>
</div>

<?php if (isset($_GET['print'])): ?>
    <script>window.onload = function() { window.print(); }</script>
<?php endif; ?>

<?php 
if (!isset($_GET['print'])) {
    require_once __DIR__ . '/../includes/footer.php'; 
}
?>
