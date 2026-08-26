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

    function cap(sprice, lmp) {
        var s = num(sprice);
        var l = num(lmp);
        if (!(isFinite(s) && s > 0)) return isFinite(s) ? +Number(s).toFixed(2) : s;
        if (isFinite(l) && l > 0 && s + 0.0001 >= l) return +Number(l).toFixed(2);
        return +Number(s).toFixed(2);
    }

    function prepare(row, sprice, getLmp) {
        return cap(sprice, lmpOf(row, getLmp));
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

    function apply(row, sprice, getLmp) {
        var raw = num(sprice);
        var lmp = lmpOf(row, getLmp);
        var shown = prepare(row, raw, getLmp);
        var alert = hasAlert(row, raw, getLmp);
        return {
            raw: raw,
            value: isFinite(shown) ? shown : raw,
            shown: isFinite(shown) ? shown : raw,
            lmp: lmp,
            overLmp: alert,
            alert: alert,
            triangleHtml: alert ? triangleHtml(lmp) : ''
        };
    }

    window.SpriceLmpCap = {
        lmpOf: lmpOf,
        cap: cap,
        prepare: prepare,
        hasAlert: hasAlert,
        triangleHtml: triangleHtml,
        apply: apply,
        decorate: apply
    };
})(window);
