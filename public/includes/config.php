<?php
defined('XCHANGE') or exit;

// ─── Environment detection ────────────────────────────────────────────────────
// We detect which environment the app is running in by checking the HTTP_HOST
// header. If the host is 'localhost' or '127.0.0.1' we are running locally on
// XAMPP, so we use local database credentials and a local APP_URL.
// Otherwise we assume the site is live on InfinityFree and use the production
// credentials. This means one config file works for both environments without
// any manual switching between deploys.
if ($_SERVER['HTTP_HOST'] === 'localhost' ||
    str_contains($_SERVER['HTTP_HOST'], '127.0.0.1')) {
    // Local XAMPP development environment
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'xchangelocal');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('APP_URL', 'http://localhost/c2c web dev project/public');
    define('APP_ENV', 'development');
} else {
    // Live InfinityFree production environment
    define('DB_HOST', 'sql303.infinityfree.com');
    define('DB_NAME', 'if0_42100298_xchange');
    define('DB_USER', 'if0_42100298');
    define('DB_PASS', 'vadercodesIRL1');
    define('APP_URL', 'https://xchangelocal.infinityfree.me');
    define('APP_ENV', 'production');
}
define('DB_CHARSET', 'utf8mb4');

// ─── Application ─────────────────────────────────────────────────────────────
define('APP_NAME', 'XchangeLocal');

// ─── File uploads ─────────────────────────────────────────────────────────────
// UPLOAD_PATH is the server filesystem path where listing images are stored.
// UPLOAD_URL is the public URL buyers/sellers use to view those images in a browser.
// UPLOAD_MAX_BYTES enforces a 3 MB limit per photo upload.
define('UPLOAD_PATH', dirname(__DIR__) . '/uploads/listings/');
define('UPLOAD_URL',  APP_URL . '/uploads/listings/');
define('UPLOAD_MAX_BYTES', 3 * 1024 * 1024); // 3 MB per image

// ─── Session ─────────────────────────────────────────────────────────────────
// SESSION_SECURE forces the session cookie to be sent over HTTPS only.
// We enable this on production (InfinityFree uses HTTPS) but disable it on
// localhost because XAMPP does not use HTTPS by default.
define('SESSION_NAME',   'xl_sess');
define('SESSION_SECURE', APP_ENV === 'production');

// ─── Error reporting ─────────────────────────────────────────────────────────
if (APP_ENV === 'development') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
    ini_set('log_errors', '1');
    ini_set('error_log', dirname(__DIR__) . '/logs/php_errors.log');
}
