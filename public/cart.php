<?php
define('XCHANGE', true);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/csrf.php';

start_session();
$user = current_user();

if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $action    = $_POST['action']     ?? '';
    $listingId = (int) ($_POST['listing_id'] ?? 0);

    // Add an item to the cart
    if ($action === 'add' && $listingId) {
        if (!$user) {
            redirect('account/login.php?next=' . urlencode('/listing.php?id=' . $listingId));
        }

        // make sure the listing is active and doesn't belong to this user
        $stmt = db()->prepare('SELECT id, status, seller_id FROM listings WHERE id = ?');
        $stmt->execute([$listingId]);
        $l = $stmt->fetch();

        if ($l && $l['status'] === 'active' && (int)$l['seller_id'] !== (int)$user['id']) {
            if (!in_array($listingId, $_SESSION['cart'])) {
                $_SESSION['cart'][] = $listingId;
            }
        }

        // go back to wherever the user came from
        $back = filter_var($_POST['back'] ?? '', FILTER_VALIDATE_URL)
              ? $_POST['back']
              : APP_URL . '/listing.php?id=' . $listingId;
        header('Location: ' . $back);
        exit;
    }

    // Remove one item from the cart
    if ($action === 'remove' && $listingId) {
        $newCart = [];
        foreach ($_SESSION['cart'] as $id) {
            if ((int)$id !== $listingId) $newCart[] = $id;
        }
        $_SESSION['cart'] = $newCart;
        $notice = 'Item removed from your cart.';
    }

    // Empty the whole cart
    if ($action === 'clear') {
        $_SESSION['cart'] = [];
    }
}

// Load the cart items from the database (preserving the order they were added)
$cartItems = [];
$cartTotal = 0;
if (!empty($_SESSION['cart'])) {
    $ids          = array_map('intval', $_SESSION['cart']);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare(
        "SELECT l.id, l.title, l.price_cents, l.status, l.seller_id, l.image_path,
                u.name AS seller_name, u.surname AS seller_surname
         FROM listings l
         JOIN users u ON u.id = l.seller_id
         WHERE l.id IN ($placeholders)"
    );
    $stmt->execute($ids);

    // index by ID so we can keep the original session order
    $byId = [];
    foreach ($stmt->fetchAll() as $r) $byId[$r['id']] = $r;

    foreach ($ids as $id) {
        if (isset($byId[$id])) {
            $cartItems[] = $byId[$id];
            $cartTotal  += (int) $byId[$id]['price_cents'];
        }
    }
}

$pageTitle      = 'Your cart — ' . APP_NAME;
$pageStylesheet = 'auth.css';
require_once __DIR__ . '/includes/header.php';
?>

<section class="dashboard-section">
    <div class="container">

        <div class="my-listings-header">
            <div>
                <a href="<?= APP_URL ?>/browse.php" class="back-link">&larr; Continue shopping</a>
                <h1 class="section__heading" style="margin-top:var(--space-2)">
                    Your cart
                    <?php if (!empty($cartItems)): ?>
                    <span style="font-size:var(--text-lg);font-weight:var(--weight-regular);color:var(--color-text-muted)">
                        (<?= count($cartItems) ?> item<?= count($cartItems) !== 1 ? 's' : '' ?>)
                    </span>
                    <?php endif; ?>
                </h1>
            </div>
        </div>

        <?php if ($notice): ?>
        <div class="form-alert form-alert--success" role="status"><?= e($notice) ?></div>
        <?php endif; ?>

        <?php if (empty($cartItems)): ?>
        <div class="listings-empty-state">
            <p>Your cart is empty.</p>
            <a href="<?= APP_URL ?>/browse.php" class="btn btn--accent" style="margin-top:var(--space-4)">Browse listings</a>
        </div>

        <?php else: ?>
        <div class="listings-table-wrap">
            <table class="listings-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Seller</th>
                        <th>Price</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cartItems as $item): ?>
                    <tr <?= $item['status'] !== 'active' ? 'style="opacity:0.5"' : '' ?>>
                        <td class="listings-table__title">
                            <a href="<?= APP_URL ?>/listing.php?id=<?= (int)$item['id'] ?>"
                               style="color:var(--color-primary)">
                                <?= e($item['title']) ?>
                            </a>
                            <?php if ($item['status'] !== 'active'): ?>
                            <span style="font-size:var(--text-xs);color:var(--color-danger-text);margin-left:var(--space-2)">
                                (<?= e(ucfirst($item['status'])) ?> — skipped at checkout)
                            </span>
                            <?php endif; ?>
                        </td>
                        <td><?= e($item['seller_name'] . ' ' . $item['seller_surname']) ?></td>
                        <td style="color:var(--color-accent-text);font-weight:var(--weight-semibold)">
                            <?= format_zar((int)$item['price_cents']) ?>
                        </td>
                        <td>
                            <form method="POST" action="">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="listing_id" value="<?= (int)$item['id'] ?>">
                                <button type="submit" class="btn btn--sm btn--danger">Remove</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="cart-summary">
            <div class="cart-summary__total">
                Total: <span><?= format_zar($cartTotal) ?></span>
            </div>
            <?php if ($user): ?>
            <a href="<?= APP_URL ?>/checkout.php" class="btn btn--accent">
                Proceed to checkout &rarr;
            </a>
            <?php else: ?>
            <a href="<?= APP_URL ?>/account/login.php?next=<?= urlencode('/checkout.php') ?>"
               class="btn btn--accent">Sign in to checkout &rarr;</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
