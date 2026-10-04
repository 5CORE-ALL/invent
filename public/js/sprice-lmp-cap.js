(function (window) {
    'use strict';

    function num(v) {
        var n = parseFloat(v);
        return isFinite(n) ? n : NaN;
    }

    function lmpOf(row, getLmp) {
        if (typeof getLmp === 'function') {
            var custom = num(getLmp(row));
            if (isFinite(custom) && custom > 0) return custom;
            if (row && Array.isArray(row.lmp_entries) && row.lmp_entries.length) return NaN;
        }
        if (window.PriceGtLmpBadge && typeof PriceGtLmpBadge.lmpOf === 'function') {
            var fromBadge = PriceGtLmpBadge.lmpOf(row);
            if (isFinite(fromBadge) && fromBadge > 0) return fromBadge;
        }
        if (!row) return NaN;
        var fields = ['lmp_price', 'lmp', 'LMP', 'LMP 1', 'lmp_1'];
        for (var i = 0; i < fields.length; i++) {
            var v = num(row[fields[i]]);
            if (isFinite(v) && v > 0) return v;
        }
        return NaN;
    }

    function stdOf(row) {
        if (!row) return NaN;
        var fields = ['STANDARD_PRICE', 'standard_price', 'std_price'];
        for (var i = 0; i < fields.length; i++) {
            var raw = row[fields[i]];
            if (raw == null || raw === '') continue;
            var v = num(raw);
            if (isFinite(v) && v > 0) return v;
        }
        return NaN;
    }

    /**
     * Std Prc is the maximum when LMP is missing, or when LMP is above Std.
     * Blank Std Prc does not cap. LMP at or below Std leaves the price unchanged.
     */
    function capToStdWhenNoLmp(sprice, lmp, std) {
        var s = num(sprice);
        var l = num(lmp);
        var t = num(std);
        if (!(isFinite(s) && s > 0)) return isFinite(s) ? +Number(s).toFixed(2) : s;
        s = +Number(s).toFixed(2);
        if (!(isFinite(t) && t > 0) || s + 0.0001 <= t) return s;
        if (isFinite(l) && l > 0 && l + 0.0001 <= t) return s;
        return +Number(t).toFixed(2);
    }

    function lmpAboveStd(lmp, std) {
        var l = num(lmp);
        var t = num(std);
        return isFinite(l) && l > 0 && isFinite(t) && t > 0 && l + 0.0001 > t;
    }

    function cap(sprice, lmp, std) {
        var s = num(sprice);
        var l = num(lmp);
        if (!(isFinite(s) && s > 0)) return isFinite(s) ? +Number(s).toFixed(2) : s;
        if (isFinite(l) && l > 0 && s + 0.0001 >= l) s = +Number(l).toFixed(2);
        return capToStdWhenNoLmp(s, l, std);
    }

    function prepare(row, sprice, getLmp) {
        return cap(sprice, lmpOf(row, getLmp), stdOf(row));
    }

    function hasAlert(row, sprice, getLmp) {
        var raw = num(sprice);
        var lmp = lmpOf(row, getLmp);
        if (!(isFinite(lmp) && lmp > 0)) return false;
        if (!(isFinite(raw) && raw > 0)) return false;
        var shown = cap(raw, lmp);
        return raw + 0.0001 >= lmp || shown + 0.0001 >= lmp;
    }

    function triangleHtml(lmp) {
        var l = num(lmp);
        if (!(isFinite(l) && l > 0)) return '';
        return '<i class="fas fa-exclamation-triangle sprice-lmp-alert" style="color:#dc3545;font-size:10px;margin-left:3px;" title="S PRC capped at LMP $'
            + l.toFixed(2) + '"></i>';
    }

    function stdTriangleHtml(std) {
        var t = num(std);
        if (!(isFinite(t) && t > 0)) return '';
        return '<i class="fas fa-exclamation-triangle sprice-std-cap" style="color:#b45309;font-size:10px;margin-left:3px;" title="No LMP — S PRC capped at Std Prc $'
            + t.toFixed(2) + '"></i>';
    }

    /** Orange triangle on Std Prc when LMP is above Std — review that Std price. */
    function reviewStdTriangleHtml(row) {
        var lmp = lmpOf(row);
        var std = stdOf(row);
        if (!lmpAboveStd(lmp, std)) return '';
        return '<i class="fas fa-exclamation-triangle sprice-std-review" style="color:#fd7e14;font-size:10px;margin-left:3px;" title="LMP $'
            + Number(lmp).toFixed(2) + ' is above Std Prc $' + Number(std).toFixed(2) + ' — review Std price"></i>';
    }

    function apply(row, sprice, getLmp) {
        var raw = num(sprice);
        var lmp = lmpOf(row, getLmp);
        var std = stdOf(row);
        var shown = prepare(row, raw, getLmp);
        var alert = hasAlert(row, raw, getLmp);
        var review = lmpAboveStd(lmp, std);
        var stdCappedHighLmp = review
            && isFinite(raw) && raw > 0
            && isFinite(std) && raw + 0.0001 > std
            && isFinite(shown) && shown + 0.0001 <= std + 0.0001;
        var stdCapped = !review
            && !(isFinite(lmp) && lmp > 0)
            && isFinite(std) && std > 0
            && isFinite(raw) && raw > 0
            && raw + 0.0001 > std;
        var tri = '';
        if (stdCappedHighLmp) tri = reviewStdTriangleHtml(row);
        else if (alert) tri = triangleHtml(lmp);
        else if (stdCapped) tri = stdTriangleHtml(std);
        return {
            raw: raw,
            value: isFinite(shown) ? shown : raw,
            shown: isFinite(shown) ? shown : raw,
            lmp: lmp,
            std: std,
            stdCapped: stdCapped || stdCappedHighLmp,
            lmpAboveStd: review,
            overLmp: alert && !stdCappedHighLmp,
            alert: alert && !stdCappedHighLmp,
            triangleHtml: tri
        };
    }

    window.SpriceLmpCap = {
        lmpOf: lmpOf,
        stdOf: stdOf,
        cap: cap,
        capToStdWhenNoLmp: capToStdWhenNoLmp,
        lmpAboveStd: lmpAboveStd,
        prepare: prepare,
        hasAlert: hasAlert,
        triangleHtml: triangleHtml,
        stdTriangleHtml: stdTriangleHtml,
        reviewStdTriangleHtml: reviewStdTriangleHtml,
        apply: apply,
        decorate: apply
    };
})(window);
