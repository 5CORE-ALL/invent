{{--
    "Issue?" column from /customer-care/qc-packing, for Tabulator tables keyed by SKU.
    Data is limited to what the QC / Packaging page shows (not archived, department QC or Packaging).
    Include once inside a <script> tag before the table is built, then:
        columns: [ ..., window.QcIssueCol.column('SKU'), ... ]
        window.QcIssueCol.attach(table);
--}}
window.QcIssueCol = (function () {
    let map = null;
    let tableRef = null;

    function esc(s) {
        return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function render(sku, isParent) {
        if (isParent) return '<span class="text-muted small">—</span>';
        if (!map) return '';
        const items = map[String(sku || '').trim().toUpperCase()];
        if (!items || !items.length) return '<span class="text-muted">—</span>';
        const tip = items.map(function (it) { return it.value + (it.count > 1 ? ' (x' + it.count + ')' : ''); }).join('\n');
        const parts = items.map(function (it) {
            const t = String(it.value || '').trim().toLowerCase();
            if (t === '0 stock') return '<span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#dc3545;vertical-align:middle;"></span>';
            if (t === 'damaged') return '<span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#b8860b;vertical-align:middle;"></span>';
            return '<span class="fw-semibold" style="font-size:12px;">' + esc(it.value) + '</span>';
        });
        return '<div title="' + esc(tip) + '" style="display:flex;flex-wrap:wrap;gap:4px;align-items:center;justify-content:center;">' + parts.join('') + '</div>';
    }

    function column(skuField) {
        return {
            title: 'Issue?',
            field: 'qc_issue_what',
            hozAlign: 'center',
            vertAlign: 'middle',
            width: 90,
            minWidth: 60,
            headerSort: false,
            download: false,
            headerTooltip: 'Issue? from QC / Packaging (/customer-care/qc-packing)',
            formatter: function (cell) {
                const d = cell.getRow().getData() || {};
                const sku = d[skuField] != null ? d[skuField] : (d.SKU != null ? d.SKU : d.sku);
                return render(sku, !!(d.is_parent || d.isParent));
            },
        };
    }

    function attach(table) {
        tableRef = table;
        fetch('{{ route('customer.care.qc.and.packing.what.happened') }}', {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                map = (res && res.success && res.data) ? res.data : {};
                if (tableRef) tableRef.redraw(true);
            })
            .catch(function (err) {
                console.warn('QC Issue? data failed to load', err);
                map = {};
                if (tableRef) tableRef.redraw(true);
            });
    }

    return { column: column, attach: attach };
})();
