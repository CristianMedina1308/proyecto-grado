<?php

header('Content-Type: application/json; charset=utf-8');

define('DB_SKIP_CONNECT', true);
require_once __DIR__ . '/includes/conexion.php';

$config = database_config();
$connected = false;
$error = null;

try {
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $config['host'],
        $config['port'],
        $config['db']
    );

    $test = new PDO($dsn, $config['user'], $config['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 8,
    ]);
    $test->query('SELECT 1');
    $connected = true;
} catch (PDOException $e) {
    http_response_code(503);
    $error = $e->getMessage();
}

echo json_encode([
    'status' => $connected ? 'ok' : 'error',
    'connection' => $connected ? 'available' : 'failed',
    'host' => $config['host'],
    'port' => $config['port'],
    'name' => $config['db'],
    'user' => $config['user'],
    'source' => $config['source'],
    'url_source' => $config['url_source'],
    'env_present' => [
        'MYSQL_URL' => getenv('MYSQL_URL') !== false,
        'MYSQL_PRIVATE_URL' => getenv('MYSQL_PRIVATE_URL') !== false,
        'MYSQL_PUBLIC_URL' => getenv('MYSQL_PUBLIC_URL') !== false,
        'MYSQLHOST' => getenv('MYSQLHOST') !== false,
        'MYSQLDATABASE' => getenv('MYSQLDATABASE') !== false,
        'MYSQLUSER' => getenv('MYSQLUSER') !== false,
        'MYSQLPASSWORD' => getenv('MYSQLPASSWORD') !== false,
        'MYSQLPORT' => getenv('MYSQLPORT') !== false,
        'DB_HOST' => getenv('DB_HOST') !== false,
        'DB_NAME' => getenv('DB_NAME') !== false,
        'DB_USER' => getenv('DB_USER') !== false,
        'DB_PASS' => getenv('DB_PASS') !== false,
        'DB_PORT' => getenv('DB_PORT') !== false,
    ],
    'error' => $error,
], JSON_UNESCAPED_SLASHES);
