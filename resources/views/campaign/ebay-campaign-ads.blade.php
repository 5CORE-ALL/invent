@extends('layouts.vertical', ['title' => 'eBay Campaign Ads — Raw Data', 'mode' => '', 'demo' => ''])

@section('content')
<div class="container-fluid px-4 py-3">

    {{-- Header --}}
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h4 class="mb-0 fw-bold">eBay Campaign Ads</h4>
            <small class="text-muted">Raw data from <code>ebay_campaign_ads</code> table · synced daily</small>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <span class="badge bg-primary fs-6" id="total-count">Loading…</span>
            <button class="btn btn-sm btn-success d-none" id="push-selected-btn">
                <i class="fas fa-cloud-upload-alt me-1"></i>Push Selected (<span id="selected-count">0</span>)
            </button>
            <button class="btn btn-sm btn-danger" id="force-push-btn" type="button" disabled
                    title="Pull live C Bid, then push S Bid until they match. A pending gap is the yellow dot. The red alert is a failed push.">
                <i class="fas fa-exclamation-circle me-1"></i>Force Push (<span id="force-push-count">0</span>)
            </button>
            <button class="btn btn-sm btn-info text-white d-none" id="enroll-selected-btn" data-bs-toggle="modal" data-bs-target="#enrollModal">
                <i class="fas fa-plus-circle me-1"></i>Enroll in Campaign (<span id="enroll-count">0</span>)
            </button>
            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#dilSbidRuleModal"
                    title="S Bid = Dil + Views + CVR + eBay Sold + Std NPFT %, then CVR up / down, then L30 View up / down, then the Min / Max cap.">
                <i class="fas fa-percent me-1"></i>Dil vs SBid
            </button>
            @include('campaign.partials.ebay-dil-sbid-rule', ['part' => 'button', 'account' => 'eBay 1'])
            <button class="btn btn-sm btn-outline-secondary" onclick="table.download('csv','ebay_campaign_ads.csv')">
                <i class="fas fa-download me-1"></i>CSV
            </button>
        </div>
    </div>

    @include('campaign.partials.ebay-campaign-ads-stat-badges', [
        'badgePrefix' => 'eca',
        'badgesUrl' => route('ebay.campaign.ads.badges'),
        'storeSalesTitle' => 'eBay L30 store sales',
        'showCbidNullBadge' => true,
        'showYSpendBadges' => true,
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
            <div id="ebay-campaign-ads-table"></div>
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
                    Bid comes from Dil vs SBid.
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

@include('campaign.partials.ebay-dil-sbid-rule', [
    'part' => 'modal',
    'account' => 'eBay 1',
    'extended' => true,
    'gpft' => true,
])

@include('campaign.partials.ebay-report-history', ['historyUrl' => url('/ebay/listing-report-history')])

<div class="modal fade" id="ecaYSpendModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-info text-white py-2">
                <h6 class="modal-title mb-0" id="ecaYSpendTitle">Y Spend</h6>
                <div class="d-flex align-items-center gap-2">
                    <select id="eca-y-spend-days" class="form-select form-select-sm" style="width:110px;">
                        <option value="7">7 Days</option>
                        <option value="14">14 Days</option>
                        <option value="30" selected>30 Days</option>
                        <option value="60">60 Days</option>
                        <option value="90">90 Days</option>
                        <option value="0">Lifetime</option>
                    </select>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
            </div>
            <div class="modal-body">
                <div id="ecaYSpendLoading" class="text-center py-4">
                    <div class="spinner-border spinner-border-sm text-primary"></div>
                    <p class="mt-2 text-muted small mb-0">Loading Y Spend history…</p>
                </div>
                <div id="ecaYSpendEmpty" class="text-center text-muted py-4 d-none">No daily Y Spend yet.</div>
                <div id="ecaYSpendBody" class="d-none">
                    <div class="d-flex justify-content-end gap-3 small fw-bold mb-2">
                        <span>High <span id="ecaYSpendHigh" class="text-danger">—</span></span>
                        <span>Med <span id="ecaYSpendMed" class="text-secondary">—</span></span>
                        <span>Low <span id="ecaYSpendLow" class="text-success">—</span></span>
                    </div>
                    <div id="ecaYSpendChart" style="height:340px;"></div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('css')
<link rel="stylesheet" href="https://unpkg.com/tabulator-tables@6.2.1/dist/css/tabulator_bootstrap5.min.css">
<style>
    #ebay-campaign-ads-table .tabulator-row:hover { background: #f0f7ff !important; }
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
    .eca-y-spend-dot {
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        box-shadow: 0 0 0 1px rgba(15, 23, 42, 0.12);
    }
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

    $.get('/ebay/campaign-ads/data', { search, funding_strategy: funding, campaign_status: status, promote_with_ad: promote })
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
                if (typeof renderDilSbidTable === 'function' && document.getElementById('dilSbidRuleModal')?.classList.contains('show')) {
                    renderDilSbidTable();
                }
                applyCbidNullFilter();
                updateSelectedCount();
                syncSelectAllHeader();
                ebayPaintForcePush();
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

function listingHasCampaign(listingId) {
    const row = rowByListingId(listingId);
    return !!(row && campaignIdPresent(row));
}

function rowByListingId(listingId) {
    if (listingId == null || listingId === '' || typeof table === 'undefined' || !table) return null;
    const lid = String(listingId);
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

/** Already in a live campaign — do not treat as "no campaign_id" / enrollable. */
function isInActiveCampaign(row) {
    if (!row) return false;
    const status = campaignStatusOf(row);
    if (status === 'RUNNING' || status === 'PAUSED') return true;
    const bid = parseFloat(row.bid_percentage);
    return campaignIdPresent(row) && isFinite(bid) && bid > 0 && status !== 'ENDED' && status !== 'INACTIVE';
}

/** ENDED / INACTIVE / no campaign can join a RUNNING campaign. RUNNING cannot. */
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
    if (!table) return;
    if (cbidNullFilterOn) {
        table.setFilter(function (data) { return isMissingAd(data); });
    } else {
        table.clearFilter();
    }
    const wrap = document.getElementById('eca-badge-cbidnull-wrap');
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
    loadEbayCampaignAdsStatBadges(@json(route('ebay.campaign.ads.badges')), 'eca');


    table = new Tabulator('#ebay-campaign-ads-table', {
        data: [],
        layout: 'fitDataFill',
        height: 'calc(100vh - 260px)',
        columnDefaults: { hozAlign: 'center', headerHozAlign: 'center' },
        pagination: true,
        paginationSize: 100,
        paginationSizeSelector: [50, 100, 200, 500],
        movableColumns: true,
        placeholder: 'No data — run php artisan ebay:sync-campaign-listings',
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
                headerTooltip: 'Live eBay bid vs S Bid. Green = they match. Yellow = still waiting to push. Red = the last push failed.',
                formatter: function(cell) {
                    const row = cell.getRow().getData();
                    const v = parseFloat(cell.getValue());
                    let valueHtml = '—';
                    if (!isNaN(v)) {
                        const color = v <= 4 ? '#dc3545' : v <= 7 ? '#ffc107' : v <= 13 ? '#198754' : '#e83e8c';
                        valueHtml = '<span style="color:' + color + '; font-weight:600;">' + v.toFixed(1) + '%</span>';
                    }
                    const sync = ebayBidSync(row);
                    if (!sync) return valueHtml;
                    return '<span class="eca-sync-cell"><span class="eca-sync-dot is-' + sync.color + '" title="' + ebayEsc(sync.tip) + '"></span>' + valueHtml + '</span>';
                }
            },
            {
                title: 'Alert',
                field: '_bid_alert',
                width: 56,
                hozAlign: 'center',
                headerSort: false,
                headerTooltip: 'Shown only when the S Bid push failed. A pending gap stays the yellow dot on C Bid. Hover the mark for the eBay error.',
                formatter: function(cell) {
                    const tip = ebayBidAlertText(cell.getRow().getData());
                    if (!tip) return '';
                    return '<span class="eca-push-alert" title="' + ebayEsc(tip) + '" aria-label="' + ebayEsc(tip) + '">!</span>';
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
                title: 'Y Spend', field: 'y_spend', width: 110, hozAlign: 'center',
                sorter: 'number',
                headerTooltip: 'Yesterday ad spend (L1). Promoted rows use that listing\'s ad fees. CPC rows use that campaign\'s CPC fees. Green dot = up vs the last recorded day, red = down, gray = same. Click for history.',
                formatter: function(cell) {
                    const row = cell.getRow().getData();
                    const amount = parseFloat(row.y_spend);
                    const value = isFinite(amount) ? amount : 0;
                    const prevRaw = row.y_spend_prev;
                    const hasPrev = prevRaw !== null && prevRaw !== undefined && prevRaw !== '' && isFinite(parseFloat(prevRaw));
                    const prev = hasPrev ? parseFloat(prevRaw) : null;
                    const prevWhen = row.y_spend_prev_date ? String(row.y_spend_prev_date) : 'the last recorded day';
                    let dotColor = '#6c757d';
                    let dotTip = hasPrev ? ('Same as ' + prevWhen + ' ($' + prev.toFixed(2) + ')') : 'No prior Y Spend yet';
                    if (hasPrev && value > prev) {
                        dotColor = '#28a745';
                        dotTip = 'Up vs ' + prevWhen + ' ($' + prev.toFixed(2) + ')';
                    } else if (hasPrev && value < prev) {
                        dotColor = '#a00211';
                        dotTip = 'Down vs ' + prevWhen + ' ($' + prev.toFixed(2) + ')';
                    }
                    return '<span class="eca-y-spend-hist" style="white-space:nowrap;display:inline-flex;align-items:center;gap:4px;cursor:pointer;" title="' + ebayEsc(dotTip) + ' — click for Y Spend history">'
                        + '<span style="font-weight:600;">$' + value.toFixed(2) + '</span>'
                        + '<span class="eca-y-spend-dot" style="background:' + dotColor + ';"></span></span>';
                },
                cellClick: function(e, cell) {
                    if (!e.target.closest('.eca-y-spend-hist')) return;
                    e.stopPropagation();
                    openEcaYSpendChart(cell.getRow().getData());
                }
            },
            {
                title: 'S Bid', field: 'ebay_l30', width: 110, hozAlign: 'center',
                headerTooltip: 'eBay 1 Dil vs SBid: Dil + Views + CVR + eBay Sold + Std NPFT %, then CVR up / down, then L30 View up / down, then the Min / Max cap. Switch off: S Bid is not changed.',
                sorter: function(a, b, aRow, bRow) {
                    return campaignSbid(aRow.getData()).bid - campaignSbid(bRow.getData()).bid;
                },
                formatter: function(cell) {
                    const res = campaignSbid(cell.getRow().getData());
                    const title = (res && res.title) ? res.title : '';
                    if (res && res.off) {
                        return `<span class="fw-bold" style="color:#842029;" title="${title}">OFF</span>`;
                    }
                    if (!res || res.skip) {
                        return `<span class="text-muted" title="${title || 'No matching Dil slab'}" style="font-size:11px;">—</span>`;
                    }
                    return `<span style="color:${res.color}; font-weight:700;" title="${title}">${res.bid.toFixed(1)}%</span>`;
                }
            },
            ...ebaySbidDiffColumns(),
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
                    if (!v) return '<span class="text-muted">—</span>';
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

    const cbidWrap = document.getElementById('eca-badge-cbidnull-wrap');
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

    loadData();
});

function ebayEsc(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

/** Last failed S Bid push, keyed by listing id. A later match clears it. */
const ebayPushFailed = {};

function ebayRound2(n) {
    return Math.round(Number(n) * 100) / 100;
}

/** Same tenth the C Bid and S Bid cells print. 10.04 and 10.0 both show 10.0%. */
function ebayShownPercent(n) {
    return Math.round(Number(n) * 10) / 10;
}

/** Running promoted listing with Dil vs SBid on and a real S Bid. Otherwise null. */
function ebaySbidResult(row) {
    if (!row || typeof dilSbidEnabled === 'undefined' || !dilSbidEnabled) return null;
    if (String(row.funding_strategy || '') !== 'COST_PER_SALE') return null;
    if (campaignStatusOf(row) !== 'RUNNING') return null;
    if (typeof campaignSbid !== 'function') return null;
    const res = campaignSbid(row);
    if (!res || res.skip || res.off || !(res.bid > 0)) return null;
    return res;
}

/** True when this row is not a push target, or C Bid already matches S Bid. */
function ebayBidMatches(row) {
    const res = ebaySbidResult(row);
    if (!res) return true;
    const live = parseFloat(row.bid_percentage);
    if (!isFinite(live) || live <= 0) return false;
    return ebayShownPercent(live) === ebayShownPercent(res.bid);
}

/**
 * Same colors as Amazon ads: green = match, yellow = waiting to push, red = push failed.
 * Null when the row is outside that rule.
 */
function ebayBidSync(row) {
    const res = ebaySbidResult(row);
    if (!res) return null;
    const id = String((row && row.listing_id) || '');
    const want = Number(res.bid).toFixed(1);
    const live = parseFloat(row.bid_percentage);
    const liveText = (isFinite(live) && live > 0) ? live.toFixed(1) + '%' : 'empty';
    if (ebayBidMatches(row)) {
        if (id) delete ebayPushFailed[id];
        return { color: 'green', reason: 'already_matched', tip: 'Updated — C Bid matches S Bid ' + want + '%' };
    }
    const fail = id ? ebayPushFailed[id] : '';
    if (fail) {
        return {
            color: 'red',
            reason: 'push_failed',
            tip: 'S Bid push failed — C Bid ' + liveText + ', S Bid ' + want + '%. ' + fail
        };
    }
    return {
        color: 'yellow',
        reason: 'sbid_differs',
        tip: 'Pending — S Bid ' + want + '% does not match C Bid ' + liveText
    };
}

/** Red alert only after a failed push. A pending gap stays the yellow dot. */
function ebayBidAlertText(row) {
    const sync = ebayBidSync(row);
    if (!sync || sync.color !== 'red') return '';
    return 'S Bid: ' + sync.tip;
}

/** Checked mismatches when any row is selected; otherwise every loaded mismatch. */
function ebayForcePushTargets() {
    const all = (typeof table !== 'undefined' && table) ? (table.getData() || []) : [];
    if (selectedIds.size > 0) {
        return all.filter(function (r) {
            return r && selectedIds.has(String(r.listing_id)) && !ebayBidMatches(r);
        });
    }
    return all.filter(function (r) { return !ebayBidMatches(r); });
}

let ebayForcePushBusy = false;

function ebayPaintForcePush() {
    const btn = document.getElementById('force-push-btn');
    const countEl = document.getElementById('force-push-count');
    if (!btn || !countEl || ebayForcePushBusy) return;
    const n = ebayForcePushTargets().length;
    const ruleOn = typeof dilSbidEnabled !== 'undefined' && !!dilSbidEnabled;
    countEl.textContent = String(n);
    btn.disabled = !ruleOn || n === 0;
    btn.title = ruleOn
        ? 'Pull live C Bid and push S Bid until they match for ' + n + ' listing(s). Checked rows are used when any row is selected.'
        : 'Dil vs SBid is off. Turn it on to force push.';
}

/**
 * Push S Bid onto eBay for listings whose C Bid does not match.
 * @param {string[]|null} listingIds  null uses ebayForcePushTargets()
 */
function ebayForcePush(listingIds) {
    const ids = Array.from(new Set((listingIds || ebayForcePushTargets().map(function (r) { return r.listing_id; }))
        .map(String)
        .filter(function (id) { return id && id !== 'undefined' && id !== 'null'; })));
    if (!ids.length) {
        alert(selectedIds.size > 0
            ? 'Selected listings already match S Bid.'
            : 'No listings where C Bid differs from S Bid.');
        return;
    }
    if (!confirm('Force push S Bid for ' + ids.length + ' listing(s) where C Bid does not match?')) return;

    const btn = document.getElementById('force-push-btn');
    const btnHtml = btn ? btn.innerHTML : '';
    const chunkSize = 40;
    const chunks = [];
    for (let i = 0; i < ids.length; i += chunkSize) chunks.push(ids.slice(i, i + chunkSize));
    let success = 0, failed = 0, skipped = 0, done = 0;
    const lines = [];
    ebayForcePushBusy = true;
    if (btn) btn.disabled = true;

    function finish() {
        ebayForcePushBusy = false;
        if (btn) btn.innerHTML = btnHtml;
        ebayPaintForcePush();
        let msg = 'Force push finished\nPushed: ' + success + ' | Failed: ' + failed + ' | Skipped: ' + skipped;
        if (lines.length) msg += '\n\n' + lines.slice(0, 40).join('\n');
        alert(msg);
        loadData();
    }

    function next(i) {
        if (i >= chunks.length) {
            finish();
            return;
        }
        const chunk = chunks[i];
        done += chunk.length;
        if (btn) {
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Pushing ' + Math.min(done, ids.length) + '/' + ids.length + '…';
        }
        $.ajax({
            url: '/ebay/campaign-ads/push-selected',
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            contentType: 'application/json',
            data: JSON.stringify({ listing_ids: chunk }),
            timeout: 300000,
            success: function(resp) {
                success += Number(resp && resp.success) || 0;
                failed += Number(resp && resp.failed) || 0;
                skipped += Number(resp && resp.skipped) || 0;
                (resp && resp.results || []).forEach(function (r) {
                    if (!r) return;
                    const id = String(r.listing_id || '');
                    if (r.status === 'pushed') {
                        if (id) delete ebayPushFailed[id];
                        return;
                    }
                    if (r.status === 'failed' && id) {
                        ebayPushFailed[id] = r.reason || 'Push failed';
                        lines.push(id + ' → failed' + (r.reason ? ' (' + r.reason + ')' : ''));
                    }
                });
                next(i + 1);
            },
            error: function(xhr) {
                failed += chunk.length;
                const reason = (xhr.responseJSON && xhr.responseJSON.error) || ('HTTP ' + xhr.status);
                chunk.forEach(function (id) { ebayPushFailed[String(id)] = reason; });
                lines.push('Chunk ' + (i + 1) + ': ' + reason);
                next(i + 1);
            }
        });
    }

    next(0);
}

// ── Checkbox selection ─────────────────────────────
function updateSelectedCount() {
    const count = selectedIds.size;
    const enrollable = selectedEnrollableIds();
    $('#selected-count, #enroll-count').text(count);
    $('#enroll-listing-count').text(enrollable.length);

    if (count > 0) {
        $('#push-selected-btn').removeClass('d-none');
        if (enrollable.length) $('#enroll-selected-btn').removeClass('d-none');
        else                   $('#enroll-selected-btn').addClass('d-none');
    } else {
        $('#push-selected-btn').addClass('d-none');
        $('#enroll-selected-btn').addClass('d-none');
    }
    ebayPaintForcePush();
}

// Load campaigns when enroll modal opens
document.getElementById('enrollModal').addEventListener('show.bs.modal', function() {
    $.get('/ebay/campaign-ads/campaigns', function(data) {
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
        url: '/ebay/campaign-ads/enroll',
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
        url: '/ebay/campaign-ads/push-selected',
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

document.getElementById('force-push-btn').addEventListener('click', function() {
    ebayForcePush(null);
});

// Dil column colours only. S Bid comes from Dil vs SBid.
let currentDilRule = @json($dilRule ?? ['bands' => []]);

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

@include('campaign.partials.ebay-dil-sbid-rule', [
    'part' => 'script',
    'account' => 'eBay 1',
    'extended' => true,
    'gpft' => true,
    'getUrl' => url('/ebay/campaign-ads/dil-sbid-rule'),
    'saveUrl' => url('/ebay/campaign-ads/dil-sbid-rule'),
    'applyUrl' => url('/ebay/campaign-ads/push-selected'),
])

let ecaYSpendRow = null;
let ecaYSpendChart = null;

function openEcaYSpendChart(row) {
    ecaYSpendRow = row || null;
    const sku = (row && (row.resolved_sku || row.sku)) || '';
    const titleEl = document.getElementById('ecaYSpendTitle');
    if (titleEl) titleEl.textContent = 'Y Spend — ' + (sku || (row && row.listing_id) || '');
    const modalEl = document.getElementById('ecaYSpendModal');
    if (modalEl && window.bootstrap && bootstrap.Modal) {
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
    loadEcaYSpendChart();
}

function loadEcaYSpendChart() {
    const row = ecaYSpendRow;
    if (!row) return;
    const daysEl = document.getElementById('eca-y-spend-days');
    const days = daysEl ? daysEl.value : '30';
    const loading = document.getElementById('ecaYSpendLoading');
    const empty = document.getElementById('ecaYSpendEmpty');
    const body = document.getElementById('ecaYSpendBody');
    if (loading) loading.classList.remove('d-none');
    if (empty) empty.classList.add('d-none');
    if (body) body.classList.add('d-none');
    const params = new URLSearchParams({
        days: String(days),
        mode: 'row',
        listing_id: row.listing_id || '',
        campaign_id: row.campaign_id || '',
        funding: row.funding_strategy || '',
        sku: row.resolved_sku || row.sku || ''
    });
    fetch(@json(url('/ebay/y-spend-history')) + '?' + params.toString(), { headers: { Accept: 'application/json' } })
        .then(function(r) { return r.json(); })
        .then(function(rows) { renderEcaYSpendChart(Array.isArray(rows) ? rows : []); })
        .catch(function() { renderEcaYSpendChart([]); });
}

function renderEcaYSpendChart(rows) {
    const loading = document.getElementById('ecaYSpendLoading');
    const empty = document.getElementById('ecaYSpendEmpty');
    const body = document.getElementById('ecaYSpendBody');
    if (loading) loading.classList.add('d-none');
    const points = (rows || []).filter(function(d) {
        return d && d.date && isFinite(parseFloat(d.y_spend));
    });
    if (!points.length || typeof Highcharts === 'undefined') {
        if (body) body.classList.add('d-none');
        if (empty) empty.classList.remove('d-none');
        return;
    }
    if (empty) empty.classList.add('d-none');
    if (body) body.classList.remove('d-none');
    const values = points.map(function(d) { return parseFloat(d.y_spend); });
    const sorted = values.slice().sort(function(a, b) { return a - b; });
    const mid = Math.floor(sorted.length / 2);
    const median = sorted.length % 2 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
    const money = function(n) { return '$' + Number(n).toFixed(2); };
    const highEl = document.getElementById('ecaYSpendHigh');
    const medEl = document.getElementById('ecaYSpendMed');
    const lowEl = document.getElementById('ecaYSpendLow');
    if (highEl) highEl.textContent = money(Math.max.apply(null, values));
    if (medEl) medEl.textContent = money(median);
    if (lowEl) lowEl.textContent = money(Math.min.apply(null, values));
    if (ecaYSpendChart) {
        ecaYSpendChart.destroy();
        ecaYSpendChart = null;
    }
    ecaYSpendChart = Highcharts.chart('ecaYSpendChart', {
        chart: { type: 'line', height: 340 },
        title: { text: null },
        credits: { enabled: false },
        legend: { enabled: false },
        xAxis: {
            categories: points.map(function(d) { return d.date_formatted || d.date; }),
            labels: { rotation: -90, style: { fontSize: '10px', fontWeight: '600' } }
        },
        yAxis: {
            title: { text: null },
            labels: { formatter: function() { return '$' + Highcharts.numberFormat(this.value, 2); } }
        },
        tooltip: { pointFormatter: function() { return '<b>$' + Highcharts.numberFormat(this.y, 2) + '</b>'; } },
        plotOptions: { series: { marker: { radius: 4, lineWidth: 1, lineColor: '#fff' } } },
        series: [{
            name: 'Y Spend',
            color: '#64748b',
            data: values.map(function(v, i) {
                let color = '#6c757d';
                if (i > 0 && v > values[i - 1]) color = '#28a745';
                else if (i > 0 && v < values[i - 1]) color = '#a00211';
                return { y: v, color: color };
            })
        }]
    });
}

document.getElementById('eca-y-spend-days')?.addEventListener('change', loadEcaYSpendChart);
</script>
@endsection
