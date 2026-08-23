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

    /*
     * Yellow / warning chips and buttons (e.g. Syncing… 0): always black text.
     * Theme + .text-white otherwise wash out on #ffc107.
     */
    .badge.bg-warning,
    .badge.badge-warning,
    .badge.text-bg-warning,
    .badge.bg-warning-subtle,
    .badge.bg-warning-subtle.text-warning,
    .btn-warning,
    .btn.btn-warning,
    .btn.btn-warning.text-white,
    .btn-warning.text-white,
    .text-bg-warning {
        --bs-btn-color: #000 !important;
        --bs-btn-hover-color: #000 !important;
        --bs-btn-active-color: #000 !important;
        --bs-btn-disabled-color: #000 !important;
        --bs-badge-color: #000 !important;
        color: #000 !important;
    }
    .badge.bg-warning i,
    .badge.bg-warning *,
    .badge.badge-warning *,
    .badge.text-bg-warning *,
    .badge.bg-warning-subtle *,
    .btn-warning i,
    .btn-warning *,
    .btn.btn-warning i,
    .btn.btn-warning *,
    .btn.btn-warning.text-white *,
    .text-bg-warning * {
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

    /*
     * Freeze header rows on every HTML table. --app-freeze-top is 0 inside a
     * capped scroll wrapper and the topbar height for page-scroll tables
     * (see public/js/freeze-table-headers.js).
     */
    table > thead > tr > th,
    table > thead > tr > td {
        position: sticky !important;
        top: var(--app-freeze-top, 0px) !important;
        z-index: 6 !important;
        background-color: #dbeafe;
        box-shadow: 0 1px 0 #93c5fd;
    }
    .table-responsive > table > thead > tr > th,
    .table-container > table > thead > tr > th,
    .modal-body .table-responsive > table > thead > tr > th,
    #ebay-table > thead > tr > th,
    #sku-history-content .table-responsive > table > thead > tr > th {
        position: sticky !important;
        top: var(--app-freeze-top, 0px) !important;
        z-index: 6 !important;
        background-color: #dbeafe !important;
        box-shadow: 0 1px 0 #93c5fd;
    }
    .tabulator .tabulator-header {
        position: sticky !important;
        top: 0 !important;
        z-index: 7 !important;
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

    /* Scout / zoom / table hover previews — larger than 32px thumbs, but NEVER full-screen.
       (Previously max-width:none !important blew up ebay/amazon hover previews to natural image size.) */
    .modal img.image-thumbnail,
    img.scouth-image-thumbnail,
    img[data-preview],
    img[style*="max-width:120px"],
    img[style*="max-width: 120px"],
    img[style*="max-width:350px"],
    img[style*="max-width: 350px"],
    #image-hover-preview img,
    #global-img-hover-preview,
    .product-image-enlarged {
        width: auto !important;
        height: auto !important;
        max-width: min(420px, 90vw) !important;
        max-height: min(420px, 80vh) !important;
        object-fit: contain !important;
    }

    /* Cap OV L30 modal SKU thumb (inline width/height win within this ceiling) */
    .modal #modal-product-image {
        max-width: 50px !important;
        max-height: 50px !important;
        object-fit: cover !important;
    }

    /*
     * KPI / summary badge trend dots — compact so more badges fit on a row.
     * Used on Active Channel, marketplace tabulators, dashboard, PEF, LMP, etc.
     */
    .summary-trend-dot,
    .kpi-status-dot,
    .pef-kpi-dot,
    .amz-vv-trend-dot {
        display: inline-block !important;
        width: 6px !important;
        height: 6px !important;
        min-width: 6px !important;
        min-height: 6px !important;
        border-radius: 50% !important;
        margin-right: 0.22rem !important;
        margin-left: 0 !important;
        flex-shrink: 0 !important;
        vertical-align: 0.08em;
        box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.85);
        cursor: pointer;
        position: relative;
        z-index: 2;
    }
    .summary-trend-dot:hover,
    .kpi-status-dot:hover,
    .pef-kpi-dot:hover,
    .amz-vv-trend-dot:hover {
        transform: scale(1.35);
    }
    .summary-trend-dot.up,
    .kpi-status-dot--green,
    .pef-kpi-dot--green { background: #22c55e; }
    .summary-trend-dot.down,
    .kpi-status-dot--red,
    .pef-kpi-dot--red { background: #ef4444; }
    .summary-trend-dot.flat,
    .summary-trend-dot.none,
    .kpi-status-dot--gray,
    .pef-kpi-dot--gray { background: #9ca3af; }

    #summary-stats .badge,
    .badge-chart-link,
    .amz-badge-chart,
    .tt-badge-chart,
    .pef-metric-badge,
    .dashboard-badge-panel__badges > .badge,
    .ebay2-summary-badge-row .badge {
        display: inline-flex;
        align-items: center;
    }

</style>

{{-- Global NROI / GROI / NPFT / GPFT % color schema (window.MetricPctColors) --}}
<script src="{{ asset('js/metric-percent-colors.js') }}"></script>
