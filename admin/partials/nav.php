<?php
$adminActive = isset($adminActive) ? (string) $adminActive : '';

$adminLinks = [
  'dashboard' => ['href' => 'index.php', 'label' => 'Dashboard'],
  'productos' => ['href' => 'productos.php', 'label' => 'Inventario'],
  'usuarios' => ['href' => 'usuarios.php', 'label' => 'Usuarios'],
  'pedidos' => ['href' => 'pedidos.php', 'label' => 'Pedidos']
];
?>
<nav class="navbar navbar-expand-lg admin-navbar sticky-top">
  <div class="container-fluid px-3 px-lg-4">
    <a class="admin-brand" href="index.php">
      <span class="admin-brand-mark">TS</span>
      <span>Tauro Admin</span>
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbarMenu" aria-controls="adminNavbarMenu" aria-expanded="false" aria-label="Abrir menu admin">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="adminNavbarMenu">
      <div class="navbar-nav gap-lg-2 ms-auto align-items-lg-center py-3 py-lg-0">
        <?php foreach ($adminLinks as $key => $link): ?>
          <a href="<?= htmlspecialchars($link['href']) ?>" class="admin-nav-link<?= $adminActive === $key ? ' is-active' : '' ?>">
            <?= htmlspecialchars($link['label']) ?>
          </a>
        <?php endforeach; ?>
        <a href="../index.php" class="admin-nav-link">Ver tienda</a>
        <a href="../logout.php" class="admin-nav-link">Cerrar sesion</a>
      </div>
    </div>
  </div>
</nav>
