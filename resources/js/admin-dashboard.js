// Charts for the admin operations dashboard. Loaded ONLY on that page via its
// own Vite entry so the customer bundle stays lean. Do NOT import Alpine here:
// app.js already starts it. Data arrives as inert JSON script tags (CSP-safe).
import {
    BarController,
    BarElement,
    CategoryScale,
    Chart,
    Legend,
    LinearScale,
    Tooltip,
} from 'chart.js';

Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip, Legend);

const locale = document.documentElement.lang || 'de';
const eur = new Intl.NumberFormat(locale, { style: 'currency', currency: 'EUR' });
const eurCompact = new Intl.NumberFormat(locale, {
    style: 'currency',
    currency: 'EUR',
    maximumFractionDigits: 0,
});

function readJson(id) {
    const el = document.getElementById(id);
    if (!el) return null;
    try {
        return JSON.parse(el.textContent);
    } catch {
        return null;
    }
}

// ── Weekly revenue: grouped bars per ISO week, future weeks faded ─────────
const weekly = readJson('admin-ops-weekly-data');
const weeklyCanvas = document.getElementById('admin-ops-weekly-chart');
if (weekly && weeklyCanvas && weekly.buckets.some((b) => b.b2cNet > 0 || b.b2bNet > 0)) {
    const fade = (hex, bucket) => (bucket.isFuture ? hex + '59' : hex);
    new Chart(weeklyCanvas, {
        type: 'bar',
        data: {
            labels: weekly.buckets.map((b) => b.isoLabel),
            datasets: [
                {
                    label: weekly.labels.b2c,
                    data: weekly.buckets.map((b) => b.b2cNet),
                    backgroundColor: weekly.buckets.map((b) => fade('#059669', b)),
                    borderRadius: 3,
                },
                {
                    label: weekly.labels.b2b,
                    data: weekly.buckets.map((b) => b.b2bNet),
                    backgroundColor: weekly.buckets.map((b) => fade('#d97706', b)),
                    borderRadius: 3,
                },
            ],
        },
        options: {
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12 } },
                tooltip: {
                    callbacks: {
                        label: (c) => `${c.dataset.label}: ${eur.format(c.parsed.y)}`,
                    },
                },
            },
            scales: {
                x: { grid: { display: false } },
                y: {
                    beginAtZero: true,
                    ticks: { callback: (v) => eurCompact.format(v) },
                },
            },
        },
    });
}

// ── Top products: horizontal bars ──────────────────────────────────────────
const products = readJson('admin-ops-products-data');
const productsCanvas = document.getElementById('admin-ops-products-chart');
if (products && productsCanvas && products.rows.length > 0) {
    const pct = new Intl.NumberFormat(locale, { style: 'percent', maximumFractionDigits: 1 });
    new Chart(productsCanvas, {
        type: 'bar',
        data: {
            labels: products.rows.map((r) => (r.producer ? `${r.name} (${r.producer})` : r.name)),
            datasets: [
                {
                    label: products.label,
                    data: products.rows.map((r) => r.netRevenue),
                    backgroundColor: '#059669',
                    borderRadius: 3,
                },
            ],
        },
        options: {
            indexAxis: 'y',
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (c) => {
                            const r = products.rows[c.dataIndex];
                            return `${eur.format(r.netRevenue)} · ${r.qtyDisplay} ${r.unitSymbol} · ${pct.format(r.share)}`;
                        },
                    },
                },
            },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: { callback: (v) => eurCompact.format(v) },
                },
                y: { grid: { display: false } },
            },
        },
    });
}
