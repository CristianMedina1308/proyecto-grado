<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once __DIR__ . '/includes/app.php';

include 'includes/conexion.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
  include 'header.php';
  echo "<div class='container py-5 text-center'><div class='alert alert-warning'>Producto no encontrado.</div></div>";
  include 'footer.php';
  exit;
}

$stmt = $conn->prepare("SELECT * FROM productos WHERE id = ?");
$stmt->execute([$id]);
$producto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$producto) {
  include 'header.php';
  echo "<div class='container py-5 text-center'><div class='alert alert-warning'>Producto no disponible.</div></div>";
  include 'footer.php';
  exit;
}

$usuarioActual = $_SESSION['usuario'] ?? null;
$usuarioId = isset($usuarioActual['id']) ? (int) $usuarioActual['id'] : 0;
$mensajeResena = '';
$errorResena = '';

$puedeResenar = false;
if ($usuarioId > 0) {
  $checkCompra = $conn->prepare("
    SELECT 1
    FROM pedidos p
    INNER JOIN detalle_pedido dp ON dp.pedido_id = p.id
    WHERE p.usuario_id = ?
      AND p.estado = 'entregado'
      AND dp.producto_id = ?
    LIMIT 1
  ");
  $checkCompra->execute([$usuarioId, $id]);
  $puedeResenar = (bool) $checkCompra->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_resena'])) {
  if ($usuarioId <= 0) {
    $errorResena = 'Debes iniciar sesion para dejar una resena.';
  } elseif (!$puedeResenar) {
    $errorResena = 'Solo puedes resenar productos que hayas comprado y recibido.';
  } else {
    $puntuacion = (int) ($_POST['puntuacion'] ?? 0);
    $comentario = trim((string) ($_POST['comentario'] ?? ''));

    if ($puntuacion < 1 || $puntuacion > 5) {
      $errorResena = 'Selecciona una puntuacion valida entre 1 y 5.';
    } elseif (strlen($comentario) < 5 || strlen($comentario) > 500) {
      $errorResena = 'El comentario debe tener entre 5 y 500 caracteres.';
    } else {
      $upsert = $conn->prepare("
        INSERT INTO `reseñas` (producto_id, usuario_id, puntuacion, comentario, compra_verificada, fecha)
        VALUES (?, ?, ?, ?, 1, NOW())
        ON DUPLICATE KEY UPDATE
          puntuacion = VALUES(puntuacion),
          comentario = VALUES(comentario),
          compra_verificada = 1,
          fecha = NOW()
      ");
      $upsert->execute([$id, $usuarioId, $puntuacion, $comentario]);
      $mensajeResena = 'Resena guardada correctamente.';
    }
  }
}

$tallasStmt = $conn->prepare("
  SELECT t.nombre AS talla, pt.stock
  FROM producto_tallas pt
  JOIN tallas t ON pt.talla_id = t.id
  WHERE pt.producto_id = ?
  ORDER BY t.nombre ASC
");
$tallasStmt->execute([$id]);
$tallas = $tallasStmt->fetchAll(PDO::FETCH_ASSOC);

$imagenes = $conn->prepare("SELECT archivo FROM producto_imagenes WHERE producto_id = ?");
$imagenes->execute([$id]);
$galeria = $imagenes->fetchAll(PDO::FETCH_ASSOC);

$imagenPrincipal = trim((string) ($producto['imagen'] ?? ''));
if ($imagenPrincipal === '' && !empty($galeria[0]['archivo'])) {
  $imagenPrincipal = (string) $galeria[0]['archivo'];
}
$producto['imagen'] = $imagenPrincipal;
$imagenPrincipal = appResolveProductImage($producto, __DIR__ . '/assets/img/productos');

$resumenResenasStmt = $conn->prepare("
  SELECT COUNT(*) AS total, AVG(puntuacion) AS promedio
  FROM `reseñas`
  WHERE producto_id = ?
");
$resumenResenasStmt->execute([$id]);
$resumenResenas = $resumenResenasStmt->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'promedio' => 0];

$resenasStmt = $conn->prepare("
  SELECT r.puntuacion, r.comentario, r.fecha, r.compra_verificada, u.nombre
  FROM `reseñas` r
  INNER JOIN usuarios u ON u.id = r.usuario_id
  WHERE r.producto_id = ?
  ORDER BY r.fecha DESC
  LIMIT 30
");
$resenasStmt->execute([$id]);
$resenas = $resenasStmt->fetchAll(PDO::FETCH_ASSOC);

include 'header.php';
?>

<main class="container py-5">
  <div class="row g-5 align-items-start">
    <div class="col-lg-6">
      <div class="product-gallery">
        <img id="imagen-principal"
             src="assets/img/productos/<?= htmlspecialchars($imagenPrincipal) ?>"
             class="img-fluid rounded"
             style="max-height:520px; width:100%; object-fit:cover; transition:transform .25s ease;"
             alt="<?= htmlspecialchars((string) $producto['nombre']) ?>"
             onerror="this.onerror=null;this.src='assets/img/productos/look-default.svg';">
      </div>

      <?php if (count($galeria) > 1): ?>
        <div class="d-flex flex-wrap gap-2 mt-3">
          <?php foreach ($galeria as $img): ?>
            <?php
              $archivo = trim((string) ($img['archivo'] ?? ''));
              if ($archivo === '') {
                continue;
              }

              $tmpProduct = [
                'id' => $producto['id'] ?? 0,
                'nombre' => $producto['nombre'] ?? '',
                'categoria' => $producto['categoria'] ?? '',
                'imagen' => $archivo
              ];
              $archivo = appResolveProductImage($tmpProduct, __DIR__ . '/assets/img/productos');
            ?>
            <button type="button"
                    class="border-0 bg-transparent p-0"
                    onclick="cambiarImagen('assets/img/productos/<?= htmlspecialchars($archivo) ?>')">
              <img src="assets/img/productos/<?= htmlspecialchars($archivo) ?>"
                   style="width:68px; height:68px; object-fit:cover; border-radius:10px; border:1px solid #ccd9e3;"
                    alt="Miniatura producto"
                    onerror="this.onerror=null;this.src='assets/img/productos/look-default.svg';">
            </button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="col-lg-6">
      <h2 class="product-main-title mb-2"><?= htmlspecialchars((string) $producto['nombre']) ?></h2>
      <p class="text-soft mb-4"><?= nl2br(htmlspecialchars((string) ($producto['descripcion'] ?? ''))) ?></p>
      <?php $precioConIva = (float) $producto['precio'] * 1.19; ?>
      <h3 class="product-price mb-4">$<?= number_format($precioConIva, 0, ',', '.') ?> <small class="text-soft">(IVA incl.)</small></h3>

      <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
          <h6 class="mb-3">Ficha tecnica</h6>
          <div class="row g-2 small">
            <div class="col-6"><strong>SKU:</strong> <?= htmlspecialchars((string) ($producto['sku'] ?? '-')) ?></div>
            <div class="col-6"><strong>Marca:</strong> <?= htmlspecialchars((string) ($producto['marca'] ?? '-')) ?></div>
            <div class="col-6"><strong>Color:</strong> <?= htmlspecialchars((string) ($producto['color'] ?? '-')) ?></div>
            <div class="col-6"><strong>Material:</strong> <?= htmlspecialchars((string) ($producto['material'] ?? '-')) ?></div>
            <div class="col-6"><strong>Fit:</strong> <?= htmlspecialchars((string) ($producto['fit'] ?? '-')) ?></div>
            <div class="col-6"><strong>Categoria:</strong> <?= htmlspecialchars((string) ($producto['categoria'] ?? '-')) ?></div>
          </div>
        </div>
      </div>

      <?php if ($tallas): ?>
        <div class="mb-3">
          <h6 class="mb-3">Selecciona talla</h6>
          <div class="d-flex gap-2 flex-wrap">
            <?php foreach ($tallas as $t): ?>
              <button type="button"
                      class="talla-btn"
                      data-talla="<?= htmlspecialchars((string) $t['talla']) ?>"
                      data-stock="<?= (int) $t['stock'] ?>"
                      <?= (int) $t['stock'] <= 0 ? 'disabled' : '' ?>>
                <?= htmlspecialchars((string) $t['talla']) ?>
              </button>
            <?php endforeach; ?>
          </div>
          <div id="stockInfo" class="text-soft mt-2"></div>
        </div>
      <?php else: ?>
        <p class="text-soft">Este producto no tiene tallas disponibles.</p>
      <?php endif; ?>

      <button id="btnAgregar" class="btn btn-primary mt-3 px-4" disabled>
        Agregar al carrito
      </button>
    </div>
  </div>

  <section class="mt-5">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
      <h3 class="mb-0">Resenas de clientes</h3>
      <div class="text-soft">
        Promedio: <strong><?= number_format((float) ($resumenResenas['promedio'] ?? 0), 1) ?>/5</strong>
        (<?= (int) ($resumenResenas['total'] ?? 0) ?> resenas)
      </div>
    </div>

    <?php if ($mensajeResena !== ''): ?>
      <div class="alert alert-success"><?= htmlspecialchars($mensajeResena) ?></div>
    <?php endif; ?>
    <?php if ($errorResena !== ''): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($errorResena) ?></div>
    <?php endif; ?>

    <?php if ($usuarioId > 0): ?>
      <?php if ($puedeResenar): ?>
        <div class="card border-0 shadow-sm mb-4">
          <div class="card-body">
            <h5 class="mb-3">Tu resena (compra verificada)</h5>
            <form method="post" class="row g-3">
              <input type="hidden" name="guardar_resena" value="1">
              <div class="col-md-3">
                <label class="form-label">Puntuacion</label>
                <select name="puntuacion" class="form-select" required>
                  <option value="">Seleccionar</option>
                  <?php for ($i = 5; $i >= 1; $i--): ?>
                    <option value="<?= $i ?>"><?= $i ?> / 5</option>
                  <?php endfor; ?>
                </select>
              </div>
              <div class="col-md-9">
                <label class="form-label">Comentario</label>
                <textarea name="comentario" class="form-control" rows="3" maxlength="500" required></textarea>
              </div>
              <div class="col-12">
                <button class="btn btn-primary">Guardar resena</button>
              </div>
            </form>
          </div>
        </div>
      <?php else: ?>
        <div class="alert alert-info">
          Podras dejar una resena cuando este producto figure como entregado en alguno de tus pedidos.
        </div>
      <?php endif; ?>
    <?php else: ?>
      <div class="alert alert-info">
        <a href="login.php" class="alert-link">Inicia sesion</a> para dejar tu resena.
      </div>
    <?php endif; ?>

    <?php if ($resenas): ?>
      <div class="row g-3">
        <?php foreach ($resenas as $r): ?>
          <div class="col-12">
            <div class="card border-0 shadow-sm">
              <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <strong><?= htmlspecialchars((string) $r['nombre']) ?></strong>
                  <small class="text-muted"><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string) $r['fecha']))) ?></small>
                </div>
                <div class="mb-2" style="color:#e0a402;">
                  <?php $puntuacion = max(1, min(5, (int) $r['puntuacion'])); ?>
                  <?php for ($i = 1; $i <= 5; $i++): ?>
                    <?= $i <= $puntuacion ? '&#9733;' : '&#9734;' ?>
                  <?php endfor; ?>
                  <?php if ((int) ($r['compra_verificada'] ?? 0) === 1): ?>
                    <span class="badge text-bg-success ms-2">Compra verificada</span>
                  <?php endif; ?>
                </div>
                <p class="mb-0"><?= nl2br(htmlspecialchars((string) $r['comentario'])) ?></p>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="text-soft">Aun no hay resenas para este producto.</p>
    <?php endif; ?>
  </section>
</main>

<script>
let tallaSeleccionada = null;
let stockSeleccionado = 0;

const btnAgregar = document.getElementById("btnAgregar");
const stockInfo = document.getElementById("stockInfo");
const botonesTalla = document.querySelectorAll(".talla-btn");

botonesTalla.forEach(btn => {
  btn.addEventListener("click", function() {
    botonesTalla.forEach(b => b.classList.remove("active"));
    this.classList.add("active");

    tallaSeleccionada = this.dataset.talla;
    stockSeleccionado = parseInt(this.dataset.stock, 10);

    stockInfo.innerHTML = stockSeleccionado > 0
      ? `Stock disponible: <strong>${stockSeleccionado}</strong>`
      : `<span class="text-danger">Sin stock</span>`;

    btnAgregar.disabled = stockSeleccionado <= 0;
  });
});

btnAgregar.addEventListener("click", function() {
  if (!tallaSeleccionada) {
    if (typeof window.appSwalFire === "function") {
      window.appSwalFire({
        icon: "warning",
        title: "Selecciona una talla",
        text: "Debes seleccionar una talla.",
        confirmButtonText: "Entendido"
      });
    }
    return;
  }

  agregarCarrito(
    "<?= htmlspecialchars((string) $producto['nombre']) ?>",
    <?= (float) $producto['precio'] ?>,
    <?= (int) $producto['id'] ?>,
    tallaSeleccionada
  );
});

function cambiarImagen(src) {
  const img = document.getElementById("imagen-principal");
  if (img) {
    img.src = src;
  }
}

const zoom = document.getElementById("imagen-principal");
if (zoom) {
  zoom.addEventListener("mousemove", function(e) {
    const rect = zoom.getBoundingClientRect();
    const x = ((e.clientX - rect.left) / rect.width) * 100;
    const y = ((e.clientY - rect.top) / rect.height) * 100;
    zoom.style.transformOrigin = `${x}% ${y}%`;
  });

  zoom.addEventListener("mouseenter", () => zoom.style.transform = "scale(1.28)");
  zoom.addEventListener("mouseleave", () => zoom.style.transform = "scale(1)");
}
</script>

<?php include 'footer.php'; ?>
