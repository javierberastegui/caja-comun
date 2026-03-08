(function () {
  function revealCards() {
    var cards = document.querySelectorAll('.ecopro-reveal');
    if (!('IntersectionObserver' in window)) {
      cards.forEach(function(card){ card.classList.add('is-visible'); });
      return;
    }
    var observer = new IntersectionObserver(function(entries){
      entries.forEach(function(entry){
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12 });
    cards.forEach(function(card){ observer.observe(card); });
  }

  function loadChartJs(callback) {
    if (window.Chart) {
      callback();
      return;
    }
    var script = document.createElement('script');
    script.src = 'https://cdn.jsdelivr.net/npm/chart.js';
    script.onload = callback;
    document.head.appendChild(script);
  }

  function initCharts() {
    var lineCharts = document.querySelectorAll('canvas.ecopro-chart');
    var donutCharts = document.querySelectorAll('canvas.ecopro-chart-donut');
    if (!lineCharts.length && !donutCharts.length) return;
    loadChartJs(function(){
      lineCharts.forEach(function(canvas){
        var labels = [];
        var income = [];
        var expense = [];
        try {
          labels = JSON.parse(canvas.dataset.labels || '[]');
          income = JSON.parse(canvas.dataset.income || '[]');
          expense = JSON.parse(canvas.dataset.expense || '[]');
        } catch (e) {
          return;
        }
        new Chart(canvas, {
          type: 'line',
          data: {
            labels: labels,
            datasets: [
              { label: 'Ingresos', data: income, borderColor: '#86f0b0', backgroundColor: 'rgba(134,240,176,0.12)', tension: 0.35, fill: true },
              { label: 'Gastos', data: expense, borderColor: '#8eb8ff', backgroundColor: 'rgba(142,184,255,0.10)', tension: 0.35, fill: true }
            ]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { labels: { color: '#eef4ff' } } },
            scales: {
              x: { ticks: { color: '#bfd2f5' }, grid: { color: 'rgba(255,255,255,0.08)' } },
              y: { beginAtZero: true, ticks: { color: '#bfd2f5' }, grid: { color: 'rgba(255,255,255,0.08)' } }
            }
          }
        });
      });

      donutCharts.forEach(function(canvas){
        var labels = [];
        var values = [];
        try {
          labels = JSON.parse(canvas.dataset.labels || '[]');
          values = JSON.parse(canvas.dataset.values || '[]');
        } catch (e) {
          return;
        }
        new Chart(canvas, {
          type: 'doughnut',
          data: {
            labels: labels,
            datasets: [{
              data: values,
              backgroundColor: ['#4f8dff','#86f0b0','#ffb16b','#ff8c8c','#9f7aea','#56ccf2','#ffd166','#6ee7b7'],
              borderColor: 'rgba(255,255,255,0.08)',
              borderWidth: 1
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              legend: { position: 'bottom', labels: { color: '#eef4ff' } }
            }
          }
        });
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function(){
    revealCards();
    initCharts();
  });
})();