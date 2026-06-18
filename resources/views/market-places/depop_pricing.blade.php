@extends('layouts.vertical', ['title' => 'Depop - Analytics', 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        .tabulator-paginator label { margin-right: 5px; }
        .badge-pricing-stat { font-size: 0.9rem; padding: 0.45rem 0.7rem; }
        /* Select column header checkbox */
        .depop-select-header { display: flex; align-items: center; justify-content: center; }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Depop - Analytics',
        'sub_title'  => '',
    ])

    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1080;"></div>

    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
                    <a href="{{ route('depop.pricing.export') }}" class="btn btn-sm btn-success" id="export-btn">
                        <i class="fa fa-file-csv"></i> Export CSV
                    </a>

                    <form id="import-form" class="d-flex align-items-center gap-2 mb-0">
                        @csrf
                        <input type="file" name="file" id="import-file" accept=".csv,.txt"
                            class="form-control form-control-sm" style="max-width: 240px;" required>
                        <button type="submit" class="btn btn-sm btn-primary" id="import-btn">
                            <i class="fa fa-upload"></i> Import CSV
                        </button>
                    </form>

                    <button id="decrease-btn" class="btn btn-sm btn-warning">
                        <i class="fas fa-arrow-down"></i> Decrease Mode
                    </button>
                    <button id="increase-btn" class="btn btn-sm btn-success">
                        <i class="fas fa-arrow-up"></i> Increase Mode
                    </button>
                    <button id="same-price-btn" class="btn btn-sm btn-info" title="Apply ONE price (entered in the box) to every selected SKU">
                        <i class="fas fa-equals"></i> Same Price Mode
                    </button>

                    <span class="text-muted small ms-2">
                        CSV columns: <code>parent, sku, price, l30</code> (price &amp; l30 are editable; sku is the match key)
                    </span>
                </div>

                <div class="d-flex flex-wrap gap-2 mt-2">
                    <span class="badge bg-primary badge-pricing-stat" id="stat-total">SKUs: 0</span>
                    <span class="badge bg-success badge-pricing-stat" id="stat-priced">With Price: 0</span>
                    <span class="badge bg-info text-dark badge-pricing-stat" id="stat-l30">With L30: 0</span>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <!-- Discount Input Box (shown when in a pricing mode + SKUs selected) -->
                <div id="discount-input-container" class="p-2 bg-light border-bottom" style="display: none;">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span id="selected-skus-count" class="fw-bold"></span>
                        <span id="discount-input-label" class="text-muted small d-none">Same Price ($):</span>
                        <span id="discount-type-select-wrap">
                            <select id="discount-type-select" class="form-select form-select-sm" style="width: 120px;">
                                <option value="percentage">Percentage</option>
                                <option value="value">Value ($)</option>
                            </select>
                        </span>
                        <input type="number" id="discount-percentage-input" class="form-control form-control-sm"
                            placeholder="Enter %" step="0.01" style="width: 140px;">
                        <button id="apply-discount-btn" class="btn btn-primary btn-sm">Apply</button>
                        <button id="clear-sprice-btn" class="btn btn-danger btn-sm">
                            <i class="fas fa-eraser"></i> Clear SPRICE
                        </button>
                    </div>
                </div>

                <div class="p-2 bg-light border-bottom d-flex flex-wrap gap-2 align-items-center">
                    <input type="text" id="sku-search" class="form-control form-control-sm" placeholder="Search by Parent or SKU..." style="max-width: 280px;">

                    <!-- Play / Pause parent navigation -->
                    <div class="btn-group align-items-center" role="group" aria-label="Parent navigation">
                        <button type="button" id="play-backward" class="btn btn-sm btn-light rounded-circle shadow-sm" title="Previous parent" disabled>
                            <i class="fas fa-step-backward"></i>
                        </button>
                        <button type="button" id="play-auto" class="btn btn-sm btn-primary rounded-circle shadow-sm" title="Start parent navigation">
                            <i class="fas fa-play"></i>
                        </button>
                        <button type="button" id="play-pause" class="btn btn-sm btn-warning rounded-circle shadow-sm" style="display: none;" title="Stop navigation and show all">
                            <i class="fas fa-pause"></i>
                        </button>
                        <button type="button" id="play-forward" class="btn btn-sm btn-light rounded-circle shadow-sm" title="Next parent" disabled>
                            <i class="fas fa-step-forward"></i>
                        </button>
                    </div>
                </div>
                <div id="depop-pricing-table" style="height: calc(100vh - 320px);"></div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
    let table = null;
    let decreaseModeActive = false;
    let increaseModeActive = false;
    let samePriceModeActive = false;
    let selectedSkus = new Set();

    function showToast(message, type) {
        type = type || 'info';
        const container = document.querySelector('.toast-container');
        if (!container) return;
        const bg = type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info';
        const el = document.createElement('div');
        el.className = `toast align-items-center text-white bg-${bg} border-0 mb-2`;
        el.setAttribute('role', 'alert');
        el.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>`;
        container.appendChild(el);
        new bootstrap.Toast(el, { delay: 6000 }).show();
        el.addEventListener('hidden.bs.toast', () => el.remove());
    }

    function updateStats(rows) {
        const total  = rows.length;
        const priced = rows.filter(r => r.price !== null && r.price !== undefined && r.price > 0).length;
        const withL30 = rows.filter(r => r.l30 !== null && r.l30 !== undefined && r.l30 > 0).length;
        $('#stat-total').text('SKUs: ' + total.toLocaleString('en-US'));
        $('#stat-priced').text('With Price: ' + priced.toLocaleString('en-US'));
        $('#stat-l30').text('With L30: ' + withL30.toLocaleString('en-US'));
    }

    // Retail .99 rounding (matches Macys / Shopify B2C helpers).
    function roundToRetailPrice(price) {
        if (price < 20.99) return +price.toFixed(2);
        const roundedDollar = Math.ceil(price);
        return +(roundedDollar - 0.01).toFixed(2);
    }

    function anyModeActive() {
        return decreaseModeActive || increaseModeActive || samePriceModeActive;
    }

    function updateSelectedCount() {
        const count = selectedSkus.size;
        $('#selected-skus-count').text(`${count} SKU${count !== 1 ? 's' : ''} selected`);
        $('#discount-input-container').toggle(anyModeActive() && count > 0);
    }

    function updateSelectAllHeaderCheckbox() {
        const el = document.getElementById('depop-select-all');
        if (!el || !table) return;
        const rows = table.getRows('active');
        if (!rows.length) {
            el.checked = false;
            el.indeterminate = false;
            return;
        }
        let selected = 0;
        rows.forEach(r => {
            const d = r.getData();
            if (d.sku && selectedSkus.has(String(d.sku))) selected++;
        });
        el.checked = selected === rows.length && rows.length > 0;
        el.indeterminate = selected > 0 && selected < rows.length;
    }

    // Mode button visual resets.
    function resetDecreaseBtn() {
        $('#decrease-btn').removeClass('btn-danger').addClass('btn-warning')
            .html('<i class="fas fa-arrow-down"></i> Decrease Mode');
    }
    function resetIncreaseBtn() {
        $('#increase-btn').removeClass('btn-danger').addClass('btn-success')
            .html('<i class="fas fa-arrow-up"></i> Increase Mode');
    }
    function resetSamePriceBtn() {
        $('#same-price-btn').removeClass('btn-danger').addClass('btn-info')
            .html('<i class="fas fa-equals"></i> Same Price Mode');
    }

    function syncDiscountInputUi() {
        const $input = $('#discount-percentage-input');
        if (samePriceModeActive) {
            $('#discount-type-select-wrap').hide();
            $('#discount-input-label').removeClass('d-none');
            $input.attr('placeholder', 'Enter price (e.g. 19.99)').attr('step', '0.01');
            $('#apply-discount-btn').text('Apply Same Price');
        } else {
            $('#discount-type-select-wrap').show();
            $('#discount-input-label').addClass('d-none');
            const t = $('#discount-type-select').val();
            $input.attr('placeholder', t === 'percentage' ? 'Enter %' : 'Enter $');
            $('#apply-discount-btn').text('Apply');
        }
    }

    function showSelectColumn(show) {
        if (!table) return;
        const col = table.getColumn('_select');
        if (col) {
            if (show) col.show(); else col.hide();
        }
    }

    function exitAllModesUiOnly() {
        // Visual reset only — caller decides whether to clear selection.
        resetDecreaseBtn();
        resetIncreaseBtn();
        resetSamePriceBtn();
    }

    $(document).ready(function() {
        $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } });

        table = new Tabulator("#depop-pricing-table", {
            ajaxURL: "{{ route('depop.pricing.data') }}",
            ajaxResponse: function(_url, _params, response) {
                const data = (response && response.data) ? response.data : [];
                updateStats(data);
                return data;
            },
            layout: "fitDataStretch",
            pagination: true,
            paginationSize: 50,
            paginationSizeSelector: [25, 50, 100, 200, 500],
            initialSort: [{ column: "parent", dir: "asc" }],
            placeholder: "No SKUs found. Make sure ProductMaster has SKUs.",
            columns: [
                {
                    // Selection column (hidden until a pricing mode is active).
                    title: '<div class="depop-select-header"><input type="checkbox" id="depop-select-all" title="Select / clear all filtered SKUs"></div>',
                    field: "_select",
                    width: 50,
                    headerSort: false,
                    visible: false,
                    hozAlign: "center",
                    formatter: function(cell) {
                        const sku = cell.getRow().getData().sku;
                        if (!sku) return '';
                        const safe = String(sku).replace(/&/g, '&amp;').replace(/"/g, '&quot;');
                        const checked = selectedSkus.has(String(sku)) ? 'checked' : '';
                        return `<input type="checkbox" class="depop-sku-chk" data-sku="${safe}" ${checked}>`;
                    }
                },
                {
                    // Image — same source as Macy's pricing (shopify_skus.image_src)
                    title: "Image",
                    field: "image",
                    headerSort: false,
                    width: 70,
                    hozAlign: "center",
                    formatter: function(cell) {
                        const v = cell.getValue();
                        if (!v) return '<span class="text-muted">-</span>';
                        return `<img src="${v}" alt="" style="width:46px;height:46px;object-fit:cover;border-radius:4px;">`;
                    }
                },
                { title: "Parent", field: "parent", width: 180 },
                { title: "SKU",    field: "sku",    minWidth: 200 },
                {
                    title: "Inv",
                    field: "inv",
                    width: 80,
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const v = Number(cell.getValue() || 0);
                        return v ? v.toLocaleString('en-US') : '<span class="text-muted">0</span>';
                    }
                },
                {
                    title: "OV L30",
                    field: "ov_l30",
                    width: 90,
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const v = Number(cell.getValue() || 0);
                        return v ? v.toLocaleString('en-US') : '<span class="text-muted">0</span>';
                    }
                },
                {
                    // Dil% = OV L30 ÷ INV × 100, with the same colour bands Macy's uses.
                    title: "Dil",
                    field: "dil",
                    width: 80,
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const row = cell.getRow().getData();
                        const inv = Number(row.inv   || 0);
                        const ov  = Number(row.ov_l30 || 0);
                        if (inv === 0) return '<span style="color:#6c757d;">0%</span>';
                        const dil = (ov / inv) * 100;
                        let color = '#a00211';                                  // < 16.66
                        if (dil >= 16.66 && dil < 25) color = '#ffc107';        // 16.66–25
                        else if (dil >= 25 && dil < 50) color = '#28a745';      // 25–50
                        else if (dil >= 50) color = '#e83e8c';                  // >= 50
                        return `<span style="color:${color};font-weight:600;">${Math.round(dil)}%</span>`;
                    }
                },
                {
                    title: "Price ($)",
                    field: "price",
                    width: 110,
                    hozAlign: "right",
                    sorter: "number",
                    formatter: function(cell) {
                        const v = cell.getValue();
                        if (v === null || v === undefined || v === '') return '<span class="text-muted">-</span>';
                        return '$' + Number(v).toFixed(2);
                    }
                },
                {
                    title: "SPRICE",
                    field: "sprice",
                    width: 110,
                    hozAlign: "right",
                    sorter: "number",
                    formatter: function(cell) {
                        const v = cell.getValue();
                        if (v === null || v === undefined || v === '') return '<span class="text-muted">-</span>';
                        return '<strong>$' + Number(v).toFixed(2) + '</strong>';
                    }
                },
                {
                    title: "L30",
                    field: "l30",
                    width: 90,
                    hozAlign: "right",
                    sorter: "number",
                    formatter: function(cell) {
                        const v = cell.getValue();
                        if (v === null || v === undefined || v === '') return '<span class="text-muted">-</span>';
                        return Number(v).toLocaleString('en-US');
                    }
                },
            ],
        });

        table.on('renderComplete', updateSelectAllHeaderCheckbox);

        // ─── Pricing-mode toggles ─────────────────────────────────────────
        $('#decrease-btn').on('click', function() {
            decreaseModeActive = !decreaseModeActive;
            increaseModeActive = false;
            samePriceModeActive = false;

            exitAllModesUiOnly();
            if (decreaseModeActive) {
                $(this).removeClass('btn-warning').addClass('btn-danger')
                    .html('<i class="fas fa-arrow-down"></i> Decrease ON');
                showSelectColumn(true);
            } else {
                resetDecreaseBtn();
                showSelectColumn(false);
                selectedSkus.clear();
            }
            updateSelectedCount();
            syncDiscountInputUi();
        });

        $('#increase-btn').on('click', function() {
            increaseModeActive = !increaseModeActive;
            decreaseModeActive = false;
            samePriceModeActive = false;

            exitAllModesUiOnly();
            if (increaseModeActive) {
                $(this).removeClass('btn-success').addClass('btn-danger')
                    .html('<i class="fas fa-arrow-up"></i> Increase ON');
                showSelectColumn(true);
            } else {
                resetIncreaseBtn();
                showSelectColumn(false);
                selectedSkus.clear();
            }
            updateSelectedCount();
            syncDiscountInputUi();
        });

        $('#same-price-btn').on('click', function() {
            samePriceModeActive = !samePriceModeActive;
            decreaseModeActive = false;
            increaseModeActive = false;

            exitAllModesUiOnly();
            if (samePriceModeActive) {
                $(this).removeClass('btn-info').addClass('btn-danger')
                    .html('<i class="fas fa-equals"></i> Same Price ON');
                showSelectColumn(true);
            } else {
                resetSamePriceBtn();
                showSelectColumn(false);
                selectedSkus.clear();
            }
            updateSelectedCount();
            syncDiscountInputUi();
        });

        $('#discount-type-select').on('change', function() { syncDiscountInputUi(); });

        // ─── Selection handlers ───────────────────────────────────────────
        $(document).on('change', '#depop-select-all', function() {
            const checked = $(this).prop('checked');
            const rows = table.getRows('active');
            rows.forEach(r => {
                const d = r.getData();
                if (!d.sku) return;
                if (checked) selectedSkus.add(String(d.sku));
                else selectedSkus.delete(String(d.sku));
            });
            $('.depop-sku-chk').each(function() {
                const sku = String($(this).attr('data-sku'));
                $(this).prop('checked', selectedSkus.has(sku));
            });
            updateSelectedCount();
        });

        $(document).on('change', '.depop-sku-chk', function() {
            const sku = String($(this).attr('data-sku'));
            if (!sku) return;
            if ($(this).prop('checked')) selectedSkus.add(sku);
            else selectedSkus.delete(sku);
            updateSelectedCount();
            updateSelectAllHeaderCheckbox();
        });

        // ─── Apply / Clear handlers ───────────────────────────────────────
        $('#apply-discount-btn').on('click', applyDiscount);
        $('#discount-percentage-input').on('keypress', function(e) {
            if (e.which === 13) applyDiscount();
        });
        $('#clear-sprice-btn').on('click', clearSpriceForSelected);

        // ─── Search (parent + sku) ────────────────────────────────────────
        $('#sku-search').on('input', function() {
            applyDepopFilters();
        });

        // Play / Pause parent navigation state
        let dpUniqueParents = [];
        let isDpPlayActive = false;
        let currentDpParentIndex = -1;

        function normalizeDpParentKey(val) {
            if (val == null || val === '') return '';
            return String(val).trim().replace(/\s+/g, ' ').replace(/^PARENT\s+/i, '');
        }
        function buildDpUniqueParents() {
            if (!table) return [];
            const allRows = table.getData('all') || [];
            const seen = {};
            const list = [];
            allRows.forEach(function(r) {
                const p = normalizeDpParentKey(r.parent);
                if (p && !seen[p]) { seen[p] = true; list.push(p); }
            });
            list.sort(function(a, b) { return String(a).localeCompare(String(b)); });
            return list;
        }
        function updateDpPlayButtonStates() {
            $('#play-backward').prop('disabled', !isDpPlayActive || currentDpParentIndex <= 0);
            $('#play-forward').prop('disabled', !isDpPlayActive || currentDpParentIndex >= dpUniqueParents.length - 1);
        }
        function applyDepopFilters() {
            if (!table) return;
            table.clearFilter(true);

            // Play navigation: only show current parent's group
            if (isDpPlayActive && dpUniqueParents.length > 0 && currentDpParentIndex >= 0) {
                const currentKey = dpUniqueParents[currentDpParentIndex];
                if (currentKey) {
                    table.addFilter(function(d) {
                        const p = normalizeDpParentKey(d.parent);
                        return p === currentKey || p === ('PARENT ' + currentKey);
                    });
                }
                return;
            }

            const q = ($('#sku-search').val() || '').trim().toLowerCase();
            if (q) {
                table.addFilter(function(row) {
                    return (String(row.parent || '').toLowerCase().includes(q))
                        || (String(row.sku    || '').toLowerCase().includes(q));
                });
            }
        }
        function startDpPlay() {
            dpUniqueParents = buildDpUniqueParents();
            if (dpUniqueParents.length === 0) return;
            isDpPlayActive = true;
            currentDpParentIndex = 0;
            $('#play-auto').hide();
            $('#play-pause').show();
            applyDepopFilters();
            try { table.setPage(1); } catch (e) {}
            updateDpPlayButtonStates();
        }
        function stopDpPlay() {
            isDpPlayActive = false;
            currentDpParentIndex = -1;
            $('#play-pause').hide();
            $('#play-auto').show();
            applyDepopFilters();
            updateDpPlayButtonStates();
        }
        function nextDpParent() {
            if (!isDpPlayActive || currentDpParentIndex >= dpUniqueParents.length - 1) return;
            currentDpParentIndex++;
            applyDepopFilters();
            try { table.setPage(1); } catch (e) {}
            updateDpPlayButtonStates();
        }
        function previousDpParent() {
            if (!isDpPlayActive || currentDpParentIndex <= 0) return;
            currentDpParentIndex--;
            applyDepopFilters();
            try { table.setPage(1); } catch (e) {}
            updateDpPlayButtonStates();
        }
        $('#play-auto').on('click', startDpPlay);
        $('#play-pause').on('click', stopDpPlay);
        $('#play-forward').on('click', nextDpParent);
        $('#play-backward').on('click', previousDpParent);

        // ─── Import CSV ───────────────────────────────────────────────────
        $('#import-form').on('submit', function(e) {
            e.preventDefault();
            const fileInput = $('#import-file')[0];
            if (!fileInput.files || !fileInput.files[0]) {
                showToast('Choose a CSV file first.', 'error');
                return;
            }

            const formData = new FormData();
            formData.append('file', fileInput.files[0]);

            const $btn = $('#import-btn');
            const original = $btn.html();
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Importing...');

            $.ajax({
                url: "{{ route('depop.pricing.import') }}",
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(res) {
                    if (res.success) {
                        showToast(res.message || 'Import complete', 'success');
                        $('#import-file').val('');
                        table.setData();
                    } else {
                        showToast(res.message || 'Import failed', 'error');
                    }
                },
                error: function(xhr) {
                    const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Import failed';
                    showToast(msg, 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).html(original);
                }
            });
        });
    });

    // ─── Apply discount / same-price logic ────────────────────────────────
    function applyDiscount() {
        const discountType = $('#discount-type-select').val();
        const discountValue = parseFloat($('#discount-percentage-input').val());

        if (!anyModeActive()) {
            showToast('Turn on Decrease, Increase, or Same Price mode first', 'error');
            return;
        }
        if (isNaN(discountValue) || discountValue <= 0) {
            showToast(samePriceModeActive ? 'Please enter a price (e.g. 19.99)' : 'Please enter a valid value', 'error');
            return;
        }
        if (selectedSkus.size === 0) {
            showToast('Please select at least one SKU', 'error');
            return;
        }

        const updates = [];
        let updatedCount = 0;

        // Iterate over the active filtered rows so checked SKUs get matched even
        // across pages (Tabulator returns row components for all filtered data).
        table.getRows('active').forEach(function(row) {
            const d = row.getData();
            const sku = d && d.sku != null ? String(d.sku) : '';
            if (!sku || !selectedSkus.has(sku)) return;

            const currentPrice = parseFloat(d.price) || 0;
            // Decrease / Increase modes need a current Price to compute against;
            // Same Price mode applies the typed value even when Price is empty.
            if (!samePriceModeActive && currentPrice <= 0) return;

            let newSprice;
            if (samePriceModeActive) {
                newSprice = Math.max(0.99, discountValue);
            } else if (discountType === 'percentage') {
                newSprice = increaseModeActive
                    ? currentPrice * (1 + discountValue / 100)
                    : currentPrice * (1 - discountValue / 100);
            } else {
                newSprice = increaseModeActive
                    ? currentPrice + discountValue
                    : currentPrice - discountValue;
            }

            newSprice = roundToRetailPrice(newSprice);
            newSprice = Math.max(0.99, newSprice);

            row.update({ sprice: newSprice });
            updates.push({ sku: sku, sprice: newSprice });
            updatedCount++;
        });

        if (updates.length === 0) {
            showToast('No matching selected rows in the current view.', 'error');
            return;
        }

        saveSpriceUpdates(updates);

        const action = samePriceModeActive ? 'Same Price' : (increaseModeActive ? 'Increase' : 'Decrease');
        showToast(`${action} applied to ${updatedCount} SKU(s)`, 'success');
        $('#discount-percentage-input').val('');
    }

    function clearSpriceForSelected() {
        if (selectedSkus.size === 0) {
            showToast('Select SKUs first', 'error');
            return;
        }
        if (!confirm(`Clear SPRICE for ${selectedSkus.size} SKU(s)?`)) return;

        const updates = [];
        table.getRows('active').forEach(function(row) {
            const d = row.getData();
            const sku = d && d.sku != null ? String(d.sku) : '';
            if (!sku || !selectedSkus.has(sku)) return;
            row.update({ sprice: null });
            updates.push({ sku: sku, sprice: null });
        });

        if (!updates.length) return;
        saveSpriceUpdates(updates);
        showToast(`Cleared SPRICE for ${updates.length} SKU(s)`, 'success');
    }

    function saveSpriceUpdates(updates) {
        $.ajax({
            url: "{{ route('depop.pricing.save.sprice') }}",
            method: 'POST',
            data: { _token: '{{ csrf_token() }}', updates: updates },
            success: function(res) {
                if (!res || res.success !== true) {
                    showToast((res && res.message) || 'Failed to save SPRICE', 'error');
                }
            },
            error: function(xhr) {
                const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to save SPRICE';
                showToast(msg, 'error');
            }
        });
    }
</script>
@endsection
