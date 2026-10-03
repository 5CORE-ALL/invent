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
                    title="Push S Bid where C Bid does not match. A pending gap is the yellow dot. The red alert is a failed push.">
                <i class="fas fa-exclamation-circle me-1"></i>Force Push (<span id="force-push-count">0</span>)
            </button>
            <button class="btn btn-sm btn-info text-white d-none" id="enroll-selected-btn" data-bs-toggle="modal" data-bs-target="#enrollModal">
                <i class="fas fa-plus-circle me-1"></i>Enroll in Campaign (<span id="enroll-count">0</span>)
            </button>
            <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#dilRuleModal">
                <i class="fas fa-tint me-1"></i>Dil Rule
            </button>
            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#dilSbidRuleModal"
                    title="Set S Bid from Dil. This account keeps its own slabs.">
                <i class="fas fa-percent me-1"></i>Dil vs SBid
            </button>
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
                    Bid comes from SCVR + the current SBID rule.
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

{{-- Dilution Rule Modal --}}
<div class="modal fade" id="dilRuleModal" tabindex="-1" aria-labelledby="dilRuleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="dilRuleModalLabel">
                    <i class="fas fa-tint me-2 text-danger"></i>eBay Dilution Rule — DIL % → Color
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

@include('campaign.partials.ebay-dil-sbid-rule', [
    'part' => 'modal',
    'account' => 'eBay 1',
])

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
                headerTooltip: 'Shown when C Bid does not match S Bid. Hover the mark for the reason. A failed push includes the eBay error.',
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
                title: 'S Bid', field: 'ebay_l30', width: 110, hozAlign: 'center',
                headerTooltip: 'Dil vs SBid switch on: Dil slabs, then the CVR overlay. Switch off: S Bid is not changed.',
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

/** Red alert when C Bid does not match S Bid, including a failed push. */
function ebayBidAlertText(row) {
    const sync = ebayBidSync(row);
    if (!sync || sync.color === 'green') return '';
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
        ? 'Push S Bid for ' + n + ' listing(s) still pending or failed. Checked rows are used when any row is selected.'
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
            timeout: 180000,
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

// ── SBID Rule helper — used by S Bid column ────────
// Walk bands top-to-bottom (ascending scvr_max) and return the first match's bid.
// CVR = 0 is a valid value and falls into the lowest band (e.g. ≤ 4 → 10.1%) — no skip.
// `row` (optional) carries metric values so a matched band can resolve a dynamic sub-rule.
function getBidFromRule(scvr, row) {
    const s = parseFloat(scvr);
    const safeScvr = (!isFinite(s) || s < 0) ? 0 : s;
    const bands = currentRule.bands || [];
    const ctx = {
        scvr:       safeScvr,
        ebay_price: parseFloat(row && row.metric_price) || 0,
        ebay_l30:   parseFloat(row && row.ebay_l30)     || 0,
        views:      parseFloat(row && row.views)        || 0,
        es_bid:     parseFloat(row && row.suggested_bid) || 0,
    };
    // First band whose [scvr_min, scvr_max] range contains the SCVR wins.
    for (let i = 0; i < bands.length; i++) {
        const min = parseFloat(bands[i].scvr_min);
        const max = parseFloat(bands[i].scvr_max);
        const lo = isFinite(min) ? min : 0;
        const hi = isFinite(max) ? max : 9999;
        if (safeScvr >= lo && safeScvr <= hi) {
            return resolveBandBid(bands[i], ctx);
        }
    }
    // fallback: last band
    const last = bands[bands.length - 1] || { bid: 2.1, color: '#e83e8c' };
    return resolveBandBid(last, ctx);
}

// Resolve a band's bid.
function resolveBandBid(band, ctx) {
    // Band flagged to use the row's ES Bid (raw eBay suggested_bid).
    if (band.use_es_bid) {
        return esBidResult(parseFloat(ctx.es_bid));
    }
    return { bid: parseFloat(band.bid), color: band.color || '#333', skip: false };
}

// ── SBID Rule (editor removed; the S Bid column still uses this rule) ──
let currentRule = @json($sbidRule ?? ['bands' => []]);
// Normalize bands (ensure scvr_min/scvr_max) so the S Bid column resolves correctly.
if (currentRule && typeof currentRule === 'object') {
    currentRule.bands = normalizeSbidBands(currentRule.bands || []);
}

// Default dynamic CVR bands (editable Min/Max). 0% band uses each row's ES Bid.
function defaultSbidBands() {
    return [
        { scvr_min: 0,     scvr_max: 0,    use_es_bid: true, bid: 0 },
        { scvr_min: 0.01,  scvr_max: 3,    bid: 10.1 },
        { scvr_min: 3.01,  scvr_max: 7,    bid: 8.1 },
        { scvr_min: 7.01,  scvr_max: 13,   bid: 5.1 },
        { scvr_min: 13.01, scvr_max: 9999, bid: 5.1 },
    ];
}

// Ensure every band has explicit scvr_min / scvr_max. Legacy bands with only
// scvr_max get a min derived from the previous band's max (+0.01).
function normalizeSbidBands(bands) {
    let arr = Array.isArray(bands) ? bands.slice() : [];
    if (!arr.length) return defaultSbidBands();
    let prevMax = null;
    arr.forEach(function(b, i) {
        if (b.scvr_max == null || b.scvr_max === '') b.scvr_max = 9999;
        if (b.scvr_min == null || b.scvr_min === '') {
            b.scvr_min = (i === 0 || prevMax == null)
                ? 0
                : +(parseFloat(prevMax) + 0.01).toFixed(2);
        }
        if (parseFloat(b.scvr_min) === 0 && parseFloat(b.scvr_max) === 0) b.use_es_bid = true;
        prevMax = parseFloat(b.scvr_max);
    });
    return arr;
}

// ── Dilution Rule ───────────────────────────────────
// DIL = (L30 sold / Inventory) × 100. Bands evaluated top-to-bottom, first DIL ≤ max wins.
const dilGetUrl  = '/ebay/campaign-ads/dil-rule';
const dilSaveUrl = '/ebay/campaign-ads/dil-rule';
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

// S Bid for the column:
//   Step 1 — if L30 sold ≤ l30_sold_es_bid_max → ES Bid (suggested_bid).
//   Step 2 — if row's `l7_views` < `l7_views_threshold` → fall back to ES Bid (suggested_bid).
//   Step 3 — else evaluate SCVR bands (the existing SBID Rule modal) top-to-bottom.
function shouldUseEsBid(sold, l7, rule) {
    const l30Max = parseFloat(rule && rule.l30_sold_es_bid_max);
    const l7Thr = parseFloat(rule && rule.l7_views_threshold);
    const l30Limit = isFinite(l30Max) ? l30Max : 0;
    const l7Limit = isFinite(l7Thr) ? l7Thr : 70;
    return sold <= l30Limit || l7 < l7Limit;
}

function esBidResult(esBidRaw) {
    if (!isFinite(esBidRaw) || esBidRaw <= 0) {
        return { bid: 0, color: '#6c757d', skip: true };
    }
    return { bid: esBidRaw, color: '#0dcaf0', skip: false };
}


function getCombinedSbid() {
    return { bid: 0, color: '#6c757d', skip: true, title: 'Sbid Rule removed' };
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
@include('campaign.partials.ebay-dil-sbid-rule', [
    'part' => 'script',
    'account' => 'eBay 1',
    'getUrl' => url('/ebay/campaign-ads/dil-sbid-rule'),
    'saveUrl' => url('/ebay/campaign-ads/dil-sbid-rule'),
    'applyUrl' => url('/ebay/campaign-ads/push-selected'),
])
</script>
@endsection
