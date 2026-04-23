<?php
/**
 * Common Helper Functions
 */

function cleanInput($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

function formatCurrency($amount) {
    return '$' . number_format($amount, 2);
}

function formatDate($date) {
    return date('d M, Y', strtotime($date));
}

function generateInvoiceNo() {
    return 'INV-' . strtoupper(substr(uniqid(), 7));
}

function getStockStatus($quantity, $threshold) {
    if ($quantity <= 0) {
        return '<span class="badge bg-danger">Out of Stock</span>';
    } elseif ($quantity <= $threshold) {
        return '<span class="badge bg-warning text-dark">Low Stock</span>';
    } else {
        return '<span class="badge bg-success">In Stock</span>';
    }
}

function isExpired($expiry_date) {
    $today = date('Y-m-d');
    $expiry = date('Y-m-d', strtotime($expiry_date));
    $warning_buffer = date('Y-m-d', strtotime('+30 days'));

    if ($expiry < $today) {
        return '<span class="badge bg-danger">Expired</span>';
    } elseif ($expiry <= $warning_buffer) {
        return '<span class="badge bg-warning text-dark">Expiring Soon</span>';
    } else {
        return '<span class="badge bg-info text-dark">Valid</span>';
    }
}

/**
 * CSRF Protection
 */
function getCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . getCsrfToken() . '">';
}
?>
