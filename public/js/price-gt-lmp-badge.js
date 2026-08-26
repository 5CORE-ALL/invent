(function (window) {
    'use strict';

    function isParentRow(row) {
        if (!row || typeof row !== 'object') return false;
        if (row.is_parent_summary || row.is_parent || row.is_parent_row) return true;
        if (Array.isArray(row._children) && row._children.length) return true;
        // Parent *name* is often "PARENT 10 FR" on child SKUs — that is not a parent row.
        var sku = String(row['(Child) sku'] || row.sku || row.Sku || row.SKU || '').trim().toUpperCase();
        return sku.indexOf('PARENT') !== -1;
    }

    function num(v) {
        var n = parseFloat(v);
        return isFinite(n) ? n : NaN;
    }

    function firstNum(row, fields) {
        if (!row) return NaN;
        for (var i = 0; i < fields.length; i++) {
            var v = num(row[fields[i]]);
            if (isFinite(v) && v > 0) return v;
        }
        return NaN;
    }

    function priceOf(row, priceField) {
        var fields = priceField ? [priceField] : [];
        fields = fields.concat(['eBay Price', 'Price', 'price', 'MC Price', 'api_price', 'doba Price', 'self_pick_price']);
        return firstNum(row, fields);
    }

    function invOf(row) {
        if (!row) return 0;
        var fields = ['inventory', 'INV', 'inv', 'Inv', 'QTY AVAIL', 'qty_avail'];
        for (var i = 0; i < fields.length; i++) {
            if (row[fields[i]] == null || row[fields[i]] === '') continue;
            var n = num(row[fields[i]]);
            if (isFinite(n)) return n;
        }
        return 0;
    }

    function lmpOf(row) {
        var v = firstNum(row, ['lmp_price', 'lmp', 'LMP', 'LMP 1', 'lmp_1']);
        if (isFinite(v) && v > 0) return v;
        if (Array.isArray(row.lmp_entries) && row.lmp_entries.length) {
            var lowest = NaN;
            for (var j = 0; j < row.lmp_entries.length; j++) {
                var e = row.lmp_entries[j] || {};
                var p = num(e.price != null ? e.price : e.lmp);
                if (isFinite(p) && p > 0 && (!isFinite(lowest) || p < lowest)) lowest = p;
            }
            return lowest;
        }
        return NaN;
    }

    function hasRedTriangle(row, priceField) {
        if (!row || isParentRow(row)) return false;
        if (!(invOf(row) > 0)) return false;
        var price = priceOf(row, priceField);
        var lmp = lmpOf(row);
        return isFinite(price) && price > 0 && isFinite(lmp) && lmp > 0 && price > lmp;
    }

    function count(rows, priceField) {
        var n = 0;
        (rows || []).forEach(function (row) {
            if (hasRedTriangle(row, priceField)) n++;
        });
        return n;
    }

    function elOf(target) {
        if (!target) return null;
        if (typeof target === 'string') return document.querySelector(target);
        if (window.jQuery && target.jquery) return target[0] || null;
        return target;
    }

    function paint(target, n) {
        var el = elOf(target);
        if (!el) return;
        var value = Number(n || 0);
        var dot = el.querySelector('.summary-trend-dot, .kpi-status-dot');
        el.innerHTML = '<i class="fas fa-exclamation-triangle"></i> ' + value.toLocaleString();
        if (dot) el.insertBefore(dot, el.firstChild);
        el.setAttribute('data-live-value', String(value));
        el.style.backgroundColor = value === 0 ? '#28a745' : '#dc3545';
        el.style.color = '#fff';
        el.style.fontWeight = '700';
    }

    function report(channelKey, n) {
        if (!channelKey || !window.PRICE_GT_LMP_REPORT_URL) return;
        try {
            fetch(window.PRICE_GT_LMP_REPORT_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') || {}).content || '',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ channel: channelKey, count: Number(n || 0) })
            });
        } catch (e) {
            // ignore
        }
    }

    function update(target, rows, channelKey, priceField) {
        var n = count(rows, priceField);
        paint(target, n);
        if (channelKey) report(channelKey, n);
        return n;
    }

    function setOutline(el, on) {
        if (!el) return;
        el.style.outline = on ? '3px solid #ffc107' : '';
        el.style.outlineOffset = on ? '2px' : '';
    }

    function triangleHtml(price, lmp) {
        var p = num(price);
        var l = num(lmp);
        if (!(isFinite(p) && p > 0 && isFinite(l) && l > 0 && p > l)) return '';
        return ' <i class="fas fa-exclamation-triangle" style="color:#dc3545;font-size:10px;margin-left:3px;" title="Price $'
            + p.toFixed(2) + ' &gt; LMP $' + l.toFixed(2) + '"></i>';
    }

    function decoratePrice(html, price, lmp) {
        return String(html || '') + triangleHtml(price, lmp);
    }

    function bind(opts) {
        opts = opts || {};
        var el = elOf(opts.badge);
        if (!el || el.dataset.pglBound === '1') return;
        el.dataset.pglBound = '1';
        el.style.cursor = 'pointer';
        el.addEventListener('click', function (e) {
            if (e.target && e.target.closest && e.target.closest('.summary-trend-dot, .kpi-status-dot')) {
                return;
            }
            var next = !opts.getActive();
            if (typeof opts.onToggle === 'function') opts.onToggle(next);
            setOutline(el, next);
        });
    }

    window.PriceGtLmpBadge = {
        isParentRow: isParentRow,
        priceOf: priceOf,
        lmpOf: lmpOf,
        hasRedTriangle: hasRedTriangle,
        count: count,
        paint: paint,
        report: report,
        update: update,
        bind: bind,
        setOutline: setOutline,
        triangleHtml: triangleHtml,
        decoratePrice: decoratePrice
    };
})(window);
