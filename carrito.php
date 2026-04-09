<?php include 'header.php'; ?>

<div class="container py-5">
  <div class="text-center mb-5">
    <h1 class="title-accent mb-2">Tu carrito</h1>
    <p class="text-soft">Revisa tus productos antes de finalizar la compra.</p>
  </div>

  <div class="cart-shell">
    <div class="table-responsive">
      <table class="table align-middle text-center" id="tabla-carrito">
        <thead>
          <tr>
            <th>Producto</th>
            <th>Precio</th>
            <th>Cantidad</th>
            <th>Subtotal</th>
            <th>Eliminar</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>

    <hr class="my-4">

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
      <h3 class="mb-0">Total: <span class="status-price">$<span id="total-carrito">0</span></span></h3>

      <a href="checkout.php" class="btn btn-primary px-4">
        Finalizar compra
      </a>
    </div>
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
  const carrito = JSON.parse(localStorage.getItem("carrito")) || [];
  const tabla = document.querySelector("#tabla-carrito tbody");
  const totalSpan = document.getElementById("total-carrito");

  let total = 0;
  tabla.innerHTML = "";

  if (carrito.length === 0) {
    tabla.innerHTML = `
      <tr>
        <td colspan="5" class="py-5 text-soft">
          Tu carrito esta vacio.
        </td>
      </tr>
    `;
  } else {
    carrito.forEach((item, index) => {
      const cantidad = Number(item.cantidad ?? 1);
      const precio = Number(item.precio ?? 0);
      const subtotal = precio * cantidad;
      total += subtotal;

      const nombreProducto = item.talla
        ? `${item.nombre} - Talla ${item.talla}`
        : item.nombre;

      const fila = document.createElement("tr");
      fila.innerHTML = `
        <td>${nombreProducto}</td>
        <td class="status-price">$${precio.toLocaleString()}</td>
        <td>${cantidad}</td>
        <td class="status-price">$${subtotal.toLocaleString()}</td>
        <td>
          <button class="btn btn-outline-primary btn-sm" onclick="eliminarDelCarrito(${index})">
            Quitar
          </button>
        </td>
      `;
      tabla.appendChild(fila);
    });
  }

  totalSpan.textContent = total.toLocaleString();
  actualizarContadorCarrito();
});

function eliminarDelCarrito(index) {
  let carrito = JSON.parse(localStorage.getItem("carrito")) || [];
  carrito.splice(index, 1);
  localStorage.setItem("carrito", JSON.stringify(carrito));
  location.reload();
}

function actualizarContadorCarrito() {
  const carrito = JSON.parse(localStorage.getItem("carrito")) || [];
  const contador = document.getElementById("contador-carrito");
  if (contador) {
    contador.textContent = carrito.length;
  }
}
</script>

<?php include 'footer.php'; ?>
