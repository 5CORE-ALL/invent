@extends('layouts.vertical', ['title' => 'eBay 2 Campaign Ads — Raw Data', 'mode' => '', 'demo' => ''])

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
            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#sbidRuleModal"
                    title="eBay 2 Sbid Rule — For L7 Views that set the S Bid column">
                <i class="fas fa-sliders-h me-1"></i>Sbid Rule
            </button>
            <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#dilRuleModal">
                <i class="fas fa-tint me-1"></i>Dil Rule
            </button>
            {{-- Push SBID cron removed — S Bid Autopush runs when a slab or 0-sold (ES Bid) value changes. --}}
            <button class="btn btn-sm btn-outline-secondary" onclick="table.download('csv','ebay2_campaign_ads.csv')">
                <i class="fas fa-download me-1"></i>CSV
            </button>
        </div>
    </div>

    @include('campaign.partials.ebay-campaign-ads-stat-badges', [
        'badgePrefix' => 'eca2',
        'badgesUrl' => route('ebay2.campaign.ads.badges'),
        'storeSalesTitle' => 'eBay 2 L30 store sales',
    ])

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
                    with bid calculated from the eBay 2 <strong>Sbid Rule</strong> slabs (For L7 Views).
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

{{-- Sbid Rule Modal — eBay 2 View VS SBID (ebay_sbid_rules.key = ebay2_sbid_slabs). --}}
<div class="modal fade" id="sbidRuleModal" tabindex="-1" aria-labelledby="sbidRuleModalLabel" aria-hidden="true">
    <style>
        #sbidRuleModal .modal-dialog { max-width: 98vw; width: 98vw; margin: 0.5rem auto; }
        #sbid-slab-rule-table thead th { background-color: #fffef2 !important; color: #000 !important; }
        #sbidRuleModal input[type=number]::-webkit-inner-spin-button,
        #sbidRuleModal input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
        #sbidRuleModal input[type=number] { -moz-appearance: textfield; appearance: textfield; }
        #sbidRuleModal .form-control, #sbidRuleModal .form-select { border-radius: 0.6rem; }
    </style>
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="sbidRuleModalLabel">
                    <i class="fas fa-sliders-h me-2 text-primary"></i>View VS SBID
                    <span class="badge bg-primary ms-2" style="font-size:11px;">eBay 2 only</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                    <label for="sbid-es-bid-input" class="form-label mb-0 small fw-semibold">ES Bid (%)</label>
                    <input type="number" id="sbid-es-bid-input" step="0.1" min="0"
                           class="form-control form-control-sm text-end fw-semibold" style="width:88px;"
                           placeholder="—" title="Editable. Used only when eBay L30 (EL30) is 0. Leave blank to use each row's ES Bid.">
                    <span class="small text-muted">Only for EL30 = 0.
                        <span id="sbid-es-bid-count" class="fw-semibold"></span>
                    </span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle" id="sbid-slab-rule-table" style="min-width: 520px;">
                        <thead class="table-light">
                            <tr>
                                <th rowspan="2" style="width:34px;" class="text-center align-middle">#</th>
                                <th colspan="2" class="text-center">For L7 Views</th>
                                <th rowspan="2" style="width:72px;" class="align-middle text-center"
                                    title="Listings whose L7 Views fall in this slab">Count</th>
                                <th rowspan="2" style="width:100px;" class="align-middle text-center">S Bid (%)</th>
                                <th rowspan="2" style="width:44px;" class="align-middle"></th>
                            </tr>
                            <tr>
                                <th class="text-center small text-muted">Min</th><th class="text-center small text-muted">Max</th>
                            </tr>
                        </thead>
                        <tbody id="sbid-slab-rules-body">
                            {{-- filled by JS --}}
                        </tbody>
                    </table>
                </div>

                <button type="button" class="btn btn-sm btn-primary mb-2" id="sbid-slab-add-rule-btn">
                    <i class="fas fa-plus me-1"></i>Add rule / slab
                </button>
                <p class="small text-danger mb-0 mt-2 d-none" id="sbid-slab-rule-err"></p>
            </div>
            <div class="modal-footer py-2 d-flex justify-content-between">
                <span class="small text-muted" id="sbid-slab-rule-status"></span>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-sm btn-success" id="sbid-slab-apply-btn"
                            title="Autopush is on. Changing a slab or the 0-sold (ES Bid) value saves the rule and pushes the new S Bid to eBay.">
                        <i class="fas fa-bolt me-1"></i>Autopush
                    </button>
                    <button type="button" class="btn btn-sm btn-primary" id="sbid-slab-rule-save-btn">
                        <i class="fas fa-save me-1"></i>Save Rule
                    </button>
                </div>
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
                    Used for the Dil % column colours. Bid push uses the eBay 2 <strong>Sbid Rule</strong> slabs.
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
let allLoadedListingIds = [];
const selectedIds = new Set();

function selectAllListings(checked) {
    if (checked) {
        allLoadedListingIds.forEach(lid => selectedIds.add(lid));
    } else {
        allLoadedListingIds.forEach(lid => selectedIds.delete(lid));
    }
    document.querySelectorAll('.row-cb').forEach(cb => { cb.checked = !!checked; });
    const headerCb = document.getElementById('select-all-cb');
    if (headerCb) headerCb.checked = !!checked;
    updateSelectedCount();
}

function syncSelectAllHeader() {
    const headerCb = document.getElementById('select-all-cb');
    if (!headerCb) return;
    headerCb.checked = allLoadedListingIds.length > 0
        && allLoadedListingIds.every(lid => selectedIds.has(lid));
}

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
                allLoadedListingIds = (resp.data || [])
                    .map(d => d && d.listing_id)
                    .filter(lid => lid != null)
                    .map(String);
                Array.from(selectedIds).forEach(lid => {
                    if (!allLoadedListingIds.includes(lid)) selectedIds.delete(lid);
                });
                table.replaceData(resp.data);
                updateSelectedCount();
                syncSelectAllHeader();
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
    loadEbayCampaignAdsStatBadges(@json(route('ebay2.campaign.ads.badges')), 'eca2');

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
                title: '',
                field: '_select', width: 40, hozAlign: 'center',
                headerSort: false, frozen: true,
                titleFormatter: function() {
                    const cb = document.createElement('input');
                    cb.type = 'checkbox';
                    cb.id = 'select-all-cb';
                    cb.style.cursor = 'pointer';
                    cb.title = 'Select all rows across every page';
                    cb.checked = allLoadedListingIds.length > 0
                        && allLoadedListingIds.every(lid => selectedIds.has(lid));
                    cb.addEventListener('click', function(e) { e.stopPropagation(); });
                    cb.addEventListener('change', function(e) {
                        e.stopPropagation();
                        selectAllListings(cb.checked);
                    });
                    return cb;
                },
                formatter: function(cell) {
                    const lid = String(cell.getRow().getData().listing_id);
                    const checked = selectedIds.has(lid) ? 'checked' : '';
                    return `<input type="checkbox" class="row-cb" data-lid="${lid}" ${checked} style="cursor:pointer;">`;
                },
                cellClick: function(e, cell) {
                    e.stopPropagation();
                    const lid = String(cell.getRow().getData().listing_id);
                    const cb  = cell.getElement().querySelector('.row-cb');
                    if (!cb || !lid || lid === 'undefined' || lid === 'null') return;
                    if (selectedIds.has(lid)) { selectedIds.delete(lid); cb.checked = false; }
                    else                       { selectedIds.add(lid);    cb.checked = true;  }
                    updateSelectedCount();
                    syncSelectAllHeader();
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
                title: 'ES Bid', field: 'suggested_bid', width: 110, hozAlign: 'center',
                sorter: 'number',
                formatter: function(cell) {
                    const v = parseFloat(cell.getValue());
                    return isNaN(v) ? '—' : `<span class="text-info fw-semibold">${v.toFixed(1)}%</span>`;
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
                title: 'Price', field: 'metric_price', width: 110, hozAlign: 'center',
                sorter: 'number',
                formatter: function(cell) {
                    const v = parseFloat(cell.getValue());
                    return isNaN(v) || v === 0 ? '—' : `<span class="fw-semibold">$${v.toFixed(2)}</span>`;
                }
            },
            {
                title: 'S Bid', field: 'ebay_l30', width: 110, hozAlign: 'center',
                headerTooltip: 'EL30 = 0 → ES Bid. Otherwise Sbid Rule slabs (For L7 Views). First matching rule wins. No match → —.',
                sorter: function(a, b, aRow, bRow) {
                    return getCombinedSbid(aRow.getData()).bid - getCombinedSbid(bRow.getData()).bid;
                },
                formatter: function(cell) {
                    const res = getCombinedSbid(cell.getRow().getData());
                    if (res.skip) {
                        const tip = res.via === 'es_bid' ? 'EL30 is 0 but no ES Bid' : 'No matching Sbid Rule slab';
                        return `<span class="text-muted" title="${tip}" style="font-size:11px;">— no sbid</span>`;
                    }
                    const color = res.via === 'es_bid' ? '#0dcaf0' : res.color;
                    return `<span style="color:${color}; font-weight:700;">${res.bid.toFixed(1)}%</span>`;
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

// ── S Bid from eBay 2 View VS SBID slabs (ebay2_sbid_slabs) ──
let currentSbidSlabs = [];
let currentSbidEsBid = null;

function sbidSlabInRange(val, min, max) {
    if (min !== null && min !== undefined && min !== '' && val < parseFloat(min)) return false;
    if (max !== null && max !== undefined && max !== '' && val > parseFloat(max)) return false;
    return true;
}

function getCombinedSbid(row) {
    const el30 = parseFloat(row.ebay_l30) || 0;
    if (el30 <= 0) {
        const override = parseFloat(currentSbidEsBid);
        const esBid = (isFinite(override) && override > 0)
            ? override
            : (parseFloat(row.suggested_bid) || 0);
        if (isFinite(esBid) && esBid > 0) {
            return { bid: esBid, color: '#0dcaf0', skip: false, via: 'es_bid' };
        }
        return { bid: 0, color: '#6c757d', skip: true, via: 'es_bid' };
    }

    const l7Views = parseFloat(row.l7_views) || 0;
    for (let i = 0; i < currentSbidSlabs.length; i++) {
        const r = currentSbidSlabs[i];
        if (sbidSlabInRange(l7Views, r.l7_views_min, r.l7_views_max)) {
            const bid = parseFloat(r.sbid);
            if (isFinite(bid) && bid > 0) return { bid: bid, color: '#0d6efd', skip: false };
            return { bid: 0, color: '#6c757d', skip: true };
        }
    }
    return { bid: 0, color: '#6c757d', skip: true };
}

const sbidSlabGetUrl   = @json(url('/ebay2/campaign-ads/sbid-slab-rule'));
const sbidSlabSaveUrl  = @json(url('/ebay2/campaign-ads/sbid-slab-rule'));
const sbidSlabApplyUrl = @json(url('/ebay2/campaign-ads/push-sbid-slabs'));

function sbidSlabNumAttr(v) {
    return (v === null || v === undefined || v === '' || isNaN(v)) ? '' : v;
}

function autofillSbidSlabMins(rules) {
    if (!rules || !rules.length) return;
    const firstMin = parseFloat(rules[0].l7_views_min);
    const firstMax = parseFloat(rules[0].l7_views_max);
    const diff = (isFinite(firstMin) && isFinite(firstMax)) ? (firstMax - firstMin) : null;
    for (let i = 1; i < rules.length; i++) {
        const prevMax = rules[i - 1].l7_views_max;
        if (prevMax === null || prevMax === undefined || prevMax === '' || isNaN(prevMax)) break;
        const prev = parseFloat(prevMax);
        rules[i].l7_views_min = prev + 1;
        if (diff !== null && diff > 0) {
            rules[i].l7_views_max = prev + diff;
        }
    }
}

function sbidSlabRangeInputs(rule, key, idx) {
    const locked = (idx > 0 && key === 'l7_views')
        ? ' readonly tabindex="-1" style="background:#f8f9fa;"'
        : '';
    const minTitle = idx > 0 ? ' title="Auto: previous Max + 1"' : '';
    const maxTitle = idx > 0 ? ' title="Auto: same difference as Rule 1"' : ' title="Sets the difference for all following slabs"';
    return `
        <td><input type="number" step="0.01" class="form-control form-control-sm text-end"
                   value="${sbidSlabNumAttr(rule[key + '_min'])}" data-field="${key}_min"
                   onchange="sbidSlabUpdate(this)" placeholder="—"${locked}${minTitle}></td>
        <td><input type="number" step="0.01" class="form-control form-control-sm text-end"
                   value="${sbidSlabNumAttr(rule[key + '_max'])}" data-field="${key}_max"
                   onchange="sbidSlabUpdate(this)" placeholder="—"${locked}${maxTitle}></td>`;
}

function countRowsBySlab(rules) {
    const counts = rules.map(function() { return 0; });
    let esCount = 0;
    let rows = [];
    try {
        if (typeof table !== 'undefined' && table) rows = table.getData('active') || table.getData() || [];
    } catch (e) { rows = []; }
    rows.forEach(function(d) {
        const el30 = parseFloat(d.ebay_l30) || 0;
        if (el30 <= 0) {
            esCount++;
            return;
        }
        const l7 = parseFloat(d.l7_views) || 0;
        for (let i = 0; i < rules.length; i++) {
            if (sbidSlabInRange(l7, rules[i].l7_views_min, rules[i].l7_views_max)) {
                counts[i]++;
                break;
            }
        }
    });
    const esCountEl = document.getElementById('sbid-es-bid-count');
    if (esCountEl) esCountEl.textContent = esCount ? '(' + esCount + ' SKUs)' : '';
    return counts;
}

function renderSbidSlabRules(rules) {
    const tbody = document.getElementById('sbid-slab-rules-body');
    if (!tbody) return;
    tbody.innerHTML = '';
    if (!rules.length) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-center text-muted small py-3">
            No rules yet — click <strong>Add rule / slab</strong> to create one.</td></tr>`;
        return;
    }
    autofillSbidSlabMins(rules);
    const slabCounts = countRowsBySlab(rules);
    rules.forEach(function(rule, i) {
        const tr = document.createElement('tr');
        tr.setAttribute('data-idx', i);
        const count = slabCounts[i] || 0;
        tr.innerHTML = `
            <td class="text-center text-muted small">${i + 1}</td>
            ${sbidSlabRangeInputs(rule, 'l7_views', i)}
            <td class="text-center fw-semibold" title="Listings in this slab">${count}</td>
            <td><input type="number" step="0.1" min="0" class="form-control form-control-sm text-end fw-semibold"
                       value="${sbidSlabNumAttr(rule.sbid)}" data-field="sbid"
                       ${i === 0 ? 'title="Changing this sets following rows to −1 each, minimum 2%"' : ''}
                       onchange="sbidSlabUpdate(this)"></td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1"
                        onclick="sbidSlabRemove(${i})" title="Remove rule">&times;</button>
            </td>`;
        tbody.appendChild(tr);
    });
}

function readEsBidInput() {
    const el = document.getElementById('sbid-es-bid-input');
    if (!el || el.value === '') return null;
    const n = parseFloat(el.value);
    return (isFinite(n) && n > 0) ? n : null;
}

function writeEsBidInput(val) {
    const el = document.getElementById('sbid-es-bid-input');
    if (!el) return;
    el.value = (val === null || val === undefined || val === '' || isNaN(val)) ? '' : val;
}

function applyLoadedSbidSlabs(data) {
    currentSbidSlabs = (data && Array.isArray(data.rules)) ? data.rules : [];
    currentSbidEsBid = (data && data.es_bid != null && data.es_bid !== '') ? parseFloat(data.es_bid) : null;
    if (!isFinite(currentSbidEsBid) || currentSbidEsBid <= 0) currentSbidEsBid = null;
    writeEsBidInput(currentSbidEsBid);
    renderSbidSlabRules(currentSbidSlabs);
    if (typeof table !== 'undefined' && table && table.redraw) table.redraw(true);
}

$.get(sbidSlabGetUrl, applyLoadedSbidSlabs).fail(function(xhr) {
    console.error('[Sbid slabs] load failed', xhr.status, xhr.responseText);
});

function cascadeSbidFromFirstRow(rules) {
    if (!rules || !rules.length) return;
    const first = parseFloat(rules[0].sbid);
    if (!isFinite(first)) return;
    for (let i = 1; i < rules.length; i++) {
        rules[i].sbid = Math.max(2, first - i);
    }
}

function sbidSlabUpdate(el) {
    const tr = el.closest('tr');
    const idx = parseInt(tr.getAttribute('data-idx'), 10);
    const field = el.dataset.field;
    if (!currentSbidSlabs[idx]) return;
    currentSbidSlabs[idx][field] = (el.value === '' ? null : parseFloat(el.value));
    if (field === 'sbid' && idx === 0) {
        cascadeSbidFromFirstRow(currentSbidSlabs);
        renderSbidSlabRules(currentSbidSlabs);
        if (typeof table !== 'undefined' && table && table.redraw) table.redraw(true);
        scheduleEbay2SbidAutopush();
        return;
    }
    if (field === 'l7_views_min' || field === 'l7_views_max') {
        renderSbidSlabRules(currentSbidSlabs);
    }
    if (typeof table !== 'undefined' && table && table.redraw) table.redraw(true);
    scheduleEbay2SbidAutopush();
}

function sbidSlabRemove(idx) {
    currentSbidSlabs.splice(idx, 1);
    renderSbidSlabRules(currentSbidSlabs);
    if (typeof table !== 'undefined' && table && table.redraw) table.redraw(true);
    scheduleEbay2SbidAutopush();
}

document.getElementById('sbid-slab-add-rule-btn').addEventListener('click', function() {
    currentSbidSlabs.push({ l7_views_min: null, l7_views_max: null, sbid: 2.1 });
    cascadeSbidFromFirstRow(currentSbidSlabs);
    renderSbidSlabRules(currentSbidSlabs);
    scheduleEbay2SbidAutopush();
});

document.getElementById('sbid-es-bid-input').addEventListener('input', function() {
    currentSbidEsBid = readEsBidInput();
    if (typeof table !== 'undefined' && table && table.redraw) table.redraw(true);
    scheduleEbay2SbidAutopush();
});

document.getElementById('sbidRuleModal').addEventListener('show.bs.modal', function() {
    writeEsBidInput(currentSbidEsBid);
    renderSbidSlabRules(currentSbidSlabs);
});

let ebay2SbidAutopushTimer = null;
let ebay2SbidAutopushBusy = false;

function setEbay2AutopushLabel(html) {
    const btn = document.getElementById('sbid-slab-apply-btn');
    if (btn) btn.innerHTML = html;
}

function collectEbay2AutopushSkus() {
    const skus = [];
    if (typeof table === 'undefined' || !table) return skus;
    table.getRows('active').forEach(function(r) {
        const rd = r.getData();
        const sku = rd.resolved_sku;
        if (!sku) return;
        const res = getCombinedSbid(rd);
        if (res && !res.skip && res.bid > 0) skus.push(sku);
    });
    return skus;
}

function autoPushEbay2Sbid() {
    const statusEl = document.getElementById('sbid-slab-rule-status');
    const errEl = document.getElementById('sbid-slab-rule-err');
    if (errEl) errEl.classList.add('d-none');
    if (!currentSbidSlabs.length) return;
    const skus = collectEbay2AutopushSkus();
    if (!skus.length) {
        if (statusEl) statusEl.textContent = 'Autopush: no listings match a slab or 0-sold ES Bid.';
        return;
    }
    if (ebay2SbidAutopushBusy) return;
    ebay2SbidAutopushBusy = true;
    setEbay2AutopushLabel('<i class="fas fa-spinner fa-spin me-1"></i>Pushing…');
    if (statusEl) statusEl.textContent = 'Autopush: ' + skus.length + ' listing(s)…';
    $.ajax({
        url: sbidSlabApplyUrl,
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        contentType: 'application/json',
        data: JSON.stringify({ skus: skus }),
        success: function(resp) {
            ebay2SbidAutopushBusy = false;
            setEbay2AutopushLabel('<i class="fas fa-bolt me-1"></i>Autopush');
            const s = resp.success || 0, f = resp.failed || 0, sk = resp.skipped || 0;
            if (statusEl) statusEl.textContent = 'Autopush: ' + s + ' pushed · ' + f + ' failed · ' + sk + ' skipped';
        },
        error: function(xhr) {
            ebay2SbidAutopushBusy = false;
            setEbay2AutopushLabel('<i class="fas fa-bolt me-1"></i>Autopush');
            if (errEl) {
                errEl.textContent = 'Error: ' + ((xhr.responseJSON && xhr.responseJSON.error) || xhr.responseText);
                errEl.classList.remove('d-none');
            }
        }
    });
}

function syncEbay2SbidRulesFromDom() {
    const tbody = document.getElementById('sbid-slab-rules-body');
    if (!tbody) return;
    tbody.querySelectorAll('tr[data-idx]').forEach(function(tr) {
        const idx = parseInt(tr.getAttribute('data-idx'), 10);
        if (!currentSbidSlabs[idx]) return;
        tr.querySelectorAll('input[data-field]').forEach(function(el) {
            currentSbidSlabs[idx][el.dataset.field] = (el.value === '' ? null : parseFloat(el.value));
        });
    });
}

function saveEbay2SbidRules(thenPush) {
    syncEbay2SbidRulesFromDom();
    const errEl = document.getElementById('sbid-slab-rule-err');
    if (errEl) errEl.classList.add('d-none');
    const btn = document.getElementById('sbid-slab-rule-save-btn');
    const csrf = $('meta[name="csrf-token"]').attr('content') || '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Saving…';
    }
    $.ajax({
        url: sbidSlabSaveUrl,
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': csrf,
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        },
        contentType: 'application/json',
        data: JSON.stringify({
            rules: (currentSbidSlabs || []).map(function(r) {
                return {
                    label: r.label || '',
                    l7_views_min: r.l7_views_min,
                    l7_views_max: r.l7_views_max,
                    sbid: r.sbid
                };
            }),
            es_bid: readEsBidInput(),
            _token: csrf
        }),
        success: function(resp) {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check me-1"></i>Saved!';
                setTimeout(function() { btn.innerHTML = '<i class="fas fa-save me-1"></i>Save Rule'; }, 1200);
            }
            if (resp.rule) applyLoadedSbidSlabs(resp.rule);
            if (thenPush) autoPushEbay2Sbid();
        },
        error: function(xhr) {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save me-1"></i>Save Rule';
            }
            if (errEl) {
                errEl.textContent = 'Error: ' + ((xhr.responseJSON && (xhr.responseJSON.error || xhr.responseJSON.message)) || xhr.responseText);
                errEl.classList.remove('d-none');
            }
        }
    });
}

function scheduleEbay2SbidAutopush() {
    clearTimeout(ebay2SbidAutopushTimer);
    ebay2SbidAutopushTimer = setTimeout(function() {
        saveEbay2SbidRules(true);
    }, 800);
}

document.getElementById('sbid-slab-rule-save-btn').addEventListener('click', function() {
    saveEbay2SbidRules(true);
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
