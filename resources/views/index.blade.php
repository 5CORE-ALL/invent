@extends('layouts.vertical', ['title' => 'Dashboard', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
<!-- task dashboard css -->
<style>
        .dashboard-card {
            background: #ffffff !important;
            border-radius: 12px !important;
            padding: 20px !important;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06) !important;
            transition: all 0.2s ease !important;
            position: relative !important;
            overflow: visible !important;
            border: 1px solid #e5e7eb !important;
            height: 100% !important;
            display: flex !important;
            flex-direction: column !important;
        }

        .dashboard-card:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06) !important;
            border-color: #d1d5db !important;
        }

        .card-actions {
            position: absolute !important;
            top: 12px !important;
            right: 12px !important;
            z-index: 10 !important;
        }

        .eye-icon-btn {
            width: 36px !important;
            height: 36px !important;
            border-radius: 50% !important;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            border: none !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            cursor: pointer !important;
            transition: all 0.3s ease !important;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3) !important;
        }

        .eye-icon-btn:hover {
            transform: scale(1.1) !important;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.5) !important;
        }

        .eye-icon-btn i {
            color: white !important;
            font-size: 18px !important;
        }        .dashboard-card::before {
            content: '' !important;
            position: absolute !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            height: 4px !important;
            background: #3b82f6 !important;
            border-radius: 12px 12px 0 0 !important;
        }

        .card-header {
            display: flex !important;
            justify-content: space-between !important;
            align-items: flex-start !important;
            margin-bottom: 12px !important;
            margin-top: 8px !important;
        }

        .card-icon {
            width: 52px !important;
            height: 52px !important;
            border-radius: 10px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-size: 1.6em !important;
            margin-bottom: 0 !important;
            box-shadow: none !important;
            transition: all 0.2s ease !important;
        }
        
        .dashboard-card:hover .card-icon {
            transform: scale(1.05) !important;
        }

        .card-title {
            font-size: 1.125rem !important;
            font-weight: 600 !important;
            color: #111827 !important;
            margin-bottom: 4px !important;
            letter-spacing: -0.01em !important;
            line-height: 1.4 !important;
        }

        .card-description {
            color: #6b7280 !important;
            font-size: 0.875rem !important;
            line-height: 1.5 !important;
            margin-bottom: 0 !important;
        }

        .card-badge {
            padding: 4px 12px !important;
            border-radius: 20px !important;
            font-size: 0.8125rem !important;
            font-weight: 600 !important;
            box-shadow: none !important;
            white-space: nowrap !important;
            line-height: 1.5 !important;
        }

        .badge-cyan {
            background: #cffafe;
            color: #0891b2;
        }

        .badge-green {
            background: #d1fae5;
            color: #059669;
        }

        .badge-orange {
            background: #fed7aa;
            color: #ea580c;
        }

        .badge-pink {
            background: #fce7f3;
            color: #db2777;
        }

        .badge-purple {
            background: #e9d5ff;
            color: #9333ea;
        }

        .badge-blue {
            background: #dbeafe;
            color: #2563eb;
        }

        .badge-gray {
            background: #e5e7eb;
            color: #4b5563;
        }

        .badge-red {
            background: #fecaca;
            color: #dc2626;
        }

        .badge-yellow {
            background: #fef3c7;
            color: #d97706;
        }

        .badge-teal {
            background: #ccfbf1;
            color: #0d9488;
        }

        .badge-indigo {
            background: #e0e7ff;
            color: #4f46e5;
        }

        .badge-brown {
            background: #fef3c7;
            color: #92400e;
        }

        .subcards-preview {
            display: flex !important;
            gap: 8px !important;
            margin-top: 14px !important;
            padding-top: 14px !important;
            border-top: 1px solid #e5e7eb !important;
            flex-wrap: wrap !important;
        }

        .subcard-item {
            padding: 5px 10px !important;
            border-radius: 6px !important;
            background: #f9fafb !important;
            font-size: 0.8125rem !important;
            color: #6b7280 !important;
            display: flex !important;
            align-items: center !important;
            gap: 4px !important;
            border: none !important;
            transition: all 0.15s ease !important;
            font-weight: 500 !important;
        }
        
        .subcard-item:hover {
            background: #f3f4f6 !important;
            color: #374151 !important;
        }

        .graphs-section {
            display: grid !important;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)) !important;
            gap: 20px !important;
            margin-bottom: 30px !important;
        }

        .graph-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
        }

        .graph-title {
            font-size: 1.2em;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 20px;
        }

        .chart-container {
            position: relative;
            height: 250px;
        }

        .bar {
            display: flex;
            align-items: flex-end;
            justify-content: space-around;
            height: 100%;
            gap: 10px;
        }

        .bar-item {
            flex: 1;
            background: #3b82f6;
            border-radius: 8px 8px 0 0;
            position: relative;
            transition: all 0.3s;
        }

        .bar-item:hover {
            opacity: 0.8;
            transform: translateY(-5px);
        }

        .bar-label {
            position: absolute;
            bottom: -25px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 0.8em;
            color: #6b7280;
            white-space: nowrap;
        }

        .bar-value {
            position: absolute;
            top: -25px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 0.85em;
            font-weight: 600;
            color: #1f2937;
        }

        .donut-chart {
            width: 200px;
            height: 200px;
            border-radius: 50%;
            background: conic-gradient(
                #3b82f6 0deg 120deg,
                #10b981 120deg 240deg,
                #f59e0b 240deg 300deg,
                #ef4444 300deg 360deg
            );
            position: relative;
            margin: 0 auto;
        }

        .donut-center {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 120px;
            height: 120px;
            background: white;
            border-radius: 50%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .donut-value {
            font-size: 2em;
            font-weight: 700;
            color: #1f2937;
        }

        .donut-label {
            font-size: 0.8em;
            color: #6b7280;
        }

        .legend {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-top: 20px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .legend-color {
            width: 16px;
            height: 16px;
            border-radius: 4px;
        }

        .legend-text {
            font-size: 0.85em;
            color: #4b5563;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-box {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-number {
            font-size: 2.5em;
            font-weight: 700;
            color: #3b82f6;
        }

        .stat-label {
            color: #6b7280;
            font-size: 0.9em;
            margin-top: 8px;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(5px);
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from { 
                opacity: 0;
                transform: translateY(30px);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 0;
            border-radius: 20px;
            max-width: 700px;
            width: 90%;
            max-height: 85vh;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: slideUp 0.3s ease;
            display: flex;
            flex-direction: column;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 25px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px 20px 0 0;
        }

        .modal-title {
            font-size: 1.5em;
            font-weight: 700;
            color: white;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .modal-title-icon {
            font-size: 1.3em;
        }

        .close-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            font-size: 1.5em;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            color: white;
        }

        .close-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 30px;
            overflow-y: auto;
            flex: 1;
        }

        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 15px;
        }

        .menu-item {
            background: linear-gradient(135deg, #f6f8fb 0%, #ffffff 100%);
            padding: 18px 20px;
            border-radius: 12px;
            border: 2px solid #e5e7eb;
            transition: all 0.3s ease;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: #1f2937;
            position: relative;
            overflow: hidden;
        }

        .menu-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transform: scaleY(0);
            transition: transform 0.3s ease;
        }

        .menu-item:hover {
            transform: translateX(8px);
            border-color: #667eea;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
        }

        .menu-item:hover::before {
            transform: scaleY(1);
        }

        .menu-item-icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3em;
            flex-shrink: 0;
        }

        .menu-item-text {
            font-weight: 600;
            font-size: 0.95em;
            flex: 1;
        }

        .menu-item-arrow {
            color: #9ca3af;
            font-size: 1.2em;
            transition: transform 0.3s ease;
        }

        .menu-item:hover .menu-item-arrow {
            transform: translateX(4px);
            color: #667eea;
        }

        .no-items {
            text-align: center;
            padding: 40px 20px;
            color: #6b7280;
        }

        .no-items i {
            font-size: 3em;
            color: #d1d5db;
            margin-bottom: 15px;
        }

        .modal-search {
            margin-bottom: 20px;
            position: relative;
        }

        .modal-search input {
            width: 100%;
            padding: 12px 45px 12px 15px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 0.95em;
            transition: all 0.3s ease;
        }

        .modal-search input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .modal-search i {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
        }

        .chart-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.9);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(10px);
        }

        .chart-modal.active {
            display: flex;
        }

        .chart-modal-content {
            background: white;
            padding: 30px;
            border-radius: 15px;
            width: 95%;
            height: 90vh;
            max-width: 1400px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
            display: flex;
            flex-direction: column;
        }

        .chart-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e5e7eb;
        }

        .chart-modal-title {
            font-size: 1.5em;
            font-weight: 600;
            color: #1f2937;
        }

        .chart-modal-body {
            flex: 1;
            position: relative;
            min-height: 0;
        }

        .dashboard-grid {
            display: grid !important;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)) !important;
            gap: 20px !important;
            margin-top: 30px !important;
            margin-bottom: 30px !important;
            padding: 0 !important;
        }

        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr !important;
            }

            .graphs-section {
                grid-template-columns: 1fr;
            }

            .stats-row {
                grid-template-columns: repeat(2, 1fr);
            }

            .top-header {
                flex-direction: column;
                gap: 15px;
            }
        }

        @media (min-width: 769px) and (max-width: 1024px) {
            .dashboard-grid {
                grid-template-columns: repeat(2, 1fr) !important;
            }
        }

        @media (min-width: 1025px) and (max-width: 1400px) {
            .dashboard-grid {
                grid-template-columns: repeat(3, 1fr) !important;
            }
        }

        @media (min-width: 1401px) {
            .dashboard-grid {
                grid-template-columns: repeat(4, 1fr) !important;
            }
        }
</style>
@endsection

@section('content')
@include('layouts.shared/page-title', ['sub_title' => 'Menu', 'page_title' => 'Dashboard'])

    <div class="row">
         <div class="col-xxl-3 col-sm-6">
        <a href="https://inventory.5coremanagement.com/channel/channels/channel-masters" target="_blank" style="text-decoration: none;">       
            <div class="card widget-flat text-bg-pink">
                <div class="card-body">
                    <div class="float-end">
                        <i class="ri-shopping-cart-line widget-icon"></i>
                    </div>
                    <h6 class="text-uppercase mt-0" title="Total Sales L30">L30 Total Sales</h6>
                    <h2 class="my-2" id="l30-sales-value">--</h2>
                    <p class="mb-0 text-dark small" style="font-weight: 600;">
                        <i class="ri-time-line"></i> Last 30 Days
                    </p>
                </div>
            </div>
            </a>
        </div> 
        <!-- end col-->

        <div class="col-xxl-3 col-sm-6">
            <a href="https://inventory.5coremanagement.com/channel/channels/channel-masters" target="_blank" style="text-decoration: none;">       
            <div class="card widget-flat text-bg-success">
                <div class="card-body">
                    <div class="float-end">
                        <i class="ri-percent-line widget-icon"></i>
                    </div>
                    <h6 class="text-uppercase mt-0" title="Profit Margin">Profit Margin %</h6>
                    <h2 class="my-2" id="profit-margin-value">--</h2>
                    <p class="mb-0 text-dark small" style="font-weight: 600;">
                        <i class="ri-calculator-line"></i> Calculated from L30
                    </p>
                </div>
            </div>
            </a>
        </div> <!-- end col-->

        <div class="col-xxl-3 col-sm-6">
            <a href="https://inventory.5coremanagement.com/channel/channels/channel-masters" target="_blank" style="text-decoration: none;">       
            <div class="card widget-flat text-bg-warning">
                <div class="card-body">
                    <div class="float-end">
                        <i class="ri-arrow-up-circle-line widget-icon"></i>
                    </div>
                    <h6 class="text-uppercase mt-0" title="Return on Investment">ROI %</h6>
                    <h2 class="my-2" id="roi-value">--</h2>
                    <p class="mb-0 text-dark small" style="font-weight: 600;">
                        <i class="ri-line-chart-line"></i> Return on COGS
                    </p>
                </div>
            </div>
            </a>
        </div> <!-- end col-->

        <div class="col-xxl-3 col-sm-6" style="display:none">
            <div class="card widget-flat text-bg-info">
                <div class="card-body">
                    <div class="float-end">
                        <i class="ri-currency-line widget-icon"></i>
                    </div>
                    <h6 class="text-uppercase mt-0" title="Total Profit">Total Profit $</h6>
                    <h2 class="my-2" id="total-profit-value">--</h2>
                    <p class="mb-0 text-dark small" style="font-weight: 600;">
                        <i class="ri-bank-card-line"></i> Absolute amount
                    </p>
                </div>
            </div>
        </div> 

    </div>

    <div class="row">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <div class="card-widgets">
                        <a href="https://inventory.5coremanagement.com/channel/channels/channel-masters" target="_blank" title="View Channel Masters"><i class="ri-eye-line"></i></a>
                        <a href="javascript:;" data-bs-toggle="reload"><i class="ri-refresh-line"></i></a>
                        <a data-bs-toggle="collapse" href="#weeklysales-collapse" role="button" aria-expanded="false"
                            aria-controls="weeklysales-collapse"><i class="ri-subtract-line"></i></a>
                        <a href="#" data-bs-toggle="remove"><i class="ri-close-line"></i></a>
                    </div>
                    <h5 class="header-title mb-0">Sales by Channel's</h5>

                    <div id="weeklysales-collapse" class="collapse pt-3 show">
                        <div dir="ltr" style="position: relative; height: 300px;">
                            <canvas id="channelSalesChart"></canvas>
                        </div>

                        <div class="row text-center mt-4">
                            <div class="col-12">
                                <p class="text-muted small"><strong>L30 Sales by Channel</strong></p>
                            </div>
                        </div>
                        
                        <div class="row mt-2">
                            <div class="col text-center">
                                <button type="button" class="btn btn-sm btn-primary" onclick="loadChannelSalesChart();">
                                    <i class="ri-refresh-line"></i> Refresh Channels
                                </button>
                                <button type="button" class="btn btn-sm btn-info ms-2" onclick="openChartModal();">
                                    <i class="ri-fullscreen-line"></i> View Fullscreen
                                </button>
                            </div>
                        </div>
                    </div>

                </div> <!-- end card-body-->
            </div> <!-- end card-->
        </div> <!-- end col-->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <div class="card-widgets">
                        <a href="javascript:;" data-bs-toggle="reload"><i class="ri-refresh-line"></i></a>
                        <a data-bs-toggle="collapse" href="#yearly-sales-collapse" role="button" aria-expanded="false"
                            aria-controls="yearly-sales-collapse"><i class="ri-subtract-line"></i></a>
                        <a href="#" data-bs-toggle="remove"><i class="ri-close-line"></i></a>
                    </div>
                    <h5 class="header-title mb-0">Revenue - Daily Sales</h5>

                    <div id="yearly-sales-collapse" class="collapse pt-3 show">
                        <div dir="ltr" style="position: relative; height: 300px;">
                            <canvas id="dailySalesChart"></canvas>
                        </div>
                        
                        <!-- Metrics Summary Below Chart -->
                        <div class="row text-center mt-4">
                            <div class="col-md-6">
                                <p class="text-muted mb-2"><small>L30 Sales</small></p>
                                <h5 class="mb-0" id="chart-l30-sales">--</h5>
                            </div>
                            <div class="col-md-6">
                                <p class="text-muted mb-2"><small>Profit Margin</small></p>
                                <h5 class="mb-0" id="chart-profit-margin">--</h5>
                            </div>
                        </div>
                        
                        <div class="row text-center mt-3">
                            <div class="col-md-6">
                                <p class="text-muted mb-2"><small>ROI</small></p>
                                <h5 class="mb-0" id="chart-roi">--</h5>
                            </div>
                            <div class="col-md-6">
                                <p class="text-muted mb-2"><small>Total Profit</small></p>
                                <h5 class="mb-0" id="chart-total-profit">--</h5>
                            </div>
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col text-center">
                                <button type="button" class="btn btn-sm btn-info" onclick="reloadDashboardMetrics();">
                                    <i class="ri-refresh-line"></i> Reload Metrics
                                </button>
                            </div>
                        </div>
                    </div>

                </div> <!-- end card-body-->
            </div> <!-- end card-->

           
        </div> <!-- end col-->

    </div>
    <!-- end row -->

    <!-- task dashboard -->
      <div class="dashboard-grid">
        <div class="dashboard-card" onclick="openModal('Tasks')">
            <div class="card-icon" style="background: #cffafe;">✓</div>
            <div class="card-header">
                <div>
                    <div class="card-title">Tasks</div>
                    <div class="card-description">Manage your tasks, assigned tasks, and track progress</div>
                </div>
                <span class="card-badge badge-cyan">31 Items</span>
            </div>
            <div class="subcards-preview">
                <span class="subcard-item">📋 My Tasks</span>
                <span class="subcard-item">👥 Team Tasks</span>
                <span class="subcard-item">✓ Completed</span>
            </div>
        </div>

        <div class="dashboard-card" onclick="openModal('My Team')">
            <div class="card-icon" style="background: #d1fae5;">👥</div>
            <div class="card-header">
                <div>
                    <div class="card-title">My Team</div>
                    <div class="card-description">View team members and performance metrics</div>
                </div>
                <span class="card-badge badge-green">1 Members</span>
            </div>
            <div class="subcards-preview">
                <span class="subcard-item">👤 Members</span>
                <span class="subcard-item">📊 Performance</span>
                <span class="subcard-item">🎯 Goals</span>
            </div>
        </div>

        <div class="dashboard-card" onclick="openModal('Inventory')">
            <div class="card-icon" style="background: #fed7aa;">📦</div>
            <div class="card-header">
                <div>
                    <div class="card-title">Inventory</div>
                    <div class="card-description">Inventory values</div>
                </div>
                <span class="card-badge badge-orange">1 Metrics</span>
            </div>
            <div class="subcards-preview">
                <span class="subcard-item">📈 Stock Levels</span>
                <span class="subcard-item">💰 Valuation</span>
            </div>
        </div>

        <!-- <div class="dashboard-card" onclick="openModal('Sales')">
            <div class="card-icon" style="background: #fef3c7;">💰</div>
            <div class="card-header">
                <div>
                    <div class="card-title">Sales</div>
                    <div class="card-description">Track sales performance</div>
                </div>
                <span class="card-badge badge-brown">286,435</span>
            </div>
            <div class="subcards-preview">
                <span class="subcard-item">🛒 E-Commerce</span>
                <span class="subcard-item">🛍️ Shopify</span>
                <span class="subcard-item">📱 Social Media</span>
                <span class="subcard-item">📦 Amazon</span>
            </div>
        </div> -->

        <div class="dashboard-card" onclick="openModal('Operations')">
            <div class="card-icon" style="background: #fce7f3;">⏰</div>
            <div class="card-header">
                <div>
                    <div class="card-title">Operations</div>
                    <div class="card-description">Track customer, Shipping & Reviews analyze</div>
                </div>
                <span class="card-badge badge-pink">3 Metrics</span>
            </div>
            <div class="subcards-preview">
                <span class="subcard-item">🚚 Shipping</span>
                <span class="subcard-item">⭐ Reviews</span>
                <span class="subcard-item">👥 Customers</span>
            </div>
        </div>

        <div class="dashboard-card" onclick="openModal('Human Resources')">
            <div class="card-icon" style="background: #e9d5ff;">👨‍💼</div>
            <div class="card-header">
                <div>
                    <div class="card-title">Human Resources</div>
                    <div class="card-description">Employee management & attendance tracking</div>
                </div>
                <span class="card-badge badge-purple">3 Metrics</span>
            </div>
            <div class="subcards-preview">
                <span class="subcard-item">👥 Employees</span>
                <span class="subcard-item">📅 Attendance</span>
                <span class="subcard-item">💼 Payroll</span>
            </div>
        </div>

        <div class="dashboard-card" onclick="openModal('Software & IT')">
            <div class="card-icon" style="background: #ccfbf1;">💻</div>
            <div class="card-header">
                <div>
                    <div class="card-title">Software & IT</div>
                    <div class="card-description">Generate reports and view analytics</div>
                </div>
                <span class="card-badge badge-teal">12 Items</span>
            </div>
            <div class="subcards-preview">
                <span class="subcard-item">🖥️ Systems</span>
                <span class="subcard-item">🔧 Maintenance</span>
                <span class="subcard-item">📊 Analytics</span>
            </div>
        </div>

        <div class="dashboard-card">
            <div class="card-actions">
                <button class="eye-icon-btn" onclick="openModal('Purchase'); event.stopPropagation();">
                    <i class="ri-eye-line"></i>
                </button>
            </div>
            <div class="card-icon" style="background: #1e3a5f; color: white;">🛒</div>
            <div class="card-header">
                <div>
                    <div class="card-title">Purchase</div>
                    <div class="card-description">Purchase management and analytics</div>
                </div>
                <span class="card-badge badge-indigo">3 Modules</span>
            </div>
            <div class="subcards-preview">
                <span class="subcard-item">📦 Categories</span>
                <span class="subcard-item">🏢 Suppliers</span>
                <span class="subcard-item">⚙️ MFRG</span>
            </div>
        </div>

        <div class="dashboard-card" onclick="openModal('Pricing')">
            <div class="card-icon" style="background: #fef3c7;">💵</div>
            <div class="card-header">
                <div>
                    <div class="card-title">Pricing</div>
                    <div class="card-description">Get Pricing reports and view analytics</div>
                </div>
                <span class="card-badge badge-yellow">79%</span>
            </div>
            <div class="subcards-preview">
                <span class="subcard-item">💰 Price Lists</span>
                <span class="subcard-item">📈 Trends</span>
            </div>
        </div>

        <div class="dashboard-card" onclick="openModal('Advertisements')">
            <div class="card-icon" style="background: #e5e7eb;">📢</div>
            <div class="card-header">
                <div>
                    <div class="card-title">Advertisements</div>
                    <div class="card-description">Get Advertisments reports and view analytics</div>
                </div>
                <span class="card-badge badge-gray">9 Metrics</span>
            </div>
            <div class="subcards-preview">
                <span class="subcard-item">📱 Digital Ads</span>
                <span class="subcard-item">📺 Campaigns</span>
                <span class="subcard-item">📊 ROI</span>
            </div>
        </div>

        <div class="dashboard-card" onclick="openModal('Content')">
            <div class="card-icon" style="background: #7c2d12; color: white;">📝</div>
            <div class="card-header">
                <div>
                    <div class="card-title">Content</div>
                    <div class="card-description">Get Content reports</div>
                </div>
                <span class="card-badge badge-red">0 Metrics</span>
            </div>
            <div class="subcards-preview">
                <span class="subcard-item">✍️ Articles</span>
                <span class="subcard-item">🎨 Media</span>
                <span class="subcard-item">📅 Schedule</span>
            </div>
        </div>

        <div class="dashboard-card" onclick="openModal('Marketing')">
            <div class="card-icon" style="background: #dbeafe;">🎯</div>
            <div class="card-header">
                <div>
                    <div class="card-title">Marketing</div>
                    <div class="card-description">Get Marketing analytics</div>
                </div>
                <span class="card-badge badge-blue">6 Metrics</span>
            </div>
            <div class="subcards-preview">
                <span class="subcard-item">📧 Email</span>
                <span class="subcard-item">🎯 Campaigns</span>
                <span class="subcard-item">📊 Analytics</span>
            </div>
        </div>

        <div class="dashboard-card" onclick="openModal('Social Media')">
            <div class="card-icon" style="background: #fef3c7;">📱</div>
            <div class="card-header">
                <div>
                    <div class="card-title">Social Media</div>
                    <div class="card-description">Get Social Media analytics</div>
                </div>
                <span class="card-badge badge-yellow">0 Metrics</span>
            </div>
            <div class="subcards-preview">
                <span class="subcard-item">📘 Facebook</span>
                <span class="subcard-item">📷 Instagram</span>
                <span class="subcard-item">🐦 Twitter</span>
            </div>
        </div>

        <div class="dashboard-card" onclick="openModal('Videos')">
            <div class="card-icon" style="background: #fed7aa;">🎬</div>
            <div class="card-header">
                <div>
                    <div class="card-title">Videos</div>
                    <div class="card-description">Get Videos details</div>
                </div>
                <span class="card-badge badge-orange">0 Metrics</span>
            </div>
            <div class="subcards-preview">
                <span class="subcard-item">🎥 Library</span>
                <span class="subcard-item">▶️ Views</span>
                <span class="subcard-item">👍 Engagement</span>
            </div>
        </div>

        <div class="dashboard-card" onclick="openModal('Logistics')">
            <div class="card-icon" style="background: #1e3a5f; color: white;">🚚</div>
            <div class="card-header">
                <div>
                    <div class="card-title">Logistics</div>
                    <div class="card-description">Get Logistics Track Reports</div>
                </div>
                <span class="card-badge badge-indigo">0 Metrics</span>
            </div>
            <div class="subcards-preview">
                <span class="subcard-item">📦 Shipments</span>
                <span class="subcard-item">🚛 Tracking</span>
                <span class="subcard-item">📍 Delivery</span>
            </div>
        </div>
    </div>
    
    <!-- end row -->

    <!-- Menu Modal -->
    <div class="modal" id="menuModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">
                    <span class="modal-title-icon" id="modalIcon">📦</span>
                    <span id="modalCategory">Menu</span>
                </div>
                <button class="close-btn" onclick="closeMenuModal()">
                    <i class="ri-close-line"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="modal-search">
                    <input type="text" id="menuSearch" placeholder="Search menu items..." onkeyup="filterMenuItems()">
                    <i class="ri-search-line"></i>
                </div>
                <div class="menu-grid" id="menuGrid">
                    <!-- Menu items will be dynamically loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Channel Sales Fullscreen Modal -->
    <div class="chart-modal" id="chartModal">
        <div class="chart-modal-content">
            <div class="chart-modal-header">
                <h3 class="chart-modal-title">Sales by Channel's - Fullscreen View</h3>
                <button class="close-btn" onclick="closeChartModal();">
                    <i class="ri-close-line"></i>
                </button>
            </div>
            <div class="chart-modal-body">
                <canvas id="channelSalesChartModal"></canvas>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        // ============================================
        // DASHBOARD METRICS - Complete Fresh Implementation
        // ============================================
        
        let dailySalesChartInstance = null;
        
        // Function to create line graph with sales trend data
        function createSalesLineChart() {
            console.log('[Dashboard] Fetching sales trend data...');
            
            fetch('/sales-trend-data')
                .then(response => {
                    console.log('[Dashboard] Trend data response status:', response.status);
                    return response.json();
                })
                .then(result => {
                    console.log('[Dashboard] Trend data received:', result);
                    
                    if (!result.chartData || result.chartData.length === 0) {
                        console.warn('[Dashboard] No chart data available');
                        return;
                    }
                    
                    // Get only last 30 days of data
                    const chartData = result.chartData.slice(-30);
                    console.log('[Dashboard] Chart data - Total records: ' + result.chartData.length + ', Showing last 30 days: ' + chartData.length);
                    
                    const dates = [];
                    const l30Sales = [];
                    
                    // Extract dates and L30 sales from the data
                    chartData.forEach(row => {
                        dates.push(row.date);
                        l30Sales.push(parseFloat(row.l30_sales) || 0);
                    });
                    
                    console.log('[Dashboard] Chart data extracted - dates:', dates.length, 'records');
                    
                    // Destroy existing chart if it exists
                    if (dailySalesChartInstance) {
                        dailySalesChartInstance.destroy();
                    }
                    
                    // Create line chart
                    const ctx = document.getElementById('dailySalesChart');
                    if (!ctx) {
                        console.warn('[Dashboard] Chart canvas not found');
                        return;
                    }
                    
                    dailySalesChartInstance = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: dates,
                            datasets: [
                                {
                                    label: 'L30 Daily Sales',
                                    data: l30Sales,
                                    borderColor: 'rgba(54, 162, 235, 1)',
                                    backgroundColor: 'rgba(54, 162, 235, 0.1)',
                                    borderWidth: 3,
                                    pointRadius: 4,
                                    pointBackgroundColor: 'rgba(54, 162, 235, 1)',
                                    pointBorderColor: '#fff',
                                    pointBorderWidth: 2,
                                    tension: 0.4,
                                    fill: true
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: {
                                mode: 'index',
                                intersect: false
                            },
                            plugins: {
                                legend: {
                                    display: true,
                                    position: 'top',
                                    labels: {
                                        font: { size: 13, weight: 'bold' },
                                        padding: 15,
                                        usePointStyle: true
                                    }
                                },
                                tooltip: {
                                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                    padding: 12,
                                    titleFont: { size: 12, weight: 'bold' },
                                    bodyFont: { size: 11 },
                                    callbacks: {
                                        label: function(context) {
                                            return 'Sales: $' + context.parsed.y.toLocaleString('en-US', { maximumFractionDigits: 0 });
                                        }
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        callback: function(value) {
                                            return '$' + value.toLocaleString('en-US', { maximumFractionDigits: 0 });
                                        },
                                        font: { size: 11 }
                                    },
                                    grid: {
                                        color: 'rgba(0, 0, 0, 0.05)',
                                        drawBorder: false
                                    },
                                    title: {
                                        display: true,
                                        text: 'Sales ($)',
                                        font: { size: 12, weight: 'bold' }
                                    }
                                },
                                x: {
                                    ticks: {
                                        font: { size: 10 },
                                        maxRotation: 45,
                                        minRotation: 0
                                    },
                                    grid: {
                                        display: false
                                    },
                                    title: {
                                        display: true,
                                        text: 'Date',
                                        font: { size: 12, weight: 'bold' }
                                    }
                                }
                            }
                        }
                    });
                    
                    console.log('[Dashboard] ✅ Line chart created successfully');
                })
                .catch(error => {
                    console.error('[Dashboard] ❌ Error fetching chart data:', error);
                });
        }
        
        // Function to create bar graph for channel sales comparison
        function loadChannelSalesChart() {
            console.log('[Dashboard] Fetching channel sales data...');
            
            fetch('/channels-master-data')
                .then(response => {
                    console.log('[Dashboard] Channel data response status:', response.status);
                    return response.json();
                })
                .then(result => {
                    console.log('[Dashboard] Channel data received:', result);
                    
                    if (!result.data || result.data.length === 0) {
                        console.warn('[Dashboard] No channel data available');
                        return;
                    }
                    
                    const channelData = [];
                    
                    // Extract channel names and L30 sales from the data
                    result.data.forEach(row => {
                        // The API returns 'Channel ' with a trailing space (check ChannelMasterController line 162)
                        const channelName = row['Channel '] || row['Channel'] || row.channel || row.name || row.Name || 'Unknown';
                        const sales = parseFloat(row['L30 Sales'] || row.l30_sales || row.L30Sales || 0);
                        
                        console.log('[Dashboard] Channel:', channelName, 'Sales:', sales);
                        
                        channelData.push({ name: channelName, sales: sales });
                    });
                    
                    // Sort by sales (highest to lowest)
                    channelData.sort((a, b) => b.sales - a.sales);
                    
                    // Separate into arrays for Chart.js
                    const channels = channelData.map(item => item.name);
                    const l30Sales = channelData.map(item => item.sales);
                    
                    console.log('[Dashboard] Channels extracted and sorted:', channels.length, 'channels');
                    
                    // Destroy existing chart if it exists
                    if (window.channelSalesChartInstance) {
                        window.channelSalesChartInstance.destroy();
                    }
                    
                    // Create bar chart for channel comparison
                    const ctx = document.getElementById('channelSalesChart');
                    if (!ctx) {
                        console.warn('[Dashboard] Channel chart canvas not found');
                        return;
                    }
                    
                    window.channelSalesChartInstance = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: channels,
                            datasets: [
                                {
                                    label: 'L30 Sales ($)',
                                    data: l30Sales,
                                    backgroundColor: [
                                        'rgba(54, 162, 235, 0.7)',
                                        'rgba(75, 192, 75, 0.7)',
                                        'rgba(255, 193, 7, 0.7)',
                                        'rgba(255, 87, 34, 0.7)',
                                        'rgba(156, 39, 176, 0.7)',
                                        'rgba(233, 30, 99, 0.7)',
                                        'rgba(0, 188, 212, 0.7)',
                                        'rgba(76, 175, 80, 0.7)',
                                        'rgba(255, 152, 0, 0.7)',
                                        'rgba(63, 81, 181, 0.7)'
                                    ],
                                    borderColor: [
                                        'rgba(54, 162, 235, 1)',
                                        'rgba(75, 192, 75, 1)',
                                        'rgba(255, 193, 7, 1)',
                                        'rgba(255, 87, 34, 1)',
                                        'rgba(156, 39, 176, 1)',
                                        'rgba(233, 30, 99, 1)',
                                        'rgba(0, 188, 212, 1)',
                                        'rgba(76, 175, 80, 1)',
                                        'rgba(255, 152, 0, 1)',
                                        'rgba(63, 81, 181, 1)'
                                    ],
                                    borderWidth: 2,
                                    borderRadius: 4
                                }
                            ]
                        },
                        options: {
                            indexAxis: 'y',
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: true,
                                    position: 'top',
                                    labels: {
                                        font: { size: 12, weight: 'bold' },
                                        padding: 15
                                    }
                                },
                                tooltip: {
                                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                    padding: 12,
                                    titleFont: { size: 12, weight: 'bold' },
                                    bodyFont: { size: 11 },
                                    callbacks: {
                                        label: function(context) {
                                            return 'Sales: $' + context.parsed.x.toLocaleString('en-US', { maximumFractionDigits: 0 });
                                        }
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    beginAtZero: true,
                                    ticks: {
                                        callback: function(value) {
                                            return '$' + value.toLocaleString('en-US', { maximumFractionDigits: 0 });
                                        },
                                        font: { size: 10 }
                                    },
                                    grid: {
                                        color: 'rgba(0, 0, 0, 0.05)',
                                        drawBorder: false
                                    }
                                },
                                y: {
                                    ticks: {
                                        font: { size: 11 },
                                        autoSkip: false,
                                        maxRotation: 0,
                                        minRotation: 0
                                    },
                                    grid: {
                                        display: false
                                    }
                                }
                            }
                        }
                    });
                    
                    console.log('[Dashboard] ✅ Channel sales chart created successfully');
                })
                .catch(error => {
                    console.error('[Dashboard] ❌ Error fetching channel data:', error);
                });
        }
        // ============================================
        // DASHBOARD METRICS - Complete Fresh Implementation
        // ============================================
        
        // Function to fetch and display dashboard metrics
        function loadDashboardMetrics() {
            console.log('[Dashboard] Fetching metrics...');
            
            // Show loading state
            document.getElementById('l30-sales-value').textContent = 'Loading...';
            document.getElementById('profit-margin-value').textContent = 'Loading...';
            document.getElementById('roi-value').textContent = 'Loading...';
            document.getElementById('total-profit-value').textContent = 'Loading...';
            
            // Fetch the API
            fetch('/dashboard-metrics')
                .then(response => {
                    console.log('[Dashboard] Response status:', response.status);
                    if (!response.ok) throw new Error('HTTP ' + response.status);
                    return response.json();
                })
                .then(result => {
                    console.log('[Dashboard] API Response:', result);
                    
                    if (result.status === 200 && result.data) {
                        const data = result.data;
                        console.log('[Dashboard] ✅ Data received:', data);
                        
                        // Format and display L30 Sales - FULL AMOUNT (no 'k' formatting, no .00)
                        const salesValue = parseFloat(data.total_sales);
                        const salesDisplay = '$' + salesValue.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
                        document.getElementById('l30-sales-value').textContent = salesDisplay;
                        console.log('[Dashboard] ✅ L30 Sales updated:', salesDisplay);
                        
                        // Format and display Profit Margin
                        const marginValue = parseFloat(data.profit_margin);
                        const marginDisplay = marginValue.toFixed(1) + '%';
                        document.getElementById('profit-margin-value').textContent = marginDisplay;
                        console.log('[Dashboard] ✅ Profit Margin updated:', marginDisplay);
                        
                        // Format and display ROI
                        const roiValue = parseFloat(data.roi);
                        const roiDisplay = roiValue.toFixed(1) + '%';
                        document.getElementById('roi-value').textContent = roiDisplay;
                        console.log('[Dashboard] ✅ ROI updated:', roiDisplay);
                        
                        // Format and display Total Profit
                        const profitValue = parseFloat(data.total_profit);
                        let profitDisplay;
                        if (profitValue >= 1000) {
                            profitDisplay = '$' + (profitValue / 1000).toFixed(1) + 'k';
                        } else {
                            profitDisplay = '$' + profitValue.toLocaleString('en-US');
                        }
                        document.getElementById('total-profit-value').textContent = profitDisplay;
                        console.log('[Dashboard] ✅ Total Profit updated:', profitDisplay);
                        
                        // Also populate the chart section metrics (if they exist)
                        const chartSalesEl = document.getElementById('chart-l30-sales');
                        const chartMarginEl = document.getElementById('chart-profit-margin');
                        const chartRoiEl = document.getElementById('chart-roi');
                        const chartProfitEl = document.getElementById('chart-total-profit');
                        
                        if (chartSalesEl) chartSalesEl.textContent = '$' + salesValue.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
                        if (chartMarginEl) chartMarginEl.textContent = marginDisplay;
                        if (chartRoiEl) chartRoiEl.textContent = roiDisplay;
                        if (chartProfitEl) chartProfitEl.textContent = profitDisplay;
                        
                        // Create the line chart showing daily sales trend
                        createSalesLineChart();
                        
                        // Log summary
                        console.log('[Dashboard] 📊 Summary:', {
                            channels: data.channels_count,
                            period: data.period,
                            data_found: data.data_found,
                            l30_sales: '$' + salesValue.toLocaleString('en-US'),
                            profit_margin: marginValue.toFixed(2) + '%',
                            roi: roiValue.toFixed(2) + '%',
                            total_profit: '$' + profitValue.toLocaleString('en-US')
                        });
                        
                        console.log('[Dashboard] ✅ All metrics loaded successfully!');
                    } else {
                        console.error('[Dashboard] ❌ Invalid response format:', result);
                        showErrorState();
                    }
                })
                .catch(error => {
                    console.error('[Dashboard] ❌ Error:', error);
                    showErrorState();
                });
        }
        
        // Show error state on all cards
        function showErrorState() {
            document.getElementById('l30-sales-value').textContent = 'Error';
            document.getElementById('profit-margin-value').textContent = 'Error';
            document.getElementById('roi-value').textContent = 'Error';
            document.getElementById('total-profit-value').textContent = 'Error';
        }
        
        // Load metrics when page is ready
        document.addEventListener('DOMContentLoaded', function() {
            console.log('[Dashboard] DOM Content Loaded - Starting initialization');
            
            // Verify elements exist
            const salesEl = document.getElementById('l30-sales-value');
            const marginEl = document.getElementById('profit-margin-value');
            const roiEl = document.getElementById('roi-value');
            const profitEl = document.getElementById('total-profit-value');
            
            console.log('[Dashboard] Element verification:');
            console.log('  - L30 Sales element:', !!salesEl);
            console.log('  - Profit Margin element:', !!marginEl);
            console.log('  - ROI element:', !!roiEl);
            console.log('  - Total Profit element:', !!profitEl);
            
            if (salesEl && marginEl && roiEl && profitEl) {
                console.log('[Dashboard] ✅ All elements found, loading metrics...');
                loadDashboardMetrics();
            } else {
                console.error('[Dashboard] ❌ Some elements not found!');
            }
            
            // Load channel sales chart
            loadChannelSalesChart();
        });
        
        // Expose function globally for manual reload button
        window.reloadDashboardMetrics = function() {
            console.log('[Dashboard] Manual reload requested');
            loadDashboardMetrics();
        };

        // Fullscreen chart modal functions
        let modalChartInstance = null;

        window.openChartModal = function() {
            const modal = document.getElementById('chartModal');
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
            
            // Load chart data in modal
            loadChannelSalesChartModal();
        };

        window.closeChartModal = function() {
            const modal = document.getElementById('chartModal');
            modal.classList.remove('active');
            document.body.style.overflow = '';
            
            // Destroy modal chart instance
            if (modalChartInstance) {
                modalChartInstance.destroy();
                modalChartInstance = null;
            }
        };

        function loadChannelSalesChartModal() {
            console.log('[Dashboard] Loading fullscreen channel chart...');
            
            fetch('/channels-master-data')
                .then(response => response.json())
                .then(result => {
                    if (!result.data || result.data.length === 0) {
                        console.warn('[Dashboard] No channel data available');
                        return;
                    }
                    
                    const channelData = [];
                    
                    result.data.forEach(row => {
                        const channelName = row['Channel '] || row['Channel'] || row.channel || row.name || row.Name || 'Unknown';
                        const sales = parseFloat(row['L30 Sales'] || row.l30_sales || row.L30Sales || 0);
                        channelData.push({ name: channelName, sales: sales });
                    });
                    
                    // Sort by sales (highest to lowest)
                    channelData.sort((a, b) => b.sales - a.sales);
                    
                    const channels = channelData.map(item => item.name);
                    const l30Sales = channelData.map(item => item.sales);
                    
                    // Destroy existing modal chart
                    if (modalChartInstance) {
                        modalChartInstance.destroy();
                    }
                    
                    const ctx = document.getElementById('channelSalesChartModal');
                    if (!ctx) return;
                    
                    modalChartInstance = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: channels,
                            datasets: [{
                                label: 'L30 Sales ($)',
                                data: l30Sales,
                                backgroundColor: [
                                    'rgba(54, 162, 235, 0.7)',
                                    'rgba(75, 192, 75, 0.7)',
                                    'rgba(255, 193, 7, 0.7)',
                                    'rgba(255, 87, 34, 0.7)',
                                    'rgba(156, 39, 176, 0.7)',
                                    'rgba(233, 30, 99, 0.7)',
                                    'rgba(0, 188, 212, 0.7)',
                                    'rgba(76, 175, 80, 0.7)',
                                    'rgba(255, 152, 0, 0.7)',
                                    'rgba(63, 81, 181, 0.7)'
                                ],
                                borderColor: [
                                    'rgba(54, 162, 235, 1)',
                                    'rgba(75, 192, 75, 1)',
                                    'rgba(255, 193, 7, 1)',
                                    'rgba(255, 87, 34, 1)',
                                    'rgba(156, 39, 176, 1)',
                                    'rgba(233, 30, 99, 1)',
                                    'rgba(0, 188, 212, 1)',
                                    'rgba(76, 175, 80, 1)',
                                    'rgba(255, 152, 0, 1)',
                                    'rgba(63, 81, 181, 1)'
                                ],
                                borderWidth: 2,
                                borderRadius: 6
                            }]
                        },
                        options: {
                            indexAxis: 'y',
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: true,
                                    position: 'top',
                                    labels: {
                                        font: { size: 14, weight: 'bold' },
                                        padding: 20
                                    }
                                },
                                tooltip: {
                                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                    padding: 15,
                                    titleFont: { size: 14, weight: 'bold' },
                                    bodyFont: { size: 13 },
                                    callbacks: {
                                        label: function(context) {
                                            return 'Sales: $' + context.parsed.x.toLocaleString('en-US', { maximumFractionDigits: 0 });
                                        }
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    beginAtZero: true,
                                    ticks: {
                                        callback: function(value) {
                                            return '$' + value.toLocaleString('en-US', { maximumFractionDigits: 0 });
                                        },
                                        font: { size: 12 }
                                    },
                                    grid: {
                                        color: 'rgba(0, 0, 0, 0.05)',
                                        drawBorder: false
                                    }
                                },
                                y: {
                                    ticks: {
                                        font: { size: 13, weight: '500' },
                                        autoSkip: false,
                                        maxRotation: 0,
                                        minRotation: 0
                                    },
                                    grid: {
                                        display: false
                                    }
                                }
                            }
                        }
                    });
                    
                    console.log('[Dashboard] ✅ Fullscreen chart created');
                })
                .catch(error => {
                    console.error('[Dashboard] ❌ Error loading fullscreen chart:', error);
                });
        }

        // Close modal on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeChartModal();
                closeMenuModal();
            }
        });

        // ============================================
        // MENU MODAL SYSTEM
        // ============================================

        // Menu data structure with routes
        const menuData = {
            'Purchase': {
                icon: '🛒',
                color: '#1e3a5f',
                items: [
                    { name: 'Categories', icon: '📦', route: '/purchase-masters/categories' },
                    { name: 'Suppliers', icon: '🏢', route: '/purchase-masters/suppliers' },
                    { name: 'MFRG In Progress', icon: '⚙️', route: '/purchase-masters/mfrg-in-progress' }
                ]
            },
            'Tasks': {
                icon: '✓',
                color: '#0891b2',
                items: [
                    { name: 'My Tasks', icon: '📋', route: '/tasks/my-tasks' },
                    { name: 'Team Tasks', icon: '👥', route: '/tasks/team-tasks' },
                    { name: 'Completed Tasks', icon: '✅', route: '/tasks/completed' }
                ]
            },
            'My Team': {
                icon: '👥',
                color: '#059669',
                items: [
                    { name: 'Team Members', icon: '👤', route: '/team/members' },
                    { name: 'Performance', icon: '📊', route: '/team/performance' },
                    { name: 'Goals & Targets', icon: '🎯', route: '/team/goals' }
                ]
            },
            'Inventory': {
                icon: '📦',
                color: '#ea580c',
                items: [
                    { name: 'Stock Levels', icon: '📈', route: '/inventory/stock-levels' },
                    { name: 'Valuation', icon: '💰', route: '/inventory/valuation' }
                ]
            },
            'Operations': {
                icon: '⏰',
                color: '#db2777',
                items: [
                    { name: 'Shipping Analysis', icon: '🚚', route: '/operations/shipping' },
                    { name: 'Reviews Management', icon: '⭐', route: '/operations/reviews' },
                    { name: 'Customer Care', icon: '👥', route: '/operations/customer-care' }
                ]
            },
            'Human Resources': {
                icon: '👨‍💼',
                color: '#9333ea',
                items: [
                    { name: 'Employee Directory', icon: '👥', route: '/hr/employees' },
                    { name: 'Attendance Tracking', icon: '📅', route: '/hr/attendance' },
                    { name: 'Payroll Management', icon: '💼', route: '/hr/payroll' }
                ]
            },
            'Software & IT': {
                icon: '💻',
                color: '#0d9488',
                items: [
                    { name: 'System Management', icon: '🖥️', route: '/it/systems' },
                    { name: 'Maintenance', icon: '🔧', route: '/it/maintenance' },
                    { name: 'Analytics Dashboard', icon: '📊', route: '/it/analytics' }
                ]
            },
            'Pricing': {
                icon: '💵',
                color: '#d97706',
                items: [
                    { name: 'Price Lists', icon: '💰', route: '/pricing/lists' },
                    { name: 'Pricing Trends', icon: '📈', route: '/pricing/trends' }
                ]
            },
            'Advertisements': {
                icon: '📢',
                color: '#4b5563',
                items: [
                    { name: 'Digital Ads', icon: '📱', route: '/ads/digital' },
                    { name: 'Campaigns', icon: '📺', route: '/ads/campaigns' },
                    { name: 'ROI Analysis', icon: '📊', route: '/ads/roi' }
                ]
            },
            'Content': {
                icon: '📝',
                color: '#dc2626',
                items: [
                    { name: 'Articles', icon: '✍️', route: '/content/articles' },
                    { name: 'Media Library', icon: '🎨', route: '/content/media' },
                    { name: 'Content Schedule', icon: '📅', route: '/content/schedule' }
                ]
            },
            'Marketing': {
                icon: '🎯',
                color: '#2563eb',
                items: [
                    { name: 'Email Marketing', icon: '📧', route: '/marketing/email' },
                    { name: 'Campaigns', icon: '🎯', route: '/marketing/campaigns' },
                    { name: 'Analytics', icon: '📊', route: '/marketing/analytics' }
                ]
            },
            'Social Media': {
                icon: '📱',
                color: '#d97706',
                items: [
                    { name: 'Facebook', icon: '📘', route: '/social/facebook' },
                    { name: 'Instagram', icon: '📷', route: '/social/instagram' },
                    { name: 'Twitter', icon: '🐦', route: '/social/twitter' }
                ]
            },
            'Videos': {
                icon: '🎬',
                color: '#ea580c',
                items: [
                    { name: 'Video Library', icon: '🎥', route: '/videos/library' },
                    { name: 'Views Analytics', icon: '▶️', route: '/videos/analytics' },
                    { name: 'Engagement', icon: '👍', route: '/videos/engagement' }
                ]
            },
            'Logistics': {
                icon: '🚚',
                color: '#4f46e5',
                items: [
                    { name: 'Shipments', icon: '📦', route: '/logistics/shipments' },
                    { name: 'Tracking', icon: '🚛', route: '/logistics/tracking' },
                    { name: 'Delivery Status', icon: '📍', route: '/logistics/delivery' }
                ]
            }
        };

        // Open menu modal
        function openModal(category) {
            const modal = document.getElementById('menuModal');
            const modalIcon = document.getElementById('modalIcon');
            const modalCategory = document.getElementById('modalCategory');
            const menuGrid = document.getElementById('menuGrid');
            const menuSearch = document.getElementById('menuSearch');

            // Set modal title and icon
            const categoryData = menuData[category];
            if (categoryData) {
                modalIcon.textContent = categoryData.icon;
                modalCategory.textContent = category;
                
                // Clear search
                menuSearch.value = '';
                
                // Generate menu items
                let html = '';
                if (categoryData.items && categoryData.items.length > 0) {
                    categoryData.items.forEach(item => {
                        html += `
                            <a href="${item.route}" class="menu-item">
                                <div class="menu-item-icon">${item.icon}</div>
                                <div class="menu-item-text">${item.name}</div>
                                <i class="ri-arrow-right-line menu-item-arrow"></i>
                            </a>
                        `;
                    });
                } else {
                    html = `
                        <div class="no-items">
                            <i class="ri-inbox-line"></i>
                            <p>No items available in this category</p>
                        </div>
                    `;
                }
                
                menuGrid.innerHTML = html;
            }
            
            // Show modal
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        // Close menu modal
        function closeMenuModal() {
            const modal = document.getElementById('menuModal');
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }

        // Filter menu items
        function filterMenuItems() {
            const searchInput = document.getElementById('menuSearch');
            const filter = searchInput.value.toLowerCase();
            const menuItems = document.querySelectorAll('.menu-item');
            
            menuItems.forEach(item => {
                const text = item.querySelector('.menu-item-text').textContent.toLowerCase();
                if (text.includes(filter)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        // Close modal when clicking outside
        document.addEventListener('click', function(e) {
            const modal = document.getElementById('menuModal');
            if (e.target === modal) {
                closeMenuModal();
            }
        });
    </script>
@endsection

