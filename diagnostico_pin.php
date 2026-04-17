<?php
// Diagnóstico local: PIN de recuperación.
// Este archivo es SOLO para depurar en tu PC.
// En producción responde 404.

require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/conexion.php';

$remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
$isLocal = $remote === '127.0.0.1' || $remote === '::1';
if (!$isLocal) {
    http_response_code(404);
    exit;
}

header('Content-Type: text/plain; charset=UTF-8');

echo "=== Diagnóstico PIN (local) ===\n";

echo "REMOTE_ADDR: {$remote}\n";

$db = appDbCurrentDatabase($conn);
echo "DB actual: " . ($db !== '' ? $db : '[desconocida]') . "\n\n";

try {
    $cols = $conn->query('SHOW COLUMNS FROM usuarios')->fetchAll(PDO::FETCH_ASSOC);
    echo "Columnas en usuarios (" . count($cols) . "):\n";
    foreach ($cols as $c) {
        $name = (string) ($c['Field'] ?? '');
        echo "- {$name}\n";
    }
} catch (Throwable $e) {
    echo "Error leyendo columnas: " . $e->getMessage() . "\n";
}

echo "\nChequeos:\n";
echo "- recovery_pin_hash: " . (appDbHasColumn($conn, 'usuarios', 'recovery_pin_hash') ? 'SI' : 'NO') . "\n";
echo "- recovery_pin_set_at: " . (appDbHasColumn($conn, 'usuarios', 'recovery_pin_set_at') ? 'SI' : 'NO') . "\n";
echo "- pin_failed_attempts: " . (appDbHasColumn($conn, 'usuarios', 'pin_failed_attempts') ? 'SI' : 'NO') . "\n";
echo "- pin_locked_until: " . (appDbHasColumn($conn, 'usuarios', 'pin_locked_until') ? 'SI' : 'NO') . "\n";

