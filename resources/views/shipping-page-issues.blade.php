@extends('layouts.vertical', ['title' => 'Shipping Cost Issue', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/css/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }

        .tabulator {
            border: 1px solid #dee2e6;
            font-size: 12px;
        }

        .tabulator .tabulator-header {
            background: #8fb9fe;
            border-bottom: 1px solid #7aa8fd;
        }

        .tabulator .tabulator-header .tabulator-col {
            background: #8fb9fe;
            border-right: 1px solid rgba(255, 255, 255, 0.35);
        }

        .tabulator .tabulator-header .tabulator-col .tabulator-col-content {
            padding: 6px 4px;
        }

        .tabulator .tabulator-header .tabulator-col .tabulator-col-title {
            font-weight: 700;
            color: #000;
            white-space: nowrap;
        }

        .tabulator .tabulator-row {
            min-height: 32px;
        }

        .tabulator .tabulator-row:nth-child(even) {
            background-color: #fcfcfd;
        }

        .tabulator .tabulator-row:hover {
            background-color: #f1f5ff;
        }

        .tabulator .tabulator-cell {
            padding: 6px 8px;
            border-right: 1px solid #f1f3f5;
            white-space: nowrap;
        }

        .tabulator .tabulator-footer {
            border-top: 1px solid #dee2e6;
            background: #f8f9fa;
        }

        .tabulator-paginator label {
            margin-right: 5px;
        }

        .sci-history-dot {
            display: inline-block;
            width: 10px;
            height: 10px;
            margin-left: 6px;
            border-radius: 50%;
            background: #fff;
            box-shadow: 0 0 0 1px rgba(0,0,0,0.15);
            cursor: pointer;
            vertical-align: middle;
        }
        #sci-issues-badge .sci-history-dot { background: #fff; }
        #sci-loss-gain-badge .sci-history-dot { background: #212529; }

        .sci-action-btn {
            border: 0;
            background: transparent;
            color: #0d6efd;
            padding: 2px 4px;
            cursor: pointer;
        }
        .sci-action-btn:hover { color: #0a58ca; }
        .sci-action-btn:disabled { color: #adb5bd; cursor: not-allowed; }

        .label-type-dropdown {
            border: 1px solid #94a3b8;
            border-radius: 999px;
            padding: 2px 8px;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            min-width: 78px;
        }
        .label-type-dropdown.label-type-env { background:#fecaca; border-color:#ef4444; color:#991b1b; }
        .label-type-dropdown.label-type-std { background:#bbf7d0; border-color:#22c55e; color:#166534; }
        .label-type-dropdown.label-type-osize { background:#e9d5ff; border-color:#a855f7; color:#6b21a8; }
        .label-type-dropdown.label-type-pallet { background:#bfdbfe; border-color:#3b82f6; color:#1e40af; }

        .sci-lg-pos { color: #28a745; font-weight: bold; }
        .sci-lg-neg { color: #dc3545; font-weight: bold; }
        .sci-sku-thumb {
            width: 32px;
            height: 32px;
            object-fit: contain;
            border-radius: 2px;
            border: 1px solid #e9ecef;
            background: #fff;
        }
        .sci-sku-thumb-empty {
            width: 32px;
            height: 32px;
            border-radius: 2px;
            border: 1px dashed #ced4da;
            background: #f8f9fa;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #adb5bd;
            font-size: 11px;
        }

        .select2-container { width: 100% !important; }

        .sci-section-card {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 0.375rem;
            padding: 0.9rem;
            margin-bottom: 0.85rem;
        }
        .sci-section-title {
            font-size: 0.85rem;
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.7rem;
        }

        #sci-history-modal.modal { padding: 0 !important; }
        #sci-history-modal .modal-dialog { max-width: 100%; width: 100%; margin: 0; }
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Shipping Cost Issue',
        'sub_title' => 'Product Masters',
    ])

    <div class="toast-container"></div>

    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <span class="badge bg-primary fs-6 p-2" id="sci-issues-badge" style="color: white; font-weight: bold;" title="Count of shipping cost issues in L30">
                        Issue: <span id="sci-issues-l30-value">0</span>
                        <span class="sci-history-dot" data-metric="issues" title="L30 issue count history"></span>
                    </span>
                    <span class="badge bg-warning fs-6 p-2" id="sci-loss-gain-badge" style="color: black; font-weight: bold;" title="Sum of Loss Gain Before Action in L30">
                        Loss/Gain: <span id="sci-loss-gain-l30-value">0.00</span>
                        <span class="sci-history-dot" data-metric="loss_gain" title="L30 Loss/Gain history"></span>
                    </span>
                    <button type="button" class="btn btn-sm btn-primary" id="btn-add-issue" data-bs-toggle="modal" data-bs-target="#issueModal">
                        <i class="fa fa-plus"></i> Add Shipping Cost Issue
                    </button>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div id="sci-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <div id="sci-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>

{{-- Issue modal --}}
<div class="modal fade" id="issueModal" tabindex="-1" aria-labelledby="issueModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="issueModalLabel"><i class="fas fa-truck-fast me-2"></i>Add Shipping Cost Issue</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="issueForm">
                <div class="modal-body">
                    <input type="hidden" id="issue_id" value="">
                    <input type="hidden" id="ship_display" value="">
                    <input type="hidden" id="zone_display" value="">
                    <input type="hidden" id="state_display" value="">
                    <input type="hidden" id="loss_gain_display" value="">

                    <div class="sci-section-card">
                        <div class="sci-section-title">Order</div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="o_date" class="form-label">O Date</label>
                                <input type="date" class="form-control" id="o_date" name="o_date">
                            </div>
                            <div class="col-md-6">
                                <label for="o_number" class="form-label">O Number</label>
                                <input type="text" class="form-control" id="o_number" name="o_number" maxlength="100" placeholder="Order number">
                            </div>
                            <div class="col-md-6">
                                <label for="channel" class="form-label">Channel</label>
                                <select class="form-select" id="channel" name="channel">
                                    <option value="">Select Channel</option>
                                    @foreach($channels as $ch)
                                        <option value="{{ $ch->channel }}">{{ $ch->alias ?: $ch->channel }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="sku" class="form-label">SKU</label>
                                <div class="d-flex align-items-center gap-2">
                                    <select class="form-select" id="sku" name="sku">
                                        <option value="">Select SKU</option>
                                        @foreach($skus as $sku)
                                            <option value="{{ $sku }}">{{ $sku }}</option>
                                        @endforeach
                                    </select>
                                    <div id="sku_image_preview_wrap" class="flex-shrink-0 d-flex align-items-center justify-content-center">
                                        <div class="sci-sku-thumb-empty" id="sku_image_placeholder" style="width:44px;height:44px;"><i class="fas fa-image"></i></div>
                                        <img id="sku_image_preview" src="" alt="SKU" class="d-none" style="width:44px;height:44px;">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="sci-section-card">
                        <div class="sci-section-title">Shipping &amp; Amounts</div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="pin_code" class="form-label">Pin Code</label>
                                <input type="text" class="form-control" id="pin_code" name="pin_code" maxlength="20" placeholder="US ZIP">
                            </div>
                            <div class="col-md-4">
                                <label for="amount_received" class="form-label">Amount Received</label>
                                <input type="text" class="form-control" id="amount_received" name="amount_received" maxlength="100" placeholder="0.00">
                            </div>
                            <div class="col-md-4">
                                <label for="amount_paid" class="form-label">Amount Paid</label>
                                <input type="text" class="form-control" id="amount_paid" name="amount_paid" maxlength="100" placeholder="0.00">
                            </div>
                        </div>
                    </div>

                    <div class="sci-section-card mb-0">
                        <div class="sci-section-title">Action</div>
                        <label for="action_taken" class="form-label">Action Taken</label>
                        <textarea class="form-control" id="action_taken" name="action_taken" rows="3" placeholder="Describe action taken"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4" id="issueSaveBtn">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Dim/Wt modal --}}
<div class="modal fade" id="editDimWtModal" tabindex="-1" aria-labelledby="editDimWtModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editDimWtModalLabel"><i class="fas fa-ruler-combined me-2"></i>Edit Dimensions &amp; Weight</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editDimWtForm">
                    <input type="hidden" id="editProductId" name="product_id">
                    <input type="hidden" id="editSku" name="sku">
                    <input type="hidden" id="editParent" name="parent">

                    <div class="sci-section-card">
                        <div class="sci-section-title">Item Dimension</div>
                        <div class="row g-3">
                            <div class="col-md-4"><label class="form-label" for="editWtActKg">Weight ACT (Kg)</label><input type="number" step="0.01" class="form-control" id="editWtActKg"></div>
                            <div class="col-md-4"><label class="form-label" for="editWtAct">Itm wt GW</label><input type="number" step="0.01" class="form-control" id="editWtAct"></div>
                            <div class="col-md-4"><label class="form-label" for="editWtDecl">Itm wt GW Decl</label><input type="number" step="0.01" class="form-control" id="editWtDecl"></div>
                            <div class="col-md-4"><label class="form-label" for="editL">Length (inch)</label><input type="number" step="0.01" class="form-control" id="editL"></div>
                            <div class="col-md-4"><label class="form-label" for="editW">Width (inch)</label><input type="number" step="0.01" class="form-control" id="editW"></div>
                            <div class="col-md-4"><label class="form-label" for="editH">Height (inch)</label><input type="number" step="0.01" class="form-control" id="editH"></div>
                            <div class="col-md-4"><label class="form-label" for="editLDecl">L Decl</label><input type="number" step="0.01" class="form-control" id="editLDecl"></div>
                            <div class="col-md-4"><label class="form-label" for="editWDecl">W Decl</label><input type="number" step="0.01" class="form-control" id="editWDecl"></div>
                            <div class="col-md-4"><label class="form-label" for="editHDecl">H Decl</label><input type="number" step="0.01" class="form-control" id="editHDecl"></div>
                            <div class="col-md-4"><label class="form-label" for="editLCm">Length (CM)</label><input type="number" step="0.01" class="form-control" id="editLCm"></div>
                            <div class="col-md-4"><label class="form-label" for="editWCm">Width (CM)</label><input type="number" step="0.01" class="form-control" id="editWCm"></div>
                            <div class="col-md-4"><label class="form-label" for="editHCm">Height (CM)</label><input type="number" step="0.01" class="form-control" id="editHCm"></div>
                        </div>
                    </div>

                    <div class="sci-section-card mb-0">
                        <div class="sci-section-title">Carton Dimension</div>
                        <div class="row g-3">
                            <div class="col-md-3"><label class="form-label" for="editCtnL">CTN L (CM)</label><input type="number" step="0.01" class="form-control" id="editCtnL"></div>
                            <div class="col-md-3"><label class="form-label" for="editCtnW">CTN W (CM)</label><input type="number" step="0.01" class="form-control" id="editCtnW"></div>
                            <div class="col-md-3"><label class="form-label" for="editCtnH">CTN H (CM)</label><input type="number" step="0.01" class="form-control" id="editCtnH"></div>
                            <div class="col-md-3"><label class="form-label" for="editCtnQty">CTN Qty</label><input type="number" step="0.01" class="form-control" id="editCtnQty"></div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary px-4" id="saveDimWtBtn"><i class="fas fa-save me-2"></i>Save Changes</button>
            </div>
        </div>
    </div>
</div>

{{-- History modal --}}
<div class="modal fade p-0" id="sci-history-modal" tabindex="-1" aria-labelledby="sci-history-modal-label" aria-hidden="true">
    <div class="modal-dialog shadow-none m-0 mx-0">
        <div class="modal-content" style="overflow: hidden;">
            <div class="modal-header bg-info text-white py-1 px-3">
                <h6 class="modal-title mb-0" style="font-size: 13px;" id="sci-history-modal-label">
                    <i class="fas fa-chart-area me-1"></i>History
                </h6>
                <div class="d-flex align-items-center gap-2">
                    <select id="sci-history-range" class="form-select form-select-sm bg-white" style="width: 110px; height: 26px; font-size: 11px; padding: 1px 8px;">
                        <option value="7">7 Days</option>
                        <option value="30" selected>30 Days</option>
                        <option value="60">60 Days</option>
                        <option value="90">90 Days</option>
                        <option value="0">Lifetime</option>
                    </select>
                    <button type="button" class="btn-close btn-close-white" style="font-size: 10px;" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body p-2">
                <div id="sci-history-chart-wrapper" style="height: 20vh; display: flex; align-items: stretch;">
                    <div style="flex: 1; min-width: 0; position: relative;">
                        <canvas id="sci-history-chart"></canvas>
                    </div>
                    <div style="width: 100px; display: flex; flex-direction: column; justify-content: center; gap: 8px; padding: 6px 8px; border-left: 1px solid #e9ecef; background: #f8f9fa;">
                        <div style="text-align: center;">
                            <div style="font-size: 8px; font-weight: 700; color: #dc3545;">Highest</div>
                            <div id="sci-history-high" style="font-size: 13px; font-weight: 700; color: #dc3545;">-</div>
                        </div>
                        <div style="text-align: center; border-top: 1px dashed #adb5bd; border-bottom: 1px dashed #adb5bd; padding: 4px 0;">
                            <div style="font-size: 8px; font-weight: 700; color: #6c757d;">Median</div>
                            <div id="sci-history-median" style="font-size: 13px; font-weight: 700; color: #6c757d;">-</div>
                        </div>
                        <div style="text-align: center;">
                            <div style="font-size: 8px; font-weight: 700; color: #198754;">Lowest</div>
                            <div id="sci-history-low" style="font-size: 13px; font-weight: 700; color: #198754;">-</div>
                        </div>
                    </div>
                </div>
                <div id="sci-history-empty" class="text-center text-muted py-4" style="display: none;">No history data yet.</div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script-after-vite')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const urls = {
        data: @json(route('shipping.page.issues.data')),
        store: @json(route('shipping.page.issues.store')),
        updateBase: @json(url('/shipping-page-issues')),
        shipLookup: @json(route('shipping.page.issues.ship')),
        pinLookup: @json(route('shipping.page.issues.pin')),
        summary: @json(route('shipping.page.issues.summary')),
        history: @json(route('shipping.page.issues.history')),
        dimUpdate: @json(route('dim.wt.master.update')),
    };

    let table = null;
    let pinLookupTimer = null;
    let historyChart = null;
    let historyMetric = 'issues';
    let skipNextModalReset = false;
    const modalEl = document.getElementById('issueModal');
    const historyModalEl = document.getElementById('sci-history-modal');
    const LABEL_TYPE_OPTIONS = ['ENV', 'STD', 'O-Size', 'Pallet'];
    const LABEL_TYPE_COLOR = {
        'ENV': 'label-type-env',
        'STD': 'label-type-std',
        'O-Size': 'label-type-osize',
        'Pallet': 'label-type-pallet',
    };

    function getBootstrap() { return window.bootstrap || null; }
    function getModal() {
        const bs = getBootstrap();
        return (bs && modalEl) ? bs.Modal.getOrCreateInstance(modalEl) : null;
    }
    function getHistoryModal() {
        const bs = getBootstrap();
        return (bs && historyModalEl) ? bs.Modal.getOrCreateInstance(historyModalEl) : null;
    }
    function showModal() { const m = getModal(); if (m) m.show(); }
    function hideModal() { const m = getModal(); if (m) m.hide(); }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }
    function formatMoney(n) {
        const num = Number(n);
        if (!Number.isFinite(num)) return '0.00';
        return num.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function toNumber(value) {
        if (value === null || value === undefined) return null;
        const raw = String(value).trim();
        if (!raw) return null;
        const normalized = raw.replace(/[^0-9.\-]/g, '');
        if (!normalized || normalized === '-' || normalized === '.') return null;
        const n = parseFloat(normalized);
        return Number.isFinite(n) ? n : null;
    }
    function normalizeLabelType(raw) {
        const v = String(raw == null ? '' : raw).trim();
        return LABEL_TYPE_OPTIONS.includes(v) ? v : 'STD';
    }

    function calcLossGain() {
        const received = toNumber(document.getElementById('amount_received').value);
        const ship = toNumber(document.getElementById('ship_display').value);
        const paid = toNumber(document.getElementById('amount_paid').value);
        const out = document.getElementById('loss_gain_display');
        if (received === null && ship === null && paid === null) { out.value = ''; return; }
        out.value = (((received || 0) + (ship || 0) - (paid || 0))).toFixed(2);
    }

    async function loadSummaryBadges() {
        try {
            const res = await fetch(urls.summary, { headers: { 'Accept': 'application/json' } });
            const json = await res.json();
            if (!json.success) return;
            document.getElementById('sci-issues-l30-value').textContent = String(json.issues_l30 ?? 0);
            document.getElementById('sci-loss-gain-l30-value').textContent = formatMoney(json.loss_gain_l30 ?? 0);
        } catch (e) {}
    }

    function openHistory(metric) {
        historyMetric = metric;
        document.getElementById('sci-history-modal-label').innerHTML =
            '<i class="fas fa-chart-area me-1"></i>' + (metric === 'loss_gain' ? 'Loss/Gain L30 - History' : 'Issue Count L30 - History');
        getHistoryModal()?.show();
        loadHistory();
    }

    async function loadHistory() {
        const days = document.getElementById('sci-history-range').value;
        try {
            const res = await fetch(urls.history + '?metric=' + encodeURIComponent(historyMetric) + '&days=' + encodeURIComponent(days), {
                headers: { 'Accept': 'application/json' },
            });
            const json = await res.json();
            const labels = json.labels || [];
            const values = json.values || [];
            const hasData = labels.length > 0;
            document.getElementById('sci-history-chart-wrapper').style.display = hasData ? 'flex' : 'none';
            document.getElementById('sci-history-empty').style.display = hasData ? 'none' : 'block';
            const fmt = historyMetric === 'loss_gain' ? (v) => formatMoney(v) : (v) => String(v ?? 0);
            document.getElementById('sci-history-high').textContent = json.highest == null ? '-' : fmt(json.highest);
            document.getElementById('sci-history-median').textContent = json.median == null ? '-' : fmt(json.median);
            document.getElementById('sci-history-low').textContent = json.lowest == null ? '-' : fmt(json.lowest);
            if (!hasData) return;
            const ctx = document.getElementById('sci-history-chart').getContext('2d');
            if (historyChart) historyChart.destroy();
            historyChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels,
                    datasets: [{
                        data: values,
                        borderColor: historyMetric === 'loss_gain' ? '#d39e00' : '#0d6efd',
                        backgroundColor: historyMetric === 'loss_gain' ? 'rgba(211,158,0,0.15)' : 'rgba(13,110,253,0.12)',
                        tension: 0.25, fill: true, pointRadius: 2,
                    }],
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true } },
                },
            });
        } catch (e) {
            document.getElementById('sci-history-chart-wrapper').style.display = 'none';
            document.getElementById('sci-history-empty').style.display = 'block';
        }
    }

    function initSelect2() {
        if (!window.jQuery) return;
        $('#channel').select2({ theme: 'bootstrap-5', dropdownParent: $('#issueModal'), placeholder: 'Select Channel', allowClear: true, width: '100%' });
        $('#sku').select2({ theme: 'bootstrap-5', dropdownParent: $('#issueModal'), placeholder: 'Select SKU', allowClear: true, width: '100%' });
    }

    function resetForm() {
        document.getElementById('issueForm').reset();
        document.getElementById('issue_id').value = '';
        document.getElementById('ship_display').value = '';
        document.getElementById('zone_display').value = '';
        document.getElementById('state_display').value = '';
        document.getElementById('loss_gain_display').value = '';
        setSkuImagePreview('');
        if (window.jQuery) {
            $('#channel').val(null).trigger('change');
            $('#sku').val(null).trigger('change');
        }
        document.getElementById('issueModalLabel').innerHTML = '<i class="fas fa-truck-fast me-2"></i>Add Shipping Cost Issue';
        document.getElementById('issueSaveBtn').textContent = 'Save';
    }

    function setSkuImagePreview(url) {
        const img = document.getElementById('sku_image_preview');
        const placeholder = document.getElementById('sku_image_placeholder');
        if (!img || !placeholder) return;
        if (!url) {
            img.src = '';
            img.classList.add('d-none');
            placeholder.classList.remove('d-none');
            return;
        }
        img.src = url;
        img.classList.remove('d-none');
        placeholder.classList.add('d-none');
    }

    async function loadShipForSku(sku) {
        const shipInput = document.getElementById('ship_display');
        if (!sku) {
            shipInput.value = '';
            setSkuImagePreview('');
            calcLossGain();
            return;
        }
        try {
            const res = await fetch(urls.shipLookup + '?sku=' + encodeURIComponent(sku), { headers: { 'Accept': 'application/json' } });
            const json = await res.json();
            shipInput.value = (json.ship === null || json.ship === undefined || json.ship === '') ? '' : String(json.ship);
            setSkuImagePreview(json.image || '');
        } catch (e) {
            shipInput.value = '';
            setSkuImagePreview('');
        }
        calcLossGain();
    }

    async function loadPinDerived(pin) {
        const clean = String(pin || '').trim();
        if (!clean) {
            document.getElementById('zone_display').value = '';
            document.getElementById('state_display').value = '';
            return;
        }
        try {
            const res = await fetch(urls.pinLookup + '?pin_code=' + encodeURIComponent(clean), { headers: { 'Accept': 'application/json' } });
            const json = await res.json();
            const data = json.data || {};
            document.getElementById('zone_display').value = data.zone || '';
            document.getElementById('state_display').value = data.state_abbr || data.state || '';
        } catch (e) {
            document.getElementById('zone_display').value = '';
            document.getElementById('state_display').value = '';
        }
    }

    function schedulePinLookup(pin) {
        if (pinLookupTimer) clearTimeout(pinLookupTimer);
        pinLookupTimer = setTimeout(function () { loadPinDerived(pin); }, 400);
    }

    function openEdit(row) {
        resetForm();
        document.getElementById('issue_id').value = row.id;
        document.getElementById('o_date').value = row.o_date || '';
        document.getElementById('o_number').value = row.o_number || '';
        if (window.jQuery) {
            $('#channel').val(row.channel || '').trigger('change');
            $('#sku').val(row.sku || '').trigger('change');
        }
        document.getElementById('ship_display').value = (row.ship ?? '') === '' ? '' : String(row.ship);
        document.getElementById('pin_code').value = row.pin_code || '';
        document.getElementById('zone_display').value = row.zone || '';
        document.getElementById('state_display').value = row.state || '';
        document.getElementById('amount_received').value = row.amount_received || '';
        document.getElementById('amount_paid').value = row.amount_paid || '';
        document.getElementById('action_taken').value = row.action_taken || '';
        setSkuImagePreview(row.image || '');
        calcLossGain();
        document.getElementById('issueModalLabel').innerHTML = '<i class="fas fa-pen me-2"></i>Edit Shipping Cost Issue';
        document.getElementById('issueSaveBtn').textContent = 'Update';
        skipNextModalReset = true;
        showModal();
        if (row.pin_code) schedulePinLookup(row.pin_code);
    }

    async function saveLabelType(selectEl, row) {
        const productId = row.product_id;
        const sku = row.sku;
        const prev = normalizeLabelType(row.label_type);
        const labelType = normalizeLabelType(selectEl.value);
        if (!productId || !sku) return;
        selectEl.disabled = true;
        try {
            const res = await fetch(urls.dimUpdate, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ product_id: productId, sku: sku, label_type: labelType }),
            });
            const json = await res.json().catch(() => ({}));
            if (!res.ok) {
                selectEl.value = prev;
                alert(json.message || 'Failed to sync Size');
                return;
            }
            row.label_type = labelType;
            selectEl.className = 'label-type-dropdown ' + (LABEL_TYPE_COLOR[labelType] || 'label-type-std');
            table?.updateData([{ id: row.id, label_type: labelType }]);
        } catch (e) {
            selectEl.value = prev;
            alert('Failed to sync Size');
        } finally {
            selectEl.disabled = false;
        }
    }

    function openDimWtEditor(row) {
        if (!row?.product_id || !row?.sku) { alert('SKU not found in Product Master'); return; }
        const dim = row.dim_wt || {};
        document.getElementById('editProductId').value = row.product_id;
        document.getElementById('editSku').value = row.sku || '';
        document.getElementById('editParent').value = row.parent || '';
        document.getElementById('editWtActKg').value = dim.wt_act_kg ?? '';
        document.getElementById('editWtAct').value = dim.wt_act ?? '';
        document.getElementById('editWtDecl').value = dim.wt_decl ?? dim.wt_act ?? '';
        document.getElementById('editL').value = dim.l ?? '';
        document.getElementById('editW').value = dim.w ?? '';
        document.getElementById('editH').value = dim.h ?? '';
        document.getElementById('editLDecl').value = dim.l_decl ?? dim.l ?? '';
        document.getElementById('editWDecl').value = dim.w_decl ?? dim.w ?? '';
        document.getElementById('editHDecl').value = dim.h_decl ?? dim.h ?? '';
        document.getElementById('editLCm').value = dim.l_cm ?? '';
        document.getElementById('editWCm').value = dim.w_cm ?? '';
        document.getElementById('editHCm').value = dim.h_cm ?? '';
        document.getElementById('editCtnL').value = dim.ctn_l ?? '';
        document.getElementById('editCtnW').value = dim.ctn_w ?? '';
        document.getElementById('editCtnH').value = dim.ctn_h ?? '';
        document.getElementById('editCtnQty').value = dim.ctn_qty ?? '';
        const lVal = parseFloat(dim.l) || 0, wVal = parseFloat(dim.w) || 0, hVal = parseFloat(dim.h) || 0;
        if (!dim.l_cm && lVal) document.getElementById('editLCm').value = (lVal * 2.54).toFixed(2);
        if (!dim.w_cm && wVal) document.getElementById('editWCm').value = (wVal * 2.54).toFixed(2);
        if (!dim.h_cm && hVal) document.getElementById('editHCm').value = (hVal * 2.54).toFixed(2);
        const bs = getBootstrap();
        const el = document.getElementById('editDimWtModal');
        if (bs && el) bs.Modal.getOrCreateInstance(el).show();
    }

    async function saveDimWt() {
        const saveBtn = document.getElementById('saveDimWtBtn');
        const original = saveBtn.innerHTML;
        const productId = document.getElementById('editProductId').value;
        const sku = document.getElementById('editSku').value;
        if (!productId || !sku) { alert('Missing product/SKU'); return; }
        const ctnL = parseFloat(document.getElementById('editCtnL').value) || 0;
        const ctnW = parseFloat(document.getElementById('editCtnW').value) || 0;
        const ctnH = parseFloat(document.getElementById('editCtnH').value) || 0;
        const ctnQty = parseFloat(document.getElementById('editCtnQty').value) || 0;
        const ctnCbm = (ctnL > 0 && ctnW > 0 && ctnH > 0) ? (ctnL * ctnW * ctnH) / 1000000 : 0;
        const ctnCbmEach = (ctnCbm > 0 && ctnQty > 0) ? ctnCbm / ctnQty : 0;
        const payload = {
            product_id: productId, sku: sku,
            parent: document.getElementById('editParent').value || null,
            wt_act_kg: document.getElementById('editWtActKg').value.trim() || null,
            wt_act: document.getElementById('editWtAct').value.trim() || null,
            wt_decl: document.getElementById('editWtDecl').value.trim() || null,
            l: document.getElementById('editL').value.trim() || null,
            w: document.getElementById('editW').value.trim() || null,
            h: document.getElementById('editH').value.trim() || null,
            l_decl: document.getElementById('editLDecl').value.trim() || null,
            w_decl: document.getElementById('editWDecl').value.trim() || null,
            h_decl: document.getElementById('editHDecl').value.trim() || null,
            l_cm: document.getElementById('editLCm').value.trim() || null,
            w_cm: document.getElementById('editWCm').value.trim() || null,
            h_cm: document.getElementById('editHCm').value.trim() || null,
            ctn_l: document.getElementById('editCtnL').value.trim() || null,
            ctn_w: document.getElementById('editCtnW').value.trim() || null,
            ctn_h: document.getElementById('editCtnH').value.trim() || null,
            ctn_qty: document.getElementById('editCtnQty').value.trim() || null,
            ctn_cbm: ctnCbm > 0 ? ctnCbm : null,
            ctn_cbm_each: ctnCbmEach > 0 ? ctnCbmEach : null,
        };
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
        try {
            const res = await fetch(urls.dimUpdate, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify(payload),
            });
            const json = await res.json().catch(() => ({}));
            if (!res.ok) { alert(json.message || 'Failed to save Dim and Wt'); return; }
            const bs = getBootstrap();
            const el = document.getElementById('editDimWtModal');
            if (bs && el) bs.Modal.getInstance(el)?.hide();
            table?.setData(urls.data);
        } catch (e) {
            alert('Failed to save Dim and Wt');
        } finally {
            saveBtn.disabled = false;
            saveBtn.innerHTML = original;
        }
    }

    function bootPage() {
        if (typeof Tabulator === 'undefined') {
            setTimeout(bootPage, 50);
            return;
        }

        initSelect2();
        loadSummaryBadges();

        table = new Tabulator("#sci-table", {
            ajaxURL: urls.data,
            ajaxResponse: function (url, params, response) { return response.data || []; },
            ajaxSorting: false,
            layout: "fitDataStretch",
            pagination: true,
            paginationSize: 100,
            paginationSizeSelector: [10, 25, 50, 100, 200],
            paginationCounter: "rows",
            initialSort: [{ column: "o_date", dir: "desc" }],
            columns: [
                { title: "O Date", field: "o_date", width: 100,
                    formatter: (c) => escapeHtml(c.getData().o_date_display || '') },
                { title: "O Number", field: "o_number", width: 130 },
                { title: "Channel", field: "channel", width: 120 },
                { title: "SKU", field: "sku", width: 150 },
                { title: "Image", field: "image", headerSort: false, width: 70, hozAlign: "center",
                    formatter: function (cell) {
                        const url = cell.getValue();
                        if (!url) return '<span class="sci-sku-thumb-empty"><i class="fa fa-image"></i></span>';
                        return '<img class="sci-sku-thumb" src="' + escapeHtml(url) + '" alt="SKU" loading="lazy">';
                    }
                },
                { title: "Pin Code", field: "pin_code", width: 100 },
                { title: "Zone", field: "zone", width: 90 },
                { title: "State", field: "state", width: 80 },
                { title: "SHIP", field: "ship", width: 90, hozAlign: "right", sorter: "number",
                    formatter: (c) => {
                        const v = c.getValue();
                        return (v === null || v === undefined || v === '') ? '' : escapeHtml(v);
                    }
                },
                { title: "Size", field: "label_type", width: 100, hozAlign: "center",
                    formatter: function (cell) {
                        const row = cell.getData();
                        if (!row.sku || !row.product_id) return '<span class="text-muted">—</span>';
                        const current = normalizeLabelType(row.label_type);
                        const cls = LABEL_TYPE_COLOR[current] || 'label-type-std';
                        const opts = LABEL_TYPE_OPTIONS.map((opt) =>
                            `<option value="${opt}"${opt === current ? ' selected' : ''}>${opt}</option>`
                        ).join('');
                        return `<select class="label-type-dropdown ${cls}" title="Size / Label Type">${opts}</select>`;
                    }
                },
                { title: "Recd $", field: "amount_received", width: 140, hozAlign: "right" },
                { title: "Paid $", field: "amount_paid", width: 120, hozAlign: "right" },
                { title: "Loss/Gain", field: "loss_gain_before_action", width: 180, hozAlign: "right", sorter: "number",
                    formatter: function (cell) {
                        const v = cell.getValue();
                        if (v === null || v === undefined || v === '') return '';
                        const n = Number(v);
                        const color = n < 0 ? '#dc3545' : (n > 0 ? '#28a745' : '#6c757d');
                        return `<span style="color: ${color}; font-weight: bold;">${n.toFixed(2)}</span>`;
                    }
                },
                { title: "Action Taken", field: "action_taken", width: 180,
                    formatter: (c) => {
                        const v = c.getValue();
                        if (!v) return '';
                        return `<span title="${escapeHtml(v)}">${escapeHtml(String(v).length > 40 ? String(v).slice(0, 40) + '…' : v)}</span>`;
                    }
                },
                { title: "Edit", field: "id", headerSort: false, width: 70, hozAlign: "center",
                    formatter: () => '<button type="button" class="sci-action-btn btn-edit" title="Edit issue"><i class="fa fa-pen"></i></button>'
                },
                { title: "Dim and Wt", field: "product_id", headerSort: false, width: 100, hozAlign: "center",
                    formatter: function (cell) {
                        const row = cell.getData();
                        if (!row.product_id || !row.sku) {
                            return '<button type="button" class="sci-action-btn" disabled title="SKU not in Product Master"><i class="fa fa-pen-square"></i></button>';
                        }
                        return '<button type="button" class="sci-action-btn btn-dimwt-edit" title="Edit Dim and Wt"><i class="fa fa-pen-square"></i></button>';
                    }
                },
            ]
        });

        table.on('cellClick', function (e, cell) {
            const field = cell.getField();
            const row = cell.getData();
            if (e.target.closest('.btn-edit')) {
                openEdit(row);
                return;
            }
            if (e.target.closest('.btn-dimwt-edit')) {
                openDimWtEditor(row);
                return;
            }
            if (field === 'label_type' && e.target.classList.contains('label-type-dropdown')) {
                // change handled below
            }
        });

        document.getElementById('sci-table').addEventListener('change', function (e) {
            const sel = e.target.closest('.label-type-dropdown');
            if (!sel) return;
            const cellEl = sel.closest('.tabulator-cell');
            if (!cellEl || !table) return;
            // Find row by walking Tabulator row element
            const rowEl = sel.closest('.tabulator-row');
            if (!rowEl) return;
            const rowComponent = table.getRows().find((r) => r.getElement() === rowEl);
            if (!rowComponent) return;
            saveLabelType(sel, rowComponent.getData());
        });

        if (modalEl) {
            modalEl.addEventListener('show.bs.modal', function () {
                if (skipNextModalReset) { skipNextModalReset = false; return; }
                resetForm();
            });
        }

        document.querySelectorAll('.sci-history-dot').forEach((dot) => {
            dot.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                openHistory(this.dataset.metric || 'issues');
            });
        });
        document.getElementById('sci-history-range')?.addEventListener('change', loadHistory);
        document.getElementById('saveDimWtBtn')?.addEventListener('click', saveDimWt);

        if (window.jQuery) {
            $('#sku').on('change', function () { loadShipForSku($(this).val()); });
        }
        document.getElementById('pin_code')?.addEventListener('input', function () { schedulePinLookup(this.value); });
        document.getElementById('amount_received')?.addEventListener('input', calcLossGain);
        document.getElementById('amount_paid')?.addEventListener('input', calcLossGain);

        document.getElementById('issueForm')?.addEventListener('submit', async function (e) {
            e.preventDefault();
            const id = document.getElementById('issue_id').value;
            const payload = {
                o_date: document.getElementById('o_date').value || null,
                o_number: document.getElementById('o_number').value || null,
                channel: document.getElementById('channel').value || null,
                sku: document.getElementById('sku').value || null,
                pin_code: document.getElementById('pin_code').value || null,
                amount_received: document.getElementById('amount_received').value || null,
                amount_paid: document.getElementById('amount_paid').value || null,
                action_taken: document.getElementById('action_taken').value || null,
            };
            const isEdit = !!id;
            try {
                const res = await fetch(isEdit ? (urls.updateBase + '/' + id) : urls.store, {
                    method: isEdit ? 'PUT' : 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify(payload),
                });
                const json = await res.json();
                if (!res.ok || !json.success) { alert(json.message || 'Save failed'); return; }
                hideModal();
                table?.setData(urls.data);
                loadSummaryBadges();
            } catch (err) {
                alert('Save failed');
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootPage);
    } else {
        bootPage();
    }
})();
</script>
@endsection
