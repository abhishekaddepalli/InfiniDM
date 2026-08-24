// IgDesk dashboard — 14-day DM volume. Data is rendered into the element by
// the server (already grouped in SQL), so there is no fetch and no spinner.

import ApexCharts from 'apexcharts';

export default function init() {
    const el = document.getElementById('ig-dm-chart');
    if (!el) return;

    const read = (k, fallback) => { try { return JSON.parse(el.dataset[k]); } catch (_) { return fallback; } };
    const labels = read('labels', []);
    const inbound = read('in', []);
    const outbound = read('out', []);
    if (!labels.length) return;

    // Instagram's own gradient ends: pink = what people send us, purple = what we
    // sent back automatically. Same mapping as the legend in the blade.
    const dark = document.documentElement.dataset.theme === 'dark'
        || window.matchMedia?.('(prefers-color-scheme: dark)').matches;
    const grid = dark ? 'rgba(255,255,255,.08)' : 'rgba(0,0,0,.06)';
    const ink  = dark ? 'rgba(255,255,255,.55)' : 'rgba(0,0,0,.45)';

    new ApexCharts(el, {
        chart: {
            type: 'area', height: 210, toolbar: { show: false }, zoom: { enabled: false },
            fontFamily: 'inherit', background: 'transparent', animations: { easing: 'easeout', speed: 400 },
            parentHeightOffset: 0,
        },
        series: [
            { name: 'Received',  data: inbound },
            { name: 'Auto-sent', data: outbound },
        ],
        colors: ['#E1306C', '#833AB4'],
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: 2 },
        fill: {
            type: 'gradient',
            gradient: { shadeIntensity: 1, opacityFrom: 0.28, opacityTo: 0.02, stops: [0, 95] },
        },
        grid: { borderColor: grid, strokeDashArray: 3, padding: { left: 4, right: 4, top: 0 } },
        xaxis: {
            categories: labels,
            axisBorder: { show: false }, axisTicks: { show: false },
            // 14 labels collide at this width — show every other one.
            labels: { style: { colors: ink, fontSize: '10px' }, rotate: 0, hideOverlappingLabels: true },
            tooltip: { enabled: false },
        },
        yaxis: {
            labels: { style: { colors: ink, fontSize: '10px' }, formatter: (v) => Math.round(v) },
            min: 0,
            forceNiceScale: true,
        },
        legend: { show: false },     // the blade renders its own, with totals
        tooltip: { theme: dark ? 'dark' : 'light', x: { show: true } },
    }).render();
}
