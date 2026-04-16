<?php
require_once 'includes/app.php';
if (!isset($_SESSION['usuario'])) {
  header("Location: login.php");
  exit;
}

include 'includes/conexion.php';
include 'includes/pedidos_utils.php';

$idPedido = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$usuarioId = (int) $_SESSION['usuario']['id'];
$esAdmin = ($_SESSION['usuario']['rol'] ?? '') === 'admin';
$mensajeEstado = '';
$errorEstado = '';
$etiquetasEstado = etiquetasEstadoPedido();

if ($idPedido <= 0) {
  include 'header.php';
  echo "<div class='container py-5'><div class='alert alert-danger text-center'>Pedido no encontrado.</div></div>";
  include 'footer.php';
  exit;
}

$pedidoSql = $esAdmin
  ? "SELECT * FROM pedidos WHERE id = ?"
  : "SELECT * FROM pedidos WHERE id = ? AND usuario_id = ?";
$pedidoParams = $esAdmin ? [$idPedido] : [$idPedido, $usuarioId];
$pedidoStmt = $conn->prepare($pedidoSql);
$pedidoStmt->execute($pedidoParams);
$pedido = $pedidoStmt->fetch(PDO::FETCH_ASSOC);

if (!$pedido) {
  include 'header.php';
  echo "<div class='container py-5'><div class='alert alert-warning text-center'>Pedido no disponible.</div></div>";
  include 'footer.php';
  exit;
}

if (isset($_POST['cancelar']) && !$esAdmin) {
  if (!appValidarCsrf('cancelar_pedido_form', $_POST['csrf_token'] ?? null)) {
    $errorEstado = 'La sesion del formulario expiro. Intenta nuevamente.';
  } else {
    $estadoActual = normalizarTextoPedido((string) ($pedido['estado'] ?? ''));
    if (!in_array($estadoActual, ['pendiente', 'pagado'], true)) {
      $errorEstado = 'Solo puedes cancelar pedidos en estado pendiente o pagado.';
    } else {
      $resultado = actualizarEstadoPedido(
        $conn,
        $idPedido,
        'cancelado',
        $usuarioId,
        'cliente',
        'Cancelado por cliente desde ver_pedido'
      );

      if ($resultado['ok']) {
        appFlash('success', 'Pedido cancelado correctamente y stock reintegrado.', 'Pedido cancelado');
        appRedirect('ver_pedido.php?id=' . $idPedido);
      } else {
        $errorEstado = $resultado['mensaje'] ?? 'No se pudo cancelar el pedido.';
      }
    }
  }
}

$detalleStmt = $conn->prepare("
  SELECT producto_id, talla, nombre_producto, cantidad, precio_unitario
  FROM detalle_pedido
  WHERE pedido_id = ?
");
$detalleStmt->execute([$idPedido]);
$productos = $detalleStmt->fetchAll(PDO::FETCH_ASSOC);

$hitosEstado = [
  'pendiente' => $pedido['estado_pendiente_at'] ?? null,
  'pagado' => $pedido['estado_pagado_at'] ?? null,
  'preparando' => $pedido['estado_preparando_at'] ?? null,
  'enviado' => $pedido['estado_enviado_at'] ?? null,
  'entregado' => $pedido['estado_entregado_at'] ?? null,
  'cancelado' => $pedido['estado_cancelado_at'] ?? null
];

include 'header.php';
?>

<div class="container py-5">
  <h1 class="text-center mb-4">Detalle del pedido</h1>

  <?php if ($mensajeEstado !== ''): ?>
    <div class="alert alert-success"><?= htmlspecialchars($mensajeEstado) ?></div>
  <?php endif; ?>
  <?php if ($errorEstado !== ''): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($errorEstado) ?></div>
  <?php endif; ?>

  <div class="card shadow-sm mb-4">
    <div class="card-body">
      <?php
        $subtotalProductos = (float) ($pedido['subtotal_productos'] ?? $pedido['total']);
        $costoEnvio = (float) ($pedido['costo_envio'] ?? 0);
        $totalPedido = (float) ($pedido['total'] ?? 0);
        $ivaMonto = isset($pedido['iva_monto']) ? (float) $pedido['iva_monto'] : max($totalPedido - $subtotalProductos - $costoEnvio, 0);
        $ivaRate = isset($pedido['iva_rate']) ? (float) $pedido['iva_rate'] : 0.19;
        $mostrarIva = $ivaMonto > 0.005;
      ?>

      <p><strong>Pedido #:</strong> <?= (int) $pedido['id'] ?></p>
      <p><strong>Fecha:</strong> <?= htmlspecialchars((string) $pedido['fecha']) ?></p>
      <p><strong>Modalidad:</strong> <?= htmlspecialchars(etiquetaMetodoPagoPedido((string) ($pedido['metodo_pago'] ?? ''))) ?></p>
      <p><strong>Estado:</strong>
        <span class="badge bg-secondary">
          <?= htmlspecialchars($etiquetasEstado[normalizarTextoPedido((string) $pedido['estado'])] ?? ucfirst((string) $pedido['estado'])) ?>
        </span>
      </p>
      <p><strong>Subtotal productos<?= $mostrarIva ? ' (sin IVA)' : '' ?>:</strong> $<?= number_format($subtotalProductos, 0, ',', '.') ?></p>
      <?php if ($mostrarIva): ?>
        <p><strong>IVA (<?= (int) round($ivaRate * 100) ?>%):</strong> $<?= number_format($ivaMonto, 0, ',', '.') ?></p>
      <?php endif; ?>
      <p><strong>Costo envio:</strong> $<?= number_format($costoEnvio, 0, ',', '.') ?></p>
      <p><strong>Total:</strong> $<?= number_format($totalPedido, 0, ',', '.') ?></p>

      <?php if (
        !$esAdmin &&
        in_array(normalizarTextoPedido((string) $pedido['estado']), ['pendiente', 'pagado'], true)
      ): ?>
        <form method="post"
              data-confirm="true"
              data-confirm-title="Cancelar pedido"
              data-confirm-message="Se cancelara el pedido y el stock volvera al inventario. Esta accion no se puede deshacer.">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(appCsrfToken('cancelar_pedido_form')) ?>">
          <button type="submit" name="cancelar" class="btn btn-danger mt-3">
            Cancelar pedido y reintegrar stock
          </button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <?php if (($pedido['metodo_pago'] ?? '') === 'entrega'): ?>
    <div class="card shadow-sm mb-4">
      <div class="card-header fw-bold">Informacion de envio</div>
      <div class="card-body">
        <p><strong>Nombre:</strong> <?= htmlspecialchars((string) ($pedido['nombre_envio'] ?? 'No registrado')) ?></p>
        <p><strong>Telefono:</strong> <?= htmlspecialchars((string) ($pedido['telefono_envio'] ?? 'No registrado')) ?></p>
        <p><strong>Direccion:</strong> <?= htmlspecialchars((string) ($pedido['direccion_envio'] ?? 'No registrada')) ?></p>
        <p><strong>Barrio:</strong> <?= htmlspecialchars((string) ($pedido['barrio_envio'] ?? 'No registrado')) ?></p>
        <p><strong>Ciudad:</strong> <?= htmlspecialchars(textoTituloPedido((string) ($pedido['ciudad_envio'] ?? ''))) ?></p>
        <p><strong>Zona:</strong> <?= htmlspecialchars(textoTituloPedido((string) ($pedido['zona_envio'] ?? ''))) ?></p>
        <p><strong>Entrega estimada:</strong>
          <?php if (!empty($pedido['dias_entrega_min']) && !empty($pedido['dias_entrega_max'])): ?>
            <?= (int) $pedido['dias_entrega_min'] ?> - <?= (int) $pedido['dias_entrega_max'] ?> dias
          <?php else: ?>
            No definida
          <?php endif; ?>
        </p>
      </div>
    </div>
  <?php endif; ?>

  <?php if (($pedido['metodo_pago'] ?? '') === 'recoger_tienda'): ?>
    <div class="card shadow-sm mb-4">
      <div class="card-header fw-bold">Recogida</div>
      <div class="card-body">
        <p class="mb-0"><strong>Modalidad:</strong> Recoger en tienda</p>
      </div>
    </div>
  <?php endif; ?>

  <div class="card shadow-sm mb-4">
    <div class="card-header fw-bold">Seguimiento de estado</div>
    <div class="card-body">
      <ul class="list-group list-group-flush">
        <?php foreach ($hitosEstado as $estadoKey => $fechaEstado): ?>
          <li class="list-group-item d-flex justify-content-between align-items-center">
            <span><?= htmlspecialchars($etiquetasEstado[$estadoKey] ?? ucfirst($estadoKey)) ?></span>
            <span class="text-muted">
              <?= $fechaEstado ? htmlspecialchars(date('d/m/Y H:i', strtotime((string) $fechaEstado))) : 'Sin registro' ?>
            </span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>

  <h4 class="mb-3">Productos del pedido</h4>
  <div class="table-responsive">
    <table class="table table-bordered table-hover text-center align-middle" data-datatable="true" data-page-length="5">
      <thead class="table-light">
        <tr>
          <th>Producto</th>
          <th>Talla</th>
          <th>Precio<?= $mostrarIva ? ' (sin IVA)' : '' ?></th>
          <th>Cantidad</th>
          <th>Subtotal<?= $mostrarIva ? ' (sin IVA)' : '' ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($productos as $p):
          $nombreProducto = (string) ($p['nombre_producto'] ?? '');
          $tallaProducto = trim((string) ($p['talla'] ?? ''));
          if ($tallaProducto === '' && preg_match('/^(.*)\s-\sTalla\s(.+)$/u', $nombreProducto, $coincidencias)) {
            $nombreProducto = trim($coincidencias[1]);
            $tallaProducto = trim($coincidencias[2]);
          }
        ?>
          <tr>
            <td><?= htmlspecialchars($nombreProducto) ?></td>
            <td><?= $tallaProducto !== '' ? htmlspecialchars($tallaProducto) : '-' ?></td>
            <td>$<?= number_format((float) $p['precio_unitario'], 0, ',', '.') ?></td>
            <td><?= (int) $p['cantidad'] ?></td>
            <td>$<?= number_format((float) $p['precio_unitario'] * (int) $p['cantidad'], 0, ',', '.') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="text-center mt-4 d-grid gap-3">
    <a href="<?= $esAdmin ? 'admin/pedidos.php' : 'perfil.php' ?>" class="btn btn-secondary btn-lg">Volver</a>

    <?php if (!$esAdmin): ?>
      <button class="btn btn-outline-success btn-lg" onclick="repetirPedido()">Repetir pedido</button>
    <?php endif; ?>

    <a href="factura_pdf.php?id=<?= (int) $pedido['id'] ?>" target="_blank" class="btn btn-primary btn-lg">
      Descargar factura
    </a>
  </div>

</div>

<?php if (!$esAdmin): ?>
<script>
function repetirPedido() {
  const productos = <?= json_encode($productos) ?>;
  let carrito = JSON.parse(localStorage.getItem("carrito")) || [];

  productos.forEach(p => {
    const nombreCompleto = String(p.nombre_producto || '');
    let talla = String(p.talla || '').trim();
    let nombreBase = nombreCompleto;

    if (!talla && nombreCompleto.includes(' - Talla ')) {
      const partes = nombreCompleto.split(' - Talla ');
      nombreBase = partes[0];
      talla = String(partes[1] || '').trim();
    }

    const existente = carrito.find(item =>
      Number(item.id) === Number(p.producto_id) &&
      String(item.talla || '') === String(talla || '')
    );

    if (existente) {
      existente.cantidad = Number(existente.cantidad || 1) + Number(p.cantidad || 1);
    } else {
      carrito.push({
        id: Number(p.producto_id),
        nombre: nombreBase,
        precio: Number(p.precio_unitario || 0),
        talla: talla || null,
        cantidad: Number(p.cantidad || 1)
      });
    }
  });

  localStorage.setItem("carrito", JSON.stringify(carrito));
  alert("Productos agregados al carrito.");
  window.location.href = "carrito.php";
}
</script>
<?php endif; ?>

<?php include 'footer.php'; ?>
