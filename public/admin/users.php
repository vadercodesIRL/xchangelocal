<?php
define('XCHANGE', true);
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/csrf.php';

start_session();
if (empty($_SESSION['admin_verified'])) { redirect('admin/login.php'); }
require_role(['admin', 'moderator', 'support']);

$currentUser = current_user();
$notice      = null;

// Handle all form submissions (add, edit, delete user)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if ($currentUser['role'] !== 'admin') {
        http_response_code(403); exit('Admins only.');
    }
    $action = $_POST['action'] ?? '';

    // ---- Add new user ----
    if ($action === 'add_user') {
        $name    = trim($_POST['name']    ?? '');
        $surname = trim($_POST['surname'] ?? '');
        $email   = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        $role    = $_POST['role'] ?? 'buyer';

        $addErrors = [];
        if ($name === '')    $addErrors[] = 'First name is required.';
        if ($surname === '') $addErrors[] = 'Surname is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $addErrors[] = 'Valid email is required.';
        if (strlen($password) < 8) $addErrors[] = 'Password must be at least 8 characters.';
        if (!in_array($role, ['buyer', 'seller', 'admin', 'moderator', 'support'], true)) $addErrors[] = 'Invalid role.';

        if (empty($addErrors)) {
            $dup = db()->prepare('SELECT 1 FROM users WHERE email = ?');
            $dup->execute([$email]);
            if ($dup->fetchColumn()) {
                $addErrors[] = 'An account with that email already exists.';
            }
        }

        if (empty($addErrors)) {
            db()->prepare(
                'INSERT INTO users (name, surname, email, password_hash, role, can_sell)
                 VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([
                $name, $surname, $email,
                password_hash($password, PASSWORD_DEFAULT),
                $role,
                1,
            ]);
            $notice = ['type' => 'success', 'msg' => 'User created.'];
        } else {
            $notice = ['type' => 'error', 'msg' => implode(' ', $addErrors)];
        }

    // ---- Edit existing user ----
    } elseif ($action === 'edit_user') {
        $uid      = (int) ($_POST['user_id'] ?? 0);
        $name     = trim($_POST['name']    ?? '');
        $surname  = trim($_POST['surname'] ?? '');
        $email    = strtolower(trim($_POST['email'] ?? ''));
        $role     = $_POST['role'] ?? '';
        $password = $_POST['password'] ?? '';

        // fetch target user first so we can enforce role rules below
        $tStmt = db()->prepare('SELECT role FROM users WHERE id = ?');
        $tStmt->execute([$uid]);
        $targetUser = $tStmt->fetch();

        $editErrors = [];
        if (!$targetUser) {
            $editErrors[] = 'User not found.';
        } else {
            if ($name === '')    $editErrors[] = 'First name is required.';
            if ($surname === '') $editErrors[] = 'Surname is required.';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $editErrors[] = 'Valid email is required.';
            if ($password !== '' && strlen($password) < 8) $editErrors[] = 'New password must be at least 8 characters.';

            // existing admins stay admin; non-admins can be promoted to any role
            if ($targetUser['role'] === 'admin') {
                $role = 'admin';
            } elseif (!in_array($role, ['buyer', 'seller', 'admin', 'moderator', 'support'], true)) {
                $editErrors[] = 'Invalid role.';
            }
        }

        if (empty($editErrors)) {
            // Check email not taken by a different user
            $dup = db()->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
            $dup->execute([$email, $uid]);
            if ($dup->fetchColumn()) {
                $editErrors[] = 'That email is already used by another account.';
            }
        }

        if (empty($editErrors)) {
            if ($password !== '') {
                db()->prepare(
                    'UPDATE users SET name=?, surname=?, email=?, role=?, password_hash=? WHERE id=?'
                )->execute([
                    $name, $surname, $email, $role,
                    password_hash($password, PASSWORD_DEFAULT),
                    $uid,
                ]);
            } else {
                db()->prepare(
                    'UPDATE users SET name=?, surname=?, email=?, role=? WHERE id=?'
                )->execute([$name, $surname, $email, $role, $uid]);
            }
            $notice = ['type' => 'success', 'msg' => 'User updated.'];
        } else {
            $notice = ['type' => 'error', 'msg' => implode(' ', $editErrors)];
        }

    // ---- Toggle verified badge ----
    } elseif ($action === 'toggle_verify') {
        $uid = (int) ($_POST['user_id'] ?? 0);
        $tStmt = db()->prepare('SELECT is_verified FROM users WHERE id = ?');
        $tStmt->execute([$uid]);
        $tRow = $tStmt->fetch();
        if ($tRow) {
            $newVal = $tRow['is_verified'] ? 0 : 1;
            db()->prepare('UPDATE users SET is_verified = ? WHERE id = ?')->execute([$newVal, $uid]);
            $notice = ['type' => 'success', 'msg' => $newVal ? 'Seller marked as verified.' : 'Verified status removed.'];
        }

    // ---- Delete user ----
    } elseif ($action === 'delete_user') {
        $uid = (int) ($_POST['user_id'] ?? 0);

        if ($uid === (int) $currentUser['id']) {
            $notice = ['type' => 'error', 'msg' => 'You cannot delete your own account.'];
        } else {
            $tStmt = db()->prepare('SELECT role FROM users WHERE id = ?');
            $tStmt->execute([$uid]);
            $targetUser = $tStmt->fetch();

            if (!$targetUser) {
                $notice = ['type' => 'error', 'msg' => 'User not found.'];
            } elseif ($targetUser['role'] === 'admin') {
                $notice = ['type' => 'error', 'msg' => 'Admin accounts cannot be deleted.'];
            } else {
                db()->prepare('DELETE FROM users WHERE id = ?')->execute([$uid]);
                $notice = ['type' => 'success', 'msg' => 'User deleted.'];
            }
        }
    }
}

// Get all users to show in the table
$users = db()->query(
    'SELECT id, name, surname, email, role, is_verified, created_at FROM users ORDER BY created_at DESC'
)->fetchAll();

$pageTitle      = 'Users — Admin — ' . APP_NAME;
$pageStylesheet = 'auth.css';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<section class="dashboard-section">
    <div class="container">

        <div class="my-listings-header">
            <div>
                <a href="<?= APP_URL ?>/admin/index.php" class="back-link">&larr; Dashboard</a>
                <h1 class="section__heading" style="margin-top:var(--space-2)">Users</h1>
            </div>
            <?php if ($currentUser['role'] === 'admin'): ?>
            <button class="btn btn--primary" id="toggle-add-form" type="button">+ Add user</button>
            <?php endif; ?>
        </div>

        <?php if ($notice): ?>
        <div class="form-alert form-alert--<?= $notice['type'] === 'error' ? 'error' : 'success' ?>"
             role="<?= $notice['type'] === 'error' ? 'alert' : 'status' ?>">
            <?= e($notice['msg']) ?>
        </div>
        <?php endif; ?>

        <!-- Add user form (hidden until toggled) -->
        <div id="add-user-card" class="add-user-card" hidden>
            <h2 class="auth-card__heading" style="font-size:var(--text-xl);margin-bottom:var(--space-6)">
                New user
            </h2>
            <form method="POST" action="" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_user">

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="add-name">First name</label>
                        <input class="form-input" type="text" id="add-name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add-surname">Surname</label>
                        <input class="form-input" type="text" id="add-surname" name="surname" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="add-email">Email</label>
                        <input class="form-input" type="email" id="add-email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add-role">Role</label>
                        <select class="form-input" id="add-role" name="role">
                            <option value="buyer">Buyer</option>
                            <option value="seller">Seller</option>
                            <option value="moderator">Moderator</option>
                            <option value="support">Support</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="add-password">Password</label>
                    <input class="form-input" type="password" id="add-password" name="password"
                           autocomplete="new-password" required>
                    <span class="form-error" style="color:var(--color-text-muted)">Minimum 8 characters</span>
                </div>

                <div style="display:flex;gap:var(--space-3);margin-top:var(--space-2)">
                    <button type="submit" class="btn btn--accent">Create user</button>
                    <button type="button" class="btn btn--ghost-white" id="cancel-add-form">Cancel</button>
                </div>
            </form>
        </div>

        <!-- Search filter -->
        <div style="margin-bottom:var(--space-4)">
            <input type="search"
                   id="user-search"
                   class="form-input"
                   placeholder="Search by name or email..."
                   style="max-width:360px"
                   aria-label="Search users">
        </div>

        <!-- Users table -->
        <div class="listings-table-wrap">
            <table class="listings-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr class="user-row">
                        <td class="listings-table__title" data-label="Name">
                            <?= e($u['name'] . ' ' . $u['surname']) ?>
                            <?php if ((int)$u['id'] === (int)$currentUser['id']): ?>
                            <span class="status-badge status-badge--active" style="margin-left:4px">You</span>
                            <?php endif; ?>
                            <?php if ($u['is_verified']): ?>
                            <span class="verified-badge" style="display:inline-flex;align-items:center;gap:3px;color:var(--color-success-text);font-size:var(--text-xs);font-weight:var(--weight-semibold);margin-left:4px">
                                <i class="bi bi-patch-check-fill" aria-hidden="true"></i> Verified
                            </span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Email"><?= e($u['email']) ?></td>
                        <td data-label="Role">
                            <span class="status-badge role-badge--<?= e($u['role']) ?>">
                                <?= e(ucfirst($u['role'])) ?>
                            </span>
                        </td>
                        <td data-label="Joined"><?= e(date('d M Y', strtotime($u['created_at']))) ?></td>
                        <td class="listings-table__actions" data-label="">
                            <button type="button"
                                    class="btn btn--sm btn--ghost-white"
                                    onclick="toggleEditRow(<?= (int)$u['id'] ?>)">
                                <i class="bi bi-pencil" aria-hidden="true"></i> Edit
                            </button>
                            <?php if ($currentUser['role'] === 'admin'): ?>
                            <form method="POST" action="" style="display:inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action"  value="toggle_verify">
                                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                <button type="submit" class="btn btn--sm <?= $u['is_verified'] ? 'btn--primary' : 'btn--ghost-white' ?>"
                                        title="<?= $u['is_verified'] ? 'Remove verified status' : 'Mark as verified seller' ?>">
                                    <i class="bi bi-patch-check<?= $u['is_verified'] ? '-fill' : '' ?>" aria-hidden="true"></i>
                                    <?= $u['is_verified'] ? 'Verified' : 'Verify' ?>
                                </button>
                            </form>
                            <?php if ($u['role'] !== 'admin' && (int)$u['id'] !== (int)$currentUser['id']): ?>
                            <form method="POST" action="" class="delete-form"
                                  data-title="<?= e($u['name'] . ' ' . $u['surname']) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action"  value="delete_user">
                                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                <button type="submit" class="btn btn--sm btn--danger"><i class="bi bi-trash" aria-hidden="true"></i> Delete</button>
                            </form>
                            <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <!-- Inline edit row -->
                    <tr id="edit-row-<?= (int)$u['id'] ?>" hidden>
                        <td colspan="5" class="edit-row-cell" data-label="">
                            <form method="POST" action="" novalidate class="edit-row-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action"  value="edit_user">
                                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">

                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label">First name</label>
                                        <input class="form-input" type="text" name="name"
                                               value="<?= e($u['name']) ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Surname</label>
                                        <input class="form-input" type="text" name="surname"
                                               value="<?= e($u['surname']) ?>" required>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label">Email</label>
                                        <input class="form-input" type="email" name="email"
                                               value="<?= e($u['email']) ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Role</label>
                                        <?php if ($currentUser['role'] !== 'admin' || $u['role'] === 'admin'): ?>
                                        <div class="form-input" style="color:var(--color-text-muted);cursor:default;display:flex;align-items:center;gap:var(--space-2)">
                                            <?= e(ucfirst($u['role'])) ?>
                                            <span style="font-size:var(--text-xs)">(view only)</span>
                                        </div>
                                        <input type="hidden" name="role" value="<?= e($u['role']) ?>">
                                        <?php else: ?>
                                        <select class="form-input" name="role">
                                            <option value="buyer"     <?= $u['role'] === 'buyer'     ? 'selected' : '' ?>>Buyer</option>
                                            <option value="seller"    <?= $u['role'] === 'seller'    ? 'selected' : '' ?>>Seller</option>
                                            <option value="moderator" <?= $u['role'] === 'moderator' ? 'selected' : '' ?>>Moderator</option>
                                            <option value="support"   <?= $u['role'] === 'support'   ? 'selected' : '' ?>>Support</option>
                                        </select>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">New password <span style="font-weight:400;color:var(--color-text-muted)">(leave blank to keep current)</span></label>
                                    <input class="form-input" type="password" name="password"
                                           autocomplete="new-password" placeholder="Leave blank to keep current">
                                </div>

                                <div style="display:flex;gap:var(--space-3)">
                                    <button type="submit" class="btn btn--sm btn--accent">Save</button>
                                    <button type="button" class="btn btn--sm btn--ghost-white"
                                            onclick="toggleEditRow(<?= (int)$u['id'] ?>)">Cancel</button>
                                </div>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>
</section>

<script>
document.getElementById('toggle-add-form').addEventListener('click', function () {
    var card = document.getElementById('add-user-card');
    card.hidden = !card.hidden;
    if (!card.hidden) card.querySelector('input[name="name"]').focus();
});

document.getElementById('cancel-add-form').addEventListener('click', function () {
    document.getElementById('add-user-card').hidden = true;
});

function toggleEditRow(userId) {
    var row = document.getElementById('edit-row-' + userId);
    row.hidden = !row.hidden;
    if (!row.hidden) row.querySelector('input[name="name"]').focus();
}

document.querySelectorAll('.delete-form').forEach(function (form) {
    form.addEventListener('submit', function (e) {
        var name = form.dataset.title || 'this user';
        if (!confirm('Delete user "' + name + '"?\n\nThis will also delete all their listings. Cannot be undone.')) {
            e.preventDefault();
        }
    });
});

// Live search — filters visible user rows by name or email
document.getElementById('user-search').addEventListener('input', function () {
    var q = this.value.trim().toLowerCase();
    document.querySelectorAll('tbody tr.user-row').forEach(function (row) {
        var name  = (row.querySelector('td:first-child')?.textContent  || '').toLowerCase();
        var email = (row.querySelector('td:nth-child(2)')?.textContent || '').toLowerCase();
        var match = !q || name.includes(q) || email.includes(q);
        row.hidden = !match;
        var editRow = row.nextElementSibling;
        if (editRow && editRow.id && editRow.id.startsWith('edit-row-') && !match) {
            editRow.hidden = true;
        }
    });
});
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
