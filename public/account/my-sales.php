<?php
define('XCHANGE', true);
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/csrf.php';

start_session();
require_login();

$user = current_user();

// Pick up any flash message left by order-action.php after a redirect
$flash = $_SESSION['xl_flash'] ?? null;
unset($_SESSION['xl_flash']);

$stmt = db()->prepare(
    'SELECT o.id, o.price_cents, o.commission_cents, o.seller_payout_cents,
            o.status, o.created_at,
            l.id AS listing_id, l.title AS listing_title,
            u.name AS buyer_name, u.surname AS buyer_surname
     FROM orders o
     JOIN listings l ON l.id = o.listing_id
     JOIN users u    ON u.id = o.buyer_id
     WHERE l.seller_id = ?
     ORDER BY o.created_at DESC'
);
$stmt->execute([$user['id']]);
$sales = $stmt->fetchAll();

// Human-readable label for each status
function order_status_label(string $s): string
{
    return match ($s) {
        'pending'   => 'Pending',
        'confirmed' => 'Confirmed',
        'completed' => 'Completed',
        'approved'  => 'Approved',
        'disputed'  => 'Disputed',
        'cancelled' => 'Cancelled',
        default     => ucfirst($s),
    };
}

$pageTitle      = 'My sales — ' . APP_NAME;
$pageStylesheet = 'auth.css';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<section class="dashboard-section">
    <div class="container">

        <div class="my-listings-header">
            <div>
                <a href="<?= APP_URL ?>/account/dashboard.php" class="back-link">&larr; Dashboard</a>
                <h1 class="section__heading" style="margin-top:var(--space-2)">
                    My sales
                    <?php if ($user['is_verified'] ?? false): ?>
                    <span class="verified-badge" style="display:inline-flex;align-items:center;gap:4px;color:var(--color-success-text);font-size:var(--text-base);font-weight:var(--weight-semibold);vertical-align:middle">
                        <i class="bi bi-patch-check-fill" aria-hidden="true"></i> Verified Seller
                    </span>
                    <?php endif; ?>
                </h1>
            </div>
        </div>

        <div class="commission-info-bar">
            <i class="bi bi-info-circle" aria-hidden="true"></i>
            XchangeLocal deducts a <strong>2% platform fee</strong> from each sale. The amount shown under "Your payout" is what you receive.
        </div>

        <?php if ($flash): ?>
        <div class="form-alert form-alert--<?= e($flash['type']) ?>" role="status">
            <?= e($flash['msg']) ?>
        </div>
        <?php endif; ?>

        <?php if (empty($sales)): ?>
        <div class="listings-empty-state">
            <p>No orders on your listings yet.</p>
            <a href="<?= APP_URL ?>/account/my-listings.php" class="btn btn--accent"
               style="margin-top:var(--space-4)">View my listings</a>
        </div>

        <?php else: ?>
        <div class="listings-table-wrap">
            <table class="listings-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Buyer</th>
                        <th>Sale price</th>
                        <th>Your payout</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sales as $s): ?>
                    <tr>
                        <td class="listings-table__title" data-label="Item">
                            <a href="<?= APP_URL ?>/listing.php?id=<?= (int)$s['listing_id'] ?>"
                               style="color:var(--color-primary)">
                                <?= e($s['listing_title']) ?>
                            </a>
                        </td>
                        <td data-label="Buyer"><?= e($s['buyer_name'] . ' ' . $s['buyer_surname']) ?></td>
                        <td data-label="Sale price" style="color:var(--color-accent-text);font-weight:var(--weight-semibold)">
                            <?= format_zar((int)$s['price_cents']) ?>
                        </td>
                        <td data-label="Your payout" style="color:var(--color-success-text);font-weight:var(--weight-semibold)">
                            <?php
                            $payout = (int)$s['seller_payout_cents'] > 0
                                ? (int)$s['seller_payout_cents']
                                : (int)$s['price_cents'] - (int)round($s['price_cents'] * 0.02);
                            ?>
                            <?= format_zar($payout) ?>
                        </td>
                        <td data-label="Date"><?= e(date('d M Y', strtotime($s['created_at']))) ?></td>
                        <td data-label="Status">
                            <span class="status-badge order-status-badge--<?= e($s['status']) ?>">
                                <?= order_status_label($s['status']) ?>
                            </span>
                        </td>
                        <td data-label="">
                            <?php if ($s['status'] === 'pending'): ?>
                            <div class="order-actions">
                                <form method="POST" action="<?= APP_URL ?>/api/order-action.php">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="order_id" value="<?= (int)$s['id'] ?>">
                                    <input type="hidden" name="action"   value="confirm">
                                    <input type="hidden" name="back"     value="account/my-sales.php">
                                    <button type="submit" class="btn btn--sm btn--primary">
                                        <i class="bi bi-check-lg" aria-hidden="true"></i> Confirm Order
                                    </button>
                                </form>
                                <form method="POST" action="<?= APP_URL ?>/api/order-action.php">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="order_id" value="<?= (int)$s['id'] ?>">
                                    <input type="hidden" name="action"   value="cancel">
                                    <input type="hidden" name="back"     value="account/my-sales.php">
                                    <button type="submit" class="btn btn--sm btn--danger">
                                        <i class="bi bi-x-circle" aria-hidden="true"></i> Cancel Order
                                    </button>
                                </form>
                            </div>

                            <?php elseif ($s['status'] === 'confirmed'): ?>
                            <form method="POST" action="<?= APP_URL ?>/api/order-action.php">
                                <?= csrf_field() ?>
                                <input type="hidden" name="order_id" value="<?= (int)$s['id'] ?>">
                                <input type="hidden" name="action"   value="complete">
                                <input type="hidden" name="back"     value="account/my-sales.php">
                                <button type="submit" class="btn btn--sm btn--accent">
                                    <i class="bi bi-check-circle" aria-hidden="true"></i> Mark as Completed
                                </button>
                            </form>

                            <?php else: ?>
                            <span style="font-size:var(--text-xs);color:var(--color-text-muted)">
                                No actions
                            </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Support note -->
        <div class="support-contact">
            <i class="bi bi-info-circle" aria-hidden="true"></i>
            Need help with an order? Contact XchangeLocal support at
            <a href="mailto:xchangelocalbusiness@outlook.com">xchangelocalbusiness@outlook.com</a>
            — please allow 1–2 business days for a response.
        </div>

    </div>
</section>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
