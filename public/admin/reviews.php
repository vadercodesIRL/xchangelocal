<?php
define('XCHANGE', true);
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/csrf.php';

start_session();
if (empty($_SESSION['admin_verified'])) { redirect('admin/login.php'); }
require_role(['admin', 'moderator']);

$notice = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $reviewId = (int) ($_POST['review_id'] ?? 0);
    if ($reviewId) {
        db()->prepare('DELETE FROM reviews WHERE id = ?')->execute([$reviewId]);
        $notice = ['type' => 'success', 'msg' => 'Review deleted.'];
    }
}

$reviews = db()->query(
    "SELECT r.id, r.rating, r.body, r.created_at,
            u1.name AS reviewer_name, u1.surname AS reviewer_surname,
            u2.name AS reviewee_name, u2.surname AS reviewee_surname,
            l.title  AS listing_title, l.id AS listing_id
     FROM reviews r
     JOIN users u1  ON u1.id = r.reviewer_id
     JOIN users u2  ON u2.id = r.reviewee_id
     LEFT JOIN orders o ON o.id = r.order_id
     LEFT JOIN listings l ON l.id = o.listing_id
     ORDER BY r.created_at DESC"
)->fetchAll();

$pageTitle      = 'Reviews — Admin — ' . APP_NAME;
$pageStylesheet = 'auth.css';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<section class="dashboard-section">
    <div class="container">

        <div class="my-listings-header">
            <div>
                <a href="<?= APP_URL ?>/admin/index.php" class="back-link">&larr; Dashboard</a>
                <h1 class="section__heading" style="margin-top:var(--space-2)">All Reviews</h1>
            </div>
        </div>

        <?php if ($notice): ?>
        <div class="form-alert form-alert--<?= $notice['type'] === 'error' ? 'error' : 'success' ?>"
             role="<?= $notice['type'] === 'error' ? 'alert' : 'status' ?>">
            <?= e($notice['msg']) ?>
        </div>
        <?php endif; ?>

        <?php if (empty($reviews)): ?>
        <div class="listings-empty-state">
            <p>No reviews yet.</p>
        </div>

        <?php else: ?>
        <div class="listings-table-wrap">
            <table class="listings-table">
                <thead>
                    <tr>
                        <th>Reviewer</th>
                        <th>Seller rated</th>
                        <th>Listing</th>
                        <th>Rating</th>
                        <th>Comment</th>
                        <th>Date</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reviews as $rev): ?>
                    <tr>
                        <td><?= e($rev['reviewer_name'] . ' ' . $rev['reviewer_surname']) ?></td>
                        <td><?= e($rev['reviewee_name'] . ' ' . $rev['reviewee_surname']) ?></td>
                        <td class="listings-table__title">
                            <?php if ($rev['listing_id']): ?>
                            <a href="<?= APP_URL ?>/listing.php?id=<?= (int)$rev['listing_id'] ?>"
                               style="color:var(--color-primary)">
                                <?= e($rev['listing_title'] ?? '—') ?>
                            </a>
                            <?php else: ?>
                            <span style="color:var(--color-text-muted)">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span style="color:var(--color-accent-text)">
                                <?= str_repeat('<i class="bi bi-star-fill"></i>', (int)$rev['rating']) ?>
                                <?= str_repeat('<i class="bi bi-star"></i>', 5 - (int)$rev['rating']) ?>
                            </span>
                            <span style="font-size:var(--text-xs);color:var(--color-text-muted);margin-left:4px">
                                <?= (int)$rev['rating'] ?>/5
                            </span>
                        </td>
                        <td style="max-width:200px;white-space:normal;font-size:var(--text-sm);color:var(--color-text-muted)">
                            <?= $rev['body'] ? e(truncate($rev['body'], 80)) : '<em style="opacity:.5">No comment</em>' ?>
                        </td>
                        <td><?= e(date('d M Y', strtotime($rev['created_at']))) ?></td>
                        <td>
                            <form method="POST" action=""
                                  onsubmit="return confirm('Delete this review? Cannot be undone.')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="review_id" value="<?= (int)$rev['id'] ?>">
                                <button type="submit" class="btn btn--sm btn--danger">
                                    <i class="bi bi-trash" aria-hidden="true"></i> Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

    </div>
</section>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
