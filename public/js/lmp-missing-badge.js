(function (window) {
    'use strict';

    function isParentRow(row) {
        if (!row || typeof row !== 'object') return false;
        if (row.is_parent_summary || row.is_parent || row.is_parent_row) return true;
        if (Array.isArray(row._children) && row._children.length) return true;
        var sku = String(row['(Child) sku'] || row.sku || row.Sku || row.SKU || '').trim().toUpperCase();
        return sku.indexOf('PARENT') !== -1;
    }

    function num(v) {
        var n = parseFloat(v);
        return isFinite(n) ? n : NaN;
    }

    function hasLmp(row) {
        if (!row) return false;
        var fields = ['lmp_price', 'lmp', 'LMP', 'LMP 1', 'lmp_1'];
        for (var i = 0; i < fields.length; i++) {
            var v = num(row[fields[i]]);
            if (isFinite(v) && v > 0) return true;
        }
        var entriesTotal = num(row.lmp_entries_total);
        if (isFinite(entriesTotal) && entriesTotal > 0) return true;
        if (Array.isArray(row.lmp_entries) && row.lmp_entries.length) {
            for (var j = 0; j < row.lmp_entries.length; j++) {
                var e = row.lmp_entries[j] || {};
                var p = num(e.price != null ? e.price : e.lmp);
                if (isFinite(p) && p > 0) return true;
            }
        }
        return false;
    }

    function count(rows) {
        var n = 0;
        (rows || []).forEach(function (row) {
            if (isParentRow(row)) return;
            if (!hasLmp(row)) n++;
        });
        return n;
    }

    function elOf(target) {
        if (!target) return null;
        if (typeof target === 'string') {
            return document.querySelector(target);
        }
        if (window.jQuery && target.jquery) {
            return target[0] || null;
        }
        return target;
    }

    function paint(target, n) {
        var el = elOf(target);
        if (!el) return;
        var value = Number(n || 0);
        var dot = el.querySelector('.summary-trend-dot, .kpi-status-dot');
        el.textContent = 'LMP M. ' + value.toLocaleString();
        if (dot) el.insertBefore(dot, el.firstChild);
        el.setAttribute('data-live-value', String(value));
        el.style.backgroundColor = value === 0 ? '#28a745' : '#dc3545';
        el.style.color = '#fff';
        el.style.fontWeight = '700';
    }

    function report(channelKey, n) {
        if (!channelKey || !window.LMP_MISSING_REPORT_URL) return;
        try {
            fetch(window.LMP_MISSING_REPORT_URL, {
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

    function update(target, rows, channelKey) {
        var n = count(rows);
        paint(target, n);
        if (channelKey) report(channelKey, n);
        return n;
    }

    function setOutline(el, on) {
        if (!el) return;
        el.style.outline = on ? '3px solid #ffc107' : '';
        el.style.outlineOffset = on ? '2px' : '';
    }

    function columnDef(field) {
        field = field || 'lmp_price';
        return {
            title: 'LMP',
            field: field,
            hozAlign: 'center',
            sorter: 'number',
            width: 90,
            headerTooltip: 'Lowest marketplace price. N/A = no LMP data.',
            formatter: function (cell) {
                var row = cell.getRow().getData();
                if (isParentRow(row)) return '';
                var raw = cell.getValue();
                if (raw == null || raw === '') raw = row.lmp_price != null ? row.lmp_price : row.lmp;
                var v = num(raw);
                if (isFinite(v) && v > 0) {
                    return '$' + v.toFixed(2);
                }
                return '<span style="color:#999;">N/A</span>';
            }
        };
    }

    function bind(opts) {
        opts = opts || {};
        var el = elOf(opts.badge);
        if (!el || el.dataset.lmpBound === '1') return;
        el.dataset.lmpBound = '1';
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

    window.LmpMissingBadge = {
        isParentRow: isParentRow,
        hasLmp: hasLmp,
        count: count,
        paint: paint,
        report: report,
        update: update,
        bind: bind,
        setOutline: setOutline,
        columnDef: columnDef
    };
})(window);
