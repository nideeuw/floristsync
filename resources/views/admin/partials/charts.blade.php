@php($days = $days ?? 7)

<div class="grid gap-6 lg:grid-cols-3">
    <div class="card p-5 lg:col-span-2">
        <h2 class="mb-3 font-semibold">Revenue (last {{ $days }} days)</h2>
        <div class="h-72"><canvas id="revenueChart"></canvas></div>
    </div>

    <div class="card p-5">
        <h2 class="mb-3 font-semibold">Top Categories (all time)</h2>
        <div class="h-72"><canvas id="categoryChart"></canvas></div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', async () => {
        const headers = {
            Accept: 'application/json'
        };
        const rupiah = (v) => 'Rp ' + new Intl.NumberFormat('id-ID').format(v);

        const revenue = await (await fetch('/api/dashboard/revenue?days={{ $days }}', {
            headers
        })).json();

        new Chart(document.getElementById('revenueChart'), {
            type: 'bar',
            data: {
                labels: revenue.labels,
                datasets: revenue.datasets.map((set, i) => ({
                    ...set,
                    backgroundColor: i === 0 ? '#7E8C69' : '#F6B0BB',
                    borderRadius: 6,
                })),
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                },
                scales: {
                    y: {
                        ticks: {
                            callback: rupiah
                        }
                    }
                },
            },
        });

        const categories = await (await fetch('/api/dashboard/top-categories', {
            headers
        })).json();

        new Chart(document.getElementById('categoryChart'), {
            type: 'doughnut',
            data: {
                labels: categories.labels,
                datasets: [{
                    data: categories.data,
                    backgroundColor: ['#7E8C69', '#F6B0BB', '#9CAD8C', '#F1C8CB', '#374030'],
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                },
            },
        });
    });
</script>
