<?php
define('XCHANGE', true);
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/csrf.php';

start_session();

// redirect to dashboard if already logged in
if (current_user()) {
    redirect('account/dashboard.php');
}

$errors = [];
$old    = [];

// handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    // grab posted values
    $old = [
        'name'    => trim($_POST['name']    ?? ''),
        'surname' => trim($_POST['surname'] ?? ''),
        'email'   => trim($_POST['email']   ?? ''),
    ];
    $password = $_POST['password'] ?? '';

    // validate each field
    if ($old['name'] === '') {
        $errors['name'] = 'First name is required.';
    }
    if ($old['surname'] === '') {
        $errors['surname'] = 'Surname is required.';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    }
    if (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters.';
    }

    // check email isnt already taken
    if (empty($errors) && email_exists($old['email'])) {
        $errors['email'] = 'An account with this email already exists.';
    }

    if (empty($errors)) {
        try {
            // save to db
            register_user([
                'name'     => $old['name'],
                'surname'  => $old['surname'],
                'email'    => $old['email'],
                'password' => $password,
            ]);
            redirect('account/login.php?registered=1&next=' . urlencode('/browse.php'));
        } catch (PDOException $e) {
            if (APP_ENV === 'development') throw $e;
            $errors['general'] = 'Registration failed. Please try again.';
        }
    }
}

$pageTitle      = 'Create account — ' . APP_NAME;
$pageStylesheet = 'auth.css';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<section class="auth-section">
    <div class="auth-card cascade-up" style="animation-delay:80ms">

        <h1 class="auth-card__heading">Create your account</h1>
        <p class="auth-card__sub">Free to join. Buy and sell in your area.</p>

        <?php if (!empty($errors['general'])): ?>
        <div class="form-alert form-alert--error" role="alert">
            <?= e($errors['general']) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="" novalidate>
            <?= csrf_field() ?>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="name">First name</label>
                    <input class="form-input <?= isset($errors['name']) ? 'form-input--error' : '' ?>"
                           type="text" id="name" name="name"
                           value="<?= e($old['name'] ?? '') ?>"
                           autocomplete="given-name"
                           required>
                    <?php if (isset($errors['name'])): ?>
                    <span class="form-error" role="alert"><?= e($errors['name']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label class="form-label" for="surname">Surname</label>
                    <input class="form-input <?= isset($errors['surname']) ? 'form-input--error' : '' ?>"
                           type="text" id="surname" name="surname"
                           value="<?= e($old['surname'] ?? '') ?>"
                           autocomplete="family-name"
                           required>
                    <?php if (isset($errors['surname'])): ?>
                    <span class="form-error" role="alert"><?= e($errors['surname']) ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="email">Email address</label>
                <input class="form-input <?= isset($errors['email']) ? 'form-input--error' : '' ?>"
                       type="email" id="email" name="email"
                       value="<?= e($old['email'] ?? '') ?>"
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
                       autocomplete="new-password"
                       required>
                <?php if (isset($errors['password'])): ?>
                <span class="form-error" role="alert"><?= e($errors['password']) ?></span>
                <?php else: ?>
                <span class="form-error" style="color:var(--color-text-muted)">Minimum 8 characters</span>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn btn--accent form-submit">Create account</button>
        </form>

        <div class="auth-card__footer">
            Already have an account?
            <a href="<?= APP_URL ?>/account/login.php">Sign in &rarr;</a>
        </div>

    </div>
</section>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
