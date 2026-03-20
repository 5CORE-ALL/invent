@extends('layouts.vertical', ['title' => 'Health Analysis', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    @vite(['node_modules/admin-resources/rwd-table/rwd-table.min.css'])
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.dataTables.min.css" rel="stylesheet">

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --accent-color: #4895ef;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --border-radius: 12px;
            --box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s ease;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fa !important;
            color: var(--dark-color) !important;
        }

        .container {
            max-width: 1200px !important;
            margin-top: 30px !important;
            margin-bottom: 50px !important;
        }

        .header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 15px 25px;
            border-radius: var(--border-radius);
            margin-bottom: 30px;
            box-shadow: var(--box-shadow);
        }

        .header h4 {
            font-weight: 600;
            margin: 0;
        }
        .metric-value {
    padding: 2px 6px;
    border-radius: 4px;
    display: inline-block;
}

/* Negative growth (Red) */
.negative-growth {
    background-color:rgb(255, 0, 0);
    color:rgb(0, 0, 0);
    width: 60px;
    text-align: center;
}

/* Zero growth (Yellow) */
.zero-growth {
    background-color:rgb(255, 196, 0);
    color:rgb(0, 0, 0);
    width: 60px;
    text-align: center;
}

/* EXACTLY 100% (Magenta) */
.exact-100 {
    background-color: #ff00ff;
    color:rgb(0, 0, 0);
    width: 60px;
    text-align: center;

}
        .search-box {
            max-width: 350px;
            margin-left: auto;
            background-color: rgba(255, 255, 255, 0.2);
            border-radius: 50px;
            padding: 5px;
            transition: var(--transition);
        }

        .dataTables_wrapper .dataTables_filter input {
            border: none;
            border-radius: 50px;
            padding: 8px 15px;
            margin-left: 10px;
        }

        .table>thead {
            vertical-align: bottom;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        }
        th.sorting {
            color: white !important;
            font-size: 10px;
        }

        /* Rest of your existing styles... */
        /* Keep all your existing styles, just add these new ones below */

        /* DataTables custom styling */
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_processing,
        .dataTables_wrapper .dataTables_paginate {
            color: #333;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button {
            padding: 0.5em 1em;
            margin: 0 2px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current,
        .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
            background: var(--primary-color);
            color: white !important;
            border: 1px solid var(--primary-color);
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: #e9ecef;
            border: 1px solid #ddd;
        }

        /* Loading indicator */
        .dataTables_processing {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(255, 255, 255, 0.9);
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            z-index: 100;
        }

        /* Responsive adjustments for DataTables */
        @media (max-width: 768px) {

            .dataTables_wrapper .dataTables_length,
            .dataTables_wrapper .dataTables_filter {
                float: none;
                text-align: left;
            }

            .dataTables_wrapper .dataTables_filter input {
                width: 100%;
                margin-left: 0;
                margin-top: 10px;
            }
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared/page-title', [
        'page_title' => 'Health Analysis',
        'sub_title' => 'Channel',
    ])
    <div class="container-fluid">
        <!-- Header with Title and Search -->
        <div class="header d-flex align-items-center">
            <div>
                <h4><i class="bi bi-bar-chart-line me-2"></i> Review Master Dashboard</h4>
            </div>
 
        </div>
        <!-- Add these 5 small cards -->
                <div class="row mb-4">
            <!-- Sales Cards -->
            <div class="col-md-2 col-sm-4">
                <div class="card bg-primary text-white">
                    <div class="card-body p-2">
                        <h6 class="card-title mb-1">L-60 Sales</h6>
                        <h4 class="mb-0" id="l60SalesCount">0</h4>
                        <small class="text-white-50">Total</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-sm-4">
                <div class="card bg-success text-white">
                    <div class="card-body p-2">
                        <h6 class="card-title mb-1">L-30 Sales</h6>
                        <h4 class="mb-0" id="l30SalesCount">0</h4>
                        <small class="text-white-50">Recent</small>
                    </div>
                </div>
            </div>
            
            <!-- Orders Cards -->
            <div class="col-md-2 col-sm-4">
                <div class="card bg-secondary text-white">
                    <div class="card-body p-2">
                        <h6 class="card-title mb-1">L-60 Orders</h6>
                        <h4 class="mb-0" id="l60OrdersCount">0</h4>
                        <small class="text-white-50">Total</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-sm-4">
                <div class="card bg-dark text-white">
                    <div class="card-body p-2">
                        <h6 class="card-title mb-1">L-30 Orders</h6>
                        <h4 class="mb-0" id="l30OrdersCount">0</h4>
                        <small class="text-white-50">Recent</small>
                    </div>
                </div>
            </div>
            
            <!-- Metrics Cards -->
            <div class="col-md-2 col-sm-4">
                <div class="card bg-info text-white">
                    <div class="card-body p-2">
                        <h6 class="card-title mb-1">Growth</h6>
                        <h4 class="mb-0" id="growthPercentage">0%</h4>
                        <small class="text-white-50">Change</small>
                    </div>
                </div>
            </div>
            <!-- <div class="col-md-1 col-sm-4">
                <div class="card bg-warning text-dark">
                    <div class="card-body p-2">
                        <h6 class="card-title mb-1">Gprofit%</h6>
                        <h4 class="mb-0" id="gprofitPercentage">0%</h4>
                        <small class="text-dark-50">Average</small>
                    </div>
                </div>
            </div> -->
            <div class="col-md-2 col-sm-4">
                <div class="card bg-danger text-white">
                    <div class="card-body p-2">
                        <h6 class="card-title mb-1">G Roi%</h6>
                        <h4 class="mb-0" id="groiPercentage">0%</h4>
                        <small class="text-white-50">Average</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Table Container -->
        <div class="table-container">
            <table class="table table-hover" id="channelTable">
                <thead>
                    <tr>
                        <th>Channel</th>
                        <th>Executive</th>
                        <th>Link</th>
                        <!-- <th>L-60 Sales</th>
                        <th>L30 Sales</th>
                        <th>Growth</th>
                        <th>L60 Orders</th>
                        <th>L30 Orders</th>
                        <th>Gprofit%</th>
                        <th>G Roi%</th> -->
                        <!-- <th>Update</th> -->
                    </tr>
                </thead>
             <tbody>
                    <!-- Data will be loaded via AJAX -->
                </tbody>
            </table>
        </div>
    </div>

    <!-- Advanced Info Modal -->
    <div class="modal fade" id="advancedInfoModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Channel Metrics Summary</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="compact-metrics">
                        <div class="metric-row">
                            <div class="metric-item">
                                <i class="bi bi-pencil-square edit-icon" onclick="editMetric(this)"></i>
                                <div class="metric-value">159</div>
                                <input type="text" class="metric-input" value="159" style="display: none;">
                            </div>
                            <div class="metric-item">
                                <i class="bi bi-pencil-square edit-icon" onclick="editMetric(this)"></i>
                                <div class="metric-value">189</div>
                                <input type="text" class="metric-input" value="189" style="display: none;">
                            </div>
                            <div class="metric-item">
                                <i class="bi bi-pencil-square edit-icon" onclick="editMetric(this)"></i>
                                <div class="metric-value">340</div>
                                <input type="text" class="metric-input" value="340" style="display: none;">
                            </div>
                            <div class="metric-item">
                                <i class="bi bi-pencil-square edit-icon" onclick="editMetric(this)"></i>
                                <div class="metric-value">760</div>
                                <input type="text" class="metric-input" value="760" style="display: none;">
                            </div>
                            <div class="metric-item">
                                <i class="bi bi-pencil-square edit-icon" onclick="editMetric(this)"></i>
                                <div class="metric-value">230</div>
                                <input type="text" class="metric-input" value="230" style="display: none;">
                            </div>
                            <div class="metric-item">
                                <i class="bi bi-pencil-square edit-icon" onclick="editMetric(this)"></i>
                                <div class="metric-value">600</div>
                                <input type="text" class="metric-input" value="230" style="display: none;">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="saveChanges()">Save Changes</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <!-- Load jQuery FIRST -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Bootstrap JS (required for DataTables Bootstrap integration) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- DataTables Core JS -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>

    <!-- DataTables Bootstrap 5 Integration -->
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

    <!-- DataTables Buttons Extension -->
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>

    <!-- Buttons HTML5 Export -->
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>

    <!-- Buttons Print Support -->
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>

    <!-- JSZip (required for Excel export) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>

    <!-- pdfmake (required for PDF export) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
    <script>
        // Use jQuery.noConflict() if needed
        var jq = $.noConflict(true);

        //all card total count start ***************************************
        // Enhanced number parsing function
        function parseNumber(value) {
            if (value === null || value === undefined || value === '' || 
                value === '#DIV/0!' || value === 'N/A') return 0;
            if (typeof value === 'number') return value;
            const cleaned = String(value).replace(/[^0-9.-]/g, '');
            return parseFloat(cleaned) || 0;
        }

        // Function to update all summary cards
        function updateAllTotals(data) {
            console.log("Calculating totals for:", data); // Debugging
            
            let l60SalesTotal = 0;
            let l30SalesTotal = 0;
            let l60OrdersTotal = 0;
            let l30OrdersTotal = 0;
            let growthValues = [];
            let gprofitValues = [];
            let groiValues = [];
            
            data.forEach(row => {
                try {
                    // Process sales - try multiple possible column names
                    const l60Sales = parseNumber(
                        row['L-60 Sales'] || row['L40 Sales'] || row['L40-Dollar'] || row['L40-Dollars'] || 0
                    );
                    const l30Sales = parseNumber(
                        row['L30 Sales'] || row['L30-Dollar'] || row['L30-Dollars'] || 0
                    );
                    
                    // Process orders - try multiple possible column names
                    const l60Orders = parseNumber(
                        row['L60 Orders'] || row['L40-Dollars'] || 0
                    );
                    const l30Orders = parseNumber(
                        row['L30 Orders'] || row['L30-Dollars'] || 0
                    );
                    
                    // Process metrics
                    const growth = parseNumber(row['Growth'] || 0);
                    const gprofit = parseNumber(row['Gprofit%'] || row['Growth%'] || 0);
                    const groi = parseNumber(row['G Roi%'] || row['G.Rents'] || 0);
                    
                    // Add to totals
                    l60SalesTotal += l60Sales;
                    l30SalesTotal += l30Sales;
                    l60OrdersTotal += l60Orders;
                    l30OrdersTotal += l30Orders;
                    
                    // Collect for averages
                    if (!isNaN(growth)) growthValues.push(growth);
                    if (!isNaN(gprofit)) gprofitValues.push(gprofit);
                    if (!isNaN(groi)) groiValues.push(groi);
                    
                } catch (e) {
                    console.error("Error processing row:", row, e);
                }
            });

            // Update the cards with formatted values
            $('#l60SalesCount').text(Math.round(l60SalesTotal).toLocaleString('en-US'));
            $('#l30SalesCount').text(Math.round(l30SalesTotal).toLocaleString('en-US'));
            $('#l60OrdersCount').text(Math.round(l60OrdersTotal).toLocaleString('en-US'));
            $('#l30OrdersCount').text(Math.round(l30OrdersTotal).toLocaleString('en-US'));
            
            // Calculate averages for percentages
            const calculateAverage = arr => arr.length ? 
                (arr.reduce((a, b) => a + b, 0) / arr.length * 100) : 0;
            
            $('#growthPercentage').text(Math.round(calculateAverage(growthValues)) + '%');
            $('#gprofitPercentage').text(Math.round(calculateAverage(gprofitValues)) + '%');
            $('#groiPercentage').text(Math.round(calculateAverage(groiValues)) + '%');
            
            console.log("Updated totals:", {
                l60SalesTotal,
                l30SalesTotal,
                l60OrdersTotal,
                l30OrdersTotal,
                growthAvg: calculateAverage(growthValues),
                gprofitAvg: calculateAverage(gprofitValues),
                groiAvg: calculateAverage(groiValues)
            });
        }

        //all card count last code

    jq(document).ready(function($) {
            // Now you can safely use $ inside this function
            var table = $('#channelTable').DataTable({
                processing: true,
                serverSide: true,
                searching: true, // ← Enable built-in search
                ajax: {
                    url: '/channels-master-data',
                    type: "GET",
                    error: function(xhr, error, thrown) {
                        console.log("AJAX error:", error, thrown);
                    }
                },
                columns: [{
                        data: 'Channel ',
                        render: function(data, type, row) {
                            return `<div class="d-flex align-items-center">
                            <span>${data}</span>
                        </div>`;
                        }
                    },
                    {
                        data: 'Exec',
                        render: function(data, type, row) {
                            return data || ''; // Shows 'N/A' if Exec is null/undefined
                        }
                    },
                    {
                        data: 'URL LINK',
                        render: function(data, type, row) {
                            return data ?
                                `<a href="${data}" target="_blank"><i class="bi bi-box-arrow-up-right link-icon"></i></a>` :
                                '';
                        }
                    },
                //     {
                //         data: 'L-60 Sales',
                //         className: 'metric-cell',
                //         render: function(data, type, row) {
                //             try {
                //                 const num = parseFloat(data.toString().replace(/,/g, ''));
                //                 const roundedNum = Math.round(num);
                //                 return `<span class="metric-value">${roundedNum.toLocaleString('en-US')}</span>`;
                //             } catch (e) {
                //                 return '<span class="metric-value">N/A</span>';
                //             }
                //         }
                //     },
                //     {
                //         data: 'L30 Sales',
                //         className: 'metric-cell',
                //         render: function(data, type, row) {
                //             if (data === undefined || data === null || data === '#DIV/0!') {
                //                 return '<span class="metric-value"></span>';
                //             }
                            
                //             // Remove any existing commas and convert to number
                //             const num = parseFloat(data.toString().replace(/,/g, ''));
                            
                //             // Check if we got a valid number
                //             if (isNaN(num)) {
                //                 return '<span class="metric-value"></span>';
                //             }
                            
                //             // Round to nearest integer
                //             const roundedNum = Math.round(num);
                            
                //             // Format with thousands separators and no decimal places
                //             return `<span class="metric-value">${roundedNum.toLocaleString('en-US', {
                //                 minimumFractionDigits: 0,
                //                 maximumFractionDigits: 0
                //             })}</span>`;
                //         }
                //     },
                //     {
                //     data: 'Growth',
                //     className: 'metric-cell',
                //     render: function(data, type, row) {
                //         // Convert to number and calculate percentage
                //         if (data === undefined || data === null || data === '#DIV/0!') {
                //                                 return '<span class="metric-value"></span>';
                //                             }
                //         const num = parseFloat(data);
                //         if (isNaN(num)) {
                //             return `<span class="metric-value">${data}</span>`;
                //         }
                        
                //         const percentageValue = (num * 100).toFixed(0);
                //         let cellClass = 'metric-value';
                        
                //         if (num < 0) {
                //             cellClass += ' negative-growth'; // Red for negative
                //         } else if (num === 0) {
                //             cellClass += ' zero-growth'; // Yellow for zero
                //         } else if (percentageValue === '100') { // Check for EXACTLY 100%
                //             cellClass += ' exact-100'; // Magenta ONLY for 100%
                //         }
                //         // Other values (50%, 200%, etc.) get default styling
                        
                //         return `<span class="${cellClass}">${percentageValue}%</span>`;
                //     }
                // },                    
                //     {
                //         data: 'L60 Orders',
                //         className: 'metric-cell',
                //         render: function(data, type, row) {
                //             if (data === undefined || data === null || data === '#DIV/0!') {
                //                 return '<span class="metric-value"></span>';
                //             }
                //             return `<span class="metric-value">${data}</span>`;
                //         }
                //     },
                //     {
                //         data: 'L30 Orders',
                //         className: 'metric-cell',
                //         render: function(data, type, row) {
                //             if (data === undefined || data === null || data === '#DIV/0!') {
                //                 return '<span class="metric-value"></span>';
                //             }
                //             return `<span class="metric-value">${data}</span>`;
                //         }
                //     },
                //     {
                //         data: 'Gprofit%',
                //         className: 'metric-cell',
                //         render: function(data, type, row) {
                //             // Handle undefined, null, or #DIV/0!
                //             if (data === undefined || data === null || data === '#DIV/0!') {
                //                 return '<span class="metric-value"></span>';
                //             }
                            
                //             // Convert to number
                //             const num = parseFloat(data);
                            
                //             // If not a number, return empty
                //             if (isNaN(num)) {
                //                 return '<span class="metric-value"></span>';
                //             }
                            
                //             // Convert to percentage (multiply by 100) and format
                //             const percentageValue = (num * 100).toFixed(0); // Rounds to whole number
                //             return `<span class="metric-value">${percentageValue}%</span>`;
                //         }
                //     },
                //     {
                //         data: 'G Roi%',
                //         className: 'metric-cell',
                //         render: function(data, type, row) {
                //             // Handle undefined, null, or #DIV/0!
                //             if (data === undefined || data === null || data === '#DIV/0!') {
                //                 return '<span class="metric-value"></span>'; // Blank if invalid
                //             }

                //             // Convert to number
                //             const num = parseFloat(data);

                //             // If not a valid number, return blank
                //             if (isNaN(num)) {
                //                 return '<span class="metric-value"></span>';
                //             }

                //             // Convert to percentage (×100) and round to nearest integer
                //             const roiPercent = Math.round(num * 100); // 0.17 → 17%, 3.12 → 312%
                            
                //             return `<span class="metric-value">${roiPercent}%</span>`;
                //         }
                //     },
                //     {
                //         data: 'Checked',
                //         render: function(data, type, row) {
                //             var isChecked = data ? 'checked' : '';
                //             var dateDisplay = data ? new Date(data).toLocaleDateString() : '';

                //             return '<input type="checkbox" ' + isChecked +
                //                 ' title="' + dateDisplay + '" disabled>';
                //         }
                //     }
                ],
                responsive: true,
                dom: '<"top"lf>rt<"bottom"ip><"clear">',
                lengthMenu: [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                pageLength: 25,
                order: [
                    [0, 'asc']
                ],
                buttons: [{
                        extend: 'excel',
                        text: '<i class="fas fa-file-excel"></i> Excel',
                        className: 'btn btn-success'
                    },
                    {
                        extend: 'print',
                        text: '<i class="fas fa-print"></i> Print',
                        className: 'btn btn-info'
                    }
                ],
                initComplete: function() {
                    // Add buttons to DOM
                    var buttons = new $.fn.dataTable.Buttons(table, {
                        buttons: ['excel', 'print']
                    }).container().appendTo($('#channelTable_wrapper .col-md-6:eq(0)'));
                }
            });


                        // Update totals when table is filtered/searched
                        table.on('draw', function() {
                var data = table.rows({search: 'applied'}).data().toArray();
                updateAllTotals(data);
            });

            // Custom search input (optional - DataTables has built-in search)
          // Custom search functionality
    $('#searchInput').keyup(function() {
        // Use the DataTables API to search
        table.search(this.value).draw();
    });

            // Keep your existing functions for modal, editing, etc.
            // ...

            // EDIT METRIC FUNCTIONALITY
            function editMetric(editIcon) {
                const metricItem = editIcon.closest('.metric-item');
                const metricValue = metricItem.querySelector('.metric-value');
                const metricInput = metricItem.querySelector('.metric-input');

                // Toggle edit mode
                metricItem.classList.add('editing');
                metricValue.style.display = 'none';
                metricInput.style.display = 'block';
                metricInput.focus();

                // Handle Enter key to save
                metricInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        saveMetric(metricItem);
                    }
                });

                // Handle click outside to save
                document.addEventListener('click', function outsideClick(e) {
                    if (!metricItem.contains(e.target) && e.target !== editIcon) {
                        saveMetric(metricItem);
                        document.removeEventListener('click', outsideClick);
                    }
                });
            }

            function saveMetric(metricItem) {
                const metricValue = metricItem.querySelector('.metric-value');
                const metricInput = metricItem.querySelector('.metric-input');

                metricValue.textContent = metricInput.value;
                metricItem.classList.remove('editing');
                metricValue.style.display = 'block';
                metricInput.style.display = 'none';
            }

            function saveChanges() {
                // In a real app, you would save the changes to your database here
                alert('Changes saved successfully!');
                const modal = bootstrap.Modal.getInstance(document.getElementById('advancedInfoModal'));
                modal.hide();
            }

            // SHOW ADVANCED POPUP WITH DRAGGABLE, SCROLLABLE AND EDITABLE FUNCTIONALITY
            function showAdvancedPopup() {
                const modalElement = document.getElementById('advancedInfoModal');
                const modal = new bootstrap.Modal(modalElement);

                // Reset position when modal is shown
                modalElement.addEventListener('show.bs.modal', function() {
                    const dialog = modalElement.querySelector('.modal-dialog');
                    dialog.style.top = '50px';
                    dialog.style.left = '50px';
                });

                // Initialize draggable functionality
                modalElement.addEventListener('shown.bs.modal', function() {
                    makeDraggable(modalElement);
                });

                modal.show();
            }
        });
    </script>
@endsection
