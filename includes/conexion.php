<?php

// Conexion para local (XAMPP) y despliegue (Railway).
// Las credenciales reales deben llegar por variables de entorno.

if (!function_exists('env_value')) {
    function env_value(array $names, ?string $default = null): ?string
    {
        foreach ($names as $name) {
            $value = getenv($name);
            if ($value !== false && trim((string) $value) !== '') {
                return (string) $value;
            }
        }

        return $default;
    }
}

$config = [
    'host' => '127.0.0.1',
    'db' => 'tiendaropa',
    'user' => 'root',
    'pass' => '',
    'port' => 3306,
];

// Railway puede exponer una URL privada o publica. Si existe, es la opcion mas confiable.
$databaseUrl = env_value([
    'MYSQL_URL',
    'MYSQL_PRIVATE_URL',
    'MYSQL_PUBLIC_URL',
    'DATABASE_URL',
    'DATABASE_PRIVATE_URL',
    'DATABASE_PUBLIC_URL',
]);

if ($databaseUrl !== null) {
    $parts = parse_url($databaseUrl);
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));

    if (is_array($parts) && !empty($parts['host']) && in_array($scheme, ['mysql', 'mariadb'], true)) {
        $config['host'] = (string) $parts['host'];
        $config['port'] = (int) ($parts['port'] ?? $config['port']);
        $config['user'] = rawurldecode((string) ($parts['user'] ?? $config['user']));
        $config['pass'] = rawurldecode((string) ($parts['pass'] ?? $config['pass']));

        $path = ltrim((string) ($parts['path'] ?? ''), '/');
        if ($path !== '') {
            $config['db'] = rawurldecode($path);
        }
    }
}

// Variables individuales. Se aplican despues para permitir sobreescrituras explicitas.
$config['host'] = env_value(['MYSQLHOST', 'MYSQL_HOST', 'DB_HOST'], $config['host']);
$config['db'] = env_value(['MYSQLDATABASE', 'MYSQL_DATABASE', 'DB_NAME'], $config['db']);
$config['user'] = env_value(['MYSQLUSER', 'MYSQL_USER', 'DB_USER'], $config['user']);
$config['pass'] = env_value(['MYSQLPASSWORD', 'MYSQL_PASSWORD', 'DB_PASS'], $config['pass']);
$port = env_value(['MYSQLPORT', 'MYSQL_PORT', 'DB_PORT'], (string) $config['port']);
$config['port'] = max(1, (int) $port);

try {
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $config['host'],
        $config['port'],
        $config['db']
    );

    $conn = new PDO($dsn, $config['user'], $config['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 8,
    ]);
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());

    http_response_code(503);
    die('No se pudo conectar con la base de datos. Verifica las variables de entorno de MySQL en Railway.');
}
