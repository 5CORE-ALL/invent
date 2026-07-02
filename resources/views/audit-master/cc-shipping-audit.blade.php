@extends('layouts.vertical', ['title' => 'CC Shipping Audit', 'sidenav' => 'condensed'])

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
        .audit-btn-yellow img {
            filter: hue-rotate(35deg) saturate(1.35) brightness(1.05)
                drop-shadow(0 1px 2px rgba(180, 130, 0, 0.35));
        }
        .audit-btn-yellow:hover img {
            filter: hue-rotate(35deg) saturate(1.35) brightness(1.05)
                drop-shadow(0 2px 4px rgba(180, 130, 0, 0.55));
        }

        /* Shipping Health column button (ship icon — green / yellow) */
        .sh-health-btn {
            background: transparent;
            border: 0;
            padding: 0;
            cursor: pointer;
            line-height: 0;
            transition: transform 0.15s ease, filter 0.15s ease;
        }
        .sh-health-btn img {
            width: 32px;
            height: 32px;
            object-fit: contain;
        }
        .sh-health-btn.sh-yellow img {
            filter: drop-shadow(0 1px 2px rgba(180, 130, 0, 0.35));
        }
        .sh-health-btn.sh-yellow:hover img {
            transform: scale(1.08);
            filter: drop-shadow(0 2px 4px rgba(180, 130, 0, 0.55));
        }
        .sh-health-btn.sh-green img {
            filter: drop-shadow(0 1px 2px rgba(27, 94, 32, 0.30));
        }
        .sh-health-btn.sh-green:hover img {
            transform: scale(1.08);
            filter: drop-shadow(0 2px 4px rgba(27, 94, 32, 0.55));
        }

        /* Shipping Health modal */
        #shippingHealthModal .modal-header {
            background: linear-gradient(135deg, #00897b 0%, #00695c 100%);
            color: #fff;
        }
        #shippingHealthModal .sh-channel-pill {
            background: rgba(255, 255, 255, 0.2);
            color: #fff;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 8px;
        }
        .sh-param-table th {
            font-size: 12px;
            background: #f8f9fa;
        }
        .sh-param-table td {
            font-size: 12.5px;
            vertical-align: middle;
        }
        .sh-required-text {
            font-size: 12.5px;
            font-weight: 600;
            color: #495057;
        }
        .sh-percent-input {
            max-width: 110px;
        }
        .sh-score-card {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 10px 14px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            background: #f8f9fa;
            margin-bottom: 12px;
        }
        .sh-score-ring {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 14px;
            color: #fff;
            flex-shrink: 0;
        }

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
            grid-template-columns: 1fr 130px 1fr;
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

        /* Param editor (nested) modal — must sit above the parent modal */
        #paramEditorModal,
        #shParamEditorModal { z-index: 1080 !important; }
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

        /* Avg shipping health badge (page header) */
        .avg-health-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 700;
            border: 1px solid transparent;
            line-height: 1.2;
        }
        .avg-health-badge .avg-health-value {
            font-size: 15px;
        }
        .avg-health-badge .avg-health-meta {
            font-size: 11px;
            font-weight: 500;
            opacity: 0.85;
        }
        .avg-health-badge.sh-green  { background: #d1e7dd; color: #0f5132; border-color: #a3cfbb; }
        .avg-health-badge.sh-yellow { background: #fff3cd; color: #664d03; border-color: #ffe69c; }
        .avg-health-badge.sh-red    { background: #f8d7da; color: #842029; border-color: #f1aeb5; }
        .avg-health-badge.sh-gray   { background: #e9ecef; color: #495057; border-color: #dee2e6; }
    </style>
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center flex-wrap gap-3">
                    <h4 class="page-title mb-0">
                        <i class="ri-truck-line me-2 text-primary"></i>CC Shipping Audit
                    </h4>
                    <span class="avg-health-badge sh-{{ $avgShippingHealth !== null ? ($avgShippingHealth >= 80 ? 'green' : ($avgShippingHealth >= 50 ? 'yellow' : 'red')) : 'gray' }}" id="avgHealthBadge"
                        title="Average of latest shipping health % across all platforms">
                        <i class="ri-heart-pulse-line"></i>
                        <span>Avg Health:</span>
                        <span class="avg-health-value" id="avgHealthBadgeValue">{{ $avgShippingHealth !== null ? $avgShippingHealth . '%' : '—' }}</span>
                        <span class="avg-health-meta" id="avgHealthBadgeMeta">
                            @if($avgShippingHealthCount > 0)
                                {{ $avgShippingHealthCount }} of {{ $totalChannelCount }} platforms assessed
                            @else
                                No assessments yet
                            @endif
                        </span>
                    </span>
                </div>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="javascript:void(0);">Audit Master</a></li>
                        <li class="breadcrumb-item active">CC Shipping Audit</li>
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
                    <div id="ccShippingAuditTable"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- ================== AUDIT MODAL (CC Shipping) ================== --}}
    <div class="modal fade" id="auditChecklistModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title d-flex align-items-center flex-wrap gap-2">
                        <img src="{{ asset('images/audit-button-blue.png') }}" alt="Audit"
                            style="width: 32px; height: 32px;">
                        <span>CC Shipping Audit</span>
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
                                    <div class="col-md-8">
                                        <label class="form-label small fw-semibold mb-1">Shipping Executive</label>
                                        <input type="text" class="form-control form-control-sm" id="auditExecutive"
                                            placeholder="Executive who generated the label" maxlength="191">
                                    </div>
                                    <div class="col-md-4">
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

    {{-- ============ SHIPPING HEALTH MODAL ============ --}}
    <div class="modal fade" id="shippingHealthModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title d-flex align-items-center flex-wrap gap-2">
                        <i class="ri-heart-pulse-line"></i>
                        <span>Shipping Health</span>
                        <span class="sh-channel-pill" id="shChannelName">Channel</span>
                        <span id="shAdminBadge" class="admin-only-badge d-none">
                            <i class="ri-shield-user-line"></i> Admin Mode
                        </span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <ul class="nav nav-tabs audit-tabs mb-3" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active" id="sh-form-tab" data-bs-toggle="tab"
                                data-bs-target="#sh-form-pane" type="button" role="tab">
                                <i class="ri-edit-line me-1"></i> Current Assessment
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" id="sh-history-tab" data-bs-toggle="tab"
                                data-bs-target="#sh-history-pane" type="button" role="tab">
                                <i class="ri-history-line me-1"></i> History
                                <span class="badge bg-secondary" id="shHistoryCount">0</span>
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="sh-form-pane" role="tabpanel">
                            <div id="shLoading" class="text-center text-muted py-4 d-none">
                                <div class="spinner-border spinner-border-sm me-2"></div> Loading parameters…
                            </div>

                            <div class="sh-score-card">
                                <div class="sh-score-ring" id="shScoreRing">—</div>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold">Health Score</div>
                                    <div class="text-muted small" id="shScoreSummary">Enter current values against required parameters.</div>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-secondary d-none" id="shAddParamBtn">
                                    <i class="ri-add-line"></i> Add Parameter
                                </button>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-sm table-bordered sh-param-table mb-2">
                                    <thead>
                                        <tr>
                                            <th>Parameter</th>
                                            <th style="width:120px;">Required</th>
                                            <th style="width:140px;">Current %</th>
                                            <th style="width:70px;" class="text-center sh-admin-col d-none">Admin</th>
                                        </tr>
                                    </thead>
                                    <tbody id="shParamsBody">
                                        <tr><td colspan="4" class="text-center text-muted py-3">Loading…</td></tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="mt-2">
                                <label class="form-label small fw-semibold mb-1">Notes</label>
                                <textarea id="shNotes" class="form-control form-control-sm" rows="2"
                                    placeholder="Optional notes about this platform's shipping health"></textarea>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="sh-history-pane" role="tabpanel">
                            <div id="shHistoryLoading" class="text-center text-muted py-4 d-none">
                                <div class="spinner-border spinner-border-sm me-2"></div> Loading history…
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-striped audit-history-table mb-0">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Date</th>
                                            <th>User</th>
                                            <th class="text-end">Score</th>
                                            <th class="text-center">Met</th>
                                            <th>Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody id="shHistoryBody">
                                        <tr><td colspan="6" class="text-center text-muted py-3">No assessments yet.</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="saveShippingHealthBtn">
                        <i class="ri-save-line me-1"></i> Save Assessment
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ============ SHIPPING HEALTH PARAMETER EDITOR (admin) ============ --}}
    <div class="modal fade" id="shParamEditorModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background: #e0f2f1; color: #00695c;">
                    <h5 class="modal-title">
                        <i class="ri-tools-line me-1"></i>
                        <span id="shParamEditorTitle">Add Shipping Health Parameter</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="shParamEditorForm">
                        <input type="hidden" id="shParamEditorId">
                        <div class="mb-2">
                            <label class="form-label small fw-semibold mb-1">Label <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="shParamEditorLabel" maxlength="255" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-semibold mb-1">Description</label>
                            <textarea class="form-control form-control-sm" id="shParamEditorDescription" rows="2"></textarea>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-semibold mb-1">Required (text)</label>
                            <input type="text" class="form-control form-control-sm" id="shParamEditorRequired"
                                placeholder="e.g. 100%">
                            <div class="form-text">Target value shown in the Required column (editable here).</div>
                        </div>
                        <input type="hidden" id="shParamEditorType" value="percent">
                        <div class="row g-2 mt-1">
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold mb-1">Sort Order</label>
                                <input type="number" class="form-control form-control-sm" id="shParamEditorSort" min="0" placeholder="auto">
                            </div>
                            <div class="col-md-8 d-flex align-items-end">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="shParamEditorActive" checked>
                                    <label class="form-check-label small" for="shParamEditorActive">Active</label>
                                </div>
                            </div>
                        </div>
                        <div class="small text-danger mt-2 d-none" id="shParamEditorError"></div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-sm btn-primary" id="shParamEditorSaveBtn">
                        <i class="ri-save-line me-1"></i> Save
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
                        <input type="hidden" id="paramEditorModule" value="cc_shipping">

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
@endsection

@section('script-after-vite')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        $(function () {
            const channelsWithLogo = @json($channelsWithLogo);

            // Audit-button image variants. Picked dynamically based on how
            // recently the channel was last audited.
            const AUDIT_BTN = {
                green:  "{{ asset('images/audit-button-green.png') }}",  // audited within 7 days
                yellow: "{{ asset('images/audit-button-red.png') }}",    // default / not yet audited
                red:    "{{ asset('images/audit-button-red.png') }}",    // audited > 7 days ago
            };

            // Returns 'yellow' | 'green' | 'red' for a given last-audited timestamp.
            // Default (never audited) => yellow; <= 7 days => green; > 7 days => red
            function pickAuditColor(lastAuditedAt) {
                if (!lastAuditedAt) return 'yellow';
                const last = new Date(lastAuditedAt);
                if (isNaN(last.getTime())) return 'yellow';
                const days = Math.floor((Date.now() - last.getTime()) / 86400000);
                if (days <= 7) return 'green';
                return 'red';
            }

            // Human-friendly label like "12 days ago" / "Not audited yet"
            function lastAuditedLabel(lastAuditedAt) {
                if (!lastAuditedAt) return 'Not audited yet';
                const last = new Date(lastAuditedAt);
                if (isNaN(last.getTime())) return 'Not audited yet';
                const days = Math.floor((Date.now() - last.getTime()) / 86400000);
                if (days <= 0) return 'Audited today';
                if (days === 1) return 'Audited 1 day ago';
                return `Audited ${days} days ago`;
            }

            const tableData = channelsWithLogo.map((row, index) => ({
                id: index + 1,
                channel_id: row.channel_id || null,
                channel: row.channel,
                logo: row.logo || null,
                last_audited_at: row.last_audited_at || null,
                last_audited: row.last_audited_at
                    ? new Date(row.last_audited_at).toLocaleDateString()
                    : '-',
                shipping_health_score: row.shipping_health_score ?? null,
                shipping_health_at: row.shipping_health_at || null,
            }));

            const SH_HEALTH_BTN = {
                green:  "{{ asset('images/shipping-health-button-green.svg') }}",
                yellow: "{{ asset('images/shipping-health-button-yellow.svg') }}",
            };

            // Yellow = no assessment yet; green = has a saved health score
            function pickHealthIconColor(score) {
                if (score === null || score === undefined || score === '') return 'yellow';
                const n = parseFloat(score);
                return isNaN(n) ? 'yellow' : 'green';
            }

            function pickHealthColor(score) {
                if (score === null || score === undefined || score === '') return 'gray';
                const n = parseFloat(score);
                if (isNaN(n)) return 'gray';
                if (n >= 80) return 'green';
                if (n >= 50) return 'yellow';
                return 'red';
            }

            function refreshAvgHealthBadge() {
                const rows = table.getData();
                const scores = rows
                    .map(r => r.shipping_health_score)
                    .filter(s => s !== null && s !== undefined && !isNaN(parseFloat(s)))
                    .map(s => parseFloat(s));
                const total = rows.length;
                const $badge = $('#avgHealthBadge');
                if (!scores.length) {
                    $badge.removeClass('sh-green sh-yellow sh-red').addClass('sh-gray');
                    $('#avgHealthBadgeValue').text('—');
                    $('#avgHealthBadgeMeta').text('No assessments yet');
                    return;
                }
                const avg = Math.round(scores.reduce((a, b) => a + b, 0) / scores.length);
                const color = pickHealthColor(avg);
                $badge.removeClass('sh-green sh-yellow sh-red sh-gray').addClass('sh-' + color);
                $('#avgHealthBadgeValue').text(avg + '%');
                $('#avgHealthBadgeMeta').text(`${scores.length} of ${total} platforms assessed`);
            }

            function formatHealthScore(score) {
                if (score === null || score === undefined || score === '') return '—';
                const n = parseFloat(score);
                return isNaN(n) ? '—' : n.toFixed(0) + '%';
            }

            const table = new Tabulator('#ccShippingAuditTable', {
                data: tableData,
                layout: 'fitColumns',
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
                        title: 'Shipping Health',
                        field: 'shipping_health_score',
                        width: 90,
                        hozAlign: 'center',
                        headerSort: false,
                        formatter: function (cell) {
                            const data    = cell.getRow().getData();
                            const channel = (data.channel || '').toString();
                            const safeCh  = channel.replace(/"/g, '&quot;');
                            const score   = data.shipping_health_score;
                            const color   = pickHealthIconColor(score);
                            const label   = formatHealthScore(score);
                            const tip     = score != null
                                ? `${channel} — Health ${label}`
                                : `${channel} — No assessment yet`;
                            return `<button type="button" class="sh-health-btn open-sh-health-btn sh-${color}"
                                        data-channel="${safeCh}"
                                        data-channel-id="${data.channel_id || ''}"
                                        title="${tip.replace(/"/g, '&quot;')}">
                                        <img src="${SH_HEALTH_BTN[color]}" alt="Shipping Health ${label}"/>
                                    </button>`;
                        },
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
                    { title: 'Last Audited & Health', field: 'last_audited', width: 180 },
                ],
            });

            refreshAvgHealthBadge();

            // ============================================================
            //  CC Shipping Audit Modal — dynamic, DB-backed
            // ============================================================
            const AUDIT_MODULE = 'cc_shipping';
            const ROUTES = {
                config:    '{{ route('audit.master.parameters') }}',
                store:     '{{ route('audit.master.audits.store') }}',
                history:   '{{ route('audit.master.audits.history') }}',
                agentKpis: '{{ route('audit.master.agent.kpis') }}',
                paramStore:   '{{ route('audit.master.parameters.store') }}',
                paramUpdate:  '{{ url('/audit-master/parameters/manage') }}',
                paramDestroy: '{{ url('/audit-master/parameters/manage') }}',
                shConfig:     '{{ route('audit.master.shipping.health.config') }}',
                shStore:      '{{ route('audit.master.shipping.health.store') }}',
                shHistory:    '{{ route('audit.master.shipping.health.history') }}',
                shParamStore: '{{ route('audit.master.shipping.health.parameters.store') }}',
                shParamUpdate: '{{ url('/audit-master/shipping-health/parameters/manage') }}',
                shParamDestroy: '{{ url('/audit-master/shipping-health/parameters/manage') }}',
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

                    const failed = isCrit && score < (max * 0.5);

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

            // Re-load history when the History tab is opened (in case state changed)
            $('#audit-history-tab').on('shown.bs.tab', function () {
                if (currentChannel) loadAuditHistory(currentChannel);
            });

            // Live recalculation
            $(document).on('input change', '#auditParamsContainer .param-score, #auditBonusPoints', recalcScore);

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
                    const max   = parseFloat($row.data('max')) || 0;
                    const score = parseFloat($row.find('.param-score').val()) || 0;
                    const isCrit = parseInt($row.data('is-critical'), 10) === 1;
                    return {
                        id: parseInt($row.data('param-id'), 10),
                        score: score,
                        is_critical_failed: (isCrit && score < max * 0.5) ? 1 : 0,
                        remarks: ($row.find('.param-remarks').val() || '').trim(),
                    };
                }).get();

                const formData = new FormData();
                formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
                formData.append('module', AUDIT_MODULE);
                formData.append('channel', currentChannel);
                formData.append('executive_name', $('#auditExecutive').val().trim());
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
                    table.getRows().forEach(r => {
                        if (r.getData().channel === currentChannel) {
                            r.update({
                                last_audited: new Date().toLocaleDateString(),
                                last_audited_at: nowIso, // <-- triggers Audit column to re-render in green
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

            // ============================================================
            //  Shipping Health Modal — dynamic parameters
            // ============================================================
            let shCurrentChannel   = null;
            let shCurrentChannelId = null;
            let shParameters       = [];

            function shFormatRequired(p) {
                const v = String(p.required_value ?? '').trim();
                return v || '—';
            }

            function shParsePercent(v) {
                const s = String(v ?? '').trim().replace('%', '');
                if (s === '' || isNaN(parseFloat(s))) return null;
                return parseFloat(s);
            }

            function shCurrentInputHtml(p, value) {
                const num = shParsePercent(value);
                const display = num !== null ? num : '';
                return `<div class="input-group input-group-sm sh-percent-input">
                            <input type="number" min="0" max="100" step="0.1"
                                class="form-control form-control-sm sh-current-input"
                                data-param-id="${p.id}" data-type="percent"
                                value="${display !== '' ? escapeHtml(String(display)) : ''}"
                                placeholder="0">
                            <span class="input-group-text">%</span>
                        </div>`;
            }

            function shReadCurrentValue($input) {
                const raw = ($input.val() || '').trim();
                if (raw === '') return '';
                const n = shParsePercent(raw);
                return n !== null ? String(n) : raw;
            }

            function shScoreColor(score) {
                if (score >= 80) return '#198754';
                if (score >= 50) return '#ffc107';
                return '#dc3545';
            }

            function shRecalcScore() {
                let pctSum = 0;
                let pctCount = 0;
                $('#shParamsBody tr[data-param-id]').each(function () {
                    const $row = $(this);
                    const paramId = parseInt($row.data('param-id'), 10);
                    const p = shParameters.find(x => x.id === paramId);
                    if (!p) return;
                    const $input = $row.find('.sh-current-input');
                    const current = shReadCurrentValue($input);
                    const curPct = shParsePercent(current);
                    if (curPct !== null) {
                        pctSum += curPct;
                        pctCount++;
                    }
                });
                const score = pctCount > 0 ? Math.round(pctSum / pctCount) : 0;
                $('#shScoreRing').text(score + '%').css('background', shScoreColor(score));
                $('#shScoreSummary').text(
                    pctCount > 0
                        ? `Average current health: ${score}%`
                        : 'Enter current % for each parameter.'
                );
                return score;
            }

            function shRenderParameters() {

                if (!shParameters.length) {
                    $('#shParamsBody').html(`<tr><td colspan="4" class="text-center text-muted py-3">
                        No shipping health parameters configured yet.
                        ${isAuditAdmin ? ' Use Add Parameter to create one.' : ''}
                    </td></tr>`);
                    $('#shScoreRing').text('—').css('background', '#adb5bd');
                    $('#shScoreSummary').text('No parameters to assess.');
                    return;
                }

                const adminCol = isAuditAdmin ? '' : 'd-none';
                const rows = shParameters.map(p => {
                    const desc = p.description
                        ? `<div class="text-muted small">${escapeHtml(p.description)}</div>` : '';
                    const adminBtns = isAuditAdmin
                        ? `<button type="button" class="btn btn-link btn-sm p-0 sh-edit-param-btn" data-param-id="${p.id}" title="Edit">
                               <i class="ri-pencil-line"></i>
                           </button>
                           <button type="button" class="btn btn-link btn-sm p-0 text-danger sh-archive-param-btn" data-param-id="${p.id}" title="Archive">
                               <i class="ri-archive-line"></i>
                           </button>`
                        : '';
                    return `<tr data-param-id="${p.id}">
                        <td>
                            <div class="fw-semibold">${escapeHtml(p.label)}</div>
                            ${desc}
                        </td>
                        <td><span class="sh-required-text">${escapeHtml(shFormatRequired(p))}</span></td>
                        <td>${shCurrentInputHtml(p, '')}</td>
                        <td class="text-center sh-admin-col ${adminCol}">${adminBtns}</td>
                    </tr>`;
                }).join('');
                $('#shParamsBody').html(rows);
                shRecalcScore();
            }

            function shLoadConfig() {
                $('#shLoading').removeClass('d-none');
                return $.get(ROUTES.shConfig, { channel: shCurrentChannel })
                    .then(res => {
                        shParameters = res.parameters || [];
                        if (typeof res.is_admin !== 'undefined') {
                            isAuditAdmin = !!res.is_admin;
                        }
                        $('#shAdminBadge').toggleClass('d-none', !isAuditAdmin);
                        $('#shAddParamBtn').toggleClass('d-none', !isAuditAdmin);
                        $('.sh-admin-col').toggleClass('d-none', !isAuditAdmin);
                        shRenderParameters();
                        $('#shNotes').val('');
                    })
                    .catch(() => {
                        $('#shParamsBody').html(`<tr><td colspan="4" class="text-center text-danger py-3">
                            Failed to load shipping health parameters.
                        </td></tr>`);
                    })
                    .always(() => $('#shLoading').addClass('d-none'));
            }

            function shLoadHistory() {
                $('#shHistoryLoading').removeClass('d-none');
                $('#shHistoryBody').empty();
                return $.get(ROUTES.shHistory, { channel: shCurrentChannel })
                    .then(res => {
                        const rows = res.assessments || [];
                        $('#shHistoryCount').text(rows.length);
                        if (!rows.length) {
                            $('#shHistoryBody').html(`<tr><td colspan="6" class="text-center text-muted py-3">No assessments yet.</td></tr>`);
                            return;
                        }
                        const html = rows.map((a, i) => {
                            const met = (a.items || []).filter(it => it.meets_required).length;
                            const total = (a.items || []).length;
                            return `<tr>
                                <td>${i + 1}</td>
                                <td>${escapeHtml(a.assessed_at || '')}</td>
                                <td>${escapeHtml(a.user?.name || '—')}</td>
                                <td class="text-end fw-bold">${a.health_score != null ? parseFloat(a.health_score).toFixed(0) + '%' : '—'}</td>
                                <td class="text-center">${met}/${total}</td>
                                <td class="text-truncate" style="max-width:200px;" title="${escapeHtml(a.notes || '')}">${escapeHtml(a.notes || '—')}</td>
                            </tr>`;
                        }).join('');
                        $('#shHistoryBody').html(html);
                    })
                    .catch(() => {
                        $('#shHistoryBody').html(`<tr><td colspan="6" class="text-center text-danger py-3">Failed to load history.</td></tr>`);
                    })
                    .always(() => $('#shHistoryLoading').addClass('d-none'));
            }

            function shUpdateTableRow(score) {
                const rows = table.getRows();
                rows.forEach(r => {
                    const d = r.getData();
                    if (d.channel === shCurrentChannel) {
                        r.update({
                            shipping_health_score: score,
                            shipping_health_at: new Date().toISOString(),
                        });
                    }
                });
                refreshAvgHealthBadge();
            }

            $(document).on('click', '.open-sh-health-btn', function () {
                shCurrentChannel   = $(this).data('channel') || '';
                shCurrentChannelId = $(this).data('channel-id') || null;
                $('#shChannelName').text(shCurrentChannel);
                $('#shNotes').val('');
                const tabEl = document.getElementById('sh-form-tab');
                if (tabEl) bootstrap.Tab.getOrCreateInstance(tabEl).show();
                shLoadConfig();
                shLoadHistory();
                bootstrap.Modal.getOrCreateInstance(document.getElementById('shippingHealthModal')).show();
            });

            $('#sh-history-tab').on('shown.bs.tab', function () {
                if (shCurrentChannel) shLoadHistory();
            });

            $(document).on('input change', '#shParamsBody .sh-current-input', shRecalcScore);

            $('#saveShippingHealthBtn').on('click', function () {
                if (!shCurrentChannel || !shParameters.length) return;

                const items = $('#shParamsBody tr[data-param-id]').map(function () {
                    const paramId = parseInt($(this).data('param-id'), 10);
                    const $input = $(this).find('.sh-current-input');
                    return {
                        parameter_id: paramId,
                        current_value: shReadCurrentValue($input),
                    };
                }).get();

                const $btn = $(this).prop('disabled', true)
                    .html('<span class="spinner-border spinner-border-sm me-1"></span> Saving...');

                $.ajax({
                    url: ROUTES.shStore,
                    method: 'POST',
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        channel: shCurrentChannel,
                        channel_id: shCurrentChannelId || null,
                        notes: $('#shNotes').val().trim(),
                        items: items,
                    },
                })
                .done(res => {
                    if (res.success && res.assessment) {
                        shUpdateTableRow(res.assessment.health_score);
                        shLoadHistory();
                        const tabEl = document.getElementById('sh-form-tab');
                        if (tabEl) bootstrap.Tab.getOrCreateInstance(tabEl).show();
                        shRenderParameters();
                    }
                })
                .fail(xhr => {
                    alert(xhr.responseJSON?.message || 'Failed to save shipping health assessment.');
                })
                .always(() => {
                    $btn.prop('disabled', false).html('<i class="ri-save-line me-1"></i> Save Assessment');
                });
            });

            function getShParamEditorModal() {
                const el = document.getElementById('shParamEditorModal');
                let inst = bootstrap.Modal.getInstance(el);
                if (!inst) {
                    // No second backdrop — avoids it covering the nested modal close button
                    inst = new bootstrap.Modal(el, { backdrop: false, keyboard: true });
                }
                return inst;
            }

            function openShParamEditorModal() {
                const el = document.getElementById('shParamEditorModal');
                el.style.zIndex = '1080';
                getShParamEditorModal().show();
            }

            // Admin: add / edit / archive shipping health parameters
            $('#shAddParamBtn').on('click', function () {
                $('#shParamEditorId').val('');
                $('#shParamEditorTitle').text('Add Shipping Health Parameter');
                $('#shParamEditorLabel, #shParamEditorDescription, #shParamEditorRequired, #shParamEditorSort').val('');
                $('#shParamEditorType').val('percent');
                $('#shParamEditorActive').prop('checked', true);
                $('#shParamEditorError').addClass('d-none');
                openShParamEditorModal();
            });

            $(document).on('click', '.sh-edit-param-btn', function () {
                const p = shParameters.find(x => x.id === parseInt($(this).data('param-id'), 10));
                if (!p) return;
                $('#shParamEditorId').val(p.id);
                $('#shParamEditorTitle').text('Edit Shipping Health Parameter');
                $('#shParamEditorLabel').val(p.label);
                $('#shParamEditorDescription').val(p.description || '');
                $('#shParamEditorType').val('percent');
                $('#shParamEditorRequired').val(p.required_value || '');
                $('#shParamEditorSort').val(p.sort_order || '');
                $('#shParamEditorActive').prop('checked', true);
                $('#shParamEditorError').addClass('d-none');
                openShParamEditorModal();
            });

            $(document).on('click', '.sh-archive-param-btn', function () {
                const id = parseInt($(this).data('param-id'), 10);
                if (!confirm('Archive this parameter? It will no longer appear in new assessments.')) return;
                $.ajax({
                    url: `${ROUTES.shParamDestroy}/${id}`,
                    method: 'DELETE',
                    data: { _token: $('meta[name="csrf-token"]').attr('content') },
                }).done(() => shLoadConfig())
                  .fail(() => alert('Failed to archive parameter.'));
            });

            $('#shParamEditorSaveBtn').on('click', function () {
                const id = $('#shParamEditorId').val();
                const payload = {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    label: $('#shParamEditorLabel').val().trim(),
                    description: $('#shParamEditorDescription').val().trim(),
                    value_type: 'percent',
                    required_value: $('#shParamEditorRequired').val().trim(),
                    is_active: $('#shParamEditorActive').is(':checked') ? 1 : 0,
                };
                const sort = $('#shParamEditorSort').val();
                if (sort !== '') payload.sort_order = parseInt(sort, 10);

                if (!payload.label) {
                    $('#shParamEditorError').text('Label is required.').removeClass('d-none');
                    return;
                }

                const $btn = $(this).prop('disabled', true);
                const req = id
                    ? $.ajax({ url: `${ROUTES.shParamUpdate}/${id}`, method: 'PUT', data: payload })
                    : $.post(ROUTES.shParamStore, payload);

                req.done(() => {
                    getShParamEditorModal().hide();
                    shLoadConfig();
                })
                .fail(xhr => {
                    $('#shParamEditorError').text(xhr.responseJSON?.message || 'Save failed.').removeClass('d-none');
                })
                .always(() => $btn.prop('disabled', false));
            });

            $(document).on('click', '#shParamEditorModal .btn-close, #shParamEditorModal [data-bs-dismiss="modal"]', function (e) {
                e.preventDefault();
                e.stopPropagation();
                getShParamEditorModal().hide();
            });
        });
    </script>
@endsection
