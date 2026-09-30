<?php

define('APP_ROOT', dirname(__DIR__));

if (!defined('BASE_URL')) {
    $root = str_replace('\\', '/', (string) realpath(APP_ROOT));
    $doc  = str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $base = '';
    if ($doc !== '' && stripos($root, $doc) === 0) {
        $base = rtrim(substr($root, strlen($doc)), '/');
    }
    define('BASE_URL', $base);
}

require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/includes/helpers.php';
require_once APP_ROOT . '/includes/auth.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; base-uri 'self'; form-action 'self'; frame-ancestors 'self'");

startSession();
