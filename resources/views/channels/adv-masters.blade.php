@extends('layouts.vertical', ['title' => 'ADV Masters'])

@section('css')
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
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
                <h3 class="mb-0" style="color: var(--text-primary); font-weight: 700;">ADV Masters Dashboard</h3>
                <div class="d-flex align-items-center gap-3">
                    <div class="position-relative" style="width: 300px;">
                        <input type="text" class="form-control" id="search-input" placeholder="🔍 Search channels..." />
                    </div>
                </div>
            </div>

            <div class="table-responsive mt-3">
                <table class="table table-bordered table-responsive display" id="adv-master-table" style="width:100%">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 110px;">TOTAL</th>
                            <th class="text-center">L30 SALES <br><hr> {{ $total_l30_sales}}</th>
                            <th class="text-center">GPFT <br><hr> 0</th>
                            <th class="text-center">TPFT <br><hr> 0</th>
                            <th class="text-center">SPENT <br><hr> {{ $total_spent}}</th>
                            <th class="text-center">CLICKS <br><hr> {{ $total_clicks}}</th>
                            <th class="text-center">AD SALES <br><hr> {{ $total_ad_sales}}</th>
                            <th class="text-center">ACOS <br><hr> 0</th>
                            <th class="text-center">TACOS <br><hr> 0</th>     
                            <th class="text-center">AD SOLD <br><hr> {{ $total_ad_sold}}</th>        
                            <th class="text-center">CVR <br><hr> 0 </th>     
                            <th class="text-center">MISSING ADS <br><hr> {{ $total_missing}}</th>     
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
                            <td class="text-center">{{ $amazon_clicks }}</td>
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
                            <td class="text-center">{{ $amazon_missing_ads }}</td>
                        </tr>

                        <tr class="accordion-body">
                            <td class="text-center"><a href="{{ route('amazon.kw.ads') }}" target="_blank" style="text-decoration:none; color:#000000;">AMZ KW</a></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center">{{ $amazonkw_spent }}</td>
                            <td class="text-center">{{ $amazonkw_clicks }}</td>
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
                            <td class="text-center">{{ $amazonkw_missing_ads }}</td>
                        </tr>

                         <tr class="accordion-body">
                            <td class="text-center"><a href="{{ route('amazon.pt.ads') }}" target="_blank" style="text-decoration:none; color:#000000;">AMZ PT</a></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center">{{ $amazonpt_spent }}</td>
                            <td class="text-center">{{ $amazonpt_clicks }}</td>
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
                            <td class="text-center">{{ $amazonpt_missing_ads }}</td>
                        </tr>

                        <tr class="accordion-body">
                            <td class="text-center"><a href="{{ route('amazon.hl.ads') }}" target="_blank" style="text-decoration:none; color:#000000;">AMZ HL</a></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center">{{ $amazonhl_spent }}</td>
                            <td class="text-center">{{ $amazonhl_clicks }}</td>
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
                            <td class="text-center"></td>
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
                            <td class="text-center">{{ $ebay_clicks }}</td>
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
                            <td class="text-center">{{ $ebay_missing_ads }}</td>
                        </tr>

                        <tr class="accordion-body">
                            <td class="text-center"><a href="{{ route('ebay.keywords.ads') }}" target="_blank" style="text-decoration:none; color:#000000;">EB KW</a></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center">{{ $ebaykw_spent }}</td>
                            <td class="text-center">{{ $ebaykw_clicks }}</td>
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
                            <td class="text-center">{{ $ebaykw_missing_ads }}</td>
                        </tr>

                        <tr class="accordion-body">
                            <td class="text-center"><a href="{{ route('ebay.pmp.ads') }}" target="_blank" style="text-decoration:none; color:#000000;">EB PMT</a></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center">{{ $ebaypmt_spent }}</td>
                            <td class="text-center">{{ $ebaypmt_clicks }}</td>
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
                            <td class="text-center">{{ $ebaypmt_missing_ads }}</td>
                        </tr>

                        <tr style="background-color:#cfe2f3;" class="accordion-header">
                            <td class="text-center"><b>EBAY 2</b></td>
                            <td class="text-center">{{ $ebay2_l30_sales }}</td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center">{{ $ebay2_spent }}</td>
                            <td class="text-center">{{ $ebay2_clicks }}</td>
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
                            <td class="text-center">{{ $ebay2_missing_ads }}</td>
                        </tr>

                        <tr class="accordion-body">
                            <td class="text-center">EB PMT</td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center">{{ $ebay2pmt_spent }}</td>
                            <td class="text-center">{{ $ebay2pmt_clicks }}</td>
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
                            <td class="text-center">{{ $ebay2pmt_missing_ads }}</td>
                        </tr>

                        <tr style="background-color:#cfe2f3;" class="accordion-header">
                            <td class="text-center"><b>EBAY 3</b></td>
                            <td class="text-center">{{ $ebay3_l30_sales }}</td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center">{{ $ebay3_spent }}</td>
                            <td class="text-center">{{ $ebay3_clicks }}</td>
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
                            <td class="text-center">{{ $ebay3_missing_ads }}</td>
                        </tr>

                         <tr class="accordion-body">
                            <td class="text-center">EB KW</td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center">{{ $ebay3kw_spent }}</td>
                            <td class="text-center">{{ $ebay3kw_clicks }}</td>
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
                            <td class="text-center">{{ $ebay3kw_missing_ads }}</td>
                        </tr>

                        <tr class="accordion-body">
                            <td class="text-center">EB PMT</td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center">{{ $ebay3pmt_spent }}</td>
                            <td class="text-center">{{ $ebay3pmt_clicks }}</td>
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
                            <td class="text-center">{{ $ebay3pmt_missing_ads }}</td>
                        </tr>

                        <tr style="background-color:#cfe2f3;" class="accordion-header">
                            <td class="text-center"><b>WALMART</b></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center">{{ $walmart_spent }}</td>
                            <td class="text-center">{{ $walmart_clicks }}</td>
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
                            <td class="text-center"></td>
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
                        </tr>

                         <tr class="accordion-body">
                            <td class="text-center">G SHOPPING</td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center">{{ $gshoping_spent }}</td>
                            <td class="text-center">{{ $gshoping_clicks }}</td>
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
                            <td class="text-center"></td>
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
  {{-- <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script> --}}
  {{-- <script src="https://cdnjs.cloudflare.com/ajax/libs/colresizable/1.6.0/colResizable-1.6.min.js"></script> --}}
 
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
        var dtScript = document.createElement('script');
        dtScript.src = "https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js";
        dtScript.onload = function() {
            var colScript = document.createElement('script');
            colScript.src = "https://cdnjs.cloudflare.com/ajax/libs/colresizable/1.6.0/colResizable-1.6.min.js";
            colScript.onload = function() {

                let table = $('#adv-master-table').DataTable({
                    paging: false,
                    info: false,
                    searching: true,
                    scrollX:false,
                    autoWidth: false,
                    ordering:false,
                });

                $('.dataTables_filter').hide();
                
                $('#adv-master-table').colResizable({
                    liveDrag: true,
                    resizeMode: 'fit', // or 'flex'
                    gripInnerHtml: "<div class='grip'></div>",
                    draggingClass: "dragging"
                });

                $('#search-input').on('keyup', function() {
                    table.search(this.value).draw();
                });

            };
            document.body.appendChild(colScript); 
        };
        document.body.appendChild(dtScript); 
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
