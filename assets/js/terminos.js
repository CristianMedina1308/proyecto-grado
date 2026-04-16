/**
 * Módulo para manejo de Términos y Condiciones con SweetAlert
 */

const TERMINOS_CONTENIDO = `
<div style="text-align: left; max-height: 400px; overflow-y: auto; padding: 0 10px;">
  <h4>Términos y Condiciones de Tauro Store</h4>
  
  <h5>1. Identificación del comercio</h5>
  <p>Tauro Store es el responsable de la operación comercial de este sitio. Los datos de contacto, canales oficiales y medios de atención publicados en la página se entienden como los medios válidos para soporte, novedades de pedidos y atención postventa.</p>
  
  <h5>2. Uso del sitio</h5>
  <p>Al navegar, registrarte o realizar una compra, aceptas usar esta plataforma de manera lícita, suministrar información veraz y no afectar el funcionamiento técnico del sitio ni la experiencia de otros usuarios.</p>
  
  <h5>3. Productos, precios y disponibilidad</h5>
  <p>Los productos, tallas, precios, promociones y existencias están sujetos a disponibilidad real. Tauro Store puede actualizar catálogo, descripciones, precios o inventario sin previo aviso.</p>
  
  <h5>4. Modalidades de entrega</h5>
  <p><strong>Contra entrega:</strong> Requiere registrar nombre, teléfono, dirección, barrio, ciudad y zona.</p>
  <p><strong>Recoger en tienda:</strong> Permite reservar el pedido para retiro presencial sin costo de envío.</p>
  
  <h5>5. Confirmación del pedido</h5>
  <p>El pedido se considera recibido cuando el sistema lo registra correctamente y genera su número de identificación.</p>
  
  <h5>6. Política de datos personales</h5>
  <p>Los datos suministrados se utilizan para registro, atención, gestión de pedidos, soporte y facturación. Al aceptar estos términos, autorizas su tratamiento para esas finalidades.</p>
  
  <h5>7. Cambios, devoluciones y soporte</h5>
  <p>Las solicitudes deben gestionarse por los canales oficiales publicados por Tauro Store.</p>
  
  <h5>8. Modificaciones</h5>
  <p>Tauro Store puede actualizar estos términos cuando sea necesario. La versión publicada será la referencia aplicable.</p>
</div>
`;

document.addEventListener("DOMContentLoaded", function() {
  // Agregar listeners a todos los links de términos
  const terminosLinks = document.querySelectorAll(".terminos-link");

  terminosLinks.forEach(link => {
    link.addEventListener("click", function(e) {
      e.preventDefault();

      const checkboxId = this.getAttribute("data-checkbox");
      const checkbox = document.getElementById(checkboxId);

      if (!checkbox) {
        console.error("Checkbox no encontrado:", checkboxId);
        return;
      }

      mostrarTerminos(checkbox);
    });
  });

  // Validar formularios
  validarRegistro();
  validarCheckout();
});

/**
 * Mostrar términos en SweetAlert
 */
function mostrarTerminos(checkbox) {
  if (!window.Swal) {
    // Fallback si SweetAlert no está disponible
    alert("Ver términos en: terminos.php");
    return;
  }

  Swal.fire({
    title: "Términos y Condiciones",
    html: TERMINOS_CONTENIDO,
    icon: "info",
    width: "600px",
    confirmButtonText: "Aceptar y Marcar",
    cancelButtonText: "Cerrar",
    showCancelButton: true,
    confirmButtonColor: "#b89247",
    cancelButtonColor: "#6b6054",
    didClose: function() {
      // Nada que hacer al cerrar
    }
  }).then((result) => {
    if (result.isConfirmed) {
      // Marcar el checkbox
      checkbox.checked = true;

      // Disparar evento change para que cualquier listener se ejecute
      checkbox.dispatchEvent(new Event("change", { bubbles: true }));

      // Mostrar confirmación visual
      Swal.fire({
        title: "¡Excelente!",
        text: "Has aceptado los términos y condiciones.",
        icon: "success",
        confirmButtonText: "Continuar",
        confirmButtonColor: "#b89247",
        timer: 2000
      });
    }
  });
}

/**
 * Validar registro
 */
function validarRegistro() {
  const form = document.querySelector("form");
  const checkboxTerminos = document.getElementById("acepta_terminos_registro");

  if (!form || !checkboxTerminos) {
    return;
  }

  form.addEventListener("submit", function(event) {
    if (!checkboxTerminos.checked) {
      event.preventDefault();
      event.stopPropagation();

      if (window.Swal) {
        Swal.fire({
          icon: "warning",
          title: "Términos no aceptados",
          text: "Debes aceptar los términos y condiciones para registrarte.",
          confirmButtonText: "OK",
          confirmButtonColor: "#b89247"
        });
      } else {
        alert("Debes aceptar los términos y condiciones para registrarte.");
      }

      checkboxTerminos.focus();
      return false;
    }
  }, true);
}

/**
 * Validar checkout
 */
function validarCheckout() {
  const checkboxTerminos = document.getElementById("acepta_terminos_checkout");
  const form = document.getElementById("checkout-form");

  if (!checkboxTerminos || !form) {
    return;
  }

  form.addEventListener("submit", function(event) {
    if (!checkboxTerminos.checked) {
      event.preventDefault();
      event.stopPropagation();

      if (window.Swal) {
        Swal.fire({
          icon: "warning",
          title: "Términos no aceptados",
          text: "Debes aceptar los términos y condiciones antes de finalizar la compra.",
          confirmButtonText: "OK",
          confirmButtonColor: "#b89247"
        });
      } else {
        alert("Debes aceptar los términos y condiciones antes de finalizar la compra.");
      }

      checkboxTerminos.focus();
      return false;
    }
  }, true);
}

