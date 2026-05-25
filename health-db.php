<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/includes/conexion.php';

$conn->query('SELECT 1');

echo json_encode([
    'status' => 'ok',
    'database' => 'connected',
], JSON_UNESCAPED_SLASHES);
