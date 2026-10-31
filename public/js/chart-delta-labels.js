/*
 * Global history-graph delta labels.
 *
 * Adds a small "▲ +12" / "▼ −5" label on every segment of a single-series line
 * history graph, showing how much was added or deducted from the previous
 * point (count) to the next one.
 *
 * Works for every page that uses Chart.js (v3 / v4) or Highcharts line charts
 * without touching each blade: the plugin registers itself globally the moment
 * the library is loaded (including libraries that are lazy-loaded later).
 *
 * Opt-out per chart:
 *   Chart.js : options.plugins.historyDeltaLabels = false   (or dataset.historyDelta = false)
 *   Highcharts: chart.historyDelta = false
 */
(function (w) {
    'use strict';
    if (w.__historyDeltaLabelsLoaded) return;
    w.__historyDeltaLabelsLoaded = true;

    var MAX_POINTS = 400;
    var UP = '#16a34a';
    var DOWN = '#dc2626';
    var MINUS = '\u2212';

    function num(v) {
        if (v && typeof v === 'object' && 'y' in v) v = v.y;
        if (v === null || v === undefined || v === '') return null;
        var n = Number(v);
        return isFinite(n) ? n : null;
    }

    function plainFmt(abs) {
        var d = abs < 10 ? 2 : (abs < 100 ? 1 : 0);
        var n = Number(abs.toFixed(d));
        return n.toLocaleString('en-US', { maximumFractionDigits: d });
    }

    function isColor(c) {
        if (typeof c !== 'string') return false;
        var s = c.replace(/\s+/g, '').toLowerCase();
        if (!s || s === 'transparent' || s === '#fff' || s === '#ffffff' || s === 'white') return false;
        if (/^rgba?\(255,255,255/.test(s) || /^rgba\(.*,0\)$/.test(s)) return false;
        return true;
    }

    // Re-use the page's own good/bad dot colour (green / red, already inverted for
    // lower-is-better metrics); anything else falls back to green for added, red for deducted.
    function signColor(diff, base) {
        var m = typeof base === 'string' && /^#([0-9a-f]{6})$/i.exec(base.trim());
        if (m) {
            var r = parseInt(m[1].substr(0, 2), 16), g = parseInt(m[1].substr(2, 2), 16), b = parseInt(m[1].substr(4, 2), 16);
            if (g > r + 30 && g > b + 30) return UP;
            if (r > g + 50 && r > b + 50) return DOWN;
        }
        return diff > 0 ? UP : DOWN;
    }

    function label(diff, formatted) {
        return (diff > 0 ? '\u25B2 +' : '\u25BC ' + MINUS) + formatted;
    }

    /* ───────────────────────── Chart.js ───────────────────────── */

    function isConstant(arr) {
        var first = null, seen = false;
        for (var i = 0; i < arr.length; i++) {
            var n = num(arr[i]);
            if (n === null) continue;
            if (!seen) { first = n; seen = true; }
            else if (n !== first) return false;
        }
        return true;
    }

    function pickDataset(chart) {
        var cands = [];
        (chart.data.datasets || []).forEach(function (ds, i) {
            if (!ds || ds.historyDelta === false) return;
            var visible = typeof chart.isDatasetVisible === 'function' ? chart.isDatasetVisible(i) : true;
            if (!visible) return;
            var meta = chart.getDatasetMeta(i);
            if (!meta || (meta.type && meta.type !== 'line')) return;
            cands.push({ ds: ds, index: i, meta: meta });
        });
        if (!cands.length) return null;
        if (cands.length === 1) return cands[0];
        // Several datasets: only proceed when everything but one is a flat reference line.
        var moving = cands.filter(function (c) { return !isConstant(c.ds.data || []); });
        return moving.length === 1 ? moving[0] : null;
    }

    function chartJsFormatter(chart) {
        var yScale = chart.scales && (chart.scales.y || chart.scales['y-axis-0']);
        var cb = yScale && yScale.options && yScale.options.ticks && yScale.options.ticks.callback;
        return function (abs) {
            if (typeof cb === 'function') {
                try {
                    var r = cb.call(yScale, abs, 0, yScale.ticks || []);
                    if (r !== null && r !== undefined && r !== '') {
                        var digits = String(r).replace(/[^0-9]/g, '');
                        // A real diff that the axis formatter rounds to 0 falls back to plain formatting.
                        if (!(digits.length && /^0+$/.test(digits))) return String(r);
                    }
                } catch (e) { /* use plain format */ }
            }
            return plainFmt(abs);
        };
    }

    var chartJsPlugin = {
        id: 'historyDeltaLabels',
        afterDatasetsDraw: function (chart) {
            try {
                var type = (chart.config && chart.config.type) || (chart.config && chart.config._config && chart.config._config.type);
                if (type !== 'line') return;
                if (chart.options && chart.options.indexAxis === 'y') return;
                var pick = pickDataset(chart);
                if (!pick) return;
                var data = pick.ds.data || [];
                var points = pick.meta.data || [];
                if (data.length < 2 || data.length > MAX_POINTS) return;

                var ctx = chart.ctx;
                var area = chart.chartArea;
                if (!ctx || !area) return;
                var fmt = chartJsFormatter(chart);
                var cw = chart.width || (chart.canvas && chart.canvas.width) || 0;
                var colors = Array.isArray(pick.ds.pointBackgroundColor) ? pick.ds.pointBackgroundColor : null;
                var borders = Array.isArray(pick.ds.pointBorderColor) ? pick.ds.pointBorderColor : null;

                ctx.save();
                ctx.font = 'bold 9px Inter, system-ui, sans-serif';
                ctx.textBaseline = 'middle';
                ctx.textAlign = 'center';
                ctx.lineJoin = 'round';
                ctx.lineWidth = 3;
                ctx.strokeStyle = 'rgba(255,255,255,0.95)';

                for (var i = 1; i < data.length; i++) {
                    var a = num(data[i - 1]);
                    var b = num(data[i]);
                    var p0 = points[i - 1];
                    var p1 = points[i];
                    if (a === null || b === null || !p0 || !p1 || p0.skip || p1.skip) continue;
                    var diff = b - a;
                    if (Math.abs(diff) < 1e-9) continue;

                    var spacing = Math.abs(p1.x - p0.x);
                    if (spacing < 12) continue;

                    var txt = label(diff, fmt(Math.abs(diff)));
                    var tw = ctx.measureText(txt).width;
                    var mx = (p0.x + p1.x) / 2;
                    var my = (p0.y + p1.y) / 2;
                    var below = my + 14 < area.bottom;
                    var vertical = spacing < tw + 4;
                    var base = colors ? colors[i] : null;
                    if (!isColor(base) && borders) base = borders[i];
                    var color = signColor(diff, base);

                    var ty = below ? my + (vertical ? tw / 2 + 8 : 12) : my - (vertical ? tw / 2 + 8 : 12);
                    if (!vertical) {
                        mx = Math.max(tw / 2 + 2, Math.min((cw || mx + tw) - tw / 2 - 2, mx));
                    }
                    ctx.save();
                    ctx.translate(mx, ty);
                    if (vertical) ctx.rotate(-Math.PI / 2);
                    ctx.strokeText(txt, 0, 0);
                    ctx.fillStyle = color;
                    ctx.fillText(txt, 0, 0);
                    ctx.restore();
                }
                ctx.restore();
            } catch (e) { /* never break the host chart */ }
        }
    };

    var chartJsRegistered = false;
    function registerChartJs(C) {
        if (!C || chartJsRegistered) return;
        try {
            if (typeof C.register === 'function') {
                C.register(chartJsPlugin);
                chartJsRegistered = true;
            } else if (C.plugins && typeof C.plugins.register === 'function') {
                C.plugins.register(chartJsPlugin); // Chart.js v2
                chartJsRegistered = true;
            }
        } catch (e) { /* ignore */ }
    }

    /* ───────────────────────── Highcharts ───────────────────────── */

    var LINE_TYPES = { line: 1, spline: 1, area: 1, areaspline: 1 };

    function drawHighcharts(chart) {
        try {
            if (chart.__histDeltaGroup) {
                chart.__histDeltaGroup.destroy();
                chart.__histDeltaGroup = null;
            }
            if (chart.historyDelta === false || (chart.userOptions && chart.userOptions.historyDelta === false)) return;
            var series = (chart.series || []).filter(function (s) {
                return s.visible && !s.options.isInternal && LINE_TYPES[s.type] && s.points && s.points.length > 1
                    && !(s.options.id === 'highcharts-navigator-series');
            });
            if (series.length !== 1) return;
            var s = series[0];
            if (s.points.length > MAX_POINTS) return;
            var opts = s.tooltipOptions || {};
            var prefix = opts.valuePrefix || '';
            var suffix = opts.valueSuffix || '';

            var g = chart.renderer.g('history-delta').attr({ zIndex: 7 }).add();
            chart.__histDeltaGroup = g;

            for (var i = 1; i < s.points.length; i++) {
                var p0 = s.points[i - 1];
                var p1 = s.points[i];
                var a = num(p0.y);
                var b = num(p1.y);
                if (a === null || b === null) continue;
                if (typeof p0.plotX !== 'number' || typeof p1.plotX !== 'number' || typeof p0.plotY !== 'number' || typeof p1.plotY !== 'number') continue;
                var diff = b - a;
                if (Math.abs(diff) < 1e-9) continue;
                if (Math.abs(p1.plotX - p0.plotX) < 12) continue;

                var mx = chart.plotLeft + (p0.plotX + p1.plotX) / 2;
                var my = chart.plotTop + (p0.plotY + p1.plotY) / 2;
                var below = my + 14 < chart.plotTop + chart.plotHeight;
                var txt = label(diff, prefix + plainFmt(Math.abs(diff)) + suffix);
                chart.renderer.text(txt, mx, my + (below ? 14 : -8))
                    .attr({ align: 'center', zIndex: 7 })
                    .css({ fontSize: '9px', fontWeight: '700', color: signColor(diff, null), textOutline: '2px #ffffff', pointerEvents: 'none' })
                    .add(g);
            }
        } catch (e) { /* never break the host chart */ }
    }

    var highchartsHooked = false;
    function hookHighcharts(H) {
        if (!H || highchartsHooked || typeof H.addEvent !== 'function' || !H.Chart) return;
        highchartsHooked = true;
        try {
            H.addEvent(H.Chart, 'render', function () { drawHighcharts(this); });
        } catch (e) { highchartsHooked = false; }
    }

    /* ───────────────────────── bootstrap ───────────────────────── */

    // Run `cb` as soon as window[name] is assigned (works for libraries loaded later / lazily).
    function onGlobal(name, cb) {
        if (w[name]) { cb(w[name]); return; }
        try {
            Object.defineProperty(w, name, {
                configurable: true,
                enumerable: true,
                get: function () { return undefined; },
                set: function (v) {
                    Object.defineProperty(w, name, { value: v, writable: true, configurable: true, enumerable: true });
                    cb(v);
                }
            });
        } catch (e) { /* fall back to the load-time check below */ }
    }

    onGlobal('Chart', registerChartJs);
    onGlobal('Highcharts', hookHighcharts);

    // Safety net in case a library was attached in a way the setter could not see.
    function sweep() {
        if (w.Chart) registerChartJs(w.Chart);
        if (w.Highcharts) hookHighcharts(w.Highcharts);
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', sweep);
    else sweep();
    w.addEventListener('load', sweep);
})(window);
