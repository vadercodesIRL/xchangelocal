<?php
define('XCHANGE', true);
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/helpers.php';

start_session();
if (empty($_SESSION['admin_verified'])) { redirect('admin/login.php'); }
require_role(['admin', 'moderator', 'support']);

// Grab the numbers for the stat cards
$totalUsers   = (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
$totalActive  = (int) db()->query("SELECT COUNT(*) FROM listings WHERE status = 'active'")->fetchColumn();
$totalOrders  = (int) db()->query('SELECT COUNT(*) FROM orders')->fetchColumn();
// platform revenue = sum of commission cents on completed/approved orders
$totalRevenue = (int) db()->query(
    "SELECT COALESCE(SUM(commission_cents), 0) FROM orders WHERE status IN ('completed', 'approved')"
)->fetchColumn();

// total transaction volume (full sale price)
$totalVolume = (int) db()->query(
    "SELECT COALESCE(SUM(price_cents), 0) FROM orders WHERE status IN ('completed', 'approved')"
)->fetchColumn();

// Get the 10 most recent orders for the table below the stat cards
$recentOrders = db()->query(
    "SELECT o.id, o.status, o.price_cents, o.commission_cents, o.created_at,
            CONCAT(bu.name, ' ', bu.surname) AS buyer_name,
            l.title AS item_title
     FROM orders o
     JOIN users bu   ON bu.id = o.buyer_id
     JOIN listings l ON l.id  = o.listing_id
     ORDER BY o.created_at DESC
     LIMIT 10"
)->fetchAll();

$pageTitle      = 'Admin — ' . APP_NAME;
$pageStylesheet = 'auth.css';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<section class="dashboard-section">
    <div class="container">

        <div class="admin-header">
            <h1 class="section__heading" style="margin-bottom:0">Admin Dashboard</h1>
            <div style="display:flex;gap:var(--space-3);flex-wrap:wrap">
                <a href="<?= APP_URL ?>/admin/listings.php"    class="btn btn--ghost-white btn--sm">All Listings</a>
                <a href="<?= APP_URL ?>/admin/users.php"       class="btn btn--ghost-white btn--sm">All Users</a>
                <a href="<?= APP_URL ?>/admin/reviews.php"     class="btn btn--ghost-white btn--sm">All Reviews</a>
                <a href="<?= APP_URL ?>/admin/transactions.php" class="btn btn--ghost-white btn--sm">Transactions</a>
                <a href="<?= APP_URL ?>/"                      class="btn btn--primary btn--sm">Back to site &rarr;</a>
            </div>
        </div>

        <div class="stats-grid">

            <div class="stat-card stat-card--ocean">
                <div class="stat-card__icon">
                    <i class="bi bi-people" aria-hidden="true"></i>
                </div>
                <div class="stat-card__value"><?= number_format($totalUsers) ?></div>
                <div class="stat-card__label">Total Users</div>
                <a href="<?= APP_URL ?>/admin/users.php" class="stat-card__link">Manage &rarr;</a>
            </div>

            <div class="stat-card stat-card--green">
                <div class="stat-card__icon">
                    <i class="bi bi-grid" aria-hidden="true"></i>
                </div>
                <div class="stat-card__value"><?= number_format($totalActive) ?></div>
                <div class="stat-card__label">Active Listings</div>
                <a href="<?= APP_URL ?>/admin/listings.php?status=active" class="stat-card__link">View &rarr;</a>
            </div>

            <div class="stat-card stat-card--gold">
                <div class="stat-card__icon">
                    <i class="bi bi-bag" aria-hidden="true"></i>
                </div>
                <div class="stat-card__value"><?= number_format($totalOrders) ?></div>
                <div class="stat-card__label">Total Orders</div>
            </div>

            <div class="stat-card stat-card--purple">
                <div class="stat-card__icon">
                    <i class="bi bi-cash-coin" aria-hidden="true"></i>
                </div>
                <div class="stat-card__value stat-card__value--revenue"><?= format_zar($totalRevenue) ?></div>
                <div class="stat-card__label">Platform Revenue (2% commission)</div>
                <div style="font-size:var(--text-xs);color:var(--color-text-muted);margin-top:var(--space-1)">
                    Transaction volume: <?= format_zar($totalVolume) ?>
                </div>
            </div>

        </div>

        <h2 class="section__heading" style="font-size:var(--text-xl);margin-bottom:var(--space-4)">Recent Orders</h2>

        <?php if ($recentOrders): ?>
        <div class="listings-table-wrap" style="margin-bottom:var(--space-10)">
            <table class="listings-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Buyer</th>
                        <th>Item</th>
                        <th>Price</th>
                        <th>Commission</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentOrders as $o): ?>
                    <tr>
                        <td data-label="#" style="color:var(--color-text-muted);font-size:var(--text-xs)">#<?= (int)$o['id'] ?></td>
                        <td data-label="Buyer"><?= e($o['buyer_name']) ?></td>
                        <td class="listings-table__title" data-label="Item"><?= e($o['item_title']) ?></td>
                        <td data-label="Price"><?= format_zar((int)$o['price_cents']) ?></td>
                        <td data-label="Commission" style="color:var(--color-admin-text);font-size:var(--text-xs)">
                            <?php $com = (int)$o['commission_cents'] > 0 ? (int)$o['commission_cents'] : (int)round($o['price_cents'] * 0.02); ?>
                            <?= format_zar($com) ?>
                        </td>
                        <td data-label="Status">
                            <span class="status-badge order-status-badge--<?= e($o['status']) ?>">
                                <?= e(ucfirst($o['status'])) ?>
                            </span>
                        </td>
                        <td data-label="Date"><?= e(date('d M Y', strtotime($o['created_at']))) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <p style="color:var(--color-text-muted);margin-bottom:var(--space-10)">No orders yet.</p>
        <?php endif; ?>

    </div>
</section>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
