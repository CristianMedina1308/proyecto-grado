<?php
require_once __DIR__ . '/includes/app.php';

// Logo: preferimos SVG (se ve nitido y con buen contraste). Fallback a WebP existente.
$logoUrl = 'assets/img/logo.svg';
$logoPath = __DIR__ . '/assets/img/logo.svg';
if (!is_file($logoPath)) {
  $logoUrl = 'assets/img/Tauro%20Store.webp';
  $logoPath = __DIR__ . '/assets/img/Tauro Store.webp';
}
$logoExiste = is_file($logoPath);
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Tauro Store | Estilo que impone caracter</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <?php include __DIR__ . '/includes/ui_head.php'; ?>
  <?php
    $assetVersion = (string) (file_exists(__DIR__ . '/assets/css/style.css') ? filemtime(__DIR__ . '/assets/css/style.css') : time());
  ?>
  <link rel="stylesheet" href="assets/css/style.css?v=<?= $assetVersion ?>">
  <link rel="stylesheet" href="assets/css/responsive.css?v=<?= $assetVersion ?>">
</head>

<body class="page-shell">

<div id="tauro-loader">
  <div class="loader-content">
    <div class="loader-logo">TAURO</div>
  </div>
</div>

<script>
// Tasa de IVA global (usada por carrito/checkout para mostrar totales consistentes)
window.TAURO_IVA_RATE = 0.19;
</script>

<script>
(() => {
  let hidden = false;

  function hideLoader() {
    if (hidden) {
      return;
    }

    hidden = true;
    const loader = document.getElementById("tauro-loader");

    if (!loader) {
      return;
    }

    loader.style.opacity = "0";
    loader.style.pointerEvents = "none";

    setTimeout(() => {
      loader.style.display = "none";
    }, 220);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => {
      window.setTimeout(hideLoader, 80);
    }, { once: true });
  } else {
    window.setTimeout(hideLoader, 80);
  }

  window.addEventListener("load", hideLoader, { once: true });
  window.setTimeout(hideLoader, 900);

  // Hardening: En algunos navegadores/hosts, el evento load puede no dispararse como se espera
  // (o la pagina puede volver desde bfcache). Si el loader quedara encima, bloquearia toda la UI.
  // Estos failsafes aseguran que nunca quede atrapado.
  window.addEventListener("pageshow", hideLoader);
  window.setTimeout(hideLoader, 3000);
})();
</script>

<nav class="navbar navbar-expand-lg navbar-light sticky-top site-navbar">
  <div class="container-fluid px-3 px-md-4">
    <a class="navbar-brand d-flex align-items-center" href="index.php">
      <?php if ($logoExiste): ?>
        <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Tauro Store" height="42" class="me-2 brand-logo">
      <?php else: ?>
        <span class="me-2 fw-bold px-2 py-1 rounded bg-light border">TS</span>
      <?php endif; ?>
      <span>Tauro <span class="brand-highlight">Store</span></span>
    </a>

    <div class="d-flex align-items-center order-lg-2 ms-auto">
      <button class="bag-button position-relative me-2" id="btnMiniCarrito" aria-label="Abrir carrito">
        <i class="bi bi-bag-fill"></i>
        <span id="contador-carrito" class="position-absolute top-0 start-100 translate-middle badge rounded-pill">0</span>
      </button>
    </div>

    <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse order-lg-1" id="navbarNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item">
          <a class="nav-link" href="index.php">Inicio</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="productos.php">Coleccion</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="contacto.php">Contacto</a>
        </li>

        <?php if (isset($_SESSION['usuario'])): ?>
          <li class="nav-item">
            <a class="nav-link" href="perfil.php">
              <i class="bi bi-person"></i> Perfil
            </a>
          </li>

          <?php if ($_SESSION['usuario']['rol'] === 'admin'): ?>
            <li class="nav-item">
              <a class="nav-link nav-admin fw-bold" href="admin/index.php">Admin</a>
            </li>
          <?php endif; ?>

          <li class="nav-item">
            <a class="nav-link" href="logout.php">Salir</a>
          </li>
        <?php else: ?>
          <li class="nav-item">
            <a class="nav-link" href="login.php">Ingresar</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="registro.php">Registrarse</a>
          </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<div id="mini-carrito" class="mini-carrito">
  <h5 class="mb-3 mini-cart-title">Tu carrito</h5>
  <ul id="lista-mini-carrito" class="list-unstyled mb-3"></ul>
  <a href="checkout.php" class="btn btn-primary w-100">Finalizar compra</a>
</div>

<a href="https://wa.me/573175378274" class="whatsapp-float" target="_blank" rel="noopener" title="Necesitas ayuda?">
  <i class="bi bi-whatsapp"></i>
</a>

<button type="button" class="chatbot-toggle" id="btnChatbot" aria-label="Abrir asistente Tauro">
  <span class="chatbot-toggle-icon"><i class="bi bi-chat-dots-fill"></i></span>
  <span class="chatbot-toggle-text">Asistente</span>
</button>

<section class="chatbot-panel"
         id="chatbotPanel"
         aria-live="polite"
         aria-label="Asistente Tauro"
         data-api-url="chatbot_api.php"
         data-csrf-token="<?= htmlspecialchars(appCsrfToken('chatbot_publico'), ENT_QUOTES, 'UTF-8') ?>"
         data-whatsapp="+57 317 537 8274">
  <div class="chatbot-header">
    <div>
      <div class="chatbot-eyebrow">Tauro Concierge</div>
      <h5 class="mb-0">Asistente Tauro</h5>
    </div>
    <button type="button" class="chatbot-close" id="btnCerrarChatbot" aria-label="Cerrar asistente">
      <i class="bi bi-x-lg"></i>
    </button>
  </div>

  <div class="chatbot-body">
    <div class="chatbot-messages" id="chatbotMessages"></div>

    <div class="chatbot-suggestions" id="chatbotSuggestions">
      <button type="button" class="chatbot-chip">Arma un outfit</button>
      <button type="button" class="chatbot-chip">Ver chaquetas negras</button>
      <button type="button" class="chatbot-chip">Guia de tallas</button>
      <button type="button" class="chatbot-chip">Consultar pedido con token</button>
    </div>
  </div>

  <form class="chatbot-form" id="chatbotForm">
    <input type="text"
           id="chatbotInput"
           class="chatbot-input"
           placeholder="Pregunta por prendas, estilo o cualquier duda..."
           autocomplete="off"
           maxlength="500">
    <button type="submit" class="chatbot-send" aria-label="Enviar mensaje">
      <i class="bi bi-arrow-up-right"></i>
    </button>
  </form>
</section>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- SweetAlert2 se carga en includes/ui_footer.php para toda la aplicacion -->
<script src="assets/js/terminos.js?v=<?= $assetVersion ?>"></script>
<script>
document.addEventListener("DOMContentLoaded", () => {
  const btnCarrito = document.getElementById("btnMiniCarrito");
  if (btnCarrito && typeof toggleMiniCarrito === "function") {
    btnCarrito.addEventListener("click", toggleMiniCarrito);
  } else if (btnCarrito) {
    btnCarrito.addEventListener("click", () => {
      const mini = document.getElementById("mini-carrito");
      if (!mini) return;
      mini.style.display = (mini.style.display === "none" || mini.style.display === "") ? "block" : "none";
    });
  }
});
</script>
