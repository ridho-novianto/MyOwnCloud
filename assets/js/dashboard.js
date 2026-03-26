/**
 * Dashboard Charts (Chart.js)
 */
document.addEventListener('DOMContentLoaded', () => {
    if (!window.chartData) return;

    // Task Status Donut Chart
    const statusCtx = document.getElementById('taskStatusChart');
    if (statusCtx) {
        const statusColors = {
            'To Do': '#64b5f6',
            'In Progress': '#ffd54f',
            'Done': '#81c784',
            'Cancelled': '#90a4ae',
            'Terlambat': '#ef5350'
        };
        const statusLabels = Object.keys(statusColors);
        const statusValues = [
            window.chartData.status.todo,
            window.chartData.status.in_progress,
            window.chartData.status.done,
            window.chartData.status.cancelled,
            window.chartData.status.overdue
        ];

        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: statusLabels,
                datasets: [{
                    data: statusValues,
                    backgroundColor: Object.values(statusColors),
                    borderColor: '#0a0e1a',
                    borderWidth: 3,
                    hoverBorderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: '#90a4ae',
                            font: { family: 'Inter', size: 12 },
                            padding: 15,
                            usePointStyle: true,
                            pointStyleWidth: 10
                        }
                    }
                }
            }
        });
    }

    // Activity Bar Chart
    const actCtx = document.getElementById('activityChart');
    if (actCtx) {
        new Chart(actCtx, {
            type: 'bar',
            data: {
                labels: window.chartData.activity.labels,
                datasets: [{
                    label: 'Aktivitas',
                    data: window.chartData.activity.data,
                    backgroundColor: 'rgba(0, 229, 255, 0.3)',
                    borderColor: '#00e5ff',
                    borderWidth: 2,
                    borderRadius: 6,
                    borderSkipped: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { 
                            color: '#90a4ae', 
                            font: { family: 'Inter' },
                            stepSize: 1
                        },
                        grid: { color: 'rgba(255,255,255,0.05)' }
                    },
                    x: {
                        ticks: { 
                            color: '#90a4ae', 
                            font: { family: 'Inter' } 
                        },
                        grid: { display: false }
                    }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }
});
