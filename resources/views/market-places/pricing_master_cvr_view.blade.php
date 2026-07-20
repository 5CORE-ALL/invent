@extends('layouts.vertical', ['title' => 'Master Analytics ', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        /* Hide sort icons – sorting still works on header click */
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

        /* Custom pagination label */
        .tabulator-paginator label {
            margin-right: 5px;
        }

        /* OV L30 Modal – light bg with dark text */
        #ovl30DetailsModal #modal-price-pct-btn {
            width: 34px;
            height: 30px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }
        #ovl30DetailsModal #modal-price-pct-btn.dropdown-toggle::after {
            display: none;
        }
        #ovl30DetailsModal #modal-price-pct-dropdown {
            z-index: 2000;
        }
        #ovl30DetailsModal .modal-header .btn-group {
            position: relative;
        }
        #ovl30DetailsModal .ovl30-prc-row-cb {
            width: 16px;
            height: 16px;
            cursor: pointer;
        }
        #ovl30DetailsModal .modal-header,
        #ovl30DetailsModal .table thead {
            background-color: #e2e8f0 !important;
            color: #000000 !important;
            border-color: #cbd5e1 !important;
        }
        #ovl30DetailsModal .modal-vertical-header th {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            white-space: nowrap;
            transform: rotate(180deg);
            height: calc(var(--ovl30-fs, 11px) * 5.2);
            min-height: 48px;
            vertical-align: middle;
            font-size: var(--ovl30-fs, 11px) !important;
            font-weight: 700;
            padding: 2px 2px;
            background-color: #e2e8f0 !important;
            color: #000000 !important;
            border-color: #cbd5e1 !important;
        }
        #ovl30DetailsModal .modal-vertical-header th span {
            color: #000000 !important;
        }
        /* Exception for M column - keep it horizontal */
        #ovl30DetailsModal .modal-vertical-header th:nth-child(1) {
            writing-mode: horizontal-tb;
            transform: none;
            height: auto;
            min-height: 56px;
        }
        /* Auto Fill SPRICE button column – keep horizontal */
        #ovl30DetailsModal .modal-vertical-header th.ovl30-sprice-suggest-th {
            writing-mode: horizontal-tb;
            transform: none;
            height: auto;
            min-height: 48px;
            padding: 2px !important;
            vertical-align: middle;
        }
        #ovl30DetailsModal .ovl30-sprice-suggest-btn {
            padding: 2px 5px;
            line-height: 1;
            font-size: 11px;
        }

        /* Playback controls – default (teal/primary) + NPFT (violet) */
        .cvr-play-group .btn.rounded-circle {
            width: 32px;
            height: 32px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .cvr-play-group #play-auto,
        .cvr-play-group #play-pause,
        .cvr-play-group #play-npft-auto,
        .cvr-play-group #play-npft-pause,
        .cvr-play-group #play-dil-auto,
        .cvr-play-group #play-dil-pause,
        .cvr-play-group #play-cvr-auto,
        .cvr-play-group #play-cvr-pause,
        .cvr-play-group #play-groi-auto,
        .cvr-play-group #play-groi-pause {
            width: 38px;
            height: 38px;
        }
        .cvr-play-npft-main {
            background-color: #6f42c1 !important;
            border-color: #5a32a3 !important;
            color: #fff !important;
        }
        .cvr-play-npft-main:hover {
            background-color: #5a32a3 !important;
            border-color: #4b278a !important;
            color: #fff !important;
        }
        .cvr-play-dil-main {
            background-color: #fd7e14 !important;
            border-color: #e96b05 !important;
            color: #fff !important;
        }
        .cvr-play-dil-main:hover {
            background-color: #e96b05 !important;
            border-color: #d05f00 !important;
            color: #fff !important;
        }
        .cvr-play-cvr-main {
            background-color: #e83e8c !important;
            border-color: #d63384 !important;
            color: #fff !important;
        }
        .cvr-play-cvr-main:hover {
            background-color: #d63384 !important;
            border-color: #c22573 !important;
            color: #fff !important;
        }
        .cvr-play-groi-main {
            background-color: #0dcaf0 !important;
            border-color: #0bb5d7 !important;
            color: #fff !important;
        }
        .cvr-play-groi-main:hover {
            background-color: #0bb5d7 !important;
            border-color: #0a9fb8 !important;
            color: #fff !important;
        }
        #ovl30DetailsModal .ovl30-sprice-suggest-btn i {
            font-size: 11px;
        }
        #spriceSuggestModal {
            z-index: 1065;
        }
        #spriceSuggestModal .sprice-suggest-rule {
            border-left: 3px solid #198754;
            background: #f8fafc;
            padding: 0.45rem 0.65rem;
            margin-bottom: 0.4rem;
            font-size: 12px;
        }
        #spriceSuggestModal .sprice-rule-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 0.5rem 0.75rem;
        }
        #spriceSuggestModal .sprice-rule-field label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            margin-bottom: 2px;
            color: #334155;
        }
        #spriceSuggestModal .sprice-rule-field input {
            width: 100%;
            max-width: 110px;
            height: 28px;
            font-size: 12px;
            padding: 2px 6px;
        }
        #spriceSuggestModal .sprice-rule-card {
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 0.5rem 0.65rem;
            background: #fff;
            margin-bottom: 0.5rem;
        }
        #spriceSuggestModal .sprice-rule-card h6 {
            font-size: 12px;
            font-weight: 700;
            margin: 0 0 0.35rem;
            color: #0f172a;
        }
        #spriceSuggestModal .sprice-rule-card .rule-desc {
            font-size: 11px;
            color: #64748b;
            margin-bottom: 0.4rem;
        }
        #spriceSuggestModal .table th,
        #spriceSuggestModal .table td {
            font-size: 12px;
            vertical-align: middle;
        }

        /* ========== STATUS INDICATORS ========== */
        .status-circle {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
            border: 1px solid #ddd;
        }

        .status-circle.default {
            background-color: #6c757d;
        }

        .status-circle.red {
            background-color: #dc3545;
        }

        .status-circle.yellow {
            background-color: #ff9c00;
        }

        .status-circle.blue {
            background-color: #3591dc;
        }

        .status-circle.green {
            background-color: #28a745;
        }

        .status-circle.pink {
            background-color: #e83e8c;
        }
        .status-circle.magenta-bg {
            background-color: #e83e8c;
            border: 1px solid #111;
            box-shadow: inset 0 0 0 2px #e83e8c;
        }

        /* Columns dropdown – 3-column layout */
        #column-dropdown-menu.column-visibility-menu {
            column-count: 3;
            column-gap: 12px;
            min-width: 560px;
            max-width: 720px;
            max-height: 420px;
            overflow-y: auto;
            padding: 10px 12px;
        }
        #column-dropdown-menu.column-visibility-menu .dropdown-item {
            break-inside: avoid;
            -webkit-column-break-inside: avoid;
            page-break-inside: avoid;
            padding: 4px 8px;
            white-space: nowrap;
            font-size: 12px;
        }
        #column-dropdown-menu.column-visibility-menu .dropdown-item label {
            margin-bottom: 0;
            width: 100%;
        }
        @media (max-width: 768px) {
            #column-dropdown-menu.column-visibility-menu {
                column-count: 2;
                min-width: 320px;
            }
        }
        
        /* Totals row – light background, dark text; same font size as body rows */
        #ovl30DetailsModal .modal-totals-row {
            background-color: #f1f5f9 !important;
            font-weight: 700 !important;
            font-size: var(--ovl30-fs, 11px) !important;
            color: #0f172a !important;
            border-top: 2px solid #cbd5e1 !important;
        }
        #ovl30DetailsModal .modal-totals-row th {
            font-weight: 700 !important;
            font-size: var(--ovl30-fs, 11px) !important;
            line-height: 1.2;
            padding: 2px 2px !important;
            color: #0f172a !important;
            border-color: #e2e8f0 !important;
        }
        #ovl30DetailsModal .modal-dialog {
            max-width: 98vw !important;
            width: 98vw;
            height: 100vh;
            max-height: 100vh;
            margin: 0 auto;
            display: flex;
            align-items: stretch;
        }
        #ovl30DetailsModal .modal-content {
            height: 100%;
            max-height: 100vh;
            display: flex;
            flex-direction: column;
            border-radius: 0;
        }
        #ovl30DetailsModal .modal-header,
        #ovl30DetailsModal .modal-footer {
            flex-shrink: 0;
        }
        #ovl30DetailsModal .modal-body {
            background-color: #fff !important;
            color: #0f172a !important;
            flex: 1 1 auto;
            min-height: 0;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            padding-bottom: 0.5rem;
        }
        #ovl30DetailsModal .ovl30-table-wrap {
            --ovl30-fs: 11px;
            --ovl30-header-h: 56px;
            flex: 1 1 auto;
            min-height: 0;
            max-height: none;
            overflow-y: auto;
            overflow-x: hidden;
        }
        #ovl30DetailsModal .table tbody {
            background-color: #fff !important;
            color: #0f172a !important;
        }
        #ovl30DetailsModal #ovl30DetailsTable {
            width: 100%;
            max-width: 100%;
            table-layout: fixed;
        }
        #ovl30DetailsModal .table td {
            color: #334155 !important;
            padding: 1px 2px !important;
            font-size: var(--ovl30-fs) !important;
            font-weight: 700 !important;
            line-height: 1.2;
            vertical-align: middle;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        /* Center all column data (headers, totals, body) */
        #ovl30DetailsModal .ovl30-table-wrap .table th,
        #ovl30DetailsModal .ovl30-table-wrap .table td {
            text-align: center !important;
        }
        #ovl30DetailsModal #ovl30DetailsTable td:nth-child(12) .lmp-channel-price,
        #ovl30DetailsModal #ovl30DetailsTable td:nth-child(12) .lmp-add-btn {
            font-size: inherit;
            white-space: nowrap;
        }
        #ovl30DetailsModal .table .editable-sprice {
            width: 3.6em !important;
            min-width: 0 !important;
            max-width: 100% !important;
            height: calc(var(--ovl30-fs) + 8px);
            padding: 0 2px !important;
            font-size: var(--ovl30-fs) !important;
            font-weight: 700;
            text-align: center;
            margin: 0 auto;
            display: inline-block;
            -moz-appearance: textfield;
            appearance: textfield;
        }
        #ovl30DetailsModal .table .editable-sprice::-webkit-outer-spin-button,
        #ovl30DetailsModal .table .editable-sprice::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        /* Sticky table header + totals row in OV L30 Details modal */
        #ovl30DetailsModal .table thead .modal-vertical-header th {
            position: sticky;
            top: 0;
            z-index: 11;
            background-color: #e2e8f0 !important;
            box-shadow: 0 1px 0 0 #cbd5e1;
        }
        #ovl30DetailsModal .table thead .modal-totals-row th {
            position: sticky;
            top: var(--ovl30-header-h, 56px);
            z-index: 10;
            background-color: #f1f5f9 !important;
            box-shadow: 0 1px 0 0 #e2e8f0;
        }
        #ovl30DetailsModal .table thead .modal-vertical-header th:nth-child(1) {
            min-height: var(--ovl30-header-h, 56px);
        }
        /* Sortable column headers – cursor and sort icon */
        #ovl30DetailsModal .table thead .modal-vertical-header th.ovl30-sortable {
            cursor: pointer;
            user-select: none;
        }
        #ovl30DetailsModal .table thead .modal-vertical-header th.ovl30-sortable:hover {
            background-color: #cbd5e1 !important;
        }

        /* Missing L modal – stacks above the detail modal */
        #missingLModal {
            z-index: 1060;
        }
        .missing-l-dot:hover {
            box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.35);
            transform: scale(1.3);
            transition: transform 0.12s ease, box-shadow 0.12s ease;
        }
        /* Sort icons hidden; click-to-sort on headers still works */
        #ovl30DetailsModal .ovl30-sort-icon {
            display: none !important;
        }

        /* ========== DROPDOWN STYLING ========== */
        .manual-dropdown-container {
            position: relative;
            display: inline-block;
        }

        .manual-dropdown-container .dropdown-menu {
            position: absolute;
            top: 100%;
            left: 0;
            z-index: 1000;
            display: none;
            min-width: 200px;
            padding: 0.5rem 0;
            margin: 0;
            background-color: #fff;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }

        .manual-dropdown-container.show .dropdown-menu {
            display: block;
        }

        .dropdown-item {
            display: block;
            width: 100%;
            padding: 0.5rem 1rem;
            clear: both;
            font-weight: 400;
            color: #212529;
            text-align: inherit;
            text-decoration: none;
            white-space: nowrap;
            background-color: transparent;
            border: 0;
            cursor: pointer;
        }

        .dropdown-item:hover {
            color: #1e2125;
            background-color: #e9ecef;
        }

        /* Parent row styling - Light blue background like Amazon */
        .parent-row {
            background-color: #bde0ff !important;
            font-weight: bold !important;
        }

        .tabulator-row.parent-row {
            background-color: #bde0ff !important;
            font-weight: bold !important;
        }

        /* Push price button styling — compact icon to save column space */
        #ovl30DetailsModal .push-price-btn,
        .push-price-btn {
            font-size: 10px;
            padding: 1px 4px;
            line-height: 1;
            white-space: nowrap;
            min-width: 0;
            width: 22px;
            height: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        #ovl30DetailsModal .push-price-btn i,
        .push-price-btn i {
            font-size: 10px;
            line-height: 1;
        }
        
        .push-price-btn:disabled {
            cursor: not-allowed;
            opacity: 0.7;
        }

        #ovl30DetailsModal .table td:has(.push-price-btn) {
            width: 28px;
            max-width: 32px;
            padding: 1px 2px !important;
        }

        #ovl30DetailsModal .lmp-add-btn {
            color: #28a745;
            font-size: 14px;
            line-height: 1;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        #ovl30DetailsModal .lmp-add-btn:hover {
            color: #1e7e34;
        }
        #lmpModal .lmp-add-form-box {
            border: 1px solid #dee2e6;
            border-radius: 6px;
            background: #f8fafc;
            padding: 8px 10px;
            margin-bottom: 10px;
        }
        #lmpModal .lmp-add-form-box .form-control,
        #lmpModal .lmp-add-form-box .form-select {
            font-size: 12px;
            height: 28px;
            padding: 2px 8px;
        }
        #lmpModal .lmp-add-form-box .btn {
            font-size: 12px;
            padding: 3px 10px;
        }
        
        /* Pushed By – green dot; full name + date on hover */
        .pushed-by-dot {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background-color: #28a745;
            cursor: default;
            vertical-align: middle;
        }

        /* Modal width - 95% of screen */
        .modal-xxl {
            max-width: 90% !important;
        }

        /* LMP Competitors – right-side drawer (full height, 30% width) */
        #lmpModal {
            z-index: 1065;
        }
        #lmpModal .modal-dialog {
            position: fixed;
            top: 0;
            right: 0;
            left: auto;
            margin: 0;
            width: 30vw;
            max-width: 30vw;
            height: 100vh;
            max-height: 100vh;
            transform: none;
        }
        #lmpModal.fade .modal-dialog {
            transform: translateX(100%);
            transition: transform 0.25s ease-out;
        }
        #lmpModal.show .modal-dialog {
            transform: translateX(0);
        }
        #lmpModal .modal-content {
            height: 100%;
            max-height: 100vh;
            border-radius: 0;
            border: none;
            border-left: 1px solid #cbd5e1;
            display: flex;
            flex-direction: column;
        }
        #lmpModal .modal-header {
            flex-shrink: 0;
            padding: 0.6rem 0.85rem;
        }
        #lmpModal .modal-title {
            font-size: 0.95rem;
            line-height: 1.3;
        }
        #lmpModal .modal-body {
            flex: 1 1 auto;
            min-height: 0;
            overflow: auto;
            padding: 0.6rem;
        }
        #lmpModal .table {
            font-size: 11px;
            margin-bottom: 0;
        }
        #lmpModal .table th,
        #lmpModal .table td {
            padding: 0.3rem 0.35rem;
            vertical-align: middle;
            white-space: nowrap;
        }
        #lmpModal .table img.lmp-thumb {
            height: 28px !important;
            width: 28px !important;
        }
        #lmpModal .lmp-channel-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            margin-bottom: 0.5rem;
        }
        #lmpModal .lmp-channel-filters .btn {
            font-size: 11px;
            padding: 2px 8px;
            line-height: 1.4;
        }
        #lmpModal .lmp-channel-filters .btn.active {
            font-weight: 600;
        }
        #lmpModal .lmp-lowest-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            margin-bottom: 0.5rem;
        }
        #lmpModal .lmp-channel-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 18px;
            height: 18px;
            border-radius: 3px;
            font-size: 11px;
            flex-shrink: 0;
            margin-right: 4px;
        }
        #lmpModal .lmp-channel-icon.amazon {
            background: #ff9900;
            color: #111;
        }
        #lmpModal .lmp-channel-icon.ebay {
            background: #0064d2;
            color: #fff;
        }
        #lmpModal .lmp-channel-icon.google {
            background: #fff;
            color: #4285f4;
            border: 1px solid #dadce0;
        }
        #lmpModal .lmp-channel-icon.temu {
            background: #fb7701;
            color: #fff;
        }

        /* Parent SKU dot - P column */
        .parent-sku-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: #17a2b8;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .parent-sku-dot:hover {
            background-color: #0d6efd;
        }
        .parent-sku-dot.no-parent {
            background-color: #dee2e6;
            cursor: default;
        }

        /* SKU column — larger on hover for readability */
        .pricing-master-sku-text {
            display: inline-block;
            transform-origin: left center;
            transition: transform 0.18s ease;
            cursor: default;
        }
        .pricing-master-sku-text:hover {
            transform: scale(1.35);
            position: relative;
            z-index: 5;
        }
        .tabulator .tabulator-cell.pricing-master-sku-col {
            overflow: visible;
        }

        /* Row selection checkboxes */
        .row-select-cb, .select-all-cb {
            cursor: pointer;
            width: 16px;
            height: 16px;
        }

        /* Summary header bar */
        .summary-header-bar {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            font-size: 14px;
        }
        .summary-item {
            color: #495057;
        }
        .summary-item strong {
            color: #212529;
            margin-right: 4px;
        }
        .summary-chart-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 13px;
            color: #212529;
            background-color: #fff;
            border: 1px solid #dee2e6;
            cursor: pointer;
            text-decoration: none;
            transition: background-color 0.15s, border-color 0.15s, box-shadow 0.15s;
        }
        .summary-chart-badge:hover {
            background-color: #f8f9fa;
            border-color: #ff9c00;
            box-shadow: 0 1px 4px rgba(255, 156, 0, 0.2);
        }
        .summary-chart-badge .summary-badge-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        .summary-chart-badge .summary-badge-value {
            font-weight: 700;
        }
        .summary-badge-only {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 13px;
            color: #212529;
            background-color: #fff;
            border: 1px solid #dee2e6;
        }
        .summary-badge-only .summary-badge-value {
            font-weight: 700;
        }

        /* Sprice modal: top-center position and draggable header */
        #spriceDetailsModal.modal {
            align-items: flex-start;
            padding-top: 1.5rem;
        }
        #spriceDetailsModal .sprice-modal-dialog {
            margin-top: 0;
        }
        #spriceDetailsModal .modal-header.sprice-modal-drag-header {
            cursor: move;
            user-select: none;
        }

    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Master Analytics',
        'sub_title' => 'Master Analytics Data with Editable SPRICE',
    ])
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999;"></div>
    
    <!-- Remark History Modal -->
    <div class="modal fade" id="remarkHistoryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background-color: #6c757d;">
                    <h5 class="modal-title text-white">
                        <i class="fas fa-history me-2"></i> 
                        Remark History - <span id="historySkuName"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover mb-0">
                            <thead style="background-color: #6c757d; color: white;">
                                <tr>
                                    <th style="width: 50%;">Remark</th>
                                    <th>User</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="remarkHistoryTableBody">
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">No remarks yet</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Sprice Details Modal (top-center, draggable by header) -->
    <div class="modal fade" id="spriceDetailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog sprice-modal-dialog">
            <div class="modal-content">
                <div class="modal-header sprice-modal-drag-header" style="background-color: #0d6efd;">
                    <h5 class="modal-title text-white">
                        <i class="fas fa-dollar-sign me-2"></i>
                        Sprice – <span id="spriceModalSkuName"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <table class="table table-bordered mb-0">
                        <tbody>
                            <tr>
                                <th style="width: 40%;">Amz SPRICE</th>
                                <td class="text-end">
                                    <span class="text-muted me-1">$</span>
                                    <input type="number" class="form-control form-control-sm d-inline-block sprice-modal-sprice-input" id="spriceModalAmzSpriceInput" value="" step="0.01" min="0" placeholder="0.00" style="width: 90px; text-align: right;">
                                </td>
                            </tr>
                            <tr>
                                <th>Amz SGPFT%</th>
                                <td class="text-end" id="spriceModalSgpft">-</td>
                            </tr>
                            <tr>
                                <th>Amz SPFT%</th>
                                <td class="text-end" id="spriceModalSpft">-</td>
                            </tr>
                            <tr>
                                <th>Amz SROI%</th>
                                <td class="text-end" id="spriceModalSroi">-</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- OV L30 Details Modal -->
    <div class="modal fade" id="ovl30DetailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xxl">
            <div class="modal-content">
                <div class="modal-header" style="background-color: #e2e8f0; color: #0f172a;">
                    <div class="modal-title d-flex align-items-center justify-content-between w-100" style="font-size: 2em; color: #0f172a;">
                        <div class="d-flex align-items-center gap-3">
                            <i class="fas fa-mouse-pointer me-2"></i> 
                            <span id="modalSkuName" style="font-weight: bold;">SKU</span>
                        </div>
                        <div class="d-flex align-items-center gap-3 flex-wrap">
                            <span>
                                <strong>Total INV:</strong> <span id="modal-header-inv">0</span>
                            </span>
                            <span>
                                <strong>L30 Sold:</strong> <span id="modal-header-l30">0</span>
                            </span>
                            <span>
                                <strong>Dil %:</strong> <span id="modal-header-dil">0%</span>
                            </span>
                            <span id="modal-header-lmp-link" style="cursor: pointer; text-decoration: underline;" title="Click to view LMP competitors">
                                <i class="fas fa-search me-2"></i><strong>LMP</strong>
                            </span>
                            <div class="btn-group">
                                <button type="button" id="modal-price-pct-btn" class="btn btn-sm btn-primary dropdown-toggle"
                                    data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false"
                                    title="Prc Mode">
                                    <i class="fas fa-percent"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end" id="modal-price-pct-dropdown">
                                    <li><a class="dropdown-item" href="#" data-mode="decrease"><i class="fas fa-minus-circle text-warning"></i> Decrease</a></li>
                                    <li><a class="dropdown-item" href="#" data-mode="increase"><i class="fas fa-plus-circle text-primary"></i> Increase</a></li>
                                    <li><a class="dropdown-item" href="#" data-mode="same"><i class="fas fa-equals text-info"></i> Same Price</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="#" data-mode="cancel"><i class="fas fa-times text-muted"></i> Cancel</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div id="modal-discount-input-container" class="px-3 py-2 border-bottom bg-light" style="display: none;">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span id="modal-selected-channels-count" class="fw-semibold small text-muted">(select channels)</span>
                        <span id="modal-discount-input-label" class="text-muted small">By how much:</span>
                        <span id="modal-discount-type-select-wrap">
                            <select id="modal-discount-type-select" class="form-select form-select-sm" style="width: 120px;">
                                <option value="percentage">Percentage</option>
                                <option value="value">Value ($)</option>
                            </select>
                        </span>
                        <input type="text" id="modal-discount-percentage-input" class="form-control form-control-sm"
                            inputmode="decimal" placeholder="e.g. 10 or 2.50" autocomplete="off" style="width: 120px;">
                        <button type="button" id="modal-apply-discount-btn" class="btn btn-sm btn-primary">
                            <i class="fas fa-check"></i> Apply
                        </button>
                        <button type="button" id="modal-select-all-channels-btn" class="btn btn-sm btn-outline-secondary">
                            Select all editable
                        </button>
                    </div>
                </div>
                <div class="modal-body" style="background-color: #fff;">
                    <div class="table-responsive ovl30-table-wrap">
                        <table class="table table-bordered table-hover mb-0" id="ovl30DetailsTable">
                            <thead style="background-color: #e2e8f0; color: #0f172a;">
                                <tr class="modal-vertical-header">
                                    <th class="ovl30-sortable" data-sort="marketplace" data-dir="asc" title="Sort by Marketplace"><span>M</span><i class="ovl30-sort-icon fas fa-sort ms-1"></i></th>
                                    <th class="ovl30-sortable" data-sort="l30" data-dir="desc" title="Sort by L30"><span>L30</span><i class="ovl30-sort-icon fas fa-sort ms-1"></i></th>
                                    <th title="Missing Listings – click dot to view channels with no price"><span>Missing L</span></th>
                                    <th class="ovl30-sortable" data-sort="price" data-dir="desc" title="Sort by Price"><span>Price</span><i class="ovl30-sort-icon fas fa-sort ms-1"></i></th>
                                    <th class="ovl30-sortable" data-sort="views" data-dir="desc" title="Sort by Views"><span>Views</span><i class="ovl30-sort-icon fas fa-sort ms-1"></i></th>
                                    <th class="ovl30-sortable" data-sort="cvr" data-dir="desc" title="Sort by CVR%"><span>CVR%</span><i class="ovl30-sort-icon fas fa-sort ms-1"></i></th>
                                    <th class="ovl30-sortable" data-sort="groi" data-dir="desc" title="GROI% = (Price × Margin − LP − Ship) ÷ LP × 100"><span>GROI%</span><i class="ovl30-sort-icon fas fa-sort ms-1"></i></th>
                                    <th class="ovl30-sortable" data-sort="gpft" data-dir="desc" title="Sort by GPFT%"><span>GPFT%</span><i class="ovl30-sort-icon fas fa-sort ms-1"></i></th>
                                    <th class="ovl30-sortable" data-sort="nroi" data-dir="desc" title="NROI% = (Gross Profit − Ads $) ÷ LP × 100"><span>NROI%</span><i class="ovl30-sort-icon fas fa-sort ms-1"></i></th>
                                    <th class="ovl30-sortable" data-sort="npft" data-dir="desc" title="Sort by NPFT%"><span>NPFT%</span><i class="ovl30-sort-icon fas fa-sort ms-1"></i></th>
                                    <th class="ovl30-sortable" data-sort="ad" data-dir="asc" title="Sort by Ads% (channel Ads% from All Marketplace Master)"><span>Ads%</span><i class="ovl30-sort-icon fas fa-sort ms-1"></i></th>
                                    <th>LMP</th>
                                    <th>Links</th>
                                    <th class="ovl30-sprice-suggest-th" title="Auto Fill SPRICE using Dil%, CVR%, LMP &amp; Price">
                                        <button type="button" class="btn btn-sm btn-success ovl30-sprice-suggest-btn" title="Suggest SPRICE rules">
                                            <i class="fas fa-magic"></i>
                                        </button>
                                    </th>
                                    <th class="ovl30-sortable" data-sort="sprice" data-dir="desc" title="Sort by SPRICE"><span>SPRICE</span><i class="ovl30-sort-icon fas fa-sort ms-1"></i></th>
                                    <th class="ovl30-sortable" data-sort="sroi" data-dir="desc" title="Sort by SROI%"><span>SROI%</span><i class="ovl30-sort-icon fas fa-sort ms-1"></i></th>
                                    <th class="ovl30-sortable" data-sort="snroi" data-dir="desc" title="SNROI% = (SPRICE × Margin − LP − Ship − SPRICE × Ads%) ÷ LP × 100"><span>SNROI%</span><i class="ovl30-sort-icon fas fa-sort ms-1"></i></th>
                                    <th class="ovl30-sortable" data-sort="sgpft" data-dir="desc" title="Sort by SGPFT%"><span>SGPFT%</span><i class="ovl30-sort-icon fas fa-sort ms-1"></i></th>
                                    <th class="ovl30-sortable" data-sort="spft" data-dir="desc" title="Sort by SPFT%"><span>SPFT%</span><i class="ovl30-sort-icon fas fa-sort ms-1"></i></th>
                                    <th>Push</th>
                                    <th>Pushed By</th>
                                </tr>
                                <tr class="modal-totals-row">
                                    <th><img id="modal-product-image" src="" alt="" style="width: 50px; height: 50px; object-fit: cover; display: none;"></th>
                                    <th class="text-end" id="modal-total-l30">0</th>
                                    <th class="text-center">
                                        <span class="missing-l-dot" data-sku=""
                                            style="display:inline-block;width:12px;height:12px;border-radius:50%;background:#dc3545;cursor:pointer;border:1px solid #a00211;"
                                            title="View missing listings for this SKU"></span>
                                    </th>
                                    <th class="text-end" id="modal-total-price">$0.00</th>
                                    <th class="text-end" id="modal-total-views">0</th>
                                    <th class="text-end" id="modal-avg-cvr">0%</th>
                                    <th class="text-end" id="modal-avg-groi">0%</th>
                                    <th class="text-end" id="modal-avg-gpft">0%</th>
                                    <th class="text-end" id="modal-avg-nroi">0%</th>
                                    <th class="text-end" id="modal-avg-npft">0%</th>
                                    <th class="text-end" id="modal-avg-ad">0%</th>
                                    <th></th>
                                    <th></th>
                                    <th></th>
                                    <th class="text-end" id="modal-avg-sprice">$0.00</th>
                                    <th class="text-end" id="modal-avg-sroi">0%</th>
                                    <th class="text-end" id="modal-avg-snroi">0%</th>
                                    <th class="text-end" id="modal-avg-sgpft">0%</th>
                                    <th class="text-end" id="modal-avg-spft">0%</th>
                                    <th></th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="ovl30DetailsTableBody">
                                <!-- Table rows will be populated dynamically -->
                                <tr>
                                    <td colspan="21" class="text-center text-muted py-4">No data available</td>
                                </tr>
                            </tbody>
                        </table>

                    
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Auto Fill SPRICE – Suggest Rules Modal -->
    <div class="modal fade" id="spriceSuggestModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header" style="background-color: #198754; color: #fff;">
                    <h5 class="modal-title">
                        <i class="fas fa-magic me-2"></i>Auto Fill SPRICE — <span id="spriceSuggestSku">SKU</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2 small">
                        <strong>Goal:</strong> Increase PFT% without losing sale opportunity.
                        Suggestions use <strong>Dil%</strong>, channel <strong>CVR%</strong>, <strong>LMP</strong>, and current <strong>Price</strong>.
                        Prices stay at or below LMP (when available) to protect conversion.
                    </p>
                    <div class="d-flex flex-wrap gap-3 mb-3 small">
                        <span><strong>Dil%:</strong> <span id="spriceSuggestDil">-</span></span>
                        <span><strong>SKU CVR (avg):</strong> <span id="spriceSuggestAvgCvr">-</span></span>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <div class="fw-semibold small">Editable rules <span class="text-muted fw-normal">(saved in this browser)</span></div>
                            <div class="d-flex gap-1">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="spriceSuggestResetRulesBtn" title="Reset to defaults">Reset</button>
                                <button type="button" class="btn btn-outline-primary btn-sm" id="spriceSuggestRecalcBtn" title="Recalculate preview">
                                    <i class="fas fa-sync-alt me-1"></i>Recalc
                                </button>
                            </div>
                        </div>
                        <div id="spriceSuggestRulesEditor"></div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>M</th>
                                    <th class="text-end">Price</th>
                                    <th class="text-end">LMP</th>
                                    <th class="text-end">CVR%</th>
                                    <th class="text-end">Now</th>
                                    <th class="text-end">Suggest</th>
                                    <th>Rule</th>
                                    <th class="text-center">
                                        <input type="checkbox" id="spriceSuggestSelectAll" checked title="Select all">
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="spriceSuggestPreviewBody">
                                <tr><td colspan="8" class="text-center text-muted py-3">Open from Details to preview.</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="small text-muted mt-2 mb-0" id="spriceSuggestStatus"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success btn-sm" id="spriceSuggestApplyBtn">
                        <i class="fas fa-fill me-1"></i>Apply &amp; Save SPRICE
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Missing L – Channels with Price Modal -->
    <div class="modal fade" id="missingLModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header" style="background-color: #e2e8f0; color: #0f172a;">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2" style="color:#dc3545;"></i> Missing Listings &mdash; <span id="missingLSkuName" style="font-weight:bold;"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover mb-0" id="missingLTable">
                            <thead style="background-color: #e2e8f0; color: #0f172a;">
                                <tr>
                                    <th>Channel</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center">Listed</th>
                                    <th class="text-end">L30</th>
                                </tr>
                            </thead>
                            <tbody id="missingLTableBody">
                                <tr><td colspan="4" class="text-center text-muted py-4">No data.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Amazon SPRICE Table Modal -->
    <div class="modal fade" id="amazonSpriceTableModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header" style="background-color: #232f3e; color: white;">
                    <h5 class="modal-title">
                        <i class="fas fa-table me-2"></i> Amazon SPRICE Table
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover mb-0" id="amazonSpriceTableModalTable">
                            <thead style="background-color: #232f3e; color: white;">
                                <tr>
                                    <th>SKU</th>
                                    <th class="text-end">SPRICE</th>
                                    <th class="text-end">Amazon Margin</th>
                                    <th class="text-end">SGPFT%</th>
                                    <th class="text-end">SPFT%</th>
                                    <th class="text-end">SROI%</th>
                                    <th class="text-end">Avg PFT%</th>
                                    <th>Updated At</th>
                                </tr>
                            </thead>
                            <tbody id="amazonSpriceTableModalBody">
                                <tr><td colspan="8" class="text-center text-muted py-4">Load data using the button below.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- LMP Competitors Modal – right-side drawer (Amazon + eBay) -->
    <div class="modal fade" id="lmpModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="true">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title mb-0">
                        <i class="fa fa-shopping-cart me-1"></i> LMP: <span id="lmpSku"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="lmpDataList">
                        <div class="text-center py-5 text-muted">
                            <div class="spinner-border text-primary me-2"></div>Loading competitors...
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Master Analytics Rolling L30 Chart Modal (Inv, OV L30, Price, CVR) -->
    <div class="modal fade" id="pricingMasterChartModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog shadow-none" style="max-width: 98vw; width: 98vw; margin: 10px auto 0;">
            <div class="modal-content" style="border-radius: 8px; overflow: hidden;">
                <div class="modal-header bg-info text-white py-1 px-3">
                    <h6 class="modal-title mb-0" style="font-size: 13px;">
                        <i class="fas fa-chart-area me-1"></i>
                        <span id="pricingMasterChartModalTitle">Master Analytics - Inv (Rolling L30)</span>
                    </h6>
                    <div class="d-flex align-items-center gap-2">
                        <select id="pricingMasterChartRangeSelect" class="form-select form-select-sm bg-white" style="width: 110px; height: 26px; font-size: 11px; padding: 1px 8px;">
                            <option value="7">7 Days</option>
                            <option value="30" selected>30 Days</option>
                            <option value="60">60 Days</option>
                            <option value="90">90 Days</option>
                        </select>
                        <button type="button" class="btn-close btn-close-white" style="font-size: 10px;" data-bs-dismiss="modal"></button>
                    </div>
                </div>
                <div class="modal-body p-2">
                    <div id="pricingMasterChartContainer" style="height: 20vh; display: flex; align-items: stretch;">
                        <div style="flex: 1; min-width: 0; position: relative;">
                            <canvas id="pricingMasterChart"></canvas>
                        </div>
                        <div id="pricingMasterChartRefPanel" style="width: 100px; display: flex; flex-direction: column; justify-content: center; gap: 8px; padding: 6px 8px; border-left: 1px solid #e9ecef; background: #f8f9fa; border-radius: 0 4px 4px 0;">
                            <div style="text-align: center;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #dc3545; margin-bottom: 1px;">Highest</div>
                                <div id="pricingMasterChartHighest" style="font-size: 13px; font-weight: 700; color: #dc3545;">-</div>
                            </div>
                            <div style="text-align: center; border-top: 1px dashed #adb5bd; border-bottom: 1px dashed #adb5bd; padding: 4px 0;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #6c757d; margin-bottom: 1px;">Median</div>
                                <div id="pricingMasterChartMedian" style="font-size: 13px; font-weight: 700; color: #6c757d;">-</div>
                            </div>
                            <div style="text-align: center;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #198754; margin-bottom: 1px;">Lowest</div>
                                <div id="pricingMasterChartLowest" style="font-size: 13px; font-weight: 700; color: #198754;">-</div>
                            </div>
                        </div>
                    </div>
                    <div id="pricingMasterChartLoading" class="text-center py-3">
                        <div class="spinner-border spinner-border-sm text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-1 text-muted small mb-0">Loading chart data...</p>
                    </div>
                    <div id="pricingMasterChartNoData" class="text-center py-3" style="display: none;">
                        <i class="fas fa-exclamation-circle text-warning fa-2x mb-2"></i>
                        <p class="text-muted small mb-0">No daily data for this SKU yet.</p>
                        <p class="text-muted small mb-0"><strong>Refresh this page (Master Analytics CVR)</strong> once so today’s SKU data is saved, then open the graph again.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="card shadow-sm">
            <!-- Header Bar - Totals (badges with chart links) -->
            <div class="summary-header-bar px-4 py-3 d-flex flex-wrap align-items-center gap-3 border-bottom">
                <a href="#" class="summary-chart-badge" data-metric="inv" data-aggregate="1" title="Click to view Inv line graph">
                    <span class="summary-badge-dot" style="background-color: #4361ee;"></span>
                    <span>Total INV:</span>
                    <span class="summary-badge-value" id="total-inv-badge">0</span>
                </a>
                <a href="#" class="summary-chart-badge" data-metric="ov_l30" data-aggregate="1" title="Click to view OV L30 line graph">
                    <span class="summary-badge-dot" style="background-color: #28a745;"></span>
                    <span>Total OV L30:</span>
                    <span class="summary-badge-value" id="total-l30-badge">0</span>
                </a>
                <a href="#" class="summary-chart-badge" data-metric="dil" data-aggregate="1" title="Click to view DIL line graph">
                    <span class="summary-badge-dot" style="background-color: #0d6efd;"></span>
                    <span>DIL:</span>
                    <span class="summary-badge-value" id="avg-dil-badge">0%</span>
                </a>
                <a href="#" class="summary-chart-badge" data-metric="total_views" data-aggregate="1" title="Click to view Total Views line graph">
                    <span class="summary-badge-dot" style="background-color: #17a2b8;"></span>
                    <span>Total Views:</span>
                    <span class="summary-badge-value" id="total-views-badge">0</span>
                </a>
                <a href="#" class="summary-chart-badge" data-metric="cvr" data-aggregate="1" title="Click to view CVR line graph">
                    <span class="summary-badge-dot" style="background-color: #ff9c00;"></span>
                    <span>CVR:</span>
                    <span class="summary-badge-value" id="avg-cvr-badge">0%</span>
                </a>
                <a href="#" class="summary-chart-badge" data-metric="price" data-aggregate="1" title="Click to view Avg Price line graph">
                    <span class="summary-badge-dot" style="background-color: #e83e8c;"></span>
                    <span>Avg Price:</span>
                    <span class="summary-badge-value" id="avg-price-badge">$0.00</span>
                </a>
                <span class="summary-badge-only">
                    <span class="summary-badge-dot" style="background-color: #6c757d;"></span>
                    <span>Amz LMP:</span>
                    <span class="summary-badge-value" id="amz-lmp-badge">$0.00</span>
                </span>
                <span class="summary-badge-only">
                    <span class="summary-badge-dot" style="background-color: #6f42c1;"></span>
                    <span>AVG LQS:</span>
                    <span class="summary-badge-value" id="avg-lqs-badge">-</span>
                </span>
            </div>
            <div class="card-body py-3">
             
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <select id="inventory-filter" class="form-select form-select-sm"
                        style="width: 130px;">
                        <option value="all">All Inventory</option>
                        <option value="zero">0 Inventory</option>
                        <option value="more" selected>More than 0</option>
                    </select>

                    <!-- DIL Filter (Walmart-style dropdown) -->
                    <div class="dropdown manual-dropdown-container">
                        <button class="btn btn-light dropdown-toggle" type="button" id="dilFilterDropdown">
                            <span class="status-circle default"></span> DIL%
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="dilFilterDropdown">
                            <li><a class="dropdown-item column-filter active" href="#" data-column="dil_percent" data-color="all">
                                    <span class="status-circle default"></span> All DIL</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="dil_percent" data-color="red">
                                    <span class="status-circle red"></span> Red (&lt;25%)</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="dil_percent" data-color="green">
                                    <span class="status-circle green"></span> Green (25-50%)</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="dil_percent" data-color="pink">
                                    <span class="status-circle pink"></span> Pink (50%+)</a></li>
                        </ul>
                    </div>

                    <!-- CVR Filter -->
                    <div class="dropdown manual-dropdown-container">
                        <button class="btn btn-light dropdown-toggle" type="button" id="cvrFilterDropdown">
                            <span class="status-circle default"></span> CVR
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="cvrFilterDropdown">
                            <li><a class="dropdown-item column-filter active" href="#" data-column="avg_cvr" data-range="all">
                                    <span class="status-circle default"></span> All CVR</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="avg_cvr" data-range="0">
                                    <span class="status-circle red"></span> 0 to 0.00%</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="avg_cvr" data-range="0.01-1">
                                    <span class="status-circle red"></span> 0.01 - 1%</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="avg_cvr" data-range="1-2">
                                    <span class="status-circle yellow"></span> 1-2%</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="avg_cvr" data-range="2-3">
                                    <span class="status-circle yellow"></span> 2-3%</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="avg_cvr" data-range="3-4">
                                    <span class="status-circle green"></span> 3-4%</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="avg_cvr" data-range="0-4">
                                    <span class="status-circle default"></span> 0-4%</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="avg_cvr" data-range="4-7">
                                    <span class="status-circle green"></span> 4-7%</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="avg_cvr" data-range="7-13">
                                    <span class="status-circle green"></span> 7-13%</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="avg_cvr" data-range="10+">
                                    <span class="status-circle pink"></span> 10%+</a></li>
                        </ul>
                    </div>

                    <!-- GPFT% Filter -->
                    <div class="dropdown manual-dropdown-container">
                        <button class="btn btn-light dropdown-toggle" type="button" id="gpftFilterDropdown">
                            <span class="status-circle default"></span> GPFT%
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="gpftFilterDropdown">
                            <li><a class="dropdown-item column-filter active" href="#" data-column="avg_gpft" data-range="all">
                                    <span class="status-circle default"></span> All GPFT</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="avg_gpft" data-range="lt-20">
                                    <span class="status-circle red"></span> &lt; 20%</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="avg_gpft" data-range="20-30">
                                    <span class="status-circle yellow"></span> 20-30%</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="avg_gpft" data-range="30-40">
                                    <span class="status-circle green"></span> 30-40%</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="avg_gpft" data-range="gt-40">
                                    <span class="status-circle magenta-bg"></span> &gt; 40%</a></li>
                        </ul>
                    </div>

                    <!-- NPFT% Filter -->
                    <div class="dropdown manual-dropdown-container">
                        <button class="btn btn-light dropdown-toggle" type="button" id="npftFilterDropdown">
                            <span class="status-circle default"></span> NPFT%
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="npftFilterDropdown">
                            <li><a class="dropdown-item column-filter active" href="#" data-column="avg_pft" data-range="all">
                                    <span class="status-circle default"></span> All NPFT</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="avg_pft" data-range="lt-30">
                                    <span class="status-circle red"></span> &lt; 30%</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="avg_pft" data-range="30-40">
                                    <span class="status-circle yellow"></span> 30-40%</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="avg_pft" data-range="40-50">
                                    <span class="status-circle green"></span> 40-50%</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="avg_pft" data-range="gt-50">
                                    <span class="status-circle magenta-bg"></span> &gt; 50%</a></li>
                        </ul>
                    </div>

                    <!-- OV vs SW L30 (green = match, red = mismatch) -->
                    <select id="sw-l30-match-filter" class="form-select form-select-sm" style="width: auto; min-width: 168px;"
                        title="Show rows where OV L30 equals SW L30 (green), or only mismatches (red text)">
                        <option value="all" selected>SW L30 — All</option>
                        <option value="red">SW L30 — Red only</option>
                    </select>

                    <!-- SKU/Parent Filter -->
                    <select id="sku-parent-filter" class="form-select form-select-sm" style="width: auto;">
                        <option value="both" selected>Both (SKU + Parent)</option>
                        <option value="sku">SKU Only</option>
                        <option value="parent">Parent Only</option>
                    </select>

                    <button type="button" id="remove-filter-btn" class="btn btn-sm btn-outline-danger" title="Remove all filters">
                        <i class="fas fa-times-circle"></i> Remove Filter
                    </button>

                    <!-- Play → shows Pause, enables Next/Prev; Pause → back to normal, disables Next/Prev -->
                    <div class="btn-group align-items-center ms-2 cvr-play-group" role="group" aria-label="Parent playback">
                        <button type="button" id="play-backward" class="btn btn-sm btn-light rounded-circle shadow-sm"
                            title="Previous parent" data-bs-toggle="tooltip" data-bs-placement="bottom" disabled>
                            <i class="fas fa-step-backward"></i>
                        </button>
                        <button type="button" id="play-auto" class="btn btn-sm btn-primary rounded-circle shadow-sm me-1"
                            title="Play: walk parents in default order" data-bs-toggle="tooltip" data-bs-placement="bottom">
                            <i class="fas fa-play"></i>
                        </button>
                        <button type="button" id="play-pause" class="btn btn-sm btn-primary rounded-circle shadow-sm me-1" style="display: none;"
                            title="Pause: stop and show all rows" data-bs-toggle="tooltip" data-bs-placement="bottom">
                            <i class="fas fa-pause"></i>
                        </button>
                        <button type="button" id="play-forward" class="btn btn-sm btn-light rounded-circle shadow-sm"
                            title="Next parent" data-bs-toggle="tooltip" data-bs-placement="bottom" disabled>
                            <i class="fas fa-step-forward"></i>
                        </button>
                    </div>

                    <!-- NPFT Play: start from Lowest NPFT% (different color) -->
                    <div class="btn-group align-items-center ms-2 cvr-play-group cvr-play-npft" role="group" aria-label="Lowest NPFT% playback">
                        <button type="button" id="play-npft-backward" class="btn btn-sm btn-light rounded-circle shadow-sm"
                            title="Previous: lower NPFT% parent" data-bs-toggle="tooltip" data-bs-placement="bottom" disabled>
                            <i class="fas fa-step-backward"></i>
                        </button>
                        <button type="button" id="play-npft-auto" class="btn btn-sm rounded-circle shadow-sm me-1 cvr-play-npft-main"
                            title="Play Lowest NPFT%: walk parents starting from lowest Avg NPFT%" data-bs-toggle="tooltip" data-bs-placement="bottom">
                            <i class="fas fa-play"></i>
                        </button>
                        <button type="button" id="play-npft-pause" class="btn btn-sm rounded-circle shadow-sm me-1 cvr-play-npft-main" style="display: none;"
                            title="Pause: stop NPFT playback and show all rows" data-bs-toggle="tooltip" data-bs-placement="bottom">
                            <i class="fas fa-pause"></i>
                        </button>
                        <button type="button" id="play-npft-forward" class="btn btn-sm btn-light rounded-circle shadow-sm"
                            title="Next: next higher NPFT% parent" data-bs-toggle="tooltip" data-bs-placement="bottom" disabled>
                            <i class="fas fa-step-forward"></i>
                        </button>
                    </div>

                    <!-- Dil Play: start from Lowest Dil% (orange) -->
                    <div class="btn-group align-items-center ms-2 cvr-play-group cvr-play-dil" role="group" aria-label="Lowest Dil% playback">
                        <button type="button" id="play-dil-backward" class="btn btn-sm btn-light rounded-circle shadow-sm"
                            title="Previous: lower Dil% parent" data-bs-toggle="tooltip" data-bs-placement="bottom" disabled>
                            <i class="fas fa-step-backward"></i>
                        </button>
                        <button type="button" id="play-dil-auto" class="btn btn-sm rounded-circle shadow-sm me-1 cvr-play-dil-main"
                            title="Play Lowest Dil%: walk parents starting from lowest Dil%" data-bs-toggle="tooltip" data-bs-placement="bottom">
                            <i class="fas fa-play"></i>
                        </button>
                        <button type="button" id="play-dil-pause" class="btn btn-sm rounded-circle shadow-sm me-1 cvr-play-dil-main" style="display: none;"
                            title="Pause: stop Dil% playback and show all rows" data-bs-toggle="tooltip" data-bs-placement="bottom">
                            <i class="fas fa-pause"></i>
                        </button>
                        <button type="button" id="play-dil-forward" class="btn btn-sm btn-light rounded-circle shadow-sm"
                            title="Next: next higher Dil% parent" data-bs-toggle="tooltip" data-bs-placement="bottom" disabled>
                            <i class="fas fa-step-forward"></i>
                        </button>
                    </div>

                    <!-- CVR Play: start from Lowest CVR% (pink) -->
                    <div class="btn-group align-items-center ms-2 cvr-play-group cvr-play-cvr" role="group" aria-label="Lowest CVR% playback">
                        <button type="button" id="play-cvr-backward" class="btn btn-sm btn-light rounded-circle shadow-sm"
                            title="Previous: lower CVR% parent" data-bs-toggle="tooltip" data-bs-placement="bottom" disabled>
                            <i class="fas fa-step-backward"></i>
                        </button>
                        <button type="button" id="play-cvr-auto" class="btn btn-sm rounded-circle shadow-sm me-1 cvr-play-cvr-main"
                            title="Play Lowest CVR%: walk parents starting from lowest Avg CVR%" data-bs-toggle="tooltip" data-bs-placement="bottom">
                            <i class="fas fa-play"></i>
                        </button>
                        <button type="button" id="play-cvr-pause" class="btn btn-sm rounded-circle shadow-sm me-1 cvr-play-cvr-main" style="display: none;"
                            title="Pause: stop CVR% playback and show all rows" data-bs-toggle="tooltip" data-bs-placement="bottom">
                            <i class="fas fa-pause"></i>
                        </button>
                        <button type="button" id="play-cvr-forward" class="btn btn-sm btn-light rounded-circle shadow-sm"
                            title="Next: next higher CVR% parent" data-bs-toggle="tooltip" data-bs-placement="bottom" disabled>
                            <i class="fas fa-step-forward"></i>
                        </button>
                    </div>

                    <!-- GROI Play: start from Lowest GROI% (cyan) -->
                    <div class="btn-group align-items-center ms-2 cvr-play-group cvr-play-groi" role="group" aria-label="Lowest GROI% playback">
                        <button type="button" id="play-groi-backward" class="btn btn-sm btn-light rounded-circle shadow-sm"
                            title="Previous: lower GROI% parent" data-bs-toggle="tooltip" data-bs-placement="bottom" disabled>
                            <i class="fas fa-step-backward"></i>
                        </button>
                        <button type="button" id="play-groi-auto" class="btn btn-sm rounded-circle shadow-sm me-1 cvr-play-groi-main"
                            title="Play Lowest GROI%: walk parents starting from lowest Avg GROI%" data-bs-toggle="tooltip" data-bs-placement="bottom">
                            <i class="fas fa-play"></i>
                        </button>
                        <button type="button" id="play-groi-pause" class="btn btn-sm rounded-circle shadow-sm me-1 cvr-play-groi-main" style="display: none;"
                            title="Pause: stop GROI% playback and show all rows" data-bs-toggle="tooltip" data-bs-placement="bottom">
                            <i class="fas fa-pause"></i>
                        </button>
                        <button type="button" id="play-groi-forward" class="btn btn-sm btn-light rounded-circle shadow-sm"
                            title="Next: next higher GROI% parent" data-bs-toggle="tooltip" data-bs-placement="bottom" disabled>
                            <i class="fas fa-step-forward"></i>
                        </button>
                    </div>

                    <!-- Column Visibility Dropdown (3-column layout) -->
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button"
                            id="columnVisibilityDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa fa-eye"></i> Columns
                        </button>
                        <ul class="dropdown-menu column-visibility-menu" aria-labelledby="columnVisibilityDropdown" id="column-dropdown-menu">
                            <!-- Columns will be populated by JavaScript -->
                        </ul>
                    </div>
                    <button id="show-all-columns-btn" class="btn btn-sm btn-outline-secondary">
                        <i class="fa fa-eye"></i> Show All
                    </button>

                    <button id="export-btn" class="btn btn-sm btn-info">
                        <i class="fas fa-file-excel"></i> Export CSV
                    </button>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div id="cvr-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <!-- SKU & Parent Search -->
                    <div class="p-2 bg-light border-bottom d-flex flex-wrap gap-2 align-items-center">
                        <input type="text" id="sku-search" class="form-control" placeholder="Search SKU..." style="max-width: 220px;">
                        <input type="text" id="parent-search" class="form-control" placeholder="Search Parent..." style="max-width: 220px;">
                    </div>
                    <!-- Table body -->
                    <div id="cvr-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    /**
     * ========================================
     * CVR MASTER - TABULATOR VIEW
     * ========================================
     * 
     * FEATURES:
     * - Display: Image, SKU, INV, OV L30, DIL%
     * - Color-coded DIL% (Red <25%, Green 25–50%, Pink 50%+)
     * - SKU-wise breakdown modal (click info icon on OV L30)
     * - Filters: Inventory, DIL%
     * - Export to CSV
     * 
     * BACKEND ENDPOINTS:
     * 1. GET /cvr-master-data-json - Main table data
     * 2. GET /cvr-master-breakdown?sku=... - Modal breakdown data
     * 3. GET/POST /cvr-master-column-visibility - Column visibility
     * ========================================
     */

    let table = null;
    
    // ==================== UTILITY FUNCTIONS ====================
    
    function showToast(message, type = 'info') {
        const toastContainer = document.querySelector('.toast-container');
        if (!toastContainer) return;
        
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-white bg-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info'} border-0`;
        toast.setAttribute('role', 'alert');
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
        
        // ==================== MODAL FUNCTIONS ====================
        
        // OVL30 Details modal sort state (column header click)
        let ovl30ModalSortBy = 'l30_desc';

        $(document).on('click', '#ovl30DetailsModal .modal-vertical-header th.ovl30-sortable', function() {
            const sortField = $(this).data('sort');
            const currentVal = (ovl30ModalSortBy || 'l30_desc').toString();
            const [currentField, currentDir] = currentVal.split('_');
            let newDir = currentDir;
            if (currentField === sortField) {
                newDir = currentDir === 'asc' ? 'desc' : 'asc';
            } else {
                newDir = (sortField === 'marketplace') ? 'asc' : 'desc';
            }
            ovl30ModalSortBy = sortField + '_' + newDir;
            if (ovl30ModalData.length) {
                renderMarketplaceData();
                updateOvl30SortIcons();
            }
        });

        // OV L30 Info Icon Click Handler (SKU-wise)
        $(document).on('click', '.ovl30-info-icon', function(e) {
            e.stopPropagation();
            e.preventDefault();
            
            const $icon = $(this);
            const sku = $icon.data('sku');
            
            // Validate that we have a valid SKU (prevents wrong row issue)
            if (!sku || sku.trim() === '') {
                console.error('Invalid SKU in click handler');
                return;
            }
            
            // Get data from the icon's data attributes (set in formatter)
            const imagePath = $icon.data('image') || '';
            const inv = parseInt($icon.data('inv')) || 0;
            const l30 = parseInt($icon.data('l30')) || 0;
            const dil = parseFloat($icon.data('dil')) || 0;
            
            // Double-check by getting row data from Tabulator if possible
            try {
                const $row = $icon.closest('.tabulator-row');
                if ($row.length && typeof table !== 'undefined') {
                    const tabulatorRow = table.getRowFromElement($row[0]);
                    if (tabulatorRow) {
                        const rowData = tabulatorRow.getData();
                        // Use row data if available and valid (ensures correct row)
                        if (rowData && rowData.sku && !rowData.is_parent_summary) {
                            loadMarketplaceBreakdown(
                                rowData.sku,
                                rowData.image_path || imagePath,
                                rowData.inventory ?? rowData.inv ?? inv,
                                parseFloat(rowData.overall_l30 || 0),
                                rowData.dil_percent || dil
                            );
                            return;
                        }
                    }
                }
            } catch (err) {
                // If we can't get row data, continue with icon data attributes
                console.warn('Could not get row data from Tabulator, using icon data:', err);
            }
            
            loadMarketplaceBreakdown(sku, imagePath, inv, l30, dil);
        });
        
        // LMP value click (from modal breakdown) – open channel-filtered LMP drawer
        $(document).on('click', '.lmp-channel-price', function(e) {
            e.stopPropagation();
            e.preventDefault();
            const sku = $(this).data('sku');
            const marketplace = $(this).data('marketplace'); // amazon | ebay | google | temu
            if (sku) {
                loadLmpCompetitorsModal(sku, marketplace || null, false);
            }
        });

        // No LMP → Add (+) opens drawer with add form for that channel
        $(document).on('click', '.lmp-add-btn', function(e) {
            e.stopPropagation();
            e.preventDefault();
            const sku = $(this).data('sku') || $('#modalSkuName').text();
            const marketplace = $(this).data('marketplace') || 'amazon';
            if (sku) {
                loadLmpCompetitorsModal(sku, marketplace, true);
            }
        });
        
        // LMP Header Link Click Handler (from modal header)
        $(document).on('click', '#modal-header-lmp-link', function(e) {
            e.stopPropagation();
            const sku = $('#modalSkuName').text();
            console.log('Opening LMP modal from header for:', sku);
            loadLmpCompetitorsModal(sku);
        });

        // Missing L main-table dot – directly open missing listings modal without detail modal
        $(document).on('click', '.missing-l-main-dot', function(e) {
            e.stopPropagation();
            const sku = $(this).data('sku');

            // Show modal with loading state immediately
            $('#missingLSkuName').text(sku);
            $('#missingLTableBody').html(
                '<tr><td colspan="4" class="text-center text-muted py-4">' +
                '<div class="spinner-border spinner-border-sm text-danger me-2" role="status"></div>' +
                'Loading missing listings for ' + sku + '…</td></tr>'
            );
            const missingLModalEl = document.getElementById('missingLModal');
            const existing = bootstrap.Modal.getInstance(missingLModalEl);
            if (existing) { existing.dispose(); }
            new bootstrap.Modal(missingLModalEl, { backdrop: true }).show();

            // Fetch breakdown data and render missing channels
            $.ajax({
                url: '/cvr-master-breakdown?sku=' + encodeURIComponent(sku),
                method: 'GET',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function(data) {
                    ovl30ModalData = data.slice();

                    const channels = data.filter(function(item) {
                        const price = parseFloat(item.price || 0);
                        if (price > 0) return false;
                        const mp = (item.marketplace || '').toLowerCase();
                        if ((mp === 'ebaytwo' || mp === 'ebay2') && parseFloat(item.act_wt || 0) > 0.75) return false;
                        return true;
                    });

                    let html = '';
                    channels.forEach(function(item) {
                        const l30 = parseInt(item.l30 || 0);
                        const isListed = item.is_listed !== false;
                        const listedBadge = isListed
                            ? '<span class="badge" style="background:#28a745;">Listed</span>'
                            : '<span class="badge bg-danger">Not Listed</span>';
                        html += '<tr>' +
                            '<td><strong>' + (item.marketplace || '-') + '</strong></td>' +
                            '<td class="text-center"><span class="badge bg-danger">Missing Listing</span></td>' +
                            '<td class="text-center">' + listedBadge + '</td>' +
                            '<td class="text-end">' + l30.toLocaleString() + '</td>' +
                            '</tr>';
                    });
                    if (!html) {
                        html = '<tr><td colspan="4" class="text-center text-muted py-4">No missing listings found.</td></tr>';
                    }
                    $('#missingLTableBody').html(html);
                },
                error: function() {
                    $('#missingLTableBody').html(
                        '<tr><td colspan="4" class="text-center text-danger py-4">' +
                        '<i class="fas fa-exclamation-circle me-2"></i>Failed to load data.</td></tr>'
                    );
                }
            });
        });

        // Missing L – show all channels with price
        $(document).on('click', '.missing-l-dot', function(e) {
            e.stopPropagation();
            const sku = $(this).data('sku') || $('#modalSkuName').text();
            $('#missingLSkuName').text(sku);

            // Show only channels where price is 0 or null (missing listings)
            // Exception: hide EbayTwo if act_wt > 0.75 LB (weight restriction)
            const channels = ovl30ModalData.filter(item => {
                const price = parseFloat(item.price || 0);
                if (price > 0) return false; // already listed – hide
                const mp = (item.marketplace || '').toLowerCase();
                if ((mp === 'ebaytwo' || mp === 'ebay2') && parseFloat(item.act_wt || 0) > 0.75) return false; // weight restriction
                return true; // missing listing – show
            });

            let html = '';
            channels.forEach(item => {
                const l30 = parseInt(item.l30 || 0);
                const isListed = item.is_listed !== false;
                const listedBadge = isListed
                    ? '<span class="badge" style="background:#28a745;">Listed</span>'
                    : '<span class="badge bg-danger">Not Listed</span>';
                html += `<tr>
                    <td><strong>${item.marketplace || '-'}</strong></td>
                    <td class="text-center"><span class="badge bg-danger">Missing Listing</span></td>
                    <td class="text-center">${listedBadge}</td>
                    <td class="text-end">${l30.toLocaleString()}</td>
                </tr>`;
            });
            if (!html) {
                html = '<tr><td colspan="4" class="text-center text-muted py-4">No missing listings found.</td></tr>';
            }
            $('#missingLTableBody').html(html);

            const missingLModalEl = document.getElementById('missingLModal');
            const existing = bootstrap.Modal.getInstance(missingLModalEl);
            if (existing) { existing.dispose(); }
            new bootstrap.Modal(missingLModalEl, { backdrop: false }).show();
        });

        function loadMarketplaceBreakdown(sku, imagePath, inv, l30, dil) {
            $('#modalSkuName').text(sku);
            
            // Set product image in modal totals row
            const imgElement = $('#modal-product-image');
            if (imagePath) {
                imgElement.attr('src', imagePath);
                imgElement.attr('alt', sku);
                imgElement.show();
            } else {
                imgElement.hide();
            }
            
            // Update header stats with color formatting
            $('#modal-header-inv').text(inv.toLocaleString());
            $('#modal-header-l30').text(l30.toLocaleString());
            
            // Dil% colors: Red <25%, Green 25–50%, Pink 50%+
            const dilValue = parseFloat(dil);
            const dilColor = getDilPercentColor(dilValue);
            
            $('#modal-header-dil').html(`<span style="${styleForCellColor(dilColor)}">${Math.round(dilValue)}%</span>`);
            ovl30ModalDil = isFinite(dilValue) ? dilValue : 0;
            
            showModalLoading(sku);
            
            const modal = new bootstrap.Modal(document.getElementById('ovl30DetailsModal'));
            modal.show();
            
            // Fetch marketplace breakdown and FBA data
            $.ajax({
                url: '/cvr-master-breakdown?sku=' + encodeURIComponent(sku),
                method: 'GET',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function(data) { renderMarketplaceData(data); },
                error: function(xhr) { showModalError('Failed to load data'); }
            });
        }

        function showModalLoading(sku) {
            $('#ovl30DetailsTableBody').html(`
                <tr>
                    <td colspan="21" class="text-center text-muted py-4">
                        <div class="spinner-border spinner-border-sm text-info me-2" role="status"></div>
                        Loading data for ${sku}...
                    </td>
                </tr>
            `);
        }

        function showModalEmpty(sku) {
            $('#ovl30DetailsTableBody').html(`
                <tr>
                    <td colspan="21" class="text-center text-muted py-4">
                        No marketplace data available for ${sku}
                    </td>
                </tr>
            `);
        }

        function showModalError(message) {
            $('#ovl30DetailsTableBody').html(`
                <tr>
                    <td colspan="21" class="text-center text-danger py-4">
                        <i class="fas fa-exclamation-circle me-2"></i>${message}
                    </td>
                </tr>
            `);
        }

        let ovl30ModalData = [];
        let ovl30ModalDil = 0;
        let ovl30SpriceSuggestions = [];
        let modalPrcModeActive = false;
        let modalDecreaseModeActive = false;
        let modalIncreaseModeActive = false;
        let modalSamePriceModeActive = false;
        let modalSelectedChannels = new Set();

        function updateModalPrcSelectedCount() {
            const n = modalSelectedChannels.size;
            $('#modal-selected-channels-count').text(
                n > 0 ? `(${n} channel${n > 1 ? 's' : ''} selected)` : '(select channels)'
            );
        }

        function exitModalPricePctMode(rerender) {
            modalPrcModeActive = false;
            modalDecreaseModeActive = false;
            modalIncreaseModeActive = false;
            modalSamePriceModeActive = false;
            modalSelectedChannels.clear();
            $('#modal-discount-input-container').hide();
            $('#modal-price-pct-btn').removeClass('btn-danger btn-warning btn-success btn-info').addClass('btn-primary')
                .attr('title', 'Prc Mode')
                .html('<i class="fas fa-percent"></i>');
            $('#modal-apply-discount-btn').html('<i class="fas fa-check"></i> Apply');
            $('#modal-discount-type-select-wrap').show();
            $('#modal-discount-input-label').text('By how much:');
            $('#modal-discount-percentage-input').val('').attr('placeholder', 'e.g. 10 or 2.50');
            if (rerender !== false && ovl30ModalData.length) {
                renderMarketplaceData();
            }
        }

        function setModalPricePctMode(mode) {
            if (mode === 'cancel') {
                exitModalPricePctMode(true);
                return;
            }
            modalPrcModeActive = true;
            modalDecreaseModeActive = (mode === 'decrease');
            modalIncreaseModeActive = (mode === 'increase');
            modalSamePriceModeActive = (mode === 'same');
            modalSelectedChannels.clear();
            $('#modal-discount-input-container').show();
            $('#modal-discount-percentage-input').val('');
            updateModalPrcSelectedCount();

            if (mode === 'decrease') {
                $('#modal-discount-type-select-wrap').show();
                $('#modal-discount-input-label').text('By how much:');
                $('#modal-discount-percentage-input').attr('placeholder', 'e.g. 10 or 2.50');
                $('#modal-price-pct-btn').removeClass('btn-primary btn-success btn-info').addClass('btn-warning')
                    .attr('title', 'Decrease')
                    .html('<i class="fas fa-minus-circle"></i>');
                $('#modal-apply-discount-btn').html('<i class="fas fa-check"></i> Apply Decrease');
            } else if (mode === 'increase') {
                $('#modal-discount-type-select-wrap').show();
                $('#modal-discount-input-label').text('By how much:');
                $('#modal-discount-percentage-input').attr('placeholder', 'e.g. 10 or 2.50');
                $('#modal-price-pct-btn').removeClass('btn-primary btn-warning btn-info').addClass('btn-success')
                    .attr('title', 'Increase')
                    .html('<i class="fas fa-plus-circle"></i>');
                $('#modal-apply-discount-btn').html('<i class="fas fa-check"></i> Apply Increase');
            } else if (mode === 'same') {
                $('#modal-discount-type-select-wrap').hide();
                $('#modal-discount-input-label').text('Same Price ($):');
                $('#modal-discount-percentage-input').attr('placeholder', 'Enter price (e.g. 19.99)');
                $('#modal-price-pct-btn').removeClass('btn-primary btn-warning btn-success').addClass('btn-info')
                    .attr('title', 'Same Price')
                    .html('<i class="fas fa-equals"></i>');
                $('#modal-apply-discount-btn').html('<i class="fas fa-check"></i> Apply Same Price');
            }
            if (ovl30ModalData.length) {
                renderMarketplaceData();
            }
        }

        function getOvl30SortCompare() {
            const val = (ovl30ModalSortBy || 'l30_desc').toString();
            const [field, dir] = val.split('_');
            const asc = dir === 'asc' ? 1 : -1;
            return function(a, b) {
                let cmp = 0;
                if (field === 'l30') {
                    cmp = parseInt(a.l30 || 0) - parseInt(b.l30 || 0);
                } else if (field === 'marketplace') {
                    cmp = (a.marketplace || '').toLowerCase().localeCompare((b.marketplace || '').toLowerCase());
                } else if (field === 'price') {
                    cmp = parseFloat(a.price || 0) - parseFloat(b.price || 0);
                } else if (field === 'views') {
                    cmp = parseInt(a.views || 0) - parseInt(b.views || 0);
                } else if (field === 'cvr') {
                    const va = parseInt(a.views || 0), vb = parseInt(b.views || 0);
                    const la = parseInt(a.l30 || 0), lb = parseInt(b.l30 || 0);
                    const cvrA = va > 0 ? (la / va) * 100 : 0;
                    const cvrB = vb > 0 ? (lb / vb) * 100 : 0;
                    cmp = cvrA - cvrB;
                } else if (field === 'groi' || field === 'nroi') {
                    const calcRoi = (row, net) => {
                        const p = parseFloat(row.price || 0);
                        const lpV = parseFloat(row.lp || 0);
                        const shipV = parseFloat(row.ship || 0);
                        const marginV = parseFloat(row.margin || 0.80);
                        const adsPct = parseFloat(row.tacos_ch || 0);
                        const mp = String(row.marketplace || '').toLowerCase();
                        if (!(lpV > 0) || !(p > 0)) return 0;
                        const gross = p * marginV - lpV - shipV;
                        const groiV = (gross / lpV) * 100;
                        // Temu/Temu2: NROI% = GROI% − Ads% (same as /temu-decrease)
                        if (net && (mp === 'temu' || mp === 'temu2')) {
                            return adsPct === 100 ? groiV : (groiV - adsPct);
                        }
                        const profit = net ? (gross - p * (adsPct / 100)) : gross;
                        return (profit / lpV) * 100;
                    };
                    cmp = calcRoi(a, field === 'nroi') - calcRoi(b, field === 'nroi');
                } else if (field === 'gpft') {
                    cmp = parseFloat(a.gpft || 0) - parseFloat(b.gpft || 0);
                } else if (field === 'ad') {
                    // Ads% column uses channel Ads% (tacos_ch)
                    cmp = parseFloat(a.tacos_ch || 0) - parseFloat(b.tacos_ch || 0);
                } else if (field === 'npft') {
                    cmp = parseFloat(a.npft || 0) - parseFloat(b.npft || 0);
                } else if (field === 'sprice') {
                    cmp = parseFloat(a.sprice || 0) - parseFloat(b.sprice || 0);
                } else if (field === 'snroi') {
                    const lpA = parseFloat(a.lp || 0), shipA = parseFloat(a.ship || 0), marginA = parseFloat(a.margin || 0.80);
                    const lpB = parseFloat(b.lp || 0), shipB = parseFloat(b.ship || 0), marginB = parseFloat(b.margin || 0.80);
                    const adsA = parseFloat(a.tacos_ch || 0), adsB = parseFloat(b.tacos_ch || 0);
                    const sA = parseFloat(a.sprice || 0), sB = parseFloat(b.sprice || 0);
                    let nA = 0, nB = 0;
                    if (lpA > 0 && sA > 0) nA = ((sA * marginA - lpA - shipA - sA * (adsA / 100)) / lpA) * 100;
                    if (lpB > 0 && sB > 0) nB = ((sB * marginB - lpB - shipB - sB * (adsB / 100)) / lpB) * 100;
                    cmp = nA - nB;
                } else if (field === 'sgpft') {
                    const lpA = parseFloat(a.lp || 0), shipA = parseFloat(a.ship || 0), marginA = parseFloat(a.margin || 0.80);
                    const lpB = parseFloat(b.lp || 0), shipB = parseFloat(b.ship || 0), marginB = parseFloat(b.margin || 0.80);
                    const sA = parseFloat(a.sprice || 0), sB = parseFloat(b.sprice || 0);
                    let sgA = 0, sgB = 0;
                    if (sA > 0) sgA = ((sA * marginA - shipA - lpA) / sA) * 100;
                    if (sB > 0) sgB = ((sB * marginB - shipB - lpB) / sB) * 100;
                    cmp = sgA - sgB;
                } else if (field === 'spft') {
                    const l30A = parseInt(a.l30 || 0), l30B = parseInt(b.l30 || 0);
                    const adA = parseFloat(a.ad || 0), adB = parseFloat(b.ad || 0);
                    const lpA = parseFloat(a.lp || 0), shipA = parseFloat(a.ship || 0), marginA = parseFloat(a.margin || 0.80);
                    const lpB = parseFloat(b.lp || 0), shipB = parseFloat(b.ship || 0), marginB = parseFloat(b.margin || 0.80);
                    const sA = parseFloat(a.sprice || 0), sB = parseFloat(b.sprice || 0);
                    let spA = 0, spB = 0;
                    if (sA > 0) { const sgA = ((sA * marginA - shipA - lpA) / sA) * 100; spA = l30A === 0 ? sgA : sgA - adA; }
                    if (sB > 0) { const sgB = ((sB * marginB - shipB - lpB) / sB) * 100; spB = l30B === 0 ? sgB : sgB - adB; }
                    cmp = spA - spB;
                } else if (field === 'sroi') {
                    const lpA = parseFloat(a.lp || 0), shipA = parseFloat(a.ship || 0), marginA = parseFloat(a.margin || 0.80);
                    const lpB = parseFloat(b.lp || 0), shipB = parseFloat(b.ship || 0), marginB = parseFloat(b.margin || 0.80);
                    const sA = parseFloat(a.sprice || 0), sB = parseFloat(b.sprice || 0);
                    let rA = 0, rB = 0;
                    if (lpA > 0 && sA > 0) rA = ((sA * marginA - lpA - shipA) / lpA) * 100;
                    if (lpB > 0 && sB > 0) rB = ((sB * marginB - lpB - shipB) / lpB) * 100;
                    cmp = rA - rB;
                }
                return cmp * asc;
            };
        }

        function updateOvl30SortIcons() {
            const val = (ovl30ModalSortBy || 'l30_desc').toString();
            const [field, dir] = val.split('_');
            $('#ovl30DetailsModal .modal-vertical-header th.ovl30-sortable').each(function() {
                const $th = $(this);
                const sortField = $th.data('sort');
                const $icon = $th.find('.ovl30-sort-icon');
                $icon.removeClass('fa-sort-up fa-sort-down active').addClass('fa-sort');
                if (sortField === field) {
                    $icon.removeClass('fa-sort').addClass(dir === 'asc' ? 'fa-sort-up' : 'fa-sort-down').addClass('active');
                }
            });
        }

        /** Largest font size that fits all columns in the modal width, then stretch table to 100%. */
        function autoFitOvl30TableFont() {
            const wrap = document.querySelector('#ovl30DetailsModal .ovl30-table-wrap');
            const tableEl = document.getElementById('ovl30DetailsTable');
            if (!wrap || !tableEl || !$('#ovl30DetailsModal').hasClass('show')) return;

            const avail = wrap.clientWidth;
            if (avail < 80) return;

            const applyFs = (fs) => {
                wrap.style.setProperty('--ovl30-fs', fs + 'px');
                const headerH = Math.max(48, Math.round(fs * 5.2));
                wrap.style.setProperty('--ovl30-header-h', headerH + 'px');
            };

            // Measure natural width at each font size; pick largest that fits screen
            tableEl.style.width = 'max-content';
            tableEl.style.tableLayout = 'auto';

            let best = 8;
            for (let fs = 8; fs <= 16; fs += 0.5) {
                applyFs(fs);
                void tableEl.offsetWidth;
                if (tableEl.scrollWidth <= avail + 1) {
                    best = fs;
                } else if (fs > 8) {
                    break;
                }
            }
            applyFs(best);
            tableEl.style.width = '100%';
            tableEl.style.tableLayout = 'fixed';
        }

        let ovl30AutoFitTimer = null;
        function scheduleAutoFitOvl30TableFont() {
            clearTimeout(ovl30AutoFitTimer);
            ovl30AutoFitTimer = setTimeout(autoFitOvl30TableFont, 50);
        }

        $(window).on('resize', function() {
            if ($('#ovl30DetailsModal').hasClass('show')) {
                scheduleAutoFitOvl30TableFont();
            }
        });
        $('#ovl30DetailsModal').on('shown.bs.modal', function() {
            scheduleAutoFitOvl30TableFont();
        });

        function renderMarketplaceData(data) {
            if (data && data.length > 0) {
                ovl30ModalData = data.slice();
            }
            const toRender = ovl30ModalData.length ? ovl30ModalData : (data || []);
            if (!toRender.length) {
                showModalEmpty($('#modalSkuName').text());
                return;
            }
            const sorted = toRender.slice();
            sorted.sort(getOvl30SortCompare());
            data = sorted;

            let html = '';
            let totalPrice = 0;
            let totalViews = 0;
            let totalL30 = 0;
            let totalViewsForCVR = 0;  // Exclude Reverb for avg CVR
            let totalL30ForCVR = 0;    // Exclude Reverb for avg CVR
            let totalCVR = 0;
            let totalPftAmount = 0; // Sum of PFT amounts
            let totalNpftAmount = 0; // Sum of NPFT amounts
            let totalSalesAmount = 0; // Sum of sales amounts
            let totalAdsAmount = 0; // Sum of implied ads $ = sales × Ads%
            let totalCogsAmount = 0; // Sum of LP × L30 (for sales-weighted GROI/NROI)
            let totalSPRICE = 0;
            let totalSGPFT = 0;
            let totalSPFT = 0;
            let totalSROI = 0;
            let totalSNROI = 0;
            let cvrCount = 0;
            let spriceCount = 0;
            let sgpftCount = 0;
            let spftCount = 0;
            let sroiCount = 0;
            let snroiCount = 0;
            
            data.forEach(item => {
                const isListed = item.is_listed !== false;
                const rowClass = !isListed ? 'table-secondary' : '';
                const textClass = !isListed ? 'text-muted fst-italic' : '';
                
                // Calculate CVR% (L30 / Views * 100). null/undefined views → N/A (channel has no view data)
                const viewsMissing = item.views === null || item.views === undefined || item.views === '';
                const views = viewsMissing ? 0 : (parseInt(item.views, 10) || 0);
                const l30 = parseInt(item.l30 || 0);
                const cvr = (!viewsMissing && views > 0) ? (l30 / views) * 100 : 0;
                const gpft = parseFloat(item.gpft || 0);
                const ad = parseFloat(item.ad || 0);
                const tacosCh = parseFloat(item.tacos_ch || 0);
                // NPFT% = GPFT% − Ads% (channel Ads%)
                const npft = gpft - tacosCh;
                
                // SPRICE and calculated values
                const sprice = parseFloat(item.sprice || 0);
                const price = parseFloat(item.price || 0);
                const lp = parseFloat(item.lp || 0);
                const ship = parseFloat(item.ship || 0);
                const margin = parseFloat(item.margin || 0.80);

                const mpLower = (item.marketplace || '').toLowerCase();
                // GROI% = (Price × Margin − LP − Ship) ÷ LP × 100 — same as /temu-decrease & channel pages
                // Temu/Temu2 NROI% = GROI% − Ads% (same as /temu-decrease); others use dollar-ads / LP
                let groi = 0, nroi = 0;
                if (lp > 0 && price > 0) {
                    const grossProfit = price * margin - lp - ship;
                    groi = (grossProfit / lp) * 100;
                    if (mpLower === 'temu' || mpLower === 'temu2') {
                        nroi = (tacosCh === 100) ? groi : (groi - tacosCh);
                    } else {
                        const adsPerUnit = price * (tacosCh / 100);
                        nroi = ((grossProfit - adsPerUnit) / lp) * 100;
                    }
                }
                let sgpft = 0, spft = 0, sroi = 0, snroi = 0;
                if (sprice > 0) {
                    // Temu/Temu2: suggested metrics use FB price (base ≤26.99 → +$2.99) — same as /temu-decrease
                    const isTemuMp = (mpLower === 'temu' || mpLower === 'temu2');
                    const calcSp = isTemuMp ? (sprice <= 26.99 ? sprice + 2.99 : sprice) : sprice;
                    sgpft = ((calcSp * margin - ship - lp) / calcSp) * 100;
                    // TikTok: SPFT = SGPFT − TACOS% (same as /tiktok-pricing); others keep L30==0 skip-ads rule
                    spft = (mpLower === 'tiktok') ? (sgpft - tacosCh) : (l30 == 0 ? sgpft : (sgpft - ad));
                    sroi = lp > 0 ? ((calcSp * margin - lp - ship) / lp) * 100 : 0;
                    // SNROI% = (SPRICE × Margin − LP − Ship − SPRICE × Ads%) ÷ LP × 100
                    if (lp > 0) {
                        const sGross = calcSp * margin - lp - ship;
                        const sAds = calcSp * (tacosCh / 100);
                        snroi = ((sGross - sAds) / lp) * 100;
                    }
                }
                
                const isEditable = ['amazon', 'doba', 'ebay', 'ebay1', 'ebaytwo', 'ebay2', 'ebaythree', 'ebay3', 'temu', 'temu2', 'tiktok', 'bestbuy', 'macy', 'reverb', 'tiendamia', 'sb2c', 'shopify', 'shopifyb2c', 'sb2b', 'shopifyb2b', 'fba', 'shein', 'aliexpress', 'ppower', 'purchasingpower'].includes(mpLower);
                
                // Color coding for CVR%
                let cvrColor = '';
                if (cvr < 1) cvrColor = '#a00211'; // Dark red
                else if (cvr >= 1 && cvr < 3) cvrColor = '#ffc107'; // Yellow
                else if (cvr >= 3 && cvr < 5) cvrColor = '#28a745'; // Green
                else cvrColor = '#e83e8c'; // Pink
                
                // Color coding for GPFT% / NPFT%: <20 red, 20–30 yellow, 30–40 green, >40 black on magenta
                let gpftColor = '';
                let gpftStyle = '';
                let adColor = '';
                let npftColor = '';
                let npftStyle = '';
                
                if (gpft < 20) { gpftColor = '#dc3545'; gpftStyle = styleForCellColor(gpftColor); }
                else if (gpft < 30) { gpftColor = '#ffc107'; gpftStyle = styleForCellColor(gpftColor); }
                else if (gpft <= 40) { gpftColor = '#28a745'; gpftStyle = styleForCellColor(gpftColor); }
                else { gpftStyle = 'color:#4e0dab;font-weight:700;'; }
                
                // Ads% color — channel Ads% (same thresholds as /all-marketplace-master)
                if (tacosCh < 5) adColor = '#e83e8c';
                else if (tacosCh <= 10) adColor = '#28a745';
                else adColor = '#a00211';
                
                // NPFT: <30 red, 30–40 yellow, 40–50 green, >50 black on magenta
                if (npft < 30) { npftColor = '#dc3545'; npftStyle = styleForCellColor(npftColor); }
                else if (npft < 40) { npftColor = '#ffc107'; npftStyle = styleForCellColor(npftColor); }
                else if (npft <= 50) { npftColor = '#28a745'; npftStyle = styleForCellColor(npftColor); }
                else { npftStyle = 'color:#4e0dab;font-weight:700;'; }
                
                // Color coding for SGPFT%, SPFT%, SROI%
                let sgpftColor = '';
                if (sgpft < 0) sgpftColor = '#a00211';
                else if (sgpft >= 0 && sgpft < 10) sgpftColor = '#ffc107';
                else if (sgpft >= 10 && sgpft < 20) sgpftColor = '#3591dc';
                else if (sgpft >= 20 && sgpft <= 40) sgpftColor = '#28a745';
                else sgpftColor = '#e83e8c';
                
                let spftColor = '';
                if (spft < 0) spftColor = '#a00211';
                else if (spft >= 0 && spft < 10) spftColor = '#ffc107';
                else if (spft >= 10 && spft < 20) spftColor = '#3591dc';
                else if (spft >= 20 && spft <= 40) spftColor = '#28a745';
                else spftColor = '#e83e8c';
                
                let sroiColor = '';
                if (sroi < 0) sroiColor = '#a00211';
                else if (sroi >= 0 && sroi < 50) sroiColor = '#ffc107';
                else if (sroi >= 50 && sroi < 100) sroiColor = '#3591dc';
                else if (sroi >= 100 && sroi <= 150) sroiColor = '#28a745';
                else sroiColor = '#e83e8c';

                // GROI / NROI color slabs (same as main-table Avg GROI%)
                const roiColorFor = (pct) => {
                    if (pct < 50) return '#a00211';
                    if (pct < 100) return '#ffc107';
                    if (pct <= 150) return '#28a745';
                    return '#e83e8c';
                };
                const groiColor = roiColorFor(groi);
                const nroiColor = roiColorFor(nroi);
                const snroiColor = roiColorFor(snroi);
                
                // Add to totals only if listed
                if (isListed) {
                    // Calculate sold amount = price × L30 qty
                    const soldAmount = price * l30;
                    totalPrice += soldAmount;
                    if (!viewsMissing) {
                        totalViews += views;
                    }
                    totalL30 += l30;
                    // For avg CVR: exclude Reverb views and L30; skip channels with no views data
                    const isReverb = (item.marketplace || '').toLowerCase() === 'reverb';
                    if (!isReverb && !viewsMissing) {
                        totalViewsForCVR += views;
                        totalL30ForCVR += l30;
                    }
                    
                    // Calculate PFT amount = Sales Amount × GPFT%
                    const pftAmount = soldAmount * (gpft / 100);
                    totalPftAmount += pftAmount;
                    
                    // Calculate NPFT amount = Sales Amount × NPFT%
                    const npftAmount = soldAmount * (npft / 100);
                    totalNpftAmount += npftAmount;
                    
                    totalSalesAmount += soldAmount;
                    // Implied ads $ for this channel = sales × channel Ads%
                    totalAdsAmount += soldAmount * (tacosCh / 100);
                    // COGS $ = LP × qty sold (for sales-weighted GROI/NROI)
                    if (lp > 0 && l30 > 0) {
                        totalCogsAmount += lp * l30;
                    }
                    
                    if (cvr > 0) {
                        totalCVR += cvr;
                        cvrCount++;
                    }
                    if (sprice > 0) {
                        totalSPRICE += sprice;
                        spriceCount++;
                    }
                    if (sgpft !== 0) {
                        totalSGPFT += sgpft;
                        sgpftCount++;
                    }
                    if (spft !== 0) {
                        totalSPFT += spft;
                        spftCount++;
                    }
                    if (sroi !== 0) {
                        totalSROI += sroi;
                        sroiCount++;
                    }
                    if (snroi !== 0) {
                        totalSNROI += snroi;
                        snroiCount++;
                    }
                }
                
                // Push button when channel has API price push available and row is listed
                const pushableChannels = [
                    'amazon', 'doba', 'walmart',
                    'ebay', 'ebay1', 'ebay2', 'ebaytwo', 'ebay3', 'ebaythree',
                    'sb2c', 'shopify', 'shopifyb2c', 'sb2b', 'shopifyb2b',
                    'bestbuy', 'bestbuyusa', 'macy', 'macys',
                    'reverb', 'fba'
                ];
                const canPushPrice = pushableChannels.includes((item.marketplace || '').toLowerCase()) && isListed;

                // Price in red when LMP is available and LMP < Price
                const lmpForPrice = parseFloat(item.lmp_price);
                // Price vs LMP: ≤90% LMP → purple; 90%–LMP → dark green; > LMP → red
                let priceColorStyle = '';
                if (isListed && price > 0 && lmpForPrice > 0) {
                    const lmp90 = lmpForPrice * 0.90;
                    if (price > lmpForPrice) {
                        priceColorStyle = 'color:#a00211;font-weight:700;';
                    } else if (price <= lmp90) {
                        priceColorStyle = 'color:#4e0dab;font-weight:700;';
                    } else {
                        // Between LMP×0.90 and LMP (inclusive of LMP)
                        priceColorStyle = 'color:#006400;font-weight:700;';
                    }
                }
                const priceCellHtml = !isListed
                    ? '-'
                    : '<span style="' + priceColorStyle + '">$' + price.toFixed(2) + '</span>';

                const pushedByTip = item.pushed_by
                    ? String(item.pushed_by + (item.pushed_at ? ' ' + item.pushed_at : '')).replace(/"/g, '&quot;')
                    : '';
                const pushedByCell = item.pushed_by
                    ? '<span class="pushed-by-dot" title="' + pushedByTip + '" aria-label="' + pushedByTip + '"></span>'
                    : '<span class="text-muted">-</span>';
                
                const lmpPriceAttr = parseFloat(item.lmp_price) || 0;
                html += `
                    <tr class="${rowClass}" data-marketplace="${item.marketplace}" data-sku="${item.sku}" 
                        data-lp="${lp}" data-ship="${ship}" data-ad="${ad}" data-tacos-ch="${tacosCh}" data-margin="${margin}" data-l30="${l30}"
                        data-price="${price}" data-lmp="${lmpPriceAttr}" data-cvr="${cvr}" data-views="${views}" data-editable="${isEditable && isListed ? 1 : 0}">
                        <td class="${textClass}">${item.marketplace || '-'}</td>
                        <td class="text-end ${textClass}">${isListed ? l30.toLocaleString() : '-'}</td>
                        <td class="text-center">-</td>
                        <td class="text-end ${textClass}">${priceCellHtml}</td>
                        <td class="text-end ${textClass}">${!isListed ? '-' : (viewsMissing ? 'N/A' : views.toLocaleString())}</td>
                        <td class="text-end ${textClass}">${!isListed ? '-' : (viewsMissing ? 'N/A' : (views > 0 ? '<span style="' + styleForCellColor(cvrColor) + '">' + cvr.toFixed(1) + '%</span>' : '-'))}</td>
                        <td class="text-end ${textClass}">${isListed && price > 0 && lp > 0 ? '<span style="' + styleForCellColor(groiColor) + '">' + Math.round(groi) + '%</span>' : '-'}</td>
                        <td class="text-end ${textClass}">${isListed && gpft !== 0 ? '<span style="' + gpftStyle + '">' + Math.round(gpft) + '%</span>' : '-'}</td>
                        <td class="text-end ${textClass}">${isListed && price > 0 && lp > 0 ? '<span style="' + styleForCellColor(nroiColor) + '">' + Math.round(nroi) + '%</span>' : '-'}</td>
                        <td class="text-end ${textClass}">${isListed ? '<span style="' + npftStyle + '">' + Math.round(npft) + '%</span>' : '-'}</td>
                        <td class="text-end ${textClass}">${isListed ? '<span style="' + styleForCellColor(adColor) + ';font-weight:600;">' + Math.round(tacosCh) + '%</span>' : '-'}</td>
                        <td class="text-end ${textClass}">
                            ${(() => {
                                if (!isListed) return '-';
                                const lmpPrice = parseFloat(item.lmp_price);
                                const lmpChannel = (item.lmp_channel || '').toLowerCase();
                                const lmpSku = (item.sku && item.sku !== 'Not Listed')
                                    ? item.sku
                                    : ($('#modalSkuName').text() || '');
                                const skuAttr = String(lmpSku).replace(/"/g, '&quot;');
                                // No LMP for this channel → Add (+)
                                if (lmpChannel && !(lmpPrice > 0)) {
                                    return '<a href="#" class="lmp-add-btn" '
                                        + 'data-sku="' + skuAttr + '" '
                                        + 'data-marketplace="' + lmpChannel + '" '
                                        + 'title="Add ' + lmpChannel + ' LMP">'
                                        + '<i class="fas fa-plus-circle"></i></a>';
                                }
                                if (!lmpChannel || !(lmpPrice > 0)) {
                                    return '<span class="text-muted">-</span>';
                                }
                                return '<a href="#" class="lmp-channel-price" '
                                    + 'data-sku="' + skuAttr + '" '
                                    + 'data-marketplace="' + lmpChannel + '" '
                                    + 'title="View ' + lmpChannel + ' LMP" '
                                    + 'style="color:#0d6efd;font-weight:600;text-decoration:underline;cursor:pointer;">'
                                    + '$' + lmpPrice.toFixed(2)
                                    + '</a>';
                            })()}
                        </td>
                        <td class="text-center ${textClass}" style="white-space: nowrap;">
                            ${(item.buyer_link || item.seller_link) ? 
                                (item.buyer_link ? '<a href="' + item.buyer_link + '" target="_blank" rel="noopener" class="ovl30-link-bs" title="Buyer link" style="color:#0d6efd;font-weight:700;text-decoration:none;padding:0 2px;">B</a>' : '') +
                                (item.seller_link ? '<a href="' + item.seller_link + '" target="_blank" rel="noopener" class="ovl30-link-bs" title="Seller link" style="color:#495057;font-weight:700;text-decoration:none;padding:0 2px;">S</a>' : '')
                                : '-'}
                        </td>
                        <td class="text-center ${textClass}">
                            ${(modalPrcModeActive && isEditable && isListed)
                                ? '<input type="checkbox" class="ovl30-prc-row-cb" data-marketplace="'
                                    + String(item.marketplace || '').replace(/"/g, '&quot;')
                                    + '"' + (modalSelectedChannels.has(String(item.marketplace || '')) ? ' checked' : '')
                                    + ' title="Select for Prc Mode">'
                                : '-'}
                        </td>
                        <td class="text-end ${textClass}">
                            ${isEditable && isListed ? 
                                '<input type="number" class="form-control form-control-sm editable-sprice" value="' + sprice.toFixed(2) + '" step="0.01">' 
                                : (sprice > 0 ? '$' + sprice.toFixed(2) : '-')}
                        </td>
                        <td class="text-end ${textClass}">
                            <span class="calculated-sroi" style="${styleForCellColor(sroiColor)}">${Math.round(sroi)}%</span>
                        </td>
                        <td class="text-end ${textClass}">
                            ${sprice > 0 && lp > 0
                                ? '<span class="calculated-snroi" style="' + styleForCellColor(snroiColor) + '">' + Math.round(snroi) + '%</span>'
                                : '<span class="calculated-snroi text-muted">-</span>'}
                        </td>
                        <td class="text-end ${textClass}">
                            <span class="calculated-sgpft" style="${styleForCellColor(sgpftColor)}">${Math.round(sgpft)}%</span>
                        </td>
                        <td class="text-end ${textClass}">
                            <span class="calculated-spft" style="${styleForCellColor(spftColor)}">${Math.round(spft)}%</span>
                        </td>
                        <td class="text-center ${textClass}">
                            ${canPushPrice ? 
                                '<button class="btn btn-sm btn-primary push-price-btn" ' +
                                'data-sku="' + item.sku + '" ' +
                                'data-marketplace="' + item.marketplace + '" ' +
                                'title="Push price to ' + item.marketplace + '">' +
                                '<i class="fas fa-upload"></i></button>' 
                                : '-'}
                        </td>
                        <td class="text-center ${textClass}">${pushedByCell}</td>
                    </tr>
                `;
            });
            
            $('#ovl30DetailsTableBody').html(html);
            
            // Calculate averages
            // Avg CVR using CVR formula: (Total L30 / Total Views) × 100 — exclude Reverb
            const avgCVR = totalViewsForCVR > 0 ? (totalL30ForCVR / totalViewsForCVR) * 100 : 0;
            // Avg GPFT% = (Total PFT Amount / Total Sales Amount) × 100
            const avgGPFT = totalSalesAmount > 0 ? (totalPftAmount / totalSalesAmount) * 100 : 0;
            // Avg Ads% = (Σ ads amount) ÷ (Σ sales amount) × 100
            const avgAD = totalSalesAmount > 0 ? (totalAdsAmount / totalSalesAmount) * 100 : 0;
            // Avg NPFT% = (Total NPFT Amount / Total Sales Amount) × 100
            const avgNPFT = totalSalesAmount > 0 ? (totalNpftAmount / totalSalesAmount) * 100 : 0;
            // Avg GROI% = (Σ Gross Profit $) ÷ (Σ COGS $) × 100
            // Avg NROI% = (Σ Gross Profit $ − Σ Ads $) ÷ (Σ COGS $) × 100
            const avgGROI = totalCogsAmount > 0 ? (totalPftAmount / totalCogsAmount) * 100 : 0;
            const avgNROI = totalCogsAmount > 0 ? ((totalPftAmount - totalAdsAmount) / totalCogsAmount) * 100 : 0;
            const avgSGPFT = sgpftCount > 0 ? totalSGPFT / sgpftCount : 0;
            const avgSPFT = spftCount > 0 ? totalSPFT / spftCount : 0;
            const avgSROI = sroiCount > 0 ? totalSROI / sroiCount : 0;
            const avgSNROI = snroiCount > 0 ? totalSNROI / snroiCount : 0;
            
            // Apply color formatting for totals row
            // CVR% color
            let cvrColorTotal = '';
            if (avgCVR < 1) cvrColorTotal = '#a00211';
            else if (avgCVR >= 1 && avgCVR < 3) cvrColorTotal = '#ffc107';
            else if (avgCVR >= 3 && avgCVR < 5) cvrColorTotal = '#28a745';
            else cvrColorTotal = '#e83e8c';
            
            // GPFT% / NPFT% color: <20 red, 20–30 yellow, 30–40 green, >40 black on magenta
            let gpftStyleTotal = '';
            if (avgGPFT < 20) gpftStyleTotal = styleForCellColor('#dc3545');
            else if (avgGPFT < 30) gpftStyleTotal = styleForCellColor('#ffc107');
            else if (avgGPFT <= 40) gpftStyleTotal = styleForCellColor('#28a745');
            else gpftStyleTotal = 'color:#4e0dab;font-weight:700;';
            
            let npftStyleTotal = '';
            if (avgNPFT < 30) npftStyleTotal = styleForCellColor('#dc3545');
            else if (avgNPFT < 40) npftStyleTotal = styleForCellColor('#ffc107');
            else if (avgNPFT <= 50) npftStyleTotal = styleForCellColor('#28a745');
            else npftStyleTotal = 'color:#4e0dab;font-weight:700;';
            
            let sgpftColorTotal = '';
            if (avgSGPFT < 0) sgpftColorTotal = '#a00211';
            else if (avgSGPFT >= 0 && avgSGPFT < 10) sgpftColorTotal = '#ffc107';
            else if (avgSGPFT >= 10 && avgSGPFT < 20) sgpftColorTotal = '#3591dc';
            else if (avgSGPFT >= 20 && avgSGPFT <= 40) sgpftColorTotal = '#28a745';
            else sgpftColorTotal = '#e83e8c';
            
            let spftColorTotal = '';
            if (avgSPFT < 0) spftColorTotal = '#a00211';
            else if (avgSPFT >= 0 && avgSPFT < 10) spftColorTotal = '#ffc107';
            else if (avgSPFT >= 10 && avgSPFT < 20) spftColorTotal = '#3591dc';
            else if (avgSPFT >= 20 && avgSPFT <= 40) spftColorTotal = '#28a745';
            else spftColorTotal = '#e83e8c';
            
            // Ads% color — same thresholds as /all-marketplace-master
            let adColorTotal = '';
            if (avgAD < 5) adColorTotal = '#e83e8c';
            else if (avgAD <= 10) adColorTotal = '#28a745';
            else adColorTotal = '#a00211';
            
            // SROI% color
            let sroiColorTotal = '';
            if (avgSROI < 0) sroiColorTotal = '#a00211';
            else if (avgSROI >= 0 && avgSROI < 50) sroiColorTotal = '#ffc107';
            else if (avgSROI >= 50 && avgSROI < 100) sroiColorTotal = '#3591dc';
            else if (avgSROI >= 100 && avgSROI <= 150) sroiColorTotal = '#28a745';
            else sroiColorTotal = '#e83e8c';
            
            // Update totals with color formatting
            // Calculate average price = Total Sold Amount / Total Sold Qty (L30)
            const avgPrice = totalL30 > 0 ? totalPrice / totalL30 : 0;
            $('#modal-total-price').text('$' + avgPrice.toFixed(2));
            $('#modal-total-views').text(totalViews.toLocaleString());
            $('#modal-total-l30').text(totalL30.toLocaleString());
            
            // Update header L30 to match the calculated total from breakdown (fixes L30 diff issue)
            $('#modal-header-l30').text(totalL30.toLocaleString());
            
            const roiColorTotal = (pct) => {
                if (pct < 50) return '#a00211';
                if (pct < 100) return '#ffc107';
                if (pct <= 150) return '#28a745';
                return '#e83e8c';
            };
            $('#modal-avg-cvr').html(`<span style="${styleForCellColor(cvrColorTotal)}">${Math.round(avgCVR)}%</span>`);
            $('#modal-avg-groi').html(`<span style="${styleForCellColor(roiColorTotal(avgGROI))}">${Math.round(avgGROI)}%</span>`);
            $('#modal-avg-nroi').html(`<span style="${styleForCellColor(roiColorTotal(avgNROI))}">${Math.round(avgNROI)}%</span>`);
            $('#modal-avg-gpft').html(`<span style="${gpftStyleTotal}">${Math.round(avgGPFT)}%</span>`);
            $('#modal-avg-ad').html(`<span style="${styleForCellColor(adColorTotal)};font-weight:600;">${Math.round(avgAD)}%</span>`);
            $('#modal-avg-npft').html(`<span style="${npftStyleTotal}">${Math.round(avgNPFT)}%</span>`);
            
            $('#modal-avg-sprice').text('$' + (spriceCount > 0 ? totalSPRICE / spriceCount : 0).toFixed(2));
            $('#modal-avg-sgpft').html(`<span style="${styleForCellColor(sgpftColorTotal)}">${Math.round(avgSGPFT)}%</span>`);
            $('#modal-avg-spft').html(`<span style="${styleForCellColor(spftColorTotal)}">${Math.round(avgSPFT)}%</span>`);
            $('#modal-avg-sroi').html(`<span style="${styleForCellColor(sroiColorTotal)}">${Math.round(avgSROI)}%</span>`);
            $('#modal-avg-snroi').html(`<span style="${styleForCellColor(roiColorTotal(avgSNROI))}">${Math.round(avgSNROI)}%</span>`);
            updateOvl30SortIcons();
            scheduleAutoFitOvl30TableFont();
        }

        // ==================== TABULATOR INITIALIZATION ====================
        
        table = new Tabulator("#cvr-table", {
            ajaxURL: "/cvr-master-data-json",
            ajaxSorting: false,
            layout: "fitDataFill",
            pagination: true,
            paginationSize: 100,
            paginationSizeSelector: [10, 25, 50, 100, 200],
            paginationCounter: "rows",
            columnCalcs: "top",
            langs: {
                "default": {
                    "pagination": {
                        "page_size": "SKU Count"
                    }
                }
            },
            initialSort: [{ column: "parent", dir: "asc" }],
            rowFormatter: function(row) {
                const data = row.getData();
                if (data.is_parent_summary === true) {
                    row.getElement().style.backgroundColor = "#bde0ff";
                    row.getElement().style.fontWeight = "bold";
                    row.getElement().classList.add("parent-row");
                }
            },
            columns: [
                {
                    title: "#",
                    field: "_selected",
                    headerSort: false,
                    minWidth: 40,
                    hozAlign: "center",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sku = rowData.sku;
                        const checked = selectedSkus.has(sku) ? 'checked' : '';
                        return `<input type="checkbox" class="row-select-cb" data-sku="${(sku || '').replace(/"/g, '&quot;')}" ${checked}>`;
                    },
                    titleFormatter: function(column) {
                        const allChecked = isAllFilteredSelected();
                        return `<input type="checkbox" class="select-all-cb" title="Select all filtered rows SKUs (excludes parent rows)" ${allChecked ? 'checked' : ''}>`;
                    }
                },
                {
                    title: "Image",
                    field: "image_path",
                    sorter: "string",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value) {
                            return `<img src="${value}" alt="Product" style="width: 50px; height: 50px; object-fit: cover;">`;
                        }
                        return '';
                    },
                    minWidth: 60
                },
                {
                    title: "P",
                    field: "parent",
                    sorter: "string",
                    minWidth: 40,
                    hozAlign: "center",
                    formatter: function(cell) {
                        const parent = cell.getValue();
                        if (!parent) {
                            return '<span class="parent-sku-dot no-parent" title="No parent"></span>';
                        }
                        return `<span class="parent-sku-dot parent-sku-dot-btn" 
                                    data-parent="${parent.replace(/"/g, '&quot;')}" 
                                    title="Click to view SKUs for parent: ${parent.replace(/"/g, '&quot;')}"></span>`;
                    }
                },
                {
                    title: "Parent",
                    field: "parent",
                    sorter: "string",
                    headerFilter: "input",
                    headerFilterPlaceholder: "Search Parent",
                    minWidth: 80,
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const parent = rowData.parent;
                        if (parent === undefined || parent === null || (typeof parent === 'string' && !parent.trim())) return '-';
                        return (typeof parent === 'string' ? parent : String(parent)).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
                    }
                },
                {
                    title: "SKU",
                    field: "sku",
                    sorter: "string",
                    headerFilter: "input",
                    headerFilterPlaceholder: "Search SKU...",
                    cssClass: "text-primary fw-bold pricing-master-sku-col",
                    tooltip: true,
                    frozen: true,
                    minWidth: 120,
                    formatter: function(cell) {
                        const sku = cell.getValue();
                        let html = `<span class="pricing-master-sku-text">${sku}</span>`;
                        html += `<i class="fa fa-copy text-secondary copy-sku-btn" 
                                   style="cursor: pointer; margin-left: 8px; font-size: 14px;" 
                                   data-sku="${sku}" title="Copy SKU"></i>`;
                        return html;
                    }
                },
                {
                    title: "Details",
                    field: "details_dot",
                    headerSort: false,
                    hozAlign: "center",
                    minWidth: 52,
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        if (rowData.is_parent_summary === true) return '';
                        const sku = rowData.sku;
                        const imagePath = rowData.image_path || '';
                        const inv = rowData.inventory ?? rowData.inv ?? 0;
                        const value = parseFloat(cell.getRow().getData().overall_l30 || 0);
                        const dilPercent = rowData.dil_percent || 0;
                        return `<i class="fas fa-search text-info ovl30-info-icon" 
                               style="cursor: pointer; font-size: 12px;" 
                               data-sku="${sku}"
                               data-image="${imagePath}"
                               data-inv="${inv}"
                               data-l30="${value}"
                               data-dil="${dilPercent}"
                               title="View breakdown for ${sku}"></i>`;
                    }
                },
                {
                    title: "INV",
                    field: "inventory",
                    hozAlign: "center",
                    minWidth: 60,
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const value = parseFloat(cell.getValue() || 0);
                        let html = value === 0 ? '<span style="color: #dc3545; font-weight: 600;">0</span>' : `<span style="font-weight: 600;">${value}</span>`;
                        const parentEsc = (rowData.parent || '').replace(/"/g, '&quot;');
                        const skuEsc = (rowData.sku || '').replace(/"/g, '&quot;');
                        if (rowData.is_parent_summary === true) {
                            html += ' <i class="fas fa-circle pricing-master-chart-link ms-1" data-metric="inv" data-parent="' + parentEsc + '" data-sku="' + skuEsc + '" style="cursor:pointer;color:#4361ee;font-size:8px;vertical-align:middle;" title="View Inv graph (Parent, Rolling L30)"></i>';
                        } else {
                            html += ' <i class="fas fa-circle pricing-master-chart-link ms-1" data-metric="inv" data-sku="' + skuEsc + '" style="cursor:pointer;color:#4361ee;font-size:8px;vertical-align:middle;" title="View Inv graph (Rolling L30)"></i>';
                        }
                        return html;
                    }
                },
                {
                    title: "OV L30 + FBA",
                    field: "ov_l30_plus_fba",
                    hozAlign: "center",
                    minWidth: 100,
                    sorter: "number",
                    headerTooltip: "Shopify OV L30 plus FBA L30: Product SKU is resolved to an FBA listing (FbaInventoryService, same as FBA Dispatch), then fba_monthly_sales.l30_units for that MSKU.",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue() || 0);
                        return `<span style="font-weight: 600;">${value}</span>`;
                    }
                },
                {
                    title: "OV L30",
                    field: "overall_l30",
                    hozAlign: "center",
                    minWidth: 80,
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue() || 0);
                        const rowData = cell.getRow().getData();
                        const sku = rowData.sku;
                        const parentEsc = (rowData.parent || '').replace(/"/g, '&quot;');
                        const skuEsc = (sku || '').replace(/"/g, '&quot;');
                        if (rowData.is_parent_summary === true) {
                            return `<span style="font-weight: 600;">${value}</span>
                            <i class="fas fa-circle pricing-master-chart-link ms-1" data-metric="ov_l30" data-parent="${parentEsc}" data-sku="${skuEsc}" style="cursor:pointer;color:#28a745;font-size:8px;vertical-align:middle;" title="View OV L30 graph (Parent, Rolling L30)"></i>`;
                        }
                        return `<span style="font-weight: 600;">${value}</span>
                            <i class="fas fa-circle pricing-master-chart-link ms-1" data-metric="ov_l30" data-sku="${skuEsc}" style="cursor:pointer;color:#28a745;font-size:8px;vertical-align:middle;" title="View OV L30 graph (Rolling L30)"></i>`;
                    }
                },
                {
                    title: "SW L30",
                    field: "m_l30",
                    hozAlign: "center",
                    minWidth: 80,
                    sorter: "number",
                    headerTooltip: "SW L30: total L30 summed across marketplace channels (Amazon, eBay, Walmart, Temu, Temu 2, Macy's, Reverb, etc.). Per-channel values appear in the SKU detail modal. Green when SW L30 equals OV L30; red otherwise.",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sw = parseFloat(cell.getValue() || 0);
                        const ov = parseFloat(rowData.overall_l30 ?? 0);
                        const match = Math.abs(sw - ov) < 0.01;
                        const color = match ? '#28a745' : '#dc3545';
                        return `<span style="font-weight: 600; color: ${color};">${sw}</span>`;
                    }
                },
                {
                    title: "Dil %",
                    field: "dil_percent",
                    hozAlign: "center",
                    sorter: "number",
                    headerTooltip: "DIL%: Red <25% · Green 25–50% · Pink 50%+",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const value = parseFloat(cell.getValue() || 0);
                        const color = getDilPercentColor(value);

                        let html = `<span style="${styleForCellColor(color)}">${Math.round(value)}%</span>`;
                        const parentEscDil = (rowData.parent || '').replace(/"/g, '&quot;');
                        const skuEscDil = (rowData.sku || '').replace(/"/g, '&quot;');
                        if (rowData.is_parent_summary === true) {
                            html += ' <i class="fas fa-circle pricing-master-chart-link ms-1" data-metric="dil" data-parent="' + parentEscDil + '" data-sku="' + skuEscDil + '" style="cursor:pointer;color:#0d6efd;font-size:8px;vertical-align:middle;" title="View DIL history (Parent, Rolling L30)"></i>';
                        } else {
                            html += ' <i class="fas fa-circle pricing-master-chart-link ms-1" data-metric="dil" data-sku="' + skuEscDil + '" style="cursor:pointer;color:#0d6efd;font-size:8px;vertical-align:middle;" title="View DIL history (Rolling L30)"></i>';
                        }
                        return html;
                    },
                    minWidth: 60
                },
                {
                    title: "Avg Price",
                    field: "avg_price",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const value = parseFloat(cell.getValue() || 0);
                        let html = value === 0 ? '<span style="color: #6c757d;">-</span>' : `<span style="font-weight: 600;">$${value.toFixed(2)}</span>`;
                        const parentEscPrice = (rowData.parent || '').replace(/"/g, '&quot;');
                        const skuEscPrice = (rowData.sku || '').replace(/"/g, '&quot;');
                        if (rowData.is_parent_summary === true) {
                            html += ' <i class="fas fa-circle pricing-master-chart-link ms-1" data-metric="price" data-parent="' + parentEscPrice + '" data-sku="' + skuEscPrice + '" style="cursor:pointer;color:#e83e8c;font-size:8px;vertical-align:middle;" title="View Price graph (Parent, Rolling L30)"></i>';
                        } else {
                            html += ' <i class="fas fa-circle pricing-master-chart-link ms-1" data-metric="price" data-sku="' + skuEscPrice + '" style="cursor:pointer;color:#e83e8c;font-size:8px;vertical-align:middle;" title="View Price graph (Rolling L30)"></i>';
                        }
                        return html;
                    },
                    minWidth: 70
                },
                {
                    title: "Avg GPFT%",
                    field: "avg_gpft",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue() || 0);
                        // <20 red, 20–30 yellow, 30–40 green, >40 black on magenta
                        return `<span style="${styleForGpftValue(value)}">${Math.round(value)}%</span>`;
                    },
                    minWidth: 70
                },
                {
                    title: "Avg GROI%",
                    field: "avg_roi",
                    hozAlign: "center",
                    minWidth: 60,
                    sorter: "number",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value == null || value === '') return '-';
                        const pct = parseFloat(value);
                        if (!Number.isFinite(pct)) return '-';
                        // Same slabs as Amz GROI%: <50 red, 50–100 yellow, 100–150 green, >150 magenta
                        let color = '';
                        if (pct < 50) color = '#a00211';
                        else if (pct >= 50 && pct < 100) color = '#ffc107';
                        else if (pct >= 100 && pct <= 150) color = '#28a745';
                        else color = '#e83e8c';
                        return `<span style="${styleForCellColor(color)}">${Math.round(pct)}%</span>`;
                    }
                },
                {
                    title: "Avg NPFT%",
                    field: "avg_pft",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue() || 0);
                        // NPFT: <30 red, 30–40 yellow, 40–50 green, >50 black on magenta
                        return `<span style="${styleForNpftValue(value)}">${Math.round(value)}%</span>`;
                    },
                    minWidth: 70
                },
                {
                    title: "Avg NROI%",
                    field: "avg_nroi",
                    hozAlign: "center",
                    minWidth: 60,
                    sorter: "number",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value == null || value === '') return '-';
                        const pct = parseFloat(value);
                        if (!Number.isFinite(pct)) return '-';
                        // Same slabs as GROI%: <50 red, 50–100 yellow, 100–150 green, >150 magenta
                        let color = '';
                        if (pct < 50) color = '#a00211';
                        else if (pct >= 50 && pct < 100) color = '#ffc107';
                        else if (pct >= 100 && pct <= 150) color = '#28a745';
                        else color = '#e83e8c';
                        return `<span style="${styleForCellColor(color)}">${Math.round(pct)}%</span>`;
                    }
                },
                {
                    title: "Missing L",
                    field: "missing_l",
                    hozAlign: "center",
                    minWidth: 55,
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        if (rowData.is_parent_summary === true) return '';
                        const hasMissing = cell.getValue();
                        const color = hasMissing ? '#dc3545' : '#28a745';
                        const borderColor = hasMissing ? '#a00211' : '#1a7a38';
                        const title = hasMissing ? 'Has missing listings – click to view breakdown' : 'All channels have a price';
                        const sku = (rowData.sku || '').replace(/"/g, '&quot;');
                        const imagePath = (rowData.image_path || '').replace(/"/g, '&quot;');
                        const inv = rowData.inventory ?? rowData.inv ?? 0;
                        const l30 = rowData.overall_l30 || 0;
                        const dil = rowData.dil_percent || 0;
                        return `<span class="missing-l-main-dot"
                            data-sku="${sku}"
                            data-image="${imagePath}"
                            data-inv="${inv}"
                            data-l30="${l30}"
                            data-dil="${dil}"
                            style="display:inline-block;width:10px;height:10px;border-radius:50%;background:${color};cursor:pointer;border:1px solid ${borderColor};"
                            title="${title}"></span>`;
                    }
                },
                {
                    title: "CVR",
                    field: "avg_cvr",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const value = parseFloat(cell.getValue() || 0);
                        let color = '';
                        if (value === 0) color = '#6c757d';
                        else if (value < 1) color = '#a00211';
                        else if (value >= 1 && value < 3) color = '#ffc107';
                        else if (value >= 3 && value < 5) color = '#28a745';
                        else color = '#e83e8c';
                        let html = `<span style="${styleForCellColor(color)}">${value.toFixed(1)}%</span>`;
                        const parentEscCvr = (rowData.parent || '').replace(/"/g, '&quot;');
                        const skuEscCvr = (rowData.sku || '').replace(/"/g, '&quot;');
                        if (rowData.is_parent_summary === true) {
                            html += ' <i class="fas fa-circle pricing-master-chart-link ms-1" data-metric="cvr" data-parent="' + parentEscCvr + '" data-sku="' + skuEscCvr + '" style="cursor:pointer;color:#ff9c00;font-size:8px;vertical-align:middle;" title="View CVR graph (Parent, Rolling L30)"></i>';
                        } else {
                            html += ' <i class="fas fa-circle pricing-master-chart-link ms-1" data-metric="cvr" data-sku="' + skuEscCvr + '" style="cursor:pointer;color:#ff9c00;font-size:8px;vertical-align:middle;" title="View CVR graph (Rolling L30)"></i>';
                        }
                        return html;
                    },
                    minWidth: 70
                },
                {
                    title: "Amz Price",
                    field: "amazon_price",
                    visible: false,
                    hozAlign: "right",
                    minWidth: 70,
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const value = cell.getValue();
                        let html = '';
                        if (value == null || value === '' || parseFloat(value) <= 0) {
                            html = '-';
                        } else {
                            const num = parseFloat(value);
                            html = `<span style="font-weight: 600;">$` + num.toFixed(2) + '</span>';
                        }
                        const parentEscAmz = (rowData.parent || '').replace(/"/g, '&quot;');
                        const skuEscAmz = (rowData.sku || '').replace(/"/g, '&quot;');
                        if (rowData.is_parent_summary === true) {
                            html += ' <i class="fas fa-circle pricing-master-chart-link ms-1" data-metric="amz_price" data-parent="' + parentEscAmz + '" data-sku="' + skuEscAmz + '" style="cursor:pointer;color:#e83e8c;font-size:8px;vertical-align:middle;" title="View Amz Price history (Parent, Rolling L30)"></i>';
                        } else {
                            html += ' <i class="fas fa-circle pricing-master-chart-link ms-1" data-metric="amz_price" data-sku="' + skuEscAmz + '" style="cursor:pointer;color:#e83e8c;font-size:8px;vertical-align:middle;" title="View Amz Price history (Rolling L30)"></i>';
                        }
                        return html;
                    }
                },
                {
                    title: "Amz GPFT%",
                    field: "amz_pft",
                    visible: false,
                    hozAlign: "center",
                    minWidth: 60,
                    sorter: "number",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value == null || value === '') return '-';
                        const pct = parseFloat(value);
                        return `<span style="${styleForGpftValue(pct)}">${Math.round(pct)}%</span>`;
                    }
                },
                {
                    title: "Amz GROI%",
                    field: "amz_roi",
                    visible: false,
                    hozAlign: "center",
                    minWidth: 60,
                    sorter: "number",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value == null || value === '') return '-';
                        const pct = parseFloat(value);
                        let color = '';
                        if (pct < 50) color = '#a00211';
                        else if (pct >= 50 && pct < 100) color = '#ffc107';
                        else if (pct >= 100 && pct <= 150) color = '#28a745';
                        else color = '#e83e8c';
                        return `<span style="${styleForCellColor(color)}">${pct.toFixed(0)}%</span>`;
                    }
                },
                {
                    title: "Sprice",
                    field: "sprice_dot",
                    headerSort: false,
                    minWidth: 44,
                    hozAlign: "center",
                    formatter: function(cell) {
                        return '<span style="cursor:pointer;color:#0d6efd;font-size:14px;line-height:1;" title="Click to show Sprice details">●</span>';
                    }
                },
                {
                    title: "Amz SPRICE",
                    field: "amazon_sprice",
                    visible: false,
                    hozAlign: "right",
                    minWidth: 70,
                    sorter: "number",
                    editor: "number",
                    editorParams: { step: 0.01, min: 0 },
                    editable: function(cell) {
                        const d = cell.getRow().getData();
                        return d.is_parent_summary !== true && d.sku && d.sku.indexOf('PARENT') === -1;
                    },
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        if (rowData.is_parent_summary === true) {
                            const value = cell.getValue();
                            if (value == null || value === '' || parseFloat(value) <= 0) return '-';
                            return '<span style="font-weight: 600;">$' + parseFloat(value).toFixed(2) + '</span>';
                        }
                        const value = cell.getValue();
                        if (value == null || value === '' || parseFloat(value) <= 0) return '-';
                        const num = parseFloat(value);
                        return `<span style="font-weight: 600;">$` + num.toFixed(2) + '</span>';
                    }
                },
                {
                    title: "Amz SGPFT%",
                    field: "amazon_sgpft",
                    visible: false,
                    hozAlign: "center",
                    minWidth: 70,
                    sorter: "number",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value == null || value === '') return '-';
                        const pct = parseFloat(value);
                        let color = '';
                        if (pct < 0) color = '#a00211';
                        else if (pct >= 0 && pct < 10) color = '#ffc107';
                        else if (pct >= 10 && pct < 20) color = '#3591dc';
                        else if (pct >= 20 && pct <= 40) color = '#28a745';
                        else color = '#e83e8c';
                        return `<span style="${styleForCellColor(color)}">${Math.round(pct)}%</span>`;
                    }
                },
                {
                    title: "Amz SPFT%",
                    field: "amazon_spft",
                    visible: false,
                    hozAlign: "center",
                    minWidth: 70,
                    sorter: "number",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value == null || value === '') return '-';
                        const pct = parseFloat(value);
                        let color = '';
                        if (pct < 0) color = '#a00211';
                        else if (pct >= 0 && pct < 10) color = '#ffc107';
                        else if (pct >= 10 && pct < 20) color = '#3591dc';
                        else if (pct >= 20 && pct <= 40) color = '#28a745';
                        else color = '#e83e8c';
                        return `<span style="${styleForCellColor(color)}">${Math.round(pct)}%</span>`;
                    }
                },
                {
                    title: "Amz SROI%",
                    field: "amazon_sroi",
                    visible: false,
                    hozAlign: "center",
                    minWidth: 70,
                    sorter: "number",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value == null || value === '') return '-';
                        const pct = parseFloat(value);
                        let color = '';
                        if (pct < 0) color = '#a00211';
                        else if (pct >= 0 && pct < 50) color = '#ffc107';
                        else if (pct >= 50 && pct < 100) color = '#3591dc';
                        else if (pct >= 100 && pct <= 150) color = '#28a745';
                        else color = '#e83e8c';
                        return `<span style="${styleForCellColor(color)}">${Math.round(pct)}%</span>`;
                    }
                },
                {
                    title: "Rating",
                    field: "rating",
                    hozAlign: "center",
                    sorter: "number",
                    tooltip: "Rating and reviews from Jungle Scout",
                    formatter: function(cell) {
                        const rating = cell.getValue();
                        const rowData = cell.getRow().getData();
                        const reviews = rowData.reviews || 0;
                        let html = '';
                        if (!rating || rating === 0) {
                            html = '<span style="color: #6c757d;">-</span>';
                        } else {
                            let ratingColor = '';
                            const ratingVal = parseFloat(rating);
                            if (ratingVal < 3) ratingColor = '#a00211';
                            else if (ratingVal >= 3 && ratingVal <= 3.5) ratingColor = '#ffc107';
                            else if (ratingVal >= 3.51 && ratingVal <= 3.99) ratingColor = '#3591dc';
                            else if (ratingVal >= 4 && ratingVal <= 4.5) ratingColor = '#28a745';
                            else ratingColor = '#e83e8c';
                            const reviewColor = reviews < 4 ? '#a00211' : '#6c757d';
                            html = `<div class="d-flex align-items-center justify-content-center gap-1 flex-wrap">
                                <span style="${styleForCellColor(ratingColor)}"><i class="fa fa-star"></i> ${parseFloat(rating).toFixed(1)}</span>
                                <span style="font-size: 11px; ${styleForCellColor(reviewColor)}">(${parseInt(reviews).toLocaleString()})</span>
                            </div>`;
                        }
                        const parentEscRat = (rowData.parent || '').replace(/"/g, '&quot;');
                        const skuEscRat = (rowData.sku || '').replace(/"/g, '&quot;');
                        if (rowData.is_parent_summary === true) {
                            html += ' <i class="fas fa-circle pricing-master-chart-link ms-1" data-metric="rating" data-parent="' + parentEscRat + '" data-sku="' + skuEscRat + '" style="cursor:pointer;color:#e83e8c;font-size:8px;vertical-align:middle;" title="View Rating history (Parent, Rolling L30)"></i>';
                        } else {
                            html += ' <i class="fas fa-circle pricing-master-chart-link ms-1" data-metric="rating" data-sku="' + skuEscRat + '" style="cursor:pointer;color:#e83e8c;font-size:8px;vertical-align:middle;" title="View Rating history (Rolling L30)"></i>';
                        }
                        return html;
                    },
                    minWidth: 70
                },
                {
                    title: "AVG LQS",
                    field: "listing_quality_score",
                    hozAlign: "center",
                    sorter: "number",
                    tooltip: "5core Listing Quality Score from Jungle Scout",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value == null || value === '') return '<span style="color: #6c757d;">-</span>';
                        const num = typeof value === 'number' ? value : parseFloat(value);
                        if (isNaN(num)) return '<span style="color: #6c757d;">-</span>';
                        return '<span style="font-weight: 600;">' + num + '</span>';
                    },
                    minWidth: 50
                },
                {
                    title: "Total Views",
                    field: "total_views",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const value = parseInt(cell.getValue() || 0);
                        let html = value === 0 ? '<span style="color: #6c757d;">0</span>' : `<span style="font-weight: 600;">${value.toLocaleString()}</span>`;
                        const parentEscTv = (rowData.parent || '').replace(/"/g, '&quot;');
                        const skuEscTv = (rowData.sku || '').replace(/"/g, '&quot;');
                        if (rowData.is_parent_summary === true) {
                            html += ' <i class="fas fa-circle pricing-master-chart-link ms-1" data-metric="total_views" data-parent="' + parentEscTv + '" data-sku="' + skuEscTv + '" style="cursor:pointer;color:#17a2b8;font-size:8px;vertical-align:middle;" title="View Total Views history (Parent, Rolling L30)"></i>';
                        } else {
                            html += ' <i class="fas fa-circle pricing-master-chart-link ms-1" data-metric="total_views" data-sku="' + skuEscTv + '" style="cursor:pointer;color:#17a2b8;font-size:8px;vertical-align:middle;" title="View Total Views history (Rolling L30)"></i>';
                        }
                        return html;
                    },
                    minWidth: 80
                },
                {
                    title: "Amz LMP",
                    field: "amazon_lmp_price",
                    visible: false,
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sku = (rowData.sku || '').replace(/"/g, '&quot;');
                        const skuEnc = encodeURIComponent(rowData.sku || '');
                        if (rowData.is_parent_summary === true) {
                            const v = cell.getValue();
                            return v != null ? '<span style="font-weight: 600;">$' + parseFloat(v).toFixed(2) + '</span>' : '<span class="text-muted">-</span>';
                        }
                        const value = cell.getValue();
                        const price = value != null && value !== '' ? parseFloat(value) : null;
                        if (price == null || price <= 0) {
                            const url = '/repricer/amazon-search' + (skuEnc ? '?sku=' + skuEnc : '');
                            return '<a href="' + url + '" target="_blank" rel="noopener" class="lmp-no-data-link" title="No LMP – open Amazon repricer search"><i class="fas fa-circle" style="color: #ff9c00; font-size: 10px;"></i></a>';
                        }
                        const avgPrice = parseFloat(rowData.avg_price || 0);
                        const color = (avgPrice > 0 && price < avgPrice) ? '#dc3545' : '#28a745';
                        return `<a href="#" class="lmp-price-link" data-sku="${sku}" data-marketplace="amazon" style="${styleForCellColor(color)} text-decoration: none; cursor: pointer;">$${price.toFixed(2)}</a>`;
                    },
                    minWidth: 70
                },
                {
                    title: "eBay LMP",
                    field: "ebay_lmp_price",
                    visible: false,
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sku = (rowData.sku || '').replace(/"/g, '&quot;');
                        const skuEnc = encodeURIComponent(rowData.sku || '');
                        if (rowData.is_parent_summary === true) {
                            const v = cell.getValue();
                            return v != null ? '<span style="font-weight: 600;">$' + parseFloat(v).toFixed(2) + '</span>' : '<span class="text-muted">-</span>';
                        }
                        const value = cell.getValue();
                        const price = value != null && value !== '' ? parseFloat(value) : null;
                        if (price == null || price <= 0) {
                            const url = '/repricer/ebay-search' + (skuEnc ? '?sku=' + skuEnc : '');
                            return '<a href="' + url + '" target="_blank" rel="noopener" class="lmp-no-data-link" title="No LMP – open eBay repricer search"><i class="fas fa-circle" style="color: #ff9c00; font-size: 10px;"></i></a>';
                        }
                        const avgPrice = parseFloat(rowData.avg_price || 0);
                        const color = (avgPrice > 0 && price < avgPrice) ? '#dc3545' : '#28a745';
                        return `<a href="#" class="lmp-price-link" data-sku="${sku}" data-marketplace="ebay" style="${styleForCellColor(color)} text-decoration: none; cursor: pointer;">$${price.toFixed(2)}</a>`;
                    },
                    minWidth: 70
                },
                {
                    title: "Google LMP",
                    field: "google_lmp_price",
                    visible: false,
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sku = (rowData.sku || '').replace(/"/g, '&quot;');
                        const skuEnc = encodeURIComponent(rowData.sku || '');
                        if (rowData.is_parent_summary === true) {
                            const v = cell.getValue();
                            return v != null ? '<span style="font-weight: 600;">$' + parseFloat(v).toFixed(2) + '</span>' : '<span class="text-muted">-</span>';
                        }
                        const value = cell.getValue();
                        const price = value != null && value !== '' ? parseFloat(value) : null;
                        if (price == null || price <= 0) {
                            const url = '/repricer/google-search' + (skuEnc ? '?sku=' + skuEnc : '');
                            return '<a href="' + url + '" target="_blank" rel="noopener" class="lmp-no-data-link" title="No LMP – open Google repricer search"><i class="fas fa-circle" style="color: #ff9c00; font-size: 10px;"></i></a>';
                        }
                        const avgPrice = parseFloat(rowData.avg_price || 0);
                        const color = (avgPrice > 0 && price < avgPrice) ? '#dc3545' : '#28a745';
                        return `<a href="#" class="lmp-price-link" data-sku="${sku}" data-marketplace="google" style="${styleForCellColor(color)} text-decoration: none; cursor: pointer;">$${price.toFixed(2)}</a>`;
                    },
                    minWidth: 80
                },
                {
                    title: "Temu LMP",
                    field: "temu_lmp_price",
                    visible: false,
                    hozAlign: "center",
                    sorter: "number",
                    headerTooltip: "Temu / Temu 2 LMP from temu_lmp (same as /temu-decrease)",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sku = (rowData.sku || '').replace(/"/g, '&quot;');
                        if (rowData.is_parent_summary === true) {
                            const v = cell.getValue();
                            return v != null ? '<span style="font-weight: 600;">$' + parseFloat(v).toFixed(2) + '</span>' : '<span class="text-muted">-</span>';
                        }
                        const value = cell.getValue();
                        const price = value != null && value !== '' ? parseFloat(value) : null;
                        if (price == null || price <= 0) {
                            return '<a href="#" class="lmp-price-link" data-sku="' + sku + '" data-marketplace="temu" title="No Temu LMP – open drawer"><i class="fas fa-circle" style="color: #ff9c00; font-size: 10px;"></i></a>';
                        }
                        const avgPrice = parseFloat(rowData.avg_price || 0);
                        const color = (avgPrice > 0 && price < avgPrice) ? '#dc3545' : '#28a745';
                        return `<a href="#" class="lmp-price-link" data-sku="${sku}" data-marketplace="temu" style="${styleForCellColor(color)} text-decoration: none; cursor: pointer;">$${price.toFixed(2)}</a>`;
                    },
                    minWidth: 70
                },
                {
                    title: "Avg AD",
                    field: "avg_ad",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue() || 0);
                        let color = '';
                        
                        // Color coding for AD% (lower is better)
                        if (value >= 100) color = '#a00211';
                        else if (value >= 50) color = '#dc3545';
                        else if (value >= 20) color = '#ffc107';
                        else if (value >= 10) color = '#3591dc';
                        else if (value > 0) color = '#28a745';
                        else color = '#6c757d';
                        
                        return `<span style="${styleForCellColor(color)}">${Math.round(value)}%</span>`;
                    },
                    minWidth: 70
                },
                {
                    title: "Sh L30",
                    field: "shein_l30",
                    visible: false,
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const value = parseInt(cell.getValue() || 0);
                        if (value === 0) return '<span style="color:#6c757d;">0</span>';
                        return `<span style="color:#e83e8c;font-weight:600;">${value.toLocaleString()}</span>`;
                    },
                    minWidth: 60
                },
                {
                    title: "AE L30",
                    field: "ae_l30",
                    visible: false,
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseInt(cell.getValue() || 0);
                        if (value === 0) return '<span style="color:#6c757d;">0</span>';
                        return `<span style="color:#ff6600;font-weight:600;">${value.toLocaleString()}</span>`;
                    },
                    minWidth: 60
                },
                {
                    title: "PP L30",
                    field: "pp_l30",
                    visible: false,
                    hozAlign: "center",
                    sorter: "number",
                    tooltip: "Purchasing Power last-30-days sales",
                    formatter: function(cell) {
                        const value = parseInt(cell.getValue() || 0);
                        if (value === 0) return '<span style="color:#6c757d;">0</span>';
                        return `<span style="color:#6f42c1;font-weight:600;">${value.toLocaleString()}</span>`;
                    },
                    minWidth: 60
                }
            ]
        });

        // Row reference for Sprice modal save (set when modal opens, used on blur)
        let spriceModalCurrentRow = null;

        // Sprice dot click: open modal with editable Amz SPRICE and instant SGPFT/SPFT/SROI
        table.on('cellClick', function(e, cell) {
            if (cell.getField() !== 'sprice_dot') return;
            const row = cell.getRow();
            const d = row.getData();
            if (d.is_parent_summary === true) return;
            spriceModalCurrentRow = row;
            const skuName = (d.sku || '-') + (d.parent ? ' (' + d.parent + ')' : '');
            const lp = parseFloat(d.amazon_lp) || 0;
            const ship = parseFloat(d.amazon_ship) || 0;
            const ad = parseFloat(d.amazon_ad) || 0;
            const margin = parseFloat(d.amazon_margin) || 0.80;
            const l30 = parseInt(d.amazon_l30, 10) || 0;
            const sprice = d.amazon_sprice;
            const sgpft = d.amazon_sgpft;
            const spft = d.amazon_spft;
            const sroi = d.amazon_sroi;
            $('#spriceModalSkuName').text(skuName);
            const $modal = $('#spriceDetailsModal');
            $modal.attr('data-sku', d.sku || '');
            $modal.attr('data-lp', lp);
            $modal.attr('data-ship', ship);
            $modal.attr('data-ad', ad);
            $modal.attr('data-margin', margin);
            $modal.attr('data-l30', l30);
            const spriceVal = (sprice != null && sprice !== '' && parseFloat(sprice) > 0) ? parseFloat(sprice) : '';
            $('#spriceModalAmzSpriceInput').val(spriceVal === '' ? '' : spriceVal.toFixed(2));
            function updateSpriceModalCalculated(spriceNum) {
                if (spriceNum <= 0) {
                    applyCellColor($('#spriceModalSgpft'), '#6c757d');
                    $('#spriceModalSgpft').text('-');
                    applyCellColor($('#spriceModalSpft'), '#6c757d');
                    $('#spriceModalSpft').text('-');
                    applyCellColor($('#spriceModalSroi'), '#6c757d');
                    $('#spriceModalSroi').text('-');
                    return;
                }
                const sgpftVal = ((spriceNum * margin - ship - lp) / spriceNum) * 100;
                const spftVal = l30 === 0 ? sgpftVal : (sgpftVal - ad);
                const sroiVal = lp > 0 ? ((spriceNum * margin - lp - ship) / lp) * 100 : 0;
                applyCellColor($('#spriceModalSgpft'), getSgpftSpftColor(sgpftVal));
                $('#spriceModalSgpft').text(Math.round(sgpftVal) + '%');
                applyCellColor($('#spriceModalSpft'), getSgpftSpftColor(spftVal));
                $('#spriceModalSpft').text(Math.round(spftVal) + '%');
                applyCellColor($('#spriceModalSroi'), getSroiColor(sroiVal));
                $('#spriceModalSroi').text(Math.round(sroiVal) + '%');
            }
            if (spriceVal !== '') updateSpriceModalCalculated(parseFloat(spriceVal));
            else {
                applyCellColor($('#spriceModalSgpft'), '#6c757d');
                $('#spriceModalSgpft').text('-');
                applyCellColor($('#spriceModalSpft'), '#6c757d');
                $('#spriceModalSpft').text('-');
                applyCellColor($('#spriceModalSroi'), '#6c757d');
                $('#spriceModalSroi').text('-');
            }
            new bootstrap.Modal(document.getElementById('spriceDetailsModal')).show();
        });

        // Sprice modal: top-center position, draggable by header, and load FBA data
        $('#spriceDetailsModal').on('shown.bs.modal', function() {
            const modal = document.getElementById('spriceDetailsModal');
            const dialog = modal.querySelector('.modal-dialog');
            if (!dialog) return;
            dialog.style.position = 'fixed';
            dialog.style.left = '50%';
            dialog.style.top = '1.5rem';
            dialog.style.transform = 'translateX(-50%)';
            dialog.style.margin = '0';
        });
        (function() {
            let startX = 0, startY = 0, startLeft = 0, startTop = 0;
            const modal = document.getElementById('spriceDetailsModal');
            if (!modal) return;
            const header = modal.querySelector('.sprice-modal-drag-header');
            const dialog = modal.querySelector('.modal-dialog');
            if (!header || !dialog) return;
            function onMove(e) {
                dialog.style.left = (startLeft + (e.clientX - startX)) + 'px';
                dialog.style.top = (startTop + (e.clientY - startY)) + 'px';
                dialog.style.transform = 'none';
            }
            function onUp() {
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
            }
            header.addEventListener('mousedown', function(e) {
                if (e.target.closest('.btn-close')) return;
                const r = dialog.getBoundingClientRect();
                startLeft = r.left;
                startTop = r.top;
                startX = e.clientX;
                startY = e.clientY;
                document.addEventListener('mousemove', onMove);
                document.addEventListener('mouseup', onUp);
                e.preventDefault();
            });
        })();

        // Amz SPRICE edited in table: save and recalculate SGPFT, SPFT, SROI
        table.on('cellEdited', function(cell) {
            if (cell.getField() !== 'amazon_sprice') return;
            const row = cell.getRow();
            const rowData = row.getData();
            if (rowData.is_parent_summary === true) return;
            const sku = rowData.sku;
            const sprice = parseFloat(cell.getValue()) || 0;
            if (sprice <= 0) return;
            const lp = parseFloat(rowData.amazon_lp) || 0;
            const ship = parseFloat(rowData.amazon_ship) || 0;
            const ad = parseFloat(rowData.amazon_ad) || 0;
            const margin = parseFloat(rowData.amazon_margin) || 0.80;
            const l30 = parseInt(rowData.amazon_l30, 10) || 0;
            const sgpft = sprice > 0 ? ((sprice * margin - ship - lp) / sprice) * 100 : 0;
            const spft = l30 === 0 ? sgpft : (sgpft - ad);
            const sroi = lp > 0 ? ((sprice * margin - lp - ship) / lp) * 100 : 0;
            $.ajax({
                url: '/cvr-master-save-suggested-data',
                method: 'POST',
                data: {
                    sku: sku,
                    marketplace: 'amazon',
                    sprice: sprice,
                    sgpft: Math.round(sgpft * 100) / 100,
                    spft: Math.round(spft * 100) / 100,
                    sroi: Math.round(sroi * 100) / 100,
                    amazon_margin: margin,
                    _token: '{{ csrf_token() }}'
                },
                success: function() {
                    row.update({
                        amazon_sgpft: Math.round(sgpft * 100) / 100,
                        amazon_spft: Math.round(spft * 100) / 100,
                        amazon_sroi: Math.round(sroi * 100) / 100
                    });
                    showToast('Amz SPRICE saved', 'success');
                },
                error: function() {
                    showToast('Failed to save Amz SPRICE', 'error');
                }
            });
        });

        // ==================== TABLE EVENT HANDLERS ====================
        
        $('#sku-search').on('keyup', function() {
            const value = $(this).val();
            table.setFilter("sku", "like", value);
        });
        $('#parent-search').on('keyup', function() {
            const value = $(this).val();
            table.setFilter("parent", "like", value);
        });

        $(document).on('click', '.copy-sku-btn', function(e) {
            e.stopPropagation();
            const sku = $(this).data('sku');
            navigator.clipboard.writeText(sku).then(() => {
                showToast(`Copied: ${sku}`, 'success');
            });
        });

        // ==================== SPRICE EDITING ====================
        
        // Helper: same color logic as modal table for SGPFT%, SPFT%, SROI%
        function getSgpftSpftColor(pct) {
            if (pct < 0) return '#a00211';
            if (pct >= 0 && pct < 10) return '#ffc107';
            if (pct >= 10 && pct < 20) return '#3591dc';
            if (pct >= 20 && pct <= 40) return '#28a745';
            return '#e83e8c';
        }
        function getSroiColor(pct) {
            if (pct < 0) return '#a00211';
            if (pct >= 0 && pct < 50) return '#ffc107';
            if (pct >= 50 && pct < 100) return '#3591dc';
            if (pct >= 100 && pct <= 150) return '#28a745';
            return '#e83e8c';
        }
        // Dark mustard text (no yellow background)
        const darkMustard = '#ff9c00'; // orange/mustard accent
        /** Dil%: Red <25%, Green 25–50%, Pink 50%+ */
        function getDilPercentColor(value) {
            const v = parseFloat(value) || 0;
            if (v < 25) return '#a00211';
            if (v < 50) return '#28a745';
            return '#e83e8c';
        }

        function styleForCellColor(c) {
            if (!c) return 'font-weight:600;';
            if (c === '#ffc107') return 'color:' + darkMustard + ';font-weight:600;';
            return 'color:' + c + ';font-weight:600;';
        }
        // GPFT slabs: <20 red, 20–30 yellow, 30–40 green, >40 purple text (no bg)
        function styleForGpftValue(value) {
            const v = parseFloat(value) || 0;
            if (v < 20) return styleForCellColor('#dc3545');
            if (v < 30) return styleForCellColor('#ffc107');
            if (v <= 40) return styleForCellColor('#28a745');
            return 'color:#4e0dab;font-weight:700;';
        }
        // NPFT slabs: <30 red, 30–40 yellow, 40–50 green, >50 purple text (no bg)
        function styleForNpftValue(value) {
            const v = parseFloat(value) || 0;
            if (v < 30) return styleForCellColor('#dc3545');
            if (v < 40) return styleForCellColor('#ffc107');
            if (v <= 50) return styleForCellColor('#28a745');
            return 'color:#4e0dab;font-weight:700;';
        }
        function applyCellColor($el, c) {
            if (c === '#ffc107') { $el.css({ backgroundColor: '', color: darkMustard }); }
            else { $el.css({ backgroundColor: '', color: c || '#6c757d' }); }
        }

        // Sprice modal: instant recalc when Amz SPRICE input changes
        $(document).on('input', '.sprice-modal-sprice-input', function() {
            const $modal = $('#spriceDetailsModal');
            const sprice = parseFloat($(this).val()) || 0;
            const lp = parseFloat($modal.attr('data-lp')) || 0;
            const ship = parseFloat($modal.attr('data-ship')) || 0;
            const ad = parseFloat($modal.attr('data-ad')) || 0;
            const margin = parseFloat($modal.attr('data-margin')) || 0.80;
            const l30 = parseInt($modal.attr('data-l30'), 10) || 0;
            if (sprice <= 0) {
                applyCellColor($('#spriceModalSgpft'), '#6c757d');
                $('#spriceModalSgpft').text('-');
                applyCellColor($('#spriceModalSpft'), '#6c757d');
                $('#spriceModalSpft').text('-');
                applyCellColor($('#spriceModalSroi'), '#6c757d');
                $('#spriceModalSroi').text('-');
                return;
            }
            const sgpft = ((sprice * margin - ship - lp) / sprice) * 100;
            const spft = l30 === 0 ? sgpft : (sgpft - ad);
            const sroi = lp > 0 ? ((sprice * margin - lp - ship) / lp) * 100 : 0;
            applyCellColor($('#spriceModalSgpft'), getSgpftSpftColor(sgpft));
            $('#spriceModalSgpft').text(Math.round(sgpft) + '%');
            applyCellColor($('#spriceModalSpft'), getSgpftSpftColor(spft));
            $('#spriceModalSpft').text(Math.round(spft) + '%');
            applyCellColor($('#spriceModalSroi'), getSroiColor(sroi));
            $('#spriceModalSroi').text(Math.round(sroi) + '%');
        });

        // Sprice modal: save on blur and update table row
        $(document).on('blur', '.sprice-modal-sprice-input', function() {
            const input = $(this);
            const sprice = parseFloat(input.val()) || 0;
            const $modal = $('#spriceDetailsModal');
            const sku = $modal.attr('data-sku');
            if (!sku || sprice <= 0) return;
            const lp = parseFloat($modal.attr('data-lp')) || 0;
            const ship = parseFloat($modal.attr('data-ship')) || 0;
            const ad = parseFloat($modal.attr('data-ad')) || 0;
            const margin = parseFloat($modal.attr('data-margin')) || 0.80;
            const l30 = parseInt($modal.attr('data-l30'), 10) || 0;
            const sgpft = ((sprice * margin - ship - lp) / sprice) * 100;
            const spft = l30 === 0 ? sgpft : (sgpft - ad);
            const sroi = lp > 0 ? ((sprice * margin - lp - ship) / lp) * 100 : 0;
            $.ajax({
                url: '/cvr-master-save-suggested-data',
                method: 'POST',
                data: {
                    sku: sku,
                    marketplace: 'amazon',
                    sprice: sprice,
                    sgpft: Math.round(sgpft * 100) / 100,
                    spft: Math.round(spft * 100) / 100,
                    sroi: Math.round(sroi * 100) / 100,
                    amazon_margin: margin,
                    _token: '{{ csrf_token() }}'
                },
                success: function() {
                    if (spriceModalCurrentRow) {
                        spriceModalCurrentRow.update({
                            amazon_sprice: Math.round(sprice * 100) / 100,
                            amazon_sgpft: Math.round(sgpft * 100) / 100,
                            amazon_spft: Math.round(spft * 100) / 100,
                            amazon_sroi: Math.round(sroi * 100) / 100
                        });
                    }
                    showToast('Sprice saved', 'success');
                },
                error: function() {
                    showToast('Failed to save Sprice', 'error');
                }
            });
        });

        // Real-time calculation when SPRICE changes (OVL30 modal – same formula as main table)
        $(document).on('input', '.editable-sprice', function() {
            const input = $(this);
            const row = input.closest('tr');
            const sprice = parseFloat(input.val()) || 0;
            const lp = parseFloat(row.attr('data-lp')) || 0;
            const ship = parseFloat(row.attr('data-ship')) || 0;
            const ad = parseFloat(row.attr('data-ad')) || 0;
            const tacosCh = parseFloat(row.attr('data-tacos-ch')) || 0;
            const margin = parseFloat(row.attr('data-margin')) || 0.80;
            const l30 = parseFloat(row.attr('data-l30')) || 0;
            
            const $sgpftSpan = row.find('.calculated-sgpft');
            const $spftSpan = row.find('.calculated-spft');
            const $roiSpan = row.find('.calculated-sroi');
            const $snroiSpan = row.find('.calculated-snroi');
            
            if (sprice > 0) {
                const mpLower = String(row.attr('data-marketplace') || '').toLowerCase();
                const isTemuMp = (mpLower === 'temu' || mpLower === 'temu2');
                const calcSp = isTemuMp ? (sprice <= 26.99 ? sprice + 2.99 : sprice) : sprice;
                const sgpft = ((calcSp * margin - ship - lp) / calcSp) * 100;
                const spft = (mpLower === 'tiktok') ? (sgpft - tacosCh) : (l30 == 0 ? sgpft : (sgpft - ad));
                const sroi = lp > 0 ? ((calcSp * margin - lp - ship) / lp) * 100 : 0;
                const snroi = lp > 0
                    ? (((calcSp * margin - lp - ship) - calcSp * (tacosCh / 100)) / lp) * 100
                    : 0;
                
                applyCellColor($sgpftSpan, getSgpftSpftColor(sgpft));
                $sgpftSpan.text(Math.round(sgpft) + '%');
                applyCellColor($spftSpan, getSgpftSpftColor(spft));
                $spftSpan.text(Math.round(spft) + '%');
                applyCellColor($roiSpan, getSroiColor(sroi));
                $roiSpan.text(Math.round(sroi) + '%');
                applyCellColor($snroiSpan, getSroiColor(snroi));
                $snroiSpan.text(Math.round(snroi) + '%');
            } else {
                applyCellColor($sgpftSpan, '#6c757d');
                $sgpftSpan.text('-');
                applyCellColor($spftSpan, '#6c757d');
                $spftSpan.text('-');
                applyCellColor($roiSpan, '#6c757d');
                $roiSpan.text('-');
                applyCellColor($snroiSpan, '#6c757d');
                $snroiSpan.text('-');
            }
        });
        
        // Auto-save on blur
        $(document).on('blur', '.editable-sprice', function() {
            const input = $(this);
            const row = input.closest('tr');
            const sku = row.attr('data-sku');
            const marketplace = row.attr('data-marketplace');
            const sprice = parseFloat(input.val()) || 0;
            
            if (sprice === 0) return;
            
            const lp = parseFloat(row.attr('data-lp')) || 0;
            const ship = parseFloat(row.attr('data-ship')) || 0;
            const ad = parseFloat(row.attr('data-ad')) || 0;
            const tacosCh = parseFloat(row.attr('data-tacos-ch')) || 0;
            const margin = parseFloat(row.attr('data-margin')) || 0.80;
            const l30 = parseFloat(row.attr('data-l30')) || 0;
            const mpLower = String(marketplace || '').toLowerCase();
            const isTemuMp = (mpLower === 'temu' || mpLower === 'temu2');
            const calcSp = isTemuMp ? (sprice <= 26.99 ? sprice + 2.99 : sprice) : sprice;
            
            const sgpft = sprice > 0 ? ((calcSp * margin - ship - lp) / calcSp) * 100 : 0;
            const spft = (mpLower === 'tiktok') ? (sgpft - tacosCh) : (l30 == 0 ? sgpft : (sgpft - ad));
            const sroi = lp > 0 ? ((calcSp * margin - lp - ship) / lp) * 100 : 0;
            
            input.css('border-color', '#ff9c00');
            
            $.ajax({
                url: '/cvr-master-save-suggested-data',
                method: 'POST',
                data: {
                    sku: sku,
                    marketplace: marketplace,
                    sprice: sprice,
                    sgpft: sgpft,
                    spft: spft,
                    sroi: sroi,
                    amazon_margin: margin,
                    _token: '{{ csrf_token() }}'
                },
                success: function() {
                    input.css('border-color', '#28a745');
                    setTimeout(() => input.css('border-color', ''), 1000);
                    showToast('Saved!', 'success');
                },
                error: function() {
                    input.css('border-color', '#dc3545');
                    showToast('Failed to save', 'error');
                }
            });
        });

        // ==================== DETAILS MODAL PRC MODE (Increase / Decrease / Same) ====================
        function roundModalRetailPrice(price) {
            if (!(price > 0)) return 0.01;
            if (price < 20.99) return +Number(price).toFixed(2);
            return +(Math.ceil(price) - 0.01).toFixed(2);
        }
        function roundModalRetailPrice49(price) {
            if (!(price > 0)) return 0.01;
            if (price < 20.99) return +Number(price).toFixed(2);
            return +(Math.ceil(price) - 0.51).toFixed(2);
        }
        function computeModalModePrice(basePrice, mode, inputValue, discountType) {
            let newPrice;
            if (mode === 'same') {
                newPrice = Math.max(0.01, inputValue);
            } else if (discountType === 'percentage') {
                const decimal = inputValue / 100;
                newPrice = mode === 'decrease' ? basePrice * (1 - decimal) : basePrice * (1 + decimal);
            } else {
                newPrice = mode === 'decrease'
                    ? Math.max(0.01, basePrice - inputValue)
                    : basePrice + inputValue;
            }
            newPrice = roundModalRetailPrice(newPrice);
            if (mode !== 'same' && newPrice.toFixed(2) === Number(basePrice).toFixed(2)) {
                newPrice = roundModalRetailPrice49(newPrice);
            }
            return Math.max(0.01, parseFloat(newPrice.toFixed(2)));
        }

        function saveModalSpriceForRow($row, sprice, done) {
            const sku = $row.attr('data-sku');
            const marketplace = $row.attr('data-marketplace');
            const lp = parseFloat($row.attr('data-lp')) || 0;
            const ship = parseFloat($row.attr('data-ship')) || 0;
            const ad = parseFloat($row.attr('data-ad')) || 0;
            const tacosCh = parseFloat($row.attr('data-tacos-ch')) || 0;
            const margin = parseFloat($row.attr('data-margin')) || 0.80;
            const l30 = parseFloat($row.attr('data-l30')) || 0;
            const mpLower = String(marketplace || '').toLowerCase();
            const isTemuMp = (mpLower === 'temu' || mpLower === 'temu2');
            const calcSp = isTemuMp ? (sprice <= 26.99 ? sprice + 2.99 : sprice) : sprice;
            const sgpft = sprice > 0 ? ((calcSp * margin - ship - lp) / calcSp) * 100 : 0;
            const spft = (mpLower === 'tiktok') ? (sgpft - tacosCh) : (l30 == 0 ? sgpft : (sgpft - ad));
            const sroi = lp > 0 ? ((calcSp * margin - lp - ship) / lp) * 100 : 0;

            $.ajax({
                url: '/cvr-master-save-suggested-data',
                method: 'POST',
                data: {
                    sku: sku,
                    marketplace: marketplace,
                    sprice: sprice,
                    sgpft: sgpft,
                    spft: spft,
                    sroi: sroi,
                    amazon_margin: margin,
                    _token: '{{ csrf_token() }}'
                },
                success: function() { if (done) done(true); },
                error: function() { if (done) done(false); }
            });
        }

        $(document).on('click', '#modal-price-pct-dropdown a[data-mode]', function(e) {
            e.preventDefault();
            e.stopPropagation();
            setModalPricePctMode($(this).data('mode'));
        });

        $(document).on('change', '.ovl30-prc-row-cb', function() {
            const mp = String($(this).attr('data-marketplace') || '');
            if ($(this).is(':checked')) modalSelectedChannels.add(mp);
            else modalSelectedChannels.delete(mp);
            updateModalPrcSelectedCount();
        });

        $(document).on('click', '#modal-select-all-channels-btn', function(e) {
            e.preventDefault();
            $('#ovl30DetailsTableBody .ovl30-prc-row-cb').each(function() {
                const mp = String($(this).attr('data-marketplace') || '');
                $(this).prop('checked', true);
                if (mp) modalSelectedChannels.add(mp);
            });
            updateModalPrcSelectedCount();
        });

        $(document).on('change', '#modal-discount-type-select', function() {
            $('#modal-discount-percentage-input').attr(
                'placeholder',
                $(this).val() === 'percentage' ? 'Enter percentage' : 'Enter value ($)'
            );
        });

        $('#ovl30DetailsModal').on('hidden.bs.modal', function() {
            exitModalPricePctMode(false);
        });

        $(document).on('click', '#modal-apply-discount-btn', function() {
            if (!modalPrcModeActive) {
                showToast('Choose Decrease, Increase, or Same Price from Prc Mode first', 'error');
                return;
            }
            const rawInput = $('#modal-discount-percentage-input').val();
            const inputValue = parseFloat(String(rawInput == null ? '' : rawInput).replace(/[$,\s]/g, '').replace(',', '.'));
            if (rawInput === '' || rawInput == null || isNaN(inputValue) || inputValue < 0) {
                showToast(modalSamePriceModeActive ? 'Please enter a price' : 'Please enter a valid value (% or $)', 'error');
                $('#modal-discount-percentage-input').focus();
                return;
            }
            const discountType = $('#modal-discount-type-select').val() || 'percentage';
            if (!modalSamePriceModeActive && discountType === 'percentage' && inputValue > 100) {
                showToast('Percentage cannot exceed 100', 'error');
                return;
            }
            if (modalSelectedChannels.size === 0) {
                showToast('Please select at least one channel (checkbox next to SPRICE)', 'error');
                return;
            }

            const mode = modalSamePriceModeActive ? 'same' : (modalIncreaseModeActive ? 'increase' : 'decrease');
            const $rows = [];
            $('#ovl30DetailsTableBody tr').each(function() {
                const $tr = $(this);
                const mp = String($tr.attr('data-marketplace') || '');
                if (!modalSelectedChannels.has(mp)) return;
                if (!$tr.find('.editable-sprice').length) return;
                $rows.push($tr);
            });
            if (!$rows.length) {
                showToast('No editable SPRICE rows selected', 'error');
                return;
            }

            const actionLabel = mode === 'same' ? 'Same Price' : (mode === 'increase' ? 'Increase' : 'Decrease');
            if (!confirm(actionLabel + ' SPRICE for ' + $rows.length + ' channel(s)?')) return;

            const $btn = $(this);
            const origHtml = $btn.html();
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Applying...');

            let doneCount = 0;
            let okCount = 0;
            $rows.forEach(function($tr) {
                const basePrice = parseFloat($tr.attr('data-price')) || 0;
                const $input = $tr.find('.editable-sprice');
                if (mode !== 'same' && !(basePrice > 0)) {
                    doneCount++;
                    if (doneCount === $rows.length) {
                        $btn.prop('disabled', false).html(origHtml);
                        showToast(okCount ? ('Updated ' + okCount + ' channel(s); some had no Price') : 'Selected channels have no Price', okCount ? 'success' : 'error');
                    }
                    return;
                }
                const newPrice = computeModalModePrice(basePrice, mode, inputValue, discountType);
                $input.val(newPrice.toFixed(2)).trigger('input');
                saveModalSpriceForRow($tr, newPrice, function(ok) {
                    if (ok) okCount++;
                    doneCount++;
                    if (doneCount === $rows.length) {
                        $btn.prop('disabled', false).html(origHtml);
                        showToast(okCount
                            ? (actionLabel + ' applied to ' + okCount + ' channel(s). Use Push to go live.')
                            : 'Failed to save SPRICE', okCount ? 'success' : 'error');
                        $('#modal-discount-percentage-input').val('');
                    }
                });
            });
        });

        $(document).on('keydown', '#modal-discount-percentage-input', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                $('#modal-apply-discount-btn').trigger('click');
            }
        });
        
        // ==================== AUTO FILL SPRICE (Dil / CVR / LMP / Price) ====================

        const SPRICE_RULES_STORAGE_KEY = 'cvr_master_sprice_suggest_rules_v1';
        const SPRICE_RULES_DEFAULT = {
            above_lmp_mult: 0.98,
            strong_dil_min: 30,
            strong_cvr_min: 3,
            strong_lmp_cap: 0.99,
            strong_raise_pct: 5,
            strong_max_raise_pct: 8,
            weak_cvr_max: 3,
            volume_lmp_mult: 0.97,
            slow_dil_max: 10,
            slow_weak_lmp_mult: 0.95,
            slow_weak_price_mult: 0.95,
            slow_strong_cvr_min: 5,
            slow_strong_raise_pct: 3,
            slow_strong_lmp_cap: 0.98,
            balanced_lmp_cap: 0.98,
            no_lmp_raise_pct: 5,
            no_lmp_cut_pct: 5,
            gpft_floor_pct: 20
        };

        function getDefaultSpriceRules() {
            return Object.assign({}, SPRICE_RULES_DEFAULT);
        }

        function loadSpriceRules() {
            try {
                const raw = localStorage.getItem(SPRICE_RULES_STORAGE_KEY);
                if (!raw) return getDefaultSpriceRules();
                const parsed = JSON.parse(raw);
                return Object.assign(getDefaultSpriceRules(), parsed || {});
            } catch (e) {
                return getDefaultSpriceRules();
            }
        }

        function saveSpriceRules(rules) {
            try {
                localStorage.setItem(SPRICE_RULES_STORAGE_KEY, JSON.stringify(rules));
            } catch (e) { /* ignore */ }
        }

        let ovl30SpriceRules = loadSpriceRules();

        function numRule(v, fallback) {
            const n = parseFloat(v);
            return isFinite(n) ? n : fallback;
        }

        function readSpriceRulesFromEditor() {
            const $ed = $('#spriceSuggestRulesEditor');
            if (!$ed.length) return ovl30SpriceRules;
            const d = SPRICE_RULES_DEFAULT;
            const r = {
                above_lmp_mult: numRule($ed.find('[name="above_lmp_mult"]').val(), d.above_lmp_mult),
                strong_dil_min: numRule($ed.find('[name="strong_dil_min"]').val(), d.strong_dil_min),
                strong_cvr_min: numRule($ed.find('[name="strong_cvr_min"]').val(), d.strong_cvr_min),
                strong_lmp_cap: numRule($ed.find('[name="strong_lmp_cap"]').val(), d.strong_lmp_cap),
                strong_raise_pct: numRule($ed.find('[name="strong_raise_pct"]').val(), d.strong_raise_pct),
                strong_max_raise_pct: numRule($ed.find('[name="strong_max_raise_pct"]').val(), d.strong_max_raise_pct),
                weak_cvr_max: numRule($ed.find('[name="weak_cvr_max"]').val(), d.weak_cvr_max),
                volume_lmp_mult: numRule($ed.find('[name="volume_lmp_mult"]').val(), d.volume_lmp_mult),
                slow_dil_max: numRule($ed.find('[name="slow_dil_max"]').val(), d.slow_dil_max),
                slow_weak_lmp_mult: numRule($ed.find('[name="slow_weak_lmp_mult"]').val(), d.slow_weak_lmp_mult),
                slow_weak_price_mult: numRule($ed.find('[name="slow_weak_price_mult"]').val(), d.slow_weak_price_mult),
                slow_strong_cvr_min: numRule($ed.find('[name="slow_strong_cvr_min"]').val(), d.slow_strong_cvr_min),
                slow_strong_raise_pct: numRule($ed.find('[name="slow_strong_raise_pct"]').val(), d.slow_strong_raise_pct),
                slow_strong_lmp_cap: numRule($ed.find('[name="slow_strong_lmp_cap"]').val(), d.slow_strong_lmp_cap),
                balanced_lmp_cap: numRule($ed.find('[name="balanced_lmp_cap"]').val(), d.balanced_lmp_cap),
                no_lmp_raise_pct: numRule($ed.find('[name="no_lmp_raise_pct"]').val(), d.no_lmp_raise_pct),
                no_lmp_cut_pct: numRule($ed.find('[name="no_lmp_cut_pct"]').val(), d.no_lmp_cut_pct),
                gpft_floor_pct: numRule($ed.find('[name="gpft_floor_pct"]').val(), d.gpft_floor_pct)
            };
            ovl30SpriceRules = r;
            saveSpriceRules(r);
            return r;
        }

        function ruleField(name, label, value, step) {
            return '<div class="sprice-rule-field">'
                + '<label>' + label + '</label>'
                + '<input type="number" class="form-control form-control-sm sprice-rule-input" name="' + name + '" '
                + 'value="' + value + '" step="' + (step || '0.01') + '">'
                + '</div>';
        }

        function renderSpriceRulesEditor(rules) {
            const r = rules || ovl30SpriceRules;
            const html = ''
                + '<div class="sprice-rule-card">'
                + '<h6>above lmp</h6>'
                + '<div class="rule-desc">Price &gt; LMP → SPRICE = LMP × multiplier</div>'
                + '<div class="sprice-rule-grid">'
                + ruleField('above_lmp_mult', 'LMP multiplier', r.above_lmp_mult, '0.01')
                + '</div></div>'

                + '<div class="sprice-rule-card">'
                + '<h6>strong demand</h6>'
                + '<div class="rule-desc">Dil ≥ min &amp; CVR ≥ min → raise under LMP cap (max raise %)</div>'
                + '<div class="sprice-rule-grid">'
                + ruleField('strong_dil_min', 'Dil% min', r.strong_dil_min, '1')
                + ruleField('strong_cvr_min', 'CVR% min', r.strong_cvr_min, '0.1')
                + ruleField('strong_lmp_cap', 'LMP cap mult', r.strong_lmp_cap, '0.01')
                + ruleField('strong_raise_pct', 'Raise %', r.strong_raise_pct, '0.5')
                + ruleField('strong_max_raise_pct', 'Max raise %', r.strong_max_raise_pct, '0.5')
                + '</div></div>'

                + '<div class="sprice-rule-card">'
                + '<h6>volume weak cvr</h6>'
                + '<div class="rule-desc">Dil ≥ strong Dil min &amp; CVR &lt; weak max → min(Price, LMP × mult)</div>'
                + '<div class="sprice-rule-grid">'
                + ruleField('weak_cvr_max', 'CVR% max', r.weak_cvr_max, '0.1')
                + ruleField('volume_lmp_mult', 'LMP multiplier', r.volume_lmp_mult, '0.01')
                + '</div></div>'

                + '<div class="sprice-rule-card">'
                + '<h6>slow weak</h6>'
                + '<div class="rule-desc">Dil &lt; max &amp; CVR &lt; weak max → LMP × mult</div>'
                + '<div class="sprice-rule-grid">'
                + ruleField('slow_dil_max', 'Dil% max', r.slow_dil_max, '1')
                + ruleField('slow_weak_lmp_mult', 'LMP multiplier', r.slow_weak_lmp_mult, '0.01')
                + '</div></div>'

                + '<div class="sprice-rule-card">'
                + '<h6>slow strong cvr</h6>'
                + '<div class="rule-desc">Dil &lt; slow Dil max &amp; CVR ≥ min → raise %, capped at LMP × mult</div>'
                + '<div class="sprice-rule-grid">'
                + ruleField('slow_strong_cvr_min', 'CVR% min', r.slow_strong_cvr_min, '0.1')
                + ruleField('slow_strong_raise_pct', 'Raise %', r.slow_strong_raise_pct, '0.5')
                + ruleField('slow_strong_lmp_cap', 'LMP cap mult', r.slow_strong_lmp_cap, '0.01')
                + '</div></div>'

                + '<div class="sprice-rule-card">'
                + '<h6>balanced</h6>'
                + '<div class="rule-desc">Mid Dil → keep Price, never above LMP × cap</div>'
                + '<div class="sprice-rule-grid">'
                + ruleField('balanced_lmp_cap', 'LMP cap mult', r.balanced_lmp_cap, '0.01')
                + '</div></div>'

                + '<div class="sprice-rule-card">'
                + '<h6>no lmp + floor</h6>'
                + '<div class="rule-desc">No LMP → Dil/CVR ±% on Price; GPFT floor when LP known</div>'
                + '<div class="sprice-rule-grid">'
                + ruleField('no_lmp_raise_pct', 'Raise % (strong)', r.no_lmp_raise_pct, '0.5')
                + ruleField('no_lmp_cut_pct', 'Cut % (weak)', r.no_lmp_cut_pct, '0.5')
                + ruleField('gpft_floor_pct', 'GPFT floor %', r.gpft_floor_pct, '1')
                + '</div></div>';

            $('#spriceSuggestRulesEditor').html(html);
        }

        function gpftFloorSprice(lp, ship, margin, targetGpft) {
            const denom = margin - (targetGpft / 100);
            if (!(lp > 0) || !(denom > 0)) return 0;
            return (lp + ship) / denom;
        }

        function roundMoney(v) {
            return Math.round((v + Number.EPSILON) * 100) / 100;
        }

        /** Suggest SPRICE using editable rule params. */
        function suggestSpriceForChannel(ctx, rules) {
            const R = rules || ovl30SpriceRules;
            const price = parseFloat(ctx.price) || 0;
            const lmp = parseFloat(ctx.lmp) || 0;
            const dil = parseFloat(ctx.dil) || 0;
            const cvr = parseFloat(ctx.cvr) || 0;
            const lp = parseFloat(ctx.lp) || 0;
            const ship = parseFloat(ctx.ship) || 0;
            const margin = parseFloat(ctx.margin) || 0.80;
            const floor = gpftFloorSprice(lp, ship, margin, R.gpft_floor_pct);

            if (!(price > 0) && !(lmp > 0)) {
                return { sprice: 0, ruleId: 'skip', rule: 'No Price / LMP', skip: true };
            }

            let suggested = price > 0 ? price : lmp;
            let ruleId = 'balanced';
            let rule = 'Hold current price';

            if (lmp > 0 && price > lmp) {
                suggested = lmp * R.above_lmp_mult;
                ruleId = 'above_lmp';
                rule = 'Above LMP → LMP×' + R.above_lmp_mult;
            } else if (lmp > 0) {
                const cap = lmp * R.strong_lmp_cap;
                const softCap = lmp * R.balanced_lmp_cap;
                if (dil >= R.strong_dil_min && cvr >= R.strong_cvr_min) {
                    const raiseMult = 1 + (R.strong_raise_pct / 100);
                    const maxRaiseMult = 1 + (R.strong_max_raise_pct / 100);
                    suggested = Math.min(cap, Math.max(price, price * raiseMult, floor || 0));
                    if (suggested > price * maxRaiseMult) suggested = Math.min(cap, price * maxRaiseMult);
                    ruleId = 'strong_demand';
                    rule = 'Strong Dil+CVR → raise under LMP×' + R.strong_lmp_cap;
                } else if (dil >= R.strong_dil_min && cvr < R.weak_cvr_max) {
                    suggested = Math.min(price, lmp * R.volume_lmp_mult);
                    ruleId = 'volume_weak_cvr';
                    rule = 'High Dil, weak CVR → LMP×' + R.volume_lmp_mult;
                } else if (dil < R.slow_dil_max && cvr < R.weak_cvr_max) {
                    suggested = lmp * R.slow_weak_lmp_mult;
                    ruleId = 'slow_weak';
                    rule = 'Slow + weak CVR → LMP×' + R.slow_weak_lmp_mult;
                } else if (dil < R.slow_dil_max && cvr >= R.slow_strong_cvr_min) {
                    const raiseMult = 1 + (R.slow_strong_raise_pct / 100);
                    const slowCap = lmp * R.slow_strong_lmp_cap;
                    suggested = Math.min(slowCap, price * raiseMult);
                    ruleId = 'slow_strong_cvr';
                    rule = 'Slow but converting → +' + R.slow_strong_raise_pct + '% under LMP';
                } else {
                    suggested = Math.min(softCap, Math.max(price, floor || price));
                    ruleId = 'balanced';
                    rule = 'Balanced → under LMP×' + R.balanced_lmp_cap;
                }
                if (suggested > softCap && ruleId !== 'strong_demand') suggested = softCap;
                if (suggested > cap) suggested = cap;
            } else {
                if (dil >= R.strong_dil_min && cvr >= R.strong_cvr_min) {
                    suggested = price * (1 + R.no_lmp_raise_pct / 100);
                    ruleId = 'no_lmp';
                    rule = 'No LMP, strong → +' + R.no_lmp_raise_pct + '%';
                } else if (dil < R.slow_dil_max && cvr < R.weak_cvr_max) {
                    suggested = price * (1 - R.no_lmp_cut_pct / 100);
                    ruleId = 'no_lmp';
                    rule = 'No LMP, weak → −' + R.no_lmp_cut_pct + '%';
                } else {
                    suggested = price;
                    ruleId = 'no_lmp';
                    rule = 'No LMP → keep Price';
                }
            }

            const floorCap = lmp > 0 ? lmp * R.strong_lmp_cap : Infinity;
            if (floor > 0 && suggested < floor && floor <= floorCap) {
                suggested = Math.max(suggested, floor);
                rule += ' (GPFT≥' + R.gpft_floor_pct + '% floor)';
            }

            suggested = roundMoney(Math.max(0, suggested));
            if (!(suggested > 0)) {
                return { sprice: 0, ruleId: 'skip', rule: 'Could not compute', skip: true };
            }
            return { sprice: suggested, ruleId, rule, skip: false };
        }

        function buildOvl30SpriceSuggestions(rules) {
            const R = rules || ovl30SpriceRules;
            const dil = ovl30ModalDil;
            const rows = [];
            let cvrSum = 0, cvrN = 0;

            (ovl30ModalData || []).forEach(item => {
                const isListed = item.is_listed !== false;
                const price = parseFloat(item.price || 0);
                if (!isListed || !(price > 0)) return;

                const views = item.views == null ? null : parseFloat(item.views);
                const l30 = parseInt(item.l30 || 0);
                const cvr = (views != null && views > 0) ? (l30 / views) * 100 : 0;
                if (views != null && views > 0) { cvrSum += cvr; cvrN++; }

                const mp = (item.marketplace || '').toLowerCase();
                const editableChannels = [
                    'amazon', 'doba', 'walmart',
                    'ebay', 'ebay1', 'ebay2', 'ebaytwo', 'ebay3', 'ebaythree',
                    'temu', 'temu2', 'tiktok', 'bestbuy', 'bestbuyusa', 'macy', 'macys',
                    'reverb', 'tiendamia', 'sb2c', 'shopify', 'shopifyb2c', 'sb2b', 'shopifyb2b',
                    'fba', 'shein', 'aliexpress', 'ppower', 'purchasingpower'
                ];
                const editable = editableChannels.includes(mp);

                const result = suggestSpriceForChannel({
                    price,
                    lmp: parseFloat(item.lmp_price) || 0,
                    dil,
                    cvr,
                    lp: parseFloat(item.lp) || 0,
                    ship: parseFloat(item.ship) || 0,
                    margin: parseFloat(item.margin) || 0.80
                }, R);

                rows.push({
                    marketplace: item.marketplace,
                    sku: item.sku,
                    price,
                    lmp: parseFloat(item.lmp_price) || 0,
                    cvr,
                    viewsMissing: views == null,
                    currentSprice: parseFloat(item.sprice) || 0,
                    suggested: result.sprice,
                    rule: result.rule,
                    ruleId: result.ruleId,
                    skip: result.skip || !editable,
                    editable,
                    selected: editable && !result.skip && result.sprice > 0
                });
            });

            return {
                dil,
                avgCvr: cvrN > 0 ? cvrSum / cvrN : 0,
                rows
            };
        }

        function refreshSpriceSuggestPreview(keepEditor) {
            const rules = keepEditor ? readSpriceRulesFromEditor() : ovl30SpriceRules;
            if (!keepEditor) {
                renderSpriceRulesEditor(rules);
            }
            const built = buildOvl30SpriceSuggestions(rules);
            ovl30SpriceSuggestions = built.rows;

            $('#spriceSuggestSku').text($('#modalSkuName').text() || '-');
            $('#spriceSuggestDil').text(Math.round(built.dil) + '%');
            $('#spriceSuggestAvgCvr').text(built.avgCvr > 0 ? built.avgCvr.toFixed(1) + '%' : 'N/A');

            let html = '';
            built.rows.forEach((r, idx) => {
                const cvrTxt = r.viewsMissing ? 'N/A' : (r.cvr > 0 ? r.cvr.toFixed(1) + '%' : '-');
                const disabled = r.skip || !r.editable;
                const delta = r.suggested - (r.currentSprice || r.price);
                const deltaCls = delta > 0.01 ? 'text-success' : (delta < -0.01 ? 'text-danger' : 'text-muted');
                html += '<tr data-idx="' + idx + '">'
                    + '<td><strong>' + (r.marketplace || '-') + '</strong></td>'
                    + '<td class="text-end">$' + r.price.toFixed(2) + '</td>'
                    + '<td class="text-end">' + (r.lmp > 0 ? '$' + r.lmp.toFixed(2) : '-') + '</td>'
                    + '<td class="text-end">' + cvrTxt + '</td>'
                    + '<td class="text-end">' + (r.currentSprice > 0 ? '$' + r.currentSprice.toFixed(2) : '-') + '</td>'
                    + '<td class="text-end ' + deltaCls + '"><strong>$' + (r.suggested > 0 ? r.suggested.toFixed(2) : '-') + '</strong></td>'
                    + '<td class="small">' + (disabled && !r.editable ? 'Not editable' : r.rule) + '</td>'
                    + '<td class="text-center"><input type="checkbox" class="sprice-suggest-row-cb" data-idx="' + idx + '" '
                    + (r.selected ? 'checked' : '') + (disabled ? ' disabled' : '') + '></td>'
                    + '</tr>';
            });
            if (!html) {
                html = '<tr><td colspan="8" class="text-center text-muted py-3">No listed channels with price to suggest.</td></tr>';
            }
            $('#spriceSuggestPreviewBody').html(html);
            $('#spriceSuggestSelectAll').prop('checked', built.rows.some(r => r.selected));
            $('#spriceSuggestStatus').text(built.rows.filter(r => r.selected).length + ' channel(s) ready to apply.');
        }

        function renderSpriceSuggestModal() {
            ovl30SpriceRules = loadSpriceRules();
            refreshSpriceSuggestPreview(false);
        }

        $(document).on('click', '.ovl30-sprice-suggest-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (!ovl30ModalData.length) {
                showToast('Load Details data first', 'error');
                return;
            }
            renderSpriceSuggestModal();
            const el = document.getElementById('spriceSuggestModal');
            bootstrap.Modal.getOrCreateInstance(el).show();
        });

        $(document).on('click', '#spriceSuggestRecalcBtn', function(e) {
            e.preventDefault();
            refreshSpriceSuggestPreview(true);
            showToast('Preview updated from your rules', 'success');
        });

        $(document).on('click', '#spriceSuggestResetRulesBtn', function(e) {
            e.preventDefault();
            ovl30SpriceRules = getDefaultSpriceRules();
            saveSpriceRules(ovl30SpriceRules);
            refreshSpriceSuggestPreview(false);
            showToast('Rules reset to defaults', 'info');
        });

        let spriceRuleRecalcTimer = null;
        $(document).on('input change', '#spriceSuggestRulesEditor .sprice-rule-input', function() {
            clearTimeout(spriceRuleRecalcTimer);
            spriceRuleRecalcTimer = setTimeout(function() {
                refreshSpriceSuggestPreview(true);
            }, 250);
        });

        $(document).on('change', '#spriceSuggestSelectAll', function() {
            const on = $(this).is(':checked');
            $('#spriceSuggestPreviewBody .sprice-suggest-row-cb:not(:disabled)').prop('checked', on);
        });

        $('#spriceSuggestApplyBtn').on('click', function() {
            const btn = $(this);
            // Ensure preview matches latest edited rules
            const checkedIdx = [];
            $('#spriceSuggestPreviewBody .sprice-suggest-row-cb:checked').each(function() {
                checkedIdx.push(parseInt($(this).data('idx'), 10));
            });
            refreshSpriceSuggestPreview(true);
            const selected = [];
            ovl30SpriceSuggestions.forEach((row, idx) => {
                if (checkedIdx.indexOf(idx) >= 0 && row && row.suggested > 0 && row.editable) {
                    selected.push(row);
                }
            });
            // Re-check boxes that were selected
            $('#spriceSuggestPreviewBody .sprice-suggest-row-cb').each(function() {
                const idx = parseInt($(this).data('idx'), 10);
                if (checkedIdx.indexOf(idx) >= 0 && !$(this).prop('disabled')) {
                    $(this).prop('checked', true);
                }
            });
            if (!selected.length) {
                showToast('Select at least one channel', 'error');
                return;
            }

            btn.prop('disabled', true);
            let done = 0, failed = 0;
            const total = selected.length;

            const finishOne = () => {
                done++;
                if (done >= total) {
                    btn.prop('disabled', false);
                    $('#spriceSuggestStatus').text('Applied ' + (total - failed) + '/' + total + (failed ? (' (' + failed + ' failed)') : ''));
                    showToast('SPRICE filled for ' + (total - failed) + ' channel(s)', failed ? 'error' : 'success');
                    const modalEl = document.getElementById('spriceSuggestModal');
                    bootstrap.Modal.getInstance(modalEl)?.hide();
                    scheduleAutoFitOvl30TableFont();
                }
            };

            selected.forEach(s => {
                // Update in-memory breakdown
                const item = ovl30ModalData.find(x => (x.marketplace || '') === s.marketplace);
                if (item) item.sprice = s.suggested;

                const $tr = $('#ovl30DetailsTableBody tr').filter(function() {
                    return $(this).attr('data-marketplace') === s.marketplace;
                });
                const $input = $tr.find('.editable-sprice');
                if ($input.length) {
                    $input.val(s.suggested.toFixed(2)).trigger('input');
                }

                const lp = parseFloat($tr.attr('data-lp')) || (item ? parseFloat(item.lp) || 0 : 0);
                const ship = parseFloat($tr.attr('data-ship')) || (item ? parseFloat(item.ship) || 0 : 0);
                const ad = parseFloat($tr.attr('data-ad')) || (item ? parseFloat(item.ad) || 0 : 0);
                const margin = parseFloat($tr.attr('data-margin')) || (item ? parseFloat(item.margin) || 0.80 : 0.80);
                const l30 = parseFloat($tr.attr('data-l30')) || (item ? parseInt(item.l30) || 0 : 0);
                const sprice = s.suggested;
                const sgpft = sprice > 0 ? ((sprice * margin - ship - lp) / sprice) * 100 : 0;
                const spft = l30 == 0 ? sgpft : (sgpft - ad);
                const sroi = lp > 0 ? ((sprice * margin - lp - ship) / lp) * 100 : 0;

                $.ajax({
                    url: '/cvr-master-save-suggested-data',
                    method: 'POST',
                    data: {
                        sku: s.sku,
                        marketplace: s.marketplace,
                        sprice: sprice,
                        sgpft: sgpft,
                        spft: spft,
                        sroi: sroi,
                        amazon_margin: margin,
                        _token: '{{ csrf_token() }}'
                    },
                    success: function() {
                        if ($input.length) {
                            $input.css('border-color', '#28a745');
                            setTimeout(() => $input.css('border-color', ''), 1000);
                        }
                        finishOne();
                    },
                    error: function() {
                        failed++;
                        if ($input.length) $input.css('border-color', '#dc3545');
                        finishOne();
                    }
                });
            });
        });

        // ==================== PRICE PUSH TO AMAZON ====================
        
        // Push price button click handler
        $(document).on('click', '.push-price-btn', function(e) {
            e.stopPropagation();
            const btn = $(this);
            const row = btn.closest('tr');
            const sku = btn.data('sku');
            const marketplace = btn.data('marketplace');
            const priceInput = row.find('.editable-sprice');
            const price = parseFloat(priceInput.val()) || 0;
            
            if (price <= 0) {
                showToast('Please enter a valid price greater than 0', 'error');
                priceInput.focus();
                return;
            }
            
            // Confirm before pushing
            if (!confirm(`Push price $${price.toFixed(2)} to ${marketplace.toUpperCase()} for SKU: ${sku}?`)) {
                return;
            }
            
            // Disable button and show loading state
            const originalHtml = btn.html();
            btn.prop('disabled', true);
            btn.html('<i class="fas fa-spinner fa-spin"></i>');
            
            $.ajax({
                url: '/cvr-master-push-price',
                method: 'POST',
                data: {
                    sku: sku,
                    price: price,
                    marketplace: marketplace,
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.success) {
                        showToast(response.message, 'success');
                        btn.html('<i class="fas fa-check"></i>');
                        btn.removeClass('btn-primary').addClass('btn-success');
                        
                        // Reload modal data to show pushed_by info
                        setTimeout(() => {
                            const currentSku = $('#modalSkuName').text();
                            const currentImage = $('#modal-product-image').attr('src');
                            const currentInv = $('#modal-header-inv').text().replace(/,/g, '');
                            const currentL30 = $('#modal-header-l30').text().replace(/,/g, '');
                            const currentDil = parseFloat($('#modal-header-dil').text());
                            loadMarketplaceBreakdown(currentSku, currentImage, currentInv, currentL30, currentDil);
                        }, 1500);
                    } else {
                        showToast(response.message || 'Failed to push price', 'error');
                        btn.html(originalHtml);
                        btn.prop('disabled', false);
                    }
                },
                error: function(xhr) {
                    console.error('Price push failed:', {
                        sku: sku,
                        marketplace: marketplace,
                        status: xhr.status,
                        error: xhr.responseJSON
                    });
                    
                    let errorMsg = 'Failed to push price';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    showToast(errorMsg, 'error');
                    btn.html(originalHtml);
                    btn.prop('disabled', false);
                }
            });
        });

        // ==================== LMP COMPETITORS MODAL ====================

        let lmpModalCache = { sku: '', rows: [], filter: 'all', showAdd: false };
        // draftPrice/draftLink hold typed values so re-renders don't wipe direct input
        let lmpEditState = null; // { id, channel, extId, origPrice, origLink, draftPrice, draftLink, draftExtId }

        function resetLmpEditState() {
            lmpEditState = null;
        }

        function parseLmpPriceInput(raw) {
            if (raw == null) return NaN;
            const cleaned = String(raw).replace(/[$,\s]/g, '').trim();
            if (cleaned === '') return NaN;
            return parseFloat(cleaned);
        }

        function syncLmpEditDraftFromForm() {
            if (!lmpEditState) return;
            lmpEditState.draftPrice = $('#lmpAddPrice').val();
            lmpEditState.draftLink = $('#lmpAddLink').val();
            if ($('#lmpAddId').length) {
                lmpEditState.draftExtId = $('#lmpAddId').val();
            }
        }

        function applyLmpEditFormState() {
            const $form = $('#lmpAddForm');
            if (!$form.length) return;
            const isEdit = !!lmpEditState;
            const ch = isEdit
                ? lmpEditState.channel
                : (($('#lmpAddChannel').val() || lmpModalCache.filter || 'amazon').toLowerCase());
            const labelMap = { amazon: 'Amazon', ebay: 'eBay', google: 'Google', temu: 'Temu' };
            const label = labelMap[ch] || ch;
            const $box = $form.closest('.lmp-add-form-box');
            $box.find('strong').html(
                isEdit
                    ? '<i class="fas fa-edit text-warning me-1"></i>Edit ' + label + ' LMP'
                    : '<i class="fas fa-plus-circle text-success me-1"></i>Add ' + label + ' LMP'
            );
            $('#lmpEditId').val(isEdit ? String(lmpEditState.id) : '');
            $('#lmpFormChannel').val(ch);
            $('#lmpAddChannel').val(ch);
            if (isEdit) {
                $('#lmpAddChannel').prop('disabled', true);
                // Prefer draft (what user typed) over original row values
                const priceVal = lmpEditState.draftPrice != null && lmpEditState.draftPrice !== ''
                    ? lmpEditState.draftPrice
                    : (lmpEditState.origPrice != null ? Number(lmpEditState.origPrice).toFixed(2) : '');
                const linkVal = lmpEditState.draftLink != null
                    ? lmpEditState.draftLink
                    : (lmpEditState.origLink || '');
                const extVal = lmpEditState.draftExtId != null
                    ? lmpEditState.draftExtId
                    : (lmpEditState.extId || '');
                $('#lmpAddPrice').val(priceVal);
                $('#lmpAddLink').val(linkVal);
                if ($('#lmpAddId').length) {
                    $('#lmpAddId').val(extVal);
                }
                $('#lmpAddSubmitBtn').removeClass('btn-success').addClass('btn-warning')
                    .html('<i class="fas fa-save me-1"></i>Update');
                $('#lmpCancelEditBtn').removeClass('d-none');
            } else {
                $('#lmpAddChannel').prop('disabled', false);
                $('#lmpAddSubmitBtn').removeClass('btn-warning').addClass('btn-success')
                    .html('<i class="fas fa-plus me-1"></i>Add');
                $('#lmpCancelEditBtn').addClass('d-none');
            }
        }

        function enterLmpEditMode(row) {
            if (!row || !row.id) return;
            const priceStr = row.price != null && row.price !== '' ? String(row.price) : '';
            lmpEditState = {
                id: row.id,
                channel: row.channel,
                extId: row.extId || '',
                origPrice: row.price,
                origLink: row.link || '',
                draftPrice: priceStr,
                draftLink: row.link || '',
                draftExtId: row.extId || '',
            };
            lmpModalCache.filter = row.channel;
            lmpModalCache.showAdd = true;
            $('#lmpModal').data('lmp-filter', row.channel);
            renderLmpMergedTable();
            setTimeout(function() {
                const el = document.getElementById('lmpAddPrice');
                if (el) {
                    el.focus();
                    el.select();
                }
            }, 0);
        }

        function loadLmpCompetitorsModal(sku, marketplace, showAddForm) {
            resetLmpEditState();
            $('#lmpSku').text(sku);
            const initialFilter = (marketplace === 'amazon' || marketplace === 'ebay' || marketplace === 'google' || marketplace === 'temu')
                ? marketplace
                : 'all';
            const showAdd = !!showAddForm || initialFilter !== 'all';
            $('#lmpModal').data('lmp-marketplace', marketplace || null);
            $('#lmpModal').data('lmp-filter', initialFilter);
            $('#lmpModal').data('lmp-show-add', showAdd);
            const modal = new bootstrap.Modal(document.getElementById('lmpModal'));
            modal.show();

            $('#lmpDataList').html('<div class="text-center py-5 text-muted"><div class="spinner-border text-primary me-2"></div>Loading competitors...</div>');

            let amazonData = null;
            let ebayData = null;
            let googleData = null;
            let temuData = null;
            let loaded = 0;
            const totalNeeded = 4;

            function tryRender() {
                loaded++;
                if (loaded < totalNeeded) return;
                const rows = buildLmpMergedRows(sku, amazonData, ebayData, googleData, temuData);
                lmpModalCache = { sku: sku, rows: rows, filter: initialFilter, showAdd: showAdd };
                renderLmpMergedTable();
            }

            // Always load Amazon + eBay + Google + Temu so channel filter can switch instantly
            $.ajax({
                url: '/amazon/competitors',
                method: 'GET',
                data: { sku: sku },
                timeout: 10000,
                success: function(res) {
                    amazonData = res.success && res.competitors ? res : null;
                    tryRender();
                },
                error: function() {
                    amazonData = null;
                    tryRender();
                }
            });

            $.ajax({
                url: '/ebay-lmp-data',
                method: 'GET',
                data: { sku: sku },
                timeout: 10000,
                success: function(res) {
                    ebayData = res.success && res.competitors ? res : null;
                    tryRender();
                },
                error: function() {
                    ebayData = null;
                    tryRender();
                }
            });

            $.ajax({
                url: '/google-lmp-data',
                method: 'GET',
                data: { sku: sku },
                timeout: 10000,
                success: function(res) {
                    googleData = res.success && res.competitors ? res : null;
                    tryRender();
                },
                error: function() {
                    googleData = null;
                    tryRender();
                }
            });

            $.ajax({
                url: '/cvr-master-temu-lmp',
                method: 'GET',
                data: { sku: sku },
                timeout: 10000,
                success: function(res) {
                    temuData = res.success && res.competitors ? res : null;
                    tryRender();
                },
                error: function() {
                    temuData = null;
                    tryRender();
                }
            });
        }

        function formatLmpDelivery(delivery) {
            if (delivery == null || delivery === '') {
                return '<span class="text-muted">-</span>';
            }
            const text = String(delivery).trim();
            if (!text) return '<span class="text-muted">-</span>';

            const amountMatch = text.match(/\$\s*([0-9]+(?:\.[0-9]{1,2})?)/);
            const isFree = /\bfree\b/i.test(text);
            if (isFree && !amountMatch) {
                return '<span style="color:#28a745;font-weight:600;">Free</span>';
            }
            if (amountMatch) {
                const amount = parseFloat(amountMatch[1]);
                if (!isNaN(amount) && amount > 0) {
                    return '<span style="font-weight:600;">$' + amount.toFixed(2) + '</span>';
                }
                if (!isNaN(amount) && amount === 0) {
                    return '<span style="color:#28a745;font-weight:600;">Free</span>';
                }
            }
            if (isFree) {
                return '<span style="color:#28a745;font-weight:600;">Free</span>';
            }
            return '<span class="text-muted">-</span>';
        }

        function lmpChannelIconHtml(channel) {
            if (channel === 'amazon') {
                return '<span class="lmp-channel-icon amazon" title="Amazon"><i class="fab fa-amazon"></i></span>';
            }
            if (channel === 'ebay') {
                return '<span class="lmp-channel-icon ebay" title="eBay"><i class="fas fa-gavel"></i></span>';
            }
            if (channel === 'google') {
                return '<span class="lmp-channel-icon google" title="Google"><i class="fab fa-google"></i></span>';
            }
            if (channel === 'temu') {
                return '<span class="lmp-channel-icon temu" title="Temu">T</span>';
            }
            return '';
        }

        function buildLmpMergedRows(sku, amazonRes, ebayRes, googleRes, temuRes) {
            const rows = [];
            const amzList = (amazonRes && amazonRes.competitors) ? amazonRes.competitors : [];
            const ebayList = (ebayRes && ebayRes.competitors) ? ebayRes.competitors : [];
            const googleList = (googleRes && googleRes.competitors) ? googleRes.competitors : [];
            const temuList = (temuRes && temuRes.competitors) ? temuRes.competitors : [];

            amzList.forEach(function(amz) {
                const price = parseFloat(amz.price) || 0;
                if (price <= 0) return;
                rows.push({
                    channel: 'amazon',
                    id: amz.id,
                    sku: sku,
                    price: price,
                    link: amz.product_link || amz.link || '',
                    extId: amz.asin || '',
                    image: amz.image || '',
                    title: amz.product_title || amz.title || '',
                    rating: amz.rating != null ? parseFloat(amz.rating) : null,
                    reviews: amz.reviews != null ? parseInt(amz.reviews) : null,
                    old_price: amz.extracted_old_price != null ? parseFloat(amz.extracted_old_price) : null,
                    delivery: amz.delivery || '',
                    source: '',
                });
            });

            ebayList.forEach(function(ebay) {
                const price = parseFloat(ebay.total_price || ebay.price) || 0;
                if (price <= 0) return;
                rows.push({
                    channel: 'ebay',
                    id: ebay.id,
                    sku: sku,
                    price: price,
                    link: ebay.link || ebay.product_link || '',
                    extId: ebay.item_id || '',
                    image: ebay.image || '',
                    title: ebay.product_title || ebay.title || '',
                    rating: null,
                    reviews: null,
                    old_price: null,
                    delivery: '',
                    source: '',
                });
            });

            googleList.forEach(function(google) {
                const price = parseFloat(google.price) || 0;
                if (price <= 0) return;
                rows.push({
                    channel: 'google',
                    id: google.id,
                    sku: sku,
                    price: price,
                    link: google.link || google.product_link || '',
                    extId: google.product_id || '',
                    image: google.image || '',
                    title: google.product_title || google.title || '',
                    rating: google.rating != null ? parseFloat(google.rating) : null,
                    reviews: google.reviews != null ? parseInt(google.reviews) : null,
                    old_price: null,
                    delivery: '',
                    source: google.source || '',
                });
            });

            temuList.forEach(function(temu) {
                const price = parseFloat(temu.price) || 0;
                if (price <= 0) return;
                rows.push({
                    channel: 'temu',
                    id: temu.id,
                    sku: sku,
                    price: price,
                    link: temu.link || temu.product_link || '',
                    extId: '',
                    image: temu.image || '',
                    title: temu.product_title || temu.title || '',
                    rating: null,
                    reviews: null,
                    old_price: null,
                    delivery: '',
                    source: 'Temu',
                });
            });

            rows.sort(function(a, b) { return a.price - b.price; });
            return rows;
        }

        function renderLmpMergedTable() {
            const sku = lmpModalCache.sku;
            const filter = lmpModalCache.filter || 'all';
            const allRows = lmpModalCache.rows || [];
            const rows = filter === 'all' ? allRows : allRows.filter(function(r) { return r.channel === filter; });

            const amzPrices = allRows.filter(function(r) { return r.channel === 'amazon'; }).map(function(r) { return r.price; });
            const ebayPrices = allRows.filter(function(r) { return r.channel === 'ebay'; }).map(function(r) { return r.price; });
            const googlePrices = allRows.filter(function(r) { return r.channel === 'google'; }).map(function(r) { return r.price; });
            const temuPrices = allRows.filter(function(r) { return r.channel === 'temu'; }).map(function(r) { return r.price; });
            const amzLowest = amzPrices.length ? Math.min.apply(null, amzPrices) : null;
            const ebayLowest = ebayPrices.length ? Math.min.apply(null, ebayPrices) : null;
            const googleLowest = googlePrices.length ? Math.min.apply(null, googlePrices) : null;
            const temuLowest = temuPrices.length ? Math.min.apply(null, temuPrices) : null;
            const channelLowest = {
                amazon: amzLowest,
                ebay: ebayLowest,
                google: googleLowest,
                temu: temuLowest,
            };

            const counts = {
                all: allRows.length,
                amazon: amzPrices.length,
                ebay: ebayPrices.length,
                google: googlePrices.length,
                temu: temuPrices.length,
            };

            let html = '';
            html += '<div class="lmp-channel-filters">';
            [
                { key: 'all', label: 'All' },
                { key: 'amazon', label: 'Amazon' },
                { key: 'ebay', label: 'eBay' },
                { key: 'google', label: 'Google' },
                { key: 'temu', label: 'Temu' },
            ].forEach(function(opt) {
                const active = filter === opt.key ? ' active' : '';
                let btnClass = 'btn-outline-secondary';
                if (filter === opt.key) {
                    if (opt.key === 'amazon') btnClass = 'btn-warning';
                    else if (opt.key === 'ebay') btnClass = 'btn-primary';
                    else if (opt.key === 'google') btnClass = 'btn-success';
                    else if (opt.key === 'temu') btnClass = 'btn-warning';
                    else btnClass = 'btn-dark';
                }
                html += '<button type="button" class="btn ' + btnClass + active + ' lmp-channel-filter-btn" data-filter="' + opt.key + '">'
                    + opt.label + ' <span class="badge bg-light text-dark ms-1">' + counts[opt.key] + '</span></button>';
            });
            html += '</div>';

            const badgeParts = [];
            if (amzLowest != null) {
                badgeParts.push('<span class="badge" style="background:transparent;color:#ff9900;font-weight:600;border:1px solid #ff9900;">Amz $' + amzLowest.toFixed(2) + '</span>');
            }
            if (ebayLowest != null) {
                badgeParts.push('<span class="badge bg-info text-dark">eBay $' + ebayLowest.toFixed(2) + '</span>');
            }
            if (googleLowest != null) {
                badgeParts.push('<span class="badge bg-success">Google $' + googleLowest.toFixed(2) + '</span>');
            }
            if (temuLowest != null) {
                badgeParts.push('<span class="badge" style="background:#fb7701;color:#fff;">Temu $' + temuLowest.toFixed(2) + '</span>');
            }
            if (badgeParts.length) {
                html += '<div class="lmp-lowest-badges">' + badgeParts.join('') + '</div>';
            }

            // Add LMP form (shown when opened via +, or when a specific channel filter is active)
            const addChannel = (filter !== 'all') ? filter : (lmpModalCache.showAdd ? 'amazon' : null);
            if (addChannel || lmpModalCache.showAdd) {
                html += buildLmpAddFormHtml(sku, addChannel || 'amazon');
            }

            if (!rows.length) {
                const labelMap = { all: 'Amazon, eBay, Google, or Temu', amazon: 'Amazon', ebay: 'eBay', google: 'Google', temu: 'Temu' };
                const label = labelMap[filter] || 'competitors';
                html += '<div class="alert alert-info mb-0 py-2 px-2"><i class="fa fa-info-circle"></i> No ' + label + ' competitors found</div>';
                $('#lmpDataList').html(html);
                applyLmpEditFormState();
                return;
            }

            html += '<div class="table-responsive"><table class="table table-hover table-bordered table-sm"><thead class="table-light">'
                + '<tr><th>#</th><th>Price</th><th>Rating</th><th>Rev</th><th>Del</th><th></th></tr>'
                + '</thead><tbody>';

            rows.forEach(function(row, i) {
                const sn = 'L' + (i + 1);
                const lowest = channelLowest[row.channel];
                const isLowest = lowest != null && Math.abs(row.price - lowest) < 0.01;
                const thumb = row.image
                    ? '<img src="' + String(row.image).replace(/"/g, '&quot;') + '" alt="" class="rounded lmp-thumb me-1" style="object-fit:contain;" onerror="this.style.display=\'none\'">'
                    : '';
                const linkHtml = row.link
                    ? ' <a href="' + String(row.link).replace(/"/g, '&quot;') + '" target="_blank" class="text-primary ms-1" title="Open product"><i class="fa fa-external-link"></i></a>'
                    : '';
                const priceCell = '<div class="d-flex align-items-center">'
                    + thumb
                    + lmpChannelIconHtml(row.channel)
                    + (isLowest ? '<i class="fa fa-trophy text-success me-1"></i>' : '')
                    + '<span style="font-weight:600;">$' + row.price.toFixed(2) + '</span>'
                    + linkHtml
                    + '</div>';
                const ratingCell = row.rating != null
                    ? '<span><i class="fa fa-star text-warning"></i> ' + row.rating.toFixed(1) + '</span>'
                    : '<span class="text-muted">-</span>';
                const reviewsCell = row.reviews != null
                    ? row.reviews.toLocaleString()
                    : '<span class="text-muted">-</span>';
                const deliveryCell = formatLmpDelivery(row.delivery);
                const titleAttr = (row.title || row.source || '').replace(/"/g, '&quot;');
                const linkAttr = String(row.link || '').replace(/"/g, '&quot;');
                const extAttr = String(row.extId || '').replace(/"/g, '&quot;');
                const editBtn = '<button type="button" class="btn btn-sm btn-outline-warning edit-lmp-row-btn me-1" data-id="' + row.id
                    + '" data-marketplace="' + row.channel
                    + '" data-sku="' + String(sku || '').replace(/"/g, '&quot;')
                    + '" data-price="' + row.price
                    + '" data-link="' + linkAttr
                    + '" data-ext-id="' + extAttr
                    + '" title="Edit price"><i class="fa fa-edit"></i></button>';
                const delBtn = '<button type="button" class="btn btn-sm btn-outline-danger delete-lmp-row-btn" data-id="' + row.id
                    + '" data-marketplace="' + row.channel
                    + '" data-sku="' + String(sku || '').replace(/"/g, '&quot;')
                    + '" data-price="' + row.price
                    + '" title="Delete"><i class="fa fa-trash"></i></button>';
                const rowClass = isLowest ? 'table-success' : '';
                html += '<tr class="' + rowClass + '" title="' + titleAttr + '">'
                    + '<td>' + sn + '</td>'
                    + '<td>' + priceCell + '</td>'
                    + '<td>' + ratingCell + '</td>'
                    + '<td>' + reviewsCell + '</td>'
                    + '<td>' + deliveryCell + '</td>'
                    + '<td class="text-nowrap">' + editBtn + delBtn + '</td>'
                    + '</tr>';
            });

            html += '</tbody></table></div>';
            $('#lmpDataList').html(html);
            applyLmpEditFormState();
        }

        function buildLmpAddFormHtml(sku, channel) {
            const ch = (channel || 'amazon').toLowerCase();
            const labelMap = { amazon: 'Amazon', ebay: 'eBay', google: 'Google', temu: 'Temu' };
            const label = labelMap[ch] || ch;
            let idField = '';
            if (ch === 'amazon') {
                idField = '<div class="col-4"><label class="form-label mb-0 small">ASIN</label>'
                    + '<input type="text" class="form-control" id="lmpAddId" placeholder="B0XXXXXXXX" required></div>';
            } else if (ch === 'ebay') {
                idField = '<div class="col-4"><label class="form-label mb-0 small">Item ID</label>'
                    + '<input type="text" class="form-control" id="lmpAddId" placeholder="eBay item id" required></div>';
            } else if (ch === 'google') {
                idField = '<div class="col-4"><label class="form-label mb-0 small">Product ID</label>'
                    + '<input type="text" class="form-control" id="lmpAddId" placeholder="Product ID" required></div>';
            } else {
                // Temu: price + link only
                idField = '';
            }
            return '<div class="lmp-add-form-box">'
                + '<div class="d-flex align-items-center justify-content-between mb-1">'
                + '<strong style="font-size:12px;"><i class="fas fa-plus-circle text-success me-1"></i>Add ' + label + ' LMP</strong>'
                + '<select class="form-select form-select-sm" id="lmpAddChannel" style="width:110px;">'
                + '<option value="amazon"' + (ch === 'amazon' ? ' selected' : '') + '>Amazon</option>'
                + '<option value="ebay"' + (ch === 'ebay' ? ' selected' : '') + '>eBay</option>'
                + '<option value="google"' + (ch === 'google' ? ' selected' : '') + '>Google</option>'
                + '<option value="temu"' + (ch === 'temu' ? ' selected' : '') + '>Temu</option>'
                + '</select></div>'
                + '<form id="lmpAddForm" class="row g-1 align-items-end" novalidate data-sku="' + String(sku || '').replace(/"/g, '&quot;') + '">'
                + '<input type="hidden" id="lmpEditId" value="">'
                + '<input type="hidden" id="lmpFormChannel" value="' + ch + '">'
                + idField
                + '<div class="' + (idField ? 'col-3' : 'col-4') + '"><label class="form-label mb-0 small">Price</label>'
                + '<input type="text" class="form-control" id="lmpAddPrice" inputmode="decimal" placeholder="0.00" autocomplete="off"></div>'
                + '<div class="' + (idField ? 'col-3' : 'col-5') + '"><label class="form-label mb-0 small">Link</label>'
                + '<input type="text" class="form-control" id="lmpAddLink" placeholder="https://..." autocomplete="off"></div>'
                + '<div class="col-auto d-flex gap-1">'
                + '<button type="submit" class="btn btn-success btn-sm" id="lmpAddSubmitBtn"><i class="fas fa-plus me-1"></i>Add</button>'
                + '<button type="button" class="btn btn-outline-secondary btn-sm d-none" id="lmpCancelEditBtn">Cancel</button>'
                + '</div></form></div>';
        }

        function extractAmazonAsin(link) {
            const m = String(link || '').match(/\/(?:dp|gp\/product)\/([A-Z0-9]{10})/i)
                || String(link || '').match(/[?&]asin=([A-Z0-9]{10})/i);
            return m ? m[1].toUpperCase() : '';
        }
        function extractEbayItemId(link) {
            const m = String(link || '').match(/\/itm\/(?:[^\/]+\/)?(\d{9,15})/i)
                || String(link || '').match(/[?&]item=(\d{9,15})/i);
            return m ? m[1] : '';
        }

        $(document).on('input change', '#lmpAddPrice, #lmpAddLink, #lmpAddId', function() {
            syncLmpEditDraftFromForm();
        });

        $(document).on('change', '#lmpAddChannel', function() {
            if (lmpEditState) return;
            lmpModalCache.showAdd = true;
            lmpModalCache.filter = $(this).val() || 'amazon';
            $('#lmpFormChannel').val(lmpModalCache.filter);
            $('#lmpModal').data('lmp-filter', lmpModalCache.filter);
            renderLmpMergedTable();
        });

        $(document).on('click', '#lmpCancelEditBtn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            resetLmpEditState();
            renderLmpMergedTable();
        });

        $(document).on('click', '.edit-lmp-row-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const $btn = $(this);
            enterLmpEditMode({
                id: $btn.attr('data-id') || $btn.data('id'),
                channel: ($btn.attr('data-marketplace') || $btn.data('marketplace') || '').toLowerCase(),
                extId: $btn.attr('data-ext-id') || '',
                price: $btn.attr('data-price') || $btn.data('price'),
                link: $btn.attr('data-link') || '',
            });
        });

        function refreshLmpAfterMutation(sku, channel) {
            resetLmpEditState();
            loadLmpCompetitorsModal(sku, channel, true);
            const currentSku = $('#modalSkuName').text();
            if (currentSku) {
                const currentImage = $('#modal-product-image').attr('src');
                const currentInv = $('#modal-header-inv').text().replace(/,/g, '');
                const currentL30 = $('#modal-header-l30').text().replace(/,/g, '');
                const currentDil = parseFloat($('#modal-header-dil').text());
                loadMarketplaceBreakdown(currentSku, currentImage, currentInv, currentL30, currentDil);
            }
        }

        function saveTemuLmpEntries(sku, entries, done) {
            $.ajax({
                url: '/temu-lmp/save',
                method: 'POST',
                contentType: 'application/json',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'Accept': 'application/json',
                },
                data: JSON.stringify({ sku: sku, lmp_entries: entries }),
                success: function(r) { done(!!r.success, r.message || 'Temu LMP saved'); },
                error: function(xhr) {
                    done(false, (xhr.responseJSON && (xhr.responseJSON.error || xhr.responseJSON.message)) || 'Failed to save Temu LMP');
                }
            });
        }

        function withTemuLmpEntries(sku, mutator, done) {
            $.ajax({
                url: '/cvr-master-temu-lmp',
                method: 'GET',
                data: { sku: sku },
                success: function(res) {
                    const existing = (res && res.competitors) ? res.competitors : [];
                    const entries = existing.map(function(c) {
                        return { price: c.price, link: c.link || c.product_link || null };
                    });
                    const next = mutator(entries, existing);
                    if (next === false) {
                        done(false, 'Temu LMP entry not found');
                        return;
                    }
                    saveTemuLmpEntries(sku, next, done);
                },
                error: function() {
                    done(false, 'Failed to load Temu LMP entries');
                }
            });
        }

        function findTemuEntryIndex(entries, editId, origPrice, origLink) {
            const m = String(editId || '').match(/^temu-(\d+)$/i);
            const idxFromId = m ? (parseInt(m[1], 10) - 1) : -1;
            if (idxFromId >= 0 && idxFromId < entries.length) {
                return idxFromId;
            }
            const op = parseLmpPriceInput(origPrice);
            const ol = String(origLink || '').trim().toUpperCase();
            for (let i = 0; i < entries.length; i++) {
                const ep = parseLmpPriceInput(entries[i].price);
                const el = String(entries[i].link || '').trim().toUpperCase();
                if (!isNaN(op) && !isNaN(ep) && Math.abs(ep - op) < 0.001 && el === ol) {
                    return i;
                }
            }
            return -1;
        }

        $(document).on('submit', '#lmpAddForm', function(e) {
            e.preventDefault();
            e.stopPropagation();
            syncLmpEditDraftFromForm();
            const sku = $(this).attr('data-sku') || $(this).data('sku') || $('#lmpSku').text() || '';
            const channel = (
                (lmpEditState && lmpEditState.channel)
                || $('#lmpFormChannel').val()
                || $('#lmpAddChannel').val()
                || 'amazon'
            ).toLowerCase();
            const editId = ($('#lmpEditId').val() || (lmpEditState && lmpEditState.id) || '').toString();
            const isEdit = !!editId;
            let price = parseLmpPriceInput($('#lmpAddPrice').val());
            let link = ($('#lmpAddLink').val() || '').trim();
            let idVal = ($('#lmpAddId').val() || '').trim();
            if (!(price > 0)) {
                showToast('Enter a valid LMP price', 'error');
                $('#lmpAddPrice').focus();
                return;
            }
            if (channel === 'amazon' && !idVal) idVal = extractAmazonAsin(link);
            if (channel === 'ebay' && !idVal) idVal = extractEbayItemId(link);
            if (channel !== 'temu' && !idVal) {
                showToast(channel === 'amazon' ? 'ASIN is required' : (channel === 'ebay' ? 'Item ID is required' : 'Product ID is required'), 'error');
                return;
            }

            const $btn = $('#lmpAddSubmitBtn');
            const originalHtml = $btn.html();
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

            function done(ok, msg) {
                $btn.prop('disabled', false).html(originalHtml);
                if (ok) {
                    showToast(msg || (isEdit ? 'LMP updated' : 'LMP added'), 'success');
                    refreshLmpAfterMutation(sku, channel);
                } else {
                    showToast(msg || (isEdit ? 'Failed to update LMP' : 'Failed to add LMP'), 'error');
                }
            }

            if (channel === 'amazon') {
                $.ajax({
                    url: isEdit ? '/amazon/lmp/update' : '/amazon/lmp/add',
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    data: isEdit
                        ? { id: editId, asin: idVal, price: price, product_link: link || null }
                        : { sku: sku, asin: idVal, price: price, product_link: link || null, marketplace: 'amazon' },
                    success: function(r) { done(!!r.success, r.message || (isEdit ? 'Amazon LMP updated' : 'Amazon LMP added')); },
                    error: function(xhr) {
                        done(false, (xhr.responseJSON && (xhr.responseJSON.error || xhr.responseJSON.message)) || (isEdit ? 'Failed to update Amazon LMP' : 'Failed to add Amazon LMP'));
                    }
                });
                return;
            }
            if (channel === 'ebay') {
                $.ajax({
                    url: isEdit ? '/ebay-lmp-update' : '/ebay-lmp-add',
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    data: isEdit
                        ? { id: editId, item_id: idVal, price: price, shipping_cost: 0, product_link: link || null }
                        : { sku: sku, item_id: idVal, price: price, shipping_cost: 0, product_link: link || null },
                    success: function(r) { done(!!r.success, r.message || (isEdit ? 'eBay LMP updated' : 'eBay LMP added')); },
                    error: function(xhr) {
                        done(false, (xhr.responseJSON && (xhr.responseJSON.error || xhr.responseJSON.message)) || (isEdit ? 'Failed to update eBay LMP' : 'Failed to add eBay LMP'));
                    }
                });
                return;
            }
            if (channel === 'google') {
                $.ajax({
                    url: isEdit ? '/google-lmp-update' : '/google-lmp-add',
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    data: isEdit
                        ? { id: editId, product_id: idVal, price: price, product_link: link || null }
                        : { sku: sku, product_id: idVal, price: price, product_link: link || null, source: 'manual' },
                    success: function(r) { done(!!(r.success || r.data), r.message || (isEdit ? 'Google LMP updated' : 'Google LMP added')); },
                    error: function(xhr) {
                        done(false, (xhr.responseJSON && (xhr.responseJSON.error || xhr.responseJSON.message)) || (isEdit ? 'Failed to update Google LMP' : 'Failed to add Google LMP'));
                    }
                });
                return;
            }

            // Temu: append or replace entry then save
            if (isEdit) {
                const origPrice = lmpEditState ? lmpEditState.origPrice : null;
                const origLink = lmpEditState ? lmpEditState.origLink : '';
                withTemuLmpEntries(sku, function(entries) {
                    const idx = findTemuEntryIndex(entries, editId, origPrice, origLink);
                    if (idx < 0) return false;
                    entries[idx] = { price: price, link: link || null };
                    return entries;
                }, done);
                return;
            }
            withTemuLmpEntries(sku, function(entries) {
                entries.push({ price: price, link: link || null });
                return entries;
            }, function(ok, msg) {
                if (!ok && msg === 'Failed to load Temu LMP entries') {
                    saveTemuLmpEntries(sku, [{ price: price, link: link || null }], done);
                    return;
                }
                done(ok, msg || 'Temu LMP added');
            });
        });

        $(document).on('click', '.lmp-channel-filter-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (lmpEditState) resetLmpEditState();
            const filter = $(this).data('filter') || 'all';
            lmpModalCache.filter = filter;
            if (filter !== 'all') lmpModalCache.showAdd = true;
            $('#lmpModal').data('lmp-filter', filter);
            renderLmpMergedTable();
        });
        
        $(document).on('click', '.view-lmp-competitors', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const sku = $(this).data('sku');
            if (sku) loadLmpCompetitorsModal(sku);
        });

        $(document).on('click', '.lmp-price-link', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const sku = $(this).data('sku');
            const marketplace = $(this).data('marketplace'); // 'amazon' or 'ebay'
            if (sku) loadLmpCompetitorsModal(sku, marketplace);
        });

        $(document).on('click', '.delete-lmp-row-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const btn = $(this);
            const id = btn.attr('data-id') || btn.data('id');
            const marketplace = (btn.attr('data-marketplace') || btn.data('marketplace') || '').toLowerCase();
            const sku = btn.attr('data-sku') || btn.data('sku') || $('#lmpSku').text();
            const price = btn.attr('data-price') || btn.data('price');
            const link = btn.closest('tr').find('a.text-primary').attr('href') || '';
            const labelMap = { amazon: 'Amazon', ebay: 'eBay', google: 'Google', temu: 'Temu' };
            const label = labelMap[marketplace] || marketplace;
            if (!id) return;
            if (!confirm('Delete this ' + label + ' competitor ($' + (price ? parseFloat(price).toFixed(2) : '') + ') from LMP? This cannot be undone.')) return;

            function afterDelete(ok, msg) {
                if (ok) {
                    showToast(msg || 'Competitor deleted', 'success');
                    refreshLmpAfterMutation(sku, marketplace);
                } else {
                    showToast(msg || 'Failed to delete', 'error');
                }
            }

            if (marketplace === 'temu') {
                withTemuLmpEntries(sku, function(entries) {
                    const idx = findTemuEntryIndex(entries, id, price, link);
                    if (idx < 0) return false;
                    entries.splice(idx, 1);
                    return entries;
                }, afterDelete);
                return;
            }

            const url = marketplace === 'amazon' ? '/amazon/lmp/delete' : (marketplace === 'google' ? '/google-lmp-delete' : '/ebay-lmp-delete');
            $.ajax({
                url: url,
                method: 'POST',
                data: { id: id, _token: '{{ csrf_token() }}' },
                success: function(response) {
                    afterDelete(!!response.success, response.message || response.error);
                },
                error: function(xhr) {
                    const msg = (xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'Failed to delete competitor';
                    afterDelete(false, msg);
                }
            });
        });

        // ==================== REMARK FUNCTIONS ====================
        
        // Submit remark
        $(document).on('click', '.submit-remark-btn', function(e) {
            e.stopPropagation();
            const sku = $(this).data('sku');
            const remarkInput = $(`.remark-input[data-sku="${sku}"]`);
            const remark = remarkInput.val().trim();
            
            if (!remark) {
                showToast('Please enter a remark', 'error');
                return;
            }
            
            $.ajax({
                url: '/cvr-master-remark',
                method: 'POST',
                data: {
                    sku: sku,
                    remark: remark,
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    showToast('Remark saved successfully', 'success');
                    remarkInput.val(''); // Clear input
                    
                    // Update the cell directly
                    const row = table.getRow(function(row){ return row.getData().sku === sku; });
                    if (row) {
                        row.update({latest_remark: remark, remark_solved: false});
                    }
                },
                error: function() {
                    showToast('Failed to save remark', 'error');
                }
            });
        });

        // Open remark history modal
        $(document).on('click', '.remark-history-icon', function(e) {
            e.stopPropagation();
            const sku = $(this).data('sku');
            loadRemarkHistory(sku);
        });

        function loadRemarkHistory(sku) {
            $('#historySkuName').text(sku);
            const modal = new bootstrap.Modal(document.getElementById('remarkHistoryModal'));
            modal.show();
            
            $('#remarkHistoryTableBody').html(`
                <tr>
                    <td colspan="5" class="text-center">
                        <div class="spinner-border spinner-border-sm text-info me-2" role="status"></div>
                        Loading history...
                    </td>
                </tr>
            `);
            
            $.ajax({
                url: `/cvr-master-remark-history/${sku}`,
                method: 'GET',
                success: function(data) {
                    if (data.length === 0) {
                        $('#remarkHistoryTableBody').html(`
                            <tr><td colspan="5" class="text-center text-muted py-4">No remarks yet for this SKU</td></tr>
                        `);
                        return;
                    }
                    
                    let html = '';
                    data.forEach(item => {
                        const statusClass = item.is_solved ? 'success' : 'warning';
                        const statusText = item.is_solved ? 'Solved' : 'Pending';
                        const statusIcon = item.is_solved ? 'check-circle' : 'clock';
                        
                        html += `
                            <tr>
                                <td>${item.remark}</td>
                                <td>${item.user_name}</td>
                                <td><small>${item.created_at}</small></td>
                                <td>
                                    <span class="badge bg-${statusClass}">
                                        <i class="fas fa-${statusIcon}"></i> ${statusText}
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-${item.is_solved ? 'warning' : 'success'} toggle-solved-btn" 
                                            data-id="${item.id}"
                                            title="${item.is_solved ? 'Mark as Pending' : 'Mark as Solved'}">
                                        <i class="fas fa-${item.is_solved ? 'undo' : 'check'}"></i>
                                    </button>
                                </td>
                            </tr>
                        `;
                    });
                    
                    $('#remarkHistoryTableBody').html(html);
                },
                error: function() {
                    $('#remarkHistoryTableBody').html(`
                        <tr><td colspan="5" class="text-center text-danger py-4">Failed to load history</td></tr>
                    `);
                }
            });
        }

        // Toggle solved status
        $(document).on('click', '.toggle-solved-btn', function(e) {
            e.stopPropagation();
            const id = $(this).data('id');
            const sku = $('#historySkuName').text();
            
            $.ajax({
                url: `/cvr-master-remark-toggle/${id}`,
                method: 'POST',
                data: { _token: '{{ csrf_token() }}' },
                success: function(response) {
                    showToast('Status updated', 'success');
                    loadRemarkHistory(sku); // Reload history
                    
                    // Update the row in table if it's the latest remark
                    const row = table.getRow(function(row){ return row.getData().sku === sku; });
                    if (row) {
                        const currentData = row.getData();
                        row.update({remark_solved: response.is_solved});
                    }
                },
                error: function() {
                    showToast('Failed to update status', 'error');
                }
            });
        });

        // Store all data for parent expand/collapse
        let fullDataset = [];
        let expandedParent = null;
        let dotExpandedParent = null;
        // Play/Pause parent navigation (same as product master: show only current parent, ignore other filters)
        // playNavMode: null | 'default' | 'npft' | 'dil' | 'cvr' | 'groi'
        let isPlayNavigationActive = false;
        let playNavMode = null;
        let currentPlayParentIndex = 0;
        // Prevent dataLoaded side-effects for local setData operations
        let suppressDataLoadedHandler = false;

        /** Reorder data so "10 FR" group is first, then other groups A-Z by parent; within each group children A-Z by SKU then parent row last. */
        function reorderDataWith10FRFirst(data) {
            if (!data || data.length === 0) return data;
            const parentGroups = {};
            data.forEach(row => {
                const p = (row.parent || '').toString().trim();
                if (!parentGroups[p]) parentGroups[p] = { children: [], parentRow: null };
                if (row.is_parent_summary === true) {
                    parentGroups[p].parentRow = row;
                } else {
                    parentGroups[p].children.push(row);
                }
            });
            const PREFER_FIRST = '10 FR';
            const parentNames = Object.keys(parentGroups).filter(p => p !== '');
            parentNames.sort((a, b) => {
                if (a === PREFER_FIRST) return -1;
                if (b === PREFER_FIRST) return 1;
                return String(a).localeCompare(String(b));
            });
            const out = [];
            parentNames.forEach(p => {
                const g = parentGroups[p];
                if (!g) return;
                g.children.sort((a, b) => String((a.sku || '')).localeCompare(String((b.sku || ''))));
                out.push(...g.children);
                if (g.parentRow) out.push(g.parentRow);
            });
            // Rows with empty parent at end
            if (parentGroups['']) {
                const g = parentGroups[''];
                g.children.sort((a, b) => String((a.sku || '')).localeCompare(String((b.sku || ''))));
                out.push(...g.children);
                if (g.parentRow) out.push(g.parentRow);
            }
            return out;
        }

        // Row selection - Set of selected SKUs
        let selectedSkus = new Set();

        function isAllFilteredSelected() {
            if (!table) return false;
            const rows = table.getRows('active');
            if (rows.length === 0) return false;
            return rows.every(r => selectedSkus.has(r.getData().sku));
        }

        // Row checkbox click
        $(document).on('change', '.row-select-cb', function() {
            if (!table) return;
            const sku = $(this).data('sku');
            if ($(this).is(':checked')) {
                selectedSkus.add(sku);
            } else {
                selectedSkus.delete(sku);
            }
            const row = table.getRow(function(r) { return r.getData().sku === sku; });
            if (row) row.reformat();
            const headerCb = document.querySelector('#cvr-table .select-all-cb');
            if (headerCb) headerCb.checked = isAllFilteredSelected();
        });

        // Select all checkbox click
        $(document).on('change', '.select-all-cb', function() {
            if (!table) return;
            const $cb = $(this);
            const rows = table.getRows('active');
            if ($cb.is(':checked')) {
                rows.forEach(r => selectedSkus.add(r.getData().sku));
            } else {
                rows.forEach(r => selectedSkus.delete(r.getData().sku));
            }
            table.getRows().forEach(r => r.reformat());
            const headerCb = document.querySelector('#cvr-table .select-all-cb');
            if (headerCb) headerCb.checked = isAllFilteredSelected();
        });

        // Parent SKU dot click - expand to show parent + all child rows with full data
        $(document).on('click', '.parent-sku-dot-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const $dot = $(this);
            const parentVal = $dot.data('parent');
            if (!parentVal) return;

            if (dotExpandedParent === parentVal) {
                // Keep current value; applyFilters() needs it to restore fullDataset
                applyFilters();
                return;
            }

            dotExpandedParent = parentVal;

            const parentRow = fullDataset.find(row =>
                row.is_parent_summary === true && row.parent === parentVal
            );
            const childRows = fullDataset.filter(row =>
                row.parent === parentVal && row.is_parent_summary !== true
            );

            let displayData = [];
            displayData = displayData.concat(childRows);
            if (parentRow) {
                parentRow._expanded = true;
                displayData.push(parentRow);
            }

            suppressDataLoadedHandler = true;
            table.setData(displayData).then(() => {
                updateSummary();
            });
        });

        function buildParentView() {
            console.log('=== Building Parent View ===');
            console.log('fullDataset length:', fullDataset.length);
            console.log('expandedParent:', expandedParent);
            
            if (!fullDataset || fullDataset.length === 0) {
                console.error('❌ No data available in fullDataset');
                return;
            }
            
            const parentRows = fullDataset.filter(row => row.is_parent_summary === true);
            const childRows = fullDataset.filter(row => row.is_parent_summary !== true);
            
            console.log('Parents found:', parentRows.length);
            console.log('Children found:', childRows.length);
            
            // Debug: Show first few parent values
            if (parentRows.length > 0) {
                console.log('Sample parent values:', parentRows.slice(0, 3).map(p => p.parent));
            }
            
            let displayData = [];
            
            // Build ordered list: when expanded, show children first then parent last (parent row highlighted at bottom)
            parentRows.forEach(parent => {
                // Mark parent as expanded or not (for icon display)
                parent._expanded = (expandedParent === parent.parent);
                
                if (expandedParent !== null && expandedParent === parent.parent) {
                    const children = childRows.filter(child => child.parent === expandedParent);
                    console.log('✓ Parent matched! Adding', children.length, 'children then parent for:', parent.parent);
                    if (children.length > 0) {
                        console.log('Sample children SKUs:', children.slice(0, 3).map(c => c.sku));
                    }
                    // Children first, then parent at bottom (like product-master: parent row highlighted last)
                    displayData = displayData.concat(children);
                    displayData.push(parent);
                } else {
                    displayData.push(parent);
                }
            });
            
            // Apply inventory filter to parent view (so "More than 0" hides parent rows with INV 0)
            const inventoryFilter = $('#inventory-filter').val();
            if (inventoryFilter === 'zero') {
                displayData = displayData.filter(row => (parseFloat(row.inventory) || 0) === 0);
            } else if (inventoryFilter === 'more') {
                displayData = displayData.filter(row => (parseFloat(row.inventory) || 0) > 0);
            }
            // Apply DIL% filter to parent view
            const dilFilter = $('.column-filter[data-column="dil_percent"].active')?.data('color') || 'all';
            if (dilFilter !== 'all') {
                displayData = displayData.filter(row => {
                    const inv = parseFloat(row.inventory) || 0;
                    const l30 = parseFloat(row.overall_l30) || 0;
                    const dil = inv === 0 ? 0 : (l30 / inv) * 100;
                    if (dilFilter === 'red') return dil < 25;
                    if (dilFilter === 'green') return dil >= 25 && dil < 50;
                    if (dilFilter === 'pink') return dil >= 50;
                    return true;
                });
            }
            // Apply CVR filter to parent view (ranges: 0, 0.01-1, 1-2, 2-3, 3-4, 0-4, 4-7, 7-10, 10+)
            const cvrRange = $('.column-filter[data-column="avg_cvr"].active')?.data('range') || 'all';
            if (cvrRange !== 'all') {
                displayData = displayData.filter(row => {
                    const cvr = parseFloat(row.avg_cvr) || 0;
                    if (cvrRange === '0') return cvr >= 0 && cvr < 0.01;
                    if (cvrRange === '0.01-1') return cvr >= 0.01 && cvr < 1;
                    if (cvrRange === '1-2') return cvr >= 1 && cvr < 2;
                    if (cvrRange === '2-3') return cvr >= 2 && cvr < 3;
                    if (cvrRange === '3-4') return cvr >= 3 && cvr < 4;
                    if (cvrRange === '0-4') return cvr >= 0 && cvr < 4;
                    if (cvrRange === '4-7') return cvr >= 4 && cvr < 7;
                    if (cvrRange === '7-13') return cvr >= 7 && cvr < 13;
                    if (cvrRange === '10+') return cvr >= 10;
                    return true;
                });
            }
            // Apply GPFT% filter (<20, 20-30, 30-40, >40)
            const gpftRange = $('.column-filter[data-column="avg_gpft"].active')?.data('range') || 'all';
            if (gpftRange !== 'all') {
                displayData = displayData.filter(row => {
                    const gpft = parseFloat(row.avg_gpft) || 0;
                    if (gpftRange === 'lt-20') return gpft < 20;
                    if (gpftRange === '20-30') return gpft >= 20 && gpft < 30;
                    if (gpftRange === '30-40') return gpft >= 30 && gpft <= 40;
                    if (gpftRange === 'gt-40') return gpft > 40;
                    // legacy saved filters
                    if (gpftRange === 'negative' || gpftRange === '0-10' || gpftRange === '10-20') return gpft < 20;
                    if (gpftRange === '40-50' || gpftRange === '50-60' || gpftRange === '50+') return gpft > 40;
                    return true;
                });
            }
            // Apply NPFT% filter (<30, 30-40, 40-50, >50)
            const npftRange = $('.column-filter[data-column="avg_pft"].active')?.data('range') || 'all';
            if (npftRange !== 'all') {
                displayData = displayData.filter(row => {
                    const npft = parseFloat(row.avg_pft) || 0;
                    if (npftRange === 'lt-30') return npft < 30;
                    if (npftRange === '30-40') return npft >= 30 && npft < 40;
                    if (npftRange === '40-50') return npft >= 40 && npft <= 50;
                    if (npftRange === 'gt-50') return npft > 50;
                    return true;
                });
            }
            const swL30MatchFilter = ($('#sw-l30-match-filter').val() || 'all').toString();
            if (swL30MatchFilter === 'red') {
                displayData = displayData.filter(row => {
                    const sw = parseFloat(row.m_l30 ?? 0);
                    const ov = parseFloat(row.overall_l30 ?? 0);
                    return Math.abs(sw - ov) >= 0.01;
                });
            }
            
            console.log('Final display data length:', displayData.length);
            console.log('Expected:', parentRows.length, '+ children if expanded');
            
            // Update table
            suppressDataLoadedHandler = true;
            table.setData(displayData).then(() => {
                console.log('✓ Table updated successfully');
                // Re-apply SKU and Parent search when in parent view
                const skuVal = $('#sku-search').val();
                if (skuVal) table.addFilter("sku", "like", skuVal);
                const parentVal = $('#parent-search').val();
                if (parentVal) table.addFilter("parent", "like", parentVal);
                updateSummary();
            }).catch(err => {
                console.error('❌ Error updating table:', err);
            });
        }

        // ==================== NAVIGATE PARENTS (Play/Pause like product master) ====================
        function getParentRows() {
            if (!fullDataset || fullDataset.length === 0) return [];
            return fullDataset.filter(row => row.is_parent_summary === true);
        }

        /** Parent list for active playback. npft/dil/cvr/groi = lowest metric first. */
        function getPlayParentRows() {
            const parents = getParentRows();
            let field = null;
            if (playNavMode === 'npft') field = 'avg_pft';
            else if (playNavMode === 'dil') field = 'dil_percent';
            else if (playNavMode === 'cvr') field = 'avg_cvr';
            else if (playNavMode === 'groi') field = 'avg_roi';
            if (!field) return parents;
            return parents.slice().sort(function(a, b) {
                const na = parseFloat(a[field]);
                const nb = parseFloat(b[field]);
                const va = isFinite(na) ? na : Number.POSITIVE_INFINITY;
                const vb = isFinite(nb) ? nb : Number.POSITIVE_INFINITY;
                if (va !== vb) return va - vb;
                return String(a.parent || '').localeCompare(String(b.parent || ''));
            });
        }

        function resetAllPlayUiButtons() {
            $('#play-pause, #play-npft-pause, #play-dil-pause, #play-cvr-pause, #play-groi-pause').hide();
            $('#play-auto, #play-npft-auto, #play-dil-auto, #play-cvr-auto, #play-groi-auto').show();
            $('#play-backward, #play-forward, #play-npft-backward, #play-npft-forward, #play-dil-backward, #play-dil-forward, #play-cvr-backward, #play-cvr-forward, #play-groi-backward, #play-groi-forward').prop('disabled', true);
        }

        function getCurrentParentIndex() {
            const parentRows = getParentRows();
            if (parentRows.length === 0 || expandedParent === null) return -1;
            const idx = parentRows.findIndex(p => p.parent === expandedParent);
            return idx >= 0 ? idx : -1;
        }

        function goToParentByIndex(index) {
            const parentRows = getParentRows();
            if (parentRows.length === 0 || index < 0 || index >= parentRows.length) return;
            expandedParent = parentRows[index].parent;
            buildParentView();
        }

        function refreshPlayTooltips() {
            if (typeof bootstrap === 'undefined' || !bootstrap.Tooltip) return;
            document.querySelectorAll('.cvr-play-group [data-bs-toggle="tooltip"]').forEach(function(el) {
                const existing = bootstrap.Tooltip.getInstance(el);
                if (existing) existing.dispose();
                new bootstrap.Tooltip(el);
            });
        }

        /** Show only current parent's rows (children first, parent row last – like product master). No other filters. */
        function showCurrentParentPlayView() {
            if (!fullDataset || fullDataset.length === 0) return;
            const parentRows = getPlayParentRows();
            if (parentRows.length === 0) return;
            const currentParent = parentRows[currentPlayParentIndex].parent;
            const childRows = fullDataset.filter(row => row.parent === currentParent && row.is_parent_summary !== true);
            const parentRow = fullDataset.find(row => row.is_parent_summary === true && row.parent === currentParent);
            // Children first, then parent row at the end (same order as product master)
            const displayData = [...childRows];
            if (parentRow) displayData.push(parentRow);
            suppressDataLoadedHandler = true;
            table.clearSort(); // Keep our order: parent row last, don't re-sort by DIL% etc.
            table.setData(displayData).then(() => {
                updateSummary();
                updatePlayButtonStates();
            });
        }

        function startPlayNavigation(mode) {
            const nextMode = mode || 'default';
            const parentRowsPreview = (function() {
                const prev = playNavMode;
                playNavMode = nextMode;
                const rows = getPlayParentRows();
                playNavMode = prev;
                return rows;
            })();
            if (parentRowsPreview.length === 0) return;

            playNavMode = nextMode;
            isPlayNavigationActive = true;
            currentPlayParentIndex = 0;
            dotExpandedParent = null;
            expandedParent = null;
            showCurrentParentPlayView();

            resetAllPlayUiButtons();
            if (playNavMode === 'npft') {
                $('#play-npft-auto').hide();
                $('#play-npft-pause').show();
            } else if (playNavMode === 'dil') {
                $('#play-dil-auto').hide();
                $('#play-dil-pause').show();
            } else if (playNavMode === 'cvr') {
                $('#play-cvr-auto').hide();
                $('#play-cvr-pause').show();
            } else if (playNavMode === 'groi') {
                $('#play-groi-auto').hide();
                $('#play-groi-pause').show();
            } else {
                $('#play-auto').hide();
                $('#play-pause').show();
            }
            updatePlayButtonStates();
            refreshPlayTooltips();
        }

        function stopPlayNavigation() {
            isPlayNavigationActive = false;
            playNavMode = null;
            currentPlayParentIndex = 0;
            expandedParent = null;
            dotExpandedParent = null;
            resetAllPlayUiButtons();
            refreshPlayTooltips();
            if (fullDataset.length > 0) {
                suppressDataLoadedHandler = true;
                table.setData(fullDataset).then(applyFilters);
            } else {
                applyFilters();
            }
        }

        function updatePlayButtonStates() {
            const parentRows = getPlayParentRows();
            const atStart = !isPlayNavigationActive || currentPlayParentIndex <= 0;
            const atEnd = !isPlayNavigationActive || currentPlayParentIndex >= parentRows.length - 1;

            $('#play-backward, #play-forward, #play-npft-backward, #play-npft-forward, #play-dil-backward, #play-dil-forward, #play-cvr-backward, #play-cvr-forward, #play-groi-backward, #play-groi-forward').prop('disabled', true);
            if (playNavMode === 'npft') {
                $('#play-npft-backward').prop('disabled', atStart);
                $('#play-npft-forward').prop('disabled', atEnd);
            } else if (playNavMode === 'dil') {
                $('#play-dil-backward').prop('disabled', atStart);
                $('#play-dil-forward').prop('disabled', atEnd);
            } else if (playNavMode === 'cvr') {
                $('#play-cvr-backward').prop('disabled', atStart);
                $('#play-cvr-forward').prop('disabled', atEnd);
            } else if (playNavMode === 'groi') {
                $('#play-groi-backward').prop('disabled', atStart);
                $('#play-groi-forward').prop('disabled', atEnd);
            } else if (playNavMode === 'default') {
                $('#play-backward').prop('disabled', atStart);
                $('#play-forward').prop('disabled', atEnd);
            }

            $('#play-auto').attr('title', 'Play: walk parents in default order');
            $('#play-pause').attr('title', 'Pause: stop and show all rows');
            $('#play-backward').attr('title', 'Previous parent');
            $('#play-forward').attr('title', 'Next parent');
            $('#play-npft-auto').attr('title', 'Play Lowest NPFT%: walk parents starting from lowest Avg NPFT%');
            $('#play-npft-pause').attr('title', 'Pause: stop NPFT playback and show all rows');
            $('#play-npft-backward').attr('title', 'Previous: lower NPFT% parent');
            $('#play-npft-forward').attr('title', 'Next: next higher NPFT% parent');
            $('#play-dil-auto').attr('title', 'Play Lowest Dil%: walk parents starting from lowest Dil%');
            $('#play-dil-pause').attr('title', 'Pause: stop Dil% playback and show all rows');
            $('#play-dil-backward').attr('title', 'Previous: lower Dil% parent');
            $('#play-dil-forward').attr('title', 'Next: next higher Dil% parent');
            $('#play-cvr-auto').attr('title', 'Play Lowest CVR%: walk parents starting from lowest Avg CVR%');
            $('#play-cvr-pause').attr('title', 'Pause: stop CVR% playback and show all rows');
            $('#play-cvr-backward').attr('title', 'Previous: lower CVR% parent');
            $('#play-cvr-forward').attr('title', 'Next: next higher CVR% parent');
            $('#play-groi-auto').attr('title', 'Play Lowest GROI%: walk parents starting from lowest Avg GROI%');
            $('#play-groi-pause').attr('title', 'Pause: stop GROI% playback and show all rows');
            $('#play-groi-backward').attr('title', 'Previous: lower GROI% parent');
            $('#play-groi-forward').attr('title', 'Next: next higher GROI% parent');
            refreshPlayTooltips();
        }

        function playNextParent() {
            if (!isPlayNavigationActive) return;
            const parentRows = getPlayParentRows();
            if (currentPlayParentIndex >= parentRows.length - 1) return;
            currentPlayParentIndex++;
            showCurrentParentPlayView();
        }

        function playPreviousParent() {
            if (!isPlayNavigationActive) return;
            if (currentPlayParentIndex <= 0) return;
            currentPlayParentIndex--;
            showCurrentParentPlayView();
        }

        $('#play-auto').on('click', function() { startPlayNavigation('default'); });
        $('#play-pause').on('click', stopPlayNavigation);
        $('#play-forward').on('click', playNextParent);
        $('#play-backward').on('click', playPreviousParent);

        $('#play-npft-auto').on('click', function() { startPlayNavigation('npft'); });
        $('#play-npft-pause').on('click', stopPlayNavigation);
        $('#play-npft-forward').on('click', playNextParent);
        $('#play-npft-backward').on('click', playPreviousParent);

        $('#play-dil-auto').on('click', function() { startPlayNavigation('dil'); });
        $('#play-dil-pause').on('click', stopPlayNavigation);
        $('#play-dil-forward').on('click', playNextParent);
        $('#play-dil-backward').on('click', playPreviousParent);

        $('#play-cvr-auto').on('click', function() { startPlayNavigation('cvr'); });
        $('#play-cvr-pause').on('click', stopPlayNavigation);
        $('#play-cvr-forward').on('click', playNextParent);
        $('#play-cvr-backward').on('click', playPreviousParent);

        $('#play-groi-auto').on('click', function() { startPlayNavigation('groi'); });
        $('#play-groi-pause').on('click', stopPlayNavigation);
        $('#play-groi-forward').on('click', playNextParent);
        $('#play-groi-backward').on('click', playPreviousParent);

        // Init hover tooltips for playback controls
        $(function() { refreshPlayTooltips(); });

        // ==================== FILTER FUNCTIONS ====================
        
        $(document).on('click', '.manual-dropdown-container .btn', function(e) {
            e.stopPropagation();
            const container = $(this).closest('.manual-dropdown-container');
            $('.manual-dropdown-container').not(container).removeClass('show');
            container.toggleClass('show');
        });

        $(document).on('click', '.column-filter', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $item = $(this);
            const container = $item.closest('.manual-dropdown-container');
            const button = container.find('.btn');
            const column = $item.data('column');
            
            container.find('.column-filter').removeClass('active');
            $item.addClass('active');
            
            const statusCircle = $item.find('.status-circle').clone();
            const label = column === 'dil_percent' ? ' DIL%'
                : (column === 'avg_cvr' ? ' CVR'
                : (column === 'avg_gpft' ? ' GPFT%'
                : (column === 'avg_pft' ? ' NPFT%' : '')));
            button.html('').append(statusCircle).append(label);
            container.removeClass('show');
            
            applyFilters();
        });

        $(document).on('click', function() {
            $('.manual-dropdown-container').removeClass('show');
        });

        function applyFilters() {
            // When Play navigation is active, ignore all filters and show only current parent (same as product master)
            if (isPlayNavigationActive) {
                showCurrentParentPlayView();
                return;
            }

            const wasInDotView = !!dotExpandedParent;
            dotExpandedParent = null;

            const doFilters = function() {
                const inventoryFilter = $('#inventory-filter').val();
                const dilFilter = $('.column-filter[data-column="dil_percent"].active')?.data('color') || 'all';
                const skuParentFilter = $('#sku-parent-filter').val();

                table.clearFilter();

                // SKU/Parent filter
                if (skuParentFilter === 'sku') {
                    table.addFilter(function(data) {
                        return data.is_parent_summary !== true;
                    });
                } else if (skuParentFilter === 'parent') {
                    expandedParent = null;
                    buildParentView();
                    return;
                }

                if (inventoryFilter === 'zero') {
                    table.addFilter("inventory", "=", 0);
                } else if (inventoryFilter === 'more') {
                    table.addFilter("inventory", ">", 0);
                }

                if (dilFilter !== 'all') {
                    table.addFilter(function(data) {
                        const inv = parseFloat(data['inventory']) || 0;
                        const l30 = parseFloat(data['overall_l30']) || 0;
                        const dil = inv === 0 ? 0 : (l30 / inv) * 100;
                        if (dilFilter === 'red') return dil < 25;
                        if (dilFilter === 'green') return dil >= 25 && dil < 50;
                        if (dilFilter === 'pink') return dil >= 50;
                        return true;
                    });
                }

                const cvrRange = $('.column-filter[data-column="avg_cvr"].active')?.data('range') || 'all';
                if (cvrRange !== 'all') {
                    table.addFilter(function(data) {
                        const cvr = parseFloat(data['avg_cvr']) || 0;
                        if (cvrRange === '0') return cvr >= 0 && cvr < 0.01;
                        if (cvrRange === '0.01-1') return cvr >= 0.01 && cvr < 1;
                        if (cvrRange === '1-2') return cvr >= 1 && cvr < 2;
                        if (cvrRange === '2-3') return cvr >= 2 && cvr < 3;
                        if (cvrRange === '3-4') return cvr >= 3 && cvr < 4;
                        if (cvrRange === '0-4') return cvr >= 0 && cvr < 4;
                        if (cvrRange === '4-7') return cvr >= 4 && cvr < 7;
                        if (cvrRange === '7-13') return cvr >= 7 && cvr < 13;
                        if (cvrRange === '10+') return cvr >= 10;
                        return true;
                    });
                }

                const gpftRange = $('.column-filter[data-column="avg_gpft"].active')?.data('range') || 'all';
                if (gpftRange !== 'all') {
                    table.addFilter(function(data) {
                        const gpft = parseFloat(data['avg_gpft']) || 0;
                        if (gpftRange === 'lt-20') return gpft < 20;
                        if (gpftRange === '20-30') return gpft >= 20 && gpft < 30;
                        if (gpftRange === '30-40') return gpft >= 30 && gpft <= 40;
                        if (gpftRange === 'gt-40') return gpft > 40;
                        if (gpftRange === 'negative' || gpftRange === '0-10' || gpftRange === '10-20') return gpft < 20;
                        if (gpftRange === '40-50' || gpftRange === '50-60' || gpftRange === '50+') return gpft > 40;
                        return true;
                    });
                }

                const npftRange = $('.column-filter[data-column="avg_pft"].active')?.data('range') || 'all';
                if (npftRange !== 'all') {
                    table.addFilter(function(data) {
                        const npft = parseFloat(data['avg_pft']) || 0;
                        if (npftRange === 'lt-30') return npft < 30;
                        if (npftRange === '30-40') return npft >= 30 && npft < 40;
                        if (npftRange === '40-50') return npft >= 40 && npft <= 50;
                        if (npftRange === 'gt-50') return npft > 50;
                        return true;
                    });
                }

                const swL30MatchFilter = ($('#sw-l30-match-filter').val() || 'all').toString();
                if (swL30MatchFilter === 'red') {
                    table.addFilter(function(data) {
                        const sw = parseFloat(data.m_l30 ?? 0);
                        const ov = parseFloat(data.overall_l30 ?? 0);
                        return Math.abs(sw - ov) >= 0.01;
                    });
                }

                // Apply SKU and Parent search filters
                const skuVal = $('#sku-search').val();
                if (skuVal) table.addFilter("sku", "like", skuVal);
                const parentVal = $('#parent-search').val();
                if (parentVal) table.addFilter("parent", "like", parentVal);

                updateSummary();
            };

            if (wasInDotView && fullDataset.length > 0) {
                suppressDataLoadedHandler = true;
                table.setData(fullDataset).then(doFilters);
            } else {
                doFilters();
            }
        }

        $('#inventory-filter, #sku-parent-filter, #sw-l30-match-filter').on('change', function() {
            applyFilters();
        });

        $('#remove-filter-btn').on('click', function() {
            $('#inventory-filter').val('more');
            $('#sku-parent-filter').val('both');
            $('#sku-search').val('');
            $('#parent-search').val('');
            // Reset DIL
            const $allDil = $('.column-filter[data-column="dil_percent"][data-color="all"]');
            $('.column-filter[data-column="dil_percent"]').removeClass('active');
            $allDil.addClass('active');
            $('#dilFilterDropdown').html('').append($allDil.find('.status-circle').clone()).append(' DIL%');
            // Reset CVR
            const $allCvr = $('.column-filter[data-column="avg_cvr"][data-range="all"]');
            $('.column-filter[data-column="avg_cvr"]').removeClass('active');
            $allCvr.addClass('active');
            $('#cvrFilterDropdown').html('').append($allCvr.find('.status-circle').clone()).append(' CVR');
            // Reset GPFT%
            const $allGpft = $('.column-filter[data-column="avg_gpft"][data-range="all"]');
            $('.column-filter[data-column="avg_gpft"]').removeClass('active');
            $allGpft.addClass('active');
            $('#gpftFilterDropdown').html('').append($allGpft.find('.status-circle').clone()).append(' GPFT%');
            // Reset NPFT%
            const $allNpft = $('.column-filter[data-column="avg_pft"][data-range="all"]');
            $('.column-filter[data-column="avg_pft"]').removeClass('active');
            $allNpft.addClass('active');
            $('#npftFilterDropdown').html('').append($allNpft.find('.status-circle').clone()).append(' NPFT%');
            $('#sw-l30-match-filter').val('all');
            applyFilters();
        });

        // ==================== SUMMARY FUNCTIONS ====================
        
        function updateSummary() {
            const data = table.getData('active');
            let totalInv = 0, totalL30 = 0, totalDil = 0, dilCount = 0;
            let totalViews = 0, totalCvr = 0, cvrCount = 0;
            let totalPrice = 0, priceCount = 0;
            let totalAmzLmp = 0, amzLmpCount = 0;

            data.forEach(row => {
                totalInv += parseFloat(row['inventory']) || 0;
                totalL30 += parseFloat(row['overall_l30']) || 0;
                const dil = parseFloat(row['dil_percent']) || 0;
                if (dil > 0) {
                    totalDil += dil;
                    dilCount++;
                }
                totalViews += parseInt(row['total_views']) || 0;
                totalCvr += parseFloat(row['avg_cvr']) || 0;
                cvrCount++;
                const price = parseFloat(row['avg_price']) || 0;
                if (price > 0) {
                    totalPrice += price;
                    priceCount++;
                }
                if (!row.is_parent_summary) {
                    const amzLmp = parseFloat(row['amazon_lmp_price']) || 0;
                    if (amzLmp > 0) {
                        totalAmzLmp += amzLmp;
                        amzLmpCount++;
                    }
                }
            });

            const avgDil = dilCount > 0 ? totalDil / dilCount : 0;
            const avgCvr = cvrCount > 0 ? totalCvr / cvrCount : 0;
            const avgPrice = priceCount > 0 ? totalPrice / priceCount : 0;
            const avgAmzLmp = amzLmpCount > 0 ? totalAmzLmp / amzLmpCount : 0;

            const skuParentFilter = $('#sku-parent-filter').val();
            const lqsRows = skuParentFilter === 'parent'
                ? data.filter(r => r.is_parent_summary === true)
                : data.filter(r => r.is_parent_summary !== true);
            let lqsSum = 0, lqsCount = 0;
            lqsRows.forEach(row => {
                const lqs = row.listing_quality_score;
                if (lqs == null || lqs === '') return;
                const num = typeof lqs === 'number' ? lqs : parseFloat(lqs);
                if (isNaN(num)) return;
                lqsSum += num;
                lqsCount++;
            });
            const avgLqs = lqsCount > 0 ? lqsSum / lqsCount : null;

            $('#total-inv-badge').text(totalInv.toLocaleString());
            $('#total-l30-badge').text(totalL30.toLocaleString());
            $('#avg-dil-badge').html('<span style="' + styleForCellColor(getDilPercentColor(avgDil)) + '">' + avgDil.toFixed(1) + '%</span>');
            $('#total-views-badge').text(totalViews.toLocaleString());
            $('#avg-cvr-badge').text(avgCvr.toFixed(1) + '%');
            $('#avg-price-badge').text('$' + avgPrice.toFixed(2));
            $('#amz-lmp-badge').text('$' + avgAmzLmp.toFixed(2));
            $('#avg-lqs-badge').text(avgLqs === null ? '-' : avgLqs.toFixed(1));

            const headerCb = document.querySelector('#cvr-table .select-all-cb');
            if (headerCb) headerCb.checked = isAllFilteredSelected();
        }

        // ==================== COLUMN VISIBILITY FUNCTIONS ====================

        // Permanently removed from Columns dropdown and kept hidden in the table
        const CVR_HIDDEN_FROM_COLUMN_MENU = [
            'amazon_price', 'amz_pft', 'amz_roi',
            'amazon_sprice', 'amazon_sgpft', 'amazon_spft', 'amazon_sroi',
            'amazon_lmp_price', 'ebay_lmp_price', 'google_lmp_price', 'temu_lmp_price',
            'shein_l30', 'ae_l30', 'pp_l30'
        ];
        
        function buildColumnDropdown() {
            const columns = table.getColumns();
            let html = '';

            columns.forEach(col => {
                const field = col.getField();
                const title = col.getDefinition().title;
                // Skip internal/select helper columns and permanently hidden Amz columns
                if (!field || !title || field === '_select') return;
                if (CVR_HIDDEN_FROM_COLUMN_MENU.indexOf(field) !== -1) return;
                const isVisible = col.isVisible();
                const label = String(title).replace(/<[^>]*>/g, '');
                html += `<li class="dropdown-item">
                    <label style="cursor: pointer; display: flex; align-items: center; gap: 8px;">
                        <input type="checkbox" class="column-toggle" data-field="${field}" ${isVisible ? 'checked' : ''}>
                        ${label}
                    </label>
                </li>`;
            });

            $('#column-dropdown-menu').html(html);
        }

        function forceHideRemovedCvrColumns() {
            CVR_HIDDEN_FROM_COLUMN_MENU.forEach(field => {
                const col = table.getColumn(field);
                if (col) col.hide();
            });
        }

        function saveColumnVisibilityToServer() {
            const visibility = {};
            table.getColumns().forEach(col => {
                const field = col.getField();
                if (field && field !== '_select') {
                    visibility[field] = col.isVisible();
                }
            });

            // Persists to channel_tabulator_column_settings (channel_name = pricing_master_cvr)
            $.ajax({
                url: '/cvr-master-column-visibility',
                method: 'POST',
                data: { visibility: visibility, _token: '{{ csrf_token() }}' }
            });
        }

        function applyColumnVisibilityFromServer() {
            $.ajax({
                url: '/cvr-master-column-visibility',
                method: 'GET',
                success: function(visibility) {
                    if (visibility && typeof visibility === 'object' && !Array.isArray(visibility)
                        && Object.keys(visibility).length > 0) {
                        Object.keys(visibility).forEach(field => {
                            if (CVR_HIDDEN_FROM_COLUMN_MENU.indexOf(field) !== -1) return;
                            const col = table.getColumn(field);
                            if (col) {
                                visibility[field] ? col.show() : col.hide();
                            }
                        });
                    }
                    forceHideRemovedCvrColumns();
                    buildColumnDropdown();
                },
                error: function() {
                    forceHideRemovedCvrColumns();
                    buildColumnDropdown();
                }
            });
        }

        // ==================== TABLE EVENTS ====================
        
        table.on('tableBuilt', function() {
            buildColumnDropdown();
            applyColumnVisibilityFromServer();
        });

        table.on('dataLoaded', function(data) {
            // Ignore dataLoaded triggered by local setData (dot view / parent view / restore)
            if (suppressDataLoadedHandler) {
                suppressDataLoadedHandler = false;
                return;
            }

            // Reorder so "10 FR" group is first, then others A-Z; within group children A-Z then parent row last
            const reordered = reorderDataWith10FRFirst(data);
            fullDataset = reordered;

            suppressDataLoadedHandler = true;
            table.setData(reordered).then(function() {
                applyFilters();
                updateSummary();
            });
        });

        table.on('renderComplete', function() {
            setTimeout(() => updateSummary(), 100);
        });

        document.getElementById("column-dropdown-menu").addEventListener("change", function(e) {
            if (e.target.classList.contains('column-toggle')) {
                const field = e.target.dataset.field;
                const col = table.getColumn(field);
                if (col) {
                    e.target.checked ? col.show() : col.hide();
                    saveColumnVisibilityToServer();
                }
            }
        });

        document.getElementById("show-all-columns-btn").addEventListener("click", function() {
            table.getColumns().forEach(col => col.show());
            buildColumnDropdown();
            saveColumnVisibilityToServer();
        });

        // ==================== EXPORT FUNCTIONS ====================
        
        $('#export-btn').on('click', function() {
            const exportData = [];
            const visibleColumns = table.getColumns().filter(col => col.isVisible());
            
            const headers = visibleColumns.map(col => {
                let title = col.getDefinition().title || col.getField();
                return title.replace(/<[^>]*>/g, '');
            });
            exportData.push(headers);
            
            const data = table.getData("active");
            data.forEach(row => {
                const rowData = [];
                visibleColumns.forEach(col => {
                    const field = col.getField();
                    let value = row[field];
                    
                    if (value === null || value === undefined) value = '';
                    else if (typeof value === 'number') value = parseFloat(value.toFixed(2));
                    else if (typeof value === 'string') value = value.replace(/<[^>]*>/g, '').trim();
                    
                    rowData.push(value);
                });
                exportData.push(rowData);
            });
            
            let csv = '';
            exportData.forEach(row => {
                csv += row.map(cell => {
                    if (typeof cell === 'string' && (cell.includes(',') || cell.includes('"') || cell.includes('\n'))) {
                        return '"' + cell.replace(/"/g, '""') + '"';
                    }
                    return cell;
                }).join(',') + '\n';
            });
            
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', 'cvr_master_export_' + new Date().toISOString().slice(0,10) + '.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            showToast('Export downloaded successfully!', 'success');
        });

        // ==================== PRICING MASTER ROLLING L30 CHARTS (SKU-wise: Inv, OV L30, Price, CVR) ====================
        let pricingMasterChartInstance = null;
        let currentPricingChartMetric = 'inv';
        let currentPricingChartSku = '';
        let currentPricingChartParent = '';
        let currentPricingChartAggregate = false;
        let currentPricingChartDays = 30;
        const pricingChartMetricLabels = { inv: 'Inv', ov_l30: 'OV L30', price: 'Price', cvr: 'CVR', dil: 'DIL', amz_price: 'Amz Price', rating: 'Rating', total_views: 'Total Views' };
        const pricingChartRangeLabel = (days) => 'L' + days;

        $(document).on('click', '.summary-chart-badge', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const metric = $(e.currentTarget).attr('data-metric') || $(e.currentTarget).data('metric');
            if (!metric) return;
            currentPricingChartMetric = metric;
            currentPricingChartSku = '';
            currentPricingChartParent = '';
            currentPricingChartAggregate = true;
            currentPricingChartDays = 30;
            $('#pricingMasterChartRangeSelect').val('30');
            const label = pricingChartMetricLabels[metric] || metric;
            $('#pricingMasterChartModalTitle').text('Master Analytics - All (Summary) - ' + label + ' (Rolling ' + pricingChartRangeLabel(30) + ')');
            $('#pricingMasterChartContainer').hide();
            $('#pricingMasterChartNoData').hide();
            $('#pricingMasterChartLoading').show();
            const modal = new bootstrap.Modal(document.getElementById('pricingMasterChartModal'));
            modal.show();
            loadPricingMasterChart();
        });

        $(document).on('click', '.pricing-master-chart-link', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const metric = $(e.currentTarget).attr('data-metric') || $(e.currentTarget).data('metric');
            const sku = String($(e.currentTarget).attr('data-sku') || $(e.currentTarget).data('sku') || '').trim();
            const parent = String($(e.currentTarget).attr('data-parent') || $(e.currentTarget).data('parent') || '').trim();
            if (!metric) return;
            currentPricingChartAggregate = false;
            const isParentChart = parent !== '' || (sku.indexOf('PARENT ') === 0);
            const displayName = isParentChart ? (parent || sku.replace(/^PARENT\s+/i, '')) : sku;
            if (!isParentChart && !sku) { showToast('SKU not found for chart', 'error'); return; }
            if (isParentChart) {
                currentPricingChartParent = parent || sku.replace(/^PARENT\s+/i, '').trim();
                currentPricingChartSku = '';
            } else {
                currentPricingChartParent = '';
                currentPricingChartSku = sku;
            }
            currentPricingChartMetric = metric;
            currentPricingChartDays = 30;
            $('#pricingMasterChartRangeSelect').val('30');
            const label = pricingChartMetricLabels[metric] || metric;
            $('#pricingMasterChartModalTitle').text('Master Analytics - ' + displayName + (isParentChart ? ' (Parent)' : '') + ' - ' + label + ' (Rolling ' + pricingChartRangeLabel(30) + ')');
            $('#pricingMasterChartContainer').hide();
            $('#pricingMasterChartNoData').hide();
            $('#pricingMasterChartLoading').show();
            const modal = new bootstrap.Modal(document.getElementById('pricingMasterChartModal'));
            modal.show();
            loadPricingMasterChart();
        });

        $(document).on('change', '#pricingMasterChartRangeSelect', function() {
            const days = parseInt($(this).val(), 10);
            if (days === currentPricingChartDays) return;
            currentPricingChartDays = days;
            $('#pricingMasterChartModalTitle').text($('#pricingMasterChartModalTitle').text().replace(/Rolling L\d+/, 'Rolling ' + pricingChartRangeLabel(days)));
            loadPricingMasterChart();
        });

        function loadPricingMasterChart() {
            $('#pricingMasterChartLoading').show();
            $('#pricingMasterChartContainer').hide();
            $('#pricingMasterChartNoData').hide();
            const payload = { metric: currentPricingChartMetric, days: currentPricingChartDays };
            if (currentPricingChartAggregate) {
                payload.aggregate = 1;
            } else if (currentPricingChartParent) {
                payload.parent = currentPricingChartParent;
            } else {
                payload.sku = currentPricingChartSku;
            }
            $.ajax({
                url: '/cvr-master-chart-data',
                method: 'GET',
                data: payload,
                success: function(response) {
                    $('#pricingMasterChartLoading').hide();
                    if (response.success && response.data && response.data.length > 0) {
                        $('#pricingMasterChartContainer').show();
                        renderPricingMasterChart(response.data);
                    } else {
                        $('#pricingMasterChartNoData').show();
                    }
                },
                error: function() {
                    $('#pricingMasterChartLoading').hide();
                    $('#pricingMasterChartNoData').show();
                }
            });
        }

        function renderPricingMasterChart(data) {
            const ctx = document.getElementById('pricingMasterChart');
            if (!ctx) return;
            if (pricingMasterChartInstance) {
                pricingMasterChartInstance.destroy();
                pricingMasterChartInstance = null;
            }
            const labels = data.map(d => d.date);
            const values = data.map(d => parseFloat(d.value) || 0);
            const dataMin = Math.min(...values);
            const dataMax = Math.max(...values);
            const sorted = [...values].sort((a, b) => a - b);
            const mid = Math.floor(sorted.length / 2);
            const median = sorted.length % 2 !== 0 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
            const range = dataMax - dataMin || 1;
            const yMin = Math.max(0, dataMin - range * 0.1);
            const yMax = dataMax + range * 0.1;
            const fmtVal = (v) => {
                if (currentPricingChartMetric === 'price' || currentPricingChartMetric === 'amz_price') return '$' + (Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
                if (currentPricingChartMetric === 'cvr' || currentPricingChartMetric === 'dil') return Number(v).toFixed(1) + '%';
                if (currentPricingChartMetric === 'rating') return Number(v).toFixed(1);
                if (currentPricingChartMetric === 'total_views') return Math.round(v).toLocaleString('en-US');
                return Math.round(v).toLocaleString('en-US');
            };
            $('#pricingMasterChartHighest').text(fmtVal(dataMax)).css('color', '#dc3545');
            $('#pricingMasterChartMedian').text(fmtVal(median)).css('color', '#6c757d');
            $('#pricingMasterChartLowest').text(fmtVal(dataMin)).css('color', '#198754');
            const dotColors = values.map((v, i) => {
                if (i === 0) return '#6c757d';
                return v > values[i - 1] ? '#28a745' : v < values[i - 1] ? '#dc3545' : '#6c757d';
            });
            const medianLinePlugin = {
                id: 'pricingMedianLine',
                afterDraw(chart) {
                    const yScale = chart.scales.y;
                    const xScale = chart.scales.x;
                    const ctx = chart.ctx;
                    const yPixel = yScale.getPixelForValue(median);
                    ctx.save();
                    ctx.setLineDash([6, 4]);
                    ctx.strokeStyle = '#6c757d';
                    ctx.lineWidth = 1.2;
                    ctx.beginPath();
                    ctx.moveTo(xScale.left, yPixel);
                    ctx.lineTo(xScale.right, yPixel);
                    ctx.stroke();
                    ctx.restore();
                }
            };
            const valueLabelsPlugin = {
                id: 'pricingValueLabels',
                afterDatasetsDraw(chart) {
                    const dataset = chart.data.datasets[0];
                    const meta = chart.getDatasetMeta(0);
                    const ctx = chart.ctx;
                    ctx.save();
                    ctx.font = 'bold 7px Inter, system-ui, sans-serif';
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'bottom';
                    meta.data.forEach((point, i) => {
                        const val = dataset.data[i];
                        const x = point.x;
                        const y = point.y;
                        const offsetY = (i % 2 === 0) ? -7 : -14;
                        ctx.fillStyle = val === 0 ? '#198754' : val > 0 ? '#dc3545' : '#6c757d';
                        ctx.fillText(fmtVal(val), x, y + offsetY);
                    });
                    ctx.restore();
                }
            };
            pricingMasterChartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: pricingChartMetricLabels[currentPricingChartMetric] || currentPricingChartMetric,
                        data: values,
                        backgroundColor: 'rgba(108,117,125,0.08)',
                        borderColor: '#adb5bd',
                        borderWidth: 1.5,
                        fill: true,
                        tension: 0.3,
                        pointRadius: 3,
                        pointHoverRadius: 5,
                        pointBackgroundColor: dotColors,
                        pointBorderColor: dotColors,
                        pointBorderWidth: 1.5
                    }]
                },
                plugins: [medianLinePlugin, valueLabelsPlugin],
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: { padding: { top: 18, left: 2, right: 2, bottom: 2 } },
                    plugins: { legend: { display: false } },
                    scales: {
                        y: {
                            min: yMin,
                            max: yMax,
                            ticks: { font: { size: 9 }, callback: function(value) { return fmtVal(value); } }
                        },
                        x: { ticks: { font: { size: 9 }, maxRotation: 45 } }
                    }
                }
            });
        }
    });
</script>
@endsection
