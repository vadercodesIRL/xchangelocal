<?php
define('XCHANGE', true);
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/csrf.php';

start_session();
if (empty($_SESSION['admin_verified'])) { redirect('admin/login.php'); }
require_role(['admin', 'moderator']);

$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
    redirect('admin/listings.php');
}

$stmt = db()->prepare(
    'SELECT l.*, u.id AS owner_id
     FROM listings l
     JOIN users u ON u.id = l.seller_id
     WHERE l.id = ?'
);
$stmt->execute([$id]);
$listing = $stmt->fetch();

if (!$listing) {
    redirect('admin/listings.php?error=not_found');
}

$categories = [];
try {
    $categories = db()->query('SELECT id, name_en FROM categories ORDER BY name_en ASC')->fetchAll();
} catch (PDOException $e) {
    // categories table may not exist — silently ignore
}

$currentImages = [
    1 => $listing['image_path']   ?? null,
    2 => $listing['image_path_2'] ?? null,
    3 => $listing['image_path_3'] ?? null,
];

$errors = [];
$old    = [
    'title'         => $listing['title'],
    'description'   => $listing['description'],
    'price'         => number_format($listing['price_cents'] / 100, 2, '.', ''),
    'location_city' => $listing['location_city'],
    'condition'     => $listing['condition'],
    'status'        => $listing['status'],
    'category_id'   => (int) ($listing['category_id'] ?? 0),
];

// Handle the edit form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $old = [
        'title'         => trim($_POST['title']         ?? ''),
        'description'   => trim($_POST['description']   ?? ''),
        'price'         => trim($_POST['price']         ?? ''),
        'location_city' => trim($_POST['location_city'] ?? ''),
        'condition'     => trim($_POST['condition']     ?? ''),
        'status'        => trim($_POST['status']        ?? ''),
        'category_id'   => (int) ($_POST['category_id'] ?? 0),
    ];

    if ($old['title'] === '') {
        $errors['title'] = 'Title is required.';
    } elseif (mb_strlen($old['title']) > 200) {
        $errors['title'] = 'Title must be 200 characters or fewer.';
    }
    if ($old['description'] === '') {
        $errors['description'] = 'Description is required.';
    }
    if ($old['price'] === '' || !is_numeric($old['price']) || (float) $old['price'] < 0) {
        $errors['price'] = 'Enter a valid price (e.g. 250.00).';
    }
    if ($old['location_city'] === '') {
        $errors['location_city'] = 'City is required.';
    }
    if (!in_array($old['condition'], ['new', 'like_new', 'good', 'fair'], true)) {
        $errors['condition'] = 'Please select a condition.';
    }
    if (!in_array($old['status'], ['active', 'sold', 'reserved', 'removed'], true)) {
        $errors['status'] = 'Please select a valid status.';
    }

    if (empty($errors)) {
        $priceCents    = (int) round((float) $old['price'] * 100);
        $finalImages   = $currentImages;
        $pendingDelete = [];

        for ($slot = 1; $slot <= 3; $slot++) {
            $fileKey = 'new_img_' . $slot;
            if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $path = save_listing_image($_FILES[$fileKey], (int) $listing['owner_id']);
            if ($path === false) {
                $errors['images'] = 'One or more photos could not be saved. Use JPG or PNG under 3 MB.';
                break;
            }
            if ($finalImages[$slot]) $pendingDelete[] = $finalImages[$slot];
            $finalImages[$slot] = $path;
        }

        if (empty($errors)) {
            $deleteRequests = $_POST['delete_img'] ?? [];
            for ($slot = 1; $slot <= 3; $slot++) {
                if (isset($deleteRequests[$slot]) && $finalImages[$slot]) {
                    $pendingDelete[] = $finalImages[$slot];
                    $finalImages[$slot] = null;
                }
            }

            $categoryId = ($old['category_id'] > 0) ? (int) $old['category_id'] : null;

            db()->prepare(
                'UPDATE listings
                 SET title = ?, description = ?, price_cents = ?,
                     `condition` = ?, location_city = ?,
                     status = ?, category_id = ?,
                     image_path = ?, image_path_2 = ?, image_path_3 = ?
                 WHERE id = ?'
            )->execute([
                $old['title'],
                $old['description'],
                $priceCents,
                $old['condition'],
                $old['location_city'],
                $old['status'],
                $categoryId,
                $finalImages[1],
                $finalImages[2],
                $finalImages[3],
                $id,
            ]);

            foreach ($pendingDelete as $p) delete_listing_image_file($p);

            redirect('admin/listings.php?updated=1');
        }
    }
}

$pageTitle      = 'Edit Listing — Admin — ' . APP_NAME;
$pageStylesheet = 'auth.css';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<section class="auth-section">
    <div class="auth-card listing-form-card cascade-up" style="animation-delay:80ms">

        <a href="<?= APP_URL ?>/admin/listings.php" class="back-link"
           style="display:block;margin-bottom:var(--space-4)">&larr; Back to listings</a>

        <h1 class="auth-card__heading">Edit listing</h1>
        <p class="auth-card__sub">Admin edit &mdash; changes apply immediately to all users.</p>

        <form method="POST" action="" enctype="multipart/form-data" novalidate>
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label" for="title">Title</label>
                <input class="form-input <?= isset($errors['title']) ? 'form-input--error' : '' ?>"
                       type="text" id="title" name="title"
                       value="<?= e($old['title']) ?>"
                       maxlength="200" required>
                <?php if (isset($errors['title'])): ?>
                <span class="form-error" role="alert"><?= e($errors['title']) ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label" for="description">Description</label>
                <textarea class="form-input <?= isset($errors['description']) ? 'form-input--error' : '' ?>"
                          id="description" name="description" rows="4"
                          required><?= e($old['description']) ?></textarea>
                <?php if (isset($errors['description'])): ?>
                <span class="form-error" role="alert"><?= e($errors['description']) ?></span>
                <?php endif; ?>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="price">Price (R)</label>
                    <input class="form-input <?= isset($errors['price']) ? 'form-input--error' : '' ?>"
                           type="number" id="price" name="price"
                           value="<?= e($old['price']) ?>"
                           min="0" step="0.01" required>
                    <?php if (isset($errors['price'])): ?>
                    <span class="form-error" role="alert"><?= e($errors['price']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label class="form-label" for="condition">Condition</label>
                    <select class="form-input <?= isset($errors['condition']) ? 'form-input--error' : '' ?>"
                            id="condition" name="condition" required>
                        <option value="">Select...</option>
                        <option value="new"       <?= $old['condition'] === 'new'       ? 'selected' : '' ?>>New</option>
                        <option value="like_new"  <?= $old['condition'] === 'like_new'  ? 'selected' : '' ?>>Like New</option>
                        <option value="good"      <?= $old['condition'] === 'good'      ? 'selected' : '' ?>>Good</option>
                        <option value="fair"      <?= $old['condition'] === 'fair'      ? 'selected' : '' ?>>Fair</option>
                    </select>
                    <?php if (isset($errors['condition'])): ?>
                    <span class="form-error" role="alert"><?= e($errors['condition']) ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="location_city">City</label>
                <input class="form-input <?= isset($errors['location_city']) ? 'form-input--error' : '' ?>"
                       type="text" id="location_city" name="location_city"
                       value="<?= e($old['location_city']) ?>" required>
                <?php if (isset($errors['location_city'])): ?>
                <span class="form-error" role="alert"><?= e($errors['location_city']) ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label" for="status">Listing Status</label>
                <select class="form-input <?= isset($errors['status']) ? 'form-input--error' : '' ?>"
                        id="status" name="status" required>
                    <option value="active"   <?= $old['status'] === 'active'   ? 'selected' : '' ?>>Active &mdash; visible to everyone</option>
                    <option value="sold"     <?= $old['status'] === 'sold'     ? 'selected' : '' ?>>Sold &mdash; hidden from browse</option>
                    <option value="reserved" <?= $old['status'] === 'reserved' ? 'selected' : '' ?>>Reserved &mdash; being arranged</option>
                    <option value="removed"  <?= $old['status'] === 'removed'  ? 'selected' : '' ?>>Removed &mdash; hidden (admin action)</option>
                </select>
                <?php if (isset($errors['status'])): ?>
                <span class="form-error" role="alert"><?= e($errors['status']) ?></span>
                <?php endif; ?>
            </div>

            <?php if ($categories): ?>
            <div class="form-group">
                <label class="form-label" for="category_id">Category <span style="font-weight:var(--weight-regular);color:var(--color-text-muted)">(optional)</span></label>
                <select class="form-input" id="category_id" name="category_id">
                    <option value="0">No category</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int)$cat['id'] ?>"
                            <?= (int)$old['category_id'] === (int)$cat['id'] ? 'selected' : '' ?>>
                        <?= e($cat['name_en']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <!-- Per-slot image management -->
            <div class="form-group">
                <p class="form-label">Photos</p>
                <?php for ($slot = 1; $slot <= 3; $slot++):
                    $imgPath = $currentImages[$slot];
                ?>
                <div style="margin-bottom:var(--space-4);padding:var(--space-4);background:var(--color-surface);border:1px solid var(--color-border);border-radius:var(--radius-md)">
                    <p style="font-size:var(--text-sm);font-weight:var(--weight-semibold);color:var(--color-text-muted);margin-bottom:var(--space-3)">
                        Photo <?= $slot ?>
                    </p>

                    <?php if ($imgPath): ?>
                    <img src="<?= e(UPLOAD_URL . $imgPath) ?>"
                         alt="Photo <?= $slot ?>"
                         style="width:120px;height:90px;object-fit:cover;border-radius:var(--radius-sm);display:block;margin-bottom:var(--space-3)">
                    <label style="display:flex;align-items:center;gap:var(--space-2);font-size:var(--text-sm);color:var(--color-text);margin-bottom:var(--space-3);cursor:pointer">
                        <input type="checkbox" name="delete_img[<?= $slot ?>]" value="1">
                        Remove this photo
                    </label>
                    <?php else: ?>
                    <p style="font-size:var(--text-sm);color:var(--color-text-light);margin-bottom:var(--space-3)">No photo uploaded</p>
                    <?php endif; ?>

                    <label class="form-label" for="new_img_<?= $slot ?>" style="font-size:var(--text-sm)">
                        <?= $imgPath ? 'Replace photo' : 'Add photo' ?> (JPG or PNG, max 3 MB)
                    </label>
                    <input class="form-input" type="file"
                           id="new_img_<?= $slot ?>" name="new_img_<?= $slot ?>"
                           accept=".jpg,.jpeg,.png">
                </div>
                <?php endfor; ?>

                <?php if (isset($errors['images'])): ?>
                <span class="form-error" role="alert"><?= e($errors['images']) ?></span>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn btn--accent form-submit"><i class="bi bi-check-circle" aria-hidden="true"></i> Save changes</button>
        </form>

        <div class="auth-card__footer">
            <a href="<?= APP_URL ?>/admin/listings.php">&larr; Cancel &mdash; back to all listings</a>
        </div>

    </div>
</section>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
