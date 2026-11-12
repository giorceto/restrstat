// Графиките: тази година (синьо) и миналата (сиво), една ос; данните са и в таблиците под тях.
(function () {
  if (!window.Chart) return;
  const css = (n) => getComputedStyle(document.documentElement).getPropertyValue(n).trim();
  const eur = (v) => new Intl.NumberFormat('bg-BG', { maximumFractionDigits: 0 }).format(v) + ' €';
  const num = (v) => new Intl.NumberFormat('bg-BG', { maximumFractionDigits: 0 }).format(v);
  document.querySelectorAll('canvas[data-chart]').forEach((el) => {
    const d = JSON.parse(el.dataset.chart);
    const money = d.unit === 'eur';
    const series = d.series.map((s, i) => ({
      label: s.label, data: s.data,
      backgroundColor: i === 0 ? css('--accent') : css('--ly'),
      borderColor: i === 0 ? css('--accent') : css('--ly'),
      borderWidth: d.type === 'line' ? 2 : 0, borderRadius: { topLeft: 4, topRight: 4 }, borderSkipped: 'start',
      pointRadius: 0, pointHoverRadius: 4, tension: 0.25, fill: false,
      categoryPercentage: 0.75, barPercentage: 0.9,
    }));
    new Chart(el, {
      type: d.type || 'bar',
      data: { labels: d.labels, datasets: series },
      options: {
        maintainAspectRatio: false, animation: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { display: series.length > 1, position: 'top', align: 'start', labels: { color: css('--text-2'), boxWidth: 12, boxHeight: 12 } },
          tooltip: { callbacks: { label: (c) => c.dataset.label + ': ' + (money ? eur(c.parsed.y) : num(c.parsed.y)) } },
        },
        scales: {
          x: { grid: { display: false }, ticks: { color: css('--text-2'), maxRotation: 0, autoSkip: true } },
          y: { beginAtZero: true, grid: { color: css('--grid') }, border: { display: false },
               ticks: { color: css('--muted'), callback: (v) => (money ? eur(v) : num(v)) } },
        },
      },
    });
  });
})();
