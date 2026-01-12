@extends('layouts.vertical', ['title' => 'Lost/Gain', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
<meta name="csrf-token" content="{{ csrf_token() }}">

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .table-container {
            overflow-x: auto;
        }
        .table-bordered {
            border: 1px solid #dee2e6;
        }
        .table-bordered th,
        .table-bordered td {
            border: 1px solid #dee2e6;
        }
        .badge {
            display: inline-block;
            padding: 0.35em 0.65em;
            font-size: 0.75em;
            font-weight: 700;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 0.25rem;
        }
        .bg-primary {
            background-color: #0d6efd !important;
            color: #fff;
        }
        .loss-gain-column {
            text-align: center !important;
        }
        .loss-gain-header {
            cursor: pointer;
        }
        .loss-gain-header:hover {
            background-color: #f0f0f0;
        }
        .sort-arrow {
            margin-left: 5px;
            font-size: 0.8em;
        }
        .filter-row th {
            padding: 5px;
            background-color: #f8f9fa;
        }
        .filter-row input {
            border: 1px solid #ced4da;
            font-size: 0.875rem;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared/page-title', ['page_title' => 'Inventory Management', 'sub_title' => 'Lost/Gain'])
    
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h4 class="header-title">Lost/Gain</h4>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="d-flex align-items-center gap-3">
                            <div>
                                <input type="text" id="lostGainSearch" class="form-control" placeholder="Search all columns">
                            </div>
                            <div>
                                <span class="badge bg-secondary fs-6">
                                    I&A Total: <span id="iaTotal">0</span>
                                </span>
                            </div>
                            <div>
                                <button id="bulkIABtn" class="btn btn-warning btn-sm" disabled>
                                    <i class="fas fa-archive"></i> Mark Selected as I&A
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="table-container">
                        <table class="table table-bordered" id="lostGainTable">
                            <thead>
                                <tr>
                                    <th>
                                        <input type="checkbox" id="selectAllCheckbox" title="Select All">
                                    </th>
                                    <th>Parent</th>
                                    <th>SKU</th>
                                    <th>Verified Stock</th>
                                    <th>Adjusted</th>
                                    <th class="loss-gain-column loss-gain-header" data-sort="loss_gain">
                                        <span id="lostGainTotal" class="badge bg-primary fs-4">0</span><br>
                                        Loss/Gain <span class="sort-arrow">↓</span>
                                    </th>
                                    <th>Reason</th>
                                    <th>Approved By</th>
                                    <th>Approved At (Ohio)</th>
                                    <th>Remarks</th>
                                    <th>Actions</th>
                                </tr>
                                <tr class="filter-row">
                                    <th></th>
                                    <th>
                                        <input type="text" id="parentFilter" class="form-control form-control-sm" placeholder="Search Parent">
                                    </th>
                                    <th>
                                        <input type="text" id="skuFilter" class="form-control form-control-sm" placeholder="Search SKU">
                                    </th>
                                    <th></th>
                                    <th></th>
                                    <th></th>
                                    <th></th>
                                    <th></th>
                                    <th></th>
                                    <th></th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Will be populated via JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            let tableRows = [];
            let currentSort = { field: null, direction: -1 }; // -1 for descending (highest to lowest)
            let iaRows = new Set(); // Track rows marked as I&A by index
            
            // Load data on page load
            loadLostGainData();

            function loadLostGainData() {
                $.ajax({
                    url: '/verified-stock-activity-log',
                    method: 'GET',
                    success: function(res) {
                        const tableBody = $('#lostGainTable tbody');
                        tableBody.empty();

                        if (!res.data || res.data.length === 0) {
                            tableBody.append('<tr><td colspan="11" class="text-center">No data found.</td></tr>');
                        } else {
                            // Fetch parent and LP data for all SKUs
                            const skus = res.data.map(item => item.sku);
                            
                            if (skus.length === 0) {
                                return;
                            }
                            
                            $.ajax({
                                url: '/lost-gain-product-data',
                                method: 'POST',
                                data: {
                                    skus: skus,
                                    _token: $('meta[name="csrf-token"]').attr('content')
                                },
                                success: function(productData) {
                                    const productMap = {};
                                    if (productData.data && Array.isArray(productData.data)) {
                                        productData.data.forEach(p => {
                                            productMap[p.sku] = p;
                                        });
                                    }

                                    res.data.forEach(item => {
                                        const product = productMap[item.sku] || {};
                                        const parentTitle = product.parent || '(No Parent)';
                                        const toAdjust = parseFloat(item.to_adjust) || 0;
                                        const lp = parseFloat(product.lp) || 0;

                                        let lossGainValue;
                                        if (item.loss_gain !== null && item.loss_gain !== undefined && item.loss_gain !== '') {
                                            lossGainValue = parseFloat(item.loss_gain);
                                        } else {
                                            lossGainValue = lp ? toAdjust * lp : 0;
                                        }

                                        const formattedLossGain = lossGainValue !== 0 ? `${Math.trunc(lossGainValue)}` : '-';

                                        tableRows.push({
                                            parent: parentTitle,
                                            sku: item.sku ?? '-',
                                            verified_stock: item.verified_stock ?? '-',
                                            to_adjust: item.to_adjust ?? '-',
                                            loss_gain: lossGainValue,
                                            formatted_loss_gain: formattedLossGain,
                                            reason: item.reason ?? '-',
                                            approved_by: item.approved_by ?? '-',
                                            approved_at: item.approved_at ?? '-',
                                            remarks: item.remarks ?? '-'
                                        });
                                    });

                                    // Sort by loss_gain descending (highest to lowest) by default
                                    tableRows.sort((a, b) => (b.loss_gain - a.loss_gain));
                                    
                                    // Render rows
                                    renderTableRows(tableRows);
                                    
                                    // Calculate and update totals
                                    updateTotals();
                                    
                                    // Initialize sort functionality
                                    initSort();
                                },
                                error: function() {
                                    // Fallback: render without parent data
                                    res.data.forEach(item => {
                                        const toAdjust = parseFloat(item.to_adjust) || 0;
                                        let lossGainValue = parseFloat(item.loss_gain) || 0;
                                        
                                        const formattedLossGain = lossGainValue !== 0 ? `${Math.trunc(lossGainValue)}` : '-';
                                        
                                        tableRows.push({
                                            parent: '(No Parent)',
                                            sku: item.sku ?? '-',
                                            verified_stock: item.verified_stock ?? '-',
                                            to_adjust: item.to_adjust ?? '-',
                                            loss_gain: lossGainValue,
                                            formatted_loss_gain: formattedLossGain,
                                            reason: item.reason ?? '-',
                                            approved_by: item.approved_by ?? '-',
                                            approved_at: item.approved_at ?? '-',
                                            remarks: item.remarks ?? '-',
                                            isIA: false // Track I&A state
                                        });
                                    });
                                    
                                    // Sort by loss_gain descending (highest to lowest) by default
                                    tableRows.sort((a, b) => (b.loss_gain - a.loss_gain));
                                    
                                    // Render rows
                                    renderTableRows(tableRows);
                                    
                                    // Calculate and update totals
                                    updateTotals();
                                    
                                    // Initialize sort functionality
                                    initSort();
                                }
                            });
                        }
                    },
                    error: function() {
                        alert('Failed to load data.');
                    }
                });
            }

            function renderTableRows(rows) {
                const tableBody = $('#lostGainTable tbody');
                tableBody.empty();
                
                rows.forEach((row, index) => {
                    const iaChecked = row.isIA ? 'checked' : '';
                    const iaClass = row.isIA ? 'btn-warning' : 'btn-outline-secondary';
                    tableBody.append(`
                        <tr data-row-index="${index}" ${row.isIA ? 'class="table-warning"' : ''}>
                            <td class="text-center">
                                <input type="checkbox" class="row-checkbox" data-row-index="${index}">
                            </td>
                            <td>${row.parent}</td>
                            <td>${row.sku}</td>
                            <td>${row.verified_stock}</td>
                            <td>${row.to_adjust}</td>
                            <td class="loss-gain-column">${row.formatted_loss_gain}</td>
                            <td>${row.reason}</td>
                            <td>${row.approved_by}</td>
                            <td>${row.approved_at}</td>
                            <td>${row.remarks}</td>
                            <td class="text-center">
                                <button class="btn btn-sm ia-btn ${iaClass}" data-row-index="${index}" title="Ignore & Archive">
                                    I&A
                                </button>
                            </td>
                        </tr>
                    `);
                });
                
                // Attach click handlers for I&A buttons
                $('.ia-btn').off('click').on('click', function() {
                    const rowIndex = parseInt($(this).data('row-index'));
                    toggleIA(rowIndex);
                });
                
                // Attach checkbox handlers
                $('.row-checkbox').off('change').on('change', function() {
                    updateBulkButtonState();
                });
                
                // Update bulk button state
                updateBulkButtonState();
            }

            function toggleIA(rowIndex) {
                if (tableRows[rowIndex]) {
                    tableRows[rowIndex].isIA = !tableRows[rowIndex].isIA;
                    renderTableRows(tableRows);
                    updateTotals();
                }
            }

            function updateTotals() {
                let lossGainTotal = 0;
                let iaTotal = 0;
                
                tableRows.forEach(row => {
                    if (row.isIA) {
                        iaTotal += row.loss_gain;
                    } else {
                        lossGainTotal += row.loss_gain;
                    }
                });
                
                $('#lostGainTotal').text(`${Math.trunc(lossGainTotal)}`);
                $('#iaTotal').text(`${Math.trunc(iaTotal)}`);
            }

            function updateBulkButtonState() {
                const checkedCount = $('.row-checkbox:checked').length;
                $('#bulkIABtn').prop('disabled', checkedCount === 0);
            }

            function bulkMarkAsIA() {
                const selectedRows = [];
                $('.row-checkbox:checked').each(function() {
                    const rowIndex = parseInt($(this).data('row-index'));
                    selectedRows.push(rowIndex);
                });

                if (selectedRows.length === 0) {
                    return;
                }

                // Mark all selected rows as I&A
                selectedRows.forEach(rowIndex => {
                    if (tableRows[rowIndex]) {
                        tableRows[rowIndex].isIA = true;
                    }
                });

                // Re-render table and update totals
                renderTableRows(tableRows);
                updateTotals();
                
                // Uncheck all checkboxes
                $('.row-checkbox').prop('checked', false);
                $('#selectAllCheckbox').prop('checked', false);
                updateBulkButtonState();
            }

            function initSort() {
                $('#lostGainTable thead th.loss-gain-header').off('click').on('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Toggle sort direction
                    if (currentSort.field === 'loss_gain') {
                        currentSort.direction *= -1;
                    } else {
                        currentSort.field = 'loss_gain';
                        currentSort.direction = -1; // Start with descending (highest to lowest)
                    }
                    
                    // Update sort arrow
                    const arrowText = currentSort.direction === -1 ? '↓' : '↑';
                    $(this).find('.sort-arrow').text(arrowText);
                    
                    // Sort tableRows
                    tableRows.sort((a, b) => {
                        return (b.loss_gain - a.loss_gain) * currentSort.direction;
                    });
                    
                    // Re-render table
                    renderTableRows(tableRows);
                    
                    // Update totals
                    updateTotals();
                });
            }

            // Column filter functionality
            function applyFilters() {
                const parentFilter = $('#parentFilter').val().toLowerCase();
                const skuFilter = $('#skuFilter').val().toLowerCase();
                const generalSearch = $('#lostGainSearch').val().toLowerCase();
                
                let visibleLossGainTotal = 0;
                let visibleIATotal = 0;

                $('#lostGainTable tbody tr').each(function() {
                    const $row = $(this);
                    const rowIndex = parseInt($row.data('row-index'));
                    
                    // Get column values (skip checkbox column, so Parent is index 1, SKU is index 2)
                    const parentText = $row.find('td:eq(1)').text().toLowerCase();
                    const skuText = $row.find('td:eq(2)').text().toLowerCase();
                    const rowText = $row.text().toLowerCase();
                    
                    // Apply filters
                    let isVisible = true;
                    
                    if (parentFilter && !parentText.includes(parentFilter)) {
                        isVisible = false;
                    }
                    
                    if (skuFilter && !skuText.includes(skuFilter)) {
                        isVisible = false;
                    }
                    
                    if (generalSearch && !rowText.includes(generalSearch)) {
                        isVisible = false;
                    }
                    
                    $row.toggle(isVisible);
                    
                    // Calculate totals for visible rows (Loss/Gain is now at index 5)
                    if (isVisible) {
                        const lossGainText = $row.find('td:eq(5)').text().trim();
                        const lossGainValue = parseFloat(lossGainText);
                        if (!isNaN(lossGainValue)) {
                            if (tableRows[rowIndex] && tableRows[rowIndex].isIA) {
                                visibleIATotal += lossGainValue;
                            } else {
                                visibleLossGainTotal += lossGainValue;
                            }
                        }
                    }
                });

                // Update the total badges with filtered totals
                $('#lostGainTotal').text(`${Math.trunc(visibleLossGainTotal)}`);
                $('#iaTotal').text(`${Math.trunc(visibleIATotal)}`);
            }

            // Search functionality
            $('#lostGainSearch').on('keyup', function() {
                applyFilters();
            });

            // Column-specific search functionality
            $('#parentFilter').on('keyup', function() {
                applyFilters();
            });

            $('#skuFilter').on('keyup', function() {
                applyFilters();
            });

            // Select all checkbox functionality
            $('#selectAllCheckbox').on('change', function() {
                const isChecked = $(this).is(':checked');
                $('.row-checkbox:visible').prop('checked', isChecked);
                updateBulkButtonState();
            });

            // Bulk I&A button functionality
            $('#bulkIABtn').on('click', function() {
                bulkMarkAsIA();
            });
        });
    </script>
@endsection
