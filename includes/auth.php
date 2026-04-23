<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Authentication Helper Functions
 */

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: " . APP_URL . "/pages/login.php");
        exit();
    }
}

function requireAdmin() {
    requireLogin();
    if ($_SESSION['role'] !== 'admin') {
        die("Unauthorized Access: Administrator privileges required.");
    }
}

function setFlash($message, $type = 'success') {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

function displayFlash() {
    if (isset($_SESSION['flash'])) {
        $type = $_SESSION['flash']['type'];
        $message = $_SESSION['flash']['message'];
        unset($_SESSION['flash']);
        
        $alertClass = $type;
        if($type === 'error') $alertClass = 'danger';
        
        return "<div class='alert alert-{$alertClass} alert-dismissible fade show' role='alert'>
                    {$message}
                    <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                </div>";
    }
    return '';
}
?>
