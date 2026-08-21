@extends('layouts.vertical', ['title' => 'Vinted - Analytics', 'sidenav' => 'condensed'])

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
        #summary-stats .badge.active-filter {
            box-shadow: 0 0 0 3px rgba(255,255,255,.85), 0 0 0 5px currentColor;
        }

        /* Column visibility dropdown — 4 columns */
        .column-dropdown-multicol {
            min-width: 560px;
            padding: 6px 4px;
            column-count: 4;
            column-gap: 8px;
            max-height: 420px;
            overflow-y: auto;
        }
        .column-dropdown-multicol > li {
            break-inside: avoid;
            -webkit-column-break-inside: avoid;
            page-break-inside: avoid;
        }
        .column-dropdown-multicol .dropdown-item {
            padding: 3px 10px;
            white-space: nowrap;
        }
        @include('partials.channel-pef-promo', ['channelPromoPart' => 'css', 'channelPromoChannel' => 'vinted'])
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Vinted - Analytics',
        'sub_title'  => '',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <select id="inventory-filter" class="form-select form-select-sm" style="width: auto;">
                        <option value="all">INV</option>
                        <option value="zero">0 Inventory</option>
                        <option value="more" selected>More than 0</option>
                    </select>

                    <select id="nrl-filter" class="form-select form-select-sm" style="width: auto;">
                        <option value="all">Status</option>
                        <option value="REQ" selected>REQ Only</option>
                        <option value="NR">NR Only</option>
                    </select>

                    <div class="d-flex align-items-center gap-1" style="width: auto;" title="CVR = V L30 ÷ OV L30">
                        <select id="gpft-filter" class="form-select form-select-sm" style="width: auto;">
                            <option value="all">GPFT%</option>
                            <option value="negative">Negative</option>
                            <option value="0-10">0-10%</option>
                            <option value="10-20">10-20%</option>
                            <option value="20-30">20-30%</option>
                            <option value="30-40">30-40%</option>
                            <option value="40-50">40-50%</option>
                            <option value="50plus">Above 50%</option>
                        </select>
                        <select id="cvr-filter" class="form-select form-select-sm" style="width: auto;">
                            <option value="all">CVR%</option>
                            <option value="0-0">0%</option>
                            <option value="0-3">0-3%</option>
                            <option value="3-7">3-7%</option>
                            <option value="7-13">7-13%</option>
                            <option value="13plus">13%+</option>
                        </select>
                    </div>

                    {{-- Sold dropdown (mirrors Amazon tabulator + /doba + /shopify-b2c + /macys).
                         Backed by `V L30`:
                           all  → no filter
                           sold → V L30 > 0
                           zero → V L30 = 0
                         Single source of truth — #zero-sold-badge / #more-sold-badge
                         click handlers just toggle this dropdown so badges + dropdown stay synced. --}}
                    <select id="sold-filter" class="form-select form-select-sm" style="width: auto;"
                            title="Filter by V L30 sold quantity">
                        <option value="all">Sold</option>
                        <option value="sold">Sold &gt; 0</option>
                        <option value="zero">0 Sold</option>
                    </select>

                    <select id="roi-filter" class="form-select form-select-sm" style="width: auto;">
                        <option value="all">ROI%</option>
                        <option value="lt40">&lt; 40%</option>
                        <option value="40-75">40–75%</option>
                        <option value="75-125">75–125%</option>
                        <option value="gt125">125%+</option>
                    </select>

                    <select id="dil-filter" class="form-select form-select-sm" style="width: auto;">
                        <option value="all">DIL%</option>
                        <option value="red">Red (&lt;16.7%)</option>
                        <option value="yellow">Yellow (16.7-25%)</option>
                        <option value="green">Green (25-50%)</option>
                        <option value="pink">Pink (50%+)</option>
                    </select>

                    <div class="dropdown d-inline-block">
                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button"
                            id="columnVisibilityDropdown" data-bs-toggle="dropdown" aria-expanded="false"
                            title="Columns" aria-label="Columns">
                            <i class="fa fa-eye"></i>
                        </button>
                        <ul class="dropdown-menu column-dropdown-multicol" id="column-dropdown-menu"></ul>
                    </div>
                    <button id="export-btn" class="btn btn-sm btn-info" title="Export CSV" aria-label="Export CSV">
                        <i class="fas fa-file-excel"></i>
                    </button>
                    @include('partials.channel-pef-promo', ['channelPromoPart' => 'buttons', 'channelPromoChannel' => 'vinted'])
                    <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal"
                            data-bs-target="#uploadVintedPriceModal" title="Merge-upload into VintedPricing by SKU (keeps SPRICE / NR / links)">
                        <i class="fas fa-upload"></i> Up Prc
                    </button>
                    <div class="dropdown d-inline-block" id="sprice-mode-dropdown">
                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button"
                            id="sprice-mode-btn" data-bs-toggle="dropdown" aria-expanded="false"
                            title="Choose Decrease / Increase / Same Price">
                            <i class="fas fa-sliders-h"></i> PrcM
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="sprice-mode-btn">
                            <li><a class="dropdown-item sprice-mode-item active" href="#" data-mode="">
                                <i class="fas fa-times me-1"></i> Off</a></li>
                            <li><a class="dropdown-item sprice-mode-item" href="#" data-mode="decrease">
                                <i class="fas fa-arrow-down me-1 text-warning"></i> Decrease</a></li>
                            <li><a class="dropdown-item sprice-mode-item" href="#" data-mode="increase">
                                <i class="fas fa-arrow-up me-1 text-success"></i> Increase</a></li>
                            <li><a class="dropdown-item sprice-mode-item" href="#" data-mode="same"
                                    title="Apply ONE price to every selected SKU">
                                <i class="fas fa-equals me-1 text-info"></i> Same Price</a></li>
                        </ul>
                    </div>

                    {{-- Target ROI% / GPFT% — same compact control as /topdawg-pricing --}}
                    <div class="d-inline-flex align-items-center gap-1 ms-1 p-1 border rounded bg-white"
                        id="target-roi-controls"
                        title="Target ROI% — sets S PRC = (LP × (1 + Target ROI%/100) + Ship) / {{ $vintedPercentage }}% on every selected row">
                        <label for="target-roi-input" class="form-label mb-0 small fw-bold text-nowrap"
                               aria-label="Target ROI percent">
                            <span style="font-size:1em;" aria-hidden="true">🎯</span> ROI%:
                        </label>
                        <input type="number" id="target-roi-input" class="form-control form-control-sm text-end"
                            placeholder="30" step="0.1" style="width: 80px;"
                            title="Target ROI% applied to all selected rows when you click Apply">
                        <button id="apply-target-roi-btn" class="btn btn-sm btn-success" type="button"
                            title="Apply — Compute & save S PRC = (LP × (1 + Target ROI%/100) + Ship) / {{ $vintedPercentage }}% for every selected row"
                            aria-label="Apply Target ROI">
                            <i class="fas fa-calculator"></i>
                        </button>
                    </div>

                    <div class="d-inline-flex align-items-center gap-1 ms-1 p-1 border rounded bg-white"
                        id="target-gpft-controls"
                        title="Target GPFT% — sets S PRC = (LP + Ship) / ({{ $vintedPercentage }}% − Target GPFT%/100) on every selected row">
                        <label for="target-gpft-input" class="form-label mb-0 small fw-bold text-nowrap"
                               aria-label="Target GPFT percent">
                            <span style="font-size:1em;" aria-hidden="true">🎯</span> GPFT%:
                        </label>
                        <input type="number" id="target-gpft-input" class="form-control form-control-sm text-end"
                            placeholder="30" step="0.1" style="width: 80px;"
                            title="Target GPFT% applied to all selected rows when you click Apply. Must be less than the Vinted take-home margin ({{ $vintedPercentage }}%).">
                        <button id="apply-target-gpft-btn" class="btn btn-sm btn-success" type="button"
                            title="Apply — Compute & save S PRC = (LP + Ship) / ({{ $vintedPercentage }}% − Target GPFT%/100) for every selected row"
                            aria-label="Apply Target GPFT">
                            <i class="fas fa-calculator"></i>
                        </button>
                    </div>
                </div>

                {{-- Summary badges — same format as /topdawg-pricing (equal-width strip) --}}
                <div id="summary-stats" class="mt-2 p-1 bg-light rounded">
                    <div class="d-flex flex-nowrap gap-1 w-100" style="overflow-x:auto;">
                        <span class="badge text-center" id="total-rows-badge"
                              style="background:#343a40;color:#fff;font-weight:bold;flex:1 1 0;min-width:90px;font-size:14px;padding:8px 10px;"
                              title="Number of SKU rows currently passing the filters">Rows: 0</span>
                        <span class="badge text-center" id="total-sales-badge"
                              style="background:#198754;color:#fff;font-weight:bold;flex:1 1 0;min-width:90px;font-size:14px;padding:8px 10px;"
                              title="Σ (V L30 × V Price) across visible rows">Sales: $0</span>
                        <span class="badge bg-success text-center" id="total-v-l30-badge"
                              style="color:#000;font-weight:bold;flex:1 1 0;min-width:90px;font-size:14px;padding:8px 10px;"
                              title="Sum of V L30 on filtered rows">V L30: 0</span>
                        <span class="badge bg-danger text-center" id="zero-sold-badge"
                              style="color:#fff;font-weight:bold;cursor:pointer;flex:1 1 0;min-width:90px;font-size:14px;padding:8px 10px;"
                              title="SKUs with V L30 = 0">0 Sold: 0</span>
                        <span class="badge text-center" id="more-sold-badge"
                              style="background:#28a745;color:#fff;font-weight:bold;cursor:pointer;flex:1 1 0;min-width:90px;font-size:14px;padding:8px 10px;"
                              title="SKUs with V L30 &gt; 0">&gt; 0 Sold: 0</span>
                        <span class="badge text-center" id="gpft-pct-badge"
                              style="background:#6f42c1;color:#fff;font-weight:bold;flex:1 1 0;min-width:90px;font-size:14px;padding:8px 10px;"
                              title="Weighted Gross Profit %: (Σ Profit ÷ Σ Sales L30) × 100 ({{ $vintedPercentage }}% margin)">GPFT: 0%</span>
                        <span class="badge text-center" id="groi-pct-badge"
                              style="background:#0d6efd;color:#fff;font-weight:bold;flex:1 1 0;min-width:90px;font-size:14px;padding:8px 10px;"
                              title="Weighted Gross ROI %: (Σ Profit ÷ Σ COGS) × 100">GROI: 0%</span>
                        <span class="badge bg-danger text-center" id="missing-badge"
                              style="color:#fff;font-weight:bold;cursor:pointer;flex:1 1 0;min-width:90px;font-size:14px;padding:8px 10px;"
                              title="REQ + INV&gt;0 + V Price=0">Missing L: 0</span>
                        @include('partials.price-gt-lmp-badge', ['pglBadgeId' => 'vinted-price-gt-lmp-badge', 'pglChannelKey' => 'vinted', 'pglPriceField' => 'V Price'])
                        @include('partials.price-lt80-lmp-badge', ['pltBadgeId' => 'vinted-price-lt80-lmp-badge', 'pltChannelKey' => 'vinted', 'pltPriceField' => 'V Price'])
                        <span class="badge fs-6 p-2" id="vinted-blue-triangle-badge"
                            style="background-color:#0d6efd;color:#fff;font-weight:700;cursor:pointer;"
                            title="Blue triangle: S PRC ≠ V Price. Click to show only those rows. Click again to clear.">
                            <i class="fas fa-exclamation-triangle"></i> 0</span>
                    </div>
                </div>
            </div>

            <div class="card-body" style="padding:0;">
                <div id="discount-input-container" class="p-2 bg-light border-bottom" style="display:none;">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span id="selected-skus-count" class="fw-bold"></span>
                        <span id="discount-input-label" class="text-muted small d-none">Same Price ($):</span>
                        <span id="discount-type-select-wrap">
                        <select id="discount-type-select" class="form-select form-select-sm" style="width:120px;">
                            <option value="percentage">Percentage</option>
                            <option value="value">Value ($)</option>
                        </select>
                        </span>
                        <input type="number" id="discount-percentage-input" class="form-control form-control-sm"
                            placeholder="Enter %" step="0.01" style="width:140px;">
                        <button id="apply-discount-btn" class="btn btn-primary btn-sm">Apply</button>
                        <button id="clear-sprice-btn" class="btn btn-danger btn-sm">
                            <i class="fas fa-eraser"></i> Clear SPRICE
                        </button>
                    </div>
                </div>
                <div id="vinted-table-wrapper" style="height:calc(100vh - 200px);display:flex;flex-direction:column;">
                    <div class="p-2 bg-light border-bottom d-flex flex-wrap gap-2 align-items-center">
                        <input type="text" id="parent-search" class="form-control form-control-sm" placeholder="Search Parent..." style="max-width: 220px;">
                        <input type="text" id="sku-search" class="form-control form-control-sm" placeholder="Search SKU..." style="max-width: 220px;">
                    </div>
                    <div id="vinted-table" style="flex:1;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Links Modal -->
    <div class="modal fade" id="editLinksModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Links</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="editLinksSku">
                    <p class="mb-3"><strong>SKU:</strong> <span id="editLinksSkuDisplay"></span></p>
                    <div class="mb-3">
                        <label for="editSellerLink" class="form-label">S Link (Seller)</label>
                        <input type="url" class="form-control" id="editSellerLink" placeholder="https://...">
                    </div>
                    <div class="mb-3">
                        <label for="editBuyerLink" class="form-label">B Link (Buyer)</label>
                        <input type="url" class="form-control" id="editBuyerLink" placeholder="https://...">
                    </div>
                    <div id="editLinksError" class="text-danger small" style="display:none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveLinksBtn">Save</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Merge price sheet into VintedPricing model (updateOrCreate by sku) --}}
    <div class="modal fade" id="uploadVintedPriceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title"><i class="fas fa-upload me-2"></i>Upload Vinted price sheet</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">
                        Download the template (all SKUs + current prices), edit <strong>price</strong>,
                        then upload — rows <strong>merge</strong> into <code>VintedPricing</code> by SKU
                        (SPRICE / NR / links are kept).
                    </p>
                    <a href="{{ route('vinted.pricing.sample') }}" class="btn btn-sm btn-outline-secondary mb-3">
                        <i class="fas fa-download"></i> Sample CSV
                    </a>
                    <input type="file" class="form-control" id="vintedPriceSheetFile" accept=".csv,.txt" required>
                    <small class="text-muted d-block mt-2">Headers: <strong>sku</strong>, <strong>price</strong> (optional <strong>l30</strong>).</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-warning" id="vintedUploadPriceSheetBtn">
                        <i class="fas fa-upload"></i> Upload &amp; Merge
                    </button>
                </div>
            </div>
        </div>
    </div>
    @include('partials.channel-pef-promo', ['channelPromoPart' => 'modals', 'channelPromoChannel' => 'vinted'])
@endsection

@section('script-bottom')
<script>
    @include('partials.channel-pef-promo', ['channelPromoPart' => 'script', 'channelPromoChannel' => 'vinted'])
    const COLUMN_VIS_KEY = "vinted_tabulator_column_visibility";
    let table = null;
    let allTableData = [];
    let decreaseModeActive = false;
    let increaseModeActive = false;
    let samePriceModeActive = false;
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

    function syncSpriceModeBtn() {
        const $btn = $('#sprice-mode-btn');
        $btn.removeClass('btn-secondary btn-warning btn-success btn-info btn-danger');
        $('.sprice-mode-item').removeClass('active');

        if (decreaseModeActive) {
            $btn.addClass('btn-warning')
                .html('<i class="fas fa-arrow-down"></i> Decrease');
            $('.sprice-mode-item[data-mode="decrease"]').addClass('active');
        } else if (increaseModeActive) {
            $btn.addClass('btn-success')
                .html('<i class="fas fa-arrow-up"></i> Increase');
            $('.sprice-mode-item[data-mode="increase"]').addClass('active');
        } else if (samePriceModeActive) {
            $btn.addClass('btn-info')
                .html('<i class="fas fa-equals"></i> Same Price');
            $('.sprice-mode-item[data-mode="same"]').addClass('active');
        } else {
            $btn.addClass('btn-secondary')
                .html('<i class="fas fa-sliders-h"></i> PrcM');
            $('.sprice-mode-item[data-mode=""]').addClass('active');
        }
    }

    // Swap the discount-input panel between %/$ and Same Price modes.
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

    $(document).ready(function() {

        function enterPriceMode(which) {
            const turningOff = !which
                || (which === 'decrease' && decreaseModeActive)
                || (which === 'increase' && increaseModeActive)
                || (which === 'same' && samePriceModeActive);

            decreaseModeActive = !turningOff && which === 'decrease';
            increaseModeActive = !turningOff && which === 'increase';
            samePriceModeActive = !turningOff && which === 'same';

            const anyOn = decreaseModeActive || increaseModeActive || samePriceModeActive;
            syncSpriceModeBtn();

            if (table) {
                const selCol = table.getColumn('_select');
                if (selCol) {
                    if (anyOn) {
                        selCol.show();
                    } else {
                        selCol.hide();
                        selectedSkus.clear();
                        updateSelectedCount();
                    }
                }
                table.redraw(true);
            }
            syncDiscountInputUi();
        }

        // Merge price sheet → VintedPricing model (same pattern as AliExpress price upload)
        $('#vintedUploadPriceSheetBtn').on('click', function() {
            const fileInput = document.getElementById('vintedPriceSheetFile');
            if (!fileInput || !fileInput.files.length) {
                showToast('Select a CSV file (use Sample CSV template)', 'error');
                return;
            }
            const fd = new FormData();
            fd.append('price_file', fileInput.files[0]);
            fd.append('_token', '{{ csrf_token() }}');
            const $btn = $(this).prop('disabled', true)
                .html('<i class="fa fa-spinner fa-spin"></i> Uploading...');
            $.ajax({
                url: '{{ route("vinted.pricing.import") }}',
                type: 'POST',
                data: fd,
                processData: false,
                contentType: false,
                success: function(res) {
                    showToast(res.message || 'Price sheet merged', res.success ? 'success' : 'error');
                    if (res.success) {
                        fileInput.value = '';
                        bootstrap.Modal.getInstance(document.getElementById('uploadVintedPriceModal'))?.hide();
                        if (table) table.setData();
                    }
                },
                error: function(xhr) {
                    showToast(xhr.responseJSON?.message || 'Upload failed', 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).html('<i class="fas fa-upload"></i> Upload &amp; Merge');
                }
            });
        });

        $('#discount-type-select').on('change', function() { syncDiscountInputUi(); });

        $(document).on('click', '.sprice-mode-item', function(e) {
            e.preventDefault();
            enterPriceMode($(this).data('mode') || '');
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
        $('#clear-sprice-btn').on('click', function() { clearSpriceForSelected(); });

        /*
         * Target ROI% / Target GPFT% bulk apply (Vinted, margin = $vintedPercentage / 100)
         * --------------------------------------------------------------------------------------
         * Back-solves SPRICE so the resulting SROI / SGPFT column matches the entered target:
         *     SROI%  = ((sprice * margin − lp − ship) / lp)     * 100
         *           → sprice = (lp * (1 + ROI%/100) + ship) / margin
         *     SGPFT% = ((sprice * margin − lp − ship) / sprice) * 100
         *           → sprice = (lp + ship) / (margin − GPFT%/100)
         * Optimistic SGPFT / SPFT / SROI are written client-side, then the existing
         * bulk save-sprice-batch endpoint reconciles them server-side. Rounding
         * is plain 2-decimal — no .99 / .49 retail snapping — because snapping would
         * shift the achieved SROI / SGPFT off the user-typed target.
         */
        const VINTED_MARGIN = {{ $vintedPercentage }} / 100;

        $('#apply-target-roi-btn').on('click', function () {
            const rawInput = $('#target-roi-input').val();
            const targetRoiPct = parseFloat(String(rawInput).replace(',', '.'));

            if (rawInput === '' || rawInput == null) {
                showToast('Please enter a Target ROI%', 'error');
                return;
            }
            if (!isFinite(targetRoiPct)) {
                showToast('Target ROI% must be a number', 'error');
                return;
            }
            if (selectedSkus.size === 0) {
                const selectColumn = table && table.getColumn ? table.getColumn('_select') : null;
                if (selectColumn) selectColumn.show();
                showToast('Please select at least one SKU first (choose a Price Mode to reveal checkboxes)', 'error');
                return;
            }

            const roiMultiplier = 1 + (targetRoiPct / 100);
            const updates = [];
            let updatedCount = 0;
            let skippedNoLp = 0;

            selectedSkus.forEach(sku => {
                const rows = table.searchRows('(Child) sku', '=', sku);
                if (!rows.length) return;
                const row = rows[0];
                const rowData = row.getData();
                if (rowData.Parent && String(rowData.Parent).startsWith('PARENT')) return;

                const lp = parseFloat(rowData['LP_productmaster']) || 0;
                if (lp <= 0) { skippedNoLp++; return; }
                const ship = parseFloat(rowData['Ship_productmaster']) || 0;

                const candidate = (lp * roiMultiplier + ship) / VINTED_MARGIN;
                const newSprice = +candidate.toFixed(2);
                if (!isFinite(newSprice) || newSprice <= 0) return;

                const sgpft = newSprice > 0 ? Math.round(((newSprice * VINTED_MARGIN - lp - ship) / newSprice) * 10000) / 100 : 0;
                const sroi  = lp > 0       ? Math.round(((newSprice * VINTED_MARGIN - lp - ship) / lp)     * 10000) / 100 : 0;

                row.update({ SPRICE: newSprice, SGPFT: sgpft, SPFT: sgpft, SROI: sroi });
                updates.push({ sku, sprice: newSprice });
                updatedCount++;
            });

            if (updates.length === 0) {
                showToast('No selected rows have a usable LP > 0', 'warning');
                return;
            }

            saveSpriceUpdates(updates);
            const note = skippedNoLp > 0 ? ` (${skippedNoLp} skipped — no LP)` : '';
            showToast(`Target ROI ${targetRoiPct}% applied to ${updatedCount} SKU(s)${note}`, 'success');
        });

        $('#apply-target-gpft-btn').on('click', function () {
            const rawInput = $('#target-gpft-input').val();
            const targetGpftPct = parseFloat(String(rawInput).replace(',', '.'));

            if (rawInput === '' || rawInput == null) {
                showToast('Please enter a Target GPFT%', 'error');
                return;
            }
            if (!isFinite(targetGpftPct)) {
                showToast('Target GPFT% must be a number', 'error');
                return;
            }
            if (selectedSkus.size === 0) {
                const selectColumn = table && table.getColumn ? table.getColumn('_select') : null;
                if (selectColumn) selectColumn.show();
                showToast('Please select at least one SKU first (choose a Price Mode to reveal checkboxes)', 'error');
                return;
            }

            const denom = VINTED_MARGIN - (targetGpftPct / 100);
            if (denom <= 0) {
                showToast(`Target GPFT% ${targetGpftPct}% is too high — must be < ${(VINTED_MARGIN * 100).toFixed(0)}% (Vinted take-home).`, 'error');
                return;
            }

            const updates = [];
            let updatedCount = 0;
            let skippedNoLp = 0;

            selectedSkus.forEach(sku => {
                const rows = table.searchRows('(Child) sku', '=', sku);
                if (!rows.length) return;
                const row = rows[0];
                const rowData = row.getData();
                if (rowData.Parent && String(rowData.Parent).startsWith('PARENT')) return;

                const lp = parseFloat(rowData['LP_productmaster']) || 0;
                if (lp <= 0) { skippedNoLp++; return; }
                const ship = parseFloat(rowData['Ship_productmaster']) || 0;

                const candidate = (lp + ship) / denom;
                const newSprice = +candidate.toFixed(2);
                if (!isFinite(newSprice) || newSprice <= 0) return;

                const sgpft = newSprice > 0 ? Math.round(((newSprice * VINTED_MARGIN - lp - ship) / newSprice) * 10000) / 100 : 0;
                const sroi  = lp > 0       ? Math.round(((newSprice * VINTED_MARGIN - lp - ship) / lp)     * 10000) / 100 : 0;

                row.update({ SPRICE: newSprice, SGPFT: sgpft, SPFT: sgpft, SROI: sroi });
                updates.push({ sku, sprice: newSprice });
                updatedCount++;
            });

            if (updates.length === 0) {
                showToast('No selected rows have a usable LP > 0', 'warning');
                return;
            }

            saveSpriceUpdates(updates);
            const note = skippedNoLp > 0 ? ` (${skippedNoLp} skipped — no LP)` : '';
            showToast(`Target GPFT ${targetGpftPct}% applied to ${updatedCount} SKU(s)${note}`, 'success');
        });

        $('#target-roi-input').on('keypress', function(e) {
            if (e.which === 13) $('#apply-target-roi-btn').click();
        });
        $('#target-gpft-input').on('keypress', function(e) {
            if (e.which === 13) $('#apply-target-gpft-btn').click();
        });

        // Sold filter is owned by the #sold-filter dropdown (TopDawg-style badges).
        let missingFilterActive = false;
        let priceGtLmpFilterActive = false;
        let priceLt80LmpFilterActive = false;
        let blueTriangleFilterActive = false;

        function vintedRowSpriceForAlert(data) {
            if (!data) return 0;
            let sprice = parseFloat(data.SPRICE != null ? data.SPRICE : data.sprice) || 0;
            if (typeof chPromoSpriceFromStdTPromo === 'function') {
                const calc = chPromoSpriceFromStdTPromo(data);
                if (calc > 0) sprice = calc;
            }
            return sprice;
        }
        function vintedHasBlueTriangle(data) {
            if (!data) return false;
            const sprice = vintedRowSpriceForAlert(data);
            const price = parseFloat(data['V Price']) || 0;
            return sprice > 0 && price > 0 && Math.round(sprice * 100) !== Math.round(price * 100);
        }
        function syncVintedTriangleBadgeState() {
            $('#vinted-blue-triangle-badge').css({
                outline: blueTriangleFilterActive ? '3px solid #ffc107' : '',
                outlineOffset: blueTriangleFilterActive ? '2px' : ''
            });
        }

        $('#zero-sold-badge').on('click', function() {
            const next = $('#sold-filter').val() === 'zero' ? 'all' : 'zero';
            $('#sold-filter').val(next);
            applyFilters();
        });
        $('#more-sold-badge').on('click', function() {
            const next = $('#sold-filter').val() === 'sold' ? 'all' : 'sold';
            $('#sold-filter').val(next);
            applyFilters();
        });
        $('#missing-badge').on('click', function() { missingFilterActive = !missingFilterActive; applyFilters(); });

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

        function roundToRetailPrice(price) { 
            if (price < 20.99) {
                return +price.toFixed(2);
            }
            return Math.ceil(price) - 0.01; 
        }

        function applyDiscount() {
            const discountType  = $('#discount-type-select').val();
            const discountValue = parseFloat($('#discount-percentage-input').val());

            if (!decreaseModeActive && !increaseModeActive && !samePriceModeActive) {
                showToast('Choose a Price Mode first (Decrease / Increase / Same Price)', 'error');
                return;
            }
            if (isNaN(discountValue) || discountValue <= 0) {
                showToast(samePriceModeActive ? 'Please enter a price (e.g. 19.99)' : 'Enter a valid value', 'error');
                return;
            }
            if (selectedSkus.size === 0) { showToast('Select at least one SKU', 'error'); return; }

            let updatedCount = 0;
            const updates = [];

            selectedSkus.forEach(sku => {
                const rows = table.searchRows('(Child) sku', '=', sku);
                if (!rows.length) return;
                const row = rows[0], rowData = row.getData();
                const currentPrice = parseFloat(rowData['V Price']) || 0;
                // Same Price mode applies even when V Price is empty;
                // %/$ modes still need a positive V Price to compute against.
                if (!samePriceModeActive && currentPrice <= 0) return;

                let newSprice;
                if (samePriceModeActive) {
                    newSprice = Math.max(0.99, discountValue);
                } else if (discountType === 'percentage') {
                    newSprice = decreaseModeActive ? currentPrice * (1 - discountValue / 100) : currentPrice * (1 + discountValue / 100);
                } else {
                    newSprice = decreaseModeActive ? currentPrice - discountValue : currentPrice + discountValue;
                }
                newSprice = Math.max(0.99, roundToRetailPrice(newSprice));

                const percentage = {{ $vintedPercentage }} / 100;
                const lp   = parseFloat(rowData['LP_productmaster']) || 0;
                const ship = parseFloat(rowData['Ship_productmaster']) || 0;
                const sgpft = newSprice > 0 ? Math.round(((newSprice * percentage - lp - ship) / newSprice) * 10000) / 100 : 0;
                const sroi  = lp > 0    ? Math.round(((newSprice * percentage - lp - ship) / lp) * 10000) / 100 : 0;

                row.update({ SPRICE: newSprice, SGPFT: sgpft, SPFT: sgpft, SROI: sroi });
                updates.push({ sku, sprice: newSprice });
                updatedCount++;
            });

            if (updates.length) saveSpriceUpdates(updates);
            const action = samePriceModeActive ? 'Same Price' : (decreaseModeActive ? 'Decrease' : 'Increase');
            showToast(`${action} applied to ${updatedCount} SKU(s)`, 'success');
            $('#discount-percentage-input').val('');
        }

        function saveSpriceUpdates(updates) {
            $.ajax({
                url: '{{ route("vinted.pricing.save.sprice.batch") }}',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                data: { updates },
                success: function(response) {
                    if (response.success) console.log('Vinted SPRICE saved:', response.updated, 'records');
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
                url: '{{ route("vinted.pricing.save.sprice.tabulator") }}',
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
        table = new Tabulator('#vinted-table', {
            ajaxURL: '{{ route("vinted.pricing.data") }}',
            ajaxSorting: false,
            layout: 'fitDataStretch',
            pagination: true,
            paginationSize: 100,
            paginationSizeSelector: [10, 25, 50, 100, 200],
            paginationCounter: 'rows',
            columnCalcs: 'both',
            langs: { default: { pagination: { page_size: 'SKU Count' } } },
            initialSort: [{ column: 'V L30', dir: 'desc' }],
            rowFormatter: function(row) {
                if (row.getData().Parent && row.getData().Parent.startsWith('PARENT'))
                    row.getElement().style.backgroundColor = '#fffef2';
            },
            columns: [
                { title: 'Parent', field: 'Parent', headerFilter: 'input', headerFilterPlaceholder: 'Search Parent...', cssClass: 'text-primary', tooltip: true, frozen: true, width: 150, visible: false },
                (window.ParentExpand ? ParentExpand.columnDef() : { title: 'P', field: '_parent_expand', width: 36, headerSort: false, frozen: true }),
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
                {
                    title: 'Links', field: 'links_column', frozen: true, width: 55, hozAlign: 'center', headerSort: false, visible: true,
                    tooltip: 'Double-click to add / edit links',
                    formatter: function(cell) {
                        const d = cell.getRow().getData();
                        const buyerLink = d['B Link'] || '';
                        const sellerLink = d['S Link'] || '';
                        let html = '<div style="display:flex;flex-direction:column;gap:4px;align-items:center;">';
                        if (sellerLink) {
                            html += `<a href="${sellerLink}" target="_blank" class="text-info" style="font-size:12px;text-decoration:none;"><i class="fa fa-link"></i> S</a>`;
                        }
                        if (buyerLink) {
                            html += `<a href="${buyerLink}" target="_blank" class="text-success" style="font-size:12px;text-decoration:none;"><i class="fa fa-link"></i> B</a>`;
                        }
                        if (!sellerLink && !buyerLink) {
                            html += '<span class="text-muted" style="font-size:12px;">-</span>';
                        }
                        html += '</div>';
                        return html;
                    },
                    cellDblClick: function(e, cell) {
                        e.stopPropagation();
                        openEditLinksModal(cell.getRow());
                    }
                },
                { title: 'INV',  field: 'INV',  hozAlign: 'center', width: 50, sorter: 'number' },
                { title: 'OV L30', field: 'L30', hozAlign: 'center', width: 50, sorter: 'number' },
                {
                    title: 'Dil', field: 'Dil%', hozAlign: 'center', sorter: 'number', width: 50,
                    formatter: function(cell) {
                        const d = cell.getRow().getData();
                        const inv = parseFloat(d.INV) || 0;
                        if (inv === 0) return '<span style="color:#6c757d;">0%</span>';
                        const dil = (parseFloat(d['L30']) || 0) / inv * 100;
                        const color = dil < 16.66 ? '#a00211' : dil < 25 ? '#ffc107' : dil < 50 ? '#28a745' : '#e83e8c';
                        return `<span style="color:${color};font-weight:600;">${Math.round(dil)}%</span>`;
                    }
                },
                { title: 'V L30', field: 'V L30', hozAlign: 'center', width: 50, sorter: 'number' },
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
                    title: 'Price', field: 'V Price', hozAlign: 'center', sorter: 'number', width: 70,
                    formatter: function(cell) {
                        const v = parseFloat(cell.getValue() || 0);
                        const rowData = cell.getRow().getData();
                        const lmpTri = (window.PriceGtLmpBadge ? PriceGtLmpBadge.triangleHtml(v, rowData.lmp_price || rowData.lmp || rowData.LMP) : '');
                        const purpleTri = (window.PriceLt80LmpBadge ? PriceLt80LmpBadge.triangleHtml(v, rowData.lmp_price || rowData.lmp || rowData.LMP) : '');
                        if (v === 0) return `<span style="color:#a00211;font-weight:600;">$0.00 <i class="fas fa-exclamation-triangle"></i></span>`;
                        return `$${v.toFixed(2)}${lmpTri}${purpleTri}`;
                    }
                },
                {
                    title: "<span style='color:#a00211;'>Missing</span>", field: 'Missing', hozAlign: 'center', width: 60,
                    formatter: function(cell) {
                        const d = cell.getRow().getData();
                        const price = parseFloat(d['V Price']) || 0, inv = parseFloat(d.INV) || 0, nrReq = d.nr_req || 'REQ';
                        if (nrReq === 'NR' || inv === 0) return '';
                        return price === 0 ? '<span style="color:#a00211;font-weight:600;">M</span>' : '';
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
                    title: 'NPFT', field: 'PFT %', hozAlign: 'center', sorter: 'number', width: 50,
                    formatter: function(cell) {
                        // Vinted has no ads — NPFT% = GPFT%
                        const p = parseFloat(cell.getRow().getData()['GPFT%'] ?? cell.getValue());
                        if (!isFinite(p)) return '';
                        const color = p < 10 ? '#a00211' : p < 15 ? '#ffc107' : p < 20 ? '#3591dc' : p <= 40 ? '#28a745' : '#e83e8c';
                        return `<span style="color:${color};font-weight:600;">${p.toFixed(0)}%</span>`;
                    }
                },
                {
                    title: 'GROI%', field: 'ROI%', hozAlign: 'center', sorter: 'number', width: 50,
                    formatter: function(cell) {
                        const p = parseFloat(cell.getValue());
                        if (!isFinite(p)) return '';
                        const color = p < 40 ? '#a00211' : p < 75 ? '#ffc107' : p < 125 ? '#28a745' : '#d63384';
                        return `<span style="color:${color};font-weight:600;">${p.toFixed(0)}%</span>`;
                    }
                },
                {
                    title: 'NROI', field: 'NROI', hozAlign: 'center', sorter: 'number', width: 50,
                    formatter: function(cell) {
                        // Vinted has no ads — NROI% = GROI% (ROI%)
                        const p = parseFloat(cell.getRow().getData()['ROI%']);
                        if (!isFinite(p)) return '';
                        const color = p < 40 ? '#a00211' : p < 75 ? '#ffc107' : p < 125 ? '#28a745' : '#d63384';
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
                ...(typeof channelPromoAnalyticsColumns === 'function' ? channelPromoAnalyticsColumns() : (typeof channelPromoPricingColumns === 'function' ? channelPromoPricingColumns() : [])),
                {
                    title: 'SPRICE', field: 'SPRICE', hozAlign: 'center', editor: 'number',
                    editorParams: { min: 0, step: 0.01 }, sorter: 'number', width: 80,
                    headerTooltip: "S PRC = Std × (1 − (PRMT% + cvr%)/100). Blue triangle = S PRC ≠ V Price. Red text = S PRC > LMP.",
                    formatter: function(cell) {
                        const d = cell.getRow().getData();
                        let value = parseFloat(cell.getValue() || 0);
                        if (typeof chPromoSpriceFromStdTPromo === 'function') {
                            const calc = chPromoSpriceFromStdTPromo(d);
                            if (calc > 0) value = calc;
                        }
                        if (!(value > 0) && !(parseFloat(cell.getValue() || 0) > 0)) {
                            return '';
                        }
                        const display = value > 0 ? value : (parseFloat(cell.getValue() || 0) || 0);
                        let bg = '';
                        if (d.SPRICE_STATUS === 'pushed') bg = 'background-color:#fff3cd;';
                        else if (d.SPRICE_STATUS === 'applied') bg = 'background-color:#d4edda;';
                        else if (d.has_custom_sprice) bg = 'background-color:#e7f1ff;';
                        const live = parseFloat(d['V Price']) || 0;
                        const lmp = parseFloat(d.lmp_price || d.lmp || d.LMP) || 0;
                        const formatted = '$' + display.toFixed(2);
                        const overLmp = lmp > 0 && display > lmp;
                        const priceHtml = overLmp
                            ? '<span style="color:#dc3545;font-weight:600;' + bg + 'padding:2px 6px;border-radius:3px;">' + formatted + '</span>'
                            : '<span style="font-weight:600;' + bg + 'padding:2px 6px;border-radius:3px;">' + formatted + '</span>';
                        const blueTri = (live > 0 && Math.round(display * 100) !== Math.round(live * 100))
                            ? '<i class="fas fa-exclamation-triangle" style="color:#0d6efd;font-size:10px;margin-left:3px;" title="S PRC $'
                                + display.toFixed(2) + ' ≠ V Price $' + live.toFixed(2) + '"></i>'
                            : '';
                        return '<span style="white-space:nowrap;display:inline-flex;align-items:center;gap:2px;">' + priceHtml + blueTri + '</span>';
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
                    title: 'SNPFT', field: 'SPFT', hozAlign: 'center', sorter: 'number', width: 50,
                    formatter: function(cell) {
                        // Vinted has no ads — SNPFT = SGPFT
                        const p = parseFloat(cell.getRow().getData().SGPFT ?? cell.getValue());
                        if (!isFinite(p)) return '';
                        const color = p < 10 ? '#a00211' : p < 15 ? '#ffc107' : p < 20 ? '#3591dc' : p <= 40 ? '#28a745' : '#e83e8c';
                        return `<span style="color:${color};font-weight:600;">${p.toFixed(0)}%</span>`;
                    }
                },
                {
                    title: 'SNROI', field: 'SROI', hozAlign: 'center', sorter: 'number', width: 50,
                    formatter: function(cell) {
                        // Vinted has no ads — SNROI = gross SROI (no Ads% cut)
                        const p = parseFloat(cell.getValue());
                        if (!isFinite(p)) return '';
                        const color = p < 40 ? '#a00211' : p < 75 ? '#ffc107' : p < 125 ? '#28a745' : '#d63384';
                        return `<span style="color:${color};font-weight:600;">${p.toFixed(0)}%</span>`;
                    }
                }
            ]
        });

        if (window.ParentExpand) {
            ParentExpand.configure({
                parentField: 'Parent',
                skuField: '(Child) sku',
                getTable: function() { return table; },
                getDataset: function() { return allTableData; },
                onAfterExpand: function() { if (typeof updateSummary === 'function') updateSummary(); },
                onCollapse: function() { applyFilters(); }
            });
            ParentExpand.bind();
        }

        $('#sku-search, #parent-search').on('keyup', function() {
            table.setFilter([
                { field: '(Child) sku', type: 'like', value: $('#sku-search').val() || '' },
                { field: 'Parent', type: 'like', value: $('#parent-search').val() || '' }
            ]);
        });

        $(document).on('change', '.nr-req-dropdown', function() {
            const $cell = $(this).closest('.tabulator-cell');
            const row = table.getRow($cell.closest('.tabulator-row')[0]);
            const sku = row.getData()['(Child) sku'];
            const newValue = $(this).val();
            $.ajax({
                url: '{{ route("vinted.pricing.update.nr") }}',
                method: 'POST',
                data: { sku, nr_req: newValue, _token: '{{ csrf_token() }}' },
                success: function() { showToast(`${sku}: updated to ${newValue}`, 'success'); row.update({ nr_req: newValue }); },
                error: function() { showToast(`Failed to update ${sku}`, 'error'); }
            });
        });

        // Open Edit Links modal
        let editLinksRow = null;
        function openEditLinksModal(row) {
            if (!row) return;
            editLinksRow = row;
            const d = row.getData();
            $('#editLinksSku').val(d['(Child) sku']);
            $('#editLinksSkuDisplay').text(d['(Child) sku']);
            $('#editSellerLink').val(d['S Link'] || '');
            $('#editBuyerLink').val(d['B Link'] || '');
            $('#editLinksError').hide().text('');
            new bootstrap.Modal(document.getElementById('editLinksModal')).show();
        }

        // Save links
        $(document).on('click', '#saveLinksBtn', function() {
            const sku = $('#editLinksSku').val();
            const sellerLink = $('#editSellerLink').val().trim();
            const buyerLink = $('#editBuyerLink').val().trim();
            const $err = $('#editLinksError');
            $err.hide().text('');

            const $btn = $(this).prop('disabled', true);
            $.ajax({
                url: '{{ route("vinted.pricing.update.links") }}',
                method: 'POST',
                data: { sku, seller_link: sellerLink, buyer_link: buyerLink, _token: '{{ csrf_token() }}' },
                success: function(res) {
                    if (editLinksRow) {
                        editLinksRow.update({ 'S Link': res.seller_link || '', 'B Link': res.buyer_link || '' })
                            .then(function() { editLinksRow.reformat(); })
                            .catch(function() { editLinksRow.reformat(); });
                    }
                    showToast(`${sku}: links saved`, 'success');
                    bootstrap.Modal.getInstance(document.getElementById('editLinksModal'))?.hide();
                },
                error: function(xhr) {
                    const msg = xhr.responseJSON?.message || 'Failed to save links.';
                    $err.text(msg).show();
                },
                complete: function() { $btn.prop('disabled', false); }
            });
        });

        table.on('cellEdited', function(cell) {
            if (cell.getField() !== 'SPRICE') return;
            const row = cell.getRow(), d = row.getData();
            const newSprice = parseFloat(cell.getValue()) || 0;
            const percentage = {{ $vintedPercentage }} / 100;
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
            if (window.ParentExpand && ParentExpand.isExpanded()) {
                ParentExpand.beforeFilters(function() { applyFilters(); });
                return;
            }
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
                else if (gpft === '50plus') table.addFilter('GPFT%', '>=', 50);
                else { const [min, max] = gpft.split('-').map(Number); table.addFilter('GPFT%', '>=', min); table.addFilter('GPFT%', '<', max); }
            }

            if (cvrF !== 'all') {
                table.addFilter(function(d) {
                    const ov = parseFloat(d.L30) || 0;
                    const sold = parseFloat(d['V L30']) || 0;
                    const cvrPercent = ov > 0 ? (sold / ov) * 100 : 0;
                    const cvrRounded = Math.round(cvrPercent * 100) / 100;
                    if (cvrF === '0-0') return cvrRounded === 0;
                    if (cvrF === '0-3') return cvrRounded > 0 && cvrRounded <= 3;
                    if (cvrF === '3-7') return cvrRounded > 3 && cvrRounded <= 7;
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
                    if (roi === '40-75') return roiVal >= 40 && roiVal < 75;
                    if (roi === '75-125') return roiVal >= 75 && roiVal < 125;
                    if (roi === 'gt125') return roiVal >= 125;
                    return true;
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

            const soldFilter = $('#sold-filter').val();
            if (soldFilter === 'zero') table.addFilter('V L30', '=', 0);
            else if (soldFilter === 'sold') table.addFilter('V L30', '>', 0);
            if (missingFilterActive) table.addFilter(d => { return (d.nr_req || 'REQ') === 'REQ' && (parseFloat(d.INV) || 0) > 0 && (parseFloat(d['V Price']) || 0) === 0; });
            if (priceGtLmpFilterActive && window.PriceGtLmpBadge) {
                table.addFilter(function(data) {
                    return PriceGtLmpBadge.hasRedTriangle(data, 'V Price');
                });
            }
            if (priceLt80LmpFilterActive && window.PriceLt80LmpBadge) {
                table.addFilter(function(data) {
                    return PriceLt80LmpBadge.hasPurpleTriangle(data, 'V Price');
                });
            }
            if (blueTriangleFilterActive) {
                table.addFilter(function(data) {
                    return vintedHasBlueTriangle(data);
                });
            }

            // TopDawg-style active-filter ring on clickable badges
            $('#zero-sold-badge').toggleClass('active-filter', soldFilter === 'zero');
            $('#more-sold-badge').toggleClass('active-filter', soldFilter === 'sold');
            $('#missing-badge').toggleClass('active-filter', missingFilterActive);

            updateSummary();
        }

        if (window.PriceGtLmpBadge) {
            PriceGtLmpBadge.bind({
                badge: '#vinted-price-gt-lmp-badge',
                getActive: function() { return priceGtLmpFilterActive; },
                onToggle: function(on) {
                    priceGtLmpFilterActive = on;
                    if (on) blueTriangleFilterActive = false;
                    applyFilters();
                }
            });
        }
        if (window.PriceLt80LmpBadge) {
            PriceLt80LmpBadge.bind({
                badge: '#vinted-price-lt80-lmp-badge',
                getActive: function() { return priceLt80LmpFilterActive; },
                onToggle: function(on) {
                    priceLt80LmpFilterActive = on;
                    if (on) blueTriangleFilterActive = false;
                    applyFilters();
                }
            });
        }
        $('#vinted-blue-triangle-badge').on('click', function() {
            blueTriangleFilterActive = !blueTriangleFilterActive;
            if (blueTriangleFilterActive) {
                priceGtLmpFilterActive = false;
                priceLt80LmpFilterActive = false;
            }
            applyFilters();
            syncVintedTriangleBadgeState();
        });

        $('#inventory-filter, #nrl-filter, #gpft-filter, #cvr-filter, #dil-filter, #roi-filter, #sold-filter').on('change', function() { applyFilters(); });

        function updateSummary() {
            if (!table) return;
            const data = table.getData('active').filter(r => !(r.Parent && String(r.Parent).startsWith('PARENT')));
            let totalVl30 = 0, zeroSold = 0, moreSold = 0, missingCount = 0;
            let totalProfit = 0, totalSales = 0, totalCogs = 0;

            data.forEach(row => {
                const vL30 = parseInt(row['V L30'], 10) || 0;
                totalVl30 += vL30;
                vL30 === 0 ? zeroSold++ : moreSold++;

                const price = parseFloat(row['V Price']) || 0;
                const inv = parseFloat(row.INV) || 0;
                const nrReq = row.nr_req || 'REQ';
                if (nrReq === 'REQ' && inv > 0 && price === 0) missingCount++;

                totalProfit += parseFloat(row.Profit) || 0;
                totalSales += parseFloat(row['Sales L30']) || 0;
                const lp = parseFloat(row.LP_productmaster) || 0;
                totalCogs += lp * vL30;
            });

            const gpftPct = totalSales > 0 ? (totalProfit / totalSales) * 100 : 0;
            const groiPct = totalCogs > 0 ? (totalProfit / totalCogs) * 100 : 0;

            $('#total-rows-badge').text('Rows: ' + data.length.toLocaleString());
            $('#total-sales-badge').text('Sales: $' + Math.round(totalSales).toLocaleString());
            $('#total-v-l30-badge').text('V L30: ' + totalVl30.toLocaleString());
            $('#zero-sold-badge').text('0 Sold: ' + zeroSold.toLocaleString());
            $('#more-sold-badge').text('> 0 Sold: ' + moreSold.toLocaleString());
            $('#gpft-pct-badge').text('GPFT: ' + Math.round(gpftPct) + '%');
            $('#groi-pct-badge').text('GROI: ' + Math.round(groiPct) + '%');
            $('#missing-badge').text('Missing L: ' + missingCount.toLocaleString());
            if (window.PriceGtLmpBadge && table) {
                PriceGtLmpBadge.update('#vinted-price-gt-lmp-badge', table.getData(), 'vinted', 'V Price');
                if (window.PriceLt80LmpBadge) {
                    PriceLt80LmpBadge.update('#vinted-price-lt80-lmp-badge', table.getData(), 'vinted', 'V Price');
                }
            }
            let blueTriangleCount = 0;
            (table ? table.getData() : data).forEach(function(row) {
                if (vintedHasBlueTriangle(row)) blueTriangleCount++;
            });
            $('#vinted-blue-triangle-badge').html(
                '<i class="fas fa-exclamation-triangle"></i> ' + blueTriangleCount.toLocaleString()
            );
            if (typeof syncVintedTriangleBadgeState === 'function') syncVintedTriangleBadgeState();
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
            $.ajax({ url: '{{ route("vinted.pricing.column.set") }}', method: 'POST', data: { visibility, _token: '{{ csrf_token() }}' } });
        }

        function applyColumnVisibilityFromServer() {
            $.ajax({
                url: '{{ route("vinted.pricing.column.get") }}', method: 'GET',
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
        table.on('dataLoaded', function(data) {
            allTableData = Array.isArray(data) ? data : (table.getData('all') || []);
            if (window.ParentExpand) ParentExpand.captureDataset(allTableData);
            setTimeout(function() { applyFilters(); updateSummary(); }, 100);
        });
        table.on('renderComplete', function() { setTimeout(function() { updateSummary(); }, 100); });

        document.getElementById('column-dropdown-menu').addEventListener('change', function(e) {
            if (e.target.classList.contains('column-toggle')) {
                const col = table.getColumn(e.target.dataset.field);
                if (col) { e.target.checked ? col.show() : col.hide(); saveColumnVisibilityToServer(); }
            }
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
            link.setAttribute('download', 'vinted_pricing_' + new Date().toISOString().slice(0,10) + '.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link); link.click(); document.body.removeChild(link);
            showToast('Export downloaded!', 'success');
        });
    });
</script>
@endsection
