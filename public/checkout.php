<?php
define('XCHANGE', true);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/csrf.php';

start_session();
require_login();
$user = current_user();

$listingId = (int)($_GET['listing_id'] ?? 0);


// MODE A — Single-item fake-payment checkout  (?listing_id=X)

if ($listingId) {

    $stmt = db()->prepare(
        'SELECT l.id, l.title, l.price_cents, l.status, l.seller_id,
                l.condition, l.location_city, l.created_at,
                u.name AS seller_name
         FROM listings l
         JOIN users u ON u.id = l.seller_id
         WHERE l.id = ?'
    );
    $stmt->execute([$listingId]);
    $listing = $stmt->fetch();

    if (!$listing || $listing['status'] !== 'active') {
        redirect('listing.php?id=' . $listingId);
    }
    if ((int)$listing['seller_id'] === (int)$user['id']) {
        redirect('listing.php?id=' . $listingId);
    }

    $errors = [];
    $old    = ['card_name' => '', 'card_number' => '', 'expiry' => '', 'cvv' => ''];

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay'])) {
        csrf_check();

        $cardName   = trim($_POST['card_name']   ?? '');
        $cardNumber = preg_replace('/\s+/', '', $_POST['card_number'] ?? '');
        $expiry     = trim($_POST['expiry']       ?? '');
        $cvv        = trim($_POST['cvv']          ?? '');

        $old = [
            'card_name'   => $cardName,
            'card_number' => trim($_POST['card_number'] ?? ''),
            'expiry'      => $expiry,
            'cvv'         => $cvv,
        ];

        if ($cardName === '') {
            $errors['card_name'] = 'Name on card is required.';
        }
        if (!preg_match('/^\d{16}$/', $cardNumber)) {
            $errors['card_number'] = 'Enter a valid 16-digit card number.';
        }
        if (!preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $expiry)) {
            $errors['expiry'] = 'Enter expiry as MM/YY (e.g. 08/27).';
        }
        if (!preg_match('/^\d{3}$/', $cvv)) {
            $errors['cvv'] = 'CVV must be exactly 3 digits.';
        }

        if (empty($errors)) {
            $stmt2 = db()->prepare('SELECT status, seller_id, price_cents FROM listings WHERE id = ?');
            $stmt2->execute([$listingId]);
            $fresh = $stmt2->fetch();

            if ($fresh && $fresh['status'] === 'active' && (int)$fresh['seller_id'] !== (int)$user['id']) {
                $pc  = (int)$fresh['price_cents'];
                $com = (int) round($pc * 0.02);
                $pay = $pc - $com;
                db()->prepare(
                    'INSERT INTO orders (buyer_id, listing_id, price_cents, commission_rate, commission_cents, seller_payout_cents, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                )->execute([$user['id'], $listingId, $pc, 2.00, $com, $pay, 'pending']);
                redirect('listing.php?id=' . $listingId . '&ordered=1');
            }

            $errors['general'] = 'This listing is no longer available for purchase.';
        }
    }

    $conditionLabels = [
        'new'       => 'New',
        'like_new'  => 'Like New',
        'good'      => 'Good',
        'fair'      => 'Fair',
    ];

    $pageTitle      = 'Pay — ' . APP_NAME;
    $pageStylesheet = 'auth.css';
    require_once __DIR__ . '/includes/header.php';
    ?>

<section class="dashboard-section">
    <div class="container">

        <a href="<?= APP_URL ?>/listing.php?id=<?= $listingId ?>" class="back-link">&larr; Back to listing</a>

        <div class="payment-layout">

            <?php
            $summaryPrice = (int)$listing['price_cents'];
            $summaryFee   = (int) round($summaryPrice * 0.02);
            $summaryPay   = $summaryPrice - $summaryFee;
            ?>
            <div class="payment-summary">
                <p class="payment-summary__heading">Order summary</p>
                <div class="payment-summary__item">
                    <span class="payment-summary__title"><?= e($listing['title']) ?></span>
                    <span class="payment-summary__price"><?= format_zar($summaryPrice) ?></span>
                </div>
                <div class="payment-summary__meta">
                    <?= e($listing['location_city']) ?> &middot;
                    <?= e($conditionLabels[$listing['condition']] ?? ucfirst($listing['condition'])) ?> &middot;
                    Sold by <?= e($listing['seller_name']) ?>
                </div>
                <div class="commission-breakdown">
                    <div class="commission-row">
                        <span>Item price</span>
                        <span><?= format_zar($summaryPrice) ?></span>
                    </div>
                    <div class="commission-row commission-row--fee">
                        <span>Platform fee (2%)</span>
                        <span><?= format_zar($summaryFee) ?></span>
                    </div>
                    <div class="commission-row">
                        <span>Seller receives</span>
                        <span><?= format_zar($summaryPay) ?></span>
                    </div>
                </div>
                <div class="payment-summary__total">
                    <span>Total charged</span>
                    <span><?= format_zar($summaryPrice) ?></span>
                </div>
                <p class="commission-note">A 2% platform fee applies to all transactions on XchangeLocal</p>
            </div>

            <div class="auth-card payment-card">
                <h1 class="auth-card__heading">Payment details</h1>
                <p class="auth-card__sub">Enter your card details below to complete your order.</p>

                <?php if (isset($errors['general'])): ?>
                <div class="form-alert form-alert--error" role="alert"><?= e($errors['general']) ?></div>
                <?php endif; ?>

                <?= checkout_card_form($errors, $old, format_zar((int)$listing['price_cents'])) ?>
            </div>

        </div>
    </div>
</section>

    <?php
    checkout_card_scripts();
    require_once __DIR__ . '/includes/footer.php';
    exit;
}



// MODE B — Cart checkout (fake payment form over all cart items)

if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

$errors      = [];
$old         = ['card_name' => '', 'card_number' => '', 'expiry' => '', 'cvv' => ''];
$orderPlaced = false;
$orderCount  = 0;
$placeError  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay'])) {
    csrf_check();

    $cardName   = trim($_POST['card_name']   ?? '');
    $cardNumber = preg_replace('/\s+/', '', $_POST['card_number'] ?? '');
    $expiry     = trim($_POST['expiry']       ?? '');
    $cvv        = trim($_POST['cvv']          ?? '');

    $old = [
        'card_name'   => $cardName,
        'card_number' => trim($_POST['card_number'] ?? ''),
        'expiry'      => $expiry,
        'cvv'         => $cvv,
    ];

    if ($cardName === '') {
        $errors['card_name'] = 'Name on card is required.';
    }
    if (!preg_match('/^\d{16}$/', $cardNumber)) {
        $errors['card_number'] = 'Enter a valid 16-digit card number.';
    }
    if (!preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $expiry)) {
        $errors['expiry'] = 'Enter expiry as MM/YY (e.g. 08/27).';
    }
    if (!preg_match('/^\d{3}$/', $cvv)) {
        $errors['cvv'] = 'CVV must be exactly 3 digits.';
    }

    if (empty($errors)) {
        $ids = array_map('intval', $_SESSION['cart']);
        if (empty($ids)) redirect('cart.php');

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = db()->prepare(
            "SELECT id, title, price_cents, status, seller_id FROM listings WHERE id IN ($placeholders)"
        );
        $stmt->execute($ids);
        $toOrder = $stmt->fetchAll();

        $db = db();
        $db->beginTransaction();
        try {
            $ins = $db->prepare(
                'INSERT INTO orders (buyer_id, listing_id, price_cents, commission_rate, commission_cents, seller_payout_cents, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            foreach ($toOrder as $l) {
                if ($l['status'] !== 'active')                  continue;
                if ((int)$l['seller_id'] === (int)$user['id']) continue;
                $pc  = (int)$l['price_cents'];
                $com = (int) round($pc * 0.02);
                $pay = $pc - $com;
                $ins->execute([$user['id'], $l['id'], $pc, 2.00, $com, $pay, 'pending']);
                $orderCount++;
            }
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            $placeError = 'Something went wrong placing your orders. Please try again.';
        }

        if (!$placeError && $orderCount > 0) {
            $_SESSION['cart'] = [];
            $orderPlaced      = true;
        } elseif (!$placeError) {
            $placeError = 'No eligible items in your cart — items may have been sold or are your own listings.';
        }
    }
}

// Load cart items for display
$cartItems = [];
$cartTotal = 0;
if (!$orderPlaced && !empty($_SESSION['cart'])) {
    $ids          = array_map('intval', $_SESSION['cart']);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare(
        "SELECT l.id, l.title, l.price_cents, l.status, l.seller_id,
                u.name AS seller_name, u.surname AS seller_surname
         FROM listings l
         JOIN users u ON u.id = l.seller_id
         WHERE l.id IN ($placeholders)"
    );
    $stmt->execute($ids);
    $byId = [];
    foreach ($stmt->fetchAll() as $r) $byId[$r['id']] = $r;
    foreach ($ids as $cid) {
        if (!isset($byId[$cid])) continue;
        $item = $byId[$cid];
        $cartItems[] = $item;
        if ($item['status'] === 'active' && (int)$item['seller_id'] !== (int)$user['id']) {
            $cartTotal += (int)$item['price_cents'];
        }
    }
}

$pageTitle      = 'Checkout — ' . APP_NAME;
$pageStylesheet = 'auth.css';
require_once __DIR__ . '/includes/header.php';
?>

<section class="dashboard-section">
    <div class="container">

        <?php if ($orderPlaced): ?>
        <div class="checkout-success">
            <div class="checkout-success__icon" aria-hidden="true">&#10003;</div>
            <h1 class="section__heading">
                Order<?= $orderCount !== 1 ? 's' : '' ?> placed!
            </h1>
            <p style="color:var(--color-text-muted);margin-bottom:var(--space-8)">
                <?= $orderCount ?> order<?= $orderCount !== 1 ? 's' : '' ?> placed successfully.
                Each seller will contact you to arrange collection.
            </p>
            <div style="display:flex;gap:var(--space-3);justify-content:center;flex-wrap:wrap">
                <a href="<?= APP_URL ?>/account/my-purchases.php" class="btn btn--primary">View my purchases</a>
                <a href="<?= APP_URL ?>/browse.php" class="btn btn--ghost-white">Keep browsing</a>
            </div>
        </div>

        <?php else: ?>

        <a href="<?= APP_URL ?>/cart.php" class="back-link">&larr; Back to cart</a>

        <?php if ($placeError): ?>
        <div class="form-alert form-alert--error" role="alert"><?= e($placeError) ?></div>
        <?php endif; ?>

        <?php if (empty($cartItems)): ?>
        <div class="listings-empty-state">
            <p>Your cart is empty.</p>
            <a href="<?= APP_URL ?>/browse.php" class="btn btn--accent"
               style="margin-top:var(--space-4)">Browse listings</a>
        </div>

        <?php else: ?>

        <div class="payment-layout">

            <!-- Order summary — all cart items -->
            <div class="payment-summary">
                <p class="payment-summary__heading">Order summary</p>

                <?php foreach ($cartItems as $item):
                    $eligible = $item['status'] === 'active' && (int)$item['seller_id'] !== (int)$user['id'];
                ?>
                <div class="payment-summary__item"
                     style="<?= !$eligible ? 'opacity:0.45' : '' ?>">
                    <span class="payment-summary__title"><?= e($item['title']) ?></span>
                    <span class="payment-summary__price">
                        <?= $eligible ? format_zar((int)$item['price_cents']) : '—' ?>
                    </span>
                </div>
                <?php if (!$eligible): ?>
                <p style="font-size:var(--text-xs);color:var(--color-text-muted);margin-bottom:var(--space-2)">
                    <?= (int)$item['seller_id'] === (int)$user['id'] ? 'Your listing — skipped' : ucfirst($item['status']) . ' — skipped' ?>
                </p>
                <?php endif; ?>
                <?php endforeach; ?>

                <?php
                $cartFee    = (int) round($cartTotal * 0.02);
                $cartPayout = $cartTotal - $cartFee;
                ?>
                <div class="commission-breakdown">
                    <div class="commission-row">
                        <span>Items total</span>
                        <span><?= format_zar($cartTotal) ?></span>
                    </div>
                    <div class="commission-row commission-row--fee">
                        <span>Platform fee (2%)</span>
                        <span><?= format_zar($cartFee) ?></span>
                    </div>
                    <div class="commission-row">
                        <span>Sellers receive</span>
                        <span><?= format_zar($cartPayout) ?></span>
                    </div>
                </div>
                <div class="payment-summary__total">
                    <span>Total charged</span>
                    <span><?= format_zar($cartTotal) ?></span>
                </div>
                <p class="commission-note">A 2% platform fee applies to all transactions on XchangeLocal</p>
            </div>

            <!-- Payment form -->
            <div class="auth-card payment-card">
                <h1 class="auth-card__heading">Payment details</h1>
                <p class="auth-card__sub">Enter your card details to complete your order.</p>

                <?= checkout_card_form($errors, $old, format_zar($cartTotal)) ?>
            </div>

        </div>

        <?php endif; ?>
        <?php endif; ?>

    </div>
</section>

<?php
checkout_card_scripts();
require_once __DIR__ . '/includes/footer.php';


// Shared helper functions
// Defined down here (after output) so they don't accidentally send headers.

// Builds the fake card payment form HTML
function checkout_card_form(array $errors, array $old, string $totalLabel): string
{
    global $user;
    $csrf  = csrf_field();
    $fname = htmlspecialchars($old['card_name'],   ENT_QUOTES, 'UTF-8');
    $fnum  = htmlspecialchars($old['card_number'], ENT_QUOTES, 'UTF-8');
    $fexp  = htmlspecialchars($old['expiry'],      ENT_QUOTES, 'UTF-8');
    $fcvv  = htmlspecialchars($old['cvv'],         ENT_QUOTES, 'UTF-8');

    $eN = !empty($errors['card_name'])   ? ' form-input--error' : '';
    $eC = !empty($errors['card_number']) ? ' form-input--error' : '';
    $eE = !empty($errors['expiry'])      ? ' form-input--error' : '';
    $eV = !empty($errors['cvv'])         ? ' form-input--error' : '';

    $errN = !empty($errors['card_name'])   ? '<span class="form-error" role="alert">' . htmlspecialchars($errors['card_name'],   ENT_QUOTES, 'UTF-8') . '</span>' : '';
    $errC = !empty($errors['card_number']) ? '<span class="form-error" role="alert">' . htmlspecialchars($errors['card_number'], ENT_QUOTES, 'UTF-8') . '</span>' : '';
    $errE = !empty($errors['expiry'])      ? '<span class="form-error" role="alert">' . htmlspecialchars($errors['expiry'],      ENT_QUOTES, 'UTF-8') . '</span>' : '';
    $errV = !empty($errors['cvv'])         ? '<span class="form-error" role="alert">' . htmlspecialchars($errors['cvv'],         ENT_QUOTES, 'UTF-8') . '</span>' : '';

    return <<<HTML
<form method="POST" action="" id="payment-form" novalidate>
    {$csrf}
    <div class="form-group">
        <label class="form-label" for="card_name">Name on card</label>
        <input class="form-input{$eN}" type="text" id="card_name" name="card_name"
               value="{$fname}" placeholder="Sipho Dlamini" autocomplete="cc-name">
        {$errN}
    </div>
    <div class="form-group">
        <label class="form-label" for="card_number">Card number</label>
        <input class="form-input{$eC}" type="text" id="card_number" name="card_number"
               value="{$fnum}" placeholder="1234 5678 9012 3456"
               maxlength="19" inputmode="numeric" autocomplete="cc-number">
        {$errC}
    </div>
    <div class="form-row">
        <div class="form-group">
            <label class="form-label" for="expiry">Expiry date</label>
            <input class="form-input{$eE}" type="text" id="expiry" name="expiry"
                   value="{$fexp}" placeholder="MM/YY"
                   maxlength="5" inputmode="numeric" autocomplete="cc-exp">
            {$errE}
        </div>
        <div class="form-group">
            <label class="form-label" for="cvv">CVV</label>
            <input class="form-input{$eV}" type="text" id="cvv" name="cvv"
                   value="{$fcvv}" placeholder="123"
                   maxlength="3" inputmode="numeric" autocomplete="cc-csc">
            {$errV}
        </div>
    </div>
    <button type="submit" name="pay" value="1" class="btn btn--accent form-submit">
        <i class="bi bi-credit-card" aria-hidden="true"></i> Pay {$totalLabel} now
    </button>
    <p class="payment-card__demo-note">
        Demo payment &mdash; no real charge is made
    </p>
</form>
HTML;
}

// Outputs the JS that auto-formats card number, expiry and CVV as the user types
function checkout_card_scripts(): void
{
    echo <<<'JS'
<script>
(function () {
    var cardInput = document.getElementById('card_number');
    if (cardInput) {
        cardInput.addEventListener('input', function () {
            var digits = this.value.replace(/\D/g, '').slice(0, 16);
            var groups = digits.match(/.{1,4}/g);
            this.value = groups ? groups.join(' ') : digits;
        });
    }
    var expiryInput = document.getElementById('expiry');
    if (expiryInput) {
        expiryInput.addEventListener('input', function () {
            var digits = this.value.replace(/\D/g, '').slice(0, 4);
            if (digits.length >= 3) digits = digits.slice(0, 2) + '/' + digits.slice(2);
            this.value = digits;
        });
    }
    var cvvInput = document.getElementById('cvv');
    if (cvvInput) {
        cvvInput.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '').slice(0, 3);
        });
    }
}());
</script>
JS;
}
