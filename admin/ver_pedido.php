<?php
require_once '../includes/app.php';
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'admin') {
  header("Location: ../login.php");
  exit;
}

include '../includes/conexion.php';
include '../includes/pedidos_utils.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
  echo "Pedido no encontrado.";
  exit;
}

$pedido = $conn->prepare("SELECT p.*, u.nombre FROM pedidos p LEFT JOIN usuarios u ON p.usuario_id = u.id WHERE p.id = ?");
$pedido->execute([$id]);
$info = $pedido->fetch(PDO::FETCH_ASSOC);
$etiquetasEstado = etiquetasEstadoPedido();

if (!$info) {
  echo "Pedido no encontrado.";
  exit;
}

$detalle = $conn->prepare("SELECT talla, nombre_producto, cantidad, precio_unitario FROM detalle_pedido WHERE pedido_id = ?");
$detalle->execute([$id]);
$productos = $detalle->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Detalle del pedido</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <?php include '../includes/ui_head.php'; ?>
</head>
<body style="background:#f4f7fa;">
  <div class="container py-5">
    <div class="card shadow-sm border-0 rounded-4 mb-4">
      <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
          <h1 class="h3 mb-0">Detalle del pedido #<?= (int) $info['id'] ?></h1>
          <a href="pedidos.php" class="btn btn-outline-primary btn-sm">Volver</a>
        </div>
        <p class="mb-1"><strong>Usuario:</strong> <?= htmlspecialchars($info['nombre'] ?? 'No registrado') ?></p>
        <p class="mb-1"><strong>Estado:</strong> <?= htmlspecialchars($etiquetasEstado[normalizarTextoPedido((string) $info['estado'])] ?? ucfirst((string) $info['estado'])) ?></p>
        <p class="mb-1"><strong>Subtotal productos:</strong> $<?= number_format((float) ($info['subtotal_productos'] ?? 0), 2) ?></p>
        <p class="mb-1"><strong>Costo envio:</strong> $<?= number_format((float) ($info['costo_envio'] ?? 0), 2) ?></p>
        <p class="mb-1"><strong>Total:</strong> $<?= number_format((float) $info['total'], 2) ?></p>
        <p class="mb-0"><strong>Fecha:</strong> <?= htmlspecialchars($info['fecha']) ?></p>
      </div>
    </div>

    <?php if (($info['metodo_pago'] ?? '') === 'entrega'): ?>
      <div class="card shadow-sm border-0 rounded-4 mb-4">
        <div class="card-body p-4">
          <h2 class="h5 mb-3">Envio</h2>
          <p class="mb-1"><strong>Ciudad:</strong> <?= htmlspecialchars(textoTituloPedido($info['ciudad_envio'] ?? '')) ?></p>
          <p class="mb-1"><strong>Zona:</strong> <?= htmlspecialchars(textoTituloPedido($info['zona_envio'] ?? '')) ?></p>
          <p class="mb-1"><strong>Direccion:</strong> <?= htmlspecialchars((string) ($info['direccion_envio'] ?? 'No registrada')) ?></p>
          <p class="mb-1"><strong>Barrio:</strong> <?= htmlspecialchars((string) ($info['barrio_envio'] ?? 'No registrado')) ?></p>
          <p class="mb-0"><strong>Entrega estimada:</strong>
            <?php if (!empty($info['dias_entrega_min']) && !empty($info['dias_entrega_max'])): ?>
              <?= (int) $info['dias_entrega_min'] ?> - <?= (int) $info['dias_entrega_max'] ?> dias
            <?php else: ?>
              No definida
            <?php endif; ?>
          </p>
        </div>
      </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0 rounded-4 mb-4">
      <div class="card-body p-4">
        <h2 class="h5 mb-3">Seguimiento</h2>
        <?php
          $hitos = [
            'pendiente' => $info['estado_pendiente_at'] ?? null,
            'pagado' => $info['estado_pagado_at'] ?? null,
            'preparando' => $info['estado_preparando_at'] ?? null,
            'enviado' => $info['estado_enviado_at'] ?? null,
            'entregado' => $info['estado_entregado_at'] ?? null,
            'cancelado' => $info['estado_cancelado_at'] ?? null
          ];
        ?>
        <ul class="list-group list-group-flush">
          <?php foreach ($hitos as $estadoKey => $fechaEstado): ?>
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

    <div class="card shadow-sm border-0 rounded-4">
      <div class="card-body p-4">
        <h2 class="h5 mb-3">Productos incluidos</h2>
        <div class="table-responsive">
          <table class="table table-hover align-middle text-center mb-0" data-datatable="true" data-page-length="5">
            <thead class="table-light">
              <tr>
                <th>Producto</th>
                <th>Talla</th>
                <th>Cantidad</th>
                <th>Precio unitario</th>
                <th>Subtotal</th>
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
                  <td><?= (int) $p['cantidad'] ?></td>
                  <td>$<?= number_format((float) $p['precio_unitario'], 2) ?></td>
                  <td>$<?= number_format((float) $p['cantidad'] * (float) $p['precio_unitario'], 2) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <?php
  $appAssetPrefix = '../';
  include '../includes/ui_footer.php';
  ?>
</body>
</html>
