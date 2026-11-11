@extends('layouts.vertical', ['title' => 'FBA dispatch', 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }

        /* Vertical column headers */
        .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            white-space: nowrap;
            transform: rotate(180deg);
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 600;
        }

        .tabulator .tabulator-header .tabulator-col {
            height: 80px !important;
        }

        .tabulator .tabulator-header .tabulator-col.tabulator-sortable .tabulator-col-title {
            padding-right: 0px !important;
        }

        /* Size columns to their values; scroll sideways instead of clipping text */
        #fba-table .tabulator {
            font-size: 11px;
        }

        #fba-table .tabulator-col-content {
            padding: 2px 4px !important;
        }

        #fba-table .tabulator-cell {
            padding: 2px 4px !important;
            white-space: nowrap;
            overflow: visible;
        }

        #fba-table .tabulator-header-filter input {
            min-width: 0 !important;
            width: 100% !important;
            padding: 1px 2px;
            font-size: 10px;
        }

        /* Column visibility — 4 groups (Basic / Price / Ads / Other) */
        /* Full width panel pinned to the top of the page (Popper's own positioning is overridden). */
        #column-dropdown-menu.show {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            inset: 0 0 auto 0 !important;
            transform: none !important;
            width: 100vw;
            min-width: 100vw;
            max-width: 100vw;
            max-height: 85vh;
            margin: 0 !important;
            border-radius: 0 0 8px 8px;
            z-index: 2000;
            overflow-y: auto;
            padding: 0.6rem 1rem 0.75rem;
            box-shadow: 0 6px 24px rgba(0, 0, 0, 0.25);
        }
        #column-dropdown-menu > li.col-vis-full {
            list-style: none;
        }
        #column-dropdown-menu .col-vis-groups {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
            gap: 10px;
            list-style: none;
            margin: 0;
            padding: 0;
        }
        #column-dropdown-menu .col-vis-group {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 6px;
            min-height: 120px;
            display: flex;
            flex-direction: column;
        }
        #column-dropdown-menu .col-vis-toolbar { display: flex; gap: 6px; align-items: center; margin-bottom: 8px; flex-wrap: wrap; }
        #column-dropdown-menu .col-vis-toolbar .col-vis-search { flex: 1 1 200px; }
        #column-dropdown-menu .col-vis-toolbar .col-vis-newname { flex: 0 1 170px; }
        #column-dropdown-menu .col-vis-group-custom { background: #f3f0ff; border-color: #d9d0f5; }
        #column-dropdown-menu .col-vis-group-tools { display: flex; gap: 4px; margin-bottom: 6px; }
        #column-dropdown-menu .col-vis-group-tools .col-vis-group-search { flex: 1 1 auto; min-width: 0; font-size: 0.72rem; }
        #column-dropdown-menu .col-vis-group-tools .btn { padding: 0 6px; line-height: 1.6; font-size: 0.7rem; }
        #column-dropdown-menu .col-vis-group-results { border: 1px solid #d9d0f5; border-radius: 4px; background: #fff; margin-bottom: 6px; max-height: 150px; overflow-y: auto; }
        #column-dropdown-menu .col-vis-result { display: flex; align-items: center; gap: 6px; width: 100%; margin: 0; padding: 3px 6px; font-size: 0.78rem; cursor: pointer; }
        #column-dropdown-menu .col-vis-result span { flex: 1 1 auto; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        #column-dropdown-menu .col-vis-result input { margin: 0; flex-shrink: 0; }
        #column-dropdown-menu .col-vis-result-foot { position: sticky; bottom: 0; display: flex; justify-content: space-between; align-items: center; gap: 6px; padding: 4px 6px; background: #f8f9fa; border-top: 1px solid #e9ecef; }
        #column-dropdown-menu .col-vis-result-all { display: flex; align-items: center; gap: 4px; margin: 0; font-size: 0.75rem; cursor: pointer; }
        #column-dropdown-menu .col-vis-result-foot .btn { padding: 0 8px; font-size: 0.72rem; }
        #column-dropdown-menu .col-vis-result:hover { background: #eef5ff; }
        #column-dropdown-menu .col-vis-result small { color: #868e96; white-space: nowrap; }
        #column-dropdown-menu .col-vis-noresult { padding: 4px 6px; font-size: 0.75rem; color: #868e96; }
        #column-dropdown-menu .col-vis-group.col-vis-drop-over {
            border-color: #0d6efd;
            background: #eef5ff;
            box-shadow: inset 0 0 0 1px rgba(13, 110, 253, 0.25);
        }
        #column-dropdown-menu .col-vis-group-title {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #495057;
            margin: 0 0 6px;
            padding: 2px 4px;
            border-bottom: 1px solid #dee2e6;
            user-select: none;
            cursor: pointer;
        }
        #column-dropdown-menu .col-vis-group-title input[type="checkbox"] {
            margin: 0;
            flex-shrink: 0;
            cursor: pointer;
        }
        #column-dropdown-menu .col-vis-group-list {
            flex: 1;
            min-height: 60px;
            max-height: 320px;
            overflow-y: auto;
            margin: 0;
            padding: 0;
            list-style: none;
        }
        #column-dropdown-menu .col-vis-item {
            list-style: none;
            margin: 0;
            padding: 0;
            border-radius: 4px;
            cursor: grab;
        }
        #column-dropdown-menu .col-vis-item:active { cursor: grabbing; }
        #column-dropdown-menu .col-vis-item { display: flex; align-items: center; }
        #column-dropdown-menu .col-vis-item > label { flex: 1 1 auto; min-width: 0; }
        #column-dropdown-menu .col-vis-item.col-vis-selected { background: #dbe7ff; border-radius: 4px; box-shadow: inset 0 0 0 1px #6ea8fe; }
        #column-dropdown-menu .col-vis-group.col-vis-active { box-shadow: 0 0 0 2px #6f42c1; }
        #column-dropdown-menu .col-vis-item.col-vis-drop-before { box-shadow: inset 0 2px 0 0 #0d6efd; }
        #column-dropdown-menu .col-vis-item.col-vis-drop-after { box-shadow: inset 0 -2px 0 0 #0d6efd; }
        #column-dropdown-menu .col-vis-hint { font-size: 0.72rem; margin: -2px 0 8px; }
        #column-dropdown-menu .col-vis-status { color: #198754; font-weight: 600; }
        #column-dropdown-menu .col-vis-item-x { flex: 0 0 auto; border: 0; background: none; color: #dc3545; font-size: 1rem; line-height: 1; padding: 0 6px; cursor: pointer; opacity: 0.55; }
        #column-dropdown-menu .col-vis-item-x:hover { opacity: 1; background: rgba(220, 53, 69, 0.1); border-radius: 3px; }
        #column-dropdown-menu .col-vis-item.col-vis-dragging { opacity: 0.55; }
        #column-dropdown-menu .col-vis-item > label {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 3px 5px;
            cursor: pointer;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin: 0;
            font-size: 0.8rem;
            user-select: none;
        }
        #column-dropdown-menu .col-vis-item > label input[type="checkbox"] {
            margin: 0;
            flex-shrink: 0;
            width: 14px;
            height: 14px;
        }
        #column-dropdown-menu .col-vis-item > label:hover {
            background: rgba(0, 0, 0, 0.04);
            border-radius: 3px;
        }
        @media (max-width: 768px) {
            #column-dropdown-menu .col-vis-groups {
                grid-template-columns: repeat(2, minmax(140px, 1fr));
            }
        }

        /* Red/Green toggle dot for Sugg Send column */
        .send-toggle-dot {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            cursor: pointer;
        }

        .send-toggle-dot.red {
            background-color: #dc3545;
        }

        .send-toggle-dot.green {
            background-color: #28a745;
        }

        /* Red/Green/Yellow toggle dot for Compliance column */
        .compliance-dot {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            cursor: pointer;
        }

        .compliance-dot.red {
            background-color: #dc3545;
        }

        .compliance-dot.green {
            background-color: #28a745;
        }

        .compliance-dot.yellow {
            background-color: #ffc107;
        }

        /* Send Qty column always yellow */
        .send-qty-cell {
            background-color: #ffc107 !important;
        }

        /* Compact history indicator — details appear on hover */
        .history-dot {
            display: inline-block;
            width: 11px;
            height: 11px;
            border-radius: 50%;
            cursor: pointer;
            vertical-align: middle;
        }

        .history-dot.empty {
            background-color: #c5cdd6;
        }

        .history-dot.has {
            background-color: #28a745;
        }

        .history-dot.cleared {
            background-color: #dc3545;
        }

        .history-pop {
            position: fixed;
            z-index: 2000;
            min-width: 200px;
            max-width: 320px;
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
            padding: 8px 10px;
            font-size: 12px;
            line-height: 1.4;
        }

        .history-pop-title {
            font-weight: 700;
            font-size: 11px;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            margin-bottom: 4px;
        }

        .history-pop-list {
            max-height: 160px;
            overflow-y: auto;
            margin-bottom: 4px;
        }

        .barcode-label {
            font-weight: 700;
            cursor: default;
        }

        .barcode-label.bar {
            color: #28a745;
        }

        .barcode-label.asin {
            color: #b8860b;
        }

        .barcode-label.verify {
            color: #dc3545;
        }

        .barcode-label:hover {
            text-decoration: underline;
        }

        .s-status-dot {
            display: inline-block;
            width: 11px;
            height: 11px;
            border-radius: 50%;
            vertical-align: middle;
        }

        .s-status-dot.good {
            background-color: #28a745;
        }

        .s-status-dot.bad {
            background-color: #dc3545;
        }

        /* Ensure all modals have proper z-index */
        .modal {
            z-index: 1050 !important;
        }
        .modal.show {
            z-index: 1050 !important;
        }
        .modal-backdrop {
            z-index: 1040 !important;
        }
        .modal-backdrop.show {
            z-index: 1040 !important;
        }
        /* Ensure missing fields modal is visible */
        #missingFieldsModal {
            z-index: 1060 !important;
        }
        #missingFieldsModal .modal-dialog {
            z-index: 1061 !important;
        }
        /* Fix modal stacking issues */
        body.modal-open {
            overflow: hidden;
        }
        body.modal-open .modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }
        @include('partials.lmp-ignore', ['lmpIgnorePart' => 'css'])
    </style>
@endsection
@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@endsection

@section('content')
    <div class="toast-container"></div>

    <!-- Send Qty Rule Modal (full width, slides in from the top) -->
    <style>
        #sendQtyRuleModal .modal-dialog { max-width: 100%; width: 100%; margin: 0; }
        #sendQtyRuleModal.fade .modal-dialog { transform: translateY(-100%); transition: transform .32s cubic-bezier(.2,.8,.2,1); }
        #sendQtyRuleModal.show .modal-dialog { transform: none; }
        #sendQtyRuleModal .modal-content { border: 0; border-radius: 0 0 22px 22px; overflow: hidden; box-shadow: 0 24px 70px rgba(40, 30, 110, .35); }
        #sendQtyRuleModal .sqr-header { background: linear-gradient(120deg, #4f46e5 0%, #7c3aed 55%, #db2777 100%); color: #fff; padding: 18px 28px; display: flex; align-items: center; gap: 14px; }
        #sendQtyRuleModal .sqr-header .sqr-icon { width: 44px; height: 44px; border-radius: 14px; background: rgba(255,255,255,.18); display: flex; align-items: center; justify-content: center; font-size: 20px; }
        #sendQtyRuleModal .sqr-header h5 { margin: 0; font-weight: 700; letter-spacing: .01em; }
        #sendQtyRuleModal .sqr-header small { opacity: .85; }
        #sendQtyRuleModal .sqr-body { padding: 24px 28px; background: #f8f9fe; }
        #sendQtyRuleModal .sqr-input-wrap { position: relative; }
        #sendQtyRuleModal .sqr-status { min-height: 26px; margin-top: 10px; font-size: .95rem; font-weight: 600; }
        #sendQtyRuleModal .sqr-status.ok { color: #15803d; }
        #sendQtyRuleModal .sqr-status.bad { color: #dc2626; }
        #sendQtyRuleModal .sqr-status.idle { color: #6b7280; font-weight: 500; }
        #sendQtyRuleModal .sqr-card { background: #fff; border-radius: 16px; padding: 16px 18px; height: 100%; box-shadow: 0 2px 12px rgba(30,20,80,.06); }
        #sendQtyRuleModal .sqr-card h6 { font-size: .72rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: #6b7280; margin-bottom: 10px; }
        #sendQtyRuleModal .sqr-chip { border: 0; border-radius: 999px; padding: 6px 14px; font-size: .85rem; font-weight: 600; cursor: pointer; transition: transform .1s, box-shadow .15s; margin: 0 6px 8px 0; }
        #sendQtyRuleModal .sqr-chip:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(79,70,229,.25); }
        #sendQtyRuleModal .sqr-example { background: #ecfdf5; color: #047857; }
        #sendQtyRuleModal .sqr-footer { background: #fff; padding: 16px 28px; display: flex; align-items: center; gap: 10px; border-top: 1px solid #eceefb; }
        #sendQtyRuleModal .sqr-apply { background: linear-gradient(120deg, #4f46e5, #7c3aed); border: 0; color: #fff; font-weight: 700; padding: 10px 30px; border-radius: 12px; box-shadow: 0 6px 18px rgba(124,58,237,.35); }
        #sendQtyRuleModal .sqr-apply:hover { filter: brightness(1.08); color: #fff; }
        #sendQtyRuleModal .sqr-table { margin: 0; }
        #sendQtyRuleModal .sqr-table thead th { background: #f1f2fd; color: #6b7280; font-size: .72rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; border: 0; padding: 12px 10px; }
        #sendQtyRuleModal .sqr-table td { padding: 10px; border-color: #eef0fb; vertical-align: middle; }
        #sendQtyRuleModal .sqr-table .form-select, #sendQtyRuleModal .sqr-table .form-control { border-radius: 10px; border: 2px solid #e1e4f7; font-weight: 600; }
        #sendQtyRuleModal .sqr-table .form-select:focus, #sendQtyRuleModal .sqr-table .form-control:focus { border-color: #7c3aed; box-shadow: 0 0 0 3px rgba(124,58,237,.15); }
        #sendQtyRuleModal .sqr-table .form-control.is-invalid { border-color: #ef4444; background-image: none; }
        #sendQtyRuleModal .sqr-if { display: inline-block; font-size: .72rem; font-weight: 800; letter-spacing: .08em; color: #7c3aed; background: #f3e8ff; border-radius: 8px; padding: 6px 12px; }
        #sendQtyRuleModal .sqr-table .sqr-join { min-width: 92px; }
        #sendQtyRuleModal .sqr-table .sqr-field { min-width: 200px; }
        #sendQtyRuleModal .sqr-table .sqr-pick { min-width: 190px; width: 100%; text-align: left; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; cursor: pointer; background-color: #fff; }
        #sendQtyRuleModal .sqr-picker { position: fixed; z-index: 3000; background: #fff; border: 1px solid #c7d2fe; border-radius: 12px; box-shadow: 0 18px 40px rgba(30, 27, 75, .25); padding: 8px; }
        #sendQtyRuleModal .sqr-picker-list { max-height: 300px; overflow-y: auto; margin-top: 6px; }
        #sendQtyRuleModal .sqr-picker-group { font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #6b7280; padding: 6px 8px 2px; }
        #sendQtyRuleModal .sqr-picker-item { padding: 6px 10px; border-radius: 8px; font-size: 13px; cursor: pointer; }
        #sendQtyRuleModal .sqr-picker-item.is-active { background: #4f46e5; color: #fff; }
        #sendQtyRuleModal .sqr-picker-item.is-selected:not(.is-active) { background: #eef2ff; font-weight: 600; }
        #sendQtyRuleModal .sqr-picker-empty { padding: 10px; color: #6b7280; font-size: 13px; }
        #sendQtyRuleModal .sqr-table .sqr-vtype { min-width: 120px; }
        #sendQtyRuleModal .sqr-table .sqr-valcol { min-width: 190px; }
        #sendQtyRuleModal .sqr-table .sqr-op { font-family: ui-monospace, Menlo, Consolas, monospace; min-width: 190px; }
        #sendQtyRuleModal .sqr-count { font-size: 1rem; white-space: nowrap; }
        #sendQtyRuleModal .sqr-count b { color: #4338ca; font-size: 1.1rem; }
        #sendQtyRuleModal .sqr-add { border: 2px dashed #c4b5fd; color: #6d28d9; background: #faf5ff; border-radius: 12px; font-weight: 700; padding: 8px 18px; }
        #sendQtyRuleModal .sqr-add:hover { background: #f3e8ff; color: #5b21b6; }
        #sendQtyRuleModal .sqr-preview { font-family: ui-monospace, Menlo, Consolas, monospace; background: #1e1b4b; color: #c7d2fe; border-radius: 12px; padding: 10px 16px; font-size: .95rem; word-break: break-word; }
    </style>
    <div class="modal fade" id="sendQtyRuleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="sqr-header">
                    <div class="sqr-icon"><i class="fa fa-wand-magic-sparkles"></i></div>
                    <div class="flex-grow-1">
                        <h5>Send Qty Rule</h5>
                        <small>Auto-fill Send Qty with Qty CTN whenever this rule is true. Type any condition, it is understood automatically.</small>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="sqr-body">
                    <div class="sqr-card p-0" style="overflow:hidden;">
                        <table class="table sqr-table align-middle">
                            <thead>
                                <tr>
                                    <th style="width:48px;">#</th>
                                    <th style="width:100px;">Join</th>
                                    <th style="min-width:220px;">Column</th>
                                    <th style="width:210px;">Condition</th>
                                    <th style="width:130px;">Compare with</th>
                                    <th style="width:170px;">Value</th>
                                    <th style="width:120px;" class="text-end">Count</th>
                                    <th style="width:64px;"></th>
                                </tr>
                            </thead>
                            <tbody id="sqr-rows"></tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="6" style="padding:14px 10px;">
                                        <button type="button" class="btn sqr-add" id="sqr-add"><i class="fa fa-plus me-1"></i> Add condition</button>
                                        <span class="text-muted small d-block mt-2">AND is checked before OR. Count = rows (with a Qty CTN) that match that line.</span>
                                    </td>
                                    <td class="text-end fw-bold" id="sqr-total-count" style="font-size:1.05rem;"></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <div id="send-qty-rule-status" class="sqr-status idle">Add a condition, e.g. Dil &lt; 50</div>
                    <div class="sqr-preview" id="sqr-preview"></div>
                    <div class="mt-3">
                        <h6 class="text-uppercase text-muted fw-bold" style="font-size:.72rem;letter-spacing:.08em;">Quick rules</h6>
                        <button type="button" class="sqr-chip sqr-example send-qty-rule-example" data-rule="Dil &lt; 50">Dil &lt; 50</button>
                        <button type="button" class="sqr-chip sqr-example send-qty-rule-example" data-rule="Dil &lt; 30 and Main INV &gt; 0">Dil &lt; 30 and Main INV &gt; 0</button>
                        <button type="button" class="sqr-chip sqr-example send-qty-rule-example" data-rule="Dil &lt; 50 and AMZ Rating &gt;= 3.5">Dil &lt; 50 and AMZ Rating &gt;= 3.5</button>
                        <button type="button" class="sqr-chip sqr-example send-qty-rule-example" data-rule="Dil &lt; 50 or Qty CTN &gt;= 100">Dil &lt; 50 or Qty CTN &gt;= 100</button>
                    </div>
                    <div class="small text-muted mt-2">A Send Qty you typed yourself is never replaced by the rule.</div>
                </div>
                <div class="sqr-footer">
                    <button type="button" class="btn btn-outline-secondary" id="send-qty-rule-default">Default (Dil &lt; 50)</button>
                    <span class="ms-auto"></span>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn sqr-apply" id="send-qty-rule-apply"><i class="fa fa-check me-1"></i> Apply</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Row Details Modal -->
    <div class="modal fade" id="rowDetailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fa fa-search me-2"></i>Row Details<span id="rowDetailsSku" class="ms-2 text-muted"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <table class="table table-sm table-striped table-bordered mb-0">
                        <tbody id="rowDetailsBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2">
                            <h4 class="mb-0">FBA dispatch</h4>
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span id="sugg-send-rows-badge" class="badge bg-primary me-2 d-inline-flex align-items-center" style="font-size: 0.91875rem; padding: 0.3rem 0.6rem; line-height: 1.5;">
                                <i class="fa fa-list me-1"></i> <strong>Send:</strong>
                                <select id="sugg-send-filter" class="form-select form-select-sm mx-2" style="width: auto; display: inline-block;"
                                    title="Filter the Send Qty column: All, Blank (empty) or greater than 0">
                                    <option value="all" selected>All</option>
                                    <option value="blank">Blank</option>
                                    <option value="positive">&gt;0</option>
                                </select>
                                <span id="sugg-send-rows-count">0</span>
                            </span>
                            <input type="text" id="sku-search" class="form-control form-control-sm me-2"
                                style="width: 200px; display: inline-block;" placeholder="Search SKU or FBA SKU...">
                            <input type="text" id="parent-search" class="form-control form-control-sm me-2"
                                style="width: 180px; display: inline-block;" placeholder="Search Parent...">
                            <button type="button" id="send-qty-rule-btn" class="btn btn-sm btn-info me-2 text-white"
                                data-bs-toggle="modal" data-bs-target="#sendQtyRuleModal"
                                title="Rule that decides when Send Qty is auto-filled with Qty CTN">
                                <i class="fa fa-sliders-h"></i> Send Qty Rule
                            </button>
                            <select id="nrl-fba-filter" class="form-select form-select-sm me-2"
                                style="width: auto; display: inline-block;">
                                <option value="all">All Types</option>
                                <option value="FBA" selected>FBA</option>
                                <option value="FBM">FBM</option>
                                <option value="NRL">NRL</option>
                                <option value="Both">Both</option>
                            </select>
                            <a href="{{ url('/fba-manual-export') }}" class="btn btn-sm btn-success me-2">
                                <i class="fa fa-download"></i>
                            </a>
                            <div class="btn-group me-2" role="group">
                                <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle"
                                    id="columnVisibilityDropdown" title="Columns"
                                    data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                                    <i class="fa fa-columns"></i>
                                </button>
                                <ul class="dropdown-menu" id="column-dropdown-menu" aria-labelledby="columnVisibilityDropdown"></ul>
                            </div>
                            <button id="reset-all-filters-btn" type="button" class="btn btn-sm btn-warning me-2">
                                <i class="fa fa-filter-circle-xmark"></i> all
                            </button>
                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal"
                                data-bs-target="#importModal">
                                <i class="fa fa-upload"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <div id="fba-table-wrapper"
                            style="height: calc(100vh - 200px); display: flex; flex-direction: column;">

                            <!--Table body (scrollable section) -->
                            <div id="fba-table" style="flex: 1;"></div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Inv age Modal -->
    <div class="modal fade" id="invageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Inv age Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p><strong>SKU:</strong> <span id="modalSKU"></span></p>
                    <p><strong>Inv age:</strong> <span id="modalInvage"></span></p>
                </div>
            </div>
        </div>
    </div>

    <!-- SKU Metrics Chart Modal -->
    <div class="modal fade" id="skuMetricsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Metrics Chart for <span id="modalSkuName"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Date Range:</label>
                        <select id="sku-chart-days-filter" class="form-select form-select-sm"
                            style="width: auto; display: inline-block;">
                            <option value="7" selected>Last 7 Days</option>
                            <option value="14">Last 14 Days</option>
                            <option value="30">Last 30 Days</option>
                        </select>
                    </div>
                    <div id="chart-no-data-message" class="alert alert-info" style="display: none;">
                        No historical data available for this SKU. Data will appear after running the metrics collection
                        command.
                    </div>
                    <div style="height: 400px;">
                        <canvas id="skuMetricsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Import Modal -->
    <div class="modal fade" id="importModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Import FBA Manual Data</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="importForm">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="csvFile" class="form-label">Choose CSV File</label>
                            <input type="file" class="form-control" id="csvFile" name="file" accept=".csv"
                                required>
                        </div>
                        <small class="text-muted">
                            <i class="fa fa-info-circle"></i> CSV must have: SKU, Length, Width, Height, Weight, Qty in
                            each box, Total
                            qty Sent, Total Send Cost, Inbound qty, Send cost, Commission Percentage, S Price, Ratings,
                            Shipment Track Status
                        </small>
                    </div>
                    <div class="modal-footer">
                        <a href="{{ url('/fba-manual-sample') }}" class="btn btn-info me-auto">
                            <i class="fa fa-download"></i> Sample Template
                        </a>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="uploadBtn">Upload & Import</button>
                    </div>
                </form>
            </div>
        </div>
        <!-- LMP Modal -->
        <div class="modal fade" id="lmpModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">LMP Data for <span id="lmpSku"></span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div id="lmpDataList"></div>
                    </div>
                </div>
            </div>
        </div>
    <!-- Monthly Sales Modal (Jan-Dec) -->
    <div class="modal fade" id="monthlySalesModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered" style="max-width:900px;">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Monthly Sales for <span id="monthlyModalSku"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="monthlySalesModalBody" style="min-width:300px; overflow-x:auto;">
                    <!-- Content populated by JS -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Missing Fields Modal -->
    <div class="modal fade" id="missingFieldsModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Missing Fields for <span id="missingFieldsSku"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2"><strong>Please fill the following fields:</strong></p>
                    <ul id="missingFieldsList" class="list-group list-group-flush">
                        <!-- Fields will be populated by JS -->
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- CTN Details Modal -->
    <div class="modal fade" id="ctnColumnsModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">CTN Details for <span id="ctnColumnsSku"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <table class="table table-sm table-bordered mb-0">
                        <tbody id="ctnColumnsBody"></tbody>
                    </table>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- UPC Edit Modal -->
    <div class="modal fade" id="upcEditModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">UPC Code for <span id="upcEditSku"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <small class="text-muted">UPC Company: <strong id="upcEditCompany">-</strong></small>
                    </div>
                    <label for="upcEditInput" class="form-label">UPC Code</label>
                    <input type="text" class="form-control" id="upcEditInput" placeholder="Enter UPC code" autocomplete="off">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="upcEditSaveBtn">Save</button>
                </div>
            </div>
        </div>
    </div>
    @endsection

    @section('script-bottom')
        <script>
            @include('partials.lmp-ignore', ['lmpIgnorePart' => 'script'])
            // SKU-specific chart
            let skuMetricsChart = null;
            let currentSku = null;

            function initSkuMetricsChart() {
                const ctx = document.getElementById('skuMetricsChart').getContext('2d');
                skuMetricsChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: [],
                        datasets: [{
                                label: 'Price (USD)',
                                data: [],
                                borderColor: '#FF0000',
                                backgroundColor: 'rgba(255, 0, 0, 0.1)',
                                borderWidth: 2,
                                pointRadius: 4,
                                pointHoverRadius: 6,
                                yAxisID: 'y',
                                tension: 0.4
                            },
                            {
                                label: 'Views',
                                data: [],
                                borderColor: '#0000FF',
                                backgroundColor: 'rgba(0, 0, 255, 0.1)',
                                borderWidth: 2,
                                pointRadius: 4,
                                pointHoverRadius: 6,
                                yAxisID: 'y',
                                tension: 0.4
                            },
                            {
                                label: 'CVR%',
                                data: [],
                                borderColor: '#008000',
                                backgroundColor: 'rgba(0, 128, 0, 0.1)',
                                borderWidth: 2,
                                pointRadius: 4,
                                pointHoverRadius: 6,
                                yAxisID: 'y1',
                                tension: 0.4
                            },
                            {
                                label: 'TACOS%',
                                data: [],
                                borderColor: '#FFD700',
                                backgroundColor: 'rgba(255, 215, 0, 0.1)',
                                borderWidth: 2,
                                pointRadius: 4,
                                pointHoverRadius: 6,
                                yAxisID: 'y1',
                                tension: 0.4
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            mode: 'index',
                            intersect: false,
                        },
                        plugins: {
                            legend: {
                                position: 'top',
                                labels: {
                                    usePointStyle: true,
                                    padding: 15,
                                    font: {
                                        size: 12
                                    }
                                }
                            },
                            title: {
                                display: true,
                                text: 'FBA SKU Metrics',
                                font: {
                                    size: 16,
                                    weight: 'bold'
                                },
                                padding: {
                                    top: 10,
                                    bottom: 20
                                }
                            },
                            tooltip: {
                                enabled: true,
                                mode: 'index',
                                intersect: false,
                                callbacks: {
                                    label: function(context) {
                                        let label = context.dataset.label || '';
                                        let value = context.parsed.y || 0;

                                        if (label.includes('Price')) {
                                            return label + ': $' + value.toFixed(2);
                                        } else if (label.includes('Views')) {
                                            return label + ': ' + value.toLocaleString();
                                        } else if (label.includes('CVR')) {
                                            return label + ': ' + value.toFixed(1) + '%';
                                        } else if (label.includes('TACOS')) {
                                            return label + ': ' + Math.round(value) + '%';
                                        } else if (label.includes('%')) {
                                            return label + ': ' + value.toFixed(2) + '%';
                                        }
                                        return label + ': ' + value;
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                title: {
                                    display: true,
                                    text: 'Date',
                                    font: {
                                        size: 12,
                                        weight: 'bold'
                                    }
                                },
                                ticks: {
                                    font: {
                                        size: 11
                                    }
                                }
                            },
                            y: {
                                type: 'linear',
                                display: true,
                                position: 'left',
                                title: {
                                    display: true,
                                    text: 'Price/Views',
                                    font: {
                                        size: 12,
                                        weight: 'bold'
                                    }
                                },
                                beginAtZero: true,
                                ticks: {
                                    font: {
                                        size: 11
                                    },
                                    callback: function(value, index, values) {
                                        if (values.length > 0 && Math.max(...values.map(v => v.value)) < 1000) {
                                            return '$' + value.toFixed(0);
                                        }
                                        return value.toLocaleString();
                                    }
                                }
                            },
                            y1: {
                                type: 'linear',
                                display: true,
                                position: 'right',
                                title: {
                                    display: true,
                                    text: 'Percent (%)',
                                    font: {
                                        size: 12,
                                        weight: 'bold'
                                    }
                                },
                                beginAtZero: true,
                                grid: {
                                    drawOnChartArea: false,
                                },
                                ticks: {
                                    font: {
                                        size: 11
                                    },
                                    callback: function(value) {
                                        return value.toFixed(0) + '%';
                                    }
                                }
                            }
                        }
                    }
                });
            }

            function loadSkuMetricsData(sku, days = 7) {
                console.log('Loading metrics data for SKU:', sku, 'Days:', days);
                fetch(`/fba-metrics-history?days=${days}&sku=${encodeURIComponent(sku)}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log('Metrics data received:', data);
                        if (skuMetricsChart) {
                            if (!data || data.length === 0) {
                                console.warn('No data returned for SKU:', sku);
                                $('#chart-no-data-message').show();
                                skuMetricsChart.data.labels = [];
                                skuMetricsChart.data.datasets.forEach(dataset => {
                                    dataset.data = [];
                                });
                                skuMetricsChart.options.plugins.title.text = 'FBA Metrics';
                                skuMetricsChart.update();
                                return;
                            }

                            $('#chart-no-data-message').hide();
                            skuMetricsChart.options.plugins.title.text = `FBA Metrics (${days} Days)`;
                            skuMetricsChart.data.labels = data.map(d => d.date_formatted || d.date || '');
                            skuMetricsChart.data.datasets[0].data = data.map(d => d.price || 0);
                            skuMetricsChart.data.datasets[1].data = data.map(d => d.views || 0);
                            skuMetricsChart.data.datasets[2].data = data.map(d => d.cvr_percent || 0);
                            skuMetricsChart.data.datasets[3].data = data.map(d => d.tacos_percent || 0);
                            skuMetricsChart.update('active');
                            console.log('Chart updated successfully with', data.length, 'data points');
                        }
                    })
                    .catch(error => {
                        console.error('Error loading SKU metrics data:', error);
                        alert('Error loading metrics data. Please check console for details.');
                    });
            }

            // Toast notification function
            function showToast(message, type = 'info') {
                const toastContainer = document.querySelector('.toast-container');
                if (!toastContainer) return;
                
                const toast = document.createElement('div');
                toast.className = `toast align-items-center text-white bg-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info'} border-0`;
                toast.setAttribute('role', 'alert');
                toast.setAttribute('aria-live', 'assertive');
                toast.setAttribute('aria-atomic', 'true');
                toast.innerHTML = `
                    <div class="d-flex">
                        <div class="toast-body">${message}</div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                `;
                toastContainer.appendChild(toast);
                const bsToast = new bootstrap.Toast(toast);
                bsToast.show();
                toast.addEventListener('hidden.bs.toast', () => toast.remove());
            }

            $(document).ready(function() {
                const currentUser = @json(auth()->check() ? (auth()->user()->name ?? 'User') : 'User');

                // Parse Send History (stored as JSON string or array)
                function parseSendHistory(v) {
                    if (!v) return [];
                    if (Array.isArray(v)) return v;
                    try {
                        const parsed = JSON.parse(v);
                        return Array.isArray(parsed) ? parsed : [];
                    } catch (e) {
                        return [];
                    }
                }

                // Append a history entry (user + date + action) for a row and persist it
                function appendSendHistory(row, action) {
                    const data = row.getData();
                    const history = parseSendHistory(data.Send_History);
                    const dateStr = new Date().toLocaleString();
                    history.push({ user: currentUser, date: dateStr, action: action });

                    row.update({ Send_History: history });

                    $.ajax({
                        url: '/update-fba-manual-data',
                        method: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}',
                            sku: data.FBA_SKU || data.SKU,
                            field: 'send_history',
                            value: JSON.stringify(history)
                        },
                        error: function(xhr) {
                            console.error('Error saving Send History:', xhr.responseText);
                            showToast('Failed to save history', 'error');
                        }
                    });
                }

                // Show data for a row in a modal — only columns currently visible in the table
                function showRowDetailsModal(rowData) {
                    const body = document.getElementById('rowDetailsBody');
                    body.innerHTML = '';

                    const skuLabel = rowData.FBA_SKU || rowData.SKU || '';
                    document.getElementById('rowDetailsSku').textContent = skuLabel ? ` — ${skuLabel}` : '';

                    const skipFields = ['_details', '_select', '_accept', 'is_parent'];

                    table.getColumns().forEach(col => {
                        if (!col.isVisible()) return; // skip hidden columns
                        const def = col.getDefinition();
                        const field = def.field;
                        if (!field || skipFields.includes(field)) return;

                        const title = (def.title || field).replace(/<[^>]*>/g, '').trim() || field;
                        let value = rowData[field];

                        if (value === null || value === undefined || value === '') {
                            value = '<span class="text-muted">—</span>';
                        } else if (typeof value === 'object') {
                            value = `<code>${JSON.stringify(value)}</code>`;
                        } else {
                            value = String(value).replace(/<[^>]*>/g, '').trim() || '<span class="text-muted">—</span>';
                        }

                        const tr = document.createElement('tr');
                        tr.innerHTML = `<th style="width:35%; white-space:nowrap;">${title}</th><td>${value}</td>`;
                        body.appendChild(tr);
                    });

                    const modalEl = document.getElementById('rowDetailsModal');
                    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                    modal.show();
                }

                // Fix modal backdrop z-index issues globally
                $(document).on('shown.bs.modal', '.modal', function() {
                    $(this).css('z-index', '1050');
                    $('.modal-backdrop').not('.stacked').css('z-index', '1040').addClass('stacked');
                });
                
                $(document).on('hidden.bs.modal', '.modal', function() {
                    // Remove backdrop if no other modals are open
                    if ($('.modal.show').length === 0) {
                        $('.modal-backdrop').remove();
                    }
                });
                
                // Initialize SKU metrics chart
                initSkuMetricsChart();

                // SKU chart days filter
                $('#sku-chart-days-filter').on('change', function() {
                    const days = $(this).val();
                    if (currentSku) {
                        if (skuMetricsChart) {
                            skuMetricsChart.options.plugins.title.text = `FBA Metrics (${days} Days)`;
                            skuMetricsChart.update();
                        }
                        loadSkuMetricsData(currentSku, days);
                    }
                });

                // Event delegation for eye button clicks
                $(document).on('click', '.view-sku-chart', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const sku = $(this).data('sku');
                    currentSku = sku;
                    $('#modalSkuName').text(sku);
                    $('#sku-chart-days-filter').val('7');
                    $('#chart-no-data-message').hide();
                    loadSkuMetricsData(sku, 7);
                    
                    // Fix modal z-index and backdrop issues
                    const modal = $('#skuMetricsModal');
                    modal.appendTo('body');
                    modal.css('z-index', '1050');
                    modal.modal('show');
                    
                    // Ensure backdrop is properly configured
                    $('.modal-backdrop').css('z-index', '1040');
                });

                // ===== Send Qty: auto-filled with Qty CTN when the rule typed in the toolbar is true =====
                // Rule examples: "Dil < 50"   "Dil < 30 and Main INV > 0"   "Dil < 50 or Qty CTN >= 100"
                // Column names are the header names or field names (case / spaces / % ignored). A saved Send Qty value always wins.
                const FBA_SEND_QTY_RULE_KEY = 'fba_send_qty_rule_v1';
                const FBA_SEND_QTY_DEFAULT_RULE = 'Dil < 50';
                let fbaSendQtyRuleText = '';
                let fbaSendQtyRuleFn = null;
                let fbaSendQtyRuleLabel = '';       // the rule written with column titles (for tooltips)

                function fbaRuleKey(s) { return String(s == null ? '' : s).toLowerCase().replace(/[^a-z0-9]/g, ''); }
                function fbaRuleNum(v) {
                    if (v === null || v === undefined || v === '') return 0;
                    if (v === true) return 1;
                    const n = parseFloat(String(v).replace(/[%,\s]/g, ''));
                    return isNaN(n) ? 0 : n;
                }
                function fbaRuleColumnMap() {
                    const map = {};
                    table.getColumns().forEach(function(col) {
                        const def = col.getDefinition();
                        if (!def.field) return;
                        map[fbaRuleKey(def.field)] = def.field;
                        const title = String(def.title == null ? '' : def.title).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
                        if (title && !map[fbaRuleKey(title)]) map[fbaRuleKey(title)] = def.field;
                    });
                    return map;
                }
                /** Turn the typed rule into a function(rowData) -> boolean. Throws Error(message) when the rule is not understood. */
                function fbaCompileRule(text) {
                    const src = String(text || '').trim();
                    if (!src) throw new Error('Rule is empty');
                    const colMap = fbaRuleColumnMap();
                    const re = /\s*(\(|\)|<=|>=|==|!=|=|<|>|&&|\|\||!|-?[A-Za-z0-9_.%\/]+)/y;
                    const tokens = [];
                    let pos = 0, m;
                    while (pos < src.length) {
                        re.lastIndex = pos;
                        m = re.exec(src);
                        if (!m) {
                            if (/^\s*$/.test(src.slice(pos))) break;
                            throw new Error('Unexpected "' + src.slice(pos).trim().charAt(0) + '"');
                        }
                        tokens.push(m[1]);
                        pos = re.lastIndex;
                    }
                    let i = 0;
                    const lower = function(t) { return t === undefined ? '' : String(t).toLowerCase(); };
                    const isWord = function(t) { return t !== undefined && /^-?[A-Za-z0-9_.%\/]+$/.test(t) && ['and', 'or', 'not'].indexOf(lower(t)) === -1; };

                    function parseValue() {
                        const t = tokens[i];
                        if (t === '(') {
                            i++;
                            const inner = parseOr();
                            if (tokens[i] !== ')') throw new Error('Missing )');
                            i++;
                            return { type: 'bool', fn: inner };
                        }
                        if (!isWord(t)) throw new Error(t === undefined ? 'Rule ends too early' : 'Unexpected "' + t + '"');
                        if (/^-?\d+(\.\d+)?%?$/.test(t)) {
                            i++;
                            const n = parseFloat(t);
                            return { type: 'num', fn: function() { return n; } };
                        }
                        const words = [];
                        while (isWord(tokens[i]) && !/^-?\d+(\.\d+)?%?$/.test(tokens[i])) { words.push(tokens[i]); i++; }
                        const name = words.join(' ');
                        const field = colMap[fbaRuleKey(name)];
                        if (!field) throw new Error('Unknown column "' + name + '"');
                        return { type: 'num', fn: function(row) { return fbaRuleNum(row[field]); } };
                    }
                    function parseCmp() {
                        const left = parseValue();
                        const op = tokens[i];
                        if (['<', '<=', '>', '>=', '=', '==', '!='].indexOf(op) !== -1) {
                            i++;
                            const right = parseValue();
                            return function(row) {
                                const a = left.fn(row), b = right.fn(row);
                                switch (op) {
                                    case '<': return a < b;
                                    case '<=': return a <= b;
                                    case '>': return a > b;
                                    case '>=': return a >= b;
                                    case '!=': return a !== b;
                                    default: return a === b;
                                }
                            };
                        }
                        return function(row) { return !!left.fn(row); };
                    }
                    function parseNot() {
                        if (lower(tokens[i]) === 'not' || tokens[i] === '!') { i++; const f = parseNot(); return function(row) { return !f(row); }; }
                        return parseCmp();
                    }
                    function parseAnd() {
                        let f = parseNot();
                        while (lower(tokens[i]) === 'and' || tokens[i] === '&&') {
                            i++;
                            const a = f, b = parseNot();
                            f = function(row) { return a(row) && b(row); };
                        }
                        return f;
                    }
                    function parseOr() {
                        let f = parseAnd();
                        while (lower(tokens[i]) === 'or' || tokens[i] === '||') {
                            i++;
                            const a = f, b = parseAnd();
                            f = function(row) { return a(row) || b(row); };
                        }
                        return f;
                    }
                    const fn = parseOr();
                    if (i < tokens.length) throw new Error('Unexpected "' + tokens[i] + '"');
                    return fn;
                }
                function fbaSendQtyEnsureRule() {
                    if (fbaSendQtyRuleFn) return true;
                    try {
                        let text = FBA_SEND_QTY_DEFAULT_RULE;
                        try { text = localStorage.getItem(FBA_SEND_QTY_RULE_KEY) || text; } catch (e) { /* ignore */ }
                        try {
                            fbaSendQtyRuleFn = fbaCompileRule(text);
                            fbaSendQtyRuleText = text;
                        } catch (e) {
                            fbaSendQtyRuleFn = fbaCompileRule(FBA_SEND_QTY_DEFAULT_RULE);
                            fbaSendQtyRuleText = FBA_SEND_QTY_DEFAULT_RULE;
                        }
                        const input = document.getElementById('send-qty-rule');
                        if (input) input.value = fbaSendQtyRuleText;
                        return true;
                    } catch (e) {
                        return false;      // table is not ready yet
                    }
                }
                /** { value, manual } shown in Send Qty: a saved value wins, else Qty CTN when the rule is true. null = blank. */
                function fbaSendQtyEffective(data) {
                    const saved = data.Send_Qty;
                    if (saved !== null && saved !== undefined && String(saved).trim() !== '') return { value: saved, manual: true };
                    if (!fbaSendQtyEnsureRule()) return null;
                    const q = data.Quantity_in_each_box;
                    if (q === null || q === undefined || String(q).trim() === '') return null;
                    let ok = false;
                    try { ok = !!fbaSendQtyRuleFn(data); } catch (e) { ok = false; }
                    return ok ? { value: q, manual: false } : null;
                }

                /** True when the Send Qty column shows a number above 0 (typed by the user or auto-filled by the rule). */
                function fbaSendQtyPositive(data) {
                    const eff = fbaSendQtyEffective(data);
                    if (!eff) return false;
                    const n = parseFloat(String(eff.value).replace(/,/g, ''));
                    return !isNaN(n) && n > 0;
                }

                // L / W / H CTN colour: below the limit green, the limit and above red.
                // L CTN limit 36; W CTN and H CTN limit 25.
                function ctnDimensionFormatter(limit) {
                    return function(cell) {
                        const raw = cell.getValue();
                        const text = (raw === null || raw === undefined) ? '' : String(raw);
                        const esc = text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                        if (cell.getRow().getData().is_parent || text.trim() === '') return esc;
                        const n = parseFloat(text);
                        if (isNaN(n)) return esc;
                        const color = n < limit ? '#00b341' : '#ff0000';
                        return `<span style="color:${color};font-weight:800;">${esc}</span>`;
                    };
                }

                const table = new Tabulator("#fba-table", {
                    ajaxURL: "/fba-data-json",
                    layout: "fitDataStretch",
                    layoutColumnsOnNewData: true,
                    movableColumns: true,
                    editTriggerEvent: "dblclick",
                    pagination: true,
                    paginationSize: 50,
                    rowFormatter: function(row) {
                        if (row.getData().is_parent) {
                            row.getElement().classList.add("parent-row");
                        }
                    },
                    columns: [{
                            title: "Parent",
                            field: "Parent",
                            headerFilter: "input",
                            headerFilterPlaceholder: "Search Parent...",
                            cssClass: "text-primary",
                            tooltip: true,
                            frozen: true,
                            visible: false
                        },
                        {
                            title: "Child SKU",
                            field: "SKU",
                            headerFilter: "input",
                            headerFilterPlaceholder: "Search SKU...",
                            cssClass: "font-weight-bold",
                            tooltip: true,
                            frozen: true,
                            visible: false
                        },
                        {
                            title: "Image",
                            field: "image_path",
                            headerSort: false,
                            hozAlign: "center",
                            frozen: true,
                            width: 44,
                            minWidth: 44,
                            widthGrow: 0,
                            widthShrink: 0,
                            formatter: function(cell) {
                                const rowData = cell.getRow().getData();
                                if (rowData.is_parent) return '';
                                const value = (cell.getValue() || '').toString().trim();
                                if (!value) return '';
                                const src = value.replace(/"/g, '&quot;');
                                return `<img src="${src}" alt="" style="width:36px;height:36px;object-fit:contain;" onerror="this.style.display='none'">`;
                            }
                        },
                        {
                            title: "FBA SKU",
                            field: "FBA_SKU",
                            headerFilter: "input",
                            headerFilterPlaceholder: "Search SKU...",
                            cssClass: "font-weight-bold",
                            tooltip: true,
                            frozen: true,
                            formatter: function(cell) {
                                const fbaSku = cell.getValue();
                                const rowData = cell.getRow().getData();
                                const sku = rowData.SKU;
                                const ratings = rowData.Ratings;
                                const missingData = rowData.Missing_Fields_Data || {count: 0, fields: []};
                                const missingCount = missingData.count || 0;
                                const missingFields = missingData.fields || [];
                                
                                if (!fbaSku || rowData.is_parent) return fbaSku;

                                let ratingDisplay = '';
                                if (ratings && ratings > 0) {
                                    ratingDisplay =
                                        ` <i class="fa fa-star" style="color: orange;"></i> ${ratings}`;
                                }

                                let missingDisplay = '';
                                if (missingCount > 0) {
                                    const missingFieldsJson = JSON.stringify(missingFields).replace(/'/g, "&#39;");
                                    missingDisplay = ` <span class="missing-fields-count" 
                                        style="color: #dc3545; font-size: 8px; font-weight: bold; vertical-align: super; cursor: pointer; text-decoration: underline;" 
                                        data-fields='${missingFieldsJson}'
                                        data-sku="${sku}"
                                        title="Click to view missing fields">${missingCount}</span>`;
                                }

                                return `${fbaSku}${ratingDisplay}${missingDisplay} <i class="fa fa-copy text-secondary copy-fba-sku-btn" style="cursor: pointer; margin-left: 8px; font-size: 14px;" data-fba-sku="${fbaSku}" title="Copy FBA SKU"></i> <button class="btn btn-sm ms-1 view-sku-chart" data-sku="${sku}" title="View Metrics Chart" style="border: none; background: none; color: #87CEEB; padding: 2px 6px;"><i class="fa fa-info-circle"></i></button>`;
                            }
                        },

                        {
                            title: "Sugg Send",
                            field: "Sugg_Send",
                            hozAlign: "center",
                            formatter: function(cell) {
                                const rowData = cell.getRow().getData();
                                // Don't show value on parent rows
                                if (rowData.is_parent) {
                                    return '';
                                }
                                let value = parseFloat(cell.getValue());
                                if (isNaN(value)) return '';

                                // If Dil is above 50%, force Sugg Send to 0
                                const dil = parseFloat(rowData.Dil);
                                if (!isNaN(dil) && dil > 50) {
                                    value = 0;
                                }

                                // If Compliance toggle is red, force Sugg Send to 0 (default green)
                                const compliance = (rowData.compliance || 'green').toString().toLowerCase();
                                if (compliance === 'red') {
                                    value = 0;
                                }

                                // If rating is below 3.5 stars, force Sugg Send to 0
                                const rating = parseFloat(rowData.AMZ_Rating);
                                if (!isNaN(rating) && rating > 0 && rating < 3.5) {
                                    value = 0;
                                }

                                // Negative Main INV is not a send candidate
                                const mainInv = parseFloat(rowData.Shopify_INV);
                                if (!isNaN(mainInv) && mainInv < 0) {
                                    return '';
                                }

                                // If Main INV is less than CTN QTY, force Sugg Send to 0
                                const ctnQty = parseFloat(rowData.CTN_QTY);
                                if (!isNaN(ctnQty) && ctnQty > 0 && !isNaN(mainInv) && mainInv < ctnQty) {
                                    value = 0;
                                }

                                const display = Number.isInteger(value) ? value : value.toFixed(0);

                                // Color coding: positive = green, else (negative or zero) = red
                                const bgColor = value > 0 ? '#28a745' :
                                    '#dc3545'; // green for positive, red for else
                                const textColor = '#fff';
                                return `<span style="background-color:${bgColor}; color:${textColor}; padding:4px 8px; border-radius:3px; font-weight:600;">${display}</span>`;
                            }
                        },

                        {
                            title: "Main INV",
                            field: "Shopify_INV",
                            hozAlign: "center",
                            formatter: function(cell) {
                                const rowData = cell.getRow().getData();
                                if (rowData.is_parent) return '';
                                const value = cell.getValue();
                                const mainInv = parseFloat(value);
                                const ctnQty = parseFloat(rowData.CTN_QTY);
                                if (!isNaN(ctnQty) && ctnQty > 0 && !isNaN(mainInv) && mainInv < ctnQty) {
                                    return `<span style="color: #dc3545; font-weight: 600;">${value}</span>`;
                                }
                                return `${value ?? ''}`;
                            }
                        },

                        {
                            title: "M/A Barcode",
                            field: "Barcode",
                            editor: "list",
                            editorParams: {
                                values: ["", "Barcode", "Asin", "Verify"],
                                autocomplete: true,
                                allowEmpty: true,
                                listOnEmpty: true
                            },
                            hozAlign: "center",
                            tooltip: function(e, cell) {
                                const rowData = cell.getRow().getData();
                                if (rowData.is_parent) return false;
                                let value = (cell.getValue() || '').toString().trim();
                                if (value === 'M') value = 'Barcode';
                                else if (value === 'A') value = 'Asin';
                                return value || false;
                            },
                            formatter: function(cell) {
                                const rowData = cell.getRow().getData();
                                if (rowData.is_parent) return '';
                                let value = (cell.getValue() || '').toString().trim();
                                // Backward-compat with old M/A values
                                if (value === 'M') value = 'Barcode';
                                else if (value === 'A') value = 'Asin';
                                if (!value) return '';

                                const labelMap = {
                                    'Barcode': 'Bar',
                                    'Asin': 'Asin',
                                    'Verify': 'Verify'
                                };
                                const classMap = {
                                    'Barcode': 'bar',
                                    'Asin': 'asin',
                                    'Verify': 'verify'
                                };
                                const label = labelMap[value];
                                const cls = classMap[value];
                                if (!label) return value;
                                return `<span class="barcode-label ${cls}">${label}</span>`;
                            }
                        },

                        {
                            title: "Send Qty",
                            field: "Send_Qty",
                            hozAlign: "center",
                            cssClass: "send-qty-cell",
                            // Double-click opens the editor with the value that is shown (saved value, or the auto-filled Qty CTN).
                            editor: function(cell, onRendered, success, cancel) {
                                const eff = fbaSendQtyEffective(cell.getRow().getData());
                                const initial = eff ? String(eff.value) : '';
                                const input = document.createElement('input');
                                input.type = 'text';
                                input.value = initial;
                                input.style.cssText = 'width:100%;box-sizing:border-box;padding:3px;text-align:center;';
                                let done = false;
                                const commit = function() {
                                    if (done) return;
                                    done = true;
                                    const v = input.value.trim();
                                    if (v === initial) cancel(); else success(v);
                                };
                                input.addEventListener('blur', commit);
                                input.addEventListener('keydown', function(e) {
                                    if (e.key === 'Enter') { e.preventDefault(); commit(); }
                                    else if (e.key === 'Escape') { e.preventDefault(); if (!done) { done = true; cancel(); } }
                                });
                                onRendered(function() { input.focus(); input.select(); });
                                return input;
                            },
                            formatter: function(cell) {
                                const rowData = cell.getRow().getData();
                                if (rowData.is_parent) return '';
                                const eff = fbaSendQtyEffective(rowData);
                                if (!eff) return '';
                                const esc = String(eff.value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                                const tip = eff.manual ? '' : ` title="Auto-filled from Qty CTN (rule: ${String(fbaSendQtyRuleLabel || fbaSendQtyRuleText).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;')}). Double-click to edit"`;
                                return `<span${tip} style="color:#000; font-weight:600;">${esc}</span>`;
                            }
                        },

                        {
                            title: "Sent QTY",
                            field: "Total_quantity_sent",
                            hozAlign: "center",
                            width: 32,
                            minWidth: 32,
                            widthGrow: 0,
                            widthShrink: 0,
                            formatter: function(cell) {
                                const rowData = cell.getRow().getData();
                                if (rowData.is_parent) return '';
                                const v = cell.getValue();
                                const checked = (v === true || v === 1 || v === '1');
                                return `<input type="checkbox" class="sent-qty-check" ${checked ? 'checked' : ''} style="width:18px; height:18px; cursor:pointer; accent-color:#28a745;">`;
                            },
                            cellClick: function(e, cell) {
                                const box = e.target.closest('.sent-qty-check');
                                if (!box) return;
                                const rowData = cell.getRow().getData();
                                if (rowData.is_parent) return;

                                const newVal = box.checked ? 1 : 0;
                                cell.setValue(newVal);

                                $.ajax({
                                    url: '/update-fba-manual-data',
                                    method: 'POST',
                                    data: {
                                        _token: '{{ csrf_token() }}',
                                        sku: rowData.FBA_SKU || rowData.SKU,
                                        field: 'total_quantity_sent',
                                        value: newVal
                                    },
                                    success: function() {
                                        console.log('Sent QTY saved:', newVal);
                                    },
                                    error: function(xhr) {
                                        console.error('Error saving Sent QTY:', xhr.responseText);
                                        showToast('Failed to save Sent QTY', 'error');
                                        cell.setValue(newVal ? 0 : 1);
                                    }
                                });

                                // Log history when checked (Sent)
                                if (newVal === 1) {
                                    appendSendHistory(cell.getRow(), 'Sent');
                                }
                            }
                        },

                        {
                            title: "History",
                            field: "Send_History",
                            headerSort: false,
                            hozAlign: "center",
                            width: 32,
                            minWidth: 32,
                            widthGrow: 0,
                            widthShrink: 0,
                            formatter: function(cell) {
                                const rowData = cell.getRow().getData();
                                if (rowData.is_parent) return '';
                                const history = parseSendHistory(cell.getValue());
                                let cls = 'empty';
                                if (history.length > 0) {
                                    const last = history[history.length - 1];
                                    cls = (last && last.action === 'Cleared') ? 'cleared' : 'has';
                                }
                                return `<span class="history-dot ${cls}"></span>`;
                            }
                        },



                        {
                            title: "Ov L30",
                            field: "Shopify_OV_L30",
                            hozAlign: "center",
                        },

                        {
                            title: "Dil",
                            field: "Dil",
                            hozAlign: "center",
                            formatter: function(cell) {
                                const value = parseFloat(cell.getValue());
                                if (isNaN(value)) return '';
                                // Same colors as Analytics Amz (amazon-tabulator-view) Dil column:
                                // grey 0%, red < 25%, green 25-50%, pink 50%+
                                if (!(value > 0)) return '<span style="color:#6c757d;">0%</span>';
                                let color = '#a00211';
                                if (value < 25) color = '#a00211';
                                else if (value < 50) color = '#28a745';
                                else color = '#e83e8c';
                                return `<span style="color:${color}; font-weight:600;">${Math.round(value)}%</span>`;
                            },
                        },

                        {
                            title: "FBA INV",
                            field: "FBA_Quantity",
                            hozAlign: "center"
                        },


                        {
                            title: "Inbound",
                            field: "Inbound_Quantity",
                            hozAlign: "center",
                            editor: "input"
                        },



                        {
                            title: "L30 FBA",
                            field: "l30_units",
                            hozAlign: "center"
                        },
                        {
                            title: "AMZ L30",
                            field: "AMZ_L30",
                            hozAlign: "center"
                        },
                        {
                            title: "FBA Dil",
                            field: "FBA_Dil",
                            visible: false,
                            hozAlign: "center",
                            formatter: function(cell) {
                                const value = parseFloat(cell.getValue());
                                if (isNaN(value)) return '';
                                const formattedValue = `${value.toFixed(0)}%`;
                                let color = '';
                                if (value <= 50) color = 'red';
                                else if (value <= 100) color = 'green';
                                else color = 'purple';
                                return `<span style="color:${color}; font-weight:600;">${formattedValue}</span>`;
                            },
                        },

                        {
                            title: "MSL",
                            field: "MSL",
                            hozAlign: "center",
                            formatter: function(cell) {
                                const rowData = cell.getRow().getData();
                                // Don't show value on parent rows
                                if (rowData.is_parent) {
                                    return '';
                                }
                                let value = parseFloat(cell.getValue());
                                if (isNaN(value)) return '';

                                // If value is exactly 0, display 1 (no color)
                                if (value === 0) {
                                    return '1';
                                }

                                const display = Number.isInteger(value) ? value : value.toFixed(0);
                                return `${display}`;
                            }
                        },

                        {
                            title: "Compliance",
                            field: "compliance",
                            hozAlign: "center",
                            width: 32,
                            minWidth: 32,
                            widthGrow: 0,
                            widthShrink: 0,
                            formatter: function(cell) {
                                const rowData = cell.getRow().getData();
                                if (rowData.is_parent) {
                                    return '';
                                }
                                // Cycle: red -> green -> yellow. Default green (first time).
                                let value = (cell.getValue() || 'green').toString().toLowerCase();
                                if (!['red', 'green', 'yellow'].includes(value)) {
                                    value = 'green';
                                }
                                return `<span class="compliance-dot ${value}" title="Click to toggle compliance"></span>`;
                            },
                            cellClick: function(e, cell) {
                                const rowData = cell.getRow().getData();
                                if (rowData.is_parent) {
                                    return;
                                }

                                const dot = e.target.closest('.compliance-dot');
                                if (!dot) return;

                                const order = ['red', 'green', 'yellow'];
                                let current = (cell.getValue() || 'green').toString().toLowerCase();
                                if (!order.includes(current)) current = 'green';
                                const newValue = order[(order.indexOf(current) + 1) % order.length];

                                order.forEach(c => dot.classList.remove(c));
                                dot.classList.add(newValue);
                                cell.setValue(newValue, true);

                                $.ajax({
                                    url: '/update-fba-manual-data',
                                    method: 'POST',
                                    data: {
                                        sku: rowData.FBA_SKU || rowData.SKU,
                                        field: 'compliance',
                                        value: newValue,
                                        _token: '{{ csrf_token() }}'
                                    },
                                    success: function(response) {
                                        console.log('Compliance saved successfully:', response);
                                    },
                                    error: function(xhr) {
                                        console.error('Error saving compliance:', xhr.responseText);
                                        // Revert on error
                                        order.forEach(c => dot.classList.remove(c));
                                        dot.classList.add(current);
                                        cell.setValue(current, true);
                                        alert('Failed to save compliance. Please try again.');
                                    }
                                });
                            }
                        },

                        {
                            title: "Send Toggle",
                            field: "send_toggle",
                            hozAlign: "center",
                            width: 32,
                            minWidth: 32,
                            widthGrow: 0,
                            widthShrink: 0,
                            formatter: function(cell) {
                                const rowData = cell.getRow().getData();
                                if (rowData.is_parent) {
                                    return '';
                                }

                                // Default is red dot
                                const value = cell.getValue();
                                const isGreen = value === true || value === 1 || value === '1';
                                const colorClass = isGreen ? 'green' : 'red';

                                return `<span class="send-toggle-dot ${colorClass}"></span>`;
                            },
                            cellClick: function(e, cell) {
                                const rowData = cell.getRow().getData();
                                if (rowData.is_parent) {
                                    return;
                                }

                                const dot = e.target.closest('.send-toggle-dot');
                                if (!dot) return;

                                let newValue;
                                if (dot.classList.contains('red')) {
                                    dot.classList.remove('red');
                                    dot.classList.add('green');
                                    newValue = 1;
                                    cell.setValue(1, true);
                                } else {
                                    dot.classList.remove('green');
                                    dot.classList.add('red');
                                    newValue = 0;
                                    cell.setValue(0, true);
                                }

                                // Save to database immediately
                                $.ajax({
                                    url: '/update-fba-manual-data',
                                    method: 'POST',
                                    data: {
                                        sku: rowData.FBA_SKU || rowData.SKU,
                                        field: 'send_toggle',
                                        value: newValue,
                                        _token: '{{ csrf_token() }}'
                                    },
                                    success: function(response) {
                                        console.log('Send toggle saved successfully:', response);
                                    },
                                    error: function(xhr) {
                                        console.error('Error saving send toggle:', xhr.responseText);
                                        // Revert the toggle on error
                                        if (newValue === 1) {
                                            dot.classList.remove('green');
                                            dot.classList.add('red');
                                            cell.setValue(0, true);
                                        } else {
                                            dot.classList.remove('red');
                                            dot.classList.add('green');
                                            cell.setValue(1, true);
                                        }
                                        alert('Failed to save send toggle. Please try again.');
                                    }
                                });
                            }
                        },

                        {
                            title: "FBA Price",
                            field: "FBA_Price",
                            hozAlign: "center",
                            visible: true,
                            // formatter: "dollar"
                        },
                        {
                            title: "LMP",
                            field: "LMP",
                            hozAlign: "center",
                            visible: true,
                            formatter: function(cell) {
                                const rowData = cell.getRow().getData();
                                const value = cell.getValue();
                                const lmpLink = rowData.LMP_Link;
                                const sku = rowData.SKU || '';
                                
                                if (rowData.is_parent) {
                                    return '';
                                }
                                
                                if (value == null || value === '' || parseFloat(value) <= 0) {
                                    const skuEnc = encodeURIComponent(sku);
                                    const url = '/repricer/amazon-search' + (skuEnc ? '?sku=' + skuEnc : '');
                                    return '<a href="' + url + '" target="_blank" rel="noopener" class="lmp-no-data-link" title="No LMP – open Amz repricer search"><i class="fas fa-circle" style="color: #ff9c00; font-size: 10px;"></i></a>';
                                }
                                
                                const price = parseFloat(value);
                                const fbaPrice = parseFloat(rowData.FBA_Price || 0);
                                const color = (fbaPrice > 0 && price < fbaPrice) ? '#dc3545' : '#28a745';
                                // Make LMP price clickable to show all competitors in modal
                                const baseSku = sku.replace(/\s*FBA\s*/i, '').trim();
                                return '<a href="#" class="lmp-price-link" data-sku="' + baseSku.replace(/"/g, '&quot;') + '" data-marketplace="amazon" style="color: ' + color + '; text-decoration: none; cursor: pointer; font-weight: 600;">$' + price.toFixed(2) + '</a>';
                            },
                        },
                        {
                            title: "Price",
                            field: "AMZ_Price",
                            hozAlign: "center",
                            visible: true,
                            formatter: function(cell) {
                                const rowData = cell.getRow().getData();
                                const value = cell.getValue();
                                
                                if (rowData.is_parent) {
                                    return '';
                                }
                                
                                if (value == null || value === '' || parseFloat(value) <= 0) {
                                    return '<span class="text-muted">-</span>';
                                }
                                
                                const price = parseFloat(value);
                                return '<span style="font-weight: 600;">$' + price.toFixed(2) + '</span>';
                            },
                        },
                        {
                            title: "FBM<br>GPFT%",
                            field: "AMZ_GPFT",
                            hozAlign: "center",
                            visible: true,
                            formatter: function(cell) {
                                const value = cell.getValue();
                                if (value == null || value === '') return '<span class="text-muted">-</span>';
                                const pct = parseFloat(value);
                                let color = '';
                                if (pct < 0) color = '#a00211';
                                else if (pct >= 0 && pct < 10) color = '#ffc107';
                                else if (pct >= 10 && pct < 20) color = '#3591dc';
                                else if (pct >= 20 && pct <= 40) color = '#28a745';
                                else color = '#e83e8c';
                                return `<span style="color: ${color}; font-weight: 600;">${pct.toFixed(0)}%</span>`;
                            },
                        },
                        {
                            title: "AMZ<br>AD%",
                            field: "AMZ_AD",
                            hozAlign: "center",
                            visible: false,
                            formatter: function(cell) {
                                const value = cell.getValue();
                                if (value == null || value === '') return '<span class="text-muted">-</span>';
                                const pct = parseFloat(value);
                                let color = '';
                                // AD% color: lower is better
                                if (pct >= 100) color = '#a00211';
                                else if (pct >= 50) color = '#dc3545';
                                else if (pct >= 20) color = '#ffc107';
                                else if (pct >= 10) color = '#3591dc';
                                else if (pct > 0) color = '#28a745';
                                else color = '#6c757d';
                                return `<span style="color: ${color}; font-weight: 600;">${pct.toFixed(1)}%</span>`;
                            },
                        },
                        {
                            title: "AMZ<br>NPFT%",
                            field: "AMZ_NPFT",
                            hozAlign: "center",
                            visible: false,
                            formatter: function(cell) {
                                const value = cell.getValue();
                                if (value == null || value === '') return '<span class="text-muted">-</span>';
                                const pct = parseFloat(value);
                                let color = '';
                                if (pct < 0) color = '#a00211';
                                else if (pct >= 0 && pct < 10) color = '#ffc107';
                                else if (pct >= 10 && pct < 20) color = '#3591dc';
                                else if (pct >= 20 && pct <= 40) color = '#28a745';
                                else color = '#e83e8c';
                                return `<span style="color: ${color}; font-weight: 600;">${pct.toFixed(1)}%</span>`;
                            },
                        },
                        {
                            title: "CVR<br>L60<br>amz",
                            field: "CVR_L60_amz",
                            hozAlign: "center",
                            visible: false,
                            formatter: function(cell) {
                                const row = cell.getRow().getData();
                                if (row.is_parent) return '';
                                
                                const aL60 = parseFloat(row['AMZ_L60'] || 0);
                                const sess60 = parseFloat(row['AMZ_Sess60'] || 0);
                                
                                if (sess60 === 0) return '<span style="color: #a00211; font-weight: 600;">0.0%</span>';
                                
                                const cvr = (aL60 / sess60) * 100;
                                let color = '';
                                if (cvr <= 4) color = '#a00211'; // red
                                else if (cvr > 4 && cvr <= 7) color = '#ffc107'; // yellow
                                else if (cvr > 7 && cvr <= 13) color = '#28a745'; // green
                                else color = '#e83e8c'; // pink
                                
                                return `<span style="color: ${color}; font-weight: 600;">${cvr.toFixed(1)}%</span>`;
                            },
                            sorter: function(a, b, aRow, bRow) {
                                const calcCVR = (row) => {
                                    const aL60 = parseFloat(row['AMZ_L60'] || 0);
                                    const sess60 = parseFloat(row['AMZ_Sess60'] || 0);
                                    return sess60 === 0 ? 0 : (aL60 / sess60) * 100;
                                };
                                return calcCVR(aRow.getData()) - calcCVR(bRow.getData());
                            },
                        },
                        {
                            title: "CVR<br>L45<br>amz",
                            field: "CVR_L45_amz",
                            hozAlign: "center",
                            visible: false,
                            formatter: function(cell) {
                                const row = cell.getRow().getData();
                                if (row.is_parent) return '';
                                
                                // 45-day: use average of L30 and L60 (sold and views) for 45-day approximation
                                const aL30 = parseFloat(row['AMZ_L30'] || 0);
                                const sess30 = parseFloat(row['AMZ_Sess30'] || 0);
                                const aL60 = parseFloat(row['AMZ_L60'] || 0);
                                const sess60 = parseFloat(row['AMZ_Sess60'] || 0);
                                const aL45 = (aL30 + aL60) / 2;
                                const sess45 = (sess30 + sess60) / 2;
                                
                                if (sess45 === 0) return '<span style="color: #a00211; font-weight: 600;">0.0%</span>';
                                
                                const cvr = (aL45 / sess45) * 100;
                                let color = '';
                                if (cvr <= 4) color = '#a00211'; // red
                                else if (cvr > 4 && cvr <= 7) color = '#ffc107'; // yellow
                                else if (cvr > 7 && cvr <= 13) color = '#28a745'; // green
                                else color = '#e83e8c'; // pink
                                
                                return `<span style="color: ${color}; font-weight: 600;">${cvr.toFixed(1)}%</span>`;
                            },
                            sorter: function(a, b, aRow, bRow) {
                                const calcCVR = (row) => {
                                    const aL30 = parseFloat(row['AMZ_L30'] || 0);
                                    const sess30 = parseFloat(row['AMZ_Sess30'] || 0);
                                    const aL60 = parseFloat(row['AMZ_L60'] || 0);
                                    const sess60 = parseFloat(row['AMZ_Sess60'] || 0);
                                    const aL45 = (aL30 + aL60) / 2;
                                    const sess45 = (sess30 + sess60) / 2;
                                    return sess45 === 0 ? 0 : (aL45 / sess45) * 100;
                                };
                                return calcCVR(aRow.getData()) - calcCVR(bRow.getData());
                            },
                        },
                        {
                            title: "cvr",
                            field: "CVR_L30_amz",
                            hozAlign: "center",
                            visible: true,
                            formatter: function(cell) {
                                const row = cell.getRow().getData();
                                if (row.is_parent) return '';
                                
                                const sku = row['SKU'] || '';
                                const aL30 = parseFloat(row['AMZ_L30'] || 0);
                                const sess30 = parseFloat(row['AMZ_Sess30'] || 0);
                                const aL60 = parseFloat(row['AMZ_L60'] || 0);
                                const sess60 = parseFloat(row['AMZ_Sess60'] || 0);
                                const cvrL30 = sess30 === 0 ? 0 : (aL30 / sess30) * 100;
                                const cvrL60 = sess60 === 0 ? 0 : (aL60 / sess60) * 100;
                                const tol = 0.1;
                                
                                let arrowHtml = '';
                                if (sku) {
                                    let arrowColor = '#6c757d';
                                    let arrowIcon = 'fa-minus';
                                    if (cvrL30 > cvrL60 + tol) {
                                        arrowColor = '#28a745';
                                        arrowIcon = 'fa-arrow-up';
                                    } else if (cvrL30 < cvrL60 - tol) {
                                        arrowColor = '#a00211';
                                        arrowIcon = 'fa-arrow-down';
                                    }
                                    arrowHtml = `<i class="fas ${arrowIcon}" style="color: ${arrowColor}; font-size: 12px; margin-left: 4px;"></i>`;
                                }
                                
                                if (sess30 === 0) {
                                    return `<span style="color: #a00211; font-weight: 600; font-size: 16px;">0%</span>${arrowHtml}`.trim();
                                }
                                
                                const cvr = cvrL30;
                                let color = '';
                                if (cvr <= 4) color = '#a00211';
                                else if (cvr > 4 && cvr <= 7) color = '#ffc107';
                                else if (cvr > 7 && cvr <= 13) color = '#28a745';
                                else color = '#e83e8c';
                                
                                return `<span style="color: ${color}; font-weight: 600; font-size: 16px;">${cvr.toFixed(0)}%</span>${arrowHtml}`.trim();
                            },
                            sorter: function(a, b, aRow, bRow) {
                                const calcCVR = (row) => {
                                    const aL30 = parseFloat(row['AMZ_L30'] || 0);
                                    const sess30 = parseFloat(row['AMZ_Sess30'] || 0);
                                    return sess30 === 0 ? 0 : (aL30 / sess30) * 100;
                                };
                                return calcCVR(aRow.getData()) - calcCVR(bRow.getData());
                            },
                        },
                        {
                            title: "Rating",
                            field: "AMZ_Rating",
                            hozAlign: "center",
                            visible: true,
                            formatter: function(cell) {
                                const row = cell.getRow().getData();
                                if (row.is_parent) return '';
                                
                                const rating = parseFloat(row['AMZ_Rating'] || 0);
                                const reviews = parseInt(row['AMZ_Reviews'] || 0);
                                
                                if (!rating || rating === 0) {
                                    return '<span style="color: #6c757d;">-</span>';
                                }
                                
                                // Use same colors as rating filter
                                let ratingColor = '';
                                const ratingVal = parseFloat(rating);
                                if (ratingVal < 3) ratingColor = '#a00211'; // red
                                else if (ratingVal >= 3 && ratingVal <= 3.5) ratingColor = '#ffc107'; // yellow
                                else if (ratingVal >= 3.51 && ratingVal <= 3.99) ratingColor = '#3591dc'; // blue
                                else if (ratingVal >= 4 && ratingVal <= 4.5) ratingColor = '#28a745'; // green
                                else ratingColor = '#e83e8c'; // pink (>4.5)
                                
                                const reviewColor = reviews < 4 ? '#a00211' : '#6c757d';
                                const fontWeight = '600';
                                
                                return `<div style="display: flex; flex-direction: column; align-items: center; gap: 2px;">
                                    <span style="color: ${ratingColor}; font-weight: ${fontWeight};">
                                        <i class="fa fa-star"></i> ${rating.toFixed(1)}
                                    </span>
                                    <span style="font-size: 11px; color: ${reviewColor}; font-weight: ${fontWeight};">
                                        (${reviews.toLocaleString()})
                                    </span>
                                </div>`;
                            },
                            sorter: function(a, b, aRow, bRow) {
                                const ratingA = parseFloat(aRow.getData()['AMZ_Rating'] || 0);
                                const ratingB = parseFloat(bRow.getData()['AMZ_Rating'] || 0);
                                return ratingA - ratingB;
                            },
                        },

                        {
                            title: "ROI%",
                            field: "ROI%",
                            hozAlign: "center",
                            visible: true,
                            formatter: function(cell) {
                                const value = parseFloat(cell.getValue() || 0);
                                let color = '';
                                if (value >= 0 && value <= 50) {
                                    color = 'red';
                                } else if (value >= 51 && value <= 100) {
                                    color = 'green';
                                } else if (value >= 101) {
                                    color = 'magenta';
                                }
                                return `<span style="color: ${color}; font-weight: bold;">${value.toFixed(0)}%</span>`;
                            },
                        },

                       

                        {
                            title: "GROI%",
                            field: "GROI%",
                            hozAlign: "center",
                            visible: true,
                            formatter: function(cell) {
                                return cell.getValue();
                            }
                        },

                        {
                            title: "GPFT%",
                            field: "GPFT%",
                            hozAlign: "center",
                            visible: true,
                            formatter: function(cell) {
                                return cell.getValue();
                            }
                        },

                        {
                            title: "PRFT<br>%",
                            field: "TPFT",
                            hozAlign: "center",
                            formatter: function(cell) {
                                const rowData = cell.getRow().getData();
                                if (rowData.is_parent) return '';
                                // PRFT% = GPFT% - AMZ Ads%
                                // GPFT% may be an HTML string (e.g. "<span>32%</span>"), so strip tags before parsing
                                const gpftRaw = String(rowData['GPFT%'] ?? '').replace(/<[^>]*>/g, '');
                                const gpft = parseFloat(gpftRaw) || 0;
                                const amzAds = parseFloat(rowData['AMZ_AD']) || 0;
                                const value = gpft - amzAds;
                                let style = '';
                                if (value < 10) {
                                    style = 'color: red;';
                                } else if (value >= 11 && value <= 15) {
                                    style = 'background-color: yellow; color: black;';
                                } else if (value >= 16 && value <= 20) {
                                    style = 'color: blue;';
                                } else if (value >= 21 && value <= 40) {
                                    style = 'color: green;';
                                } else if (value > 40) {
                                    style = 'color: purple;';
                                }
                                return `<span style="${style}">${value.toFixed(0)}%</span>`;
                            },
                        },

                        {
                            title: "ROI",
                            field: "FBA_ROI",
                            hozAlign: "center",
                            headerTooltip: "ROI = ((FBA Price × Marketplace %) − FBA Ship − LP) / LP × 100 − AMZ Ads%",
                            formatter: function(cell) {
                                const rowData = cell.getRow().getData();
                                if (rowData.is_parent) return '';
                                const value = cell.getValue();
                                if (value === null || value === '' || isNaN(parseFloat(value))) {
                                    return '<span class="text-muted">-</span>';
                                }
                                // ROI minus AMZ Ads%
                                const amzAds = parseFloat(rowData['AMZ_AD']) || 0;
                                const roi = parseFloat(value) - amzAds;
                                let color = '';
                                if (roi <= 50) {
                                    color = 'red';
                                } else if (roi > 50 && roi <= 100) {
                                    color = 'green';
                                } else {
                                    color = 'magenta';
                                }
                                return `<span style="color: ${color}; font-weight: bold;">${roi.toFixed(0)}%</span>`;
                            },
                        },

                        {
                            title: "ROI%",
                            field: "ROI%",
                            hozAlign: "center",
                            visible: false,
                            formatter: function(cell) {
                                return cell.getValue();
                            }
                        },



                        {
                            title: "S Price",
                            field: "S_Price",
                            hozAlign: "center",
                            visible: false,
                            editor: "input",
                            cellEdited: function(cell) {
                                var data = cell.getRow().getData();
                                var value = cell.getValue();

                                $.ajax({
                                    url: '/update-fba-manual-data',
                                    method: 'POST',
                                    data: {
                                        sku: data.FBA_SKU,
                                        field: 's_price',
                                        value: value,
                                        _token: '{{ csrf_token() }}'
                                    },
                                    success: function() {
                                        table
                                            .replaceData();
                                    }
                                });
                            }
                        },
                        {
                            title: "SPft%",
                            field: "SPft%",
                            hozAlign: "center",
                            visible: false,
                            formatter: function(cell) {
                                return cell.getValue();
                            },
                        },
                        {
                            title: "SROI%",
                            field: "SROI%",
                            hozAlign: "center",
                            visible: false,
                            formatter: function(cell) {
                                return cell.getValue();
                            },
                        },


                        {
                            title: "LMP ",
                            field: "lmp_1",
                            hozAlign: "center",
                            visible: false,
                            formatter: function(cell) {
                                const value = cell.getValue();
                                const rowData = cell.getRow().getData();
                                if (value > 0) {
                                    return `<a href="#" class="lmp-link" data-sku="${rowData.SKU}" data-lmp-data='${JSON.stringify(rowData.lmp_data)}' style="color: blue; text-decoration: underline;">${value}</a>`;
                                } else {
                                    return value || '';
                                }
                            }
                        },

                        {
                            title: "Views",
                            field: "Current_Month_Views",
                            hozAlign: "center",
                            visible: false
                        },


                        {
                            title: "UPC Company",
                            field: "UPC_Codes",
                            hozAlign: "center",
                            width: 32,
                            minWidth: 32,
                            widthGrow: 0,
                            widthShrink: 0,
                            formatter: function(cell) {
                                const rowData = cell.getRow().getData();
                                if (rowData.is_parent) return '';
                                const upc = (cell.getValue() || '').toString().trim();
                                const edited = (rowData.UPC_Edit || '').toString().trim();
                                const hasData = upc !== '' || edited !== '';

                                // Green = UPC Company matches UPC Edit, Yellow = mismatch, Red = nothing filled
                                const isMatch = upc !== '' && edited !== '' && upc.toLowerCase() === edited.toLowerCase();
                                const color = !hasData ? '#dc3545' : (isMatch ? '#28a745' : '#ffc107');
                                const status = !hasData ? 'No UPC' : (isMatch ? 'Match' : 'No match');
                                const esc = (s) => String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                                const title = hasData ? `UPC Company: ${upc || '-'} | UPC Edit: ${edited || '-'} (${status}) - click to view` : status;
                                return `<span class="upc-dot" data-upc="${esc(upc)}" data-upc-edit="${esc(edited)}" style="display:inline-block; width:12px; height:12px; border-radius:50%; background-color:${color}; cursor:${hasData ? 'pointer' : 'default'};" title="${esc(title)}"></span>`;
                            },
                            cellClick: function(e, cell) {
                                const dot = e.target.closest('.upc-dot');
                                if (!dot) return;
                                const upc = dot.getAttribute('data-upc');
                                const edited = dot.getAttribute('data-upc-edit');
                                if (upc || edited) {
                                    showToast(`UPC Company: ${upc || '-'} | UPC Edit: ${edited || '-'}`, 'success');
                                }
                            }
                        },

                        {
                            title: "UPC Edit",
                            field: "UPC_Edit",
                            hozAlign: "center",
                            headerSort: false,
                            formatter: function(cell) {
                                const rowData = cell.getRow().getData();
                                if (rowData.is_parent) return '';
                                const value = String(cell.getValue() ?? '').trim();
                                const esc = (s) => String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                                // Green dot when a UPC code is filled (code shown on hover), then the edit icon
                                const dot = value
                                    ? `<span title="${esc(value)}" style="display:inline-block;width:12px;height:12px;border-radius:50%;background-color:#28a745;margin-right:6px;vertical-align:middle;"></span>`
                                    : '';
                                return `${dot}<i class="fa fa-edit" style="color:#3b7ddd;cursor:pointer;" title="Edit UPC code"></i>`;
                            },
                            cellClick: function(e, cell) {
                                const rowData = cell.getRow().getData();
                                if (rowData.is_parent) return;
                                openUpcEditModal(cell.getRow());
                            }
                        },


                        {
                            title: "L CTN",
                            field: "Length",
                            hozAlign: "center",
                            visible: false,
                            editor: "input",
                            formatter: ctnDimensionFormatter(36),
                            cellEdited: function(cell) {
                                var data = cell.getRow().getData();
                                var value = cell.getValue();
                                
                                // Handle empty values - send empty string if cleared
                                if (value === null || value === undefined) {
                                    value = '';
                                } else {
                                    value = String(value).trim();
                                }

                                $.ajax({
                                    url: '/update-fba-manual-data',
                                    method: 'POST',
                                    data: {
                                        sku: data.FBA_SKU || data.SKU,
                                        field: 'length',
                                        value: value,
                                        _token: '{{ csrf_token() }}'
                                    },
                                    success: function() {
                                        table.replaceData();
                                    },
                                    error: function(xhr) {
                                        console.error('Error saving Length:', xhr);
                                    }
                                });
                            }
                        },

                        {
                            title: "W CTN",
                            field: "Width",
                            hozAlign: "center",
                            visible: false,
                            editor: "input",
                            formatter: ctnDimensionFormatter(25),
                            cellEdited: function(cell) {
                                var data = cell.getRow().getData();
                                var value = cell.getValue();
                                
                                // Handle empty values - send empty string if cleared
                                if (value === null || value === undefined) {
                                    value = '';
                                } else {
                                    value = String(value).trim();
                                }

                                $.ajax({
                                    url: '/update-fba-manual-data',
                                    method: 'POST',
                                    data: {
                                        sku: data.FBA_SKU || data.SKU,
                                        field: 'width',
                                        value: value,
                                        _token: '{{ csrf_token() }}'
                                    },
                                    success: function() {
                                        table.replaceData();
                                    },
                                    error: function(xhr) {
                                        console.error('Error saving Width:', xhr);
                                    }
                                });
                            }
                        },

                        {
                            title: "H CTN",
                            field: "Height",
                            hozAlign: "center",
                            visible: false,
                            editor: "input",
                            formatter: ctnDimensionFormatter(25),
                            cellEdited: function(cell) {
                                var data = cell.getRow().getData();
                                var value = cell.getValue();
                                
                                // Handle empty values - send empty string if cleared
                                if (value === null || value === undefined) {
                                    value = '';
                                } else {
                                    value = String(value).trim();
                                }

                                $.ajax({
                                    url: '/update-fba-manual-data',
                                    method: 'POST',
                                    data: {
                                        sku: data.FBA_SKU || data.SKU,
                                        field: 'height',
                                        value: value,
                                        _token: '{{ csrf_token() }}'
                                    },
                                    success: function() {
                                        table.replaceData();
                                    },
                                    error: function(xhr) {
                                        console.error('Error saving Height:', xhr);
                                    }
                                });
                            }
                        },


                        {
                            title: "Qty CTN",
                            field: "Quantity_in_each_box",
                            hozAlign: "center",
                            visible: false,
                            editor: "input",
                            formatter: function(cell) {
                                const rowData = cell.getRow().getData();
                                const raw = cell.getValue();
                                const text = (raw === null || raw === undefined) ? '' : String(raw);
                                if (rowData.is_parent) return text;
                                const esc = (s) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                                // mousedown preventDefault keeps the click on the dot from starting the cell editor
                                const dot = `<span class="ctn-dot" onmousedown="event.preventDefault();" title="View CTN details" style="display:inline-block;width:10px;height:10px;border-radius:50%;background-color:#28a745;margin-left:6px;vertical-align:middle;cursor:pointer;"></span>`;
                                return `${esc(text)}${dot}`;
                            },
                            cellClick: function(e, cell) {
                                if (!e.target.closest('.ctn-dot')) return;
                                e.stopPropagation();
                                openCtnColumnsModal(cell.getRow());
                                // Make sure the input editor did not open underneath the modal
                                setTimeout(function() { try { cell.cancelEdit(); } catch (err) {} }, 0);
                            },
                            cellEdited: function(cell) {
                                var data = cell.getRow().getData();
                                var value = cell.getValue();
                                
                                // Handle empty values - send empty string if cleared
                                if (value === null || value === undefined) {
                                    value = '';
                                } else {
                                    value = String(value).trim();
                                }

                                $.ajax({
                                    url: '/update-fba-manual-data',
                                    method: 'POST',
                                    data: {
                                        sku: data.FBA_SKU || data.SKU,
                                        field: 'quantity_in_each_box',
                                        value: value,
                                        _token: '{{ csrf_token() }}'
                                    },
                                    success: function() {
                                        table.replaceData();
                                    },
                                    error: function(xhr) {
                                        console.error('Error saving Quantity_in_each_box:', xhr);
                                    }
                                });
                            }
                        },

                        {
                            title: "GW CTN",
                            field: "GW_CTN",
                            hozAlign: "center",
                            visible: false,
                            editor: "input",
                            formatter: function(cell) {
                                const raw = cell.getValue();
                                const text = (raw === null || raw === undefined) ? '' : String(raw);
                                const esc = text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                                // Gross weight above 50 shows in red
                                if (parseFloat(text) > 50) return `<span style="color:#ff0000;font-weight:800;">${esc}</span>`;
                                return esc;
                            },
                            cellEdited: function(cell) {
                                var data = cell.getRow().getData();
                                var value = cell.getValue();
                                
                                // Handle empty values - send empty string if cleared
                                if (value === null || value === undefined) {
                                    value = '';
                                } else {
                                    value = String(value).trim();
                                }

                                $.ajax({
                                    url: '/update-fba-manual-data',
                                    method: 'POST',
                                    data: {
                                        sku: data.FBA_SKU || data.SKU,
                                        field: 'gw_ctn',
                                        value: value,
                                        _token: '{{ csrf_token() }}'
                                    },
                                    success: function() {
                                        table.replaceData();
                                    },
                                    error: function(xhr) {
                                        console.error('Error saving GW_CTN:', xhr);
                                    }
                                });
                            }
                        },

                        {
                            title: "CTN cost",
                            field: "Shipping_Amount",
                            hozAlign: "center",
                            visible: false,
                            editor: "input",
                            cellEdited: function(cell) {
                                var data = cell.getRow().getData();
                                var value = cell.getValue();
                                
                                // Handle empty values - send empty string if cleared
                                if (value === null || value === undefined) {
                                    value = '';
                                } else {
                                    value = String(value).trim();
                                }

                                $.ajax({
                                    url: '/update-fba-manual-data',
                                    method: 'POST',
                                    data: {
                                        sku: data.FBA_SKU || data.SKU,
                                        field: 'shipping_amount',
                                        value: value,
                                        _token: '{{ csrf_token() }}'
                                    },
                                    success: function() {
                                        table.replaceData();
                                    },
                                    error: function(xhr) {
                                        console.error('Error saving Shipping_Amount:', xhr);
                                    }
                                });
                            }
                        },

                        {
                            title: "S status",
                            field: "FBA_Shipment_Status",
                            hozAlign: "center",
                            width: 32,
                            minWidth: 32,
                            widthGrow: 0,
                            widthShrink: 0,
                            tooltip: function(e, cell) {
                                const rowData = cell.getRow().getData();
                                if (rowData.is_parent) return false;
                                const status = (cell.getValue() || '').toString().trim();
                                return status || false;
                            },
                            formatter: function(cell) {
                                const rowData = cell.getRow().getData();
                                if (rowData.is_parent) return '';
                                const status = (cell.getValue() || '').toString().trim();
                                if (!status) return '';

                                const bad = ['CANCELLED', 'DELETED', 'ERROR'];
                                const cls = bad.includes(status) ? 'bad' : 'good';
                                return `<span class="s-status-dot ${cls}"></span>`;
                            }
                        },



                        {
                            title: "ASIN",
                            field: "ASIN",
                            hozAlign: "center",
                            width: 32,
                            minWidth: 32,
                            widthGrow: 0,
                            widthShrink: 0,
                            formatter: function(cell) {
                                const rowData = cell.getRow().getData();
                                if (rowData.is_parent) return '';
                                const asin = (cell.getValue() || '').toString().trim();
                                const hasData = asin !== '';
                                const color = hasData ? '#28a745' : '#dc3545';
                                const title = hasData ? 'Click to view ASIN' : 'No ASIN';
                                return `<span class="asin-dot" data-asin="${asin}" style="display:inline-block; width:12px; height:12px; border-radius:50%; background-color:${color}; cursor:${hasData ? 'pointer' : 'default'};" title="${title}"></span>`;
                            },
                            cellClick: function(e, cell) {
                                const dot = e.target.closest('.asin-dot');
                                if (!dot) return;
                                const asin = dot.getAttribute('data-asin');
                                if (asin) {
                                    showToast(`ASIN: ${asin}`, 'success');
                                }
                            }
                        },



                     


                        {
                            title: "FBA Ship",
                            field: "FBA_Ship_Calculation",
                            hozAlign: "center",
                            formatter: function(cell) {
                                const value = parseFloat(cell.getValue());
                                if (isNaN(value)) return '';
                                return value.toFixed(2);
                            }
                        },

                        {
                            title: "Ship",
                            field: "Ship",
                            hozAlign: "center",
                            headerTooltip: "Ship value from Shipping Master",
                            formatter: function(cell) {
                                const rowData = cell.getRow().getData();
                                if (rowData.is_parent) return '';
                                const value = parseFloat(cell.getValue());
                                if (isNaN(value)) return '<span class="text-muted">--</span>';
                                return value.toFixed(2);
                            }
                        },


                        {
                            title: "Send Cost",
                            field: "Send_Cost",
                            hozAlign: "center",
                            formatter: function(cell) {
                                const value = parseFloat(cell.getValue());
                                if (isNaN(value)) return '';
                                return `
                                    <span>${value.toFixed(2)}</span>
                                    <i class="fa fa-info-circle text-primary send-cost-toggle-btn" 
                                        style="cursor:pointer; margin-left:8px;" 
                                        title="Toggle CTN columns"></i>
                                `;
                            }
                        },



                        {
                            title: "FBA Fee",
                            field: "Fulfillment_Fee",
                            hozAlign: "center",
                            formatter: function(cell) {
                                const rowData = cell.getRow().getData();
                                if (rowData.is_parent) return '';
                                const raw = cell.getValue();
                                const value = parseFloat(raw);
                                const display = (raw === null || raw === '' || isNaN(value)) ? (raw ?? '') : value;
                                if (!isNaN(value) && value === 0) {
                                    return `<span style="color: #dc3545; font-weight: 600;">${display}</span>`;
                                }
                                return `${display}`;
                            }
                        },

                        {
                            title: "fees manual",
                            field: "FBA_Fee_Manual",
                            hozAlign: "center",
                            editor: "input",
                            formatter: function(cell) {
                                const value = parseFloat(cell.getValue());
                                if (isNaN(value)) return '';
                                if (value === 0) {
                                    return `<span style="color: #dc3545; font-weight: 600;">0</span>`;
                                }
                                return value.toFixed(2);
                            }
                        },

                        // {
                        //     title: "Correct Cost",
                        //     field: "Correct_Cost",
                        //     formatter: "tickCross",
                        //     hozAlign: "center",
                        //     editor: true,
                        //     cellClick: function(e, cell) {
                        //         var currentValue = cell.getValue();
                        //         cell.setValue(!currentValue);
                        //     }
                        // },
                        // {
                        //     title: "FBA Send",
                        //     field: "FBA_Send",
                        //     hozAlign: "center",
                        //     formatter: "tickCross",
                        //     editor: true,
                        //     cellClick: function(e, cell) {
                        //         var currentValue = cell.getValue();
                        //         cell.setValue(!currentValue);
                        //     }
                        // },
                        // {
                        //     title: "Approval",
                        //     field: "Approval",
                        //     formatter: "tickCross",
                        //     hozAlign: "center",
                        //     editor: true,
                        //     cellClick: function(e, cell) {
                        //         var currentValue = cell.getValue();
                        //         cell.setValue(!currentValue);
                        //     }
                        // },
                        // {
                        //     title: "Profit is ok",
                        //     field: "Profit_is_ok",
                        //     formatter: "tickCross",
                        //     hozAlign: "center",
                        //     editor: true,
                        //     cellClick: function(e, cell) {
                        //         var currentValue = cell.getValue();
                        //         cell.setValue(!currentValue);
                        //     }
                        // },

                        // {
                        //     title: "TPFT",
                        //     field: "TPFT",
                        //     hozAlign: "center",
                        //     // formatter: "dollar"
                        // },

                        // {
                        //     title: "Inv age",
                        //     field: "Inv_age",
                        //     hozAlign: "center",
                        //     formatter: function(cell) {
                        //         const value = cell.getValue();
                        //         return `<span>${value || ''}</span> <i class="fa fa-eye" style="cursor:pointer; color:#3b7ddd; margin-left:5px;" onclick="openInvageModal('${value || ''}', '${cell.getRow().getData().SKU}')"></i>`;
                        //     }
                        // },





                        // {
                        //     title: "TPFT",
                        //     field: "TPFT",
                        //     hozAlign: "center",
                        //     formatter: function(cell) {
                        //         return cell.getValue();
                        //     },
                        // },





                        // {
                        //     title: "FBA_CVR",
                        //     field: "FBA_CVR",
                        //     hozAlign: "center",
                        //     formatter: function(cell) {
                        //         return cell.getValue();
                        //     },
                        // },




                        // {
                        //     title: "Listed",
                        //     field: "Listed",
                        //     formatter: "tickCross",
                        //     hozAlign: "center",
                        //     editor: true,
                        //     cellClick: function(e, cell) {
                        //         var currentValue = cell.getValue();
                        //         cell.setValue(!currentValue);
                        //     }
                        // },
                        // {
                        //     title: "Live",
                        //     field: "Live",
                        //     formatter: "tickCross",
                        //     hozAlign: "center",
                        //     editor: true,
                        //     cellClick: function(e, cell) {
                        //         var currentValue = cell.getValue();
                        //         cell.setValue(!currentValue);
                        //     }
                        // },



                        // {
                        //     title: "WH ACT",
                        //     field: "WH_ACT",
                        //     hozAlign: "center",
                        //     editor: "input",
                        //     tooltip: true
                        // },



                        // {
                        //     title: "Shipment Track Status",
                        //     field: "Shipment_Track_Status",
                        //     hozAlign: "center",
                        //     editor: "input",
                        //     cellEdited: function(cell) {
                        //         var data = cell.getRow().getData();
                        //         var value = cell.getValue();

                        //         $.ajax({
                        //             url: '/update-fba-manual-data',
                        //             method: 'POST',
                        //             data: {
                        //                 sku: data.FBA_SKU,
                        //                 field: 'shipment_track_status',
                        //                 value: value,
                        //                 _token: '{{ csrf_token() }}'
                        //             },
                        //             success: function() {
                        //                 table.replaceData();
                        //             }
                        //         });
                        //     }
                        // },



                        // {
                        //     title: "FBA Fee Manual",
                        //     field: "FBA_Fee_Manual",
                        //     hozAlign: "center",
                        //     editor: "input",
                        //     formatter: function(cell) {
                        //         cell.getElement().style.color = "#a80f8b"; // dark text
                        //         return cell.getValue();
                        //     }
                        // },

                        // ,







                        {
                            title: "T sales",
                            field: "T_sales",
                            hozAlign: "center",
                            formatter: function(cell) {
                                const value = cell.getValue() || 0;
                                const row = cell.getRow().getData();
                                const months = {
                                    Jan: row.Jan || 0,
                                    Feb: row.Feb || 0,
                                    Mar: row.Mar || 0,
                                    Apr: row.Apr || 0,
                                    May: row.May || 0,
                                    Jun: row.Jun || 0,
                                    Jul: row.Jul || 0,
                                    Aug: row.Aug || 0,
                                    Sep: row.Sep || 0,
                                    Oct: row.Oct || 0,
                                    Nov: row.Nov || 0,
                                    Dec: row.Dec || 0,
                                };
                                const payload = encodeURIComponent(JSON.stringify(months));
                                return `<span style='font-weight:600;'>${value}</span> &nbsp; <i class='fa fa-eye monthly-eye' data-months='${payload}' data-sku='${row.SKU}' style='cursor:pointer; color:#3b7ddd;' title='View monthly breakdown'></i>`;
                            }
                        },

                        {
                            title: "NRL FBA",
                            field: "NRL_FBA",
                            hozAlign: "center",
                            editor: "list",
                            editorParams: {
                                values: ["All", "FBA", "FBM", "NRL", "Both"],
                                autocomplete: true,
                                allowEmpty: false,
                                listOnEmpty: true
                            },
                            formatter: function(cell) {
                                const rowData = cell.getRow().getData();
                                // Don't show value for parent rows
                                if (rowData.is_parent) {
                                    return '';
                                }
                                const value = cell.getValue() || 'FBA';
                                const colorMap = {
                                    'All': '#808080', // Gray
                                    'FBA': '#28a745', // Green
                                    'FBM': '#007bff', // Blue
                                    'NRL': '#ffc107', // Yellow/Orange
                                    'Both': '#6f42c1' // Purple
                                };
                                const color = colorMap[value] ||
                                '#28a745'; // Default to green (FBA) if unknown
                                return `<span style="color: ${color}; font-weight: 700;">${value}</span>`;
                            },
                            cellEdited: function(cell) {
                                var data = cell.getRow().getData();
                                var value = cell.getValue();

                                // Skip if it's a parent row
                                if (data.is_parent) {
                                    cell.setValue('FBA');
                                    return;
                                }

                                $.ajax({
                                    url: '/update-fba-listing-status',
                                    method: 'POST',
                                    data: {
                                        sku: data.SKU,
                                        status: value,
                                        _token: '{{ csrf_token() }}'
                                    },
                                    success: function(response) {
                                        console.log('NRL FBA status updated:', response);
                                        if (response.success) {
                                            console.log('✅ Saved to database - SKU:', data
                                                .SKU, 'Status:', value);
                                        }
                                    },
                                    error: function(xhr) {
                                        console.error('Error updating NRL FBA status:',
                                        xhr);
                                        var errorMsg = 'Failed to update NRL FBA status';
                                        if (xhr.responseJSON && xhr.responseJSON.message) {
                                            errorMsg = xhr.responseJSON.message;
                                        }
                                        alert(errorMsg);
                                        // Revert to old value on error
                                        cell.setValue(cell.getOldValue() || 'All');
                                    }
                                });
                            }
                        },

                        // Jan–Dec columns intentionally removed — values are available in modal via the T_sales eye icon
                    ]
                });

                function escapeHistoryText(value) {
                    return String(value ?? '')
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;');
                }

                let historyPopEl = null;
                let historyPopHideTimer = null;

                function hideHistoryPop() {
                    if (historyPopHideTimer) {
                        clearTimeout(historyPopHideTimer);
                        historyPopHideTimer = null;
                    }
                    if (historyPopEl) {
                        historyPopEl.remove();
                        historyPopEl = null;
                    }
                }

                function scheduleHideHistoryPop() {
                    if (historyPopHideTimer) clearTimeout(historyPopHideTimer);
                    historyPopHideTimer = setTimeout(hideHistoryPop, 180);
                }

                function showHistoryPop(cell) {
                    hideHistoryPop();
                    const rowData = cell.getRow().getData();
                    if (rowData.is_parent) return;

                    const history = parseSendHistory(cell.getValue());
                    const pop = document.createElement('div');
                    pop.className = 'history-pop';

                    let html = '<div class="history-pop-title">History</div>';
                    if (history.length > 0) {
                        html += '<div class="history-pop-list">';
                        history.forEach(h => {
                            const action = h.action || '';
                            const color = action === 'Cleared' ? '#dc3545' : '#28a745';
                            html += `<div><span style="color:${color}; font-weight:600;">${escapeHistoryText(action)}</span> — ${escapeHistoryText(h.user || '')} <span class="text-muted">(${escapeHistoryText(h.date || '')})</span></div>`;
                        });
                        html += '</div>';
                    } else {
                        html += '<div class="text-muted">No history</div>';
                    }
                    html += '<button type="button" class="btn btn-sm btn-outline-danger send-history-clear mt-1" style="font-size:10px; padding:1px 6px;">Clear</button>';
                    pop.innerHTML = html;
                    document.body.appendChild(pop);

                    const rect = cell.getElement().getBoundingClientRect();
                    const popWidth = pop.offsetWidth;
                    const popHeight = pop.offsetHeight;
                    let left = rect.left + (rect.width / 2) - (popWidth / 2);
                    let top = rect.bottom + 4;
                    if (left + popWidth > window.innerWidth - 8) left = window.innerWidth - popWidth - 8;
                    if (left < 8) left = 8;
                    if (top + popHeight > window.innerHeight - 8) top = Math.max(8, rect.top - popHeight - 4);
                    pop.style.left = left + 'px';
                    pop.style.top = top + 'px';

                    pop.addEventListener('mouseenter', function() {
                        if (historyPopHideTimer) {
                            clearTimeout(historyPopHideTimer);
                            historyPopHideTimer = null;
                        }
                    });
                    pop.addEventListener('mouseleave', scheduleHideHistoryPop);
                    pop.querySelector('.send-history-clear').addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        appendSendHistory(cell.getRow(), 'Cleared');
                        hideHistoryPop();
                    });

                    historyPopEl = pop;
                }

                table.on('cellMouseEnter', function(e, cell) {
                    if (cell.getField() !== 'Send_History') return;
                    showHistoryPop(cell);
                });
                table.on('cellMouseLeave', function(e, cell) {
                    if (cell.getField() !== 'Send_History') return;
                    scheduleHideHistoryPop();
                });
                table.on('scrollVertical', hideHistoryPop);
                table.on('scrollHorizontal', hideHistoryPop);
                table.on('pageLoaded', hideHistoryPop);

                table.on('cellEdited', function(cell) {
                    var row = cell.getRow();
                    var data = row.getData();
                    var field = cell.getColumn().getField();
                    var value = cell.getValue();

                    // Skip fields that have specific cellEdited handlers (Length, Width, Height, Quantity_in_each_box, GW_CTN, Shipping_Amount)
                    if (field === 'Length' || field === 'Width' || field === 'Height' || 
                        field === 'Quantity_in_each_box' || field === 'GW_CTN' || field === 'Shipping_Amount') {
                        return; // These fields have specific handlers, skip general handler
                    }
                    
                    if (field === 'Barcode' || field === 'Done' || field === 'Listed' || field === 'Live' ||
                        field === 'Dispatch_Date' || field === 'Weight' || field === 'WH_ACT' ||
                        field === 'Total_quantity_sent' || field ===
                        'IN_Charges' ||
                        field === 'Warehouse_INV_Reduction' || field ===
                        'Inbound_Quantity' || field === 'FBA_Send' || field ===
                        'FBA_Fee_Manual' || field === 'MSL' || field === 'Correct_Cost' || field ===
                        'Approval' || field === 'Profit_is_ok' || field === 'UPC_Codes' || field === 'Commission_Percentage' ||
                        field === 'Send_Qty' || field === 'Send_History') {
                        
                        // Convert field name to lowercase for backend (UPC_Codes -> upc_codes)
                        var fieldName = field.toLowerCase();
                        
                        // Handle empty values properly - send empty string if cleared
                        var processedValue = value;
                        if (processedValue === null || processedValue === undefined) {
                            processedValue = '';
                        } else if (typeof processedValue === 'string') {
                            processedValue = processedValue.trim();
                        }
                        
                        $.ajax({
                            url: '/update-fba-manual-data',
                            method: 'POST',
                            data: {
                                sku: data.FBA_SKU || data.SKU,
                                field: fieldName,
                                value: processedValue,
                                _token: '{{ csrf_token() }}'
                            },
                            success: function(response) {
                                console.log('Data saved successfully for field:', fieldName, 'value:', processedValue);
                            },
                            error: function(xhr) {
                                console.error('Error saving data for field:', fieldName, 'Error:', xhr.responseText);
                                alert('Failed to save ' + field + '. Please try again.');
                            }
                        });
                    }
                });

                // Update badge when data loads
                table.on('dataLoaded', function() {
                    updateSuggSendBadge();
                });

                // Update badge after filtering
                table.on('dataFiltered', function() {
                    updateSuggSendBadge();
                });

                // INV 0 and More than 0 Filter
                let skuSearch = '';
                let parentSearch = '';

                function applyFilters() {
                    const nrlFbaFilter = $('#nrl-fba-filter').val();
                    const suggSendFilter = $('#sugg-send-filter').val();

                    table.clearFilter(true);

                    // SKU Search Filter
                    if (skuSearch) {
                        table.addFilter(function(data) {
                            const sku = (data.SKU || '').toUpperCase();
                            const fbaSku = (data.FBA_SKU || '').toUpperCase();
                            return sku.includes(skuSearch) || fbaSku.includes(skuSearch);
                        });
                    }

                    // Parent Search Filter (matches the Parent of each row)
                    if (parentSearch) {
                        table.addFilter(function(data) {
                            return String(data.Parent || '').toUpperCase().includes(parentSearch);
                        });
                    }

                    // Parents are always hidden
                    table.addFilter(function(data) {
                        return data.is_parent !== true;
                    });

                    // Hide rows where Dil is above 49% by default
                    table.addFilter(function(data) {
                        const dil = parseFloat(data.Dil);
                        return isNaN(dil) || dil <= 49;
                    });

                    // Hide rows where Main INV is 0 or negative.
                    // Negative Main INV must not appear in Suggested Send.
                    table.addFilter(function(data) {
                        const mainInv = parseFloat(data.Shopify_INV);
                        if (isNaN(mainInv)) return true;
                        return mainInv > 0;
                    });

                    if (nrlFbaFilter !== 'all') {
                        console.log('Applying NRL FBA filter:', nrlFbaFilter);
                        table.addFilter('NRL_FBA', '=', nrlFbaFilter);
                    } else {
                        console.log('NRL FBA filter is "all" - not applying filter');
                    }

                    // Send Qty filter: Blank = Send Qty column is empty, >0 = Send Qty (typed or auto-filled) above 0
                    if (suggSendFilter === 'blank') {
                        table.addFilter(function(data) {
                            return !fbaSendQtyEffective(data);
                        });
                    } else if (suggSendFilter === 'positive') {
                        table.addFilter(function(data) {
                            return fbaSendQtyPositive(data);
                        });
                    }
                }

                // Effective Sugg Send value (mirrors the Sugg Send column formatter rules)
                function getEffectiveSuggSend(data) {
                    let value = parseFloat(data.Sugg_Send);
                    if (isNaN(value)) return NaN;

                    const dil = parseFloat(data.Dil);
                    if (!isNaN(dil) && dil > 50) value = 0;

                    const compliance = (data.compliance || 'green').toString().toLowerCase();
                    if (compliance === 'red') value = 0;

                    const rating = parseFloat(data.AMZ_Rating);
                    if (!isNaN(rating) && rating > 0 && rating < 3.5) value = 0;

                    const mainInv = parseFloat(data.Shopify_INV);
                    if (!isNaN(mainInv) && mainInv < 0) return NaN;

                    const ctnQty = parseFloat(data.CTN_QTY);
                    if (!isNaN(ctnQty) && ctnQty > 0 && !isNaN(mainInv) && mainInv < ctnQty) value = 0;

                    return value;
                }

                function updateSuggSendBadge() {
                    setTimeout(function() {
                        try {
                            const allData = table.getData('active');
                            const positiveCount = allData.filter(row => {
                                if (row.is_parent) return false;
                                const value = parseFloat(row.Sugg_Send);
                                return !isNaN(value) && value > 0;
                            }).length;

                            $('#sugg-send-count').text(positiveCount);

                            // Count of rows visible in the Sugg Send column
                            // (non-parent active rows that display a value)
                            const visibleRowsCount = allData.filter(row => {
                                if (row.is_parent) return false;
                                return fbaSendQtyPositive(row);
                            }).length;

                            $('#sugg-send-rows-count').text(visibleRowsCount);
                        } catch (e) {
                            console.log('Badge update error:', e);
                        }
                    }, 100);
                }

                $('#sku-search').on('input', function() {
                    skuSearch = $(this).val().toUpperCase();
                    applyFilters();
                    updateSuggSendBadge();
                });

                $('#parent-search').on('input', function() {
                    parentSearch = $(this).val().trim().toUpperCase();
                    applyFilters();
                    updateSuggSendBadge();
                });

                // Send Qty rule: typed in the toolbar, applied on Enter / when leaving the box
                // ===== Send Qty Rule window: a table of conditions (Join, Column, Condition, Value, live Count) =====
                const SQR_ROWS_KEY = 'fba_send_qty_rule_rows_v1';
                const SQR_OPS = [['<', '<  less than'], ['<=', '<=  less than or equal'], ['>', '>  greater than'], ['>=', '>=  greater than or equal'], ['=', '=  equal to'], ['!=', '!=  not equal to']];
                const SQR_MAIN_FIELDS = ['Dil', 'Shopify_INV', 'Quantity_in_each_box', 'AMZ_Rating', 'Shopify_OV_L30', 'FBA_Quantity', 'Inbound_Quantity', 'l30_units', 'AMZ_L30', 'FBA_Dil', 'MSL', 'Length', 'Width', 'Height', 'GW_CTN', 'Shipping_Amount', 'Sugg_Send'];
                const SQR_HIDE_FIELDS = new Set(['image_path', '_select', '_accept', '_details', 'Parent', 'SKU', 'FBA_SKU', 'Barcode', 'Done', 'Listed', 'Live', 'Dispatch_Date', 'UPC_Codes', 'UPC_Edit', 'Send_History', 'Total_quantity_sent', 'compliance']);
                let sqrRows = [];
                let sqrCountTimer = null;

                function sqrEsc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
                function sqrColumns() {
                    const out = [], seen = {};
                    table.getColumns().forEach(function(col) {
                        const d = col.getDefinition();
                        if (!d.field || seen[d.field] || SQR_HIDE_FIELDS.has(d.field)) return;
                        seen[d.field] = true;
                        const title = String(d.title == null ? d.field : d.title).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim() || d.field;
                        out.push({ field: d.field, title: title });
                    });
                    return out;
                }
                function sqrTitle(field) {
                    const c = sqrColumns().filter(function(x) { return x.field === field; })[0];
                    return c ? c.title : field;
                }
                function sqrValueOk(v) { return /^-?\d+(\.\d+)?%?$/.test(String(v == null ? '' : v).trim()); }
                function sqrRowOk(r) {
                    if (!r || !r.field || !SQR_OPS.some(function(o) { return o[0] === r.op; })) return false;
                    return r.vtype === 'col' ? String(r.value == null ? '' : r.value).trim() !== '' : sqrValueOk(r.value);
                }
                /** Right side of a condition: a number, or the other column (field name for the engine, title for people). */
                function sqrRight(r, useTitle) { return r.vtype === 'col' ? (useTitle ? sqrTitle(r.value) : String(r.value)) : sqrValueText(r.value); }
                function sqrValueText(v) { return String(v).trim().replace(/%$/, ''); }
                /** The rule as text, e.g. "Dil < 50 and Shopify_INV > 0" (fields for the engine, titles for people). */
                function sqrText(rows, useTitle) {
                    return rows.map(function(r, i) {
                        return (i ? ' ' + (r.join === 'or' ? 'or' : 'and') + ' ' : '') + (useTitle ? sqrTitle(r.field) : r.field) + ' ' + r.op + ' ' + sqrRight(r, useTitle);
                    }).join('');
                }
                /** "Dil < 50 and Main INV > 0" -> rows. Returns null when the text is not a plain list of conditions. */
                function sqrParseText(text) {
                    const src = String(text || '').trim();
                    if (!src) return null;
                    const parts = src.split(/\s+(and|or)\s+/i);
                    const map = fbaRuleColumnMap();
                    const rows = [];
                    for (let i = 0; i < parts.length; i += 2) {
                        const m = parts[i].trim().match(/^(.+?)\s*(<=|>=|!=|==|=|<|>)\s*(.+)$/);
                        if (!m) return null;
                        const field = map[fbaRuleKey(m[1])];
                        if (!field) return null;
                        const rhs = m[3].trim();
                        const row = { join: i === 0 ? 'and' : String(parts[i - 1]).toLowerCase(), field: field, op: m[2] === '==' ? '=' : m[2], vtype: 'num', value: '' };
                        if (/^-?\d+(\.\d+)?%?$/.test(rhs)) {
                            row.value = rhs.replace(/%$/, '');
                        } else if (map[fbaRuleKey(rhs)]) {
                            row.vtype = 'col';
                            row.value = map[fbaRuleKey(rhs)];
                        } else {
                            return null;
                        }
                        rows.push(row);
                    }
                    return rows.length ? rows : null;
                }
                function sqrDefaultRows() { return sqrParseText(FBA_SEND_QTY_DEFAULT_RULE) || [{ join: 'and', field: 'Dil', op: '<', vtype: 'num', value: '50' }]; }
                function sqrLoadRows() {
                    try {
                        const p = JSON.parse(localStorage.getItem(SQR_ROWS_KEY) || 'null');
                        if (Array.isArray(p) && p.length && p.every(sqrRowOk)) {
                            return p.map(function(r) { return { join: r.join === 'or' ? 'or' : 'and', field: r.field, op: r.op, vtype: r.vtype === 'col' ? 'col' : 'num', value: String(r.value) }; });
                        }
                    } catch (e) { /* ignore */ }
                    return sqrParseText(fbaSendQtyRuleText || FBA_SEND_QTY_DEFAULT_RULE) || sqrDefaultRows();
                }
                function sqrCtnRows() {
                    return table.getData().filter(function(r) {
                        if (r.is_parent) return false;
                        const q = r.Quantity_in_each_box;
                        return q !== null && q !== undefined && String(q).trim() !== '';
                    });
                }
                function setSendQtyRuleStatus(kind, html) {
                    const box = document.getElementById('send-qty-rule-status');
                    if (box) { box.className = 'sqr-status ' + kind; box.innerHTML = html; }
                }

                /** Quick-search picker used instead of a plain <select> for the column lists. */
                let sqrPicker = null;
                function sqrClosePicker() {
                    if (!sqrPicker) return;
                    sqrPicker.el.remove();
                    document.removeEventListener('mousedown', sqrPicker.out, true);
                    window.removeEventListener('resize', sqrClosePicker);
                    sqrPicker = null;
                }
                function sqrOpenPicker(sel, btn, refreshLabel) {
                    if (sqrPicker && sqrPicker.btn === btn) { sqrClosePicker(); return; }
                    sqrClosePicker();
                    const items = Array.prototype.map.call(sel.querySelectorAll('option'), function(o) {
                        return { value: o.value, text: o.textContent, group: o.parentNode.tagName === 'OPTGROUP' ? o.parentNode.label : '' };
                    });
                    const el = document.createElement('div');
                    el.className = 'sqr-picker';
                    el.innerHTML = '<input type="text" class="form-control form-control-sm sqr-picker-q" placeholder="Quick search column..." autocomplete="off"><div class="sqr-picker-list"></div>';
                    (document.getElementById('sendQtyRuleModal') || document.body).appendChild(el);
                    const input = el.querySelector('input');
                    const list = el.querySelector('.sqr-picker-list');
                    let shown = [], active = 0;

                    const place = function() {
                        const r = btn.getBoundingClientRect();
                        el.style.width = Math.max(r.width, 280) + 'px';
                        el.style.left = Math.max(8, Math.min(r.left, window.innerWidth - el.offsetWidth - 8)) + 'px';
                        const below = r.bottom + 4;
                        el.style.top = (below + el.offsetHeight > window.innerHeight - 8 ? Math.max(8, r.top - el.offsetHeight - 4) : below) + 'px';
                    };
                    const draw = function() {
                        const words = input.value.toLowerCase().split(/\s+/).filter(Boolean);
                        shown = items.filter(function(it) {
                            const hay = (it.text + ' ' + it.value).toLowerCase();
                            return words.every(function(w) { return hay.indexOf(w) !== -1; });
                        });
                        if (active >= shown.length) active = Math.max(0, shown.length - 1);
                        let html = '', lastGroup = null;
                        shown.forEach(function(it, i) {
                            if (it.group && it.group !== lastGroup) html += '<div class="sqr-picker-group">' + sqrEsc(it.group) + '</div>';
                            lastGroup = it.group;
                            html += '<div class="sqr-picker-item' + (i === active ? ' is-active' : '') + (it.value === sel.value ? ' is-selected' : '') + '" data-i="' + i + '">' + sqrEsc(it.text) + '</div>';
                        });
                        list.innerHTML = html || '<div class="sqr-picker-empty">No column found</div>';
                        const a = list.querySelector('.is-active');
                        if (a) a.scrollIntoView({ block: 'nearest' });
                    };
                    const pick = function(i) {
                        const it = shown[i];
                        if (!it) return;
                        sel.value = it.value;
                        refreshLabel();
                        sqrClosePicker();
                        sel.dispatchEvent(new Event('change', { bubbles: true }));
                        btn.focus();
                    };

                    input.addEventListener('input', function() { active = 0; draw(); });
                    input.addEventListener('keydown', function(e) {
                        if (e.key === 'ArrowDown') { e.preventDefault(); active = Math.min(shown.length - 1, active + 1); draw(); }
                        else if (e.key === 'ArrowUp') { e.preventDefault(); active = Math.max(0, active - 1); draw(); }
                        else if (e.key === 'Enter') { e.preventDefault(); pick(active); }
                        else if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); sqrClosePicker(); btn.focus(); }
                    });
                    list.addEventListener('mousedown', function(e) {
                        const t = e.target.closest('.sqr-picker-item');
                        if (!t) return;
                        e.preventDefault();
                        pick(parseInt(t.getAttribute('data-i'), 10));
                    });
                    list.addEventListener('mousemove', function(e) {
                        const t = e.target.closest('.sqr-picker-item');
                        if (!t) return;
                        const i = parseInt(t.getAttribute('data-i'), 10);
                        if (i !== active) {
                            active = i;
                            Array.prototype.forEach.call(list.querySelectorAll('.sqr-picker-item'), function(n) { n.classList.toggle('is-active', parseInt(n.getAttribute('data-i'), 10) === i); });
                        }
                    });
                    const out = function(e) { if (!el.contains(e.target) && !btn.contains(e.target)) sqrClosePicker(); };
                    document.addEventListener('mousedown', out, true);
                    window.addEventListener('resize', sqrClosePicker);
                    sqrPicker = { el: el, btn: btn, out: out };
                    draw();
                    place();
                    input.focus();
                }
                function sqrEnhanceSelects() {
                    Array.prototype.forEach.call(document.querySelectorAll('#sqr-rows select.sqr-field, #sqr-rows select.sqr-valcol'), function(sel) {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'form-select form-select-sm sqr-pick';
                        btn.title = 'Click, then type to search';
                        const refresh = function() { btn.textContent = sel.selectedOptions[0] ? sel.selectedOptions[0].textContent : ''; };
                        refresh();
                        sel.style.display = 'none';
                        sel.parentNode.insertBefore(btn, sel);
                        btn.addEventListener('click', function() { sqrOpenPicker(sel, btn, refresh); });
                    });
                }
                function sqrRender() {
                    const cols = sqrColumns();
                    const mainSet = SQR_MAIN_FIELDS.filter(function(f) { return cols.some(function(c) { return c.field === f; }); });
                    const options = function(selected) {
                        const opt = function(c) { return '<option value="' + sqrEsc(c.field) + '"' + (c.field === selected ? ' selected' : '') + '>' + sqrEsc(c.title) + '</option>'; };
                        let main = '', other = '', found = false;
                        mainSet.forEach(function(f) { const c = cols.filter(function(x) { return x.field === f; })[0]; main += opt(c); if (f === selected) found = true; });
                        cols.forEach(function(c) { if (mainSet.indexOf(c.field) === -1) { other += opt(c); if (c.field === selected) found = true; } });
                        const extra = found ? '' : '<option value="' + sqrEsc(selected) + '" selected>' + sqrEsc(selected) + '</option>';
                        return extra + '<optgroup label="Main columns">' + main + '</optgroup><optgroup label="Other columns">' + other + '</optgroup>';
                    };
                    const html = sqrRows.map(function(r, i) {
                        const join = i === 0
                            ? '<span class="sqr-if">IF</span>'
                            : '<select class="form-select form-select-sm sqr-join"><option value="and"' + (r.join !== 'or' ? ' selected' : '') + '>AND</option><option value="or"' + (r.join === 'or' ? ' selected' : '') + '>OR</option></select>';
                        const ops = SQR_OPS.map(function(o) { return '<option value="' + sqrEsc(o[0]) + '"' + (o[0] === r.op ? ' selected' : '') + '>' + sqrEsc(o[1]) + '</option>'; }).join('');
                        return '<tr data-i="' + i + '"><td class="text-muted fw-bold">' + (i + 1) + '</td>'
                            + '<td>' + join + '</td>'
                            + '<td><select class="form-select form-select-sm sqr-field">' + options(r.field) + '</select></td>'
                            + '<td><select class="form-select form-select-sm sqr-op">' + ops + '</select></td>'
                            + '<td><select class="form-select form-select-sm sqr-vtype"><option value="num"' + (r.vtype !== 'col' ? ' selected' : '') + '>Number</option><option value="col"' + (r.vtype === 'col' ? ' selected' : '') + '>Column</option></select></td>'
                            + (r.vtype === 'col'
                                ? '<td><select class="form-select form-select-sm sqr-valcol">' + options(r.value) + '</select></td>'
                                : '<td><input type="text" class="form-control form-control-sm sqr-value' + (sqrValueOk(r.value) ? '' : ' is-invalid') + '" value="' + sqrEsc(r.value) + '" placeholder="number" autocomplete="off"></td>')
                            + '<td class="text-end sqr-count"></td>'
                            + '<td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger sqr-del" title="Remove this condition"' + (sqrRows.length < 2 ? ' disabled' : '') + '><i class="fa fa-trash"></i></button></td></tr>';
                    }).join('');
                    sqrClosePicker();
                    document.getElementById('sqr-rows').innerHTML = html;
                    sqrEnhanceSelects();
                }
                /** Fill every line's Count and the combined total (no re-render, so typing keeps its focus). */
                function sqrUpdateCounts() {
                    const data = sqrCtnRows();
                    const total = data.length;
                    const tb = document.getElementById('sqr-rows');
                    const countOf = function(text) {
                        const fn = fbaCompileRule(text);
                        let n = 0;
                        data.forEach(function(d) { try { if (fn(d)) n++; } catch (e) { /* ignore */ } });
                        return n;
                    };
                    sqrRows.forEach(function(r, i) {
                        const cell = tb.querySelector('tr[data-i="' + i + '"] .sqr-count');
                        if (!cell) return;
                        if (!sqrRowOk(r)) { cell.innerHTML = '<span class="text-muted">-</span>'; return; }
                        try { cell.innerHTML = '<b>' + countOf(r.field + ' ' + r.op + ' ' + sqrRight(r, false)) + '</b><span class="text-muted"> / ' + total + '</span>'; }
                        catch (e) { cell.innerHTML = '<span class="text-danger">?</span>'; }
                    });
                    const totalCell = document.getElementById('sqr-total-count');
                    const preview = document.getElementById('sqr-preview');
                    if (!sqrRows.every(sqrRowOk)) {
                        if (totalCell) totalCell.innerHTML = '<span class="text-muted">-</span>';
                        if (preview) preview.textContent = '...';
                        setSendQtyRuleStatus('bad', '<i class="fa fa-circle-xmark me-1"></i>Fix the red value: it must be a number, e.g. 50 (or pick Compare with: Column)');
                        return;
                    }
                    try {
                        const n = countOf(sqrText(sqrRows, false));
                        if (totalCell) totalCell.innerHTML = '<span class="text-success">' + n + '</span><span class="text-muted"> / ' + total + '</span>';
                        if (preview) preview.textContent = 'Send Qty = Qty CTN when  ' + sqrText(sqrRows, true);
                        setSendQtyRuleStatus('ok', '<i class="fa fa-circle-check me-1"></i>Rule understood. Auto-fills <b>' + n + '</b> of ' + total + ' rows that have a Qty CTN.');
                    } catch (err) {
                        setSendQtyRuleStatus('bad', '<i class="fa fa-circle-xmark me-1"></i>' + sqrEsc(err.message));
                    }
                }
                function sqrScheduleCounts() {
                    clearTimeout(sqrCountTimer);
                    sqrCountTimer = setTimeout(sqrUpdateCounts, 150);
                }
                /** Use the rows as the live rule and save them. Returns true when the rule is in use. */
                function applySendQtyRuleFromInput() {
                    if (!sqrRows.every(sqrRowOk)) {
                        sqrRender();
                        sqrUpdateCounts();
                        return false;
                    }
                    const text = sqrText(sqrRows, false);
                    try {
                        const fn = fbaCompileRule(text);
                        fbaSendQtyRuleFn = fn;
                        fbaSendQtyRuleText = text;
                        fbaSendQtyRuleLabel = sqrText(sqrRows, true);
                        try {
                            localStorage.setItem(FBA_SEND_QTY_RULE_KEY, text);
                            localStorage.setItem(SQR_ROWS_KEY, JSON.stringify(sqrRows));
                        } catch (e) { /* ignore */ }
                        table.redraw(true);
                        applyFilters();
                        updateSuggSendBadge();
                        showToast('Send Qty rule applied: ' + sqrEsc(fbaSendQtyRuleLabel), 'success');
                        return true;
                    } catch (err) {
                        setSendQtyRuleStatus('bad', '<i class="fa fa-circle-xmark me-1"></i>Not applied: ' + sqrEsc(err.message));
                        return false;
                    }
                }
                $('#sendQtyRuleModal').on('hide.bs.modal', sqrClosePicker);
                $('#sendQtyRuleModal').on('show.bs.modal', function() {
                    $(this).appendTo('body');
                    $(this).css('z-index', '1060');
                    fbaSendQtyEnsureRule();                     // makes sure the saved rule is loaded
                    sqrRows = sqrLoadRows();
                    sqrRender();
                    sqrUpdateCounts();
                });
                $('#sqr-rows').on('change', '.sqr-vtype', function() {
                    const i = parseInt($(this).closest('tr').attr('data-i'), 10);
                    if (!sqrRows[i]) return;
                    sqrRows[i].vtype = this.value === 'col' ? 'col' : 'num';
                    sqrRows[i].value = sqrRows[i].vtype === 'col' ? (sqrRows[i].field === 'Quantity_in_each_box' ? 'Shopify_INV' : 'Quantity_in_each_box') : '0';
                    sqrRender();
                    sqrUpdateCounts();
                }).on('change', '.sqr-valcol', function() {
                    const i = parseInt($(this).closest('tr').attr('data-i'), 10);
                    if (!sqrRows[i]) return;
                    sqrRows[i].value = this.value;
                    sqrUpdateCounts();
                }).on('change', '.sqr-join, .sqr-field, .sqr-op', function() {
                    const i = parseInt($(this).closest('tr').attr('data-i'), 10);
                    if (!sqrRows[i]) return;
                    if ($(this).hasClass('sqr-join')) sqrRows[i].join = this.value;
                    else if ($(this).hasClass('sqr-field')) sqrRows[i].field = this.value;
                    else sqrRows[i].op = this.value;
                    sqrUpdateCounts();
                }).on('input', '.sqr-value', function() {
                    const i = parseInt($(this).closest('tr').attr('data-i'), 10);
                    if (!sqrRows[i]) return;
                    sqrRows[i].value = this.value;
                    this.classList.toggle('is-invalid', !sqrValueOk(this.value));
                    sqrScheduleCounts();
                }).on('keydown', '.sqr-value', function(e) {
                    if (e.key === 'Enter') { e.preventDefault(); $('#send-qty-rule-apply').trigger('click'); }
                }).on('click', '.sqr-del', function() {
                    const i = parseInt($(this).closest('tr').attr('data-i'), 10);
                    if (sqrRows.length < 2 || !sqrRows[i]) return;
                    sqrRows.splice(i, 1);
                    sqrRender();
                    sqrUpdateCounts();
                });
                $('#sqr-add').on('click', function() {
                    sqrRows.push({ join: 'and', field: 'Shopify_INV', op: '>', vtype: 'num', value: '0' });
                    sqrRender();
                    sqrUpdateCounts();
                });
                $('#send-qty-rule-apply').on('click', function() {
                    if (applySendQtyRuleFromInput()) bootstrap.Modal.getOrCreateInstance(document.getElementById('sendQtyRuleModal')).hide();
                });
                $('#send-qty-rule-default').on('click', function() {
                    sqrRows = sqrDefaultRows();
                    sqrRender();
                    if (applySendQtyRuleFromInput()) bootstrap.Modal.getOrCreateInstance(document.getElementById('sendQtyRuleModal')).hide();
                });
                $(document).on('click', '.send-qty-rule-example', function() {
                    sqrRows = sqrParseText($(this).attr('data-rule')) || sqrDefaultRows();
                    sqrRender();
                    sqrUpdateCounts();
                });

                $('#nrl-fba-filter').on('change', function() {
                    const selectedValue = $(this).val();
                    console.log('NRL FBA Filter changed to:', selectedValue);
                    applyFilters();
                    updateSuggSendBadge();
                });

                // Keep the Send filter and count right after a Send Qty is typed / changed
                table.on('cellEdited', function(cell) {
                    if (cell.getColumn().getField() !== 'Send_Qty') return;
                    // Flag a typed value as manual so it survives reloads; a cleared value goes back to the rule's auto-fill.
                    const typed = String(cell.getValue() == null ? '' : cell.getValue()).trim();
                    const rd = cell.getRow().getData();
                    $.ajax({
                        url: '/update-fba-manual-data',
                        method: 'POST',
                        data: { sku: rd.FBA_SKU || rd.SKU, field: 'send_qty_manual', value: typed === '' ? '' : '1', _token: '{{ csrf_token() }}' }
                    });
                    setTimeout(function() {
                        if ($('#sugg-send-filter').val() !== 'all') applyFilters();
                        updateSuggSendBadge();
                    }, 0);
                });

                $('#sugg-send-filter').on('change', function() {
                    applyFilters();
                    updateSuggSendBadge();
                });

                // Reset All Filters button - shows ALL data
                $('#reset-all-filters-btn').on('click', function() {
                    console.log('Reset All Filters clicked - showing ALL data');
                    $('#nrl-fba-filter').val('all');
                    $('#sugg-send-filter').val('all');
                    $('#sku-search').val('');
                    skuSearch = '';
                    $('#parent-search').val('');
                    parentSearch = '';
                    applyFilters();
                    updateSuggSendBadge();
                    showToast('All filters reset - showing all data', 'info');
                });

                // Apply filters on initial load (to hide parents by default)
                applyFilters();

                // Fix import modal z-index when opened
                $('#importModal').on('show.bs.modal', function() {
                    $(this).appendTo('body');
                    $(this).css('z-index', '1050');
                });

                // AJAX Import Handler
                $('#importForm').on('submit', function(e) {
                    e.preventDefault();

                    const formData = new FormData();
                    const file = $('#csvFile')[0].files[0];

                    if (!file) return;

                    formData.append('file', file);
                    formData.append('_token', '{{ csrf_token() }}');

                    const uploadBtn = $('#uploadBtn');
                    uploadBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Importing...');

                    $.ajax({
                        url: '/fba-manual-import',
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function(response) {
                            if (response.success) {
                                showToast(response.message, 'success');
                                $('#importModal').modal('hide');
                                $('#importForm')[0].reset();
                                table.setData('/fba-data-json');
                            }
                        },
                        error: function(xhr) {
                            const errorMsg = xhr.responseJSON?.message || 'Import failed';
                            showToast(errorMsg, 'error');
                        },
                        complete: function() {
                            uploadBtn.prop('disabled', false).html(
                                '<i class="fa fa-upload"></i> Import');
                        }
                    });
                });

                // ===== Column manager: same panel as /amazon-tabulator-view =====
                // Headers (add / rename / delete), search & add column, drag to move / re-order, Ctrl/Cmd+C / +V, Save & Close.
                const FBA_COL_CHANNEL = 'fba_dispatch_tabulator';
                const FBA_COL_VIS_URL = '/tabulator-column-visibility';
                const FBA_COL_ORDER_URL = '/tabulator-column-order';
                const FBA_COL_LEGACY_VIS_URL = '/fba-dispatch-column-visibility';
                const FBA_COL_CSRF = '{{ csrf_token() }}';
                const FBA_COL_SKIP_FIELDS = new Set(['_select', '_accept', '_details']);
                // Hidden on a fresh layout (until you tick them in the Columns panel and save)
                const FBA_COL_DEFAULT_HIDDEN = ["ROI%", "S_Price", "SPft%", "SROI%", "lmp_1", "Length", "Width", "Height", "Quantity_in_each_box", "GW_CTN", "Shipping_Amount"];
                const FBA_COL_GROUP_FIELDS = {
                    basic: ['image_path', 'Parent', 'SKU', 'FBA_SKU', 'Sugg_Send', 'Shopify_INV', 'Barcode', 'Send_Qty', 'Total_quantity_sent', 'Shopify_OV_L30', 'Dil', 'FBA_Quantity', 'Inbound_Quantity', 'l30_units', 'AMZ_L30', 'FBA_Dil', 'MSL', 'AMZ_Rating', 'NRL_FBA', 'Current_Month_Views'],
                    price: ['FBA_Price', 'LMP', 'AMZ_Price', 'AMZ_GPFT', 'AMZ_AD', 'AMZ_NPFT', 'CVR_L30_amz', 'CVR_L60_amz', 'CVR_L45_amz', 'ROI%', 'GROI%', 'GPFT%', 'TPFT', 'FBA_ROI', 'S_Price', 'SPft%', 'SROI%', 'lmp_1', 'Fulfillment_Fee', 'FBA_Fee_Manual', 'T_sales'],
                    shipping: ['Length', 'Width', 'Height', 'Quantity_in_each_box', 'GW_CTN', 'Shipping_Amount', 'FBA_Ship_Calculation', 'Ship', 'Send_Cost']
                };

                const COL_VIS_BASE_KEYS = ['basic', 'price', 'shipping', 'other'];
                const COL_VIS_BASE_LABELS = { basic: 'Basic', price: 'Pricing', shipping: 'Shipping', other: 'Other' };

                function fbaColJsonStore(key, fallback) {
                    try {
                        const parsed = JSON.parse(localStorage.getItem(key) || 'null');
                        return parsed === null || parsed === undefined ? fallback : parsed;
                    } catch (e) { return fallback; }
                }
                function fbaColJsonSave(key, value) {
                    try { localStorage.setItem(key, JSON.stringify(value)); } catch (e) { /* ignore */ }
                }
                function fbaColIsPlainObject(o) { return o && typeof o === 'object' && !Array.isArray(o); }

                // Your own headers ("+ Add header"): [{ key: 'u_xxxx', label }]. Saved in this browser.
                const FBA_COL_GROUP_STORAGE = 'fba_dispatch_col_groups_v1';
                function loadFbaColGroups() {
                    const parsed = fbaColJsonStore(FBA_COL_GROUP_STORAGE, []);
                    return (Array.isArray(parsed) ? parsed : []).filter(function(g) {
                        return g && /^u_[a-z0-9]+$/.test(String(g.key || '')) && String(g.label || '').trim();
                    }).map(function(g) { return { key: String(g.key), label: String(g.label).trim().slice(0, 30) }; });
                }
                function saveFbaColGroups(list) { fbaColJsonSave(FBA_COL_GROUP_STORAGE, list || []); }
                function fbaColVisKeys() { return COL_VIS_BASE_KEYS.concat(loadFbaColGroups().map(function(g) { return g.key; })); }

                // New names for the built-in headers: { key: label }.
                const FBA_COL_LABEL_STORAGE = 'fba_dispatch_col_labels_v1';
                function loadFbaColLabels() {
                    const parsed = fbaColJsonStore(FBA_COL_LABEL_STORAGE, {});
                    return fbaColIsPlainObject(parsed) ? parsed : {};
                }
                function saveFbaColLabels(map) { fbaColJsonSave(FBA_COL_LABEL_STORAGE, map || {}); }
                function fbaColVisLabel(key) {
                    if (COL_VIS_BASE_LABELS[key]) {
                        const custom = String(loadFbaColLabels()[key] || '').trim();
                        return custom || COL_VIS_BASE_LABELS[key];
                    }
                    const g = loadFbaColGroups().filter(function(x) { return x.key === key; })[0];
                    return g ? g.label : key;
                }
                function fbaColVisIsCustom(key) { return COL_VIS_BASE_KEYS.indexOf(key) === -1; }

                function fbaColVisPlainTitle(def) {
                    const field = def && def.field ? String(def.field) : '';
                    const raw = (def && def.title != null) ? def.title : field;
                    const t = String(raw).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
                    return t || field;
                }
                function classifyFbaColumn(field) {
                    const f = String(field || '');
                    const keys = Object.keys(FBA_COL_GROUP_FIELDS);
                    for (let i = 0; i < keys.length; i++) {
                        if (FBA_COL_GROUP_FIELDS[keys[i]].indexOf(f) !== -1) return keys[i];
                    }
                    return 'other';
                }

                // Column moved to another header: { field: header key }.
                const FBA_COL_CAT_STORAGE = 'fba_dispatch_col_cats_v1';
                function loadFbaColCats() {
                    const parsed = fbaColJsonStore(FBA_COL_CAT_STORAGE, {});
                    return fbaColIsPlainObject(parsed) ? parsed : {};
                }
                function saveFbaColCats(map) { fbaColJsonSave(FBA_COL_CAT_STORAGE, map || {}); }

                // A column can also show under other headers: { field: [header keys] } (copies; the column stays one column).
                const FBA_COL_EXTRA_STORAGE = 'fba_dispatch_col_extra_v1';
                function loadFbaColExtras() {
                    const parsed = fbaColJsonStore(FBA_COL_EXTRA_STORAGE, {});
                    return fbaColIsPlainObject(parsed) ? parsed : {};
                }
                function saveFbaColExtras(map) { fbaColJsonSave(FBA_COL_EXTRA_STORAGE, map || {}); }
                function fbaColExtraAdd(field, cat) {
                    const ex = loadFbaColExtras();
                    const list = Array.isArray(ex[field]) ? ex[field] : [];
                    if (list.indexOf(cat) === -1) list.push(cat);
                    ex[field] = list;
                    saveFbaColExtras(ex);
                }
                function fbaColExtraRemove(field, cat) {
                    const ex = loadFbaColExtras();
                    if (!Array.isArray(ex[field])) return;
                    ex[field] = ex[field].filter(function(c) { return c !== cat; });
                    if (!ex[field].length) delete ex[field];
                    saveFbaColExtras(ex);
                }
                /** Same column under several headers: keep every checkbox of one field equal, and every header tick right. */
                function fbaColVisSyncField(menu, field, checked) {
                    menu.querySelectorAll('.col-vis-item input.col-vis-field-toggle').forEach(function(cb) {
                        if ((cb.getAttribute('data-field') || cb.value) === field) cb.checked = checked;
                    });
                    menu.querySelectorAll('.col-vis-group').forEach(syncFbaGroupHeaderCheckbox);
                }

                // Order of the columns inside each header: { header key: [fields] }.
                const FBA_COL_ORDER_STORAGE = 'fba_dispatch_col_order_v1';
                function loadFbaColOrder() {
                    const parsed = fbaColJsonStore(FBA_COL_ORDER_STORAGE, {});
                    return fbaColIsPlainObject(parsed) ? parsed : {};
                }
                function fbaColVisSaveOrder(list) {
                    if (!list) return;
                    const ord = loadFbaColOrder();
                    ord[list.dataset.category] = Array.from(list.children).filter(function(li) { return li.classList.contains('col-vis-item'); })
                        .map(function(li) { return li.dataset.field; });
                    fbaColJsonSave(FBA_COL_ORDER_STORAGE, ord);
                }
                /** Put the columns of every header in their saved order (unplaced columns keep their order, after the placed ones). */
                function fbaColVisApplyOrder(lists) {
                    const ord = loadFbaColOrder();
                    Object.keys(lists).forEach(function(cat) {
                        const saved = ord[cat];
                        if (!Array.isArray(saved)) return;
                        const rank = function(f) { const i = saved.indexOf(f); return i < 0 ? 1e6 : i; };
                        Array.from(lists[cat].children).map(function(li, i) { return { li: li, i: i }; })
                            .sort(function(a, b) { return (rank(a.li.dataset.field) - rank(b.li.dataset.field)) || (a.i - b.i); })
                            .forEach(function(o) { lists[cat].appendChild(o.li); });
                    });
                }

                let fbaColVisDropRef = null;      // { li, after }: where a dragged column will land
                let fbaColVisActiveCat = '';      // header that Ctrl+V pastes into
                let fbaColVisFlashMsg = '';       // one-time message shown after the menu is rebuilt
                let fbaApplyingColumnOrder = false;
                let fbaColumnOrderSaveTimer = null;
                let fbaArrangeTimer = null;

                function fbaColVisClearDropMarks(root) {
                    (root || document).querySelectorAll('.col-vis-drop-before, .col-vis-drop-after').forEach(function(el) {
                        el.classList.remove('col-vis-drop-before', 'col-vis-drop-after');
                    });
                }
                function bindFbaColVisDrag(li, groupEls) {
                    li.draggable = true;
                    li.addEventListener('dragstart', function(e) {
                        e.stopPropagation();
                        li.classList.add('col-vis-dragging');
                        e.dataTransfer.setData('text/plain', li.dataset.field || '');
                        e.dataTransfer.setData('text/col-vis-field', li.dataset.field || '');
                        e.dataTransfer.setData('text/col-vis-from', li.dataset.group || '');
                        e.dataTransfer.effectAllowed = 'move';
                    });
                    li.addEventListener('dragover', function(e) {
                        e.preventDefault();
                        const r = li.getBoundingClientRect();
                        const after = (e.clientY - r.top) > r.height / 2;
                        fbaColVisClearDropMarks(li.parentNode);
                        li.classList.add(after ? 'col-vis-drop-after' : 'col-vis-drop-before');
                        fbaColVisDropRef = { li: li, after: after };
                    });
                    li.addEventListener('dragend', function() {
                        fbaColVisDropRef = null;
                        fbaColVisClearDropMarks(li.closest('#column-dropdown-menu'));
                        li.classList.remove('col-vis-dragging');
                        Object.keys(groupEls).forEach(function(k) {
                            groupEls[k].classList.remove('col-vis-drop-over');
                        });
                    });
                }
                function bindFbaColVisDropZone(group, list, groupEls) {
                    [group, list].forEach(function(zone) {
                        zone.addEventListener('dragover', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            group.classList.add('col-vis-drop-over');
                            if (!e.target.closest || !e.target.closest('.col-vis-item')) fbaColVisDropRef = null;
                            e.dataTransfer.dropEffect = 'move';
                        });
                        zone.addEventListener('dragleave', function(e) {
                            if (!group.contains(e.relatedTarget)) group.classList.remove('col-vis-drop-over');
                        });
                        zone.addEventListener('drop', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            group.classList.remove('col-vis-drop-over');
                            const field = e.dataTransfer.getData('text/col-vis-field')
                                || e.dataTransfer.getData('text/plain');
                            if (!field) return;
                            const menu = document.getElementById('column-dropdown-menu');
                            const from = e.dataTransfer.getData('text/col-vis-from');
                            const li = menu ? (menu.querySelector('.col-vis-item[data-field="' + CSS.escape(field) + '"][data-group="' + CSS.escape(from) + '"]')
                                || menu.querySelector('.col-vis-item[data-field="' + CSS.escape(field) + '"]')) : null;
                            if (!li) return;
                            const nextCat = group.dataset.category;
                            if (!nextCat) return;
                            // Where in the list it lands: before / after the column it was dropped on, else at the end.
                            const ref = (fbaColVisDropRef && list.contains(fbaColVisDropRef.li) && fbaColVisDropRef.li !== li) ? fbaColVisDropRef : null;
                            const place = function() {
                                if (ref) list.insertBefore(li, ref.after ? ref.li.nextSibling : ref.li);
                                else list.appendChild(li);
                            };
                            fbaColVisDropRef = null;
                            fbaColVisClearDropMarks(menu);
                            // Same header: only the order changes.
                            if (li.dataset.group === nextCat) {
                                place();
                                fbaColVisSaveOrder(list);
                                fbaScheduleColumnArrangement();
                                return;
                            }
                            // Already under that header (same column in two headers is allowed, twice in one is not).
                            if (list.querySelector('.col-vis-item[data-field="' + CSS.escape(field) + '"]')) return;
                            const fromGroup = li.closest('.col-vis-group');
                            const prevCat = li.dataset.group;
                            place();
                            li.dataset.group = nextCat;
                            const cb = li.querySelector('input[type="checkbox"]');
                            if (cb) cb.dataset.group = nextCat;
                            if (li.dataset.copy === '1') {
                                fbaColExtraRemove(field, prevCat);
                                fbaColExtraAdd(field, nextCat);
                            } else {
                                const cats = loadFbaColCats();
                                cats[field] = nextCat;
                                saveFbaColCats(cats);
                            }
                            syncFbaGroupHeaderCheckbox(fromGroup);
                            syncFbaGroupHeaderCheckbox(group);
                            fbaColVisSaveOrder(list);
                            fbaColVisSaveOrder(fromGroup ? fromGroup.querySelector('.col-vis-group-list') : null);
                            fbaScheduleColumnArrangement();
                        });
                    });
                }

                function syncFbaGroupHeaderCheckbox(groupEl) {
                    if (!groupEl) return;
                    const headerCb = groupEl.querySelector('.col-vis-group-toggle');
                    const itemCbs = groupEl.querySelectorAll('.col-vis-item input[type="checkbox"]');
                    if (!headerCb || !itemCbs.length) return;
                    let checked = 0;
                    itemCbs.forEach(function(cb) { if (cb.checked) checked++; });
                    headerCb.checked = checked === itemCbs.length;
                    headerCb.indeterminate = checked > 0 && checked < itemCbs.length;
                }

                function setFieldVisible(field, visible) {
                    table.getColumns().forEach(function(col) {
                        if (col.getDefinition().field !== field) return;
                        if (visible) col.show();
                        else col.hide();
                    });
                }

                function buildColumnDropdown() {
                    const menu = document.getElementById("column-dropdown-menu");
                    if (!menu) return;
                    menu.innerHTML = '';

                    const catOverrides = loadFbaColCats();
                    const extras = loadFbaColExtras();
                    const visKeys = fbaColVisKeys();

                    const groupsLi = document.createElement("li");
                    groupsLi.className = "col-vis-full";
                    const groupsWrap = document.createElement("div");
                    groupsWrap.className = "col-vis-groups";

                    const lists = {};
                    const groupEls = {};

                    // Toolbar: quick search over every column + add your own header.
                    const toolbarLi = document.createElement("li");
                    toolbarLi.className = "col-vis-full";
                    toolbarLi.innerHTML = '<div class="col-vis-toolbar">'
                        + '<input type="search" class="form-control form-control-sm col-vis-search" placeholder="Quick search columns…" autocomplete="off">'
                        + '<input type="text" class="form-control form-control-sm col-vis-newname" maxlength="30" placeholder="New header name" autocomplete="off">'
                        + '<button type="button" class="btn btn-sm btn-primary col-vis-addgroup" title="Add a new header (column group)">+ Add header</button>'
                        + '<button type="button" class="btn btn-sm btn-success col-vis-save ms-auto" title="Arrange the table columns as shown here, save, and close">Save &amp; Close</button>'
                        + '</div>'
                        + '<div class="col-vis-hint"><span class="col-vis-status"></span>'
                        + '<span class="text-muted">⌘/Ctrl + click a column to select (Shift = range) · ⌘/Ctrl+C copy · ⌘/Ctrl+V paste into the highlighted header · drag a column to move or re-order it</span></div>';
                    menu.appendChild(toolbarLi);
                    if (fbaColVisFlashMsg) {
                        toolbarLi.querySelector('.col-vis-status').textContent = fbaColVisFlashMsg + ' ';
                        fbaColVisFlashMsg = '';
                    }

                    visKeys.forEach(function(cat) {
                        const group = document.createElement("div");
                        group.className = "col-vis-group" + (fbaColVisIsCustom(cat) ? " col-vis-group-custom" : "");
                        group.dataset.category = cat;
                        if (cat === fbaColVisActiveCat) group.classList.add("col-vis-active");

                        const titleEl = document.createElement("label");
                        titleEl.className = "col-vis-group-title";
                        const groupCb = document.createElement("input");
                        groupCb.type = "checkbox";
                        groupCb.className = "col-vis-group-toggle";
                        groupCb.dataset.group = cat;
                        groupCb.title = "Select / deselect all in " + fbaColVisLabel(cat);
                        titleEl.appendChild(groupCb);
                        titleEl.appendChild(document.createTextNode(fbaColVisLabel(cat)));
                        group.appendChild(titleEl);

                        // Every header: a search box that finds any column and adds it here, and a rename button.
                        // Your own headers also get a delete button.
                        const tools = document.createElement("div");
                        tools.className = "col-vis-group-tools";
                        tools.innerHTML = '<input type="search" class="form-control form-control-sm col-vis-group-search" placeholder="Search &amp; add column…" autocomplete="off" data-category="' + cat + '">'
                            + '<button type="button" class="btn btn-sm btn-outline-secondary col-vis-rename" data-category="' + cat + '" title="Rename this header"><i class="fas fa-pen"></i></button>'
                            + (fbaColVisIsCustom(cat)
                                ? '<button type="button" class="btn btn-sm btn-outline-danger col-vis-delgroup" data-category="' + cat + '" title="Delete this header (its columns go back to their normal header)"><i class="fas fa-trash-alt"></i></button>'
                                : '');
                        group.appendChild(tools);
                        const results = document.createElement("div");
                        results.className = "col-vis-group-results";
                        results.style.display = "none";
                        group.appendChild(results);

                        const list = document.createElement("ul");
                        list.className = "col-vis-group-list";
                        list.dataset.category = cat;
                        group.appendChild(list);
                        groupsWrap.appendChild(group);
                        lists[cat] = list;
                        groupEls[cat] = group;
                        bindFbaColVisDropZone(group, list, groupEls);
                    });

                    table.getColumns().forEach(function(col) {
                        const def = col.getDefinition();
                        const field = def.field;
                        if (!field || FBA_COL_SKIP_FIELDS.has(field)) return;

                        const title = fbaColVisPlainTitle(def);
                        let cat = catOverrides[field];
                        if (visKeys.indexOf(cat) === -1) cat = classifyFbaColumn(field);
                        const isVisible = col.isVisible();

                        const makeItem = function(itemCat, isCopy) {
                            const li = document.createElement("li");
                            li.className = "col-vis-item";
                            li.dataset.field = field;
                            li.dataset.group = itemCat;
                            if (isCopy) li.dataset.copy = '1';

                            const label = document.createElement("label");
                            const checkbox = document.createElement("input");
                            checkbox.type = "checkbox";
                            checkbox.value = field;
                            checkbox.setAttribute('data-field', field);
                            checkbox.className = "col-vis-field-toggle";
                            checkbox.dataset.group = itemCat;
                            checkbox.checked = isVisible;

                            label.appendChild(checkbox);
                            label.appendChild(document.createTextNode(' ' + title));
                            label.title = title + ' (drag to another header)';
                            li.appendChild(label);
                            const xBtn = document.createElement("button");
                            xBtn.type = "button";
                            xBtn.className = "col-vis-item-x";
                            xBtn.innerHTML = "&times;";
                            xBtn.dataset.field = field;
                            xBtn.title = isCopy ? "Remove from this header" : "Remove from this header (in a normal header: hide the column)";
                            li.appendChild(xBtn);
                            bindFbaColVisDrag(li, groupEls);
                            lists[itemCat].appendChild(li);
                        };
                        makeItem(cat, false);
                        // The same column also shown under other headers.
                        const extraCats = extras[field];
                        if (Array.isArray(extraCats)) {
                            extraCats.forEach(function(c) {
                                if (c !== cat && lists[c]) makeItem(c, true);
                            });
                        }
                    });

                    fbaColVisApplyOrder(lists);
                    // Headers that already have a saved order: keep it equal to what the menu shows now.
                    Object.keys(loadFbaColOrder()).forEach(function(cat) { if (lists[cat]) fbaColVisSaveOrder(lists[cat]); });
                    visKeys.forEach(function(cat) { syncFbaGroupHeaderCheckbox(groupEls[cat]); });

                    groupsLi.appendChild(groupsWrap);
                    menu.appendChild(groupsLi);
                }

                function fbaColFetchJson(url) {
                    return fetch(url, {
                        method: 'GET',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }
                    }).then(function(res) { return res.json(); });
                }

                function saveColumnVisibilityToServer() {
                    const visibility = {};
                    table.getColumns().forEach(function(col) {
                        const field = col.getDefinition().field;
                        if (field && !FBA_COL_SKIP_FIELDS.has(field)) visibility[field] = col.isVisible();
                    });
                    fetch(FBA_COL_VIS_URL, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': FBA_COL_CSRF },
                        body: JSON.stringify({ channel: FBA_COL_CHANNEL, visibility: visibility })
                    }).catch(function(err) { console.error('Error saving column visibility:', err); });
                }

                function applyColumnVisibilityFromServer() {
                    return fbaColFetchJson(FBA_COL_VIS_URL + '?channel=' + encodeURIComponent(FBA_COL_CHANNEL))
                        .then(function(saved) {
                            if (saved && typeof saved === 'object' && Object.keys(saved).length) {
                                // Layout saved from the Columns panel: honour it exactly.
                                table.getColumns().forEach(function(col) {
                                    const field = col.getDefinition().field;
                                    if (!field || FBA_COL_SKIP_FIELDS.has(field)) return;
                                    if (Object.prototype.hasOwnProperty.call(saved, field)) {
                                        if (saved[field]) col.show(); else col.hide();
                                    } else if (FBA_COL_DEFAULT_HIDDEN.indexOf(field) !== -1) {
                                        col.hide();
                                    }
                                });
                                return;
                            }
                            // First time on the new panel: start from the layout saved by the old Columns menu.
                            return fbaColFetchJson(FBA_COL_LEGACY_VIS_URL).then(function(legacy) {
                                legacy = (legacy && typeof legacy === 'object') ? legacy : {};
                                table.getColumns().forEach(function(col) {
                                    const field = col.getDefinition().field;
                                    if (!field || FBA_COL_SKIP_FIELDS.has(field)) return;
                                    // Dil sits after Ov L30 and stays visible even if an older saved layout still has it hidden.
                                    if (field === 'Dil') col.show();
                                    else if (FBA_COL_DEFAULT_HIDDEN.indexOf(field) !== -1) col.hide();
                                    else if (legacy[field] !== undefined) { if (legacy[field]) col.show(); else col.hide(); }
                                });
                            });
                        })
                        .then(function() { table.redraw(true); })
                        .catch(function(err) { console.error('Error loading column visibility:', err); });
                }

                function currentFbaColumnOrder() {
                    if (!table) return [];
                    return table.getColumns().map(function(col) { return col.getField(); }).filter(function(f) { return !!f; });
                }
                function saveFbaColumnOrderToServer() {
                    if (!table || fbaApplyingColumnOrder) return;
                    const order = currentFbaColumnOrder();
                    if (!order.length) return;
                    fetch(FBA_COL_ORDER_URL, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': FBA_COL_CSRF },
                        body: JSON.stringify({ channel: FBA_COL_CHANNEL, order: order })
                    }).catch(function(err) { console.error('Error saving column order:', err); });
                }
                function scheduleFbaColumnOrderSave() {
                    if (fbaColumnOrderSaveTimer) clearTimeout(fbaColumnOrderSaveTimer);
                    fbaColumnOrderSaveTimer = setTimeout(saveFbaColumnOrderToServer, 350);
                }

                function fbaMoveColumnsToOrder(order) {
                    if (!table || !Array.isArray(order)) return;
                    fbaApplyingColumnOrder = true;
                    try {
                        for (let i = 0; i < order.length; i++) {
                            const field = order[i];
                            const cols = table.getColumns().filter(function(c) { return !!c.getField(); });
                            const currentIdx = cols.findIndex(function(c) { return c.getField() === field; });
                            if (currentIdx === i || currentIdx < 0) continue;
                            if (i === 0) {
                                const firstField = cols[0].getField();
                                if (firstField && firstField !== field) table.moveColumn(field, firstField, false);
                            } else if (order[i - 1]) {
                                table.moveColumn(field, order[i - 1], true);
                            }
                        }
                    } catch (err) {
                        console.error('Error arranging columns:', err);
                    } finally {
                        fbaApplyingColumnOrder = false;
                    }
                }
                /** Apply the saved table column order; columns missing from it keep their place next to their neighbour. */
                function applyFbaColumnOrder(order) {
                    if (!table || !Array.isArray(order) || !order.length) return;
                    const existing = currentFbaColumnOrder();
                    if (!existing.length) return;
                    const valid = [];
                    const seen = {};
                    order.forEach(function(f) {
                        if (!f || seen[f] || existing.indexOf(f) === -1) return;
                        seen[f] = true;
                        valid.push(f);
                    });
                    existing.forEach(function(f) {
                        if (seen[f]) return;
                        seen[f] = true;
                        let inserted = false;
                        const existingIdx = existing.indexOf(f);
                        for (let j = existingIdx - 1; j >= 0; j--) {
                            const prevIdx = valid.indexOf(existing[j]);
                            if (prevIdx !== -1) { valid.splice(prevIdx + 1, 0, f); inserted = true; break; }
                        }
                        if (!inserted) valid.unshift(f);
                    });
                    fbaMoveColumnsToOrder(valid);
                }

                /**
                 * Table column order from the Columns menu. A header you made (or whose columns you re-ordered) becomes one block of
                 * columns in the table, in the order shown in the menu, starting where its left-most column already was.
                 */
                function fbaColumnArrangementGroups() {
                    const groups = [];
                    const catsMap = loadFbaColCats();
                    const extras = loadFbaColExtras();
                    const ord = loadFbaColOrder();
                    loadFbaColGroups().forEach(function(g) {
                        const members = new Set();
                        Object.keys(catsMap).forEach(function(f) { if (catsMap[f] === g.key) members.add(f); });
                        Object.keys(extras).forEach(function(f) { if (Array.isArray(extras[f]) && extras[f].indexOf(g.key) !== -1) members.add(f); });
                        const saved = Array.isArray(ord[g.key]) ? ord[g.key] : [];
                        const list = saved.filter(function(f) { return members.has(f); });
                        members.forEach(function(f) { if (list.indexOf(f) === -1) list.push(f); });
                        if (list.length) groups.push(list);
                    });
                    COL_VIS_BASE_KEYS.forEach(function(cat) {
                        if (!Array.isArray(ord[cat]) || !ord[cat].length) return;
                        // A copy of a column placed here does not pull the column out of its own header in the table.
                        const own = ord[cat].filter(function(f) { return !(Array.isArray(extras[f]) && extras[f].indexOf(cat) !== -1); });
                        if (own.length) groups.push(own);
                    });
                    return groups;
                }
                function fbaApplyColumnArrangement() {
                    if (!table) return;
                    const groups = fbaColumnArrangementGroups();
                    if (!groups.length) return;
                    let order = currentFbaColumnOrder();
                    if (!order.length) return;
                    const consumed = new Set();
                    groups.forEach(function(list) {
                        const have = new Set(order);
                        const L = [];
                        list.forEach(function(f) { if (have.has(f) && !consumed.has(f) && L.indexOf(f) === -1) L.push(f); });
                        L.forEach(function(f) { consumed.add(f); });
                        if (L.length < 2) return;
                        const inL = new Set(L);
                        const next = [];
                        let placed = false;
                        order.forEach(function(f) {
                            if (inL.has(f)) {
                                if (!placed) { placed = true; L.forEach(function(x) { next.push(x); }); }
                            } else {
                                next.push(f);
                            }
                        });
                        order = next;
                    });
                    fbaMoveColumnsToOrder(order);
                }
                /** Re-sequence the table a moment after a change in the Columns menu, and keep it saved. */
                function fbaScheduleColumnArrangement() {
                    if (fbaArrangeTimer) clearTimeout(fbaArrangeTimer);
                    fbaArrangeTimer = setTimeout(function() {
                        fbaApplyColumnArrangement();
                        scheduleFbaColumnOrderSave();
                    }, 250);
                }
                function applyColumnOrderFromServer() {
                    return fbaColFetchJson(FBA_COL_ORDER_URL + '?channel=' + encodeURIComponent(FBA_COL_CHANNEL))
                        .then(function(resp) {
                            if (resp && resp.success && Array.isArray(resp.order) && resp.order.length) applyFbaColumnOrder(resp.order);
                        })
                        .catch(function(err) { console.error('Error loading column order:', err); });
                }

                // A column dragged in the table header: save the new order and let the Columns box follow it.
                table.on('columnMoved', function() {
                    if (fbaApplyingColumnOrder) return;
                    const tableOrder = currentFbaColumnOrder();
                    const trank = function(f) { const i = tableOrder.indexOf(f); return i < 0 ? 1e6 : i; };
                    const ord = loadFbaColOrder();
                    Object.keys(ord).forEach(function(cat) {
                        if (!Array.isArray(ord[cat])) return;
                        ord[cat] = ord[cat].map(function(f, i) { return { f: f, i: i }; })
                            .sort(function(a, b) { return (trank(a.f) - trank(b.f)) || (a.i - b.i); })
                            .map(function(o) { return o.f; });
                    });
                    fbaColJsonSave(FBA_COL_ORDER_STORAGE, ord);
                    scheduleFbaColumnOrderSave();
                    buildColumnDropdown();
                });

                // Apply saved visibility + order, then build the Columns panel
                setTimeout(function() {
                    Promise.resolve(applyColumnVisibilityFromServer())
                        .then(function() { return applyColumnOrderFromServer(); })
                        .then(function() { fbaApplyColumnArrangement(); })
                        .finally(function() { buildColumnDropdown(); });
                }, 500);

                // Toggle column / whole header from the panel
                document.getElementById("column-dropdown-menu").addEventListener("change", function(e) {
                    if (e.target.type !== 'checkbox') return;
                    // Ticks in the "Search & add column" list are only a pick list, not column on / off switches.
                    if (e.target.closest('.col-vis-group-results')) return;
                    const menuRoot = document.getElementById('column-dropdown-menu');

                    // Header checkbox: select / deselect the whole header
                    if (e.target.classList.contains('col-vis-group-toggle')) {
                        const checked = e.target.checked;
                        const groupEl = e.target.closest('.col-vis-group');
                        const itemCbs = groupEl ? groupEl.querySelectorAll('.col-vis-item input[type="checkbox"]') : [];
                        itemCbs.forEach(function(cb) {
                            const field = cb.getAttribute('data-field') || cb.value;
                            cb.checked = checked;
                            setFieldVisible(field, checked);
                            fbaColVisSyncField(menuRoot, field, checked);
                        });
                        e.target.indeterminate = false;
                        saveColumnVisibilityToServer();
                        table.redraw(true);
                        return;
                    }

                    const field = e.target.getAttribute('data-field') || e.target.value;
                    if (!field || !table.getColumn(field)) return;
                    setFieldVisible(field, e.target.checked);
                    syncFbaGroupHeaderCheckbox(e.target.closest('.col-vis-group'));
                    fbaColVisSyncField(menuRoot, field, e.target.checked);
                    saveColumnVisibilityToServer();
                    table.redraw(true);
                });

                // Keep the panel open when clicking checkboxes / labels
                document.getElementById("column-dropdown-menu").addEventListener("click", function(e) {
                    if (e.target.closest('label') || e.target.type === 'checkbox') {
                        e.stopPropagation();
                    }
                });

                // ---- Columns panel: your own headers + quick search + copy / paste ----
                (function() {
                    const menuEl = document.getElementById("column-dropdown-menu");
                    if (!menuEl) return;

                    /** Put a column under another header (same as dragging it there) and remember it. */
                    function moveColToGroup(field, cat) {
                        const li = menuEl.querySelector('.col-vis-item[data-field="' + CSS.escape(field) + '"]:not([data-copy="1"])');
                        const group = menuEl.querySelector('.col-vis-group[data-category="' + CSS.escape(cat) + '"]');
                        if (!li || !group) return;
                        const fromGroup = li.closest('.col-vis-group');
                        group.querySelector('.col-vis-group-list').appendChild(li);
                        li.dataset.group = cat;
                        const cb = li.querySelector('input[type="checkbox"]');
                        if (cb) cb.dataset.group = cat;
                        const cats = loadFbaColCats();
                        cats[field] = cat;
                        saveFbaColCats(cats);
                        syncFbaGroupHeaderCheckbox(fromGroup);
                        syncFbaGroupHeaderCheckbox(group);
                        fbaColVisSaveOrder(group.querySelector('.col-vis-group-list'));
                        fbaColVisSaveOrder(fromGroup ? fromGroup.querySelector('.col-vis-group-list') : null);
                        fbaScheduleColumnArrangement();
                    }
                    function addHeaderFromToolbar() {
                        const input = menuEl.querySelector('.col-vis-newname');
                        const name = input ? input.value.trim().slice(0, 30) : '';
                        if (!name) { if (input) input.focus(); return; }
                        const list = loadFbaColGroups();
                        const dup = list.concat(COL_VIS_BASE_KEYS.map(function(k) { return { label: fbaColVisLabel(k) }; }))
                            .some(function(g) { return String(g.label).toLowerCase() === name.toLowerCase(); });
                        if (dup) { alert('A header named "' + name + '" already exists.'); return; }
                        list.push({ key: 'u_' + Date.now().toString(36) + Math.random().toString(36).slice(2, 5), label: name });
                        saveFbaColGroups(list);
                        buildColumnDropdown();
                    }

                    // Ticked results per header (kept while you keep typing), so several columns can be added at once.
                    const pickedByGroup = {};
                    function pickedSet(cat) { return pickedByGroup[cat] || (pickedByGroup[cat] = new Set()); }
                    function paintGroupResults(searchEl) {
                        const group = searchEl.closest('.col-vis-group');
                        const box = group ? group.querySelector('.col-vis-group-results') : null;
                        if (!box) return;
                        const cat = group.dataset.category;
                        const picked = pickedSet(cat);
                        const q = searchEl.value.trim().toLowerCase();

                        // Columns already in this header are left out (and can no longer be "picked").
                        const inHeader = {};
                        menuEl.querySelectorAll('.col-vis-item').forEach(function(li) {
                            if (li.dataset.group === cat) inHeader[li.dataset.field] = true;
                        });
                        Array.from(picked).forEach(function(f) { if (inHeader[f]) picked.delete(f); });

                        const infoFor = function(field) {
                            const li = menuEl.querySelector('.col-vis-item:not([data-copy="1"])[data-field="' + CSS.escape(field) + '"]');
                            if (!li) return null;
                            return { field: field, text: ((li.querySelector('label') || li).textContent || '').trim(), from: fbaColVisLabel(li.dataset.group) };
                        };

                        // Ticked columns stay in the list (shown first) even when a new search does not match them,
                        // so you can search again and tick more before pressing "Add selected".
                        const rows = [];
                        const seen = {};
                        Array.from(picked).forEach(function(f) {
                            const info = infoFor(f);
                            if (info) { rows.push(info); seen[f] = true; } else { picked.delete(f); }
                        });

                        if (q) {
                            // A column that is in another header can still be added (same column in two headers).
                            let extra = 0;
                            menuEl.querySelectorAll('.col-vis-item:not([data-copy="1"])').forEach(function(li) {
                                const f = li.dataset.field;
                                if (inHeader[f] || seen[f] || extra >= 40) return;
                                const text = ((li.querySelector('label') || li).textContent || '').trim();
                                if (text.toLowerCase().indexOf(q) !== -1) {
                                    seen[f] = true;
                                    extra++;
                                    rows.push({ field: f, text: text, from: fbaColVisLabel(li.dataset.group) });
                                }
                            });
                        }

                        box.innerHTML = '';
                        if (!rows.length) {
                            if (!q) { box.style.display = 'none'; return; }
                            box.innerHTML = '<div class="col-vis-noresult">No column found</div>';
                            box.style.display = '';
                            return;
                        }
                        rows.forEach(function(h) {
                            const row = document.createElement('label');
                            row.className = 'col-vis-result';
                            const cb = document.createElement('input');
                            cb.type = 'checkbox';
                            cb.className = 'col-vis-result-cb';
                            cb.dataset.field = h.field;
                            cb.checked = picked.has(h.field);
                            const name = document.createElement('span');
                            name.textContent = h.text;
                            const from = document.createElement('small');
                            from.textContent = h.from;
                            row.appendChild(cb); row.appendChild(name); row.appendChild(from);
                            box.appendChild(row);
                        });
                        const foot = document.createElement('div');
                        foot.className = 'col-vis-result-foot';
                        foot.innerHTML = '<label class="col-vis-result-all"><input type="checkbox" class="col-vis-result-cb-all"> All</label>'
                            + '<button type="button" class="btn btn-sm btn-primary col-vis-add-selected" disabled>Add selected</button>';
                        box.appendChild(foot);
                        box.style.display = '';
                        syncResultFoot(box, cat);
                    }
                    function syncResultFoot(box, cat) {
                        const cbs = box.querySelectorAll('.col-vis-result-cb');
                        const n = Array.from(cbs).filter(function(c) { return c.checked; }).length;
                        const all = box.querySelector('.col-vis-result-cb-all');
                        const btn = box.querySelector('.col-vis-add-selected');
                        if (all) { all.checked = cbs.length > 0 && n === cbs.length; all.indeterminate = n > 0 && n < cbs.length; }
                        if (btn) { btn.disabled = n === 0; btn.textContent = n ? ('Add selected (' + n + ')') : 'Add selected'; }
                    }

                    menuEl.addEventListener('input', function(e) {
                        const t = e.target;
                        if (t.classList && t.classList.contains('col-vis-search')) {
                            // Quick search: hide the columns that do not match, in every header.
                            const q = t.value.trim().toLowerCase();
                            menuEl.querySelectorAll('.col-vis-item').forEach(function(li) {
                                li.style.display = (!q || ((li.querySelector('label') || li).textContent || '').toLowerCase().indexOf(q) !== -1) ? '' : 'none';
                            });
                        } else if (t.classList && t.classList.contains('col-vis-group-search')) {
                            paintGroupResults(t);
                        }
                    });
                    menuEl.addEventListener('change', function(e) {
                        const t = e.target;
                        if (!t.classList) return;
                        const group = t.closest('.col-vis-group');
                        const box = t.closest('.col-vis-group-results');
                        if (!group || !box) return;
                        const cat = group.dataset.category;
                        const picked = pickedSet(cat);
                        if (t.classList.contains('col-vis-result-cb')) {
                            if (t.checked) picked.add(t.dataset.field); else picked.delete(t.dataset.field);
                        } else if (t.classList.contains('col-vis-result-cb-all')) {
                            box.querySelectorAll('.col-vis-result-cb').forEach(function(c) {
                                c.checked = t.checked;
                                if (t.checked) picked.add(c.dataset.field); else picked.delete(c.dataset.field);
                            });
                        } else { return; }
                        syncResultFoot(box, cat);
                    });
                    menuEl.addEventListener('keydown', function(e) {
                        if (e.key === 'Enter' && e.target.classList && e.target.classList.contains('col-vis-newname')) {
                            e.preventDefault();
                            addHeaderFromToolbar();
                        }
                    });

                    // ---- Select columns (Ctrl/Cmd + click, Shift for a range), Ctrl+C copy, Ctrl+V paste into the highlighted header ----
                    let colClipboard = [];
                    let lastSelectedLi = null;
                    function setActiveHeader(cat) {
                        fbaColVisActiveCat = cat || '';
                        menuEl.querySelectorAll('.col-vis-group').forEach(function(g) {
                            g.classList.toggle('col-vis-active', g.dataset.category === fbaColVisActiveCat);
                        });
                    }
                    function flashStatus(msg) {
                        const el = menuEl.querySelector('.col-vis-status');
                        if (el) el.textContent = msg + ' ';
                    }
                    menuEl.addEventListener('mousedown', function(e) {
                        const g = e.target.closest ? e.target.closest('.col-vis-group') : null;
                        if (g) setActiveHeader(g.dataset.category);
                    });
                    menuEl.addEventListener('click', function(e) {
                        const lab = e.target.closest ? e.target.closest('.col-vis-item > label') : null;
                        if (!lab) return;
                        const li = lab.closest('.col-vis-item');
                        if (e.ctrlKey || e.metaKey || e.shiftKey) {
                            e.preventDefault(); // select only, do not tick / untick the column
                            if (e.shiftKey && lastSelectedLi && lastSelectedLi.parentNode === li.parentNode) {
                                const items = Array.from(li.parentNode.children);
                                const a = items.indexOf(lastSelectedLi), b = items.indexOf(li);
                                items.slice(Math.min(a, b), Math.max(a, b) + 1).forEach(function(x) { x.classList.add('col-vis-selected'); });
                            } else {
                                li.classList.toggle('col-vis-selected');
                                lastSelectedLi = li;
                            }
                            const g = li.closest('.col-vis-group');
                            if (g) setActiveHeader(g.dataset.category);
                        } else {
                            menuEl.querySelectorAll('.col-vis-item.col-vis-selected').forEach(function(x) { x.classList.remove('col-vis-selected'); });
                        }
                    });
                    document.addEventListener('keydown', function(e) {
                        if (!menuEl.classList.contains('show')) return;
                        if (!(e.ctrlKey || e.metaKey)) return;
                        const t = e.target;
                        if (t && ((t.tagName === 'INPUT' && t.type !== 'checkbox') || t.tagName === 'TEXTAREA' || t.isContentEditable)) return;
                        const k = String(e.key || '').toLowerCase();
                        if (k === 'c') {
                            const fields = [];
                            menuEl.querySelectorAll('.col-vis-item.col-vis-selected').forEach(function(li) {
                                if (fields.indexOf(li.dataset.field) === -1) fields.push(li.dataset.field);
                            });
                            if (!fields.length) return;
                            e.preventDefault();
                            colClipboard = fields;
                            flashStatus('Copied ' + fields.length + ' column' + (fields.length === 1 ? '' : 's') + '.');
                        } else if (k === 'v') {
                            if (!colClipboard.length) return;
                            e.preventDefault();
                            const cat = fbaColVisActiveCat;
                            const group = cat ? menuEl.querySelector('.col-vis-group[data-category="' + CSS.escape(cat) + '"]') : null;
                            if (!group) { flashStatus('Click a header first, then paste.'); return; }
                            let added = 0;
                            colClipboard.forEach(function(f) {
                                if (group.querySelector('.col-vis-item[data-field="' + CSS.escape(f) + '"]')) return; // already there
                                fbaColExtraAdd(f, cat);
                                added++;
                            });
                            if (!added) { flashStatus('Already in ' + fbaColVisLabel(cat) + '.'); return; }
                            fbaScheduleColumnArrangement();
                            fbaColVisFlashMsg = 'Pasted ' + added + ' column' + (added === 1 ? '' : 's') + ' into ' + fbaColVisLabel(cat) + '.';
                            buildColumnDropdown();
                        }
                    });

                    menuEl.addEventListener('click', function(e) {
                        const saveBtn = e.target.closest('.col-vis-save');
                        if (saveBtn) {
                            e.preventDefault();
                            if (fbaArrangeTimer) { clearTimeout(fbaArrangeTimer); fbaArrangeTimer = null; }
                            fbaApplyColumnArrangement();
                            saveColumnVisibilityToServer();
                            saveFbaColumnOrderToServer();
                            table.redraw(true);
                            const tg = document.getElementById('columnVisibilityDropdown');
                            if (tg && window.bootstrap && bootstrap.Dropdown) bootstrap.Dropdown.getOrCreateInstance(tg).hide();
                            else if (tg) tg.click();
                            return;
                        }
                        const addBtn = e.target.closest('.col-vis-addgroup');
                        if (addBtn) { e.preventDefault(); addHeaderFromToolbar(); return; }

                        const xBtn = e.target.closest('.col-vis-item-x');
                        if (xBtn) {
                            e.preventDefault();
                            e.stopPropagation();
                            const li = xBtn.closest('.col-vis-item');
                            if (!li) return;
                            const field = li.dataset.field;
                            if (li.dataset.copy === '1') {
                                // A copy of the column under this header: just take it out of here (the column and its own header stay).
                                fbaColExtraRemove(field, li.dataset.group);
                                const grp = li.closest('.col-vis-group');
                                li.remove();
                                syncFbaGroupHeaderCheckbox(grp);
                                fbaColVisSaveOrder(grp ? grp.querySelector('.col-vis-group-list') : null);
                                fbaScheduleColumnArrangement();
                            } else if (fbaColVisIsCustom(li.dataset.group)) {
                                // Your header: take the column out, it goes back to its normal header.
                                const home = classifyFbaColumn(field);
                                moveColToGroup(field, home);
                                const cats = loadFbaColCats();
                                delete cats[field];
                                saveFbaColCats(cats);
                            } else {
                                // Normal header: hide the column in the table (tick it again to bring it back).
                                setFieldVisible(field, false);
                                fbaColVisSyncField(menuEl, field, false);
                                saveColumnVisibilityToServer();
                                table.redraw(true);
                            }
                            return;
                        }

                        const addSel = e.target.closest('.col-vis-add-selected');
                        if (addSel) {
                            e.preventDefault();
                            const group = addSel.closest('.col-vis-group');
                            if (!group) return;
                            const cat = group.dataset.category;
                            // Added as a copy: the column stays under its own header too.
                            Array.from(pickedSet(cat)).forEach(function(f) { fbaColExtraAdd(f, cat); });
                            pickedSet(cat).clear();
                            fbaScheduleColumnArrangement();
                            buildColumnDropdown();
                            return;
                        }

                        const renBtn = e.target.closest('.col-vis-rename');
                        if (renBtn) {
                            e.preventDefault();
                            const key = renBtn.dataset.category;
                            const name = prompt('Header name', fbaColVisLabel(key));
                            if (name === null) return;
                            const clean = name.trim().slice(0, 30);
                            if (!clean) return;
                            if (fbaColVisIsCustom(key)) {
                                const list = loadFbaColGroups();
                                list.forEach(function(g) { if (g.key === key) g.label = clean; });
                                saveFbaColGroups(list);
                            } else {
                                const labels = loadFbaColLabels();
                                labels[key] = clean;
                                saveFbaColLabels(labels);
                            }
                            buildColumnDropdown();
                            return;
                        }

                        const delBtn = e.target.closest('.col-vis-delgroup');
                        if (delBtn) {
                            e.preventDefault();
                            const key = delBtn.dataset.category;
                            if (!confirm('Delete the header "' + fbaColVisLabel(key) + '"? Its columns go back to their normal header (they stay as they are in the table).')) return;
                            saveFbaColGroups(loadFbaColGroups().filter(function(g) { return g.key !== key; }));
                            const cats = loadFbaColCats();
                            Object.keys(cats).forEach(function(f) { if (cats[f] === key) delete cats[f]; });
                            saveFbaColCats(cats);
                            const ex = loadFbaColExtras();
                            Object.keys(ex).forEach(function(f) { fbaColExtraRemove(f, key); });
                            fbaScheduleColumnArrangement();
                            buildColumnDropdown();
                        }
                    });
                })();

                // Pft% Toggle Event Listener
                $(document).on('click', '.pft-toggle-btn', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    console.log('Pft% toggle clicked, table:', table);
                    let colsToToggle = ["ROI%", "S_Price", "SPft%", "SROI%", "lmp_1"];

                    colsToToggle.forEach(colName => {
                        try {
                            let col = table.getColumn(colName);
                            if (col) {
                                console.log('Toggling column:', colName);
                                col.toggle();
                            } else {
                                console.warn('Column not found:', colName);
                            }
                        } catch (err) {
                            console.error('Error toggling column', colName, err);
                        }
                    });
                });

                // Send Cost Toggle Event Listener
                $(document).on('click', '.send-cost-toggle-btn', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    console.log('Send Cost toggle clicked, table:', table);
                    let colsToToggle = ["Length", "Width", "Height", "Quantity_in_each_box", "GW_CTN", "Shipping_Amount"];

                    colsToToggle.forEach(colName => {
                        try {
                            let col = table.getColumn(colName);
                            if (col) {
                                console.log('Toggling CTN column:', colName);
                                col.toggle();
                            } else {
                                console.warn('CTN column not found:', colName);
                            }
                        } catch (err) {
                            console.error('Error toggling CTN column', colName, err);
                        }
                    });
                });
            });

            // LMP Modal Event Listener for lmp-link (existing functionality)
            $(document).on('click', '.lmp-link', function(e) {
                e.preventDefault();
                const sku = $(this).data('sku');
                let data = $(this).data('lmp-data');
                console.log('SKU:', sku);
                console.log('Raw data:', data);
                try {
                    if (typeof data === 'string') {
                        data = JSON.parse(data);
                    }
                    console.log('Parsed data:', data);
                    openLmpModal(sku, data);
                } catch (error) {
                    console.error('Error parsing LMP data:', error);
                    alert('Error loading LMP data');
                }
            });

            // LMP Price Link Event Listener - fetch all competitors
            $(document).on('click', '.lmp-price-link', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const sku = $(this).data('sku');
                const marketplace = $(this).data('marketplace') || 'amazon';
                
                $('#lmpSku').text(sku);
                $('#lmpModal').appendTo('body');
                
                // Show loading state
                $('#lmpDataList').html(`
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading competitors for ${sku}...</p>
                    </div>
                `);
                
                // Fix modal z-index and backdrop issues
                const modal = $('#lmpModal');
                modal.css('z-index', '1050');
                modal.modal('show');
                $('.modal-backdrop').css('z-index', '1040');
                
                // Fetch all competitors from backend
                $.ajax({
                    url: '/amazon/competitors',
                    method: 'GET',
                    data: { sku: sku },
                    success: function(res) {
                        if (res.success && res.competitors && res.competitors.length > 0) {
                            renderLmpCompetitors(sku, res.competitors);
                        } else {
                            $('#lmpDataList').html('<div class="alert alert-info"><i class="fa fa-info-circle"></i> No Amz competitors found for this SKU</div>');
                        }
                    },
                    error: function(xhr) {
                        console.error('Error fetching competitors:', xhr);
                        $('#lmpDataList').html('<div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> Failed to load competitors. Please try again.</div>');
                    }
                });
            });

            // Render LMP Competitors in Modal
            let fbaCurrentLmp = { sku: '', competitors: [] };
            function renderLmpCompetitors(sku, competitors) {
                competitors = Array.isArray(competitors) ? competitors : [];
                fbaCurrentLmp = { sku: sku, competitors: competitors };
                if (!competitors.length) {
                    $('#lmpDataList').html('<div class="alert alert-info"><i class="fa fa-info-circle"></i> No competitors found for this SKU</div>');
                    return;
                }

                const lowestPrice = (window.LmpIgnore && LmpIgnore.l1) ? LmpIgnore.l1(competitors, 'price') : 0;
                
                let html = '';
                if (lowestPrice > 0) {
                    html += '<div class="mb-3"><span class="badge" style="background-color: transparent; color: #ff9c00; font-weight: 600;">L1 (lowest non-ignored): $' + lowestPrice.toFixed(2) + '</span></div>';
                }
                
                html += '<div class="table-responsive"><table class="table table-hover table-bordered table-sm"><thead class="table-light"><tr><th>#</th><th>Price</th><th>Title</th><th>Rating</th><th>Reviews</th><th>Link</th>' + LmpIgnore.header() + '</tr></thead><tbody>';
                
                competitors.forEach(function(comp, i) {
                    const sn = i + 1;
                    const price = parseFloat(comp.price) || 0;
                    const link = comp.product_link || comp.link || '';
                    const image = comp.image || '';
                    const title = (comp.product_title || '').substring(0, 60) + ((comp.product_title || '').length > 60 ? '...' : '');
                    const rating = comp.rating != null ? parseFloat(comp.rating).toFixed(1) : '-';
                    const reviews = comp.reviews != null ? (parseInt(comp.reviews) || 0).toLocaleString() : '-';
                    const ignored = !!comp.ignored;
                    const isLowest = !ignored && price > 0 && lowestPrice > 0 && Math.abs(price - lowestPrice) < 0.01;
                    const rowClass = (ignored ? 'lmp-ignored-row ' : '') + (isLowest ? 'table-success' : '');
                    const imgHtml = image ? `<img src="${image.replace(/"/g, '&quot;')}" alt="Product" class="rounded" style="height:40px;width:40px;object-fit:contain;margin-right:6px;" onerror="this.style.display='none'">` : '';
                    
                    html += `<tr class="${rowClass}">
                        <td>${sn}</td>
                        <td><div class="d-flex align-items-center">${imgHtml}<span>${isLowest ? '<i class="fa fa-trophy text-success me-1"></i>' : ''}<strong>$${price.toFixed(2)}</strong>${ignored ? ' <span class="badge bg-secondary">Ignored</span>' : ''}</span></div></td>
                        <td title="${(comp.product_title || '').replace(/"/g, '&quot;')}">${title || '-'}</td>
                        <td>${rating !== '-' ? '<i class="fa fa-star text-warning"></i> ' + rating : '-'}</td>
                        <td>${reviews !== '-' ? reviews : '-'}</td>
                        <td>${link ? '<a href="' + link.replace(/"/g, '&quot;') + '" target="_blank" class="text-primary" title="Open product"><i class="fa fa-external-link"></i></a>' : '-'}</td>
                        <td class="text-center align-middle">${LmpIgnore.checkbox(comp, 'amazon', sku)}</td>
                    </tr>`;
                });
                
                html += '</tbody></table></div>';
                $('#lmpDataList').html(html);
            }
            LmpIgnore.bind({
                marketplace: 'amazon',
                sku: function() { return fbaCurrentLmp.sku; },
                onToggled: function(id, ignored) {
                    fbaCurrentLmp.competitors.forEach(function(c) {
                        if (String(c.id) === String(id)) c.ignored = ignored;
                    });
                    renderLmpCompetitors(fbaCurrentLmp.sku, fbaCurrentLmp.competitors);
                }
            });

            // LMP Modal Function (for existing lmp-link)
            function openLmpModal(sku, data) {
                console.log('Opening modal for SKU:', sku, 'Data length:', data.length);
                console.log('lmpDataList exists:', $('#lmpDataList').length);
                $('#lmpSku').text(sku);
                let html = '';
                data.forEach(item => {
                    console.log('Item:', item);
                    html += `<div style="margin-bottom: 10px; border: 1px solid #ccc; padding: 10px;">
                    <strong>Price: $${item.price}</strong><br>
                    <a href="${item.link}" target="_blank">View Link</a>
                    ${item.image ? `<br><img src="${item.image}" alt="Product Image" style="max-width: 100px; max-height: 100px;">` : ''}
                </div>`;
                });
                console.log('Generated HTML:', html);
                $('#lmpDataList').html(html);
                
                // Fix modal z-index and backdrop issues
                const modal = $('#lmpModal');
                modal.appendTo('body');
                modal.css('z-index', '1050');
                modal.modal('show');
                
                // Ensure backdrop is properly configured
                $('.modal-backdrop').css('z-index', '1040');
                console.log('Modal shown');
            }

            // CTN details modal: shows L / W / H / Qty / GW CTN and CTN cost for the clicked row (read-only)
            function openCtnColumnsModal(row) {
                const data = row.getData();
                const fields = [
                    ['L CTN', data.Length],
                    ['W CTN', data.Width],
                    ['H CTN', data.Height],
                    ['Qty CTN', data.Quantity_in_each_box],
                    ['GW CTN', data.GW_CTN],
                    ['CTN cost', data.Shipping_Amount]
                ];
                const esc = (s) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

                $('#ctnColumnsSku').text(data.FBA_SKU || data.SKU || '');
                const body = $('#ctnColumnsBody').empty();
                fields.forEach(function(f) {
                    const v = (f[1] === null || f[1] === undefined || String(f[1]).trim() === '') ? '-' : f[1];
                    body.append(`<tr><th style="width:45%;background:#f8f9fa;">${f[0]}</th><td class="fw-semibold">${esc(v)}</td></tr>`);
                });

                const modal = $('#ctnColumnsModal');
                modal.appendTo('body');
                modal.css('z-index', '1060');
                modal.modal('show');
                $('.modal-backdrop').css('z-index', '1059');
            }

            // UPC Edit modal: fill UPC code, saved as `upc_edit` and compared with UPC Company
            let upcEditRow = null;

            function openUpcEditModal(row) {
                upcEditRow = row;
                const data = row.getData();

                $('#upcEditSku').text(data.FBA_SKU || data.SKU || '');
                $('#upcEditCompany').text(String(data.UPC_Codes ?? '').trim() || '-');
                $('#upcEditInput').val(data.UPC_Edit || '');

                const modal = $('#upcEditModal');
                modal.appendTo('body');
                modal.css('z-index', '1060');
                modal.modal('show');
                $('.modal-backdrop').css('z-index', '1059');
            }

            $(document).on('shown.bs.modal', '#upcEditModal', function() {
                $('#upcEditInput').trigger('focus').select();
            });

            $(document).on('keydown', '#upcEditInput', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    $('#upcEditSaveBtn').trigger('click');
                }
            });

            $(document).on('click', '#upcEditSaveBtn', function() {
                if (!upcEditRow) return;

                const btn = $(this);
                const row = upcEditRow;
                const data = row.getData();
                const value = ($('#upcEditInput').val() || '').trim();

                btn.prop('disabled', true);

                $.ajax({
                    url: '/update-fba-manual-data',
                    method: 'POST',
                    data: {
                        sku: data.FBA_SKU || data.SKU,
                        field: 'upc_edit',
                        value: value,
                        _token: '{{ csrf_token() }}'
                    },
                    success: function() {
                        // Re-renders both UPC Edit and UPC Company (dot) cells
                        row.update({ UPC_Edit: value });
                        row.reformat();
                        $('#upcEditModal').modal('hide');
                    },
                    error: function(xhr) {
                        console.error('Error saving UPC Edit:', xhr.responseText);
                        alert('Failed to save UPC code. Please try again.');
                    },
                    complete: function() {
                        btn.prop('disabled', false);
                    }
                });
            });

            // Missing Fields Modal Handler
            $(document).on('click', '.missing-fields-count', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                let fields = [];
                try {
                    const fieldsData = $(this).attr('data-fields');
                    if (fieldsData) {
                        fields = JSON.parse(fieldsData.replace(/&#39;/g, "'"));
                    }
                } catch (err) {
                    console.error('Error parsing missing fields:', err);
                }
                
                const sku = $(this).data('sku') || '';
                
                console.log('Missing fields clicked, SKU:', sku, 'Fields:', fields);
                
                $('#missingFieldsSku').text(sku);
                const fieldsList = $('#missingFieldsList');
                fieldsList.empty();
                
                if (fields.length === 0) {
                    fieldsList.append('<li class="list-group-item text-success"><i class="fa fa-check-circle"></i> All fields are filled!</li>');
                } else {
                    fields.forEach(function(field) {
                        fieldsList.append(`<li class="list-group-item"><i class="fa fa-exclamation-circle text-danger"></i> ${field}</li>`);
                    });
                }
                
                // Use the same pattern as other modals in this file
                const modal = $('#missingFieldsModal');
                modal.appendTo('body');
                modal.css('z-index', '1060');
                
                // Show modal using Bootstrap/jQuery modal
                modal.modal('show');
                
                // Ensure backdrop is properly configured
                $('.modal-backdrop').css('z-index', '1059');
                
                console.log('Modal shown, fields count:', fields.length);
            });

            // Monthly sales modal handler (twelve-month breakdown)
            $(document).on('click', '.monthly-eye', function(e) {
                e.preventDefault();
                e.stopPropagation();
                try {
                    // data-months is url-encoded JSON
                    const raw = $(this).attr('data-months') || '';
                    const json = decodeURIComponent(String(raw));
                    const months = JSON.parse(json || '{}');
                    const sku = $(this).data('sku') || '';

                    // Render months horizontally: one header row with months and one row with values
                    const monthKeys = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov',
                        'Dec'
                    ];
                    let html = '<div style="overflow-x:auto;"><table class="table table-sm table-bordered"><thead><tr>';
                    monthKeys.forEach(m => {
                        html += `<th class="text-center" style="min-width:60px;">${m}</th>`;
                    });
                    html += '</tr></thead><tbody><tr>';
                    monthKeys.forEach(m => {
                        const v = months[m] || 0;
                        // format numbers with commas
                        const formatted = (typeof v === 'number') ? v.toLocaleString() : (parseFloat(v) ?
                            parseFloat(v).toLocaleString() : v);
                        html += `<td class='text-end' style="vertical-align:middle;">${formatted}</td>`;
                    });
                    html += '</tr></tbody></table></div>';

                    $('#monthlyModalSku').text(sku);
                    $('#monthlySalesModalBody').html(html);
                    
                    // Fix modal z-index and backdrop issues
                    const modal = $('#monthlySalesModal');
                    modal.appendTo('body');
                    modal.css('z-index', '1050');
                    modal.modal('show');
                    
                    // Ensure backdrop is properly configured
                    $('.modal-backdrop').css('z-index', '1040');
                } catch (err) {
                    console.error('Failed to show monthly sales modal', err);
                    alert('Failed to load monthly sales details');
                }
            });

            // Copy FBA SKU button handler
            $(document).on('click', '.copy-fba-sku-btn', function(e) {
                e.stopPropagation();
                const fbaSku = $(this).data('fba-sku');
                if (fbaSku) {
                    navigator.clipboard.writeText(fbaSku).then(() => {
                        showToast(`Copied: ${fbaSku}`, 'success');
                    }).catch(() => {
                        // Fallback for older browsers
                        const textArea = document.createElement('textarea');
                        textArea.value = fbaSku;
                        textArea.style.position = 'fixed';
                        textArea.style.opacity = '0';
                        document.body.appendChild(textArea);
                        textArea.select();
                        try {
                            document.execCommand('copy');
                            showToast(`Copied: ${fbaSku}`, 'success');
                        } catch (err) {
                            showToast('Failed to copy', 'error');
                        }
                        document.body.removeChild(textArea);
                    });
                }
            });
        </script>
    @endsection
