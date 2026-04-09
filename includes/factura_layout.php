<?php

function facturaPdfText(string $text): string
{
  return utf8_decode($text);
}

function facturaTruncar(string $text, int $max): string
{
  $text = trim($text);
  if ($text === '') {
    return '-';
  }

  if (function_exists('mb_strwidth')) {
    if (mb_strwidth($text, 'UTF-8') <= $max) {
      return $text;
    }
    return rtrim(mb_strimwidth($text, 0, $max - 1, '', 'UTF-8')) . '...';
  }

  if (strlen($text) <= $max) {
    return $text;
  }

  return rtrim(substr($text, 0, $max - 1)) . '...';
}

function facturaGenerarCodigo(int $idPedido): string
{
  return 'FAC-' . date('Ymd') . '-' . str_pad((string) $idPedido, 5, '0', STR_PAD_LEFT);
}

function facturaConstruirBaseUrl(): string
{
  $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
  $scheme = $https ? 'https' : 'http';
  $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
  $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
  $dir = rtrim($dir, '/');
  return $scheme . '://' . $host . ($dir !== '' ? $dir : '');
}

function facturaConstruirUrlPublica(string $token): string
{
  $base = facturaConstruirBaseUrl();
  return $base . '/factura_publica.php?token=' . rawurlencode($token);
}

function facturaAsegurarToken(PDO $conn, array &$pedido): string
{
  $token = trim((string) ($pedido['factura_token'] ?? ''));
  if ($token !== '') {
    return $token;
  }

  $token = bin2hex(random_bytes(24));
  $upd = $conn->prepare("UPDATE pedidos SET factura_token = ? WHERE id = ?");
  $upd->execute([$token, (int) $pedido['id']]);
  $pedido['factura_token'] = $token;
  return $token;
}

function facturaDescargarQrTemporal(string $url): ?string
{
  if ($url === '') {
    return null;
  }

  $endpoint = 'https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=' . rawurlencode($url);
  $context = stream_context_create([
    'http' => ['timeout' => 6],
    'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
  ]);

  $contenido = @file_get_contents($endpoint, false, $context);
  if ($contenido === false || strlen($contenido) < 100) {
    return null;
  }

  $tmp = @tempnam(sys_get_temp_dir(), 'qr_');
  if (!$tmp) {
    return null;
  }

  if (@file_put_contents($tmp, $contenido) === false) {
    @unlink($tmp);
    return null;
  }

  return $tmp;
}

function facturaRenderizar(FPDF $pdf, array $pedido, array $productos, string $urlPublica): void
{
  $etiquetasEstado = etiquetasEstadoPedido();
  $estadoKey = normalizarTextoPedido((string) ($pedido['estado'] ?? 'pendiente'));
  $estadoEtiqueta = $etiquetasEstado[$estadoKey] ?? ucfirst((string) ($pedido['estado'] ?? 'Pendiente'));

  $subtotalProductos = (float) ($pedido['subtotal_productos'] ?? 0);
  $costoEnvio = (float) ($pedido['costo_envio'] ?? 0);
  $totalPedido = (float) ($pedido['total'] ?? ($subtotalProductos + $costoEnvio));
  $codigoFactura = facturaGenerarCodigo((int) $pedido['id']);

  $pdf->SetAutoPageBreak(true, 18);

  $colorPrimario = [30, 74, 102];
  $colorSecundario = [44, 110, 146];
  $colorBorde = [208, 219, 229];
  $colorTextoSuave = [87, 103, 114];
  $colorBlanco = [255, 255, 255];

  $pdf->SetFillColor($colorPrimario[0], $colorPrimario[1], $colorPrimario[2]);
  $pdf->Rect(0, 0, 210, 34, 'F');

  if (file_exists('assets/img/logo.png')) {
    $pdf->Image('assets/img/logo.png', 12, 7, 30);
  }

  $pdf->SetTextColor($colorBlanco[0], $colorBlanco[1], $colorBlanco[2]);
  $pdf->SetFont('Arial', 'B', 16);
  $pdf->SetXY(46, 8);
  $pdf->Cell(95, 8, facturaPdfText('Tauro Store'), 0, 2, 'L');
  $pdf->SetFont('Arial', '', 10);
  $pdf->Cell(95, 6, facturaPdfText('Moda masculina y accesorios'), 0, 0, 'L');

  $pdf->SetXY(142, 8);
  $pdf->SetFont('Arial', 'B', 13);
  $pdf->Cell(56, 8, facturaPdfText('FACTURA DE VENTA'), 0, 2, 'R');
  $pdf->SetFont('Arial', '', 9);
  $pdf->Cell(56, 5, facturaPdfText('No: ' . $codigoFactura), 0, 2, 'R');
  $pdf->Cell(56, 5, facturaPdfText('Fecha emision: ' . date('d/m/Y H:i')), 0, 0, 'R');

  $pdf->SetTextColor(0, 0, 0);
  $y = 40;

  $pdf->SetDrawColor($colorBorde[0], $colorBorde[1], $colorBorde[2]);
  $pdf->SetFillColor(248, 251, 253);
  $pdf->Rect(10, $y, 92, 36, 'DF');
  $pdf->Rect(108, $y, 92, 36, 'DF');

  $pdf->SetFont('Arial', 'B', 10);
  $pdf->SetXY(13, $y + 3);
  $pdf->Cell(86, 6, facturaPdfText('EMISOR'), 0, 1);
  $pdf->SetFont('Arial', '', 9);
  $pdf->SetX(13);
  $pdf->Cell(86, 5, facturaPdfText('Tauro Store S.A.S'), 0, 1);
  $pdf->SetX(13);
  $pdf->Cell(86, 5, facturaPdfText('NIT: 900123456-7'), 0, 1);
  $pdf->SetX(13);
  $pdf->Cell(86, 5, facturaPdfText('Email: soporte@taurostore.com'), 0, 1);
  $pdf->SetX(13);
  $pdf->Cell(86, 5, facturaPdfText('Telefono: +57 300 000 0000'), 0, 1);

  $pdf->SetFont('Arial', 'B', 10);
  $pdf->SetXY(111, $y + 3);
  $pdf->Cell(86, 6, facturaPdfText('CLIENTE'), 0, 1);
  $pdf->SetFont('Arial', '', 9);
  $pdf->SetX(111);
  $pdf->Cell(86, 5, facturaPdfText(facturaTruncar((string) ($pedido['nombre'] ?? 'No registrado'), 42)), 0, 1);
  $pdf->SetX(111);
  $pdf->Cell(86, 5, facturaPdfText(facturaTruncar((string) ($pedido['email'] ?? 'No registrado'), 42)), 0, 1);
  $pdf->SetX(111);
  $pdf->Cell(86, 5, facturaPdfText('Pedido #: ' . (int) $pedido['id']), 0, 1);
  $pdf->SetX(111);
  $pdf->Cell(86, 5, facturaPdfText('Fecha pedido: ' . date('d/m/Y H:i', strtotime((string) $pedido['fecha']))), 0, 1);

  $y += 41;

  $pdf->SetFillColor(248, 251, 253);
  $pdf->Rect(10, $y, 190, 22, 'DF');
  $pdf->SetFont('Arial', 'B', 9);
  $pdf->SetXY(13, $y + 3);
  $pdf->Cell(38, 5, facturaPdfText('Estado'), 0, 0);
  $pdf->Cell(48, 5, facturaPdfText('Metodo pago'), 0, 0);
  $pdf->Cell(52, 5, facturaPdfText('Subtotal productos'), 0, 0);
  $pdf->Cell(48, 5, facturaPdfText('Costo envio'), 0, 1);
  $pdf->SetFont('Arial', '', 9);
  $pdf->SetX(13);
  $pdf->Cell(38, 7, facturaPdfText($estadoEtiqueta), 0, 0);
  $pdf->Cell(48, 7, facturaPdfText(ucfirst((string) ($pedido['metodo_pago'] ?? 'No definido'))), 0, 0);
  $pdf->Cell(52, 7, '$' . number_format($subtotalProductos, 0, ',', '.'), 0, 0);
  $pdf->Cell(48, 7, '$' . number_format($costoEnvio, 0, ',', '.'), 0, 1);

  $y += 27;

  if (($pedido['metodo_pago'] ?? '') === 'entrega') {
    $pdf->SetFillColor(248, 251, 253);
    $pdf->Rect(10, $y, 190, 28, 'DF');
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetXY(13, $y + 3);
    $pdf->Cell(184, 5, facturaPdfText('DATOS DE ENVIO'), 0, 1);
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetX(13);
    $pdf->Cell(92, 5, facturaPdfText('Recibe: ' . facturaTruncar((string) ($pedido['nombre_envio'] ?? 'No registrado'), 36)), 0, 0);
    $pdf->Cell(92, 5, facturaPdfText('Telefono: ' . facturaTruncar((string) ($pedido['telefono_envio'] ?? 'No registrado'), 26)), 0, 1);
    $pdf->SetX(13);
    $pdf->Cell(92, 5, facturaPdfText('Ciudad: ' . textoTituloPedido((string) ($pedido['ciudad_envio'] ?? ''))), 0, 0);
    $pdf->Cell(92, 5, facturaPdfText('Zona: ' . textoTituloPedido((string) ($pedido['zona_envio'] ?? ''))), 0, 1);
    $entregaEstimada = (!empty($pedido['dias_entrega_min']) && !empty($pedido['dias_entrega_max']))
      ? ((int) $pedido['dias_entrega_min'] . '-' . (int) $pedido['dias_entrega_max'] . ' dias')
      : 'No definida';
    $direccion = trim((string) ($pedido['direccion_envio'] ?? ''));
    $barrio = trim((string) ($pedido['barrio_envio'] ?? ''));
    $pdf->SetX(13);
    $pdf->Cell(122, 5, facturaPdfText('Direccion: ' . facturaTruncar($direccion . ($barrio !== '' ? ' - ' . $barrio : ''), 64)), 0, 0);
    $pdf->Cell(62, 5, facturaPdfText('Entrega: ' . $entregaEstimada), 0, 1);
    $y += 33;
  }

  $pdf->SetFillColor($colorSecundario[0], $colorSecundario[1], $colorSecundario[2]);
  $pdf->SetTextColor(255, 255, 255);
  $pdf->SetFont('Arial', 'B', 9);
  $pdf->SetX(10);
  $pdf->Cell(58, 8, facturaPdfText('Producto'), 1, 0, 'C', true);
  $pdf->Cell(28, 8, facturaPdfText('SKU'), 1, 0, 'C', true);
  $pdf->Cell(18, 8, facturaPdfText('Talla'), 1, 0, 'C', true);
  $pdf->Cell(20, 8, facturaPdfText('Cant.'), 1, 0, 'C', true);
  $pdf->Cell(30, 8, facturaPdfText('Unitario'), 1, 0, 'C', true);
  $pdf->Cell(36, 8, facturaPdfText('Subtotal'), 1, 1, 'C', true);

  $pdf->SetTextColor(0, 0, 0);
  $pdf->SetFont('Arial', '', 9);

  $totalLineas = 0.0;
  if (!$productos) {
    $pdf->SetX(10);
    $pdf->Cell(190, 9, facturaPdfText('No hay lineas para este pedido.'), 1, 1, 'C');
  } else {
    foreach ($productos as $p) {
      $nombreProducto = (string) ($p['nombre_producto'] ?? '');
      $tallaProducto = trim((string) ($p['talla'] ?? ''));
      if ($tallaProducto === '' && preg_match('/^(.*)\s-\sTalla\s(.+)$/u', $nombreProducto, $m)) {
        $nombreProducto = trim((string) $m[1]);
        $tallaProducto = trim((string) $m[2]);
      }

      $cantidad = (int) ($p['cantidad'] ?? 0);
      $unitario = (float) ($p['precio_unitario'] ?? 0);
      $subtotal = $cantidad * $unitario;
      $totalLineas += $subtotal;

      if ($pdf->GetY() > 255) {
        $pdf->AddPage();
        $pdf->SetFillColor($colorSecundario[0], $colorSecundario[1], $colorSecundario[2]);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetX(10);
        $pdf->Cell(58, 8, facturaPdfText('Producto'), 1, 0, 'C', true);
        $pdf->Cell(28, 8, facturaPdfText('SKU'), 1, 0, 'C', true);
        $pdf->Cell(18, 8, facturaPdfText('Talla'), 1, 0, 'C', true);
        $pdf->Cell(20, 8, facturaPdfText('Cant.'), 1, 0, 'C', true);
        $pdf->Cell(30, 8, facturaPdfText('Unitario'), 1, 0, 'C', true);
        $pdf->Cell(36, 8, facturaPdfText('Subtotal'), 1, 1, 'C', true);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('Arial', '', 9);
      }

      $pdf->SetX(10);
      $pdf->Cell(58, 8, facturaPdfText(facturaTruncar($nombreProducto, 35)), 1, 0, 'L');
      $pdf->Cell(28, 8, facturaPdfText(facturaTruncar((string) ($p['sku'] ?? '-'), 17)), 1, 0, 'C');
      $pdf->Cell(18, 8, facturaPdfText($tallaProducto !== '' ? $tallaProducto : '-'), 1, 0, 'C');
      $pdf->Cell(20, 8, (string) $cantidad, 1, 0, 'C');
      $pdf->Cell(30, 8, '$' . number_format($unitario, 0, ',', '.'), 1, 0, 'R');
      $pdf->Cell(36, 8, '$' . number_format($subtotal, 0, ',', '.'), 1, 1, 'R');
    }
  }

  $pdf->Ln(4);

  $xResumen = 110;
  $wLabel = 54;
  $wValor = 36;

  $pdf->SetDrawColor($colorBorde[0], $colorBorde[1], $colorBorde[2]);
  $pdf->SetFont('Arial', '', 9);
  $pdf->SetX($xResumen);
  $pdf->Cell($wLabel, 8, facturaPdfText('Subtotal productos'), 1, 0, 'L');
  $pdf->Cell($wValor, 8, '$' . number_format($totalLineas, 0, ',', '.'), 1, 1, 'R');
  $pdf->SetX($xResumen);
  $pdf->Cell($wLabel, 8, facturaPdfText('Costo envio'), 1, 0, 'L');
  $pdf->Cell($wValor, 8, '$' . number_format($costoEnvio, 0, ',', '.'), 1, 1, 'R');

  $pdf->SetFont('Arial', 'B', 10);
  $pdf->SetFillColor(234, 244, 250);
  $pdf->SetX($xResumen);
  $pdf->Cell($wLabel, 9, facturaPdfText('TOTAL A PAGAR'), 1, 0, 'L', true);
  $pdf->Cell($wValor, 9, '$' . number_format($totalPedido, 0, ',', '.'), 1, 1, 'R', true);

  if ($pdf->GetY() > 232) {
    $pdf->AddPage();
  }

  $pdf->Ln(6);
  $yQr = $pdf->GetY();
  $pdf->SetDrawColor($colorBorde[0], $colorBorde[1], $colorBorde[2]);
  $pdf->Rect(10, $yQr, 190, 36, 'D');

  $qrTemp = facturaDescargarQrTemporal($urlPublica);
  if ($qrTemp && file_exists($qrTemp)) {
    $pdf->Image($qrTemp, 13, $yQr + 3, 30, 30, 'PNG');
    @unlink($qrTemp);
  } else {
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetTextColor($colorSecundario[0], $colorSecundario[1], $colorSecundario[2]);
    $pdf->SetXY(14, $yQr + 14);
    $pdf->Cell(28, 6, facturaPdfText('QR'), 0, 0, 'C');
    $pdf->SetTextColor(0, 0, 0);
  }

  $pdf->SetXY(47, $yQr + 4);
  $pdf->SetFont('Arial', 'B', 9);
  $pdf->Cell(150, 5, facturaPdfText('Verificacion digital de factura'), 0, 2, 'L');
  $pdf->SetFont('Arial', '', 8.5);
  $pdf->SetTextColor($colorTextoSuave[0], $colorTextoSuave[1], $colorTextoSuave[2]);
  $pdf->Cell(150, 5, facturaPdfText('Escanea el QR o abre el enlace para consultar esta factura.'), 0, 2, 'L');
  $pdf->SetTextColor($colorSecundario[0], $colorSecundario[1], $colorSecundario[2]);
  $pdf->SetFont('Arial', 'U', 8);
  $pdf->Cell(150, 5, facturaPdfText(facturaTruncar($urlPublica, 90)), 0, 1, 'L', false, $urlPublica);
  $pdf->SetTextColor(0, 0, 0);

  $pdf->Ln(8);
  $pdf->SetTextColor($colorTextoSuave[0], $colorTextoSuave[1], $colorTextoSuave[2]);
  $pdf->SetFont('Arial', '', 8);
  $pdf->MultiCell(190, 4.8, facturaPdfText('Observaciones: Esta factura respalda la compra realizada en Tauro Store. Si necesitas soporte, escribe a soporte@taurostore.com con el numero de pedido.'));
  $pdf->Ln(2);
  $pdf->SetFont('Arial', 'I', 8);
  $pdf->Cell(190, 5, facturaPdfText('Documento generado automaticamente el ' . date('d/m/Y H:i') . ' - Tauro Store'), 0, 1, 'C');
}
