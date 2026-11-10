document.addEventListener('DOMContentLoaded', () => {
    const canvas = document.getElementById('chart-failed');
    if (!canvas || typeof Chart === 'undefined') return;
  
    new Chart(canvas, {
      type: 'bar',
      data: {
        labels: JSON.parse(canvas.dataset.labels),
        datasets: [{
          label: 'Connexions échouées',
          data: JSON.parse(canvas.dataset.values),
          backgroundColor: '#3b82f6'
        }]
      },
      options: {
        plugins: { legend: { display: false } },
        scales: {
          y: { beginAtZero: true, ticks: { precision: 0, color: '#94a3b8' } },
          x: { ticks: { color: '#94a3b8' } }
        }
      }
    });
  });