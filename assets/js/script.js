// ===========================
// CARRITO PROFESIONAL CON TALLA
// ===========================

function mostrarAlertaCarrito(nombre, cantidad, talla = null) {
  const titulo = cantidad > 1 ? "Producto actualizado" : "Producto agregado";
  const detalleTalla = talla ? ` Talla ${talla}.` : "";
  const mensaje = `${nombre} ahora está en tu carrito.${detalleTalla}`;

  if (window.Swal) {
    window.Swal.fire({
      icon: "success",
      title: titulo,
      text: mensaje,
      timer: 1800,
      showConfirmButton: false,
      toast: true,
      position: "top-end",
      customClass: {
        popup: "app-swal-popup"
      }
    });
    return;
  }

  alert("✅ " + mensaje);
}

function mostrarAlertaFavorito(agregado = true) {
  const config = agregado
    ? {
        icon: "success",
        title: "Agregado a favoritos",
        text: "El producto se guardó en tu lista de deseos."
      }
    : {
        icon: "info",
        title: "Eliminado de favoritos",
        text: "El producto se quitó de tu lista de deseos."
      };

  if (window.Swal) {
    window.Swal.fire({
      icon: config.icon,
      title: config.title,
      text: config.text,
      timer: 1700,
      showConfirmButton: false,
      toast: true,
      position: "top-end",
      customClass: {
        popup: "app-swal-popup"
      }
    });
    return;
  }

  alert((agregado ? "💖 " : "❌ ") + config.text);
}

function agregarCarrito(nombre, precio, id, talla = null) {

  if (!id || isNaN(id)) {
    alert("Error: El producto no tiene un ID válido.");
    return;
  }

  let carrito = JSON.parse(localStorage.getItem("carrito")) || [];

  // 🔥 Buscar producto por ID + talla
  let producto = carrito.find(p => 
    p.id === id && p.talla === talla
  );

  if (producto) {
    producto.cantidad += 1;
  } else {
    carrito.push({
      id: Number(id),
      nombre: nombre,
      precio: Number(precio),
      talla: talla, // 👈 ahora guardamos talla
      cantidad: 1
    });
  }

  localStorage.setItem("carrito", JSON.stringify(carrito));

  actualizarContadorCarrito();
  mostrarMiniCarrito();
  mostrarAlertaCarrito(nombre, producto ? producto.cantidad : 1, talla);
}

function actualizarContadorCarrito() {
  const carrito = JSON.parse(localStorage.getItem("carrito")) || [];

  const total = carrito.reduce((acc, item) => {
    return acc + (item.cantidad ?? 1);
  }, 0);

  const contador = document.getElementById("contador-carrito");

  if (contador) {
    contador.innerText = total;
    contador.style.display = total > 0 ? "inline-block" : "none";
  }
}

function toggleMiniCarrito() {
  const mini = document.getElementById("mini-carrito");

  if (!mini) return;

  mini.style.display =
    mini.style.display === "none" || mini.style.display === ""
      ? "block"
      : "none";
}

function mostrarMiniCarrito() {
  const carrito = JSON.parse(localStorage.getItem("carrito")) || [];
  const lista = document.getElementById("lista-mini-carrito");

  if (!lista) return;

  lista.innerHTML = "";

  if (carrito.length === 0) {
    lista.innerHTML = "<li>Tu carrito está vacío.</li>";
    return;
  }

  carrito.forEach(item => {

    const subtotal = item.precio * (item.cantidad ?? 1);

    const li = document.createElement("li");
    li.classList.add("mb-2");

    li.innerHTML = `
      <div>
        <span>
          ${item.nombre}
          ${item.talla ? `- <strong>Talla ${item.talla}</strong>` : ""}
          x${item.cantidad ?? 1}
        </span>
        <strong class="float-end">
          $${subtotal.toLocaleString()}
        </strong>
      </div>
    `;

    lista.appendChild(li);
  });
}

// ===========================
// FAVORITOS
// ===========================

function toggleFavorito(id) {
  let favoritos = JSON.parse(localStorage.getItem("favoritos")) || [];
  id = Number(id);

  const idx = favoritos.indexOf(id);

  if (idx !== -1) {
    favoritos.splice(idx, 1);
    mostrarAlertaFavorito(false);
  } else {
    favoritos.push(id);
    mostrarAlertaFavorito(true);
  }

  localStorage.setItem("favoritos", JSON.stringify(favoritos));
  updateFavoriteIcons();
}

function updateFavoriteIcons() {
  const favoritos = JSON.parse(localStorage.getItem("favoritos")) || [];

  document.querySelectorAll("[data-fav-id]").forEach(btn => {
    const id = Number(btn.getAttribute("data-fav-id"));
    const icon = btn.querySelector("i");

    if (!icon) return;

    if (favoritos.includes(id)) {
      icon.classList.remove("bi-heart");
      icon.classList.add("bi-heart-fill", "text-danger");
    } else {
      icon.classList.remove("bi-heart-fill", "text-danger");
      icon.classList.add("bi-heart");
    }
  });
}

// ===========================
// INICIALIZACIÓN
// ===========================

document.addEventListener("DOMContentLoaded", () => {

  mostrarMiniCarrito();
  actualizarContadorCarrito();
  updateFavoriteIcons();

  // Cerrar mini carrito al hacer click fuera
  document.addEventListener("click", function (e) {

    const mini = document.getElementById("mini-carrito");
    const icono = document.getElementById("btnMiniCarrito");

    if (!mini || !icono) return;

    if (!mini.contains(e.target) && !icono.contains(e.target)) {
      mini.style.display = "none";
    }

  });

});
