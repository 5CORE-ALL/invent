@extends('layouts.vertical', ['title' => 'Review Intelligence Master', 'sidenav' => 'condensed'])

@section('css')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    .review-badge { font-size: 0.72rem; font-weight: 600; padding: 3px 8px; border-radius: 20px; }
    .badge-positive  { background: #d1f4e0; color: #198754; }
    .badge-neutral   { background: #fff3cd; color: #856404; }
    .badge-negative  { background: #f8d7da; color: #842029; }
    .badge-quality   { background: #f8d7da; color: #842029; }
    .badge-packaging { background: #fff3cd; color: #856404; }
    .badge-shipping  { background: #cff4fc; color: #055160; }
    .badge-service   { background: #e2e3e5; color: #383d41; }
    .badge-wrong_item{ background: #212529; color: #fff; }
    .badge-missing_parts { background: #cfe2ff; color: #084298; }
    .badge-other     { background: #f8f9fa; color: #495057; }

    .stat-card { border-radius: 12px; transition: transform 0.15s; }
    .stat-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,.12); }
    .stat-card .stat-icon { width: 48px; height: 48px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; }

    .review-text-preview { max-width: 240px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: inline-block; cursor: pointer; }
    .star-rating { color: #f0ad4e; font-size: 0.8rem; }

    .alert-badge { animation: pulse 2s infinite; }
    @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.5} }

    .flag-icon { color: #dc3545; }
    .table-row-negative td { background: rgba(220,53,69,.04); }
    .insights-card { border-left: 4px solid; border-radius: 8px; }
    .insights-card.danger { border-left-color: #dc3545; }
    .insights-card.warning { border-left-color: #ffc107; }
    .insights-card.info { border-left-color: #0dcaf0; }

    #reviewTable_wrapper .dataTables_filter input { border-radius: 20px; padding: 4px 14px; }
    .chart-container { position: relative; height: 280px; }
    .supplier-row:hover { background: rgba(13,110,253,.05); cursor: pointer; }
</style>
@endsection

@section('content')
<div class="container-fluid">

    {{-- ======================================================= --}}
    {{-- PAGE HEADER --}}
    {{-- ======================================================= --}}
    <div class="row mb-3">
        <div class="col-12">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <h4 class="mb-0 fw-bold"><i class="ri-star-smile-line me-2 text-warning"></i>Review Intelligence Master</h4>
                    <small class="text-muted">AI-powered review analysis, issue detection & supplier intelligence</small>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#alertsModal">
                        <i class="ri-alarm-warning-line me-1"></i>Alerts
                        @if($openAlerts > 0)
                            <span class="badge bg-danger ms-1 alert-badge">{{ $openAlerts }}</span>
                        @endif
                    </button>
                    <button class="btn btn-sm btn-outline-info" id="btnAiInsights" data-bs-toggle="modal" data-bs-target="#aiInsightsModal">
                        <i class="ri-brain-line me-1"></i>AI Insights
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" id="btnSupplierPanel" data-bs-toggle="modal" data-bs-target="#supplierModal">
                        <i class="ri-user-star-line me-1"></i>Supplier Panel
                    </button>
                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#uploadCsvModal">
                        <i class="ri-upload-cloud-2-line me-1"></i>Import CSV
                    </button>
                    <button class="btn btn-sm btn-outline-success" id="btnRefreshSummary">
                        <i class="ri-refresh-line me-1"></i>Refresh
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ======================================================= --}}
    {{-- DASHBOARD STATS --}}
    {{-- ======================================================= --}}
    <div class="row g-3 mb-4" id="dashboardStats">
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card stat-card h-100">
                <div class="card-body d-flex align-items-center gap-3 p-3">
                    <div class="stat-icon bg-primary bg-opacity-10"><i class="ri-chat-3-line text-primary"></i></div>
                    <div>
                        <div class="fw-bold fs-5" id="stat-total">{{ number_format($dashStats['total_reviews']) }}</div>
                        <div class="text-muted" style="font-size:.72rem">Total Reviews</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card stat-card h-100">
                <div class="card-body d-flex align-items-center gap-3 p-3">
                    <div class="stat-icon bg-danger bg-opacity-10"><i class="ri-thumb-down-line text-danger"></i></div>
                    <div>
                        <div class="fw-bold fs-5 text-danger" id="stat-neg">{{ $dashStats['negative_pct'] }}%</div>
                        <div class="text-muted" style="font-size:.72rem">Negative Rate</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card stat-card h-100">
                <div class="card-body d-flex align-items-center gap-3 p-3">
                    <div class="stat-icon bg-warning bg-opacity-10"><i class="ri-alert-line text-warning"></i></div>
                    <div>
                        <div class="fw-bold text-uppercase" id="stat-issue" style="font-size:.85rem">{{ ucfirst(str_replace('_',' ',$dashStats['top_issue'])) }}</div>
                        <div class="text-muted" style="font-size:.72rem">Top Issue</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card stat-card h-100">
                <div class="card-body d-flex align-items-center gap-3 p-3">
                    <div class="stat-icon bg-danger bg-opacity-10"><i class="ri-barcode-box-line text-danger"></i></div>
                    <div>
                        <div class="fw-bold" id="stat-sku" style="font-size:.85rem">{{ $dashStats['worst_sku'] }}</div>
                        <div class="text-muted" style="font-size:.72rem">Worst SKU ({{ $dashStats['worst_sku_rate'] }}%)</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card stat-card h-100">
                <div class="card-body d-flex align-items-center gap-3 p-3">
                    <div class="stat-icon bg-warning bg-opacity-10"><i class="ri-truck-line text-warning"></i></div>
                    <div>
                        <div class="fw-bold" id="stat-supplier" style="font-size:.82rem;line-height:1.2">{{ $dashStats['worst_supplier'] }}</div>
                        <div class="text-muted" style="font-size:.72rem">Worst Supplier</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card stat-card h-100">
                <div class="card-body d-flex align-items-center gap-3 p-3">
                    <div class="stat-icon bg-danger bg-opacity-10"><i class="ri-alarm-warning-line text-danger"></i></div>
                    <div>
                        <div class="fw-bold fs-5 text-danger" id="stat-alerts">{{ $dashStats['open_alerts'] }}</div>
                        <div class="text-muted" style="font-size:.72rem">Open Alerts</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ======================================================= --}}
    {{-- FILTERS --}}
    {{-- ======================================================= --}}
    <div class="card mb-3">
        <div class="card-body p-2">
            <form id="filterForm">
                <div class="row g-2 align-items-end">
                    <div class="col-6 col-md-3 col-xl-2">
                        <input type="text" class="form-control form-control-sm" id="filter_sku" placeholder="Search SKU...">
                    </div>
                    <div class="col-6 col-md-3 col-xl-2">
                        <select class="form-select form-select-sm" id="filter_supplier">
                            <option value="">All Suppliers</option>
                            @foreach($suppliers as $s)
                                <option value="{{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-2 col-xl-2">
                        <select class="form-select form-select-sm" id="filter_marketplace">
                            <option value="">All Marketplaces</option>
                            <option value="amazon">Amazon</option>
                            <option value="ebay">eBay</option>
                            <option value="walmart">Walmart</option>
                            <option value="temu">Temu</option>
                            <option value="shopify">Shopify</option>
                            <option value="csv">CSV Import</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2 col-xl-1">
                        <select class="form-select form-select-sm" id="filter_rating">
                            <option value="">All Ratings</option>
                            <option value="1">★ 1</option>
                            <option value="2">★★ 2</option>
                            <option value="3">★★★ 3</option>
                            <option value="4">★★★★ 4</option>
                            <option value="5">★★★★★ 5</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2 col-xl-2">
                        <select class="form-select form-select-sm" id="filter_issue">
                            <option value="">All Issues</option>
                            <option value="quality">Quality</option>
                            <option value="packaging">Packaging</option>
                            <option value="shipping">Shipping</option>
                            <option value="service">Service</option>
                            <option value="wrong_item">Wrong Item</option>
                            <option value="missing_parts">Missing Parts</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2 col-xl-1">
                        <select class="form-select form-select-sm" id="filter_sentiment">
                            <option value="">All Sentiments</option>
                            <option value="positive">Positive</option>
                            <option value="neutral">Neutral</option>
                            <option value="negative">Negative</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-3 col-xl-2">
                        <input type="text" class="form-control form-control-sm" id="filter_daterange" placeholder="Date range...">
                    </div>
                    <div class="col-auto">
                        <button type="button" class="btn btn-sm btn-primary" id="btnApplyFilters">
                            <i class="ri-filter-line"></i> Filter
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnClearFilters">
                            <i class="ri-close-line"></i>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ======================================================= --}}
    {{-- MAIN DATATABLE --}}
    {{-- ======================================================= --}}
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="reviewTable" class="table table-sm table-hover mb-0 nowrap w-100">
                    <thead class="table-dark">
                        <tr>
                            <th>SKU</th>
                            <th>Product</th>
                            <th>Marketplace</th>
                            <th>Rating</th>
                            <th>Review</th>
                            <th>Sentiment</th>
                            <th>Issue</th>
                            <th>Supplier</th>
                            <th>Department</th>
                            <th>Date</th>
                            <th>Flag</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

</div><!-- /container-fluid -->

{{-- ======================================================= --}}
{{-- MODALS --}}
{{-- ======================================================= --}}

{{-- SKU Detail Modal --}}
<div class="modal fade" id="skuDetailModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="ri-barcode-box-line me-2"></i>SKU Detail: <span id="skuDetailTitle"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="skuDetailBody">
                <div class="text-center py-5"><div class="spinner-border text-primary"></div></div>
            </div>
        </div>
    </div>
</div>

{{-- Review Expand Modal --}}
<div class="modal fade" id="reviewExpandModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="ri-chat-3-line me-2"></i>Full Review</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="reviewExpandBody"></div>
            <div class="modal-footer">
                <button class="btn btn-sm btn-outline-primary" id="btnGenerateReply"><i class="ri-robot-2-line me-1"></i>Generate AI Reply</button>
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- Alerts Modal --}}
<div class="modal fade" id="alertsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="ri-alarm-warning-line me-2"></i>Active Alerts</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="alertsBody">
                <div class="text-center py-4"><div class="spinner-border text-danger"></div></div>
            </div>
        </div>
    </div>
</div>

{{-- AI Insights Modal --}}
<div class="modal fade" id="aiInsightsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title"><i class="ri-brain-line me-2"></i>AI Insights Panel</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="aiInsightsBody">
                <div class="text-center py-4"><div class="spinner-border text-dark"></div></div>
            </div>
        </div>
    </div>
</div>

{{-- Supplier Panel Modal --}}
<div class="modal fade" id="supplierModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="ri-user-star-line me-2"></i>Supplier Intelligence Panel</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="supplierBody">
                <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
            </div>
        </div>
    </div>
</div>

{{-- CSV Upload Modal --}}
<div class="modal fade" id="uploadCsvModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="ri-upload-cloud-2-line me-2"></i>Import Reviews via CSV</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info small">
                    <strong>Required CSV Columns:</strong><br>
                    <code>sku, marketplace, review_id, rating, review_title, review_text, reviewer_name, review_date</code>
                </div>
                <form id="csvUploadForm" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Select CSV File (max 10MB)</label>
                        <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv,.txt">
                    </div>
                    <div id="csvUploadMsg"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" id="btnUploadCsv"><i class="ri-upload-2-line me-1"></i>Upload & Process</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/moment/moment.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
// ============================================================
// CSRF + helpers
// ============================================================
const CSRF = '{{ csrf_token() }}';
let currentReviewId = null;

function sentimentBadge(val) {
    if (!val) return '<span class="review-badge badge-neutral">—</span>';
    const icons = { positive:'ri-thumb-up-fill', neutral:'ri-subtract-line', negative:'ri-thumb-down-fill' };
    return `<span class="review-badge badge-${val}"><i class="${icons[val] || ''} me-1"></i>${val}</span>`;
}

function issueBadge(val) {
    if (!val) return '—';
    const label = val.replace(/_/g,' ').replace(/\b\w/g,c=>c.toUpperCase());
    return `<span class="review-badge badge-${val}">${label}</span>`;
}

function starRating(n) {
    if (!n) return '—';
    return '<span class="star-rating">' + '★'.repeat(n) + '☆'.repeat(5-n) + `</span> <small class="text-muted">(${n})</small>`;
}

function marketplaceBadge(val) {
    const colors = { amazon:'primary', ebay:'warning', walmart:'info', temu:'success', shopify:'dark', csv:'secondary' };
    const c = colors[val] || 'secondary';
    return `<span class="badge bg-${c} bg-opacity-15 text-${c} text-capitalize">${val}</span>`;
}

// ============================================================
// DataTable
// ============================================================
let reviewTable;
let extraFilters = {};

$(function () {
    reviewTable = $('#reviewTable').DataTable({
        processing  : true,
        serverSide  : true,
        responsive  : true,
        pageLength  : 25,
        lengthMenu  : [[15,25,50,100],[15,25,50,100]],
        ajax: {
            url: '{{ route("reviews.data") }}',
            type: 'GET',
            data: function (d) {
                d.sku           = extraFilters.sku || '';
                d.supplier_id   = extraFilters.supplier_id || '';
                d.marketplace   = extraFilters.marketplace || '';
                d.rating        = extraFilters.rating || '';
                d.issue_category= extraFilters.issue || '';
                d.sentiment     = extraFilters.sentiment || '';
                d.date_from     = extraFilters.date_from || '';
                d.date_to       = extraFilters.date_to || '';
            }
        },
        columns: [
            { data: 'sku',           name: 'sku_reviews.sku',
              render: (v) => `<a href="javascript:void(0)" class="fw-semibold text-primary text-decoration-none sku-link" data-sku="${v}">${v}</a>` },
            { data: 'product_name',  name: 'product_master.title150',
              render: (v) => v ? `<span title="${v}" style="max-width:140px;display:inline-block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${v}</span>` : '—' },
            { data: 'marketplace',   name: 'sku_reviews.marketplace', render: marketplaceBadge },
            { data: 'rating',        name: 'sku_reviews.rating',  render: starRating },
            { data: 'review_title',  name: 'sku_reviews.review_title',
              render: (v, t, row) => {
                const txt = v || row.review_text || '—';
                const truncated = txt.length > 50 ? txt.substring(0,50)+'…' : txt;
                return `<span class="review-text-preview text-decoration-underline review-expand-btn" data-id="${row.id}" title="Click to expand">${truncated}</span>`;
              }
            },
            { data: 'sentiment',     name: 'sku_reviews.sentiment', render: sentimentBadge },
            { data: 'issue_category',name: 'sku_reviews.issue_category', render: issueBadge },
            { data: 'supplier_name', name: 'suppliers.name',
              render: (v) => v || '<span class="text-muted">—</span>' },
            { data: 'department',    name: 'sku_reviews.department',
              render: (v) => v ? `<small class="text-info">${v}</small>` : '—' },
            { data: 'review_date',   name: 'sku_reviews.review_date',
              render: (v) => v ? `<small>${v}</small>` : '—' },
            { data: 'is_flagged',    orderable: false,
              render: (v) => v ? '<i class="ri-error-warning-fill text-danger flag-icon fs-5" title="Critical"></i>' : '' },
            { data: null, orderable: false, searchable: false,
              render: (v, t, row) => `
                <div class="d-flex gap-1">
                  <button class="btn btn-xs btn-outline-secondary py-0 px-1 review-expand-btn" data-id="${row.id}" title="View"><i class="ri-eye-line"></i></button>
                  <button class="btn btn-xs btn-outline-primary py-0 px-1 sku-link" data-sku="${row.sku}" title="SKU Detail"><i class="ri-bar-chart-2-line"></i></button>
                </div>` }
        ],
        rowCallback: function(row, data) {
            if (data.sentiment === 'negative') {
                $(row).addClass('table-row-negative');
            }
        },
        language: {
            processing: '<div class="spinner-border spinner-border-sm text-primary" role="status"></div>',
        },
        order: [[9, 'desc']],
    });

    // ---- Filters ----
    $('#btnApplyFilters').on('click', applyFilters);
    $('#btnClearFilters').on('click', clearFilters);
    $('#filter_sku').on('keypress', function(e){ if(e.which===13) applyFilters(); });

    function applyFilters() {
        const drp = $('#filter_daterange').data('daterangepicker');
        extraFilters = {
            sku         : $('#filter_sku').val().trim(),
            supplier_id : $('#filter_supplier').val(),
            marketplace : $('#filter_marketplace').val(),
            rating      : $('#filter_rating').val(),
            issue       : $('#filter_issue').val(),
            sentiment   : $('#filter_sentiment').val(),
            date_from   : drp && $('#filter_daterange').val() ? drp.startDate.format('YYYY-MM-DD') : '',
            date_to     : drp && $('#filter_daterange').val() ? drp.endDate.format('YYYY-MM-DD') : '',
        };
        reviewTable.ajax.reload();
    }

    function clearFilters() {
        $('#filter_sku,#filter_daterange').val('');
        $('#filter_supplier,#filter_marketplace,#filter_rating,#filter_issue,#filter_sentiment').val('');
        extraFilters = {};
        reviewTable.ajax.reload();
    }

    // ---- Date range picker ----
    $('#filter_daterange').daterangepicker({
        autoUpdateInput: false,
        locale: { cancelLabel: 'Clear', format: 'YYYY-MM-DD' }
    });
    $('#filter_daterange').on('apply.daterangepicker', function(ev, picker) {
        $(this).val(picker.startDate.format('YYYY-MM-DD') + ' ~ ' + picker.endDate.format('YYYY-MM-DD'));
    });
    $('#filter_daterange').on('cancel.daterangepicker', function() {
        $(this).val('');
    });

    // ---- SKU Detail click ----
    $(document).on('click', '.sku-link', function() {
        const sku = $(this).data('sku');
        openSkuDetail(sku);
    });

    // ---- Review expand click ----
    $(document).on('click', '.review-expand-btn', function() {
        const id = $(this).data('id');
        openReviewExpand(id);
    });

    // ---- Refresh summary ----
    $('#btnRefreshSummary').on('click', function() {
        $(this).html('<i class="ri-loader-4-line ri-spin me-1"></i>Refreshing...');
        $.post('{{ route("reviews.refresh-summary") }}', {_token: CSRF})
            .done(() => {
                showToast('Summary refreshed!', 'success');
                reviewTable.ajax.reload();
            })
            .fail(() => showToast('Refresh failed', 'danger'))
            .always(() => $(this).html('<i class="ri-refresh-line me-1"></i>Refresh'));
    });

    // ---- Alerts modal ----
    $('#alertsModal').on('show.bs.modal', loadAlerts);

    // ---- AI Insights modal ----
    $('#aiInsightsModal').on('show.bs.modal', loadAiInsights);

    // ---- Supplier Panel modal ----
    $('#supplierModal').on('show.bs.modal', loadSupplierPanel);

    // ---- CSV Upload ----
    $('#btnUploadCsv').on('click', function() {
        const form = new FormData($('#csvUploadForm')[0]);
        const $btn = $(this).html('<i class="ri-loader-4-line ri-spin"></i> Uploading...').prop('disabled', true);

        $.ajax({
            url: '{{ route("reviews.upload-csv") }}',
            method: 'POST',
            data: form,
            processData: false,
            contentType: false,
        }).done(res => {
            $('#csvUploadMsg').html('<div class="alert alert-success small">' + res.message + '</div>');
        }).fail(err => {
            const msg = err.responseJSON?.message || 'Upload failed';
            $('#csvUploadMsg').html('<div class="alert alert-danger small">' + msg + '</div>');
        }).always(() => {
            $btn.html('<i class="ri-upload-2-line me-1"></i>Upload & Process').prop('disabled', false);
        });
    });

    // ---- Generate Reply ----
    $('#btnGenerateReply').on('click', function() {
        if (!currentReviewId) return;
        const $btn = $(this).html('<i class="ri-loader-4-line ri-spin"></i> Generating...').prop('disabled', true);

        $.post(`/reviews/${currentReviewId}/generate-reply`, {_token: CSRF})
            .done(res => {
                const existing = $('#reviewExpandBody .ai-reply-section');
                if (existing.length) {
                    existing.find('.reply-text').text(res.reply);
                } else {
                    $('#reviewExpandBody').append(`
                        <div class="ai-reply-section mt-3 p-3 bg-light rounded border">
                            <strong><i class="ri-robot-2-line me-1 text-primary"></i>AI Suggested Reply:</strong>
                            <p class="reply-text mt-2 mb-0">${res.reply}</p>
                        </div>`);
                }
            })
            .fail(() => showToast('Failed to generate reply', 'danger'))
            .always(() => $btn.html('<i class="ri-robot-2-line me-1"></i>Generate AI Reply').prop('disabled', false));
    });
});

// ============================================================
// SKU Detail Modal
// ============================================================
function openSkuDetail(sku) {
    $('#skuDetailTitle').text(sku);
    $('#skuDetailBody').html('<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>');
    $('#skuDetailModal').modal('show');

    $.get(`/reviews/sku/${sku}/detail`)
        .done(data => renderSkuDetail(data))
        .fail(() => $('#skuDetailBody').html('<div class="alert alert-danger">Failed to load SKU data.</div>'));
}

function renderSkuDetail(data) {
    const s = data.summary;
    const negRate = s ? s.negative_rate : 0;
    const negColor = negRate > 30 ? 'danger' : negRate > 15 ? 'warning' : 'success';

    let summaryHtml = s ? `
        <div class="row g-3 mb-4">
            <div class="col-4"><div class="text-center"><div class="fs-4 fw-bold">${s.total_reviews}</div><small class="text-muted">Total Reviews</small></div></div>
            <div class="col-4"><div class="text-center"><div class="fs-4 fw-bold text-${negColor}">${negRate}%</div><small class="text-muted">Negative</small></div></div>
            <div class="col-4"><div class="text-center"><div class="fs-4 fw-bold text-warning">${s.avg_rating || '—'}</div><small class="text-muted">Avg Rating</small></div></div>
        </div>` : '<div class="alert alert-info small">No summary data yet. Run analysis first.</div>';

    // Issue distribution chart
    const issueLabels = data.issue_distribution.map(x => x.issue_category.replace(/_/g,' '));
    const issueCounts = data.issue_distribution.map(x => x.cnt);
    const issueColors = ['#dc3545','#ffc107','#0dcaf0','#6c757d','#212529','#0d6efd','#adb5bd'];

    // Rating trend chart
    const trendLabels = data.trend.map(x => 'W'+String(x.week).slice(-2));
    const trendData   = data.trend.map(x => parseFloat(x.avg_rating).toFixed(1));

    // Top complaints
    const complaintsHtml = data.top_complaints.length
        ? data.top_complaints.map(c => `<li class="list-group-item list-group-item-action py-1 small">${c}</li>`).join('')
        : '<li class="list-group-item small text-muted">No complaints yet.</li>';

    $('#skuDetailBody').html(`
        ${summaryHtml}
        <div class="row g-3">
            <div class="col-md-5">
                <div class="card"><div class="card-header py-2 small fw-semibold">Issue Distribution</div>
                <div class="card-body p-2"><div class="chart-container"><canvas id="issueChart"></canvas></div></div></div>
            </div>
            <div class="col-md-7">
                <div class="card"><div class="card-header py-2 small fw-semibold">Rating Trend (Last 12 Weeks)</div>
                <div class="card-body p-2"><div class="chart-container"><canvas id="trendChart"></canvas></div></div></div>
            </div>
            <div class="col-12">
                <div class="card"><div class="card-header py-2 small fw-semibold">Top Complaints</div>
                <div class="card-body p-0"><ul class="list-group list-group-flush">${complaintsHtml}</ul></div></div>
            </div>
        </div>
    `);

    if (issueCounts.length) {
        new Chart(document.getElementById('issueChart'), {
            type: 'doughnut',
            data: { labels: issueLabels, datasets: [{ data: issueCounts, backgroundColor: issueColors }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right', labels: { font: { size: 11 } } } } }
        });
    }

    if (trendData.length) {
        new Chart(document.getElementById('trendChart'), {
            type: 'line',
            data: {
                labels: trendLabels,
                datasets: [{ label: 'Avg Rating', data: trendData, borderColor: '#0d6efd', backgroundColor: 'rgba(13,110,253,.1)', tension: 0.3, fill: true }]
            },
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { min: 1, max: 5 } } }
        });
    }
}

// ============================================================
// Review Expand Modal
// ============================================================
function openReviewExpand(id) {
    currentReviewId = id;
    $('#reviewExpandBody').html('<div class="text-center py-3"><div class="spinner-border text-secondary"></div></div>');
    $('#reviewExpandModal').modal('show');

    // Find row data from DataTable
    const allData = reviewTable.rows().data().toArray();
    const row = allData.find(r => r.id == id);

    if (row) {
        renderReviewExpand(row);
    } else {
        $('#reviewExpandBody').html('<div class="alert alert-warning">Review data not found.</div>');
    }
}

function renderReviewExpand(row) {
    $('#reviewExpandBody').html(`
        <div class="row g-3">
            <div class="col-md-8">
                <h6 class="fw-bold">${row.review_title || 'No Title'}</h6>
                <p class="mb-2">${row.review_text || '—'}</p>
                ${row.ai_summary ? `<div class="p-2 bg-light rounded border small"><i class="ri-brain-line me-1 text-primary"></i><strong>AI Summary:</strong> ${row.ai_summary}</div>` : ''}
                ${row.ai_reply ? `<div class="ai-reply-section mt-2 p-2 bg-light rounded border small"><i class="ri-robot-2-line me-1 text-primary"></i><strong>AI Reply:</strong><p class="reply-text mb-0 mt-1">${row.ai_reply}</p></div>` : ''}
            </div>
            <div class="col-md-4">
                <table class="table table-sm table-borderless small">
                    <tr><td class="text-muted">Reviewer</td><td>${row.reviewer_name || '—'}</td></tr>
                    <tr><td class="text-muted">Date</td><td>${row.review_date || '—'}</td></tr>
                    <tr><td class="text-muted">Rating</td><td>${starRating(row.rating)}</td></tr>
                    <tr><td class="text-muted">Sentiment</td><td>${sentimentBadge(row.sentiment)}</td></tr>
                    <tr><td class="text-muted">Issue</td><td>${issueBadge(row.issue_category)}</td></tr>
                    <tr><td class="text-muted">Department</td><td><small class="text-info">${row.department || '—'}</small></td></tr>
                    <tr><td class="text-muted">Supplier</td><td>${row.supplier_name || '—'}</td></tr>
                    <tr><td class="text-muted">Source</td><td><span class="badge bg-secondary">${row.source_type}</span></td></tr>
                    <tr><td class="text-muted">Flagged</td><td>${row.is_flagged ? '<i class="ri-error-warning-fill text-danger"></i> Yes' : 'No'}</td></tr>
                </table>
            </div>
        </div>
    `);
}

// ============================================================
// Alerts Modal
// ============================================================
function loadAlerts() {
    $('#alertsBody').html('<div class="text-center py-4"><div class="spinner-border text-danger"></div></div>');
    $.get('{{ route("reviews.alerts") }}').done(data => {
        if (!data.length) {
            $('#alertsBody').html('<div class="alert alert-success text-center"><i class="ri-checkbox-circle-line me-2"></i>No open alerts.</div>');
            return;
        }
        const icons = { high_negative_rate:'ri-percent-line', top_issue:'ri-list-unordered', spike_detected:'ri-line-chart-line' };
        const colors = { high_negative_rate:'danger', top_issue:'warning', spike_detected:'info' };
        let html = '<div class="list-group list-group-flush">';
        data.forEach(a => {
            const ic = colors[a.alert_type] || 'secondary';
            html += `
                <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-start py-2">
                    <div>
                        <span class="badge bg-${ic} me-2">${a.alert_type_label || a.alert_type.replace(/_/g,' ')}</span>
                        <strong class="me-1">${a.sku}</strong>
                        <span class="text-muted small">${a.message}</span>
                    </div>
                    <button class="btn btn-xs btn-outline-success py-0 px-2 btn-resolve-alert" data-id="${a.id}">
                        <i class="ri-check-line"></i> Resolve
                    </button>
                </div>`;
        });
        html += '</div>';
        $('#alertsBody').html(html);
    }).fail(() => $('#alertsBody').html('<div class="alert alert-danger">Failed to load alerts.</div>'));
}

$(document).on('click', '.btn-resolve-alert', function() {
    const id = $(this).data('id');
    const $btn = $(this).html('<i class="ri-loader-4-line ri-spin"></i>').prop('disabled', true);
    $.post(`/reviews/alerts/${id}/resolve`, {_token: CSRF})
        .done(() => { $(this).closest('.list-group-item').fadeOut(300); showToast('Alert resolved', 'success'); })
        .fail(() => $btn.html('<i class="ri-check-line"></i> Resolve').prop('disabled', false));
});

// ============================================================
// AI Insights Modal
// ============================================================
function loadAiInsights() {
    $('#aiInsightsBody').html('<div class="text-center py-4"><div class="spinner-border text-dark"></div></div>');
    $.get('{{ route("reviews.ai-insights") }}').done(data => {
        let topProblemsHtml = data.top_problems.length
            ? data.top_problems.map((p,i) => `
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div><span class="badge bg-secondary me-2">#${i+1}</span>${p.issue_category.replace(/_/g,' ').toUpperCase()}</div>
                    <div><span class="badge bg-danger">${p.cnt}</span></div>
                </div>
                <div class="progress mb-3" style="height:6px"><div class="progress-bar bg-danger" style="width:${Math.min(100,p.cnt)}%"></div></div>`).join('')
            : '<p class="text-muted small">No data this week.</p>';

        $('#aiInsightsBody').html(`
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="card insights-card danger h-100">
                        <div class="card-header py-2 small fw-semibold"><i class="ri-list-unordered me-1"></i>Top 5 Problems This Week</div>
                        <div class="card-body">${topProblemsHtml}</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card insights-card warning h-100">
                        <div class="card-header py-2 small fw-semibold"><i class="ri-user-star-line me-1"></i>Most Complained Supplier</div>
                        <div class="card-body">
                            <div class="text-center py-3">
                                <div class="fs-3 fw-bold text-warning">${data.most_complained_supplier?.name || 'N/A'}</div>
                                <small class="text-muted">${data.most_complained_supplier?.cnt || 0} negative reviews this week</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card insights-card info h-100">
                        <div class="card-header py-2 small fw-semibold"><i class="ri-barcode-box-line me-1"></i>Most Problematic SKU</div>
                        <div class="card-body">
                            <div class="text-center py-3">
                                <div class="fs-3 fw-bold text-info">${data.most_problematic_sku?.sku || 'N/A'}</div>
                                <small class="text-muted">${data.most_problematic_sku?.cnt || 0} negative reviews this week</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card insights-card info h-100">
                        <div class="card-header py-2 small fw-semibold"><i class="ri-line-chart-line me-1"></i>Issue Trend (6 Weeks)</div>
                        <div class="card-body p-2"><div class="chart-container"><canvas id="issueTrendChart"></canvas></div></div>
                    </div>
                </div>
            </div>
        `);

        renderIssueTrendChart(data.issue_trend);
    }).fail(() => $('#aiInsightsBody').html('<div class="alert alert-danger">Failed to load insights.</div>'));
}

function renderIssueTrendChart(trendObj) {
    const weeks = Object.keys(trendObj);
    if (!weeks.length) return;

    const allIssues = [...new Set(weeks.flatMap(w => trendObj[w].map(x => x.issue_category)))];
    const issueColorMap = {
        quality:'#dc3545', packaging:'#ffc107', shipping:'#0dcaf0',
        service:'#6c757d', wrong_item:'#212529', missing_parts:'#0d6efd', other:'#adb5bd'
    };

    const datasets = allIssues.map(issue => ({
        label: issue.replace(/_/g, ' '),
        data: weeks.map(w => {
            const item = (trendObj[w] || []).find(x => x.issue_category === issue);
            return item ? item.cnt : 0;
        }),
        borderColor: issueColorMap[issue] || '#adb5bd',
        backgroundColor: (issueColorMap[issue] || '#adb5bd') + '22',
        tension: 0.3, fill: false,
    }));

    new Chart(document.getElementById('issueTrendChart'), {
        type: 'line',
        data: { labels: weeks.map(w => 'W' + String(w).slice(-2)), datasets },
        options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } }, plugins: { legend: { position: 'bottom', labels: { font: { size: 10 } } } } }
    });
}

// ============================================================
// Supplier Panel Modal
// ============================================================
function loadSupplierPanel() {
    $('#supplierBody').html('<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>');
    $.get('{{ route("reviews.supplier-intelligence") }}').done(data => {
        if (!data.length) {
            $('#supplierBody').html('<div class="alert alert-info">No supplier data yet.</div>');
            return;
        }
        let rows = data.map(s => {
            const neg = parseFloat(s.neg_rate || 0);
            const color = neg > 30 ? 'danger' : neg > 15 ? 'warning' : 'success';
            const topIssue = getTopIssue(s);
            return `<tr class="supplier-row">
                <td class="fw-semibold">${s.supplier_name || '—'}</td>
                <td>${s.total || 0}</td>
                <td><span class="fw-bold text-${color}">${neg}%</span></td>
                <td>${topIssue}</td>
                <td>${s.sku_count || 0}</td>
                <td>
                    <div class="progress" style="height:8px;width:80px">
                        <div class="progress-bar bg-${color}" style="width:${Math.min(100,neg)}%"></div>
                    </div>
                </td>
            </tr>`;
        }).join('');

        $('#supplierBody').html(`
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Supplier</th><th>Total Reviews</th><th>Negative %</th><th>Top Issue</th><th>SKUs Affected</th><th>Rate</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>
        `);
    }).fail(() => $('#supplierBody').html('<div class="alert alert-danger">Failed to load data.</div>'));
}

function getTopIssue(s) {
    const issues = {
        quality: parseInt(s.quality||0), packaging: parseInt(s.packaging||0),
        shipping: parseInt(s.shipping||0), service: parseInt(s.service||0)
    };
    const top = Object.keys(issues).reduce((a,b) => issues[a] > issues[b] ? a : b);
    return issues[top] > 0 ? issueBadge(top) : '—';
}

// ============================================================
// Utility: Toast
// ============================================================
function showToast(msg, type = 'success') {
    const id = 'toast_' + Date.now();
    const color = { success:'bg-success', danger:'bg-danger', warning:'bg-warning', info:'bg-info' }[type] || 'bg-primary';
    $('body').append(`
        <div id="${id}" class="toast align-items-center text-white ${color} border-0 position-fixed bottom-0 end-0 m-3" role="alert" style="z-index:9999">
            <div class="d-flex"><div class="toast-body">${msg}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>
        </div>`);
    const el = document.getElementById(id);
    new bootstrap.Toast(el, { delay: 3500 }).show();
    el.addEventListener('hidden.bs.toast', () => el.remove());
}

function starRating(n) {
    if (!n) return '—';
    n = parseInt(n);
    return '<span class="star-rating">' + '★'.repeat(n) + '<span class="text-muted">' + '☆'.repeat(5-n) + `</span></span> <small class="text-muted">(${n})</small>`;
}
</script>
@endsection
