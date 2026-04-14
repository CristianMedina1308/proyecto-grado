<footer class="site-footer pt-5 pb-4 mt-5 fade-in">
  <div class="container">
    <div class="row text-center text-md-start">
      <div class="col-md-4 mb-4">
        <h5 class="footer-title">Tauro Store</h5>
        <p class="footer-muted">
          Elegancia, caracter y estilo en cada prenda.
          Diseñado para quienes proyectan presencia.
        </p>
      </div>

      <div class="col-md-4 mb-4">
        <h6 class="footer-title">Enlaces</h6>
        <ul class="list-unstyled footer-muted">
          <li><a href="index.php">Inicio</a></li>
          <li><a href="productos.php">Coleccion</a></li>
          <li><a href="carrito.php">Carrito</a></li>
          <li><a href="contacto.php">Contacto</a></li>
          <li><a href="terminos.php">Terminos y condiciones</a></li>
        </ul>
      </div>

      <div class="col-md-4 mb-4 text-center text-md-end">
        <h6 class="footer-title">Siguenos</h6>
        <a href="https://facebook.com" target="_blank" class="me-2 social-icon" aria-label="Facebook">
          <i class="bi bi-facebook"></i>
        </a>
        <a href="https://instagram.com" target="_blank" class="me-2 social-icon" aria-label="Instagram">
          <i class="bi bi-instagram"></i>
        </a>
        <a href="https://tiktok.com" target="_blank" class="social-icon" aria-label="TikTok">
          <i class="bi bi-tiktok"></i>
        </a>
      </div>
    </div>

    <hr class="my-4">

    <div class="text-center footer-muted small">
      &copy; <?= date('Y') ?> Tauro Store. Todos los derechos reservados.
    </div>
  </div>
</footer>

<div class="cookie-banner" id="cookieBanner" hidden aria-live="polite" aria-label="Aviso de cookies">
  <div class="cookie-banner__content">
    <div class="cookie-banner__text">
      <strong>Uso de cookies y almacenamiento local</strong>
      <p class="mb-0">
        Tauro Store usa una cookie tecnica de sesion para funciones esenciales y almacenamiento local para carrito,
        favoritos y tu preferencia del aviso. Puedes aceptar o rechazar este aviso y consultar el detalle en la
        <a href="cookies.php">politica de cookies</a>.
      </p>
    </div>
    <div class="cookie-banner__actions">
      <button type="button" class="btn btn-outline-primary" id="cookieReject">Rechazar</button>
      <button type="button" class="btn btn-primary" id="cookieAccept">Aceptar</button>
    </div>
  </div>
</div>

<?php
$appAssetPrefix = '';
include __DIR__ . '/includes/ui_footer.php';
?>

<script src="assets/js/script.js"></script>
<script src="assets/js/chatbot.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
  const elements = Array.from(document.querySelectorAll(".fade-in"));

  function revealVisibleElements(extraOffset = 0) {
    const revealLimit = window.innerHeight + extraOffset;

    elements.forEach((element) => {
      if (element.classList.contains("visible")) {
        return;
      }

      const rect = element.getBoundingClientRect();

      if (rect.top <= revealLimit) {
        element.classList.add("visible");
      }
    });
  }

  revealVisibleElements(140);
  window.requestAnimationFrame(() => revealVisibleElements(220));

  if ("IntersectionObserver" in window) {
    const observer = new IntersectionObserver((entries, currentObserver) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) {
          return;
        }

        entry.target.classList.add("visible");
        currentObserver.unobserve(entry.target);
      });
    }, {
      threshold: 0.01,
      rootMargin: "0px 0px 180px 0px"
    });

    elements.forEach((element) => {
      if (element.classList.contains("visible")) {
        return;
      }

      observer.observe(element);
    });
  } else {
    elements.forEach((element) => element.classList.add("visible"));
  }

  document.body.classList.add("page-loaded");

  document.querySelectorAll("a").forEach(link => {
    if (
      link.hostname === window.location.hostname &&
      !link.target &&
      !link.href.includes("#") &&
      !link.href.startsWith("javascript:")
    ) {
      link.addEventListener("click", function(e) {
        if (this.getAttribute("href") !== "#") {
          e.preventDefault();
          document.body.classList.remove("page-loaded");
          setTimeout(() => {
            window.location = this.href;
          }, 220);
        }
      });
    }
  });
});

window.addEventListener("scroll", function() {
  const navbar = document.querySelector(".site-navbar");
  if (!navbar) return;

  if (window.scrollY > 30) {
    navbar.classList.add("scrolled");
  } else {
    navbar.classList.remove("scrolled");
  }
});
</script>

</body>
</html>
