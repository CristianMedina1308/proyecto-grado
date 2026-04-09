<?php
require_once '../includes/app.php';

if (!isset($_SESSION['usuario']) || ($_SESSION['usuario']['rol'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

require_once '../includes/conexion.php';
require_once '../includes/pedidos_utils.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pedido_id'], $_POST['nuevo_estado'])) {
    if (!appValidarCsrf('admin_pedidos_estado', $_POST['csrf_token'] ?? null)) {
        appFlash('error', 'La sesion del formulario expiro. Intenta nuevamente.', 'Accion no valida');
        appRedirect('pedidos.php');
    }

    $pedidoId = (int) $_POST['pedido_id'];
    $nuevoEstado = (string) $_POST['nuevo_estado'];

    $resultado = actualizarEstadoPedido(
        $conn,
        $pedidoId,
        $nuevoEstado,
        (int) ($_SESSION['usuario']['id'] ?? 0),
        'admin',
        'Cambio de estado desde panel admin'
    );

    if ($resultado['ok']) {
        appFlash('success', $resultado['mensaje'] ?? 'Estado actualizado.', 'Pedido actualizado');
    } else {
        appFlash('error', $resultado['mensaje'] ?? 'No se pudo actualizar el estado.', 'No se pudo actualizar');
    }

    appRedirect('pedidos.php');
}

$pedidos = $conn->query("
  SELECT p.*, u.nombre
  FROM pedidos p
  LEFT JOIN usuarios u ON p.usuario_id = u.id
  ORDER BY p.fecha DESC
")->fetchAll(PDO::FETCH_ASSOC);

$contadoresRaw = $conn->query('SELECT estado, COUNT(*) AS total FROM pedidos GROUP BY estado')->fetchAll(PDO::FETCH_KEY_PAIR);
$estados = estadosPedidoPermitidos();
$etiquetas = etiquetasEstadoPedido();
$contadores = [];
foreach ($estados as $estado) {
    $contadores[$estado] = (int) ($contadoresRaw[$estado] ?? 0);
}

$mesActual = date('Y-m');
$resumenMesStmt = $conn->prepare("
  SELECT
    COUNT(*) AS pedidos_mes,
    SUM(CASE WHEN estado <> 'cancelado' THEN total ELSE 0 END) AS ingresos_mes,
    AVG(CASE WHEN estado <> 'cancelado' THEN total END) AS ticket_mes,
    SUM(CASE WHEN estado = 'cancelado' THEN 1 ELSE 0 END) AS cancelados_mes
  FROM pedidos
  WHERE DATE_FORMAT(fecha, '%Y-%m') = ?
");
$resumenMesStmt->execute([$mesActual]);
$resumenMes = $resumenMesStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$ventasMensualesRaw = $conn->query("
  SELECT DATE_FORMAT(fecha, '%Y-%m') AS mes, SUM(CASE WHEN estado <> 'cancelado' THEN total ELSE 0 END) AS ingresos, COUNT(*) AS pedidos
  FROM pedidos
  WHERE fecha >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), '%Y-%m-01')
  GROUP BY DATE_FORMAT(fecha, '%Y-%m')
  ORDER BY mes ASC
")->fetchAll(PDO::FETCH_ASSOC);

$ventasMensualesMap = [];
$pedidosMensualesMap = [];
foreach ($ventasMensualesRaw as $row) {
    $mes = (string) ($row['mes'] ?? '');
    $ventasMensualesMap[$mes] = (float) ($row['ingresos'] ?? 0);
    $pedidosMensualesMap[$mes] = (int) ($row['pedidos'] ?? 0);
}

$mesesLabels = [];
$ventasMensuales = [];
$pedidosMensuales = [];
for ($i = 5; $i >= 0; $i--) {
    $mesKey = date('Y-m', strtotime("-{$i} month"));
    $mesesLabels[] = $mesKey;
    $ventasMensuales[] = (float) ($ventasMensualesMap[$mesKey] ?? 0);
    $pedidosMensuales[] = (int) ($pedidosMensualesMap[$mesKey] ?? 0);
}

$totalPedidos = count($pedidos);
$ingresosTotales = 0.0;
$ticketPromedioGeneral = 0.0;
$pedidosValidos = 0;
$pedidosRecientes = array_slice($pedidos, 0, 6);
$pedidosAltos = $pedidos;

foreach ($pedidos as $pedido) {
    if ((string) ($pedido['estado'] ?? '') !== 'cancelado') {
        $ingresosTotales += (float) ($pedido['total'] ?? 0);
        $pedidosValidos++;
    }
}

if ($pedidosValidos > 0) {
    $ticketPromedioGeneral = $ingresosTotales / $pedidosValidos;
}

usort($pedidosAltos, static function (array $a, array $b): int {
    return (float) ($b['total'] ?? 0) <=> (float) ($a['total'] ?? 0);
});
$pedidosAltos = array_slice($pedidosAltos, 0, 6);

$stateLabels = [];
$stateValues = [];
foreach ($estados as $estado) {
    $stateLabels[] = (string) ($etiquetas[$estado] ?? ucfirst($estado));
    $stateValues[] = (int) ($contadores[$estado] ?? 0);
}

$highValueLabels = [];
$highValueTotals = [];
foreach ($pedidosAltos as $pedidoAlto) {
    $highValueLabels[] = '#' . (int) ($pedidoAlto['id'] ?? 0);
    $highValueTotals[] = round((float) ($pedidoAlto['total'] ?? 0), 2);
}

function pedidoEstadoMeta(string $estado): array
{
    return match ($estado) {
        'cancelado' => ['label' => 'Cancelado', 'class' => 'is-danger'],
        'pendiente', 'pagado' => ['label' => ucfirst($estado), 'class' => 'is-warning'],
        'entregado' => ['label' => 'Entregado', 'class' => 'is-ok'],
        default => ['label' => ucfirst(str_replace('_', ' ', $estado)), 'class' => 'is-neutral'],
    };
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin | Pedidos Tauro</title>
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
<?php $adminActive = 'pedidos'; include __DIR__ . '/partials/nav.php'; ?>

<div class="container py-4 py-lg-5">
  <div class="admin-page-header">
    <div>
      <h1 class="admin-page-title">Gestion de pedidos</h1>
      <p class="admin-page-subtitle">Administra el flujo operativo de los pedidos con una vista mas limpia, mejor lectura del estado y analitica ampliada en modal.</p>
    </div>
    <div class="admin-actions">
      <button type="button" class="btn btn-admin-soft" data-bs-toggle="modal" data-bs-target="#pedidosAnalyticsModal"><i class="bi bi-bar-chart-line me-2"></i>Ver estadisticas</button>
      <button type="button" class="btn btn-admin-primary" id="btnDescargarPedidosCsv"><i class="bi bi-download me-2"></i>Descargar CSV</button>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3"><div class="admin-card admin-kpi h-100"><div class="admin-kpi-label">Pedidos</div><div class="admin-kpi-value"><?= $totalPedidos ?></div><div class="admin-kpi-foot">historial total</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="admin-card admin-kpi h-100"><div class="admin-kpi-label">Ingresos mes</div><div class="admin-kpi-value">$<?= number_format((float) ($resumenMes['ingresos_mes'] ?? 0), 0, ',', '.') ?></div><div class="admin-kpi-foot">pedidos no cancelados</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="admin-card admin-kpi h-100"><div class="admin-kpi-label">Ticket mes</div><div class="admin-kpi-value">$<?= number_format((float) ($resumenMes['ticket_mes'] ?? 0), 0, ',', '.') ?></div><div class="admin-kpi-foot">promedio operativo</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="admin-card admin-kpi h-100"><div class="admin-kpi-label">Cancelados mes</div><div class="admin-kpi-value"><?= (int) ($resumenMes['cancelados_mes'] ?? 0) ?></div><div class="admin-kpi-foot">seguimiento actual</div></div></div>
  </div>

  <div class="row g-4 mb-4">
    <div class="col-lg-5">
      <div class="admin-card p-4 h-100">
        <div class="admin-card-header">
          <div>
            <h2 class="admin-card-title">Resumen por estado</h2>
            <p class="admin-meta mb-0">Lectura rapida del flujo de pedidos y donde esta la carga operativa.</p>
          </div>
          <span class="admin-pill"><i class="bi bi-diagram-3"></i> Flujo</span>
        </div>

        <div class="admin-list">
          <?php foreach ($estados as $estado): ?>
            <?php $metaEstado = pedidoEstadoMeta((string) $estado); ?>
            <div class="admin-list-item">
              <div>
                <div class="admin-list-title"><?= htmlspecialchars((string) ($etiquetas[$estado] ?? ucfirst($estado))) ?></div>
                <div class="admin-list-meta">Pedidos en este estado</div>
              </div>
              <span class="admin-stat-badge <?= htmlspecialchars($metaEstado['class']) ?>"><?= (int) ($contadores[$estado] ?? 0) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="col-lg-7">
      <div class="admin-card p-4 h-100">
        <div class="admin-card-header">
          <div>
            <h2 class="admin-card-title">Pedidos de mayor valor</h2>
            <p class="admin-meta mb-0">Ordenes que mas pesan en ingresos y merecen seguimiento cuidadoso.</p>
          </div>
          <span class="admin-pill"><i class="bi bi-cash-stack"></i> Top valor</span>
        </div>

        <?php if ($pedidosAltos): ?>
          <div class="table-responsive">
            <table class="table admin-table align-middle mb-0">
              <thead>
                <tr>
                  <th>Pedido</th>
                  <th>Cliente</th>
                  <th>Total</th>
                  <th>Estado</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($pedidosAltos as $pedidoAlto): ?>
                  <?php $metaEstado = pedidoEstadoMeta(normalizarTextoPedido((string) ($pedidoAlto['estado'] ?? 'pendiente'))); ?>
                  <tr>
                    <td class="fw-semibold">#<?= (int) ($pedidoAlto['id'] ?? 0) ?></td>
                    <td><?= htmlspecialchars((string) ($pedidoAlto['nombre'] ?? 'Invitado')) ?></td>
                    <td class="fw-bold">$<?= number_format((float) ($pedidoAlto['total'] ?? 0), 0, ',', '.') ?></td>
                    <td><span class="admin-stat-badge <?= htmlspecialchars($metaEstado['class']) ?>"><?= htmlspecialchars((string) ($etiquetas[normalizarTextoPedido((string) ($pedidoAlto['estado'] ?? 'pendiente'))] ?? $metaEstado['label'])) ?></span></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="admin-empty">Aun no hay pedidos con datos suficientes para destacar.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="admin-card p-4">
    <div class="admin-card-header">
      <div>
        <h2 class="admin-card-title">Pedidos registrados</h2>
        <p class="admin-meta mb-0">Filtra pedidos, revisa montos y actualiza estados desde una tabla mas clara.</p>
      </div>
      <div class="inventory-stack">
        <span class="admin-stat-badge is-warning">Pendientes: <?= (int) ($contadores['pendiente'] ?? 0) ?></span>
        <span class="admin-stat-badge is-ok">Entregados: <?= (int) ($contadores['entregado'] ?? 0) ?></span>
        <span class="admin-stat-badge is-danger">Cancelados: <?= (int) ($contadores['cancelado'] ?? 0) ?></span>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table admin-table align-middle mb-0" data-datatable="true" data-no-sort="6,7" data-page-length="10">
        <thead>
          <tr>
            <th>ID</th>
            <th>Cliente</th>
            <th>Subtotal</th>
            <th>Envio</th>
            <th>Total</th>
            <th>Fecha</th>
            <th width="280">Estado</th>
            <th>Detalle</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($pedidos): ?>
            <?php foreach ($pedidos as $pedido): ?>
              <?php
                $estadoActual = normalizarTextoPedido((string) ($pedido['estado'] ?? 'pendiente'));
                $opciones = opcionesEstadoPedido($estadoActual);
                $bloqueado = count($opciones) <= 1;
                $metaEstado = pedidoEstadoMeta($estadoActual);
              ?>
              <tr>
                <td>#<?= (int) $pedido['id'] ?></td>
                <td>
                  <div class="fw-semibold"><?= htmlspecialchars((string) ($pedido['nombre'] ?? 'Invitado')) ?></div>
                  <div class="admin-meta">Pedido #<?= (int) $pedido['id'] ?></div>
                </td>
                <td>$<?= number_format((float) ($pedido['subtotal_productos'] ?? 0), 0, ',', '.') ?></td>
                <td>$<?= number_format((float) ($pedido['costo_envio'] ?? 0), 0, ',', '.') ?></td>
                <td class="fw-bold">$<?= number_format((float) ($pedido['total'] ?? 0), 0, ',', '.') ?></td>
                <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string) $pedido['fecha']))) ?></td>
                <td>
                  <form method="post"
                        class="d-flex align-items-center gap-2 flex-wrap"
                        data-confirm="true"
                        data-confirm-title="Actualizar estado"
                        data-confirm-message="Se actualizara el estado del pedido seleccionado. Verifica que el cambio corresponda al flujo real del despacho."
                        data-confirm-button="Actualizar"
                        data-confirm-variant="btn-primary">
                    <input type="hidden" name="pedido_id" value="<?= (int) $pedido['id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(appCsrfToken('admin_pedidos_estado')) ?>">
                    <select name="nuevo_estado" class="form-select form-select-sm" style="min-width: 150px;" <?= $bloqueado ? 'disabled' : '' ?>>
                      <?php foreach ($opciones as $estado): ?>
                        <option value="<?= htmlspecialchars($estado) ?>" <?= $estado === $estadoActual ? 'selected' : '' ?>>
                          <?= htmlspecialchars((string) ($etiquetas[$estado] ?? ucfirst($estado))) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <span class="admin-stat-badge <?= htmlspecialchars($metaEstado['class']) ?>"><?= htmlspecialchars((string) ($etiquetas[$estadoActual] ?? $metaEstado['label'])) ?></span>
                    <?php if ($bloqueado): ?>
                      <span class="admin-meta">Estado final</span>
                    <?php else: ?>
                      <button type="submit" class="btn btn-admin-ghost btn-sm">Aplicar</button>
                    <?php endif; ?>
                  </form>
                </td>
                <td>
                  <a href="../ver_pedido.php?id=<?= (int) $pedido['id'] ?>" class="btn btn-admin-primary btn-sm">Ver</a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="8"><div class="admin-empty text-center">No hay pedidos registrados.</div></td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="modal fade admin-modal" id="pedidosAnalyticsModal" tabindex="-1" aria-labelledby="pedidosAnalyticsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h2 class="modal-title" id="pedidosAnalyticsModalLabel">Analitica de pedidos</h2>
          <div class="admin-meta">Graficos ampliados para seguir volumen, ingresos y estados del flujo logístico.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div class="chart-grid">
          <div class="admin-card-soft chart-card">
            <div class="admin-card-header"><h3 class="admin-card-title">Pedidos por estado</h3></div>
            <canvas id="ordersStateChart"></canvas>
            <div class="chart-actions">
              <button type="button" class="btn btn-admin-soft btn-sm" data-download-chart="ordersStateChart" data-filename="pedidos-por-estado"><i class="bi bi-image me-1"></i>Descargar PNG</button>
            </div>
          </div>
          <div class="admin-card-soft chart-card chart-card-lg">
            <div class="admin-card-header"><h3 class="admin-card-title">Ingresos ultimos 6 meses</h3></div>
            <canvas id="ordersRevenueChart"></canvas>
            <div class="chart-actions">
              <button type="button" class="btn btn-admin-soft btn-sm" data-download-chart="ordersRevenueChart" data-filename="pedidos-ingresos-mensuales"><i class="bi bi-image me-1"></i>Descargar PNG</button>
            </div>
          </div>
          <div class="admin-card-soft chart-card chart-card-lg">
            <div class="admin-card-header"><h3 class="admin-card-title">Volumen mensual de pedidos</h3></div>
            <canvas id="ordersVolumeChart"></canvas>
            <div class="chart-actions">
              <button type="button" class="btn btn-admin-soft btn-sm" data-download-chart="ordersVolumeChart" data-filename="pedidos-volumen-mensual"><i class="bi bi-image me-1"></i>Descargar PNG</button>
            </div>
          </div>
          <div class="admin-card-soft chart-card chart-card-lg">
            <div class="admin-card-header"><h3 class="admin-card-title">Pedidos de mayor valor</h3></div>
            <canvas id="ordersHighValueChart"></canvas>
            <div class="chart-actions">
              <button type="button" class="btn btn-admin-soft btn-sm" data-download-chart="ordersHighValueChart" data-filename="pedidos-mayor-valor"><i class="bi bi-image me-1"></i>Descargar PNG</button>
            </div>
          </div>
          <div class="admin-card-soft chart-card chart-card-lg">
            <div class="admin-card-header"><h3 class="admin-card-title">Resumen descargable</h3></div>
            <div class="admin-empty h-100">
              <p class="mb-3">Descarga un CSV con resumen de pedidos, estados, historico mensual y detalle de las ordenes listadas.</p>
              <button type="button" class="btn btn-admin-primary" id="btnDescargarPedidosCsvModal"><i class="bi bi-download me-2"></i>Descargar estadisticas CSV</button>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer justify-content-between">
        <div class="admin-meta">Pedidos: <?= $totalPedidos ?> · Ingresos acumulados: $<?= number_format($ingresosTotales, 0, ',', '.') ?></div>
        <button type="button" class="btn btn-admin-ghost" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<script>
window.ADMIN_ORDERS_DATA = <?= json_encode([
    'summary' => [
        'totalPedidos' => $totalPedidos,
        'ingresosTotales' => round($ingresosTotales, 2),
        'ticketPromedioGeneral' => round($ticketPromedioGeneral, 2),
        'pedidosMes' => (int) ($resumenMes['pedidos_mes'] ?? 0),
        'ingresosMes' => round((float) ($resumenMes['ingresos_mes'] ?? 0), 2),
        'ticketMes' => round((float) ($resumenMes['ticket_mes'] ?? 0), 2),
        'canceladosMes' => (int) ($resumenMes['cancelados_mes'] ?? 0),
        'mesActual' => $mesActual
    ],
    'states' => [
        'labels' => $stateLabels,
        'values' => $stateValues,
        'rows' => array_map(static function (string $estado) use ($contadores, $etiquetas): array {
            return [
                'estado' => (string) ($etiquetas[$estado] ?? ucfirst($estado)),
                'cantidad' => (int) ($contadores[$estado] ?? 0)
            ];
        }, $estados)
    ],
    'monthly' => [
        'labels' => $mesesLabels,
        'revenue' => $ventasMensuales,
        'orders' => $pedidosMensuales
    ],
    'highValue' => [
        'labels' => $highValueLabels,
        'values' => $highValueTotals
    ],
    'rows' => array_map(static function (array $row) use ($etiquetas): array {
        $estado = normalizarTextoPedido((string) ($row['estado'] ?? 'pendiente'));
        return [
            'id' => (int) ($row['id'] ?? 0),
            'cliente' => (string) ($row['nombre'] ?? 'Invitado'),
            'subtotal' => (float) ($row['subtotal_productos'] ?? 0),
            'envio' => (float) ($row['costo_envio'] ?? 0),
            'total' => (float) ($row['total'] ?? 0),
            'fecha' => (string) ($row['fecha'] ?? ''),
            'estado' => (string) ($etiquetas[$estado] ?? ucfirst($estado))
        ];
    }, $pedidosRecientes)
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="../assets/js/admin-orders.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php
$appAssetPrefix = '../';
include '../includes/ui_footer.php';
?>
</body>
</html>
