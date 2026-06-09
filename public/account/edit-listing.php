<?php
define('XCHANGE', true);
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/csrf.php';

start_session();
require_login();

$user = current_user();
$id   = (int) ($_GET['id'] ?? 0);

$categories = [];
try {
    $categories = db()->query('SELECT id, name_en FROM categories ORDER BY name_en ASC')->fetchAll();
} catch (PDOException $e) {
    // categories table may not exist — silently ignore
}

if (!$id) {
    redirect('account/my-listings.php');
}

$stmt = db()->prepare('SELECT * FROM listings WHERE id = ?');
$stmt->execute([$id]);
$listing = $stmt->fetch();

if (!$listing || (int) $listing['seller_id'] !== (int) $user['id']) {
    redirect('account/my-listings.php?error=not_found');
}

// Current images keyed 1–3 for convenience
$currentImages = [
    1 => $listing['image_path']   ?? null,
    2 => $listing['image_path_2'] ?? null,
    3 => $listing['image_path_3'] ?? null,
];

$errors = [];
$old = [
    'title'         => $listing['title'],
    'description'   => $listing['description'],
    'price'         => number_format($listing['price_cents'] / 100, 2, '.', ''),
    'location_city' => $listing['location_city'],
    'condition'     => $listing['condition'],
    'status'        => $listing['status'],
    'category_id'   => (int) ($listing['category_id'] ?? 0),
    'phone'         => $user['phone'] ?? '',
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
        'phone'         => trim($_POST['phone']         ?? ''),
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
    if (!in_array($old['status'], ['active', 'sold', 'reserved'], true)) {
        $errors['status'] = 'Please select a valid status.';
    }
    if ($old['phone'] === '') {
        $errors['phone'] = 'Phone number is required so buyers can arrange delivery with you.';
    }

    if (empty($errors)) {
        $priceCents    = (int) round((float) $old['price'] * 100);
        $finalImages   = $currentImages; // working copy
        $pendingDelete = [];             // paths to unlink after successful DB write

        // Handle image uploads for each slot (1, 2, 3)
        for ($slot = 1; $slot <= 3; $slot++) {
            $fileKey = 'new_img_' . $slot;
            if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $path = save_listing_image($_FILES[$fileKey], (int) $user['id']);
            if ($path === false) {
                $errors['images'] = 'One or more photos could not be saved. Use JPG or PNG under 3 MB.';
                break;
            }
            if ($finalImages[$slot]) $pendingDelete[] = $finalImages[$slot]; // replaced
            $finalImages[$slot] = $path;
        }

        // Then handle any "remove this photo" checkboxes
        if (empty($errors)) {
            $deleteRequests = $_POST['delete_img'] ?? [];
            for ($slot = 1; $slot <= 3; $slot++) {
                if (isset($deleteRequests[$slot]) && $finalImages[$slot]) {
                    $pendingDelete[] = $finalImages[$slot];
                    $finalImages[$slot] = null;
                }
            }

            $categoryId = ($old['category_id'] > 0) ? (int) $old['category_id'] : null;

            $stmt = db()->prepare(
                'UPDATE listings
                 SET title = ?, description = ?, price_cents = ?,
                     `condition` = ?, location_city = ?,
                     status = ?, category_id = ?,
                     image_path = ?, image_path_2 = ?, image_path_3 = ?
                 WHERE id = ? AND seller_id = ?'
            );
            $stmt->execute([
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
                $user['id'],
            ]);

            foreach ($pendingDelete as $p) delete_listing_image_file($p);

            db()->prepare('UPDATE users SET phone = ? WHERE id = ?')->execute([$old['phone'], $user['id']]);
            redirect('account/my-listings.php?updated=1');
        }
    }
}

$pageTitle      = 'Edit listing — ' . APP_NAME;
$pageStylesheet = 'auth.css';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<section class="auth-section">
    <div class="auth-card listing-form-card cascade-up" style="animation-delay:80ms">

        <h1 class="auth-card__heading">Edit listing</h1>
        <p class="auth-card__sub">Update your listing details below.</p>

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
                       value="<?= e($old['location_city']) ?>"
                       required>
                <?php if (isset($errors['location_city'])): ?>
                <span class="form-error" role="alert"><?= e($errors['location_city']) ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label" for="status">Listing Status</label>
                <select class="form-input <?= isset($errors['status']) ? 'form-input--error' : '' ?>"
                        id="status" name="status" required>
                    <option value="active"   <?= $old['status'] === 'active'   ? 'selected' : '' ?>>Active — visible to everyone</option>
                    <option value="sold"     <?= $old['status'] === 'sold'     ? 'selected' : '' ?>>Sold — hidden from browse</option>
                    <option value="reserved" <?= $old['status'] === 'reserved' ? 'selected' : '' ?>>Reserved — being arranged</option>
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

            <div class="form-group">
                <label class="form-label" for="phone">Contact phone number</label>
                <input class="form-input <?= isset($errors['phone']) ? 'form-input--error' : '' ?>"
                       type="tel" id="phone" name="phone"
                       value="<?= e($old['phone']) ?>"
                       placeholder="e.g. +27 82 123 4567" required>
                <?php if (isset($errors['phone'])): ?>
                <span class="form-error" role="alert"><?= e($errors['phone']) ?></span>
                <?php endif; ?>
                <p class="form-hint">Your phone number will be shared with buyers after purchase to arrange delivery.</p>
            </div>

            <button type="submit" class="btn btn--accent form-submit"><i class="bi bi-check-circle" aria-hidden="true"></i> Save changes</button>
        </form>

        <div class="auth-card__footer">
            <a href="<?= APP_URL ?>/account/my-listings.php">&larr; Cancel — back to my listings</a>
        </div>

    </div>
</section>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
