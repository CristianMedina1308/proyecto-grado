<?php
include 'header.php';
include 'includes/conexion.php';

$destacadosPreferidos = [
  'Chaqueta Bomber Negra',
  'Jean Slim Fit Azul Oscuro',
  'Camiseta Essential Blanca',
  'Tenis Urban Classic Blanco'
];

$destacadosStmt = $conn->prepare("
  SELECT *
  FROM productos
  WHERE nombre IN (?, ?, ?, ?)
  ORDER BY CASE nombre
    WHEN ? THEN 1
    WHEN ? THEN 2
    WHEN ? THEN 3
    WHEN ? THEN 4
    ELSE 99
  END
  LIMIT 4
");
$destacadosStmt->execute(array_merge($destacadosPreferidos, $destacadosPreferidos));
$destacados = $destacadosStmt->fetchAll(PDO::FETCH_ASSOC);

if (count($destacados) < 4) {
  $destacados = $conn->query("SELECT * FROM productos ORDER BY id DESC LIMIT 4")->fetchAll(PDO::FETCH_ASSOC);
}
$categorias = $conn->query("SELECT DISTINCT categoria FROM productos WHERE categoria IS NOT NULL AND categoria <> ''")->fetchAll(PDO::FETCH_COLUMN);
$totalProductos = (int) ($conn->query("SELECT COUNT(*) FROM productos")->fetchColumn() ?: 0);

$heroBrandImage = file_exists(__DIR__ . '/assets/img/hero-tauro-campaign.svg')
  ? 'assets/img/hero-tauro-campaign.svg'
  : (file_exists(__DIR__ . '/assets/img/Tauro Store.webp')
    ? 'assets/img/Tauro%20Store.webp'
    : '');
?>

<main class="home-shell">
  <section class="hero-home fade-in">
    <div class="container hero-grid">
      <div class="hero-content">
        <span class="hero-kicker">Moda masculina premium</span>
        <h1 class="hero-title">Estilo con peso propio.</h1>
        <p class="hero-subtitle">
          Siluetas limpias, tonos sobrios y una experiencia de compra mas clara para
          vestir con presencia sin complicarte.
        </p>

        <div class="hero-actions">
          <a href="productos.php" class="btn btn-primary px-4">Descubrir coleccion</a>
          <a href="contacto.php" class="btn btn-outline-primary px-4">Hablar con asesor</a>
        </div>

        <div class="hero-metrics">
          <div class="hero-metric">
            <strong><?= $totalProductos > 0 ? (int) $totalProductos : 12 ?>+</strong>
            <span>referencias seleccionadas</span>
          </div>
          <div class="hero-metric">
            <strong><?= count($categorias) ?></strong>
            <span>categorias para combinar</span>
          </div>
          <div class="hero-metric">
            <strong>24/7</strong>
            <span>acompanamiento con Tauro Concierge</span>
          </div>
        </div>
      </div>

      <div class="hero-stage">
        <div class="hero-stage-card">
          <?php if ($heroBrandImage !== ''): ?>
            <img src="<?= htmlspecialchars($heroBrandImage) ?>"
                 alt="Banner editorial Tauro"
                 class="hero-brand-image">
          <?php else: ?>
            <div class="hero-brand-wordmark">TAURO</div>
          <?php endif; ?>
        </div>

        <div class="hero-note hero-note-primary">
          Negro, dorado envejecido y una linea mas sobria para vestir hombre.
        </div>

        <div class="hero-note hero-note-secondary">
          Compra simple, seguimiento claro y productos listos para elevar el look.
        </div>
      </div>
    </div>
  </section>

  <section class="container home-section home-section-first fade-in">
    <div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-4">
      <div>
        <h2 class="mb-1">Nueva seleccion</h2>
        <p class="text-soft mb-0">Productos destacados para esta temporada.</p>
      </div>
      <a href="productos.php" class="fw-semibold">Ver todos</a>
    </div>

    <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-4">
      <?php if ($destacados): ?>
        <?php foreach ($destacados as $p): ?>
          <?php
            $imagenProducto = trim((string) ($p['imagen'] ?? ''));
            if ($imagenProducto === '' || !file_exists(__DIR__ . '/assets/img/productos/' . $imagenProducto)) {
              $imagenProducto = 'look-default.svg';
            }
          ?>
          <div class="col">
            <a href="producto.php?id=<?= (int) $p['id'] ?>" class="text-decoration-none">
              <article class="producto-card">
                <div class="producto-imagen-wrapper">
                  <img src="assets/img/productos/<?= htmlspecialchars($imagenProducto) ?>" alt="<?= htmlspecialchars($p['nombre']) ?>">
                </div>
                <div class="producto-info">
                  <h6 class="producto-nombre mb-1"><?= htmlspecialchars($p['nombre']) ?></h6>
                  <p class="producto-precio mb-0">$<?= number_format((float) $p['precio'], 0, ',', '.') ?></p>
                </div>
              </article>
            </a>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="col-12">
          <div class="alert alert-info mb-0">Aun no hay productos para mostrar.</div>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <section class="container home-section home-section-tight fade-in">
    <div class="section-soft p-4 p-md-5 text-center">
      <h2 class="mb-2">Compra con confianza</h2>
      <p class="text-soft mb-0 mx-auto" style="max-width: 700px;">
        Catalogo actualizado, proceso de pago simplificado y seguimiento de pedidos
        para una experiencia mas profesional en cada compra.
      </p>
    </div>
  </section>

  <section class="container home-section home-section-compact fade-in">
    <div class="d-flex justify-content-between align-items-end flex-wrap gap-2 mb-4">
      <h2 class="mb-0">Explorar por categoria</h2>
      <small class="text-soft"><?= count($categorias) ?> categorias disponibles</small>
    </div>

    <div class="row row-cols-2 row-cols-md-4 g-3">
      <?php foreach ($categorias as $cat): ?>
        <div class="col">
          <a href="productos.php?categoria=<?= urlencode($cat) ?>" class="category-tile">
            <?= htmlspecialchars(strtoupper($cat)) ?>
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="container home-section home-section-last text-center fade-in">
    <h2 class="mb-2">Encuentra tu siguiente favorito</h2>
    <p class="text-soft mb-4">Coleccion completa disponible para compra inmediata.</p>
    <a href="productos.php" class="btn btn-primary px-5">Comprar ahora</a>
  </section>
</main>

<?php include 'footer.php'; ?>
