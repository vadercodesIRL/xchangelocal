<?php
define('XCHANGE', true);
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/helpers.php';

start_session();
if (empty($_SESSION['admin_verified'])) { redirect('admin/login.php'); }
require_role(['admin', 'support']);

// Filter by status if provided
$allowedStatuses = ['', 'pending', 'confirmed', 'completed', 'approved', 'disputed', 'cancelled'];
$filterStatus    = in_array($_GET['status'] ?? '', $allowedStatuses, true)
                   ? ($_GET['status'] ?? '')
                   : '';

// Get all orders with buyer/seller names and commission data
$sql    = "SELECT o.id, o.price_cents, o.commission_cents, o.seller_payout_cents,
                  o.status, o.created_at,
                  CONCAT(u.name, ' ', u.surname) AS buyer_name,
                  l.title AS listing_title, l.id AS listing_id,
                  CONCAT(s.name, ' ', s.surname) AS seller_name
           FROM orders o
           JOIN users u    ON u.id = o.buyer_id
           JOIN listings l ON l.id = o.listing_id
           JOIN users s    ON s.id = l.seller_id";
$params = [];

if ($filterStatus !== '') {
    $sql     .= ' WHERE o.status = ?';
    $params[] = $filterStatus;
}
$sql .= ' ORDER BY o.created_at DESC';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Human-readable status label
function order_label(string $s): string {
    return match($s) {
        'pending'   => 'Pending',
        'confirmed' => 'Confirmed',
        'completed' => 'Completed',
        'approved'  => 'Approved',
        'disputed'  => 'Disputed',
        'cancelled' => 'Cancelled',
        default     => ucfirst($s),
    };
}

$pageTitle      = 'Transactions — Admin — ' . APP_NAME;
$pageStylesheet = 'auth.css';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<section class="dashboard-section">
    <div class="container">

        <div class="my-listings-header">
            <div>
                <a href="<?= APP_URL ?>/admin/index.php" class="back-link">&larr; Dashboard</a>
                <h1 class="section__heading" style="margin-top:var(--space-2)">All Transactions</h1>
            </div>
        </div>

        <!-- Status filter -->
        <form method="GET" action="" class="admin-filter-bar">
            <label class="form-label" style="margin:0;white-space:nowrap">Filter:</label>
            <select class="form-input" name="status" style="max-width:180px" onchange="this.form.submit()">
                <option value=""          <?= $filterStatus === ''          ? 'selected' : '' ?>>All statuses</option>
                <option value="pending"   <?= $filterStatus === 'pending'   ? 'selected' : '' ?>>Pending</option>
                <option value="confirmed" <?= $filterStatus === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                <option value="completed" <?= $filterStatus === 'completed' ? 'selected' : '' ?>>Completed</option>
                <option value="approved"  <?= $filterStatus === 'approved'  ? 'selected' : '' ?>>Approved</option>
                <option value="disputed"  <?= $filterStatus === 'disputed'  ? 'selected' : '' ?>>Disputed</option>
                <option value="cancelled" <?= $filterStatus === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
            <?php if ($filterStatus): ?>
            <a href="<?= APP_URL ?>/admin/transactions.php" class="btn btn--sm btn--ghost-white">Clear</a>
            <?php endif; ?>
        </form>

        <?php if (empty($orders)): ?>
        <div class="listings-empty-state">
            <p>No transactions found.</p>
        </div>

        <?php else: ?>
        <p style="font-size:var(--text-sm);color:var(--color-text-muted);margin-bottom:var(--space-4)">
            Showing <strong><?= count($orders) ?></strong> transaction<?= count($orders) !== 1 ? 's' : '' ?>
        </p>
        <?php
        // compute totals for footer row
        $totalVol = 0; $totalCom = 0; $totalPay = 0;
        foreach ($orders as $o) {
            $pc  = (int)$o['price_cents'];
            $com = (int)$o['commission_cents'] > 0 ? (int)$o['commission_cents'] : (int)round($pc * 0.02);
            $pay = (int)$o['seller_payout_cents'] > 0 ? (int)$o['seller_payout_cents'] : $pc - $com;
            $totalVol += $pc;
            $totalCom += $com;
            $totalPay += $pay;
        }
        ?>
        <div class="listings-table-wrap">
            <table class="listings-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Buyer</th>
                        <th>Seller</th>
                        <th>Listing</th>
                        <th>Sale price</th>
                        <th>Commission</th>
                        <th>Seller payout</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $o):
                        $pc  = (int)$o['price_cents'];
                        $com = (int)$o['commission_cents'] > 0 ? (int)$o['commission_cents'] : (int)round($pc * 0.02);
                        $pay = (int)$o['seller_payout_cents'] > 0 ? (int)$o['seller_payout_cents'] : $pc - $com;
                    ?>
                    <tr>
                        <td data-label="#" style="color:var(--color-text-muted);font-size:var(--text-xs)">#<?= (int)$o['id'] ?></td>
                        <td data-label="Buyer"><?= e($o['buyer_name']) ?></td>
                        <td data-label="Seller"><?= e($o['seller_name']) ?></td>
                        <td data-label="Listing" class="listings-table__title">
                            <a href="<?= APP_URL ?>/listing.php?id=<?= (int)$o['listing_id'] ?>"
                               style="color:var(--color-primary)">
                                <?= e($o['listing_title']) ?>
                            </a>
                        </td>
                        <td data-label="Sale price" style="color:var(--color-accent-text);font-weight:var(--weight-semibold)">
                            <?= format_zar($pc) ?>
                        </td>
                        <td data-label="Commission" style="color:var(--color-admin-text)">
                            <?= format_zar($com) ?>
                        </td>
                        <td data-label="Seller payout" style="color:var(--color-success-text)">
                            <?= format_zar($pay) ?>
                        </td>
                        <td data-label="Status">
                            <span class="status-badge order-status-badge--<?= e($o['status']) ?>">
                                <?= order_label($o['status']) ?>
                            </span>
                        </td>
                        <td data-label="Date"><?= e(date('d M Y', strtotime($o['created_at']))) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:var(--color-surface-alt);font-weight:var(--weight-semibold)">
                        <td colspan="4" style="padding:var(--space-3) var(--space-4);font-size:var(--text-xs);text-transform:uppercase;letter-spacing:0.05em;color:var(--color-text-muted)">
                            Totals (<?= count($orders) ?> orders)
                        </td>
                        <td style="padding:var(--space-3) var(--space-4);color:var(--color-accent-text)"><?= format_zar($totalVol) ?></td>
                        <td style="padding:var(--space-3) var(--space-4);color:var(--color-admin-text)"><?= format_zar($totalCom) ?></td>
                        <td style="padding:var(--space-3) var(--space-4);color:var(--color-success-text)"><?= format_zar($totalPay) ?></td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php endif; ?>

    </div>
</section>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
