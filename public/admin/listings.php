<?php
define('XCHANGE', true);
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/csrf.php';

start_session();
if (empty($_SESSION['admin_verified'])) { redirect('admin/login.php'); }
require_role(['admin', 'moderator']);

$notice = null;

// handle post actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete_listing') {
        $lid = (int) ($_POST['listing_id'] ?? 0);
        if ($lid) {
            // remove the image files from disk before deleting the DB row
            $imgStmt = db()->prepare(
                'SELECT image_path, image_path_2, image_path_3 FROM listings WHERE id = ?'
            );
            $imgStmt->execute([$lid]);
            $imgs = $imgStmt->fetch();
            if ($imgs) {
                delete_listing_image_file($imgs['image_path']);
                delete_listing_image_file($imgs['image_path_2']);
                delete_listing_image_file($imgs['image_path_3']);
            }
            db()->prepare('DELETE FROM listings WHERE id = ?')->execute([$lid]);
            $notice = ['type' => 'success', 'msg' => 'Listing deleted.'];
        }

    } elseif ($action === 'toggle_feature') {
        // toggle is_featured on a listing
        $lid        = (int) ($_POST['listing_id']  ?? 0);
        $currentVal = (int) ($_POST['is_featured']  ?? 0);
        if ($lid) {
            $newVal = $currentVal ? 0 : 1;
            db()->prepare('UPDATE listings SET is_featured = ? WHERE id = ?')
               ->execute([$newVal, $lid]);
            $notice = ['type' => 'success', 'msg' => $newVal
                ? 'Added to Editor\'s Picks.'
                : 'Removed from Editor\'s Picks.'];
        }
    }
}

$allowedStatuses = ['', 'active', 'sold', 'reserved', 'removed'];
$filterStatus    = in_array($_GET['status'] ?? '', $allowedStatuses, true)
                   ? ($_GET['status'] ?? '')
                   : '';

$sql    = 'SELECT l.id, l.title, l.price_cents, l.status, l.is_featured, l.created_at,
                  CONCAT(u.name, " ", u.surname) AS seller_name
           FROM listings l
           JOIN users u ON u.id = l.seller_id';
$params = [];
if ($filterStatus !== '') {
    $sql    .= ' WHERE l.status = ?';
    $params[] = $filterStatus;
}
$sql .= ' ORDER BY l.is_featured DESC, l.created_at DESC';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$listings = $stmt->fetchAll();

// count how many are currently featured on the homepage
$featuredCount = (int) db()->query(
    "SELECT COUNT(*) FROM listings WHERE is_featured = 1 AND status = 'active'"
)->fetchColumn();

$pageTitle      = 'Listings — Admin — ' . APP_NAME;
$pageStylesheet = 'auth.css';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<section class="dashboard-section">
    <div class="container">

        <div class="my-listings-header">
            <div>
                <a href="<?= APP_URL ?>/admin/index.php" class="back-link">&larr; Dashboard</a>
                <h1 class="section__heading" style="margin-top:var(--space-2)">All Listings</h1>
            </div>
        </div>

        <?php if ($notice): ?>
        <div class="form-alert form-alert--<?= $notice['type'] === 'error' ? 'error' : 'success' ?>"
             role="<?= $notice['type'] === 'error' ? 'alert' : 'status' ?>">
            <?= e($notice['msg']) ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($_GET['updated'])): ?>
        <div class="form-alert form-alert--success" role="status">Listing updated successfully.</div>
        <?php endif; ?>

        <!-- Editor's picks count -->
        <div style="display:flex;align-items:center;gap:var(--space-2);margin-bottom:var(--space-5);
                    padding:var(--space-3) var(--space-4);background:var(--color-accent-light);
                    border:1px solid var(--color-accent);border-radius:var(--radius-sm);
                    font-size:var(--text-sm);color:var(--color-accent-text)">
            <i class="bi bi-star-fill" aria-hidden="true"></i>
            <strong><?= $featuredCount ?></strong>
            listing<?= $featuredCount !== 1 ? 's' : '' ?> featured on homepage as Editor's Picks
        </div>

        <!-- Status filter -->
        <form method="GET" action="" class="admin-filter-bar">
            <label class="form-label" for="status-filter" style="margin:0;white-space:nowrap">Filter:</label>
            <select class="form-input" id="status-filter" name="status"
                    style="max-width:180px" onchange="this.form.submit()">
                <option value=""         <?= $filterStatus === ''         ? 'selected' : '' ?>>All statuses</option>
                <option value="active"   <?= $filterStatus === 'active'   ? 'selected' : '' ?>>Active</option>
                <option value="sold"     <?= $filterStatus === 'sold'     ? 'selected' : '' ?>>Sold</option>
                <option value="reserved" <?= $filterStatus === 'reserved' ? 'selected' : '' ?>>Reserved</option>
                <option value="removed"  <?= $filterStatus === 'removed'  ? 'selected' : '' ?>>Removed</option>
            </select>
            <?php if ($filterStatus): ?>
            <a href="<?= APP_URL ?>/admin/listings.php" class="btn btn--sm btn--ghost-white">Clear</a>
            <?php endif; ?>
        </form>

        <?php if ($listings): ?>
        <div class="listings-table-wrap">
            <table class="listings-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Seller</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Posted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($listings as $l): ?>
                    <tr>
                        <td class="listings-table__title" data-label="Title">
                            <?php if ($l['is_featured']): ?>
                            <i class="bi bi-star-fill" aria-hidden="true"
                               style="color:var(--color-accent-text);margin-right:4px;flex-shrink:0"
                               title="Editor's Pick"></i>
                            <?php endif; ?>
                            <?= e($l['title']) ?>
                        </td>
                        <td data-label="Seller"><?= e($l['seller_name']) ?></td>
                        <td data-label="Price"><?= format_zar((int)$l['price_cents']) ?></td>
                        <td data-label="Status">
                            <span class="status-badge status-badge--<?= e($l['status']) ?>">
                                <?= e(ucfirst($l['status'])) ?>
                            </span>
                        </td>
                        <td data-label="Posted"><?= e(date('d M Y', strtotime($l['created_at']))) ?></td>
                        <td class="listings-table__actions" data-label="">
                            <!-- feature toggle -->
                            <form method="POST" action="">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action"     value="toggle_feature">
                                <input type="hidden" name="listing_id" value="<?= (int)$l['id'] ?>">
                                <input type="hidden" name="is_featured" value="<?= (int)$l['is_featured'] ?>">
                                <?php if ($l['is_featured']): ?>
                                <button type="submit" class="btn btn--sm btn--accent"
                                        title="Remove from Editor's Picks">
                                    <i class="bi bi-star-fill" aria-hidden="true"></i> Unfeature
                                </button>
                                <?php else: ?>
                                <button type="submit" class="btn btn--sm btn--ghost-white"
                                        title="Add to Editor's Picks">
                                    <i class="bi bi-star" aria-hidden="true"></i> Feature
                                </button>
                                <?php endif; ?>
                            </form>
                            <a href="<?= APP_URL ?>/admin/edit-listing.php?id=<?= (int)$l['id'] ?>"
                               class="btn btn--sm btn--ghost-white"><i class="bi bi-pencil" aria-hidden="true"></i> Edit</a>
                            <form method="POST" action="" class="delete-listing-form"
                                  data-title="<?= e($l['title']) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action"     value="delete_listing">
                                <input type="hidden" name="listing_id" value="<?= (int)$l['id'] ?>">
                                <button type="submit" class="btn btn--sm btn--danger"><i class="bi bi-trash" aria-hidden="true"></i> Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="listings-empty-state">
            <p style="font-weight:var(--weight-semibold);margin-bottom:var(--space-2)">No listings found.</p>
            <?php if ($filterStatus): ?>
            <p>Try a different status filter.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div>
</section>

<script>
document.querySelectorAll('.delete-listing-form').forEach(function (form) {
    form.addEventListener('submit', function (e) {
        var title = form.dataset.title || 'this listing';
        if (!confirm('Delete "' + title + '"?\n\nThis action cannot be undone.')) {
            e.preventDefault();
        }
    });
});
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
