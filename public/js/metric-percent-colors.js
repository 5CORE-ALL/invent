/**
 * Shared % color schema for NROI / GROI / NPFT / GPFT (and S* variants).
 * All thresholds are in percent points.
 *
 * NROI%:  <40 red · <70 yellow · <125 green · >=125 pink
 * GROI%:  <60 red · <90 yellow · <150 green · >=150 pink
 * NPFT%:  <=10 red · <20 yellow · <33 green · >=33 pink
 * GPFT%:  <=20 red · <30 yellow · <43 green · >=43 pink-dil (#4e0dab)
 */
(function (global) {
    'use strict';

    var COLORS = {
        red: '#dc3545',
        yellow: '#ffc107',
        green: '#28a745',
        pink: '#e83e8c',
        pinkDil: '#4e0dab',
    };

    function toNum(v) {
        if (v === null || v === undefined || v === '') return NaN;
        if (typeof v === 'number') return v;
        var n = parseFloat(String(v).replace('%', '').replace(/,/g, '').trim());
        return n;
    }

    /** @returns {'red'|'yellow'|'green'|'pink'|'pink-dil'|null} */
    function nroiBand(v) {
        var n = toNum(v);
        if (!isFinite(n)) return null;
        if (n < 40) return 'red';
        if (n < 70) return 'yellow';
        if (n < 125) return 'green';
        return 'pink';
    }

    function groiBand(v) {
        var n = toNum(v);
        if (!isFinite(n)) return null;
        if (n < 60) return 'red';
        if (n < 90) return 'yellow';
        if (n < 150) return 'green';
        return 'pink';
    }

    function npftBand(v) {
        var n = toNum(v);
        if (!isFinite(n)) return null;
        if (n <= 10) return 'red';
        if (n < 20) return 'yellow';
        if (n < 33) return 'green';
        return 'pink';
    }

    function gpftBand(v) {
        var n = toNum(v);
        if (!isFinite(n)) return null;
        if (n <= 20) return 'red';
        if (n < 30) return 'yellow';
        if (n < 43) return 'green';
        return 'pink-dil';
    }

    function bandColor(band) {
        if (band === 'red') return COLORS.red;
        if (band === 'yellow') return COLORS.yellow;
        if (band === 'green') return COLORS.green;
        if (band === 'pink') return COLORS.pink;
        if (band === 'pink-dil') return COLORS.pinkDil;
        return '';
    }

    function nroiColor(v) { return bandColor(nroiBand(v)); }
    function groiColor(v) { return bandColor(groiBand(v)); }
    function npftColor(v) { return bandColor(npftBand(v)); }
    function gpftColor(v) { return bandColor(gpftBand(v)); }

    /**
     * CSS style string for a band (yellow gets black text via callers that use styleForCellColor).
     * pink-dil uses purple text (project convention).
     */
    function styleFromBand(band) {
        var c = bandColor(band);
        if (!c) return '';
        if (band === 'pink-dil') return 'color:#4e0dab;font-weight:700;';
        if (band === 'yellow') return 'color:#000000;font-weight:700;';
        return 'color:' + c + ';font-weight:700;';
    }

    function nroiStyle(v) { return styleFromBand(nroiBand(v)); }
    function groiStyle(v) { return styleFromBand(groiBand(v)); }
    function npftStyle(v) { return styleFromBand(npftBand(v)); }
    function gpftStyle(v) { return styleFromBand(gpftBand(v)); }

    /** Map a column/field name to metric kind. */
    function kindFromField(field) {
        var f = String(field || '').toLowerCase().replace(/[%\s_\-]/g, '');
        if (!f) return null;
        // Gross ROI first (sgroi / groi) before generic roi
        if (f === 'sgroi' || f === 'groi' || f.indexOf('sgroi') !== -1 || f === 'avggroi') return 'groi';
        // Net ROI (nroi / snroi). Exact "sroi" is ambiguous (PEF uses it for SGROI) — treat as GROI via generic roi below unless clearly net.
        if (f === 'nroi' || f === 'snroi' || f === 'avgnroi' || f.indexOf('nroi') !== -1 || f.indexOf('snroi') !== -1) return 'nroi';
        // Gross PFT
        if (f === 'gpft' || f === 'sgpft' || f === 'grpft' || f === 'avggpft' || f.indexOf('gpft') !== -1 || f.indexOf('sgpft') !== -1) return 'gpft';
        // Net PFT
        if (f === 'npft' || f === 'snpft' || f === 'spft' || f === 'pft' || f === 'tpft' || f === 'tprft' || f === 'avgnpft' || f.indexOf('npft') !== -1 || f.indexOf('snpft') !== -1) return 'npft';
        // Generic ROI → GROI; generic PFT → NPFT
        if (f === 'roi' || f.indexOf('roi') !== -1) return 'groi';
        if (f.indexOf('pft') !== -1) return 'npft';
        return null;
    }

    function bandFor(kind, v) {
        if (kind === 'nroi') return nroiBand(v);
        if (kind === 'groi') return groiBand(v);
        if (kind === 'npft') return npftBand(v);
        if (kind === 'gpft') return gpftBand(v);
        return null;
    }

    function colorFor(kind, v) {
        return bandColor(bandFor(kind, v));
    }

    function styleFor(kind, v) {
        return styleFromBand(bandFor(kind, v));
    }

    function colorForField(field, v) {
        var kind = kindFromField(field);
        return kind ? colorFor(kind, v) : '';
    }

    function styleForField(field, v) {
        var kind = kindFromField(field);
        return kind ? styleFor(kind, v) : '';
    }

    function htmlFor(kind, v, opts) {
        opts = opts || {};
        var n = toNum(v);
        if (!isFinite(n)) return opts.empty != null ? opts.empty : '';
        var decimals = opts.decimals;
        var text = (decimals == null)
            ? (Math.round(n) + '%')
            : (n.toFixed(decimals) + '%');
        var st = styleFor(kind, n);
        if (!st) return text;
        return '<span style="' + st + '">' + text + '</span>';
    }

    function htmlForField(field, v, opts) {
        var kind = kindFromField(field);
        if (!kind) {
            var n = toNum(v);
            if (!isFinite(n)) return (opts && opts.empty) || '';
            return Math.round(n) + '%';
        }
        return htmlFor(kind, v, opts);
    }

    /**
     * Class-name helpers for pages using .dil-percent-value.red|yellow|green|pink
     * pink-dil maps to 'pink' class (no separate dil-percent class historically).
     */
    function classBand(band) {
        if (band === 'pink-dil') return 'pink';
        return band || '';
    }

    /** Legacy getRoiColor replacement → GROI bands as class names (fractions OK). */
    function legacyRoiClass(value) {
        var n = toNum(value);
        if (!isFinite(n)) return 'red';
        if (Math.abs(n) <= 1.5) n = n * 100;
        return classBand(groiBand(n)) || 'red';
    }

    /**
     * Legacy getPftColor replacement.
     * Old helpers often pass a fraction (0.25); if |value| <= 1.5 treat as fraction.
     * Uses GPFT bands (gross PFT columns are the common case on older analysis pages).
     */
    function legacyPftClass(value) {
        var n = toNum(value);
        if (!isFinite(n)) return 'red';
        if (Math.abs(n) <= 1.5) n = n * 100;
        return classBand(gpftBand(n)) || 'red';
    }

    global.MetricPctColors = {
        COLORS: COLORS,
        toNum: toNum,
        nroiBand: nroiBand,
        groiBand: groiBand,
        npftBand: npftBand,
        gpftBand: gpftBand,
        bandFor: bandFor,
        nroiColor: nroiColor,
        groiColor: groiColor,
        npftColor: npftColor,
        gpftColor: gpftColor,
        nroiStyle: nroiStyle,
        groiStyle: groiStyle,
        npftStyle: npftStyle,
        gpftStyle: gpftStyle,
        kindFromField: kindFromField,
        colorFor: colorFor,
        styleFor: styleFor,
        colorForField: colorForField,
        styleForField: styleForField,
        htmlFor: htmlFor,
        htmlForField: htmlForField,
        legacyRoiClass: legacyRoiClass,
        legacyPftClass: legacyPftClass,
    };
})(typeof window !== 'undefined' ? window : globalThis);
