document.addEventListener("DOMContentLoaded", () => {
  const stats = window.ADMIN_USERS_DATA || null;
  const modalElement = document.getElementById("usuariosAnalyticsModal");

  if (!stats) {
    return;
  }

  const charts = {};
  let chartsReady = false;
  const palette = ["#b89247", "#8a6521", "#d7b56d", "#4d3620", "#ead9b0", "#75614a"];

  function money(value) {
    return new Intl.NumberFormat("es-CO", {
      style: "currency",
      currency: "COP",
      maximumFractionDigits: 0
    }).format(Number(value || 0));
  }

  function csvEscape(value) {
    const text = String(value ?? "");
    return /[",\r\n]/.test(text) ? `"${text.replace(/"/g, "\"\"")}"` : text;
  }

  function buildCsv(sections) {
    const lines = [];

    sections.forEach((section, index) => {
      if (index > 0) {
        lines.push("");
      }

      lines.push(csvEscape(section.title));
      section.rows.forEach((row) => {
        lines.push(row.map(csvEscape).join(","));
      });
    });

    return "\uFEFF" + lines.join("\r\n");
  }

  function triggerCsvDownload(filename, content) {
    const blob = new Blob([content], { type: "text/csv;charset=utf-8;" });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");

    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();

    window.setTimeout(() => URL.revokeObjectURL(url), 1000);
  }

  function createChart(id, config) {
    const canvas = document.getElementById(id);
    if (!canvas) {
      return null;
    }

    if (!charts[id]) {
      charts[id] = new Chart(canvas, config);
    }

    return charts[id];
  }

  function baseScales() {
    return {
      x: {
        ticks: { color: "#4c4135" },
        grid: { display: false }
      },
      y: {
        ticks: { color: "#4c4135" },
        grid: { color: "rgba(128,102,53,0.12)" }
      }
    };
  }

  function initializeCharts() {
    if (chartsReady) {
      return;
    }

    createChart("usersRoleChart", {
      type: "doughnut",
      data: {
        labels: stats.roles.labels,
        datasets: [{
          data: stats.roles.values,
          backgroundColor: ["#17130f", "#b89247"],
          borderWidth: 0
        }]
      },
      options: {
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: "bottom",
            labels: {
              color: "#4c4135",
              usePointStyle: true,
              padding: 18
            }
          }
        }
      }
    });

    createChart("usersSpendChart", {
      type: "bar",
      data: {
        labels: stats.spenders.labels,
        datasets: [{
          label: "Facturacion",
          data: stats.spenders.values,
          backgroundColor: palette,
          borderRadius: 12,
          maxBarThickness: 54
        }]
      },
      options: {
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: (context) => ` ${money(context.parsed.y)}`
            }
          }
        },
        scales: {
          x: baseScales().x,
          y: {
            ...baseScales().y,
            ticks: {
              color: "#4c4135",
              callback: (value) => money(value)
            }
          }
        }
      }
    });

    createChart("usersOrdersChart", {
      type: "bar",
      data: {
        labels: stats.orders.labels,
        datasets: [{
          label: "Pedidos",
          data: stats.orders.values,
          backgroundColor: "#8a6521",
          borderRadius: 10,
          maxBarThickness: 48
        }]
      },
      options: {
        indexAxis: "y",
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          x: baseScales().y,
          y: baseScales().x
        }
      }
    });

    chartsReady = true;
  }

  function downloadUsersCsv() {
    const sections = [
      {
        title: "Resumen usuarios",
        rows: [
          ["Indicador", "Valor"],
          ["Usuarios", stats.summary.totalUsuarios],
          ["Admins", stats.summary.totalAdmins],
          ["Clientes", stats.summary.totalClientes],
          ["Compradores activos", stats.summary.compradoresActivos],
          ["Facturacion usuarios", stats.summary.facturacionUsuarios],
          ["Ticket promedio usuario", stats.summary.ticketPromedioUsuario]
        ]
      },
      {
        title: "Detalle usuarios",
        rows: [
          ["ID", "Nombre", "Email", "Rol", "Pedidos", "Total comprado", "Ultimo pedido"],
          ...stats.rows.map((row) => [
            row.id,
            row.nombre,
            row.email,
            row.rol,
            row.total_pedidos,
            row.total_gastado,
            row.ultimo_pedido
          ])
        ]
      }
    ];

    triggerCsvDownload("usuarios-tauro.csv", buildCsv(sections));
  }

  function downloadChart(chartId, filename) {
    initializeCharts();
    const chart = charts[chartId];

    if (!chart) {
      return;
    }

    const link = document.createElement("a");
    link.href = chart.toBase64Image("image/png", 1);
    link.download = `${filename}.png`;
    document.body.appendChild(link);
    link.click();
    link.remove();
  }

  document.getElementById("btnDescargarUsuariosCsv")?.addEventListener("click", downloadUsersCsv);
  document.getElementById("btnDescargarUsuariosCsvModal")?.addEventListener("click", downloadUsersCsv);

  document.querySelectorAll("[data-download-chart]").forEach((button) => {
    button.addEventListener("click", () => {
      downloadChart(button.dataset.downloadChart, button.dataset.filename || "grafico");
    });
  });

  modalElement?.addEventListener("shown.bs.modal", () => {
    initializeCharts();
    Object.values(charts).forEach((chart) => chart.resize());
  });
});
