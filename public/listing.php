<?php
define('XCHANGE', true);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/csrf.php';

start_session();

$user = current_user();
$id   = (int) ($_GET['id'] ?? 0);

if (!$id) redirect('');

$stmt = db()->prepare(
    'SELECT l.*,
            u.name AS seller_name, u.created_at AS seller_joined, u.is_verified AS seller_verified
     FROM listings l
     JOIN users u ON u.id = l.seller_id
     WHERE l.id = ?'
);
$stmt->execute([$id]);
$listing = $stmt->fetch();

if (!$listing) redirect('');

// Get the seller's average star rating — hidden reviews excluded
$rStmt = db()->prepare(
    'SELECT AVG(rating) AS avg_r, COUNT(*) AS cnt FROM reviews WHERE reviewee_id = ? AND is_hidden = 0'
);
$rStmt->execute([$listing['seller_id']]);
$rData      = $rStmt->fetch();
$avgRating  = $rData['cnt'] > 0 ? round((float)$rData['avg_r'], 1) : null;
$reviewCnt  = (int)$rData['cnt'];

$conditionLabels = [
    'new'       => 'New',
    'like_new'  => 'Like New',
    'good'      => 'Good',
    'fair'      => 'Fair',
];

$isOwner = $user && (int)$user['id'] === (int)$listing['seller_id'];
$inCart  = $user && in_array($id, $_SESSION['cart'] ?? []);
$ordered = !empty($_GET['ordered']);

$pageTitle      = e($listing['title']) . ' — ' . APP_NAME;
$pageStylesheet = 'listing.css';
require_once __DIR__ . '/includes/header.php';
?>

<section class="listing-detail">
    <div class="container">

        <a href="<?= APP_URL ?>/" class="back-link">&larr; Back to listings</a>

        <?php if ($ordered): ?>
        <div class="form-alert form-alert--success" role="status" style="margin-top:var(--space-4)">
            Order placed! The seller will be in touch to arrange collection.
            <a href="<?= APP_URL ?>/account/my-purchases.php"
               style="color:inherit;font-weight:var(--weight-semibold);text-decoration:underline;margin-left:var(--space-2)">
                View my purchases &rarr;
            </a>
        </div>
        <?php endif; ?>

        <?php if ($listing['status'] === 'sold'): ?>
        <div class="sold-banner" role="status">This item has been sold.</div>
        <?php elseif ($listing['status'] === 'reserved'): ?>
        <div class="sold-banner" role="status">This item is currently reserved.</div>
        <?php endif; ?>

        <div class="listing-detail__grid">

            <!-- Left: main content -->
            <div class="listing-detail__main">

                <?php
                $detailImages = array_values(array_filter([
                    $listing['image_path']   ?? null,
                    $listing['image_path_2'] ?? null,
                    $listing['image_path_3'] ?? null,
                ]));
                ?>
                <?php if ($detailImages): ?>
                <?php $total = count($detailImages); ?>
                <div class="listing-gallery">

                    <?php foreach ($detailImages as $i => $img): ?>
                    <img class="listing-gallery__slide<?= $i === 0 ? ' is-active' : '' ?>"
                         src="<?= e(UPLOAD_URL . $img) ?>"
                         alt="<?= e($listing['title']) ?><?= $total > 1 ? ' (' . ($i + 1) . ' of ' . $total . ')' : '' ?>"
                         <?= $i > 0 ? 'loading="lazy"' : '' ?>>
                    <?php endforeach; ?>

                    <?php if ($total > 1): ?>
                    <button class="listing-gallery__btn listing-gallery__btn--prev" aria-label="Previous photo">&#8249;</button>
                    <button class="listing-gallery__btn listing-gallery__btn--next" aria-label="Next photo">&#8250;</button>
                    <div class="listing-gallery__dots" aria-hidden="true">
                        <?php for ($i = 0; $i < $total; $i++): ?>
                        <span class="listing-gallery__dot<?= $i === 0 ? ' is-active' : '' ?>"></span>
                        <?php endfor; ?>
                    </div>
                    <?php endif; ?>

                </div>
                <?php endif; ?>

                <h1 class="listing-detail__title"><?= e($listing['title']) ?></h1>

                <div class="listing-detail__price"><?= format_zar((int)$listing['price_cents']) ?></div>

                <div class="listing-detail__meta">
                    <span class="listing-meta-chip">
                        <?= e($conditionLabels[$listing['condition']] ?? ucfirst($listing['condition'])) ?>
                    </span>
                    <span class="listing-meta-chip"><?= e($listing['location_city']) ?></span>
                    <span class="listing-meta-chip listing-meta-chip--muted">
                        Listed <?= date('d M Y', strtotime($listing['created_at'])) ?>
                    </span>
                </div>

                <div class="listing-detail__description">
                    <?= nl2br(e($listing['description'])) ?>
                </div>
            </div>

            <!-- Right: seller card + action -->
            <aside class="seller-card">

                <div class="seller-card__info">
                    <div class="seller-card__avatar" aria-hidden="true">
                        <?= e(strtoupper(substr($listing['seller_name'], 0, 1))) ?>
                    </div>
                    <div>
                        <a href="<?= APP_URL ?>/seller.php?id=<?= (int)$listing['seller_id'] ?>"
                       class="seller-card__name seller-card__name--link">
                        <?= e($listing['seller_name']) ?>
                    </a>
                        <?php if ($listing['seller_verified']): ?>
                        <div class="verified-badge" style="display:flex;align-items:center;gap:4px;color:var(--color-success-text);font-size:var(--text-xs);font-weight:var(--weight-semibold);margin-top:2px">
                            <i class="bi bi-patch-check-fill" aria-hidden="true"></i> Verified Seller
                        </div>
                        <?php endif; ?>

                        <!-- Seller rating -->
                        <?php if ($avgRating !== null): ?>
                        <div class="seller-card__rating">
                            <span class="seller-card__stars" aria-label="<?= $avgRating ?> out of 5">
                                <?php
                                $full  = (int) floor($avgRating);
                                $empty = 5 - $full;
                                echo str_repeat('<i class="bi bi-star-fill" aria-hidden="true"></i>', $full) . str_repeat('<i class="bi bi-star" aria-hidden="true"></i>', $empty);
                                ?>
                            </span>
                            <span class="seller-card__rating-text">
                                <?= $avgRating ?> (<?= $reviewCnt ?> review<?= $reviewCnt !== 1 ? 's' : '' ?>)
                            </span>
                        </div>
                        <?php else: ?>
                        <div class="seller-card__no-rating">No ratings yet</div>
                        <?php endif; ?>

                        <div class="seller-card__since">
                            Member since <?= date('Y', strtotime($listing['seller_joined'])) ?>
                        </div>
                    </div>
                </div>

                <a href="<?= APP_URL ?>/seller.php?id=<?= (int)$listing['seller_id'] ?>"
                   class="seller-card__profile-btn">
                    <i class="bi bi-person" aria-hidden="true"></i> View seller profile
                </a>

                <div class="seller-card__action">

                    <?php if ($listing['status'] === 'sold'): ?>
                    <div class="sold-chip">This item has been sold.</div>

                    <?php elseif ($listing['status'] === 'reserved'): ?>
                    <div class="sold-chip">This item is reserved.</div>

                    <?php elseif ($isOwner): ?>
                    <a href="<?= APP_URL ?>/account/edit-listing.php?id=<?= (int)$listing['id'] ?>"
                       class="btn btn--ghost-white seller-card__btn">
                        Edit this listing
                    </a>

                    <?php elseif (!$user): ?>
                    <a href="<?= APP_URL ?>/account/login.php?next=<?= urlencode('/listing.php?id=' . $id) ?>"
                       class="btn btn--accent seller-card__btn">
                        Sign in to buy
                    </a>

                    <?php elseif ($inCart): ?>
                    <div class="seller-card__incart">
                        <span class="seller-card__incart-label">Added to your cart</span>
                        <a href="<?= APP_URL ?>/cart.php"
                           class="btn btn--primary seller-card__btn" style="margin-top:var(--space-3)">
                            View cart &amp; checkout &rarr;
                        </a>
                    </div>

                    <?php else: ?>
                    <form method="POST" action="<?= APP_URL ?>/cart.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action"     value="add">
                        <input type="hidden" name="listing_id" value="<?= (int)$listing['id'] ?>">
                        <input type="hidden" name="back"       value="<?= e(APP_URL . '/listing.php?id=' . $id) ?>">
                        <button type="submit" class="btn btn--accent seller-card__btn">
                            Add to cart
                        </button>
                    </form>
                    <?php endif; ?>

                </div>
            </aside>

        </div>
    </div>
</section>

<script>
(function () {
    var slides = Array.from(document.querySelectorAll('.listing-gallery__slide'));
    var dots   = Array.from(document.querySelectorAll('.listing-gallery__dot'));
    if (slides.length < 2) return;

    var current = 0;

    function goTo(i) {
        slides[current].classList.remove('is-active');
        if (dots[current]) dots[current].classList.remove('is-active');
        current = (i + slides.length) % slides.length;
        slides[current].classList.add('is-active');
        if (dots[current]) dots[current].classList.add('is-active');
    }

    document.querySelector('.listing-gallery__btn--prev')
        .addEventListener('click', function () { goTo(current - 1); });
    document.querySelector('.listing-gallery__btn--next')
        .addEventListener('click', function () { goTo(current + 1); });

    // arrow keys navigate the gallery
    document.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowLeft')  goTo(current - 1);
        if (e.key === 'ArrowRight') goTo(current + 1);
    });
}());
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
