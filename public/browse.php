<?php
define('XCHANGE', true);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

start_session();

$query = trim($_GET['q'] ?? '');

// Only allow specific sort values — never let user input go directly into SQL
$allowedSorts = [
    'newest'     => 'l.created_at DESC',
    'oldest'     => 'l.created_at ASC',
    'price_asc'  => 'l.price_cents ASC',
    'price_desc' => 'l.price_cents DESC',
];
$sortKey    = array_key_exists($_GET['sort'] ?? '', $allowedSorts) ? $_GET['sort'] : 'newest';
$sortClause = $allowedSorts[$sortKey];

// Same for condition filter
$allowedConditions = ['new', 'like_new', 'good', 'fair'];
$condFilter = in_array($_GET['condition'] ?? '', $allowedConditions, true)
              ? ($_GET['condition'] ?? '')
              : '';

// Category filter — just an int, 0 means all
$catFilter = (int) ($_GET['cat'] ?? 0);

// Load categories for the dropdown
$categories = [];
try {
    $categories = db()->query('SELECT id, name_en FROM categories ORDER BY name_en ASC')->fetchAll();
} catch (PDOException $e) {
    // categories table may not exist yet
}

// Pagination
$perPage = 12;
$page    = max(1, (int) ($_GET['page'] ?? 1));

// Build and run the search query
try {
    // Base WHERE clause params (shared between COUNT and paginated SELECT)
    $whereParams = ['active'];
    $where = 'WHERE l.status = ?';

    if ($query !== '') {
        $where       .= ' AND (l.title LIKE ? OR l.description LIKE ?)';
        $whereParams[] = '%' . $query . '%';
        $whereParams[] = '%' . $query . '%';
    }

    if ($condFilter !== '') {
        $where       .= ' AND l.`condition` = ?';
        $whereParams[] = $condFilter;
    }

    if ($catFilter > 0) {
        $where       .= ' AND l.category_id = ?';
        $whereParams[] = $catFilter;
    }

    // Total count for pagination
    $countStmt = db()->prepare("SELECT COUNT(*) FROM listings l $where");
    $countStmt->execute($whereParams);
    $totalListings = (int) $countStmt->fetchColumn();

    $totalPages = max(1, (int) ceil($totalListings / $perPage));
    $page       = min($page, $totalPages);
    $offset     = ($page - 1) * $perPage;

    // Paginated result — sortClause is from the whitelist, safe to interpolate
    $sql = "SELECT l.id, l.title, l.price_cents, l.`condition`, l.location_city, l.created_at,
                   l.image_path AS image,
                   u.name AS seller_name, u.is_verified AS seller_verified,
                   rs.avg_rating, rs.review_count
            FROM listings l
            JOIN users u ON u.id = l.seller_id
            LEFT JOIN (
                SELECT reviewee_id,
                       ROUND(AVG(rating), 1) AS avg_rating,
                       COUNT(*)              AS review_count
                FROM reviews
                GROUP BY reviewee_id
            ) rs ON rs.reviewee_id = l.seller_id
            {$where}
            ORDER BY {$sortClause}
            LIMIT {$perPage} OFFSET {$offset}";

    $stmt = db()->prepare($sql);
    $stmt->execute($whereParams);
    $listings = $stmt->fetchAll();

} catch (PDOException $e) {
    if (APP_ENV === 'development') throw $e;
    $listings     = [];
    $totalListings = 0;
    $totalPages    = 1;
}

// Build query string for pagination links (preserving all filters)
function pagination_qs(array $extras = []): string
{
    global $query, $sortKey, $condFilter, $catFilter;
    $params = [];
    if ($query      !== '')        $params['q']         = $query;
    if ($sortKey    !== 'newest')  $params['sort']      = $sortKey;
    if ($condFilter !== '')        $params['condition'] = $condFilter;
    if ($catFilter  > 0)           $params['cat']       = $catFilter;
    $params = array_merge($params, $extras);
    return $params ? '?' . http_build_query($params) : '';
}

// Generate page number list: max 5 slots with ... gaps
function pagination_pages(int $current, int $total): array
{
    if ($total <= 5) return range(1, $total);

    if ($current <= 3) return [1, 2, 3, 4, '...', $total];
    if ($current >= $total - 2) return [1, '...', $total - 3, $total - 2, $total - 1, $total];

    return [1, '...', $current - 1, $current, $current + 1, '...', $total];
}

// Find the active category name for display in the results count
$activeCatName = '';
foreach ($categories as $cat) {
    if ((int)$cat['id'] === $catFilter) {
        $activeCatName = $cat['name_en'];
        break;
    }
}

// Returns a coloured badge for a listing's condition
function condition_badge_b(string $condition): string
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

$pageTitle = $query ? 'Search: ' . $query . ' — ' . APP_NAME : 'Browse listings — ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
?>

<section class="browse-section">
    <div class="container">

        <!-- Search bar -->
        <form class="browse-search" action="<?= APP_URL ?>/browse.php" method="GET" role="search">
            <input class="browse-search__input"
                   type="search" name="q"
                   value="<?= e($query) ?>"
                   placeholder="Search listings..."
                   aria-label="Search listings"
                   autocomplete="off">
            <?php if ($sortKey !== 'newest'): ?>
            <input type="hidden" name="sort" value="<?= e($sortKey) ?>">
            <?php endif; ?>
            <?php if ($condFilter !== ''): ?>
            <input type="hidden" name="condition" value="<?= e($condFilter) ?>">
            <?php endif; ?>
            <?php if ($catFilter > 0): ?>
            <input type="hidden" name="cat" value="<?= $catFilter ?>">
            <?php endif; ?>
            <button type="submit" class="btn btn--primary"><i class="bi bi-search" aria-hidden="true"></i> Search</button>
        </form>

        <!-- Sort & filter bar -->
        <form class="browse-filter-bar" action="<?= APP_URL ?>/browse.php" method="GET" aria-label="Sort and filter">
            <?php if ($query !== ''): ?>
            <input type="hidden" name="q" value="<?= e($query) ?>">
            <?php endif; ?>

            <label class="browse-filter-bar__label" for="sort-select">Sort:</label>
            <select class="browse-filter-bar__select" id="sort-select" name="sort"
                    onchange="this.form.submit()">
                <option value="newest"     <?= $sortKey === 'newest'     ? 'selected' : '' ?>>Newest first</option>
                <option value="oldest"     <?= $sortKey === 'oldest'     ? 'selected' : '' ?>>Oldest first</option>
                <option value="price_asc"  <?= $sortKey === 'price_asc'  ? 'selected' : '' ?>>Price: low to high</option>
                <option value="price_desc" <?= $sortKey === 'price_desc' ? 'selected' : '' ?>>Price: high to low</option>
            </select>

            <label class="browse-filter-bar__label" for="cat-select">Category:</label>
            <select class="browse-filter-bar__select" id="cat-select" name="cat"
                    onchange="this.form.submit()">
                <option value="0" <?= $catFilter === 0 ? 'selected' : '' ?>>All categories</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= (int)$cat['id'] ?>" <?= $catFilter === (int)$cat['id'] ? 'selected' : '' ?>>
                    <?= e($cat['name_en']) ?>
                </option>
                <?php endforeach; ?>
            </select>

            <label class="browse-filter-bar__label" for="cond-select">Condition:</label>
            <select class="browse-filter-bar__select" id="cond-select" name="condition"
                    onchange="this.form.submit()">
                <option value=""          <?= $condFilter === ''         ? 'selected' : '' ?>>All conditions</option>
                <option value="new"       <?= $condFilter === 'new'      ? 'selected' : '' ?>>New</option>
                <option value="like_new"  <?= $condFilter === 'like_new' ? 'selected' : '' ?>>Like New</option>
                <option value="good"      <?= $condFilter === 'good'     ? 'selected' : '' ?>>Good</option>
                <option value="fair"      <?= $condFilter === 'fair'     ? 'selected' : '' ?>>Fair</option>
            </select>

            <?php if ($sortKey !== 'newest' || $condFilter !== '' || $catFilter > 0): ?>
            <a href="<?= APP_URL ?>/browse.php<?= $query !== '' ? '?q=' . urlencode($query) : '' ?>"
               class="browse-filter-bar__clear">Clear filters</a>
            <?php endif; ?>
        </form>

        <!-- Results count + heading -->
        <div class="browse-header">
            <?php if ($query): ?>
            <h1 class="section__heading">
                <?= $totalListings ?> result<?= $totalListings !== 1 ? 's' : '' ?> for
                &ldquo;<?= e($query) ?>&rdquo;
            </h1>
            <?php else: ?>
            <h1 class="section__heading"><?= $activeCatName ? e($activeCatName) : 'All listings' ?></h1>
            <?php endif; ?>
            <p class="browse-results-count">
                Showing <strong><?= count($listings) ?></strong> of <strong><?= $totalListings ?></strong> listing<?= $totalListings !== 1 ? 's' : '' ?>
                <?php if ($activeCatName): ?>
                &mdash; <?= e($activeCatName) ?>
                <?php endif; ?>
                <?php if ($condFilter !== ''): ?>
                &mdash; <?= e(ucwords(str_replace('_', ' ', $condFilter))) ?> condition
                <?php endif; ?>
            </p>
        </div>

        <!-- Listings grid -->
        <?php if ($listings): ?>
        <div class="listings-grid">
            <?php foreach ($listings as $listing): ?>
            <a href="<?= APP_URL ?>/listing.php?id=<?= (int) $listing['id'] ?>"
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
                    <span class="listing-card__price"><?= format_zar((int) $listing['price_cents']) ?></span>
                    <div class="listing-card__meta">
                        <span class="listing-card__location"><?= e($listing['location_city']) ?></span>
                        <?= condition_badge_b($listing['condition']) ?>
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
            <?php if ($query || $condFilter || $catFilter): ?>
            <p class="listings-empty__heading">Nothing found</p>
            <p>Try different filters or <a href="<?= APP_URL ?>/browse.php">browse all listings</a>.</p>
            <?php else: ?>
            <p class="listings-empty__heading">No listings yet</p>
            <p>Be the first to <a href="<?= APP_URL ?>/account/new-listing.php">post something</a>.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($totalPages > 1): ?>
        <nav class="pagination" aria-label="Browse pages">
            <?php if ($page > 1): ?>
            <a class="pagination__btn" href="<?= APP_URL ?>/browse.php<?= pagination_qs(['page' => $page - 1]) ?>" aria-label="Previous page">&lsaquo;</a>
            <?php endif; ?>

            <?php foreach (pagination_pages($page, $totalPages) as $p): ?>
                <?php if ($p === '...'): ?>
                <span class="pagination__gap">&hellip;</span>
                <?php elseif ((int)$p === $page): ?>
                <span class="pagination__page pagination__page--active" aria-current="page"><?= $p ?></span>
                <?php else: ?>
                <a class="pagination__page" href="<?= APP_URL ?>/browse.php<?= pagination_qs(['page' => (int)$p]) ?>"><?= $p ?></a>
                <?php endif; ?>
            <?php endforeach; ?>

            <?php if ($page < $totalPages): ?>
            <a class="pagination__btn" href="<?= APP_URL ?>/browse.php<?= pagination_qs(['page' => $page + 1]) ?>" aria-label="Next page">&rsaquo;</a>
            <?php endif; ?>
        </nav>
        <?php endif; ?>

    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
