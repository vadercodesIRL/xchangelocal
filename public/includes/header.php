<?php
defined('XCHANGE') or exit;

// Language setup 
if (!isset($_SESSION['lang'])) $_SESSION['lang'] = 'en';

// Validate the stored value in case someone tampered with the session directly.
// Only 'en' and 'zu' are accepted; anything else falls back to 'en'.
$lang   = in_array($_SESSION['lang'], ['en', 'zu'], true) ? $_SESSION['lang'] : 'en';


// Each file (en.php / zu.php) returns an associative array of translation keys.
$_xlang = require __DIR__ . '/lang/' . $lang . '.php';

// t() is a global helper function used across all pages to translate a string.

if (!function_exists('t')) {
    function t(string $key): string {
        global $_xlang;
        return $_xlang[$key] ?? $key;
    }
}

// ─── Page defaults ────────────────────────────────────────────────────────────
$pageTitle = $pageTitle ?? APP_NAME . ' — Buy and sell with people near you';
$user      = function_exists('current_user') ? current_user() : null;
$assetsUrl = APP_URL . '/assets';

// Age check — user must have DOB set and be 18+ to post listings
$sellOk  = $user && function_exists('is_adult') && is_adult($user['date_of_birth'] ?? null);
$sellUrl = $sellOk
    ? APP_URL . '/account/new-listing.php'
    : APP_URL . '/account/settings.php?notice=age';
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="XchangeLocal — Buy and sell with people near you. Township-economy marketplace for South Africa.">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Stylesheets -->
    <link rel="stylesheet" href="<?= $assetsUrl ?>/css/tokens.css">
    <link rel="stylesheet" href="<?= $assetsUrl ?>/css/base.css">
    <link rel="stylesheet" href="<?= $assetsUrl ?>/css/components.css">
    <?php if (!empty($pageStylesheet)): ?>
    <link rel="stylesheet" href="<?= $assetsUrl ?>/css/pages/<?= e($pageStylesheet) ?>">
    <?php endif; ?>

    <!-- Theme script runs before <body> to prevent flash -->
    <script src="<?= $assetsUrl ?>/js/theme.js"></script>
    <script>var XL_URL = '<?= APP_URL ?>';</script>
</head>
<body>

<a href="#main-content" class="skip-link">Skip to content</a>


<header class="site-header cascade-down">
    <div class="container">
        <div class="header-inner">

            <!-- Logo -->
            <a href="<?= APP_URL ?>/" class="site-logo" aria-label="XchangeLocal home">
                <img src="<?= $assetsUrl ?>/img/logo.svg"
                     alt=""
                     width="36" height="36"
                     class="site-logo__img">
                <div class="site-logo__wordmark">
                    <span class="site-logo__name">XchangeLocal</span>
                    <span class="site-logo__tagline">A C2C Marketplace</span>
                </div>
            </a>

            <!-- Desktop nav -->
            <nav class="header-nav" aria-label="Main navigation">
                <a href="<?= APP_URL ?>/"><?= t('nav_home') ?></a>
                <a href="<?= APP_URL ?>/browse.php"><?= t('nav_browse') ?></a>
                <?php if ($user && $user['role'] === 'admin'): ?>
                <a href="<?= APP_URL ?>/admin/login.php" class="header-nav__admin">Admin</a>
                <?php endif; ?>
            </nav>

            <!-- Language + theme toggles -->
            <div class="header-toggles">
                <div class="lang-switch" aria-label="Language">
                    <a class="lang-switch__btn<?= $lang === 'en' ? ' lang-switch__btn--active' : '' ?>"
                       href="<?= APP_URL ?>/api/set-lang.php?lang=en">EN</a>
                    <a class="lang-switch__btn<?= $lang === 'zu' ? ' lang-switch__btn--active' : '' ?>"
                       href="<?= APP_URL ?>/api/set-lang.php?lang=zu">ZU</a>
                </div>
                <button class="theme-toggle"
                        id="theme-toggle"
                        aria-label="Switch to dark mode">
                    <i class="bi bi-sun icon-sun" aria-hidden="true"></i>
                    <i class="bi bi-moon-stars icon-moon" aria-hidden="true"></i>
                </button>
            </div>

            <!-- Right-side actions -->
            <?php $cartCount = count($_SESSION['cart'] ?? []); ?>
            <div class="header-actions">

                <!-- Auth — desktop only -->
                <?php if ($user): ?>
                    <a href="<?= APP_URL ?>/account/dashboard.php" class="header-user">
                        <i class="bi bi-person-circle" aria-hidden="true"></i>
                        <?= e($user['name']) ?>
                    </a>
                    <a href="<?= APP_URL ?>/account/logout.php" class="header-signout">Sign out</a>
                <?php else: ?>
                    <a href="<?= APP_URL ?>/account/login.php" class="btn btn--ghost-white btn--sm"><?= t('nav_login') ?></a>
                    <a href="<?= APP_URL ?>/admin/login.php" class="header-signout header-admin-login">
                        <i class="bi bi-shield-lock" aria-hidden="true"></i> Admin
                    </a>
                    <a href="<?= APP_URL ?>/account/register.php" class="btn btn--accent btn--sm"><?= t('nav_signup') ?></a>
                <?php endif; ?>

                <!-- Cart — far right on desktop -->
                <a href="<?= APP_URL ?>/cart.php" class="cart-btn"
                   aria-label="<?= t('nav_cart') ?><?= $cartCount > 0 ? ", $cartCount item" . ($cartCount !== 1 ? 's' : '') : '' ?>">
                    <i class="bi bi-bag" aria-hidden="true"></i>
                    <span class="cart-btn__label"><?= t('nav_cart') ?></span>
                    <?php if ($cartCount > 0): ?>
                    <span class="cart-btn__count" aria-hidden="true"><?= $cartCount ?></span>
                    <?php endif; ?>
                </a>
                
                <!-- Mobile hamburger -->
                <button class="nav-toggle"
                        id="nav-toggle"
                        aria-expanded="false"
                        aria-controls="nav-drawer"
                        aria-label="Open menu">
                    <i class="bi bi-list" aria-hidden="true"></i>
                </button>

            </div><!-- /.header-actions -->
        </div><!-- /.header-inner -->
    </div><!-- /.container -->
</header>

<!-- Mobile nav drawer -->
<nav id="nav-drawer"
     class="nav-drawer"
     aria-hidden="true"
     aria-label="Mobile navigation">
    <a href="<?= APP_URL ?>/"><?= t('nav_home') ?></a>
    <a href="<?= APP_URL ?>/browse.php"><?= t('nav_browse') ?></a>
    <a href="<?= APP_URL ?>/cart.php" class="nav-drawer__cart">
        <?= t('nav_cart') ?><?= $cartCount > 0 ? ' (' . $cartCount . ')' : '' ?>
    </a>
    <?php if ($user): ?>
        <a href="<?= APP_URL ?>/account/my-purchases.php">My orders</a>
        <?php if ($user['can_sell']): ?>
        <a href="<?= APP_URL ?>/account/my-sales.php">My sales</a>
        <a href="<?= APP_URL ?>/account/my-listings.php">My listings</a>
        <?php endif; ?>
        <a href="<?= APP_URL ?>/account/dashboard.php">Account</a>
        <a href="<?= APP_URL ?>/account/settings.php"><i class="bi bi-gear" aria-hidden="true"></i> Settings</a>
        <?php if (can_access_admin()): ?>
        <a href="<?= APP_URL ?>/admin/index.php" class="nav-drawer__admin">
            <i class="bi bi-shield-lock" aria-hidden="true"></i> Admin portal
        </a>
        <?php endif; ?>
        <a href="<?= APP_URL ?>/account/logout.php"><i class="bi bi-box-arrow-right" aria-hidden="true"></i> Sign out</a>
    <?php else: ?>
        <a href="<?= APP_URL ?>/account/login.php"><?= t('nav_login') ?></a>
        <a href="<?= APP_URL ?>/account/register.php"><?= t('nav_signup') ?></a>
    <?php endif; ?>
    <a href="<?= $sellUrl ?>" class="btn btn--accent nav-drawer__sell">
        <i class="bi bi-plus-circle" aria-hidden="true"></i> <?= t('nav_sell') ?>
    </a>
</nav>

<main id="main-content">
