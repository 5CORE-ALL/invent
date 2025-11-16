@extends('layouts.vertical', ['title' => 'ADV Masters'])

@section('css')
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <style>
        .stats-card {
            background: linear-gradient(135deg, #6366F1 0%, #4F46E5 100%);
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }
        .stats-card h4 {
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 0.5rem;
        }
        .stats-card .badge {
            font-size: 1.5rem;
            padding: 0.5rem 1rem;
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        .table-container {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        th {
            cursor: col-resize;
        }
        #adv-master-table {
            table-layout: fixed; /* prevents text from overflowing */
            width: 100%;
        }

        #adv-master-table th,
        #adv-master-table td {
            overflow: hidden;
            text-overflow: ellipsis; /* optional: shows "..." for long text */
            white-space: nowrap;
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
                box-shadow: 0 8px 20px rgba(0,0,0,0.1);
                padding: 25px 35px;
            }
            h2 {
                text-align: center;
                color: #374151;
                font-weight: 600;
                margin-bottom: 15px;
            }
            canvas {
                margin-top: 10px;
            }
        }
       .spend-color {
            width: 20px;
            height: 20px;
            background-color: #6c2bd9;
            border-radius: 5px;
            display: inline-block;
        }
        .clicks-color{
            width: 20px;
            height: 20px;
            background-color: #00b894;
            border-radius: 5px;
            display: inline-block;
            
        }
        .adsales-color{
            width: 20px;
            height: 20px;
            background-color: #ed0808fc;
            border-radius: 5px;
            display: inline-block;
        }
        .adsold-color{
            width: 20px;
            height: 20px;
            background-color: #0984e3;
            border-radius: 5px;
            display: inline-block; 
        }
        .cpc-color{
            width: 20px;
            height: 20px;
            background-color: #0c293efc;
            border-radius: 5px;
            display: inline-block;
        }
        .cvr-color{
            width: 20px;
            height: 20px;
            background-color: #f6da09ee;
            border-radius: 5px;
            display: inline-block;
        }       
        .label-text{
            font-weight: 900;
            color: #000000;
        }
        .title-label{
            margin-bottom: 5px;
            font-size: 16px;
            font-weight: 700;
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
            <div class="row">
                <div class="col-md-4">
                    <input type="text" class="form-control" id="search-input" placeholder="Search..." />
                </div>
                <div class="col-md-4"></div>
                <div class="col-md-4"></div>
            <div>

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
                            <td class="text-center"><b>AMAZON</b> <button type="button" class="btn btn-primary rounded-circle p-0 ms-2" style="width: 12px; height: 12px;" data-bs-toggle="modal" data-bs-target="#amazonModal"></button></td>
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
                            <td class="text-center"><b>EBAY</b> <button type="button" class="btn btn-primary rounded-circle p-0 ms-2" style="width: 12px; height: 12px;" data-bs-toggle="modal" data-bs-target="#ebayModal"></button></td>
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
                    <div class="row mb-2">
                        <div class="col-md-3">
                            <div class="form-group">
                                {{-- <label>From Date</label> --}}
                                <input type="text" class="form-control amazon-from-date" name="amazon_from_date" onfocus="(this.type='date')" Placeholder="From Date" />
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                {{-- <label>To Date</label> --}}
                                <input type="text" class="form-control amazon-to-date" name="amazon_to_date" onfocus="(this.type='date')" Placeholder="To Date"/>
                            </div>
                        </div>
                        <div class="col-md-3 text-start"><button class="btn btn-success amazon-go" name="amazon_go">GO</button></div>
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
                    <div class="row mb-2">
                        <div class="col-md-3">
                            <div class="form-group">
                                {{-- <label>From Date</label> --}}
                                <input type="text" class="form-control ebay-from-date" name="ebay_from_date" onfocus="(this.type='date')" Placeholder="From Date" />
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                {{-- <label>To Date</label> --}}
                                <input type="text" class="form-control ebay-to-date" name="ebay_to_date" onfocus="(this.type='date')" Placeholder="To Date"/>
                            </div>
                        </div>
                        <div class="col-md-3 text-start"><button class="btn btn-success ebay-go" name="ebay_go">GO</button></div>
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
