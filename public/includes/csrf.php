<?php
defined('XCHANGE') or exit;

require_once __DIR__ . '/auth.php';

// csrf protection - prevents cross-site request forgery attacks

// get or create a random token for this session
function csrf_token(): string
{
    start_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// output a hidden input to put inside every form
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

// check the token on form submit, kill the request if it doesnt match
function csrf_check(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    if (!hash_equals(csrf_token(), $submitted)) {
        http_response_code(403);
        exit('Invalid form token. Please go back and try again.');
    }
}
