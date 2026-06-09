<?php
define('XCHANGE', true);
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/csrf.php';

start_session();
require_login();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('');

csrf_check();

$orderId = (int)($_POST['order_id'] ?? 0);
$action  = trim($_POST['action']   ?? '');
$back    = trim($_POST['back']     ?? '');

// Only allow known back destinations to prevent open redirect
$allowed = ['account/my-sales.php', 'account/my-purchases.php'];
if (!in_array($back, $allowed, true)) $back = 'account/dashboard.php';

if (!$orderId || !$action) {
    redirect($back);
}

$stmt = db()->prepare(
    'SELECT o.id, o.status, o.buyer_id, l.seller_id
     FROM orders o
     JOIN listings l ON l.id = o.listing_id
     WHERE o.id = ?'
);
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    redirect($back);
}

$uid      = (int)$user['id'];
$isSeller = (int)$order['seller_id'] === $uid;
$isBuyer  = (int)$order['buyer_id']  === $uid;
$status   = $order['status'];

// Work out what status to change to based on who's acting and the current status.
// If nothing matches, $newStatus stays null and we show an error.
$newStatus = null;

if ($isSeller) {
    if ($action === 'confirm'  && $status === 'pending')   $newStatus = 'confirmed';
    if ($action === 'cancel'   && $status === 'pending')   $newStatus = 'cancelled';
    if ($action === 'complete' && $status === 'confirmed') $newStatus = 'completed';
}

if ($isBuyer) {
    if ($action === 'cancel'  && $status === 'pending')    $newStatus = 'cancelled';
    if ($action === 'approve' && $status === 'completed')  $newStatus = 'approved';
    if ($action === 'dispute' && $status === 'completed')  $newStatus = 'disputed';
}

if ($newStatus) {
    $upd = db()->prepare('UPDATE orders SET status = ? WHERE id = ?');
    $upd->execute([$newStatus, $orderId]);
    $_SESSION['xl_flash'] = ['type' => 'success', 'msg' => 'Order updated successfully.'];
} else {
    $_SESSION['xl_flash'] = ['type' => 'error', 'msg' => 'That action is not allowed.'];
}

redirect($back);
