@vite([
    'resources/js/head.js',
    'resources/js/config.js',
    'resources/scss/app.scss',
    'resources/scss/icons.scss'
    ])

{{-- Global: badges with light backgrounds must use black text for readability --}}
<style>
    .badge.bg-warning,
    .badge.bg-info,
    .badge.bg-light,
    .badge.bg-primary,
    .badge.bg-success,
    .badge.text-bg-warning,
    .badge.text-bg-info,
    .badge.text-bg-light,
    .badge.text-bg-primary,
    .badge.text-bg-success,
    .badge.bg-warning-subtle,
    .badge.bg-info-subtle,
    .badge.bg-light-subtle,
    .badge.bg-success-subtle,
    .badge.bg-primary-subtle,
    .badge.bg-secondary-subtle,
    .badge.bg-danger-subtle,
    .badge.bg-warning *,
    .badge.bg-info *,
    .badge.bg-light *,
    .badge.bg-primary *,
    .badge.bg-success * {
        color: #000 !important;
    }
</style>

{{-- Global: PARENT rows light yellow + ParentExpand triangle column helpers --}}
@include('partials.parent-row-highlight')

{{-- Global: hide sort icons; header click-to-sort still works --}}
<style>
    .sort-arrow,
    th .sort-arrow,
    .sortable .sort-arrow,
    .ovl30-sort-icon,
    .tabulator .tabulator-header .tabulator-col .tabulator-col-sorter,
    .tabulator .tabulator-header .tabulator-col .tabulator-arrow {
        display: none !important;
        visibility: hidden !important;
        width: 0 !important;
        height: 0 !important;
        margin: 0 !important;
        padding: 0 !important;
        overflow: hidden !important;
        border: 0 !important;
    }

    /*
     * Global: light-blue page background + frozen (sticky) table headers.
     */
    body,
    .content-page,
    .content-page .content,
    #layout-wrapper,
    .page-content {
        background-color: #e8f4fc !important;
    }
    .card,
    .card-body {
        background-color: #fff;
    }
    .table-responsive,
    .table-container,
    .modal-body .table-responsive,
    .modal-dialog-scrollable .modal-body {
        overflow: auto;
    }
    table.table > thead > tr > th,
    table.custom-resizable-table > thead > tr > th,
    #ebay-table > thead > tr > th,
    .tabulator .tabulator-header,
    .tabulator .tabulator-header .tabulator-col {
        background-color: #dbeafe !important;
        color: #0f172a !important;
    }
    .table-responsive > table > thead > tr > th,
    .table-container > table > thead > tr > th,
    .modal-body .table-responsive > table > thead > tr > th,
    #ebay-table > thead > tr > th,
    #sku-history-content .table-responsive > table > thead > tr > th {
        position: sticky !important;
        top: 0 !important;
        z-index: 6 !important;
        background-color: #dbeafe !important;
        box-shadow: 0 1px 0 #93c5fd;
    }
    .tabulator .tabulator-header {
        position: sticky !important;
        top: 0 !important;
        z-index: 6 !important;
    }

    /*
     * Global product table thumbnails — match Verification Adjustment (32px = 0.80× of 40px).
     * Targets common Image-column patterns; keeps modal / scout zoom previews larger.
     */
    img[alt="Product"],
    img[alt="SKU Image"],
    img.product-thumb,
    img.hover-thumb,
    img.ebay2op-thumb-sm,
    #ebay-table td img,
    .tabulator-cell img.image-thumbnail,
    table td img.image-thumbnail,
    .tabulator-cell img[style*="object-fit: cover"],
    .tabulator-cell img[style*="object-fit:cover"],
    .tabulator-cell img[style*="object-fit: contain"],
    .tabulator-cell img[style*="object-fit:contain"],
    .tabulator-cell img[style*="width: 40px"],
    .tabulator-cell img[style*="width:40px"],
    .tabulator-cell img[style*="width: 50px"],
    .tabulator-cell img[style*="width:50px"],
    .tabulator-cell img[style*="width: 60px"],
    .tabulator-cell img[style*="width:60px"],
    .tabulator-cell img[style*="max-width:50px"],
    .tabulator-cell img[style*="max-width: 50px"],
    .tabulator-cell img[style*="height:40px"],
    .tabulator-cell img[style*="height: 40px"],
    table td img[style*="width: 40px"],
    table td img[style*="width:40px"],
    table td img[style*="width: 50px"],
    table td img[style*="width:50px"],
    table td img[style*="height:40px"],
    table td img[style*="height: 40px"] {
        width: 32px !important;
        height: 32px !important;
        max-width: 32px !important;
        max-height: 32px !important;
        object-fit: cover !important;
    }

    /* Keep modal / zoom previews larger */
    .modal img[alt="Product"],
    .modal img[alt="SKU Image"],
    .modal img.image-thumbnail,
    .modal #modal-product-image,
    img.scouth-image-thumbnail,
    img[data-preview],
    img[style*="max-width:120px"],
    img[style*="max-width: 120px"],
    img[style*="max-width:350px"],
    img[style*="max-width: 350px"] {
        width: auto !important;
        height: auto !important;
        max-width: none !important;
        max-height: none !important;
        object-fit: contain !important;
    }

</style>

{{-- Global NROI / GROI / NPFT / GPFT % color schema (window.MetricPctColors) --}}
<script src="{{ asset('js/metric-percent-colors.js') }}"></script>
