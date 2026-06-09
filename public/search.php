<?php
define('XCHANGE', true);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';

$q = trim($_GET['q'] ?? '');

if ($q !== '') {
    redirect('browse.php?q=' . urlencode($q));
} else {
    redirect('browse.php');
}
