<?php
define('XCHANGE', true);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

start_session();

$sellerId = (int) ($_GET['id'] ?? 0);
if (!$sellerId) redirect('');

// Fetch seller profile
$stmt = db()->prepare(
    'SELECT id, name, surname, created_at FROM users WHERE id = ? AND is_active = 1'
);
$stmt->execute([$sellerId]);
$seller = $stmt->fetch();
if (!$seller) redirect('');

// Aggregate rating — exclude hidden reviews
$stmt = db()->prepare(
    'SELECT AVG(rating) AS avg_r, COUNT(*) AS cnt FROM reviews WHERE reviewee_id = ? AND is_hidden = 0'
);
$stmt->execute([$sellerId]);
$rData     = $stmt->fetch();
$avgRating = $rData['cnt'] > 0 ? round((float) $rData['avg_r'], 1) : null;
$reviewCnt = (int) $rData['cnt'];

// All visible reviews for this seller, newest first
$stmt = db()->prepare(
    'SELECT r.rating, r.body, r.created_at,
            u.name AS reviewer_name, u.surname AS reviewer_surname
     FROM reviews r
     JOIN users u ON u.id = r.reviewer_id
     WHERE r.reviewee_id = ? AND r.is_hidden = 0
     ORDER BY r.created_at DESC'
);
$stmt->execute([$sellerId]);
$reviews = $stmt->fetchAll();

// Active listings by this seller
$stmt = db()->prepare(
    'SELECT id, title, price_cents, `condition`, location_city, image_path
     FROM listings
     WHERE seller_id = ? AND status = ?
     ORDER BY created_at DESC
     LIMIT 12'
);
$stmt->execute([$sellerId, 'active']);
$listings = $stmt->fetchAll();

$conditionLabels = [
    'new'       => 'New',
    'like_new'  => 'Like New',
    'good'      => 'Good',
    'fair'      => 'Fair',
];

$pageTitle      = e($seller['name'] . ' ' . $seller['surname']) . ' — ' . APP_NAME;
$pageStylesheet = 'seller.css';
require_once __DIR__ . '/includes/header.php';
?>

<section class="seller-profile">
    <div class="container">

        <a href="javascript:history.back()" class="back-link">&larr; Back</a>

        <!-- Seller profile header -->
        <div class="seller-profile__header">
            <div class="seller-profile__avatar" aria-hidden="true">
                <?= e(strtoupper(substr($seller['name'], 0, 1))) ?>
            </div>
            <div class="seller-profile__info">
                <h1 class="seller-profile__name">
                    <?= e($seller['name'] . ' ' . $seller['surname']) ?>
                </h1>
                <div class="seller-profile__meta">
                    Member since <?= date('F Y', strtotime($seller['created_at'])) ?>
                </div>
                <?php if ($avgRating !== null): ?>
                <div class="seller-profile__rating">
                    <span class="seller-profile__stars" aria-label="<?= $avgRating ?> out of 5">
                        <?php
                        $full  = (int) floor($avgRating);
                        $empty = 5 - $full;
                        echo str_repeat('<i class="bi bi-star-fill" aria-hidden="true"></i>', $full)
                           . str_repeat('<i class="bi bi-star" aria-hidden="true"></i>', $empty);
                        ?>
                    </span>
                    <span class="seller-profile__rating-text">
                        <?= $avgRating ?> &mdash; <?= $reviewCnt ?> review<?= $reviewCnt !== 1 ? 's' : '' ?>
                    </span>
                </div>
                <?php else: ?>
                <div class="seller-profile__no-rating">No reviews yet</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="seller-profile__body">

            <!-- Reviews column -->
            <div class="seller-profile__reviews">
                <h2 class="seller-profile__section-heading">
                    Reviews
                    <?php if ($reviewCnt > 0): ?>
                    <span class="seller-profile__count"><?= $reviewCnt ?></span>
                    <?php endif; ?>
                </h2>

                <?php if ($reviews): ?>
                    <?php foreach ($reviews as $rev): ?>
                    <div class="review-item">
                        <div class="review-item__header">
                            <div class="review-item__avatar" aria-hidden="true">
                                <?= e(strtoupper(substr($rev['reviewer_name'], 0, 1))) ?>
                            </div>
                            <div class="review-item__meta">
                                <span class="review-item__reviewer">
                                    <?= e($rev['reviewer_name'] . ' ' . $rev['reviewer_surname']) ?>
                                </span>
                                <span class="review-item__stars" aria-label="<?= (int)$rev['rating'] ?> out of 5">
                                    <?php
                                    $f = (int) $rev['rating'];
                                    echo str_repeat('<i class="bi bi-star-fill" aria-hidden="true"></i>', $f)
                                       . str_repeat('<i class="bi bi-star" aria-hidden="true"></i>', 5 - $f);
                                    ?>
                                </span>
                                <span class="review-item__date">
                                    <?= date('d M Y', strtotime($rev['created_at'])) ?>
                                </span>
                            </div>
                        </div>
                        <?php if (!empty($rev['body'])): ?>
                        <p class="review-item__body"><?= nl2br(e($rev['body'])) ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                <p class="seller-profile__empty">This seller has no reviews yet.</p>
                <?php endif; ?>
            </div>

            <!-- Active listings column -->
            <div class="seller-profile__listings">
                <h2 class="seller-profile__section-heading">
                    Active listings
                    <?php if ($listings): ?>
                    <span class="seller-profile__count"><?= count($listings) ?></span>
                    <?php endif; ?>
                </h2>

                <?php if ($listings): ?>
                <div class="seller-listings-grid">
                    <?php foreach ($listings as $l): ?>
                    <a href="<?= APP_URL ?>/listing.php?id=<?= (int)$l['id'] ?>"
                       class="listing-card listing-card--compact">
                        <div class="listing-card__image">
                            <?php if ($l['image_path']): ?>
                            <img src="<?= e(UPLOAD_URL . $l['image_path']) ?>"
                                 alt="<?= e($l['title']) ?>"
                                 loading="lazy">
                            <?php else: ?>
                            <div class="listing-card__placeholder">
                                <i class="bi bi-image" aria-hidden="true"></i>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="listing-card__body">
                            <span class="listing-card__title"><?= e($l['title']) ?></span>
                            <span class="listing-card__price"><?= format_zar((int)$l['price_cents']) ?></span>
                            <div class="listing-card__meta">
                                <span class="listing-card__location"><?= e($l['location_city']) ?></span>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="seller-profile__empty">No active listings right now.</p>
                <?php endif; ?>
            </div>

        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
