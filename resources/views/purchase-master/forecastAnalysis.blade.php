@extends('layouts.vertical', ['title' => 'Forecast Analysis'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link href="{{ asset('css/select-searchable.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
    <style>
        .tabulator .tabulator-footer {
            background: #f4f7fa;
            border-top: 1px solid #262626;
            font-size: 1rem;
            color: #4b5563;
            padding: 5px;
            height: 70px;
        }
        .tabulator .tabulator-footer .tabulator-page-counter {
            display: none !important;
        }
        #top-row-counter {
            font-size: 18px;
            font-weight: 700;
            color: #3a475d;
            white-space: nowrap;
        }

        /* Pagination styling */
        .tabulator .tabulator-footer .tabulator-paginator .tabulator-page {
            padding: 8px 16px;
            margin: 0 4px;
            border-radius: 6px;
            font-size: 0.95rem;
            font-weight: 500;
            transition: all 0.2s;
        }

        .tabulator .tabulator-footer .tabulator-paginator .tabulator-page:hover {
            background: #e0eaff;
            color: #2563eb;
        }

        .tabulator .tabulator-footer .tabulator-paginator .tabulator-page.active {
            background: #2563eb;
            color: white;
        }

        #image-hover-preview {
            transition: opacity 0.2s ease;
        }

        /* Forecast table: no gray behind headers — Tabulator defaults show gray around title area */
        #forecast-table.tabulator .tabulator-header,
        #forecast-table.tabulator .tabulator-header .tabulator-col,
        #forecast-table.tabulator .tabulator-header .tabulator-col .tabulator-col-content,
        #forecast-table.tabulator .tabulator-header .tabulator-col .tabulator-col-title,
        #forecast-table.tabulator .tabulator-header .tabulator-col .tabulator-col-title-holder {
            background: #7ec8c3 !important;
            background-color: #7ec8c3 !important;
        }
        #forecast-table.tabulator .tabulator-header .tabulator-col .tabulator-header-filter {
            background: #7ec8c3 !important;
        }

        /* Center-align all header text */
        .tabulator .tabulator-header .tabulator-col,
        .tabulator .tabulator-header .tabulator-col .tabulator-col-content {
            text-align: center;
        }
        /* Hide sort arrows in header */
        .tabulator .tabulator-header .tabulator-col .tabulator-arrow {
            display: none !important;
        }
        .tabulator .tabulator-header .tabulator-header-filter {
            display: flex;
            justify-content: center;
        }

        /* NRP: REQ = green dot, 2BDC (NR) = red, LATER = yellow; select overlaid for editing */
        .nrp-dot-cell {
            min-height: 36px;
            min-width: 44px;
        }
        .nrp-dot-cell .nrp-status-dot {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            flex-shrink: 0;
            box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.15);
        }
        .nrp-dot-cell .nrp-nr-select {
            opacity: 0;
            cursor: pointer;
            margin: 0 !important;
            border: 0 !important;
            padding: 0 !important;
            background: transparent !important;
            -webkit-appearance: none;
            appearance: none;
        }

        .stage-dot-cell {
            min-height: 36px;
            min-width: 40px;
        }
        .stage-dot-cell .stage-status-dot {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            flex-shrink: 0;
            box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.12);
        }
        .stage-dot-cell .stage-stage-select {
            opacity: 0;
            cursor: pointer;
            margin: 0 !important;
            border: 0 !important;
            padding: 0 !important;
            background: transparent !important;
            -webkit-appearance: none;
            appearance: none;
        }
        .stage-dot-cell .stage-transit-icon {
            font-size: 1.05rem;
            line-height: 1;
            color: #334155;
        }

        .tabulator-cell.forecast-rating-combo-cell {
            font-size: 0.65rem;
            line-height: 1.1;
            padding: 1px 2px !important;
        }

        .tabulator-cell.forecast-current-supplier-cell {
            vertical-align: middle;
            padding-left: 2px !important;
            padding-right: 2px !important;
        }
        .tabulator-cell.forecast-current-supplier-cell .forecast-supplier-name {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            overflow-wrap: anywhere;
            word-break: break-word;
            line-height: 1.15;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            text-align: center;
            font-weight: 600;
            font-size: 0.72rem;
        }

        .forecast-dil-pct {
            font-weight: 700;
            font-size: 0.95rem;
            background: none !important;
            border: none !important;
            padding: 0;
            border-radius: 0;
        }

        .forecast-to-order-pct {
            font-weight: 700;
            font-size: 0.95rem;
            background: none !important;
            padding: 0;
            border-radius: 0;
        }

        /* Table fills one screen: height set in JS from viewport; slightly tighter rows */
        #forecast-table-wrap {
            width: 100%;
            min-height: 220px;
        }
        #forecast-table-wrap .tabulator .tabulator-cell {
            padding-top: 3px;
            padding-bottom: 3px;
        }
        /* All column titles vertical (except row-selection checkbox column) */
        #forecast-table-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content {
            display: flex;
            flex-direction: column;
            align-items: stretch;
            justify-content: flex-start;
            gap: 4px;
            min-height: 108px;
            padding: 4px 3px;
            box-sizing: border-box;
        }
        #forecast-table-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-title-holder {
            display: flex;
            flex-direction: row;
            flex-wrap: nowrap;
            align-items: center;
            justify-content: center;
            gap: 2px;
            flex: 0 0 auto;
            min-height: 56px;
            width: 100%;
        }
        #forecast-table-wrap .tabulator .tabulator-header .tabulator-col:not(:first-child) .tabulator-col-title,
        #forecast-table-wrap .tabulator .tabulator-header .tabulator-col:not(:first-child) .tabulator-title {
            writing-mode: vertical-rl;
            transform: rotate(180deg);
            white-space: nowrap;
            font-weight: 700;
            font-size: 0.68rem;
            line-height: 1.1;
            letter-spacing: 0.02em;
            text-align: center;
        }
        #forecast-table-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-sorter {
            writing-mode: horizontal-tb !important;
            transform: none !important;
            flex-shrink: 0;
        }
        #forecast-table-wrap .tabulator .tabulator-header .tabulator-col:first-child .tabulator-col-content {
            min-height: auto;
            flex-direction: row;
            align-items: center;
            justify-content: center;
        }
        #forecast-table-wrap .tabulator .tabulator-header .tabulator-col:first-child .tabulator-col-title,
        #forecast-table-wrap .tabulator .tabulator-header .tabulator-col:first-child .tabulator-col-title-holder,
        #forecast-table-wrap .tabulator .tabulator-header .tabulator-col:first-child .tabulator-title {
            writing-mode: horizontal-tb !important;
            transform: none !important;
            min-height: auto !important;
        }
        #forecast-table-wrap .tabulator .tabulator-header .tabulator-header-filter {
            writing-mode: horizontal-tb !important;
            transform: none !important;
            flex: 1 1 auto;
            width: 100%;
            max-width: 100%;
            margin-top: auto;
            align-self: stretch;
        }
        #forecast-table-wrap .tabulator .tabulator-header .tabulator-header-filter input,
        #forecast-table-wrap .tabulator .tabulator-header .tabulator-header-filter select {
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            font-size: 0.68rem;
            padding: 2px 3px;
        }
        /* Header filters fill narrow Parent/SKU columns */
        #forecast-table-wrap .tabulator .tabulator-col.tabulator-field-Parent .tabulator-header-filter input,
        #forecast-table-wrap .tabulator .tabulator-col.tabulator-field-SKU .tabulator-header-filter input {
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
        }
        #forecast-table-wrap .tabulator .tabulator-col.tabulator-field-mfrg_supplier .tabulator-header-filter input {
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            padding: 2px 3px;
            font-size: 0.7rem;
        }

        .column-customize-modal {
            border: 0;
            border-radius: 14px;
            overflow: hidden;
        }
        .column-modal-header {
            background: #ffffff;
            border-bottom: 1px solid #e9ecef;
            z-index: 2;
        }
        .column-panel {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            background: #fff;
            padding: 12px;
        }
        .column-panel-title {
            font-weight: 700;
            font-size: 1.05rem;
            color: #111827;
            margin-bottom: 4px;
        }
        .column-panel-hint {
            color: #6b7280;
            font-size: 0.85rem;
            margin-bottom: 10px;
        }
        .column-available-wrap {
            max-height: 54vh;
            overflow: auto;
            padding-right: 4px;
        }
        .column-group-title {
            font-weight: 700;
            color: #1f2937;
            margin: 8px 0 6px;
        }
        .column-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(160px, 1fr));
            gap: 6px 10px;
        }
        .column-checkbox-row {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 8px;
            border-radius: 8px;
            transition: background-color .2s ease;
        }
        .column-checkbox-row:hover {
            background: #f8fafc;
        }
        .column-arrange-wrap {
            max-height: 56vh;
            overflow: auto;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 8px;
            background: #fafafa;
        }
        .arrange-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 7px 10px;
            background: #fff;
            margin-bottom: 6px;
            cursor: grab;
            transition: all .2s ease;
        }
        .arrange-item.active {
            border-color: #3b82f6;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, .15);
            background: #eff6ff;
        }
        .arrange-item .arrange-name {
            font-weight: 600;
            color: #111827;
        }
        .arrange-item:last-child {
            margin-bottom: 0;
        }
        .sortable-ghost {
            opacity: .45;
        }
        /* Ensure frozen column header filter stays visible */
        .tabulator-col.tabulator-frozen .tabulator-header-filter {
            position: relative;
            z-index: 11;
        }
        .tabulator-col.tabulator-frozen .tabulator-header-filter input {
            position: relative;
            z-index: 11;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Forecast Analysis',
        'sub_title' => 'Forecast Analysis',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body pb-0 d-flex flex-column gap-2">

                    <!-- ── Row 1: Play controls + row counter ── -->
                    <div class="d-flex align-items-center flex-wrap gap-2">

                        <!-- Parent play -->
                        <div class="d-flex align-items-center gap-1 border rounded px-2 py-1 bg-light" title="Play by Parent">
                            <small class="text-muted fw-semibold" style="font-size:0.65rem;">P</small>
                            <button id="play-backward" class="btn btn-light btn-sm rounded-circle p-0" style="width:28px;height:28px;"><i class="fas fa-step-backward" style="font-size:10px;"></i></button>
                            <button id="play-pause"   class="btn btn-primary btn-sm rounded-circle p-0" style="width:28px;height:28px;display:none;"><i class="fas fa-pause" style="font-size:10px;"></i></button>
                            <button id="play-auto"    class="btn btn-primary btn-sm rounded-circle p-0" style="width:28px;height:28px;"><i class="fas fa-play" style="font-size:10px;"></i></button>
                            <button id="play-forward" class="btn btn-light btn-sm rounded-circle p-0" style="width:28px;height:28px;"><i class="fas fa-step-forward" style="font-size:10px;"></i></button>
                        </div>

                        <!-- Supplier play -->
                        <div class="d-flex align-items-center gap-1 border rounded px-2 py-1 bg-light" title="Play by Supplier">
                            <small class="text-muted fw-semibold" style="font-size:0.65rem;">S</small>
                            <button id="supplier-play-backward" class="btn btn-light btn-sm rounded-circle p-0" style="width:28px;height:28px;" title="Prev supplier"><i class="fas fa-step-backward" style="font-size:10px;"></i></button>
                            <button id="supplier-play-pause"    class="btn btn-warning btn-sm rounded-circle p-0" style="width:28px;height:28px;display:none;" title="Stop supplier"><i class="fas fa-pause" style="font-size:10px;"></i></button>
                            <button id="supplier-play-auto"     class="btn btn-outline-warning btn-sm rounded-circle p-0 fw-bold" style="width:28px;height:28px;font-size:11px;" title="Play by supplier">S</button>
                            <button id="supplier-play-forward"  class="btn btn-light btn-sm rounded-circle p-0" style="width:28px;height:28px;" title="Next supplier"><i class="fas fa-step-forward" style="font-size:10px;"></i></button>
                            <span class="badge bg-warning text-dark" id="supplier-play-label" style="font-size:0.65rem;display:none;max-width:90px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></span>
                            </div>

                        <!-- Zone play -->
                        <div class="d-flex align-items-center gap-1 border rounded px-2 py-1 bg-light" title="Play by Zone">
                            <small class="text-muted fw-semibold" style="font-size:0.65rem;">Z</small>
                            <button id="zone-play-backward" class="btn btn-light btn-sm rounded-circle p-0" style="width:28px;height:28px;" title="Prev zone"><i class="fas fa-step-backward" style="font-size:10px;"></i></button>
                            <button id="zone-play-pause"    class="btn btn-info btn-sm rounded-circle p-0" style="width:28px;height:28px;display:none;" title="Stop zone"><i class="fas fa-pause" style="font-size:10px;"></i></button>
                            <button id="zone-play-auto"     class="btn btn-outline-info btn-sm rounded-circle p-0 fw-bold" style="width:28px;height:28px;font-size:11px;" title="Play by zone">Z</button>
                            <button id="zone-play-forward"  class="btn btn-light btn-sm rounded-circle p-0" style="width:28px;height:28px;" title="Next zone"><i class="fas fa-step-forward" style="font-size:10px;"></i></button>
                            <span class="badge bg-info text-dark" id="zone-play-label" style="font-size:0.65rem;display:none;max-width:90px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></span>
                        </div>

                        <!-- Container play -->
                        <div class="d-flex align-items-center gap-1 border rounded px-2 py-1 bg-light" title="Play by Container">
                            <small class="text-muted fw-semibold" style="font-size:0.65rem;">C</small>
                            <button id="container-play-backward" class="btn btn-light btn-sm rounded-circle p-0" style="width:28px;height:28px;" title="Prev container"><i class="fas fa-step-backward" style="font-size:10px;"></i></button>
                            <button id="container-play-pause"    class="btn btn-dark btn-sm rounded-circle p-0" style="width:28px;height:28px;display:none;" title="Stop container"><i class="fas fa-pause" style="font-size:10px;"></i></button>
                            <button id="container-play-auto"     class="btn btn-outline-dark btn-sm rounded-circle p-0 fw-bold" style="width:28px;height:28px;font-size:11px;" title="Play by container">C</button>
                            <button id="container-play-forward"  class="btn btn-light btn-sm rounded-circle p-0" style="width:28px;height:28px;" title="Next container"><i class="fas fa-step-forward" style="font-size:10px;"></i></button>
                            <span class="badge bg-dark text-white" id="container-play-label" style="font-size:0.65rem;display:none;max-width:90px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></span>
                        </div>

                        <!-- Chart Toggle Button -->
                        <button id="toggle-pmt-chart-btn" class="btn btn-sm btn-outline-primary fw-semibold d-flex align-items-center gap-1" title="Toggle Payment Terms Chart">
                            <i class="fas fa-chart-pie"></i>
                            <span>Chart</span>
                        </button>

                        <span class="vr align-self-stretch opacity-25"></span>
                        <span id="top-row-counter" class="text-muted small">Showing 0-0 of 0 rows</span>
                    </div>

                    <!-- ── Row 2: Searches + Filters ── -->
                        <div class="d-flex align-items-center flex-wrap gap-2">
                        <!-- Column Searches -->
                        <input type="text" id="search-sku"      class="form-control form-control-sm border-primary" placeholder="SKU…"      autocomplete="off" style="width:140px;">
                        <input type="text" id="search-parent"   class="form-control form-control-sm border-primary" placeholder="Parent…"   autocomplete="off" style="width:130px;">
                        <input type="text" id="search-supplier" class="form-control form-control-sm border-primary" placeholder="Supplier…" autocomplete="off" style="width:130px;">

                        <span class="vr align-self-stretch opacity-25"></span>

                        <!-- Stage Filter -->
                        <select id="stage-filter" class="form-select form-select-sm border border-primary" style="width:140px;">
                                <option value="">All</option>
                                <option value="__blank__">Not Req Now</option>
                                <option value="two_ord_nonneg">2 Ord</option>
                                <option value="appr_req">Appr Req</option>
                                <option value="mip">MIP</option>
                                <option value="r2s">R2S</option>
                                <option value="transit">Trn</option>
                                <option value="to_order_analysis">Order</option>
                            </select>
                        <span id="stage-filter-badge" style="display:none;background:#0d6efd;color:#fff;font-size:0.78rem;font-weight:700;border-radius:20px;padding:3px 10px;white-space:nowrap;box-shadow:0 1px 4px rgba(13,110,253,.35);"></span>

                        <!-- Row Type -->
                        <select id="row-data-type" class="form-select form-select-sm border border-primary" style="width:150px;" aria-label="Row type"></select>

                        <!-- NRP multiselect -->
                        <div class="dropdown d-inline-block">
                            <button class="btn btn-sm btn-light border border-primary dropdown-toggle" type="button" id="nrp-filter-dropdown" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" style="min-width:120px;">
                                <span id="nrp-filter-label">ALL Items</span>
                                </button>
                            <ul class="dropdown-menu shadow-sm p-2" style="min-width:170px;" aria-labelledby="nrp-filter-dropdown">
                                <li class="small text-muted px-2 mb-1">Show item types</li>
                                <li><label class="dropdown-item-text mb-0 d-flex align-items-center gap-2 cursor-pointer"><input type="checkbox" class="form-check-input nrp-ms-opt flex-shrink-0" value="REQ" checked><span>REQ</span></label></li>
                                <li><label class="dropdown-item-text mb-0 d-flex align-items-center gap-2 cursor-pointer"><input type="checkbox" class="form-check-input nrp-ms-opt flex-shrink-0" value="NR" checked><span>2BDC</span></label></li>
                                <li><label class="dropdown-item-text mb-0 d-flex align-items-center gap-2 cursor-pointer"><input type="checkbox" class="form-check-input nrp-ms-opt flex-shrink-0" value="LATER" checked><span>LATER</span></label></li>
                            </ul>
                            </div>

                        <!-- Appr Req filter -->
                            <div class="dropdown">
                            <button class="btn btn-sm btn-warning dropdown-toggle fw-semibold text-dark px-2" type="button" id="order-color-filter-dropdown" data-bs-toggle="dropdown" aria-expanded="false" title="Appr Req. filter">
                                <span class="d-none" aria-hidden="true"><i class="bi bi-funnel-fill"></i><span id="appr-req-badge-label">All</span></span>
                                    <span class="vr align-self-stretch my-n1 opacity-50 d-none" aria-hidden="true"></span>
                                <i class="bi bi-star-fill"></i> <span id="yellow-count-box">Appr Req: 0</span>
                                </button>
                                <ul class="dropdown-menu p-2 shadow-lg border rounded-3">
                                    <li><button class="dropdown-item" type="button" data-filter="">All</button></li>
                                    <li><button class="dropdown-item" type="button" data-filter="yellow">Appr Req.</button></li>
                                </ul>
                            </div>

                        <!-- Column Management -->
                        <div class="dropdown">
                            <button class="btn btn-sm btn-primary d-flex align-items-center gap-1" type="button" id="hide-column-dropdown" title="Manage Columns">
                                <svg width="14" height="14" viewBox="0 0 14 14" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                                    <rect x="0"  y="0" width="2.5" height="14" rx="1"/>
                                    <rect x="3.8" y="0" width="2.5" height="14" rx="1"/>
                                    <rect x="7.6" y="0" width="2.5" height="14" rx="1"/>
                                    <rect x="11.4" y="0" width="2.5" height="14" rx="1"/>
                                </svg>
                                </button>
                            </div>

                        <!-- Export Button -->
                        <button id="export-forecast-btn" class="btn btn-sm btn-success fw-semibold d-flex align-items-center gap-1" title="Export filtered rows: Supplier, SKU, Image, QTY, Order Date">
                            <i class="fas fa-file-csv"></i>
                            <span>Export</span>
                            </button>
                    </div>

                    <!-- ── Row 3: Value badges ── -->
                    <div class="d-flex align-items-center flex-wrap gap-2">
                        <button id="total_msl_c"       class="btn btn-sm btn-success fw-semibold text-dark"> MSL_LP: $<span id="total_msl_c_value">0.00</span></button>
                        <button type="button"           class="btn btn-sm btn-info fw-semibold text-dark" title="MSL × AMZ price ÷ 4"> MSL_SP: $<span id="total_msl_sp_amz_value">0</span></button>
                        <button id="total_inv_value"    class="btn btn-sm btn-info fw-semibold text-dark"> INV Val: $<span id="total_inv_value_display">0</span></button>
                        <button id="total_lp_value"     class="btn btn-sm btn-warning fw-semibold text-dark"> LP Val: $<span id="total_lp_value_display">0</span></button>
                        <button id="total_order_value"  class="btn btn-sm btn-warning fw-semibold text-dark" title="2 Ord × CP"> Ord Val: $<span id="total_order_value_display">0</span></button>
                        <button id="total_minimal_msl"  class="btn btn-sm btn-secondary fw-semibold text-white">Missing Sales: $<span id="total_minimal_msl_value">0</span></button>
                        <button id="total_mip_value"    class="btn btn-sm btn-warning fw-semibold text-dark"> MIP Val: $<span id="total_mip_value_display">0</span></button>
                        <button id="total_r2s_value"    class="btn btn-sm btn-warning fw-semibold text-dark"> R2S Val: $<span id="total_r2s_value_display">0</span></button>
                        <button id="total_transit_value" class="btn btn-sm btn-secondary fw-semibold text-dark"> Trn Val: $<span id="total_transit_value_display">0</span></button>
                    </div>

                    <!-- Bulk edit badge (shown when rows selected) -->
                    <div id="bulk-edit-badge" class="d-none mb-2 p-2 rounded border bg-light d-flex align-items-center gap-2 flex-wrap" style="min-height: 40px;">
                        <span class="fw-semibold text-dark" id="bulk-edit-count">0 selected</span>
                        <div class="d-flex align-items-center gap-2">
                            <select id="bulk-current-supplier-select" class="form-select form-select-sm select-searchable" style="min-width: 180px;">
                                <option value="">Select supplier...</option>
                            </select>
                            <button class="btn btn-sm btn-secondary" type="button" id="bulk-apply-current-supplier">
                                <i class="fas fa-check me-1"></i> Apply
                            </button>
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-info dropdown-toggle" type="button" id="bulkEditStageBtn" data-bs-toggle="dropdown" aria-expanded="false">
                                Stage
                            </button>
                            <ul class="dropdown-menu" aria-labelledby="bulkEditStageBtn">
                                <li class="px-3 py-2">
                                    <select id="bulk-stage-select" class="form-select form-select-sm" style="min-width: 160px;">
                                        <option value="">Select stage...</option>
                                        <option value="appr_req">Appr Req</option>
                                        <option value="mip">MIP</option>
                                        <option value="r2s">R2S</option>
                                        <option value="transit">Trn</option>
                                        <option value="to_order_analysis">Order</option>
                                    </select>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <button class="dropdown-item" type="button" id="bulk-apply-stage">
                                        <i class="fas fa-check me-1"></i> Apply
                                    </button>
                                </li>
                            </ul>
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-dark dropdown-toggle" type="button" id="bulkEditNrpBtn" data-bs-toggle="dropdown" aria-expanded="false">
                                NRP
                            </button>
                            <ul class="dropdown-menu" aria-labelledby="bulkEditNrpBtn">
                                <li class="px-3 py-2">
                                    <select id="bulk-nrp-select" class="form-select form-select-sm" style="min-width: 150px;">
                                        <option value="">Select NRP...</option>
                                        <option value="REQ">REQ</option>
                                        <option value="NR">2BDC</option>
                                        <option value="LATER">LATER</option>
                                    </select>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <button class="dropdown-item" type="button" id="bulk-apply-nrp">
                                        <i class="fas fa-check me-1"></i> Apply
                                    </button>
                                </li>
                            </ul>
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-warning dropdown-toggle" type="button" id="bulkEditMoqBtn" data-bs-toggle="dropdown" aria-expanded="false">
                                MOQ
                            </button>
                            <ul class="dropdown-menu" aria-labelledby="bulkEditMoqBtn">
                                <li class="px-3 py-2">
                                    <input type="number" id="bulk-moq-input" class="form-control form-control-sm" placeholder="Enter MOQ" style="min-width: 140px;">
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <button class="dropdown-item" type="button" id="bulk-apply-moq">
                                        <i class="fas fa-check me-1"></i> Apply
                                    </button>
                                </li>
                            </ul>
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-primary dropdown-toggle" type="button" id="bulkEditOrderBtn" data-bs-toggle="dropdown" aria-expanded="false">
                                Order
                            </button>
                            <ul class="dropdown-menu" aria-labelledby="bulkEditOrderBtn">
                                <li class="px-3 py-2">
                                    <input type="number" id="bulk-order-input" class="form-control form-control-sm" placeholder="Enter Order" style="min-width: 140px;">
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <button class="dropdown-item" type="button" id="bulk-apply-order">
                                        <i class="fas fa-check me-1"></i> Apply
                                    </button>
                                </li>
                            </ul>
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-success dropdown-toggle" type="button" id="bulkEditCpBtn" data-bs-toggle="dropdown" aria-expanded="false">
                                CP
                            </button>
                            <ul class="dropdown-menu" aria-labelledby="bulkEditCpBtn">
                                <li class="px-3 py-2">
                                    <input type="number" step="0.01" id="bulk-cp-input" class="form-control form-control-sm" placeholder="Enter CP" style="min-width: 140px;">
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <button class="dropdown-item" type="button" id="bulk-apply-cp">
                                        <i class="fas fa-check me-1"></i> Apply
                                    </button>
                                </li>
                            </ul>
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" id="bulkEditPmtTermsBtn" data-bs-toggle="dropdown" aria-expanded="false">
                                Pmt Terms
                            </button>
                            <ul class="dropdown-menu" aria-labelledby="bulkEditPmtTermsBtn">
                                <li class="px-3 py-2">
                                    <select id="bulk-pmt-terms-select" class="form-select form-select-sm" style="min-width: 140px;">
                                        <option value="">Select…</option>
                                        <option value="BL">🔴 BL</option>
                                        <option value="AL">🟡 AL</option>
                                        <option value="BRBL">🟢 BRBL</option>
                                    </select>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <button class="dropdown-item" type="button" id="bulk-apply-pmt-terms">
                                        <i class="fas fa-check me-1"></i> Apply
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div id="forecast-table-wrap" class="flex-grow-1" style="min-height: 0;">
                        <!-- Payment Terms Pie Chart (hidden until Chart button clicked) -->
                        <div id="pmt-chart-row" class="align-items-center gap-3 px-1 py-2 mb-1 rounded border bg-white" style="display:none;">
                            <div style="position:relative;width:140px;height:140px;flex-shrink:0;">
                                <canvas id="pmt-terms-chart"></canvas>
                            </div>
                            <div class="d-flex flex-column gap-1">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="fw-bold text-dark" style="font-size:0.8rem;">Payment Terms</span>
                                    <span id="pmt-chart-scope" class="badge bg-secondary" style="font-size:0.68rem;"></span>
                                </div>
                                <div id="pmt-chart-legend" class="d-flex flex-wrap gap-2" style="font-size:0.75rem;max-width:340px;"></div>
                                <div id="pmt-chart-nodata" class="text-muted small" style="display:none;">No payment term data available.</div>
                            </div>
                        </div>
                        <div id="forecast-table"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    {{-- month view modal --}}
    <div class="modal fade" id="monthModal" tabindex="-1" aria-labelledby="monthModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content border-0 shadow-sm">
                <div class="modal-header bg-info text-white d-flex justify-content-between align-items-center">
                    <h5 class="modal-title mb-0">MONTH VIEW <span id="month-view-sku" class="ms-1"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body" id="monthModalBody">
                    <div class="d-flex justify-content-between gap-2 flex-nowrap w-100 px-3" id="monthCardWrapper"
                        style="overflow-x: auto;">
                        <!-- Month cards inserted here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Metric View Modal -->
    <div class="modal fade" id="metricModal" tabindex="-1" aria-labelledby="metricModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content border-0">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title fw-bold" id="metricModalLabel">METRIC VIEW</h5>
                    <button type="button" class="btn-close text-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="metricCardWrapper" class="d-flex justify-content-between gap-2 flex-nowrap w-100 px-3">
                        <!-- Metric cards will be appended here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- link edit modal --}}
    <div class="modal fade" id="linkEditModal" tabindex="-1" aria-labelledby="linkEditModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg shadow-none">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-primary text-white border-0">
                    <h5 class="modal-title" id="linkEditModalLabel">
                        <i class="fas fa-link me-2"></i>
                        <span>Edit Link</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label id="linkLabel" class="form-label fw-lg mb-2">Link URL:</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-link text-primary"></i>
                            </span>
                            <input type="url" class="form-control form-control-lg border-start-0 ps-2"
                                id="linkEditInput" placeholder="Enter URL here..." autocomplete="off"
                                spellcheck="false">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-primary" id="saveLinkBtn">
                        <i class="fas fa-check me-1"></i>
                        Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Edit Notes Modal --}}
    <div class="modal fade" id="editNotesModal" tabindex="-1" role="dialog" aria-labelledby="editNotesLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg shadow-none" role="document">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-primary text-white border-0">
                    <h5 class="modal-title" id="editNotesLabel">
                        <i class="fas fa-edit me-2"></i> Edit Notes
                    </h5>
                    <button type="button" class="close text-white custom-close" data-bs-dismiss="modal"
                        aria-label="Close" style="font-size:25px; background-color: transparent; border: none;">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <textarea id="notesInput" class="form-control form-control-lg shadow-none mb-4" rows="3"
                        placeholder="Type your note here..." style="resize: vertical;"></textarea>
                    <div class="text-end">
                        <button type="button" class="btn btn-primary" id="saveNotesBtn">
                            <i class="fas fa-save me-2"></i> Save Changes
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="scouthProductsModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Scout Products View</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- dynamic content here -->
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="columnCustomizeModal" tabindex="-1" aria-labelledby="columnCustomizeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width:520px;">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title fw-semibold" id="columnCustomizeModalLabel"><i class="bi bi-layout-three-columns me-2"></i>Show / Hide Columns</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="max-height:65vh;overflow-y:auto;">
                    <div id="columnCheckboxList" class="row row-cols-2 row-cols-sm-3 g-2"></div>
                            </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="columnShowAllBtn">Show All</button>
                    <button type="button" class="btn btn-sm btn-outline-danger" id="columnHideAllBtn">Hide All</button>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-dismiss="modal">Done</button>
                        </div>
            </div>
        </div>
    </div>

@endsection

@section('script')
    <script src="{{ asset('js/select-searchable.js') }}"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
    <script>
        document.body.style.zoom = "95%";
        
        // Debounce utility to prevent rapid fire AJAX calls
        let debounceTimers = {};
        function debounce(key, callback, delay = 300) {
            if (debounceTimers[key]) {
                clearTimeout(debounceTimers[key]);
            }
            debounceTimers[key] = setTimeout(callback, delay);
        }

        /** POST inline forecast updates (used by Tabulator MOQ editor and other handlers). */
        function updateForecastField(data, onSuccess, onFail) {
            onSuccess = typeof onSuccess === 'function' ? onSuccess : function() {};
            onFail = typeof onFail === 'function' ? onFail : function() {};
            $.post('/update-forecast-data', {
                ...data,
                _token: $('meta[name="csrf-token"]').attr('content')
            }).done(function(res) {
                if (res.success) {
                    if (res.message) console.log('Saved:', res.message);
                    onSuccess();
                } else {
                    console.warn('Not saved:', res.message);
                    onFail();
                }
            }).fail(function(err) {
                console.error('AJAX failed:', err);
                alert('Error saving data.');
                onFail();
            });
        }
        
        /** DIL % display: text color only (red / dark green / magenta). */
        const getDilTextColor = (ratio) => {
            const percent = parseFloat(ratio) * 100;
            if (percent < 16.66) {
                return '#b71c1c';
            }
            if (percent < 50) {
                return '#1b5e20';
            }

            return '#ad1457';
        };

        const getPftColor = (value) => {
            const percent = parseFloat(value) * 100;
            if (percent < 10) return 'red';
            if (percent >= 10 && percent < 15) return 'yellow';
            if (percent >= 15 && percent < 20) return 'blue';
            if (percent >= 20 && percent <= 40) return 'green';
            return 'pink';
        };

        const getRoiColor = (value) => {
            const percent = parseFloat(value) * 100;
            if (percent >= 0 && percent < 50) return 'red';
            if (percent >= 50 && percent < 75) return 'yellow';
            if (percent >= 75 && percent <= 100) return 'green';
            return 'pink';
        };

        //global variables for play btn
        let groupedSkuData = {};
        let currentOrderPositiveCount = 0;
        let currentMipPositiveCount = 0;
        let currentR2sPositiveCount = 0;
        let currentTransitPositiveCount = 0;
        let currentMoqPositiveCount = 0;
        function isSelectableForecastRow(row) {
            if (!row) return false;
            const data = (typeof row.getData === 'function') ? row.getData() : (row || {});
            const sku = (data.SKU || '').toString().toLowerCase();
            return sku.indexOf('parent') === -1;
        }
        function updateOrderColumnHeader(count) {
            currentOrderPositiveCount = Number.isFinite(count) ? count : 0;
            table.updateColumnDefinition("to_order", {
                title: "2 Ord (" + currentOrderPositiveCount + ")"
            });
        }
        function updateMipColumnHeader(count) {
            currentMipPositiveCount = Number.isFinite(count) ? count : 0;
            table.updateColumnDefinition("order_given", {
                title: "MIP"
            });
        }
        function updateR2sColumnHeader(count) {
            currentR2sPositiveCount = Number.isFinite(count) ? count : 0;
            table.updateColumnDefinition("readyToShipQty", {
                title: "R2S"
            });
        }
        function updateTransitColumnHeader(count) {
            currentTransitPositiveCount = Number.isFinite(count) ? count : 0;
            table.updateColumnDefinition("transit", {
                title: "Trn"
            });
        }
        function updateTopQtyFilterOptionCounts(counts) {
            const c = counts || {};
            const defs = [
                { id: 'order-color-filter-top', all: 'Order: All', pos: 'Order: > 0', n: Number.isFinite(c.order) ? c.order : 0 },
                { id: 'mip-color-filter-top', all: 'MIP: All', pos: 'MIP: > 0', n: Number.isFinite(c.mip) ? c.mip : 0 },
                { id: 'r2s-color-filter-top', all: 'R2S: All', pos: 'R2S: > 0', n: Number.isFinite(c.r2s) ? c.r2s : 0 },
                { id: 'trn-color-filter-top', all: 'Trn: All', pos: 'Trn: > 0', n: Number.isFinite(c.trn) ? c.trn : 0 },
                { id: 'moq-color-filter-top', all: 'MOQ: All', pos: 'MOQ: > 0', n: Number.isFinite(c.moq) ? c.moq : 0 },
            ];
            defs.forEach(function(def) {
                const sel = document.getElementById(def.id);
                if (!sel) return;
                const allOpt = sel.querySelector('option[value=""]');
                const posOpt = sel.querySelector('option[value="pos"]');
                if (allOpt) allOpt.textContent = def.all;
                if (posOpt) posOpt.textContent = def.pos + ' (' + def.n + ')';
            });
        }
        const table = new Tabulator("#forecast-table", {
            ajaxURL: "/forecast-analysis-data-view",
            ajaxConfig: "GET",
            layout: "fitDataFill",
            pagination: true,
            paginationSize: 100,
            initialSort: [{ column: "mfrg_order_date", dir: "asc" }],
            initialHeaderFilter: [{ field: "nr", value: "" }, { field: "stage", value: "" }, { field: "INV", value: "" }],
            paginationCounter: "rows",
            movableColumns: true,
            resizableColumns: true,
            height: 600,
            index: "SKU",
            editTriggerEvent: "dblclick",
            rowFormatter: function(row) {
                const data = row.getData();
                const sku = data["SKU"] || '';

                if (sku.toUpperCase().includes("PARENT")) {
                    row.getElement().classList.add("parent-row");
                }
            },
            columns: [
                {
                    title: "#",
                    field: "Image",
                    headerSort: true,
                    formatter: function(cell) {
                        const url = cell.getValue();
                        if (!url) return `<span class="text-muted">N/A</span>`;
                        const fallbackSvg = "data:image/svg+xml;utf8," + encodeURIComponent(
                            "<svg xmlns='http://www.w3.org/2000/svg' width='30' height='30'><rect width='100%' height='100%' fill='%23f3f4f6'/><text x='50%' y='52%' font-size='8' text-anchor='middle' fill='%239ca3af' font-family='Arial'>No Img</text></svg>"
                        );
                        return `<img 
                        src="${url}" 
                        data-full="${url}" 
                        class="hover-thumb" 
                        onerror="this.onerror=null;this.dataset.full='';this.src='${fallbackSvg}';"
                        style="width:30px;height:30px;border-radius:6px;object-fit:contain;box-shadow:0 1px 4px #0001;cursor: pointer;"
                    >`;
                    },
                    cellMouseOver: function(e, cell) {
                        const img = cell.getElement().querySelector('.hover-thumb');
                        if (!img) return;

                        const fullUrl = img.getAttribute('data-full');
                        if (!fullUrl) return;

                        let preview = document.createElement('div');
                        preview.id = 'image-hover-preview';
                        preview.style.position = 'fixed';
                        preview.style.top = `${e.clientY + 10}px`;
                        preview.style.left = `${e.clientX + 10}px`;
                        preview.style.zIndex = 9999;
                        preview.style.border = '1px solid #ccc';
                        preview.style.background = '#fff';
                        preview.style.padding = '4px';
                        preview.style.boxShadow = '0 2px 8px rgba(0,0,0,0.2)';
                        preview.innerHTML =
                            `<img src="${fullUrl}" style="max-width:350px;max-height:350px;">`;

                        document.body.appendChild(preview);
                    },
                    cellMouseOut: function(e, cell) {
                        const preview = document.getElementById('image-hover-preview');
                        if (preview) preview.remove();
                    },
                    width: 52,
                    minWidth: 48,
                    maxWidth: 56,
                    widthGrow: 0
                },
                {
                    title: "Parent",
                    field: "Parent",
                    width: 92,
                    minWidth: 72,
                    maxWidth: 180,
                    widthGrow: 0,
                    accessor: row => (row ? row["Parent"] : ''),
                    formatter: function(cell) {
                        const v = cell.getValue() == null ? '' : String(cell.getValue());
                        const esc = v.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                        const title = esc.replace(/"/g, '&quot;');
                        const safeParent = v.replace(/'/g, "\\'").replace(/"/g, '&quot;');
                        if (!v) return '<span style="display:block;text-align:center;color:#6c757d;">-</span>';
                        const copyBtn = `<i class="fa fa-copy forecast-copy-parent" data-parent="${safeParent}" title="Copy Parent" style="cursor:pointer;margin-left:5px;color:#6c757d;font-size:11px;flex-shrink:0;"></i>`;
                        return `<span title="${title}" style="display:flex;align-items:center;overflow:hidden;min-width:0;"><span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;min-width:0;">${esc}</span>${copyBtn}</span>`;
                    }
                },


           
                {
                    title: "SKU",
                    field: "SKU",
                    frozen: true,
                    movable: false,
                    resizable: false,
                    minWidth: 180,
                    widthGrow: 1,
                    accessor: row => (row ? row["SKU"] : ''),
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData() || {};
                        const v = cell.getValue() == null ? '' : String(cell.getValue());
                        const esc = v.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                        const title = esc.replace(/"/g, '&quot;');
                        const safeSku = v.replace(/'/g, "\\'").replace(/"/g, '&quot;');

                        // Copy icon
                        const copyBtn = `<i class="fa fa-copy forecast-copy-sku" data-sku="${safeSku}" title="Copy SKU" style="cursor:pointer;margin-left:6px;color:#6c757d;font-size:12px;flex-shrink:0;"></i>`;

                        // B / S links
                        const buyerLink  = String(rowData.Clink || '').trim();
                        const sellerLink = String(rowData.Olink || '').trim();
                        const buyerBtn   = buyerLink
                            ? `<a href="${buyerLink}" target="_blank" class="btn btn-sm btn-outline-primary" title="Buyer Link" style="padding:1px 6px;font-size:11px;line-height:1.4;flex-shrink:0;">B</a>`
                            : '';
                        const sellerBtn  = sellerLink
                            ? `<a href="${sellerLink}" target="_blank" class="btn btn-sm btn-outline-secondary" title="Seller Link" style="padding:1px 6px;font-size:11px;line-height:1.4;flex-shrink:0;">S</a>`
                            : '';
                        const linkPart   = (buyerBtn || sellerBtn)
                            ? `<span style="display:flex;gap:4px;flex-shrink:0;">${buyerBtn}${sellerBtn}</span>`
                            : '';

                        return `<div style="display:flex;align-items:center;gap:4px;min-width:0;">
                            <span title="${title}" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;min-width:0;">${esc}</span>
                            ${copyBtn}
                            ${linkPart}
                        </div>`;
                    }
                },
                {
                    formatter: "rowSelection",
                    titleFormatter: function(cell) {
                        const checkbox = document.createElement("input");
                        checkbox.type = "checkbox";
                        checkbox.style.margin = "0";
                        checkbox.style.cursor = "pointer";
                        checkbox.addEventListener("click", function(e) {
                            e.stopPropagation();
                            const activeRows = cell.getTable().getRows("active").filter(isSelectableForecastRow);
                            if (checkbox.checked) {
                                activeRows.forEach(function(row) { row.select(); });
                            } else {
                                cell.getTable().deselectRow();
                            }
                        });
                        return checkbox;
                    },
                    hozAlign: "center",
                    headerSort: false,
                    width: 40,
                    minWidth: 40,
                    movable: false,
                    cellClick: function(e, cell) {
                        e.stopPropagation();
                        const row = cell.getRow();
                        if (!isSelectableForecastRow(row)) return;
                        row.toggleSelect();
                    }
                },
                
                {
                    title: "INV",
                    field: "INV",
                    accessor: row => (row ? row["INV"] : 0),
                    headerSort: true,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        return `<span style="display:block; text-align:center;">${value}</span>`;
                    }
                },
                // {
                //     title: "Shopify Price",
                //     field: "shopifyb2c_price",
                //     accessor: row => row["shopifyb2c_price"],
                //     formatter: function(cell) {
                //         const value = cell.getValue() || 0;
                //         const roundedValue = (value);
                //         return `<span style="display:block; text-align:center; font-weight:bold;">$${roundedValue.toLocaleString()}</span>`;
                //     }
                // },
                // {
                //     title: "INV Value",
                //     field: "inv_value",
                //     accessor: row => row["inv_value"],
                //     formatter: function(cell) {
                //         const value = cell.getValue() || 0;
                //         const roundedValue = Math.round(parseFloat(value));
                //         return `<span style="display:block; text-align:center; font-weight:bold;">$${roundedValue.toLocaleString()}</span>`;
                //     }
                // },
                // {
                //     title: "LP Value",
                //     field: "lp_value",
                //     accessor: row => row["lp_value"],
                //     formatter: function(cell) {
                //         const value = cell.getValue() || 0;
                //         const roundedValue = Math.round(parseFloat(value));
                //         return `<span style="display:block; text-align:center; font-weight:bold;">$${roundedValue.toLocaleString()}</span>`;
                //     }
                // },
               
                {
                    title: "l30",
                    field: "L30",
                    accessor: row => (row ? row["L30"] : 0),
                    formatter: function(cell) {
                        const value = cell.getValue();
                        return `<span style="display:block; text-align:center;">${value}</span>`;
                    }
                },
                {
                    title: "DIL",
                    field: "ov_dil",
                    headerSort: true,
                    accessor: function(row) {
                        if (!row) return 0;
                        const l30 = parseFloat(row.L30);
                        const inv = parseFloat(row.INV);
                        if (!isNaN(l30) && !isNaN(inv) && inv !== 0) {
                            return l30 / inv;
                        }

                        return 0;
                    },
                    sorter: "number",
                    formatter: function(cell) {
                        const data = cell.getData();
                        const l30 = parseFloat(data.L30);
                        const inv = parseFloat(data.INV);

                        if (!isNaN(l30) && !isNaN(inv) && inv !== 0) {
                            const dilDecimal = (l30 / inv);
                            const col = getDilTextColor(dilDecimal);
                            return `<div class="text-center"><span class="forecast-dil-pct" style="color:${col};">${Math.round(dilDecimal * 100)}%</span></div>`;
                        }
                        return `<div class="text-center"><span class="forecast-dil-pct" style="color:#b71c1c;">0%</span></div>`;
                    }
                },

                {
                    title: "MSL",
                    field: "msl",
                    accessor: row => (row && (row["msl"] !== undefined && row["msl"] !== null)) ? row["msl"] : 0,
                    formatter: function(cell) {
                        const value = cell.getValue() || 0;
                        return `
                        <div style="text-align:center; font-weight:bold;">
                            ${value}
                            <button class="btn btn-sm btn-link text-info open-month-modal" style="padding: 0 4px;" title="View Monthly">
                                <i class="bi bi-calendar3"></i>
                            </button>
                        </div>
                    `;
                    },
                    cellClick: function(e, cell) {
                        if (e.target.closest(".open-month-modal")) {
                            const row = cell.getRow().getData();
                            const sku = row["SKU"] || '';
                            const monthData = {
                                "JAN": row["Jan"] ?? 0,
                                "FEB": row["Feb"] ?? 0,
                                "MAR": row["Mar"] ?? 0,
                                "APR": row["Apr"] ?? 0,
                                "MAY": row["May"] ?? 0,
                                "JUN": row["Jun"] ?? 0,
                                "JUL": row["Jul"] ?? 0,
                                "AUG": row["Aug"] ?? 0,
                                "SEP": row["Sep"] ?? 0,
                                "OCT": row["Oct"] ?? 0,
                                "NOV": row["Nov"] ?? 0,
                                "DEC": row["Dec"] ?? 0
                            };
                            openMonthModal(monthData, sku);
                        }
                    }
                },
                {
                    title: "MSL Manual",
                    field: "s_msl",
                    accessor: row => (row && row["s_msl"] !== undefined && row["s_msl"] !== null) ? row["s_msl"] : '',
                    hozAlign: "center",
                    editable: function(cell) {
                        const d = cell.getRow().getData() || {};
                        return !(d.is_parent || d.isParent);
                    },
                    editor: "input",
                    editorParams: {
                        elementAttributes: {
                            maxlength: "4"
                        }
                    },
                    formatter: function(cell) {
                        const v = cell.getValue();
                        const s = String(v == null ? '' : v).trim();
                        if (!s) return '<div style="text-align:center;" class="text-muted">—</div>';
                        return `<div style="text-align:center;font-weight:700;">${s}</div>`;
                    },
                    cellEditing: function(cell) {
                        cell.getRow().forecastMslManualEditStart = cell.getValue();
                    },
                    cellEdited: function(cell) {
                        const row = cell.getRow();
                        const d = row.getData() || {};
                        if (d.is_parent || d.isParent) return;
                        const oldVal = row.forecastMslManualEditStart;
                        delete row.forecastMslManualEditStart;

                        const next = String(cell.getValue() == null ? '' : cell.getValue()).trim().slice(0, 4);
                        const prev = String(oldVal == null ? '' : oldVal).trim().slice(0, 4);
                        if (next === prev) {
                            cell.setValue(next, true);
                            return;
                        }
                        cell.setValue(next, true);

                        const sku = d.SKU || '';
                        const parent = d.Parent || '';
                        updateForecastField(
                            { sku: sku, parent: parent, column: 'S-MSL', value: next },
                            function() {
                                row.update({ s_msl: next }, true);
                            },
                            function() {
                                cell.setValue(prev, true);
                            }
                        );
                    }
                },
                {
                    title: "2 Ord",
                    field: "to_order",
                    formatter: function(cell) {
                        const raw = cell.getValue();
                        const n = parseFloat(raw);
                        const disp = Number.isFinite(n) ? n : raw;
                        const isNeg = Number.isFinite(n) && n < 0;
                        const col = isNeg ? '#b71c1c' : '#e6aa19';

                        return `<div class="text-center"><span class="forecast-to-order-pct" style="color:${col};">${disp}</span></div>`;
                    }
                },
                {
                    title: "M AVG ",
                    field: "MSL_Four",
                    accessor: row => (row ? row["MSL_Four"] : null),
                    formatter: function(cell) {
                        const value = cell.getValue() || 0;
                        return `<div style="text-align:center; font-weight:bold;">${value.toFixed(0)}</div>`;
                    }
                },
                {
                    title: "Stage",
                    field: "stage",
                    minWidth: 48,
                    width: 52,
                    maxWidth: 64,
                    widthGrow: 0,
                    hozAlign: "center",
                    accessor: function(row) {
                        const stageValue = row?.["stage"] ?? '';
                        return stageValue ? String(stageValue).trim().toLowerCase() : '';
                    },
                    headerSort: true,
                    formatter: function(cell) {
                        let value = cell.getValue() ?? '';
                        value = String(value).trim().toLowerCase();
                        const rowData = cell.getRow().getData();
                        const sku = (rowData["SKU"] || '').replace(/'/g, "\\'");
                        const parent = (rowData["Parent"] || '').replace(/'/g, "\\'");

                        const stageTips = {
                            '': 'Select stage',
                            'appr_req': 'Appr Req — Approval',
                            'mip': 'MIP',
                            'r2s': 'R2S — Ready to ship',
                            'transit': 'Transit',
                            'to_order_analysis': 'Order — 2 Order'
                        };
                        const tip = stageTips[value] || 'Select stage';
                        const tipAttr = String(tip).replace(/&/g, '&amp;').replace(/"/g, '&quot;');

                        let markerHtml = '';
                        if (value === 'transit') {
                            markerHtml = '<i class="bi bi-truck stage-transit-icon" aria-hidden="true"></i>';
                        } else {
                            let dotColor = '#94a3b8';
                            if (value === 'appr_req') dotColor = '#facc15';
                            else if (value === 'mip') dotColor = '#2563eb';
                            else if (value === 'to_order_analysis') dotColor = '#c2410c';
                            else if (value === 'r2s') dotColor = '#16a34a';
                            markerHtml = '<span class="stage-status-dot" style="background-color:' + dotColor + ';" aria-hidden="true"></span>';
                        }

                        return (
                            '<div class="stage-dot-cell position-relative d-flex justify-content-center align-items-center w-100" title="' + tipAttr + '">' +
                            markerHtml +
                            '<select class="form-select form-select-sm editable-select stage-stage-select position-absolute top-0 start-0 w-100 h-100"' +
                            ' data-type="Stage"' +
                            ' data-sku=\'' + sku + '\'' +
                            ' data-parent=\'' + parent + '\'' +
                            ' aria-label="' + tipAttr + '">' +
                            '<option value="">Not Req Now</option>' +
                            '<option value="appr_req"' + (value === 'appr_req' ? ' selected' : '') + '>Appr Req</option>' +
                            '<option value="mip"' + (value === 'mip' ? ' selected' : '') + '>MIP</option>' +
                            '<option value="r2s"' + (value === 'r2s' ? ' selected' : '') + '>R2S</option>' +
                            '<option value="transit"' + (value === 'transit' ? ' selected' : '') + '>Trn</option>' +
                            '<option value="to_order_analysis"' + (value === 'to_order_analysis' ? ' selected' : '') + '>Order</option>' +
                            '</select></div>'
                        );
                    },
                    // select value is already controlled by formatter selected options
                },
                {
                    title: "Appr Req",
                    field: "appr_req_qty",
                    accessor: row => (row ? row.appr_req_qty : null),
                    sorter: "number",
                    headerSort: true,
                    hozAlign: "center",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData() || {};
                        const isParent = !!(rowData.is_parent || rowData.isParent);
                        const skuAttr = String(rowData.SKU || '').replace(/'/g, "\\'");
                        const parentAttr = String(rowData.Parent || '').replace(/'/g, "\\'");
                        const renderWithMoveDot = function(valueText, isFallback) {
                            if (isParent) {
                                const bgParent = isFallback ? 'background:#fff3a0;border-radius:4px;padding:2px 4px;' : '';
                                return `<div style="text-align:center;font-weight:700;${bgParent}">${valueText}</div>`;
                            }
                            const bg = isFallback ? 'background:#fff3a0;border-radius:4px;padding:2px 4px;' : '';
                            return `<div style="text-align:center;font-weight:700;${bg}display:flex;align-items:center;justify-content:center;gap:6px;">
                                <span>${valueText}</span>
                                <button type="button" class="appr-req-move-dot" data-sku='${skuAttr}' data-parent='${parentAttr}' title="Move MOQ to Order" aria-label="Move MOQ to Order"
                                    style="width:10px;height:10px;border-radius:9999px;border:1px solid #b8860b;background:#facc15;padding:0;cursor:pointer;display:inline-block;line-height:1;"></button>
                            </div>`;
                        };
                        const v = parseFloat(cell.getValue());
                        if (!v || isNaN(v)) {
                            const fallbackApprReq = getEffectiveApprReqValue(rowData);
                            if (fallbackApprReq > 0) {
                                const dispMoq = Number.isInteger(fallbackApprReq) ? fallbackApprReq : fallbackApprReq.toFixed(2).replace(/\.?0+$/, '');
                                return renderWithMoveDot(dispMoq, true);
                            }
                            return '<div style="text-align:center;" class="text-muted">—</div>';
                        }
                        const disp = Number.isInteger(v) ? v : v.toFixed(2).replace(/\.?0+$/, '');
                        return renderWithMoveDot(disp, false);
                    }
                },
                {
                    title: "Order",
                    field: "two_order_qty",
                    accessor: row => (row ? row.two_order_qty : null),
                    sorter: "number",
                    headerSort: true,
                    editor: "number",
                    editorParams: { min: 0, step: 1, verticalNavigation: "editor" },
                    editable: function(cell) {
                        const d = cell.getRow().getData();
                        return !(d.is_parent || d.isParent);
                    },
                    cellClick: function(e, cell) {
                        const d = cell.getRow().getData();
                        if (d.is_parent || d.isParent) return;
                        if (e.target && e.target.classList.contains('order-to-mip-move-dot')) return;
                        cell.edit();
                    },
                    cellEditing: function(cell) {
                        const row = cell.getRow();
                        row.forecastOrderEditStart = cell.getValue();
                        setTimeout(function() {
                            const input = cell.getElement().querySelector('input, textarea');
                            if (input) {
                                input.focus();
                                input.select();
                            }
                        }, 0);
                    },
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const skuAttr = String(rowData.SKU || '').replace(/'/g, "\\'");
                        const parentAttr = String(rowData.Parent || '').replace(/'/g, "\\'");
                        const v = parseFloat(cell.getValue());
                        if (!v || isNaN(v)) {
                            return '<div style="text-align:center;" class="text-muted">—</div>';
                        }
                        const disp = Number.isInteger(v) ? v : v.toFixed(2).replace(/\.?0+$/, '');
                        if (rowData && (rowData.is_parent || rowData.isParent)) {
                            return `<div style="text-align:center;font-weight:bold;">${disp}</div>`;
                        }
                        return `<div style="text-align:center;font-weight:bold;display:flex;align-items:center;justify-content:center;gap:6px;">
                            <span style="cursor:text;" title="Click to edit Order">${disp}</span>
                            <button type="button" class="order-to-mip-move-dot" data-sku='${skuAttr}' data-parent='${parentAttr}' title="Move Order to MIP" aria-label="Move Order to MIP"
                                style="width:10px;height:10px;border-radius:9999px;border:1px solid #1e40af;background:#2563eb;padding:0;cursor:pointer;display:inline-block;line-height:1;flex-shrink:0;"></button>
                        </div>`;
                    },
                    cellEdited: function(cell) {
                        const row = cell.getRow();
                        const d = row.getData();
                        if (d.is_parent || d.isParent) return;

                        const rawNew = cell.getValue();
                        const oldVal = row.forecastOrderEditStart;
                        delete row.forecastOrderEditStart;

                        if (rawNew === '' || rawNew === null || rawNew === undefined) {
                            cell.setValue(oldVal, true);
                            alert('Please enter a valid Order quantity.');
                            return;
                        }

                        const newValue = Number(rawNew);
                        if (Number.isNaN(newValue) || newValue < 0) {
                            cell.setValue(oldVal, true);
                            alert('Please enter a valid Order quantity.');
                            return;
                        }

                        const origNum = Number(oldVal);
                        if (!Number.isNaN(origNum) && origNum === newValue) return;

                        updateForecastField(
                            { sku: d.SKU, parent: d.Parent || '', column: 'Order', value: newValue },
                            function() {
                                row.update({ two_order_qty: newValue }, true);
                                const stageCell = row.getCells().find(function(c) { return c.getField() === 'stage'; });
                                if (stageCell) stageCell.reformat();
                                syncParentStageQtyColumns(d.Parent || d.parentKey);
                            },
                            function() {
                                cell.setValue(oldVal, true);
                            }
                        );
                    }
                },
                //   {
                //     title: "S-MSL",
                //     field: "s_msl",
                //     headerSort: true,
                //     formatter: function(cell) {
                //         const value = cell.getValue();
                //         const rowData = cell.getRow().getData();

                //         const sku = rowData.SKU ?? '';
                //         const parent = rowData.Parent ?? '';

                //         return `<div 
                //         class="editable-qty" 
                //         contenteditable="true" 
                //         data-field="S-MSL"
                //         data-original="${value ?? ''}" 
                //         data-sku='${sku}' 
                //         data-parent='${parent}' 
                //         style="outline:none; min-width:50px; text-align:center;">
                //         ${value ?? ''}
                //     </div>`;
                //     }
                // },
                // {
                //     title: "MSL_VL",
                //     field: "MSL_C",
                //     accessor: row => row["MSL_C"],
                //     formatter: function(cell) {
                //         const value = cell.getValue() || 0;
                //         const wholeNumber = Math.round(parseFloat(value));
                //         return `<div style="text-align:center; font-weight:bold;">${wholeNumber}</div>`;
                //     },
                //     sum: function(cells) {
                //         return cells.reduce((acc, cell) => acc + (cell.getValue() || 0), 0);
                //     }
                // },
                
                {
                    title: "MIP",
                    field: "order_given",
                    accessor: row => (row ? row["order_given"] : null),
                    sorter: "number",
                    headerSort: true,
                    editor: "number",
                    editorParams: { min: 0, step: 1, verticalNavigation: "editor" },
                    editable: function(cell) {
                        const d = cell.getRow().getData();
                        return !(d.is_parent || d.isParent);
                    },
                    cellClick: function(e, cell) {
                        const d = cell.getRow().getData();
                        if (d.is_parent || d.isParent) return;
                        if (e.target && e.target.classList.contains('mip-to-r2s-move-dot')) return;
                        cell.edit();
                    },
                    cellEditing: function(cell) {
                        const row = cell.getRow();
                        row.forecastMipEditStart = cell.getValue();
                        setTimeout(function() {
                            const input = cell.getElement().querySelector('input, textarea');
                            if (input) {
                                input.focus();
                                input.select();
                            }
                        }, 0);
                    },
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const skuAttr = String(rowData.SKU || '').replace(/'/g, "\\'");
                        const parentAttr = String(rowData.Parent || '').replace(/'/g, "\\'");
                        const value = cell.getValue();
                        const n = parseFloat(value);
                        const showDash = value === null || value === undefined || value === '' || isNaN(n) || n === 0;
                        if (showDash) return `<div style="text-align:center;font-weight:bold;cursor:text;">-</div>`;
                        if (rowData.is_parent || rowData.isParent) return `<div style="text-align:center;font-weight:bold;">${String(value)}</div>`;
                        return `<div style="text-align:center;font-weight:bold;display:flex;align-items:center;justify-content:center;gap:6px;">
                            <span style="cursor:text;" title="Click to edit MIP">${String(value)}</span>
                            <button type="button" class="mip-to-r2s-move-dot" data-sku='${skuAttr}' data-parent='${parentAttr}' title="Move MIP to R2S" aria-label="Move MIP to R2S"
                                style="width:10px;height:10px;border-radius:9999px;border:1px solid #15803d;background:#16a34a;padding:0;cursor:pointer;display:inline-block;line-height:1;flex-shrink:0;"></button>
                        </div>`;
                    },
                    cellEdited: function(cell) {
                        const row = cell.getRow();
                        const d = row.getData();
                        if (d.is_parent || d.isParent) return;

                        const rawNew = cell.getValue();
                        const oldVal = row.forecastMipEditStart;
                        delete row.forecastMipEditStart;

                        if (rawNew === '' || rawNew === null || rawNew === undefined) {
                            cell.setValue(oldVal, true);
                            alert('Please enter a valid MIP quantity.');
                            return;
                        }
                        const newValue = Number(rawNew);
                        if (Number.isNaN(newValue) || newValue < 0) {
                            cell.setValue(oldVal, true);
                            alert('Please enter a valid MIP quantity.');
                            return;
                        }
                        const origNum = Number(oldVal);
                        if (!Number.isNaN(origNum) && origNum === newValue) return;

                        updateForecastField(
                            { sku: d.SKU, parent: d.Parent || '', column: 'order_given', value: newValue },
                            function() {
                                row.update({ order_given: newValue }, true);
                                const stageCell = row.getCells().find(function(c) { return c.getField() === 'stage'; });
                                if (stageCell) stageCell.reformat();
                                syncParentStageQtyColumns(d.Parent || d.parentKey);
                            },
                            function() {
                                cell.setValue(oldVal, true);
                            }
                        );
                    }
                },
                {
                    title: "R2S",
                    field: "readyToShipQty",
                    accessor: row => (row ? row["readyToShipQty"] : null),
                    sorter: "number",
                    headerSort: true,
                    editor: "number",
                    editorParams: { min: 0, step: 1, verticalNavigation: "editor" },
                    editable: function(cell) {
                        const d = cell.getRow().getData();
                        return !(d.is_parent || d.isParent);
                    },
                    cellClick: function(e, cell) {
                        const d = cell.getRow().getData();
                        if (d.is_parent || d.isParent) return;
                        if (e.target && e.target.classList.contains('r2s-to-trn-move-dot')) return;
                        cell.edit();
                    },
                    cellEditing: function(cell) {
                        const row = cell.getRow();
                        row.forecastR2sEditStart = cell.getValue();
                        setTimeout(function() {
                            const input = cell.getElement().querySelector('input, textarea');
                            if (input) {
                                input.focus();
                                input.select();
                            }
                        }, 0);
                    },
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const skuAttr = String(rowData.SKU || '').replace(/'/g, "\\'");
                        const parentAttr = String(rowData.Parent || '').replace(/'/g, "\\'");
                        const value = cell.getValue();
                        const n = parseFloat(value);
                        const showDash = value === null || value === undefined || value === '' || isNaN(n) || n === 0;
                        if (showDash) return `<div style="text-align:center;font-weight:bold;cursor:text;">-</div>`;
                        if (rowData.is_parent || rowData.isParent) return `<div style="text-align:center;font-weight:bold;">${String(value)}</div>`;
                        return `<div style="text-align:center;font-weight:bold;display:flex;align-items:center;justify-content:center;gap:6px;">
                            <span style="cursor:text;" title="Click to edit R2S">${String(value)}</span>
                            <button type="button" class="r2s-to-trn-move-dot" data-sku='${skuAttr}' data-parent='${parentAttr}' title="Move R2S to TRN" aria-label="Move R2S to TRN"
                                style="width:10px;height:10px;border-radius:9999px;border:1px solid #9a3412;background:#ea580c;padding:0;cursor:pointer;display:inline-block;line-height:1;flex-shrink:0;"></button>
                        </div>`;
                    },
                    cellEdited: function(cell) {
                        const row = cell.getRow();
                        const d = row.getData();
                        if (d.is_parent || d.isParent) return;

                        const rawNew = cell.getValue();
                        const oldVal = row.forecastR2sEditStart;
                        delete row.forecastR2sEditStart;

                        if (rawNew === '' || rawNew === null || rawNew === undefined) {
                            cell.setValue(oldVal, true);
                            alert('Please enter a valid R2S quantity.');
                            return;
                        }
                        const newValue = Number(rawNew);
                        if (Number.isNaN(newValue) || newValue < 0) {
                            cell.setValue(oldVal, true);
                            alert('Please enter a valid R2S quantity.');
                            return;
                        }
                        const origNum = Number(oldVal);
                        if (!Number.isNaN(origNum) && origNum === newValue) return;

                        updateForecastField(
                            { sku: d.SKU, parent: d.Parent || '', column: 'R2S', value: newValue },
                            function() {
                                row.update({ readyToShipQty: newValue }, true);
                                const stageCell = row.getCells().find(function(c) { return c.getField() === 'stage'; });
                                if (stageCell) stageCell.reformat();
                                syncParentStageQtyColumns(d.Parent || d.parentKey);
                            },
                            function() {
                                cell.setValue(oldVal, true);
                            }
                        );
                    }
                },
                // {
                //     title: "MIP Value",
                //     field: "MIP_Value",
                //     accessor: row => (row ? row["MIP_Value"] : null),
                //     sorter: "number",
                //     headerSort: true,
                //     formatter: function(cell) {
                //         const value = cell.getValue();

                //         return value ?? '';
                //     }
                // },

                // {
                //     title: "R2S Value",
                //     field: "R2S_Value",
                //     accessor: row => (row ? row["R2S_Value"] : null),
                //     sorter: "number",
                //     headerSort: true,
                //     formatter: function(cell) {
                //         const value = cell.getValue();

                //         return value ?? '';
                //     }
                // },

                // {
                //     title: "Transit Value",
                //     field: "Transit_Value",
                //     accessor: row => (row ? row["Transit_Value"] : null),
                //     sorter: "number",
                //     headerSort: true,
                //     formatter: function(cell) {
                //         const value = cell.getValue();

                //         return value ?? '';
                //     }
                // },

                // {
                //     title: "Trnst",
                //     field: "transit",
                //     accessor: row => (row ? row["transit"] : null),
                //     sorter: "number",
                //     headerSort: true,
                //     formatter: function(cell) {
                //         const value = cell.getValue();
                //         const rowData = cell.getRow().getData();

                //         const sku = rowData.SKU ?? '';
                //         const parent = rowData.Parent ?? '';

                //         return `<div 
                //             class="editable-qty" 
                //             contenteditable="true" 
                //             data-field="Transit" 
                //             data-original="${value ?? ''}" 
                //             data-sku='${sku}' 
                //             data-parent='${parent}' 
                //             style="outline:none; min-width:40px; text-align:center; font-weight:bold;">
                //             ${value ?? ''}
                //         </div>`;
                //     }
                // },

                {
                    title: "Trn",
                    field: "transit",
                    accessor: row => (row ? row["transit"] : null),
                    sorter: "number",
                    headerSort: true,
                    hozAlign: "center",
                    formatter: function(cell) {
                        const row = cell.getRow();
                        const transit = row.getData().transit;
                        const tn = parseFloat(transit);
                        const transitDisp = (transit === null || transit === undefined || transit === '' || isNaN(tn) || tn === 0) ? '-' : transit;
                        let containerName = row.getData().containerName;
                        if (containerName) {
                            containerName = containerName
                                .split(",") 
                                .map(name => name.trim()) 
                                .filter(name => name.length > 0) 
                                .map(name => {
                                    const match = name.match(/(\d+)/);
                                    return match ? `C-${match[1]}` : name;
                                })
                                .join(", ");
                        }
                        const sub = containerName ? `<br><small class="text-info">${containerName}</small>` : '';
                        return `<div style="line-height:1.5; text-align:center;">
                            <span style="font-weight:600;">${transitDisp}</span>${sub}
                        </div>`;
                    }
                },

                {
                    title: "MOQ",
                    field: "MOQ",
                    accessor: row => (row ? row["MOQ"] : ''),
                    headerSort: true,
                    hozAlign: "center",
                    editable: function(cell) {
                        const d = cell.getRow().getData();
                        return !d.is_parent && !d.isParent;
                    },
                    editor: "number",
                    editorParams: {
                        min: 0,
                        verticalNavigation: "editor",
                    },
                    cellEditing: function(cell) {
                        cell.getRow().forecastMoqEditStart = cell.getValue();
                    },
                    cellEditCancelled: function(cell) {
                        delete cell.getRow().forecastMoqEditStart;
                    },
                    formatter: function(cell) {
                        const value = cell.getValue();
                        const rowData = cell.getRow().getData();
                        const esc = function(s) {
                            return String(s === null || s === undefined ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                        };
                        const disp = esc(value);

                        if (rowData.is_parent || rowData.isParent) {
                            return `<div 
                                style="outline:none; min-width:40px; text-align:center; font-weight:bold;color:#6c757d;"
                                title="Total MOQ for this parent (edit MOQ on each SKU row below)">
                                ${disp}
                            </div>`;
                        }

                        let moqColor = '#212529';
                        const moq = parseFloat(value);
                        const msl = parseFloat(rowData.msl);
                        if (Number.isFinite(moq) && Number.isFinite(msl) && msl > 0) {
                            if (moq < msl) {
                                moqColor = '#1b5e20';
                            } else if (moq > msl) {
                                moqColor = '#b71c1c';
                            }
                        }

                        return `<span class="forecast-moq-cell" style="display:block;outline:none;min-width:40px;text-align:center;font-weight:bold;color:${moqColor};cursor:text;"
                            title="${Number.isFinite(parseFloat(rowData.msl)) && parseFloat(rowData.msl) > 0 ? 'Green: MOQ &lt; MSL · Red: MOQ &gt; MSL · Double-click to edit' : 'Double-click to edit MOQ'}">${disp}</span>`;
                    },
                    cellEdited: function(cell) {
                        const row = cell.getRow();
                        const d = row.getData();
                        if (d.is_parent || d.isParent) return;

                        const rawNew = cell.getValue();
                        const oldVal = row.forecastMoqEditStart;
                        delete row.forecastMoqEditStart;
                        if (rawNew === '' || rawNew === null || rawNew === undefined) {
                            cell.setValue(oldVal);
                            alert('Please enter a valid number.');
                            return;
                        }
                        const newValue = Number(rawNew);
                        if (Number.isNaN(newValue)) {
                            cell.setValue(oldVal);
                            alert('Please enter a valid number.');
                            return;
                        }
                        const origNum = Number(oldVal);
                        if (!Number.isNaN(origNum) && origNum === newValue) return;

                        const sku = d.SKU;
                        const parent = d.Parent || '';
                        updateForecastField(
                            { sku: sku, parent: parent, column: 'MOQ', value: newValue },
                            function() {
                                const st = String(d.stage || '').trim().toLowerCase();
                                const moqNum = parseFloat(newValue) || 0;
                                const twoq = st === 'to_order_analysis' ? moqNum : 0;
                                const apprq = st === 'appr_req' ? moqNum : 0;
                                const rawNext = Object.assign({}, d.raw_data || {}, { MOQ: newValue });
                                row.update({ two_order_qty: twoq, appr_req_qty: apprq, raw_data: rawNext }, true);
                                syncParentStageQtyColumns(d.Parent || d.parentKey);
                                refreshParentMoqFromChildren(d.Parent || d.parentKey);
                                ['MOQ', 'two_order_qty', 'appr_req_qty', 'TAT', 'eff_roi_pct'].forEach(function(f) {
                                    const c = row.getCells().find(function(x) { return x.getField() === f; });
                                    if (c) c.reformat();
                                });
                                const today = new Date();
                                const currentDate = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0') + '-' + String(today.getDate()).padStart(2, '0');
                                updateForecastField({ sku: sku, parent: parent, column: 'Date of Appr', value: currentDate });
                            },
                            function() {
                                cell.setValue(oldVal);
                            }
                        );
                    },
                },
                {
                    title: "NRP",
                    field: "nr",
                    minWidth: 52,
                    hozAlign: "center",
                    accessor: row => {
                        const val = row?.["nr"];
                        if (val === null || val === undefined) return '';
                        const strVal = String(val);
                        const normalized = strVal.trim().toUpperCase();
                        return normalized;
                    },
                    headerSort: true,
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        let value = cell.getValue();
                        if (value === null || value === undefined || value === '') {
                            value = rowData["nr"];
                        }
                        if (value === null || value === undefined) {
                            value = '';
                        } else {
                            value = String(value).trim().toUpperCase();
                        }
                        const sku = rowData["SKU"] || '';
                        const parent = rowData["Parent"] || '';
                        if (!value || value === '') {
                            value = 'REQ';
                        }
                        if (value !== 'REQ' && value !== 'NR' && value !== 'LATER') {
                            value = 'REQ';
                        }
                        let dotColor = '#22c55e';
                        let tip = 'REQ';
                        if (value === 'NR') {
                            dotColor = '#dc3545';
                            tip = '2BDC';
                        } else if (value === 'LATER') {
                            dotColor = '#facc15';
                            tip = 'LATER';
                        }
                        return `
                            <div class="nrp-dot-cell position-relative d-flex justify-content-center align-items-center w-100" title="${tip} (click to change)">
                                <span class="nrp-status-dot" style="background-color:${dotColor};" aria-hidden="true"></span>
                                <select class="form-select form-select-sm editable-select nrp-nr-select position-absolute top-0 start-0 w-100 h-100"
                                    data-type="NR"
                                    data-sku='${sku}'
                                    data-parent='${parent}'
                                    aria-label="NRP: ${tip}">
                                    <option value="REQ" ${value === 'REQ' ? 'selected' : ''}>REQ</option>
                                    <option value="NR" ${value === 'NR' ? 'selected' : ''}>2BDC</option>
                                    <option value="LATER" ${value === 'LATER' ? 'selected' : ''}>LATER</option>
                                </select>
                            </div>
                        `;
                    }
                },
                {
                    title: "Supplier",
                    field: "mfrg_supplier",
                    accessor: function(value) { return value ?? ''; },
                    minWidth: 68,
                    width: 76,
                    maxWidth: 92,
                    widthGrow: 0,
                    hozAlign: "center",
                    vertAlign: "middle",
                    cssClass: "forecast-current-supplier-cell",
                    headerSort: true,
                    sorter: function(a, b, aRow, bRow, column, dir) {
                        const aVal = (a || '').trim();
                        const bVal = (b || '').trim();
                        const aEmpty = aVal === '';
                        const bEmpty = bVal === '';
                        if (aEmpty && bEmpty) return 0;
                        if (aEmpty) return 1;
                        if (bEmpty) return -1;
                        return aVal.localeCompare(bVal);
                    },
                    titleFormatter: function() {
                        const span = document.createElement('span');
                        span.textContent = 'Supplier';
                        span.setAttribute('title', 'Supplier');
                        span.style.fontWeight = '700';
                        return span;
                    },
                    formatter: function(cell) {
                        const value = cell.getValue() || '';
                        const esc = String(value).replace(/</g, '&lt;').replace(/>/g, '&gt;');
                        const display = esc || '-';
                        return '<span class="forecast-supplier-name" title="' + esc.replace(/"/g, '&quot;') + '">' + display + '</span>';
                    }
                },
                {
                    title: "Rating",
                    field: "rating",
                    minWidth: 80,
                    width: 90,
                    headerSort: true,
                    hozAlign: "center",
                    vertAlign: "middle",
                    titleFormatter: function() {
                        const span = document.createElement("span");
                        span.textContent = "Rating";
                        span.setAttribute("title", "Rating & reviews (Jungle Scout)");
                        return span;
                    },
                    accessor: function(row) {
                        if (!row) return null;
                        const r = row.rating;
                        if (r === null || r === undefined || r === '') {
                            return null;
                        }
                        const n = parseFloat(r);

                        return Number.isFinite(n) ? n : null;
                    },
                    sorter: "number",
                    cssClass: "forecast-rating-combo-cell",
                    formatter: function(cell) {
                        const d = cell.getRow().getData();
                        const rawR = d.rating;
                        const rawRev = d.reviews;
                        const rVal = parseFloat(rawR);
                        const hasRating = rawR !== null && rawR !== undefined && String(rawR).trim() !== '' && Number.isFinite(rVal);
                        const revParsed = parseInt(String(rawRev == null ? '' : rawRev).replace(/,/g, ''), 10);
                        const hasReviews = Number.isFinite(revParsed) && revParsed >= 0 && String(rawRev).trim() !== '';

                        if (!hasRating && !hasReviews) {
                            return '<div style="display:flex;align-items:center;justify-content:center;"><span style="color:#6c757d;font-size:0.75rem;">—</span></div>';
                        }

                        /* <3.5 red; 3.5–<4 yellow; 4–<4.5 green; ≥4.5 pink cell bg */
                        let starColor = '#b91c1c';
                        let ratingWrapStyle = '';
                        if (hasRating) {
                            if (rVal >= 4.5) {
                                starColor = '#9d174d';
                                ratingWrapStyle = 'background:#fce7f3;border-radius:2px;padding:0 1px;box-sizing:border-box;';
                            } else if (rVal >= 4) {
                                starColor = '#15803d';
                            } else if (rVal >= 3.5) {
                                starColor = '#a16207';
                            } else {
                                starColor = '#dc2626';
                            }
                        }

                        const ratingLine = hasRating
                            ? (Number.isInteger(rVal) ? String(rVal) : rVal.toFixed(1))
                            : null;
                        const revLine = hasReviews
                            ? ("(" + revParsed.toLocaleString("en-US") + ")")
                            : null;

                        let html = "<div style=\"display:inline-flex;align-items:center;justify-content:center;gap:3px;flex-wrap:nowrap;padding:0 2px;" + ratingWrapStyle + "\">";
                        if (hasRating) {
                            html += "<span style=\"font-weight:700;color:" + starColor + ";display:inline-flex;align-items:center;gap:1px;font-size:0.72rem;\">";
                            html += "<i class=\"bi bi-star-fill\" style=\"font-size:0.68rem;line-height:1;\"></i>";
                            html += "<span>" + ratingLine + "</span></span>";
                        } else {
                            html += "<span style=\"font-weight:700;color:#9e9e9e;display:inline-flex;align-items:center;gap:1px;font-size:0.68rem;\">";
                            html += "<i class=\"bi bi-star\" style=\"font-size:0.65rem;\"></i><span>—</span></span>";
                        }
                        const revMuted = hasRating && rVal >= 4.5 ? '#861657' : '#5c5c5c';
                        const revZero = hasRating && rVal >= 4.5 ? '#9d174d' : '#9e9e9e';
                        if (revLine) {
                            html += "<span style=\"font-size:0.62rem;color:" + revMuted + ";font-weight:500;white-space:nowrap;\">" + revLine + "</span>";
                        } else if (hasRating) {
                            html += "<span style=\"font-size:0.6rem;color:" + revZero + ";\">(0)</span>";
                        }
                        html += "</div>";

                        return html;
                    }
                },
                {
                    title: "TAT",
                    field: "TAT",
                    headerSort: true,
                    titleFormatter: function() {
                        const span = document.createElement("span");
                        span.setAttribute("title", "Turn Around Time (Months)");
                        span.setAttribute("aria-label", "Turn Around Time (Months)");
                        span.style.cursor = "help";
                        span.textContent = "TAT";
                        return span;
                    },
                    hozAlign: "center",
                    accessor: function(row) {
                        if (!row || row.is_parent || row.isParent) {
                            return null;
                        }
                        const mAvg = parseFloat(row.m_avg) || 0;
                        const moq = parseFloat(row.MOQ) || 0;
                        if (mAvg <= 0) {
                            return null;
                        }

                        return Math.round(moq / mAvg);
                    },
                    sorter: function(a, b, aRow, bRow) {
                        const tatVal = function(row) {
                            const d = row.getData();
                            if (d.is_parent || d.isParent) {
                                return null;
                            }
                            const mAvg = parseFloat(d.m_avg) || 0;
                            const moq = parseFloat(d.MOQ) || 0;
                            if (mAvg <= 0) {
                                return null;
                            }

                            return Math.round(moq / mAvg);
                        };
                        const va = tatVal(aRow);
                        const vb = tatVal(bRow);
                        if (va == null && vb == null) {
                            return 0;
                        }
                        if (va == null) {
                            return 1;
                        }
                        if (vb == null) {
                            return -1;
                        }

                        return va - vb;
                    },
                    formatter: function(cell) {
                        const d = cell.getRow().getData();
                        if (d.is_parent || d.isParent) {
                            return '<span style="display:block;text-align:center;color:#6c757d">—</span>';
                        }
                        const mAvg = parseFloat(d.m_avg) || 0;
                        const moq = parseFloat(d.MOQ) || 0;
                        if (mAvg <= 0) {
                            return '<span style="display:block;text-align:center;color:#6c757d">—</span>';
                        }
                        const tat = moq / mAvg;
                        const rounded = Math.round(tat);
                        let color = '#1b5e20';
                        if (rounded > 6) {
                            color = '#b71c1c';
                        } else if (rounded >= 4) {
                            color = '#9a7b00';
                        }
                        return `<span style="display:block;text-align:center;font-weight:700;color:${color};" title="MOQ ÷ M AVG (rounded) — green &lt;4, yellow 4–6, red &gt;6">${rounded}</span>`;
                    }
                },
                {
                    title: "DOA",
                    field: "date_apprvl",
                    width: 72,
                    minWidth: 68,
                    headerSort: true,
                    sorter: function(a, b, aRow, bRow, col, dir) {
                        const toMs = v => { const d = v ? new Date(v) : null; return d && !isNaN(d) ? d.getTime() : (dir === 'asc' ? Infinity : -Infinity); };
                        return toMs(a) - toMs(b);
                    },
                    formatter: function(cell) {
                        const value = cell.getValue() || "";
                        let displayText = "-";
                        let textStyle = "";

                        if (value) {
                            const d = new Date(value);
                            if (!isNaN(d.getTime())) {
                                const day = String(d.getDate()).padStart(2, "0");
                                const monthNames = ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"];
                                const monthName = monthNames[d.getMonth()];
                                displayText = `${day} ${monthName}`;

                                const today = new Date();
                                today.setHours(0, 0, 0, 0);
                                d.setHours(0, 0, 0, 0);
                                const diffTime = today - d;
                                const daysDiff = Math.floor(diffTime / (1000 * 60 * 60 * 24));

                                if (daysDiff >= 14) {
                                    textStyle = "color:red; font-weight:700;";
                                } else if (daysDiff >= 7) {
                                    textStyle = "color:#FFC106; font-weight:700;";
                                }
                            }
                        }

                        return `<span style="min-width:55px; display:inline-block; ${textStyle}">${displayText}</span>`;
                    }
                },
                {
                    title: "RFQ",
                    field: "rfq_form_link",
                    hozAlign: "center",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData() || {};
                        const formLink = String(rowData.rfq_form_link || '').trim();
                        const reportLink = String(rowData.rfq_report || '').trim();
                        if (!formLink && !reportLink) {
                            return '<span class="text-muted">-</span>';
                        }
                        const formDot = formLink
                            ? `<a href="${formLink}" target="_blank" title="RFQ Form" style="display:inline-block;width:12px;height:12px;border-radius:50%;background:#fbc02d;border:1px solid #f9a825;"></a>`
                            : '';
                        const reportDot = reportLink
                            ? `<a href="${reportLink}" target="_blank" title="RFQ Report" style="display:inline-block;width:12px;height:12px;border-radius:50%;background:#2e7d32;border:1px solid #1b5e20;"></a>`
                            : '';
                        return `<div style="display:flex;justify-content:center;align-items:center;gap:8px;">${formDot}${reportDot}</div>`;
                    }
                },
                {
                    title: "GPFT%",
                    field: "avg_gpft_pct",
                    minWidth: 58,
                    width: 62,
                    headerSort: true,
                    hozAlign: "center",
                    sorter: "number",
                    titleFormatter: function() {
                        const span = document.createElement("span");
                        span.textContent = "GPFT%";
                        span.setAttribute("title", "Gross Profit % (amazon_data_view)");
                        return span;
                    },
                    accessor: function(row) {
                        if (!row) return null;
                        const v = parseFloat(row.avg_gpft_pct);
                        return Number.isFinite(v) ? v : null;
                    },
                    formatter: function(cell) {
                        const v = cell.getValue();
                        if (v === null || v === undefined || v === '' || (typeof v === 'number' && isNaN(v))) {
                            return '<span style="display:block;text-align:center;color:#6c757d">—</span>';
                        }
                        const n = Math.round(parseFloat(v));
                        let col = '#1b5e20';
                        if (n < 18) col = '#b71c1c';
                        else if (n > 33) col = '#c2185b';
                        return `<span style="display:block;text-align:center;font-weight:700;color:${col};" title="Gross Profit % — red &lt;18, green 18–33, magenta &gt;33">${n}%</span>`;
                    }
                },
                {
                    title: "Ads%",
                    field: "avg_ad_pct",
                    minWidth: 52,
                    width: 58,
                    headerSort: true,
                    hozAlign: "center",
                    sorter: "number",
                    titleFormatter: function() {
                        const span = document.createElement("span");
                        span.textContent = "Ads%";
                        span.setAttribute("title", "Ads % — live channel-level Ads% from all-marketplace-master (Amazon)");
                        return span;
                    },
                    accessor: function(row) {
                        if (!row) return null;
                        const v = parseFloat(row.avg_ad_pct);
                        return Number.isFinite(v) ? v : null;
                    },
                    formatter: function(cell) {
                        const v = cell.getValue();
                        if (v === null || v === undefined || v === '' || (typeof v === 'number' && isNaN(v))) {
                            return '<span style="display:block;text-align:center;color:#6c757d">—</span>';
                        }
                        const n = parseFloat(v);
                        let col = '#1b5e20';
                        if (n > 10) col = '#b71c1c';
                        else if (n >= 5) col = '#e65100';
                        const dot = `<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:${col};vertical-align:middle;margin-left:3px;"></span>`;
                        return `<span style="display:block;text-align:center;font-weight:600;color:${col};" title="Ads % — green &lt;5, orange 5–10, red &gt;10">${n.toFixed(1)}%${dot}</span>`;
                    }
                },
                {
                    title: "NPFT%",
                    field: "avg_gpft_pct",
                    minWidth: 58,
                    width: 62,
                    headerSort: false,
                    hozAlign: "center",
                    titleFormatter: function() {
                        const span = document.createElement("span");
                        span.textContent = "NPFT%";
                        span.setAttribute("title", "Net Profit % = GPFT% − Ads%");
                        return span;
                    },
                    formatter: function(cell) {
                        const row = cell.getRow().getData();
                        const gpft = parseFloat(row.avg_gpft_pct);
                        const ads  = parseFloat(row.avg_ad_pct);
                        if (!Number.isFinite(gpft) || !Number.isFinite(ads)) {
                            return '<span style="display:block;text-align:center;color:#6c757d">—</span>';
                        }
                        const n = Math.round(gpft - ads);
                        let col = '#1b5e20';
                        if (n < 18) col = '#b71c1c';
                        else if (n > 33) col = '#c2185b';
                        return `<span style="display:block;text-align:center;font-weight:700;color:${col};" title="GPFT% − Ads% — red &lt;18, green 18–33, magenta &gt;33">${n}%</span>`;
                    }
                },
                {
                    title: "GROI%",
                    field: "avg_groi_pct",
                    minWidth: 58,
                    width: 62,
                    headerSort: true,
                    hozAlign: "center",
                    sorter: "number",
                    titleFormatter: function() {
                        const span = document.createElement("span");
                        span.textContent = "GROI%";
                        span.setAttribute("title", "Gross ROI % (amazon_data_view)");
                        return span;
                    },
                    accessor: function(row) {
                        if (!row) return null;
                        const v = parseFloat(row.avg_groi_pct);
                        return Number.isFinite(v) ? v : null;
                    },
                    formatter: function(cell) {
                        const v = cell.getValue();
                        if (v === null || v === undefined || v === '' || (typeof v === 'number' && isNaN(v))) {
                            return '<span style="display:block;text-align:center;color:#6c757d">—</span>';
                        }
                        const n = Math.round(parseFloat(v));
                        let col = '#1b5e20';
                        if (n < 50) col = '#b71c1c';
                        else if (n > 100) col = '#c2185b';
                        return `<span style="display:block;text-align:center;font-weight:700;color:${col};" title="Gross ROI % — red &lt;50, green 50–100, magenta &gt;100">${n}%</span>`;
                    }
                },
                {
                    title: "NROI%",
                    field: "avg_groi_pct",
                    minWidth: 58,
                    width: 62,
                    headerSort: false,
                    hozAlign: "center",
                    titleFormatter: function() {
                        const span = document.createElement("span");
                        span.textContent = "NROI%";
                        span.setAttribute("title", "Net ROI % = GROI% − Ads%");
                        return span;
                    },
                    formatter: function(cell) {
                        const row = cell.getRow().getData();
                        const groi = parseFloat(row.avg_groi_pct);
                        const ads  = parseFloat(row.avg_ad_pct);
                        if (!Number.isFinite(groi) || !Number.isFinite(ads)) {
                            return '<span style="display:block;text-align:center;color:#6c757d">—</span>';
                        }
                        const n = Math.round(groi - ads);
                        let col = '#1b5e20';
                        if (n < 50) col = '#b71c1c';
                        else if (n > 100) col = '#c2185b';
                        return `<span style="display:block;text-align:center;font-weight:700;color:${col};" title="GROI% − Ads% — red &lt;50, green 50–100, magenta &gt;100">${n}%</span>`;
                    }
                },
                {
                    title: "EFF ROI %",
                    field: "eff_roi_pct",
                    minWidth: 58,
                    width: 62,
                    headerSort: true,
                    hozAlign: "center",
                    titleFormatter: function() {
                        const span = document.createElement("span");
                        span.textContent = "EFF ROI %";
                        span.setAttribute("title", "NROI% ÷ TAT × 12");
                        return span;
                    },
                    accessor: function(row) {
                        if (!row) {
                            return null;
                        }
                        if (row.is_parent || row.isParent) {
                            const pe = row.eff_roi_pct;
                            return pe != null && Number.isFinite(Number(pe)) ? Math.round(Number(pe)) : null;
                        }
                        const mAvg = parseFloat(row.m_avg) || 0;
                        const moq = parseFloat(row.MOQ) || 0;
                        const tat = mAvg > 0 ? Math.round(moq / mAvg) : 0;
                        const nroi = parseFloat(row.avg_groi_pct);
                        if (!tat || tat <= 0 || !Number.isFinite(nroi)) {
                            return null;
                        }

                        return Math.round((nroi / tat) * 12);
                    },
                    sorter: "number",
                    formatter: function(cell) {
                        function effRoiTextColor(effRounded) {
                            const e = Math.round(Number(effRounded));
                            if (e < 100) {
                                return '#b71c1c';
                            }
                            if (e <= 200) {
                                return '#1b5e20';
                            }
                            return '#c2185b';
                        }
                        const d = cell.getRow().getData();
                        if (d.is_parent || d.isParent) {
                            const pe = d.eff_roi_pct;
                            if (pe != null && pe !== '' && Number.isFinite(Number(pe))) {
                                const eff = Math.round(Number(pe));
                                const col = effRoiTextColor(eff);

                                return `<span style="display:block;text-align:center;font-weight:700;color:${col};" title="Avg of child EFF ROI % (NROI% ÷ TAT × 12)">${eff}%</span>`;
                            }
                            return '<span style="display:block;text-align:center;color:#6c757d">—</span>';
                        }
                        const mAvg = parseFloat(d.m_avg) || 0;
                        const moq = parseFloat(d.MOQ) || 0;
                        const tat = mAvg > 0 ? Math.round(moq / mAvg) : 0;
                        const nroi = parseFloat(d.avg_groi_pct);
                        if (!tat || tat <= 0 || !Number.isFinite(nroi)) {
                            return '<span style="display:block;text-align:center;color:#6c757d">—</span>';
                        }
                        const eff = Math.round((nroi / tat) * 12);
                        const col = effRoiTextColor(eff);

                        return `<span style="display:block;text-align:center;font-weight:700;color:${col};" title="NROI% ÷ TAT × 12 — red &lt;100, green 100–200, magenta &gt;200">${eff}%</span>`;
                    }
                },
                 {
                    title: "CP",
                    field: "CP",
                    accessor: row => (row ? row["CP"] : 0),
                    visible: false,
                    formatter: function(cell) {
                        const value = cell.getValue() || 0;
                        return `<span style="display:block; text-align:center; font-weight:bold;">$${value.toLocaleString()}</span>`;
                    }
                },
                {
                    title: "LP",
                    field: "LP",
                    accessor: row => (row ? row["LP"] : 0),
                    visible: false,
                    formatter: function(cell) {
                        const value = cell.getValue() || 0;
                        return `<span style="display:block; text-align:center; font-weight:bold;">$${value.toLocaleString()}</span>`;
                    }
                },
                {
                    title: "CBM",
                    field: "cbm",
                    hozAlign: "center",
                    formatter: function(cell) {
                        const d = cell.getRow().getData() || {};
                        if (d.is_parent || d.isParent) return '<span style="display:block;text-align:center;color:#6c757d;">-</span>';
                        const v = parseFloat(cell.getValue());
                        if (!Number.isFinite(v) || v <= 0) return '<span style="display:block;text-align:center;color:#6c757d;">N/A</span>';
                        return `<span style="display:block;text-align:center;font-weight:700;">${v.toFixed(4)}</span>`;
                    }
                },
                {
                    title: "Total CBM",
                    field: "total_cbm",
                    hozAlign: "center",
                    formatter: function(cell) {
                        const d = cell.getRow().getData() || {};
                        if (d.is_parent || d.isParent) return '<span style="display:block;text-align:center;color:#6c757d;">-</span>';
                        const total = parseFloat(cell.getValue());
                        if (!Number.isFinite(total) || total <= 0) {
                            return '<span style="display:block;text-align:center;color:#6c757d;">-</span>';
                        }
                        return `<span style="display:block;text-align:center;font-weight:700;">${total.toFixed(2)}</span>`;
                    }
                },
                {
                    title: "Zone",
                    field: "r2s_zone",
                    hozAlign: "center",
                    headerSort: true,
                    editor: "input",
                    editable: function(cell) {
                        const d = cell.getRow().getData();
                        return !(d.is_parent || d.isParent);
                    },
                    cellClick: function(e, cell) {
                        const d = cell.getRow().getData();
                        if (d.is_parent || d.isParent) return;
                        cell.edit();
                    },
                    cellEditing: function(cell) {
                        cell._zonePrev = cell.getValue();
                        setTimeout(function() {
                            const input = cell.getElement().querySelector('input');
                            if (input) { input.focus(); input.select(); }
                        }, 0);
                    },
                    cellEdited: function(cell) {
                        const row = cell.getRow();
                        const d = row.getData();
                        if (d.is_parent || d.isParent) return;
                        const next = String(cell.getValue() || '').trim();
                        const prev = cell._zonePrev || '';
                        delete cell._zonePrev;
                        if (next === prev) return;
                        const sku = String(d.SKU || '').trim();
                        if (!sku) return;
                        const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
                        fetch('/ready-to-ship/inline-update-by-sku', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': token,
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ sku: sku, column: 'area', value: next })
                        })
                        .then(r => r.json())
                        .then(res => {
                            if (!res || !res.success) {
                                cell.setValue(prev, true);
                                alert(res?.message || 'Failed to update Zone');
                            } else {
                                row.update({ r2s_zone: next }, true);
                            }
                        })
                        .catch(() => {
                            cell.setValue(prev, true);
                            alert('Failed to update Zone');
                        });
                    },
                    formatter: function(cell) {
                        const d = cell.getRow().getData() || {};
                        if (d.is_parent || d.isParent) return '<span style="display:block;text-align:center;color:#6c757d;">-</span>';
                        const v = String(cell.getValue() || '').trim();
                        if (!v) return '<span style="display:block;text-align:center;color:#6c757d;cursor:text;">-</span>';
                        return `<span style="display:block;text-align:center;font-weight:600;cursor:text;" title="${v}">${v}</span>`;
                    }
                },
                {
                    title: "Order Date",
                    field: "mfrg_order_date",
                    hozAlign: "center",
                    headerSort: true,
                    sorter: function(a, b, aRow, bRow, col, dir) {
                        const toMs = v => { const d = v ? new Date(v) : null; return d && !isNaN(d) ? d.getTime() : (dir === 'asc' ? Infinity : -Infinity); };
                        return toMs(a) - toMs(b);
                    },
                    formatter: function(cell) {
                        const drow = cell.getRow().getData() || {};
                        if (drow.is_parent || drow.isParent) {
                            return '<span style="display:block;text-align:center;color:#6c757d;">-</span>';
                        }
                        const raw = String(cell.getValue() || '').trim();
                        if (!raw) {
                            return '<span style="display:block;text-align:center;color:#6c757d;">-</span>';
                        }
                        const dateObj = new Date(raw);
                        if (isNaN(dateObj.getTime())) {
                            return '<span style="display:block;text-align:center;color:#6c757d;">-</span>';
                        }
                        const today = new Date();
                        today.setHours(0, 0, 0, 0);
                        dateObj.setHours(0, 0, 0, 0);
                        const daysDiff = Math.floor((today - dateObj) / (1000 * 60 * 60 * 24));
                        const day = String(dateObj.getDate()).padStart(2, '0');
                        const month = dateObj.toLocaleString('en-US', { month: 'short' }).toUpperCase();
                        const year = dateObj.getFullYear();
                        let color = '#000';
                        if (daysDiff > 25) color = 'red';
                        else if (daysDiff >= 15) color = '#ffc107';
                        return `<span style="display:block;text-align:center;font-weight:700;color:${color};">${day} ${month} ${year}</span>`;
                    }
                },
                {
                    title: "Amount",
                    field: "r2s_amount",
                    hozAlign: "center",
                    formatter: function(cell) {
                        const d = cell.getRow().getData() || {};
                        if (d.is_parent || d.isParent) return '<span style="display:block;text-align:center;color:#6c757d;">-</span>';
                        const v = parseFloat(cell.getValue());
                        if (!Number.isFinite(v)) return '<span style="display:block;text-align:center;color:#6c757d;">-</span>';
                        return `<span style="display:block;text-align:center;font-weight:700;">${Math.round(v)}</span>`;
                    }
                },
                {
                    title: "Pmt Terms",
                    field: "r2s_pay_term",
                    hozAlign: "center",
                    headerSort: true,
                    editor: "list",
                    editorParams: {
                        values: ["BL", "AL", "BRBL"],
                        defaultValue: "BL",
                        verticalNavigation: "editor",
                    },
                    editable: function(cell) {
                        const d = cell.getRow().getData();
                        return !(d.is_parent || d.isParent);
                    },
                    cellClick: function(e, cell) {
                        const d = cell.getRow().getData();
                        if (d.is_parent || d.isParent) return;
                        cell.edit();
                    },
                    cellEditing: function(cell) {
                        cell._pmtTermsPrev = cell.getValue();
                    },
                    cellEdited: function(cell) {
                        const row = cell.getRow();
                        const d = row.getData();
                        if (d.is_parent || d.isParent) return;
                        const next = String(cell.getValue() || '').trim().toUpperCase();
                        const prev = cell._pmtTermsPrev || '';
                        delete cell._pmtTermsPrev;
                        if (next === prev) return;
                        const sku = String(d.SKU || '').trim();
                        if (!sku) return;
                        const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
                        fetch('/ready-to-ship/inline-update-by-sku', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': token,
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ sku: sku, column: 'pay_term', value: next })
                        })
                        .then(r => r.json())
                        .then(res => {
                            if (!res || !res.success) {
                                cell.setValue(prev, true);
                                alert(res?.message || 'Failed to update Pmt Terms');
                            } else {
                                row.update({ r2s_pay_term: next }, true);
                            }
                        })
                        .catch(() => {
                            cell.setValue(prev, true);
                            alert('Failed to update Pmt Terms');
                        });
                    },
                    formatter: function(cell) {
                        const d = cell.getRow().getData() || {};
                        if (d.is_parent || d.isParent) return '<span style="display:block;text-align:center;color:#6c757d;">-</span>';
                        const value = String(cell.getValue() || 'BL').trim().toUpperCase();
                        const colorMap = { BRBL: '#28a745', AL: '#ffc107', BL: '#dc3545' };
                        const color = colorMap[value] || '#6c757d';
                        return `<span style="display:inline-flex;align-items:center;justify-content:center;gap:4px;cursor:pointer;" title="Click to edit">
                            <span style="display:inline-block;width:10px;height:10px;border-radius:50%;flex-shrink:0;background:${color};"></span>
                            <span style="font-size:0.75rem;font-weight:700;">${value}</span>
                        </span>`;
                    }
                },
                {
                    title: "New Photo",
                    field: "r2s_new_photo",
                    visible: false,
                    hozAlign: "center",
                    headerSort: true,
                    formatter: function(cell) {
                        const d = cell.getRow().getData() || {};
                        if (d.is_parent || d.isParent) return '<span style="display:block;text-align:center;color:#6c757d;">-</span>';
                        const yes = String(cell.getValue() || 'No').trim().toUpperCase() === 'YES';
                        return `<span style="display:inline-block;width:14px;height:14px;border-radius:50%;background-color:${yes ? '#28a745' : '#dc3545'};"></span>`;
                    }
                },
            ],
            ajaxResponse: function(url, params, response) {
                groupedSkuData = {}; // clear previous

                // SKU count for column header (exclude rows where SKU contains "parent")
                const skuCount = (response.data || []).filter(row => !String(row.SKU || '').toLowerCase().includes('parent')).length;

                // Update total MSL_C from server response (connected to MSL data)
                const totalMslCElement = document.getElementById('total_msl_c_value');
                if (totalMslCElement && response.total_msl_c !== undefined) {
                    const wholeNumber = Math.round(parseFloat(response.total_msl_c));
                    totalMslCElement.textContent = wholeNumber.toLocaleString('en-US');
                }
                const totalMslSpAmzEl = document.getElementById('total_msl_sp_amz_value');
                if (totalMslSpAmzEl && response.total_msl_sp_amz !== undefined) {
                    totalMslSpAmzEl.textContent = Math.round(parseFloat(response.total_msl_sp_amz)).toLocaleString('en-US');
                }

                // Calculate and update total INV Value
                const totalInvValue = response.data.reduce((sum, item) => {
                    if (!item.is_parent) {
                        return sum + (parseFloat(item.inv_value) || 0);
                    }
                    return sum;
                }, 0);
                const totalInvValueElement = document.getElementById('total_inv_value_display');
                if (totalInvValueElement) {
                    const roundedTotal = Math.round(totalInvValue);
                    totalInvValueElement.textContent = roundedTotal.toLocaleString('en-US');
                }

                // Calculate and update total LP Value
                const totalLpValue = response.data.reduce((sum, item) => {
                    if (!item.is_parent) {
                        return sum + (parseFloat(item.lp_value) || 0);
                    }
                    return sum;
                }, 0);
                const totalLpValueElement = document.getElementById('total_lp_value_display');
                if (totalLpValueElement) {
                    const roundedTotal = Math.round(totalLpValue);
                    totalLpValueElement.textContent = roundedTotal.toLocaleString('en-US');
                }

                // Calculate and update total Restock MSL
                const totalRestockMsl = response.data.reduce((sum, item) => {
                    if (!item.is_parent && (parseFloat(item.INV) || 0) === 0) {
                        const lp = parseFloat(item.LP) || 0;
                        return sum + (lp / 4);
                    }
                        // lp* msl
                    return sum;
                }, 0);
                const totalRestockMslElement = document.getElementById('total_restock_msl_value');
                if (totalRestockMslElement) {
                    const wholeNumber = Math.round(totalRestockMsl);
                    totalRestockMslElement.textContent = wholeNumber.toLocaleString('en-US');
                }

                // Calculate restock count and average shopify price for restock SKUs
                const restockItems = response.data.filter(item => !item.is_parent && (parseFloat(item.INV) || 0) === 0);
                const restockCount = restockItems.length;
                const totalShopifyPrice = restockItems.reduce((sum, item) => sum + (parseFloat(item.shopifyb2c_price) || 0), 0);
                const averageShopifyPrice = restockCount > 0 ? totalShopifyPrice / restockCount : 0;
                const totalMinimalMsl = restockItems.reduce((sum, item) => sum + (parseFloat(item.MSL_SP) || 0), 0);
                const totalMinimalMslElement = document.getElementById('total_minimal_msl_value');
                if (totalMinimalMslElement) {
                    const wholeNumber = Math.round(totalMinimalMsl);
                    totalMinimalMslElement.textContent = wholeNumber.toLocaleString('en-US');
                }

                // Calculate sum of restock shopify prices
                const sumRestockShopifyPrice = restockItems.reduce((sum, item) => sum + (parseFloat(item.shopifyb2c_price) || 0), 0);
                const sumRestockShopifyPriceElement = document.getElementById('sum_restock_shopify_price_value');
                if (sumRestockShopifyPriceElement) {
                    const wholeNumber = Math.round(sumRestockShopifyPrice);
                    sumRestockShopifyPriceElement.textContent = wholeNumber.toLocaleString('en-US');
                }

                // Calculate and update total MIP Value - only for items with stage === 'mip' (like mfrg-in-progress page)
                // Filter criteria: stage === 'mip', ready_to_ship !== 'Yes', nr !== 'NR' (same as mfrg-in-progress page)
                // Calculate directly as qty * rate (like mfrg-in-progress page calculates from DOM)
                const totalMipValue = response.data.reduce((sum, item) => {
                    if (!item.is_parent) {
                        // Check stage field - only count if stage is 'mip'
                        const stage = item.stage || '';
                        const stageValue = String(stage || '').trim().toLowerCase();
                        
                        // Check ready_to_ship from mfrg_progress table (exclude if 'Yes', like mfrg-in-progress page)
                        const mfrgReadyToShip = item.mfrg_ready_to_ship || 'No';
                        const readyToShipValue = String(mfrgReadyToShip || '').trim();
                        
                        // Check nr field (exclude if 'NR', like mfrg-in-progress page)
                        const nr = item.nr || '';
                        const nrValue = String(nr || '').trim().toUpperCase();
                        
                        // Only count if stage is 'mip', ready_to_ship !== 'Yes', and nr !== 'NR' (matching mfrg-in-progress page logic)
                        if (stageValue === 'mip' && readyToShipValue !== 'Yes' && nrValue !== 'NR') {
                            // Calculate directly as qty * rate (same as mfrg-in-progress page)
                            // In mfrg-in-progress: $item->qty * $item->rate (directly from mfrg_progress table)
                            // In forecastAnalysis: order_given (qty) and mip_rate (rate) from mfrg_progress table
                            // But ONLY if ready_to_ship === 'No' (controller already sets order_given=0 if ready_to_ship='Yes')
                            
                            // Only calculate if both qty and rate are available (matching mfrg-in-progress template logic)
                            // mfrg-in-progress template: is_numeric($item->qty) && is_numeric($item->rate)
                            const qty = parseFloat(item.order_given || 0) || 0;
                            const rate = parseFloat(item.mip_rate || 0) || 0;
                            
                            // Only calculate if both qty and rate are available (matching mfrg-in-progress template logic)
                            if (qty > 0 && rate > 0) {
                                return sum + (qty * rate);
                            }
                            // Note: We don't use fallback to MIP_Value here because mfrg-in-progress page doesn't show items without qty*rate
                        }
                    }
                    return sum;
                }, 0);
                const totalMipValueElement = document.getElementById('total_mip_value_display');
                if (totalMipValueElement) {
                    const roundedTotal = Math.round(totalMipValue);
                    totalMipValueElement.textContent = roundedTotal.toLocaleString('en-US');
                }

                // Calculate and update total R2S Value - only for items with stage === 'r2s' (like ready-to-ship page)
                // Filter criteria: stage === 'r2s', transit_inv_status === 0 (already filtered in controller), nr !== 'NR' (same as ready-to-ship page)
                // Calculate directly as qty * rate (like ready-to-ship page calculates from DOM)
                const totalR2sValue = response.data.reduce((sum, item) => {
                    if (!item.is_parent) {
                        // Check stage field - only count if stage is 'r2s'
                        const stage = item.stage || '';
                        const stageValue = String(stage || '').trim().toLowerCase();
                        
                        // Check nr field (exclude if 'NR', like ready-to-ship page)
                        const nr = item.nr || '';
                        const nrValue = String(nr || '').trim().toUpperCase();
                        
                        // Only count if stage is 'r2s' and nr !== 'NR' (matching ready-to-ship page logic)
                        // IMPORTANT: ready-to-ship page calculates from items that are already filtered in template (using continue directive in Blade)
                        // Controller already filters: transit_inv_status = 0 and stage === 'r2s'
                        if (stageValue === 'r2s' && nrValue !== 'NR') {
                            // Calculate directly as qty * rate (same as ready-to-ship page)
                            // In ready-to-ship: $item->qty * $item->rate (directly from ready_to_ship table)
                            // In forecastAnalysis: readyToShipQty (qty) and r2s_rate (rate) from ready_to_ship table
                            
                            // Only calculate if both qty and rate are available (matching ready-to-ship template logic)
                            // ready-to-ship template: is_numeric($item->qty) && is_numeric($item->rate)
                            const qty = parseFloat(item.readyToShipQty || 0) || 0;
                            const rate = parseFloat(item.r2s_rate || 0) || 0;
                            
                            // Only calculate if both qty and rate are available (matching ready-to-ship template logic)
                            if (qty > 0 && rate > 0) {
                                return sum + (qty * rate);
                            }
                            // Note: We don't use fallback to R2S_Value here because ready-to-ship page doesn't show items without qty*rate
                        }
                    }
                    return sum;
                }, 0);
                const totalR2sValueElement = document.getElementById('total_r2s_value_display');
                if (totalR2sValueElement) {
                    const roundedTotal = Math.round(totalR2sValue);
                    totalR2sValueElement.textContent = roundedTotal.toLocaleString('en-US');
                }

                // Trn Val: controller sends sum of (transit QTY × CP) per child SKU
                let totalTransitValue = 0;
                if (response.total_transit_value !== undefined) {
                    totalTransitValue = parseFloat(response.total_transit_value || 0) || 0;
                } else {
                    totalTransitValue = (response.data || []).reduce(function(sum, item) {
                        if (item.is_parent) return sum;
                        const t = parseFloat(item.transit) || 0;
                        const cp = parseFloat(item.CP) || 0;
                        return sum + (t * cp);
                    }, 0);
                }
                const totalTransitValueElement = document.getElementById('total_transit_value_display');
                if (totalTransitValueElement) {
                    const roundedTotal = Math.round(totalTransitValue);
                    totalTransitValueElement.textContent = roundedTotal.toLocaleString('en-US');
                }

                   // Calculate total restock MSL LP
                const totalLp = restockItems.reduce((sum, item) => sum + (parseFloat(item.LP) || 0), 0);
                const averageLp = restockCount > 0 ? totalLp / restockCount : 0;
                const totalRestockMslLp = restockCount * (averageLp / 4);
                const totalRestockMslLpElement = document.getElementById('total_restock_msl_lp_value');
                if (totalRestockMslLpElement) {
                    const wholeNumber = Math.round(totalRestockMslLp);
                    totalRestockMslLpElement.textContent = wholeNumber.toLocaleString('en-US');
                }


                const groupedMSL = {};
                const groupedS_MSL = {};

                const processed = response.data.map((item, index) => {
                    const sku = item["SKU"] || "";
                    const parentKey = item["Parent"] || "";

                    const total = parseFloat(item["Total"]) || 0;
                    const totalMonth = parseFloat(item["Total month"]) || 0;

                    const inv = parseFloat(item["INV"]) || 0;
                    const transit = parseFloat(item["Transit"] ?? item["transit"]) || 0;
                    const orderGiven = parseFloat(item["order_given"] ?? item["Order Given"]) || 0;
                    const r2s = parseFloat(item["readyToShipQty"] ?? item["readyToShipQty"]) || 0;
                    const msl = totalMonth > 0 ? (total / totalMonth) * 4 : 0;
                    const s_msl_val = parseFloat(item["s_msl"] ?? item["s-msl"]) || 0;
                    const effectiveMslForToOrder = Math.max(msl, s_msl_val);
                    const m_avg = totalMonth > 0 ? total / totalMonth : 0;

                    // Get stage from item to determine which fields to use for to_order calculation
                    const itemStage = item.stage || '';
                    
                    // For to_order calculation, transit is always considered (since it always shows)
                    // MIP and R2S are only considered when their respective stage matches
                    let effectiveOrderGiven = 0;
                    let effectiveR2s = 0;
                    let effectiveTransit = transit; // Always include transit in calculation
                    
                    if (itemStage === 'mip') {
                        effectiveOrderGiven = orderGiven;
                    } else if (itemStage === 'r2s') {
                        effectiveR2s = r2s;
                    }
                    // Note: Transit is always included regardless of stage

                    const toOrder = Math.round(effectiveMslForToOrder - inv - effectiveTransit - effectiveOrderGiven - effectiveR2s);

                    const stageNorm = String(itemStage || '').trim().toLowerCase();
                    const twoOrderQty = stageNorm === 'to_order_analysis'
                        ? (parseFloat(item.two_order_qty ?? item['two_order_qty'] ?? item.MOQ ?? item['Approved QTY']) || 0)
                        : 0;
                    const apprReqQty = stageNorm === 'appr_req'
                        ? (parseFloat(item.MOQ ?? item['Approved QTY']) || 0)
                        : 0;

                    // if (toOrder == 0) {
                    //     return false;
                    // }

                    if (!groupedMSL[parentKey]) groupedMSL[parentKey] = 0;
                    groupedMSL[parentKey] += msl;

                    if (!groupedS_MSL[parentKey]) groupedS_MSL[parentKey] = 0;
                    groupedS_MSL[parentKey] += s_msl_val;

                    const isParent = item.is_parent === true || item.is_parent === "true" || sku.toUpperCase().includes("PARENT");

                    // Calculate MSL_C (MSL * LP / 4)
                    const lp = parseFloat(item["LP"]) || 0;
                    const msl_c = Math.round((msl * lp / 4) * 100) / 100; // Round to 2 decimal places
                    
                    // MSL_SP badge: same as MSL_LP but AMZ price (amazon_datsheets.price)
                    const amzPrc = parseFloat(item.amz_prc) || 0;
                    const msl_sp_amz = Math.round((msl * amzPrc / 4) * 100) / 100;
                    
                    // Calculate MSL SP (shopify price * MSL / 4)
                    const shopifyPrice = parseFloat(item["shopifyb2c_price"]) || 0;
                    const msl_sp = Math.round(shopifyPrice * msl / 4);

                    // Get original values (itemStage already declared above)
                    const originalOrderGiven = parseFloat(item['order_given'] ?? item['Order Given'] ?? 0);
                    const originalReadyToShipQty = parseFloat(item['readyToShipQty'] ?? item['ready_to_ship'] ?? 0);
                    const originalTransit = parseFloat(item['transit'] ?? item['Transit'] ?? 0);
                    
                    // Clear stage fields based on current stage - only show value for matching stage
                    // Transit values always show regardless of stage
                    let finalOrderGiven = 0;
                    let finalReadyToShipQty = 0;
                    let finalTransit = originalTransit; // Always show transit value
                    
                    // If stage is set, only show value for matching stage (except transit which always shows)
                    if (itemStage === 'mip') {
                        finalOrderGiven = originalOrderGiven;
                    } else if (itemStage === 'r2s') {
                        finalReadyToShipQty = originalReadyToShipQty;
                    }

                    const processedItem = {
                        ...item,
                        sl_no: index + 1,
                        pft_percent: item['pft%'] ?? null,
                        msl: Math.round(msl),
                        m_avg: m_avg,
                        MSL_C: msl_c,
                        MSL_SP: msl_sp,
                        MSL_SP_AMZ: msl_sp_amz,
                        amz_prc: amzPrc,
                        to_order: toOrder,
                        two_order_qty: twoOrderQty,
                        appr_req_qty: apprReqQty,
                        parentKey: parentKey,
                        s_msl: s_msl_val,
                        is_parent: isParent,
                        isParent: isParent,
                        raw_data: item || {},
                        order_given: finalOrderGiven,
                        readyToShipQty: finalReadyToShipQty,
                        transit: finalTransit
                    };

                    // Group for play button use
                    if (!groupedSkuData[parentKey]) groupedSkuData[parentKey] = [];
                    groupedSkuData[parentKey].push(processedItem);

                    return processedItem;
                });

                // Update parent rows with sum of all child SKUs (INV, L30, etc.)
                processed.forEach(row => {
                    if (row.isParent) {
                        const parentKey = row.parentKey;
                        const children = (groupedSkuData[parentKey] || []).filter(c => !c.is_parent && !c.isParent);
                        const sumInv = children.reduce((s, c) => s + (parseFloat(c.INV) || parseFloat(c.raw_data && c.raw_data["INV"]) || 0), 0);
                        const sumL30 = children.reduce((s, c) => s + (parseFloat(c["L30"]) || parseFloat(c.raw_data && c.raw_data["L30"]) || 0), 0);
                        const sumOrderGiven = children.reduce((s, c) => s + (parseFloat(c.order_given) || parseFloat(c.raw_data && c.raw_data["order_given"]) || 0), 0);
                        const sumTransit = children.reduce((s, c) => s + (parseFloat(c.transit) || parseFloat(c.raw_data && c.raw_data["transit"]) || 0), 0);
                        const sumToOrder = children.reduce((s, c) => s + (parseFloat(c.to_order) || 0), 0);
                        const sumMOQ = children.reduce((s, c) => s + (parseFloat(c.MOQ) || parseFloat(c.raw_data && c.raw_data["MOQ"]) || 0), 0);
                        const sumTwoOrderQty = children.reduce((s, c) => s + (parseFloat(c.two_order_qty) || 0), 0);
                        const sumApprReqQty = children.reduce((s, c) => s + (parseFloat(c.appr_req_qty) || 0), 0);
                        row.m_avg = 0;
                        row.TAT = null;
                        const gpftKids = children.map(c => c.avg_gpft_pct).filter(function(v) {
                            return v != null && v !== '' && Number.isFinite(parseFloat(v));
                        });
                        row.avg_gpft_pct = gpftKids.length
                            ? Math.round(gpftKids.reduce(function(s, x) { return s + parseFloat(x); }, 0) / gpftKids.length)
                            : null;
                        const adKids = children.map(c => c.avg_ad_pct).filter(function(v) {
                            return v != null && v !== '' && Number.isFinite(parseFloat(v));
                        });
                        row.avg_ad_pct = adKids.length
                            ? Math.round(adKids.reduce(function(s, x) { return s + parseFloat(x); }, 0) / adKids.length * 10) / 10
                            : null;
                        const npftKids = children.map(c => c.avg_npft_pct).filter(function(v) {
                            return v != null && v !== '' && Number.isFinite(parseFloat(v));
                        });
                        row.avg_npft_pct = npftKids.length
                            ? Math.round(npftKids.reduce(function(s, x) { return s + parseFloat(x); }, 0) / npftKids.length)
                            : null;
                        const nroiKids = children.map(c => c.avg_groi_pct).filter(function(v) {
                            return v != null && v !== '' && Number.isFinite(parseFloat(v));
                        });
                        row.avg_groi_pct = nroiKids.length
                            ? Math.round(nroiKids.reduce(function(s, x) { return s + parseFloat(x); }, 0) / nroiKids.length)
                            : null;
                        const effKids = children.map(function(c) {
                            const mAvg = parseFloat(c.m_avg) || 0;
                            const moq = parseFloat(c.MOQ) || 0;
                            const tat = mAvg > 0 ? Math.round(moq / mAvg) : 0;
                            const nroi = parseFloat(c.avg_groi_pct);
                            if (!tat || tat <= 0 || !Number.isFinite(nroi)) {
                                return null;
                            }

                            return Math.round((nroi / tat) * 12);
                        }).filter(function(x) { return x != null && !isNaN(x); });
                        row.eff_roi_pct = effKids.length
                            ? Math.round(effKids.reduce(function(s, x) { return s + x; }, 0) / effKids.length)
                            : null;
                        const revNums = children.map(function(c) {
                            const r = c.reviews;
                            if (r === null || r === undefined || r === '') {
                                return null;
                            }
                            const n = parseInt(String(r).replace(/,/g, ''), 10);

                            return Number.isFinite(n) ? n : null;
                        }).filter(function(x) { return x != null; });
                        row.reviews = revNums.length
                            ? Math.round(revNums.reduce(function(s, x) { return s + x; }, 0) / revNums.length)
                            : null;
                        const ratNums = children.map(function(c) {
                            const r = c.rating;
                            if (r === null || r === undefined || r === '') {
                                return null;
                            }
                            const n = parseFloat(r);

                            return Number.isFinite(n) ? n : null;
                        }).filter(function(x) { return x != null; });
                        row.rating = ratNums.length
                            ? Math.round(ratNums.reduce(function(s, x) { return s + x; }, 0) / ratNums.length * 10) / 10
                            : null;
                        row.INV = sumInv;
                        row["L30"] = sumL30;
                        row.order_given = sumOrderGiven;
                        row.transit = sumTransit;
                        row.to_order = sumToOrder;
                        row.MOQ = sumMOQ;
                        row.two_order_qty = sumTwoOrderQty;
                        row.appr_req_qty = sumApprReqQty;
                        if (row.raw_data) {
                            row.raw_data["INV"] = sumInv;
                            row.raw_data["L30"] = sumL30;
                            row.raw_data["order_given"] = sumOrderGiven;
                            row.raw_data["transit"] = sumTransit;
                            row.raw_data["to_order"] = sumToOrder;
                            row.raw_data["MOQ"] = sumMOQ;
                            row.raw_data["two_order_qty"] = sumTwoOrderQty;
                            row.raw_data["appr_req_qty"] = sumApprReqQty;
                        }
                    }
                });

                // Sort so parent row is last within each Parent group (bottom of filtered rows)
                const groupOrder = [];
                const groups = {};
                processed.forEach(function(row) {
                    const p = row.Parent || '';
                    if (!groups[p]) { groups[p] = []; groupOrder.push(p); }
                    groups[p].push(row);
                });
                const sorted = [];
                groupOrder.forEach(function(p) {
                    const g = groups[p];
                    g.sort(function(a, b) { return (a.is_parent ? 1 : 0) - (b.is_parent ? 1 : 0); });
                    sorted.push.apply(sorted, g);
                });

                setTimeout(() => {
                    setCombinedFilters();
                    // Default sort by Order Date oldest first (ascending)
                    table.setSort([{ column: "mfrg_order_date", dir: "asc" }]);
                    // Update SKU column header with count (excluding rows with "parent" in SKU)
                    table.updateColumnDefinition("SKU", { title: "SKU (" + skuCount + ")" });
                    // Update column headers with count of rows (excluding parent) where value > 0
                    const allData = table.getData();
                    const notParent = row => !String(row.SKU || '').toLowerCase().includes('parent');
                    const mslCount = allData.filter(row => notParent(row) && (parseFloat(row.msl) || 0) > 0).length;
                    const toOrderCount = allData.filter(row => notParent(row) && (parseFloat(row.to_order) || 0) > 0).length;
                    const orderCount = allData.filter(row => notParent(row) && (parseFloat(row.two_order_qty) || 0) > 0).length;
                    const mipCount = allData.filter(row => notParent(row) && (parseFloat(row.order_given) || 0) > 0).length;
                    const r2sCount = allData.filter(row => notParent(row) && (parseFloat(row.readyToShipQty) || 0) > 0).length;
                    const transitCount = allData.filter(row => notParent(row) && (parseFloat(row.transit) || 0) > 0).length;
                    const moqCount = allData.filter(row => notParent(row) && (parseFloat(row.MOQ) || 0) > 0).length;
                    const apprReqStageCount = allData.filter(row => notParent(row) && (parseFloat(row.appr_req_qty) || 0) > 0).length;
                    table.updateColumnDefinition("msl", { title: "MSL (" + mslCount + ")" });
                    table.updateColumnDefinition("to_order", { title: "2 Ord (" + toOrderCount + ")" });
                    table.updateColumnDefinition("appr_req_qty", { title: "Appr Req (" + apprReqStageCount + ")" });
                    table.updateColumnDefinition("two_order_qty", { title: "Order" });
                    table.updateColumnDefinition("order_given", { title: "MIP" });
                    table.updateColumnDefinition("readyToShipQty", { title: "R2S" });
                    table.updateColumnDefinition("transit", { title: "Trn" });
                    table.updateColumnDefinition("MOQ", { title: "MOQ" });
                    updateOrderColumnHeader(toOrderCount);
                    currentMoqPositiveCount = moqCount;
                    updateTopQtyFilterOptionCounts({
                        order: orderCount,
                        mip: mipCount,
                        r2s: r2sCount,
                        trn: transitCount,
                        moq: moqCount,
                    });
                }, 0);
                return sorted;
            },

            ajaxError: function(xhr, textStatus, errorThrown) {
                console.error("Error loading data:", textStatus);
            },
        });

        (function bindForecastTableViewportHeight() {
            function applyForecastTableHeight() {
                const wrap = document.getElementById('forecast-table-wrap');
                if (!wrap || typeof table === 'undefined' || !table) return;
                const top = wrap.getBoundingClientRect().top;
                const px = Math.max(320, Math.floor(window.innerHeight - top - 2));
                wrap.style.height = px + 'px';
                if (typeof table.setHeight === 'function') {
                    table.setHeight(px);
                }
            }
            window.addEventListener('resize', function() { requestAnimationFrame(applyForecastTableHeight); });
            table.on('tableBuilt', function() { requestAnimationFrame(applyForecastTableHeight); });
            table.on('dataLoaded', function() { requestAnimationFrame(applyForecastTableHeight); });
            window.addEventListener('load', function() { requestAnimationFrame(applyForecastTableHeight); });
            const host = document.querySelector('#forecast-table-wrap')?.closest('.card-body');
            if (host && typeof ResizeObserver !== 'undefined') {
                const ro = new ResizeObserver(function() {
                    requestAnimationFrame(applyForecastTableHeight);
                });
                ro.observe(host);
            }
            requestAnimationFrame(applyForecastTableHeight);
        })();

        window.forecastSuppliersList = [];
        function loadForecastSuppliers(callback) {
            fetch('/supplier.list.json')
                .then(r => r.json())
                .then(function(data) {
                    window.forecastSuppliersList = data.suppliers || [];
                    if (table) table.redraw();
                    if (typeof callback === 'function') callback();
                })
                .catch(function() { window.forecastSuppliersList = []; });
        }
        // Bulk edit badge: show when rows selected, update count
        function updateBulkEditBadge() {
            const selected = table.getSelectedRows();
            const badge = document.getElementById('bulk-edit-badge');
            const countEl = document.getElementById('bulk-edit-count');
            if (!badge || !countEl) return;
            const n = selected.length;
            if (n > 0) {
                badge.classList.remove('d-none');
                badge.classList.add('d-flex');
                countEl.textContent = n + ' selected';
            } else {
                badge.classList.add('d-none');
                badge.classList.remove('d-flex');
            }
        }
        table.on("rowSelectionChanged", function() {
            const scrollEl = table.rowManager?.element;
            const savedTop  = scrollEl?.scrollTop  || 0;
            const savedLeft = scrollEl?.scrollLeft || 0;
            const savedPage = (table.getPage && table.getPage() > 0) ? table.getPage() : null;

            updateBulkEditBadge();

            requestAnimationFrame(function() {
                const restoreScroll = function() {
                    if (scrollEl) {
                        scrollEl.scrollTop  = savedTop;
                        scrollEl.scrollLeft = savedLeft;
                    }
                };
                if (savedPage && table.getPage && table.getPage() !== savedPage) {
                    const p = table.setPage(savedPage);
                    if (p && typeof p.then === 'function') {
                        p.then(restoreScroll);
                    } else {
                        restoreScroll();
                    }
                } else {
                    restoreScroll();
                }
            });
        });
        table.on("dataLoaded", function() { updateTopRowCounter(); });
        table.on("dataFiltered", function() { updateTopRowCounter(); });
        table.on("pageLoaded", function() { updateTopRowCounter(); });

        // Populate bulk supplier dropdowns when suppliers load
        function refreshBulkSupplierSearchSelect() {
            const sel = document.getElementById('bulk-current-supplier-select');
            if (!sel || !window.SelectSearchable) return;
            window.SelectSearchable.refresh(sel);
        }
        function populateBulkSupplierSelect() {
            const sel = document.getElementById('bulk-current-supplier-select');
            if (!sel) return;
            sel.innerHTML = '<option value="">Select supplier...</option>';
            (window.forecastSuppliersList || []).forEach(function(s) {
                const opt = document.createElement('option');
                opt.value = s.name || s.id;
                opt.textContent = s.name || s.id;
                sel.appendChild(opt);
            });
            refreshBulkSupplierSearchSelect();
        }
        loadForecastSuppliers(function() { populateBulkSupplierSelect(); });

        document.getElementById('bulk-apply-current-supplier')?.addEventListener('click', function(e) {
            e.preventDefault();
            const supplierName = (document.getElementById('bulk-current-supplier-select')?.value || '').trim();
            if (!supplierName) { alert('Please select a supplier.'); return; }
            const selected = table.getSelectedRows();
            const validRows = [];
            selected.forEach(function(row) {
                const d = row.getData();
                const sku = (d.SKU || '').trim();
                if (sku && !sku.toLowerCase().includes('parent')) validRows.push({ row: row, sku: sku });
            });
            if (validRows.length === 0) { alert('No valid SKUs in selection.'); return; }
            const btn = this;
            btn.disabled = true;
            const token = document.querySelector('input[name="_token"]')?.value || document.querySelector('meta[name="csrf-token"]')?.content || '';
            const promises = validRows.map(function(item) {
                const fd = new FormData();
                fd.append('sku', item.sku);
                fd.append('column', 'supplier');
                fd.append('value', supplierName);
                fd.append('_token', token);
                return fetch('/mfrg-progresses/inline-update-by-sku', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                    .then(function(r) { return r.json(); });
            });
            Promise.all(promises).then(function() {
                validRows.forEach(function(item) {
                    item.row.update({ mfrg_supplier: supplierName }, true);
                });
                table.deselectRow();
                updateBulkEditBadge();
                btn.disabled = false;
                const sel = document.getElementById('bulk-current-supplier-select');
                if (sel) {
                    sel.value = '';
                    sel.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }).catch(function() { btn.disabled = false; });
        });

        function bulkApplyForecastField(column, getValue, btnId, selectId, ddBtnId) {
            const val = getValue();
            if (val === '' || val === null || val === undefined) {
                alert('Please enter a value.');
                return;
            }
            const selected = table.getSelectedRows();
            const rows = [];
            selected.forEach(function(row) {
                const d = row.getData();
                const sku = (d.SKU || '').trim();
                const parent = (d.Parent || '').trim();
                if (sku && !sku.toLowerCase().includes('parent')) rows.push({ sku, parent });
            });
            if (rows.length === 0) { alert('No valid rows in selection.'); return; }
            const btn = document.getElementById(btnId);
            if (btn) btn.disabled = true;
            let done = 0;
            const total = rows.length;
            const onComplete = function() {
                done++;
                if (done >= total) {
                    table.replaceData();
                    table.deselectRow();
                    updateBulkEditBadge();
                    if (btn) btn.disabled = false;
                    const el = document.getElementById(selectId);
                    if (el) el.value = el.tagName === 'SELECT' ? '' : '';
                    const dd = bootstrap.Dropdown.getInstance(document.querySelector('#' + ddBtnId));
                    if (dd) dd.hide();
                }
            };
            rows.forEach(function(r) {
                $.post('/update-forecast-data', { sku: r.sku, parent: r.parent, column: column, value: val, _token: $('meta[name="csrf-token"]').attr('content') })
                    .done(function() { onComplete(); })
                    .fail(function() { onComplete(); });
            });
        }

        document.getElementById('bulk-apply-stage')?.addEventListener('click', function(e) {
            e.preventDefault();
            bulkApplyForecastField('Stage', function() { return document.getElementById('bulk-stage-select')?.value?.trim() || ''; },
                'bulk-apply-stage', 'bulk-stage-select', 'bulkEditStageBtn');
        });
        document.getElementById('bulk-apply-nrp')?.addEventListener('click', function(e) {
            e.preventDefault();
            const v = document.getElementById('bulk-nrp-select')?.value?.trim() || '';
            if (!v) { alert('Please select an NRP value.'); return; }
            bulkApplyForecastField('NR', function() { return v; }, 'bulk-apply-nrp', 'bulk-nrp-select', 'bulkEditNrpBtn');
        });
        document.getElementById('bulk-apply-moq')?.addEventListener('click', function(e) {
            e.preventDefault();
            const v = document.getElementById('bulk-moq-input')?.value?.trim() || '';
            if (!v || isNaN(parseFloat(v))) { alert('Please enter a valid MOQ.'); return; }
            bulkApplyForecastField('MOQ', function() { return v; }, 'bulk-apply-moq', 'bulk-moq-input', 'bulkEditMoqBtn');
        });
        document.getElementById('bulk-apply-order')?.addEventListener('click', function(e) {
            e.preventDefault();
            const v = document.getElementById('bulk-order-input')?.value?.trim() || '';
            if (!v || isNaN(parseFloat(v))) { alert('Please enter a valid Order quantity.'); return; }
            bulkApplyForecastField('Order', function() { return v; }, 'bulk-apply-order', 'bulk-order-input', 'bulkEditOrderBtn');
        });
        document.getElementById('bulk-apply-cp')?.addEventListener('click', function(e) {
            e.preventDefault();
            const v = document.getElementById('bulk-cp-input')?.value?.trim() || '';
            if (!v || isNaN(parseFloat(v))) { alert('Please enter a valid CP.'); return; }
            bulkApplyForecastField('CP', function() { return v; }, 'bulk-apply-cp', 'bulk-cp-input', 'bulkEditCpBtn');
        });

        document.getElementById('bulk-apply-pmt-terms')?.addEventListener('click', function(e) {
            e.preventDefault();
            const val = (document.getElementById('bulk-pmt-terms-select')?.value || '').trim().toUpperCase();
            if (!val) { alert('Please select a Pmt Terms value.'); return; }
            const selected = table.getSelectedRows();
            const validRows = [];
            selected.forEach(function(row) {
                const d = row.getData();
                const sku = (d.SKU || '').trim();
                if (sku && !sku.toLowerCase().includes('parent')) validRows.push({ row: row, sku: sku });
            });
            if (validRows.length === 0) { alert('No valid SKUs in selection.'); return; }
            const btn = this;
            btn.disabled = true;
            const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const promises = validRows.map(function(item) {
                return fetch('/ready-to-ship/inline-update-by-sku', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ sku: item.sku, column: 'pay_term', value: val })
                }).then(r => r.json());
            });
            Promise.all(promises).then(function() {
                validRows.forEach(function(item) {
                    item.row.update({ r2s_pay_term: val }, true);
                });
                table.deselectRow();
                updateBulkEditBadge();
                btn.disabled = false;
                const sel = document.getElementById('bulk-pmt-terms-select');
                if (sel) sel.value = '';
                const dd = bootstrap.Dropdown.getInstance(document.getElementById('bulkEditPmtTermsBtn'));
                if (dd) dd.hide();
            }).catch(function() { btn.disabled = false; });
        });

        let currentParentFilter = null;
        let currentColorFilter = null;
        let currentSearchQuery = '';
        let currentSearchSku = '';
        let currentSearchParent = '';
        let currentSearchSupplier = '';
        let currentSupplierFilter = null;
        let currentZoneFilter = null;
        let currentContainerFilter = null;
        let currentTwoOrdColorFilter = '';
        let currentTopQtySignFilters = {
            order: '',
            mip: '',
            r2s: '',
            trn: '',
            moq: '',
        };
        const hideNRYes = false;
        const hideLATERYes = false;
        let currentRowTypeFilter = 'sku';
        let currentStageFilter = '';
        let isProgrammaticStageHeaderSync = false;
        let afterTatVisibilitySnapshot = null;
        let isApplyingCombinedFilters = false;
        let pendingCombinedFiltersRun = false;
        let filterPostCalcTimer = null;
        const FORECAST_FILTER_PREF_KEY = 'forecast_analysis_filter_prefs_v1';
        function readForecastFilterPrefs() {
            try {
                const raw = localStorage.getItem(FORECAST_FILTER_PREF_KEY);
                if (!raw) return {};
                const parsed = JSON.parse(raw);
                return parsed && typeof parsed === 'object' ? parsed : {};
            } catch (e) {
                return {};
            }
        }
        function saveForecastFilterPrefs() {
            try {
                const nrpChecked = Array.from(document.querySelectorAll('.nrp-ms-opt:checked')).map(function(cb) { return cb.value; });
                const payload = {
                    stageFilter: currentStageFilter || '',
                    rowTypeFilter: currentRowTypeFilter || 'sku',
                    apprReqFilter: currentColorFilter || '',
                    twoOrdFilter: currentTwoOrdColorFilter || '',
                    topQtyFilters: Object.assign({}, currentTopQtySignFilters || {}),
                    nrpChecked: nrpChecked.length ? nrpChecked : ['REQ', 'NR', 'LATER']
                };
                localStorage.setItem(FORECAST_FILTER_PREF_KEY, JSON.stringify(payload));
            } catch (e) {}
        }
        function restoreForecastFilterPrefs() {
            const prefs = readForecastFilterPrefs();

            const stageVal = normalizeStageValue(prefs.stageFilter || '');
            currentStageFilter = stageVal;
            const sf = document.getElementById('stage-filter');
            if (sf) sf.value = stageVal;

            const rowTypeVal = ['all', 'sku', 'parent'].includes(prefs.rowTypeFilter) ? prefs.rowTypeFilter : 'sku';
            currentRowTypeFilter = rowTypeVal;
            const rt = document.getElementById('row-data-type');
            if (rt) rt.value = rowTypeVal;

            currentColorFilter = prefs.apprReqFilter || '';
            const apprLabelEl = document.getElementById('appr-req-badge-label');
            if (apprLabelEl) {
                apprLabelEl.textContent = currentColorFilter === 'yellow' ? 'Appr Req.' : (currentColorFilter === 'red' ? 'Filter' : 'All');
            }

            const twoOrdVal = normalizeTwoOrdFilterValue(prefs.twoOrdFilter || '');
            currentTwoOrdColorFilter = twoOrdVal;
            const twoOrdSel = document.getElementById('two-ord-color-filter');
            if (twoOrdSel) twoOrdSel.value = twoOrdVal;

            const tq = prefs.topQtyFilters && typeof prefs.topQtyFilters === 'object' ? prefs.topQtyFilters : {};
            ['order', 'mip', 'r2s', 'trn', 'moq'].forEach(function(k) {
                const val = normalizeQtySignFilterValue(tq[k] || '');
                currentTopQtySignFilters[k] = val;
                const el = document.getElementById(k + '-color-filter-top');
                if (el) el.value = val;
            });

            const wantedNrp = Array.isArray(prefs.nrpChecked) && prefs.nrpChecked.length ? new Set(prefs.nrpChecked) : new Set(['REQ', 'NR', 'LATER']);
            document.querySelectorAll('.nrp-ms-opt').forEach(function(cb) {
                cb.checked = wantedNrp.has(cb.value);
            });
            updateNRPMultiselectLabel();
        }
        function updateTopRowCounter() {
            const el = document.getElementById('top-row-counter');
            if (!el || !table) return;
            let total = 0;
            try {
                total = Number(table.getDataCount('active')) || 0;
            } catch (e) {
                total = 0;
            }
            if (!Number.isFinite(total) || total < 0) {
                try {
                    total = (table.getRows('active') || []).length;
                } catch (e) {
                    total = 0;
                }
            }

            // Stage filter badge — only visible when a filter is active
            const badge = document.getElementById('stage-filter-badge');
            if (badge) {
                if (currentStageFilter) {
                    const activeRows = table.getRows('active') || [];
                    const childCount = activeRows.filter(function(row) {
                        const d = row.getData();
                        return !d.is_parent && !d.isParent;
                    }).length;
                    const labelMap = {
                        '__blank__':         'Not Req Now',
                        'two_ord_nonneg':    '2 Ord',
                        'appr_req':          'Appr Req',
                        'mip':               'MIP',
                        'r2s':               'R2S',
                        'transit':           'Trn',
                        'to_order_analysis': 'Order'
                    };
                    const qtyFieldMap = {
                        '__blank__':         null,
                        'two_ord_nonneg':    'to_order',
                        'appr_req':          'appr_req_qty',
                        'mip':               'order_given',
                        'r2s':               'readyToShipQty',
                        'transit':           'transit',
                        'to_order_analysis': 'two_order_qty'
                    };
                    const label    = labelMap[currentStageFilter] || currentStageFilter;
                    const qtyField = qtyFieldMap[currentStageFilter] || null;
                    let totalQty   = 0;
                    if (qtyField) {
                        activeRows.forEach(function(row) {
                            const d = row.getData();
                            if (!d.is_parent && !d.isParent) {
                                totalQty += parseFloat(d[qtyField]) || 0;
                            }
                        });
                    }
                    const qtyPart = qtyField ? ' &nbsp;|&nbsp; QTY: <strong>' + Math.round(totalQty).toLocaleString() + '</strong>' : '';
                    badge.innerHTML = label + ': <strong>' + childCount + '</strong> SKU' + (childCount !== 1 ? 's' : '') + qtyPart;
                    badge.style.display = 'inline-block';
                } else {
                    badge.style.display = 'none';
                }
            }

            let start = 0;
            let end = 0;
            if (total > 0) {
                let pageSize = 0;
                let pageNo = 1;
                try { pageSize = Number(table.getPageSize()); } catch (e) {}
                try { pageNo = Number(table.getPage()); } catch (e) {}
                if (!Number.isFinite(pageSize) || pageSize <= 0) pageSize = total;
                if (!Number.isFinite(pageNo) || pageNo <= 0) pageNo = 1;
                start = ((pageNo - 1) * pageSize) + 1;
                end = Math.min(total, pageNo * pageSize);
            }
            el.textContent = `Showing ${start}-${end} of ${total} rows`;
        }
        function normalizeTwoOrdFilterValue(rawValue) {
            const raw = (rawValue || '').trim().toLowerCase();
            if (raw === 'red') return 'neg';
            if (raw === 'yellow') return 'nonneg';
            return raw;
        }
        function applyTwoOrdColorFilter(rawValue) {
            currentTwoOrdColorFilter = normalizeTwoOrdFilterValue(rawValue);
            currentParentFilter = null;
            if (currentTwoOrdColorFilter) {
                currentColorFilter = null;
                const apprLabel = document.getElementById('appr-req-badge-label');
                if (apprLabel) apprLabel.textContent = 'All';
            }
            if (typeof setCombinedFilters === 'function' && table && table.rowManager && table.rowManager.element) {
                setCombinedFilters();
            }
            saveForecastFilterPrefs();
        }
        window.__fa_applyTwoOrdColorFilter = applyTwoOrdColorFilter;
        if (!window.__fa_twoOrdChangeBound) {
            window.__fa_twoOrdChangeBound = true;
            document.addEventListener('change', function(e) {
                const t = e && e.target;
                if (t && t.id === 'two-ord-color-filter') {
                    applyTwoOrdColorFilter(t.value || '');
                }
            });
        }
        /** INV header: empty=all, 0/zero=exactly 0, >0/gt0=positive */
        let currentInvFilter = '';
        function invHeaderFilterMode(raw) {
            if (raw === undefined || raw === null) return '';
            const s = String(raw).trim().toLowerCase();
            if (s === '' || s === 'all') return '';
            if (s === '0' || s === 'zero' || s === '=0') return 'zero';
            if (s === '>0' || s === '> 0' || s === 'gt0' || s === '+' || s === 'pos' || s === 'positive') return 'gt0';
            return '';
        }
        function invHeaderMatchesRow(data) {
            if (!data) return true;
            const mode = invHeaderFilterMode(currentInvFilter);
            if (!mode) return true;
            const invValue = data.raw_data ? data.raw_data["INV"] : data["INV"];
            const inv = parseFloat(invValue);
            const invNum = Number.isFinite(inv) ? inv : 0;
            if (mode === 'gt0') return invNum > 0;
            if (mode === 'zero') return invNum === 0;
            return true;
        }
        function syncInvFilterFromHeader() {
            const tbl = Tabulator.findTable("#forecast-table")[0];
            if (!tbl || typeof tbl.getHeaderFilterValue !== 'function') return;
            const v = tbl.getHeaderFilterValue("INV");
            currentInvFilter = v === undefined || v === null ? '' : String(v);
            if (typeof setCombinedFilters === 'function') setCombinedFilters();
        }
        function normalizeStageValue(value) {
            if (value === undefined || value === null) return '';
            return String(value).trim().toLowerCase();
        }
        function matchesStageFilterValue(rowData, stageFilterValue) {
            const stageValue = normalizeStageValue(rowData && rowData.stage);
            const raw = rowData && rowData.raw_data ? rowData.raw_data : {};
            if (!stageFilterValue) return true;
            if (stageFilterValue === '__blank__') {
                const twoOrd = parseFloat(rowData?.to_order ?? raw.to_order ?? 0);
                return (!stageValue || stageValue === '') || (Number.isFinite(twoOrd) && twoOrd < 0);
            }
            if (stageFilterValue === 'two_ord_nonneg') {
                const twoOrd = parseFloat(rowData?.to_order ?? raw.to_order ?? 0);
                return Number.isFinite(twoOrd) && twoOrd >= 0;
            }
            if (stageFilterValue === 'transit') {
                const transit = parseFloat(rowData?.transit ?? raw.transit ?? raw["Transit"] ?? 0) || 0;
                return stageValue === 'transit' || transit > 0;
            }
            if (stageFilterValue === 'appr_req') {
                return getEffectiveApprReqValue(rowData) > 0;
            }
            if (stageFilterValue === 'mip') {
                const mip = parseFloat(rowData?.order_given ?? raw.order_given ?? raw["Order Given"] ?? 0) || 0;
                return stageValue === 'mip' || mip > 0;
            }
            if (stageFilterValue === 'r2s') {
                const r2s = parseFloat(rowData?.readyToShipQty ?? raw.readyToShipQty ?? raw["readyToShipQty"] ?? 0) || 0;
                return stageValue === 'r2s' || r2s > 0;
            }
            if (stageFilterValue === 'to_order_analysis') {
                const orderQty = parseFloat(rowData?.two_order_qty ?? raw.two_order_qty ?? raw["two_order_qty"] ?? 0) || 0;
                return stageValue === 'to_order_analysis' || orderQty > 0;
            }
            return stageValue === stageFilterValue;
        }
        function syncColumnsAfterTatForStageFilter() {
            if (!table || typeof table.getColumns !== 'function') return;

            // Apply this hide rule only when Stage filter is specifically "2 Ord".
            const stageActive = currentStageFilter === 'two_ord_nonneg';
            const allCols = table.getColumns() || [];
            const tatIdx = allCols.findIndex(function(c) {
                return String(c.getField ? (c.getField() || '') : '').trim() === 'TAT';
            });
            if (tatIdx < 0) return;

            const colsAfterTat = allCols.slice(tatIdx + 1).filter(function(c) {
                const f = c.getField ? c.getField() : '';
                return !!f;
            });

            if (stageActive) {
                if (afterTatVisibilitySnapshot === null) {
                    afterTatVisibilitySnapshot = {};
                    colsAfterTat.forEach(function(c) {
                        const f = c.getField();
                        afterTatVisibilitySnapshot[f] = !!c.isVisible();
                    });
                }
                colsAfterTat.forEach(function(c) {
                    if (c.isVisible()) c.hide();
                });
            } else if (afterTatVisibilitySnapshot !== null) {
                colsAfterTat.forEach(function(c) {
                    const f = c.getField();
                    const shouldShow = afterTatVisibilitySnapshot[f];
                    if (shouldShow === true) c.show();
                    if (shouldShow === false) c.hide();
                });
                afterTatVisibilitySnapshot = null;
            }

            // Do not rebuild column dropdown here: it reapplies saved localStorage visibility
            // and can immediately undo the temporary Stage-based hide/show behavior.
        }
        function matchesTwoOrdColorFilter(data) {
            if (!currentTwoOrdColorFilter) return true;
            const n = parseFloat(data && data.to_order);
            const v = Number.isFinite(n) ? n : 0;
            if (currentTwoOrdColorFilter === 'neg' || currentTwoOrdColorFilter === 'red') return v < 0;
            if (currentTwoOrdColorFilter === 'nonneg' || currentTwoOrdColorFilter === 'yellow') return v >= 0;
            return true;
        }
        function normalizeQtySignFilterValue(rawValue) {
            const raw = (rawValue || '').trim().toLowerCase();
            if (raw === 'pos' || raw === '>0' || raw === 'gt0' || raw === 'positive' || raw === 'yellow' || raw === 'nonneg') return 'pos';
            return '';
        }
        function hasActiveTopQtySignFilter() {
            return !!(
                currentTopQtySignFilters.order ||
                currentTopQtySignFilters.mip ||
                currentTopQtySignFilters.r2s ||
                currentTopQtySignFilters.trn ||
                currentTopQtySignFilters.moq
            );
        }
        function getQtyFilterNumericValue(data, key) {
            const raw = data && data.raw_data ? data.raw_data : {};
            if (key === 'order') {
                const n = parseFloat(data?.two_order_qty ?? raw?.two_order_qty ?? raw?.['two_order_qty']);
                return Number.isFinite(n) ? n : 0;
            }
            if (key === 'mip') {
                const n = parseFloat(data?.order_given ?? raw?.order_given ?? raw?.['Order Given']);
                return Number.isFinite(n) ? n : 0;
            }
            if (key === 'r2s') {
                const n = parseFloat(data?.readyToShipQty ?? raw?.readyToShipQty ?? raw?.['readyToShipQty']);
                return Number.isFinite(n) ? n : 0;
            }
            if (key === 'trn') {
                const n = parseFloat(data?.transit ?? raw?.transit ?? raw?.['Transit']);
                return Number.isFinite(n) ? n : 0;
            }
            if (key === 'moq') {
                const n = parseFloat(data?.MOQ ?? raw?.MOQ ?? raw?.['Approved QTY']);
                return Number.isFinite(n) ? n : 0;
            }
            return 0;
        }
        function matchesTopQtySignFilters(data) {
            if (!hasActiveTopQtySignFilter()) return true;
            const keys = ['order', 'mip', 'r2s', 'trn', 'moq'];
            for (let i = 0; i < keys.length; i++) {
                const k = keys[i];
                const mode = normalizeQtySignFilterValue(currentTopQtySignFilters[k]);
                if (!mode) continue;
                const v = getQtyFilterNumericValue(data, k);
                if (mode === 'pos' && !(v > 0)) return false;
            }
            return true;
        }
        function applyTopQtySignFilter(key, rawValue) {
            if (!currentTopQtySignFilters || !Object.prototype.hasOwnProperty.call(currentTopQtySignFilters, key)) return;
            const normalized = normalizeQtySignFilterValue(rawValue);
            // These 5 top qty filters are mutually exclusive by UX request.
            if (normalized) {
                Object.keys(currentTopQtySignFilters).forEach(function(k) {
                    currentTopQtySignFilters[k] = (k === key) ? normalized : '';
                });
                const topQtyFilterIds = {
                    order: 'order-color-filter-top',
                    mip: 'mip-color-filter-top',
                    r2s: 'r2s-color-filter-top',
                    trn: 'trn-color-filter-top',
                    moq: 'moq-color-filter-top',
                };
                Object.keys(topQtyFilterIds).forEach(function(k) {
                    if (k === key) return;
                    const el = document.getElementById(topQtyFilterIds[k]);
                    if (el && el.value !== '') el.value = '';
                });
            } else {
                currentTopQtySignFilters[key] = '';
            }
            currentParentFilter = null;
            if (typeof setCombinedFilters === 'function' && table && table.rowManager && table.rowManager.element) {
                setCombinedFilters();
            }
        }
        function syncParentStageQtyColumns(parentKey) {
            if (!parentKey || typeof table === 'undefined' || !table) return;
            let sumTwo = 0;
            let sumAppr = 0;
            let parentRow = null;
            table.getRows().forEach(function(r) {
                const d = r.getData();
                const pk = d.Parent || d.parentKey || '';
                if (pk !== parentKey) return;
                const isP = d.is_parent === true || String(d.SKU || '').toLowerCase().includes('parent');
                if (isP) parentRow = r;
                else {
                    sumTwo += parseFloat(d.two_order_qty) || 0;
                    sumAppr += parseFloat(d.appr_req_qty) || 0;
                }
            });
            if (parentRow) parentRow.update({ two_order_qty: sumTwo, appr_req_qty: sumAppr }, true);
        }

        /** After child MOQ edits: keep parent aggregate MOQ in sync (raw_data is used by parent rollups). */
        function refreshParentMoqFromChildren(parentKey) {
            if (!parentKey || typeof table === 'undefined' || !table) return;
            let sumMOQ = 0;
            let parentRow = null;
            table.getRows().forEach(function(r) {
                const d = r.getData();
                const pk = d.Parent || d.parentKey || '';
                if (pk !== parentKey) return;
                const isP = d.is_parent === true || String(d.SKU || '').toLowerCase().includes('parent');
                if (isP) {
                    parentRow = r;
                } else {
                    const moq = parseFloat(d.MOQ) || parseFloat(d.raw_data && d.raw_data['MOQ']) || 0;
                    sumMOQ += moq;
                }
            });
            if (parentRow) {
                const pd = parentRow.getData();
                const rd = Object.assign({}, pd.raw_data || {}, { MOQ: sumMOQ });
                parentRow.update({ MOQ: sumMOQ, raw_data: rd }, true);
                const moqCell = parentRow.getCells().find(function(x) { return x.getField() === 'MOQ'; });
                if (moqCell) moqCell.reformat();
            }
        }
        function getEffectiveNRPFilterSet() {
            const checked = [...document.querySelectorAll('.nrp-ms-opt:checked')].map(b => b.value);
            if (checked.length === 0) {
                document.querySelectorAll('.nrp-ms-opt').forEach(cb => { cb.checked = true; });
                return null;
            }
            if (checked.length === 3) return null;
            return new Set(checked);
        }

        function updateNRPMultiselectLabel() {
            const el = document.getElementById('nrp-filter-label');
            if (!el) return;
            const checked = [...document.querySelectorAll('.nrp-ms-opt:checked')].map(b => b.value);
            if (checked.length === 3 || checked.length === 0) {
                el.textContent = 'ALL Items';
                return;
            }
            const labels = { REQ: 'REQ', NR: '2BDC', LATER: 'LATER' };
            el.textContent = checked.map(v => labels[v] || v).join(', ');
        }

        function syncNRPMultiselectFromHeader(val) {
            const v = (val === undefined || val === null || val === '') ? '' : String(val).trim().toUpperCase();
            document.querySelectorAll('.nrp-ms-opt').forEach(cb => {
                cb.checked = !v ? true : (cb.value === v);
            });
            updateNRPMultiselectLabel();
        }

        function isNullOrDashQty(value) {
            if (value === null || value === undefined) return true;
            const raw = String(value).trim();
            if (raw === '' || raw === '-' || raw === '—') return true;
            const n = parseFloat(raw);
            return !Number.isFinite(n) || n === 0;
        }

        /** APPR Req. filter: hide rows with any pipeline qty in Order / MIP / R2S / Trn */
        function apprReqHideRowForPipelineQty(rowData) {
            if (!rowData || rowData.is_parent || rowData.isParent) return false;
            const raw = rowData.raw_data || {};
            const transit = rowData.transit ?? raw.transit ?? raw['Transit'] ?? null;
            const r2s = rowData.readyToShipQty ?? raw.readyToShipQty ?? raw['readyToShipQty'] ?? null;
            const mip = rowData.order_given ?? raw.order_given ?? raw['Order Given'] ?? null;
            const order = rowData.two_order_qty ?? raw.two_order_qty ?? raw['two_order_qty'] ?? null;
            return !isNullOrDashQty(transit) || !isNullOrDashQty(r2s) || !isNullOrDashQty(mip) || !isNullOrDashQty(order);
        }

        /** Appr Req. filter: hide NRP = 2BDC (NR) and LATER — only REQ rows */
        function apprReqHideRowForNrp2BdcOrLater(rowData) {
            if (!rowData || rowData.is_parent) return false;
            const nr = String(rowData.nr || '').trim().toUpperCase();
            return nr === 'NR' || nr === 'LATER';
        }

        function apprReqYellowRowVisible(rowData) {
            if (!rowData || rowData.is_parent || rowData.isParent) return false;
            const raw = rowData.raw_data || {};
            const twoOrdVal = parseFloat(rowData.to_order ?? raw.to_order ?? 0);
            if (!Number.isFinite(twoOrdVal) || twoOrdVal < 0) return false;
            return !apprReqHideRowForPipelineQty(rowData) &&
                !apprReqHideRowForNrp2BdcOrLater(rowData);
        }

        /** Fallback rule for Appr Req cell:
         * if 2 Ord >= 0 and Order/MIP/R2S/Trn are null-or-dash, show MOQ (yellow).
         */
        function shouldShowMoqFallbackInApprReq(rowData) {
            if (!rowData || rowData.is_parent || rowData.isParent) return false;
            const raw = rowData.raw_data || {};
            const twoOrdVal = parseFloat(rowData.to_order ?? raw.to_order ?? 0);
            if (!Number.isFinite(twoOrdVal) || twoOrdVal < 0) return false;
            return !apprReqHideRowForPipelineQty(rowData);
        }

        function getEffectiveApprReqValue(rowData) {
            if (!rowData || rowData.is_parent || rowData.isParent) return 0;
            const explicitApprReq = parseFloat(rowData.appr_req_qty);
            if (Number.isFinite(explicitApprReq) && explicitApprReq > 0) {
                return explicitApprReq;
            }
            if (shouldShowMoqFallbackInApprReq(rowData)) {
                const raw = rowData.raw_data || {};
                const moqVal = parseFloat(rowData.MOQ ?? raw.MOQ ?? raw['Approved QTY']);
                if (Number.isFinite(moqVal) && moqVal > 0) {
                    return moqVal;
                }
            }
            return 0;
        }

        function setCombinedFilters() {
            if (isApplyingCombinedFilters) {
                pendingCombinedFiltersRun = true;
                return;
            }
            const allData = table && typeof table.getData === 'function' ? table.getData() : [];
            // Tabulator may emit early lifecycle events before rowManager element exists.
            // Applying filters in that window can throw inside RowManager.getBoundingClientRect.
            if (!table || !table.rowManager || !table.rowManager.element) {
                return;
            }
            isApplyingCombinedFilters = true;
            try {
            const groupedChildrenMap = {};
            const visibleParentKeys = new Set();

            const nrpSel = getEffectiveNRPFilterSet();

            // Group all children by parent
            allData.forEach(item => {
                if (!item.is_parent) {
                    const key = item.Parent;
                    if (!groupedChildrenMap[key]) groupedChildrenMap[key] = [];
                    groupedChildrenMap[key].push(item);
                }
            });

            // Determine which parents should be visible
            Object.keys(groupedChildrenMap).forEach(parentKey => {
                const children = groupedChildrenMap[parentKey];

                const matchingChildren = children.filter(child => {
                    const childNR = child.nr || '';
                    let effectiveChildNR = (childNR === '' ? 'REQ' : String(childNR).trim().toUpperCase());
                    if (effectiveChildNR !== 'REQ' && effectiveChildNR !== 'NR' && effectiveChildNR !== 'LATER') {
                        effectiveChildNR = 'REQ';
                    }
                    const nrpMatch = !nrpSel || nrpSel.has(effectiveChildNR);
                    const nrMatch = !nrpSel || nrpSel.has('NR') || !hideNRYes || child.nr !== 'NR';
                    const laterMatch = !nrpSel || nrpSel.has('LATER') || !hideLATERYes || child.nr !== 'LATER';
                    
                    // Stage filter - check stage field or transit field
                    // If filter is "__blank__", match empty/null/undefined stage
                    const stageMatch = matchesStageFilterValue(child, currentStageFilter);
                    
                    const filterMatch = currentColorFilter === 'red' ?
                        ((parseFloat(child.to_order) || 0) < 0) :
                        currentColorFilter === 'yellow' ?
                        apprReqYellowRowVisible(child) :
                        true;
                    const twoOrdColorMatch = matchesTwoOrdColorFilter(child);
                    const qtySignMatch = matchesTopQtySignFilters(child);
                    return nrMatch && laterMatch && nrpMatch && stageMatch && filterMatch && twoOrdColorMatch && qtySignMatch && invHeaderMatchesRow(child);
                });

                if (matchingChildren.length > 0) {
                    visibleParentKeys.add(parentKey);
                }
            });
            try {
            table.setFilter(function(row) {
                const data = typeof row.getData === 'function' ? row.getData() : row;

                // Global search: match SKU, Parent, or Supplier
                if (currentSearchQuery) {
                    const sku      = String(data.SKU || '').toLowerCase();
                    const parent   = String(data.Parent || '').toLowerCase();
                    const supplier = String(data.mfrg_supplier || '').toLowerCase();
                    if (!sku.includes(currentSearchQuery) && !parent.includes(currentSearchQuery) && !supplier.includes(currentSearchQuery)) {
                        return false;
                    }
                }

                // Per-column searches
                if (currentSearchSku      && !String(data.SKU            || '').toLowerCase().includes(currentSearchSku))      return false;
                if (currentSearchParent   && !String(data.Parent         || '').toLowerCase().includes(currentSearchParent))   return false;
                if (currentSearchSupplier && !String(data.mfrg_supplier  || '').toLowerCase().includes(currentSearchSupplier)) return false;

                // Supplier play filter
                if (currentSupplierFilter) {
                    const rowSupplier = String(data.mfrg_supplier || '').trim();
                    if (rowSupplier !== currentSupplierFilter) return false;
                }

                // Zone play filter
                if (currentZoneFilter) {
                    const rowZone = String(data.r2s_zone || '').trim();
                    if (rowZone !== currentZoneFilter) return false;
                }

                // Container (Trn) play filter
                if (currentContainerFilter) {
                    const rawContainer = String(data.containerName || '').trim();
                    if (!rawContainer) return false;
                    // containerName may be comma-separated; check if any part matches
                    const parts = rawContainer.split(',').map(function(p) { return p.trim(); });
                    if (!parts.includes(currentContainerFilter)) return false;
                }
                const isChild = !data.is_parent;
                const isParent = data.is_parent;
                const twoOrdRaw = data.to_order ?? (data.raw_data ? data.raw_data.to_order : 0);
                const twoOrdValue = Number.isFinite(parseFloat(twoOrdRaw)) ? parseFloat(twoOrdRaw) : 0;
                const twoOrdFilterLive = (document.getElementById('two-ord-color-filter')?.value || currentTwoOrdColorFilter || '').trim().toLowerCase();

                const matchesFilter = currentColorFilter === 'red' ?
                    ((parseFloat(data.to_order) || 0) < 0) :
                    currentColorFilter === 'yellow' ?
                    apprReqYellowRowVisible(data) :
                    true;
                const twoOrdColorMatch = matchesTwoOrdColorFilter(data);
                const qtySignMatch = matchesTopQtySignFilters(data);
                // Hard guard: when 2 Ord color filter is set, enforce it before all other checks.
                if ((twoOrdFilterLive === 'neg' || twoOrdFilterLive === 'red') && !(twoOrdValue < 0)) return false;
                if ((twoOrdFilterLive === 'nonneg' || twoOrdFilterLive === 'yellow') && !(twoOrdValue >= 0)) return false;

                const dataNR = data.nr || '';
                let effectiveNR = (dataNR === '' ? 'REQ' : String(dataNR).trim().toUpperCase());
                if (effectiveNR !== 'REQ' && effectiveNR !== 'NR' && effectiveNR !== 'LATER') {
                    effectiveNR = 'REQ';
                }
                const rowNrpSel = nrpSel;
                const nrpMatch = !rowNrpSel || rowNrpSel.has(effectiveNR);
                const matchesNR = !rowNrpSel || rowNrpSel.has('NR') || !hideNRYes || data.nr !== 'NR';
                const matchesLATER = !rowNrpSel || rowNrpSel.has('LATER') || !hideLATERYes || data.nr !== 'LATER';
                
                // Stage filter - check stage field or transit field
                // If filter is "__blank__", match empty/null/undefined stage
                const stageMatch = matchesStageFilterValue(data, currentStageFilter);

                // 🎯 Force filter to one parent group if play mode is active
                if (currentParentFilter) {
                    if (isParent) {
                        if (currentColorFilter === 'yellow') {
                            return false;
                        }
                        if (currentTwoOrdColorFilter && !matchesTwoOrdColorFilter(data)) {
                            return false;
                        }
                        if (!qtySignMatch) {
                            return false;
                        }
                        return data.Parent === currentParentFilter;
                    } else {
                        return data.Parent === currentParentFilter && matchesFilter && twoOrdColorMatch && qtySignMatch && matchesNR && matchesLATER && nrpMatch && stageMatch &&
                            invHeaderMatchesRow(data);
                    }
                }

                if (isChild) {
                    const showChild = matchesFilter && twoOrdColorMatch && qtySignMatch && matchesNR && matchesLATER && nrpMatch && stageMatch &&
                        invHeaderMatchesRow(data);
                    if (currentRowTypeFilter === 'parent') return false;
                    if (currentRowTypeFilter === 'sku') return showChild;
                    return showChild;
                }

                if (isParent) {
                    if (currentColorFilter === 'yellow') {
                        return false;
                    }
                    if (currentTwoOrdColorFilter && !matchesTwoOrdColorFilter(data)) {
                        return false;
                    }
                    if (currentRowTypeFilter === 'sku') return false;
                    // When NRP multiselect is partial (not all types), only show parents that have
                    // at least one visible child matching filters — otherwise every parent stayed visible
                    // with default REQ styling while children were filtered (broken UX).
                    const useChildDerivedParentVisibility =
                        (nrpSel !== null) ||
                        !!invHeaderFilterMode(currentInvFilter) ||
                        !!currentStageFilter ||
                        !!currentTwoOrdColorFilter ||
                        hasActiveTopQtySignFilter();
                    if (useChildDerivedParentVisibility) {
                        return visibleParentKeys.has(data.Parent);
                    }
                    const showParent =
                        currentRowTypeFilter === 'parent' ? true :
                        currentRowTypeFilter === 'all' ? true :
                        visibleParentKeys.has(data.Parent);
                    return showParent;
                }

                return false;
            });
            } catch (err) {
                console.warn('[Forecast] skipped filter apply (table not ready):', err);
                return;
            }
            updateTopRowCounter();

            // update visible count (debounced to avoid heavy repeated background recalculations)
            if (filterPostCalcTimer) {
                clearTimeout(filterPostCalcTimer);
            }
            filterPostCalcTimer = setTimeout(() => {
                filterPostCalcTimer = null;
                updateParentTotalsBasedOnVisibleRows();
                if (typeof window.updatePmtChart === 'function') window.updatePmtChart();
                
                // Calculate total MSL_C for visible rows
                const visibleRows = table.getRows(true);
                let totalMslC = 0;
                
                visibleRows.forEach(row => {
                    const data = row.getData();
                    if (!data.is_parent) {
                        totalMslC += parseFloat(data.MSL_C) || 0;
                    }
                });
                
                // Update total MSL_C display
                const totalMslCElement = document.getElementById('total_msl_c_value');
                if (totalMslCElement) {
                    const wholeNumber = Math.round(totalMslC);
                    totalMslCElement.textContent = wholeNumber.toLocaleString('en-US');
                }

                // Keep INV Val / LP Val badges aligned with currently visible (active) rows.
                const visibleChildRows = visibleRows.filter(function(row) {
                    const d = row.getData();
                    return d && !d.is_parent;
                });
                const visibleInvValue = visibleChildRows.reduce(function(sum, row) {
                    const d = row.getData();
                    const invValue = parseFloat(d.inv_value);
                    if (Number.isFinite(invValue)) return sum + invValue;
                    const inv = parseFloat(d.INV) || 0;
                    const sp = parseFloat(d.shopifyb2c_price) || 0;
                    return sum + (inv * sp);
                }, 0);
                const totalInvValueElement = document.getElementById('total_inv_value_display');
                if (totalInvValueElement) {
                    totalInvValueElement.textContent = Math.round(visibleInvValue).toLocaleString('en-US');
                }
                const visibleLpValue = visibleChildRows.reduce(function(sum, row) {
                    const d = row.getData();
                    const lpValue = parseFloat(d.lp_value);
                    if (Number.isFinite(lpValue)) return sum + lpValue;
                    const inv = parseFloat(d.INV) || 0;
                    const lp = parseFloat(d.LP) || 0;
                    return sum + (inv * lp);
                }, 0);
                const totalLpValueElement = document.getElementById('total_lp_value_display');
                if (totalLpValueElement) {
                    totalLpValueElement.textContent = Math.round(visibleLpValue).toLocaleString('en-US');
                }
                const visibleOrderValue = visibleChildRows.reduce(function(sum, row) {
                    const d = row.getData();
                    const orderQty = parseFloat(d.two_order_qty);
                    if (!Number.isFinite(orderQty) || orderQty <= 0) return sum;
                    const cp = parseFloat(d.CP ?? (d.raw_data ? d.raw_data.CP : 0)) || 0;
                    return sum + (orderQty * cp);
                }, 0);
                const totalOrderValueElement = document.getElementById('total_order_value_display');
                if (totalOrderValueElement) {
                    totalOrderValueElement.textContent = Math.round(visibleOrderValue).toLocaleString('en-US');
                }

                let totalMslSpAmz = 0;
                visibleRows.forEach(row => {
                    const data = row.getData();
                    if (!data.is_parent) {
                        totalMslSpAmz += parseFloat(data.MSL_SP_AMZ) || 0;
                    }
                });
                const totalMslSpAmzEl = document.getElementById('total_msl_sp_amz_value');
                if (totalMslSpAmzEl) {
                    totalMslSpAmzEl.textContent = Math.round(totalMslSpAmz).toLocaleString('en-US');
                }

                // Calculate total Restock MSL for visible rows
                let totalRestockMsl = 0;
                visibleRows.forEach(row => {
                    const data = row.getData();
                    if (!data.is_parent && (parseFloat(data.INV) || 0) === 0) {
                        const lp = parseFloat(data.LP) || 0;
                        totalRestockMsl += (lp / 4);
                    }
                });

                // Update total Restock MSL display
                const totalRestockMslElement = document.getElementById('total_restock_msl_value');
                if (totalRestockMslElement) {
                    const wholeNumber = Math.round(totalRestockMsl);
                    totalRestockMslElement.textContent = wholeNumber.toLocaleString('en-US');
                }

                // Calculate total Minimal MSL for visible rows
                const visibleRestockItems = visibleRows.filter(row => {
                    const data = row.getData();
                    return !data.is_parent && (parseFloat(data.INV) || 0) === 0;
                });
                const visibleRestockCount = visibleRestockItems.length;
                const visibleTotalShopifyPrice = visibleRestockItems.reduce((sum, row) => {
                    const data = row.getData();
                    return sum + (parseFloat(data.shopifyb2c_price) || 0);
                }, 0);
                const visibleAverageShopifyPrice = visibleRestockCount > 0 ? visibleTotalShopifyPrice / visibleRestockCount : 0;
                const totalMinimalMsl = visibleRestockItems.reduce((sum, row) => {
                    const data = row.getData();
                    return sum + (parseFloat(data.MSL_SP) || 0);
                }, 0);

                // Update total Minimal MSL display
                const totalMinimalMslElement = document.getElementById('total_minimal_msl_value');
                if (totalMinimalMslElement) {
                    const wholeNumber = Math.round(totalMinimalMsl);
                    totalMinimalMslElement.textContent = wholeNumber.toLocaleString('en-US');
                }

                // Calculate sum restock shopify price for visible rows
                const visibleSumRestockShopifyPrice = visibleRestockItems.reduce((sum, row) => {
                    const data = row.getData();
                    return sum + (parseFloat(data.shopifyb2c_price) || 0);
                }, 0);

                // Update sum restock shopify price display
                const sumRestockShopifyPriceElement = document.getElementById('sum_restock_shopify_price_value');
                if (sumRestockShopifyPriceElement) {
                    const wholeNumber = Math.round(visibleSumRestockShopifyPrice);
                    sumRestockShopifyPriceElement.textContent = wholeNumber.toLocaleString('en-US');
                }

                // Calculate total restock MSL LP for visible rows
                const visibleTotalLp = visibleRestockItems.reduce((sum, row) => {
                    const data = row.getData();
                    return sum + (parseFloat(data.LP) || 0);
                }, 0);
                const visibleAverageLp = visibleRestockCount > 0 ? visibleTotalLp / visibleRestockCount : 0;
                const totalRestockMslLp = visibleRestockCount * (visibleAverageLp / 4);

                // Calculate total MIP Value - from ALL rows (not filtered), only for rows with stage === 'mip' (like mfrg-in-progress page)
                // Get all rows regardless of filters to calculate MIP Value
                // Filter criteria: stage === 'mip', ready_to_ship !== 'Yes', nr !== 'NR' (same as mfrg-in-progress page)
                // Calculate directly as qty * rate (like mfrg-in-progress page calculates from DOM)
                const allRowsForMip = table.getRows();
                let totalMipValue = 0;
                
                allRowsForMip.forEach(row => {
                    const data = row.getData();
                    if (!data.is_parent) {
                        // Check stage field - both direct and from raw_data
                        const stage = data.stage || (data.raw_data && data.raw_data.stage) || '';
                        const stageValue = String(stage || '').trim().toLowerCase();
                        
                        // Check ready_to_ship from mfrg_progress table (exclude if 'Yes', like mfrg-in-progress page)
                        const mfrgReadyToShip = data.mfrg_ready_to_ship || (data.raw_data && data.raw_data.mfrg_ready_to_ship) || 'No';
                        const readyToShipValue = String(mfrgReadyToShip || '').trim();
                        
                        // Check nr field (exclude if 'NR', like mfrg-in-progress page)
                        const nr = data.nr || (data.raw_data && data.raw_data.nr) || '';
                        const nrValue = String(nr || '').trim().toUpperCase();
                        
                        // Only count if stage is 'mip', ready_to_ship !== 'Yes', and nr !== 'NR' (matching mfrg-in-progress page logic)
                        // IMPORTANT: mfrg-in-progress page calculates from items that are already filtered in template (using continue directive in Blade)
                        // So we need to match the exact same filtering logic here
                        if (stageValue === 'mip' && readyToShipValue !== 'Yes' && nrValue !== 'NR') {
                            // Calculate directly as qty * rate (like mfrg-in-progress page calculates from DOM)
                            // In mfrg-in-progress: $item->qty * $item->rate (directly from mfrg_progress table)
                            // In forecastAnalysis: order_given (qty) and mip_rate (rate) from mfrg_progress table
                            // But ONLY if ready_to_ship === 'No' (controller already sets order_given=0 if ready_to_ship='Yes')
                            
                            // Check if item has mfrg_progress data (order_given > 0 means ready_to_ship was 'No')
                            const qty = parseFloat(data.order_given || data["order_given"] || (data.raw_data && data.raw_data["order_given"]) || 0) || 0;
                            const rate = parseFloat(data.mip_rate || data["mip_rate"] || (data.raw_data && data.raw_data["mip_rate"]) || 0) || 0;
                            
                            // Only calculate if both qty and rate are available (matching mfrg-in-progress template logic)
                            // mfrg-in-progress template: is_numeric($item->qty) && is_numeric($item->rate)
                            if (qty > 0 && rate > 0) {
                                totalMipValue += (qty * rate);
                            }
                        }
                    }
                });

                // Update total MIP Value display
                const totalMipValueElement = document.getElementById('total_mip_value_display');
                if (totalMipValueElement) {
                    const roundedTotal = Math.round(totalMipValue);
                    totalMipValueElement.textContent = roundedTotal.toLocaleString('en-US');
                }

                // Calculate total R2S Value - from ALL rows (not filtered), only for rows with stage === 'r2s' (like ready-to-ship page)
                // Get all rows regardless of filters to calculate R2S Value
                // Filter criteria: stage === 'r2s', transit_inv_status === 0, nr !== 'NR' (same as ready-to-ship page)
                // Calculate directly as qty * rate (like ready-to-ship page calculates from DOM)
                const allRowsForR2s = table.getRows();
                let totalR2sValue = 0;
                
                allRowsForR2s.forEach(row => {
                    const data = row.getData();
                    if (!data.is_parent) {
                        // Check stage field - both direct and from raw_data
                        const stage = data.stage || (data.raw_data && data.raw_data.stage) || '';
                        const stageValue = String(stage || '').trim().toLowerCase();
                        
                        // Check nr field (exclude if 'NR', like ready-to-ship page)
                        const nr = data.nr || (data.raw_data && data.raw_data.nr) || '';
                        const nrValue = String(nr || '').trim().toUpperCase();
                        
                        // Only count if stage is 'r2s' and nr !== 'NR' (matching ready-to-ship page logic)
                        // IMPORTANT: ready-to-ship page calculates from items that are already filtered in template (using continue directive in Blade)
                        // Controller already filters: transit_inv_status = 0 and stage === 'r2s'
                        if (stageValue === 'r2s' && nrValue !== 'NR') {
                            // Calculate directly as qty * rate (same as ready-to-ship page)
                            // In ready-to-ship: $item->qty * $item->rate (directly from ready_to_ship table)
                            // In forecastAnalysis: readyToShipQty (qty) and r2s_rate (rate) from ready_to_ship table
                            
                            // Only calculate if both qty and rate are available (matching ready-to-ship template logic)
                            // ready-to-ship template: is_numeric($item->qty) && is_numeric($item->rate)
                            const qty = parseFloat(data.readyToShipQty || data["readyToShipQty"] || (data.raw_data && data.raw_data["readyToShipQty"]) || 0) || 0;
                            const rate = parseFloat(data.r2s_rate || data["r2s_rate"] || (data.raw_data && data.raw_data["r2s_rate"]) || 0) || 0;
                            
                            // Only calculate if both qty and rate are available (matching ready-to-ship template logic)
                            if (qty > 0 && rate > 0) {
                                totalR2sValue += (qty * rate);
                            }
                            // Note: We don't use fallback to R2S_Value here because ready-to-ship page doesn't show items without qty*rate
                        }
                    }
                });

                // Update total R2S Value display
                const totalR2sValueElement = document.getElementById('total_r2s_value_display');
                if (totalR2sValueElement) {
                    const roundedTotal = Math.round(totalR2sValue);
                    totalR2sValueElement.textContent = roundedTotal.toLocaleString('en-US');
                }

                // Trn Val badge: sum(transit QTY × CP) from controller; not recalculated on row filter
            }, 50);

            const visibleRows = table.getRows(true).map(r => r.getData());
            const yellowCount = visibleRows.filter(r =>
                !r.is_parent && apprReqYellowRowVisible(r)
            ).length;

            document.getElementById('yellow-count-box').textContent = `Appr Req: ${yellowCount}`;
            } finally {
                isApplyingCombinedFilters = false;
                if (pendingCombinedFiltersRun) {
                    pendingCombinedFiltersRun = false;
                    if (typeof window !== 'undefined' && typeof window.requestAnimationFrame === 'function') {
                        window.requestAnimationFrame(() => setCombinedFilters());
                    } else {
                        setTimeout(() => setCombinedFilters(), 0);
                    }
                }
            }
        }

        function updateParentTotalsBasedOnVisibleRows() {
            const visibleRows = table.getRows(true);
            const parentGroups = {};

            visibleRows.forEach(row => {
                const data = row.getData();
                const parent = data.Parent;
                if (!parent) return;

                if (!parentGroups[parent]) {
                    parentGroups[parent] = {
                        approved: 0,
                        inv: 0,
                        l30: 0,
                        orderGiven: 0,
                        transit: 0,
                        toOrder: 0,
                        parentRow: null
                    };
                }

                if (data.is_parent) {
                    // ✅ Skip update if already updated in ajaxResponse
                    parentGroups[parent].parentRow = row;
                } else {
                    const approvedValue = data.raw_data ? data.raw_data["MOQ"] : data["MOQ"];
                    const invValue = data.raw_data ? data.raw_data["INV"] : data["INV"];
                    const l30Value = data.raw_data ? data.raw_data["L30"] : data["L30"];
                    const orderGivenValue = data.raw_data ? data.raw_data["order_given"] : data["order_given"];
                    const transitValue = data.raw_data ? data.raw_data["transit"] : data["transit"];
                    const toOrderValue = data.raw_data ? data.raw_data["to_order"] : data["to_order"];

                    parentGroups[parent].approved += parseFloat(approvedValue) || 0;
                    parentGroups[parent].inv += parseFloat(invValue) || 0;
                    parentGroups[parent].l30 += parseFloat(l30Value) || 0;
                    parentGroups[parent].orderGiven += parseFloat(orderGivenValue) || 0;
                    parentGroups[parent].transit += parseFloat(transitValue) || 0;
                    parentGroups[parent].toOrder += parseFloat(toOrderValue) || 0;
                }
            });

            Object.values(parentGroups).forEach(group => {
                if (group.parentRow) {
                    const parentData = group.parentRow.getData();

                    // ✅ Only update if current values are null or 0
                    const alreadySet =
                        parentData["to_order"] !== undefined && parentData["to_order"] !== null;

                    if (!alreadySet) {
                        group.parentRow.update({
                            "MOQ": group.approved,
                            "INV": group.inv,
                            "L30": group.l30,
                            "order_given": group.orderGiven,
                            "transit": group.transit,
                            "to_order": group.toOrder
                        });
                    }
                }
            });
        }

        //modals
        function openMonthModal(monthData, sku) {
            const wrapper = document.getElementById("monthCardWrapper");
            if (!wrapper) return;

            wrapper.innerHTML = ""; // Clear previous content

            const monthOrder = [
                "JAN", "FEB", "MAR", "APR", "MAY", "JUN",
                "JUL", "AUG", "SEP", "OCT", "NOV", "DEC"
            ];

            // Get current date to determine year for each month
            const currentDate = new Date();
            const currentYear = currentDate.getFullYear();
            const currentMonth = currentDate.getMonth(); // 0-11 (Jan = 0, Dec = 11)

            // Month index mapping (0 = Jan, 11 = Dec)
            const monthIndexMap = {
                "JAN": 0, "FEB": 1, "MAR": 2, "APR": 3,
                "MAY": 4, "JUN": 5, "JUL": 6, "AUG": 7,
                "SEP": 8, "OCT": 9, "NOV": 10, "DEC": 11
            };

            // Determine year for each month based on GenerateMovementAnalysis command logic
            // Command generates rolling data for last 12-14 months
            // 
            // Examples when current month is Jan 2026:
            //   - Data range: Nov 2025 to Jan 2026 (previousMonths=2)
            //   - JAN: 2026 (current year, monthIndex 0 <= currentMonth 0)
            //   - FEB to DEC: 2025 (previous year, monthIndex > currentMonth)
            //
            // Year assignment rule (matches rolling data):
            //   - If monthIndex > currentMonth: previous year (months after current in calendar)
            //   - If monthIndex <= currentMonth: current year (current month and months before it in same calendar)
            const getYearForMonth = (monthIndex) => {
                // If month index is greater than current month, it's from previous year
                // Example: If current month is Jan (0) and month is Feb (1), Feb is from previous year (2025)
                if (monthIndex > currentMonth) {
                    return currentYear - 1;
                }
                // If month index is less than or equal to current month, it's current year
                // Example: If current month is Jan (0) and month is Jan (0), it's current year (2026)
                return currentYear;
            };

            // Sort and display in month order
            monthOrder.forEach(month => {
                const value = monthData[month] ?? 0;
                const monthIndex = monthIndexMap[month];
                const year = getYearForMonth(monthIndex);

                const card = document.createElement("div");
                card.className = "month-card";

                const title = document.createElement("div");
                title.className = "month-title";
                title.innerText = `${month} ${year}`;

                const count = document.createElement("div");
                count.className = "month-value";
                count.innerText = value;

                card.appendChild(title);
                card.appendChild(count);
                wrapper.appendChild(card);
            });

            document.getElementById("month-view-sku").innerText = `( ${sku} )`;

            const modal = new bootstrap.Modal(document.getElementById("monthModal"));
            modal.show();
        }

        function openMetricModal(row) {
            const metricData = {
                SH: row['SH'],
                CP: row['CP'],
                MOQ: row['MOQ'],
                LP: row['LP'],
                "Shopify Price": row['shopifyb2c_price'],
                "MSL_C": row['MSL_C'],
                Freight: row['Freight'],
                "GW (KG)": row['GW (KG)'],
                "GW (LB)": row['GW (LB)'],
                "CBM MSL": row['CBM MSL'],
            };

            const wrapper = document.getElementById("metricCardWrapper");
            wrapper.innerHTML = "";

            for (const [key, value] of Object.entries(metricData)) {
                const displayValue = (!isNaN(value) && value !== null) ? parseFloat(value).toFixed(2) : '-';

                const card = document.createElement("div");
                card.className = "month-card";
                card.innerHTML = `
                <div class="month-title">${key}</div>
                <div class="month-value">${displayValue}</div>
            `;
                wrapper.appendChild(card);
            }

            const modal = new bootstrap.Modal(document.getElementById('metricModal'));
            modal.show();
        }

        const COLUMN_VIS_KEY = "tabulator_column_visibility";

        function saveColumnVisibilityToLocalStorage() {
            const vis = {};
            table.getColumns().forEach(function(col) {
                const f = col.getField();
                if (f) vis[f] = col.isVisible();
            });
            localStorage.setItem(COLUMN_VIS_KEY, JSON.stringify(vis));
        }

        function initColumnModal() {
            const trigger = document.getElementById("hide-column-dropdown");
            const modalEl = document.getElementById("columnCustomizeModal");
            if (!trigger || !modalEl) return;

            // Restore saved visibility only after table is fully built
            table.on('tableBuilt', function() {
                const savedVis = JSON.parse(localStorage.getItem(COLUMN_VIS_KEY) || '{}');
                if (Object.keys(savedVis).length) {
                    table.getColumns().forEach(function(col) {
                        const f = col.getField();
                        if (!f) return;
                        if (savedVis[f] === false) col.hide();
                    });
                }
            });

            // Map of field → plain text label
            const FIELD_LABELS = {};

            function getColumnLabel(col) {
                const f = col.getField();
                if (FIELD_LABELS[f]) return FIELD_LABELS[f];
                const def = col.getDefinition();
                let label = '';
                if (typeof def.title === 'string' && def.title.trim()) {
                    label = def.title.trim();
                }
                if (!label) {
                    // Read from rendered header text (safe plain-text only)
                    try {
                        const el = col.getElement();
                        const titleEl = el && el.querySelector('.tabulator-col-title');
                        label = titleEl ? (titleEl.textContent || '').trim() : '';
                } catch (_) {}
            }
                if (!label || label === 'undefined') label = f || '—';
                // Strip any HTML tags just in case
                label = label.replace(/<[^>]*>/g, '').trim() || f || '—';
                FIELD_LABELS[f] = label;
                return label;
            }

            function buildCheckboxList() {
                const list = document.getElementById("columnCheckboxList");
                if (!list) return;
                list.innerHTML = table.getColumns().map(function(col) {
                    const f = col.getField();
                    if (!f) return '';
                    const label = getColumnLabel(col);
                    const checked = col.isVisible() ? 'checked' : '';
                    const safeF = String(f).replace(/"/g, '&quot;');
                    const safeLabel = String(label).replace(/</g, '&lt;').replace(/>/g, '&gt;');
                    return `<div class="col">
                        <div class="form-check">
                            <input class="form-check-input col-vis-checkbox" type="checkbox" id="colvis_${safeF}" data-field="${safeF}" ${checked}>
                            <label class="form-check-label small" for="colvis_${safeF}" style="cursor:pointer;">${safeLabel}</label>
                        </div>
                    </div>`;
                }).join('');
            }

                trigger.addEventListener("click", function() {
                buildCheckboxList();
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            });

            // Immediate apply on checkbox toggle
            $(document).off('change.colvis', '.col-vis-checkbox').on('change.colvis', '.col-vis-checkbox', function() {
                const col = table.getColumn(this.dataset.field);
                if (!col) return;
                if (this.checked) col.show(); else col.hide();
                saveColumnVisibilityToLocalStorage();
            });

            // Show All
            document.getElementById("columnShowAllBtn")?.addEventListener("click", function() {
                table.getColumns().forEach(function(col) { if (col.getField()) col.show(); });
                saveColumnVisibilityToLocalStorage();
                buildCheckboxList();
            });

            // Hide All (keep SKU visible so table stays usable)
            document.getElementById("columnHideAllBtn")?.addEventListener("click", function() {
                table.getColumns().forEach(function(col) {
                    const f = col.getField();
                    if (!f || f === 'SKU') return;
                    col.hide();
                });
                saveColumnVisibilityToLocalStorage();
                buildCheckboxList();
            });
        }

        function saveColumnVisibilityToLocalStorage() {
            const visibility = {};
            table.getColumns().forEach(col => {
                const field = col.getField();
                if (field) {
                    visibility[field] = col.isVisible();
                }
            });
            localStorage.setItem(COLUMN_VIS_KEY, JSON.stringify(visibility));
        }

        document.addEventListener("DOMContentLoaded", () => {
            initColumnModal();

            // Handle editable field (contenteditable cells, e.g. legacy qty fields — MOQ uses Tabulator number editor)
            $(document).off('blur', '.editable-qty').on('blur', '.editable-qty', function() {
                const $cell = $(this);
                const newValueRaw = $cell.text().trim();
                const originalValue = ($cell.data('original') ?? '').toString().trim();
                const field = $cell.data('field');
                const sku = $cell.data('sku');
                const parent = $cell.data('parent');

                // Convert raw value to number safely
                const newValue = ['MOQ', 'S-MSL', 'order_given'].includes(field) ?
                    Number(newValueRaw) :
                    newValueRaw;

                const original = ['MOQ', 'S-MSL', 'order_given'].includes(field) ?
                    Number(originalValue) :
                    originalValue;

                // Avoid unnecessary updates
                if (newValue === original) return;

                // Numeric validation
                if (['MOQ', 'S-MSL', 'order_given'].includes(field) && isNaN(newValue)) {
                    alert('Please enter a valid number.');
                    $cell.text(originalValue); // revert
                    return;
                }

                // Optional validation for date fields (YYYY-MM-DD)
                if (['Date of Appr'].includes(field)) {
                    const isValidDate = /^\d{4}-\d{2}-\d{2}$/.test(newValue);
                    if (!isValidDate) {
                        alert('Please enter a valid date in YYYY-MM-DD format.');
                        $cell.text(originalValue);
                        return;
                    }
                }

                // Add visual feedback
                $cell.css('opacity', '0.5');

                // Debounce the AJAX call
                debounce(`qty-${sku}-${parent}-${field}`, function() {
                    updateForecastField({
                        sku,
                        parent,
                        column: field,
                        value: newValue
                    }, function() {
                        $cell.data('original', newValue);
                        $cell.css('opacity', '1');

                        // MOQ is saved via Tabulator cellEdited (number editor), not this blur handler
                        // No need to call setCombinedFilters for inline qty changes
                    }, function() {
                        $cell.text(originalValue);
                        $cell.css('opacity', '1');
                    });
                }, 200);

            });

            // Handle link edit modal save
            $('#saveLinkBtn').on('click', function() {
                const newValue = $('#linkEditInput').val().trim();
                const field = editingField;
                const sku = editingRow['SKU'];
                const parent = editingRow['Parent'];

                editingRow[field] = newValue;

                const iconMap = {
                    'Clink': `<i class="fas fa-link text-primary me-1"></i>`,
                    'Olink': `<i class="fas fa-external-link-alt text-success me-1"></i>`,
                    'rfq_form_link': `<i class="fas fa-file-contract text-success me-1"></i>`,
                    'rfq_report': `<i class="fas fa-file-alt text-info me-1"></i>`
                };


                const iconHtml = newValue ?
                    `<a href="${newValue}" target="_blank" title="${field}">${iconMap[field] || ''}</a>` :
                    '';

                const editIcon = `<a href="#" class="edit-${field.toLowerCase()}" title="Edit ${field}">
                                    <i class="fas fa-edit text-warning"></i>
                                </a>`;

                $(editingLinkCell).html(`
                    <div class="d-flex align-items-center justify-content-center gap-1 ${field.toLowerCase()}-cell">
                        ${iconHtml}${editIcon}
                    </div>
                `);

                $('#linkEditModal').modal('hide');

                updateForecastField({
                        sku,
                        parent,
                        column: field,
                        value: newValue
                    },
                    function() {
                        console.log(`${field} saved successfully.`);
                    },
                    function() {
                        alert(`Failed to save ${field}.`);
                    }
                );
            });

            // Handle editable select field
            $(document).off('change', '.editable-select, .editable-date').on('change',
                '.editable-select, .editable-date',
                function() {
                    const $el = $(this);
                    const isSelect = $el.hasClass('editable-select');
                    const isDate = $el.hasClass('editable-date');

                    let newValue = $el.val().trim();
                    const sku = $el.data('sku');
                    const parent = $el.data('parent');
                    const field = isSelect ? $el.data('type') : $el.data('field');
                    const originalValue = isDate ? $el.data('original') : null;

                    // Normalize stage value to lowercase
                    if (field === "Stage" && newValue) {
                        newValue = newValue.toLowerCase();
                    }

                    if (field === "Stage" && newValue === "appr_req") {
                        const row = table.getRow(sku);
                        const rowData = row ? row.getData() : null;
                        const approvedQty = rowData ? rowData["MOQ"] : null;
                        const previousStage = rowData ? (rowData["stage"] || '') : '';
                        
                        if (!approvedQty || approvedQty === "0" || parseInt(approvedQty) === 0) {
                            alert("MOQ cannot be empty or zero.");
                            $el.val(previousStage); // Restore previous stage value
                            return;
                        }
                    }

                    // For date input: skip if no change
                    if (isDate && newValue === originalValue) return;

                    // Add visual feedback (NRP / Stage use invisible overlay select; skip dimming)
                    if (field !== 'NR' && field !== 'Stage') $el.css('opacity', '0.6');
                    
                    // Debounce for rapid changes
                    debounce(`select-${sku}-${field}`, function() {
                        updateForecastField({
                                sku,
                                parent,
                                column: field,
                                value: newValue
                            },
                            function() {
                                if (field !== 'NR' && field !== 'Stage') $el.css('opacity', '1');
                                
                                if (isDate) {
                                    $el.data('original', newValue);
                                }
                                
                                const row = table.getRows().find(r =>
                                    r.getData().SKU === sku && r.getData().Parent === parent
                                );
                                
                                if (!row) return;
                                
                                if (field === 'NR') {
                                    row.update({ nr: newValue }, true);
                                    const nrCell = row.getCells().find(function(c) { return c.getField() === 'nr'; });
                                    if (nrCell) nrCell.reformat();
                                    setCombinedFilters();
                                    return;
                                } else if (field === 'Stage') {
                                    const rowData = row.getData();
                                    
                                    // Stage to table mapping
                                    const stageTableMap = {
                                        'mip': { table: 'mfrg-progress', field: 'order_given' },
                                        'r2s': { table: 'ready-to-ship', field: 'readyToShipQty' },
                                        'transit': { table: 'transit', field: 'transit' }
                                    };

                                    const stageConfig = stageTableMap[newValue];
                                    
                                    // Prepare update - clear other stage fields
                                    const stLow = String(newValue || '').trim().toLowerCase();
                                    const updateData = { stage: newValue };
                                    updateData.two_order_qty = stLow === 'to_order_analysis' ? (parseFloat(rowData.MOQ) || 0) : 0;
                                    updateData.appr_req_qty = stLow === 'appr_req' ? (parseFloat(rowData.MOQ) || 0) : 0;
                                    if (newValue !== 'mip') updateData['order_given'] = 0;
                                    if (newValue !== 'r2s') updateData['readyToShipQty'] = 0;
                                    if (newValue !== 'transit') updateData['transit'] = 0;
                                    
                                    if (stageConfig && stageConfig.table) {
                                        // Fetch quantity for the stage
                                        fetch(`/forecast-analysis/get-sku-quantity?sku=${encodeURIComponent(sku)}&table=${stageConfig.table}`, {
                                            method: 'GET',
                                            headers: {
                                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                                                'Accept': 'application/json'
                                            }
                                        })
                                        .then(res => res.json())
                                        .then(data => {
                                            updateData[stageConfig.field] = (data.success && data.exists) ? (parseFloat(data.quantity) || 0) : 0;
                                            row.update(updateData, true);
                                            syncParentStageQtyColumns(rowData.Parent || rowData.parentKey);
                                            const cells = row.getCells();
                                            cells.forEach(cell => {
                                                const cellField = cell.getField();
                                                if (['stage', 'order_given', 'readyToShipQty', 'transit', 'to_order', 'two_order_qty', 'appr_req_qty'].includes(cellField)) {
                                                    cell.reformat();
                                                }
                                            });
                                            setCombinedFilters(); // Stage affects filtering
                                        })
                                        .catch(error => {
                                            console.error('Error:', error);
                                            row.update(updateData, true);
                                            syncParentStageQtyColumns(rowData.Parent || rowData.parentKey);
                                            setCombinedFilters();
                                        });
                                    } else {
                                        row.update(updateData, true);
                                        syncParentStageQtyColumns(rowData.Parent || rowData.parentKey);
                                        row.getCells().forEach(cell => {
                                            const cellField = cell.getField();
                                            if (['stage', 'order_given', 'readyToShipQty', 'transit', 'to_order', 'two_order_qty', 'appr_req_qty'].includes(cellField)) {
                                                cell.reformat();
                                            }
                                        });
                                        setCombinedFilters(); // Stage affects filtering
                                    }
                                } else if (field === 'Hide') {
                                    row.update({ hide: newValue }, true);
                                    // No need for setCombinedFilters for Hide field
                                }
                            },
                            function() {
                                $el.css('opacity', '1');
                                if (isDate) {
                                    $el.val(originalValue);
                                }
                                alert(`Failed to save ${field}.`);
                            }
                        );
                    }, 150);
                });

            $(document).off('click', '.appr-req-move-dot').on('click', '.appr-req-move-dot', function(e) {
                e.preventDefault();
                e.stopPropagation();

                const $btn = $(this);
                if ($btn.data('busy')) return;

                const sku = String($btn.data('sku') || '').trim();
                const parent = String($btn.data('parent') || '').trim();
                if (!sku) return;

                const row = table.getRows().find(r => {
                    const d = r.getData();
                    return String(d.SKU || '').trim() === sku && String(d.Parent || '').trim() === parent;
                });

                if (!row) {
                    alert('Row not found.');
                    return;
                }

                const rowData = row.getData() || {};
                const raw = rowData.raw_data || {};
                const moqVal = parseFloat(rowData.MOQ ?? raw.MOQ ?? raw['Approved QTY'] ?? 0);
                if (!Number.isFinite(moqVal) || moqVal <= 0) {
                    alert('MOQ is empty or zero.');
                    return;
                }

                $btn.data('busy', true).css('opacity', '0.5').css('cursor', 'wait');

                updateForecastField({
                        sku: sku,
                        parent: parent,
                        column: 'Stage',
                        value: 'to_order_analysis'
                    },
                    function() {
                        const updatedRaw = Object.assign({}, raw, {
                            stage: 'to_order_analysis',
                            two_order_qty: moqVal,
                            appr_req_qty: 0,
                            order_given: 0,
                            readyToShipQty: 0,
                            transit: 0
                        });

                        row.update({
                            stage: 'to_order_analysis',
                            two_order_qty: moqVal,
                            appr_req_qty: 0,
                            order_given: 0,
                            readyToShipQty: 0,
                            transit: 0,
                            raw_data: updatedRaw
                        }, true);

                        syncParentStageQtyColumns(rowData.Parent || rowData.parentKey);
                        row.getCells().forEach(function(cell) {
                            const f = cell.getField();
                            if (['stage', 'order_given', 'readyToShipQty', 'transit', 'to_order', 'two_order_qty', 'appr_req_qty'].includes(f)) {
                                cell.reformat();
                            }
                        });
                        setCombinedFilters();
                        $btn.data('busy', false).css('opacity', '1').css('cursor', 'pointer');
                    },
                    function() {
                        $btn.data('busy', false).css('opacity', '1').css('cursor', 'pointer');
                        alert('Failed to move MOQ to Order.');
                    }
                );
            });

            $(document).off('click', '.order-to-mip-move-dot').on('click', '.order-to-mip-move-dot', function(e) {
                e.preventDefault();
                e.stopPropagation();

                const $btn = $(this);
                if ($btn.data('busy')) return;

                const sku = String($btn.data('sku') || '').trim();
                const parent = String($btn.data('parent') || '').trim();
                if (!sku) return;

                const row = table.getRows().find(r => {
                    const d = r.getData();
                    return String(d.SKU || '').trim() === sku && String(d.Parent || '').trim() === parent;
                });

                if (!row) { alert('Row not found.'); return; }

                const rowData = row.getData() || {};
                const orderQty = parseFloat(rowData.two_order_qty ?? 0);
                if (!Number.isFinite(orderQty) || orderQty <= 0) {
                    alert('Order quantity is empty or zero.');
                    return;
                }

                $btn.data('busy', true).css('opacity', '0.5').css('cursor', 'wait');

                updateForecastField(
                    { sku: sku, parent: parent, column: 'Stage', value: 'mip' },
                    function() {
                        updateForecastField(
                            { sku: sku, parent: parent, column: 'order_given', value: orderQty },
                            function() {
                                row.update({
                                    stage: 'mip',
                                    order_given: orderQty,
                                    two_order_qty: 0,
                                    appr_req_qty: 0,
                                    readyToShipQty: 0,
                                    transit: 0
                                }, true);
                                syncParentStageQtyColumns(rowData.Parent || rowData.parentKey);
                                row.getCells().forEach(function(cell) {
                                    const f = cell.getField();
                                    if (['stage', 'order_given', 'readyToShipQty', 'transit', 'to_order', 'two_order_qty', 'appr_req_qty'].includes(f)) {
                                        cell.reformat();
                                    }
                                });
                                setCombinedFilters();
                                $btn.data('busy', false).css('opacity', '1').css('cursor', 'pointer');
                            },
                            function() {
                                $btn.data('busy', false).css('opacity', '1').css('cursor', 'pointer');
                                alert('Failed to move Order to MIP.');
                            }
                        );
                    },
                    function() {
                        $btn.data('busy', false).css('opacity', '1').css('cursor', 'pointer');
                        alert('Failed to move Order to MIP.');
                    }
                );
            });

            $(document).off('click', '.mip-to-r2s-move-dot').on('click', '.mip-to-r2s-move-dot', function(e) {
                e.preventDefault();
                e.stopPropagation();

                const $btn = $(this);
                if ($btn.data('busy')) return;

                const sku = String($btn.data('sku') || '').trim();
                const parent = String($btn.data('parent') || '').trim();
                if (!sku) return;

                const row = table.getRows().find(r => {
                    const d = r.getData();
                    return String(d.SKU || '').trim() === sku && String(d.Parent || '').trim() === parent;
                });

                if (!row) { alert('Row not found.'); return; }

                const rowData = row.getData() || {};
                const mipQty = parseFloat(rowData.order_given ?? 0);
                if (!Number.isFinite(mipQty) || mipQty <= 0) {
                    alert('MIP quantity is empty or zero.');
                    return;
                }

                $btn.data('busy', true).css('opacity', '0.5').css('cursor', 'wait');

                updateForecastField(
                    { sku: sku, parent: parent, column: 'Stage', value: 'r2s' },
                    function() {
                        updateForecastField(
                            { sku: sku, parent: parent, column: 'R2S', value: mipQty },
                            function() {
                                row.update({
                                    stage: 'r2s',
                                    readyToShipQty: mipQty,
                                    order_given: 0,
                                    two_order_qty: 0,
                                    appr_req_qty: 0,
                                    transit: 0
                                }, true);
                                syncParentStageQtyColumns(rowData.Parent || rowData.parentKey);
                                row.getCells().forEach(function(cell) {
                                    const f = cell.getField();
                                    if (['stage', 'order_given', 'readyToShipQty', 'transit', 'to_order', 'two_order_qty', 'appr_req_qty'].includes(f)) {
                                        cell.reformat();
                                    }
                                });
                                setCombinedFilters();
                                $btn.data('busy', false).css('opacity', '1').css('cursor', 'pointer');
                            },
                            function() {
                                $btn.data('busy', false).css('opacity', '1').css('cursor', 'pointer');
                                alert('Failed to move MIP to R2S.');
                            }
                        );
                    },
                    function() {
                        $btn.data('busy', false).css('opacity', '1').css('cursor', 'pointer');
                        alert('Failed to move MIP to R2S.');
                    }
                );
            });

            $(document).off('click', '.r2s-to-trn-move-dot').on('click', '.r2s-to-trn-move-dot', function(e) {
                e.preventDefault();
                e.stopPropagation();

                const $btn = $(this);
                if ($btn.data('busy')) return;

                const sku = String($btn.data('sku') || '').trim();
                const parent = String($btn.data('parent') || '').trim();
                if (!sku) return;

                const row = table.getRows().find(r => {
                    const d = r.getData();
                    return String(d.SKU || '').trim() === sku && String(d.Parent || '').trim() === parent;
                });

                if (!row) { alert('Row not found.'); return; }

                const rowData = row.getData() || {};
                const r2sQty = parseFloat(rowData.readyToShipQty ?? 0);
                if (!Number.isFinite(r2sQty) || r2sQty <= 0) {
                    alert('R2S quantity is empty or zero.');
                    return;
                }

                $btn.data('busy', true).css('opacity', '0.5').css('cursor', 'wait');

                updateForecastField(
                    { sku: sku, parent: parent, column: 'TRANSIT_MOVE', value: r2sQty },
                    function() {
                        row.update({
                            stage: 'transit',
                            transit: r2sQty,
                            readyToShipQty: 0,
                            order_given: 0,
                            two_order_qty: 0,
                            appr_req_qty: 0
                        }, true);
                        syncParentStageQtyColumns(rowData.Parent || rowData.parentKey);
                        row.getCells().forEach(function(cell) {
                            const f = cell.getField();
                            if (['stage', 'order_given', 'readyToShipQty', 'transit', 'to_order', 'two_order_qty', 'appr_req_qty'].includes(f)) {
                                cell.reformat();
                            }
                        });
                        setCombinedFilters();
                        $btn.data('busy', false).css('opacity', '1').css('cursor', 'pointer');
                    },
                    function() {
                        $btn.data('busy', false).css('opacity', '1').css('cursor', 'pointer');
                        alert('Failed to move R2S to TRN.');
                    }
                );
            });

            $(document).off('click', '.forecast-mfrg-toggle-dot').on('click', '.forecast-mfrg-toggle-dot', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const dot = this;
                const sku = String(dot.getAttribute('data-sku') || '').trim();
                const column = String(dot.getAttribute('data-column') || '').trim();
                if (!sku || !column) return;
                if (dot.dataset.busy === '1') return;

                const current = String(dot.getAttribute('data-value') || 'No').trim().toUpperCase() === 'YES' ? 'Yes' : 'No';
                const next = current === 'Yes' ? 'No' : 'Yes';
                dot.dataset.busy = '1';
                dot.style.opacity = '0.55';

                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                fetch('/mfrg-progresses/inline-update-by-sku', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token
                    },
                    body: JSON.stringify({ sku: sku, column: column, value: next })
                })
                .then(r => r.json())
                .then(res => {
                    if (!res || !res.success) throw new Error(res && res.message ? res.message : 'Update failed');
                    dot.setAttribute('data-value', next);
                    dot.style.backgroundColor = (next === 'Yes') ? '#28a745' : '#dc3545';

                    const row = table.getRows().find(r => String(r.getData()?.SKU || '').trim() === sku);
                    if (row) {
                        const patch = {};
                        patch[column] = next;
                        row.update(patch, true);
                    }
                })
                .catch(err => {
                    alert(err && err.message ? err.message : 'Failed to update');
                })
                .finally(() => {
                    dot.dataset.busy = '0';
                    dot.style.opacity = '1';
                });
            });

            $(document).off('click', '.forecast-r2s-payment-toggle').on('click', '.forecast-r2s-payment-toggle', function(e) {
                const dot = this;
                if (dot.dataset.busy === '1') return;
                const sku = String(dot.getAttribute('data-sku') || '').trim();
                if (!sku) return;

                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const current = String(dot.getAttribute('data-value') || 'No').trim().toUpperCase() === 'YES' ? 'Yes' : 'No';
                const next = current === 'Yes' ? 'No' : 'Yes';

                dot.dataset.busy = '1';
                dot.style.opacity = '0.6';

                fetch('/ready-to-ship/inline-update-by-sku', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ sku: sku, column: 'payment', value: next })
                })
                .then(async (r) => {
                    const text = await r.text();
                    let data = null;
                    try { data = JSON.parse(text); } catch (err) {
                        throw new Error('Server returned non-JSON response');
                    }
                    if (!r.ok || !data || !data.success) {
                        throw new Error(data && data.message ? data.message : `Request failed (${r.status})`);
                    }
                    return data;
                })
                .then(() => {
                    dot.setAttribute('data-value', next);
                    dot.style.backgroundColor = next === 'Yes' ? '#28a745' : '#dc3545';
                    const row = table.getRows().find(r => String(r.getData()?.SKU || '').trim() === sku);
                    if (row) row.update({ r2s_payment: next }, true);
                })
                .catch(err => {
                    alert(err && err.message ? err.message : 'Failed to update PMT Confirm');
                })
                .finally(() => {
                    dot.dataset.busy = '0';
                    dot.style.opacity = '1';
                });
            });

            // Handle notes edit modal save
            $('#saveNotesBtn').on('click', function() {
                const newValue = $('#notesInput').val().trim();
                const field = editingField;
                const sku = editingRow['SKU'];
                const parent = editingRow['Parent'];

                editingRow[field] = newValue;

                // Update DOM cell content
                const display = newValue ? newValue.substring(0, 30) + (newValue.length > 30 ? '...' : '') :
                    '<em class="text-muted">No notes</em>';

                const updatedHTML = `
                    <div class="d-flex align-items-center justify-content-between notes-cell">
                        <span class="text-truncate" title="${newValue}">${display}</span>
                        <a href="#" class="edit-notes ms-2" title="Edit Notes">
                            <i class="fas fa-edit text-warning"></i>
                        </a>
                    </div>
                `;

                $(editingLinkCell).html(updatedHTML);
                $('#editNotesModal').modal('hide');

                updateForecastField({
                        sku,
                        parent,
                        column: 'Notes',
                        value: newValue
                    },
                    () => {
                        $('#editNotesModal').modal('hide');

                        const cell = $(`.edit-notes-btn[data-sku="${sku}"][data-parent="${parent}"]`)
                            .closest('td');

                        if (cell.length === 0) {
                            console.warn('Cell not found for SKU:', sku, 'and Parent:', parent);
                            return;
                        }

                        cell.empty();

                        const viewBtn = $('<i>')
                            .addClass('fas fa-eye text-info ms-2 view-note-btn')
                            .css('cursor', 'pointer')
                            .attr('title', 'View Note')
                            .attr('data-note', newValue);

                        const editBtn = $('<i>')
                            .addClass('fas fa-edit text-primary ms-2 edit-notes-btn')
                            .css('cursor', 'pointer')
                            .attr('title', 'Edit Note')
                            .attr('data-note', newValue)
                            .attr('data-sku', sku)
                            .attr('data-parent', parent);

                        cell.append(viewBtn, editBtn);
                    },
                    () => {
                        alert('Failed to save note.');
                    }
                );

            });

        });

        //play btn filter
        document.addEventListener('DOMContentLoaded', function() {
            document.documentElement.setAttribute("data-sidenav-size", "condensed");
            const table = Tabulator.findTable("#forecast-table")[0];

            (function initRowDataTypeSelect() {
                const sel = document.getElementById('row-data-type');
                if (!sel) return;
                const prev = (sel.value || 'sku').trim();
                const ROW_TYPE_OPTS = [
                    ['all', '🔁 Show All'],
                    ['sku', '🔹 SKU (Child)'],
                    ['parent', '🔸 Parent']
                ];
                sel.innerHTML = '';
                ROW_TYPE_OPTS.forEach(function(pair) {
                    const o = document.createElement('option');
                    o.value = pair[0];
                    o.textContent = pair[1];
                    sel.appendChild(o);
                });
                const allowed = new Set(ROW_TYPE_OPTS.map(function(p) { return p[0]; }));
                sel.value = allowed.has(prev) ? prev : 'sku';
                currentRowTypeFilter = sel.value;
                if (typeof setCombinedFilters === 'function') setCombinedFilters();
            })();
            restoreForecastFilterPrefs();

            const parentKeys = () => Object.keys(groupedSkuData);
            let currentIndex = 0;
            let isPlaying = false;

            function renderGroup(parentKey) {
                if (!groupedSkuData[parentKey]) return;
                currentParentFilter = parentKey;
                setCombinedFilters();
                // When play is active, move the parent row (SKU containing "Parent") to the bottom of the filtered rows
                setTimeout(function() {
                    if (!currentParentFilter) return;
                    const visibleRows = table.getRows(true);
                    const parentRow = visibleRows.find(function(r) {
                        const d = r.getData();
                        return d && (d.is_parent === true || (d.SKU && String(d.SKU).toUpperCase().indexOf('PARENT') !== -1));
                    });
                    if (parentRow && visibleRows.length > 1) {
                        const lastRow = visibleRows[visibleRows.length - 1];
                        if (parentRow !== lastRow) {
                            table.moveRow(parentRow, lastRow, false);
                        }
                    }
                }, 50);
            }

            document.getElementById('play-auto').addEventListener('click', () => {
                isPlaying = true;
                currentIndex = 0;
                renderGroup(parentKeys()[currentIndex]);
                document.getElementById('play-pause').style.display = 'inline-block';
                document.getElementById('play-auto').style.display = 'none';
            });

            document.getElementById('play-forward').addEventListener('click', () => {
                if (!isPlaying) return;
                currentIndex = (currentIndex + 1) % parentKeys().length;
                renderGroup(parentKeys()[currentIndex]);
            });

            document.getElementById('play-backward').addEventListener('click', () => {
                if (!isPlaying) return;
                currentIndex = (currentIndex - 1 + parentKeys().length) % parentKeys().length;
                renderGroup(parentKeys()[currentIndex]);
            });

            document.getElementById('play-pause').addEventListener('click', () => {
                isPlaying = false;
                currentParentFilter = null;
                currentSupplierFilter = null;
                setCombinedFilters();
                document.getElementById('play-pause').style.display = 'none';
                document.getElementById('play-auto').style.display = 'inline-block';
            });

            // ── Supplier play/pause ──────────────────────────────────────
            let isSupplierPlaying = false;
            let supplierIndex = 0;

            function getSupplierList() {
                const tbl = Tabulator.findTable("#forecast-table")[0];
                if (!tbl) return [];
                const seen = new Set();
                const list = [];
                tbl.getRows().forEach(function(row) {
                    const d = row.getData();
                    // Try cell value first (bypasses accessor), then raw data fields
                    const cell = row.getCell("mfrg_supplier");
                    const s = String(
                        (cell ? cell.getValue() : null) ||
                        d.mfrg_supplier || d['mfrg_supplier'] || ''
                    ).trim();
                    if (s && s !== '-' && !seen.has(s)) { seen.add(s); list.push(s); }
                });
                return list.sort();
            }

            function renderSupplierGroup(supplier) {
                currentSupplierFilter = supplier;
                setCombinedFilters();
                const lbl = document.getElementById('supplier-play-label');
                if (lbl) { lbl.textContent = supplier; lbl.style.display = 'inline-block'; }
                // Scroll to top of table
                const tbl = Tabulator.findTable("#forecast-table")[0];
                if (tbl && tbl.rowManager && tbl.rowManager.element) {
                    tbl.rowManager.element.scrollTop = 0;
                }
            }

            document.getElementById('supplier-play-auto').addEventListener('click', function() {
                const list = getSupplierList();
                if (!list.length) { alert('No supplier data available.'); return; }
                isSupplierPlaying = true;
                supplierIndex = 0;
                renderSupplierGroup(list[supplierIndex]);
                document.getElementById('supplier-play-pause').style.display = 'inline-block';
                document.getElementById('supplier-play-auto').style.display = 'none';
            });

            document.getElementById('supplier-play-forward').addEventListener('click', function() {
                if (!isSupplierPlaying) return;
                const list = getSupplierList();
                supplierIndex = (supplierIndex + 1) % list.length;
                renderSupplierGroup(list[supplierIndex]);
            });

            document.getElementById('supplier-play-backward').addEventListener('click', function() {
                if (!isSupplierPlaying) return;
                const list = getSupplierList();
                supplierIndex = (supplierIndex - 1 + list.length) % list.length;
                renderSupplierGroup(list[supplierIndex]);
            });

            document.getElementById('supplier-play-pause').addEventListener('click', function() {
                isSupplierPlaying = false;
                currentSupplierFilter = null;
                setCombinedFilters();
                document.getElementById('supplier-play-pause').style.display = 'none';
                document.getElementById('supplier-play-auto').style.display = 'inline-block';
                const lbl = document.getElementById('supplier-play-label');
                if (lbl) lbl.style.display = 'none';
            });
            // ─────────────────────────────────────────────────────────────

            // ── Zone play/pause ──────────────────────────────────────────
            let isZonePlaying = false;
            let zoneIndex = 0;

            function getZoneList() {
                const tbl = Tabulator.findTable("#forecast-table")[0];
                if (!tbl) return [];
                const seen = new Set();
                const list = [];
                tbl.getRows().forEach(function(row) {
                    const d = row.getData();
                    const cell = row.getCell("r2s_zone");
                    const s = String(
                        (cell ? cell.getValue() : null) ||
                        d.r2s_zone || d['r2s_zone'] || ''
                    ).trim();
                    if (s && s !== '-' && !seen.has(s)) { seen.add(s); list.push(s); }
                });
                return list.sort();
            }

            function renderZoneGroup(zone) {
                currentZoneFilter = zone;
                setCombinedFilters();
                const lbl = document.getElementById('zone-play-label');
                if (lbl) { lbl.textContent = zone; lbl.style.display = 'inline-block'; }
                const tbl = Tabulator.findTable("#forecast-table")[0];
                if (tbl && tbl.rowManager && tbl.rowManager.element) {
                    tbl.rowManager.element.scrollTop = 0;
                }
            }

            document.getElementById('zone-play-auto').addEventListener('click', function() {
                const list = getZoneList();
                if (!list.length) { alert('No zone data available.'); return; }
                isZonePlaying = true;
                zoneIndex = 0;
                renderZoneGroup(list[zoneIndex]);
                document.getElementById('zone-play-pause').style.display = 'inline-block';
                document.getElementById('zone-play-auto').style.display = 'none';
            });

            document.getElementById('zone-play-forward').addEventListener('click', function() {
                if (!isZonePlaying) return;
                const list = getZoneList();
                zoneIndex = (zoneIndex + 1) % list.length;
                renderZoneGroup(list[zoneIndex]);
            });

            document.getElementById('zone-play-backward').addEventListener('click', function() {
                if (!isZonePlaying) return;
                const list = getZoneList();
                zoneIndex = (zoneIndex - 1 + list.length) % list.length;
                renderZoneGroup(list[zoneIndex]);
            });

            document.getElementById('zone-play-pause').addEventListener('click', function() {
                isZonePlaying = false;
                currentZoneFilter = null;
                setCombinedFilters();
                document.getElementById('zone-play-pause').style.display = 'none';
                document.getElementById('zone-play-auto').style.display = 'inline-block';
                const lbl = document.getElementById('zone-play-label');
                if (lbl) lbl.style.display = 'none';
            });
            // ─────────────────────────────────────────────────────────────

            // ── Container (Trn) play/pause ───────────────────────────────
            let isContainerPlaying = false;
            let containerIndex = 0;

            function getContainerList() {
                const tbl = Tabulator.findTable("#forecast-table")[0];
                if (!tbl) return [];
                const seen = new Set();
                const list = [];
                tbl.getRows().forEach(function(row) {
                    const d = row.getData();
                    const raw = String(d.containerName || '').trim();
                    if (!raw) return;
                    raw.split(',').forEach(function(part) {
                        const c = part.trim();
                        if (c && c !== '-' && !seen.has(c)) { seen.add(c); list.push(c); }
                    });
                });
                return list.sort();
            }

            function renderContainerGroup(container) {
                currentContainerFilter = container;
                setCombinedFilters();
                const lbl = document.getElementById('container-play-label');
                if (lbl) { lbl.textContent = container; lbl.style.display = 'inline-block'; }
                const tbl = Tabulator.findTable("#forecast-table")[0];
                if (tbl && tbl.rowManager && tbl.rowManager.element) {
                    tbl.rowManager.element.scrollTop = 0;
                }
            }

            document.getElementById('container-play-auto').addEventListener('click', function() {
                const list = getContainerList();
                if (!list.length) { alert('No container data available.'); return; }
                isContainerPlaying = true;
                containerIndex = 0;
                renderContainerGroup(list[containerIndex]);
                document.getElementById('container-play-pause').style.display = 'inline-block';
                document.getElementById('container-play-auto').style.display = 'none';
            });

            document.getElementById('container-play-forward').addEventListener('click', function() {
                if (!isContainerPlaying) return;
                const list = getContainerList();
                containerIndex = (containerIndex + 1) % list.length;
                renderContainerGroup(list[containerIndex]);
            });

            document.getElementById('container-play-backward').addEventListener('click', function() {
                if (!isContainerPlaying) return;
                const list = getContainerList();
                containerIndex = (containerIndex - 1 + list.length) % list.length;
                renderContainerGroup(list[containerIndex]);
            });

            document.getElementById('container-play-pause').addEventListener('click', function() {
                isContainerPlaying = false;
                currentContainerFilter = null;
                setCombinedFilters();
                document.getElementById('container-play-pause').style.display = 'none';
                document.getElementById('container-play-auto').style.display = 'inline-block';
                const lbl = document.getElementById('container-play-label');
                if (lbl) lbl.style.display = 'none';
            });
            // ─────────────────────────────────────────────────────────────

            if (currentColorFilter === null || currentColorFilter === undefined) currentColorFilter = '';
            const apprLabelEl = document.getElementById('appr-req-badge-label');
            if (apprLabelEl) {
                apprLabelEl.textContent = currentColorFilter === 'yellow' ? 'Appr Req.' : (currentColorFilter === 'red' ? 'Filter' : 'All');
            }
            setCombinedFilters();
            updateTopRowCounter();

            document.querySelectorAll('#order-color-filter-dropdown + .dropdown-menu [data-filter]').forEach(
                btn => {
                    btn.addEventListener('click', function() {
                        const filter = this.getAttribute('data-filter');
                        currentColorFilter = filter || null;
                        setCombinedFilters();
                        saveForecastFilterPrefs();

                        const lbl = document.getElementById('appr-req-badge-label');
                        if (lbl) {
                            lbl.textContent = filter === 'yellow' ? 'Appr Req.' :
                                (filter === 'red' ? 'Filter' : 'All');
                        }
                        const ddBtn = document.getElementById('order-color-filter-dropdown');
                        if (ddBtn && typeof bootstrap !== 'undefined' && bootstrap.Dropdown) {
                            const inst = bootstrap.Dropdown.getInstance(ddBtn);
                            if (inst) inst.hide();
                        }
                    });
                });

            document.getElementById('row-data-type').addEventListener('change', function(e) {
                currentRowTypeFilter = e.target.value;
                setCombinedFilters();
                saveForecastFilterPrefs();
            });

            const twoOrdFilterEl = document.getElementById('two-ord-color-filter');
            if (twoOrdFilterEl && twoOrdFilterEl.value) {
                window.__fa_applyTwoOrdColorFilter(twoOrdFilterEl.value);
            }
            const topQtyFilterConfig = [
                { id: 'order-color-filter-top', key: 'order' },
                { id: 'mip-color-filter-top', key: 'mip' },
                { id: 'r2s-color-filter-top', key: 'r2s' },
                { id: 'trn-color-filter-top', key: 'trn' },
                { id: 'moq-color-filter-top', key: 'moq' },
            ];
            topQtyFilterConfig.forEach(function(cfg) {
                const el = document.getElementById(cfg.id);
                if (!el) return;
                if (el.value) applyTopQtySignFilter(cfg.key, el.value);
                el.addEventListener('change', function(e) {
                    applyTopQtySignFilter(cfg.key, e && e.target ? e.target.value : '');
                    saveForecastFilterPrefs();
                });
            });

            // Stage filter (toolbar) — keep in sync with Stage column header filter
            // Per-column search inputs
            (function() {
                function makeSearchHandler(inputId, setter) {
                    const el = document.getElementById(inputId);
                    if (!el) return;
                    let timer;
                    el.addEventListener('input', function() {
                        clearTimeout(timer);
                        timer = setTimeout(function() {
                            setter(el.value.trim().toLowerCase());
                            setCombinedFilters();
                        }, 200);
                    });
                    el.addEventListener('keydown', function(e) {
                        if (e.key === 'Escape') {
                            el.value = '';
                            setter('');
                            setCombinedFilters();
                        }
                    });
                }
                makeSearchHandler('search-sku',      function(v) { currentSearchSku      = v; });
                makeSearchHandler('search-parent',   function(v) { currentSearchParent   = v; });
                makeSearchHandler('search-supplier', function(v) { currentSearchSupplier = v; });
            })();

            document.getElementById('stage-filter').addEventListener('change', function(e) {
                currentStageFilter = normalizeStageValue(e.target.value);
                syncColumnsAfterTatForStageFilter();
                setCombinedFilters();
                saveForecastFilterPrefs();
            });

            document.querySelectorAll('.nrp-ms-opt').forEach(function(cb) {
                cb.addEventListener('change', function() {
                    let n = document.querySelectorAll('.nrp-ms-opt:checked').length;
                    if (n === 0) {
                        document.querySelectorAll('.nrp-ms-opt').forEach(x => { x.checked = true; });
                    }
                    updateNRPMultiselectLabel();
                    setCombinedFilters();
                    saveForecastFilterPrefs();
                });
            });

            const tableEl = document.getElementById('forecast-table');
            if (tableEl) {
                tableEl.addEventListener('change', function(e) {
                    if (e.target.closest && e.target.closest('.tabulator-col[tabulator-field="nr"]')) {
                        const tbl = Tabulator.findTable("#forecast-table")[0];
                        if (tbl && typeof tbl.getHeaderFilterValue === 'function') {
                            syncNRPMultiselectFromHeader(tbl.getHeaderFilterValue("nr"));
                        }
                        setCombinedFilters();
                        saveForecastFilterPrefs();
                    }
                    if (e.target.closest && e.target.closest('.tabulator-col[tabulator-field="stage"]')) {
                        if (isProgrammaticStageHeaderSync) {
                            return;
                        }
                        const tbl = Tabulator.findTable("#forecast-table")[0];
                        if (tbl && typeof tbl.getHeaderFilterValue === 'function') {
                            let v = tbl.getHeaderFilterValue("stage");
                            currentStageFilter = normalizeStageValue(v);
                            syncColumnsAfterTatForStageFilter();
                            const sf = document.getElementById("stage-filter");
                            if (sf) sf.value = currentStageFilter;
                            setCombinedFilters();
                            saveForecastFilterPrefs();
                        }
                    }
                    if (e.target.closest && e.target.closest('.tabulator-col[tabulator-field="INV"]')) {
                        syncInvFilterFromHeader();
                    }
                });
                tableEl.addEventListener('input', function(e) {
                    if (e.target.closest && e.target.closest('.tabulator-col[tabulator-field="INV"]')) {
                        syncInvFilterFromHeader();
                    }
                });
            }

            // Keep R2S Val badge in sync with Ready to Ship blade (refresh on load, every 60s, and when tab becomes visible)
            function refreshR2sVal() {
                const el = document.getElementById('total_r2s_value_display');
                if (!el) return;
                fetch("{{ route('ready.to.ship.r2s.total') }}", { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (typeof data.value === 'number') {
                            el.textContent = data.value.toLocaleString('en-US');
                        }
                    })
                    .catch(function() {});
            }
            refreshR2sVal();
            setInterval(refreshR2sVal, 60000);
            document.addEventListener('visibilitychange', function() {
                if (document.visibilityState === 'visible') refreshR2sVal();
            });
        });

        // Scout products view handler
        document.addEventListener('click', function(e) {
            const trigger = e.target.closest('.scouth-products-view-trigger');
            if (trigger) {
                e.preventDefault();
                e.stopPropagation();

                const encoded = trigger.getAttribute('data-item');
                if (encoded) {
                    try {
                        const rawData = JSON.parse(decodeURIComponent(encoded));
                        openModal(rawData, 'scouth products view');
                    } catch (err) {
                        console.error("Failed to parse rawData", err);
                    }
                }
            }
        });

        window.openModal = function(selectedItem, type) {
            try {
                if (type.toLowerCase() === 'scouth products view') {
                    const modalId = 'scouthProductsModal';
                    return openScouthProductsView(selectedItem, modalId);
                }
            } catch (error) {
                console.error("Error in openModal:", error);
                showNotification('danger', 'Failed to open details view. Please try again.');
            }
        };

        function openScouthProductsView(data, modalId) {
            const modal = document.getElementById(modalId);
            if (!modal) return;

            const title = modal.querySelector('.modal-title');
            const body = modal.querySelector('.modal-body');

            if (!data.scout_data || !data.scout_data.all_data) {
                title.textContent = 'Scout Products View Details';
                body.innerHTML = '<div class="alert alert-warning">No scout data available</div>';
                const instance = new bootstrap.Modal(modal);
                instance.show();
                return;
            }

            // Sort by price
            const sortedProducts = [...data.scout_data.all_data].sort((a, b) => {
                const priceA = parseFloat(a.price) || Infinity;
                const priceB = parseFloat(b.price) || Infinity;
                return priceA - priceB;
            });

            title.textContent = 'Scouth Products View (Sorted by Lowest Price)';

            let html = `
                    <div><strong>Parent:</strong> ${data.Parent || 'N/A'} | <strong>SKU:</strong> ${data['(Child) sku'] || 'N/A'}</div>
                    <div class="table-responsive mt-3">
                        <table class="table table-bordered table-sm align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th><th>Price</th><th>Category</th><th>Dimensions</th><th>Image</th>
                                    <th>Quality Score</th><th>Parent ASIN</th><th>Product Rank</th>
                                    <th>Rating</th><th>Reviews</th><th>Weight</th>
                                </tr>
                            </thead>
                            <tbody>
                `;

            sortedProducts.forEach(product => {
                html += `
                <tr>
                    <td>${product.id || 'N/A'}</td>
                    <td>${product.price ? '$' + parseFloat(product.price).toFixed(2) : 'N/A'}</td>
                    <td>${product.category || 'N/A'}</td>
                    <td>${product.dimensions || 'N/A'}</td>
                    <td>
                        ${product.image_url ? `
                                                                <a href="${product.image_url}" target="_blank">
                                                                    <img src="${product.image_url}" width="60" height="60" style="border-radius:50%;">
                                                                </a>` : 'N/A'}
                    </td>
                    <td>${product.listing_quality_score || 'N/A'}</td>
                    <td>${product.parent_asin || 'N/A'}</td>
                    <td>${product.product_rank || 'N/A'}</td>
                    <td>${product.rating || 'N/A'}</td>
                    <td>${product.reviews || 'N/A'}</td>
                    <td>${product.weight || 'N/A'}</td>
                </tr>
            `;
            });

            html += '</tbody></table></div>';
            body.innerHTML = html;

            const instance = new bootstrap.Modal(modal);
            instance.show();
        }

        // SKU copy handler
        $(document).on('click', '.forecast-copy-sku', function(e) {
            e.stopPropagation();
            const sku = $(this).data('sku');
            navigator.clipboard.writeText(sku).then(() => {
                const icon = $(this);
                icon.removeClass('fa-copy').addClass('fa-check').css('color', '#28a745');
                setTimeout(() => icon.removeClass('fa-check').addClass('fa-copy').css('color', '#6c757d'), 1500);
            });
        });

        $(document).on('click', '.forecast-copy-parent', function(e) {
            e.stopPropagation();
            const parent = $(this).data('parent');
            navigator.clipboard.writeText(parent).then(() => {
                const icon = $(this);
                icon.removeClass('fa-copy').addClass('fa-check').css('color', '#28a745');
                setTimeout(() => icon.removeClass('fa-check').addClass('fa-copy').css('color', '#6c757d'), 1500);
            });
        });

        // ── Export button: Supplier Name, SKU, Image, QTY, Order Date ──────────────
        // ── Payment Terms Pie Chart ─────────────────────────────────────────────
        (function() {
            const PIE_COLORS = [
                '#2563eb','#16a34a','#ea580c','#9333ea','#0891b2',
                '#d97706','#db2777','#65a30d','#dc2626','#475569'
            ];
            let pmtChart = null;

            function buildPmtChart() {
                const ctx = document.getElementById('pmt-terms-chart');
                if (!ctx) return;
                if (pmtChart) { pmtChart.destroy(); pmtChart = null; }
                pmtChart = new Chart(ctx, {
                    type: 'pie',
                    data: { labels: [], datasets: [{ data: [], backgroundColor: [] }] },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function(ctx) {
                                        const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                                        const pct = total > 0 ? Math.round(ctx.parsed / total * 100) : 0;
                                        return ctx.label + ': ' + ctx.parsed + ' (' + pct + '%)';
                                    }
                                }
                            }
                        }
                    }
                });
            }

            window.updatePmtChart = function() {
                if (!table) return;
                const chartRow = document.getElementById('pmt-chart-row');
                // Only update if chart panel is currently visible
                if (!chartRow || chartRow.style.display === 'none' || chartRow.style.display === '') return;

                // Use selected rows if any, otherwise filtered rows
                const selected = table.getSelectedRows ? table.getSelectedRows() : [];
                const useSelected = selected.length > 0;
                const rows = useSelected ? selected : (table.getRows('active') || []);

                const counts = {};
                let total = 0;
                rows.forEach(function(row) {
                    const d = row.getData();
                    if (d.is_parent || d.isParent) return;
                    const term = String(d.r2s_pay_term || '').trim() || 'Not Set';
                    counts[term] = (counts[term] || 0) + 1;
                    total++;
                });

                const noDataEl  = document.getElementById('pmt-chart-nodata');
                const legendEl  = document.getElementById('pmt-chart-legend');
                const canvasWrap = document.querySelector('#pmt-chart-row > div:first-child');
                const scopeEl   = document.getElementById('pmt-chart-scope');

                if (total === 0) {
                    if (noDataEl)  noDataEl.style.display  = 'block';
                    if (legendEl)  legendEl.style.display  = 'none';
                    if (canvasWrap) canvasWrap.style.display = 'none';
                    if (scopeEl)   scopeEl.textContent      = '0 rows';
                    return;
                }

                if (noDataEl)   noDataEl.style.display   = 'none';
                if (legendEl)   legendEl.style.display   = '';
                if (canvasWrap) canvasWrap.style.display = '';

                // Sort by count desc
                const entries = Object.entries(counts).sort((a, b) => b[1] - a[1]);
                const labels = entries.map(e => e[0]);
                const data   = entries.map(e => e[1]);
                const colors = labels.map((_, i) => PIE_COLORS[i % PIE_COLORS.length]);

                if (!pmtChart) buildPmtChart();
                pmtChart.data.labels                       = labels;
                pmtChart.data.datasets[0].data             = data;
                pmtChart.data.datasets[0].backgroundColor  = colors;
                pmtChart.update();

                // Legend
                if (legendEl) {
                    legendEl.innerHTML = entries.map(function(e, i) {
                        const pct = Math.round(e[1] / total * 100);
                        return '<span style="display:inline-flex;align-items:center;gap:4px;white-space:nowrap;">' +
                            '<span style="width:10px;height:10px;border-radius:50%;background:' + colors[i] + ';flex-shrink:0;display:inline-block;"></span>' +
                            '<span><strong>' + e[0] + '</strong>: ' + e[1] + ' (' + pct + '%)</span>' +
                            '</span>';
                    }).join('');
                }

                // Scope badge
                if (scopeEl) {
                    const childSelected = selected.filter(r => { const d = r.getData(); return !d.is_parent && !d.isParent; }).length;
                    scopeEl.textContent = useSelected
                        ? childSelected + ' selected'
                        : total + ' filtered';
                    scopeEl.className = 'badge ' + (useSelected ? 'bg-primary' : 'bg-secondary');
                }
            };

            // Chart toggle button
            document.getElementById('toggle-pmt-chart-btn').addEventListener('click', function() {
                const chartRow = document.getElementById('pmt-chart-row');
                if (!chartRow) return;
                const isHidden = chartRow.style.display === 'none' || chartRow.style.display === '';
                if (isHidden) {
                    chartRow.style.display = 'flex';
                    this.classList.remove('btn-outline-primary');
                    this.classList.add('btn-primary');
                    window.updatePmtChart();
                } else {
                    chartRow.style.display = 'none';
                    this.classList.remove('btn-primary');
                    this.classList.add('btn-outline-primary');
                }
            });

            // Build chart on load
            buildPmtChart();

            // Hook into table events after table is ready
            setTimeout(function() {
                if (!table) return;
                table.on('dataLoaded',   function() { window.updatePmtChart(); });
                table.on('dataFiltered', function() { window.updatePmtChart(); });
                table.on('rowSelected',  function() { window.updatePmtChart(); });
                table.on('rowDeselected',function() { window.updatePmtChart(); });
            }, 500);
        })();

        document.getElementById('export-forecast-btn').addEventListener('click', function() {
            const btn = this;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Exporting…';

            try {
                // Get all active (filtered) rows across all pages
                const rows = table.getRows('active');

                const stageQty = function(d) {
                    const stage = String(d.stage || '').trim().toLowerCase();
                    if (stage === 'to_order_analysis') return parseFloat(d.two_order_qty) || '';
                    if (stage === 'appr_req')           return parseFloat(d.appr_req_qty) || parseFloat(d.MOQ) || '';
                    if (stage === 'mip')                return parseFloat(d.order_given)  || '';
                    if (stage === 'r2s')                return parseFloat(d.readyToShipQty) || '';
                    if (stage === 'transit')            return parseFloat(d.transit)      || '';
                    return parseFloat(d.MOQ) || '';
                };

                const formatDate = function(raw) {
                    if (!raw) return '';
                    const d = new Date(raw);
                    if (isNaN(d.getTime())) return String(raw).split(' ')[0] || '';
                    const months = ['JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'];
                    const dd   = String(d.getDate()).padStart(2, '0');
                    const mon  = months[d.getMonth()];
                    const yyyy = d.getFullYear();
                    return dd + ' ' + mon + ' ' + yyyy; // e.g. 03 MAR 2024
                };

                const headers = ['Supplier Name', 'SKU', 'Image', 'QTY', 'Order Date'];
                const csvData = [headers];

                rows.forEach(function(row) {
                    const d = row.getData();
                    if (d.is_parent || d.isParent) return; // skip parent rows
                    csvData.push([
                        d.mfrg_supplier   || '',
                        d.SKU             || '',
                        d.Image           || '',
                        stageQty(d),
                        formatDate(d.mfrg_order_date),
                    ]);
                });

                // Build CSV string (UTF-8 BOM for Excel compatibility)
                const escape = function(v) {
                    return '"' + String(v ?? '').replace(/"/g, '""') + '"';
                };
                const csv = csvData.map(function(row) {
                    return row.map(escape).join(',');
                }).join('\r\n');

                const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
                const url  = URL.createObjectURL(blob);
                const link = document.createElement('a');
                const date = new Date().toISOString().slice(0, 10);
                link.href     = url;
                link.download = 'forecast_export_' + date + '.csv';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(url);
            } catch (err) {
                console.error('Export error:', err);
                alert('Export failed. Please try again.');
            }

            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-file-csv"></i> <span>Export</span>';
        });

    </script>
@endsection
