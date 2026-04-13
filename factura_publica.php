<?php
require 'includes/fpdf/fpdf.php';
include 'includes/conexion.php';
include 'includes/pedidos_utils.php';
include 'includes/factura_layout.php';

$token = trim((string) ($_GET['token'] ?? ''));
if ($token === '' || !preg_match('/^[a-f0-9]{32,64}$/', $token)) {
  die('Token de factura no valido.');
}

$stmt = $conn->prepare("
  SELECT p.*, u.nombre, u.email
  FROM pedidos p
  JOIN usuarios u ON p.usuario_id = u.id
  WHERE p.factura_token = ?
  LIMIT 1
");
$stmt->execute([$token]);
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pedido) {
  die('Factura no encontrada.');
}

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
$detalle->execute([(int) $pedido['id']]);
$productos = $detalle->fetchAll(PDO::FETCH_ASSOC);

$urlPublica = facturaConstruirUrlPublica($token);

$pdf = new FPDF();
$pdf->AddPage();
facturaRenderizar($pdf, $pedido, $productos, $urlPublica);
facturaLimpiarSalidaAntesDePdf();
header('Content-Type: application/pdf');
$pdf->Output('I', 'factura_pedido_' . (int) $pedido['id'] . '.pdf');
