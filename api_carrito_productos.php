<?php

header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/includes/conexion.php';

    $idsParam = trim((string) ($_GET['ids'] ?? ''));

    if ($idsParam === '') {
        echo json_encode(['products' => []], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $ids = array_values(array_filter(array_unique(array_map('intval', explode(',', $idsParam))), static function (int $id): bool {
        return $id > 0;
    }));

    if (!$ids) {
        echo json_encode(['products' => []], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $productosStmt = $conn->prepare("
      SELECT id, nombre, precio
      FROM productos
      WHERE id IN ($placeholders)
    ");
    $productosStmt->execute($ids);

    $productos = [];
    foreach ($productosStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $productos[(int) $row['id']] = [
            'id' => (int) $row['id'],
            'nombre' => (string) $row['nombre'],
            'precio' => (float) $row['precio'],
            'requires_size' => false,
            'sizes' => [],
        ];
    }

    if ($productos) {
        $tallasStmt = $conn->prepare("
          SELECT
            pt.producto_id,
            t.nombre AS talla,
            pt.stock
          FROM producto_tallas pt
          INNER JOIN tallas t ON t.id = pt.talla_id
          WHERE pt.producto_id IN ($placeholders)
          ORDER BY pt.producto_id ASC, t.nombre ASC
        ");
        $tallasStmt->execute($ids);

        foreach ($tallasStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $productoId = (int) $row['producto_id'];
            if (!isset($productos[$productoId])) {
                continue;
            }

            $productos[$productoId]['requires_size'] = true;
            $productos[$productoId]['sizes'][] = [
                'name' => (string) $row['talla'],
                'stock' => (int) $row['stock'],
                'available' => (int) $row['stock'] > 0,
            ];
        }
    }

    echo json_encode([
        'products' => array_values($productos)
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'products' => [],
        'error' => 'No fue posible cargar la informacion del carrito.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
