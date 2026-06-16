@extends('layouts.vertical', ['title' => 'eBay 3 Campaign Ads — Raw Data', 'mode' => '', 'demo' => ''])

@section('content')
<div class="container-fluid px-4 py-3">

    {{-- Header --}}
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h4 class="mb-0 fw-bold">eBay 2 Campaign Ads</h4>
            <small class="text-muted">Raw data from <code>ebay2_campaign_ads</code> table · synced daily</small>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <span class="badge bg-primary fs-6" id="total-count">Loading…</span>
            <button class="btn btn-sm btn-success d-none" id="push-selected-btn">
                <i class="fas fa-cloud-upload-alt me-1"></i>Push Selected (<span id="selected-count">0</span>)
            </button>
            <button class="btn btn-sm btn-info text-white d-none" id="enroll-selected-btn" data-bs-toggle="modal" data-bs-target="#enrollModal">
                <i class="fas fa-plus-circle me-1"></i>Enroll in Campaign (<span id="enroll-count">0</span>)
            </button>
            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#sbidRuleModal">
                <i class="fas fa-sliders-h me-1"></i>SBID Rule
            </button>
            <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#dilRuleModal">
                <i class="fas fa-tint me-1"></i>Dil Rule
            </button>
            <button class="btn btn-sm btn-warning text-dark" id="push-sbid-btn" title="Run ebay2:update-suggestedbid now">
                <i class="fas fa-cloud-upload-alt me-1"></i>Push SBID
            </button>
            <button class="btn btn-sm btn-outline-secondary" onclick="table.download('csv','ebay2_campaign_ads.csv')">
                <i class="fas fa-download me-1"></i>CSV
            </button>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2">
            <div class="row g-2 align-items-center">
                <div class="col-auto">
                    <input type="text" id="search-input" class="form-control form-control-sm"
                           placeholder="Search SKU / listing_id / campaign…" style="width:260px;">
                </div>
                <div class="col-auto">
                    <select id="funding-filter" class="form-select form-select-sm">
                        <option value="">All Funding</option>
                        <option value="COST_PER_SALE">COST_PER_SALE (PMT)</option>
                        <option value="COST_PER_CLICK">COST_PER_CLICK (PPC)</option>
                    </select>
                </div>
                <div class="col-auto">
                    <select id="status-filter" class="form-select form-select-sm">
                        <option value="">All Status</option>
                        <option value="RUNNING">RUNNING</option>
                        <option value="PAUSED">PAUSED</option>
                        <option value="ENDED">ENDED</option>
                    </select>
                </div>
                <div class="col-auto">
                    <select id="promote-filter" class="form-select form-select-sm">
                        <option value="">All Promote</option>
                        <option value="RECOMMENDED">⭐ Eligible (RECOMMENDED)</option>
                        <option value="OPTIONAL">⚡ Optional</option>
                        <option value="AD_ALREADY_CREATED">📢 In Campaign</option>
                        <option value="NOT_RECOMMENDED">— Not Recommended</option>
                        <option value="UNDETERMINED">? Undetermined</option>
                        <option value="__NONE__">— No Value</option>
                    </select>
                </div>
                <div class="col-auto">
                    <button class="btn btn-sm btn-outline-secondary" onclick="clearFilters()">
                        Clear
                    </button>
                </div>
                <div class="col-auto ms-auto text-muted small" id="last-updated"></div>
            </div>
        </div>
    </div>

    {{-- Tabulator --}}
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div id="ebay2-campaign-ads-table"></div>
        </div>
    </div>

</div>

{{-- Enroll in Campaign Modal --}}
<div class="modal fade" id="enrollModal" tabindex="-1" aria-labelledby="enrollModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="enrollModalLabel">
                    <i class="fas fa-plus-circle me-2 text-info"></i>Enroll in Campaign
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">
                    Selected <strong id="enroll-listing-count">0</strong> eligible listing(s) will be added to the chosen campaign
                    with bid calculated from SCVR + current SBID rule.
                </p>
                <label class="form-label fw-semibold">Select Campaign (RUNNING · COST_PER_SALE)</label>
                <select class="form-select" id="enroll-campaign-select">
                    <option value="">Loading campaigns…</option>
                </select>
                <p class="small text-danger mt-2 d-none" id="enroll-err"></p>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm btn-info text-white" id="enroll-confirm-btn">
                    <i class="fas fa-plus-circle me-1"></i>Enroll Now
                </button>
            </div>
        </div>
    </div>
</div>

{{-- SBID Rule Modal --}}
<div class="modal fade" id="sbidRuleModal" tabindex="-1" aria-labelledby="sbidRuleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="sbidRuleModalLabel">
                    <i class="fas fa-sliders-h me-2 text-primary"></i>eBay 2 SBID Rule — SCVR % → Bid %
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">
                    Bands evaluated <strong>top to bottom</strong> — first match wins.
                    <code>SCVR = (Sold L30 / Views) × 100</code>. Each band: if SCVR ≤ max → use that bid.
                </p>

                <table class="table table-sm table-bordered align-middle" id="sbid-rule-table">
                    <thead class="table-light">
                        <tr>
                            <th style="width:40px;">#</th>
                            <th>Label</th>
                            <th>Color</th>
                            <th>CVR ≤ (%)</th>
                            <th>Bid (%)</th>
                        </tr>
                    </thead>
                    <tbody id="sbid-bands-body">
                        {{-- filled by JS --}}
                    </tbody>
                </table>

                <div class="alert alert-info small py-2 mb-0">
                    <i class="fas fa-info-circle me-1"></i>
                    Set SCVR Max to <code>9999</code> for the last band (catches everything above previous threshold).
                    Tick <strong>Dynamic by metric</strong> on any band (e.g. Pink) to decide its bid from
                    Price / L30 Sold / Views / SCVR tiers instead of a single flat bid.
                    Changes apply next time <strong>ebay2:update-suggestedbid</strong> runs.
                </div>
                <p class="small text-danger mb-0 mt-2 d-none" id="sbid-rule-err"></p>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-sm btn-primary" id="sbid-rule-save-btn">
                    <i class="fas fa-save me-1"></i>Save Rule
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Dilution Rule Modal --}}
<div class="modal fade" id="dilRuleModal" tabindex="-1" aria-labelledby="dilRuleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="dilRuleModalLabel">
                    <i class="fas fa-tint me-2 text-danger"></i>eBay 2 Dilution Rule — DIL % → Color
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">
                    Bands evaluated <strong>top to bottom</strong> — first match wins.
                    <code>DIL = (L30 sold / Inventory) × 100</code>. Each band sets a color and a bid.
                </p>

                <table class="table table-sm table-bordered align-middle" id="dil-rule-table">
                    <thead class="table-light">
                        <tr>
                            <th style="width:40px;">#</th>
                            <th>Label</th>
                            <th>Color</th>
                            <th>DIL ≤ (%)</th>
                            <th>Bid (%)</th>
                        </tr>
                    </thead>
                    <tbody id="dil-bands-body">
                        {{-- filled by JS --}}
                    </tbody>
                </table>

                <button type="button" class="btn btn-sm btn-outline-primary py-0 mb-2" id="dil-add-band-btn">
                    <i class="fas fa-plus me-1"></i>Add band
                </button>

                <div class="alert alert-info small py-2 mb-0">
                    <i class="fas fa-info-circle me-1"></i>
                    Set DIL Max to <code>9999</code> for the last band (catches everything above the previous threshold).
                    <strong>Push logic:</strong> if a listing's SCVR <em>or</em> DIL lands in its <strong>Pink (catch-all)</strong>
                    band, the Pink bid (e.g. 2.1%) is pushed; otherwise the SCVR rule's bid is used.
                </div>
                <p class="small text-danger mb-0 mt-2 d-none" id="dil-rule-err"></p>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-sm btn-primary" id="dil-rule-save-btn">
                    <i class="fas fa-save me-1"></i>Save Rule
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('css')
<link rel="stylesheet" href="https://unpkg.com/tabulator-tables@6.2.1/dist/css/tabulator_bootstrap5.min.css">
<style>
    #ebay2-campaign-ads-table .tabulator-row:hover { background: #f0f7ff !important; }
    .badge-cps  { background: #198754; color:#fff; padding:2px 7px; border-radius:4px; font-size:11px; }
    .badge-cpc  { background: #0d6efd; color:#fff; padding:2px 7px; border-radius:4px; font-size:11px; }
    .badge-run  { background: #198754; color:#fff; padding:2px 7px; border-radius:4px; font-size:11px; }
    .badge-paus { background: #ffc107; color:#000; padding:2px 7px; border-radius:4px; font-size:11px; }
    .badge-end  { background: #dc3545; color:#fff; padding:2px 7px; border-radius:4px; font-size:11px; }
</style>
@endsection

@section('script-after-vite')
<script src="https://unpkg.com/tabulator-tables@6.2.1/dist/js/tabulator.min.js"></script>
<script>
let table;

function loadData() {
    const search  = $('#search-input').val();
    const funding = $('#funding-filter').val();
    const status  = $('#status-filter').val();
    const promote = $('#promote-filter').val();

    $.get('/ebay2/campaign-ads/data', { search, funding_strategy: funding, campaign_status: status, promote_with_ad: promote })
        .done(function(resp) {
            if (resp && resp.data) {
                $('#total-count').text(resp.total.toLocaleString() + ' rows');
                $('#last-updated').text('Updated: ' + new Date().toLocaleTimeString());
                table.replaceData(resp.data);
            } else {
                $('#total-count').text('Error');
                console.error('Unexpected response:', resp);
            }
        })
        .fail(function(xhr) {
            $('#total-count').text('Error ' + xhr.status);
            console.error('API Error:', xhr.status, xhr.responseText);
            alert('API Error ' + xhr.status + ': ' + xhr.responseText.substring(0, 200));
        });
}

function clearFilters() {
    $('#search-input').val('');
    $('#funding-filter').val('');
    $('#status-filter').val('');
    $('#promote-filter').val('');
    loadData();
}

$(document).ready(function () {

    table = new Tabulator('#ebay2-campaign-ads-table', {
        data: [],
        layout: 'fitDataFill',
        height: 'calc(100vh - 260px)',
        columnDefaults: { hozAlign: 'center', headerHozAlign: 'center' },
        pagination: true,
        paginationSize: 100,
        paginationSizeSelector: [50, 100, 200, 500],
        movableColumns: true,
        placeholder: 'No data — run php artisan ebay2:sync-campaign-listings',
        columns: [
            {
                title: '<input type="checkbox" id="select-all-cb" style="cursor:pointer;">',
                field: '_select', width: 40, hozAlign: 'center',
                headerSort: false, frozen: true,
                formatter: function(cell) {
                    const lid = String(cell.getRow().getData().listing_id);
                    const checked = selectedIds.has(lid) ? 'checked' : '';
                    return `<input type="checkbox" class="row-cb" data-lid="${lid}" ${checked} style="cursor:pointer;">`;
                },
                cellClick: function(e, cell) {
                    const lid = String(cell.getRow().getData().listing_id);
                    const cb  = cell.getElement().querySelector('.row-cb');
                    if (cb) {
                        if (selectedIds.has(lid)) { selectedIds.delete(lid); cb.checked = false; }
                        else                       { selectedIds.add(lid);    cb.checked = true;  }
                        updateSelectedCount();
                    }
                }
            },
            {
                title: '#', formatter: function(cell) {
                    return cell.getRow().getPosition(true);
                }, width: 50, hozAlign: 'center',
                headerSort: false, frozen: true
            },
            {
                title: 'SKU', field: 'resolved_sku', width: 250, frozen: true,
                formatter: function(cell) {
                    const row     = cell.getRow().getData();
                    const matched = row.sku_matched == 1;
                    const v       = cell.getValue() || '—';
                    if (matched) {
                        return `<span class="fw-semibold text-primary">${v}</span>`;
                    } else {
                        // No SKU match — show listing_id in grey italic
                        return `<span class="text-muted fst-italic" style="font-size:11px;" title="No SKU match for listing_id ${v}">${v}</span>`;
                    }
                }
            },
            {
                title: 'Dil', field: 'shopify_qty', width: 80, hozAlign: 'center', frozen: true,
                headerTooltip: 'Dilution = (L30 sold / Inventory) × 100. Colors from the Dil Rule.',
                sorter: function(a, b, aRow, bRow) {
                    return dilValue(aRow.getData()) - dilValue(bRow.getData());
                },
                formatter: function(cell) {
                    const row = cell.getRow().getData();
                    const inv = parseFloat(row.shopify_inv) || 0;
                    const l30 = parseFloat(row.shopify_qty)  || 0;
                    if (inv === 0) {
                        return `<span style="color:${getDilColor(0)}; font-weight:600;">0%</span>`;
                    }
                    const dil = (l30 / inv) * 100;
                    return `<span style="color:${getDilColor(dil)}; font-weight:600;">${Math.round(dil)}%</span>`;
                }
            },
            {
                title: 'Listing ID', field: 'listing_id', width: 140,
                formatter: function(cell) {
                    const v       = cell.getValue();
                    const matched = cell.getRow().getData().sku_matched == 1;
                    const color   = matched ? '' : 'color:#aaa;';
                    return `<a href="https://www.ebay.com/itm/${v}" target="_blank"
                               class="text-decoration-none" style="${color}">${v}
                               <i class="fas fa-external-link-alt fa-xs"></i></a>`;
                }
            },
            {
                title: 'Campaign Name', field: 'campaign_name', width: 220, visible: false,
                formatter: function(cell) {
                    return cell.getValue() || '—';
                }
            },
            {
                title: 'Campaign ID', field: 'campaign_id', width: 130, visible: false,
                formatter: function(cell) {
                    return `<small class="text-muted">${cell.getValue()}</small>`;
                }
            },
            {
                title: 'Funding', field: 'funding_strategy', width: 130, hozAlign: 'center',
                formatter: function(cell) {
                    const v = cell.getValue();
                    if (v === 'COST_PER_SALE')  return '<span class="badge-cps">PMT (CPS)</span>';
                    if (v === 'COST_PER_CLICK') return '<span class="badge-cpc">PPC (CPC)</span>';
                    return '<span style="color:#aaa; font-size:11px;">No Campaign</span>';
                }
            },
            {
                title: 'Status', field: 'campaign_status', width: 100, hozAlign: 'center',
                formatter: function(cell) {
                    const v = cell.getValue();
                    if (v === 'RUNNING') return '<span class="badge-run">RUNNING</span>';
                    if (v === 'PAUSED')  return '<span class="badge-paus">PAUSED</span>';
                    if (v === 'ENDED')   return '<span class="badge-end">ENDED</span>';
                    return '<span style="color:#aaa; font-size:11px;">—</span>';
                }
            },
            {
                title: 'Ad ID', field: 'ad_id', width: 130, visible: false,
                formatter: function(cell) {
                    return `<small class="text-muted">${cell.getValue() || '—'}</small>`;
                }
            },
            {
                title: 'C Bid', field: 'bid_percentage', width: 110, hozAlign: 'center',
                sorter: 'number',
                formatter: function(cell) {
                    const v = parseFloat(cell.getValue());
                    if (isNaN(v)) return '—';
                    const color = v <= 4 ? '#dc3545' : v <= 7 ? '#ffc107' : v <= 13 ? '#198754' : '#e83e8c';
                    return `<span style="color:${color}; font-weight:600;">${v.toFixed(1)}%</span>`;
                }
            },
            {
                title: 'ES Bid', field: 'suggested_bid', width: 110, hozAlign: 'center',
                sorter: 'number',
                formatter: function(cell) {
                    const v = parseFloat(cell.getValue());
                    return isNaN(v) ? '—' : `<span class="text-info fw-semibold">${v.toFixed(1)}%</span>`;
                }
            },
            {
                title: 'Price', field: 'metric_price', width: 110, hozAlign: 'center',
                sorter: 'number',
                formatter: function(cell) {
                    const v = parseFloat(cell.getValue());
                    return isNaN(v) || v === 0 ? '—' : `<span class="fw-semibold">$${v.toFixed(2)}</span>`;
                }
            },
            {
                title: 'S Bid', field: 'ebay_l30', width: 110, hozAlign: 'center',
                headerTooltip: 'Suggested bid: if SCVR or DIL is Pink → Pink bid; else SCVR rule. No SBID when CVR = 0 and DIL not Pink.',
                sorter: function(a, b, aRow, bRow) {
                    return getCombinedSbid(aRow.getData()).bid - getCombinedSbid(bRow.getData()).bid;
                },
                formatter: function(cell) {
                    const row   = cell.getRow().getData();
                    const match = getCombinedSbid(row);
                    if (match.skip) {
                        return `<span class="text-muted" title="No SBID — 0 CVR (no L30 sales)" style="font-size:11px;">— no sbid</span>`;
                    }
                    return `<span style="color:${match.color}; font-weight:700;">${match.bid.toFixed(1)}%</span>`;
                }
            },
            {
                title: 'CVR', field: 'ebay_l30', width: 80, hozAlign: 'center',
                sorter: function(a, b, aRow, bRow) {
                    const aViews = parseFloat(aRow.getData().views) || 0;
                    const bViews = parseFloat(bRow.getData().views) || 0;
                    const aCvr  = aViews > 0 ? (parseFloat(a) / aViews) * 100 : 0;
                    const bCvr  = bViews > 0 ? (parseFloat(b) / bViews) * 100 : 0;
                    return aCvr - bCvr;
                },
                formatter: function(cell) {
                    const row   = cell.getRow().getData();
                    const sold  = parseFloat(row.ebay_l30) || 0;
                    const views = parseFloat(row.views)    || 0;
                    if (views === 0) return '<span class="text-muted">—</span>';
                    const cvr   = (sold / views) * 100;
                    const color = cvr <= 4 ? '#dc3545' : cvr <= 7 ? '#ffc107' : cvr <= 13 ? '#198754' : '#e83e8c';
                    return `<span style="color:${color}; font-weight:600;">${cvr.toFixed(1)}%</span>`;
                }
            },
            {
                title: 'Promote', field: 'promote_with_ad', width: 140, hozAlign: 'center',
                headerTooltip: 'eBay Promotion eligibility status',
                formatter: function(cell) {
                    const v = cell.getValue();
                    if (!v) return '<span class="text-muted">—</span>';
                    const map = {
                        'RECOMMENDED':        { color: '#198754', bg: '#d1f5e0', label: '⭐ Eligible' },
                        'OPTIONAL':           { color: '#856404', bg: '#fff3cd', label: '⚡ Optional' },
                        'AD_ALREADY_CREATED': { color: '#0d6efd', bg: '#cfe2ff', label: '📢 In Campaign' },
                        'NOT_RECOMMENDED':    { color: '#6c757d', bg: '#f8f9fa', label: '— Not Rec.' },
                        'UNDETERMINED':       { color: '#6c757d', bg: '#f8f9fa', label: '? Unknown' },
                    };
                    const s = map[v] || { color: '#6c757d', bg: '#f8f9fa', label: v };
                    return `<span style="color:${s.color}; background:${s.bg}; padding:2px 6px; border-radius:4px; font-size:11px; font-weight:600;">${s.label}</span>`;
                }
            },
            {
                title: 'Updated', field: 'updated_at', width: 140,
                formatter: function(cell) {
                    const v = cell.getValue();
                    return v ? `<small class="text-muted">${v.substring(0,16)}</small>` : '—';
                }
            },
        ]
    });

    // Search — live on typing (debounced 400ms)
    let searchTimer;
    $('#search-input').on('input', function() {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(loadData, 400);
    });

    // Dropdowns — auto load on change
    $('#funding-filter, #status-filter, #promote-filter').on('change', loadData);

    loadData();
});

// ── Checkbox selection ─────────────────────────────
const selectedIds = new Set();

function updateSelectedCount() {
    const count = selectedIds.size;
    $('#selected-count, #enroll-count').text(count);
    $('#enroll-listing-count').text(count);

    if (count > 0) {
        $('#push-selected-btn').removeClass('d-none');
        // Check if any selected are eligible (no campaign)
        const hasEligible = Array.from(selectedIds).some(lid => {
            const rows = table ? table.getRows() : [];
            for (let r of rows) {
                const d = r.getData();
                if (d.listing_id == lid && !d.campaign_id) return true;
            }
            return false;
        });
        if (hasEligible) $('#enroll-selected-btn').removeClass('d-none');
        else             $('#enroll-selected-btn').addClass('d-none');
    } else {
        $('#push-selected-btn').addClass('d-none');
        $('#enroll-selected-btn').addClass('d-none');
    }
}

// Load campaigns when enroll modal opens
document.getElementById('enrollModal').addEventListener('show.bs.modal', function() {
    $.get('/ebay2/campaign-ads/campaigns', function(data) {
        const sel = $('#enroll-campaign-select');
        sel.empty().append('<option value="">— Select a campaign —</option>');
        data.forEach(c => sel.append(`<option value="${c.campaign_id}">${c.campaign_name}</option>`));
    });
});

// Enroll confirm
document.getElementById('enroll-confirm-btn').addEventListener('click', function() {
    const campaignId = $('#enroll-campaign-select').val();
    const errEl      = document.getElementById('enroll-err');
    errEl.classList.add('d-none');

    if (!campaignId) { errEl.textContent = 'Please select a campaign.'; errEl.classList.remove('d-none'); return; }

    // Only send eligible (no campaign_id) listings
    const eligibleIds = Array.from(selectedIds).filter(lid => {
        const rows = table ? table.getRows() : [];
        for (let r of rows) {
            const d = r.getData();
            if (d.listing_id == lid && !d.campaign_id) return true;
        }
        return false;
    });

    if (eligibleIds.length === 0) { errEl.textContent = 'No eligible listings selected.'; errEl.classList.remove('d-none'); return; }

    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Enrolling…';

    $.ajax({
        url: '/ebay2/campaign-ads/enroll',
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        contentType: 'application/json',
        data: JSON.stringify({ listing_ids: eligibleIds, campaign_id: campaignId }),
        timeout: 120000,
        success: function(resp) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-plus-circle me-1"></i>Enroll Now';
            bootstrap.Modal.getInstance(document.getElementById('enrollModal')).hide();

            let msg = `✅ Enrolled: ${resp.success} | ❌ Failed: ${resp.failed} | ⏭ Skipped: ${resp.skipped || 0}\n\n`;
            (resp.results || []).forEach(r => {
                const icon = r.status === 'enrolled' ? '✅' : r.status === 'skipped' ? '⏭' : '❌';
                msg += `${icon} ${r.sku || r.listing_id} → ${r.status}${r.bid ? ' @ ' + r.bid : ''}${r.reason ? ' (' + r.reason + ')' : ''}\n`;
            });
            alert(msg);
            loadData(); // refresh table
        },
        error: function(xhr) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-plus-circle me-1"></i>Enroll Now';
            errEl.textContent = 'Error: ' + (xhr.responseJSON?.error || xhr.responseText.substring(0, 100));
            errEl.classList.remove('d-none');
        }
    });
});

// Select All checkbox — selects ALL filtered rows across every page, not just visible DOM rows
document.addEventListener('change', function(e) {
    if (e.target && e.target.id === 'select-all-cb') {
        const checked = e.target.checked;

        // Use Tabulator's full row list (post-filter, all pages) instead of querySelectorAll,
        // which only sees the currently rendered page.
        const rows = table ? table.getRows('active') : [];

        if (checked) {
            rows.forEach(r => {
                const lid = r.getData().listing_id;
                if (lid != null) selectedIds.add(String(lid));
            });
        } else {
            rows.forEach(r => {
                const lid = r.getData().listing_id;
                if (lid != null) selectedIds.delete(String(lid));
            });
        }

        // Sync visible checkboxes on the current page
        document.querySelectorAll('.row-cb').forEach(cb => { cb.checked = checked; });

        updateSelectedCount();
    }
});

// Push Selected button
document.getElementById('push-selected-btn').addEventListener('click', function() {
    if (selectedIds.size === 0) return;
    if (!confirm(`Push SBID bid to ${selectedIds.size} selected listing(s)?`)) return;

    const btn = this;
    btn.disabled = true;
    btn.innerHTML = `<i class="fas fa-spinner fa-spin me-1"></i>Pushing ${selectedIds.size}…`;

    $.ajax({
        url: '/ebay2/campaign-ads/push-selected',
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        contentType: 'application/json',
        data: JSON.stringify({ listing_ids: Array.from(selectedIds) }),
        timeout: 120000,
        success: function(resp) {
            btn.disabled = false;
            btn.innerHTML = `<i class="fas fa-cloud-upload-alt me-1"></i>Push Selected (<span id="selected-count">${selectedIds.size}</span>)`;

            // Build result message
            let msg = `✅ Pushed: ${resp.success} | ❌ Failed: ${resp.failed} | ⏭ Skipped: ${resp.skipped}\n\n`;
            (resp.results || []).forEach(r => {
                const icon = r.status === 'pushed' ? '✅' : r.status === 'skipped' ? '⏭' : '❌';
                msg += `${icon} ${r.listing_id} → ${r.status}${r.bid ? ' ' + r.bid : ''}${r.reason ? ' (' + r.reason + ')' : ''}\n`;
            });
            alert(msg);
        },
        error: function(xhr) {
            btn.disabled = false;
            btn.innerHTML = `<i class="fas fa-cloud-upload-alt me-1"></i>Push Selected (<span id="selected-count">${selectedIds.size}</span>)`;
            alert('Error: ' + (xhr.responseJSON?.error || xhr.responseText));
        }
    });
});

// ── SBID Rule helper — used by S Bid column ────────
// Skip SBID entirely when CVR = 0 (no signal: zero sales in L30 means we don't know what to bid)
// `row` (optional) carries metric values so a matched band can resolve a dynamic sub-rule.
function getBidFromRule(scvr, row) {
    const s = parseFloat(scvr);
    if (!isFinite(s) || s <= 0) {
        return { bid: 0, color: '#6c757d', skip: true };
    }
    const bands = currentRule.bands || [];
    const ctx = {
        scvr:       s,
        ebay_price: parseFloat(row && row.metric_price) || 0,
        ebay_l30:   parseFloat(row && row.ebay_l30)     || 0,
        views:      parseFloat(row && row.views)        || 0,
    };
    for (let i = 0; i < bands.length; i++) {
        if (s <= parseFloat(bands[i].scvr_max)) {
            return resolveBandBid(bands[i], ctx);
        }
    }
    // fallback: last band
    const last = bands[bands.length - 1] || { bid: 2.1, color: '#e83e8c' };
    return resolveBandBid(last, ctx);
}

// Resolve a band's bid — uses its dynamic sub-rule (by metric) when present.
function resolveBandBid(band, ctx) {
    const color = band.color || '#333';
    const sub   = band.sub;
    if (sub && sub.metric && Array.isArray(sub.bands) && sub.bands.length) {
        const val = parseFloat(ctx[sub.metric]) || 0;
        for (let j = 0; j < sub.bands.length; j++) {
            if (val <= parseFloat(sub.bands[j].max)) {
                return { bid: parseFloat(sub.bands[j].bid), color: color, skip: false };
            }
        }
        const ls = sub.bands[sub.bands.length - 1];
        return { bid: parseFloat(ls.bid), color: color, skip: false };
    }
    return { bid: parseFloat(band.bid), color: color, skip: false };
}

// ── SBID Rule ──────────────────────────────────────
const ruleGetUrl  = '/ebay2/campaign-ads/rule';
const ruleSaveUrl = '/ebay2/campaign-ads/rule';
const pushSbidUrl = '/ebay2/campaign-ads/push-sbid';
let currentRule = @json($sbidRule ?? ['bands' => []]);

// Metric options available for a band's dynamic sub-rule
const SUB_METRICS = {
    scvr:       { label: 'SCVR %',   unit: '%', step: '0.1' },
    ebay_price: { label: 'Price $',  unit: '$', step: '0.01' },
    ebay_l30:   { label: 'L30 Sold', unit: '',  step: '1' },
    views:      { label: 'Views',    unit: '',  step: '1' },
};

function renderRuleBands(bands) {
    const tbody = document.getElementById('sbid-bands-body');
    tbody.innerHTML = '';
    bands.forEach(function(band, i) {
        const isLast  = (parseFloat(band.scvr_max) >= 9999);
        const hasSub  = !!(band.sub && band.sub.metric);
        tbody.innerHTML += `
        <tr>
            <td class="text-center text-muted small">${i+1}</td>
            <td><input type="text" class="form-control form-control-sm" value="${band.label || ''}"
                       data-idx="${i}" data-field="label" onchange="updateBand(this)"></td>
            <td>
                <div class="d-flex align-items-center gap-2">
                    <input type="color" class="form-control form-control-color form-control-sm" style="width:40px;height:31px;"
                           value="${band.color || '#6c757d'}" data-idx="${i}" data-field="color" onchange="updateBand(this)">
                    <span class="badge" style="background:${band.color || '#6c757d'};">${band.label || ''}</span>
                </div>
            </td>
            <td>
                ${isLast
                    ? '<span class="text-muted small">∞ (catch-all)</span><input type="hidden" value="9999" data-idx="'+i+'" data-field="scvr_max">'
                    : `<input type="number" step="0.1" min="0" class="form-control form-control-sm" value="${band.scvr_max}"
                              data-idx="${i}" data-field="scvr_max" onchange="updateBand(this)">`
                }
            </td>
            <td>
                <div class="input-group input-group-sm">
                    <input type="number" step="0.1" min="0" max="100" class="form-control form-control-sm fw-semibold"
                           value="${band.bid}" data-idx="${i}" data-field="bid" ${hasSub ? 'disabled' : ''}
                           style="color:${band.color || '#333'}; font-weight:600;"
                           onchange="updateBand(this)">
                    <span class="input-group-text">%</span>
                </div>
                <div class="form-check form-check-inline mt-1">
                    <input class="form-check-input" type="checkbox" id="sub-toggle-${i}" ${hasSub ? 'checked' : ''}
                           onchange="toggleSub(${i}, this.checked)">
                    <label class="form-check-label small text-muted" for="sub-toggle-${i}">Dynamic by metric</label>
                </div>
            </td>
        </tr>
        ${hasSub ? renderSubEditor(band, i) : ''}`;
    });
}

// Renders the dynamic sub-rule editor row for a band (used for Pink / any band).
function renderSubEditor(band, i) {
    const sub    = band.sub || { metric: 'ebay_price', bands: [] };
    const metric = sub.metric || 'ebay_price';
    const unit   = (SUB_METRICS[metric] || {}).unit || '';
    const step   = (SUB_METRICS[metric] || {}).step || '0.1';
    const opts   = Object.keys(SUB_METRICS).map(function(k) {
        return `<option value="${k}" ${k === metric ? 'selected' : ''}>${SUB_METRICS[k].label}</option>`;
    }).join('');

    const rows = (sub.bands || []).map(function(sb, j) {
        const isLastSub = (parseFloat(sb.max) >= 9999);
        return `
        <tr>
            <td class="text-center text-muted small">${j+1}</td>
            <td>
                ${isLastSub
                    ? '<span class="text-muted small">∞ (catch-all)</span><input type="hidden" value="9999" data-idx="'+i+'" data-sub="max" data-j="'+j+'">'
                    : `<div class="input-group input-group-sm">
                           <input type="number" step="${step}" min="0" class="form-control form-control-sm"
                                  value="${sb.max}" data-idx="${i}" data-sub="max" data-j="${j}" onchange="updateSubBand(this)">
                           <span class="input-group-text">${unit || '≤'}</span>
                       </div>`
                }
            </td>
            <td>
                <div class="input-group input-group-sm">
                    <input type="number" step="0.1" min="0" max="100" class="form-control form-control-sm fw-semibold"
                           value="${sb.bid}" data-idx="${i}" data-sub="bid" data-j="${j}" onchange="updateSubBand(this)">
                    <span class="input-group-text">%</span>
                </div>
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1" onclick="removeSubBand(${i}, ${j})"
                        title="Remove tier">&times;</button>
            </td>
        </tr>`;
    }).join('');

    return `
    <tr class="sub-rule-row">
        <td></td>
        <td colspan="4" class="bg-light">
            <div class="border rounded p-2">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="small fw-semibold text-secondary">
                        <i class="fas fa-layer-group me-1"></i>${band.label || 'Band'} — bid by
                    </span>
                    <select class="form-select form-select-sm" style="width:auto;"
                            data-idx="${i}" onchange="updateSubMetric(${i}, this.value)">${opts}</select>
                    <span class="small text-muted">tiers (top to bottom — first match wins)</span>
                </div>
                <table class="table table-sm table-bordered align-middle mb-2">
                    <thead class="table-light">
                        <tr>
                            <th style="width:40px;">#</th>
                            <th>${(SUB_METRICS[metric] || {}).label || 'Value'} ≤</th>
                            <th>Bid (%)</th>
                            <th style="width:50px;"></th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
                <button type="button" class="btn btn-sm btn-outline-primary py-0" onclick="addSubBand(${i})">
                    <i class="fas fa-plus me-1"></i>Add tier
                </button>
                <span class="small text-muted ms-2">Set the last tier's value to <code>9999</code> as the catch-all.</span>
            </div>
        </td>
    </tr>`;
}

function updateBand(el) {
    const idx   = parseInt(el.dataset.idx);
    const field = el.dataset.field;
    currentRule.bands[idx][field] = field === 'scvr_max' || field === 'bid'
        ? parseFloat(el.value) : el.value;
    // Update color badge live
    if (field === 'color') {
        el.closest('tr').querySelector('.badge').style.background = el.value;
    }
}

// Enable/disable a band's dynamic sub-rule
function toggleSub(idx, enabled) {
    if (enabled) {
        currentRule.bands[idx].sub = {
            metric: 'ebay_price',
            bands: [
                { max: 9999, bid: parseFloat(currentRule.bands[idx].bid) || 2.1 }
            ]
        };
    } else {
        delete currentRule.bands[idx].sub;
    }
    renderRuleBands(currentRule.bands);
}

function updateSubMetric(idx, metric) {
    if (!currentRule.bands[idx].sub) return;
    currentRule.bands[idx].sub.metric = metric;
    renderRuleBands(currentRule.bands);
}

function updateSubBand(el) {
    const idx   = parseInt(el.dataset.idx);
    const j     = parseInt(el.dataset.j);
    const field = el.dataset.sub; // 'max' | 'bid'
    currentRule.bands[idx].sub.bands[j][field] = parseFloat(el.value);
}

function addSubBand(idx) {
    if (!currentRule.bands[idx].sub) return;
    const sb = currentRule.bands[idx].sub.bands;
    // Insert before a trailing 9999 catch-all if present, else append
    const lastIsCatch = sb.length && parseFloat(sb[sb.length - 1].max) >= 9999;
    const newTier = { max: 0, bid: parseFloat(currentRule.bands[idx].bid) || 2.1 };
    if (lastIsCatch) sb.splice(sb.length - 1, 0, newTier);
    else sb.push(newTier);
    renderRuleBands(currentRule.bands);
}

function removeSubBand(idx, j) {
    if (!currentRule.bands[idx].sub) return;
    currentRule.bands[idx].sub.bands.splice(j, 1);
    renderRuleBands(currentRule.bands);
}

// Load rule when modal opens
document.getElementById('sbidRuleModal').addEventListener('show.bs.modal', function() {
    $.get(ruleGetUrl, function(data) {
        currentRule = data;
        renderRuleBands(data.bands || []);
    });
});

// Save rule
document.getElementById('sbid-rule-save-btn').addEventListener('click', function() {
    const errEl = document.getElementById('sbid-rule-err');
    errEl.classList.add('d-none');
    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Saving…';

    $.ajax({
        url: ruleSaveUrl,
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        contentType: 'application/json',
        data: JSON.stringify({ bands: currentRule.bands }),
        success: function(resp) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check me-1"></i>Saved!';
            currentRule = resp.rule; // update in-memory rule immediately
            if (table) table.redraw(true); // re-render S Bid column with new rule
            setTimeout(() => {
                btn.innerHTML = '<i class="fas fa-save me-1"></i>Save Rule';
                bootstrap.Modal.getInstance(document.getElementById('sbidRuleModal')).hide();
            }, 1200);
        },
        error: function(xhr) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save me-1"></i>Save Rule';
            errEl.textContent = 'Error: ' + (xhr.responseJSON?.error || xhr.responseText);
            errEl.classList.remove('d-none');
        }
    });
});

// Push SBID button
document.getElementById('push-sbid-btn').addEventListener('click', function() {
    if (!confirm('Run ebay2:update-suggestedbid now?\nThis will push bids to eBay for all campaign listings.')) return;
    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Pushing…';

    $.ajax({
        url: pushSbidUrl,
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        timeout: 300000,
        success: function(resp) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check me-1"></i>Done!';
            alert('✅ Push complete!\n\n' + (resp.output || '').substring(0, 500));
            setTimeout(() => btn.innerHTML = '<i class="fas fa-cloud-upload-alt me-1"></i>Push SBID', 3000);
        },
        error: function(xhr) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-cloud-upload-alt me-1"></i>Push SBID';
            alert('Error: ' + (xhr.responseJSON?.error || xhr.responseText));
        }
    });
});

// ── Dilution Rule ───────────────────────────────────
// DIL = (L30 sold / Inventory) × 100. Bands evaluated top-to-bottom, first DIL ≤ max wins.
const dilGetUrl  = '/ebay2/campaign-ads/dil-rule';
const dilSaveUrl = '/ebay2/campaign-ads/dil-rule';
let currentDilRule = @json($dilRule ?? ['bands' => []]);

// DIL value for a row (0 when inventory is 0 — treated as the lowest/worst band)
function dilValue(row) {
    const inv = parseFloat(row && row.shopify_inv) || 0;
    const l30 = parseFloat(row && row.shopify_qty)  || 0;
    return inv > 0 ? (l30 / inv) * 100 : 0;
}

// Color for a DIL% from the dynamic dilution rule
function getDilColor(dil) {
    const d = parseFloat(dil);
    const bands = currentDilRule.bands || [];
    for (let i = 0; i < bands.length; i++) {
        if (d <= parseFloat(bands[i].dil_max)) {
            return bands[i].color || '#333';
        }
    }
    const last = bands[bands.length - 1];
    return last ? (last.color || '#333') : '#e83e8c';
}

// True when value falls in the last (Pink / catch-all) band
function isPinkBand(value, bands) {
    const n = (bands || []).length;
    if (!n) return false;
    for (let i = 0; i < n; i++) {
        const max = parseFloat(bands[i].scvr_max != null ? bands[i].scvr_max : bands[i].dil_max);
        if (value <= max) return i === n - 1;
    }
    return true;
}

function pinkBidOf(bands) {
    const last = (bands || [])[(bands || []).length - 1] || { bid: 2.1, color: '#e83e8c' };
    return { bid: parseFloat(last.bid), color: last.color || '#e83e8c' };
}

// Combined S Bid for the column — mirrors the command:
// if SCVR or DIL is Pink → push the Pink bid; else normal SCVR rule (skip when 0).
function getCombinedSbid(row) {
    const sold  = parseFloat(row.ebay_l30) || 0;
    const views = parseFloat(row.views)    || 0;
    const scvr  = views > 0 ? (sold / views) * 100 : 0;
    const dil   = dilValue(row);

    if (isPinkBand(dil, currentDilRule.bands || [])) {
        const b = pinkBidOf(currentDilRule.bands);
        return { bid: b.bid, color: b.color, skip: false };
    }
    if (isPinkBand(scvr, currentRule.bands || [])) {
        const b = pinkBidOf(currentRule.bands);
        return { bid: b.bid, color: b.color, skip: false };
    }
    return getBidFromRule(scvr, row);
}

function renderDilBands(bands) {
    const tbody = document.getElementById('dil-bands-body');
    tbody.innerHTML = '';
    bands.forEach(function(band, i) {
        const isLast = (parseFloat(band.dil_max) >= 9999);
        tbody.innerHTML += `
        <tr>
            <td class="text-center text-muted small">${i+1}</td>
            <td><input type="text" class="form-control form-control-sm" value="${band.label || ''}"
                       data-idx="${i}" data-field="label" onchange="updateDilBand(this)"></td>
            <td>
                <div class="d-flex align-items-center gap-2">
                    <input type="color" class="form-control form-control-color form-control-sm" style="width:40px;height:31px;"
                           value="${band.color || '#6c757d'}" data-idx="${i}" data-field="color" onchange="updateDilBand(this)">
                    <span class="badge" style="background:${band.color || '#6c757d'};">${band.label || ''}</span>
                </div>
            </td>
            <td>
                ${isLast
                    ? '<span class="text-muted small">∞ (catch-all)</span><input type="hidden" value="9999" data-idx="'+i+'" data-field="dil_max">'
                    : `<input type="number" step="0.01" min="0" class="form-control form-control-sm" value="${band.dil_max}"
                              data-idx="${i}" data-field="dil_max" onchange="updateDilBand(this)">`
                }
            </td>
            <td>
                <div class="input-group input-group-sm">
                    <input type="number" step="0.1" min="0" max="100" class="form-control form-control-sm fw-semibold"
                           value="${band.bid != null ? band.bid : ''}" data-idx="${i}" data-field="bid"
                           style="color:${band.color || '#333'}; font-weight:600;" onchange="updateDilBand(this)">
                    <span class="input-group-text">%</span>
                    <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1" onclick="removeDilBand(${i})"
                            title="Remove band">&times;</button>
                </div>
            </td>
        </tr>`;
    });
}

function updateDilBand(el) {
    const idx   = parseInt(el.dataset.idx);
    const field = el.dataset.field;
    currentDilRule.bands[idx][field] = (field === 'dil_max' || field === 'bid') ? parseFloat(el.value) : el.value;
    if (field === 'color') {
        el.closest('tr').querySelector('.badge').style.background = el.value;
    }
}

function removeDilBand(idx) {
    currentDilRule.bands.splice(idx, 1);
    renderDilBands(currentDilRule.bands);
}

document.getElementById('dil-add-band-btn').addEventListener('click', function() {
    const bands = currentDilRule.bands;
    const lastIsCatch = bands.length && parseFloat(bands[bands.length - 1].dil_max) >= 9999;
    const newBand = { dil_max: 0, bid: 2.1, label: 'New', color: '#6c757d' };
    if (lastIsCatch) bands.splice(bands.length - 1, 0, newBand);
    else bands.push(newBand);
    renderDilBands(bands);
});

// Load rule when modal opens
document.getElementById('dilRuleModal').addEventListener('show.bs.modal', function() {
    $.get(dilGetUrl, function(data) {
        currentDilRule = data;
        renderDilBands(data.bands || []);
    });
});

// Save rule
document.getElementById('dil-rule-save-btn').addEventListener('click', function() {
    const errEl = document.getElementById('dil-rule-err');
    errEl.classList.add('d-none');
    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Saving…';

    $.ajax({
        url: dilSaveUrl,
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        contentType: 'application/json',
        data: JSON.stringify({ bands: currentDilRule.bands }),
        success: function(resp) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check me-1"></i>Saved!';
            currentDilRule = resp.rule;
            if (table) table.redraw(true);
            setTimeout(() => {
                btn.innerHTML = '<i class="fas fa-save me-1"></i>Save Rule';
                bootstrap.Modal.getInstance(document.getElementById('dilRuleModal')).hide();
            }, 1200);
        },
        error: function(xhr) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save me-1"></i>Save Rule';
            errEl.textContent = 'Error: ' + (xhr.responseJSON?.error || xhr.responseText);
            errEl.classList.remove('d-none');
        }
    });
});
</script>
@endsection
