/**
 * Módulo para manejo de Términos y Condiciones con SweetAlert
 */

console.log("✅ terminos.js cargado correctamente");

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

// Función global para mostrar términos en modal SweetAlert
window.mostrarTerminosModal = function(checkboxId) {
  console.log("✅ mostrarTerminosModal llamado con checkboxId:", checkboxId);

  const checkbox = document.getElementById(checkboxId);
  console.log("✅ Checkbox encontrado:", checkbox);

  if (!checkbox) {
    console.error("❌ Checkbox no encontrado:", checkboxId);
    alert("Error al cargar términos y condiciones");
    return false;
  }

  if (!window.Swal) {
    console.error("❌ SweetAlert no está disponible");
    alert("Ver términos y condiciones en: terminos.php");
    return false;
  }

  console.log("✅ Abriendo SweetAlert...");

  Swal.fire({
    title: "Términos y Condiciones",
    html: TERMINOS_CONTENIDO,
    icon: "info",
    width: "650px",
    confirmButtonText: "Aceptar y Marcar",
    cancelButtonText: "Cerrar",
    showCancelButton: true,
    confirmButtonColor: "#b89247",
    cancelButtonColor: "#6b6054",
    allowOutsideClick: false,
    allowEscapeKey: true
  }).then((result) => {
    console.log("✅ SweetAlert resultado:", result);

    if (result.isConfirmed) {
      console.log("✅ Usuario aceptó términos");

      // Marcar el checkbox
      checkbox.checked = true;
      console.log("✅ Checkbox marcado:", checkbox.checked);

      // Dispara evento change para actualizar UI
      checkbox.dispatchEvent(new Event("change", { bubbles: true }));
      console.log("✅ Evento change disparado");

      // Mostrar confirmación
      Swal.fire({
        title: "¡Excelente!",
        text: "Has aceptado los términos y condiciones.",
        icon: "success",
        confirmButtonText: "Continuar",
        confirmButtonColor: "#b89247",
        timer: 1200,
        timerProgressBar: true
      });
    }
  });

  return false;
};


