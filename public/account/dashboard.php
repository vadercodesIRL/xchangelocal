<?php
define('XCHANGE', true);
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/helpers.php';

start_session();
require_login();

$user = current_user();
$uid  = (int)$user['id'];

// Pull counts to show on the dashboard cards
$activeListings = 0;
$totalSales     = 0;
$pendingSales   = 0;

if ($user['can_sell']) {
    $s = db()->prepare("SELECT COUNT(*) FROM listings WHERE seller_id = ? AND status = 'active'");
    $s->execute([$uid]);
    $activeListings = (int)$s->fetchColumn();

    // total sales (exclude cancelled)
    $s = db()->prepare(
        "SELECT COUNT(*) FROM orders o
         JOIN listings l ON l.id = o.listing_id
         WHERE l.seller_id = ? AND o.status NOT IN ('cancelled')"
    );
    $s->execute([$uid]);
    $totalSales = (int)$s->fetchColumn();

    // sales that need action (pending or confirmed)
    $s = db()->prepare(
        "SELECT COUNT(*) FROM orders o
         JOIN listings l ON l.id = o.listing_id
         WHERE l.seller_id = ? AND o.status IN ('pending','confirmed')"
    );
    $s->execute([$uid]);
    $pendingSales = (int)$s->fetchColumn();
}

// buyer stats
$s = db()->prepare("SELECT COUNT(*) FROM orders WHERE buyer_id = ?");
$s->execute([$uid]);
$totalBuys = (int)$s->fetchColumn();

$s = db()->prepare("SELECT COUNT(*) FROM orders WHERE buyer_id = ? AND status IN ('pending','confirmed')");
$s->execute([$uid]);
$pendingBuys = (int)$s->fetchColumn();

$pageTitle      = 'My account — ' . APP_NAME;
$pageStylesheet = 'auth.css';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<section class="dashboard-section">
    <div class="container">

        <!-- Welcome -->
        <div class="account-welcome">
            <div class="account-welcome__avatar" aria-hidden="true">
                <?= e(strtoupper(substr($user['name'], 0, 1))) ?>
            </div>
            <div>
                <h1 class="account-welcome__name">
                    <?= e($user['name'] . ' ' . $user['surname']) ?>
                </h1>
                <p class="account-welcome__email"><?= e($user['email']) ?></p>
            </div>
        </div>

        <!-- Account cards grid -->
        <div class="account-grid">

            <!-- My orders -->
            <a href="<?= APP_URL ?>/account/my-purchases.php" class="account-card">
                <div class="account-card__icon account-card__icon--blue">
                    <i class="bi bi-bag" aria-hidden="true"></i>
                </div>
                <div class="account-card__body">
                    <div class="account-card__title">My orders</div>
                    <div class="account-card__sub">
                        <?php if ($pendingBuys > 0): ?>
                        <span class="account-card__badge"><?= $pendingBuys ?> pending</span>
                        <?php elseif ($totalBuys > 0): ?>
                        <?= $totalBuys ?> order<?= $totalBuys !== 1 ? 's' : '' ?> total
                        <?php else: ?>
                        No orders yet
                        <?php endif; ?>
                    </div>
                </div>
                <span class="account-card__arrow" aria-hidden="true">&rarr;</span>
            </a>

            <?php if ($user['can_sell']): ?>

            <!-- My sales -->
            <a href="<?= APP_URL ?>/account/my-sales.php" class="account-card">
                <div class="account-card__icon account-card__icon--green">
                    <i class="bi bi-cash-coin" aria-hidden="true"></i>
                </div>
                <div class="account-card__body">
                    <div class="account-card__title">My sales</div>
                    <div class="account-card__sub">
                        <?php if ($pendingSales > 0): ?>
                        <span class="account-card__badge account-card__badge--gold">
                            <?= $pendingSales ?> need action
                        </span>
                        <?php elseif ($totalSales > 0): ?>
                        <?= $totalSales ?> sale<?= $totalSales !== 1 ? 's' : '' ?> total
                        <?php else: ?>
                        No sales yet
                        <?php endif; ?>
                    </div>
                </div>
                <span class="account-card__arrow" aria-hidden="true">&rarr;</span>
            </a>

            <!-- My listings -->
            <a href="<?= APP_URL ?>/account/my-listings.php" class="account-card">
                <div class="account-card__icon account-card__icon--gold">
                    <i class="bi bi-tag" aria-hidden="true"></i>
                </div>
                <div class="account-card__body">
                    <div class="account-card__title">My listings</div>
                    <div class="account-card__sub">
                        <?= $activeListings ?> active listing<?= $activeListings !== 1 ? 's' : '' ?>
                    </div>
                </div>
                <span class="account-card__arrow" aria-hidden="true">&rarr;</span>
            </a>

            <!-- Post a listing -->
            <a href="<?= APP_URL ?>/account/new-listing.php" class="account-card account-card--primary">
                <div class="account-card__icon account-card__icon--primary">
                    <i class="bi bi-plus-circle" aria-hidden="true"></i>
                </div>
                <div class="account-card__body">
                    <div class="account-card__title">Post a listing</div>
                    <div class="account-card__sub">List something for sale</div>
                </div>
                <span class="account-card__arrow" aria-hidden="true">&rarr;</span>
            </a>

            <?php else: ?>

            <!-- Browse (for buyers who can't sell) -->
            <a href="<?= APP_URL ?>/browse.php" class="account-card">
                <div class="account-card__icon account-card__icon--primary">
                    <i class="bi bi-search" aria-hidden="true"></i>
                </div>
                <div class="account-card__body">
                    <div class="account-card__title">Browse listings</div>
                    <div class="account-card__sub">Find something to buy</div>
                </div>
                <span class="account-card__arrow" aria-hidden="true">&rarr;</span>
            </a>

            <?php endif; ?>

            <!-- Cart -->
            <?php $cartCount = count($_SESSION['cart'] ?? []); ?>
            <a href="<?= APP_URL ?>/cart.php" class="account-card">
                <div class="account-card__icon account-card__icon--muted">
                    <i class="bi bi-cart" aria-hidden="true"></i>
                </div>
                <div class="account-card__body">
                    <div class="account-card__title">My cart</div>
                    <div class="account-card__sub">
                        <?= $cartCount > 0
                            ? $cartCount . ' item' . ($cartCount !== 1 ? 's' : '') . ' ready to checkout'
                            : 'Your cart is empty' ?>
                    </div>
                </div>
                <span class="account-card__arrow" aria-hidden="true">&rarr;</span>
            </a>

            <!-- Settings -->
            <a href="<?= APP_URL ?>/account/settings.php" class="account-card">
                <div class="account-card__icon account-card__icon--muted">
                    <i class="bi bi-gear" aria-hidden="true"></i>
                </div>
                <div class="account-card__body">
                    <div class="account-card__title">Settings</div>
                    <div class="account-card__sub">Name, password, date of birth</div>
                </div>
                <span class="account-card__arrow" aria-hidden="true">&rarr;</span>
            </a>

            <!-- Sign out -->
            <a href="<?= APP_URL ?>/account/logout.php" class="account-card account-card--signout">
                <div class="account-card__icon account-card__icon--muted">
                    <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
                </div>
                <div class="account-card__body">
                    <div class="account-card__title">Sign out</div>
                    <div class="account-card__sub">You're signed in as <?= e($user['email']) ?></div>
                </div>
            </a>

        </div>

    </div>
</section>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
