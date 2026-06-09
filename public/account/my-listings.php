<?php
define('XCHANGE', true);
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/csrf.php';

start_session();
require_login();

$user = current_user();

$notice = '';

// Handle delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    csrf_check();

    $deleteId = (int) $_POST['delete_id'];

    $stmt = db()->prepare(
        'SELECT seller_id, image_path, image_path_2, image_path_3 FROM listings WHERE id = ?'
    );
    $stmt->execute([$deleteId]);
    $row = $stmt->fetch();

    if (!$row || (int) $row['seller_id'] !== (int) $user['id']) {
        $notice = ['type' => 'error', 'msg' => 'Listing not found or you do not own it.'];
    } else {
        db()->prepare('DELETE FROM listings WHERE id = ? AND seller_id = ?')
            ->execute([$deleteId, $user['id']]);
        delete_listing_image_file($row['image_path']   ?? null);
        delete_listing_image_file($row['image_path_2'] ?? null);
        delete_listing_image_file($row['image_path_3'] ?? null);
        $notice = ['type' => 'success', 'msg' => 'Listing deleted.'];
    }
}

// Show a success/error message after being redirected back here
if (!$notice) {
    if (isset($_GET['created'])) {
        $notice = ['type' => 'success', 'msg' => 'Listing posted successfully.'];
    } elseif (isset($_GET['updated'])) {
        $notice = ['type' => 'success', 'msg' => 'Listing updated.'];
    } elseif (isset($_GET['error'])) {
        $notice = ['type' => 'error', 'msg' => 'Listing not found or you do not have permission.'];
    }
}

$stmt = db()->prepare(
    'SELECT l.id, l.title, l.price_cents, l.`condition`, l.status, l.created_at
     FROM listings l
     WHERE l.seller_id = ?
     ORDER BY l.created_at DESC'
);
$stmt->execute([$user['id']]);
$listings = $stmt->fetchAll();

$pageTitle      = 'My listings — ' . APP_NAME;
$pageStylesheet = 'auth.css';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<section class="dashboard-section">
    <div class="container">

        <div class="my-listings-header">
            <div>
                <a href="<?= APP_URL ?>/account/dashboard.php" class="back-link">&larr; Dashboard</a>
                <h1 class="section__heading" style="margin-top:var(--space-2)">My listings</h1>
            </div>
            <a href="<?= APP_URL ?>/account/new-listing.php" class="btn btn--primary">+ Post a listing</a>
        </div>

        <?php if ($notice): ?>
        <div class="form-alert form-alert--<?= $notice['type'] === 'error' ? 'error' : 'success' ?>" role="<?= $notice['type'] === 'error' ? 'alert' : 'status' ?>">
            <?= e($notice['msg']) ?>
        </div>
        <?php endif; ?>

        <?php if (empty($listings)): ?>
        <div class="listings-empty-state">
            <p>You haven't posted any listings yet.</p>
            <a href="<?= APP_URL ?>/account/new-listing.php" class="btn btn--accent" style="margin-top:var(--space-4)">Post your first listing</a>
        </div>

        <?php else: ?>
        <div class="listings-table-wrap">
            <table class="listings-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Price</th>
                        <th>Condition</th>
                        <th>Status</th>
                        <th>Posted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($listings as $l): ?>
                    <tr>
                        <td class="listings-table__title" data-label="Title"><?= e($l['title']) ?></td>
                        <td data-label="Price"><?= e(format_zar((int) $l['price_cents'])) ?></td>
                        <td data-label="Condition"><?= e(ucfirst($l['condition'])) ?></td>
                        <td data-label="Status">
                            <span class="status-badge status-badge--<?= e($l['status']) ?>">
                                <?= e(ucfirst($l['status'])) ?>
                            </span>
                        </td>
                        <td data-label="Posted"><?= e(date('d M Y', strtotime($l['created_at']))) ?></td>
                        <td class="listings-table__actions" data-label="">
                            <a href="<?= APP_URL ?>/account/edit-listing.php?id=<?= (int) $l['id'] ?>"
                               class="btn btn--sm btn--ghost-white">Edit</a>
                            <form method="POST" action=""
                                  class="delete-form"
                                  data-title="<?= e($l['title']) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="delete_id" value="<?= (int) $l['id'] ?>">
                                <button type="submit" class="btn btn--sm btn--danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

    </div>
</section>

<script>
document.querySelectorAll('.delete-form').forEach(function (form) {
    form.addEventListener('submit', function (e) {
        var title = form.dataset.title || 'this listing';
        if (!confirm('Delete "' + title + '"?\n\nThis cannot be undone.')) {
            e.preventDefault();
        }
    });
});
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
