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

// Función global para mostrar términos en modal SweetAlert (sin usar alert() nativo)
window.mostrarTerminosModal = function (checkboxId) {
  const checkbox = document.getElementById(checkboxId);

  const fire = typeof window.appSwalFire === "function"
    ? window.appSwalFire
    : (window.Swal && typeof window.Swal.fire === "function" ? window.Swal.fire.bind(window.Swal) : null);

  if (!checkbox) {
    if (fire) {
      fire({
        icon: "error",
        title: "No disponible",
        text: "No fue posible cargar los términos y condiciones en este momento.",
        confirmButtonText: "Entendido"
      });
    }
    return false;
  }

  // Si Swal aun no esta cargado (p.ej. click muy temprano), redirigimos a la pagina.
  if (!fire) {
    window.location.href = "terminos.php";
    return false;
  }

  fire({
    icon: "info",
    title: "Términos y Condiciones",
    html: TERMINOS_CONTENIDO,
    width: 650,
    confirmButtonText: "Aceptar y marcar",
    cancelButtonText: "Cerrar",
    showCancelButton: true,
    allowOutsideClick: false,
    allowEscapeKey: true,
    reverseButtons: true
  }).then((result) => {
    if (!result || !result.isConfirmed) {
      return;
    }

    checkbox.checked = true;
    checkbox.dispatchEvent(new Event("change", { bubbles: true }));

    const toast = typeof window.appSwalToast === "function" ? window.appSwalToast : null;

    if (toast) {
      toast({
        icon: "success",
        title: "Términos aceptados"
      });
      return;
    }

    fire({
      icon: "success",
      title: "¡Excelente!",
      text: "Has aceptado los términos y condiciones.",
      confirmButtonText: "Continuar",
      timer: 1200,
      showConfirmButton: true
    });
  });

  return false;
};


