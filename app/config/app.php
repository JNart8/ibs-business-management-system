<?php

/**
 * Application Configuration
 * General settings for the entire application
 */

// Prevent direct access to this file
if (!defined('APP_START')) {
    die('Direct access not permitted');
}

// Load environment variables if not already loaded
if (!getenv('DB_HOST')) {
    require_once __DIR__ . '/database.php';
}

// Error reporting based on environment
if (getenv('APP_ENV') === 'production') {
    // Production: Hide errors from users, log them instead
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../../storage/logs/error.log');
} else {
    // Development: Show all errors
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Timezone (adjust to your location)
date_default_timezone_set('Africa/Blantyre');

// Session configuration
ini_set('session.cookie_httponly', 1); // Prevent JavaScript access to session cookie
ini_set('session.use_only_cookies', 1); // Don't allow session ID in URL
ini_set('session.cookie_lifetime', getenv('SESSION_LIFETIME') * 60); // Convert minutes to seconds

// Application constants
define('APP_NAME', getenv('APP_NAME'));
define('APP_URL', getenv('APP_URL'));
define('APP_VERSION', '1.0.0');

// Base URL for links (important for XAMPP subdirectory)
$scriptName = dirname($_SERVER['SCRIPT_NAME']);
define('BASE_URL', rtrim($scriptName, '/'));

// Path constants (makes it easy to reference folders)
define('BASE_PATH', dirname(__DIR__, 2)); // Root folder
define('APP_PATH', BASE_PATH . '/app');
define('PUBLIC_PATH', BASE_PATH . '/public');
define('STORAGE_PATH', BASE_PATH . '/storage');
define('UPLOAD_PATH', PUBLIC_PATH . '/uploads');

// Currency settings (for displaying prices)
define('CURRENCY_HOLDER', 'GHS');
define('CURRENCY_FORMAT', 'before'); // 'before' = GHS 1,000 | 'after' = 1,000 GHS

// Pagination
define('ITEMS_PER_PAGE', 20);

// Low stock threshold (default if not in database)
define('DEFAULT_LOW_STOCK_LEVEL', 10);

// File upload settings
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB in bytes
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

// Security settings
define('PASSWORD_MIN_LENGTH', 6);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_TIMEOUT_MINUTES', 15);

/**
 * Autoload helper files
 * This automatically includes common functions
 */
$helpers = [
    'functions.php',
    'validation.php',
    'response.php'
];

foreach ($helpers as $helper) {
    $file = APP_PATH . '/helpers/' . $helper;
    if (file_exists($file)) {
        require_once $file;
    }
}

/**
 * Initialize session if not already started
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Security: Regenerate session ID periodically
 * This prevents session fixation attacks
 */
if (!isset($_SESSION['LAST_REGENERATION'])) {
    $_SESSION['LAST_REGENERATION'] = time();
} else if (time() - $_SESSION['LAST_REGENERATION'] > 1800) {
    // Regenerate every 30 minutes
    session_regenerate_id(true);
    $_SESSION['LAST_REGENERATION'] = time();
}

/**
 * CSRF Token Generation
 * This prevents Cross-Site Request Forgery attacks
 */
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Create required directories if they don't exist
 */
$requiredDirs = [
    STORAGE_PATH . '/cache',
    STORAGE_PATH . '/logs',
    UPLOAD_PATH
];

foreach ($requiredDirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

/**
 * Application-wide database connection
 * This makes the database available everywhere
 */
require_once __DIR__ . '/database.php';
