/* Klienta puses skripts. Pārlūka pārbaude ir tikai ērtībai – obligātā validācija notiek serverī (PHP). */
(function () {
    'use strict';

    // 1) Poga, kas apmaina "no" un "uz" valūtas
    var swap = document.getElementById('swap');
    if (swap) {
        swap.addEventListener('click', function () {
            var from = document.getElementById('from');
            var to = document.getElementById('to');
            var tmp = from.value;
            from.value = to.value;
            to.value = tmp;
        });
    }

    // 2) Summas pārbaude rakstīšanas laikā
    var amount = document.getElementById('amount');
    if (amount) {
        amount.addEventListener('input', function () {
            var text = amount.value.trim();
            var ok = /^\d{1,12}([.,]\d{1,2})?$/.test(text) && parseFloat(text.replace(',', '.')) > 0;
            amount.setCustomValidity(ok ? '' : 'Ievadiet summu virs 0, ne vairāk kā ar 2 zīmēm aiz komata.');
        });
    }

    // 3) Kursa diagramma (Chart.js, ielādēta lokāli no assets/vendor)
    var canvas = document.getElementById('rateChart');
    if (canvas && window.Chart) {
        var labels = JSON.parse(canvas.dataset.labels);
        var values = JSON.parse(canvas.dataset.values);
        new window.Chart(canvas, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: canvas.dataset.title,
                    data: values,
                    borderColor: '#0a6c74',
                    backgroundColor: 'rgba(10, 108, 116, 0.12)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.2,
                    pointRadius: values.length > 60 ? 0 : 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { display: false } },
                scales: { x: { ticks: { maxTicksLimit: 8 } } }
            }
        });
    }
})();
