<?php
// ── Language switcher endpoint ────────────────────────────────────────────────
// This script is called when a user clicks the EN or ZU language button.
// It receives the chosen language via the ?lang= query parameter, validates it,
// saves it into the PHP session, then redirects the user back to whatever page
// they were on. Using HTTP_REFERER means they stay on the same page — they
// just see it re-rendered in the new language.

define('XCHANGE', true);
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';

start_session();

// Only allow the two supported language codes; default to English if invalid.
$lang = $_GET['lang'] ?? 'en';
if (!in_array($lang, ['en', 'zu'], true)) $lang = 'en';

// Save the selected language in the session so every page picks it up via t().
$_SESSION['lang'] = $lang;

// Send the user back to where they came from, or the homepage if no referrer.
$back = $_SERVER['HTTP_REFERER'] ?? APP_URL . '/';
header('Location: ' . $back);
exit;
