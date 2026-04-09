<?php
require_once '../includes/app.php';
if (!isset($_SESSION['usuario']) || ($_SESSION['usuario']['rol'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}
require_once '../includes/conexion.php';
require_once '../includes/pedidos_utils.php';

$mesActual = date('Y-m');
$resumenMesStmt = $conn->prepare("
  SELECT
    SUM(CASE WHEN estado <> 'cancelado' THEN total ELSE 0 END) AS ventas_mes,
    COUNT(*) AS pedidos_mes,
    AVG(CASE WHEN estado <> 'cancelado' THEN total END) AS ticket_promedio,
    SUM(CASE WHEN estado = 'cancelado' THEN 1 ELSE 0 END) AS cancelados_mes
  FROM pedidos
  WHERE DATE_FORMAT(fecha, '%Y-%m') = ?
");
$resumenMesStmt->execute([$mesActual]);
$resumenMes = $resumenMesStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$totalProductos = (int) $conn->query('SELECT COUNT(*) FROM productos')->fetchColumn();
$totalUsuarios = (int) $conn->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
$stockCritico = (int) $conn->query("
  SELECT COUNT(*) FROM (
    SELECT producto_id, SUM(stock) AS stock_total
    FROM producto_tallas
    GROUP BY producto_id
    HAVING stock_total <= 5
  ) x
")->fetchColumn();
$sinStock = (int) $conn->query("
  SELECT COUNT(*) FROM (
    SELECT producto_id, SUM(stock) AS stock_total
    FROM producto_tallas
    GROUP BY producto_id
    HAVING stock_total <= 0
  ) x
")->fetchColumn();

$topProductos = $conn->query("
  SELECT COALESCE(p.nombre, dp.nombre_producto) AS nombre, SUM(dp.cantidad) AS total_vendidos
  FROM detalle_pedido dp
  INNER JOIN pedidos pe ON pe.id = dp.pedido_id
  LEFT JOIN productos p ON dp.producto_id = p.id
  WHERE pe.estado <> 'cancelado'
  GROUP BY dp.producto_id, dp.nombre_producto
  ORDER BY total_vendidos DESC
  LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);

$stockBajoProductos = $conn->query("
  SELECT p.id, p.nombre, COALESCE(SUM(pt.stock), 0) AS stock_total
  FROM productos p
  LEFT JOIN producto_tallas pt ON pt.producto_id = p.id
  GROUP BY p.id, p.nombre
  ORDER BY stock_total ASC, p.nombre ASC
  LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);

$estados = $conn->query('SELECT estado, COUNT(*) AS cantidad FROM pedidos GROUP BY estado')->fetchAll(PDO::FETCH_ASSOC);
$etiquetasEstado = etiquetasEstadoPedido();
$tallaMasVendidaStmt = $conn->query("
  SELECT talla, SUM(cantidad) AS total
  FROM detalle_pedido dp
  INNER JOIN pedidos pe ON pe.id = dp.pedido_id
  WHERE pe.estado <> 'cancelado' AND dp.talla IS NOT NULL AND dp.talla <> ''
  GROUP BY dp.talla
  ORDER BY total DESC
  LIMIT 1
");
$tallaMasVendida = $tallaMasVendidaStmt->fetch(PDO::FETCH_ASSOC) ?: null;

$ventasMensualesRaw = $conn->query("
  SELECT DATE_FORMAT(fecha, '%Y-%m') AS mes, SUM(CASE WHEN estado <> 'cancelado' THEN total ELSE 0 END) AS ventas
  FROM pedidos
  WHERE fecha >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), '%Y-%m-01')
  GROUP BY DATE_FORMAT(fecha, '%Y-%m')
  ORDER BY mes ASC
")->fetchAll(PDO::FETCH_KEY_PAIR);

$labels6Meses = [];
$ventas6Meses = [];
for ($i = 5; $i >= 0; $i--) {
    $mesKey = date('Y-m', strtotime("-{$i} month"));
    $labels6Meses[] = $mesKey;
    $ventas6Meses[] = (float) ($ventasMensualesRaw[$mesKey] ?? 0);
}

$ventasMes = (float) ($resumenMes['ventas_mes'] ?? 0);
$pedidosMes = (int) ($resumenMes['pedidos_mes'] ?? 0);
$ticketPromedio = (float) ($resumenMes['ticket_promedio'] ?? 0);
$canceladosMes = (int) ($resumenMes['cancelados_mes'] ?? 0);
$tasaCancelacion = $pedidosMes > 0 ? ($canceladosMes / $pedidosMes) * 100 : 0;

$topProductosLabels = [];
$topProductosDatos = [];
foreach ($topProductos as $itemTop) {
    $topProductosLabels[] = (string) ($itemTop['nombre'] ?? 'Producto');
    $topProductosDatos[] = (int) ($itemTop['total_vendidos'] ?? 0);
}

$estadosLabels = [];
$estadosDatos = [];
foreach ($estados as $estadoRow) {
    $codigoEstado = (string) ($estadoRow['estado'] ?? '');
    $estadosLabels[] = (string) ($etiquetasEstado[$codigoEstado] ?? $codigoEstado);
    $estadosDatos[] = (int) ($estadoRow['cantidad'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin | Dashboard Tauro</title>
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
<?php $adminActive = 'dashboard'; include __DIR__ . '/partials/nav.php'; ?>
<div class="container py-4 py-lg-5">
  <div class="admin-page-header">
    <div>
      <h1 class="admin-page-title">Dashboard operativo</h1>
      <p class="admin-page-subtitle">Panorama rapido del negocio para seguir ventas, estado de pedidos e inventario sin ruido visual. Los graficos se abren en un modal amplio para una lectura mas limpia.</p>
    </div>
    <div class="admin-actions">
      <button type="button" class="btn btn-admin-soft" data-bs-toggle="modal" data-bs-target="#dashboardAnalyticsModal"><i class="bi bi-bar-chart-line me-2"></i>Ver graficos</button>
      <button type="button" class="btn btn-admin-primary" id="btnDescargarDashboardCsv"><i class="bi bi-download me-2"></i>Descargar CSV</button>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-2"><div class="admin-card admin-kpi h-100"><div class="admin-kpi-label">Ventas del mes</div><div class="admin-kpi-value">$<?= number_format($ventasMes, 0, ',', '.') ?></div><div class="admin-kpi-foot">solo pedidos no cancelados</div></div></div>
    <div class="col-sm-6 col-xl-2"><div class="admin-card admin-kpi h-100"><div class="admin-kpi-label">Pedidos del mes</div><div class="admin-kpi-value"><?= $pedidosMes ?></div><div class="admin-kpi-foot">movimiento mensual</div></div></div>
    <div class="col-sm-6 col-xl-2"><div class="admin-card admin-kpi h-100"><div class="admin-kpi-label">Ticket promedio</div><div class="admin-kpi-value">$<?= number_format($ticketPromedio, 0, ',', '.') ?></div><div class="admin-kpi-foot">ventas no canceladas</div></div></div>
    <div class="col-sm-6 col-xl-2"><div class="admin-card admin-kpi h-100"><div class="admin-kpi-label">Cancelacion</div><div class="admin-kpi-value"><?= number_format($tasaCancelacion, 1, ',', '.') ?>%</div><div class="admin-kpi-foot"><?= $canceladosMes ?> pedidos cancelados</div></div></div>
    <div class="col-sm-6 col-xl-2"><div class="admin-card admin-kpi h-100"><div class="admin-kpi-label">Stock critico</div><div class="admin-kpi-value"><?= $stockCritico ?></div><div class="admin-kpi-foot">productos en riesgo</div></div></div>
    <div class="col-sm-6 col-xl-2"><div class="admin-card admin-kpi h-100"><div class="admin-kpi-label">Talla top</div><div class="admin-kpi-value"><?= htmlspecialchars((string) ($tallaMasVendida['talla'] ?? '-')) ?></div><div class="admin-kpi-foot">mas vendida historicamente</div></div></div>
  </div>

  <div class="row g-4 mb-4">
    <div class="col-lg-6">
      <div class="admin-card p-4 h-100">
        <div class="admin-card-header">
          <div>
            <h2 class="admin-card-title">Productos mas vendidos</h2>
            <p class="admin-meta mb-0">Referencias que mas estan empujando la facturacion.</p>
          </div>
          <span class="admin-pill"><i class="bi bi-fire"></i> Top ventas</span>
        </div>
        <?php if ($topProductos): ?>
          <div class="table-responsive">
            <table class="table admin-table align-middle mb-0">
              <thead><tr><th>Producto</th><th>Vendidos</th></tr></thead>
              <tbody>
                <?php foreach ($topProductos as $p): ?>
                  <tr>
                    <td class="fw-semibold"><?= htmlspecialchars((string) $p['nombre']) ?></td>
                    <td class="fw-bold"><?= (int) $p['total_vendidos'] ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="admin-empty">Aun no hay ventas suficientes para armar un top comercial.</div>
        <?php endif; ?>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="admin-card p-4 h-100">
        <div class="admin-card-header">
          <div>
            <h2 class="admin-card-title">Inventario bajo</h2>
            <p class="admin-meta mb-0">Productos que necesitan reposicion o ajuste antes de afectar la experiencia.</p>
          </div>
          <span class="admin-pill"><i class="bi bi-box-seam"></i> Inventario</span>
        </div>
        <?php if ($stockBajoProductos): ?>
          <div class="table-responsive">
            <table class="table admin-table align-middle mb-0">
              <thead><tr><th>Producto</th><th>Stock</th><th></th></tr></thead>
              <tbody>
                <?php foreach ($stockBajoProductos as $p): ?>
                  <tr>
                    <td class="fw-semibold"><?= htmlspecialchars((string) $p['nombre']) ?></td>
                    <td class="fw-bold"><?= (int) $p['stock_total'] ?></td>
                    <td class="text-end"><a href="editar_producto.php?id=<?= (int) $p['id'] ?>" class="btn btn-admin-ghost btn-sm">Ajustar</a></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="admin-empty">No hay productos para revisar en este momento.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-lg-6">
      <div class="admin-card p-4 h-100">
        <div class="admin-card-header">
          <div>
            <h2 class="admin-card-title">Pulso del admin</h2>
            <p class="admin-meta mb-0">Indicadores rapidos para mantener contexto operativo.</p>
          </div>
          <span class="admin-pill"><i class="bi bi-speedometer2"></i> Resumen</span>
        </div>
        <div class="admin-note-grid">
          <div class="admin-card-soft admin-note-card"><div class="admin-kpi-label">Productos</div><div class="admin-note-value"><?= $totalProductos ?></div><div class="admin-meta">catalogo activo</div></div>
          <div class="admin-card-soft admin-note-card"><div class="admin-kpi-label">Usuarios</div><div class="admin-note-value"><?= $totalUsuarios ?></div><div class="admin-meta">cuentas registradas</div></div>
          <div class="admin-card-soft admin-note-card"><div class="admin-kpi-label">Sin stock</div><div class="admin-note-value"><?= $sinStock ?></div><div class="admin-meta">referencias agotadas</div></div>
          <div class="admin-card-soft admin-note-card"><div class="admin-kpi-label">Mes analizado</div><div class="admin-note-value"><?= htmlspecialchars($mesActual) ?></div><div class="admin-meta">corte actual</div></div>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="admin-card p-4 h-100">
        <div class="admin-card-header">
          <div>
            <h2 class="admin-card-title">Estados de pedidos</h2>
            <p class="admin-meta mb-0">Distribucion actual para revisar carga operativa y tiempos de cierre.</p>
          </div>
          <span class="admin-pill"><i class="bi bi-truck"></i> Flujo</span>
        </div>
        <?php if ($estados): ?>
          <div class="admin-list">
            <?php foreach ($estados as $estadoItem): ?>
              <?php
                $codigo = (string) ($estadoItem['estado'] ?? '');
                $label = (string) ($etiquetasEstado[$codigo] ?? $codigo);
                $cantidad = (int) ($estadoItem['cantidad'] ?? 0);
                $badgeClass = $codigo === 'cancelado' ? 'is-danger' : ($codigo === 'pendiente' ? 'is-warning' : 'is-ok');
              ?>
              <div class="admin-list-item">
                <div>
                  <div class="admin-list-title"><?= htmlspecialchars($label) ?></div>
                  <div class="admin-list-meta">Pedidos en este estado</div>
                </div>
                <span class="admin-stat-badge <?= htmlspecialchars($badgeClass) ?>"><?= $cantidad ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="admin-empty">Aun no hay pedidos para mostrar distribucion.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="modal fade admin-modal" id="dashboardAnalyticsModal" tabindex="-1" aria-labelledby="dashboardAnalyticsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h2 class="modal-title" id="dashboardAnalyticsModalLabel">Centro analitico</h2>
          <div class="admin-meta">Graficos ampliados del rendimiento comercial y operativo con descargas listas para reporte.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div class="chart-grid">
          <div class="admin-card-soft chart-card chart-card-lg">
            <div class="admin-card-header"><h3 class="admin-card-title">Top productos vendidos</h3></div>
            <canvas id="dashboardTopProductos"></canvas>
            <div class="chart-actions"><button type="button" class="btn btn-admin-soft btn-sm" data-download-chart="dashboardTopProductos" data-filename="dashboard-top-productos"><i class="bi bi-image me-1"></i>Descargar PNG</button></div>
          </div>
          <div class="admin-card-soft chart-card chart-card-lg">
            <div class="admin-card-header"><h3 class="admin-card-title">Pedidos por estado</h3></div>
            <canvas id="dashboardEstadosPedidos"></canvas>
            <div class="chart-actions"><button type="button" class="btn btn-admin-soft btn-sm" data-download-chart="dashboardEstadosPedidos" data-filename="dashboard-estados-pedidos"><i class="bi bi-image me-1"></i>Descargar PNG</button></div>
          </div>
          <div class="admin-card-soft chart-card chart-card-lg">
            <div class="admin-card-header"><h3 class="admin-card-title">Ventas ultimos 6 meses</h3></div>
            <canvas id="dashboardVentasMensuales"></canvas>
            <div class="chart-actions"><button type="button" class="btn btn-admin-soft btn-sm" data-download-chart="dashboardVentasMensuales" data-filename="dashboard-ventas-mensuales"><i class="bi bi-image me-1"></i>Descargar PNG</button></div>
          </div>
          <div class="admin-card-soft chart-card chart-card-lg">
            <div class="admin-card-header"><h3 class="admin-card-title">Resumen descargable</h3></div>
            <div class="admin-empty h-100">
              <p class="mb-3">Descarga un CSV con los KPI del mes, productos top, alertas de stock y el historico mensual del dashboard.</p>
              <button type="button" class="btn btn-admin-primary" id="btnDescargarDashboardCsvModal"><i class="bi bi-download me-2"></i>Descargar estadisticas CSV</button>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer justify-content-between">
        <div class="admin-meta">Ventas del mes: $<?= number_format($ventasMes, 0, ',', '.') ?> · Pedidos del mes: <?= $pedidosMes ?></div>
        <button type="button" class="btn btn-admin-ghost" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<script>
window.ADMIN_DASHBOARD_DATA = <?= json_encode([
    'summary' => [
        'ventasMes' => round($ventasMes, 2),
        'pedidosMes' => $pedidosMes,
        'ticketPromedio' => round($ticketPromedio, 2),
        'canceladosMes' => $canceladosMes,
        'tasaCancelacion' => round($tasaCancelacion, 2),
        'stockCritico' => $stockCritico,
        'sinStock' => $sinStock,
        'totalProductos' => $totalProductos,
        'totalUsuarios' => $totalUsuarios,
        'tallaTop' => (string) ($tallaMasVendida['talla'] ?? ''),
        'mesActual' => $mesActual
    ],
    'topProductos' => [
        'labels' => $topProductosLabels,
        'values' => $topProductosDatos,
        'rows' => $topProductos
    ],
    'estados' => [
        'labels' => $estadosLabels,
        'values' => $estadosDatos,
        'rows' => array_map(static function (array $item) use ($etiquetasEstado): array {
            $codigo = (string) ($item['estado'] ?? '');
            return [
                'estado' => (string) ($etiquetasEstado[$codigo] ?? $codigo),
                'cantidad' => (int) ($item['cantidad'] ?? 0)
            ];
        }, $estados)
    ],
    'ventasMensuales' => [
        'labels' => $labels6Meses,
        'values' => $ventas6Meses
    ],
    'stockBajo' => array_map(static function (array $item): array {
        return [
            'id' => (int) ($item['id'] ?? 0),
            'nombre' => (string) ($item['nombre'] ?? ''),
            'stock_total' => (int) ($item['stock_total'] ?? 0)
        ];
    }, $stockBajoProductos)
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="../assets/js/admin-dashboard.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php
$appAssetPrefix = '../';
include '../includes/ui_footer.php';
?>
</body>
</html>
