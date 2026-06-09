<?php
defined('XCHANGE') or exit;

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// helper functions used across the whole app

// escape html before echoing user input - prevents XSS
function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// format a price in cents as a ZAR string e.g. R 1 250.00
function format_zar(int $cents): string
{
    return 'R ' . number_format($cents / 100, 2, '.', ' ');
}

// redirect to a url within the app
function redirect(string $path): void
{
    header('Location: ' . APP_URL . '/' . ltrim($path, '/'));
    exit;
}

// returns human readable time like "3 hr ago" or "12 May 2024"
function time_ago(string $datetime): string
{
    $diff = time() - strtotime($datetime);

    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return floor($diff / 60) . ' min ago';
    if ($diff < 86400)  return floor($diff / 3600) . ' hr ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';

    return date('d M Y', strtotime($datetime));
}

// shorten a string and add ... at the end if its too long
function truncate(string $s, int $max = 120): string
{
    if (mb_strlen($s) <= $max) return $s;
    return mb_substr($s, 0, $max - 1) . '…';
}

// check if a date of birth means the person is 18 or older
function is_adult(?string $dob): bool
{
    if (!$dob) return false;
    return (new DateTime($dob))->diff(new DateTime())->y >= 18;
}

// validate a 1-5 star rating value
function valid_rating(mixed $v): bool
{
    return is_numeric($v) && (int)$v >= 1 && (int)$v <= 5;
}

// === IMAGE UPLOADS ===

// check an uploaded file is a valid image (jpeg or png, under 2mb)
// reads the actual mime type, not just the file extension
function validate_image_upload(array $file): string|false
{
    if ($file['error'] !== UPLOAD_ERR_OK) return false;
    if ($file['size'] > UPLOAD_MAX_BYTES)  return false;

    $allowed = ['image/jpeg', 'image/png'];
    $mime    = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);

    return in_array($mime, $allowed, true) ? $mime : false;
}

// save uploaded image to the listings folder, returns relative path or false
function save_listing_image(array $file, int $userId): string|false
{
    $mime = validate_image_upload($file);
    if (!$mime) return false;

    $ext  = ($mime === 'image/jpeg') ? 'jpg' : 'png';
    $dir  = UPLOAD_PATH . $userId . '/';
    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    $dest = $dir . $name;

    if (!is_dir($dir)) mkdir($dir, 0755, true);
    if (!move_uploaded_file($file['tmp_name'], $dest)) return false;

    return $userId . '/' . $name;
}

// delete an image file from disk (silently ignores if file doesnt exist)
function delete_listing_image_file(?string $path): void
{
    if (!$path) return;
    $full = UPLOAD_PATH . $path;
    if (is_file($full)) @unlink($full);
}
