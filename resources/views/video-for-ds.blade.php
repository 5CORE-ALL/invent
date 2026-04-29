@extends('layouts.vertical', ['title' => 'FB Video Ads', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    @vite(['node_modules/admin-resources/rwd-table/rwd-table.min.css'])
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet" />
    <link href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css" rel="stylesheet" />

    <style>
        .table-wrapper {
            position: relative;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            max-height: calc(100vh - 200px);
            overflow-y: auto;
            overflow-x: auto;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            background: #fff;
        }

        .table-wrapper table {
            margin-bottom: 0;
            width: 100% !important;
            table-layout: fixed;
        }

        .table-wrapper thead th {
            position: sticky;
            top: 0;
            background: linear-gradient(135deg, #2c6ed5 0%, #1a56b7 100%) !important;
            color: #fff;
            z-index: 10;
            padding: 8px 5px 6px;
            font-weight: 600;
            border-bottom: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            font-size: 10px;
            text-align: center;
            letter-spacing: 0.3px;
            text-transform: uppercase;
            white-space: nowrap;
            vertical-align: top;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .table-wrapper thead th:hover {
            background: linear-gradient(135deg, #1a56b7 0%, #0a3d8f 100%) !important;
        }

        .th-filter input,
        .th-filter select {
            background: rgba(255,255,255,0.92);
            border: none;
            border-radius: 3px;
            color: #333;
            padding: 3px 4px;
            margin-top: 4px;
            font-size: 10px;
            width: 100%;
        }

        .th-filter input:focus,
        .th-filter select:focus {
            background: #fff;
            box-shadow: 0 0 0 2px rgba(26,86,183,0.3);
            outline: none;
        }

        .table-wrapper tbody td {
            padding: 6px 5px;
            vertical-align: middle;
            text-align: center;
            border-bottom: 1px solid #edf2f9;
            font-size: 11px;
            color: #495057;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .table-wrapper tbody tr:nth-child(even) { background: #f8fafc; }
        .table-wrapper tbody tr:hover           { background: #e8f0fe; }

        .badge-count {
            font-size: 10px;
            font-weight: 700;
            color: #ff6b6b;
            vertical-align: middle;
        }

        .missing-label {
            font-size: 10px;
            color: rgba(255,255,255,0.6);
            display: block;
            margin-top: 2px;
        }

        .action-btn {
            padding: 3px 7px;
            border: none;
            border-radius: 4px;
            font-size: 11px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .edit-btn {
            background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
            color: #fff;
        }

        .edit-btn:hover  { transform: translateY(-1px); box-shadow: 0 2px 8px rgba(255,193,7,.35); }

        .del-btn {
            background: linear-gradient(135deg, #ff4d4d 0%, #c0392b 100%);
            color: #fff;
        }

        .del-btn:hover { transform: translateY(-1px); box-shadow: 0 2px 8px rgba(255,77,77,.35); }

        #loader {
            display: none;
            text-align: center;
            padding: 40px;
        }

        .modal-header-gradient {
            background: linear-gradient(135deg, #6B73FF 0%, #000DFF 100%);
            border-bottom: 4px solid #4D55E6;
            color: #fff;
        }

        .video-link-icon {
            color: #2c6ed5;
            font-size: 19px;
            text-decoration: none;
            transition: all .3s ease;
            display: inline-block;
        }

        .video-link-icon:hover { color: #1a56b7; transform: scale(1.2); }

        .cell-text {
            max-width: 130px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            display: inline-block;
            font-size: 12px;
            vertical-align: middle;
        }

        .data-dot {
            display: inline-block;
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: #28a745;
            cursor: pointer;
            box-shadow: 0 0 0 2px rgba(40,167,69,0.25);
            transition: transform 0.15s ease, box-shadow 0.15s ease;
            vertical-align: middle;
        }

        .data-dot:hover {
            transform: scale(1.4);
            box-shadow: 0 0 0 4px rgba(40,167,69,0.2);
        }

        /* Status dropdown */
        .status-select {
            border: none;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
            padding: 3px 8px;
            cursor: pointer;
            outline: none;
            appearance: none;
            -webkit-appearance: none;
            text-align: center;
            width: 100%;
        }
        .status-select:focus { box-shadow: none; }
        .ss-Todo           { background:#cff4fc; color:#055160; }
        .ss-Working        { background:#fff3cd; color:#856404; }
        .ss-Archived       { background:#e2e3e5; color:#41464b; }
        .ss-Done           { background:#d4edda; color:#155724; }
        .ss-Need_Help      { background:#ffe5d0; color:#984c0c; }
        .ss-Need_Approval  { background:#e0cffc; color:#432874; }
        .ss-Dependent      { background:#f7d6e6; color:#ab296a; }
        .ss-Approved       { background:#d1f4e0; color:#146c43; }
        .ss-Hold           { background:#dee2e6; color:#212529; }
        .ss-Cancelled      { background:#f8d7da; color:#721c24; }

        /* Approval toggle dots */
        .appr-dot {
            display: inline-block;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            cursor: pointer;
            transition: background 0.2s, transform 0.15s, box-shadow 0.2s;
            border: 2px solid rgba(0,0,0,0.1);
        }
        .appr-dot.off { background: #dc3545; }
        .appr-dot.on  { background: #28a745; }
        .appr-dot:not(.appr-summary):hover { transform: scale(1.35); box-shadow: 0 0 0 3px rgba(0,0,0,0.12); }
        .appr-summary { width: 16px !important; height: 16px !important; cursor: default !important; }

        .lang-toggle {
            line-height: 1.2;
            border-radius: 6px !important;
            transition: all 0.15s;
        }
        .lang-toggle.btn-primary { box-shadow: 0 0 0 2px rgba(26,86,183,0.35); }

        .sku-badge {
            background: #e8f0fe;
            color: #1a56b7;
            font-weight: 600;
            padding: 2px 5px;
            border-radius: 8px;
            font-size: 10px;
            white-space: nowrap;
            display: inline-block;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        #total-count-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: linear-gradient(135deg, #2c6ed5, #1a56b7);
            color: #fff;
            font-size: 12px;
            font-weight: 600;
            padding: 5px 14px;
            border-radius: 20px;
            box-shadow: 0 2px 8px rgba(26,86,183,0.25);
            letter-spacing: 0.3px;
        }
        #total-count-badge .count-num {
            background: rgba(255,255,255,0.25);
            border-radius: 12px;
            padding: 1px 8px;
            font-size: 13px;
            font-weight: 700;
        }

        /* ── FB Insights ─────────────────────────────────────── */
        .fb-th {
            background: linear-gradient(135deg, #1877f2 0%, #0d5fd4 100%) !important;
        }
        .fb-th:hover {
            background: linear-gradient(135deg, #0d5fd4 0%, #0a4fbb 100%) !important;
        }
        .fb-section-header {
            background: linear-gradient(135deg, #1877f2 0%, #0d5fd4 100%) !important;
            text-align: center;
            font-weight: 700;
            letter-spacing: 0.5px;
            font-size: 9px;
            padding: 3px 4px !important;
            color: #fff;
            border-bottom: 2px solid rgba(255,255,255,0.3);
        }
        .fb-cell {
            font-size: 10px;
            font-weight: 600;
            color: #1877f2;
            background: rgba(24,119,242,0.04);
        }
        .fb-cell.no-data { color: #adb5bd; font-weight: 400; }
        .fb-cell-loading {
            font-size: 10px;
            color: #adb5bd;
            font-style: italic;
        }
        .fb-sync-btn {
            background: #1877f2;
            color: #fff;
            border: none;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            padding: 5px 14px;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .fb-sync-btn:hover { background: #0d5fd4; transform: translateY(-1px); }
        .fb-sync-btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
        #fb-sync-info {
            font-size: 10px;
            color: #6c757d;
            margin-left: 4px;
        }
        .fb-push-btn {
            background: #fff;
            color: #1877f2;
            border: 2px solid #1877f2;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            padding: 4px 12px;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .fb-push-btn:hover { background: #1877f2; color: #fff; }
        .fb-push-btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .sync-progress-bar {
            height: 6px;
            border-radius: 3px;
            background: #e9ecef;
            overflow: hidden;
            margin-top: 8px;
        }
        .sync-progress-bar .bar {
            height: 100%;
            background: linear-gradient(90deg, #1877f2, #42b3ff);
            border-radius: 3px;
            transition: width 0.4s ease;
        }
        .sync-progress-bar .bar.indeterminate {
            width: 40%;
            animation: slide 1.2s ease-in-out infinite;
        }
        @keyframes slide {
            0%   { margin-left: -40%; }
            100% { margin-left: 100%; }
        }
        .sync-log-entry {
            font-size: 11px;
            padding: 3px 0;
            border-bottom: 1px dashed #dee2e6;
            color: #495057;
        }
        .sync-log-entry.success { color: #198754; }
        .sync-log-entry.error   { color: #dc3545; }
        .sync-log-entry.info    { color: #0dcaf0; }
        .table-wrapper table {
            min-width: 1500px;
        }

        /* ── Campaigns DataTable ──────────────────────────────── */
        .campaigns-section {
            margin-top: 28px;
        }
        .campaigns-section-header {
            background: linear-gradient(135deg, #1877f2 0%, #0d5fd4 100%);
            color: #fff;
            padding: 10px 18px;
            border-radius: 8px 8px 0 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
        }
        .campaigns-section-header h6 { margin: 0; font-weight: 700; font-size: 13px; }
        #campaignsPeriodLabel {
            font-size: 11px;
            opacity: 0.85;
            background: rgba(255,255,255,0.15);
            border-radius: 12px;
            padding: 2px 10px;
        }
        #campaignsTable thead th {
            background: #f0f4ff;
            color: #1877f2;
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
            text-align: center;
        }
        #campaignsTable tbody td {
            font-size: 11px;
            vertical-align: middle;
            text-align: center;
        }
        #campaignsTable tbody tr:hover { background: #f0f4ff !important; }
        .cstatus-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .cstatus-ACTIVE        { background:#d4edda; color:#155724; }
        .cstatus-PAUSED        { background:#fff3cd; color:#856404; }
        .cstatus-ARCHIVED      { background:#e2e3e5; color:#41464b; }
        .cstatus-DELETED       { background:#f8d7da; color:#721c24; }
        .cstatus-IN_PROCESS    { background:#cff4fc; color:#055160; }
        .cstatus-WITH_ISSUES   { background:#ffe5d0; color:#984c0c; }
        .cstatus-CAMPAIGN_PAUSED { background:#fff3cd; color:#856404; }
        .fb-metric { color: #1877f2; font-weight: 600; }
        .fb-metric-zero { color: #adb5bd; }
        #campaignsTable_wrapper .dataTables_filter input {
            border: 1px solid #ced4da;
            border-radius: 6px;
            padding: 4px 10px;
            font-size: 12px;
        }
        #campaignsTable_wrapper .dataTables_length select {
            border: 1px solid #ced4da;
            border-radius: 6px;
            padding: 3px 8px;
            font-size: 12px;
        }
    </style>
@endsection

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('layouts.shared/page-title', [
        'page_title' => 'FB Video Ads',
        'sub_title'  => 'Manage Product Ad Videos',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">

                    {{-- Toolbar --}}
                    <div class="mb-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="d-flex gap-2 flex-wrap align-items-center">
                            <button id="addBtn" class="btn btn-success btn-sm">
                                <i class="fas fa-plus"></i> Add Video
                            </button>
                            <button id="exportBtn" class="btn btn-primary btn-sm">
                                <i class="fas fa-download"></i> Export
                            </button>
                            <button id="importBtn" class="btn btn-sm" style="background:#17a2b8;color:#fff;">
                                <i class="fas fa-upload"></i> Import
                            </button>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-warning dropdown-toggle" id="bulkBtn" data-bs-toggle="dropdown">
                                    <i class="fas fa-layer-group"></i> Bulk
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="#" id="bulkDuplicate"><i class="fas fa-copy me-2"></i>Duplicate selected</a></li>
                                </ul>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <button id="fbSyncBtn" class="fb-sync-btn" title="Refresh Facebook Insights from cached data">
                                <i class="fab fa-facebook-f"></i> FB Insights
                            </button>
                            <button id="fbPushSyncBtn" class="fb-push-btn" title="Push a new sync from Facebook API">
                                <i class="fas fa-cloud-download-alt"></i> Push Sync
                            </button>
                            <span id="fb-sync-info">Not loaded</span>
                        </div>
                        <span id="total-count-badge">
                            <i class="fas fa-video"></i>
                            Total Videos:
                            <span class="count-num" id="total-count">0</span>
                        </span>
                    </div>

                    {{-- Table --}}
                    <div class="table-wrapper">
                        <table class="table w-100" id="ads-table">
                            <thead>
                                {{-- Row 1: group labels --}}
                                <tr>
                                    <th colspan="9" style="text-align:center;background:linear-gradient(135deg,#2c6ed5,#1a56b7) !important;font-size:9px;padding:3px;letter-spacing:0.5px;">
                                        VIDEO INFO
                                    </th>
                                    <th colspan="12" class="fb-section-header" id="fb-header-label">
                                        <i class="fab fa-facebook-f me-1"></i> FACEBOOK INSIGHTS — LAST 30 DAYS
                                    </th>
                                    <th style="text-align:center;background:linear-gradient(135deg,#2c6ed5,#1a56b7) !important;font-size:9px;padding:3px;">
                                        ACT
                                    </th>
                                </tr>
                                {{-- Row 2: column names + filters --}}
                                <tr>
                                    <th style="width:2%;text-align:center;">
                                        <input type="checkbox" id="selectAll" title="Select all" style="cursor:pointer;width:13px;height:13px;">
                                    </th>
                                    <th style="width:2%;">#</th>
                                    <th style="width:4%;">Video</th>
                                    <th style="width:6%;">
                                        Category
                                        <div class="th-filter">
                                            <select id="parentSearch">
                                                <option value="all">All</option>
                                                <option value="category">Category</option>
                                                <option value="parents">Parents</option>
                                                <option value="group">Group</option>
                                                <option value="sku">SKU</option>
                                            </select>
                                        </div>
                                    </th>
                                    <th style="width:6%;">
                                        Target
                                        <div class="th-filter">
                                            <input type="text" id="skuSearch" placeholder="Search">
                                        </div>
                                    </th>
                                    <th style="width:5%;">Topic</th>
                                    <th style="width:6%;">
                                        Audience
                                        <div class="th-filter">
                                            <select id="filter_ads_audience">
                                                <option value="all">All</option>
                                            </select>
                                        </div>
                                    </th>
                                    <th style="width:4%;">Lang</th>
                                    <th style="width:5%;">Link</th>
                                    {{-- Facebook Insight columns --}}
                                    <th class="fb-th" style="width:5%;" title="Matched ad name(s)">Ad Name</th>
                                    <th class="fb-th" style="width:4%;" title="Total Impressions">Impr.</th>
                                    <th class="fb-th" style="width:4%;" title="Total Reach">Reach</th>
                                    <th class="fb-th" style="width:3%;" title="Total Clicks">Clicks</th>
                                    <th class="fb-th" style="width:4%;" title="Total Spend (USD)">Spend</th>
                                    <th class="fb-th" style="width:3%;" title="Click-through Rate (%)">CTR%</th>
                                    <th class="fb-th" style="width:4%;" title="Cost per 1000 Impressions">CPM</th>
                                    <th class="fb-th" style="width:3%;" title="Average Frequency">Freq.</th>
                                    <th class="fb-th" style="width:3%;" title="Video ThruPlay Views">Views</th>
                                    <th class="fb-th" style="width:3%;" title="Results (Purchases)">Results</th>
                                    <th class="fb-th" style="width:4%;" title="Cost per Result">$/Res.</th>
                                    <th class="fb-th" style="width:4%;" title="Average Video Watch Time (seconds)">Watch</th>
                                    <th style="width:5%;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="table-body"></tbody>
                        </table>
                    </div>

                    <div id="loader">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <div class="mt-2 fw-bold text-primary">Loading...</div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    {{-- ── Campaigns DataTable ─────────────────────────────────────── --}}
    <div class="row">
        <div class="col-12">
            <div class="card mt-3">
                <div class="campaigns-section-header">
                    <h6><i class="fab fa-facebook-f me-2"></i>All Facebook Campaigns</h6>
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <span id="campaignsPeriodLabel">Loading…</span>
                        <span id="campaignsTotalBadge" class="badge bg-white text-primary fw-bold" style="font-size:11px;"></span>
                        <button id="refreshCampaignsBtn" class="btn btn-sm btn-light fw-semibold" style="font-size:11px;padding:3px 12px;">
                            <i class="fas fa-sync-alt me-1"></i>Refresh
                        </button>
                    </div>
                </div>
                <div class="card-body p-0" style="border-radius:0 0 8px 8px;overflow:hidden;">
                    <div id="campaignsLoader" class="text-center py-4">
                        <div class="spinner-border spinner-border-sm text-primary"></div>
                        <span class="ms-2 text-muted" style="font-size:12px;">Loading campaigns…</span>
                    </div>
                    <div id="campaignsTableWrap" class="d-none p-3">
                        <table id="campaignsTable" class="table table-sm table-bordered w-100" style="font-size:11px;">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Campaign Name</th>
                                    <th>Account</th>
                                    <th>Status</th>
                                    <th>Objective</th>
                                    <th>Daily Budget</th>
                                    <th>Lifetime Budget</th>
                                    <th>Budget Left</th>
                                    <th>Start</th>
                                    <th>End</th>
                                    <th>Impr.</th>
                                    <th>Reach</th>
                                    <th>Clicks</th>
                                    <th>Spend ($)</th>
                                    <th>CTR %</th>
                                    <th>CPM ($)</th>
                                    <th>Freq.</th>
                                    <th>Results</th>
                                    <th>Cost/Res.</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Add / Edit Modal --}}
    <div class="modal fade" id="videoModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title">
                        <i class="fas fa-video me-2"></i>
                        <span id="modalTitle">Add FB Video</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="videoForm" novalidate>
                        <input type="hidden" id="editId" name="id">

                        <div class="mb-3" id="skuSelectWrap">
                            <label class="form-label fw-semibold">SKU / Target Products</label>
                            <select class="form-select" id="f_sku" name="sku">
                                <option value="">Choose or type SKU...</option>
                            </select>
                            <small class="text-muted">Select from the list <strong>or</strong> type a custom SKU and press Enter</small>
                        </div>
                        <div class="mb-3 d-none" id="skuDisplayWrap">
                            <label class="form-label fw-semibold">SKU / Target Products</label>
                            <div id="skuDisplayBadge" class="sku-badge d-inline-block px-3 py-2" style="font-size:14px;"></div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Category</label>
                                <select class="form-select" id="f_category" name="category">
                                    <option value="">-- Select Category --</option>
                                    <option value="Category">Category</option>
                                    <option value="Parents">Parents</option>
                                    <option value="Group">Group</option>
                                    <option value="SKU">SKU</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Status</label>
                                <select class="form-select" id="f_ads_status" name="ads_status">
                                    <option value="Todo">Todo</option>
                                    <option value="Working">Working</option>
                                    <option value="Archived">Archived</option>
                                    <option value="Done">Done</option>
                                    <option value="Need Help">Need Help</option>
                                    <option value="Need Approval">Need Approval</option>
                                    <option value="Dependent">Dependent</option>
                                    <option value="Approved">Approved</option>
                                    <option value="Hold">Hold</option>
                                    <option value="Cancelled">Cancelled</option>
                                </select>
                            </div>
                        </div>

                        <div class="card border-0 bg-light rounded-3 p-3 mb-3">
                            <div class="fw-semibold mb-2" style="font-size:13px;">
                                <i class="fas fa-film me-1 text-primary"></i> Video Preview
                            </div>
                            <div class="mb-2">
                                <label class="form-label mb-1">Video Thumbnail URL</label>
                                <input type="url" class="form-control form-control-sm" id="f_video_thumbnail"
                                       name="video_thumbnail" placeholder="https:// (thumbnail image link)">
                                <small class="text-muted">Image shown in the Videos column</small>
                            </div>
                            <div>
                                <label class="form-label mb-1">Video URL <span class="text-muted">(plays on click)</span></label>
                                <input type="url" class="form-control form-control-sm" id="f_video_url"
                                       name="video_url" placeholder="https:// (YouTube, Drive, Vimeo…)">
                            </div>
                            <div id="videoPreviewBox" class="mt-2 d-none text-center">
                                <img id="videoPreviewThumb" src="" alt="Thumbnail preview"
                                     style="max-height:100px;border-radius:6px;border:1px solid #dee2e6;cursor:pointer;"
                                     title="Click to open video">
                                <div class="text-muted mt-1" style="font-size:11px;">Thumbnail preview</div>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Topic</label>
                                <input type="text" class="form-control" id="f_ads_topic_story_val"
                                       name="ads_topic_story" placeholder="Enter topic...">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">What</label>
                                <input type="text" class="form-control" id="f_ads_what_val"
                                       name="ads_what" placeholder="What is the video about?">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Why</label>
                                <input type="text" class="form-control" id="f_ads_why_purpose_val"
                                       name="ads_why_purpose" placeholder="Purpose of the video...">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Audience</label>
                                <select class="form-select" id="f_ads_audience_val" name="ads_audience">
                                    <option value="">All</option>
                                </select>
                                <small class="text-muted">Choose from list or type to add new</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Benefits</label>
                                <input type="text" class="form-control" id="f_ads_benefit_audience_val"
                                       name="ads_benefit_audience" placeholder="Benefits...">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label d-block">Language</label>
                                <div class="d-flex gap-2" id="langToggleGroup">
                                    <button type="button" class="btn btn-outline-secondary lang-toggle" data-lang="en" style="font-size:20px;padding:4px 14px;" title="English">
                                        🇺🇸 <small style="font-size:11px;">EN</small>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary lang-toggle" data-lang="es" style="font-size:20px;padding:4px 14px;" title="Spanish">
                                        🇪🇸 <small style="font-size:11px;">ES</small>
                                    </button>
                                </div>
                                <input type="hidden" id="f_ads_language_val" name="ads_language">
                            </div>
                        </div>

                        <hr class="my-3">

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Script Link</label>
                                <input type="url" class="form-control" id="f_ads_script_link_val"
                                       name="ads_script_link" placeholder="https://">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Link</label>
                                <input type="url" class="form-control" id="f_ads_video_en_link_val"
                                       name="ads_video_en_link" placeholder="https://">
                            </div>
                        </div>

                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveBtn">
                        <i class="fas fa-save"></i> Save
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Delete Confirm Modal --}}
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h6 class="modal-title"><i class="fas fa-trash me-2"></i>Confirm Delete</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">Delete record for SKU <strong id="deleteSku"></strong>?</p>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger btn-sm" id="confirmDeleteBtn">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Import Modal --}}
    <div class="modal fade" id="importModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-md">
            <div class="modal-content">
                <div class="modal-header" style="background:linear-gradient(135deg,#17a2b8,#117a8b);color:#fff;">
                    <h6 class="modal-title"><i class="fas fa-upload me-2"></i>Import Data (CSV)</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">

                    {{-- Instructions --}}
                    <div class="alert alert-info py-2 mb-3" style="font-size:12px;">
                        <strong>Instructions:</strong>
                        <ul class="mb-0 mt-1 ps-3">
                            <li>Upload a <strong>CSV</strong> file with a header row.</li>
                            <li><strong>sku</strong> column is required; all other columns are optional.</li>
                            <li>Existing records with the same SKU will be <strong>updated</strong>; new SKUs will be inserted.</li>
                            <li>Supported columns: <code>sku, ads_status, category, appr_s, appr_i, appr_n, video_thumbnail, video_url, ads_topic_story, ads_what, ads_why_purpose, ads_audience, ads_benefit_audience, ads_language, ads_script_link, ads_video_en_link</code></li>
                        </ul>
                    </div>

                    {{-- Download template --}}
                    <div class="mb-3">
                        <a id="downloadTemplate" href="#" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-file-csv me-1"></i> Download CSV Template
                        </a>
                    </div>

                    {{-- File picker --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Select CSV File</label>
                        <input type="file" class="form-control form-control-sm" id="importFile" accept=".csv,.txt">
                    </div>

                    {{-- Progress / result --}}
                    <div id="importProgress" class="d-none">
                        <div class="progress mb-2" style="height:6px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated bg-info w-100"></div>
                        </div>
                        <small class="text-muted">Uploading and processing…</small>
                    </div>
                    <div id="importResult" class="d-none"></div>

                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-sm text-white" id="doImportBtn" style="background:#17a2b8;">
                        <i class="fas fa-upload me-1"></i> Import
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- FB Push Sync Modal --}}
    <div class="modal fade" id="fbSyncModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-md">
            <div class="modal-content">
                <div class="modal-header" style="background:linear-gradient(135deg,#1877f2,#0d5fd4);color:#fff;">
                    <h6 class="modal-title">
                        <i class="fab fa-facebook-f me-2"></i>Push Facebook Insights Sync
                    </h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">

                    {{-- Date range --}}
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="font-size:12px;">From Date</label>
                            <input type="date" class="form-control form-control-sm" id="syncFromDate">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="font-size:12px;">To Date</label>
                            <input type="date" class="form-control form-control-sm" id="syncToDate">
                        </div>
                    </div>

                    {{-- Quick presets --}}
                    <div class="d-flex gap-2 flex-wrap mb-3">
                        <button type="button" class="btn btn-outline-secondary btn-sm sync-preset" data-days="30">Last 30 days</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm sync-preset" data-days="90">Last 90 days</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm sync-preset" data-days="180">Last 180 days</button>
                        <button type="button" class="btn btn-outline-primary btn-sm sync-preset fw-bold" data-days="365">Last 365 days</button>
                    </div>

                    {{-- Info box --}}
                    <div class="alert alert-info py-2 mb-3" style="font-size:11px;">
                        <i class="fas fa-info-circle me-1"></i>
                        This queues background jobs that fetch data from the Facebook Ads API.
                        Jobs run via <code>php artisan queue:work</code>.
                        Large date ranges (365 days) may take <strong>5–15 minutes</strong> to complete.
                    </div>

                    {{-- Status area --}}
                    <div id="syncStatusArea" class="d-none">
                        <div class="sync-progress-bar mb-2">
                            <div class="bar indeterminate" id="syncProgressBar"></div>
                        </div>
                        <div id="syncStatusText" class="fw-semibold" style="font-size:12px;"></div>
                        <div id="syncLogBox" style="max-height:140px;overflow-y:auto;margin-top:8px;padding:6px;background:#f8f9fa;border-radius:6px;border:1px solid #dee2e6;"></div>
                    </div>

                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal" id="fbSyncModalClose">Close</button>
                    <button type="button" class="btn btn-sm text-white fw-bold" id="startSyncBtn" style="background:#1877f2;">
                        <i class="fab fa-facebook-f me-1"></i> Start Sync
                    </button>
                </div>
            </div>
        </div>
    </div>

@endsection

@section('script')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script>
        @verbatim
        const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        let allData        = [];
        let fbInsightsMap  = {};   // keyed by video id
        let fbLoaded       = false;
        let deleteId       = null;
        let syncPollTimer  = null;
        let videoModal, deleteModal, importModal, fbSyncModal;

        // Field definitions: type 'text' = plain text, type 'link' = URL + status
        const fields = [
            { key: 'ads_topic_story',      label: 'Topic',          type: 'text' },
            { key: 'ads_audience',         label: 'Audience',       type: 'text' },
            { key: 'ads_language',         label: 'Language',       type: 'lang' },
            { key: 'ads_video_en_link',    label: 'Link',           type: 'url' },
        ];

        /* ── Bootstrap ─────────────────────────────────────────── */
        document.addEventListener('DOMContentLoaded', () => {
            videoModal  = new bootstrap.Modal(document.getElementById('videoModal'));
            deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            importModal = new bootstrap.Modal(document.getElementById('importModal'));
            fbSyncModal = new bootstrap.Modal(document.getElementById('fbSyncModal'));

            // Set default dates for Push Sync (last 365 days → today)
            const today     = new Date().toISOString().slice(0, 10);
            const yearAgo   = new Date(Date.now() - 364 * 86400000).toISOString().slice(0, 10);
            document.getElementById('syncToDate').value   = today;
            document.getElementById('syncFromDate').value = yearAgo;

            loadData();
            loadAudienceOptions();
            loadFbInsights();
            loadCampaigns();
            bindToolbar();
            bindFilters();
            bindTableEvents();
            bindSyncModal();
        });

        /* ── Toolbar ────────────────────────────────────────────── */
        function bindToolbar() {
            document.getElementById('addBtn').addEventListener('click', () => openModal('add'));
            document.getElementById('exportBtn').addEventListener('click', exportExcel);
            document.getElementById('importBtn').addEventListener('click', openImportModal);
            document.getElementById('saveBtn').addEventListener('click', saveRecord);
            document.getElementById('confirmDeleteBtn').addEventListener('click', deleteRecord);
            document.getElementById('doImportBtn').addEventListener('click', doImport);
            document.getElementById('downloadTemplate').addEventListener('click', downloadCsvTemplate);
            document.getElementById('fbSyncBtn').addEventListener('click', () => loadFbInsights(true));
            document.getElementById('fbPushSyncBtn').addEventListener('click', () => openFbSyncModal());
            document.getElementById('refreshCampaignsBtn').addEventListener('click', () => loadCampaigns());
        }

        /* ── Facebook Insights ──────────────────────────────────── */
        function loadFbInsights(manual = false) {
            const btn  = document.getElementById('fbSyncBtn');
            const info = document.getElementById('fb-sync-info');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading…';
            if (!manual && !fbLoaded) info.textContent = 'Loading…';

            fetch('/video-for-ds/fb-insights')
                .then(r => r.json())
                .then(resp => {
                    if (resp.success) {
                        fbInsightsMap = resp.insights || {};
                        fbLoaded = true;
                        const period   = resp.period || '—';
                        const syncedAt = resp.synced_at
                            ? new Date(resp.synced_at).toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric'})
                            : '—';
                        info.textContent = period + ' · DB synced ' + syncedAt;
                        // Update the header label with actual date range
                        const hdr = document.getElementById('fb-header-label');
                        if (hdr && period !== '—') {
                            hdr.innerHTML = '<i class="fab fa-facebook-f me-1"></i> FACEBOOK INSIGHTS — ' + period.toUpperCase();
                        }
                        // Re-render table rows with fresh FB data
                        applyFilters();
                    }
                })
                .catch(err => {
                    info.textContent = 'FB data unavailable';
                    console.warn('FB insights error:', err);
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fab fa-facebook-f"></i> FB Insights';
                });
        }

        /* ── Campaigns DataTable helpers ───────────────────────── */
        function renderFbInt(v) {
            const n = parseInt(v);
            if (!n) return '<span class="fb-metric-zero">—</span>';
            if (n >= 1e6)  return '<span class="fb-metric">' + (n/1e6).toFixed(1) + 'M</span>';
            if (n >= 1000) return '<span class="fb-metric">' + (n/1000).toFixed(1) + 'K</span>';
            return '<span class="fb-metric">' + n.toLocaleString() + '</span>';
        }
        function renderFbMoney(v) {
            const n = parseFloat(v);
            if (!n) return '<span class="fb-metric-zero">—</span>';
            return '<span class="fb-metric">$' + n.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}) + '</span>';
        }
        function renderFbPct(v) {
            const n = parseFloat(v);
            if (!n) return '<span class="fb-metric-zero">—</span>';
            return '<span class="fb-metric">' + n + '%</span>';
        }
        function renderFbDec(v) {
            const n = parseFloat(v);
            if (!n) return '<span class="fb-metric-zero">—</span>';
            return '<span class="fb-metric">' + n.toFixed(2) + '</span>';
        }

        /* ── Campaigns DataTable ────────────────────────────────── */
        let campaignsDT = null;

        function loadCampaigns() {
            document.getElementById('campaignsLoader').style.display = 'block';
            document.getElementById('campaignsTableWrap').classList.add('d-none');

            fetch('/video-for-ds/campaigns')
                .then(r => r.json())
                .then(resp => {
                    if (!resp.success) throw new Error('Server error');

                    document.getElementById('campaignsPeriodLabel').textContent = resp.period || '—';
                    document.getElementById('campaignsTotalBadge').textContent  = resp.total + ' campaigns';

                    const rows = (resp.data || []).map((c, i) => [
                        i + 1,
                        c.name || '—',
                        c.account_name || '—',
                        c.effective_status || c.status || '—',
                        (c.objective || '—').replace(/_/g, ' '),
                        c.daily_budget    || '—',
                        c.lifetime_budget || '—',
                        c.budget_remaining || '—',
                        c.start_time || '—',
                        c.stop_time  || '—',
                        c.impressions,
                        c.reach,
                        c.clicks,
                        c.spend,
                        c.ctr,
                        c.cpm,
                        c.frequency,
                        c.results,
                        c.cost_result,
                    ]);

                    document.getElementById('campaignsLoader').style.display  = 'none';
                    document.getElementById('campaignsTableWrap').classList.remove('d-none');

                    if (campaignsDT) {
                        campaignsDT.clear().rows.add(rows).draw();
                    } else {
                        campaignsDT = $('#campaignsTable').DataTable({
                            data: rows,
                            pageLength: 25,
                            order: [[13, 'desc']], // sort by Spend desc
                            dom: '<"d-flex justify-content-between align-items-center mb-2"lBf>rt<"d-flex justify-content-between align-items-center mt-2"ip>',
                            buttons: [
                                {
                                    extend: 'csvHtml5',
                                    text: '<i class="fas fa-file-csv me-1"></i>CSV',
                                    className: 'btn btn-sm btn-outline-secondary',
                                    title: 'FB_Campaigns_' + new Date().toISOString().slice(0,10),
                                },
                                {
                                    extend: 'excelHtml5',
                                    text: '<i class="fas fa-file-excel me-1"></i>Excel',
                                    className: 'btn btn-sm btn-outline-success',
                                    title: 'FB_Campaigns_' + new Date().toISOString().slice(0,10),
                                },
                            ],
                            language: {
                                search: '',
                                searchPlaceholder: 'Search campaigns…',
                                lengthMenu: 'Show _MENU_',
                                info: '_START_–_END_ of _TOTAL_ campaigns',
                                emptyTable: 'No Facebook campaigns found',
                            },
                            columnDefs: [
                                // Status column — badge
                                {
                                    targets: 3,
                                    render: function (val) {
                                        const cls = 'cstatus-' + (val || '').replace(/\s/g, '_');
                                        return '<span class="cstatus-badge ' + cls + '">' + (val || '—') + '</span>';
                                    }
                                },
                                // Numeric insight columns: Impr(10) Reach(11) Clicks(12) Spend(13) CTR%(14) CPM(15) Freq(16) Results(17) Cost/Res(18)
                                { targets: [10,11,12], render: (v,t) => t!=='display' ? (parseInt(v)||0) : renderFbInt(v) },
                                { targets: [13,15,18], render: (v,t) => t!=='display' ? (parseFloat(v)||0) : renderFbMoney(v) },
                                { targets: 14,         render: (v,t) => t!=='display' ? (parseFloat(v)||0) : renderFbPct(v) },
                                { targets: [16,17],    render: (v,t) => t!=='display' ? (parseFloat(v)||0) : renderFbDec(v) },
                                // Row number — not sortable
                                { targets: 0, orderable: false, width: '30px' },
                                // Name column — left align
                                { targets: 1, className: 'text-start' },
                            ],
                        });
                    }
                })
                .catch(err => {
                    document.getElementById('campaignsLoader').innerHTML =
                        '<span class="text-danger" style="font-size:12px;"><i class="fas fa-exclamation-circle me-1"></i>Failed to load campaigns: ' + err.message + '</span>';
                });
        }

        /* ── FB Push Sync Modal ─────────────────────────────────── */
        function openFbSyncModal() {
            // Reset UI
            document.getElementById('syncStatusArea').classList.add('d-none');
            document.getElementById('syncLogBox').innerHTML = '';
            document.getElementById('startSyncBtn').disabled = false;
            document.getElementById('startSyncBtn').innerHTML = '<i class="fab fa-facebook-f me-1"></i> Start Sync';
            if (syncPollTimer) { clearInterval(syncPollTimer); syncPollTimer = null; }
            fbSyncModal.show();
        }

        function bindSyncModal() {
            // Quick preset buttons
            document.querySelectorAll('.sync-preset').forEach(btn => {
                btn.addEventListener('click', function () {
                    const days = parseInt(this.dataset.days);
                    const to   = new Date().toISOString().slice(0, 10);
                    const from = new Date(Date.now() - (days - 1) * 86400000).toISOString().slice(0, 10);
                    document.getElementById('syncFromDate').value = from;
                    document.getElementById('syncToDate').value   = to;
                    document.querySelectorAll('.sync-preset').forEach(b => b.classList.remove('btn-primary','btn-outline-primary'));
                    this.classList.add('btn-primary');
                    this.classList.remove('btn-outline-secondary','btn-outline-primary');
                });
            });

            document.getElementById('startSyncBtn').addEventListener('click', startFbSync);

            // Clean up poll timer when modal closes
            document.getElementById('fbSyncModal').addEventListener('hidden.bs.modal', () => {
                if (syncPollTimer) { clearInterval(syncPollTimer); syncPollTimer = null; }
            });
        }

        function addSyncLog(msg, type = '') {
            const box  = document.getElementById('syncLogBox');
            const line = document.createElement('div');
            line.className = 'sync-log-entry ' + type;
            line.textContent = new Date().toLocaleTimeString() + '  ' + msg;
            box.prepend(line);
        }

        function startFbSync() {
            const fromDate = document.getElementById('syncFromDate').value;
            const toDate   = document.getElementById('syncToDate').value;
            if (!fromDate || !toDate) { alert('Please set both From and To dates.'); return; }
            if (fromDate > toDate)    { alert('"From" date must be before "To" date.'); return; }

            const btn = document.getElementById('startSyncBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Queuing…';

            // Show progress area
            const area = document.getElementById('syncStatusArea');
            area.classList.remove('d-none');
            document.getElementById('syncProgressBar').classList.add('indeterminate');
            document.getElementById('syncStatusText').textContent = 'Sending sync request…';
            document.getElementById('syncLogBox').innerHTML = '';

            fetch('/video-for-ds/trigger-fb-sync', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ from: fromDate, to: toDate })
            })
            .then(r => r.json())
            .then(resp => {
                if (!resp.success) throw new Error(resp.message || 'Unknown error');

                addSyncLog('✓ ' + resp.message, 'success');
                addSyncLog('Jobs are running in the background via queue worker.', 'info');
                document.getElementById('syncStatusText').textContent =
                    resp.queued + ' job(s) queued — processing in background…';

                btn.innerHTML = '<i class="fas fa-check me-1"></i> Queued (' + resp.queued + ')';

                // Start polling status every 6 seconds
                let pollCount = 0;
                syncPollTimer = setInterval(() => {
                    pollCount++;
                    fetch('/video-for-ds/sync-status')
                        .then(r => r.json())
                        .then(status => {
                            const pending = status.pending_jobs ?? '?';
                            const failed  = status.failed_jobs  ?? 0;
                            const maxDate = status.max_date || '—';

                            document.getElementById('syncStatusText').textContent =
                                pending + ' job(s) still pending · Latest data: ' + maxDate
                                + (failed ? ' · ⚠ ' + failed + ' failed' : '');

                            if (pending === 0 || pollCount >= 60) {
                                clearInterval(syncPollTimer);
                                syncPollTimer = null;

                                document.getElementById('syncProgressBar').classList.remove('indeterminate');
                                document.getElementById('syncProgressBar').style.width = '100%';

                                if (pending === 0) {
                                    addSyncLog('✓ All jobs completed. Refreshing insights…', 'success');
                                    document.getElementById('syncStatusText').textContent = '✓ Sync complete — refreshing table…';
                                    // Auto-reload FB insights + campaigns into the table
                                    setTimeout(() => {
                                        loadFbInsights(true);
                                        loadCampaigns();
                                        document.getElementById('syncStatusText').textContent = '✓ Done — insights refreshed.';
                                    }, 1500);
                                } else {
                                    addSyncLog('⏱ Jobs still running — click "FB Insights" to refresh when done.', 'info');
                                }
                            }
                        })
                        .catch(() => {});
                }, 6000);
            })
            .catch(err => {
                addSyncLog('✗ Error: ' + err.message, 'error');
                document.getElementById('syncStatusText').textContent = 'Error queuing jobs. See log above.';
                btn.disabled = false;
                btn.innerHTML = '<i class="fab fa-facebook-f me-1"></i> Start Sync';
            });
        }

        /* ── Table delegation (inline status dropdowns) ─────────── */
        function bindTableEvents() {
            document.getElementById('table-body').addEventListener('change', function(e) {
                if (!e.target.classList.contains('inline-status')) return;
                const id    = e.target.dataset.id;
                const field = e.target.dataset.field;
                const row   = allData.find(r => r.id == id);
                if (!row) return;
                row[field + '_status'] = e.target.value;
                patchRecord(row);
            });
        }

        /* ── Filters ────────────────────────────────────────────── */
        /* ── Checkbox helpers ────────────────────────────────────── */
        function syncSelectAll() {
            const all = document.querySelectorAll('.row-cb');
            const chk = document.querySelectorAll('.row-cb:checked');
            const sa  = document.getElementById('selectAll');
            if (!sa) return;
            sa.indeterminate = chk.length > 0 && chk.length < all.length;
            sa.checked       = chk.length > 0 && chk.length === all.length;
        }

        document.addEventListener('change', function(e) {
            if (e.target.id === 'selectAll') {
                document.querySelectorAll('.row-cb').forEach(cb => cb.checked = e.target.checked);
            }
        });

        function getSelectedIds() {
            return Array.from(document.querySelectorAll('.row-cb:checked')).map(cb => cb.dataset.id);
        }

        /* ── Bulk actions ────────────────────────────────────────── */
        document.addEventListener('click', function(e) {
            if (e.target.closest('#bulkDuplicate')) {
                e.preventDefault();
                bulkDuplicate();
            }
        });

        function bulkDuplicate() {
            const ids = getSelectedIds();
            if (!ids.length) { alert('Please select at least one row to duplicate.'); return; }

            const rows = allData.filter(r => ids.includes(String(r.id)));
            let done = 0;
            const total = rows.length;

            rows.forEach(row => {
                const newSku = 'COPY-' + row.sku + '-' + Date.now().toString().slice(-4);
                const payload = Object.assign({}, row, { sku: newSku, id: undefined, appr_s: 0, appr_i: 0, appr_n: 0 });
                delete payload.id;
                delete payload.parent_name;
                delete payload.image_path;
                delete payload.created_at;
                delete payload.updated_at;

                fetch('/videos-for-ads/store', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify(payload)
                })
                .then(r => r.json())
                .then(() => { done++; if (done === total) { loadData(); alert(total + ' row(s) duplicated.'); } })
                .catch(err => { done++; console.error(err); if (done === total) loadData(); });
            });
        }

        function bindFilters() {
            document.getElementById('skuSearch').addEventListener('input', applyFilters);
            document.getElementById('parentSearch').addEventListener('change', applyFilters);
            document.getElementById('filter_ads_audience').addEventListener('change', applyFilters);
        }

        /* ── Load ───────────────────────────────────────────────── */
        function loadData() {
            document.getElementById('loader').style.display = 'block';
            document.getElementById('table-body').innerHTML = '';

            fetch('/videos-for-ads/data')
                .then(r => r.json())
                .then(resp => {
                    allData = resp.data || [];
                    applyFilters();
                    updateCounts();
                })
                .catch(e => alert('Failed to load data: ' + e.message))
                .finally(() => document.getElementById('loader').style.display = 'none');
        }

        /* ── Render ─────────────────────────────────────────────── */
        /* ── Audience Options ───────────────────────────────────── */
        let audienceOptions = [];

        function loadAudienceOptions(callback) {
            fetch('/videos-for-ads/audience-options')
                .then(r => r.json())
                .then(resp => {
                    audienceOptions = resp.options || [];
                    populateAudienceHeaderFilter();
                    if (callback) callback();
                });
        }

        function populateAudienceHeaderFilter() {
            const filterSel = document.getElementById('filter_ads_audience');
            if (!filterSel) return;
            const current = filterSel.value;
            filterSel.innerHTML = '<option value="all">All</option><option value="missing">Missing</option>';
            audienceOptions.forEach(opt => {
                const o = document.createElement('option');
                o.value = opt; o.textContent = opt;
                filterSel.appendChild(o);
            });
            // Restore selection if still valid
            if (current) filterSel.value = current;
        }

        function rebuildAudienceSelect() {
            const sel = document.getElementById('f_ads_audience_val');
            if (!sel) return;

            // Destroy existing Select2 before rebuilding
            if ($(sel).hasClass('select2-hidden-accessible')) $(sel).select2('destroy');

            // Rebuild options
            sel.innerHTML = '<option value="">All</option>';
            audienceOptions.forEach(opt => {
                const o = document.createElement('option');
                o.value = opt; o.textContent = opt;
                sel.appendChild(o);
            });

            // Initialise Select2 with tags
            $(sel).select2({
                theme: 'bootstrap-5',
                placeholder: 'Select or type audience...',
                allowClear: true,
                tags: true,
                createTag: function(params) {
                    const term = $.trim(params.term);
                    if (!term) return null;
                    return { id: term, text: '+ Add "' + term + '"', newTag: true };
                },
                width: '100%',
                dropdownParent: $('#videoModal')
            });

            // When a new tag is chosen, persist it to the server
            $(sel).on('select2:select', function(e) {
                if (e.params.data.newTag) {
                    const newVal = e.params.data.id;
                    fetch('/videos-for-ads/audience-options', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                        body: JSON.stringify({ name: newVal })
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success && !audienceOptions.includes(data.name)) {
                            audienceOptions.push(data.name);
                            // Add option to form select so it persists on next open
                            const o = document.createElement('option');
                            o.value = data.name; o.textContent = data.name;
                            sel.appendChild(o);
                            // Also update the table header filter
                            populateAudienceHeaderFilter();
                        }
                    });
                }
            });
        }

        function applyFilters() {
            const skuQ      = document.getElementById('skuSearch').value.toLowerCase();
            const categoryQ = document.getElementById('parentSearch').value;
            const audienceQ = document.getElementById('filter_ads_audience').value;

            const filtered = allData.filter(row => {
                if (skuQ && !row.sku.toLowerCase().includes(skuQ)) return false;

                if (categoryQ && categoryQ !== 'all') {
                    const parent = (row.parent_name || '').toLowerCase();
                    if (!parent.includes(categoryQ.toLowerCase())) return false;
                }

                if (audienceQ && audienceQ !== 'all') {
                    if ((row.ads_audience || '').toLowerCase() !== audienceQ.toLowerCase()) return false;
                }

                return true;
            });

            renderTable(filtered);
        }

        function renderTable(data) {
            const tbody = document.getElementById('table-body');
            tbody.innerHTML = '';
            const sa = document.getElementById('selectAll');
            if (sa) { sa.checked = false; sa.indeterminate = false; }

            if (!data.length) {
                tbody.innerHTML = '<tr><td colspan="22" class="text-center py-4 text-muted">No records found</td></tr>';
                document.getElementById('total-count').textContent = '0';
                return;
            }

            document.getElementById('total-count').textContent = data.length;

            data.forEach((row, idx) => {
                const tr = document.createElement('tr');

                // Checkbox
                const cbTd = document.createElement('td');
                cbTd.style.textAlign = 'center';
                const cb = document.createElement('input');
                cb.type = 'checkbox'; cb.className = 'row-cb';
                cb.dataset.id = row.id;
                cb.style.cssText = 'cursor:pointer;width:13px;height:13px;';
                cb.addEventListener('change', syncSelectAll);
                cbTd.appendChild(cb);
                tr.appendChild(cbTd);

                // #
                td(tr, idx + 1, 'text-center text-muted');

                // Videos column — thumbnail + clickable link
                const videoTd = document.createElement('td');
                videoTd.style.textAlign = 'center';
                videoTd.style.verticalAlign = 'middle';
                if (row.video_thumbnail && row.video_url) {
                    videoTd.innerHTML =
                        '<a href="' + esc(row.video_url) + '" target="_blank" title="Play video" style="display:inline-block;position:relative;">' +
                            '<img src="' + esc(row.video_thumbnail) + '" style="width:36px;height:28px;object-fit:cover;border-radius:3px;border:2px solid #2c6ed5;">' +
                            '<span style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);' +
                                'background:rgba(0,0,0,0.55);border-radius:50%;width:14px;height:14px;' +
                                'display:flex;align-items:center;justify-content:center;">' +
                                '<i class="fas fa-play" style="color:#fff;font-size:6px;margin-left:1px;"></i>' +
                            '</span>' +
                        '</a>';
                } else if (row.video_url) {
                    videoTd.innerHTML =
                        '<a href="' + esc(row.video_url) + '" target="_blank" class="video-link-icon" title="Play video">' +
                            '<i class="fas fa-play-circle"></i>' +
                        '</a>';
                } else if (row.video_thumbnail) {
                    videoTd.innerHTML =
                        '<img src="' + esc(row.video_thumbnail) + '" style="width:36px;height:28px;object-fit:cover;border-radius:3px;">';
                } else {
                    videoTd.innerHTML = '<span class="text-muted">—</span>';
                }
                tr.appendChild(videoTd);

                // Parent
                const parentTd = document.createElement('td');
                parentTd.style.overflow = 'hidden';
                parentTd.style.textOverflow = 'ellipsis';
                parentTd.style.whiteSpace = 'nowrap';
                parentTd.title = row.parent_name || '';
                parentTd.innerHTML = row.parent_name
                    ? '<span style="color:#6c757d;">' + esc(row.parent_name) + '</span>'
                    : '<span class="text-muted">—</span>';
                tr.appendChild(parentTd);

                // SKU
                const skuTd = document.createElement('td');
                skuTd.style.overflow = 'hidden';
                skuTd.style.textOverflow = 'ellipsis';
                skuTd.style.whiteSpace = 'nowrap';
                skuTd.title = row.sku || '';
                skuTd.innerHTML = '<span class="sku-badge">' + esc(row.sku) + '</span>';
                tr.appendChild(skuTd);

                // Text fields — green dot when data exists, dash when empty
                fields.filter(f => f.type === 'text').forEach(f => {
                    const cell = document.createElement('td');
                    cell.style.textAlign = 'center';
                    cell.style.verticalAlign = 'middle';
                    if (row[f.key]) {
                        cell.innerHTML =
                            '<span class="data-dot" title="' + esc(row[f.key]) + '"></span>';
                    } else {
                        cell.innerHTML = '<span class="text-muted">-</span>';
                    }
                    tr.appendChild(cell);
                });

                // Language field — flag emojis
                fields.filter(f => f.type === 'lang').forEach(f => {
                    const cell = document.createElement('td');
                    cell.style.textAlign = 'center';
                    cell.style.verticalAlign = 'middle';
                    const val = (row[f.key] || '').toLowerCase();
                    let flags = '';
                    if (val.includes('en')) flags += '<span title="English" style="font-size:16px;line-height:1;">🇺🇸</span>';
                    if (val.includes('es')) flags += '<span title="Spanish" style="font-size:16px;line-height:1;margin-left:2px;">🇪🇸</span>';
                    cell.innerHTML = flags || '<span class="text-muted">-</span>';
                    tr.appendChild(cell);
                });

                // URL fields — hyperlink icon only
                fields.filter(f => f.type === 'url').forEach(f => {
                    const cell = document.createElement('td');
                    cell.style.textAlign = 'center';
                    cell.style.verticalAlign = 'middle';
                    cell.innerHTML = row[f.key]
                        ? '<a href="' + esc(row[f.key]) + '" target="_blank" class="video-link-icon" title="' + esc(row[f.key]) + '"><i class="fas fa-link"></i></a>'
                        : '<span class="text-muted">-</span>';
                    tr.appendChild(cell);
                });

                // ── Facebook Insight columns ──────────────────────────
                const fb = fbInsightsMap[row.id];
                const fbCols = [
                    { key: 'ad_name',     fmt: v => v ? '<span title="' + esc(v) + '" style="font-size:9px;display:block;max-width:70px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + esc(v) + '</span>' : null },
                    { key: 'impressions', fmt: v => v ? fmtNum(v) : null },
                    { key: 'reach',       fmt: v => v ? fmtNum(v) : null },
                    { key: 'clicks',      fmt: v => v ? fmtNum(v) : null },
                    { key: 'spend',       fmt: v => v ? '$' + fmtDec(v) : null },
                    { key: 'ctr',         fmt: v => v ? v + '%' : null },
                    { key: 'cpm',         fmt: v => v ? '$' + fmtDec(v) : null },
                    { key: 'frequency',   fmt: v => v ? v : null },
                    { key: 'video_views', fmt: v => v ? fmtNum(v) : null },
                    { key: 'results',     fmt: v => v ? fmtNum(v) : null },
                    { key: 'cost_result', fmt: v => v ? '$' + fmtDec(v) : null },
                    { key: 'watch_time',  fmt: v => v ? v + 's' : null },
                ];
                fbCols.forEach(col => {
                    const c = document.createElement('td');
                    c.style.textAlign = 'center';
                    c.style.verticalAlign = 'middle';
                    if (!fbLoaded) {
                        c.className = 'fb-cell-loading';
                        c.innerHTML = '…';
                    } else if (!fb) {
                        c.className = 'fb-cell no-data';
                        c.innerHTML = '<span class="text-muted" style="font-size:10px;">—</span>';
                    } else {
                        const val = fb[col.key];
                        const display = (val !== null && val !== undefined && val !== 0 && val !== '')
                            ? col.fmt(val) : null;
                        if (display) {
                            c.className = 'fb-cell';
                            c.innerHTML = display;
                        } else {
                            c.className = 'fb-cell no-data';
                            c.innerHTML = '<span class="text-muted" style="font-size:10px;">—</span>';
                        }
                    }
                    tr.appendChild(c);
                });

                // Action
                const actTd = document.createElement('td');
                actTd.style.whiteSpace = 'nowrap';
                actTd.innerHTML =
                    '<button class="action-btn edit-btn me-1" data-id="' + row.id + '" title="Edit"><i class="fas fa-edit"></i></button>' +
                    '<button class="action-btn del-btn"  data-id="' + row.id + '" data-sku="' + esc(row.sku) + '" title="Delete"><i class="fas fa-trash"></i></button>';
                tr.appendChild(actTd);

                actTd.querySelector('.edit-btn').addEventListener('click', () => openModal('edit', row.id));
                actTd.querySelector('.del-btn').addEventListener('click', () => openDeleteConfirm(row.id, row.sku));

                tbody.appendChild(tr);
            });
        }

        function td(tr, val, cls = '') {
            const c = document.createElement('td');
            if (cls) c.className = cls;
            c.textContent = val ?? '—';
            tr.appendChild(c);
        }

        function fmtNum(n) {
            const num = parseInt(n, 10);
            if (isNaN(num)) return '—';
            if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
            if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
            return num.toLocaleString();
        }

        function fmtDec(n) {
            const num = parseFloat(n);
            if (isNaN(num)) return '0.00';
            return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        /* ── Counts ─────────────────────────────────────────────── */
        function updateCounts() {
            // counts removed per user request
        }

        /* ── Modal open ─────────────────────────────────────────── */
        function openModal(mode, id = null) {
            document.getElementById('videoForm').reset();
            document.getElementById('editId').value = '';
            setLangToggles('');

            const skuSelect      = document.getElementById('f_sku');
            const skuSelectWrap  = document.getElementById('skuSelectWrap');
            const skuDisplayWrap = document.getElementById('skuDisplayWrap');
            const skuBadge       = document.getElementById('skuDisplayBadge');

            if ($(skuSelect).hasClass('select2-hidden-accessible')) {
                $(skuSelect).select2('destroy');
            }

            if (mode === 'add') {
                document.getElementById('modalTitle').textContent = 'Add FB Video';
                skuSelectWrap.classList.remove('d-none');
                skuDisplayWrap.classList.add('d-none');

                skuSelect.innerHTML = '<option value="">Loading SKUs...</option>';
                document.getElementById('videoPreviewBox').classList.add('d-none');
                rebuildAudienceSelect();

                fetch('/product-master-data-view')
                    .then(r => r.json())
                    .then(resp => {
                        const data = resp.data ? resp.data : resp;
                        const alreadySaved = new Set(allData.map(r => r.sku));
                        skuSelect.innerHTML = '<option value="">Choose SKU...</option>';
                        (Array.isArray(data) ? data : []).forEach(item => {
                            if (item.SKU && !item.SKU.toUpperCase().includes('PARENT') && !alreadySaved.has(item.SKU)) {
                                const opt = document.createElement('option');
                                opt.value       = item.SKU;
                                opt.textContent = item.SKU;
                                skuSelect.appendChild(opt);
                            }
                        });
                        $(skuSelect).select2({
                            theme: 'bootstrap-5',
                            placeholder: 'Search, choose or type custom SKU...',
                            allowClear: true,
                            tags: true,
                            createTag: function(params) {
                                const term = $.trim(params.term);
                                if (!term) return null;
                                return { id: term, text: term + ' (custom)', newTag: true };
                            },
                            width: '100%',
                            dropdownParent: $('#videoModal')
                        });
                    })
                    .catch(() => {
                        skuSelect.innerHTML = '<option value="">Failed to load SKUs</option>';
                    });

            } else {
                const row = allData.find(r => r.id == id);
                if (!row) return;

                document.getElementById('modalTitle').textContent = 'Edit — ' + row.sku;
                document.getElementById('editId').value = row.id;

                skuSelectWrap.classList.add('d-none');
                skuDisplayWrap.classList.remove('d-none');
                skuBadge.textContent = row.sku;

                // Prefill category, status + video fields
                const catEl = document.getElementById('f_category');
                if (catEl) catEl.value = row.category || '';
                const statusEl = document.getElementById('f_ads_status');
                if (statusEl) statusEl.value = row.ads_status || 'Todo';

                const thumbEl = document.getElementById('f_video_thumbnail');
                const urlEl   = document.getElementById('f_video_url');
                if (thumbEl) thumbEl.value = row.video_thumbnail || '';
                if (urlEl)   urlEl.value   = row.video_url || '';
                updateVideoPreview();

                // Rebuild audience Select2 then set saved value
                rebuildAudienceSelect();
                const savedAudience = row.ads_audience || '';
                if (savedAudience) {
                    // Ensure option exists before setting
                    const audSel = document.getElementById('f_ads_audience_val');
                    if (audSel && !Array.from(audSel.options).some(o => o.value === savedAudience)) {
                        const o = document.createElement('option');
                        o.value = savedAudience; o.textContent = savedAudience;
                        audSel.appendChild(o);
                    }
                    $(audSel).val(savedAudience).trigger('change');
                }

                fields.filter(f => f.type === 'text').forEach(f => {
                    if (f.key === 'ads_audience') return; // handled above
                    const el = document.getElementById('f_' + f.key + '_val');
                    if (el) el.value = row[f.key] || '';
                });
                fields.filter(f => f.type === 'url').forEach(f => {
                    const url = document.getElementById('f_' + f.key + '_val');
                    if (url) url.value = row[f.key] || '';
                });

                // Language toggles prefill
                setLangToggles(row.ads_language || '');
            }

            document.getElementById('videoModal').addEventListener('hidden.bs.modal', function() {
                if ($(skuSelect).hasClass('select2-hidden-accessible')) $(skuSelect).select2('destroy');
                const audSel = document.getElementById('f_ads_audience_val');
                if (audSel && $(audSel).hasClass('select2-hidden-accessible')) $(audSel).select2('destroy');
            }, { once: true });

            videoModal.show();
        }

        /* ── Save ───────────────────────────────────────────────── */
        function saveRecord() {
            const editId   = document.getElementById('editId').value;
            const skuEl    = document.getElementById('f_sku');
            const skuBadge = document.getElementById('skuDisplayBadge');

            // In edit mode use the badge text; in add mode use Select2 value
            const sku = editId
                ? skuBadge.textContent.trim()
                : ($(skuEl).hasClass('select2-hidden-accessible') ? $(skuEl).val() : skuEl.value);

            skuEl.classList.remove('is-invalid');

            // Preserve appr values from existing row in edit mode; default to 0 for new records
            const existingRow = allData.find(r => String(r.id) === String(editId)) || {};
            const payload = { sku };
            payload.ads_status      = document.getElementById('f_ads_status').value;
            payload.appr_s          = existingRow.appr_s ?? 0;
            payload.appr_i          = existingRow.appr_i ?? 0;
            payload.appr_n          = existingRow.appr_n ?? 0;
            payload.category        = document.getElementById('f_category').value;
            payload.video_thumbnail = document.getElementById('f_video_thumbnail').value;
            payload.video_url       = document.getElementById('f_video_url').value;
            fields.filter(f => f.type === 'text').forEach(f => {
                payload[f.key] = document.getElementById('f_' + f.key + '_val').value;
            });
            fields.filter(f => f.type === 'lang').forEach(f => {
                payload[f.key] = document.getElementById('f_' + f.key + '_val').value;
            });
            fields.filter(f => f.type === 'url').forEach(f => {
                payload[f.key] = document.getElementById('f_' + f.key + '_val').value;
            });

            const btn = document.getElementById('saveBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

            fetch('/videos-for-ads/store', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify(payload)
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    videoModal.hide();
                    loadData();
                } else {
                    alert(data.message || 'Failed to save');
                }
            })
            .catch(e => alert('Error: ' + e.message))
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save"></i> Save';
            });
        }

        /* ── Inline status patch ────────────────────────────────── */
        function patchRecord(row) {
            const payload = { sku: row.sku };
            payload.ads_status      = row.ads_status      || 'Todo';
            payload.appr_s          = row.appr_s          ?? 0;
            payload.appr_i          = row.appr_i          ?? 0;
            payload.appr_n          = row.appr_n          ?? 0;
            payload.category        = row.category        || '';
            payload.video_thumbnail = row.video_thumbnail || '';
            payload.video_url       = row.video_url       || '';
            fields.forEach(f => {
                payload[f.key] = row[f.key] || '';
            });
            fetch('/videos-for-ads/store', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify(payload)
            })
            .then(r => r.json())
            .then(d => { if (!d.success) alert(d.message || 'Failed to save status'); })
            .catch(e => alert('Error: ' + e.message));
        }

        /* ── Delete ─────────────────────────────────────────────── */
        function openDeleteConfirm(id, sku) {
            deleteId = id;
            document.getElementById('deleteSku').textContent = sku;
            deleteModal.show();
        }

        function deleteRecord() {
            if (!deleteId) return;
            const btn = document.getElementById('confirmDeleteBtn');
            btn.disabled = true;

            fetch('/videos-for-ads/' + deleteId, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrf }
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    deleteModal.hide();
                    loadData();
                } else {
                    alert(data.message || 'Failed to delete');
                }
            })
            .catch(e => alert('Error: ' + e.message))
            .finally(() => { btn.disabled = false; deleteId = null; });
        }

        /* ── Export ─────────────────────────────────────────────── */
        function exportExcel() {
            const rows = allData.map(row => {
                const r = { SKU: row.sku };
                fields.forEach(f => {
                    r[f.label] = row[f.key] || '';
                    if (f.type === 'link') r[f.label + ' Status'] = row[f.key + '_status'] || '';
                });
                return r;
            });
            const ws = XLSX.utils.json_to_sheet(rows);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'FB Video Ads');
            XLSX.writeFile(wb, 'video_for_ds_' + new Date().toISOString().split('T')[0] + '.xlsx');
        }

        /* ── Video thumbnail live preview ───────────────────────── */
        /* ── Language toggle helpers ─────────────────────────────── */
        function setLangToggles(value) {
            const val = (value || '').toLowerCase();
            document.querySelectorAll('.lang-toggle').forEach(btn => {
                const lang = btn.dataset.lang;
                if (val.includes(lang)) {
                    btn.classList.remove('btn-outline-secondary');
                    btn.classList.add('btn-primary');
                } else {
                    btn.classList.remove('btn-primary');
                    btn.classList.add('btn-outline-secondary');
                }
            });
            syncLangHidden();
        }

        function syncLangHidden() {
            const selected = [];
            document.querySelectorAll('.lang-toggle.btn-primary').forEach(btn => selected.push(btn.dataset.lang));
            document.getElementById('f_ads_language_val').value = selected.join(',');
        }

        document.addEventListener('click', function(e) {
            if (!e.target.closest('.lang-toggle')) return;
            const btn = e.target.closest('.lang-toggle');
            btn.classList.toggle('btn-primary');
            btn.classList.toggle('btn-outline-secondary');
            syncLangHidden();
        });

        /* ── Import ──────────────────────────────────────────────── */
        function openImportModal() {
            document.getElementById('importFile').value = '';
            document.getElementById('importProgress').classList.add('d-none');
            document.getElementById('importResult').classList.add('d-none');
            document.getElementById('importResult').innerHTML = '';
            importModal.show();
        }

        function doImport() {
            const fileEl = document.getElementById('importFile');
            if (!fileEl.files.length) {
                alert('Please select a CSV file first.');
                return;
            }
            const btn = document.getElementById('doImportBtn');
            btn.disabled = true;
            document.getElementById('importProgress').classList.remove('d-none');
            document.getElementById('importResult').classList.add('d-none');
            document.getElementById('importResult').innerHTML = '';

            const form = new FormData();
            form.append('file', fileEl.files[0]);
            form.append('_token', csrf);

            fetch('/videos-for-ads/import', { method: 'POST', body: form })
                .then(r => r.json())
                .then(d => {
                    document.getElementById('importProgress').classList.add('d-none');
                    const res = document.getElementById('importResult');
                    res.classList.remove('d-none');
                    if (d.success) {
                        let html = '<div class="alert alert-success py-2 mb-2" style="font-size:12px;">'
                            + '<i class="fas fa-check-circle me-1"></i>'
                            + '<strong>' + d.imported + ' record(s) imported/updated</strong>'
                            + (d.skipped ? ', ' + d.skipped + ' skipped.' : '.')
                            + '</div>';
                        if (d.errors && d.errors.length) {
                            html += '<div class="alert alert-warning py-2" style="font-size:11px;"><strong>Warnings:</strong><ul class="mb-0 mt-1 ps-3">'
                                + d.errors.map(e => '<li>' + e + '</li>').join('')
                                + '</ul></div>';
                        }
                        res.innerHTML = html;
                        loadData();
                    } else {
                        res.innerHTML = '<div class="alert alert-danger py-2" style="font-size:12px;">'
                            + '<i class="fas fa-times-circle me-1"></i>' + (d.message || 'Import failed.') + '</div>';
                    }
                })
                .catch(e => {
                    document.getElementById('importProgress').classList.add('d-none');
                    document.getElementById('importResult').classList.remove('d-none');
                    document.getElementById('importResult').innerHTML = '<div class="alert alert-danger py-2" style="font-size:12px;">Error: ' + e.message + '</div>';
                })
                .finally(() => { btn.disabled = false; });
        }

        function downloadCsvTemplate(e) {
            e.preventDefault();
            const headers = [
                'sku','ads_status','category','appr_s','appr_i','appr_n',
                'video_thumbnail','video_url','ads_topic_story','ads_what',
                'ads_why_purpose','ads_audience','ads_benefit_audience',
                'ads_language','ads_script_link','ads_video_en_link'
            ];
            const example = [
                'SAMPLE-SKU-001','Todo','Category','0','0','0',
                'https://example.com/thumb.jpg','https://example.com/video.mp4',
                'Topic text','What text','Why text','Drummer','Benefit text',
                'en','https://example.com/script','https://example.com/link'
            ];
            const csv = headers.join(',') + '\n' + example.join(',');
            const blob = new Blob([csv], { type: 'text/csv' });
            const url  = URL.createObjectURL(blob);
            const a    = document.createElement('a');
            a.href = url; a.download = 'video_for_ds_template.csv';
            a.click(); URL.revokeObjectURL(url);
        }

        function updateVideoPreview() {
            const thumbEl   = document.getElementById('f_video_thumbnail');
            const urlEl     = document.getElementById('f_video_url');
            const previewBox   = document.getElementById('videoPreviewBox');
            const previewThumb = document.getElementById('videoPreviewThumb');
            if (!thumbEl || !previewBox || !previewThumb) return;
            const thumbUrl = thumbEl.value.trim();
            const videoUrl = urlEl ? urlEl.value.trim() : '';
            if (thumbUrl) {
                previewThumb.src = thumbUrl;
                previewThumb.onclick = videoUrl ? () => window.open(videoUrl, '_blank') : null;
                previewBox.classList.remove('d-none');
            } else {
                previewBox.classList.add('d-none');
            }
        }

        // Bind live preview on thumbnail input
        document.addEventListener('DOMContentLoaded', function() {
            const thumbInput = document.getElementById('f_video_thumbnail');
            const urlInput   = document.getElementById('f_video_url');
            if (thumbInput) thumbInput.addEventListener('input', updateVideoPreview);
            if (urlInput)   urlInput.addEventListener('input', updateVideoPreview);
        });

        /* ── Helpers ────────────────────────────────────────────── */
        function isEmpty(v) { return v === null || v === undefined || v === '' || (typeof v === 'string' && !v.trim()); }

        function esc(t) {
            if (!t) return '';
            return String(t).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
        }
        @endverbatim
    </script>
@endsection
