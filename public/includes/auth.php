<?php
defined('XCHANGE') or exit;

require_once __DIR__ . '/db.php';

// SESSION

// start session with secure cookie settings
function start_session(): void
{
    if (session_status() !== PHP_SESSION_NONE) return;

    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => SESSION_SECURE,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// GET CURRENT USER

// returns the logged in user or null if not logged in
// cached so we only hit the db once per page
function current_user(): ?array
{
    start_session();
    if (empty($_SESSION['user_id'])) return null;

    static $cache = null;
    if ($cache === null) {
        // get user from database
        // Fetch all user fields we need throughout the app.
        // is_verified to display verified badge
        $stmt = db()->prepare(
            'SELECT id, name, surname, email, phone, date_of_birth, role, can_sell, is_verified, is_active
             FROM users WHERE id = ? AND is_active = 1'
        );
        $stmt->execute([$_SESSION['user_id']]);
        $cache = $stmt->fetch() ?: null;

        // log out if account was deactivated
        if ($cache === null) logout_user();
    }

    return $cache;
}

// redirect to login if not signed in
function require_login(): void
{
    if (current_user() === null) {
        $next = urlencode($_SERVER['REQUEST_URI'] ?? '');
        header('Location: ' . APP_URL . '/account/login.php?next=' . $next);
        exit;
    }
}

// check user has the right role, e.g. admin
function require_role(array $roles): void
{
    require_login();
    $user = current_user();
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        exit('You do not have permission to access this page.');
    }
}

// Checks whether the currently logged-in user has any admin-area access.
//   admin     — full control over everything
//   moderator — can manage listings and reviews
//   support   — can view transactions and user accounts
// Used as a helper wherever we need to show/hide admin-related UI elements.
function can_access_admin(): bool
{
    $user = current_user();
    return $user && in_array($user['role'], ['admin', 'moderator', 'support'], true);
}

//LOGIN / LOGOUT

// try to log the user in - returns true on success, false on wrong credentials
function login_user(string $email, string $password): bool
{
    $stmt = db()->prepare('SELECT id, password_hash, is_active FROM users WHERE email = ?');
    $stmt->execute([strtolower(trim($email))]);
    $row = $stmt->fetch();

    // always run password_verify even if no user found (prevents timing attacks)
    $hash = $row['password_hash'] ?? '$2y$10$invalidhashpadding000000000000000000000000000000000000000';

    if (!password_verify($password, $hash) || !$row || !$row['is_active']) {
        return false;
    }

    start_session();
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $row['id'];
    return true;
}

// clear session and log out
function logout_user(): void
{
    start_session();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }

    session_destroy();
}

// REGISTER

// create a new user account, returns the new user id
function register_user(array $data): int
{
    $hash = password_hash($data['password'], PASSWORD_DEFAULT);

    // save to db
    db()->prepare(
        'INSERT INTO users (name, surname, email, phone, password_hash, role, can_sell)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        trim($data['name']),
        trim($data['surname']),
        strtolower(trim($data['email'])),
        trim($data['phone'] ?? ''),
        $hash,
        'buyer',
        1,
    ]);

    return (int) db()->lastInsertId();
}

// check if an email is already registered
function email_exists(string $email): bool
{
    $stmt = db()->prepare('SELECT 1 FROM users WHERE email = ?');
    $stmt->execute([strtolower(trim($email))]);
    return (bool) $stmt->fetchColumn();
}
