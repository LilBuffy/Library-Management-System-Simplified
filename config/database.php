<?php

define('DB_HOST', 'localhost');
define('DB_NAME', 'FUCKYOU');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'Library Management System');
define('APP_TIMEZONE', 'Asia/Manila');
define('CURRENCY_SYMBOL', "\u{20B1}");

define('FINE_PER_DAY', 5.00);
define('BORROW_DAYS', 14);
define('MAX_LOAN_DAYS', 30);
define('MAX_ACTIVE_BORROWS', 5);
define('MIN_PASSWORD_LENGTH', 8);

date_default_timezone_set(APP_TIMEZONE);

function getDB()
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            $pdo->exec("SET time_zone = '" . date('P') . "'");
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            http_response_code(503);
            header_remove('Content-Security-Policy');
            echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
               . '<meta name="viewport" content="width=device-width, initial-scale=1">'
               . '<title>Database unavailable</title>'
               . '<style>body{font-family:Georgia,serif;background:#f3f1ec;color:#1c1c1a;margin:0;padding:4rem 1.5rem}'
               . 'main{max-width:34rem;margin:0 auto}h1{font-size:1.75rem;margin:0 0 .75rem}'
               . 'p{line-height:1.6;color:#4a4a46;margin:.5rem 0}code{background:#e6e3db;padding:.1rem .35rem;border-radius:3px}'
               . '</style></head><body><main><h1>The database is not reachable</h1>'
               . '<p>Start MySQL in XAMPP and make sure <code>' . htmlspecialchars(DB_NAME, ENT_QUOTES, 'UTF-8')
               . '</code> has been imported from <code>database.sql</code>.</p>'
               . '<p>The technical details were written to the PHP error log.</p></main></body></html>';
            exit;
        }
    }
    return $pdo;
}
