<?php
define('XCHANGE', true);
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/csrf.php';

start_session();
require_login();

$user = current_user();

// check user is old enough to sell (must be 18+)
if (!is_adult($user['date_of_birth'] ?? null)) {
    redirect('account/settings.php?notice=age');
}

// load categories if the table exists
$categories = [];
try {
    $categories = db()->query('SELECT id, name_en FROM categories ORDER BY name_en ASC')->fetchAll();
} catch (PDOException $e) {
    // categories table doesnt exist, thats fine
}

$errors = [];
$old    = [
    'title'         => '',
    'description'   => '',
    'price'         => '',
    'location_city' => '',
    'condition'     => '',
    'category_id'   => '',
    'phone'         => $user['phone'] ?? '',
];

// handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    // grab all the posted values
    $old = [
        'title'         => trim($_POST['title']         ?? ''),
        'description'   => trim($_POST['description']   ?? ''),
        'price'         => trim($_POST['price']         ?? ''),
        'location_city' => trim($_POST['location_city'] ?? ''),
        'condition'     => trim($_POST['condition']     ?? ''),
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
    if ($old['phone'] === '') {
        $errors['phone'] = 'Phone number is required so buyers can arrange delivery with you.';
    }

    if (empty($errors)) {
        $priceCents = (int) round((float) $old['price'] * 100);
        $categoryId = ($old['category_id'] > 0) ? (int) $old['category_id'] : null;
        $imagePaths = [null, null, null];

        // process each photo slot separately
        foreach ([1, 2, 3] as $slot) {
            $file = $_FILES['image_' . $slot] ?? null;
            if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) continue;
            $path = save_listing_image($file, (int) $user['id']);
            if ($path === false) {
                $errors['images'] = 'Photo ' . $slot . ' could not be saved. Use JPG or PNG under 3 MB.';
                break;
            }
            $imagePaths[$slot - 1] = $path;
        }

        if (empty($errors)) {
            // save to db
            db()->prepare(
                'INSERT INTO listings
                     (seller_id, category_id, title, description, price_cents, `condition`,
                      location_city, status, image_path, image_path_2, image_path_3)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $user['id'],
                $categoryId,
                $old['title'],
                $old['description'],
                $priceCents,
                $old['condition'],
                $old['location_city'],
                'active',
                $imagePaths[0],
                $imagePaths[1],
                $imagePaths[2],
            ]);

            db()->prepare('UPDATE users SET phone = ? WHERE id = ?')->execute([$old['phone'], $user['id']]);
            redirect('account/my-listings.php?created=1');
        }
    }
}

$pageTitle      = 'Post a listing — ' . APP_NAME;
$pageStylesheet = 'auth.css';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<section class="auth-section">
    <div class="auth-card listing-form-card cascade-up" style="animation-delay:80ms">

        <h1 class="auth-card__heading">Post a listing</h1>
        <p class="auth-card__sub">Fill in the details — we'll show it to buyers near you.</p>

        <form method="POST" action="" enctype="multipart/form-data" novalidate>
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label" for="title">Title</label>
                <input class="form-input <?= isset($errors['title']) ? 'form-input--error' : '' ?>"
                       type="text" id="title" name="title"
                       value="<?= e($old['title']) ?>"
                       placeholder="e.g. Samsung Galaxy A14, barely used"
                       maxlength="200" required>
                <?php if (isset($errors['title'])): ?>
                <span class="form-error" role="alert"><?= e($errors['title']) ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label" for="description">Description</label>
                <textarea class="form-input <?= isset($errors['description']) ? 'form-input--error' : '' ?>"
                          id="description" name="description" rows="4"
                          placeholder="Describe the item — condition details, size, what's included, why you're selling..."
                          required><?= e($old['description']) ?></textarea>
                <?php if (isset($errors['description'])): ?>
                <span class="form-error" role="alert"><?= e($errors['description']) ?></span>
                <?php endif; ?>
                <p class="form-hint">Selling clothes or shoes? Include the size (e.g. M, UK 9, 32x30) in your description.</p>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="price">Price (R)</label>
                    <input class="form-input <?= isset($errors['price']) ? 'form-input--error' : '' ?>"
                           type="number" id="price" name="price"
                           value="<?= e($old['price']) ?>"
                           min="0" step="0.01" placeholder="0.00" required>
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
                       placeholder="e.g. Soweto, Johannesburg" required>
                <?php if (isset($errors['location_city'])): ?>
                <span class="form-error" role="alert"><?= e($errors['location_city']) ?></span>
                <?php endif; ?>
            </div>

            <?php if ($categories): ?>
            <div class="form-group">
                <label class="form-label" for="category_id">Category <span style="font-weight:var(--weight-regular);color:var(--color-text-muted)">(optional)</span></label>
                <select class="form-input" id="category_id" name="category_id">
                    <option value="0">Select a category...</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int)$cat['id'] ?>"
                            <?= (int)$old['category_id'] === (int)$cat['id'] ? 'selected' : '' ?>>
                        <?= e($cat['name_en']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="form-group">
                <label class="form-label">
                    Photos <span style="font-weight:var(--weight-regular);color:var(--color-text-muted)">(optional — JPG or PNG, max 3 MB each)</span>
                </label>
                <div class="photo-slots">
                    <div class="photo-slot">
                        <label class="photo-slot__label" for="image_1">Photo 1</label>
                        <input class="form-input" type="file" id="image_1" name="image_1" accept=".jpg,.jpeg,.png">
                    </div>
                    <div class="photo-slot">
                        <label class="photo-slot__label" for="image_2">Photo 2 <span style="color:var(--color-text-muted);font-weight:var(--weight-regular)">(optional)</span></label>
                        <input class="form-input" type="file" id="image_2" name="image_2" accept=".jpg,.jpeg,.png">
                    </div>
                    <div class="photo-slot">
                        <label class="photo-slot__label" for="image_3">Photo 3 <span style="color:var(--color-text-muted);font-weight:var(--weight-regular)">(optional)</span></label>
                        <input class="form-input" type="file" id="image_3" name="image_3" accept=".jpg,.jpeg,.png">
                    </div>
                </div>
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

            <button type="submit" class="btn btn--accent form-submit"><i class="bi bi-check-circle" aria-hidden="true"></i> Save listing</button>
        </form>

        <div class="auth-card__footer">
            <a href="<?= APP_URL ?>/account/my-listings.php">&larr; Back to my listings</a>
        </div>

    </div>
</section>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
