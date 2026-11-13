// Standalone regression checks: node tests/HistoryChartTemplateTest.cjs
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const luxon = require('luxon');

const template = fs.readFileSync(path.join(__dirname,
    '../contao/templates/frontend/ce_coh_history_chart_display.html5'), 'utf8');
const legendCode = template.slice(template.indexOf('  const differenceFormat'),
    template.indexOf('  // Nur die Darstellung beim Zeichnen'));

function legend(values, unit = 'month', mode = 'sum', aggregation = 'counter') {
    const context = {
        Intl,
        Chart: { defaults: { plugins: { legend: { labels: {
            generateLabels: () => [{ datasetIndex: 0, text: 'Brenner' }],
        } } } } },
        chartData: {
            rangeMin: '2026-01-01T00:00:00+01:00',
            rangeMax: '2027-01-01T00:00:00+01:00',
            xUnit: unit,
            axes: { y: { unit: 'h' } },
        },
    };
    vm.createContext(context);
    vm.runInContext(legendCode, context);
    return context.generateIntervalLegendLabels({ data: { datasets: [{
        legendMode: mode, historyAggregation: aggregation, yAxisID: 'y',
        data: values.map((y, i) => ({ x: Date.parse('2026-10-01') + i * 3600000, y })),
    }] } })[0].text;
}

assert.equal(legend([null, 1, 2.5]), 'Brenner · Erfasste Summe: 3,5 h (unvollständig)');
assert.equal(legend([null, null, 53.6, 44], 'year'), 'Brenner · Erfasste Summe: 97,6 h (unvollständig)');
assert.equal(legend([null, null]), 'Brenner · Summe: –');
assert.equal(legend([]), 'Brenner · Summe: –');
assert.equal(legend([null, 0]), 'Brenner · Erfasste Summe: 0 h (unvollständig)');
assert.equal(legend([1, 2.5]), 'Brenner · Summe: 3,5 h');
assert.equal(legend([0, 1, 2, 3], 'day'), 'Brenner · Summe: 3 h');
assert.equal(legend([null, 0, 2, 3], 'day'), 'Brenner · Erfasste Summe: 3 h (unvollständig)');
assert.equal(legend([null, 2, 3], 'week', 'range'), 'Brenner · Δ 1 h');
assert.equal(legend([null, 2, 3], 'week', 'minmax'), 'Brenner · Min: 2 h / Max: 3 h');
assert.equal(legend([null, 2, 3], 'week', 'none'), 'Brenner');

// On DST change days the offset at midnight differs from the offset at noon.
// All sensors must still share one bar position for that calendar day.
const barCode = template.slice(template.indexOf('  const chartType'),
    template.indexOf('  const scales ='));
luxon.Settings.defaultZone = 'UTC'; // The browser timezone may differ from Contao.
try {
    for (const [midnight, noon, expected] of [
        ['2026-10-25T00:00:00+02:00', '2026-10-25T12:00:00+01:00', '2026-10-25T10:30:00.000Z'],
        ['2026-03-29T00:00:00+01:00', '2026-03-29T12:00:00+02:00', '2026-03-29T10:30:00.000Z'],
    ]) {
        const chartData = {
            xUnit: 'month', timezone: 'Europe/Berlin',
            datasets: [midnight, noon, Date.parse(noon)].map(x => ({ data: [{ x, y: 1 }] })),
        };
        vm.runInNewContext(barCode, { chartData, luxon });
        const centers = chartData.datasets.map(d => d.data[0].x);
        assert.equal(centers[0], centers[1]);
        assert.equal(centers[0], centers[2]);
        assert.equal(new Date(centers[0]).toISOString(), expected);
    }
} finally {
    luxon.Settings.defaultZone = 'system';
}

console.log('History chart legend and DST regression checks passed.');
