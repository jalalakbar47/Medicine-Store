<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin(); // Reports are for Admin only
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Analytics & Reports';
require_once __DIR__ . '/../includes/header.php';

// 1. Fetch Sales Data for last 30 days
$salesDataQuery = "SELECT DATE(sale_date) as date, SUM(final_amount) as revenue, 
                   SUM(si.quantity * (si.unit_price - m.purchase_price)) as profit
                   FROM sales s
                   JOIN sale_items si ON s.id = si.sale_id
                   JOIN medicines m ON si.medicine_id = m.id
                   WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                   GROUP BY DATE(sale_date)
                   ORDER BY date ASC";
$salesData = $pdo->query($salesDataQuery)->fetchAll();

// 2. Fetch Category Distribution
$categoryDistQuery = "SELECT c.name, SUM(si.subtotal) as total_sales
                      FROM sale_items si
                      JOIN medicines m ON si.medicine_id = m.id
                      JOIN categories c ON m.category_id = c.id
                      GROUP BY c.id
                      ORDER BY total_sales DESC";
$categoryDist = $pdo->query($categoryDistQuery)->fetchAll();

// 3. Top Selling Medicines
$topSellingQuery = "SELECT m.name, SUM(si.quantity) as total_qty, SUM(si.subtotal) as total_sales
                    FROM sale_items si
                    JOIN medicines m ON si.medicine_id = m.id
                    GROUP BY m.id
                    ORDER BY total_qty DESC
                    LIMIT 10";
$topSelling = $pdo->query($topSellingQuery)->fetchAll();

// Prepare charts data
$dates = json_encode(array_column($salesData, 'date'));
$revenues = json_encode(array_column($salesData, 'revenue'));
$profits = json_encode(array_column($salesData, 'profit'));

$catNames = json_encode(array_column($categoryDist, 'name'));
$catSales = json_encode(array_column($categoryDist, 'total_sales'));
?>

<div class="row mb-4">
    <div class="col-12">
        <h1 class="h3 mb-0 text-gray-800">Business Analytics</h1>
        <p class="text-muted">Proactive insights into your pharmacy's performance.</p>
    </div>
</div>

<div class="row">
    <!-- Revenue vs Profit Chart -->
    <div class="col-xl-8 col-lg-7">
        <div class="card shadow mb-4 border-0">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between bg-white border-0">
                <h6 class="m-0 font-weight-bold text-primary">Revenue & Profit (Last 30 Days)</h6>
            </div>
            <div class="card-body">
                <div class="chart-area">
                    <canvas id="salesChart" style="height: 320px;"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Category Distribution -->
    <div class="col-xl-4 col-lg-5">
        <div class="card shadow mb-4 border-0">
            <div class="card-header py-3 bg-white border-0">
                <h6 class="m-0 font-weight-bold text-primary">Sales by Category</h6>
            </div>
            <div class="card-body">
                <div class="chart-pie pt-4 pb-2">
                    <canvas id="categoryChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Top Selling Products -->
    <div class="col-lg-12">
        <div class="card shadow mb-4 border-0">
            <div class="card-header py-3 bg-white border-0 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">Top 10 Selling Medicines</h6>
                <button onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="fas fa-print me-1"></i> Print Report</button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light text-uppercase small">
                            <tr>
                                <th>Medicine Name</th>
                                <th class="text-center">Units Sold</th>
                                <th class="text-end">Total Revenue</th>
                                <th class="text-end">Growth</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topSelling as $item): ?>
                                <tr>
                                    <td class="fw-bold text-dark"><?= $item['name'] ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-2"><?= number_format($item['total_qty']) ?></span>
                                    </td>
                                    <td class="text-end fw-bold"><?= formatCurrency($item['total_sales']) ?></td>
                                    <td class="text-end">
                                        <span class="text-success small"><i class="fas fa-caret-up"></i> Stable</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Load Chart.js from CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Line Chart: Revenue vs Profit
const salesCtx = document.getElementById('salesChart').getContext('2d');
new Chart(salesCtx, {
    type: 'line',
    data: {
        labels: <?= $dates ?>,
        datasets: [{
            label: 'Revenue',
            data: <?= $revenues ?>,
            borderColor: '#4e73df',
            backgroundColor: 'rgba(78, 115, 223, 0.05)',
            fill: true,
            tension: 0.3
        }, {
            label: 'Profit',
            data: <?= $profits ?>,
            borderColor: '#1cc88a',
            backgroundColor: 'rgba(28, 200, 138, 0.05)',
            fill: true,
            tension: 0.3
        }]
    },
    options: {
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'top' }
        },
        scales: {
            y: { beginAtZero: true }
        }
    }
});

// Pie Chart: Category Sales
const catCtx = document.getElementById('categoryChart').getContext('2d');
new Chart(catCtx, {
    type: 'doughnut',
    data: {
        labels: <?= $catNames ?>,
        datasets: [{
            data: <?= $catSales ?>,
            backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#858796'],
            hoverOffset: 4
        }]
    },
    options: {
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom' }
        },
        cutout: '70%'
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
