    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .tabulator { border: 1px solid #dee2e6; border-radius: 8px; font-size: 12px; }
        .tabulator .tabulator-header { background: #f8f9fa; border-bottom: 1px solid #dee2e6; }
        .tabulator-col .tabulator-col-sorter { display: none !important; }
        .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            writing-mode: vertical-rl; text-orientation: mixed; transform: rotate(180deg);
            white-space: nowrap; height: 78px; display: flex; align-items: center;
            justify-content: center; font-size: 11px; font-weight: 600;
        }
        .tabulator .tabulator-header .tabulator-col { height: 80px !important; }
        .tabulator .tabulator-row { min-height: 50px; }

        /* ── Parent row ── */
        .tabulator-row.amz-lqs-parent-row,
        .tabulator-row.amz-lqs-parent-row .tabulator-cell {
            background-color: #fff3cd !important;
            font-weight: 700 !important;
            min-height: 48px !important;
        }
        .tabulator-row.amz-lqs-parent-row .tabulator-cell {
            min-height: 48px !important; height: 48px !important;
            padding-top: 8px !important; padding-bottom: 8px !important;
            overflow: visible !important; vertical-align: middle !important;
            color: #664d03;
        }
        .tabulator-row.amz-lqs-parent-row:hover,
        .tabulator-row.amz-lqs-parent-row:hover .tabulator-cell {
            background-color: #ffe69c !important;
        }

        /* ── Modern pagination ── */
        .tabulator .tabulator-footer {
            background: #f8fafc !important; border-top: 1px solid #e2e8f0 !important;
            padding: 10px 16px !important;
        }
        .tabulator .tabulator-footer .tabulator-paginator {
            display: flex; align-items: center; justify-content: center; gap: 4px;
        }
        .tabulator .tabulator-footer .tabulator-paginator .tabulator-page {
            font-size: 14px !important; font-weight: 500 !important;
            min-width: 36px !important; height: 36px !important; line-height: 36px !important;
            padding: 0 10px !important; border-radius: 8px !important;
            border: 1px solid #e2e8f0 !important; background: #fff !important;
            color: #475569 !important; cursor: pointer; transition: all 0.15s ease !important;
            text-align: center !important;
        }
        .tabulator .tabulator-footer .tabulator-paginator .tabulator-page:hover {
            background: #f1f5f9 !important; border-color: #cbd5e1 !important; color: #1e293b !important;
        }
        .tabulator .tabulator-footer .tabulator-paginator .tabulator-page.active {
            background: #ff9900 !important; border-color: #ff9900 !important;
            color: #fff !important; font-weight: 600 !important;
            box-shadow: 0 2px 6px rgba(255,153,0,0.35) !important;
        }
        .tabulator .tabulator-footer .tabulator-paginator .tabulator-page[disabled] {
            opacity: 0.4 !important; cursor: not-allowed !important;
        }
        .tabulator .tabulator-header .tabulator-col.tabulator-sortable .tabulator-col-title {
            padding-right: 0 !important;
        }

        /* ── DIL dropdown ── */
        .amz-lqs-dropdown { position: relative; display: inline-block; }
        .amz-lqs-dropdown .dropdown-menu {
            position: absolute; top: 100%; left: 0; z-index: 1050;
            display: none; min-width: 200px; padding: .5rem 0; margin: 0;
            background: #fff; border: 1px solid #dee2e6; border-radius: .375rem;
            box-shadow: 0 .125rem .25rem rgba(0,0,0,.075);
        }
        .amz-lqs-dropdown.show .dropdown-menu { display: block; }
        .amz-dil-item {
            display: block; width: 100%; padding: .5rem 1rem; clear: both;
            font-weight: 400; color: #212529; text-decoration: none;
            background: transparent; border: 0; cursor: pointer; white-space: nowrap;
        }
        .amz-dil-item:hover { background: #e9ecef; }

        /* ── Status circles ── */
        .amz-sc { display:inline-block; width:12px; height:12px; border-radius:50%; margin-right:6px; border:1px solid #ddd; }
        .amz-sc.def    { background:#6c757d; }
        .amz-sc.red    { background:#dc3545; }
        .amz-sc.yellow { background:#ffc107; }
        .amz-sc.green  { background:#28a745; }
        .amz-sc.pink   { background:#e83e8c; }

        /* Summary badges */
        #amz-summary-stats .amz-badge-row {
            display: flex; flex-wrap: nowrap; align-items: stretch;
            gap: clamp(0.2rem, 0.5vw, 0.45rem); width: 100%;
            overflow-x: auto; -webkit-overflow-scrolling: touch; scrollbar-width: thin;
        }
        #amz-summary-stats .amz-badge-row > .badge {
            flex: 1 1 0; min-width: 0;
            font-size: clamp(0.62rem, 0.35rem + 0.85vw, 1.05rem);
            padding: clamp(0.28rem, 0.4vw, 0.5rem) clamp(0.2rem, 0.5vw, 0.5rem);
            font-weight: bold; box-sizing: border-box;
            display: inline-flex; align-items: center; justify-content: center;
            text-align: center; white-space: nowrap;
        }

        /* Amazon orange accent */
        .btn-amz-orange { background: #ff9900; border-color: #e88e00; color: #fff; }
        .btn-amz-orange:hover { background: #e88e00; color: #fff; }

        /* ── Action dot ── */
        .amz-action-dot {
            display: inline-block; width: 14px; height: 14px; border-radius: 50%;
            cursor: pointer; border: 2px solid rgba(0,0,0,0.15);
            transition: transform 0.15s, box-shadow 0.15s;
        }
        .amz-action-dot:hover { transform: scale(1.3); box-shadow: 0 0 6px rgba(0,0,0,0.25); }
        .amz-action-dot.no-action  { background: #dc3545; }
        .amz-action-dot.has-action { background: #28a745; }

        /* ── History cell ── */
        .amz-history-cell {
            font-size: 10px; line-height: 1.35; max-width: 160px;
            overflow: hidden; cursor: pointer;
        }
        .amz-history-cell .amz-hist-user { font-weight: 700; color: #495057; }
        .amz-history-cell .amz-hist-date { color: #6c757d; }
        .amz-history-cell .amz-hist-text { color: #212529; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 150px; display: block; }
        .amz-history-cell .amz-hist-more { color: #ff9900; font-weight: 600; font-size: 9px; }

        /* ── History modal entries ── */
        .amz-hist-entry {
            border-left: 3px solid #ff9900; padding: 6px 10px;
            margin-bottom: 8px; background: #fffdf8; border-radius: 0 4px 4px 0;
        }
        .amz-hist-entry:last-child { margin-bottom: 0; }
        .amz-hist-entry .amz-he-meta { font-size: 10px; color: #6c757d; margin-bottom: 2px; }
        .amz-hist-entry .amz-he-text { font-size: 12px; color: #212529; font-weight: 500; }

        /* Metric history modals — full width (theme uses --tz-modal-width / --tz-modal-margin) */
        #amzCvrChartModal.modal,
        #amzBadgeChartModal.modal {
            --tz-modal-width: 100%;
            --tz-modal-margin: 0.5rem 0;
            padding-left: 0 !important;
            padding-right: 0 !important;
        }
        #amzCvrChartModal .modal-dialog,
        #amzBadgeChartModal .modal-dialog {
            width: 100% !important;
            max-width: none !important;
            margin: 0.5rem 0 0 0 !important;
        }
        #amzCvrChartModal .modal-content,
        #amzBadgeChartModal .modal-content {
            border-radius: 0;
            width: 100%;
            max-width: 100%;
        }

        #lqsAuditModal,
        #lqsAuditModal textarea,
        #lqsAuditModal .btn,
        #lqsAuditModal .form-label,
        #lqsAuditErr {
            letter-spacing: normal !important;
            word-spacing: normal !important;
        }

        .lqs-audit-btn {
            display: inline-flex; align-items: center; justify-content: center;
            width: 26px; height: 26px; border: none; background: transparent;
            color: #0d6efd; cursor: pointer; border-radius: 50%;
        }
        .lqs-audit-btn:hover { background: rgba(13,110,253,0.12); color: #0a58ca; }
        .lqs-findings-cell {
            font-size: 10px; line-height: 1.35; max-width: 240px;
            cursor: pointer; text-align: left;
        }
        .lqs-findings-cell .lqs-findings-label { font-weight: 700; color: #495057; }
        .lqs-findings-cell .lqs-findings-text,
        .lqs-findings-cell .lqs-suggest-text {
            color: #212529; display: block; white-space: nowrap;
            overflow: hidden; text-overflow: ellipsis; max-width: 230px;
        }
        .lqs-findings-cell .lqs-suggest-label { font-weight: 700; color: #0d6efd; }

        .lqs-yoast-dash {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .lqs-yoast-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 14px 16px;
        }
        .lqs-yoast-card-head h6 {
            margin: 0;
            font-size: 15px;
            font-weight: 700;
            color: #111827;
        }
        .lqs-yoast-note {
            display: block;
            font-size: 11px;
            color: #6b7280;
            margin: 2px 0 10px;
        }
        .lqs-yoast-rows { display: flex; flex-direction: column; gap: 6px; }
        .lqs-yoast-row {
            display: flex;
            align-items: center;
            gap: 8px;
            width: 100%;
            border: 0;
            background: transparent;
            padding: 4px 0;
            font-size: 13px;
            color: #111827;
            text-align: left;
        }
        .lqs-yoast-row:hover { color: #0d6efd; }
        .lqs-yoast-row.active { font-weight: 700; }
        .lqs-yoast-row strong { margin-left: auto; }
        .lqs-yoast-dot {
            width: 10px; height: 10px; border-radius: 50%;
            display: inline-block; flex: 0 0 10px;
        }
        .lqs-yoast-dot.good { background: #7ad03a; }
        .lqs-yoast-dot.ok { background: #ee7c1b; }
        .lqs-yoast-dot.bad { background: #dc3232; }
        .lqs-yoast-dot.na { background: #888; }
        .lqs-yoast-score {
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 42px; padding: 2px 8px; border-radius: 999px;
            font-weight: 700; font-size: 12px; color: #fff; cursor: pointer;
        }
        .lqs-yoast-score.good { background: #7ad03a; }
        .lqs-yoast-score.ok { background: #ee7c1b; }
        .lqs-yoast-score.bad { background: #dc3232; }
        .lqs-yoast-score.na { background: #9ca3af; }
        @media (max-width: 768px) {
            .lqs-yoast-dash { grid-template-columns: 1fr; }
        }
    </style>
