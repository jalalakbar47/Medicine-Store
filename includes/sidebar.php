<div id="sidebar-wrapper">
    <div class="sidebar-heading text-center">
        <i class="fas fa-prescription-bottle-alt me-2"></i> PHARMA<span>CARE</span>
    </div>
    <div class="list-group list-group-flush mt-3">
        <a href="<?= APP_URL ?>/pages/dashboard.php" class="list-group-item list-group-item-action <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>">
            <i class="fas fa-fw fa-tachometer-alt"></i> Dashboard
        </a>
        
        <div class="text-white-50 small fw-bold px-4 mt-4 mb-2 text-uppercase">Inventory</div>
        <a href="<?= APP_URL ?>/pages/medicines.php" class="list-group-item list-group-item-action <?= basename($_SERVER['PHP_SELF']) == 'medicines.php' ? 'active' : '' ?>">
            <i class="fas fa-fw fa-pills"></i> Medicines
        </a>
        <a href="<?= APP_URL ?>/pages/categories.php" class="list-group-item list-group-item-action <?= basename($_SERVER['PHP_SELF']) == 'categories.php' ? 'active' : '' ?>">
            <i class="fas fa-fw fa-list"></i> Categories
        </a>
        
        <div class="text-white-50 small fw-bold px-4 mt-4 mb-2 text-uppercase">Transactions</div>
        <a href="<?= APP_URL ?>/pages/pos.php" class="list-group-item list-group-item-action <?= basename($_SERVER['PHP_SELF']) == 'pos.php' ? 'active' : '' ?>">
            <i class="fas fa-fw fa-cash-register"></i> POS / Billing
        </a>
        <a href="<?= APP_URL ?>/pages/sales_history.php" class="list-group-item list-group-item-action <?= basename($_SERVER['PHP_SELF']) == 'sales_history.php' ? 'active' : '' ?>">
            <i class="fas fa-fw fa-history"></i> Sales History
        </a>
        
        <div class="text-white-50 small fw-bold px-4 mt-4 mb-2 text-uppercase">People</div>
        <a href="<?= APP_URL ?>/pages/patients.php" class="list-group-item list-group-item-action <?= basename($_SERVER['PHP_SELF']) == 'patients.php' ? 'active' : '' ?>">
            <i class="fas fa-fw fa-user-injured"></i> Patients
        </a>
        
        <?php if ($_SESSION['role'] === 'admin'): ?>
        <a href="<?= APP_URL ?>/pages/suppliers.php" class="list-group-item list-group-item-action <?= basename($_SERVER['PHP_SELF']) == 'suppliers.php' ? 'active' : '' ?>">
            <i class="fas fa-fw fa-truck-loading"></i> Suppliers
        </a>
        
        <div class="text-white-50 small fw-bold px-4 mt-4 mb-2 text-uppercase">Management</div>
        <a href="<?= APP_URL ?>/pages/users.php" class="list-group-item list-group-item-action <?= basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : '' ?>">
            <i class="fas fa-fw fa-users-cog"></i> Users & Staff
        </a>
        <a href="<?= APP_URL ?>/pages/purchases.php" class="list-group-item list-group-item-action <?= basename($_SERVER['PHP_SELF']) == 'purchases.php' ? 'active' : '' ?>">
            <i class="fas fa-fw fa-shopping-cart"></i> Purchase Orders
        </a>
        <a href="<?= APP_URL ?>/pages/reports.php" class="list-group-item list-group-item-action <?= basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : '' ?>">
            <i class="fas fa-fw fa-chart-bar"></i> Analytics & Reports
        </a>
        <?php endif; ?>
    </div>
</div>
