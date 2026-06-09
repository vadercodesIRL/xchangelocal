<?php
define('XCHANGE', true);
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/csrf.php';

start_session();

// Logout action — clears the admin gate session flag only
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    unset($_SESSION['admin_verified']);
    redirect('admin/login.php');
}

// Already verified — skip straight to dashboard
if (!empty($_SESSION['admin_verified'])) {
    redirect('admin/index.php');
}

$errors   = [];
$oldEmail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $oldEmail = trim($_POST['email']    ?? '');
    $password = $_POST['password'] ?? '';

    if ($oldEmail === '') $errors['email']    = 'Email address is required.';
    if ($password === '') $errors['password'] = 'Password is required.';

    if (empty($errors)) {
        // Fetch user directly so we can check role before granting access
        $stmt = db()->prepare(
            'SELECT id, password_hash, role, is_active FROM users WHERE email = ?'
        );
        $stmt->execute([strtolower($oldEmail)]);
        $row = $stmt->fetch();

        // Always run password_verify — prevents timing attacks
        $hash = $row['password_hash'] ?? '$2y$10$invalidhashpaddinginvalidhashpaddinginvalidhashpadding000';
        $ok   = password_verify($password, $hash)
             && $row
             && $row['is_active']
             && in_array($row['role'], ['admin', 'moderator', 'support'], true);

        if ($ok) {
            session_regenerate_id(true);
            $_SESSION['user_id']        = (int) $row['id'];
            $_SESSION['admin_verified'] = true;
            redirect('admin/index.php');
        } else {
            $errors['general'] = 'Invalid admin credentials.';
        }
    }
}

$pageTitle      = 'Admin Portal — ' . APP_NAME;
$pageStylesheet = 'auth.css';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<section class="auth-section">
    <div class="auth-card cascade-up" style="animation-delay:80ms">

        <div class="admin-portal-badge">
            <i class="bi bi-shield-lock-fill" aria-hidden="true"></i>
            Admin Portal
        </div>

        <h1 class="auth-card__heading" style="margin-top:var(--space-3)">Admin sign in</h1>
        <p class="auth-card__sub">Restricted area. Authorised personnel only.</p>

        <?php if (!empty($errors['general'])): ?>
        <div class="form-alert form-alert--error" role="alert">
            <?= e($errors['general']) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="" novalidate>
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label" for="email">Email address</label>
                <input class="form-input <?= isset($errors['email']) ? 'form-input--error' : '' ?>"
                       type="email" id="email" name="email"
                       value="<?= e($oldEmail) ?>"
                       autocomplete="email"
                       required>
                <?php if (isset($errors['email'])): ?>
                <span class="form-error" role="alert"><?= e($errors['email']) ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input class="form-input <?= isset($errors['password']) ? 'form-input--error' : '' ?>"
                       type="password" id="password" name="password"
                       autocomplete="current-password"
                       required>
                <?php if (isset($errors['password'])): ?>
                <span class="form-error" role="alert"><?= e($errors['password']) ?></span>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn btn--accent form-submit">Enter admin area</button>
        </form>

        <div class="auth-card__footer">
            <a href="<?= APP_URL ?>/">&larr; Back to XchangeLocal</a>
        </div>

    </div>
</section>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
