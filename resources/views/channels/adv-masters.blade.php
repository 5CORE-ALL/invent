@extends('layouts.vertical', ['title' => 'ADV Masters'])

@section('css')
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/tabulator-tables@5.5.2/dist/css/tabulator_bootstrap5.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #6366F1;
            --secondary-color: #4F46E5;
            --success-color: #10B981;
            --danger-color: #EF4444;
            --warning-color: #F59E0B;
            --info-color: #3B82F6;
            --light-bg: #F9FAFB;
            --border-color: #E5E7EB;
            --text-primary: #111827;
            --text-secondary: #6B7280;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        body {
            background-color: var(--light-bg);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }

        .stats-card {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border-radius: 16px;
            padding: 24px;
            box-shadow: var(--shadow-lg);
            margin-bottom: 2rem;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.15);
        }

        .stats-card h4 {
            color: rgba(255, 255, 255, 0.95);
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .stats-card .badge {
            font-size: 1.5rem;
            padding: 0.5rem 1rem;
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(10px);
        }

        .table-container {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--shadow-md);
            margin-bottom: 2rem;
            transition: box-shadow 0.3s ease;
        }

        .table-container:hover {
            box-shadow: var(--shadow-lg);
        }

        /* Improved Search Bar */
        #search-input {
            border-radius: 8px;
            border: 1px solid var(--border-color);
            padding: 8px 32px 8px 12px;
            font-size: 13px;
            transition: all 0.2s ease;
            background: white;
        }

        #search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.1);
        }

        #search-input::placeholder {
            color: var(--text-secondary);
            font-size: 13px;
        }

        /* Table Styling */
        #adv-master-table {
            table-layout: fixed;
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        /* Tabulator Styling */
        .tabulator {
            border: none;
            background: white;
            border-radius: 12px;
            overflow: hidden;
        }

        .tabulator .tabulator-header {
            background: linear-gradient(135deg, #F8F9FA 0%, #E9ECEF 100%);
            border-bottom: 2px solid var(--border-color);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .tabulator .tabulator-col {
            background: linear-gradient(135deg, #F8F9FA 0%, #E9ECEF 100%);
            border-right: 1px solid var(--border-color);
        }

        .tabulator .tabulator-col-content {
            padding: 16px 12px;
            font-weight: 600;
            font-size: 13px;
            color: var(--text-primary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Tabulator headers – all columns bottom-to-top */
        .tabulator .tabulator-col .tabulator-col-content {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            white-space: nowrap;
            height: 120px;
            min-width: 36px;
            vertical-align: bottom;
            padding: 8px 6px;
            line-height: 1.3;
            overflow: visible;
            transform: rotate(180deg);
        }

        .tabulator .tabulator-cell {
            padding: 12px;
            border-right: 1px solid var(--border-color);
        }

        .tabulator .tabulator-row {
            border-bottom: 1px solid var(--border-color);
        }

        .tabulator .tabulator-row:hover {
            background-color: #f8f9fa;
        }

        /* Header sort & filter */
        .tabulator .tabulator-col .tabulator-header-filter input,
        .tabulator .tabulator-col input[type="text"] {
            width: 100%;
            max-width: 140px;
            padding: 4px 8px;
            font-size: 12px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            background: #fff;
        }
        .tabulator .tabulator-col .tabulator-header-filter input:focus,
        .tabulator .tabulator-col input[type="text"]:focus {
            border-color: #4361ee;
            outline: none;
        }

        #adv-master-table thead th {
            background: linear-gradient(135deg, #F8F9FA 0%, #E9ECEF 100%);
            color: var(--text-primary);
            font-weight: 600;
            font-size: 13px;
            padding: 16px 12px;
            border-bottom: 2px solid var(--border-color);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            cursor: col-resize;
            position: sticky;
            top: 0;
            z-index: 10;
            text-align: center;
        }

        #adv-master-table thead th:first-child {
            border-top-left-radius: 12px;
        }

        #adv-master-table thead th:last-child {
            border-top-right-radius: 12px;
        }

        /* Vertical headers (columns 4+) – bottom-to-top */
        #adv-master-table thead th.th-vertical {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            white-space: nowrap;
            height: 140px;
            min-width: 38px;
            max-width: 42px;
            vertical-align: bottom;
            padding: 8px 6px;
            line-height: 1.3;
            overflow: visible;
            text-overflow: clip;
            transform: rotate(180deg);
        }

        #adv-master-table thead th.th-vertical hr {
            margin: 4px auto;
            width: 80%;
        }

        /* All headers bottom-to-top (including CHANNELS, Ad Type, Tab, Graph) */
        #adv-master-table thead th.th-horizontal {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            white-space: nowrap;
            height: 140px;
            min-width: 38px;
            vertical-align: bottom;
            transform: rotate(180deg);
        }

        #adv-master-table tbody td {
            padding: 14px 12px;
            border-bottom: 1px solid var(--border-color);
            font-size: 14px;
            transition: background-color 0.2s ease;
            text-align: center;
        }

        #adv-master-table tbody tr:hover {
            background-color: #F9FAFB;
        }

        #adv-master-table th,
        #adv-master-table td {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* Button Improvements */
        .btn-primary.rounded-circle {
            width: 20px;
            height: 20px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: none;
            background: var(--primary-color);
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
        }

        .btn-primary.rounded-circle:hover {
            background: var(--secondary-color);
            transform: scale(1.1);
            box-shadow: var(--shadow-md);
        }

        .btn-success {
            border-radius: 8px;
            padding: 10px 24px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        /* Link Styling */
        #adv-master-table a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s ease;
            padding: 4px 8px;
            border-radius: 6px;
        }

        #adv-master-table a:hover {
            color: var(--secondary-color);
            background-color: rgba(99, 102, 241, 0.1);
            text-decoration: none;
        }

        /* Modal Improvements */
        .modal-content {
            border-radius: 16px;
            border: none;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border-radius: 16px 16px 0 0;
            padding: 20px 24px;
            border-bottom: none;
        }

        .modal-title {
            font-weight: 600;
            font-size: 18px;
        }

        .btn-close {
            filter: brightness(0) invert(1);
            opacity: 0.9;
        }

        .btn-close:hover {
            opacity: 1;
        }

        .modal-body {
            padding: 24px;
        }

        /* Form Input Improvements */
        .form-control {
            border-radius: 10px;
            border: 2px solid var(--border-color);
            padding: 10px 16px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
            outline: none;
        }

        /* Stats Cards in Modal */
        .shadow.p-3.mb-1.bg-white.rounded {
            border-radius: 12px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .shadow.p-3.mb-1.bg-white.rounded:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-color);
        }

        /* Color Indicators */
        .spend-color, .clicks-color, .adsales-color, .adsold-color, .cpc-color, .cvr-color {
            width: 24px;
            height: 24px;
            border-radius: 6px;
            display: inline-block;
            box-shadow: var(--shadow-sm);
        }

        .spend-color {
            background-color: #6c2bd9;
        }

        .clicks-color {
            background-color: #00b894;
        }

        .adsales-color {
            background-color: #ed0808fc;
        }

        .adsold-color {
            background-color: #0984e3;
        }

        .cpc-color {
            background-color: #0c293efc;
        }

        .cvr-color {
            background-color: #f6da09ee;
        }

        /* L30 CLKS column: eye icon visible with value (no clip) */
        #adv-master-table td.td-l30-clicks,
        #tabulator-table .tabulator-cell:nth-child(11) {
            overflow: visible;
            white-space: nowrap;
        }
        #adv-master-table td.td-l30-clicks .clicks-cell-inner,
        #tabulator-table .tabulator-cell:nth-child(11) .clicks-cell-inner {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .clicks-chart-btn {
            color: transparent !important;
            text-decoration: none !important;
            opacity: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            vertical-align: middle;
            padding: 0;
            margin: 0;
            border: none !important;
            background: none !important;
            box-shadow: none !important;
            flex-shrink: 0;
        }
        .clicks-chart-btn:hover {
            background: none !important;
        }
        .clicks-chart-btn svg {
            width: 18px;
            height: 18px;
            fill: #00b894 !important;
            display: block;
            flex-shrink: 0;
        }
        .clicks-chart-btn:hover svg {
            fill: #009975 !important;
        }

        /* L30 SPENT column: eye icon (same as L30 CLKS) */
        #adv-master-table td.td-l30-spent,
        #tabulator-table .tabulator-cell:nth-child(8) {
            overflow: visible;
            white-space: nowrap;
        }
        #adv-master-table td.td-l30-spent .spend-cell-inner,
        #tabulator-table .tabulator-cell:nth-child(8) .spend-cell-inner {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .spend-chart-btn {
            color: transparent !important;
            text-decoration: none !important;
            opacity: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            vertical-align: middle;
            padding: 0;
            margin: 0;
            border: none !important;
            background: none !important;
            box-shadow: none !important;
            flex-shrink: 0;
        }
        .spend-chart-btn:hover {
            background: none !important;
        }
        .spend-chart-btn svg {
            width: 18px;
            height: 18px;
            fill: #00b894 !important;
            display: block;
            flex-shrink: 0;
        }
        .spend-chart-btn:hover svg {
            fill: #009975 !important;
        }

        /* AD SALES column: eye icon (same as L30 CLKS) */
        #adv-master-table td.td-l30-adsales,
        #tabulator-table .tabulator-cell:nth-child(17) {
            overflow: visible;
            white-space: nowrap;
        }
        #adv-master-table td.td-l30-adsales .adsales-cell-inner,
        #tabulator-table .tabulator-cell:nth-child(17) .adsales-cell-inner {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .ad-sales-chart-btn {
            color: transparent !important;
            text-decoration: none !important;
            opacity: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            vertical-align: middle;
            padding: 0;
            margin: 0;
            border: none !important;
            background: none !important;
            box-shadow: none !important;
            flex-shrink: 0;
        }
        .ad-sales-chart-btn:hover {
            background: none !important;
        }
        .ad-sales-chart-btn svg {
            width: 18px;
            height: 18px;
            fill: #00b894 !important;
            display: block;
            flex-shrink: 0;
        }
        .ad-sales-chart-btn:hover svg {
            fill: #009975 !important;
        }

        /* L30 ACOS% column: eye icon (same as L30 CLKS) */
        #adv-master-table td.td-l30-acos,
        #tabulator-table .tabulator-cell:nth-child(18) {
            overflow: visible;
            white-space: nowrap;
        }
        #adv-master-table td.td-l30-acos .acos-cell-inner,
        #tabulator-table .tabulator-cell:nth-child(18) .acos-cell-inner {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .acos-chart-btn {
            color: transparent !important;
            text-decoration: none !important;
            opacity: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            vertical-align: middle;
            padding: 0;
            margin: 0;
            border: none !important;
            background: none !important;
            box-shadow: none !important;
            flex-shrink: 0;
        }
        .acos-chart-btn:hover {
            background: none !important;
        }
        .acos-chart-btn svg {
            width: 18px;
            height: 18px;
            fill: #00b894 !important;
            display: block;
            flex-shrink: 0;
        }
        .acos-chart-btn:hover svg {
            fill: #009975 !important;
        }

        /* Daily Chart Modal – full width, compact height, pinned to top */
        .daily-chart-modal .modal-dialog {
            max-width: 98vw;
            width: 98vw;
            margin: 10px auto 0;
        }
        .daily-chart-modal .modal-body {
            padding: 10px 18px 8px;
        }
        .daily-chart-modal .modal-header {
            padding: 8px 18px;
        }
        .daily-chart-modal .modal-header .modal-title {
            font-size: 15px;
        }
        .daily-chart-modal canvas {
            max-height: 18vh !important;
        }
        .daily-chart-modal .chart-stats-panel {
            padding: 10px 12px;
            gap: 8px;
        }
        .daily-chart-modal .chart-stats-panel .stat-header {
            font-size: 9px;
        }
        .daily-chart-modal .chart-stats-panel .stat-value {
            font-size: 15px;
        }

        /* Enhanced Chart Stats Panel */
        .chart-enhanced-wrapper {
            display: flex;
            align-items: stretch;
            gap: 16px;
        }
        .chart-enhanced-wrapper .chart-wrapper {
            flex: 1 1 0%;
            min-width: 0;
        }
        .chart-stats-panel {
            min-width: 150px;
            max-width: 170px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 18px;
            padding: 18px 14px;
            background: #f8f9fa;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
        }
        .chart-stats-panel .stat-block {
            text-align: center;
        }
        .chart-stats-panel .stat-header {
            display: block;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #6b7280;
            margin-bottom: 4px;
        }
        .chart-stats-panel .stat-value {
            display: block;
            font-size: 20px;
            font-weight: 800;
            color: #1f2937;
            line-height: 1.2;
        }
        .chart-stats-panel .stat-block.stat-highest .stat-value { color: #dc2626; }
        .chart-stats-panel .stat-block.stat-median .stat-value { color: #2563eb; }
        .chart-stats-panel .stat-block.stat-lowest .stat-value { color: #16a34a; }

        .label-text {
            font-weight: 700;
            color: var(--text-primary);
            font-size: 16px;
        }

        .title-label {
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .table-container {
                padding: 1rem;
                border-radius: 12px;
            }

            #adv-master-table {
                font-size: 12px;
            }

            #adv-master-table th,
            #adv-master-table td {
                padding: 8px 6px;
            }
        }

        @media (min-width: 1200px) {
            .modal-fullscreen-xl-up .modal-dialog {
                max-width: 100%;
                margin: 0;
                height: 100vh;
            }

            .modal-fullscreen-xl-up .modal-content {
                height: 100vh;
                border: 0;
                border-radius: 0;
            }

            .modal-fullscreen-xl-up .modal-body {
                overflow: auto;
            }

            .chart-box {
                max-width: 950px;
                margin: auto;
                background: #fff;
                border-radius: 20px;
                box-shadow: var(--shadow-lg);
                padding: 25px 35px;
            }

            h2 {
                text-align: center;
                color: var(--text-primary);
                font-weight: 600;
                margin-bottom: 15px;
            }

            canvas {
                margin-top: 10px;
            }
        }

        /* Loading Animation */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .table-container {
            animation: fadeIn 0.5s ease;
        }

        /* Scrollbar Styling */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--light-bg);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--border-color);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--text-secondary);
        }

        /* Number Formatting */
        .number-cell {
            font-variant-numeric: tabular-nums;
            font-weight: 500;
        }

        /* Empty Cell Styling */
        #adv-master-table tbody td:empty::before {
            content: '—';
            color: var(--text-secondary);
            opacity: 0.5;
        }

        /* Header Row Styling */
        #adv-master-table thead th hr {
            margin: 8px 0 4px 0;
            border: none;
            border-top: 2px solid var(--primary-color);
            opacity: 0.3;
        }

        /* Smooth Transitions */
        * {
            transition: background-color 0.2s ease, color 0.2s ease, border-color 0.2s ease;
        }

        /* All main channels – soft blue (easy on eyes) */
        .tabulator .tabulator-row.channel-main .tabulator-cell {
            background-color: #93c5fd !important;
            color: #1e3a5f;
        }
        .tabulator .tabulator-row.channel-main:hover .tabulator-cell {
            background-color: #7dd3fc !important;
            color: #1e3a5f;
        }
        .tabulator .tabulator-row.channel-main a,
        .tabulator .tabulator-row.channel-main .text-primary {
            color: #1e40af !important;
        }

        /* All child channels – very soft blue tint */
        .tabulator .tabulator-row.channel-child .tabulator-cell {
            background-color: #f0f9ff !important;
            color: #334155;
        }
        .tabulator .tabulator-row.channel-child:hover .tabulator-cell {
            background-color: #e0f2fe !important;
            color: #334155;
        }
        .tabulator .tabulator-row.channel-child a {
            color: #2563eb !important;
            font-weight: 500;
        }
    </style>
@endsection

@section('content')
    <div class="container-fluid py-4">
        
        {{-- <div class="stats-card">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="m-0">ADV Masters</h4>
            </div>
        </div> --}}

        <!-- Table Container -->
        <div class="table-container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                <h3 class="mb-0" style="color: var(--text-primary); font-weight: 700;">ADV Masters Dashboard</h3>
                </div>
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <div class="d-flex align-items-center gap-2">
                        <label class="mb-0 small fw-semibold text-nowrap">View:</label>
                        <select class="form-select form-select-sm" id="adv-view-mode" style="width: auto;">
                            <option value="all">All channels</option>
                            <option value="channel-wise">Channel-wise</option>
                        </select>
                        <select class="form-select form-select-sm" id="adv-channel-select" style="width: auto; display: none;">
                            <option value="">Select channel</option>
                            @foreach(['AMAZON','EBAY','EBAY 2','EBAY 3','WALMART','G SHOPPING'] as $ch)
                                <option value="{{ $ch }}">{{ $ch }}</option>
                            @endforeach
                            @if(isset($additionalChannelsData) && count($additionalChannelsData) > 0)
                                @foreach($additionalChannelsData as $chData)
                                    <option value="{{ $chData['channel'] }}">{{ $chData['channel'] }}</option>
                                @endforeach
                            @endif
                        </select>
                        <a href="{{ route('all.marketplace.master') }}" class="btn btn-sm btn-outline-primary" title="Active Channels view">Active Channels</a>
                    </div>
                    <div class="position-relative" style="width: 280px;">
                        <input type="text" class="form-control form-control-sm" id="search-input" placeholder="🔍 Search channels..." style="border-radius: 8px;" />
                        <span class="position-absolute top-50 end-0 translate-middle-y me-2" style="pointer-events: none; opacity: 0.5;">
                            <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                                <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/>
                            </svg>
                        </span>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" id="add-channel-btn" data-bs-toggle="modal" data-bs-target="#addChannelModal" title="Add New Channel" style="border-radius: 8px; font-weight: 500;">
                        <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor" style="margin-right: 4px; vertical-align: middle;">
                            <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
                        </svg>
                        Add Channel
                    </button>
                </div>
            </div>

            <div class="table-responsive mt-3">
                <table class="table table-bordered table-responsive display" id="adv-master-table" style="width:100%">
                    <thead>
                        <tr>
                            <th class="text-center th-horizontal" style="width: 110px;">CHANNELS</th>
                            <th class="text-center th-horizontal" style="width: 90px;">Ad Type</th>
                            <th class="text-center th-horizontal" style="width: 70px;">Tab</th>
                            <th class="text-center th-horizontal" style="width: 70px;">Graph</th>
                            <th class="text-center th-horizontal">L30 SALES</th>
                            <th class="text-center th-horizontal">GPFT</th>
                            <th class="text-center th-vertical">TPFT</th>
                            <th class="text-center th-vertical">L30 SPENT</th>
                            <th class="text-center th-vertical">L60 SPENT</th>
                            <th class="text-center th-vertical">GRW</th>
                            <th class="text-center th-vertical">L30 CLKS</th>
                            <th class="text-center th-vertical">L60 CLICKS</th>
                            <th class="text-center th-vertical">GRW CLKS</th>
                            <th class="text-center th-vertical">CPC</th>
                            <th class="text-center th-vertical">CTR</th>
                            <th class="text-center th-vertical">CPS</th>
                            <th class="text-center th-vertical">AD SALES</th>
                            <th class="text-center th-vertical">L30 ACOS%</th>
                            <th class="text-center th-vertical">L60 ACOS%</th>
                            <th class="text-center th-vertical">Ctrl ACOS%</th>
                            <th class="text-center th-vertical">TACOS</th>     
                            <th class="text-center th-vertical">AD SOLD</th>        
                            <th class="text-center th-vertical">CVR</th>     
                            <th class="text-center th-vertical">CVR 60</th>
                            <th class="text-center th-vertical">Grw CVR</th>
                            <th class="text-center th-vertical">MISSING ADS</th>
                            <th class="text-center th-vertical">ACTIONS</th>     
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="accordion-header">
                            <td class="text-center">
                                <div class="d-flex align-items-center justify-content-center gap-2">
                                    <b>AMAZON</b>
                                    <button type="button" class="btn btn-primary rounded-circle p-0" data-bs-toggle="modal" data-bs-target="#amazonModal" title="View Graph">
                                        <svg width="12" height="12" viewBox="0 0 12 12" fill="white">
                                            <path d="M6 0L7.5 4.5L12 6L7.5 7.5L6 12L4.5 7.5L0 6L4.5 4.5L6 0Z"/>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center"></td>
                            <td class="text-center"><a href="#" class="adv-l60-tab-link text-primary" title="View L60 Data">L60</a></td>
                            <td class="text-center"><a href="#" class="adv-l60-graph-link text-success" title="View L60 Graph">📈</a></td>
                            <td class="text-center">{{ $amazon_l30_sales }}</td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center td-l30-spent">
                                <div class="spend-cell-inner">
                                    <span>{{ $amazon_spent }}</span>
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline spend-chart-btn" data-channel="AMAZON" data-bs-toggle="modal" data-bs-target="#dailySpendModal" title="View daily spend">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#00b894" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/><path d="M8 3.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center">{{ $amazon_l60_spent ?? 0 }}</td>
                            <td class="text-center">
                                @php
                                    $amazon_grw = (($amazon_l60_spent ?? 0) > 0) ? ($amazon_spent / ($amazon_l60_spent ?? 1)) * 100 : 0;
                                    echo number_format($amazon_grw, 0) . '%';
                                @endphp
                            </td>
                            <td class="text-center td-l30-clicks">
                                <div class="clicks-cell-inner">
                                    <span>{{ $amazon_clicks }}</span>
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline clicks-chart-btn" data-channel="AMAZON" data-bs-toggle="modal" data-bs-target="#dailyClicksModal" title="View daily clicks">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#00b894" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/><path d="M8 3.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center">{{ $amazon_l60_clicks ?? 0 }}</td>
                            <td class="text-center">
                                @php
                                    $grw_clks = (($amazon_l60_clicks ?? 0) > 0) ? ($amazon_clicks / ($amazon_l60_clicks ?? 1)) * 100 : 0;
                                    echo number_format($grw_clks, 0) . '%';
                                @endphp
                            </td>
                            <td class="text-center">
                                @php
                                    $amazon_cpc = ($amazon_clicks > 0) ? ($amazon_spent / $amazon_clicks) : 0;
                                    echo number_format($amazon_cpc, 2);
                                @endphp
                            </td>
                            <td class="text-center">
                                @php
                                    $amazon_impressions = ($amazon_impressions ?? 0);
                                    $amazon_ctr = ($amazon_impressions > 0) ? (($amazon_clicks / $amazon_impressions) * 100) : 0;
                                    echo number_format($amazon_ctr, 2) . '%';
                                @endphp
                            </td>
                            <td class="text-center">
                                @php
                                    $amazon_cps = ($amazon_ad_sold > 0) ? ($amazon_spent / $amazon_ad_sold) : 0;
                                    echo number_format($amazon_cps, 0);
                                @endphp
                            </td>
                            <td class="text-center td-l30-adsales">
                                <div class="adsales-cell-inner">
                                    <span>{{ $amazon_ad_sales }}</span>
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline ad-sales-chart-btn" data-channel="AMAZON" data-bs-toggle="modal" data-bs-target="#dailyAdSalesModal" title="View daily ad sales">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#00b894" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/><path d="M8 3.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center td-l30-acos">
                                <div class="acos-cell-inner">
                                    <span>
                                        @php
                                            if($amazon_ad_sales > 0){
                                                $acos = ($amazon_spent/$amazon_ad_sales)*100;
                                                $acos = number_format($acos, 2);
                                            }else{
                                                $acos = 0;
                                            }
                                            $acos = round($acos);
                                        @endphp
                                        {{ $acos.' %'  }}
                                    </span>
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline acos-chart-btn" data-channel="AMAZON" data-bs-toggle="modal" data-bs-target="#dailyAcosModal" title="View daily ACOS">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#00b894" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/><path d="M8 3.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center">
                                @php
                                    if(($amazon_l60_ad_sales ?? 0) > 0){
                                        $l60_acos = (($amazon_l60_spent ?? 0) / ($amazon_l60_ad_sales ?? 1)) * 100;
                                        $l60_acos = number_format($l60_acos, 2);
                                    }else{
                                        $l60_acos = 0;
                                    }
                                    $l60_acos = round($l60_acos);
                                @endphp
                                {{ $l60_acos.' %'  }}
                            </td>
                            <td class="text-center">
                                @php
                                    $l30_acos_val = ($amazon_ad_sales > 0) ? ($amazon_spent/$amazon_ad_sales)*100 : 0;
                                    $l60_acos_val = (($amazon_l60_ad_sales ?? 0) > 0) ? (($amazon_l60_spent ?? 0) / ($amazon_l60_ad_sales ?? 1)) * 100 : 0;
                                    if($l60_acos_val > 0){
                                        $ctrl_acos = (($l30_acos_val - $l60_acos_val) / $l60_acos_val) * 100;
                                    }else{
                                        $ctrl_acos = 0;
                                    }
                                    $ctrl_acos = number_format($ctrl_acos, 2);
                                    $ctrl_color = ($ctrl_acos < 0) ? 'color: #006400;' : 'color: #8B0000;'; // Dark green for decrease, dark red for increase
                                @endphp
                                <span style="{{ $ctrl_color }}">{{ $ctrl_acos.' %'  }}</span>
                            </td>
                            <td class="text-center">
                                @php
                                    if($amazon_l30_sales > 0){
                                        $tacos = ($amazon_spent/$amazon_l30_sales)*100;
                                        $tacos = number_format($tacos, 2);
                                    }else{
                                        $tacos = 0;
                                    }
                                    $tacos = round($tacos);
                                @endphp
                                {{ $tacos.' %'  }}
                            </td>
                            <td class="text-center">{{ $amazon_ad_sold }}</td>
                            <td class="text-center">
                                @php
                                    if($amazon_clicks > 0){
                                        $cvr = ($amazon_ad_sold/$amazon_clicks)*100;
                                        $cvr = number_format($cvr, 2);
                                    }else{
                                        $cvr = 0;
                                    }
                                    $cvr = round($cvr, 1);
                                    $amazon_cvr = $cvr; 
                                @endphp
                                {{ $cvr.' %' }}
                            </td>
                            <td class="text-center">
                                @php
                                    if(($amazon_l60_clicks ?? 0) > 0){
                                        $cvr_60 = (($amazon_l60_ad_sold ?? 0) / ($amazon_l60_clicks ?? 1)) * 100;
                                        $cvr_60 = number_format($cvr_60, 2);
                                    }else{
                                        $cvr_60 = 0;
                                    }
                                    $cvr_60 = round($cvr_60, 1);
                                @endphp
                                {{ $cvr_60.' %' }}
                            </td>
                            <td class="text-center">
                                @php
                                    $l30_cvr_val = ($amazon_clicks > 0) ? ($amazon_ad_sold/$amazon_clicks)*100 : 0;
                                    $l60_cvr_val = (($amazon_l60_clicks ?? 0) > 0) ? (($amazon_l60_ad_sold ?? 0) / ($amazon_l60_clicks ?? 1)) * 100 : 0;
                                    if($l60_cvr_val > 0){
                                        $grw_cvr = (($l30_cvr_val - $l60_cvr_val) / $l60_cvr_val) * 100;
                                    }else{
                                        $grw_cvr = 0;
                                    }
                                    $grw_cvr = number_format($grw_cvr, 0);
                                @endphp
                                {{ $grw_cvr.' %' }}
                            </td>
                            <td class="text-center">{{ $amazon_missing_ads }}</td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-warning edit-channel-btn" data-channel="AMAZON" data-type="" title="Edit Channel">
                                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                                        <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5L13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.207l6.5-6.5zm-9.761 5.175l-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>

                        <tr class="accordion-body">
                            <td class="text-center"><a href="{{ route('amazon.kw.ads') }}" target="_blank" style="text-decoration:none;">AMZ KW</a></td>
                            <td class="text-center"></td>
                            <td class="text-center"><a href="#" class="adv-l60-tab-link text-primary" title="View L60 Data">L60</a></td>
                            <td class="text-center"><a href="#" class="adv-l60-graph-link text-success" title="View L60 Graph">📈</a></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center td-l30-spent">
                                <div class="spend-cell-inner">
                                    <span>{{ $amazonkw_spent }}</span>
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline spend-chart-btn" data-channel="AMZ KW" data-bs-toggle="modal" data-bs-target="#dailySpendModal" title="View daily spend">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#00b894" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/><path d="M8 3.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center">{{ $amazonkw_l60_spent ?? 0 }}</td>
                            <td class="text-center">
                                @php
                                    $amazonkw_grw = (($amazonkw_l60_spent ?? 0) > 0) ? ($amazonkw_spent / ($amazonkw_l60_spent ?? 1)) * 100 : 0;
                                    echo number_format($amazonkw_grw, 0) . '%';
                                @endphp
                            </td>
                            <td class="text-center td-l30-clicks">
                                <div class="clicks-cell-inner">
                                    <span>{{ $amazonkw_clicks }}</span>
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline clicks-chart-btn" data-channel="AMZ KW" data-bs-toggle="modal" data-bs-target="#dailyClicksModal" title="View daily clicks">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#00b894" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/><path d="M8 3.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center">{{ $amazonkw_l60_clicks ?? 0 }}</td>
                            <td class="text-center">
                                @php
                                    $grw_clks = (($amazonkw_l60_clicks ?? 0) > 0) ? ($amazonkw_clicks / ($amazonkw_l60_clicks ?? 1)) * 100 : 0;
                                    echo number_format($grw_clks, 0) . '%';
                                @endphp
                            </td>
                            <td class="text-center">
                                @php
                                    $amazonkw_cpc = ($amazonkw_clicks > 0) ? ($amazonkw_spent / $amazonkw_clicks) : 0;
                                    echo number_format($amazonkw_cpc, 2);
                                @endphp
                            </td>
                            <td class="text-center">
                                @php
                                    $amazonkw_impressions = ($amazonkw_impressions ?? 0);
                                    $amazonkw_ctr = ($amazonkw_impressions > 0) ? (($amazonkw_clicks / $amazonkw_impressions) * 100) : 0;
                                    echo number_format($amazonkw_ctr, 2) . '%';
                                @endphp
                            </td>
                            <td class="text-center">
                                @php
                                    $amazonkw_cps = ($amazonkw_ad_sold > 0) ? ($amazonkw_spent / $amazonkw_ad_sold) : 0;
                                    echo number_format($amazonkw_cps, 0);
                                @endphp
                            </td>
                            <td class="text-center td-l30-adsales">
                                <div class="adsales-cell-inner">
                                    <span>{{ $amazonkw_ad_sales }}</span>
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline ad-sales-chart-btn" data-channel="AMZ KW" data-bs-toggle="modal" data-bs-target="#dailyAdSalesModal" title="View daily ad sales">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#00b894" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/><path d="M8 3.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center td-l30-acos">
                                <div class="acos-cell-inner">
                                    <span>
                                        @php
                                            if($amazonkw_ad_sales > 0){
                                                $acos = ($amazonkw_spent/$amazonkw_ad_sales)*100;
                                                $acos = number_format($acos, 2);
                                            }else{
                                                $acos = 0;
                                            }
                                            $acos = round($acos);
                                        @endphp
                                        {{ $acos.' %'  }}
                                    </span>
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline acos-chart-btn" data-channel="AMZ KW" data-bs-toggle="modal" data-bs-target="#dailyAcosModal" title="View daily ACOS">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#00b894" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/><path d="M8 3.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center">
                                @php
                                    if(($amazonkw_l60_ad_sales ?? 0) > 0){
                                        $l60_acos = (($amazonkw_l60_spent ?? 0) / ($amazonkw_l60_ad_sales ?? 1)) * 100;
                                        $l60_acos = number_format($l60_acos, 2);
                                    }else{
                                        $l60_acos = 0;
                                    }
                                    $l60_acos = round($l60_acos);
                                @endphp
                                {{ $l60_acos.' %'  }}
                            </td>
                            <td class="text-center">
                                @php
                                    $l30_acos_val = ($amazonkw_ad_sales > 0) ? ($amazonkw_spent/$amazonkw_ad_sales)*100 : 0;
                                    $l60_acos_val = (($amazonkw_l60_ad_sales ?? 0) > 0) ? (($amazonkw_l60_spent ?? 0) / ($amazonkw_l60_ad_sales ?? 1)) * 100 : 0;
                                    if($l60_acos_val > 0){
                                        $ctrl_acos = (($l30_acos_val - $l60_acos_val) / $l60_acos_val) * 100;
                                    }else{
                                        $ctrl_acos = 0;
                                    }
                                    $ctrl_acos = number_format($ctrl_acos, 2);
                                    $ctrl_color = ($ctrl_acos < 0) ? 'color: #006400;' : 'color: #8B0000;';
                                @endphp
                                <span style="{{ $ctrl_color }}">{{ $ctrl_acos.' %'  }}</span>
                            </td>
                            <td class="text-center"></td>
                            <td class="text-center">{{ $amazonkw_ad_sold }}</td>
                            <td class="text-center">
                                @php
                                    if($amazonkw_clicks > 0){
                                        $cvr = ($amazonkw_ad_sold/$amazonkw_clicks)*100;
                                        $cvr = number_format($cvr, 2);
                                    }else{
                                        $cvr = 0;
                                    }
                                    $cvr = round($cvr, 1);
                                @endphp
                                {{ $cvr.' %' }}
                            </td>
                            <td class="text-center">
                                @php
                                    if(($amazonkw_l60_clicks ?? 0) > 0){
                                        $cvr_60 = (($amazonkw_l60_ad_sold ?? 0) / ($amazonkw_l60_clicks ?? 1)) * 100;
                                        $cvr_60 = number_format($cvr_60, 2);
                                    }else{
                                        $cvr_60 = 0;
                                    }
                                    $cvr_60 = round($cvr_60, 1);
                                @endphp
                                {{ $cvr_60.' %' }}
                            </td>
                            <td class="text-center">
                                @php
                                    $l30_cvr_val = ($amazonkw_clicks > 0) ? ($amazonkw_ad_sold/$amazonkw_clicks)*100 : 0;
                                    $l60_cvr_val = (($amazonkw_l60_clicks ?? 0) > 0) ? (($amazonkw_l60_ad_sold ?? 0) / ($amazonkw_l60_clicks ?? 1)) * 100 : 0;
                                    if($l60_cvr_val > 0){
                                        $grw_cvr = (($l30_cvr_val - $l60_cvr_val) / $l60_cvr_val) * 100;
                                    }else{
                                        $grw_cvr = 0;
                                    }
                                    $grw_cvr = number_format($grw_cvr, 0);
                                @endphp
                                {{ $grw_cvr.' %' }}
                            </td>
                            <td class="text-center">{{ $amazonkw_missing_ads }}</td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-warning edit-channel-btn" data-channel="AMZ KW" data-type="" title="Edit Channel">
                                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                                        <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5L13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.207l6.5-6.5zm-9.761 5.175l-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>

                         <tr class="accordion-body">
                            <td class="text-center"><a href="{{ route('amazon.pt.ads') }}" target="_blank" style="text-decoration:none;">AMZ PT</a></td>
                            <td class="text-center"></td>
                            <td class="text-center"><a href="#" class="adv-l60-tab-link text-primary" title="View L60 Data">L60</a></td>
                            <td class="text-center"><a href="#" class="adv-l60-graph-link text-success" title="View L60 Graph">📈</a></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center td-l30-spent">
                                <div class="spend-cell-inner">
                                    <span>{{ $amazonpt_spent }}</span>
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline spend-chart-btn" data-channel="AMZ PT" data-bs-toggle="modal" data-bs-target="#dailySpendModal" title="View daily spend">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#00b894" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/><path d="M8 3.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center">{{ $amazonpt_l60_spent ?? 0 }}</td>
                            <td class="text-center">
                                @php
                                    $amazonpt_grw = (($amazonpt_l60_spent ?? 0) > 0) ? ($amazonpt_spent / ($amazonpt_l60_spent ?? 1)) * 100 : 0;
                                    echo number_format($amazonpt_grw, 0) . '%';
                                @endphp
                            </td>
                            <td class="text-center td-l30-clicks">
                                <div class="clicks-cell-inner">
                                    <span>{{ $amazonpt_clicks }}</span>
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline clicks-chart-btn" data-channel="AMZ PT" data-bs-toggle="modal" data-bs-target="#dailyClicksModal" title="View daily clicks">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#00b894" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/><path d="M8 3.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center">{{ $amazonpt_l60_clicks ?? 0 }}</td>
                            <td class="text-center">
                                @php
                                    $grw_clks = (($amazonpt_l60_clicks ?? 0) > 0) ? ($amazonpt_clicks / ($amazonpt_l60_clicks ?? 1)) * 100 : 0;
                                    echo number_format($grw_clks, 0) . '%';
                                @endphp
                            </td>
                            <td class="text-center">
                                @php
                                    $amazonpt_cpc = ($amazonpt_clicks > 0) ? ($amazonpt_spent / $amazonpt_clicks) : 0;
                                    echo number_format($amazonpt_cpc, 2);
                                @endphp
                            </td>
                            <td class="text-center">
                                @php
                                    $amazonpt_impressions = ($amazonpt_impressions ?? 0);
                                    $amazonpt_ctr = ($amazonpt_impressions > 0) ? (($amazonpt_clicks / $amazonpt_impressions) * 100) : 0;
                                    echo number_format($amazonpt_ctr, 2) . '%';
                                @endphp
                            </td>
                            <td class="text-center">
                                @php
                                    $amazonpt_cps = ($amazonpt_ad_sold > 0) ? ($amazonpt_spent / $amazonpt_ad_sold) : 0;
                                    echo number_format($amazonpt_cps, 0);
                                @endphp
                            </td>
                            <td class="text-center td-l30-adsales">
                                <div class="adsales-cell-inner">
                                    <span>{{ $amazonpt_ad_sales }}</span>
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline ad-sales-chart-btn" data-channel="AMZ PT" data-bs-toggle="modal" data-bs-target="#dailyAdSalesModal" title="View daily ad sales">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#00b894" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/><path d="M8 3.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center td-l30-acos">
                                <div class="acos-cell-inner">
                                    <span>
                                        @php
                                            if($amazonpt_ad_sales > 0){
                                                $acos = ($amazonpt_spent/$amazonpt_ad_sales)*100;
                                                $acos = number_format($acos, 2);
                                            }else{
                                                $acos = 0;
                                            }
                                            $acos = round($acos);
                                        @endphp
                                        {{ $acos.' %'  }}
                                    </span>
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline acos-chart-btn" data-channel="AMZ PT" data-bs-toggle="modal" data-bs-target="#dailyAcosModal" title="View daily ACOS">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#00b894" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/><path d="M8 3.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center">
                                @php
                                    if(($amazonpt_l60_ad_sales ?? 0) > 0){
                                        $l60_acos = (($amazonpt_l60_spent ?? 0) / ($amazonpt_l60_ad_sales ?? 1)) * 100;
                                        $l60_acos = number_format($l60_acos, 2);
                                    }else{
                                        $l60_acos = 0;
                                    }
                                    $l60_acos = round($l60_acos);
                                @endphp
                                {{ $l60_acos.' %'  }}
                            </td>
                            <td class="text-center">
                                @php
                                    $l30_acos_val = ($amazonpt_ad_sales > 0) ? ($amazonpt_spent/$amazonpt_ad_sales)*100 : 0;
                                    $l60_acos_val = (($amazonpt_l60_ad_sales ?? 0) > 0) ? (($amazonpt_l60_spent ?? 0) / ($amazonpt_l60_ad_sales ?? 1)) * 100 : 0;
                                    if($l60_acos_val > 0){
                                        $ctrl_acos = (($l30_acos_val - $l60_acos_val) / $l60_acos_val) * 100;
                                    }else{
                                        $ctrl_acos = 0;
                                    }
                                    $ctrl_acos = number_format($ctrl_acos, 2);
                                    $ctrl_color = ($ctrl_acos < 0) ? 'color: #006400;' : 'color: #8B0000;';
                                @endphp
                                <span style="{{ $ctrl_color }}">{{ $ctrl_acos.' %'  }}</span>
                            </td>
                            <td class="text-center"></td>
                            <td class="text-center">{{ $amazonpt_ad_sold }}</td>
                            <td class="text-center">
                                @php
                                    if($amazonpt_clicks > 0){
                                        $cvr = ($amazonpt_ad_sold/$amazonpt_clicks)*100;
                                        $cvr = number_format($cvr, 2);
                                    }else{
                                        $cvr = 0;
                                    }
                                    $cvr = round($cvr, 1);
                                @endphp
                                {{ $cvr.' %' }}
                            </td>
                            <td class="text-center">
                                @php
                                    if(($amazonpt_l60_clicks ?? 0) > 0){
                                        $cvr_60 = (($amazonpt_l60_ad_sold ?? 0) / ($amazonpt_l60_clicks ?? 1)) * 100;
                                        $cvr_60 = number_format($cvr_60, 2);
                                    }else{
                                        $cvr_60 = 0;
                                    }
                                    $cvr_60 = round($cvr_60, 1);
                                @endphp
                                {{ $cvr_60.' %' }}
                            </td>
                            <td class="text-center">
                                @php
                                    $l30_cvr_val = ($amazonpt_clicks > 0) ? ($amazonpt_ad_sold/$amazonpt_clicks)*100 : 0;
                                    $l60_cvr_val = (($amazonpt_l60_clicks ?? 0) > 0) ? (($amazonpt_l60_ad_sold ?? 0) / ($amazonpt_l60_clicks ?? 1)) * 100 : 0;
                                    if($l60_cvr_val > 0){
                                        $grw_cvr = (($l30_cvr_val - $l60_cvr_val) / $l60_cvr_val) * 100;
                                    }else{
                                        $grw_cvr = 0;
                                    }
                                    $grw_cvr = number_format($grw_cvr, 0);
                                @endphp
                                {{ $grw_cvr.' %' }}
                            </td>
                            <td class="text-center">{{ $amazonpt_missing_ads }}</td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-warning edit-channel-btn" data-channel="AMZ PT" data-type="" title="Edit Channel">
                                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                                        <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5L13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.207l6.5-6.5zm-9.761 5.175l-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>

                        <tr class="accordion-body">
                            <td class="text-center"><a href="{{ route('amazon.hl.ads') }}" target="_blank" style="text-decoration:none;">AMZ HL</a></td>
                            <td class="text-center"></td>
                            <td class="text-center"><a href="#" class="adv-l60-tab-link text-primary" title="View L60 Data">L60</a></td>
                            <td class="text-center"><a href="#" class="adv-l60-graph-link text-success" title="View L60 Graph">📈</a></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center td-l30-spent">
                                <div class="spend-cell-inner">
                                    <span>{{ $amazonhl_spent }}</span>
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline spend-chart-btn" data-channel="AMZ HL" data-bs-toggle="modal" data-bs-target="#dailySpendModal" title="View daily spend">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#00b894" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/><path d="M8 3.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center">{{ $amazonhl_l60_spent ?? 0 }}</td>
                            <td class="text-center">
                                @php
                                    $amazonhl_grw = (($amazonhl_l60_spent ?? 0) > 0) ? ($amazonhl_spent / ($amazonhl_l60_spent ?? 1)) * 100 : 0;
                                    echo number_format($amazonhl_grw, 0) . '%';
                                @endphp
                            </td>
                            <td class="text-center td-l30-clicks">
                                <div class="clicks-cell-inner">
                                    <span>{{ $amazonhl_clicks }}</span>
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline clicks-chart-btn" data-channel="AMZ HL" data-bs-toggle="modal" data-bs-target="#dailyClicksModal" title="View daily clicks">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#00b894" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/><path d="M8 3.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center">{{ $amazonhl_l60_clicks ?? 0 }}</td>
                            <td class="text-center">
                                @php
                                    $grw_clks = (($amazonhl_l60_clicks ?? 0) > 0) ? ($amazonhl_clicks / ($amazonhl_l60_clicks ?? 1)) * 100 : 0;
                                    echo number_format($grw_clks, 0) . '%';
                                @endphp
                            </td>
                            <td class="text-center">
                                @php
                                    $amazonhl_cpc = ($amazonhl_clicks > 0) ? ($amazonhl_spent / $amazonhl_clicks) : 0;
                                    echo number_format($amazonhl_cpc, 2);
                                @endphp
                            </td>
                            <td class="text-center">
                                @php
                                    $amazonhl_impressions = ($amazonhl_impressions ?? 0);
                                    $amazonhl_ctr = ($amazonhl_impressions > 0) ? (($amazonhl_clicks / $amazonhl_impressions) * 100) : 0;
                                    echo number_format($amazonhl_ctr, 2) . '%';
                                @endphp
                            </td>
                            <td class="text-center">
                                @php
                                    $amazonhl_cps = ($amazonhl_ad_sold > 0) ? ($amazonhl_spent / $amazonhl_ad_sold) : 0;
                                    echo number_format($amazonhl_cps, 0);
                                @endphp
                            </td>
                            <td class="text-center td-l30-adsales">
                                <div class="adsales-cell-inner">
                                    <span>{{ $amazonhl_ad_sales }}</span>
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline ad-sales-chart-btn" data-channel="AMZ HL" data-bs-toggle="modal" data-bs-target="#dailyAdSalesModal" title="View daily ad sales">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#00b894" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/><path d="M8 3.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center td-l30-acos">
                                <div class="acos-cell-inner">
                                    <span>
                                        @php
                                            if($amazonhl_ad_sales > 0){
                                                $acos = ($amazonhl_spent/$amazonhl_ad_sales)*100;
                                                $acos = number_format($acos, 2);
                                            }else{
                                                $acos = 0;
                                            }
                                            $acos = round($acos);
                                        @endphp
                                        {{ $acos.' %'  }}
                                    </span>
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline acos-chart-btn" data-channel="AMZ HL" data-bs-toggle="modal" data-bs-target="#dailyAcosModal" title="View daily ACOS">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#00b894" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/><path d="M8 3.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center">
                                @php
                                    if(($amazonhl_l60_ad_sales ?? 0) > 0){
                                        $l60_acos = (($amazonhl_l60_spent ?? 0) / ($amazonhl_l60_ad_sales ?? 1)) * 100;
                                        $l60_acos = number_format($l60_acos, 2);
                                    }else{
                                        $l60_acos = 0;
                                    }
                                    $l60_acos = round($l60_acos);
                                @endphp
                                {{ $l60_acos.' %'  }}
                            </td>
                            <td class="text-center">
                                @php
                                    $l30_acos_val = ($amazonhl_ad_sales > 0) ? ($amazonhl_spent/$amazonhl_ad_sales)*100 : 0;
                                    $l60_acos_val = (($amazonhl_l60_ad_sales ?? 0) > 0) ? (($amazonhl_l60_spent ?? 0) / ($amazonhl_l60_ad_sales ?? 1)) * 100 : 0;
                                    if($l60_acos_val > 0){
                                        $ctrl_acos = (($l30_acos_val - $l60_acos_val) / $l60_acos_val) * 100;
                                    }else{
                                        $ctrl_acos = 0;
                                    }
                                    $ctrl_acos = number_format($ctrl_acos, 2);
                                    $ctrl_color = ($ctrl_acos < 0) ? 'color: #006400;' : 'color: #8B0000;';
                                @endphp
                                <span style="{{ $ctrl_color }}">{{ $ctrl_acos.' %'  }}</span>
                            </td>
                            <td class="text-center"></td>
                            <td class="text-center">{{ $amazonhl_ad_sold }}</td>
                            <td class="text-center">
                                @php
                                    if($amazonhl_clicks > 0){
                                        $cvr = ($amazonhl_ad_sold/$amazonhl_clicks)*100;
                                        $cvr = number_format($cvr, 2); 
                                    }else{
                                        $cvr = 0;
                                    }
                                    $cvr = round($cvr, 1);
                                @endphp
                                {{ $cvr.' %' }}
                            </td>
                            <td class="text-center">
                                @php
                                    if(($amazonhl_l60_clicks ?? 0) > 0){
                                        $cvr_60 = (($amazonhl_l60_ad_sold ?? 0) / ($amazonhl_l60_clicks ?? 1)) * 100;
                                        $cvr_60 = number_format($cvr_60, 2);
                                    }else{
                                        $cvr_60 = 0;
                                    }
                                    $cvr_60 = round($cvr_60, 1);
                                @endphp
                                {{ $cvr_60.' %' }}
                            </td>
                            <td class="text-center">
                                @php
                                    $l30_cvr_val = ($amazonhl_clicks > 0) ? ($amazonhl_ad_sold/$amazonhl_clicks)*100 : 0;
                                    $l60_cvr_val = (($amazonhl_l60_clicks ?? 0) > 0) ? (($amazonhl_l60_ad_sold ?? 0) / ($amazonhl_l60_clicks ?? 1)) * 100 : 0;
                                    if($l60_cvr_val > 0){
                                        $grw_cvr = (($l30_cvr_val - $l60_cvr_val) / $l60_cvr_val) * 100;
                                    }else{
                                        $grw_cvr = 0;
                                    }
                                    $grw_cvr = number_format($grw_cvr, 0);
                                @endphp
                                {{ $grw_cvr.' %' }}
                            </td>
                            <td class="text-center">{{ $amazonhl_missing_ads }}</td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-warning edit-channel-btn" data-channel="AMZ HL" data-type="" title="Edit Channel">
                                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                                        <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5L13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.207l6.5-6.5zm-9.761 5.175l-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>

                        <tr class="accordion-header">
                            <td class="text-center">
                                <div class="d-flex align-items-center justify-content-center gap-2">
                                    <b>EBAY</b>
                                    <button type="button" class="btn btn-primary rounded-circle p-0" data-bs-toggle="modal" data-bs-target="#ebayModal" title="View Graph">
                                        <svg width="12" height="12" viewBox="0 0 12 12" fill="white">
                                            <path d="M6 0L7.5 4.5L12 6L7.5 7.5L6 12L4.5 7.5L0 6L4.5 4.5L6 0Z"/>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center"></td>
                            <td class="text-center"><a href="#" class="adv-l60-tab-link text-primary" title="View L60 Data">L60</a></td>
                            <td class="text-center"><a href="#" class="adv-l60-graph-link text-success" title="View L60 Graph">📈</a></td>
                            <td class="text-center">{{ $ebay_l30_sales }}</td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center td-l30-spent">
                                <div class="spend-cell-inner">
                                    <span>{{ $ebay_spent }}</span>
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline spend-chart-btn" data-channel="EBAY" data-bs-toggle="modal" data-bs-target="#dailySpendModal" title="View daily spend">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#00b894" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/><path d="M8 3.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center">{{ $ebay_l60_spent ?? 0 }}</td>
                            <td class="text-center">
                                @php
                                    $ebay_grw = (($ebay_l60_spent ?? 0) > 0) ? ($ebay_spent / ($ebay_l60_spent ?? 1)) * 100 : 0;
                                    echo number_format($ebay_grw, 0) . '%';
                                @endphp
                            </td>
                            <td class="text-center td-l30-clicks">
                                <div class="clicks-cell-inner">
                                    <span>{{ $ebay_clicks }}</span>
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline clicks-chart-btn" data-channel="EBAY" data-bs-toggle="modal" data-bs-target="#dailyClicksModal" title="View daily clicks">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#00b894" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/><path d="M8 3.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center">{{ $ebay_l60_clicks ?? 0 }}</td>
                            <td class="text-center">
                                @php
                                    $grw_clks = (($ebay_l60_clicks ?? 0) > 0) ? ($ebay_clicks / ($ebay_l60_clicks ?? 1)) * 100 : 0;
                                    echo number_format($grw_clks, 0) . '%';
                                @endphp
                            </td>
                            <td class="text-center">
                                @php
                                    $ebay_cpc = ($ebay_clicks > 0) ? ($ebay_spent / $ebay_clicks) : 0;
                                    echo number_format($ebay_cpc, 2);
                                @endphp
                            </td>
                            <td class="text-center">
                                @php
                                    $ebay_impressions = ($ebay_impressions ?? 0);
                                    $ebay_ctr = ($ebay_impressions > 0) ? (($ebay_clicks / $ebay_impressions) * 100) : 0;
                                    echo number_format($ebay_ctr, 2) . '%';
                                @endphp
                            </td>
                            <td class="text-center">
                                @php
                                    $ebay_cps = ($ebay_ad_sold > 0) ? ($ebay_spent / $ebay_ad_sold) : 0;
                                    echo number_format($ebay_cps, 0);
                                @endphp
                            </td>
                            <td class="text-center td-l30-adsales">
                                <div class="adsales-cell-inner">
                                    <span>{{ $ebay_ad_sales }}</span>
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline ad-sales-chart-btn" data-channel="EBAY" data-bs-toggle="modal" data-bs-target="#dailyAdSalesModal" title="View daily ad sales">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#00b894" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/><path d="M8 3.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center td-l30-acos">
                                <div class="acos-cell-inner">
                                    <span>
                                        @php
                                            if($ebay_ad_sales > 0){
                                                $acos = ($ebay_spent/$ebay_ad_sales)*100;
                                                $acos = number_format($acos, 2);
                                            }else{
                                                $acos = 0;
                                            }
                                            $acos = round($acos);
                                        @endphp
                                        {{ $acos.' %'  }}
                                    </span>
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline acos-chart-btn" data-channel="EBAY" data-bs-toggle="modal" data-bs-target="#dailyAcosModal" title="View daily ACOS">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#00b894" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/><path d="M8 3.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center">
                                @php
                                    if(($ebay_l60_ad_sales ?? 0) > 0){
                                        $l60_acos = (($ebay_l60_spent ?? 0) / ($ebay_l60_ad_sales ?? 1)) * 100;
                                        $l60_acos = number_format($l60_acos, 2);
                                    }else{
                                        $l60_acos = 0;
                                    }
                                    $l60_acos = round($l60_acos);
                                @endphp
                                {{ $l60_acos.' %'  }}
                            </td>
                            <td class="text-center">
                                @php
                                    $l30_acos_val = ($ebay_ad_sales > 0) ? ($ebay_spent/$ebay_ad_sales)*100 : 0;
                                    $l60_acos_val = (($ebay_l60_ad_sales ?? 0) > 0) ? (($ebay_l60_spent ?? 0) / ($ebay_l60_ad_sales ?? 1)) * 100 : 0;
                                    if($l60_acos_val > 0){
                                        $ctrl_acos = (($l30_acos_val - $l60_acos_val) / $l60_acos_val) * 100;
                                    }else{
                                        $ctrl_acos = 0;
                                    }
                                    $ctrl_acos = number_format($ctrl_acos, 2);
                                    $ctrl_color = ($ctrl_acos < 0) ? 'color: #006400;' : 'color: #8B0000;';
                                @endphp
                                <span style="{{ $ctrl_color }}">{{ $ctrl_acos.' %'  }}</span>
                            </td>
                            <td class="text-center">
                                @php
                                    if($ebay_l30_sales > 0){
                                        $tacos = ($ebay_spent/$ebay_l30_sales)*100;
                                        $tacos = number_format($tacos, 2);
                                    }else{
                                        $tacos = 0;
                                    }
                                    $tacos = round($tacos);
                                @endphp
                                {{ $tacos.' %'  }}
                            </td>
                            <td class="text-center">{{ $ebay_ad_sold }}</td>
                            <td class="text-center">
                                @php
                                    if($ebay_clicks > 0){
                                        $cvr = ($ebay_ad_sold/$ebay_clicks)*100;
                                        $cvr = number_format($cvr, 2);
                                    }else{
                                        $cvr = 0;
                                    }
                                    $cvr = round($cvr, 1);
                                    $ebay_cvr = $cvr; 
                                @endphp
                                {{ $cvr.' %' }}
                            </td>
                            <td class="text-center">
                                @php
                                    if(($ebay_l60_clicks ?? 0) > 0){
                                        $cvr_60 = (($ebay_l60_ad_sold ?? 0) / ($ebay_l60_clicks ?? 1)) * 100;
                                        $cvr_60 = number_format($cvr_60, 2);
                                    }else{
                                        $cvr_60 = 0;
                                    }
                                    $cvr_60 = round($cvr_60, 1);
                                @endphp
                                {{ $cvr_60.' %' }}
                            </td>
                            <td class="text-center">
                                @php
                                    $l30_cvr_val = ($ebay_clicks > 0) ? ($ebay_ad_sold/$ebay_clicks)*100 : 0;
                                    $l60_cvr_val = (($ebay_l60_clicks ?? 0) > 0) ? (($ebay_l60_ad_sold ?? 0) / ($ebay_l60_clicks ?? 1)) * 100 : 0;
                                    if($l60_cvr_val > 0){
                                        $grw_cvr = (($l30_cvr_val - $l60_cvr_val) / $l60_cvr_val) * 100;
                                    }else{
                                        $grw_cvr = 0;
                                    }
                                    $grw_cvr = number_format($grw_cvr, 0);
                                @endphp
                                {{ $grw_cvr.' %' }}
                            </td>
                            <td class="text-center">{{ $ebay_missing_ads }}</td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-warning edit-channel-btn" data-channel="EBAY" data-type="" title="Edit Channel">
                                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                                        <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5L13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.207l6.5-6.5zm-9.761 5.175l-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>

                        <tr class="accordion-body">
                            <td class="text-center"><a href="{{ route('ebay.keywords.ads') }}" target="_blank" style="text-decoration:none;">EB KW</a></td>
                            <td class="text-center"></td>
                            <td class="text-center"><a href="#" class="adv-l60-tab-link text-primary" title="View L60 Data">L60</a></td>
                            <td class="text-center"><a href="#" class="adv-l60-graph-link text-success" title="View L60 Graph">📈</a></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center td-l30-spent">
                                <div class="spend-cell-inner">
                                    <span>{{ $ebaykw_spent }}</span>
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline spend-chart-btn" data-channel="EB KW" data-bs-toggle="modal" data-bs-target="#dailySpendModal" title="View daily spend">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#00b894" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/><path d="M8 3.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center">{{ $ebaykw_l60_spent ?? 0 }}</td>
                            <td class="text-center">
                                @php
                                    $ebaykw_grw = (($ebaykw_l60_spent ?? 0) > 0) ? ($ebaykw_spent / ($ebaykw_l60_spent ?? 1)) * 100 : 0;
                                    echo number_format($ebaykw_grw, 0) . '%';
                                @endphp
                            </td>
                            <td class="text-center td-l30-clicks">
                                <div class="clicks-cell-inner">
                                    <span>{{ $ebaykw_clicks }}</span>
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline clicks-chart-btn" data-channel="EB KW" data-bs-toggle="modal" data-bs-target="#dailyClicksModal" title="View daily clicks">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#00b894" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/><path d="M8 3.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center">{{ $ebaykw_l60_clicks ?? 0 }}</td>
                            <td class="text-center">
                                @php
                                    $grw_clks = (($ebaykw_l60_clicks ?? 0) > 0) ? ($ebaykw_clicks / ($ebaykw_l60_clicks ?? 1)) * 100 : 0;
                                    echo number_format($grw_clks, 0) . '%';
                                @endphp
                            </td>
                            <td class="text-center">
                                @php
                                    $ebaykw_cpc = ($ebaykw_clicks > 0) ? ($ebaykw_spent / $ebaykw_clicks) : 0;
                                    echo number_format($ebaykw_cpc, 2);
                                @endphp
                            </td>
                            <td class="text-center">
                                @php
                                    $ebaykw_impressions = ($ebaykw_impressions ?? 0);
                                    $ebaykw_ctr = ($ebaykw_impressions > 0) ? (($ebaykw_clicks / $ebaykw_impressions) * 100) : 0;
                                    echo number_format($ebaykw_ctr, 2) . '%';
                                @endphp
                            </td>
                            <td class="text-center">
                                @php
                                    $ebaykw_cps = ($ebaykw_ad_sold > 0) ? ($ebaykw_spent / $ebaykw_ad_sold) : 0;
                                    echo number_format($ebaykw_cps, 0);
                                @endphp
                            </td>
                            <td class="text-center td-l30-adsales">
                                <div class="adsales-cell-inner">
                                    <span>{{ $ebaykw_ad_sales }}</span>
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline ad-sales-chart-btn" data-channel="EB KW" data-bs-toggle="modal" data-bs-target="#dailyAdSalesModal" title="View daily ad sales">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#00b894" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/><path d="M8 3.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center td-l30-acos">
                                <div class="acos-cell-inner">
                                    <span>
                                        @php
                                            if($ebaykw_ad_sales > 0){
                                                $acos = ($ebaykw_spent/$ebaykw_ad_sales)*100;
                                                $acos = number_format($acos, 2);
                                            }else{
                                                $acos = 0;
                                            }
                                            $acos = round($acos);
                                        @endphp
                                        {{ $acos.' %'  }}
                                    </span>
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline acos-chart-btn" data-channel="EB KW" data-bs-toggle="modal" data-bs-target="#dailyAcosModal" title="View daily ACOS">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#00b894" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/><path d="M8 3.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center">
                                @php
                                    if(($ebaykw_l60_ad_sales ?? 0) > 0){
                                        $l60_acos = (($ebaykw_l60_spent ?? 0) / ($ebaykw_l60_ad_sales ?? 1)) * 100;
                                        $l60_acos = number_format($l60_acos, 2);
                                    }else{
                                        $l60_acos = 0;
                                    }
                                    $l60_acos = round($l60_acos);
                                @endphp
                                {{ $l60_acos.' %'  }}
                            </td>
                            <td class="text-center">
                                @php
                                    $l30_acos_val = ($ebaykw_ad_sales > 0) ? ($ebaykw_spent/$ebaykw_ad_sales)*100 : 0;
                                    $l60_acos_val = (($ebaykw_l60_ad_sales ?? 0) > 0) ? (($ebaykw_l60_spent ?? 0) / ($ebaykw_l60_ad_sales ?? 1)) * 100 : 0;
                                    if($l60_acos_val > 0){
                                        $ctrl_acos = (($l30_acos_val - $l60_acos_val) / $l60_acos_val) * 100;
                                    }else{
                                        $ctrl_acos = 0;
                                    }
                                    $ctrl_acos = number_format($ctrl_acos, 2);
                                    $ctrl_color = ($ctrl_acos < 0) ? 'color: #006400;' : 'color: #8B0000;';
                                @endphp
                                <span style="{{ $ctrl_color }}">{{ $ctrl_acos.' %'  }}</span>
                            </td>
                            <td class="text-center"></td>
                            <td class="text-center">{{ $ebaykw_ad_sold }}</td>
                            <td class="text-center">
                                @php
                                    if($ebaykw_clicks > 0){
                                        $cvr = ($ebaykw_ad_sold/$ebaykw_clicks)*100;
                                        $cvr = number_format($cvr, 2);
                                    }else{
                                        $cvr = 0;
                                    }
                                    $cvr = round($cvr, 1);
                                @endphp
                                {{ $cvr.' %' }}
                            </td>
                            <td class="text-center">
                                @php
                                    if(($ebaykw_l60_clicks ?? 0) > 0){
                                        $cvr_60 = (($ebaykw_l60_ad_sold ?? 0) / ($ebaykw_l60_clicks ?? 1)) * 100;
                                        $cvr_60 = number_format($cvr_60, 2);
                                    }else{
                                        $cvr_60 = 0;
                                    }
                                    $cvr_60 = round($cvr_60, 1);
                                @endphp
                                {{ $cvr_60.' %' }}
                            </td>
                            <td class="text-center">
                                @php
                                    $l30_cvr_val = ($ebaykw_clicks > 0) ? ($ebaykw_ad_sold/$ebaykw_clicks)*100 : 0;
                                    $l60_cvr_val = (($ebaykw_l60_clicks ?? 0) > 0) ? (($ebaykw_l60_ad_sold ?? 0) / ($ebaykw_l60_clicks ?? 1)) * 100 : 0;
                                    if($l60_cvr_val > 0){
                                        $grw_cvr = (($l30_cvr_val - $l60_cvr_val) / $l60_cvr_val) * 100;
                                    }else{
                                        $grw_cvr = 0;
                                    }
                                    $grw_cvr = number_format($grw_cvr, 0);
                                @endphp
                                {{ $grw_cvr.' %' }}
                            </td>
                            <td class="text-center">{{ $ebaykw_missing_ads }}</td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-warning edit-channel-btn" data-channel="EB KW" data-type="" title="Edit Channel">
                                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                                        <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5L13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.207l6.5-6.5zm-9.761 5.175l-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>

                        <tr class="accordion-body">
                            <td class="text-center"><a href="{{ route('ebay.pmp.ads') }}" target="_blank" style="text-decoration:none;">EB PMT</a></td>
                            <td class="text-center"></td>
                            <td class="text-center"><a href="#" class="adv-l60-tab-link text-primary" title="View L60 Data">L60</a></td>
                            <td class="text-center"><a href="#" class="adv-l60-graph-link text-success" title="View L60 Graph">📈</a></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center td-l30-spent">
                                <div class="spend-cell-inner">
                                    <span>{{ $ebaypmt_spent }}</span>
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline spend-chart-btn" data-channel="EB PMT" data-bs-toggle="modal" data-bs-target="#dailySpendModal" title="View daily spend">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#00b894" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/><path d="M8 3.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center">{{ $ebaypmt_l60_spent ?? 0 }}</td>
                            <td class="text-center">
                                @php
                                    $ebaypmt_grw = (($ebaypmt_l60_spent ?? 0) > 0) ? ($ebaypmt_spent / ($ebaypmt_l60_spent ?? 1)) * 100 : 0;
                                    echo number_format($ebaypmt_grw, 0) . '%';
                                @endphp
                            </td>
                            <td class="text-center td-l30-clicks">
                                <div class="clicks-cell-inner">
                                    <span>{{ $ebaypmt_clicks }}</span>
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline clicks-chart-btn" data-channel="EB PMT" data-bs-toggle="modal" data-bs-target="#dailyClicksModal" title="View daily clicks">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#00b894" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/><path d="M8 3.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center">{{ $ebaypmt_l60_clicks ?? 0 }}</td>
                            <td class="text-center">
                                @php
                                    $grw_clks = (($ebaypmt_l60_clicks ?? 0) > 0) ? ($ebaypmt_clicks / ($ebaypmt_l60_clicks ?? 1)) * 100 : 0;
                                    echo number_format($grw_clks, 0) . '%';
                                @endphp
                            </td>
                            <td class="text-center">
                                @php
                                    $ebaypmt_cpc = ($ebaypmt_clicks > 0) ? ($ebaypmt_spent / $ebaypmt_clicks) : 0;
                                    echo number_format($ebaypmt_cpc, 2);
                                @endphp
                            </td>
                            <td class="text-center">
                                @php
                                    $ebaypmt_impressions = ($ebaypmt_impressions ?? 0);
                                    $ebaypmt_ctr = ($ebaypmt_impressions > 0) ? (($ebaypmt_clicks / $ebaypmt_impressions) * 100) : 0;
                                    echo number_format($ebaypmt_ctr, 2) . '%';
                                @endphp
                            </td>
                            <td class="text-center">
                                @php
                                    $ebaypmt_cps = ($ebaypmt_ad_sold > 0) ? ($ebaypmt_spent / $ebaypmt_ad_sold) : 0;
                                    echo number_format($ebaypmt_cps, 0);
                                @endphp
                            </td>
                            <td class="text-center td-l30-adsales">
                                <div class="adsales-cell-inner">
                                    <span>{{ $ebaypmt_ad_sales }}</span>
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline ad-sales-chart-btn" data-channel="EB PMT" data-bs-toggle="modal" data-bs-target="#dailyAdSalesModal" title="View daily ad sales">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#00b894" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/><path d="M8 3.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center td-l30-acos">
                                <div class="acos-cell-inner">
                                    <span>
                                        @php
                                            if($ebaypmt_ad_sales > 0){
                                                $acos = ($ebaypmt_spent/$ebaypmt_ad_sales)*100;
                                                $acos = number_format($acos, 2);
                                            }else{
                                                $acos = 0;
                                            }
                                            $acos = round($acos);
                                        @endphp
                                        {{ $acos.' %'  }}
                                    </span>
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline acos-chart-btn" data-channel="EB PMT" data-bs-toggle="modal" data-bs-target="#dailyAcosModal" title="View daily ACOS">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#00b894" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/><path d="M8 3.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center">
                                @php
                                    if(($ebaypmt_l60_ad_sales ?? 0) > 0){
                                        $l60_acos = (($ebaypmt_l60_spent ?? 0) / ($ebaypmt_l60_ad_sales ?? 1)) * 100;
                                        $l60_acos = number_format($l60_acos, 2);
                                    }else{
                                        $l60_acos = 0;
                                    }
                                    $l60_acos = round($l60_acos);
                                @endphp
                                {{ $l60_acos.' %'  }}
                            </td>
                            <td class="text-center">
                                @php
                                    $l30_acos_val = ($ebaypmt_ad_sales > 0) ? ($ebaypmt_spent/$ebaypmt_ad_sales)*100 : 0;
                                    $l60_acos_val = (($ebaypmt_l60_ad_sales ?? 0) > 0) ? (($ebaypmt_l60_spent ?? 0) / ($ebaypmt_l60_ad_sales ?? 1)) * 100 : 0;
                                    if($l60_acos_val > 0){
                                        $ctrl_acos = (($l30_acos_val - $l60_acos_val) / $l60_acos_val) * 100;
                                    }else{
                                        $ctrl_acos = 0;
                                    }
                                    $ctrl_acos = number_format($ctrl_acos, 2);
                                    $ctrl_color = ($ctrl_acos < 0) ? 'color: #006400;' : 'color: #8B0000;';
                                @endphp
                                <span style="{{ $ctrl_color }}">{{ $ctrl_acos.' %'  }}</span>
                            </td>
                            <td class="text-center"></td>
                            <td class="text-center">{{ $ebaypmt_ad_sold }}</td>
                            <td class="text-center">
                                @php
                                    if($ebaypmt_clicks > 0){
                                        $cvr = ($ebaypmt_ad_sold/$ebaypmt_clicks)*100;
                                        $cvr = number_format($cvr, 2);
                                    }else{
                                        $cvr = 0;
                                    }
                                    $cvr = round($cvr, 1);
                                @endphp
                                {{ $cvr.' %' }}
                            </td>
                            <td class="text-center">
                                @php
                                    if(($ebaypmt_l60_clicks ?? 0) > 0){
                                        $cvr_60 = (($ebaypmt_l60_ad_sold ?? 0) / ($ebaypmt_l60_clicks ?? 1)) * 100;
                                        $cvr_60 = number_format($cvr_60, 2);
                                    }else{
                                        $cvr_60 = 0;
                                    }
                                    $cvr_60 = round($cvr_60, 1);
                                @endphp
                                {{ $cvr_60.' %' }}
                            </td>
                            <td class="text-center">
                                @php
                                    $l30_cvr_val = ($ebaypmt_clicks > 0) ? ($ebaypmt_ad_sold/$ebaypmt_clicks)*100 : 0;
                                    $l60_cvr_val = (($ebaypmt_l60_clicks ?? 0) > 0) ? (($ebaypmt_l60_ad_sold ?? 0) / ($ebaypmt_l60_clicks ?? 1)) * 100 : 0;
                                    if($l60_cvr_val > 0){
                                        $grw_cvr = (($l30_cvr_val - $l60_cvr_val) / $l60_cvr_val) * 100;
                                    }else{
                                        $grw_cvr = 0;
                                    }
                                    $grw_cvr = number_format($grw_cvr, 0);
                                @endphp
                                {{ $grw_cvr.' %' }}
                            </td>
                            <td class="text-center">{{ $ebaypmt_missing_ads }}</td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-warning edit-channel-btn" data-channel="EB PMT" data-type="" title="Edit Channel">
                                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                                        <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5L13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.207l6.5-6.5zm-9.761 5.175l-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>

                        <tr class="accordion-header">
                            <td class="text-center">
                                <div class="d-flex align-items-center justify-content-center gap-2">
                                    <b>EBAY 2</b>
                                    <button type="button" class="btn btn-primary rounded-circle p-0 adv-channel-chart-btn" data-channel="EBAY 2" data-bs-toggle="modal" data-bs-target="#channelChartModal" title="View Graph">
                                        <svg width="12" height="12" viewBox="0 0 12 12" fill="white">
                                            <path d="M6 0L7.5 4.5L12 6L7.5 7.5L6 12L4.5 7.5L0 6L4.5 4.5L6 0Z"/>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center"></td>
                            <td class="text-center"><a href="#" class="adv-l60-tab-link text-primary" title="View L60 Data">L60</a></td>
                            <td class="text-center"><a href="#" class="adv-l60-graph-link text-success" title="View L60 Graph">📈</a></td>
                            <td class="text-center">{{ $ebay2_l30_sales }}</td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center td-l30-spent">
                                <div class="spend-cell-inner">
                                    <span>{{ $ebay2_spent }}</span>
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline spend-chart-btn" data-channel="EBAY 2" data-bs-toggle="modal" data-bs-target="#dailySpendModal" title="View daily spend">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#00b894" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/><path d="M8 3.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center">{{ $ebay2_l60_spent ?? 0 }}</td>
                            <td class="text-center">
                                @php
                                    $ebay2_grw = (($ebay2_l60_spent ?? 0) > 0) ? ($ebay2_spent / ($ebay2_l60_spent ?? 1)) * 100 : 0;
                                    echo number_format($ebay2_grw, 0) . '%';
                                @endphp
                            </td>
                            <td class="text-center td-l30-clicks">
                                <div class="clicks-cell-inner">
                                    <span>{{ $ebay2_clicks }}</span>
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline clicks-chart-btn" data-channel="EBAY 2" data-bs-toggle="modal" data-bs-target="#dailyClicksModal" title="View daily clicks">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#00b894" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/><path d="M8 3.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center">{{ $ebay2_l60_clicks ?? 0 }}</td>
                            <td class="text-center">
                                @php
                                    $grw_clks = (($ebay2_l60_clicks ?? 0) > 0) ? ($ebay2_clicks / ($ebay2_l60_clicks ?? 1)) * 100 : 0;
                                    echo number_format($grw_clks, 0) . '%';
                                @endphp
                            </td>
                            <td class="text-center">
                                @php
                                    $ebay2_cpc = ($ebay2_clicks > 0) ? ($ebay2_spent / $ebay2_clicks) : 0;
                                    echo number_format($ebay2_cpc, 2);
                                @endphp
                            </td>
                            <td class="text-center">
                                @php
                                    $ebay2_impressions = ($ebay2_impressions ?? 0);
                                    $ebay2_ctr = ($ebay2_impressions > 0) ? (($ebay2_clicks / $ebay2_impressions) * 100) : 0;
                                    echo number_format($ebay2_ctr, 2) . '%';
                                @endphp
                            </td>
                            <td class="text-center">
                                @php
                                    $ebay2_cps = ($ebay2_ad_sold > 0) ? ($ebay2_spent / $ebay2_ad_sold) : 0;
                                    echo number_format($ebay2_cps, 0);
                                @endphp
                            </td>
                            <td class="text-center td-l30-adsales">
                                <div class="adsales-cell-inner">
                                    <span>{{ $ebay2_ad_sales }}</span>
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline ad-sales-chart-btn" data-channel="EBAY 2" data-bs-toggle="modal" data-bs-target="#dailyAdSalesModal" title="View daily ad sales">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#00b894" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/><path d="M8 3.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center td-l30-acos">
                                <div class="acos-cell-inner">
                                    <span>
                                        @php
                                            if($ebay2_ad_sales > 0){
                                                $acos = ($ebay2_spent/$ebay2_ad_sales)*100;
                                                $acos = number_format($acos, 2);
                                            }else{
                                                $acos = 0;
                                            }
                                            $acos = round($acos);
                                        @endphp
                                        {{ $acos.' %'  }}
                                    </span>
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline acos-chart-btn" data-channel="EBAY 2" data-bs-toggle="modal" data-bs-target="#dailyAcosModal" title="View daily ACOS">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#00b894" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/><path d="M8 3.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center">
                                @php
                                    if(($ebay2_l60_ad_sales ?? 0) > 0){
                                        $l60_acos = (($ebay2_l60_spent ?? 0) / ($ebay2_l60_ad_sales ?? 1)) * 100;
                                        $l60_acos = number_format($l60_acos, 2);
                                    }else{
                                        $l60_acos = 0;
                                    }
                                    $l60_acos = round($l60_acos);
                                @endphp
                                {{ $l60_acos.' %'  }}
                            </td>
                            <td class="text-center">
                                @php
                                    $l30_acos_val = ($ebay2_ad_sales > 0) ? ($ebay2_spent/$ebay2_ad_sales)*100 : 0;
                                    $l60_acos_val = (($ebay2_l60_ad_sales ?? 0) > 0) ? (($ebay2_l60_spent ?? 0) / ($ebay2_l60_ad_sales ?? 1)) * 100 : 0;
                                    if($l60_acos_val > 0){
                                        $ctrl_acos = (($l30_acos_val - $l60_acos_val) / $l60_acos_val) * 100;
                                    }else{
                                        $ctrl_acos = 0;
                                    }
                                    $ctrl_acos = number_format($ctrl_acos, 2);
                                    $ctrl_color = ($ctrl_acos < 0) ? 'color: #006400;' : 'color: #8B0000;';
                                @endphp
                                <span style="{{ $ctrl_color }}">{{ $ctrl_acos.' %'  }}</span>
                            </td>
                            <td class="text-center">
                                @php
                                    if($ebay2_l30_sales > 0){
                                        $tacos = ($ebay2_spent/$ebay2_l30_sales)*100;
                                        $tacos = number_format($tacos, 2);
                                    }else{
                                        $tacos = 0;
                                    }
                                    $tacos = round($tacos);
                                @endphp
                                {{ $tacos.' %'  }}
                            </td>
                            <td class="text-center">{{ $ebay2_ad_sold }}</td>
                            <td class="text-center">
                                @php
                                    if($ebay2_clicks > 0){
                                        $cvr = ($ebay2_ad_sold/$ebay2_clicks)*100;
                                        $cvr = number_format($cvr, 2);
                                    }else{
                                        $cvr = 0;
                                    }
                                    $cvr = round($cvr, 1);
                                @endphp
                                {{ $cvr.' %' }}
                            </td>
                            <td class="text-center">
                                @php
                                    if(($ebay2_l60_clicks ?? 0) > 0){
                                        $cvr_60 = (($ebay2_l60_ad_sold ?? 0) / ($ebay2_l60_clicks ?? 1)) * 100;
                                        $cvr_60 = number_format($cvr_60, 2);
                                    }else{
                                        $cvr_60 = 0;
                                    }
                                    $cvr_60 = round($cvr_60, 1);
                                @endphp
                                {{ $cvr_60.' %' }}
                            </td>
                            <td class="text-center">
                                @php
                                    $l30_cvr_val = ($ebay2_clicks > 0) ? ($ebay2_ad_sold/$ebay2_clicks)*100 : 0;
                                    $l60_cvr_val = (($ebay2_l60_clicks ?? 0) > 0) ? (($ebay2_l60_ad_sold ?? 0) / ($ebay2_l60_clicks ?? 1)) * 100 : 0;
                                    if($l60_cvr_val > 0){
                                        $grw_cvr = (($l30_cvr_val - $l60_cvr_val) / $l60_cvr_val) * 100;
                                    }else{
                                        $grw_cvr = 0;
                                    }
                                    $grw_cvr = number_format($grw_cvr, 0);
                                @endphp
                                {{ $grw_cvr.' %' }}
                            </td>
                            <td class="text-center">{{ $ebay2_missing_ads }}</td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-warning edit-channel-btn" data-channel="EBAY 2" data-type="" title="Edit Channel">
                                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                                        <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5L13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.207l6.5-6.5zm-9.761 5.175l-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>

                        <tr class="accordion-body">
                            <td class="text-center">EB PMT</td>
                            <td class="text-center"></td>
                            <td class="text-center"><a href="#" class="adv-l60-tab-link text-primary" title="View L60 Data">L60</a></td>
                            <td class="text-center"><a href="#" class="adv-l60-graph-link text-success" title="View L60 Graph">📈</a></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center td-l30-spent">
                                <div class="spend-cell-inner">
                                    <span>{{ $ebay2pmt_spent }}</span>
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline spend-chart-btn" data-channel="EB PMT2" data-bs-toggle="modal" data-bs-target="#dailySpendModal" title="View daily spend">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#00b894" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/><path d="M8 3.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center">{{ $ebay2pmt_l60_spent ?? 0 }}</td>
                            <td class="text-center">
                                @php
                                    $grw = (($ebay2pmt_l60_spent ?? 0) > 0) ? ($ebay2pmt_spent / ($ebay2pmt_l60_spent ?? 1)) * 100 : 0;
                                    echo number_format($grw, 0) . '%';
                                @endphp
                            </td>
                            <td class="text-center td-l30-clicks">
                                <div class="clicks-cell-inner">
                                    <span>{{ $ebay2pmt_clicks }}</span>
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline clicks-chart-btn" data-channel="EB PMT2" data-bs-toggle="modal" data-bs-target="#dailyClicksModal" title="View daily clicks">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#00b894" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/><path d="M8 3.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center">{{ $ebay2pmt_l60_clicks ?? 0 }}</td>
                            <td class="text-center">
                                @php
                                    $grw_clks = (($ebay2pmt_l60_clicks ?? 0) > 0) ? ($ebay2pmt_clicks / ($ebay2pmt_l60_clicks ?? 1)) * 100 : 0;
                                    echo number_format($grw_clks, 0) . '%';
                                @endphp
                            </td>
                            <td class="text-center">
                                @php
                                    $ebay2pmt_cpc = ($ebay2pmt_clicks > 0) ? ($ebay2pmt_spent / $ebay2pmt_clicks) : 0;
                                    echo number_format($ebay2pmt_cpc, 2);
                                @endphp
                            </td>
                            <td class="text-center">
                                @php
                                    $ebay2pmt_impressions = ($ebay2pmt_impressions ?? 0);
                                    $ebay2pmt_ctr = ($ebay2pmt_impressions > 0) ? (($ebay2pmt_clicks / $ebay2pmt_impressions) * 100) : 0;
                                    echo number_format($ebay2pmt_ctr, 2) . '%';
                                @endphp
                            </td>
                            <td class="text-center">
                                @php
                                    $ebay2pmt_cps = ($ebay2pmt_ad_sold > 0) ? ($ebay2pmt_spent / $ebay2pmt_ad_sold) : 0;
                                    echo number_format($ebay2pmt_cps, 0);
                                @endphp
                            </td>
                            <td class="text-center td-l30-adsales">
                                <div class="adsales-cell-inner">
                                    <span>{{ $ebay2pmt_ad_sales }}</span>
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline ad-sales-chart-btn" data-channel="EB PMT2" data-bs-toggle="modal" data-bs-target="#dailyAdSalesModal" title="View daily ad sales">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#00b894" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/><path d="M8 3.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center td-l30-acos">
                                <div class="acos-cell-inner">
                                    <span>
                                        @php
                                            if($ebay2pmt_ad_sales > 0){
                                                $acos = ($ebay2pmt_spent/$ebay2pmt_ad_sales)*100;
                                                $acos = number_format($acos, 2);
                                            }else{
                                                $acos = 0;
                                            }
                                            $acos = round($acos);
                                        @endphp
                                        {{ $acos.' %'  }}
                                    </span>
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline acos-chart-btn" data-channel="EB PMT2" data-bs-toggle="modal" data-bs-target="#dailyAcosModal" title="View daily ACOS">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#00b894" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/><path d="M8 3.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center">
                                @php
                                    if(($ebay2pmt_l60_ad_sales ?? 0) > 0){
                                        $l60_acos = (($ebay2pmt_l60_spent ?? 0) / ($ebay2pmt_l60_ad_sales ?? 1)) * 100;
                                        $l60_acos = number_format($l60_acos, 2);
                                    }else{
                                        $l60_acos = 0;
                                    }
                                    $l60_acos = round($l60_acos);
                                @endphp
                                {{ $l60_acos.' %'  }}
                            </td>
                            <td class="text-center">
                                @php
                                    $l30_acos_val = ($ebay2pmt_ad_sales > 0) ? ($ebay2pmt_spent/$ebay2pmt_ad_sales)*100 : 0;
                                    $l60_acos_val = (($ebay2pmt_l60_ad_sales ?? 0) > 0) ? (($ebay2pmt_l60_spent ?? 0) / ($ebay2pmt_l60_ad_sales ?? 1)) * 100 : 0;
                                    if($l60_acos_val > 0){
                                        $ctrl_acos = (($l30_acos_val - $l60_acos_val) / $l60_acos_val) * 100;
                                    }else{
                                        $ctrl_acos = 0;
                                    }
                                    $ctrl_acos = number_format($ctrl_acos, 2);
                                    $ctrl_color = ($ctrl_acos < 0) ? 'color: #006400;' : 'color: #8B0000;';
                                @endphp
                                <span style="{{ $ctrl_color }}">{{ $ctrl_acos.' %'  }}</span>
                            </td>
                            <td class="text-center"></td>
                            <td class="text-center">{{ $ebay2pmt_ad_sold }}</td>
                            <td class="text-center">
                                @php
                                    if($ebay2pmt_clicks > 0){
                                        $cvr = ($ebay2pmt_ad_sold/$ebay2pmt_clicks)*100;
                                        $cvr = number_format($cvr, 2);
                                    }else{
                                        $cvr = 0;
                                    }
                                    $cvr = round($cvr, 1);
                                @endphp
                                {{ $cvr.' %' }}
                            </td>
                            <td class="text-center">
                                @php
                                    if(($ebay2pmt_l60_clicks ?? 0) > 0){
                                        $cvr_60 = (($ebay2pmt_l60_ad_sold ?? 0) / ($ebay2pmt_l60_clicks ?? 1)) * 100;
                                        $cvr_60 = number_format($cvr_60, 2);
                                    }else{
                                        $cvr_60 = 0;
                                    }
                                    $cvr_60 = round($cvr_60, 1);
                                @endphp
                                {{ $cvr_60.' %' }}
                            </td>
                            <td class="text-center">
                                @php
                                    $l30_cvr_val = ($ebay2pmt_clicks > 0) ? ($ebay2pmt_ad_sold/$ebay2pmt_clicks)*100 : 0;
                                    $l60_cvr_val = (($ebay2pmt_l60_clicks ?? 0) > 0) ? (($ebay2pmt_l60_ad_sold ?? 0) / ($ebay2pmt_l60_clicks ?? 1)) * 100 : 0;
                                    if($l60_cvr_val > 0){
                                        $grw_cvr = (($l30_cvr_val - $l60_cvr_val) / $l60_cvr_val) * 100;
                                    }else{
                                        $grw_cvr = 0;
                                    }
                                    $grw_cvr = number_format($grw_cvr, 0);
                                @endphp
                                {{ $grw_cvr.' %' }}
                            </td>
                            <td class="text-center">{{ $ebay2pmt_missing_ads }}</td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-warning edit-channel-btn" data-channel="EB PMT" data-type="" title="Edit Channel">
                                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                                        <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5L13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.207l6.5-6.5zm-9.761 5.175l-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>

                        <tr class="accordion-header">
                            <td class="text-center">
                                <div class="d-flex align-items-center justify-content-center gap-2">
                                    <b>EBAY 3</b>
                                    <button type="button" class="btn btn-primary rounded-circle p-0 adv-channel-chart-btn" data-channel="EBAY 3" data-bs-toggle="modal" data-bs-target="#channelChartModal" title="View Graph">
                                        <svg width="12" height="12" viewBox="0 0 12 12" fill="white">
                                            <path d="M6 0L7.5 4.5L12 6L7.5 7.5L6 12L4.5 7.5L0 6L4.5 4.5L6 0Z"/>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center"></td>
                            <td class="text-center"><a href="#" class="adv-l60-tab-link text-primary" title="View L60 Data">L60</a></td>
                            <td class="text-center"><a href="#" class="adv-l60-graph-link text-success" title="View L60 Graph">📈</a></td>
                            <td class="text-center">{{ $ebay3_l30_sales }}</td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center td-l30-spent">
                                <div class="spend-cell-inner">
                                    <span>{{ $ebay3_spent }}</span>
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline spend-chart-btn" data-channel="EBAY 3" data-bs-toggle="modal" data-bs-target="#dailySpendModal" title="View daily spend">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#00b894" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/><path d="M8 3.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center">{{ $ebay3_l60_spent ?? 0 }}</td>
                            <td class="text-center">
                                @php
                                    $grw = (($ebay3_l60_spent ?? 0) > 0) ? ($ebay3_spent / ($ebay3_l60_spent ?? 1)) * 100 : 0;
                                    echo number_format($grw, 0) . '%';
                                @endphp
                            </td>
                            <td class="text-center td-l30-clicks">
                                <div class="clicks-cell-inner">
                                    <span>{{ $ebay3_clicks }}</span>
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline clicks-chart-btn" data-channel="EBAY 3" data-bs-toggle="modal" data-bs-target="#dailyClicksModal" title="View daily clicks">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#00b894" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/><path d="M8 3.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center">{{ $ebay3_l60_clicks ?? 0 }}</td>
                            <td class="text-center">
                                @php
                                    $grw_clks = (($ebay3_l60_clicks ?? 0) > 0) ? ($ebay3_clicks / ($ebay3_l60_clicks ?? 1)) * 100 : 0;
                                    echo number_format($grw_clks, 0) . '%';
                                @endphp
                            </td>
                            <td class="text-center">
                                @php
                                    $ebay3_cpc = ($ebay3_clicks > 0) ? ($ebay3_spent / $ebay3_clicks) : 0;
                                    echo number_format($ebay3_cpc, 2);
                                @endphp
                            </td>
                            <td class="text-center">
                                @php
                                    $ebay3_impressions = ($ebay3_impressions ?? 0);
                                    $ebay3_ctr = ($ebay3_impressions > 0) ? (($ebay3_clicks / $ebay3_impressions) * 100) : 0;
                                    echo number_format($ebay3_ctr, 2) . '%';
                                @endphp
                            </td>
                            <td class="text-center">
                                @php
                                    $ebay3_cps = ($ebay3_ad_sold > 0) ? ($ebay3_spent / $ebay3_ad_sold) : 0;
                                    echo number_format($ebay3_cps, 0);
                                @endphp
                            </td>
                            <td class="text-center td-l30-adsales">
                                <div class="adsales-cell-inner">
                                    <span>{{ $ebay3_ad_sales }}</span>
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline ad-sales-chart-btn" data-channel="EBAY 3" data-bs-toggle="modal" data-bs-target="#dailyAdSalesModal" title="View daily ad sales">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#00b894" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/><path d="M8 3.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center td-l30-acos">
                                <div class="acos-cell-inner">
                                    <span>
                                        @php
                                            if($ebay3_ad_sales > 0){
                                                $acos = ($ebay3_spent/$ebay3_ad_sales)*100;
                                                $acos = number_format($acos, 2);
                                            }else{
                                                $acos = 0;
                                            }
                                            $acos = round($acos);
                                        @endphp
                                        {{ $acos.' %'  }}
                                    </span>
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline acos-chart-btn" data-channel="EBAY 3" data-bs-toggle="modal" data-bs-target="#dailyAcosModal" title="View daily ACOS">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#00b894" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/><path d="M8 3.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center">
                                @php
                                    if(($ebay3_l60_ad_sales ?? 0) > 0){
                                        $l60_acos = (($ebay3_l60_spent ?? 0) / ($ebay3_l60_ad_sales ?? 1)) * 100;
                                        $l60_acos = number_format($l60_acos, 2);
                                    }else{
                                        $l60_acos = 0;
                                    }
                                    $l60_acos = round($l60_acos);
                                @endphp
                                {{ $l60_acos.' %'  }}
                            </td>
                            <td class="text-center">
                                @php
                                    $l30_acos_val = ($ebay3_ad_sales > 0) ? ($ebay3_spent/$ebay3_ad_sales)*100 : 0;
                                    $l60_acos_val = (($ebay3_l60_ad_sales ?? 0) > 0) ? (($ebay3_l60_spent ?? 0) / ($ebay3_l60_ad_sales ?? 1)) * 100 : 0;
                                    if($l60_acos_val > 0){
                                        $ctrl_acos = (($l30_acos_val - $l60_acos_val) / $l60_acos_val) * 100;
                                    }else{
                                        $ctrl_acos = 0;
                                    }
                                    $ctrl_acos = number_format($ctrl_acos, 2);
                                    $ctrl_color = ($ctrl_acos < 0) ? 'color: #006400;' : 'color: #8B0000;';
                                @endphp
                                <span style="{{ $ctrl_color }}">{{ $ctrl_acos.' %'  }}</span>
                            </td>
                            <td class="text-center">
                                @php
                                    if($ebay3_l30_sales > 0){
                                        $tacos = ($ebay3_spent/$ebay3_l30_sales)*100;
                                        $tacos = number_format($tacos, 2);
                                    }else{
                                        $tacos = 0;
                                    }
                                    $tacos = round($tacos);
                                @endphp
                                {{ $tacos.' %'  }}
                            </td>
                            <td class="text-center">{{ $ebay3_ad_sold }}</td>
                            <td class="text-center">
                                @php
                                    if($ebay3_clicks > 0){
                                        $cvr = ($ebay3_ad_sold/$ebay3_clicks)*100;
                                        $cvr = number_format($cvr, 2);
                                    }else{
                                        $cvr = 0;
                                    }
                                    $cvr = round($cvr, 1);
                                @endphp
                                {{ $cvr.' %' }}
                            </td>
                            <td class="text-center">
                                @php
                                    if(($ebay3_l60_clicks ?? 0) > 0){
                                        $cvr_60 = (($ebay3_l60_ad_sold ?? 0) / ($ebay3_l60_clicks ?? 1)) * 100;
                                        $cvr_60 = number_format($cvr_60, 2);
                                    }else{
                                        $cvr_60 = 0;
                                    }
                                    $cvr_60 = round($cvr_60, 1);
                                @endphp
                                {{ $cvr_60.' %' }}
                            </td>
                            <td class="text-center">
                                @php
                                    $l30_cvr_val = ($ebay3_clicks > 0) ? ($ebay3_ad_sold/$ebay3_clicks)*100 : 0;
                                    $l60_cvr_val = (($ebay3_l60_clicks ?? 0) > 0) ? (($ebay3_l60_ad_sold ?? 0) / ($ebay3_l60_clicks ?? 1)) * 100 : 0;
                                    if($l60_cvr_val > 0){
                                        $grw_cvr = (($l30_cvr_val - $l60_cvr_val) / $l60_cvr_val) * 100;
                                    }else{
                                        $grw_cvr = 0;
                                    }
                                    $grw_cvr = number_format($grw_cvr, 0);
                                @endphp
                                {{ $grw_cvr.' %' }}
                            </td>
                            <td class="text-center">{{ $ebay3_missing_ads }}</td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-warning edit-channel-btn" data-channel="EBAY 3" data-type="" title="Edit Channel">
                                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                                        <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5L13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.207l6.5-6.5zm-9.761 5.175l-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>

                         <tr class="accordion-body">
                            <td class="text-center">EB KW</td>
                            <td class="text-center"></td>
                            <td class="text-center"><a href="#" class="adv-l60-tab-link text-primary" title="View L60 Data">L60</a></td>
                            <td class="text-center"><a href="#" class="adv-l60-graph-link text-success" title="View L60 Graph">📈</a></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center td-l30-spent">
                                <div class="spend-cell-inner">
                                    <span>{{ $ebay3kw_spent }}</span>
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline spend-chart-btn" data-channel="EB KW3" data-bs-toggle="modal" data-bs-target="#dailySpendModal" title="View daily spend">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#00b894" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/><path d="M8 3.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center">{{ $ebay3kw_l60_spent ?? 0 }}</td>
                            <td class="text-center">
                                @php
                                    $grw = (($ebay3kw_l60_spent ?? 0) > 0) ? ($ebay3kw_spent / ($ebay3kw_l60_spent ?? 1)) * 100 : 0;
                                    echo number_format($grw, 0) . '%';
                                @endphp
                            </td>
                            <td class="text-center td-l30-clicks">
                                <div class="clicks-cell-inner">
                                    <span>{{ $ebay3kw_clicks }}</span>
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline clicks-chart-btn" data-channel="EB KW3" data-bs-toggle="modal" data-bs-target="#dailyClicksModal" title="View daily clicks">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#00b894" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/><path d="M8 3.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center">{{ $ebay3kw_l60_clicks ?? 0 }}</td>
                            <td class="text-center">
                                @php
                                    $grw_clks = (($ebay3kw_l60_clicks ?? 0) > 0) ? ($ebay3kw_clicks / ($ebay3kw_l60_clicks ?? 1)) * 100 : 0;
                                    echo number_format($grw_clks, 0) . '%';
                                @endphp
                            </td>
                            <td class="text-center">
                                @php
                                    $ebay3kw_cpc = ($ebay3kw_clicks > 0) ? ($ebay3kw_spent / $ebay3kw_clicks) : 0;
                                    echo number_format($ebay3kw_cpc, 2);
                                @endphp
                            </td>
                            <td class="text-center">
                                @php
                                    $ebay3kw_impressions = ($ebay3kw_impressions ?? 0);
                                    $ebay3kw_ctr = ($ebay3kw_impressions > 0) ? (($ebay3kw_clicks / $ebay3kw_impressions) * 100) : 0;
                                    echo number_format($ebay3kw_ctr, 2) . '%';
                                @endphp
                            </td>
                            <td class="text-center">
                                @php
                                    $ebay3kw_cps = ($ebay3kw_ad_sold > 0) ? ($ebay3kw_spent / $ebay3kw_ad_sold) : 0;
                                    echo number_format($ebay3kw_cps, 0);
                                @endphp
                            </td>
                            <td class="text-center td-l30-adsales">
                                <div class="adsales-cell-inner">
                                    <span>{{ $ebay3kw_ad_sales }}</span>
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline ad-sales-chart-btn" data-channel="EB KW3" data-bs-toggle="modal" data-bs-target="#dailyAdSalesModal" title="View daily ad sales">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#00b894" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/><path d="M8 3.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center td-l30-acos">
                                <div class="acos-cell-inner">
                                    <span>
                                        @php
                                            if($ebay3kw_ad_sales > 0){
                                                $acos = ($ebay3kw_spent/$ebay3kw_ad_sales)*100;
                                                $acos = number_format($acos, 2);
                                            }else{
                                                $acos = 0;
                                            }
                                            $acos = round($acos);
                                        @endphp
                                        {{ $acos.' %'  }}
                                    </span>
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline acos-chart-btn" data-channel="EB KW3" data-bs-toggle="modal" data-bs-target="#dailyAcosModal" title="View daily ACOS">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#00b894" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/><path d="M8 3.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center">
                                @php
                                    if(($ebay3kw_l60_ad_sales ?? 0) > 0){
                                        $l60_acos = (($ebay3kw_l60_spent ?? 0) / ($ebay3kw_l60_ad_sales ?? 1)) * 100;
                                        $l60_acos = number_format($l60_acos, 2);
                                    }else{
                                        $l60_acos = 0;
                                    }
                                    $l60_acos = round($l60_acos);
                                @endphp
                                {{ $l60_acos.' %'  }}
                            </td>
                            <td class="text-center">
                                @php
                                    $l30_acos_val = ($ebay3kw_ad_sales > 0) ? ($ebay3kw_spent/$ebay3kw_ad_sales)*100 : 0;
                                    $l60_acos_val = (($ebay3kw_l60_ad_sales ?? 0) > 0) ? (($ebay3kw_l60_spent ?? 0) / ($ebay3kw_l60_ad_sales ?? 1)) * 100 : 0;
                                    if($l60_acos_val > 0){
                                        $ctrl_acos = (($l30_acos_val - $l60_acos_val) / $l60_acos_val) * 100;
                                    }else{
                                        $ctrl_acos = 0;
                                    }
                                    $ctrl_acos = number_format($ctrl_acos, 2);
                                    $ctrl_color = ($ctrl_acos < 0) ? 'color: #006400;' : 'color: #8B0000;';
                                @endphp
                                <span style="{{ $ctrl_color }}">{{ $ctrl_acos.' %'  }}</span>
                            </td>
                            <td class="text-center"></td>
                            <td class="text-center">{{ $ebay3kw_ad_sold }}</td>
                            <td class="text-center">
                                @php
                                    if($ebay3kw_clicks > 0){
                                        $cvr = ($ebay3kw_ad_sold/$ebay3kw_clicks)*100;
                                        $cvr = number_format($cvr, 2);
                                    }else{
                                        $cvr = 0;
                                    }
                                    $cvr = round($cvr, 1);
                                @endphp
                                {{ $cvr.' %' }}
                            </td>
                            <td class="text-center">
                                @php
                                    if(($ebay3kw_l60_clicks ?? 0) > 0){
                                        $cvr_60 = (($ebay3kw_l60_ad_sold ?? 0) / ($ebay3kw_l60_clicks ?? 1)) * 100;
                                        $cvr_60 = number_format($cvr_60, 2);
                                    }else{
                                        $cvr_60 = 0;
                                    }
                                    $cvr_60 = round($cvr_60, 1);
                                @endphp
                                {{ $cvr_60.' %' }}
                            </td>
                            <td class="text-center">
                                @php
                                    $l30_cvr_val = ($ebay3kw_clicks > 0) ? ($ebay3kw_ad_sold/$ebay3kw_clicks)*100 : 0;
                                    $l60_cvr_val = (($ebay3kw_l60_clicks ?? 0) > 0) ? (($ebay3kw_l60_ad_sold ?? 0) / ($ebay3kw_l60_clicks ?? 1)) * 100 : 0;
                                    if($l60_cvr_val > 0){
                                        $grw_cvr = (($l30_cvr_val - $l60_cvr_val) / $l60_cvr_val) * 100;
                                    }else{
                                        $grw_cvr = 0;
                                    }
                                    $grw_cvr = number_format($grw_cvr, 0);
                                @endphp
                                {{ $grw_cvr.' %' }}
                            </td>
                            <td class="text-center">{{ $ebay3kw_missing_ads }}</td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-warning edit-channel-btn" data-channel="EB KW" data-type="" title="Edit Channel">
                                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                                        <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5L13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.207l6.5-6.5zm-9.761 5.175l-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>

                        <tr class="accordion-body">
                            <td class="text-center">EB PMT</td>
                            <td class="text-center"></td>
                            <td class="text-center"><a href="#" class="adv-l60-tab-link text-primary" title="View L60 Data">L60</a></td>
                            <td class="text-center"><a href="#" class="adv-l60-graph-link text-success" title="View L60 Graph">📈</a></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center td-l30-spent">
                                <div class="spend-cell-inner">
                                    <span>{{ $ebay3pmt_spent }}</span>
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline spend-chart-btn" data-channel="EB PMT3" data-bs-toggle="modal" data-bs-target="#dailySpendModal" title="View daily spend">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#00b894" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/><path d="M8 3.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center">{{ $ebay3pmt_l60_spent ?? 0 }}</td>
                            <td class="text-center">
                                @php
                                    $grw = (($ebay3pmt_l60_spent ?? 0) > 0) ? ($ebay3pmt_spent / ($ebay3pmt_l60_spent ?? 1)) * 100 : 0;
                                    echo number_format($grw, 0) . '%';
                                @endphp
                            </td>
                            <td class="text-center td-l30-clicks">
                                <div class="clicks-cell-inner">
                                    <span>{{ $ebay3pmt_clicks }}</span>
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline clicks-chart-btn" data-channel="EB PMT3" data-bs-toggle="modal" data-bs-target="#dailyClicksModal" title="View daily clicks">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#00b894" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/><path d="M8 3.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center">{{ $ebay3pmt_l60_clicks ?? 0 }}</td>
                            <td class="text-center">
                                @php
                                    $grw_clks = (($ebay3pmt_l60_clicks ?? 0) > 0) ? ($ebay3pmt_clicks / ($ebay3pmt_l60_clicks ?? 1)) * 100 : 0;
                                    echo number_format($grw_clks, 0) . '%';
                                @endphp
                            </td>
                            <td class="text-center">
                                @php
                                    $ebay3pmt_cpc = ($ebay3pmt_clicks > 0) ? ($ebay3pmt_spent / $ebay3pmt_clicks) : 0;
                                    echo number_format($ebay3pmt_cpc, 2);
                                @endphp
                            </td>
                            <td class="text-center">
                                @php
                                    $ebay3pmt_impressions = ($ebay3pmt_impressions ?? 0);
                                    $ebay3pmt_ctr = ($ebay3pmt_impressions > 0) ? (($ebay3pmt_clicks / $ebay3pmt_impressions) * 100) : 0;
                                    echo number_format($ebay3pmt_ctr, 2) . '%';
                                @endphp
                            </td>
                            <td class="text-center">
                                @php
                                    $ebay3pmt_cps = ($ebay3pmt_ad_sold > 0) ? ($ebay3pmt_spent / $ebay3pmt_ad_sold) : 0;
                                    echo number_format($ebay3pmt_cps, 0);
                                @endphp
                            </td>
                            <td class="text-center td-l30-adsales">
                                <div class="adsales-cell-inner">
                                    <span>{{ $ebay3pmt_ad_sales }}</span>
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline ad-sales-chart-btn" data-channel="EB PMT3" data-bs-toggle="modal" data-bs-target="#dailyAdSalesModal" title="View daily ad sales">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#00b894" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/><path d="M8 3.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center td-l30-acos">
                                <div class="acos-cell-inner">
                                    <span>
                                        @php
                                            if($ebay3pmt_ad_sales > 0){
                                                $acos = ($ebay3pmt_spent/$ebay3pmt_ad_sales)*100;
                                                $acos = number_format($acos, 2);
                                            }else{
                                                $acos = 0;
                                            }
                                            $acos = round($acos);
                                        @endphp
                                        {{ $acos.' %'  }}
                                    </span>
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline acos-chart-btn" data-channel="EB PMT3" data-bs-toggle="modal" data-bs-target="#dailyAcosModal" title="View daily ACOS">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#00b894" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/><path d="M8 3.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center">
                                @php
                                    if(($ebay3pmt_l60_ad_sales ?? 0) > 0){
                                        $l60_acos = (($ebay3pmt_l60_spent ?? 0) / ($ebay3pmt_l60_ad_sales ?? 1)) * 100;
                                        $l60_acos = number_format($l60_acos, 2);
                                    }else{
                                        $l60_acos = 0;
                                    }
                                    $l60_acos = round($l60_acos);
                                @endphp
                                {{ $l60_acos.' %'  }}
                            </td>
                            <td class="text-center">
                                @php
                                    $l30_acos_val = ($ebay3pmt_ad_sales > 0) ? ($ebay3pmt_spent/$ebay3pmt_ad_sales)*100 : 0;
                                    $l60_acos_val = (($ebay3pmt_l60_ad_sales ?? 0) > 0) ? (($ebay3pmt_l60_spent ?? 0) / ($ebay3pmt_l60_ad_sales ?? 1)) * 100 : 0;
                                    if($l60_acos_val > 0){
                                        $ctrl_acos = (($l30_acos_val - $l60_acos_val) / $l60_acos_val) * 100;
                                    }else{
                                        $ctrl_acos = 0;
                                    }
                                    $ctrl_acos = number_format($ctrl_acos, 2);
                                    $ctrl_color = ($ctrl_acos < 0) ? 'color: #006400;' : 'color: #8B0000;';
                                @endphp
                                <span style="{{ $ctrl_color }}">{{ $ctrl_acos.' %'  }}</span>
                            </td>
                            <td class="text-center"></td>
                            <td class="text-center">{{ $ebay3pmt_ad_sold }}</td>
                            <td class="text-center">
                                @php
                                    if($ebay3pmt_clicks > 0){
                                        $cvr = ($ebay3pmt_ad_sold/$ebay3pmt_clicks)*100;
                                        $cvr = number_format($cvr, 2);
                                    }else{
                                        $cvr = 0;
                                    }
                                    $cvr = round($cvr, 1);
                                @endphp
                                {{ $cvr.' %' }}
                            </td>
                            <td class="text-center">
                                @php
                                    if(($ebay3pmt_l60_clicks ?? 0) > 0){
                                        $cvr_60 = (($ebay3pmt_l60_ad_sold ?? 0) / ($ebay3pmt_l60_clicks ?? 1)) * 100;
                                        $cvr_60 = number_format($cvr_60, 2);
                                    }else{
                                        $cvr_60 = 0;
                                    }
                                    $cvr_60 = round($cvr_60, 1);
                                @endphp
                                {{ $cvr_60.' %' }}
                            </td>
                            <td class="text-center">
                                @php
                                    $l30_cvr_val = ($ebay3pmt_clicks > 0) ? ($ebay3pmt_ad_sold/$ebay3pmt_clicks)*100 : 0;
                                    $l60_cvr_val = (($ebay3pmt_l60_clicks ?? 0) > 0) ? (($ebay3pmt_l60_ad_sold ?? 0) / ($ebay3pmt_l60_clicks ?? 1)) * 100 : 0;
                                    if($l60_cvr_val > 0){
                                        $grw_cvr = (($l30_cvr_val - $l60_cvr_val) / $l60_cvr_val) * 100;
                                    }else{
                                        $grw_cvr = 0;
                                    }
                                    $grw_cvr = number_format($grw_cvr, 0);
                                @endphp
                                {{ $grw_cvr.' %' }}
                            </td>
                            <td class="text-center">{{ $ebay3pmt_missing_ads }}</td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-warning edit-channel-btn" data-channel="EB PMT" data-type="" title="Edit Channel">
                                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                                        <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5L13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.207l6.5-6.5zm-9.761 5.175l-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>

                        <tr class="accordion-header">
                            <td class="text-center">
                                <div class="d-flex align-items-center justify-content-center gap-2">
                                    <b>WALMART</b>
                                    <button type="button" class="btn btn-primary rounded-circle p-0 adv-channel-chart-btn" data-channel="WALMART" data-bs-toggle="modal" data-bs-target="#channelChartModal" title="View Graph">
                                        <svg width="12" height="12" viewBox="0 0 12 12" fill="white">
                                            <path d="M6 0L7.5 4.5L12 6L7.5 7.5L6 12L4.5 7.5L0 6L4.5 4.5L6 0Z"/>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center"></td>
                            <td class="text-center"><a href="#" class="adv-l60-tab-link text-primary" title="View L60 Data">L60</a></td>
                            <td class="text-center"><a href="#" class="adv-l60-graph-link text-success" title="View L60 Graph">📈</a></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center td-l30-spent">
                                <div class="spend-cell-inner">
                                    <span>{{ $walmart_spent }}</span>
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline spend-chart-btn" data-channel="WALMART" data-bs-toggle="modal" data-bs-target="#dailySpendModal" title="View daily spend">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#00b894" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/><path d="M8 3.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center">{{ $walmart_l60_spent ?? 0 }}</td>
                            <td class="text-center">
                                @php
                                    $grw = (($walmart_l60_spent ?? 0) > 0) ? ($walmart_spent / ($walmart_l60_spent ?? 1)) * 100 : 0;
                                    echo number_format($grw, 0) . '%';
                                @endphp
                            </td>
                            <td class="text-center td-l30-clicks">
                                <div class="clicks-cell-inner">
                                    <span>{{ $walmart_clicks }}</span>
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline clicks-chart-btn" data-channel="WALMART" data-bs-toggle="modal" data-bs-target="#dailyClicksModal" title="View daily clicks">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#00b894" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/><path d="M8 3.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center">{{ $walmart_l60_clicks ?? 0 }}</td>
                            <td class="text-center">
                                @php
                                    $grw_clks = (($walmart_l60_clicks ?? 0) > 0) ? ($walmart_clicks / ($walmart_l60_clicks ?? 1)) * 100 : 0;
                                    echo number_format($grw_clks, 0) . '%';
                                @endphp
                            </td>
                            <td class="text-center">
                                @php
                                    $walmart_cpc = ($walmart_clicks > 0) ? ($walmart_spent / $walmart_clicks) : 0;
                                    echo number_format($walmart_cpc, 2);
                                @endphp
                            </td>
                            <td class="text-center">
                                @php
                                    $walmart_impressions = ($walmart_impressions ?? 0);
                                    $walmart_ctr = ($walmart_impressions > 0) ? (($walmart_clicks / $walmart_impressions) * 100) : 0;
                                    echo number_format($walmart_ctr, 2) . '%';
                                @endphp
                            </td>
                            <td class="text-center">
                                @php
                                    $walmart_cps = ($walmart_ad_sold > 0) ? ($walmart_spent / $walmart_ad_sold) : 0;
                                    echo number_format($walmart_cps, 0);
                                @endphp
                            </td>
                            <td class="text-center td-l30-adsales">
                                <div class="adsales-cell-inner">
                                    <span>{{ $walmart_ad_sales }}</span>
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline ad-sales-chart-btn" data-channel="WALMART" data-bs-toggle="modal" data-bs-target="#dailyAdSalesModal" title="View daily ad sales">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#00b894" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/><path d="M8 3.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center td-l30-acos">
                                <div class="acos-cell-inner">
                                    <span>
                                        @php
                                            if($walmart_ad_sales > 0){
                                                $acos = ($walmart_spent/$walmart_ad_sales)*100;
                                                $acos = number_format($acos, 2);
                                            }else{
                                                $acos = 0;
                                            }
                                            $acos = round($acos);
                                        @endphp
                                        {{ $acos.' %'  }}
                                    </span>
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline acos-chart-btn" data-channel="WALMART" data-bs-toggle="modal" data-bs-target="#dailyAcosModal" title="View daily ACOS">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#00b894" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/><path d="M8 3.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center">
                                @php
                                    if(($walmart_l60_ad_sales ?? 0) > 0){
                                        $l60_acos = (($walmart_l60_spent ?? 0) / ($walmart_l60_ad_sales ?? 1)) * 100;
                                        $l60_acos = number_format($l60_acos, 2);
                                    }else{
                                        $l60_acos = 0;
                                    }
                                    $l60_acos = round($l60_acos);
                                @endphp
                                {{ $l60_acos.' %'  }}
                            </td>
                            <td class="text-center">
                                @php
                                    $l30_acos_val = ($walmart_ad_sales > 0) ? ($walmart_spent/$walmart_ad_sales)*100 : 0;
                                    $l60_acos_val = (($walmart_l60_ad_sales ?? 0) > 0) ? (($walmart_l60_spent ?? 0) / ($walmart_l60_ad_sales ?? 1)) * 100 : 0;
                                    if($l60_acos_val > 0){
                                        $ctrl_acos = (($l30_acos_val - $l60_acos_val) / $l60_acos_val) * 100;
                                    }else{
                                        $ctrl_acos = 0;
                                    }
                                    $ctrl_acos = number_format($ctrl_acos, 2);
                                    $ctrl_color = ($ctrl_acos < 0) ? 'color: #006400;' : 'color: #8B0000;';
                                @endphp
                                <span style="{{ $ctrl_color }}">{{ $ctrl_acos.' %'  }}</span>
                            </td>
                            <td class="text-center">
                              
                            </td>
                            <td class="text-center">{{ $walmart_ad_sold }}</td>
                            <td class="text-center">
                                @php
                                    if($walmart_clicks > 0){
                                        $cvr = ($walmart_ad_sold/$walmart_clicks)*100;
                                        $cvr = number_format($cvr, 2);
                                    }else{
                                        $cvr = 0;
                                    }
                                    $cvr = round($cvr, 1);
                                @endphp
                                {{ $cvr.' %' }}
                            </td>
                            <td class="text-center">
                                @php
                                    if(($walmart_l60_clicks ?? 0) > 0){
                                        $cvr_60 = (($walmart_l60_ad_sold ?? 0) / ($walmart_l60_clicks ?? 1)) * 100;
                                        $cvr_60 = number_format($cvr_60, 2);
                                    }else{
                                        $cvr_60 = 0;
                                    }
                                    $cvr_60 = round($cvr_60, 1);
                                @endphp
                                {{ $cvr_60.' %' }}
                            </td>
                            <td class="text-center">
                                @php
                                    $l30_cvr_val = ($walmart_clicks > 0) ? ($walmart_ad_sold/$walmart_clicks)*100 : 0;
                                    $l60_cvr_val = (($walmart_l60_clicks ?? 0) > 0) ? (($walmart_l60_ad_sold ?? 0) / ($walmart_l60_clicks ?? 1)) * 100 : 0;
                                    if($l60_cvr_val > 0){
                                        $grw_cvr = (($l30_cvr_val - $l60_cvr_val) / $l60_cvr_val) * 100;
                                    }else{
                                        $grw_cvr = 0;
                                    }
                                    $grw_cvr = number_format($grw_cvr, 0);
                                @endphp
                                {{ $grw_cvr.' %' }}
                            </td>
                            <td class="text-center">{{ $walmart_missing_ads }}</td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-warning edit-channel-btn" data-channel="WALMART" data-type="" title="Edit Channel">
                                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                                        <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5L13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.207l6.5-6.5zm-9.761 5.175l-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>

                        <tr class="accordion-header">
                            <td class="text-center"><b>SHOPIFY</b></td>
                            <td class="text-center"></td>
                            <td class="text-center"><a href="#" class="adv-l60-tab-link text-primary" title="View L60 Data">L60</a></td>
                            <td class="text-center"><a href="#" class="adv-l60-graph-link text-success" title="View L60 Graph">📈</a></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-warning edit-channel-btn" data-channel="SHOPIFY" data-type="" title="Edit Channel">
                                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                                        <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5L13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.207l6.5-6.5zm-9.761 5.175l-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>

                         <tr class="accordion-body">
                            <td class="text-center">
                                <div class="d-flex align-items-center justify-content-center gap-2">
                                    G SHOPPING
                                    <button type="button" class="btn btn-primary rounded-circle p-0 adv-channel-chart-btn" data-channel="G SHOPPING" data-bs-toggle="modal" data-bs-target="#channelChartModal" title="View Graph">
                                        <svg width="12" height="12" viewBox="0 0 12 12" fill="white">
                                            <path d="M6 0L7.5 4.5L12 6L7.5 7.5L6 12L4.5 7.5L0 6L4.5 4.5L6 0Z"/>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center"></td>
                            <td class="text-center"><a href="#" class="adv-l60-tab-link text-primary" title="View L60 Data">L60</a></td>
                            <td class="text-center"><a href="#" class="adv-l60-graph-link text-success" title="View L60 Graph">📈</a></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center td-l30-spent">
                                <div class="spend-cell-inner">
                                    <span>{{ $gshoping_spent }}</span>
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline spend-chart-btn" data-channel="G SHOPPING" data-bs-toggle="modal" data-bs-target="#dailySpendModal" title="View daily spend">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#00b894" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/><path d="M8 3.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center">{{ $gshoping_l60_spent ?? 0 }}</td>
                            <td class="text-center">
                                @php
                                    $grw = (($gshoping_l60_spent ?? 0) > 0) ? ($gshoping_spent / ($gshoping_l60_spent ?? 1)) * 100 : 0;
                                    echo number_format($grw, 0) . '%';
                                @endphp
                            </td>
                            <td class="text-center td-l30-clicks">
                                <div class="clicks-cell-inner">
                                    <span>{{ $gshoping_clicks }}</span>
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline clicks-chart-btn" data-channel="G SHOPPING" data-bs-toggle="modal" data-bs-target="#dailyClicksModal" title="View daily clicks">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#00b894" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/><path d="M8 3.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center">{{ $gshoping_l60_clicks ?? 0 }}</td>
                            <td class="text-center">
                                @php
                                    $grw_clks = (($gshoping_l60_clicks ?? 0) > 0) ? ($gshoping_clicks / ($gshoping_l60_clicks ?? 1)) * 100 : 0;
                                    echo number_format($grw_clks, 0) . '%';
                                @endphp
                            </td>
                            <td class="text-center">
                                @php
                                    $gshoping_cpc = ($gshoping_clicks > 0) ? ($gshoping_spent / $gshoping_clicks) : 0;
                                    echo number_format($gshoping_cpc, 2);
                                @endphp
                            </td>
                            <td class="text-center">
                                @php
                                    $gshoping_impressions = ($gshoping_impressions ?? 0);
                                    $gshoping_ctr = ($gshoping_impressions > 0) ? (($gshoping_clicks / $gshoping_impressions) * 100) : 0;
                                    echo number_format($gshoping_ctr, 2) . '%';
                                @endphp
                            </td>
                            <td class="text-center">
                                @php
                                    $gshoping_cps = ($gshoping_ad_sold > 0) ? ($gshoping_spent / $gshoping_ad_sold) : 0;
                                    echo number_format($gshoping_cps, 0);
                                @endphp
                            </td>
                            <td class="text-center td-l30-adsales">
                                <div class="adsales-cell-inner">
                                    <span>{{ $gshoping_ad_sales }}</span>
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline ad-sales-chart-btn" data-channel="G SHOPPING" data-bs-toggle="modal" data-bs-target="#dailyAdSalesModal" title="View daily ad sales">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#00b894" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/><path d="M8 3.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center td-l30-acos">
                                <div class="acos-cell-inner">
                                    <span>
                                        @php
                                            if($gshoping_ad_sales > 0){
                                                $acos = ($gshoping_spent/$gshoping_ad_sales)*100;
                                                $acos = number_format($acos, 2);
                                            }else{
                                                $acos = 0;
                                            }
                                            $acos = round($acos);
                                        @endphp
                                        {{ $acos.' %'  }}
                                    </span>
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline acos-chart-btn" data-channel="G SHOPPING" data-bs-toggle="modal" data-bs-target="#dailyAcosModal" title="View daily ACOS">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#00b894" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/><path d="M8 3.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center">
                                @php
                                    if(($gshoping_l60_ad_sales ?? 0) > 0){
                                        $l60_acos = (($gshoping_l60_spent ?? 0) / ($gshoping_l60_ad_sales ?? 1)) * 100;
                                        $l60_acos = number_format($l60_acos, 2);
                                    }else{
                                        $l60_acos = 0;
                                    }
                                    $l60_acos = round($l60_acos);
                                @endphp
                                {{ $l60_acos.' %'  }}
                            </td>
                            <td class="text-center">
                                @php
                                    $l30_acos_val = ($gshoping_ad_sales > 0) ? ($gshoping_spent/$gshoping_ad_sales)*100 : 0;
                                    $l60_acos_val = (($gshoping_l60_ad_sales ?? 0) > 0) ? (($gshoping_l60_spent ?? 0) / ($gshoping_l60_ad_sales ?? 1)) * 100 : 0;
                                    if($l60_acos_val > 0){
                                        $ctrl_acos = (($l30_acos_val - $l60_acos_val) / $l60_acos_val) * 100;
                                    }else{
                                        $ctrl_acos = 0;
                                    }
                                    $ctrl_acos = number_format($ctrl_acos, 2);
                                    $ctrl_color = ($ctrl_acos < 0) ? 'color: #006400;' : 'color: #8B0000;';
                                @endphp
                                <span style="{{ $ctrl_color }}">{{ $ctrl_acos.' %'  }}</span>
                            </td>
                            <td class="text-center"></td>
                            <td class="text-center">{{ $gshoping_ad_sold }}</td>
                            <td class="text-center">
                                @php
                                    if($gshoping_clicks > 0){
                                        $cvr = ($gshoping_ad_sold/$gshoping_clicks)*100;
                                        $cvr = number_format($cvr, 2);
                                    }else{
                                        $cvr = 0;
                                    }
                                    $cvr = round($cvr, 1);
                                @endphp
                                {{ $cvr.' %' }}
                            </td>
                            <td class="text-center">
                                @php
                                    if(($gshoping_l60_clicks ?? 0) > 0){
                                        $cvr_60 = (($gshoping_l60_ad_sold ?? 0) / ($gshoping_l60_clicks ?? 1)) * 100;
                                        $cvr_60 = number_format($cvr_60, 2);
                                    }else{
                                        $cvr_60 = 0;
                                    }
                                    $cvr_60 = round($cvr_60, 1);
                                @endphp
                                {{ $cvr_60.' %' }}
                            </td>
                            <td class="text-center">
                                @php
                                    $l30_cvr_val = ($gshoping_clicks > 0) ? ($gshoping_ad_sold/$gshoping_clicks)*100 : 0;
                                    $l60_cvr_val = (($gshoping_l60_clicks ?? 0) > 0) ? (($gshoping_l60_ad_sold ?? 0) / ($gshoping_l60_clicks ?? 1)) * 100 : 0;
                                    if($l60_cvr_val > 0){
                                        $grw_cvr = (($l30_cvr_val - $l60_cvr_val) / $l60_cvr_val) * 100;
                                    }else{
                                        $grw_cvr = 0;
                                    }
                                    $grw_cvr = number_format($grw_cvr, 0);
                                @endphp
                                {{ $grw_cvr.' %' }}
                            </td>
                            <td class="text-center">{{ $gshoping_missing_ads }}</td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-warning edit-channel-btn" data-channel="G SHOPPING" data-type="" title="Edit Channel">
                                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                                        <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5L13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.207l6.5-6.5zm-9.761 5.175l-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>

                         <tr class="accordion-body">
                            <td class="text-center">G SERP</td>
                            <td class="text-center"></td>
                            <td class="text-center"><a href="#" class="adv-l60-tab-link text-primary" title="View L60 Data">L60</a></td>
                            <td class="text-center"><a href="#" class="adv-l60-graph-link text-success" title="View L60 Graph">📈</a></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-warning edit-channel-btn" data-channel="G SERP" data-type="" title="Edit Channel">
                                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                                        <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5L13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.207l6.5-6.5zm-9.761 5.175l-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>

                        <tr class="accordion-body">
                            <td class="text-center">FB CARAOUSAL</td>
                            <td class="text-center"></td>
                            <td class="text-center"><a href="#" class="adv-l60-tab-link text-primary" title="View L60 Data">L60</a></td>
                            <td class="text-center"><a href="#" class="adv-l60-graph-link text-success" title="View L60 Graph">📈</a></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-warning edit-channel-btn" data-channel="FB CARAOUSAL" data-type="" title="Edit Channel">
                                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                                        <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5L13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.207l6.5-6.5zm-9.761 5.175l-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>

                        <tr class="accordion-body">
                            <td class="text-center">FB VIDEO</td>
                            <td class="text-center"></td>
                            <td class="text-center"><a href="#" class="adv-l60-tab-link text-primary" title="View L60 Data">L60</a></td>
                            <td class="text-center"><a href="#" class="adv-l60-graph-link text-success" title="View L60 Graph">📈</a></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-warning edit-channel-btn" data-channel="FB VIDEO" data-type="" title="Edit Channel">
                                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                                        <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5L13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.207l6.5-6.5zm-9.761 5.175l-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>

                        <tr class="accordion-body">
                            <td class="text-center">INSTA CARAOUSAL</td>
                            <td class="text-center"></td>
                            <td class="text-center"><a href="#" class="adv-l60-tab-link text-primary" title="View L60 Data">L60</a></td>
                            <td class="text-center"><a href="#" class="adv-l60-graph-link text-success" title="View L60 Graph">📈</a></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-warning edit-channel-btn" data-channel="INSTA CARAOUSAL" data-type="" title="Edit Channel">
                                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                                        <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5L13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.207l6.5-6.5zm-9.761 5.175l-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>

                        <tr class="accordion-body">
                            <td class="text-center">INSTA VIDEO</td>
                            <td class="text-center"></td>
                            <td class="text-center"><a href="#" class="adv-l60-tab-link text-primary" title="View L60 Data">L60</a></td>
                            <td class="text-center"><a href="#" class="adv-l60-graph-link text-success" title="View L60 Graph">📈</a></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-warning edit-channel-btn" data-channel="INSTA VIDEO" data-type="" title="Edit Channel">
                                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                                        <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5L13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.207l6.5-6.5zm-9.761 5.175l-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>

                        <tr class="accordion-body">
                            <td class="text-center">YOUTUBE</td>
                            <td class="text-center"></td>
                            <td class="text-center"><a href="#" class="adv-l60-tab-link text-primary" title="View L60 Data">L60</a></td>
                            <td class="text-center"><a href="#" class="adv-l60-graph-link text-success" title="View L60 Graph">📈</a></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-warning edit-channel-btn" data-channel="YOUTUBE" data-type="" title="Edit Channel">
                                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                                        <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5L13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.207l6.5-6.5zm-9.761 5.175l-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>

                        <tr class="accordion-header">
                            <td class="text-center"><b>TIKTOK</b></td>
                            <td class="text-center"></td>
                            <td class="text-center"><a href="#" class="adv-l60-tab-link text-primary" title="View L60 Data">L60</a></td>
                            <td class="text-center"><a href="#" class="adv-l60-graph-link text-success" title="View L60 Graph">📈</a></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-warning edit-channel-btn" data-channel="TIKTOK" data-type="" title="Edit Channel">
                                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                                        <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5L13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.207l6.5-6.5zm-9.761 5.175l-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>

                        @if(isset($additionalChannelsData) && count($additionalChannelsData) > 0)
                            @foreach($additionalChannelsData as $chData)
                                @php
                                    $ch = $chData['channel'];
                                    $grw = ($chData['l60_spent'] > 0) ? ($chData['spent'] / $chData['l60_spent']) * 100 : 0;
                                    $grw_clks = ($chData['l60_clicks'] > 0) ? ($chData['clicks'] / $chData['l60_clicks']) * 100 : 0;
                                    $l30_acos = ($chData['ad_sales'] > 0) ? ($chData['spent'] / $chData['ad_sales']) * 100 : 0;
                                    $l60_acos = ($chData['l60_ad_sold'] > 0 && $chData['l60_clicks'] > 0) ? (($chData['l60_spent'] / ($chData['l60_ad_sold'] ?? 1)) * 100) : 0;
                                    $ctrl_acos = ($l60_acos > 0) ? (($l30_acos - $l60_acos) / $l60_acos) * 100 : 0;
                                    $cvr = ($chData['clicks'] > 0) ? ($chData['ad_sold'] / $chData['clicks']) * 100 : 0;
                                    $cvr_60 = ($chData['l60_clicks'] > 0) ? ($chData['l60_ad_sold'] / $chData['l60_clicks']) * 100 : 0;
                                    $grw_cvr = ($cvr_60 > 0) ? (($cvr - $cvr_60) / $cvr_60) * 100 : 0;
                                @endphp
                                <tr class="accordion-header">
                                    <td class="text-center"><b>{{ $ch }}</b></td>
                                    <td class="text-center"></td>
                                    <td class="text-center"><a href="#" class="adv-l60-tab-link text-primary" title="View L60 Data">L60</a></td>
                                    <td class="text-center"><a href="#" class="adv-l60-graph-link text-success" title="View L60 Graph">📈</a></td>
                                    <td class="text-center">{{ number_format($chData['l30_sales'], 0) }}</td>
                                    <td class="text-center"></td>
                                    <td class="text-center"></td>
                                    <td class="text-center td-l30-spent">
                                        <div class="spend-cell-inner">
                                            <span>{{ number_format($chData['spent'], 2) }}</span>
                                            <button type="button" class="btn btn-link btn-sm p-0 align-baseline spend-chart-btn" data-channel="{{ $ch }}" data-bs-toggle="modal" data-bs-target="#dailySpendModal" title="View daily spend">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#00b894" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/><path d="M8 3.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg>
                                            </button>
                                        </div>
                                    </td>
                                    <td class="text-center">{{ number_format($chData['l60_spent'], 2) }}</td>
                                    <td class="text-center">{{ number_format($grw, 0) }}%</td>
                                    <td class="text-center td-l30-clicks">
                                        <div class="clicks-cell-inner">
                                            <span>{{ number_format($chData['clicks'], 0) }}</span>
                                            <button type="button" class="btn btn-link btn-sm p-0 align-baseline clicks-chart-btn" data-channel="{{ $ch }}" data-bs-toggle="modal" data-bs-target="#dailyClicksModal" title="View daily clicks">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#00b894" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/><path d="M8 3.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg>
                                            </button>
                                        </div>
                                    </td>
                                    <td class="text-center">{{ number_format($chData['l60_clicks'], 0) }}</td>
                                    <td class="text-center">{{ number_format($grw_clks, 0) }}%</td>
                                    <td class="text-center">
                                        @php
                                            $ch_cpc = ($chData['clicks'] > 0) ? ($chData['spent'] / $chData['clicks']) : 0;
                                            echo number_format($ch_cpc, 2);
                                        @endphp
                                    </td>
                                    <td class="text-center">
                                        @php
                                            $ch_impressions = ($chData['impressions'] ?? 0);
                                            $ch_ctr = ($ch_impressions > 0) ? (($chData['clicks'] / $ch_impressions) * 100) : 0;
                                            echo number_format($ch_ctr, 2) . '%';
                                        @endphp
                                    </td>
                                    <td class="text-center">
                                        @php
                                            $ch_cps = (($chData['ad_sold'] ?? 0) > 0) ? ($chData['spent'] / $chData['ad_sold']) : 0;
                                            echo number_format($ch_cps, 0);
                                        @endphp
                                    </td>
                                    <td class="text-center td-l30-adsales">
                                        <div class="adsales-cell-inner">
                                            <span>{{ number_format($chData['ad_sales'], 2) }}</span>
                                            <button type="button" class="btn btn-link btn-sm p-0 align-baseline ad-sales-chart-btn" data-channel="{{ $ch }}" data-bs-toggle="modal" data-bs-target="#dailyAdSalesModal" title="View daily ad sales">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#00b894" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/><path d="M8 3.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg>
                                            </button>
                                        </div>
                                    </td>
                                    <td class="text-center td-l30-acos">
                                        <div class="acos-cell-inner">
                                            <span>{{ number_format($l30_acos, 2) }}%</span>
                                            <button type="button" class="btn btn-link btn-sm p-0 align-baseline acos-chart-btn" data-channel="{{ $ch }}" data-bs-toggle="modal" data-bs-target="#dailyAcosModal" title="View daily ACOS">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#00b894" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/><path d="M8 3.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg>
                                            </button>
                                        </div>
                                    </td>
                                    <td class="text-center">{{ number_format($l60_acos, 2) }}%</td>
                                    <td class="text-center" style="color: {{ $ctrl_acos < 0 ? '#006400' : '#8B0000' }}">{{ number_format($ctrl_acos, 2) }}%</td>
                                    <td class="text-center">0</td>
                                    <td class="text-center">{{ number_format($chData['ad_sold'], 0) }}</td>
                                    <td class="text-center">{{ number_format($cvr, 2) }}%</td>
                                    <td class="text-center">{{ number_format($cvr_60, 2) }}%</td>
                                    <td class="text-center">{{ number_format($grw_cvr, 0) }}%</td>
                                    <td class="text-center">{{ number_format($chData['missing_ads'], 0) }}</td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-warning edit-channel-btn" data-channel="{{ $ch }}" data-type="{{ $chData['type'] ?? '' }}" title="Edit Channel">
                                            <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                                                <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5L13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.207l6.5-6.5zm-9.761 5.175l-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        @endif

                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- 1) Extra-large modal (standard Bootstrap) -->
    <div class="modal fade" id="amazonModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen"> <!-- modal-xl -->
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Amazon Graph</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="form-label text-muted small mb-2">From Date</label>
                                <input type="text" class="form-control amazon-from-date" name="amazon_from_date" onfocus="(this.type='date')" placeholder="Select from date" />
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="form-label text-muted small mb-2">To Date</label>
                                <input type="text" class="form-control amazon-to-date" name="amazon_to_date" onfocus="(this.type='date')" placeholder="Select to date"/>
                            </div>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button class="btn btn-success amazon-go w-100" name="amazon_go">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" style="margin-right: 6px; vertical-align: middle;">
                                    <path d="M8 0L10.5 6L16 8L10.5 10L8 16L5.5 10L0 8L5.5 6L8 0Z"/>
                                </svg>
                                Apply Filter
                            </button>
                        </div>
                        <div class="col-md-3"></div>
                    </div>
                    <div class="row">
                        <div class="col-md-2">
                            <div class="shadow p-3 mb-1 bg-white rounded">
                                <p class="title-label">Spend</p>
                                <p style="display: flex; align-items: center; justify-content: center; gap: 6px; margin: 0;">
                                    <span class="spend-color"></span>
                                    <b class="label-text">${{ $amazon_spent }}</b>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="shadow p-3 mb-1 bg-white rounded">
                                <p class="title-label">Clicks</p>
                                <p style="display: flex; align-items: center; justify-content: center; gap: 6px; margin: 0;">
                                    <span class="clicks-color"></span>
                                    <b class="label-text">{{ $amazon_clicks }}</b>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="shadow p-3 mb-1 bg-white rounded">
                                <p class="title-label">Ad-Sales</p>
                                <p style="display: flex; align-items: center; justify-content: center; gap: 6px; margin: 0;">
                                    <span class="adsales-color"></span>
                                    <b class="label-text">${{ $amazon_ad_sales }}</b>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="shadow p-3 mb-1 bg-white rounded">
                                <p class="title-label">Ad-Sold</p>
                                <p style="display: flex; align-items: center; justify-content: center; gap: 6px; margin: 0;">
                                    <span class="adsold-color"></span>
                                    <b class="label-text">${{ $amazon_ad_sold }}</b>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="shadow p-3 mb-1 bg-white rounded">
                                <p class="title-label">CPC</p>
                                <p style="display: flex; align-items: center; justify-content: center; gap: 6px; margin: 0;">
                                    <span class="cpc-color"></span>
                                    <b class="label-text"></b>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="shadow p-3 mb-1 bg-white rounded">
                                <p class="title-label">CVR</p>
                                <p style="display: flex; align-items: center; justify-content: center; gap: 6px; margin: 0;">
                                    <span class="cvr-color"></span>
                                    <b class="label-text">{{ $amazon_cvr ?? 0 }} %</b>
                                </p>
                            </div>
                        </div>
                    </div>
                   <canvas id="advMultiLineChart" height="100"></canvas>
                </div>
            </div>
        </div>
    </div>
    <!-- END AMAZON LARGE MODAL -->
 
    <!-- START EBAY LARGE MODAL -->
    <div class="modal fade" id="ebayModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen"> <!-- modal-xl -->
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Ebay Graph</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="form-label text-muted small mb-2">From Date</label>
                                <input type="text" class="form-control ebay-from-date" name="ebay_from_date" onfocus="(this.type='date')" placeholder="Select from date" />
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="form-label text-muted small mb-2">To Date</label>
                                <input type="text" class="form-control ebay-to-date" name="ebay_to_date" onfocus="(this.type='date')" placeholder="Select to date"/>
                            </div>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button class="btn btn-success ebay-go w-100" name="ebay_go">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" style="margin-right: 6px; vertical-align: middle;">
                                    <path d="M8 0L10.5 6L16 8L10.5 10L8 16L5.5 10L0 8L5.5 6L8 0Z"/>
                                </svg>
                                Apply Filter
                            </button>
                        </div>
                        <div class="col-md-3"></div>
                    </div>
                    <div class="row">
                        <div class="col-md-2">
                            <div class="shadow p-3 mb-1 bg-white rounded">
                                <p class="title-label">Spend</p>
                                <p style="display: flex; align-items: center; justify-content: center; gap: 6px; margin: 0;">
                                    <span class="spend-color"></span>
                                    <b class="label-text">${{ $ebay_spent }}</b>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="shadow p-3 mb-1 bg-white rounded">
                                <p class="title-label">Clicks</p>
                                <p style="display: flex; align-items: center; justify-content: center; gap: 6px; margin: 0;">
                                    <span class="clicks-color"></span>
                                    <b class="label-text">{{ $ebay_clicks }}</b>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="shadow p-3 mb-1 bg-white rounded">
                                <p class="title-label">Ad-Sales</p>
                                <p style="display: flex; align-items: center; justify-content: center; gap: 6px; margin: 0;">
                                    <span class="adsales-color"></span>
                                    <b class="label-text">${{ $ebay_ad_sales }}</b>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="shadow p-3 mb-1 bg-white rounded">
                                <p class="title-label">Ad-Sold</p>
                                <p style="display: flex; align-items: center; justify-content: center; gap: 6px; margin: 0;">
                                    <span class="adsold-color"></span>
                                    <b class="label-text">${{ $ebay_ad_sold }}</b>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="shadow p-3 mb-1 bg-white rounded">
                                <p class="title-label">CVR</p>
                                <p style="display: flex; align-items: center; justify-content: center; gap: 6px; margin: 0;">
                                    <span class="cvr-color"></span>
                                    <b class="label-text">{{ $ebay_cvr ?? 0 }} %</b>
                                </p>
                            </div>
                        </div>
                    </div>
                    <canvas id="advEbayChart" height="100"></canvas>
                </div>
            </div>
        </div>
    </div>
    <!-- END EBAY LARGE MODAL -->

    <!-- Channel Chart Modal (EBAY 2, EBAY 3, WALMART, G SHOPPING) -->
    <div class="modal fade" id="channelChartModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="channelChartModalTitle">Channel Graph</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="form-label text-muted small mb-2">From Date</label>
                                <input type="date" class="form-control" id="channelChartFromDate" placeholder="Select from date" />
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="form-label text-muted small mb-2">To Date</label>
                                <input type="date" class="form-control" id="channelChartToDate" placeholder="Select to date"/>
                            </div>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button class="btn btn-success w-100" id="channelChartApplyBtn">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" style="margin-right: 6px; vertical-align: middle;">
                                    <path d="M8 0L10.5 6L16 8L10.5 10L8 16L5.5 10L0 8L5.5 6L8 0Z"/>
                                </svg>
                                Apply Filter
                            </button>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-2">
                            <div class="shadow p-3 mb-1 bg-white rounded">
                                <p class="title-label">Spend</p>
                                <p style="display: flex; align-items: center; gap: 6px; margin: 0;">
                                    <span class="spend-color"></span>
                                    <b class="label-text" id="channelChartSpend">$0</b>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="shadow p-3 mb-1 bg-white rounded">
                                <p class="title-label">Clicks</p>
                                <p style="display: flex; align-items: center; gap: 6px; margin: 0;">
                                    <span class="clicks-color"></span>
                                    <b class="label-text" id="channelChartClicks">0</b>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="shadow p-3 mb-1 bg-white rounded">
                                <p class="title-label">Ad-Sales</p>
                                <p style="display: flex; align-items: center; gap: 6px; margin: 0;">
                                    <span class="adsales-color"></span>
                                    <b class="label-text" id="channelChartAdSales">$0</b>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="shadow p-3 mb-1 bg-white rounded">
                                <p class="title-label">Ad-Sold</p>
                                <p style="display: flex; align-items: center; gap: 6px; margin: 0;">
                                    <span class="adsold-color"></span>
                                    <b class="label-text" id="channelChartAdSold">$0</b>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="shadow p-3 mb-1 bg-white rounded">
                                <p class="title-label">CVR</p>
                                <p style="display: flex; align-items: center; gap: 6px; margin: 0;">
                                    <span class="cvr-color"></span>
                                    <b class="label-text" id="channelChartCvr">0 %</b>
                                </p>
                            </div>
                        </div>
                    </div>
                    <canvas id="channelChartCanvas" height="100"></canvas>
                </div>
            </div>
        </div>
    </div>
    <!-- END Channel Chart Modal -->

    <!-- Daily Clicks Chart Modal (eye icon on L30 CLKS column) -->
    <div class="modal fade daily-chart-modal" id="dailyClicksModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="dailyClicksModalTitle">Daily Clicks</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <span class="text-muted small">Period:</span>
                        <select class="form-select form-select-sm daily-chart-range" data-chart="clicks" style="width: auto; min-width: 140px;">
                            <option value="7">Last 7 Days</option>
                            <option value="30" selected>Last 30 Days</option>
                            <option value="60">Last 60 Days</option>
                            <option value="90">Last 90 Days</option>
                            <option value="lifetime">Lifetime</option>
                        </select>
                    </div>
                    <div id="dailyClicksNoData" class="alert alert-info mb-0" style="display: none;">No daily clicks data for this period. Data is stored daily when the cron runs.</div>
                    <div class="chart-enhanced-wrapper">
                        <div class="chart-wrapper">
                            <canvas id="dailyClicksCanvas" height="80"></canvas>
                        </div>
                        <div id="dailyClicksStats" class="chart-stats-panel" style="display: none;">
                            <div class="stat-block stat-highest">
                                <span class="stat-header">Highest</span>
                                <span class="stat-value" id="dailyClicksHighest">--</span>
                            </div>
                            <div class="stat-block stat-median">
                                <span class="stat-header">Median</span>
                                <span class="stat-value" id="dailyClicksMedian">--</span>
                            </div>
                            <div class="stat-block stat-lowest">
                                <span class="stat-header">Lowest</span>
                                <span class="stat-value" id="dailyClicksLowest">--</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- END Daily Clicks Chart Modal -->

    <!-- Daily Spend Chart Modal (eye icon on L30 SPENT column) -->
    <div class="modal fade daily-chart-modal" id="dailySpendModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="dailySpendModalTitle">Daily Spend</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <span class="text-muted small">Period:</span>
                        <select class="form-select form-select-sm daily-chart-range" data-chart="spend" style="width: auto; min-width: 140px;">
                            <option value="7">Last 7 Days</option>
                            <option value="30" selected>Last 30 Days</option>
                            <option value="60">Last 60 Days</option>
                            <option value="90">Last 90 Days</option>
                            <option value="lifetime">Lifetime</option>
                        </select>
                    </div>
                    <div id="dailySpendNoData" class="alert alert-info mb-0" style="display: none;">No daily spend data for this period. Data is stored daily when the cron runs.</div>
                    <div class="chart-enhanced-wrapper">
                        <div class="chart-wrapper">
                            <canvas id="dailySpendCanvas" height="80"></canvas>
                        </div>
                        <div id="dailySpendStats" class="chart-stats-panel" style="display: none;">
                            <div class="stat-block stat-highest">
                                <span class="stat-header">Highest</span>
                                <span class="stat-value" id="dailySpendHighest">--</span>
                            </div>
                            <div class="stat-block stat-median">
                                <span class="stat-header">Median</span>
                                <span class="stat-value" id="dailySpendMedian">--</span>
                            </div>
                            <div class="stat-block stat-lowest">
                                <span class="stat-header">Lowest</span>
                                <span class="stat-value" id="dailySpendLowest">--</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- END Daily Spend Chart Modal -->

    <!-- Daily Ad Sales Chart Modal (eye icon on AD SALES column) -->
    <div class="modal fade daily-chart-modal" id="dailyAdSalesModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="dailyAdSalesModalTitle">Daily Ad Sales</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <span class="text-muted small">Period:</span>
                        <select class="form-select form-select-sm daily-chart-range" data-chart="adsales" style="width: auto; min-width: 140px;">
                            <option value="7">Last 7 Days</option>
                            <option value="30" selected>Last 30 Days</option>
                            <option value="60">Last 60 Days</option>
                            <option value="90">Last 90 Days</option>
                            <option value="lifetime">Lifetime</option>
                        </select>
                    </div>
                    <div id="dailyAdSalesNoData" class="alert alert-info mb-0" style="display: none;">No daily ad sales data for this period. Data is stored daily when the cron runs.</div>
                    <div class="chart-enhanced-wrapper">
                        <div class="chart-wrapper">
                            <canvas id="dailyAdSalesCanvas" height="80"></canvas>
                        </div>
                        <div id="dailyAdSalesStats" class="chart-stats-panel" style="display: none;">
                            <div class="stat-block stat-highest">
                                <span class="stat-header">Highest</span>
                                <span class="stat-value" id="dailyAdSalesHighest">--</span>
                            </div>
                            <div class="stat-block stat-median">
                                <span class="stat-header">Median</span>
                                <span class="stat-value" id="dailyAdSalesMedian">--</span>
                            </div>
                            <div class="stat-block stat-lowest">
                                <span class="stat-header">Lowest</span>
                                <span class="stat-value" id="dailyAdSalesLowest">--</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- END Daily Ad Sales Chart Modal -->

    <!-- Daily ACOS Chart Modal (eye icon on L30 ACOS% column) -->
    <div class="modal fade daily-chart-modal" id="dailyAcosModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="dailyAcosModalTitle">Daily ACOS</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <span class="text-muted small">Period:</span>
                        <select class="form-select form-select-sm daily-chart-range" data-chart="acos" style="width: auto; min-width: 140px;">
                            <option value="7">Last 7 Days</option>
                            <option value="30" selected>Last 30 Days</option>
                            <option value="60">Last 60 Days</option>
                            <option value="90">Last 90 Days</option>
                            <option value="lifetime">Lifetime</option>
                        </select>
                    </div>
                    <div id="dailyAcosNoData" class="alert alert-info mb-0" style="display: none;">No daily ACOS data for this period. Data is stored daily when the cron runs.</div>
                    <div class="chart-enhanced-wrapper">
                        <div class="chart-wrapper">
                            <canvas id="dailyAcosCanvas" height="80"></canvas>
                        </div>
                        <div id="dailyAcosStats" class="chart-stats-panel" style="display: none;">
                            <div class="stat-block stat-highest">
                                <span class="stat-header">Highest</span>
                                <span class="stat-value" id="dailyAcosHighest">--</span>
                            </div>
                            <div class="stat-block stat-median">
                                <span class="stat-header">Median</span>
                                <span class="stat-value" id="dailyAcosMedian">--</span>
                            </div>
                            <div class="stat-block stat-lowest">
                                <span class="stat-header">Lowest</span>
                                <span class="stat-value" id="dailyAcosLowest">--</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- END Daily ACOS Chart Modal -->

    <!-- L60 Data Table Modal (stacked tabular) -->
    <div class="modal fade" id="adv-l60-tab-modal" tabindex="-1" aria-labelledby="adv-l60-tab-modal-label" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="adv-l60-tab-modal-label">L60 Data – All Rows</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="table-responsive" style="max-height: 70vh;">
                        <table class="table table-bordered table-striped mb-0" id="adv-l60-tab-table">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>#</th>
                                    <th>Channel</th>
                                    <th class="text-end">L60 SPENT</th>
                                    <th class="text-end">L60 CLICKS</th>
                                    <th class="text-end">CVR 60</th>
                                    <th class="text-end">Grw CVR</th>
                                </tr>
                            </thead>
                            <tbody id="adv-l60-tab-tbody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- L60 Data Graph Modal (stacked line graph) -->
    <div class="modal fade" id="adv-l60-graph-modal" tabindex="-1" aria-labelledby="adv-l60-graph-modal-label" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="adv-l60-graph-modal-label">L60 Data – Line Graph (All Channels)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label class="form-label">Select Metric:</label>
                            <select class="form-select" id="l60-graph-metric">
                                <option value="l60_spent">L60 SPENT</option>
                                <option value="l60_clicks">L60 CLICKS</option>
                                <option value="cvr_60">CVR 60</option>
                                <option value="grw_cvr">Grw CVR</option>
                            </select>
                        </div>
                    </div>
                    <div class="chart-container" style="position: relative; height: 500px;">
                        <canvas id="adv-l60-graph-canvas"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Channel Modal -->
    <div class="modal fade" id="addChannelModal" tabindex="-1" aria-labelledby="addChannelModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="addChannelModalLabel">
                        <svg width="18" height="18" viewBox="0 0 16 16" fill="currentColor" class="me-2" style="vertical-align: middle;">
                            <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
                        </svg>
                        Add New Channel
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addChannelForm">
                        @csrf
                        <div class="mb-3">
                            <label for="addChannelName" class="form-label">Channel Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="addChannelName" name="channel" required placeholder="e.g. AMAZON, EBAY" autocomplete="off">
                        </div>
                        <div class="mb-3">
                            <label for="addChannelSheetLink" class="form-label">Sheet Link</label>
                            <input type="url" class="form-control" id="addChannelSheetLink" name="sheet_link" placeholder="https://..." autocomplete="off">
                        </div>
                        <div class="mb-3">
                            <label for="addChannelAdType" class="form-label">Ad Type</label>
                            <input type="text" class="form-control" id="addChannelAdType" name="ad_type" placeholder="e.g. B2B, B2C, Dropship" autocomplete="off">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveChannelBtn">
                        <span class="btn-text">Save Channel</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Channel Modal -->
    <div class="modal fade" id="editChannelModal" tabindex="-1" aria-labelledby="editChannelModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="editChannelModalLabel">
                        <svg width="18" height="18" viewBox="0 0 16 16" fill="currentColor" class="me-2" style="vertical-align: middle;">
                            <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10z"/>
                        </svg>
                        Edit Channel
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editChannelForm">
                        @csrf
                        <input type="hidden" id="editOriginalChannel" name="original_channel">
                        <div class="mb-3">
                            <label for="editChannelName" class="form-label">Channel Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="editChannelName" name="channel" required placeholder="e.g. AMAZON, EBAY" autocomplete="off">
                        </div>
                        <div class="mb-3">
                            <label for="editChannelType" class="form-label">Ad Type</label>
                            <select class="form-select" id="editChannelType" name="type">
                                <option value="">Select type (optional)</option>
                                <option value="B2B">B2B</option>
                                <option value="B2C">B2C</option>
                                <option value="Dropship">Dropship</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-warning" id="saveEditChannelBtn">
                        <span class="btn-text">Save</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

@endsection

@section('script')
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
window.advMasterChannelWiseTotals = @json($channelWiseTotals ?? []);

$(document).ready(function() {

    /** Start Ebay Chart Ajax **/
    let performanceChartEbay;
    $(document).on('click', '.ebay-go', function(){
        let ebayFromDate = $('.ebay-from-date').val();
        let ebayToDate = $('.ebay-to-date').val();
        if((ebayFromDate == '' || ebayFromDate == undefined) || (ebayToDate == '' || ebayToDate == undefined)){
            alert('Please Select Dates !');
        }else{
            $.ajax({
                url: "{{ route('ebay.adv.chart.data') }}",
                method: 'GET',
                data: { ebayFromDate: ebayFromDate, ebayToDate: ebayToDate },
                beforeSend: function() {
                    if (performanceChartEbay) {
                        performanceChartEbay.destroy();
                    }
                },
                success: function(response) {
                   
                    const ctxEbay = document.getElementById('advEbayChart').getContext('2d');
                    if (performanceChartEbay) {
                        performanceChartEbay.destroy();
                    }
                    performanceChartEbay = new Chart(ctxEbay, {
                        type: 'line',
                        data: {
                            labels: response.ebayDateArray,
                            datasets: [
                                {
                                    label: 'Spend',
                                    data: response.ebaySpentArray,
                                    borderColor: '#6c2bd9',
                                    backgroundColor: '#6c2bd9',
                                    tension: 0.4,
                                    yAxisID: 'y',
                                    borderWidth: 3,
                                    pointRadius: 4,
                                    pointHoverRadius: 6,
                                    pointStyle: 'circle'
                                },
                                {
                                    label: 'Clicks',
                                    data: response.ebayclicksArray,
                                    borderColor: '#00b894',
                                    backgroundColor: '#00b894',
                                    tension: 0.4,
                                    yAxisID: 'y1',
                                    borderWidth: 3,
                                    pointRadius: 4,
                                    pointHoverRadius: 6,
                                    pointStyle: 'circle'
                                },
                                {
                                    label: 'AD-Sales',
                                    data: response.ebayadSalesArray,
                                    borderColor: '#ed0808fc',
                                    backgroundColor: '#ed0808fc',
                                    tension: 0.4,
                                    yAxisID: 'y2',
                                    borderWidth: 3,
                                    pointRadius: 4,
                                    pointHoverRadius: 6,
                                    pointStyle: 'circle'
                                },
                                {
                                    label: 'AD-Sold',
                                    data: response.ebayadSoldArray,
                                    borderColor: '#0984e3',
                                    backgroundColor: '#0984e3',
                                    tension: 0.4,
                                    yAxisID: 'y3',
                                    borderWidth: 3,
                                    pointRadius: 4,
                                    pointHoverRadius: 6,
                                    pointStyle: 'circle'
                                },
                                {
                                    label: 'CVR',
                                    data: response.ebayCvrArray,
                                    borderColor: '#f6da09ee',
                                    backgroundColor: '#f6da09ee',
                                    tension: 0.4,
                                    yAxisID: 'y4',
                                    borderWidth: 3,
                                    pointRadius: 4,
                                    pointHoverRadius: 6,
                                    pointStyle: 'circle'
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            interaction: { mode: 'index', intersect: false },
                            stacked: false,
                            plugins: {
                                legend: {
                                    display: false,
                                    position: 'top',
                                    labels: { usePointStyle: true, boxWidth: 14, padding: 50, font: { size: 13,
                                    weight: 'bold'
                                    }}
                                },
                                tooltip: {
                                backgroundColor: 'rgba(30, 41, 59, 0.95)', 
                                    titleColor: '#facc15',                     
                                    bodyColor: '#f8fafc',                      
                                    borderColor: '#334155',                    
                                    borderWidth: 1,
                                    padding: 12,
                                    cornerRadius: 10,
                                    titleFont: {
                                        size: 14,
                                        weight: 'bold',
                                        family: 'Inter, sans-serif'
                                    },
                                    bodyFont: {
                                        size: 13,
                                        family: 'Inter, sans-serif'
                                    },
                                    boxPadding: 6,
                                    displayColors: true,                       
                                    usePointStyle: true,                       
                                    caretPadding: 8,                           
                                    caretSize: 6,
                                    titleAlign: 'left',
                                    bodyAlign: 'left',
                                    callbacks: {
                                        label: function(context) {
                                        
                                            let label = context.dataset.label?.replace(/\s*\(.*?\)\s*/g, '') || '';
                                            let value = context.formattedValue;
                                            return `${label}  :   ${value}`;
                                        }
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    type: 'linear',
                                    display: true,
                                    position: 'left',
                                    grid: { display: true, drawBorder: false, color: '#e5e7eb' },
                                    ticks: { color: '#6c2bd9', callback: function (value) {
                                        if (value >= 1000000) return (value / 1000000).toFixed(1) + "M";
                                        if (value >= 1000) return (value / 1000).toFixed(1) + "K";
                                        return value;
                                    }},
                                    text: 'Spend',
                                },
                                y1: {
                                    type: 'linear',
                                    display: true,
                                    position: 'left',
                                    grid: { display: false, drawBorder: false, color: '#e5e7eb' },
                                    ticks: { color: '#00b894', callback: function (value) {
                                        if (value >= 1000000) return (value / 1000000).toFixed(1) + "M";
                                        if (value >= 1000) return (value / 1000).toFixed(1) + "K";
                                        return value;
                                    }},
                                    text: 'Clicks',
                                    border: {
                                        display: false       
                                    },
                                },
                                y2: {
                                    type: 'linear',
                                    display: true,
                                    position: 'left',
                                    grid: { display: false, drawBorder: false, color: '#e5e7eb' },
                                    ticks: { color: '#ed0808fc', callback: function (value) {
                                        if (value >= 1000000) return (value / 1000000).toFixed(1) + "M";
                                        if (value >= 1000) return (value / 1000).toFixed(1) + "K";
                                        return value;
                                    }},
                                    text: 'Ad-Sales',
                                    border: {
                                        display: false
                                    },
                                },
                                y3: {
                                    type: 'linear',
                                    display: true,
                                    position: 'right',
                                    grid: { display: false, drawBorder: false, color: '#e5e7eb' },
                                    ticks: { color: '#0984e3', callback: function (value) {
                                        if (value >= 1000000) return (value / 1000000).toFixed(1) + "M";
                                        if (value >= 1000) return (value / 1000).toFixed(1) + "K";
                                        return value;
                                    }},
                                    text: 'Ad-Sold',
                                },
                                y4: {
                                    type: 'linear',
                                    display: true,
                                    position: 'right',
                                    grid: { display: false, drawBorder: false, color: '#e5e7eb' },
                                    ticks: { color: '#f6da09ee' },
                                    text: 'CVR',
                                    border: {
                                        display: false       
                                    },
                                },
                                x: {
                                    grid: { display: false },
                                    ticks: { color: '#6b7280' }
                                }
                            }
                        }
                    });
                }
            });
        }
    });
    /** End Ebay Chart Ajax **/

    /** Start Amazon Chart Ajax **/
    let performanceChart;
    $(document).on('click', '.amazon-go', function(){
        let amazonFromDate = $('.amazon-from-date').val();
        let amazonToDate = $('.amazon-to-date').val();
        if((amazonFromDate == '' || amazonFromDate == undefined) || (amazonToDate == '' || amazonToDate == undefined)){
            alert('Please Select Dates !');
        }else{
            $.ajax({
                url: "{{ route('amazon.adv.chart.data') }}",
                method: 'GET',
                data: { amazonFromDate: amazonFromDate, amazonToDate: amazonToDate },
                beforeSend: function() {
                    if (performanceChart) {
                        performanceChart.destroy();
                    }
                },
                success: function(response) {

                    const ctx = document.getElementById('advMultiLineChart').getContext('2d');
                    performanceChart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: response.amazonDateArray,
                            datasets: [
                                {
                                    label: 'Spend',
                                    data: response.amazonSpentArray,
                                    borderColor: '#6c2bd9',
                                    backgroundColor: '#6c2bd9',
                                    tension: 0.4,
                                    yAxisID: 'y',
                                    borderWidth: 3,
                                    pointRadius: 4,
                                    pointHoverRadius: 6,
                                    pointStyle: 'circle'
                                },
                                {
                                    label: 'Clicks',
                                    data: response.amazonclicksArray,
                                    borderColor: '#00b894',
                                    backgroundColor: '#00b894',
                                    tension: 0.4,
                                    yAxisID: 'y1',
                                    borderWidth: 3,
                                    pointRadius: 4,
                                    pointHoverRadius: 6,
                                    pointStyle: 'circle'
                                },
                                {
                                    label: 'Ad-Sales',
                                    data: response.amazonadSalesArray,
                                    borderColor: '#ed0808fc',
                                    backgroundColor: '#ed0808fc',
                                    tension: 0.4,
                                    yAxisID: 'y2',
                                    borderWidth: 3,
                                    pointRadius: 4,
                                    pointHoverRadius: 6,
                                    pointStyle: 'circle'
                                },
                                {
                                    label: 'Ad-Sold',
                                    data: response.amzonadSoldArray,
                                    borderColor: '#0984e3',
                                    backgroundColor: '#0984e3',
                                    tension: 0.4,
                                    yAxisID: 'y3',
                                    borderWidth: 3,
                                    pointRadius: 4,
                                    pointHoverRadius: 6,
                                    pointStyle: 'circle'
                                },
                                {
                                    label: 'CPC',
                                    data: response.amzonCpcArray,
                                    borderColor: '#0c293efc',
                                    backgroundColor: '#0c293efc',
                                    tension: 0.4,
                                    yAxisID: 'y4',
                                    borderWidth: 3,
                                    pointRadius: 4,
                                    pointHoverRadius: 6,
                                    pointStyle: 'circle'
                                },
                                {
                                    label: 'CVR',
                                    data: response.amazonCvrArray,
                                    borderColor: '#f6da09ee',
                                    backgroundColor: '#f6da09ee',
                                    tension: 0.4,
                                    yAxisID: 'y5',
                                    borderWidth: 3,
                                    pointRadius: 4,
                                    pointHoverRadius: 6,
                                    pointStyle: 'circle'
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            interaction: { mode: 'index', intersect: false },
                            stacked: false,
                            plugins: {
                                legend: {
                                    display: false,
                                    position: 'top',
                                    labels: { usePointStyle: true, boxWidth: 14, padding: 50, font: { size: 13,
                                        weight: 'bold'
                                    }}
                                },
                                tooltip: {
                                    backgroundColor: 'rgba(30, 41, 59, 0.95)', 
                                    titleColor: '#facc15',                     
                                    bodyColor: '#f8fafc',                      
                                    borderColor: '#334155',                    
                                    borderWidth: 1,
                                    padding: 12,
                                    cornerRadius: 10,
                                    titleFont: {
                                        size: 14,
                                        weight: 'bold',
                                        family: 'Inter, sans-serif'
                                    },
                                    bodyFont: {
                                        size: 13,
                                        family: 'Inter, sans-serif'
                                    },
                                    boxPadding: 6,
                                    displayColors: true,                       
                                    usePointStyle: true,                       
                                    caretPadding: 8,                           
                                    caretSize: 6,
                                    titleAlign: 'left',
                                    bodyAlign: 'left',
                                    callbacks: {
                                        label: function(context) {
                                        
                                            let label = context.dataset.label?.replace(/\s*\(.*?\)\s*/g, '') || '';
                                            let value = context.formattedValue;
                                            return `${label}  :   ${value}`;
                                        }
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    type: 'linear',
                                    display: true,
                                    position: 'left',
                                    grid: { display: true, drawBorder: false, color: '#e5e7eb' },
                                    ticks: { color: '#6c2bd9', callback: function (value) {
                                        if (value >= 1000000) return (value / 1000000).toFixed(1) + "M";
                                        if (value >= 1000) return (value / 1000).toFixed(1) + "K";
                                        return value;
                                    }},
                                    text: 'Spend',                   
                                },
                                y1: {
                                    type: 'linear',
                                    display: true,
                                    position: 'left',
                                    grid: { display: false, drawBorder: false, color: '#e5e7eb' },
                                    ticks: { color: '#00b894', callback: function (value) {
                                        if (value >= 1000000) return (value / 1000000).toFixed(1) + "M";
                                        if (value >= 1000) return (value / 1000).toFixed(1) + "K";
                                        return value;
                                    }},
                                    text: 'Clicks',
                                    border: {
                                        display: false       
                                    },
                                },
                                y2: {
                                    type: 'linear',
                                    display: true,
                                    position: 'left',
                                    grid: { display: false, drawBorder: false, color: '#e5e7eb' },
                                    ticks: { color: '#ed0808fc', callback: function (value) {
                                        if (value >= 1000000) return (value / 1000000).toFixed(1) + "M";
                                        if (value >= 1000) return (value / 1000).toFixed(1) + "K";
                                        return value;
                                    }},
                                    text: 'Ad-Sales',
                                    border: {
                                        display: false
                                    },
                                },
                                y3: {
                                    type: 'linear',
                                    display: true,
                                    position: 'right',
                                    grid: { display: false, drawBorder: false, color: '#e5e7eb' },
                                    ticks: { color: '#0984e3', callback: function (value) {
                                        if (value >= 1000000) return (value / 1000000).toFixed(1) + "M";
                                        if (value >= 1000) return (value / 1000).toFixed(1) + "K";
                                        return value;
                                    }},
                                    text: 'Ad-Sold',
                                },
                                y4: {
                                    type: 'linear',
                                    display: true,
                                    position: 'right',
                                    grid: { display: false, drawBorder: false, color: '#e5e7eb' },
                                    ticks: { color: '#0c293efc' },
                                    text: 'CPC',
                                    border: {
                                        display: false       
                                    },
                                },
                                y5: {
                                    type: 'linear',
                                    display: true,
                                    position: 'right',
                                    grid: { display: false, drawBorder: false, color: '#e5e7eb' },
                                    ticks: { color: '#f6da09ee' },
                                    text: 'CVR',
                                    border: {
                                        display: false       
                                    },
                                },
                                x: {
                                    grid: { display: false },
                                    ticks: { color: '#6b7280' }
                                }
                            }
                        }
                    });
                }
            });
        }
    });
 
    /** End Amazon Chart Ajax **/

    /** Channel Chart Modal (EBAY 2, EBAY 3, WALMART, G SHOPPING) **/
    var channelChartCurrentChannel = null;
    var channelChartInstance = null;
    var chartOptionsCommon = {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        stacked: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: 'rgba(30, 41, 59, 0.95)',
                titleColor: '#facc15',
                bodyColor: '#f8fafc',
                borderColor: '#334155',
                borderWidth: 1,
                padding: 12,
                cornerRadius: 10,
                callbacks: { label: function(c) { return (c.dataset.label || '') + ' : ' + c.formattedValue; } }
            }
        },
        scales: {
            y: { type: 'linear', display: true, position: 'left', grid: { display: true, drawBorder: false, color: '#e5e7eb' }, ticks: { color: '#6c2bd9' } },
            y1: { type: 'linear', display: true, position: 'left', grid: { display: false }, ticks: { color: '#00b894' } },
            y2: { type: 'linear', display: true, position: 'left', grid: { display: false }, ticks: { color: '#ed0808fc' } },
            y3: { type: 'linear', display: true, position: 'right', grid: { display: false }, ticks: { color: '#0984e3' } },
            y4: { type: 'linear', display: true, position: 'right', grid: { display: false }, ticks: { color: '#f6da09ee' } },
            x: { grid: { display: false }, ticks: { color: '#6b7280' } }
        }
    };

    $('#channelChartModal').on('show.bs.modal', function(e) {
        var btn = e.relatedTarget ? $(e.relatedTarget) : $();
        channelChartCurrentChannel = btn.length ? (btn.data('channel') || null) : null;
        if (channelChartCurrentChannel) {
            $('#channelChartModalTitle').text(channelChartCurrentChannel + ' Graph');
            var ch = window.advMasterChannelWiseTotals && window.advMasterChannelWiseTotals[channelChartCurrentChannel];
            if (ch) {
                $('#channelChartSpend').text('$' + parseFloat(ch.spent || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
                $('#channelChartClicks').text(parseInt(ch.clicks || 0).toLocaleString('en-US'));
                $('#channelChartAdSales').text('$' + parseFloat(ch.ad_sales || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
                $('#channelChartAdSold').text(parseFloat(ch.ad_sold || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
                var cvr = parseFloat(ch.cvr || 0);
                $('#channelChartCvr').text(cvr.toFixed(2) + ' %');
            }
            var today = new Date();
            var d30 = new Date(today);
            d30.setDate(d30.getDate() - 30);
            $('#channelChartFromDate').val(d30.toISOString().slice(0, 10));
            $('#channelChartToDate').val(today.toISOString().slice(0, 10));
        }
    });

    $('#channelChartApplyBtn').on('click', function() {
        if (!channelChartCurrentChannel) return;
        var fromDate = $('#channelChartFromDate').val();
        var toDate = $('#channelChartToDate').val();
        if (!fromDate || !toDate) {
            alert('Please Select Dates !');
            return;
        }
        $.ajax({
            url: "{{ route('channel.adv.chart.data') }}",
            method: 'GET',
            data: { channel: channelChartCurrentChannel, fromDate: fromDate, toDate: toDate },
            success: function(response) {
                if (channelChartInstance) channelChartInstance.destroy();
                var ctx = document.getElementById('channelChartCanvas');
                if (!ctx) return;
                channelChartInstance = new Chart(ctx.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: response.dateArray || [],
                        datasets: [
                            { label: 'Spend', data: response.spentArray || [], borderColor: '#6c2bd9', backgroundColor: '#6c2bd9', tension: 0.4, yAxisID: 'y', borderWidth: 3, pointRadius: 4, pointHoverRadius: 6, pointStyle: 'circle' },
                            { label: 'Clicks', data: response.clicksArray || [], borderColor: '#00b894', backgroundColor: '#00b894', tension: 0.4, yAxisID: 'y1', borderWidth: 3, pointRadius: 4, pointHoverRadius: 6, pointStyle: 'circle' },
                            { label: 'Ad-Sales', data: response.adSalesArray || [], borderColor: '#ed0808fc', backgroundColor: '#ed0808fc', tension: 0.4, yAxisID: 'y2', borderWidth: 3, pointRadius: 4, pointHoverRadius: 6, pointStyle: 'circle' },
                            { label: 'Ad-Sold', data: response.adSoldArray || [], borderColor: '#0984e3', backgroundColor: '#0984e3', tension: 0.4, yAxisID: 'y3', borderWidth: 3, pointRadius: 4, pointHoverRadius: 6, pointStyle: 'circle' },
                            { label: 'CVR', data: response.cvrArray || [], borderColor: '#f6da09ee', backgroundColor: '#f6da09ee', tension: 0.4, yAxisID: 'y4', borderWidth: 3, pointRadius: 4, pointHoverRadius: 6, pointStyle: 'circle' }
                        ]
                    },
                    options: chartOptionsCommon
                });
            },
            error: function(xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'Failed to load chart data.';
                alert(msg);
            }
        });
    });

    /** End Channel Chart Modal **/

    /**
     * Shared Enhanced Chart Builder
     * - Dynamic Y-axis based on data min/max
     * - Right-side stats panel (Highest / Median / Lowest)
     * - Median horizontal dotted line
     * - Dot color: green if value < previous day, red if value > previous day, gray if equal or first
     * - Value label color: green if decreased vs 7 days prior, red if increased, gray otherwise
     */
    function buildEnhancedChart(canvasId, labels, dataArr, opts) {
        var ctx = document.getElementById(canvasId);
        if (!ctx) return null;
        var data = dataArr.map(function(v) { return parseFloat(v) || 0; });
        var len = data.length;

        // Compute stats
        var sorted = data.slice().sort(function(a, b) { return a - b; });
        var dataMin = sorted[0];
        var dataMax = sorted[sorted.length - 1];
        var median;
        if (len === 0) { median = 0; }
        else if (len % 2 === 0) { median = (sorted[len / 2 - 1] + sorted[len / 2]) / 2; }
        else { median = sorted[Math.floor(len / 2)]; }

        // Update stats panel
        var fmt = opts.formatValue || function(v) { return v.toLocaleString('en-US', {minimumFractionDigits: 0, maximumFractionDigits: 2}); };
        var suffix = opts.suffix || '';
        var prefix = opts.prefix || '';
        if (opts.statsIds) {
            $('#' + opts.statsIds.panel).show();
            $('#' + opts.statsIds.highest).text(prefix + fmt(dataMax) + suffix);
            $('#' + opts.statsIds.median).text(prefix + fmt(median) + suffix);
            $('#' + opts.statsIds.lowest).text(prefix + fmt(dataMin) + suffix);
        }

        // Dynamic Y-axis: pad 10% above max and below min
        var range = dataMax - dataMin;
        var yPad = range > 0 ? range * 0.12 : (dataMax > 0 ? dataMax * 0.12 : 1);
        var yMin = Math.max(0, dataMin - yPad);
        var yMax = dataMax + yPad;

        // Dot colors: green if value < previous day, red if value > previous day
        var pointColors = data.map(function(val, i) {
            if (i === 0) return '#9ca3af'; // first point: gray
            if (val < data[i - 1]) return '#16a34a'; // lower = green
            if (val > data[i - 1]) return '#dc2626'; // higher = red
            return '#9ca3af'; // equal = gray
        });

        // Value label colors: green if decreased vs 7 days prior, red if increased
        var labelColors = data.map(function(val, i) {
            if (i < 7) return '#6b7280'; // not enough history: gray
            if (val < data[i - 7]) return '#16a34a'; // decreased = green
            if (val > data[i - 7]) return '#dc2626'; // increased = red
            return '#6b7280'; // same = gray
        });

        // Custom plugin: draw median dotted line
        var medianLinePlugin = {
            id: 'medianLine_' + canvasId,
            afterDraw: function(chart) {
                var yScale = chart.scales.y;
                if (!yScale) return;
                var yPixel = yScale.getPixelForValue(median);
                var drawCtx = chart.ctx;
                drawCtx.save();
                drawCtx.setLineDash([6, 4]);
                drawCtx.strokeStyle = '#2563eb';
                drawCtx.lineWidth = 1.5;
                drawCtx.globalAlpha = 0.6;
                drawCtx.beginPath();
                drawCtx.moveTo(chart.chartArea.left, yPixel);
                drawCtx.lineTo(chart.chartArea.right, yPixel);
                drawCtx.stroke();
                // Label for median line
                drawCtx.setLineDash([]);
                drawCtx.globalAlpha = 0.8;
                drawCtx.font = '10px sans-serif';
                drawCtx.fillStyle = '#2563eb';
                drawCtx.textAlign = 'right';
                drawCtx.fillText('Median', chart.chartArea.right - 4, yPixel - 5);
                drawCtx.restore();
            }
        };

        // Custom plugin: draw value labels near dots
        var dataLabelPlugin = {
            id: 'dataLabels_' + canvasId,
            afterDatasetsDraw: function(chart) {
                var meta = chart.getDatasetMeta(0);
                if (!meta || !meta.data) return;
                var drawCtx = chart.ctx;
                drawCtx.save();
                drawCtx.font = 'bold 9px sans-serif';
                drawCtx.textAlign = 'center';
                meta.data.forEach(function(point, i) {
                    if (!point || point.skip) return;
                    var val = data[i];
                    var displayVal = prefix + Math.round(val).toLocaleString() + suffix;
                    drawCtx.fillStyle = labelColors[i];
                    // Position label above/below dot to avoid overlap
                    var yOffset = -10;
                    if (i > 0 && val > data[i - 1]) yOffset = 14; // if higher, show below
                    drawCtx.fillText(displayVal, point.x, point.y + yOffset);
                });
                drawCtx.restore();
            }
        };

        // Format labels to show only day + month (no year)
        var shortMonths = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        var displayLabels = labels.map(function(lbl) {
            var d = new Date(lbl);
            if (!isNaN(d.getTime())) {
                return d.getDate() + ' ' + shortMonths[d.getMonth()];
            }
            // Fallback: strip year from common formats like "2026-02-07" or "Feb 07, 2026"
            return String(lbl).replace(/\d{4}[-\/]?/g, '').replace(/,?\s*\d{4}/, '').trim();
        });

        var baseColor = opts.borderColor || '#00b894';
        var bgColor = opts.backgroundColor || 'rgba(0, 184, 148, 0.05)';

        var chartInstance = new Chart(ctx.getContext('2d'), {
            type: 'line',
            data: {
                labels: displayLabels,
                datasets: [{
                    label: opts.label || 'Value',
                    data: data,
                    borderColor: baseColor,
                    backgroundColor: bgColor,
                    tension: 0.3,
                    borderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    pointBackgroundColor: pointColors,
                    pointBorderColor: pointColors,
                    pointBorderWidth: 1.5,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                layout: { padding: { top: 20, bottom: 5 } },
                plugins: {
                    legend: { display: true, position: 'top' },
                    tooltip: {
                        callbacks: {
                            title: function(items) {
                                if (!items.length) return '';
                                var idx = items[0].dataIndex;
                                return labels[idx] || displayLabels[idx] || '';
                            },
                            label: opts.tooltipLabel || function(c) { return (opts.label || 'Value') + ': ' + c.formattedValue; }
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false }, ticks: { color: '#6b7280', maxRotation: 45 } },
                    y: {
                        min: yMin,
                        max: yMax,
                        grid: { color: '#e5e7eb' },
                        ticks: {
                            color: baseColor,
                            callback: opts.yTickCallback || function(v) { return v; }
                        }
                    }
                }
            },
            plugins: [medianLinePlugin, dataLabelPlugin]
        });

        return chartInstance;
    }

    /** Daily Chart Modals – unified config-driven handler **/
    var dailyChartState = { clicks: {}, spend: {}, adsales: {}, acos: {} };
    var dailyChartCfg = {
        clicks: {
            btnClass: 'clicks-chart-btn', modalId: 'dailyClicksModal', titleId: 'dailyClicksModalTitle',
            canvasId: 'dailyClicksCanvas', noDataId: 'dailyClicksNoData', titlePrefix: 'Daily Clicks',
            url: "{{ route('channel.adv.clicks.chart.data') }}",
            dataKey: 'clicksArray',
            chartOpts: {
                label: 'Clicks', borderColor: '#00b894', backgroundColor: 'rgba(0, 184, 148, 0.05)',
                tooltipLabel: function(c) { return 'Clicks: ' + c.formattedValue; },
                yTickCallback: function(v) { return v >= 1000 ? (v/1000) + 'K' : v; },
                formatValue: function(v) { return Math.round(v).toLocaleString(); },
                statsIds: { panel: 'dailyClicksStats', highest: 'dailyClicksHighest', median: 'dailyClicksMedian', lowest: 'dailyClicksLowest' }
            }
        },
        spend: {
            btnClass: 'spend-chart-btn', modalId: 'dailySpendModal', titleId: 'dailySpendModalTitle',
            canvasId: 'dailySpendCanvas', noDataId: 'dailySpendNoData', titlePrefix: 'Daily Spend',
            url: "{{ route('channel.adv.spend.chart.data') }}",
            dataKey: 'spentArray',
            chartOpts: {
                label: 'Spend', borderColor: '#6c2bd9', backgroundColor: 'rgba(108, 43, 217, 0.05)', prefix: '$',
                tooltipLabel: function(c) { return 'Spend: $' + parseFloat(c.raw).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}); },
                yTickCallback: function(v) { return '$' + (v >= 1000 ? (v/1000).toFixed(1) + 'K' : parseFloat(v).toFixed(0)); },
                formatValue: function(v) { return parseFloat(v).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}); },
                statsIds: { panel: 'dailySpendStats', highest: 'dailySpendHighest', median: 'dailySpendMedian', lowest: 'dailySpendLowest' }
            }
        },
        adsales: {
            btnClass: 'ad-sales-chart-btn', modalId: 'dailyAdSalesModal', titleId: 'dailyAdSalesModalTitle',
            canvasId: 'dailyAdSalesCanvas', noDataId: 'dailyAdSalesNoData', titlePrefix: 'Daily Ad Sales',
            url: "{{ route('channel.adv.adsales.chart.data') }}",
            dataKey: 'adSalesArray',
            chartOpts: {
                label: 'Ad Sales', borderColor: '#ed0808fc', backgroundColor: 'rgba(237, 8, 8, 0.05)', prefix: '$',
                tooltipLabel: function(c) { return 'Ad Sales: $' + parseFloat(c.raw).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}); },
                yTickCallback: function(v) { return '$' + (v >= 1000 ? (v/1000).toFixed(1) + 'K' : parseFloat(v).toFixed(0)); },
                formatValue: function(v) { return parseFloat(v).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}); },
                statsIds: { panel: 'dailyAdSalesStats', highest: 'dailyAdSalesHighest', median: 'dailyAdSalesMedian', lowest: 'dailyAdSalesLowest' }
            }
        },
        acos: {
            btnClass: 'acos-chart-btn', modalId: 'dailyAcosModal', titleId: 'dailyAcosModalTitle',
            canvasId: 'dailyAcosCanvas', noDataId: 'dailyAcosNoData', titlePrefix: 'Daily ACOS',
            url: "{{ route('channel.adv.acos.chart.data') }}",
            dataKey: 'acosArray',
            chartOpts: {
                label: 'ACOS %', borderColor: '#0984e3', backgroundColor: 'rgba(9, 132, 227, 0.05)', suffix: '%',
                tooltipLabel: function(c) { return 'ACOS: ' + parseFloat(c.raw).toFixed(2) + ' %'; },
                yTickCallback: function(v) { return parseFloat(v).toFixed(1) + ' %'; },
                formatValue: function(v) { return parseFloat(v).toFixed(2); },
                statsIds: { panel: 'dailyAcosStats', highest: 'dailyAcosHighest', median: 'dailyAcosMedian', lowest: 'dailyAcosLowest' }
            }
        }
    };

    // Compute from/to dates from a range value
    function getDateRange(rangeVal) {
        var today = new Date();
        var toDate = today.toISOString().slice(0, 10);
        if (rangeVal === 'lifetime') return { fromDate: '2020-01-01', toDate: toDate };
        var days = parseInt(rangeVal) || 30;
        var from = new Date(today);
        from.setDate(from.getDate() - days);
        return { fromDate: from.toISOString().slice(0, 10), toDate: toDate };
    }

    // Load chart data for a given chart type
    function loadDailyChart(type) {
        var cfg = dailyChartCfg[type];
        var state = dailyChartState[type];
        if (!cfg || !state.channel) return;
        var rangeVal = $('.daily-chart-range[data-chart="' + type + '"]').val() || '30';
        var range = getDateRange(rangeVal);
        if (state.chart) { state.chart.destroy(); state.chart = null; }
        $.ajax({
            url: cfg.url,
            method: 'GET',
            data: { channel: state.channel, fromDate: range.fromDate, toDate: range.toDate },
            success: function(response) {
                if (state.chart) { state.chart.destroy(); state.chart = null; }
                var dateArray = response.dateArray || [];
                var valArray = response[cfg.dataKey] || [];
                $('#' + cfg.noDataId).toggle(!dateArray.length);
                $('#' + cfg.canvasId).closest('.chart-enhanced-wrapper').toggle(!!dateArray.length);
                if (!dateArray.length) { $('#' + cfg.chartOpts.statsIds.panel).hide(); return; }
                state.chart = buildEnhancedChart(cfg.canvasId, dateArray, valArray, cfg.chartOpts);
            },
            error: function(xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'Failed to load data.';
                alert(msg);
            }
        });
    }

    // Bind eye-icon click to capture channel, modal shown to auto-load, dropdown change to reload
    $.each(dailyChartCfg, function(type, cfg) {
        $(document).on('click', '.' + cfg.btnClass, function() {
            dailyChartState[type].channel = $(this).data('channel') || null;
        });
        $('#' + cfg.modalId).on('shown.bs.modal', function(e) {
            var btn = e.relatedTarget ? $(e.relatedTarget) : $();
            if (btn.length && btn.hasClass(cfg.btnClass)) {
                dailyChartState[type].channel = btn.data('channel') || dailyChartState[type].channel;
            }
            if (dailyChartState[type].channel) {
                $('#' + cfg.titleId).text(cfg.titlePrefix + ' - ' + dailyChartState[type].channel);
                loadDailyChart(type);
            }
        });
    });
    $(document).on('change', '.daily-chart-range', function() {
        var type = $(this).data('chart');
        if (type && dailyChartState[type] && dailyChartState[type].channel) loadDailyChart(type);
    });
    /** End Daily Chart Modals **/

    /** Start Ebay Graph Date **/
    const ctxEbay = document.getElementById('advEbayChart').getContext('2d');
    performanceChartEbay = new Chart(ctxEbay, {
        type: 'line',
        data: {
            labels: @json($ebayDateArray),
            datasets: [
                {
                    label: 'Spend',
                    data: @json($ebaySpentArray),
                    borderColor: '#6c2bd9',
                    backgroundColor: '#6c2bd9',
                    tension: 0.4,
                    yAxisID: 'y',
                    borderWidth: 3,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointStyle: 'circle'
                },
                {
                    label: 'Clicks',
                    data: @json($ebayclicksArray),
                    borderColor: '#00b894',
                    backgroundColor: '#00b894',
                    tension: 0.4,
                    yAxisID: 'y1',
                    borderWidth: 3,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointStyle: 'circle'
                },
                {
                    label: 'AD-Sales',
                    data: @json($ebayadSalesArray),
                    borderColor: '#ed0808fc',
                    backgroundColor: '#ed0808fc',
                    tension: 0.4,
                    yAxisID: 'y2',
                    borderWidth: 3,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointStyle: 'circle'
                },
                {
                    label: 'AD-Sold',
                    data: @json($ebayadSoldArray),
                    borderColor: '#0984e3',
                    backgroundColor: '#0984e3',
                    tension: 0.4,
                    yAxisID: 'y3',
                    borderWidth: 3,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointStyle: 'circle'
                },
                {
                    label: 'CVR',
                    data: @json($ebayCvrArray),
                    borderColor: '#f6da09ee',
                    backgroundColor: '#f6da09ee',
                    tension: 0.4,
                    yAxisID: 'y4',
                    borderWidth: 3,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointStyle: 'circle'
                }
            ]
        },
        options: {
            responsive: true,
            interaction: { mode: 'index', intersect: false },
            stacked: false,
            plugins: {
                legend: {
                    display: false,
                    position: 'top',
                    labels: { usePointStyle: true, boxWidth: 14, padding: 50, font: { size: 13,
                    weight: 'bold'
                    }}
                },
                tooltip: {
                   backgroundColor: 'rgba(30, 41, 59, 0.95)', 
                    titleColor: '#facc15',                     
                    bodyColor: '#f8fafc',                      
                    borderColor: '#334155',                    
                    borderWidth: 1,
                    padding: 12,
                    cornerRadius: 10,
                    titleFont: {
                        size: 14,
                        weight: 'bold',
                        family: 'Inter, sans-serif'
                    },
                    bodyFont: {
                        size: 13,
                        family: 'Inter, sans-serif'
                    },
                    boxPadding: 6,
                    displayColors: true,                       
                    usePointStyle: true,                       
                    caretPadding: 8,                           
                    caretSize: 6,
                    titleAlign: 'left',
                    bodyAlign: 'left',
                    callbacks: {
                        label: function(context) {
                        
                            let label = context.dataset.label?.replace(/\s*\(.*?\)\s*/g, '') || '';
                            let value = context.formattedValue;
                            return `${label}  :   ${value}`;
                        }
                    }
                }
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    grid: { display: true, drawBorder: false, color: '#e5e7eb' },
                    ticks: { color: '#6c2bd9', callback: function (value) {
                        if (value >= 1000000) return (value / 1000000).toFixed(1) + "M";
                        if (value >= 1000) return (value / 1000).toFixed(1) + "K";
                        return value;
                    }},
                    text: 'Spend',
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    grid: { display: false, drawBorder: false, color: '#e5e7eb' },
                    ticks: { color: '#00b894', callback: function (value) {
                        if (value >= 1000000) return (value / 1000000).toFixed(1) + "M";
                        if (value >= 1000) return (value / 1000).toFixed(1) + "K";
                        return value;
                    }},
                    text: 'Clicks',
                    border: {
                        display: false       
                    },
                },
                y2: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    grid: { display: false, drawBorder: false, color: '#e5e7eb' },
                    ticks: { color: '#ed0808fc', callback: function (value) {
                        if (value >= 1000000) return (value / 1000000).toFixed(1) + "M";
                        if (value >= 1000) return (value / 1000).toFixed(1) + "K";
                        return value;
                    }},
                    text: 'Ad-Sales',
                    border: {
                        display: false
                    },
                },
                y3: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    grid: { display: false, drawBorder: false, color: '#e5e7eb' },
                    ticks: { color: '#0984e3', callback: function (value) {
                        if (value >= 1000000) return (value / 1000000).toFixed(1) + "M";
                        if (value >= 1000) return (value / 1000).toFixed(1) + "K";
                        return value;
                    }},
                    text: 'Ad-Sold',
                },
                y4: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    grid: { display: false, drawBorder: false, color: '#e5e7eb' },
                    ticks: { color: '#f6da09ee' },
                    text: 'CVR',
                    border: {
                        display: false       
                    },
                },
                x: {
                    grid: { display: false },
                    ticks: { color: '#6b7280' }
                }
            }
        }
    });
    /** End Ebay Graph Data **/

    /** Start Amazon Graph Code */

    const ctx = document.getElementById('advMultiLineChart').getContext('2d');
    performanceChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: @json($amazonDateArray),
            datasets: [
                {
                    label: 'Spend',
                    data: @json($amazonSpentArray),
                    borderColor: '#6c2bd9',
                    backgroundColor: '#6c2bd9',
                    tension: 0.4,
                    yAxisID: 'y',
                    borderWidth: 3,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointStyle: 'circle'
                },
                {
                    label: 'Clicks',
                    data: @json($amazonclicksArray),
                    borderColor: '#00b894',
                    backgroundColor: '#00b894',
                    tension: 0.4,
                    yAxisID: 'y1',
                    borderWidth: 3,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointStyle: 'circle'
                },
                {
                    label: 'Ad-Sales',
                    data: @json($amazonadSalesArray),
                    borderColor: '#ed0808fc',
                    backgroundColor: '#ed0808fc',
                    tension: 0.4,
                    yAxisID: 'y2',
                    borderWidth: 3,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointStyle: 'circle'
                },
                {
                    label: 'Ad-Sold',
                    data: @json($amzonadSoldArray),
                    borderColor: '#0984e3',
                    backgroundColor: '#0984e3',
                    tension: 0.4,
                    yAxisID: 'y3',
                    borderWidth: 3,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointStyle: 'circle'
                },
                {
                    label: 'CPC',
                    data: @json($amzonCpcArray),
                    borderColor: '#0c293efc',
                    backgroundColor: '#0c293efc',
                    tension: 0.4,
                    yAxisID: 'y4',
                    borderWidth: 3,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointStyle: 'circle'
                },
                {
                    label: 'CVR',
                    data: @json($amazonCvrArray),
                    borderColor: '#f6da09ee',
                    backgroundColor: '#f6da09ee',
                    tension: 0.4,
                    yAxisID: 'y5',
                    borderWidth: 3,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointStyle: 'circle'
                }
            ]
        },
        options: {
            responsive: true,
            interaction: { mode: 'index', intersect: false },
            stacked: false,
            plugins: {
                legend: {
                    display: false,
                    position: 'top',
                    labels: { usePointStyle: true, boxWidth: 14, padding: 50, font: { size: 13,
                        weight: 'bold'
                    }}
                },
                tooltip: {
                    backgroundColor: 'rgba(30, 41, 59, 0.95)', 
                    titleColor: '#facc15',                     
                    bodyColor: '#f8fafc',                      
                    borderColor: '#334155',                    
                    borderWidth: 1,
                    padding: 12,
                    cornerRadius: 10,
                    titleFont: {
                        size: 14,
                        weight: 'bold',
                        family: 'Inter, sans-serif'
                    },
                    bodyFont: {
                        size: 13,
                        family: 'Inter, sans-serif'
                    },
                    boxPadding: 6,
                    displayColors: true,                       
                    usePointStyle: true,                       
                    caretPadding: 8,                           
                    caretSize: 6,
                    titleAlign: 'left',
                    bodyAlign: 'left',
                    callbacks: {
                        label: function(context) {
                        
                            let label = context.dataset.label?.replace(/\s*\(.*?\)\s*/g, '') || '';
                            let value = context.formattedValue;
                            return `${label}  :   ${value}`;
                        }
                    }
                }
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    grid: { display: true, drawBorder: false, color: '#e5e7eb' },
                    ticks: { color: '#6c2bd9', callback: function (value) {
                        if (value >= 1000000) return (value / 1000000).toFixed(1) + "M";
                        if (value >= 1000) return (value / 1000).toFixed(1) + "K";
                        return value;
                    }},
                    text: 'Spend',                   
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    grid: { display: false, drawBorder: false, color: '#e5e7eb' },
                    ticks: { color: '#00b894', callback: function (value) {
                        if (value >= 1000000) return (value / 1000000).toFixed(1) + "M";
                        if (value >= 1000) return (value / 1000).toFixed(1) + "K";
                        return value;
                    }},
                    text: 'Clicks',
                    border: {
                        display: false       
                    },
                },
                y2: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    grid: { display: false, drawBorder: false, color: '#e5e7eb' },
                    ticks: { color: '#ed0808fc', callback: function (value) {
                        if (value >= 1000000) return (value / 1000000).toFixed(1) + "M";
                        if (value >= 1000) return (value / 1000).toFixed(1) + "K";
                        return value;
                    }},
                    text: 'Ad-Sales',
                    border: {
                        display: false
                    },
                },
                y3: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    grid: { display: false, drawBorder: false, color: '#e5e7eb' },
                    ticks: { color: '#0984e3', callback: function (value) {
                        if (value >= 1000000) return (value / 1000000).toFixed(1) + "M";
                        if (value >= 1000) return (value / 1000).toFixed(1) + "K";
                        return value;
                    }},
                    text: 'Ad-Sold',
                },
                y4: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    grid: { display: false, drawBorder: false, color: '#e5e7eb' },
                    ticks: { color: '#0c293efc' },
                    text: 'CPC',
                    border: {
                        display: false       
                    },
                },
                y5: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    grid: { display: false, drawBorder: false, color: '#e5e7eb' },
                    ticks: { color: '#f6da09ee' },
                    text: 'CVR',
                    border: {
                        display: false       
                    },
                },
                x: {
                    grid: { display: false },
                    ticks: { color: '#6b7280' }
                }
            }
        }
    });

    /** End Amazon Graph Code **/

    // Format numbers with commas for better readability
    function formatNumber(num) {
        if (num === null || num === undefined || num === '') return '0';
        return parseFloat(num).toLocaleString('en-US');
    }

    // Apply number formatting to all numeric cells
    $('#adv-master-table tbody td').each(function() {
        let text = $(this).text().trim();
        // Check if it's a number (not empty, not %, not a link)
        if (text && !isNaN(text) && text !== '' && !text.includes('%') && !$(this).find('a').length) {
            let num = parseFloat(text);
            if (!isNaN(num) && num !== 0) {
                $(this).text(formatNumber(num));
            }
        }
    });

    window.advEditRowRef = null;

    // Edit channel: open modal with Channel Name + Ad Type (registered early, uses window.advMasterTable when set)
    $(document).on('click', '.edit-channel-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var $btn = $(this);
        var channelName = $btn.data('channel') || '';
        var adType = $btn.data('type') || '';
        var table = window.advMasterTable;
        var rowComponent = null;
        var $row = $btn.closest('.tabulator-row');
        if ($row.length && table) {
            var rows = table.getRows();
            for (var i = 0; i < rows.length; i++) {
                if (rows[i].getElement() === $row[0]) {
                    rowComponent = rows[i];
                    break;
                }
            }
        }
        window.advEditRowRef = rowComponent;
        $('#editOriginalChannel').val(channelName);
        $('#editChannelName').val(channelName);
        $('#editChannelType').val(adType);
        var modalEl = document.getElementById('editChannelModal');
        if (modalEl && typeof bootstrap !== 'undefined') {
            var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
        }
    });

    // Save Edit Channel: POST update-name-type, then update row and close modal
    $(document).on('click', '#saveEditChannelBtn', function() {
        var btn = $(this);
        var original = $('#editOriginalChannel').val().trim();
        var channelName = $('#editChannelName').val().trim();
        var adType = $('#editChannelType').val() || '';
        if (!channelName) {
            alert('Channel name is required.');
            $('#editChannelName').focus();
            return;
        }
        btn.prop('disabled', true);
        btn.find('.btn-text').text('Saving…');
        $.ajax({
            url: '{{ route("channel_master.update_name_type") }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                original_channel: original,
                channel: channelName,
                type: adType
            },
            success: function(res) {
                var modalEl = document.getElementById('editChannelModal');
                if (modalEl && typeof bootstrap !== 'undefined') {
                    var modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) modal.hide();
                }
                var editRowRef = window.advEditRowRef;
                if (editRowRef) {
                    var typeDisplay = adType ? adType : '';
                    var nameHtml = '<b>' + channelName.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;') + '</b>';
                    editRowRef.update({ col0: nameHtml, col1: typeDisplay });
                    try {
                        var cell = editRowRef.getCell('col23');
                        if (cell) {
                            var editBtn = cell.getElement().querySelector('.edit-channel-btn');
                            if (editBtn) {
                                editBtn.setAttribute('data-channel', channelName);
                                editBtn.setAttribute('data-type', adType);
                            }
                        }
                    } catch (err) { /* ignore */ }
                }
                window.advEditRowRef = null;
                if (typeof window.showToast === 'function') {
                    window.showToast('success', res.message || 'Channel updated successfully.');
                } else {
                    alert(res.message || 'Channel updated successfully.');
                }
            },
            error: function(xhr) {
                var msg = 'Failed to update channel. Please try again.';
                if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                else if (xhr.status === 404) msg = 'Channel not found. It may only exist in this table.';
                else if (xhr.status === 419) msg = 'Session expired. Please refresh and try again.';
                else if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                    var errs = xhr.responseJSON.errors;
                    msg = (errs.channel && errs.channel[0]) || msg;
                }
                alert(msg);
            },
            complete: function() {
                btn.prop('disabled', false);
                btn.find('.btn-text').text('Save');
            }
        });
    });

    /* Accordion handled by Tabulator rowClick; table is replaced by Tabulator */
    setTimeout(function() {
        var tabulatorScript = document.createElement('script');
        tabulatorScript.src = "https://unpkg.com/tabulator-tables@5.5.2/dist/js/tabulator.min.js";
        tabulatorScript.onload = function() {
            try {
                // Get the table element
                var tableElement = document.getElementById('adv-master-table');
                if (!tableElement) {
                    console.error('Table element not found');
                    return;
                }

                // Extract table data from existing HTML table
                var tableData = [];
                var rows = tableElement.querySelectorAll('tbody tr');
                
                rows.forEach(function(row) {
                    var cells = row.querySelectorAll('td');
                    var rowData = {};
                    var rowClasses = row.className;
                    var rowStyle = row.getAttribute('style') || '';
                    
                    cells.forEach(function(cell, index) {
                        rowData['col' + index] = cell.innerHTML.trim();
                    });
                    
                    // Store metadata for row formatting
                    rowData._isAccordionHeader = rowClasses.includes('accordion-header');
                    rowData._isAccordionBody = rowClasses.includes('accordion-body');
                    rowData._rowStyle = rowStyle;
                    rowData._originalRow = row;
                    
                    tableData.push(rowData);
                });

                // Helpers for sort/filter (strip HTML, parse numbers)
                function stripHtml(html) {
                    if (html == null || html === undefined) return '';
                    var div = document.createElement('div');
                    div.innerHTML = String(html);
                    return (div.textContent || div.innerText || '').trim();
                }
                function parseNum(val) {
                    var s = stripHtml(val).replace(/[,\s%$]/g, '');
                    var n = parseFloat(s);
                    return isNaN(n) ? null : n;
                }
                // Numeric columns: L30 SALES(4) through MISSING ADS(22). 0-3: CHANNELS, Ad Type, Tab, Graph. 23: ACTIONS.
                var numericColIndices = {};
                for (var i = 4; i <= 22; i++) numericColIndices[i] = true;

                // Extract column definitions from header
                var columns = [];
                var headerRow = tableElement.querySelector('thead tr');
                var headerCells = headerRow.querySelectorAll('th');
                
                // L30 SPENT=7, L30 CLKS=10, AD SALES=16, L30 ACOS%=17
                var l30SpentColIndex = 7;
                var l30ClksColIndex = 10;
                var l30AdSalesColIndex = 16;
                var l30AcosColIndex = 17;
                var eyeIconSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="#00b894" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/><path d="M8 3.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg>';
                function buildL30SpentCellHtml(val, row) {
                    var num = (val != null && val !== '') ? stripHtml(String(val)).trim() : '';
                    var channel = '';
                    var valStr = String(val || '');
                    var m = valStr.match(/data-channel=["']([^"']+)["']/);
                    if (m) channel = m[1];
                    if (!channel && row && row.getData) channel = stripHtml((row.getData().col0 || '')).trim();
                    if (!channel) channel = 'AMAZON';
                    var btn = '<button type="button" class="btn btn-link btn-sm p-0 align-baseline spend-chart-btn" data-channel="' + (channel.replace(/"/g, '&quot;')) + '" data-bs-toggle="modal" data-bs-target="#dailySpendModal" title="View daily spend">' + eyeIconSvg + '</button>';
                    return '<div class="spend-cell-inner"><span>' + (num || '0') + '</span>' + btn + '</div>';
                }
                function buildL30ClksCellHtml(val, row) {
                    var num = (val != null && val !== '') ? stripHtml(String(val)).trim() : '';
                    var channel = '';
                    var valStr = String(val || '');
                    var m = valStr.match(/data-channel=["']([^"']+)["']/);
                    if (m) channel = m[1];
                    if (!channel && row && row.getData) channel = stripHtml((row.getData().col0 || '')).trim();
                    if (!channel) channel = 'AMAZON';
                    var btn = '<button type="button" class="btn btn-link btn-sm p-0 align-baseline clicks-chart-btn" data-channel="' + (channel.replace(/"/g, '&quot;')) + '" data-bs-toggle="modal" data-bs-target="#dailyClicksModal" title="View daily clicks">' + eyeIconSvg + '</button>';
                    return '<div class="clicks-cell-inner"><span>' + (num || '0') + '</span>' + btn + '</div>';
                }
                function buildL30AdSalesCellHtml(val, row) {
                    var num = (val != null && val !== '') ? stripHtml(String(val)).trim() : '';
                    var channel = '';
                    var valStr = String(val || '');
                    var m = valStr.match(/data-channel=["']([^"']+)["']/);
                    if (m) channel = m[1];
                    if (!channel && row && row.getData) channel = stripHtml((row.getData().col0 || '')).trim();
                    if (!channel) channel = 'AMAZON';
                    var btn = '<button type="button" class="btn btn-link btn-sm p-0 align-baseline ad-sales-chart-btn" data-channel="' + (channel.replace(/"/g, '&quot;')) + '" data-bs-toggle="modal" data-bs-target="#dailyAdSalesModal" title="View daily ad sales">' + eyeIconSvg + '</button>';
                    return '<div class="adsales-cell-inner"><span>' + (num || '0') + '</span>' + btn + '</div>';
                }
                function buildL30AcosCellHtml(val, row) {
                    var num = (val != null && val !== '') ? stripHtml(String(val)).trim() : '';
                    var channel = '';
                    var valStr = String(val || '');
                    var m = valStr.match(/data-channel=["']([^"']+)["']/);
                    if (m) channel = m[1];
                    if (!channel && row && row.getData) channel = stripHtml((row.getData().col0 || '')).trim();
                    if (!channel) channel = 'AMAZON';
                    var btn = '<button type="button" class="btn btn-link btn-sm p-0 align-baseline acos-chart-btn" data-channel="' + (channel.replace(/"/g, '&quot;')) + '" data-bs-toggle="modal" data-bs-target="#dailyAcosModal" title="View daily ACOS">' + eyeIconSvg + '</button>';
                    return '<div class="acos-cell-inner"><span>' + (num || '0') + '</span>' + btn + '</div>';
                }
                headerCells.forEach(function(header, index) {
                    var headerHtml = header.innerHTML;
                    var formatterFn = 'html';
                    if (index === l30ClksColIndex) formatterFn = function(cell) { return buildL30ClksCellHtml(cell.getValue(), cell.getRow()); };
                    else if (index === l30SpentColIndex) formatterFn = function(cell) { return buildL30SpentCellHtml(cell.getValue(), cell.getRow()); };
                    else if (index === l30AdSalesColIndex) formatterFn = function(cell) { return buildL30AdSalesCellHtml(cell.getValue(), cell.getRow()); };
                    else if (index === l30AcosColIndex) formatterFn = function(cell) { return buildL30AcosCellHtml(cell.getValue(), cell.getRow()); };
                    var colDef = {
                        title: headerHtml,
                        field: 'col' + index,
                        formatter: formatterFn,
                        headerSort: true,
                        resizable: true,
                        width: index === 0 ? 110 : undefined,
                        minWidth: 80,
                        headerTooltip: true,
                        headerFilter: 'input',
                        headerFilterPlaceholder: 'Filter..',
                        headerFilterFunc: function(filterInputVal, cellValue) {
                            if (!filterInputVal) return true;
                            var text = stripHtml(cellValue).toLowerCase();
                            return text.indexOf(String(filterInputVal).toLowerCase()) !== -1;
                        }
                    };
                    if (numericColIndices[index]) {
                        colDef.sorter = function(a, b) {
                            var na = parseNum(a), nb = parseNum(b);
                            if (na == null && nb == null) return 0;
                            if (na == null) return 1;
                            if (nb == null) return -1;
                            return na - nb;
                        };
                    } else {
                        colDef.sorter = function(a, b) {
                            var sa = stripHtml(a).toLowerCase(), sb = stripHtml(b).toLowerCase();
                            return sa.localeCompare(sb);
                        };
                    }
                    columns.push(colDef);
                });

                // Create a container div for Tabulator
                var container = tableElement.parentElement;
                var tabulatorDiv = document.createElement('div');
                tabulatorDiv.id = 'tabulator-table';
                container.insertBefore(tabulatorDiv, tableElement);
                tableElement.style.display = 'none';

                // Create Tabulator instance
                var table = new Tabulator("#tabulator-table", {
                    data: tableData,
                    columns: columns,
                    layout: "fitColumns",
                    pagination: false,
                    movableColumns: false,
                    resizableColumns: true,
                    headerVisible: true,
                    height: "auto",
                    placeholder: "No Data Available",
                    headerFilterLiveFilter: true,
                    rowFormatter: function(row) {
                        var rowData = row.getData();
                        var rowElement = row.getElement();
                        // Apply original row style only for non–group-header rows (no accordion styling)
                        if (rowData._rowStyle && !rowData._isAccordionHeader) {
                            rowElement.setAttribute('style', rowData._rowStyle + '; ' + (rowElement.getAttribute('style') || ''));
                        }
                        // Channel hierarchy styling (main = darker blue, child = lighter blue)
                        if (rowData._isAccordionHeader) rowElement.classList.add('channel-main');
                        if (rowData._isAccordionBody) rowElement.classList.add('channel-child');
                    }
                });
                window.advMasterTable = table;

                // Search functionality - search across all columns (works alongside header filters)
                var globalSearchFilter = null;
                var searchTimeout;
                $('#search-input').on('keyup', function() {
                    var searchInput = this;
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(function() {
                        var searchValue = searchInput.value.toLowerCase().trim();
                        // Remove previous global search filter
                        if (globalSearchFilter) {
                            table.removeFilter(globalSearchFilter);
                            globalSearchFilter = null;
                        }
                        if (searchValue !== '') {
                            // Add global search filter (header filters remain active)
                            globalSearchFilter = function(data) {
                                var searchText = searchValue;
                                for (var key in data) {
                                    if (key.startsWith('col') && typeof data[key] === 'string') {
                                        var cellText = stripHtml(data[key]).toLowerCase();
                                        if (cellText.indexOf(searchText) !== -1) {
                                            return true;
                                        }
                                    }
                                }
                                return false;
                            };
                            table.addFilter(globalSearchFilter);
                        }
                    }, 300);
                });

                // Add Channel: opens modal via data-bs-toggle/data-bs-target

                // Save Channel (Add Channel modal form submit)
                $(document).on('click', '#saveChannelBtn', function() {
                    var btn = $('#saveChannelBtn');
                    var name = $('#addChannelName').val().trim();
                    var sheetLink = $('#addChannelSheetLink').val().trim();
                    var adType = $('#addChannelAdType').val().trim() || '';

                    if (!name) {
                        alert('Channel name is required.');
                        $('#addChannelName').focus();
                        return;
                    }
                    var token = $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val();
                    if (!token) {
                        alert('CSRF token missing. Please refresh the page.');
                        return;
                    }
                    btn.prop('disabled', true);
                    btn.find('.btn-text').text('Saving...');
                    $.ajax({
                        url: '{{ route("channel_master.store") }}',
                        method: 'POST',
                        data: {
                            channel: name,
                            sheet_link: sheetLink || '',
                            type: adType,
                            _token: token
                        },
                        success: function(res) {
                            var modalEl = document.getElementById('addChannelModal');
                            if (modalEl && typeof bootstrap !== 'undefined') {
                                var modal = bootstrap.Modal.getInstance(modalEl);
                                if (modal) modal.hide();
                            }
                            $('#addChannelForm')[0].reset();
                            var channelName = name;
                            try {
                                var editBtnHtml = '<button type="button" class="btn btn-sm btn-warning edit-channel-btn" data-channel="' + channelName.replace(/"/g, '&quot;') + '" data-type="' + adType.replace(/"/g, '&quot;') + '" title="Edit Channel">' +
                                    '<svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">' +
                                    '<path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5L13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.207l6.5-6.5zm-9.761 5.175l-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>' +
                                    '</svg></button>';
                                var tabLink = '<a href="#" class="adv-l60-tab-link text-primary" title="View L60 Data">L60</a>';
                                var graphLink = '<a href="#" class="adv-l60-graph-link text-success" title="View L60 Graph">📈</a>';
                                var typeDisplay = adType ? adType : '';
                                var newRow = {
                                    col0: '<b>' + channelName.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;') + '</b>',
                                    col1: typeDisplay,
                                    col2: tabLink,
                                    col3: graphLink,
                                    col4: '', col5: '', col6: '', col7: '', col8: '', col9: '', col10: '', col11: '', col12: '',
                                    col13: '', col14: '', col15: '', col16: '', col17: '', col18: '', col19: '', col20: '', col21: '', col22: '',
                                    col23: editBtnHtml,
                                    _isAccordionHeader: false,
                                    _isAccordionBody: false
                                };
                                table.addRow(newRow, false);
                                var rows = table.getRows();
                                if (rows.length) rows[rows.length - 1].getElement().scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                            } catch (e) {
                                console.warn('Could not add row to table:', e);
                            }
                            if (typeof window.showToast === 'function') {
                                window.showToast('success', res.message || 'Channel added successfully. Please refresh the page to see it in the table.');
                            } else {
                                alert(res.message || 'Channel added successfully. Please refresh the page to see it in the table.');
                            }
                        },
                        error: function(xhr) {
                            var msg = 'Failed to add channel. Please try again.';
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                msg = xhr.responseJSON.message;
                            } else if (xhr.status === 419) {
                                msg = 'Session expired. Please refresh the page and try again.';
                            } else if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                                var errs = xhr.responseJSON.errors;
                                msg = (errs.channel && errs.channel[0]) || (errs.sheet_link && errs.sheet_link[0]) || msg;
                            }
                            alert(msg);
                        },
                        complete: function() {
                            btn.prop('disabled', false);
                            btn.find('.btn-text').text('Save Channel');
                        }
                    });
                });

                // View mode & Channel-wise (badges removed)
                $('#adv-view-mode').on('change', function() {
                    var isChannelWise = $(this).val() === 'channel-wise';
                    $('#adv-channel-select').toggle(isChannelWise);
                    if (!isChannelWise) {
                        $('#adv-channel-select').val('');
                    }
                });

                // L60 Tab: table handler
                function escapeHtml(str) {
                    if (str == null || str === undefined) return '';
                    var div = document.createElement('div');
                    div.textContent = str;
                    return div.innerHTML;
                }
                function fmtNumCell(v) {
                    if (v == null || v === undefined || v === '') return '—';
                    var n = parseFloat(v);
                    return isNaN(n) ? '—' : n.toFixed(2);
                }
                $(document).on('click', '.adv-l60-tab-link', function(e) {
                    e.preventDefault();
                    var channelWise = window.advMasterChannelWiseTotals || {};
                    var channels = ['AMAZON', 'EBAY', 'EBAY 2', 'EBAY 3', 'WALMART', 'G SHOPPING'];
                    var tbody = document.getElementById('adv-l60-tab-tbody');
                    if (!tbody) return;
                    tbody.innerHTML = '';
                    channels.forEach(function(ch, i) {
                        var chData = channelWise[ch];
                        if (chData) {
                            var tr = document.createElement('tr');
                            var cvr60 = chData.cvr_60 != null && chData.cvr_60 !== '' ? parseFloat(chData.cvr_60).toFixed(2) + '%' : '—';
                            var grwCvr = chData.grw_cvr != null && chData.grw_cvr !== '' ? parseFloat(chData.grw_cvr).toFixed(2) + '%' : '—';
                            tr.innerHTML = 
                                '<td>' + (i + 1) + '</td>' +
                                '<td>' + escapeHtml(ch) + '</td>' +
                                '<td class="text-end">' + fmtNumCell(chData.l60_spent) + '</td>' +
                                '<td class="text-end">' + fmtNumCell(chData.l60_clicks) + '</td>' +
                                '<td class="text-end">' + cvr60 + '</td>' +
                                '<td class="text-end">' + grwCvr + '</td>';
                            tbody.appendChild(tr);
                        }
                    });
                    var modalEl = document.getElementById('adv-l60-tab-modal');
                    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                        var modal = new bootstrap.Modal(modalEl);
                        modal.show();
                    } else {
                        modalEl.classList.add('show');
                        modalEl.style.display = 'block';
                        modalEl.setAttribute('aria-hidden', 'false');
                    }
                });

                // L60 Graph: line graph handler
                var l60GraphChart = null;
                function renderL60Graph(metric) {
                    var channelWise = window.advMasterChannelWiseTotals || {};
                    var channels = ['AMAZON', 'EBAY', 'EBAY 2', 'EBAY 3', 'WALMART', 'G SHOPPING'];
                    var labels = [];
                    var data = [];
                    var colors = ['#4361ee', '#3f37c9', '#7209b7', '#560bad', '#480ca8', '#3a0ca3'];
                    channels.forEach(function(ch, i) {
                        var chData = channelWise[ch];
                        if (chData) {
                            labels.push(ch);
                            var val = chData[metric] || 0;
                            data.push(parseFloat(val) || 0);
                        }
                    });
                    var ctx = document.getElementById('adv-l60-graph-canvas');
                    if (!ctx) return;
                    if (l60GraphChart) l60GraphChart.destroy();
                    var metricLabels = {
                        'l60_spent': 'L60 SPENT',
                        'l60_clicks': 'L60 CLICKS',
                        'cvr_60': 'CVR 60',
                        'grw_cvr': 'Grw CVR'
                    };
                    l60GraphChart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: metricLabels[metric] || metric,
                                data: data,
                                borderColor: '#4361ee',
                                backgroundColor: 'rgba(67, 97, 238, 0.1)',
                                borderWidth: 3,
                                fill: true,
                                tension: 0.4,
                                pointRadius: 6,
                                pointHoverRadius: 8,
                                pointBackgroundColor: '#4361ee',
                                pointBorderColor: '#fff',
                                pointBorderWidth: 2
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: true,
                                    position: 'top'
                                },
                                tooltip: {
                                    mode: 'index',
                                    intersect: false
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    grid: {
                                        color: 'rgba(0, 0, 0, 0.1)'
                                    }
                                },
                                x: {
                                    grid: {
                                        display: false
                                    }
                                }
                            }
                        }
                    });
                }
                $(document).on('click', '.adv-l60-graph-link', function(e) {
                    e.preventDefault();
                    var modalEl = document.getElementById('adv-l60-graph-modal');
                    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                        var modal = new bootstrap.Modal(modalEl);
                        modal.show();
                        setTimeout(function() {
                            renderL60Graph('l60_spent');
                        }, 300);
                    } else {
                        modalEl.classList.add('show');
                        modalEl.style.display = 'block';
                        setTimeout(function() {
                            renderL60Graph('l60_spent');
                        }, 300);
                    }
                });
                $('#l60-graph-metric').on('change', function() {
                    var metric = $(this).val();
                    renderL60Graph(metric);
                });
            } catch (error) {
                console.error('Tabulator initialization error:', error);
            }
        };
        document.body.appendChild(tabulatorScript);
    }, 200); 

    /** START CODE FOR DATE DISABLE */

    document.addEventListener('DOMContentLoaded', function() {
        const fromDateEEbay = document.querySelector('.ebay-from-date');
        const toDateEbay = document.querySelector('.ebay-to-date');
        if (!fromDateEEbay || !toDateEbay) return;

        fromDateEEbay.addEventListener('change', function() {
            toDateEbay.min = fromDateEEbay.value;
            if (toDateEbay.value && toDateEbay.value < fromDateEEbay.value) {
                toDateEbay.value = '';
            }
        });
        toDateEbay.addEventListener('change', function() {
            fromDateEEbay.max = toDateEbay.value;
            if (fromDateEEbay.value && fromDateEEbay.value > toDateEbay.value) {
                fromDateEEbay.value = '';
            }
        });
    });



    /* END CODE FOR DATE DISABLE **/ 




});
</script>
   
@endsection
