<?php
require_once '../includes/app.php';

if (!isset($_SESSION['usuario']) || ($_SESSION['usuario']['rol'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

require_once '../includes/conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['usuario_id'], $_POST['nuevo_rol'])) {
    if (!appValidarCsrf('admin_users_role', $_POST['csrf_token'] ?? null)) {
        appFlash('error', 'La sesion del formulario expiro. Intenta nuevamente.', 'Accion no valida');
        appRedirect('usuarios.php');
    }

    $usuarioId = (int) $_POST['usuario_id'];
    $nuevoRol = $_POST['nuevo_rol'] === 'admin' ? 'admin' : 'cliente';

    if ($usuarioId === (int) ($_SESSION['usuario']['id'] ?? 0) && $nuevoRol !== 'admin') {
        appFlash('error', 'No puedes quitarte tu propio acceso administrativo desde esta pantalla.', 'Accion bloqueada');
        appRedirect('usuarios.php');
    }

    $update = $conn->prepare('UPDATE usuarios SET rol = ? WHERE id = ?');
    $update->execute([$nuevoRol, $usuarioId]);

    appFlash('success', 'El rol del usuario fue actualizado correctamente.', 'Rol actualizado');
    appRedirect('usuarios.php');
}

$usuarios = $conn->query("
  SELECT
    u.id,
    u.nombre,
    u.email,
    u.rol,
    COUNT(p.id) AS total_pedidos,
    COALESCE(SUM(CASE WHEN p.estado <> 'cancelado' THEN p.total ELSE 0 END), 0) AS total_gastado,
    COALESCE(SUM(CASE WHEN p.estado <> 'cancelado' THEN 1 ELSE 0 END), 0) AS pedidos_validos,
    MAX(p.fecha) AS ultimo_pedido
  FROM usuarios u
  LEFT JOIN pedidos p ON u.id = p.usuario_id
  GROUP BY u.id
  ORDER BY u.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

$totalUsuarios = count($usuarios);
$totalAdmins = 0;
$totalClientes = 0;
$compradoresActivos = 0;
$facturacionUsuarios = 0.0;
$usuariosConPedidos = [];

foreach ($usuarios as $usuario) {
    $rol = (string) ($usuario['rol'] ?? 'cliente');
    $totalGastado = (float) ($usuario['total_gastado'] ?? 0);
    $pedidosValidos = (int) ($usuario['pedidos_validos'] ?? 0);

    if ($rol === 'admin') {
        $totalAdmins++;
    } else {
        $totalClientes++;
    }

    if ($pedidosValidos > 0) {
        $compradoresActivos++;
        $usuariosConPedidos[] = $usuario;
    }

    $facturacionUsuarios += $totalGastado;
}

usort($usuariosConPedidos, static function (array $a, array $b): int {
    $gastoCompare = (float) ($b['total_gastado'] ?? 0) <=> (float) ($a['total_gastado'] ?? 0);

    if ($gastoCompare !== 0) {
        return $gastoCompare;
    }

    return strcmp((string) ($a['nombre'] ?? ''), (string) ($b['nombre'] ?? ''));
});

$topCompradores = array_slice($usuariosConPedidos, 0, 6);
$ticketPromedioUsuario = $compradoresActivos > 0 ? ($facturacionUsuarios / $compradoresActivos) : 0;

$roleLabels = ['Clientes', 'Admins'];
$roleValues = [$totalClientes, $totalAdmins];

$spenderLabels = [];
$spenderValues = [];
$orderLabels = [];
$orderValues = [];
foreach ($topCompradores as $comprador) {
    $nombre = (string) ($comprador['nombre'] ?? 'Usuario');
    $spenderLabels[] = $nombre;
    $spenderValues[] = round((float) ($comprador['total_gastado'] ?? 0), 2);
    $orderLabels[] = $nombre;
    $orderValues[] = (int) ($comprador['total_pedidos'] ?? 0);
}

function usuarioRolMeta(string $rol): array
{
    if ($rol === 'admin') {
        return ['label' => 'Admin', 'class' => 'is-warning'];
    }

    return ['label' => 'Cliente', 'class' => 'is-neutral'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin | Usuarios Tauro</title>
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
<?php $adminActive = 'usuarios'; include __DIR__ . '/partials/nav.php'; ?>

<div class="container py-4 py-lg-5">
  <div class="admin-page-header">
    <div>
      <h1 class="admin-page-title">Gestion de usuarios</h1>
      <p class="admin-page-subtitle">Visualiza la base de clientes, detecta compradores clave y administra roles desde una vista mas elegante y operativa.</p>
    </div>
    <div class="admin-actions">
      <button type="button" class="btn btn-admin-soft" data-bs-toggle="modal" data-bs-target="#usuariosAnalyticsModal"><i class="bi bi-bar-chart-line me-2"></i>Ver estadisticas</button>
      <button type="button" class="btn btn-admin-primary" id="btnDescargarUsuariosCsv"><i class="bi bi-download me-2"></i>Descargar CSV</button>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3"><div class="admin-card admin-kpi h-100"><div class="admin-kpi-label">Usuarios</div><div class="admin-kpi-value"><?= $totalUsuarios ?></div><div class="admin-kpi-foot">registros totales</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="admin-card admin-kpi h-100"><div class="admin-kpi-label">Compradores activos</div><div class="admin-kpi-value"><?= $compradoresActivos ?></div><div class="admin-kpi-foot">con pedidos validos</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="admin-card admin-kpi h-100"><div class="admin-kpi-label">Admins</div><div class="admin-kpi-value"><?= $totalAdmins ?></div><div class="admin-kpi-foot">acceso administrativo</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="admin-card admin-kpi h-100"><div class="admin-kpi-label">Ticket por cliente</div><div class="admin-kpi-value">$<?= number_format($ticketPromedioUsuario, 0, ',', '.') ?></div><div class="admin-kpi-foot">facturacion media activa</div></div></div>
  </div>

  <div class="row g-4 mb-4">
    <div class="col-lg-5">
      <div class="admin-card p-4 h-100">
        <div class="admin-card-header">
          <div>
            <h2 class="admin-card-title">Clientes clave</h2>
            <p class="admin-meta mb-0">Usuarios con mayor impacto comercial hasta ahora.</p>
          </div>
          <span class="admin-pill"><i class="bi bi-stars"></i> Valor</span>
        </div>

        <?php if ($topCompradores): ?>
          <div class="admin-list">
            <?php foreach ($topCompradores as $comprador): ?>
              <div class="admin-list-item">
                <div>
                  <div class="admin-list-title"><?= htmlspecialchars((string) $comprador['nombre']) ?></div>
                  <div class="admin-list-meta"><?= htmlspecialchars((string) $comprador['email']) ?> · pedidos: <?= (int) ($comprador['total_pedidos'] ?? 0) ?></div>
                </div>
                <div class="text-end">
                  <div class="fw-bold">$<?= number_format((float) ($comprador['total_gastado'] ?? 0), 0, ',', '.') ?></div>
                  <div class="admin-meta"><?= !empty($comprador['ultimo_pedido']) ? htmlspecialchars(date('d/m/Y', strtotime((string) $comprador['ultimo_pedido']))) : 'Sin pedidos recientes' ?></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="admin-empty">Aun no hay compradores con historial suficiente para destacar.</div>
        <?php endif; ?>
      </div>
    </div>

    <div class="col-lg-7">
      <div class="admin-card p-4 h-100">
        <div class="admin-card-header">
          <div>
            <h2 class="admin-card-title">Distribucion de perfiles</h2>
            <p class="admin-meta mb-0">Balance general entre clientes, administradores y actividad comercial.</p>
          </div>
          <span class="admin-pill"><i class="bi bi-people"></i> Base</span>
        </div>

        <div class="admin-note-grid">
          <div class="admin-card-soft admin-note-card">
            <div class="admin-kpi-label">Clientes</div>
            <div class="admin-note-value"><?= $totalClientes ?></div>
            <div class="admin-meta">usuarios de compra</div>
          </div>
          <div class="admin-card-soft admin-note-card">
            <div class="admin-kpi-label">Admins</div>
            <div class="admin-note-value"><?= $totalAdmins ?></div>
            <div class="admin-meta">gestores activos</div>
          </div>
          <div class="admin-card-soft admin-note-card">
            <div class="admin-kpi-label">Facturacion usuarios</div>
            <div class="admin-note-value">$<?= number_format($facturacionUsuarios, 0, ',', '.') ?></div>
            <div class="admin-meta">pedidos no cancelados</div>
          </div>
          <div class="admin-card-soft admin-note-card">
            <div class="admin-kpi-label">Sin compras</div>
            <div class="admin-note-value"><?= $totalUsuarios - $compradoresActivos ?></div>
            <div class="admin-meta">usuarios por activar</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="admin-card p-4">
    <div class="admin-card-header">
      <div>
        <h2 class="admin-card-title">Usuarios registrados</h2>
        <p class="admin-meta mb-0">Filtra, compara y actualiza roles desde una tabla mas legible.</p>
      </div>
      <div class="inventory-stack">
        <span class="admin-stat-badge is-neutral">Clientes: <?= $totalClientes ?></span>
        <span class="admin-stat-badge is-warning">Admins: <?= $totalAdmins ?></span>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table admin-table align-middle" data-datatable="true" data-no-sort="6" data-page-length="10">
        <thead>
          <tr>
            <th>ID</th>
            <th>Usuario</th>
            <th>Email</th>
            <th>Pedidos</th>
            <th>Total comprado</th>
            <th>Ultimo pedido</th>
            <th width="260">Rol</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($usuarios as $user): ?>
            <?php $rolMeta = usuarioRolMeta((string) ($user['rol'] ?? 'cliente')); ?>
            <tr>
              <td><?= (int) $user['id'] ?></td>
              <td>
                <div class="fw-semibold"><?= htmlspecialchars((string) $user['nombre']) ?></div>
                <div class="admin-meta">ID cliente: <?= (int) $user['id'] ?></div>
              </td>
              <td><?= htmlspecialchars((string) $user['email']) ?></td>
              <td><span class="admin-stat-badge is-neutral"><?= (int) ($user['total_pedidos'] ?? 0) ?> pedidos</span></td>
              <td class="fw-bold">$<?= number_format((float) ($user['total_gastado'] ?? 0), 0, ',', '.') ?></td>
              <td><?= !empty($user['ultimo_pedido']) ? htmlspecialchars(date('d/m/Y H:i', strtotime((string) $user['ultimo_pedido']))) : '<span class="admin-meta">Sin pedidos</span>' ?></td>
              <td>
                <form method="post"
                      data-confirm="true"
                      data-confirm-title="Actualizar rol"
                      data-confirm-message="Cambiar el rol afecta el acceso del usuario al panel administrativo."
                      data-confirm-button="Guardar rol"
                      data-confirm-variant="btn-primary"
                      class="d-flex align-items-center gap-2 flex-wrap">
                  <input type="hidden" name="usuario_id" value="<?= (int) $user['id'] ?>">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(appCsrfToken('admin_users_role')) ?>">
                  <select name="nuevo_rol" class="form-select form-select-sm" style="min-width: 130px;">
                    <option value="cliente" <?= ($user['rol'] ?? '') === 'cliente' ? 'selected' : '' ?>>Cliente</option>
                    <option value="admin" <?= ($user['rol'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                  </select>
                  <span class="admin-stat-badge <?= htmlspecialchars($rolMeta['class']) ?>"><?= htmlspecialchars($rolMeta['label']) ?></span>
                  <button type="submit" class="btn btn-admin-ghost btn-sm">Guardar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="modal fade admin-modal" id="usuariosAnalyticsModal" tabindex="-1" aria-labelledby="usuariosAnalyticsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h2 class="modal-title" id="usuariosAnalyticsModalLabel">Analitica de usuarios</h2>
          <div class="admin-meta">Vista ampliada para entender composicion de la base y clientes con mayor valor.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div class="chart-grid">
          <div class="admin-card-soft chart-card">
            <div class="admin-card-header"><h3 class="admin-card-title">Distribucion de roles</h3></div>
            <canvas id="usersRoleChart"></canvas>
            <div class="chart-actions">
              <button type="button" class="btn btn-admin-soft btn-sm" data-download-chart="usersRoleChart" data-filename="usuarios-distribucion-roles"><i class="bi bi-image me-1"></i>Descargar PNG</button>
            </div>
          </div>
          <div class="admin-card-soft chart-card chart-card-lg">
            <div class="admin-card-header"><h3 class="admin-card-title">Top compradores por facturacion</h3></div>
            <canvas id="usersSpendChart"></canvas>
            <div class="chart-actions">
              <button type="button" class="btn btn-admin-soft btn-sm" data-download-chart="usersSpendChart" data-filename="usuarios-top-facturacion"><i class="bi bi-image me-1"></i>Descargar PNG</button>
            </div>
          </div>
          <div class="admin-card-soft chart-card chart-card-lg">
            <div class="admin-card-header"><h3 class="admin-card-title">Usuarios con mas pedidos</h3></div>
            <canvas id="usersOrdersChart"></canvas>
            <div class="chart-actions">
              <button type="button" class="btn btn-admin-soft btn-sm" data-download-chart="usersOrdersChart" data-filename="usuarios-top-pedidos"><i class="bi bi-image me-1"></i>Descargar PNG</button>
            </div>
          </div>
          <div class="admin-card-soft chart-card chart-card-lg">
            <div class="admin-card-header"><h3 class="admin-card-title">Resumen descargable</h3></div>
            <div class="admin-empty h-100">
              <p class="mb-3">Descarga un CSV con resumen de usuarios, composicion por rol y detalle completo de la base.</p>
              <button type="button" class="btn btn-admin-primary" id="btnDescargarUsuariosCsvModal"><i class="bi bi-download me-2"></i>Descargar estadisticas CSV</button>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer justify-content-between">
        <div class="admin-meta">Usuarios: <?= $totalUsuarios ?> · Compradores activos: <?= $compradoresActivos ?></div>
        <button type="button" class="btn btn-admin-ghost" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<script>
window.ADMIN_USERS_DATA = <?= json_encode([
    'summary' => [
        'totalUsuarios' => $totalUsuarios,
        'totalAdmins' => $totalAdmins,
        'totalClientes' => $totalClientes,
        'compradoresActivos' => $compradoresActivos,
        'facturacionUsuarios' => round($facturacionUsuarios, 2),
        'ticketPromedioUsuario' => round($ticketPromedioUsuario, 2)
    ],
    'roles' => [
        'labels' => $roleLabels,
        'values' => $roleValues
    ],
    'spenders' => [
        'labels' => $spenderLabels,
        'values' => $spenderValues
    ],
    'orders' => [
        'labels' => $orderLabels,
        'values' => $orderValues
    ],
    'rows' => array_map(static function (array $row): array {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'nombre' => (string) ($row['nombre'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'rol' => (string) ($row['rol'] ?? ''),
            'total_pedidos' => (int) ($row['total_pedidos'] ?? 0),
            'total_gastado' => (float) ($row['total_gastado'] ?? 0),
            'ultimo_pedido' => (string) ($row['ultimo_pedido'] ?? '')
        ];
    }, $usuarios)
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="../assets/js/admin-users.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php
$appAssetPrefix = '../';
include '../includes/ui_footer.php';
?>
</body>
</html>
