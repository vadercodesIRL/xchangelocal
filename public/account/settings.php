<?php
define('XCHANGE', true);
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/csrf.php';

start_session();
require_login();

$user   = current_user();
$notice = null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    // ---- Update name ----
    if ($action === 'update_name') {
        $name    = trim($_POST['name']    ?? '');
        $surname = trim($_POST['surname'] ?? '');

        if ($name === '' || $surname === '') {
            $notice = ['type' => 'error', 'msg' => 'First name and surname are both required.'];
        } else {
            db()->prepare('UPDATE users SET name = ?, surname = ? WHERE id = ?')
               ->execute([$name, $surname, $user['id']]);
            $notice = ['type' => 'success', 'msg' => 'Name updated successfully.'];
        }
    }

    // ---- Change password ----
    if ($action === 'update_password') {
        $current = $_POST['current_password']  ?? '';
        $new     = $_POST['new_password']      ?? '';
        $confirm = $_POST['confirm_password']  ?? '';

        // Fetch the stored hash directly — current_user() doesn't return it
        $row = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
        $row->execute([$user['id']]);
        $hash = $row->fetchColumn();

        if (!password_verify($current, $hash)) {
            $notice = ['type' => 'error', 'msg' => 'Current password is incorrect.'];
        } elseif (strlen($new) < 8) {
            $notice = ['type' => 'error', 'msg' => 'New password must be at least 8 characters.'];
        } elseif ($new !== $confirm) {
            $notice = ['type' => 'error', 'msg' => 'New passwords do not match.'];
        } else {
            db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
               ->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
            $notice = ['type' => 'success', 'msg' => 'Password changed successfully.'];
        }
    }

    // ---- Update phone ----
    if ($action === 'update_phone') {
        $phone = trim($_POST['phone'] ?? '');
        db()->prepare('UPDATE users SET phone = ? WHERE id = ?')->execute([$phone, $user['id']]);
        $notice = ['type' => 'success', 'msg' => 'Phone number updated.'];
    }

    // ---- Update date of birth ----
    if ($action === 'update_dob') {
        $dob = trim($_POST['date_of_birth'] ?? '');

        if ($dob === '') {
            $notice = ['type' => 'error', 'msg' => 'Please enter your date of birth.'];
        } else {
            $date = DateTime::createFromFormat('Y-m-d', $dob);
            if (!$date || $date->format('Y-m-d') !== $dob) {
                $notice = ['type' => 'error', 'msg' => 'Invalid date. Use the date picker.'];
            } else {
                db()->prepare('UPDATE users SET date_of_birth = ? WHERE id = ?')
                   ->execute([$dob, $user['id']]);
                $notice = ['type' => 'success', 'msg' => 'Date of birth saved.'];
            }
        }
    }

    // Reload user data after any successful update
    if ($notice && $notice['type'] === 'success') {
        $stmt = db()->prepare(
            'SELECT id, name, surname, email, phone, date_of_birth, role, can_sell, is_active
             FROM users WHERE id = ?'
        );
        $stmt->execute([$user['id']]);
        $user = $stmt->fetch();
    }
}

$pageTitle      = 'Settings — ' . APP_NAME;
$pageStylesheet = 'auth.css';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<section class="dashboard-section">
    <div class="container" style="max-width:560px">

        <div class="my-listings-header">
            <div>
                <a href="<?= APP_URL ?>/account/dashboard.php" class="back-link">&larr; Dashboard</a>
                <h1 class="section__heading" style="margin-top:var(--space-2)">Account settings</h1>
            </div>
        </div>

        <?php if ($notice): ?>
        <div class="form-alert form-alert--<?= $notice['type'] === 'error' ? 'error' : 'success' ?>"
             role="<?= $notice['type'] === 'error' ? 'alert' : 'status' ?>">
            <?= e($notice['msg']) ?>
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['notice']) && $_GET['notice'] === 'age'): ?>
        <div class="form-alert form-alert--error" role="alert">
            You must be 18 or older to post listings. Please verify your age below.
        </div>
        <?php endif; ?>

        <!-- ── Change name ────────────────────────────────── -->
        <div class="auth-card" style="margin-bottom:var(--space-6)">
            <h2 class="auth-card__heading" style="font-size:var(--text-xl)">Update name</h2>

            <form method="POST" action="" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_name">

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="name">First name</label>
                        <input class="form-input" type="text" id="name" name="name"
                               value="<?= e($user['name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="surname">Surname</label>
                        <input class="form-input" type="text" id="surname" name="surname"
                               value="<?= e($user['surname']) ?>" required>
                    </div>
                </div>

                <button type="submit" class="btn btn--primary">
                    <i class="bi bi-check-circle" aria-hidden="true"></i> Save name
                </button>
            </form>
        </div>

        <!-- ── Change password ───────────────────────────── -->
        <div class="auth-card" style="margin-bottom:var(--space-6)">
            <h2 class="auth-card__heading" style="font-size:var(--text-xl)">Change password</h2>

            <form method="POST" action="" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_password">

                <div class="form-group">
                    <label class="form-label" for="current_password">Current password</label>
                    <input class="form-input" type="password" id="current_password"
                           name="current_password" autocomplete="current-password" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="new_password">New password</label>
                    <input class="form-input" type="password" id="new_password"
                           name="new_password" autocomplete="new-password" required>
                    <span class="form-error" style="color:var(--color-text-muted)">Minimum 8 characters</span>
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirm_password">Confirm new password</label>
                    <input class="form-input" type="password" id="confirm_password"
                           name="confirm_password" autocomplete="new-password" required>
                </div>

                <button type="submit" class="btn btn--primary">
                    <i class="bi bi-check-circle" aria-hidden="true"></i> Update password
                </button>
            </form>
        </div>

        <!-- ── Phone number ──────────────────────────────── -->
        <div class="auth-card" style="margin-bottom:var(--space-6)">
            <h2 class="auth-card__heading" style="font-size:var(--text-xl)">Phone number</h2>
            <p class="auth-card__sub">Shared with buyers after a purchase so they can arrange delivery with you.</p>

            <form method="POST" action="" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_phone">

                <div class="form-group">
                    <label class="form-label" for="phone">Phone number</label>
                    <input class="form-input" type="tel" id="phone" name="phone"
                           value="<?= e($user['phone'] ?? '') ?>"
                           placeholder="e.g. +27 82 123 4567">
                </div>

                <button type="submit" class="btn btn--primary">
                    <i class="bi bi-check-circle" aria-hidden="true"></i> Save phone number
                </button>
            </form>
        </div>

        <!-- ── Date of birth ─────────────────────────────── -->
        <div class="auth-card">
            <h2 class="auth-card__heading" style="font-size:var(--text-xl)">Date of birth</h2>
            <p class="auth-card__sub">
                You must be 18 or older to post listings.
                <?php if (is_adult($user['date_of_birth'] ?? null)): ?>
                <span style="color:var(--color-success-text);font-weight:var(--weight-semibold)">
                    <i class="bi bi-check-circle-fill" aria-hidden="true"></i> Age verified
                </span>
                <?php elseif ($user['date_of_birth']): ?>
                <span style="color:var(--color-danger-text);font-weight:var(--weight-semibold)">
                    <i class="bi bi-x-circle-fill" aria-hidden="true"></i> You must be 18+ to sell
                </span>
                <?php else: ?>
                <span style="color:var(--color-text-muted)">Not yet verified</span>
                <?php endif; ?>
            </p>

            <form method="POST" action="" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_dob">

                <div class="form-group">
                    <label class="form-label" for="date_of_birth">Date of birth</label>
                    <input class="form-input" type="date" id="date_of_birth" name="date_of_birth"
                           value="<?= e($user['date_of_birth'] ?? '') ?>"
                           max="<?= date('Y-m-d') ?>"
                           required>
                </div>

                <button type="submit" class="btn btn--primary">
                    <i class="bi bi-check-circle" aria-hidden="true"></i> Save date of birth
                </button>
            </form>
        </div>

    </div>
</section>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
