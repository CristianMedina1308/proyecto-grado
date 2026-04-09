document.addEventListener("DOMContentLoaded", () => {
  const stats = window.ADMIN_PRODUCT_EDIT_DATA || null;
  const modalElement = document.getElementById("productoAnalyticsModal");

  if (!stats) {
    return;
  }

  const charts = {};
  let chartsReady = false;
  const palette = ["#b89247", "#8a6521", "#d7b56d", "#4d3620", "#caa05a", "#ead9b0", "#75614a"];

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
    if (chartsReady || !stats.rows.length) {
      chartsReady = true;
      return;
    }

    createChart("chartProductoTallas", {
      type: "bar",
      data: {
        labels: stats.sizes.labels,
        datasets: [{
          label: "Stock",
          data: stats.sizes.values,
          backgroundColor: palette,
          borderRadius: 12,
          maxBarThickness: 56
        }]
      },
      options: {
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: baseScales()
      }
    });

    createChart("chartProductoEstados", {
      type: "doughnut",
      data: {
        labels: stats.status.labels,
        datasets: [{
          data: stats.status.values,
          backgroundColor: ["#b23a48", "#b7791f", "#2d6a4f"],
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

    createChart("chartProductoParticipacion", {
      type: "polarArea",
      data: {
        labels: stats.sizes.labels,
        datasets: [{
          data: stats.sizes.values,
          backgroundColor: palette
        }]
      },
      options: {
        maintainAspectRatio: false,
        scales: {
          r: {
            grid: { color: "rgba(128,102,53,0.12)" },
            ticks: { color: "#4c4135", backdropColor: "transparent" }
          }
        },
        plugins: {
          legend: {
            position: "bottom",
            labels: {
              color: "#4c4135",
              usePointStyle: true,
              padding: 16
            }
          }
        }
      }
    });

    chartsReady = true;
  }

  function getSizeState(stock) {
    if (Number(stock) <= 0) {
      return "Agotada";
    }
    if (Number(stock) <= 2) {
      return "Critica";
    }
    return "Estable";
  }

  function downloadProductCsv() {
    const sections = [
      {
        title: "Resumen producto",
        rows: [
          ["Campo", "Valor"],
          ["ID", stats.product.id],
          ["Producto", stats.product.nombre],
          ["SKU", stats.product.sku],
          ["Categoria", stats.product.categoria],
          ["Precio", stats.product.precio],
          ["Stock total", stats.product.totalStock],
          ["Valor stock", stats.product.value],
          ["Tallas activas", stats.product.sizesCount],
          ["Talla top", stats.product.topSize]
        ]
      },
      {
        title: "Detalle tallas",
        rows: [
          ["Talla", "Stock", "Estado"],
          ...stats.rows.map((row) => [row.talla, row.stock, getSizeState(row.stock)])
        ]
      }
    ];

    triggerCsvDownload(`producto-${stats.product.id}-inventario.csv`, buildCsv(sections));
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

  document.getElementById("btnDescargarProductoCsv")?.addEventListener("click", downloadProductCsv);
  document.getElementById("btnDescargarProductoCsvModal")?.addEventListener("click", downloadProductCsv);

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
