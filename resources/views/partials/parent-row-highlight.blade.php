{{-- Light yellow highlight for SKU rows containing PARENT --}}
@once
<style>
    tr.pm-parent-row,
    tr.pm-parent-row > td,
    .tabulator-row.pm-parent-row,
    .tabulator-row.pm-parent-row .tabulator-cell {
        background-color: #fef9c3 !important;
    }
    tr.pm-parent-row:hover,
    tr.pm-parent-row:hover > td,
    .tabulator-row.pm-parent-row:hover,
    .tabulator-row.pm-parent-row:hover .tabulator-cell,
    .tabulator-row.pm-parent-row.tabulator-row-even,
    .tabulator-row.pm-parent-row.tabulator-row-odd {
        background-color: #fef08a !important;
    }
</style>
<script>
window.isPmParentSku = function (sku) {
    return String(sku || '').toUpperCase().includes('PARENT');
};
window.pmParentRowFormatter = function (row) {
    try {
        var el = row.getElement();
        var d = row.getData() || {};
        if (window.isPmParentSku(d.SKU)) {
            el.classList.add('pm-parent-row');
        } else {
            el.classList.remove('pm-parent-row');
        }
    } catch (e) {}
};
</script>
@endonce
