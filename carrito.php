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
            <th>Precio (IVA incl.)</th>
            <th>Cantidad</th>
            <th>Subtotal (IVA incl.)</th>
            <th>Eliminar</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>

    <hr class="my-4">

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
      <div>
        <h3 class="mb-1">Total (IVA incl.): <span class="status-price">$<span id="total-carrito">0</span></span></h3>
        <div class="text-soft small" id="carrito-desglose-iva"></div>
      </div>

      <a href="checkout.php" class="btn btn-primary px-4">
        Finalizar compra
      </a>
    </div>
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
  const tabla = document.querySelector("#tabla-carrito tbody");
  const totalSpan = document.getElementById("total-carrito");

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

  function actualizarContadorCarrito() {
    const carrito = leerCarrito();
    const contador = document.getElementById("contador-carrito");
    if (contador) {
      const total = carrito.reduce((acc, item) => acc + Number(item.cantidad || 1), 0);
      contador.textContent = total;
    }
  }

  function eliminarDelCarrito(index) {
    const carrito = leerCarrito();
    carrito.splice(index, 1);
    guardarCarrito(carrito);
    renderizar();
  }

  function cambiarTalla(index, nuevaTalla) {
    const carrito = leerCarrito();

    if (!carrito[index]) {
      return;
    }

    carrito[index].talla = nuevaTalla || null;
    guardarCarrito(mergeItems(carrito));
    renderizar();
  }

  async function renderizar() {
    const carrito = leerCarrito();
    const ids = [...new Set(carrito.map((item) => Number(item.id)).filter(Boolean))];
    const metaProductos = await cargarMetaProductos(ids);

    const ivaRate = Number(window.TAURO_IVA_RATE ?? 0.19);
    const desglose = document.getElementById("carrito-desglose-iva");

    let subtotalBase = 0;
    let totalConIva = 0;

    tabla.innerHTML = "";

    if (carrito.length === 0) {
      tabla.innerHTML = `
        <tr>
          <td colspan="5" class="py-5 text-soft">
            Tu carrito esta vacio.
          </td>
        </tr>
      `;
      totalSpan.textContent = "0";
      if (desglose) {
        desglose.textContent = "";
      }
      actualizarContadorCarrito();
      return;
    }

    carrito.forEach((item, index) => {
      const cantidad = Number(item.cantidad ?? 1);
      const precioBase = Number(item.precio ?? 0);

      const subtotalBaseItem = precioBase * cantidad;
      const precioConIva = precioBase * (1 + ivaRate);
      const subtotalConIva = precioConIva * cantidad;

      subtotalBase += subtotalBaseItem;
      totalConIva += subtotalConIva;

      const meta = metaProductos[Number(item.id)] || null;
      const requiereTalla = !!(meta && meta.requires_size);

      let contenidoProducto = `<div class="fw-semibold">${item.nombre}</div>`;

      if (requiereTalla) {
        const opciones = (meta.sizes || []).map((size) => `
          <option value="${size.name}" ${String(item.talla || "") === String(size.name) ? "selected" : ""} ${!size.available ? "disabled" : ""}>
            ${size.name}${size.available ? "" : " - Sin stock"}
          </option>
        `).join("");

        contenidoProducto += `
          <div class="mt-2">
            <label class="form-label small text-soft mb-1">Talla</label>
            <select class="form-select form-select-sm" onchange="window.cambiarTallaCarrito(${index}, this.value)">
              <option value="">Seleccionar talla</option>
              ${opciones}
            </select>
          </div>
        `;
      } else if (item.talla) {
        contenidoProducto += `<div class="small text-soft mt-1">Talla ${item.talla}</div>`;
      }

      const fila = document.createElement("tr");
      fila.innerHTML = `
        <td></td>
        <td class="status-price">$${precioConIva.toLocaleString()}</td>
        <td>${cantidad}</td>
        <td class="status-price">$${subtotalConIva.toLocaleString()}</td>
        <td>
          <button class="btn btn-outline-primary btn-sm" onclick="eliminarDelCarrito(${index})">
            Quitar
          </button>
        </td>
      `;
      fila.children[0].innerHTML = contenidoProducto;
      tabla.appendChild(fila);
    });

    totalSpan.textContent = totalConIva.toLocaleString();

    const ivaMonto = subtotalBase * ivaRate;
    if (desglose) {
      desglose.textContent = `Subtotal sin IVA: $${subtotalBase.toLocaleString()} | IVA (${Math.round(ivaRate * 100)}%): $${ivaMonto.toLocaleString()}`;
    }

    actualizarContadorCarrito();
  }

  window.eliminarDelCarrito = eliminarDelCarrito;
  window.cambiarTallaCarrito = cambiarTalla;

  renderizar();
});
</script>

<?php include 'footer.php'; ?>
