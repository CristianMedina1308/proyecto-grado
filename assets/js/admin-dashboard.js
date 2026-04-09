document.addEventListener("DOMContentLoaded", () => {
  const stats = window.ADMIN_DASHBOARD_DATA || null;
  const modalElement = document.getElementById("dashboardAnalyticsModal");

  if (!stats) {
    return;
  }

  const charts = {};
  let chartsReady = false;
  const palette = ["#b89247", "#8a6521", "#d7b56d", "#4d3620", "#caa05a", "#ead9b0"];

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

    createChart("dashboardTopProductos", {
      type: "bar",
      data: {
        labels: stats.topProductos.labels,
        datasets: [{
          label: "Vendidos",
          data: stats.topProductos.values,
          backgroundColor: palette,
          borderRadius: 12,
          maxBarThickness: 54
        }]
      },
      options: {
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: baseScales()
      }
    });

    createChart("dashboardEstadosPedidos", {
      type: "doughnut",
      data: {
        labels: stats.estados.labels,
        datasets: [{
          data: stats.estados.values,
          backgroundColor: ["#b89247", "#8a6521", "#d7b56d", "#2d6a4f", "#b23a48", "#ead9b0"],
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

    createChart("dashboardVentasMensuales", {
      type: "line",
      data: {
        labels: stats.ventasMensuales.labels,
        datasets: [{
          label: "Ventas",
          data: stats.ventasMensuales.values,
          borderColor: "#8a6521",
          backgroundColor: "rgba(184,146,71,0.16)",
          fill: true,
          tension: 0.34,
          pointRadius: 4,
          pointBackgroundColor: "#b89247"
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

    chartsReady = true;
  }

  function downloadDashboardCsv() {
    const sections = [
      {
        title: "Resumen dashboard",
        rows: [
          ["Indicador", "Valor"],
          ["Ventas mes", stats.summary.ventasMes],
          ["Pedidos mes", stats.summary.pedidosMes],
          ["Ticket promedio", stats.summary.ticketPromedio],
          ["Cancelados mes", stats.summary.canceladosMes],
          ["Tasa cancelacion", stats.summary.tasaCancelacion],
          ["Stock critico", stats.summary.stockCritico],
          ["Sin stock", stats.summary.sinStock],
          ["Productos", stats.summary.totalProductos],
          ["Usuarios", stats.summary.totalUsuarios],
          ["Talla top", stats.summary.tallaTop],
          ["Mes analizado", stats.summary.mesActual]
        ]
      },
      {
        title: "Top productos",
        rows: [
          ["Producto", "Cantidad vendida"],
          ...stats.topProductos.rows.map((row) => [row.nombre, row.total_vendidos])
        ]
      },
      {
        title: "Estados pedidos",
        rows: [
          ["Estado", "Cantidad"],
          ...stats.estados.rows.map((row) => [row.estado, row.cantidad])
        ]
      },
      {
        title: "Stock bajo",
        rows: [
          ["ID", "Producto", "Stock total"],
          ...stats.stockBajo.map((row) => [row.id, row.nombre, row.stock_total])
        ]
      },
      {
        title: "Ventas mensuales",
        rows: [
          ["Mes", "Ventas"],
          ...stats.ventasMensuales.labels.map((label, index) => [label, stats.ventasMensuales.values[index] || 0])
        ]
      }
    ];

    triggerCsvDownload("dashboard-tauro.csv", buildCsv(sections));
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

  document.getElementById("btnDescargarDashboardCsv")?.addEventListener("click", downloadDashboardCsv);
  document.getElementById("btnDescargarDashboardCsvModal")?.addEventListener("click", downloadDashboardCsv);

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
