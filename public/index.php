<?php
declare(strict_types=1);
session_start();

require __DIR__ . '/../src/db.php';
require __DIR__ . '/../src/router.php';

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$base = rtrim((require __DIR__ . '/../config.php')['app']['base_url'], '/');
$route = '/' . ltrim(substr($path, strlen($base)), '/');

route($route, $_SERVER['REQUEST_METHOD'], $pdo);
