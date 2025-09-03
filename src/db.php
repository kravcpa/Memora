<?php
$config = require __DIR__ . '/../config.php';
$pdo = new PDO(
  $config['db']['dsn'],
  $config['db']['user'],
  $config['db']['pass'],
  $config['db']['options']
);
