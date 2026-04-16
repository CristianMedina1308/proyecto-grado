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

function appHasSwal() {
  return !!(window.Swal && typeof window.Swal.fire === "function");
}

function appSwalMergeCustomClass(baseClass, overrideClass) {
  return Object.assign({}, baseClass || {}, overrideClass || {});
}

function appSwalFire(options) {
  if (!appHasSwal()) {
    return Promise.resolve({ isConfirmed: true });
  }

  const baseCustomClass = {
    confirmButton: "btn btn-primary",
    cancelButton: "btn btn-outline-secondary"
  };

  const mergedOptions = Object.assign({}, options || {});
  mergedOptions.customClass = appSwalMergeCustomClass(baseCustomClass, mergedOptions.customClass);
  mergedOptions.buttonsStyling = false;

  return window.Swal.fire(mergedOptions);
}

function appSwalToast(options) {
  if (!appHasSwal()) {
    return Promise.resolve();
  }

  const mergedOptions = Object.assign(
    {
      toast: true,
      position: "top-end",
      showConfirmButton: false,
      timer: 1800,
      timerProgressBar: true,
      customClass: {
        popup: "app-swal-popup"
      }
    },
    options || {}
  );

  return window.Swal.fire(mergedOptions);
}

// Exponer helpers por si otros scripts inline los necesitan.
window.appSwalFire = appSwalFire;
window.appSwalToast = appSwalToast;

function appShowFlashes() {
  if (!appHasSwal() || !Array.isArray(window.APP_FLASHES) || window.APP_FLASHES.length === 0) {
    return;
  }

  window.APP_FLASHES.reduce((chain, flash) => {
    return chain.then(() => {
      const type = String(flash.type || "info");
      const title = String(flash.title || "");
      const message = String(flash.message || "");

      return appSwalFire({
        icon: type,
        title: title || message,
        text: title ? message : "",
        confirmButtonText: "Entendido"
      });
    });
  }, Promise.resolve());
}

function appInitConfirmModal() {
  // Si SweetAlert2 esta disponible, usamos confirmaciones con Swal.
  if (appHasSwal()) {
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

      const submitter = event.submitter || null;
      const source = submitter || form;
      const title = source.dataset.confirmTitle || form.dataset.confirmTitle || "Confirmar accion";
      const message = source.dataset.confirmMessage || form.dataset.confirmMessage || "Esta accion requiere confirmacion.";
      const buttonText = source.dataset.confirmButton || form.dataset.confirmButton || "Confirmar";

      appSwalFire({
        icon: "question",
        title: title,
        text: message,
        showCancelButton: true,
        confirmButtonText: buttonText,
        cancelButtonText: "Cancelar",
        reverseButtons: true
      }).then((result) => {
        if (!result || !result.isConfirmed) {
          return;
        }

        form.dataset.confirmed = "true";

        if (typeof form.requestSubmit === "function") {
          form.requestSubmit(submitter || undefined);
          return;
        }

        if (submitter && submitter.name) {
          const hidden = document.createElement("input");
          hidden.type = "hidden";
          hidden.name = submitter.name;
          hidden.value = submitter.value;
          form.appendChild(hidden);
        }

        form.submit();
      });
    });

    return;
  }

  // Fallback: modal Bootstrap existente.
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
