<?php
require_once '../includes/app.php';
if (!isset($_SESSION['usuario']) || ($_SESSION['usuario']['rol'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

require_once '../includes/conexion.php';

// Inventario (admin):
// - Para ver todo el inventario incluyendo otros productos, usa ?todo=1
$verTodo = isset($_GET['todo']) && $_GET['todo'] === '1';

$imagesDir = __DIR__ . '/../assets/img/productos';
$catalogImages = appListCatalogImageFiles($imagesDir);

// Asegura que existan productos para las imágenes del catálogo
if (!$verTodo && $catalogImages) {
    try {
        appSeedCatalogFromImages($conn, $imagesDir);
    } catch (Throwable $e) {
        // Si falla el seed, el panel igual intenta mostrar lo que exista en el momentp
    }
}

// Accion manual: generar/actualizar el catálogo desde imágenes (camisa/saco/mochila o cualquier otra categoria).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['seed_catalogo_imagenes'])) {
    if (!appValidarCsrf('admin_productos_seed_images', $_POST['csrf_token'] ?? null)) {
        appFlash('danger', 'La sesion del formulario expiro. Intenta nuevamente.', 'No se pudo generar');
        appRedirect('productos.php');
    }

    try {
        $resumen = appSeedCatalogFromImages($conn, __DIR__ . '/../assets/img/productos');
        $msg = 'Listo: ' . (int) ($resumen['updated'] ?? 0) . ' productos actualizados y ' . (int) ($resumen['inserted'] ?? 0) . ' productos creados (imagenes detectadas: ' . (int) ($resumen['totalImages'] ?? 0) . ').';
        appFlash('success', $msg, 'Catalogo generado');
    } catch (Throwable $e) {
        appFlash('danger', 'No se pudo generar el catalogo desde imagenes.', 'Error');
    }

    appRedirect('productos.php');
}

$error = '';
$formData = [
    'nombre' => trim((string) ($_POST['nombre'] ?? '')),
    'sku' => strtoupper(trim((string) ($_POST['sku'] ?? ''))),
    'descripcion' => trim((string) ($_POST['descripcion'] ?? '')),
    'precio' => trim((string) ($_POST['precio'] ?? '')),
    'categoria' => trim((string) ($_POST['categoria'] ?? 'Moda masculina')),
    'marca' => trim((string) ($_POST['marca'] ?? 'Tauro')),
    'color' => trim((string) ($_POST['color'] ?? '')),
    'material' => trim((string) ($_POST['material'] ?? '')),
    'fit' => trim((string) ($_POST['fit'] ?? 'Regular'))
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_producto'])) {
    if (!appValidarCsrf('admin_productos_create', $_POST['csrf_token'] ?? null)) {
        $error = 'La sesion del formulario expiro. Intenta nuevamente.';
    } else {
        $nombre = $formData['nombre'];
        $sku = $formData['sku'];
        $descripcion = $formData['descripcion'];
        $precio = (float) ($formData['precio'] !== '' ? $formData['precio'] : 0);
        $categoria = $formData['categoria'];
        $marca = $formData['marca'];
        $color = $formData['color'];
        $material = $formData['material'];
        $fit = $formData['fit'];

        if ($nombre === '' || $precio <= 0) {
            $error = 'Nombre y precio son obligatorios.';
        } else {
            if ($sku !== '') {
                $checkSku = $conn->prepare('SELECT id FROM productos WHERE sku = ? LIMIT 1');
                $checkSku->execute([$sku]);

                if ($checkSku->fetchColumn()) {
                    $error = 'El SKU ya existe. Usa un SKU diferente.';
                }
            }

            if ($error === '') {
                $nombreImg = 'look-default.svg';

                if (!empty($_FILES['imagen']['name'])) {
                    try {
                        $nombreImg = appStoreProductImage($_FILES['imagen'], __DIR__ . '/../assets/img/productos');
                    } catch (RuntimeException $e) {
                        $error = $e->getMessage();
                    }
                }

                if ($error === '') {
                    $insert = $conn->prepare('
                      INSERT INTO productos (nombre, sku, descripcion, precio, categoria, marca, color, material, fit, imagen)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ');
                    $insert->execute([
                        $nombre,
                        $sku !== '' ? $sku : null,
                        $descripcion !== '' ? $descripcion : null,
                        $precio,
                        $categoria !== '' ? $categoria : null,
                        $marca !== '' ? $marca : null,
                        $color !== '' ? $color : null,
                        $material !== '' ? $material : null,
                        $fit !== '' ? $fit : null,
                        $nombreImg
                    ]);

                    $nuevoId = (int) $conn->lastInsertId();

                    if ($sku === '') {
                        $skuGenerado = 'TS-' . str_pad((string) $nuevoId, 5, '0', STR_PAD_LEFT);
                        $updSku = $conn->prepare('UPDATE productos SET sku = ? WHERE id = ?');
                        $updSku->execute([$skuGenerado, $nuevoId]);
                    }

                    $insertGaleria = $conn->prepare('INSERT INTO producto_imagenes (producto_id, archivo) VALUES (?, ?)');
                    $insertGaleria->execute([$nuevoId, $nombreImg]);

                    appFlash('success', 'Producto creado correctamente.', 'Producto guardado');
                    appRedirect('productos.php');
                }
            }
        }
    }
}

$productosSql = '
  SELECT
    p.*,
    COALESCE(inv.stock_total, 0) AS stock_total,
    COALESCE(inv.tallas_activas, 0) AS tallas_activas,
    COALESCE(inv.tallas_agotadas, 0) AS tallas_agotadas
  FROM productos p
  LEFT JOIN (
    SELECT
      producto_id,
      SUM(stock) AS stock_total,
      COUNT(*) AS tallas_activas,
      SUM(CASE WHEN stock <= 0 THEN 1 ELSE 0 END) AS tallas_agotadas
    FROM producto_tallas
    GROUP BY producto_id
  ) inv ON inv.producto_id = p.id
  ';

if (!$verTodo && $catalogImages) {
    $placeholders = implode(',', array_fill(0, count($catalogImages), '?'));
    $productosSql .= " WHERE p.imagen IN ($placeholders)";
}

$productosSql .= ' ORDER BY p.id DESC';

if (!$verTodo && $catalogImages) {
    $stmt = $conn->prepare($productosSql);
    $stmt->execute($catalogImages);
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $productos = $conn->query($productosSql)->fetchAll(PDO::FETCH_ASSOC);
}

$categoriasStats = $conn->query('
  SELECT
    COALESCE(NULLIF(TRIM(p.categoria), \'\'), \'Sin categoria\') AS categoria,
    COALESCE(SUM(pt.stock), 0) AS unidades,
    COALESCE(SUM(pt.stock * p.precio), 0) AS valor
  FROM productos p
  LEFT JOIN producto_tallas pt ON pt.producto_id = p.id
  GROUP BY COALESCE(NULLIF(TRIM(p.categoria), \'\'), \'Sin categoria\')
  ORDER BY unidades DESC, categoria ASC
')->fetchAll(PDO::FETCH_ASSOC);

$tallasStats = $conn->query('
  SELECT
    t.nombre AS talla,
    COALESCE(SUM(pt.stock), 0) AS unidades
  FROM tallas t
  LEFT JOIN producto_tallas pt ON pt.talla_id = t.id
  GROUP BY t.id, t.nombre
  ORDER BY unidades DESC, t.nombre ASC
')->fetchAll(PDO::FETCH_ASSOC);

$totalProductos = count($productos);
$totalUnidades = 0;
$valorInventario = 0.0;
$stockCritico = 0;
$sinStock = 0;
$saludables = 0;
$productosCriticos = [];

foreach ($productos as $productoItem) {
    $stockTotal = (int) ($productoItem['stock_total'] ?? 0);
    $precio = (float) ($productoItem['precio'] ?? 0);

    $totalUnidades += $stockTotal;
    $valorInventario += $precio * $stockTotal;

    if ($stockTotal <= 0) {
        $sinStock++;
    } elseif ($stockTotal <= 5) {
        $stockCritico++;
        $productosCriticos[] = $productoItem;
    } else {
        $saludables++;
    }
}

usort($productosCriticos, static function (array $a, array $b): int {
    $stockCompare = (int) ($a['stock_total'] ?? 0) <=> (int) ($b['stock_total'] ?? 0);

    if ($stockCompare !== 0) {
        return $stockCompare;
    }

    return strcmp((string) ($a['nombre'] ?? ''), (string) ($b['nombre'] ?? ''));
});

$productosCriticos = array_slice($productosCriticos, 0, 8);
$promedioStock = $totalProductos > 0 ? ($totalUnidades / $totalProductos) : 0;
$categoriaTop = $categoriasStats[0] ?? null;

$statusDistribution = [
    ['label' => 'Sin stock', 'value' => $sinStock],
    ['label' => 'Critico', 'value' => $stockCritico],
    ['label' => 'Saludable', 'value' => $saludables]
];

$categoryLabels = [];
$categoryUnits = [];
$categoryValues = [];
foreach ($categoriasStats as $categoriaRow) {
    $categoryLabels[] = (string) ($categoriaRow['categoria'] ?? 'Sin categoria');
    $categoryUnits[] = (int) ($categoriaRow['unidades'] ?? 0);
    $categoryValues[] = round((float) ($categoriaRow['valor'] ?? 0), 2);
}

$sizeLabels = [];
$sizeUnits = [];
foreach ($tallasStats as $tallaRow) {
    if ((int) ($tallaRow['unidades'] ?? 0) <= 0) {
        continue;
    }

    $sizeLabels[] = (string) ($tallaRow['talla'] ?? 'Talla');
    $sizeUnits[] = (int) ($tallaRow['unidades'] ?? 0);
}

$criticalLabels = [];
$criticalStockData = [];
foreach ($productosCriticos as $critico) {
    $criticalLabels[] = (string) ($critico['nombre'] ?? 'Producto');
    $criticalStockData[] = (int) ($critico['stock_total'] ?? 0);
}

function inventarioEstadoMeta(int $stockTotal): array
{
    if ($stockTotal <= 0) {
        return ['label' => 'Sin stock', 'class' => 'is-danger'];
    }

    if ($stockTotal <= 5) {
        return ['label' => 'Critico', 'class' => 'is-warning'];
    }

    return ['label' => 'Saludable', 'class' => 'is-ok'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin | Inventario Tauro</title>
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
      <h1 class="admin-page-title">Centro de inventario</h1>
      <p class="admin-page-subtitle">
        Controla stock, salud del catalogo y estadisticas operativas desde un solo lugar,
        con una vista mas clara para detectar productos criticos antes de que afecten ventas.
      </p>
    </div>
    <div class="admin-actions">
      <button class="btn btn-admin-primary" type="button" data-bs-toggle="collapse" data-bs-target="#panelNuevoProducto" aria-expanded="<?= $error !== '' ? 'true' : 'false' ?>" aria-controls="panelNuevoProducto">
        <i class="bi bi-plus-circle me-2"></i>Nuevo producto
      </button>
      <button class="btn btn-admin-soft" type="button" data-bs-toggle="modal" data-bs-target="#inventarioModal">
        <i class="bi bi-bar-chart-line me-2"></i>Ver estadisticas
      </button>
      <button class="btn btn-admin-ghost" type="button" id="btnDescargarInventarioCsv">
        <i class="bi bi-download me-2"></i>Descargar CSV
      </button>
    </div>
  </div>

  <?php if ($error !== ''): ?>
    <div class="alert alert-danger rounded-4 border-0 shadow-sm"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div class="admin-kpi-grid mb-4">
    <div class="admin-card admin-kpi">
      <div class="admin-kpi-label">Productos</div>
      <div class="admin-kpi-value"><?= $totalProductos ?></div>
      <div class="admin-kpi-foot">referencias activas en catalogo</div>
    </div>
    <div class="admin-card admin-kpi">
      <div class="admin-kpi-label">Unidades disponibles</div>
      <div class="admin-kpi-value"><?= number_format($totalUnidades, 0, ',', '.') ?></div>
      <div class="admin-kpi-foot">stock total consolidado</div>
    </div>
    <div class="admin-card admin-kpi">
      <div class="admin-kpi-label">Stock critico</div>
      <div class="admin-kpi-value"><?= $stockCritico ?></div>
      <div class="admin-kpi-foot">productos con 1 a 5 unidades</div>
    </div>
    <div class="admin-card admin-kpi">
      <div class="admin-kpi-label">Sin stock</div>
      <div class="admin-kpi-value"><?= $sinStock ?></div>
      <div class="admin-kpi-foot">productos agotados</div>
    </div>
    <div class="admin-card admin-kpi">
      <div class="admin-kpi-label">Valor inventario</div>
      <div class="admin-kpi-value">$<?= number_format($valorInventario, 0, ',', '.') ?></div>
      <div class="admin-kpi-foot">promedio por producto: <?= number_format($promedioStock, 1, ',', '.') ?> und.</div>
    </div>
  </div>

  <div class="row g-4 mb-4">
    <div class="col-lg-5">
      <div class="admin-card p-4 h-100">
        <div class="admin-card-header">
          <div>
            <h2 class="admin-card-title">Alertas inmediatas</h2>
            <p class="admin-meta mb-0">Productos que requieren accion rapida para no afectar conversion.</p>
          </div>
          <div class="d-flex flex-column align-items-end gap-2">
            <form method="post" class="d-inline">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(appCsrfToken('admin_productos_seed_images')) ?>">
              <button type="submit" name="seed_catalogo_imagenes" value="1" class="btn btn-admin-soft">
                <i class="bi bi-images me-2"></i>Generar productos desde imagenes
              </button>
            </form>
            <div class="inventory-stack">
              <span class="admin-stat-badge is-danger">Sin stock: <?= $sinStock ?></span>
              <span class="admin-stat-badge is-warning">Criticos: <?= $stockCritico ?></span>
              <span class="admin-stat-badge is-ok">Saludables: <?= $saludables ?></span>
            </div>
          </div>
        </div>

        <?php if ($productosCriticos): ?>
          <div class="admin-list mt-3">
            <?php foreach ($productosCriticos as $critico): ?>
              <div class="admin-list-item">
                <div>
                  <div class="admin-list-title"><?= htmlspecialchars((string) $critico['nombre']) ?></div>
                  <div class="admin-list-meta"><?= htmlspecialchars((string) ($critico['sku'] ?? 'Sin SKU')) ?> · <?= htmlspecialchars((string) ($critico['categoria'] ?? 'Sin categoria')) ?></div>
                </div>
                <div class="text-end">
                  <span class="admin-stat-badge <?= inventarioEstadoMeta((int) ($critico['stock_total'] ?? 0))['class'] ?>">
                    <?= number_format((int) ($critico['stock_total'] ?? 0), 0, ',', '.') ?> und.
                  </span>
                  <div class="mt-2">
                    <a href="editar_producto.php?id=<?= (int) $critico['id'] ?>" class="btn btn-admin-ghost btn-sm">Ajustar</a>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="admin-empty mt-3">No hay productos en alerta por ahora. El inventario se ve estable.</div>
        <?php endif; ?>
      </div>
    </div>

    <div class="col-lg-7">
      <div class="admin-card p-4 h-100">
        <div class="admin-card-header">
          <div>
            <h2 class="admin-card-title">Balance por categoria</h2>
            <p class="admin-meta mb-0">Resumen rapido para identificar que lineas sostienen el inventario.</p>
          </div>
          <?php if ($categoriaTop): ?>
            <span class="admin-pill"><i class="bi bi-stars"></i> Top: <?= htmlspecialchars((string) $categoriaTop['categoria']) ?></span>
          <?php endif; ?>
        </div>

        <?php if ($categoriasStats): ?>
          <div class="table-responsive">
            <table class="table admin-table align-middle mb-0">
              <thead>
                <tr>
                  <th>Categoria</th>
                  <th>Unidades</th>
                  <th>Valor</th>
                  <th>Participacion</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($categoriasStats as $categoriaRow): ?>
                  <?php
                    $unidadesCategoria = (int) ($categoriaRow['unidades'] ?? 0);
                    $participacion = $totalUnidades > 0 ? ($unidadesCategoria / $totalUnidades) * 100 : 0;
                  ?>
                  <tr>
                    <td class="fw-semibold"><?= htmlspecialchars((string) $categoriaRow['categoria']) ?></td>
                    <td><?= number_format($unidadesCategoria, 0, ',', '.') ?></td>
                    <td class="fw-semibold">$<?= number_format((float) ($categoriaRow['valor'] ?? 0), 0, ',', '.') ?></td>
                    <td>
                      <div class="fw-semibold mb-2"><?= number_format($participacion, 1, ',', '.') ?>%</div>
                      <div class="inventory-meter"><span style="width: <?= min(100, max(5, $participacion)) ?>%"></span></div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="admin-empty">Todavia no hay categorias con stock disponible para resumir.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="collapse <?= $error !== '' ? 'show' : '' ?>" id="panelNuevoProducto">
    <div class="admin-card p-4 p-lg-4 mb-4">
      <div class="admin-card-header">
        <div>
          <h2 class="admin-card-title">Agregar nuevo producto</h2>
          <p class="admin-meta mb-0">Completa la ficha base del producto. Luego puedes afinar tallas y stock desde su edición.</p>
        </div>
        <span class="admin-pill"><i class="bi bi-box-seam"></i> Alta de catalogo</span>
      </div>

      <form method="post" enctype="multipart/form-data" class="row g-3">
        <input type="hidden" name="crear_producto" value="1">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(appCsrfToken('admin_productos_create')) ?>">

        <div class="col-md-4">
          <label class="form-label">Nombre</label>
          <input type="text" name="nombre" class="form-control" required value="<?= htmlspecialchars($formData['nombre']) ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label">SKU</label>
          <input type="text" name="sku" class="form-control" placeholder="TS-00039" value="<?= htmlspecialchars($formData['sku']) ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label">Precio</label>
          <input type="number" name="precio" step="0.01" class="form-control" required value="<?= htmlspecialchars($formData['precio']) ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label">Categoria</label>
          <input type="text" name="categoria" class="form-control" value="<?= htmlspecialchars($formData['categoria']) ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label">Fit</label>
          <select name="fit" class="form-select">
            <?php foreach (['Regular', 'Slim', 'Oversize', 'Relaxed'] as $fitOption): ?>
              <option value="<?= htmlspecialchars($fitOption) ?>" <?= $formData['fit'] === $fitOption ? 'selected' : '' ?>>
                <?= htmlspecialchars($fitOption) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Marca</label>
          <input type="text" name="marca" class="form-control" value="<?= htmlspecialchars($formData['marca']) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Color</label>
          <input type="text" name="color" class="form-control" placeholder="Negro" value="<?= htmlspecialchars($formData['color']) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Material</label>
          <input type="text" name="material" class="form-control" placeholder="Algodon premium" value="<?= htmlspecialchars($formData['material']) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Imagen principal</label>
          <input type="file" name="imagen" class="form-control" accept=".jpg,.jpeg,.png,.webp,.gif">
        </div>

        <div class="col-12">
          <label class="form-label">Descripcion</label>
          <textarea name="descripcion" class="form-control" rows="3" placeholder="Describe corte, tela y uso recomendado..."><?= htmlspecialchars($formData['descripcion']) ?></textarea>
        </div>

        <div class="col-12">
          <button type="submit" class="btn btn-admin-primary px-4">Guardar producto</button>
        </div>
      </form>
    </div>
  </div>

  <div class="admin-card p-4 p-lg-4">
    <div class="admin-card-header">
      <div>
        <h2 class="admin-card-title">Inventario del catalogo</h2>
        <p class="admin-meta mb-0">Consulta stock, tallas activas y valor por referencia. Usa la tabla para filtrar o detectar productos sensibles.</p>
      </div>
      <div class="inventory-stack">
        <span class="admin-stat-badge is-danger">Sin stock: <?= $sinStock ?></span>
        <span class="admin-stat-badge is-warning">Criticos: <?= $stockCritico ?></span>
        <span class="admin-stat-badge is-ok">Saludables: <?= $saludables ?></span>
      </div>
    </div>

    <div class="table-responsive">
      <table id="tablaInventario" class="table admin-table align-middle" data-datatable="true" data-no-sort="1,9,10" data-page-length="10">
        <thead>
          <tr>
            <th>ID</th>
            <th>Imagen</th>
            <th>Producto</th>
            <th>SKU</th>
            <th>Categoria</th>
            <th>Precio</th>
            <th>Stock total</th>
            <th>Tallas</th>
            <th>Valor stock</th>
            <th>Estado</th>
            <th width="220">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($productos): ?>
            <?php foreach ($productos as $productoItem): ?>
              <?php
                $img = appResolveProductImage($productoItem, __DIR__ . '/../assets/img/productos');

                $stockTotal = (int) ($productoItem['stock_total'] ?? 0);
                $tallasActivas = (int) ($productoItem['tallas_activas'] ?? 0);
                $stockMeta = inventarioEstadoMeta($stockTotal);
                $stockRatio = $totalUnidades > 0 ? min(100, max(5, ($stockTotal / $totalUnidades) * 100)) : 5;
                $valorStock = (float) ($productoItem['precio'] ?? 0) * $stockTotal;
              ?>
              <tr>
                <td><?= (int) $productoItem['id'] ?></td>
                <td>
                  <img src="../assets/img/productos/<?= htmlspecialchars($img) ?>"
                       alt="<?= htmlspecialchars((string) $productoItem['nombre']) ?>"
                       class="admin-thumb"
                       onerror="this.onerror=null;this.src='../assets/img/productos/look-default.svg';">
                </td>
                <td>
                  <div class="fw-bold"><?= htmlspecialchars((string) $productoItem['nombre']) ?></div>
                  <div class="admin-meta"><?= htmlspecialchars((string) ($productoItem['marca'] ?? 'Tauro')) ?> · <?= htmlspecialchars((string) ($productoItem['color'] ?? 'Sin color')) ?></div>
                </td>
                <td><span class="admin-pill"><?= htmlspecialchars((string) ($productoItem['sku'] ?? '-')) ?></span></td>
                <td><?= htmlspecialchars((string) ($productoItem['categoria'] ?? 'Sin categoria')) ?></td>
                <td class="fw-bold">$<?= number_format((float) $productoItem['precio'], 0, ',', '.') ?></td>
                <td>
                  <div class="fw-bold mb-2"><?= number_format($stockTotal, 0, ',', '.') ?> und.</div>
                  <div class="inventory-meter"><span style="width: <?= $stockRatio ?>%"></span></div>
                </td>
                <td>
                  <div class="fw-bold"><?= $tallasActivas ?></div>
                  <div class="admin-meta">agotadas: <?= (int) ($productoItem['tallas_agotadas'] ?? 0) ?></div>
                </td>
                <td class="fw-bold">$<?= number_format($valorStock, 0, ',', '.') ?></td>
                <td>
                  <span class="admin-stat-badge <?= htmlspecialchars($stockMeta['class']) ?>">
                    <?= htmlspecialchars($stockMeta['label']) ?>
                  </span>
                </td>
                <td>
                  <div class="dt-actions">
                    <a href="editar_producto.php?id=<?= (int) $productoItem['id'] ?>" class="btn btn-admin-primary btn-sm">
                      <i class="bi bi-pencil-square me-1"></i>Editar
                    </a>
                    <form method="post"
                          action="eliminar_producto.php"
                          class="d-inline"
                          data-confirm="true"
                          data-confirm-title="Eliminar producto"
                          data-confirm-message="Se eliminara este producto del catalogo. Si tiene relaciones activas, la operacion puede fallar para proteger la integridad de los pedidos."
                          data-confirm-button="Eliminar"
                          data-confirm-variant="btn-danger">
                      <input type="hidden" name="producto_id" value="<?= (int) $productoItem['id'] ?>">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(appCsrfToken('admin_productos_delete')) ?>">
                      <button type="submit" class="btn btn-admin-danger btn-sm">
                        <i class="bi bi-trash3 me-1"></i>Eliminar
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="11">
                <div class="admin-empty text-center">No hay productos registrados en el inventario.</div>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="modal fade admin-modal" id="inventarioModal" tabindex="-1" aria-labelledby="inventarioModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h2 class="modal-title" id="inventarioModalLabel">Estadisticas de inventario</h2>
          <div class="admin-meta">Vista ampliada del estado del catalogo con descargas listas para equipo operativo.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div class="chart-grid">
          <div class="admin-card-soft chart-card chart-card-lg">
            <div class="admin-card-header"><h3 class="admin-card-title">Unidades por categoria</h3></div>
            <canvas id="chartCategoriaUnidades"></canvas>
            <div class="chart-actions">
              <button type="button" class="btn btn-admin-soft btn-sm" data-download-chart="chartCategoriaUnidades" data-filename="inventario-unidades-por-categoria"><i class="bi bi-image me-1"></i>Descargar PNG</button>
            </div>
          </div>
          <div class="admin-card-soft chart-card chart-card-lg">
            <div class="admin-card-header"><h3 class="admin-card-title">Valor por categoria</h3></div>
            <canvas id="chartCategoriaValor"></canvas>
            <div class="chart-actions">
              <button type="button" class="btn btn-admin-soft btn-sm" data-download-chart="chartCategoriaValor" data-filename="inventario-valor-por-categoria"><i class="bi bi-image me-1"></i>Descargar PNG</button>
            </div>
          </div>
          <div class="admin-card-soft chart-card">
            <div class="admin-card-header"><h3 class="admin-card-title">Distribucion de salud</h3></div>
            <canvas id="chartEstadoInventario"></canvas>
            <div class="chart-actions">
              <button type="button" class="btn btn-admin-soft btn-sm" data-download-chart="chartEstadoInventario" data-filename="inventario-estado-general"><i class="bi bi-image me-1"></i>Descargar PNG</button>
            </div>
          </div>
          <div class="admin-card-soft chart-card">
            <div class="admin-card-header"><h3 class="admin-card-title">Tallas con mas stock</h3></div>
            <canvas id="chartTallasStock"></canvas>
            <div class="chart-actions">
              <button type="button" class="btn btn-admin-soft btn-sm" data-download-chart="chartTallasStock" data-filename="inventario-stock-por-talla"><i class="bi bi-image me-1"></i>Descargar PNG</button>
            </div>
          </div>
          <div class="admin-card-soft chart-card chart-card-lg">
            <div class="admin-card-header"><h3 class="admin-card-title">Productos criticos</h3></div>
            <?php if ($productosCriticos): ?>
              <canvas id="chartProductosCriticos"></canvas>
              <div class="chart-actions">
                <button type="button" class="btn btn-admin-soft btn-sm" data-download-chart="chartProductosCriticos" data-filename="inventario-productos-criticos"><i class="bi bi-image me-1"></i>Descargar PNG</button>
              </div>
            <?php else: ?>
              <div class="admin-empty">No hay productos en estado critico ahora mismo.</div>
            <?php endif; ?>
          </div>
          <div class="admin-card-soft chart-card chart-card-lg">
            <div class="admin-card-header"><h3 class="admin-card-title">Resumen descargable</h3></div>
            <div class="admin-empty h-100">
              <p class="mb-3">Puedes descargar un CSV con resumen general, categorias, tallas y detalle de productos para analisis externo o respaldo operativo.</p>
              <button type="button" class="btn btn-admin-primary" id="btnDescargarInventarioCsvModal">
                <i class="bi bi-download me-2"></i>Descargar estadisticas CSV
              </button>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer justify-content-between">
        <div class="admin-meta">Total unidades: <?= number_format($totalUnidades, 0, ',', '.') ?> · Valor inventario: $<?= number_format($valorInventario, 0, ',', '.') ?></div>
        <button type="button" class="btn btn-admin-ghost" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<script>
const inventoryStats = {
  summary: {
    totalProducts: <?= json_encode($totalProductos) ?>,
    totalUnits: <?= json_encode($totalUnidades) ?>,
    critical: <?= json_encode($stockCritico) ?>,
    empty: <?= json_encode($sinStock) ?>,
    healthy: <?= json_encode($saludables) ?>,
    inventoryValue: <?= json_encode(round($valorInventario, 2)) ?>,
    averageStock: <?= json_encode(round($promedioStock, 2)) ?>
  },
  categories: {
    labels: <?= json_encode($categoryLabels, JSON_UNESCAPED_UNICODE) ?>,
    units: <?= json_encode($categoryUnits) ?>,
    values: <?= json_encode($categoryValues) ?>
  },
  sizes: {
    labels: <?= json_encode($sizeLabels, JSON_UNESCAPED_UNICODE) ?>,
    units: <?= json_encode($sizeUnits) ?>
  },
  status: {
    labels: <?= json_encode(array_column($statusDistribution, 'label'), JSON_UNESCAPED_UNICODE) ?>,
    values: <?= json_encode(array_column($statusDistribution, 'value')) ?>
  },
  criticalProducts: {
    labels: <?= json_encode($criticalLabels, JSON_UNESCAPED_UNICODE) ?>,
    values: <?= json_encode($criticalStockData) ?>
  },
  products: <?= json_encode(array_map(static function (array $productoRow): array {
    return [
      'id' => (int) ($productoRow['id'] ?? 0),
      'nombre' => (string) ($productoRow['nombre'] ?? ''),
      'sku' => (string) ($productoRow['sku'] ?? ''),
      'categoria' => (string) ($productoRow['categoria'] ?? ''),
      'precio' => (float) ($productoRow['precio'] ?? 0),
      'stock_total' => (int) ($productoRow['stock_total'] ?? 0),
      'tallas_activas' => (int) ($productoRow['tallas_activas'] ?? 0),
      'tallas_agotadas' => (int) ($productoRow['tallas_agotadas'] ?? 0)
    ];
  }, $productos), JSON_UNESCAPED_UNICODE) ?>
};

document.addEventListener("DOMContentLoaded", () => {
  const modalElement = document.getElementById("inventarioModal");
  const charts = {};
  let chartsReady = false;
  const palette = ["#b89247", "#8a6521", "#d7b56d", "#4d3620", "#caa05a", "#ead9b0", "#75614a", "#b27d2f"];

  function money(value) {
    return new Intl.NumberFormat("es-CO", {
      style: "currency",
      currency: "COP",
      maximumFractionDigits: 0
    }).format(Number(value || 0));
  }

  function csvEscape(value) {
    const text = String(value ?? "");
    return /[",\r\n]/.test(text) ? `"${text.replace(/"/g, "\"\"")}"` : text;
  }

  function buildCsv(sections) {
    const lines = [];

    sections.forEach((section, index) => {
      if (index > 0) {
        lines.push("");
      }

      lines.push(csvEscape(section.title));
      section.rows.forEach((row) => {
        lines.push(row.map(csvEscape).join(","));
      });
    });

    return "\uFEFF" + lines.join("\r\n");
  }

  function triggerDownload(filename, content, type) {
    const blob = new Blob([content], { type });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");

    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();

    window.setTimeout(() => URL.revokeObjectURL(url), 1000);
  }

  function createChart(id, config) {
    const canvas = document.getElementById(id);
    if (!canvas) {
      return null;
    }

    if (charts[id]) {
      return charts[id];
    }

    charts[id] = new Chart(canvas, config);
    return charts[id];
  }

  function baseScales() {
    return {
      x: {
        ticks: { color: "#4c4135" },
        grid: { display: false }
      },
      y: {
        ticks: { color: "#4c4135" },
        grid: { color: "rgba(128,102,53,0.12)" }
      }
    };
  }

  function initializeCharts() {
    if (chartsReady) {
      return;
    }

    createChart("chartCategoriaUnidades", {
      type: "bar",
      data: {
        labels: inventoryStats.categories.labels,
        datasets: [{
          label: "Unidades",
          data: inventoryStats.categories.units,
          backgroundColor: palette,
          borderRadius: 12,
          maxBarThickness: 52
        }]
      },
      options: {
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: baseScales()
      }
    });

    createChart("chartCategoriaValor", {
      type: "bar",
      data: {
        labels: inventoryStats.categories.labels,
        datasets: [{
          label: "Valor",
          data: inventoryStats.categories.values,
          backgroundColor: palette.map((color, index) => index % 2 === 0 ? color : "#1c1510"),
          borderRadius: 12,
          maxBarThickness: 52
        }]
      },
      options: {
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: (context) => ` ${money(context.parsed.y)}`
            }
          }
        },
        scales: {
          x: baseScales().x,
          y: {
            ...baseScales().y,
            ticks: {
              color: "#4c4135",
              callback: (value) => money(value)
            }
          }
        }
      }
    });

    createChart("chartEstadoInventario", {
      type: "doughnut",
      data: {
        labels: inventoryStats.status.labels,
        datasets: [{
          data: inventoryStats.status.values,
          backgroundColor: ["#b23a48", "#b7791f", "#2d6a4f"],
          borderWidth: 0
        }]
      },
      options: {
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: "bottom",
            labels: {
              color: "#4c4135",
              usePointStyle: true,
              padding: 18
            }
          }
        }
      }
    });

    createChart("chartTallasStock", {
      type: "line",
      data: {
        labels: inventoryStats.sizes.labels,
        datasets: [{
          label: "Stock",
          data: inventoryStats.sizes.units,
          borderColor: "#8a6521",
          backgroundColor: "rgba(184, 146, 71, 0.16)",
          fill: true,
          tension: 0.32,
          pointRadius: 4,
          pointBackgroundColor: "#b89247"
        }]
      },
      options: {
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: baseScales()
      }
    });

    if (inventoryStats.criticalProducts.labels.length) {
      createChart("chartProductosCriticos", {
        type: "bar",
        data: {
          labels: inventoryStats.criticalProducts.labels,
          datasets: [{
            label: "Stock disponible",
            data: inventoryStats.criticalProducts.values,
            backgroundColor: "#b23a48",
            borderRadius: 10,
            maxBarThickness: 40
          }]
        },
        options: {
          indexAxis: "y",
          maintainAspectRatio: false,
          plugins: { legend: { display: false } },
          scales: {
            x: baseScales().y,
            y: baseScales().x
          }
        }
      });
    }

    chartsReady = true;
  }

  function getInventoryState(stock) {
    if (Number(stock) <= 0) {
      return "Sin stock";
    }
    if (Number(stock) <= 5) {
      return "Critico";
    }
    return "Saludable";
  }

  function downloadInventoryCsv() {
    const sections = [
      {
        title: "Resumen inventario",
        rows: [
          ["Indicador", "Valor"],
          ["Productos", inventoryStats.summary.totalProducts],
          ["Unidades", inventoryStats.summary.totalUnits],
          ["Stock critico", inventoryStats.summary.critical],
          ["Sin stock", inventoryStats.summary.empty],
          ["Saludables", inventoryStats.summary.healthy],
          ["Valor inventario", inventoryStats.summary.inventoryValue],
          ["Promedio stock", inventoryStats.summary.averageStock]
        ]
      },
      {
        title: "Categorias",
        rows: [
          ["Categoria", "Unidades", "Valor"],
          ...inventoryStats.categories.labels.map((label, index) => [
            label,
            inventoryStats.categories.units[index] || 0,
            inventoryStats.categories.values[index] || 0
          ])
        ]
      },
      {
        title: "Tallas",
        rows: [
          ["Talla", "Unidades"],
          ...inventoryStats.sizes.labels.map((label, index) => [
            label,
            inventoryStats.sizes.units[index] || 0
          ])
        ]
      },
      {
        title: "Detalle productos",
        rows: [
          ["ID", "Producto", "SKU", "Categoria", "Precio", "Stock total", "Tallas activas", "Tallas agotadas", "Estado"],
          ...inventoryStats.products.map((product) => [
            product.id,
            product.nombre,
            product.sku,
            product.categoria,
            product.precio,
            product.stock_total,
            product.tallas_activas,
            product.tallas_agotadas,
            getInventoryState(product.stock_total)
          ])
        ]
      }
    ];

    triggerDownload("inventario-tauro.csv", buildCsv(sections), "text/csv;charset=utf-8;");
  }

  function downloadChart(chartId, filename) {
    initializeCharts();
    const chart = charts[chartId];

    if (!chart) {
      return;
    }

    const link = document.createElement("a");
    link.href = chart.toBase64Image("image/png", 1);
    link.download = `${filename}.png`;
    document.body.appendChild(link);
    link.click();
    link.remove();
  }

  document.getElementById("btnDescargarInventarioCsv")?.addEventListener("click", downloadInventoryCsv);
  document.getElementById("btnDescargarInventarioCsvModal")?.addEventListener("click", downloadInventoryCsv);

  document.querySelectorAll("[data-download-chart]").forEach((button) => {
    button.addEventListener("click", () => {
      downloadChart(button.dataset.downloadChart, button.dataset.filename || "grafico");
    });
  });

  modalElement?.addEventListener("shown.bs.modal", () => {
    initializeCharts();
    Object.values(charts).forEach((chart) => chart.resize());
  });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php
$appAssetPrefix = '../';
include '../includes/ui_footer.php';
?>
</body>
</html>
