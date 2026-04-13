<?php

function facturaPdfText(string $text): string
{
  $text = trim(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

  if ($text === '') {
    return '';
  }

  $search = ["\u{2018}", "\u{2019}", "\u{201C}", "\u{201D}", "\u{2013}", "\u{2014}", "\u{2026}", "\u{00A0}"];
  $replace = ["'", "'", '"', '"', '-', '-', '...', ' '];
  $text = str_replace($search, $replace, $text);

  if (function_exists('iconv')) {
    $converted = @iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $text);
    if ($converted !== false) {
      return $converted;
    }
  }

  if (function_exists('mb_convert_encoding')) {
    return mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');
  }

  return preg_replace('/[^\x20-\x7E]/', '', $text) ?? '';
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
  $explicitBaseUrl = trim((string) (getenv('APP_URL') ?: getenv('PUBLIC_URL') ?: ''));
  if ($explicitBaseUrl !== '') {
    return rtrim($explicitBaseUrl, '/');
  }

  $forwardedProto = trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
  if (strpos($forwardedProto, ',') !== false) {
    $forwardedProto = trim((string) explode(',', $forwardedProto)[0]);
  }

  $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
  $scheme = $https ? 'https' : 'http';

  if ($forwardedProto !== '') {
    $scheme = strtolower($forwardedProto) === 'https' ? 'https' : 'http';
  } elseif (getenv('RAILWAY_PUBLIC_DOMAIN')) {
    $scheme = 'https';
  }

  $host = trim((string) ($_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? getenv('RAILWAY_PUBLIC_DOMAIN') ?: 'localhost'));
  if (strpos($host, ',') !== false) {
    $host = trim((string) explode(',', $host)[0]);
  }

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

  $providers = [
    'https://api.qrserver.com/v1/create-qr-code/?size=320x320&qzone=2&format=png&data=' . rawurlencode($url),
    'https://quickchart.io/qr?size=320&margin=2&ecLevel=H&format=png&text=' . rawurlencode($url),
  ];

  $contenido = null;

  foreach ($providers as $endpoint) {
    if (function_exists('curl_init')) {
      $ch = curl_init($endpoint);
      curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => ['Accept: image/png'],
      ]);
      $respuesta = curl_exec($ch);
      $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);

      if (is_string($respuesta) && $respuesta !== '' && $status >= 200 && $status < 300) {
        $contenido = $respuesta;
      }
    }

    if ($contenido === null) {
      $context = stream_context_create([
        'http' => ['timeout' => 8, 'header' => "Accept: image/png\r\n"],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
      ]);
      $respuesta = @file_get_contents($endpoint, false, $context);
      if (is_string($respuesta) && $respuesta !== '') {
        $contenido = $respuesta;
      }
    }

    if (is_string($contenido) && strlen($contenido) > 100 && str_starts_with($contenido, "\x89PNG")) {
      break;
    }

    $contenido = null;
  }

  if (!is_string($contenido) || strlen($contenido) < 100 || !str_starts_with($contenido, "\x89PNG")) {
    return null;
  }

  $tmpBase = @tempnam(sys_get_temp_dir(), 'qr_');
  if (!$tmpBase) {
    return null;
  }

  $tmp = $tmpBase . '.png';
  @rename($tmpBase, $tmp);
  if (!$tmp) {
    return null;
  }

  if (@file_put_contents($tmp, $contenido) === false) {
    @unlink($tmp);
    return null;
  }

  return $tmp;
}

function facturaTokenVisible(string $urlPublica): string
{
  $query = parse_url($urlPublica, PHP_URL_QUERY);
  if (!is_string($query) || $query === '') {
    return '-';
  }

  parse_str($query, $params);
  $token = trim((string) ($params['token'] ?? ''));
  if ($token === '') {
    return '-';
  }

  if (strlen($token) <= 18) {
    return $token;
  }

  return substr($token, 0, 10) . '...' . substr($token, -8);
}

function facturaPdfMoney(float $amount): string
{
  return '$' . number_format($amount, 0, ',', '.');
}

function facturaPdfRectTitle(FPDF $pdf, int $x, int $y, int $w, int $h, string $title, array $fillColor, array $borderColor): void
{
  $pdf->SetDrawColor($borderColor[0], $borderColor[1], $borderColor[2]);
  $pdf->SetFillColor($fillColor[0], $fillColor[1], $fillColor[2]);
  $pdf->Rect($x, $y, $w, $h, 'DF');
  $pdf->SetXY($x + 4, $y + 3);
  $pdf->SetFont('Helvetica', 'B', 8);
  $pdf->SetTextColor(138, 101, 33);
  $pdf->Cell($w - 8, 4, facturaPdfText($title), 0, 0, 'L');
  $pdf->SetTextColor(25, 21, 16);
}

function facturaLimpiarSalidaAntesDePdf(): void
{
  while (ob_get_level() > 0) {
    ob_end_clean();
  }
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

  $colorPage = [242, 238, 231];
  $colorSurface = [251, 247, 240];
  $colorSoft = [239, 230, 216];
  $colorDark = [23, 19, 15];
  $colorDarkSoft = [36, 29, 22];
  $colorAccent = [184, 146, 71];
  $colorAccentStrong = [138, 101, 33];
  $colorText = [25, 21, 16];
  $colorTextSoft = [107, 96, 84];
  $colorBorder = [216, 200, 173];
  $colorWhite = [255, 255, 255];

  $pdf->SetFillColor($colorPage[0], $colorPage[1], $colorPage[2]);
  $pdf->Rect(0, 0, 210, 297, 'F');

  $pdf->SetFillColor($colorDark[0], $colorDark[1], $colorDark[2]);
  $pdf->Rect(0, 0, 210, 38, 'F');
  $pdf->SetFillColor($colorAccent[0], $colorAccent[1], $colorAccent[2]);
  $pdf->Rect(0, 38, 210, 3, 'F');

  $pdf->SetTextColor($colorWhite[0], $colorWhite[1], $colorWhite[2]);
  $pdf->SetFont('Times', 'B', 24);
  $pdf->SetXY(12, 8);
  $pdf->Cell(98, 10, facturaPdfText('Tauro Store'), 0, 2, 'L');
  $pdf->SetFont('Helvetica', '', 9);
  $pdf->SetTextColor(245, 232, 211);
  $pdf->Cell(98, 5, facturaPdfText('Moda masculina sobria, elegante y contemporanea'), 0, 0, 'L');

  $pdf->SetXY(136, 8);
  $pdf->SetTextColor($colorWhite[0], $colorWhite[1], $colorWhite[2]);
  $pdf->SetFont('Helvetica', 'B', 14);
  $pdf->Cell(62, 7, facturaPdfText('FACTURA DE VENTA'), 0, 2, 'R');
  $pdf->SetFont('Helvetica', '', 8.5);
  $pdf->Cell(62, 5, facturaPdfText('Codigo: ' . $codigoFactura), 0, 2, 'R');
  $pdf->Cell(62, 5, facturaPdfText('Emitida: ' . date('d/m/Y H:i')), 0, 2, 'R');
  $pdf->Cell(62, 5, facturaPdfText('Pedido: #' . (int) $pedido['id']), 0, 0, 'R');

  $pdf->SetTextColor($colorText[0], $colorText[1], $colorText[2]);
  $y = 48;

  facturaPdfRectTitle($pdf, 10, $y, 92, 40, 'EMISOR', $colorSurface, $colorBorder);
  facturaPdfRectTitle($pdf, 108, $y, 92, 40, 'CLIENTE', $colorSurface, $colorBorder);

  $pdf->SetFont('Helvetica', 'B', 11);
  $pdf->SetXY(14, $y + 10);
  $pdf->Cell(84, 6, facturaPdfText('Tauro Store S.A.S.'), 0, 1);
  $pdf->SetFont('Helvetica', '', 9);
  $pdf->SetTextColor($colorTextSoft[0], $colorTextSoft[1], $colorTextSoft[2]);
  $pdf->SetX(14);
  $pdf->Cell(84, 5, facturaPdfText('NIT: 900123456-7'), 0, 1);
  $pdf->SetX(14);
  $pdf->Cell(84, 5, facturaPdfText('Correo: soporte@taurostore.com'), 0, 1);
  $pdf->SetX(14);
  $pdf->Cell(84, 5, facturaPdfText('WhatsApp: +57 302 334 1713'), 0, 1);

  $pdf->SetFont('Helvetica', 'B', 11);
  $pdf->SetTextColor($colorText[0], $colorText[1], $colorText[2]);
  $pdf->SetXY(112, $y + 10);
  $pdf->Cell(84, 6, facturaPdfText(facturaTruncar((string) ($pedido['nombre'] ?? 'No registrado'), 40)), 0, 1);
  $pdf->SetFont('Helvetica', '', 9);
  $pdf->SetTextColor($colorTextSoft[0], $colorTextSoft[1], $colorTextSoft[2]);
  $pdf->SetX(112);
  $pdf->Cell(84, 5, facturaPdfText(facturaTruncar((string) ($pedido['email'] ?? 'No registrado'), 40)), 0, 1);
  $pdf->SetX(112);
  $pdf->Cell(84, 5, facturaPdfText('Fecha pedido: ' . date('d/m/Y H:i', strtotime((string) $pedido['fecha']))), 0, 1);
  $pdf->SetX(112);
  $pdf->Cell(84, 5, facturaPdfText('Estado actual: ' . $estadoEtiqueta), 0, 1);

  $y += 46;

  $pdf->SetDrawColor($colorBorder[0], $colorBorder[1], $colorBorder[2]);
  $pdf->SetFillColor($colorSoft[0], $colorSoft[1], $colorSoft[2]);
  $pdf->Rect(10, $y, 190, 24, 'DF');
  $pdf->SetFont('Helvetica', 'B', 8);
  $pdf->SetTextColor($colorAccentStrong[0], $colorAccentStrong[1], $colorAccentStrong[2]);
  $pdf->SetXY(14, $y + 3);
  $pdf->Cell(40, 4, facturaPdfText('Metodo de pago'), 0, 0);
  $pdf->Cell(48, 4, facturaPdfText('Subtotal productos'), 0, 0);
  $pdf->Cell(40, 4, facturaPdfText('Costo de envio'), 0, 0);
  $pdf->Cell(44, 4, facturaPdfText('Total pedido'), 0, 1);
  $pdf->SetFont('Helvetica', 'B', 11);
  $pdf->SetTextColor($colorText[0], $colorText[1], $colorText[2]);
  $pdf->SetX(14);
  $pdf->Cell(40, 11, facturaPdfText(ucfirst((string) ($pedido['metodo_pago'] ?? 'No definido'))), 0, 0);
  $pdf->Cell(48, 11, facturaPdfMoney($subtotalProductos), 0, 0);
  $pdf->Cell(40, 11, facturaPdfMoney($costoEnvio), 0, 0);
  $pdf->Cell(44, 11, facturaPdfMoney($totalPedido), 0, 1, 'L');

  $y += 30;

  if (($pedido['metodo_pago'] ?? '') === 'entrega') {
    facturaPdfRectTitle($pdf, 10, $y, 190, 32, 'DATOS DE ENVIO', $colorSurface, $colorBorder);
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->SetTextColor($colorText[0], $colorText[1], $colorText[2]);
    $pdf->SetXY(14, $y + 10);
    $pdf->Cell(92, 5, facturaPdfText('Recibe: ' . facturaTruncar((string) ($pedido['nombre_envio'] ?? 'No registrado'), 36)), 0, 0);
    $pdf->Cell(90, 5, facturaPdfText('Telefono: ' . facturaTruncar((string) ($pedido['telefono_envio'] ?? 'No registrado'), 26)), 0, 1);
    $pdf->SetX(14);
    $pdf->Cell(92, 5, facturaPdfText('Ciudad: ' . textoTituloPedido((string) ($pedido['ciudad_envio'] ?? ''))), 0, 0);
    $pdf->Cell(90, 5, facturaPdfText('Zona: ' . textoTituloPedido((string) ($pedido['zona_envio'] ?? ''))), 0, 1);
    $entregaEstimada = (!empty($pedido['dias_entrega_min']) && !empty($pedido['dias_entrega_max']))
      ? ((int) $pedido['dias_entrega_min'] . '-' . (int) $pedido['dias_entrega_max'] . ' dias')
      : 'No definida';
    $direccion = trim((string) ($pedido['direccion_envio'] ?? ''));
    $barrio = trim((string) ($pedido['barrio_envio'] ?? ''));
    $pdf->SetX(14);
    $pdf->Cell(122, 5, facturaPdfText('Direccion: ' . facturaTruncar($direccion . ($barrio !== '' ? ' - ' . $barrio : ''), 64)), 0, 0);
    $pdf->Cell(62, 5, facturaPdfText('Entrega: ' . $entregaEstimada), 0, 1);
    $y += 38;
  }

  $pdf->SetFillColor($colorDarkSoft[0], $colorDarkSoft[1], $colorDarkSoft[2]);
  $pdf->SetTextColor($colorWhite[0], $colorWhite[1], $colorWhite[2]);
  $pdf->SetFont('Helvetica', 'B', 8.5);
  $pdf->SetX(10);
  $pdf->Cell(58, 8, facturaPdfText('Producto'), 1, 0, 'C', true);
  $pdf->Cell(28, 8, facturaPdfText('SKU'), 1, 0, 'C', true);
  $pdf->Cell(18, 8, facturaPdfText('Talla'), 1, 0, 'C', true);
  $pdf->Cell(20, 8, facturaPdfText('Cant.'), 1, 0, 'C', true);
  $pdf->Cell(30, 8, facturaPdfText('Unitario'), 1, 0, 'C', true);
  $pdf->Cell(36, 8, facturaPdfText('Subtotal'), 1, 1, 'C', true);

  $pdf->SetTextColor($colorText[0], $colorText[1], $colorText[2]);
  $pdf->SetFont('Helvetica', '', 8.5);

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
        $pdf->SetFillColor($colorPage[0], $colorPage[1], $colorPage[2]);
        $pdf->Rect(0, 0, 210, 297, 'F');
        $pdf->SetFillColor($colorDarkSoft[0], $colorDarkSoft[1], $colorDarkSoft[2]);
        $pdf->SetTextColor($colorWhite[0], $colorWhite[1], $colorWhite[2]);
        $pdf->SetFont('Helvetica', 'B', 8.5);
        $pdf->SetX(10);
        $pdf->Cell(58, 8, facturaPdfText('Producto'), 1, 0, 'C', true);
        $pdf->Cell(28, 8, facturaPdfText('SKU'), 1, 0, 'C', true);
        $pdf->Cell(18, 8, facturaPdfText('Talla'), 1, 0, 'C', true);
        $pdf->Cell(20, 8, facturaPdfText('Cant.'), 1, 0, 'C', true);
        $pdf->Cell(30, 8, facturaPdfText('Unitario'), 1, 0, 'C', true);
        $pdf->Cell(36, 8, facturaPdfText('Subtotal'), 1, 1, 'C', true);
        $pdf->SetTextColor($colorText[0], $colorText[1], $colorText[2]);
        $pdf->SetFont('Helvetica', '', 8.5);
      }

      $pdf->SetFillColor($colorSurface[0], $colorSurface[1], $colorSurface[2]);
      $pdf->SetX(10);
      $pdf->Cell(58, 8, facturaPdfText(facturaTruncar($nombreProducto, 35)), 1, 0, 'L', true);
      $pdf->Cell(28, 8, facturaPdfText(facturaTruncar((string) ($p['sku'] ?? '-'), 17)), 1, 0, 'C', true);
      $pdf->Cell(18, 8, facturaPdfText($tallaProducto !== '' ? $tallaProducto : '-'), 1, 0, 'C', true);
      $pdf->Cell(20, 8, (string) $cantidad, 1, 0, 'C', true);
      $pdf->Cell(30, 8, facturaPdfMoney($unitario), 1, 0, 'R', true);
      $pdf->Cell(36, 8, facturaPdfMoney($subtotal), 1, 1, 'R', true);
    }
  }

  $pdf->Ln(4);

  $xResumen = 110;
  $wLabel = 54;
  $wValor = 36;

  $pdf->SetDrawColor($colorBorder[0], $colorBorder[1], $colorBorder[2]);
  $pdf->SetFont('Helvetica', '', 9);
  $pdf->SetX($xResumen);
  $pdf->Cell($wLabel, 8, facturaPdfText('Subtotal productos'), 1, 0, 'L', true);
  $pdf->Cell($wValor, 8, facturaPdfMoney($totalLineas), 1, 1, 'R', true);
  $pdf->SetX($xResumen);
  $pdf->Cell($wLabel, 8, facturaPdfText('Costo envio'), 1, 0, 'L', true);
  $pdf->Cell($wValor, 8, facturaPdfMoney($costoEnvio), 1, 1, 'R', true);

  $pdf->SetFont('Helvetica', 'B', 10);
  $pdf->SetFillColor($colorSoft[0], $colorSoft[1], $colorSoft[2]);
  $pdf->SetX($xResumen);
  $pdf->Cell($wLabel, 9, facturaPdfText('TOTAL A PAGAR'), 1, 0, 'L', true);
  $pdf->Cell($wValor, 9, facturaPdfMoney($totalPedido), 1, 1, 'R', true);

  if ($pdf->GetY() > 232) {
    $pdf->AddPage();
    $pdf->SetFillColor($colorPage[0], $colorPage[1], $colorPage[2]);
    $pdf->Rect(0, 0, 210, 297, 'F');
  }

  $pdf->Ln(6);
  $yQr = $pdf->GetY();
  $pdf->SetDrawColor($colorBorder[0], $colorBorder[1], $colorBorder[2]);
  $pdf->SetFillColor($colorSurface[0], $colorSurface[1], $colorSurface[2]);
  $pdf->Rect(10, $yQr, 190, 48, 'DF');

  $qrBoxX = 14;
  $qrBoxY = $yQr + 4;
  $qrBoxSize = 38;
  $contentX = 58;
  $contentWidth = 136;

  $qrTemp = facturaDescargarQrTemporal($urlPublica);
  if ($qrTemp && file_exists($qrTemp)) {
    $pdf->SetDrawColor($colorBorder[0], $colorBorder[1], $colorBorder[2]);
    $pdf->SetFillColor(255, 255, 255);
    $pdf->Rect($qrBoxX, $qrBoxY, $qrBoxSize, $qrBoxSize, 'DF');
    $pdf->Image($qrTemp, $qrBoxX + 3, $qrBoxY + 3, 32, 32, 'PNG');
    @unlink($qrTemp);
  } else {
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->SetTextColor($colorAccentStrong[0], $colorAccentStrong[1], $colorAccentStrong[2]);
    $pdf->SetFillColor(255, 255, 255);
    $pdf->Rect($qrBoxX, $qrBoxY, $qrBoxSize, $qrBoxSize, 'DF');
    $pdf->SetXY($qrBoxX, $qrBoxY + 14);
    $pdf->Cell($qrBoxSize, 6, facturaPdfText('QR'), 0, 0, 'C');
    $pdf->SetTextColor($colorText[0], $colorText[1], $colorText[2]);
  }

  $pdf->SetXY($contentX, $yQr + 5);
  $pdf->SetFont('Helvetica', 'B', 9);
  $pdf->Cell($contentWidth, 5, facturaPdfText('Verificacion digital de factura'), 0, 2, 'L');
  $pdf->SetFont('Helvetica', '', 8.5);
  $pdf->SetTextColor($colorTextSoft[0], $colorTextSoft[1], $colorTextSoft[2]);
  $pdf->Cell($contentWidth, 5, facturaPdfText('Escanea el QR para abrir la factura publica desde tu celular.'), 0, 2, 'L');
  $pdf->Cell($contentWidth, 5, facturaPdfText('Token publico: ' . facturaTokenVisible($urlPublica)), 0, 2, 'L');

  $pdf->SetTextColor($colorAccentStrong[0], $colorAccentStrong[1], $colorAccentStrong[2]);
  $pdf->SetFont('Helvetica', 'B', 8.5);
  $pdf->SetFillColor($colorSoft[0], $colorSoft[1], $colorSoft[2]);
  $pdf->SetX($contentX);
  $pdf->Cell(56, 7, facturaPdfText('Abrir factura publica'), 1, 1, 'C', true, $urlPublica);
  $pdf->SetTextColor($colorText[0], $colorText[1], $colorText[2]);

  $pdf->Ln(10);
  $pdf->SetTextColor($colorTextSoft[0], $colorTextSoft[1], $colorTextSoft[2]);
  $pdf->SetFont('Helvetica', '', 8.2);
  $pdf->MultiCell(190, 4.8, facturaPdfText('Observaciones: Esta factura respalda la compra realizada en Tauro Store. Si necesitas soporte, escribe a soporte@taurostore.com con el numero de pedido o consulta tu factura publica.'));
  $pdf->Ln(2);
  $pdf->SetDrawColor($colorAccent[0], $colorAccent[1], $colorAccent[2]);
  $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
  $pdf->Ln(2.5);
  $pdf->SetFont('Helvetica', 'I', 8);
  $pdf->Cell(190, 5, facturaPdfText('Documento generado automaticamente el ' . date('d/m/Y H:i') . ' - Tauro Store'), 0, 1, 'C');
}
