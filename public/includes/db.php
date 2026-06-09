<?php
defined('XCHANGE') or exit;

require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;

    if ($pdo !== null) return $pdo;

    // Build the connection string from config.php constants
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,  // throw exceptions on errors
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,        // return rows as arrays
            PDO::ATTR_EMULATE_PREPARES   => false,                    // use real prepared statements
        ]);
    } catch (PDOException $e) {
        // Show the full error in dev, a friendly message in production
        if (APP_ENV === 'development') throw $e;
        http_response_code(500);
        exit('Database connection failed. Please try again later.');
    }

    return $pdo;
}
