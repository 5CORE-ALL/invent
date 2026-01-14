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
        .badge.badge-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            line-height: 1.5;
        }
        .bg-danger {
            background-color: #dc3545 !important;
            color: #fff;
        }
        .text-danger {
            color: #dc3545 !important;
        }
        @media (max-width: 1200px) {
            .table-container {
                font-size: 0.875rem;
            }
            .table th, .table td {
                padding: 0.5rem;
            }
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

                    <div class="mb-3">
                        <div class="row g-3 mb-3">
                            <div class="col-md-3">
                                <label for="reasonFilter" class="form-label">Reason</label>
                                <select id="reasonFilter" class="form-select form-select-sm">
                                    <option value="">All Reasons</option>
                                    <option value="Count">Count</option>
                                    <option value="Received">Received</option>
                                    <option value="Return Restock">Return Restock</option>
                                    <option value="Damaged">Damaged</option>
                                    <option value="Theft or Loss">Theft or Loss</option>
                                    <option value="Promotion">Promotion</option>
                                    <option value="Suspense">Suspense</option>
                                    <option value="Unknown">Unknown</option>
                                    <option value="Adjustment">Adjustment</option>
                                    <option value="Combo">Combo</option>
                                    <option value="Maybe FBA">Maybe FBA</option>
                                    <option value="Need 2 Find">Need 2 Find</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="approvedByFilter" class="form-label">Approved By</label>
                                <select id="approvedByFilter" class="form-select form-select-sm">
                                    <option value="">All Users</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="dateFromFilter" class="form-label">Date From</label>
                                <input type="date" id="dateFromFilter" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-3">
                                <label for="dateToFilter" class="form-label">Date To</label>
                                <input type="date" id="dateToFilter" class="form-control form-control-sm">
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <div>
                                    <input type="text" id="lostGainSearch" class="form-control form-control-sm" placeholder="Search all columns" style="min-width: 200px;">
                                </div>
                                <div>
                                    <span class="badge bg-info badge-sm">
                                        Adjusted: <span id="adjustedTotal">0</span>
                                    </span>
                                </div>
                                <div>
                                    <span class="badge bg-primary badge-sm" id="lostGainBadge">
                                        Loss/Gain: <span id="lostGainTotal">0</span>
                                    </span>
                                </div>
                                <div>
                                    <span class="badge bg-secondary badge-sm">
                                        I&A Total: <span id="iaTotal">0</span>
                                    </span>
                                </div>
                                <div>
                                    <button id="iaFilterBtn" class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-filter"></i> I&A (<span id="iaFilterCount">0</span>)
                                    </button>
                                </div>
                                <div>
                                    <button id="bulkIABtn" class="btn btn-dark btn-sm" disabled>
                                        <i class="fas fa-archive"></i> Mark Selected as I&A
                                    </button>
                                </div>
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
                                    <th class="text-center">Adjusted</th>
                                    <th class="loss-gain-column loss-gain-header" data-sort="loss_gain">
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
            let showIARows = true; // Toggle state for I&A rows visibility
            
            // Load data on page load
            loadLostGainData();

            function loadLostGainData() {
                // Get filter values
                const reason = $('#reasonFilter').val() || '';
                const approvedBy = $('#approvedByFilter').val() || '';
                const dateFrom = $('#dateFromFilter').val() || '';
                const dateTo = $('#dateToFilter').val() || '';
                
                // Clear existing table rows
                tableRows = [];
                
                $.ajax({
                    url: '/verified-stock-activity-log',
                    method: 'GET',
                    data: {
                        reason: reason,
                        approved_by: approvedBy,
                        date_from: dateFrom,
                        date_to: dateTo
                    },
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

                                    // Collect unique approved_by values for dropdown
                                    const approvedBySet = new Set();
                                    
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

                                        // Collect approved_by values
                                        if (item.approved_by && item.approved_by !== '-') {
                                            approvedBySet.add(item.approved_by);
                                        }

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
                                            remarks: item.remarks ?? '-',
                                            isIA: item.is_ia || false // Track I&A state from database
                                        });
                                    });
                                    
                                    // Populate approved_by dropdown
                                    populateApprovedByDropdown(Array.from(approvedBySet).sort());

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
                                    // Collect unique approved_by values for dropdown
                                    const approvedBySet = new Set();
                                    
                                    res.data.forEach(item => {
                                        const toAdjust = parseFloat(item.to_adjust) || 0;
                                        let lossGainValue = parseFloat(item.loss_gain) || 0;
                                        
                                        // Collect approved_by values
                                        if (item.approved_by && item.approved_by !== '-') {
                                            approvedBySet.add(item.approved_by);
                                        }
                                        
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
                                            isIA: item.is_ia || false // Track I&A state from database
                                        });
                                    });
                                    
                                    // Populate approved_by dropdown
                                    populateApprovedByDropdown(Array.from(approvedBySet).sort());
                                    
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
                    // Skip I&A rows if filter is off
                    if (!showIARows && row.isIA) {
                        return;
                    }
                    
                    const iaChecked = row.isIA ? 'checked' : '';
                    const iaClass = row.isIA ? 'btn-warning' : 'btn-outline-secondary';
                    // Apply red color for negative loss/gain values
                    const lossGainClass = row.loss_gain < 0 ? 'text-danger fw-bold' : '';
                    const lossGainDisplay = row.formatted_loss_gain !== '-' 
                        ? `<span class="${lossGainClass}">${row.formatted_loss_gain}</span>` 
                        : row.formatted_loss_gain;
                    tableBody.append(`
                        <tr data-row-index="${index}" ${row.isIA ? 'class="table-warning"' : ''}>
                            <td class="text-center">
                                <input type="checkbox" class="row-checkbox" data-row-index="${index}">
                            </td>
                            <td>${row.parent}</td>
                            <td>${row.sku}</td>
                            <td>${row.verified_stock}</td>
                            <td>${row.to_adjust}</td>
                            <td class="loss-gain-column">${lossGainDisplay}</td>
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
                    const newIAStatus = !tableRows[rowIndex].isIA;
                    const sku = tableRows[rowIndex].sku;
                    
                    // Save to database
                    $.ajax({
                        url: '/lost-gain-update-ia',
                        method: 'POST',
                        data: {
                            skus: [sku],
                            is_ia: newIAStatus,
                            _token: $('meta[name="csrf-token"]').attr('content')
                        },
                        success: function(res) {
                            if (res.success) {
                                if (res.updated > 0) {
                                    // Update ALL rows with the same SKU (not just the clicked one)
                                    tableRows.forEach((row, idx) => {
                                        if (row.sku === sku) {
                                            row.isIA = newIAStatus;
                                        }
                                    });
                                    
                                    renderTableRows(tableRows);
                                    updateTotals();
                                    
                                    // Show success message if there were any issues
                                    if (res.not_found && res.not_found.length > 0) {
                                        alert('Warning: ' + res.message);
                                    }
                                } else {
                                    alert('Failed to update: SKU not found in database.');
                                }
                            } else {
                                alert('Failed to save I&A status: ' + (res.message || 'Unknown error'));
                            }
                        },
                        error: function(xhr) {
                            let errorMsg = 'Failed to save I&A status. Please try again.';
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                errorMsg = xhr.responseJSON.message;
                            }
                            alert(errorMsg);
                        }
                    });
                }
            }

            function updateTotals() {
                let lossGainTotal = 0;
                let iaTotal = 0;
                let adjustedTotal = 0;
                let iaCount = 0;
                
                tableRows.forEach(row => {
                    const toAdjust = parseFloat(row.to_adjust) || 0;
                    adjustedTotal += toAdjust;
                    
                    if (row.isIA) {
                        iaTotal += row.loss_gain;
                        iaCount++;
                    } else {
                        lossGainTotal += row.loss_gain;
                    }
                });
                
                $('#lostGainTotal').text(`${Math.trunc(lossGainTotal)}`);
                $('#iaTotal').text(`${Math.trunc(iaTotal)}`);
                $('#adjustedTotal').text(`${Math.trunc(adjustedTotal)}`);
                $('#iaFilterCount').text(iaCount);
                
                // Update badge color based on value (red for negative)
                const lostGainBadge = $('#lostGainBadge');
                if (lossGainTotal < 0) {
                    lostGainBadge.removeClass('bg-primary').addClass('bg-danger');
                } else {
                    lostGainBadge.removeClass('bg-danger').addClass('bg-primary');
                }
            }

            function updateBulkButtonState() {
                const checkedCount = $('.row-checkbox:checked').length;
                $('#bulkIABtn').prop('disabled', checkedCount === 0);
            }

            function bulkMarkAsIA() {
                const selectedRows = [];
                const selectedSkus = [];
                $('.row-checkbox:checked').each(function() {
                    const rowIndex = parseInt($(this).data('row-index'));
                    selectedRows.push(rowIndex);
                    if (tableRows[rowIndex]) {
                        selectedSkus.push(tableRows[rowIndex].sku);
                    }
                });

                if (selectedRows.length === 0 || selectedSkus.length === 0) {
                    return;
                }

                // Disable button during processing
                $('#bulkIABtn').prop('disabled', true).text('Processing...');

                // Save to database
                $.ajax({
                    url: '/lost-gain-update-ia',
                    method: 'POST',
                    data: {
                        skus: selectedSkus,
                        is_ia: true,
                        _token: $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(res) {
                        if (res.success) {
                            if (res.updated > 0) {
                                // Get unique SKUs from selected rows
                                const selectedSkuSet = new Set(selectedSkus);
                                
                                // Update ALL rows with the same SKU (not just the selected ones)
                                tableRows.forEach((row, idx) => {
                                    if (selectedSkuSet.has(row.sku)) {
                                        row.isIA = true;
                                    }
                                });
                            }

                            // Re-render table and update totals
                            renderTableRows(tableRows);
                            updateTotals();
                            
                            // Uncheck all checkboxes
                            $('.row-checkbox').prop('checked', false);
                            $('#selectAllCheckbox').prop('checked', false);
                            updateBulkButtonState();
                            
                            // Show message if there were issues
                            if (res.not_found && res.not_found.length > 0) {
                                alert('Warning: ' + res.message);
                            } else if (res.updated === 0) {
                                alert('Failed to update: No records found for the selected SKUs.');
                            }
                        } else {
                            alert('Failed to save I&A status: ' + (res.message || 'Unknown error'));
                        }
                    },
                    error: function(xhr) {
                        let errorMsg = 'Failed to save I&A status. Please try again.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMsg = xhr.responseJSON.message;
                        }
                        alert(errorMsg);
                    },
                    complete: function() {
                        // Re-enable button
                        $('#bulkIABtn').prop('disabled', false).html('<i class="fas fa-archive"></i> Mark Selected as I&A');
                    }
                });
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
                let visibleAdjustedTotal = 0;

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
                    
                    // Calculate totals for visible rows (Adjusted is at index 4, Loss/Gain is at index 5)
                    if (isVisible) {
                        const adjustedText = $row.find('td:eq(4)').text().trim();
                        const adjustedValue = parseFloat(adjustedText);
                        if (!isNaN(adjustedValue)) {
                            visibleAdjustedTotal += adjustedValue;
                        }
                        
                        // Get loss/gain value from tableRows data (more reliable than parsing HTML)
                        if (tableRows[rowIndex]) {
                            const lossGainValue = tableRows[rowIndex].loss_gain || 0;
                            if (tableRows[rowIndex].isIA) {
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
                $('#adjustedTotal').text(`${Math.trunc(visibleAdjustedTotal)}`);
                
                // Update badge color based on value (red for negative)
                const lostGainBadge = $('#lostGainBadge');
                if (visibleLossGainTotal < 0) {
                    lostGainBadge.removeClass('bg-primary').addClass('bg-danger');
                } else {
                    lostGainBadge.removeClass('bg-danger').addClass('bg-primary');
                }
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
            
            // Filter change handlers
            $('#reasonFilter, #approvedByFilter, #dateFromFilter, #dateToFilter').on('change', function() {
                loadLostGainData();
            });
            
            // I&A filter button functionality
            $('#iaFilterBtn').on('click', function() {
                showIARows = !showIARows;
                
                // Update button appearance
                if (showIARows) {
                    $(this).removeClass('btn-secondary').addClass('btn-outline-secondary');
                } else {
                    $(this).removeClass('btn-outline-secondary').addClass('btn-secondary');
                }
                
                // Re-render table with current filter state
                renderTableRows(tableRows);
                updateTotals();
            });
            
            // Function to populate approved_by dropdown
            function populateApprovedByDropdown(approvedByList) {
                const dropdown = $('#approvedByFilter');
                const currentValue = dropdown.val();
                
                // Clear existing options except "All Users"
                dropdown.find('option:not(:first)').remove();
                
                // Add options
                approvedByList.forEach(name => {
                    dropdown.append(`<option value="${name}">${name}</option>`);
                });
                
                // Restore previous selection if it still exists
                if (currentValue) {
                    dropdown.val(currentValue);
                }
            }
        });
    </script>
@endsection
