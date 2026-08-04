@extends('layouts.vertical', ['title' => 'Price Increase', 'sidenav' => 'condensed'])

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
        /* Outer main table: no column-expand grips (resize only inside OV L30 modal) */
        .tabulator .tabulator-header .tabulator-col-resize-handle {
            display: none !important;
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

        /* OV L30 Modal – compact, readable UI */
        #ovl30DetailsModal #modal-price-pct-btn {
            width: 30px;
            height: 28px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
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
        #ovl30DetailsModal .ovl30-prc-row-cb,
        #ovl30DetailsModal #ovl30-select-all-cb {
            width: 15px;
            height: 15px;
            cursor: pointer;
            margin: 0;
            vertical-align: middle;
        }
        #ovl30DetailsModal .modal-vertical-header th.ovl30-select-th,
        #ovl30DetailsModal .modal-vertical-header th.ovl30-bulk-push-th,
        #ovl30DetailsModal .modal-vertical-header th.ovl30-sprice-suggest-th {
            padding: 4px 2px !important;
            vertical-align: middle;
            text-align: center;
        }
        #ovl30DetailsModal .ovl30-bulk-push-btn {
            width: 24px;
            height: 22px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
        }
        #ovl30DetailsModal .modal-header {
            background: #f8fafc !important;
            color: #0f172a !important;
            border-bottom: 1px solid #e2e8f0 !important;
            padding: 0.55rem 0.85rem !important;
        }
        #ovl30DetailsModal .table thead {
            background-color: #5FA6AA !important;
            color: #fff !important;
            border-color: #4f969a !important;
        }
        /* Horizontal compact headers — teal from reference */
        #ovl30DetailsModal .modal-vertical-header th {
            writing-mode: horizontal-tb !important;
            text-orientation: mixed;
            white-space: nowrap;
            transform: none !important;
            height: auto !important;
            min-height: 0 !important;
            vertical-align: middle;
            font-size: 10px !important;
            font-weight: 700;
            letter-spacing: 0.01em;
            padding: 6px 4px !important;
            background-color: #5FA6AA !important;
            color: #fff !important;
            border-color: #4f969a !important;
            line-height: 1.15;
        }
        #ovl30DetailsModal .modal-vertical-header th span {
            color: #fff !important;
            display: inline;
        }
        #ovl30DetailsModal .modal-vertical-header th:nth-child(1) {
            writing-mode: horizontal-tb;
            transform: none;
            min-width: 72px;
            text-align: left !important;
        }
        #ovl30DetailsModal .ovl30-sprice-suggest-btn {
            padding: 2px 6px;
            line-height: 1;
            font-size: 11px;
        }
        #ovl30DetailsModal .ovl30-header-sku {
            font-size: 1.05rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.2;
        }
        #ovl30DetailsModal .ovl30-stat {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 8px;
            border-radius: 999px;
            background: #fff;
            border: 1px solid #e2e8f0;
            font-size: 12px;
            color: #475569;
            white-space: nowrap;
        }
        #ovl30DetailsModal .ovl30-stat.pricing-master-chart-link {
            cursor: pointer;
            user-select: none;
            transition: border-color .15s ease, box-shadow .15s ease, background .15s ease;
        }
        #ovl30DetailsModal .ovl30-stat.pricing-master-chart-link:hover {
            border-color: #94a3b8;
            background: #f8fafc;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
        }
        #ovl30DetailsModal .ovl30-stat strong {
            color: #0f172a;
            font-weight: 700;
        }
        #ovl30DetailsModal .ovl30-toolbar {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem 0.65rem;
            padding: 0.55rem 0.85rem;
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
        }
        #ovl30DetailsModal .ovl30-tool-group {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.2rem 0.45rem;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #f8fafc;
        }
        #ovl30DetailsModal .ovl30-tool-group label {
            margin: 0;
            font-size: 11px;
            font-weight: 700;
            color: #475569;
            white-space: nowrap;
        }
        #ovl30DetailsModal .ovl30-tool-group .form-control {
            height: 28px;
            font-size: 12px;
            padding: 2px 6px;
        }
        #ovl30DetailsModal .ovl30-tool-group .btn,
        #ovl30DetailsModal .ovl30-toolbar > .btn {
            height: 28px;
            font-size: 12px;
            padding: 2px 10px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
        }
        #ovl30DetailsModal .ovl30-toolbar .form-check {
            margin: 0;
            padding: 0.2rem 0.55rem 0.2rem 1.7rem;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #f8fafc;
            min-height: 0;
        }
        #ovl30DetailsModal .ovl30-toolbar .form-check-label {
            font-size: 11px;
            font-weight: 700;
            color: #475569;
        }
        #ovl30DetailsModal #modal-clear-all-sprice-btn {
            background: #fff;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }
        #ovl30DetailsModal #modal-clear-all-sprice-btn:hover {
            background: #fef2f2;
            color: #991b1b;
            border-color: #fca5a5;
        }
        #ovl30DetailsModal .ovl30-tool-group-sp {
            max-width: 420px;
        }
        #ovl30DetailsModal .ovl30-tool-group-sp .ovl30-sp-hint {
            font-size: 11px;
            line-height: 1.25;
            max-width: 220px;
        }
        #ovl30DetailsModal #modal-std-sp-input {
            font-weight: 700;
        }
        #ovl30DetailsModal .modal-footer {
            padding: 0.45rem 0.85rem;
            border-top: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        #ovl30DetailsModal #ovl30DetailsTable td:nth-child(1) {
            font-weight: 700 !important;
            color: #0f172a !important;
        }
        #ovl30DetailsModal #modal-header-lmp-link {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        /* Fixed column widths so header / totals / body stay aligned */
        #ovl30DetailsModal #ovl30DetailsTable col.ovl30-col-channel { width: 88px; }
        #ovl30DetailsModal #ovl30DetailsTable col.ovl30-col-num { width: 52px; }
        #ovl30DetailsModal #ovl30DetailsTable col.ovl30-col-price { width: 68px; }
        #ovl30DetailsModal #ovl30DetailsTable col.ovl30-col-std-prc { width: 64px; }
        #ovl30DetailsModal #ovl30DetailsTable col.ovl30-col-promo { width: 64px; }
        #ovl30DetailsModal #ovl30DetailsTable col.ovl30-col-lmp { width: 64px; }
        #ovl30DetailsModal .modal-vertical-header th.ovl30-std-prc-th {
            background: #20c997 !important;
            color: #000 !important;
        }
        #ovl30DetailsModal .modal-vertical-header th.ovl30-promo-th {
            background: #fd7e14 !important;
            color: #000 !important;
        }
        #ovl30DetailsModal .editable-std-prc,
        #ovl30DetailsModal .editable-promo {
            width: 4em !important;
            min-width: 0 !important;
            max-width: 100% !important;
            display: inline-block;
            padding: 1px 4px !important;
            font-size: inherit !important;
            height: auto !important;
            line-height: 1.3 !important;
            text-align: right;
        }
        #ovl30DetailsModal .ovl30-std-prc-wrap,
        #ovl30DetailsModal .ovl30-promo-wrap {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
        }
        #ovl30DetailsModal #modal-promo-input {
            font-weight: 700;
            width: 72px;
        }
        #ovl30DetailsModal #ovl30DetailsTable col.ovl30-col-link { width: 40px; }
        #ovl30DetailsModal #ovl30DetailsTable col.ovl30-col-check { width: 34px; }
        #ovl30DetailsModal #ovl30DetailsTable col.ovl30-col-icon { width: 34px; }
        #ovl30DetailsModal #ovl30DetailsTable col.ovl30-col-sprice { width: 72px; }
        #ovl30DetailsModal #ovl30DetailsTable col.ovl30-col-push { width: 36px; }
        #ovl30DetailsModal #ovl30DetailsTable col.ovl30-col-by { width: 36px; }
        #ovl30DetailsModal #ovl30DetailsTable col.ovl30-col-history { width: 52px; }
        .ovl30-history-btn {
            border: none;
            background: transparent;
            color: #5FA6AA;
            padding: 0 4px;
            font-size: 12px;
            line-height: 1;
            cursor: pointer;
        }
        .ovl30-history-btn:hover { color: #3d7f83; }
        .ovl30-history-btn.has-history { color: #0d6efd; }
        #ovl30PushHistoryModal {
            z-index: 1080;
        }
        #ovl30PushHistoryModal .table th,
        #ovl30PushHistoryModal .table td {
            font-size: 12px;
            vertical-align: middle;
        }
        #ovl30DetailsModal #ovl30DetailsTable th.ovl30-select-th,
        #ovl30DetailsModal #ovl30DetailsTable td.ovl30-select-cell,
        #ovl30DetailsModal #ovl30DetailsTable th.ovl30-sprice-suggest-th,
        #ovl30DetailsModal #ovl30DetailsTable th.ovl30-bulk-push-th,
        #ovl30DetailsModal #ovl30DetailsTable td.ovl30-push-cell,
        #ovl30DetailsModal #ovl30DetailsTable td.ovl30-link-cell,
        #ovl30DetailsModal #ovl30DetailsTable td.ovl30-by-cell {
            text-align: center !important;
            vertical-align: middle !important;
            padding-left: 2px !important;
            padding-right: 2px !important;
        }
        #ovl30DetailsModal #ovl30DetailsTable .ovl30-prc-row-cb,
        #ovl30DetailsModal #ovl30-select-all-cb {
            display: block;
            margin: 0 auto;
        }
        #ovl30DetailsModal #ovl30DetailsTable .editable-sprice {
            display: inline-block;
            margin: 0;
            vertical-align: middle;
        }
        #ovl30DetailsModal .ovl30-sprice-wrap {
            display: inline-flex;
            align-items: center;
            justify-content: flex-end;
            gap: 4px;
            width: 100%;
        }
        #ovl30DetailsModal .ovl30-sprice-lmp-alert {
            color: #dc3545;
            font-size: 11px;
            line-height: 1;
            cursor: help;
            flex-shrink: 0;
        }
        #ovl30DetailsModal #ovl30DetailsTable td.ovl30-num-cell,
        #ovl30DetailsModal #ovl30DetailsTable th.ovl30-num-th {
            text-align: right !important;
            font-variant-numeric: tabular-nums;
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
            background-color: #eef2ff !important;
            font-weight: 600 !important;
            font-size: var(--ovl30-fs, 11px) !important;
            color: #0f172a !important;
            border-top: 1px solid #c7d2fe !important;
        }
        #ovl30DetailsModal .modal-totals-row th {
            font-weight: 600 !important;
            font-size: var(--ovl30-fs, 11px) !important;
            line-height: 1.2;
            padding: 4px 3px !important;
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
            border: 0;
            box-shadow: none;
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
            padding: 0.35rem 0.65rem 0.5rem !important;
        }
        #ovl30DetailsModal .ovl30-table-wrap {
            --ovl30-fs: 11px;
            --ovl30-header-h: 34px;
            flex: 1 1 auto;
            min-height: 0;
            max-height: none;
            overflow-y: auto;
            overflow-x: auto;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #fff;
        }
        #ovl30DetailsModal .table tbody {
            background-color: #fff !important;
            color: #0f172a !important;
        }
        #ovl30DetailsModal .table tbody tr:nth-child(even) {
            background-color: #fafbfc !important;
        }
        #ovl30DetailsModal .table tbody tr:hover {
            background-color: #f1f5f9 !important;
        }
        #ovl30DetailsModal #ovl30DetailsTable {
            width: 100%;
            max-width: none;
            table-layout: fixed;
            margin-bottom: 0;
            border-color: #e2e8f0;
        }
        /* OV L30: column resize grips on header right edge */
        #ovl30DetailsModal .modal-vertical-header th {
            position: relative;
        }
        #ovl30DetailsModal .ovl30-col-resizer {
            position: absolute;
            top: 0;
            right: -3px;
            left: auto;
            width: 8px;
            height: 100%;
            cursor: ew-resize;
            z-index: 6;
            user-select: none;
            touch-action: none;
            background: transparent;
        }
        /* No visible gray grip marks — only a blue highlight while resizing */
        #ovl30DetailsModal .ovl30-col-resizer::before {
            content: none;
            display: none;
        }
        #ovl30DetailsModal .ovl30-col-resizer:hover,
        #ovl30DetailsModal .ovl30-col-resizer.is-resizing {
            background: rgba(13, 110, 253, 0.18);
        }
        #ovl30DetailsModal .modal-vertical-header th {
            border-color: #e2e8f0 !important;
            border-top: none !important;
        }
        #ovl30DetailsModal .table {
            border-collapse: collapse;
        }
        #ovl30DetailsModal .table > :not(caption) > * > * {
            border-bottom-width: 1px;
        }
        #ovl30DetailsModal.ovl30-cols-manual #ovl30DetailsTable {
            width: max-content;
            min-width: 100%;
        }
        body.ovl30-col-resizing {
            cursor: ew-resize !important;
            user-select: none !important;
        }
        #ovl30DetailsModal .table td {
            color: #334155 !important;
            padding: 4px 3px !important;
            font-size: var(--ovl30-fs) !important;
            font-weight: 600 !important;
            line-height: 1.25;
            vertical-align: middle;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            border-color: #eef2f6 !important;
        }
        /* Default center; numeric / channel override via cell classes */
        #ovl30DetailsModal .ovl30-table-wrap .table th,
        #ovl30DetailsModal .ovl30-table-wrap .table td {
            text-align: center;
        }
        #ovl30DetailsModal .ovl30-table-wrap .table th:nth-child(1),
        #ovl30DetailsModal .ovl30-table-wrap .table td:nth-child(1) {
            text-align: left !important;
            padding-left: 8px !important;
        }
        #ovl30DetailsModal .ovl30-table-wrap .table th:nth-child(2),
        #ovl30DetailsModal .ovl30-table-wrap .table td:nth-child(2),
        #ovl30DetailsModal .ovl30-table-wrap .table th:nth-child(4),
        #ovl30DetailsModal .ovl30-table-wrap .table td:nth-child(4),
        #ovl30DetailsModal .ovl30-table-wrap .table th:nth-child(5),
        #ovl30DetailsModal .ovl30-table-wrap .table td:nth-child(5),
        #ovl30DetailsModal .ovl30-table-wrap .table th:nth-child(6),
        #ovl30DetailsModal .ovl30-table-wrap .table td:nth-child(6),
        #ovl30DetailsModal .ovl30-table-wrap .table th:nth-child(7),
        #ovl30DetailsModal .ovl30-table-wrap .table td:nth-child(7),
        #ovl30DetailsModal .ovl30-table-wrap .table th:nth-child(8),
        #ovl30DetailsModal .ovl30-table-wrap .table td:nth-child(8),
        /* Right: GPFT→LMP (9–13), SPRICE→SPFT (18–22) — STD PRC is col 17 (before SPRICE) */
        #ovl30DetailsModal .ovl30-table-wrap .table th:nth-child(9),
        #ovl30DetailsModal .ovl30-table-wrap .table td:nth-child(9),
        #ovl30DetailsModal .ovl30-table-wrap .table th:nth-child(10),
        #ovl30DetailsModal .ovl30-table-wrap .table td:nth-child(10),
        #ovl30DetailsModal .ovl30-table-wrap .table th:nth-child(11),
        #ovl30DetailsModal .ovl30-table-wrap .table td:nth-child(11),
        #ovl30DetailsModal .ovl30-table-wrap .table th:nth-child(12),
        #ovl30DetailsModal .ovl30-table-wrap .table td:nth-child(12),
        #ovl30DetailsModal .ovl30-table-wrap .table th:nth-child(13),
        #ovl30DetailsModal .ovl30-table-wrap .table td:nth-child(13),
        #ovl30DetailsModal .ovl30-table-wrap .table th:nth-child(18),
        #ovl30DetailsModal .ovl30-table-wrap .table td:nth-child(18),
        #ovl30DetailsModal .ovl30-table-wrap .table th:nth-child(19),
        #ovl30DetailsModal .ovl30-table-wrap .table td:nth-child(19),
        #ovl30DetailsModal .ovl30-table-wrap .table th:nth-child(20),
        #ovl30DetailsModal .ovl30-table-wrap .table td:nth-child(20),
        #ovl30DetailsModal .ovl30-table-wrap .table th:nth-child(21),
        #ovl30DetailsModal .ovl30-table-wrap .table td:nth-child(21),
        #ovl30DetailsModal .ovl30-table-wrap .table th:nth-child(22),
        #ovl30DetailsModal .ovl30-table-wrap .table td:nth-child(22) {
            text-align: right !important;
            font-variant-numeric: tabular-nums;
        }
        /* Center: Miss, Link, Check, Suggest, STD PRC, Push, By */
        #ovl30DetailsModal .ovl30-table-wrap .table th:nth-child(3),
        #ovl30DetailsModal .ovl30-table-wrap .table td:nth-child(3),
        #ovl30DetailsModal .ovl30-table-wrap .table th:nth-child(14),
        #ovl30DetailsModal .ovl30-table-wrap .table td:nth-child(14),
        #ovl30DetailsModal .ovl30-table-wrap .table th:nth-child(15),
        #ovl30DetailsModal .ovl30-table-wrap .table td:nth-child(15),
        #ovl30DetailsModal .ovl30-table-wrap .table th:nth-child(16),
        #ovl30DetailsModal .ovl30-table-wrap .table td:nth-child(16),
        #ovl30DetailsModal .ovl30-table-wrap .table th:nth-child(17),
        #ovl30DetailsModal .ovl30-table-wrap .table td:nth-child(17),
        #ovl30DetailsModal .ovl30-table-wrap .table th:nth-child(23),
        #ovl30DetailsModal .ovl30-table-wrap .table td:nth-child(23),
        #ovl30DetailsModal .ovl30-table-wrap .table th:nth-child(24),
        #ovl30DetailsModal .ovl30-table-wrap .table td:nth-child(24) {
            text-align: center !important;
        }
        #ovl30DetailsModal #ovl30DetailsTable td:nth-child(13) .lmp-channel-price,
        #ovl30DetailsModal #ovl30DetailsTable td:nth-child(13) .lmp-add-btn {
            font-size: inherit;
            white-space: nowrap;
        }
        #ovl30DetailsModal .ovl30-table-wrap .table th.ovl30-std-prc-th,
        #ovl30DetailsModal .ovl30-table-wrap .table td.ovl30-std-prc-cell,
        #ovl30DetailsModal .ovl30-table-wrap .table th.ovl30-promo-th,
        #ovl30DetailsModal .ovl30-table-wrap .table td.ovl30-promo-cell {
            text-align: center !important;
        }
        #ovl30DetailsModal .table .editable-sprice {
            width: 4em !important;
            min-width: 0 !important;
            max-width: 100% !important;
            height: 24px;
            padding: 0 4px !important;
            font-size: var(--ovl30-fs) !important;
            font-weight: 600;
            text-align: center;
            margin: 0 auto;
            display: inline-block;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            -moz-appearance: textfield;
            appearance: textfield;
        }
        #ovl30DetailsModal .table .editable-sprice:focus {
            border-color: #93c5fd;
            outline: 0;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.15);
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
            background-color: #5FA6AA !important;
            box-shadow: 0 1px 0 0 #4f969a;
        }
        #ovl30DetailsModal .table thead .modal-totals-row th {
            position: sticky;
            top: var(--ovl30-header-h, 34px);
            z-index: 10;
            background-color: #e8f4f4 !important;
            box-shadow: 0 1px 0 0 #c5e0e1;
        }
        #ovl30DetailsModal .table thead .modal-vertical-header th:nth-child(1) {
            min-height: var(--ovl30-header-h, 34px);
        }
        /* Sortable column headers – cursor and sort icon */
        #ovl30DetailsModal .table thead .modal-vertical-header th.ovl30-sortable {
            cursor: pointer;
            user-select: none;
        }
        #ovl30DetailsModal .table thead .modal-vertical-header th.ovl30-sortable:hover {
            background-color: #4f969a !important;
            color: #fff !important;
        }
        #ovl30DetailsModal .table thead .modal-vertical-header th.ovl30-sortable:hover span {
            color: #fff !important;
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

        /* Chart modal stacks above OV L30 only when both are open (set via JS — do not raise all backdrops) */
        #pricingMasterChartModal.pi-chart-over-ovl30 { z-index: 1085; }

        #ovl30DetailsModal .table td:has(.push-price-btn),
        #ovl30DetailsModal .table td.ovl30-push-cell {
            width: 36px;
            max-width: 40px;
            padding: 2px !important;
        }
        #ovl30DetailsModal .ovl30-sprice-suggest-btn,
        #ovl30DetailsModal .ovl30-bulk-push-btn,
        #ovl30DetailsModal .push-price-btn {
            margin: 0 auto;
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

        /* LMP Competitors – right-side drawer (full height, ~48% width) */
        #lmpModal {
            z-index: 1065;
        }
        #lmpModal .modal-dialog {
            position: fixed;
            top: 0;
            right: 0;
            left: auto;
            margin: 0;
            width: 48vw;
            max-width: 48vw;
            min-width: 520px;
            height: 100vh;
            max-height: 100vh;
            transform: none;
        }
        @media (max-width: 768px) {
            #lmpModal .modal-dialog {
                width: 100vw;
                max-width: 100vw;
                min-width: 0;
            }
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
        #lmpModal tr.lmp-ignored-row {
            opacity: 0.55;
            background-color: #f1f3f5 !important;
        }
        #lmpModal tr.lmp-ignored-row .lmp-ps-cell,
        #lmpModal tr.lmp-ignored-row td {
            text-decoration: line-through;
        }
        #lmpModal tr.lmp-ignored-row td:last-child,
        #lmpModal tr.lmp-ignored-row td.text-center,
        #lmpModal tr.lmp-ignored-row .lmp-ignore-cb {
            text-decoration: none;
        }
        #lmpModal .lmp-ignore-cb {
            cursor: pointer;
            width: 16px;
            height: 16px;
        }
        /* Blue 5 Core reference row — same as /ebay-tabulator-view */
        #lmpModal .lmp-five-core-row,
        #lmpModal .lmp-five-core-row > td {
            background-color: #dbeafe !important;
            color: #1e3a8a;
            --bs-table-bg-type: #dbeafe;
            --bs-table-striped-bg: #dbeafe;
            --bs-table-hover-bg: #bfdbfe;
            font-weight: 600;
        }
        #lmpModal .lmp-five-core-row:hover > td {
            background-color: #bfdbfe !important;
        }
        #lmpModal .lmp-five-core-row .lmp-five-core-price {
            font-size: 14px;
            font-weight: 700;
        }
        #lmpModal .lmp-channel-icon.temu {
            background: #fb7701;
            color: #fff;
        }
        #lmpModal .lmp-channel-icon.aliexpress {
            background: #e43225;
            color: #fff;
            font-size: 9px;
            font-weight: 700;
        }
        #lmpModal .lmp-channel-icon.bestbuy {
            background: #0046be;
            color: #fff;
            font-size: 9px;
            font-weight: 700;
        }
        #lmpModal .lmp-channel-icon.macy {
            background: #e21a2c;
            color: #fff;
            font-size: 9px;
            font-weight: 700;
        }
        #lmpModal .lmp-channel-icon.reverb {
            background: #d9281d;
            color: #fff;
            font-size: 9px;
            font-weight: 700;
        }

        /* Parent expand icon - P column (glossy yellow play triangle) */
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
        .parent-sku-dot svg {
            width: 14px;
            height: 14px;
            display: block;
        }
        .parent-sku-dot:hover {
            filter: drop-shadow(0 2px 3px rgba(180, 110, 0, 0.45));
            transform: scale(1.08);
        }
        .parent-sku-dot.is-expanded {
            transform: rotate(90deg);
        }
        .parent-sku-dot.is-expanded:hover {
            transform: rotate(90deg) scale(1.08);
        }
        .parent-sku-dot.no-parent {
            cursor: default;
            opacity: 0.35;
            filter: grayscale(1) drop-shadow(none);
        }
        .parent-sku-dot.no-parent:hover {
            transform: none;
            filter: grayscale(1) drop-shadow(none);
        }

        /* SKU column — keep long SKUs inside the cell (do not overwrite Details) */
        .pricing-master-sku-wrap {
            display: flex;
            align-items: flex-start;
            gap: 6px;
            max-width: 100%;
            min-width: 0;
            overflow: hidden;
        }
        .pricing-master-sku-text {
            flex: 1 1 auto;
            min-width: 0;
            overflow: hidden;
            overflow-wrap: anywhere;
            word-break: break-word;
            white-space: normal;
            line-height: 1.25;
            cursor: default;
        }
        .pricing-master-sku-wrap .copy-sku-btn {
            flex: 0 0 auto;
            margin-left: 0 !important;
        }
        .tabulator .tabulator-cell.pricing-master-sku-col {
            overflow: hidden;
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

        /* Metric history modal — full width (theme uses --tz-modal-width / --tz-modal-margin) */
        #pricingMasterChartModal.modal {
            --tz-modal-width: 100%;
            --tz-modal-margin: 0.5rem 0;
            padding-left: 0 !important;
            padding-right: 0 !important;
        }
        #pricingMasterChartModal .modal-dialog {
            width: 100% !important;
            max-width: none !important;
            margin: 0.5rem 0 0 0 !important;
        }
        #pricingMasterChartModal .modal-content {
            border-radius: 0;
            width: 100%;
            max-width: 100%;
        }

        /* Label column (Shipping Master) — blue dot only; modal matches /sales-order-fulfillment */
        .tabulator .tabulator-header .tabulator-col.pi-label-col {
            background: #cfe2ff !important;
        }
        .pi-label-cell {
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .pi-label-dims-dot {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #6c8cff;
            box-shadow: 0 0 0 2px rgba(108, 140, 255, 0.22);
            cursor: pointer;
            flex-shrink: 0;
            vertical-align: middle;
            border: none;
            padding: 0;
        }
        .pi-label-dims-dot:hover {
            box-shadow: 0 0 0 3px rgba(108, 140, 255, 0.4);
        }
        #piLabelDimsModal .modal-dialog {
            max-width: min(1600px, 96vw);
            width: 96vw;
            margin: 1rem auto;
        }
        #piLabelDimsModal .modal-header {
            padding: 1rem 1.5rem;
            background: #cfe2ff;
            border-bottom-color: #9ec5fe;
        }
        #piLabelDimsModal .modal-title {
            font-size: 1.5rem;
            color: #084298;
        }
        #piLabelDimsModal .modal-body {
            padding: 1.5rem 1.75rem;
            font-size: 1.15rem;
        }
        #piLabelDimsModal .modal-footer {
            padding: 1rem 1.5rem;
        }
        #piLabelDimsModal .modal-footer .btn {
            font-size: 1rem;
            padding: 0.5rem 1.25rem;
        }
        #piLabelDimsModal .pi-label-sku-row {
            display: flex;
            align-items: flex-start;
            gap: 1.25rem;
            margin-bottom: 1.25rem;
            flex-wrap: wrap;
        }
        #piLabelDimsModal .pi-label-type-right {
            margin-left: auto;
            text-align: right;
            font-size: 2rem;
            line-height: 1.3;
            white-space: nowrap;
            padding-top: 0.15rem;
        }
        #piLabelDimsModal .pi-label-type-right .text-muted {
            font-size: 2rem;
            color: #64748b !important;
        }
        #piLabelDimsModal #pi-label-dims-type {
            font-size: 2rem;
            font-weight: 700;
        }
        #piLabelDimsModal .pi-label-sku-img {
            width: 120px;
            height: 120px;
            object-fit: contain;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            background: #f8f9fa;
            flex-shrink: 0;
        }
        #piLabelDimsModal .pi-label-sku-img.is-missing {
            display: flex;
            align-items: center;
            justify-content: center;
            color: #adb5bd;
            font-size: 0.85rem;
        }
        #piLabelDimsModal .pi-label-sku-meta {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            min-width: 0;
            flex: 1;
        }
        #piLabelDimsModal .pi-label-sku-line {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            flex-wrap: wrap;
        }
        #piLabelDimsModal .pi-label-sku-label {
            color: #64748b;
            font-size: 1rem;
        }
        #piLabelDimsModal #pi-label-dims-sku {
            font-size: 2.55rem;
            font-weight: 700;
            color: #0f766e;
            word-break: break-word;
            line-height: 1.2;
        }
        #piLabelDimsModal .pi-sku-copy {
            border: none;
            background: #f1f5f9;
            color: #475569;
            width: 36px;
            height: 36px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            flex-shrink: 0;
            padding: 0;
            font-size: 1rem;
        }
        #piLabelDimsModal .pi-sku-copy:hover {
            background: #e2e8f0;
            color: #0f172a;
        }
        #piLabelDimsModal .pi-sku-copy.copied {
            background: #d1e7dd;
            color: #0f5132;
        }
        #piLabelDimsModal .pi-label-dims-table {
            font-size: 1.15rem;
            margin-bottom: 0;
        }
        #piLabelDimsModal .pi-label-dims-table th,
        #piLabelDimsModal .pi-label-dims-table thead th,
        #piLabelDimsModal .pi-label-dims-table > :not(caption) > * > th {
            font-weight: 700;
            text-align: center;
            white-space: nowrap;
            font-size: 1rem;
            text-transform: lowercase;
            color: #000;
            vertical-align: middle;
            padding: 0.85rem 0.75rem;
            --bs-table-bg: #fff9c4;
            --bs-table-accent-bg: #fff9c4;
            background-color: #fff9c4 !important;
        }
        #piLabelDimsModal .pi-label-dims-table th.pi-dim-decl {
            --bs-table-bg: #f8d7da;
            --bs-table-accent-bg: #f8d7da;
            background-color: #f8d7da !important;
        }
        #piLabelDimsModal .pi-label-dims-table td {
            text-align: center;
            vertical-align: middle;
            padding: 1rem 0.75rem;
            font-size: 1.25rem;
        }
        /* CP$ / FRG / LP (Product Master) — blue header bar like PM */
        #piLabelDimsModal .pi-label-cost-table {
            font-size: 1.15rem;
            margin-bottom: 1rem;
        }
        #piLabelDimsModal .pi-label-cost-table th {
            font-weight: 700;
            text-align: center;
            white-space: nowrap;
            font-size: 1rem;
            text-transform: uppercase;
            color: #fff !important;
            vertical-align: middle;
            padding: 0.65rem 0.75rem;
            background: linear-gradient(180deg, #4a90d9 0%, #2c6fbb 100%) !important;
            border-color: #2c6fbb !important;
        }
        #piLabelDimsModal .pi-label-cost-table td {
            text-align: center;
            vertical-align: middle;
            padding: 0.85rem 0.75rem;
            font-size: 1.25rem;
            font-weight: 600;
        }

    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Price Increase',
        'sub_title' => 'Price Increase Data with Editable SPRICE',
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
                <div class="modal-header">
                    <div class="modal-title d-flex align-items-center justify-content-between w-100 gap-2 flex-wrap pe-2">
                        <div class="d-flex align-items-center gap-2 min-w-0">
                            <i class="fas fa-layer-group text-primary"></i>
                            <span id="modalSkuName" class="ovl30-header-sku text-truncate">SKU</span>
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="ovl30-stat pricing-master-chart-link" id="modal-header-inv-badge"
                                data-metric="inv" data-sku="" data-parent=""
                                title="View Inv graph (Rolling L30)">
                                INV <strong id="modal-header-inv">0</strong>
                            </span>
                            <span class="ovl30-stat pricing-master-chart-link" id="modal-header-l30-badge"
                                data-metric="ov_l30" data-sku="" data-parent=""
                                title="View OV L30 graph (Rolling L30)">
                                L30 <strong id="modal-header-l30">0</strong>
                            </span>
                            <span class="ovl30-stat pricing-master-chart-link" id="modal-header-dil-badge"
                                data-metric="dil" data-sku="" data-parent=""
                                title="View DIL history (Rolling L30)">
                                Dil% <strong id="modal-header-dil">0%</strong>
                            </span>
                            <span class="ovl30-tool-group py-0">
                                <label for="modal-group-select" class="mb-0">Group</label>
                                <select id="modal-group-select" class="form-select form-select-sm" style="width: auto; min-width: 64px; height: 28px; font-size: 12px;"
                                    title="A=Amazon+others (excludes Temu, Doba, B2B); D=Doba; T=Temu">
                                    <option value="">All</option>
                                    <option value="A">A</option>
                                    <option value="D">D</option>
                                    <option value="T">T</option>
                                </select>
                            </span>
                            <button type="button" id="modal-header-lmp-link" class="btn btn-sm btn-outline-primary"
                                title="View LMP competitors" style="height: 28px;">
                                <i class="fas fa-search"></i> LMP
                            </button>
                            <div class="btn-group">
                                <button type="button" id="modal-price-pct-btn" class="btn btn-sm btn-primary dropdown-toggle"
                                    data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false"
                                    title="Price change mode">
                                    <i class="fas fa-percent"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end" id="modal-price-pct-dropdown">
                                    <li><a class="dropdown-item" href="#" data-mode="decrease"><i class="fas fa-minus-circle text-warning"></i> Decrease</a></li>
                                    <li><a class="dropdown-item" href="#" data-mode="increase"><i class="fas fa-plus-circle text-primary"></i> Increase</a></li>
                                    <li><a class="dropdown-item" href="#" data-mode="same"><i class="fas fa-equals text-info"></i> Same Price</a></li>
                                    <li><a class="dropdown-item" href="#" data-mode="clear"><i class="fas fa-eraser text-danger"></i> Clear SPRICE</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="#" data-mode="cancel"><i class="fas fa-times text-muted"></i> Cancel</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div id="modal-discount-input-container" class="ovl30-toolbar" style="display: none;">
                    <span id="modal-selected-channels-count" class="fw-semibold small text-muted">(select channels)</span>
                    <span id="modal-discount-input-label" class="text-muted small">By how much:</span>
                    <span id="modal-discount-type-select-wrap">
                        <select id="modal-discount-type-select" class="form-select form-select-sm" style="width: 120px; height: 28px;">
                            <option value="percentage">Percentage</option>
                            <option value="value">Value ($)</option>
                        </select>
                    </span>
                    <input type="text" id="modal-discount-percentage-input" class="form-control form-control-sm"
                        inputmode="decimal" placeholder="e.g. 10 or 2.50" autocomplete="off" style="width: 110px; height: 28px;">
                    <button type="button" id="modal-apply-discount-btn" class="btn btn-sm btn-primary">
                        <i class="fas fa-check"></i> Apply
                    </button>
                    <button type="button" id="modal-select-all-channels-btn" class="btn btn-sm btn-outline-secondary">
                        Select all editable
                    </button>
                    <span class="ovl30-tool-group py-0">
                        <label for="modal-group-select-bar" class="mb-0">Group</label>
                        <select id="modal-group-select-bar" class="form-select form-select-sm" style="width: auto; min-width: 64px; height: 28px; font-size: 12px;"
                            title="A=Amazon+others (excludes Temu, Doba, B2B); D=Doba; T=Temu">
                            <option value="">All</option>
                            <option value="A">A</option>
                            <option value="D">D</option>
                            <option value="T">T</option>
                        </select>
                    </span>
                </div>
                {{-- Target ROI% / GPFT% — same back-solve as /doba-tabulator (uses each channel margin) --}}
                <div id="modal-target-controls" class="ovl30-toolbar">
                    <div class="ovl30-tool-group"
                        title="Apply this SPRICE to checked channels. Doba always −25%. SB2B / TopDawg / Faire = (SP × 0.80) − Ship. PPower = (SP × 1.15) − Ship.">
                        <label for="modal-bulk-sprice-input">SPRICE</label>
                        <input type="number" id="modal-bulk-sprice-input" class="form-control form-control-sm text-end"
                            placeholder="0.00" step="0.01" min="0" style="width: 78px;"
                            title="Doba −25%; SB2B/TopDawg/Faire = ×0.80 − Ship; PPower ×1.15 − Ship">
                        <button type="button" id="modal-apply-bulk-sprice-btn" class="btn btn-sm btn-primary"
                            title="Apply SPRICE" aria-label="Apply SPRICE">
                            Apply
                        </button>
                    </div>
                    <div class="ovl30-tool-group"
                        title="Target ROI% — SPRICE = (LP × (1 + ROI%/100) + Ship) / margin on selected channels">
                        <label for="modal-target-roi-input">ROI%</label>
                        <input type="number" id="modal-target-roi-input" class="form-control form-control-sm text-end"
                            placeholder="30" step="0.1" style="width: 58px;"
                            title="Target ROI% for selected channels">
                        <button type="button" id="modal-apply-target-roi-btn" class="btn btn-sm btn-success"
                            title="Apply Target ROI% to selected channels" aria-label="Apply Target ROI">
                            <i class="fas fa-calculator"></i>
                        </button>
                    </div>
                    <div class="ovl30-tool-group"
                        title="Target GPFT% — SPRICE = (LP + Ship) / (margin − GPFT%/100) on selected channels">
                        <label for="modal-target-gpft-input">GPFT%</label>
                        <input type="number" id="modal-target-gpft-input" class="form-control form-control-sm text-end"
                            placeholder="30" step="0.1" style="width: 58px;"
                            title="Target GPFT% for selected channels (must be &lt; channel margin %)">
                        <button type="button" id="modal-apply-target-gpft-btn" class="btn btn-sm btn-success"
                            title="Apply Target GPFT% to selected channels" aria-label="Apply Target GPFT">
                            <i class="fas fa-calculator"></i>
                        </button>
                    </div>
                    <div class="form-check"
                        title="When checked, the same SPRICE is saved on all child SKUs under the same parent (Temu↔Temu2 both channels). Preference is remembered for this SKU. Never applies 0.">
                        <input class="form-check-input" type="checkbox" id="modal-siblings-apply-cb">
                        <label class="form-check-label" for="modal-siblings-apply-cb">
                            Siblings
                            <span id="modal-siblings-count" class="text-muted fw-normal"></span>
                        </label>
                    </div>
                    <div class="ovl30-tool-group"
                        title="Add this $ amount to current SPRICE on selected channels (uses Price if SPRICE is empty). With Siblings checked, also applies to sibling SKUs including INV 0.">
                        <label for="modal-sprice-bump-input">+$ SPRICE</label>
                        <input type="number" id="modal-sprice-bump-input" class="form-control form-control-sm text-end"
                            placeholder="1" step="0.01" style="width: 58px;"
                            title="Increase SPRICE by this dollar amount on selected channels (+ siblings when checked, including INV 0)">
                        <button type="button" id="modal-apply-sprice-bump-btn" class="btn btn-sm btn-outline-primary"
                            title="Add $ to SPRICE on checked channels (and siblings if checked)" aria-label="Apply SPRICE increase">
                            Apply
                        </button>
                    </div>
                    <div class="ovl30-tool-group ovl30-tool-group-sp"
                        title="STD PRC for all marketplaces on this SKU. Saves to STD PRC column and Sku Link LMP siblings.">
                        <label for="modal-std-sp-input">STD PRC</label>
                        <input type="number" id="modal-std-sp-input" class="form-control form-control-sm text-end fw-bold"
                            placeholder="0.00" step="0.01" min="0.01" style="width: 72px;"
                            title="STD PRC for all marketplaces — same value on every channel row.">
                        <span class="ovl30-sp-hint text-muted">
                            <strong>STD PRC</strong> for all marketplaces (same as column). Also saves to Sku Link LMP siblings.
                        </span>
                    </div>
                    <div class="ovl30-tool-group"
                        title="Set this SPRICE on selected channels (often prefilled from STD PRC). Doba always −25%. SB2B / TopDawg / Faire = (STD × 0.80) − Ship. PPower = (STD × 1.15) − Ship.">
                        <label for="modal-sprice-same-input">SPRICE $</label>
                        <input type="number" id="modal-sprice-same-input" class="form-control form-control-sm text-end"
                            placeholder="19.99" step="0.01" min="0.01" style="width: 72px;"
                            title="Apply as SPRICE. Doba −25%; SB2B/TopDawg/Faire = ×0.80 − Ship; PPower ×1.15 − Ship">
                        <button type="button" id="modal-apply-sprice-same-btn" class="btn btn-sm btn-outline-success"
                            title="Apply SPRICE $" aria-label="Apply same SPRICE">
                            Apply
                        </button>
                    </div>
                    <div class="ovl30-tool-group"
                        title="Promotion for all marketplaces. 10% = SPRICE −10%. 10 = SPRICE − $10.">
                        <label for="modal-promo-input">Promotion</label>
                        <input type="text" id="modal-promo-input" class="form-control form-control-sm text-end fw-bold"
                            placeholder="10% or 10" title="10% = less 10% of SPRICE; 10 = less $10">
                        <button type="button" id="modal-apply-promo-btn" class="btn btn-sm btn-warning"
                            title="Apply Promotion to SPRICE on all listed channels" aria-label="Apply Promotion">
                            Apply
                        </button>
                    </div>
                    <button type="button" id="modal-clear-all-sprice-btn" class="btn btn-sm"
                        title="Clear SPRICE on every marketplace for this SKU (this SKU only — siblings never get 0)">
                        <i class="fas fa-eraser"></i> Clear All SPRICE
                    </button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive ovl30-table-wrap">
                        <table class="table table-bordered table-hover mb-0" id="ovl30DetailsTable">
                            <colgroup>
                                <col class="ovl30-col-channel">
                                <col class="ovl30-col-num">
                                <col class="ovl30-col-icon">
                                <col class="ovl30-col-price">
                                <col class="ovl30-col-promo">
                                <col class="ovl30-col-num">
                                <col class="ovl30-col-num">
                                <col class="ovl30-col-num">
                                <col class="ovl30-col-num">
                                <col class="ovl30-col-num">
                                <col class="ovl30-col-num">
                                <col class="ovl30-col-num">
                                <col class="ovl30-col-lmp">
                                <col class="ovl30-col-link">
                                <col class="ovl30-col-check">
                                <col class="ovl30-col-icon">
                                <col class="ovl30-col-std-prc">
                                <col class="ovl30-col-sprice">
                                <col class="ovl30-col-num">
                                <col class="ovl30-col-num">
                                <col class="ovl30-col-num">
                                <col class="ovl30-col-num">
                                <col class="ovl30-col-push">
                                <col class="ovl30-col-by">
                                <col class="ovl30-col-history">
                            </colgroup>
                            <thead>
                                <tr class="modal-vertical-header">
                                    <th class="ovl30-sortable" data-sort="marketplace" data-dir="asc" title="Marketplace"><span>Channel</span><i class="ovl30-sort-icon fas fa-sort ms-1"></i></th>
                                    <th class="ovl30-sortable" data-sort="l30" data-dir="desc" title="L30 Sold"><span>L30</span><i class="ovl30-sort-icon fas fa-sort ms-1"></i></th>
                                    <th title="Missing Listings – click dot to view channels with no price"><span>Miss</span></th>
                                    <th class="ovl30-sortable" data-sort="price" data-dir="desc" title="Price"><span>Price</span><i class="ovl30-sort-icon fas fa-sort ms-1"></i></th>
                                    <th class="ovl30-promo-th" title="Promotion — same value on all channels. 10% = SPRICE −10%. 10 = SPRICE − $10. Apply from toolbar."><span>Promotion</span></th>
                                    <th class="ovl30-sortable ovl30-metric-col" data-sort="views" data-dir="desc" title="Views"><span>Views</span><i class="ovl30-sort-icon fas fa-sort ms-1"></i></th>
                                    <th class="ovl30-sortable ovl30-metric-col" data-sort="cvr" data-dir="desc" title="CVR%"><span>CVR</span><i class="ovl30-sort-icon fas fa-sort ms-1"></i></th>
                                    <th class="ovl30-sortable ovl30-metric-col" data-sort="groi" data-dir="desc" title="GROI% = (Price × Margin − LP − Ship) ÷ LP × 100"><span>GROI</span><i class="ovl30-sort-icon fas fa-sort ms-1"></i></th>
                                    <th class="ovl30-sortable ovl30-metric-col" data-sort="gpft" data-dir="desc" title="GPFT%"><span>GPFT</span><i class="ovl30-sort-icon fas fa-sort ms-1"></i></th>
                                    <th class="ovl30-sortable ovl30-metric-col" data-sort="nroi" data-dir="desc" title="NROI% = (Gross Profit − Ads $) ÷ LP × 100"><span>NROI</span><i class="ovl30-sort-icon fas fa-sort ms-1"></i></th>
                                    <th class="ovl30-sortable ovl30-metric-col" data-sort="npft" data-dir="desc" title="NPFT%"><span>NPFT</span><i class="ovl30-sort-icon fas fa-sort ms-1"></i></th>
                                    <th class="ovl30-sortable ovl30-metric-col" data-sort="ad" data-dir="asc" title="Ads% from All Marketplace Master"><span>Ads</span><i class="ovl30-sort-icon fas fa-sort ms-1"></i></th>
                                    <th title="Lowest Marketplace Price"><span>LMP</span></th>
                                    <th title="Listing links"><span>Link</span></th>
                                    <th class="ovl30-select-th" title="Select channels (respects Group filter)">
                                        <input type="checkbox" id="ovl30-select-all-cb" title="Select as per filter">
                                    </th>
                                    <th class="ovl30-sprice-suggest-th" title="Auto Fill SPRICE using Dil%, CVR%, LMP &amp; Price">
                                        <button type="button" class="btn btn-sm btn-success ovl30-sprice-suggest-btn" title="Suggest SPRICE rules">
                                            <i class="fas fa-magic"></i>
                                        </button>
                                    </th>
                                    <th class="ovl30-std-prc-th" title="Standard Price (SP) — one value for all marketplaces on this SKU (amazon_data_view.STANDARD_PRICE). Editable on any channel row."><span>STD PRC</span></th>
                                    <th class="ovl30-sortable" data-sort="sprice" data-dir="desc" title="Suggested Price"><span>SPRICE</span><i class="ovl30-sort-icon fas fa-sort ms-1"></i></th>
                                    <th class="ovl30-sortable" data-sort="sroi" data-dir="desc" title="SROI%"><span>SROI</span><i class="ovl30-sort-icon fas fa-sort ms-1"></i></th>
                                    <th class="ovl30-sortable" data-sort="snroi" data-dir="desc" title="SNROI% = (SPRICE × Margin − LP − Ship − SPRICE × Ads%) ÷ LP × 100"><span>SNROI</span><i class="ovl30-sort-icon fas fa-sort ms-1"></i></th>
                                    <th class="ovl30-sortable" data-sort="sgpft" data-dir="desc" title="SGPFT%"><span>SGPFT</span><i class="ovl30-sort-icon fas fa-sort ms-1"></i></th>
                                    <th class="ovl30-sortable" data-sort="spft" data-dir="desc" title="SPFT%"><span>SPFT</span><i class="ovl30-sort-icon fas fa-sort ms-1"></i></th>
                                    <th class="ovl30-bulk-push-th" title="Bulk push SPRICE to selected / visible marketplaces">
                                        <button type="button" class="btn btn-sm btn-primary ovl30-bulk-push-btn" id="ovl30-bulk-push-btn"
                                            title="Bulk Push — pushes SPRICE marketplace-wise (selected channels, or all visible if none selected)">
                                            <i class="fas fa-upload"></i>
                                        </button>
                                    </th>
                                    <th title="Last pushed by"><span>By</span></th>
                                    <th title="Push history — date, price, user for this marketplace"><span>Hist</span></th>
                                </tr>
                                <tr class="modal-totals-row">
                                    <th><img id="modal-product-image" src="" alt="" style="width: 36px; height: 36px; object-fit: cover; border-radius: 4px; display: none;"></th>
                                    <th class="text-end" id="modal-total-l30">0</th>
                                    <th class="text-center">
                                        <span class="missing-l-dot" data-sku=""
                                            style="display:inline-block;width:12px;height:12px;border-radius:50%;background:#dc3545;cursor:pointer;border:1px solid #a00211;"
                                            title="View missing listings for this SKU"></span>
                                    </th>
                                    <th class="text-end">
                                        <span id="modal-total-price">$0.00</span>
                                        <i class="fas fa-circle pricing-master-chart-link ms-1" id="modal-price-chart-dot"
                                            data-metric="price" data-sku=""
                                            style="cursor:pointer;color:#e83e8c;font-size:8px;vertical-align:middle;"
                                            title="View Price history (Rolling L30)"></i>
                                    </th>
                                    <th class="ovl30-promo-th text-center">
                                        <input type="text" id="modal-promo-header-input" class="form-control form-control-sm text-end editable-promo"
                                            placeholder="10%" title="Promotion for all channels — same as Promo column / toolbar">
                                    </th>
                                    <th class="text-end ovl30-metric-col" id="modal-total-views">0</th>
                                    <th class="text-end ovl30-metric-col">
                                        <span id="modal-avg-cvr">0%</span>
                                        <i class="fas fa-circle pricing-master-chart-link ms-1" id="modal-cvr-chart-dot"
                                            data-metric="cvr" data-sku=""
                                            style="cursor:pointer;color:#ff9c00;font-size:8px;vertical-align:middle;"
                                            title="View CVR history (Rolling L30)"></i>
                                    </th>
                                    <th class="text-end ovl30-metric-col" id="modal-avg-groi">0%</th>
                                    <th class="text-end ovl30-metric-col" id="modal-avg-gpft">0%</th>
                                    <th class="text-end ovl30-metric-col" id="modal-avg-nroi">0%</th>
                                    <th class="text-end ovl30-metric-col" id="modal-avg-npft">0%</th>
                                    <th class="text-end ovl30-metric-col" id="modal-avg-ad">0%</th>
                                    <th></th>
                                    <th></th>
                                    <th></th>
                                    <th></th>
                                    <th class="ovl30-std-prc-th"></th>
                                    <th class="text-end" id="modal-avg-sprice">$0.00</th>
                                    <th class="text-end" id="modal-avg-sroi">0%</th>
                                    <th class="text-end" id="modal-avg-snroi">0%</th>
                                    <th class="text-end" id="modal-avg-sgpft">0%</th>
                                    <th class="text-end" id="modal-avg-spft">0%</th>
                                    <th></th>
                                    <th></th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="ovl30DetailsTableBody">
                                <!-- Table rows will be populated dynamically -->
                                <tr>
                                    <td colspan="25" class="text-center text-muted py-4">No data available</td>
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

    <!-- OV L30 Push History (per marketplace) -->
    <div class="modal fade" id="ovl30PushHistoryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-md modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header" style="background:#5FA6AA;color:#fff;">
                    <h6 class="modal-title mb-0">
                        <i class="fas fa-history me-2"></i>
                        Push History — <span id="ovl30PushHistoryMarketplace">Channel</span>
                        <span class="fw-normal opacity-75"> / <span id="ovl30PushHistorySku"></span></span>
                    </h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-2">
                    <div class="d-flex align-items-center justify-content-between gap-2 mb-2 flex-wrap">
                        <label class="small text-muted mb-0 d-flex align-items-center gap-1" for="ovl30PushHistoryDays">
                            Days
                            <select id="ovl30PushHistoryDays" class="form-select form-select-sm" style="width:auto;min-width:7rem;">
                                <option value="1">Today</option>
                                <option value="7" selected>Last 7 days</option>
                                <option value="30">Last 30 days</option>
                                <option value="90">Last 90 days</option>
                                <option value="0">All</option>
                            </select>
                        </label>
                        <span class="small text-muted" id="ovl30PushHistoryHint" title="Same-day rows where History date = SPRICE edit date = Push date are hidden">
                            Same-day edit+push rows hidden
                        </span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Marketplace</th>
                                    <th class="text-end">Price</th>
                                    <th>User</th>
                                </tr>
                            </thead>
                            <tbody id="ovl30PushHistoryBody">
                                <tr><td colspan="4" class="text-center text-muted py-3">No push history</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer py-1">
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
                    <div class="d-flex align-items-center gap-2 ms-auto">
                        <button type="button" id="lmpPullApiBtn" class="btn btn-sm btn-light"
                            title="Pull live LMP prices from SerpApi (Amazon / eBay / Google — same as pricing tabulators)">
                            <i class="fas fa-cloud-download-alt"></i> Pull
                        </button>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
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

    {{-- Label details modal (Shipping Master) — same as /sales-order-fulfillment --}}
    <div class="modal fade" id="piLabelDimsModal" tabindex="-1" aria-labelledby="piLabelDimsModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-semibold mb-0" id="piLabelDimsModalLabel">Label details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="pi-label-sku-row">
                        <img id="pi-label-dims-img" class="pi-label-sku-img d-none" alt="SKU image" src="">
                        <div id="pi-label-dims-img-missing" class="pi-label-sku-img is-missing">No image</div>
                        <div class="pi-label-sku-meta">
                            <div class="pi-label-sku-line">
                                <span class="pi-label-sku-label">SKU:</span>
                                <code id="pi-label-dims-sku">—</code>
                                <button type="button" class="pi-sku-copy" id="pi-label-dims-sku-copy" title="Copy SKU" aria-label="Copy SKU">
                                    <i class="fas fa-copy" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                        <div class="pi-label-type-right">
                            <span class="text-muted">Type:</span>
                            <span id="pi-label-dims-type" class="ms-1">—</span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <span class="fw-semibold">Label Qty:</span>
                        <span id="pi-label-dims-qty" class="ms-1">—</span>
                    </div>
                    <div class="table-responsive mb-3">
                        <table class="table table-bordered pi-label-cost-table mb-0">
                            <thead>
                                <tr>
                                    <th title="Cost Price (Product Master)">CP$</th>
                                    <th title="Freight — Product Master FRGHT (CBM × 200)">FRG</th>
                                    <th title="Landed Price (Product Master)">LP</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td id="pi-label-dims-cp">—</td>
                                    <td id="pi-label-dims-frg">—</td>
                                    <td id="pi-label-dims-lp">—</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered pi-label-dims-table">
                            <thead>
                                <tr>
                                    <th class="pi-dim-act">itm wt gw</th>
                                    <th class="pi-dim-act">item l in</th>
                                    <th class="pi-dim-act">item w in</th>
                                    <th class="pi-dim-act">item h in</th>
                                    <th class="pi-dim-decl">itm wt gw<br>decl</th>
                                    <th class="pi-dim-decl">item l in<br>decl</th>
                                    <th class="pi-dim-decl">item w in<br>decl</th>
                                    <th class="pi-dim-decl">item h in<br>decl</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td id="pi-label-dims-wt-act">—</td>
                                    <td id="pi-label-dims-l">—</td>
                                    <td id="pi-label-dims-w">—</td>
                                    <td id="pi-label-dims-h">—</td>
                                    <td id="pi-label-dims-wt-decl">—</td>
                                    <td id="pi-label-dims-l-decl">—</td>
                                    <td id="pi-label-dims-w-decl">—</td>
                                    <td id="pi-label-dims-h-decl">—</td>
                                </tr>
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

    <!-- Price Increase Rolling L30 Chart Modal (Inv, OV L30, Price, CVR) -->
    <div class="modal fade p-0" id="pricingMasterChartModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog shadow-none m-0 mx-0">
            <div class="modal-content" style="overflow: hidden;">
                <div class="modal-header bg-info text-white py-1 px-3">
                    <h6 class="modal-title mb-0" style="font-size: 13px;">
                        <i class="fas fa-chart-area me-1"></i>
                        <span id="pricingMasterChartModalTitle">Price Increase - Inv (Rolling L30)</span>
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
                        <p class="text-muted small mb-0"><strong>Refresh this page (Price Increase)</strong> once so today’s SKU data is saved, then open the graph again.</p>
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
                        <option value="both">Both (SKU + Parent)</option>
                        <option value="sku">SKU Only</option>
                        <option value="parent" selected>Parent Only</option>
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

                    {{-- Sprice Rule: SP = Amazon LMP × editable factor (default 0.98) --}}
                    <div class="d-inline-flex align-items-center gap-1 ms-2 p-1 border rounded"
                        id="pi-sprice-lmp-rule"
                        style="background: #ffc107;"
                        title="Sprice Rule — set SP = Amazon LMP × factor. Factor is editable (default 0.98). Applies to selected SKUs, or visible parents' children when none selected.">
                        <button type="button" id="pi-apply-sprice-lmp-rule-btn"
                            class="btn btn-sm btn-warning border-0 py-0 px-2 fw-bold text-dark"
                            style="background: transparent;">
                            <i class="fas fa-percentage"></i> Sprice Rule
                        </button>
                        <span class="small fw-bold text-dark text-nowrap">LMP ×</span>
                        <input type="number" id="pi-sprice-lmp-mult-input"
                            class="form-control form-control-sm text-end"
                            value="0.98" step="0.01" min="0.01" max="2"
                            style="width: 72px;"
                            title="Editable LMP multiplier — autofilled with 0.98">
                    </div>

                    {{-- Temu Clear/Push removed from outer toolbar — use OV L30 modal Temu push instead --}}
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

    {{-- Audit modal — same tables/APIs as /amz-cvr-issues --}}
    @include('market-places.partials.amz_cvr_audit_modals')
@endsection

@section('script-bottom')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="{{ asset('js/amz-cvr-audit.js') }}"></script>
<script>
    window.__authUserName = @json(optional(auth()->user())->name ?? optional(auth()->user())->email ?? 'You');
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

    /**
     * Temu / Temu2 push base from SPRICE (Price Increase):
     *   if SPRICE < $35 → (Sprice × 0.85) − 2.99
     *   if SPRICE ≥ $35 → (Sprice × 0.85)
     * PFT / ROI still use raw SPRICE / channel price — this is push/display conversion only.
     */
    function temuPushBaseFromSprice(sprice) {
        const s = parseFloat(sprice);
        if (!isFinite(s) || s <= 0) return null;
        const push = s < 35 ? ((s * 0.85) - 2.99) : (s * 0.85);
        if (!(push > 0)) return null;
        return +push.toFixed(2);
    }

    // ==================== LABEL DETAILS MODAL (Shipping Master) ====================

    function piLabelDimsDisplay(v) {
        if (v === null || v === undefined || v === '') {
            return '—';
        }
        return String(v);
    }

    function piCopyTextToClipboard(text, btn) {
        const value = (text || '').toString();
        if (!value) {
            return;
        }
        const done = function() {
            if (!btn) return;
            btn.classList.add('copied');
            btn.innerHTML = '<i class="fas fa-check" aria-hidden="true"></i>';
            setTimeout(function() {
                btn.classList.remove('copied');
                btn.innerHTML = '<i class="fas fa-copy" aria-hidden="true"></i>';
            }, 1200);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(value).then(done).catch(function() {
                window.prompt('Copy:', value);
            });
        } else {
            window.prompt('Copy:', value);
            done();
        }
    }

    function openPiLabelDimsModal(row) {
        const modalEl = document.getElementById('piLabelDimsModal');
        if (!modalEl || typeof bootstrap === 'undefined') {
            return;
        }
        const data = row || {};
        const sku = (data.sku || '').toString().trim();
        const skuEl = document.getElementById('pi-label-dims-sku');
        const typeEl = document.getElementById('pi-label-dims-type');
        const qtyEl = document.getElementById('pi-label-dims-qty');
        const imgEl = document.getElementById('pi-label-dims-img');
        const imgMissingEl = document.getElementById('pi-label-dims-img-missing');
        if (skuEl) skuEl.textContent = sku || '—';
        if (typeEl) typeEl.textContent = (data.label || '').toString().trim() || '—';
        if (qtyEl) qtyEl.textContent = piLabelDimsDisplay(data.label_qty);

        const imageUrl = (data.sku_image || data.image_path || '').toString().trim();
        if (imgEl && imgMissingEl) {
            if (imageUrl) {
                imgEl.src = imageUrl;
                imgEl.alt = sku ? ('Image for ' + sku) : 'SKU image';
                imgEl.classList.remove('d-none');
                imgMissingEl.classList.add('d-none');
                imgEl.onerror = function() {
                    imgEl.classList.add('d-none');
                    imgEl.removeAttribute('src');
                    imgMissingEl.classList.remove('d-none');
                };
            } else {
                imgEl.classList.add('d-none');
                imgEl.removeAttribute('src');
                imgMissingEl.classList.remove('d-none');
            }
        }

        // FRG = Product Master FRGHT (stored, else CBM×200 from ACT dims)
        let frg = data.frght;
        if (frg === null || frg === undefined || frg === '') {
            const l = parseFloat(data.l);
            const w = parseFloat(data.w);
            const h = parseFloat(data.h);
            if (isFinite(l) && isFinite(w) && isFinite(h)) {
                const cbm = (((l * 2.54) * (w * 2.54) * (h * 2.54)) / 1000000);
                frg = +(cbm * 200).toFixed(2);
            }
        }
        const lpVal = (data.lp != null && data.lp !== '')
            ? data.lp
            : (data.amazon_lp != null ? data.amazon_lp : '');

        const map = {
            'pi-label-dims-cp': data.cp,
            'pi-label-dims-frg': frg,
            'pi-label-dims-lp': lpVal,
            'pi-label-dims-wt-act': data.wt_act,
            'pi-label-dims-l': data.l,
            'pi-label-dims-w': data.w,
            'pi-label-dims-h': data.h,
            'pi-label-dims-wt-decl': data.wt_decl,
            'pi-label-dims-l-decl': data.l_decl,
            'pi-label-dims-w-decl': data.w_decl,
            'pi-label-dims-h-decl': data.h_decl,
        };
        Object.keys(map).forEach(function(id) {
            const el = document.getElementById(id);
            if (!el) return;
            const v = map[id];
            if (v === null || v === undefined || v === '') {
                el.textContent = '—';
                return;
            }
            // Money-ish fields: show 2 decimals when numeric
            if ((id === 'pi-label-dims-cp' || id === 'pi-label-dims-frg' || id === 'pi-label-dims-lp')
                && isFinite(parseFloat(v))) {
                el.textContent = parseFloat(v).toFixed(2);
                return;
            }
            el.textContent = piLabelDimsDisplay(v);
        });

        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }

    document.getElementById('pi-label-dims-sku-copy')?.addEventListener('click', function(ev) {
        ev.preventDefault();
        const skuEl = document.getElementById('pi-label-dims-sku');
        const sku = (skuEl?.textContent || '').trim();
        if (!sku || sku === '—') {
            return;
        }
        piCopyTextToClipboard(sku, ev.currentTarget);
    });
    
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

        let ovl30JustResizedCol = false;
        $(document).on('click', '#ovl30DetailsModal .modal-vertical-header th.ovl30-sortable', function(e) {
            // Ignore click after a column resize drag
            if ($(e.target).closest('.ovl30-col-resizer').length) return;
            if (ovl30JustResizedCol) return;
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
            const marketplace = $(this).data('marketplace'); // amazon | ebay | google | bestbuy | macy | reverb | temu
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

        let ovl30BreakdownXhr = null;
        let ovl30BreakdownReqId = 0;
        let ovl30ModalClosedByUser = false;

        function loadMarketplaceBreakdown(sku, imagePath, inv, l30, dil) {
            ovl30ModalClosedByUser = false;
            const reqId = ++ovl30BreakdownReqId;

            // Cancel any in-flight breakdown so closing/reopening doesn't stack "Loading..." loops
            if (ovl30BreakdownXhr && typeof ovl30BreakdownXhr.abort === 'function') {
                try { ovl30BreakdownXhr.abort(); } catch (e) { /* ignore */ }
                ovl30BreakdownXhr = null;
            }

            $('#modalSkuName').text(sku);
            // Wire totals-row Price / CVR chart dots + header INV/L30/Dil badges to this SKU
            const chartSku = String(sku || '').trim();
            const isParentSku = chartSku.toUpperCase().indexOf('PARENT ') === 0;
            const chartParent = isParentSku ? chartSku.replace(/^PARENT\s+/i, '').trim() : '';
            $('#modal-price-chart-dot, #modal-cvr-chart-dot')
                .attr('data-sku', chartSku)
                .attr('data-parent', chartParent);
            $('#modal-header-inv-badge, #modal-header-l30-badge, #modal-header-dil-badge')
                .attr('data-sku', chartSku)
                .attr('data-parent', chartParent)
                .attr('title', function() {
                    const metric = $(this).attr('data-metric') || '';
                    const labels = { inv: 'Inv', ov_l30: 'OV L30', dil: 'DIL' };
                    const label = labels[metric] || metric;
                    return 'View ' + label + ' graph' + (chartParent ? ' (Parent, Rolling L30)' : ' (Rolling L30)');
                });
            syncModalGroupSelects('');
            // Restore per-SKU siblings preference (saved when user ticks Siblings for this SKU)
            $('#modal-siblings-apply-cb').prop('checked', getSiblingsPrefForSku(sku));
            $('#modal-std-sp-input').val('');
            modalPromotionRaw = '';
            $('#modal-promo-input, #modal-promo-header-input, .editable-promo').val('');
            refreshModalSiblingSkus(sku);
            
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

            // Reuse existing instance — `new Modal().show()` on an already-open modal
            // stacks orphan .modal-backdrop layers and leaves a black screen after close/refresh.
            // Never re-show while the user is closing / already dismissed this open.
            const modalEl = document.getElementById('ovl30DetailsModal');
            if (modalEl && !ovl30ModalClosedByUser) {
                const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                if (!modalEl.classList.contains('show')) {
                    modal.show();
                }
            }
            
            // Fetch marketplace breakdown and FBA data
            ovl30BreakdownXhr = $.ajax({
                url: '/cvr-master-breakdown?sku=' + encodeURIComponent(sku),
                method: 'GET',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function(data) {
                    if (reqId !== ovl30BreakdownReqId || ovl30ModalClosedByUser) return;
                    if (!$('#ovl30DetailsModal').hasClass('show')) return;
                    renderMarketplaceData(data);
                },
                error: function(xhr, status) {
                    if (status === 'abort') return;
                    if (reqId !== ovl30BreakdownReqId || ovl30ModalClosedByUser) return;
                    if (!$('#ovl30DetailsModal').hasClass('show')) return;
                    showModalError('Failed to load data');
                },
                complete: function() {
                    if (reqId === ovl30BreakdownReqId) ovl30BreakdownXhr = null;
                }
            });
        }

        /** Remove leftover Bootstrap backdrops when no modal is open (fixes black screen). */
        function cleanupOrphanModalBackdrops() {
            if (document.querySelector('.modal.show')) return;
            document.querySelectorAll('.modal-backdrop').forEach(function(el) { el.remove(); });
            document.body.classList.remove('modal-open');
            document.body.style.removeProperty('overflow');
            document.body.style.removeProperty('padding-right');
        }

        // Deep-link from /ebay-tabulator-view LMP magnifying glass (and others):
        // /price-increase?sku=XXX&inv=&l30=&dil= → open same analytics modal once.
        // Clear ?sku= from the URL immediately so close/refresh does not keep reopening.
        (function openSkuBreakdownFromQuery() {
            try {
                const params = new URLSearchParams(window.location.search || '');
                const sku = String(params.get('sku') || params.get('openSku') || '').trim();
                if (!sku) return;
                const inv = parseInt(params.get('inv'), 10) || 0;
                const l30 = parseInt(params.get('l30'), 10) || 0;
                const dil = parseFloat(params.get('dil')) || 0;
                const image = params.get('image') || '';

                // Strip deep-link params BEFORE opening (prevents reopen loops on close/reload)
                if (window.history && window.history.replaceState) {
                    const url = new URL(window.location.href);
                    ['sku', 'openSku', 'inv', 'l30', 'dil', 'image'].forEach(function(k) {
                        url.searchParams.delete(k);
                    });
                    window.history.replaceState({}, '', url.pathname + (url.search || '') + (url.hash || ''));
                }

                let opened = false;
                function openOnce() {
                    if (opened) return;
                    opened = true;
                    loadMarketplaceBreakdown(sku, image, inv, l30, dil);
                }
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', openOnce, { once: true });
                } else {
                    // Defer one tick so Bootstrap/modal DOM is ready; still only once
                    setTimeout(openOnce, 0);
                }
            } catch (err) {
                console.warn('openSkuBreakdownFromQuery failed', err);
            }
        })();

        function showModalLoading(sku) {
            $('#ovl30DetailsTableBody').html(`
                <tr>
                    <td colspan="25" class="text-center text-muted py-4">
                        <div class="spinner-border spinner-border-sm text-info me-2" role="status"></div>
                        Loading data for ${sku}...
                    </td>
                </tr>
            `);
        }

        function showModalEmpty(sku) {
            $('#ovl30DetailsTableBody').html(`
                <tr>
                    <td colspan="25" class="text-center text-muted py-4">
                        No marketplace data available for ${sku}
                    </td>
                </tr>
            `);
        }

        function showModalError(message) {
            $('#ovl30DetailsTableBody').html(`
                <tr>
                    <td colspan="25" class="text-center text-danger py-4">
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
        let modalClearSpriceModeActive = false;
        let modalSelectedChannels = new Set();
        let modalSiblingSkus = []; // other SKUs sharing the same parent
        let modalPromotionRaw = ''; // shared Promo value (e.g. "10%" or "10")

        function getModalPrimarySku() {
            const sku = String($('#modalSkuName').text() || '').trim();
            if (!sku || sku.toUpperCase() === 'SKU' || sku.toUpperCase() === 'NOT LISTED') return '';
            return sku;
        }

        function isModalSiblingsApply() {
            return $('#modal-siblings-apply-cb').is(':checked');
        }

        // Persist Siblings checkbox per SKU so reopening the same SKU restores it
        const SIBLINGS_PREF_STORAGE_KEY = 'cvr_master_siblings_apply_by_sku_v1';
        function getSiblingsPrefMap() {
            try {
                const raw = localStorage.getItem(SIBLINGS_PREF_STORAGE_KEY);
                const map = raw ? JSON.parse(raw) : {};
                return (map && typeof map === 'object') ? map : {};
            } catch (e) {
                return {};
            }
        }
        function setSiblingsPrefForSku(sku, on) {
            sku = String(sku || '').trim();
            if (!sku) return;
            const key = sku.toUpperCase();
            const map = getSiblingsPrefMap();
            if (on) map[key] = 1;
            else delete map[key];
            try { localStorage.setItem(SIBLINGS_PREF_STORAGE_KEY, JSON.stringify(map)); } catch (e) { /* ignore */ }
        }
        function getSiblingsPrefForSku(sku) {
            sku = String(sku || '').trim();
            if (!sku) return false;
            return !!getSiblingsPrefMap()[sku.toUpperCase()];
        }

        function siblingsApplyPayload(price) {
            // Never apply 0 / empty to siblings — only real suggested prices
            if (price !== undefined && price !== null) {
                const n = parseFloat(price);
                if (!isFinite(n) || !(n > 0)) return 0;
            }
            return isModalSiblingsApply() ? 1 : 0;
        }

        /** Temu ↔ Temu2 pair for auto-apply */
        function temuPairedMarketplace(mp) {
            const m = String(mp || '').toLowerCase();
            if (m === 'temu') return 'temu2';
            if (m === 'temu2') return 'temu';
            return null;
        }

        /** Ebay1 ↔ Ebay2 ↔ Ebay3 family aliases */
        function ebayChannelFamily(mp) {
            const m = String(mp || '').toLowerCase();
            if (m === 'ebay' || m === 'ebay1' || m === 'ebayone') return 'ebay1';
            if (m === 'ebaytwo' || m === 'ebay2') return 'ebay2';
            if (m === 'ebaythree' || m === 'ebay3') return 'ebay3';
            return null;
        }

        /** Other eBay channels that share SPRICE with source */
        function ebayPairedMarketplaces(mp) {
            const family = ebayChannelFamily(mp);
            if (!family) return [];
            return ['ebay1', 'ebay2', 'ebay3'].filter(function(ch) { return ch !== family; });
        }

        function marketplaceMatchesEbayFamily(mp, family) {
            return ebayChannelFamily(mp) === family;
        }

        /** Mirror SPRICE into the paired Temu row in the open modal (UI only; backend already saved). */
        function syncTemuPairedSpriceInModal(sourceMp, sprice) {
            const other = temuPairedMarketplace(sourceMp);
            if (!other) return;
            const $otherRow = $('#ovl30DetailsTableBody tr').filter(function() {
                return String($(this).attr('data-marketplace') || '').toLowerCase() === other;
            }).first();
            if (!$otherRow.length) return;
            const $input = $otherRow.find('.editable-sprice');
            if (!$input.length) return;
            const display = (sprice > 0) ? Number(sprice).toFixed(2) : '';
            $input.val(display).attr('data-temu-synced', '1').trigger('input');
            ovl30ModalData.forEach(function(item) {
                if (String(item.marketplace || '').toLowerCase() === other) {
                    item.sprice = sprice > 0 ? sprice : 0;
                }
            });
        }

        /** Mirror SPRICE into Ebay1/2/3 paired rows in the open modal (UI only; backend already saved). */
        function syncEbayPairedSpriceInModal(sourceMp, sprice) {
            const others = ebayPairedMarketplaces(sourceMp);
            if (!others.length) return;
            const display = (sprice > 0) ? Number(sprice).toFixed(2) : '';
            others.forEach(function(family) {
                const $otherRow = $('#ovl30DetailsTableBody tr').filter(function() {
                    return marketplaceMatchesEbayFamily($(this).attr('data-marketplace'), family);
                }).first();
                if ($otherRow.length) {
                    const $input = $otherRow.find('.editable-sprice');
                    if ($input.length) {
                        $input.val(display).attr('data-ebay-synced', '1').trigger('input');
                    }
                }
                ovl30ModalData.forEach(function(item) {
                    if (marketplaceMatchesEbayFamily(item.marketplace, family)) {
                        item.sprice = sprice > 0 ? sprice : 0;
                    }
                });
            });
        }

        function siblingsApplyLabel() {
            if (!isModalSiblingsApply()) return '';
            const n = modalSiblingSkus.length;
            if (n > 0) {
                const preview = modalSiblingSkus.slice(0, 3).join(', ')
                    + (n > 3 ? (' +' + (n - 3) + ' more') : '');
                return ' (+' + n + ' siblings: ' + preview + ')';
            }
            return ' (+ siblings)';
        }

        function updateModalSiblingsCountUi() {
            const n = modalSiblingSkus.length;
            const $count = $('#modal-siblings-count');
            const $cb = $('#modal-siblings-apply-cb');
            if (n > 0) {
                $count.text('(' + n + ')').attr('title', modalSiblingSkus.join(', '));
                $cb.prop('disabled', false);
            } else {
                $count.text('(0)').attr('title', 'No sibling SKUs share this parent');
            }
        }

        function refreshModalSiblingSkus(sku) {
            modalSiblingSkus = [];
            updateModalSiblingsCountUi();
            sku = String(sku || getModalPrimarySku() || '').trim();
            if (!sku || sku.toUpperCase() === 'NOT LISTED') return;

            // Prefer in-memory master table rows (same parent)
            try {
                if (typeof fullDataset !== 'undefined' && Array.isArray(fullDataset)) {
                    const norm = String(sku).trim().toUpperCase();
                    const row = fullDataset.find(function(r) {
                        return !r.is_parent_summary && String(r.sku || '').trim().toUpperCase() === norm;
                    });
                    const parent = row && row.parent ? String(row.parent).trim() : '';
                    if (parent) {
                        const parentNorm = parent.toUpperCase();
                        // Include ALL siblings under parent — even INV 0
                        modalSiblingSkus = fullDataset
                            .filter(function(r) {
                                return !r.is_parent_summary
                                    && String(r.parent || '').trim().toUpperCase() === parentNorm
                                    && String(r.sku || '').trim().toUpperCase() !== norm;
                            })
                            .map(function(r) { return r.sku; });
                        updateModalSiblingsCountUi();
                    }
                }
            } catch (e) { /* ignore */ }

            $.ajax({
                url: '/cvr-master-siblings',
                method: 'GET',
                data: { sku: sku },
                success: function(res) {
                    if (res && res.success && Array.isArray(res.siblings)) {
                        // API is source of truth (includes INV 0 SKUs not on the filtered table)
                        modalSiblingSkus = res.siblings;
                        updateModalSiblingsCountUi();
                    }
                },
                error: function() {
                    // keep any in-memory siblings already found
                    updateModalSiblingsCountUi();
                }
            });
        }

        /** Save SPRICE for a specific SKU/marketplace (used for siblings, including INV 0). */
        function saveSpriceForSkuMarketplace(sku, marketplace, sprice, metrics, done) {
            metrics = metrics || {};
            $.ajax({
                url: '/cvr-master-save-suggested-data',
                method: 'POST',
                data: {
                    sku: sku,
                    marketplace: marketplace,
                    sprice: sprice,
                    sgpft: metrics.sgpft || 0,
                    spft: metrics.spft || 0,
                    sroi: metrics.sroi || 0,
                    amazon_margin: metrics.margin || 0.80,
                    apply_siblings: 0, // already iterating siblings ourselves
                    _token: '{{ csrf_token() }}'
                },
                success: function(res) { if (done) done(!!(res && (res.success !== false)), res); },
                error: function() { if (done) done(false); }
            });
        }

        /** Apply same SPRICE to every sibling SKU (includes INV 0). Temu↔Temu2 / Ebay1↔2↔3 channels. */
        function applySpriceToSiblingSkus(marketplace, sprice, metrics, done) {
            const list = (modalSiblingSkus || []).map(String).map(function(s) { return s.trim(); }).filter(Boolean);
            if (!list.length || !(sprice > 0) || !marketplace) {
                if (done) done(0);
                return;
            }
            const channels = [marketplace];
            const paired = temuPairedMarketplace(marketplace);
            if (paired) channels.push(paired);
            ebayPairedMarketplaces(marketplace).forEach(function(ch) { channels.push(ch); });
            let i = 0;
            let ok = 0;
            function next() {
                if (i >= list.length) {
                    if (done) done(ok);
                    return;
                }
                const sibSku = list[i++];
                let chIdx = 0;
                let sibOk = false;
                function nextChannel() {
                    if (chIdx >= channels.length) {
                        if (sibOk) ok++;
                        next();
                        return;
                    }
                    const ch = channels[chIdx++];
                    saveSpriceForSkuMarketplace(sibSku, ch, sprice, metrics, function(success) {
                        if (success) sibOk = true;
                        nextChannel();
                    });
                }
                nextChannel();
            }
            next();
        }

        $(document).on('change', '#modal-siblings-apply-cb', function() {
            const sku = getModalPrimarySku();
            const on = $(this).is(':checked');
            if (!sku) {
                if (on) {
                    showToast('Open a SKU details modal first', 'error');
                    $(this).prop('checked', false);
                }
                return;
            }
            // Remember preference for this SKU (restored next time the modal opens)
            setSiblingsPrefForSku(sku, on);
            if (!on) return;
            if (!modalSiblingSkus.length) {
                // Re-fetch once in case count was stale
                refreshModalSiblingSkus(sku);
                setTimeout(function() {
                    if (!modalSiblingSkus.length) {
                        showToast('No sibling SKUs found for this parent', 'error');
                    } else {
                        showToast('Siblings Apply ON — saved for ' + sku + ' (+' + modalSiblingSkus.length + ' sibling(s))', 'success');
                    }
                }, 400);
                return;
            }
            showToast('Siblings Apply ON — saved for ' + sku + ' (+' + modalSiblingSkus.length + ' sibling(s))', 'success');
        });
        // Group: A = Amazon + others (exclude Temu, B2B); Doba is in A so SP Apply can hit it (−25%).
        // D = Doba-only; T = Temu
        const MODAL_GROUP_EXCLUDE_FROM_A = ['temu', 'temu2', 'sb2b', 'shopifyb2b', 'shopify_b2b'];
        const MODAL_GROUP_CHANNELS = {
            D: ['doba'],
            T: ['temu', 'temu2'],
        };

        function getModalGroupValue() {
            return String($('#modal-group-select').val() || '');
        }

        function syncModalGroupSelects(group) {
            const val = group == null ? getModalGroupValue() : String(group || '');
            $('#modal-group-select, #modal-group-select-bar').val(val);
        }

        function marketplaceInModalGroup(mp, group) {
            if (!group) return true;
            const key = String(mp || '').toLowerCase().replace(/\s+/g, '');
            if (group === 'A') {
                return key !== '' && !MODAL_GROUP_EXCLUDE_FROM_A.includes(key);
            }
            const list = MODAL_GROUP_CHANNELS[group] || [];
            return list.includes(key);
        }

        function getVisibleModalRowCbs() {
            return $('#ovl30DetailsTableBody tr:visible .ovl30-prc-row-cb');
        }

        function syncOvl30SelectAllHeader() {
            const $cbs = getVisibleModalRowCbs();
            const total = $cbs.length;
            const checked = $cbs.filter(':checked').length;
            const $all = $('#ovl30-select-all-cb');
            if (!$all.length) return;
            if (!total) {
                $all.prop({ checked: false, indeterminate: false });
                return;
            }
            $all.prop('checked', checked === total);
            $all.prop('indeterminate', checked > 0 && checked < total);
        }

        /** Filter channel rows by Group. Pass { selectChannels: true } to also check matching boxes. */
        function applyModalGroupFilter(opts) {
            const selectChannels = !!(opts && opts.selectChannels);
            const group = getModalGroupValue();
            syncModalGroupSelects(group);

            $('#ovl30DetailsTableBody tr[data-marketplace]').each(function() {
                const mp = $(this).attr('data-marketplace');
                $(this).toggle(marketplaceInModalGroup(mp, group));
            });

            if (selectChannels && group) {
                modalSelectedChannels.clear();
                $('#ovl30DetailsTableBody .ovl30-prc-row-cb').each(function() {
                    const mp = String($(this).attr('data-marketplace') || '');
                    const match = marketplaceInModalGroup(mp, group);
                    $(this).prop('checked', match);
                    if (match && mp) modalSelectedChannels.add(mp);
                });
                updateModalPrcSelectedCount();
            }
            syncOvl30SelectAllHeader();
        }

        function updateModalPrcSelectedCount() {
            const n = modalSelectedChannels.size;
            $('#modal-selected-channels-count').text(
                n > 0 ? `(${n} channel${n > 1 ? 's' : ''} selected)` : '(select channels)'
            );
            syncOvl30SelectAllHeader();
        }

        function exitModalPricePctMode(rerender) {
            modalPrcModeActive = false;
            modalDecreaseModeActive = false;
            modalIncreaseModeActive = false;
            modalSamePriceModeActive = false;
            modalClearSpriceModeActive = false;
            modalSelectedChannels.clear();
            $('#ovl30-select-all-cb').prop({ checked: false, indeterminate: false });
            $('#modal-discount-input-container').hide();
            $('#modal-price-pct-btn').removeClass('btn-danger btn-warning btn-success btn-info').addClass('btn-primary')
                .attr('title', 'Prc Mode')
                .html('<i class="fas fa-percent"></i>');
            $('#modal-apply-discount-btn').html('<i class="fas fa-check"></i> Apply');
            $('#modal-discount-type-select-wrap').show();
            $('#modal-discount-input-label').text('By how much:');
            $('#modal-discount-percentage-input').val('').show().attr('placeholder', 'e.g. 10 or 2.50');
            if (rerender !== false && ovl30ModalData.length) {
                renderMarketplaceData();
            } else {
                $('#ovl30DetailsTableBody .ovl30-prc-row-cb').prop('checked', false);
                syncOvl30SelectAllHeader();
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
            modalClearSpriceModeActive = (mode === 'clear');
            modalSelectedChannels.clear();
            $('#modal-discount-input-container').show();
            $('#modal-discount-percentage-input').val('');
            updateModalPrcSelectedCount();

            if (mode === 'decrease') {
                $('#modal-discount-type-select-wrap').show();
                $('#modal-discount-percentage-input').show();
                $('#modal-discount-input-label').text('By how much:');
                $('#modal-discount-percentage-input').attr('placeholder', 'e.g. 10 or 2.50');
                $('#modal-price-pct-btn').removeClass('btn-primary btn-success btn-info btn-danger').addClass('btn-warning')
                    .attr('title', 'Decrease')
                    .html('<i class="fas fa-minus-circle"></i>');
                $('#modal-apply-discount-btn').html('<i class="fas fa-check"></i> Apply Decrease');
            } else if (mode === 'increase') {
                $('#modal-discount-type-select-wrap').show();
                $('#modal-discount-percentage-input').show();
                $('#modal-discount-input-label').text('By how much:');
                $('#modal-discount-percentage-input').attr('placeholder', 'e.g. 10 or 2.50');
                $('#modal-price-pct-btn').removeClass('btn-primary btn-warning btn-info btn-danger').addClass('btn-success')
                    .attr('title', 'Increase')
                    .html('<i class="fas fa-plus-circle"></i>');
                $('#modal-apply-discount-btn').html('<i class="fas fa-check"></i> Apply Increase');
            } else if (mode === 'same') {
                $('#modal-discount-type-select-wrap').hide();
                $('#modal-discount-percentage-input').show();
                $('#modal-discount-input-label').text('Same Price ($):');
                $('#modal-discount-percentage-input').attr('placeholder', 'Enter price (e.g. 19.99)');
                $('#modal-price-pct-btn').removeClass('btn-primary btn-warning btn-success btn-danger').addClass('btn-info')
                    .attr('title', 'Same Price')
                    .html('<i class="fas fa-equals"></i>');
                $('#modal-apply-discount-btn').html('<i class="fas fa-check"></i> Apply Same Price');
            } else if (mode === 'clear') {
                $('#modal-discount-type-select-wrap').hide();
                $('#modal-discount-percentage-input').hide();
                $('#modal-discount-input-label').text('Clear SPRICE on selected channels');
                $('#modal-price-pct-btn').removeClass('btn-primary btn-warning btn-success btn-info').addClass('btn-danger')
                    .attr('title', 'Clear SPRICE')
                    .html('<i class="fas fa-eraser"></i>');
                $('#modal-apply-discount-btn').html('<i class="fas fa-eraser"></i> Clear SPRICE');
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

        // v2: invalidate old vertical-header widths that break horizontal alignment
        const OVL30_COL_WIDTHS_KEY = 'priceIncrease_ovl30_col_widths_v3';
        let ovl30ManualColWidths = false;
        try {
            localStorage.removeItem('priceIncrease_ovl30_col_widths');
            localStorage.removeItem('priceIncrease_ovl30_col_widths_v2');
        } catch (e) {}

        function loadOvl30SavedColWidths() {
            try {
                const raw = localStorage.getItem(OVL30_COL_WIDTHS_KEY);
                if (!raw) return null;
                const parsed = JSON.parse(raw);
                return (parsed && typeof parsed === 'object') ? parsed : null;
            } catch (e) { return null; }
        }
        function saveOvl30ColWidths(map) {
            try { localStorage.setItem(OVL30_COL_WIDTHS_KEY, JSON.stringify(map || {})); } catch (e) {}
        }
        function applyOvl30ColWidths(map) {
            if (!map) return;
            const $ths = $('#ovl30DetailsTable thead tr.modal-vertical-header th');
            $ths.each(function(idx) {
                const w = parseInt(map[idx], 10);
                if (!w || w < 24) return;
                $(this).css({ width: w + 'px', minWidth: w + 'px', maxWidth: w + 'px' });
                $('#ovl30DetailsTable tbody td:nth-child(' + (idx + 1) + '), #ovl30DetailsTable thead tr.modal-totals-row th:nth-child(' + (idx + 1) + ')')
                    .css({ width: w + 'px', minWidth: w + 'px', maxWidth: w + 'px' });
            });
        }
        function collectOvl30ColWidths() {
            const map = {};
            $('#ovl30DetailsTable thead tr.modal-vertical-header th').each(function(idx) {
                const w = Math.round($(this).outerWidth());
                if (w > 0) map[idx] = w;
            });
            return map;
        }
        function setOvl30ManualWidthMode(on) {
            ovl30ManualColWidths = !!on;
            $('#ovl30DetailsModal').toggleClass('ovl30-cols-manual', ovl30ManualColWidths);
            const tableEl = document.getElementById('ovl30DetailsTable');
            if (!tableEl) return;
            if (ovl30ManualColWidths) {
                tableEl.style.width = 'max-content';
                tableEl.style.minWidth = '100%';
                tableEl.style.tableLayout = 'fixed';
            }
        }
        function applyWidthToOvl30Col(colIdx, newW) {
            const $th = $('#ovl30DetailsTable thead tr.modal-vertical-header th').eq(colIdx);
            $th.css({ width: newW + 'px', minWidth: newW + 'px', maxWidth: newW + 'px' });
            $('#ovl30DetailsTable tbody td:nth-child(' + (colIdx + 1) + '), #ovl30DetailsTable thead tr.modal-totals-row th:nth-child(' + (colIdx + 1) + ')')
                .css({ width: newW + 'px', minWidth: newW + 'px', maxWidth: newW + 'px' });
        }

        function initOvl30ColResizers() {
            $('#ovl30DetailsTable thead tr.modal-vertical-header th').each(function() {
                const $th = $(this);
                if ($th.find('.ovl30-col-resizer').length) return;
                $th.append('<div class="ovl30-col-resizer" title="Drag to resize column"></div>');
            });
        }

        // Drag ◂▸ grips on OV L30 headers (same look/feel as Tabulator)
        (function bindOvl30ColResize() {
            let resizing = false;
            let startX = 0;
            let startW = 0;
            let colIdx = -1;

            $(document).on('mousedown', '#ovl30DetailsModal .ovl30-col-resizer', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const $th = $(this).closest('th');
                resizing = true;
                startX = e.pageX;
                startW = $th.outerWidth();
                colIdx = $th.index();
                $(this).addClass('is-resizing');
                $('body').addClass('ovl30-col-resizing');
                setOvl30ManualWidthMode(true);
            });
            $(document).on('mousemove.ovl30Resize', function(e) {
                if (!resizing || colIdx < 0) return;
                applyWidthToOvl30Col(colIdx, Math.max(28, Math.round(startW + (e.pageX - startX))));
            });
            $(document).on('mouseup.ovl30Resize', function() {
                if (!resizing) return;
                resizing = false;
                $('body').removeClass('ovl30-col-resizing');
                $('#ovl30DetailsModal .ovl30-col-resizer').removeClass('is-resizing');
                saveOvl30ColWidths(collectOvl30ColWidths());
                ovl30JustResizedCol = true;
                setTimeout(function() { ovl30JustResizedCol = false; }, 0);
                colIdx = -1;
            });
        })();

        /** Largest font size that fits all columns in the modal width, then stretch table to 100%.
         *  Skipped when user has manually resized columns (Tabulator-like widths win). */
        function autoFitOvl30TableFont() {
            const wrap = document.querySelector('#ovl30DetailsModal .ovl30-table-wrap');
            const tableEl = document.getElementById('ovl30DetailsTable');
            if (!wrap || !tableEl || !$('#ovl30DetailsModal').hasClass('show')) return;

            initOvl30ColResizers();

            // Restore / keep manual widths — do not crush them with autofit
            const saved = loadOvl30SavedColWidths();
            if (ovl30ManualColWidths || (saved && Object.keys(saved).length)) {
                setOvl30ManualWidthMode(true);
                applyOvl30ColWidths(saved || collectOvl30ColWidths());
                return;
            }

            const avail = wrap.clientWidth;
            if (avail < 80) return;

            const applyFs = (fs) => {
                wrap.style.setProperty('--ovl30-fs', fs + 'px');
            };

            // Measure natural width at each font size; pick largest that fits screen
            tableEl.style.width = 'max-content';
            tableEl.style.tableLayout = 'auto';

            let best = 8;
            for (let fs = 8; fs <= 14; fs += 0.5) {
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
            // Sticky totals offset = actual horizontal header height
            const headerRow = tableEl.querySelector('thead tr.modal-vertical-header');
            const headerH = headerRow ? Math.ceil(headerRow.getBoundingClientRect().height) : 34;
            wrap.style.setProperty('--ovl30-header-h', Math.max(28, headerH) + 'px');
            initOvl30ColResizers();
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
            const sorted = toRender.slice().filter(function(item) {
                const mp = String(item.marketplace || '').toLowerCase();
                // Walmart / Tiendamia removed from /price-increase
                return mp !== 'walmart' && mp !== 'tiendamia';
            });
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
                // Show all channels; mute / dash metrics when not listed (do not hide)
                const isListed = !(item.is_listed === false || item.is_listed === 0
                    || item.is_listed === '0' || item.is_listed === 'false');
                const rowClass = !isListed ? 'table-secondary' : '';
                const textClass = !isListed ? 'text-muted fst-italic' : '';
                
                // Calculate CVR% (L30 / Views * 100). null/undefined views → N/A (channel has no view data)
                const viewsMissing = item.views === null || item.views === undefined || item.views === '';
                const views = viewsMissing ? 0 : (parseInt(item.views, 10) || 0);
                const l30 = parseInt(item.l30 || 0);
                const cvr = (!viewsMissing && views > 0) ? (l30 / views) * 100 : 0;
                const gpft = parseFloat(item.gpft || 0);
                const ad = parseFloat(item.ad || 0);
                const mpLower = (item.marketplace || '').toLowerCase();
                const isDobaMp = mpLower === 'doba';
                const isReverbMp = mpLower === 'reverb';
                const isEbayMp = ['ebay', 'ebay1', 'ebaytwo', 'ebay2', 'ebaythree', 'ebay3'].includes(mpLower);
                const isPpMp = mpLower === 'ppower' || mpLower === 'purchasingpower' || mpLower === 'purchase';
                const isTdMp = mpLower === 'topdawg';
                const isSheinMp = mpLower === 'shein';
                const isFaireMp = mpLower === 'faire';
                const isTiktok2Mp = mpLower === 'tiktok2' || mpLower === 'tiktok 2';
                // Doba/PPower/TopDawg/Shein: Ads% = 0; Reverb/eBay use channel Ads% (pricing tabulators)
                const isNoAdsMp = isDobaMp || isPpMp || isTdMp || isSheinMp || isFaireMp || isTiktok2Mp;
                // Temu: every row uses aggregate Ads% (~2.6%) — same as /temu-decrease badgeAvgAds
                const isTemuMpRow = mpLower === 'temu';
                const isTemu2MpRow = mpLower === 'temu2';
                const tacosCh = (isNoAdsMp || isTemu2MpRow) ? 0 : parseFloat(item.tacos_ch || 0);
                const adsDisplay = tacosCh;
                // NPFT% = GPFT% − Ads%; Temu skips subtract when Ads%=100 (same as /temu-decrease)
                let npft;
                if (isNoAdsMp || isTemu2MpRow) {
                    npft = gpft;
                } else if (isTemuMpRow && tacosCh === 100) {
                    npft = gpft;
                } else {
                    npft = gpft - tacosCh;
                }

                // SPRICE and calculated values
                const price = parseFloat(item.price || 0);
                const lp = parseFloat(item.lp || 0);
                // Product-master ship for data-ship / SP Apply; formulas below zero ship for PP/TD/Faire
                const rawShip = parseFloat(item.ship || 0) || 0;
                const ship = (isPpMp || isTdMp || isFaireMp) ? 0 : rawShip;
                // SB2B: always SPRICE = (Price × 0.80) − Ship
                const isSb2bMp = (mpLower === 'sb2b' || mpLower === 'shopifyb2b' || mpLower === 'shopify_b2b');
                let sprice = parseFloat(item.sprice || 0);
                if (isSb2bMp && price > 0) {
                    sprice = Math.max(0.01, Math.round(((price * 0.80) - rawShip) * 100) / 100);
                    item.sprice = sprice;
                }
                // Doba 0.95; Reverb/eBay2/eBay3 0.85; PPower 0.65; TopDawg from marketplace_percentages; others 0.80
                const isEbay23Mp = ['ebay2', 'ebaytwo', 'ebay3', 'ebaythree'].includes(mpLower);
                const margin = (item.margin !== null && item.margin !== undefined && item.margin !== '')
                    ? parseFloat(item.margin)
                    : (isDobaMp ? 0.95 : ((isReverbMp || isEbay23Mp) ? 0.85 : (isPpMp ? 0.65 : (isTdMp ? 0 : 0.80))));

                // GROI% = (Price × Margin − LP − Ship) ÷ LP × 100
                // Doba NROI = GROI (no ads); Temu uses GROI−Ads%; Reverb/others: dollar-ads/LP
                let groi = 0, nroi = 0;
                if (lp > 0 && price > 0) {
                    const grossProfit = price * margin - lp - ship;
                    groi = (grossProfit / lp) * 100;
                    if (isNoAdsMp || isTemu2MpRow) {
                        nroi = groi;
                    } else if (isTemuMpRow) {
                        nroi = (tacosCh === 100) ? groi : (groi - tacosCh);
                    } else {
                        const adsPerUnit = price * (tacosCh / 100);
                        nroi = ((grossProfit - adsPerUnit) / lp) * 100;
                    }
                }
                let sgpft = 0, spft = 0, sroi = 0, snroi = 0;
                if (sprice > 0) {
                    // Temu/Temu2: Profit = (Sprice × 0.80) − temu_ship − LP
                    // SGPFT = Profit/Sprice; SROI = Profit/LP
                    const isTemuMp = (mpLower === 'temu' || mpLower === 'temu2');
                    const calcSp = isTemuMp ? (sprice <= 26.99 ? sprice + 2.99 : sprice) : sprice;
                    const temuProfit = isTemuMp ? ((sprice * 0.80) - ship - lp) : 0;
                    sgpft = isTemuMp
                        ? (temuProfit / sprice) * 100
                        : ((calcSp * margin - ship - lp) / calcSp) * 100;
                    // Doba: SPFT = SGPFT; Reverb/eBay/TikTok/Temu: SPFT = SGPFT − Ads%; else L30==0 skip-ads
                    if (isNoAdsMp || isTemu2MpRow) {
                        spft = sgpft;
                    } else if (mpLower === 'tiktok' || isTemuMpRow || isReverbMp || isEbayMp) {
                        spft = (tacosCh === 100) ? sgpft : (sgpft - tacosCh);
                    } else {
                        spft = (l30 == 0 ? sgpft : (sgpft - ad));
                    }
                    sroi = isTemuMp
                        ? (lp > 0 ? (temuProfit / lp) * 100 : 0)
                        : (lp > 0 ? ((calcSp * margin - lp - ship) / lp) * 100 : 0);
                    // SNROI%: Doba/Temu2 = SROI; Temu = SROI − Ads%; others (incl. Reverb) subtract SPRICE × Ads$
                    if (lp > 0) {
                        if (isNoAdsMp || isTemu2MpRow) {
                            snroi = sroi;
                        } else if (isTemuMpRow) {
                            snroi = (tacosCh === 100) ? sroi : (sroi - tacosCh);
                        } else {
                            const sGross = calcSp * margin - lp - ship;
                            const sAds = calcSp * (tacosCh / 100);
                            snroi = ((sGross - sAds) / lp) * 100;
                        }
                    }
                }
                
                const isEditable = ['amazon', 'doba', 'ebay', 'ebay1', 'ebaytwo', 'ebay2', 'ebaythree', 'ebay3', 'temu', 'temu2', 'tiktok', 'tiktok2', 'tiktok 2', 'bestbuy', 'macy', 'reverb', 'sb2c', 'shopify', 'shopifyb2c', 'sb2b', 'shopifyb2b', 'fba', 'tiktok2', 'shein', 'faire', 'aliexpress', 'ppower', 'purchasingpower', 'topdawg'].includes(mpLower);
                
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
                
                // Ads% color — Temu uses per-SKU Ads%; others use channel Ads%
                const adsColorVal = isTemuMpRow ? adsDisplay : tacosCh;
                if (adsColorVal < 5) adColor = '#e83e8c';
                else if (adsColorVal <= 10) adColor = '#28a745';
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
                    'amazon', 'doba',
                    'ebay', 'ebay1', 'ebay2', 'ebaytwo', 'ebay3', 'ebaythree',
                    'sb2c', 'shopify', 'shopifyb2c', 'sb2b', 'shopifyb2b',
                    'bestbuy', 'bestbuyusa', 'macy', 'macys',
                    'ppower', 'purchasingpower', 'purchase',
                    'reverb', 'fba', 'topdawg', 'temu', 'temu2'
                ];
                const canPushPrice = pushableChannels.includes((item.marketplace || '').toLowerCase()) && isListed;
                const hasPushableSprice = parseFloat(item.sprice) > 0;

                // Price in red when LMP is available and LMP < Price
                const lmpForPrice = parseFloat(item.lmp_price);
                // Temu / Temu2 Price column: display only × 1.1765 (~1/0.85). GPFT / GROI / NROI / NPFT stay on original price.
                const TEMU_PRICE_DISPLAY_MULT = 1.1765;
                const displayPrice = ((isTemuMpRow || isTemu2MpRow) && price > 0)
                    ? +(price * TEMU_PRICE_DISPLAY_MULT).toFixed(2)
                    : price;
                // Price vs LMP: ≤90% LMP → purple; 90%–LMP → dark green; > LMP → red
                let priceColorStyle = '';
                if (isListed && displayPrice > 0 && lmpForPrice > 0) {
                    const lmp90 = lmpForPrice * 0.90;
                    if (displayPrice > lmpForPrice) {
                        priceColorStyle = 'color:#a00211;font-weight:700;';
                    } else if (displayPrice <= lmp90) {
                        priceColorStyle = 'color:#4e0dab;font-weight:700;';
                    } else {
                        // Between LMP×0.90 and LMP (inclusive of LMP)
                        priceColorStyle = 'color:#006400;font-weight:700;';
                    }
                }
                // Rolling history dots — channel Price uses per-marketplace history (not blended avg_price)
                const chartSkuEsc = String(getModalPrimarySku() || item.sku || '')
                    .replace(/"/g, '&quot;');
                const chartMpEsc = String(item.marketplace || '')
                    .replace(/"/g, '&quot;');
                const priceChartDot = (isListed && chartSkuEsc && displayPrice > 0)
                    ? ' <i class="fas fa-circle pricing-master-chart-link ms-1" data-metric="channel_price"'
                        + ' data-sku="' + chartSkuEsc
                        + '" data-marketplace="' + chartMpEsc
                        + '" data-current-price="' + displayPrice.toFixed(2) + '"'
                        + ' style="cursor:pointer;color:#e83e8c;font-size:8px;vertical-align:middle;"'
                        + ' title="View ' + chartMpEsc + ' Price history (Rolling L30)"></i>'
                    : '';
                const cvrChartDot = (isListed && chartSkuEsc && !viewsMissing)
                    ? ' <i class="fas fa-circle pricing-master-chart-link ms-1" data-metric="cvr"'
                        + ' data-sku="' + chartSkuEsc
                        + '" style="cursor:pointer;color:#ff9c00;font-size:8px;vertical-align:middle;"'
                        + ' title="View CVR history (Rolling L30)"></i>'
                    : '';

                const priceCellHtml = !isListed
                    ? '-'
                    : '<span style="' + priceColorStyle + '" title="' +
                        ((isTemuMpRow || isTemu2MpRow) && price > 0
                            ? ((isTemu2MpRow ? 'Temu2' : 'Temu') + ' Price × 1.1765 (shown). Base calc price: $' + price.toFixed(2))
                            : '') +
                      '">$' + displayPrice.toFixed(2) + '</span>' + priceChartDot;

                const pushedByTip = item.pushed_by
                    ? String(item.pushed_by + (item.pushed_at ? ' ' + item.pushed_at : '')).replace(/"/g, '&quot;')
                    : '';
                const pushedByCell = item.pushed_by
                    ? '<span class="pushed-by-dot" title="' + pushedByTip + '" aria-label="' + pushedByTip + '"></span>'
                    : '<span class="text-muted">-</span>';

                const pushHistory = Array.isArray(item.push_history) ? item.push_history : [];
                const histCount = pushHistory.length;
                const histTitle = histCount
                    ? (histCount + ' push record(s) — click to view')
                    : 'No push history yet';
                const historyCell = '<button type="button" class="ovl30-history-btn'
                    + (histCount ? ' has-history' : '')
                    + '" data-marketplace="' + String(item.marketplace || '').replace(/"/g, '&quot;') + '"'
                    + ' title="' + histTitle.replace(/"/g, '&quot;') + '">'
                    + '<i class="fas fa-history"></i>'
                    + (histCount ? (' <span class="small">' + histCount + '</span>') : '')
                    + '</button>';
                
                const lmpPriceAttr = parseFloat(item.lmp_price) || 0;
                html += `
                    <tr class="${rowClass}" data-marketplace="${item.marketplace}" data-sku="${item.sku}" 
                        data-lp="${lp}" data-ship="${(isPpMp || isTdMp || isFaireMp) ? rawShip : ship}" data-ad="${ad}" data-tacos-ch="${tacosCh}" data-margin="${margin}" data-l30="${l30}"
                        data-price="${price}" data-lmp="${lmpPriceAttr}" data-cvr="${cvr}" data-views="${views}" data-editable="${isEditable && isListed ? 1 : 0}">
                        <td class="${textClass}">${item.marketplace || '-'}</td>
                        <td class="text-end ${textClass}">${isListed ? l30.toLocaleString() : '-'}</td>
                        <td class="text-center">-</td>
                        <td class="text-end ${textClass}">${priceCellHtml}</td>
                        <td class="text-center ovl30-promo-cell ${textClass}">
                            ${(() => {
                                if (!isListed) return '-';
                                const promoVal = String(typeof modalPromotionRaw !== 'undefined' ? modalPromotionRaw : '')
                                    .replace(/"/g, '&quot;');
                                return '<span class="ovl30-promo-wrap">'
                                    + '<input type="text" class="form-control form-control-sm editable-promo" '
                                    + 'value="' + promoVal + '" placeholder="10%" '
                                    + 'title="Promotion — same on all channels. 10% = −10% SPRICE; 10 = −$10">'
                                    + '</span>';
                            })()}
                        </td>
                        <td class="text-end ovl30-metric-col ${textClass}">${!isListed ? '-' : (viewsMissing ? 'N/A' : views.toLocaleString())}</td>
                        <td class="text-end ovl30-metric-col ${textClass}">${!isListed ? '-' : (viewsMissing ? 'N/A' : (views > 0 ? '<span style="' + styleForCellColor(cvrColor) + '">' + cvr.toFixed(1) + '%</span>' + cvrChartDot : '-'))}</td>
                        <td class="text-end ovl30-metric-col ${textClass}">${isListed && price > 0 && lp > 0 ? '<span style="' + styleForCellColor(groiColor) + '">' + Math.round(groi) + '%</span>' : '-'}</td>
                        <td class="text-end ovl30-metric-col ${textClass}">${isListed && gpft !== 0 ? '<span style="' + gpftStyle + '">' + Math.round(gpft) + '%</span>' : '-'}</td>
                        <td class="text-end ovl30-metric-col ${textClass}">${isListed && price > 0 && lp > 0 ? '<span style="' + styleForCellColor(nroiColor) + '">' + Math.round(nroi) + '%</span>' : '-'}</td>
                        <td class="text-end ovl30-metric-col ${textClass}">${isListed ? '<span style="' + npftStyle + '">' + Math.round(npft) + '%</span>' : '-'}</td>
                        <td class="text-end ovl30-metric-col ${textClass}">${isListed ? '<span style="' + styleForCellColor(adColor) + ';font-weight:600;">' + Math.round(adsDisplay) + '%</span>' : '-'}</td>
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
                        <td class="text-center ovl30-link-cell ${textClass}" style="white-space: nowrap;">
                            ${(item.buyer_link || item.seller_link) ? 
                                (item.buyer_link ? '<a href="' + item.buyer_link + '" target="_blank" rel="noopener" class="ovl30-link-bs" title="Buyer link" style="color:#0d6efd;font-weight:700;text-decoration:none;padding:0 2px;">B</a>' : '') +
                                (item.seller_link ? '<a href="' + item.seller_link + '" target="_blank" rel="noopener" class="ovl30-link-bs" title="Seller link" style="color:#495057;font-weight:700;text-decoration:none;padding:0 2px;">S</a>' : '')
                                : '-'}
                        </td>
                        <td class="text-center ovl30-select-cell">
                            ${(isEditable && isListed)
                                ? '<input type="checkbox" class="ovl30-prc-row-cb" data-marketplace="'
                                    + String(item.marketplace || '').replace(/"/g, '&quot;')
                                    + '"' + (modalSelectedChannels.has(String(item.marketplace || '')) ? ' checked' : '')
                                    + ' title="Select channel">'
                                : ''}
                        </td>
                        <td class="text-center ovl30-suggest-cell ${textClass}">-</td>
                        <td class="text-center ovl30-std-prc-cell ${textClass}">
                            ${(() => {
                                // STD PRC = shared SKU STANDARD_PRICE on every listed marketplace
                                if (!isListed) return '-';
                                const std = parseFloat(item.standard_price) || 0;
                                const skuEsc = String(getModalPrimarySku() || item.sku || '')
                                    .replace(/"/g, '&quot;');
                                const dot = std > 0
                                    ? priceIncreaseSpChangeDotHtml(std, price, skuEsc)
                                    : '';
                                const valAttr = std > 0 ? std.toFixed(2) : '';
                                return '<span class="ovl30-std-prc-wrap">'
                                    + dot
                                    + '<input type="number" class="form-control form-control-sm editable-std-prc" '
                                    + 'value="' + valAttr + '" step="0.01" min="0" '
                                    + 'placeholder="" title="Standard Price (STD PRC) — same SP for all marketplaces">'
                                    + '</span>';
                            })()}
                        </td>
                        <td class="text-end ovl30-sprice-cell ${textClass}">
                            ${(() => {
                                const lmpVal = parseFloat(item.lmp_price) || 0;
                                const showAlert = sprice > 0 && lmpVal > 0 && sprice > lmpVal;
                                const alertHtml = showAlert
                                    ? '<i class="fas fa-exclamation-triangle ovl30-sprice-lmp-alert" title="SPRICE $'
                                        + sprice.toFixed(2) + ' &gt; LMP $' + lmpVal.toFixed(2) + '"></i>'
                                    : '';
                                if (isEditable && isListed) {
                                    return '<span class="ovl30-sprice-wrap">'
                                        + '<input type="number" class="form-control form-control-sm editable-sprice" value="'
                                        + sprice.toFixed(2) + '" step="0.01">'
                                        + alertHtml
                                        + '</span>';
                                }
                                if (sprice > 0) {
                                    return '<span class="ovl30-sprice-wrap">$' + sprice.toFixed(2) + alertHtml + '</span>';
                                }
                                return '-';
                            })()}
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
                        <td class="text-center ovl30-push-cell ${textClass}">
                            ${canPushPrice ? 
                                '<button class="btn btn-sm btn-primary push-price-btn" ' +
                                'data-sku="' + item.sku + '" ' +
                                'data-marketplace="' + item.marketplace + '" ' +
                                (hasPushableSprice ? '' : 'disabled ') +
                                'title="' + (hasPushableSprice
                                    ? ('Push price to ' + item.marketplace)
                                    : 'Enter a SPRICE greater than 0 to push') + '">' +
                                '<i class="fas fa-upload"></i></button>' 
                                : '-'}
                        </td>
                        <td class="text-center ovl30-by-cell ${textClass}">${pushedByCell}</td>
                        <td class="text-center ovl30-history-cell ${textClass}">${historyCell}</td>
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
            syncModalToolbarSpInput();
            // Prefer SP from Sku Link LMP siblings (shared group value)
            loadModalToolbarSpFromSkuLinkGroup(getModalPrimarySku());
            updateOvl30SortIcons();
            scheduleAutoFitOvl30TableFont();
            applyModalGroupFilter({ selectChannels: false });
        }

        /** Shared SKU STANDARD_PRICE from any marketplace row in the OV L30 modal. */
        function getModalSharedStandardPrice() {
            let std = 0;
            if (!Array.isArray(ovl30ModalData)) return std;
            // Prefer Amazon row, then any channel with a positive SP
            for (let pass = 0; pass < 2; pass++) {
                for (let i = 0; i < ovl30ModalData.length; i++) {
                    const item = ovl30ModalData[i];
                    const mp = String(item.marketplace || '').toLowerCase();
                    if (pass === 0 && mp !== 'amazon') continue;
                    if (pass === 1 && mp === 'amazon') continue;
                    const n = parseFloat(item.standard_price);
                    if (isFinite(n) && n > 0) return n;
                }
            }
            return std;
        }

        function setModalSharedStandardPrice(saved) {
            const std = parseFloat(saved);
            if (!isFinite(std) || std <= 0 || !Array.isArray(ovl30ModalData)) return;
            ovl30ModalData.forEach(function(item) {
                item.standard_price = std;
            });
        }

        /** Toolbar SP — same STANDARD_PRICE as STD PRC on every marketplace row. */
        function syncModalToolbarSpInput(forcedValue) {
            const $input = $('#modal-std-sp-input');
            if (!$input.length) return;
            if ($input.is(':focus')) return;
            let std = parseFloat(forcedValue);
            if (!isFinite(std) || std <= 0) {
                std = getModalSharedStandardPrice();
            }
            $input.val(std > 0 ? std.toFixed(2) : '');
        }

        /**
         * Load SP from Sku Link LMP siblings (/amazon-standard-price) into OV L30 toolbar + STD PRC.
         * Ensures SP shows the shared sibling value even when this SKU has no STANDARD_PRICE saved.
         */
        function loadModalToolbarSpFromSkuLinkGroup(sku) {
            sku = String(sku || getModalPrimarySku() || '').trim();
            if (!sku || sku.toUpperCase().indexOf('PARENT') !== -1) return;
            $.getJSON('/amazon-standard-price', { sku: sku })
                .done(function(res) {
                    const std = parseFloat(res && res.standard_price);
                    if (!isFinite(std) || std <= 0) return;
                    setModalSharedStandardPrice(std);
                    syncModalToolbarSpInput(std);
                    refreshModalStdPrcCells(std);
                    // Prefill Same-$ SPRICE box from shared sibling SP (user can still edit)
                    const $same = $('#modal-sprice-same-input');
                    if ($same.length && !$same.is(':focus') && String($same.val() || '').trim() === '') {
                        $same.val(std.toFixed(2));
                    }
                });
        }

        /** Refresh STD PRC inputs on all listed marketplace rows (shared SP). */
        function refreshModalStdPrcCells(saved) {
            const std = parseFloat(saved);
            if (!isFinite(std) || std <= 0) return;
            const sku = getModalPrimarySku();
            $('#ovl30DetailsTableBody tr[data-marketplace], #ovl30DetailsTable tbody tr[data-marketplace]').each(function() {
                const $row = $(this);
                const rowSku = String($row.attr('data-sku') || '');
                if (!rowSku || rowSku === 'Not Listed') return;
                const $cell = $row.find('.ovl30-std-prc-cell');
                if (!$cell.length) return;
                const channelPrice = parseFloat($row.attr('data-price')) || 0;
                const dotHtml = priceIncreaseSpChangeDotHtml(std, channelPrice, sku);
                $cell.html(
                    '<span class="ovl30-std-prc-wrap">'
                    + (dotHtml || '')
                    + '<input type="number" class="form-control form-control-sm editable-std-prc" '
                    + 'value="' + std.toFixed(2) + '" step="0.01" min="0" '
                    + 'title="Standard Price (STD PRC) — same SP for all marketplaces">'
                    + '</span>'
                );
            });
        }
        // Back-compat alias
        function refreshModalAmazonStdPrcCells(saved) {
            refreshModalStdPrcCells(saved);
        }

        // ==================== TABULATOR INITIALIZATION ====================
        
        table = new Tabulator("#cvr-table", {
            ajaxURL: "/cvr-master-data-json",
            ajaxSorting: false,
            layout: "fitDataFill",
            columnDefaults: {
                resizable: false,
                minWidth: 40
            },
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
            initialSort: [{ column: "dil_percent", dir: "desc" }],
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
                    visible: false,
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
                        const uid = 'pi' + Math.random().toString(36).slice(2, 9);
                        const playIcon =
                            '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">' +
                            '<defs>' +
                            `<linearGradient id="${uid}g" x1="4" y1="2" x2="20" y2="22" gradientUnits="userSpaceOnUse">` +
                            '<stop offset="0%" stop-color="#FFE566"/>' +
                            '<stop offset="45%" stop-color="#FFC107"/>' +
                            '<stop offset="100%" stop-color="#F59E0B"/>' +
                            '</linearGradient>' +
                            `<linearGradient id="${uid}s" x1="6" y1="3" x2="14" y2="14" gradientUnits="userSpaceOnUse">` +
                            '<stop offset="0%" stop-color="#FFFFFF" stop-opacity="0.75"/>' +
                            '<stop offset="55%" stop-color="#FFFFFF" stop-opacity="0.12"/>' +
                            '<stop offset="100%" stop-color="#FFFFFF" stop-opacity="0"/>' +
                            '</linearGradient>' +
                            '</defs>' +
                            `<path d="M8.2 4.8c-.9-.55-2.05.1-2.05 1.15v12.1c0 1.05 1.15 1.7 2.05 1.15l10.2-6.05c.85-.5.85-1.8 0-2.3L8.2 4.8z" fill="url(#${uid}g)"/>` +
                            `<path d="M8.2 4.8c-.9-.55-2.05.1-2.05 1.15v12.1c0 1.05 1.15 1.7 2.05 1.15l10.2-6.05c.85-.5.85-1.8 0-2.3L8.2 4.8z" fill="url(#${uid}s)"/>` +
                            '<path d="M8.2 4.8c-.9-.55-2.05.1-2.05 1.15v12.1c0 1.05 1.15 1.7 2.05 1.15l10.2-6.05c.85-.5.85-1.8 0-2.3L8.2 4.8z" fill="none" stroke="#D97706" stroke-opacity="0.35" stroke-width="0.8"/>' +
                            '</svg>';
                        if (!parent) {
                            return '<span class="parent-sku-dot no-parent" title="No parent">' + playIcon + '</span>';
                        }
                        const parentEsc = parent.replace(/"/g, '&quot;');
                        const isExpanded = (typeof dotExpandedParent !== 'undefined' && dotExpandedParent === parent)
                            || (cell.getRow().getData()._expanded === true);
                        const expandedCls = isExpanded ? ' is-expanded' : '';
                        return `<span class="parent-sku-dot parent-sku-dot-btn${expandedCls}"
                                    data-parent="${parentEsc}"
                                    title="Click to view SKUs for parent: ${parentEsc}">${playIcon}</span>`;
                    }
                },
                {
                    title: "Parent",
                    field: "parent_display",
                    sorter: "string",
                    visible: false,
                    headerFilter: "input",
                    headerFilterPlaceholder: "Search Parent",
                    minWidth: 80,
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const parent = rowData.parent;
                        if (parent === undefined || parent === null || (typeof parent === 'string' && !parent.trim())) return '-';
                        return (typeof parent === 'string' ? parent : String(parent)).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
                    },
                    headerFilterFunc: function(headerValue, rowValue, rowData) {
                        if (!headerValue) return true;
                        const parent = String(rowData.parent || '');
                        return parent.toLowerCase().includes(String(headerValue).toLowerCase());
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
                        const sku = cell.getValue() ?? '';
                        const safeSku = String(sku)
                            .replace(/&/g, '&amp;')
                            .replace(/</g, '&lt;')
                            .replace(/>/g, '&gt;')
                            .replace(/"/g, '&quot;');
                        return `<span class="pricing-master-sku-wrap">` +
                            `<span class="pricing-master-sku-text" title="${safeSku}">${safeSku}</span>` +
                            `<i class="fa fa-copy text-secondary copy-sku-btn" ` +
                            `style="cursor: pointer; font-size: 14px;" ` +
                            `data-sku="${safeSku}" title="Copy SKU"></i>` +
                            `</span>`;
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
                    title: "Hist",
                    field: "push_history_count",
                    headerSort: false,
                    hozAlign: "center",
                    minWidth: 52,
                    headerTooltip: "Push history — date, marketplace, price, user",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        if (rowData.is_parent_summary === true) return '';
                        const sku = rowData.sku || '';
                        const histCount = parseInt(cell.getValue() || rowData.push_history_count || 0, 10) || 0;
                        const histTitle = histCount
                            ? (histCount + ' push record(s) — click to view')
                            : 'Push history — click to view';
                        const skuEsc = String(sku).replace(/"/g, '&quot;');
                        return '<button type="button" class="ovl30-history-btn outer-push-history-btn'
                            + (histCount ? ' has-history' : '')
                            + '" data-sku="' + skuEsc + '"'
                            + ' title="' + histTitle.replace(/"/g, '&quot;') + '">'
                            + '<i class="fas fa-history"></i>'
                            + (histCount ? (' <span class="small">' + histCount + '</span>') : '')
                            + '</button>';
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
                    headerTooltip: "SW L30: total L30 summed across marketplace channels (Amazon, eBay, Temu, Temu 2, Macy's, Reverb, etc. — Walmart & Tiendamia excluded). Per-channel values appear in the SKU detail modal. Green when SW L30 equals OV L30; red otherwise.",
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
                    title: "Label",
                    field: "label",
                    hozAlign: "center",
                    headerHozAlign: "center",
                    headerSort: false,
                    cssClass: "pi-label-col",
                    headerTooltip: "Shipping Master Label. Click the blue dot for Label Qty & dimensions.",
                    formatter: function(cell) {
                        const data = cell.getRow().getData() || {};
                        const wrap = document.createElement('span');
                        wrap.className = 'pi-label-cell';
                        const dot = document.createElement('button');
                        dot.type = 'button';
                        dot.className = 'pi-label-dims-dot';
                        dot.title = 'View Label Qty & dimensions';
                        dot.setAttribute('aria-label', 'View Label Qty and dimensions');
                        dot.addEventListener('click', function(ev) {
                            ev.preventDefault();
                            ev.stopPropagation();
                            openPiLabelDimsModal(data);
                        });
                        wrap.appendChild(dot);
                        return wrap;
                    },
                    minWidth: 50,
                    width: 55
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
                    title: "SP",
                    field: "amazon_standard_price",
                    hozAlign: "center",
                    headerTooltip: "Standard Price — same as /amazon-tabulator-view SP (amazon_data_view.STANDARD_PRICE). Parent shows lowest child SP; (1) when children differ.",
                    editor: "input",
                    minWidth: 70,
                    sorter: "number",
                    editable: function(cell) {
                        const d = cell.getRow().getData();
                        return d.is_parent_summary !== true && d.sku && String(d.sku).indexOf('PARENT') === -1;
                    },
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const value = cell.getValue();
                        const std = parseFloat(value) || 0;
                        // Parent: show lowest child SP; append (1) when children have different SPs.
                        // Red when any child is missing SP.
                        if (rowData.is_parent_summary) {
                            const missing = !!rowData.amazon_standard_price_missing;
                            const mixed = !!rowData.amazon_standard_price_mixed;
                            const color = missing ? '#dc3545' : 'inherit';
                            if (!value || std <= 0) {
                                if (!missing) return '';
                                return '<span style="font-weight:600;color:#dc3545;" title="One or more children missing SP">—</span>';
                            }
                            let tip = 'Lowest child SP $' + std.toFixed(2);
                            if (missing) tip += ' — one or more children missing SP';
                            else if (mixed) tip += ' — children have different SPs';
                            return '<span style="font-weight:600;color:' + color + ';" title="' + tip.replace(/"/g, '&quot;') + '">$'
                                + std.toFixed(2)
                                + (mixed ? ' <span style="font-weight:500;color:' + (missing ? '#dc3545' : '#6c757d') + ';">(1)</span>' : '')
                                + '</span>';
                        }
                        const currentPrice = parseFloat(rowData.amazon_price) || 0;
                        if (!value || std <= 0) return '';
                        const sku = rowData.sku || '';
                        // Hold (SP = Amazon price): show price only — no yellow dot
                        if (currentPrice > 0 && currentPrice.toFixed(2) === std.toFixed(2)) {
                            return '<span style="font-weight:600;" title="Hold (matches Amazon price) — SP">$'
                                + std.toFixed(2) + '</span>';
                        }
                        const dot = priceIncreaseSpChangeDotHtml(std, currentPrice, sku);
                        return '<span style="display:inline-flex;align-items:center;justify-content:center;gap:4px;">' +
                            dot + ('$' + std.toFixed(2)) + '</span>';
                    }
                },
                {
                    title: "Audit",
                    field: "audit",
                    hozAlign: "center",
                    headerSort: false,
                    width: 70,
                    minWidth: 60,
                    headerTooltip: "Open audit modal — same as /amz-cvr-issues (amz_cvr_audit_histories)",
                    formatter: function(cell) {
                        const d = cell.getRow().getData() || {};
                        if (d.is_parent_summary) return '';
                        const sku = String(d.sku || '').replace(/"/g, '&quot;');
                        if (!sku || sku.indexOf('PARENT') !== -1) return '';
                        return '<button type="button" class="amz-cvr-audit-btn" data-sku="'
                            + sku + '" title="Open audit"><i class="fa fa-search"></i></button>';
                    },
                    cellClick: function(e, cell) {
                        e.preventDefault();
                        e.stopPropagation();
                        const d = cell.getRow().getData() || {};
                        if (d.is_parent_summary || !d.sku || String(d.sku).indexOf('PARENT') !== -1) return;
                        if (window.AmzCvrAudit && typeof window.AmzCvrAudit.open === 'function') {
                            window.AmzCvrAudit.open(d);
                        }
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
                    title: "Temu Views",
                    field: "temu_views",
                    hozAlign: "center",
                    sorter: "number",
                    headerTooltip: "L30 Temu ad clicks — same as /temu-decrease Views (temu_metrics.product_clicks_l30).",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const value = parseInt(cell.getValue() || 0);
                        const skuEsc = (rowData.sku || '').replace(/"/g, '&quot;');
                        let html = value === 0
                            ? '<span style="color: #6c757d;">0</span>'
                            : `<span style="font-weight: 600;">${value.toLocaleString()}</span>`;
                        if (rowData.is_parent_summary !== true && skuEsc) {
                            html += ' <button type="button" class="btn btn-sm p-0 temu-views-chart-link align-middle" data-sku="' + skuEsc + '" title="View Temu Views chart (same as /temu-decrease)" style="border:none;background:none;cursor:pointer;padding:0 2px;line-height:1;vertical-align:middle;"><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#0000FF;"></span></button>';
                        }
                        return html;
                    },
                    minWidth: 70
                },
                {
                    title: "Temu S PRC",
                    field: "temu_sprice",
                    hozAlign: "center",
                    sorter: "number",
                    editor: "input",
                    headerTooltip: "Temu Suggested Price (base/supplier) — same as /temu-decrease S PRC. Saved to temu_data_view.",
                    editable: function(cell) {
                        const d = cell.getRow().getData();
                        return d.is_parent_summary !== true && d.sku && String(d.sku).indexOf('PARENT') === -1;
                    },
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        if (rowData.is_parent_summary) {
                            const v = cell.getValue();
                            if (v == null || v === '' || parseFloat(v) <= 0) return '';
                            return '<span style="font-weight:600;">$' + parseFloat(v).toFixed(2) + '</span>';
                        }
                        const value = cell.getValue();
                        const currentPrice = parseFloat(rowData.temu_base_price) || 0;
                        const spriceNum = (value != null && value !== '') ? parseFloat(value) : NaN;
                        const sprice = isNaN(spriceNum) ? 0 : spriceNum;
                        if (value == null || value === '' || isNaN(spriceNum) || sprice <= 0) return '';
                        if (currentPrice > 0 && currentPrice.toFixed(2) === sprice.toFixed(2)) return '';
                        return '$' + sprice.toFixed(2);
                    },
                    minWidth: 80
                },
                {
                    title: "S Temu Prc",
                    field: "stemu_price_display",
                    hozAlign: "center",
                    headerSort: false,
                    headerTooltip: "Temu push base from SPRICE: (Sprice×0.85)−2.99 if SPRICE&lt;$35; else Sprice×0.85",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        if (rowData.is_parent_summary) return '';
                        const pushBase = temuPushBaseFromSprice(rowData.temu_sprice);
                        if (pushBase == null) return '';
                        return '<span style="font-weight:600;">$' + pushBase.toFixed(2) + '</span>';
                    },
                    minWidth: 80
                },
                {
                    title: "Temu Push",
                    field: "_temu_push",
                    width: 60,
                    hozAlign: "center",
                    headerSort: false,
                    headerTooltip: "Push Temu base = (Sprice×0.85)−2.99 if SPRICE&lt;$35; else Sprice×0.85",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        if (rowData.is_parent_summary) return '';
                        const sprice = parseFloat(rowData.temu_sprice) || 0;
                        const pushBase = temuPushBaseFromSprice(sprice);
                        const pushStatus = rowData.temu_push_status || null;
                        if (sprice <= 0 || pushBase == null) return '';

                        const sku = String(rowData.sku || '').replace(/"/g, '&quot;');
                        const goodsId = String(rowData.temu_goods_id || '').replace(/"/g, '&quot;');
                        const skuId = String(rowData.temu_sku_id || '').replace(/"/g, '&quot;');

                        if (pushStatus === 'pushing') {
                            return '<i class="fas fa-spinner fa-spin" style="color:#ffc107;" title="Pushing to Temu..."></i>';
                        }
                        if (pushStatus === 'pushed') {
                            return '<i class="fa-solid fa-check-double" style="color:#28a745;" title="Pushed to Temu"></i>';
                        }
                        if (pushStatus === 'error') {
                            return '<button type="button" class="pi-temu-push-single-btn" data-sku="' + sku + '" data-price="' + pushBase + '" data-goods-id="' + goodsId + '" data-sku-id="' + skuId + '" style="border:none;background:none;color:#dc3545;cursor:pointer;" title="Push failed — click to retry"><i class="fa-solid fa-x"></i></button>';
                        }
                        return '<button type="button" class="pi-temu-push-single-btn" data-sku="' + sku + '" data-price="' + pushBase + '" data-goods-id="' + goodsId + '" data-sku-id="' + skuId + '" style="border:none;background:none;color:#0d6efd;cursor:pointer;" title="Push base $' + pushBase.toFixed(2) + ' to Temu"><i class="fas fa-upload"></i></button>';
                    }
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
                    headerTooltip: "Temu LMP L1 = Price + Del (Del defaults to $2.99 when Price &lt; $27)",
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
                        return `<a href="#" class="lmp-price-link" data-sku="${sku}" data-marketplace="temu" style="${styleForCellColor(color)} text-decoration: none; cursor: pointer;" title="Temu LMP (direct)">$${price.toFixed(2)}</a>`;
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
                    title: "Fr L30",
                    field: "faire_l30",
                    visible: false,
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseInt(cell.getValue() || 0);
                        if (value === 0) return '<span style="color:#6c757d;">0</span>';
                        return `<span style="color:#0d6efd;font-weight:600;">${value.toLocaleString()}</span>`;
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

        /** SP vs Amazon price: reduce / hold / increase → red / yellow / green (same as amazon-tabulator-view). */
        function priceIncreaseSpChangeDotMeta(sprice, amazonPrice) {
            const sp = parseFloat(sprice) || 0;
            const ap = parseFloat(amazonPrice) || 0;
            if (sp <= 0) return null;
            if (ap <= 0) {
                return { kind: 'hold', color: '#ffc107', title: 'Hold' };
            }
            const sp2 = sp.toFixed(2);
            const ap2 = ap.toFixed(2);
            if (parseFloat(sp2) < parseFloat(ap2)) {
                return { kind: 'reduce', color: '#dc3545', title: 'Reduced vs Amazon price' };
            }
            if (parseFloat(sp2) > parseFloat(ap2)) {
                return { kind: 'increase', color: '#28a745', title: 'Increase vs Amazon price' };
            }
            return { kind: 'hold', color: '#ffc107', title: 'Hold (matches Amazon price)' };
        }

        function priceIncreaseSpChangeDotHtml(sprice, amazonPrice, sku) {
            const meta = priceIncreaseSpChangeDotMeta(sprice, amazonPrice);
            if (!meta) return '';
            const tip = meta.title + ' — SP (Standard Price)';
            return '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;' +
                'background:' + meta.color + ';flex-shrink:0;" title="' + String(tip).replace(/"/g, '&quot;') + '"></span>';
        }

        // ==================== Sprice Rule (SP = Amazon LMP × factor) ====================
        const PI_SPRICE_LMP_MULT_KEY = 'price_increase_sprice_lmp_mult';
        let piSpriceLmpMult = 0.98;

        function getPiSpriceLmpMult() {
            const n = parseFloat(piSpriceLmpMult);
            if (isFinite(n) && n > 0 && n <= 2) return n;
            return 0.98;
        }

        function formatPiSpriceLmpMult(v) {
            const n = Number(v);
            if (!isFinite(n)) return '0.98';
            return String(+n.toFixed(4));
        }

        function loadPiSpriceLmpMult() {
            try {
                const stored = parseFloat(localStorage.getItem(PI_SPRICE_LMP_MULT_KEY));
                if (isFinite(stored) && stored > 0 && stored <= 2) {
                    piSpriceLmpMult = stored;
                }
            } catch (e) { /* ignore */ }
            $('#pi-sprice-lmp-mult-input').val(formatPiSpriceLmpMult(getPiSpriceLmpMult()));
        }

        function savePiSpriceLmpMultFromInput() {
            const raw = parseFloat(String($('#pi-sprice-lmp-mult-input').val() || '').replace(',', '.'));
            if (!isFinite(raw) || raw <= 0 || raw > 2) {
                showToast('Enter a multiplier between 0.01 and 2.00', 'error');
                $('#pi-sprice-lmp-mult-input').val(formatPiSpriceLmpMult(getPiSpriceLmpMult()));
                return false;
            }
            piSpriceLmpMult = raw;
            try { localStorage.setItem(PI_SPRICE_LMP_MULT_KEY, String(raw)); } catch (e) { /* ignore */ }
            $('#pi-sprice-lmp-mult-input').val(formatPiSpriceLmpMult(raw));
            return true;
        }

        function collectSkusForPiSpriceLmpRule() {
            const out = [];
            const seen = new Set();
            const useSelection = typeof selectedSkus !== 'undefined' && selectedSkus.size > 0;

            function pushSku(sku, lmp) {
                const key = String(sku || '').trim().toUpperCase();
                if (!key || key.indexOf('PARENT') === 0 || seen.has(key)) return;
                const lmpN = parseFloat(lmp) || 0;
                if (lmpN <= 0) return;
                seen.add(key);
                out.push({ sku: String(sku).trim(), lmp: lmpN });
            }

            function addFromDatasetRow(d) {
                if (!d || d.is_parent_summary) return;
                pushSku(d.sku, d.amazon_lmp_price);
            }

            if (useSelection) {
                selectedSkus.forEach(function(selSku) {
                    const key = String(selSku || '').trim().toUpperCase();
                    if (!key) return;
                    // Selected parent → all children under that parent
                    if (key.indexOf('PARENT ') === 0 || (fullDataset || []).some(r =>
                        r.is_parent_summary && String(r.sku || '').trim().toUpperCase() === key
                    )) {
                        const parentRow = (fullDataset || []).find(r =>
                            r.is_parent_summary && String(r.sku || '').trim().toUpperCase() === key
                        );
                        const parentName = parentRow
                            ? String(parentRow.parent || '').trim()
                            : String(selSku || '').replace(/^PARENT\s+/i, '').trim();
                        (fullDataset || []).forEach(function(r) {
                            if (r.is_parent_summary) return;
                            if (String(r.parent || '').trim() === parentName) addFromDatasetRow(r);
                        });
                        return;
                    }
                    const child = (fullDataset || []).find(r =>
                        !r.is_parent_summary && String(r.sku || '').trim().toUpperCase() === key
                    );
                    if (child) addFromDatasetRow(child);
                });
            } else if (table) {
                // No selection: visible rows — parents expand to children; child rows used directly
                table.getRows('active').forEach(function(r) {
                    const d = r.getData();
                    if (!d) return;
                    if (d.is_parent_summary) {
                        const parentName = String(d.parent || '').trim();
                        (fullDataset || []).forEach(function(row) {
                            if (row.is_parent_summary) return;
                            if (String(row.parent || '').trim() === parentName) addFromDatasetRow(row);
                        });
                    } else {
                        addFromDatasetRow(d);
                    }
                });
            }
            return out;
        }

        loadPiSpriceLmpMult();

        $('#pi-sprice-lmp-mult-input').on('change blur', function() {
            savePiSpriceLmpMultFromInput();
        });

        $('#pi-apply-sprice-lmp-rule-btn').on('click', function() {
            if (!savePiSpriceLmpMultFromInput()) return;
            const mult = getPiSpriceLmpMult();
            const multLabel = formatPiSpriceLmpMult(mult);
            const $btn = $(this);
            if (!table) {
                showToast('Table not ready', 'error');
                return;
            }

            const targets = collectSkusForPiSpriceLmpRule();
            if (targets.length === 0) {
                showToast(selectedSkus.size > 0
                    ? 'No selected SKUs with Amazon LMP > 0'
                    : 'No visible SKUs with Amazon LMP > 0', 'error');
                return;
            }

            const scope = selectedSkus.size > 0 ? 'selected' : 'visible';
            if (!confirm('Set SP = Amazon LMP × ' + multLabel + ' for ' + targets.length + ' ' + scope + ' SKU(s)?\n\nSaves to the same SP field as /amazon-tabulator-view.')) {
                return;
            }

            const btnHtml = $btn.html();
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
            let successCount = 0;
            let errorCount = 0;
            let done = 0;
            const total = targets.length;

            targets.forEach(function(item) {
                const sp = +Number(item.lmp * mult).toFixed(2);
                if (!isFinite(sp) || sp <= 0) {
                    errorCount++;
                    done++;
                    if (done >= total) {
                        $btn.prop('disabled', false).html(btnHtml);
                        showToast('Sprice Rule done: ' + successCount + ' saved, ' + errorCount + ' failed', errorCount ? 'error' : 'success');
                    }
                    return;
                }
                $.ajax({
                    url: '/save-amazon-sprice',
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    data: {
                        sku: item.sku,
                        sprice: sp,
                        is_standard_price: 1,
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        const saved = response.data || sp;
                        applyStandardPriceToPriceIncreaseRows(item.sku, saved, response.applied_skus);
                        successCount++;
                    },
                    error: function() {
                        errorCount++;
                    },
                    complete: function() {
                        done++;
                        if (done >= total) {
                            $btn.prop('disabled', false).html(btnHtml);
                            showToast(
                                'Sprice Rule: SP = LMP × ' + multLabel + ' — ' + successCount + ' saved' +
                                (errorCount ? (', ' + errorCount + ' failed') : ''),
                                errorCount ? 'error' : 'success'
                            );
                        }
                    }
                });
            });
        });

        // ==================== Temu SPRICE / Push (same as /temu-decrease) ====================
        function collectPiTemuSpriceRows() {
            const out = [];
            const seen = new Set();
            const useSelection = typeof selectedSkus !== 'undefined' && selectedSkus.size > 0;

            function addChild(d) {
                if (!d || d.is_parent_summary) return;
                const key = String(d.sku || '').trim().toUpperCase();
                if (!key || key.indexOf('PARENT') === 0 || seen.has(key)) return;
                seen.add(key);
                out.push(d);
            }

            if (useSelection) {
                selectedSkus.forEach(function(selSku) {
                    const key = String(selSku || '').trim().toUpperCase();
                    if (!key) return;
                    if (key.indexOf('PARENT ') === 0 || (fullDataset || []).some(r =>
                        r.is_parent_summary && String(r.sku || '').trim().toUpperCase() === key
                    )) {
                        const parentRow = (fullDataset || []).find(r =>
                            r.is_parent_summary && String(r.sku || '').trim().toUpperCase() === key
                        );
                        const parentName = parentRow
                            ? String(parentRow.parent || '').trim()
                            : String(selSku || '').replace(/^PARENT\s+/i, '').trim();
                        (fullDataset || []).forEach(function(r) {
                            if (r.is_parent_summary) return;
                            if (String(r.parent || '').trim() === parentName) addChild(r);
                        });
                        return;
                    }
                    const child = (fullDataset || []).find(r =>
                        !r.is_parent_summary && String(r.sku || '').trim().toUpperCase() === key
                    );
                    if (child) addChild(child);
                });
            } else if (table) {
                table.getRows('active').forEach(function(r) {
                    const d = r.getData();
                    if (!d) return;
                    if (d.is_parent_summary) {
                        const parentName = String(d.parent || '').trim();
                        (fullDataset || []).forEach(function(row) {
                            if (row.is_parent_summary) return;
                            if (String(row.parent || '').trim() === parentName) addChild(row);
                        });
                    } else {
                        addChild(d);
                    }
                });
            }
            return out;
        }

        function findPiTableRowBySku(sku) {
            if (!table || !sku) return null;
            const key = String(sku).trim().toUpperCase();
            return (table.getRows() || []).find(function(r) {
                return String(r.getData().sku || '').trim().toUpperCase() === key;
            }) || null;
        }

        function pushPiTemuPriceForRow(row, price) {
            const data = row.getData();
            const sku = data.sku;
            const goodsId = data.temu_goods_id || '';
            const skuId = data.temu_sku_id || '';
            // Accept either raw SPRICE or already-converted base; always normalize via formula
            const raw = parseFloat(price);
            const fromSprice = temuPushBaseFromSprice(data.temu_sprice);
            // Prefer convert-from-SPRICE; fall back to provided price if already a push base
            let pushPrice = fromSprice;
            if (pushPrice == null && isFinite(raw) && raw > 0) pushPrice = +raw.toFixed(2);
            if (!sku || !(pushPrice > 0)) {
                return Promise.reject({ message: 'SKU and price required' });
            }

            row.update({ temu_push_status: 'pushing' });
            row.reformat();

            return new Promise(function(resolve, reject) {
                $.ajax({
                    url: '/temu/push-price',
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    data: {
                        _token: '{{ csrf_token() }}',
                        sku: sku,
                        price: pushPrice,
                        goods_id: goodsId,
                        sku_id: skuId
                    },
                    success: function(response) {
                        if (response && response.success) {
                            row.update({ temu_push_status: 'pushed' });
                            row.reformat();
                            if (Array.isArray(fullDataset)) {
                                const key = String(sku).trim().toUpperCase();
                                fullDataset.forEach(function(r) {
                                    if (!r || r.is_parent_summary) return;
                                    if (String(r.sku || '').trim().toUpperCase() === key) {
                                        r.temu_push_status = 'pushed';
                                    }
                                });
                            }
                            resolve(response);
                        } else {
                            row.update({ temu_push_status: 'error' });
                            row.reformat();
                            reject({ message: (response && response.message) || 'Push failed' });
                        }
                    },
                    error: function(xhr) {
                        row.update({ temu_push_status: 'error' });
                        row.reformat();
                        const msg = (xhr.responseJSON && (xhr.responseJSON.message
                            || (xhr.responseJSON.errors && xhr.responseJSON.errors[0] && xhr.responseJSON.errors[0].message)))
                            || 'Push failed';
                        reject({ message: msg });
                    }
                });
            });
        }

        $(document).on('click', '.pi-temu-push-single-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const $btn = $(this);
            const sku = $btn.data('sku');
            const row = findPiTableRowBySku(sku);
            if (!row) return;
            const sprice = parseFloat(row.getData().temu_sprice) || 0;
            const pushBase = temuPushBaseFromSprice(sprice);
            if (pushBase == null) {
                showToast('Cannot push — invalid Temu SPRICE', 'error');
                return;
            }
            if (!confirm(
                'Push Temu base $' + pushBase.toFixed(2)
                + ' (from SPRICE $' + sprice.toFixed(2) + ' × 0.85'
                + (sprice < 35 ? ' − 2.99' : '')
                + ') for SKU: ' + sku + '?'
            )) return;

            pushPiTemuPriceForRow(row, sprice).then(function() {
                showToast('Price pushed to Temu', 'success');
            }).catch(function(err) {
                showToast((err && err.message) || 'Failed to push price', 'error');
            });
        });

        $('#pi-push-temu-price-btn').on('click', function() {
            if (!table) {
                showToast('Table not ready', 'error');
                return;
            }
            const items = [];
            const rows = collectPiTemuSpriceRows();
            rows.forEach(function(d) {
                const sprice = parseFloat(d.temu_sprice) || 0;
                const pushBase = temuPushBaseFromSprice(sprice);
                if (sprice <= 0 || pushBase == null || d.temu_push_status === 'pushed') return;
                const row = findPiTableRowBySku(d.sku);
                if (row) items.push({ row: row, price: sprice, sku: d.sku, pushBase: pushBase });
            });

            if (items.length === 0) {
                showToast('No rows with Temu SPRICE to push (or all already pushed)', 'warning');
                return;
            }
            if (!confirm(
                'Push Temu base for ' + items.length + ' SKU(s)?\n'
                + '(Sprice × 0.85) − 2.99 if SPRICE < $35; else Sprice × 0.85'
            )) return;

            const $btn = $('#pi-push-temu-price-btn');
            const btnHtml = $btn.html();
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
            let ok = 0, fail = 0, i = 0;

            function next() {
                if (i >= items.length) {
                    $btn.prop('disabled', false).html(btnHtml);
                    showToast('Temu push done: ' + ok + ' ok, ' + fail + ' failed', fail ? 'warning' : 'success');
                    return;
                }
                const item = items[i++];
                pushPiTemuPriceForRow(item.row, item.price).then(function() {
                    ok++;
                    setTimeout(next, 250);
                }).catch(function() {
                    fail++;
                    setTimeout(next, 250);
                });
            }
            next();
        });

        $('#pi-clear-temu-sprice-btn').on('click', function() {
            if (!table) {
                showToast('Table not ready', 'error');
                return;
            }
            const rows = collectPiTemuSpriceRows().filter(function(d) {
                return (parseFloat(d.temu_sprice) || 0) > 0;
            });
            if (rows.length === 0) {
                showToast('No Temu SPRICE to clear', 'warning');
                return;
            }
            if (!confirm('Clear Temu SPRICE for ' + rows.length + ' SKU(s)?')) return;

            const $btn = $('#pi-clear-temu-sprice-btn');
            const btnHtml = $btn.html();
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
            let ok = 0, fail = 0, done = 0;

            rows.forEach(function(d) {
                $.ajax({
                    url: '/cvr-master-save-suggested-data',
                    method: 'POST',
                    data: {
                        sku: d.sku,
                        marketplace: 'temu',
                        sprice: 0,
                        sgpft: 0,
                        spft: 0,
                        sroi: 0,
                        _token: '{{ csrf_token() }}'
                    },
                    success: function() {
                        ok++;
                        const row = findPiTableRowBySku(d.sku);
                        if (row) {
                            row.update({ temu_sprice: null, temu_push_status: null });
                            row.reformat();
                        }
                        if (Array.isArray(fullDataset)) {
                            const key = String(d.sku || '').trim().toUpperCase();
                            fullDataset.forEach(function(r) {
                                if (!r || r.is_parent_summary) return;
                                if (String(r.sku || '').trim().toUpperCase() === key) {
                                    r.temu_sprice = null;
                                    r.temu_push_status = null;
                                }
                            });
                        }
                    },
                    error: function() { fail++; },
                    complete: function() {
                        done++;
                        if (done >= rows.length) {
                            $btn.prop('disabled', false).html(btnHtml);
                            showToast('Cleared Temu SPRICE: ' + ok + ' ok' + (fail ? (', ' + fail + ' failed') : ''), fail ? 'warning' : 'success');
                        }
                    }
                });
            });
        });

        /** Recompute parent-row SP from children: lowest value; mixed → (1); any missing → red. */
        function refreshParentStandardPriceSummary(parentKey) {
            const parentNorm = String(parentKey || '').trim().toUpperCase();
            if (!parentNorm || !Array.isArray(fullDataset)) return;
            const childVals = [];
            let childCount = 0;
            let missing = false;
            fullDataset.forEach(function(row) {
                if (!row || row.is_parent_summary) return;
                if (String(row.parent || '').trim().toUpperCase() !== parentNorm) return;
                childCount++;
                const v = parseFloat(row.amazon_standard_price);
                if (isFinite(v) && v > 0) {
                    childVals.push(+v.toFixed(2));
                } else {
                    missing = true;
                }
            });
            if (childCount === 0) missing = false;
            const unique = Array.from(new Set(childVals));
            const lowest = unique.length ? Math.min.apply(null, unique) : null;
            const mixed = unique.length > 1;
            const patch = {
                amazon_standard_price: lowest,
                amazon_standard_price_mixed: mixed,
                amazon_standard_price_missing: missing
            };
            fullDataset.forEach(function(row) {
                if (!row || !row.is_parent_summary) return;
                if (String(row.parent || '').trim().toUpperCase() !== parentNorm) return;
                row.amazon_standard_price = lowest;
                row.amazon_standard_price_mixed = mixed;
                row.amazon_standard_price_missing = missing;
            });
            if (!table) return;
            (table.getRows() || []).forEach(function(r) {
                const d = r.getData();
                if (!d || !d.is_parent_summary) return;
                if (String(d.parent || '').trim().toUpperCase() !== parentNorm) return;
                r.update(patch);
            });
        }

        /** Apply STANDARD_PRICE to SKU + any linked SKUs returned by /save-amazon-sprice. */
        function applyStandardPriceToPriceIncreaseRows(sku, std, appliedSkus) {
            if (!table) return;
            const target = String(sku || '').trim().toUpperCase();
            const appliedSet = new Set(
                (Array.isArray(appliedSkus) ? appliedSkus : [])
                    .map(function(s) { return String(s || '').trim().toUpperCase(); })
                    .filter(Boolean)
            );
            if (target) appliedSet.add(target);
            const parentsToRefresh = new Set();
            (table.getRows() || []).forEach(function(r) {
                const d = r.getData();
                if (!d || d.is_parent_summary) return;
                const rowSku = String(d.sku || '').trim().toUpperCase();
                if (!rowSku || !appliedSet.has(rowSku)) return;
                r.update({ amazon_standard_price: std });
                if (d.parent) parentsToRefresh.add(String(d.parent).trim());
            });
            // Keep fullDataset in sync so parent/expand views keep the new SP
            if (Array.isArray(fullDataset)) {
                fullDataset.forEach(function(row) {
                    if (!row || row.is_parent_summary) return;
                    const rowSku = String(row.sku || '').trim().toUpperCase();
                    if (rowSku && appliedSet.has(rowSku)) {
                        row.amazon_standard_price = std;
                        if (row.parent) parentsToRefresh.add(String(row.parent).trim());
                    }
                });
            }
            parentsToRefresh.forEach(function(p) { refreshParentStandardPriceSummary(p); });
        }

        // Shared LMP modal SP box (lmp-modal-sp.js) → keep grid SP + modal toolbar SP in sync
        document.addEventListener('lmp-modal-sp-saved', function(e) {
            const d = e && e.detail ? e.detail : {};
            applyStandardPriceToPriceIncreaseRows(d.sku, d.standard_price, d.applied_skus);
            const saved = parseFloat(d.standard_price);
            if (isFinite(saved) && saved > 0) {
                setModalSharedStandardPrice(saved);
                refreshModalStdPrcCells(saved);
                syncModalToolbarSpInput(saved);
            }
        });

        // Amz SPRICE / SP edited in table
        table.on('cellEdited', function(cell) {
            const field = cell.getField();
            const row = cell.getRow();
            const rowData = row.getData();
            if (rowData.is_parent_summary === true) return;
            const sku = rowData.sku;

            // SP (Standard Price) — same store as /amazon-tabulator-view via /save-amazon-sprice
            if (field === 'amazon_standard_price') {
                const std = parseFloat(cell.getValue());
                if (!sku || !isFinite(std) || std <= 0) {
                    row.update({ amazon_standard_price: null });
                    if (Array.isArray(fullDataset)) {
                        const skuU = String(sku || '').trim().toUpperCase();
                        fullDataset.forEach(function(r) {
                            if (!r || r.is_parent_summary) return;
                            if (String(r.sku || '').trim().toUpperCase() === skuU) {
                                r.amazon_standard_price = null;
                            }
                        });
                    }
                    if (rowData.parent) refreshParentStandardPriceSummary(rowData.parent);
                    return;
                }
                $.ajax({
                    url: '/save-amazon-sprice',
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: {
                        sku: sku,
                        sprice: std,
                        is_standard_price: 1,
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        const saved = parseFloat(response.data || response.STANDARD_PRICE || std) || std;
                        applyStandardPriceToPriceIncreaseRows(sku, saved, response.applied_skus);
                        if (typeof setModalSharedStandardPrice === 'function') {
                            setModalSharedStandardPrice(saved);
                        }
                        if (typeof refreshModalStdPrcCells === 'function') {
                            refreshModalStdPrcCells(saved);
                        } else if (typeof refreshModalAmazonStdPrcCells === 'function') {
                            refreshModalAmazonStdPrcCells(saved);
                        }
                        if (typeof syncModalToolbarSpInput === 'function') {
                            syncModalToolbarSpInput(saved);
                        }
                        const n = Array.isArray(response.applied_skus) ? response.applied_skus.length : 1;
                        showToast(n > 1
                            ? ('Standard Price (SP) saved for ' + n + ' linked SKUs')
                            : 'Standard Price (SP) saved', 'success');
                    },
                    error: function() {
                        showToast('Failed to save Standard Price', 'error');
                    }
                });
                return;
            }

            // Temu S PRC — same as /temu-decrease via /temu-pricing/save-sprice
            if (field === 'temu_sprice') {
                const newSprice = parseFloat(cell.getValue());
                if (!sku) return;
                if (!isFinite(newSprice) || newSprice <= 0) {
                    row.update({ temu_sprice: null, temu_push_status: null });
                    row.reformat();
                    // Clear stored SPRICE (same keys as /temu-decrease)
                    $.ajax({
                        url: '/cvr-master-save-suggested-data',
                        method: 'POST',
                        data: {
                            sku: sku,
                            marketplace: 'temu',
                            sprice: 0,
                            sgpft: 0,
                            spft: 0,
                            sroi: 0,
                            _token: '{{ csrf_token() }}'
                        }
                    });
                    return;
                }
                row.update({ temu_sprice: +newSprice.toFixed(2), temu_push_status: null });
                row.reformat();
                $.ajax({
                    url: '/temu-pricing/save-sprice',
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        sku: sku,
                        sprice: newSprice
                    },
                    success: function() {
                        if (Array.isArray(fullDataset)) {
                            fullDataset.forEach(function(r) {
                                if (!r || r.is_parent_summary) return;
                                if (String(r.sku || '').trim().toUpperCase() === String(sku).trim().toUpperCase()) {
                                    r.temu_sprice = +newSprice.toFixed(2);
                                    r.temu_push_status = null;
                                }
                            });
                        }
                        showToast('Temu SPRICE saved', 'success');
                    },
                    error: function() {
                        showToast('Failed to save Temu SPRICE', 'error');
                    }
                });
                return;
            }

            if (field !== 'amazon_sprice') return;
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

        /** Red triangle when SPRICE > LMP (OV L30 modal). */
        function updateOvl30SpriceLmpAlert($row) {
            if (!$row || !$row.length) return;
            const $wrap = $row.find('.ovl30-sprice-wrap');
            if (!$wrap.length) return;
            const $input = $row.find('.editable-sprice');
            const sprice = $input.length
                ? (parseFloat(String($input.val() || '').replace(/[$,\s]/g, '')) || 0)
                : 0;
            const lmp = parseFloat($row.attr('data-lmp')) || 0;
            $wrap.find('.ovl30-sprice-lmp-alert').remove();
            if (sprice > 0 && lmp > 0 && sprice > lmp) {
                $wrap.append(
                    '<i class="fas fa-exclamation-triangle ovl30-sprice-lmp-alert" title="SPRICE $'
                    + sprice.toFixed(2) + ' > LMP $' + lmp.toFixed(2) + '"></i>'
                );
            }
        }

        // Real-time calculation when SPRICE changes (OVL30 modal – same formula as main table)
        $(document).on('input', '.editable-sprice', function() {
            const input = $(this);
            const row = input.closest('tr');
            const sprice = parseFloat(input.val()) || 0;
            const lp = parseFloat(row.attr('data-lp')) || 0;
            const ad = parseFloat(row.attr('data-ad')) || 0;
            const mpForMargin = String(row.attr('data-marketplace') || '').toLowerCase();
            const isPpEdit = (mpForMargin === 'ppower' || mpForMargin === 'purchasingpower' || mpForMargin === 'purchase');
            const isTdEdit = (mpForMargin === 'topdawg');
            const isSheinEdit = (mpForMargin === 'shein');
            const isFaireEdit = (mpForMargin === 'faire');
            const isTiktok2Edit = (mpForMargin === 'tiktok2' || mpForMargin === 'tiktok 2');
            const isNoAdsEdit = (mpForMargin === 'doba' || isPpEdit || isTdEdit || isSheinEdit || isFaireEdit || isTiktok2Edit);
            // PPower/TopDawg: ship excluded
            const ship = (isPpEdit || isTdEdit) ? 0 : (parseFloat(row.attr('data-ship')) || 0);
            const tacosCh = isNoAdsEdit ? 0 : (parseFloat(row.attr('data-tacos-ch')) || 0);
            const marginAttr = row.attr('data-margin');
            const margin = (marginAttr !== null && marginAttr !== undefined && marginAttr !== '')
                ? parseFloat(marginAttr)
                : (mpForMargin === 'doba' ? 0.95
                    : ((mpForMargin === 'reverb' || ['ebay2', 'ebaytwo', 'ebay3', 'ebaythree'].includes(mpForMargin)) ? 0.85
                        : (isPpEdit ? 0.65 : (isTdEdit ? 0 : 0.80))));
            const l30 = parseFloat(row.attr('data-l30')) || 0;
            
            const $sgpftSpan = row.find('.calculated-sgpft');
            const $spftSpan = row.find('.calculated-spft');
            const $roiSpan = row.find('.calculated-sroi');
            const $snroiSpan = row.find('.calculated-snroi');

            updateOvl30SpriceLmpAlert(row);
            
            if (sprice > 0) {
                const mpLower = String(row.attr('data-marketplace') || '').toLowerCase();
                const isTemuMp = (mpLower === 'temu' || mpLower === 'temu2');
                const isTemu2Mp = (mpLower === 'temu2');
                const isNoAdsMp = (mpLower === 'doba' || isPpEdit || isTdEdit || isSheinEdit || isFaireEdit || isTiktok2Edit);
                const calcSp = isTemuMp ? (sprice <= 26.99 ? sprice + 2.99 : sprice) : sprice;
                // Temu/Temu2: Profit = (Sprice × 0.80) − ship − LP; SGPFT = Profit/Sprice; SROI = Profit/LP
                const temuProfit = isTemuMp ? ((sprice * 0.80) - ship - lp) : 0;
                const sgpft = isTemuMp
                    ? (temuProfit / sprice) * 100
                    : ((calcSp * margin - ship - lp) / calcSp) * 100;
                // Doba/Shein: no ads; Reverb/eBay/TikTok: SGPFT − channel Ads%
                const isChannelAdsMp = (mpLower === 'tiktok' || mpLower === 'reverb'
                    || ['ebay', 'ebay1', 'ebaytwo', 'ebay2', 'ebaythree', 'ebay3'].includes(mpLower));
                const spft = isNoAdsMp ? sgpft
                    : ((isChannelAdsMp || isTemuMp) ? (sgpft - tacosCh) : (l30 == 0 ? sgpft : (sgpft - ad)));
                const sroi = isTemuMp
                    ? (lp > 0 ? (temuProfit / lp) * 100 : 0)
                    : (lp > 0 ? ((calcSp * margin - lp - ship) / lp) * 100 : 0);
                const snroi = lp > 0
                    ? (isNoAdsMp || isTemu2Mp
                        ? sroi
                        : (isTemuMp
                            ? ((tacosCh === 100) ? sroi : (sroi - tacosCh))
                            : (((calcSp * margin - lp - ship) - calcSp * (tacosCh / 100)) / lp) * 100))
                    : 0;
                
                applyCellColor($sgpftSpan, getSgpftSpftColor(sgpft));
                $sgpftSpan.text(Math.round(sgpft) + '%');
                applyCellColor($spftSpan, getSgpftSpftColor(spft));
                $spftSpan.text(Math.round(spft) + '%');
                applyCellColor($roiSpan, getSroiColor(sroi));
                $roiSpan.text(Math.round(sroi) + '%');
                applyCellColor($snroiSpan, getSroiColor(snroi));
                $snroiSpan.text(Math.round(snroi) + '%');
                row.find('.push-price-btn').prop('disabled', false).attr('title', 'Push price to ' + (row.attr('data-marketplace') || ''));
            } else {
                applyCellColor($sgpftSpan, '#6c757d');
                $sgpftSpan.text('-');
                applyCellColor($spftSpan, '#6c757d');
                $spftSpan.text('-');
                applyCellColor($roiSpan, '#6c757d');
                $roiSpan.text('-');
                applyCellColor($snroiSpan, '#6c757d');
                $snroiSpan.text('-');
                // Do not allow push when SPRICE is 0 / empty / null
                row.find('.push-price-btn').prop('disabled', true).attr('title', 'Enter a SPRICE greater than 0 to push');
            }
        });
        
        // STD PRC (Standard Price) — shared SP for all marketplaces; same store as /amazon-tabulator SP
        $(document).on('blur', '.editable-std-prc', function() {
            const input = $(this);
            const row = input.closest('tr');
            const sku = getModalPrimarySku() || row.attr('data-sku');
            if (!sku || String(sku).indexOf('PARENT') !== -1 || String(sku) === 'Not Listed') return;

            const raw = String(input.val() || '').trim();
            if (raw === '') return; // leave blank — do not clear on empty blur
            const std = parseFloat(raw);
            if (!isFinite(std) || std <= 0) {
                input.val('');
                return;
            }

            input.css('border-color', '#20c997');
            $.ajax({
                url: '/save-amazon-sprice',
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: {
                    sku: sku,
                    sprice: std,
                    is_standard_price: 1,
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    const saved = parseFloat(response.data || response.STANDARD_PRICE || std) || std;
                    setModalSharedStandardPrice(saved);
                    if (typeof applyStandardPriceToPriceIncreaseRows === 'function') {
                        applyStandardPriceToPriceIncreaseRows(sku, saved, response.applied_skus);
                    }
                    refreshModalStdPrcCells(saved);
                    syncModalToolbarSpInput(saved);
                    const $wrap = row.find('.ovl30-std-prc-wrap');
                    $wrap.find('.editable-std-prc').css('border-color', '#28a745');
                    setTimeout(function() {
                        $wrap.find('.editable-std-prc').css('border-color', '');
                    }, 800);
                    const n = Array.isArray(response.applied_skus) ? response.applied_skus.length : 1;
                    showToast(n > 1
                        ? ('STD PRC saved for all marketplaces (' + n + ' linked SKUs)')
                        : 'STD PRC saved for all marketplaces', 'success');
                },
                error: function() {
                    input.css('border-color', '#dc3545');
                    showToast('Failed to save STD PRC', 'error');
                }
            });
        });
        $(document).on('keydown', '.editable-std-prc', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                $(this).blur();
            }
        });

        // Toolbar SP — same store/API as STD PRC on all marketplace rows
        $(document).on('blur', '#modal-std-sp-input', function() {
            const input = $(this);
            const sku = getModalPrimarySku();
            if (!sku || String(sku).indexOf('PARENT') !== -1) return;

            const raw = String(input.val() || '').trim();
            if (raw === '') return; // leave blank — do not clear on empty blur
            const std = parseFloat(raw);
            if (!isFinite(std) || std <= 0) {
                input.val('');
                return;
            }

            input.css('border-color', '#20c997');
            $.ajax({
                url: '/save-amazon-sprice',
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: {
                    sku: sku,
                    sprice: std,
                    is_standard_price: 1,
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    const saved = parseFloat(response.data || response.STANDARD_PRICE || std) || std;
                    setModalSharedStandardPrice(saved);
                    if (typeof applyStandardPriceToPriceIncreaseRows === 'function') {
                        applyStandardPriceToPriceIncreaseRows(sku, saved, response.applied_skus);
                    }
                    refreshModalStdPrcCells(saved);
                    syncModalToolbarSpInput(saved);
                    input.css('border-color', '#28a745');
                    setTimeout(function() { input.css('border-color', ''); }, 800);
                    const n = Array.isArray(response.applied_skus) ? response.applied_skus.length : 1;
                    showToast(n > 1
                        ? ('STD PRC saved for ' + n + ' linked SKUs')
                        : 'STD PRC saved', 'success');
                },
                error: function() {
                    input.css('border-color', '#dc3545');
                    showToast('Failed to save STD PRC', 'error');
                }
            });
        });
        $(document).on('keydown', '#modal-std-sp-input', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                $(this).blur();
            }
        });

        // ==================== PROMOTION (shared like STD PRC) ====================
        // 10% → SPRICE × 0.90; 10 → SPRICE − $10. Applies to all listed editable channels.
        function parseModalPromotion(raw) {
            const s = String(raw == null ? '' : raw).trim();
            if (!s) return null;
            const pct = s.match(/^(-?\d+(?:\.\d+)?)\s*%$/);
            if (pct) {
                const value = parseFloat(pct[1]);
                if (!isFinite(value) || value === 0) return null;
                return { type: 'percent', value: value, label: value + '%' };
            }
            const num = parseFloat(s.replace(/[$,\s]/g, '').replace(',', '.'));
            if (!isFinite(num) || num === 0) return null;
            // Bare number = dollar off (not percent)
            return { type: 'dollar', value: num, label: '$' + Math.abs(num).toFixed(2) };
        }

        function applyPromotionToSpriceBase(base, promo) {
            if (!(base > 0) || !promo) return null;
            let next = promo.type === 'percent'
                ? base * (1 - (promo.value / 100))
                : base - promo.value;
            return Math.max(0.01, +Number(next).toFixed(2));
        }

        function syncModalPromotionInputs(raw, exceptEl) {
            modalPromotionRaw = String(raw == null ? '' : raw);
            const $except = exceptEl ? $(exceptEl) : $();
            $('#modal-promo-input, #modal-promo-header-input, .editable-promo').each(function() {
                if ($except.length && this === $except[0]) return;
                if ($(this).is(':focus') && !$except.length) return;
                $(this).val(modalPromotionRaw);
            });
        }

        function collectModalPromoTargetRows() {
            const $rows = [];
            $('#ovl30DetailsTableBody tr[data-marketplace]').each(function() {
                const $tr = $(this);
                if (!$tr.find('.editable-sprice').length) return;
                if (String($tr.attr('data-editable') || '') === '0') return;
                const rowSku = String($tr.attr('data-sku') || '');
                if (!rowSku || rowSku === 'Not Listed') return;
                $rows.push($tr);
            });
            return $rows;
        }

        function applyModalPromotionToAll() {
            const raw = String($('#modal-promo-input').val() || modalPromotionRaw || '').trim();
            const promo = parseModalPromotion(raw);
            if (!promo) {
                showToast('Enter a promotion (e.g. 10% or 10)', 'error');
                $('#modal-promo-input').focus();
                return;
            }
            syncModalPromotionInputs(raw);
            const $rows = collectModalPromoTargetRows();
            if (!$rows.length) {
                showToast('No editable SPRICE rows to apply promotion', 'error');
                return;
            }
            const modeLabel = promo.type === 'percent'
                ? (promo.value + '% less')
                : ('$' + Math.abs(promo.value).toFixed(2) + ' less');
            if (!confirm(
                'Apply promotion (' + modeLabel + ') to SPRICE on ' + $rows.length
                + ' channel(s)' + siblingsApplyLabel() + '?'
            )) return;

            const $btn = $('#modal-apply-promo-btn');
            const origHtml = $btn.html();
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

            let doneCount = 0;
            let okCount = 0;
            let skipped = 0;
            let siblingsOk = 0;

            $rows.forEach(function($tr) {
                const mp = String($tr.attr('data-marketplace') || '');
                let base = parseFloat($tr.find('.editable-sprice').val());
                if (!(base > 0)) base = parseFloat($tr.attr('data-price')) || 0;
                const newPrice = applyPromotionToSpriceBase(base, promo);
                if (!(newPrice > 0)) {
                    skipped++;
                    doneCount++;
                    if (doneCount === $rows.length) {
                        $btn.prop('disabled', false).html(origHtml);
                        showToast(
                            okCount
                                ? ('Promotion applied to ' + okCount + ' channel(s)'
                                    + (skipped ? ('; skipped ' + skipped) : '')
                                    + '. Use Push to go live.')
                                : 'No valid SPRICE to discount',
                            okCount ? 'success' : 'error'
                        );
                    }
                    return;
                }
                $tr.find('.editable-sprice').val(newPrice.toFixed(2)).trigger('input');
                saveModalSpriceForRow($tr, newPrice, function(ok, res) {
                    if (ok) {
                        okCount++;
                        if (res && res.siblings_count) {
                            siblingsOk = Math.max(siblingsOk, parseInt(res.siblings_count, 10) || 0);
                        }
                        if (Array.isArray(ovl30ModalData)) {
                            ovl30ModalData.forEach(function(item) {
                                if (String(item.marketplace || '') === mp) {
                                    item.sprice = newPrice;
                                }
                            });
                        }
                    }
                    doneCount++;
                    if (doneCount === $rows.length) {
                        $btn.prop('disabled', false).html(origHtml);
                        showToast(
                            okCount
                                ? ('Promotion (' + modeLabel + ') applied to ' + okCount + ' channel(s)'
                                    + (siblingsOk ? (' (+' + siblingsOk + ' siblings)') : '')
                                    + (skipped ? ('; skipped ' + skipped) : '')
                                    + '. Use Push to go live.')
                                : 'Failed to apply promotion',
                            okCount ? 'success' : 'error'
                        );
                    }
                });
            });
        }

        $(document).on('input', '#modal-promo-input, #modal-promo-header-input, .editable-promo', function() {
            syncModalPromotionInputs($(this).val(), this);
        });
        $(document).on('click', '#modal-apply-promo-btn', function(e) {
            e.preventDefault();
            applyModalPromotionToAll();
        });
        $(document).on('keydown', '#modal-promo-input, #modal-promo-header-input, .editable-promo', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                syncModalPromotionInputs($(this).val(), this);
                applyModalPromotionToAll();
            }
        });

        // Auto-save on blur
        $(document).on('blur', '.editable-sprice', function() {
            const input = $(this);
            // Paired Temu↔Temu2 / Ebay1↔2↔3 UI sync — skip re-POST (already saved on the source channel)
            if (input.attr('data-temu-synced')) {
                input.removeAttr('data-temu-synced');
                return;
            }
            if (input.attr('data-ebay-synced')) {
                input.removeAttr('data-ebay-synced');
                return;
            }
            const row = input.closest('tr');
            // Always use modal SKU (row data-sku can be "Not Listed" / channel listing SKU)
            const sku = getModalPrimarySku() || row.attr('data-sku');
            const marketplace = row.attr('data-marketplace');
            const sprice = parseFloat(input.val()) || 0;
            
            if (!sku || sprice === 0) return;
            
            const lp = parseFloat(row.attr('data-lp')) || 0;
            const mpLower = String(marketplace || '').toLowerCase();
            const isNoAdsBlur = (mpLower === 'doba' || mpLower === 'ppower' || mpLower === 'purchasingpower'
                || mpLower === 'purchase' || mpLower === 'topdawg' || mpLower === 'shein'
                || mpLower === 'faire' || mpLower === 'tiktok2' || mpLower === 'tiktok 2' || mpLower === 'aliexpress');
            const ship = (mpLower === 'ppower' || mpLower === 'purchasingpower' || mpLower === 'purchase'
                || mpLower === 'topdawg' || mpLower === 'faire') ? 0 : (parseFloat(row.attr('data-ship')) || 0);
            const ad = parseFloat(row.attr('data-ad')) || 0;
            const tacosCh = isNoAdsBlur ? 0 : (parseFloat(row.attr('data-tacos-ch')) || 0);
            const marginAttrBlur = row.attr('data-margin');
            const margin = (marginAttrBlur !== null && marginAttrBlur !== undefined && marginAttrBlur !== '')
                ? parseFloat(marginAttrBlur) : 0.80;
            const l30 = parseFloat(row.attr('data-l30')) || 0;
            const isTemuMp = (mpLower === 'temu' || mpLower === 'temu2');
            const calcSp = isTemuMp ? (sprice <= 26.99 ? sprice + 2.99 : sprice) : sprice;
            
            // Temu/Temu2: Profit = (Sprice × 0.80) − ship − LP; SGPFT = Profit/Sprice; SROI = Profit/LP
            const temuProfit = isTemuMp ? ((sprice * 0.80) - ship - lp) : 0;
            const sgpft = sprice > 0
                ? (isTemuMp
                    ? (temuProfit / sprice) * 100
                    : ((calcSp * margin - ship - lp) / calcSp) * 100)
                : 0;
            const isChannelAdsMp = (mpLower === 'tiktok' || mpLower === 'reverb'
                || ['ebay', 'ebay1', 'ebaytwo', 'ebay2', 'ebaythree', 'ebay3'].includes(mpLower));
            const spft = isNoAdsBlur ? sgpft
                : ((isChannelAdsMp || isTemuMp) ? (sgpft - tacosCh) : (l30 == 0 ? sgpft : (sgpft - ad)));
            const sroi = isTemuMp
                ? (lp > 0 ? (temuProfit / lp) * 100 : 0)
                : (lp > 0 ? ((calcSp * margin - lp - ship) / lp) * 100 : 0);
            
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
                    apply_siblings: siblingsApplyPayload(sprice),
                    _token: '{{ csrf_token() }}'
                },
                success: function(res) {
                    input.css('border-color', '#28a745');
                    setTimeout(() => input.css('border-color', ''), 1000);
                    if (isTemuMp) {
                        syncTemuPairedSpriceInModal(mpLower, sprice);
                    }
                    const isEbayMp = !!ebayChannelFamily(mpLower);
                    if (isEbayMp) {
                        syncEbayPairedSpriceInModal(mpLower, sprice);
                    }
                    const sibN = res && res.siblings_count ? res.siblings_count : 0;
                    let msg = 'Saved!';
                    if (isTemuMp) msg += ' (Temu + Temu2)';
                    if (isEbayMp) msg += ' (Ebay1 + Ebay2 + Ebay3)';
                    if (sibN) msg += ' (+' + sibN + ' siblings)';
                    showToast(msg, 'success');
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

        /**
         * Clear SPRICE on every editable marketplace row for the open SKU.
         * This SKU only — siblings never get 0 (same rule as Prc Mode → Clear SPRICE).
         */
        function clearAllMarketplaceSpriceInModal() {
            const sku = getModalPrimarySku();
            if (!sku || sku.toUpperCase() === 'NOT LISTED') {
                showToast('No SKU loaded in modal', 'error');
                return;
            }
            const $rows = [];
            $('#ovl30DetailsTableBody tr').each(function() {
                const $tr = $(this);
                if (!$tr.find('.editable-sprice').length) return;
                // Only clear rows that currently have a SPRICE (or any editable listed row)
                const mp = String($tr.attr('data-marketplace') || '');
                if (!mp) return;
                $rows.push($tr);
            });
            if (!$rows.length) {
                showToast('No editable SPRICE rows to clear', 'warning');
                return;
            }
            if (!confirm(
                'Clear SPRICE on ALL ' + $rows.length + ' marketplace(s) for SKU: ' + sku + '?\n\n'
                + 'This SKU only — siblings are never cleared to 0.'
            )) return;

            const $btn = $('#modal-clear-all-sprice-btn');
            const origHtml = $btn.html();
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Clearing...');
            let doneCount = 0;
            let okCount = 0;

            $rows.forEach(function($tr) {
                const mp = String($tr.attr('data-marketplace') || '');
                $tr.find('.editable-sprice').val('').trigger('input');
                saveModalSpriceForRow($tr, 0, function(ok) {
                    if (ok) {
                        okCount++;
                        ovl30ModalData.forEach(function(item) {
                            if (String(item.marketplace || '') === mp) {
                                item.sprice = 0;
                                item.sgpft = 0;
                                item.sroi = 0;
                                item.spft = 0;
                            }
                        });
                    }
                    doneCount++;
                    if (doneCount === $rows.length) {
                        $btn.prop('disabled', false).html(origHtml);
                        showToast(
                            okCount
                                ? ('Cleared SPRICE on ' + okCount + '/' + $rows.length + ' marketplace(s)')
                                : 'Failed to clear SPRICE',
                            okCount ? 'success' : 'error'
                        );
                    }
                });
            });
        }

        $(document).on('click', '#modal-clear-all-sprice-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            clearAllMarketplaceSpriceInModal();
        });

        function saveModalSpriceForRow($row, sprice, done, opts) {
            opts = opts || {};
            const sku = getModalPrimarySku() || $row.attr('data-sku');
            const marketplace = $row.attr('data-marketplace');
            const lp = parseFloat($row.attr('data-lp')) || 0;
            const ad = parseFloat($row.attr('data-ad')) || 0;
            const mpLower = String(marketplace || '').toLowerCase();
            const isPpMp = (mpLower === 'ppower' || mpLower === 'purchasingpower' || mpLower === 'purchase');
            const isTdMp = (mpLower === 'topdawg');
            const isSheinMp = (mpLower === 'shein');
            const isFaireMp = (mpLower === 'faire');
            const isTiktok2Mp = (mpLower === 'tiktok2' || mpLower === 'tiktok 2');
            const isNoAdsMp = (mpLower === 'doba' || isPpMp || isTdMp || isSheinMp || isFaireMp || isTiktok2Mp);
            // PPower/TopDawg: ship excluded (Shein uses product_master ship)
            const ship = (isPpMp || isTdMp || isFaireMp) ? 0 : (parseFloat($row.attr('data-ship')) || 0);
            const tacosCh = isNoAdsMp ? 0 : (parseFloat($row.attr('data-tacos-ch')) || 0);
            const marginAttrSave = $row.attr('data-margin');
            const margin = (marginAttrSave !== null && marginAttrSave !== undefined && marginAttrSave !== '')
                ? parseFloat(marginAttrSave)
                : (mpLower === 'doba' ? 0.95
                    : ((mpLower === 'reverb' || ['ebay2', 'ebaytwo', 'ebay3', 'ebaythree'].includes(mpLower)) ? 0.85
                        : (isPpMp ? 0.65 : (isTdMp ? 0 : 0.80))));
            const l30 = parseFloat($row.attr('data-l30')) || 0;
            const isTemuMp = (mpLower === 'temu' || mpLower === 'temu2');
            const calcSp = isTemuMp ? (sprice <= 26.99 ? sprice + 2.99 : sprice) : sprice;
            // Temu/Temu2: Profit = (Sprice × 0.80) − ship − LP; SGPFT = Profit/Sprice; SROI = Profit/LP
            const temuProfit = isTemuMp ? ((sprice * 0.80) - ship - lp) : 0;
            const sgpft = sprice > 0
                ? (isTemuMp
                    ? (temuProfit / sprice) * 100
                    : ((calcSp * margin - ship - lp) / calcSp) * 100)
                : 0;
            const isChannelAdsMp = (mpLower === 'tiktok' || mpLower === 'reverb'
                || ['ebay', 'ebay1', 'ebaytwo', 'ebay2', 'ebaythree', 'ebay3'].includes(mpLower));
            const spft = isNoAdsMp ? sgpft
                : ((isChannelAdsMp || isTemuMp) ? (sgpft - tacosCh) : (l30 == 0 ? sgpft : (sgpft - ad)));
            const sroi = isTemuMp
                ? (lp > 0 ? (temuProfit / lp) * 100 : 0)
                : (lp > 0 ? ((calcSp * margin - lp - ship) / lp) * 100 : 0);

            if (!sku || !marketplace) {
                if (done) done(false);
                return;
            }

            const applySiblings = (opts.applySiblings !== undefined)
                ? (opts.applySiblings && sprice > 0 ? 1 : 0)
                : siblingsApplyPayload(sprice);

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
                    apply_siblings: applySiblings,
                    _token: '{{ csrf_token() }}'
                },
                success: function(res) {
                    if (isTemuMp) {
                        syncTemuPairedSpriceInModal(mpLower, sprice);
                    }
                    if (ebayChannelFamily(mpLower)) {
                        syncEbayPairedSpriceInModal(mpLower, sprice);
                    }
                    if (done) done(true, res);
                },
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

        // Header select-all: only visible rows (respects Group filter)
        $(document).on('change', '#ovl30-select-all-cb', function() {
            const on = $(this).is(':checked');
            $(this).prop('indeterminate', false);
            getVisibleModalRowCbs().each(function() {
                const mp = String($(this).attr('data-marketplace') || '');
                $(this).prop('checked', on);
                if (!mp) return;
                if (on) modalSelectedChannels.add(mp);
                else modalSelectedChannels.delete(mp);
            });
            updateModalPrcSelectedCount();
        });

        $(document).on('click', '#modal-select-all-channels-btn', function(e) {
            e.preventDefault();
            getVisibleModalRowCbs().each(function() {
                const mp = String($(this).attr('data-marketplace') || '');
                $(this).prop('checked', true);
                if (mp) modalSelectedChannels.add(mp);
            });
            updateModalPrcSelectedCount();
        });

        $(document).on('change', '#modal-group-select, #modal-group-select-bar', function() {
            syncModalGroupSelects($(this).val());
            applyModalGroupFilter({ selectChannels: true });
        });

        $(document).on('change', '#modal-discount-type-select', function() {
            $('#modal-discount-percentage-input').attr(
                'placeholder',
                $(this).val() === 'percentage' ? 'Enter percentage' : 'Enter value ($)'
            );
        });

        $('#ovl30DetailsModal').on('hide.bs.modal', function() {
            // User closed — stop in-flight load so it cannot re-show / flip back to "Loading..."
            ovl30ModalClosedByUser = true;
            ovl30BreakdownReqId++;
            if (ovl30BreakdownXhr && typeof ovl30BreakdownXhr.abort === 'function') {
                try { ovl30BreakdownXhr.abort(); } catch (e) { /* ignore */ }
                ovl30BreakdownXhr = null;
            }
        });

        $('#ovl30DetailsModal').on('hidden.bs.modal', function() {
            exitModalPricePctMode(false);
            syncModalGroupSelects('');
            // After push-reload used to stack backdrops; clear any leftover black screen
            setTimeout(cleanupOrphanModalBackdrops, 50);
        });

        $(document).on('click', '#modal-apply-discount-btn', function() {
            if (!modalPrcModeActive) {
                showToast('Choose Decrease, Increase, Same Price, or Clear SPRICE from Prc Mode first', 'error');
                return;
            }
            if (modalSelectedChannels.size === 0) {
                showToast('Please select at least one channel (checkbox column)', 'error');
                return;
            }

            const $rows = [];
            const seenMp = {};
            $('#ovl30DetailsTableBody tr').each(function() {
                const $tr = $(this);
                const mp = String($tr.attr('data-marketplace') || '');
                if (!modalSelectedChannels.has(mp)) return;
                if (!$tr.find('.editable-sprice').length) return;
                $rows.push($tr);
                seenMp[mp.toLowerCase()] = true;
            });
            // Same Price: always include Doba (−25%) and SB2B/TopDawg ((SP×0.80)−Ship) even if unchecked
            if (modalSamePriceModeActive && !seenMp.doba) {
                const $doba = findModalDobaEditableRow();
                if ($doba.length) $rows.push($doba);
            }
            if (!$rows.length) {
                showToast('No editable SPRICE rows selected', 'error');
                return;
            }

            // Clear SPRICE — no price input required (never clears siblings; Do NOT apply 0)
            if (modalClearSpriceModeActive) {
                if (isModalSiblingsApply()) {
                    showToast('Clear SPRICE applies to this SKU only (siblings never get 0)', 'info');
                }
                if (!confirm('Clear SPRICE for ' + $rows.length + ' channel(s) on this SKU only?')) return;
                const $btn = $(this);
                const origHtml = $btn.html();
                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Clearing...');
                let doneCount = 0;
                let okCount = 0;
                $rows.forEach(function($tr) {
                    const mp = String($tr.attr('data-marketplace') || '');
                    const $input = $tr.find('.editable-sprice');
                    $input.val('').trigger('input');
                    // Force apply_siblings off via price=0 guard in saveModalSpriceForRow
                    saveModalSpriceForRow($tr, 0, function(ok) {
                        if (ok) {
                            okCount++;
                            // Keep in-memory modal data in sync
                            ovl30ModalData.forEach(function(item) {
                                if (String(item.marketplace || '') === mp) {
                                    item.sprice = 0;
                                    item.sgpft = 0;
                                    item.sroi = 0;
                                    item.spft = 0;
                                }
                            });
                        }
                        doneCount++;
                        if (doneCount === $rows.length) {
                            $btn.prop('disabled', false).html(origHtml);
                            showToast(okCount
                                ? ('Cleared SPRICE on ' + okCount + ' channel(s)')
                                : 'Failed to clear SPRICE', okCount ? 'success' : 'error');
                        }
                    });
                });
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

            const mode = modalSamePriceModeActive ? 'same' : (modalIncreaseModeActive ? 'increase' : 'decrease');
            const actionLabel = mode === 'same' ? 'Same Price' : (mode === 'increase' ? 'Increase' : 'Decrease');
            const withSiblings = isModalSiblingsApply();
            if (!confirm(
                actionLabel + ' SPRICE for ' + $rows.length + ' channel(s)' + siblingsApplyLabel()
                + (withSiblings ? '\n\nSibling SKUs (including INV 0) will get the same new SPRICE.' : '')
                + '?'
            )) return;

            const $btn = $(this);
            const origHtml = $btn.html();

            function runPrcModeApply() {
                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Applying...');

                let doneCount = 0;
                let okCount = 0;
                let siblingsOk = 0;
                let skipped = 0;

                function finishIfDone() {
                    if (doneCount !== $rows.length) return;
                    $btn.prop('disabled', false).html(origHtml);
                    showToast(okCount
                        ? (actionLabel + ' applied to ' + okCount + ' channel(s)'
                            + (skipped ? ' (' + skipped + ' skipped)' : '')
                            + (siblingsOk ? (' (+' + siblingsOk + ' siblings, incl. INV 0)') : '')
                            + '. Use Push to go live.')
                        : (skipped === $rows.length
                            ? 'Selected channels have no Price'
                            : 'Failed to save SPRICE'),
                        okCount ? 'success' : 'error');
                    $('#modal-discount-percentage-input').val('');
                }

                $rows.forEach(function($tr) {
                    const mp = String($tr.attr('data-marketplace') || '');
                    const mpLower = mp.toLowerCase();
                    const basePrice = parseFloat($tr.attr('data-price')) || 0;
                    const $input = $tr.find('.editable-sprice');
                    if (mode !== 'same' && !(basePrice > 0)) {
                        skipped++;
                        doneCount++;
                        finishIfDone();
                        return;
                    }
                    let newPrice = computeModalModePrice(basePrice, mode, inputValue, discountType);
                    // Same-price apply: Doba −25%; SB2B/TopDawg/Faire = ×0.80 − Ship; PPower = ×1.15 − Ship
                    if (mode === 'same') {
                        newPrice = adjustAppliedSpriceForChannel(inputValue, $tr);
                    }
                    const lp = parseFloat($tr.attr('data-lp')) || 0;
                    const ship = parseFloat($tr.attr('data-ship')) || 0;
                    const margin = parseFloat($tr.attr('data-margin')) || 0.80;
                    const isTemuMp = (String(mp).toLowerCase() === 'temu' || String(mp).toLowerCase() === 'temu2');
                    const temuProfit = isTemuMp ? ((newPrice * 0.80) - ship - lp) : 0;
                    const sgpft = newPrice > 0
                        ? (isTemuMp ? (temuProfit / newPrice) * 100 : ((newPrice * margin - ship - lp) / newPrice) * 100)
                        : 0;
                    const sroi = isTemuMp
                        ? (lp > 0 ? (temuProfit / lp) * 100 : 0)
                        : (lp > 0 ? ((newPrice * margin - lp - ship) / lp) * 100 : 0);
                    const metrics = { sgpft: sgpft, spft: sgpft, sroi: sroi, margin: isTemuMp ? 0.80 : margin };

                    $input.val(newPrice.toFixed(2)).trigger('input');
                    saveModalSpriceForRow($tr, newPrice, function(ok) {
                        if (ok) {
                            okCount++;
                            ovl30ModalData.forEach(function(item) {
                                if (String(item.marketplace || '') === mp) item.sprice = newPrice;
                            });
                        }
                        if (ok && withSiblings && modalSiblingSkus.length) {
                            applySpriceToSiblingSkus(mp, newPrice, metrics, function(sibOk) {
                                siblingsOk = Math.max(siblingsOk, sibOk || 0);
                                doneCount++;
                                finishIfDone();
                            });
                        } else {
                            doneCount++;
                            finishIfDone();
                        }
                    }, { applySiblings: false });
                });
            }

            if (withSiblings) {
                refreshModalSiblingSkus(getModalPrimarySku());
                setTimeout(runPrcModeApply, 350);
            } else {
                runPrcModeApply();
            }
        });

        $(document).on('keydown', '#modal-discount-percentage-input', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                $('#modal-apply-discount-btn').trigger('click');
            }
        });

        // ==================== BULK SPRICE ====================
        // Doba ALWAYS −25% (×0.75); SB2B/TopDawg always & Faire = (SP × 0.80) − Ship; PPower = (SP × 1.15) − Ship
        function isDobaChannel(mpLower) {
            return String(mpLower || '').toLowerCase().replace(/\s+/g, '') === 'doba';
        }
        function isBulkSprice25OffChannel(mpLower) {
            return isDobaChannel(mpLower);
        }
        function isBulkSpricePpowerChannel(mpLower) {
            const m = String(mpLower || '').toLowerCase().replace(/\s+/g, '');
            return m === 'ppower' || m === 'purchasingpower' || m === 'purchase';
        }
        function isBulkSpriceSb2bChannel(mpLower) {
            const m = String(mpLower || '').toLowerCase().replace(/\s+/g, '');
            return m === 'sb2b' || m === 'shopifyb2b' || m === 'shopify_b2b';
        }
        function isBulkSpriceTopDawgChannel(mpLower) {
            const m = String(mpLower || '').toLowerCase().replace(/\s+/g, '');
            return m === 'topdawg' || m === 'topdog';
        }
        function isBulkSpriceFaireChannel(mpLower) {
            return String(mpLower || '').toLowerCase().replace(/\s+/g, '') === 'faire';
        }
        /** Channels that use (SP × 0.80) − Ship on Apply */
        function isBulkSprice20OffMinusShipChannel(mpLower) {
            return isBulkSpriceSb2bChannel(mpLower)
                || isBulkSpriceTopDawgChannel(mpLower)
                || isBulkSpriceFaireChannel(mpLower);
        }
        /** Doba: always 25% less than the applied base. */
        function sprice25OffFromBase(basePrice) {
            return Math.max(0.01, +(Number(basePrice) * 0.75).toFixed(2));
        }
        /** SB2B / TopDawg / Faire: (base × 0.80) − Ship */
        function sprice20OffMinusShipFromBase(basePrice, rowShip) {
            const ship = (isFinite(rowShip) && rowShip > 0) ? rowShip : 0;
            return Math.max(0.01, +((Number(basePrice) * 0.80) - ship).toFixed(2));
        }
        /** PPower SP Apply: (base × 1.15) − Ship */
        function ppowerSpriceFromBase(basePrice, rowShip) {
            const ship = (isFinite(rowShip) && rowShip > 0) ? rowShip : 0;
            return Math.max(0.01, +((basePrice * 1.15) - ship).toFixed(2));
        }
        /**
         * Convert a shared apply base into the channel SPRICE.
         * Doba always uses ×0.75 (never the full SP).
         */
        function adjustAppliedSpriceForChannel(basePrice, $tr) {
            const mpLower = String(($tr && $tr.attr('data-marketplace')) || '').toLowerCase();
            const base = Math.max(0.01, +Number(basePrice).toFixed(2));
            if (isBulkSprice25OffChannel(mpLower)) {
                return sprice25OffFromBase(base);
            }
            if (isBulkSpricePpowerChannel(mpLower)) {
                const rowShip = parseFloat($tr.attr('data-ship')) || 0;
                return ppowerSpriceFromBase(base, rowShip);
            }
            if (isBulkSprice20OffMinusShipChannel(mpLower)) {
                const rowShip = parseFloat($tr.attr('data-ship')) || 0;
                return sprice20OffMinusShipFromBase(base, rowShip);
            }
            return base;
        }
        /** Find editable row for marketplace even if hidden / not checked. */
        function findModalEditableRowByMatcher(matcherFn) {
            let $found = $();
            $('#ovl30DetailsTableBody tr[data-marketplace]').each(function() {
                const $tr = $(this);
                if (!matcherFn($tr.attr('data-marketplace'))) return;
                if (!$tr.find('.editable-sprice').length) return;
                if (String($tr.attr('data-editable') || '') === '0') return;
                $found = $tr;
                return false;
            });
            return $found;
        }
        /** Find editable Doba row even if hidden / not checked (Doba always gets −25% on SP apply). */
        function findModalDobaEditableRow() {
            return findModalEditableRowByMatcher(isDobaChannel);
        }
        function findModalSb2bEditableRow() {
            return findModalEditableRowByMatcher(isBulkSpriceSb2bChannel);
        }
        function findModalTopDawgEditableRow() {
            return findModalEditableRowByMatcher(isBulkSpriceTopDawgChannel);
        }

        function applyModalBulkSprice() {
            const rawInput = $('#modal-bulk-sprice-input').val();
            const basePrice = parseFloat(String(rawInput == null ? '' : rawInput).replace(/[$,\s]/g, '').replace(',', '.'));
            if (rawInput === '' || rawInput == null || !isFinite(basePrice) || !(basePrice > 0)) {
                showToast('Please enter a SPRICE greater than 0', 'error');
                $('#modal-bulk-sprice-input').focus();
                return;
            }
            if (modalSelectedChannels.size === 0) {
                showToast('Please select at least one channel (checkbox)', 'error');
                return;
            }
            const $rows = collectModalTargetRows({ alwaysIncludeDoba: true, alwaysIncludeSb2bTopDawg: true });
            if (!$rows.length) {
                showToast('No editable SPRICE rows selected', 'error');
                return;
            }

            const reducedPrice = sprice25OffFromBase(basePrice); // Doba always −25%
            if (!confirm(
                'Apply SPRICE $' + basePrice.toFixed(2) + ' to ' + $rows.length + ' channel(s)'
                + '?\n\nDoba always −25%: $' + reducedPrice.toFixed(2)
                + '\nSB2B / TopDawg / Faire = (SP × 0.80) − Ship'
                + '\nPPower = (SP × 1.15) − Ship'
                + siblingsApplyLabel()
            )) return;

            const $btn = $('#modal-apply-bulk-sprice-btn');
            const origHtml = $btn.html();
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

            let doneCount = 0;
            let okCount = 0;
            let siblingsOk = 0;
            $rows.forEach(function($tr) {
                const mp = String($tr.attr('data-marketplace') || '');
                const newPrice = adjustAppliedSpriceForChannel(basePrice, $tr);
                $tr.find('.editable-sprice').val(newPrice.toFixed(2)).trigger('input');
                saveModalSpriceForRow($tr, newPrice, function(ok, res) {
                    if (ok) {
                        okCount++;
                        if (res && res.siblings_count) {
                            siblingsOk = Math.max(siblingsOk, parseInt(res.siblings_count, 10) || 0);
                        }
                        ovl30ModalData.forEach(function(item) {
                            if (String(item.marketplace || '') === mp) {
                                item.sprice = newPrice;
                            }
                        });
                    }
                    doneCount++;
                    if (doneCount === $rows.length) {
                        $btn.prop('disabled', false).html(origHtml);
                        showToast(
                            okCount
                                ? ('SPRICE applied to ' + okCount + ' channel(s)'
                                    + (siblingsOk ? (' (+' + siblingsOk + ' siblings)') : '')
                                    + '. Doba always −25% ($' + reducedPrice.toFixed(2) + '). Use Push to go live.')
                                : 'Failed to save SPRICE',
                            okCount ? 'success' : 'error'
                        );
                    }
                });
            });
        }

        $(document).on('click', '#modal-apply-bulk-sprice-btn', function(e) {
            e.preventDefault();
            applyModalBulkSprice();
        });
        $(document).on('keydown', '#modal-bulk-sprice-input', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                applyModalBulkSprice();
            }
        });

        // ==================== +$ BUMP SPRICE (add fixed $ to current SPRICE) ====================
        function applyModalSpriceBump() {
            const rawInput = $('#modal-sprice-bump-input').val();
            const bump = parseFloat(String(rawInput == null ? '' : rawInput).replace(/[$,\s]/g, '').replace(',', '.'));
            if (rawInput === '' || rawInput == null || !isFinite(bump) || bump === 0) {
                showToast('Enter a $ amount to add (e.g. 1 or 2)', 'error');
                $('#modal-sprice-bump-input').focus();
                return;
            }
            if (modalSelectedChannels.size === 0) {
                showToast('Please select at least one channel (checkbox)', 'error');
                return;
            }
            const $rows = collectModalTargetRows();
            if (!$rows.length) {
                showToast('No editable SPRICE rows selected', 'error');
                return;
            }

            const bumpLabel = (bump > 0 ? '+' : '') + '$' + Math.abs(bump).toFixed(2);
            const withSiblings = isModalSiblingsApply();

            function runBump() {
                if (!confirm(
                    'Increase SPRICE by ' + bumpLabel + ' on ' + $rows.length + ' selected channel(s)'
                    + siblingsApplyLabel()
                    + (withSiblings ? '\n\nSibling SKUs (including INV 0) will get the same new SPRICE.' : '')
                    + '?'
                )) return;

                const $btn = $('#modal-apply-sprice-bump-btn');
                const origHtml = $btn.html();
                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

                let doneCount = 0;
                let okCount = 0;
                let skipped = 0;
                let siblingsOk = 0;

                function finishIfDone() {
                    if (doneCount !== $rows.length) return;
                    $btn.prop('disabled', false).html(origHtml);
                    showToast(
                        okCount
                            ? ('SPRICE ' + bumpLabel + ' applied to ' + okCount + ' channel(s)'
                                + (skipped ? ' (' + skipped + ' skipped)' : '')
                                + (siblingsOk ? (' (+' + siblingsOk + ' siblings, incl. INV 0)') : '')
                                + '. Use Push to go live.')
                            : (skipped === $rows.length
                                ? 'Selected channels have no SPRICE/Price to increase'
                                : 'Failed to save SPRICE'),
                        okCount ? 'success' : 'error'
                    );
                }

                $rows.forEach(function($tr) {
                    const mp = String($tr.attr('data-marketplace') || '');
                    const $input = $tr.find('.editable-sprice');
                    const currentSprice = parseFloat(String($input.val() || '').replace(/[$,\s]/g, '')) || 0;
                    const basePrice = parseFloat($tr.attr('data-price')) || 0;
                    // Prefer current SPRICE; fall back to live Price if SPRICE empty
                    const base = currentSprice > 0 ? currentSprice : basePrice;
                    if (!(base > 0)) {
                        skipped++;
                        doneCount++;
                        finishIfDone();
                        return;
                    }
                    const newPrice = Math.max(0.01, +(base + bump).toFixed(2));
                    const lp = parseFloat($tr.attr('data-lp')) || 0;
                    const ship = parseFloat($tr.attr('data-ship')) || 0;
                    const margin = parseFloat($tr.attr('data-margin')) || 0.80;
                    const isTemuMp = (String(mp).toLowerCase() === 'temu' || String(mp).toLowerCase() === 'temu2');
                    const temuProfit = isTemuMp ? ((newPrice * 0.80) - ship - lp) : 0;
                    const sgpft = newPrice > 0
                        ? (isTemuMp ? (temuProfit / newPrice) * 100 : ((newPrice * margin - ship - lp) / newPrice) * 100)
                        : 0;
                    const sroi = isTemuMp
                        ? (lp > 0 ? (temuProfit / lp) * 100 : 0)
                        : (lp > 0 ? ((newPrice * margin - lp - ship) / lp) * 100 : 0);
                    const metrics = { sgpft: sgpft, spft: sgpft, sroi: sroi, margin: isTemuMp ? 0.80 : margin };

                    $input.val(newPrice.toFixed(2)).trigger('input');
                    // Primary SKU only here; siblings (incl. INV 0) saved explicitly below
                    saveModalSpriceForRow($tr, newPrice, function(ok) {
                        if (ok) {
                            okCount++;
                            ovl30ModalData.forEach(function(item) {
                                if (String(item.marketplace || '') === mp) item.sprice = newPrice;
                            });
                        }

                        // Explicitly apply same new SPRICE to every sibling (including INV 0)
                        if (ok && withSiblings && modalSiblingSkus.length) {
                            applySpriceToSiblingSkus(mp, newPrice, metrics, function(sibOk) {
                                siblingsOk = Math.max(siblingsOk, sibOk || 0);
                                doneCount++;
                                finishIfDone();
                            });
                        } else {
                            doneCount++;
                            finishIfDone();
                        }
                    }, { applySiblings: false });
                });
            }

            // Refresh sibling list from API first so INV 0 siblings are included
            if (withSiblings) {
                const sku = getModalPrimarySku();
                refreshModalSiblingSkus(sku);
                setTimeout(runBump, 350);
            } else {
                runBump();
            }
        }

        $(document).on('click', '#modal-apply-sprice-bump-btn', function(e) {
            e.preventDefault();
            applyModalSpriceBump();
        });
        $(document).on('keydown', '#modal-sprice-bump-input', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                applyModalSpriceBump();
            }
        });

        // ==================== SAME $ SPRICE (SP / box → channels + siblings) ====================
        // Doba −25%; SB2B/TopDawg/Faire = (SP × 0.80) − Ship; PPower = (SP × 1.15) − Ship
        function applyModalSameSpriceFromBox() {
            const rawInput = $('#modal-sprice-same-input').val();
            const samePrice = parseFloat(String(rawInput == null ? '' : rawInput).replace(/[$,\s]/g, '').replace(',', '.'));
            if (rawInput === '' || rawInput == null || !isFinite(samePrice) || !(samePrice > 0)) {
                showToast('Enter a SPRICE greater than 0 (e.g. 19.99)', 'error');
                $('#modal-sprice-same-input').focus();
                return;
            }
            if (modalSelectedChannels.size === 0) {
                showToast('Please select at least one channel (checkbox)', 'error');
                return;
            }
            const $rows = collectModalTargetRows({ alwaysIncludeDoba: true, alwaysIncludeSb2bTopDawg: true });
            if (!$rows.length) {
                showToast('No editable SPRICE rows selected', 'error');
                return;
            }

            const basePrice = Math.max(0.01, +samePrice.toFixed(2));
            const reducedPrice = sprice25OffFromBase(basePrice); // Doba always −25%
            const withSiblings = isModalSiblingsApply();

            function runSame() {
                if (!confirm(
                    'Set SPRICE to $' + basePrice.toFixed(2) + ' on ' + $rows.length + ' channel(s)'
                    + '?\n\nDoba always −25%: $' + reducedPrice.toFixed(2)
                    + '\nSB2B / TopDawg / Faire = (SP × 0.80) − Ship'
                    + '\nPPower = (SP × 1.15) − Ship'
                    + siblingsApplyLabel()
                    + (withSiblings ? '\n\nSibling SKUs (including INV 0) will get the same per-channel SPRICE.' : '')
                )) return;

                const $btn = $('#modal-apply-sprice-same-btn');
                const origHtml = $btn.html();
                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

                let doneCount = 0;
                let okCount = 0;
                let siblingsOk = 0;

                function finishIfDone() {
                    if (doneCount !== $rows.length) return;
                    $btn.prop('disabled', false).html(origHtml);
                    showToast(
                        okCount
                            ? ('SPRICE applied to ' + okCount + ' channel(s)'
                                + (siblingsOk ? (' (+' + siblingsOk + ' siblings, incl. INV 0)') : '')
                                + '. Doba always −25% ($' + reducedPrice.toFixed(2) + '). Use Push to go live.')
                            : 'Failed to save SPRICE',
                        okCount ? 'success' : 'error'
                    );
                }

                $rows.forEach(function($tr) {
                    const mp = String($tr.attr('data-marketplace') || '');
                    const newPrice = adjustAppliedSpriceForChannel(basePrice, $tr);
                    const $input = $tr.find('.editable-sprice');
                    const lp = parseFloat($tr.attr('data-lp')) || 0;
                    const ship = parseFloat($tr.attr('data-ship')) || 0;
                    const margin = parseFloat($tr.attr('data-margin')) || 0.80;
                    const isTemuMp = (String(mp).toLowerCase() === 'temu' || String(mp).toLowerCase() === 'temu2');
                    const temuProfit = isTemuMp ? ((newPrice * 0.80) - ship - lp) : 0;
                    const sgpft = newPrice > 0
                        ? (isTemuMp ? (temuProfit / newPrice) * 100 : ((newPrice * margin - ship - lp) / newPrice) * 100)
                        : 0;
                    const sroi = isTemuMp
                        ? (lp > 0 ? (temuProfit / lp) * 100 : 0)
                        : (lp > 0 ? ((newPrice * margin - lp - ship) / lp) * 100 : 0);
                    const metrics = { sgpft: sgpft, spft: sgpft, sroi: sroi, margin: isTemuMp ? 0.80 : margin };

                    $input.val(newPrice.toFixed(2)).trigger('input');
                    saveModalSpriceForRow($tr, newPrice, function(ok) {
                        if (ok) {
                            okCount++;
                            ovl30ModalData.forEach(function(item) {
                                if (String(item.marketplace || '') === mp) item.sprice = newPrice;
                            });
                        }

                        if (ok && withSiblings && modalSiblingSkus.length) {
                            applySpriceToSiblingSkus(mp, newPrice, metrics, function(sibOk) {
                                siblingsOk = Math.max(siblingsOk, sibOk || 0);
                                doneCount++;
                                finishIfDone();
                            });
                        } else {
                            doneCount++;
                            finishIfDone();
                        }
                    }, { applySiblings: false });
                });
            }

            if (withSiblings) {
                const sku = getModalPrimarySku();
                refreshModalSiblingSkus(sku);
                setTimeout(runSame, 350);
            } else {
                runSame();
            }
        }

        $(document).on('click', '#modal-apply-sprice-same-btn', function(e) {
            e.preventDefault();
            applyModalSameSpriceFromBox();
        });
        $(document).on('keydown', '#modal-sprice-same-input', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                applyModalSameSpriceFromBox();
            }
        });

        // ==================== TARGET ROI% / GPFT% (same as /doba-tabulator) ====================
        // ROI:  sprice = (LP × (1 + ROI%/100) + Ship) / margin
        // GPFT: sprice = (LP + Ship) / (margin − GPFT%/100)

        function collectModalTargetRows(opts) {
            const alwaysIncludeDoba = !!(opts && opts.alwaysIncludeDoba);
            const alwaysIncludeSb2bTopDawg = !!(opts && opts.alwaysIncludeSb2bTopDawg);
            const $rows = [];
            const seen = {};
            if (modalSelectedChannels.size === 0 && !alwaysIncludeDoba && !alwaysIncludeSb2bTopDawg) return $rows;
            $('#ovl30DetailsTableBody tr:visible').each(function() {
                const $tr = $(this);
                if (!$tr.find('.editable-sprice').length) return;
                const mp = String($tr.attr('data-marketplace') || '');
                if (!modalSelectedChannels.has(mp)) return;
                $rows.push($tr);
                seen[mp.toLowerCase().replace(/\s+/g, '')] = true;
            });
            // Doba always gets SP Apply at −25%, even if unchecked / in another group
            if (alwaysIncludeDoba && !seen.doba) {
                const $doba = findModalDobaEditableRow();
                if ($doba.length) $rows.push($doba);
            }
            // SB2B / TopDawg always get (SP × 0.80) − Ship, even if unchecked
            if (alwaysIncludeSb2bTopDawg) {
                if (!seen.sb2b && !seen.shopifyb2b && !seen.shopify_b2b) {
                    const $sb2b = findModalSb2bEditableRow();
                    if ($sb2b.length) $rows.push($sb2b);
                }
                if (!seen.topdawg && !seen.topdog) {
                    const $td = findModalTopDawgEditableRow();
                    if ($td.length) $rows.push($td);
                }
            }
            return $rows;
        }

        function applyModalTargetBackSolve(computeFn, labelPrefix) {
            if (modalSelectedChannels.size === 0) {
                showToast('Please select at least one channel (checkbox)', 'error');
                return;
            }
            const $rows = collectModalTargetRows();
            if (!$rows.length) {
                showToast('No editable SPRICE rows selected', 'error');
                return;
            }
            if (!confirm(labelPrefix + ' — set SPRICE on ' + $rows.length + ' channel(s)' + siblingsApplyLabel() + '?')) return;

            let doneCount = 0;
            let okCount = 0;
            let skipped = 0;
            let siblingsOk = 0;
            $rows.forEach(function($tr) {
                const lp = parseFloat($tr.attr('data-lp')) || 0;
                const ship = parseFloat($tr.attr('data-ship')) || 0;
                const mpLower = String($tr.attr('data-marketplace') || '').toLowerCase();
                let margin = parseFloat($tr.attr('data-margin'));
                if (!(margin > 0)) {
                    margin = (mpLower === 'doba') ? 0.95
                        : ((mpLower === 'reverb' || ['ebay2', 'ebaytwo', 'ebay3', 'ebaythree'].includes(mpLower)) ? 0.85 : 0.80);
                }
                const computed = computeFn({ lp: lp, ship: ship, margin: margin, marketplace: mpLower });
                if (!computed || !(computed.newPrice > 0)) {
                    skipped++;
                    doneCount++;
                    if (doneCount === $rows.length) {
                        showToast(
                            okCount
                                ? (labelPrefix + ' applied to ' + okCount + ' channel(s)'
                                    + (skipped ? ' (' + skipped + ' skipped)' : ''))
                                : (labelPrefix + ' — no rows could be solved (need LP & margin)'),
                            okCount ? 'success' : 'error'
                        );
                    }
                    return;
                }
                const newPrice = +Number(computed.newPrice).toFixed(2);
                const mp = String($tr.attr('data-marketplace') || '');
                $tr.find('.editable-sprice').val(newPrice.toFixed(2)).trigger('input');
                saveModalSpriceForRow($tr, newPrice, function(ok, res) {
                    if (ok) {
                        okCount++;
                        if (res && res.siblings_count) {
                            siblingsOk = Math.max(siblingsOk, parseInt(res.siblings_count, 10) || 0);
                        }
                        ovl30ModalData.forEach(function(item) {
                            if (String(item.marketplace || '') === mp) item.sprice = newPrice;
                        });
                    }
                    doneCount++;
                    if (doneCount === $rows.length) {
                        showToast(
                            okCount
                                ? (labelPrefix + ' applied to ' + okCount + ' channel(s)'
                                    + (skipped ? ' (' + skipped + ' skipped)' : '')
                                    + (siblingsOk ? (' (+' + siblingsOk + ' siblings)') : ''))
                                : 'Failed to save SPRICE',
                            okCount ? 'success' : 'error'
                        );
                    }
                });
            });
        }

        $(document).on('click', '#modal-apply-target-roi-btn', function() {
            const rawInput = $('#modal-target-roi-input').val();
            const targetRoiPct = parseFloat(String(rawInput == null ? '' : rawInput).replace(',', '.'));
            if (rawInput === '' || rawInput == null) {
                showToast('Please enter a Target ROI%', 'error');
                $('#modal-target-roi-input').focus();
                return;
            }
            if (!isFinite(targetRoiPct)) {
                showToast('Target ROI% must be a number', 'error');
                return;
            }
            applyModalTargetBackSolve(function(ctx) {
                if (!(ctx.lp > 0)) return null;
                // Temu/Temu2 SROI = Profit/LP → sprice = (LP × (1 + ROI%/100) + ship) / 0.80
                const isTemuMp = ['temu', 'temu2', 'temutwo'].includes(String(ctx.marketplace || '').toLowerCase());
                const margin = isTemuMp ? 0.80 : ctx.margin;
                if (!(margin > 0)) return null;
                const candidate = (ctx.lp * (1 + targetRoiPct / 100) + ctx.ship) / margin;
                if (!isFinite(candidate) || candidate <= 0) return null;
                return { newPrice: candidate };
            }, 'Target ROI ' + targetRoiPct + '%');
        });

        $(document).on('click', '#modal-apply-target-gpft-btn', function() {
            const rawInput = $('#modal-target-gpft-input').val();
            const targetGpftPct = parseFloat(String(rawInput == null ? '' : rawInput).replace(',', '.'));
            if (rawInput === '' || rawInput == null) {
                showToast('Please enter a Target GPFT%', 'error');
                $('#modal-target-gpft-input').focus();
                return;
            }
            if (!isFinite(targetGpftPct)) {
                showToast('Target GPFT% must be a number', 'error');
                return;
            }
            applyModalTargetBackSolve(function(ctx) {
                if (!(ctx.lp > 0)) return null;
                // Temu/Temu2 SGPFT: Profit = (Sprice × 0.80) − ship − LP
                const isTemuMp = ['temu', 'temu2', 'temutwo'].includes(String(ctx.marketplace || '').toLowerCase());
                const margin = isTemuMp ? 0.80 : ctx.margin;
                if (!(margin > 0)) return null;
                const denom = margin - targetGpftPct / 100;
                if (!(denom > 0)) return null;
                const candidate = (ctx.lp + ctx.ship) / denom;
                if (!isFinite(candidate) || candidate <= 0) return null;
                return { newPrice: candidate };
            }, 'Target GPFT ' + targetGpftPct + '%');
        });

        $(document).on('keydown', '#modal-target-roi-input', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                $('#modal-apply-target-roi-btn').trigger('click');
            }
        });
        $(document).on('keydown', '#modal-target-gpft-input', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                $('#modal-apply-target-gpft-btn').trigger('click');
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
                    'amazon', 'doba',
                    'ebay', 'ebay1', 'ebay2', 'ebaytwo', 'ebay3', 'ebaythree',
                    'temu', 'temu2', 'tiktok', 'tiktok2', 'tiktok 2', 'bestbuy', 'bestbuyusa', 'macy', 'macys',
                    'reverb', 'sb2c', 'shopify', 'shopifyb2c', 'sb2b', 'shopifyb2b',
                    'fba', 'tiktok2', 'shein', 'faire', 'aliexpress', 'ppower', 'purchasingpower', 'topdawg'
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
                const tacosCh = parseFloat($tr.attr('data-tacos-ch')) || (item ? parseFloat(item.tacos_ch) || 0 : 0);
                const margin = parseFloat($tr.attr('data-margin')) || (item ? parseFloat(item.margin) || 0.80 : 0.80);
                const l30 = parseFloat($tr.attr('data-l30')) || (item ? parseInt(item.l30) || 0 : 0);
                const mpLower = String(s.marketplace || '').toLowerCase();
                const sprice = s.suggested;
                const isTemuMp = (mpLower === 'temu' || mpLower === 'temu2');
                const temuProfit = isTemuMp ? ((sprice * 0.80) - ship - lp) : 0;
                const sgpft = sprice > 0
                    ? (isTemuMp ? (temuProfit / sprice) * 100 : ((sprice * margin - ship - lp) / sprice) * 100)
                    : 0;
                const isChannelAdsMp = (mpLower === 'tiktok' || mpLower === 'reverb'
                    || ['ebay', 'ebay1', 'ebaytwo', 'ebay2', 'ebaythree', 'ebay3'].includes(mpLower));
                const spft = (isChannelAdsMp || isTemuMp) ? (sgpft - tacosCh) : (l30 == 0 ? sgpft : (sgpft - ad));
                const sroi = isTemuMp
                    ? (lp > 0 ? (temuProfit / lp) * 100 : 0)
                    : (lp > 0 ? ((sprice * margin - lp - ship) / lp) * 100 : 0);

                $.ajax({
                    url: '/cvr-master-save-suggested-data',
                    method: 'POST',
                    data: {
                        sku: getModalPrimarySku() || s.sku,
                        marketplace: s.marketplace,
                        sprice: sprice,
                        sgpft: sgpft,
                        spft: spft,
                        sroi: sroi,
                        amazon_margin: margin,
                        apply_siblings: siblingsApplyPayload(sprice),
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(res) {
                        if ($input.length) {
                            $input.css('border-color', '#28a745');
                            setTimeout(() => $input.css('border-color', ''), 1000);
                        }
                        if (res && res.siblings_count) {
                            showToast('Saved (+' + res.siblings_count + ' siblings) on ' + s.marketplace, 'success');
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

        // ==================== PRICE PUSH ====================

        function parseOvl30PushPrice(raw) {
            if (raw === null || raw === undefined) return null;
            const s = String(raw).trim();
            if (s === '' || s.toLowerCase() === 'null' || s.toLowerCase() === 'undefined') return null;
            const price = parseFloat(s.replace(/[$,\s]/g, '').replace(',', '.'));
            if (!isFinite(price) || !(price > 0)) return null;
            return +price.toFixed(2);
        }

        function collectOvl30PushTargets(onlySelected) {
            const targets = [];
            const primarySku = getModalPrimarySku();
            $('#ovl30DetailsTableBody tr:visible').each(function() {
                const $tr = $(this);
                const $btn = $tr.find('.push-price-btn');
                if (!$btn.length || $btn.prop('disabled')) return;
                const mp = String($btn.data('marketplace') || $tr.attr('data-marketplace') || '');
                if (onlySelected && modalSelectedChannels.size > 0 && !modalSelectedChannels.has(mp)) return;
                const sku = primarySku || String($btn.attr('data-sku') || $tr.attr('data-sku') || '');
                const price = parseOvl30PushPrice($tr.find('.editable-sprice').val());
                if (price == null || !sku || !mp || sku.toUpperCase() === 'NOT LISTED') return;
                targets.push({ sku: sku, marketplace: mp, price: price, $btn: $btn, $tr: $tr });
            });
            return targets;
        }

        function pushOvl30PriceToMarketplace(target) {
            const price = parseOvl30PushPrice(target && target.price);
            if (price == null) {
                return $.Deferred().resolve({
                    ok: false,
                    marketplace: target && target.marketplace,
                    message: 'Skipped — price is 0 or empty'
                }).promise();
            }
            target.price = price;
            const mpLower = String(target.marketplace || '').toLowerCase();
            // Temu / Temu2: push base = (Sprice×0.85)−2.99 if SPRICE<$35; else Sprice×0.85
            let pushPrice = price;
            if (mpLower === 'temu' || mpLower === 'temu2') {
                const converted = temuPushBaseFromSprice(price);
                if (converted == null) {
                    return $.Deferred().resolve({
                        ok: false,
                        marketplace: target.marketplace,
                        message: 'Skipped — ' + (mpLower === 'temu2' ? 'Temu2' : 'Temu') + ' SPRICE converts to invalid base'
                    }).promise();
                }
                pushPrice = converted;
                target.price = converted;
            }
            const payload = {
                sku: target.sku,
                price: pushPrice,
                marketplace: target.marketplace,
                apply_siblings: siblingsApplyPayload(pushPrice),
                _token: '{{ csrf_token() }}'
            };
            // Doba: Self Pick = SPRICE − Ship (same as /doba-tabulator)
            if (mpLower === 'doba' && target.$tr && target.$tr.length) {
                const ship = parseFloat(target.$tr.attr('data-ship')) || 0;
                payload.self_pick_price = Math.max(0, +(price - ship).toFixed(2));
            }
            // Temu / Temu2: pass goods_id / sku_id when available
            if ((mpLower === 'temu' || mpLower === 'temu2') && target.$tr && target.$tr.length) {
                const goodsId = target.$tr.attr('data-goods-id') || target.$btn.data('goods-id') || '';
                const skuId = target.$tr.attr('data-sku-id') || target.$btn.data('sku-id') || '';
                if (goodsId) payload.goods_id = goodsId;
                if (skuId) payload.sku_id = skuId;
            }
            return $.ajax({
                url: '/cvr-master-push-price',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                data: payload
            }).then(function(response) {
                if (response && response.success) {
                    target.$btn.html('<i class="fas fa-check"></i>')
                        .removeClass('btn-primary').addClass('btn-success')
                        .prop('disabled', true);
                    return {
                        ok: true,
                        marketplace: target.marketplace,
                        message: response.message,
                        price: pushPrice,
                        $tr: target.$tr,
                        $btn: target.$btn
                    };
                }
                return {
                    ok: false,
                    marketplace: target.marketplace,
                    message: (response && (response.message || (response.errors && response.errors[0] && response.errors[0].message))) || 'Failed to push price'
                };
            }, function(xhr) {
                const j = xhr.responseJSON || {};
                return {
                    ok: false,
                    marketplace: target.marketplace,
                    message: j.message || (j.errors && j.errors[0] && j.errors[0].message) || 'Failed to push price'
                };
            });
        }

        /**
         * After push: patch the affected row in-place (no full /cvr-master-breakdown reload).
         * Full reload was making every modal action feel slow.
         */
        function patchOvl30RowAfterPush($tr, pushedPrice, marketplace) {
            if (!$tr || !$tr.length || !(pushedPrice > 0)) return;
            const mp = String(marketplace || $tr.attr('data-marketplace') || '');
            const mpLower = mp.toLowerCase();
            const basePrice = +Number(pushedPrice).toFixed(2);

            $tr.attr('data-price', basePrice);

            // Price cell: Temu/Temu2 display × 1.1765 (same as renderMarketplaceData)
            const TEMU_PRICE_DISPLAY_MULT = 1.1765;
            const isTemu = (mpLower === 'temu' || mpLower === 'temu2');
            const displayPrice = (isTemu && basePrice > 0)
                ? +(basePrice * TEMU_PRICE_DISPLAY_MULT).toFixed(2)
                : basePrice;
            const $priceTd = $tr.children('td').eq(3);
            const tip = isTemu
                ? ((mpLower === 'temu2' ? 'Temu2' : 'Temu') + ' Price × 1.1765 (shown). Base calc price: $' + basePrice.toFixed(2))
                : '';
            const existingDot = $priceTd.find('.pricing-master-chart-link').prop('outerHTML') || '';
            $priceTd.html(
                '<span style="font-weight:700;" title="' + tip.replace(/"/g, '&quot;') + '">$'
                + displayPrice.toFixed(2) + '</span>' + (existingDot ? (' ' + existingDot) : '')
            );

            // Pushed By column (second-to-last); History is last
            const nowLabel = 'Just now';
            const $tds = $tr.children('td');
            $tds.filter('.ovl30-by-cell').html(
                '<span class="pushed-by-dot" title="Pushed ' + nowLabel + '" aria-label="Pushed"></span>'
            );

            // Keep in-memory modal data in sync + prepend history entry
            const userName = (window.__authUserName || 'You');
            const atIso = new Date().toISOString().slice(0, 16).replace('T', ' ');
            ovl30ModalData.forEach(function(item) {
                if (String(item.marketplace || '') === mp) {
                    item.price = basePrice;
                    item.pushed_by = item.pushed_by || userName;
                    item.pushed_at = nowLabel;
                    item.pushed_price = basePrice;
                    if (!Array.isArray(item.push_history)) item.push_history = [];
                    item.push_history.unshift({
                        price: basePrice,
                        by: userName,
                        at: atIso,
                        marketplace: mp,
                    });
                    const count = item.push_history.length;
                    $tds.filter('.ovl30-history-cell').html(
                        '<button type="button" class="ovl30-history-btn has-history" data-marketplace="'
                        + String(mp).replace(/"/g, '&quot;') + '" title="' + count + ' push record(s) — click to view">'
                        + '<i class="fas fa-history"></i> <span class="small">' + count + '</span></button>'
                    );
                }
            });
        }

        /** Parse history/push/edit timestamps to YYYY-MM-DD (dynamic calendar day). */
        function ovl30HistoryDayKey(raw) {
            if (raw == null || raw === '') return '';
            const s = String(raw).trim();
            // Already "Y-m-d ..." or ISO
            const m = s.match(/^(\d{4}-\d{2}-\d{2})/);
            if (m) return m[1];
            const d = new Date(s);
            if (isNaN(d.getTime())) return '';
            const y = d.getFullYear();
            const mo = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return y + '-' + mo + '-' + day;
        }

        function ovl30HistoryDaysAgoKey(daysAgo) {
            const d = new Date();
            d.setHours(0, 0, 0, 0);
            d.setDate(d.getDate() - (parseInt(daysAgo, 10) || 0));
            const y = d.getFullYear();
            const mo = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return y + '-' + mo + '-' + day;
        }

        /**
         * Filter push history:
         * 1) Hide rows when History day === SPRICE edit day === Push day (same calendar day).
         * 2) Keep one row per marketplace+day (newest first — history is already newest-first).
         * 3) Optional dynamic day-range (Today / 7 / 30 / 90 / All).
         */
        function filterOvl30PushHistory(history, opts) {
            opts = opts || {};
            const list = Array.isArray(history) ? history.slice() : [];
            const pushedDay = ovl30HistoryDayKey(opts.pushedAtIso || opts.pushedAt || '');
            const editedDay = ovl30HistoryDayKey(opts.spriceEditedAt || '');
            const days = parseInt(opts.days, 10);
            const cutoff = (isFinite(days) && days > 0) ? ovl30HistoryDaysAgoKey(days - 1) : null;
            const seenDayMp = {};

            return list.filter(function(h) {
                const day = ovl30HistoryDayKey(h && h.at);
                if (!day) return true;

                // Dynamic day window
                if (cutoff && day < cutoff) return false;

                // Prefer per-history-row meta (All-channels view); fall back to modal-level meta
                const rowPushedDay = ovl30HistoryDayKey(h.pushed_at_iso || opts.pushedAtIso || opts.pushedAt || '') || pushedDay;
                const rowEditedDay = ovl30HistoryDayKey(h.sprice_edited_at || opts.spriceEditedAt || '') || editedDay;
                const rowPushedPrice = (h.pushed_price != null && h.pushed_price !== '')
                    ? parseFloat(h.pushed_price)
                    : parseFloat(opts.pushedPrice);

                // Hide when History date, SPRICE edit date, and Push date are the same day
                if (day && rowPushedDay && rowEditedDay && day === rowPushedDay && day === rowEditedDay) {
                    return false;
                }
                // Hide current By push when same day + same price (redundant with By column)
                const histPrice = parseFloat(h && h.price);
                if (
                    day && rowPushedDay && day === rowPushedDay
                    && isFinite(rowPushedPrice) && isFinite(histPrice)
                    && Math.abs(rowPushedPrice - histPrice) < 0.005
                ) {
                    return false;
                }

                // One visible row per marketplace + calendar day
                const mpKey = String((h && h.marketplace) || opts.fallbackMarketplace || '').toLowerCase();
                const dedupeKey = mpKey + '|' + day;
                if (seenDayMp[dedupeKey]) return false;
                seenDayMp[dedupeKey] = true;
                return true;
            });
        }

        let ovl30PushHistoryState = {
            marketplaceLabel: '',
            sku: '',
            history: [],
            pushedAtIso: null,
            spriceEditedAt: null,
            pushedPrice: null,
        };

        function renderOvl30PushHistoryRows(history, fallbackMarketplace) {
            const mp = String(fallbackMarketplace || '');
            if (!history || !history.length) {
                return '<tr><td colspan="4" class="text-center text-muted py-3">No push history for this range</td></tr>';
            }
            let html = '';
            history.forEach(function(h) {
                const price = (h.price != null && isFinite(parseFloat(h.price)))
                    ? ('$' + parseFloat(h.price).toFixed(2))
                    : '-';
                html += '<tr>'
                    + '<td>' + String(h.at || '-').replace(/</g, '&lt;') + '</td>'
                    + '<td>' + String(h.marketplace || mp || '-').replace(/</g, '&lt;') + '</td>'
                    + '<td class="text-end fw-semibold">' + price + '</td>'
                    + '<td>' + String(h.by || '-').replace(/</g, '&lt;') + '</td>'
                    + '</tr>';
            });
            return html;
        }

        function refreshOvl30PushHistoryBody() {
            const days = parseInt($('#ovl30PushHistoryDays').val(), 10);
            const filtered = filterOvl30PushHistory(ovl30PushHistoryState.history, {
                days: isFinite(days) ? days : 0,
                pushedAtIso: ovl30PushHistoryState.pushedAtIso,
                spriceEditedAt: ovl30PushHistoryState.spriceEditedAt,
                pushedPrice: ovl30PushHistoryState.pushedPrice,
                fallbackMarketplace: ovl30PushHistoryState.marketplaceLabel,
            });
            $('#ovl30PushHistoryBody').html(
                renderOvl30PushHistoryRows(filtered, ovl30PushHistoryState.marketplaceLabel)
            );
        }

        function showOvl30PushHistoryModal(marketplaceLabel, sku, history, meta) {
            meta = meta || {};
            ovl30PushHistoryState = {
                marketplaceLabel: marketplaceLabel || 'Channel',
                sku: sku || '-',
                history: Array.isArray(history) ? history : [],
                pushedAtIso: meta.pushedAtIso || meta.pushed_at_iso || null,
                spriceEditedAt: meta.spriceEditedAt || meta.sprice_edited_at || null,
                pushedPrice: meta.pushedPrice != null ? meta.pushedPrice : meta.pushed_price,
            };
            $('#ovl30PushHistoryMarketplace').text(ovl30PushHistoryState.marketplaceLabel);
            $('#ovl30PushHistorySku').text(ovl30PushHistoryState.sku);
            refreshOvl30PushHistoryBody();
            const el = document.getElementById('ovl30PushHistoryModal');
            if (el) bootstrap.Modal.getOrCreateInstance(el).show();
        }

        $(document).on('change', '#ovl30PushHistoryDays', function() {
            refreshOvl30PushHistoryBody();
        });

        function openOvl30PushHistory(marketplace) {
            const mp = String(marketplace || '');
            const sku = getModalPrimarySku() || $('#modalSkuName').text() || '';
            const item = (ovl30ModalData || []).find(function(r) {
                return String(r.marketplace || '') === mp;
            });
            const history = (item && Array.isArray(item.push_history)) ? item.push_history : [];
            showOvl30PushHistoryModal(mp || 'Channel', sku, history, {
                pushedAtIso: item ? (item.pushed_at_iso || item.pushed_at || null) : null,
                spriceEditedAt: item ? (item.sprice_edited_at || null) : null,
                pushedPrice: item ? item.pushed_price : null,
            });
        }

        function openOuterSkuPushHistory(sku, $btn) {
            const skuKey = String(sku || '').trim();
            if (!skuKey) return;
            $('#ovl30PushHistoryMarketplace').text('All channels');
            $('#ovl30PushHistorySku').text(skuKey);
            $('#ovl30PushHistoryBody').html(
                '<tr><td colspan="4" class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin me-1"></i>Loading…</td></tr>'
            );
            const el = document.getElementById('ovl30PushHistoryModal');
            if (el) bootstrap.Modal.getOrCreateInstance(el).show();
            $.ajax({
                url: '/cvr-master-push-history',
                method: 'GET',
                data: { sku: skuKey },
                success: function(resp) {
                    const history = (resp && Array.isArray(resp.history)) ? resp.history : [];
                    const displaySku = (resp && resp.sku) ? resp.sku : skuKey;
                    showOvl30PushHistoryModal('All channels', displaySku, history);
                    const count = history.length;
                    if ($btn && $btn.length) {
                        $btn.toggleClass('has-history', count > 0);
                        $btn.attr('title', count
                            ? (count + ' push record(s) — click to view')
                            : 'No push history yet');
                        $btn.html(
                            '<i class="fas fa-history"></i>'
                            + (count ? (' <span class="small">' + count + '</span>') : '')
                        );
                        try {
                            const row = (typeof table !== 'undefined' && table)
                                ? table.getRows().find(function(r) {
                                    return String((r.getData() || {}).sku || '') === skuKey;
                                })
                                : null;
                            if (row) row.update({ push_history_count: count });
                        } catch (e) { /* ignore */ }
                    }
                },
                error: function() {
                    $('#ovl30PushHistoryBody').html(
                        '<tr><td colspan="4" class="text-center text-danger py-3">Failed to load push history</td></tr>'
                    );
                }
            });
        }

        $(document).on('click', '#ovl30DetailsModal .ovl30-history-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            openOvl30PushHistory($(this).attr('data-marketplace') || $(this).data('marketplace'));
        });

        $(document).on('click', '.outer-push-history-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const $btn = $(this);
            openOuterSkuPushHistory($btn.attr('data-sku') || $btn.data('sku'), $btn);
        });

        function patchOvl30LmpCell(marketplace, lmpPrice) {
            if (!$('#ovl30DetailsModal').hasClass('show')) return;
            const mp = String(marketplace || '').toLowerCase();
            if (!mp) return;
            // Drawer filter channel (Ebay1/2/3 → ebay, FBA → amazon)
            let linkMp = mp;
            if (['ebay1', 'ebay2', 'ebay3', 'ebaytwo', 'ebaythree', 'ebay'].indexOf(mp) !== -1) {
                linkMp = 'ebay';
            } else if (mp === 'fba') {
                linkMp = 'amazon';
            }
            const $tr = $('#ovl30DetailsTableBody tr').filter(function() {
                return String($(this).attr('data-marketplace') || '').toLowerCase() === mp;
            }).first();
            if (!$tr.length) return;
            const sku = getModalPrimarySku() || '';
            const skuAttr = String(sku).replace(/"/g, '&quot;');
            const $lmpTd = $tr.children('td').eq(11); // LMP column
            if (!(lmpPrice > 0)) {
                $lmpTd.html(
                    '<a href="#" class="lmp-add-btn" data-sku="' + skuAttr + '" data-marketplace="' + linkMp
                    + '" title="Add ' + linkMp + ' LMP"><i class="fas fa-plus-circle"></i></a>'
                );
                $tr.attr('data-lmp', '0');
                return;
            }
            const p = +Number(lmpPrice).toFixed(2);
            $tr.attr('data-lmp', p);
            $lmpTd.html(
                '<a href="#" class="lmp-channel-price" data-sku="' + skuAttr + '" data-marketplace="' + linkMp
                + '" title="View ' + linkMp + ' LMP" style="color:#0d6efd;font-weight:600;text-decoration:underline;cursor:pointer;">$'
                + p.toFixed(2) + '</a>'
            );
            ovl30ModalData.forEach(function(item) {
                if (String(item.marketplace || '').toLowerCase() === mp) {
                    item.lmp_price = p;
                    item.lmp_channel = linkMp;
                }
            });
        }

        /** No full-table reload after push — only patch pushed rows. */
        function reloadOvl30ModalAfterPush(pushedTargets) {
            if (!$('#ovl30DetailsModal').hasClass('show')) return;
            const list = Array.isArray(pushedTargets) ? pushedTargets : [];
            list.forEach(function(t) {
                if (!t || !t.ok) return;
                const $tr = t.$tr && t.$tr.length
                    ? t.$tr
                    : $('#ovl30DetailsTableBody tr').filter(function() {
                        return String($(this).attr('data-marketplace') || '') === String(t.marketplace || '');
                    }).first();
                const price = parseFloat(t.price) || parseFloat($tr.attr('data-price')) || 0;
                patchOvl30RowAfterPush($tr, price, t.marketplace);
            });
        }

        // Single-row push
        $(document).on('click', '.push-price-btn', function(e) {
            e.stopPropagation();
            const btn = $(this);
            if (btn.prop('disabled')) return;
            const row = btn.closest('tr');
            const sku = getModalPrimarySku() || String(btn.attr('data-sku') || row.attr('data-sku') || '');
            const marketplace = btn.attr('data-marketplace') || btn.data('marketplace') || row.attr('data-marketplace');
            const priceInput = row.find('.editable-sprice');
            const price = parseOvl30PushPrice(priceInput.val());

            if (price == null) {
                showToast('Cannot push — SPRICE is 0 or empty', 'error');
                priceInput.focus();
                return;
            }
            if (!sku || sku.toUpperCase() === 'NOT LISTED') {
                showToast('Cannot push — invalid SKU', 'error');
                return;
            }

            const mpLowerConfirm = String(marketplace || '').toLowerCase();
            let confirmPrice = price;
            let confirmExtra = '';
            if (mpLowerConfirm === 'temu' || mpLowerConfirm === 'temu2') {
                const converted = temuPushBaseFromSprice(price);
                if (converted == null) {
                    showToast('Cannot push — ' + (mpLowerConfirm === 'temu2' ? 'Temu2' : 'Temu') + ' SPRICE converts to invalid base', 'error');
                    return;
                }
                confirmPrice = converted;
                confirmExtra = ' (from SPRICE $' + price.toFixed(2)
                    + ' × 0.85' + (price < 35 ? ' − 2.99' : '') + ')';
            }

            if (!confirm(
                'Push price $' + confirmPrice.toFixed(2) + confirmExtra
                + ' to ' + String(marketplace).toUpperCase()
                + ' for SKU: ' + sku + siblingsApplyLabel() + '?'
            )) {
                return;
            }

            const originalHtml = btn.html();
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

            pushOvl30PriceToMarketplace({
                sku: sku,
                marketplace: marketplace,
                price: price,
                $btn: btn,
                $tr: row
            }).then(function(result) {
                if (result.ok) {
                    showToast(result.message || 'Price pushed', 'success');
                    reloadOvl30ModalAfterPush([result]);
                } else {
                    showToast(result.message || 'Failed to push price', 'error');
                    btn.html(originalHtml).prop('disabled', false);
                }
            });
        });

        // Header bulk push — marketplace-wise for selected channels (or all visible pushable rows)
        $(document).on('click', '#ovl30-bulk-push-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const $bulkBtn = $(this);
            if ($bulkBtn.prop('disabled')) return;

            const onlySelected = modalSelectedChannels.size > 0;
            const targets = collectOvl30PushTargets(onlySelected);
            if (!targets.length) {
                showToast(
                    onlySelected
                        ? 'No selected channels have a pushable SPRICE (> 0)'
                        : 'No visible channels with pushable SPRICE. Select channels or enter SPRICE first.',
                    'error'
                );
                return;
            }

            const names = targets.map(function(t) {
                return t.marketplace + ' $' + t.price.toFixed(2);
            }).join(', ');
            if (!confirm(
                'Bulk push SPRICE to ' + targets.length + ' marketplace(s)'
                + siblingsApplyLabel() + '?\n\n' + names
            )) {
                return;
            }

            const origHtml = $bulkBtn.html();
            $bulkBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
            targets.forEach(function(t) {
                t.$btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
            });

            let idx = 0;
            let okCount = 0;
            const errors = [];
            const okResults = [];

            function pushNext() {
                if (idx >= targets.length) {
                    $bulkBtn.prop('disabled', false).html(origHtml);
                    if (okCount > 0) {
                        showToast(
                            'Pushed ' + okCount + '/' + targets.length + ' marketplace(s)'
                                + (errors.length ? ('. Failed: ' + errors.join(', ')) : ''),
                            errors.length ? 'error' : 'success'
                        );
                        reloadOvl30ModalAfterPush(okResults);
                    } else {
                        showToast('Bulk push failed' + (errors.length ? ': ' + errors.join(', ') : ''), 'error');
                        targets.forEach(function(t) {
                            if (!t.$btn.hasClass('btn-success')) {
                                t.$btn.prop('disabled', false).html('<i class="fas fa-upload"></i>')
                                    .removeClass('btn-success').addClass('btn-primary');
                            }
                        });
                    }
                    return;
                }
                const target = targets[idx++];
                pushOvl30PriceToMarketplace(target).then(function(result) {
                    if (result.ok) {
                        okCount++;
                        okResults.push(result);
                    } else {
                        errors.push(String(target.marketplace));
                        target.$btn.html('<i class="fas fa-upload"></i>')
                            .removeClass('btn-success').addClass('btn-primary')
                            .prop('disabled', false);
                    }
                    pushNext();
                });
            }
            pushNext();
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
            if ($('#lmpAddShip').length) {
                lmpEditState.draftShip = $('#lmpAddShip').val();
            }
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
            const labelMap = { amazon: 'Amazon', ebay: 'eBay', google: 'Google', bestbuy: 'BestBuy', macy: 'Macy', reverb: 'Reverb', temu: 'Temu', aliexpress: 'AliExpress' };
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
                if ($('#lmpAddShip').length) {
                    const shipVal = lmpEditState.draftShip != null && lmpEditState.draftShip !== ''
                        ? lmpEditState.draftShip
                        : (lmpEditState.origDelivery != null && lmpEditState.origDelivery !== ''
                            ? Number(lmpEditState.origDelivery).toFixed(2)
                            : '0.00');
                    $('#lmpAddShip').val(shipVal);
                }
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
            const shipNum = parseFloat(row.delivery != null ? row.delivery : (row.shipCost != null ? row.shipCost : 0));
            const shipStr = (!isNaN(shipNum) && shipNum >= 0) ? String(shipNum) : '0';
            lmpEditState = {
                id: row.id,
                channel: row.channel,
                extId: row.extId || '',
                origPrice: row.price,
                origLink: row.link || '',
                origDelivery: !isNaN(shipNum) ? shipNum : 0,
                sourceSku: row.sourceSku || row.sku || '',
                draftPrice: priceStr,
                draftLink: row.link || '',
                draftShip: shipStr,
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

        /** Channels that support live SerpApi LMP Pull (same as amazon/ebay pricing tabulators). */
        const LMP_PULLABLE_CHANNELS = ['amazon', 'ebay', 'google'];

        function updateLmpPullBtnTitle(filter) {
            const $btn = $('#lmpPullApiBtn');
            if (!$btn.length) return;
            const ch = String(filter || 'all').toLowerCase();
            if (ch === 'all') {
                $btn.attr('title', 'Pull live LMP for Amazon + eBay + Google (SerpApi) — same as pricing tabulators');
            } else if (LMP_PULLABLE_CHANNELS.indexOf(ch) !== -1) {
                $btn.attr('title', 'Pull live ' + ch.charAt(0).toUpperCase() + ch.slice(1) + ' LMP prices from SerpApi');
            } else {
                $btn.attr('title', 'Live Pull is available for Amazon, eBay, and Google. Select one of those channels (or All).');
            }
        }

        /** Patch main-table LMP columns after a live Pull. */
        function patchPriceIncreaseLmpFromPull(sku, amazonRes, ebayRes, googleRes) {
            if (!table || !sku) return;
            const key = String(sku).trim().toUpperCase();
            const patch = {};
            if (amazonRes && amazonRes.lowest_price != null && amazonRes.lowest_price > 0) {
                patch.amazon_lmp_price = amazonRes.lowest_price;
            }
            if (ebayRes && ebayRes.lowest_price != null && ebayRes.lowest_price > 0) {
                patch.ebay_lmp_price = ebayRes.lowest_price;
            }
            if (googleRes && googleRes.lowest_price != null && googleRes.lowest_price > 0) {
                patch.google_lmp_price = googleRes.lowest_price;
            }
            if (!Object.keys(patch).length) return;

            (table.getRows() || []).forEach(function(r) {
                const d = r.getData();
                if (!d || d.is_parent_summary) return;
                if (String(d.sku || '').trim().toUpperCase() !== key) return;
                r.update(patch);
            });
            if (Array.isArray(fullDataset)) {
                fullDataset.forEach(function(row) {
                    if (!row || row.is_parent_summary) return;
                    if (String(row.sku || '').trim().toUpperCase() !== key) return;
                    Object.keys(patch).forEach(function(k) { row[k] = patch[k]; });
                });
            }
        }

        /** Lowest ranked LMP from current modal cache (Amazon = price + paid delivery). */
        function lowestLmpFromModalCache(channel) {
            const ch = String(channel || '').toLowerCase();
            if (!ch || !lmpModalCache || !Array.isArray(lmpModalCache.rows)) return null;
            let lowest = null;
            lmpModalCache.rows.forEach(function(r) {
                if (!r || r.channel !== ch || r.ignored) return;
                const ranked = (typeof lmpRankValue === 'function' ? lmpRankValue(r) : null) || r.price;
                if (ranked > 0 && (lowest == null || ranked < lowest)) lowest = ranked;
            });
            return lowest;
        }

        /**
         * After Pull: refresh open LMP drawer + patch OV L30 LMP cells in place
         * (no page reload, no closing/reopening modals).
         */
        function applyLmpPullResultsToOpenUi(sku, amazonRes, ebayRes, googleRes) {
            patchPriceIncreaseLmpFromPull(sku, amazonRes, ebayRes, googleRes);

            const amz = (amazonRes && amazonRes.lowest_price != null && amazonRes.lowest_price > 0)
                ? +Number(amazonRes.lowest_price).toFixed(2)
                : lowestLmpFromModalCache('amazon');
            const ebay = (ebayRes && ebayRes.lowest_price != null && ebayRes.lowest_price > 0)
                ? +Number(ebayRes.lowest_price).toFixed(2)
                : lowestLmpFromModalCache('ebay');
            const google = (googleRes && googleRes.lowest_price != null && googleRes.lowest_price > 0)
                ? +Number(googleRes.lowest_price).toFixed(2)
                : lowestLmpFromModalCache('google');

            if (amz != null) {
                patchOvl30LmpCell('amazon', amz);
                patchOvl30LmpCell('fba', amz);
            }
            if (ebay != null) {
                // OV L30 channel labels: Ebay1 / Ebay2 / Ebay3
                ['ebay1', 'ebay2', 'ebay3'].forEach(function(mp) {
                    patchOvl30LmpCell(mp, ebay);
                });
            }
            if (google != null) {
                patchOvl30LmpCell('google', google);
            }
        }

        /**
         * Load multi-channel LMP drawer.
         * Pass options.refresh = true to live-fetch Amazon/eBay/Google via SerpApi
         * (same pattern as amazon/ebay tabulator #lmpPullApiBtn → ?refresh=1).
         */
        function loadLmpCompetitorsModal(sku, marketplace, showAddForm, options) {
            options = options || {};
            const refreshFromApi = !!options.refresh;
            resetLmpEditState();
            $('#lmpSku').text(sku);
            if (marketplace === 'macys' || marketplace === 'bestbuyusa') {
                marketplace = marketplace === 'macys' ? 'macy' : 'bestbuy';
            }

            // On Pull, keep the currently selected channel filter when possible
            let initialFilter;
            if (refreshFromApi && lmpModalCache && lmpModalCache.sku === sku && lmpModalCache.filter) {
                initialFilter = lmpModalCache.filter;
            } else {
                initialFilter = (marketplace === 'amazon' || marketplace === 'ebay' || marketplace === 'google' || marketplace === 'bestbuy' || marketplace === 'macy' || marketplace === 'reverb' || marketplace === 'temu' || marketplace === 'aliexpress')
                    ? marketplace
                    : 'all';
            }

            // Live Pull only for Amazon / eBay / Google
            if (refreshFromApi) {
                const ch = String(initialFilter || 'all').toLowerCase();
                if (ch !== 'all' && LMP_PULLABLE_CHANNELS.indexOf(ch) === -1) {
                    showToast('Live Pull is available for Amazon, eBay, and Google. Select one of those channels (or All).', 'warning');
                    const $btn = $('#lmpPullApiBtn');
                    if ($btn.length) $btn.prop('disabled', false).html('<i class="fas fa-cloud-download-alt"></i> Pull');
                    return;
                }
            }

            const showAdd = (refreshFromApi && lmpModalCache && lmpModalCache.sku === sku)
                ? !!lmpModalCache.showAdd
                : (!!showAddForm || initialFilter !== 'all');
            $('#lmpModal').data('lmp-marketplace', marketplace || null);
            $('#lmpModal').data('lmp-filter', initialFilter);
            $('#lmpModal').data('lmp-show-add', showAdd);
            updateLmpPullBtnTitle(initialFilter);

            // Keep LMP drawer open for both initial load and live Pull (no page/modal reopen)
            const lmpEl = document.getElementById('lmpModal');
            if (lmpEl) {
                const modal = bootstrap.Modal.getOrCreateInstance(lmpEl);
                if (!lmpEl.classList.contains('show')) {
                    modal.show();
                }
            }

            const loadingMsg = refreshFromApi
                ? 'Pulling live LMP prices from SerpApi...'
                : 'Loading competitors...';
            $('#lmpDataList').html('<div class="text-center py-5 text-muted"><div class="spinner-border text-primary me-2"></div>' + loadingMsg + '</div>');

            const refreshAmazon = refreshFromApi && (initialFilter === 'all' || initialFilter === 'amazon');
            const refreshEbay = refreshFromApi && (initialFilter === 'all' || initialFilter === 'ebay');
            const refreshGoogle = refreshFromApi && (initialFilter === 'all' || initialFilter === 'google');

            let amazonData = null;
            let ebayData = null;
            let googleData = null;
            let bestbuyData = null;
            let macyData = null;
            let reverbData = null;
            let temuData = null;
            let aliexpressData = null;
            let loaded = 0;
            const totalNeeded = 8;
            let pullHadError = false;

            function tryRender() {
                loaded++;
                if (loaded < totalNeeded) return;

                // Re-render LMP drawer in place with fresh Pull/DB data
                const rows = buildLmpMergedRows(sku, amazonData, ebayData, googleData, bestbuyData, macyData, reverbData, temuData, aliexpressData);
                lmpModalCache = { sku: sku, rows: rows, filter: initialFilter, showAdd: showAdd };
                renderLmpMergedTable();
                updateLmpPullBtnTitle(initialFilter);

                // Ensure drawer stayed open after DOM rebuild
                if (lmpEl && !lmpEl.classList.contains('show')) {
                    bootstrap.Modal.getOrCreateInstance(lmpEl).show();
                }

                if (refreshFromApi) {
                    if (pullHadError) {
                        showToast('LMP Pull finished with some errors — check the list', 'warning');
                    } else {
                        const labels = [];
                        if (refreshAmazon) labels.push('Amazon');
                        if (refreshEbay) labels.push('eBay');
                        if (refreshGoogle) labels.push('Google');
                        showToast('Pulled live LMP for ' + (labels.join(', ') || 'channels') + ' — ' + sku, 'success');
                    }
                    // Update main table + OV L30 LMP cells without page/modal refresh
                    applyLmpPullResultsToOpenUi(sku, amazonData, ebayData, googleData);
                }

                const $btn = $('#lmpPullApiBtn');
                if ($btn.length) {
                    $btn.prop('disabled', false).html('<i class="fas fa-cloud-download-alt"></i> Pull');
                }
            }

            // Always load all channels so filter tabs work; only Amazon/eBay/Google get ?refresh=1
            $.ajax({
                url: '/amazon/competitors',
                method: 'GET',
                data: refreshAmazon ? { sku: sku, refresh: 1 } : { sku: sku },
                timeout: refreshAmazon ? 90000 : 10000,
                success: function(res) {
                    amazonData = res.success && res.competitors ? res : null;
                    tryRender();
                },
                error: function() {
                    amazonData = null;
                    if (refreshAmazon) pullHadError = true;
                    tryRender();
                }
            });

            $.ajax({
                url: '/ebay-lmp-data',
                method: 'GET',
                data: refreshEbay ? { sku: sku, refresh: 1 } : { sku: sku },
                timeout: refreshEbay ? 300000 : 10000,
                success: function(res) {
                    ebayData = res.success && res.competitors ? res : null;
                    tryRender();
                },
                error: function() {
                    ebayData = null;
                    if (refreshEbay) pullHadError = true;
                    tryRender();
                }
            });

            $.ajax({
                url: '/google-lmp-data',
                method: 'GET',
                data: refreshGoogle ? { sku: sku, refresh: 1 } : { sku: sku },
                timeout: refreshGoogle ? 90000 : 10000,
                success: function(res) {
                    googleData = res.success && res.competitors ? res : null;
                    tryRender();
                },
                error: function() {
                    googleData = null;
                    if (refreshGoogle) pullHadError = true;
                    tryRender();
                }
            });

            $.ajax({
                url: '/bestbuy-lmp-data',
                method: 'GET',
                data: { sku: sku },
                timeout: 10000,
                success: function(res) {
                    bestbuyData = res.success && res.competitors ? res : null;
                    tryRender();
                },
                error: function() {
                    bestbuyData = null;
                    tryRender();
                }
            });

            $.ajax({
                url: '/macy-lmp-data',
                method: 'GET',
                data: { sku: sku },
                timeout: 10000,
                success: function(res) {
                    macyData = res.success && res.competitors ? res : null;
                    tryRender();
                },
                error: function() {
                    macyData = null;
                    tryRender();
                }
            });

            $.ajax({
                url: '/reverb-lmp-data',
                method: 'GET',
                data: { sku: sku },
                timeout: 10000,
                success: function(res) {
                    reverbData = res.success && res.competitors ? res : null;
                    tryRender();
                },
                error: function() {
                    reverbData = null;
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

            $.ajax({
                url: '/aliexpress-lmp/data',
                method: 'GET',
                data: { sku: sku },
                timeout: 10000,
                success: function(res) {
                    aliexpressData = res.success && res.competitors ? res : null;
                    tryRender();
                },
                error: function() {
                    aliexpressData = null;
                    tryRender();
                }
            });
        }

        // Pull live LMP (Amazon / eBay / Google) — same as pricing tabulators
        $(document).on('click', '#lmpPullApiBtn', function() {
            const sku = (lmpModalCache && lmpModalCache.sku) || $('#lmpSku').text().trim();
            if (!sku) {
                showToast('No SKU selected', 'error');
                return;
            }
            const filter = (lmpModalCache && lmpModalCache.filter)
                || $('#lmpModal').data('lmp-filter')
                || 'all';
            const ch = String(filter || 'all').toLowerCase();
            if (ch !== 'all' && LMP_PULLABLE_CHANNELS.indexOf(ch) === -1) {
                showToast('Live Pull is available for Amazon, eBay, and Google. Select one of those channels (or All).', 'warning');
                return;
            }
            const $btn = $(this);
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Pulling...');
            const marketplace = $('#lmpModal').data('lmp-marketplace') || ch;
            const showAdd = !!(lmpModalCache && lmpModalCache.showAdd);
            loadLmpCompetitorsModal(sku, marketplace === 'all' ? null : marketplace, showAdd, { refresh: true });
        });

        /** Parse shipping $ from LMP delivery text. Free / Prime free → 0. Unknown → null. */
        function parseLmpShipCost(delivery) {
            if (delivery == null || delivery === '') return null;
            // Numeric shipping (eBay shipping_cost from /ebay-tabulator-view)
            if (typeof delivery === 'number' && !isNaN(delivery)) {
                return Math.max(0, delivery);
            }
            const text = String(delivery).trim();
            if (!text) return null;
            if (/^[\d.]+$/.test(text)) {
                const n = parseFloat(text);
                return isNaN(n) ? null : Math.max(0, n);
            }

            const isFree = /\bfree\b/i.test(text)
                || /\bfree\s+(delivery|shipping)\b/i.test(text)
                || (/\bprime\b/i.test(text) && /\bfree\b/i.test(text));
            if (isFree) return 0;

            const amountMatch = text.match(/\$\s*([0-9]+(?:\.[0-9]{1,2})?)/);
            if (amountMatch) {
                const amount = parseFloat(amountMatch[1]);
                if (!isNaN(amount) && amount >= 0) return amount;
            }
            return null;
        }

        /** Amazon competitor Inv/stock from SerpApi (numeric qty or status text). */
        function formatLmpStockCell(row) {
            if (!row || row.channel !== 'amazon') {
                return '<span class="text-muted">-</span>';
            }
            const stockText = row.stock != null ? String(row.stock).trim() : '';
            const qty = row.stock_quantity != null && row.stock_quantity !== ''
                ? parseInt(row.stock_quantity, 10)
                : NaN;
            if (!stockText && !isFinite(qty)) {
                return '<span class="text-muted">-</span>';
            }
            const tip = (stockText || (isFinite(qty) ? String(qty) : '')).replace(/"/g, '&quot;');
            if (isFinite(qty) && qty === 0) {
                return '<span style="color:#dc3545;font-weight:700;" title="' + tip + '">0</span>';
            }
            if (isFinite(qty) && qty > 0) {
                const color = qty <= 5 ? '#dc3545' : (qty <= 20 ? '#ffc107' : '#28a745');
                return '<span style="color:' + color + ';font-weight:700;" title="' + tip + '">' + qty + '</span>';
            }
            if (/\bout\s+of\s+stock\b/i.test(stockText)) {
                return '<span style="color:#dc3545;font-weight:600;" title="' + tip + '">OOS</span>';
            }
            if (/\bin\s+stock\b/i.test(stockText)) {
                return '<span style="color:#28a745;font-weight:600;" title="' + tip + '">In Stock</span>';
            }
            const short = stockText.length > 14 ? stockText.substring(0, 14) + '…' : stockText;
            return '<span style="font-size:10px;" title="' + tip + '">' + short.replace(/</g, '&lt;') + '</span>';
        }

        /** Prefer row.shipCost (eBay), else parse delivery text (Amazon). */
        function getLmpRowShipCost(row) {
            if (!row) return null;
            if (row.shipCost != null && row.shipCost !== '' && !isNaN(parseFloat(row.shipCost))) {
                return Math.max(0, parseFloat(row.shipCost));
            }
            return parseLmpShipCost(row.delivery);
        }

        function formatLmpDelivery(deliveryOrShip, tipText) {
            const ship = (typeof deliveryOrShip === 'number' && !isNaN(deliveryOrShip))
                ? Math.max(0, deliveryOrShip)
                : parseLmpShipCost(deliveryOrShip);
            if (ship === null) {
                return '<span class="text-muted">-</span>';
            }
            if (ship === 0) {
                const tip = String(tipText || deliveryOrShip || 'Free shipping').replace(/"/g, '&quot;');
                return '<span style="color:#28a745;font-weight:600;" title="' + tip + '">0</span>';
            }
            return '<span style="font-weight:600;">$' + ship.toFixed(2) + '</span>';
        }

        /** Price + shipping (P+S). eBay: same as /ebay-tabulator-view Total = price + shipping_cost. */
        function lmpPricePlusShip(row) {
            const price = parseFloat(row.price) || 0;
            if (!(price > 0)) return null;
            if (row.totalPrice != null && parseFloat(row.totalPrice) > 0) {
                return parseFloat(row.totalPrice);
            }
            const ship = getLmpRowShipCost(row);
            if (ship === null) return price;
            return price + ship;
        }

        /**
         * Value used for L1 / channel badge / sort — must match outer OV L30 LMP.
         * Amazon: price + paid delivery (FREE delivery does not add) — same as backend landedPrice().
         * Google: item price. eBay / BestBuy / Macy / Reverb / Temu: landed P+S.
         */
        function lmpRankValue(row) {
            if (!row) return null;
            const ch = String(row.channel || '').toLowerCase();
            const price = parseFloat(row.price) || 0;
            if (!(price > 0)) return null;
            if (ch === 'google') {
                return price;
            }
            // Amazon + marketplaces with ship: FREE → +0, paid delivery → price + ship
            return lmpPricePlusShip(row) || price;
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
            if (channel === 'aliexpress') {
                return '<span class="lmp-channel-icon aliexpress" title="AliExpress">AE</span>';
            }
            if (channel === 'bestbuy') {
                return '<span class="lmp-channel-icon bestbuy" title="Best Buy">BB</span>';
            }
            if (channel === 'macy') {
                return '<span class="lmp-channel-icon macy" title="Macy\'s">M</span>';
            }
            if (channel === 'reverb') {
                return '<span class="lmp-channel-icon reverb" title="Reverb">R</span>';
            }
            return '';
        }

        function buildLmpMergedRows(sku, amazonRes, ebayRes, googleRes, bestbuyRes, macyRes, reverbRes, temuRes, aliexpressRes) {
            const rows = [];
            const amzList = (amazonRes && amazonRes.competitors) ? amazonRes.competitors : [];
            const ebayList = (ebayRes && ebayRes.competitors) ? ebayRes.competitors : [];
            const googleList = (googleRes && googleRes.competitors) ? googleRes.competitors : [];
            const bestbuyList = (bestbuyRes && bestbuyRes.competitors) ? bestbuyRes.competitors : [];
            const macyList = (macyRes && macyRes.competitors) ? macyRes.competitors : [];
            const reverbList = (reverbRes && reverbRes.competitors) ? reverbRes.competitors : [];
            const temuList = (temuRes && temuRes.competitors) ? temuRes.competitors : [];
            const aliexpressList = (aliexpressRes && aliexpressRes.competitors) ? aliexpressRes.competitors : [];

            amzList.forEach(function(amz) {
                const price = parseFloat(amz.price) || 0;
                if (price <= 0) return;
                const delivery = amz.delivery || '';
                const shipCost = parseLmpShipCost(delivery);
                const stockQty = amz.stock_quantity != null && amz.stock_quantity !== ''
                    ? parseInt(amz.stock_quantity, 10)
                    : null;
                rows.push({
                    channel: 'amazon',
                    id: amz.id,
                    sku: sku,
                    price: price,
                    shipCost: shipCost,
                    totalPrice: shipCost != null ? price + shipCost : null,
                    ignored: !!amz.ignored,
                    link: amz.product_link || amz.link || '',
                    extId: amz.asin || '',
                    image: amz.image || '',
                    title: amz.product_title || amz.title || '',
                    rating: amz.rating != null ? parseFloat(amz.rating) : null,
                    reviews: amz.reviews != null ? parseInt(amz.reviews) : null,
                    old_price: amz.extracted_old_price != null ? parseFloat(amz.extracted_old_price) : null,
                    delivery: delivery,
                    stock: amz.stock || '',
                    stock_quantity: (stockQty != null && isFinite(stockQty)) ? stockQty : null,
                    source: '',
                });
            });

            ebayList.forEach(function(ebay) {
                // Same source as /ebay-tabulator-view LMP modal:
                // Price = item price, Del/Ship = shipping_cost, P+S/Total = total_price
                const basePrice = parseFloat(ebay.price) || 0;
                const shipCost = parseFloat(ebay.shipping_cost);
                const ship = !isNaN(shipCost) ? Math.max(0, shipCost) : 0;
                const totalPrice = parseFloat(ebay.total_price) || (basePrice > 0 ? basePrice + ship : 0);
                if (!(basePrice > 0) && !(totalPrice > 0)) return;
                rows.push({
                    channel: 'ebay',
                    id: ebay.id,
                    sku: sku,
                    price: basePrice > 0 ? basePrice : totalPrice,
                    shipCost: ship,
                    totalPrice: totalPrice > 0 ? totalPrice : null,
                    ignored: !!ebay.ignored,
                    link: ebay.link || ebay.product_link || '',
                    extId: ebay.item_id || '',
                    image: ebay.image || '',
                    title: ebay.product_title || ebay.title || '',
                    rating: null,
                    reviews: null,
                    old_price: null,
                    delivery: ship, // numeric — formatLmpDelivery / Del column
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
                    ignored: !!google.ignored,
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

            bestbuyList.forEach(function(bb) {
                // Same shape as eBay: Price + shipping_cost → total_price
                const basePrice = parseFloat(bb.price) || 0;
                const shipCost = parseFloat(bb.shipping_cost);
                const ship = !isNaN(shipCost) ? Math.max(0, shipCost) : 0;
                const totalPrice = parseFloat(bb.total_price) || (basePrice > 0 ? basePrice + ship : 0);
                if (!(basePrice > 0) && !(totalPrice > 0)) return;
                rows.push({
                    channel: 'bestbuy',
                    id: bb.id,
                    sku: sku,
                    price: basePrice > 0 ? basePrice : totalPrice,
                    shipCost: ship,
                    totalPrice: totalPrice > 0 ? totalPrice : null,
                    ignored: !!bb.ignored,
                    link: bb.link || bb.product_link || '',
                    extId: bb.item_id || '',
                    image: bb.image || '',
                    title: bb.product_title || bb.title || '',
                    rating: null,
                    reviews: null,
                    old_price: null,
                    delivery: ship,
                    source: '',
                });
            });

            macyList.forEach(function(macy) {
                const basePrice = parseFloat(macy.price) || 0;
                const shipCost = parseFloat(macy.shipping_cost);
                const ship = !isNaN(shipCost) ? Math.max(0, shipCost) : 0;
                const totalPrice = parseFloat(macy.total_price) || (basePrice > 0 ? basePrice + ship : 0);
                if (!(basePrice > 0) && !(totalPrice > 0)) return;
                rows.push({
                    channel: 'macy',
                    id: macy.id,
                    sku: sku,
                    price: basePrice > 0 ? basePrice : totalPrice,
                    shipCost: ship,
                    totalPrice: totalPrice > 0 ? totalPrice : null,
                    ignored: !!macy.ignored,
                    link: macy.link || macy.product_link || '',
                    extId: macy.item_id || '',
                    image: macy.image || '',
                    title: macy.product_title || macy.title || '',
                    rating: null,
                    reviews: null,
                    old_price: null,
                    delivery: ship,
                    source: '',
                });
            });

            reverbList.forEach(function(reverb) {
                const basePrice = parseFloat(reverb.price) || 0;
                const shipCost = parseFloat(reverb.shipping_cost);
                const ship = !isNaN(shipCost) ? Math.max(0, shipCost) : 0;
                const totalPrice = parseFloat(reverb.total_price) || (basePrice > 0 ? basePrice + ship : 0);
                if (!(basePrice > 0) && !(totalPrice > 0)) return;
                rows.push({
                    channel: 'reverb',
                    id: reverb.id,
                    sku: sku,
                    price: basePrice > 0 ? basePrice : totalPrice,
                    shipCost: ship,
                    totalPrice: totalPrice > 0 ? totalPrice : null,
                    ignored: !!reverb.ignored,
                    link: reverb.link || reverb.product_link || '',
                    extId: reverb.item_id || '',
                    image: reverb.image || '',
                    title: reverb.product_title || reverb.title || '',
                    rating: null,
                    reviews: null,
                    old_price: null,
                    delivery: ship,
                    source: '',
                });
            });

            temuList.forEach(function(temu) {
                const price = parseFloat(temu.price) || 0;
                if (price <= 0) return;
                // Temu Del: use saved delivery, else default $2.99 when Price < $27
                let ship = parseFloat(temu.delivery != null ? temu.delivery : temu.shipping_cost);
                if (isNaN(ship) || ship < 0) ship = 0;
                if (ship <= 0 && price < 27) ship = 2.99;
                const totalPrice = parseFloat(temu.total_price);
                rows.push({
                    channel: 'temu',
                    id: temu.id,
                    sku: sku,
                    price: price,
                    shipCost: ship,
                    totalPrice: (!isNaN(totalPrice) && totalPrice > 0) ? totalPrice : (price + ship),
                    ignored: !!temu.ignored,
                    link: temu.link || temu.product_link || '',
                    extId: '',
                    image: temu.image || '',
                    title: temu.product_title || temu.title || '',
                    rating: null,
                    reviews: null,
                    old_price: null,
                    delivery: ship,
                    sourceSku: temu.source_sku || sku,
                    source: 'Temu',
                });
            });

            aliexpressList.forEach(function(ae) {
                const price = parseFloat(ae.price) || 0;
                if (price <= 0) return;
                let ship = parseFloat(ae.shipping_cost != null ? ae.shipping_cost
                    : (ae.ship != null ? ae.ship : ae.delivery));
                if (isNaN(ship) || ship < 0) ship = 0;
                const totalPrice = parseFloat(ae.total_price);
                rows.push({
                    channel: 'aliexpress',
                    id: ae.id,
                    sku: sku,
                    price: price,
                    shipCost: ship,
                    totalPrice: (!isNaN(totalPrice) && totalPrice > 0) ? totalPrice : (price + ship),
                    ignored: !!ae.ignored,
                    link: ae.link || ae.product_link || '',
                    extId: '',
                    image: ae.image || '',
                    title: ae.product_title || ae.title || '',
                    rating: null,
                    reviews: null,
                    old_price: null,
                    delivery: ship,
                    sourceSku: ae.source_sku || sku,
                    source: 'AliExpress',
                });
            });

            // Sort by channel L1 metric (Amazon/Google = item price; eBay/etc = P+S)
            rows.sort(function(a, b) {
                const pa = lmpRankValue(a) || a.price || 0;
                const pb = lmpRankValue(b) || b.price || 0;
                return pa - pb;
            });
            return rows;
        }

        /**
         * Our listing for the blue 5 Core LMP row (same idea as /ebay-tabulator-view).
         * Prefer OV L30 breakdown price when available; else main-table fields.
         */
        function getLmpOurListing(sku, channel) {
            const ch = String(channel || 'ebay').toLowerCase();
            const skuKey = String(sku || '').trim();
            let price = 0;
            let link = '';
            let image = '';
            let itemId = '';
            let title = '5 Core — ' + skuKey;

            // 1) OV L30 modal breakdown (most accurate per-channel price / links)
            if (Array.isArray(ovl30ModalData) && ovl30ModalData.length) {
                const mpAliases = {
                    ebay: ['ebay', 'ebay1', 'ebayone'],
                    amazon: ['amazon'],
                    temu: ['temu'],
                };
                const aliases = mpAliases[ch] || [ch];
                const hit = ovl30ModalData.find(function(item) {
                    return aliases.indexOf(String(item.marketplace || '').toLowerCase()) !== -1
                        && parseFloat(item.price) > 0;
                });
                if (hit) {
                    price = parseFloat(hit.price) || 0;
                    link = hit.buyer_link || hit.seller_link || '';
                    image = hit.image_path || hit.image || '';
                }
            }

            // 2) Main Tabulator / fullDataset row
            let rowData = null;
            if (!(price > 0) || !image) {
                if (typeof table !== 'undefined' && table && table.getRows) {
                    const r = (table.getRows() || []).find(function(row) {
                        const d = row.getData();
                        return String(d.sku || '').trim() === skuKey;
                    });
                    if (r) rowData = r.getData();
                }
                if (!rowData && Array.isArray(fullDataset)) {
                    rowData = fullDataset.find(function(r) {
                        return String(r.sku || '').trim() === skuKey && r.is_parent_summary !== true;
                    }) || null;
                }
                if (rowData) {
                    if (!(price > 0)) {
                        if (ch === 'ebay') price = parseFloat(rowData.ebay1_price) || 0;
                        else if (ch === 'amazon') price = parseFloat(rowData.amazon_price) || 0;
                        else if (ch === 'temu') price = parseFloat(rowData.temu_price) || parseFloat(rowData.temu_base_price) || 0;
                    }
                    if (!image) image = rowData.image_path || rowData.image || '';
                    if (ch === 'ebay') itemId = rowData.ebay1_item_id ? String(rowData.ebay1_item_id) : '';
                }
            }

            if (!link && ch === 'ebay' && itemId) {
                link = 'https://www.ebay.com/itm/' + itemId;
            }
            if (!title || title === '5 Core — ') title = '5 Core — ' + (skuKey || 'our listing');

            return { price: price, link: link, image: image, itemId: itemId, title: title, channel: ch };
        }

        function buildLmpFiveCoreRowHtml(our) {
            const price = parseFloat(our.price) || 0;
            if (!(price > 0)) return '';
            const thumb = our.image
                ? '<img src="' + String(our.image).replace(/"/g, '&quot;') + '" alt="" class="rounded lmp-thumb me-1" style="object-fit:contain;" onerror="this.style.display=\'none\'">'
                : '';
            const linkHtml = our.link
                ? ' <a href="' + String(our.link).replace(/"/g, '&quot;') + '" target="_blank" class="text-primary ms-1" title="Open our listing"><i class="fa fa-external-link"></i></a>'
                : '';
            const chIcon = lmpChannelIconHtml(our.channel || 'ebay');
            const priceCell = '<div class="d-flex align-items-center">'
                + thumb + chIcon
                + '<span class="lmp-five-core-price">$' + price.toFixed(2) + '</span>'
                + ' <span class="badge bg-primary ms-1">Ours</span>'
                + linkHtml
                + '</div>';
            const psCell = '<strong class="lmp-five-core-price">$' + price.toFixed(2) + '</strong>'
                + ' <span class="badge bg-primary ms-1">5 CORE</span>';
            const titleEsc = String(our.title || '5 Core (our listing)').replace(/"/g, '&quot;');
            return '<tr class="lmp-five-core-row" title="Our 5 Core listing — sorted by price to show market level">'
                + '<td class="text-center text-muted small">—</td>'
                + '<td class="text-center text-muted small">—</td>'
                + '<td>' + priceCell + '</td>'
                + '<td class="text-muted">-</td>'
                + '<td class="text-muted">-</td>'
                + '<td><span class="badge bg-primary">Ours</span></td>'
                + '<td title="' + titleEsc + '">' + psCell + '</td>'
                + '<td class="text-center text-muted small">—</td>'
                + '<td class="text-center text-muted small">—</td>'
                + '<td class="text-muted small">' + titleEsc + '</td>'
                + '</tr>';
        }

        function renderLmpMergedTable() {
            const sku = lmpModalCache.sku;
            const filter = lmpModalCache.filter || 'all';
            const allRows = lmpModalCache.rows || [];
            const rows = filter === 'all' ? allRows : allRows.filter(function(r) { return r.channel === filter; });

            // L1 / badges: Amazon/Google = item price (matches outer); eBay/etc = P+S
            function activeRanked(ch) {
                return allRows
                    .filter(function(r) { return r.channel === ch && !r.ignored; })
                    .map(function(r) { return lmpRankValue(r); })
                    .filter(function(v) { return v != null && v > 0; });
            }
            const amzPrices = activeRanked('amazon');
            const ebayPrices = activeRanked('ebay');
            const googlePrices = activeRanked('google');
            const bestbuyPrices = activeRanked('bestbuy');
            const macyPrices = activeRanked('macy');
            const reverbPrices = activeRanked('reverb');
            const temuPrices = activeRanked('temu');
            const amzLowest = amzPrices.length ? Math.min.apply(null, amzPrices) : null;
            const ebayLowest = ebayPrices.length ? Math.min.apply(null, ebayPrices) : null;
            const googleLowest = googlePrices.length ? Math.min.apply(null, googlePrices) : null;
            const bestbuyLowest = bestbuyPrices.length ? Math.min.apply(null, bestbuyPrices) : null;
            const macyLowest = macyPrices.length ? Math.min.apply(null, macyPrices) : null;
            const reverbLowest = reverbPrices.length ? Math.min.apply(null, reverbPrices) : null;
            const temuLowest = temuPrices.length ? Math.min.apply(null, temuPrices) : null;
            const aePrices = activeRanked('aliexpress');
            const aliexpressLowest = aePrices.length ? Math.min.apply(null, aePrices) : null;
            const channelLowest = {
                amazon: amzLowest,
                ebay: ebayLowest,
                google: googleLowest,
                bestbuy: bestbuyLowest,
                macy: macyLowest,
                reverb: reverbLowest,
                temu: temuLowest,
                aliexpress: aliexpressLowest,
            };

            const counts = {
                all: allRows.length,
                amazon: allRows.filter(function(r) { return r.channel === 'amazon'; }).length,
                ebay: allRows.filter(function(r) { return r.channel === 'ebay'; }).length,
                google: allRows.filter(function(r) { return r.channel === 'google'; }).length,
                bestbuy: allRows.filter(function(r) { return r.channel === 'bestbuy'; }).length,
                macy: allRows.filter(function(r) { return r.channel === 'macy'; }).length,
                reverb: allRows.filter(function(r) { return r.channel === 'reverb'; }).length,
                temu: allRows.filter(function(r) { return r.channel === 'temu'; }).length,
                aliexpress: allRows.filter(function(r) { return r.channel === 'aliexpress'; }).length,
            };

            let html = '';
            html += '<div class="lmp-channel-filters">';
            [
                { key: 'all', label: 'All' },
                { key: 'amazon', label: 'Amazon' },
                { key: 'ebay', label: 'eBay' },
                { key: 'google', label: 'Google' },
                { key: 'bestbuy', label: 'BestBuy' },
                { key: 'macy', label: 'Macy' },
                { key: 'reverb', label: 'Reverb' },
                { key: 'temu', label: 'Temu' },
                { key: 'aliexpress', label: 'AliExpress' },
            ].forEach(function(opt) {
                const active = filter === opt.key ? ' active' : '';
                let btnClass = 'btn-outline-secondary';
                if (filter === opt.key) {
                    if (opt.key === 'amazon') btnClass = 'btn-warning';
                    else if (opt.key === 'ebay') btnClass = 'btn-primary';
                    else if (opt.key === 'google') btnClass = 'btn-success';
                    else if (opt.key === 'bestbuy') btnClass = 'btn-primary';
                    else if (opt.key === 'macy') btnClass = 'btn-danger';
                    else if (opt.key === 'reverb') btnClass = 'btn-danger';
                    else if (opt.key === 'temu') btnClass = 'btn-warning';
                    else if (opt.key === 'aliexpress') btnClass = 'btn-danger';
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
            if (bestbuyLowest != null) {
                badgeParts.push('<span class="badge" style="background:#0046be;color:#fff;">BB $' + bestbuyLowest.toFixed(2) + '</span>');
            }
            if (macyLowest != null) {
                badgeParts.push('<span class="badge" style="background:#e21a2c;color:#fff;">Macy $' + macyLowest.toFixed(2) + '</span>');
            }
            if (reverbLowest != null) {
                badgeParts.push('<span class="badge" style="background:#d9281d;color:#fff;">Reverb $' + reverbLowest.toFixed(2) + '</span>');
            }
            if (temuLowest != null) {
                // Direct Temu LMP (raw L1 — no Recovery calculation)
                badgeParts.push('<span class="badge" style="background:#fb7701;color:#fff;" title="Temu LMP (direct)">'
                    + 'Temu $' + temuLowest.toFixed(2) + '</span>');
            }
            if (aliexpressLowest != null) {
                badgeParts.push('<span class="badge" style="background:#e43225;color:#fff;" title="AliExpress LMP">'
                    + 'AE $' + aliexpressLowest.toFixed(2) + '</span>');
            }
            if (badgeParts.length) {
                html += '<div class="lmp-lowest-badges">' + badgeParts.join('') + '</div>';
            }

            // Add LMP form (shown when opened via +, or when a specific channel filter is active)
            const addChannel = (filter !== 'all') ? filter : (lmpModalCache.showAdd ? 'amazon' : null);
            if (addChannel || lmpModalCache.showAdd) {
                html += buildLmpAddFormHtml(sku, addChannel || 'amazon');
            }

            // 5 Core our-listing row — same as /ebay-tabulator-view (insert at our price level)
            // Show for eBay (primary), and Amazon/Temu when those filters are active.
            const fiveCoreChannel = (filter === 'ebay' || filter === 'amazon' || filter === 'temu')
                ? filter
                : (filter === 'all' ? 'ebay' : null);
            const ourListing = fiveCoreChannel ? getLmpOurListing(sku, fiveCoreChannel) : { price: 0 };
            const fiveCoreHtml = buildLmpFiveCoreRowHtml(ourListing);

            if (!rows.length && !fiveCoreHtml) {
                const labelMap = { all: 'Amazon, eBay, Google, BestBuy, Macy, Reverb, or Temu', amazon: 'Amazon', ebay: 'eBay', google: 'Google', bestbuy: 'BestBuy', macy: 'Macy', reverb: 'Reverb', temu: 'Temu' };
                const label = labelMap[filter] || 'competitors';
                html += '<div class="alert alert-info mb-0 py-2 px-2"><i class="fa fa-info-circle"></i> No ' + label + ' competitors found</div>';
                $('#lmpDataList').html(html);
                applyLmpEditFormState();
                return;
            }

            let toolbar = '<div class="d-flex align-items-center justify-content-between gap-2 mb-2 flex-wrap">';
            toolbar += '<div class="small text-muted mb-0">Select competitors, then delete</div>';
            if (rows.length > 0) {
                toolbar += '<button type="button" class="btn btn-sm btn-danger" id="lmp-bulk-delete-btn" disabled>'
                    + '<i class="fa fa-trash"></i> Delete Selected</button>';
            }
            toolbar += '</div>';
            html += toolbar;

            html += '<div class="table-responsive"><table class="table table-hover table-bordered table-sm"><thead class="table-light">'
                + '<tr>'
                + '<th class="text-center" style="width:36px;" title="Select for bulk delete">'
                + '<input type="checkbox" class="form-check-input" id="lmp-select-all-cb" title="Select all">'
                + '</th>'
                + '<th>#</th>'
                + '<th>Price</th><th>Rating</th><th>Rev</th><th>Del</th>'
                + '<th title="Price + Shipping">P+S</th>'
                + '<th title="Competitor inventory / stock (Amazon SerpApi)">Inv</th>'
                + '<th title="Ignore for L1 (same as Temu Decrease)">Ign</th><th></th></tr>'
                + '</thead><tbody>';

            let fiveCoreInserted = false;
            const ourPrice = parseFloat(ourListing.price) || 0;
            let lIndex = 0;

            rows.forEach(function(row) {
                const rankVal = lmpRankValue(row) || row.price;
                const landedPs = lmpPricePlusShip(row) || row.price;
                // Insert 5 Core just before the first competitor at/above our price (never counts as L1)
                if (!fiveCoreInserted && fiveCoreHtml && ourPrice > 0 && rankVal >= ourPrice) {
                    html += fiveCoreHtml;
                    fiveCoreInserted = true;
                }

                lIndex++;
                const sn = 'L' + lIndex;
                const lowest = channelLowest[row.channel];
                const isLowest = !row.ignored && lowest != null && Math.abs(rankVal - lowest) < 0.01;
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
                const shipCost = getLmpRowShipCost(row);
                const shipChannelLabel = ({ ebay: 'eBay', bestbuy: 'BestBuy', macy: 'Macy', reverb: 'Reverb', aliexpress: 'AliExpress', temu: 'Temu' })[row.channel];
                const deliveryCell = (shipCost !== null)
                    ? formatLmpDelivery(
                        shipCost,
                        shipChannelLabel
                            ? (shipChannelLabel + ' shipping_cost: $' + Number(shipCost).toFixed(2))
                            : row.delivery
                    )
                    : formatLmpDelivery(row.delivery);
                const psTotal = landedPs;
                const psTip = (shipCost === null)
                    ? 'Price (no ship data)'
                    : ('Price $' + row.price.toFixed(2) + ' + Ship $' + Number(shipCost).toFixed(2));
                const psCell = (psTotal != null && psTotal > 0)
                    ? '<span class="lmp-ps-cell" style="font-weight:700;" title="' + psTip.replace(/"/g, '&quot;') + '">$' + psTotal.toFixed(2) + '</span>'
                    : '<span class="text-muted">-</span>';
                const stockCell = formatLmpStockCell(row);
                const ignoreCb = '<input type="checkbox" class="form-check-input lmp-ignore-cb" title="Ignore for L1"'
                    + (row.ignored ? ' checked' : '')
                    + ' data-id="' + String(row.id).replace(/"/g, '&quot;') + '"'
                    + ' data-marketplace="' + row.channel + '"'
                    + ' data-sku="' + String(sku || '').replace(/"/g, '&quot;') + '">';
                const titleAttr = (row.title || row.source || '').replace(/"/g, '&quot;');
                const linkAttr = String(row.link || '').replace(/"/g, '&quot;');
                const extAttr = String(row.extId || '').replace(/"/g, '&quot;');
                const skuAttr = String(sku || '').replace(/"/g, '&quot;');
                const sourceSkuAttr = String(row.sourceSku || sku || '').replace(/"/g, '&quot;');
                const deliveryAttr = (row.delivery != null && row.delivery !== '') ? String(row.delivery) : '';
                const selectCb = '<input type="checkbox" class="form-check-input lmp-row-cb" title="Select for delete"'
                    + ' data-id="' + String(row.id).replace(/"/g, '&quot;') + '"'
                    + ' data-marketplace="' + row.channel + '"'
                    + ' data-sku="' + skuAttr + '"'
                    + ' data-source-sku="' + sourceSkuAttr + '"'
                    + ' data-price="' + row.price + '"'
                    + ' data-delivery="' + deliveryAttr + '"'
                    + ' data-ext-id="' + extAttr + '"'
                    + ' data-link="' + linkAttr + '">';
                const editBtn = '<button type="button" class="btn btn-sm btn-outline-warning edit-lmp-row-btn me-1" data-id="' + row.id
                    + '" data-marketplace="' + row.channel
                    + '" data-sku="' + skuAttr
                    + '" data-source-sku="' + sourceSkuAttr
                    + '" data-price="' + row.price
                    + '" data-delivery="' + deliveryAttr
                    + '" data-link="' + linkAttr
                    + '" data-ext-id="' + extAttr
                    + '" title="Edit price"><i class="fa fa-edit"></i></button>';
                const delBtn = '<button type="button" class="btn btn-sm btn-danger delete-lmp-row-btn" data-id="' + row.id
                    + '" data-marketplace="' + row.channel
                    + '" data-sku="' + skuAttr
                    + '" data-source-sku="' + sourceSkuAttr
                    + '" data-price="' + row.price
                    + '" data-delivery="' + deliveryAttr
                    + '" data-ext-id="' + extAttr
                    + '" data-link="' + linkAttr
                    + '" title="Delete"><i class="fa fa-trash"></i></button>';
                const rowClass = (row.ignored ? 'lmp-ignored-row ' : '') + (isLowest ? 'table-success' : '');
                html += '<tr class="' + rowClass + '" title="' + titleAttr + '">'
                    + '<td class="text-center align-middle">' + selectCb + '</td>'
                    + '<td>' + sn + '</td>'
                    + '<td>' + priceCell + '</td>'
                    + '<td>' + ratingCell + '</td>'
                    + '<td>' + reviewsCell + '</td>'
                    + '<td>' + deliveryCell + '</td>'
                    + '<td>' + psCell + '</td>'
                    + '<td class="text-center">' + stockCell + '</td>'
                    + '<td class="text-center">' + ignoreCb + '</td>'
                    + '<td class="text-nowrap">' + editBtn + delBtn + '</td>'
                    + '</tr>';
            });

            if (!fiveCoreInserted && fiveCoreHtml) {
                html += fiveCoreHtml;
            }

            html += '</tbody></table></div>';
            $('#lmpDataList').html(html);
            applyLmpEditFormState();
            updateLmpBulkDeleteUi();
        }

        function buildLmpAddFormHtml(sku, channel) {
            const ch = (channel || 'amazon').toLowerCase();
            const labelMap = { amazon: 'Amazon', ebay: 'eBay', google: 'Google', bestbuy: 'BestBuy', macy: 'Macy', reverb: 'Reverb', temu: 'Temu', aliexpress: 'AliExpress' };
            const label = labelMap[ch] || ch;
            let idField = '';
            if (ch === 'amazon') {
                idField = '<div class="col-4"><label class="form-label mb-0 small">ASIN</label>'
                    + '<input type="text" class="form-control" id="lmpAddId" placeholder="B0XXXXXXXX" required></div>';
            } else if (ch === 'ebay' || ch === 'bestbuy' || ch === 'macy' || ch === 'reverb') {
                const idPlaceholder = ({ ebay: 'eBay item id', bestbuy: 'BestBuy item id', macy: 'Macy item id', reverb: 'Reverb item id' })[ch] || 'Item id';
                idField = '<div class="col-4"><label class="form-label mb-0 small">Item ID</label>'
                    + '<input type="text" class="form-control" id="lmpAddId" placeholder="' + idPlaceholder + '" required></div>';
            } else if (ch === 'google') {
                idField = '<div class="col-4"><label class="form-label mb-0 small">Product ID</label>'
                    + '<input type="text" class="form-control" id="lmpAddId" placeholder="Product ID" required></div>';
            } else {
                // Temu / AliExpress: no ASIN/item id
                idField = '';
            }
            const showShip = (ch === 'aliexpress');
            const shipField = showShip
                ? ('<div class="col-2"><label class="form-label mb-0 small">Ship</label>'
                    + '<input type="text" class="form-control" id="lmpAddShip" inputmode="decimal" placeholder="0.00" autocomplete="off" title="Shipping cost (Del)"></div>')
                : '';
            let priceCol = idField ? 'col-3' : 'col-4';
            let linkCol = idField ? 'col-3' : 'col-5';
            if (showShip && !idField) {
                priceCol = 'col-3';
                linkCol = 'col-4';
            } else if (showShip && idField) {
                priceCol = 'col-2';
                linkCol = 'col-3';
            }
            const chLabel = ({ aliexpress: 'AliExpress' })[ch] || label;
            return '<div class="lmp-add-form-box">'
                + '<div class="d-flex align-items-center justify-content-between mb-1">'
                + '<strong style="font-size:12px;"><i class="fas fa-plus-circle text-success me-1"></i>Add ' + chLabel + ' LMP</strong>'
                + '<select class="form-select form-select-sm" id="lmpAddChannel" style="width:120px;">'
                + '<option value="amazon"' + (ch === 'amazon' ? ' selected' : '') + '>Amazon</option>'
                + '<option value="ebay"' + (ch === 'ebay' ? ' selected' : '') + '>eBay</option>'
                + '<option value="google"' + (ch === 'google' ? ' selected' : '') + '>Google</option>'
                + '<option value="bestbuy"' + (ch === 'bestbuy' ? ' selected' : '') + '>BestBuy</option>'
                + '<option value="macy"' + (ch === 'macy' ? ' selected' : '') + '>Macy</option>'
                + '<option value="reverb"' + (ch === 'reverb' ? ' selected' : '') + '>Reverb</option>'
                + '<option value="temu"' + (ch === 'temu' ? ' selected' : '') + '>Temu</option>'
                + '<option value="aliexpress"' + (ch === 'aliexpress' ? ' selected' : '') + '>AliExpress</option>'
                + '</select></div>'
                + '<form id="lmpAddForm" class="row g-1 align-items-end" novalidate data-sku="' + String(sku || '').replace(/"/g, '&quot;') + '">'
                + '<input type="hidden" id="lmpEditId" value="">'
                + '<input type="hidden" id="lmpFormChannel" value="' + ch + '">'
                + idField
                + '<div class="' + priceCol + '"><label class="form-label mb-0 small">Price</label>'
                + '<input type="text" class="form-control" id="lmpAddPrice" inputmode="decimal" placeholder="0.00" autocomplete="off"></div>'
                + shipField
                + '<div class="' + linkCol + '"><label class="form-label mb-0 small">Link</label>'
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
        function extractBestbuyItemId(link) {
            const m = String(link || '').match(/\/site\/[^\/]+\/(\d+)\.p/i)
                || String(link || '').match(/[?&]skuId=(\d+)/i)
                || String(link || '').match(/bestbuy\.com\/.*?\/(\d{5,})/i);
            return m ? m[1] : '';
        }
        function extractMacyItemId(link) {
            const m = String(link || '').match(/[?&]ID=(\d+)/i)
                || String(link || '').match(/\/(\d+)\.html/i)
                || String(link || '').match(/macys\.com\/.*?[?&]ID=(\d+)/i);
            return m ? m[1] : '';
        }
        function extractReverbItemId(link) {
            const m = String(link || '').match(/reverb\.com\/item\/(\d+)/i)
                || String(link || '').match(/\/item\/(\d+)/i);
            return m ? m[1] : '';
        }

        $(document).on('input change', '#lmpAddPrice, #lmpAddShip, #lmpAddLink, #lmpAddId', function() {
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
                delivery: $btn.attr('data-delivery') || $btn.data('delivery'),
                sourceSku: $btn.attr('data-source-sku') || $btn.data('source-sku') || '',
                sku: $btn.attr('data-sku') || $btn.data('sku') || '',
            });
        });

        function refreshLmpAfterMutation(sku, channel) {
            resetLmpEditState();
            // Refresh LMP drawer only — do NOT reload full OV L30 breakdown (too slow)
            loadLmpCompetitorsModal(sku, channel, true);
            // Patch OV L30 LMP cell from updated cache when available
            setTimeout(function() {
                const ch = String(channel || 'amazon').toLowerCase();
                const rows = (lmpModalCache.rows || []).filter(function(r) {
                    return r.channel === ch && !r.ignored;
                });
                let lowest = null;
                rows.forEach(function(r) {
                    const ranked = (typeof lmpRankValue === 'function' ? lmpRankValue(r) : null)
                        || r.price;
                    if (ranked > 0 && (lowest == null || ranked < lowest)) lowest = ranked;
                });
                patchOvl30LmpCell(ch, lowest);
            }, 400);
        }

        function saveTemuLmpEntries(sku, entries, done) {
            if (!sku) {
                done(false, 'Missing SKU');
                return;
            }
            $.ajax({
                url: '/temu-lmp/save',
                method: 'POST',
                contentType: 'application/json',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'Accept': 'application/json',
                },
                data: JSON.stringify({ sku: sku, lmp_entries: entries }),
                success: function(r) { done(!!(r && r.success), (r && (r.message || r.error)) || 'Temu LMP saved'); },
                error: function(xhr) {
                    let msg = (xhr.responseJSON && (xhr.responseJSON.error || xhr.responseJSON.message)) || 'Failed to save Temu LMP';
                    if (xhr.responseJSON && xhr.responseJSON.errors) {
                        const first = Object.values(xhr.responseJSON.errors)[0];
                        if (Array.isArray(first) && first[0]) msg = first[0];
                    }
                    done(false, msg);
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
                        let delivery = parseFloat(c.delivery != null ? c.delivery : c.shipping_cost);
                        if (isNaN(delivery) || delivery < 0) delivery = 0;
                        return {
                            price: c.price,
                            delivery: delivery,
                            link: c.link || c.product_link || null,
                            ignored: !!c.ignored,
                            source_sku: c.source_sku || sku,
                        };
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

        function saveAliexpressLmpEntries(sku, entries, done) {
            if (!sku) {
                done(false, 'Missing SKU');
                return;
            }
            $.ajax({
                url: '/aliexpress-lmp/save',
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'Accept': 'application/json',
                },
                data: {
                    _token: '{{ csrf_token() }}',
                    sku: sku,
                    lmp_entries: entries,
                },
                success: function(r) { done(!!(r && r.success), (r && (r.message || r.error)) || 'AliExpress LMP saved'); },
                error: function(xhr) {
                    let msg = (xhr.responseJSON && (xhr.responseJSON.error || xhr.responseJSON.message)) || 'Failed to save AliExpress LMP';
                    if (xhr.responseJSON && xhr.responseJSON.errors) {
                        const first = Object.values(xhr.responseJSON.errors)[0];
                        if (Array.isArray(first) && first[0]) msg = first[0];
                    }
                    done(false, msg);
                }
            });
        }

        function withAliexpressLmpEntries(sku, mutator, done) {
            $.ajax({
                url: '/aliexpress-lmp/data',
                method: 'GET',
                data: { sku: sku },
                success: function(res) {
                    const existing = (res && res.competitors) ? res.competitors : [];
                    const entries = existing.map(function(c) {
                        let ship = parseFloat(c.ship != null ? c.ship
                            : (c.shipping_cost != null ? c.shipping_cost : c.delivery));
                        if (isNaN(ship) || ship < 0) ship = 0;
                        return {
                            price: c.price,
                            ship: ship,
                            delivery: ship,
                            link: c.link || c.product_link || null,
                            ignored: !!c.ignored,
                            source_sku: c.source_sku || sku,
                        };
                    });
                    const next = mutator(entries, existing);
                    if (next === false) {
                        done(false, 'AliExpress LMP entry not found');
                        return;
                    }
                    saveAliexpressLmpEntries(sku, next, done);
                },
                error: function() {
                    done(false, 'Failed to load AliExpress LMP entries');
                }
            });
        }

        function findAliexpressEntryIndex(entries, editId, origPrice, origLink) {
            const idMatch = String(editId || '').match(/^aliexpress-(\d+)$/i);
            if (idMatch) {
                const idx = parseInt(idMatch[1], 10) - 1;
                if (idx >= 0 && idx < entries.length) return idx;
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

        $(document).on('change', '.lmp-ignore-cb', function() {
            const $cb = $(this);
            const id = $cb.attr('data-id') || $cb.data('id');
            const marketplace = ($cb.attr('data-marketplace') || $cb.data('marketplace') || '').toLowerCase();
            const sku = $cb.attr('data-sku') || $cb.data('sku') || $('#lmpSku').text() || '';
            const ignored = $cb.is(':checked');
            $cb.prop('disabled', true);

            $.ajax({
                url: '/cvr-master-lmp-ignore',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                data: { id: id, marketplace: marketplace, sku: sku, ignored: ignored ? 1 : 0 },
                success: function(res) {
                    $cb.prop('disabled', false);
                    if (res && res.success) {
                        // Update cache + re-render so L1 trophy moves
                        (lmpModalCache.rows || []).forEach(function(r) {
                            if (String(r.id) === String(id) && r.channel === marketplace) {
                                r.ignored = ignored;
                            }
                        });
                        renderLmpMergedTable();
                        showToast(res.message || (ignored ? 'Ignored for L1' : 'Included in L1'), 'success');
                        // Patch OV L30 LMP cell only (no full breakdown reload)
                        const ch = String(marketplace || '').toLowerCase();
                        const active = (lmpModalCache.rows || []).filter(function(r) {
                            return r.channel === ch && !r.ignored;
                        });
                        let lowest = null;
                        active.forEach(function(r) {
                            const ranked = (typeof lmpRankValue === 'function' ? lmpRankValue(r) : null)
                                || r.price;
                            if (ranked > 0 && (lowest == null || ranked < lowest)) lowest = ranked;
                        });
                        patchOvl30LmpCell(ch, lowest);
                    } else {
                        $cb.prop('checked', !ignored);
                        showToast((res && res.error) || 'Failed to update ignore', 'error');
                    }
                },
                error: function(xhr) {
                    $cb.prop('disabled', false);
                    $cb.prop('checked', !ignored);
                    showToast((xhr.responseJSON && xhr.responseJSON.error) || 'Failed to update ignore', 'error');
                }
            });
        });

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
            if (channel === 'bestbuy' && !idVal) idVal = extractBestbuyItemId(link);
            if (channel === 'macy' && !idVal) idVal = extractMacyItemId(link);
            if (channel === 'reverb' && !idVal) idVal = extractReverbItemId(link);
            if (channel !== 'temu' && channel !== 'aliexpress' && !idVal) {
                showToast(
                    channel === 'amazon' ? 'ASIN is required'
                        : ((channel === 'ebay' || channel === 'bestbuy' || channel === 'macy' || channel === 'reverb') ? 'Item ID is required' : 'Product ID is required'),
                    'error'
                );
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
            if (channel === 'bestbuy') {
                $.ajax({
                    url: isEdit ? '/bestbuy-lmp-update' : '/bestbuy-lmp-add',
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    data: isEdit
                        ? { id: editId, item_id: idVal, price: price, shipping_cost: 0, product_link: link || null }
                        : { sku: sku, item_id: idVal, price: price, shipping_cost: 0, product_link: link || null },
                    success: function(r) { done(!!r.success, r.message || (isEdit ? 'BestBuy LMP updated' : 'BestBuy LMP added')); },
                    error: function(xhr) {
                        done(false, (xhr.responseJSON && (xhr.responseJSON.error || xhr.responseJSON.message)) || (isEdit ? 'Failed to update BestBuy LMP' : 'Failed to add BestBuy LMP'));
                    }
                });
                return;
            }
            if (channel === 'macy') {
                $.ajax({
                    url: isEdit ? '/macy-lmp-update' : '/macy-lmp-add',
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    data: isEdit
                        ? { id: editId, item_id: idVal, price: price, shipping_cost: 0, product_link: link || null }
                        : { sku: sku, item_id: idVal, price: price, shipping_cost: 0, product_link: link || null },
                    success: function(r) { done(!!r.success, r.message || (isEdit ? 'Macy LMP updated' : 'Macy LMP added')); },
                    error: function(xhr) {
                        done(false, (xhr.responseJSON && (xhr.responseJSON.error || xhr.responseJSON.message)) || (isEdit ? 'Failed to update Macy LMP' : 'Failed to add Macy LMP'));
                    }
                });
                return;
            }
            if (channel === 'reverb') {
                $.ajax({
                    url: isEdit ? '/reverb-lmp-update' : '/reverb-lmp-add',
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    data: isEdit
                        ? { id: editId, item_id: idVal, price: price, shipping_cost: 0, product_link: link || null }
                        : { sku: sku, item_id: idVal, price: price, shipping_cost: 0, product_link: link || null },
                    success: function(r) { done(!!r.success, r.message || (isEdit ? 'Reverb LMP updated' : 'Reverb LMP added')); },
                    error: function(xhr) {
                        done(false, (xhr.responseJSON && (xhr.responseJSON.error || xhr.responseJSON.message)) || (isEdit ? 'Failed to update Reverb LMP' : 'Failed to add Reverb LMP'));
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

            // Temu: append or replace entry then save (keeps source_sku + delivery)
            if (channel === 'temu') {
                if (isEdit) {
                    const origPrice = lmpEditState ? lmpEditState.origPrice : null;
                    const origLink = lmpEditState ? lmpEditState.origLink : '';
                    const sourceSku = (lmpEditState && lmpEditState.sourceSku) ? lmpEditState.sourceSku : sku;
                    const origDelivery = lmpEditState && lmpEditState.origDelivery != null
                        ? parseFloat(lmpEditState.origDelivery) : 0;
                    withTemuLmpEntries(sku, function(entries) {
                        const idx = findTemuEntryIndex(entries, editId, origPrice, origLink);
                        if (idx < 0) return false;
                        const prev = entries[idx] || {};
                        let delivery = parseFloat(prev.delivery);
                        if (isNaN(delivery) || delivery < 0) delivery = (!isNaN(origDelivery) && origDelivery > 0) ? origDelivery : 0;
                        entries[idx] = {
                            price: price,
                            delivery: delivery,
                            link: link || null,
                            ignored: !!prev.ignored,
                            source_sku: prev.source_sku || sourceSku || sku,
                        };
                        return entries;
                    }, done);
                    return;
                }
                withTemuLmpEntries(sku, function(entries) {
                    entries.push({
                        price: price,
                        delivery: (price < 27 ? 2.99 : 0),
                        link: link || null,
                        ignored: false,
                        source_sku: sku,
                    });
                    return entries;
                }, function(ok, msg) {
                    if (!ok && msg === 'Failed to load Temu LMP entries') {
                        saveTemuLmpEntries(sku, [{
                            price: price,
                            delivery: (price < 27 ? 2.99 : 0),
                            link: link || null,
                            ignored: false,
                            source_sku: sku,
                        }], done);
                        return;
                    }
                    done(ok, msg || 'Temu LMP added');
                });
                return;
            }

            // AliExpress: save to aliexpress_lmp_data_sheet via /aliexpress-lmp/save (price + ship)
            if (channel === 'aliexpress') {
                let ship = parseLmpPriceInput($('#lmpAddShip').val());
                if (isNaN(ship) || ship < 0) ship = 0;
                if (isEdit) {
                    const origPrice = lmpEditState ? lmpEditState.origPrice : null;
                    const origLink = lmpEditState ? lmpEditState.origLink : '';
                    const sourceSku = (lmpEditState && lmpEditState.sourceSku) ? lmpEditState.sourceSku : sku;
                    withAliexpressLmpEntries(sku, function(entries) {
                        const idx = findAliexpressEntryIndex(entries, editId, origPrice, origLink);
                        if (idx < 0) return false;
                        const prev = entries[idx] || {};
                        entries[idx] = {
                            price: price,
                            ship: ship,
                            delivery: ship,
                            link: link || null,
                            ignored: !!prev.ignored,
                            source_sku: prev.source_sku || sourceSku || sku,
                        };
                        return entries;
                    }, done);
                    return;
                }
                withAliexpressLmpEntries(sku, function(entries) {
                    entries.push({
                        price: price,
                        ship: ship,
                        delivery: ship,
                        link: link || null,
                        ignored: false,
                        source_sku: sku,
                    });
                    return entries;
                }, function(ok, msg) {
                    if (!ok && msg === 'Failed to load AliExpress LMP entries') {
                        saveAliexpressLmpEntries(sku, [{
                            price: price,
                            ship: ship,
                            delivery: ship,
                            link: link || null,
                            ignored: false,
                            source_sku: sku,
                        }], done);
                        return;
                    }
                    done(ok, msg || 'AliExpress LMP added');
                });
                return;
            }
        });

        $(document).on('click', '.lmp-channel-filter-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (lmpEditState) resetLmpEditState();
            const filter = $(this).data('filter') || 'all';
            lmpModalCache.filter = filter;
            if (filter !== 'all') lmpModalCache.showAdd = true;
            $('#lmpModal').data('lmp-filter', filter);
            updateLmpPullBtnTitle(filter);
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

        function updateLmpBulkDeleteUi() {
            const $cbs = $('#lmpModal .lmp-row-cb');
            const $checked = $cbs.filter(':checked');
            const total = $cbs.length;
            const selected = $checked.length;
            const $all = $('#lmp-select-all-cb');
            if ($all.length) {
                $all.prop('checked', total > 0 && selected === total);
                $all.prop('indeterminate', selected > 0 && selected < total);
            }
            const $btn = $('#lmp-bulk-delete-btn');
            if ($btn.length) {
                $btn.prop('disabled', selected === 0)
                    .html(selected > 0
                        ? '<i class="fa fa-trash"></i> Delete Selected (' + selected + ')'
                        : '<i class="fa fa-trash"></i> Delete Selected');
            }
        }

        function collectCheckedLmpRows() {
            return $('#lmpModal .lmp-row-cb:checked').map(function() {
                const $el = $(this);
                return {
                    id: $el.attr('data-id') || $el.data('id'),
                    marketplace: ($el.attr('data-marketplace') || $el.data('marketplace') || '').toLowerCase(),
                    sku: $el.attr('data-sku') || $el.data('sku') || $('#lmpSku').text(),
                    price: $el.attr('data-price') || $el.data('price'),
                    extId: $el.attr('data-ext-id') || $el.data('ext-id') || '',
                    link: $el.attr('data-link') || $el.data('link') || '',
                };
            }).get().filter(function(r) { return r.id || r.extId; });
        }

        function deleteLmpApiRow(item) {
            const marketplace = (item.marketplace || '').toLowerCase();
            const id = item.id;
            const extId = item.extId || '';
            const url = marketplace === 'amazon' ? '/amazon/lmp/delete'
                : (marketplace === 'google' ? '/google-lmp-delete'
                    : (marketplace === 'bestbuy' ? '/bestbuy-lmp-delete'
                        : (marketplace === 'macy' ? '/macy-lmp-delete'
                            : (marketplace === 'reverb' ? '/reverb-lmp-delete' : '/ebay-lmp-delete'))));
            const payload = { id: id, _token: '{{ csrf_token() }}' };
            if ((marketplace === 'ebay' || marketplace === 'bestbuy' || marketplace === 'macy' || marketplace === 'reverb') && extId) {
                payload.item_id = extId;
            }
            if (marketplace === 'amazon' && extId) payload.asin = extId;
            if (marketplace === 'google' && extId) payload.product_id = extId;
            return $.ajax({
                url: url,
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                data: payload,
            });
        }

        function deleteLmpSelectedRows(items, opts) {
            opts = opts || {};
            const list = (items || []).filter(function(r) { return r && (r.id || r.extId); });
            if (!list.length) {
                showToast('Select at least one competitor to delete', 'warning');
                return;
            }
            const label = opts.confirmLabel || (list.length === 1
                ? 'Delete this competitor from LMP? This cannot be undone.'
                : 'Delete ' + list.length + ' selected competitors from LMP? This cannot be undone.');
            if (!confirm(label)) return;

            const $bulkBtn = $('#lmp-bulk-delete-btn');
            const bulkHtml = $bulkBtn.html();
            $bulkBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Deleting...');
            $('#lmpModal .delete-lmp-row-btn, #lmpModal .lmp-row-cb, #lmp-select-all-cb').prop('disabled', true);

            const sku = list[0].sku || $('#lmpSku').text();
            const refreshChannel = (lmpModalCache.filter && lmpModalCache.filter !== 'all')
                ? lmpModalCache.filter
                : (list[0].marketplace || 'amazon');

            const temuItems = list.filter(function(r) { return r.marketplace === 'temu'; });
            const aliexpressItems = list.filter(function(r) { return r.marketplace === 'aliexpress'; });
            const apiItems = list.filter(function(r) {
                return r.marketplace !== 'temu' && r.marketplace !== 'aliexpress';
            });
            let success = 0;
            let failed = 0;

            function finish() {
                $bulkBtn.prop('disabled', false).html(bulkHtml);
                if (failed === 0) {
                    showToast(success === 1
                        ? 'Competitor deleted successfully'
                        : success + ' competitors deleted successfully', 'success');
                } else {
                    showToast('Deleted ' + success + ' of ' + list.length + ' (' + failed + ' failed)', 'error');
                }
                if (success > 0) {
                    refreshLmpAfterMutation(sku, refreshChannel);
                } else {
                    $('#lmpModal .delete-lmp-row-btn, #lmpModal .lmp-row-cb, #lmp-select-all-cb').prop('disabled', false);
                    updateLmpBulkDeleteUi();
                }
            }

            function runApiDeletes(done) {
                let i = 0;
                function next() {
                    if (i >= apiItems.length) {
                        done();
                        return;
                    }
                    const item = apiItems[i++];
                    deleteLmpApiRow(item)
                        .done(function(response) {
                            if (response && response.success) success++;
                            else failed++;
                        })
                        .fail(function() { failed++; })
                        .always(next);
                }
                next();
            }

            function runTemuDeletes(done) {
                if (!temuItems.length) {
                    done();
                    return;
                }
                let removedCount = 0;
                // Match against API competitors[] (parallel to mapped entries) by temu-N id,
                // then rewrite remaining rows back into temu_lmp via /temu-lmp/save.
                withTemuLmpEntries(sku, function(entries, existing) {
                    const removeIds = {};
                    temuItems.forEach(function(item) {
                        const id = String(item.id || '').trim();
                        if (id) removeIds[id] = true;
                    });
                    const next = [];
                    const list = Array.isArray(existing) ? existing : [];
                    for (let i = 0; i < list.length; i++) {
                        const c = list[i] || {};
                        const id = String(c.id || ('temu-' + (i + 1)));
                        if (removeIds[id]) {
                            removedCount++;
                            continue;
                        }
                        // Prefer mapped entry (has delivery/source_sku); fall back to competitor fields
                        if (entries[i]) {
                            next.push(entries[i]);
                        } else {
                            let delivery = parseFloat(c.delivery != null ? c.delivery : c.shipping_cost);
                            if (isNaN(delivery) || delivery < 0) delivery = 0;
                            next.push({
                                price: c.price,
                                delivery: delivery,
                                link: c.link || c.product_link || null,
                                ignored: !!c.ignored,
                                source_sku: c.source_sku || sku,
                            });
                        }
                    }
                    // Fallback: if id match failed, try price+link match on remaining
                    if (removedCount === 0) {
                        temuItems.forEach(function(item) {
                            const idx = findTemuEntryIndex(next, item.id, item.price, item.link);
                            if (idx >= 0) {
                                next.splice(idx, 1);
                                removedCount++;
                            }
                        });
                    }
                    if (!removedCount) return false;
                    return next;
                }, function(ok) {
                    if (ok) {
                        success += removedCount;
                        failed += Math.max(0, temuItems.length - removedCount);
                        // Optimistic UI: drop deleted Temu rows from cache immediately
                        if (removedCount > 0 && lmpModalCache && Array.isArray(lmpModalCache.rows)) {
                            const dropIds = {};
                            temuItems.forEach(function(item) {
                                const id = String(item.id || '').trim();
                                if (id) dropIds[id] = true;
                            });
                            lmpModalCache.rows = lmpModalCache.rows.filter(function(r) {
                                if (r.channel !== 'temu') return true;
                                return !dropIds[String(r.id || '')];
                            });
                            if (typeof renderLmpMergedTable === 'function') {
                                renderLmpMergedTable();
                            }
                        }
                    } else {
                        failed += temuItems.length;
                    }
                    done();
                });
            }

            function runAliexpressDeletes(done) {
                if (!aliexpressItems.length) {
                    done();
                    return;
                }
                let removedCount = 0;
                withAliexpressLmpEntries(sku, function(entries, existing) {
                    const removeIds = {};
                    aliexpressItems.forEach(function(item) {
                        const id = String(item.id || '').trim();
                        if (id) removeIds[id] = true;
                    });
                    const next = [];
                    const list = Array.isArray(existing) ? existing : [];
                    for (let i = 0; i < list.length; i++) {
                        const c = list[i] || {};
                        const id = String(c.id || ('aliexpress-' + (i + 1)));
                        if (removeIds[id]) {
                            removedCount++;
                            continue;
                        }
                        if (entries[i]) {
                            next.push(entries[i]);
                        } else {
                            let ship = parseFloat(c.ship != null ? c.ship
                                : (c.shipping_cost != null ? c.shipping_cost : c.delivery));
                            if (isNaN(ship) || ship < 0) ship = 0;
                            next.push({
                                price: c.price,
                                ship: ship,
                                delivery: ship,
                                link: c.link || c.product_link || null,
                                ignored: !!c.ignored,
                                source_sku: c.source_sku || sku,
                            });
                        }
                    }
                    if (removedCount === 0) {
                        aliexpressItems.forEach(function(item) {
                            const idx = findAliexpressEntryIndex(next, item.id, item.price, item.link);
                            if (idx >= 0) {
                                next.splice(idx, 1);
                                removedCount++;
                            }
                        });
                    }
                    if (!removedCount) return false;
                    return next;
                }, function(ok) {
                    if (ok) {
                        success += removedCount;
                        failed += Math.max(0, aliexpressItems.length - removedCount);
                        if (removedCount > 0 && lmpModalCache && Array.isArray(lmpModalCache.rows)) {
                            const dropIds = {};
                            aliexpressItems.forEach(function(item) {
                                const id = String(item.id || '').trim();
                                if (id) dropIds[id] = true;
                            });
                            lmpModalCache.rows = lmpModalCache.rows.filter(function(r) {
                                if (r.channel !== 'aliexpress') return true;
                                return !dropIds[String(r.id || '')];
                            });
                            if (typeof renderLmpMergedTable === 'function') {
                                renderLmpMergedTable();
                            }
                        }
                    } else {
                        failed += aliexpressItems.length;
                    }
                    done();
                });
            }

            runTemuDeletes(function() {
                runAliexpressDeletes(function() {
                    runApiDeletes(finish);
                });
            });
        }

        $(document).on('change', '#lmp-select-all-cb', function() {
            const checked = $(this).is(':checked');
            $('#lmpModal .lmp-row-cb').prop('checked', checked);
            updateLmpBulkDeleteUi();
        });

        $(document).on('change', '#lmpModal .lmp-row-cb', function() {
            updateLmpBulkDeleteUi();
        });

        $(document).on('click', '#lmp-bulk-delete-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const selected = collectCheckedLmpRows();
            if (!selected.length) {
                showToast('Select at least one competitor to delete', 'warning');
                return;
            }
            deleteLmpSelectedRows(selected, {
                confirmLabel: selected.length === 1
                    ? 'Delete the selected competitor from LMP? This cannot be undone.'
                    : 'Delete ' + selected.length + ' selected competitors from LMP? This cannot be undone.'
            });
        });

        $(document).on('click', '#lmpModal .delete-lmp-row-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const btn = $(this);
            if (btn.prop('disabled')) return;
            const id = btn.attr('data-id') || btn.data('id');
            const marketplace = (btn.attr('data-marketplace') || btn.data('marketplace') || '').toLowerCase();
            const sku = btn.attr('data-sku') || btn.data('sku') || $('#lmpSku').text();
            const price = btn.attr('data-price') || btn.data('price');
            const extId = btn.attr('data-ext-id') || btn.data('ext-id') || '';
            const link = btn.attr('data-link') || btn.closest('tr').find('a.text-primary').attr('href') || '';
            const labelMap = { amazon: 'Amazon', ebay: 'eBay', google: 'Google', bestbuy: 'BestBuy', macy: 'Macy', reverb: 'Reverb', temu: 'Temu', aliexpress: 'AliExpress' };
            const label = labelMap[marketplace] || marketplace;

            const selected = collectCheckedLmpRows();
            if (selected.length > 1) {
                const hasClicked = selected.some(function(r) {
                    return String(r.id) === String(id) && r.marketplace === marketplace;
                });
                if (!hasClicked && (id || extId)) {
                    selected.push({ id: id, marketplace: marketplace, sku: sku, price: price, extId: extId, link: link });
                }
                deleteLmpSelectedRows(selected, {
                    confirmLabel: 'Delete ' + selected.length + ' selected competitors from LMP? This cannot be undone.'
                });
                return;
            }

            if (!id && !extId) {
                showToast('Cannot delete — missing competitor id', 'error');
                return;
            }

            deleteLmpSelectedRows([{
                id: id,
                marketplace: marketplace,
                sku: sku,
                price: price,
                extId: extId,
                link: link,
            }], {
                confirmLabel: 'Delete this ' + label + ' competitor ($'
                    + (price ? parseFloat(price).toFixed(2) : '')
                    + ') from LMP? This cannot be undone.'
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

            // Children first, parent blue row last (clear Dil% sort so order is preserved)
            let displayData = childRows.slice();
            if (parentRow) {
                parentRow._expanded = true;
                displayData.push(parentRow);
            }

            suppressDataLoadedHandler = true;
            table.clearSort();
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
            
            const parentRows = fullDataset
                .filter(row => row.is_parent_summary === true)
                .slice()
                .sort((a, b) => (parseFloat(b.dil_percent) || 0) - (parseFloat(a.dil_percent) || 0));
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
                // Default: Dil% high → low (keep child grouping when a parent is expanded)
                if (expandedParent === null) {
                    table.setSort([{ column: "dil_percent", dir: "desc" }]);
                }
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
            const skuParentFilter = $('#sku-parent-filter').val();

            // Parent Only rebuilds its own dataset from fullDataset
            if (skuParentFilter === 'parent') {
                table.clearFilter();
                expandedParent = null;
                buildParentView();
                return;
            }

            const doFilters = function() {
                const inventoryFilter = $('#inventory-filter').val();
                const dilFilter = $('.column-filter[data-column="dil_percent"].active')?.data('color') || 'all';

                table.clearFilter();

                // SKU Only — hide parent summary rows
                if (skuParentFilter === 'sku') {
                    table.addFilter(function(data) {
                        return data.is_parent_summary !== true;
                    });
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

            // Parent Only / dot-expand replace table data — must restore fullDataset for SKU Only / Both
            const currentLen = (typeof table.getDataCount === 'function')
                ? table.getDataCount('all')
                : ((table.getData('all') || []).length);
            const needsFullRestore = fullDataset.length > 0 && (
                wasInDotView || currentLen !== fullDataset.length
            );

            if (needsFullRestore) {
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
            $('#sku-parent-filter').val('parent');
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
            'shein_l30', 'faire_l30', 'ae_l30', 'pp_l30'
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

        function hidePriceIncreaseDefaultColumns() {
            ['image_path', 'parent_display'].forEach(field => {
                const col = table.getColumn(field);
                if (col) col.hide();
            });
            // Always show Label (Shipping Master) column after Dil %
            const labelCol = table.getColumn('label');
            if (labelCol) labelCol.show();
            // Always show Audit (same as /amz-cvr-issues)
            const auditCol = table.getColumn('audit');
            if (auditCol) auditCol.show();
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
                            // Price Increase: keep Image / Parent text columns hidden by default
                            if (field === 'image_path' || field === 'parent_display' || field === 'parent') return;
                            const col = table.getColumn(field);
                            if (col) {
                                visibility[field] ? col.show() : col.hide();
                            }
                        });
                    }
                    forceHideRemovedCvrColumns();
                    hidePriceIncreaseDefaultColumns();
                    buildColumnDropdown();
                },
                error: function() {
                    forceHideRemovedCvrColumns();
                    hidePriceIncreaseDefaultColumns();
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
        let currentPricingChartSource = 'cvr'; // 'cvr' | 'temu_views' | 'channel_price'
        let currentPricingChartMarketplace = '';
        let currentPricingChartCurrentPrice = null;
        let currentPricingChartCurrentValue = null;
        const pricingChartMetricLabels = { inv: 'Inv', ov_l30: 'OV L30', price: 'Price', channel_price: 'Price', cvr: 'CVR', dil: 'DIL', amz_price: 'Amz Price', rating: 'Rating', total_views: 'Total Views', temu_views: 'Temu Views' };
        const pricingChartRangeLabel = (days) => 'L' + days;

        /**
         * Open chart modal safely. When OV L30 is already open, stack chart above it
         * without trapping the OV L30 modal under a raised backdrop.
         */
        function showPricingMasterChartModal() {
            const chartEl = document.getElementById('pricingMasterChartModal');
            if (!chartEl) return;
            const ovl30Open = $('#ovl30DetailsModal').hasClass('show');
            chartEl.classList.toggle('pi-chart-over-ovl30', ovl30Open);
            const existing = bootstrap.Modal.getInstance(chartEl);
            const modal = existing || new bootstrap.Modal(chartEl);
            modal.show();
            if (ovl30Open) {
                // Raise only the newest backdrop (chart's), leave OV L30 backdrop alone
                setTimeout(function() {
                    const backs = document.querySelectorAll('.modal-backdrop');
                    if (backs.length) {
                        backs[backs.length - 1].style.zIndex = '1080';
                        backs[backs.length - 1].classList.add('pi-chart-backdrop');
                    }
                }, 10);
            }
            loadPricingMasterChart();
        }

        $(document).on('hidden.bs.modal', '#pricingMasterChartModal', function() {
            this.classList.remove('pi-chart-over-ovl30');
            document.querySelectorAll('.modal-backdrop.pi-chart-backdrop').forEach(function(el) {
                el.classList.remove('pi-chart-backdrop');
                el.style.zIndex = '';
            });
            setTimeout(cleanupOrphanModalBackdrops, 50);
        });

        $(document).on('hidden.bs.modal', '#lmpModal, #spriceDetailsModal, #missingListingsModal, #remarkHistoryModal, #spriceSuggestModal', function() {
            setTimeout(cleanupOrphanModalBackdrops, 50);
        });

        $(document).on('click', '.summary-chart-badge', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const metric = $(e.currentTarget).attr('data-metric') || $(e.currentTarget).data('metric');
            if (!metric) return;
            currentPricingChartSource = 'cvr';
            currentPricingChartMetric = metric;
            currentPricingChartSku = '';
            currentPricingChartParent = '';
            currentPricingChartAggregate = true;
            currentPricingChartDays = 30;
            $('#pricingMasterChartRangeSelect').val('30');
            const label = pricingChartMetricLabels[metric] || metric;
            $('#pricingMasterChartModalTitle').text('Price Increase - All (Summary) - ' + label + ' (Rolling ' + pricingChartRangeLabel(30) + ')');
            $('#pricingMasterChartContainer').hide();
            $('#pricingMasterChartNoData').hide();
            $('#pricingMasterChartLoading').show();
            showPricingMasterChartModal();
        });

        $(document).on('click', '.pricing-master-chart-link', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const $el = $(e.currentTarget);
            const metric = $el.attr('data-metric') || $el.data('metric');
            const sku = String($el.attr('data-sku') || $el.data('sku') || '').trim();
            const parent = String($el.attr('data-parent') || $el.data('parent') || '').trim();
            const marketplace = String($el.attr('data-marketplace') || $el.data('marketplace') || '').trim();
            const currentPriceRaw = $el.attr('data-current-price') || $el.data('current-price');
            if (!metric) return;

            // OV L30 per-channel Price dot → channel-specific history
            if (metric === 'channel_price' || (marketplace && (metric === 'price' || metric === 'amz_price'))) {
                if (!sku) { showToast('SKU not found for chart', 'error'); return; }
                if (!marketplace) { showToast('Marketplace not found for price chart', 'error'); return; }
                currentPricingChartSource = 'channel_price';
                currentPricingChartMetric = 'channel_price';
                currentPricingChartSku = sku;
                currentPricingChartParent = '';
                currentPricingChartAggregate = false;
                currentPricingChartMarketplace = marketplace;
                const cp = parseFloat(currentPriceRaw);
                currentPricingChartCurrentPrice = (isFinite(cp) && cp > 0) ? cp : null;
                currentPricingChartDays = 30;
                $('#pricingMasterChartRangeSelect').val('30');
                $('#pricingMasterChartModalTitle').text(
                    'Price Increase - ' + sku + ' - ' + marketplace + ' Price (Rolling ' + pricingChartRangeLabel(30) + ')'
                );
                $('#pricingMasterChartContainer').hide();
                $('#pricingMasterChartNoData').hide();
                $('#pricingMasterChartLoading').show();
                showPricingMasterChartModal();
                return;
            }

            currentPricingChartSource = 'cvr';
            currentPricingChartMarketplace = '';
            currentPricingChartCurrentPrice = null;
            currentPricingChartCurrentValue = null;
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
            // Modal INV / L30 / Dil badges — overlay today's badge value on the chart
            if (metric === 'inv' || metric === 'ov_l30' || metric === 'dil') {
                let raw = $el.attr('data-current-value');
                if (raw == null || raw === '') {
                    const $strong = $el.find('strong').first();
                    raw = $strong.length ? $strong.text() : '';
                }
                const n = parseFloat(String(raw).replace(/[^0-9.\-]/g, ''));
                currentPricingChartCurrentValue = isFinite(n) ? n : null;
            }
            currentPricingChartDays = 30;
            $('#pricingMasterChartRangeSelect').val('30');
            const label = pricingChartMetricLabels[metric] || metric;
            $('#pricingMasterChartModalTitle').text('Price Increase - ' + displayName + (isParentChart ? ' (Parent)' : '') + ' - ' + label + ' (Rolling ' + pricingChartRangeLabel(30) + ')');
            $('#pricingMasterChartContainer').hide();
            $('#pricingMasterChartNoData').hide();
            $('#pricingMasterChartLoading').show();
            showPricingMasterChartModal();
        });

        // Temu Views chart — same /temu-metrics-history source as /temu-decrease Views dot
        $(document).on('click', '.temu-views-chart-link', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const sku = String($(e.currentTarget).attr('data-sku') || $(e.currentTarget).data('sku') || '').trim();
            if (!sku) {
                showToast('SKU not found for Temu Views chart', 'error');
                return;
            }
            currentPricingChartSource = 'temu_views';
            currentPricingChartMetric = 'temu_views';
            currentPricingChartSku = sku;
            currentPricingChartParent = '';
            currentPricingChartAggregate = false;
            currentPricingChartDays = 30;
            $('#pricingMasterChartRangeSelect').val('30');
            $('#pricingMasterChartModalTitle').text('Price Increase - ' + sku + ' - Temu Views (Rolling L30)');
            $('#pricingMasterChartContainer').hide();
            $('#pricingMasterChartNoData').hide();
            $('#pricingMasterChartLoading').show();
            showPricingMasterChartModal();
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

            // Temu Views history from /temu-decrease API
            if (currentPricingChartSource === 'temu_views') {
                $.ajax({
                    url: '/temu-metrics-history',
                    method: 'GET',
                    data: { sku: currentPricingChartSku, days: currentPricingChartDays },
                    success: function(data) {
                        $('#pricingMasterChartLoading').hide();
                        const rows = Array.isArray(data) ? data : [];
                        if (rows.length > 0) {
                            const mapped = rows.map(function(d) {
                                return {
                                    date: d.date_formatted || d.date || '',
                                    value: parseFloat(d.views) || 0
                                };
                            });
                            $('#pricingMasterChartContainer').show();
                            renderPricingMasterChart(mapped);
                        } else {
                            $('#pricingMasterChartNoData').show();
                        }
                    },
                    error: function() {
                        $('#pricingMasterChartLoading').hide();
                        $('#pricingMasterChartNoData').show();
                    }
                });
                return;
            }

            // Per-channel Price history (OV L30 modal Price column dots)
            if (currentPricingChartSource === 'channel_price') {
                const payload = {
                    sku: currentPricingChartSku,
                    marketplace: currentPricingChartMarketplace,
                    days: currentPricingChartDays
                };
                if (currentPricingChartCurrentPrice != null) {
                    payload.current_price = currentPricingChartCurrentPrice;
                }
                $.ajax({
                    url: '/cvr-master-channel-price-chart',
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
                return;
            }

            const payload = { metric: currentPricingChartMetric, days: currentPricingChartDays };
            if (currentPricingChartAggregate) {
                payload.aggregate = 1;
            } else if (currentPricingChartParent) {
                payload.parent = currentPricingChartParent;
            } else {
                payload.sku = currentPricingChartSku;
            }
            if (currentPricingChartCurrentValue != null
                && (currentPricingChartMetric === 'inv'
                    || currentPricingChartMetric === 'ov_l30'
                    || currentPricingChartMetric === 'dil')) {
                payload.current_value = currentPricingChartCurrentValue;
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
            // Preserve nulls (days before first snapshot) — do not coerce to 0
            const values = data.map(function(d) {
                if (d.value === null || d.value === undefined || d.value === '') return null;
                const n = parseFloat(d.value);
                return isFinite(n) ? n : null;
            });
            const isPriceChart = currentPricingChartSource === 'channel_price'
                || currentPricingChartMetric === 'price'
                || currentPricingChartMetric === 'amz_price'
                || currentPricingChartMetric === 'channel_price';
            const numericValues = values.filter(function(v) {
                if (v === null || !isFinite(v)) return false;
                // Price charts: ignore placeholder 0s so MEDIAN/LOWEST stay real prices
                if (isPriceChart && v <= 0) return false;
                return true;
            });
            if (!numericValues.length) {
                $('#pricingMasterChartContainer').hide();
                $('#pricingMasterChartNoData').show();
                return;
            }
            const dataMin = Math.min.apply(null, numericValues);
            const dataMax = Math.max.apply(null, numericValues);
            const sorted = numericValues.slice().sort(function(a, b) { return a - b; });
            const mid = Math.floor(sorted.length / 2);
            const median = sorted.length % 2 !== 0 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
            const range = dataMax - dataMin || 1;
            const yMin = Math.max(0, dataMin - range * 0.1);
            const yMax = dataMax + range * 0.1;
            const fmtVal = (v) => {
                if (v === null || v === undefined || !isFinite(Number(v))) return '';
                // Prices: always show 2 decimals (never Math.round → "6")
                if (isPriceChart) {
                    return '$' + Number(v).toFixed(2);
                }
                if (currentPricingChartMetric === 'cvr' || currentPricingChartMetric === 'dil') return Number(v).toFixed(1) + '%';
                if (currentPricingChartMetric === 'rating') return Number(v).toFixed(1);
                if (currentPricingChartMetric === 'total_views' || currentPricingChartMetric === 'temu_views') {
                    return Math.round(v).toLocaleString('en-US');
                }
                return Math.round(v).toLocaleString('en-US');
            };
            $('#pricingMasterChartHighest').text(fmtVal(dataMax)).css('color', '#dc3545');
            $('#pricingMasterChartMedian').text(fmtVal(median)).css('color', '#6c757d');
            $('#pricingMasterChartLowest').text(fmtVal(dataMin)).css('color', '#198754');
            const dotColors = values.map(function(v, i) {
                if (v === null) return '#adb5bd';
                let prev = null;
                for (let j = i - 1; j >= 0; j--) {
                    if (values[j] !== null) { prev = values[j]; break; }
                }
                if (prev === null) return '#6c757d';
                return v > prev ? '#28a745' : v < prev ? '#dc3545' : '#6c757d';
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
                    const n = meta.data.length;
                    // For dense daily series, label every Nth point (plus first/last / value changes)
                    const step = n > 20 ? Math.ceil(n / 12) : 1;
                    ctx.save();
                    ctx.font = 'bold 7px Inter, system-ui, sans-serif';
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'bottom';
                    meta.data.forEach((point, i) => {
                        const val = dataset.data[i];
                        if (val === null || val === undefined || !isFinite(Number(val))) return;
                        let prev = null;
                        for (let j = i - 1; j >= 0; j--) {
                            const p = dataset.data[j];
                            if (p !== null && p !== undefined && isFinite(Number(p))) { prev = Number(p); break; }
                        }
                        const changed = prev !== null && Number(val) !== prev;
                        const show = i === 0 || i === n - 1 || changed || (step === 1) || (i % step === 0);
                        if (!show) return;
                        const x = point.x;
                        const y = point.y;
                        const offsetY = (i % 2 === 0) ? -7 : -14;
                        ctx.fillStyle = Number(val) === 0 ? '#198754' : Number(val) > 0 ? '#dc3545' : '#6c757d';
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
                        spanGaps: true,
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
                        x: {
                            ticks: {
                                font: { size: 9 },
                                maxRotation: 45,
                                autoSkip: true,
                                maxTicksLimit: 16
                            }
                        }
                    }
                }
            });
        }

        // ==================== Audit (same as /amz-cvr-issues) ====================
        window.AmzCvrAuditBridge = {
            getTable: function() { return table; },
            rowSku: function(row) { return String((row && row.sku) || '').trim(); },
            rowParent: function(row) { return String((row && row.parent) || '').trim(); },
            rowInv: function(row) { return parseFloat(row && row.inventory) || 0; },
            rowViews: function(row) { return parseFloat(row && row.total_views) || 0; },
            rowCvr: function(row) { return parseFloat(row && row.avg_cvr) || 0; },
            rowPrice: function(row) { return parseFloat(row && row.amazon_price) || 0; },
            getSelectedSkus: function() { return []; }
        };
        @php
            $piAuditAssigneeEmails = [
                'pricing' => 'pricing1@5core.com',
                'compliance' => 'mgr-content@5core.com',
                'missing_listing' => 'ecomm6@5core.com',
                'advertisement' => 'mgr-advertisement@5core.com',
            ];
            $piAuditAssigneeUsers = \App\Models\User::query()
                ->where(function ($q) use ($piAuditAssigneeEmails) {
                    foreach ($piAuditAssigneeEmails as $email) {
                        $q->orWhereRaw('LOWER(email) = ?', [strtolower($email)]);
                    }
                })
                ->get(['id', 'name', 'email']);
            $piAuditAssigneeMap = [];
            foreach ($piAuditAssigneeEmails as $key => $email) {
                $user = $piAuditAssigneeUsers->first(
                    fn ($u) => strtolower((string) $u->email) === strtolower($email)
                );
                $piAuditAssigneeMap[$key] = [
                    'label' => match ($key) {
                        'pricing' => 'Pricing Issue',
                        'compliance' => 'Compliance Issue',
                        'missing_listing' => 'Missing listing Issue',
                        'advertisement' => 'Advertisement Issue',
                        default => ucfirst(str_replace('_', ' ', $key)) . ' Issue',
                    },
                    'email' => $email,
                    'user_id' => $user ? (int) $user->id : null,
                    'name' => $user ? (string) $user->name : null,
                    'custom' => false,
                ];
            }
        @endphp
        if (window.AmzCvrAudit && typeof window.AmzCvrAudit.init === 'function') {
            window.AmzCvrAudit.init({
                taskStoreUrl: @json(route('tasks.store')),
                historyStoreUrl: @json(route('amz.cvr.issues.history.store')),
                builtInAssignees: @json($piAuditAssigneeMap)
            });
        }
    });
</script>
@endsection
