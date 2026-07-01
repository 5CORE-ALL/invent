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
            padding: 5px 12px;
            min-height: 70px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
        }
        .tabulator .tabulator-footer .tabulator-page-counter {
            display: block !important;
            font-weight: 500;
            color: #374151;
            padding: 8px 4px;
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
            pointer-events: auto;
            z-index: 10050;
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
        /* Sortable headers: pointer cursor; sort icons hidden but click still works */
        #forecast-table.tabulator .tabulator-header .tabulator-col.tabulator-sortable {
            cursor: pointer;
        }
        #forecast-table.tabulator .tabulator-header .tabulator-col.tabulator-sortable:hover {
            background: #6cb8b3 !important;
            background-color: #6cb8b3 !important;
        }
        #forecast-table.tabulator .tabulator-header .tabulator-col.tabulator-sortable:hover .tabulator-col-content,
        #forecast-table.tabulator .tabulator-header .tabulator-col.tabulator-sortable:hover .tabulator-col-title-holder,
        #forecast-table.tabulator .tabulator-header .tabulator-col.tabulator-sortable:hover .tabulator-col-title,
        #forecast-table.tabulator .tabulator-header .tabulator-col.tabulator-sortable:hover .tabulator-header-filter {
            background: #6cb8b3 !important;
            background-color: #6cb8b3 !important;
        }
        #forecast-table.tabulator .tabulator-header .tabulator-col .tabulator-col-sorter,
        #forecast-table-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-sorter {
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
        .open-month-modal .nrp-status-dot {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            flex-shrink: 0;
            box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.15);
        }
        .forecast-history-row-btn .nrp-status-dot {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            flex-shrink: 0;
            box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.15);
        }
        .forecast-edit-row-btn {
            line-height: 1;
            text-decoration: none !important;
            color: #0d9488 !important;
        }
        .forecast-edit-row-btn:hover {
            color: #0f766e !important;
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
            display: inline-block;
        }
        .stage-dot-cell .stage-mip-icon {
            font-size: 1rem;
            line-height: 1;
            color: #2563eb;
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
        .tabulator-cell.forecast-exec-cell {
            padding-left: 2px !important;
            padding-right: 2px !important;
        }
        .tabulator-cell.forecast-edit-actions-cell {
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

        /* Pink "badge" styling for cells whose value is "pink" (DIL >= 50%,
           GROI% > 100%, etc.). Generic utility class so any percent cell can
           opt in by adding `pink-pct-badge`. Applied only to the inline span
           inside the cell so only the number pill changes — surrounding cell
           padding/border stays as-is.
           NOTE: `.forecast-dil-pct` uses `!important` on `background` and
           `border`, so this utility must use `!important` too or it gets
           cancelled out on DIL cells. */
        .pink-pct-badge {
            display: inline-block !important;
            background-color: #fbe3ee !important;
            color: #ad1457 !important;
            border: 1px solid #f1b5d0 !important;
            border-radius: 999px !important;
            padding: 1px 10px !important;
            line-height: 1.2 !important;
            font-weight: 700;
        }
        /* Backwards-compatible alias so any existing template still using
           `.forecast-dil-pct.is-pink` keeps working. */
        .forecast-dil-pct.is-pink {
            display: inline-block !important;
            background-color: #fbe3ee !important;
            color: #ad1457 !important;
            border: 1px solid #f1b5d0 !important;
            border-radius: 999px !important;
            padding: 1px 10px !important;
            line-height: 1.2 !important;
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
        #forecast-table-wrap .tabulator .tabulator-header .tabulator-col:not(:first-child):not(.tabulator-field-SKU) .tabulator-col-title,
        #forecast-table-wrap .tabulator .tabulator-header .tabulator-col:not(:first-child):not(.tabulator-field-SKU) .tabulator-title {
            writing-mode: vertical-rl;
            transform: rotate(180deg);
            white-space: nowrap;
            font-weight: 700;
            font-size: 0.68rem;
            line-height: 1.1;
            letter-spacing: 0.02em;
            text-align: center;
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
        /* SKU column title — horizontal, 2× header size */
        #forecast-table-wrap .tabulator .tabulator-header .tabulator-col.tabulator-field-SKU .tabulator-col-content,
        #forecast-table-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="SKU"] .tabulator-col-content {
            min-height: auto !important;
            flex-direction: column;
            align-items: stretch;
            justify-content: center;
            gap: 4px;
        }
        #forecast-table-wrap .tabulator .tabulator-header .tabulator-col.tabulator-field-SKU .tabulator-col-title-holder,
        #forecast-table-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="SKU"] .tabulator-col-title-holder {
            min-height: auto !important;
            flex-direction: row !important;
            align-items: center;
            justify-content: center;
            width: 100%;
        }
        #forecast-table-wrap .tabulator .tabulator-header .tabulator-col.tabulator-field-SKU .tabulator-col-title,
        #forecast-table-wrap .tabulator .tabulator-header .tabulator-col.tabulator-field-SKU .tabulator-col-title-holder,
        #forecast-table-wrap .tabulator .tabulator-header .tabulator-col.tabulator-field-SKU .tabulator-title,
        #forecast-table-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="SKU"] .tabulator-col-title,
        #forecast-table-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="SKU"] .tabulator-col-title-holder,
        #forecast-table-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="SKU"] .tabulator-title {
            writing-mode: horizontal-tb !important;
            transform: none !important;
            min-height: auto !important;
            font-size: 1.36rem !important;
            font-weight: 700;
            line-height: 1.15;
            letter-spacing: normal;
            text-align: center;
            white-space: nowrap;
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

        /* Forecast Analysis change-history modal */
        .forecast-history-table { font-size: 12px; }
        .forecast-history-table th,
        .forecast-history-table td {
            padding: 4px 8px !important;
            vertical-align: middle;
        }
        .forecast-history-table .fah-field-cell {
            font-weight: 600;
            color: #0a3d91;
            white-space: nowrap;
        }
        .forecast-history-table .fah-field-cell .fah-field-icon {
            color: #6c8fc4;
            margin-right: 4px;
        }
        .forecast-history-table tr.fah-field-first td {
            border-top: 1px solid #c7dbff;
        }
        .forecast-history-table tr.fah-field-cont .fah-field-cell {
            color: #b9c4d6;
            font-weight: 500;
            font-size: 11px;
        }
        .forecast-history-table .fah-when {
            white-space: nowrap;
            color: #6c757d;
        }
        .forecast-history-table .fah-who .badge {
            font-size: 11px;
            font-weight: 500;
        }
        .forecast-history-table .fah-old {
            color: #842029;
            background: #f8d7da;
            padding: 1px 6px;
            border-radius: 3px;
        }
        .forecast-history-table .fah-new {
            color: #0f5132;
            background: #d1e7dd;
            padding: 1px 6px;
            border-radius: 3px;
        }
        .forecast-history-table .fah-arrow {
            color: #adb5bd;
            margin: 0 4px;
        }
        .forecast-history-table .fah-empty {
            color: #adb5bd;
            font-style: italic;
        }
        .forecast-history-table .fah-latest-dot {
            display: inline-block;
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #17a2b8;
            margin-right: 5px;
            vertical-align: middle;
        }
        #forecast-toolbar-top {
            display: flex;
            flex-wrap: nowrap;
            align-items: stretch;
            gap: 6px;
            width: 100%;
        }
        #forecast-toolbar-top .forecast-play-group {
            display: flex;
            align-items: center;
            gap: 3px;
            border: 1px solid #dee2e6;
            border-radius: 0.25rem;
            padding: 2px 5px;
            background: #f8f9fa;
            flex-shrink: 0;
        }
        #forecast-toolbar-top .forecast-play-group > small {
            font-size: 0.65rem;
            line-height: 1;
        }
        #forecast-toolbar-top .forecast-play-group .btn.rounded-circle {
            width: 26px;
            height: 26px;
            min-width: 26px;
            min-height: 26px;
        }
        #forecast-toolbar-top .forecast-play-group .btn.rounded-circle i {
            font-size: 9px;
        }
        #forecast-toolbar-top .forecast-play-group .forecast-play-letter {
            width: 26px;
            height: 26px;
            min-width: 26px;
            min-height: 26px;
            font-size: 10px;
            padding: 0;
        }
        #forecast-toolbar-top .forecast-play-label {
            font-size: 0.62rem;
            max-width: 80px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        #forecast-toolbar-top .forecast-toolbar-vr {
            align-self: stretch;
            width: 1px;
            min-width: 1px;
            background: rgba(0, 0, 0, 0.12);
            flex-shrink: 0;
            margin: 3px 2px;
        }
        #forecast-summary-badges {
            display: flex;
            flex-wrap: nowrap;
            align-items: stretch;
            gap: 4px;
            flex: 1 1 auto;
            min-width: 0;
        }
        #forecast-summary-badges .btn {
            flex: 1 1 0;
            min-width: 0;
            padding: 0.35rem 0.3rem;
            font-size: 0.78rem;
            line-height: 1.2;
            white-space: nowrap;
            text-align: center;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        @media (max-width: 1400px) {
            #forecast-summary-badges .btn {
                font-size: 0.7rem;
                padding: 0.3rem 0.2rem;
            }
        }
        @media (max-width: 1100px) {
            #forecast-toolbar-top {
                overflow-x: auto;
                scrollbar-width: thin;
            }
            #forecast-summary-badges .btn {
                flex: 0 0 auto;
                min-width: max-content;
            }
        }
        #forecast-filter-bar {
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            gap: 6px;
            width: 100%;
        }
        #forecast-filter-bar .forecast-filter-fields {
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            gap: 6px;
            flex: 1 1 auto;
            min-width: 0;
        }
        #forecast-filter-bar .forecast-filter-field {
            flex: 1 1 0;
            min-width: 0;
            width: auto !important;
        }
        #forecast-filter-bar .forecast-filter-nrp {
            flex: 0.75 1 0;
            min-width: 0;
        }
        #forecast-filter-bar .forecast-filter-nrp .dropdown-toggle {
            width: 100%;
            min-width: 0;
        }
        #forecast-filter-bar .page-info-toolbar-item {
            flex-shrink: 0;
        }
        #forecast-filter-bar .forecast-filter-vr {
            align-self: stretch;
            width: 1px;
            min-width: 1px;
            background: rgba(0, 0, 0, 0.12);
            flex-shrink: 0;
            margin: 2px 0;
        }
        #forecast-filter-bar .forecast-filter-actions {
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            gap: 4px;
            flex-shrink: 0;
        }
        #forecast-filter-bar #stage-filter-badge {
            flex-shrink: 0;
        }
        @media (max-width: 1200px) {
            #forecast-filter-bar {
                overflow-x: auto;
                scrollbar-width: thin;
            }
            #forecast-filter-bar .forecast-filter-fields {
                flex: 0 0 auto;
            }
            #forecast-filter-bar .forecast-filter-field,
            #forecast-filter-bar .forecast-filter-nrp {
                flex: 0 0 100px;
                min-width: 100px;
            }
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

                    <!-- ── Play controls + summary badges (one row) ── -->
                    <div id="forecast-toolbar-top">
                        <!-- Parent play -->
                        <div class="forecast-play-group" title="Play by Parent">
                            <small class="text-muted fw-semibold">P</small>
                            <button id="play-backward" class="btn btn-light btn-sm rounded-circle p-0"><i class="fas fa-step-backward"></i></button>
                            <button id="play-pause"   class="btn btn-primary btn-sm rounded-circle p-0" style="display:none;"><i class="fas fa-pause"></i></button>
                            <button id="play-auto"    class="btn btn-primary btn-sm rounded-circle p-0"><i class="fas fa-play"></i></button>
                            <button id="play-forward" class="btn btn-light btn-sm rounded-circle p-0"><i class="fas fa-step-forward"></i></button>
                        </div>

                        <!-- Supplier play -->
                        <div class="forecast-play-group" title="Play by Supplier">
                            <small class="text-muted fw-semibold">S</small>
                            <button id="supplier-play-backward" class="btn btn-light btn-sm rounded-circle p-0" title="Prev supplier"><i class="fas fa-step-backward"></i></button>
                            <button id="supplier-play-pause"    class="btn btn-warning btn-sm rounded-circle p-0" style="display:none;" title="Stop supplier"><i class="fas fa-pause"></i></button>
                            <button id="supplier-play-auto"     class="btn btn-outline-warning btn-sm rounded-circle p-0 fw-bold forecast-play-letter" title="Play by supplier">S</button>
                            <button id="supplier-play-forward"  class="btn btn-light btn-sm rounded-circle p-0" title="Next supplier"><i class="fas fa-step-forward"></i></button>
                            <span class="badge bg-warning text-dark forecast-play-label" id="supplier-play-label" style="display:none;"></span>
                        </div>

                        <!-- Container play -->
                        <div class="forecast-play-group" title="Play by Container">
                            <small class="text-muted fw-semibold">C</small>
                            <button id="container-play-backward" class="btn btn-light btn-sm rounded-circle p-0" title="Prev container"><i class="fas fa-step-backward"></i></button>
                            <button id="container-play-pause"    class="btn btn-dark btn-sm rounded-circle p-0" style="display:none;" title="Stop container"><i class="fas fa-pause"></i></button>
                            <button id="container-play-auto"     class="btn btn-outline-dark btn-sm rounded-circle p-0 fw-bold forecast-play-letter" title="Play by container">C</button>
                            <button id="container-play-forward"  class="btn btn-light btn-sm rounded-circle p-0" title="Next container"><i class="fas fa-step-forward"></i></button>
                            <span class="badge bg-dark text-white forecast-play-label" id="container-play-label" style="display:none;"></span>
                        </div>

                        <span class="forecast-toolbar-vr" aria-hidden="true"></span>

                        <div id="forecast-summary-badges">
                            <span id="filtered-row-badge" class="btn btn-sm btn-dark fw-semibold text-white" title="Filtered child SKU rows"><span id="filtered-row-count">0</span></span>
                            <button id="total_msl_c" class="btn btn-sm btn-success fw-semibold text-dark">MSL_LP $<span id="total_msl_c_value">0.00</span></button>
                            <button type="button" class="btn btn-sm btn-info fw-semibold text-dark" title="MSL × AMZ price ÷ 4">MSL_SP $<span id="total_msl_sp_amz_value">0</span></button>
                            <button id="total_inv_value" class="btn btn-sm btn-info fw-semibold text-dark" title="INV Value">INV $<span id="total_inv_value_display">0</span></button>
                            <button id="total_lp_value" class="btn btn-sm btn-warning fw-semibold text-dark" title="LP Value">LP $<span id="total_lp_value_display">0</span></button>
                            <button id="total_order_value" class="btn btn-sm btn-warning fw-semibold text-dark" title="2 Ord × CP">Ord $<span id="total_order_value_display">0</span></button>
                            <button id="total_minimal_msl" class="btn btn-sm btn-secondary fw-semibold text-white" title="Missing forecast.analysis">Missing $<span id="total_minimal_msl_value">0</span></button>
                            <button id="total_mip_value" class="btn btn-sm btn-warning fw-semibold text-dark" title="MIP Value">MIP $<span id="total_mip_value_display">0</span></button>
                            <button id="total_r2s_value" class="btn btn-sm btn-warning fw-semibold text-dark" title="R2S Value">R2S $<span id="total_r2s_value_display">0</span></button>
                            <button id="total_transit_value" class="btn btn-sm btn-secondary fw-semibold text-dark" title="Transit Value">Trn $<span id="total_transit_value_display">0</span></button>
                            <button id="total_cbm_value" class="btn btn-sm btn-info fw-semibold text-dark" title="Total CBM — Σ (MSL × CBM/unit) across visible child SKUs">CBM <span id="total_cbm_value_display">0</span></button>
                            <button type="button" id="zero-stock-badge-btn" class="btn btn-sm btn-danger fw-semibold text-white" style="cursor:pointer;" title="Child SKUs with INV ≤ 0 (zero or negative). Click to filter or clear." aria-pressed="false"><span id="zero-stock-count">0%</span></button>
                        </div>
                    </div>

                    <!-- ── Row 2: Searches + Filters ── -->
                    <div id="forecast-filter-bar">
                        <div class="forecast-filter-fields">
                            @include('purchase-master.partials.page-info-toolbar', ['pageKey' => 'forecast'])
                            <input type="text" id="search-parent" class="form-control form-control-sm border-primary forecast-filter-field" placeholder="Parent…" autocomplete="off">
                            <input type="text" id="search-sku" class="form-control form-control-sm border-primary forecast-filter-field" placeholder="SKU…" autocomplete="off">
                            <input type="text" id="search-supplier" class="form-control form-control-sm border-primary forecast-filter-field" placeholder="Supplier…" autocomplete="off">

                            <select id="executive-filter"
                                    class="form-select form-select-sm border-primary forecast-filter-field"
                                    title="Filter by Exec (all executives when unset)"
                                    aria-label="Exec filter">
                                <option value="">Exec</option>
                                <option value="__unassigned__">NA</option>
                                <option value="Atin">Atin</option>
                                <option value="Jack">Jack</option>
                                <option value="Nitish">Nitish</option>
                                <option value="Ajay">Ajay</option>
                                <option value="Candy">Candy</option>
                                <option value="Sruti">Sruti</option>
                            </select>

                            <div class="dropdown forecast-filter-nrp">
                                <button class="btn btn-sm btn-light border border-primary dropdown-toggle w-100" type="button" id="nrp-filter-dropdown" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" title="Filter by NRP: All items (REQ / 2BDC / LATER)">
                                    NRP
                                </button>
                                <ul class="dropdown-menu shadow-sm p-2" style="min-width:170px;" aria-labelledby="nrp-filter-dropdown">
                                    <li class="small text-muted px-2 mb-1">Show item types</li>
                                    <li><label class="dropdown-item-text mb-0 d-flex align-items-center gap-2 cursor-pointer"><input type="checkbox" class="form-check-input nrp-ms-opt flex-shrink-0" value="REQ" checked><span>REQ</span></label></li>
                                    <li><label class="dropdown-item-text mb-0 d-flex align-items-center gap-2 cursor-pointer"><input type="checkbox" class="form-check-input nrp-ms-opt flex-shrink-0" value="NR" checked><span>2BDC</span></label></li>
                                    <li><label class="dropdown-item-text mb-0 d-flex align-items-center gap-2 cursor-pointer"><input type="checkbox" class="form-check-input nrp-ms-opt flex-shrink-0" value="LATER" checked><span>LATER</span></label></li>
                                </ul>
                            </div>

                            <select id="stage-filter" class="form-select form-select-sm border border-primary forecast-filter-field"
                                    title="Stage — child SKU rows currently visible after Stage and all other filters. QTY is the sum of that stage’s quantity column (e.g. appr_req_qty for Appr Req).">
                                <option value="">Stage</option>
                                <option value="__blank__">Not Req Now</option>
                                <option value="two_ord_nonneg">2 Ord</option>
                                <option value="appr_req">Appr Req</option>
                                <option value="mip">MIP</option>
                                <option value="r2s">R2S</option>
                                <option value="transit">Trn</option>
                                <option value="to_order_analysis">Order</option>
                            </select>
                            <span id="stage-filter-badge" style="display:none;background:#0d6efd;color:#fff;font-size:0.78rem;font-weight:700;border-radius:20px;padding:3px 10px;white-space:nowrap;box-shadow:0 1px 4px rgba(13,110,253,.35);"></span>

                            <select id="row-data-type" class="form-select form-select-sm border border-primary forecast-filter-field" aria-label="Row type"></select>
                        </div>

                        <span class="forecast-filter-vr" aria-hidden="true"></span>

                        <div class="forecast-filter-actions">
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

                            <button id="export-forecast-btn" class="btn btn-sm btn-success fw-semibold d-flex align-items-center" type="button"
                                    title="Export filtered rows: Supplier, SKU, Image, QTY, Order Date"
                                    aria-label="Export filtered rows">
                                <i class="fas fa-download"></i>
                            </button>

                            <button id="clear-all-filters-btn"
                                    class="btn btn-sm btn-outline-danger fw-semibold d-flex align-items-center"
                                    type="button"
                                    aria-label="Clear all filters"
                                    title="Reset every search, filter, play mode, NRP selection, header filter and zero-stock toggle">
                                <i class="fas fa-times-circle"></i>
                            </button>

                            @php
                                $__forecastPresidentEmail = 'president@5core.com';
                                $__forecastIsPresident = strtolower(trim((string) (\Illuminate\Support\Facades\Auth::user()->email ?? ''))) === $__forecastPresidentEmail;
                            @endphp
                            @if ($__forecastIsPresident)
                                <button id="archive-selected-btn"
                                        class="btn btn-sm btn-outline-dark fw-semibold d-flex align-items-center"
                                        type="button" disabled
                                        aria-label="Archive selected (0)"
                                        title="Archive selected (0). Archived rows disappear from this view and live on the Restore page.">
                                    <i class="fas fa-archive"></i>
                                </button>
                                <a href="{{ route('forecast.analysis.archived') }}"
                                   class="btn btn-sm btn-outline-secondary fw-semibold d-flex align-items-center"
                                   aria-label="Restore archived rows"
                                   title="Open the Restore page to bring archived rows back">
                                    <i class="fas fa-rotate-left"></i>
                                </a>
                            @endif
                        </div>
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
                            <button class="btn btn-sm btn-warning dropdown-toggle" type="button" id="bulkEditMoqBtn" data-bs-toggle="dropdown" aria-expanded="false" title="Minimum Order Quantity">
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
                    </div>
                    <div id="forecast-table-wrap" class="flex-grow-1" style="min-height: 0;">
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
                    <div id="monthCardWrapper" class="px-3">
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

    {{-- Per-row Edit modal. All fields here post to the same /update-forecast-data
         endpoint used by inline cell editing (see updateForecastField()). Only fields
         the user actually changes are sent; unchanged fields are skipped. --}}
    <div class="modal fade" id="forecastRowEditModal" tabindex="-1" role="dialog"
        aria-labelledby="forecastRowEditLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable shadow-none" role="document">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-primary text-white border-0">
                    <h5 class="modal-title" id="forecastRowEditLabel">
                        <i class="fas fa-edit me-2"></i> Edit Row
                        <small class="text-white-50 ms-2" id="forecastRowEditSubtitle"></small>
                    </h5>
                    <button type="button" class="close text-white custom-close" data-bs-dismiss="modal"
                        aria-label="Close" style="font-size:25px; background-color: transparent; border: none;">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="forecastRowEditForm" autocomplete="off">
                        <input type="hidden" id="fre_sku">
                        <input type="hidden" id="fre_parent">

                        <div class="row g-3">
                            {{-- Quantities --}}
                            <div class="col-12">
                                <h6 class="text-muted small text-uppercase mb-2">Quantities</h6>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-1">MOQ (Approved Qty)</label>
                                <input type="number" step="1" class="form-control" id="fre_moq">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-1">2 Order</label>
                                <input type="number" step="1" min="0" class="form-control" id="fre_order">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-1">MIP</label>
                                <input type="number" step="1" min="0" class="form-control" id="fre_mip">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-1">R2S</label>
                                <input type="number" step="1" min="0" class="form-control" id="fre_r2s">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-1">Transit</label>
                                <input type="number" step="1" min="0" class="form-control" id="fre_transit">
                            </div>

                            {{-- Product Master (CP / CBM live on product_master.Values) --}}
                            <div class="col-12 mt-3">
                                <h6 class="text-muted small text-uppercase mb-2">Product Master</h6>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-1">CP</label>
                                <input type="number" step="0.01" class="form-control" id="fre_cp">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-1">CBM</label>
                                <input type="number" step="0.0001" class="form-control" id="fre_cbm">
                            </div>

                            {{-- Workflow --}}
                            <div class="col-12 mt-3">
                                <h6 class="text-muted small text-uppercase mb-2">Workflow</h6>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-1">Supplier</label>
                                <select class="form-select" id="fre_supplier">
                                    <option value="">-- Select --</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-1">Category</label>
                                <select class="form-select select-searchable" id="fre_category">
                                    <option value="">-- Select --</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-1">Zone</label>
                                <input type="text" class="form-control" id="fre_zone" maxlength="80" placeholder="e.g. Ningbo">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-1">Stage</label>
                                <select class="form-select" id="fre_stage">
                                    <option value="">— None —</option>
                                    <option value="appr_req">Approval Required</option>
                                    <option value="mip">MIP</option>
                                    <option value="r2s">R2S</option>
                                    <option value="transit">Transit</option>
                                    <option value="to_order_analysis">To Order Analysis</option>
                                    <option value="all_good">All Good</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-1">NR</label>
                                <select class="form-select" id="fre_nr">
                                    <option value="">— None —</option>
                                    <option value="REQ">REQ</option>
                                    <option value="NR">NR</option>
                                    <option value="LATER">LATER</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-1">Date of Appr</label>
                                <input type="date" class="form-control" id="fre_dateappr">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-1">REQ</label>
                                <input type="text" maxlength="20" class="form-control" id="fre_req">
                            </div>

                            {{-- Links --}}
                            <div class="col-12 mt-3">
                                <h6 class="text-muted small text-uppercase mb-2">Links</h6>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small mb-1">Comparison Link (Clink)</label>
                                <input type="url" class="form-control" id="fre_clink" placeholder="https://...">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small mb-1">Order Link (Olink)</label>
                                <input type="url" class="form-control" id="fre_olink" placeholder="https://...">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small mb-1">RFQ Form Link</label>
                                <input type="url" class="form-control" id="fre_rfq_form" placeholder="https://...">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small mb-1">RFQ Report</label>
                                <input type="url" class="form-control" id="fre_rfq_report" placeholder="https://...">
                            </div>

                            {{-- Misc --}}
                            <div class="col-12 mt-3">
                                <h6 class="text-muted small text-uppercase mb-2">Other</h6>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-1">Hide</label>
                                <select class="form-select" id="fre_hide">
                                    <option value="">— No change —</option>
                                    <option value="1">Hidden (1)</option>
                                    <option value="0">Visible (0)</option>
                                </select>
                            </div>
                            <div class="col-md-9">
                                <label class="form-label small mb-1">Notes</label>
                                <textarea class="form-control" id="fre_notes" rows="2"
                                    placeholder="Internal notes" style="resize:vertical;"></textarea>
                            </div>
                        </div>

                        <div id="forecastRowEditStatus" class="small mt-3"></div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="forecastRowEditSaveBtn">
                        <i class="fas fa-save me-2"></i> Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Forecast Analysis row change history --}}
    <div class="modal fade" id="forecastHistoryModal" tabindex="-1" aria-labelledby="forecastHistoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); color: white;">
                    <h5 class="modal-title" id="forecastHistoryModalLabel">
                        <i class="bi bi-clock-history me-2"></i>Change History — <span id="forecastHistorySku" class="fw-bold"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="forecastHistoryLoading" class="text-center py-4" style="display:none;">
                        <div class="spinner-border text-info" role="status"></div>
                        <p class="mt-2 text-muted small mb-0">Loading history…</p>
                    </div>
                    <div id="forecastHistoryEmpty" class="alert alert-info mb-0" style="display:none;">
                        <i class="fas fa-info-circle me-2"></i> No edits recorded for this SKU yet. Changes made from now on will be tracked here.
                    </div>
                    <div id="forecastHistoryError" class="alert alert-danger mb-0" style="display:none;"></div>
                    <div class="table-responsive" id="forecastHistoryTableWrap" style="display:none; max-height: 65vh;">
                        <table class="table table-sm table-hover mb-0 align-middle forecast-history-table">
                            <thead class="table-light" style="position: sticky; top: 0; z-index: 1;">
                                <tr>
                                    <th style="white-space:nowrap; width: 24%;">Field</th>
                                    <th style="white-space:nowrap; width: 16%;">When</th>
                                    <th style="white-space:nowrap; width: 14%;">Who</th>
                                    <th>Change (old → new)</th>
                                </tr>
                            </thead>
                            <tbody id="forecastHistoryTbody"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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

        /** Round dollar badge totals to nearest thousand (e.g. 176356 → 176K). */
        function formatBadgeK(value) {
            const n = parseFloat(value);
            if (!Number.isFinite(n)) return '0';
            return Math.round(n / 1000).toLocaleString('en-US') + 'K';
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

        /** Promise wrapper for ready-to-ship inline updates (zone, pay terms, etc.). */
        function updateForecastR2sInlinePromise(sku, column, value) {
            return new Promise(function(resolve) {
                const token = $('meta[name="csrf-token"]').attr('content') || '';
                fetch('/ready-to-ship/inline-update-by-sku', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ sku: sku, column: column, value: value })
                })
                .then(function(r) { return r.json().catch(function() { return { success: false }; }); })
                .then(function(res) {
                    if (res && res.success === true) {
                        resolve({ ok: true, message: res.message || '' });
                    } else {
                        resolve({ ok: false, message: (res && res.message) || 'Not saved' });
                    }
                })
                .catch(function(err) {
                    resolve({ ok: false, message: (err && err.message) || 'AJAX failed' });
                });
            });
        }

        /** Promise wrapper for supplier updates — writes to both to_order + mfrg via /update-forecast-data. */
        function updateForecastSupplierPromise(sku, value, parent) {
            return updateForecastFieldPromise({
                sku: sku,
                parent: parent || '',
                column: 'Supplier',
                value: value
            });
        }

        function populateForecastRowEditSupplierSelect(selectedValue) {
            const sel = document.getElementById('fre_supplier');
            if (!sel) return;
            const value = String(selectedValue == null ? '' : selectedValue).trim();
            const suppliers = (window.forecastSuppliersList || []);
            const names = new Set();
            suppliers.forEach(function(s) {
                const n = (s && (s.name || s.id)) ? String(s.name || s.id).trim() : '';
                if (n) names.add(n);
            });
            if (value) names.add(value);
            const sorted = Array.from(names).sort(function(a, b) { return a.localeCompare(b); });
            sel.innerHTML = '<option value="">-- Select --</option>';
            sorted.forEach(function(name) {
                const opt = document.createElement('option');
                opt.value = name;
                opt.textContent = name;
                if (name === value) opt.selected = true;
                sel.appendChild(opt);
            });
        }

        function populateForecastRowEditCategorySelect(selectedValue) {
            const sel = document.getElementById('fre_category');
            if (!sel) return;
            const value = String(selectedValue == null ? '' : selectedValue).trim();
            const categories = (window.forecastCategoriesList || []);
            const names = new Set();
            categories.forEach(function(c) {
                const n = String(c || '').trim();
                if (n) names.add(n);
            });
            if (value) names.add(value);
            const sorted = Array.from(names).sort(function(a, b) { return a.localeCompare(b); });
            sel.innerHTML = '<option value="">-- Select --</option>';
            sorted.forEach(function(name) {
                const opt = document.createElement('option');
                opt.value = name;
                opt.textContent = name;
                if (name === value) opt.selected = true;
                sel.appendChild(opt);
            });
            if (window.SelectSearchable) window.SelectSearchable.refresh(sel);
        }

        /** Promise wrapper around updateForecastField so the per-row Edit modal can
         *  fire many column updates in parallel and await them all before closing. */
        function updateForecastFieldPromise(payload) {
            return new Promise(function(resolve) {
                $.post('/update-forecast-data', {
                    ...payload,
                    _token: $('meta[name="csrf-token"]').attr('content')
                }).done(function(res) {
                    if (res && res.success) {
                        resolve({ ok: true, payload: payload, message: res.message || '' });
                    } else {
                        resolve({ ok: false, payload: payload, message: (res && res.message) || 'Not saved' });
                    }
                }).fail(function(err) {
                    resolve({ ok: false, payload: payload, message: (err && err.statusText) || 'AJAX failed' });
                });
            });
        }

        // --------------------------------------------------------------------
        // Per-row "Edit" modal: opens with the current row's values, lets the
        // user change any field on that row, and on Save fans the changed
        // fields out to the same /update-forecast-data endpoint that the
        // inline cell editors already use. Reads the current row, posts only
        // the diff (so unchanged fields don't churn the DB), then reloads the
        // table so derived/computed columns (to_order, msl_sp, etc.) refresh.
        // --------------------------------------------------------------------
        let forecastRowEditState = {
            row: null,
            targetRows: [],
            pendingBulkTargets: null,
            original: {},
        };

        function forecastRowGetField(d, ...keys) {
            for (const k of keys) {
                if (d == null) continue;
                if (Object.prototype.hasOwnProperty.call(d, k)) {
                    const v = d[k];
                    if (v !== undefined && v !== null) return v;
                }
            }
            return '';
        }

        /** Display supplier as "FIRST MIDDLE L" — last word abbreviated to first letter. */
        function formatSupplierShortName(name) {
            const value = String(name == null ? '' : name).trim();
            if (!value) return '';
            const parts = value.split(/\s+/).filter(Boolean);
            if (parts.length <= 1) return parts[0] || '';
            const lastInitial = parts[parts.length - 1].charAt(0);
            return parts.slice(0, -1).join(' ') + ' ' + lastInitial;
        }

        function forecastRowToYmd(value) {
            if (!value) return '';
            const s = String(value).trim();
            if (!s) return '';
            // Already ISO yyyy-mm-dd
            if (/^\d{4}-\d{2}-\d{2}/.test(s)) return s.slice(0, 10);
            const d = new Date(s);
            if (isNaN(d.getTime())) return '';
            const yyyy = d.getFullYear();
            const mm = String(d.getMonth() + 1).padStart(2, '0');
            const dd = String(d.getDate()).padStart(2, '0');
            return `${yyyy}-${mm}-${dd}`;
        }

        function openForecastEditModal(row) {
            const d = (row && row.getData()) || {};
            if (d.is_parent || d.isParent) return;

            forecastRowEditState.row = row;

            const sku    = String(forecastRowGetField(d, 'SKU', 'sku') || '').trim();
            const parent = String(forecastRowGetField(d, 'Parent', 'parent') || '').trim();

            // Lock bulk targets when the modal opens so a later selection change
            // (e.g. clicking the Edit button) cannot shrink this to a single row.
            const bulkTargets = (forecastRowEditState.pendingBulkTargets && forecastRowEditState.pendingBulkTargets.length)
                ? forecastRowEditState.pendingBulkTargets
                : ((typeof getForecastBulkTargetRows === 'function')
                    ? getForecastBulkTargetRows(sku, [row])
                    : [row]);
            forecastRowEditState.pendingBulkTargets = null;
            forecastRowEditState.targetRows = bulkTargets;

            // Row data values used to pre-fill the modal. Try several known field
            // aliases so this works against whichever shape the controller's
            // buildForecastAnalysisData() emits.
            const original = {
                moq:        forecastRowGetField(d, 'MOQ', 'Approved QTY', 'approved_qty'),
                order:      forecastRowGetField(d, 'to_order', 'order', 'two_order_qty'),
                mip:        forecastRowGetField(d, 'order_given', 'Order Given'),
                r2s:        forecastRowGetField(d, 'readyToShipQty', 'ready_to_ship', 'r2s'),
                transit:    forecastRowGetField(d, 'transit', 'Transit'),
                cp:         forecastRowGetField(d, 'CP', 'cp', 'LP'),
                cbm:        forecastRowGetField(d, 'CBM', 'cbm'),
                stage:      String(forecastRowGetField(d, 'stage', 'Stage') || '').trim().toLowerCase(),
                // Mirror the table cell's default: empty / invalid -> 'REQ'. This way the
                // modal opens with the same value the user already sees in the NR column,
                // and the diff logic won't no-op when the stored value is empty but the
                // user "confirms" REQ from the dropdown.
                nr: (function() {
                    const raw = String(forecastRowGetField(d, 'nr', 'NR') || '').trim().toUpperCase();
                    if (raw === 'REQ' || raw === 'NR' || raw === 'LATER') return raw;
                    return 'REQ';
                })(),
                date_appr:  forecastRowToYmd(forecastRowGetField(d, 'date_apprvl', 'Date of Appr')),
                req:        forecastRowGetField(d, 'req', 'REQ'),
                clink:      forecastRowGetField(d, 'Clink', 'clink'),
                olink:      forecastRowGetField(d, 'Olink', 'olink'),
                rfq_form:   forecastRowGetField(d, 'rfq_form_link'),
                rfq_report: forecastRowGetField(d, 'rfq_report'),
                hide:       (function(v) {
                    if (v === null || v === undefined || v === '') return '';
                    const n = Number(v);
                    if (!isNaN(n)) return n ? '1' : '0';
                    return String(v).toLowerCase() === 'true' ? '1' : '0';
                })(forecastRowGetField(d, 'hide', 'Hide')),
                notes:      forecastRowGetField(d, 'notes', 'Notes'),
                supplier:   forecastRowGetField(d, 'mfrg_supplier', 'Supplier'),
                category:   forecastRowGetField(d, 'Category', 'category'),
                zone:       forecastRowGetField(d, 'r2s_zone', 'zone'),
            };
            forecastRowEditState.original = original;

            // Pre-fill form
            $('#fre_sku').val(sku);
            $('#fre_parent').val(parent);
            const bulkHint = bulkTargets.length > 1
                ? ' · changes apply to ' + bulkTargets.length + ' selected rows'
                : '';
            $('#forecastRowEditSubtitle').text(sku + (parent ? '  ·  ' + parent : '') + bulkHint);

            $('#fre_moq').val(original.moq);
            $('#fre_order').val(original.order);
            $('#fre_mip').val(original.mip);
            $('#fre_r2s').val(original.r2s);
            $('#fre_transit').val(original.transit);
            $('#fre_cp').val(original.cp);
            $('#fre_cbm').val(original.cbm);
            $('#fre_stage').val(original.stage);
            $('#fre_nr').val(original.nr);
            $('#fre_dateappr').val(original.date_appr);
            $('#fre_req').val(original.req);
            $('#fre_clink').val(original.clink);
            $('#fre_olink').val(original.olink);
            $('#fre_rfq_form').val(original.rfq_form);
            $('#fre_rfq_report').val(original.rfq_report);
            $('#fre_hide').val(original.hide);
            $('#fre_notes').val(original.notes);
            populateForecastRowEditSupplierSelect(original.supplier);
            populateForecastRowEditCategorySelect(original.category);
            $('#fre_zone').val(original.zone);
            $('#forecastRowEditStatus').empty();

            const modalEl = document.getElementById('forecastRowEditModal');
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
        }

        function forecastHistoryEscapeHtml(text) {
            if (text == null) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function forecastHistoryFmtValue(v) {
            if (v === null || v === undefined || v === '') {
                return '<span class="fah-empty">empty</span>';
            }
            return forecastHistoryEscapeHtml(String(v));
        }

        async function openForecastHistoryModal(sku, parent) {
            const modalEl = document.getElementById('forecastHistoryModal');
            if (!modalEl) return;
            const skuLabel = document.getElementById('forecastHistorySku');
            const loadingEl = document.getElementById('forecastHistoryLoading');
            const emptyEl = document.getElementById('forecastHistoryEmpty');
            const errorEl = document.getElementById('forecastHistoryError');
            const tableWrap = document.getElementById('forecastHistoryTableWrap');
            const tbody = document.getElementById('forecastHistoryTbody');

            const label = sku + (parent ? ' · ' + parent : '');
            if (skuLabel) skuLabel.textContent = label;
            if (loadingEl) loadingEl.style.display = 'block';
            if (emptyEl) emptyEl.style.display = 'none';
            if (errorEl) { errorEl.style.display = 'none'; errorEl.textContent = ''; }
            if (tableWrap) tableWrap.style.display = 'none';
            if (tbody) tbody.innerHTML = '';

            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();

            if (!sku) {
                if (loadingEl) loadingEl.style.display = 'none';
                if (errorEl) {
                    errorEl.textContent = 'Missing SKU — history cannot be loaded.';
                    errorEl.style.display = 'block';
                }
                return;
            }

            try {
                const params = new URLSearchParams({ sku: sku });
                if (parent) params.set('parent', parent);
                const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const response = await fetch('/forecast-analysis/history?' + params.toString(), {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Failed to load history.');
                }

                const rows = Array.isArray(data.history) ? data.history : [];
                if (rows.length === 0) {
                    if (emptyEl) emptyEl.style.display = 'block';
                    return;
                }

                const groups = new Map();
                rows.forEach(function(r) {
                    const key = r.field || '';
                    if (!groups.has(key)) {
                        groups.set(key, { label: r.field_label || r.field || '', items: [] });
                    }
                    groups.get(key).items.push(r);
                });

                const parts = [];
                groups.forEach(function(group, fieldKey) {
                    group.items.forEach(function(r, idx) {
                        const isFirst = idx === 0;
                        const isLatest = idx === 0;
                        const rowClass = isFirst ? 'fah-field-first' : 'fah-field-cont';
                        const fieldCell = isFirst
                            ? '<i class="bi bi-tag-fill fah-field-icon"></i>' + forecastHistoryEscapeHtml(group.label)
                            : '<span style="padding-left:14px;">↳</span>';
                        parts.push(
                            '<tr class="' + rowClass + '" data-field="' + forecastHistoryEscapeHtml(fieldKey) + '">' +
                                '<td class="fah-field-cell">' + fieldCell + '</td>' +
                                '<td class="fah-when">' + (isLatest ? '<span class="fah-latest-dot" title="latest"></span>' : '') + forecastHistoryEscapeHtml(r.updated_at || '') + '</td>' +
                                '<td class="fah-who"><span class="badge bg-secondary">' + forecastHistoryEscapeHtml(r.updated_by || 'N/A') + '</span></td>' +
                                '<td>' +
                                    '<span class="fah-old">' + forecastHistoryFmtValue(r.old_value) + '</span>' +
                                    '<i class="bi bi-arrow-right fah-arrow"></i>' +
                                    '<span class="fah-new">' + forecastHistoryFmtValue(r.new_value) + '</span>' +
                                '</td>' +
                            '</tr>'
                        );
                    });
                });

                tbody.innerHTML = parts.join('');
                if (tableWrap) tableWrap.style.display = 'block';
            } catch (err) {
                console.error('Forecast history load error:', err);
                if (errorEl) {
                    errorEl.textContent = err.message || 'Failed to load history.';
                    errorEl.style.display = 'block';
                }
            } finally {
                if (loadingEl) loadingEl.style.display = 'none';
            }
        }

        // Treat empty/null/undefined as equal so re-saving a never-set field doesn't fire.
        function forecastRowValChanged(prev, next) {
            const a = (prev === null || prev === undefined) ? '' : String(prev).trim();
            const b = (next === null || next === undefined) ? '' : String(next).trim();
            return a !== b;
        }

        $(document).on('click', '#forecastRowEditSaveBtn', function() {
            const row = forecastRowEditState.row;
            if (!row) return;
            const $btn = $(this);
            const $status = $('#forecastRowEditStatus');
            const sku = String($('#fre_sku').val() || '').trim();
            if (!sku) {
                $status.html('<span class="text-danger">Missing SKU — cannot save.</span>');
                return;
            }

            const fieldDefs = [
                { id: 'fre_moq',         column: 'MOQ',           key: 'moq' },
                { id: 'fre_order',       column: 'ORDER',         key: 'order' },
                { id: 'fre_mip',         column: 'MIP',           key: 'mip' },
                { id: 'fre_r2s',         column: 'R2S',           key: 'r2s' },
                { id: 'fre_transit',     column: 'Transit',       key: 'transit' },
                { id: 'fre_cp',          column: 'CP',            key: 'cp' },
                { id: 'fre_cbm',         column: 'CBM',           key: 'cbm' },
                { id: 'fre_stage',       column: 'Stage',         key: 'stage' },
                { id: 'fre_supplier',    column: 'supplier',      key: 'supplier' },
                { id: 'fre_category',    column: 'Category',      key: 'category' },
                { id: 'fre_zone',        column: 'area',          key: 'zone' },
                { id: 'fre_nr',          column: 'NR',            key: 'nr' },
                { id: 'fre_dateappr',    column: 'Date of Appr',  key: 'date_appr' },
                { id: 'fre_req',         column: 'REQ',           key: 'req' },
                { id: 'fre_clink',       column: 'Clink',         key: 'clink' },
                { id: 'fre_olink',       column: 'Olink',         key: 'olink' },
                { id: 'fre_rfq_form',    column: 'rfq_form_link', key: 'rfq_form' },
                { id: 'fre_rfq_report', column: 'rfq_report',    key: 'rfq_report' },
                { id: 'fre_hide',        column: 'Hide',          key: 'hide' },
                { id: 'fre_notes',       column: 'Notes',         key: 'notes' },
            ];

            const original = forecastRowEditState.original || {};
            const fieldChanges = [];
            fieldDefs.forEach(function(f) {
                let val = $('#' + f.id).val();
                if (val === undefined || val === null) val = '';
                if (forecastRowValChanged(original[f.key], val)) {
                    fieldChanges.push({ column: f.column, value: val });
                }
            });

            if (fieldChanges.length === 0) {
                $status.html('<span class="text-muted">No changes to save.</span>');
                return;
            }

            const targetRows = (forecastRowEditState.targetRows && forecastRowEditState.targetRows.length)
                ? forecastRowEditState.targetRows
                : getForecastBulkTargetRows(sku, [row]);
            if (!targetRows.length) {
                $status.html('<span class="text-danger">No rows to update.</span>');
                return;
            }

            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Saving…');
            $status.html('<span class="text-muted">Saving ' + fieldChanges.length + ' field(s) to ' + targetRows.length + ' row(s)…</span>');

            (async function() {
                const failed = [];
                let ok = 0;

                for (let ri = 0; ri < targetRows.length; ri++) {
                    const targetRow = targetRows[ri];
                    const d = targetRow.getData() || {};
                    const tSku = String(d.SKU || '').trim();
                    const tParent = String(d.Parent || '').trim();
                    if (!tSku) continue;

                    for (let fi = 0; fi < fieldChanges.length; fi++) {
                        const fc = fieldChanges[fi];
                        if (fc.column === 'Stage') {
                            const moq = parseInt(d.MOQ, 10) || 0;
                            if (!moq) {
                                failed.push(tSku + ' (MOQ=0, Stage skipped)');
                                continue;
                            }
                        }

                        const saveVal = fc.column === 'Stage'
                            ? String(fc.value || '').trim().toLowerCase()
                            : fc.value;

                        try {
                            let res;
                            if (fc.column === 'supplier') {
                                const tParent = String(d.Parent || d.parent || '').trim();
                                res = await updateForecastSupplierPromise(tSku, saveVal, tParent);
                            } else if (fc.column === 'area') {
                                res = await updateForecastR2sInlinePromise(tSku, 'area', saveVal);
                            } else {
                                res = await updateForecastFieldPromise({
                                    sku: tSku,
                                    parent: tParent,
                                    column: fc.column,
                                    value: saveVal
                                });
                            }
                            if (!res || !res.ok) {
                                failed.push(tSku + ' · ' + fc.column + (res && res.message ? ': ' + res.message : ''));
                                continue;
                            }
                            ok++;
                            patchForecastRowAfterModalSave(targetRow, fc.column, saveVal);
                        } catch (e) {
                            failed.push(tSku + ' · ' + fc.column + ': ' + (e.message || 'error'));
                        }
                    }
                }

                if (failed.length === 0) {
                    $status.html('<span class="text-success">Saved ' + fieldChanges.length + ' field(s) on ' + targetRows.length + ' row(s).</span>');
                    setTimeout(function() {
                        const modalEl = document.getElementById('forecastRowEditModal');
                        const modal = bootstrap.Modal.getInstance(modalEl);
                        if (modal) modal.hide();
                        table.deselectRow();
                        forecastBulkSelectionCache = [];
                        updateBulkEditBadge();
                        if (FORECAST_IS_PRESIDENT) forecastArchiveUpdateButton();
                        if (typeof setCombinedFilters === 'function') setCombinedFilters();
                    }, 500);
                } else {
                    $status.html(
                        '<div class="text-warning">' +
                        ok + ' saved, ' + failed.length + ' failed:<ul class="mb-0 small">' +
                        failed.map(function(msg) { return '<li>' + msg + '</li>'; }).join('') +
                        '</ul></div>'
                    );
                }
            })().finally(function() {
                $btn.prop('disabled', false).html('<i class="fas fa-save me-2"></i> Save Changes');
            });
        });
        
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

        let forecastBulkSelectionCache = [];

        function dedupeForecastRows(rows) {
            const seen = new Set();
            return (rows || []).filter(function (row) {
                if (!isSelectableForecastRow(row)) return false;
                const d = row.getData() || {};
                const key = String(d.SKU || '').trim() + '||' + String(d.Parent || '').trim();
                if (!key || seen.has(key)) return false;
                seen.add(key);
                return true;
            });
        }

        function getForecastActiveSelectedRows() {
            if (!table) return [];
            const activeSet = new Set(table.getRows('active'));
            return dedupeForecastRows((table.getSelectedRows() || []).filter(function (row) {
                return activeSet.has(row);
            }));
        }

        function pruneForecastSelectionToActive() {
            if (!table) return;
            const activeSet = new Set(table.getRows('active'));
            (table.getSelectedRows() || []).forEach(function (row) {
                if (!activeSet.has(row)) {
                    row.deselect();
                }
            });
            forecastBulkSelectionCache = getForecastActiveSelectedRows();
            updateBulkEditBadge();
            if (FORECAST_IS_PRESIDENT) forecastArchiveUpdateButton();
        }

        /** Checkbox-selected rows; keeps multi-select when focus moves to a dropdown. */
        function getForecastBulkTargetRows(primarySku, extraRows) {
            const merged = dedupeForecastRows([
                ...(forecastBulkSelectionCache || []),
                ...getForecastActiveSelectedRows(),
                ...(extraRows || [])
            ]);
            if (merged.length > 0) return merged;

            if (primarySku && typeof table !== 'undefined' && table) {
                const match = table.getRows().find(function (r) {
                    return String((r.getData() || {}).SKU || '').trim() === String(primarySku).trim();
                });
                if (match && isSelectableForecastRow(match)) return [match];
            }
            return [];
        }

        function patchForecastRowAfterStage(row, newValue) {
            const rowData = row.getData() || {};
            const stLow = String(newValue || '').trim().toLowerCase();
            const moqNum = parseFloat(rowData.MOQ) || 0;
            const updateData = {
                stage: stLow,
                two_order_qty: stLow === 'to_order_analysis' ? moqNum : 0,
                appr_req_qty: stLow === 'appr_req' ? moqNum : 0,
                order_given: stLow === 'mip' ? rowData.order_given : 0,
                readyToShipQty: stLow === 'r2s' ? rowData.readyToShipQty : 0,
                transit: stLow === 'transit' ? rowData.transit : 0,
            };
            row.update(updateData, true);
            if (typeof syncParentStageQtyColumns === 'function') {
                syncParentStageQtyColumns(rowData.Parent || rowData.parentKey);
            }
            if (typeof row.reformat === 'function') {
                row.reformat();
            } else {
                row.getCells().forEach(function (cell) {
                    if (cell && typeof cell.reformat === 'function') cell.reformat();
                });
            }
            if (typeof setCombinedFilters === 'function') setCombinedFilters();
        }

        function patchForecastRowAfterModalSave(row, column, value) {
            const col = String(column || '');
            if (col === 'Stage') {
                patchForecastRowAfterStage(row, String(value || '').trim().toLowerCase());
                return;
            }

            const patch = {};
            if (col === 'NR') patch.nr = value;
            else if (col === 'MOQ') patch.MOQ = value;
            else if (col === 'ORDER') patch.two_order_qty = value;
            else if (col === 'MIP') patch.order_given = value;
            else if (col === 'R2S') patch.readyToShipQty = value;
            else if (col === 'Transit') patch.transit = value;
            else if (col === 'CP') patch.CP = value;
            else if (col === 'CBM') patch.CBM = value;
            else if (col === 'Notes') patch.notes = value;
            else if (col === 'REQ') patch.req = value;
            else if (col === 'Clink') patch.Clink = value;
            else if (col === 'Olink') patch.Olink = value;
            else if (col === 'rfq_form_link') patch.rfq_form_link = value;
            else if (col === 'rfq_report') patch.rfq_report = value;
            else if (col === 'Hide') patch.hide = value;
            else if (col === 'Date of Appr') patch.date_apprvl = value;
            else if (col === 'supplier') patch.mfrg_supplier = value;
            else if (col === 'Category') patch.Category = value;
            else if (col === 'area') patch.r2s_zone = value;

            if (!Object.keys(patch).length) return;
            row.update(patch, true);
            if (typeof row.reformat === 'function') row.reformat();
        }

        // ── President-only Archive / Restore (selection state lives outside Tabulator
        //    so it survives re-renders, pagination and filter changes) ─────────────
        // Whether the current user is the president - emitted by Blade from
        // Auth::user()->email so the Archive button is also hidden client-side if
        // the request somehow falls through. The actual security check is the
        // exact-email guard on the controller.
        const FORECAST_IS_PRESIDENT = @json($__forecastIsPresident ?? false);
        function forecastArchiveKey(sku, parent) {
            return String(sku || '').trim() + '||' + String(parent || '').trim();
        }

        function forecastArchiveUpdateButton() {
            const count = getForecastActiveSelectedRows().length;
            const $btn = $('#archive-selected-btn');
            if ($btn.length) {
                $btn.prop('disabled', count === 0);
                const title = count === 1
                    ? 'Archive 1 selected row. It will disappear from this view and live on the Restore page.'
                    : 'Archive ' + count + ' selected rows. They will disappear from this view and live on the Restore page.';
                $btn.attr('title', title);
                $btn.attr('aria-label', 'Archive selected (' + count + ')');
            }
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

        let forecastImagePreviewHideTimer = null;
        let forecastImagePreviewEl = null;

        function forecastRemoveImagePreview() {
            if (forecastImagePreviewHideTimer) {
                clearTimeout(forecastImagePreviewHideTimer);
                forecastImagePreviewHideTimer = null;
            }
            document.querySelectorAll('#image-hover-preview').forEach(function(el) {
                el.remove();
            });
            forecastImagePreviewEl = null;
        }

        function forecastCancelImagePreviewHide() {
            if (forecastImagePreviewHideTimer) {
                clearTimeout(forecastImagePreviewHideTimer);
                forecastImagePreviewHideTimer = null;
            }
        }

        function forecastScheduleImagePreviewHide() {
            forecastCancelImagePreviewHide();
            forecastImagePreviewHideTimer = setTimeout(forecastRemoveImagePreview, 220);
        }

        function forecastEnsureImagePreviewListeners(wrap) {
            if (wrap.dataset.forecastPreviewListeners === '1') return;
            wrap.dataset.forecastPreviewListeners = '1';
            wrap.addEventListener('mouseenter', forecastCancelImagePreviewHide);
            wrap.addEventListener('mouseleave', forecastScheduleImagePreviewHide);
        }

        function forecastClampPreviewPosition(wrap, clientX, clientY) {
            const pad = 12;
            let left = clientX + pad;
            let top = clientY + pad;
            wrap.style.position = 'fixed';
            wrap.style.left = left + 'px';
            wrap.style.top = top + 'px';
            const rect = wrap.getBoundingClientRect();
            const vw = window.innerWidth;
            const vh = window.innerHeight;
            const m = 8;
            if (rect.right > vw - m) left = Math.max(m, vw - rect.width - m);
            if (rect.bottom > vh - m) top = Math.max(m, vh - rect.height - m);
            if (left < m) left = m;
            if (top < m) top = m;
            wrap.style.left = left + 'px';
            wrap.style.top = top + 'px';
        }

        function forecastShowImagePreview(clientX, clientY, fullUrl) {
            if (!fullUrl) return;
            forecastCancelImagePreviewHide();
            const existing = forecastImagePreviewEl;
            if (existing && document.body.contains(existing)) {
                const prevImg = existing.querySelector('img');
                if (prevImg && prevImg.getAttribute('src') === fullUrl) {
                    forecastClampPreviewPosition(existing, clientX, clientY);
                    return;
                }
            }
            document.querySelectorAll('#image-hover-preview').forEach(function(el) {
                el.remove();
            });
            forecastImagePreviewEl = null;

            const wrap = document.createElement('div');
            wrap.id = 'image-hover-preview';
            wrap.style.zIndex = '10050';
            wrap.style.pointerEvents = 'auto';
            wrap.style.border = '1px solid #ccc';
            wrap.style.background = '#fff';
            wrap.style.padding = '4px';
            wrap.style.boxShadow = '0 4px 16px rgba(0,0,0,0.18)';
            wrap.style.borderRadius = '6px';
            const big = document.createElement('img');
            big.style.maxWidth = '350px';
            big.style.maxHeight = '350px';
            big.style.display = 'block';
            big.alt = '';
            big.src = fullUrl;
            wrap.appendChild(big);
            forecastEnsureImagePreviewListeners(wrap);
            document.body.appendChild(wrap);
            forecastImagePreviewEl = wrap;
            forecastClampPreviewPosition(wrap, clientX, clientY);
        }

        const table = new Tabulator("#forecast-table", {
            ajaxURL: "/forecast-analysis-data-view",
            ajaxConfig: {
                method: "GET",
                headers: {
                    "Cache-Control": "no-cache, no-store, must-revalidate",
                    "Pragma": "no-cache"
                }
            },
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
            selectableRows: true,
            rowFormatter: function(row) {
                const data = row.getData();
                const sku = data["SKU"] || '';

                if (sku.toUpperCase().includes("PARENT")) {
                    row.getElement().classList.add("parent-row");
                }
            },
            columns: [
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
                                activeRows.forEach(function(row) { row.deselect(); });
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
                        forecastShowImagePreview(e.clientX, e.clientY, fullUrl);
                    },
                    cellMouseMove: function(e, cell) {
                        const preview = forecastImagePreviewEl;
                        if (!preview || !document.body.contains(preview)) return;
                        const img = cell.getElement().querySelector('.hover-thumb');
                        const fullUrl = img ? img.getAttribute('data-full') : '';
                        const big = preview.querySelector('img');
                        if (!fullUrl || !big || big.getAttribute('src') !== fullUrl) return;
                        forecastClampPreviewPosition(preview, e.clientX, e.clientY);
                    },
                    cellMouseOut: function(e, cell) {
                        const related = e.relatedTarget;
                        if (related && typeof related.closest === 'function' && related.closest('#image-hover-preview')) {
                            forecastCancelImagePreviewHide();
                            return;
                        }
                        forecastScheduleImagePreviewHide();
                    },
                    width: 52,
                    minWidth: 48,
                    maxWidth: 56,
                    widthGrow: 0
                },
                {
                    title: "Parent",
                    field: "Parent",
                    visible: false,
                    hideFromColumnPicker: true,
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
                            const percent = Math.round(dilDecimal * 100);
                            // Pink range (>= 50%) gets the badge/pill look; other ranges
                            // keep the previous coloured-text styling.
                            if (percent >= 50) {
                                return `<div class="text-center"><span class="forecast-dil-pct pink-pct-badge">${percent}%</span></div>`;
                            }
                            const col = getDilTextColor(dilDecimal);
                            return `<div class="text-center"><span class="forecast-dil-pct" style="color:${col};">${percent}%</span></div>`;
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
                            <button class="btn btn-sm btn-link open-month-modal d-inline-flex align-items-center" style="padding: 0 4px; vertical-align: middle;" title="View Monthly">
                                <span class="nrp-status-dot" style="background-color:#22c55e;" aria-hidden="true"></span>
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
                            const fbaMonths = row["fba_months"] || null;
                            const mslInfo = {
                                total:       row["Total"]        ?? 0,
                                activeMonths: row["Total month"] ?? 0,
                                msl:         row["msl"]          ?? 0,
                                mslShopify:  row["msl_shopify"]  ?? 0,
                                mAvg:        row["m_avg"]        ?? 0,
                            };
                            openMonthModal(monthData, sku, fbaMonths, mslInfo);
                        }
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
                            markerHtml = '<span class="stage-transit-icon" aria-hidden="true">🚢</span>';
                        } else if (value === 'mip') {
                            markerHtml = '<i class="fas fa-hammer stage-mip-icon" aria-hidden="true"></i>';
                        } else {
                            let dotColor = '#94a3b8';
                            if (value === 'appr_req') dotColor = '#facc15';
                            else if (value === 'to_order_analysis') dotColor = '#c2410c';
                            else if (value === 'r2s') dotColor = '#16a34a';
                            markerHtml = '<span class="stage-status-dot" style="background-color:' + dotColor + ';" aria-hidden="true"></span>';
                        }

                        return (
                            '<div class="stage-dot-cell d-flex justify-content-center align-items-center w-100" title="' + tipAttr + '">' +
                            markerHtml +
                            '</div>'
                        );
                    },
                    // select value is already controlled by formatter selected options
                },
                {
                    title: "Appr",
                    field: "appr_req_qty",
                    accessor: row => (row ? row.appr_req_qty : null),
                    sorter: "number",
                    headerSort: true,
                    hozAlign: "center",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData() || {};
                        const isParent = !!(rowData.is_parent || rowData.isParent);
                        const renderValue = function(valueText, isFallback) {
                            const bg = (!isParent && isFallback) ? 'background:#fff3a0;border-radius:4px;padding:2px 4px;' : '';
                            return `<div style="text-align:center;font-weight:700;${bg}">${valueText}</div>`;
                        };
                        if (!isParent && apprReqHideRowForNrp2BdcOrLater(rowData)) {
                            return renderValue('0', false);
                        }
                        const effective = getEffectiveApprReqValue(rowData);
                        if (!(effective > 0)) {
                            return '<div style="text-align:center;" class="text-muted">—</div>';
                        }
                        const explicit = parseFloat(cell.getValue());
                        const isFallback = !(Number.isFinite(explicit) && explicit > 0);
                        const disp = Number.isInteger(effective) ? effective : effective.toFixed(2).replace(/\.?0+$/, '');
                        return renderValue(disp, isFallback);
                    }
                },
                {
                    title: "Order",
                    field: "two_order_qty",
                    accessor: row => (row ? row.two_order_qty : null),
                    sorter: "number",
                    headerSort: true,
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const v = parseFloat(cell.getValue());
                        if (!v || isNaN(v)) {
                            return '<div style="text-align:center;" class="text-muted">—</div>';
                        }
                        const disp = Number.isInteger(v) ? v : v.toFixed(2).replace(/\.?0+$/, '');
                        return `<div style="text-align:center;font-weight:bold;">${disp}</div>`;
                    },
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
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const value = cell.getValue();
                        const n = parseFloat(value);
                        const showDash = value === null || value === undefined || value === '' || isNaN(n) || n === 0;
                        if (showDash) return `<div style="text-align:center;font-weight:bold;">-</div>`;
                        return `<div style="text-align:center;font-weight:bold;">${String(value)}</div>`;
                    },
                },
                {
                    title: "R2S",
                    field: "readyToShipQty",
                    accessor: row => (row ? row["readyToShipQty"] : null),
                    sorter: "number",
                    headerSort: true,
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const value = cell.getValue();
                        const n = parseFloat(value);
                        const showDash = value === null || value === undefined || value === '' || isNaN(n) || n === 0;
                        if (showDash) return `<div style="text-align:center;font-weight:bold;">-</div>`;
                        if (rowData.is_parent || rowData.isParent) return `<div style="text-align:center;font-weight:bold;">${String(value)}</div>`;
                        return `<div style="text-align:center;font-weight:bold;">${String(value)}</div>`;
                    },
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
                    headerTooltip: "Minimum Order Quantity",
                    accessor: row => (row ? row["MOQ"] : ''),
                    headerSort: true,
                    hozAlign: "center",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const moqNum = displayMoqForRow(rowData);
                        const esc = function(s) {
                            return String(s === null || s === undefined ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                        };

                        if (rowData.is_parent || rowData.isParent) {
                            return `<div 
                                style="outline:none; min-width:40px; text-align:center; font-weight:bold;color:#6c757d;"
                                title="Total MOQ for this parent (edit MOQ on each SKU row below)">
                                ${esc(moqNum)}
                            </div>`;
                        }

                        if (isNrp2BdcRow(rowData)) {
                            return `<span class="forecast-moq-cell" style="display:block;outline:none;min-width:40px;text-align:center;font-weight:bold;color:#212529;"
                                title="2BDC — MOQ shown as 0">0</span>`;
                        }

                        let moqColor = '#212529';
                        const moq = moqNum;
                        const msl = parseFloat(rowData.msl);
                        if (Number.isFinite(moq) && Number.isFinite(msl) && msl > 0) {
                            if (moq < msl) {
                                moqColor = '#1b5e20';
                            } else if (moq > msl) {
                                moqColor = '#b71c1c';
                            }
                        }

                        const disp = Number.isInteger(moq) ? String(moq) : moq.toFixed(2).replace(/\.?0+$/, '');
                        return `<span class="forecast-moq-cell" style="display:block;outline:none;min-width:40px;text-align:center;font-weight:bold;color:${moqColor};"
                            title="${Number.isFinite(parseFloat(rowData.msl)) && parseFloat(rowData.msl) > 0 ? 'Green: MOQ &lt; MSL · Red: MOQ &gt; MSL' : 'MOQ'}">${esc(disp)}</span>`;
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
                            <div class="nrp-dot-cell d-flex justify-content-center align-items-center w-100" title="${tip}">
                                <span class="nrp-status-dot" style="background-color:${dotColor};" aria-hidden="true"></span>
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
                        const rawValue = cell.getValue();
                        const value = rawValue == null ? '' : String(rawValue).trim();
                        const escHtml = function(s) {
                            return String(s == null ? '' : s)
                                .replace(/&/g, '&amp;')
                                .replace(/</g, '&lt;')
                                .replace(/>/g, '&gt;')
                                .replace(/"/g, '&quot;')
                                .replace(/'/g, '&#39;');
                        };
                        const display = value
                            ? escHtml(formatSupplierShortName(value))
                            : '-';
                        return '<span class="forecast-supplier-name" title="' + escHtml(value) + '">' + display + '</span>';
                    }
                },
                {
                    title: "Category",
                    field: "Category",
                    minWidth: 90,
                    width: 100,
                    maxWidth: 130,
                    widthGrow: 0,
                    hozAlign: "center",
                    vertAlign: "middle",
                    headerSort: true,
                    headerTooltip: "Category (per SKU, or from supplier when not set)",
                    formatter: function(cell) {
                        const v = String(cell.getValue() || "").trim();
                        if (!v) {
                            return '<span class="text-muted">—</span>';
                        }
                        return '<span style="font-weight:700;">' + v.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</span>';
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

                        let html = "<div style=\"display:block;text-align:center;padding:0 2px;" + ratingWrapStyle + "\">";
                        if (hasRating) {
                            html += "<span style=\"font-weight:700;color:" + starColor + ";display:inline-flex;align-items:center;justify-content:center;gap:1px;font-size:0.72rem;\">";
                            html += "<i class=\"bi bi-star-fill\" style=\"font-size:0.68rem;line-height:1;\"></i>";
                            html += "<span>" + ratingLine + "</span></span>";
                        } else {
                            html += "<span style=\"font-weight:700;color:#9e9e9e;display:inline-flex;align-items:center;justify-content:center;gap:1px;font-size:0.68rem;\">";
                            html += "<i class=\"bi bi-star\" style=\"font-size:0.65rem;\"></i><span>—</span></span>";
                        }
                        const revMuted = hasRating && rVal >= 4.5 ? '#861657' : '#5c5c5c';
                        const revZero = hasRating && rVal >= 4.5 ? '#9d174d' : '#9e9e9e';
                        if (revLine) {
                            html += "<br><span style=\"font-size:0.62rem;color:" + revMuted + ";font-weight:500;white-space:nowrap;\">" + revLine + "</span>";
                        } else if (hasRating) {
                            html += "<br><span style=\"font-size:0.6rem;color:" + revZero + ";\">(0)</span>";
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
                    visible: false,
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
                    title: "C link",
                    field: "Clink",
                    visible: false,
                    hideFromColumnPicker: true,
                    hozAlign: "center",
                    headerSort: false,
                    headerTooltip: "Comparison link",
                    formatter: function(cell) {
                        const d = cell.getRow().getData() || {};
                        if (d.is_parent || d.isParent) {
                            return '<span style="display:block;text-align:center;color:#6c757d;">-</span>';
                        }
                        const url = String(cell.getValue() || '').trim();
                        if (!url) {
                            return '<span style="display:block;text-align:center;color:#6c757d;">-</span>';
                        }
                        return `<div style="display:flex;align-items:center;justify-content:center;">
                            <a href="${url}" target="_blank" rel="noopener noreferrer"
                                class="btn btn-sm btn-outline-primary py-0 px-2" title="Open link" aria-label="Open link">
                                <i class="mdi mdi-link"></i>
                            </a>
                        </div>`;
                    },
                },
                {
                    title: "RFQ",
                    field: "rfq_form_link",
                    visible: false,
                    hideFromColumnPicker: true,
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
                        // Pink range (>100%) uses the shared pill/badge styling. Other
                        // ranges keep the previous coloured-text styling.
                        if (n > 100) {
                            return `<span class="pink-pct-badge" title="Gross ROI % — red &lt;50, green 50–100, magenta &gt;100">${n}%</span>`;
                        }
                        let col = '#1b5e20';
                        if (n < 50) col = '#b71c1c';
                        return `<span style="display:block;text-align:center;font-weight:700;color:${col};" title="Gross ROI % — red &lt;50, green 50–100, magenta &gt;100">${n}%</span>`;
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
                        if (d.is_parent || d.isParent) {
                            return '<span style="display:block;text-align:center;color:#6c757d;">-</span>';
                        }
                        const v = parseFloat(cell.getValue());
                        const hasData = Number.isFinite(v) && v > 0;
                        const dotColor = hasData ? '#22c55e' : '#dc3545';
                        const tip = hasData ? v.toFixed(4) : 'No data';
                        const escTip = String(tip)
                            .replace(/&/g, '&amp;')
                            .replace(/"/g, '&quot;')
                            .replace(/'/g, '&#39;');
                        return `<div class="nrp-dot-cell d-flex justify-content-center align-items-center w-100" title="${escTip}">
                            <span class="nrp-status-dot" style="background-color:${dotColor};" aria-label="${escTip}"></span>
                        </div>`;
                    }
                },
                {
                    title: "Total CBM",
                    field: "total_cbm",
                    visible: false,
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
                    title: "Order Date",
                    field: "mfrg_order_date",
                    visible: false,
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
                    visible: false,
                    hideFromColumnPicker: true,
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
                    // Exec — reads/writes the same to_order_analysis.exec column used by
                    // the MFRG In Progress page and the /update-link "Exec" save path,
                    // so an exec change here is visible everywhere immediately.
                    title: "Exec",
                    field: "exec",
                    hozAlign: "center",
                    headerSort: true,
                    minWidth: 48,
                    maxWidth: 62,
                    widthGrow: 0,
                    widthShrink: 1,
                    cssClass: "forecast-exec-cell",
                    editor: "list",
                    editorParams: {
                        values: { "": "NA", "Atin": "Atin", "Jack": "Jack", "Nitish": "Nitish", "Ajay": "Ajay", "Candy": "Candy", "Sruti": "Sruti" },
                        defaultValue: "",
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
                        cell._execPrev = cell.getValue();
                    },
                    cellEdited: function(cell) {
                        const row = cell.getRow();
                        const d = row.getData();
                        if (d.is_parent || d.isParent) return;
                        const next = String(cell.getValue() || '').trim();
                        const prev = cell._execPrev || '';
                        delete cell._execPrev;
                        if (next === prev) return;
                        const sku = String(d.SKU || '').trim();
                        if (!sku) return;
                        const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
                        // Same backend path used by MFRG In Progress for executive saves.
                        // value = null when the user picks "Unassigned" so the DB column
                        // gets a real NULL (matches the rest of the app).
                        fetch('/update-link', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': token,
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ sku: sku, row_id: 0, column: 'Exec', value: next || null })
                        })
                        .then(r => r.json())
                        .then(res => {
                            if (!res || !res.success) {
                                cell.setValue(prev, true);
                                alert(res?.message || 'Failed to update Exec');
                            } else {
                                row.update({ exec: next }, true);
                            }
                        })
                        .catch(() => {
                            cell.setValue(prev, true);
                            alert('Failed to update Exec');
                        });
                    },
                    formatter: function(cell) {
                        const d = cell.getRow().getData() || {};
                        if (d.is_parent || d.isParent) return '<span style="display:block;text-align:center;color:#6c757d;">-</span>';
                        const value = String(cell.getValue() || '').trim();
                        const colorMap = {
                            Atin:   { bg: '#3b82f6', text: '#fff' },
                            Jack:   { bg: '#10b981', text: '#fff' },
                            Nitish: { bg: '#8b5cf6', text: '#fff' },
                            Ajay:   { bg: '#f59e0b', text: '#fff' },
                            Candy:  { bg: '#ec4899', text: '#fff' },
                            Sruti:  { bg: '#14b8a6', text: '#fff' },
                        };
                        if (!value) {
                            return '<span style="display:inline-block;padding:2px 6px;border-radius:6px;background:#e5e7eb;color:#6b7280;font-size:0.72rem;font-weight:600;cursor:pointer;white-space:nowrap;" title="Click to assign">NA</span>';
                        }
                        const c = colorMap[value] || { bg: '#6b7280', text: '#fff' };
                        return `<span style="display:inline-block;padding:2px 6px;border-radius:6px;background:${c.bg};color:${c.text};font-size:0.72rem;font-weight:700;cursor:pointer;white-space:nowrap;" title="Click to change">${value}</span>`;
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
                {
                    title: "Edit",
                    field: "_edit_row",
                    hozAlign: "center",
                    headerSort: false,
                    width: 52,
                    minWidth: 44,
                    maxWidth: 58,
                    widthGrow: 0,
                    widthShrink: 1,
                    cssClass: "forecast-edit-actions-cell",
                    download: false,
                    formatter: function(cell) {
                        const d = cell.getRow().getData() || {};
                        if (d.is_parent || d.isParent) {
                            return '<span style="display:block;text-align:center;color:#6c757d;">-</span>';
                        }
                        return `<div style="display:flex;align-items:center;justify-content:center;gap:4px;">
                            <button type="button" class="btn btn-sm btn-link forecast-edit-row-btn d-inline-flex align-items-center p-0" title="Edit row" style="line-height:1;"><i class="mdi mdi-pencil" style="font-size:1rem;"></i></button>
                            <button type="button" class="btn btn-sm btn-link forecast-history-row-btn d-inline-flex align-items-center p-0" title="History — see who changed what" style="line-height:1;">
                                <span class="nrp-status-dot" style="background-color:#22c55e;" aria-label="History"></span>
                            </button>
                        </div>`;
                    },
                    cellClick: function(e, cell) {
                        const d = cell.getRow().getData() || {};
                        if (d.is_parent || d.isParent) return;
                        if (e.target.closest('.forecast-edit-row-btn')) {
                            e.stopPropagation();
                            openForecastEditModal(cell.getRow());
                            return;
                        }
                        if (e.target.closest('.forecast-history-row-btn')) {
                            e.stopPropagation();
                            const sku = String(forecastRowGetField(d, 'SKU', 'sku') || '').trim();
                            const parent = String(forecastRowGetField(d, 'Parent', 'parent') || '').trim();
                            openForecastHistoryModal(sku, parent);
                        }
                    }
                },
            ],
            ajaxResponse: function(url, params, response) {
                groupedSkuData = {}; // clear previous

                // ── Single-pass aggregation ──
                // All badge totals + SKU count derived from one walk of response.data.
                // Previously this block ran ~10 separate .filter/.reduce/.reduce sweeps,
                // each one stalling the main thread before the table could render.
                const dataArr = response.data || [];
                let skuCount = 0;
                let totalInvValue = 0;
                let totalLpValue = 0;
                let totalRestockMsl = 0;
                let totalMinimalMsl = 0;
                let sumRestockShopifyPrice = 0;
                let totalRestockLpSum = 0;
                let restockCount = 0;
                let totalMipValue = 0;
                let totalR2sValue = 0;
                let totalTransitValueLocal = 0;
                // Sum the CBM column (already pre-computed per row as MSL × CBM/unit
                // by the controller — see total_cbm assignment in ForecastAnalysisController).
                let totalCbmSum = 0;

                for (let i = 0, n = dataArr.length; i < n; i++) {
                    const item = dataArr[i];
                    const skuStr = String(item.SKU || '').toLowerCase();
                    if (!skuStr.includes('parent')) skuCount++;
                    if (item.is_parent) continue;

                    const inv = parseFloat(item.INV) || 0;
                    totalInvValue += parseFloat(item.inv_value) || 0;
                    totalLpValue  += parseFloat(item.lp_value)  || 0;
                    const rowCbm = parseFloat(item.total_cbm);
                    if (Number.isFinite(rowCbm)) totalCbmSum += rowCbm;

                    if (inv === 0) {
                        restockCount++;
                        const lp = parseFloat(item.LP) || 0;
                        totalRestockMsl        += lp / 4;
                        totalMinimalMsl        += parseFloat(item.MSL_SP) || 0;
                        sumRestockShopifyPrice += parseFloat(item.shopifyb2c_price) || 0;
                        totalRestockLpSum      += lp;
                    }

                    const stageNorm   = String(item.stage || '').trim().toLowerCase();
                    const readyToShip = String(item.mfrg_ready_to_ship || 'No').trim();
                    const nrNorm      = String(item.nr || '').trim().toUpperCase();

                    if (stageNorm === 'mip' && readyToShip !== 'Yes' && nrNorm !== 'NR') {
                        const qty  = parseFloat(item.order_given) || 0;
                        const rate = parseFloat(item.mip_rate)    || 0;
                        if (qty > 0 && rate > 0) totalMipValue += qty * rate;
                    }
                    if (stageNorm === 'r2s' && nrNorm !== 'NR') {
                        const qty  = parseFloat(item.readyToShipQty) || 0;
                        const rate = parseFloat(item.r2s_rate)       || 0;
                        if (qty > 0 && rate > 0) totalR2sValue += qty * rate;
                    }

                    const t  = parseFloat(item.transit) || 0;
                    const cp = parseFloat(item.CP)      || 0;
                    totalTransitValueLocal += t * cp;
                }

                const averageRestockLp  = restockCount > 0 ? totalRestockLpSum / restockCount : 0;
                const totalRestockMslLp = restockCount * (averageRestockLp / 4);

                // Trn Val: prefer pre-summed server total when present, fall back to client sum
                const serverTransitRaw = (response.total_transit_value !== undefined)
                    ? parseFloat(response.total_transit_value)
                    : NaN;
                const totalTransitValue = Number.isFinite(serverTransitRaw) ? serverTransitRaw : totalTransitValueLocal;

                // ── Single DOM-write phase for every badge ──
                const setBadgeText = (id, value) => {
                    const el = document.getElementById(id);
                    if (el) el.textContent = formatBadgeK(value);
                };
                const totalMslCElement = document.getElementById('total_msl_c_value');
                if (totalMslCElement && response.total_msl_c !== undefined) {
                    totalMslCElement.textContent = formatBadgeK(parseFloat(response.total_msl_c));
                }
                const totalMslSpAmzEl = document.getElementById('total_msl_sp_amz_value');
                if (totalMslSpAmzEl && response.total_msl_sp_amz !== undefined) {
                    totalMslSpAmzEl.textContent = formatBadgeK(parseFloat(response.total_msl_sp_amz));
                }
                setBadgeText('total_inv_value_display',          totalInvValue);
                setBadgeText('total_lp_value_display',           totalLpValue);
                setBadgeText('total_restock_msl_value',          totalRestockMsl);
                setBadgeText('total_minimal_msl_value',          totalMinimalMsl);
                setBadgeText('sum_restock_shopify_price_value',  sumRestockShopifyPrice);
                setBadgeText('total_mip_value_display',          totalMipValue);
                setBadgeText('total_r2s_value_display',          totalR2sValue);
                setBadgeText('total_transit_value_display',      totalTransitValue);
                setBadgeText('total_restock_msl_lp_value',       totalRestockMslLp);

                // CBM badge — rounded to nearest whole number.
                const totalCbmEl = document.getElementById('total_cbm_value_display');
                if (totalCbmEl) {
                    totalCbmEl.textContent = Math.round(totalCbmSum).toLocaleString('en-US');
                }


                const groupedMSL = {};

                // Children grouped by parentKey — built during the .map pass so the
                // parent-aggregation forEach below doesn't have to .filter again per parent.
                const childrenByParent = {};

                const processed = response.data.map((item, index) => {
                    const sku = item["SKU"] || "";
                    const parentKey = item["Parent"] || "";

                    const total = parseFloat(item["Total"]) || 0;
                    const totalMonth = parseFloat(item["Total month"]) || 0;

                    const inv = parseFloat(item["INV"]) || 0;
                    const transit = parseFloat(item["Transit"] ?? item["transit"]) || 0;
                    const orderGiven = parseFloat(item["order_given"] ?? item["Order Given"]) || 0;
                    const r2s = parseFloat(item["readyToShipQty"] ?? item["readyToShipQty"]) || 0;

                    // Use PHP-computed combined MSL (Shopify + FBA) directly — no need to recalculate
                    const msl = parseFloat(item.msl) || (totalMonth > 0 ? (total / totalMonth) * 4 : 0);
                    const effectiveMslForToOrder = msl;
                    const m_avg = parseFloat(item.m_avg) || (totalMonth > 0 ? total / totalMonth : 0);

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

                    // Mirror in children-only bucket so the parent-aggregation loop
                    // below doesn't have to .filter(non-parent) again per parent.
                    if (!isParent) {
                        if (!childrenByParent[parentKey]) childrenByParent[parentKey] = [];
                        childrenByParent[parentKey].push(processedItem);
                    }

                    return processedItem;
                });

                // Update parent rows with sum of all child SKUs (INV, L30, etc.).
                // Previously ran 8 .reduce() + 7 .map().filter().reduce() chains per parent
                // (~15 array sweeps × N children). Now a single typed for-loop per parent.
                processed.forEach(row => {
                    if (!row.isParent) return;
                    const children = childrenByParent[row.parentKey] || [];

                    let sumInv = 0, sumL30 = 0, sumOrderGiven = 0, sumTransit = 0;
                    let sumToOrder = 0, sumMOQ = 0, sumTwoOrderQty = 0, sumApprReqQty = 0;
                    let gpftSum = 0, gpftCnt = 0;
                    let adSum   = 0, adCnt   = 0;
                    let npftSum = 0, npftCnt = 0;
                    let groiSum = 0, groiCnt = 0;
                    let effRoiSum = 0, effRoiCnt = 0;
                    let revSum  = 0, revCnt  = 0;
                    let ratSum  = 0, ratCnt  = 0;

                    for (let i = 0, n = children.length; i < n; i++) {
                        const c = children[i];
                        const raw = c.raw_data;

                        sumInv         += (parseFloat(c.INV)            || (raw && parseFloat(raw.INV))            || 0);
                        sumL30         += (parseFloat(c["L30"])         || (raw && parseFloat(raw["L30"]))         || 0);
                        sumOrderGiven  += (parseFloat(c.order_given)    || (raw && parseFloat(raw.order_given))    || 0);
                        sumTransit     += (parseFloat(c.transit)        || (raw && parseFloat(raw.transit))        || 0);
                        sumToOrder     += (parseFloat(c.to_order)       || 0);
                        if (String(c.nr ?? raw?.nr ?? '').trim().toUpperCase() !== 'NR') {
                            sumMOQ     += (parseFloat(c.MOQ)            || (raw && parseFloat(raw.MOQ))            || 0);
                        }
                        sumTwoOrderQty += (parseFloat(c.two_order_qty)  || 0);
                        sumApprReqQty  += getEffectiveApprReqValue(c);

                        const gp = c.avg_gpft_pct;
                        if (gp != null && gp !== '') {
                            const v = parseFloat(gp);
                            if (Number.isFinite(v)) { gpftSum += v; gpftCnt++; }
                        }
                        const ad = c.avg_ad_pct;
                        if (ad != null && ad !== '') {
                            const v = parseFloat(ad);
                            if (Number.isFinite(v)) { adSum += v; adCnt++; }
                        }
                        const np = c.avg_npft_pct;
                        if (np != null && np !== '') {
                            const v = parseFloat(np);
                            if (Number.isFinite(v)) { npftSum += v; npftCnt++; }
                        }
                        let groiF = NaN;
                        const gi = c.avg_groi_pct;
                        if (gi != null && gi !== '') {
                            const v = parseFloat(gi);
                            if (Number.isFinite(v)) { groiF = v; groiSum += v; groiCnt++; }
                        }

                        const mAvg = parseFloat(c.m_avg) || 0;
                        const moq  = parseFloat(c.MOQ)   || 0;
                        const tat  = mAvg > 0 ? Math.round(moq / mAvg) : 0;
                        if (tat > 0 && Number.isFinite(groiF)) {
                            effRoiSum += Math.round((groiF / tat) * 12);
                            effRoiCnt++;
                        }

                        const r = c.reviews;
                        if (r !== null && r !== undefined && r !== '') {
                            const v = parseInt(String(r).replace(/,/g, ''), 10);
                            if (Number.isFinite(v)) { revSum += v; revCnt++; }
                        }
                        const rt = c.rating;
                        if (rt !== null && rt !== undefined && rt !== '') {
                            const v = parseFloat(rt);
                            if (Number.isFinite(v)) { ratSum += v; ratCnt++; }
                        }
                    }

                    row.m_avg = 0;
                    row.TAT = null;
                    row.avg_gpft_pct = gpftCnt ? Math.round(gpftSum / gpftCnt) : null;
                    row.avg_ad_pct   = adCnt   ? Math.round(adSum   / adCnt   * 10) / 10 : null;
                    row.avg_npft_pct = npftCnt ? Math.round(npftSum / npftCnt) : null;
                    row.avg_groi_pct = groiCnt ? Math.round(groiSum / groiCnt) : null;
                    row.eff_roi_pct  = effRoiCnt ? Math.round(effRoiSum / effRoiCnt) : null;
                    row.reviews      = revCnt  ? Math.round(revSum  / revCnt) : null;
                    row.rating       = ratCnt  ? Math.round(ratSum  / ratCnt  * 10) / 10 : null;
                    row.INV          = sumInv;
                    row["L30"]       = sumL30;
                    row.order_given  = sumOrderGiven;
                    row.transit      = sumTransit;
                    row.to_order     = sumToOrder;
                    row.MOQ          = sumMOQ;
                    row.two_order_qty = sumTwoOrderQty;
                    row.appr_req_qty = sumApprReqQty;
                    if (row.raw_data) {
                        row.raw_data["INV"]            = sumInv;
                        row.raw_data["L30"]            = sumL30;
                        row.raw_data["order_given"]    = sumOrderGiven;
                        row.raw_data["transit"]        = sumTransit;
                        row.raw_data["to_order"]       = sumToOrder;
                        row.raw_data["MOQ"]            = sumMOQ;
                        row.raw_data["two_order_qty"]  = sumTwoOrderQty;
                        row.raw_data["appr_req_qty"]   = sumApprReqQty;
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

                // Defer to the next animation frame so Tabulator paints the rows
                // first; only after the first frame do we recompute column-header
                // counts and apply the cross-row filter pass. This is what unblocks
                // scroll right after the AJAX returns.
                requestAnimationFrame(() => {
                    // Count column-header positives in ONE pass instead of 8 .filter() sweeps.
                    const allData = table.getData();
                    let orderCount = 0, mipCount = 0,
                        r2sCount = 0, transitCount = 0, moqCount = 0;
                    for (let i = 0, n = allData.length; i < n; i++) {
                        const row = allData[i];
                        if (String(row.SKU || '').toLowerCase().includes('parent')) continue;
                        if ((parseFloat(row.two_order_qty)  || 0) > 0) orderCount++;
                        if ((parseFloat(row.order_given)    || 0) > 0) mipCount++;
                        if ((parseFloat(row.readyToShipQty) || 0) > 0) r2sCount++;
                        if ((parseFloat(row.transit)        || 0) > 0) transitCount++;
                        if ((parseFloat(row.MOQ)            || 0) > 0 && !isNrp2BdcRow(row)) moqCount++;
                    }

                    // Batch the 8 column-definition updates inside blockRedraw so Tabulator
                    // re-lays-out the header exactly ONCE instead of once per call.
                    if (typeof table.blockRedraw === 'function') table.blockRedraw();
                    try {
                        table.updateColumnDefinition("SKU",            { title: "SKU (" + skuCount + ")" });
                        table.updateColumnDefinition("two_order_qty",   { title: "Order" });
                        table.updateColumnDefinition("order_given",     { title: "MIP" });
                        table.updateColumnDefinition("readyToShipQty",  { title: "R2S" });
                        table.updateColumnDefinition("transit",         { title: "Trn" });
                        table.updateColumnDefinition("MOQ",             { title: "MOQ", headerTooltip: "Minimum Order Quantity" });
                    } finally {
                        if (typeof table.restoreRedraw === 'function') table.restoreRedraw();
                    }

                    currentMoqPositiveCount = moqCount;
                    updateTopQtyFilterOptionCounts({
                        order: orderCount,
                        mip: mipCount,
                        r2s: r2sCount,
                        trn: transitCount,
                        moq: moqCount,
                    });
                    updateZeroStockBadgeCount();
                    updateFilteredRowBadge();
                    syncZeroStockBadgeActiveState();

                    // setCombinedFilters() runs setFilter() across every row plus a debounced
                    // totals pass; push it to a second rAF so the user can already see/scroll
                    // the rendered table while the cross-row filter pass finishes.
                    requestAnimationFrame(() => {
                        setCombinedFilters();
                        table.setSort([{ column: "mfrg_order_date", dir: "asc" }]);
                    });
                });
                return sorted;
            },

            ajaxError: function(xhr, textStatus, errorThrown) {
                console.error("Error loading data:", textStatus);
            },
        });

        table.on('scrollVertical', forecastRemoveImagePreview);
        table.on('scrollHorizontal', forecastRemoveImagePreview);

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
        window.forecastCategoriesList = @json($allCategories ?? []);
        function loadForecastSuppliers(callback) {
            fetch('/supplier.list.json')
                .then(r => r.json())
                .then(function(data) {
                    window.forecastSuppliersList = data.suppliers || [];
                    if (table) table.redraw();
                    populateBulkSupplierSelect();
                    const freSel = document.getElementById('fre_supplier');
                    if (freSel && freSel.closest('.modal.show')) {
                        populateForecastRowEditSupplierSelect(freSel.value);
                    }
                    if (typeof callback === 'function') callback();
                })
                .catch(function() { window.forecastSuppliersList = []; });
        }
        // Bulk edit badge: show when rows selected, update count
        function updateBulkEditBadge() {
            const badge = document.getElementById('bulk-edit-badge');
            const countEl = document.getElementById('bulk-edit-count');
            if (!badge || !countEl) return;
            const n = getForecastActiveSelectedRows().length;
            if (n > 0) {
                badge.classList.remove('d-none');
                badge.classList.add('d-flex');
                countEl.textContent = n + ' selected';
            } else {
                badge.classList.add('d-none');
                badge.classList.remove('d-flex');
            }
        }
        table.on("rowSelectionChanged", function(data, rows) {
            forecastBulkSelectionCache = getForecastActiveSelectedRows();
            const scrollEl = table.rowManager?.element;
            const savedTop  = scrollEl?.scrollTop  || 0;
            const savedLeft = scrollEl?.scrollLeft || 0;
            const savedPage = (table.getPage && table.getPage() > 0) ? table.getPage() : null;

            updateBulkEditBadge();
            if (FORECAST_IS_PRESIDENT) forecastArchiveUpdateButton();

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
        table.on("dataFiltered", function() {
            pruneForecastSelectionToActive();
            updateTopRowCounter();
        });
        table.on("pageLoaded", function() { updateTopRowCounter(); });

        // Preserve checkbox multi-select before Edit click collapses Tabulator selection.
        $(document).on('mousedown', '.forecast-edit-row-btn', function(e) {
            e.stopPropagation();
            if (!table) return;
            const rowEl = this.closest('.tabulator-row');
            if (!rowEl) return;
            let clickedRow = null;
            table.getRows().forEach(function(r) {
                if (r.getElement() === rowEl) clickedRow = r;
            });
            forecastRowEditState.pendingBulkTargets = dedupeForecastRows([
                ...(forecastBulkSelectionCache || []),
                ...getForecastActiveSelectedRows(),
                ...(clickedRow ? [clickedRow] : [])
            ]);
        });

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
            const selectedRows = getForecastBulkTargetRows();
            const validRows = selectedRows.map(function (row) {
                return { row: row, sku: String((row.getData() || {}).SKU || '').trim() };
            }).filter(function (item) { return item.sku && !item.sku.toLowerCase().includes('parent'); });
            if (validRows.length === 0) { alert('No valid SKUs in selection.'); return; }
            const btn = this;
            btn.disabled = true;
            const token = document.querySelector('input[name="_token"]')?.value || document.querySelector('meta[name="csrf-token"]')?.content || '';
            const promises = validRows.map(function(item) {
                const d = item.row.getData() || {};
                const parent = String(d.Parent || d.parent || '').trim();
                return updateForecastFieldPromise({
                    sku: item.sku,
                    parent: parent,
                    column: 'Supplier',
                    value: supplierName
                }).then(function(res) {
                    return res && res.ok ? res : Promise.reject(new Error((res && res.message) || 'Not saved'));
                });
            });
            Promise.all(promises).then(function() {
                validRows.forEach(function(item) {
                    item.row.update({ mfrg_supplier: supplierName }, true);
                });
                table.deselectRow();
                forecastBulkSelectionCache = [];
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
            const selectedRows = getForecastBulkTargetRows();
            if (selectedRows.length === 0) {
                alert('Select one or more rows using the checkboxes first.');
                return;
            }
            const btn = document.getElementById(btnId);
            if (btn) btn.disabled = true;
            const stageVal = String(column).toLowerCase() === 'stage' ? String(val).trim().toLowerCase() : val;

            (async function () {
                const failed = [];
                let ok = 0;
                for (let i = 0; i < selectedRows.length; i++) {
                    const row = selectedRows[i];
                    const d = row.getData() || {};
                    const sku = String(d.SKU || '').trim();
                    const parent = String(d.Parent || '').trim();
                    if (!sku) continue;

                    if (column === 'Stage') {
                        const moq = parseInt(d.MOQ, 10) || 0;
                        if (!moq) {
                            failed.push(sku + ' (MOQ=0)');
                            continue;
                        }
                    }

                    try {
                        const res = await updateForecastFieldPromise({
                            sku: sku,
                            parent: parent,
                            column: column,
                            value: column === 'Stage' ? stageVal : val
                        });
                        if (!res || !res.ok) {
                            failed.push(sku + (res && res.message ? ': ' + res.message : ''));
                            continue;
                        }
                        ok++;
                        if (column === 'Stage') {
                            patchForecastRowAfterStage(row, stageVal);
                        } else if (column === 'NR') {
                            row.update({ nr: val }, true);
                            row.reformat();
                        } else if (column === 'MOQ') {
                            row.update({ MOQ: val }, true);
                            if (typeof row.reformat === 'function') row.reformat();
                        } else if (column === 'Order') {
                            row.update({ two_order_qty: val }, true);
                        } else if (column === 'CP') {
                            row.update({ CP: val }, true);
                        }
                    } catch (e) {
                        failed.push(sku + ': ' + (e.message || 'error'));
                    }
                }

                table.deselectRow();
                forecastBulkSelectionCache = [];
                updateBulkEditBadge();
                if (btn) btn.disabled = false;
                const el = document.getElementById(selectId);
                if (el) el.value = el.tagName === 'SELECT' ? '' : '';
                const dd = bootstrap.Dropdown.getInstance(document.querySelector('#' + ddBtnId));
                if (dd) dd.hide();

                if (failed.length) {
                    alert('Updated ' + ok + ' row(s). Failed: ' + failed.join('; '));
                }
            })();
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

        let currentParentFilter = null;
        let currentColorFilter = null;
        let currentSearchQuery = '';
        let currentSearchSku = '';
        let currentSearchParent = '';
        let currentSearchSupplier = '';
        let currentExecutiveFilter = '';
        let currentSupplierFilter = null;
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
        function clearForecastStoredPrefs() {
            try {
                localStorage.removeItem(FORECAST_FILTER_PREF_KEY);
                localStorage.removeItem('tabulator_column_visibility');
                localStorage.removeItem('tabulator_column_visibility_forecast');
            } catch (e) {}
        }
        // Never restore saved filters/column prefs after refresh — clear on each load.
        clearForecastStoredPrefs();
        function readForecastFilterPrefs() {
            return {};
        }
        function saveForecastFilterPrefs() {
            clearForecastStoredPrefs();
        }
        function restoreForecastFilterPrefs() {
            /* intentionally empty — filters always start at defaults on refresh */
        }

        /**
         * Reset every filter/search/play mode on the page back to its default state,
         * then re-apply filters (which, with everything empty, shows every row).
         * Wired to the "Clear" button in Row 2 of the toolbar.
         */
        function clearAllForecastFilters() {
            // Stop any active "play" modes by clicking their visible stop buttons.
            // Each mode's `is*Playing` flag is closed over inside its own IIFE, so the
            // only safe public way to stop it is to trigger the same UI click.
            ['play-pause', 'supplier-play-pause', 'container-play-pause']
                .forEach(function(id) {
                    const btn = document.getElementById(id);
                    if (btn && btn.offsetParent !== null) {
                        try { btn.click(); } catch (e) {}
                    }
                });

            // Reset filter state variables
            currentParentFilter      = null;
            currentColorFilter       = '';
            currentSearchQuery       = '';
            currentSearchSku         = '';
            currentSearchParent      = '';
            currentSearchSupplier    = '';
            currentExecutiveFilter   = '';
            currentSupplierFilter    = null;
            currentContainerFilter   = null;
            currentTwoOrdColorFilter = '';
            currentRowTypeFilter     = 'sku';
            currentStageFilter       = '';
            currentInvFilter         = '';
            if (currentTopQtySignFilters && typeof currentTopQtySignFilters === 'object') {
                ['order', 'mip', 'r2s', 'trn', 'moq'].forEach(function(k) {
                    currentTopQtySignFilters[k] = '';
                });
            }

            // Reset DOM inputs
            ['search-sku', 'search-parent', 'search-supplier'].forEach(function(id) {
                const el = document.getElementById(id);
                if (el) el.value = '';
            });
            const execEl = document.getElementById('executive-filter');
            if (execEl) execEl.value = '';
            const sf = document.getElementById('stage-filter');
            if (sf) sf.value = '';
            const sbadge = document.getElementById('stage-filter-badge');
            if (sbadge) { sbadge.style.display = 'none'; sbadge.textContent = ''; }
            const rt = document.getElementById('row-data-type');
            if (rt) rt.value = 'sku';
            const twoOrdSel = document.getElementById('two-ord-color-filter');
            if (twoOrdSel) twoOrdSel.value = '';
            ['order-color-filter-top', 'mip-color-filter-top', 'r2s-color-filter-top',
             'trn-color-filter-top', 'moq-color-filter-top'].forEach(function(id) {
                const el = document.getElementById(id);
                if (el) el.value = '';
            });

            // Recheck all NRP options
            document.querySelectorAll('.nrp-ms-opt').forEach(function(cb) { cb.checked = true; });
            if (typeof updateNRPMultiselectLabel === 'function') updateNRPMultiselectLabel();

            // Clear Tabulator's own header filters + any active setFilter predicate.
            if (table) {
                if (typeof table.clearHeaderFilter === 'function') {
                    try { table.clearHeaderFilter(); } catch (e) {}
                }
                if (typeof table.clearFilter === 'function') {
                    try { table.clearFilter(true); } catch (e) {}
                }
            }

            // Re-apply combined filters with everything empty → shows every row,
            // then refresh the zero-stock badge state (do not persist prefs).
            if (typeof setCombinedFilters === 'function') setCombinedFilters();
            if (typeof syncZeroStockBadgeActiveState === 'function') syncZeroStockBadgeActiveState();
            if (typeof updateZeroStockBadgeCount === 'function') updateZeroStockBadgeCount();
            clearForecastStoredPrefs();
        }
        window.clearAllForecastFilters = clearAllForecastFilters;
        document.addEventListener('click', function(e) {
            const btn = e.target && e.target.closest && e.target.closest('#clear-all-filters-btn');
            if (!btn) return;
            e.preventDefault();
            clearAllForecastFilters();
        });

        $(document).on('click', '#archive-selected-btn', function() {
            if (!FORECAST_IS_PRESIDENT) return;
            const selectedRows = getForecastActiveSelectedRows();
            if (selectedRows.length === 0) return;

            const itemsBySku = new Map();
            selectedRows.forEach(function(r) {
                const d = r.getData() || {};
                const sku = String(d.SKU || '').trim();
                const parent = String(d.Parent || '').trim();
                const key = forecastArchiveKey(sku, parent);
                if (itemsBySku.has(key)) return;
                itemsBySku.set(key, {
                    sku: sku,
                    parent: parent,
                    stage:         d.stage || d.Stage || '',
                    nr:            d.nr || d.NR || '',
                    req:           d.req || d.REQ || '',
                    notes:         d.notes || d.Notes || '',
                    clink:         d.Clink || d.clink || '',
                    olink:         d.Olink || d.olink || '',
                    rfq_form_link: d.rfq_form_link || '',
                    rfq_report:    d.rfq_report || '',
                    date_apprvl:   d.date_apprvl || d['Date of Appr'] || '',
                    approved_qty:  (d.MOQ ?? d['Approved QTY'] ?? d.approved_qty ?? ''),
                    order_given:   (d.order_given ?? d['Order Given'] ?? ''),
                    transit:       (d.transit ?? d.Transit ?? ''),
                });
            });
            const items = Array.from(itemsBySku.values());
            if (!confirm('Archive ' + items.length + ' row(s)? They will be hidden from this view and listed on the Restore page.')) {
                return;
            }

            const $btn = $(this);
            const prevHtml = $btn.html();
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Archiving…');

            $.ajax({
                url: '{{ route('forecast.analysis.archive') }}',
                method: 'POST',
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    items: items,
                },
                success: function(res) {
                    if (res && res.success) {
                        table.deselectRow();
                        forecastBulkSelectionCache = [];
                        forecastArchiveUpdateButton();
                        updateBulkEditBadge();
                        try { table.replaceData(); } catch (e) { /* ignore */ }
                    } else {
                        alert((res && res.message) || 'Failed to archive.');
                    }
                },
                error: function(xhr) {
                    if (xhr && xhr.status === 403) {
                        alert('You are not authorized to archive rows.');
                    } else {
                        alert('Failed to archive (network or server error).');
                    }
                },
                complete: function() {
                    $btn.html(prevHtml);
                    forecastArchiveUpdateButton(); // re-evaluates disabled state
                }
            });
        });

        function updateTopRowCounter() {
            if (!table) return;

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
                    const label = labelMap[currentStageFilter] || currentStageFilter;
                    badge.innerHTML = label + ': <strong>' + childCount + '</strong> SKU' + (childCount !== 1 ? 's' : '');
                    badge.title = 'Filtered child rows (Stage + all filters).';
                    badge.style.display = 'inline-block';
                } else {
                    badge.style.display = 'none';
                    badge.title = '';
                }
            }

            updateFilteredRowBadge();
        }
        function updateFilteredRowBadge() {
            const countEl = document.getElementById('filtered-row-count');
            const badge = document.getElementById('filtered-row-badge');
            if (!countEl || !table) return;
            let filteredChild = 0;
            try {
                const activeRows = table.getRows('active') || [];
                filteredChild = activeRows.filter(function(row) {
                    const d = row.getData();
                    return d && !d.is_parent && !d.isParent;
                }).length;
            } catch (e) {
                filteredChild = 0;
            }
            let totalChild = 0;
            if (typeof table.getData === 'function') {
                table.getData().forEach(function(d) {
                    if (!d || d.is_parent || d.isParent) return;
                    totalChild++;
                });
            }
            const pct = totalChild > 0 ? Math.round((filteredChild / totalChild) * 100) : 0;
            countEl.textContent = String(filteredChild);
            if (badge) {
                badge.title = 'Filtered child SKU rows: ' + filteredChild + ' of ' + totalChild + ' (' + pct + '%)';
            }
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
        /** INV header: empty=all, 0/zero=exactly 0, <=0/le0=zero or negative, >0/gt0=positive */
        let currentInvFilter = '';
        function invHeaderFilterMode(raw) {
            if (raw === undefined || raw === null) return '';
            const s = String(raw).trim().toLowerCase().replace(/\s+/g, '');
            if (s === '' || s === 'all') return '';
            if (s === '<=0' || s === '≤0' || s === 'le0' || s === '0-' || s === 'zeroneg' || s === '0neg' || s === 'nonpos') return 'le0';
            if (s === '0' || s === 'zero' || s === '=0') return 'zero';
            if (s === '>0' || s === 'gt0' || s === '+' || s === 'pos' || s === 'positive') return 'gt0';
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
            if (mode === 'le0') return invNum <= 0;
            if (mode === 'zero') return invNum === 0;
            return true;
        }
        function syncZeroStockBadgeActiveState() {
            const btn = document.getElementById('zero-stock-badge-btn');
            if (!btn) return;
            const on = invHeaderFilterMode(currentInvFilter) === 'le0';
            btn.classList.toggle('active', on);
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        }
        function updateZeroStockBadgeCount() {
            const countEl = document.getElementById('zero-stock-count');
            const btn = document.getElementById('zero-stock-badge-btn');
            if (!countEl || !table || typeof table.getData !== 'function') return;
            let total = 0;
            let zero = 0;
            table.getData().forEach(function(d) {
                if (!d || d.is_parent || d.isParent) return;
                total++;
                const invValue = d.raw_data ? d.raw_data["INV"] : d["INV"];
                const invNum = parseFloat(invValue);
                const v = Number.isFinite(invNum) ? invNum : 0;
                if (v <= 0) zero++;
            });
            const pct = total > 0 ? Math.round((zero / total) * 100) : 0;
            countEl.textContent = pct + '%';
            if (btn) {
                btn.title = 'Child SKUs with INV ≤ 0: ' + zero + ' of ' + total + ' (' + pct + '%). Click to filter or clear.';
            }
        }
        function syncInvFilterFromHeader() {
            const tbl = Tabulator.findTable("#forecast-table")[0];
            if (!tbl || typeof tbl.getHeaderFilterValue !== 'function') return;
            const v = tbl.getHeaderFilterValue("INV");
            currentInvFilter = v === undefined || v === null ? '' : String(v);
            syncZeroStockBadgeActiveState();
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
                // Strict: rows that still need to be routed somewhere (to_order >= 0 AND no explicit
                // stage yet). Rows already tagged Appr Req / MIP / R2S / Trn / Order / All Good
                // belong to their own dropdown options, so they shouldn't be double-counted here.
                const twoOrd = parseFloat(rowData?.to_order ?? raw.to_order ?? 0);
                if (!Number.isFinite(twoOrd) || twoOrd < 0) return false;
                if (stageValue === 'appr_req' || stageValue === 'mip' || stageValue === 'r2s'
                    || stageValue === 'transit' || stageValue === 'all_good'
                    || stageValue === 'to_order_analysis') {
                    return false;
                }
                return true;
            }
            if (stageFilterValue === 'transit') {
                // Strict: only rows whose stage is actually 'transit'. Rows with a positive transit
                // qty but a different stage are still in their own bucket and shouldn't double-count.
                return stageValue === 'transit';
            }
            if (stageFilterValue === 'appr_req') {
                return rowCountsForApprColumnHeader(rowData);
            }
            if (stageFilterValue === 'mip') {
                // Strict: only rows whose stage is actually 'mip'. This keeps the MIP count on
                // /forecast.analysis aligned with the MIP rows displayed on /mfrg-in-progress.
                return stageValue === 'mip';
            }
            if (stageFilterValue === 'r2s') {
                // Strict: only rows whose stage is actually 'r2s'. Same alignment with the R2S
                // rows shown on /mfrg-in-progress (which now lists both MIP and R2S stages).
                return stageValue === 'r2s';
            }
            if (stageFilterValue === 'to_order_analysis') {
                // Strict: only count rows whose stage is actually 'to_order_analysis' so this
                // matches the "2Order" filter/count on /approval.required. Rows with two_order_qty>0
                // but a different stage (mip/r2s/transit/all_good) are already past the 2-Order step
                // and should not appear under the "Order" stage filter here either.
                return stageValue === 'to_order_analysis';
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
                if (isNrp2BdcRow(data)) return 0;
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
                    sumAppr += getEffectiveApprReqValue(d);
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
                    if (isNrp2BdcRow(d)) return;
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
            const btn = document.getElementById('nrp-filter-dropdown');
            if (!btn) return;
            const checked = [...document.querySelectorAll('.nrp-ms-opt:checked')].map(b => b.value);
            const labels = { REQ: 'REQ', NR: '2BDC', LATER: 'LATER' };
            let filterDesc;
            if (checked.length === 3 || checked.length === 0) {
                filterDesc = 'All items';
            } else {
                filterDesc = checked.map(v => labels[v] || v).join(', ');
            }
            btn.title = 'Filter by NRP: ' + filterDesc + ' (REQ / 2BDC / LATER)';
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

        /** NRP = 2BDC (stored as NR) — MOQ displays as 0 for these child rows. */
        function isNrp2BdcRow(rowData) {
            if (!rowData || rowData.is_parent || rowData.isParent) return false;
            const raw = rowData.raw_data || {};
            const nr = String(rowData.nr ?? raw.nr ?? '').trim().toUpperCase();
            return nr === 'NR';
        }

        function displayMoqForRow(rowData) {
            if (isNrp2BdcRow(rowData)) return 0;
            const raw = rowData.raw_data || {};
            const n = parseFloat(rowData.MOQ ?? raw.MOQ ?? raw['Approved QTY']);
            return Number.isFinite(n) ? n : 0;
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
            if (apprReqHideRowForNrp2BdcOrLater(rowData)) return 0;
            const raw = rowData.raw_data || {};
            const twoOrdVal = parseFloat(rowData.to_order ?? raw.to_order ?? 0);

            // When 2 Ord > 0, show MOQ — order still needed even if row is in transit/MIP/R2S.
            if (Number.isFinite(twoOrdVal) && twoOrdVal > 0) {
                const moqForOrder = displayMoqForRow(rowData);
                if (moqForOrder > 0) {
                    return moqForOrder;
                }
            }

            // Rows already moved into a downstream pipeline stage no longer need approval —
            // hide the Appr Req value for them so they drop off /approval.required and /to-order-analysis.
            // 'to_order_analysis' is also excluded so the Appr Req filter/count matches /approval.required
            // (which separates 2Order rows into their own dropdown option).
            const stageNorm = String(rowData.stage ?? raw.stage ?? '').trim().toLowerCase();
            if (stageNorm === 'mip' || stageNorm === 'r2s' || stageNorm === 'transit' || stageNorm === 'all_good' || stageNorm === 'to_order_analysis') {
                return 0;
            }
            const explicitApprReq = parseFloat(rowData.appr_req_qty);
            if (Number.isFinite(explicitApprReq) && explicitApprReq > 0) {
                return explicitApprReq;
            }
            // Loose Appr rule: to_order >= 0, no pipeline qty — show MOQ (yellow in cell).
            if (apprReqHideRowForPipelineQty(rowData)) {
                return 0;
            }
            if (Number.isFinite(twoOrdVal) && twoOrdVal >= 0) {
                const moqVal = displayMoqForRow(rowData);
                if (moqVal > 0) {
                    return moqVal;
                }
            }
            return 0;
        }

        /** Appr Req stage filter — same bucket as the Appr column display. */
        function rowCountsForApprColumnHeader(rowData) {
            if (!rowData || rowData.is_parent || rowData.isParent) return false;
            if (String(rowData.SKU || '').toLowerCase().includes('parent')) return false;
            // Match Appr cell: 2BDC/LATER render "0" — not counted as Appr Req SKUs.
            if (apprReqHideRowForNrp2BdcOrLater(rowData)) return false;
            return getEffectiveApprReqValue(rowData) > 0;
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

                // Executive filter (exact match; "__unassigned__" sentinel = blank exec).
                // Parent rows render Exec as "-" and don't carry their own exec, so the
                // unassigned bucket only meaningfully includes child SKUs.
                if (currentExecutiveFilter) {
                    const rowExec = String(data.exec || '').trim();
                    if (currentExecutiveFilter === '__unassigned__') {
                        if (rowExec !== '') return false;
                    } else if (rowExec.toLowerCase() !== currentExecutiveFilter.toLowerCase()) {
                        return false;
                    }
                }

                // Supplier play filter
                if (currentSupplierFilter) {
                    const rowSupplier = String(data.mfrg_supplier || '').trim();
                    if (rowSupplier !== currentSupplierFilter) return false;
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
                    totalMslCElement.textContent = formatBadgeK(totalMslC);
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
                    totalInvValueElement.textContent = formatBadgeK(visibleInvValue);
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
                    totalLpValueElement.textContent = formatBadgeK(visibleLpValue);
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
                    totalOrderValueElement.textContent = formatBadgeK(visibleOrderValue);
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
                    totalMslSpAmzEl.textContent = formatBadgeK(totalMslSpAmz);
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
                    totalRestockMslElement.textContent = formatBadgeK(totalRestockMsl);
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
                    totalMinimalMslElement.textContent = formatBadgeK(totalMinimalMsl);
                }

                // Calculate sum restock shopify price for visible rows
                const visibleSumRestockShopifyPrice = visibleRestockItems.reduce((sum, row) => {
                    const data = row.getData();
                    return sum + (parseFloat(data.shopifyb2c_price) || 0);
                }, 0);

                // Update sum restock shopify price display
                const sumRestockShopifyPriceElement = document.getElementById('sum_restock_shopify_price_value');
                if (sumRestockShopifyPriceElement) {
                    sumRestockShopifyPriceElement.textContent = formatBadgeK(visibleSumRestockShopifyPrice);
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
                    totalMipValueElement.textContent = formatBadgeK(totalMipValue);
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
                    totalR2sValueElement.textContent = formatBadgeK(totalR2sValue);
                }

                // Trn Val badge: sum(transit QTY × CP) from controller; not recalculated on row filter
            }, 50);

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
        function openMonthModal(monthData, sku, fbaMonths, mslInfo) {
            const wrapper = document.getElementById("monthCardWrapper");
            if (!wrapper) return;

            wrapper.innerHTML = "";
            // Keep 12-col grid (from #monthCardWrapper CSS), reset any leftover inline styles
            wrapper.style.cssText = "";

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

            // Add 12 main month cards directly as grid children (each = 1 of 12 columns)
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

            // MSL formula bar — spans all 12 columns, includes FBA if available
            if (mslInfo) {
                const monthOrder12 = ["JAN","FEB","MAR","APR","MAY","JUN","JUL","AUG","SEP","OCT","NOV","DEC"];

                // Shopify numbers (from server)
                const shopifyTotal        = parseFloat(mslInfo.total)        || 0;
                const shopifyActiveMonths = parseFloat(mslInfo.activeMonths) || 0;
                const shopifyMsl          = parseFloat(mslInfo.mslShopify || mslInfo.msl) || 0;
                const mAvg                = parseFloat(mslInfo.mAvg)         || 0;

                // FBA totals — recalculated from fbaMonths data
                let fbaTotal        = 0;
                let fbaActiveMonths = 0;
                const hasFba = fbaMonths && typeof fbaMonths === 'object';
                if (hasFba) {
                    monthOrder12.forEach(m => {
                        const v = parseFloat(fbaMonths[m] ?? 0) || 0;
                        fbaTotal += v;
                        if (v > 0) fbaActiveMonths++;
                    });
                }

                // Combined per-month sum → combined total and active months
                let combinedTotal        = 0;
                let combinedActiveMonths = 0;
                monthOrder12.forEach(m => {
                    const shopV = parseFloat(monthData[m] ?? 0) || 0;
                    const fbaV  = hasFba ? (parseFloat(fbaMonths[m] ?? 0) || 0) : 0;
                    const combined = shopV + fbaV;
                    combinedTotal += combined;
                    if (combined > 0) combinedActiveMonths++;
                });

                const combinedMsl    = combinedActiveMonths > 0 ? (combinedTotal / combinedActiveMonths) * 4 : 0;
                const combinedMslStr = combinedActiveMonths > 0 ? combinedMsl.toFixed(2) : '0';
                const shopifyMslStr  = shopifyActiveMonths  > 0 ? shopifyMsl.toFixed(2)  : '0';

                const formulaEl = document.createElement("div");
                formulaEl.style.cssText = `
                    grid-column: 1 / -1;
                    background: #f0fdf9;
                    border: 1px solid #a7f3d0;
                    border-radius: 8px;
                    padding: 10px 16px;
                    margin-top: 4px;
                    display: flex;
                    align-items: center;
                    flex-wrap: wrap;
                    gap: 6px 18px;
                    font-size: 0.82rem;
                    color: #065f46;
                `;

                formulaEl.innerHTML = `
                    <span style="font-weight:700; color:#047857; font-size:0.88rem; margin-right:4px;">MSL Formula</span>

                    <span style="display:inline-flex; align-items:center; gap:6px;">
                        <span style="background:#dbeafe; color:#1d4ed8; font-size:0.7rem; font-weight:600; padding:1px 7px; border-radius:20px;">Shopify</span>
                        <span style="color:#374151;"><strong>${shopifyTotal}</strong> ÷ <strong>${shopifyActiveMonths}</strong> mo × 4</span>
                        <span style="color:#6b7280;">=</span>
                        <span style="background:#eff6ff; color:#1d4ed8; font-weight:700; padding:2px 10px; border-radius:20px;">${shopifyMslStr}</span>
                    </span>

                    ${hasFba ? `
                    <span style="color:#d1d5db; font-size:1rem;">+</span>
                    <span style="display:inline-flex; align-items:center; gap:6px;">
                        <span style="background:#d1fae5; color:#065f46; font-size:0.7rem; font-weight:600; padding:1px 7px; border-radius:20px;">FBA</span>
                        <span style="color:#374151;"><strong>${fbaTotal}</strong> ÷ <strong>${fbaActiveMonths}</strong> mo × 4</span>
                    </span>
                    <span style="color:#6b7280; font-weight:500; font-size:1rem; margin:0 2px;">→</span>
                    <span style="display:inline-flex; align-items:center; gap:6px;">
                        <span style="background:#d1fae5; color:#065f46; font-size:0.7rem; font-weight:600; padding:1px 7px; border-radius:20px;">Combined</span>
                        <span style="color:#374151;"><strong>${combinedTotal}</strong> ÷ <strong>${combinedActiveMonths}</strong> mo × 4</span>
                        <span style="color:#6b7280;">=</span>
                        <span style="background:#065f46; color:#fff; font-weight:700; padding:3px 12px; border-radius:20px; font-size:0.9rem;">MSL: ${combinedMslStr}</span>
                    </span>
                    ` : `
                    <span style="color:#6b7280; font-size:0.75rem; font-style:italic;">(no FBA data)</span>
                    <span style="color:#6b7280; font-size:1rem; margin:0 2px;">→</span>
                    <span style="background:#d1fae5; color:#065f46; font-weight:700; padding:2px 10px; border-radius:20px; font-size:0.9rem;">MSL: ${shopifyMslStr}</span>
                    `}

                    <span style="color:#9ca3af; font-size:0.73rem;">(M.Avg: ${mAvg > 0 ? mAvg.toFixed(2) : '0'})</span>
                `;
                wrapper.appendChild(formulaEl);
            }

            // FBA section: spans all 12 columns below the main row
            if (fbaMonths && typeof fbaMonths === 'object') {
                const fbaSku = fbaMonths.seller_sku || '';

                // Full-width container spanning all 12 grid columns
                const fbaContainer = document.createElement("div");
                fbaContainer.style.gridColumn = "1 / -1";

                // Divider with FBA label
                const dividerRow = document.createElement("div");
                dividerRow.style.cssText = "display:flex; align-items:center; gap:10px; margin:10px 0 6px;";
                dividerRow.innerHTML = `
                    <hr style="flex:1; border:none; border-top:2px dashed #20c997; margin:0;">
                    <span style="font-size:0.75rem; font-weight:700; color:#20c997; letter-spacing:2px; white-space:nowrap;">FBA</span>
                    <hr style="flex:1; border:none; border-top:2px dashed #20c997; margin:0;">
                `;
                fbaContainer.appendChild(dividerRow);

                if (fbaSku) {
                    const skuLabel = document.createElement("div");
                    skuLabel.style.cssText = "font-size:0.71rem; color:#6c757d; margin-bottom:6px; font-style:italic;";
                    skuLabel.innerText = `SKU: ${fbaSku}`;
                    fbaContainer.appendChild(skuLabel);
                }

                // FBA cards in their own 12-column grid
                const fbaGrid = document.createElement("div");
                fbaGrid.style.cssText = "display:grid; grid-template-columns:repeat(12,1fr); gap:12px;";

                monthOrder.forEach(month => {
                    const value = fbaMonths[month] ?? 0;
                    const monthIndex = monthIndexMap[month];
                    const year = getYearForMonth(monthIndex);

                    const card = document.createElement("div");
                    card.className = "month-card";
                    card.style.borderTop = "2px solid #20c997";

                    const title = document.createElement("div");
                    title.className = "month-title";
                    title.innerText = `${month} ${year}`;

                    const count = document.createElement("div");
                    count.className = "month-value";
                    count.style.color = "#20c997";
                    count.innerText = value;

                    card.appendChild(title);
                    card.appendChild(count);
                    fbaGrid.appendChild(card);
                });

                fbaContainer.appendChild(fbaGrid);
                wrapper.appendChild(fbaContainer);
            }

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

        const COLUMN_VIS_KEY = "tabulator_column_visibility_forecast";

        function saveColumnVisibilityToLocalStorage() {
            /* no-op — column visibility is not persisted across refresh */
        }

        function initColumnModal() {
            const trigger = document.getElementById("hide-column-dropdown");
            const modalEl = document.getElementById("columnCustomizeModal");
            if (!trigger || !modalEl) return;

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
                    const def = col.getDefinition() || {};
                    if (def.hideFromColumnPicker) return '';
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
                table.getColumns().forEach(function(col) {
                    const def = col.getDefinition() || {};
                    if (col.getField() && !def.hideFromColumnPicker) col.show();
                });
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
            /* no-op — column visibility is not persisted across refresh */
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
                const newValue = ['MOQ', 'order_given'].includes(field) ?
                    Number(newValueRaw) :
                    newValueRaw;

                const original = ['MOQ', 'order_given'].includes(field) ?
                    Number(originalValue) :
                    originalValue;

                // Avoid unnecessary updates
                if (newValue === original) return;

                // Numeric validation
                if (['MOQ', 'order_given'].includes(field) && isNaN(newValue)) {
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
                    ['all', '🔁 All'],
                    ['sku', '🔹 SKU'],
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

            currentColorFilter = '';
            setCombinedFilters();
            updateTopRowCounter();

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

                const execEl = document.getElementById('executive-filter');
                if (execEl) {
                    execEl.addEventListener('change', function() {
                        currentExecutiveFilter = String(execEl.value || '').trim();
                        setCombinedFilters();
                    });
                }
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

            document.getElementById('zero-stock-badge-btn')?.addEventListener('click', function() {
                const tbl = Tabulator.findTable("#forecast-table")[0];
                if (invHeaderFilterMode(currentInvFilter) === 'le0') {
                    currentInvFilter = '';
                    if (tbl && typeof tbl.setHeaderFilterValue === 'function') {
                        try { tbl.setHeaderFilterValue('INV', ''); } catch (e) {}
                    }
                } else {
                    currentInvFilter = '<=0';
                    if (tbl && typeof tbl.setHeaderFilterValue === 'function') {
                        try { tbl.setHeaderFilterValue('INV', '<=0'); } catch (e) {}
                    }
                }
                syncZeroStockBadgeActiveState();
                if (typeof setCombinedFilters === 'function') setCombinedFilters();
                saveForecastFilterPrefs();
            });

            // Keep R2S Val badge in sync with Ready to Ship blade (refresh on load, every 60s, and when tab becomes visible)
            function refreshR2sVal() {
                const el = document.getElementById('total_r2s_value_display');
                if (!el) return;
                fetch("{{ route('ready.to.ship.r2s.total') }}", { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (typeof data.value === 'number') {
                            el.textContent = formatBadgeK(data.value);
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
            btn.innerHTML = '<i class="fas fa-download"></i>';
        });

    </script>
@endsection
