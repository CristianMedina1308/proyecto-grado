<?php include 'header.php'; ?>

<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-lg-10">
      <div class="card p-4 p-md-5">
        <div class="mb-4">
          <span class="section-kicker">Privacidad</span>
          <h1 class="checkout-title mb-3">Politica de cookies</h1>
          <p class="text-soft mb-0">
            Esta pagina explica que tecnologias de almacenamiento usa Tauro Store, para que sirven y como
            puedes aceptar o rechazar las que no sean estrictamente necesarias.
          </p>
        </div>

        <div class="mb-4">
          <h5>1. Que usamos en este sitio</h5>
          <p class="mb-0">
            Tauro Store utiliza cookies tecnicas de sesion para funciones esenciales del sitio y almacenamiento
            local del navegador para mejorar algunas funciones como carrito, favoritos y la preferencia del
            aviso de cookies.
          </p>
        </div>

        <div class="mb-4">
          <h5>2. Cookies esenciales</h5>
          <p class="mb-3">
            Estas cookies son necesarias para el funcionamiento basico de la aplicacion y no se usan con fines
            publicitarios.
          </p>
          <div class="table-responsive">
            <table class="table align-middle">
              <thead>
                <tr>
                  <th>Nombre</th>
                  <th>Tipo</th>
                  <th>Finalidad</th>
                  <th>Duracion</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td><code>PHPSESSID</code></td>
                  <td>Cookie tecnica</td>
                  <td>Mantiene la sesion del usuario, autenticacion, mensajes temporales y proteccion CSRF.</td>
                  <td>Sesion</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="mb-4">
          <h5>3. Almacenamiento local del navegador</h5>
          <p class="mb-3">
            Adicionalmente, el sitio usa <em>localStorage</em>. Esto no es una cookie, pero conviene informarlo
            porque tambien guarda informacion en el navegador.
          </p>
          <div class="table-responsive">
            <table class="table align-middle">
              <thead>
                <tr>
                  <th>Clave</th>
                  <th>Tipo</th>
                  <th>Finalidad</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td><code>carrito</code></td>
                  <td>localStorage</td>
                  <td>Conserva temporalmente los productos agregados al carrito antes de finalizar la compra.</td>
                </tr>
                <tr>
                  <td><code>favoritos</code></td>
                  <td>localStorage</td>
                  <td>Guarda los productos marcados como favoritos dentro del navegador del usuario.</td>
                </tr>
                <tr>
                  <td><code>tauro_cookie_consent</code></td>
                  <td>localStorage</td>
                  <td>Registra si aceptaste o rechazaste el aviso de cookies para no mostrarlo en cada visita.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="mb-4">
          <h5>4. Aceptar o rechazar</h5>
          <p class="mb-0">
            Puedes aceptar o rechazar el aviso de cookies desde el banner mostrado en el sitio. Si rechazas,
            Tauro Store no activara almacenamiento opcional adicional desde ese aviso, pero algunas funciones
            tecnicas esenciales pueden seguir requiriendo la cookie de sesion cuando inicias sesion, envias
            formularios protegidos o gestionas pedidos.
          </p>
        </div>

        <div>
          <h5>5. Gestion desde tu navegador</h5>
          <p class="mb-0">
            Tambien puedes eliminar cookies o datos almacenados desde la configuracion de tu navegador. Ten en
            cuenta que hacerlo puede cerrar tu sesion o afectar funciones como carrito, favoritos o formularios.
          </p>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include 'footer.php'; ?>
