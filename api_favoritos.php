<?php
// Endpoint: devuelve información básica de productos a partir de una lista de IDs.
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/includes/app.php';
    require_once __DIR__ . '/includes/conexion.php';

    // Leer y validar los IDs recibidos por querystring.
    $idsParam = $_GET['ids'] ?? '';
    if (empty($idsParam)) {
        echo json_encode([]);
        exit;
    }

    $ids = array_filter(array_unique(array_map('intval', explode(',', $idsParam))));
    if (empty($ids)) {
        echo json_encode([]);
        exit;
    }

    // Placeholders para el IN (...)
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    // Consulta simple (solo lo necesario para pintar tarjetas de favoritos)
    $sql = "SELECT id, nombre, precio, imagen FROM productos WHERE id IN ($placeholders)";
    $stmt = $conn->prepare($sql);
    $stmt->execute($ids);

    // Indexar por id para luego respetar el orden original.
    $result = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row['imagen'] = appResolveProductImage($row, __DIR__ . '/assets/img/productos');
        $result[$row['id']] = $row;
    }

    // Respetar el orden original de $ids.
    $ordenado = [];
    foreach ($ids as $id) {
        if (isset($result[$id])) {
            $ordenado[] = $result[$id];
        }
    }

    echo json_encode($ordenado);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Error interno al obtener favoritos',
        'message' => $e->getMessage()
    ]);
}
