<?php
include 'includes/conexion.php';
include 'includes/pedidos_utils.php';
include 'includes/factura_layout.php';
require 'includes/fpdf/fpdf.php';
require 'includes/PHPMailer/PHPMailer.php';
require 'includes/PHPMailer/SMTP.php';
require 'includes/PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

include 'header.php';

if (!isset($_SESSION['usuario'])) {
  echo '<div class="container py-5 text-center">
          <p>Debes <a href="login.php">iniciar sesi&oacute;n</a> para finalizar la compra.</p>
        </div>';
  include 'footer.php';
  exit;
}

if (empty($_SESSION['checkout_csrf'])) {
  $_SESSION['checkout_csrf'] = bin2hex(random_bytes(32));
}

/**
 * Valida y agrupa el carrito por producto + talla.
 *
 * @throws RuntimeException
 */
function normalizarCarrito(?string $json): array
{
  if ($json === null || trim($json) === '') {
    throw new RuntimeException('No se recibio informacion del carrito.');
  }

  $carritoRaw = json_decode($json, true);
  if (!is_array($carritoRaw)) {
    throw new RuntimeException('El carrito recibido no es valido.');
  }

  $agrupado = [];

  foreach ($carritoRaw as $item) {
    if (!is_array($item)) {
      continue;
    }

    $productoId = filter_var($item['id'] ?? null, FILTER_VALIDATE_INT, [
      'options' => ['min_range' => 1]
    ]);

    $cantidad = filter_var($item['cantidad'] ?? 1, FILTER_VALIDATE_INT, [
      'options' => ['min_range' => 1, 'max_range' => 100]
    ]);

    $talla = trim((string) ($item['talla'] ?? ''));
    if (strlen($talla) > 10) {
      throw new RuntimeException('Se detecto una talla invalida en el carrito.');
    }

    if (!$productoId || !$cantidad) {
      throw new RuntimeException('Hay productos con datos invalidos en el carrito.');
    }

    $key = $productoId . '|' . strtolower($talla);
    if (!isset($agrupado[$key])) {
      $agrupado[$key] = [
        'id' => (int) $productoId,
        'cantidad' => 0,
        'talla' => $talla
      ];
    }

    $agrupado[$key]['cantidad'] += (int) $cantidad;
  }

  if (empty($agrupado)) {
    throw new RuntimeException('El carrito esta vacio.');
  }

  foreach ($agrupado as $item) {
    if ($item['cantidad'] > 100) {
      throw new RuntimeException('La cantidad de un producto excede el limite permitido.');
    }
  }

  return array_values($agrupado);
}

$errores = [];
$pedidoCreado = false;
$pedidoId = null;
$pedidoTotal = 0.0;
$pedidoSubtotal = 0.0;
$pedidoEnvio = 0.0;
$pedidoDiasEntrega = null;
$facturaPublicaUrl = null;

$metodoPago = $_POST['metodo_pago'] ?? '';
$nombreEnvio = trim((string) ($_POST['nombre'] ?? ''));
$telefonoEnvio = trim((string) ($_POST['telefono'] ?? ''));
$direccionEnvio = trim((string) ($_POST['direccion'] ?? ''));
$barrioEnvio = trim((string) ($_POST['barrio'] ?? ''));
$ciudadEnvio = trim((string) ($_POST['ciudad'] ?? ''));
$zonaEnvio = trim((string) ($_POST['zona'] ?? ''));
$aceptaTerminos = isset($_POST['acepta_terminos']);

$ciudadesEnvio = [
  'bogota' => 'Bogota',
  'medellin' => 'Medellin',
  'cali' => 'Cali',
  'barranquilla' => 'Barranquilla',
  'otras' => 'Otras ciudades'
];

$zonasEnvio = [
  'norte' => 'Norte',
  'centro' => 'Centro',
  'sur' => 'Sur',
  'occidente' => 'Occidente',
  'oriente' => 'Oriente',
  'estandar' => 'Estandar'
];

$tarifasActivas = $conn->query("
  SELECT ciudad, zona, costo, dias_min, dias_max
  FROM tarifas_envio
  WHERE activo = 1
")->fetchAll(PDO::FETCH_ASSOC);

$costoEnvio = 0.0;
$diasEntregaMin = null;
$diasEntregaMax = null;
$tarifaEnvioAplicada = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['finalizar_pedido'])) {
  if (!hash_equals($_SESSION['checkout_csrf'] ?? '', $_POST['csrf_token'] ?? '')) {
    $errores[] = 'La sesion del formulario expiro. Recarga la pagina e intenta de nuevo.';
  }

  $metodosPermitidos = ['entrega', 'recoger_tienda'];
  if (!in_array($metodoPago, $metodosPermitidos, true)) {
    $errores[] = 'Selecciona una modalidad valida para recibir tu pedido.';
  }

  if (!$aceptaTerminos) {
    $errores[] = 'Debes aceptar los terminos y condiciones para finalizar tu pedido.';
  }

  if ($metodoPago === 'entrega') {
    $ciudadEnvio = normalizarTextoPedido($ciudadEnvio);
    $zonaEnvio = normalizarTextoPedido($zonaEnvio);

    if ($nombreEnvio === '' || strlen($nombreEnvio) > 120) {
      $errores[] = 'Ingresa un nombre de envio valido.';
    }

    if (!preg_match('/^[0-9+()\\-\\s]{7,20}$/', $telefonoEnvio)) {
      $errores[] = 'Ingresa un telefono de envio valido.';
    }

    if ($direccionEnvio === '' || strlen($direccionEnvio) > 180) {
      $errores[] = 'Ingresa una direccion de envio valida.';
    }

    if ($barrioEnvio === '' || strlen($barrioEnvio) > 120) {
      $errores[] = 'Ingresa un barrio valido.';
    }

    if ($ciudadEnvio === '' || strlen($ciudadEnvio) > 120) {
      $errores[] = 'Ingresa una ciudad valida.';
    }

    if (!array_key_exists($ciudadEnvio, $ciudadesEnvio)) {
      $errores[] = 'Selecciona una ciudad valida para envio.';
    }

    if (!array_key_exists($zonaEnvio, $zonasEnvio)) {
      $errores[] = 'Selecciona una zona valida para envio.';
    }

    if (empty($errores)) {
      $tarifaEnvioAplicada = obtenerTarifaEnvio($conn, $ciudadEnvio, $zonaEnvio);
      if (!$tarifaEnvioAplicada) {
        $errores[] = 'No fue posible calcular la tarifa de envio para la ubicacion seleccionada.';
      } else {
        $costoEnvio = (float) $tarifaEnvioAplicada['costo'];
        $diasEntregaMin = (int) $tarifaEnvioAplicada['dias_min'];
        $diasEntregaMax = (int) $tarifaEnvioAplicada['dias_max'];
      }
    }
  } elseif ($metodoPago === 'recoger_tienda') {
    $nombreEnvio = null;
    $telefonoEnvio = null;
    $direccionEnvio = null;
    $barrioEnvio = null;
    $ciudadEnvio = null;
    $zonaEnvio = null;
    $costoEnvio = 0.0;
    $diasEntregaMin = null;
    $diasEntregaMax = null;
  }

  $itemsCarrito = [];

  if (empty($errores)) {
    try {
      $itemsCarrito = normalizarCarrito($_POST['carrito_data'] ?? null);
    } catch (RuntimeException $e) {
      $errores[] = $e->getMessage();
    }
  }

  if (empty($errores)) {
    try {
      $conn->beginTransaction();

      $buscarProducto = $conn->prepare("SELECT id, nombre, precio FROM productos WHERE id = ?");
      $contarTallas = $conn->prepare("SELECT COUNT(*) FROM producto_tallas WHERE producto_id = ?");
      $buscarTalla = $conn->prepare("
        SELECT pt.id, pt.stock, t.nombre AS talla
        FROM producto_tallas pt
        JOIN tallas t ON t.id = pt.talla_id
        WHERE pt.producto_id = ? AND t.nombre = ?
        FOR UPDATE
      ");
      $descontarStock = $conn->prepare("UPDATE producto_tallas SET stock = stock - ? WHERE id = ?");

      $lineasPedido = [];
      $subtotalProductos = 0.0;

      foreach ($itemsCarrito as $item) {
        $buscarProducto->execute([$item['id']]);
        $producto = $buscarProducto->fetch(PDO::FETCH_ASSOC);

        if (!$producto) {
          throw new RuntimeException('Uno de los productos del carrito ya no existe.');
        }

        $contarTallas->execute([$item['id']]);
        $tieneTallas = ((int) $contarTallas->fetchColumn()) > 0;

        $tallaConfirmada = '';
        if ($tieneTallas) {
          if ($item['talla'] === '') {
            throw new RuntimeException('Debes seleccionar talla para "' . $producto['nombre'] . '".');
          }

          $buscarTalla->execute([$item['id'], $item['talla']]);
          $registroTalla = $buscarTalla->fetch(PDO::FETCH_ASSOC);

          if (!$registroTalla) {
            throw new RuntimeException('La talla seleccionada para "' . $producto['nombre'] . '" no existe.');
          }

          if ((int) $registroTalla['stock'] < (int) $item['cantidad']) {
            throw new RuntimeException('Stock insuficiente para "' . $producto['nombre'] . '" en talla ' . $registroTalla['talla'] . '.');
          }

          $descontarStock->execute([(int) $item['cantidad'], (int) $registroTalla['id']]);
          $tallaConfirmada = $registroTalla['talla'];
        }

        $precioUnitario = (float) $producto['precio'];
        $subtotal = $precioUnitario * (int) $item['cantidad'];
        $subtotalProductos += $subtotal;

        $lineasPedido[] = [
          'producto_id' => (int) $producto['id'],
          'nombre_producto' => $producto['nombre'],
          'talla' => $tallaConfirmada !== '' ? $tallaConfirmada : null,
          'cantidad' => (int) $item['cantidad'],
          'precio_unitario' => $precioUnitario
        ];
      }

      $totalPedido = $subtotalProductos + $costoEnvio;
      $facturaToken = bin2hex(random_bytes(24));

      $insertPedido = $conn->prepare("
        INSERT INTO pedidos
          (
            usuario_id, total, subtotal_productos, costo_envio, estado, metodo_pago,
            nombre_envio, telefono_envio, direccion_envio, barrio_envio, ciudad_envio, zona_envio,
            dias_entrega_min, dias_entrega_max, estado_pendiente_at, stock_reintegrado, factura_token
          )
        VALUES
          (?, ?, ?, ?, 'pendiente', ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 0, ?)
      ");
      $insertPedido->execute([
        (int) $_SESSION['usuario']['id'],
        $totalPedido,
        $subtotalProductos,
        $costoEnvio,
        $metodoPago,
        $nombreEnvio,
        $telefonoEnvio,
        $direccionEnvio,
        $barrioEnvio,
        $ciudadEnvio,
        $zonaEnvio,
        $diasEntregaMin,
        $diasEntregaMax,
        $facturaToken
      ]);

      $pedidoId = (int) $conn->lastInsertId();

      $insertDetalle = $conn->prepare("
        INSERT INTO detalle_pedido
          (pedido_id, producto_id, talla, nombre_producto, cantidad, precio_unitario)
        VALUES
          (?, ?, ?, ?, ?, ?)
      ");

      foreach ($lineasPedido as $linea) {
        $insertDetalle->execute([
          $pedidoId,
          $linea['producto_id'],
          $linea['talla'],
          $linea['nombre_producto'],
          $linea['cantidad'],
          $linea['precio_unitario']
        ]);
      }

      registrarHistorialEstadoPedido(
        $conn,
        $pedidoId,
        'pendiente',
        (int) $_SESSION['usuario']['id'],
        'sistema',
        'Pedido creado desde checkout'
      );

      $conn->commit();
      $pedidoCreado = true;
      $pedidoSubtotal = $subtotalProductos;
      $pedidoEnvio = $costoEnvio;
      $pedidoTotal = $totalPedido;
      $pedidoDiasEntrega = $diasEntregaMin !== null && $diasEntregaMax !== null
        ? $diasEntregaMin . '-' . $diasEntregaMax . ' dias'
        : null;

      $_SESSION['checkout_csrf'] = bin2hex(random_bytes(32));
    } catch (Throwable $e) {
      if ($conn->inTransaction()) {
        $conn->rollBack();
      }
      $errores[] = $e->getMessage();
    }
  }

  if ($pedidoCreado) {
    /* ================= PDF ================= */
    $pedidoFacturaStmt = $conn->prepare("
      SELECT p.*, u.nombre, u.email
      FROM pedidos p
      JOIN usuarios u ON p.usuario_id = u.id
      WHERE p.id = ?
      LIMIT 1
    ");
    $pedidoFacturaStmt->execute([$pedidoId]);
    $pedidoFactura = $pedidoFacturaStmt->fetch(PDO::FETCH_ASSOC);

    $detalleFacturaStmt = $conn->prepare("
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
    $detalleFacturaStmt->execute([$pedidoId]);
    $productosFactura = $detalleFacturaStmt->fetchAll(PDO::FETCH_ASSOC);

    $pdf = new FPDF();
    $pdf->AddPage();
    if ($pedidoFactura) {
      $tokenFactura = facturaAsegurarToken($conn, $pedidoFactura);
      $facturaPublicaUrl = facturaConstruirUrlPublica($tokenFactura);
      facturaRenderizar($pdf, $pedidoFactura, $productosFactura, $facturaPublicaUrl);
    } else {
      $pdf->SetFont('Arial', 'B', 16);
      $pdf->Cell(0, 10, 'Tauro Store - Factura', 0, 1, 'C');
      $pdf->SetFont('Arial', '', 12);
      $pdf->Ln(5);
      $pdf->Cell(0, 8, 'Cliente: ' . $_SESSION['usuario']['nombre'], 0, 1);
      $pdf->Cell(0, 8, 'Pedido #: ' . $pedidoId, 0, 1);
      $pdf->Cell(0, 8, 'Subtotal productos: $' . number_format($pedidoSubtotal, 0, ',', '.'), 0, 1);
      $pdf->Cell(0, 8, 'Envio: $' . number_format($pedidoEnvio, 0, ',', '.'), 0, 1);
      $pdf->Cell(0, 8, 'Total: $' . number_format($pedidoTotal, 0, ',', '.'), 0, 1);
      if ($metodoPago === 'recoger_tienda') {
        $pdf->Cell(0, 8, 'Modalidad: Recoger en tienda', 0, 1);
      } elseif ($pedidoDiasEntrega !== null) {
        $pdf->Cell(0, 8, 'Entrega estimada: ' . $pedidoDiasEntrega, 0, 1);
      }
    }
    $pdfdoc = $pdf->Output('S');

    /* ================= CORREO (OPCIONAL SI HAY CREDENCIALES) ================= */
    $smtpUser = getenv('SMTP_USER') ?: '';
    $smtpPass = getenv('SMTP_PASS') ?: '';
    $smtpFrom = getenv('SMTP_FROM') ?: $smtpUser;

    if ($smtpUser !== '' && $smtpPass !== '' && $smtpFrom !== '') {
      $mail = new PHPMailer(true);
      try {
        $mail->isSMTP();
        $mail->Host = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUser;
        $mail->Password = $smtpPass;
        $mail->SMTPSecure = getenv('SMTP_SECURE') ?: 'tls';
        $mail->Port = (int) (getenv('SMTP_PORT') ?: 587);

        $mail->setFrom($smtpFrom, 'Tauro Store');
        $mail->addAddress($_SESSION['usuario']['email'], $_SESSION['usuario']['nombre']);
        $mail->Subject = 'Pedido Confirmado #' . $pedidoId;
        $mail->Body = $facturaPublicaUrl
          ? 'Gracias por tu compra en Tauro Store. Consulta tu factura en: ' . $facturaPublicaUrl
          : 'Gracias por tu compra en Tauro Store.';
        $mail->addStringAttachment($pdfdoc, 'factura_' . $pedidoId . '.pdf');
        $mail->send();
      } catch (MailException $e) {
        error_log($mail->ErrorInfo);
      }
    }

    echo '
    <div class="container py-5 text-center">
      <div class="card p-5">
        <h2 class="checkout-title mb-2">Pedido confirmado</h2>
        <p class="text-soft">Gracias por confiar en Tauro Store.</p>
        <p>Numero de pedido: <strong>#' . $pedidoId . '</strong></p>
        <p>Subtotal productos: <strong class="status-price">$' . number_format($pedidoSubtotal, 0, ',', '.') . '</strong></p>
        <p>Envio: <strong class="status-price">$' . number_format($pedidoEnvio, 0, ',', '.') . '</strong></p>
        <p>Total: <strong class="status-price">$' . number_format($pedidoTotal, 0, ',', '.') . '</strong></p>
        ' . ($metodoPago === 'recoger_tienda'
          ? '<p>Modalidad: <strong>Recoger en tienda</strong></p>'
          : ($pedidoDiasEntrega !== null ? '<p>Entrega estimada: <strong>' . htmlspecialchars($pedidoDiasEntrega) . '</strong></p>' : '')
        ) . '
        <a href="ver_pedido.php?id=' . $pedidoId . '" class="btn btn-primary mt-2">Ver pedido</a>
        <a href="factura_pdf.php?id=' . $pedidoId . '" class="btn btn-outline-primary mt-2 ms-2">Factura con QR</a>
        ' . ($facturaPublicaUrl ? '<p class="mt-3 mb-0"><small>Enlace de verificacion: <a href="' . htmlspecialchars($facturaPublicaUrl) . '">' . htmlspecialchars($facturaPublicaUrl) . '</a></small></p>' : '') . '
      </div>
      <script>
        localStorage.removeItem("carrito");
        document.addEventListener("DOMContentLoaded", function () {
          const mostrarConfirmacion = function () {
            if (!window.Swal) {
              window.setTimeout(mostrarConfirmacion, 120);
              return;
            }

            window.Swal.fire({
              icon: "success",
              title: "Compra finalizada",
              text: "Tu pedido fue registrado correctamente.",
              confirmButtonText: "Perfecto",
              customClass: {
                confirmButton: "btn btn-primary"
              },
              buttonsStyling: false
            });
          };

          mostrarConfirmacion();
        });
      </script>
    </div>';

    include 'footer.php';
    exit;
  }
}
?>

<div class="container py-5">
  <h1 class="text-center mb-4 checkout-title">Confirmar pedido</h1>

  <?php if (!empty($errores)): ?>
    <div class="alert alert-danger">
      <ul class="mb-0">
        <?php foreach ($errores as $error): ?>
          <li><?= htmlspecialchars($error) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="row g-4">
    <div class="col-lg-7">
      <div class="card p-4">
        <h5 class="mb-4">Entrega del pedido</h5>

        <form method="post"
              id="checkout-form"
              data-confirm="true"
              data-confirm-title="Confirmar compra"
              data-confirm-message="Se procesara tu pedido con los productos actuales del carrito. Verifica los datos antes de continuar."
              data-confirm-button="Finalizar compra"
              data-confirm-variant="btn-primary">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['checkout_csrf']) ?>">
          <input type="hidden" name="carrito_data" id="carrito-data">

          <div class="mb-3">
            <label class="form-label">Modalidad de entrega</label>
            <select name="metodo_pago" id="metodo_pago" class="form-select" required>
              <option value="">Seleccionar</option>
              <option value="entrega" <?= $metodoPago === 'entrega' ? 'selected' : '' ?>>Contra entrega</option>
              <option value="recoger_tienda" <?= $metodoPago === 'recoger_tienda' ? 'selected' : '' ?>>Recoger en tienda</option>
            </select>
            <div class="form-text">Si eliges contra entrega, debes completar los datos de domicilio. Si eliges recoger en tienda, no se cobrara envio.</div>
          </div>

          <div id="datos-entrega" class="<?= $metodoPago === 'entrega' ? '' : 'd-none' ?>">
            <h6 class="mt-4 mb-3">Datos de env&iacute;o</h6>

            <div class="mb-3">
              <label class="form-label">Nombre completo</label>
              <input type="text" name="nombre" id="campo-nombre" class="form-control" maxlength="120" value="<?= htmlspecialchars((string) ($nombreEnvio ?? '')) ?>">
            </div>

            <div class="mb-3">
              <label class="form-label">Tel&eacute;fono</label>
              <input type="text" name="telefono" id="campo-telefono" class="form-control" maxlength="20" value="<?= htmlspecialchars((string) ($telefonoEnvio ?? '')) ?>">
            </div>

            <div class="mb-3">
              <label class="form-label">Direcci&oacute;n</label>
              <input type="text" name="direccion" id="campo-direccion" class="form-control" maxlength="180" value="<?= htmlspecialchars((string) ($direccionEnvio ?? '')) ?>">
            </div>

            <div class="mb-3">
              <label class="form-label">Barrio</label>
              <input type="text" name="barrio" id="campo-barrio" class="form-control" maxlength="120" value="<?= htmlspecialchars((string) ($barrioEnvio ?? '')) ?>">
            </div>

            <div class="mb-3">
              <label class="form-label">Ciudad</label>
              <select name="ciudad" id="campo-ciudad" class="form-select">
                <option value="">Seleccionar ciudad</option>
                <?php foreach ($ciudadesEnvio as $slug => $etiqueta): ?>
                  <option value="<?= htmlspecialchars($slug) ?>" <?= $ciudadEnvio === $slug ? 'selected' : '' ?>>
                    <?= htmlspecialchars($etiqueta) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label">Zona</label>
              <select name="zona" id="campo-zona" class="form-select">
                <option value="">Seleccionar zona</option>
                <?php foreach ($zonasEnvio as $slug => $etiqueta): ?>
                  <option value="<?= htmlspecialchars($slug) ?>" <?= $zonaEnvio === $slug ? 'selected' : '' ?>>
                    <?= htmlspecialchars($etiqueta) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <div id="info-envio" class="form-text"></div>
            </div>
          </div>

           <div class="form-check mt-4">
             <input class="form-check-input" type="checkbox" value="1" id="acepta_terminos_checkout" name="acepta_terminos" <?= $aceptaTerminos ? 'checked' : '' ?>>
             <label class="form-check-label text-soft" for="acepta_terminos_checkout">
               Declaro que he leido y acepto los <a href="#" onclick="mostrarTerminosModal('acepta_terminos_checkout'); return false;">terminos y condiciones</a> y el tratamiento de los datos necesarios para gestionar este pedido.
             </label>
           </div>

          <button type="submit" name="finalizar_pedido" class="btn w-100 mt-3">Confirmar pedido</button>
        </form>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card p-4">
        <h5 class="mb-3">Resumen</h5>
        <div id="resumen-pedido"></div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
  const form = document.getElementById("checkout-form");
  const resumen = document.getElementById("resumen-pedido");
  const hiddenCarrito = document.getElementById("carrito-data");
  const metodoPago = document.getElementById("metodo_pago");
  const bloqueEntrega = document.getElementById("datos-entrega");
  const campoCiudad = document.getElementById("campo-ciudad");
  const campoZona = document.getElementById("campo-zona");
  const infoEnvio = document.getElementById("info-envio");
  const tarifasEnvio = <?= json_encode($tarifasActivas, JSON_UNESCAPED_UNICODE) ?>;

  const camposEntrega = [
    document.getElementById("campo-nombre"),
    document.getElementById("campo-telefono"),
    document.getElementById("campo-direccion"),
    document.getElementById("campo-barrio"),
    campoCiudad,
    campoZona
  ];

  function normalizarTexto(valor) {
    return String(valor || "")
      .trim()
      .toLowerCase()
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .replace(/[^a-z0-9]+/g, " ")
      .trim();
  }

  function leerCarrito() {
    return JSON.parse(localStorage.getItem("carrito")) || [];
  }

  function guardarCarrito(carrito) {
    localStorage.setItem("carrito", JSON.stringify(carrito));
  }

  function mergeItems(carrito) {
    const agrupado = [];

    carrito.forEach((item) => {
      const existente = agrupado.find((actual) =>
        Number(actual.id) === Number(item.id) &&
        String(actual.talla || "") === String(item.talla || "")
      );

      if (existente) {
        existente.cantidad = Number(existente.cantidad || 1) + Number(item.cantidad || 1);
      } else {
        agrupado.push({
          ...item,
          id: Number(item.id),
          cantidad: Number(item.cantidad || 1)
        });
      }
    });

    return agrupado;
  }

  async function cargarMetaProductos(ids) {
    if (!ids.length) {
      return {};
    }

    try {
      const respuesta = await fetch("api_carrito_productos.php?ids=" + ids.join(","));
      const data = await respuesta.json();
      const mapa = {};

      (Array.isArray(data.products) ? data.products : []).forEach((producto) => {
        mapa[Number(producto.id)] = producto;
      });

      return mapa;
    } catch (error) {
      return {};
    }
  }

  function buscarTarifa(ciudad, zona) {
    const ciudadNorm = normalizarTexto(ciudad);
    const zonaNorm = normalizarTexto(zona) || "estandar";

    if (!ciudadNorm) {
      return null;
    }

    let tarifa = tarifasEnvio.find(t =>
      normalizarTexto(t.ciudad) === ciudadNorm &&
      normalizarTexto(t.zona) === zonaNorm
    );

    if (!tarifa && zonaNorm !== "estandar") {
      tarifa = tarifasEnvio.find(t =>
        normalizarTexto(t.ciudad) === ciudadNorm &&
        normalizarTexto(t.zona) === "estandar"
      );
    }

    if (!tarifa) {
      tarifa = tarifasEnvio.find(t =>
        normalizarTexto(t.ciudad) === "otras" &&
        normalizarTexto(t.zona) === "estandar"
      );
    }

    return tarifa || null;
  }

  function actualizarInfoEnvio() {
    if (!infoEnvio) {
      return null;
    }

    if (metodoPago.value === "recoger_tienda") {
      infoEnvio.textContent = "Retiro en tienda: sin costo de envio.";
      return null;
    }

    if (metodoPago.value !== "entrega") {
      infoEnvio.textContent = "";
      return null;
    }

    const tarifa = buscarTarifa(campoCiudad.value, campoZona.value);
    if (!tarifa) {
      infoEnvio.textContent = "Selecciona ciudad y zona para calcular envio.";
      return null;
    }

    const costo = Number(tarifa.costo || 0);
    const dMin = Number(tarifa.dias_min || 0);
    const dMax = Number(tarifa.dias_max || 0);
    infoEnvio.textContent = `Envio: $${costo.toLocaleString()} | Entrega estimada: ${dMin}-${dMax} dias`;
    return tarifa;
  }

  function actualizarCamposEntrega() {
    const activo = metodoPago.value === "entrega";
    bloqueEntrega.classList.toggle("d-none", !activo);
    camposEntrega.forEach(campo => {
      if (!campo) return;
      campo.required = activo;
    });
    actualizarInfoEnvio();
  }

  async function renderizarResumen(carrito) {
    if (!carrito.length) {
      resumen.innerHTML = "<div class='alert alert-warning mb-0'>Tu carrito est&aacute; vac&iacute;o.</div>";
      return;
    }

    const ids = [...new Set(carrito.map((item) => Number(item.id)).filter(Boolean))];
    const metaProductos = await cargarMetaProductos(ids);
    let total = 0;
    let faltanTallas = false;
    let html = "<div class='table-responsive'><table class='table table-sm text-center align-middle'><thead><tr><th>Producto</th><th>Precio</th><th>Cant.</th><th>Subtotal</th><th></th></tr></thead><tbody>";

    carrito.forEach((item, index) => {
      const cantidad = Number(item.cantidad ?? 1);
      const precio = Number(item.precio ?? 0);
      const subtotal = precio * cantidad;
      total += subtotal;

      const nombre = String(item.nombre ?? "Producto");
      const meta = metaProductos[Number(item.id)] || null;
      const requiereTalla = !!(meta && meta.requires_size);
      if (requiereTalla && !String(item.talla || "").trim()) {
        faltanTallas = true;
      }

      let productoHtml = `<div class="fw-semibold">${nombre}</div>`;

      if (requiereTalla) {
        const opciones = (meta.sizes || []).map((size) => `
          <option value="${size.name}" ${String(item.talla || "") === String(size.name) ? "selected" : ""} ${!size.available ? "disabled" : ""}>
            ${size.name}${size.available ? "" : " - Sin stock"}
          </option>
        `).join("");

        productoHtml += `
          <div class="mt-2">
            <select class="form-select form-select-sm" onchange="window.cambiarTallaCheckout(${index}, this.value)">
              <option value="">Seleccionar talla</option>
              ${opciones}
            </select>
          </div>
        `;
      } else if (item.talla) {
        productoHtml += `<div class="small text-soft mt-1">Talla ${item.talla}</div>`;
      }

      html += `<tr>
        <td>${productoHtml}</td>
        <td>$${precio.toLocaleString()}</td>
        <td>${cantidad}</td>
        <td>$${subtotal.toLocaleString()}</td>
        <td>
          <button type="button" class="btn btn-outline-primary btn-sm" onclick="window.eliminarDelCheckout(${index})">
            Quitar
          </button>
        </td>
      </tr>`;
    });

    let envio = 0;
    let entrega = "";
    if (metodoPago.value === "entrega") {
      const tarifa = buscarTarifa(campoCiudad.value, campoZona.value);
      if (tarifa) {
        envio = Number(tarifa.costo || 0);
        const dMin = Number(tarifa.dias_min || 0);
        const dMax = Number(tarifa.dias_max || 0);
        entrega = `<div class='small text-soft mt-2'>Entrega estimada: ${dMin}-${dMax} dias</div>`;
      }
    } else if (metodoPago.value === "recoger_tienda") {
      entrega = "<div class='small text-soft mt-2'>Recogida en tienda: sin costo de envio.</div>";
    }

    const totalFinal = total + envio;
    html += `<tr>
      <td colspan="4"><strong>Subtotal productos</strong></td>
      <td><strong>$${total.toLocaleString()}</strong></td>
    </tr>
    <tr>
      <td colspan="4"><strong>Envio</strong></td>
      <td><strong>$${envio.toLocaleString()}</strong></td>
    </tr>
    <tr>
      <td colspan="4"><strong>Total a pagar</strong></td>
      <td><strong>$${totalFinal.toLocaleString()}</strong></td>
    </tr></tbody></table></div>`;

    if (faltanTallas) {
      html += "<div class='alert alert-warning mt-3 mb-0'>Hay productos que requieren talla. Seleccionala antes de finalizar la compra.</div>";
    }

    resumen.innerHTML = html + entrega;
    hiddenCarrito.value = JSON.stringify(carrito);
  }

  function eliminarDelCheckout(index) {
    const carrito = leerCarrito();
    carrito.splice(index, 1);
    guardarCarrito(carrito);
    renderizarResumen(carrito);
    if (typeof actualizarContadorCarrito === "function") {
      actualizarContadorCarrito();
    }
  }

  function cambiarTallaCheckout(index, nuevaTalla) {
    const carrito = leerCarrito();
    if (!carrito[index]) {
      return;
    }

    carrito[index].talla = nuevaTalla || null;
    guardarCarrito(mergeItems(carrito));
    renderizarResumen(leerCarrito());
    if (typeof actualizarContadorCarrito === "function") {
      actualizarContadorCarrito();
    }
  }

  const carrito = leerCarrito();
  hiddenCarrito.value = JSON.stringify(carrito);
  renderizarResumen(carrito);
  actualizarCamposEntrega();
  actualizarInfoEnvio();

  window.eliminarDelCheckout = eliminarDelCheckout;
  window.cambiarTallaCheckout = cambiarTallaCheckout;

  metodoPago.addEventListener("change", function () {
    actualizarCamposEntrega();
    renderizarResumen(leerCarrito());
  });
  campoCiudad.addEventListener("change", function () {
    actualizarInfoEnvio();
    renderizarResumen(leerCarrito());
  });
  campoZona.addEventListener("change", function () {
    actualizarInfoEnvio();
    renderizarResumen(leerCarrito());
  });

   form.addEventListener("submit", function (event) {
     event.preventDefault();
     const carritoActual = leerCarrito();
     const aceptaTerminos = document.getElementById("acepta_terminos_checkout");

     if (!carritoActual.length) {
       alert("Tu carrito está vacío.");
       return;
     }

     // Validar que el checkbox esté marcado
     if (!aceptaTerminos || !aceptaTerminos.checked) {
       if (window.Swal) {
         window.Swal.fire({
           icon: "warning",
           title: "Acepta los términos",
           text: "Debes aceptar los términos y condiciones antes de finalizar la compra.",
           confirmButtonText: "Entendido",
           customClass: {
             confirmButton: "btn btn-primary"
           },
           buttonsStyling: false
         });
       } else {
         alert("Debes aceptar los términos y condiciones antes de finalizar la compra.");
       }
       return;
     }

     hiddenCarrito.value = JSON.stringify(carritoActual);

     const ids = [...new Set(carritoActual.map((item) => Number(item.id)).filter(Boolean))];
     cargarMetaProductos(ids).then((metaProductos) => {
       const faltanTallas = carritoActual.some((item) => {
         const meta = metaProductos[Number(item.id)] || null;
         return meta && meta.requires_size && !String(item.talla || "").trim();
       });

       if (faltanTallas) {
         if (window.Swal) {
           window.Swal.fire({
             icon: "warning",
             title: "Faltan tallas por seleccionar",
             text: "Completa las tallas faltantes o quita esos productos antes de finalizar la compra.",
             confirmButtonText: "Entendido",
             customClass: {
               confirmButton: "btn btn-primary"
             },
             buttonsStyling: false
           });
         } else {
           alert("Completa las tallas faltantes o quita esos productos antes de finalizar la compra.");
         }
         return;
       }

       form.submit();
     });
   });
 });
 </script>

<script>
// La validación se maneja en assets/js/terminos.js
document.addEventListener("DOMContentLoaded", function() {
  const checkboxTerminos = document.getElementById("acepta_terminos_checkout");
  const btnFinalizar = document.querySelector("button[name='finalizar_pedido']");

  if (checkboxTerminos && btnFinalizar) {
    // Actualizar estado visual según checkbox
    checkboxTerminos.addEventListener("change", function() {
      console.log("Términos checkout:", this.checked ? "aceptados" : "no aceptados");
    });

    btnFinalizar.disabled = false;
  }
});
</script>

 <?php include 'footer.php'; ?>
