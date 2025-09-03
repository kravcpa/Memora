<?php
declare(strict_types=1);
session_start();

require __DIR__ . '/../src/db.php';
require __DIR__ . '/../src/router.php';

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// If your app lives at /public, strip that prefix:
$base = '/public';
if (strpos($path, $base) === 0) {
    $path = substr($path, strlen($base));
}
$route = '/' . ltrim($path, '/');  // ensures '' -> '/'

route($route, $_SERVER['REQUEST_METHOD'], $pdo);
