<?php
require_once '../includes/app.php';

if (!isset($_SESSION['usuario']) || ($_SESSION['usuario']['rol'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

include '../includes/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    appFlash('warning', 'La accion solicitada no es valida.', 'Operacion cancelada');
    appRedirect('productos.php');
}

if (!appValidarCsrf('admin_productos_delete', $_POST['csrf_token'] ?? null)) {
    appFlash('error', 'La sesion del formulario expiro. Intenta nuevamente.', 'Accion no valida');
    appRedirect('productos.php');
}

$productoId = (int) ($_POST['producto_id'] ?? 0);

if ($productoId <= 0) {
    appFlash('error', 'El producto solicitado no es valido.', 'No se pudo eliminar');
    appRedirect('productos.php');
}

$imagenesAEliminar = [];

try {
    $conn->beginTransaction();

    $productoStmt = $conn->prepare('SELECT nombre, imagen FROM productos WHERE id = ? LIMIT 1');
    $productoStmt->execute([$productoId]);
    $producto = $productoStmt->fetch(PDO::FETCH_ASSOC);

    if (!$producto) {
        $conn->rollBack();
        appFlash('warning', 'El producto ya no existe o fue eliminado previamente.', 'Sin cambios');
        appRedirect('productos.php');
    }

    $galeriaStmt = $conn->prepare('SELECT archivo FROM producto_imagenes WHERE producto_id = ?');
    $galeriaStmt->execute([$productoId]);
    $galeria = $galeriaStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($galeria as $imagen) {
        $imagenesAEliminar[] = (string) ($imagen['archivo'] ?? '');
    }

    $imagenesAEliminar[] = (string) ($producto['imagen'] ?? '');
    $imagenesAEliminar = array_values(array_unique(array_filter($imagenesAEliminar)));

    $conn->prepare('DELETE FROM producto_imagenes WHERE producto_id = ?')->execute([$productoId]);
    $conn->prepare('DELETE FROM producto_tallas WHERE producto_id = ?')->execute([$productoId]);

    $deleteProducto = $conn->prepare('DELETE FROM productos WHERE id = ?');
    $deleteProducto->execute([$productoId]);

    if ($deleteProducto->rowCount() !== 1) {
        throw new RuntimeException('No se pudo eliminar el producto solicitado.');
    }

    $conn->commit();

    foreach ($imagenesAEliminar as $imagen) {
        appDeleteProductImageFile(__DIR__ . '/../assets/img/productos', $imagen);
    }

    appFlash(
        'success',
        'El producto "' . (string) ($producto['nombre'] ?? 'seleccionado') . '" fue eliminado correctamente.',
        'Producto eliminado'
    );
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    appFlash(
        'error',
        'No se pudo eliminar el producto. Revisa si tiene relaciones activas con pedidos o elementos dependientes.',
        'Eliminacion bloqueada'
    );
}

appRedirect('productos.php');
