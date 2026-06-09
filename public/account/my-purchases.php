<?php
define('XCHANGE', true);
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/csrf.php';

start_session();
require_login();

$user = current_user();

// Handle review form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_review') {
    csrf_check();

    $orderId = (int) ($_POST['order_id'] ?? 0);
    $rating  = (int) ($_POST['rating']   ?? 0);
    $body    = trim($_POST['body'] ?? '');

    if ($orderId > 0 && $rating >= 1 && $rating <= 5) {
        // Buyer must own this order; rating allowed from confirmed onwards
        $chk = db()->prepare(
            "SELECT o.id, l.seller_id
             FROM orders o
             JOIN listings l ON l.id = o.listing_id
             WHERE o.id = ? AND o.buyer_id = ?
               AND o.status IN ('confirmed','completed','approved')"
        );
        $chk->execute([$orderId, $user['id']]);
        $orderRow = $chk->fetch();

        if ($orderRow) {
            $dup = db()->prepare('SELECT 1 FROM reviews WHERE order_id = ? AND reviewer_id = ?');
            $dup->execute([$orderId, $user['id']]);
            if (!$dup->fetchColumn()) {
                db()->prepare(
                    'INSERT INTO reviews (order_id, reviewer_id, reviewee_id, rating, body)
                     VALUES (?, ?, ?, ?, ?)'
                )->execute([
                    $orderId,
                    $user['id'],
                    $orderRow['seller_id'],
                    $rating,
                    $body !== '' ? $body : null,
                ]);
            }
        }
    }

    redirect('account/my-purchases.php?reviewed=1');
}

// Pick up any flash message left by order-action.php after a redirect
$flash = $_SESSION['xl_flash'] ?? null;
unset($_SESSION['xl_flash']);

// Get all orders placed by this buyer
$stmt = db()->prepare(
    'SELECT o.id, o.price_cents, o.status, o.created_at,
            l.id AS listing_id, l.title AS listing_title, l.seller_id,
            u.name AS seller_name, u.surname AS seller_surname,
            u.email AS seller_email, u.phone AS seller_phone
     FROM orders o
     JOIN listings l ON l.id = o.listing_id
     JOIN users u    ON u.id = l.seller_id
     WHERE o.buyer_id = ?
     ORDER BY o.created_at DESC'
);
$stmt->execute([$user['id']]);
$orders = $stmt->fetchAll();

// Check which orders the buyer has already reviewed (so we don't show the form twice)
$reviewedOrders = [];
if (!empty($orders)) {
    $orderIds     = array_column($orders, 'id');
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $revStmt      = db()->prepare(
        "SELECT order_id, rating FROM reviews WHERE reviewer_id = ? AND order_id IN ($placeholders)"
    );
    $revStmt->execute(array_merge([(int)$user['id']], $orderIds));
    foreach ($revStmt->fetchAll() as $r) {
        $reviewedOrders[(int)$r['order_id']] = (int)$r['rating'];
    }
}

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

$pageTitle      = 'My orders — ' . APP_NAME;
$pageStylesheet = 'auth.css';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<section class="dashboard-section">
    <div class="container">

        <div class="my-listings-header">
            <div>
                <a href="<?= APP_URL ?>/account/dashboard.php" class="back-link">&larr; Dashboard</a>
                <h1 class="section__heading" style="margin-top:var(--space-2)">My orders</h1>
            </div>
        </div>

        <?php if (!empty($_GET['reviewed'])): ?>
        <div class="form-alert form-alert--success" role="status">
            Thanks for your rating! Your review has been saved.
        </div>
        <?php endif; ?>

        <?php if ($flash): ?>
        <div class="form-alert form-alert--<?= e($flash['type']) ?>" role="status">
            <?= e($flash['msg']) ?>
        </div>
        <?php endif; ?>

        <?php if (empty($orders)): ?>
        <div class="listings-empty-state">
            <p>You haven't placed any orders yet.</p>
            <a href="<?= APP_URL ?>/browse.php" class="btn btn--accent"
               style="margin-top:var(--space-4)">Browse listings</a>
        </div>

        <?php else: ?>
        <div class="listings-table-wrap">
            <table class="listings-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Seller</th>
                        <th>Amount</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $o): ?>
                    <tr>
                        <td class="listings-table__title" data-label="Item">
                            <a href="<?= APP_URL ?>/listing.php?id=<?= (int)$o['listing_id'] ?>"
                               style="color:var(--color-primary)">
                                <?= e($o['listing_title']) ?>
                            </a>
                        </td>
                        <td data-label="Seller"><?= e($o['seller_name'] . ' ' . $o['seller_surname']) ?></td>
                        <td data-label="Amount" style="color:var(--color-accent-text);font-weight:var(--weight-semibold)">
                            <?= format_zar((int)$o['price_cents']) ?>
                            <span style="display:block;font-size:var(--text-xs);color:var(--color-text-muted);font-weight:var(--weight-regular)">incl. 2% platform fee</span>
                        </td>
                        <td data-label="Date"><?= e(date('d M Y', strtotime($o['created_at']))) ?></td>
                        <td data-label="Status">
                            <span class="status-badge order-status-badge--<?= e($o['status']) ?>">
                                <?= order_status_label($o['status']) ?>
                            </span>
                        </td>
                        <td data-label="">
                            <?php if ($o['status'] === 'pending'): ?>
                            <form method="POST" action="<?= APP_URL ?>/api/order-action.php">
                                <?= csrf_field() ?>
                                <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                                <input type="hidden" name="action"   value="cancel">
                                <input type="hidden" name="back"     value="account/my-purchases.php">
                                <button type="submit" class="btn btn--sm btn--danger"><i class="bi bi-x-circle" aria-hidden="true"></i> Cancel order</button>
                            </form>

                            <?php elseif ($o['status'] === 'completed'): ?>
                            <div class="order-actions">
                                <form method="POST" action="<?= APP_URL ?>/api/order-action.php">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                                    <input type="hidden" name="action"   value="approve">
                                    <input type="hidden" name="back"     value="account/my-purchases.php">
                                    <button type="submit" class="btn btn--sm btn--success"><i class="bi bi-check-lg" aria-hidden="true"></i> Approve</button>
                                </form>
                                <form method="POST" action="<?= APP_URL ?>/api/order-action.php">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                                    <input type="hidden" name="action"   value="dispute">
                                    <input type="hidden" name="back"     value="account/my-purchases.php">
                                    <button type="submit" class="btn btn--sm btn--danger"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> Dispute</button>
                                </form>
                            </div>

                            <?php elseif ($o['status'] === 'disputed'): ?>
                            <span class="order-status-note order-status-note--warn">
                                Under review &mdash;
                                <a href="mailto:xchangelocalbusiness@outlook.com"
                                   style="color:inherit;text-decoration:underline">
                                    contact support
                                </a>
                            </span>

                            <?php elseif (in_array($o['status'], ['confirmed','approved'], true)): ?>
                            <?php if (isset($reviewedOrders[(int)$o['id']])): ?>
                            <span class="rated-badge"
                                  aria-label="You rated this <?= $reviewedOrders[(int)$o['id']] ?> stars">
                                <?= str_repeat('<i class="bi bi-star-fill" aria-hidden="true"></i>', $reviewedOrders[(int)$o['id']]) ?><?= str_repeat('<i class="bi bi-star" aria-hidden="true"></i>', 5 - $reviewedOrders[(int)$o['id']]) ?>
                            </span>
                            <?php else: ?>
                            <button type="button" class="btn btn--sm btn--ghost-white"
                                    onclick="toggleRateForm(<?= (int)$o['id'] ?>)">
                                Rate seller
                            </button>
                            <?php endif; ?>

                            <?php else: ?>
                            <span style="font-size:var(--text-xs);color:var(--color-text-muted)">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <?php if (in_array($o['status'], ['confirmed','completed','approved'], true)): ?>
                    <tr>
                        <td colspan="6" style="padding:0 var(--space-4) var(--space-3);border-top:none">
                            <div style="background:var(--color-surface);border:1px solid var(--color-border);border-radius:var(--radius-md);padding:var(--space-4);font-size:var(--text-sm)">
                                <p style="margin:0 0 var(--space-2);font-weight:var(--weight-semibold);color:var(--color-text)">
                                    <i class="bi bi-truck" aria-hidden="true"></i>
                                    To arrange delivery, contact the seller:
                                </p>
                                <?php if ($o['seller_email']): ?>
                                <p style="margin:0 0 var(--space-1)">
                                    <i class="bi bi-envelope" aria-hidden="true"></i>
                                    <a href="mailto:<?= e($o['seller_email']) ?>" style="color:var(--color-primary)">
                                        <?= e($o['seller_email']) ?>
                                    </a>
                                </p>
                                <?php endif; ?>
                                <?php if ($o['seller_phone']): ?>
                                <p style="margin:0">
                                    <i class="bi bi-phone" aria-hidden="true"></i>
                                    <a href="tel:<?= e($o['seller_phone']) ?>" style="color:var(--color-primary)">
                                        <?= e($o['seller_phone']) ?>
                                    </a>
                                </p>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>

                    <?php if (
                        in_array($o['status'], ['confirmed','approved'], true)
                        && !isset($reviewedOrders[(int)$o['id']])
                    ): ?>
                    <tr id="rate-row-<?= (int)$o['id'] ?>" hidden>
                        <td colspan="6" class="rate-row-cell" data-label="">
                            <form method="POST" class="rate-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action"   value="submit_review">
                                <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                                <input type="hidden" name="rating"   class="rating-val" value="0">

                                <p class="rate-form__heading">
                                    Rate your experience with <?= e($o['seller_name']) ?>
                                </p>

                                <div class="star-picker" role="group" aria-label="Select a star rating">
                                    <?php for ($r = 1; $r <= 5; $r++): ?>
                                    <button type="button" class="star-btn" data-val="<?= $r ?>"
                                            aria-label="<?= $r ?> star<?= $r > 1 ? 's' : '' ?>">
                                        <i class="bi bi-star-fill" aria-hidden="true"></i>
                                    </button>
                                    <?php endfor; ?>
                                </div>

                                <textarea name="body" class="form-input rate-textarea"
                                          maxlength="200" rows="2"
                                          placeholder="Share your experience (optional)"></textarea>

                                <div class="rate-form__actions">
                                    <button type="submit" class="btn btn--sm btn--accent rate-submit" disabled>
                                        Submit rating
                                    </button>
                                    <button type="button" class="btn btn--sm btn--ghost-white"
                                            onclick="toggleRateForm(<?= (int)$o['id'] ?>)">Cancel</button>
                                </div>
                            </form>
                        </td>
                    </tr>
                    <?php endif; ?>

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

<script>
function toggleRateForm(orderId) {
    var row = document.getElementById('rate-row-' + orderId);
    if (row) row.hidden = !row.hidden;
}

document.querySelectorAll('.star-picker').forEach(function (picker) {
    var form   = picker.closest('form');
    var input  = form.querySelector('.rating-val');
    var submit = form.querySelector('.rate-submit');
    var stars  = Array.prototype.slice.call(picker.querySelectorAll('.star-btn'));

    stars.forEach(function (star) {
        star.addEventListener('click', function () {
            var val = parseInt(star.dataset.val, 10);
            input.value = val;
            stars.forEach(function (s, i) { s.classList.toggle('is-active', i < val); });
            submit.disabled = false;
        });
    });

    picker.addEventListener('mouseover', function (e) {
        var star = e.target.closest('.star-btn');
        if (!star) return;
        var val = parseInt(star.dataset.val, 10);
        stars.forEach(function (s, i) { s.classList.toggle('is-hover', i < val); });
    });

    picker.addEventListener('mouseleave', function () {
        stars.forEach(function (s) { s.classList.remove('is-hover'); });
    });
});
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
