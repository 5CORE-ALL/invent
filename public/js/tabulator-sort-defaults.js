/**
 * Shared Tabulator sorting rules for the marketplace analytics pages.
 * Load this right after tabulator.min.js, before any table is created.
 *
 *  1. The built-in "number" sorter keeps blank values (null, "", non-numeric) at the bottom
 *     for BOTH low to high and high to low. Tabulator's own default puts them on top in one direction.
 *     A column can still choose its own place with sorterParams: { alignEmptyValues: "top" }.
 *  2. window.tabSortBlankLast(valueFn[, zeroEmpty]) builds a sorter for columns that sort by a derived
 *     value (what the cell shows). valueFn(rowData) returns a number, or null / NaN when the cell is blank.
 *     Blank values stay at the bottom in both directions. With zeroEmpty, 0 and negatives count as blank.
 */
(function (global) {
    'use strict';

    function parseNum(v, params) {
        var s = String(v);
        if (params && params.thousandSeparator) s = s.split(params.thousandSeparator).join('');
        if (params && params.decimalSeparator) s = s.split(params.decimalSeparator).join('.');
        return parseFloat(s);
    }

    function numberSorter(a, b, aRow, bRow, column, dir, params) {
        params = params || {};
        var align = params.alignEmptyValues || 'bottom';
        var emptyAlign = 0;
        var av = (a === null || a === undefined || a === '') ? NaN : parseNum(a, params);
        var bv = (b === null || b === undefined || b === '') ? NaN : parseNum(b, params);

        if (isNaN(av)) {
            emptyAlign = isNaN(bv) ? 0 : -1;
        } else if (isNaN(bv)) {
            emptyAlign = 1;
        } else {
            return av - bv;
        }
        // Tabulator hands the rows over already swapped for "desc", so only flip for the value that stays fixed.
        if ((align === 'top' && dir === 'desc') || (align === 'bottom' && dir === 'asc')) {
            emptyAlign *= -1;
        }
        return emptyAlign;
    }

    function install() {
        var T = global.Tabulator;
        if (!T || T.__sortDefaultsInstalled || typeof T.extendModule !== 'function') return false;
        T.extendModule('sort', 'sorters', { number: numberSorter });
        T.__sortDefaultsInstalled = true;
        return true;
    }

    global.tabSortBlankLast = function (valueFn, zeroEmpty) {
        var norm = function (row) {
            var d = (row && typeof row.getData === 'function') ? (row.getData() || {}) : {};
            var v = valueFn(d);
            if (v === null || v === undefined || v === '') return null;
            v = Number(v);
            if (!isFinite(v)) return null;
            if (zeroEmpty && v <= 0) return null;
            return v;
        };
        return function (a, b, aRow, bRow, column, dir) {
            var av = norm(aRow);
            var bv = norm(bRow);
            if (av === null && bv === null) return 0;
            var blankLast = dir === 'desc' ? -1 : 1;
            if (av === null) return blankLast;
            if (bv === null) return -blankLast;
            return av - bv;
        };
    };

    global.tabSortNumberSorter = numberSorter;
    install();
})(window);
