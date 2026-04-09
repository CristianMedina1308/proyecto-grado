<?php
include 'header.php';
include 'includes/conexion.php';

$busqueda = trim($_GET['buscar'] ?? '');
$categoria = trim($_GET['categoria'] ?? '');

$sql = "SELECT * FROM productos WHERE 1";
$params = [];

if ($busqueda !== '') {
  $sql .= " AND nombre LIKE ?";
  $params[] = "%$busqueda%";
}

if ($categoria !== '') {
  $sql .= " AND categoria = ?";
  $params[] = $categoria;
}

$sql .= " ORDER BY id DESC";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$categorias = $conn->query("SELECT DISTINCT categoria FROM productos WHERE categoria IS NOT NULL AND categoria <> '' ORDER BY categoria ASC")->fetchAll(PDO::FETCH_COLUMN);
?>

<main class="container py-5">
  <div class="row align-items-end mb-5">
    <div class="col-md-7">
      <h1 class="coleccion-titulo">Coleccion</h1>
      <p class="coleccion-subtitulo">
        Productos seleccionados con una presentacion limpia y profesional.
      </p>
    </div>
    <div class="col-md-5 text-md-end">
      <small class="coleccion-count"><?= count($productos) ?> productos encontrados</small>
    </div>
  </div>

  <form method="get" class="row g-3 mb-5 filtros-bar">
    <div class="col-md-4">
      <input type="text" name="buscar" class="form-control" placeholder="Buscar producto" value="<?= htmlspecialchars($busqueda) ?>">
    </div>

    <div class="col-md-3">
      <select name="categoria" class="form-select">
        <option value="">Categorias</option>
        <?php foreach ($categorias as $cat): ?>
          <option value="<?= htmlspecialchars($cat) ?>" <?= $cat === $categoria ? 'selected' : '' ?>>
            <?= htmlspecialchars(ucfirst($cat)) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-2">
      <button type="submit" class="btn btn-primary w-100">Filtrar</button>
    </div>

    <div class="col-md-2">
      <a href="productos.php" class="btn btn-outline-primary w-100">Limpiar</a>
    </div>
  </form>

  <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-5">
    <?php if ($productos): ?>
      <?php foreach ($productos as $p): ?>
        <?php
          $imagenProducto = trim((string) ($p['imagen'] ?? ''));
          if ($imagenProducto === '' || !file_exists(__DIR__ . '/assets/img/productos/' . $imagenProducto)) {
            $imagenProducto = 'look-default.svg';
          }
        ?>
        <div class="col">
          <article class="producto-card">
            <button type="button"
                    class="favorito-toggle border-0"
                    data-fav-id="<?= (int) $p['id'] ?>"
                    onclick="toggleFavorito(<?= (int) $p['id'] ?>)"
                    aria-label="Agregar a favoritos">
              <i class="bi bi-heart"></i>
            </button>

            <div class="producto-imagen-wrapper">
              <img src="assets/img/productos/<?= htmlspecialchars($imagenProducto) ?>" alt="<?= htmlspecialchars($p['nombre']) ?>">
              <div class="producto-overlay">
                <a href="producto.php?id=<?= (int) $p['id'] ?>" class="btn-overlay">Comprar ahora</a>
              </div>
            </div>

            <div class="producto-info">
              <h6 class="producto-nombre"><?= htmlspecialchars($p['nombre']) ?></h6>
              <p class="producto-precio mb-0">$<?= number_format((float) $p['precio'], 0, ',', '.') ?></p>
            </div>
          </article>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="col-12 text-center">
        <p class="text-soft">No se encontraron productos con estos filtros.</p>
      </div>
    <?php endif; ?>
  </div>
</main>

<?php include 'footer.php'; ?>
