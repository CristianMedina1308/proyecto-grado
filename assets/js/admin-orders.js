document.addEventListener("DOMContentLoaded", () => {
  const stats = window.ADMIN_ORDERS_DATA || null;
  const modalElement = document.getElementById("pedidosAnalyticsModal");

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

    createChart("ordersStateChart", {
      type: "doughnut",
      data: {
        labels: stats.states.labels,
        datasets: [{
          data: stats.states.values,
          backgroundColor: ["#b7791f", "#b89247", "#8a6521", "#2d6a4f", "#b23a48", "#ead9b0"],
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

    createChart("ordersRevenueChart", {
      type: "line",
      data: {
        labels: stats.monthly.labels,
        datasets: [{
          label: "Ingresos",
          data: stats.monthly.revenue,
          borderColor: "#8a6521",
          backgroundColor: "rgba(184,146,71,0.16)",
          fill: true,
          tension: 0.32,
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

    createChart("ordersVolumeChart", {
      type: "bar",
      data: {
        labels: stats.monthly.labels,
        datasets: [{
          label: "Pedidos",
          data: stats.monthly.orders,
          backgroundColor: palette,
          borderRadius: 12,
          maxBarThickness: 48
        }]
      },
      options: {
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: baseScales()
      }
    });

    createChart("ordersHighValueChart", {
      type: "bar",
      data: {
        labels: stats.highValue.labels,
        datasets: [{
          label: "Total pedido",
          data: stats.highValue.values,
          backgroundColor: "#17130f",
          borderRadius: 10,
          maxBarThickness: 40
        }]
      },
      options: {
        indexAxis: "y",
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: (context) => ` ${money(context.parsed.x)}`
            }
          }
        },
        scales: {
          x: {
            ...baseScales().y,
            ticks: {
              color: "#4c4135",
              callback: (value) => money(value)
            }
          },
          y: baseScales().x
        }
      }
    });

    chartsReady = true;
  }

  function downloadOrdersCsv() {
    const sections = [
      {
        title: "Resumen pedidos",
        rows: [
          ["Indicador", "Valor"],
          ["Pedidos", stats.summary.totalPedidos],
          ["Ingresos acumulados", stats.summary.ingresosTotales],
          ["Ticket promedio general", stats.summary.ticketPromedioGeneral],
          ["Pedidos mes", stats.summary.pedidosMes],
          ["Ingresos mes", stats.summary.ingresosMes],
          ["Ticket mes", stats.summary.ticketMes],
          ["Cancelados mes", stats.summary.canceladosMes],
          ["Mes actual", stats.summary.mesActual]
        ]
      },
      {
        title: "Estados pedidos",
        rows: [
          ["Estado", "Cantidad"],
          ...stats.states.rows.map((row) => [row.estado, row.cantidad])
        ]
      },
      {
        title: "Ventas mensuales",
        rows: [
          ["Mes", "Ingresos", "Pedidos"],
          ...stats.monthly.labels.map((label, index) => [
            label,
            stats.monthly.revenue[index] || 0,
            stats.monthly.orders[index] || 0
          ])
        ]
      },
      {
        title: "Pedidos recientes",
        rows: [
          ["ID", "Cliente", "Subtotal", "Envio", "Total", "Fecha", "Estado"],
          ...stats.rows.map((row) => [
            row.id,
            row.cliente,
            row.subtotal,
            row.envio,
            row.total,
            row.fecha,
            row.estado
          ])
        ]
      }
    ];

    triggerCsvDownload("pedidos-tauro.csv", buildCsv(sections));
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

  document.getElementById("btnDescargarPedidosCsv")?.addEventListener("click", downloadOrdersCsv);
  document.getElementById("btnDescargarPedidosCsvModal")?.addEventListener("click", downloadOrdersCsv);

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
