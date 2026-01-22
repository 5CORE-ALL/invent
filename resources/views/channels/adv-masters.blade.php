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
            border-radius: 12px;
            border: 2px solid var(--border-color);
            padding: 12px 20px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: white;
        }

        #search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        #search-input::placeholder {
            color: var(--text-secondary);
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

        /* Tabulator vertical headers (columns 4+) */
        .tabulator .tabulator-col:nth-child(n+4) .tabulator-col-content {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            white-space: nowrap;
            height: 120px;
            min-width: 36px;
            vertical-align: bottom;
            padding: 8px 6px;
            line-height: 1.3;
            overflow: visible;
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
        }

        #adv-master-table thead th:first-child {
            border-top-left-radius: 12px;
        }

        #adv-master-table thead th:last-child {
            border-top-right-radius: 12px;
        }

        /* Vertical headers (columns 4+) */
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
        }

        #adv-master-table thead th.th-vertical hr {
            margin: 4px auto;
            width: 80%;
        }

        /* Horizontal headers (first 3 columns) */
        #adv-master-table thead th.th-horizontal {
            white-space: normal;
        }

        #adv-master-table tbody td {
            padding: 14px 12px;
            border-bottom: 1px solid var(--border-color);
            font-size: 14px;
            transition: background-color 0.2s ease;
        }

        #adv-master-table tbody tr:hover {
            background-color: #F9FAFB;
        }

        #adv-master-table tbody tr.accordion-header {
            background: linear-gradient(135deg, #E0E7FF 0%, #C7D2FE 100%);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        #adv-master-table tbody tr.accordion-header:hover {
            background: linear-gradient(135deg, #C7D2FE 0%, #A5B4FC 100%);
            transform: scale(1.01);
        }

        #adv-master-table tbody tr.accordion-body {
            background-color: #FAFBFC;
            border-left: 3px solid var(--primary-color);
        }

        #adv-master-table tbody tr.accordion-body:hover {
            background-color: #F3F4F6;
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

        /* Better Visual Hierarchy */
        #adv-master-table tbody tr.accordion-header td:first-child {
            font-size: 15px;
            letter-spacing: 0.3px;
        }

        /* Smooth Transitions */
        * {
            transition: background-color 0.2s ease, color 0.2s ease, border-color 0.2s ease;
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
                    <div class="d-flex align-items-center gap-2 mt-2">
                        <span class="badge bg-primary" style="font-size: 14px; padding: 8px 12px;">
                            L30 Ad Spend: ${{ number_format($total_spent, 2) }}
                        </span>
                        <span class="badge bg-info" style="font-size: 14px; padding: 8px 12px;">
                            L60 Ad Spend: ${{ number_format($total_l60_spent ?? 0, 2) }}
                        </span>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <div class="position-relative" style="width: 300px;">
                        <input type="text" class="form-control" id="search-input" placeholder="🔍 Search channels..." />
                    </div>
                    <button type="button" class="btn btn-primary" id="add-channel-btn" title="Add New Channel">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" style="margin-right: 6px; vertical-align: middle;">
                            <path d="M8 0L10.5 6L16 8L10.5 10L8 16L5.5 10L0 8L5.5 6L8 0Z"/>
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
                            <th class="text-center th-horizontal">L30 SALES <br><hr> {{ $total_l30_sales}}</th>
                            <th class="text-center th-horizontal">GPFT <br><hr> 0</th>
                            <th class="text-center th-vertical">TPFT <br><hr> 0</th>
                            <th class="text-center th-vertical">L30 SPENT <br><hr> {{ $total_spent}}</th>
                            <th class="text-center th-vertical">L60 SPENT <br><hr> {{ $total_l60_spent ?? 0}}</th>
                            <th class="text-center th-vertical">GRW <br><hr> 
                                @php
                                    $total_grw = ($total_l60_spent > 0) ? ($total_spent / $total_l60_spent) * 100 : 0;
                                    echo number_format($total_grw, 2) . '%';
                                @endphp
                            </th>
                            <th class="text-center th-vertical">L30 CLKS <br><hr> {{ $total_clicks}}</th>
                            <th class="text-center th-vertical">L60 CLICKS <br><hr> {{ $total_l60_clicks ?? 0}}</th>
                            <th class="text-center th-vertical">GRW CLKS <br><hr>
                                @php
                                    $total_grw_clks = (($total_l60_clicks ?? 0) > 0) ? ($total_clicks / ($total_l60_clicks ?? 1)) * 100 : 0;
                                    echo number_format($total_grw_clks, 2) . '%';
                                @endphp
                            </th>
                            <th class="text-center th-vertical">AD SALES <br><hr> {{ $total_ad_sales}}</th>
                            <th class="text-center th-vertical">L30 ACOS% <br><hr> 0</th>
                            <th class="text-center th-vertical">L60 ACOS% <br><hr> 0</th>
                            <th class="text-center th-vertical">Ctrl ACOS% <br><hr>
                                @php
                                    $total_l30_acos_val = ($total_ad_sales > 0) ? ($total_spent / $total_ad_sales) * 100 : 0;
                                    $total_l60_acos_val = (($total_l60_ad_sales ?? 0) > 0) ? (($total_l60_spent ?? 0) / ($total_l60_ad_sales ?? 1)) * 100 : 0;
                                    if($total_l60_acos_val > 0){
                                        $total_ctrl_acos = (($total_l30_acos_val - $total_l60_acos_val) / $total_l60_acos_val) * 100;
                                    }else{
                                        $total_ctrl_acos = 0;
                                    }
                                    $total_ctrl_acos = number_format($total_ctrl_acos, 2);
                                    $total_ctrl_color = ($total_ctrl_acos < 0) ? 'color: #006400;' : 'color: #8B0000;';
                                @endphp
                                <span style="{{ $total_ctrl_color }}">{{ $total_ctrl_acos.' %' }}</span>
                            </th>
                            <th class="text-center th-vertical">TACOS <br><hr> 0</th>     
                            <th class="text-center th-vertical">AD SOLD <br><hr> {{ $total_ad_sold}}</th>        
                            <th class="text-center th-vertical">CVR <br><hr> 0 </th>     
                            <th class="text-center th-vertical">CVR 60 <br><hr>
                                @php
                                    if(($total_l60_clicks ?? 0) > 0){
                                        $total_cvr_60 = (($total_l60_ad_sold ?? 0) / ($total_l60_clicks ?? 1)) * 100;
                                        $total_cvr_60 = number_format($total_cvr_60, 2);
                                    }else{
                                        $total_cvr_60 = 0;
                                    }
                                    $total_cvr_60 = round($total_cvr_60, 1);
                                @endphp
                                {{ $total_cvr_60.' %' }}
                            </th>
                            <th class="text-center th-vertical">Grw CVR <br><hr>
                                @php
                                    $total_cvr = 0;
                                    $total_cvr_60_val = 0;
                                    if(($total_clicks ?? 0) > 0){
                                        $total_cvr = (($total_ad_sold ?? 0) / ($total_clicks ?? 1)) * 100;
                                    }
                                    if(($total_l60_clicks ?? 0) > 0){
                                        $total_cvr_60_val = (($total_l60_ad_sold ?? 0) / ($total_l60_clicks ?? 1)) * 100;
                                    }
                                    if($total_cvr_60_val > 0){
                                        $total_grw_cvr = (($total_cvr - $total_cvr_60_val) / $total_cvr_60_val) * 100;
                                    }else{
                                        $total_grw_cvr = 0;
                                    }
                                    $total_grw_cvr = number_format($total_grw_cvr, 2);
                                @endphp
                                {{ $total_grw_cvr.' %' }}
                            </th>
                            <th class="text-center th-vertical">MISSING ADS <br><hr> {{ $total_missing}}</th>
                            <th class="text-center th-vertical">ACTIONS</th>     
                        </tr>
                    </thead>
                    <tbody>
                        <tr style="background-color:#cfe2f3;" class="accordion-header">
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
                            <td class="text-center">{{ $amazon_l30_sales }}</td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center">{{ $amazon_spent }}</td>
                            <td class="text-center">{{ $amazon_l60_spent ?? 0 }}</td>
                            <td class="text-center">
                                @php
                                    $amazon_grw = (($amazon_l60_spent ?? 0) > 0) ? ($amazon_spent / ($amazon_l60_spent ?? 1)) * 100 : 0;
                                    echo number_format($amazon_grw, 2) . '%';
                                @endphp
                            </td>
                            <td class="text-center">{{ $amazon_clicks }}</td>
                            <td class="text-center">{{ $amazon_l60_clicks ?? 0 }}</td>
                            <td class="text-center">
                                @php
                                    $grw_clks = (($amazon_l60_clicks ?? 0) > 0) ? ($amazon_clicks / ($amazon_l60_clicks ?? 1)) * 100 : 0;
                                    echo number_format($grw_clks, 2) . '%';
                                @endphp
                            </td>
                            <td class="text-center">{{ $amazon_ad_sales }}</td>
                            <td class="text-center">
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
                                    $grw_cvr = number_format($grw_cvr, 2);
                                @endphp
                                {{ $grw_cvr.' %' }}
                            </td>
                            <td class="text-center">{{ $amazon_missing_ads }}</td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-warning edit-channel-btn" data-channel="AMAZON" title="Edit Channel">
                                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                                        <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5L13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.207l6.5-6.5zm-9.761 5.175l-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>

                        <tr class="accordion-body">
                            <td class="text-center"><a href="{{ route('amazon.kw.ads') }}" target="_blank" style="text-decoration:none; color:#000000;">AMZ KW</a></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center">{{ $amazonkw_spent }}</td>
                            <td class="text-center">{{ $amazonkw_l60_spent ?? 0 }}</td>
                            <td class="text-center">
                                @php
                                    $amazonkw_grw = (($amazonkw_l60_spent ?? 0) > 0) ? ($amazonkw_spent / ($amazonkw_l60_spent ?? 1)) * 100 : 0;
                                    echo number_format($amazonkw_grw, 2) . '%';
                                @endphp
                            </td>
                            <td class="text-center">{{ $amazonkw_clicks }}</td>
                            <td class="text-center">{{ $amazonkw_l60_clicks ?? 0 }}</td>
                            <td class="text-center">
                                @php
                                    $grw_clks = (($amazonkw_l60_clicks ?? 0) > 0) ? ($amazonkw_clicks / ($amazonkw_l60_clicks ?? 1)) * 100 : 0;
                                    echo number_format($grw_clks, 2) . '%';
                                @endphp
                            </td>
                            <td class="text-center">{{ $amazonkw_ad_sales }}</td>
                            <td class="text-center">
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
                                    $grw_cvr = number_format($grw_cvr, 2);
                                @endphp
                                {{ $grw_cvr.' %' }}
                            </td>
                            <td class="text-center">{{ $amazonkw_missing_ads }}</td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-warning edit-channel-btn" data-channel="AMZ KW" title="Edit Channel">
                                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                                        <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5L13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.207l6.5-6.5zm-9.761 5.175l-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>

                         <tr class="accordion-body">
                            <td class="text-center"><a href="{{ route('amazon.pt.ads') }}" target="_blank" style="text-decoration:none; color:#000000;">AMZ PT</a></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center">{{ $amazonpt_spent }}</td>
                            <td class="text-center">{{ $amazonpt_l60_spent ?? 0 }}</td>
                            <td class="text-center">
                                @php
                                    $amazonpt_grw = (($amazonpt_l60_spent ?? 0) > 0) ? ($amazonpt_spent / ($amazonpt_l60_spent ?? 1)) * 100 : 0;
                                    echo number_format($amazonpt_grw, 2) . '%';
                                @endphp
                            </td>
                            <td class="text-center">{{ $amazonpt_clicks }}</td>
                            <td class="text-center">{{ $amazonpt_l60_clicks ?? 0 }}</td>
                            <td class="text-center">
                                @php
                                    $grw_clks = (($amazonpt_l60_clicks ?? 0) > 0) ? ($amazonpt_clicks / ($amazonpt_l60_clicks ?? 1)) * 100 : 0;
                                    echo number_format($grw_clks, 2) . '%';
                                @endphp
                            </td>
                            <td class="text-center">{{ $amazonpt_ad_sales }}</td>
                            <td class="text-center">
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
                                    $grw_cvr = number_format($grw_cvr, 2);
                                @endphp
                                {{ $grw_cvr.' %' }}
                            </td>
                            <td class="text-center">{{ $amazonpt_missing_ads }}</td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-warning edit-channel-btn" data-channel="AMZ PT" title="Edit Channel">
                                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                                        <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5L13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.207l6.5-6.5zm-9.761 5.175l-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>

                        <tr class="accordion-body">
                            <td class="text-center"><a href="{{ route('amazon.hl.ads') }}" target="_blank" style="text-decoration:none; color:#000000;">AMZ HL</a></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center">{{ $amazonhl_spent }}</td>
                            <td class="text-center">{{ $amazonhl_l60_spent ?? 0 }}</td>
                            <td class="text-center">
                                @php
                                    $amazonhl_grw = (($amazonhl_l60_spent ?? 0) > 0) ? ($amazonhl_spent / ($amazonhl_l60_spent ?? 1)) * 100 : 0;
                                    echo number_format($amazonhl_grw, 2) . '%';
                                @endphp
                            </td>
                            <td class="text-center">{{ $amazonhl_clicks }}</td>
                            <td class="text-center">{{ $amazonhl_l60_clicks ?? 0 }}</td>
                            <td class="text-center">
                                @php
                                    $grw_clks = (($amazonhl_l60_clicks ?? 0) > 0) ? ($amazonhl_clicks / ($amazonhl_l60_clicks ?? 1)) * 100 : 0;
                                    echo number_format($grw_clks, 2) . '%';
                                @endphp
                            </td>
                            <td class="text-center">{{ $amazonhl_ad_sales }}</td>
                            <td class="text-center">
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
                                    $grw_cvr = number_format($grw_cvr, 2);
                                @endphp
                                {{ $grw_cvr.' %' }}
                            </td>
                            <td class="text-center"></td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-warning edit-channel-btn" data-channel="AMZ HL" title="Edit Channel">
                                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                                        <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5L13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.207l6.5-6.5zm-9.761 5.175l-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>

                        <tr style="background-color:#cfe2f3;" class="accordion-header">
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
                            <td class="text-center">{{ $ebay_l30_sales }}</td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center">{{ $ebay_spent }}</td>
                            <td class="text-center">{{ $ebay_l60_spent ?? 0 }}</td>
                            <td class="text-center">
                                @php
                                    $ebay_grw = (($ebay_l60_spent ?? 0) > 0) ? ($ebay_spent / ($ebay_l60_spent ?? 1)) * 100 : 0;
                                    echo number_format($ebay_grw, 2) . '%';
                                @endphp
                            </td>
                            <td class="text-center">{{ $ebay_clicks }}</td>
                            <td class="text-center">{{ $ebay_l60_clicks ?? 0 }}</td>
                            <td class="text-center">
                                @php
                                    $grw_clks = (($ebay_l60_clicks ?? 0) > 0) ? ($ebay_clicks / ($ebay_l60_clicks ?? 1)) * 100 : 0;
                                    echo number_format($grw_clks, 2) . '%';
                                @endphp
                            </td>
                            <td class="text-center">{{ $ebay_ad_sales }}</td>
                            <td class="text-center">
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
                                    $grw_cvr = number_format($grw_cvr, 2);
                                @endphp
                                {{ $grw_cvr.' %' }}
                            </td>
                            <td class="text-center">{{ $ebay_missing_ads }}</td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-warning edit-channel-btn" data-channel="EBAY" title="Edit Channel">
                                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                                        <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5L13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.207l6.5-6.5zm-9.761 5.175l-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>

                        <tr class="accordion-body">
                            <td class="text-center"><a href="{{ route('ebay.keywords.ads') }}" target="_blank" style="text-decoration:none; color:#000000;">EB KW</a></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center">{{ $ebaykw_spent }}</td>
                            <td class="text-center">{{ $ebaykw_l60_spent ?? 0 }}</td>
                            <td class="text-center">
                                @php
                                    $ebaykw_grw = (($ebaykw_l60_spent ?? 0) > 0) ? ($ebaykw_spent / ($ebaykw_l60_spent ?? 1)) * 100 : 0;
                                    echo number_format($ebaykw_grw, 2) . '%';
                                @endphp
                            </td>
                            <td class="text-center">{{ $ebaykw_clicks }}</td>
                            <td class="text-center">{{ $ebaykw_l60_clicks ?? 0 }}</td>
                            <td class="text-center">
                                @php
                                    $grw_clks = (($ebaykw_l60_clicks ?? 0) > 0) ? ($ebaykw_clicks / ($ebaykw_l60_clicks ?? 1)) * 100 : 0;
                                    echo number_format($grw_clks, 2) . '%';
                                @endphp
                            </td>
                            <td class="text-center">{{ $ebaykw_ad_sales }}</td>
                            <td class="text-center">
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
                                    $grw_cvr = number_format($grw_cvr, 2);
                                @endphp
                                {{ $grw_cvr.' %' }}
                            </td>
                            <td class="text-center">{{ $ebaykw_missing_ads }}</td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-warning edit-channel-btn" data-channel="EB KW" title="Edit Channel">
                                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                                        <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5L13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.207l6.5-6.5zm-9.761 5.175l-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>

                        <tr class="accordion-body">
                            <td class="text-center"><a href="{{ route('ebay.pmp.ads') }}" target="_blank" style="text-decoration:none; color:#000000;">EB PMT</a></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center">{{ $ebaypmt_spent }}</td>
                            <td class="text-center">{{ $ebaypmt_l60_spent ?? 0 }}</td>
                            <td class="text-center">
                                @php
                                    $ebaypmt_grw = (($ebaypmt_l60_spent ?? 0) > 0) ? ($ebaypmt_spent / ($ebaypmt_l60_spent ?? 1)) * 100 : 0;
                                    echo number_format($ebaypmt_grw, 2) . '%';
                                @endphp
                            </td>
                            <td class="text-center">{{ $ebaypmt_clicks }}</td>
                            <td class="text-center">{{ $ebaypmt_l60_clicks ?? 0 }}</td>
                            <td class="text-center">
                                @php
                                    $grw_clks = (($ebaypmt_l60_clicks ?? 0) > 0) ? ($ebaypmt_clicks / ($ebaypmt_l60_clicks ?? 1)) * 100 : 0;
                                    echo number_format($grw_clks, 2) . '%';
                                @endphp
                            </td>
                            <td class="text-center">{{ $ebaypmt_ad_sales }}</td>
                            <td class="text-center">
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
                                    $grw_cvr = number_format($grw_cvr, 2);
                                @endphp
                                {{ $grw_cvr.' %' }}
                            </td>
                            <td class="text-center">{{ $ebaypmt_missing_ads }}</td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-warning edit-channel-btn" data-channel="EB PMT" title="Edit Channel">
                                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                                        <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5L13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.207l6.5-6.5zm-9.761 5.175l-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>

                        <tr style="background-color:#cfe2f3;" class="accordion-header">
                            <td class="text-center"><b>EBAY 2</b></td>
                            <td class="text-center">{{ $ebay2_l30_sales }}</td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center">{{ $ebay2_spent }}</td>
                            <td class="text-center">{{ $ebay2_l60_spent ?? 0 }}</td>
                            <td class="text-center">
                                @php
                                    $ebay2_grw = (($ebay2_l60_spent ?? 0) > 0) ? ($ebay2_spent / ($ebay2_l60_spent ?? 1)) * 100 : 0;
                                    echo number_format($ebay2_grw, 2) . '%';
                                @endphp
                            </td>
                            <td class="text-center">{{ $ebay2_clicks }}</td>
                            <td class="text-center">{{ $ebay2_l60_clicks ?? 0 }}</td>
                            <td class="text-center">
                                @php
                                    $grw_clks = (($ebay2_l60_clicks ?? 0) > 0) ? ($ebay2_clicks / ($ebay2_l60_clicks ?? 1)) * 100 : 0;
                                    echo number_format($grw_clks, 2) . '%';
                                @endphp
                            </td>
                            <td class="text-center">{{ $ebay2_ad_sales }}</td>
                            <td class="text-center">
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
                                    $grw_cvr = number_format($grw_cvr, 2);
                                @endphp
                                {{ $grw_cvr.' %' }}
                            </td>
                            <td class="text-center">{{ $ebay2_missing_ads }}</td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-warning edit-channel-btn" data-channel="EBAY 2" title="Edit Channel">
                                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                                        <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5L13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.207l6.5-6.5zm-9.761 5.175l-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>

                        <tr class="accordion-body">
                            <td class="text-center">EB PMT</td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center">{{ $ebay2pmt_spent }}</td>
                            <td class="text-center">{{ $ebay2pmt_l60_spent ?? 0 }}</td>
                            <td class="text-center">
                                @php
                                    $grw = (($ebay2pmt_l60_spent ?? 0) > 0) ? ($ebay2pmt_spent / ($ebay2pmt_l60_spent ?? 1)) * 100 : 0;
                                    echo number_format($grw, 2) . '%';
                                @endphp
                            </td>
                            <td class="text-center">{{ $ebay2pmt_clicks }}</td>
                            <td class="text-center">{{ $ebay2pmt_l60_clicks ?? 0 }}</td>
                            <td class="text-center">
                                @php
                                    $grw_clks = (($ebay2pmt_l60_clicks ?? 0) > 0) ? ($ebay2pmt_clicks / ($ebay2pmt_l60_clicks ?? 1)) * 100 : 0;
                                    echo number_format($grw_clks, 2) . '%';
                                @endphp
                            </td>
                            <td class="text-center">{{ $ebay2pmt_ad_sales }}</td>
                            <td class="text-center">
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
                                    $grw_cvr = number_format($grw_cvr, 2);
                                @endphp
                                {{ $grw_cvr.' %' }}
                            </td>
                            <td class="text-center">{{ $ebay2pmt_missing_ads }}</td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-warning edit-channel-btn" data-channel="EB PMT" title="Edit Channel">
                                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                                        <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5L13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.207l6.5-6.5zm-9.761 5.175l-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>

                        <tr style="background-color:#cfe2f3;" class="accordion-header">
                            <td class="text-center"><b>EBAY 3</b></td>
                            <td class="text-center">{{ $ebay3_l30_sales }}</td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center">{{ $ebay3_spent }}</td>
                            <td class="text-center">{{ $ebay3_l60_spent ?? 0 }}</td>
                            <td class="text-center">
                                @php
                                    $grw = (($ebay3_l60_spent ?? 0) > 0) ? ($ebay3_spent / ($ebay3_l60_spent ?? 1)) * 100 : 0;
                                    echo number_format($grw, 2) . '%';
                                @endphp
                            </td>
                            <td class="text-center">{{ $ebay3_clicks }}</td>
                            <td class="text-center">{{ $ebay3_l60_clicks ?? 0 }}</td>
                            <td class="text-center">
                                @php
                                    $grw_clks = (($ebay3_l60_clicks ?? 0) > 0) ? ($ebay3_clicks / ($ebay3_l60_clicks ?? 1)) * 100 : 0;
                                    echo number_format($grw_clks, 2) . '%';
                                @endphp
                            </td>
                            <td class="text-center">{{ $ebay3_ad_sales }}</td>
                            <td class="text-center">
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
                                    $grw_cvr = number_format($grw_cvr, 2);
                                @endphp
                                {{ $grw_cvr.' %' }}
                            </td>
                            <td class="text-center">{{ $ebay3_missing_ads }}</td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-warning edit-channel-btn" data-channel="EBAY 3" title="Edit Channel">
                                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                                        <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5L13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.207l6.5-6.5zm-9.761 5.175l-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>

                         <tr class="accordion-body">
                            <td class="text-center">EB KW</td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center">{{ $ebay3kw_spent }}</td>
                            <td class="text-center">{{ $ebay3kw_l60_spent ?? 0 }}</td>
                            <td class="text-center">
                                @php
                                    $grw = (($ebay3kw_l60_spent ?? 0) > 0) ? ($ebay3kw_spent / ($ebay3kw_l60_spent ?? 1)) * 100 : 0;
                                    echo number_format($grw, 2) . '%';
                                @endphp
                            </td>
                            <td class="text-center">{{ $ebay3kw_clicks }}</td>
                            <td class="text-center">{{ $ebay3kw_l60_clicks ?? 0 }}</td>
                            <td class="text-center">
                                @php
                                    $grw_clks = (($ebay3kw_l60_clicks ?? 0) > 0) ? ($ebay3kw_clicks / ($ebay3kw_l60_clicks ?? 1)) * 100 : 0;
                                    echo number_format($grw_clks, 2) . '%';
                                @endphp
                            </td>
                            <td class="text-center">{{ $ebay3kw_ad_sales }}</td>
                            <td class="text-center">
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
                                    $grw_cvr = number_format($grw_cvr, 2);
                                @endphp
                                {{ $grw_cvr.' %' }}
                            </td>
                            <td class="text-center">{{ $ebay3kw_missing_ads }}</td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-warning edit-channel-btn" data-channel="EB KW" title="Edit Channel">
                                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                                        <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5L13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.207l6.5-6.5zm-9.761 5.175l-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>

                        <tr class="accordion-body">
                            <td class="text-center">EB PMT</td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center">{{ $ebay3pmt_spent }}</td>
                            <td class="text-center">{{ $ebay3pmt_l60_spent ?? 0 }}</td>
                            <td class="text-center">
                                @php
                                    $grw = (($ebay3pmt_l60_spent ?? 0) > 0) ? ($ebay3pmt_spent / ($ebay3pmt_l60_spent ?? 1)) * 100 : 0;
                                    echo number_format($grw, 2) . '%';
                                @endphp
                            </td>
                            <td class="text-center">{{ $ebay3pmt_clicks }}</td>
                            <td class="text-center">{{ $ebay3pmt_l60_clicks ?? 0 }}</td>
                            <td class="text-center">
                                @php
                                    $grw_clks = (($ebay3pmt_l60_clicks ?? 0) > 0) ? ($ebay3pmt_clicks / ($ebay3pmt_l60_clicks ?? 1)) * 100 : 0;
                                    echo number_format($grw_clks, 2) . '%';
                                @endphp
                            </td>
                            <td class="text-center">{{ $ebay3pmt_ad_sales }}</td>
                            <td class="text-center">
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
                                    $grw_cvr = number_format($grw_cvr, 2);
                                @endphp
                                {{ $grw_cvr.' %' }}
                            </td>
                            <td class="text-center">{{ $ebay3pmt_missing_ads }}</td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-warning edit-channel-btn" data-channel="EB PMT" title="Edit Channel">
                                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                                        <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5L13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.207l6.5-6.5zm-9.761 5.175l-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>

                        <tr style="background-color:#cfe2f3;" class="accordion-header">
                            <td class="text-center"><b>WALMART</b></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center">{{ $walmart_spent }}</td>
                            <td class="text-center">{{ $walmart_l60_spent ?? 0 }}</td>
                            <td class="text-center">
                                @php
                                    $grw = (($walmart_l60_spent ?? 0) > 0) ? ($walmart_spent / ($walmart_l60_spent ?? 1)) * 100 : 0;
                                    echo number_format($grw, 2) . '%';
                                @endphp
                            </td>
                            <td class="text-center">{{ $walmart_clicks }}</td>
                            <td class="text-center">{{ $walmart_l60_clicks ?? 0 }}</td>
                            <td class="text-center">
                                @php
                                    $grw_clks = (($walmart_l60_clicks ?? 0) > 0) ? ($walmart_clicks / ($walmart_l60_clicks ?? 1)) * 100 : 0;
                                    echo number_format($grw_clks, 2) . '%';
                                @endphp
                            </td>
                            <td class="text-center">{{ $walmart_ad_sales }}</td>
                            <td class="text-center">
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
                                    $grw_cvr = number_format($grw_cvr, 2);
                                @endphp
                                {{ $grw_cvr.' %' }}
                            </td>
                            <td class="text-center"></td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-warning edit-channel-btn" data-channel="WALMART" title="Edit Channel">
                                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                                        <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5L13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.207l6.5-6.5zm-9.761 5.175l-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>

                        <tr style="background-color:#cfe2f3;" class="accordion-header">
                            <td class="text-center"><b>SHOPIFY</b></td>
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
                                <button type="button" class="btn btn-sm btn-warning edit-channel-btn" data-channel="SHOPIFY" title="Edit Channel">
                                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                                        <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5L13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.207l6.5-6.5zm-9.761 5.175l-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>

                         <tr class="accordion-body">
                            <td class="text-center">G SHOPPING</td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center">{{ $gshoping_spent }}</td>
                            <td class="text-center">{{ $gshoping_l60_spent ?? 0 }}</td>
                            <td class="text-center">
                                @php
                                    $grw = (($gshoping_l60_spent ?? 0) > 0) ? ($gshoping_spent / ($gshoping_l60_spent ?? 1)) * 100 : 0;
                                    echo number_format($grw, 2) . '%';
                                @endphp
                            </td>
                            <td class="text-center">{{ $gshoping_clicks }}</td>
                            <td class="text-center">{{ $gshoping_l60_clicks ?? 0 }}</td>
                            <td class="text-center">
                                @php
                                    $grw_clks = (($gshoping_l60_clicks ?? 0) > 0) ? ($gshoping_clicks / ($gshoping_l60_clicks ?? 1)) * 100 : 0;
                                    echo number_format($grw_clks, 2) . '%';
                                @endphp
                            </td>
                            <td class="text-center">{{ $gshoping_ad_sales }}</td>
                            <td class="text-center">
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
                                    $grw_cvr = number_format($grw_cvr, 2);
                                @endphp
                                {{ $grw_cvr.' %' }}
                            </td>
                            <td class="text-center"></td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-warning edit-channel-btn" data-channel="G SHOPPING" title="Edit Channel">
                                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                                        <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5L13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.207l6.5-6.5zm-9.761 5.175l-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>

                         <tr class="accordion-body">
                            <td class="text-center">G SERP</td>
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
                                <button type="button" class="btn btn-sm btn-warning edit-channel-btn" data-channel="G SERP" title="Edit Channel">
                                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                                        <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5L13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.207l6.5-6.5zm-9.761 5.175l-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>

                        <tr class="accordion-body">
                            <td class="text-center">FB CARAOUSAL</td>
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
                                <button type="button" class="btn btn-sm btn-warning edit-channel-btn" data-channel="FB CARAOUSAL" title="Edit Channel">
                                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                                        <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5L13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.207l6.5-6.5zm-9.761 5.175l-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>

                        <tr class="accordion-body">
                            <td class="text-center">FB VIDEO</td>
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
                                <button type="button" class="btn btn-sm btn-warning edit-channel-btn" data-channel="FB VIDEO" title="Edit Channel">
                                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                                        <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5L13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.207l6.5-6.5zm-9.761 5.175l-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>

                        <tr class="accordion-body">
                            <td class="text-center">INSTA CARAOUSAL</td>
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
                                <button type="button" class="btn btn-sm btn-warning edit-channel-btn" data-channel="INSTA CARAOUSAL" title="Edit Channel">
                                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                                        <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5L13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.207l6.5-6.5zm-9.761 5.175l-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>

                        <tr class="accordion-body">
                            <td class="text-center">INSTA VIDEO</td>
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
                                <button type="button" class="btn btn-sm btn-warning edit-channel-btn" data-channel="INSTA VIDEO" title="Edit Channel">
                                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                                        <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5L13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.207l6.5-6.5zm-9.761 5.175l-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>

                        <tr class="accordion-body">
                            <td class="text-center">YOUTUBE</td>
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
                                <button type="button" class="btn btn-sm btn-warning edit-channel-btn" data-channel="YOUTUBE" title="Edit Channel">
                                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                                        <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5L13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.207l6.5-6.5zm-9.761 5.175l-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>

                        <tr style="background-color:#cfe2f3;" class="accordion-header">
                            <td class="text-center"><b>TIKTOK</b></td>
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
                                <button type="button" class="btn btn-sm btn-warning edit-channel-btn" data-channel="TIKTOK" title="Edit Channel">
                                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                                        <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5L13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.207l6.5-6.5zm-9.761 5.175l-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>
                    
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
                                <p style="display: flex; align-items: justify-content: center; center; gap: 6px; margin: 0;">
                                    <span class="spend-color"></span>
                                    <b class="label-text">${{ $amazon_spent }}</b>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="shadow p-3 mb-1 bg-white rounded">
                                <p class="title-label">Clicks</p>
                                <p style="display: flex; align-items: justify-content: center; center; gap: 6px; margin: 0;">
                                    <span class="clicks-color"></span>
                                    <b class="label-text">{{ $amazon_clicks }}</b>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="shadow p-3 mb-1 bg-white rounded">
                                <p class="title-label">Ad-Sales</p>
                                <p style="display: flex; align-items: justify-content: center; center; gap: 6px; margin: 0;">
                                    <span class="adsales-color"></span>
                                    <b class="label-text">${{ $amazon_ad_sales }}</b>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="shadow p-3 mb-1 bg-white rounded">
                                <p class="title-label">Ad-Sold</p>
                                <p style="display: flex; align-items: justify-content: center; center; gap: 6px; margin: 0;">
                                    <span class="adsold-color"></span>
                                    <b class="label-text">${{ $amazon_ad_sold }}</b>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="shadow p-3 mb-1 bg-white rounded">
                                <p class="title-label">CPC</p>
                                <p style="display: flex; align-items: justify-content: center; center; gap: 6px; margin: 0;">
                                    <span class="cpc-color"></span>
                                    <b class="label-text"></b>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="shadow p-3 mb-1 bg-white rounded">
                                <p class="title-label">CVR</p>
                                <p style="display: flex; align-items: justify-content: center; center; gap: 6px; margin: 0;">
                                    <span class="cvr-color"></span>
                                    <b class="label-text">{{ $amazon_cvr }} %</b>
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
                                <p style="display: flex; align-items: justify-content: center; center; gap: 6px; margin: 0;">
                                    <span class="spend-color"></span>
                                    <b class="label-text">${{ $ebay_spent }}</b>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="shadow p-3 mb-1 bg-white rounded">
                                <p class="title-label">Clicks</p>
                                <p style="display: flex; align-items: justify-content: center; center; gap: 6px; margin: 0;">
                                    <span class="clicks-color"></span>
                                    <b class="label-text">{{ $ebay_clicks }}</b>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="shadow p-3 mb-1 bg-white rounded">
                                <p class="title-label">Ad-Sales</p>
                                <p style="display: flex; align-items: justify-content: center; center; gap: 6px; margin: 0;">
                                    <span class="adsales-color"></span>
                                    <b class="label-text">${{ $ebay_ad_sales }}</b>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="shadow p-3 mb-1 bg-white rounded">
                                <p class="title-label">Ad-Sold</p>
                                <p style="display: flex; align-items: justify-content: center; center; gap: 6px; margin: 0;">
                                    <span class="adsold-color"></span>
                                    <b class="label-text">${{ $ebay_ad_sold }}</b>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="shadow p-3 mb-1 bg-white rounded">
                                <p class="title-label">CVR</p>
                                <p style="display: flex; align-items: justify-content: center; center; gap: 6px; margin: 0;">
                                    <span class="cvr-color"></span>
                                    <b class="label-text">{{ $ebay_cvr }} %</b>
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

@endsection

@section('script')
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
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

    $(".accordion-body").hide();
    $(".accordion-header").click(function() {
        $(this).nextUntil(".accordion-header").slideToggle(200);
    });
   
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

                // Extract column definitions from header
                var columns = [];
                var headerRow = tableElement.querySelector('thead tr');
                var headerCells = headerRow.querySelectorAll('th');
                
                headerCells.forEach(function(header, index) {
                    var headerHtml = header.innerHTML;
                    columns.push({
                        title: headerHtml,
                        field: 'col' + index,
                        formatter: 'html',
                        headerSort: false,
                        resizable: true,
                        width: index === 0 ? 110 : undefined,
                        minWidth: 80,
                        headerTooltip: true
                    });
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
                    rowFormatter: function(row) {
                        var rowData = row.getData();
                        var rowElement = row.getElement();
                        
                        // Apply accordion header styling
                        if (rowData._isAccordionHeader) {
                            rowElement.style.backgroundColor = '#cfe2f3';
                            rowElement.style.fontWeight = 'bold';
                            rowElement.style.cursor = 'pointer';
                        }
                        
                        // Hide accordion body rows initially
                        if (rowData._isAccordionBody) {
                            rowElement.style.display = 'none';
                        }
                        
                        // Apply original row style if exists
                        if (rowData._rowStyle) {
                            rowElement.setAttribute('style', rowData._rowStyle + '; ' + rowElement.getAttribute('style'));
                        }
                    }
                });

                // Search functionality - search across all columns
                $('#search-input').on('keyup', function() {
                    var searchValue = this.value.toLowerCase();
                    if (searchValue === '') {
                        table.clearFilter();
                    } else {
                        table.setFilter(function(data) {
                            // Search across all columns
                            for (var key in data) {
                                if (key.startsWith('col') && typeof data[key] === 'string') {
                                    if (data[key].toLowerCase().includes(searchValue)) {
                                        return true;
                                    }
                                }
                            }
                            return false;
                        });
                    }
                });

                // Accordion functionality
                var accordionState = {};
                
                // Add index to each row data for easier tracking
                tableData.forEach(function(row, index) {
                    row._rowIndex = index;
                });
                
                table.on("rowClick", function(e, row) {
                    var rowData = row.getData();
                    if (rowData._isAccordionHeader) {
                        var rowIndex = rowData._rowIndex;
                        var isExpanded = accordionState[rowIndex] || false;
                        
                        // Get all rows from table
                        var allRows = table.getRows();
                        var startIndex = -1;
                        
                        // Find the clicked row's index in allRows
                        for (var i = 0; i < allRows.length; i++) {
                            if (allRows[i] === row) {
                                startIndex = i;
                                break;
                            }
                        }
                        
                        if (startIndex === -1) return;
                        
                        // Find the next accordion-header
                        var endIndex = allRows.length;
                        for (var i = startIndex + 1; i < allRows.length; i++) {
                            var checkRow = allRows[i];
                            var checkData = checkRow.getData();
                            if (checkData._isAccordionHeader) {
                                endIndex = i;
                                break;
                            }
                        }
                        
                        // Toggle visibility of accordion-body rows
                        for (var i = startIndex + 1; i < endIndex; i++) {
                            var targetRow = allRows[i];
                            if (targetRow) {
                                var targetData = targetRow.getData();
                                if (targetData._isAccordionBody) {
                                    var rowElement = targetRow.getElement();
                                    if (rowElement) {
                                        if (isExpanded) {
                                            rowElement.style.display = 'none';
                                        } else {
                                            rowElement.style.display = '';
                                        }
                                    }
                                }
                            }
                        }
                        
                        accordionState[rowIndex] = !isExpanded;
                    }
                });

                // Add channel button handler
                $('#add-channel-btn').on('click', function() {
                    alert('Add Channel functionality - to be implemented');
                });

                // Edit channel button handler (delegated event)
                $(document).on('click', '.edit-channel-btn', function() {
                    const channelName = $(this).data('channel');
                    alert('Edit Channel: ' + channelName);
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
