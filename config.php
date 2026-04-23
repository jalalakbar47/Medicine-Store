<?php
/**
 * Global Configuration Settings
 */

// Application Settings
define('APP_NAME', 'PharmaCare');
define('APP_URL', 'http://localhost/medecne store');

// Database Settings
define('DB_HOST', 'localhost');
define('DB_NAME', 'pharmacy_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Stock Settings
define('LOW_STOCK_THRESHOLD', 10);

// Set default timezone
date_default_timezone_set('Asia/Kolkata');

// Error Reporting (Development)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Pagination
define('RECORDS_PER_PAGE', 10);
?>
