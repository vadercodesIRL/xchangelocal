<?php
define('XCHANGE', true);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

start_session();

// shared subquery for seller ratings
$ratingsSql = '
    LEFT JOIN (
        SELECT reviewee_id,
               ROUND(AVG(rating), 1) AS avg_rating,
               COUNT(*)              AS review_count
        FROM reviews
        GROUP BY reviewee_id
    ) rs ON rs.reviewee_id = l.seller_id';

$selectCols = 'l.id, l.title, l.price_cents, l.`condition`, l.location_city,
               l.image_path AS image, u.name AS seller_name, u.is_verified AS seller_verified,
               rs.avg_rating, rs.review_count';

$latestListings = [];
$useFeatured    = false;

try {
    // try editor's picks first
    $stmt = db()->prepare(
        "SELECT $selectCols FROM listings l
         JOIN users u ON u.id = l.seller_id $ratingsSql
         WHERE l.is_featured = 1 AND l.status = 'active'
         ORDER BY l.created_at DESC"
    );
    $stmt->execute([]);
    $picks = $stmt->fetchAll();

    if (!empty($picks)) {
        $latestListings = $picks;
        $useFeatured    = true;
    } else {
        // fall back to 6 newest active listings
        $stmt = db()->prepare(
            "SELECT $selectCols FROM listings l
             JOIN users u ON u.id = l.seller_id $ratingsSql
             WHERE l.status = 'active'
             ORDER BY l.created_at DESC
             LIMIT 6"
        );
        $stmt->execute([]);
        $latestListings = $stmt->fetchAll();
    }

} catch (PDOException $e) {
    if (APP_ENV === 'development') throw $e;
    // production: fall back gracefully
    try {
        $stmt = db()->prepare(
            "SELECT $selectCols FROM listings l
             JOIN users u ON u.id = l.seller_id $ratingsSql
             WHERE l.status = 'active'
             ORDER BY l.created_at DESC LIMIT 6"
        );
        $stmt->execute([]);
        $latestListings = $stmt->fetchAll();
    } catch (PDOException $e2) {
        $latestListings = [];
    }
}

// ── Helpers ────────────────────────────────────────────────────────────────

function condition_badge(string $condition): string
{
    $map = [
        'new'       => ['New',       'new'],
        'like_new'  => ['Like New',  'like-new'],
        'good'      => ['Good',      'good'],
        'fair'      => ['Fair',      'fair'],
    ];
    [$label, $mod] = $map[$condition] ?? ['', ''];
    if (!$label) return '';
    return '<span class="badge badge--' . $mod . '">' . $label . '</span>';
}

$pageTitle = APP_NAME . ' — Buy and sell with people near you';
require_once __DIR__ . '/includes/header.php';
?>

<!-- ── Hero ────────────────────────────────────────────────────────────────── -->
<section class="hero">
    <div class="container">
        <div class="hero__inner">

            <!-- Left: copy + search -->
            <div class="hero__copy">
                <h1 class="hero__headline cascade-up" style="animation-delay:100ms"><?= t('hero_title') ?></h1>
                <p class="hero__sub cascade-up" style="animation-delay:250ms">
                    <?= t('hero_sub') ?>
                </p>

                <a href="<?= $user ? APP_URL . '/account/dashboard.php' : APP_URL . '/account/register.php' ?>"
                   class="btn btn--accent cascade-up"
                   style="animation-delay:400ms;font-size:var(--text-lg);padding:0 var(--space-8);min-height:56px;align-self:flex-start">
                    <i class="bi bi-arrow-right-circle" aria-hidden="true"></i> <?= t('hero_btn') ?>
                </a>

                <p class="hero__commission cascade-up" style="animation-delay:550ms">
                    <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                    <?= t('commission') ?>
                </p>
            </div>


        </div>
    </div>
</section>

<!-- ── Editor's Picks / Latest listings ────────────────────────────────────── -->
<section class="listings-section cascade-up" style="animation-delay:700ms">
    <div class="container">
        <div class="listings-section__header">
            <h2 class="section__heading" style="margin-bottom:0">
                <?php if ($useFeatured): ?>
                <i class="bi bi-star-fill" aria-hidden="true" style="color:var(--color-accent-text);font-size:0.85em;margin-right:var(--space-2)"></i><?= t('editors_picks') ?>
                <?php else: ?>
                Latest Listings
                <?php endif; ?>
            </h2>
            <a href="<?= APP_URL ?>/browse.php" class="listings-section__link">
                <?= t('view_all') ?> &rarr;
            </a>
        </div>

        <?php if ($latestListings): ?>
        <div class="listings-grid">
            <?php foreach ($latestListings as $listing): ?>
            <a href="<?= APP_URL ?>/listing.php?id=<?= (int)$listing['id'] ?>"
               class="listing-card">
                <div class="listing-card__image">
                    <?php if ($listing['image']): ?>
                    <img src="<?= e(APP_URL . '/uploads/listings/' . $listing['image']) ?>"
                         alt="<?= e($listing['title']) ?>"
                         loading="lazy">
                    <?php else: ?>
                    <div class="listing-card__placeholder">
                        <i class="bi bi-image" aria-hidden="true"></i>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="listing-card__body">
                    <span class="listing-card__title"><?= e($listing['title']) ?></span>
                    <span class="listing-card__price"><?= format_zar((int)$listing['price_cents']) ?></span>
                    <div class="listing-card__meta">
                        <span class="listing-card__location"><?= e($listing['location_city']) ?></span>
                        <?= condition_badge($listing['condition']) ?>
                    </div>
                    <div class="listing-card__seller">
                        <span class="listing-card__seller-name">By <?= e($listing['seller_name']) ?><?php if ($listing['seller_verified']): ?><i class="bi bi-patch-check-fill" title="Verified Seller" aria-label="Verified Seller" style="color:var(--color-success-text);font-size:var(--text-sm);margin-left:3px"></i><?php endif; ?></span>
                        <?php if ($listing['avg_rating'] !== null): ?>
                        <span class="listing-card__seller-rating">
                            <?php
                            $f = (int) floor((float)$listing['avg_rating']);
                            echo str_repeat('<i class="bi bi-star-fill" aria-hidden="true"></i>', $f) . str_repeat('<i class="bi bi-star" aria-hidden="true"></i>', 5 - $f);
                            ?>
                            <span class="listing-card__seller-count">(<?= (int)$listing['review_count'] ?>)</span>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <?php else: ?>
        <div class="listings-empty">
            <p class="listings-empty__heading">No listings yet</p>
            <p>Be the first to post something.</p>
        </div>
        <?php endif; ?>

    </div>
</section>

<!-- ── Reviews ──────────────────────────────────────────────────────────── -->
<section class="reviews-section">
    <div class="container">

        <div class="reviews-header">
            <h2 class="section__heading" style="margin-bottom:0">What people are saying</h2>
            <div class="reviews-overall">
                <span class="reviews-overall__stars" aria-label="4.5 out of 5 stars"><i class="bi bi-star-fill" aria-hidden="true"></i><i class="bi bi-star-fill" aria-hidden="true"></i><i class="bi bi-star-fill" aria-hidden="true"></i><i class="bi bi-star-fill" aria-hidden="true"></i><i class="bi bi-star-half" aria-hidden="true"></i></span>
                <span class="reviews-overall__score">4.5 out of 5</span>
            </div>
        </div>

        <div class="reviews-grid">

            <article class="review-card">
                <div class="review-card__header">
                    <span class="review-card__name">Liam.G</span>
                    <span class="review-card__stars" aria-label="5 out of 5 stars"><i class="bi bi-star-fill" aria-hidden="true"></i><i class="bi bi-star-fill" aria-hidden="true"></i><i class="bi bi-star-fill" aria-hidden="true"></i><i class="bi bi-star-fill" aria-hidden="true"></i><i class="bi bi-star-fill" aria-hidden="true"></i></span>
                </div>
                <p class="review-card__body">"HAPPY that I found xchangelocal! ANGRY I didn't find it sooner!</p>
            </article>

            <article class="review-card">
                <div class="review-card__header">
                    <span class="review-card__name">Nipho</span>
                    <span class="review-card__stars" aria-label="4 out of 5 stars"><i class="bi bi-star-fill" aria-hidden="true"></i><i class="bi bi-star-fill" aria-hidden="true"></i><i class="bi bi-star-fill" aria-hidden="true"></i><i class="bi bi-star-fill" aria-hidden="true"></i><i class="bi bi-star" aria-hidden="true"></i></span>
                </div>
                <p class="review-card__body">"Website was super easy to use. Seller was quick to respond too."</p>
            </article>

            <article class="review-card">
                <div class="review-card__header">
                    <span class="review-card__name">Adam Blu</span>
                    <span class="review-card__stars" aria-label="5 out of 5 stars"><i class="bi bi-star-fill" aria-hidden="true"></i><i class="bi bi-star-fill" aria-hidden="true"></i><i class="bi bi-star-fill" aria-hidden="true"></i><i class="bi bi-star-fill" aria-hidden="true"></i><i class="bi bi-star-fill" aria-hidden="true"></i></span>
                </div>
                <p class="review-card__body">"Product was exactly as described! "</p>
            </article>

            <article class="review-card">
                <div class="review-card__header">
                    <span class="review-card__name">Nipho Magwaza</span>
                    <span class="review-card__stars" aria-label="3 out of 5 stars"><i class="bi bi-star-fill" aria-hidden="true"></i><i class="bi bi-star-fill" aria-hidden="true"></i><i class="bi bi-star-fill" aria-hidden="true"></i><i class="bi bi-star" aria-hidden="true"></i><i class="bi bi-star" aria-hidden="true"></i></span>
                </div>
                <p class="review-card__body">"It's so EASY to start selling! Take advantage of this AMAZING platform!"</p>
            </article>

            <article class="review-card">
                <div class="review-card__header">
                    <span class="review-card__name">Lucie.M</span>
                    <span class="review-card__stars" aria-label="5 out of 5 stars"><i class="bi bi-star-fill" aria-hidden="true"></i><i class="bi bi-star-fill" aria-hidden="true"></i><i class="bi bi-star-fill" aria-hidden="true"></i><i class="bi bi-star-fill" aria-hidden="true"></i><i class="bi bi-star-fill" aria-hidden="true"></i></span>
                </div>
                <p class="review-card__body">"Perfect for community buying and selling. I highly recommend."</p>
            </article>

            <article class="review-card">
                <div class="review-card__header">
                    <span class="review-card__name">Jaden Grey</span>
                    <span class="review-card__stars" aria-label="4 out of 5 stars"><i class="bi bi-star-fill" aria-hidden="true"></i><i class="bi bi-star-fill" aria-hidden="true"></i><i class="bi bi-star-fill" aria-hidden="true"></i><i class="bi bi-star-fill" aria-hidden="true"></i><i class="bi bi-star" aria-hidden="true"></i></span>
                </div>
                <p class="review-card__body">"Made so much money here. Easy to buy and sell, their support email responds almost instantly. "</p>
            </article>

        </div>

    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
