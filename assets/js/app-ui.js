function appGetSpanishDataTableLanguage() {
  return {
    decimal: ",",
    thousands: ".",
    emptyTable: "No hay datos disponibles en la tabla",
    info: "Mostrando _START_ a _END_ de _TOTAL_ registros",
    infoEmpty: "Mostrando 0 a 0 de 0 registros",
    infoFiltered: "(filtrado de _MAX_ registros totales)",
    lengthMenu: "Mostrar _MENU_ registros",
    loadingRecords: "Cargando...",
    processing: "Procesando...",
    search: "Buscar:",
    zeroRecords: "No se encontraron resultados",
    paginate: {
      first: "Primero",
      last: "Ultimo",
      next: "Siguiente",
      previous: "Anterior"
    }
  };
}

function appShowFlashes() {
  if (!window.Swal || !Array.isArray(window.APP_FLASHES) || window.APP_FLASHES.length === 0) {
    return;
  }

  window.APP_FLASHES.reduce((chain, flash) => {
    return chain.then(() => {
      const type = String(flash.type || "info");
      const title = String(flash.title || "");
      const message = String(flash.message || "");

      return window.Swal.fire({
        icon: type,
        title: title || message,
        text: title ? message : "",
        confirmButtonText: "Entendido",
        customClass: {
          confirmButton: "btn btn-primary"
        },
        buttonsStyling: false
      });
    });
  }, Promise.resolve());
}

function appInitConfirmModal() {
  const modalElement = document.getElementById("app-confirm-modal");
  if (!modalElement || !window.bootstrap) {
    return;
  }

  const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
  const titleElement = modalElement.querySelector("[data-confirm-title]");
  const messageElement = modalElement.querySelector("[data-confirm-message]");
  const acceptButton = modalElement.querySelector("[data-confirm-accept]");

  let pendingForm = null;
  let pendingSubmitter = null;

  document.addEventListener("submit", (event) => {
    const form = event.target;

    if (
      event.defaultPrevented ||
      !(form instanceof HTMLFormElement) ||
      form.dataset.confirm !== "true" ||
      form.dataset.confirmed === "true"
    ) {
      return;
    }

    event.preventDefault();

    pendingForm = form;
    pendingSubmitter = event.submitter || null;

    const source = pendingSubmitter || form;
    const title = source.dataset.confirmTitle || form.dataset.confirmTitle || "Confirmar accion";
    const message = source.dataset.confirmMessage || form.dataset.confirmMessage || "Esta accion requiere confirmacion.";
    const buttonText = source.dataset.confirmButton || form.dataset.confirmButton || "Confirmar";
    const buttonClass = source.dataset.confirmVariant || form.dataset.confirmVariant || "btn-primary";

    if (titleElement) {
      titleElement.textContent = title;
    }

    if (messageElement) {
      messageElement.textContent = message;
    }

    if (acceptButton) {
      acceptButton.textContent = buttonText;
      acceptButton.className = "btn " + buttonClass;
    }

    modal.show();
  });

  modalElement.addEventListener("hidden.bs.modal", () => {
    pendingForm = null;
    pendingSubmitter = null;
  });

  if (acceptButton) {
    acceptButton.addEventListener("click", () => {
      if (!pendingForm) {
        modal.hide();
        return;
      }

      pendingForm.dataset.confirmed = "true";

      if (typeof pendingForm.requestSubmit === "function") {
        pendingForm.requestSubmit(pendingSubmitter || undefined);
      } else {
        if (pendingSubmitter && pendingSubmitter.name) {
          const hidden = document.createElement("input");
          hidden.type = "hidden";
          hidden.name = pendingSubmitter.name;
          hidden.value = pendingSubmitter.value;
          pendingForm.appendChild(hidden);
        }
        pendingForm.submit();
      }

      modal.hide();
    });
  }
}

function appInitDataTables() {
  if (!window.jQuery || !jQuery.fn || !jQuery.fn.DataTable) {
    return;
  }

  document.querySelectorAll("table[data-datatable='true']").forEach((table) => {
    if (jQuery.fn.DataTable.isDataTable(table)) {
      return;
    }

    const noSort = String(table.dataset.noSort || "")
      .split(",")
      .map((item) => Number.parseInt(item.trim(), 10))
      .filter((item) => Number.isInteger(item));

    jQuery(table).DataTable({
      pageLength: Number.parseInt(table.dataset.pageLength || "10", 10),
      responsive: false,
      language: appGetSpanishDataTableLanguage(),
      order: [],
      columnDefs: noSort.length ? [{ targets: noSort, orderable: false }] : []
    });
  });
}

document.addEventListener("DOMContentLoaded", () => {
  appShowFlashes();
  appInitConfirmModal();
  appInitDataTables();
});
