<?php

// Conexión para local (XAMPP) y despliegue (Railway).
// Importante: las credenciales no van en el código; en Railway se manejan con variables de entorno.

$host = '127.0.0.1';
$db = 'tiendaropa';
$user = 'root';
$pass = '';
$port = 3306;

// 1) Si existe una URL estilo mysql://user:pass@host:port/db, la usamos.
// En Railway suele ser la fuente más confiable (evita desalineaciones con bases distintas).
$url = getenv('MYSQL_URL') ?: getenv('DATABASE_URL') ?: '';
$hasUrl = is_string($url) && trim($url) !== '';
if ($hasUrl) {
    $parts = parse_url($url);
    if (is_array($parts) && !empty($parts['host'])) {
        $host = (string) ($parts['host'] ?? $host);
        $port = (int) ($parts['port'] ?? $port);
        $user = (string) ($parts['user'] ?? $user);
        $pass = (string) ($parts['pass'] ?? $pass);
        $path = (string) ($parts['path'] ?? '');
        $path = ltrim($path, '/');
        if ($path !== '') {
            $db = $path;
        }
    }
}

// 2) Variables típicas de Railway/servicios (con o sin guiones bajos).
// Solo se aplican cuando NO hay URL, para evitar conectarse a una base equivocada por valores por defecto.
if (!$hasUrl) {
    $host = getenv('MYSQLHOST') ?: getenv('MYSQL_HOST') ?: getenv('DB_HOST') ?: $host;
    $db   = getenv('MYSQLDATABASE') ?: getenv('MYSQL_DATABASE') ?: getenv('DB_NAME') ?: $db;
    $user = getenv('MYSQLUSER') ?: getenv('MYSQL_USER') ?: getenv('DB_USER') ?: $user;
    $pass = getenv('MYSQLPASSWORD') ?: getenv('MYSQL_PASSWORD') ?: getenv('DB_PASS') ?: $pass;
    $port = (int) (getenv('MYSQLPORT') ?: getenv('MYSQL_PORT') ?: $port);
}

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";

    $conn = new PDO($dsn, $user, $pass);

    // Ajustes básicos de PDO
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // (Debug)
    // echo "✅ Conectado a Railway";

} catch (PDOException $e) {
    die('❌ Error de conexión: ' . $e->getMessage());
}