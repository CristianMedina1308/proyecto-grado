<?php
require_once '../includes/app.php';
if (!isset($_SESSION['usuario']) || ($_SESSION['usuario']['rol'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}
require_once '../includes/conexion.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    echo 'ID no valido.';
    exit;
}

$stmt = $conn->prepare('SELECT * FROM productos WHERE id = ?');
$stmt->execute([$id]);
$producto = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$producto) {
    echo 'Producto no encontrado.';
    exit;
}

$error = '';
$productForm = [
    'nombre' => (string) ($producto['nombre'] ?? ''),
    'sku' => (string) ($producto['sku'] ?? ''),
    'descripcion' => (string) ($producto['descripcion'] ?? ''),
    'precio' => (string) ($producto['precio'] ?? ''),
    'categoria' => (string) ($producto['categoria'] ?? ''),
    'marca' => (string) ($producto['marca'] ?? ''),
    'color' => (string) ($producto['color'] ?? ''),
    'material' => (string) ($producto['material'] ?? ''),
    'fit' => (string) ($producto['fit'] ?? 'Regular')
];

if (isset($_POST['actualizar_producto'])) {
    $productForm = [
        'nombre' => trim((string) ($_POST['nombre'] ?? '')),
        'sku' => strtoupper(trim((string) ($_POST['sku'] ?? ''))),
        'descripcion' => trim((string) ($_POST['descripcion'] ?? '')),
        'precio' => trim((string) ($_POST['precio'] ?? '')),
        'categoria' => trim((string) ($_POST['categoria'] ?? '')),
        'marca' => trim((string) ($_POST['marca'] ?? '')),
        'color' => trim((string) ($_POST['color'] ?? '')),
        'material' => trim((string) ($_POST['material'] ?? '')),
        'fit' => trim((string) ($_POST['fit'] ?? 'Regular'))
    ];
}

if (isset($_POST['actualizar_producto'])) {
    if (!appValidarCsrf('admin_producto_edit', $_POST['csrf_token'] ?? null)) {
        $error = 'La sesion del formulario expiro. Intenta nuevamente.';
    } else {
        $nombre = $productForm['nombre'];
        $sku = $productForm['sku'];
        $descripcion = $productForm['descripcion'];
        $precio = (float) ($productForm['precio'] !== '' ? $productForm['precio'] : 0);
        $categoria = $productForm['categoria'];
        $marca = $productForm['marca'];
        $color = $productForm['color'];
        $material = $productForm['material'];
        $fit = $productForm['fit'];

        if ($nombre === '' || $precio <= 0) {
            $error = 'Nombre y precio son obligatorios.';
        } else {
            if ($sku !== '') {
                $skuCheck = $conn->prepare('SELECT id FROM productos WHERE sku = ? AND id <> ? LIMIT 1');
                $skuCheck->execute([$sku, $id]);
                if ($skuCheck->fetchColumn()) {
                    $error = 'El SKU ya existe en otro producto.';
                }
            }

            $imagen = (string) ($producto['imagen'] ?? 'look-default.svg');
            $imagenAnterior = $imagen;

            if ($error === '' && !empty($_FILES['imagen']['name'])) {
                try {
                    $imagen = appStoreProductImage($_FILES['imagen'], __DIR__ . '/../assets/img/productos');
                } catch (RuntimeException $e) {
                    $error = $e->getMessage();
                }
            }

            if ($error === '') {
                $update = $conn->prepare('UPDATE productos SET nombre = ?, sku = ?, descripcion = ?, precio = ?, categoria = ?, marca = ?, color = ?, material = ?, fit = ?, imagen = ? WHERE id = ?');
                $update->execute([
                    $nombre,
                    $sku !== '' ? $sku : null,
                    $descripcion !== '' ? $descripcion : null,
                    $precio,
                    $categoria !== '' ? $categoria : null,
                    $marca !== '' ? $marca : null,
                    $color !== '' ? $color : null,
                    $material !== '' ? $material : null,
                    $fit !== '' ? $fit : null,
                    $imagen,
                    $id
                ]);

                if ($imagen !== $imagenAnterior) {
                    $galeriaActualizada = $conn->prepare('UPDATE producto_imagenes SET archivo = ? WHERE producto_id = ? AND archivo = ?');
                    $galeriaActualizada->execute([$imagen, $id, $imagenAnterior]);

                    if ($galeriaActualizada->rowCount() === 0) {
                        $existeGaleria = $conn->prepare('SELECT COUNT(*) FROM producto_imagenes WHERE producto_id = ?');
                        $existeGaleria->execute([$id]);
                        if ((int) $existeGaleria->fetchColumn() === 0) {
                            $insertGaleria = $conn->prepare('INSERT INTO producto_imagenes (producto_id, archivo) VALUES (?, ?)');
                            $insertGaleria->execute([$id, $imagen]);
                        }
                    }

                    appDeleteProductImageFile(__DIR__ . '/../assets/img/productos', $imagenAnterior);
                }

                appFlash('success', 'Producto actualizado correctamente.', 'Cambios guardados');
                appRedirect('editar_producto.php?id=' . $id);
            }
        }
    }
}

if (isset($_POST['agregar_talla'])) {
    if (!appValidarCsrf('admin_producto_add_size', $_POST['csrf_token'] ?? null)) {
        $error = 'La sesion del formulario expiro. Intenta nuevamente.';
    } else {
        $tallaId = (int) ($_POST['talla_id'] ?? 0);
        $stock = max(0, (int) ($_POST['stock'] ?? 0));

        $tallaExisteStmt = $conn->prepare('SELECT COUNT(*) FROM tallas WHERE id = ?');
        $tallaExisteStmt->execute([$tallaId]);
        $tallaExiste = (int) $tallaExisteStmt->fetchColumn() > 0;

        $check = $conn->prepare('SELECT COUNT(*) FROM producto_tallas WHERE producto_id = ? AND talla_id = ?');
        $check->execute([$id, $tallaId]);
        $duplicada = (int) $check->fetchColumn() > 0;

        if ($tallaId <= 0 || !$tallaExiste) {
            $error = 'Selecciona una talla valida.';
        } elseif ($duplicada) {
            $error = 'Esa talla ya esta asignada a este producto.';
        } else {
            $insert = $conn->prepare('INSERT INTO producto_tallas (producto_id, talla_id, stock) VALUES (?, ?, ?)');
            $insert->execute([$id, $tallaId, $stock]);
            appFlash('success', 'Talla agregada correctamente.', 'Inventario actualizado');
            appRedirect('editar_producto.php?id=' . $id);
        }
    }
}

if (isset($_POST['actualizar_stock']) && isset($_POST['stock']) && is_array($_POST['stock'])) {
    if (!appValidarCsrf('admin_producto_stock', $_POST['csrf_token'] ?? null)) {
        $error = 'La sesion del formulario expiro. Intenta nuevamente.';
    } else {
        $updStock = $conn->prepare('UPDATE producto_tallas SET stock = ? WHERE id = ? AND producto_id = ?');
        foreach ($_POST['stock'] as $ptId => $nuevoStock) {
            $updStock->execute([max(0, (int) $nuevoStock), (int) $ptId, $id]);
        }
        appFlash('success', 'Stock actualizado correctamente.', 'Inventario guardado');
        appRedirect('editar_producto.php?id=' . $id);
    }
}

if (isset($_POST['eliminar_talla_id'])) {
    if (!appValidarCsrf('admin_producto_stock', $_POST['csrf_token'] ?? null)) {
        $error = 'La sesion del formulario expiro. Intenta nuevamente.';
    } else {
        $delete = $conn->prepare('DELETE FROM producto_tallas WHERE id = ? AND producto_id = ?');
        $delete->execute([(int) $_POST['eliminar_talla_id'], $id]);
        appFlash('success', 'Talla eliminada correctamente.', 'Cambio aplicado');
        appRedirect('editar_producto.php?id=' . $id);
    }
}

$stmt = $conn->prepare('SELECT * FROM productos WHERE id = ?');
$stmt->execute([$id]);
$producto = $stmt->fetch(PDO::FETCH_ASSOC) ?: $producto;

$tallasProductoStmt = $conn->prepare('SELECT pt.id, pt.talla_id, t.nombre AS talla, pt.stock FROM producto_tallas pt JOIN tallas t ON pt.talla_id = t.id WHERE pt.producto_id = ? ORDER BY t.nombre ASC');
$tallasProductoStmt->execute([$id]);
$tallasProducto = $tallasProductoStmt->fetchAll(PDO::FETCH_ASSOC);
$todasTallas = $conn->query('SELECT * FROM tallas ORDER BY nombre ASC')->fetchAll(PDO::FETCH_ASSOC);
$tallasAsignadasIds = array_map(static fn(array $item): int => (int) $item['talla_id'], $tallasProducto);
$tallasDisponibles = array_values(array_filter($todasTallas, static fn(array $talla): bool => !in_array((int) $talla['id'], $tallasAsignadasIds, true)));

$imagenVista = trim((string) ($producto['imagen'] ?? ''));
if ($imagenVista === '' || !file_exists(__DIR__ . '/../assets/img/productos/' . $imagenVista)) {
    $imagenVista = 'look-default.svg';
}

$totalStock = 0;
$tallasAgotadas = 0;
$tallasCriticas = 0;
$tallasEstables = 0;
$topTalla = null;
$chartSizeLabels = [];
$chartSizeValues = [];
foreach ($tallasProducto as $tallaItem) {
    $stockTalla = (int) ($tallaItem['stock'] ?? 0);
    $totalStock += $stockTalla;
    $chartSizeLabels[] = (string) ($tallaItem['talla'] ?? 'Talla');
    $chartSizeValues[] = $stockTalla;
    if ($topTalla === null || $stockTalla > (int) ($topTalla['stock'] ?? -1)) {
        $topTalla = $tallaItem;
    }
    if ($stockTalla <= 0) {
        $tallasAgotadas++;
    } elseif ($stockTalla <= 2) {
        $tallasCriticas++;
    } else {
        $tallasEstables++;
    }
}

$valorInventario = (float) ($producto['precio'] ?? 0) * $totalStock;
$statusSizeLabels = ['Agotadas', 'Criticas', 'Estables'];
$statusSizeValues = [$tallasAgotadas, $tallasCriticas, $tallasEstables];

function tallaInventarioEstado(int $stock): array
{
    if ($stock <= 0) {
        return ['label' => 'Agotada', 'class' => 'is-danger'];
    }
    if ($stock <= 2) {
        return ['label' => 'Critica', 'class' => 'is-warning'];
    }
    return ['label' => 'Estable', 'class' => 'is-ok'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin | Editar producto</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <?php include '../includes/ui_head.php'; ?>
  <link rel="stylesheet" href="../assets/css/admin-panel.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="admin-body">
<?php $adminActive = 'productos'; include __DIR__ . '/partials/nav.php'; ?>
<div class="container py-4 py-lg-5">
  <div class="admin-page-header">
    <div>
      <h1 class="admin-page-title">Editar inventario</h1>
      <p class="admin-page-subtitle">Ajusta la ficha del producto, controla tallas y revisa la salud del stock sin salir del flujo de administracion.</p>
    </div>
    <div class="admin-actions">
      <a href="productos.php" class="btn btn-admin-ghost"><i class="bi bi-arrow-left me-2"></i>Volver a inventario</a>
      <button type="button" class="btn btn-admin-soft" data-bs-toggle="modal" data-bs-target="#productoAnalyticsModal"><i class="bi bi-bar-chart-line me-2"></i>Ver estadisticas</button>
      <button type="button" class="btn btn-admin-primary" id="btnDescargarProductoCsv"><i class="bi bi-download me-2"></i>Descargar CSV</button>
    </div>
  </div>

  <?php if ($error !== ''): ?>
    <div class="alert alert-danger rounded-4 border-0 shadow-sm"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div class="row g-4 mb-4">
    <div class="col-xl-4">
      <div class="admin-card p-4 h-100">
        <div class="admin-card-header">
          <div>
            <h2 class="admin-card-title"><?= htmlspecialchars((string) ($producto['nombre'] ?? 'Producto')) ?></h2>
            <p class="admin-meta mb-0"><?= htmlspecialchars((string) ($producto['marca'] ?? 'Tauro')) ?> · <?= htmlspecialchars((string) ($productForm['categoria'] !== '' ? $productForm['categoria'] : 'Sin categoria')) ?></p>
          </div>
          <span class="admin-pill"><i class="bi bi-upc-scan"></i> <?= htmlspecialchars((string) ($productForm['sku'] !== '' ? $productForm['sku'] : 'Sin SKU')) ?></span>
        </div>
        <div class="admin-product-preview mb-4">
          <img src="../assets/img/productos/<?= htmlspecialchars($imagenVista) ?>" alt="<?= htmlspecialchars((string) ($producto['nombre'] ?? 'Producto')) ?>" class="admin-preview-image">
        </div>
        <div class="admin-list">
          <div class="admin-list-item">
            <div>
              <div class="admin-list-title">Precio actual</div>
              <div class="admin-list-meta">Valor base por unidad</div>
            </div>
            <div class="fw-bold">$<?= number_format((float) ($producto['precio'] ?? 0), 0, ',', '.') ?></div>
          </div>
          <div class="admin-list-item">
            <div>
              <div class="admin-list-title">Atributos</div>
              <div class="admin-list-meta"><?= htmlspecialchars($productForm['color'] !== '' ? $productForm['color'] : 'Sin color') ?> · <?= htmlspecialchars($productForm['material'] !== '' ? $productForm['material'] : 'Sin material') ?></div>
            </div>
            <span class="admin-stat-badge is-neutral"><?= htmlspecialchars($productForm['fit'] !== '' ? $productForm['fit'] : 'Fit') ?></span>
          </div>
          <div class="admin-list-item">
            <div>
              <div class="admin-list-title">Descripcion</div>
              <div class="admin-list-meta"><?= htmlspecialchars($productForm['descripcion'] !== '' ? $productForm['descripcion'] : 'Este producto aun no tiene descripcion detallada.') ?></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-xl-8">
      <div class="row g-3">
        <div class="col-sm-6 col-xl-3"><div class="admin-card admin-kpi h-100"><div class="admin-kpi-label">Stock total</div><div class="admin-kpi-value"><?= number_format($totalStock, 0, ',', '.') ?></div><div class="admin-kpi-foot">unidades disponibles</div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="admin-card admin-kpi h-100"><div class="admin-kpi-label">Tallas activas</div><div class="admin-kpi-value"><?= count($tallasProducto) ?></div><div class="admin-kpi-foot">configuradas en inventario</div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="admin-card admin-kpi h-100"><div class="admin-kpi-label">Tallas en riesgo</div><div class="admin-kpi-value"><?= $tallasAgotadas + $tallasCriticas ?></div><div class="admin-kpi-foot">agotadas o criticas</div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="admin-card admin-kpi h-100"><div class="admin-kpi-label">Valor stock</div><div class="admin-kpi-value">$<?= number_format($valorInventario, 0, ',', '.') ?></div><div class="admin-kpi-foot">talla top: <?= htmlspecialchars((string) ($topTalla['talla'] ?? '-')) ?></div></div></div>
      </div>

      <div class="admin-card p-4 mt-4">
        <div class="admin-card-header">
          <div>
            <h2 class="admin-card-title">Informacion del producto</h2>
            <p class="admin-meta mb-0">Actualiza la ficha comercial y la imagen principal de este producto.</p>
          </div>
          <span class="admin-pill"><i class="bi bi-pencil-square"></i> Ficha</span>
        </div>

        <form method="post" enctype="multipart/form-data" class="row g-3" data-confirm="true" data-confirm-title="Guardar cambios del producto" data-confirm-message="Se actualizara la informacion general y, si cargaste una nueva imagen, se reemplazara la portada actual." data-confirm-button="Guardar cambios" data-confirm-variant="btn-primary">
          <input type="hidden" name="actualizar_producto" value="1">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(appCsrfToken('admin_producto_edit')) ?>">
          <div class="col-md-5"><label class="form-label">Nombre</label><input type="text" name="nombre" class="form-control" required value="<?= htmlspecialchars($productForm['nombre']) ?>"></div>
          <div class="col-md-2"><label class="form-label">SKU</label><input type="text" name="sku" class="form-control" value="<?= htmlspecialchars($productForm['sku']) ?>"></div>
          <div class="col-md-2"><label class="form-label">Precio</label><input type="number" name="precio" step="0.01" class="form-control" required value="<?= htmlspecialchars($productForm['precio']) ?>"></div>
          <div class="col-md-3"><label class="form-label">Categoria</label><input type="text" name="categoria" class="form-control" value="<?= htmlspecialchars($productForm['categoria']) ?>"></div>
          <div class="col-md-3"><label class="form-label">Marca</label><input type="text" name="marca" class="form-control" value="<?= htmlspecialchars($productForm['marca']) ?>"></div>
          <div class="col-md-3"><label class="form-label">Color</label><input type="text" name="color" class="form-control" value="<?= htmlspecialchars($productForm['color']) ?>"></div>
          <div class="col-md-3"><label class="form-label">Material</label><input type="text" name="material" class="form-control" value="<?= htmlspecialchars($productForm['material']) ?>"></div>
          <div class="col-md-3">
            <label class="form-label">Fit</label>
            <select name="fit" class="form-select">
              <?php foreach (['Regular', 'Slim', 'Oversize', 'Relaxed'] as $fitOpcion): ?>
                <option value="<?= htmlspecialchars($fitOpcion) ?>" <?= $productForm['fit'] === $fitOpcion ? 'selected' : '' ?>><?= htmlspecialchars($fitOpcion) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12"><label class="form-label">Descripcion</label><textarea name="descripcion" class="form-control" rows="4"><?= htmlspecialchars($productForm['descripcion']) ?></textarea></div>
          <div class="col-md-6"><label class="form-label">Imagen principal</label><input type="file" name="imagen" class="form-control" accept=".jpg,.jpeg,.png,.webp,.gif"></div>
          <div class="col-12"><button class="btn btn-admin-primary px-4">Guardar cambios</button></div>
        </form>
      </div>
    </div>
  </div>

  <div class="admin-card p-4">
    <div class="admin-card-header">
      <div>
        <h2 class="admin-card-title">Gestion de tallas y stock</h2>
        <p class="admin-meta mb-0">Agrega tallas disponibles, actualiza cantidades y elimina combinaciones que ya no se venderan.</p>
      </div>
      <div class="inventory-stack">
        <span class="admin-stat-badge is-danger">Agotadas: <?= $tallasAgotadas ?></span>
        <span class="admin-stat-badge is-warning">Criticas: <?= $tallasCriticas ?></span>
        <span class="admin-stat-badge is-ok">Estables: <?= $tallasEstables ?></span>
      </div>
    </div>

    <form method="post" class="row g-3 mb-4">
      <input type="hidden" name="agregar_talla" value="1">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(appCsrfToken('admin_producto_add_size')) ?>">
      <div class="col-md-6">
        <label class="form-label">Nueva talla</label>
        <select name="talla_id" class="form-select" <?= $tallasDisponibles ? 'required' : 'disabled' ?>>
          <option value=""><?= $tallasDisponibles ? 'Seleccionar talla' : 'No hay tallas disponibles' ?></option>
          <?php foreach ($tallasDisponibles as $tallaDisponible): ?>
            <option value="<?= (int) $tallaDisponible['id'] ?>"><?= htmlspecialchars((string) $tallaDisponible['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4"><label class="form-label">Stock inicial</label><input type="number" name="stock" class="form-control" min="0" placeholder="0" <?= $tallasDisponibles ? 'required' : 'disabled' ?>></div>
      <div class="col-md-2 d-flex align-items-end"><button class="btn btn-admin-soft w-100" <?= $tallasDisponibles ? '' : 'disabled' ?>>Agregar talla</button></div>
    </form>

    <?php if ($tallasProducto): ?>
      <form method="post" data-confirm="true" data-confirm-title="Actualizar stock" data-confirm-message="Se guardaran las cantidades actuales para este producto en todas sus tallas." data-confirm-button="Guardar stock" data-confirm-variant="btn-primary">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(appCsrfToken('admin_producto_stock')) ?>">
        <div class="table-responsive">
          <table class="table admin-table align-middle" data-datatable="true" data-no-sort="3" data-page-length="8">
            <thead><tr><th>Talla</th><th>Estado</th><th>Stock</th><th width="150">Eliminar</th></tr></thead>
            <tbody>
              <?php foreach ($tallasProducto as $tp): ?>
                <?php $metaTalla = tallaInventarioEstado((int) $tp['stock']); ?>
                <tr>
                  <td class="fw-semibold"><?= htmlspecialchars((string) $tp['talla']) ?></td>
                  <td><span class="admin-stat-badge <?= htmlspecialchars($metaTalla['class']) ?>"><?= htmlspecialchars($metaTalla['label']) ?></span></td>
                  <td><input type="number" name="stock[<?= (int) $tp['id'] ?>]" value="<?= (int) $tp['stock'] ?>" min="0" class="form-control"></td>
                  <td><button type="submit" name="eliminar_talla_id" value="<?= (int) $tp['id'] ?>" class="btn btn-admin-danger btn-sm w-100" data-confirm-title="Eliminar talla" data-confirm-message="Se eliminara esta talla del inventario de este producto." data-confirm-button="Eliminar talla" data-confirm-variant="btn-danger"><i class="bi bi-trash3 me-1"></i>Eliminar</button></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="mt-3"><button type="submit" name="actualizar_stock" value="1" class="btn btn-admin-primary">Actualizar stock</button></div>
      </form>
    <?php else: ?>
      <div class="admin-empty">Este producto aun no tiene tallas asignadas. Agrega la primera talla para activar inventario.</div>
    <?php endif; ?>
  </div>
</div>

<div class="modal fade admin-modal" id="productoAnalyticsModal" tabindex="-1" aria-labelledby="productoAnalyticsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h2 class="modal-title" id="productoAnalyticsModalLabel">Analitica del producto</h2>
          <div class="admin-meta">Detalle visual del comportamiento del stock por talla para <?= htmlspecialchars((string) ($producto['nombre'] ?? 'este producto')) ?>.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div class="chart-grid">
          <div class="admin-card-soft chart-card chart-card-lg">
            <div class="admin-card-header"><h3 class="admin-card-title">Stock por talla</h3></div>
            <?php if ($tallasProducto): ?><canvas id="chartProductoTallas"></canvas><div class="chart-actions"><button type="button" class="btn btn-admin-soft btn-sm" data-download-chart="chartProductoTallas" data-filename="producto-stock-por-talla"><i class="bi bi-image me-1"></i>Descargar PNG</button></div><?php else: ?><div class="admin-empty">No hay tallas para graficar.</div><?php endif; ?>
          </div>
          <div class="admin-card-soft chart-card">
            <div class="admin-card-header"><h3 class="admin-card-title">Estado de tallas</h3></div>
            <?php if ($tallasProducto): ?><canvas id="chartProductoEstados"></canvas><div class="chart-actions"><button type="button" class="btn btn-admin-soft btn-sm" data-download-chart="chartProductoEstados" data-filename="producto-estado-de-tallas"><i class="bi bi-image me-1"></i>Descargar PNG</button></div><?php else: ?><div class="admin-empty">No hay estado de tallas para mostrar.</div><?php endif; ?>
          </div>
          <div class="admin-card-soft chart-card">
            <div class="admin-card-header"><h3 class="admin-card-title">Participacion por talla</h3></div>
            <?php if ($tallasProducto): ?><canvas id="chartProductoParticipacion"></canvas><div class="chart-actions"><button type="button" class="btn btn-admin-soft btn-sm" data-download-chart="chartProductoParticipacion" data-filename="producto-participacion-por-talla"><i class="bi bi-image me-1"></i>Descargar PNG</button></div><?php else: ?><div class="admin-empty">No hay inventario por talla para mostrar.</div><?php endif; ?>
          </div>
          <div class="admin-card-soft chart-card chart-card-lg">
            <div class="admin-card-header"><h3 class="admin-card-title">Resumen descargable</h3></div>
            <div class="admin-empty h-100">
              <p class="mb-3">Descarga un CSV con la ficha actual del producto y el detalle completo de sus tallas.</p>
              <button type="button" class="btn btn-admin-primary" id="btnDescargarProductoCsvModal"><i class="bi bi-download me-2"></i>Descargar estadisticas CSV</button>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer justify-content-between">
        <div class="admin-meta">Stock total: <?= number_format($totalStock, 0, ',', '.') ?> · Valor inventario: $<?= number_format($valorInventario, 0, ',', '.') ?></div>
        <button type="button" class="btn btn-admin-ghost" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<script>
window.ADMIN_PRODUCT_EDIT_DATA = <?= json_encode([
    'product' => [
        'id' => $id,
        'nombre' => (string) ($producto['nombre'] ?? ''),
        'sku' => (string) ($producto['sku'] ?? ''),
        'categoria' => (string) ($producto['categoria'] ?? ''),
        'precio' => (float) ($producto['precio'] ?? 0),
        'totalStock' => $totalStock,
        'value' => round($valorInventario, 2),
        'sizesCount' => count($tallasProducto),
        'topSize' => (string) ($topTalla['talla'] ?? '')
    ],
    'sizes' => ['labels' => $chartSizeLabels, 'values' => $chartSizeValues],
    'status' => ['labels' => $statusSizeLabels, 'values' => $statusSizeValues],
    'rows' => array_map(static function (array $row): array {
        return ['talla' => (string) ($row['talla'] ?? ''), 'stock' => (int) ($row['stock'] ?? 0)];
    }, $tallasProducto)
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="../assets/js/admin-product-edit.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php
$appAssetPrefix = '../';
include '../includes/ui_footer.php';
?>
</body>
</html>
