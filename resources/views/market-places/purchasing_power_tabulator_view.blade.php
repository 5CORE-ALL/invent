@extends('layouts.vertical', ['title' => 'Purchasing Power Pricing', 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator-col .tabulator-col-sorter { display: none !important; }

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
        .tabulator .tabulator-header .tabulator-col { height: 80px !important; }
        .tabulator .tabulator-header .tabulator-col.tabulator-sortable .tabulator-col-title { padding-right: 0px !important; }
        .tabulator-paginator label { margin-right: 5px; }
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Purchasing Power Pricing',
        'sub_title'  => 'Purchasing Power Pricing',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <h4>Purchasing Power Data</h4>
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <select id="inventory-filter" class="form-select form-select-sm" style="width: auto;">
                        <option value="all">All Inventory</option>
                        <option value="zero">0 Inventory</option>
                        <option value="more" selected>More than 0</option>
                    </select>

                    <select id="nrl-filter" class="form-select form-select-sm" style="width: auto;">
                        <option value="all">All Status</option>
                        <option value="REQ" selected>REQ Only</option>
                        <option value="NR">NR Only</option>
                    </select>

                    <div class="d-flex flex-column gap-1" style="width: auto;" title="CVR = PP L30 ÷ OV L30">
                        <select id="gpft-filter" class="form-select form-select-sm" style="width: auto;">
                            <option value="all">GPFT%</option>
                            <option value="negative">Negative</option>
                            <option value="0-10">0-10%</option>
                            <option value="10-20">10-20%</option>
                            <option value="20-30">20-30%</option>
                            <option value="30-40">30-40%</option>
                            <option value="40-50">40-50%</option>
                            <option value="60plus">Above 60%</option>
                        </select>
                        <select id="cvr-filter" class="form-select form-select-sm" style="width: auto;">
                            <option value="all">All CVR%</option>
                            <option value="0-0">0%</option>
                            <option value="0-2">0-2%</option>
                            <option value="2-4">2-4%</option>
                            <option value="4-7">4-7%</option>
                            <option value="7-13">7-13%</option>
                            <option value="13plus">13%+</option>
                        </select>
                    </div>

                    <select id="roi-filter" class="form-select form-select-sm" style="width: auto;">
                        <option value="all">ROI%</option>
                        <option value="lt40">&lt; 40%</option>
                        <option value="40-75">40–75%</option>
                        <option value="75-125">75–125%</option>
                        <option value="125-175">125–175%</option>
                        <option value="175-250">175–250%</option>
                        <option value="gt250">&gt; 250%</option>
                    </select>

                    <select id="dil-filter" class="form-select form-select-sm" style="width: auto;">
                        <option value="all">All DIL%</option>
                        <option value="red">Red (&lt;16.7%)</option>
                        <option value="yellow">Yellow (16.7-25%)</option>
                        <option value="green">Green (25-50%)</option>
                        <option value="pink">Pink (50%+)</option>
                    </select>

                    <div class="dropdown d-inline-block">
                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button"
                            id="columnVisibilityDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa fa-eye"></i> Columns
                        </button>
                        <ul class="dropdown-menu" id="column-dropdown-menu"
                            style="max-height: 400px; overflow-y: auto;"></ul>
                    </div>
                    <button id="show-all-columns-btn" class="btn btn-sm btn-outline-secondary">
                        <i class="fa fa-eye"></i> Show All
                    </button>
                    <button id="export-btn" class="btn btn-sm btn-info">
                        <i class="fas fa-file-excel"></i> Export CSV
                    </button>
                    <button id="sugg-amz-prc-btn" class="btn btn-sm btn-info">
                        <i class="fas fa-copy"></i> Sugg Amz Prc
                    </button>
                    <button id="decrease-btn" class="btn btn-sm btn-warning">
                        <i class="fas fa-arrow-down"></i> Decrease Mode
                    </button>
                    <button id="increase-btn" class="btn btn-sm btn-success">
                        <i class="fas fa-arrow-up"></i> Increase Mode
                    </button>
                </div>

                <!-- Summary Stats -->
                <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                    <h6 class="mb-3">Summary ({{ $ppPercentage }}% Margin)</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-success fs-6 p-2" id="total-pft-amt-badge" style="color:black;font-weight:bold;">Total PFT: $0</span>
                        <span class="badge bg-primary fs-6 p-2" id="total-sales-amt-badge" style="color:black;font-weight:bold;">Total Sales: $0</span>
                        <span class="badge bg-info fs-6 p-2" id="avg-gpft-badge" style="color:black;font-weight:bold;">AVG GPFT: 0%</span>
                        <span class="badge bg-warning fs-6 p-2" id="avg-price-badge" style="color:black;font-weight:bold;">Avg Price: $0</span>
                        <span class="badge bg-primary fs-6 p-2" id="total-inv-badge" style="color:black;font-weight:bold;">Total INV: 0</span>
                        <span class="badge bg-success fs-6 p-2" id="total-l30-badge" style="color:black;font-weight:bold;">Total PP L30: 0</span>
                        <span class="badge bg-secondary fs-6 p-2" id="total-pp-stock-badge" style="color:white;font-weight:bold;">PP Stock: 0</span>
                        <span class="badge bg-danger fs-6 p-2" id="zero-sold-count-badge" style="color:white;font-weight:bold;cursor:pointer;" title="Click to filter 0 sold">0 Sold: 0</span>
                        <span class="badge fs-6 p-2" id="more-sold-count-badge" style="background-color:#28a745;color:white;font-weight:bold;cursor:pointer;">&gt; 0 Sold</span>
                        <span class="badge bg-warning fs-6 p-2" id="avg-dil-badge" style="color:black;font-weight:bold;">DIL%: 0%</span>
                        <span class="badge bg-info fs-6 p-2" id="total-cogs-badge" style="color:black;font-weight:bold;">COGS: $0</span>
                        <span class="badge bg-secondary fs-6 p-2" id="roi-percent-badge" style="color:black;font-weight:bold;">ROI%: 0%</span>
                        <span class="badge bg-danger fs-6 p-2" id="less-amz-badge" style="color:white;font-weight:bold;cursor:pointer;">&lt; Amz</span>
                        <span class="badge fs-6 p-2" id="more-amz-badge" style="background-color:#28a745;color:white;font-weight:bold;cursor:pointer;">&gt; Amz</span>
                        <span class="badge bg-danger fs-6 p-2" id="missing-badge" style="color:white;font-weight:bold;cursor:pointer;">MISSING: 0</span>
                        <span class="badge bg-danger fs-6 p-2" id="mapping-badge" style="color:white;font-weight:bold;cursor:pointer;">MAPPING: 0</span>
                    </div>
                </div>
            </div>

            <div class="card-body" style="padding:0;">
                <div id="discount-input-container" class="p-2 bg-light border-bottom" style="display:none;">
                    <div class="d-flex align-items-center gap-2">
                        <span id="selected-skus-count" class="fw-bold"></span>
                        <select id="discount-type-select" class="form-select form-select-sm" style="width:120px;">
                            <option value="percentage">Percentage</option>
                            <option value="value">Value ($)</option>
                        </select>
                        <input type="number" id="discount-percentage-input" class="form-control form-control-sm"
                            placeholder="Enter %" step="0.01" style="width:100px;">
                        <button id="apply-discount-btn" class="btn btn-primary btn-sm">Apply</button>
                        <button id="clear-sprice-btn" class="btn btn-danger btn-sm">
                            <i class="fas fa-eraser"></i> Clear SPRICE
                        </button>
                    </div>
                </div>
                <div id="pp-table-wrapper" style="height:calc(100vh - 200px);display:flex;flex-direction:column;">
                    <div class="p-2 bg-light border-bottom">
                        <input type="text" id="sku-search" class="form-control" placeholder="Search SKU...">
                    </div>
                    <div id="pp-table" style="flex:1;"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script>
    const COLUMN_VIS_KEY = "pp_tabulator_column_visibility";
    let table = null;
    let decreaseModeActive = false;
    let increaseModeActive = false;
    let selectedSkus = new Set();

    function showToast(message, type = 'info') {
        const toastContainer = document.querySelector('.toast-container');
        if (!toastContainer) return;
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-white bg-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info'} border-0`;
        toast.setAttribute('role', 'alert');
        toast.innerHTML = `<div class="d-flex"><div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
        toastContainer.appendChild(toast);
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();
        toast.addEventListener('hidden.bs.toast', () => toast.remove());
    }

    $(document).ready(function() {

        $('#discount-type-select').on('change', function() {
            $('#discount-percentage-input').attr('placeholder', $(this).val() === 'percentage' ? 'Enter %' : 'Enter $');
        });

        $('#decrease-btn').on('click', function() {
            decreaseModeActive = !decreaseModeActive;
            increaseModeActive = false;
            const selectColumn = table.getColumn('_select');
            if (decreaseModeActive) {
                $(this).removeClass('btn-warning').addClass('btn-danger').html('<i class="fas fa-arrow-down"></i> Decrease ON');
                selectColumn.show();
                $('#increase-btn').removeClass('btn-danger').addClass('btn-success').html('<i class="fas fa-arrow-up"></i> Increase Mode');
            } else {
                $(this).removeClass('btn-danger').addClass('btn-warning').html('<i class="fas fa-arrow-down"></i> Decrease Mode');
                selectColumn.hide(); selectedSkus.clear(); updateSelectedCount();
            }
        });

        $('#increase-btn').on('click', function() {
            increaseModeActive = !increaseModeActive;
            decreaseModeActive = false;
            const selectColumn = table.getColumn('_select');
            if (increaseModeActive) {
                $(this).removeClass('btn-success').addClass('btn-danger').html('<i class="fas fa-arrow-up"></i> Increase ON');
                selectColumn.show();
                $('#decrease-btn').removeClass('btn-danger').addClass('btn-warning').html('<i class="fas fa-arrow-down"></i> Decrease Mode');
            } else {
                $(this).removeClass('btn-danger').addClass('btn-success').html('<i class="fas fa-arrow-up"></i> Increase Mode');
                selectColumn.hide(); selectedSkus.clear(); updateSelectedCount();
            }
        });

        $(document).on('change', '#select-all-checkbox', function() {
            const isChecked = $(this).prop('checked');
            table.getData('active').filter(r => !(r.Parent && r.Parent.startsWith('PARENT'))).forEach(r => {
                isChecked ? selectedSkus.add(r['(Child) sku']) : selectedSkus.delete(r['(Child) sku']);
            });
            $('.sku-select-checkbox').each(function() { $(this).prop('checked', selectedSkus.has($(this).data('sku'))); });
            updateSelectedCount();
        });

        $(document).on('change', '.sku-select-checkbox', function() {
            const sku = $(this).data('sku');
            $(this).prop('checked') ? selectedSkus.add(sku) : selectedSkus.delete(sku);
            updateSelectedCount(); updateSelectAllCheckbox();
        });

        $('#apply-discount-btn').on('click', function() { applyDiscount(); });
        $('#discount-percentage-input').on('keypress', function(e) { if (e.which === 13) applyDiscount(); });
        $('#sugg-amz-prc-btn').on('click', function() { applySuggestAmazonPrice(); });
        $('#clear-sprice-btn').on('click', function() { clearSpriceForSelected(); });

        let zeroSoldFilterActive = false, moreSoldFilterActive = false;
        let lessAmzFilterActive = false, moreAmzFilterActive = false;
        let missingFilterActive = false, mappingFilterActive = false;

        $('#zero-sold-count-badge').on('click', function() { zeroSoldFilterActive = !zeroSoldFilterActive; moreSoldFilterActive = false; applyFilters(); });
        $('#more-sold-count-badge').on('click', function() { moreSoldFilterActive = !moreSoldFilterActive; zeroSoldFilterActive = false; applyFilters(); });
        $('#less-amz-badge').on('click', function() { lessAmzFilterActive = !lessAmzFilterActive; moreAmzFilterActive = false; applyFilters(); });
        $('#more-amz-badge').on('click', function() { moreAmzFilterActive = !moreAmzFilterActive; lessAmzFilterActive = false; applyFilters(); });
        $('#missing-badge').on('click', function() { missingFilterActive = !missingFilterActive; mappingFilterActive = false; applyFilters(); });
        $('#mapping-badge').on('click', function() { mappingFilterActive = !mappingFilterActive; missingFilterActive = false; applyFilters(); });

        function updateSelectedCount() {
            const count = selectedSkus.size;
            $('#selected-skus-count').text(`${count} SKU${count !== 1 ? 's' : ''} selected`);
            $('#discount-input-container').toggle(count > 0);
        }

        function updateSelectAllCheckbox() {
            if (!table) return;
            const filteredSkus = new Set(table.getData('active').filter(r => !(r.Parent && r.Parent.startsWith('PARENT'))).map(r => r['(Child) sku']).filter(s => s));
            $('#select-all-checkbox').prop('checked', filteredSkus.size > 0 && [...filteredSkus].every(s => selectedSkus.has(s)));
        }

        function roundToRetailPrice(price) { return Math.ceil(price) - 0.01; }

        function applyDiscount() {
            const discountType  = $('#discount-type-select').val();
            const discountValue = parseFloat($('#discount-percentage-input').val());
            if (isNaN(discountValue) || discountValue === 0) { showToast('Enter a valid discount value', 'error'); return; }
            if (selectedSkus.size === 0) { showToast('Select at least one SKU', 'error'); return; }

            let updatedCount = 0;
            const updates = [];

            selectedSkus.forEach(sku => {
                const rows = table.searchRows('(Child) sku', '=', sku);
                if (!rows.length) return;
                const row = rows[0], rowData = row.getData();
                const currentPrice = parseFloat(rowData['PP Price']) || 0;
                if (currentPrice <= 0) return;

                let newSprice;
                if (discountType === 'percentage') {
                    newSprice = decreaseModeActive ? currentPrice * (1 - discountValue / 100) : currentPrice * (1 + discountValue / 100);
                } else {
                    newSprice = decreaseModeActive ? currentPrice - discountValue : currentPrice + discountValue;
                }
                newSprice = Math.max(0.99, roundToRetailPrice(newSprice));

                const percentage = {{ $ppPercentage }} / 100;
                const lp   = parseFloat(rowData['LP_productmaster']) || 0;
                const ship = parseFloat(rowData['Ship_productmaster']) || 0;
                const sgpft = newSprice > 0 ? Math.round(((newSprice * percentage - lp - ship) / newSprice) * 10000) / 100 : 0;
                const sroi  = lp > 0    ? Math.round(((newSprice * percentage - lp - ship) / lp) * 10000) / 100 : 0;

                row.update({ SPRICE: newSprice, SGPFT: sgpft, SPFT: sgpft, SROI: sroi });
                updates.push({ sku, sprice: newSprice });
                updatedCount++;
            });

            if (updates.length) saveSpriceUpdates(updates);
            showToast(`${decreaseModeActive ? 'Decrease' : 'Increase'} applied to ${updatedCount} SKU(s)`, 'success');
            $('#discount-percentage-input').val('');
        }

        function applySuggestAmazonPrice() {
            if (selectedSkus.size === 0) { showToast('Select SKUs first', 'error'); return; }
            let updatedCount = 0, noAmzCount = 0;
            const updates = [];
            const percentage = {{ $ppPercentage }} / 100;

            selectedSkus.forEach(sku => {
                const rows = table.searchRows('(Child) sku', '=', sku);
                if (!rows.length) { noAmzCount++; return; }
                const row = rows[0], rowData = row.getData();
                const amazonPrice = parseFloat(rowData['A Price']);
                if (!amazonPrice || amazonPrice <= 0) { noAmzCount++; return; }

                const lp   = parseFloat(rowData['LP_productmaster']) || 0;
                const ship = parseFloat(rowData['Ship_productmaster']) || 0;
                const sgpft = Math.round(((amazonPrice * percentage - lp - ship) / amazonPrice) * 10000) / 100;
                const sroi  = lp > 0 ? Math.round(((amazonPrice * percentage - lp - ship) / lp) * 10000) / 100 : 0;
                row.update({ SPRICE: amazonPrice, SGPFT: sgpft, SPFT: sgpft, SROI: sroi });
                updates.push({ sku, sprice: amazonPrice });
                updatedCount++;
            });

            if (updates.length) saveSpriceUpdates(updates);
            let msg = `Amazon price applied to ${updatedCount} SKU(s)`;
            if (noAmzCount) msg += ` (${noAmzCount} had no Amazon price)`;
            showToast(msg, updatedCount > 0 ? 'success' : 'warning');
        }

        function saveSpriceUpdates(updates) {
            $.ajax({
                url: '/pp-save-sprice-batch',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                data: { updates },
                success: function(response) {
                    if (response.success) console.log('PP SPRICE saved:', response.updated, 'records');
                },
                error: function(xhr) {
                    showToast('Error saving SPRICE: ' + (xhr.responseJSON?.error || 'Unknown'), 'error');
                }
            });
        }

        function clearSpriceForSelected() {
            if (selectedSkus.size === 0) { showToast('Select SKUs first', 'error'); return; }
            if (!confirm(`Clear SPRICE for ${selectedSkus.size} SKU(s)?`)) return;

            let clearedCount = 0;
            const updates = [];
            table.getRows().forEach(row => {
                const sku = row.getData()['(Child) sku'];
                if (!selectedSkus.has(sku)) return;
                row.update({ SPRICE: 0, SGPFT: 0, SPFT: 0, SROI: 0 });
                updates.push({ sku, sprice: 0 });
                clearedCount++;
            });
            if (updates.length) saveSpriceUpdates(updates);
            showToast(`SPRICE cleared for ${clearedCount} SKU(s)`, 'success');
        }

        function saveSpriceWithRetry(sku, sprice, row, retryCount = 0) {
            $.ajax({
                url: '/pp-save-sprice-tabulator',
                method: 'POST',
                data: { sku, sprice, _token: '{{ csrf_token() }}' },
                success: function(response) {
                    showToast(`✓ SPRICE saved: ${sku} = $${parseFloat(sprice).toFixed(2)}`, 'success');
                    if (response.spft_percent  !== undefined) row.update({ SPFT:  response.spft_percent });
                    if (response.sroi_percent  !== undefined) row.update({ SROI:  response.sroi_percent });
                    if (response.sgpft_percent !== undefined) row.update({ SGPFT: response.sgpft_percent });
                },
                error: function(xhr) {
                    if (retryCount < 3) setTimeout(() => saveSpriceWithRetry(sku, sprice, row, retryCount + 1), 2000);
                    else showToast(`Failed to save SPRICE for ${sku}`, 'error');
                }
            });
        }

        // Initialize Tabulator
        table = new Tabulator('#pp-table', {
            ajaxURL: '/pp-data-json',
            ajaxSorting: false,
            layout: 'fitDataStretch',
            pagination: true,
            paginationSize: 100,
            paginationSizeSelector: [10, 25, 50, 100, 200],
            paginationCounter: 'rows',
            columnCalcs: 'both',
            langs: { default: { pagination: { page_size: 'SKU Count' } } },
            initialSort: [{ column: 'PP L30', dir: 'desc' }],
            rowFormatter: function(row) {
                if (row.getData().Parent && row.getData().Parent.startsWith('PARENT'))
                    row.getElement().style.backgroundColor = 'rgba(69, 233, 255, 0.1)';
            },
            columns: [
                { title: 'Parent', field: 'Parent', headerFilter: 'input', headerFilterPlaceholder: 'Search Parent...', cssClass: 'text-primary', tooltip: true, frozen: true, width: 150, visible: false },
                {
                    title: 'Image', field: 'image_path', headerSort: false, width: 80,
                    formatter: function(cell) {
                        const v = cell.getValue();
                        return v ? `<img src="${v}" style="width:50px;height:50px;object-fit:cover;">` : '';
                    }
                },
                {
                    title: 'SKU', field: '(Child) sku', headerFilter: 'input', headerFilterPlaceholder: 'Search SKU...',
                    cssClass: 'text-primary fw-bold', tooltip: true, frozen: true, width: 250,
                    formatter: function(cell) {
                        const sku = cell.getValue();
                        return `<span>${sku}</span><i class="fa fa-copy text-secondary copy-sku-btn" style="cursor:pointer;margin-left:8px;font-size:14px;" data-sku="${sku}" title="Copy SKU"></i>`;
                    }
                },
                { title: 'INV',  field: 'INV',  hozAlign: 'center', width: 50, sorter: 'number' },
                { title: 'OV L30', field: 'L30', hozAlign: 'center', width: 50, sorter: 'number' },
                {
                    title: 'Dil', field: 'PP Dil%', hozAlign: 'center', sorter: 'number', width: 50,
                    formatter: function(cell) {
                        const d = cell.getRow().getData();
                        const inv = parseFloat(d.INV) || 0;
                        if (inv === 0) return '<span style="color:#6c757d;">0%</span>';
                        const dil = (parseFloat(d['L30']) || 0) / inv * 100;
                        const color = dil < 16.66 ? '#a00211' : dil < 25 ? '#ffc107' : dil < 50 ? '#28a745' : '#e83e8c';
                        return `<span style="color:${color};font-weight:600;">${Math.round(dil)}%</span>`;
                    }
                },
                { title: 'PP L30',   field: 'PP L30',  hozAlign: 'center', width: 50, sorter: 'number' },
                { title: 'PP Stock', field: 'PP INV',  hozAlign: 'center', width: 60, sorter: 'number',
                    formatter: function(cell) {
                        const v = parseInt(cell.getValue()) || 0;
                        const color = v === 0 ? '#a00211' : v < 5 ? '#ffc107' : '#28a745';
                        return `<span style="color:${color};font-weight:600;">${v}</span>`;
                    }
                },
                {
                    title: 'NR/REQ', field: 'nr_req', hozAlign: 'center', headerSort: false, width: 60,
                    formatter: function(cell) {
                        const v = cell.getValue() || 'REQ';
                        return `<select class="form-select form-select-sm nr-req-dropdown" style="border:1px solid #ddd;text-align:center;cursor:pointer;padding:2px 4px;font-size:16px;width:50px;height:28px;">
                            <option value="REQ" ${v === 'REQ' ? 'selected' : ''}>🟢</option>
                            <option value="NR"  ${v === 'NR'  ? 'selected' : ''}>🔴</option>
                        </select>`;
                    },
                    cellClick: function(e) { e.stopPropagation(); }
                },
                {
                    title: 'Prc', field: 'PP Price', hozAlign: 'center', sorter: 'number', width: 70,
                    formatter: function(cell) {
                        const v = parseFloat(cell.getValue() || 0);
                        const amz = parseFloat(cell.getRow().getData()['A Price']) || 0;
                        if (v === 0) return `<span style="color:#a00211;font-weight:600;">$0.00 <i class="fas fa-exclamation-triangle"></i></span>`;
                        const color = amz > 0 ? (v < amz ? '#a00211' : v > amz ? '#28a745' : '') : '';
                        return `<span style="color:${color};font-weight:${color ? '600' : 'normal'};">$${v.toFixed(2)}</span>`;
                    }
                },
                {
                    title: 'A Price', field: 'A Price', hozAlign: 'center', sorter: 'number', width: 70,
                    formatter: function(cell) {
                        const v = parseFloat(cell.getValue());
                        return (!v || isNaN(v)) ? '<span style="color:#6c757d;">-</span>' : `$${v.toFixed(2)}`;
                    }
                },
                {
                    title: "<span style='color:#a00211;'>Missing</span>", field: 'Missing', hozAlign: 'center', width: 60,
                    formatter: function(cell) {
                        const d = cell.getRow().getData();
                        const price = parseFloat(d['PP Price']) || 0, inv = parseFloat(d.INV) || 0, nrReq = d.nr_req || 'REQ';
                        if (nrReq === 'NR' || inv === 0) return '';
                        return price === 0 ? '<span style="color:#a00211;font-weight:600;">M</span>' : '';
                    }
                },
                {
                    title: 'Mapping', field: 'Mapping', hozAlign: 'center', width: 90,
                    formatter: function(cell) {
                        const d = cell.getRow().getData();
                        const ourInv = parseFloat(d.INV) || 0, mcInv = parseFloat(d['PP INV']) || 0;
                        const price = parseFloat(d['PP Price']) || 0, nrReq = d.nr_req || 'REQ';
                        if (nrReq === 'NR' || ourInv === 0 || price === 0) return '';
                        return ourInv === mcInv
                            ? '<span style="color:#28a745;font-weight:600;background-color:#d4edda;padding:2px 6px;border-radius:3px;">MAP</span>'
                            : `<span style="color:#a00211;font-weight:600;background-color:#f8d7da;padding:2px 6px;border-radius:3px;">N MP (${Math.abs(mcInv - ourInv)})</span>`;
                    }
                },
                {
                    title: 'GPFT%', field: 'GPFT%', hozAlign: 'center', sorter: 'number', width: 50,
                    formatter: function(cell) {
                        const p = parseFloat(cell.getValue());
                        const color = p < 10 ? '#a00211' : p < 15 ? '#ffc107' : p < 20 ? '#3591dc' : p <= 40 ? '#28a745' : '#e83e8c';
                        return `<span style="color:${color};font-weight:600;">${p.toFixed(0)}%</span>`;
                    }
                },
                {
                    title: 'PFT%', field: 'PFT %', hozAlign: 'center', sorter: 'number', width: 50,
                    formatter: function(cell) {
                        const p = parseFloat(cell.getValue());
                        const color = p < 10 ? '#a00211' : p < 15 ? '#ffc107' : p < 20 ? '#3591dc' : p <= 40 ? '#28a745' : '#e83e8c';
                        return `<span style="color:${color};font-weight:600;">${p.toFixed(0)}%</span>`;
                    }
                },
                {
                    title: 'ROI%', field: 'ROI%', hozAlign: 'center', sorter: 'number', width: 50,
                    formatter: function(cell) {
                        const p = parseFloat(cell.getValue());
                        const color = p < 50 ? '#a00211' : p < 100 ? '#ffc107' : p < 150 ? '#28a745' : '#e83e8c';
                        return `<span style="color:${color};font-weight:600;">${p.toFixed(0)}%</span>`;
                    }
                },
                {
                    title: 'Profit', field: 'Profit', hozAlign: 'center', sorter: 'number', visible: false, width: 70,
                    formatter: function(cell) {
                        const v = parseFloat(cell.getValue() || 0);
                        return `<span style="color:${v >= 0 ? '#28a745' : '#a00211'};font-weight:600;">$${v.toFixed(2)}</span>`;
                    }
                },
                {
                    title: 'Sales', field: 'Sales L30', hozAlign: 'center', sorter: 'number', visible: false, width: 80,
                    formatter: function(cell) { return `$${parseFloat(cell.getValue() || 0).toFixed(2)}`; }
                },
                {
                    title: 'LP', field: 'LP_productmaster', hozAlign: 'center', sorter: 'number', visible: false, width: 60,
                    formatter: function(cell) { return `$${parseFloat(cell.getValue() || 0).toFixed(2)}`; }
                },
                {
                    title: 'Ship', field: 'Ship_productmaster', hozAlign: 'center', sorter: 'number', visible: false, width: 60,
                    formatter: function(cell) { return `$${parseFloat(cell.getValue() || 0).toFixed(2)}`; }
                },
                {
                    title: "<input type='checkbox' id='select-all-checkbox'>",
                    field: '_select', hozAlign: 'center', headerSort: false, width: 40, visible: false,
                    formatter: function(cell) {
                        const sku = cell.getRow().getData()['(Child) sku'];
                        return `<input type='checkbox' class='sku-select-checkbox' data-sku='${sku}' ${selectedSkus.has(sku) ? 'checked' : ''}>`;
                    }
                },
                {
                    title: 'SPRICE', field: 'SPRICE', hozAlign: 'center', editor: 'number',
                    editorParams: { min: 0, step: 0.01 }, sorter: 'number', width: 80,
                    formatter: function(cell) {
                        const v = parseFloat(cell.getValue() || 0);
                        const d = cell.getRow().getData();
                        let bg = '';
                        if (d.SPRICE_STATUS === 'pushed') bg = 'background-color:#fff3cd;';
                        else if (d.SPRICE_STATUS === 'applied') bg = 'background-color:#d4edda;';
                        else if (d.has_custom_sprice) bg = 'background-color:#e7f1ff;';
                        return `<span style="font-weight:600;${bg}padding:2px 6px;border-radius:3px;">$${v.toFixed(2)}</span>`;
                    }
                },
                {
                    title: 'SGPFT', field: 'SGPFT', hozAlign: 'center', sorter: 'number', width: 50,
                    formatter: function(cell) {
                        const p = parseFloat(cell.getValue());
                        const color = p < 10 ? '#a00211' : p < 15 ? '#ffc107' : p < 20 ? '#3591dc' : p <= 40 ? '#28a745' : '#e83e8c';
                        return `<span style="color:${color};font-weight:600;">${p.toFixed(0)}%</span>`;
                    }
                },
                {
                    title: 'SPFT', field: 'SPFT', hozAlign: 'center', sorter: 'number', width: 50,
                    formatter: function(cell) {
                        const p = parseFloat(cell.getValue());
                        const color = p < 10 ? '#a00211' : p < 15 ? '#ffc107' : p < 20 ? '#3591dc' : p <= 40 ? '#28a745' : '#e83e8c';
                        return `<span style="color:${color};font-weight:600;">${p.toFixed(0)}%</span>`;
                    }
                },
                {
                    title: 'SROI', field: 'SROI', hozAlign: 'center', sorter: 'number', width: 50,
                    formatter: function(cell) {
                        const p = parseFloat(cell.getValue());
                        const color = p < 50 ? '#a00211' : p < 100 ? '#ffc107' : p < 150 ? '#28a745' : '#e83e8c';
                        return `<span style="color:${color};font-weight:600;">${p.toFixed(0)}%</span>`;
                    }
                }
            ]
        });

        $('#sku-search').on('keyup', function() { table.setFilter('(Child) sku', 'like', $(this).val()); });

        $(document).on('change', '.nr-req-dropdown', function() {
            const $cell = $(this).closest('.tabulator-cell');
            const row = table.getRow($cell.closest('.tabulator-row')[0]);
            const sku = row.getData()['(Child) sku'];
            const newValue = $(this).val();
            $.ajax({
                url: '{{ url("/pp-update-nr-req") }}',
                method: 'POST',
                data: { sku, nr_req: newValue, _token: '{{ csrf_token() }}' },
                success: function() { showToast(`${sku}: updated to ${newValue}`, 'success'); row.update({ nr_req: newValue }); },
                error: function() { showToast(`Failed to update ${sku}`, 'error'); }
            });
        });

        table.on('cellEdited', function(cell) {
            if (cell.getField() !== 'SPRICE') return;
            const row = cell.getRow(), d = row.getData();
            const newSprice = parseFloat(cell.getValue()) || 0;
            const percentage = {{ $ppPercentage }} / 100;
            const lp   = d.LP_productmaster || 0;
            const ship = d.Ship_productmaster || 0;
            const sgpft = newSprice > 0 ? Math.round(((newSprice * percentage - lp - ship) / newSprice) * 10000) / 100 : 0;
            const sroi  = lp > 0 ? Math.round(((newSprice * percentage - lp - ship) / lp) * 10000) / 100 : 0;
            row.update({ SGPFT: sgpft, SPFT: sgpft, SROI: sroi, has_custom_sprice: true });

            // Auto-save immediately — no Send button needed
            saveSpriceWithRetry(d['(Child) sku'], newSprice, row);
        });

        $(document).on('click', '.copy-sku-btn', function(e) {
            e.stopPropagation();
            navigator.clipboard.writeText($(this).data('sku')).then(() => showToast(`Copied: ${$(this).data('sku')}`, 'success'));
        });

        function applyFilters() {
            const inv   = $('#inventory-filter').val();
            const nrl   = $('#nrl-filter').val();
            const gpft  = $('#gpft-filter').val();
            const cvrF  = $('#cvr-filter').val();
            const dil   = $('#dil-filter').val();
            const roi   = $('#roi-filter').val();
            table.clearFilter();

            if (inv === 'zero') table.addFilter('INV', '=', 0);
            else if (inv === 'more') table.addFilter('INV', '>', 0);

            if (nrl === 'REQ') table.addFilter('nr_req', '=', 'REQ');
            else if (nrl === 'NR') table.addFilter('nr_req', '=', 'NR');

            if (gpft !== 'all') {
                if (gpft === 'negative') table.addFilter('GPFT%', '<', 0);
                else if (gpft === '60plus') table.addFilter('GPFT%', '>=', 60);
                else { const [min, max] = gpft.split('-').map(Number); table.addFilter('GPFT%', '>=', min); table.addFilter('GPFT%', '<', max); }
            }

            if (cvrF !== 'all') {
                table.addFilter(function(d) {
                    const ov = parseFloat(d.L30) || 0;
                    const sold = parseFloat(d['PP L30']) || 0;
                    const cvrPercent = ov > 0 ? (sold / ov) * 100 : 0;
                    const cvrRounded = Math.round(cvrPercent * 100) / 100;
                    if (cvrF === '0-0') return cvrRounded === 0;
                    if (cvrF === '0-2') return cvrRounded > 0 && cvrRounded <= 2;
                    if (cvrF === '2-4') return cvrRounded > 2 && cvrRounded <= 4;
                    if (cvrF === '4-7') return cvrRounded > 4 && cvrRounded <= 7;
                    if (cvrF === '7-13') return cvrRounded > 7 && cvrRounded <= 13;
                    if (cvrF === '13plus') return cvrRounded > 13;
                    return true;
                });
            }

            // ROI% filter (same as AliExpress)
            if (roi !== 'all') {
                table.addFilter(function(d) {
                    const roiVal = parseFloat(d['ROI%']) || 0;
                    if (roi === 'lt40')  return roiVal < 40;
                    if (roi === 'gt250') return roiVal > 250;
                    const [min, max] = roi.split('-').map(Number);
                    return roiVal >= min && roiVal <= max;
                });
            }

            if (dil !== 'all') {
                table.addFilter(function(data) {
                    const inv2 = parseFloat(data.INV) || 0, l30 = parseFloat(data.L30) || 0;
                    const d2 = inv2 === 0 ? 0 : (l30 / inv2) * 100;
                    if (dil === 'red') return d2 < 16.66;
                    if (dil === 'yellow') return d2 >= 16.66 && d2 < 25;
                    if (dil === 'green') return d2 >= 25 && d2 < 50;
                    if (dil === 'pink') return d2 >= 50;
                    return true;
                });
            }

            if (zeroSoldFilterActive) table.addFilter('PP L30', '=', 0);
            if (moreSoldFilterActive) table.addFilter('PP L30', '>', 0);
            if (lessAmzFilterActive) table.addFilter(d => { const mc = parseFloat(d['PP Price']) || 0, amz = parseFloat(d['A Price']) || 0; return amz > 0 && mc > 0 && mc < amz; });
            if (moreAmzFilterActive) table.addFilter(d => { const mc = parseFloat(d['PP Price']) || 0, amz = parseFloat(d['A Price']) || 0; return amz > 0 && mc > 0 && mc > amz; });
            if (missingFilterActive) table.addFilter(d => { return (d.nr_req || 'REQ') === 'REQ' && (parseFloat(d.INV) || 0) > 0 && (parseFloat(d['PP Price']) || 0) === 0; });
            if (mappingFilterActive) table.addFilter(d => {
                const ourInv = parseFloat(d.INV) || 0, mcInv = parseFloat(d['PP INV']) || 0, price = parseFloat(d['PP Price']) || 0;
                return (d.nr_req || 'REQ') === 'REQ' && ourInv > 0 && price > 0 && ourInv !== mcInv;
            });

            updateSummary();
        }

        $('#inventory-filter, #nrl-filter, #gpft-filter, #cvr-filter, #dil-filter, #roi-filter').on('change', function() { applyFilters(); });

        function updateSummary() {
            const data = table.getData('active').filter(r => !(r.Parent && r.Parent.startsWith('PARENT')));
            let totalPft = 0, totalSales = 0, totalGpft = 0, totalPrice = 0, priceCount = 0;
            let totalInv = 0, totalL30 = 0, zeroSold = 0, totalDil = 0, dilCount = 0;
            let totalCogs = 0, totalRoi = 0, roiCount = 0, missingCount = 0, mappingCount = 0;
            let totalPpStock = 0;

            data.forEach(row => {
                totalPft   += parseFloat(row.Profit) || 0;
                totalSales += parseFloat(row['Sales L30']) || 0;
                totalGpft  += parseFloat(row['GPFT%']) || 0;

                const price = parseFloat(row['PP Price']) || 0, inv = parseFloat(row.INV) || 0, nrReq = row.nr_req || 'REQ';
                if (price > 0) { totalPrice += price; priceCount++; }
                else if (nrReq === 'REQ' && inv > 0) missingCount++;

                totalInv  += inv;
                totalL30  += parseFloat(row['PP L30']) || 0;
                if ((parseFloat(row['PP L30']) || 0) === 0) zeroSold++;

                const dil = parseFloat(row['PP Dil%']) || 0;
                if (dil > 0) { totalDil += dil; dilCount++; }

                const lp = parseFloat(row.LP_productmaster) || 0, l30 = parseFloat(row['PP L30']) || 0;
                totalCogs += lp * l30;
                const roi = parseFloat(row['ROI%']) || 0;
                if (roi !== 0) { totalRoi += roi; roiCount++; }

                if (nrReq === 'REQ' && inv > 0 && price > 0) {
                    if (inv !== (parseFloat(row['PP INV']) || 0)) mappingCount++;
                }

                totalPpStock += parseFloat(row['PP INV']) || 0;
            });

            const avgGpft = data.length > 0 ? totalGpft / data.length : 0;
            const avgPrice = priceCount > 0 ? totalPrice / priceCount : 0;
            const avgDil = dilCount > 0 ? totalDil / dilCount : 0;
            const avgRoi = roiCount > 0 ? totalRoi / roiCount : 0;

            $('#total-pft-amt-badge').text(`Total PFT: $${Math.round(totalPft).toLocaleString()}`);
            $('#total-sales-amt-badge').text(`Total Sales: $${Math.round(totalSales).toLocaleString()}`);
            $('#avg-gpft-badge').text(`AVG GPFT: ${avgGpft.toFixed(1)}%`);
            $('#avg-price-badge').text(`Avg Price: $${avgPrice.toFixed(2)}`);
            $('#total-inv-badge').text(`Total INV: ${totalInv.toLocaleString()}`);
            $('#total-l30-badge').text(`Total PP L30: ${totalL30.toLocaleString()}`);
            $('#zero-sold-count-badge').text(`0 Sold: ${zeroSold}`);
            $('#avg-dil-badge').text(`DIL%: ${(avgDil * 100).toFixed(1)}%`);
            $('#total-cogs-badge').text(`COGS: $${Math.round(totalCogs).toLocaleString()}`);
            $('#roi-percent-badge').text(`ROI%: ${avgRoi.toFixed(1)}%`);
            $('#missing-badge').text(`MISSING: ${missingCount}`);
            $('#mapping-badge').text(`MAPPING: ${mappingCount}`);
            $('#total-pp-stock-badge').text(`PP Stock: ${totalPpStock.toLocaleString()}`);
        }

        function buildColumnDropdown() {
            let html = '';
            table.getColumns().forEach(col => {
                const field = col.getField(), title = col.getDefinition().title;
                if (field && field !== '_select' && title) {
                    html += `<li class="dropdown-item"><label style="cursor:pointer;display:flex;align-items:center;gap:8px;">
                        <input type="checkbox" class="column-toggle" data-field="${field}" ${col.isVisible() ? 'checked' : ''}>
                        ${title.replace(/<[^>]*>/g, '')}
                    </label></li>`;
                }
            });
            $('#column-dropdown-menu').html(html);
        }

        function saveColumnVisibilityToServer() {
            const visibility = {};
            table.getColumns().forEach(col => { if (col.getField() && col.getField() !== '_select') visibility[col.getField()] = col.isVisible(); });
            $.ajax({ url: '/pp-pricing-column-visibility', method: 'POST', data: { visibility, _token: '{{ csrf_token() }}' } });
        }

        function applyColumnVisibilityFromServer() {
            $.ajax({
                url: '/pp-pricing-column-visibility', method: 'GET',
                success: function(visibility) {
                    if (visibility && Object.keys(visibility).length > 0) {
                        Object.keys(visibility).forEach(field => {
                            const col = table.getColumn(field);
                            if (col) visibility[field] ? col.show() : col.hide();
                        });
                        buildColumnDropdown();
                    }
                }
            });
        }

        table.on('tableBuilt', function() { buildColumnDropdown(); applyColumnVisibilityFromServer(); });
        table.on('dataLoaded', function() { setTimeout(function() { applyFilters(); updateSummary(); }, 100); });
        table.on('renderComplete', function() { setTimeout(function() { updateSummary(); }, 100); });

        document.getElementById('column-dropdown-menu').addEventListener('change', function(e) {
            if (e.target.classList.contains('column-toggle')) {
                const col = table.getColumn(e.target.dataset.field);
                if (col) { e.target.checked ? col.show() : col.hide(); saveColumnVisibilityToServer(); }
            }
        });

        document.getElementById('show-all-columns-btn').addEventListener('click', function() {
            table.getColumns().forEach(col => { if (col.getField() !== '_select') col.show(); });
            buildColumnDropdown(); saveColumnVisibilityToServer();
        });

        document.getElementById('export-btn').addEventListener('click', function() {
            const visibleCols = table.getColumns().filter(c => c.isVisible() && c.getField() !== '_select');
            const headers = visibleCols.map(c => c.getDefinition().title || c.getField());
            const rows = table.getData('active').map(row => visibleCols.map(col => {
                let v = row[col.getField()];
                if (v === null || v === undefined) return '';
                if (typeof v === 'number') return parseFloat(v.toFixed(2));
                return v;
            }));
            let csv = [headers, ...rows].map(row => row.map(cell => {
                if (typeof cell === 'string' && (cell.includes(',') || cell.includes('"') || cell.includes('\n')))
                    return '"' + cell.replace(/"/g, '""') + '"';
                return cell;
            }).join(',')).join('\n');
            const link = document.createElement('a');
            link.setAttribute('href', URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8;' })));
            link.setAttribute('download', 'purchasing_power_pricing_' + new Date().toISOString().slice(0,10) + '.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link); link.click(); document.body.removeChild(link);
            showToast('Export downloaded!', 'success');
        });
    });
</script>
@endsection
