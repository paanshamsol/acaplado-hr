import { ArcElement, Chart, DoughnutController, Legend, Tooltip } from 'chart.js';

Chart.register(DoughnutController, ArcElement, Tooltip, Legend);

/**
 * Alpine component rendering today's attendance breakdown (on time / late /
 * absent) as a doughnut chart.
 */
export function attendanceChart({ onTime = 0, late = 0, absent = 0, noRecord = 0, names = {} } = {}) {
    const namesByIndex = [names.onTime ?? [], names.late ?? [], names.absent ?? [], names.noRecord ?? []];
    let chart = null;

    return {
        init() {
            chart = new Chart(this.$refs.canvas, {
                type: 'doughnut',
                data: {
                    labels: ['On time', 'Late', 'Absent', 'No record'],
                    datasets: [
                        {
                            data: [onTime, late, absent, noRecord],
                            backgroundColor: ['#22c55e', '#ef4444', '#f59e0b', '#9ca3af'],
                            borderWidth: 0,
                            hoverOffset: 4,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '62%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { usePointStyle: true, boxWidth: 8 },
                        },
                        tooltip: {
                            callbacks: {
                                label: (context) => `${context.label}: ${context.parsed}`,
                                afterLabel: (context) => {
                                    const list = namesByIndex[context.dataIndex] ?? [];

                                    return list.length ? list : '';
                                },
                            },
                        },
                    },
                },
            });
        },

        destroy() {
            chart?.destroy();
            chart = null;
        },
    };
}

/**
 * Alpine component rendering the leave-request status breakdown (pending /
 * approved / rejected) as a doughnut chart.
 *
 * The chart reads the shared `leaveCounts` store, so it redraws as soon as a
 * request is approved or rejected on the dashboard.
 */
export function leaveRequestChart({ pending = 0, approved = 0, rejected = 0 } = {}) {
    let chart = null;

    return {
        init() {
            chart = new Chart(this.$refs.canvas, {
                type: 'doughnut',
                data: {
                    labels: ['Pending', 'Approved', 'Rejected'],
                    datasets: [
                        {
                            data: [pending, approved, rejected],
                            backgroundColor: ['#f59e0b', '#22c55e', '#ef4444'],
                            borderWidth: 0,
                            hoverOffset: 4,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '62%',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: (context) => `${context.label}: ${context.parsed}`,
                            },
                        },
                    },
                },
                plugins: [
                    {
                        id: 'leaveTotal',
                        afterDraw: (chart) => {
                            const { ctx, chartArea } = chart;
                            if (! chartArea) {
                                return;
                            }

                            const total = chart.data.datasets[0].data.reduce((sum, value) => sum + value, 0);
                            const x = (chartArea.left + chartArea.right) / 2;
                            const y = (chartArea.top + chartArea.bottom) / 2;

                            ctx.save();
                            ctx.textAlign = 'center';
                            ctx.textBaseline = 'middle';
                            ctx.fillStyle = '#111827';
                            ctx.font = '600 1.5rem Figtree, sans-serif';
                            ctx.fillText(String(total), x, y - 8);
                            ctx.fillStyle = '#6b7280';
                            ctx.font = '400 0.75rem Figtree, sans-serif';
                            ctx.fillText('Requests', x, y + 14);
                            ctx.restore();
                        },
                    },
                ],
            });

            const counts = window.Alpine?.store?.('leaveCounts');

            if (counts) {
                window.Alpine.effect(() => {
                    const nextData = [counts.pending, counts.approved, counts.rejected];

                    if (! chart) {
                        return;
                    }

                    chart.data.datasets[0].data = nextData;
                    chart.update();
                });
            }
        },

        destroy() {
            chart?.destroy();
            chart = null;
        },
    };
}
