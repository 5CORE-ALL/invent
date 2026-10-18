@extends('layouts.vertical', ['title' => 'Active Channel', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">

    <style>
        html, body {
            height: auto !important;
            max-height: none !important;
            max-width: 100%;
            overflow-x: clip;
            overflow-y: auto !important;
        }
        .wrapper {
            height: auto !important;
            max-height: none !important;
            max-width: 100%;
            overflow-x: clip;
            overflow-y: visible !important;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fa !important;
        }

        /* Channel logo thumbnail (Img column) */
        .channel-logo-thumb {
            width: 28px;
            height: 28px;
            object-fit: contain;
            border-radius: 4px;
            background: #fff;
            border: 1px solid #e9ecef;
            padding: 1px;
            display: inline-block;
        }

        .channel-logo-link {
            display: inline-block;
            line-height: 0;
            text-decoration: none;
            cursor: pointer;
            transition: transform 0.15s ease;
        }

        .channel-logo-link:hover .channel-logo-thumb {
            border-color: #0d6efd;
            box-shadow: 0 0 0 2px rgba(13, 110, 253, 0.15);
        }

        .channel-logo-placeholder {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 4px;
            background: #f1f3f5;
            border: 1px dashed #ced4da;
            color: #adb5bd;
            font-size: 12px;
        }

        /* Logo preview inside Add / Edit channel modals */
        .channel-logo-preview {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 64px;
            height: 64px;
            border-radius: 6px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            overflow: hidden;
        }

        .channel-logo-preview img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .channel-logo-preview .placeholder-text {
            color: #adb5bd;
            font-size: 11px;
            text-align: center;
            line-height: 1.1;
            padding: 4px;
        }

        .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }

        /* Condensed sidenav otherwise forces content-page min-height ~1800px.
           Keep page scrollable so every channel row is visible. */
        .content-page {
            min-height: calc(100vh - var(--tz-topbar-height, 70px)) !important;
            height: auto !important;
            max-height: none !important;
            max-width: 100%;
            overflow: visible !important;
        }
        .content-page .content,
        .content-page .container-fluid {
            min-height: 0 !important;
            max-width: 100%;
            min-width: 0;
            overflow: visible;
        }
        .amm-shell {
            max-width: 100%;
            min-width: 0;
        }
        .amm-shell .page-title-box {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.25rem 1rem;
            min-width: 0;
        }
        .amm-shell .page-title-box .page-title-right {
            float: none;
            margin-top: 0;
            min-width: 0;
        }
        .amm-shell .page-title-box .page-title {
            line-height: 1.3;
            padding: 0.7rem 0;
            white-space: normal;
        }
        .amm-shell .page-title-box .breadcrumb-item:has(> a:empty) {
            display: none;
        }
        .amm-page-row,
        .amm-page-col,
        .amm-page-card {
            min-width: 0;
            max-width: 100%;
            overflow: visible;
            transform: none;
        }
        .amm-toolbar {
            overflow-x: hidden;
        }
        .amm-table-card-body {
            padding: 0 !important;
            overflow: visible;
            min-width: 0;
            max-width: 100%;
        }
        .amm-table-hscroll {
            position: sticky;
            top: 0;
            z-index: 26;
            overflow-x: auto;
            overflow-y: hidden;
            max-width: 100%;
            height: 14px;
            background: #dbeafe;
            border-bottom: 1px solid #bfdbfe;
            scrollbar-width: thin;
        }
        .amm-table-hscroll-inner {
            height: 1px;
        }
        #marketplace-table-wrapper {
            width: 100%;
            max-width: 100%;
            min-width: 0;
            overflow: visible;
        }
        #marketplace-table,
        #marketplace-table.tabulator {
            height: auto !important;
            min-height: 0 !important;
            width: 100% !important;
            max-width: 100%;
            overflow: visible !important;
            /* Tabulator's translateZ(0) traps position:fixed inside the table,
               so the pinned header slides off and the #888 table fill shows through. */
            transform: none !important;
            background-color: #fff !important;
        }
        #marketplace-table.tabulator .tabulator-header,
        #marketplace-table.tabulator .tabulator-footer {
            width: 100% !important;
            max-width: 100%;
            overflow: hidden !important;
        }
        #marketplace-table.tabulator .tabulator-header {
            --amm-header-top: 0px;
            position: sticky !important;
            top: var(--amm-header-top) !important;
            z-index: 24 !important;
            background: #dbeafe !important;
            background-color: #dbeafe !important;
            box-shadow: 0 2px 6px rgba(15, 23, 42, 0.08);
        }
        #amm-header-freeze-spacer,
        #amm-hscroll-freeze-spacer {
            display: none;
            width: 100%;
            pointer-events: none;
        }
        #amm-header-freeze-spacer.is-on,
        #amm-hscroll-freeze-spacer.is-on {
            display: block;
        }
        #marketplace-table.tabulator .tabulator-header.amm-header-frozen,
        #amm-table-hscroll.amm-hscroll-frozen {
            position: fixed !important;
            margin: 0 !important;
        }
        #amm-table-hscroll.amm-hscroll-frozen {
            z-index: 31 !important;
        }
        #marketplace-table.tabulator .tabulator-header.amm-header-frozen {
            z-index: 30 !important;
        }
        #marketplace-table.tabulator .tabulator-header .tabulator-header-contents,
        #marketplace-table.tabulator .tabulator-header .tabulator-headers,
        #marketplace-table.tabulator .tabulator-header .tabulator-col,
        #marketplace-table.tabulator .tabulator-header .tabulator-col .tabulator-col-content,
        #marketplace-table.tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title-holder,
        #marketplace-table.tabulator .tabulator-header .tabulator-col.tabulator-sortable:hover,
        #marketplace-table.tabulator .tabulator-header .tabulator-col.tabulator-sortable.tabulator-col-sorter-element:hover {
            background: #dbeafe !important;
            background-color: #dbeafe !important;
        }
        #marketplace-table.tabulator .tabulator-header .tabulator-header-contents,
        #marketplace-table.tabulator .tabulator-footer .tabulator-calcs-holder {
            overflow: hidden !important;
        }
        #marketplace-table.tabulator .tabulator-tableholder {
            overflow-x: auto !important;
            overflow-y: hidden !important;
            height: auto !important;
            max-height: none !important;
            width: 100% !important;
            scrollbar-width: none;
            -webkit-overflow-scrolling: touch;
        }
        #marketplace-table.tabulator .tabulator-tableholder::-webkit-scrollbar {
            display: none;
        }
        #marketplace-table.tabulator .tabulator-cell {
            white-space: nowrap;
            overflow: visible;
            text-overflow: clip;
            font-size: 11px;
            padding: 3px 4px !important;
        }
        #marketplace-table.tabulator .tabulator-header .tabulator-col .tabulator-col-content {
            padding: 4px 2px !important;
        }
        #marketplace-table.tabulator .tabulator-header .tabulator-col.tabulator-frozen {
            background-color: #dbeafe !important;
            z-index: 8 !important;
        }
        #marketplace-table.tabulator .tabulator-row.tabulator-row-even .tabulator-cell.tabulator-frozen {
            background-color: #fff;
        }
        #marketplace-table.tabulator .tabulator-row.tabulator-row-odd .tabulator-cell.tabulator-frozen {
            background-color: #f4f8fc;
        }
        #marketplace-table.tabulator .tabulator-row.tabulator-calcs .tabulator-cell.tabulator-frozen {
            background-color: #f8f9fa;
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
            color: black !important;
            overflow: visible;
            text-overflow: clip;
        }

        .tabulator .tabulator-header .tabulator-col {
            height: 80px !important;
            overflow: hidden;
        }

        .tabulator .tabulator-header .tabulator-col.tabulator-sortable .tabulator-col-title {
            padding-right: 0px !important;
        }

        /* Bottom calc row styling - ensure visibility */
        .tabulator-row.tabulator-calcs {
            background: #f8f9fa !important;
            font-weight: bold !important;
            border-top: 2px solid #4361ee !important;
        }

        .tabulator-row.tabulator-calcs .tabulator-cell {
            background: #f8f9fa !important;
            font-weight: bold !important;
            color: #333 !important;
        }

        .tabulator-row.tabulator-calcs-bottom {
            display: table-row !important;
            visibility: visible !important;
        }

        /* Ensure bottom calc cells are visible */
        .tabulator .tabulator-footer .tabulator-calcs-holder .tabulator-row {
            display: table-row !important;
        }

        .tabulator .tabulator-footer .tabulator-calcs-holder {
            display: block !important;
        }

        /* Type labels — text + color only (no pill) */
        .type-badge {
            padding: 0;
            border-radius: 0;
            font-size: 11px;
            font-weight: 700;
            display: inline;
            background: none !important;
            line-height: 1.2;
        }

        .type-b2c {
            color: #11998e;
        }

        .type-b2b {
            color: #4568dc;
        }

        .type-dropship {
            color: #f5576c;
        }

        .type-wholesale {
            color: #e67e22;
        }

        /* Toast container */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }

        /* Modal z-index fix */
        .modal {
            z-index: 9999 !important;
        }

        .modal-backdrop {
            z-index: 9998 !important;
        }

        /* Ensure modals are visible */
        .modal.show {
            display: block !important;
        }

        /* Dropdown menu styling */
        .dropdown-menu {
            max-height: 400px;
            overflow-y: auto;
        }

        .dropdown-item label {
            cursor: pointer;
            margin: 0;
        }

        .dropdown-item input[type="checkbox"] {
            margin-right: 8px;
        }

        /* Metric history modal — full width (theme uses --tz-modal-width / --tz-modal-margin, not --bs-modal-*) */
        #adBreakdownChartModal.modal {
            --tz-modal-width: 100%;
            --tz-modal-margin: 0.5rem 0;
            padding-left: 0 !important;
            padding-right: 0 !important;
        }
        #adBreakdownChartModal .modal-dialog {
            width: 100% !important;
            max-width: none !important;
            margin: 0.5rem 0 0 0 !important;
        }
        #adBreakdownChartModal .modal-content {
            border-radius: 0;
            width: 100%;
            max-width: 100%;
        }

        #yesterdayMpModal .modal-dialog,
        #projectedMpModal .modal-dialog {
            max-width: 1100px;
        }
        #yesterdayMpTable th,
        #projectedMpTable th {
            font-size: 11px;
            white-space: nowrap;
            text-align: center;
            background: #e6e6e6;
        }
        #yesterdayMpTable td,
        #projectedMpTable td {
            font-size: 12px;
            vertical-align: middle;
        }
        #yesterdayMpTable tfoot td,
        #projectedMpTable tfoot td {
            font-weight: 700;
            background: #f8f9fa;
            border-top: 2px solid #4361ee;
        }
        #yesterdayMpTable .ymp-cell,
        #projectedMpTable .ymp-cell {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            white-space: nowrap;
        }
        #yesterdayMpTable td.text-end .ymp-cell,
        #projectedMpTable td.text-end .ymp-cell {
            justify-content: flex-end;
            width: 100%;
        }
        #yesterdayMpTable .ymp-chart-dot,
        #projectedMpTable .ymp-chart-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            flex: 0 0 auto;
            cursor: pointer;
            box-shadow: 0 0 0 1px rgba(0,0,0,0.12);
            vertical-align: 0.05em;
        }
        #yesterdayMpTable .ymp-chart-dot:hover,
        #projectedMpTable .ymp-chart-dot:hover {
            transform: scale(1.35);
            box-shadow: 0 0 0 2px rgba(15, 23, 42, 0.18);
        }
        #yesterdayMpTable .ymp-hover-cell,
        #projectedMpTable .ymp-hover-cell {
            cursor: pointer;
        }

        /* Summary badges — metric row, then INV with Forecast before Others */
        #summary-stats {
            max-width: 100%;
            min-width: 0;
            overflow: visible;
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) minmax(0, 2fr) minmax(0, 1fr);
            gap: 0.5rem;
            align-items: start;
            background: transparent !important;
            padding: 0 !important;
        }
        #summary-stats .amm-badge-block {
            min-width: 0;
            border-radius: 12px;
            padding: 0.55rem 0.6rem 0.65rem;
            border: 1px solid transparent;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
        }
        #summary-stats .amm-badge-block-title {
            font-size: 0.68rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            line-height: 1;
            margin: 0 0 0.35rem;
        }
        #summary-stats .amm-badge-block--l30 {
            grid-column: 3;
            grid-row: 1;
            background: #fef9c3;
            border-color: #facc15;
        }
        #summary-stats .amm-badge-block--l30 .amm-badge-block-title { color: #854d0e; }
        #summary-stats .amm-badge-block--today {
            grid-column: 1;
            grid-row: 1;
            background: #fef9c3;
            border-color: #facc15;
        }
        #summary-stats .amm-badge-block--today .amm-badge-block-title { color: #854d0e; }
        #summary-stats .amm-badge-block--yesterday {
            grid-column: 2;
            grid-row: 1;
            background: #fef9c3;
            border-color: #facc15;
        }
        #summary-stats .amm-badge-block--yesterday .amm-badge-block-title {
            color: #854d0e;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.35rem;
        }
        #summary-stats .amm-yesterday-view-btn {
            padding: 0.05rem 0.4rem;
            line-height: 1.15;
            font-size: 0.75rem;
            border-color: #a16207;
            background: #fff;
        }
        #summary-stats .amm-badge-block--projected {
            grid-column: 4;
            grid-row: 1;
            background: #fef9c3;
            border-color: #facc15;
        }
        #summary-stats .amm-badge-block--projected .amm-badge-block-title {
            color: #854d0e;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.35rem;
        }
        #summary-stats .amm-projected-view-btn {
            padding: 0.05rem 0.4rem;
            line-height: 1.15;
            font-size: 0.75rem;
            border-color: #a16207;
            background: #fff;
        }
        #summary-stats .amm-badge-block--forecast {
            grid-column: 3;
            grid-row: 2;
            background: #fef9c3;
            border-color: #facc15;
        }
        #summary-stats .amm-badge-block--forecast .amm-badge-block-title { color: #854d0e; }
        #summary-stats .amm-badge-block--others {
            grid-column: 4;
            grid-row: 2;
            background: #fef9c3;
            border-color: #facc15;
        }
        #summary-stats .amm-badge-block--others .amm-badge-block-title { color: #854d0e; }
        #summary-stats .amm-badge-block--inv {
            grid-column: 1 / 3;
            grid-row: 2;
            background: #fef9c3;
            border-color: #facc15;
        }
        #summary-stats .amm-badge-block--inv .amm-badge-block-title { color: #854d0e; }
        #summary-stats .amm-badge-block--inv .ebay2-summary-badge-row {
            margin-bottom: 0.45rem;
        }
        #summary-stats .amm-inv-block-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 0.5rem 1rem;
        }
        #summary-stats .amm-inv-block-pair {
            display: flex;
            align-items: stretch;
            gap: 0.5rem;
            min-width: 0;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 0.4rem 0.5rem;
        }
        #summary-stats .amm-inv-block-canvas {
            width: 200px;
            min-width: 200px;
            max-width: 200px;
            height: 112px;
            flex: 0 0 200px;
            overflow: hidden;
            background: #fff;
            border-radius: 8px;
        }
        #summary-stats .amm-inv-block-canvas canvas {
            width: 200px !important;
            height: 112px !important;
            max-width: 200px !important;
            display: block;
        }
        #summary-stats .amm-inv-block-copy {
            flex: 1 1 auto;
            min-width: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        #summary-stats .amm-inv-block-subtitle {
            font-size: 0.72rem;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 0.3rem;
        }
        #summary-stats .amm-inv-band-row {
            display: flex;
            flex-wrap: nowrap;
            gap: 0.4rem;
            min-width: 0;
        }
        #summary-stats .amm-inv-band {
            flex: 1 1 0;
            min-width: 0;
            border-radius: 8px;
            padding: 0.35rem 0.45rem;
            display: flex;
            flex-direction: column;
            gap: 1px;
            line-height: 1.25;
        }
        #summary-stats .amm-inv-band--red { background: #fee2e2; color: #7f1d1d; }
        #summary-stats .amm-inv-band--green { background: #dcfce7; color: #14532d; }
        #summary-stats .amm-inv-band--pink { background: #fce7f3; color: #9d174d; }
        #summary-stats .amm-inv-band-name { font-size: 11px; font-weight: 800; white-space: nowrap; }
        #summary-stats .amm-inv-band-meta { font-size: 12px; font-weight: 700; white-space: nowrap; }
        #summary-stats .amm-badge-block--today .ebay2-summary-badge-row,
        #summary-stats .amm-badge-block--yesterday .ebay2-summary-badge-row,
        #summary-stats .amm-badge-block--projected .ebay2-summary-badge-row,
        #summary-stats .amm-badge-block--others .ebay2-summary-badge-row {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            align-items: stretch;
        }
        #summary-stats .amm-badge-block--l30 .ebay2-summary-badge-row,
        #summary-stats .amm-badge-block--forecast .ebay2-summary-badge-row,
        #summary-stats .amm-badge-block--inv .ebay2-summary-badge-row {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            align-items: stretch;
        }
        #summary-stats .ebay2-summary-badge-row {
            display: flex;
            flex-wrap: wrap;
            align-items: stretch;
            align-content: flex-start;
            gap: 0.4rem;
            width: 100%;
            max-width: 100%;
            overflow: visible;
        }
        #summary-stats .ebay2-summary-badge-row > .badge {
            width: 100%;
            min-width: 0;
            max-width: none;
            min-height: 42px;
            height: 100%;
            font-size: 0.9rem !important;
            line-height: 1.2;
            padding: 0.4rem 0.5rem !important;
            border-radius: 0.375rem;
            font-weight: bold;
            box-sizing: border-box;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            white-space: nowrap;
            text-decoration: none;
        }
        #summary-stats .summary-trend-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 0.22rem;
            margin-left: 0;
            flex-shrink: 0;
            cursor: pointer;
            vertical-align: 0.08em;
            box-shadow: 0 0 0 1px rgba(255,255,255,0.85);
            position: relative;
            z-index: 2;
        }
        #summary-stats .summary-trend-dot:hover {
            transform: scale(1.25);
            box-shadow: 0 0 0 2px rgba(15, 23, 42, 0.25);
        }
        #summary-stats .ebay2-summary-badge-row > .badge.amm-dot-down,
        #summary-stats .ebay2-summary-badge-row > .badge.amm-dot-down *:not(.summary-trend-dot) {
            color: #fff !important;
            -webkit-text-fill-color: #fff !important;
        }
        #summary-stats .summary-trend-dot.up { background: #22c55e; }
        #summary-stats .summary-trend-dot.down { background: #ef4444; }
        #summary-stats .summary-trend-dot.flat { background: #eab308; }
        #summary-stats .summary-trend-dot.none { background: #9ca3af; }
        #marketplace-table .amm-task-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1.7rem;
            min-width: 1.7rem;
            height: 1.4rem;
            padding: 0;
            margin: 0;
            border: none;
            border-radius: 7px;
            background: linear-gradient(135deg, #0d9488, #14b8a6);
            color: #fff;
            font-size: 0.62rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            line-height: 1;
            cursor: pointer;
        }
        #marketplace-table .amm-task-btn:hover {
            background: linear-gradient(135deg, #0f766e, #0d9488);
            color: #fff;
        }
        #column-dropdown-list {
            max-height: none;
            overflow: visible;
            display: flex;
            flex-direction: row;
            align-items: flex-start;
            gap: 0;
        }
        #column-dropdown-list .amm-col-group {
            padding: 0.35rem 0.55rem 0.5rem;
            border-bottom: 0;
            border-right: 1px solid #e5e7eb;
            min-width: 132px;
        }
        #column-dropdown-list .amm-col-group:last-child { border-right: 0; border-bottom: 0; }
        #column-dropdown-list .amm-col-group-head {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            margin: 0 0 0.2rem;
            padding: 0.2rem 0.15rem;
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            color: #1e293b;
            cursor: pointer;
            white-space: nowrap;
        }
        #column-dropdown-list .amm-col-group-items {
            display: flex;
            flex-direction: column;
            gap: 0;
        }
        #column-dropdown-list .amm-col-group-items label {
            min-width: 0;
        }

        #ammInvModal .amm-inv-pies {
            display: flex;
            gap: 10px;
            margin: 0 0 10px;
            flex-wrap: wrap;
        }
        #ammInvModal .amm-inv-pie-wrap {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            flex: 1 1 280px;
            min-width: 260px;
            padding: 8px 10px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #f8fafc;
        }
        #ammInvModal .amm-inv-pie-canvas-wrap {
            width: 148px;
            height: 148px;
            flex: 0 0 148px;
        }
        #ammInvModal .amm-inv-pie-legend {
            flex: 1 1 auto;
            min-width: 0;
        }
        #ammInvModal .amm-inv-pie-title {
            font-size: 12px;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 4px;
        }
        #ammInvModal .amm-inv-pie-row {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            line-height: 1.3;
            padding: 2px 0;
        }
        #ammInvModal .amm-inv-pie-swatch {
            width: 8px;
            height: 8px;
            border-radius: 2px;
            flex: 0 0 8px;
        }
        #ammInvModal .amm-inv-pie-name { flex: 1 1 auto; font-weight: 600; color: #334155; }
        #ammInvModal .amm-inv-pie-count { font-weight: 700; min-width: 28px; text-align: right; }
        #ammInvModal .amm-inv-pie-val { font-weight: 700; min-width: 40px; text-align: right; }
        #ammInvModal .amm-inv-pie-pct { color: #64748b; min-width: 28px; text-align: right; }
        #ammInvModal .amm-inv-hist-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            border: none;
            padding: 0;
            cursor: pointer;
            flex: 0 0 8px;
            box-shadow: 0 0 0 1px rgba(15, 23, 42, 0.12);
        }
        #ammInvModal .amm-inv-hist-dot:hover { transform: scale(1.35); }
        #ammInvModal .amm-inv-hist-wrap {
            display: none;
            margin: 0 0 6px;
            padding: 6px 8px 4px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #fff;
        }
        #ammInvModal .amm-inv-hist-wrap.is-open { display: block; }
        #ammInvModal .amm-inv-hist-canvas-wrap { height: 160px; }
        #marketplace-table .metric-diff-value {
            font-size: 9px;
            font-weight: 700;
            margin-left: 4px;
            white-space: nowrap;
            cursor: pointer;
            letter-spacing: -0.02em;
        }
        #marketplace-table .p-sales-vs-sales-pct {
            font-size: 10px;
            font-weight: 700;
            margin-left: 4px;
            white-space: nowrap;
            letter-spacing: -0.02em;
        }
        #marketplace-table [tabulator-field="P Growth"] .p-sales-vs-sales-pct {
            margin-left: 0;
        }
        #total-p-sales-vs-sales,
        #total-p-npft-vs-npft {
            margin-left: 4px;
            font-weight: 700;
            letter-spacing: -0.02em;
        }
        #marketplace-table .ads-page-link {
            color: #0d6efd;
            font-size: 10px;
            margin-left: 4px;
            text-decoration: none;
            vertical-align: middle;
        }
        #marketplace-table .ads-page-link:hover {
            color: #084298;
        }

        @media (max-width: 1199.98px) {
            .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
                height: 64px;
                font-size: 10px;
            }
            .tabulator .tabulator-header .tabulator-col {
                height: 64px !important;
            }
            #marketplace-table.tabulator .tabulator-cell {
                font-size: 11px;
                padding: 4px 6px;
            }
            .channel-logo-thumb,
            .channel-logo-placeholder {
                width: 22px;
                height: 22px;
            }
            #summary-stats {
                grid-template-columns: 1fr 1fr;
            }
            #summary-stats .amm-badge-block--today,
            #summary-stats .amm-badge-block--yesterday,
            #summary-stats .amm-badge-block--l30,
            #summary-stats .amm-badge-block--projected {
                grid-column: auto;
                grid-row: auto;
            }
            #summary-stats .amm-badge-block--inv {
                grid-column: 1 / -1;
                grid-row: 3;
            }
            #summary-stats .amm-badge-block--forecast {
                grid-column: 1;
                grid-row: 4;
            }
            #summary-stats .amm-badge-block--others {
                grid-column: 2;
                grid-row: 4;
            }
        }

        @media (max-width: 767.98px) {
            .amm-toolbar .d-flex {
                width: 100%;
            }
            #channel-search {
                width: 100% !important;
                min-width: 0 !important;
            }
            .amm-table-hscroll {
                top: 0;
            }
            .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
                height: 52px;
                font-size: 9px;
            }
            .tabulator .tabulator-header .tabulator-col {
                height: 52px !important;
            }
            #column-dropdown-menu {
                min-width: 0 !important;
                max-width: calc(100vw - 16px) !important;
            }
            #column-dropdown-list {
                max-height: 70vh;
                overflow: auto;
            }
            #summary-stats {
                grid-template-columns: 1fr;
            }
            #summary-stats .amm-badge-block--today,
            #summary-stats .amm-badge-block--yesterday,
            #summary-stats .amm-badge-block--l30,
            #summary-stats .amm-badge-block--projected,
            #summary-stats .amm-badge-block--inv,
            #summary-stats .amm-badge-block--forecast,
            #summary-stats .amm-badge-block--others {
                grid-column: 1;
                grid-row: auto;
            }
            #summary-stats .ebay2-summary-badge-row {
                flex-wrap: wrap;
                overflow: visible;
            }
            #summary-stats .amm-inv-block-grid {
                grid-template-columns: 1fr;
            }
            #ammInvModal .modal-dialog,
            #yesterdayMpModal .modal-dialog,
            #addChannelModal .modal-dialog,
            #editChannelModal .modal-dialog {
                margin: 0.5rem;
                max-width: calc(100vw - 1rem);
            }
            #ammInvModal .amm-inv-pie-wrap {
                min-width: 0;
                flex: 1 1 100%;
            }
        }
    </style>
@endsection

@section('content')
    <div class="amm-shell">
    @include('layouts.shared.page-title', [
        'page_title' => 'Active Channel Master',
        'sub_title' => 'Channels',
    ])

    <div class="toast-container"></div>

    <div class="row amm-page-row">
        <div class="col-12 amm-page-col">
        <div class="card shadow-sm amm-page-card">
            <div class="card-body py-3 amm-toolbar">

                <div class="d-flex align-items-center flex-wrap gap-2">
                    <!-- Search -->
                    <input type="text" id="channel-search" class="form-control form-control-sm"
                        placeholder="Search Channel..." style="width: 150px; display: inline-block;">

                    <!-- Type Filter (hidden from UI) -->
                    <select id="type-filter" class="form-select form-select-sm" style="width: auto; display: none;">
                        <option value="all">All Types</option>
                        <option value="B2C">🛒 B2C</option>
                        <option value="B2B">🏢 B2B</option>
                        <option value="Dropship">📦 DS</option>
                        <option value="Wholesale">🏬 Wholesale</option>
                    </select>

                    <!-- Column Visibility Dropdown -->
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-sm btn-outline-dark" type="button"
                            id="columnVisibilityDropdown" data-bs-toggle="dropdown" aria-expanded="false"
                            data-bs-auto-close="outside" title="Columns" aria-label="Columns">
                            <i class="fas fa-columns" style="color: #000;"></i>
                        </button>
                        <div class="dropdown-menu p-0" id="column-dropdown-menu"
                            aria-labelledby="columnVisibilityDropdown"
                            style="max-height:none; overflow:visible; min-width:0; width:max-content; max-width:calc(100vw - 24px);">
                            <div id="column-dropdown-list" class="mb-0 px-2 py-1">
                                <!-- Populated dynamically -->
                            </div>
                        </div>
                    </div>

                    <button id="addChannelBtn" class="btn btn-sm btn-outline-dark" data-bs-toggle="modal"
                        data-bs-target="#addChannelModal" title="Add Channel" aria-label="Add Channel">
                        <i class="fas fa-plus-circle" style="color: #000;"></i>
                    </button>

                </div>

                <!-- Summary Stats -->
                <div id="summary-stats" class="mt-2">
                    <div class="amm-badge-block amm-badge-block--today">
                        <div class="amm-badge-block-title">Today</div>
                        <div class="ebay2-summary-badge-row" role="group" aria-label="Today metrics">
                        <span class="badge fs-6 p-2 badge-chart-link" data-metric="today_sales" style="background-color: #fd7e14; color: white; font-weight: bold; cursor:pointer;" title="Sum of Today Sales. Current Eastern calendar day from 12:00 AM EST/EDT (America/New_York) through now. Dot compares today with yesterday.">
                            <span class="summary-trend-dot none" data-metric="today_sales" title="Today vs yesterday"></span>Sales: <span id="total-today-sales">$0</span>
                        </span>
                        <span class="badge bg-info fs-6 p-2 badge-chart-link" data-metric="t_orders" style="color: black; font-weight: bold; cursor:pointer;" title="T Orders = Σ (L30 Orders × Today Sales ÷ L30 Sales). Same pace as Today Sales. 0 when Today Sales is 0.">
                            <span class="summary-trend-dot none" data-metric="t_orders" title="Vs L30 daily average"></span>Orders: <span id="total-t-orders">0</span>
                        </span>
                        <span class="badge fs-6 p-2 badge-chart-link" data-metric="t_npft_pct" style="background-color: #17a2b8; color: white; font-weight: bold; cursor:pointer;" title="T Nprft% = Σ (Today Sales × NPFT%) ÷ Σ Today Sales.">
                            <span class="summary-trend-dot none" data-metric="t_npft" title="Vs L30 NPFT%"></span>Nprft%: <span id="avg-t-npft">0.0%</span>
                        </span>
                        <span class="badge fs-6 p-2 badge-chart-link" data-metric="t_groi_pct" style="background-color: #dc3545; color: white; font-weight: bold; cursor:pointer;" title="TGroi% = Σ (Today Sales × GPFT%) ÷ Σ today COGS. Today COGS = L30 COGS × (Today Sales ÷ L30 Sales).">
                            <span class="summary-trend-dot none" data-metric="t_groi" title="Vs L30 G ROI%"></span>Groi%: <span id="avg-t-groi">0.0%</span>
                        </span>
                        <span class="badge bg-primary fs-6 p-2 badge-chart-link" data-metric="t_nroi_pct" style="color: white; font-weight: bold; cursor:pointer;" title="TNROI% = Σ (Today Sales × NPFT%) ÷ Σ today COGS.">
                            <span class="summary-trend-dot none" data-metric="t_nroi" title="Vs L30 NROI%"></span>NROI%: <span id="avg-t-nroi">0.0%</span>
                        </span>
                        <span class="badge fs-6 p-2 badge-chart-link" data-metric="t_ads_pct" style="background-color: #d63384; color: white; font-weight: bold; cursor:pointer;" title="T Ads% = Σ (Today Sales × Ads%) ÷ Σ Today Sales.">
                            <span class="summary-trend-dot none" data-metric="t_ads" title="Vs L30 Ads%"></span>Ads%: <span id="avg-t-ads">0.0%</span>
                        </span>
                        <span class="badge fs-6 p-2 badge-chart-link" data-metric="t_views" style="background-color: #0dcaf0; color: #111; font-weight: bold; cursor:pointer;" title="T Views = Σ (Views × Today Sales ÷ L30 Sales). Same pace as Today Sales.">
                            <span class="summary-trend-dot none" data-metric="t_views" title="Vs L30 daily average"></span>Views: <span id="total-t-views">0</span>
                        </span>
                        </div>
                    </div>
                    <div class="amm-badge-block amm-badge-block--yesterday">
                        <div class="amm-badge-block-title">
                            <span>Yesterday</span>
                            <button type="button" id="yesterdayMpViewBtn" class="btn btn-sm btn-outline-dark amm-yesterday-view-btn"
                                title="Yesterday by marketplace" aria-lacrbel="Yesterday by marketplace">
                                <i class="fas fa-eye" style="color: #000;"></i>
                            </button>
                        </div>
                        <div class="ebay2-summary-badge-row" role="group" aria-label="Yesterday metrics">
                        <span class="badge fs-6 p-2 badge-chart-link" data-metric="y_sales" style="background-color: #17a2b8; color: white; font-weight: bold; cursor:pointer;" title="Sum of Y Sales column (Yesterday's sales across all channels). Trend is built from daily snapshots: older days that pre-date Y Sales being captured will be skipped.">
                            <span class="summary-trend-dot none" data-metric="y_sales" title="Rolling history"></span>Sales: <span id="total-y-sales">$0</span>
                        </span>
                        <span class="badge bg-info fs-6 p-2 badge-chart-link" data-metric="y_orders" style="color: black; font-weight: bold; cursor:pointer;" title="Y Orders = sum of yesterday order counts across channels (1-day Pacific, same Orders column as Yesterday by marketplace).">
                            <span class="summary-trend-dot none" data-metric="y_orders" title="Vs L30 daily average"></span>Orders: <span id="total-y-orders">0</span>
                        </span>
                        <span class="badge fs-6 p-2 badge-chart-link d-none" data-metric="y_pft" style="background-color: #17a2b8; color: white; font-weight: bold; cursor:pointer;" title="Y PFT $ = sum of (Y Sales × GPFT%) per channel. Yesterday gross profit using each channel’s GPFT% on the Y Sales column.">
                            <span class="summary-trend-dot none" data-metric="y_pft" title="Rolling history"></span>Y PFT: <span id="total-y-pft">$0</span>
                        </span>
                        <span class="badge fs-6 p-2 badge-chart-link d-none" data-metric="y_npft_amt" style="background-color: #0f766e; color: white; font-weight: bold; cursor:pointer;" title="Y NPFT $ = sum of (Y Sales × NPFT%) per channel. Yesterday net profit using each channel’s NPFT% on the Y Sales column.">
                            <span class="summary-trend-dot none" data-metric="y_npft_amt" title="Rolling history"></span>Y NPFT: <span id="total-y-npft">$0</span>
                        </span>
                        <span class="badge fs-6 p-2 badge-chart-link" data-metric="y_npft_pct" style="background-color: #17a2b8; color: white; font-weight: bold; cursor:pointer;" title="yNprft% = (Σ yesterday net PFT $) ÷ (Σ yesterday sales), measured from yesterday's orders alone.">
                            <span class="summary-trend-dot none" data-metric="y_npft_pct" title="Rolling history"></span>Nprft%: <span id="avg-y-npft">0.0%</span>
                        </span>
                        <span class="badge fs-6 p-2 badge-chart-link" data-metric="y_groi_pct" style="background-color: #dc3545; color: white; font-weight: bold; cursor:pointer;" title="YGroi% = (Σ yesterday gross PFT $) ÷ (Σ yesterday COGS), measured from yesterday's orders alone.">
                            <span class="summary-trend-dot none" data-metric="y_groi_pct" title="Rolling history"></span>Groi%: <span id="avg-y-groi">0.0%</span>
                        </span>
                        <span class="badge bg-primary fs-6 p-2 badge-chart-link" data-metric="y_nroi_pct" style="color: white; font-weight: bold; cursor:pointer;" title="YNROI% = (Σ yesterday net PFT $) ÷ (Σ yesterday COGS), measured from yesterday's orders alone.">
                            <span class="summary-trend-dot none" data-metric="y_nroi_pct" title="Rolling history"></span>NROI%: <span id="avg-y-nroi">0.0%</span>
                        </span>
                        <span class="badge fs-6 p-2" data-metric="y_ads_pct" style="background-color: #d63384; color: white; font-weight: bold;" title="Y Ads% = Σ yesterday ad spend ÷ Σ yesterday sales × 100. Spend is each channel’s Ads% on yesterday’s sales.">
                            <span class="summary-trend-dot none" data-metric="y_ads_pct" title="Vs L30 Ads%"></span>Ads%: <span id="avg-y-ads">0.0%</span>
                        </span>
                        <span class="badge fs-6 p-2 badge-chart-link" data-metric="y_views" style="background-color: #0dcaf0; color: #111; font-weight: bold; cursor:pointer;" title="Y Views = sum of yesterday listing views across channels (1-day Pacific, not L30).">
                            <span class="summary-trend-dot none" data-metric="y_views" title="Vs L30 daily average"></span>Views: <span id="total-y-views">0</span>
                        </span>
                        </div>
                    </div>
                    <div class="amm-badge-block amm-badge-block--l30">
                        <div class="amm-badge-block-title">L30</div>
                        <div class="ebay2-summary-badge-row" role="group" aria-label="L30 metrics">
                        <span class="badge bg-success fs-6 p-2 badge-chart-link" data-metric="l30_sales" style="color: black; font-weight: bold; cursor:pointer;" title="Sum of Sales column. Amz = last {{ (int) \App\Http\Controllers\Sales\AmazonSalesController::DAILY_SALES_WINDOW_DAYS }} days Pacific (same window &amp; AMAZON_SALES_TOTAL_MODE as Amz Daily Sales). Other channels vary.">
                            <span class="summary-trend-dot none" data-metric="l30_sales" title="Rolling history"></span>Sales: <span id="total-l30-sales">$0</span>
                        </span>
                        <span class="badge bg-info fs-6 p-2 badge-chart-link" data-metric="l30_orders" style="color: black; font-weight: bold; cursor:pointer;" title="Sum of Orders column. Amz = {{ (int) \App\Http\Controllers\Sales\AmazonSalesController::DAILY_SALES_WINDOW_DAYS }}-day Pacific rolling (same as Amz Daily Sales); other channels vary.">
                            <span class="summary-trend-dot none" data-metric="l30_orders" title="Rolling history"></span>Orders: <span id="total-l30-orders">0</span>
                        </span>
                        <span class="badge bg-primary fs-6 p-2 badge-chart-link d-none" data-metric="qty" style="color: white; font-weight: bold; cursor:pointer;" title="View trend">
                            <span class="summary-trend-dot none" data-metric="qty" title="Rolling history"></span>Qty items: <span id="total-qty">0</span>
                        </span>
                        <span class="badge bg-warning fs-6 p-2 badge-chart-link" data-metric="gprofit" style="color: black; font-weight: bold; cursor:pointer;" title="Blended Gprofit% = sum(Sales×G%) / sum(Sales) using each channel’s rolling Sales column; matches GPFT column footer">
                            <span class="summary-trend-dot none" data-metric="gprofit" title="Rolling history"></span>GPFT: <span id="avg-gprofit">0.0%</span>
                        </span>
                        <span class="badge bg-warning fs-6 p-2 d-none" style="color: black; font-weight: bold; border: 1px solid rgba(0,0,0,.25);" title="Gross profit $ = sum of (rolling Sales × Gprofit%) per channel; matches Gross PFT column (show column to verify)">
                            <span class="summary-trend-dot none" data-metric="gprofit" title="Rolling history"></span>GPFT: <span id="total-gross-pft">$0</span>
                        </span>
                        <span class="badge bg-danger fs-6 p-2 badge-chart-link" data-metric="groi" style="color: white; font-weight: bold; cursor:pointer;" title="View trend">
                            <span class="summary-trend-dot none" data-metric="groi" title="Rolling history"></span>G ROI: <span id="avg-groi">0.0%</span>
                        </span>
                        <span class="badge bg-secondary fs-6 p-2 badge-chart-link" data-metric="ad_spend" style="color: white; font-weight: bold; cursor:pointer;" title="View trend">
                            <span class="summary-trend-dot none" data-metric="ad_spend" title="Rolling history"></span>Spend: <span id="total-ad-spend">$0</span>
                        </span>
                        <span class="badge fs-6 p-2 badge-chart-link" data-metric="ads_pct" style="background-color: #d63384; color: white; font-weight: bold; cursor:pointer;" title="Ads % = Total Ad Spend / Total L30 Sales × 100 (blended across channels — same as the Ads % column). Reverb bump fees count as spend.">
                            <span class="summary-trend-dot none" data-metric="ads_pct" title="Rolling history"></span>Ads: <span id="ads-percent-badge">0%</span>
                        </span>
                        <span class="badge bg-info fs-6 p-2 badge-chart-link" data-metric="total_views" style="color: black; font-weight: bold; cursor:pointer;" title="View trend - Total Views (listing/Map traffic)">
                            <span class="summary-trend-dot none" data-metric="total_views" title="Rolling history"></span>Views: <span id="total-views-badge">0</span>
                        </span>
                        <span class="badge bg-primary fs-6 p-2 badge-chart-link" data-metric="cvr" style="color: white; font-weight: bold; cursor:pointer;" title="Listing CVR (all channels): weighted from each channel's listing CVR × views (same as the CVR column). Falls back to Qty ÷ Views when a channel has no listing CVR.">
                            <span class="summary-trend-dot none" data-metric="cvr" title="Rolling history"></span>CVR: <span id="cvr-pct-badge">0.00%</span>
                        </span>
                        @include('partials.analytics-dil-badge', ['dilChannel' => 'allmarketplace'])
                        <span class="badge bg-warning fs-6 p-2 badge-chart-link" data-metric="pft" style="color: black; font-weight: bold; cursor:pointer;" title="Net profit $ = sum(rolling Sales×Gprofit% − Ad spend); same as Sales × (G% − Ad Spend/Sales) per channel">
                            <span class="summary-trend-dot none" data-metric="pft" title="Rolling history"></span>NPFT: <span id="total-pft">$0</span>
                        </span>
                        <span class="badge bg-warning fs-6 p-2 badge-chart-link" data-metric="npft" style="color: black; font-weight: bold; cursor:pointer;" title="View trend">
                            <span class="summary-trend-dot none" data-metric="npft" title="Rolling history"></span>NPFT: <span id="avg-npft">0.0%</span>
                        </span>
                        <span class="badge bg-primary fs-6 p-2 badge-chart-link" data-metric="nroi" style="color: white; font-weight: bold; cursor:pointer;" title="View trend">
                            <span class="summary-trend-dot none" data-metric="nroi" title="Rolling history"></span>NROI: <span id="avg-nroi">0.0%</span>
                        </span>
                        </div>
                    </div>
                    <div class="amm-badge-block amm-badge-block--projected">
                        <div class="amm-badge-block-title">
                            <span>Projected</span>
                            <button type="button" id="projectedMpViewBtn" class="btn btn-sm btn-outline-dark amm-projected-view-btn"
                                title="Projected figures by marketplace" aria-label="Projected figures by marketplace">
                                <i class="fas fa-calendar-week" style="color: #000;"></i>
                            </button>
                        </div>
                        <div class="ebay2-summary-badge-row" role="group" aria-label="Projected metrics">
                        <span class="badge fs-6 p-2 badge-chart-link" data-metric="p_sales" style="background-color: #0d6efd; color: white; font-weight: bold; cursor:pointer;" title="Sum of P-Sales column. Projected 30-day sales from last-7-day pace: (L7 Sales ÷ 7) × 30. % is P-Sales vs Sales.">
                            <span class="summary-trend-dot none" data-metric="p_sales" title="Rolling history"></span>Sales: <span id="total-p-sales">$0</span><span id="total-p-sales-vs-sales"></span>
                        </span>
                        <span class="badge bg-info fs-6 p-2 badge-chart-link" data-metric="p_orders" style="color: black; font-weight: bold; cursor:pointer;" title="P Orders = Σ (L30 Orders × P-Sales ÷ L30 Sales). Same 30-day pace as P-Sales. Click for daily history.">
                            <span class="summary-trend-dot none" data-metric="p_orders" title="Rolling history"></span>Orders: <span id="total-p-orders">0</span>
                        </span>
                        <span class="badge fs-6 p-2 badge-chart-link" data-metric="p_groi_pct" style="background-color: #0d6efd; color: white; font-weight: bold; cursor:pointer;" title="P-GROI% = Σ (P-Sales × GPFT%) ÷ Σ projected COGS. Projected COGS = L30 COGS × (P-Sales ÷ L30 Sales). P-Sales = (L7 ÷ 7) × 30.">
                            <span class="summary-trend-dot none" data-metric="p_groi_pct" title="Rolling history"></span>GROI%: <span id="avg-p-groi">0.0%</span>
                        </span>
                        <span class="badge fs-6 p-2 badge-chart-link" data-metric="p_npft" style="background-color: #0d6efd; color: white; font-weight: bold; cursor:pointer;" title="Projected NPFT% = blended GPFT% on P-Sales minus Ads% on P-Sales. P-Sales is last-7-day pace × 30; spend is current L30 ad spend.">
                            <span class="summary-trend-dot none" data-metric="p_npft" title="Rolling history"></span>Npft%: <span id="avg-p-npft">0.0%</span>
                        </span>
                        <span class="badge fs-6 p-2 badge-chart-link d-none" data-metric="p_npft_amt" style="background-color: #0d6efd; color: white; font-weight: bold; cursor:pointer;" title="Sum of P NPFT $ column. Projected net profit $ = P-Sales × P-Npft%. P-Sales is last-7-day pace × 30; spend is current L30 ad spend. % is P NPFT $ vs NPFT $.">
                            <span class="summary-trend-dot none" data-metric="p_npft_amt" title="Rolling history"></span>P NPFT: <span id="total-p-npft">$0</span><span id="total-p-npft-vs-npft"></span>
                        </span>
                        <span class="badge fs-6 p-2 badge-chart-link" data-metric="p_nroi_pct" style="background-color: #0d6efd; color: white; font-weight: bold; cursor:pointer;" title="P-NROI% uses the same projected COGS as P GROI%. P-Sales = (L7 ÷ 7) × 30. Projected COGS = L30 COGS × (P-Sales ÷ L30 Sales). P-NROI% = Σ (P-Sales × GPFT% − ad spend) ÷ Σ projected COGS.">
                            <span class="summary-trend-dot none" data-metric="p_nroi_pct" title="Rolling history"></span>NROI%: <span id="avg-p-nroi">0.0%</span>
                        </span>
                        <span class="badge fs-6 p-2 badge-chart-link" data-metric="p_ads_pct" style="background-color: #d63384; color: white; font-weight: bold; cursor:pointer;" title="P Ads% = total ad spend ÷ Σ P-Sales × 100. Spend is current L30 ad spend. P-Sales = (L7 ÷ 7) × 30. Click for daily history.">
                            <span class="summary-trend-dot none" data-metric="p_ads_pct" title="Rolling history"></span>Ads%: <span id="avg-p-ads">0.0%</span>
                        </span>
                        <span class="badge fs-6 p-2 badge-chart-link" data-metric="p_views" style="background-color: #0d6efd; color: white; font-weight: bold; cursor:pointer;" title="P Views = Σ (Views × P-Sales ÷ L30 Sales). Same 30-day pace as P-Sales. Click for daily history.">
                            <span class="summary-trend-dot none" data-metric="p_views" title="Rolling history"></span>Views: <span id="total-p-views">0</span>
                        </span>
                        </div>
                    </div>
                    <div class="amm-badge-block amm-badge-block--forecast">
                        <div class="amm-badge-block-title">Forecast</div>
                        <div class="ebay2-summary-badge-row" role="group" aria-label="Forecast metrics">
                        <a href="{{ route('forecast.analysis') }}" class="badge fs-6 p-2 badge-chart-link" data-metric="fa_msl_lp" style="background-color:#198754;color:#111;font-weight:bold;text-decoration:none;cursor:pointer;" title="MSL × LP. Same total as Forecast Analysis. Click for daily history (Ctrl/Cmd+click opens Forecast Analysis).">MSL_LP: {{ $forecastBadges['msl_lp'] }}</a>
                        <a href="{{ route('forecast.analysis') }}" class="badge fs-6 p-2 badge-chart-link" data-metric="fa_msl_sp" style="background-color:#0dcaf0;color:#111;font-weight:bold;text-decoration:none;cursor:pointer;" title="MSL × AMZ price ÷ 4. Click for daily history (Ctrl/Cmd+click opens Forecast Analysis).">MSL_SP: {{ $forecastBadges['msl_sp'] }}</a>
                        <a href="{{ route('forecast.analysis') }}" class="badge fs-6 p-2 badge-chart-link" data-metric="fa_ord" style="background-color:#ffc107;color:#111;font-weight:bold;text-decoration:none;cursor:pointer;" title="Σ (CP × Order). Click for daily history (Ctrl/Cmd+click opens Forecast Analysis).">Ord: {{ $forecastBadges['ord'] }}</a>
                        <a href="{{ route('forecast.analysis') }}" class="badge fs-6 p-2 badge-chart-link" data-metric="fa_mip" style="background-color:#ffc107;color:#111;font-weight:bold;text-decoration:none;cursor:pointer;" title="MIP value. Click for daily history (Ctrl/Cmd+click opens Forecast Analysis).">MIP: {{ $forecastBadges['mip'] }}</a>
                        <a href="{{ route('forecast.analysis') }}" class="badge fs-6 p-2 badge-chart-link" data-metric="fa_r2s" style="background-color:#ffc107;color:#111;font-weight:bold;text-decoration:none;cursor:pointer;" title="R2S value. Click for daily history (Ctrl/Cmd+click opens Forecast Analysis).">R2S: {{ $forecastBadges['r2s'] }}</a>
                        <a href="{{ route('forecast.analysis') }}" class="badge fs-6 p-2 badge-chart-link" data-metric="fa_trn" style="background-color:#6c757d;color:#fff;font-weight:bold;text-decoration:none;cursor:pointer;" title="Transit value. Click for daily history (Ctrl/Cmd+click opens Forecast Analysis).">Trn: {{ $forecastBadges['trn'] }}</a>
                        <a href="{{ route('forecast.analysis') }}" class="badge fs-6 p-2 badge-chart-link" data-metric="fa_npft" style="background-color:#ffc107;color:#111;font-weight:bold;text-decoration:none;cursor:pointer;" title="NPFT% — same as Active channel. Click for daily history (Ctrl/Cmd+click opens Forecast Analysis).">NPFT: <span id="amm-fa-npft">—</span></a>
                        <a href="{{ route('forecast.analysis') }}" class="badge fs-6 p-2 badge-chart-link" data-metric="fa_nroi" style="background-color:#20c997;color:#111;font-weight:bold;text-decoration:none;cursor:pointer;" title="NROI% — same as Active channel. Click for daily history (Ctrl/Cmd+click opens Forecast Analysis).">NROI: <span id="amm-fa-nroi">—</span></a>
                        </div>
                    </div>
                    <div class="amm-badge-block amm-badge-block--others">
                        <div class="amm-badge-block-title">Others</div>
                        <div class="ebay2-summary-badge-row" role="group" aria-label="Other metrics">
                        <span class="badge bg-primary fs-6 p-2" style="color: white; font-weight: bold;">
                            <span class="summary-trend-dot none" title="Channel count"></span>Channels: <span id="total-channels">0</span>
                        </span>
                        <span class="badge bg-warning fs-6 p-2 badge-chart-link" data-metric="rating" style="color: black; font-weight: bold; cursor:pointer;" title="Weighted average product rating across channels (amazon_product_reviews). Dot opens rating history.">
                            <span class="summary-trend-dot none" data-metric="rating" title="Rating history"></span>Rating: <span id="total-rating-badge">0.0</span> ★
                        </span>
                        <span class="badge bg-info fs-6 p-2 badge-chart-link" data-metric="reviews" style="color: black; font-weight: bold; cursor:pointer;" title="Total review count across channels (amazon_product_reviews). Dot opens review-count history.">
                            <span class="summary-trend-dot none" data-metric="reviews" title="Review count history"></span>Reviews: <span id="total-reviews-badge">0</span>
                        </span>
                        </div>
                    </div>
                    <div class="amm-badge-block amm-badge-block--inv">
                        <div class="amm-badge-block-title">INV</div>
                        <div class="ebay2-summary-badge-row" role="group" aria-label="Inventory metrics">
                        <span class="badge bg-info fs-6 p-2 badge-chart-link" data-metric="inventory" style="color: black; font-weight: bold; cursor:pointer;" title="Sum of (Shopify INV × Amazon live price) for Active SKUs — same units as Inv@SP / Inv@LP">
                            <span class="summary-trend-dot none" data-metric="inventory" title="Rolling history"></span>inv: <span id="inventory-value-amazon">0</span>
                        </span>
                        <span class="badge bg-success fs-6 p-2 badge-chart-link" data-metric="inv_at_sp" style="color: black; font-weight: bold; cursor:pointer;" title="Shopify INV × Standard Price (amazon_data_view.STANDARD_PRICE, LMP siblings, then live Amazon price) for Active SKUs">
                            <span class="summary-trend-dot none" data-metric="inv_at_sp" title="Rolling history"></span>Inv@SP: <span id="inv-at-sp">0</span>
                        </span>
                        <span class="badge bg-warning fs-6 p-2 badge-chart-link" data-metric="inv_at_lp" style="color: black; font-weight: bold; cursor:pointer;" title="View trend - Sum of (Shopify inventory × LP)">
                            <span class="summary-trend-dot none" data-metric="inv_at_lp" title="Rolling history"></span>Inv@LP: <span id="inv-at-lp">0</span>
                        </span>
                        <span class="badge bg-secondary fs-6 p-2 badge-chart-link" data-metric="tat" style="color: white; font-weight: bold; cursor:pointer;" title="TAT = inv ÷ L30 Sales (months of stock at current sales)">
                            <span class="summary-trend-dot none" data-metric="tat" title="Rolling history"></span>TAT: <span id="tat-badge">0</span>
                        </span>
                        <a href="{{ route('forecast.analysis') }}" class="badge fs-6 p-2" style="background-color:{{ $forecastBadges['available_bg'] }};color:{{ $forecastBadges['available_fg'] }};font-weight:bold;text-decoration:none;" title="Available % — child SKUs with INV &gt; 0.">Avail: {{ $forecastBadges['available_pct'] }}%</a>
                        </div>
                        <div class="amm-inv-block-grid">
                            <div class="amm-inv-block-pair" style="display:grid;grid-template-columns:220px minmax(0,1fr);gap:10px;align-items:center;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:8px 10px;">
                                <div class="amm-inv-block-canvas" style="width:220px;height:120px;overflow:hidden;background:#fff;border-radius:8px;">
                                    <canvas id="amm-inv-block-sp-pie" width="220" height="120" style="width:220px;height:120px;display:block;"></canvas>
                                </div>
                                <div class="amm-inv-block-copy">
                                    <div class="amm-inv-block-subtitle">Inv@SP</div>
                                    <div id="amm-inv-block-sp"><div class="text-muted" style="font-size:11px;">Loading…</div></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-body amm-table-card-body">
                <div class="amm-table-hscroll" id="amm-table-hscroll" aria-label="Table columns">
                    <div class="amm-table-hscroll-inner" id="amm-table-hscroll-inner"></div>
                </div>
                <div id="marketplace-table-wrapper">
                    <div id="marketplace-table"></div>
                </div>
            </div>
        </div>
        </div>
    </div>
    </div>

    <div class="modal fade" id="ammInvModal" tabindex="-1" aria-labelledby="ammInvModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title fs-6" id="ammInvModalLabel">
                        INV — Red / Green / Pink
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-2">
                    <div id="amm-inv-loading" class="text-center py-3" style="display:none;">
                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                    </div>
                    <div id="amm-inv-error" class="text-center py-2 text-danger small" style="display:none;"></div>
                    <div class="amm-inv-pies">
                        <div class="amm-inv-pie-wrap">
                            <div class="amm-inv-pie-canvas-wrap">
                                <canvas id="amm-inv-lp-pie"></canvas>
                            </div>
                            <div>
                                <div class="amm-inv-pie-title">Inv@LP</div>
                                <div class="amm-inv-pie-legend" id="amm-inv-lp-legend"></div>
                            </div>
                        </div>
                        <div class="amm-inv-pie-wrap">
                            <div class="amm-inv-pie-canvas-wrap">
                                <canvas id="amm-inv-sp-pie"></canvas>
                            </div>
                            <div>
                                <div class="amm-inv-pie-title">Inv@SP</div>
                                <div class="amm-inv-pie-legend" id="amm-inv-sp-legend"></div>
                            </div>
                        </div>
                    </div>
                    <div class="amm-inv-hist-wrap" id="amm-inv-hist-wrap">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small fw-semibold" id="amm-inv-hist-title">INV history</span>
                            <button type="button" class="btn-close" id="amm-inv-hist-close" aria-label="Close history" style="font-size:10px;"></button>
                        </div>
                        <div class="amm-inv-hist-canvas-wrap">
                            <canvas id="amm-inv-hist"></canvas>
                        </div>
                    </div>
                    <p class="small text-muted mb-0">
                        Active SKUs with INV ≥ 0.01. DIL = L30 ÷ INV × 100.
                        <span style="color:#a00211;font-weight:700;">Red &lt;25%</span>,
                        <span style="color:#28a745;font-weight:700;">Green 25–50%</span>,
                        <span style="color:#e83e8c;font-weight:700;">Pink ≥50%</span>.
                        Count is SKUs. Pies are inventory $ at LP and SP.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Channel Modal -->
    <div class="modal fade" id="addChannelModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i> Add New Channel</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="channelForm" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="channelName" class="form-label">Channel Name</label>
                            <input type="text" class="form-control" id="channelName" required>
                        </div>
                        <div class="mb-3">
                            <label for="channelAlias" class="form-label">Alias</label>
                            <input type="text" class="form-control" id="channelAlias" maxlength="190"
                                placeholder="Short display name">
                            <small class="text-muted">Shown in the Alias column; click it to open this channel's view.</small>
                        </div>
                        <div class="mb-3">
                            <label for="channelPromotions" class="form-label">Promotions (%)</label>
                            <input type="number" class="form-control" id="channelPromotions" step="0.01"
                                min="0" placeholder="e.g. 10">
                            <small class="text-muted">Manual promotions percentage for this channel.</small>
                        </div>
                        <div class="mb-3">
                            <label for="channelComplianceCount" class="form-label">Compliance Count</label>
                            <input type="number" class="form-control" id="channelComplianceCount" step="1"
                                min="0" placeholder="e.g. 5">
                            <small class="text-muted">Manual compliance count for this channel.</small>
                        </div>
                        <div class="mb-3">
                            <label for="channelBrandAuthorisation" class="form-label">Brand Authorisation</label>
                            <select class="form-control" id="channelBrandAuthorisation">
                                <option value="">Select</option>
                                <option value="Yes">Yes</option>
                                <option value="No">No</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="channelLogo" class="form-label">Channel Logo</label>
                            <div class="d-flex align-items-center gap-2">
                                <div id="channelLogoPreview" class="channel-logo-preview">
                                    <span class="placeholder-text">No logo</span>
                                </div>
                                <div class="flex-grow-1">
                                    <input type="file" class="form-control" id="channelLogo"
                                        accept="image/png,image/jpeg,image/jpg,image/gif,image/svg+xml,image/webp">
                                    <small class="text-muted">PNG, JPG, GIF, SVG, or WEBP. Max 2 MB.</small>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="channelSellerLink" class="form-label">Seller Link</label>
                            <input type="url" class="form-control" id="channelSellerLink"
                                placeholder="https://..." maxlength="1000">
                            <small class="text-muted">When provided, the channel logo will link to this URL.</small>
                        </div>
                        <div class="mb-3">
                            <label for="channelUrl" class="form-label">Sheet Link</label>
                            <input type="url" class="form-control" id="channelUrl">
                        </div>
                        <div class="mb-3">
                            <label for="type" class="form-label">Type</label>
                            <select class="form-control" id="type">
                                <option value="">Select Type</option>
                                <option value="B2B">B2B</option>
                                <option value="B2C">B2C</option>
                                <option value="Dropship">DS</option>
                                <option value="Wholesale">Wholesale</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="channelUpdate" class="form-label">Data Source</label>
                            <select class="form-control" id="channelUpdate">
                                <option value="">Select</option>
                                <option value="A">API</option>
                                <option value="S">GS</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveChannelBtn">Save Channel</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Channel Modal -->
    <div class="modal fade" id="editChannelModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i> Edit Channel</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editChannelForm" enctype="multipart/form-data">
                        <input type="hidden" id="originalChannel" name="original_channel">
                        <input type="hidden" id="editExistingLogo">
                        @php
                            $canEditChannelName = in_array(strtolower(trim((string) (auth()->user()->email ?? ''))), [
                                'support@5core.com',
                                'president@5core.com',
                                'software5@5core.com',
                            ], true);
                        @endphp
                        <div class="mb-3">
                            <label for="editChannelName" class="form-label">Channel Name</label>
                            <input type="text" class="form-control" id="editChannelName" {{ $canEditChannelName ? '' : 'readonly' }}>
                            @unless ($canEditChannelName)
                                <small class="text-muted">Channel name editing is restricted.</small>
                            @endunless
                        </div>
                        <div class="mb-3">
                            <label for="editChannelAlias" class="form-label">Alias</label>
                            <input type="text" class="form-control" id="editChannelAlias" maxlength="190"
                                placeholder="Short display name">
                            <small class="text-muted">Shown in the Alias column; click it to open this channel's view.</small>
                        </div>
                        <div class="mb-3">
                            <label for="editChannelPromotions" class="form-label">Promotions (%)</label>
                            <input type="number" class="form-control" id="editChannelPromotions" step="0.01"
                                min="0" placeholder="e.g. 10">
                            <small class="text-muted">Manual promotions percentage for this channel.</small>
                        </div>
                        <div class="mb-3">
                            <label for="editChannelComplianceCount" class="form-label">Compliance Count</label>
                            <input type="number" class="form-control" id="editChannelComplianceCount" step="1"
                                min="0" placeholder="e.g. 5">
                            <small class="text-muted">Manual compliance count for this channel.</small>
                        </div>
                        <div class="mb-3">
                            <label for="editChannelBrandAuthorisation" class="form-label">Brand Authorisation</label>
                            <select class="form-control" id="editChannelBrandAuthorisation">
                                <option value="">Select</option>
                                <option value="Yes">Yes</option>
                                <option value="No">No</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="editChannelLogo" class="form-label">Channel Logo</label>
                            <div class="d-flex align-items-center gap-2">
                                <div id="editChannelLogoPreview" class="channel-logo-preview">
                                    <span class="placeholder-text">No logo</span>
                                </div>
                                <div class="flex-grow-1">
                                    <input type="file" class="form-control" id="editChannelLogo"
                                        accept="image/png,image/jpeg,image/jpg,image/gif,image/svg+xml,image/webp">
                                    <small class="text-muted">Choose a file to replace the current logo. Max 2 MB.</small>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="editChannelSellerLink" class="form-label">Seller Link</label>
                            <input type="url" class="form-control" id="editChannelSellerLink"
                                placeholder="https://..." maxlength="1000">
                            <small class="text-muted">When provided, the channel logo will link to this URL.</small>
                        </div>
                        <div class="mb-3">
                            <label for="editChannelUrl" class="form-label">Sheet URL</label>
                            <input type="text" class="form-control" id="editChannelUrl" required>
                        </div>
                        <div class="mb-3">
                            <label for="editType" class="form-label">Type</label>
                            <select class="form-control" id="editType" required>
                                <option value="">Select Type</option>
                                <option value="B2B">B2B</option>
                                <option value="B2C">B2C</option>
                                <option value="Dropship">DS</option>
                                <option value="Wholesale">Wholesale</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="editMissingLink" class="form-label">Blade page link</label>
                            <input type="url" class="form-control" id="editMissingLink" placeholder="https://...">
                            <small class="text-muted">This link will open when clicking channel name</small>
                        </div>
                        <div class="mb-3">
                            <label for="editChannelUpdate" class="form-label">Data Source</label>
                            <select class="form-control" id="editChannelUpdate">
                                <option value="">Select</option>
                                <option value="A">API</option>
                                <option value="S">GS</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-danger me-auto" id="archiveChannelBtn" title="Archive channel">
                        <i class="fa fa-archive"></i> Archive
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="updateChannelBtn">Update Channel</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Yesterday by marketplace -->
    <div class="modal fade" id="yesterdayMpModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2" style="background: linear-gradient(135deg, #17a2b8, #0d6efd);">
                    <h6 class="modal-title text-white mb-0">
                        <i class="fas fa-eye me-2"></i>
                        Yesterday by marketplace
                        <span class="fw-normal" style="opacity:.9;">— {{ now('America/Los_Angeles')->subDay()->format('M j, Y') }} (Pacific, 1-day — not L30)</span>
                    </h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-2">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-hover mb-0" id="yesterdayMpTable">
                            <thead>
                                <tr>
                                    <th class="text-start">Marketplace</th>
                                    <th>Y Sales</th>
                                    <th>GPFT</th>
                                    <th>GROI</th>
                                    <th>NROI</th>
                                    <th>NPFT</th>
                                    <th>Views</th>
                                    <th>CVR</th>
                                    <th>Orders</th>
                                </tr>
                            </thead>
                            <tbody id="yesterdayMpTableBody"></tbody>
                            <tfoot id="yesterdayMpTableFoot"></tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Projected by marketplace -->
    <div class="modal fade" id="projectedMpModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2" style="background: linear-gradient(135deg, #6d28d9, #4c1d95);">
                    <h6 class="modal-title text-white mb-0">
                        <i class="fas fa-calendar-week me-2"></i>
                        Projected by marketplace
                        <span class="fw-normal" style="opacity:.9;">— P-Sales = (L7 ÷ 7) × 30</span>
                    </h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-2">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-hover mb-0" id="projectedMpTable">
                            <thead>
                                <tr>
                                    <th class="text-start">Marketplace</th>
                                    <th>P-Sales</th>
                                    <th>P-GROI%</th>
                                    <th>P-Npft%</th>
                                    <th>P-NROI%</th>
                                </tr>
                            </thead>
                            <tbody id="projectedMpTableBody"></tbody>
                            <tfoot id="projectedMpTableFoot"></tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Channel History Table Modal -->
    <div class="modal fade" id="channelHistoryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #4361ee, #3f37c9);">
                    <h5 class="modal-title text-white">
                        <i class="fas fa-history me-2"></i>
                        <span id="modalChannelName">Channel</span> - Historical Data (31-day Pacific, through yesterday)
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead style="background: #f5f7fa;">
                                <tr>
                                    <th>Date</th>
                                    <th class="text-end">Sales</th>
                                    <th class="text-end">Orders</th>
                                    <th class="text-end">Qty items</th>
                                    <th class="text-end">Clicks</th>
                                    <th class="text-end">Gprofit%</th>
                                    <th class="text-end">NPFT%</th>
                                </tr>
                            </thead>
                            <tbody id="historyTableBody">
                                <tr>
                                    <td colspan="7" class="text-center">Loading...</td>
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

    <!-- Channel History Graph Modal -->
    <div class="modal fade" id="channelHistoryGraphModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #4361ee, #3f37c9);">
                    <h5 class="modal-title text-white">
                        <i class="fas fa-chart-area me-2"></i>
                        <span id="modalGraphChannelName">Channel</span> - Historical Graph (31-day Pacific, through yesterday)
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="loadingGraphMessage" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-3 text-muted">Loading chart data...</p>
                    </div>
                    <div id="dataInfoMessage" class="alert alert-info" style="display: none; margin-bottom: 15px;">
                        <i class="fas fa-info-circle me-2"></i>
                        <span id="dataPointsInfo"></span>
                    </div>
                    <div id="historyGraphContainer" style="width: 100%; height: 550px; display: none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Clicks Breakdown Modal -->
    <div class="modal fade" id="clicksBreakdownModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-md modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-mouse-pointer me-2"></i>
                        <span id="clicksModalChannelName">Channel</span> - Advertising Breakdown
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Type</th>
                                    <th class="text-end">Ad Clicks</th>
                                </tr>
                            </thead>
                            <tbody id="clicksBreakdownTableBody">
                                <tr>
                                    <td colspan="2" class="text-center">Loading...</td>
                                </tr>
                            </tbody>
                            <tfoot class="table-secondary">
                                <tr>
                                    <th>Total</th>
                                    <th class="text-end" id="clicksBreakdownTotalClicks">0</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Ad Sales Breakdown Modal -->
    <div class="modal fade" id="adSalesBreakdownModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-dollar-sign me-2"></i>
                        <span id="salesModalChannelName">Channel</span> - Ad Sales Breakdown
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Type</th>
                                    <th class="text-end">Ad Sales</th>
                                    <th class="text-end">ACOS</th>
                                    <th class="text-end">TACOS</th>
                                </tr>
                            </thead>
                            <tbody id="adSalesBreakdownTableBody">
                                <tr>
                                    <td colspan="4" class="text-center">Loading...</td>
                                </tr>
                            </tbody>
                            <tfoot class="table-secondary">
                                <tr>
                                    <th>Total</th>
                                    <th class="text-end" id="adSalesBreakdownTotalSales">$0</th>
                                    <th class="text-end" id="adSalesBreakdownTotalAcos">-</th>
                                    <th class="text-end" id="adSalesBreakdownTotalTacos">-</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- CVR Breakdown Modal -->
    <div class="modal fade" id="cvrBreakdownModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-md modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-chart-line me-2"></i>
                        <span id="cvrModalChannelName">Channel</span> - Ads CVR Breakdown
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Type</th>
                                    <th class="text-end">Ads CVR</th>
                                </tr>
                            </thead>
                            <tbody id="cvrBreakdownTableBody">
                                <tr>
                                    <td colspan="2" class="text-center">Loading...</td>
                                </tr>
                            </tbody>
                            <tfoot class="table-secondary">
                                <tr>
                                    <th>Total</th>
                                    <th class="text-end" id="cvrBreakdownTotalCvr">-</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Ad Breakdown Chart Modal -->
    <div class="modal fade p-0" id="adBreakdownChartModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog shadow-none m-0 mx-0">
            <div class="modal-content" style="overflow: hidden;">
                <div class="modal-header bg-info text-white py-1 px-3">
                    <h6 class="modal-title mb-0" style="font-size: 13px;">
                        <i class="fas fa-chart-area me-1"></i>
                        <span id="adChartModalTitle">Ad Breakdown - Rolling window</span>
                    </h6>
                    <div class="d-flex align-items-center gap-2">
                        <select id="adChartRangeSelect" class="form-select form-select-sm bg-white" style="width: 110px; height: 26px; font-size: 11px; padding: 1px 8px;">
                            <option value="7">7 Days</option>
                            <option value="30" selected>30 Days</option>
                            <option value="31" >31 Days</option>
                            <option value="32">32 Days</option>
                            <option value="35">35 Days</option>
                            <option value="60">60 Days</option>
                            <option value="90">90 Days</option>
                            <option value="0">Lifetime</option>
                        </select>
                        <button type="button" class="btn-close btn-close-white" style="font-size: 10px;" data-bs-dismiss="modal"></button>
                    </div>
                </div>
                <div class="modal-body p-2">
                    <div id="adBreakdownChartContainer" style="height: 34vh; min-height: 220px; display: flex; align-items: stretch;">
                        <div style="flex: 1; min-width: 0; position: relative;">
                            <canvas id="adBreakdownChart"></canvas>
                        </div>
                        <div id="adChartRefPanel" style="width: 118px; flex-shrink: 0; display: flex; flex-direction: column; justify-content: center; gap: 8px; padding: 6px 8px; border-left: 1px solid #e9ecef; background: #f8f9fa; border-radius: 0 4px 4px 0; overflow: auto;">
                            <div style="text-align: center;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #dc3545; margin-bottom: 1px;">Highest</div>
                                <div id="adChartHighest" style="font-size: 13px; font-weight: 700; color: #dc3545; white-space: nowrap;">-</div>
                            </div>
                            <div style="text-align: center; border-top: 1px dashed #adb5bd; border-bottom: 1px dashed #adb5bd; padding: 4px 0;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #6c757d; margin-bottom: 1px;">Median</div>
                                <div id="adChartMedian" style="font-size: 13px; font-weight: 700; color: #6c757d; white-space: nowrap;">-</div>
                            </div>
                            <div style="text-align: center;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #198754; margin-bottom: 1px;">Lowest</div>
                                <div id="adChartLowest" style="font-size: 13px; font-weight: 700; color: #198754; white-space: nowrap;">-</div>
                            </div>
                            <div id="adChartGrowthWrap" style="display:none; flex-shrink: 0; text-align: center; border-top: 1px dashed #adb5bd; padding-top: 4px;">
                                <div id="adChartGrowthLabel" style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #6c757d; margin-bottom: 1px;">L30 %</div>
                                <div id="adChartGrowth" style="font-size: 13px; font-weight: 700; color: #6c757d; white-space: nowrap;">-</div>
                            </div>
                        </div>
                    </div>
                    <div id="adChartLoading" class="text-center py-3" style="display: none;">
                        <div class="spinner-border spinner-border-sm text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-1 text-muted small mb-0">Loading chart data...</p>
                    </div>
                    <div id="adChartNoData" class="text-center py-3" style="display: none;">
                        <i class="fas fa-exclamation-circle text-warning fa-2x mb-2"></i>
                        <p class="text-muted small mb-0">Daily data is not available for this channel/ad type.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Missing Ads Breakdown Modal -->
    <div class="modal fade" id="missingAdsBreakdownModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-md modal-dialog-centered shadow-none">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <span id="missingModalChannelName">Channel</span> - L Missing Ads Breakdown
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Type</th>
                                    <th class="text-end">L Missing Ads</th>
                                </tr>
                            </thead>
                            <tbody id="missingAdsBreakdownTableBody">
                                <tr>
                                    <td colspan="2" class="text-center">Loading...</td>
                                </tr>
                            </tbody>
                            <tfoot class="table-secondary">
                                <tr>
                                    <th>Total</th>
                                    <th class="text-end" id="missingAdsBreakdownTotal">0</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="ammTaskModal" tabindex="-1" aria-labelledby="ammTaskLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title mb-0" id="ammTaskLabel">Assign Task</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="amm-task-form">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="amm-task-group">Group</label>
                            <input type="text" class="form-control" id="amm-task-group" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="amm-task-title">Task <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="amm-task-title" placeholder="Enter Task" maxlength="1000" autocomplete="off" required>
                        </div>
                        <div class="mb-0">
                            <label class="form-label fw-semibold" for="amm-task-assignee-search">Assign to <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="amm-task-assignee-search" list="amm-task-assignee-list" placeholder="Search user…" autocomplete="off" required>
                                <datalist id="amm-task-assignee-list"></datalist>
                                <button type="submit" class="btn btn-primary" id="amm-task-assign">Assign</button>
                            </div>
                        </div>
                        <p class="text-danger small mb-0 mt-2 d-none" id="amm-task-error"></p>
                        <p class="text-success small mb-0 mt-2 d-none" id="amm-task-success"></p>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://www.gstatic.com/charts/loader.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
@endsection

@section('script-bottom')
    <script>
        let table = null;
        var channelMetricDotTrendsUrl = "{{ url('channel-metric-dot-trends') }}";
        var ammInvPiesUrl = "{{ route('all.marketplace.master.inv.pies') }}";
        var dotTrendsLoadedOnce = false;
        var DEFAULT_DOT_GRAY = '#6c757d';
        var lastInventoryPies = null;
        var ammInvLpPieChart = null;
        var ammInvSpPieChart = null;
        var ammInvBlockSpPieChart = null;
        var ammInvHistChart = null;
        var ammInvLiveSlices = [];
        var ammInvLiveHistory = [];

        function snapshotChannelKey(name) {
            var k = (name || '').toString().trim().toLowerCase().replace(/[^a-z0-9]/g, '');
            var aliases = {
                ebay2: 'ebaytwo',
                ebay3: 'ebaythree',
                ebaythree: 'ebaythree',
                shopify: 'shopifyb2c',
                tiktok: 'tiktokshop',
                tiktok2: 'tiktokshop2',
                bestbuy: 'bestbuyusa',
                facebookmarketplace: 'fbmarketplace'
            };
            return aliases[k] || k;
        }

        // Chart points follow the previous day (tooltip "vs Yesterday"):
        // green = up, red = down. Table cell text stays on absolute bands.
        // ACOS & TAcos % invert (lower is better).
        function metricChartDotColors(values, isInverted, metric) {
            var gray = '#6c757d';
            var green = '#28a745';
            var red = '#dc3545';
            var eps = (metric === 'cvr' || metric === 'ads_cvr' || metric === 'gprofit' || metric === 'groi'
                || metric === 'npft' || metric === 'p_npft' || metric === 'p_groi_pct' || metric === 'p_nroi_pct' || metric === 'y_npft_pct' || metric === 'y_groi_pct' || metric === 'y_nroi_pct'
                || metric === 'nroi' || metric === 'ads_pct' || metric === 'acos') ? 0.005 : 0.01;
            return values.map(function(v, i) {
                if (i === 0) return gray;
                var prev = values[i - 1];
                if (Math.abs(v - prev) <= eps) return gray;
                if (isInverted) return v < prev ? green : red;
                return v > prev ? green : red;
            });
        }

        var METRIC_DIFF_MONEY = {
            l30_sales: 1, y_sales: 1, y_pft: 1, y_npft_amt: 1, p_sales: 1, p_npft_amt: 1, ad_spend: 1, pft: 1, ad_sales: 1,
            inv_at_lp: 1, inv_at_sp: 1, inventory: 1, l60_sales: 1
        };

        function projectedSalesFromL7(l7) {
            return (parseNumber(l7) / 7) * 30;
        }
        function pSalesVsL30Pct(pSales, l30Sales) {
            const p = parseNumber(pSales);
            const s = parseNumber(l30Sales);
            if (!isFinite(p) || !isFinite(s) || s <= 0) return null;
            return ((p - s) / s) * 100;
        }
            function todayVsYesterdayColor(today, yesterday) {
                const t = parseNumber(today);
                const y = parseNumber(yesterday);
                if (!isFinite(t) || !isFinite(y)) return DEFAULT_DOT_GRAY;
                if (Math.abs(t - y) < 0.5) return DEFAULT_DOT_GRAY;
                return t > y ? '#28a745' : '#dc3545';
            }
            function todayVsYesterdayTitle(today, yesterday) {
                const t = parseNumber(today);
                const y = parseNumber(yesterday);
                if (!isFinite(t) || !isFinite(y)) return 'Today vs yesterday — click for chart';
                const diff = t - y;
                if (Math.abs(diff) < 0.5) return 'Today in line with yesterday — click for chart';
                const sign = diff > 0 ? '+' : '−';
                return 'Today vs yesterday: ' + sign + '$' + Math.abs(Math.round(diff)).toLocaleString('en-US') + ' — click for chart';
            }
            function pSalesVsL30Color(pSales, l30Sales) {
            const p = parseNumber(pSales);
            const s = parseNumber(l30Sales);
            if (!isFinite(p) || !isFinite(s)) return DEFAULT_DOT_GRAY;
            if (s <= 0) return p > 0 ? '#28a745' : DEFAULT_DOT_GRAY;
            const diff = p - s;
            if (Math.abs(diff) < 0.5) return DEFAULT_DOT_GRAY;
            return diff > 0 ? '#28a745' : '#dc3545';
        }
        function formatPSalesVsL30PctHtml(pct) {
            if (pct == null || !isFinite(pct)) return '';
            if (Math.abs(pct) < 0.1) {
                return '<span class="p-sales-vs-sales-pct" style="color:#6c757d;">0%</span>';
            }
            const isUp = pct > 0;
            const color = isUp ? '#198754' : '#dc3545';
            const sign = isUp ? '+' : '−';
            return `<span class="p-sales-vs-sales-pct" style="color:${color};">${sign}${Math.abs(pct).toFixed(0)}%</span>`;
        }
        function pSalesVsL30Title(pct) {
            if (pct == null || !isFinite(pct)) return 'P-Sales vs Sales — click for chart';
            if (Math.abs(pct) < 0.1) return 'P-Sales in line with Sales — click for chart';
            const dir = pct > 0 ? 'above' : 'below';
            return Math.abs(pct).toFixed(0) + '% ' + dir + ' Sales — click for chart';
        }
        function pGrowthFromRow(row) {
            const l7 = parseNumber(row['L7 Sales'] || 0);
            const pSales = parseNumber(row['P-Sales'] != null && row['P-Sales'] !== ''
                ? row['P-Sales']
                : projectedSalesFromL7(l7));
            return pSalesVsL30Pct(pSales, row['L30 Sales'] || 0);
        }
        function pNpftAmtVsNpftPct(pAmt, npftAmt) {
            const p = parseNumber(pAmt);
            const s = parseNumber(npftAmt);
            if (!isFinite(p) || !isFinite(s) || Math.abs(s) < 0.5) return null;
            return ((p - s) / Math.abs(s)) * 100;
        }
        function pNpftAmtVsNpftColor(pAmt, npftAmt) {
            const p = parseNumber(pAmt);
            const s = parseNumber(npftAmt);
            if (!isFinite(p) || !isFinite(s)) return DEFAULT_DOT_GRAY;
            if (Math.abs(s) < 0.5) return p > 0.5 ? '#28a745' : (p < -0.5 ? '#dc3545' : DEFAULT_DOT_GRAY);
            const diff = p - s;
            if (Math.abs(diff) < 0.5) return DEFAULT_DOT_GRAY;
            return diff > 0 ? '#28a745' : '#dc3545';
        }
        function pNpftAmtVsNpftTitle(pct) {
            if (pct == null || !isFinite(pct)) return 'P NPFT $ vs NPFT $ — click for chart';
            if (Math.abs(pct) < 0.1) return 'P NPFT $ in line with NPFT $ — click for chart';
            const dir = pct > 0 ? 'above' : 'below';
            return Math.abs(pct).toFixed(0) + '% ' + dir + ' NPFT $ — click for chart';
        }
        function projectedNpftFromL7(l7, gprofitPercent, adSpend) {
            const pSales = projectedSalesFromL7(l7);
            if (!pSales || pSales <= 0) return null;
            return parseNumber(gprofitPercent) - (parseNumber(adSpend) / pSales) * 100;
        }
        function pSalesFromRow(row) {
            return projectedSalesFromL7(row['L7 Sales'] || 0);
        }
        function pViewsFromRow(row) {
            const views = parseNumber(row['Total Views'] || 0);
            if (views <= 0) return 0;
            const l30 = parseNumber(row['L30 Sales'] || 0);
            const pSales = pSalesFromRow(row);
            if (l30 > 0 && pSales > 0) return views * (pSales / l30);
            return views;
        }
        function pOrdersFromRow(row) {
            const orders = parseNumber(row['L30 Orders'] || 0);
            if (orders <= 0) return 0;
            const l30 = parseNumber(row['L30 Sales'] || 0);
            const pSales = pSalesFromRow(row);
            if (l30 > 0 && pSales > 0) return orders * (pSales / l30);
            return 0;
        }
        function rowAdSpendFromRow(row) {
            let spend = parseNumber(row['Total Ad Spend'] || 0);
            const chName = (row['Channel '] || '').trim().toLowerCase();
            if (spend <= 0 && chName.indexOf('reverb') !== -1) {
                const bumpPct = parseNumber(row['Ads%'] != null ? row['Ads%'] : row['TACOS']) || 0;
                const l30 = parseNumber(row['L30 Sales'] || 0);
                if (bumpPct > 0 && l30 > 0) spend = (bumpPct / 100) * l30;
            }
            return spend;
        }
        function pCogsFromRow(row) {
            const pSales = pSalesFromRow(row);
            if (!pSales) return 0;
            const l30 = parseNumber(row['L30 Sales'] || 0);
            const cogs = parseNumber(row['cogs'] || 0);
            if (l30 > 0 && cogs > 0) return cogs * (pSales / l30);
            const gp = parseNumber(row['Gprofit%'] || 0);
            return pSales * Math.max(0, 1 - gp / 100);
        }
        function pGrossPftFromRow(row) {
            return (pSalesFromRow(row) * parseNumber(row['Gprofit%'] || 0)) / 100;
        }
        function pGroiPctFromRow(row) {
            const pSales = pSalesFromRow(row);
            if (!pSales) return null;
            const pCogs = pCogsFromRow(row);
            if (pCogs > 0) return (pGrossPftFromRow(row) / pCogs) * 100;
            const groi = parseNumber(row['G Roi'] || 0);
            return groi || null;
        }
        function pNpftPctFromRow(row) {
            const l7 = parseNumber(row['L7 Sales'] || 0);
            if (!l7) return null;
            return projectedNpftFromL7(l7, parseNumber(row['Gprofit%'] || 0), rowAdSpendFromRow(row));
        }
        function pNetPftFromRow(row) {
            const l7 = parseNumber(row['L7 Sales'] || 0);
            if (!l7) return 0;
            return pGrossPftFromRow(row) - rowAdSpendFromRow(row);
        }
        function rowNetPftFromRow(row) {
            const l30 = parseNumber(row['L30 Sales'] || 0);
            const gp = parseNumber(row['Gprofit%'] || 0);
            return (l30 * gp) / 100 - rowAdSpendFromRow(row);
        }
        // Yesterday's own measured gross profit / COGS / sales, written by
        // channel:calculate-data, plus the spend the server derived from the channel's
        // Ads%. Null until that runs, then we fall back to the L30 rates below.
        // Deriving Y% from the L30 rates cancels the Y Sales factor
        // (Y×GP ÷ COGS×Y/L30 reduces to L30 GPFT$ ÷ L30 COGS), which made Y GROI% /
        // YNPFT% / YNROI% identical to G Roi / N PFT / N ROI on every row.
        function yMeasuredFromRow(row) {
            if (!row) return null;
            const pft = row['Y GPFT $'];
            const cogs = row['Y COGS'];
            if (pft == null || cogs == null) return null;
            return {
                pft: parseNumber(pft || 0),
                cogs: parseNumber(cogs || 0),
                spend: parseNumber(row['Y Spend'] || 0),
                sales: parseNumber(row['Y Day Sales'] || 0)
            };
        }
        function yGrossPftFromRow(row) {
            const m = yMeasuredFromRow(row);
            if (m) return m.pft;
            return (parseNumber(row['Y Sales'] || 0) * parseNumber(row['Gprofit%'] || 0)) / 100;
        }
        function yNetPftFromRow(row) {
            const m = yMeasuredFromRow(row);
            if (m) return m.pft - m.spend;
            return (parseNumber(row['Y Sales'] || 0) * parseNumber(row['N PFT'] || 0)) / 100;
        }
        function yCogsFromRow(row) {
            const m = yMeasuredFromRow(row);
            if (m) return m.cogs;
            const ySales = parseNumber(row['Y Sales'] || 0);
            if (!ySales) return 0;
            const l30 = parseNumber(row['L30 Sales'] || 0);
            const cogs = parseNumber(row['cogs'] || 0);
            if (l30 > 0 && cogs > 0) return cogs * (ySales / l30);
            const gp = parseNumber(row['Gprofit%'] || 0);
            return ySales * Math.max(0, 1 - gp / 100);
        }
        function yGroiPctFromRow(row) {
            const m = yMeasuredFromRow(row);
            if (m) return m.cogs > 0 ? (m.pft / m.cogs) * 100 : null;
            const ySales = parseNumber(row['Y Sales'] || 0);
            if (!ySales) return null;
            const yCogs = yCogsFromRow(row);
            if (yCogs > 0) return (yGrossPftFromRow(row) / yCogs) * 100;
            const groi = parseNumber(row['G Roi'] || 0);
            return groi || null;
        }
        function yNpftPctFromRow(row) {
            const m = yMeasuredFromRow(row);
            if (m) return m.sales > 0 ? ((m.pft - m.spend) / m.sales) * 100 : null;
            const ySales = parseNumber(row['Y Sales'] || 0);
            if (!ySales) return null;
            return parseNumber(row['N PFT'] || 0);
        }
        function yAdsPartsFromRow(row) {
            const m = yMeasuredFromRow(row);
            if (m && m.sales > 0) {
                return { spend: m.spend, sales: m.sales };
            }
            const ySales = parseNumber(row['Y Sales'] || 0);
            if (ySales <= 0) return { spend: 0, sales: 0 };
            const ads = parseNumber(row['Ads%'] != null ? row['Ads%'] : (row['TACOS'] || 0));
            return { spend: ySales * ads / 100, sales: ySales };
        }
        function todayPartsFromRow(row) {
            const sales = parseNumber(row['Today Sales'] || 0);
            if (sales <= 0) return { sales: 0, gross: 0, net: 0, cogs: 0, spend: 0, views: 0, orders: 0 };
            const gp = parseNumber(row['Gprofit%'] || 0);
            const npft = parseNumber(row['N PFT'] || 0);
            const ads = parseNumber(row['Ads%'] != null ? row['Ads%'] : (row['TACOS'] || 0));
            const l30 = parseNumber(row['L30 Sales'] || 0);
            const cogsL30 = parseNumber(row['cogs'] || 0);
            const views = parseNumber(row['Total Views'] || 0);
            const l30Orders = parseNumber(row['L30 Orders'] || 0);
            const cogs = (l30 > 0 && cogsL30 > 0)
                ? cogsL30 * (sales / l30)
                : sales * Math.max(0, 1 - gp / 100);
            const todayViews = (l30 > 0 && views > 0) ? views * (sales / l30) : 0;
            const todayOrders = (l30 > 0 && l30Orders > 0) ? l30Orders * (sales / l30) : 0;
            return {
                sales: sales,
                gross: sales * gp / 100,
                net: sales * npft / 100,
                cogs: cogs,
                spend: sales * ads / 100,
                views: todayViews,
                orders: todayOrders
            };
        }
        function listingCvrFromRow(row) {
            const channel = (row['Channel '] || '').trim();
            const views = parseNumber(row['Total Views'] || 0);
            const serverCvr = row['CVR'];
            const hasServerCvr = serverCvr !== undefined && serverCvr !== null && serverCvr !== '';
            const key = snapshotChannelKey(channel);
            if (key === 'amazon' || key === 'reverb') {
                if (views <= 0) return null;
                const units = parseNumber(row['Qty'] || 0) || parseNumber(row['L30 Orders'] || 0);
                return (units / views) * 100;
            }
            if (hasServerCvr) return parseNumber(serverCvr);
            if (views <= 0) return null;
            return (parseNumber(row['Qty'] || 0) / views) * 100;
        }
        function tCvrPartsFromRow(row) {
            const sales = parseNumber(row['Today Sales'] || 0);
            const l30 = parseNumber(row['L30 Sales'] || 0);
            if (sales <= 0 || l30 <= 0) return { views: 0, units: 0 };
            const ratio = sales / l30;
            const views = parseNumber(row['Total Views'] || 0) * ratio;
            const key = snapshotChannelKey(row['Channel '] || '');
            let units;
            if (key === 'amazon' || key === 'reverb') {
                units = (parseNumber(row['Qty'] || 0) || parseNumber(row['L30 Orders'] || 0)) * ratio;
            } else if (row['CVR'] !== undefined && row['CVR'] !== null && row['CVR'] !== '') {
                units = (parseNumber(row['CVR']) / 100) * views;
            } else {
                units = parseNumber(row['Qty'] || 0) * ratio;
            }
            return { views: views, units: units };
        }
        function tCvrFromRow(row) {
            if (parseNumber(row['Today Sales'] || 0) <= 0) return null;
            const parts = tCvrPartsFromRow(row);
            if (parts.views <= 0) return listingCvrFromRow(row);
            return (parts.units / parts.views) * 100;
        }
        function formatCvrCell(value, color) {
            if (value === null || value === undefined || value === '' || !isFinite(Number(value))) {
                return '<span style="color:#adb5bd;">—</span>';
            }
            const pct = Math.round(parseNumber(value) * 100) / 100;
            const style = color ? ('font-weight:600;color:' + color + ';') : 'font-weight:600;';
            return `<span style="${style}">${pct.toFixed(2)}%</span>`;
        }
        function belowL30AvgColor(value, l30Total) {
            return parseNumber(value) < (parseNumber(l30Total) / 30) ? '#dc3545' : '#166534';
        }
        function belowL30RateColor(value, l30Rate) {
            if (value === null || value === undefined || value === '' || !isFinite(Number(value))) return '#6c757d';
            if (l30Rate === null || l30Rate === undefined || !isFinite(Number(l30Rate))) return '#166534';
            return parseNumber(value) < parseNumber(l30Rate) ? '#dc3545' : '#166534';
        }
        function ammHistoryDot(channel, metric) {
            const colorFn = (typeof getMetricDotColor === 'function') ? getMetricDotColor : window.getMetricDotColor;
            const dotColor = (typeof colorFn === 'function') ? colorFn(channel, metric) : '#6c757d';
            return `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="${metric}" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View Chart"></i>`;
        }
        function ammOpenHistoryDot(e, cell) {
            if (!e.target.classList.contains('metric-chart-icon')) return;
            e.stopPropagation();
            const openChart = (typeof showMetricChart === 'function') ? showMetricChart : window.showMetricChart;
            if (typeof openChart === 'function') {
                openChart($(e.target).data('channel'), $(e.target).data('metric'), null);
            }
        }
        var METRIC_DIFF_PCT = {
            gprofit: 1, groi: 1, npft: 1, p_npft: 1, p_groi_pct: 1, p_nroi_pct: 1, y_npft_pct: 1, y_groi_pct: 1, y_nroi_pct: 1, nroi: 1, ads_pct: 1, acos: 1, cvr: 1, ads_cvr: 1
        };

        function formatMetricDiffText(metric, diff) {
            if (diff == null || isNaN(diff)) return '';
            if (Math.abs(diff) < 1e-9) return '';
            var sign = diff > 0 ? '+' : '−';
            var abs = Math.abs(diff);
            var text;
            if (metric === 'tat') {
                text = abs.toFixed(2);
            } else if (METRIC_DIFF_MONEY[metric]) {
                if (abs >= 1000000) text = '$' + (abs / 1e6).toFixed(1).replace(/\.0$/, '') + 'M';
                else if (abs >= 1000) text = '$' + (abs / 1000).toFixed(1).replace(/\.0$/, '') + 'K';
                else text = '$' + Math.round(abs).toLocaleString('en-US');
            } else if (METRIC_DIFF_PCT[metric]) {
                text = (abs >= 10 ? abs.toFixed(0) : abs.toFixed(1)) + '%';
            } else if (abs >= 1000) {
                text = (abs / 1000).toFixed(1).replace(/\.0$/, '') + 'K';
            } else {
                text = Math.round(abs).toLocaleString('en-US');
            }
            return sign + text;
        }

        // Toast notification helper
        function showToast(type, message) {
            const toast = $(`
                <div class="toast align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0" role="alert">
                    <div class="d-flex">
                        <div class="toast-body">${message}</div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                </div>
            `);
            $('.toast-container').append(toast);
            const bsToast = new bootstrap.Toast(toast[0]);
            bsToast.show();
            setTimeout(() => toast.remove(), 3000);
        }

        // Parse number helper
        function parseNumber(value) {
            if (value === null || value === undefined || value === '' || value === 'N/A') return 0;
            if (typeof value === 'number') return value;
            const cleaned = String(value).replace(/[^0-9.-]/g, '');
            return parseFloat(cleaned) || 0;
        }

        function ammPctNumber(value) {
            if (value === null || value === undefined || value === '' || value === 'N/A') return null;
            if (typeof value === 'number') return isFinite(value) ? value : null;
            const n = parseFloat(String(value).replace(/[^0-9.-]/g, ''));
            return isFinite(n) ? n : null;
        }
        function ammAdsPctBandColor(value) {
            const v = ammPctNumber(value);
            if (v == null) return null;
            if (v < 5) return '#e83e8c';
            if (v <= 10) return '#28a745';
            return '#a00211';
        }
        function ammAdsPctBandStyle(value) {
            const v = ammPctNumber(value);
            if (v == null) return '';
            if (v < 5) return 'color:#e83e8c;';
            if (v <= 10) return 'color:#28a745;';
            return 'color:#a00211;';
        }
        // Official MetricPctColors: NROI and NPFT are different metrics, but they
        // share the same red / yellow / green / pink language (no extra NPFT blue).
        function ammMetricKind(metric) {
            switch (metric) {
                case 'nroi':
                case 'y_nroi_pct':
                case 'p_nroi_pct': return 'nroi';
                case 'groi':
                case 'y_groi_pct':
                case 'p_groi_pct': return 'groi';
                case 'npft':
                case 'p_npft':
                case 'y_npft_pct': return 'npft';
                case 'gprofit': return 'gpft';
                default: return null;
            }
        }
        function ammFallbackBandColor(kind, value) {
            const v = ammPctNumber(value);
            if (v == null) return null;
            if (kind === 'nroi') {
                if (v < 40) return '#dc3545';
                if (v < 70) return '#ffc107';
                if (v < 125) return '#28a745';
                return '#e83e8c';
            }
            if (kind === 'groi') {
                if (v < 60) return '#dc3545';
                if (v < 90) return '#ffc107';
                if (v < 150) return '#28a745';
                return '#e83e8c';
            }
            if (kind === 'npft') {
                if (v <= 10) return '#dc3545';
                if (v < 20) return '#ffc107';
                if (v < 33) return '#28a745';
                return '#e83e8c';
            }
            if (kind === 'gpft') {
                if (v <= 20) return '#dc3545';
                if (v < 30) return '#ffc107';
                if (v < 43) return '#28a745';
                return '#4e0dab';
            }
            return null;
        }
        function ammMetricCellColor(metric, value) {
            if (metric === 'ads_pct') return ammAdsPctBandColor(value);
            const kind = ammMetricKind(metric);
            if (!kind) return null;
            if (window.MetricPctColors) {
                return MetricPctColors.colorFor(kind, value) || null;
            }
            return ammFallbackBandColor(kind, value);
        }
        function ammMetricCellStyle(metric, value) {
            if (metric === 'ads_pct') return ammAdsPctBandStyle(value);
            const kind = ammMetricKind(metric);
            if (!kind) return '';
            let band = null;
            let st = '';
            if (window.MetricPctColors) {
                band = MetricPctColors.bandFor(kind, value);
                st = MetricPctColors.styleFor(kind, value) || '';
            } else {
                const c = ammFallbackBandColor(kind, value);
                if (!c) return '';
                if (c === '#ffc107') band = 'yellow';
                else st = 'color:' + c + ';font-weight:700;';
            }
            if (band === 'yellow') {
                return 'background:#ffc107;color:black;padding:4px 8px;border-radius:4px;';
            }
            return st;
        }

        function channelAdsPageUrl(name) {
            const key = String(name || '').toLowerCase().replace(/[\s\-&/,']/g, '');
            const map = {
                amazon: '/amazon-ads/all',
                amazonfba: '/amazon-ads/all',
                ebay: '/ebay/campaign-ads',
                ebay1: '/ebay/campaign-ads',
                ebaytwo: '/ebay2/campaign-ads',
                ebay2: '/ebay2/campaign-ads',
                ebaythree: '/ebay3/campaign-ads',
                ebay3: '/ebay3/campaign-ads',
                temu: '/temu/ads',
                temu1: '/temu/ads',
                temu2: '/temu2/ads',
                shopify: '/shopify-ads-master',
                shopifyb2c: '/shopify-ads-master',
                tiktokshop: '/tiktok-1-ads-raw-data',
                tiktok: '/tiktok-1-ads-raw-data',
                tiktok1: '/tiktok-1-ads-raw-data',
                tiktokshop1: '/tiktok-1-ads-raw-data',
                tiktok2: '/tiktok-gmv-ads-raw-data',
                tiktokshop2: '/tiktok-gmv-ads-raw-data',
                walmart: '/walmart/running/ads',
                instagramshop: '/instagram-ads',
                fbmarketplace: '/facebook-ads',
                facebookmarketplace: '/facebook-ads',
                fbshop: '/facebook-ads',
            };
            return map[key] || '';
        }

        // Yesterday's date formatted in America/Los_Angeles (e.g. "Jun 14"). The current PT
        // day is still in progress so its rolling-window data isn't final yet — every "data
        // through …" label we surface points at the last COMPLETED Pacific day instead.
        function caLastCompletedMdy() {
            try {
                const fmt = new Intl.DateTimeFormat('en-US', {
                    timeZone: 'America/Los_Angeles',
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                });
                const parts = fmt.formatToParts(new Date());
                const y = parseInt(parts.find(p => p.type === 'year').value, 10);
                const mShort = parts.find(p => p.type === 'month').value;
                const d = parseInt(parts.find(p => p.type === 'day').value, 10);
                // Build a UTC date at PT midnight today, subtract one day, then format short.
                const monthIdx = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'].indexOf(mShort);
                const dt = new Date(Date.UTC(y, monthIdx, d));
                dt.setUTCDate(dt.getUTCDate() - 1);
                return new Intl.DateTimeFormat('en-US', { month: 'short', day: 'numeric', timeZone: 'UTC' }).format(dt);
            } catch (e) {
                const d = new Date();
                d.setDate(d.getDate() - 1);
                return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
            }
        }
        // Normalize percentage for display: backend may send 0.15 or 15; always show as 0-100 scale with %
        function asPercent(value) {
            const n = parseNumber(value);
            if (!n) return 0;
            if (n > 0 && n <= 1) return n * 100; // decimal form
            return n;
        }

        // Track main Ad columns visibility (from Total Ad Spend to Missing Ads)
        let adColumnsVisible = false;
        const mainAdColumnFields = ['Total Ad Spend'];

        function toggleMainAdColumns() {
            adColumnsVisible = !adColumnsVisible;
            mainAdColumnFields.forEach(field => {
                if (adColumnsVisible) {
                    table.showColumn(field);
                } else {
                    table.hideColumn(field);
                }
            });

            // Update button text
            const btn = document.getElementById('toggleAdColumnsBtn');
            if (adColumnsVisible) {
                btn.innerHTML = '<i class="fas fa-ad"></i> Hide Ads Data';
                btn.classList.remove('btn-outline-secondary');
                btn.classList.add('btn-secondary');
            } else {
                btn.innerHTML = '<i class="fas fa-ad"></i> Show Ads Data';
                btn.classList.remove('btn-secondary');
                btn.classList.add('btn-outline-secondary');
            }
        }

        // Track Ad Spend breakdown columns visibility
        let adSpendBreakdownVisible = false;
        const adSpendBreakdownFields = [];

        function toggleAdSpendBreakdownColumns() {
            adSpendBreakdownVisible = !adSpendBreakdownVisible;
            if (adSpendBreakdownVisible) {
                adSpendBreakdownFields.forEach((field, i) => {
                    table.showColumn(field);
                    const afterField = i === 0 ? 'Total Ad Spend' : adSpendBreakdownFields[i - 1];
                    table.moveColumn(field, afterField, true);
                });
            } else {
                adSpendBreakdownFields.forEach(field => table.hideColumn(field));
            }
        }

        // Track AD CLICKS breakdown columns visibility
        let clicksBreakdownVisible = false;
        const clicksBreakdownFields = [];

        function toggleClicksBreakdownColumns() {
            clicksBreakdownVisible = !clicksBreakdownVisible;
            if (clicksBreakdownVisible) {
                clicksBreakdownFields.forEach((field, i) => {
                    table.showColumn(field);
                    const afterField = i === 0 ? 'clicks' : clicksBreakdownFields[i - 1];
                    table.moveColumn(field, afterField, true);
                });
            } else {
                clicksBreakdownFields.forEach(field => table.hideColumn(field));
            }
        }

        // Track AD SALES breakdown columns visibility
        let adSalesBreakdownVisible = false;
        const adSalesBreakdownFields = [];

        function toggleAdSalesBreakdownColumns() {
            adSalesBreakdownVisible = !adSalesBreakdownVisible;
            adSalesBreakdownFields.forEach(field => {
                if (adSalesBreakdownVisible) {
                    table.showColumn(field);
                } else {
                    table.hideColumn(field);
                }
            });
        }

        // Track AD SOLD breakdown columns visibility
        let adSoldBreakdownVisible = false;
        const adSoldBreakdownFields = [];

        function toggleAdSoldBreakdownColumns() {
            adSoldBreakdownVisible = !adSoldBreakdownVisible;
            adSoldBreakdownFields.forEach(field => {
                if (adSoldBreakdownVisible) {
                    table.showColumn(field);
                } else {
                    table.hideColumn(field);
                }
            });
        }

        // Track ACOS breakdown columns visibility
        let acosBreakdownVisible = false;
        const acosBreakdownFields = [];

        function toggleAcosBreakdownColumns() {
            acosBreakdownVisible = !acosBreakdownVisible;
            if (acosBreakdownVisible) {
                acosBreakdownFields.forEach((field, i) => {
                    table.showColumn(field);
                    const afterField = i === 0 ? 'ACOS' : acosBreakdownFields[i - 1];
                    table.moveColumn(field, afterField, true);
                });
            } else {
                acosBreakdownFields.forEach(field => table.hideColumn(field));
            }
        }

        // Track AD CVR breakdown columns visibility
        let cvrBreakdownVisible = false;
        const cvrBreakdownFields = [];

        function toggleCvrBreakdownColumns() {
            cvrBreakdownVisible = !cvrBreakdownVisible;
            if (cvrBreakdownVisible) {
                cvrBreakdownFields.forEach((field, i) => {
                    table.showColumn(field);
                    const afterField = i === 0 ? 'Ads CVR' : cvrBreakdownFields[i - 1];
                    table.moveColumn(field, afterField, true);
                });
            } else {
                cvrBreakdownFields.forEach(field => table.hideColumn(field));
            }
        }

        const AC_PRICING_FILTER_CHANNELS = ['Shein', 'Aliexpress', 'Faire', 'Reverb', 'TopDawg'];
        let acPricingCellHoverTimer = null;

        function acOpenPricingPageWithBadge(missingLink, badge) {
            if (!missingLink || !badge) return;
            const sep = missingLink.indexOf('?') >= 0 ? '&' : '?';
            window.open(missingLink + sep + 'badge=' + encodeURIComponent(badge), '_blank');
        }

        function shopifyB2cDailySalesPage(channel) {
            var key = (channel || '').toLowerCase().replace(/[\s\-&\/]/g, '');
            if (key === 'shopifyb2c' || key === 'shopify') {
                return '/shopify';
            }
            if (key === 'temu') {
                return '/temu-tabulator';
            }
            if (key === 'temu2') {
                return '/temu2-tabulator';
            }
            if (key === 'temu3' || key === 'temuthree') {
                return '/temu3-tabulator';
            }
            return '';
        }

        function acPricingHoverCellHtml(channel, chartMetric, filterBadge, valueHtml, extraStyle) {
            if (!AC_PRICING_FILTER_CHANNELS.includes(channel)) {
                return `<span style="${extraStyle || ''}">${valueHtml}</span>`;
            }
            return `<span class="ac-pricing-hover-cell" data-channel="${channel}" data-chart-metric="${chartMetric}" data-filter-badge="${filterBadge}" style="${extraStyle || ''}cursor:pointer;" title="Hover for trend · Click to filter on pricing page">${valueHtml}</span>`;
        }

        $(document).ready(function() {
            var lastDotColorByKey = {};
            var lastDotPairByKey = {};
            var chartSyncedAllMetrics = {};
            var invertedDotMetrics = ['acos', 'ads_pct', 'y_ads_pct', 't_ads', 'p_ads_pct', 'tat'];
            // These Y / Today dots start from a "vs L30 average" pair, but as soon as the badge's
            // graph series is known (prefetched on load or opened by click) the dot is re-synced
            // to the graph (last point vs the day before) — see syncAllBadgeDotWithChart.
            var chartDotSynced = {};
            var yVsL30DotMetrics = { y_sales: 1, y_orders: 1, y_npft_pct: 1, y_groi_pct: 1, y_nroi_pct: 1, y_ads_pct: 1, y_views: 1,
                // Today badges: today's pace vs the L30 baseline (daily average for counts, L30 % for ratios).
                t_orders: 1, t_npft: 1, t_groi: 1, t_nroi: 1, t_ads: 1, t_views: 1 };
            var yOrdersExact = null;
            var yOrdersByChannel = null;
            var yCvrByChannel = null;
            var ySalesFooterBelowAvg = false;
            var yOrdersFooterBelowAvg = false;
            var yViewsFooterBelowAvg = false;
            var yCvrFooterBelowAvg = false;
            var tOrdersFooterBelowAvg = false;
            var tViewsFooterBelowAvg = false;
            var tCvrFooterBelowAvg = false;
            var yViewsExact = null;
            var l30DailyOrders = null;
            var l30DailyViews = null;
            var ySalesAllChartPrefetch = null;
            var metricDotMetricKeys = ['missing_l','map','nmap','l60_sales','l60_orders','l30_sales','y_sales','today_sales','y_pft','y_npft_amt','p_sales','p_npft_amt','ad_spend','l30_orders','qty','groi','gprofit','ads_pct','nroi','npft','p_npft','p_groi_pct','p_nroi_pct','y_npft_pct','y_groi_pct','y_nroi_pct','pft','clicks','ad_sales','ad_sold','acos','ads_cvr','cvr','total_views','inv_at_lp','inv_at_sp','inventory','tat','reviews','rating'];
            var dotTrendsPrefetch = null;

            function getMetricDotColor(channelName, metricKey) {
                var k = snapshotChannelKey(channelName) + '_' + (metricKey || '');
                return lastDotColorByKey[k] || DEFAULT_DOT_GRAY;
            }
            window.getMetricDotColor = getMetricDotColor;
            function getMetricDiffAmount(channelName, metricKey) {
                var k = snapshotChannelKey(channelName) + '_' + (metricKey || '');
                var pair = lastDotPairByKey[k];
                if (!pair || pair[0] == null || pair[1] == null || isNaN(pair[0]) || isNaN(pair[1])) return null;
                return pair[1] - pair[0];
            }
            function applyDotTrendsMap(channels) {
                if (!channels) return;
                Object.keys(channels).forEach(function(channel) {
                    var metrics = channels[channel] || {};
                    var norm = snapshotChannelKey(channel);
                    Object.keys(metrics).forEach(function(metric) {
                        var pair = metrics[metric];
                        var v1 = pair && pair[0] != null ? parseFloat(pair[0]) : null;
                        var v2 = pair && pair[1] != null ? parseFloat(pair[1]) : null;
                        // Keep the pair taken from the graph's own series (see syncAllBadgeDotWithChart).
                        if (norm === 'all' && chartSyncedAllMetrics[metric]) return;
                        lastDotPairByKey[norm + '_' + metric] = [v1, v2];
                        lastDotColorByKey[norm + '_' + metric] = colorFromDotPair(v1, v2, metric);
                        delete dotFallbackKeys[norm + '_' + metric];
                    });
                });
            }
            // Y / T column dots with fewer than two saved history days have no "previous day"
            // pair, so they stayed gray. Until history exists, compare against the channel's
            // own L30 level (same rule as the cell text colour and the Yesterday badges).
            var dotFallbackKeys = {};
            function setL30FallbackDot(channelKey, metric, baseline, current) {
                if (!channelKey) return;
                var k = channelKey + '_' + metric;
                var existing = lastDotPairByKey[k];
                var hasReal = existing && existing[0] != null && existing[1] != null
                    && !isNaN(existing[0]) && !isNaN(existing[1]) && !dotFallbackKeys[k];
                if (hasReal) return;
                if (baseline == null || current == null || !isFinite(baseline) || !isFinite(current)) return;
                lastDotPairByKey[k] = [baseline, current];
                lastDotColorByKey[k] = colorFromDotPair(baseline, current, metric);
                dotFallbackKeys[k] = true;
            }
            function applyL30FallbackDots() {
                if (!table || typeof table.getData !== 'function') return;
                var rows = table.getData() || [];
                rows.forEach(function(row) {
                    var key = snapshotChannelKey(row['Channel '] || '');
                    if (!key) return;
                    var l30Orders = parseNumber(row['L30 Orders'] || 0);
                    var l30Views = parseNumber(row['Total Views'] || 0);
                    var hasY = row['Y Orders'] !== undefined && row['Y Orders'] !== null && row['Y Orders'] !== '';
                    if (hasY) setL30FallbackDot(key, 'y_orders', l30Orders / 30, parseNumber(row['Y Orders']));
                    if (row['Y Views'] !== undefined && row['Y Views'] !== null && row['Y Views'] !== '' && l30Views > 0) {
                        setL30FallbackDot(key, 'y_views', l30Views / 30, parseNumber(row['Y Views']));
                    }
                    if (row['Y CVR'] !== undefined && row['Y CVR'] !== null && row['Y CVR'] !== '') {
                        var listing = listingCvrFromRow(row);
                        if (listing != null && isFinite(listing)) {
                            setL30FallbackDot(key, 'y_cvr', listing, parseNumber(row['Y CVR']));
                        }
                    }
                    var t = todayPartsFromRow(row);
                    setL30FallbackDot(key, 't_orders', l30Orders / 30, t.orders);
                    if (l30Views > 0) setL30FallbackDot(key, 't_views', l30Views / 30, t.views);
                });
                paintMetricDots(channelKeysFromTableData());
            }
            function colorFromDotPair(v1, v2, metric) {
                if (v1 == null || v2 == null || isNaN(v1) || isNaN(v2)) return DEFAULT_DOT_GRAY;
                var eps = (metric === 'cvr' || metric === 'ads_cvr' || metric === 'gprofit' || metric === 'groi'
                    || metric === 'npft' || metric === 'p_npft' || metric === 'p_groi_pct' || metric === 'p_nroi_pct' || metric === 'y_npft_pct' || metric === 'y_groi_pct' || metric === 'y_nroi_pct'
                    || metric === 'nroi' || metric === 'ads_pct' || metric === 'acos') ? 0.005 : 0.01;
                if (Math.abs(v2 - v1) <= eps) return DEFAULT_DOT_GRAY;
                var isInverted = invertedDotMetrics.indexOf(metric) >= 0;
                return isInverted ? (v2 < v1 ? '#28a745' : '#dc3545') : (v2 > v1 ? '#28a745' : '#dc3545');
            }
            function channelKeysFromTableData(tableData) {
                var data = tableData && Array.isArray(tableData) ? tableData : (table && table.getData ? table.getData() : []);
                var channelKeys = [];
                for (var i = 0; i < data.length; i++) {
                    var ch = snapshotChannelKey(data[i]['Channel '] || data[i]['Channel'] || '');
                    if (ch) channelKeys.push(ch);
                }
                return channelKeys;
            }
            function paintMetricDots(channelKeys) {
                document.querySelectorAll('i.metric-chart-icon, i.ad-chart-icon').forEach(function(el) {
                    var ch = el.getAttribute('data-channel');
                    var metric = el.getAttribute('data-metric');
                    if (metric === 'today_sales' && el.hasAttribute('data-today')) {
                        var todayVal = parseFloat(el.getAttribute('data-today'));
                        var yVal = parseFloat(el.getAttribute('data-y-sales'));
                        el.style.color = todayVsYesterdayColor(todayVal, yVal);
                        el.style.display = '';
                        el.title = todayVsYesterdayTitle(todayVal, yVal);
                        return;
                    }
                    if (metric === 'p_sales' && el.hasAttribute('data-p-sales')) {
                        var p = parseFloat(el.getAttribute('data-p-sales'));
                        var s = parseFloat(el.getAttribute('data-l30-sales'));
                        if (!isNaN(p) && !isNaN(s)) {
                            var growthPct = pSalesVsL30Pct(p, s);
                            el.style.color = pSalesVsL30Color(p, s);
                            el.style.display = '';
                            el.title = pSalesVsL30Title(growthPct);
                            return;
                        }
                    }
                    if (metric === 'p_npft_amt' && el.hasAttribute('data-p-npft')) {
                        var pAmt = parseFloat(el.getAttribute('data-p-npft'));
                        var nAmt = parseFloat(el.getAttribute('data-npft-amt'));
                        var npftPct = pNpftAmtVsNpftPct(pAmt, nAmt);
                        el.style.color = pNpftAmtVsNpftColor(pAmt, nAmt);
                        el.style.display = '';
                        el.title = pNpftAmtVsNpftTitle(npftPct);
                        return;
                    }
                    var color = getMetricDotColor(ch, metric || 'ad_spend');
                    el.style.color = color;
                    el.style.display = '';

                    var prev = el.previousElementSibling;
                    if (prev && prev.classList && prev.classList.contains('metric-diff-value')) {
                        prev.remove();
                    }

                    if (metric) {
                        var text = formatMetricDiffText(metric, getMetricDiffAmount(ch, metric));
                        var isFallback = !!dotFallbackKeys[snapshotChannelKey(ch) + '_' + metric];
                        el.title = text
                            ? (text + (isFallback ? ' vs L30 average (no previous day saved yet) — click for chart' : ' vs previous day — click for chart'))
                            : 'View Chart';
                    }
                });
                if (typeof colorSummaryBadgeDots === 'function') {
                    colorSummaryBadgeDots(channelKeys || []);
                }
            }

            // Shared server pair only. Do not reuse this browser's last visit,
            // and do not recolor from a live overlay — that made the same saved
            // row green for one user and red for another.
            try { localStorage.removeItem('channelMasterDotColors'); } catch (e) {}
            dotTrendsPrefetch = $.ajax({
                url: channelMetricDotTrendsUrl || '/channel-metric-dot-trends',
                type: 'GET',
                dataType: 'json'
            }).done(function(response) {
                var channels = response && response.success ? response.channels : null;
                if (channels && Object.keys(channels).length) {
                    applyDotTrendsMap(channels);
                    if (table) {
                        applyL30FallbackDots();
                        paintMetricDots(Object.keys(channels));
                    }
                }
            });

            table = new Tabulator("#marketplace-table", {
                ajaxURL: "/channels-master-data",
                ajaxParams: { size: 10000, page: 1 },
                ajaxSorting: false,
                layout: "fitData",
                height: false,
                renderVertical: "basic",
                pagination: false,
                responsiveLayout: false,
                columnDefaults: {
                    minWidth: 42,
                    widthShrink: 0,
                },
                columnCalcs: "both",
                initialSort: [{
                    column: "L30 Sales",
                    dir: "desc"
                }],
                ajaxResponse: function(url, params, response) {
                    if (response && response.data) {
                        if (response.data.length === 0 && response.message) {
                            showToast('info', response.message || 'No channels to display.');
                        }
                        response.data.forEach(function(row) {
                            row['P-Sales'] = projectedSalesFromL7(row['L7 Sales'] || 0);
                            row['P Growth'] = pGrowthFromRow(row);
                            row['Y PFT'] = yGrossPftFromRow(row);
                            row['Y NPFT'] = yNetPftFromRow(row);
                            row['Y GROI%'] = yGroiPctFromRow(row);
                            row['YNPFT%'] = yNpftPctFromRow(row);
                            row['P GROI%'] = pGroiPctFromRow(row);
                            row['PNPFT%'] = pNpftPctFromRow(row);
                            row['P NPFT'] = pNetPftFromRow(row);
                        });
                        updateSummaryStats(response.data);
                        function setCompactInvBadge(elId, rawVal, titlePrefix) {
                            const el = document.getElementById(elId);
                            if (!el || rawVal == null) return 0;
                            const val = Math.round(parseFloat(rawVal) || 0);
                            let compact;
                            if (Math.abs(val) >= 1000000) {
                                compact = (val / 1000000).toFixed(1).replace(/\.0$/, '') + 'M';
                            } else if (Math.abs(val) >= 1000) {
                                compact = Math.round(val / 1000) + 'K';
                            } else {
                                compact = String(val);
                            }
                            el.textContent = compact;
                            const badge = el.closest('.badge');
                            if (badge) {
                                badge.title = titlePrefix + ': $' + val.toLocaleString('en-US');
                                badge.setAttribute('data-exact-value', String(val));
                            }
                            return val;
                        }
                        const invVal = setCompactInvBadge(
                            'inventory-value-amazon',
                            response.inventory_value_amazon,
                            'Shopify INV × Amazon live price'
                        );
                        setCompactInvBadge(
                            'inv-at-lp',
                            response.inv_at_lp,
                            'Shopify INV × LP'
                        );
                        setCompactInvBadge(
                            'inv-at-sp',
                            response.inv_at_sp,
                            'Shopify INV × Standard Price'
                        );
                        if (window.AnalyticsDilBadge) {
                            const ovL30 = parseFloat(response.total_ov_l30) || 0;
                            const dilInv = parseFloat(response.total_inv) || parseFloat(response.shopify_inv_sum) || 0;
                            const fromSums = dilInv > 0 ? (ovL30 / dilInv) * 100 : 0;
                            const dilPct = parseFloat(response.dil_ov_percent);
                            const pct = (isFinite(dilPct) && !(dilPct === 0 && fromSums > 0)) ? dilPct : fromSums;
                            AnalyticsDilBadge.init({
                                getRows: function() {
                                    return [{ L30: ovL30, INV: dilInv }];
                                }
                            });
                            AnalyticsDilBadge.set(pct, ovL30, dilInv);
                        }
                        if (response.inventory_pies && Array.isArray(response.inventory_pies.slices)) {
                            lastInventoryPies = response.inventory_pies;
                        } else if (Array.isArray(response.inventory_by_color) && response.inventory_by_color.length) {
                            lastInventoryPies = { slices: response.inventory_by_color, history: [] };
                        }
                        if (lastInventoryPies && typeof ammInvRenderBlock === 'function') {
                            ammInvRenderBlock(lastInventoryPies);
                        } else if (typeof ammInvPiesUrl !== 'undefined') {
                            $.getJSON(ammInvPiesUrl).done(function(res) {
                                if (!res || (res.status && Number(res.status) !== 200) || !Array.isArray(res.slices) || !res.slices.length) return;
                                lastInventoryPies = res;
                                if (typeof ammInvRenderBlock === 'function') ammInvRenderBlock(res);
                            });
                        }
                        // TAT = inv ÷ L30 Sales (months of cover)
                        const tatEl = document.getElementById('tat-badge');
                        if (tatEl && response.data && response.data.length) {
                            let totalSales = 0;
                            response.data.forEach(function(row) {
                                const s = (row['L30 Sales'] || 0);
                                totalSales += (typeof parseNumber === 'function' ? parseNumber(s) : parseFloat(String(s).replace(/[^0-9.-]/g, ''))) || 0;
                            });
                            const tat = totalSales > 0 ? invVal / totalSales : 0;
                            const tatRounded = tat > 0 ? parseFloat(tat.toFixed(2)) : 0;
                            tatEl.textContent = tat > 0 ? tat.toFixed(2) : '0.00';
                            const tatBadge = tatEl.closest('.badge');
                            if (tatBadge) {
                                tatBadge.setAttribute('data-exact-value', String(tatRounded));
                                tatBadge.title = 'TAT = inv ÷ L30 Sales (months of stock): ' + tatRounded.toFixed(2);
                            }
                        }
                        if (response.dot_trends && Object.keys(response.dot_trends).length) {
                            applyDotTrendsMap(response.dot_trends);
                        }
                        if (!dotTrendsLoadedOnce) {
                            dotTrendsLoadedOnce = true;
                            loadMetricDotTrends(response.data);
                        }
                        return response.data;
                    }
                    return [];
                },
                ajaxRequestError: function(error) {
                    const msg = (error && error.responseJSON && error.responseJSON.message) ? error.responseJSON.message : 'Failed to load channel data. Check console for details.';
                    if (typeof showToast === 'function') showToast('error', msg);
                },
                columns: [{
                        title: "Img",
                        field: "logo",
                        frozen: true,
                        responsive: 0,
                        width: 60,
                        hozAlign: "center",
                        headerSort: false,
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const logo = cell.getValue();
                            const channel = (rowData['Channel '] || '').trim();
                            const sellerLink = (rowData['seller_link'] || '').trim();

                            const imgHtml = logo
                                ? `<img src="/storage/${logo}" alt="${channel}" class="channel-logo-thumb" onerror="this.style.display='none'"/>`
                                : `<span class="channel-logo-placeholder" title="No logo">
                                       <i class="fas fa-image text-muted"></i>
                                   </span>`;

                            if (sellerLink) {
                                const safeLink = sellerLink.replace(/"/g, '&quot;');
                                return `<a href="${safeLink}" target="_blank" rel="noopener noreferrer" title="Open seller page" class="channel-logo-link">${imgHtml}</a>`;
                            }
                            return imgHtml;
                        }
                    },
                    {
                        title: "MP",
                        field: "Channel ",
                        frozen: true,
                        responsive: 0,
                        minWidth: 108,
                        formatter: function(cell) {
                            const channel = cell.getValue();
                            const rowData = cell.getRow().getData();
                            const missingLink = rowData['missing_link'] || '';

                            const channelDisplay = missingLink
                                ? `<a href="${missingLink}" target="_blank" class="missing-l-link channel-name-link" style="color:inherit;font-weight:inherit;text-decoration:none;" title="View missing items">${channel}</a>`
                                : `<span>${channel}</span>`;
                            const chKey = String(channel || '').toLowerCase().replace(/[\s\-&/]/g, '');
                            let connectHref = '';
                            if (chKey === 'tiktok2' || chKey === 'tiktokshop2') {
                                connectHref = '/tiktok2/connect';
                            } else if (chKey === 'tiktok' || chKey === 'tiktokshop') {
                                connectHref = '/tiktok/connect';
                            }
                            const connectHtml = connectHref
                                ? `<a href="${connectHref}" target="_blank" rel="noopener noreferrer" class="ms-1" title="Connect shop API"><i class="ri-plug-line"></i></a>`
                                : '';

                            return `<div>${channelDisplay}${connectHtml}</div>`;
                        }
                    },
                    {
                        title: "Type",
                        field: "type",
                        frozen: true,
                        responsive: 0,
                        hozAlign: "center",
                        width: 48,
                        minWidth: 44,
                        headerTooltip: "Channel type (B2C, B2B, DS, Wholesale).",
                        formatter: function(cell) {
                            const type = (cell.getValue() || '').toString().trim();
                            if (!type) {
                                return '<span style="color:#adb5bd;">-</span>';
                            }
                            const slug = type.toLowerCase().replace(/[^a-z0-9]+/g, '');
                            const known = {
                                b2c: 'type-b2c',
                                b2b: 'type-b2b',
                                dropship: 'type-dropship',
                                ds: 'type-dropship',
                                wholesale: 'type-wholesale',
                            };
                            const labels = {
                                dropship: 'DS',
                                ds: 'DS',
                                wholesale: 'WS',
                            };
                            const titles = {
                                dropship: 'Dropship',
                                ds: 'Dropship',
                                wholesale: 'Wholesale',
                            };
                            const cls = known[slug] || 'type-b2c';
                            const label = labels[slug] || type;
                            const safe = label.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
                            const title = titles[slug] || label;
                            const safeTitle = title.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
                            return `<span class="type-badge ${cls}" title="${safeTitle}">${safe}</span>`;
                        }
                    },
                    {
                        title: "BA",
                        field: "brand_authorisation",
                        hozAlign: "center",
                        width: 46,
                        minWidth: 42,
                        headerTooltip: "Brand Authorisation",
                        menuTitle: "Brand Authorisation",
                        formatter: function(cell) {
                            const value = (cell.getValue() || '').toString().trim();
                            if (value !== 'Yes' && value !== 'No') {
                                return '<span style="color:#adb5bd;" title="Brand Authorisation">-</span>';
                            }
                            const color = value === 'Yes' ? '#198754' : '#dc3545';
                            return `<span style="font-weight:600;color:${color};" title="Brand Authorisation">${value}</span>`;
                        }
                    },
                    {
                        // Alias: short display label set per channel in the Edit modal.
                        // Clicking it opens the channel's tabulator view (the same
                        // "Blade page link" / missing_link used by the channel name).
                        title: "Channel",
                        field: "alias",
                        responsive: 0,
                        hozAlign: "center",
                        headerTooltip: "Channel alias — click to open this channel's view.",
                        formatter: function(cell) {
                            const alias = (cell.getValue() || '').toString().trim();
                            if (!alias) {
                                return '<span style="color:#adb5bd;">-</span>';
                            }
                            const rowData = cell.getRow().getData();
                            const viewLink = rowData['missing_link'] || '';
                            if (viewLink) {
                                return `<a href="${viewLink}" target="_blank" class="channel-alias-link" style="color:#0d6efd;font-weight:600;text-decoration:none;cursor:pointer;" title="Open ${alias} view">${alias}</a>`;
                            }
                            return `<span style="font-weight:600;">${alias}</span>`;
                        }
                    },
                    {
                        title: "Missing L",
                        field: "Miss",
                        visible: false,
                        hozAlign: "center",
                        sorter: "number",
                       
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const additionSheet = rowData['addition_sheet'] || '';
                            const channel = (rowData['Channel '] || '').trim();
                            // Dot matches number (trend dot can invert vs value and look "wrong")
                            const dotColor = value === 0 ? '#198754' : (value > 0 ? '#dc3545' : DEFAULT_DOT_GRAY);
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="missing_l" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View Chart"></i>`;

                            const textColor = value === 0 ? '#198754' : value > 0 ? '#dc3545' : 'black';
                            const style = `color:${textColor};font-weight:600;`;

                            if (additionSheet) {
                                return `<a href="${additionSheet}" target="_blank" style="${style}text-decoration:none;cursor:pointer;" title="Click to open addition sheet">${value}</a>${chartIcon}`;
                            }

                            const valueHtml = String(value);
                            const body = acPricingHoverCellHtml(channel, 'missing_l', 'missing', valueHtml, style);
                            return body + chartIcon;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                var cv = cell.getElement().querySelector('span'); cv = cv ? parseFloat(cv.textContent.replace(/[$,%,\s]/g, '')) : null; showMetricChart($(e.target).data('channel'), $(e.target).data('metric'), cv);
                                return;
                            }
                            const hoverEl = e.target.closest('.ac-pricing-hover-cell');
                            if (hoverEl) {
                                e.stopPropagation();
                                const rowData = cell.getRow().getData();
                                acOpenPricingPageWithBadge(rowData['missing_link'] || '', hoverEl.getAttribute('data-filter-badge'));
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            return `<strong>${parseNumber(value).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "Map",
                        field: "Map",
                        visible: false,
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const channel = (cell.getRow().getData()['Channel '] || '').trim();
                            const dotColor = value === 0 ? DEFAULT_DOT_GRAY : '#198754';
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="map" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View Chart"></i>`;
                            const style = value === 0 ? 'color:#6c757d;font-weight:600;' : 'color:#198754;font-weight:600;';
                            const valueHtml = value.toLocaleString('en-US');
                            return acPricingHoverCellHtml(channel, 'map', 'map', valueHtml, style) + chartIcon;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                var cv = cell.getElement().querySelector('span'); cv = cv ? parseFloat(cv.textContent.replace(/[$,%,\s]/g, '')) : null; showMetricChart($(e.target).data('channel'), $(e.target).data('metric'), cv);
                                return;
                            }
                            const hoverEl = e.target.closest('.ac-pricing-hover-cell');
                            if (hoverEl) {
                                e.stopPropagation();
                                const rowData = cell.getRow().getData();
                                acOpenPricingPageWithBadge(rowData['missing_link'] || '', hoverEl.getAttribute('data-filter-badge'));
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            return `<strong>${parseNumber(value).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "N Map",
                        field: "NMap",
                        visible: false,
                        hozAlign: "center",
                        sorter: "number",
                        
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const channel = (cell.getRow().getData()['Channel '] || '').trim();
                            const dotColor = value === 0 ? '#28a745' : '#dc3545';
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="nmap" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View Chart"></i>`;

                            const color = value === 0 ? 'green' : 'red';
                            const style = `color:${color};font-weight:bold;`;
                            const valueHtml = String(value);

                            return acPricingHoverCellHtml(channel, 'nmap', 'nmap', valueHtml, style) + chartIcon;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                var cv = cell.getElement().querySelector('span'); cv = cv ? parseFloat(cv.textContent.replace(/[$,%,\s]/g, '')) : null; showMetricChart($(e.target).data('channel'), $(e.target).data('metric'), cv);
                                return;
                            }
                            const hoverEl = e.target.closest('.ac-pricing-hover-cell');
                            if (hoverEl) {
                                e.stopPropagation();
                                const rowData = cell.getRow().getData();
                                acOpenPricingPageWithBadge(rowData['missing_link'] || '', hoverEl.getAttribute('data-filter-badge'));
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            return `<strong>${parseNumber(value).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "L Missing Ads",
                        field: "Missing Ads",
                        hozAlign: "center",
                        sorter: "number",
                        width: 110,
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();

                            if (!value || value === 0) return '-';

                            // Add info icon for channels that might have PT/KW/HL breakdown
                            const infoIcon = `<i class="fas fa-info-circle missing-info-icon ms-1" 
                                data-channel="${channel}" 
                                style="cursor:pointer;color:#dc3545;font-size:12px;" 
                                title="View L Missing Ads Breakdown"></i>`;

                            return `<span style="font-weight:600;color:#dc3545;">${value.toLocaleString('en-US')}</span>${infoIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('missing-info-icon')) {
                                e.stopPropagation();
                                const channelName = $(e.target).data('channel');
                                showMissingAdsBreakdown(
                                channelName); // Show Missing Ads specific modal
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            if (!value || value === 0) return '<strong>-</strong>';
                            return `<strong style="color:#dc3545;">${parseNumber(value).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "L60 Sales",
                        field: "L-60 Sales",
                        headerTooltip: "Sales from days 31-60 (previous 30-day period: {{ \Carbon\Carbon::now()->subDays(60)->format('M d, Y') }} – {{ \Carbon\Carbon::now()->subDays(31)->format('M d, Y') }}).",
                        hozAlign: "center",
                        sorter: "number",
                        width: 100,
                        visible: false,
                        formatter: function(cell) {
                            const value = Math.round(parseNumber(cell.getValue()));
                            const channel = (cell.getRow().getData()['Channel '] || '').trim();
                            const dotColor = getMetricDotColor(channel, 'l60_sales');
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="l60_sales" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View L60 Sales Chart"></i>`;
                            return `<span style="font-weight: 600;">$${value.toLocaleString('en-US')}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                var cv = cell.getElement().querySelector('span'); cv = cv ? parseFloat(cv.textContent.replace(/[$,%,\s]/g, '')) : null; showMetricChart($(e.target).data('channel'), $(e.target).data('metric'), cv);
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = Math.round(parseNumber(cell.getValue()));
                            return `<strong>$${value.toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "Sales",
                        field: "L30 Sales",
                        responsive: 0,
                        headerTooltip: "Rolling sales per channel. Amz = last {{ (int) \App\Http\Controllers\Sales\AmazonSalesController::DAILY_SALES_WINDOW_DAYS }} complete Pacific days through yesterday — Seller Central Ordered product sales (ItemPrice − promo; shipping/gift/tax excluded; Canceled excluded).",
                        hozAlign: "center",
                        sorter: "number",
                        width: 136,
                        formatter: function(cell) {
                            const value = Math.round(parseNumber(cell.getValue()));
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            const dotColor = getMetricDotColor(channel, 'l30_sales');
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="l30_sales" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View Chart"></i>`;
                            const fmt = `$${value.toLocaleString('en-US')}`;
                            const salesPage = (rowData['sales_page_link'] || '').trim() || shopifyB2cDailySalesPage(channel);
                            const valueHtml = salesPage
                                ? `<a href="${salesPage}" target="_blank" rel="noopener noreferrer" class="shopify-b2c-sales-link" style="font-weight:600;color:inherit;text-decoration:none;" title="Open ${channel || 'channel'} Sales">${fmt}</a>`
                                : `<span style="font-weight: 600;">${fmt}</span>`;
                            return valueHtml + chartIcon;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                var cv = cell.getElement().querySelector('span, a'); cv = cv ? parseFloat(cv.textContent.replace(/[$,%,\s]/g, '')) : null; showMetricChart($(e.target).data('channel'), $(e.target).data('metric'), cv);
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = Math.round(parseNumber(cell.getValue()));
                            return `<strong>$${value.toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "L60 Orders",
                        field: "L60 Orders",
                        headerTooltip: "Order count from the previous 30-day period (L60 window). Shein: from API-synced shein_daily_data_l60 when populated.",
                        hozAlign: "center",
                        sorter: "number",
                        width: 100,
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const channel = (cell.getRow().getData()['Channel '] || '').trim();
                            const dotColor = getMetricDotColor(channel, 'l60_orders');
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="l60_orders" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View L60 Orders Chart"></i>`;
                            if (!value) return `<span style="color:#adb5bd;">—</span>${chartIcon}`;
                            return `<span style="font-weight: 600;">${value.toLocaleString('en-US')}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                var cv = cell.getElement().querySelector('span');
                                cv = cv ? parseFloat(cv.textContent.replace(/[,$%\s]/g, '')) : null;
                                showMetricChart($(e.target).data('channel'), $(e.target).data('metric'), cv);
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            return `<strong>${value.toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "Growth",
                        field: "Growth",
                        visible: false,
                        headerTooltip: "Growth % comparing yesterday’s sales (Y Sales) to sales on the Pacific day 30 days before yesterday (D30 Sales). Formula: ((Y Sales − D30 Sales) / D30 Sales) × 100. Green = up vs that day, red = down.",
                        hozAlign: "center",
                        sorter: "number",
                        width: 88,
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            let growth = null;
                            const value = cell.getValue();

                            if (value !== null && value !== undefined && value !== '' && value !== '—') {
                                const growthStr = String(value).replace('%', '');
                                const parsed = parseFloat(growthStr);
                                if (!isNaN(parsed)) growth = parsed;
                            }

                            // Fallback: Y Sales vs D30 Sales (day −30)
                            if (growth === null) {
                                const ySales = parseNumber(rowData['Y Sales'] || 0);
                                const d30 = parseNumber(rowData['D30 Sales'] || 0);
                                if (!d30) {
                                    return '<span style="color:#adb5bd;">—</span>';
                                }
                                growth = ((ySales - d30) / d30) * 100;
                            }

                            if (!isFinite(growth)) {
                                return '<span style="color:#adb5bd;">—</span>';
                            }

                            if (Math.abs(growth) < 0.1) {
                                return '<span style="font-weight:600;color:#6c757d;">0%</span>';
                            }

                            const isPositive = growth > 0;
                            const color = isPositive ? '#198754' : '#dc3545';
                            const arrow = isPositive ? '↑' : '↓';

                            return `<span style="font-weight:600;color:${color};">${arrow} ${Math.abs(growth).toFixed(0)}%</span>`;
                        },
                        bottomCalc: function(values, data) {
                            let sumY = 0;
                            let sumD30 = 0;
                            data.forEach(function(row) {
                                sumY += parseNumber(row['Y Sales'] || 0);
                                const d30 = row['D30 Sales'];
                                if (d30 !== null && d30 !== undefined && d30 !== '') {
                                    sumD30 += parseNumber(d30);
                                }
                            });
                            if (sumD30 === 0) return null;
                            return ((sumY - sumD30) / sumD30) * 100;
                        },
                        bottomCalcFormatter: function(cell) {
                            const v = cell.getValue();
                            if (v === null || v === undefined) return '<strong>—</strong>';
                            const growth = parseNumber(v);
                            if (!isFinite(growth)) return '<strong>—</strong>';

                            if (Math.abs(growth) < 0.1) {
                                return '<strong style="color:#6c757d;">0%</strong>';
                            }

                            const isPositive = growth > 0;
                            const color = isPositive ? '#198754' : '#dc3545';
                            const arrow = isPositive ? '↑' : '↓';

                            return `<strong style="color:${color};">${arrow} ${Math.abs(growth).toFixed(0)}%</strong>`;
                        }
                    },
                    {
                        title: "Y Sales",
                        field: "Y Sales",
                        responsive: 0,
                        headerTooltip: "Yesterday Pacific. Amz = Seller Central Ordered product sales for that calendar day (shipping/gift/tax excluded).",
                        hozAlign: "center",
                        sorter: "number",
                        width: 128,
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            const value = parseNumber(cell.getValue() || 0);
                            const channel = (row['Channel '] || '').trim();
                            const dotColor = getMetricDotColor(channel, 'y_sales');
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="y_sales" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View Chart"></i>`;
                            const avg = parseNumber(row['L30 Sales'] || 0) / 30;
                            const color = value < avg ? '#dc3545' : '#166534';
                            const vs = value < avg ? 'below' : 'at or above';
                            const title = 'Y Sales ' + vs + ' the L30 daily average (L30 Sales ÷ 30).';
                            if (!value || value === 0) {
                                return `<span style="color:${color};font-weight:600;" title="No Yesterday Sales. ${title}">NYS</span>${chartIcon}`;
                            }
                            return `<span style="font-weight:600;color:${color};" title="${title}">$${Math.round(value).toLocaleString('en-US')}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                var cv = cell.getElement().querySelector('span'); cv = cv ? parseFloat(cv.textContent.replace(/[$,%,\s]/g, '')) : null; showMetricChart($(e.target).data('channel'), $(e.target).data('metric'), cv);
                            }
                        },
                        bottomCalc: function(values, data) {
                            let y = 0, l30 = 0;
                            data.forEach(function(row) {
                                y += parseNumber(row['Y Sales'] || 0);
                                l30 += parseNumber(row['L30 Sales'] || 0);
                            });
                            ySalesFooterBelowAvg = y < (l30 / 30);
                            return y;
                        },
                        bottomCalcFormatter: function(cell) {
                            const value = parseNumber(cell.getValue() || 0);
                            const color = ySalesFooterBelowAvg ? '#dc3545' : '#166534';
                            if (!value) return `<strong style="color:${color};" title="No Yesterday Sales">NYS</strong>`;
                            return `<strong style="color:${color};">$${Math.round(value).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "Y Orders",
                        field: "Y Orders",
                        headerTooltip: "Yesterday order count (1-day Pacific). Same Orders figure as Yesterday by marketplace.",
                        hozAlign: "center",
                        sorter: "number",
                        width: 110,
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            const channel = (row['Channel '] || '').trim();
                            const value = Math.round(parseNumber(cell.getValue() || 0));
                            const color = belowL30AvgColor(value, row['L30 Orders'] || 0);
                            return `<span style="font-weight:600;color:${color};">${value.toLocaleString('en-US')}</span>${ammHistoryDot(channel, 'y_orders')}`;
                        },
                        cellClick: ammOpenHistoryDot,
                        bottomCalc: function(values, data) {
                            let sum = 0, l30 = 0;
                            data.forEach(function(row) {
                                sum += parseNumber(row['Y Orders'] || 0);
                                l30 += parseNumber(row['L30 Orders'] || 0);
                            });
                            yOrdersFooterBelowAvg = sum < (l30 / 30);
                            return sum;
                        },
                        bottomCalcFormatter: function(cell) {
                            const color = yOrdersFooterBelowAvg ? '#dc3545' : '#166534';
                            return `<strong style="color:${color};">${Math.round(parseNumber(cell.getValue() || 0)).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "Y CVR",
                        field: "Y CVR",
                        headerTooltip: "Yesterday CVR = yesterday qty ÷ yesterday views. Same figure as Yesterday by marketplace.",
                        hozAlign: "center",
                        sorter: "number",
                        width: 100,
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            const channel = (row['Channel '] || '').trim();
                            const value = cell.getValue();
                            const color = belowL30RateColor(value, listingCvrFromRow(row));
                            return formatCvrCell(value, color) + ammHistoryDot(channel, 'y_cvr');
                        },
                        cellClick: ammOpenHistoryDot,
                        bottomCalc: function(values, data) {
                            if (!yCvrByChannel) return null;
                            let views = 0, qty = 0, l30Units = 0, l30Views = 0;
                            data.forEach(function(row) {
                                const rec = yCvrByChannel[snapshotChannelKey(row['Channel '] || '')];
                                if (rec) {
                                    views += rec.views;
                                    qty += rec.qty;
                                }
                                const l30ViewsRow = parseNumber(row['Total Views'] || 0);
                                const l30Rate = listingCvrFromRow(row);
                                if (l30ViewsRow > 0 && l30Rate != null) {
                                    l30Views += l30ViewsRow;
                                    l30Units += l30ViewsRow * l30Rate / 100;
                                }
                            });
                            const yRate = views > 0 ? (qty / views) * 100 : null;
                            const l30Rate = l30Views > 0 ? (l30Units / l30Views) * 100 : null;
                            yCvrFooterBelowAvg = yRate != null && l30Rate != null && yRate < l30Rate;
                            return yRate;
                        },
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            if (value === null || value === undefined || !isFinite(parseNumber(value))) {
                                return '<strong style="color:#adb5bd;">—</strong>';
                            }
                            const pct = Math.round(parseNumber(value) * 100) / 100;
                            const color = yCvrFooterBelowAvg ? '#dc3545' : '#166534';
                            return '<strong style="color:' + color + ';">' + pct.toFixed(2) + '%</strong>';
                        }
                    },
                    {
                        title: "Y Views",
                        field: "Y Views",
                        headerTooltip: "Yesterday listing views (1-day Pacific). Same Views figure as Yesterday by marketplace.",
                        hozAlign: "center",
                        sorter: "number",
                        width: 110,
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            const channel = (row['Channel '] || '').trim();
                            if (cell.getValue() === null || cell.getValue() === undefined || cell.getValue() === '') {
                                return '<span style="color:#adb5bd;" title="No views source for this channel">—</span>';
                            }
                            const value = Math.round(parseNumber(cell.getValue() || 0));
                            const color = belowL30AvgColor(value, row['Total Views'] || 0);
                            return `<span style="font-weight:600;color:${color};">${value.toLocaleString('en-US')}</span>${ammHistoryDot(channel, 'y_views')}`;
                        },
                        cellClick: ammOpenHistoryDot,
                        bottomCalc: function(values, data) {
                            let sum = 0, l30 = 0;
                            data.forEach(function(row) {
                                sum += parseNumber(row['Y Views'] || 0);
                                l30 += parseNumber(row['Total Views'] || 0);
                            });
                            yViewsFooterBelowAvg = sum < (l30 / 30);
                            return sum;
                        },
                        bottomCalcFormatter: function(cell) {
                            const color = yViewsFooterBelowAvg ? '#dc3545' : '#166534';
                            return `<strong style="color:${color};">${Math.round(parseNumber(cell.getValue() || 0)).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "Y GROI%",
                        field: "Y GROI%",
                        headerTooltip: "Yesterday GROI% = yesterday gross PFT $ ÷ yesterday COGS, both measured from yesterday's orders alone. Falls back to L30 COGS × (Y Sales ÷ L30 Sales) until the nightly calculation has run.",
                        hozAlign: "center",
                        sorter: "number",
                        width: 80,
                        mutator: function(value, data) {
                            return yGroiPctFromRow(data);
                        },
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            const ySales = parseNumber(row['Y Sales'] || 0);
                            const value = parseNumber(cell.getValue());
                            const channel = (row['Channel '] || '').trim();
                            const dotColor = getMetricDotColor(channel, 'y_groi_pct');
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="y_groi_pct" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View Chart"></i>`;
                            if (!ySales) {
                                return `<span style="color:#adb5bd;font-weight:600;" title="No Yesterday Sales">NYS</span>${chartIcon}`;
                            }
                            return `<span style="${ammMetricCellStyle('y_groi_pct', value)}font-weight:600;">${value.toFixed(1)}%</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                var cv = cell.getElement().querySelector('span'); cv = cv ? parseFloat(cv.textContent.replace(/[$,%,\s]/g, '')) : null; showMetricChart($(e.target).data('channel'), $(e.target).data('metric'), cv);
                            }
                        },
                        bottomCalc: function(values, data) {
                            let yGross = 0, yCogs = 0;
                            data.forEach(function(row) {
                                yGross += yGrossPftFromRow(row);
                                yCogs += yCogsFromRow(row);
                            });
                            return yCogs > 0 ? (yGross / yCogs) * 100 : null;
                        },
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            if (value === null || value === undefined || !isFinite(parseNumber(value))) {
                                return '<strong style="color:#adb5bd;" title="No Yesterday Sales">NYS</strong>';
                            }
                            return `<strong>${parseNumber(value).toFixed(1)}%</strong>`;
                        }
                    },
                    {
                        title: "YNPFT%",
                        field: "YNPFT%",
                        headerTooltip: "Yesterday NPFT% = (yesterday gross PFT $ − yesterday ad spend) ÷ yesterday sales, all measured from yesterday alone. Falls back to the L30 NPFT% until the nightly calculation has run; NYS when a channel had no yesterday sales.",
                        hozAlign: "center",
                        sorter: "number",
                        width: 80,
                        mutator: function(value, data) {
                            return yNpftPctFromRow(data);
                        },
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            const ySales = parseNumber(row['Y Sales'] || 0);
                            const value = parseNumber(cell.getValue());
                            const channel = (row['Channel '] || '').trim();
                            const dotColor = getMetricDotColor(channel, 'y_npft_pct');
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="y_npft_pct" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View Chart"></i>`;
                            if (!ySales) {
                                return `<span style="color:#adb5bd;font-weight:600;" title="No Yesterday Sales">NYS</span>${chartIcon}`;
                            }
                            return `<span style="${ammMetricCellStyle('y_npft_pct', value)}font-weight:600;">${value.toFixed(1)}%</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                var cv = cell.getElement().querySelector('span'); cv = cv ? parseFloat(cv.textContent.replace(/[$,%,\s]/g, '')) : null; showMetricChart($(e.target).data('channel'), $(e.target).data('metric'), cv);
                            }
                        },
                        bottomCalc: function(values, data) {
                            let yNet = 0, ySales = 0;
                            data.forEach(function(row) {
                                const m = yMeasuredFromRow(row);
                                yNet += yNetPftFromRow(row);
                                ySales += m ? m.sales : parseNumber(row['Y Sales'] || 0);
                            });
                            return ySales > 0 ? (yNet / ySales) * 100 : null;
                        },
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            if (value === null || value === undefined || !isFinite(parseNumber(value))) {
                                return '<strong style="color:#adb5bd;" title="No Yesterday Sales">NYS</strong>';
                            }
                            return `<strong>${parseNumber(value).toFixed(1)}%</strong>`;
                        }
                    },
                    {
                        title: "Y PFT",
                        field: "Y PFT",
                        visible: false,
                        headerTooltip: "Yesterday gross profit $ = Y Sales × GPFT%. Uses the Y Sales column, not rolling L30 Sales.",
                        hozAlign: "center",
                        sorter: "number",
                        width: 100,
                        mutator: function(value, data) {
                            return yGrossPftFromRow(data);
                        },
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            const ySales = parseNumber(row['Y Sales'] || 0);
                            const value = parseNumber(cell.getValue());
                            const channel = (row['Channel '] || '').trim();
                            const dotColor = getMetricDotColor(channel, 'y_pft');
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="y_pft" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View Chart"></i>`;
                            if (!ySales) {
                                return `<span style="color:#adb5bd;font-weight:600;" title="No Yesterday Sales">NYS</span>${chartIcon}`;
                            }
                            return `<span style="font-weight:600;color:#17a2b8;">$${Math.round(value).toLocaleString('en-US')}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                var cv = cell.getElement().querySelector('span'); cv = cv ? parseFloat(cv.textContent.replace(/[$,%,\s]/g, '')) : null; showMetricChart($(e.target).data('channel'), $(e.target).data('metric'), cv);
                            }
                        },
                        bottomCalc: function(values, data) {
                            let sum = 0;
                            data.forEach(function(row) { sum += yGrossPftFromRow(row); });
                            return sum;
                        },
                        bottomCalcFormatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            if (!value) return '<strong style="color:#adb5bd;" title="No Yesterday Sales">NYS</strong>';
                            return `<strong style="color:#17a2b8;">$${Math.round(value).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "Y NPFT",
                        field: "Y NPFT",
                        visible: false,
                        headerTooltip: "Yesterday net profit $ = Y Sales × NPFT%. Uses the Y Sales column, not rolling L30 Sales.",
                        hozAlign: "center",
                        sorter: "number",
                        width: 100,
                        mutator: function(value, data) {
                            return yNetPftFromRow(data);
                        },
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            const ySales = parseNumber(row['Y Sales'] || 0);
                            const value = parseNumber(cell.getValue());
                            const channel = (row['Channel '] || '').trim();
                            const dotColor = getMetricDotColor(channel, 'y_npft_amt');
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="y_npft_amt" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View Chart"></i>`;
                            if (!ySales) {
                                return `<span style="color:#adb5bd;font-weight:600;" title="No Yesterday Sales">NYS</span>${chartIcon}`;
                            }
                            const color = value > 0 ? '#198754' : (value < 0 ? '#dc3545' : '#6c757d');
                            return `<span style="font-weight:600;color:${color};">$${Math.round(value).toLocaleString('en-US')}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                var cv = cell.getElement().querySelector('span'); cv = cv ? parseFloat(cv.textContent.replace(/[$,%,\s]/g, '')) : null; showMetricChart($(e.target).data('channel'), $(e.target).data('metric'), cv);
                            }
                        },
                        bottomCalc: function(values, data) {
                            let sum = 0;
                            data.forEach(function(row) { sum += yNetPftFromRow(row); });
                            return sum;
                        },
                        bottomCalcFormatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            if (!value) return '<strong style="color:#adb5bd;" title="No Yesterday Sales">NYS</strong>';
                            const color = value > 0 ? '#198754' : (value < 0 ? '#dc3545' : '#6c757d');
                            return `<strong style="color:${color};">$${Math.round(value).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "Today Sales",
                        field: "Today Sales",
                        headerTooltip: "Current Eastern calendar day from 12:00 AM EST/EDT (America/New_York) through now. Same revenue sources as Y Sales.",
                        hozAlign: "center",
                        sorter: "number",
                        width: 128,
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            const value = parseNumber(cell.getValue() || 0);
                            const ySales = parseNumber(row['Y Sales'] || 0);
                            const channel = (row['Channel '] || '').trim();
                            const dotColor = todayVsYesterdayColor(value, ySales);
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="today_sales" data-today="${value}" data-y-sales="${ySales}" style="cursor:pointer;color:${dotColor};font-size:8px;" title="${todayVsYesterdayTitle(value, ySales)}"></i>`;
                            if (!value || value === 0) {
                                return `<span style="color:#adb5bd;font-weight:600;" title="No sales yet today (Eastern)">$0</span>${chartIcon}`;
                            }
                            return `<span style="font-weight:600;color:#fd7e14;">$${Math.round(value).toLocaleString('en-US')}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                var cv = cell.getElement().querySelector('span');
                                cv = cv ? parseFloat(cv.textContent.replace(/[$,%,\s]/g, '')) : null;
                                showMetricChart($(e.target).data('channel'), $(e.target).data('metric'), cv);
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            if (!value || value === 0) return '<strong style="color:#adb5bd;">$0</strong>';
                            return `<strong style="color:#fd7e14;">$${Math.round(parseNumber(value)).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "T Orders",
                        field: "T Orders",
                        headerTooltip: "T Orders = L30 Orders × Today Sales ÷ L30 Sales. 0 when Today Sales is 0.",
                        hozAlign: "center",
                        sorter: "number",
                        width: 110,
                        mutator: function(value, data) {
                            return todayPartsFromRow(data).orders;
                        },
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            const channel = (row['Channel '] || '').trim();
                            const value = Math.round(parseNumber(cell.getValue() || 0));
                            const color = belowL30AvgColor(value, row['L30 Orders'] || 0);
                            return `<span style="font-weight:600;color:${color};">${value.toLocaleString('en-US')}</span>${ammHistoryDot(channel, 't_orders')}`;
                        },
                        cellClick: ammOpenHistoryDot,
                        bottomCalc: function(values, data) {
                            let sum = 0, l30 = 0;
                            data.forEach(function(row) {
                                sum += todayPartsFromRow(row).orders;
                                l30 += parseNumber(row['L30 Orders'] || 0);
                            });
                            tOrdersFooterBelowAvg = sum < (l30 / 30);
                            return sum;
                        },
                        bottomCalcFormatter: function(cell) {
                            const color = tOrdersFooterBelowAvg ? '#dc3545' : '#166534';
                            return `<strong style="color:${color};">${Math.round(parseNumber(cell.getValue() || 0)).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "T CVR",
                        field: "T CVR",
                        headerTooltip: "T CVR uses the same listing CVR as the CVR column, weighted by Today Sales ÷ L30 Sales. Blank when Today Sales is 0.",
                        hozAlign: "center",
                        sorter: "number",
                        width: 100,
                        mutator: function(value, data) {
                            return tCvrFromRow(data);
                        },
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            const channel = (row['Channel '] || '').trim();
                            const value = cell.getValue();
                            const color = belowL30RateColor(value, listingCvrFromRow(row));
                            return formatCvrCell(value, color) + ammHistoryDot(channel, 't_cvr');
                        },
                        cellClick: ammOpenHistoryDot,
                        bottomCalc: function(values, data) {
                            let views = 0, units = 0, l30Units = 0;
                            data.forEach(function(row) {
                                const parts = tCvrPartsFromRow(row);
                                views += parts.views;
                                units += parts.units;
                                const rate = listingCvrFromRow(row);
                                if (parts.views > 0 && rate != null) l30Units += parts.views * rate / 100;
                            });
                            const tRate = views > 0 ? (units / views) * 100 : null;
                            const l30Rate = views > 0 ? (l30Units / views) * 100 : null;
                            tCvrFooterBelowAvg = tRate != null && l30Rate != null && tRate < l30Rate;
                            return tRate;
                        },
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            if (value === null || value === undefined || !isFinite(parseNumber(value))) {
                                return '<strong style="color:#adb5bd;">—</strong>';
                            }
                            const pct = Math.round(parseNumber(value) * 100) / 100;
                            const color = tCvrFooterBelowAvg ? '#dc3545' : '#166534';
                            return '<strong style="color:' + color + ';">' + pct.toFixed(2) + '%</strong>';
                        }
                    },
                    {
                        title: "T Views",
                        field: "T Views",
                        headerTooltip: "T Views = Views × Today Sales ÷ L30 Sales. 0 when Today Sales is 0.",
                        hozAlign: "center",
                        sorter: "number",
                        width: 110,
                        mutator: function(value, data) {
                            return todayPartsFromRow(data).views;
                        },
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            const channel = (row['Channel '] || '').trim();
                            if (parseNumber(row['Total Views'] || 0) <= 0) {
                                return '<span style="color:#adb5bd;" title="No views source for this channel">—</span>';
                            }
                            const value = Math.round(parseNumber(cell.getValue() || 0));
                            const color = belowL30AvgColor(value, row['Total Views'] || 0);
                            return `<span style="font-weight:600;color:${color};">${value.toLocaleString('en-US')}</span>${ammHistoryDot(channel, 't_views')}`;
                        },
                        cellClick: ammOpenHistoryDot,
                        bottomCalc: function(values, data) {
                            let sum = 0, l30 = 0;
                            data.forEach(function(row) {
                                sum += todayPartsFromRow(row).views;
                                l30 += parseNumber(row['Total Views'] || 0);
                            });
                            tViewsFooterBelowAvg = sum < (l30 / 30);
                            return sum;
                        },
                        bottomCalcFormatter: function(cell) {
                            const color = tViewsFooterBelowAvg ? '#dc3545' : '#166534';
                            return `<strong style="color:${color};">${Math.round(parseNumber(cell.getValue() || 0)).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "L7 Sales",
                        field: "L7 Sales",
                        hozAlign: "center",
                        sorter: "number",
                        width: 90,
                        visible: false,
                        headerTooltip: "Sales over the last 7 Pacific calendar days ending yesterday (inclusive).",
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue() || 0);
                            if (!value || value === 0) {
                                return `<span style="color:#adb5bd;font-weight:600;" title="No L7 Sales">-</span>`;
                            }
                            return `<span style="font-weight:600;color:#0d6efd;">$${Math.round(value).toLocaleString('en-US')}</span>`;
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            if (!value || value === 0) return '<strong style="color:#adb5bd;">-</strong>';
                            return `<strong style="color:#0d6efd;">$${Math.round(parseNumber(value)).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "P-Sales",
                        field: "P-Sales",
                        hozAlign: "center",
                        sorter: "number",
                        width: 120,
                        headerTooltip: "Projected 30-day sales from last-7-day pace: (L7 Sales ÷ 7) × 30.",
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            const l7 = parseNumber(row['L7 Sales'] || 0);
                            const value = parseNumber(cell.getValue() != null && cell.getValue() !== '' ? cell.getValue() : projectedSalesFromL7(l7));
                            const channel = (row['Channel '] || '').trim();
                            const dotColor = getMetricDotColor(channel, 'p_sales');
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="p_sales" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View Chart"></i>`;
                            if (!l7 || l7 === 0) {
                                return `<span style="color:#adb5bd;font-weight:600;" title="No L7 Sales">-</span>${chartIcon}`;
                            }
                            return `<span class="p-sales-value" style="font-weight:600;color:#0d6efd;">$${Math.round(value).toLocaleString('en-US')}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                var cv = cell.getElement().querySelector('.p-sales-value'); cv = cv ? parseFloat(cv.textContent.replace(/[$,%,\s]/g, '')) : null; showMetricChart($(e.target).data('channel'), $(e.target).data('metric'), cv);
                            }
                        },
                        bottomCalc: function(values, data) {
                            let sum = 0;
                            data.forEach(function(row) {
                                sum += projectedSalesFromL7(row['L7 Sales'] || 0);
                            });
                            return sum;
                        },
                        bottomCalcFormatter: function(cell) {
                            const pSales = parseNumber(cell.getValue());
                            if (!pSales || pSales === 0) return '<strong style="color:#adb5bd;">-</strong>';
                            return `<strong style="color:#0d6efd;">$${Math.round(pSales).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "P Growth",
                        field: "P Growth",
                        hozAlign: "center",
                        sorter: "number",
                        width: 88,
                        headerTooltip: "P-Sales vs Sales: ((P-Sales − Sales) ÷ Sales) × 100. Green = P-Sales above Sales, red = below.",
                        mutator: function(value, data) {
                            return pGrowthFromRow(data);
                        },
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            const l7 = parseNumber(row['L7 Sales'] || 0);
                            const pSales = parseNumber(row['P-Sales'] != null && row['P-Sales'] !== '' ? row['P-Sales'] : projectedSalesFromL7(l7));
                            const l30 = parseNumber(row['L30 Sales'] || 0);
                            const rawPct = cell.getValue();
                            const pct = rawPct !== null && rawPct !== undefined && rawPct !== ''
                                ? parseNumber(rawPct)
                                : pSalesVsL30Pct(pSales, l30);
                            const channel = (row['Channel '] || '').trim();
                            const dotColor = pSalesVsL30Color(pSales, l30);
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="p_sales" data-p-sales="${pSales}" data-l30-sales="${l30}" style="cursor:pointer;color:${dotColor};font-size:8px;" title="${pSalesVsL30Title(pct)}"></i>`;
                            if (!l7 || l7 === 0) {
                                return `<span style="color:#adb5bd;font-weight:600;" title="No L7 Sales">-</span>${chartIcon}`;
                            }
                            return `<span style="white-space:nowrap;">${formatPSalesVsL30PctHtml(pct)}${chartIcon}</span>`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                var cv = parseNumber(cell.getRow().getData()['P-Sales']);
                                showMetricChart($(e.target).data('channel'), $(e.target).data('metric'), cv);
                            }
                        },
                        bottomCalc: function(values, data) {
                            let pSales = 0, l30 = 0;
                            data.forEach(function(row) {
                                pSales += projectedSalesFromL7(row['L7 Sales'] || 0);
                                l30 += parseNumber(row['L30 Sales'] || 0);
                            });
                            return pSalesVsL30Pct(pSales, l30);
                        },
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            if (value === null || value === undefined || !isFinite(parseNumber(value))) {
                                return '<strong style="color:#adb5bd;">-</strong>';
                            }
                            return `<strong>${formatPSalesVsL30PctHtml(parseNumber(value))}</strong>`;
                        }
                    },
                    {
                        title: "P GROI%",
                        field: "P GROI%",
                        headerTooltip: "Projected GROI% from last-7-day pace. P-Sales = (L7 ÷ 7) × 30. P GROI% = (P-Sales × GPFT%) ÷ projected COGS. Projected COGS is L30 COGS × (P-Sales ÷ L30 Sales).",
                        hozAlign: "center",
                        sorter: "number",
                        width: 80,
                        mutator: function(value, data) {
                            return pGroiPctFromRow(data);
                        },
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            const l7 = parseNumber(row['L7 Sales'] || 0);
                            const value = parseNumber(cell.getValue());
                            const channel = (row['Channel '] || '').trim();
                            const dotColor = getMetricDotColor(channel, 'p_groi_pct');
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="p_groi_pct" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View Chart"></i>`;
                            if (!l7) {
                                return `<span style="color:#adb5bd;font-weight:600;" title="No L7 Sales">-</span>${chartIcon}`;
                            }
                            return `<span style="${ammMetricCellStyle('p_groi_pct', value)}font-weight:600;">${value.toFixed(1)}%</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                var cv = cell.getElement().querySelector('span'); cv = cv ? parseFloat(cv.textContent.replace(/[$,%,\s]/g, '')) : null; showMetricChart($(e.target).data('channel'), $(e.target).data('metric'), cv);
                            }
                        },
                        bottomCalc: function(values, data) {
                            let pGross = 0, pCogs = 0;
                            data.forEach(function(row) {
                                pGross += pGrossPftFromRow(row);
                                pCogs += pCogsFromRow(row);
                            });
                            return pCogs > 0 ? (pGross / pCogs) * 100 : null;
                        },
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            if (value === null || value === undefined || !isFinite(parseNumber(value))) {
                                return '<strong style="color:#adb5bd;">-</strong>';
                            }
                            return `<strong>${parseNumber(value).toFixed(1)}%</strong>`;
                        }
                    },
                    {
                        title: "PNPFT%",
                        field: "PNPFT%",
                        headerTooltip: "Projected NPFT% from last-7-day pace — same formula as the P-Npft% badge: GPFT% − (ad spend ÷ P-Sales) × 100. P-Sales = (L7 ÷ 7) × 30.",
                        hozAlign: "center",
                        sorter: "number",
                        width: 80,
                        mutator: function(value, data) {
                            return pNpftPctFromRow(data);
                        },
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            const l7 = parseNumber(row['L7 Sales'] || 0);
                            const value = cell.getValue();
                            const channel = (row['Channel '] || '').trim();
                            const dotColor = getMetricDotColor(channel, 'p_npft');
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="p_npft" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View Chart"></i>`;
                            if (!l7 || value === null || value === undefined) {
                                return `<span style="color:#adb5bd;font-weight:600;" title="No L7 Sales">-</span>${chartIcon}`;
                            }
                            const pct = parseNumber(value);
                            return `<span style="${ammMetricCellStyle('p_npft', pct)}font-weight:600;">${pct.toFixed(1)}%</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                var cv = cell.getElement().querySelector('span'); cv = cv ? parseFloat(cv.textContent.replace(/[$,%,\s]/g, '')) : null; showMetricChart($(e.target).data('channel'), $(e.target).data('metric'), cv);
                            }
                        },
                        bottomCalc: function(values, data) {
                            let pGross = 0, pSales = 0, spend = 0;
                            data.forEach(function(row) {
                                pGross += pGrossPftFromRow(row);
                                pSales += pSalesFromRow(row);
                                spend += rowAdSpendFromRow(row);
                            });
                            return pSales > 0 ? ((pGross - spend) / pSales) * 100 : null;
                        },
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            if (value === null || value === undefined || !isFinite(parseNumber(value))) {
                                return '<strong style="color:#adb5bd;">-</strong>';
                            }
                            return `<strong>${parseNumber(value).toFixed(1)}%</strong>`;
                        }
                    },
                    {
                        title: "P NPFT",
                        field: "P NPFT",
                        visible: false,
                        hozAlign: "center",
                        sorter: "number",
                        width: 168,
                        headerTooltip: "Projected net profit $ from last-7-day pace: P-Sales × P-Npft%. P-Sales = (L7 ÷ 7) × 30. % and dot compare P NPFT $ to NPFT $: green = above NPFT $, red = below NPFT $.",
                        mutator: function(value, data) {
                            return pNetPftFromRow(data);
                        },
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            const l7 = parseNumber(row['L7 Sales'] || 0);
                            const value = parseNumber(cell.getValue() != null && cell.getValue() !== '' ? cell.getValue() : pNetPftFromRow(row));
                            const npftAmt = rowNetPftFromRow(row);
                            const channel = (row['Channel '] || '').trim();
                            const pct = pNpftAmtVsNpftPct(value, npftAmt);
                            const dotColor = pNpftAmtVsNpftColor(value, npftAmt);
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="p_npft_amt" data-p-npft="${value}" data-npft-amt="${npftAmt}" style="cursor:pointer;color:${dotColor};font-size:8px;" title="${pNpftAmtVsNpftTitle(pct)}"></i>`;
                            if (!l7 || l7 === 0) {
                                return `<span style="color:#adb5bd;font-weight:600;" title="No L7 Sales">-</span>${chartIcon}`;
                            }
                            return `<span style="white-space:nowrap;"><span class="p-npft-value" style="font-weight:600;color:#0d6efd;">$${Math.round(value).toLocaleString('en-US')}</span>${formatPSalesVsL30PctHtml(pct)}${chartIcon}</span>`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                var cv = cell.getElement().querySelector('.p-npft-value'); cv = cv ? parseFloat(cv.textContent.replace(/[$,%,\s]/g, '')) : null; showMetricChart($(e.target).data('channel'), $(e.target).data('metric'), cv);
                            }
                        },
                        bottomCalc: function(values, data) {
                            let sum = 0;
                            data.forEach(function(row) {
                                sum += pNetPftFromRow(row);
                            });
                            return sum;
                        },
                        bottomCalcFormatter: function(cell) {
                            const pAmt = parseNumber(cell.getValue());
                            if (!pAmt && pAmt !== 0) return '<strong style="color:#adb5bd;">-</strong>';
                            let npftAmt = 0;
                            let hasL7 = false;
                            try {
                                const rows = cell.getTable().getData('active') || [];
                                rows.forEach(function(row) {
                                    npftAmt += rowNetPftFromRow(row);
                                    if (parseNumber(row['L7 Sales'] || 0)) hasL7 = true;
                                });
                            } catch (e) { /* ignore */ }
                            if (!hasL7) return '<strong style="color:#adb5bd;">-</strong>';
                            return `<strong style="color:#0d6efd;">$${Math.round(pAmt).toLocaleString('en-US')}</strong>${formatPSalesVsL30PctHtml(pNpftAmtVsNpftPct(pAmt, npftAmt))}`;
                        }
                    },
                    {
                      
                        title: "Source",
                        field: "Update",
                        headerTooltip: "Data source tag for this channel. A = API, S = GS. Set in the channel's Edit modal.",
                        hozAlign: "center",
                        width: 80,
                        sorter: function(a, b) {
                            // Sort A before S before blanks, so unset rows sink to the bottom
                            const na = (a === 'A' || a === 'S') ? a : 'Z';
                            const nb = (b === 'A' || b === 'S') ? b : 'Z';
                            return na.localeCompare(nb);
                        },
                        formatter: function(cell) {
                            const raw = cell.getValue();
                            const v = (raw === 'A' || raw === 'S') ? raw : '';
                            if (!v) {
                                return '<span style="color:#adb5bd;">-</span>';
                            }
                            // Color-coded chip so the two values are scannable at a glance.
                            const bg    = v === 'A' ? '#198754' : '#fd7e14';
                            const label = v === 'A' ? 'API'     : 'GS';
                            return `<span class="badge" style="background-color:${bg};color:#fff;font-weight:600;min-width:24px;" title="${label}">${label}</span>`;
                        }
                    },
                    {
                        // Manual Promotions % entered per channel in the Edit modal.
                        title: "Promotions",
                        field: "promotions",
                        hozAlign: "center",
                        sorter: "number",
                        width: 100,
                        headerTooltip: "Manual promotions percentage — set in the channel's Edit modal.",
                        formatter: function(cell) {
                            const raw = cell.getValue();
                            if (raw === null || raw === undefined || raw === '' || isNaN(parseFloat(raw))) {
                                return '<span style="color:#adb5bd;">-</span>';
                            }
                            const val = parseFloat(raw);
                            return `<span style="font-weight:600;">${val.toLocaleString('en-US', {minimumFractionDigits: 0, maximumFractionDigits: 2})}%</span>`;
                        }
                    },
                    {
                        title: "Spend",
                        field: "Total Ad Spend",
                        hozAlign: "center",
                        sorter: "number",
                        visible: true,
                        formatter: function(cell) {
                            const totalSpent = parseNumber(cell.getValue() || 0);
                            const channel = (cell.getRow().getData()['Channel '] || '').trim();
                            if (totalSpent === 0) return '-';
                            const dotColor = getMetricDotColor(channel, 'ad_spend');
                            // Trend dot compares the last two COMPLETED Pacific days — today's
                            // PT date is excluded because its rolling-window value is still
                            // accruing. The tooltip surfaces that date so the value is read
                            // correctly across timezones.
                            const dotTitle = `Data through ${caLastCompletedMdy()} PT. Click to view chart.`;
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="ad_spend" style="cursor:pointer;color:${dotColor};font-size:8px;" title="${dotTitle}"></i>`;
                            const infoIcon =
                                `<i class="fas fa-chevron-right ad-spend-breakdown-toggle ms-1" style="cursor:pointer;color:#17a2b8;font-size:10px;" title="Toggle Spend Breakdown"></i>`;
                            return `<span style="font-weight:600;">$${Math.round(totalSpent).toLocaleString('en-US')}</span>${chartIcon}${infoIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                var cv = cell.getElement().querySelector('span'); cv = cv ? parseFloat(cv.textContent.replace(/[$,%,\s]/g, '')) : null; showMetricChart($(e.target).data('channel'), $(e.target).data('metric'), cv);
                            }
                            if (e.target.classList.contains('ad-spend-breakdown-toggle')) {
                                e.stopPropagation();
                                toggleAdSpendBreakdownColumns();
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            return `<strong>$${parseNumber(value).toFixed(0)}</strong>`;
                        }
                    },
                    {
                        title: "views",
                        field: "Total Views",
                        headerTooltip: "Listing/Map traffic. eBay 2 = /ebay2-tabulator-view Views (child rows, E Stock &gt; 0, PARENT excluded). Reverb views are ÷ 1000 (then CVR uses that scaled total).",
                        hozAlign: "center",
                        sorter: "number",
                        width: 100,
                        visible: true,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value == null || value === 0) return '-';
                            const dotColor = getMetricDotColor(channel, 'total_views');
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="total_views" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View Chart"></i>`;
                            return `<span>${value.toLocaleString('en-US')}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                const span = cell.getElement().querySelector('span');
                                const cv = span ? parseFloat(span.textContent.replace(/[,$%\s]/g, '')) : null;
                                showMetricChart($(e.target).data('channel'), $(e.target).data('metric'), cv);
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            return `<strong>${parseNumber(value).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "CVR",
                        field: "CVR",
                        headerTooltip: "Per channel: server CVR when present (eBay 2 = eBay L30 ÷ Views, same as /ebay2-tabulator-view CVR). Else Qty ÷ Total Views. Reverb Views are ÷ 1000 first. Compare to &quot;AD CVR&quot; (ad sold ÷ clicks).",
                        hozAlign: "center",
                        sorter: function(a, b, aRow, bRow) {
                            // Prefer server-provided CVR (Temu / Temu 2 / Temu 3 use temu_l30 ÷ product_clicks
                            // to match the decrease-page badges exactly); fall back
                            // to Qty ÷ Total Views for channels that don't pre-compute it.
                            const aData = aRow.getData(), bData = bRow.getData();
                            const aServer = (aData['CVR'] !== undefined && aData['CVR'] !== null && aData['CVR'] !== '');
                            const bServer = (bData['CVR'] !== undefined && bData['CVR'] !== null && bData['CVR'] !== '');
                            const cvrA = aServer ? parseNumber(aData['CVR']) : (parseNumber(aData['Total Views'] || 0) > 0 ? (parseNumber(aData['Qty'] || 0) / parseNumber(aData['Total Views'])) * 100 : 0);
                            const cvrB = bServer ? parseNumber(bData['CVR']) : (parseNumber(bData['Total Views'] || 0) > 0 ? (parseNumber(bData['Qty'] || 0) / parseNumber(bData['Total Views'])) * 100 : 0);
                            return cvrA - cvrB;
                        },
                        width: 70,
                        visible: true,
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            const channel = (row['Channel '] || '').trim();
                            const views = parseNumber(row['Total Views'] || 0);
                           
                            const serverCvr = row['CVR'];
                            const hasServerCvr = (serverCvr !== undefined && serverCvr !== null && serverCvr !== '');
                            const isAmazon = snapshotChannelKey(channel) === 'amazon';
                            const isReverb = snapshotChannelKey(channel) === 'reverb';
                            let pct;
                            if (isAmazon || isReverb) {
                                if (views === 0) return '-';
                                const units = parseNumber(row['Qty'] || 0) || parseNumber(row['L30 Orders'] || 0);
                                pct = (units / views) * 100;
                            } else if (hasServerCvr) {
                                pct = parseNumber(serverCvr);
                            } else {
                                if (views === 0) return '-';
                                const qty = parseNumber(row['Qty'] || 0);
                                pct = (qty / views) * 100;
                            }
                            const dotColor = getMetricDotColor(channel, 'cvr');
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="cvr" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View CVR trend"></i>`;
                            return `<span style="font-weight:600;color:${dotColor};">${(Math.round(pct * 100) / 100).toFixed(2)}%</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                var span = cell.getElement().querySelector('span');
                                var cv = span ? parseFloat(span.textContent.replace(/[$,%,\s]/g, '')) : null;
                                showMetricChart($(e.target).data('channel'), $(e.target).data('metric'), cv);
                            }
                        },
                        bottomCalc: function(values, data) {
                            let totalQty = 0, totalViews = 0;
                            data.forEach(function(row) {
                                totalQty += parseNumber(row['Qty'] || 0);
                                totalViews += parseNumber(row['Total Views'] || 0);
                            });
                            if (totalViews === 0) return null;
                            return (totalQty / totalViews) * 100;
                        },
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            if (value === null || value === undefined || value === '' || !isFinite(parseNumber(value))) {
                                return '<strong>-</strong>';
                            }
                            return '<strong>' + (Math.round(parseNumber(value) * 100) / 100).toFixed(2) + '%</strong>';
                        }
                    },
                    {
                        title: "Orders",
                        field: "L30 Orders",
                        headerTooltip: "Rolling order count per channel. Amz = {{ (int) \App\Http\Controllers\Sales\AmazonSalesController::DAILY_SALES_WINDOW_DAYS }} days Pacific — same as Amz Daily Sales (Canceled/Cancelled excluded).",
                        hozAlign: "center",
                        sorter: "number",
                        width: 100,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const channel = (cell.getRow().getData()['Channel '] || '').trim();
                            const dotColor = getMetricDotColor(channel, 'l30_orders');
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="l30_orders" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View Chart"></i>`;
                            return `<span>${value.toLocaleString('en-US')}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                var cv = cell.getElement().querySelector('span'); cv = cv ? parseFloat(cv.textContent.replace(/[$,%,\s]/g, '')) : null; showMetricChart($(e.target).data('channel'), $(e.target).data('metric'), cv);
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            return `<strong>${parseNumber(value).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "Qty items",
                        field: "Qty",
                        hozAlign: "center",
                        sorter: "number",
                        width: 90,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const channel = (cell.getRow().getData()['Channel '] || '').trim();
                            const dotColor = getMetricDotColor(channel, 'qty');
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="qty" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View Chart"></i>`;
                            return `<span>${value.toLocaleString('en-US')}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                var cv = cell.getElement().querySelector('span'); cv = cv ? parseFloat(cv.textContent.replace(/[$,%,\s]/g, '')) : null; showMetricChart($(e.target).data('channel'), $(e.target).data('metric'), cv);
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            return `<strong>${parseNumber(value).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "G ROI %",
                        field: "G Roi",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const channel = (cell.getRow().getData()['Channel '] || '').trim();
                            const dotColor = getMetricDotColor(channel, 'groi');
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="groi" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View Chart"></i>`;
                            return `<span style="${ammMetricCellStyle('groi', value)}font-weight:600;">${value.toFixed(1)}%</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                var cv = cell.getElement().querySelector('span'); cv = cv ? parseFloat(cv.textContent.replace(/[$,%,\s]/g, '')) : null; showMetricChart($(e.target).data('channel'), $(e.target).data('metric'), cv);
                            }
                        }
                    },
                    {
                        title: "GPFT%",
                        field: "Gprofit%",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const channel = (cell.getRow().getData()['Channel '] || '').trim();
                            const dotColor = getMetricDotColor(channel, 'gprofit');
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="gprofit" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View Chart"></i>`;
                            return `<span style="${ammMetricCellStyle('gprofit', value)}font-weight:600;">${value.toFixed(1)}%</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                var cv = cell.getElement().querySelector('span'); cv = cv ? parseFloat(cv.textContent.replace(/[$,%,\s]/g, '')) : null; showMetricChart($(e.target).data('channel'), $(e.target).data('metric'), cv);
                            }
                        },
                        bottomCalc: function(values, data) {
                            let gpDollars = 0, totalL30 = 0;
                            data.forEach(function(row) {
                                const l30 = parseNumber(row['L30 Sales'] || 0);
                                const gp = parseNumber(row['Gprofit%'] || 0);
                                gpDollars += (gp / 100) * l30;
                                totalL30 += l30;
                            });
                            return totalL30 > 0 ? (gpDollars / totalL30) * 100 : 0;
                        },
                        bottomCalcFormatter: function(cell) {
                            const v = parseNumber(cell.getValue());
                            return `<strong>${v.toFixed(1)}%</strong>`;
                        }
                    },
                    {
                        title: "Gross PFT",
                        field: "_gross_pft",
                        visible: false,
                        hozAlign: "center",
                        sorter: "number",
                        mutator: function(value, data) {
                            const l30 = parseNumber(data['L30 Sales'] || 0);
                            const gp = parseNumber(data['Gprofit%'] || 0);
                            return (gp / 100) * l30;
                        },
                        formatter: function(cell) {
                            const v = parseNumber(cell.getValue());
                            return `<span>$${Math.round(v).toLocaleString('en-US')}</span>`;
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            return `<strong>$${Math.round(parseNumber(value)).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "Ads %",
                        field: "Ads%",
                        responsive: 0,
                        minWidth: 92,
                        hozAlign: "center",
                        sorter: "number",
                        headerTooltip: "Ads% — arrow opens that channel's ads page.",
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const channelRaw = (rowData['Channel '] || '').trim();
                            const channel = channelRaw.toLowerCase();
                            const adsUrl = channelAdsPageUrl(channelRaw);
                            const adsArrow = adsUrl
                                ? `<a href="${adsUrl}" target="_blank" rel="noopener noreferrer" class="ads-page-link" title="Open ${channelRaw} ads page"><i class="fas fa-arrow-up-right-from-square"></i></a>`
                                : '';

                            let adsPercent = 0;
                            if (channel === 'walmart' || channel === 'topdawg' || channel ===
                                'shopifyb2c') {
                                adsPercent = parseNumber(rowData['TACOS %'] || 0);
                            } else {
                                adsPercent = parseNumber(cell.getValue() || 0);
                            }

                            const dotColor = getMetricDotColor(channelRaw, 'ads_pct');
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channelRaw}" data-metric="ads_pct" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View Chart"></i>`;
                            return `<span style="${ammAdsPctBandStyle(adsPercent)}font-weight:600;">${adsPercent.toFixed(1)}%</span>${adsArrow}${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if ($(e.target).closest('.ads-page-link').length) {
                                e.stopPropagation();
                                return;
                            }
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                var cv = cell.getElement().querySelector('span'); cv = cv ? parseFloat(cv.textContent.replace(/[$,%,\s]/g, '')) : null; showMetricChart($(e.target).data('channel'), $(e.target).data('metric'), cv);
                            }
                        }
                    },
                    {
                        title: "NP$",
                        field: "NP$",
                        headerTooltip: "Net Profit Amount = L30 Sales × NPFT%",
                        hozAlign: "center",
                        sorter: "number",
                        width: 100,
                        visible: false,
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const l30Sales = parseNumber(rowData['L30 Sales'] || 0);
                            const npftPercent = parseNumber(rowData['N PFT'] || 0);
                            const netProfitAmount = (l30Sales * npftPercent) / 100;
                            
                            if (netProfitAmount === 0) {
                                return '<span style="color:#adb5bd;">—</span>';
                            }
                            
                            const color = netProfitAmount > 0 ? '#198754' : '#dc3545';
                            return `<span style="font-weight:600;color:${color};">$${Math.round(netProfitAmount).toLocaleString('en-US')}</span>`;
                        },
                        bottomCalc: function(values, data) {
                            let totalNetProfit = 0;
                            data.forEach(function(row) {
                                const l30Sales = parseNumber(row['L30 Sales'] || 0);
                                const npftPercent = parseNumber(row['N PFT'] || 0);
                                totalNetProfit += (l30Sales * npftPercent) / 100;
                            });
                            return totalNetProfit;
                        },
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            if (!value || value === 0) return '<strong>—</strong>';
                            const color = value > 0 ? '#198754' : '#dc3545';
                            return `<strong style="color:${color};">$${Math.round(parseNumber(value)).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "NROI %",
                        field: "N ROI",
                        hozAlign: "center",
                        // Most channels: (Gross Profit − Ad Spend) / COGS × 100.
                        // Temu / Temu 2 / Temu 3: GROI% − Ads% (same as /temu-decrease after Ads reduce).
                        sorter: "number",
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const channel = (cell.getRow().getData()['Channel '] || '').trim();
                            const dotColor = getMetricDotColor(channel, 'nroi');
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="nroi" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View Chart"></i>`;
                            return `<span style="${ammMetricCellStyle('nroi', value)}font-weight:600;">${value.toFixed(1)}%</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                var cv = cell.getElement().querySelector('span'); cv = cv ? parseFloat(cv.textContent.replace(/[$,%,\s]/g, '')) : null; showMetricChart($(e.target).data('channel'), $(e.target).data('metric'), cv);
                            }
                        }
                    },
                    {
                        title: "NPFT%",
                        field: "N PFT",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const channel = (cell.getRow().getData()['Channel '] || '').trim();
                            const dotColor = getMetricDotColor(channel, 'npft');
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="npft" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View Chart"></i>`;
                            return `<span style="${ammMetricCellStyle('npft', value)}font-weight:600;">${value.toFixed(1)}%</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                var cv = cell.getElement().querySelector('span'); cv = cv ? parseFloat(cv.textContent.replace(/[$,%,\s]/g, '')) : null; showMetricChart($(e.target).data('channel'), $(e.target).data('metric'), cv);
                            }
                        }
                    },
                    {
                        title: "KW $",
                        field: "KW Spent",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const dotColor = getMetricDotColor(channel, 'ad_spend');
                            const chartIcon = `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="kw" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View Chart"></i>`;
                            return `<span style="font-weight:600;color:#198754;">$${value.toLocaleString('en-US', {minimumFractionDigits: 0, maximumFractionDigits: 0})}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), 'spend');
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            return `<strong style="color:#198754;">$${parseNumber(value).toFixed(0)}</strong>`;
                        }
                    },
                    {
                        title: "PT $",
                        field: "PT Spent",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const dotColor = getMetricDotColor(channel, 'ad_spend');
                            const chartIcon = `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="pt" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View Chart"></i>`;
                            return `<span style="font-weight:600;color:#0d6efd;">$${value.toLocaleString('en-US', {minimumFractionDigits: 0, maximumFractionDigits: 0})}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), 'spend');
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            return `<strong style="color:#0d6efd;">$${parseNumber(value).toFixed(0)}</strong>`;
                        }
                    },
                    {
                        title: "HL $",
                        field: "HL Spent",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const dotColor = getMetricDotColor(channel, 'ad_spend');
                            const chartIcon = `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="hl" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View Chart"></i>`;
                            return `<span style="font-weight:600;color:#dc3545;">$${value.toLocaleString('en-US', {minimumFractionDigits: 0, maximumFractionDigits: 0})}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), 'spend');
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            return `<strong style="color:#dc3545;">$${parseNumber(value).toFixed(0)}</strong>`;
                        }
                    },
                    {
                        title: "PMT $",
                        field: "PMT Spent",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const dotColor = getMetricDotColor(channel, 'ad_spend');
                            const chartIcon = `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="pmt" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View Chart"></i>`;
                            return `<span style="font-weight:600;color:#ffc107;">$${value.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), 'spend');
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            return `<strong style="color:#ffc107;">$${parseNumber(value).toFixed(2)}</strong>`;
                        }
                    },
                    {
                        title: "Shop $",
                        field: "Shopping Spent",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const dotColor = getMetricDotColor(channel, 'ad_spend');
                            const chartIcon = `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="shopping" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View Chart"></i>`;
                            return `<span style="font-weight:600;color:#4285f4;">$${value.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), 'spend');
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            return `<strong style="color:#4285f4;">$${parseNumber(value).toFixed(2)}</strong>`;
                        }
                    },
                    {
                        title: "SERP $",
                        field: "SERP Spent",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const dotColor = getMetricDotColor(channel, 'ad_spend');
                            const chartIcon = `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="serp" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View Chart"></i>`;
                            return `<span style="font-weight:600;color:#6f42c1;">$${value.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), 'spend');
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            return `<strong style="color:#6f42c1;">$${parseNumber(value).toFixed(2)}</strong>`;
                        }
                    },
                    {
                        title: "AD CLICKS",
                        field: "clicks",
                        hozAlign: "center",
                        sorter: "number",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const channel = (cell.getRow().getData()['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const dotColor = getMetricDotColor(channel, 'clicks');
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="clicks" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View Chart"></i>`;
                            const infoIcon =
                                `<i class="fas fa-chevron-right clicks-breakdown-toggle ms-1" style="cursor:pointer;color:#17a2b8;font-size:10px;" title="Toggle Clicks Breakdown"></i>`;
                            return `<span style="font-weight:600;">${value.toLocaleString('en-US')}</span>${chartIcon}${infoIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                var cv = cell.getElement().querySelector('span'); cv = cv ? parseFloat(cv.textContent.replace(/[$,%,\s]/g, '')) : null; showMetricChart($(e.target).data('channel'), $(e.target).data('metric'), cv);
                            }
                            if (e.target.classList.contains('clicks-breakdown-toggle')) {
                                e.stopPropagation();
                                toggleClicksBreakdownColumns();
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            return `<strong>${parseNumber(value).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    // Hidden Clicks Breakdown Columns
                    {
                        title: "KW Clicks",
                        field: "KW Clicks",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="kw" data-metric="clicks" style="cursor:pointer;color:${getMetricDotColor(channel, 'clicks')};font-size:8px;" title="View Chart"></i>`;
                            return `<span style="font-weight:600;color:#198754;">${value.toLocaleString('en-US')}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), $(e.target).data('metric'));
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            return `<strong style="color:#198754;">${parseNumber(cell.getValue()).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "PT Clicks",
                        field: "PT Clicks",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="pt" data-metric="clicks" style="cursor:pointer;color:${getMetricDotColor(channel, 'clicks')};font-size:8px;" title="View Chart"></i>`;
                            return `<span style="font-weight:600;color:#0d6efd;">${value.toLocaleString('en-US')}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), $(e.target).data('metric'));
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            return `<strong style="color:#0d6efd;">${parseNumber(cell.getValue()).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "HL Clicks",
                        field: "HL Clicks",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="hl" data-metric="clicks" style="cursor:pointer;color:${getMetricDotColor(channel, 'clicks')};font-size:8px;" title="View Chart"></i>`;
                            return `<span style="font-weight:600;color:#dc3545;">${value.toLocaleString('en-US')}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), $(e.target).data('metric'));
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            return `<strong style="color:#dc3545;">${parseNumber(cell.getValue()).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "PMT Clicks",
                        field: "PMT Clicks",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="pmt" data-metric="clicks" style="cursor:pointer;color:${getMetricDotColor(channel, 'clicks')};font-size:8px;" title="View Chart"></i>`;
                            return `<span style="font-weight:600;color:#ffc107;">${value.toLocaleString('en-US')}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), $(e.target).data('metric'));
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            return `<strong style="color:#ffc107;">${parseNumber(cell.getValue()).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "Shop Clicks",
                        field: "Shopping Clicks",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="shopping" data-metric="clicks" style="cursor:pointer;color:${getMetricDotColor(channel, 'clicks')};font-size:8px;" title="View Chart"></i>`;
                            return `<span style="font-weight:600;color:#4285f4;">${value.toLocaleString('en-US')}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), $(e.target).data('metric'));
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            return `<strong style="color:#4285f4;">${parseNumber(cell.getValue()).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "SERP Clicks",
                        field: "SERP Clicks",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="serp" data-metric="clicks" style="cursor:pointer;color:${getMetricDotColor(channel, 'clicks')};font-size:8px;" title="View Chart"></i>`;
                            return `<span style="font-weight:600;color:#6f42c1;">${value.toLocaleString('en-US')}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), $(e.target).data('metric'));
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            return `<strong style="color:#6f42c1;">${parseNumber(cell.getValue()).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "AD SALES",
                        field: "Ad Sales",
                        hozAlign: "center",
                        sorter: "number",
                        width: 120,
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const channel = (cell.getRow().getData()['Channel '] || '').trim();
                            if (!value || value === 0) return '-';
                            const dotColor = getMetricDotColor(channel, 'ad_sales');
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="ad_sales" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View Chart"></i>`;
                            const infoIcon =
                                `<i class="fas fa-chevron-right ad-sales-breakdown-toggle ms-1" style="cursor:pointer;color:#17a2b8;font-size:10px;" title="Toggle Ad Sales Breakdown"></i>`;
                            return `<span style="font-weight:600;">$${Math.round(value).toLocaleString('en-US')}</span>${chartIcon}${infoIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                var cv = cell.getElement().querySelector('span'); cv = cv ? parseFloat(cv.textContent.replace(/[$,%,\s]/g, '')) : null; showMetricChart($(e.target).data('channel'), $(e.target).data('metric'), cv);
                            }
                            if (e.target.classList.contains('ad-sales-breakdown-toggle')) {
                                e.stopPropagation();
                                toggleAdSalesBreakdownColumns();
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            if (!value || value === 0) return '<strong>-</strong>';
                            return `<strong>$${Math.round(parseNumber(value)).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    // Hidden Ad Sales Breakdown Columns
                    {
                        title: "KW Sales",
                        field: "KW Sales",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="kw" data-metric="sales" style="cursor:pointer;color:${getMetricDotColor(channel, 'ad_sales')};font-size:8px;" title="View Chart"></i>`;
                            return `<span style="font-weight:600;color:#198754;">$${Math.round(value).toLocaleString('en-US')}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), $(e.target).data('metric'));
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            return `<strong style="color:#198754;">$${Math.round(parseNumber(cell.getValue())).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "PT Sales",
                        field: "PT Sales",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="pt" data-metric="sales" style="cursor:pointer;color:${getMetricDotColor(channel, 'ad_sales')};font-size:8px;" title="View Chart"></i>`;
                            return `<span style="font-weight:600;color:#0d6efd;">$${Math.round(value).toLocaleString('en-US')}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), $(e.target).data('metric'));
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            return `<strong style="color:#0d6efd;">$${Math.round(parseNumber(cell.getValue())).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "HL Sales",
                        field: "HL Sales",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="hl" data-metric="sales" style="cursor:pointer;color:${getMetricDotColor(channel, 'ad_sales')};font-size:8px;" title="View Chart"></i>`;
                            return `<span style="font-weight:600;color:#dc3545;">$${Math.round(value).toLocaleString('en-US')}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), $(e.target).data('metric'));
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            return `<strong style="color:#dc3545;">$${Math.round(parseNumber(cell.getValue())).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "PMT Sales",
                        field: "PMT Sales",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="pmt" data-metric="sales" style="cursor:pointer;color:${getMetricDotColor(channel, 'ad_sales')};font-size:8px;" title="View Chart"></i>`;
                            return `<span style="font-weight:600;color:#ffc107;">$${Math.round(value).toLocaleString('en-US')}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), $(e.target).data('metric'));
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            return `<strong style="color:#ffc107;">$${Math.round(parseNumber(cell.getValue())).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "Shop Sales",
                        field: "Shopping Sales",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="shopping" data-metric="sales" style="cursor:pointer;color:${getMetricDotColor(channel, 'ad_sales')};font-size:8px;" title="View Chart"></i>`;
                            return `<span style="font-weight:600;color:#4285f4;">$${Math.round(value).toLocaleString('en-US')}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), $(e.target).data('metric'));
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            return `<strong style="color:#4285f4;">$${Math.round(parseNumber(cell.getValue())).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "SERP Sales",
                        field: "SERP Sales",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="serp" data-metric="sales" style="cursor:pointer;color:${getMetricDotColor(channel, 'ad_sales')};font-size:8px;" title="View Chart"></i>`;
                            return `<span style="font-weight:600;color:#6f42c1;">$${Math.round(value).toLocaleString('en-US')}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), $(e.target).data('metric'));
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            return `<strong style="color:#6f42c1;">$${Math.round(parseNumber(cell.getValue())).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "AD SOLD",
                        field: "ad_sold",
                        hozAlign: "center",
                        sorter: "number",
                        width: 100,
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const channel = (cell.getRow().getData()['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const dotColor = getMetricDotColor(channel, 'ad_sold');
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="ad_sold" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View Chart"></i>`;
                            const infoIcon =
                                `<i class="fas fa-chevron-right ad-sold-breakdown-toggle ms-1" style="cursor:pointer;color:#17a2b8;font-size:10px;" title="Toggle Ad Sold Breakdown"></i>`;
                            return `<span style="font-weight:600;">${value.toLocaleString('en-US')}</span>${chartIcon}${infoIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                var cv = cell.getElement().querySelector('span'); cv = cv ? parseFloat(cv.textContent.replace(/[$,%,\s]/g, '')) : null; showMetricChart($(e.target).data('channel'), $(e.target).data('metric'), cv);
                            }
                            if (e.target.classList.contains('ad-sold-breakdown-toggle')) {
                                e.stopPropagation();
                                toggleAdSoldBreakdownColumns();
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            return `<strong>${parseNumber(value).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    // Hidden Ad Sold Breakdown Columns
                    {
                        title: "KW Sold",
                        field: "KW Sold",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="kw" data-metric="sold" style="cursor:pointer;color:${getMetricDotColor(channel, 'ad_sold')};font-size:8px;" title="View Chart"></i>`;
                            return `<span style="font-weight:600;color:#198754;">${value.toLocaleString('en-US')}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), $(e.target).data('metric'));
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            return `<strong style="color:#198754;">${parseNumber(cell.getValue()).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "PT Sold",
                        field: "PT Sold",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="pt" data-metric="sold" style="cursor:pointer;color:${getMetricDotColor(channel, 'ad_sold')};font-size:8px;" title="View Chart"></i>`;
                            return `<span style="font-weight:600;color:#0d6efd;">${value.toLocaleString('en-US')}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), $(e.target).data('metric'));
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            return `<strong style="color:#0d6efd;">${parseNumber(cell.getValue()).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "HL Sold",
                        field: "HL Sold",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="hl" data-metric="sold" style="cursor:pointer;color:${getMetricDotColor(channel, 'ad_sold')};font-size:8px;" title="View Chart"></i>`;
                            return `<span style="font-weight:600;color:#dc3545;">${value.toLocaleString('en-US')}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), $(e.target).data('metric'));
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            return `<strong style="color:#dc3545;">${parseNumber(cell.getValue()).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "PMT Sold",
                        field: "PMT Sold",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="pmt" data-metric="sold" style="cursor:pointer;color:${getMetricDotColor(channel, 'ad_sold')};font-size:8px;" title="View Chart"></i>`;
                            return `<span style="font-weight:600;color:#ffc107;">${value.toLocaleString('en-US')}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), $(e.target).data('metric'));
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            return `<strong style="color:#ffc107;">${parseNumber(cell.getValue()).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "Shop Sold",
                        field: "Shopping Sold",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="shopping" data-metric="sold" style="cursor:pointer;color:${getMetricDotColor(channel, 'ad_sold')};font-size:8px;" title="View Chart"></i>`;
                            return `<span style="font-weight:600;color:#4285f4;">${value.toLocaleString('en-US')}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), $(e.target).data('metric'));
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            return `<strong style="color:#4285f4;">${parseNumber(cell.getValue()).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "SERP Sold",
                        field: "SERP Sold",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="serp" data-metric="sold" style="cursor:pointer;color:${getMetricDotColor(channel, 'ad_sold')};font-size:8px;" title="View Chart"></i>`;
                            return `<span style="font-weight:600;color:#6f42c1;">${value.toLocaleString('en-US')}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), $(e.target).data('metric'));
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            return `<strong style="color:#6f42c1;">${parseNumber(cell.getValue()).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "ACOS",
                        field: "ACOS",
                        hozAlign: "center",
                        sorter: "number",
                        width: 90,
                        visible: false,
                        formatter: function(cell) {
                            const value = asPercent(cell.getValue());
                            const channel = (cell.getRow().getData()['Channel '] || '').trim();
                            if (!value || value === 0) return '-';
                            const dotColor = getMetricDotColor(channel, 'acos');
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="acos" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View Chart"></i>`;
                            const infoIcon =
                                `<i class="fas fa-chevron-right acos-breakdown-toggle ms-1" style="cursor:pointer;color:#17a2b8;font-size:10px;" title="Toggle ACOS Breakdown"></i>`;
                            return `<span style="font-weight:600;">${value.toFixed(1)}%</span>${chartIcon}${infoIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                var cv = cell.getElement().querySelector('span'); cv = cv ? parseFloat(cv.textContent.replace(/[$,%,\s]/g, '')) : null; showMetricChart($(e.target).data('channel'), $(e.target).data('metric'), cv);
                            }
                            if (e.target.classList.contains('acos-breakdown-toggle')) {
                                e.stopPropagation();
                                toggleAcosBreakdownColumns();
                            }
                        },
                        bottomCalc: function(values, data) {
                            let totalSpend = 0,
                                totalAdSales = 0;
                            data.forEach(row => {
                                totalSpend += parseNumber(row['Total Ad Spend'] || 0);
                                totalAdSales += parseNumber(row['Ad Sales'] || 0);
                            });
                            return totalAdSales > 0 ? (totalSpend / totalAdSales) * 100 : 0;
                        },
                        bottomCalcFormatter: function(cell) {
                            const value = asPercent(cell.getValue());
                            if (!value || value === 0) return '<strong>-</strong>';
                            return `<strong>${value.toFixed(1)}%</strong>`;
                        }
                    },
                    // Hidden ACOS Breakdown Columns
                    {
                        title: "KW ACOS",
                        field: "KW ACOS",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = asPercent(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="kw" data-metric="acos" style="cursor:pointer;color:${getMetricDotColor(channel, 'acos')};font-size:8px;" title="View ACOS Chart"></i>`;
                            return `<span style="font-weight:600;color:#198754;">${value.toFixed(1)}%</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), $(e.target).data('metric'));
                            }
                        }
                    },
                    {
                        title: "PT ACOS",
                        field: "PT ACOS",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = asPercent(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="pt" data-metric="acos" style="cursor:pointer;color:${getMetricDotColor(channel, 'acos')};font-size:8px;" title="View ACOS Chart"></i>`;
                            return `<span style="font-weight:600;color:#0d6efd;">${value.toFixed(1)}%</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), $(e.target).data('metric'));
                            }
                        }
                    },
                    {
                        title: "HL ACOS",
                        field: "HL ACOS",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = asPercent(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="hl" data-metric="acos" style="cursor:pointer;color:${getMetricDotColor(channel, 'acos')};font-size:8px;" title="View ACOS Chart"></i>`;
                            return `<span style="font-weight:600;color:#dc3545;">${value.toFixed(1)}%</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), $(e.target).data('metric'));
                            }
                        }
                    },
                    {
                        title: "PMT ACOS",
                        field: "PMT ACOS",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = asPercent(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="pmt" data-metric="acos" style="cursor:pointer;color:${getMetricDotColor(channel, 'acos')};font-size:8px;" title="View ACOS Chart"></i>`;
                            return `<span style="font-weight:600;color:#ffc107;">${value.toFixed(1)}%</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), $(e.target).data('metric'));
                            }
                        }
                    },
                    {
                        title: "Shop ACOS",
                        field: "Shopping ACOS",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = asPercent(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="shopping" data-metric="acos" style="cursor:pointer;color:${getMetricDotColor(channel, 'acos')};font-size:8px;" title="View ACOS Chart"></i>`;
                            return `<span style="font-weight:600;color:#4285f4;">${value.toFixed(1)}%</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), $(e.target).data('metric'));
                            }
                        }
                    },
                    {
                        title: "SERP ACOS",
                        field: "SERP ACOS",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = asPercent(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="serp" data-metric="acos" style="cursor:pointer;color:${getMetricDotColor(channel, 'acos')};font-size:8px;" title="View ACOS Chart"></i>`;
                            return `<span style="font-weight:600;color:#6f42c1;">${value.toFixed(1)}%</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), $(e.target).data('metric'));
                            }
                        }
                    },
                    {
                        title: "AD CVR",
                        field: "Ads CVR",
                        hozAlign: "center",
                        sorter: "number",
                        width: 100,
                        visible: false,
                        formatter: function(cell) {
                            const value = asPercent(cell.getValue());
                            const channel = (cell.getRow().getData()['Channel '] || '').trim();
                            if (!value || value === 0) return '-';
                            const dotColor = getMetricDotColor(channel, 'ads_cvr');
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="ads_cvr" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View Chart"></i>`;
                            const infoIcon =
                                `<i class="fas fa-chevron-right cvr-breakdown-toggle ms-1" style="cursor:pointer;color:#17a2b8;font-size:10px;" title="Toggle CVR Breakdown"></i>`;
                            return `<span style="font-weight:600;">${value.toFixed(1)}%</span>${chartIcon}${infoIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                var cv = cell.getElement().querySelector('span'); cv = cv ? parseFloat(cv.textContent.replace(/[$,%,\s]/g, '')) : null; showMetricChart($(e.target).data('channel'), $(e.target).data('metric'), cv);
                            }
                            if (e.target.classList.contains('cvr-breakdown-toggle')) {
                                e.stopPropagation();
                                toggleCvrBreakdownColumns();
                            }
                        },
                        bottomCalc: function(values, data, calcParams) {
                            let totalAdSold = 0;
                            let totalClicks = 0;
                            data.forEach(row => {
                                totalAdSold += parseNumber(row.ad_sold || 0);
                                totalClicks += parseNumber(row.clicks || 0);
                            });
                            return totalClicks > 0 ? ((totalAdSold / totalClicks) * 100) : 0;
                        },
                        bottomCalcFormatter: function(cell) {
                            const value = asPercent(cell.getValue());
                            if (!value || value === 0) return '<strong>-</strong>';
                            return `<strong>${value.toFixed(1)}%</strong>`;
                        }
                    },
                    // Hidden CVR Breakdown Columns
                    {
                        title: "KW CVR",
                        field: "KW CVR",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = asPercent(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="kw" data-metric="cvr" style="cursor:pointer;color:${getMetricDotColor(channel, 'ads_cvr')};font-size:8px;" title="View CVR Chart"></i>`;
                            return `<span style="font-weight:600;color:#198754;">${value.toFixed(1)}%</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), $(e.target).data('metric'));
                            }
                        }
                    },
                    {
                        title: "PT CVR",
                        field: "PT CVR",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = asPercent(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="pt" data-metric="cvr" style="cursor:pointer;color:${getMetricDotColor(channel, 'ads_cvr')};font-size:8px;" title="View CVR Chart"></i>`;
                            return `<span style="font-weight:600;color:#0d6efd;">${value.toFixed(1)}%</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), $(e.target).data('metric'));
                            }
                        }
                    },
                    {
                        title: "HL CVR",
                        field: "HL CVR",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = asPercent(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="hl" data-metric="cvr" style="cursor:pointer;color:${getMetricDotColor(channel, 'ads_cvr')};font-size:8px;" title="View CVR Chart"></i>`;
                            return `<span style="font-weight:600;color:#dc3545;">${value.toFixed(1)}%</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), $(e.target).data('metric'));
                            }
                        }
                    },
                    {
                        title: "PMT CVR",
                        field: "PMT CVR",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = asPercent(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="pmt" data-metric="cvr" style="cursor:pointer;color:${getMetricDotColor(channel, 'ads_cvr')};font-size:8px;" title="View CVR Chart"></i>`;
                            return `<span style="font-weight:600;color:#ffc107;">${value.toFixed(1)}%</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), $(e.target).data('metric'));
                            }
                        }
                    },
                    {
                        title: "Shop CVR",
                        field: "Shopping CVR",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = asPercent(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="shopping" data-metric="cvr" style="cursor:pointer;color:${getMetricDotColor(channel, 'ads_cvr')};font-size:8px;" title="View CVR Chart"></i>`;
                            return `<span style="font-weight:600;color:#4285f4;">${value.toFixed(1)}%</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), $(e.target).data('metric'));
                            }
                        }
                    },
                    {
                        title: "SERP CVR",
                        field: "SERP CVR",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = asPercent(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="serp" data-metric="cvr" style="cursor:pointer;color:${getMetricDotColor(channel, 'ads_cvr')};font-size:8px;" title="View CVR Chart"></i>`;
                            return `<span style="font-weight:600;color:#6f42c1;">${value.toFixed(1)}%</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), $(e.target).data('metric'));
                            }
                        }
                    },
                    {
                        title: "Shipping Health",
                        field: "Shipping Health",
                        hozAlign: "center",
                        sorter: "number",
                        width: 110,
                        visible: false,
                        formatter: function(cell) {
                            const v = cell.getValue();
                            if (v == null || v === '' || v === '-') return '-';
                            return typeof v === 'number' ? (v + '%') : v;
                        }
                    },
                    {
                        title: "CC Health",
                        field: "CC Health",
                        hozAlign: "center",
                        sorter: "number",
                        width: 90,
                        visible: false,
                        formatter: function(cell) {
                            const v = cell.getValue();
                            if (v == null || v === '' || v === '-') return '-';
                            return typeof v === 'number' ? (v + '%') : v;
                        }
                    },
                    {
                        title: "Returns %",
                        field: "Returns %",
                        hozAlign: "center",
                        sorter: "number",
                        width: 90,
                        visible: false,
                        formatter: function(cell) {
                            const v = parseNumber(cell.getValue());
                            if (v == null || isNaN(v) || v === 0) return '-';
                            return v.toFixed(1) + '%';
                        }
                    },
                    {
                        title: "A2Z Claims",
                        field: "A2Z Claims",
                        hozAlign: "center",
                        sorter: "number",
                        width: 95,
                        visible: false,
                        formatter: function(cell) {
                            const v = parseNumber(cell.getValue());
                            if (v == null || isNaN(v)) return '-';
                            return v.toLocaleString('en-US');
                        }
                    },
                    {
                        title: "Reviews",
                        field: "Ratings & Reviews",
                        hozAlign: "center",
                        sorter: "number",
                        width: 130,
                        visible: false,
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            let avg = parseNumber(rowData['Avg Rating'] || 0);
                            let total = parseNumber(rowData['Total Reviews'] || 0);
                            if ((!total || total <= 0) && rowData['Reviews'] && typeof rowData['Reviews'] === 'object') {
                                avg = parseNumber(rowData['Reviews']['Avg Rating'] || rowData['Reviews']['avg_rating'] || 0);
                                total = parseNumber(rowData['Reviews']['Total Reviews'] || rowData['Reviews']['total_reviews'] || 0);
                            }
                            if ((avg == null || isNaN(avg)) && (total == null || isNaN(total) || total === 0)) return '-';
                            const r = (!isNaN(avg) && avg > 0) ? avg.toFixed(1) + ' ★' : '';
                            const rev = (!isNaN(total) && total > 0) ? total.toLocaleString('en-US') : '';
                            return [r, rev].filter(Boolean).join(' | ') || '-';
                        }
                    },
                    {
                        title: "Task",
                        field: "_task",
                        minWidth: 56,
                        width: 64,
                        hozAlign: "center",
                        headerSort: false,
                        formatter: function() {
                            return '<button type="button" class="amm-task-btn" title="Assign task" aria-label="Assign task">TM</button>';
                        },
                        cellClick: function(e, cell) {
                            if (e && e.stopPropagation) e.stopPropagation();
                            openAmmTaskFromCell(cell);
                        }
                    },
                    {
                        title: "Action",
                        field: "_action",
                        responsive: 0,
                        minWidth: 72,
                        hozAlign: "center",
                        headerSort: false,
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            return `
                                <div class="d-flex justify-content-center gap-1">
                                    <button class="btn btn-sm btn-outline-primary edit-channel-btn" 
                                            data-channel='${JSON.stringify(rowData)}' 
                                            title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </div>
                            `;
                        },
                        cellClick: function(e, cell) {
                            e.stopPropagation();

                            const $target = $(e.target);

                            // Handle Edit button
                            if ($target.hasClass('edit-channel-btn') || $target.closest(
                                    '.edit-channel-btn').length) {
                                const $btn = $target.hasClass('edit-channel-btn') ? $target :
                                    $target.closest('.edit-channel-btn');
                                const rowDataStr = $btn.attr('data-channel');

                                console.log('Edit button clicked in cellClick');

                                if (typeof bootstrap === 'undefined') {
                                    alert('Bootstrap is not loaded. Please refresh the page.');
                                    return;
                                }

                                try {
                                    const rowData = JSON.parse(rowDataStr);

                                    const channel = rowData['Channel '] || rowData['Channel'] || '';
                                    const sheetUrl = rowData['sheet_link'] || '';
                                    const type = rowData['type'] || '';
                                    const missingLink = rowData['missing_link'] || '';
                                    const logo = rowData['logo'] || '';
                                    const sellerLink = rowData['seller_link'] || '';

                                    // Populate modal
                                    $('#editChannelName').val(channel);
                                    $('#editChannelAlias').val(rowData['alias'] || '');
                                    $('#editChannelPromotions').val(rowData['promotions'] ?? '');
                                    $('#editChannelComplianceCount').val(rowData['compliance_count'] ?? '');
                                    $('#editChannelBrandAuthorisation').val(rowData['brand_authorisation'] || '');
                                    $('#editChannelUrl').val(sheetUrl);
                                    $('#editType').val(type);
                                    $('#editMissingLink').val(missingLink);
                                    $('#editChannelSellerLink').val(sellerLink);
                                    $('#originalChannel').val(channel);

                                    // Reset file input + show current logo (if any)
                                    $('#editChannelLogo').val('');
                                    $('#editExistingLogo').val(logo);
                                    if (logo) {
                                        $('#editChannelLogoPreview').html(
                                            `<img src="/storage/${logo}" alt="${channel}"/>`);
                                    } else {
                                        $('#editChannelLogoPreview').html(
                                            '<span class="placeholder-text">No logo</span>');
                                    }

                                    // Open modal
                                    const modalElement = document.getElementById(
                                    'editChannelModal');
                                    if (modalElement) {
                                        const modal = new bootstrap.Modal(modalElement);
                                        modal.show();
                                    }
                                } catch (error) {
                                    console.error('Error:', error);
                                }
                                return;
                            }
                        }
                    },
                    {
                        title: "Total PFT",
                        field: "Total PFT",
                        hozAlign: "center",
                        sorter: "number",
                        visible: false,
                        formatter: function(cell) {
                            // Calculate Total PFT from L30 Sales × NPFT%
                            const rowData = cell.getRow().getData();
                            const l30Sales = parseNumber(rowData['L30 Sales'] || 0);
                            const npftPercent = parseNumber(rowData['N PFT'] || 0);
                            const totalPft = (l30Sales * npftPercent) / 100;
                            return `<span>$${totalPft.toFixed(0)}</span>`;
                        }
                    },
                    {
                        title: "COGS",
                        field: "cogs",
                        hozAlign: "center",
                        sorter: "number",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            return `<span>$${value.toFixed(0)}</span>`;
                        }
                    },
                    {
                        title: "Sheet",
                        field: "sheet_link",
                        hozAlign: "center",
                        visible: true,
                        formatter: function(cell) {
                            const link = cell.getValue();
                            if (!link) return '-';
                            return `<a href="${link}" target="_blank" class="btn btn-sm btn-success">🔗</a>`;
                        }
                    },
                    {
                        // Manual Compliance Count entered per channel in the Edit modal.
                        title: "Compliance Count",
                        field: "compliance_count",
                        hozAlign: "center",
                        sorter: "number",
                        width: 110,
                        headerTooltip: "Manual compliance count — set in the channel's Edit modal.",
                        formatter: function(cell) {
                            const raw = cell.getValue();
                            if (raw === null || raw === undefined || raw === '' || isNaN(parseInt(raw))) {
                                return '<span style="color:#adb5bd;">-</span>';
                            }
                            return `<span style="font-weight:600;">${parseInt(raw).toLocaleString('en-US')}</span>`;
                        }
                    },
                ]
            });

            table.on('renderComplete', function() {
                paintMetricDots(channelKeysFromTableData());
                bindAmmHorizontalScrollSync();
                pinAmmTableHeader();
            });

            var ammFitWidthsTimer = null;
            function fitAmmColumnsToData() {
                if (!table || !table.getColumns) return;
                clearTimeout(ammFitWidthsTimer);
                ammFitWidthsTimer = setTimeout(function() {
                    table.getColumns().forEach(function(col) {
                        if (!col.isVisible()) return;
                        var def = col.getDefinition() || {};
                        if (def.field === 'logo') return;
                        try { col.setWidth(true); } catch (e) { /* ignore */ }
                    });
                }, 60);
            }

            function ammStickyPort(el) {
                var node = el && el.parentElement;
                while (node && node !== document.body && node !== document.documentElement) {
                    var cs = getComputedStyle(node);
                    var oy = cs.overflowY;
                    var ox = cs.overflowX;
                    if (oy === 'auto' || oy === 'scroll' || oy === 'hidden' || ox === 'auto' || ox === 'scroll' || ox === 'hidden') {
                        return node;
                    }
                    node = node.parentElement;
                }
                return null;
            }

            function ammHeaderPinTop(anchor) {
                var port = ammStickyPort(anchor);
                var nav = document.querySelector('.navbar-custom');
                var top = 0;
                if (!port && nav) {
                    var bottom = nav.getBoundingClientRect().bottom;
                    if (bottom > 0) top = bottom;
                }
                var bar = document.getElementById('amm-table-hscroll');
                if (bar && bar.style.display !== 'none' && bar.offsetParent !== null) {
                    bar.style.top = Math.round(top) + 'px';
                    top += bar.offsetHeight || 0;
                }
                return Math.round(top);
            }

            function ammFreezeSpacer(id, before) {
                var spacer = document.getElementById(id);
                if (!spacer && before && before.parentNode) {
                    spacer = document.createElement('div');
                    spacer.id = id;
                    before.parentNode.insertBefore(spacer, before);
                }
                return spacer;
            }

            function ammReleaseFreeze(el, frozenClass) {
                if (!el) return;
                el.classList.remove(frozenClass);
                ['position', 'top', 'left', 'width', 'right', 'z-index'].forEach(function(prop) {
                    el.style.removeProperty(prop);
                });
            }

            function pinAmmTableHeader() {
                var tableEl = document.getElementById('marketplace-table');
                var header = tableEl && tableEl.querySelector('.tabulator-header');
                var bar = document.getElementById('amm-table-hscroll');
                if (!tableEl || !header) return;

                var nav = document.querySelector('.navbar-custom');
                var pinTop = 0;
                if (nav) {
                    var navBottom = nav.getBoundingClientRect().bottom;
                    if (navBottom > 0) pinTop = navBottom;
                }
                var barVisible = bar && bar.offsetParent !== null && bar.style.display !== 'none';
                var barH = barVisible ? (bar.offsetHeight || 0) : 0;
                var headerH = header.getBoundingClientRect().height || header.offsetHeight || 0;
                var headerSpacer = ammFreezeSpacer('amm-header-freeze-spacer', header);
                var barSpacer = bar ? ammFreezeSpacer('amm-hscroll-freeze-spacer', bar) : null;
                var anchorTop;
                if (barVisible) {
                    anchorTop = (bar.classList.contains('amm-hscroll-frozen') && barSpacer)
                        ? barSpacer.getBoundingClientRect().top
                        : bar.getBoundingClientRect().top;
                } else if (header.classList.contains('amm-header-frozen') && headerSpacer) {
                    anchorTop = headerSpacer.getBoundingClientRect().top;
                } else {
                    anchorTop = header.getBoundingClientRect().top;
                }
                var tableBottom = tableEl.getBoundingClientRect().bottom;
                var box = tableEl.getBoundingClientRect();
                var shouldFix = anchorTop < pinTop && tableBottom > (pinTop + barH + headerH + 8);

                if (shouldFix) {
                    if (barVisible && barSpacer) {
                        barSpacer.classList.add('is-on');
                        barSpacer.style.height = barH + 'px';
                        bar.classList.add('amm-hscroll-frozen');
                        bar.style.setProperty('position', 'fixed', 'important');
                        bar.style.setProperty('top', Math.round(pinTop) + 'px', 'important');
                        bar.style.setProperty('left', Math.round(box.left) + 'px', 'important');
                        bar.style.setProperty('width', Math.round(box.width) + 'px', 'important');
                        bar.style.setProperty('z-index', '31', 'important');
                    }
                    if (headerSpacer) {
                        headerSpacer.classList.add('is-on');
                        headerSpacer.style.height = headerH + 'px';
                    }
                    header.classList.add('amm-header-frozen');
                    header.style.setProperty('position', 'fixed', 'important');
                    header.style.setProperty('top', Math.round(pinTop + (barVisible ? barH : 0)) + 'px', 'important');
                    header.style.setProperty('left', Math.round(box.left) + 'px', 'important');
                    header.style.setProperty('width', Math.round(box.width) + 'px', 'important');
                    header.style.setProperty('z-index', '30', 'important');
                } else {
                    ammReleaseFreeze(header, 'amm-header-frozen');
                    ammReleaseFreeze(bar, 'amm-hscroll-frozen');
                    if (headerSpacer) {
                        headerSpacer.classList.remove('is-on');
                        headerSpacer.style.height = '0px';
                    }
                    if (barSpacer) {
                        barSpacer.classList.remove('is-on');
                        barSpacer.style.height = '0px';
                    }
                    header.style.setProperty('--amm-header-top', Math.round(pinTop + barH) + 'px');
                }

                if (!window.__ammHeaderFreezeBound) {
                    window.__ammHeaderFreezeBound = true;
                    window.addEventListener('scroll', pinAmmTableHeader, { passive: true, capture: true });
                    document.addEventListener('scroll', pinAmmTableHeader, { passive: true, capture: true });
                    window.addEventListener('resize', pinAmmTableHeader);
                }
            }

            function bindAmmHorizontalScrollSync() {
                var holder = document.querySelector('#marketplace-table .tabulator-tableholder');
                var header = document.querySelector('#marketplace-table .tabulator-header .tabulator-header-contents');
                var footer = document.querySelector('#marketplace-table .tabulator-footer .tabulator-calcs-holder');
                var bar = document.getElementById('amm-table-hscroll');
                var inner = document.getElementById('amm-table-hscroll-inner');
                if (!holder) return;

                function paneWidth() {
                    var tableEl = holder.querySelector('.tabulator-table');
                    return Math.max(holder.scrollWidth, tableEl ? tableEl.scrollWidth : 0);
                }

                function applyScroll(left) {
                    holder.scrollLeft = left;
                    if (header) header.scrollLeft = left;
                    if (footer) footer.scrollLeft = left;
                    if (bar) bar.scrollLeft = left;
                }

                function syncBarSize() {
                    if (!inner || !bar) return;
                    var width = paneWidth();
                    inner.style.width = width + 'px';
                    var needsBar = width > bar.clientWidth + 2;
                    bar.style.display = needsBar ? '' : 'none';
                    pinAmmTableHeader();
                }

                if (holder.dataset.ammScrollBound !== '1') {
                    holder.dataset.ammScrollBound = '1';
                    var syncing = false;
                    holder.addEventListener('scroll', function() {
                        if (syncing) return;
                        syncing = true;
                        applyScroll(holder.scrollLeft);
                        syncing = false;
                    }, { passive: true });
                    if (bar) {
                        bar.addEventListener('scroll', function() {
                            if (syncing) return;
                            syncing = true;
                            applyScroll(bar.scrollLeft);
                            syncing = false;
                        }, { passive: true });
                    }
                    var resizeTimer = null;
                    window.addEventListener('resize', function() {
                        clearTimeout(resizeTimer);
                        resizeTimer = setTimeout(function() {
                            if (table) {
                                try {
                                    table.setLayout('fitData');
                                    fitAmmColumnsToData();
                                } catch (e) { /* ignore */ }
                                table.redraw(true);
                            }
                            requestAnimationFrame(function() {
                                syncBarSize();
                                applyScroll(holder.scrollLeft);
                                if (typeof fitAmmBadgeRows === 'function') fitAmmBadgeRows();
                            });
                        }, 150);
                    });
                }
                syncBarSize();
            }

            function loadMetricDotTrends(tableData) {
                var channelKeys = channelKeysFromTableData(tableData);
                if (channelKeys.length === 0) return;

                function finish(channels) {
                    if (channels && Object.keys(channels).length) {
                        applyDotTrendsMap(channels);
                    }
                    applyL30FallbackDots();
                    paintMetricDots(channelKeys);
                }

                if (dotTrendsPrefetch && typeof dotTrendsPrefetch.then === 'function') {
                    dotTrendsPrefetch.done(function(response) {
                        finish(response && response.success ? response.channels : null);
                    }).fail(function() {
                        finish(null);
                    });
                    return;
                }

                finish(null);
            }

            function colorSummaryBadgeDots(channelKeys) {
                var inverted = invertedDotMetrics;
                var sumMetrics = {
                    l30_sales: 1, y_sales: 1, today_sales: 1, y_pft: 1, y_npft_amt: 1, p_sales: 1, p_npft_amt: 1, l30_orders: 1, qty: 1, ad_spend: 1, pft: 1,
                    clicks: 1, ad_sales: 1, ad_sold: 1, total_views: 1, inv_at_lp: 1,
                    inv_at_sp: 1, inventory: 1, missing_l: 1, map: 1, nmap: 1,
                    reviews: 1, l60_sales: 1, l60_orders: 1
                };
                var weightBy = {
                    gprofit: 'l30_sales', npft: 'l30_sales', p_npft: 'p_sales', p_groi_pct: 'p_sales', p_nroi_pct: 'p_sales', y_npft_pct: 'y_sales', y_groi_pct: 'y_sales', y_nroi_pct: 'y_sales', ads_pct: 'l30_sales',
                    groi: 'l30_sales', nroi: 'l30_sales',
                    cvr: 'total_views', ads_cvr: 'clicks', acos: 'ad_sales'
                };
                function pairClass(v1, v2, metric) {
                    if (v1 == null || v2 == null || isNaN(v1) || isNaN(v2)) return 'none';
                    var eps = (metric === 'cvr' || metric === 'ads_cvr' || metric === 'gprofit' || metric === 'groi'
                        || metric === 'npft' || metric === 'p_npft' || metric === 'p_groi_pct' || metric === 'p_nroi_pct' || metric === 'y_npft_pct' || metric === 'y_groi_pct' || metric === 'y_nroi_pct'
                        || metric === 'nroi' || metric === 'ads_pct' || metric === 'acos') ? 0.005 : 0.01;
                    if (Math.abs(v2 - v1) <= eps) return 'flat';
                    // TAT badge only: higher months of stock is worse (red up, green down).
                    var isInv = inverted.indexOf(metric) >= 0 || metric === 'tat';
                    if (isInv) return v2 < v1 ? 'up' : 'down';
                    return v2 > v1 ? 'up' : 'down';
                }
                $('#summary-stats .summary-trend-dot[data-metric]').each(function() {
                    if ($(this).closest('#analytics-dil-badge').length) return;
                    var metric = $(this).attr('data-metric');
                    if (!metric) return;
                    // Prefer the blended All pair. Only treat it as settled when
                    // both values exist — a leftover gray hex must not lock Every
                    // summary badge to flat.
                    var allPair = lastDotPairByKey['all_' + metric];
                    if (allPair && allPair[0] != null && allPair[1] != null
                        && !isNaN(allPair[0]) && !isNaN(allPair[1])) {
                        $(this).removeClass('up down flat none').addClass(pairClass(allPair[0], allPair[1], metric));
                        return;
                    }
                    var s1 = 0, s2 = 0, w1 = 0, w2 = 0, n = 0;
                    var weightKey = weightBy[metric] || (sumMetrics[metric] ? metric : null);
                    if (weightKey) {
                        (channelKeys || []).forEach(function(ch) {
                            var p = lastDotPairByKey[ch + '_' + metric];
                            if (!p || p[0] == null || p[1] == null || isNaN(p[0]) || isNaN(p[1])) return;
                            var wt = lastDotPairByKey[ch + '_' + weightKey];
                            var a = 1, b = 1;
                            if (weightKey !== metric && wt) {
                                a = (wt[0] != null && !isNaN(wt[0])) ? wt[0] : 0;
                                b = (wt[1] != null && !isNaN(wt[1])) ? wt[1] : 0;
                            }
                            s1 += p[0] * a;
                            s2 += p[1] * b;
                            w1 += a;
                            w2 += b;
                            n++;
                        });
                        if (n > 0) {
                            var v1 = weightKey !== metric ? (w1 > 0 ? s1 / w1 : null) : s1;
                            var v2 = weightKey !== metric ? (w2 > 0 ? s2 / w2 : null) : s2;
                            $(this).removeClass('up down flat none').addClass(pairClass(v1, v2, metric));
                            return;
                        }
                    }
                    var up = 0, down = 0;
                    (channelKeys || []).forEach(function(ch) {
                        var c = lastDotColorByKey[ch + '_' + metric];
                        if (c === '#28a745') up++;
                        else if (c === '#dc3545') down++;
                    });
                    var cls = 'none';
                    if (up > down) cls = 'up';
                    else if (down > up) cls = 'down';
                    else if (up > 0 || down > 0) cls = 'flat';
                    $(this).removeClass('up down flat none').addClass(cls);
                });
                paintDotBadgeBackgrounds();
            }

            function paintDotBadgeBackgrounds() {
                document.querySelectorAll('#summary-stats .summary-trend-dot').forEach(function(dot) {
                    var badge = dot.closest('.badge');
                    if (!badge) return;
                    var bg = '#d1d5db';
                    var fg = '#111827';
                    var red = false;
                    if (dot.classList.contains('up')) {
                        bg = '#22c55e';
                        fg = '#052e16';
                    } else if (dot.classList.contains('down')) {
                        bg = '#ef4444';
                        fg = '#fff';
                        red = true;
                    } else if (dot.classList.contains('flat')) {
                        bg = '#facc15';
                        fg = '#713f12';
                    }
                    badge.style.setProperty('background-color', bg, 'important');
                    badge.style.setProperty('color', fg, 'important');
                    badge.classList.toggle('amm-dot-down', red);
                    if (red) {
                        badge.style.setProperty('-webkit-text-fill-color', '#fff', 'important');
                    } else {
                        badge.style.removeProperty('-webkit-text-fill-color');
                    }
                });
                document.querySelectorAll('#summary-stats .ebay2-summary-badge-row > .badge').forEach(function(badge) {
                    if (badge.querySelector('.summary-trend-dot')) return;
                    if (badge.classList.contains('amm-dot-down') || ammBadgeBgIsRed(badge)) {
                        badge.classList.add('amm-dot-down');
                        badge.style.setProperty('color', '#fff', 'important');
                        badge.style.setProperty('-webkit-text-fill-color', '#fff', 'important');
                    }
                });
            }
            function ammBadgeBgIsRed(badge) {
                var bg = window.getComputedStyle(badge).backgroundColor || '';
                var m = bg.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/);
                if (!m) return false;
                var r = +m[1], g = +m[2], b = +m[3];
                return r >= 180 && g <= 130 && b <= 130 && (r - g) >= 50 && (r - b) >= 50;
            }
            window.paintDotBadgeBackgrounds = paintDotBadgeBackgrounds;

            function fitAmmBadgeRows() {
                document.querySelectorAll('#summary-stats .ebay2-summary-badge-row').forEach(function(row) {
                    row.style.zoom = '';
                });
                document.querySelectorAll('#summary-stats .ebay2-summary-badge-row > .badge').forEach(function(b) {
                    ['flex', 'max-width', 'min-width', 'font-size', 'padding', 'white-space'].forEach(function(prop) {
                        b.style.removeProperty(prop);
                    });
                });
            }
            requestAnimationFrame(function() {
                fitAmmBadgeRows();
                paintDotBadgeBackgrounds();
            });

            // Update summary statistics
            function updateSummaryStats(data) {
                let totalChannels = data.length;
                let totalL30Sales = 0;
                let totalYSales = 0;
                let totalYPctSales = 0;
                let totalYGross = 0;
                let totalYNet = 0;
                let totalYCogs = 0;
                let totalYAdSpend = 0;
                let totalYAdSales = 0;
                let totalTodaySales = 0;
                let totalTodayGross = 0;
                let totalTodayNet = 0;
                let totalTodayCogs = 0;
                let totalTodayAdSpend = 0;
                let totalTodayViews = 0;
                let totalTodayOrders = 0;
                const todayDotVals = {};
                let totalPSales = 0;
                let totalPGross = 0;
                let totalL30Orders = 0;
                let totalQty = 0;
                let totalClicks = 0;
                let totalPft = 0;
                let totalCogs = 0;
                let totalAdSpend = 0;
                let totalViews = 0;
                let totalMap = 0;
                let totalMiss = 0;
                let totalNMap = 0;
                let gprofitSum = 0;
                let groiSum = 0;
                let npftSum = 0;
                let nroiSum = 0;
                let nroiWeighted = 0;
                let nroiWeight = 0;
                let validChannels = 0;

                data.forEach(row => {
                    const channel = (row['Channel '] || '').trim().toLowerCase();
                    const l30Sales = parseNumber(row['L30 Sales'] || 0);
                    const ySales = parseNumber(row['Y Sales'] || 0);
                    const todaySales = parseNumber(row['Today Sales'] || 0);
                    const pSales = parseNumber(row['P-Sales'] != null && row['P-Sales'] !== ''
                        ? row['P-Sales']
                        : projectedSalesFromL7(row['L7 Sales'] || 0));
                    const l30Orders = parseNumber(row['L30 Orders'] || 0);
                    const qty = parseNumber(row['Qty'] || 0);
                    const clicks = parseNumber(row['clicks'] || 0);
                    const gprofitPercent = parseNumber(row['Gprofit%'] || 0);
                    const groi = parseNumber(row['G Roi'] || 0);
                    const npft = parseNumber(row['N PFT'] || 0);
                    const nroi = parseNumber(row['N ROI'] || 0);
                    const cogs = parseNumber(row['cogs'] || 0);
                    const mapCount = parseNumber(row['Map'] || 0);
                    const missCount = parseNumber(row['Miss'] || 0);
                    const nmapCount = parseNumber(row['NMap'] || 0);

                    // Use Total Ad Spend directly (already computed correctly per channel).
                    // Reverb stores bump fees in Ads% when Total Ad Spend was historically 0.
                    let adSpend = parseNumber(row['Total Ad Spend'] || 0);
                    if (adSpend <= 0 && channel.indexOf('reverb') !== -1) {
                        const bumpPct = parseNumber(row['Ads%'] || row['TACOS %'] || row['TACOS'] || 0);
                        if (bumpPct > 0 && l30Sales > 0) {
                            adSpend = (bumpPct / 100) * l30Sales;
                        }
                    }
                    const views = parseNumber(row['Total Views'] || 0);

                    totalL30Sales += l30Sales;
                    totalYSales += ySales;
                    totalYGross += yGrossPftFromRow(row);
                    totalYNet += yNetPftFromRow(row);
                    totalYCogs += yCogsFromRow(row);
                    const yAds = yAdsPartsFromRow(row);
                    totalYAdSpend += yAds.spend;
                    totalYAdSales += yAds.sales;
                    // YNPFT% divides by the same one-day sales the profit was measured
                    // against; the Y Sales badge keeps using the Y Sales column.
                    const yMeasured = yMeasuredFromRow(row);
                    totalYPctSales += yMeasured ? yMeasured.sales : ySales;
                    totalTodaySales += todaySales;
                    const todayParts = todayPartsFromRow(row);
                    totalTodayGross += todayParts.gross;
                    totalTodayNet += todayParts.net;
                    totalTodayCogs += todayParts.cogs;
                    totalTodayAdSpend += todayParts.spend;
                    totalTodayViews += todayParts.views;
                    totalTodayOrders += todayParts.orders;
                    totalPSales += pSales;
                    totalPGross += (gprofitPercent / 100) * pSales;
                    totalL30Orders += l30Orders;
                    totalQty += qty;
                    totalClicks += clicks;
                    totalAdSpend += adSpend;
                    totalViews += views;
                    totalCogs += cogs;
                    totalMap += mapCount;
                    totalMiss += missCount;
                    totalNMap += nmapCount;

                    // Calculate profit amount from percentage
                    const profitAmount = (gprofitPercent / 100) * l30Sales;
                    totalPft += profitAmount;

                    if (cogs > 0) {
                        nroiWeighted += nroi * cogs;
                        nroiWeight += cogs;
                    }

                    if (l30Sales > 0) {
                        gprofitSum += gprofitPercent;
                        groiSum += groi;
                        npftSum += npft;
                        nroiSum += nroi;
                        validChannels++;
                    }
                });

                // Calculate overall metrics (same as channel-masters)
                const avgGprofit = totalL30Sales > 0 ? (totalPft / totalL30Sales) * 100 : 0;
                const avgGroi = totalCogs > 0 ? (totalPft / totalCogs) * 100 : 0;

                // Calculate average TAcos % = Total Ad Spend / Total L30 Sales (same as channel-masters)
                const avgAdsPercent = totalL30Sales > 0 ? (totalAdSpend / totalL30Sales) * 100 : 0;

                // N PFT = G PFT - TAcos % (same as channel-masters)
                const avgNpft = avgGprofit - avgAdsPercent;

                // NROI% = COGS-weighted average of each active channel's N ROI column.
                // Temu / Temu 2 / Temu 3 are GROI% − Ads%, not (PFT − Spend) / COGS.
                const netProfit = totalPft - totalAdSpend;
                const avgNroi = nroiWeight > 0 ? (nroiWeighted / nroiWeight) : 0;

                // Store the exact numeric value on the badge so chart scaling matches the
                // live table total (compact text like "326K" must not be parsed as 326).
                function setBadgeExact($inner, exactVal) {
                    const $badge = $inner.closest('.badge-chart-link');
                    if ($badge.length) {
                        if (exactVal === null || exactVal === undefined || isNaN(exactVal)) {
                            $badge.removeAttr('data-exact-value');
                        } else {
                            $badge.attr('data-exact-value', exactVal);
                        }
                    }
                }
                function toCompact(val) {
                    const v = Math.round(val);
                    if (Math.abs(v) >= 1000000) return (v / 1000000).toFixed(1).replace(/\.0$/, '') + 'M';
                    if (Math.abs(v) >= 1000) return Math.round(v / 1000) + 'K';
                    return String(v);
                }

                // Update badges
                $('#total-channels').text(totalChannels);
                (function() {
                    const val = Math.round(totalL30Sales);
                    const $el = $('#total-l30-sales');
                    $el.text(toCompact(val));
                    const badge = $el.closest('.badge');
                    const salesTitle = 'Sum of Sales column (channel rolling L30 / window varies). $' + val.toLocaleString('en-US');
                    badge.attr('data-sales-title', salesTitle);
                    badge.attr('title', salesTitle);
                    setBadgeExact($el, val);
                })();
                // Show NYS when no channel had any sales yesterday — clearer than "$0".
                (function() {
                    const $el = $('#total-y-sales');
                    if (totalYSales > 0) {
                        const val = Math.round(totalYSales);
                        $el.text('$' + val.toLocaleString('en-US'));
                        setBadgeExact($el, val);
                        prefetchAllYSalesChart(val);
                    } else {
                        $el.text('NYS');
                        setBadgeExact($el, null);
                        prefetchAllYSalesChart(0);
                    }
                })();
                (function() {
                    const $el = $('#total-y-pft');
                    if (totalYSales > 0) {
                        const val = Math.round(totalYGross);
                        $el.text('$' + val.toLocaleString('en-US'));
                        $el.closest('.badge').attr('title',
                            'Y PFT $ = sum(Y Sales × GPFT%): $' + val.toLocaleString('en-US'));
                        setBadgeExact($el, val);
                    } else {
                        $el.text('NYS');
                        setBadgeExact($el, null);
                    }
                })();
                (function() {
                    const $el = $('#total-y-npft');
                    if (totalYSales > 0) {
                        const val = Math.round(totalYNet);
                        $el.text('$' + val.toLocaleString('en-US'));
                        $el.closest('.badge').attr('title',
                            'Y NPFT $ = sum(Y Sales × NPFT%): $' + val.toLocaleString('en-US'));
                        setBadgeExact($el, val);
                    } else {
                        $el.text('NYS');
                        setBadgeExact($el, null);
                    }
                })();
                (function() {
                    const val = Math.round(totalTodaySales);
                    const $el = $('#total-today-sales');
                    $el.text('$' + val.toLocaleString('en-US'));
                    $el.closest('.badge').attr('title',
                        'Sum of Today Sales. Current Eastern calendar day from 12:00 AM EST/EDT through now. $' + val.toLocaleString('en-US') + '. Dot compares today with yesterday.');
                    setBadgeExact($el, val);
                    if (!chartDotSynced['today_sales']) {
                        lastDotPairByKey['all_today_sales'] = [totalYSales, totalTodaySales];
                    }
                    prefetchAllBadgeChartDot('today_sales', val);
                })();
                (function() {
                    const npft = totalTodaySales > 0 ? (totalTodayNet / totalTodaySales) * 100 : 0;
                    const groi = totalTodayCogs > 0 ? (totalTodayGross / totalTodayCogs) * 100 : 0;
                    const nroi = totalTodayCogs > 0 ? (totalTodayNet / totalTodayCogs) * 100 : 0;
                    const ads = totalTodaySales > 0 ? (totalTodayAdSpend / totalTodaySales) * 100 : 0;
                    const views = Math.round(totalTodayViews);
                    todayDotVals.t_npft = npft;
                    todayDotVals.t_groi = groi;
                    todayDotVals.t_nroi = nroi;
                    todayDotVals.t_ads = ads;
                    todayDotVals.t_views = totalTodayViews;
                    todayDotVals.t_orders = totalTodayOrders;
                    setBadgeExact($('#avg-t-npft'), pct1(npft));
                    setBadgeExact($('#avg-t-groi'), pct1(groi));
                    setBadgeExact($('#avg-t-nroi'), pct1(nroi));
                    setBadgeExact($('#avg-t-ads'), pct1(ads));
                    setBadgeExact($('#total-t-views'), views);
                    setBadgeExact($('#total-t-orders'), Math.round(totalTodayOrders));
                    $('#avg-t-npft').text(pct1(npft).toFixed(1) + '%');
                    $('#avg-t-groi').text(pct1(groi).toFixed(1) + '%');
                    $('#avg-t-nroi').text(pct1(nroi).toFixed(1) + '%');
                    $('#avg-t-ads').text(pct1(ads).toFixed(1) + '%');
                    let viewsText = String(views);
                    if (Math.abs(views) >= 1000000) viewsText = (views / 1000000).toFixed(1).replace(/\.0$/, '') + 'M';
                    else if (Math.abs(views) >= 1000) viewsText = Math.round(views / 1000) + 'K';
                    $('#total-t-views').text(viewsText);
                    $('#avg-t-npft').closest('.badge').attr('title', 'T Nprft% = Σ (Today Sales × NPFT%) ÷ Σ Today Sales. ' + pct1(npft).toFixed(1) + '%');
                    $('#avg-t-groi').closest('.badge').attr('title', 'TGroi% = Σ (Today Sales × GPFT%) ÷ Σ today COGS. Today COGS = L30 COGS × (Today Sales ÷ L30 Sales). ' + pct1(groi).toFixed(1) + '%');
                    $('#avg-t-nroi').closest('.badge').attr('title', 'TNROI% = Σ (Today Sales × NPFT%) ÷ Σ today COGS. ' + pct1(nroi).toFixed(1) + '%');
                    $('#avg-t-ads').closest('.badge').attr('title', 'T Ads% = Σ (Today Sales × Ads%) ÷ Σ Today Sales. ' + pct1(ads).toFixed(1) + '%');
                    $('#total-t-views').closest('.badge').attr('title', 'T Views = Σ (Views × Today Sales ÷ L30 Sales). ' + views.toLocaleString('en-US'));
                    const orders = Math.round(totalTodayOrders);
                    $('#total-t-orders').text(orders.toLocaleString('en-US'));
                    $('#total-t-orders').closest('.badge').attr('title',
                        'T Orders = Σ (L30 Orders × Today Sales ÷ L30 Sales). Same pace as Today Sales. ' + orders.toLocaleString('en-US'));
                    // Colour each Today dot/badge from the same series its graph shows.
                    prefetchAllBadgeChartDot('t_orders', orders);
                    prefetchAllBadgeChartDot('t_npft_pct', pct1(npft));
                    prefetchAllBadgeChartDot('t_groi_pct', pct1(groi));
                    prefetchAllBadgeChartDot('t_nroi_pct', pct1(nroi));
                    prefetchAllBadgeChartDot('t_ads_pct', pct1(ads));
                    prefetchAllBadgeChartDot('t_views', views);
                })();
                (function() {
                    const val = Math.round(totalPSales);
                    const $el = $('#total-p-sales');
                    $el.text(toCompact(val));
                    const pct = pSalesVsL30Pct(totalPSales, totalL30Sales);
                    const $pct = $('#total-p-sales-vs-sales');
                    if (pct == null || !isFinite(pct)) {
                        $pct.text('');
                    } else if (Math.abs(pct) < 0.1) {
                        $pct.text(' 0%').css('color', '#e5e7eb');
                    } else {
                        const isUp = pct > 0;
                        $pct.text(' ' + (isUp ? '+' : '−') + Math.abs(pct).toFixed(0) + '%')
                            .css('color', isUp ? '#86efac' : '#fecaca');
                    }
                    const pctLabel = (pct != null && isFinite(pct))
                        ? (' ' + (pct > 0 ? '+' : (pct < 0 ? '−' : '')) + Math.abs(pct).toFixed(0) + '% vs Sales')
                        : '';
                    $el.closest('.badge').attr('title',
                        'Sum of P-Sales column. Projected 30-day sales from last-7-day pace: (L7 Sales ÷ 7) × 30. $' + val.toLocaleString('en-US') + pctLabel + '. Dot matches the chart (vs previous day).');
                    setBadgeExact($el, val);
                })();
                (function() {
                    const val = Math.round(totalL30Orders);
                    const $el = $('#total-l30-orders');
                    $el.text(val.toLocaleString('en-US'));
                    setBadgeExact($el, val);
                })();
                (function() {
                    const val = Math.round(totalQty);
                    const $el = $('#total-qty');
                    $el.text(val.toLocaleString('en-US'));
                    setBadgeExact($el, val);
                })();
                function pct1(n) {
                    return Math.round(n * 10) / 10;
                }
                (function() {
                    const val = pct1(avgGprofit);
                    const $el = $('#avg-gprofit');
                    $el.text(val.toFixed(1) + '%');
                    setBadgeExact($el, val);
                })();
                $('#total-gross-pft').text('$' + Math.round(totalPft).toLocaleString('en-US'));
                (function() {
                    const val = pct1(avgGroi);
                    const $el = $('#avg-groi');
                    $el.text(val.toFixed(1) + '%');
                    setBadgeExact($el, val);
                })();
                (function() {
                    const val = Math.round(totalAdSpend);
                    const $el = $('#total-ad-spend');
                    $el.text(toCompact(val));
                    $el.closest('.badge').attr('title',
                        'View trend - Total Ad Spend: $' + val.toLocaleString('en-US'));
                    setBadgeExact($el, val);
                })();
                (function() {
                    const val = Math.round(avgAdsPercent * 10) / 10;
                    const $el = $('#ads-percent-badge');
                    $el.text(val.toFixed(1) + '%');
                    setBadgeExact($el, val);
                })();
                (function() {
                    const val = Math.round(totalViews);
                    const $el = $('#total-views-badge');
                    $el.text(toCompact(val));
                    const badge = $el.closest('.badge');
                    const viewsTitle = 'View trend - Total Views (listing/Map traffic): ' + val.toLocaleString('en-US');
                    badge.attr('data-views-title', viewsTitle);
                    badge.attr('title', viewsTitle);
                    setBadgeExact($el, val);
                })();
                // Listing CVR: prefer each channel's listing CVR × views (same as the CVR column /
                // /temu-decrease / Shopify / Reverb badges). Fallback to Qty ÷ Views.
                // 2 decimals so the badge value shifts day-over-day.
                let cvrUnits = 0;
                let cvrViews = 0;
                data.forEach(row => {
                    const views = parseNumber(row['Total Views'] || 0);
                    if (views <= 0) return;
                    const ch = snapshotChannelKey(row['Channel '] || row['Channel'] || '');
                    if (ch === 'reverb' || ch === 'amazon') {
                        cvrUnits += parseNumber(row['Qty'] || 0) || parseNumber(row['L30 Orders'] || 0);
                        cvrViews += views;
                        return;
                    }
                    const serverCvr = row['CVR'];
                    if (serverCvr !== undefined && serverCvr !== null && serverCvr !== '') {
                        cvrUnits += (parseNumber(serverCvr) / 100) * views;
                        cvrViews += views;
                    } else {
                        cvrUnits += parseNumber(row['Qty'] || 0);
                        cvrViews += views;
                    }
                });
                const cvrPct = cvrViews > 0 ? (cvrUnits / cvrViews) * 100 : null;
                (function() {
                    const $el = $('#cvr-pct-badge');
                    if (cvrPct !== null) {
                        const val = Math.round(cvrPct * 100) / 100;
                        $el.text(val.toFixed(2) + '%');
                        setBadgeExact($el, val);
                    } else {
                        $el.text('-');
                        setBadgeExact($el, null);
                    }
                })();
                // NPFT $ = gross profit $ − total ad spend (= L30 × (G% − Ad Spend/Sales) in aggregate)
                (function() {
                    const val = Math.round(netProfit);
                    const $el = $('#total-pft');
                    $el.text(toCompact(val));
                    $el.closest('.badge').attr('title',
                        'Net profit $ = sum(rolling Sales×Gprofit% − Ad spend): $' + val.toLocaleString('en-US'));
                    setBadgeExact($el, val);
                })();
                (function() {
                    const val = pct1(avgNpft);
                    const $el = $('#avg-npft');
                    $el.text(val.toFixed(1) + '%');
                    setBadgeExact($el, val);
                })();
                (function() {
                    let pGross = 0;
                    let pCogs = 0;
                    data.forEach(function(row) {
                        if (!pSalesFromRow(row)) return;
                        pGross += pGrossPftFromRow(row);
                        pCogs += pCogsFromRow(row);
                    });
                    const avgPGroi = pCogs > 0 ? (pGross / pCogs) * 100 : 0;
                    const val = pct1(avgPGroi);
                    const $el = $('#avg-p-groi');
                    $el.text(val.toFixed(1) + '%');
                    $el.closest('.badge').attr('title',
                        'P-GROI% = Σ (P-Sales × GPFT%) ÷ Σ projected COGS. Projected COGS = L30 COGS × (P-Sales ÷ L30 Sales). P-Sales is (L7 ÷ 7) × 30. ' + val.toFixed(1) + '%');
                    setBadgeExact($el, val);
                })();
                (function() {
                    const avgPNpft = totalPSales > 0 ? ((totalPGross - totalAdSpend) / totalPSales) * 100 : 0;
                    const val = pct1(avgPNpft);
                    const $el = $('#avg-p-npft');
                    $el.text(val.toFixed(1) + '%');
                    $el.closest('.badge').attr('title',
                        'Projected NPFT% = (Σ P-Sales × GPFT% − ad spend) ÷ Σ P-Sales. P-Sales is (L7 ÷ 7) × 30. ' + val.toFixed(1) + '%');
                    setBadgeExact($el, val);
                })();
                (function() {
                    let pNet = 0;
                    let pCogs = 0;
                    data.forEach(function(row) {
                        if (!pSalesFromRow(row)) return;
                        pNet += pGrossPftFromRow(row) - rowAdSpendFromRow(row);
                        pCogs += pCogsFromRow(row);
                    });
                    const avgPNroi = pCogs > 0 ? (pNet / pCogs) * 100 : 0;
                    const val = pct1(avgPNroi);
                    const $el = $('#avg-p-nroi');
                    $el.text(val.toFixed(1) + '%');
                    $el.closest('.badge').attr('title',
                        'P-NROI% = Σ (P-Sales × GPFT% − ad spend) ÷ Σ projected COGS. Same projected COGS as P GROI%: L30 COGS × (P-Sales ÷ L30 Sales). P-Sales is (L7 ÷ 7) × 30. ' + val.toFixed(1) + '%');
                    setBadgeExact($el, val);
                })();
                (function() {
                    const avgPAds = totalPSales > 0 ? (totalAdSpend / totalPSales) * 100 : 0;
                    const val = pct1(avgPAds);
                    const $el = $('#avg-p-ads');
                    $el.text(val.toFixed(1) + '%');
                    $el.closest('.badge').attr('title',
                        'P Ads% = total ad spend ÷ Σ P-Sales × 100. Spend is current L30 ad spend. P-Sales is (L7 ÷ 7) × 30. ' + val.toFixed(1) + '%');
                    setBadgeExact($el, val);
                    prefetchAllBadgeChartDot('p_ads_pct', val);
                })();
                (function() {
                    let totalPViews = 0;
                    data.forEach(function(row) {
                        totalPViews += pViewsFromRow(row);
                    });
                    const val = Math.round(totalPViews);
                    const $el = $('#total-p-views');
                    $el.text(toCompact(val));
                    $el.closest('.badge').attr('title',
                        'P Views = Σ (Views × P-Sales ÷ L30 Sales). Same 30-day pace as P-Sales. ' + val.toLocaleString('en-US'));
                    setBadgeExact($el, val);
                    prefetchAllBadgeChartDot('p_views', val);
                })();
                (function() {
                    let totalPOrders = 0;
                    data.forEach(function(row) {
                        totalPOrders += pOrdersFromRow(row);
                    });
                    const val = Math.round(totalPOrders);
                    const $el = $('#total-p-orders');
                    $el.text(val.toLocaleString('en-US'));
                    $el.closest('.badge').attr('title',
                        'P Orders = Σ (L30 Orders × P-Sales ÷ L30 Sales). Same 30-day pace as P-Sales. ' + val.toLocaleString('en-US'));
                    setBadgeExact($el, val);
                    prefetchAllBadgeChartDot('p_orders', val);
                })();
                (function() {
                    let totalPNet = 0;
                    data.forEach(function(row) {
                        totalPNet += pNetPftFromRow(row);
                    });
                    const val = Math.round(totalPNet);
                    const $el = $('#total-p-npft');
                    $el.text(toCompact(val));
                    const pct = pNpftAmtVsNpftPct(totalPNet, netProfit);
                    const $pct = $('#total-p-npft-vs-npft');
                    if (pct == null || !isFinite(pct)) {
                        $pct.text('');
                    } else if (Math.abs(pct) < 0.1) {
                        $pct.text(' 0%').css('color', '#e5e7eb');
                    } else {
                        const isUp = pct > 0;
                        $pct.text(' ' + (isUp ? '+' : '−') + Math.abs(pct).toFixed(0) + '%')
                            .css('color', isUp ? '#86efac' : '#fecaca');
                    }
                    const pctLabel = (pct != null && isFinite(pct))
                        ? (' ' + (pct > 0 ? '+' : (pct < 0 ? '−' : '')) + Math.abs(pct).toFixed(0) + '% vs NPFT $')
                        : '';
                    $el.closest('.badge').attr('title',
                        'Sum of P NPFT $ column. Projected net profit $ = P-Sales × P-Npft%. P-Sales is (L7 ÷ 7) × 30. $' + val.toLocaleString('en-US') + pctLabel + '. Dot matches the chart (vs previous day).');
                    setBadgeExact($el, val);
                })();
                (function() {
                    const avgYNpft = totalYPctSales > 0 ? (totalYNet / totalYPctSales) * 100 : 0;
                    const val = pct1(avgYNpft);
                    const $el = $('#avg-y-npft');
                    $el.text(val.toFixed(1) + '%');
                    $el.closest('.badge').attr('title',
                        'yNprft% = (Σ yesterday net PFT $) ÷ (Σ yesterday sales), both measured for yesterday alone. ' + val.toFixed(1) + '%');
                    setBadgeExact($el, val);
                })();
                (function() {
                    const avgYGroi = totalYCogs > 0 ? (totalYGross / totalYCogs) * 100 : 0;
                    const val = pct1(avgYGroi);
                    const $el = $('#avg-y-groi');
                    $el.text(val.toFixed(1) + '%');
                    $el.closest('.badge').attr('title',
                        'YGroi% = (Σ yesterday gross PFT $) ÷ (Σ yesterday COGS), both measured for yesterday alone. ' + val.toFixed(1) + '%');
                    setBadgeExact($el, val);
                })();
                (function() {
                    const avgYNroi = totalYCogs > 0 ? (totalYNet / totalYCogs) * 100 : 0;
                    const val = pct1(avgYNroi);
                    const $el = $('#avg-y-nroi');
                    $el.text(val.toFixed(1) + '%');
                    $el.closest('.badge').attr('title',
                        'YNROI% = (Σ yesterday net PFT $) ÷ (Σ yesterday COGS), both measured for yesterday alone. ' + val.toFixed(1) + '%');
                    setBadgeExact($el, val);
                })();
                (function() {
                    const avgYAds = totalYAdSales > 0 ? (totalYAdSpend / totalYAdSales) * 100 : 0;
                    const val = pct1(avgYAds);
                    const $el = $('#avg-y-ads');
                    if (totalYAdSales > 0) {
                        $el.text(val.toFixed(1) + '%');
                        setBadgeExact($el, val);
                    } else {
                        $el.text('NYS');
                        setBadgeExact($el, null);
                    }
                    $el.closest('.badge').attr('title',
                        'Y Ads% = Σ yesterday ad spend ÷ Σ yesterday sales × 100. Spend is each channel’s Ads% on yesterday’s sales. '
                        + (totalYAdSales > 0 ? val.toFixed(1) + '%' : 'No yesterday sales.'));
                })();
                (function() {
                    const val = pct1(avgNroi);
                    const $el = $('#avg-nroi');
                    $el.text(val.toFixed(1) + '%');
                    setBadgeExact($el, val);
                })();
                (function() {
                    const val = Math.round(totalNMap);
                    const $el = $('#total-nmap');
                    $el.text(val.toLocaleString('en-US'));
                    setBadgeExact($el, val);
                })();
                (function() {
                    const val = Math.round(totalMiss);
                    const $el = $('#total-miss');
                    $el.text(val.toLocaleString('en-US'));
                    setBadgeExact($el, val);
                })();

                // Reviews badge: weighted avg rating (sum(rating*reviews)/sum(reviews)), total reviews (sum)
                let ratingSum = 0, reviewsSum = 0;
                data.forEach(row => {
                    let r = parseNumber(row['Avg Rating'] || 0);
                    let rev = parseNumber(row['Total Reviews'] || 0);
                    if ((!rev || rev <= 0) && row['Reviews'] && typeof row['Reviews'] === 'object') {
                        r = parseNumber(row['Reviews']['Avg Rating'] || row['Reviews']['avg_rating'] || 0);
                        rev = parseNumber(row['Reviews']['Total Reviews'] || row['Reviews']['total_reviews'] || 0);
                    }
                    if (!isNaN(r) && !isNaN(rev) && rev > 0) { ratingSum += r * rev; reviewsSum += rev; }
                });
                const weightedAvgRating = reviewsSum > 0 ? (ratingSum / reviewsSum) : 0;
                const $ratingBadge = $('#total-rating-badge');
                $ratingBadge.text(weightedAvgRating.toFixed(1));
                setBadgeExact($ratingBadge, Math.round(weightedAvgRating * 10) / 10);
                const $revBadge = $('#total-reviews-badge');
                $revBadge.text(Math.round(reviewsSum).toLocaleString('en-US'));
                setBadgeExact($revBadge, Math.round(reviewsSum));
                if (reviewsSum > 0) {
                    prefetchAllBadgeChartDot('rating', Math.round(weightedAvgRating * 10) / 10);
                    prefetchAllBadgeChartDot('reviews', Math.round(reviewsSum));
                }

                pinSummaryDot('y_sales', totalL30Sales / 30, totalYSales,
                    'Green when yesterday sales are above the L30 daily average (L30 Sales ÷ 30).');
                pinSummaryDot('y_npft_pct', avgNpft, totalYPctSales > 0 ? (totalYNet / totalYPctSales) * 100 : 0,
                    'Green when yesterday NPFT% is above the L30 NPFT%.');
                pinSummaryDot('y_groi_pct', avgGroi, totalYCogs > 0 ? (totalYGross / totalYCogs) * 100 : 0,
                    'Green when yesterday GROI% is above the L30 G ROI%.');
                pinSummaryDot('y_nroi_pct', avgNroi, totalYCogs > 0 ? (totalYNet / totalYCogs) * 100 : 0,
                    'Green when yesterday NROI% is above the L30 NROI%.');
                pinSummaryDot('y_ads_pct', avgAdsPercent, totalYAdSales > 0 ? (totalYAdSpend / totalYAdSales) * 100 : 0,
                    'Green when yesterday Ads% is below the L30 Ads%.');
                l30DailyOrders = totalL30Orders / 30;
                l30DailyViews = totalViews / 30;
                if (yOrdersExact != null) {
                    pinSummaryDot('y_orders', l30DailyOrders, yOrdersExact,
                        'Green when yesterday orders are above the L30 daily average (L30 Orders ÷ 30).');
                }
                if (yViewsExact != null) {
                    pinSummaryDot('y_views', l30DailyViews, yViewsExact,
                        'Green when yesterday views are above the L30 daily average (L30 Views ÷ 30).');
                }
                // Today badges vs L30 baselines (same rule as the Yesterday badges).
                pinSummaryDot('t_npft', avgNpft, todayDotVals.t_npft,
                    'Green when today NPFT% is above the L30 NPFT%.');
                pinSummaryDot('t_groi', avgGroi, todayDotVals.t_groi,
                    'Green when today GROI% is above the L30 G ROI%.');
                pinSummaryDot('t_nroi', avgNroi, todayDotVals.t_nroi,
                    'Green when today NROI% is above the L30 NROI%.');
                pinSummaryDot('t_ads', avgAdsPercent, todayDotVals.t_ads,
                    'Green when today Ads% is below the L30 Ads%.');
                pinSummaryDot('t_orders', totalL30Orders / 30, todayDotVals.t_orders,
                    'Green when today orders are above the L30 daily average (L30 Orders ÷ 30).');
                pinSummaryDot('t_views', totalViews / 30, todayDotVals.t_views,
                    'Green when today views are above the L30 daily average (L30 Views ÷ 30).');
                if (typeof colorSummaryBadgeDots === 'function') {
                    colorSummaryBadgeDots(channelKeysFromTableData());
                }
                requestAnimationFrame(fitAmmBadgeRows);
            }

            function pinSummaryDot(metric, baseline, current, title) {
                if (!yVsL30DotMetrics[metric]) return;
                // The graph's own series already decided this dot; do not overwrite it.
                if (chartDotSynced[metric]) return;
                if (baseline == null || current == null || !isFinite(baseline) || !isFinite(current)) return;
                lastDotPairByKey['all_' + metric] = [baseline, current];
                chartSyncedAllMetrics[metric] = true;
                var dot = document.querySelector('#summary-stats .summary-trend-dot[data-metric="' + metric + '"]');
                if (dot && title) dot.title = title;
            }

            // Combine channel search and type (B2C/B2B/Dropship/Wholesale) filters
            function applyMasterFilters() {
                if (!table || typeof table.clearFilter !== 'function') return;
                const q = ($('#channel-search').val() || '').trim().toLowerCase();
                const typeVal = $('#type-filter').val() || 'all';
                const needsFilter = q.length > 0 || (typeVal && typeVal !== 'all');
                if (!needsFilter) {
                    table.clearFilter(true);
                    return;
                }
                table.clearFilter(true);
                table.addFilter(function(data) {
                    const ch = String(data['Channel '] || data['Channel'] || '').toLowerCase();
                    if (q && ch.indexOf(q) === -1) return false;
                    if (typeVal && typeVal !== 'all' && String(data['type'] || '') !== typeVal) return false;
                    return true;
                });
            }

            // Channel Search
            $('#channel-search').on('keyup', function() {
                applyMasterFilters();
            });

            // Type Filter
            $('#type-filter').on('change', function() {
                applyMasterFilters();
            });

            // ---- Persisted column visibility (channel_tabulator_column_settings) ----
            const COLUMN_VISIBILITY_URL = '/tabulator-column-visibility';
            // Per-user key so each user keeps their own saved column layout and
            // gets their latest selection back when they reopen the page.
            const COLUMN_VISIBILITY_CHANNEL = 'all_marketplace_master_user_{{ auth()->id() ?? 'guest' }}';
            const columnCsrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            let savedColumnVisibility = {};

            function fetchColumnVisibility() {
                return fetch(`${COLUMN_VISIBILITY_URL}?channel=${encodeURIComponent(COLUMN_VISIBILITY_CHANNEL)}`, {
                        credentials: 'same-origin'
                    })
                    .then(r => r.json())
                    .then(map => {
                        savedColumnVisibility = (map && typeof map === 'object' && !Array.isArray(map)) ? map : {};
                        return savedColumnVisibility;
                    })
                    .catch(() => {
                        savedColumnVisibility = {};
                        return {};
                    });
            }

            // Fields that are permanently hidden in the UI but still drive calculations
            // (e.g. Growth uses D30 Sales; NP$ is derived from L30 Sales × N PFT).
            // PT / PMT / SERP / KW / HL breakdowns removed from page + Columns menu.
            const PERMANENTLY_HIDDEN_FIELDS = [
                'L7 Sales',
                'Y PFT',
                'Y NPFT',
                'P NPFT',
                'L-60 Sales', 'L60 Orders', 'NP$', 'D30 Sales',
                'PT Spent', 'PMT Spent', 'SERP Spent',
                'PT Clicks', 'PMT Clicks', 'SERP Clicks',
                'PT Sales', 'PMT Sales', 'SERP Sales',
                'PT Sold', 'PMT Sold', 'SERP Sold',
                'PT ACOS', 'PMT ACOS', 'SERP ACOS',
                'PT CVR', 'PMT CVR', 'SERP CVR',
                'KW Spent', 'HL Spent',
                'KW Clicks', 'HL Clicks',
                'KW Sales', 'HL Sales',
                'HL Sold',
                'KW ACOS', 'HL ACOS',
                'KW CVR', 'HL CVR',
                'A2Z Claims',
                'Missing Ads',
                'Map',
                'Miss',
                'NMap',
                'Growth',
                '_gross_pft',
                'Shopping Sales', 'Shopping ACOS', 'Shopping Sold', 'Shopping CVR',
                'ad_sold', 'Ads CVR', 'clicks', 'Ad Sales',
                'Shipping Health',
                'ACOS',
                'Total PFT',
                'Returns %',
                'Shopping Spent', 'Shopping Clicks',
                'compliance_count',
                'KW Sold',
                'CC Health',
                'cogs',
                'Seller Rating & Reviews',
                'alias',
            ];

            // Hidden from Columns menu only — still shown on the table.
            const COLUMNS_MENU_EXCLUDED_FIELDS = ['_action', '_task'];

            function applyColumnVisibility() {
                if (!table) return;
                if (savedColumnVisibility && Object.keys(savedColumnVisibility).length) {
                    table.getColumns().forEach(col => {
                        const def = col.getDefinition();
                        if (!def.field) return;
                        if (savedColumnVisibility[def.field] === false) col.hide();
                        else if (savedColumnVisibility[def.field] === true) col.show();
                    });
                }
                const existingFields = {};
                table.getColumns().forEach(col => {
                    const field = col.getField();
                    if (field) existingFields[field] = col;
                });
                PERMANENTLY_HIDDEN_FIELDS.forEach(field => {
                    const c = existingFields[field];
                    if (c) c.hide();
                });
            }

            function saveColumnVisibility() {
                if (!table) return Promise.resolve();
                const visibility = {};
                table.getColumns().forEach(col => {
                    const def = col.getDefinition();
                    if (def.field) visibility[def.field] = col.isVisible();
                });
                savedColumnVisibility = visibility;
                return fetch(COLUMN_VISIBILITY_URL, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': columnCsrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        channel: COLUMN_VISIBILITY_CHANNEL,
                        visibility: visibility,
                    }),
                }).then(r => {
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    return r.json().catch(() => ({}));
                });
            }

            // Build Column Visibility Dropdown — grouped, with a header checkbox per group.
            const COLUMN_MENU_GROUPS = [
                { id: 'basic', label: 'Basic', fields: ['logo', 'Channel ', 'type', 'brand_authorisation'] },
                { id: 'today', label: 'Today', fields: ['Today Sales', 'T Orders', 'T CVR', 'T Views'] },
                { id: 'yesterday', label: 'Yesterday', fields: ['Y Sales', 'Y Orders', 'Y CVR', 'Y Views', 'Y GROI%', 'YNPFT%'] },
                { id: 'l30', label: 'L30', fields: ['L30 Sales', 'L30 Orders', 'Qty', 'G Roi', 'Gprofit%', 'N ROI', 'N PFT', 'Total Views', 'CVR'] },
                { id: 'projected', label: 'Projected', fields: ['P-Sales', 'P Growth', 'P GROI%', 'PNPFT%'] },
                { id: 'ads', label: 'Advertisement', fields: ['Total Ad Spend', 'Ads%'] },
                { id: 'inv', label: 'Inv', fields: [] },
                { id: 'others', label: 'Others', fields: [] },
            ];

            function columnMenuLabel(text) {
                return String(text || '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/"/g, '&quot;');
            }

            function syncColumnGroupHeader(groupId) {
                const menu = document.getElementById('column-dropdown-list');
                if (!menu) return;
                const head = menu.querySelector('input[data-group-toggle="' + groupId + '"]');
                const boxes = menu.querySelectorAll('input[data-field][data-group="' + groupId + '"]');
                if (!head || !boxes.length) return;
                let checked = 0;
                boxes.forEach(function(box) { if (box.checked) checked += 1; });
                head.checked = checked === boxes.length;
                head.indeterminate = checked > 0 && checked < boxes.length;
            }

            function buildColumnDropdown() {
                const menu = document.getElementById("column-dropdown-list");
                if (!menu || !table) return;

                const byField = {};
                table.getColumns().forEach(col => {
                    const def = col.getDefinition();
                    const field = def.field;
                    if (!field) return;
                    if (PERMANENTLY_HIDDEN_FIELDS.indexOf(field) !== -1) return;
                    if (COLUMNS_MENU_EXCLUDED_FIELDS.indexOf(field) !== -1) return;
                    byField[field] = {
                        field: field,
                        visible: col.isVisible(),
                        label: def.title || field,
                        hover: def.menuTitle || def.title || field,
                    };
                });

                const claimed = {};
                const sections = COLUMN_MENU_GROUPS.map(function(group) {
                    const items = [];
                    group.fields.forEach(function(field) {
                        if (!byField[field] || claimed[field]) return;
                        claimed[field] = true;
                        items.push(byField[field]);
                    });
                    return { id: group.id, label: group.label, items: items };
                });
                const others = sections.find(function(section) { return section.id === 'others'; });
                Object.keys(byField).forEach(function(field) {
                    if (claimed[field] || !others) return;
                    others.items.push(byField[field]);
                });

                menu.innerHTML = '';
                sections.forEach(function(section) {
                    if (!section.items.length) return;
                    const wrap = document.createElement('div');
                    wrap.className = 'amm-col-group';
                    const checkedCount = section.items.filter(function(item) { return item.visible; }).length;
                    const allOn = checkedCount === section.items.length;
                    const someOn = checkedCount > 0 && !allOn;
                    wrap.innerHTML =
                        '<label class="amm-col-group-head">'
                        + '<input type="checkbox" data-group-toggle="' + section.id + '"' + (allOn ? ' checked' : '') + '>'
                        + columnMenuLabel(section.label)
                        + '</label>'
                        + '<div class="amm-col-group-items">'
                        + section.items.map(function(item) {
                            return '<label class="dropdown-item py-1 px-2 text-truncate" style="white-space:nowrap;" title="'
                                + columnMenuLabel(item.hover) + '">'
                                + '<input type="checkbox" data-group="' + section.id + '" data-field="'
                                + columnMenuLabel(item.field) + '"' + (item.visible ? ' checked' : '') + '> '
                                + columnMenuLabel(item.label)
                                + '</label>';
                        }).join('')
                        + '</div>';
                    menu.appendChild(wrap);
                    const head = wrap.querySelector('input[data-group-toggle]');
                    if (head) head.indeterminate = someOn;
                });
            }

            function applyColumnMenuToggle(field, show) {
                const col = table.getColumn(field);
                if (!col) return;
                if (show) col.show();
                else col.hide();
            }

            function queueColumnVisibilitySave() {
                clearTimeout(columnVisibilitySaveTimer);
                columnVisibilitySaveTimer = setTimeout(function() {
                    saveColumnVisibility().catch(function() {
                        if (typeof showToast === 'function') {
                            showToast('error', 'Failed to save column settings.');
                        }
                    });
                }, 300);
            }

            // Autosave column visibility whenever a checkbox is toggled.
            let columnVisibilitySaveTimer = null;
            document.getElementById("column-dropdown-menu").addEventListener("change", function(e) {
                if (!e.target || e.target.type !== 'checkbox') return;
                const groupToggle = e.target.getAttribute('data-group-toggle');
                if (groupToggle) {
                    const show = e.target.checked;
                    e.target.indeterminate = false;
                    document.querySelectorAll('#column-dropdown-list input[data-field][data-group="' + groupToggle + '"]').forEach(function(box) {
                        box.checked = show;
                        applyColumnMenuToggle(box.getAttribute('data-field'), show);
                    });
                    fitAmmColumnsToData();
                    queueColumnVisibilitySave();
                    return;
                }
                const field = e.target.getAttribute('data-field');
                if (!field) return;
                applyColumnMenuToggle(field, e.target.checked);
                syncColumnGroupHeader(e.target.getAttribute('data-group'));
                fitAmmColumnsToData();
                queueColumnVisibilitySave();
            });

            // Table built event — load saved column visibility, apply it, then build the menu.
            table.on('tableBuilt', function() {
                fetchColumnVisibility().then(() => {
                    applyColumnVisibility();
                    buildColumnDropdown();
                });
            });

            // Table data loaded: rebuild dropdown; dot colors are loaded from ajaxResponse on first data load.
            table.on('dataLoaded', function() {
                setTimeout(function() {
                    buildColumnDropdown();
                    fitAmmColumnsToData();
                    if (typeof applyYOrdersToTable === 'function') applyYOrdersToTable();
                    if (typeof applyL30FallbackDots === 'function') applyL30FallbackDots();
                }, 100);
                if (!dotTrendsLoadedOnce && table.getData && table.getData().length) {
                    dotTrendsLoadedOnce = true;
                    loadMetricDotTrends(table.getData());
                }
            });

            // Show clicks breakdown modal - Fetch data from adv_masters_datas table
            function showClicksBreakdown(channelName) {
                console.log('Opening clicks breakdown for:', channelName);

                // Set modal title
                $('#clicksModalChannelName').text(channelName);

                // Show loading state
                $('#clicksBreakdownTableBody').html(
                    '<tr><td colspan="2" class="text-center"><div class="spinner-border spinner-border-sm text-info" role="status"><span class="visually-hidden">Loading...</span></div> Loading...</td></tr>'
                    );

                // Reset totals
                $('#clicksBreakdownTotalClicks').text('0');

                // Show modal
                const modal = new bootstrap.Modal(document.getElementById('clicksBreakdownModal'));
                modal.show();

                // Fetch breakdown data from backend
                $.ajax({
                    url: '/channel-clicks-breakdown',
                    method: 'GET',
                    data: {
                        channel: channelName
                    },
                    success: function(response) {
                        console.log('Clicks breakdown response:', response);

                        if (response.success && response.data && response.data.length > 0) {
                            let html = '';
                            let totalClicks = 0;

                            // Sort by type (PT, KW, HL)
                            response.data.sort((a, b) => {
                                const orderMap = {
                                    'PT': 1,
                                    'KW': 2,
                                    'HL': 3
                                };
                                return (orderMap[a.type] || 4) - (orderMap[b.type] || 4);
                            });

                            response.data.forEach(item => {
                                const clicks = parseInt(item.clicks) || 0;
                                totalClicks += clicks;

                                html += `
                                    <tr>
                                        <td><strong>${item.type}</strong> <small class="text-muted">(${item.channel})</small></td>
                                        <td class="text-end"><strong>${clicks > 0 ? clicks.toLocaleString('en-US') : '0'}</strong></td>
                                    </tr>
                                `;
                            });

                            $('#clicksBreakdownTableBody').html(html);

                            // Update totals
                            $('#clicksBreakdownTotalClicks').text(totalClicks.toLocaleString('en-US'));
                        } else {
                            $('#clicksBreakdownTableBody').html(
                                '<tr><td colspan="2" class="text-center text-muted">No PT/KW/HL breakdown available for this channel</td></tr>'
                                );
                        }
                    },
                    error: function(xhr) {
                        console.error('Error fetching clicks breakdown:', xhr);
                        $('#clicksBreakdownTableBody').html(
                            '<tr><td colspan="2" class="text-center text-danger">Error loading data</td></tr>'
                            );
                    }
                });
            }

            // Show Ad Sales Breakdown Modal (Ad Sales focused)
            function showAdSalesBreakdown(channelName) {
                console.log('Opening ad sales breakdown for:', channelName);

                // Set modal title
                $('#salesModalChannelName').text(channelName);

                // Show loading state
                $('#adSalesBreakdownTableBody').html(
                    '<tr><td colspan="4" class="text-center"><div class="spinner-border spinner-border-sm text-success" role="status"><span class="visually-hidden">Loading...</span></div> Loading...</td></tr>'
                    );

                // Reset totals
                $('#adSalesBreakdownTotalSales').text('$0');
                $('#adSalesBreakdownTotalAcos').text('-');
                $('#adSalesBreakdownTotalTacos').text('-');

                // Show modal
                const modal = new bootstrap.Modal(document.getElementById('adSalesBreakdownModal'));
                modal.show();

                // Fetch breakdown data from backend
                $.ajax({
                    url: '/channel-clicks-breakdown',
                    method: 'GET',
                    data: {
                        channel: channelName
                    },
                    success: function(response) {
                        console.log('Ad sales breakdown response:', response);

                        if (response.success && response.data && response.data.length > 0) {
                            let html = '';
                            let totalSpent = 0;
                            let totalAdSales = 0;

                            // Sort by type (PT, KW, HL)
                            response.data.sort((a, b) => {
                                const orderMap = {
                                    'PT': 1,
                                    'KW': 2,
                                    'HL': 3
                                };
                                return (orderMap[a.type] || 4) - (orderMap[b.type] || 4);
                            });

                            response.data.forEach(item => {
                                const spent = parseFloat(item.spent) || 0;
                                const adSales = parseFloat(item.ad_sales) || 0;
                                const acos = parseFloat(item.acos) || 0;
                                const tacos = parseFloat(item.tacos) || 0;

                                totalSpent += spent;
                                totalAdSales += adSales;

                                html += `
                                    <tr>
                                        <td><strong>${item.type}</strong> <small class="text-muted">(${item.channel})</small></td>
                                        <td class="text-end"><strong>$${adSales > 0 ? Math.round(adSales).toLocaleString('en-US') : '0'}</strong></td>
                                        <td class="text-end">${acos > 0 ? acos.toFixed(2) + '%' : '-'}</td>
                                        <td class="text-end">${tacos > 0 ? tacos.toFixed(2) + '%' : '-'}</td>
                                    </tr>
                                `;
                            });

                            $('#adSalesBreakdownTableBody').html(html);

                            // Calculate totals using formulas:
                            // ACOS = (spent / ad_sales) * 100
                            const totalAcos = totalAdSales > 0 ? ((totalSpent / totalAdSales) * 100)
                                .toFixed(2) : 0;
                            // TACOS = (spent / parent_l30_sales) * 100
                            const parentL30Sales = parseFloat(response.parent_l30_sales) || 0;
                            const totalTacos = parentL30Sales > 0 ? ((totalSpent / parentL30Sales) *
                                100).toFixed(2) : 0;

                            // Update totals
                            $('#adSalesBreakdownTotalSales').text('$' + Math.round(totalAdSales)
                                .toLocaleString('en-US'));
                            $('#adSalesBreakdownTotalAcos').text(totalAcos > 0 ? totalAcos + '%' : '-');
                            $('#adSalesBreakdownTotalTacos').text(totalTacos > 0 ? totalTacos + '%' :
                                '-');
                        } else {
                            $('#adSalesBreakdownTableBody').html(
                                '<tr><td colspan="4" class="text-center text-muted">No PT/KW/HL breakdown available for this channel</td></tr>'
                                );
                        }
                    },
                    error: function(xhr) {
                        console.error('Error fetching ad sales breakdown:', xhr);
                        $('#adSalesBreakdownTableBody').html(
                            '<tr><td colspan="4" class="text-center text-danger">Error loading data</td></tr>'
                            );
                    }
                });
            }

            // Show CVR Breakdown Modal (CVR only)
            function showCvrBreakdown(channelName) {
                console.log('Opening CVR breakdown for:', channelName);

                // Set modal title
                $('#cvrModalChannelName').text(channelName);

                // Show loading state
                $('#cvrBreakdownTableBody').html(
                    '<tr><td colspan="2" class="text-center"><div class="spinner-border spinner-border-sm text-primary" role="status"><span class="visually-hidden">Loading...</span></div> Loading...</td></tr>'
                    );

                // Reset totals
                $('#cvrBreakdownTotalCvr').text('-');

                // Show modal
                const modal = new bootstrap.Modal(document.getElementById('cvrBreakdownModal'));
                modal.show();

                // Fetch breakdown data from backend
                $.ajax({
                    url: '/channel-clicks-breakdown',
                    method: 'GET',
                    data: {
                        channel: channelName
                    },
                    success: function(response) {
                        console.log('CVR breakdown response:', response);

                        if (response.success && response.data && response.data.length > 0) {
                            let html = '';
                            let totalClicks = 0;
                            let totalAdSold = 0;

                            // Sort by type (PT, KW, HL)
                            response.data.sort((a, b) => {
                                const orderMap = {
                                    'PT': 1,
                                    'KW': 2,
                                    'HL': 3
                                };
                                return (orderMap[a.type] || 4) - (orderMap[b.type] || 4);
                            });

                            response.data.forEach(item => {
                                const clicks = parseInt(item.clicks) || 0;
                                const adSold = parseInt(item.ad_sold) || 0;
                                const cvr = parseFloat(item.cvr) || 0;

                                totalClicks += clicks;
                                totalAdSold += adSold;

                                html += `
                                    <tr>
                                        <td><strong>${item.type}</strong> <small class="text-muted">(${item.channel})</small></td>
                                        <td class="text-end"><strong>${cvr > 0 ? Math.round(cvr) + '%' : '-'}</strong></td>
                                    </tr>
                                `;
                            });

                            $('#cvrBreakdownTableBody').html(html);

                            // Calculate total CVR: (ads sold / clicks) * 100
                            const totalCvr = totalClicks > 0 ? Math.round((totalAdSold / totalClicks) *
                                100) : 0;

                            // Update totals
                            $('#cvrBreakdownTotalCvr').text(totalCvr > 0 ? totalCvr + '%' : '-');
                        } else {
                            $('#cvrBreakdownTableBody').html(
                                '<tr><td colspan="2" class="text-center text-muted">No PT/KW/HL breakdown available for this channel</td></tr>'
                                );
                        }
                    },
                    error: function(xhr) {
                        console.error('Error fetching CVR breakdown:', xhr);
                        $('#cvrBreakdownTableBody').html(
                            '<tr><td colspan="2" class="text-center text-danger">Error loading data</td></tr>'
                            );
                    }
                });
            }

            // Show Missing Ads Breakdown Modal (Missing Ads only)
            function showMissingAdsBreakdown(channelName) {
                console.log('Opening missing ads breakdown for:', channelName);

                // Set modal title
                $('#missingModalChannelName').text(channelName);

                // Show loading state
                $('#missingAdsBreakdownTableBody').html(
                    '<tr><td colspan="2" class="text-center"><div class="spinner-border spinner-border-sm text-danger" role="status"><span class="visually-hidden">Loading...</span></div> Loading...</td></tr>'
                    );

                // Reset totals
                $('#missingAdsBreakdownTotal').text('0');

                // Show modal
                const modal = new bootstrap.Modal(document.getElementById('missingAdsBreakdownModal'));
                modal.show();

                // Fetch breakdown data from backend
                $.ajax({
                    url: '/channel-clicks-breakdown',
                    method: 'GET',
                    data: {
                        channel: channelName
                    },
                    success: function(response) {
                        console.log('Missing ads breakdown response:', response);

                        if (response.success && response.data && response.data.length > 0) {
                            let html = '';
                            let totalMissingAds = 0;

                            // Sort by type (PT, KW, HL)
                            response.data.sort((a, b) => {
                                const orderMap = {
                                    'PT': 1,
                                    'KW': 2,
                                    'HL': 3
                                };
                                return (orderMap[a.type] || 4) - (orderMap[b.type] || 4);
                            });

                            response.data.forEach(item => {
                                const missingAds = parseInt(item.missing_ads) || 0;
                                totalMissingAds += missingAds;

                                html += `
                                    <tr>
                                        <td><strong>${item.type}</strong> <small class="text-muted">(${item.channel})</small></td>
                                        <td class="text-end"><strong style="color:#dc3545;">${missingAds > 0 ? missingAds.toLocaleString('en-US') : '0'}</strong></td>
                                    </tr>
                                `;
                            });

                            $('#missingAdsBreakdownTableBody').html(html);

                            // Update totals
                            $('#missingAdsBreakdownTotal').text(totalMissingAds.toLocaleString(
                            'en-US'));
                        } else {
                            $('#missingAdsBreakdownTableBody').html(
                                '<tr><td colspan="2" class="text-center text-muted">No PT/KW/HL breakdown available for this channel</td></tr>'
                                );
                        }
                    },
                    error: function(xhr) {
                        console.error('Error fetching missing ads breakdown:', xhr);
                        $('#missingAdsBreakdownTableBody').html(
                            '<tr><td colspan="2" class="text-center text-danger">Error loading data</td></tr>'
                            );
                    }
                });
            }

            // Ad Breakdown Chart variables
            let adBreakdownChartInstance = null;
            let salesOrdersItemsBarChartInstance = null;
            let currentChartChannel = '';
            let currentChartAdType = '';
            let currentChartMetric = 'spend';
            // Trend chart default range — fixed at 30 days (matches the modal dropdown's pre-selected option).
            let currentChartDays = 30;
            let adChartAjax = null; // track in-flight request
            let currentChartMode = 'ad'; // 'ad' = ad breakdown, 'metric' = channel metric
            let currentMetricKey = ''; // metric key for channel metric mode

            // Channels that have daily data
            const channelsWithDailyData = ['amazon', 'amazonfba', 'ebay', 'ebaytwo', 'ebaythree', 'shopifyb2c', 'temu', 'temu2', 'temu3', 'topdawg', 'walmart'];
            const adTypesForChannel = {
                'amazon': ['kw', 'pt', 'hl'],
                'amazonfba': ['kw', 'pt'],
                'ebay': ['kw', 'pmt'],
                'ebaytwo': ['kw', 'pmt'],
                'ebaythree': ['kw', 'pmt'],
                'shopifyb2c': ['shopping', 'serp'],
                'temu': ['kw'],
                'temu2': ['kw'],
                'topdawg': ['kw'],
                'walmart': ['kw']
            };

            // Date helper — YYYY-MM-DD in local time
            const adChartFmtDate = (d) => {
                return d.getFullYear() + '-' +
                    String(d.getMonth() + 1).padStart(2, '0') + '-' +
                    String(d.getDate()).padStart(2, '0');
            };

            // Compute start/end from days selection
            function adChartDateRange(days) {
                const today = new Date();
                const end = new Date(today.getFullYear(), today.getMonth(), today.getDate() - 2);
                if (days === 0) {
                    // Lifetime — send no start_date so backend returns all available data
                    return { start: null, end: adChartFmtDate(end) };
                }
                const start = new Date(end.getFullYear(), end.getMonth(), end.getDate() - days + 1);
                return { start: adChartFmtDate(start), end: adChartFmtDate(end) };
            }

            // Range label for modal title
            function adChartRangeLabel(days) {
                if (days === 0) return 'Lifetime';
                return 'L' + days;
            }

            // Show Ad Breakdown Chart Modal
            function showAdBreakdownChart(channel, adType, metricType = 'spend') {
                currentChartMode = 'ad';
                currentChartChannel = snapshotChannelKey(channel);
                currentChartAdType = adType.toLowerCase();
                currentChartMetric = metricType;
                currentChartDays = 30; // ad reports: default rolling 30

                const hasData = channelsWithDailyData.includes(currentChartChannel) &&
                    (adTypesForChannel[currentChartChannel] || []).includes(currentChartAdType);

                $('#adChartRangeSelect').val('30');

                // Set modal title
                const adTypeLabel = currentChartAdType.toUpperCase();
                let metricLabel;
                if (metricType === 'acos') metricLabel = 'ACOS %';
                else if (metricType === 'cvr') metricLabel = 'CVR';
                else metricLabel = metricType.charAt(0).toUpperCase() + metricType.slice(1);
                $('#adChartModalTitle').text(`${channel} - ${adTypeLabel} ${metricLabel} (Rolling ${adChartRangeLabel(currentChartDays)})`);

                // Show modal
                openAdBreakdownChartModal();

                if (!hasData) {
                    $('#adBreakdownChartContainer').hide();
                    $('#adChartLoading').hide();
                    $('#adChartNoData').show();
                    return;
                }

                loadAdBreakdownChart();
            }

            // Summary badge dots must read the same last-two points as the graph
            // (graph dot = last value vs the day before). The dot-trend cache is built
            // from a different snapshot table and could disagree (green dot, red graph).
            function syncAllBadgeDotWithChart(metric, values) {
                if (!metric || !Array.isArray(values) || values.length < 2) return;
                // Today badges: the graph metric key differs from the dot's data-metric.
                var dotMetricByChart = { t_npft_pct: 't_npft', t_groi_pct: 't_groi', t_nroi_pct: 't_nroi', t_ads_pct: 't_ads' };
                var dotMetric = dotMetricByChart[metric] || metric;
                var v1 = Number(values[values.length - 2]);
                var v2 = Number(values[values.length - 1]);
                if (!isFinite(v1) || !isFinite(v2)) return;
                if (!$('#summary-stats .summary-trend-dot[data-metric="' + dotMetric + '"]').length) return;
                lastDotPairByKey['all_' + dotMetric] = [v1, v2];
                chartSyncedAllMetrics[dotMetric] = true;
                chartDotSynced[dotMetric] = true;
                colorSummaryBadgeDots(channelKeysFromTableData());
            }

            // Colour a badge dot on page load exactly like the graph does after a click:
            // fetch the same series (history + live badge value) and sync the dot from it.
            // Used by badges whose history lives in badges_data_histories (no snapshot pair).
            var badgeChartDotPrefetched = {};
            function prefetchAllBadgeChartDot(metric, badgeValue) {
                if (!metric || badgeValue === null || badgeValue === undefined || isNaN(badgeValue)) return;
                var sig = String(Math.round(Number(badgeValue) * 100) / 100);
                var state = badgeChartDotPrefetched[metric];
                if (state && state.sig === sig) return;
                badgeChartDotPrefetched[metric] = { sig: sig };
                $.ajax({
                    url: '/channel-metric-chart-data',
                    method: 'GET',
                    data: { channel: 'all', metric: metric, days: 30, badge_value: badgeValue }
                }).done(function(response) {
                    var rows = response && response.success && Array.isArray(response.data) ? response.data : [];
                    syncAllBadgeDotWithChart(metric, rows.map(function(r) { return Number(r.value); }));
                }).fail(function() {
                    delete badgeChartDotPrefetched[metric];
                });
            }

            // Forecast badges (MSL_LP, MSL_SP, Ord, MIP, R2S, Trn, NPFT, NROI): server-rendered
            // values whose history is in badges_data_histories. Their dot is injected by the
            // global footer script, so wait for it, then colour it from the same series the graph
            // shows (last point vs the day before) — no click needed.
            function primeForecastBadgeDots() {
                ['fa_msl_lp', 'fa_msl_sp', 'fa_ord', 'fa_mip', 'fa_r2s', 'fa_trn', 'fa_npft', 'fa_nroi'].forEach(function(metric) {
                    if (badgeChartDotPrefetched[metric]) return;
                    badgeChartDotPrefetched[metric] = { sig: 'forecast' };
                    $.ajax({
                        url: '/channel-metric-chart-data',
                        method: 'GET',
                        data: { channel: 'all', metric: metric, days: 30 }
                    }).done(function(response) {
                        var rows = response && response.success && Array.isArray(response.data) ? response.data : [];
                        var values = rows.map(function(r) { return Number(r.value); });
                        var tries = 0;
                        (function apply() {
                            if ($('#summary-stats .summary-trend-dot[data-metric="' + metric + '"]').length || tries >= 10) {
                                syncAllBadgeDotWithChart(metric, values);
                                return;
                            }
                            tries++;
                            setTimeout(apply, 500);
                        })();
                    }).fail(function() {
                        delete badgeChartDotPrefetched[metric];
                    });
                });
            }
            setTimeout(primeForecastBadgeDots, 800);

            function prefetchAllYSalesChart(badgeValue) {
                if (ySalesAllChartPrefetch && ySalesAllChartPrefetch.readyState !== 4) {
                    return;
                }
                ySalesAllChartPrefetch = $.ajax({
                    url: '/channel-metric-chart-data',
                    method: 'GET',
                    data: {
                        channel: 'all',
                        metric: 'y_sales',
                        days: 30,
                        badge_value: badgeValue
                    }
                }).done(function(response) {
                    var rows = response && response.success && Array.isArray(response.data) ? response.data : [];
                    syncAllBadgeDotWithChart('y_sales', rows.map(function(r) { return Number(r.value); }));
                });
            }

            function applyMetricChartResponse(response) {
                adChartAjax = null;
                $('#adChartLoading').hide();
                if (response && response.success && response.data && response.data.length > 0) {
                    $('#adBreakdownChartContainer').show();
                    renderAdBreakdownChart(response.data);
                    if (currentChartMode === 'metric') {
                        loadSalesOrdersItemsBarChart();
                    } else {
                        $('#salesOrdersItemsBarContainer').hide();
                        if (salesOrdersItemsBarChartInstance) {
                            salesOrdersItemsBarChartInstance.destroy();
                            salesOrdersItemsBarChartInstance = null;
                        }
                    }
                } else {
                    $('#adChartNoData').show();
                    $('#salesOrdersItemsBarContainer').hide();
                    if (salesOrdersItemsBarChartInstance) {
                        salesOrdersItemsBarChartInstance.destroy();
                        salesOrdersItemsBarChartInstance = null;
                    }
                }
            }

            // Load chart data (handles both ad-breakdown and channel-metric modes)
            function loadAdBreakdownChart() {
                // Reuse the All Y Sales prefetch so the badge opens immediately.
                if (currentChartMode === 'metric'
                    && currentChartChannel === 'all'
                    && currentMetricKey === 'y_sales'
                    && currentChartDays === 30
                    && ySalesAllChartPrefetch) {
                    $('#adChartNoData').hide();
                    $('#adBreakdownChartContainer').hide();
                    $('#adChartLoading').show();
                    ySalesAllChartPrefetch
                        .done(function(response) { applyMetricChartResponse(response); })
                        .fail(function(xhr, status) {
                            if (status === 'abort') return;
                            ySalesAllChartPrefetch = null;
                            loadAdBreakdownChartFresh();
                        });
                    return;
                }

                loadAdBreakdownChartFresh();
            }

            function loadAdBreakdownChartFresh() {
                // Abort any previous in-flight request
                if (adChartAjax) adChartAjax.abort();

                $('#adChartNoData').hide();
                $('#adBreakdownChartContainer').hide();
                $('#adChartLoading').show();

                let url, params;

                if (currentChartMode === 'metric') {
                    // Channel metric mode — uses ChannelMasterSummary snapshots
                    url = '/channel-metric-chart-data';
                    params = {
                        channel: currentChartChannel,
                        metric: currentMetricKey,
                        days: currentChartDays
                    };
                    if (currentCellValue !== null) {
                        params.badge_value = currentCellValue;
                    }
                } else {
                    // Ad breakdown mode — uses daily ad campaign reports
                    url = '/ad-breakdown-chart-data';
                    const range = adChartDateRange(currentChartDays);
                    params = {
                        channel: currentChartChannel,
                        ad_type: currentChartAdType,
                        metric: currentChartMetric,
                        end_date: range.end
                    };
                    if (range.start) params.start_date = range.start;
                }

                params._ = Date.now();
                adChartAjax = $.ajax({
                    url: url,
                    method: 'GET',
                    cache: false,
                    data: params,
                    success: function(response) {
                        adChartAjax = null;
                        $('#adChartLoading').hide();

                        if (response.success && response.data && response.data.length > 0) {
                            $('#adBreakdownChartContainer').show();
                            renderAdBreakdownChart(response.data);
                            if (currentChartMode === 'metric') {
                                loadSalesOrdersItemsBarChart();
                            } else {
                                $('#salesOrdersItemsBarContainer').hide();
                                if (salesOrdersItemsBarChartInstance) {
                                    salesOrdersItemsBarChartInstance.destroy();
                                    salesOrdersItemsBarChartInstance = null;
                                }
                            }
                        } else {
                            $('#adChartNoData').show();
                            $('#salesOrdersItemsBarContainer').hide();
                            if (salesOrdersItemsBarChartInstance) {
                                salesOrdersItemsBarChartInstance.destroy();
                                salesOrdersItemsBarChartInstance = null;
                            }
                        }
                    },
                    error: function(xhr, status) {
                        adChartAjax = null;
                        if (status === 'abort') return;
                        console.error('Error fetching chart data:', xhr);
                        $('#adChartLoading').hide();
                        $('#adChartNoData').show();
                        $('#salesOrdersItemsBarContainer').hide();
                        if (salesOrdersItemsBarChartInstance) {
                            salesOrdersItemsBarChartInstance.destroy();
                            salesOrdersItemsBarChartInstance = null;
                        }
                    }
                });
            }

            // Metric label map for titles
            const metricLabels = {
                'l60_sales': 'L60 Sales',
                'l60_orders': 'L60 Orders',
                'l30_sales': 'Sales',
                'y_sales': 'Y Sales',
                'today_sales': 'Today Sales',
                't_orders': 'Today Orders',
                't_npft_pct': 'Today Nprft%',
                't_groi_pct': 'Today Groi%',
                't_nroi_pct': 'Today NROI%',
                't_ads_pct': 'Today Ads%',
                't_views': 'Today Views',
                't_cvr': 'Today CVR',
                'p_orders': 'Projected Orders',
                'p_ads_pct': 'Projected Ads%',
                'p_views': 'Projected Views',
                'y_orders': 'Y Orders',
                'y_views': 'Y Views',
                'y_cvr': 'Y CVR',
                'fa_msl_lp': 'Forecast MSL_LP',
                'fa_msl_sp': 'Forecast MSL_SP',
                'fa_ord': 'Forecast Ord',
                'fa_mip': 'Forecast MIP',
                'fa_r2s': 'Forecast R2S',
                'fa_trn': 'Forecast Trn',
                'fa_npft': 'Forecast NPFT%',
                'fa_nroi': 'Forecast NROI%',
                'y_pft': 'Y PFT',
                'y_npft_amt': 'Y NPFT',
                'p_sales': 'P-Sales',
                'p_npft_amt': 'P NPFT',
                'l30_orders': 'Orders',
                'qty': 'Qty',
                'gprofit': 'Gprofit%',
                'groi': 'G ROI%',
                'ads_pct': 'TAcos %',
                'pft': 'Total Pft',
                'npft': 'N PFT%',
                'p_npft': 'PNPFT%',
                'p_groi_pct': 'P GROI%',
                'p_nroi_pct': 'P-NROI%',
                'y_npft_pct': 'YNPFT%',
                'y_groi_pct': 'Y GROI%',
                'y_nroi_pct': 'Y NROI%',
                'nroi': 'N ROI%',
                'missing_l': 'Missing L',
                'map': 'Map',
                'nmap': 'N Map',
                'ad_spend': 'Spend',
                'clicks': 'AD Clicks',
                'ad_sales': 'AD Sales',
                'ad_sold': 'AD Sold',
                'acos': 'ACOS',
                'ads_cvr': 'AD CVR',
                'cvr': 'CVR',
                'total_views': 'views',
                'inv_at_lp': 'Inv@LP',
                'inv_at_sp': 'Inv@SP',
                'inventory': 'inv',
                'tat': 'TAT',
                'reviews': 'Reviews',
                'rating': 'Rating',
            };

            // Show metric chart (for non-ad-breakdown columns)
            var currentCellValue = null;
            function showMetricChart(channel, metricKey, cellValue) {
                currentChartMode = 'metric';
                currentChartChannel = snapshotChannelKey(channel);
                currentMetricKey = metricKey;
                currentChartMetric = metricKey; // for fmtVal formatting
                // Trend chart default range — always opens at 30 days regardless of any other window.
                currentChartDays = 30;
                currentCellValue = (cellValue !== undefined && cellValue !== null && !isNaN(cellValue)) ? cellValue : null;

                $('#adChartRangeSelect').val('30');

                // Set title
                const label = metricLabels[metricKey] || metricKey;
                $('#adChartModalTitle').text(`${channel} - ${label} (Rolling ${adChartRangeLabel(currentChartDays)})`);

                // Show modal (getOrCreateInstance — new Modal() throws if one already exists)
                openAdBreakdownChartModal();

                loadAdBreakdownChart();
            }
            window.showMetricChart = showMetricChart;

            function openAdBreakdownChartModal() {
                const el = document.getElementById('adBreakdownChartModal');
                if (!el || typeof bootstrap === 'undefined') {
                    console.error('Y Sales / metric chart modal cannot open: missing #adBreakdownChartModal or bootstrap');
                    return;
                }
                el.style.zIndex = '10050';
                const inst = bootstrap.Modal.getOrCreateInstance(el);
                inst.show();
            }

            // Range dropdown change handler
            $(document).on('change', '#adChartRangeSelect', function() {
                const days = parseInt($(this).val());
                if (days === currentChartDays) return;
                currentChartDays = days;

                // Update modal title range label
                const titleEl = $('#adChartModalTitle');
                titleEl.text(titleEl.text().replace(/\(Rolling [^)]+\)/, `(Rolling ${adChartRangeLabel(days)})`));

                loadAdBreakdownChart();
            });

            // Shein / Aliexpress / Faire: hover Miss|Map|NMap cell → trend chart; click → pricing page filter
            $(document).on('mouseenter', '#marketplace-table .ac-pricing-hover-cell', function() {
                const $el = $(this);
                const channel = $el.data('channel');
                const metric = $el.data('chart-metric');
                if (!channel || !metric) return;
                if (acPricingCellHoverTimer) clearTimeout(acPricingCellHoverTimer);
                const cv = parseFloat(String($el.text()).replace(/,/g, '')) || null;
                acPricingCellHoverTimer = setTimeout(function() {
                    showMetricChart(channel, metric, cv);
                }, 500);
            });
            $(document).on('mouseleave', '#marketplace-table .ac-pricing-hover-cell', function() {
                if (acPricingCellHoverTimer) {
                    clearTimeout(acPricingCellHoverTimer);
                    acPricingCellHoverTimer = null;
                }
            });
            $(document).on('mousedown', '#marketplace-table .ac-pricing-hover-cell', function() {
                if (acPricingCellHoverTimer) {
                    clearTimeout(acPricingCellHoverTimer);
                    acPricingCellHoverTimer = null;
                }
            });

            // Parse compact badge text (e.g. "326K", "1.7M", "$12,345", "46%") into a number.
            function parseBadgeDisplayValue(raw) {
                if (raw === undefined || raw === null) return null;
                let text = String(raw).replace(/[,$%\s]/g, '').trim();
                if (!text || text === 'NYS' || text === '-' || text === 'N/A') return null;
                const suffix = text.slice(-1).toUpperCase();
                let mult = 1;
                if (suffix === 'K') { mult = 1000; text = text.slice(0, -1); }
                else if (suffix === 'M') { mult = 1000000; text = text.slice(0, -1); }
                const n = parseFloat(text);
                return (isNaN(n) ? null : n * mult);
            }

            function ammInvMoneyCompact(n) {
                const v = Math.round(Number(n) || 0);
                const abs = Math.abs(v);
                if (abs >= 1000000) return '$' + (v / 1000000).toFixed(1).replace(/\.0$/, '') + 'M';
                if (abs >= 1000) return '$' + Math.round(v / 1000) + 'K';
                return '$' + v.toLocaleString('en-US');
            }
            function ammInvNormalizeSlices(raw) {
                const defs = [
                    { key: 'red', label: 'Red <25%', color: '#a00211' },
                    { key: 'green', label: 'Green 25–50%', color: '#28a745' },
                    { key: 'pink', label: 'Pink ≥50%', color: '#e83e8c' },
                ];
                const byKey = {};
                (raw || []).forEach(function(s) {
                    const key = String(s.key || s.color || '').toLowerCase();
                    if (!key) return;
                    byKey[key] = s;
                });
                return defs.map(function(d) {
                    const s = byKey[d.key] || {};
                    return {
                        key: d.key,
                        label: s.label || d.label,
                        color: s.color || d.color,
                        count: Number(s.count) || 0,
                        units: Number(s.units) || 0,
                        inv_at_lp: Number(s.inv_at_lp != null ? s.inv_at_lp : s.inv) || 0,
                        inv_at_sp: Number(s.inv_at_sp) || 0,
                    };
                });
            }
            function ammInvLegendHtml(chart, slices, valueKey, includeHist) {
                if (includeHist === undefined) includeHist = true;
                const total = slices.reduce(function(sum, s) { return sum + (Number(s[valueKey]) || 0); }, 0);
                return '<div class="amm-inv-pie-row" style="color:#94a3b8;font-size:10px;font-weight:600;">'
                    + '<span class="amm-inv-pie-swatch" style="visibility:hidden;"></span>'
                    + '<span class="amm-inv-pie-name">DIL</span>'
                    + '<span class="amm-inv-pie-count">count</span>'
                    + '<span class="amm-inv-pie-val">$</span>'
                    + '<span class="amm-inv-pie-pct">of total</span>'
                    + (includeHist ? '<span class="amm-inv-hist-dot" style="visibility:hidden;"></span>' : '')
                    + '</div>'
                    + slices.map(function(s) {
                        const n = Number(s[valueKey]) || 0;
                        const pct = total > 0 ? Math.round((n / total) * 100) : 0;
                        return '<div class="amm-inv-pie-row">'
                            + '<span class="amm-inv-pie-swatch" style="background:' + s.color + ';"></span>'
                            + '<span class="amm-inv-pie-name">' + s.label + '</span>'
                            + '<span class="amm-inv-pie-count">' + (s.count || 0) + '</span>'
                            + '<span class="amm-inv-pie-val">' + ammInvMoneyCompact(n) + '</span>'
                            + '<span class="amm-inv-pie-pct">' + pct + '%</span>'
                            + (includeHist
                                ? '<button type="button" class="amm-inv-hist-dot" data-chart="' + chart + '" data-band="' + s.key + '" '
                                    + 'style="background:' + s.color + ';" title="' + s.label + ' daily history"></button>'
                                : '')
                            + '</div>';
                    }).join('');
            }
            function ammInvBlockBandsHtml(slices, valueKey) {
                const total = slices.reduce(function(sum, s) { return sum + (Number(s[valueKey]) || 0); }, 0);
                const tones = {
                    red: 'background:#fee2e2;color:#7f1d1d;',
                    green: 'background:#dcfce7;color:#14532d;',
                    pink: 'background:#fce7f3;color:#9d174d;'
                };
                return '<div style="display:flex;flex-wrap:nowrap;gap:8px;min-width:0;">' + slices.map(function(s) {
                    const n = Number(s[valueKey]) || 0;
                    const pct = total > 0 ? Math.round((n / total) * 100) : 0;
                    const tone = tones[s.key] || tones.red;
                    return '<div style="flex:1 1 0;min-width:0;border-radius:8px;padding:8px 10px;' + tone + '">'
                        + '<div style="font-size:12px;font-weight:800;white-space:nowrap;">' + s.label + '</div>'
                        + '<div style="font-size:13px;font-weight:700;white-space:nowrap;margin-top:2px;">' + (s.count || 0) + '</div>'
                        + '<div style="font-size:13px;font-weight:700;white-space:nowrap;">' + ammInvMoneyCompact(n) + ' · ' + pct + '%</div>'
                        + '</div>';
                }).join('') + '</div>';
            }
            function ammInvRenderBlock(payload) {
                const raw = (payload && payload.slices) || [];
                if (!raw.length) return;
                const slices = ammInvNormalizeSlices(raw);
                $('#amm-inv-block-sp').html(ammInvBlockBandsHtml(slices, 'inv_at_sp'));
                ammInvDrawColumn('amm-inv-block-sp-pie', slices, 'inv_at_sp');
            }
            function ammInvDrawColumn(canvasId, slices, valueKey) {
                const canvas = document.getElementById(canvasId);
                if (!canvas || typeof Chart === 'undefined') return;
                const total = slices.reduce(function(sum, s) { return sum + (Number(s[valueKey]) || 0); }, 0);
                if (ammInvBlockSpPieChart) {
                    ammInvBlockSpPieChart.destroy();
                    ammInvBlockSpPieChart = null;
                }
                const shortLabel = { red: 'Red', green: 'Green', pink: 'Pink' };
                const canvasBox = canvas.parentElement;
                if (canvasBox) {
                    canvasBox.style.width = '220px';
                    canvasBox.style.maxWidth = '220px';
                    canvasBox.style.height = '120px';
                    canvasBox.style.overflow = 'hidden';
                }
                canvas.style.width = '220px';
                canvas.style.height = '120px';
                canvas.style.maxWidth = '220px';
                ammInvBlockSpPieChart = new Chart(canvas.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: slices.map(function(s) { return shortLabel[s.key] || s.label; }),
                        datasets: [{
                            data: slices.map(function(s) { return Number(s[valueKey]) || 0; }),
                            backgroundColor: slices.map(function(s) { return s.color; }),
                            borderWidth: 0,
                            borderRadius: 3,
                            maxBarThickness: 36,
                            barPercentage: 0.8,
                            categoryPercentage: 0.7,
                        }],
                    },
                    options: {
                        responsive: false,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function(ctx) {
                                        const slice = slices[ctx.dataIndex] || {};
                                        const n = Number(ctx.raw) || 0;
                                        const pct = total > 0 ? Math.round((n / total) * 100) : 0;
                                        return ' ' + (slice.count || 0) + ' SKUs  ·  ' + ammInvMoneyCompact(n) + '  ·  ' + pct + '%';
                                    },
                                },
                            },
                        },
                        scales: {
                            x: {
                                grid: { display: false },
                                ticks: { font: { size: 10 }, color: '#334155' },
                            },
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    font: { size: 9 },
                                    color: '#64748b',
                                    maxTicksLimit: 4,
                                    callback: function(v) { return ammInvMoneyCompact(v); },
                                },
                                grid: { color: '#e2e8f0' },
                            },
                        },
                    },
                });
            }
            function ammInvDrawPie(canvasId, chartRef, slices, valueKey) {
                const canvas = document.getElementById(canvasId);
                if (!canvas || typeof Chart === 'undefined') return;
                const total = slices.reduce(function(sum, s) { return sum + (Number(s[valueKey]) || 0); }, 0);
                if (chartRef === 'lp' && ammInvLpPieChart) { ammInvLpPieChart.destroy(); ammInvLpPieChart = null; }
                if (chartRef === 'sp' && ammInvSpPieChart) { ammInvSpPieChart.destroy(); ammInvSpPieChart = null; }
                const chart = new Chart(canvas.getContext('2d'), {
                    type: 'pie',
                    data: {
                        labels: slices.map(function(s) { return s.label; }),
                        datasets: [{
                            data: slices.map(function(s) { return Number(s[valueKey]) || 0; }),
                            backgroundColor: slices.map(function(s) { return s.color; }),
                            borderColor: '#fff',
                            borderWidth: 1,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function(ctx) {
                                        const slice = slices[ctx.dataIndex] || {};
                                        const n = Number(ctx.raw) || 0;
                                        const pct = total > 0 ? Math.round((n / total) * 100) : 0;
                                        return ' ' + (slice.count || 0) + ' SKUs  ·  ' + ammInvMoneyCompact(n) + '  ·  ' + pct + '%';
                                    },
                                },
                            },
                        },
                    },
                });
                if (chartRef === 'lp') ammInvLpPieChart = chart;
                else if (chartRef === 'sp') ammInvSpPieChart = chart;
            }
            function ammInvRenderPies(payload) {
                const slices = ammInvNormalizeSlices((payload && payload.slices) || []);
                ammInvLiveSlices = slices;
                ammInvLiveHistory = Array.isArray(payload && payload.history) ? payload.history : [];
                lastInventoryPies = { slices: slices, history: ammInvLiveHistory };
                $('#amm-inv-lp-legend').html(ammInvLegendHtml('lp', slices, 'inv_at_lp'));
                $('#amm-inv-sp-legend').html(ammInvLegendHtml('sp', slices, 'inv_at_sp'));
                ammInvRenderBlock({ slices: slices });
                ammInvDrawPie('amm-inv-lp-pie', 'lp', slices, 'inv_at_lp');
                ammInvDrawPie('amm-inv-sp-pie', 'sp', slices, 'inv_at_sp');
            }
            function ammInvDrawHist(chart, band) {
                const spec = ammInvLiveSlices.find(function(s) { return s.key === band; })
                    || { key: band, label: band, color: '#111827' };
                const field = chart === 'sp' ? (band + '_sp') : (band + '_lp');
                const title = (chart === 'sp' ? 'Inv@SP ' : 'Inv@LP ') + spec.label;
                $('#amm-inv-hist-title').text(title);
                $('#amm-inv-hist-wrap').addClass('is-open');
                const rows = (ammInvLiveHistory || []).slice();
                const canvas = document.getElementById('amm-inv-hist');
                if (!canvas || typeof Chart === 'undefined') return;
                if (ammInvHistChart) {
                    ammInvHistChart.destroy();
                    ammInvHistChart = null;
                }
                ammInvHistChart = new Chart(canvas.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: rows.map(function(r) { return r.label || r.date; }),
                        datasets: [{
                            data: rows.map(function(r) { return Number(r[field] != null ? r[field] : r[band]) || 0; }),
                            borderColor: spec.color,
                            backgroundColor: spec.color + '22',
                            fill: true,
                            tension: 0.3,
                            borderWidth: 1.5,
                            pointRadius: 3,
                            pointHoverRadius: 5,
                            pointBackgroundColor: spec.color,
                            pointBorderColor: spec.color,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function(ctx) {
                                        return ' ' + ammInvMoneyCompact(ctx.raw);
                                    },
                                },
                            },
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    font: { size: 9 },
                                    callback: function(v) { return ammInvMoneyCompact(v); },
                                },
                            },
                            x: { ticks: { maxRotation: 45, minRotation: 45, font: { size: 9 } } },
                        },
                    },
                });
            }
            function ammInvOpenModal() {
                const modalEl = document.getElementById('ammInvModal');
                if (modalEl && window.bootstrap && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                } else {
                    $('#ammInvModal').modal('show');
                }
                $('#amm-inv-error').hide().text('');
                if (lastInventoryPies && lastInventoryPies.slices) {
                    ammInvRenderPies(lastInventoryPies);
                } else {
                    $('#amm-inv-loading').show();
                }
                $.getJSON(ammInvPiesUrl)
                    .done(function(res) {
                        $('#amm-inv-loading').hide();
                        if (!res || (res.status && Number(res.status) !== 200)) {
                            $('#amm-inv-error').text((res && res.message) || 'Could not load INV pies.').show();
                            return;
                        }
                        ammInvRenderPies(res);
                    })
                    .fail(function() {
                        $('#amm-inv-loading').hide();
                        if (!lastInventoryPies) {
                            $('#amm-inv-error').text('Could not load INV pies.').show();
                        }
                    });
            }
            $(document).on('click', '#ammInvModal .amm-inv-hist-dot', function(e) {
                e.preventDefault();
                ammInvDrawHist($(this).data('chart'), $(this).data('band'));
            });
            $(document).on('click', '#amm-inv-hist-close', function() {
                $('#amm-inv-hist-wrap').removeClass('is-open');
            });

            // Badge click handler — show overall (all channels) metric trend
            $(document).on('click', '.badge-chart-link', function(e) {
                const metricKey = $(this).data('metric');
                if (!metricKey) return;
                // Forecast badges are links to Forecast Analysis: Ctrl/Cmd+click keeps that.
                if ((e.ctrlKey || e.metaKey) && this.href) return;
                // Prefer data-exact-value (set when badges update) so compact "326K" is not
                // misread as 326. Fall back to parsing the visible text (supports K/M).
                let badgeValue = parseFloat($(this).attr('data-exact-value'));
                if (isNaN(badgeValue)) {
                    badgeValue = parseBadgeDisplayValue($(this).find('span[id]').first().text());
                }
                e.preventDefault();
                showMetricChart('All', metricKey, badgeValue);
            });

            // Per-metric daily bar chart was removed from the badge popup by request.
            // Stub kept so existing call sites in loadAdBreakdownChart() remain valid.
            function loadSalesOrdersItemsBarChart() { /* removed */ }

            function viewsL30Growth(values) {
                if (!values || values.length < 2) return null;
                var latest = Number(values[values.length - 1]);
                var baseIdx = Math.max(0, values.length - 31);
                var base = Number(values[baseIdx]);
                if (!isFinite(latest) || !isFinite(base) || Math.abs(base) < 0.5) return null;
                return {
                    pct: ((latest - base) / Math.abs(base)) * 100,
                    span: values.length - 1 - baseIdx
                };
            }
            function formatViewsGrowth(pct) {
                if (pct == null || !isFinite(pct) || Math.abs(pct) < 0.05) return '0%';
                return (pct > 0 ? '+' : '−') + Math.abs(pct).toFixed(1) + '%';
            }
            function viewsGrowthColor(pct) {
                if (pct == null || !isFinite(pct) || Math.abs(pct) < 0.05) return '#6c757d';
                return pct > 0 ? '#198754' : '#dc3545';
            }
            var l30GrowthMetrics = { total_views: 'views', l30_sales: 'sales', sales: 'sales', today_sales: 'sales' };
            function paintChartViewsGrowth(values) {
                var wrap = document.getElementById('adChartGrowthWrap');
                var el = document.getElementById('adChartGrowth');
                var label = document.getElementById('adChartGrowthLabel');
                if (!wrap || !el) return;
                var metricForGrowth = currentChartMetric || currentMetricKey;
                var kind = l30GrowthMetrics[metricForGrowth];
                if (!kind) {
                    wrap.style.display = 'none';
                    return;
                }
                var growth = viewsL30Growth(values);
                wrap.style.display = '';
                if (!growth) {
                    el.textContent = '—';
                    el.style.color = '#6c757d';
                    el.title = '';
                    if (label) label.textContent = 'L30 %';
                    return;
                }
                if (label) label.textContent = growth.span >= 25 ? 'L30 %' : (growth.span + 'D %');
                el.textContent = formatViewsGrowth(growth.pct);
                el.style.color = viewsGrowthColor(growth.pct);
                el.title = 'Change from ' + growth.span + ' days earlier to the latest ' + kind;
            }

            // Render chart
            function renderAdBreakdownChart(data) {
                const ctx = document.getElementById('adBreakdownChart').getContext('2d');

                // Destroy existing chart
                if (adBreakdownChartInstance) {
                    adBreakdownChartInstance.destroy();
                }

                const labels = data.map(d => d.date);
                const values = data.map(d => d.value);

                // --- Compute stats ---
                const dataMin = Math.min(...values);
                const dataMax = Math.max(...values);
                const sorted = [...values].sort((a, b) => a - b);
                const mid = Math.floor(sorted.length / 2);
                const median = sorted.length % 2 !== 0
                    ? sorted[mid]
                    : (sorted[mid - 1] + sorted[mid]) / 2;

                // Headroom must fit the slanted last-point label. 10% of a tight
                // 7-day CVR range (~0.9pp) is only ~16px — the "5.09%" label
                // was clipped at the top/right of the plot.
                const range = dataMax - dataMin || 1;
                const yPad = Math.max(range * 0.28, Math.abs(dataMax) * 0.08, range * 0.1);
                const yMin = Math.max(0, dataMin - range * 0.12);
                const yMax = dataMax + yPad;

                // --- Format helper (no decimals for spend/sales) ---
                const fmtVal = (v) => {
                    const m = currentChartMetric;
                    if (m === 'spend' || m === 'sales' || m === 'l30_sales' || m === 'y_sales' || m === 'today_sales' || m === 'y_pft' || m === 'y_npft_amt' || m === 'p_sales' || m === 'p_npft_amt' || m === 'l7_sales' || m === 'ad_spend' || m === 'ad_sales' || m === 'pft' || m === 'inv_at_lp' || m === 'inv_at_sp' || m === 'inventory') {
                        return '$' + Math.round(v).toLocaleString('en-US');
                    }
                    // Listing CVR / Ads CVR shift slowly inside a rolling window — show 2 decimals
                    // so adjacent days don't display as the same number (avoids a flat trend).
                    if (m === 'cvr' || m === 'ads_cvr' || m === 'y_cvr' || m === 't_cvr') {
                        return v.toFixed(2) + '%';
                    }
                    if (m === 'acos' || m === 'gprofit' || m === 'groi' || m === 'ads_pct' || m === 'npft' || m === 'p_npft' || m === 'p_groi_pct' || m === 'p_nroi_pct' || m === 'y_npft_pct' || m === 'y_groi_pct' || m === 'y_nroi_pct' || m === 'nroi'
                        || m === 't_npft_pct' || m === 't_groi_pct' || m === 't_nroi_pct' || m === 't_ads_pct' || m === 'p_ads_pct'
                        || m === 'fa_npft' || m === 'fa_nroi') {
                        return v.toFixed(1) + '%';
                    }
                    if (m === 'fa_msl_lp' || m === 'fa_msl_sp' || m === 'fa_ord' || m === 'fa_mip' || m === 'fa_r2s' || m === 'fa_trn') {
                        return '$' + Math.round(v).toLocaleString('en-US');
                    }
                    if (m === 'tat') return v.toFixed(2);
                    if (m === 'rating') return v.toFixed(1);
                    return Math.round(v).toLocaleString('en-US');
                };

                // --- Dot colors: green=UP red=DOWN. ACOS, TAcos %, and TAT invert (lower is better). ---
                const invertedMetrics = ['acos', 'ads_pct', 't_ads_pct', 'p_ads_pct', 'tat'];
                const isInverted = invertedMetrics.includes(currentChartMetric);
                const dotColors = metricChartDotColors(values, isInverted, currentChartMetric);
                if (currentChartMode === 'metric' && currentChartChannel === 'all') {
                    syncAllBadgeDotWithChart(currentChartMetric, values);
                }

                // Labels + High/Low use the same day-over-day color as the dots.
                const labelColors = dotColors.slice();
                const refGray = '#6c757d';
                let maxIdx = 0, minIdx = 0;
                values.forEach((v, i) => {
                    if (v >= values[maxIdx]) maxIdx = i;
                    if (v <= values[minIdx]) minIdx = i;
                });
                const highestEl = document.getElementById('adChartHighest');
                const medianEl = document.getElementById('adChartMedian');
                const lowestEl = document.getElementById('adChartLowest');
                highestEl.textContent = fmtVal(dataMax);
                highestEl.style.color = dotColors[maxIdx] || refGray;
                if (highestEl.previousElementSibling) highestEl.previousElementSibling.style.color = highestEl.style.color;
                medianEl.textContent = fmtVal(median);
                medianEl.style.color = refGray;
                if (medianEl.previousElementSibling) medianEl.previousElementSibling.style.color = refGray;
                lowestEl.textContent = fmtVal(dataMin);
                lowestEl.style.color = dotColors[minIdx] || refGray;
                if (lowestEl.previousElementSibling) lowestEl.previousElementSibling.style.color = lowestEl.style.color;

                paintChartViewsGrowth(values);

                // --- Median line plugin ---
                const medianLinePlugin = {
                    id: 'medianLine',
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

                // afterDraw (not afterDatasetsDraw): Chart.js clips dataset draws
                // to the plot box, which hid the last day's label at the top/right.
                const valueLabelsPlugin = {
                    id: 'valueLabels',
                    afterDraw(chart) {
                        const dataset = chart.data.datasets[0];
                        const meta = chart.getDatasetMeta(0);
                        const ctx = chart.ctx;
                        const lastIdx = meta.data.length - 1;
                        const anchors = [];

                        ctx.save();
                        ctx.font = 'bold 10px Inter, system-ui, sans-serif';
                        ctx.textAlign = 'left';
                        ctx.textBaseline = 'middle';

                        // Labels are rotated by `angle`, so two neighbours are parallel
                        // diagonals. They collide when their perpendicular distance is
                        // small AND they overlap along the text direction — not only
                        // when their start points are close (the old check missed
                        // "$310,243" running into "$317,484").
                        const angle = Math.PI / 5;
                        const sinA = Math.sin(angle);
                        const cosA = Math.cos(angle);
                        const minGap = 13;
                        const minOffset = -44;
                        const maxOffset = -8;

                        meta.data.forEach((point, i) => {
                            const val = dataset.data[i];
                            const textW = ctx.measureText(fmtVal(val)).width;
                            let offsetY = (i % 2 === 0) ? -12 : -26;
                            if (i === lastIdx) {
                                offsetY = (lastIdx % 2 === 0) ? -26 : -12;
                            }

                            const collisionShift = function (oy) {
                                const sx = point.x;
                                const sy = point.y + oy;
                                for (let k = anchors.length - 1; k >= Math.max(0, anchors.length - 3); k--) {
                                    const p = anchors[k];
                                    const dx = sx - p.x;
                                    const dy = sy - p.y;
                                    const along = dx * cosA - dy * sinA;
                                    if (!(along < p.w && along + textW > 0)) continue;
                                    const d = dx * sinA + dy * cosA;
                                    if (Math.abs(d) >= minGap) continue;
                                    const down = (minGap - d) / cosA;
                                    const up = (-minGap - d) / cosA;
                                    const options = [down, up].sort(function (a, b) { return Math.abs(a) - Math.abs(b); });
                                    for (let o = 0; o < options.length; o++) {
                                        const next = oy + options[o];
                                        if (next >= minOffset && next <= maxOffset) return next;
                                    }
                                    return oy;
                                }
                                return oy;
                            };
                            offsetY = collisionShift(offsetY);
                            anchors.push({ x: point.x, y: point.y + offsetY, w: textW });

                            ctx.save();
                            ctx.fillStyle = labelColors[i];
                            ctx.translate(point.x, point.y + offsetY);
                            ctx.rotate(-Math.PI / 5);
                            ctx.fillText(fmtVal(val), 2, 0);
                            ctx.restore();
                        });
                        ctx.restore();
                    }
                };

                // The last point's label is drawn rotated up-and-right, so a fixed
                // right padding clipped long values ("$317,xxx" -> "$317"). Size the
                // padding from the real text width of the widest recent labels.
                ctx.save();
                ctx.font = 'bold 10px Inter, system-ui, sans-serif';
                const tailLabelWidth = Math.max.apply(null, values.slice(-2).map(function(v) {
                    return ctx.measureText(fmtVal(v)).width;
                }));
                ctx.restore();
                const chartRightPad = Math.max(22, Math.ceil(tailLabelWidth * Math.cos(Math.PI / 5)) + 12);

                adBreakdownChartInstance = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: (typeof metricLabels !== 'undefined' && metricLabels[currentChartMetric])
                                ? metricLabels[currentChartMetric]
                                : (currentChartMetric.charAt(0).toUpperCase() + currentChartMetric.slice(1)),
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
                            pointHoverBackgroundColor: dotColors,
                            pointHoverBorderColor: dotColors,
                            pointBorderWidth: 1.5,
                            pointHoverBorderWidth: 1.5
                        }]
                    },
                    plugins: [medianLinePlugin, valueLabelsPlugin],
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        clip: false,
                        layout: {
                            padding: { top: 44, left: 4, right: chartRightPad, bottom: 8 }
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                titleFont: { size: 10 },
                                bodyFont: { size: 10 },
                                padding: 6,
                                callbacks: {
                                    labelColor: function(context) {
                                        const c = dotColors[context.dataIndex] || '#6c757d';
                                        return {
                                            borderColor: c,
                                            backgroundColor: c,
                                            borderWidth: 2,
                                            borderRadius: 8
                                        };
                                    },
                                    label: function(context) {
                                        const idx = context.dataIndex;
                                        let parts = [];
                                        parts.push('Value: ' + fmtVal(context.raw));
                                        if (idx > 0) {
                                            const diff = context.raw - values[idx - 1];
                                            const arrow = diff < 0 ? '▼' : diff > 0 ? '▲' : '▬';
                                            parts.push('vs Yesterday: ' + arrow + ' ' + fmtVal(Math.abs(diff)));
                                        }
                                        return parts;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                min: yMin,
                                max: yMax,
                                ticks: {
                                    font: { size: 9 },
                                    callback: function(value) {
                                        return fmtVal(value);
                                    }
                                }
                            },
                            x: {
                                ticks: {
                                    maxRotation: 60,
                                    minRotation: 60,
                                    autoSkip: false,
                                    maxTicksLimit: Math.max(labels.length, 31),
                                    font: { size: 8 }
                                }
                            }
                        }
                    }
                });
            }

            // Edit channel button handler
            $(document).on('click', '.edit-channel-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();

                console.log('Edit button clicked');

                if (typeof bootstrap === 'undefined') {
                    console.error('Bootstrap is not loaded!');
                    alert('Bootstrap is not loaded. Please refresh the page.');
                    return;
                }

                try {
                    const rowData = JSON.parse($(this).attr('data-channel'));
                    console.log('Row data:', rowData);

                    const channel = rowData['Channel '] || rowData['Channel'] || '';
                    const sheetUrl = rowData['sheet_link'] || '';
                    const type = rowData['type'] || '';
                    const missingLink = rowData['missing_link'] || '';
                    const logo = rowData['logo'] || '';
                    const sellerLink = rowData['seller_link'] || '';
                    // Row exposes the value as 'Update' (see per-channel data builders).
                    // Coerce anything that isn't 'A' or 'S' (incl. 0, null, '0') to '' so
                    // the dropdown lands on its placeholder "Select" entry.
                    const rawUpdate = rowData['Update'];
                    const updateFlag = (rawUpdate === 'A' || rawUpdate === 'S') ? rawUpdate : '';

                    // Populate modal fields
                    $('#editChannelName').val(channel);
                    $('#editChannelAlias').val(rowData['alias'] || '');
                    $('#editChannelPromotions').val(rowData['promotions'] ?? '');
                    $('#editChannelComplianceCount').val(rowData['compliance_count'] ?? '');
                    $('#editChannelBrandAuthorisation').val(rowData['brand_authorisation'] || '');
                    $('#editChannelUrl').val(sheetUrl);
                    $('#editType').val(type);
                    $('#editMissingLink').val(missingLink);
                    $('#editChannelSellerLink').val(sellerLink);
                    $('#editChannelUpdate').val(updateFlag);
                    $('#originalChannel').val(channel);

                    // Reset file input + show current logo (if any)
                    $('#editChannelLogo').val('');
                    $('#editExistingLogo').val(logo);
                    if (logo) {
                        $('#editChannelLogoPreview').html(
                            `<img src="/storage/${logo}" alt="${channel}"/>`);
                    } else {
                        $('#editChannelLogoPreview').html(
                            '<span class="placeholder-text">No logo</span>');
                    }

                    // Show modal using Bootstrap 5 API
                    const modalElement = document.getElementById('editChannelModal');
                    if (!modalElement) {
                        console.error('editChannelModal element not found!');
                        return;
                    }

                    const modal = new bootstrap.Modal(modalElement);
                    modal.show();
                    console.log('Modal opened');
                } catch (error) {
                    console.error('Error opening edit modal:', error);
                    showToast('error', 'Error opening edit form: ' + error.message);
                }
            });

            // Prevent ad spend select from triggering other events
            $(document).on('click', '.ad-spend-select', function(e) {
                e.stopPropagation();
            });

            // Reset select to total when clicking away
            $(document).on('change', '.ad-spend-select', function() {
                const $select = $(this);
                setTimeout(function() {
                    $select.val('total');
                }, 2000); // Reset after 2 seconds
            });

            // Live preview when picking a logo in the Add Channel modal
            $(document).on('change', '#channelLogo', function() {
                const file = this.files && this.files[0];
                const $preview = $('#channelLogoPreview');
                if (!file) {
                    $preview.html('<span class="placeholder-text">No logo</span>');
                    return;
                }
                const reader = new FileReader();
                reader.onload = function(e) {
                    $preview.html(`<img src="${e.target.result}" alt="logo preview"/>`);
                };
                reader.readAsDataURL(file);
            });

            // Live preview when picking a new logo in the Edit Channel modal
            $(document).on('change', '#editChannelLogo', function() {
                const file = this.files && this.files[0];
                const $preview = $('#editChannelLogoPreview');
                if (!file) {
                    // Restore the existing logo (if any) when file picker is cleared
                    const existing = $('#editExistingLogo').val();
                    if (existing) {
                        $preview.html(`<img src="/storage/${existing}" alt="logo"/>`);
                    } else {
                        $preview.html('<span class="placeholder-text">No logo</span>');
                    }
                    return;
                }
                const reader = new FileReader();
                reader.onload = function(e) {
                    $preview.html(`<img src="${e.target.result}" alt="logo preview"/>`);
                };
                reader.readAsDataURL(file);
            });

            // Reset Add modal preview when the modal closes
            $(document).on('hidden.bs.modal', '#addChannelModal', function() {
                $('#channelLogoPreview').html('<span class="placeholder-text">No logo</span>');
            });

            // Save channel form handler
            $(document).on('click', '#saveChannelBtn', function() {
                const channelName = $('#channelName').val().trim();
                const channelUrl = $('#channelUrl').val().trim();
                const type = $('#type').val().trim();
                const sellerLink = $('#channelSellerLink').val().trim();
                const updateFlag = $('#channelUpdate').val();
                const logoFile = $('#channelLogo')[0].files[0];

                if (!channelName || !channelUrl || !type) {
                    showToast('error', 'All fields are required');
                    return;
                }

                const formData = new FormData();
                formData.append('channel', channelName);
                formData.append('alias', $('#channelAlias').val().trim());
                formData.append('promotions', $('#channelPromotions').val().trim());
                formData.append('compliance_count', $('#channelComplianceCount').val().trim());
                formData.append('brand_authorisation', $('#channelBrandAuthorisation').val());
                formData.append('sheet_link', channelUrl);
                formData.append('type', type);
                formData.append('seller_link', sellerLink);
                formData.append('update', updateFlag);
                if (logoFile) {
                    formData.append('logo', logoFile);
                }
                formData.append('_token', $('meta[name="csrf-token"]').attr('content'));

                $.ajax({
                    url: '/channel_master/store',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(res) {
                        if (res.success) {
                            const modal = bootstrap.Modal.getInstance(document.getElementById(
                                'addChannelModal'));
                            if (modal) modal.hide();
                            $('#channelForm')[0].reset();
                            $('#channelSellerLink').val('');
                            $('#channelUpdate').val('');
                            $('#channelLogoPreview').html('<span class="placeholder-text">No logo</span>');
                            table.setData(); // Reload data
                            showToast('success', 'Channel added successfully');
                        } else {
                            showToast('error', res.message || 'Failed to add channel');
                        }
                    },
                    error: function(xhr) {
                        const msg = (xhr.responseJSON && xhr.responseJSON.message)
                            ? xhr.responseJSON.message
                            : 'Error submitting form';
                        showToast('error', msg);
                    }
                });
            });

            // Archive channel from Edit modal
            $(document).on('click', '#archiveChannelBtn', function() {
                const channel = ($('#originalChannel').val() || $('#editChannelName').val() || '').trim();
                if (!channel) {
                    showToast('error', 'Channel name is missing');
                    return;
                }
                if (!confirm(
                        `Are you sure you want to archive channel: ${channel}?\n\nThis will set the channel status to "Inactive" and it will no longer appear in the list.`
                    )) {
                    return;
                }
                const $btn = $(this);
                $btn.prop('disabled', true);
                $.ajax({
                    url: '/channel-archive',
                    method: 'POST',
                    data: {
                        channel: channel,
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        if (response.success) {
                            const modal = bootstrap.Modal.getInstance(document.getElementById(
                                'editChannelModal'));
                            if (modal) modal.hide();
                            showToast('success', 'Channel archived successfully');
                            table.replaceData();
                        } else {
                            showToast('error', response.message || 'Failed to archive channel');
                        }
                    },
                    error: function(xhr) {
                        showToast('error',
                            'Error archiving channel: ' + (xhr.responseJSON?.message ||
                                'Unknown error'));
                    },
                    complete: function() {
                        $btn.prop('disabled', false);
                    }
                });
            });

            // Update channel form handler
            $(document).on('click', '#updateChannelBtn', function() {
                const channel = $('#editChannelName').val().trim();
                const alias = $('#editChannelAlias').val().trim();
                const promotions = $('#editChannelPromotions').val().trim();
                const complianceCount = $('#editChannelComplianceCount').val().trim();
                const brandAuthorisation = $('#editChannelBrandAuthorisation').val();
                const sheetUrl = $('#editChannelUrl').val().trim();
                const type = $('#editType').val();
                const missingLink = $('#editMissingLink').val().trim();
                const originalChannel = $('#originalChannel').val().trim();
                const sellerLink = $('#editChannelSellerLink').val().trim();
                const updateFlag = $('#editChannelUpdate').val();
                const logoFile = $('#editChannelLogo')[0].files[0];

                if (!channel || !sheetUrl) {
                    showToast('error', 'Channel Name and Sheet URL are required');
                    return;
                }

                const formData = new FormData();
                formData.append('channel', channel);
                formData.append('alias', alias);
                formData.append('promotions', promotions);
                formData.append('compliance_count', complianceCount);
                formData.append('brand_authorisation', brandAuthorisation);
                formData.append('sheet_url', sheetUrl);
                formData.append('type', type);
                formData.append('missing_link', missingLink);
                formData.append('original_channel', originalChannel);
                formData.append('seller_link', sellerLink);
                formData.append('update', updateFlag);
                if (logoFile) {
                    formData.append('logo', logoFile);
                }
                formData.append('_token', $('meta[name="csrf-token"]').attr('content'));

                $.ajax({
                    url: '/channel_master/update',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(res) {
                        if (res.success) {
                            const modal = bootstrap.Modal.getInstance(document.getElementById(
                                'editChannelModal'));
                            if (modal) modal.hide();
                            $('#editChannelForm')[0].reset();
                            $('#editChannelSellerLink').val('');
                            $('#editChannelUpdate').val('');
                            $('#editChannelLogoPreview').html('<span class="placeholder-text">No logo</span>');
                            $('#editExistingLogo').val('');
                            table.setData(); // Reload data
                            showToast('success', 'Channel updated successfully');
                        } else {
                            showToast('error', res.message || 'Update failed');
                        }
                    },
                    error: function(xhr) {
                        const msg = (xhr.responseJSON && xhr.responseJSON.message)
                            ? xhr.responseJSON.message
                            : 'Error updating channel';
                        showToast('error', msg);
                    }
                });
            });

            function yMpEsc(s) {
                return String(s == null ? '' : s)
                    .replace(/&/g, '&amp;')
                    .replace(/"/g, '&quot;')
                    .replace(/</g, '&lt;');
            }

            // Same GPFT/NPFT + GROI/NROI bands as the Active Channels table on this page.
            function yMpBandColor(value, kind) {
                const metric = kind === 'gpft' ? 'gprofit' : kind === 'npft' ? 'npft' : kind === 'nroi' ? 'nroi' : 'groi';
                return ammMetricCellColor(metric, value);
            }

            function yMpChartDot(channel, metric, value, color) {
                if (!channel || !metric) return '';
                const hex = color || DEFAULT_DOT_GRAY;
                const cv = (value != null && !isNaN(parseNumber(value))) ? parseNumber(value) : '';
                return `<i class="fas fa-circle ymp-chart-dot" data-channel="${yMpEsc(channel)}" data-metric="${yMpEsc(metric)}" data-value="${cv}" style="color:${hex};font-size:8px;" title="Hover for history graph"></i>`;
            }

            function yMpWrap(channel, metric, value, innerHtml, color) {
                if (!channel || !metric) return innerHtml;
                const cv = (value != null && !isNaN(parseNumber(value))) ? parseNumber(value) : '';
                return `<span class="ymp-cell ymp-hover-cell" data-channel="${yMpEsc(channel)}" data-metric="${yMpEsc(metric)}" data-value="${cv}">${innerHtml}${yMpChartDot(channel, metric, value, color)}</span>`;
            }

            function yMpPct(value, kind, channel, metric) {
                if (value === null || value === undefined || value === '') {
                    return '<span class="text-muted">—</span>';
                }
                const v = parseNumber(value);
                if (v == null || isNaN(v)) return '<span class="text-muted">—</span>';
                const metricKey = metric || (kind === 'gpft' ? 'gprofit' : kind === 'npft' ? 'npft' : kind === 'nroi' ? 'nroi' : 'groi');
                const style = ammMetricCellStyle(metricKey, v) + 'font-weight:600;';
                const color = ammMetricCellColor(metricKey, v);
                return yMpWrap(channel, metric, v, `<span style="${style}">${Math.round(v)}%</span>`, color);
            }

            function yMpMoney(value, channel, metric) {
                const v = parseNumber(value);
                if (!v) return '<span class="text-muted" title="No Yesterday Sales">NYS</span>';
                const color = (typeof getMetricDotColor === 'function' && channel && metric)
                    ? getMetricDotColor(channel, metric)
                    : '#0d6efd';
                return yMpWrap(channel, metric, v, `<span style="font-weight:600;color:#0d6efd;">$${Math.round(v).toLocaleString('en-US')}</span>`, color);
            }

            function yMpInt(value, channel, metric) {
                const v = parseNumber(value);
                if (v == null || isNaN(v) || v === 0) return '<span class="text-muted">—</span>';
                const color = (typeof getMetricDotColor === 'function' && channel && metric)
                    ? getMetricDotColor(channel, metric)
                    : DEFAULT_DOT_GRAY;
                return yMpWrap(channel, metric, v, Math.round(v).toLocaleString('en-US'), color);
            }

            function yMpCvr(row) {
                const serverCvr = row['CVR'];
                if (serverCvr !== undefined && serverCvr !== null && serverCvr !== '') {
                    return parseNumber(serverCvr);
                }
                const views = parseNumber(row['Total Views'] || 0);
                const qty = parseNumber(row['Qty'] || 0);
                return views > 0 ? (qty / views) * 100 : null;
            }

            function renderYesterdayMarketplaceRows(rows) {
                let sumSales = 0, sumPft = 0, sumCogs = 0, sumAd = 0, sumGpftSales = 0;
                let sumViews = 0, sumQty = 0, sumOrders = 0;

                const body = (rows || []).map(function(row) {
                    const name = (row.channel || '').toString().trim() || '—';
                    const ySales = parseNumber(row.sales || 0) || 0;
                    const gpft = row.gpft;
                    const groi = row.groi;
                    const nroi = row.nroi;
                    const npft = row.npft;
                    const views = row.views;
                    const orders = parseNumber(row.orders || 0) || 0;
                    const qty = parseNumber(row.qty || 0) || 0;
                    const cvr = row.cvr;

                    sumSales += ySales;
                    sumPft += parseNumber(row.pft || 0) || 0;
                    sumCogs += parseNumber(row.cogs || 0) || 0;
                    sumAd += parseNumber(row.ad_spend || 0) || 0;
                    sumGpftSales += parseNumber(row.gpft_sales || 0) || 0;
                    sumViews += parseNumber(views || 0) || 0;
                    sumQty += qty;
                    sumOrders += orders;

                    const cvrNum = parseNumber(cvr);
                    const cvrHtml = (cvr == null || cvr === '' || isNaN(cvrNum))
                        ? '<span class="text-muted">—</span>'
                        : yMpWrap(name, 'cvr', cvrNum, `<span style="font-weight:600;">${Math.round(cvrNum)}%</span>`, (typeof getMetricDotColor === 'function' ? getMetricDotColor(name, 'cvr') : DEFAULT_DOT_GRAY));

                    return `<tr>
                        <td class="text-start fw-semibold">${yMpEsc(name)}</td>
                        <td class="text-end">${yMpMoney(ySales, name, 'y_sales')}</td>
                        <td class="text-center">${yMpPct(gpft, 'gpft', name, 'gprofit')}</td>
                        <td class="text-center">${yMpPct(groi, 'groi', name, 'groi')}</td>
                        <td class="text-center">${yMpPct(nroi, 'nroi', name, 'nroi')}</td>
                        <td class="text-center">${yMpPct(npft, 'npft', name, 'npft')}</td>
                        <td class="text-end">${yMpInt(views, name, 'total_views')}</td>
                        <td class="text-center">${cvrHtml}</td>
                        <td class="text-end">${yMpInt(orders, name, 'l30_orders')}</td>
                    </tr>`;
                }).join('');

                const totGpft = sumGpftSales > 0 ? (sumPft / sumGpftSales) * 100 : null;
                const totGroi = sumCogs > 0 ? (sumPft / sumCogs) * 100 : null;
                const totNroi = sumCogs > 0 ? ((sumPft - sumAd) / sumCogs) * 100 : null;
                const totAds = sumGpftSales > 0 ? (sumAd / sumGpftSales) * 100 : 0;
                const totNpft = totGpft != null ? totGpft - totAds : null;
                const totCvr = sumViews > 0 ? (sumQty / sumViews) * 100 : null;
                const totCvrHtml = (totCvr == null || isNaN(totCvr))
                    ? '—'
                    : Math.round(totCvr) + '%';

                const totCvrCell = (totCvr == null || isNaN(totCvr))
                    ? '—'
                    : yMpWrap('All', 'cvr', totCvr, totCvrHtml, (typeof getMetricDotColor === 'function' ? getMetricDotColor('All', 'cvr') : DEFAULT_DOT_GRAY));

                $('#yesterdayMpTableBody').html(body || '<tr><td colspan="9" class="text-center text-muted">No channels</td></tr>');
                $('#yesterdayMpTableFoot').html(`<tr>
                    <td class="text-start">Total</td>
                    <td class="text-end">${yMpMoney(sumSales, 'All', 'y_sales')}</td>
                    <td class="text-center">${yMpPct(totGpft, 'gpft', 'All', 'gprofit')}</td>
                    <td class="text-center">${yMpPct(totGroi, 'groi', 'All', 'groi')}</td>
                    <td class="text-center">${yMpPct(totNroi, 'nroi', 'All', 'nroi')}</td>
                    <td class="text-center">${yMpPct(totNpft, 'npft', 'All', 'npft')}</td>
                    <td class="text-end">${yMpInt(sumViews, 'All', 'total_views')}</td>
                    <td class="text-center">${totCvrCell}</td>
                    <td class="text-end">${yMpInt(sumOrders, 'All', 'l30_orders')}</td>
                </tr>`);
            }

            let ympHoverTimer = null;
            function openYmpHistoryChart(channel, metric, value) {
                if (!channel || !metric || typeof showMetricChart !== 'function') return;
                const chartEl = document.getElementById('adBreakdownChartModal');
                const onShown = function() {
                    if (chartEl) chartEl.style.zIndex = '10050';
                    const backs = document.querySelectorAll('.modal-backdrop');
                    const last = backs[backs.length - 1];
                    if (last) last.style.zIndex = '10040';
                    if (chartEl) chartEl.removeEventListener('shown.bs.modal', onShown);
                };
                if (chartEl) chartEl.addEventListener('shown.bs.modal', onShown);
                const cv = (value !== undefined && value !== null && value !== '' && !isNaN(Number(value)))
                    ? Number(value)
                    : null;
                showMetricChart(channel, metric, cv);
            }

            $(document).on('mouseenter', '#yesterdayMpTable .ymp-hover-cell, #projectedMpTable .ymp-hover-cell', function() {
                const chartEl = document.getElementById('adBreakdownChartModal');
                if (chartEl && chartEl.classList.contains('show')) return;
                const $el = $(this);
                const channel = $el.data('channel');
                const metric = $el.data('metric');
                const value = $el.data('value');
                if (!channel || !metric) return;
                if (ympHoverTimer) clearTimeout(ympHoverTimer);
                ympHoverTimer = setTimeout(function() {
                    openYmpHistoryChart(channel, metric, value);
                }, 500);
            });
            $(document).on('mouseleave', '#yesterdayMpTable .ymp-hover-cell, #projectedMpTable .ymp-hover-cell', function() {
                if (ympHoverTimer) {
                    clearTimeout(ympHoverTimer);
                    ympHoverTimer = null;
                }
            });
            $(document).on('mousedown', '#yesterdayMpTable .ymp-hover-cell, #projectedMpTable .ymp-hover-cell', function() {
                if (ympHoverTimer) {
                    clearTimeout(ympHoverTimer);
                    ympHoverTimer = null;
                }
            });
            $(document).on('click', '#yesterdayMpTable .ymp-hover-cell, #projectedMpTable .ymp-hover-cell', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const $el = $(this);
                openYmpHistoryChart($el.data('channel'), $el.data('metric'), $el.data('value'));
            });

            $('#adBreakdownChartModal').on('hidden.bs.modal', function() {
                const yEl = document.getElementById('yesterdayMpModal');
                const pEl = document.getElementById('projectedMpModal');
                const stillOpen = (yEl && yEl.classList.contains('show')) || (pEl && pEl.classList.contains('show'));
                if (stillOpen) {
                    document.body.classList.add('modal-open');
                    if (!document.querySelector('.modal-backdrop')) {
                        const back = document.createElement('div');
                        back.className = 'modal-backdrop fade show';
                        back.style.zIndex = '9998';
                        document.body.appendChild(back);
                    }
                }
            });

            function applyYViewsBadge(rows) {
                let sum = 0;
                (rows || []).forEach(function(row) {
                    sum += parseNumber(row.views || 0) || 0;
                });
                const val = Math.round(sum);
                const $el = $('#total-y-views');
                if (!$el.length) return;
                let text = String(val);
                if (Math.abs(val) >= 1000000) text = (val / 1000000).toFixed(1).replace(/\.0$/, '') + 'M';
                else if (Math.abs(val) >= 1000) text = Math.round(val / 1000) + 'K';
                $el.text(text);
                $el.closest('.badge').attr('title',
                    'Y Views = sum of yesterday listing views across channels (1-day Pacific, not L30). ' + val.toLocaleString('en-US'));
                yViewsExact = val;
                if (l30DailyViews != null) {
                    pinSummaryDot('y_views', l30DailyViews, val,
                        'Green when yesterday views are above the L30 daily average (L30 Views ÷ 30).');
                    if (typeof colorSummaryBadgeDots === 'function') colorSummaryBadgeDots(channelKeysFromTableData());
                }
            }

            function applyYOrdersToTable() {
                if (!table || typeof table.getRows !== 'function' || !yOrdersByChannel) return;
                table.getRows().forEach(function(tRow) {
                    const key = snapshotChannelKey(tRow.getData()['Channel '] || '');
                    const orders = Object.prototype.hasOwnProperty.call(yOrdersByChannel, key) ? yOrdersByChannel[key] : 0;
                    const cvrRec = yCvrByChannel ? yCvrByChannel[key] : null;
                    tRow.update({
                        'Y Orders': orders,
                        'Y CVR': cvrRec ? cvrRec.cvr : null,
                        'Y Views': cvrRec ? cvrRec.views : null
                    });
                });
                applyL30FallbackDots();
            }

            function applyYOrdersBadge(rows) {
                yOrdersByChannel = {};
                yCvrByChannel = {};
                let sum = 0;
                (rows || []).forEach(function(row) {
                    const orders = parseNumber(row.orders || 0) || 0;
                    const key = snapshotChannelKey(row.channel || '');
                    const views = parseNumber(row.views || 0) || 0;
                    const qty = parseNumber(row.qty || 0) || 0;
                    const rawCvr = row.cvr;
                    const cvr = (rawCvr == null || rawCvr === '' || !isFinite(parseNumber(rawCvr)))
                        ? (views > 0 ? (qty / views) * 100 : null)
                        : parseNumber(rawCvr);
                    if (key) {
                        yOrdersByChannel[key] = orders;
                        // No view source (e.g. Doba) -> keep null so the Y Views cell shows "—", not 0.
                        yCvrByChannel[key] = { cvr: cvr, views: (row.views == null || row.views === '') ? null : views, qty: qty };
                    }
                    sum += orders;
                });
                applyYOrdersToTable();
                const val = Math.round(sum);
                const $el = $('#total-y-orders');
                if (!$el.length) return;
                $el.text(val.toLocaleString('en-US'));
                $el.closest('.badge').attr('title',
                    'Y Orders = sum of yesterday order counts across channels (1-day Pacific, same Orders column as Yesterday by marketplace). ' + val.toLocaleString('en-US'));
                yOrdersExact = val;
                if (l30DailyOrders != null) {
                    pinSummaryDot('y_orders', l30DailyOrders, val,
                        'Green when yesterday orders are above the L30 daily average (L30 Orders ÷ 30).');
                    if (typeof colorSummaryBadgeDots === 'function') colorSummaryBadgeDots(channelKeysFromTableData());
                }
            }

            function openYesterdayMarketplaceModal() {
                const el = document.getElementById('yesterdayMpModal');
                if (typeof bootstrap === 'undefined' || !el) return;
                $('#yesterdayMpTableBody').html('<tr><td colspan="9" class="text-center text-muted py-3">Loading yesterday metrics…</td></tr>');
                $('#yesterdayMpTableFoot').empty();
                bootstrap.Modal.getOrCreateInstance(el).show();

                $.get('/yesterday-marketplace-metrics')
                    .done(function(res) {
                        const rows = (res && res.data && res.data.rows) ? res.data.rows : [];
                        renderYesterdayMarketplaceRows(rows);
                        applyYViewsBadge(rows);
                        applyYOrdersBadge(rows);
                    })
                    .fail(function() {
                        $('#yesterdayMpTableBody').html('<tr><td colspan="9" class="text-center text-danger">Could not load yesterday metrics</td></tr>');
                    });
            }

            $(document).on('click', '#yesterdayMpViewBtn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                openYesterdayMarketplaceModal();
            });

            $.get('/yesterday-marketplace-metrics').done(function(res) {
                const rows = (res && res.data && res.data.rows) ? res.data.rows : [];
                applyYViewsBadge(rows);
                applyYOrdersBadge(rows);
            });

            $.get('/active-channel-npft-nroi').done(function(res) {
                if (!res || !res.success) return;
                if (res.npft_pct != null) $('#amm-fa-npft').text(Math.round(res.npft_pct) + '%');
                if (res.nroi_pct != null) $('#amm-fa-nroi').text(Math.round(res.nroi_pct) + '%');
                if (typeof fitAmmBadgeRows === 'function') requestAnimationFrame(fitAmmBadgeRows);
            });

            function pMpPct1(value, metric, channel) {
                if (value === null || value === undefined || value === '' || !isFinite(parseNumber(value))) {
                    return '<span class="text-muted" title="No L7 Sales">—</span>';
                }
                const v = parseNumber(value);
                const style = ammMetricCellStyle(metric, v) + 'font-weight:600;';
                const color = ammMetricCellColor(metric, v);
                return yMpWrap(channel, metric, v, `<span style="${style}">${v.toFixed(1)}%</span>`, color);
            }

            function pMpSales(value, channel, l30Sales) {
                const v = parseNumber(value);
                if (!v) return '<span class="text-muted" title="No L7 Sales">—</span>';
                const pct = pSalesVsL30Pct(v, l30Sales);
                const color = (typeof getMetricDotColor === 'function')
                    ? getMetricDotColor(channel, 'p_sales')
                    : '#0d6efd';
                const inner = `<span style="font-weight:600;color:#0d6efd;">$${Math.round(v).toLocaleString('en-US')}</span>${formatPSalesVsL30PctHtml(pct)}`;
                return yMpWrap(channel, 'p_sales', v, inner, color);
            }

            function pNroiPctFromRow(row) {
                const pSales = pSalesFromRow(row);
                if (!pSales) return null;
                const pCogs = pCogsFromRow(row);
                if (!(pCogs > 0)) return null;
                return ((pGrossPftFromRow(row) - rowAdSpendFromRow(row)) / pCogs) * 100;
            }

            function renderProjectedMarketplaceRows(rows) {
                let sumPSales = 0, sumL30 = 0, sumPGross = 0, sumPCogs = 0, sumSpend = 0;
                let nroiNet = 0, nroiCogs = 0;

                const sorted = (rows || []).slice().sort(function(a, b) {
                    return pSalesFromRow(b) - pSalesFromRow(a);
                });

                const body = sorted.map(function(row) {
                    const name = (row['Channel '] || row['Channel'] || '').toString().trim() || '—';
                    const pSales = pSalesFromRow(row);
                    const l30 = parseNumber(row['L30 Sales'] || 0);
                    const pGross = pGrossPftFromRow(row);
                    const pCogs = pCogsFromRow(row);
                    const spend = rowAdSpendFromRow(row);

                    sumPSales += pSales;
                    sumL30 += l30;
                    sumPGross += pGross;
                    sumSpend += spend;
                    if (pSales) {
                        sumPCogs += pCogs;
                        nroiNet += pGross - spend;
                        nroiCogs += pCogs;
                    }

                    return `<tr>
                        <td class="text-start fw-semibold">${yMpEsc(name)}</td>
                        <td class="text-end">${pMpSales(pSales, name, l30)}</td>
                        <td class="text-center">${pMpPct1(pGroiPctFromRow(row), 'p_groi_pct', name)}</td>
                        <td class="text-center">${pMpPct1(pNpftPctFromRow(row), 'p_npft', name)}</td>
                        <td class="text-center">${pMpPct1(pNroiPctFromRow(row), 'p_nroi_pct', name)}</td>
                    </tr>`;
                }).join('');

                const totGroi = sumPCogs > 0 ? (sumPGross / sumPCogs) * 100 : null;
                const totNpft = sumPSales > 0 ? ((sumPGross - sumSpend) / sumPSales) * 100 : null;
                const totNroi = nroiCogs > 0 ? (nroiNet / nroiCogs) * 100 : null;

                $('#projectedMpTableBody').html(body || '<tr><td colspan="5" class="text-center text-muted">No channels</td></tr>');
                $('#projectedMpTableFoot').html(`<tr>
                    <td class="text-start">Total</td>
                    <td class="text-end">${pMpSales(sumPSales, 'All', sumL30)}</td>
                    <td class="text-center">${pMpPct1(totGroi, 'p_groi_pct', 'All')}</td>
                    <td class="text-center">${pMpPct1(totNpft, 'p_npft', 'All')}</td>
                    <td class="text-center">${pMpPct1(totNroi, 'p_nroi_pct', 'All')}</td>
                </tr>`);
            }

            function openProjectedMarketplaceModal() {
                const el = document.getElementById('projectedMpModal');
                if (typeof bootstrap === 'undefined' || !el) return;
                const rows = (table && table.getData) ? table.getData() : [];
                if (!rows.length) {
                    $('#projectedMpTableBody').html('<tr><td colspan="5" class="text-center text-muted py-3">Channel data is still loading…</td></tr>');
                    $('#projectedMpTableFoot').empty();
                } else {
                    renderProjectedMarketplaceRows(rows);
                }
                bootstrap.Modal.getOrCreateInstance(el).show();
            }

            $(document).on('click', '#projectedMpViewBtn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                openProjectedMarketplaceModal();
            });

            const ammTaskStoreUrl = @json(route('tasks.store'));
            const ammTaskUsersUrl = @json(route('tasks.usersList'));
            const ammAssignorId = {{ (int) (auth()->id() ?? 0) }};
            let ammTaskUsers = [];

            function openAmmTaskFromCell(cell) {
                const row = (cell && cell.getRow) ? (cell.getRow().getData() || {}) : {};
                const channel = String(row['Channel '] || row['Channel'] || '').trim();
                const groupEl = document.getElementById('amm-task-group');
                const titleEl = document.getElementById('amm-task-title');
                const searchEl = document.getElementById('amm-task-assignee-search');
                const err = document.getElementById('amm-task-error');
                const ok = document.getElementById('amm-task-success');
                if (groupEl) groupEl.value = channel;
                if (titleEl) titleEl.value = '';
                if (searchEl) searchEl.value = '';
                if (err) { err.textContent = ''; err.classList.add('d-none'); }
                if (ok) { ok.textContent = ''; ok.classList.add('d-none'); }
                ammLoadTaskUsers(function() {
                    const modalEl = document.getElementById('ammTaskModal');
                    if (modalEl && window.bootstrap && bootstrap.Modal) {
                        bootstrap.Modal.getOrCreateInstance(modalEl).show();
                    }
                    setTimeout(function() { if (titleEl) titleEl.focus(); }, 150);
                });
            }

            function ammLoadTaskUsers(cb) {
                if (ammTaskUsers.length) { cb(ammTaskUsers); return; }
                fetch(ammTaskUsersUrl, {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(function(r) { return r.json(); })
                    .then(function(users) {
                        ammTaskUsers = Array.isArray(users) ? users : [];
                        const list = document.getElementById('amm-task-assignee-list');
                        if (list) {
                            list.innerHTML = ammTaskUsers.map(function(u) {
                                const name = String(u.name || '').trim();
                                return name ? '<option value="' + name.replace(/"/g, '&quot;') + '"></option>' : '';
                            }).join('');
                        }
                        cb(ammTaskUsers);
                    })
                    .catch(function() { ammTaskUsers = []; cb([]); });
            }

            function ammResolveAssigneeId(label) {
                const q = String(label || '').trim().toLowerCase();
                if (!q) return 0;
                const exact = ammTaskUsers.find(function(u) {
                    return String(u.name || '').trim().toLowerCase() === q;
                });
                if (exact) return parseInt(exact.id, 10) || 0;
                const partial = ammTaskUsers.filter(function(u) {
                    return String(u.name || '').toLowerCase().indexOf(q) !== -1;
                });
                return partial.length === 1 ? (parseInt(partial[0].id, 10) || 0) : 0;
            }

            const ammTaskForm = document.getElementById('amm-task-form');
            if (ammTaskForm) {
                ammTaskForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const err = document.getElementById('amm-task-error');
                    const ok = document.getElementById('amm-task-success');
                    const saveBtn = document.getElementById('amm-task-assign');
                    const group = String((document.getElementById('amm-task-group') || {}).value || '').trim();
                    const title = String((document.getElementById('amm-task-title') || {}).value || '').trim();
                    const assigneeLabel = String((document.getElementById('amm-task-assignee-search') || {}).value || '').trim();
                    const assigneeId = ammResolveAssigneeId(assigneeLabel);
                    if (ok) { ok.textContent = ''; ok.classList.add('d-none'); }
                    if (!group) {
                        if (err) { err.textContent = 'Channel group is missing.'; err.classList.remove('d-none'); }
                        return;
                    }
                    if (!title) {
                        if (err) { err.textContent = 'Task is required.'; err.classList.remove('d-none'); }
                        return;
                    }
                    if (!assigneeId) {
                        if (err) { err.textContent = 'Select a user to assign.'; err.classList.remove('d-none'); }
                        return;
                    }
                    if (!ammAssignorId) {
                        if (err) { err.textContent = 'You must be signed in to assign a task.'; err.classList.remove('d-none'); }
                        return;
                    }
                    if (err) { err.textContent = ''; err.classList.add('d-none'); }
                    const body = new FormData();
                    body.append('title', title);
                    body.append('group', group);
                    body.append('priority', 'normal');
                    body.append('assignor_id', String(ammAssignorId));
                    body.append('assignee_id', String(assigneeId));
                    body.append('etc_minutes', '10');
                    body.append('tid', new Date().toISOString().slice(0, 16));
                    body.append('l1', window.location.href);
                    body.append('quick_create_more', '1');
                    if (saveBtn) { saveBtn.disabled = true; saveBtn.textContent = 'Assigning…'; }
                    fetch(ammTaskStoreUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: body
                    })
                        .then(function(r) { return r.json().then(function(data) { return { ok: r.ok, data: data }; }); })
                        .then(function(res) {
                            if (!res.ok || (res.data && res.data.success === false)) {
                                const msg = (res.data && (res.data.message || (res.data.errors && Object.values(res.data.errors).flat()[0]))) || 'Could not assign task.';
                                throw new Error(msg);
                            }
                            if (ok) {
                                ok.textContent = (res.data && res.data.message) || 'Task assigned.';
                                ok.classList.remove('d-none');
                            }
                            setTimeout(function() {
                                const modalEl = document.getElementById('ammTaskModal');
                                if (modalEl && window.bootstrap && bootstrap.Modal) {
                                    const inst = bootstrap.Modal.getInstance(modalEl);
                                    if (inst) inst.hide();
                                }
                            }, 700);
                        })
                        .catch(function(ex) {
                            if (err) { err.textContent = ex.message || 'Could not assign task.'; err.classList.remove('d-none'); }
                        })
                        .finally(function() {
                            if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Assign'; }
                        });
                });
            }
        });
    </script>
@endsection
