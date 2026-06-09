<?php
define('XCHANGE', true);
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/csrf.php';

start_session();

// already logged in, send to dashboard
if (current_user()) {
    redirect('account/dashboard.php');
}

$errors   = [];
$oldEmail = '';

// handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $oldEmail = trim($_POST['email']    ?? '');
    $password = $_POST['password'] ?? '';

    // validate fields
    if ($oldEmail === '') {
        $errors['email'] = 'Email address is required.';
    }
    if ($password === '') {
        $errors['password'] = 'Password is required.';
    }

    if (empty($errors)) {
        if (login_user($oldEmail, $password)) {
            // honor ?next= but only for same-origin paths (prevent open redirect)
            $next = $_POST['next'] ?? $_GET['next'] ?? '';
            if ($next && str_starts_with(rawurldecode($next), '/')) {
                header('Location: ' . APP_URL . rawurldecode($next));
                exit;
            }
            redirect('account/dashboard.php');
        } else {
            $errors['general'] = 'Incorrect email or password.';
        }
    }
}

$pageTitle      = 'Sign in — ' . APP_NAME;
$pageStylesheet = 'auth.css';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<section class="auth-section">
  <div class="auth-card cascade-up" style="animation-delay:80ms">

        <h1 class="auth-card__heading">Sign in</h1>
        <p class="auth-card__sub">Welcome back to XchangeLocal.</p>

        <?php if (isset($_GET['registered'])): ?>
        <div class="form-alert form-alert--success" role="status">
            Account created — you can sign in now.
        </div>
        <?php endif; ?>

        <?php if (!empty($errors['general'])): ?>
        <div class="form-alert form-alert--error" role="alert">
            <?= e($errors['general']) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="" novalidate>
            <?= csrf_field() ?>
            <?php if (!empty($_GET['next'])): ?>
            <input type="hidden" name="next" value="<?= e($_GET['next']) ?>">
            <?php endif; ?>

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

            <details class="forgot-pw">
                <summary class="forgot-pw__trigger">Forgot your password?</summary>
                <div class="forgot-pw__body">
                    Password resets are handled manually by our support team — there is no
                    automated reset link at this time. Email us at
                    <a href="mailto:xchangelocalbusiness@outlook.com">xchangelocalbusiness@outlook.com</a>
                    with the subject line <strong>Password Reset Request</strong> and we will
                    get you sorted within 1&ndash;2 business days.
                </div>
            </details>

            <button type="submit" class="btn btn--accent form-submit">Sign in</button>
        </form>

        <div class="auth-card__footer">
            Don&rsquo;t have an account?
            <a href="<?= APP_URL ?>/account/register.php">Create one &rarr;</a>
        </div>

    </div>
</section>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
