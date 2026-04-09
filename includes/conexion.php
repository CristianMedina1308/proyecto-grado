<?php

// Detecta si estás en Railway (variables reales)
$host = getenv('MYSQLHOST') ?: getenv('DB_HOST') ?: '127.0.0.1';
$db   = getenv('MYSQLDATABASE') ?: getenv('DB_NAME') ?: 'maquillaje';
$user = getenv('MYSQLUSER') ?: getenv('DB_USER') ?: 'root';
$pass = getenv('MYSQLPASSWORD') ?: getenv('DB_PASS') ?: '';
$port = getenv('MYSQLPORT') ?: 3306;

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";

    $conn = new PDO($dsn, $user, $pass);

    // Configuración recomendada
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Opcional (para debug)
    // echo "✅ Conectado a Railway";

} catch (PDOException $e) {
    die('❌ Error de conexión: ' . $e->getMessage());
}