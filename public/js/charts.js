// Графиките: текущата година (синьо) и година по-рано (сиво), една ос; данните са и в таблиците под тях.
(function () {
  if (!window.Chart) return;
  const css = (n) => getComputedStyle(document.documentElement).getPropertyValue(n).trim();
  const fmt = new Intl.NumberFormat('bg-BG', { maximumFractionDigits: 0 });
  const short = (v) => (Math.abs(v) >= 1e6 ? fmt.format(v / 1e6) + ' млн' : Math.abs(v) >= 1e3 ? fmt.format(v / 1e3) + ' хил' : fmt.format(v));
  Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
  Chart.defaults.font.size = 12;
  document.querySelectorAll('canvas[data-chart]').forEach((el) => {
    const d = JSON.parse(el.dataset.chart);
    const money = d.unit === 'eur';
    const line = d.type === 'line';
    const ctx = el.getContext('2d');
    const series = d.series.map((s, i) => {
      const color = i === 0 ? css('--accent') : css('--ly');
      let bg = color;
      if (line && i === 0) {
        const g = ctx.createLinearGradient(0, 0, 0, el.parentNode.clientHeight || 300);
        g.addColorStop(0, color + '33'); g.addColorStop(1, color + '00'); bg = g;
      }
      return {
        label: s.label, data: s.data, backgroundColor: bg, borderColor: color, hoverBackgroundColor: color,
        borderWidth: line ? 2 : 0, borderRadius: 4, borderSkipped: 'start', borderDash: line && i > 0 ? [4, 4] : [],
        pointRadius: 0, pointHoverRadius: 4, pointHoverBackgroundColor: color, tension: 0.35, fill: line && i === 0,
        categoryPercentage: 0.7, barPercentage: 0.85, maxBarThickness: 18,
      };
    });
    new Chart(el, {
      type: line ? 'line' : 'bar',
      data: { labels: d.labels, datasets: series },
      options: {
        maintainAspectRatio: false, animation: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: css('--text'), titleColor: css('--surface'), bodyColor: css('--surface'),
            padding: 10, cornerRadius: 8, boxPadding: 4, usePointStyle: true,
            callbacks: { label: (c) => ' ' + c.dataset.label + ': ' + fmt.format(c.parsed.y) + (money ? ' €' : '') },
          },
        },
        scales: {
          x: { grid: { display: false }, border: { display: false }, ticks: { color: css('--muted'), maxRotation: 0, autoSkip: true, autoSkipPadding: 12 } },
          y: { beginAtZero: true, grid: { color: css('--grid'), drawTicks: false }, border: { display: false },
               ticks: { color: css('--muted'), padding: 8, maxTicksLimit: 6, callback: (v) => short(v) + (money ? ' €' : '') } },
        },
      },
    });
  });
})();
