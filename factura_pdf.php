<?php
session_start();
require 'includes/fpdf/fpdf.php';
include 'includes/conexion.php';
include 'includes/pedidos_utils.php';
include 'includes/factura_layout.php';

if (!isset($_SESSION['usuario']) || !isset($_GET['id'])) {
  die('Acceso denegado.');
}

$idPedido = (int) $_GET['id'];
$usuarioId = (int) $_SESSION['usuario']['id'];
$esAdmin = (($_SESSION['usuario']['rol'] ?? '') === 'admin');

if ($esAdmin) {
  $stmt = $conn->prepare("
    SELECT p.*, u.nombre, u.email
    FROM pedidos p
    JOIN usuarios u ON p.usuario_id = u.id
    WHERE p.id = ?
  ");
  $stmt->execute([$idPedido]);
} else {
  $stmt = $conn->prepare("
    SELECT p.*, u.nombre, u.email
    FROM pedidos p
    JOIN usuarios u ON p.usuario_id = u.id
    WHERE p.id = ? AND p.usuario_id = ?
  ");
  $stmt->execute([$idPedido, $usuarioId]);
}
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pedido) {
  die('Pedido no encontrado.');
}

$token = facturaAsegurarToken($conn, $pedido);
$urlPublica = facturaConstruirUrlPublica($token);

$detalle = $conn->prepare("
  SELECT
    dp.talla,
    dp.nombre_producto,
    dp.cantidad,
    dp.precio_unitario,
    COALESCE(p.sku, '-') AS sku
  FROM detalle_pedido dp
  LEFT JOIN productos p ON p.id = dp.producto_id
  WHERE dp.pedido_id = ?
");
$detalle->execute([$idPedido]);
$productos = $detalle->fetchAll(PDO::FETCH_ASSOC);

$pdf = new FPDF();
$pdf->AddPage();
facturaRenderizar($pdf, $pedido, $productos, $urlPublica);
facturaLimpiarSalidaAntesDePdf();
header('Content-Type: application/pdf');
$pdf->Output('I', 'factura_pedido_' . (int) $pedido['id'] . '.pdf');
