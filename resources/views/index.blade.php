@extends('layouts.vertical', ['title' => 'Dashboard', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
@include('layouts.shared/page-title', ['sub_title' => 'Menu', 'page_title' => 'Dashboard'])

    <div class="row">
        <div class="col-xxl-3 col-sm-6">
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
        </div> <!-- end col-->

        <div class="col-xxl-3 col-sm-6">
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
        </div> <!-- end col-->

        <div class="col-xxl-3 col-sm-6">
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

            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1 overflow-hidden">
                            <h4 class="fs-22 fw-semibold">69.25%</h4>
                            <p class="text-uppercase fw-medium text-muted text-truncate mb-0"> US Dollar Share</p>
                        </div>
                        <div class="flex-shrink-0">
                            <div id="us-share-chart" class="apex-charts" dir="ltr"></div>
                        </div>
                    </div>
                </div><!-- end card body -->
            </div> <!-- end card-->
        </div> <!-- end col-->

    </div>
    <!-- end row -->

    <div class="row">
        <div class="col-xl-4">
            <!-- Chat-->
            <div class="card">
                <div class="card-body p-0">
                    <div class="p-3">
                        <div class="card-widgets">
                            <a href="javascript:;" data-bs-toggle="reload"><i class="ri-refresh-line"></i></a>
                            <a data-bs-toggle="collapse" href="#yearly-sales-collapse" role="button"
                                aria-expanded="false" aria-controls="yearly-sales-collapse"><i
                                    class="ri-subtract-line"></i></a>
                            <a href="#" data-bs-toggle="remove"><i class="ri-close-line"></i></a>
                        </div>
                        <h5 class="header-title mb-0">Chat</h5>
                    </div>

                    <div id="yearly-sales-collapse" class="collapse show">
                        <div class="chat-conversation mt-2">
                            <div class="card-body py-0 mb-3" data-simplebar style="height: 322px;">
                                <ul class="conversation-list">
                                    <li class="clearfix">
                                        <div class="chat-avatar">
                                            <img src="/images/users/avatar-5.jpg" alt="male">
                                            <i>10:00</i>
                                        </div>
                                        <div class="conversation-text">
                                            <div class="ctext-wrap">
                                                <i>Geneva</i>
                                                <p>
                                                    Hello!
                                                </p>
                                            </div>
                                        </div>
                                    </li>
                                    <li class="clearfix odd">
                                        <div class="chat-avatar">
                                            <img src="/images/users/avatar-1.jpg" alt="Female">
                                            <i>10:01</i>
                                        </div>
                                        <div class="conversation-text">
                                            <div class="ctext-wrap">
                                                <i>Thomson</i>
                                                <p>
                                                    Hi, How are you? What about our next meeting?
                                                </p>
                                            </div>
                                        </div>
                                    </li>
                                    <li class="clearfix">
                                        <div class="chat-avatar">
                                            <img src="/images/users/avatar-5.jpg" alt="male">
                                            <i>10:01</i>
                                        </div>
                                        <div class="conversation-text">
                                            <div class="ctext-wrap">
                                                <i>Geneva</i>
                                                <p>
                                                    Yeah everything is fine
                                                </p>
                                            </div>
                                        </div>
                                    </li>
                                    <li class="clearfix odd">
                                        <div class="chat-avatar">
                                            <img src="/images/users/avatar-1.jpg" alt="male">
                                            <i>10:02</i>
                                        </div>
                                        <div class="conversation-text">
                                            <div class="ctext-wrap">
                                                <i>Thomson</i>
                                                <p>
                                                    Wow that's great
                                                </p>
                                            </div>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                            <div class="card-body pt-0">
                                <form class="needs-validation" novalidate name="chat-form" id="chat-form">
                                    <div class="row align-items-start">
                                        <div class="col">
                                            <input type="text" class="form-control chat-input"
                                                placeholder="Enter your text" required>
                                            <div class="invalid-feedback">
                                                Please enter your messsage
                                            </div>
                                        </div>
                                        <div class="col-auto d-grid">
                                            <button type="submit"
                                                class="btn btn-danger chat-send waves-effect waves-light">Send</button>
                                        </div>
                                    </div>
                                </form>
                            </div>

                        </div> <!-- end .chat-conversation-->
                    </div>
                </div>

            </div> <!-- end card-->
        </div> <!-- end col-->

        <div class="col-xl-8">
            <!-- Todo-->
            <div class="card">
                <div class="card-body p-0">
                    <div class="p-3">
                        <div class="card-widgets">
                            <a href="javascript:;" data-bs-toggle="reload"><i class="ri-refresh-line"></i></a>
                            <a data-bs-toggle="collapse" href="#yearly-sales-collapse" role="button"
                                aria-expanded="false" aria-controls="yearly-sales-collapse"><i
                                    class="ri-subtract-line"></i></a>
                            <a href="#" data-bs-toggle="remove"><i class="ri-close-line"></i></a>
                        </div>
                        <h5 class="header-title mb-0">Projects</h5>
                    </div>

                    <div id="yearly-sales-collapse" class="collapse show">

                        <div class="table-responsive">
                            <table class="table table-nowrap table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Project Name</th>
                                        <th>Start Date</th>
                                        <th>Due Date</th>
                                        <th>Status</th>
                                        <th>Assign</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>1</td>
                                        <td>Velonic Admin v1</td>
                                        <td>01/01/2015</td>
                                        <td>26/04/2015</td>
                                        <td><span class="badge bg-info-subtle text-info">Released</span></td>
                                        <td>Techzaa Studio</td>
                                    </tr>
                                    <tr>
                                        <td>2</td>
                                        <td>Velonic Frontend v1</td>
                                        <td>01/01/2015</td>
                                        <td>26/04/2015</td>
                                        <td><span class="badge bg-info-subtle text-info">Released</span></td>
                                        <td>Techzaa Studio</td>
                                    </tr>
                                    <tr>
                                        <td>3</td>
                                        <td>Velonic Admin v1.1</td>
                                        <td>01/05/2015</td>
                                        <td>10/05/2015</td>
                                        <td><span class="badge bg-pink-subtle text-pink">Pending</span></td>
                                        <td>Techzaa Studio</td>
                                    </tr>
                                    <tr>
                                        <td>4</td>
                                        <td>Velonic Frontend v1.1</td>
                                        <td>01/01/2015</td>
                                        <td>31/05/2015</td>
                                        <td><span class="badge bg-purple-subtle text-purple">Work in Progress</span></td>
                                        <td>Techzaa Studio</td>
                                    </tr>
                                    <tr>
                                        <td>5</td>
                                        <td>Velonic Admin v1.3</td>
                                        <td>01/01/2015</td>
                                        <td>31/05/2015</td>
                                        <td><span class="badge bg-warning-subtle text-warning">Coming soon</span></td>
                                        <td>Techzaa Studio</td>
                                    </tr>

                                    <tr>
                                        <td>6</td>
                                        <td>Velonic Admin v1.3</td>
                                        <td>01/01/2015</td>
                                        <td>31/05/2015</td>
                                        <td><span class="badge bg-primary-subtle text-primary">Coming soon</span></td>
                                        <td>Techzaa Studio</td>
                                    </tr>

                                    <tr>
                                        <td>7</td>
                                        <td>Velonic Admin v1.3</td>
                                        <td>01/01/2015</td>
                                        <td>31/05/2015</td>
                                        <td><span class="badge bg-danger-subtle text-danger">Cool</span></td>
                                        <td>Techzaa Studio</td>
                                    </tr>

                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div> <!-- end card-->
        </div> <!-- end col-->
    </div>
    <!-- end row -->
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
                    
                    const channels = [];
                    const l30Sales = [];
                    
                    // Extract channel names and L30 sales from the data
                    result.data.forEach(row => {
                        // The API returns 'Channel ' with a trailing space (check ChannelMasterController line 162)
                        const channelName = row['Channel '] || row['Channel'] || row.channel || row.name || row.Name || 'Unknown';
                        const sales = parseFloat(row['L30 Sales'] || row.l30_sales || row.L30Sales || 0);
                        
                        console.log('[Dashboard] Channel:', channelName, 'Sales:', sales);
                        
                        channels.push(channelName);
                        l30Sales.push(sales);
                    });
                    
                    console.log('[Dashboard] Channels extracted:', channels.length, 'channels');
                    
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
                                        font: { size: 11 }
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
    </script>
@endsection

