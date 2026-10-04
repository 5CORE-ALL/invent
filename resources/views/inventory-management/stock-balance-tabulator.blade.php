@extends('layouts.vertical', ['title' => 'Stock Balance Tabulator', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">

    <style>
        .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }
        
        /* Vertical column headers */
        .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            white-space: nowrap;
            transform: rotate(180deg);
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 600;
        }
        
        .tabulator .tabulator-header .tabulator-col {
            height: 80px !important;
        }

        .tabulator .tabulator-header .tabulator-col.tabulator-sortable .tabulator-col-title {
            padding-right: 0px !important;
        }

        /* ACTION is a colored dot only */
        .last-update-dot {
            width: 16px;
            height: 16px;
            border: none;
            border-radius: 50%;
            padding: 0;
            display: inline-block;
            cursor: pointer;
        }

        .tabulator .action-select.form-select {
            width: 16px;
            height: 16px;
            min-height: 16px;
            padding: 0;
            border: none;
            border-radius: 50%;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: none !important;
            font-size: 0;
            line-height: 16px;
            color: transparent;
            cursor: pointer;
            box-shadow: none;
        }

        /* DIL% colors */
        .dil-red { color: #a00211; font-weight: 600; }
        .dil-yellow { color: #ffc107; font-weight: 600; }
        .dil-green { color: #28a745; font-weight: 600; }
        .dil-pink { color: #e83e8c; font-weight: 600; }

        /* Parent rows (SKU starts with PARENT) */
        .tabulator-row.parent-row,
        .tabulator-row.parent-row .tabulator-cell,
        .tabulator-row.tabulator-row-even.parent-row,
        .tabulator-row.tabulator-row-odd.parent-row,
        .tabulator-row.tabulator-row-even.parent-row .tabulator-cell,
        .tabulator-row.tabulator-row-odd.parent-row .tabulator-cell {
            background-color: #fff3cd !important;
        }
        .tabulator-row.parent-row:hover,
        .tabulator-row.parent-row:hover .tabulator-cell {
            background-color: #ffe69c !important;
        }
        
        /* Custom success toast styling */
        .toast-container {
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 9999;
        }
        
        .toast-success-big {
            min-width: 400px;
            background-color: #1a5928 !important;
            border: 2px solid #0d3d1a !important;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3) !important;
        }
        
        .toast-success-big .toast-body {
            font-size: 18px !important;
            font-weight: 700 !important;
            color: white !important;
            padding: 20px 25px !important;
            letter-spacing: 0.5px;
        }
        
        .from-sku-display,
        .ratio-display {
            display: block;
            font-size: 13px;
            font-weight: 600;
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .from-qty-input {
            border: none !important;
            box-shadow: none !important;
            background: transparent !important;
            padding: 0 !important;
            height: auto;
            text-align: center;
            width: 42px;
        }
        .tabulator .to-qty-display.form-control {
            border: none !important;
            outline: none !important;
            box-shadow: none !important;
            background-color: #d4edda !important;
            padding: 1px 2px !important;
            height: auto;
            min-height: 0;
            border-radius: 4px;
            text-align: center;
            width: 36px;
            font-weight: 700;
        }
        .from-qty-input:focus {
            outline: none;
            box-shadow: none !important;
        }
        .from-qty-input::-webkit-outer-spin-button,
        .from-qty-input::-webkit-inner-spin-button {
            margin: 0;
        }
        .submit-transfer-btn {
            background-color: #ffc107 !important;
            border-color: #ffc107 !important;
            color: #000 !important;
        }
        .submit-transfer-btn:hover,
        .submit-transfer-btn:focus {
            background-color: #e0a800 !important;
            border-color: #e0a800 !important;
            color: #000 !important;
        }
        .submit-transfer-btn.submit-recent,
        .submit-transfer-btn.submit-recent:hover,
        .submit-transfer-btn.submit-recent:focus {
            background-color: #198754 !important;
            border-color: #198754 !important;
            color: #fff !important;
        }
        .submit-transfer-btn.submit-blocked,
        .submit-transfer-btn.submit-blocked:hover,
        .submit-transfer-btn.submit-blocked:focus {
            background-color: #dc3545 !important;
            border-color: #dc3545 !important;
            color: #fff !important;
            cursor: not-allowed;
        }

        .rule-edit-btn {
            width: 22px;
            height: 22px;
            padding: 0;
            border: none !important;
            background: transparent !important;
            box-shadow: none !important;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
        }
        .rule-edit-btn.has-rule {
            color: #0d6efd;
        }
        .rule-edit-btn:hover {
            color: #0a58ca;
        }
        #addRuleModal .form-label {
            font-size: 11px;
            font-weight: 600;
            margin-bottom: 4px;
            letter-spacing: 0.02em;
        }
        #addRuleModal .select2-container {
            width: 100% !important;
        }
        #addRuleModal .select2-container--open {
            z-index: 2000;
        }
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Stock Balance Transfer',
        'sub_title' => 'Multi-SKU Transfer System',
    ])
    
    <div class="toast-container"></div>
    
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <h4>Stock Balance Transfer</h4>

                <!-- Filters -->
                <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
                    <label for="row-filter" class="mb-0 small text-muted">Row</label>
                    <select id="row-filter" class="form-select form-select-sm" style="width: auto;">
                        <option value="sku" selected>Sku</option>
                        <option value="parent">Parent</option>
                    </select>
                    
                    <select id="dil-filter" class="form-select form-select-sm" style="width: auto;">
                        <option value="">All DIL%</option>
                        <option value="red">Red (&lt;25%)</option>
                        <option value="green">Green (25-50%)</option>
                        <option value="pink">Pink (50%+)</option>
                    </select>
                    
                    <select id="action-filter" class="form-select form-select-sm" style="width: auto;">
                        <option value="">Show All</option>
                        <option value="RB" selected>RB</option>
                        <option value="NRB">NRB</option>
                        <option value="--">-- (No Action)</option>
                    </select>
                    
                    <button id="show-all-columns-btn" class="btn btn-sm btn-outline-secondary">
                        <i class="fa fa-eye"></i> Show All Columns
                    </button>
                    
                    <button id="export-btn" class="btn btn-sm btn-info">
                        <i class="fas fa-file-excel"></i> Export CSV
                    </button>
                    
                    <button id="transfer-mode-btn" class="btn btn-sm btn-primary" style="display: none;">
                        <i class="fas fa-exchange-alt"></i> Transfer Mode
                    </button>
                    
                    <button id="toggle-history-btn" class="btn btn-sm btn-secondary">
                        <i class="fas fa-history"></i> Show History
                    </button>
                </div>
            
            <!-- History Table Container (Hidden by default) -->
            <div class="card-body" id="history-table-container" style="display: none; padding: 0;">
                <div class="p-3 bg-light border-bottom">
                    <div class="d-flex align-items-center flex-wrap gap-2" style="row-gap: 0.5rem;">
                        <h5 class="mb-0 me-2"><i class="fas fa-history"></i> Transfer History</h5>
                        <span class="d-none d-md-inline bg-secondary rounded" style="width: 1px; height: 24px; margin: 0 0.25rem;" aria-hidden="true"></span>
                        <label class="mb-0 small text-muted align-middle me-1">From Parent:</label>
                        <input type="text" id="history-search-from-parent" class="form-control form-control-sm align-middle" placeholder="From Parent" style="max-width: 120px; height: 31px;">
                        <label class="mb-0 small text-muted align-middle me-1">To Parent:</label>
                        <input type="text" id="history-search-to-parent" class="form-control form-control-sm align-middle" placeholder="To Parent" style="max-width: 120px; height: 31px;">
                        <label class="mb-0 small text-muted align-middle me-1">From SKU:</label>
                        <input type="text" id="history-search-from-sku" class="form-control form-control-sm align-middle" placeholder="From SKU" autocomplete="off" style="max-width: 140px; height: 31px;">
                        <label class="mb-0 small text-muted align-middle me-1">To SKU:</label>
                        <input type="text" id="history-search-to-sku" class="form-control form-control-sm align-middle" placeholder="To SKU" autocomplete="off" style="max-width: 140px; height: 31px;">
                        <button type="button" id="history-search-clear" class="btn btn-outline-secondary btn-sm align-middle" style="height: 31px;">Clear</button>
                    </div>
                </div>
                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-bordered table-hover mb-0" id="history-table">
                        <thead class="table-dark">
                            <tr>
                                <th>From Parent</th>
                                <th>From SKU</th>
                                <th>From DIL %</th>
                                <th>From Available</th>
                                <th>From Adjust Qty</th>
                                <th>To Parent</th>
                                <th>To SKU</th>
                                <th>To DIL %</th>
                                <th>To Available</th>
                                <th>To Adjust Qty</th>
                                <th>Transferred By</th>
                                <th>Transferred At</th>
                            </tr>
                        </thead>
                        <tbody id="history-table-body">
                            <!-- Will be populated by JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="card-body" style="padding: 0;">
                <div id="stock-balance-table-wrapper" style="height: calc(100vh - 250px); display: flex; flex-direction: column;">
                    <!-- SKU Search + Inventory note & Refresh -->
                    <div class="p-2 bg-light border-bottom d-flex align-items-center gap-3 flex-wrap">
                        <button type="button" id="add-rule-btn" class="btn btn-sm btn-primary">
                            <i class="fas fa-plus"></i> Add Rule
                        </button>
                        <input type="text" id="sku-search" class="form-control" placeholder="Search SKU..." style="max-width: 220px;">
                        <span class="text-muted small">INV and Sold are from last sync. If numbers look wrong, click Refresh.</span>
                        <button type="button" id="refresh-inventory-data-btn" class="btn btn-sm btn-outline-primary" title="Reload table data from server">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                    </div>
                    <!-- Table -->
                    <div id="stock-balance-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addRuleModal" tabindex="-1" aria-labelledby="addRuleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addRuleModalLabel">Add Rule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-md-3">
                            <label class="form-label" for="rule-to-sku">SKU</label>
                            <select id="rule-to-sku" class="form-select form-select-sm"></select>
                        </div>
                        <div class="col-md-1">
                            <label class="form-label" for="rule-inv">INV</label>
                            <input type="text" id="rule-inv" class="form-control form-control-sm" readonly>
                        </div>
                        <div class="col-md-1">
                            <label class="form-label" for="rule-sold">SOLD</label>
                            <input type="text" id="rule-sold" class="form-control form-control-sm" readonly>
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">DIL%</label>
                            <div id="rule-dil" class="form-control form-control-sm bg-light" style="font-weight:600;">-</div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="rule-action">ACTION</label>
                            <select id="rule-action" class="form-select form-select-sm">
                                <option value="">--</option>
                                <option value="RB">RB</option>
                                <option value="NRB">NRB</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="rule-ratio">Ratio</label>
                            <select id="rule-ratio" class="form-select form-select-sm">
                                <option value="1:4">1:4</option>
                                <option value="1:3">1:3</option>
                                <option value="1:2">1:2</option>
                                <option value="1:1" selected>1:1</option>
                                <option value="2:1">2:1</option>
                                <option value="3:1">3:1</option>
                                <option value="4:1">4:1</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-2 mt-1">
                        <div class="col-md-3">
                            <label class="form-label" for="rule-from-sku">FROM SKU</label>
                            <select id="rule-from-sku" class="form-select form-select-sm"></select>
                        </div>
                        <div class="col-md-1">
                            <label class="form-label" for="rule-from-inv">FROM INV</label>
                            <input type="text" id="rule-from-inv" class="form-control form-control-sm" readonly>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="rule-from-sold">FROM SOLD</label>
                            <input type="text" id="rule-from-sold" class="form-control form-control-sm" readonly>
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">FROM DIL%</label>
                            <div id="rule-from-dil" class="form-control form-control-sm bg-light" style="font-weight:600;">-</div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="rule-from-qty">FROM Qty</label>
                            <input type="number" id="rule-from-qty" class="form-control form-control-sm" min="1" placeholder="Qty">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="rule-to-qty">TO Qty</label>
                            <input type="text" id="rule-to-qty" class="form-control form-control-sm" readonly placeholder="Calc" style="background-color:#d4edda; font-weight:bold;">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="save-rule-btn">Save Rule</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="lastUpdateModal" tabindex="-1" aria-labelledby="lastUpdateModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="lastUpdateModalLabel">Last Update</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <dl class="row mb-0">
                        <dt class="col-4">SKU</dt>
                        <dd class="col-8" id="lu-sku"></dd>
                        <dt class="col-4">Direction</dt>
                        <dd class="col-8" id="lu-direction"></dd>
                        <dt class="col-4">Qty</dt>
                        <dd class="col-8" id="lu-qty"></dd>
                        <dt class="col-4" id="lu-other-label">Other SKU</dt>
                        <dd class="col-8" id="lu-other"></dd>
                        <dt class="col-4">When</dt>
                        <dd class="col-8" id="lu-date"></dd>
                        <dt class="col-4">By</dt>
                        <dd class="col-8 mb-0" id="lu-by"></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script>
    let table = null;
    let transferModeActive = false;
    let selectedSkus = new Set();
    let allTableData = [];
    let serverSavedPreferences = {}; // FROM SKU & ratio per to_sku (shared — latest save from any user)
    let rulesBySku = {}; // transfer rules keyed by destination SKU
    let restoringFromSku = false; // batch restore; skip side effects
    let isApplyingFilters = false; // prevent filter↔render infinite loop
    
    // Toast notification (optional delay in ms; default 5000)
    function showToast(message, type = 'info', delayMs = 5000) {
        const toastContainer = document.querySelector('.toast-container');
        if (!toastContainer) return;
        
        const toast = document.createElement('div');
        let toastClass = 'toast align-items-center text-white border-0';
        
        if (type === 'success') {
            toastClass += ' toast-success-big';
        } else if (type === 'error') {
            toastClass += ' bg-danger';
        } else {
            toastClass += ' bg-info';
        }
        
        toast.className = toastClass;
        toast.setAttribute('role', 'alert');
        toast.innerHTML = '<div class="d-flex"><div class="toast-body">' + message + '</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>';
        toastContainer.appendChild(toast);
        const bsToast = new bootstrap.Toast(toast, { delay: typeof delayMs === 'number' ? delayMs : 5000 });
        bsToast.show();
        toast.addEventListener('hidden.bs.toast', function() { toast.remove(); });
    }
    
    $(document).ready(function() {
        // Select all checkbox handler (works with filtered data)
        $(document).on('change', '#select-all-checkbox', function() {
            const isChecked = $(this).prop('checked');
            const filteredData = table.getData('active'); // Get only filtered/visible data
            
            if (isChecked) {
                // Add all filtered SKUs to selection
                filteredData.forEach(function(row) {
                    selectedSkus.add(row.SKU);
                });
            } else {
                // Remove all filtered SKUs from selection
                filteredData.forEach(function(row) {
                    selectedSkus.delete(row.SKU);
                });
            }
            
            // Update checkboxes in visible rows
            table.redraw(true);
            updateBulkActionsPanel();
        });
        
        // Individual checkbox handler
        $(document).on('change', '.sku-checkbox', function() {
            const sku = $(this).data('sku');
            if ($(this).prop('checked')) {
                selectedSkus.add(sku);
            } else {
                selectedSkus.delete(sku);
                // Uncheck select-all if any item is unchecked
                $('#select-all-checkbox').prop('checked', false);
            }
            updateBulkActionsPanel();
        });
        
        // Update bulk actions panel visibility
        function updateBulkActionsPanel() {
            const count = selectedSkus.size;
            $('#selected-count').text(count + ' SKU' + (count !== 1 ? 's' : '') + ' selected');
            
            if (count > 0) {
                $('#bulk-actions-panel').slideDown(200);
            } else {
                $('#bulk-actions-panel').slideUp(200);
            }
        }
        
        // Clear selection button
        $('#clear-selection').on('click', function() {
            selectedSkus.clear();
            $('#select-all-checkbox').prop('checked', false);
            table.redraw(true);
            updateBulkActionsPanel();
        });

        // Apply Shopify pull data to one SKU in Tabulator + allTableData
        function applyStockBalanceRefreshData(sku, data) {
            if (!sku || !data) return;
            const patch = {
                INV: data.INV != null ? data.INV : 0,
                SOLD: data.SOLD != null ? data.SOLD : 0,
                DIL: data.DIL != null ? data.DIL : 0
            };
            const key = normalizeSkuKey(sku);
            if (table) {
                table.getRows().forEach(function(row) {
                    if (normalizeSkuKey(row.getData().SKU) === key) {
                        row.update(patch);
                    }
                });
            }
            allTableData.forEach(function(i) {
                if (normalizeSkuKey(i.SKU) === key) {
                    Object.assign(i, patch);
                }
            });
        }

        // Bulk pull inventory for selected SKUs
        $('#bulk-pull-inventory').on('click', function() {
            if (selectedSkus.size === 0) {
                showToast('No SKUs selected', 'error');
                return;
            }

            const skuArray = Array.from(selectedSkus);
            if (!confirm('Pull latest inventory from Shopify for ' + skuArray.length + ' selected SKU(s)?')) {
                return;
            }

            const $btn = $(this);
            const csrfToken = $('meta[name="csrf-token"]').attr('content');
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Pulling...');

            $.ajax({
                url: '/stock-balance-refresh-shopify-bulk',
                type: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Content-Type': 'application/json'
                },
                data: JSON.stringify({
                    skus: skuArray,
                    _token: csrfToken
                }),
                success: function(response) {
                    if (!response.success || !response.data || !response.data.items) {
                        showToast(response.message || 'Failed to pull inventory.', 'error');
                        return;
                    }
                    const items = response.data.items;
                    Object.keys(items).forEach(function(sku) {
                        applyStockBalanceRefreshData(sku, items[sku]);
                    });
                    // Redraw so INV/SOLD/DIL formatters refresh on visible rows
                    table.redraw(true);
                    showToast(response.message || 'Inventory pulled for selected SKUs.', 'success', 4000);
                },
                error: function(xhr) {
                    let errorMsg = 'Failed to pull inventory for selected SKUs.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    showToast(errorMsg, 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).html('<i class="fas fa-sync-alt"></i> Pull Inventory');
                }
            });
        });
        
        // Bulk action buttons
        $('#bulk-action-blank').on('click', function() {
            bulkUpdateAction('');
        });
        
        $('#bulk-action-rb').on('click', function() {
            bulkUpdateAction('RB');
        });
        
        $('#bulk-action-nrb').on('click', function() {
            bulkUpdateAction('NRB');
        });
        
        // Bulk update ACTION for selected SKUs
        function bulkUpdateAction(actionValue) {
            if (selectedSkus.size === 0) {
                showToast('No SKUs selected', 'error');
                return;
            }
            
            const skuArray = Array.from(selectedSkus);
            const actionText = actionValue === '' ? '--' : actionValue;
            
            if (!confirm('Update ACTION to "' + actionText + '" for ' + skuArray.length + ' selected SKU(s)?')) {
                return;
            }
            
            // Update all selected SKUs
            let updated = 0;
            let errors = 0;
            
            skuArray.forEach(function(sku) {
                $.ajax({
                    url: '/stock-balance-update-action',
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        'Content-Type': 'application/json'
                    },
                    data: JSON.stringify({
                        sku: sku,
                        action: actionValue || null
                    }),
                    success: function(response) {
                        updated++;
                        // Update in table
                        const rows = table.searchRows("SKU", "=", sku);
                        if (rows.length > 0) {
                            rows[0].update({ACTION: actionValue});
                        }
                        // Update in allTableData
                        const item = allTableData.find(function(i) { return i.SKU === sku; });
                        if (item) {
                            item.ACTION = actionValue;
                        }
                        
                        // Show success after all updates
                        if (updated === skuArray.length) {
                            showToast('Updated ' + updated + ' SKU(s) to ' + actionText, 'success');
                            table.redraw(true);
                        }
                    },
                    error: function(xhr) {
                        errors++;
                        if (updated + errors === skuArray.length) {
                            showToast('Updated ' + updated + ' SKU(s), ' + errors + ' failed', 'warning');
                        }
                    }
                });
            });
        }
        
        // Copy SKU button handler
        $(document).on('click', '.copy-sku-icon', function(e) {
            e.stopPropagation();
            const sku = $(this).data('sku');
            const $icon = $(this);
            
            navigator.clipboard.writeText(sku).then(function() {
                // Success - change icon temporarily
                const originalClass = $icon.attr('class');
                $icon.removeClass('fa-copy').addClass('fa-check text-success');
                
                setTimeout(function() {
                    $icon.attr('class', originalClass);
                }, 2000);
                
                showToast('Copied: ' + sku, 'success');
            }).catch(function(err) {
                showToast('Failed to copy SKU', 'error');
                console.error('Copy failed:', err);
            });
        });
        
        // History toggle functionality
        $('#toggle-history-btn').on('click', function() {
            const $container = $('#history-table-container');
            const isVisible = $container.is(':visible');
            
            if (isVisible) {
                $container.slideUp(300);
                $(this).html('<i class="fas fa-history"></i> Show History');
            } else {
                $container.slideDown(300);
                $(this).html('<i class="fas fa-history"></i> Hide History');
                // Load history data if not already loaded
                loadHistoryData();
            }
        });
        
        let lastHistoryData = [];

        // Load history data
        function loadHistoryData() {
            $.ajax({
                url: '/stock-balance-data-list',
                method: 'GET',
                success: function(response) {
                    lastHistoryData = response.data || [];
                    filterAndRenderHistoryTable();
                },
                error: function(xhr) {
                    lastHistoryData = [];
                    $('#history-table-body').html('<tr><td colspan="12" class="text-center text-danger">Error loading history</td></tr>');
                    console.error('Error loading history:', xhr);
                }
            });
        }

        // Normalize string for search: trim, lowercase, collapse spaces
        function normalizeForSearch(s) {
            return String(s || '').toLowerCase().trim().replace(/\s+/g, ' ');
        }

        // Filter history by from/to parent and from/to SKU search, then render
        function filterAndRenderHistoryTable() {
            const fromParentVal = normalizeForSearch($('#history-search-from-parent').val());
            const toParentVal = normalizeForSearch($('#history-search-to-parent').val());
            const fromSkuVal = normalizeForSearch($('#history-search-from-sku').val());
            const toSkuVal = normalizeForSearch($('#history-search-to-sku').val());
            const filtered = lastHistoryData.filter(function(item) {
                const fromParent = normalizeForSearch(item.from_parent_name);
                const toParent = normalizeForSearch(item.to_parent_name);
                const fromSku = normalizeForSearch(item.from_sku);
                const toSku = normalizeForSearch(item.to_sku);
                const matchFromParent = !fromParentVal || fromParent.indexOf(fromParentVal) !== -1;
                const matchToParent = !toParentVal || toParent.indexOf(toParentVal) !== -1;
                const matchFromSku = !fromSkuVal || fromSku.indexOf(fromSkuVal) !== -1;
                const matchToSku = !toSkuVal || toSku.indexOf(toSkuVal) !== -1;
                return matchFromParent && matchToParent && matchFromSku && matchToSku;
            });
            if (filtered.length > 0) {
                renderHistoryTable(filtered);
            } else {
                $('#history-table-body').html('<tr><td colspan="12" class="text-center">' + (lastHistoryData.length === 0 ? 'No transfer history found' : 'No rows match the search') + '</td></tr>');
            }
        }

        // Transfer History: search inputs (delegated so they work when panel is shown)
        $(document).on('input', '#history-search-from-parent, #history-search-to-parent, #history-search-from-sku, #history-search-to-sku', function() {
            filterAndRenderHistoryTable();
        });
        $(document).on('click', '#history-search-clear', function() {
            $('#history-search-from-parent').val('');
            $('#history-search-to-parent').val('');
            $('#history-search-from-sku').val('');
            $('#history-search-to-sku').val('');
            filterAndRenderHistoryTable();
        });
        
        // Render history table
        function renderHistoryTable(data) {
            let html = '';
            data.forEach(function(item) {
                html += '<tr>' +
                    '<td>' + (item.from_parent_name || '-') + '</td>' +
                    '<td><strong>' + (item.from_sku || '-') + '</strong></td>' +
                    '<td>' + (item.from_dil_percent != null ? item.from_dil_percent + '%' : '-') + '</td>' +
                    '<td>' + (item.from_available_qty || '-') + '</td>' +
                    '<td class="text-danger"><strong>' + (item.from_adjust_qty || '-') + '</strong></td>' +
                    '<td>' + (item.to_parent_name || '-') + '</td>' +
                    '<td><strong>' + (item.to_sku || '-') + '</strong></td>' +
                    '<td>' + (item.to_dil_percent != null ? item.to_dil_percent + '%' : '-') + '</td>' +
                    '<td>' + (item.to_available_qty || '-') + '</td>' +
                    '<td class="text-success"><strong>' + (item.to_adjust_qty || '-') + '</strong></td>' +
                    '<td>' + (item.transferred_by || '-') + '</td>' +
                    '<td>' + (item.transferred_at || '-') + '</td>' +
                    '</tr>';
            });
            $('#history-table-body').html(html);
        }

        // Transfer Mode Toggle (like BestBuy Decrease/Increase mode)
        $('#transfer-mode-btn').on('click', function() {
            transferModeActive = !transferModeActive;
            
            if (transferModeActive) {
                $(this).removeClass('btn-primary').addClass('btn-danger').html('<i class="fas fa-exchange-alt"></i> Transfer ON');
                // Show transfer columns
                table.getColumn('Parent').show();
                table.getColumn('from_qty').show();
                table.getColumn('to_sku').show();
                table.getColumn('to_parent').show();
                table.getColumn('from_inv_display').show();
                table.getColumn('from_sold_display').show();
                table.getColumn('from_dil_display').show();
                table.getColumn('to_qty_calc').show();
                table.getColumn('ratio').show();
                table.getColumn('submit').show();
                // Reduce ACTION column width to save space
                table.getColumn('ACTION').updateDefinition({width: 36});
                
                // Restore saved FROM SKU and ratio text
                setTimeout(function() {
                    restoreSavedFromSku();
                }, 100);
            } else {
                $(this).removeClass('btn-danger').addClass('btn-primary').html('<i class="fas fa-exchange-alt"></i> Transfer Mode');
                // Hide transfer columns
                table.getColumn('Parent').hide();
                table.getColumn('from_qty').hide();
                table.getColumn('to_sku').hide();
                table.getColumn('to_parent').hide();
                table.getColumn('from_inv_display').hide();
                table.getColumn('from_sold_display').hide();
                table.getColumn('from_dil_display').hide();
                table.getColumn('to_qty_calc').hide();
                table.getColumn('ratio').hide();
                table.getColumn('submit').hide();
                // Restore ACTION column width
                table.getColumn('ACTION').updateDefinition({width: 36});
            }
        });
        
        // ACTION dropdown change handler (save to database)
        $(document).on('change', '.action-select', function() {
            const $select = $(this);
            const sku = $select.data('sku');
            const action = $select.val();
            
            // Update styling based on selection
            if (action === 'RB') {
                $select.css('background-color', '#28a745');
            } else if (action === 'NRB') {
                $select.css('background-color', '#dc3545');
            } else {
                $select.css('background-color', '#6c757d');
            }
            
            // Save to database
            $.ajax({
                url: '/stock-balance-update-action',
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'Content-Type': 'application/json'
                },
                data: JSON.stringify({
                    sku: sku,
                    action: action || null
                }),
                success: function(response) {
                    showToast('ACTION updated for ' + sku, 'success');
                    // Update the row data in table
                    const rows = table.searchRows("SKU", "=", sku);
                    if (rows.length > 0) {
                        rows[0].update({ACTION: action});
                    }
                },
                error: function(xhr) {
                    showToast('Failed to update ACTION for ' + sku, 'error');
                    // Revert the dropdown
                    const oldValue = allTableData.find(function(i) { return i.SKU === sku; })?.ACTION || '';
                    $select.val(oldValue);
                }
            });
        });
        
        function normalizeSkuKey(sku) {
            return String(sku || '').trim().toUpperCase().replace(/\s+/g, ' ');
        }

        // DIL is stored as ratio (e.g. 14.6 => 1460%). Always convert like the column formatter.
        function dilToPercent(dil) {
            const n = parseFloat(dil);
            if (isNaN(n)) return 0;
            return n * 100;
        }

        function inventoryNumber(value) {
            if (value == null || value === '') return null;
            const n = Number(value);
            return isNaN(n) ? null : n;
        }

        function getFromSkuMeta(fromSku) {
            const key = normalizeSkuKey(fromSku);
            const fromItem = allTableData.find(function(i) {
                return normalizeSkuKey(i.SKU) === key;
            });
            const inv = fromItem ? inventoryNumber(fromItem.INV) : null;
            return {
                inv: inv == null ? 0 : inv,
                sold: fromItem ? (fromItem.SOLD || 0) : 0,
                dil: fromItem ? (parseFloat(fromItem.DIL) || 0) : 0,
                parent: fromItem ? (fromItem.Parent || '') : '',
                found: !!fromItem
            };
        }

        function setFromDilDisplay($row, fromDil) {
            const fromDilPercent = Math.round((parseFloat(fromDil) || 0) * 100);
            const $dilSpan = $row.find('.from-dil-percent');
            let dilClass = '';
            if (fromDilPercent < 25) dilClass = 'dil-red';
            else if (fromDilPercent >= 25 && fromDilPercent < 50) dilClass = 'dil-green';
            else dilClass = 'dil-pink';
            $dilSpan.attr('class', 'from-dil-percent ' + dilClass).text(fromDilPercent + '%');
        }

        const transferSaveTimers = {};

        function persistTransferInputs(toSku, fromSku, ratio, fromQty) {
            if (!toSku) return;
            let savedData = {};
            try {
                savedData = JSON.parse(localStorage.getItem('transfer_' + toSku) || '{}');
            } catch (e) {
                savedData = {};
            }
            savedData.fromSku = fromSku || '';
            savedData.ratio = ratio || savedData.ratio || '1:1';
            savedData.fromQty = (fromQty === '' || fromQty == null) ? null : (parseInt(fromQty, 10) || 0);
            localStorage.setItem('transfer_' + toSku, JSON.stringify(savedData));
            serverSavedPreferences[toSku] = serverSavedPreferences[toSku] || {};
            serverSavedPreferences[toSku].fromSku = savedData.fromSku;
            serverSavedPreferences[toSku].ratio = savedData.ratio;
            serverSavedPreferences[toSku].fromQty = savedData.fromQty;
            clearTimeout(transferSaveTimers[toSku]);
            transferSaveTimers[toSku] = setTimeout(function() {
                $.post('/stock-balance-transfer-preferences', {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    to_sku: toSku,
                    from_sku: savedData.fromSku,
                    ratio: savedData.ratio,
                    from_qty: savedData.fromQty == null ? '' : savedData.fromQty
                });
            }, 350);
        }

        function displayedFromSku($row, rowData) {
            const text = String($row.find('.from-sku-display').text() || '').trim();
            return text || (rowData && rowData._from_sku) || '';
        }

        function displayedRatio($row, rowData) {
            const text = String($row.find('.ratio-display').text() || '').trim();
            return text || (rowData && rowData._ratio) || '1:1';
        }

        function setRatioDisplay($row, ratio) {
            $row.find('.ratio-display').text(ratio || '');
        }

        function applyFromSkuToRow($row, row, fromSku, options) {
            options = options || {};
            const silent = !!options.silent;
            const toSku = row.getData().SKU;
            fromSku = fromSku || '';
            $row.find('.from-sku-display').text(fromSku);

            if (fromSku) {
                const meta = getFromSkuMeta(fromSku);
                const fromParent = meta.parent || '';
                const fromInv = meta.found ? meta.inv : 0;

                $row.find('.to-parent-display').val(fromParent);
                $row.find('.from-inv-display').text(meta.found ? String(fromInv) : '');
                $row.find('.from-sold-display').text(meta.sold);
                const data = row.getData();
                const savedQty = savedTransferQty(toSku);
                const keepQty = !!options.keepQty && data._from_qty != null && data._from_qty !== '';
                const qtyToShow = keepQty ? data._from_qty : (savedQty != null ? savedQty : fromInv);
                $row.find('.from-qty-input').val(qtyToShow);
                setFromDilDisplay($row, meta.dil);

                data._from_qty = qtyToShow;
                data._from_dil = meta.dil;
                data._from_sku = fromSku;
                if (!silent) {
                    persistTransferInputs(toSku, fromSku, displayedRatio($row, data), qtyToShow);
                }
            } else {
                $row.find('.to-parent-display').val('');
                $row.find('.from-inv-display').text('');
                $row.find('.from-sold-display').text('');
                $row.find('.from-qty-input').val('');
                $row.find('.from-dil-percent').attr('class', 'from-dil-percent').text('-');
                const data = row.getData();
                data._from_qty = null;
                data._from_dil = null;
                data._from_sku = null;
                if (!silent) {
                    persistTransferInputs(toSku, '', displayedRatio($row, data), null);
                }
            }
            updateSubmitButton($row);
        }

        function transferIsBlocked(fromQtyRaw, fromInvRaw) {
            const fromQty = parseInt(fromQtyRaw, 10);
            const fromInv = parseInt(fromInvRaw, 10);
            return String(fromQtyRaw ?? '').trim() !== '' && String(fromInvRaw ?? '').trim() !== '' && !isNaN(fromQty) && !isNaN(fromInv) && fromInv < fromQty;
        }

        function updateSubmitButton($row) {
            if (!$row || !$row.length || !table) return;
            const rowComp = table.getRow($row[0]);
            if (!rowComp) return;
            const fromQtyRaw = String($row.find('.from-qty-input').val() ?? '').trim();
            const fromInvRaw = String($row.find('.from-inv-display').text() || '').trim();
            const blocked = transferIsBlocked(fromQtyRaw, fromInvRaw);
            const recent = recentlySubmitted(rowComp.getData().SKU);
            const $btn = $row.find('.submit-transfer-btn');
            $btn.toggleClass('submit-blocked', blocked);
            $btn.toggleClass('submit-recent', recent && !blocked);
            $btn.attr('title', blocked ? 'FROM SKU INV is less than FROM Qty' : (recent ? 'Submitted in the last 24 hours' : 'Execute Transfer'));
            $btn.find('i').attr('class', blocked ? 'fas fa-times' : 'fas fa-check');
        }

        function transferFieldsForSku(sku) {
            const rowData = allTableData.find(function(item) { return item.SKU === sku; });
            if (!rowData) return null;
            let $row = $();
            if (table) {
                const matches = table.searchRows('SKU', '=', sku);
                if (matches.length) {
                    const el = matches[0].getElement();
                    if (el) $row = $(el);
                }
            }
            const hasDom = $row.length && $row.find('.from-qty-input').length;
            const fromSku = hasDom ? displayedFromSku($row, rowData) : (rowData._from_sku || '');
            const fromQtyRaw = hasDom
                ? String($row.find('.from-qty-input').val() ?? '').trim()
                : (rowData._from_qty == null || rowData._from_qty === '' ? '' : String(rowData._from_qty));
            const meta = getFromSkuMeta(fromSku);
            const fromInvRaw = hasDom
                ? String($row.find('.from-inv-display').text() || '').trim()
                : (fromSku ? String(meta.inv) : '');
            const ratio = hasDom ? displayedRatio($row, rowData) : (rowData._ratio || '1:1');
            const fromQty = parseInt(fromQtyRaw, 10) || 0;
            let toQty = 0;
            if (hasDom) {
                toQty = parseInt($row.find('.to-qty-display').val(), 10) || 0;
            } else if (fromQty > 0) {
                const parts = String(ratio).split(':');
                toQty = Math.round(fromQty * (parseFloat(parts[1]) / parseFloat(parts[0])));
            }
            return {
                sku: sku,
                blocked: transferIsBlocked(fromQtyRaw, fromInvRaw),
                fromSku: fromSku,
                fromQty: fromQty,
                toQty: toQty,
                ratio: ratio,
                fromParent: hasDom ? ($row.find('.to-parent-display').val() || meta.parent || '') : (meta.parent || ''),
                toParent: rowData.Parent || '',
                toInv: parseInt(rowData.INV, 10) || 0,
                toDil: parseFloat(rowData.DIL) || 0,
                fromInv: meta.found ? (parseInt(meta.inv, 10) || 0) : (parseInt(fromInvRaw, 10) || 0),
                fromDil: meta.dil || 0
            };
        }

        function validateTransferFields(fields) {
            if (!fields) return 'Row not found';
            if (fields.blocked) return 'FROM SKU INV is less than FROM Qty';
            if (!fields.fromSku) return 'Missing FROM SKU';
            if (fields.fromQty <= 0) return 'FROM Qty must be greater than 0';
            if (fields.toQty <= 0) return 'TO Qty must be greater than 0';
            if (fields.fromQty > fields.fromInv) return 'Insufficient inventory for ' + fields.fromSku + '. Available: ' + fields.fromInv;
            return '';
        }

        function postTransfer(fields) {
            return $.ajax({
                url: '{{ route("stock.balance.store") }}',
                method: 'POST',
                data: {
                    to_sku: fields.sku,
                    to_parent_name: fields.toParent,
                    to_available_qty: fields.toInv,
                    to_dil_percent: fields.toDil * 100,
                    to_adjust_qty: fields.toQty,
                    from_sku: fields.fromSku,
                    from_parent_name: fields.fromParent,
                    from_available_qty: fields.fromInv,
                    from_dil_percent: fields.fromDil * 100,
                    from_adjust_qty: fields.fromQty,
                    ratio: fields.ratio,
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                timeout: 120000
            });
        }

        function reloadAfterTransfers() {
            return table.setData().then(function() {
                setTimeout(function() {
                    restoreSavedFromSku();
                }, 100);
            });
        }

        function markSubmitted(sku) {
            if (!sku) return;
            let map = {};
            try {
                map = JSON.parse(localStorage.getItem('stock_balance_submitted_at') || '{}');
            } catch (e) {
                map = {};
            }
            map[normalizeSkuKey(sku)] = Date.now();
            localStorage.setItem('stock_balance_submitted_at', JSON.stringify(map));
        }

        function recentlySubmitted(sku) {
            let map = {};
            try {
                map = JSON.parse(localStorage.getItem('stock_balance_submitted_at') || '{}');
            } catch (e) {
                map = {};
            }
            const key = normalizeSkuKey(sku);
            const at = map[key];
            if (!at) return false;
            if (Date.now() - at > 24 * 60 * 60 * 1000) {
                delete map[key];
                localStorage.setItem('stock_balance_submitted_at', JSON.stringify(map));
                return false;
            }
            return true;
        }

        function pullInventoryForSkus(skus) {
            const list = [];
            (skus || []).forEach(function(sku) {
                const value = String(sku || '').trim();
                if (value && list.indexOf(value) === -1) list.push(value);
            });
            if (!list.length) {
                reloadAfterTransfers();
                return;
            }
            const csrfToken = $('meta[name="csrf-token"]').attr('content');
            let index = 0;
            function next() {
                if (index >= list.length) {
                    reloadAfterTransfers();
                    showToast('Inventory updated from Shopify.', 'success', 3000);
                    return;
                }
                const sku = list[index++];
                $.ajax({
                    url: '/stock-balance-refresh-shopify',
                    type: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                    data: { sku: sku, _token: csrfToken }
                }).done(function(response) {
                    if (response && response.success && response.data) {
                        applyStockBalanceRefreshData(sku, response.data);
                    }
                }).always(next);
            }
            next();
        }

        function scheduleInventoryPull(skus, startedAt) {
            const elapsed = startedAt ? (Date.now() - startedAt) : 0;
            const wait = Math.max(0, 5000 - elapsed);
            setTimeout(function() {
                pullInventoryForSkus(skus);
            }, wait);
        }

        // FROM Qty input change handler — do not re-filter (that re-sorts and jumps the row)
        $(document).on('input', '.from-qty-input', function() {
            if (restoringFromSku) return;
            const $row = $(this).closest('.tabulator-row');
            const row = table.getRow($row[0]);
            const raw = String($(this).val() ?? '').trim();
            const qty = raw === '' ? null : (parseInt(raw, 10) || 0);
            if (row) {
                const data = row.getData();
                data._from_qty = qty;
                persistTransferInputs(
                    data.SKU,
                    displayedFromSku($row, data),
                    displayedRatio($row, data),
                    qty
                );
            }
            calculateToQty($row);
            updateSubmitButton($row);
            if ($row.find('.submit-transfer-btn').hasClass('submit-blocked')) {
                applyAllFilters();
            }
        });
        
        // Calculate TO Qty based on FROM Qty × Ratio
        function calculateToQty($row) {
            const fromQty = parseInt($row.find('.from-qty-input').val()) || 0;
            const rowComp = table ? table.getRow($row[0]) : null;
            const ratio = displayedRatio($row, rowComp ? rowComp.getData() : null);
            
            if (fromQty > 0) {
                const ratioParts = ratio.split(':');
                const toQty = Math.round(fromQty * (parseFloat(ratioParts[1]) / parseFloat(ratioParts[0])));
                $row.find('.to-qty-display').val(toQty);
            } else {
                $row.find('.to-qty-display').val('');
            }
        }
        
        // Submit transfer button handler (inline in table). Red rows are blocked.
        $(document).on('click', '.submit-transfer-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const $btn = $(this);
            if ($btn.prop('disabled')) return;
            const $row = $btn.closest('.tabulator-row');
            const row = table.getRow($row[0]);
            if (!row) return;
            if ($btn.hasClass('submit-blocked')) {
                showToast('This row cannot be submitted. FROM SKU INV is less than FROM Qty.', 'error');
                return;
            }

            if (selectedSkus.size > 0) {
                submitSelectedTransfers();
                return;
            }

            const fields = transferFieldsForSku(row.getData().SKU);
            const err = validateTransferFields(fields);
            if (err) {
                showToast(err, 'error');
                return;
            }

            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
            const startedAt = Date.now();
            postTransfer(fields).done(function(response) {
                showToast(response.message || 'Transfer successful!', 'success');
                markSubmitted(fields.sku);
                scheduleInventoryPull([fields.sku, fields.fromSku], startedAt);
            }).fail(function(xhr) {
                const resp = xhr.responseJSON || {};
                const errorMsg = resp.error || 'Transfer failed';
                const details = resp.details || '';
                const isRateLimit = (xhr.status === 429) || (resp.is_rate_limit === true);
                const fullMsg = isRateLimit
                    ? (errorMsg + (details ? '<br><br>' + details : '') + '<br><br><em>Wait 1–2 minutes then click Submit again.</em>')
                    : (errorMsg + (details ? '<br>' + details : ''));
                showToast(fullMsg, 'error', isRateLimit ? 12000 : 5000);
            }).always(function() {
                $btn.prop('disabled', false);
                updateSubmitButton($row);
            });
        });

        function submitSelectedTransfers() {
            if (selectedSkus.size === 0) {
                showToast('Select at least one yellow row to submit.', 'error');
                return;
            }

            const ready = [];
            let skippedRed = 0;
            const invalid = [];
            Array.from(selectedSkus).forEach(function(sku) {
                const fields = transferFieldsForSku(sku);
                if (!fields) return;
                if (fields.blocked) {
                    skippedRed++;
                    return;
                }
                const err = validateTransferFields(fields);
                if (err) {
                    invalid.push(sku);
                    return;
                }
                ready.push(fields);
            });

            if (!ready.length) {
                showToast('No yellow rows to submit. Red rows are blocked.', 'error');
                return;
            }

            let index = 0;
            let ok = 0;
            const failed = [];
            const pulled = [];
            const startedAt = Date.now();

            function finish() {
                if (pulled.length) scheduleInventoryPull(pulled, startedAt);
                else reloadAfterTransfers();
                let msg = 'Submitted ' + ok + ' transfer' + (ok === 1 ? '' : 's') + '.';
                if (skippedRed) msg += ' Skipped ' + skippedRed + ' red row' + (skippedRed === 1 ? '' : 's') + '.';
                if (invalid.length) msg += ' Skipped ' + invalid.length + ' invalid row' + (invalid.length === 1 ? '' : 's') + '.';
                if (failed.length) msg += '<br>' + failed.join('<br>');
                showToast(msg, failed.length ? 'error' : 'success', 6000);
            }

            function next() {
                if (index >= ready.length) {
                    finish();
                    return;
                }
                const fields = ready[index++];
                postTransfer(fields).done(function() {
                    ok++;
                    markSubmitted(fields.sku);
                    pulled.push(fields.sku, fields.fromSku);
                    next();
                }).fail(function(xhr) {
                    const resp = xhr.responseJSON || {};
                    failed.push(fields.sku + ': ' + (resp.error || 'Transfer failed'));
                    next();
                });
            }

            next();
        }
        
        // Initialize Tabulator
        table = new Tabulator("#stock-balance-table", {
            ajaxURL: "/stock-balance-inventory-data",
            ajaxResponse: function(url, params, response) {
                allTableData = response.data || [];
                return response.data || [];
            },
            dataLoaded: function() {
                // Fetch server-saved preferences and rules so FROM SKU, ratio, and qty stay shared
                $.get('/stock-balance-transfer-preferences').always(function(res) {
                    if (res && res.preferences && typeof res.preferences === 'object') {
                        serverSavedPreferences = res.preferences;
                    }
                    $.get('/stock-balance-rules').always(function(ruleRes) {
                        if (ruleRes && ruleRes.rules && typeof ruleRes.rules === 'object') {
                            rulesBySku = ruleRes.rules;
                        }
                        enrichTransferMetaFromPreferences();
                        applyAllFilters();
                        restoreSavedFromSku();
                        refreshRuleButtons();
                    });
                });
            },
            layout: "fitDataStretch",
            pagination: true,
            paginationSize: 50,
            paginationSizeSelector: [25, 50, 100, 200],
            paginationCounter: "rows",
            initialSort: [{
                column: "DIL",
                dir: "desc"
            }],
            rowFormatter: function(row) {
                const sku = String((row.getData() || {}).SKU || '').toUpperCase().trim();
                const el = row.getElement();
                if (sku.startsWith('PARENT')) {
                    el.classList.add('parent-row');
                } else {
                    el.classList.remove('parent-row');
                }
            },
            columns: [
                {
                    title: "<input type='checkbox' id='select-all-checkbox'>",
                    field: "_select",
                    hozAlign: "center",
                    headerSort: false,
                    width: 40,
                    visible: true,
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sku = rowData.SKU;
                        const isChecked = selectedSkus.has(sku) ? 'checked' : '';
                        return '<input type="checkbox" class="sku-checkbox" data-sku="' + sku + '" ' + isChecked + '>';
                    }
                },
                {
                    title: "Image",
                    field: "IMAGE_URL",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        return value ? '<img src="' + value + '" style="width:40px;height:40px;object-fit:cover;">' : '';
                    },
                    headerSort: false,
                    width: 60
                },
                {
                    title: "Parent",
                    field: "Parent",
                    headerFilter: "input",
                    width: 150,
                    frozen: true,
                    visible: false
                },
                {
                    title: "SKU",
                    field: "SKU",
                    headerFilter: "input",
                    frozen: true,
                    width: 230,
                    cssClass: "fw-bold",
                    formatter: function(cell) {
                        const sku = cell.getValue();
                        return '<span>' + sku + '</span>' +
                            '<i class="fa fa-copy text-secondary copy-sku-icon" ' +
                            'style="cursor: pointer; margin-left: 8px; font-size: 14px;" ' +
                            'data-sku="' + sku + '" ' +
                            'title="Copy SKU"></i>';
                    }
                },
                {
                    title: "INV",
                    field: "INV",
                    hozAlign: "center",
                    sorter: "number",
                    width: 60
                },
                {
                    title: "SOLD",
                    field: "SOLD",
                    hozAlign: "center",
                    sorter: "number",
                    width: 60
                },
                {
                    title: "DIL%",
                    field: "DIL",
                    hozAlign: "center",
                    sorter: "number",
                    width: 70,
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue()) || 0;
                        if (value <= 0) return '<span>-</span>';
                        
                        const percent = Math.round(value * 100);
                        let className = '';
                        
                        if (percent < 25) className = 'dil-red';
                        else if (percent >= 25 && percent < 50) className = 'dil-green';
                        else className = 'dil-pink';
                        
                        return '<span class="' + className + '">' + percent + '%</span>';
                    }
                },
                {
                    title: "ACTION",
                    field: "ACTION",
                    hozAlign: "center",
                    width: 36,
                    headerSort: false,
                    formatter: function(cell) {
                        const value = cell.getValue() || '';
                        let selectBg = '#6c757d';

                        if (value === 'RB') {
                            selectBg = '#28a745';
                        } else if (value === 'NRB') {
                            selectBg = '#dc3545';
                        }

                        return '<select class="form-select action-select" data-sku="' + cell.getRow().getData().SKU + '" title="' + (value || '--') + '" style="background-color:' + selectBg + ';">' +
                            '<option value="" ' + (value === '' ? 'selected' : '') + '>--</option>' +
                            '<option value="RB" ' + (value === 'RB' ? 'selected' : '') + '>RB</option>' +
                            '<option value="NRB" ' + (value === 'NRB' ? 'selected' : '') + '>NRB</option>' +
                            '</select>';
                    },
                    cellClick: function(e, cell) {
                        e.stopPropagation();
                    }
                },
              
                {
                    title: "TO SKU",
                    field: "from_sku_display",
                    hozAlign: "center",
                    width: 150,
                    visible: false,
                    cssClass: "fw-bold text-success",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        return '<div style="padding: 6px; background-color: #d4edda; border-radius: 4px; font-weight: bold;">' + rowData.SKU + '</div>';
                    }
                },
                {
                    title: "FROM SKU",
                    field: "to_sku",
                    hozAlign: "center",
                    width: 180,
                    visible: true,
                    cssClass: "from-sku-column",
                    formatter: function(cell) {
                        return '<span class="from-sku-display"></span>';
                    }
                },
                {
                    title: "INV",
                    field: "from_inv_display",
                    hozAlign: "center",
                    width: 48,
                    visible: true,
                    headerSort: false,
                    formatter: function(cell) {
                        return '<span class="from-inv-display"></span>';
                    }
                },
                {
                    title: "FROM Parent",
                    field: "to_parent",
                    hozAlign: "center",
                    width: 120,
                    visible: false,
                    formatter: function(cell) {
                        return '<input type="text" class="form-control form-control-sm to-parent-display" readonly style="width:110px;">';
                    }
                },
                {
                    title: "FROM SOLD",
                    field: "from_sold_display",
                    hozAlign: "center",
                    width: 48,
                    visible: true,
                    formatter: function(cell) {
                        return '<span class="from-sold-display"></span>';
                    }
                },
                {
                    title: "FROM DIL%",
                    field: "from_dil_display",
                    hozAlign: "center",
                    width: 80,
                    visible: true,
                    formatter: function(cell) {
                        return '<span class="from-dil-percent" style="font-weight:600;">-</span>';
                    }
                },
                {
                    title: "FROM Qty",
                    field: "from_qty",
                    hozAlign: "center",
                    width: 48,
                    visible: true,
                    formatter: function(cell) {
                        return '<input type="number" class="form-control form-control-sm from-qty-input" min="1" value="" placeholder="">';
                    }
                },
                {
                    title: "TO Qty",
                    field: "to_qty_calc",
                    hozAlign: "center",
                    width: 48,
                    visible: true,
                    formatter: function(cell) {
                        return '<input type="text" class="form-control form-control-sm to-qty-display" readonly placeholder="">';
                    }
                },
                {
                    title: "Ratio",
                    field: "ratio",
                    hozAlign: "center",
                    width: 52,
                    visible: true,
                    formatter: function(cell) {
                        return '<span class="ratio-display"></span>';
                    }
                },
                {
                    title: "Submit",
                    field: "submit",
                    hozAlign: "center",
                    width: 70,
                    visible: true,
                    headerSort: false,
                    formatter: function(cell) {
                        return '<button class="btn submit-transfer-btn" title="Execute Transfer"><i class="fas fa-check"></i></button>';
                    }
                },
                {
                    title: "Rule",
                    field: "rule",
                    hozAlign: "center",
                    width: 40,
                    visible: true,
                    headerSort: false,
                    formatter: function(cell) {
                        const sku = cell.getRow().getData().SKU || '';
                        const escAttr = function(s) { return String(s || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;'); };
                        const hasRule = !!getRuleForSku(sku);
                        const cls = hasRule ? ' has-rule' : '';
                        return '<button type="button" class="btn btn-sm rule-edit-btn edit-rule-btn' + cls + '" data-sku="' + escAttr(sku) + '" title="Edit Rule"><i class="fas fa-pen"></i></button>';
                    }
                },
                {
                    title: "Last Updt",
                    field: "LAST_UPDATE",
                    hozAlign: "center",
                    width: 48,
                    headerSort: false,
                    formatter: function(cell) {
                        const data = cell.getValue();
                        if (!data) {
                            return '<span class="text-muted">—</span>';
                        }
                        const color = data.direction === 'IN' ? '#28a745' : '#dc3545';
                        const label = data.direction === 'IN' ? 'Stock in' : 'Stock out';
                        return '<button type="button" class="last-update-dot" style="background-color:' + color + ';" title="' + label + '" aria-label="' + label + '"></button>';
                    }
                },
            ]
        });
        
        $(document).on('click', '.last-update-dot', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const $row = $(this).closest('.tabulator-row');
            const row = table.getRow($row[0]);
            if (!row) return;
            const rowData = row.getData();
            const data = rowData.LAST_UPDATE;
            if (!data) return;

            const incoming = data.direction === 'IN';
            $('#lu-sku').text(rowData.SKU || '');
            $('#lu-direction').text(incoming ? 'IN' : 'OUT').css('color', incoming ? '#28a745' : '#dc3545');
            $('#lu-qty').text(data.qty != null ? data.qty : '');
            $('#lu-other-label').text(incoming ? 'From SKU' : 'To SKU');
            $('#lu-other').text(data.other_sku || '');
            $('#lu-date').text(data.date || '');
            $('#lu-by').text(data.by || '');

            const modalEl = document.getElementById('lastUpdateModal');
            if (modalEl && modalEl.parentElement !== document.body) {
                document.body.appendChild(modalEl);
            }
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        });

        // SKU Search — go through applyAllFilters so Row / DIL / Action stay applied
        $('#sku-search').on('input', function() {
            applyAllFilters();
        });

        // Refresh inventory data (reload from server so INV/Sold are up to date)
        $('#refresh-inventory-data-btn').on('click', function() {
            const $btn = $(this);
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
            table.setData().then(function() {
                $btn.prop('disabled', false).html('<i class="fas fa-sync-alt"></i> Refresh');
                showToast('Table data refreshed', 'success', 3000);
            }).catch(function() {
                $btn.prop('disabled', false).html('<i class="fas fa-sync-alt"></i> Refresh');
                showToast('Failed to refresh data', 'error');
            });
        });
        
        function isParentRow(data) {
            return String((data && data.SKU) || '').toUpperCase().trim().startsWith('PARENT');
        }

        // Filters
        $('#row-filter').on('change', function() {
            applyAllFilters();
        });
        
        $('#dil-filter').on('change', function() {
            applyAllFilters();
        });
        
        $('#action-filter').on('change', function() {
            applyAllFilters();
        });
        
        function getRuleForSku(sku) {
            if (!sku || !rulesBySku) return null;
            if (rulesBySku[sku]) return rulesBySku[sku];
            const key = normalizeSkuKey(sku);
            const found = Object.keys(rulesBySku).find(function(k) {
                return normalizeSkuKey(k) === key;
            });
            return found ? rulesBySku[found] : null;
        }

        function getServerPref(sku) {
            if (!sku || !serverSavedPreferences) return null;
            if (serverSavedPreferences[sku]) return serverSavedPreferences[sku];
            const key = normalizeSkuKey(sku);
            const found = Object.keys(serverSavedPreferences).find(function(k) {
                return normalizeSkuKey(k) === key;
            });
            return found ? serverSavedPreferences[found] : null;
        }

        function savedTransferQty(toSku) {
            const pref = getServerPref(toSku);
            if (pref && pref.fromQty != null && pref.fromQty !== '') {
                return parseInt(pref.fromQty, 10) || 0;
            }
            let saved = {};
            try {
                saved = JSON.parse(localStorage.getItem('transfer_' + toSku) || '{}');
            } catch (e) {
                saved = {};
            }
            if (saved.fromQty != null && saved.fromQty !== '') {
                return parseInt(saved.fromQty, 10) || 0;
            }
            const rule = getRuleForSku(toSku);
            if (rule && rule.fromQty != null && rule.fromQty !== '') {
                return parseInt(rule.fromQty, 10) || 0;
            }
            return null;
        }

        function savedTransferQtyForRow(rowData) {
            const saved = savedTransferQty(rowData.SKU);
            if (saved != null) return saved;
            const history = historyAutofill(rowData);
            if (history && history.fromQty != null) return history.fromQty;
            return null;
        }

        function refreshRuleButtons() {
            $('.edit-rule-btn').each(function() {
                const hasRule = !!getRuleForSku($(this).attr('data-sku'));
                $(this).toggleClass('has-rule', hasRule);
            });
        }

        function historyAutofill(rowData) {
            const history = rowData && rowData.LAST_UPDATE;
            if (!history || history.direction !== 'IN') return null;
            const fromSku = history.from_sku || history.other_sku || '';
            if (!fromSku || normalizeSkuKey(fromSku) === normalizeSkuKey(rowData.SKU)) return null;
            const fromQty = parseInt(history.from_qty, 10);
            const shownQty = parseInt(history.qty, 10);
            return {
                fromSku: fromSku,
                fromQty: fromQty > 0 ? fromQty : (shownQty > 0 ? shownQty : null),
                ratio: history.ratio || '1:1'
            };
        }

        function resolveFromSkuForRow(rowData) {
            if (rowData._from_sku) return rowData._from_sku;
            const toSku = rowData.SKU;
            const serverPref = getServerPref(toSku);
            if (serverPref && serverPref.fromSku) return serverPref.fromSku;
            const rule = getRuleForSku(toSku);
            if (rule && rule.fromSku) return rule.fromSku;
            const history = historyAutofill(rowData);
            if (history && history.fromSku) return history.fromSku;
            const savedData = JSON.parse(localStorage.getItem('transfer_' + toSku) || '{}');
            const savedFromSku = savedData.fromSku || null;
            return savedFromSku || null;
        }

        function rowSubmitBlocked(data) {
            if (!data) return false;
            const fromSku = data._from_sku || resolveFromSkuForRow(data);
            if (!fromSku) return false;
            const meta = getFromSkuMeta(fromSku);
            let fromQty = data._from_qty;
            if (fromQty == null || fromQty === '') {
                const saved = savedTransferQtyForRow(data);
                fromQty = saved != null ? saved : meta.inv;
            }
            return transferIsBlocked(fromQty, meta.inv);
        }

        // Apply all filters together (guarded to avoid renderComplete recursion)
        function applyAllFilters() {
            if (!table || isApplyingFilters || restoringFromSku) return;
            isApplyingFilters = true;
            try {
                const rowVal = $('#row-filter').val() || 'sku';
                const dilVal = $('#dil-filter').val();
                const actionVal = $('#action-filter').val();
                const searchVal = ($('#sku-search').val() || '').trim().toLowerCase();

                table.setFilter(function(data) {
                    if (searchVal) {
                        const sku = String(data.SKU || '').toLowerCase();
                        const parent = String(data.Parent || '').toLowerCase();
                        if (sku.indexOf(searchVal) === -1 && parent.indexOf(searchVal) === -1) return false;
                    }

                    const parent = isParentRow(data);
                    if (rowVal === 'sku' && parent) return false;
                    if (rowVal === 'parent' && !parent) return false;

                    if (dilVal) {
                        const dil = dilToPercent(data.DIL);
                        if (dilVal === 'red'    && !(dil < 25)) return false;
                        if (dilVal === 'green' && !(dil >= 25 && dil < 50)) return false;
                        if (dilVal === 'pink' && !(dil >= 50)) return false;
                    }

                    if (actionVal) {
                        if (actionVal === '--') {
                            if (data.ACTION && data.ACTION !== '') return false;
                        } else if (data.ACTION !== actionVal) {
                            return false;
                        }
                    }

                    if (rowSubmitBlocked(data)) return false;

                    return true;
                });
            } finally {
                setTimeout(function() {
                    isApplyingFilters = false;
                    restoreSavedFromSku();
                }, 0);
            }
        }

        // Precompute FROM SKU fields on data objects (same refs Tabulator uses)
        function enrichTransferMetaFromPreferences() {
            allTableData.forEach(function(data) {
                const rule = getRuleForSku(data.SKU);
                const pref = getServerPref(data.SKU);
                const history = historyAutofill(data);
                const fromSku = resolveFromSkuForRow(data);
                if (fromSku) {
                    const meta = getFromSkuMeta(fromSku);
                    const savedQty = savedTransferQtyForRow(data);
                    data._from_sku = fromSku;
                    data._from_qty = savedQty != null ? savedQty : meta.inv;
                    data._from_dil = meta.dil;
                } else {
                    data._from_sku = null;
                    data._from_qty = null;
                    data._from_dil = null;
                }
                data._ratio = (pref && pref.ratio) || (rule && rule.ratio) || (history && history.ratio) || null;
            });
        }
        
        // Export CSV
        $('#export-btn').on('click', function() {
            table.download("csv", "stock_balance_export.csv");
        });
        
        // Show all columns
        $('#show-all-columns-btn').on('click', function() {
            table.getColumns().forEach(function(col) {
                if (col.getField() !== '_select') {
                    col.show();
                }
            });
        });
        
        table.on('dataLoaded', function() {
            applyAllFilters();
        });
        
        // Restore saved FROM SKU/ratio into visible DOM only (no filter apply — avoids stack overflow)
        function restoreSavedFromSku() {
            if (!table || restoringFromSku || isApplyingFilters) return;
            restoringFromSku = true;
            try {
                $('.from-sku-display').each(function() {
                    const $row = $(this).closest('.tabulator-row');
                    const row = table.getRow($row[0]);
                    if (!row) return;
                    const rowData = row.getData();
                    const toSku = rowData.SKU;
                    const serverPref = getServerPref(toSku);
                    const savedData = JSON.parse(localStorage.getItem('transfer_' + toSku) || '{}');
                    const savedFromSku = savedData.fromSku || null;
                    const savedRatio = savedData.ratio || '1:1';
                    const rule = getRuleForSku(toSku);
                    const history = historyAutofill(rowData);
                    const fromSkuToRestore = rowData._from_sku || (serverPref && serverPref.fromSku) || (rule && rule.fromSku) || (history && history.fromSku) || resolveFromSkuForRow(rowData);
                    const ratioToRestore = rowData._ratio || (serverPref && serverPref.ratio) || (rule && rule.ratio) || (history && history.ratio) || savedRatio || '1:1';
                    const savedQty = savedTransferQtyForRow(rowData);
                    if (savedQty != null) {
                        rowData._from_qty = savedQty;
                    }
                    rowData._ratio = ratioToRestore;
                    setRatioDisplay($row, ratioToRestore);
                    if (fromSkuToRestore) {
                        applyFromSkuToRow($row, row, fromSkuToRestore, { silent: true, keepQty: true });
                        if (!savedFromSku && fromSkuToRestore && !(serverPref && serverPref.fromSku)) {
                            savedData.fromSku = fromSkuToRestore;
                            localStorage.setItem('transfer_' + toSku, JSON.stringify(savedData));
                        }
                    }
                    calculateToQty($row);
                });
            } finally {
                restoringFromSku = false;
            }
        }
        
        // Re-fill transfer inputs after Tabulator redraws cells (DOM only)
        table.on('renderComplete', function() {
            if (restoringFromSku || isApplyingFilters) return;
            restoreSavedFromSku();
            refreshRuleButtons();
        });

        let ruleModalFilling = false;

        function skuSelectOptions(excludeSku) {
            const exclude = normalizeSkuKey(excludeSku);
            const esc = function(s) {
                return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            };
            let html = '<option value=""></option>';
            allTableData.forEach(function(item) {
                if (!item.SKU || normalizeSkuKey(item.SKU).indexOf('PARENT') !== -1) return;
                if (exclude && normalizeSkuKey(item.SKU) === exclude) return;
                html += '<option value="' + esc(item.SKU) + '">' + esc(item.SKU) + '</option>';
            });
            return html;
        }

        function setDilBox($el, dil) {
            const n = parseFloat(dil);
            const percent = Math.round((isNaN(n) ? 0 : n) * 100);
            let cls = 'form-control form-control-sm bg-light';
            if (isNaN(n) || percent <= 0) {
                $el.attr('class', cls).text('-');
                return;
            }
            if (percent < 25) cls += ' dil-red';
            else if (percent < 50) cls += ' dil-green';
            else cls += ' dil-pink';
            $el.attr('class', cls).text(percent + '%');
        }

        function calcRuleToQty() {
            const fromQty = parseInt($('#rule-from-qty').val(), 10) || 0;
            const ratio = $('#rule-ratio').val() || '1:1';
            if (fromQty > 0) {
                const parts = ratio.split(':');
                const toQty = Math.round(fromQty * (parseFloat(parts[1]) / parseFloat(parts[0])));
                $('#rule-to-qty').val(toQty);
            } else {
                $('#rule-to-qty').val('');
            }
        }

        function destroyRuleSelect($el) {
            if ($el.hasClass('select2-hidden-accessible')) {
                $el.select2('destroy');
            }
        }

        function initRuleSelect($el, placeholder) {
            $el.select2({
                dropdownParent: $('#addRuleModal'),
                width: '100%',
                placeholder: placeholder,
                allowClear: !$el.prop('disabled')
            });
        }

        function fillRuleToSkuDetails(sku) {
            const item = allTableData.find(function(i) {
                return normalizeSkuKey(i.SKU) === normalizeSkuKey(sku);
            });
            if (!item) {
                $('#rule-inv, #rule-sold').val('');
                setDilBox($('#rule-dil'), null);
                return null;
            }
            $('#rule-inv').val(item.INV != null ? item.INV : '');
            $('#rule-sold').val(item.SOLD != null ? item.SOLD : '');
            setDilBox($('#rule-dil'), item.DIL);
            return item;
        }

        function fillRuleFromSkuDetails(fromSku, keepQty) {
            if (!fromSku) {
                $('#rule-from-inv, #rule-from-sold').val('');
                setDilBox($('#rule-from-dil'), null);
                if (!keepQty) $('#rule-from-qty').val('');
                calcRuleToQty();
                return;
            }
            const meta = getFromSkuMeta(fromSku);
            $('#rule-from-inv').val(meta.found ? String(meta.inv) : '');
            $('#rule-from-sold').val(meta.found ? meta.sold : '');
            setDilBox($('#rule-from-dil'), meta.found ? meta.dil : null);
            if (!keepQty) $('#rule-from-qty').val(meta.inv > 0 ? meta.inv : '');
            calcRuleToQty();
        }

        function openRuleModal(options) {
            const ruleModalEl = document.getElementById('addRuleModal');
            if (ruleModalEl && ruleModalEl.parentElement !== document.body) {
                document.body.appendChild(ruleModalEl);
            }
            options = options || {};
            const lockedSku = options.sku || '';
            const editing = !!lockedSku;
            ruleModalFilling = true;
            $('#addRuleModalLabel').text(editing ? 'Edit Rule' : 'Add Rule');
            $('#save-rule-btn').text(editing ? 'Update Rule' : 'Save Rule');

            const $to = $('#rule-to-sku');
            const $from = $('#rule-from-sku');
            destroyRuleSelect($to);
            destroyRuleSelect($from);
            $to.html(skuSelectOptions('')).prop('disabled', editing);
            $from.html(skuSelectOptions(lockedSku));

            const rule = lockedSku ? getRuleForSku(lockedSku) : null;
            if (lockedSku && $to.find('option').filter(function() { return $(this).val() === lockedSku; }).length === 0) {
                $to.append($('<option>', { value: lockedSku, text: lockedSku }));
            }
            $to.val(lockedSku);

            const itemForHistory = lockedSku ? allTableData.find(function(i) {
                return normalizeSkuKey(i.SKU) === normalizeSkuKey(lockedSku);
            }) : null;
            const history = itemForHistory ? historyAutofill(itemForHistory) : null;
            const fromSku = (history && history.fromSku) || (rule && rule.fromSku) || (itemForHistory && itemForHistory._from_sku) || '';
            const ratio = (history && history.ratio) || (rule && rule.ratio) || (itemForHistory && itemForHistory._ratio) || '1:1';
            const historyQty = history && history.fromQty != null && history.fromQty !== '' ? history.fromQty : null;
            const ruleQty = rule && rule.fromQty != null && rule.fromQty !== '' ? rule.fromQty : null;
            const fromQty = historyQty != null ? historyQty : ruleQty;

            function optionValueForSku($select, sku) {
                if (!sku) return '';
                const key = normalizeSkuKey(sku);
                let matched = '';
                $select.find('option').each(function() {
                    if (normalizeSkuKey($(this).val()) === key) matched = $(this).val();
                });
                if (!matched) {
                    $select.append($('<option>', { value: sku, text: sku }));
                    matched = sku;
                }
                return matched;
            }

            const fromSkuValue = optionValueForSku($from, fromSku);
            $from.val(fromSkuValue);
            $('#rule-ratio').val(ratio);

            initRuleSelect($to, 'Search SKU...');
            initRuleSelect($from, 'Search FROM SKU...');

            const item = lockedSku ? fillRuleToSkuDetails(lockedSku) : null;
            const action = (rule && rule.action) || (item && item.ACTION) || '';
            $('#rule-action').val(action);
            if (fromSkuValue) {
                fillRuleFromSkuDetails(fromSkuValue, fromQty != null);
                if (fromQty != null) $('#rule-from-qty').val(fromQty);
                calcRuleToQty();
            } else {
                $('#rule-from-inv, #rule-from-sold, #rule-from-qty, #rule-to-qty').val('');
                setDilBox($('#rule-from-dil'), null);
                if (!lockedSku) {
                    $('#rule-inv, #rule-sold').val('');
                    setDilBox($('#rule-dil'), null);
                    $('#rule-action').val('');
                }
            }

            bootstrap.Modal.getOrCreateInstance(document.getElementById('addRuleModal')).show();
            setTimeout(function() { ruleModalFilling = false; }, 0);
        }

        $('#add-rule-btn').on('click', function() {
            if (!allTableData.length) {
                showToast('Wait for the table to finish loading', 'error');
                return;
            }
            openRuleModal({});
        });

        $(document).on('click', '.edit-rule-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            openRuleModal({ sku: $(this).attr('data-sku') || '' });
        });

        $('#rule-to-sku').on('change', function() {
            if (ruleModalFilling) return;
            const sku = $(this).val() || '';
            const $from = $('#rule-from-sku');
            const currentFrom = $from.val() || '';
            destroyRuleSelect($from);
            $from.html(skuSelectOptions(sku));
            if (currentFrom && normalizeSkuKey(currentFrom) !== normalizeSkuKey(sku)) {
                $from.val(currentFrom);
            }
            initRuleSelect($from, 'Search FROM SKU...');
            const item = fillRuleToSkuDetails(sku);
            const rule = getRuleForSku(sku);
            if (rule && rule.fromSku) {
                if ($from.find('option').filter(function() { return $(this).val() === rule.fromSku; }).length === 0) {
                    destroyRuleSelect($from);
                    $from.append($('<option>', { value: rule.fromSku, text: rule.fromSku }));
                    initRuleSelect($from, 'Search FROM SKU...');
                }
                $from.val(rule.fromSku).trigger('change.select2');
                $('#rule-ratio').val(rule.ratio || '1:1');
                const keepQty = rule.fromQty != null && rule.fromQty !== '';
                fillRuleFromSkuDetails(rule.fromSku, keepQty);
                if (keepQty) $('#rule-from-qty').val(rule.fromQty);
                $('#rule-action').val(rule.action || (item && item.ACTION) || '');
                calcRuleToQty();
            } else {
                fillRuleFromSkuDetails($from.val(), false);
                $('#rule-action').val((item && item.ACTION) || '');
                $('#rule-ratio').val('1:1');
            }
        });

        $('#rule-from-sku').on('change', function() {
            if (ruleModalFilling) return;
            fillRuleFromSkuDetails($(this).val(), false);
        });

        $('#rule-ratio, #rule-from-qty').on('input change', calcRuleToQty);

        $('#save-rule-btn').on('click', function() {
            const $btn = $(this);
            const toSku = $('#rule-to-sku').val();
            const fromSku = $('#rule-from-sku').val();
            const ratio = $('#rule-ratio').val() || '1:1';
            const fromQtyRaw = String($('#rule-from-qty').val() || '').trim();
            const fromQty = fromQtyRaw === '' ? '' : parseInt(fromQtyRaw, 10);
            const action = $('#rule-action').val() || '';

            if (!toSku) {
                showToast('Select a SKU for the rule', 'error');
                return;
            }
            if (!fromSku) {
                showToast('Select a FROM SKU', 'error');
                return;
            }
            if (normalizeSkuKey(toSku) === normalizeSkuKey(fromSku)) {
                showToast('FROM SKU must be different from SKU', 'error');
                return;
            }
            if (fromQty !== '' && (!fromQty || fromQty < 1)) {
                showToast('FROM Qty must be at least 1', 'error');
                return;
            }

            const item = allTableData.find(function(i) {
                return normalizeSkuKey(i.SKU) === normalizeSkuKey(toSku);
            });
            const previousAction = item ? (item.ACTION || '') : '';

            $btn.prop('disabled', true);
            $.ajax({
                url: '/stock-balance-rules',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                data: {
                    to_sku: toSku,
                    from_sku: fromSku,
                    ratio: ratio,
                    from_qty: fromQty,
                    action: action
                },
                success: function(res) {
                    const rule = (res && res.rule) || {
                        toSku: toSku,
                        fromSku: fromSku,
                        ratio: ratio,
                        fromQty: fromQty === '' ? null : fromQty,
                        action: action || null
                    };
                    rulesBySku[rule.toSku] = rule;
                    serverSavedPreferences[rule.toSku] = serverSavedPreferences[rule.toSku] || {};
                    serverSavedPreferences[rule.toSku].fromSku = rule.fromSku || '';
                    serverSavedPreferences[rule.toSku].ratio = rule.ratio || '1:1';
                    serverSavedPreferences[rule.toSku].fromQty = (rule.fromQty == null || rule.fromQty === '') ? null : parseInt(rule.fromQty, 10);
                    const savedData = JSON.parse(localStorage.getItem('transfer_' + rule.toSku) || '{}');
                    savedData.fromSku = rule.fromSku || '';
                    savedData.ratio = rule.ratio || '1:1';
                    savedData.fromQty = serverSavedPreferences[rule.toSku].fromQty;
                    localStorage.setItem('transfer_' + rule.toSku, JSON.stringify(savedData));

                    function applyRuleToItem(rowItem) {
                        if (!rowItem) return;
                        rowItem._from_sku = rule.fromSku;
                        rowItem._ratio = rule.ratio || '1:1';
                        if (rule.fromQty != null && rule.fromQty !== '') {
                            rowItem._from_qty = parseInt(rule.fromQty, 10);
                        }
                        rowItem.ACTION = rule.action || '';
                    }
                    applyRuleToItem(item);
                    const rows = table.getRows().filter(function(row) {
                        return normalizeSkuKey(row.getData().SKU) === normalizeSkuKey(rule.toSku);
                    });
                    if (rows.length) {
                        applyRuleToItem(rows[0].getData());
                        rows[0].reformat();
                        setTimeout(restoreSavedFromSku, 0);
                    } else {
                        refreshRuleButtons();
                    }

                    const finish = function() {
                        $btn.prop('disabled', false);
                        const modalEl = document.getElementById('addRuleModal');
                        const modal = bootstrap.Modal.getInstance(modalEl);
                        if (modal) modal.hide();
                        showToast('Rule saved for ' + rule.toSku, 'success', 3000);
                    };

                    if ((action || '') !== (previousAction || '')) {
                        $.ajax({
                            url: '/stock-balance-update-action',
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                                'Content-Type': 'application/json'
                            },
                            data: JSON.stringify({ sku: toSku, action: action || null }),
                            complete: finish
                        });
                    } else {
                        finish();
                    }
                },
                error: function(xhr) {
                    $btn.prop('disabled', false);
                    const resp = xhr.responseJSON || {};
                    let msg = resp.error || resp.message || 'Failed to save rule';
                    if (resp.errors) {
                        const first = Object.values(resp.errors)[0];
                        if (first && first[0]) msg = first[0];
                    }
                    showToast(msg, 'error');
                }
            });
        });
        
    });
</script>
@endsection
