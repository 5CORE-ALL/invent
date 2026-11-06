@extends('layouts.vertical', ['title' => 'eBay 2 Campaign Ads — Raw Data', 'mode' => '', 'demo' => ''])

@section('content')
<div class="container-fluid px-4 py-3">

    {{-- Header --}}
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h4 class="mb-0 fw-bold">eBay 2 Campaign Ads</h4>
            <small class="text-muted">Raw data from <code>ebay2_campaign_ads</code> table · Eligible ads auto-enroll daily into the matching parent campaign</small>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <span class="badge bg-primary fs-6" id="total-count">Loading…</span>
            <button class="btn btn-sm btn-warning" id="auto-enroll-eligible-btn"
                    title="Enroll every Eligible (RECOMMENDED) listing into its matching parent PMT campaign and start RUNNING">
                <i class="fas fa-bolt me-1"></i>Start Eligible Ads
            </button>
            <button class="btn btn-sm btn-info text-white d-none" id="enroll-selected-btn" data-bs-toggle="modal" data-bs-target="#enrollModal">
                <i class="fas fa-plus-circle me-1"></i>Enroll in Campaign (<span id="enroll-count">0</span>)
            </button>
            <button class="btn btn-sm btn-outline-secondary" title="Download the rows on this page" onclick="table.download('csv','ebay2_campaign_ads.csv')">
                <i class="fas fa-download me-1"></i>CSV
            </button>
        </div>
    </div>

    @include('campaign.partials.ebay-campaign-ads-stat-badges', [
        'badgePrefix' => 'eca2',
        'badgesUrl' => route('ebay2.campaign.ads.badges'),
        'storeSalesTitle' => 'eBay 2 L30 store sales',
        'showCbidNullBadge' => true,
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
                        <option value="" selected>All Status</option>
                        <option value="RUNNING">RUNNING</option>
                        <option value="PAUSED">PAUSED</option>
                        <option value="ENDED">ENDED</option>
                        <option value="INACTIVE">INACTIVE</option>
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
                    <strong id="enroll-listing-count">0</strong> listing(s) can be enrolled
                    (ENDED / no campaign). Already <strong>RUNNING</strong> ads are skipped.
                    Bid is the listing <strong>ES Bid</strong>.
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

@include('campaign.partials.ebay-report-history', ['historyUrl' => url('/ebay2/listing-report-history')])

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
    .eca-sync-cell { display: inline-flex; align-items: center; justify-content: center; gap: 5px; white-space: nowrap; }
    .eca-sync-dot {
        width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0;
        box-shadow: 0 0 0 1px rgba(15, 23, 42, 0.12);
    }
    .eca-sync-dot.is-green { background: #16a34a; }
    .eca-sync-dot.is-yellow { background: #f59e0b; }
    .eca-sync-dot.is-red { background: #dc2626; }
    .eca-push-alert {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        background: #dc2626;
        color: #fff;
        font-size: 11px;
        font-weight: 700;
        line-height: 1;
        cursor: help;
    }
</style>
@endsection

@section('script-after-vite')
<script src="https://unpkg.com/tabulator-tables@6.2.1/dist/js/tabulator.min.js"></script>
<script>
let table;
let allLoadedListingIds = [];
const selectedIds = new Set();
const selectedRows = new Map();

function rememberSelected(row) {
    const lid = String((row && row.listing_id) || '');
    if (!lid || lid === 'undefined' || lid === 'null') return;
    selectedIds.add(lid);
    selectedRows.set(lid, row);
}

function forgetSelected(lid) {
    const key = String(lid);
    selectedIds.delete(key);
    selectedRows.delete(key);
}

function selectAllListings(checked) {
    const rows = (typeof table !== 'undefined' && table) ? (table.getData() || []) : [];
    rows.forEach(function (row) {
        const lid = String((row && row.listing_id) || '');
        if (!lid || lid === 'undefined' || lid === 'null') return;
        if (checked) rememberSelected(row);
        else forgetSelected(lid);
    });
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

function ebay2AdsQuery(params) {
    const q = new URLSearchParams();
    const search = $('#search-input').val() || '';
    const funding = $('#funding-filter').val() || '';
    const status = $('#status-filter').val() || '';
    const promote = $('#promote-filter').val() || '';
    if (search) q.set('search', search);
    if (funding) q.set('funding_strategy', funding);
    if (status) q.set('campaign_status', status);
    if (promote) q.set('promote_with_ad', promote);
    if (cbidNullFilterOn) q.set('missing', '1');
    q.set('page', String((params && params.page) || 1));
    q.set('size', String((params && params.size) || 100));
    return q.toString();
}

function loadData() {
    selectedIds.clear();
    selectedRows.clear();
    updateSelectedCount();
    if (!table) return;
    if (table.getPage() === 1) {
        table.replaceData();
    } else {
        table.setPage(1);
    }
}

function listingHasCampaign(listingId) {
    const row = rowByListingId(listingId);
    return !!(row && campaignIdPresent(row));
}

function rowByListingId(listingId) {
    if (listingId == null || listingId === '') return null;
    const lid = String(listingId);
    if (selectedRows.has(lid)) return selectedRows.get(lid);
    if (typeof table === 'undefined' || !table) return null;
    return (table.getData() || []).find(function (r) {
        return String(r && r.listing_id) === lid;
    }) || null;
}

function campaignIdPresent(row) {
    const cid = row && row.campaign_id;
    return cid != null && cid !== '' && cid !== 'null';
}

function campaignStatusOf(row) {
    return String((row && row.campaign_status) || '').toUpperCase();
}

/** Already in a live campaign — do not treat as enrollable. */
function isInActiveCampaign(row) {
    if (!row) return false;
    const status = campaignStatusOf(row);
    if (status === 'RUNNING' || status === 'PAUSED' || status === 'SYSTEM_PAUSED') return true;
    const bid = parseFloat(row.bid_percentage);
    return campaignIdPresent(row) && isFinite(bid) && bid > 0 && status !== 'ENDED' && status !== 'INACTIVE';
}

/** ENDED / no-campaign rows are enrollable — enroll remaps to the live listing id. */
function isEnrollable(row) {
    return !!(row && !isInActiveCampaign(row));
}

function selectedEnrollableIds() {
    return Array.from(selectedIds).filter(function (lid) {
        return isEnrollable(rowByListingId(lid));
    });
}

function isMissingAd(row) {
    const inv = parseFloat(row && row.shopify_inv) || 0;
    const price = parseFloat(row && row.metric_price);
    const skuMatched = row && (row.sku_matched == 1 || row.sku_matched === true);
    const campaignId = row && row.campaign_id;
    const noCampaign = campaignId == null || campaignId === '' || campaignId === 'null';
    if (!noCampaign || inv <= 0 || !skuMatched || !isFinite(price) || price <= 0) {
        return false;
    }
    return !listingHasCampaign(row.listing_id);
}

let cbidNullFilterOn = false;

function applyCbidNullFilter() {
    const wrap = document.getElementById('eca2-badge-cbidnull-wrap');
    if (wrap) {
        wrap.classList.toggle('is-on', cbidNullFilterOn);
        wrap.setAttribute('aria-pressed', cbidNullFilterOn ? 'true' : 'false');
    }
}

function clearFilters() {
    $('#search-input').val('');
    $('#funding-filter').val('');
    $('#status-filter').val('');
    $('#promote-filter').val('');
    cbidNullFilterOn = false;
    applyCbidNullFilter();
    loadData();
}

$(document).ready(function () {
    loadEbayCampaignAdsStatBadges(@json(route('ebay2.campaign.ads.badges')), 'eca2');

    table = new Tabulator('#ebay2-campaign-ads-table', {
        ajaxURL: '/ebay2/campaign-ads/data',
        ajaxURLGenerator: function (url, config, params) {
            const qs = ebay2AdsQuery(params);
            return qs ? (url + '?' + qs) : url;
        },
        ajaxResponse: function (url, params, resp) {
            const rows = (resp && Array.isArray(resp.data)) ? resp.data : [];
            const total = Number(resp && resp.total) || 0;
            const size = Number(params && params.size) || 100;
            $('#total-count').text(total.toLocaleString() + ' rows');
            $('#last-updated').text('Updated: ' + new Date().toLocaleTimeString());
            allLoadedListingIds = rows
                .map(function (d) { return d && d.listing_id; })
                .filter(function (lid) { return lid != null; })
                .map(String);
                    applyCbidNullFilter();
            updateSelectedCount();
            syncSelectAllHeader();
            return {
                last_page: Math.max(1, Number(resp && resp.last_page) || Math.ceil(total / size) || 1),
                last_row: total,
                data: rows,
            };
        },
        ajaxError: function (xhr) {
            $('#total-count').text('Error ' + (xhr && xhr.status ? xhr.status : ''));
            console.error('API Error:', xhr && xhr.status, xhr && xhr.responseText);
        },
        layout: 'fitDataFill',
        height: 'calc(100vh - 260px)',
        columnDefaults: { hozAlign: 'center', headerHozAlign: 'center' },
        pagination: true,
        paginationMode: 'remote',
        sortMode: 'local',
        filterMode: 'local',
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
                    cb.title = 'Select all rows on this page';
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
                    if (selectedIds.has(lid)) { forgetSelected(lid); cb.checked = false; }
                    else                       { rememberSelected(cell.getRow().getData()); cb.checked = true; }
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
                headerTooltip: 'CP Master Dil = round(OV L30 sold / Inventory × 100). Inv 0 and missing data are blank.',
                sorter: function(a, b, aRow, bRow) {
                    return dilSortValue(aRow.getData()) - dilSortValue(bRow.getData());
                },
                formatter: function(cell) {
                    const dil = dilValue(cell.getRow().getData());
                    if (dil === null) {
                        return '<span class="text-muted" title="No CP Master Dil (OV L30 or Inv missing, or Inv is 0)">—</span>';
                    }
                    return `<span style="color:${getDilColor(dil)}; font-weight:600;">${dil}%</span>`;
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
                title: 'Campaign Name', field: 'campaign_name', width: 260, hozAlign: 'left',
                headerTooltip: 'eBay Promoted Listings campaign name (not SKU). Many campaigns are named after the parent SKU, so the text can match SKU even though this is a different field.',
                formatter: function(cell) {
                    const row = cell.getRow().getData() || {};
                    const name = cell.getValue();
                    const cid = row.campaign_id;
                    if (!name && (cid == null || cid === '')) {
                        return '<span style="color:#aaa; font-size:11px;">No Campaign</span>';
                    }
                    const safeName = String(name || '—').replace(/</g, '&lt;').replace(/"/g, '&quot;');
                    const safeCid = cid ? String(cid).replace(/</g, '&lt;') : '';
                    return `<div style="line-height:1.25;">`
                        + `<div style="color:#212529; font-weight:600;">${safeName}</div>`
                        + (safeCid ? `<div style="color:#6c757d; font-size:11px;">ID ${safeCid}</div>` : '')
                        + `</div>`;
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
                title: 'Status', field: 'campaign_status', width: 110, hozAlign: 'center',
                headerTooltip: 'Campaign status from eBay. Dash / No Campaign = listing is not in a Promoted Listings campaign yet (Eligible on eBay is the Promote column).',
                formatter: function(cell) {
                    const row = cell.getRow().getData() || {};
                    const v = String(cell.getValue() || '').toUpperCase();
                    if (v === 'RUNNING') return '<span class="badge-run">RUNNING</span>';
                    if (v === 'PAUSED')  return '<span class="badge-paus">PAUSED</span>';
                    if (v === 'SYSTEM_PAUSED') return '<span class="badge-paus">SYSTEM_PAUSED</span>';
                    const listingStatus = String(row.listing_status || '').toUpperCase();
                    if (['ENDED', 'INACTIVE', 'UNSOLD', 'COMPLETED', 'SOLD'].includes(listingStatus)) {
                        return '<span class="badge-end" title="Listing ended on eBay">ENDED</span>';
                    }
                    return '<span style="color:#aaa; font-size:11px;" title="Not enrolled in a live campaign">No Campaign</span>';
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
                title: 'Clicks', field: 'rpt_clicks', width: 100, hozAlign: 'center', sorter: 'number',
                headerTooltip: 'Clicks over the last 7 report days. Green = up vs the 7 days before, red = down. Click the dot for each day.',
                formatter: function(cell) {
                    return ebayRptCellHtml(cell.getRow().getData(), 'rpt_clicks', 'rpt_clicks_prev', 'rpt_clicks_prev_date', 'rpt_clicks', false);
                },
                cellClick: function(e, cell) {
                    if (!e.target.closest('.eca-rpt-hist')) return;
                    e.stopPropagation();
                    openEcaReportChart(cell.getRow().getData(), 'rpt_clicks');
                }
            },
            {
                title: 'Spend', field: 'rpt_spend', width: 110, hozAlign: 'center', sorter: 'number',
                headerTooltip: 'Ad spend over the last 7 report days. Green = up vs the 7 days before, red = down. Click the dot for each day.',
                formatter: function(cell) {
                    return ebayRptCellHtml(cell.getRow().getData(), 'rpt_spend', 'rpt_spend_prev', 'rpt_spend_prev_date', 'rpt_spend', true);
                },
                cellClick: function(e, cell) {
                    if (!e.target.closest('.eca-rpt-hist')) return;
                    e.stopPropagation();
                    openEcaReportChart(cell.getRow().getData(), 'rpt_spend');
                }
            },
            {
                title: 'Sales', field: 'rpt_sales', width: 100, hozAlign: 'center', sorter: 'number',
                headerTooltip: 'Sales count over the last 7 report days. Green = up vs the 7 days before, red = down. Click the dot for each day.',
                formatter: function(cell) {
                    return ebayRptCellHtml(cell.getRow().getData(), 'rpt_sales', 'rpt_sales_prev', 'rpt_sales_prev_date', 'rpt_sales', false);
                },
                cellClick: function(e, cell) {
                    if (!e.target.closest('.eca-rpt-hist')) return;
                    e.stopPropagation();
                    openEcaReportChart(cell.getRow().getData(), 'rpt_sales');
                }
            },
            {
                title: 'C Bid', field: 'bid_percentage', width: 110, hozAlign: 'center',
                sorter: 'number',
                headerTooltip: 'Live eBay bid percentage.',
                formatter: function(cell) {
                    const v = parseFloat(cell.getValue());
                    if (isNaN(v)) return '—';
                    const color = v <= 4 ? '#dc3545' : v <= 7 ? '#ffc107' : v <= 13 ? '#198754' : '#e83e8c';
                    return '<span style="color:' + color + '; font-weight:600;">' + v.toFixed(1) + '%</span>';
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
                headerTooltip: 'eBay Recommendation API promoteWithAd. Eligible on Seller Hub = RECOMMENDED. Unknown = last sync was UNDETERMINED (often stale until the next sync).',
                formatter: function(cell) {
                    const row = cell.getRow().getData();
                    if (isInActiveCampaign(row)) {
                        return '<span style="color:#0d6efd; background:#cfe2ff; padding:2px 6px; border-radius:4px; font-size:11px; font-weight:600;">📢 In Campaign</span>';
                    }
                    const v = cell.getValue();
                    if (!v) {
                        return '<span style="color:#198754; background:#d1f5e0; padding:2px 6px; border-radius:4px; font-size:11px; font-weight:600;">⭐ Eligible</span>';
                    }
                    const map = {
                        'RECOMMENDED':        { color: '#198754', bg: '#d1f5e0', label: '⭐ Eligible' },
                        'OPTIONAL':           { color: '#856404', bg: '#fff3cd', label: '⚡ Optional' },
                        'AD_ALREADY_CREATED': { color: '#0d6efd', bg: '#cfe2ff', label: '📢 In Campaign' },
                        'NOT_RECOMMENDED':    { color: '#6c757d', bg: '#f8f9fa', label: '— Not Rec.' },
                        'UNDETERMINED':       { color: '#6c757d', bg: '#f8f9fa', label: '? Undetermined' },
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

    const cbidWrap = document.getElementById('eca2-badge-cbidnull-wrap');
    if (cbidWrap) {
        cbidWrap.addEventListener('click', function () {
            cbidNullFilterOn = !cbidNullFilterOn;
            if (cbidNullFilterOn) {
                $('#status-filter').val('');
                $('#funding-filter').val('');
            } else {
                $('#status-filter').val('');
            }
            loadData();
        });
        cbidWrap.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                cbidWrap.click();
            }
        });
    }
});

function ebayEsc(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// ── Checkbox selection ─────────────────────────────
function updateSelectedCount() {
    const count = selectedIds.size;
    const enrollable = selectedEnrollableIds();
    $('#selected-count, #enroll-count').text(count);
    $('#enroll-listing-count').text(enrollable.length);

    if (count > 0) {
        if (enrollable.length) $('#enroll-selected-btn').removeClass('d-none');
        else                   $('#enroll-selected-btn').addClass('d-none');
    } else {
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

    const eligibleIds = selectedEnrollableIds();

    if (eligibleIds.length === 0) {
        errEl.textContent = 'No enrollable listings selected. RUNNING ads are already in a campaign; pick ENDED or No Campaign rows.';
        errEl.classList.remove('d-none');
        return;
    }

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

document.getElementById('auto-enroll-eligible-btn').addEventListener('click', function() {
    if (!confirm('Start RUNNING ads for all Eligible (RECOMMENDED) listings?\n\nEach listing is enrolled into the matching parent PMT campaign (same name as the parent SKU). A new campaign is created only when that parent campaign does not exist yet.')) {
        return;
    }
    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Starting…';
    $.ajax({
        url: @json(url('/ebay2/campaign-ads/auto-enroll-eligible')),
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        contentType: 'application/json',
        data: '{}',
        timeout: 300000,
        success: function(resp) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-bolt me-1"></i>Start Eligible Ads';
            let msg = `✅ Enrolled: ${resp.success || 0} | ❌ Failed: ${resp.failed || 0} | ⏭ Skipped: ${resp.skipped || 0}`;
            if (resp.created_campaigns) msg += ` | Campaigns created: ${resp.created_campaigns}`;
            msg += '\n\n';
            (resp.results || []).forEach(r => {
                const icon = r.status === 'enrolled' || r.status === 'would_enroll' ? '✅' : r.status === 'skipped' ? '⏭' : '❌';
                msg += `${icon} ${r.sku || r.listing_id} → ${r.status}${r.bid ? ' @ ' + r.bid : ''}${r.campaign_name ? ' · ' + r.campaign_name : ''}${r.reason ? ' (' + r.reason + ')' : ''}\n`;
            });
            alert(msg);
            loadData();
        },
        error: function(xhr) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-bolt me-1"></i>Start Eligible Ads';
            alert('Error: ' + ((xhr.responseJSON && xhr.responseJSON.error) || xhr.responseText.substring(0, 200)));
        }
    });
});

// ── DIL % ───────────────────────────────────────────
// DIL = (L30 sold / Inventory) × 100. The Dil % column uses fixed colours (the editable Dilution Rule was removed).

// CP Master Dil = round(OV L30 sold / Inventory × 100). Inv 0 or missing is not Dil 0.
function dilValue(row) {
    if (!row) return null;
    if (Object.prototype.hasOwnProperty.call(row, 'cp_dil')) {
        if (row.cp_dil === null || row.cp_dil === '') return null;
        const n = Number(row.cp_dil);
        return isFinite(n) ? n : null;
    }
    const invRaw = row.shopify_inv;
    const qtyRaw = row.shopify_qty;
    if (invRaw === null || invRaw === undefined || invRaw === '' || qtyRaw === null || qtyRaw === undefined || qtyRaw === '') {
        return null;
    }
    const inv = Number(invRaw);
    const l30 = Number(qtyRaw);
    if (!isFinite(inv) || inv <= 0 || !isFinite(l30)) return null;
    return Math.round((l30 / inv) * 100);
}
function dilSortValue(row) {
    const d = dilValue(row);
    return d === null ? -1 : d;
}

// Fixed colour for a DIL%: <=16.66 red, <=25 yellow, <=50 green, above that pink.
function getDilColor(dil) {
    const d = parseFloat(dil);
    if (d <= 16.66) return '#a00211';
    if (d <= 25) return '#ffc107';
    if (d <= 50) return '#28a745';
    return '#e83e8c';
}
</script>
@endsection
