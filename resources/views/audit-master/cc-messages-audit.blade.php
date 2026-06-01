@extends('layouts.vertical', ['title' => 'CC Messages Audit', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .audit-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
            margin-bottom: 12px;
        }

        .audit-toolbar .form-control,
        .audit-toolbar .form-select {
            max-width: 240px;
        }

        .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            white-space: nowrap;
            font-size: 12px;
            font-weight: 600;
        }

        .tabulator .tabulator-header .tabulator-col {
            height: 40px !important;
        }

        .tabulator-cell {
            padding: 5px 8px !important;
            white-space: normal !important;
        }

        .channel-pill {
            display: inline-block;
            padding: 2px 8px;
            background: #e7f1ff;
            color: #0d6efd;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        /* Channel logo thumbnail (Img column) - sourced from channel_master.logo */
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

        /* Audit button (Audit column) */
        .audit-btn {
            background: transparent;
            border: 0;
            padding: 0;
            cursor: pointer;
            line-height: 0;
            transition: transform 0.15s ease, filter 0.15s ease;
        }

        .audit-btn img {
            width: 32px;
            height: 32px;
            object-fit: contain;
            filter: drop-shadow(0 1px 2px rgba(13, 71, 161, 0.25));
        }

        .audit-btn:hover img {
            transform: scale(1.08);
            filter: drop-shadow(0 2px 4px rgba(13, 71, 161, 0.45));
        }

        .audit-btn:focus {
            outline: none;
        }

        .audit-btn:focus-visible img {
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.35);
            border-radius: 50%;
        }

        .audit-btn-red img    { filter: drop-shadow(0 1px 2px rgba(183, 28, 28, 0.30)); }
        .audit-btn-red:hover img    { filter: drop-shadow(0 2px 4px rgba(183, 28, 28, 0.55)); }
        .audit-btn-green img  { filter: drop-shadow(0 1px 2px rgba(27, 94, 32, 0.30)); }
        .audit-btn-green:hover img  { filter: drop-shadow(0 2px 4px rgba(27, 94, 32, 0.55)); }
        .audit-btn-blue img   { filter: drop-shadow(0 1px 2px rgba(13, 71, 161, 0.25)); }
        .audit-btn-blue:hover img   { filter: drop-shadow(0 2px 4px rgba(13, 71, 161, 0.45)); }

        /* Audit modal checklist styling */
        #auditChecklistModal .modal-header {
            background: linear-gradient(135deg, #1976d2 0%, #0d47a1 100%);
            color: #fff;
        }

        #auditChecklistModal .audit-channel-pill {
            background: rgba(255, 255, 255, 0.2);
            color: #fff;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 8px;
        }

        #auditChecklistList {
            list-style: none;
            padding: 0;
            margin: 0;
            max-height: 50vh;
            overflow-y: auto;
        }

        #auditChecklistList .audit-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 10px 12px;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            margin-bottom: 8px;
            background: #fff;
            transition: border-color 0.15s ease, background 0.15s ease;
        }

        #auditChecklistList .audit-item:hover {
            border-color: #90caf9;
            background: #f5faff;
        }

        #auditChecklistList .audit-item input[type="checkbox"] {
            margin-top: 4px;
            width: 18px;
            height: 18px;
            accent-color: #1976d2;
            flex-shrink: 0;
        }

        #auditChecklistList .audit-item .audit-item-body {
            flex: 1;
            min-width: 0;
        }

        #auditChecklistList .audit-item .audit-item-label {
            font-weight: 600;
            color: #212529;
            line-height: 1.25;
            cursor: pointer;
        }

        #auditChecklistList .audit-item .audit-item-desc {
            color: #6c757d;
            font-size: 12px;
            margin-top: 2px;
        }

        #auditChecklistList .audit-item .audit-item-actions {
            display: flex;
            gap: 4px;
            opacity: 0.6;
        }

        #auditChecklistList .audit-item:hover .audit-item-actions {
            opacity: 1;
        }

        #auditChecklistList .audit-item.is-checked {
            background: #e8f5e9;
            border-color: #66bb6a;
        }

        .audit-progress {
            font-size: 12px;
            color: #6c757d;
        }

        .audit-progress strong {
            color: #1976d2;
        }

        /* ---- Audit modal v2 (dynamic) ---- */
        .audit-tabs .nav-link {
            color: #495057;
            font-weight: 600;
        }

        .audit-tabs .nav-link.active {
            color: #0d47a1;
            border-bottom: 2px solid #0d47a1;
        }

        .audit-score-card {
            display: flex;
            align-items: stretch;
            gap: 16px;
            padding: 12px 14px;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
        }

        .audit-score-grade {
            min-width: 110px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 8px 12px;
            background: #6c757d;
            color: #fff;
            border-radius: 8px;
            transition: background 0.2s ease;
        }

        .audit-score-grade .grade-letter {
            font-size: 32px;
            font-weight: 800;
            line-height: 1;
        }

        .audit-score-grade .grade-desc {
            font-size: 11px;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 2px;
        }

        .audit-score-meta .meta-label {
            font-size: 11px;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .audit-score-meta .meta-value {
            font-size: 16px;
            font-weight: 700;
            color: #212529;
        }

        .audit-section-card {
            border: 1px solid #e9ecef;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 14px;
            background: #fff;
        }

        .audit-section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 14px;
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            border-bottom: 1px solid #e9ecef;
        }

        .audit-section-header.compliance {
            background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);
        }

        .audit-section-header h6 {
            margin: 0;
            font-weight: 700;
            color: #0d47a1;
            font-size: 14px;
        }

        .audit-section-header.compliance h6 { color: #e65100; }

        .audit-section-header .section-meta {
            font-size: 12px;
            font-weight: 600;
            color: #495057;
        }

        .audit-param-row {
            display: grid;
            grid-template-columns: 1fr 130px 110px 1fr;
            gap: 10px;
            padding: 10px 14px;
            border-top: 1px solid #f1f3f5;
            align-items: start;
        }

        .audit-param-row:first-child { border-top: 0; }

        .audit-param-row.is-failed {
            background: #fff5f5;
        }

        .audit-param-row .param-label {
            font-weight: 600;
            color: #212529;
            font-size: 13px;
            line-height: 1.3;
        }

        .audit-param-row .param-desc {
            color: #6c757d;
            font-size: 11.5px;
            margin-top: 2px;
        }

        .audit-param-row .critical-tag {
            display: inline-block;
            padding: 1px 6px;
            background: #dc3545;
            color: #fff;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 700;
            margin-left: 6px;
            vertical-align: middle;
        }

        .audit-param-row .score-input-wrap {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .audit-param-row .score-input {
            width: 64px;
        }

        .audit-param-row .max-score {
            color: #6c757d;
            font-size: 12px;
            font-weight: 600;
        }

        .audit-param-row .critical-fail-toggle {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 11.5px;
            color: #dc3545;
            font-weight: 600;
        }

        .audit-param-row .critical-fail-toggle input {
            accent-color: #dc3545;
        }

        .audit-param-row .remarks-input {
            font-size: 12px;
        }

        .audit-history-table th {
            font-size: 12px;
            background: #f1f3f5;
        }

        .audit-history-table td {
            font-size: 12.5px;
            vertical-align: middle;
        }

        .audit-grade-pill {
            display: inline-block;
            padding: 1px 10px;
            border-radius: 10px;
            color: #fff;
            font-weight: 700;
            font-size: 12px;
        }

        /* Remarks viewer modal */
        .view-remarks-btn { line-height: 1; }
        .view-remarks-btn .badge { font-size: 10px; }

        #remarksViewerList .remark-card {
            border: 1px solid #e9ecef;
            border-left-width: 4px;
            border-radius: 6px;
            padding: 10px 12px;
            margin-bottom: 8px;
            background: #fff;
        }
        #remarksViewerList .remark-card.cat-core_qa { border-left-color: #1976d2; }
        #remarksViewerList .remark-card.cat-channel_compliance { border-left-color: #f57c00; }
        #remarksViewerList .remark-card.cat-note { border-left-color: #6c757d; background: #f8f9fa; }
        #remarksViewerList .remark-card.is-critical { border-left-color: #dc3545; background: #fff5f5; }

        #remarksViewerList .remark-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            margin-bottom: 4px;
        }
        #remarksViewerList .remark-label {
            font-weight: 600;
            color: #212529;
            font-size: 13px;
        }
        #remarksViewerList .remark-score {
            font-size: 11.5px;
            color: #495057;
            font-weight: 600;
            white-space: nowrap;
        }
        #remarksViewerList .remark-text {
            color: #212529;
            font-size: 12.5px;
            white-space: pre-wrap;
            line-height: 1.4;
        }
        #remarksViewerList .remark-cat-tag {
            display: inline-block;
            padding: 1px 8px;
            border-radius: 10px;
            font-size: 10.5px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-left: 6px;
        }
        #remarksViewerList .remark-cat-tag.core_qa { background: #e3f2fd; color: #0d47a1; }
        #remarksViewerList .remark-cat-tag.channel_compliance { background: #fff3e0; color: #e65100; }
        #remarksViewerList .remark-cat-tag.note { background: #ede7f6; color: #4527a0; }
        #remarksViewerList .remark-crit-tag {
            display: inline-block;
            padding: 1px 6px;
            background: #dc3545;
            color: #fff;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 700;
            margin-left: 6px;
        }

        .audit-critical-chip {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            background: #fff;
            border: 1px solid #f5c6cb;
            color: #842029;
            font-size: 11.5px;
            cursor: pointer;
            user-select: none;
            transition: background 0.15s ease;
        }

        .audit-critical-chip.is-on {
            background: #dc3545;
            border-color: #dc3545;
            color: #fff;
        }

        @media (max-width: 768px) {
            .audit-param-row {
                grid-template-columns: 1fr;
            }
        }

        /* ---- Admin controls inside the audit modal (only when isAuditAdmin) ---- */
        .audit-section-header .admin-add-btn {
            background: #ffffff;
            color: #0d47a1;
            border: 1px solid #90caf9;
            padding: 2px 8px;
            border-radius: 14px;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.15s ease;
        }
        .audit-section-header .admin-add-btn:hover {
            background: #e3f2fd;
        }
        .audit-section-header.compliance .admin-add-btn {
            color: #e65100;
            border-color: #ffb74d;
        }
        .audit-section-header.compliance .admin-add-btn:hover {
            background: #fff8e1;
        }

        .audit-param-row .param-admin-actions {
            display: flex;
            gap: 4px;
            margin-top: 4px;
            opacity: 0;
            transition: opacity 0.15s ease;
        }
        .audit-param-row:hover .param-admin-actions { opacity: 1; }

        .audit-param-row .param-admin-actions button {
            background: #fff;
            border: 1px solid #dee2e6;
            color: #495057;
            padding: 0 6px;
            border-radius: 4px;
            font-size: 11px;
            line-height: 18px;
            cursor: pointer;
        }
        .audit-param-row .param-admin-actions button:hover { background: #f1f3f5; }
        .audit-param-row .param-admin-actions .archive-param-btn:hover {
            background: #f8d7da;
            color: #842029;
            border-color: #f5c2c7;
        }
        .audit-param-row .param-admin-actions .edit-param-btn:hover {
            color: #0d47a1;
            border-color: #90caf9;
        }

        .admin-only-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            background: #ede7f6;
            color: #4527a0;
            border: 1px solid #d1c4e9;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 8px;
        }

        /* Param editor (nested) modal — must sit above the audit modal */
        #paramEditorModal { z-index: 1080; }
        .modal-backdrop.show + .modal-backdrop.show { z-index: 1075; }

        /* ============== Agent-wise KPI panel ============== */
        .kpi-panel-card .card-body { padding: 14px 16px; }

        .kpi-panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }
        .kpi-panel-header h5 {
            margin: 0;
            font-weight: 700;
            color: #0d47a1;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .kpi-summary-row {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
            margin-bottom: 12px;
        }
        @media (max-width: 768px) {
            .kpi-summary-row { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        .kpi-tile {
            border-radius: 8px;
            padding: 10px 12px;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 10px;
            min-height: 64px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.06);
        }
        .kpi-tile .kpi-icon {
            font-size: 22px;
            opacity: 0.9;
            flex-shrink: 0;
        }
        .kpi-tile .kpi-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            opacity: 0.85;
        }
        .kpi-tile .kpi-value {
            font-size: 22px;
            font-weight: 700;
            line-height: 1.1;
        }
        .kpi-tile.t-agents     { background: linear-gradient(135deg, #1976d2 0%, #0d47a1 100%); }
        .kpi-tile.t-audits     { background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%); }
        .kpi-tile.t-avg        { background: linear-gradient(135deg, #f9a825 0%, #ef6c00 100%); }
        .kpi-tile.t-critical   { background: linear-gradient(135deg, #e53935 0%, #b71c1c 100%); }

        .kpi-body {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }
        @media (max-width: 991px) {
            .kpi-body { grid-template-columns: 1fr; }
        }

        #agentScoreChart {
            width: 100%;
            min-height: 280px;
        }

        .kpi-agent-table-wrap {
            max-height: 280px;
            overflow-y: auto;
            border: 1px solid #e9ecef;
            border-radius: 6px;
        }
        .kpi-agent-table {
            margin-bottom: 0;
            font-size: 12.5px;
        }
        .kpi-agent-table thead th {
            position: sticky;
            top: 0;
            background: #f1f3f5;
            font-size: 11.5px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #495057;
            z-index: 1;
        }
        .kpi-agent-table tbody tr {
            cursor: pointer;
        }
        .kpi-agent-table tbody tr:hover {
            background: #f5faff;
        }
        .kpi-agent-table tbody tr.is-active {
            background: #e7f1ff;
        }
        .kpi-agent-table .agent-grade {
            display: inline-block;
            padding: 1px 8px;
            border-radius: 10px;
            color: #fff;
            font-weight: 700;
            font-size: 11px;
        }

        .kpi-remarks-card {
            margin-top: 12px;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            background: #fff;
        }
        .kpi-remarks-card .kpi-remarks-header {
            padding: 8px 12px;
            border-bottom: 1px solid #e9ecef;
            background: #f8f9fa;
            font-weight: 600;
            color: #212529;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .kpi-remarks-list {
            max-height: 220px;
            overflow-y: auto;
            padding: 6px 12px;
        }
        .kpi-remark-item {
            padding: 8px 0;
            border-bottom: 1px dashed #f1f3f5;
            font-size: 12.5px;
        }
        .kpi-remark-item:last-child { border-bottom: 0; }
        .kpi-remark-item .meta {
            color: #6c757d;
            font-size: 11px;
            margin-bottom: 2px;
        }
        .kpi-remark-item .text {
            color: #212529;
            white-space: pre-wrap;
        }
        .kpi-remark-item .critical-flag {
            color: #b71c1c;
            font-weight: 600;
            font-size: 11px;
            margin-left: 6px;
        }

        /* ============== LAST AUDITED (auditor + timestamp) ============== */
        .la-cell {
            display: flex;
            flex-direction: column;
            gap: 2px;
            line-height: 1.25;
            text-align: left;
        }
        .la-cell .la-auditor {
            font-size: 12.5px;
            font-weight: 600;
            color: #0d47a1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .la-cell .la-auditor.la-auditor-missing {
            color: #adb5bd;
            font-weight: 500;
            font-style: italic;
        }
        .la-cell .la-time {
            font-size: 11.5px;
            color: #6c757d;
            white-space: nowrap;
        }

        /* ============== INLINE REMARKS (Remarks column) ==============
           Rendered directly inside the Tabulator cell so auditors can read
           the last audit feedback without opening a modal. Designed to
           stay compact yet wrap cleanly inside a 360-400px column. */
        .cell-remarks {
            display: flex;
            flex-direction: column;
            gap: 6px;
            padding: 4px 0;
            text-align: left;
        }
        .cell-remarks .cr-empty {
            color: #adb5bd;
            font-style: italic;
            font-size: 12px;
        }
        .cell-remarks .cr-item {
            display: block;
            padding: 6px 8px;
            border-left: 3px solid #1976d2;
            background: #f5faff;
            border-radius: 0 4px 4px 0;
            font-size: 12px;
            line-height: 1.4;
        }
        .cell-remarks .cr-item.cr-channel { border-left-color: #f57c00; background: #fff8ef; }
        .cell-remarks .cr-item.cr-note    { border-left-color: #6c757d; background: #f8f9fa; }
        .cell-remarks .cr-item.cr-crit    { border-left-color: #dc3545; background: #fff5f5; }
        .cell-remarks .cr-label {
            display: inline-block;
            font-weight: 700;
            color: #0d47a1;
            margin-right: 4px;
        }
        .cell-remarks .cr-item.cr-channel .cr-label { color: #e65100; }
        .cell-remarks .cr-item.cr-note    .cr-label { color: #4527a0; }
        .cell-remarks .cr-item.cr-crit    .cr-label { color: #b71c1c; }
        .cell-remarks .cr-score {
            color: #6c757d;
            font-size: 11px;
            font-weight: 500;
            margin-left: 4px;
        }
        .cell-remarks .cr-crit-tag {
            display: inline-block;
            background: #dc3545;
            color: #fff;
            font-size: 9.5px;
            font-weight: 700;
            padding: 1px 5px;
            border-radius: 8px;
            margin-left: 4px;
            letter-spacing: 0.3px;
            text-transform: uppercase;
            vertical-align: middle;
        }
        .cell-remarks .cr-text {
            display: block;
            color: #212529;
            white-space: pre-wrap;
            word-break: break-word;
        }

        /* ================== SOP BUTTON + MODAL ================== */
        .sop-btn {
            background: transparent;
            border: 1px solid transparent;
            border-radius: 8px;
            padding: 2px 4px;
            cursor: pointer;
            line-height: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
        }
        .sop-btn img {
            width: 38px;
            height: 38px;
            object-fit: contain;
            display: block;
        }
        .sop-btn:hover {
            transform: scale(1.08);
            border-color: #1E90FF;
            box-shadow: 0 0 0 3px rgba(30, 144, 255, 0.18);
        }
        .sop-btn:focus { outline: none; }
        .sop-btn:focus-visible {
            border-color: #1E90FF;
            box-shadow: 0 0 0 3px rgba(30, 144, 255, 0.35);
        }
        .sop-btn[data-can-edit="1"] { position: relative; }
        .sop-btn[data-can-edit="1"]::after {
            content: "edit";
            position: absolute;
            top: -6px;
            right: -6px;
            background: #E63946;
            color: #fff;
            font-size: 9px;
            font-weight: 700;
            padding: 1px 5px;
            border-radius: 8px;
            line-height: 1.2;
            letter-spacing: 0.3px;
            text-transform: uppercase;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.25);
        }

        #sopModal .modal-header {
            background: linear-gradient(135deg, #1976d2 0%, #0d47a1 100%);
            color: #fff;
        }
        #sopModal .modal-header .btn-close { filter: invert(1); }
        #sopModal .sop-title-img {
            width: 36px;
            height: 36px;
            object-fit: contain;
            margin-right: 8px;
            background: #fff;
            border-radius: 6px;
            padding: 2px;
        }
        #sopModal .sop-badge {
            background: rgba(255, 255, 255, 0.15);
            color: #fff;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 8px;
        }
        #sopModal .sop-admin-badge {
            background: #E63946;
            color: #fff;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 700;
            margin-left: 8px;
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }
        #sopModal .sop-mode-hint {
            font-size: 12px;
            color: #6c757d;
            padding: 6px 12px;
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
        }
        #sopModal .sop-viewer {
            min-height: 55vh;
            max-height: 70vh;
            overflow-y: auto;
            padding: 20px 24px;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            background: #fff;
            font-size: 14.5px;
            line-height: 1.6;
            color: #212529;
            word-wrap: break-word;
        }
        #sopModal .sop-viewer:empty::before {
            content: "No SOP content has been added yet. " attr(data-empty-hint);
            color: #adb5bd;
            font-style: italic;
        }

        /* Rendered Markdown / HTML typography ------------------------- */
        #sopModal .sop-viewer h1,
        #sopModal .sop-viewer h2,
        #sopModal .sop-viewer h3,
        #sopModal .sop-viewer h4,
        #sopModal .sop-viewer h5,
        #sopModal .sop-viewer h6 {
            margin: 18px 0 10px;
            font-weight: 700;
            line-height: 1.3;
            color: #0d47a1;
        }
        #sopModal .sop-viewer h1 { font-size: 24px; border-bottom: 2px solid #e3f2fd; padding-bottom: 6px; }
        #sopModal .sop-viewer h2 { font-size: 20px; border-bottom: 1px solid #e9ecef; padding-bottom: 4px; }
        #sopModal .sop-viewer h3 { font-size: 17px; }
        #sopModal .sop-viewer h4 { font-size: 15px; color: #1565c0; }
        #sopModal .sop-viewer h5,
        #sopModal .sop-viewer h6 { font-size: 14px; color: #1976d2; }
        #sopModal .sop-viewer h1:first-child,
        #sopModal .sop-viewer h2:first-child,
        #sopModal .sop-viewer h3:first-child { margin-top: 0; }

        #sopModal .sop-viewer p { margin: 0 0 10px; }
        #sopModal .sop-viewer ul,
        #sopModal .sop-viewer ol {
            margin: 6px 0 12px;
            padding-left: 26px;
        }
        #sopModal .sop-viewer li { margin: 2px 0; }
        #sopModal .sop-viewer li > p { margin: 0; }

        #sopModal .sop-viewer blockquote {
            margin: 10px 0;
            padding: 8px 14px;
            border-left: 4px solid #1976d2;
            background: #f5faff;
            color: #495057;
            border-radius: 0 4px 4px 0;
        }

        #sopModal .sop-viewer hr {
            border: 0;
            border-top: 1px dashed #ced4da;
            margin: 18px 0;
        }

        #sopModal .sop-viewer a {
            color: #1565c0;
            text-decoration: underline;
            word-break: break-word;
        }
        #sopModal .sop-viewer a:hover { color: #0d47a1; }

        /* Images — small, user-friendly thumbnails by default.
           Multiple inline images flow side-by-side and wrap. Click any
           thumbnail to open a full-size lightbox. */
        #sopModal .sop-viewer img {
            max-width: 220px;
            max-height: 160px;
            width: auto;
            height: auto;
            object-fit: contain;
            display: inline-block;
            vertical-align: middle;
            margin: 6px 8px 6px 0;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            background: #fff;
            cursor: zoom-in;
            transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
        }
        #sopModal .sop-viewer img:hover {
            transform: scale(1.04);
            border-color: #1976d2;
            box-shadow: 0 4px 10px rgba(13, 71, 161, 0.18);
        }
        /* Images that sit alone on a line (Markdown image-only paragraph)
           get a tiny bit of extra breathing room, but still stay small. */
        #sopModal .sop-viewer p > img:only-child {
            display: inline-block;
            margin: 8px 8px;
        }
        /* Wrap groups of consecutive images into a tidy gallery row. */
        #sopModal .sop-viewer p:has(> img) {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: flex-start;
            margin: 6px 0 12px;
        }

        /* Auto-built gallery — groups of consecutive image-only paragraphs
           get collapsed into a single grid so they fill the full viewer
           width instead of stacking down the left edge. */
        #sopModal .sop-viewer .sop-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 10px;
            margin: 10px 0 16px;
            align-items: start;
        }
        #sopModal .sop-viewer .sop-gallery img {
            max-width: 100%;
            max-height: 180px;
            width: 100%;
            height: 100%;
            margin: 0;
            object-fit: cover;
        }

        /* ---- Full-size image lightbox (opens on thumbnail click) ---- */
        .sop-lightbox-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.85);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            padding: 24px;
            cursor: zoom-out;
            animation: sopLbFade 0.12s ease-out;
        }
        @keyframes sopLbFade { from { opacity: 0; } to { opacity: 1; } }
        .sop-lightbox-backdrop img {
            max-width: 95vw;
            max-height: 90vh;
            border-radius: 8px;
            box-shadow: 0 6px 30px rgba(0, 0, 0, 0.6);
            background: #fff;
        }
        .sop-lightbox-close {
            position: absolute;
            top: 14px;
            right: 18px;
            background: rgba(255, 255, 255, 0.15);
            color: #fff;
            border: 0;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            font-size: 22px;
            line-height: 1;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .sop-lightbox-close:hover { background: rgba(255, 255, 255, 0.25); }

        /* Tables (GFM tables from Markdown). */
        #sopModal .sop-viewer table {
            width: 100%;
            border-collapse: collapse;
            margin: 12px 0;
            font-size: 13.5px;
        }
        #sopModal .sop-viewer table, #sopModal .sop-viewer th, #sopModal .sop-viewer td {
            border: 1px solid #dee2e6;
        }
        #sopModal .sop-viewer th {
            background: #f1f3f5;
            color: #212529;
            font-weight: 700;
        }
        #sopModal .sop-viewer th, #sopModal .sop-viewer td {
            padding: 8px 10px;
            vertical-align: top;
        }
        #sopModal .sop-viewer tr:nth-child(even) td { background: #fbfcfe; }

        /* Inline & block code. */
        #sopModal .sop-viewer code {
            background: #f1f3f5;
            color: #c7254e;
            padding: 1px 5px;
            border-radius: 3px;
            font-size: 0.9em;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace;
        }
        #sopModal .sop-viewer pre {
            background: #f8f9fa;
            color: #212529;
            padding: 12px 14px;
            border-radius: 6px;
            overflow-x: auto;
            border: 1px solid #e9ecef;
        }
        #sopModal .sop-viewer pre code {
            background: transparent;
            color: inherit;
            padding: 0;
        }

        /* GFM task lists. */
        #sopModal .sop-viewer input[type="checkbox"] {
            margin-right: 6px;
            transform: translateY(1px);
        }
        #sopModal .sop-viewer .task-list-item { list-style: none; }
        #sopModal .sop-viewer .task-list-item-checkbox { margin-left: -20px; }
        #sopModal .sop-editor {
            width: 100%;
            min-height: 55vh;
            max-height: 70vh;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace;
            font-size: 12.5px;
            line-height: 1.45;
            border: 1px solid #ced4da;
            border-radius: 6px;
            padding: 12px 14px;
            resize: vertical;
        }
        #sopModal .sop-editor:focus {
            outline: none;
            border-color: #1E90FF;
            box-shadow: 0 0 0 3px rgba(30, 144, 255, 0.18);
        }
        #sopModal .sop-footer-status {
            font-size: 12px;
            color: #6c757d;
        }
        #sopModal .sop-footer-status.is-error { color: #b71c1c; }
        #sopModal .sop-footer-status.is-success { color: #2e7d32; }
    </style>
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between">
                <h4 class="page-title mb-0">
                    <i class="ri-message-3-line me-2 text-primary"></i>CC Messages Audit
                </h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="javascript:void(0);">Audit Master</a></li>
                        <li class="breadcrumb-item active">CC Messages Audit</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    {{-- ================ AGENT-WISE KPI PANEL (top) ================ --}}
    <div class="row">
        <div class="col-12">
            <div class="card kpi-panel-card">
                <div class="card-body">
                    <div class="kpi-panel-header">
                        <h5>
                            <i class="ri-team-line"></i>
                            Agent-wise KPI
                            <small class="text-muted fw-normal" id="kpiWindowLabel">last 90 days</small>
                        </h5>
                        <div class="d-flex align-items-center gap-2">
                            {{-- SOP (Standard Operating Procedure) button.
                                 Click  = open modal in view-only mode (everyone).
                                 Double-click = open in edit mode for SOP admins only
                                                (president@5core.com, software5@5core.com). --}}
                            <button type="button"
                                    id="sopOpenBtn"
                                    class="sop-btn"
                                    data-can-edit="{{ !empty($isSopAdmin) ? '1' : '0' }}"
                                    data-sop-key="{{ $sopKey ?? 'cc_messages' }}"
                                    title="{{ !empty($isSopAdmin)
                                        ? 'Click: view SOP — Double-click: edit SOP'
                                        : 'View Standard Operating Procedure (SOP)' }}"
                                    aria-label="Open SOP">
                                <img src="{{ asset('assets/images/task-sop-icon.png') }}" alt="SOP">
                            </button>
                            <select id="kpiWindow" class="form-select form-select-sm" style="width: 140px;">
                                <option value="30">Last 30 days</option>
                                <option value="60">Last 60 days</option>
                                <option value="90" selected>Last 90 days</option>
                                <option value="180">Last 180 days</option>
                                <option value="365">Last 365 days</option>
                            </select>
                            <button type="button" id="kpiRefreshBtn" class="btn btn-sm btn-outline-primary">
                                <i class="ri-refresh-line"></i>
                            </button>
                        </div>
                    </div>

                    {{-- Roll-up tiles --}}
                    <div class="kpi-summary-row">
                        <div class="kpi-tile t-agents">
                            <i class="ri-user-star-line kpi-icon"></i>
                            <div>
                                <div class="kpi-label">Active Agents</div>
                                <div class="kpi-value" id="kpiTotalAgents">—</div>
                            </div>
                        </div>
                        <div class="kpi-tile t-audits">
                            <i class="ri-checkbox-multiple-line kpi-icon"></i>
                            <div>
                                <div class="kpi-label">Total Audits</div>
                                <div class="kpi-value" id="kpiTotalAudits">—</div>
                            </div>
                        </div>
                        <div class="kpi-tile t-avg">
                            <i class="ri-bar-chart-2-line kpi-icon"></i>
                            <div>
                                <div class="kpi-label">Avg Score</div>
                                <div class="kpi-value" id="kpiAvgScore">—</div>
                            </div>
                        </div>
                        <div class="kpi-tile t-critical">
                            <i class="ri-error-warning-line kpi-icon"></i>
                            <div>
                                <div class="kpi-label">Critical Failures</div>
                                <div class="kpi-value" id="kpiCriticalFailures">—</div>
                            </div>
                        </div>
                    </div>

                    {{-- Chart + agent table --}}
                    <div class="kpi-body">
                        <div>
                            <div id="agentScoreChart"></div>
                            <div id="agentScoreEmpty" class="text-center text-muted py-4 d-none">
                                <i class="ri-line-chart-line" style="font-size: 28px; opacity: 0.4;"></i>
                                <div class="mt-1">No audits yet in this window.</div>
                            </div>
                        </div>
                        <div>
                            <div class="kpi-agent-table-wrap">
                                <table class="table table-sm kpi-agent-table">
                                    <thead>
                                        <tr>
                                            <th>Agent</th>
                                            <th class="text-center">Audits</th>
                                            <th class="text-end">Avg</th>
                                            <th class="text-end">Last</th>
                                            <th class="text-center">Grade</th>
                                            <th class="text-center" title="Critical failures">
                                                <i class="ri-error-warning-line"></i>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody id="kpiAgentTableBody">
                                        <tr><td colspan="6" class="text-center text-muted py-3">No data yet.</td></tr>
                                    </tbody>
                                </table>
                            </div>

                            {{-- Remarks for the selected agent --}}
                            <div class="kpi-remarks-card">
                                <div class="kpi-remarks-header">
                                    <span><i class="ri-chat-quote-line me-1"></i> Auditor Remarks <small class="text-muted" id="kpiRemarksAgentLabel"></small></span>
                                    <span class="badge bg-secondary" id="kpiRemarksCount">0</span>
                                </div>
                                <div class="kpi-remarks-list" id="kpiRemarksList">
                                    <div class="text-center text-muted py-3">
                                        Select an agent to see remarks.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div id="ccMessagesAuditTable"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- ================== AUDIT MODAL (CC Messages) ================== --}}
    <div class="modal fade" id="auditChecklistModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title d-flex align-items-center flex-wrap gap-2">
                        <img src="{{ asset('images/audit-button-blue.png') }}" alt="Audit"
                            style="width: 32px; height: 32px;">
                        <span>CC Messages Audit</span>
                        <span class="audit-channel-pill" id="auditChannelName">Channel</span>
                        <span id="auditAdminBadge" class="admin-only-badge d-none">
                            <i class="ri-shield-user-line"></i> Admin Mode
                        </span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body">

                    <ul class="nav nav-tabs audit-tabs mb-3" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active" id="audit-form-tab" data-bs-toggle="tab"
                                data-bs-target="#audit-form-pane" type="button" role="tab">
                                <i class="ri-edit-line me-1"></i> New Audit
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" id="audit-history-tab" data-bs-toggle="tab"
                                data-bs-target="#audit-history-pane" type="button" role="tab">
                                <i class="ri-history-line me-1"></i> History
                                <span class="badge bg-secondary" id="auditHistoryCount">0</span>
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content">
                        {{-- ====================== FORM TAB ====================== --}}
                        <div class="tab-pane fade show active" id="audit-form-pane" role="tabpanel">
                            <form id="auditForm" enctype="multipart/form-data">
                                {{-- Header / context fields --}}
                                <div class="row g-2 mb-3">
                                    <div class="col-md-4">
                                        <label class="form-label small fw-semibold mb-1">Executive (CC Agent)</label>
                                        <input type="text" class="form-control form-control-sm" id="auditExecutive"
                                            placeholder="Agent name being audited" maxlength="191">
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label small fw-semibold mb-1">Message / Thread Reference</label>
                                        <input type="text" class="form-control form-control-sm" id="auditMessageRef"
                                            placeholder="Order #, ticket id or thread URL" maxlength="500">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small fw-semibold mb-1">Audit Date</label>
                                        <input type="date" class="form-control form-control-sm" id="auditDate">
                                    </div>
                                </div>

                                {{-- Score summary card --}}
                                <div class="audit-score-card mb-3">
                                    <div class="audit-score-grade" id="auditGradeBadge">
                                        <span class="grade-letter" id="auditGradeLetter">—</span>
                                        <span class="grade-desc"  id="auditGradeDesc">Not scored</span>
                                    </div>
                                    <div class="audit-score-meta flex-grow-1">
                                        <div class="d-flex flex-wrap gap-3">
                                            <div>
                                                <div class="meta-label">Total Score</div>
                                                <div class="meta-value" id="auditTotalScore">0.0 / 120</div>
                                            </div>
                                            <div>
                                                <div class="meta-label">Core QA <small class="text-muted">(70%)</small></div>
                                                <div class="meta-value" id="auditCoreScore">0%</div>
                                            </div>
                                            <div>
                                                <div class="meta-label">Channel Compliance <small class="text-muted">(30%)</small></div>
                                                <div class="meta-value" id="auditComplianceScore">0%</div>
                                            </div>
                                            <div>
                                                <div class="meta-label">Bonus</div>
                                                <input type="number" min="0" max="20" step="0.5"
                                                    class="form-control form-control-sm meta-bonus"
                                                    id="auditBonusPoints" value="0" style="width: 80px;">
                                            </div>
                                        </div>
                                        <div id="auditCriticalBanner" class="alert alert-danger py-1 px-2 mt-2 mb-0 d-none">
                                            <i class="ri-error-warning-line me-1"></i>
                                            <strong>Critical Failure</strong> – this audit will be graded F.
                                        </div>
                                    </div>
                                </div>

                                {{-- Loading state --}}
                                <div id="auditLoading" class="text-center text-muted py-4 d-none">
                                    <div class="spinner-border spinner-border-sm me-2"></div> Loading audit parameters…
                                </div>

                                {{-- Dynamic parameter sections rendered here --}}
                                <div id="auditParamsContainer"></div>

                                {{-- Critical failure reasons --}}
                                <div class="mt-3">
                                    <label class="form-label fw-semibold mb-1">Critical Failure Reasons (optional)</label>
                                    <div class="d-flex flex-wrap gap-2 mb-2" id="auditCriticalReasonChips"></div>
                                    <textarea id="auditCriticalReasonsText" class="form-control form-control-sm"
                                        rows="2" placeholder="Describe any critical failure not captured above"></textarea>
                                </div>

                                {{-- Auditor notes --}}
                                <div class="mt-3">
                                    <label class="form-label fw-semibold mb-1">Auditor Notes</label>
                                    <textarea id="auditNotes" class="form-control form-control-sm" rows="2"
                                        placeholder="Coaching feedback, key takeaways, etc."></textarea>
                                </div>

                                {{-- Attachments --}}
                                <div class="mt-3">
                                    <label class="form-label fw-semibold mb-1">
                                        Attachments <small class="text-muted">(screenshots, PDFs – up to 5MB each)</small>
                                    </label>
                                    <input type="file" class="form-control form-control-sm" id="auditAttachments"
                                        multiple accept="image/*,application/pdf">
                                </div>
                            </form>
                        </div>

                        {{-- ====================== HISTORY TAB ====================== --}}
                        <div class="tab-pane fade" id="audit-history-pane" role="tabpanel">
                            <div id="auditHistoryLoading" class="text-center text-muted py-4 d-none">
                                <div class="spinner-border spinner-border-sm me-2"></div> Loading history…
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-striped audit-history-table mb-0">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Date</th>
                                            <th>Executive</th>
                                            <th>Reference</th>
                                            <th class="text-end">Core QA</th>
                                            <th class="text-end">Compliance</th>
                                            <th class="text-end">Total</th>
                                            <th class="text-center">Grade</th>
                                            <th class="text-center">Critical</th>
                                        </tr>
                                    </thead>
                                    <tbody id="auditHistoryBody">
                                        <tr><td colspan="9" class="text-center text-muted py-3">No audits yet.</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveAuditBtn">
                        <i class="ri-save-line me-1"></i> Save Audit
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ============ PARAMETER EDITOR (admin only, nested) ============ --}}
    <div class="modal fade" id="paramEditorModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background: #ede7f6; color: #4527a0;">
                    <h5 class="modal-title">
                        <i class="ri-tools-line me-1"></i>
                        <span id="paramEditorTitle">Add Audit Parameter</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="paramEditorForm">
                        <input type="hidden" id="paramEditorId">
                        <input type="hidden" id="paramEditorModule" value="cc_messages">

                        <div class="mb-2">
                            <label class="form-label small fw-semibold mb-1">Label <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="paramEditorLabel"
                                maxlength="255" required>
                        </div>

                        <div class="mb-2">
                            <label class="form-label small fw-semibold mb-1">Description</label>
                            <textarea class="form-control form-control-sm" id="paramEditorDescription" rows="2"
                                placeholder="Hint shown to auditors under the label"></textarea>
                        </div>

                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold mb-1">Category <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm" id="paramEditorCategory" required>
                                    <option value="core_qa">Core QA (70% weight)</option>
                                    <option value="channel_compliance">Channel Compliance (30% weight)</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold mb-1">Max Score</label>
                                <input type="number" class="form-control form-control-sm" id="paramEditorMaxScore"
                                    min="1" max="100" step="1" value="10">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold mb-1">Weight</label>
                                <input type="number" class="form-control form-control-sm" id="paramEditorWeight"
                                    min="0" max="99.99" step="0.1" value="1">
                            </div>
                        </div>

                        <div class="row g-2 mt-1">
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold mb-1">Sort Order</label>
                                <input type="number" class="form-control form-control-sm" id="paramEditorSort"
                                    min="0" max="65535" placeholder="auto">
                            </div>
                            <div class="col-md-8 d-flex align-items-end gap-3 pt-2">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="paramEditorCritical">
                                    <label class="form-check-label small" for="paramEditorCritical">
                                        Critical parameter <span class="text-muted">(failing → grade F)</span>
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="paramEditorActive" checked>
                                    <label class="form-check-label small" for="paramEditorActive">
                                        Active
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="mt-2">
                            <label class="form-label small fw-semibold mb-1">
                                Code <small class="text-muted">(auto-generated if blank)</small>
                            </label>
                            <input type="text" class="form-control form-control-sm" id="paramEditorCode"
                                maxlength="80" pattern="[a-z0-9_]+"
                                placeholder="e.g. response_within_sla — lowercase letters, digits, underscore">
                            <small class="text-muted">Stable identifier — change with care; existing audits keep their snapshot.</small>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-sm btn-primary" id="paramEditorSaveBtn">
                        <i class="ri-save-line me-1"></i> Save Parameter
                    </button>
                </div>
            </div>
        </div>
    </div>
    {{-- ============ REMARKS VIEWER MODAL ============ --}}
    <div class="modal fade" id="remarksViewerModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #1976d2 0%, #0d47a1 100%); color: #fff;">
                    <h5 class="modal-title d-flex align-items-center flex-wrap gap-2">
                        <i class="ri-chat-quote-line"></i>
                        <span>Audit Remarks</span>
                        <span class="audit-channel-pill" id="remarksViewerChannel">Channel</span>
                        <span class="badge bg-light text-dark ms-1" id="remarksViewerCount">0</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="remarksViewerEmpty" class="text-center text-muted py-4 d-none">
                        <i class="ri-chat-off-line" style="font-size: 28px; opacity: 0.4;"></i>
                        <div class="mt-1">No remarks recorded for this audit.</div>
                    </div>
                    <div id="remarksViewerList"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ================ SOP (Standard Operating Procedure) MODAL ================
         Click on the SOP button = open this modal in read-only "viewer" mode.
         Double-click on the SOP button (and only if the current user is in
         AuditMasterController::SOP_ADMIN_EMAILS, currently:
            - president@5core.com
            - software5@5core.com
         ) = open in edit mode. Editors paste the HTML they copied out of
         ChatGPT into the textarea and click Save. The HTML is persisted on
         the server (storage/app/sop/<module>-sop.html) and shown to everyone. --}}
    <div class="modal fade" id="sopModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title d-flex align-items-center flex-wrap">
                        <img src="{{ asset('assets/images/task-sop-icon.png') }}" alt="SOP" class="sop-title-img">
                        <span>Standard Operating Procedure</span>
                        <span class="sop-badge">CC Messages</span>
                        <span id="sopAdminBadge" class="sop-admin-badge d-none">
                            <i class="ri-edit-line"></i> Edit Mode
                        </span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="sop-mode-hint" id="sopModeHint">
                    {{-- Filled in by JS based on current mode (viewer / editor). --}}
                </div>

                <div class="modal-body">
                    {{-- Read-only viewer: shows the saved HTML as rendered HTML.
                         Hidden in edit mode. --}}
                    <div id="sopViewer"
                         class="sop-viewer"
                         data-empty-hint="An admin can double-click the SOP button to add content.">
                    </div>

                    {{-- Editable textarea: shown only in edit mode (admins).
                         Auditors paste HTML straight out of ChatGPT here. --}}
                    <textarea id="sopEditor"
                              class="sop-editor d-none"
                              spellcheck="false"
                              placeholder="Paste the SOP content (copied from ChatGPT) here...&#10;&#10;Both Markdown and raw HTML are supported.&#10; • Markdown:   # Heading,  ![image](url),  - bullet,  | table | ...&#10; • HTML:       <h1>Heading</h1> <img src=&quot;url&quot;> ...&#10;&#10;The viewer renders it as a formatted document (real headings, real images), never as raw code."></textarea>
                </div>

                <div class="modal-footer d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="sop-footer-status" id="sopStatus"></div>
                    <div class="d-flex gap-2">
                        {{-- Edit / Save / Cancel buttons. The Edit button is
                             only visible to SOP admins; it switches the modal
                             into edit mode (same effect as double-clicking
                             the SOP icon). --}}
                        @if(!empty($isSopAdmin))
                            <button type="button" id="sopEditBtn" class="btn btn-sm btn-outline-primary">
                                <i class="ri-edit-line me-1"></i> Edit
                            </button>
                            <button type="button" id="sopCancelBtn" class="btn btn-sm btn-outline-secondary d-none">
                                Cancel
                            </button>
                            <button type="button" id="sopSaveBtn" class="btn btn-sm btn-primary d-none">
                                <i class="ri-save-3-line me-1"></i> Save SOP
                            </button>
                        @endif
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-after-vite')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    {{-- Markdown -> HTML renderer for the SOP viewer (so pasted ChatGPT
         Markdown shows as formatted document with images, headings, etc.
         instead of raw text). --}}
    <script src="https://cdn.jsdelivr.net/npm/marked@12.0.2/marked.min.js"></script>
    {{-- DOMPurify sanitizes the rendered HTML before injection so a paste
         can't ship a stray <script>. --}}
    <script src="https://cdn.jsdelivr.net/npm/dompurify@3.1.6/dist/purify.min.js"></script>
    <script>
        $(function () {
            const channelsWithLogo = @json($channelsWithLogo);

            // Audit-button image variants. Picked dynamically based on how
            // recently the channel was last audited.
            const AUDIT_BTN = {
                green: "{{ asset('images/audit-button-green.png') }}",  // <= 7 days
                blue:  "{{ asset('images/audit-button-blue.png') }}",   // 8 - 15 days
                red:   "{{ asset('images/audit-button-red.png') }}",    // never audited or > 15 days
            };

            // Returns 'red' | 'blue' | 'green' for a given last-audited timestamp.
            // null / empty => RED (never audited)
            function pickAuditColor(lastAuditedAt) {
                if (!lastAuditedAt) return 'red';
                const last = new Date(lastAuditedAt);
                if (isNaN(last.getTime())) return 'red';
                const days = Math.floor((Date.now() - last.getTime()) / 86400000);
                if (days <= 7)  return 'green';
                if (days > 15)  return 'red';
                return 'blue';
            }

            // Human-friendly label like "12 days ago" / "Never audited"
            function lastAuditedLabel(lastAuditedAt) {
                if (!lastAuditedAt) return 'Never audited';
                const last = new Date(lastAuditedAt);
                if (isNaN(last.getTime())) return 'Never audited';
                const days = Math.floor((Date.now() - last.getTime()) / 86400000);
                if (days <= 0) return 'Audited today';
                if (days === 1) return 'Audited 1 day ago';
                return `Audited ${days} days ago`;
            }

            // Fallback palette for grades when audit_grades doesn't supply a colour.
            const GRADE_FALLBACK_COLORS = {
                'A+': '#198754', 'A': '#28a745', 'B': '#0d6efd',
                'C': '#ffc107',  'D': '#fd7e14', 'F': '#dc3545',
            };
            function resolveGradeColor(grade, color) {
                if (color) return color;
                if (!grade) return '#6c757d';
                return GRADE_FALLBACK_COLORS[grade] || '#6c757d';
            }

            const tableData = channelsWithLogo.map((row, index) => ({
                id: index + 1,
                channel: row.channel,
                logo: row.logo || null,
                last_audited_at: row.last_audited_at || null,
                last_audited_full: row.last_audited_full || null,
                last_auditor_name: row.last_auditor_name || null,
                last_audited: row.last_audited_at
                    ? new Date(row.last_audited_at).toLocaleDateString()
                    : '-',
                last_grade: row.last_grade || null,
                last_grade_color: row.last_grade_color || null,
                last_remarks_items: Array.isArray(row.last_remarks_items) ? row.last_remarks_items : [],
                last_auditor_notes: row.last_auditor_notes || null,
                last_critical_reasons: row.last_critical_reasons || null,
                last_remarks_count: parseInt(row.last_remarks_count, 10) || 0,
            }));

            const table = new Tabulator('#ccMessagesAuditTable', {
                data: tableData,
                layout: 'fitColumns',
                // Let rows grow to fit the inline remarks cell (no truncation).
                // We don't pin a rowHeight, so Tabulator auto-sizes each row
                // to its tallest cell.
                resizableColumnFit: true,
                pagination: 'local',
                paginationSize: 25,
                paginationSizeSelector: [10, 25, 50, 100],
                placeholder: 'No channels found in Channel Master.',
                columns: [
                    { title: '#', field: 'id', width: 60, hozAlign: 'center' },
                    {
                        title: 'IMG',
                        field: 'logo',
                        width: 70,
                        hozAlign: 'center',
                        headerSort: false,
                        formatter: function (cell) {
                            const logo = cell.getValue();
                            const channel = (cell.getRow().getData().channel || '').toString();
                            if (!logo) {
                                return `<span class="channel-logo-placeholder" title="No logo">
                                            <i class="fas fa-image"></i>
                                        </span>`;
                            }
                            const url = `/storage/${logo}`;
                            return `<img src="${url}" alt="${channel}" class="channel-logo-thumb" onerror="this.style.display='none'"/>`;
                        },
                    },
                    {
                        title: 'Channels',
                        field: 'channel',
                        formatter: cell => `<span class="channel-pill">${cell.getValue() ?? ''}</span>`,
                    },
                    {
                        title: 'Audit',
                        field: 'last_audited_at',
                        width: 90,
                        hozAlign: 'center',
                        headerSort: false,
                        formatter: function (cell) {
                            const data    = cell.getRow().getData();
                            const channel = (data.channel || '').toString();
                            const safeCh  = channel.replace(/"/g, '&quot;');
                            const color   = pickAuditColor(cell.getValue());
                            const label   = lastAuditedLabel(cell.getValue());
                            const tip     = `${channel} — ${label}`;
                            return `<button type="button" class="audit-btn open-audit-btn audit-btn-${color}"
                                        data-channel="${safeCh}" title="${tip.replace(/"/g, '&quot;')}">
                                        <img src="${AUDIT_BTN[color]}" alt="Audit"/>
                                    </button>`;
                        },
                    },
                    {
                        // Last Audited — shows the auditor's name (top line)
                        // and the exact date + time the audit was saved
                        // (second line). Falls back gracefully when either
                        // piece of data is missing.
                        title: 'Last Audited',
                        field: 'last_audited_full',
                        width: 200,
                        headerSort: true,
                        formatter: function (cell) {
                            const data = cell.getRow().getData();
                            const iso  = cell.getValue() || data.last_audited_at;
                            const name = (data.last_auditor_name || '').toString().trim();

                            if (!iso && !name) {
                                return `<span class="text-muted">-</span>`;
                            }

                            let dateLine = '-';
                            let timeLine = '';
                            if (iso) {
                                const d = new Date(iso);
                                if (!isNaN(d.getTime())) {
                                    dateLine = d.toLocaleDateString(undefined, {
                                        year: 'numeric', month: 'short', day: '2-digit'
                                    });
                                    timeLine = d.toLocaleTimeString(undefined, {
                                        hour: '2-digit', minute: '2-digit', hour12: true
                                    });
                                }
                            }

                            const safeName = escapeHtml(name || 'Unknown auditor');
                            const nameHtml = name
                                ? `<div class="la-auditor" title="Auditor: ${safeName}">
                                       <i class="ri-user-3-line me-1"></i>${safeName}
                                   </div>`
                                : `<div class="la-auditor la-auditor-missing" title="Auditor not recorded">
                                       <i class="ri-user-unfollow-line me-1"></i>—
                                   </div>`;

                            return `<div class="la-cell">
                                        ${nameHtml}
                                        <div class="la-time">
                                            <i class="ri-time-line me-1"></i>${escapeHtml(dateLine)}${timeLine ? ` · ${escapeHtml(timeLine)}` : ''}
                                        </div>
                                    </div>`;
                        },
                    },
                    {
                        title: 'Grade',
                        field: 'last_grade',
                        width: 110,
                        hozAlign: 'center',
                        formatter: function (cell) {
                            const data  = cell.getRow().getData();
                            const grade = cell.getValue();
                            if (!grade) {
                                return `<span class="text-muted">-</span>`;
                            }
                            const color = resolveGradeColor(grade, data.last_grade_color);
                            return `<span class="audit-grade-pill" style="background:${color}">${escapeHtml(grade)}</span>`;
                        },
                    },
                    {
                        // Inline remarks — always visible, no click/modal.
                        // Renders per-parameter feedback + auditor notes +
                        // critical failure reasons stacked vertically inside
                        // the cell so the auditor can see them at a glance.
                        title: 'Remarks',
                        field: 'last_remarks_count',
                        width: 380,
                        hozAlign: 'left',
                        headerSort: false,
                        variableHeight: true,
                        formatter: function (cell) {
                            const data  = cell.getRow().getData();
                            const items = Array.isArray(data.last_remarks_items) ? data.last_remarks_items : [];
                            const notes = (data.last_auditor_notes || '').toString().trim();
                            const crits = (data.last_critical_reasons || '').toString().trim();

                            if (!items.length && !notes && !crits) {
                                return `<div class="cell-remarks"><span class="cr-empty">No remarks</span></div>`;
                            }

                            const parts = [];

                            items.forEach(function (it) {
                                const cat       = (it.category === 'channel_compliance') ? 'channel' : 'core';
                                const catCls    = (cat === 'channel') ? 'cr-channel' : '';
                                const sc        = Number.isFinite(+it.score)     ? (+it.score).toFixed(1)     : '0.0';
                                const max       = Number.isFinite(+it.max_score) ? (+it.max_score).toFixed(1) : '0.0';
                                const isCrit    = !!it.critical;
                                const critTag   = isCrit ? `<span class="cr-crit-tag">CRITICAL</span>` : '';
                                const label     = escapeHtml(it.label || 'Parameter');
                                const text      = escapeHtml(it.remarks || '');
                                const wrapCls   = isCrit ? 'cr-crit' : catCls;
                                parts.push(
                                    `<div class="cr-item ${wrapCls}">
                                        <span class="cr-label">${label}</span>
                                        <span class="cr-score">(${sc}/${max})</span>
                                        ${critTag}
                                        <span class="cr-text">${text}</span>
                                    </div>`
                                );
                            });

                            if (notes) {
                                parts.push(
                                    `<div class="cr-item cr-note">
                                        <span class="cr-label"><i class="ri-edit-line me-1"></i>Auditor Notes</span>
                                        <span class="cr-text">${escapeHtml(notes)}</span>
                                    </div>`
                                );
                            }

                            if (crits) {
                                parts.push(
                                    `<div class="cr-item cr-crit">
                                        <span class="cr-label"><i class="ri-error-warning-fill me-1"></i>Critical Failure Reasons</span>
                                        <span class="cr-crit-tag">CRITICAL</span>
                                        <span class="cr-text">${escapeHtml(crits)}</span>
                                    </div>`
                                );
                            }

                            return `<div class="cell-remarks">${parts.join('')}</div>`;
                        },
                    },
                ],
            });

            // ============================================================
            //  CC Messages Audit Modal — dynamic, DB-backed
            // ============================================================
            const AUDIT_MODULE = 'cc_messages';
            const ROUTES = {
                config:    '{{ route('audit.master.parameters') }}',
                store:     '{{ route('audit.master.audits.store') }}',
                history:   '{{ route('audit.master.audits.history') }}',
                agentKpis: '{{ route('audit.master.agent.kpis') }}',
                paramStore:   '{{ route('audit.master.parameters.store') }}',
                paramUpdate:  '{{ url('/audit-master/parameters/manage') }}', // append /{id}
                paramDestroy: '{{ url('/audit-master/parameters/manage') }}', // append /{id}
            };

            // Server-supplied admin flag (also re-confirmed by getAuditConfig response)
            let isAuditAdmin = {{ isset($isAuditAdmin) && $isAuditAdmin ? 'true' : 'false' }};

            let currentChannel  = null;
            let currentParams   = [];   // [{id, code, label, description, category, max_score, weight, is_critical, is_active}]
            let currentGrades   = [];   // [{grade, min_score, max_score, color, description}]

            function escapeHtml(str) {
                return String(str ?? '')
                    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
            }

            function todayIso() {
                const d = new Date();
                const pad = n => String(n).padStart(2, '0');
                return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;
            }

            // Render dynamic parameter sections grouped by category
            function renderParameters() {
                const $c = $('#auditParamsContainer').empty();

                const groups = {
                    core_qa: { title: 'Core QA Parameters', meta: 'Weight: 70%', items: [], cls: '' },
                    channel_compliance: { title: 'Channel Compliance', meta: 'Weight: 30%', items: [], cls: 'compliance' },
                };

                currentParams.forEach(p => {
                    const cat = groups[p.category] ? p.category : 'core_qa';
                    groups[cat].items.push(p);
                });

                // Always render both section cards when admin (so the Add button is reachable
                // even on a fresh module). For non-admins, hide empty sections.
                Object.entries(groups).forEach(([key, group]) => {
                    if (!group.items.length && !isAuditAdmin) return;

                    const adminAddBtn = isAuditAdmin
                        ? `<button type="button" class="admin-add-btn add-param-btn" data-category="${key}" title="Add a new parameter to this section">
                               <i class="ri-add-line"></i> Add
                           </button>`
                        : '';

                    const rowsHtml = group.items.length
                        ? group.items.map(p => renderParamRowHtml(p)).join('')
                        : `<div class="audit-param-row" style="grid-template-columns:1fr;">
                               <div class="text-muted text-center py-2">No parameters in this section yet.</div>
                           </div>`;

                    $c.append(`
                        <div class="audit-section-card">
                            <div class="audit-section-header ${group.cls}">
                                <h6>${group.title}</h6>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="section-meta">${group.meta} · ${group.items.length} parameter(s)</span>
                                    ${adminAddBtn}
                                </div>
                            </div>
                            ${rowsHtml}
                        </div>
                    `);
                });

                if (!currentParams.length && !isAuditAdmin) {
                    $c.html(`<div class="text-center text-muted py-3">
                        No active audit parameters configured for this module yet.
                    </div>`);
                }

                recalcScore();
            }

            // Single parameter row HTML — extracted so admin add/edit can re-render
            // a single row without rebuilding the whole list.
            function renderParamRowHtml(p) {
                const critTag = p.is_critical
                    ? `<span class="critical-tag" title="Critical parameter">CRITICAL</span>`
                    : '';
                const desc = p.description
                    ? `<div class="param-desc">${escapeHtml(p.description)}</div>`
                    : '';
                const adminActions = isAuditAdmin
                    ? `<div class="param-admin-actions">
                           <button type="button" class="edit-param-btn" data-param-id="${p.id}" title="Edit parameter">
                               <i class="ri-pencil-line"></i> Edit
                           </button>
                           <button type="button" class="archive-param-btn" data-param-id="${p.id}" title="Archive (soft delete)">
                               <i class="ri-archive-line"></i> Archive
                           </button>
                       </div>`
                    : '';
                return `
                    <div class="audit-param-row"
                         data-param-id="${p.id}"
                         data-max="${p.max_score}"
                         data-weight="${p.weight}"
                         data-category="${escapeHtml(p.category)}"
                         data-is-critical="${p.is_critical ? 1 : 0}">
                        <div>
                            <div class="param-label">${escapeHtml(p.label)}${critTag}</div>
                            ${desc}
                            ${adminActions}
                        </div>
                        <div class="score-input-wrap">
                            <input type="number" class="form-control form-control-sm score-input param-score"
                                min="0" max="${p.max_score}" step="0.5" value="${p.max_score}">
                            <span class="max-score">/ ${p.max_score}</span>
                        </div>
                        <label class="critical-fail-toggle">
                            <input type="checkbox" class="param-critical-fail">
                            <span>Critical fail</span>
                        </label>
                        <div>
                            <input type="text" class="form-control form-control-sm remarks-input param-remarks"
                                placeholder="Remarks (optional)" maxlength="500">
                        </div>
                    </div>`;
            }

            // Render the critical-fail reason chips (free presets)
            function renderCriticalReasonChips(reasons) {
                const $box = $('#auditCriticalReasonChips').empty();
                (reasons || []).forEach(r => {
                    $box.append(`<span class="audit-critical-chip" data-reason="${escapeHtml(r)}">${escapeHtml(r)}</span>`);
                });
            }

            // Recalculate score + grade live
            function recalcScore() {
                const cat = {
                    core_qa: { raw: 0, max: 0 },
                    channel_compliance: { raw: 0, max: 0 },
                };
                let hasCritical = false;

                $('#auditParamsContainer .audit-param-row').each(function () {
                    const $row = $(this);
                    const max     = parseFloat($row.data('max')) || 0;
                    const weight  = parseFloat($row.data('weight')) || 0;
                    const isCrit  = parseInt($row.data('is-critical'), 10) === 1;
                    const cat0    = $row.data('category') || 'core_qa';
                    let   score   = parseFloat($row.find('.param-score').val());
                    if (isNaN(score) || score < 0) score = 0;
                    if (score > max) score = max;
                    $row.find('.param-score').val(score);

                    const failedManually = $row.find('.param-critical-fail').is(':checked');
                    const failedAuto     = isCrit && score < (max * 0.5);
                    const failed         = failedManually || failedAuto;

                    $row.toggleClass('is-failed', failed);
                    if (failed) hasCritical = true;

                    if (cat[cat0]) {
                        cat[cat0].raw += score * weight;
                        cat[cat0].max += max   * weight;
                    }
                });

                const corePct      = cat.core_qa.max > 0 ? (cat.core_qa.raw / cat.core_qa.max) * 100 : 0;
                const compliancePct = cat.channel_compliance.max > 0
                    ? (cat.channel_compliance.raw / cat.channel_compliance.max) * 100 : 0;
                const bonus  = Math.min(20, Math.max(0, parseFloat($('#auditBonusPoints').val()) || 0));
                let total    = (corePct * 0.70) + (compliancePct * 0.30) + bonus;
                if (total < 0) total = 0;
                if (total > 120) total = 120;

                $('#auditCoreScore').text(corePct.toFixed(1) + '%');
                $('#auditComplianceScore').text(compliancePct.toFixed(1) + '%');
                $('#auditTotalScore').text(total.toFixed(1) + ' / 120');

                // Grade lookup (force F on critical failure)
                let grade = null;
                if (hasCritical) {
                    grade = currentGrades.find(g => g.grade === 'F') || { grade: 'F', color: '#dc3545', description: 'Fail' };
                } else {
                    grade = currentGrades.find(g =>
                        total >= parseFloat(g.min_score) && total <= parseFloat(g.max_score)
                    ) || currentGrades[currentGrades.length - 1] || null;
                }

                if (grade) {
                    $('#auditGradeLetter').text(grade.grade);
                    $('#auditGradeDesc').text(grade.description || '');
                    $('#auditGradeBadge').css('background', grade.color || '#6c757d');
                } else {
                    $('#auditGradeLetter').text('—');
                    $('#auditGradeDesc').text('Not scored');
                    $('#auditGradeBadge').css('background', '#6c757d');
                }

                $('#auditCriticalBanner').toggleClass('d-none', !hasCritical);
            }

            // Load parameters + grades + latest audit summary for a channel
            function loadAuditConfig(channel) {
                $('#auditLoading').removeClass('d-none');
                $('#auditParamsContainer').empty();

                return $.get(ROUTES.config, { module: AUDIT_MODULE, channel: channel })
                    .then(res => {
                        currentParams = res.parameters || [];
                        currentGrades = (res.grades || []).map(g => ({
                            grade: g.grade,
                            min_score: parseFloat(g.min_score),
                            max_score: parseFloat(g.max_score),
                            color: g.color,
                            description: g.description,
                        }));
                        // Trust the server's admin flag (in case session changed)
                        if (typeof res.is_admin !== 'undefined') {
                            isAuditAdmin = !!res.is_admin;
                        }
                        $('#auditAdminBadge').toggleClass('d-none', !isAuditAdmin);
                        renderCriticalReasonChips(res.critical_fail_reasons);
                        renderParameters();
                    })
                    .catch(() => {
                        $('#auditParamsContainer').html(`<div class="alert alert-danger">
                            Failed to load audit parameters. Please try again.
                        </div>`);
                    })
                    .always(() => $('#auditLoading').addClass('d-none'));
            }

            // Load history for a channel
            function loadAuditHistory(channel) {
                $('#auditHistoryLoading').removeClass('d-none');
                $('#auditHistoryBody').empty();

                return $.get(ROUTES.history, { module: AUDIT_MODULE, channel: channel })
                    .then(res => {
                        const audits = res.audits || [];
                        $('#auditHistoryCount').text(audits.length);
                        if (!audits.length) {
                            $('#auditHistoryBody').html(`<tr><td colspan="9" class="text-center text-muted py-3">No audits yet.</td></tr>`);
                            return;
                        }
                        const rowsHtml = audits.map((a, i) => {
                            const grade = currentGrades.find(g => g.grade === a.grade);
                            const color = (grade && grade.color) || '#6c757d';
                            const date  = a.audit_date || (a.created_at ? a.created_at.substring(0,10) : '');
                            return `<tr>
                                <td>${i+1}</td>
                                <td>${escapeHtml(date)}</td>
                                <td>${escapeHtml(a.executive_name || '-')}</td>
                                <td class="text-truncate" style="max-width:220px;" title="${escapeHtml(a.message_reference || '')}">
                                    ${escapeHtml(a.message_reference || '-')}
                                </td>
                                <td class="text-end">${parseFloat(a.core_qa_score).toFixed(1)}%</td>
                                <td class="text-end">${parseFloat(a.channel_compliance_score).toFixed(1)}%</td>
                                <td class="text-end fw-bold">${parseFloat(a.total_score).toFixed(1)}</td>
                                <td class="text-center">
                                    <span class="audit-grade-pill" style="background:${color}">${escapeHtml(a.grade || '-')}</span>
                                </td>
                                <td class="text-center">${a.has_critical_failure ? '<i class="ri-error-warning-fill text-danger"></i>' : ''}</td>
                            </tr>`;
                        }).join('');
                        $('#auditHistoryBody').html(rowsHtml);
                    })
                    .catch(() => {
                        $('#auditHistoryBody').html(`<tr><td colspan="9" class="text-center text-danger py-3">Failed to load history.</td></tr>`);
                    })
                    .always(() => $('#auditHistoryLoading').addClass('d-none'));
            }

            // Reset form fields and switch to the form tab
            function resetAuditForm() {
                $('#auditExecutive').val('');
                $('#auditMessageRef').val('');
                $('#auditDate').val(todayIso());
                $('#auditBonusPoints').val(0);
                $('#auditNotes').val('');
                $('#auditCriticalReasonsText').val('');
                $('#auditAttachments').val('');
                $('#auditCriticalReasonChips .audit-critical-chip').removeClass('is-on');
                $('#auditCriticalBanner').addClass('d-none');
                const tabEl = document.getElementById('audit-form-tab');
                if (tabEl) bootstrap.Tab.getOrCreateInstance(tabEl).show();
            }

            // Open the modal from any "Audit" button in the table
            $(document).on('click', '.open-audit-btn', function () {
                currentChannel = $(this).data('channel') || '';
                $('#auditChannelName').text(currentChannel);
                resetAuditForm();
                loadAuditConfig(currentChannel);
                loadAuditHistory(currentChannel);

                const el = document.getElementById('auditChecklistModal');
                bootstrap.Modal.getOrCreateInstance(el).show();
            });

            // Render the Remarks viewer modal for a given channel using the
            // already-loaded row data (no extra server roundtrip required).
            function openRemarksViewer(channel) {
                const row = table.getRows().find(r => r.getData().channel === channel);
                if (!row) return;
                const data = row.getData();
                const items = Array.isArray(data.last_remarks_items) ? data.last_remarks_items : [];
                const notes = (data.last_auditor_notes || '').trim();
                const crits = (data.last_critical_reasons || '').trim();
                const total = items.length + (notes ? 1 : 0) + (crits ? 1 : 0);

                $('#remarksViewerChannel').text(channel || '');
                $('#remarksViewerCount').text(total);

                const $list = $('#remarksViewerList').empty();
                if (!total) {
                    $('#remarksViewerEmpty').removeClass('d-none');
                } else {
                    $('#remarksViewerEmpty').addClass('d-none');

                    items.forEach(it => {
                        const cat = it.category || 'core_qa';
                        const catLabel = cat === 'channel_compliance' ? 'Channel Compliance' : 'Core QA';
                        const crit = it.critical
                            ? `<span class="remark-crit-tag">CRITICAL</span>` : '';
                        const max  = it.max_score != null ? it.max_score : '';
                        const sc   = it.score != null ? Number(it.score).toFixed(1) : '-';
                        $list.append(`
                            <div class="remark-card cat-${cat} ${it.critical ? 'is-critical' : ''}">
                                <div class="remark-head">
                                    <div>
                                        <span class="remark-label">${escapeHtml(it.label || 'Parameter')}</span>
                                        <span class="remark-cat-tag ${cat}">${catLabel}</span>
                                        ${crit}
                                    </div>
                                    <div class="remark-score">${sc} / ${max}</div>
                                </div>
                                <div class="remark-text">${escapeHtml(it.remarks || '')}</div>
                            </div>
                        `);
                    });

                    if (notes) {
                        $list.append(`
                            <div class="remark-card cat-note">
                                <div class="remark-head">
                                    <div>
                                        <span class="remark-label"><i class="ri-edit-line me-1"></i>Auditor Notes</span>
                                        <span class="remark-cat-tag note">Note</span>
                                    </div>
                                </div>
                                <div class="remark-text">${escapeHtml(notes)}</div>
                            </div>
                        `);
                    }

                    if (crits) {
                        $list.append(`
                            <div class="remark-card is-critical">
                                <div class="remark-head">
                                    <div>
                                        <span class="remark-label"><i class="ri-error-warning-fill me-1 text-danger"></i>Critical Failure Reasons</span>
                                        <span class="remark-crit-tag">CRITICAL</span>
                                    </div>
                                </div>
                                <div class="remark-text">${escapeHtml(crits)}</div>
                            </div>
                        `);
                    }
                }

                const el = document.getElementById('remarksViewerModal');
                bootstrap.Modal.getOrCreateInstance(el).show();
            }

            $(document).on('click', '.view-remarks-btn', function (e) {
                e.preventDefault();
                e.stopPropagation();
                const channel = $(this).data('channel') || '';
                openRemarksViewer(channel);
            });

            // Re-load history when the History tab is opened (in case state changed)
            $('#audit-history-tab').on('shown.bs.tab', function () {
                if (currentChannel) loadAuditHistory(currentChannel);
            });

            // Live recalculation
            $(document).on('input change', '#auditParamsContainer .param-score, #auditBonusPoints', recalcScore);
            $(document).on('change', '#auditParamsContainer .param-critical-fail', recalcScore);

            // Critical failure preset chips (toggle + sync into textarea)
            $(document).on('click', '.audit-critical-chip', function () {
                $(this).toggleClass('is-on');
                const reasons = $('.audit-critical-chip.is-on')
                    .map(function () { return $(this).data('reason'); })
                    .get();
                const existing = ($('#auditCriticalReasonsText').val() || '').split('\n')
                    .filter(line => line && !$('.audit-critical-chip').toArray().some(el => $(el).data('reason') === line));
                const merged = reasons.concat(existing).filter(Boolean).join('\n');
                $('#auditCriticalReasonsText').val(merged);
            });

            // Save audit
            $('#saveAuditBtn').on('click', function () {
                if (!currentChannel) return;
                if (!currentParams.length) {
                    alert('No audit parameters loaded.');
                    return;
                }

                const items = $('#auditParamsContainer .audit-param-row').map(function () {
                    const $row = $(this);
                    return {
                        id: parseInt($row.data('param-id'), 10),
                        score: parseFloat($row.find('.param-score').val()) || 0,
                        is_critical_failed: $row.find('.param-critical-fail').is(':checked') ? 1 : 0,
                        remarks: ($row.find('.param-remarks').val() || '').trim(),
                    };
                }).get();

                const formData = new FormData();
                formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
                formData.append('module', AUDIT_MODULE);
                formData.append('channel', currentChannel);
                formData.append('executive_name', $('#auditExecutive').val().trim());
                formData.append('message_reference', $('#auditMessageRef').val().trim());
                formData.append('audit_date', $('#auditDate').val() || todayIso());
                formData.append('bonus_points', $('#auditBonusPoints').val() || 0);
                formData.append('auditor_notes', $('#auditNotes').val().trim());
                formData.append('critical_failure_reasons', $('#auditCriticalReasonsText').val().trim());
                items.forEach((it, idx) => {
                    formData.append(`items[${idx}][id]`, it.id);
                    formData.append(`items[${idx}][score]`, it.score);
                    formData.append(`items[${idx}][is_critical_failed]`, it.is_critical_failed);
                    formData.append(`items[${idx}][remarks]`, it.remarks);
                });
                const files = $('#auditAttachments')[0].files;
                for (let i = 0; i < files.length; i++) {
                    formData.append('attachments[]', files[i]);
                }

                const $btn = $(this).prop('disabled', true)
                    .html('<span class="spinner-border spinner-border-sm me-1"></span> Saving...');

                $.ajax({
                    url: ROUTES.store,
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                }).done(function (res) {
                    if (!res || !res.success) {
                        alert(res && res.message ? res.message : 'Failed to save audit.');
                        return;
                    }

                    // Reflect on the row in the table
                    const nowIso = new Date().toISOString();
                    const savedGrade = (res.audit && res.audit.grade) || null;
                    const savedGradeColor = savedGrade
                        ? ((currentGrades.find(g => g.grade === savedGrade) || {}).color
                            || GRADE_FALLBACK_COLORS[savedGrade]
                            || null)
                        : null;
                    // Mirror the controller: collect per-parameter remarks as
                    // structured items + capture auditor notes / critical reasons.
                    const savedItems = $('#auditParamsContainer .audit-param-row').map(function () {
                        const $row = $(this);
                        const text = ($row.find('.param-remarks').val() || '').trim();
                        if (!text) return null;
                        const paramId = parseInt($row.data('param-id'), 10);
                        const param   = currentParams.find(p => p.id === paramId) || {};
                        const label   = param.label
                            || ($row.find('.param-label').contents()
                                .filter(function () { return this.nodeType === 3; })
                                .text() || '').trim();
                        const max = parseFloat($row.data('max')) || 0;
                        const score = parseFloat($row.find('.param-score').val()) || 0;
                        return {
                            label:    label,
                            category: param.category || $row.data('category') || 'core_qa',
                            score:    score,
                            max_score: max,
                            critical: $row.find('.param-critical-fail').is(':checked'),
                            remarks:  text,
                        };
                    }).get().filter(Boolean);
                    const savedNotes   = ($('#auditNotes').val() || '').trim() || null;
                    const savedReasons = ($('#auditCriticalReasonsText').val() || '').trim() || null;
                    const savedCount   = savedItems.length
                        + (savedNotes ? 1 : 0)
                        + (savedReasons ? 1 : 0);
                    // Current auditor — used to refresh the Last Audited cell
                    // without a full page reload.
                    const currentAuditorName = @json(trim((string) (auth()->user()->name
                        ?? (auth()->user()->email ?? ''))));
                    table.getRows().forEach(r => {
                        if (r.getData().channel === currentChannel) {
                            r.update({
                                last_audited: new Date().toLocaleDateString(),
                                last_audited_at: nowIso, // <-- triggers Audit column to re-render in green
                                last_audited_full: nowIso,
                                last_auditor_name: currentAuditorName || null,
                                last_grade: savedGrade,
                                last_grade_color: savedGradeColor,
                                last_remarks_items: savedItems,
                                last_auditor_notes: savedNotes,
                                last_critical_reasons: savedReasons,
                                last_remarks_count: savedCount,
                            });
                        }
                    });

                    // Refresh the top KPI panel so the new audit shows up immediately
                    loadAgentKpis();

                    bootstrap.Modal.getInstance(document.getElementById('auditChecklistModal')).hide();
                }).fail(function (xhr) {
                    let msg = 'Failed to save audit.';
                    if (xhr.responseJSON) {
                        if (xhr.responseJSON.message) msg = xhr.responseJSON.message;
                        if (xhr.responseJSON.errors) {
                            msg += '\n' + Object.values(xhr.responseJSON.errors).flat().join('\n');
                        }
                    }
                    alert(msg);
                }).always(function () {
                    $btn.prop('disabled', false).html('<i class="ri-save-line me-1"></i> Save Audit');
                });
            });

            // ============================================================
            //  Parameter Editor (admin only) — add / edit / archive
            // ============================================================
            const $paramEditorModal = $('#paramEditorModal');
            let editingParamId = null;

            function openParamEditor(opts) {
                if (!isAuditAdmin) return;
                const mode     = opts.mode;
                const category = opts.category;
                const param    = opts.param;

                editingParamId = (mode === 'edit' && param) ? param.id : null;
                $('#paramEditorTitle').text(mode === 'edit' ? 'Edit Audit Parameter' : 'Add Audit Parameter');
                $('#paramEditorId').val(editingParamId || '');
                $('#paramEditorModule').val(AUDIT_MODULE);

                if (mode === 'edit' && param) {
                    $('#paramEditorLabel').val(param.label || '');
                    $('#paramEditorDescription').val(param.description || '');
                    $('#paramEditorCategory').val(param.category || 'core_qa');
                    $('#paramEditorMaxScore').val(param.max_score != null ? param.max_score : 10);
                    $('#paramEditorWeight').val(param.weight != null ? param.weight : 1);
                    $('#paramEditorSort').val(param.sort_order != null ? param.sort_order : '');
                    $('#paramEditorCritical').prop('checked', !!param.is_critical);
                    $('#paramEditorActive').prop('checked', param.is_active !== false);
                    $('#paramEditorCode').val(param.code || '');
                } else {
                    $('#paramEditorLabel').val('');
                    $('#paramEditorDescription').val('');
                    $('#paramEditorCategory').val(category || 'core_qa');
                    $('#paramEditorMaxScore').val(10);
                    $('#paramEditorWeight').val(1);
                    $('#paramEditorSort').val('');
                    $('#paramEditorCritical').prop('checked', false);
                    $('#paramEditorActive').prop('checked', true);
                    $('#paramEditorCode').val('');
                }

                bootstrap.Modal.getOrCreateInstance($paramEditorModal[0]).show();
            }

            // Open editor — Add (per category)
            $(document).on('click', '.add-param-btn', function () {
                const cat = $(this).data('category') || 'core_qa';
                openParamEditor({ mode: 'add', category: cat });
            });

            // Open editor — Edit (per row)
            $(document).on('click', '.edit-param-btn', function () {
                const id = parseInt($(this).data('param-id'), 10);
                const param = currentParams.find(p => p.id === id);
                if (!param) return;
                openParamEditor({ mode: 'edit', param: param });
            });

            // Archive (soft delete)
            $(document).on('click', '.archive-param-btn', function () {
                const id = parseInt($(this).data('param-id'), 10);
                const param = currentParams.find(p => p.id === id);
                if (!param) return;
                if (!confirm(`Archive parameter "${param.label}"?\n\nIt will be hidden from new audits, but historical audit records keep their snapshot.`)) return;

                $.ajax({
                    url: ROUTES.paramDestroy + '/' + id,
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                }).done(function (res) {
                    if (!res || !res.success) {
                        alert((res && res.message) || 'Failed to archive parameter.');
                        return;
                    }
                    if (currentChannel) loadAuditConfig(currentChannel);
                }).fail(function (xhr) {
                    alert((xhr.responseJSON && xhr.responseJSON.message) || 'Failed to archive parameter.');
                });
            });

            // Save (add or update)
            $('#paramEditorSaveBtn').on('click', function () {
                const label = ($('#paramEditorLabel').val() || '').trim();
                if (!label) {
                    alert('Label is required.');
                    $('#paramEditorLabel').trigger('focus');
                    return;
                }

                const code = ($('#paramEditorCode').val() || '').trim().toLowerCase();
                if (code && !/^[a-z0-9_]+$/.test(code)) {
                    alert('Code may only contain lowercase letters, digits and underscores.');
                    $('#paramEditorCode').trigger('focus');
                    return;
                }

                const payload = {
                    module:      $('#paramEditorModule').val(),
                    code:        code,
                    label:       label,
                    description: $('#paramEditorDescription').val().trim(),
                    category:    $('#paramEditorCategory').val(),
                    max_score:   parseInt($('#paramEditorMaxScore').val(), 10) || 10,
                    weight:      parseFloat($('#paramEditorWeight').val()) || 1,
                    is_critical: $('#paramEditorCritical').is(':checked') ? 1 : 0,
                    is_active:   $('#paramEditorActive').is(':checked') ? 1 : 0,
                };
                const sortVal = $('#paramEditorSort').val();
                if (sortVal !== '') payload.sort_order = parseInt(sortVal, 10) || 0;
                if (!payload.code) delete payload.code;

                const isEdit = !!editingParamId;
                const url    = isEdit
                    ? ROUTES.paramUpdate + '/' + editingParamId
                    : ROUTES.paramStore;
                const method = isEdit ? 'PUT' : 'POST';

                const $btn = $(this).prop('disabled', true)
                    .html('<span class="spinner-border spinner-border-sm me-1"></span> Saving...');

                $.ajax({
                    url: url,
                    method: method,
                    data: payload,
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                }).done(function (res) {
                    if (!res || !res.success) {
                        alert((res && res.message) || 'Failed to save parameter.');
                        return;
                    }
                    bootstrap.Modal.getInstance($paramEditorModal[0]).hide();
                    if (currentChannel) loadAuditConfig(currentChannel);
                }).fail(function (xhr) {
                    let msg = 'Failed to save parameter.';
                    if (xhr.responseJSON) {
                        if (xhr.responseJSON.message) msg = xhr.responseJSON.message;
                        if (xhr.responseJSON.errors) {
                            msg += '\n' + Object.values(xhr.responseJSON.errors).flat().join('\n');
                        }
                    }
                    alert(msg);
                }).always(function () {
                    $btn.prop('disabled', false).html('<i class="ri-save-line me-1"></i> Save Parameter');
                });
            });

            // ============================================================
            //  Agent-wise KPI panel (top of page)
            // ============================================================
            let kpiAgents       = [];          // last fetched agents data
            let kpiSelectedName = null;        // currently focused agent
            let agentChart      = null;        // Highcharts instance

            // Fixed palette so each agent keeps the same color across renders
            const KPI_COLORS = [
                '#1976d2', '#2e7d32', '#ef6c00', '#6a1b9a', '#c2185b',
                '#00838f', '#5d4037', '#455a64', '#9e9d24', '#d81b60',
                '#0d47a1', '#1b5e20', '#bf360c', '#4a148c', '#880e4f',
            ];
            function colorForIndex(i) {
                return KPI_COLORS[i % KPI_COLORS.length];
            }

            function gradeColor(grade) {
                if (typeof currentGrades !== 'undefined' && currentGrades.length) {
                    const g = currentGrades.find(x => x.grade === grade);
                    if (g && g.color) return g.color;
                }
                const fallback = { 'A+': '#198754', 'A': '#28a745', 'B': '#0d6efd', 'C': '#ffc107', 'D': '#fd7e14', 'F': '#dc3545' };
                return fallback[grade] || '#6c757d';
            }

            function escapeHtmlKpi(s) {
                return String(s ?? '')
                    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
            }

            function renderKpiSummary(summary) {
                $('#kpiTotalAgents').text(summary.total_agents ?? 0);
                $('#kpiTotalAudits').text(summary.total_audits ?? 0);
                $('#kpiAvgScore').text((summary.avg_score ?? 0).toFixed
                    ? Number(summary.avg_score).toFixed(1)
                    : (summary.avg_score ?? 0));
                $('#kpiCriticalFailures').text(summary.critical_failures ?? 0);
                $('#kpiWindowLabel').text('last ' + (summary.window_days ?? 90) + ' days');
            }

            function renderKpiAgentTable() {
                const $tbody = $('#kpiAgentTableBody').empty();
                if (!kpiAgents.length) {
                    $tbody.html('<tr><td colspan="6" class="text-center text-muted py-3">No audits in this window yet.</td></tr>');
                    return;
                }
                kpiAgents.forEach((a, idx) => {
                    const dot = `<span class="d-inline-block me-1" style="width:8px;height:8px;border-radius:50%;background:${colorForIndex(idx)}"></span>`;
                    const grade = a.last_grade || '-';
                    const $tr = $(`
                        <tr data-agent="${escapeHtmlKpi(a.name)}" class="${a.name === kpiSelectedName ? 'is-active' : ''}">
                            <td>${dot}<strong>${escapeHtmlKpi(a.name)}</strong></td>
                            <td class="text-center">${a.total_audits}</td>
                            <td class="text-end">${Number(a.avg_score).toFixed(1)}</td>
                            <td class="text-end">${a.last_score !== null ? Number(a.last_score).toFixed(1) : '-'}</td>
                            <td class="text-center">
                                <span class="agent-grade" style="background:${gradeColor(grade)}">${grade}</span>
                            </td>
                            <td class="text-center">${a.critical_failures > 0 ? `<span class="text-danger fw-bold">${a.critical_failures}</span>` : '-'}</td>
                        </tr>
                    `);
                    $tbody.append($tr);
                });
            }

            function renderAgentScoreChart() {
                if (typeof Highcharts === 'undefined') return;

                if (!kpiAgents.length) {
                    $('#agentScoreEmpty').removeClass('d-none');
                    $('#agentScoreChart').hide();
                    if (agentChart) { agentChart.destroy(); agentChart = null; }
                    return;
                }
                $('#agentScoreEmpty').addClass('d-none');
                $('#agentScoreChart').show();

                const series = kpiAgents.map((a, idx) => ({
                    name: a.name,
                    color: colorForIndex(idx),
                    visible: kpiSelectedName ? a.name === kpiSelectedName : true,
                    data: (a.history || []).map(h => ({
                        x: h.date ? Date.parse(h.date) : (h.created_at ? Date.parse(h.created_at) : Date.now()),
                        y: Number(h.score),
                        grade: h.grade,
                        critical: h.critical,
                        channel: h.channel,
                        remarks: h.remarks,
                        reference: h.reference,
                    })),
                }));

                if (agentChart) { agentChart.destroy(); agentChart = null; }
                agentChart = Highcharts.chart('agentScoreChart', {
                    chart: { type: 'spline', height: 280, zoomType: 'x' },
                    title: { text: null },
                    xAxis: {
                        type: 'datetime',
                        title: { text: null },
                        labels: { style: { fontSize: '11px' } },
                    },
                    yAxis: {
                        title: { text: 'Total Score' },
                        min: 0,
                        max: 120,
                        plotBands: [
                            { from: 0,   to: 60,  color: 'rgba(220, 53, 69, 0.05)', label: { text: 'F',  style: { color: '#dc3545' } } },
                            { from: 60,  to: 75,  color: 'rgba(253, 126, 20, 0.05)' },
                            { from: 75,  to: 90,  color: 'rgba(255, 193, 7, 0.05)' },
                            { from: 90,  to: 100, color: 'rgba(13, 110, 253, 0.05)' },
                            { from: 100, to: 120, color: 'rgba(40, 167, 69, 0.06)' },
                        ],
                    },
                    legend: {
                        enabled: kpiAgents.length > 1,
                        itemStyle: { fontSize: '11px' },
                    },
                    credits: { enabled: false },
                    tooltip: {
                        useHTML: true,
                        formatter: function () {
                            const p = this.point;
                            const dateStr = Highcharts.dateFormat('%e %b %Y', this.x);
                            const grade = p.grade ? `<span style="background:${gradeColor(p.grade)};color:#fff;border-radius:8px;padding:1px 6px;font-size:10px;font-weight:700;">${p.grade}</span>` : '';
                            const crit = p.critical ? '<span style="color:#dc3545;font-weight:700;">⚠ Critical</span><br>' : '';
                            const ch = p.channel ? `<div style="color:#6c757d;font-size:11px;">${escapeHtmlKpi(p.channel)}</div>` : '';
                            const rk = p.remarks ? `<div style="margin-top:4px;font-size:11.5px;color:#212529;max-width:240px;white-space:normal;"><em>${escapeHtmlKpi(p.remarks).slice(0,160)}${p.remarks.length>160?'…':''}</em></div>` : '';
                            return `<strong>${escapeHtmlKpi(this.series.name)}</strong> ${grade}<br>${ch}${crit}<b>${Number(this.y).toFixed(1)}</b> on ${dateStr}${rk}`;
                        }
                    },
                    plotOptions: {
                        spline: {
                            marker: { enabled: true, radius: 3 },
                            lineWidth: 2,
                        },
                        series: {
                            cursor: 'pointer',
                            point: {
                                events: {
                                    click: function () {
                                        kpiSelectedName = this.series.name;
                                        renderKpiAgentTable();
                                        renderRemarksFor(kpiSelectedName);
                                    }
                                }
                            }
                        }
                    },
                    series: series,
                });
            }

            function renderRemarksFor(agentName) {
                const $list = $('#kpiRemarksList').empty();
                $('#kpiRemarksAgentLabel').text(agentName ? '· ' + agentName : '');
                if (!agentName) {
                    $('#kpiRemarksCount').text(0);
                    $list.html('<div class="text-center text-muted py-3">Select an agent to see remarks.</div>');
                    return;
                }
                const agent = kpiAgents.find(a => a.name === agentName);
                if (!agent) {
                    $('#kpiRemarksCount').text(0);
                    $list.html('<div class="text-center text-muted py-3">No remarks available.</div>');
                    return;
                }
                // Sort newest first
                const items = (agent.history || []).slice().sort((a, b) => {
                    const da = Date.parse(a.date || a.created_at || 0);
                    const db = Date.parse(b.date || b.created_at || 0);
                    return db - da;
                });
                const remarkItems = items.filter(h => (h.remarks && h.remarks.trim()) || h.critical);
                $('#kpiRemarksCount').text(remarkItems.length);

                if (!remarkItems.length) {
                    $list.html('<div class="text-center text-muted py-3">No auditor notes recorded for this agent.</div>');
                    return;
                }

                remarkItems.forEach(h => {
                    const dateStr = h.date || (h.created_at || '').substring(0, 10);
                    const grade = h.grade
                        ? `<span class="agent-grade ms-1" style="background:${gradeColor(h.grade)}">${h.grade}</span>`
                        : '';
                    const critTag = h.critical ? '<span class="critical-flag"><i class="ri-error-warning-fill"></i> Critical</span>' : '';
                    const refLine = h.reference
                        ? `<div class="meta">Ref: ${escapeHtmlKpi(h.reference)}</div>`
                        : '';
                    const txt = (h.remarks && h.remarks.trim()) || (h.critical_reasons || '').trim();
                    $list.append(`
                        <div class="kpi-remark-item">
                            <div class="meta">
                                <strong>${escapeHtmlKpi(dateStr)}</strong>
                                · ${escapeHtmlKpi(h.channel || '')}
                                · Score <strong>${Number(h.score).toFixed(1)}</strong>${grade}
                                ${critTag}
                            </div>
                            ${refLine}
                            <div class="text">${escapeHtmlKpi(txt || '(no notes — critical failure flagged)')}</div>
                        </div>
                    `);
                });
            }

            function loadAgentKpis() {
                const days = parseInt($('#kpiWindow').val(), 10) || 90;
                return $.get(ROUTES.agentKpis, { module: AUDIT_MODULE, days })
                    .then(res => {
                        kpiAgents = (res.agents || []);
                        renderKpiSummary(res.summary || {});
                        renderKpiAgentTable();
                        renderAgentScoreChart();
                        // Keep the previously-selected agent if it still exists, else clear
                        if (kpiSelectedName && !kpiAgents.find(a => a.name === kpiSelectedName)) {
                            kpiSelectedName = null;
                        }
                        renderRemarksFor(kpiSelectedName);
                    })
                    .catch(() => {
                        $('#kpiAgentTableBody').html('<tr><td colspan="6" class="text-center text-danger py-3">Failed to load KPI data.</td></tr>');
                    });
            }

            // Click an agent row to focus the chart + remarks on that agent
            $(document).on('click', '#kpiAgentTableBody tr[data-agent]', function () {
                const name = $(this).data('agent');
                if (kpiSelectedName === name) {
                    kpiSelectedName = null; // toggle off → show all
                } else {
                    kpiSelectedName = name;
                }
                renderKpiAgentTable();
                renderAgentScoreChart();
                renderRemarksFor(kpiSelectedName);
            });

            $('#kpiWindow').on('change', loadAgentKpis);
            $('#kpiRefreshBtn').on('click', loadAgentKpis);

            // Initial load
            loadAgentKpis();

            /* ============================================================
             *  SOP (Standard Operating Procedure) modal wiring
             *  ----------------------------------------------------------
             *  - Click on #sopOpenBtn               -> open in VIEW mode (everyone)
             *  - Double-click on #sopOpenBtn        -> open in EDIT mode (SOP admins only)
             *  - #sopEditBtn / #sopCancelBtn        -> in-modal mode switch
             *  - #sopSaveBtn                        -> POST html to backend
             *  The "can edit" flag is set server-side via data-can-edit on
             *  the button so an unauthorised paste cannot succeed even if
             *  the DOM is tampered with — the controller re-checks the
             *  current user's email against SOP_ADMIN_EMAILS on save.
             * ============================================================ */
            (function initSopModal() {
                const $openBtn   = $('#sopOpenBtn');
                if (!$openBtn.length) return;

                const sopKey     = $openBtn.data('sop-key') || 'cc_messages';
                const canEdit    = String($openBtn.data('can-edit')) === '1';

                const sopGetUrl  = "{{ route('audit.master.sop.get') }}";
                const sopSaveUrl = "{{ route('audit.master.sop.save') }}";
                const csrfToken  = $('meta[name="csrf-token"]').attr('content');

                const $modalEl   = $('#sopModal');
                if (!$modalEl.length) return;

                const sopModal   = new bootstrap.Modal($modalEl[0]);
                const $viewer    = $('#sopViewer');
                const $editor    = $('#sopEditor');
                const $editBtn   = $('#sopEditBtn');
                const $cancelBtn = $('#sopCancelBtn');
                const $saveBtn   = $('#sopSaveBtn');
                const $adminPill = $('#sopAdminBadge');
                const $hint      = $('#sopModeHint');
                const $status    = $('#sopStatus');

                let currentHtml  = '';      // last server-confirmed HTML
                let isEditing    = false;   // current modal mode
                let isLoading    = false;
                let dblClickArmed = false;  // suppress single-click when a dblclick fires

                function setStatus(msg, kind) {
                    $status
                        .removeClass('is-error is-success')
                        .text(msg || '');
                    if (kind === 'error')   $status.addClass('is-error');
                    if (kind === 'success') $status.addClass('is-success');
                }

                function setHintForMode() {
                    if (isEditing) {
                        $hint.html('<i class="ri-edit-line me-1"></i> <strong>Edit mode.</strong> Paste the SOP <strong>Markdown</strong> (or HTML) copied from ChatGPT below, then click <em>Save SOP</em>. The viewer will render it as a formatted document. Only <code>president@5core.com</code> and <code>software5@5core.com</code> can save.');
                    } else {
                        $hint.html('<i class="ri-eye-line me-1"></i> Read-only view. ' + (canEdit
                            ? 'Double-click the <strong>SOP</strong> button (or click <em>Edit</em>) to update.'
                            : 'Contact an admin to update this SOP.'));
                    }
                }

                function showViewer() {
                    isEditing = false;
                    $viewer.removeClass('d-none');
                    $editor.addClass('d-none');
                    $editBtn.removeClass('d-none');
                    $cancelBtn.addClass('d-none');
                    $saveBtn.addClass('d-none');
                    $adminPill.addClass('d-none');
                    setHintForMode();
                }

                function showEditor() {
                    if (!canEdit) {
                        setStatus('You are not authorized to edit the SOP.', 'error');
                        return;
                    }
                    isEditing = true;
                    $editor.val(currentHtml);
                    $viewer.addClass('d-none');
                    $editor.removeClass('d-none');
                    $editBtn.addClass('d-none');
                    $cancelBtn.removeClass('d-none');
                    $saveBtn.removeClass('d-none');
                    $adminPill.removeClass('d-none');
                    setHintForMode();
                    setTimeout(() => $editor.trigger('focus'), 50);
                }

                // Heuristic: pasted ChatGPT content is usually Markdown
                // (lines starting with "#", "![Image](...)", "- ", etc.),
                // but admins can also paste raw HTML. We detect which one
                // it is and render it as a proper formatted document with
                // real images / headings / tables — never as raw code.
                function looksLikeHtml(text) {
                    if (!text) return false;
                    const t = text.trim();
                    if (!t) return false;
                    // Strong HTML signals: a tag in the first ~200 chars and
                    // a matching closing tag somewhere later.
                    if (/^<!doctype\s+html/i.test(t)) return true;
                    if (/^<(html|body|div|section|article|h[1-6]|p|table|ul|ol|img|figure)\b/i.test(t)) return true;
                    // Mixed: starts with text but contains <tag>...</tag>
                    const open  = (t.match(/<[a-z][a-z0-9]*\b[^>]*>/gi) || []).length;
                    const close = (t.match(/<\/[a-z][a-z0-9]*\s*>/gi) || []).length;
                    return open >= 3 && close >= 2;
                }

                function renderHtmlIntoViewer(raw) {
                    currentHtml = raw || '';

                    let html;
                    if (looksLikeHtml(currentHtml)) {
                        html = currentHtml;
                    } else if (window.marked) {
                        // Render Markdown -> HTML. GitHub-flavoured by default
                        // (tables, task lists, fenced code) and line breaks
                        // preserved so single line breaks behave naturally.
                        try {
                            marked.use({ gfm: true, breaks: true });
                            html = marked.parse(currentHtml);
                        } catch (e) {
                            html = '<pre>' + escapeHtml(currentHtml) + '</pre>';
                        }
                    } else {
                        // Markdown lib failed to load — show as preformatted
                        // text so the auditor at least sees the content.
                        html = '<pre>' + escapeHtml(currentHtml) + '</pre>';
                    }

                    // Sanitize before injection. Server-side only SOP admins
                    // can write, but stripping <script>/event handlers gives
                    // a second layer of safety.
                    if (window.DOMPurify) {
                        html = DOMPurify.sanitize(html, {
                            USE_PROFILES: { html: true },
                            ADD_ATTR: ['target'],
                        });
                    }

                    $viewer.html(html);

                    // Make every link safe + open in new tab (common for
                    // "SOP Test Link" style references).
                    $viewer.find('a[href]').each(function () {
                        this.setAttribute('target', '_blank');
                        this.setAttribute('rel', 'noopener noreferrer');
                    });

                    // Add tooltip + accessibility hint on the thumbnails.
                    // The actual click->lightbox is delegated below so it
                    // also works after a re-render.
                    $viewer.find('img').each(function () {
                        if (!this.getAttribute('title')) {
                            this.setAttribute('title', 'Click to enlarge');
                        }
                        if (!this.getAttribute('alt')) {
                            this.setAttribute('alt', 'SOP image');
                        }
                        this.setAttribute('loading', 'lazy');
                    });

                    // Group runs of consecutive image-only paragraphs into a
                    // responsive grid so images fill the full viewer width
                    // (instead of stacking down the left edge).
                    groupImagesIntoGallery($viewer[0]);
                }

                // Treat a node as "image-only" if it is a <p> that contains
                // only <img> elements (and whitespace / <br>). Markdown turns
                // each `![Image](url)` line into its own such paragraph, so a
                // long block of inline images becomes a vertical column.
                function isImageOnlyParagraph(node) {
                    if (!node || node.nodeType !== 1) return false;
                    if (node.tagName !== 'P') return false;
                    if (node.querySelector('img') === null) return false;
                    // Reject if there is any visible text or any non-img/br tag.
                    for (const child of node.childNodes) {
                        if (child.nodeType === 3) {
                            if (child.textContent.trim() !== '') return false;
                        } else if (child.nodeType === 1) {
                            const tag = child.tagName.toLowerCase();
                            if (tag !== 'img' && tag !== 'br') return false;
                        }
                    }
                    return true;
                }

                function groupImagesIntoGallery(root) {
                    if (!root) return;
                    const children = Array.from(root.children);
                    let i = 0;
                    while (i < children.length) {
                        const start = children[i];
                        if (!isImageOnlyParagraph(start)) { i++; continue; }
                        let j = i;
                        const group = [];
                        while (j < children.length && isImageOnlyParagraph(children[j])) {
                            group.push(children[j]);
                            j++;
                        }
                        // Need at least two consecutive image paragraphs OR
                        // one paragraph with multiple images to benefit from
                        // a grid layout.
                        const totalImgs = group.reduce((n, p) => n + p.querySelectorAll('img').length, 0);
                        if (group.length >= 2 || totalImgs >= 2) {
                            const gallery = document.createElement('div');
                            gallery.className = 'sop-gallery';
                            group.forEach(p => {
                                p.querySelectorAll('img').forEach(img => gallery.appendChild(img));
                            });
                            root.insertBefore(gallery, group[0]);
                            group.forEach(p => p.remove());
                        }
                        i = j;
                    }
                }

                // Click a thumbnail in the SOP viewer -> open full-size in a
                // lightweight lightbox. Click backdrop or press Esc to close.
                function openSopLightbox(src, alt) {
                    closeSopLightbox(); // make sure only one is ever open
                    const $backdrop = $('<div class="sop-lightbox-backdrop" role="dialog" aria-modal="true"></div>');
                    const $closeBtn = $('<button type="button" class="sop-lightbox-close" aria-label="Close">&times;</button>');
                    const $img = $('<img>').attr('src', src).attr('alt', alt || 'SOP image');
                    $backdrop.append($closeBtn).append($img);
                    $('body').append($backdrop);
                    $backdrop.on('click', function (ev) {
                        // Close on backdrop click but ignore clicks on the
                        // image itself so the user can right-click / save it.
                        if (ev.target === $backdrop[0] || ev.target === $closeBtn[0]) {
                            closeSopLightbox();
                        }
                    });
                    $(document).on('keydown.sopLb', function (ev) {
                        if (ev.key === 'Escape') closeSopLightbox();
                    });
                }
                function closeSopLightbox() {
                    $('.sop-lightbox-backdrop').remove();
                    $(document).off('keydown.sopLb');
                }
                $(document).on('click', '#sopViewer img', function (ev) {
                    ev.preventDefault();
                    const src = this.getAttribute('src');
                    if (!src) return;
                    openSopLightbox(src, this.getAttribute('alt'));
                });

                function loadSop(forceEdit) {
                    if (isLoading) return;
                    isLoading = true;
                    setStatus('Loading SOP…');
                    $.ajax({
                        url: sopGetUrl,
                        method: 'GET',
                        data: { key: sopKey },
                        dataType: 'json',
                    }).done(function (res) {
                        if (res && res.success) {
                            renderHtmlIntoViewer(res.html || '');
                            setStatus('');
                            if (forceEdit && canEdit) {
                                showEditor();
                            } else {
                                showViewer();
                            }
                        } else {
                            setStatus((res && res.message) || 'Failed to load SOP.', 'error');
                            showViewer();
                        }
                    }).fail(function (xhr) {
                        const msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Failed to load SOP.';
                        setStatus(msg, 'error');
                        showViewer();
                    }).always(function () {
                        isLoading = false;
                    });
                }

                function saveSop() {
                    if (!canEdit) {
                        setStatus('You are not authorized to edit the SOP.', 'error');
                        return;
                    }
                    const newHtml = $editor.val();
                    setStatus('Saving…');
                    $saveBtn.prop('disabled', true);
                    $.ajax({
                        url: sopSaveUrl,
                        method: 'POST',
                        data: { key: sopKey, html: newHtml, _token: csrfToken },
                        dataType: 'json',
                    }).done(function (res) {
                        if (res && res.success) {
                            renderHtmlIntoViewer(newHtml);
                            showViewer();
                            setStatus('Saved.', 'success');
                            setTimeout(() => setStatus(''), 2500);
                        } else {
                            setStatus((res && res.message) || 'Failed to save SOP.', 'error');
                        }
                    }).fail(function (xhr) {
                        const msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Failed to save SOP.';
                        setStatus(msg, 'error');
                    }).always(function () {
                        $saveBtn.prop('disabled', false);
                    });
                }

                // Server-rendered HTML may already be present in the blade
                // template (so the modal can open instantly without a round-
                // trip). Seed it here.
                renderHtmlIntoViewer(@json($sopHtml ?? ''));

                // Single click vs double-click on the SOP icon. We delay the
                // single-click handler slightly so a dblclick can cancel it.
                let clickTimer = null;
                $openBtn.on('click', function (e) {
                    e.preventDefault();
                    if (dblClickArmed) return; // dblclick will handle it
                    clearTimeout(clickTimer);
                    clickTimer = setTimeout(function () {
                        if (dblClickArmed) { dblClickArmed = false; return; }
                        sopModal.show();
                        loadSop(false);
                    }, 220);
                });

                $openBtn.on('dblclick', function (e) {
                    e.preventDefault();
                    dblClickArmed = true;
                    clearTimeout(clickTimer);
                    setTimeout(() => { dblClickArmed = false; }, 400);

                    if (!canEdit) {
                        // Non-admins falling through to a dblclick still get
                        // the read-only modal, but with a clear hint.
                        sopModal.show();
                        loadSop(false);
                        setStatus('Only president@5core.com and software5@5core.com can edit the SOP.', 'error');
                        return;
                    }
                    sopModal.show();
                    loadSop(true);
                });

                // In-modal buttons (only present for SOP admins).
                $editBtn.on('click', function () { showEditor(); });
                $cancelBtn.on('click', function () {
                    $editor.val(currentHtml);
                    showViewer();
                    setStatus('');
                });
                $saveBtn.on('click', saveSop);

                // Reset to view mode every time the modal is fully closed so
                // the next open starts clean.
                $modalEl.on('hidden.bs.modal', function () {
                    showViewer();
                    setStatus('');
                });
            })();
        });
    </script>
@endsection
