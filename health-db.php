<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/includes/conexion.php';

$conn->query('SELECT 1');

$config = database_config();

echo json_encode([
    'status' => 'ok',
    'database' => 'connected',
    'host' => $config['host'],
    'port' => $config['port'],
    'name' => $config['db'],
    'user' => $config['user'],
    'source' => $config['source'],
    'url_source' => $config['url_source'],
], JSON_UNESCAPED_SLASHES);
