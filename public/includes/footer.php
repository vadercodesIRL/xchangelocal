<?php
defined('XCHANGE') or exit;
$assetsUrl = APP_URL . '/assets';
?>
</main><!-- /#main-content -->

<footer class="site-footer">
    <div class="container">
        <div class="footer-inner">

            <!-- Brand column -->
            <div class="footer-brand">
                <a href="<?= APP_URL ?>/" class="site-logo" aria-label="XchangeLocal home">
                    <img src="<?= $assetsUrl ?>/img/logo.svg"
                         alt="" width="32" height="32" class="site-logo__img">
                    <div class="site-logo__wordmark">
                        <span class="site-logo__name">XchangeLocal</span>
                        <span class="site-logo__tagline">A C2C Marketplace</span>
                    </div>
                </a>
                <p class="footer-tagline">
                    Buy and sell with people near you.<br>
                    Built for the township economy, by South Africans.
                </p>
            </div>

            <!-- Nav column -->
            <div>
                <p class="footer-heading">Marketplace</p>
                <ul class="footer-links">
                    <li><a href="<?= APP_URL ?>/browse.php"><i class="bi bi-grid" aria-hidden="true"></i> Browse listings</a></li>
                    <li><a href="<?= APP_URL ?>/search.php"><i class="bi bi-search" aria-hidden="true"></i> Search</a></li>
                    <li><a href="<?= APP_URL ?>/account/new-listing.php"><i class="bi bi-plus-circle" aria-hidden="true"></i> Post a listing</a></li>
                    <li><a href="<?= APP_URL ?>/account/register.php"><i class="bi bi-person-plus" aria-hidden="true"></i> Create account</a></li>
                </ul>
            </div>

            <!-- Support column -->
            <div>
                <p class="footer-heading">Support</p>
                <ul class="footer-links">
                    <li>
                        <a href="mailto:xchangelocalbusiness@outlook.com">
                            <i class="bi bi-envelope" aria-hidden="true"></i> xchangelocalbusiness@outlook.com
                        </a>
                    </li>
                    <li style="color:var(--color-text-muted);font-size:var(--text-xs);line-height:var(--leading-relaxed)">
                        For order disputes, account issues, or any questions — we're here to help. Please allow 1–2 business days for a response.
                    </li>
                </ul>
            </div>

            <!-- Toggles column -->
            <div>
                <p class="footer-heading">Preferences</p>
                <div class="footer-toggles">
                    <div class="lang-switch" aria-label="Language">
                        <a class="lang-switch__btn<?= $lang === 'en' ? ' lang-switch__btn--active' : '' ?>"
                           href="<?= APP_URL ?>/api/set-lang.php?lang=en">EN</a>
                        <a class="lang-switch__btn<?= $lang === 'zu' ? ' lang-switch__btn--active' : '' ?>"
                           href="<?= APP_URL ?>/api/set-lang.php?lang=zu">ZU</a>
                    </div>
                    <button class="theme-toggle"
                            aria-label="Switch to dark mode">
                        <i class="bi bi-sun icon-sun" aria-hidden="true"></i>
                        <i class="bi bi-moon-stars icon-moon" aria-hidden="true"></i>
                    </button>
                </div>
            </div>

        </div><!-- /.footer-inner -->

        <div class="footer-bottom">
            <span>&copy; <?= date('Y') ?> XchangeLocal. All rights reserved.</span>
            <span>Made in South Africa</span>
        </div>
    </div><!-- /.container -->
</footer>

<script src="<?= $assetsUrl ?>/js/main.js" defer></script>

</body>
</html>
