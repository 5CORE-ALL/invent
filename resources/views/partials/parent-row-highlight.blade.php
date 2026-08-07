{{-- Light yellow highlight for PARENT rows — included globally via layouts.shared.head-css --}}
@once
<style>
    /*
     * Any table / Tabulator row whose class contains "parent-row"
     * (parent-row, pm-parent-row, ebay-parent-row, wf-parent-row, …).
     */
    tr[class*="parent-row"],
    tr[class*="parent-row"] > td,
    .tabulator-row[class*="parent-row"],
    .tabulator-row[class*="parent-row"] .tabulator-cell,
    .tabulator-row[class*="parent-row"] .tabulator-frozen,
    .tabulator-row[class*="parent-row"].tabulator-row-even,
    .tabulator-row[class*="parent-row"].tabulator-row-odd {
        background-color: #fffef2 !important; /* soft cream yellow */
    }

    tr[class*="parent-row"]:hover,
    tr[class*="parent-row"]:hover > td,
    .tabulator-row[class*="parent-row"]:hover,
    .tabulator-row[class*="parent-row"]:hover .tabulator-cell,
    .tabulator-row[class*="parent-row"]:hover .tabulator-frozen {
        background-color: #fefce8 !important; /* slightly warmer on hover */
    }

    /* Yellow triangle expand control (shared ParentExpand column) */
    .pm-parent-sku-dot,
    .ebay2-parent-sku-dot,
    .parent-sku-dot {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 16px;
        height: 16px;
        cursor: pointer;
        vertical-align: middle;
        line-height: 0;
        transition: transform 0.2s ease, filter 0.2s ease, opacity 0.2s ease;
        filter: drop-shadow(0 1px 1px rgba(180, 110, 0, 0.35));
    }
    .pm-parent-sku-dot svg,
    .ebay2-parent-sku-dot svg,
    .parent-sku-dot svg {
        width: 14px;
        height: 14px;
        display: block;
    }
    .pm-parent-sku-dot:hover,
    .ebay2-parent-sku-dot:hover,
    .parent-sku-dot:hover {
        filter: drop-shadow(0 2px 3px rgba(180, 110, 0, 0.45));
        transform: scale(1.08);
    }
    .pm-parent-sku-dot.is-expanded,
    .ebay2-parent-sku-dot.is-expanded,
    .parent-sku-dot.is-expanded {
        transform: rotate(90deg);
    }
    .pm-parent-sku-dot.is-expanded:hover,
    .ebay2-parent-sku-dot.is-expanded:hover,
    .parent-sku-dot.is-expanded:hover {
        transform: rotate(90deg) scale(1.08);
    }
    .pm-parent-sku-dot.no-parent,
    .ebay2-parent-sku-dot.no-parent,
    .parent-sku-dot.no-parent {
        cursor: default;
        opacity: 0.35;
        filter: grayscale(1) drop-shadow(none);
    }
    .pm-parent-sku-dot.no-parent:hover,
    .ebay2-parent-sku-dot.no-parent:hover,
    .parent-sku-dot.no-parent:hover {
        transform: none;
        filter: grayscale(1) drop-shadow(none);
    }
</style>
<script src="{{ asset('js/parent-expand-column.js') }}"></script>
<script>
    window.isPmParentSku = function (sku) {
        return String(sku || '').toUpperCase().includes('PARENT');
    };
    /** True when a Tabulator/table row is a PARENT summary row. */
    window.isPmParentRowData = function (d) {
        if (!d) return false;
        if (d.is_parent_summary === true || d.is_parent_row === true || d.is_parent === true) return true;
        var keys = ['SKU', 'sku', '(Child) sku', 'Sku', 'Parent'];
        for (var i = 0; i < keys.length; i++) {
            var v = d[keys[i]];
            if (v != null && String(v).toUpperCase().includes('PARENT')) return true;
        }
        return false;
    };
    window.pmParentRowFormatter = function (row) {
        try {
            var el = row.getElement();
            var d = row.getData() || {};
            if (window.isPmParentRowData(d)) {
                el.classList.add('pm-parent-row');
                el.classList.add('parent-row');
            } else {
                el.classList.remove('pm-parent-row');
                el.classList.remove('parent-row');
            }
        } catch (e) {}
    };
</script>
@endonce
