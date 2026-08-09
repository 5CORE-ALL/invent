@extends('layouts.vertical', ['title' => 'Missing Google Shopping Ads', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .gs-ads-missing .tabulator .tabulator-header .tabulator-col {
            font-size: 0.8rem;
        }
        .gs-ads-missing .tabulator-row .tabulator-cell {
            font-size: 0.82rem;
        }
        .gs-ads-missing .parent-row {
            background-color: #fffef2;
        }
        .gs-ads-missing .parent-copy-btn {
            cursor: pointer;
            color: #868e96;
            margin-left: 6px;
        }
        .gs-ads-missing .parent-copy-btn:hover {
            color: #1971c2;
        }
        .gs-ads-missing .gs-missing-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            border-radius: 6px;
            padding: 0.3rem 0.6rem;
            font-size: 0.8rem;
            font-weight: 600;
            line-height: 1.2;
            white-space: nowrap;
            color: #fff;
            cursor: pointer;
        }
        .gs-ads-missing .gs-missing-badge--parent {
            background-color: #1971c2;
        }
        .gs-ads-missing .gs-missing-badge--missing {
            background-color: #868e96;
        }
        .gs-ads-missing .gs-missing-badge--missing.is-alert {
            background-color: #dc2626;
        }
        .gs-ads-missing .gs-missing-badge--inv {
            background-color: #2f9e44;
        }
        .gs-badge-panel {
            position: absolute;
            z-index: 2000;
            width: 320px;
            max-height: 320px;
            background: #fff;
            border: 1px solid #ced4da;
            border-radius: 6px;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.18);
            padding: 8px 10px;
            display: flex;
            flex-direction: column;
        }
        .gs-badge-panel.d-none {
            display: none !important;
        }
        .gs-badge-panel .gs-badge-panel-title {
            font-weight: 700;
            font-size: 0.85rem;
            margin-bottom: 6px;
            border-bottom: 1px solid #e9ecef;
            padding-bottom: 4px;
        }
        .gs-badge-panel .gs-badge-panel-list {
            overflow-y: auto;
        }
        .gs-badge-panel .gs-badge-panel-item {
            font-size: 0.78rem;
            padding: 2px 2px;
            border-bottom: 1px dashed #f1f3f5;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .gs-badge-panel .gs-badge-panel-empty {
            color: #868e96;
            font-size: 0.78rem;
        }
        .gs-ads-missing .link-chip {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: #e7f5ff;
            color: #1971c2;
            border: 1px solid #a5d8ff;
            border-radius: 10px;
            padding: 1px 7px;
            margin: 1px 2px;
            font-size: 0.72rem;
            white-space: nowrap;
        }
        .gs-ads-missing .campaign-dot {
            display: inline-block;
            width: 9px;
            height: 9px;
            border-radius: 50%;
            border: 1px solid rgba(0, 0, 0, 0.15);
            flex: 0 0 auto;
        }
        .gs-ads-missing .campaign-dot-green {
            background-color: #16a34a;
        }
        .gs-ads-missing .campaign-dot-red {
            background-color: #dc2626;
        }
        .gs-ads-missing .link-chip .chip-x {
            cursor: pointer;
            color: #868e96;
            margin-left: 2px;
        }
        .gs-ads-missing .link-chip .chip-x:hover {
            color: #495057;
        }
        .gs-ads-missing .link-chip .chip-trash {
            cursor: pointer;
            color: #e03131;
            margin-left: 2px;
        }
        .gs-ads-missing .link-chip .chip-trash:hover {
            color: #c92a2a;
        }
        .gs-ads-missing .link-add-btn {
            border: 1px solid #adb5bd;
            background: #fff;
            border-radius: 6px;
            padding: 0 6px;
            line-height: 1.4;
            cursor: pointer;
            color: #2f9e44;
        }
        .gs-ads-missing .link-add-btn:hover { background: #f1f3f5; }
        .gs-campaign-picker {
            position: absolute;
            z-index: 2000;
            width: 300px;
            max-height: 280px;
            background: #fff;
            border: 1px solid #ced4da;
            border-radius: 6px;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.18);
            padding: 6px;
            display: flex;
            flex-direction: column;
        }
        .gs-campaign-picker.d-none { display: none !important; }
        .gs-campaign-picker .gs-picker-list { overflow-y: auto; margin-top: 6px; }
        .gs-campaign-picker .gs-picker-option {
            padding: 4px 6px;
            font-size: 0.78rem;
            cursor: pointer;
            border-radius: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .gs-campaign-picker .gs-picker-option:hover { background: #e7f5ff; }
        .gs-campaign-picker .gs-picker-empty {
            padding: 6px;
            color: #868e96;
            font-size: 0.78rem;
        }
        /* Price slab colors (shared by Price cells + Price Range filter) */
        .gs-ads-missing .gs-price-pill {
            display: inline-block;
            min-width: 2.5rem;
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: 700;
            line-height: 1.3;
        }
        .gs-ads-missing .gs-price-filter {
            font-size: 0.8rem;
            font-weight: 600;
            border: 1px solid #ced4da;
            border-radius: 4px;
            padding: 4px 8px;
            min-width: 120px;
        }
        .gs-ads-missing .gs-toolbar-filter {
            font-size: 0.8rem;
            border: 1px solid #ced4da;
            border-radius: 4px;
            padding: 4px 8px;
            min-width: 110px;
            background: #fff;
        }
        .gs-ads-missing #gsParentSearch {
            min-width: 160px;
        }
        /* KW(-) — same red header styling as /purchase-master/ads-link */
        .gs-ads-missing .tabulator-header .tabulator-col.gs-kw-neg-col {
            background-color: #dc2626 !important;
            border-right-color: #b91c1c !important;
        }
        .gs-ads-missing .tabulator-header .tabulator-col.gs-kw-neg-col .tabulator-col-content,
        .gs-ads-missing .tabulator-header .tabulator-col.gs-kw-neg-col .tabulator-col-title {
            color: #fff !important;
            font-weight: 700;
        }
        .gs-ads-missing .tabulator-header .tabulator-col.gs-kw-neg-col .tabulator-col-sorter {
            display: none !important;
        }
        .gs-ads-missing .gs-kw-neg-value {
            color: #a00211;
            font-weight: 700;
        }
        .gs-ads-missing .gs-create-btn {
            border: 1px solid #2f9e44;
            background: #fff;
            color: #2f9e44;
            border-radius: 6px;
            padding: 0 8px;
            line-height: 1.4;
            cursor: pointer;
            font-weight: 700;
        }
        .gs-ads-missing .gs-create-btn:hover {
            background: #ebfbee;
        }
        .gs-ads-missing .gs-create-btn:disabled {
            opacity: 0.55;
            cursor: not-allowed;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared/page-title', ['sub_title' => 'Google Ads', 'page_title' => 'Missing Google Shopping Ads'])

    <div class="row gs-ads-missing">
        <div class="col-12">
            <div class="card">
                <div class="card-body p-2">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <div class="gs-missing-badge gs-missing-badge--parent" id="gsParentWrap" title="Rows: number of rows in the current filtered view.">
                            <span class="gs-missing-badge-label">rows</span>
                            <span class="gs-missing-badge-value tabular-nums" id="gsParentValue">0</span>
                        </div>
                        <div class="gs-missing-badge gs-missing-badge--inv" id="gsInvWrap" title="Inv&gt;0: rows in the current filtered view with inventory greater than 0.">
                            <span class="gs-missing-badge-label">Inv&gt;0</span>
                            <span class="gs-missing-badge-value tabular-nums" id="gsInvValue">0</span>
                        </div>
                        <div class="gs-missing-badge gs-missing-badge--missing" id="gsMissingWrap" title="Missing: in-stock parents (Inv &gt; 0) in the current view with no linked Google Shopping campaign.">
                            <span class="gs-missing-badge-label">Missing</span>
                            <span class="gs-missing-badge-value tabular-nums" id="gsMissingValue">0</span>
                        </div>

                        <input type="text" id="gsParentSearch" class="gs-toolbar-filter" placeholder="Search Parent...">
                        <select id="gsInvFilter" class="gs-toolbar-filter" title="Inventory filter">
                            <option value="">All INV</option>
                            <option value="in">In Stock</option>
                            <option value="zero">Zero Inv</option>
                        </select>
                        <select id="gsPriceFilter" class="gs-price-filter" title="Price Range"></select>
                        <select id="gsCampaignFilter" class="gs-toolbar-filter" title="Campaign filter">
                            <option value="">All Campaigns</option>
                            <option value="missing">Missing</option>
                            <option value="linked">Linked</option>
                        </select>

                        <button type="button" class="btn btn-success btn-sm ms-auto" id="gsMissingExportBtn" title="Export the current (filtered) view to CSV">
                            <i class="fa fa-download me-1"></i> Export
                        </button>
                    </div>
                    <div id="gsAdsMissingTable"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('modal')
    {{-- Create Google Shopping campaign modal (must live in modal section so Bootstrap can show it) --}}
    <div class="modal fade" id="gsCreateCampaignModal" tabindex="-1" aria-labelledby="gsCreateCampaignModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="gsCreateCampaignModalLabel">Create Google Shopping Ad</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2 small mb-3">
                        Creates <strong>one PAUSED</strong> Shopping campaign for the parent
                        (e.g. <code>PARENT DT AH</code>). Selected child SKUs are included as
                        Merchant Center Item IDs under that campaign; Everything else is excluded.
                    </div>
                    <form id="gsCreateCampaignForm">
                        <input type="hidden" id="gsCreateParent" name="parent">
                        <div class="row g-2 mb-2">
                            <div class="col-md-4">
                                <label class="form-label small mb-1">Parent</label>
                                <input type="text" class="form-control form-control-sm" id="gsCreateParentDisplay" readonly>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label small mb-1">Campaign name</label>
                                <input type="text" class="form-control form-control-sm" id="gsCreateCampaignName" name="campaign_name" required placeholder="PARENT DT AH">
                            </div>
                            <div class="col-12">
                                <label class="form-label small mb-1">Buyer link (Shopify B2C)</label>
                                <div class="input-group input-group-sm">
                                    <input type="url" class="form-control" id="gsCreateBuyerLink" name="buyer_link" placeholder="https://...">
                                    <a class="btn btn-outline-secondary" id="gsCreateBuyerLinkOpen" href="#" target="_blank" title="Open buyer link">Open</a>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-1">Daily budget ($)</label>
                                <input type="number" class="form-control form-control-sm" id="gsCreateBudget" name="budget_amount" min="1" step="0.01" value="1">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-1">CPC bid / SBID ($)</label>
                                <input type="number" class="form-control form-control-sm" id="gsCreateCpcBid" name="cpc_bid" min="0.01" step="0.01" value="0.50">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small mb-1">Priority (0–2)</label>
                                <input type="number" class="form-control form-control-sm" id="gsCreatePriority" name="campaign_priority" min="0" max="2" value="0">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small mb-1">Merchant Center ID</label>
                                <input type="number" class="form-control form-control-sm" id="gsCreateMerchantId" name="merchant_id" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small mb-1">Feed label</label>
                                <input type="text" class="form-control form-control-sm" id="gsCreateFeedLabel" name="feed_label" value="US">
                            </div>
                        </div>

                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <label class="form-label small mb-0 fw-semibold">
                                Child SKUs in this campaign
                                <span class="text-muted fw-normal" id="gsCreateChildCount"></span>
                            </label>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-secondary" id="gsCreateSelectAll">Select all</button>
                                <button type="button" class="btn btn-outline-secondary" id="gsCreateSelectNone">Select none</button>
                            </div>
                        </div>
                        <div class="table-responsive border rounded" style="max-height: 320px;">
                            <table class="table table-sm table-hover mb-0 align-middle" id="gsCreateChildrenTable">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th style="width:36px;"></th>
                                        <th style="min-width:140px;">Target SKU</th>
                                        <th style="min-width:280px;">Item ID (Merchant Center)</th>
                                        <th style="width:60px;">Inv</th>
                                    </tr>
                                </thead>
                                <tbody id="gsCreateChildrenBody"></tbody>
                            </table>
                        </div>
                        <input type="hidden" id="gsCreateTargetSku" value="">
                        <input type="hidden" id="gsCreateItemId" value="">

                        <div class="mt-2">
                            <a href="#" id="gsCreateAiNegLink" class="small fw-semibold">
                                <i class="fa fa-magic me-1"></i> Generate AI negative keywords for this product
                            </a>
                        </div>
                    </form>
                    <div class="text-danger small mt-2 d-none" id="gsCreateError"></div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success btn-sm" id="gsCreateSubmitBtn">
                        <i class="fa fa-plus me-1"></i> Create campaign
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- AI negative keywords modal --}}
    <div class="modal fade" id="gsAiNegModal" tabindex="-1" aria-labelledby="gsAiNegModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="gsAiNegModalLabel">AI Negative Keywords</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-2" id="gsAiNegSubtitle">Generating suggestions for this product…</p>
                    <div id="gsAiNegLoading" class="text-center py-4 d-none">
                        <i class="fa fa-spinner fa-spin fa-2x text-primary"></i>
                        <div class="small text-muted mt-2">Asking AI for negative keywords…</div>
                    </div>
                    <div id="gsAiNegError" class="alert alert-danger py-2 small d-none"></div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold mb-1" for="gsAiNegIdeas">Your ideas (optional)</label>
                        <textarea class="form-control form-control-sm" id="gsAiNegIdeas" rows="2"
                            placeholder="e.g. block karaoke, wedding DJ, DJ controller, karaoke mic…"></textarea>
                        <div class="form-text small">Add themes or sample negatives; AI will expand them into more keywords.</div>
                    </div>
                    <div id="gsAiNegExistingWrap" class="mb-3 d-none">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <div class="fw-semibold small">Already on Amz KW(-) for this parent</div>
                            <span class="badge text-bg-secondary" id="gsAiNegExistingCount">0</span>
                        </div>
                        <div id="gsAiNegExisting" class="border rounded p-2 small bg-light" style="max-height:120px;overflow:auto;"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold mb-1" for="gsAiNegManualInput">Add manual negative keyword</label>
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control" id="gsAiNegManualInput"
                                placeholder="Type a keyword and press Add (or Enter)">
                            <button type="button" class="btn btn-outline-success" id="gsAiNegManualAddBtn" title="Add to list">
                                <i class="fa fa-plus me-1"></i> Add
                            </button>
                        </div>
                        <div class="form-text small">Manual keywords are included when you Push / Export.</div>
                    </div>
                    <div id="gsAiNegSuggestedWrap" class="d-none">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <div class="fw-semibold small">
                                Negatives to push
                                <span class="badge text-bg-danger ms-1" id="gsAiNegSuggestedCount">0</span>
                            </div>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-secondary" id="gsAiNegCopyBtn" title="Copy all">
                                    <i class="fa fa-copy me-1"></i> Copy
                                </button>
                                <button type="button" class="btn btn-outline-secondary" id="gsAiNegExportBtn" title="Export keywords CSV">
                                    <i class="fa fa-file-csv me-1"></i> Export CSV
                                </button>
                            </div>
                        </div>
                        <ul id="gsAiNegSuggested" class="list-group list-group-flush border rounded" style="max-height:280px;overflow:auto;"></ul>
                    </div>
                    <div class="border rounded p-2 mt-3 bg-light" id="gsAiNegPushWrap">
                        <div class="fw-semibold small mb-2">Push to Google Ads campaign</div>
                        <div class="row g-2 align-items-end">
                            <div class="col-md-5">
                                <label class="form-label small mb-1" for="gsAiNegMatchType">Match type</label>
                                <select class="form-select form-select-sm" id="gsAiNegMatchType">
                                    <option value="PHRASE" selected>Phrase</option>
                                    <option value="BROAD">Broad</option>
                                    <option value="EXACT">Exact</option>
                                </select>
                            </div>
                            <div class="col-md-7">
                                <div class="form-check mt-3">
                                    <input class="form-check-input" type="checkbox" id="gsAiNegIncludeAmazon" checked>
                                    <label class="form-check-label small" for="gsAiNegIncludeAmazon">
                                        Also push Amz KW(-) negatives for this parent
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="form-text small mt-1">
                            Pushes to the Shopping campaign for this parent (create/link it first).
                        </div>
                        <div class="text-success small mt-2 d-none" id="gsAiNegPushOk"></div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="gsAiNegAddMoreBtn" title="Generate more using your ideas">
                        <i class="fa fa-plus me-1"></i> Add more from ideas
                    </button>
                    <button type="button" class="btn btn-primary btn-sm" id="gsAiNegRegenBtn">
                        <i class="fa fa-sync me-1"></i> Regenerate
                    </button>
                    <button type="button" class="btn btn-success btn-sm" id="gsAiNegPushBtn" title="Push negatives to Google Ads">
                        <i class="fa fa-cloud-upload-alt me-1"></i> Push Negative Keywords
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-after-vite')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        (function () {
            var missingDataUrl = @json(route('google.shopping.ads.missing.data'));
            var campaignsUrl = @json(route('google.shopping.ads.missing.campaigns'));
            var linkUrl = @json(route('google.shopping.ads.missing.link'));
            var unlinkUrl = @json(route('google.shopping.ads.missing.unlink'));
            var deleteUrl = @json(route('google.shopping.ads.missing.delete'));
            var createUrl = @json(route('google.shopping.ads.missing.create'));
            var aiNegUrl = @json(route('google.shopping.ads.missing.ai-negatives'));
            var pushNegUrl = @json(route('google.shopping.ads.missing.push-negatives'));
            var csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
            var lastCreatedCampaignId = '';

            function esc(str) {
                return String(str == null ? '' : str)
                    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            }

            function onlyParentsFilter(data) {
                return data.is_parent === true;
            }

            function linkCountSorter(a, b) {
                var la = Array.isArray(a) ? a.length : 0;
                var lb = Array.isArray(b) ? b.length : 0;
                return la - lb;
            }

            function inventoryHeaderFilter(headerValue, rowValue) {
                var inv = Number(rowValue) || 0;
                if (headerValue === 'in') { return inv > 0; }
                if (headerValue === 'zero') { return inv <= 0; }
                return true;
            }

            /** Slab colors shared by Price cells + Price Range filter (highest → lowest). */
            var PRICE_SLABS = [
                { key: '', label: 'Price Range', bg: '#f1f3f5', fg: '#212529' },
                { key: 'gt100', label: '>100', bg: '#e0cffc', fg: '#3d0a91' },
                { key: '50-100', label: '50 to 100', bg: '#f7d6e6', fg: '#a4133c' },
                { key: '30-50', label: '30 to 50', bg: '#d1e7dd', fg: '#0f5132' },
                { key: '20-30', label: '20 to 30', bg: '#fff3cd', fg: '#664d03' },
                { key: '10-20', label: '10 to 20', bg: '#cfe2ff', fg: '#084298' },
                { key: '0-10', label: '0 to 10', bg: '#f8d7da', fg: '#842029' }
            ];

            function priceSlabForAmount(rawPrice) {
                var price = Math.round(parseFloat(rawPrice) || 0);
                if (!isFinite(price) || price < 0) { price = 0; }
                if (price > 100) { return priceSlabByKey('gt100'); }
                if (price > 50) { return priceSlabByKey('50-100'); }
                if (price > 30) { return priceSlabByKey('30-50'); }
                if (price > 20) { return priceSlabByKey('20-30'); }
                if (price > 10) { return priceSlabByKey('10-20'); }
                return priceSlabByKey('0-10');
            }

            function priceSlabByKey(key) {
                for (var i = 0; i < PRICE_SLABS.length; i++) {
                    if (PRICE_SLABS[i].key === key) { return PRICE_SLABS[i]; }
                }
                return PRICE_SLABS[0];
            }

            function applyPriceFilterSelectColors(select) {
                if (!select) { return; }
                var slab = priceSlabByKey(select.value || '');
                select.style.backgroundColor = slab.bg;
                select.style.color = slab.fg;
                Array.prototype.forEach.call(select.options || [], function (opt) {
                    var s = priceSlabByKey(opt.value || '');
                    opt.style.backgroundColor = s.bg;
                    opt.style.color = s.fg;
                });
            }

            function initPriceRangeToolbarSelect() {
                var select = document.getElementById('gsPriceFilter');
                if (!select) { return; }
                select.innerHTML = '';
                PRICE_SLABS.forEach(function (slab) {
                    var opt = document.createElement('option');
                    opt.value = slab.key;
                    opt.textContent = slab.label;
                    opt.style.backgroundColor = slab.bg;
                    opt.style.color = slab.fg;
                    select.appendChild(opt);
                });
                applyPriceFilterSelectColors(select);
                select.addEventListener('change', function () {
                    applyPriceFilterSelectColors(select);
                });
            }

            /** Price Range filter on Price column (uses rounded display dollars). */
            function priceRangeHeaderFilter(headerValue, rowValue) {
                if (!headerValue) { return true; }
                var raw = parseFloat(rowValue);
                var price = isFinite(raw) ? Math.round(raw) : 0;
                if (headerValue === '0-10') { return price >= 0 && price <= 10; }
                if (headerValue === '10-20') { return price > 10 && price <= 20; }
                if (headerValue === '20-30') { return price > 20 && price <= 30; }
                if (headerValue === '30-50') { return price > 30 && price <= 50; }
                if (headerValue === '50-100') { return price > 50 && price <= 100; }
                if (headerValue === 'gt100') { return price > 100; }
                return true;
            }

            function missingHeaderFilter(headerValue, rowValue) {
                var len = Array.isArray(rowValue) ? rowValue.length : 0;
                if (headerValue === 'missing') { return len === 0; }
                if (headerValue === 'linked') { return len > 0; }
                return true;
            }

            function linkNamesAccessor(value) {
                if (!Array.isArray(value)) { return ''; }
                return value.map(function (c) { return c && c.campaign_name ? c.campaign_name : ''; })
                    .filter(function (n) { return n !== ''; })
                    .join(', ');
            }

            function statusDot(c) {
                var dot = c && c.dot;
                if (dot !== 'green' && dot !== 'red') { return ''; }
                var status = c.status || (dot === 'green' ? 'ENABLED' : 'PAUSED');
                var title = status ? (status.charAt(0) + status.slice(1).toLowerCase()) : (dot === 'green' ? 'Enabled' : 'Paused');
                return '<span class="campaign-dot campaign-dot-' + dot + '" title="' + esc(title) + '"></span>';
            }

            function postJson(url, payload) {
                return fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(payload)
                }).then(function (res) {
                    return res.json().then(function (body) { return { ok: res.ok, body: body }; });
                });
            }

            function campaignsFormatter(cell) {
                var d = cell.getData();
                var list = cell.getValue() || [];
                var chips = (Array.isArray(list) ? list : []).map(function (c) {
                    var canUnlink = c && c.source === 'manual' && c.id;
                    var canDelete = c && (c.campaign_id || c.campaign_name);
                    return '<span class="link-chip" title="' + esc(c.campaign_name) + (c.source === 'manual' ? ' (manual)' : ' (auto)') + '">'
                        + statusDot(c)
                        + esc(c.campaign_name)
                        + (canUnlink
                            ? ' <i class="fa fa-times chip-x" title="Unlink only (keep in Google Ads)" data-id="' + c.id + '" data-sku="' + esc(d.sku) + '"></i>'
                            : '')
                        + (canDelete
                            ? ' <i class="fa fa-trash chip-trash" title="Delete campaign in Google Ads"'
                                + ' data-sku="' + esc(d.sku) + '"'
                                + ' data-id="' + esc(c.id || '') + '"'
                                + ' data-campaign-id="' + esc(c.campaign_id || '') + '"'
                                + ' data-campaign-name="' + esc(c.campaign_name || '') + '"></i>'
                            : '')
                        + '</span>';
                }).join('');
                return chips
                    + '<button type="button" class="link-add-btn" data-sku="' + esc(d.sku) + '" title="Link a campaign">'
                    + '<i class="fa fa-plus"></i></button>';
            }

            var campaignNames = [];
            var campaignsLoadPromise = null;

            function ensureCampaignNames() {
                if (campaignsLoadPromise) { return campaignsLoadPromise; }
                campaignsLoadPromise = fetch(campaignsUrl, { headers: { 'Accept': 'application/json' } })
                    .then(function (res) { return res.json(); })
                    .then(function (json) {
                        campaignNames = (json && Array.isArray(json.data))
                            ? json.data.map(function (c) { return c.campaign_name; })
                            : [];
                        return campaignNames;
                    })
                    .catch(function () {
                        campaignNames = [];
                        return campaignNames;
                    });
                return campaignsLoadPromise;
            }

            function buildTable() {
                var table = new Tabulator('#gsAdsMissingTable', {
                    ajaxURL: missingDataUrl,
                    ajaxResponse: function (url, params, response) {
                        return (response && Array.isArray(response.data)) ? response.data : (response || []);
                    },
                    index: 'sku',
                    layout: 'fitColumns',
                    height: 'calc(100vh - 220px)',
                    pagination: true,
                    paginationSize: 100,
                    paginationSizeSelector: [25, 50, 100, 200, 500],
                    paginationCounter: 'rows',
                    initialSort: [{ column: 'price', dir: 'desc' }],
                    rowFormatter: function (row) {
                        if (row.getData().is_parent) {
                            row.getElement().classList.add('parent-row');
                        }
                    },
                    columns: [
                        {
                            title: 'Parent', field: 'parent',
                            cssClass: 'text-primary', widthGrow: 1, tooltip: true,
                            hozAlign: 'center', headerHozAlign: 'center',
                            formatter: function (cell) {
                                var v = cell.getValue() || '';
                                return '<span class="parent-name">' + esc(v) + '</span>'
                                    + ' <i class="fa fa-copy parent-copy-btn" role="button" tabindex="0" title="Copy parent name" data-parent="' + esc(v) + '"></i>';
                            }
                        },
                        {
                            title: 'Inv', field: 'inventory', width: 110,
                            hozAlign: 'right', headerHozAlign: 'right',
                            headerSort: true, sorter: 'number',
                            formatter: function (cell) {
                                var v = cell.getValue();
                                return (v == null || v === '') ? '' : Number(v).toLocaleString('en-US');
                            }
                        },
                        {
                            title: 'Price', field: 'price', width: 90,
                            hozAlign: 'center', headerHozAlign: 'center',
                            headerSort: true, sorter: 'number',
                            // Same Shopify price as /shopify-b2c-pricing (shopify_skus.price)
                            formatter: function (cell) {
                                var value = parseFloat(cell.getValue() || 0);
                                var rounded = (!isFinite(value) || value <= 0) ? 0 : Math.round(value);
                                var slab = priceSlabForAmount(rounded);
                                return '<span class="gs-price-pill" style="background:' + slab.bg + ';color:' + slab.fg + ';">$'
                                    + rounded + '</span>';
                            }
                        },
                        {
                            // Same KW(-) data as /purchase-master/ads-link (amazon_sp_negative_keywords)
                            title: 'KW(-)', field: 'negative_count', width: 72,
                            cssClass: 'gs-kw-neg-col',
                            hozAlign: 'center', headerHozAlign: 'center',
                            headerSort: true, sorter: 'number',
                            headerTooltip: 'Amz KW negatives — same count as /purchase-master/ads-link',
                            formatter: function (cell) {
                                var row = cell.getRow().getData();
                                var n = Number(cell.getValue()) || 0;
                                var campaigns = Array.isArray(row.kw_campaigns) ? row.kw_campaigns : [];
                                if (!campaigns.length) {
                                    return '';
                                }
                                if (!n) {
                                    return '<span class="text-muted">0</span>';
                                }
                                return '<span class="gs-kw-neg-value" title="' + esc(campaigns.join(', ')) + '">'
                                    + Number(n).toLocaleString('en-US') + '</span>';
                            }
                        },
                        {
                            title: 'Create', field: '_create', width: 70,
                            hozAlign: 'center', headerHozAlign: 'center',
                            headerSort: false,
                            formatter: function (cell) {
                                var d = cell.getRow().getData();
                                var hasCampaign = Array.isArray(d.campaigns) && d.campaigns.length > 0;
                                var title = hasCampaign
                                    ? 'Campaign already linked — create another?'
                                    : 'Create Google Shopping ad for this parent';
                                return '<button type="button" class="gs-create-btn" data-sku="' + esc(d.sku || '') + '" title="' + esc(title) + '">'
                                    + '<i class="fa fa-plus"></i></button>';
                            }
                        },
                        {
                            title: 'Campaign', field: 'campaigns', widthGrow: 3,
                            hozAlign: 'center', headerHozAlign: 'center',
                            headerSort: true, sorter: linkCountSorter,
                            formatter: campaignsFormatter,
                            accessorDownload: linkNamesAccessor
                        }
                    ]
                });

                function applyFilters() {
                    var parentQ = (document.getElementById('gsParentSearch').value || '').trim().toLowerCase();
                    var inv = document.getElementById('gsInvFilter').value || '';
                    var price = document.getElementById('gsPriceFilter').value || '';
                    var campaign = document.getElementById('gsCampaignFilter').value || '';

                    table.setFilter(function (data) {
                        if (!onlyParentsFilter(data)) { return false; }
                        if (parentQ) {
                            var p = String(data.parent || '').toLowerCase();
                            if (p.indexOf(parentQ) === -1) { return false; }
                        }
                        if (!inventoryHeaderFilter(inv, data.inventory)) { return false; }
                        if (!priceRangeHeaderFilter(price, data.price)) { return false; }
                        if (!missingHeaderFilter(campaign, data.campaigns)) { return false; }
                        return true;
                    });
                }

                initPriceRangeToolbarSelect();
                var parentSearchTimer = null;
                document.getElementById('gsParentSearch').addEventListener('input', function () {
                    clearTimeout(parentSearchTimer);
                    parentSearchTimer = setTimeout(applyFilters, 200);
                });
                document.getElementById('gsInvFilter').addEventListener('change', applyFilters);
                document.getElementById('gsPriceFilter').addEventListener('change', applyFilters);
                document.getElementById('gsCampaignFilter').addEventListener('change', applyFilters);

                table.on('tableBuilt', function () {
                    applyFilters();
                });

                document.getElementById('gsMissingExportBtn').addEventListener('click', function () {
                    table.download('csv', 'missing-google-shopping-ads-' + new Date().toISOString().slice(0, 10) + '.csv');
                });

                function updateMissingBadge(rowsData) {
                    var rows = rowsData || table.getData('active');
                    var missing = 0;
                    rows.forEach(function (r) {
                        if (!r || (Number(r.inventory) || 0) <= 0) { return; }
                        if (!r.campaigns || !r.campaigns.length) { missing++; }
                    });
                    var missingEl = document.getElementById('gsMissingValue');
                    var missingWrap = document.getElementById('gsMissingWrap');
                    if (missingEl) { missingEl.textContent = Number(missing).toLocaleString('en-US'); }
                    if (missingWrap) { missingWrap.classList.toggle('is-alert', missing > 0); }
                }

                function updateRowsBadge(rowsData) {
                    var rows = rowsData || table.getData('active');
                    var el = document.getElementById('gsParentValue');
                    if (el) { el.textContent = Number(rows.length).toLocaleString('en-US'); }
                }

                function updateInvBadge(rowsData) {
                    var rows = rowsData || table.getData('active');
                    var count = 0;
                    rows.forEach(function (r) {
                        if (r && (Number(r.inventory) || 0) > 0) { count++; }
                    });
                    var el = document.getElementById('gsInvValue');
                    if (el) { el.textContent = Number(count).toLocaleString('en-US'); }
                }

                table.on('dataFiltered', function (filters, rows) {
                    var active = (rows || []).map(function (rc) { return rc.getData(); });
                    updateMissingBadge(active);
                    updateRowsBadge(active);
                    updateInvBadge(active);
                });

                var badgePanel = document.createElement('div');
                badgePanel.className = 'gs-badge-panel d-none';
                badgePanel.innerHTML = '<div class="gs-badge-panel-title"></div><div class="gs-badge-panel-list"></div>';
                document.body.appendChild(badgePanel);
                var badgePanelTitle = badgePanel.querySelector('.gs-badge-panel-title');
                var badgePanelList = badgePanel.querySelector('.gs-badge-panel-list');

                function openBadgePanel(anchorEl, title, names) {
                    badgePanelTitle.textContent = title + ' (' + names.length + ')';
                    badgePanelList.innerHTML = names.length
                        ? names.map(function (n) { return '<div class="gs-badge-panel-item" title="' + esc(n) + '">' + esc(n) + '</div>'; }).join('')
                        : '<div class="gs-badge-panel-empty">Nothing to show</div>';
                    var rect = anchorEl.getBoundingClientRect();
                    badgePanel.style.top = (window.scrollY + rect.bottom + 4) + 'px';
                    badgePanel.style.left = (window.scrollX + rect.left) + 'px';
                    badgePanel.classList.remove('d-none');
                }

                function parentNamesFrom(rows, predicate) {
                    var out = [];
                    rows.forEach(function (r) {
                        if (predicate(r)) { out.push(r.parent || r.sku || ''); }
                    });
                    return out;
                }

                function bindBadge(wrapId, titleText, getNames) {
                    var el = document.getElementById(wrapId);
                    if (!el) { return; }
                    el.addEventListener('click', function () {
                        openBadgePanel(el, titleText, getNames());
                    });
                }
                bindBadge('gsParentWrap', 'rows', function () {
                    return parentNamesFrom(table.getData('active'), function () { return true; });
                });
                bindBadge('gsInvWrap', 'Inv>0', function () {
                    return parentNamesFrom(table.getData('active'), function (r) {
                        return r && (Number(r.inventory) || 0) > 0;
                    });
                });
                bindBadge('gsMissingWrap', 'Missing', function () {
                    return parentNamesFrom(table.getData('active'), function (r) {
                        return r && (Number(r.inventory) || 0) > 0 && (!r.campaigns || !r.campaigns.length);
                    });
                });

                document.addEventListener('click', function (e) {
                    if (badgePanel.classList.contains('d-none')) { return; }
                    if (badgePanel.contains(e.target) || e.target.closest('.gs-missing-badge')) { return; }
                    badgePanel.classList.add('d-none');
                });
                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape') { badgePanel.classList.add('d-none'); }
                });

                // Campaign picker (+ like /amazon-ads/missing)
                var picker = document.createElement('div');
                picker.className = 'gs-campaign-picker d-none';
                picker.innerHTML = '<input type="text" class="form-control form-control-sm gs-picker-input" placeholder="Search campaign...">'
                    + '<div class="gs-picker-list"></div>';
                document.body.appendChild(picker);
                var pickerInput = picker.querySelector('.gs-picker-input');
                var pickerList = picker.querySelector('.gs-picker-list');
                var pickerSku = null;

                function renderPickerList(filter) {
                    var f = (filter || '').toLowerCase();
                    var matches = campaignNames.filter(function (n) {
                        return n && (!f || String(n).toLowerCase().indexOf(f) !== -1);
                    }).slice(0, 100);
                    if (!matches.length) {
                        pickerList.innerHTML = '<div class="gs-picker-empty">No matching campaigns</div>';
                        return;
                    }
                    pickerList.innerHTML = matches.map(function (n) {
                        return '<div class="gs-picker-option" data-name="' + esc(n) + '" title="' + esc(n) + '">' + esc(n) + '</div>';
                    }).join('');
                }

                function openPicker(btn, sku) {
                    pickerSku = sku;
                    var rect = btn.getBoundingClientRect();
                    picker.style.top = (window.scrollY + rect.bottom + 2) + 'px';
                    picker.style.left = (window.scrollX + rect.left) + 'px';
                    picker.classList.remove('d-none');
                    pickerInput.value = '';
                    pickerList.innerHTML = '<div class="gs-picker-empty">Loading campaigns...</div>';
                    pickerInput.focus();
                    ensureCampaignNames().then(function () {
                        if (pickerSku !== sku) { return; }
                        renderPickerList(pickerInput.value);
                    });
                }

                function closePicker() {
                    picker.classList.add('d-none');
                    pickerSku = null;
                }

                pickerInput.addEventListener('input', function () {
                    renderPickerList(this.value);
                });

                pickerList.addEventListener('click', function (e) {
                    var opt = e.target.closest('.gs-picker-option');
                    if (!opt || !pickerSku) { return; }
                    var name = opt.getAttribute('data-name');
                    var sku = pickerSku;
                    closePicker();
                    postJson(linkUrl, { sku: sku, campaign_name: name }).then(function (out) {
                        var r = table.getRow(sku);
                        if (out.ok && out.body && r) {
                            r.update({ campaigns: out.body.campaigns || [] });
                            updateMissingBadge();
                        } else if (!out.ok) {
                            window.alert((out.body && out.body.message) || 'Failed to link campaign.');
                        }
                    });
                });

                document.addEventListener('click', function (e) {
                    if (picker.classList.contains('d-none')) { return; }
                    if (picker.contains(e.target) || e.target.closest('.link-add-btn')) { return; }
                    closePicker();
                });
                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape') { closePicker(); }
                });

                document.getElementById('gsAdsMissingTable').addEventListener('click', function (e) {
                    var copyBtn = e.target.closest('.parent-copy-btn');
                    if (copyBtn) {
                        var txt = copyBtn.getAttribute('data-parent') || '';
                        if (navigator.clipboard && navigator.clipboard.writeText) {
                            navigator.clipboard.writeText(txt);
                        }
                        copyBtn.classList.remove('fa-copy');
                        copyBtn.classList.add('fa-check');
                        setTimeout(function () {
                            copyBtn.classList.remove('fa-check');
                            copyBtn.classList.add('fa-copy');
                        }, 900);
                        return;
                    }

                    var createBtn = e.target.closest('.gs-create-btn');
                    if (createBtn) {
                        e.preventDefault();
                        e.stopPropagation();
                        var createSku = createBtn.getAttribute('data-sku') || '';
                        var createRow = null;
                        try {
                            if (createSku) { createRow = table.getRow(createSku); }
                        } catch (err) { createRow = null; }
                        if (!createRow) {
                            var rowEl = createBtn.closest('.tabulator-row');
                            if (rowEl) {
                                try { createRow = table.getRow(rowEl); } catch (err2) { createRow = null; }
                            }
                        }
                        if (createRow) {
                            openCreateModal(createRow.getData());
                        } else {
                            window.alert('Could not load row data for Create.');
                        }
                        return;
                    }

                    var addBtn = e.target.closest('.link-add-btn');
                    if (addBtn) {
                        openPicker(addBtn, addBtn.getAttribute('data-sku'));
                        return;
                    }

                    var x = e.target.closest('.chip-x');
                    if (x) {
                        var id = Number(x.getAttribute('data-id'));
                        var sku2 = x.getAttribute('data-sku');
                        postJson(unlinkUrl, { id: id }).then(function (out) {
                            if (out.ok && out.body) {
                                var r = table.getRow(sku2);
                                if (r) {
                                    r.update({ campaigns: out.body.campaigns || [] });
                                }
                                updateMissingBadge();
                            }
                        });
                        return;
                    }

                    var trash = e.target.closest('.chip-trash');
                    if (trash) {
                        var delSku = trash.getAttribute('data-sku') || '';
                        var delName = trash.getAttribute('data-campaign-name') || '';
                        var delCid = trash.getAttribute('data-campaign-id') || '';
                        var delLinkId = trash.getAttribute('data-id') || '';
                        var confirmMsg = 'Delete campaign in Google Ads?\n\n'
                            + (delName || delCid)
                            + '\n\nThis sets the campaign to REMOVED in Google Ads and unlinks it here.';
                        if (!window.confirm(confirmMsg)) {
                            return;
                        }
                        trash.style.opacity = '0.4';
                        postJson(deleteUrl, {
                            sku: delSku,
                            campaign_id: delCid,
                            campaign_name: delName,
                            link_id: delLinkId ? Number(delLinkId) : null
                        }).then(function (out) {
                            trash.style.opacity = '';
                            if (out.ok && out.body && out.body.ok) {
                                var rDel = table.getRow(delSku);
                                if (rDel) {
                                    rDel.update({ campaigns: out.body.campaigns || [] });
                                }
                                updateMissingBadge();
                                window.alert(out.body.message || 'Campaign deleted.');
                            } else {
                                window.alert((out.body && out.body.message) || 'Failed to delete campaign.');
                            }
                        }).catch(function () {
                            trash.style.opacity = '';
                            window.alert('Network error deleting campaign.');
                        });
                    }
                });

                function getCreateModal() {
                    var el = document.getElementById('gsCreateCampaignModal');
                    if (!el) { return null; }
                    if (window.bootstrap && typeof bootstrap.Modal === 'function') {
                        return bootstrap.Modal.getOrCreateInstance(el);
                    }
                    if (window.jQuery && typeof jQuery(el).modal === 'function') {
                        return {
                            show: function () { jQuery(el).modal('show'); },
                            hide: function () { jQuery(el).modal('hide'); }
                        };
                    }
                    return null;
                }

                function escAttr(s) {
                    return String(s == null ? '' : s)
                        .replace(/&/g, '&amp;')
                        .replace(/"/g, '&quot;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;');
                }

                function syncCreateAiHiddenFromSelection() {
                    var first = document.querySelector('#gsCreateChildrenBody .gs-child-check:checked');
                    var row = first ? first.closest('tr') : document.querySelector('#gsCreateChildrenBody tr');
                    if (!row) {
                        document.getElementById('gsCreateTargetSku').value = '';
                        document.getElementById('gsCreateItemId').value = '';
                        return;
                    }
                    var skuEl = row.querySelector('.gs-child-sku');
                    var itemEl = row.querySelector('.gs-child-item-id');
                    document.getElementById('gsCreateTargetSku').value = skuEl ? skuEl.value : '';
                    document.getElementById('gsCreateItemId').value = itemEl ? itemEl.value : '';
                }

                function renderCreateChildren(row) {
                    var body = document.getElementById('gsCreateChildrenBody');
                    var countEl = document.getElementById('gsCreateChildCount');
                    var children = Array.isArray(row.children) ? row.children.slice() : [];
                    if (!children.length && (row.target_sku || row.merchant_item_id)) {
                        children = [{
                            target_sku: row.target_sku || '',
                            merchant_item_id: row.merchant_item_id || '',
                            inv: null
                        }];
                    }
                    if (!children.length) {
                        body.innerHTML = '<tr><td colspan="4" class="text-muted small px-3 py-3">No child SKUs found for this parent.</td></tr>';
                        countEl.textContent = '(0)';
                        return;
                    }
                    var html = '';
                    children.forEach(function (c, idx) {
                        var sku = c.target_sku || '';
                        var itemId = c.merchant_item_id || '';
                        var inv = (c.inv == null || c.inv === '') ? '—' : String(c.inv);
                        var checked = itemId.indexOf('shopify_us_') === 0 ? ' checked' : '';
                        var warn = itemId.indexOf('shopify_us_') === 0 ? '' : ' table-warning';
                        html += '<tr class="' + warn + '" data-idx="' + idx + '">'
                            + '<td class="text-center"><input type="checkbox" class="form-check-input gs-child-check"' + checked + '></td>'
                            + '<td><input type="text" class="form-control form-control-sm gs-child-sku" value="' + escAttr(sku) + '"></td>'
                            + '<td><input type="text" class="form-control form-control-sm gs-child-item-id" value="' + escAttr(itemId) + '" placeholder="shopify_us_…"></td>'
                            + '<td class="small text-muted">' + escAttr(inv) + '</td>'
                            + '</tr>';
                    });
                    body.innerHTML = html;
                    countEl.textContent = '(' + children.length + ')';
                    syncCreateAiHiddenFromSelection();
                }

                function collectSelectedChildren() {
                    var out = [];
                    document.querySelectorAll('#gsCreateChildrenBody tr').forEach(function (tr) {
                        var check = tr.querySelector('.gs-child-check');
                        if (!check || !check.checked) { return; }
                        var skuEl = tr.querySelector('.gs-child-sku');
                        var itemEl = tr.querySelector('.gs-child-item-id');
                        out.push({
                            target_sku: (skuEl ? skuEl.value : '').trim(),
                            item_id: (itemEl ? itemEl.value : '').trim()
                        });
                    });
                    return out;
                }

                function openCreateModal(row) {
                    if (!row) { return; }
                    document.getElementById('gsCreateParent').value = row.parent || '';
                    document.getElementById('gsCreateParentDisplay').value = row.parent || '';
                    document.getElementById('gsCreateCampaignName').value = row.suggested_campaign_name || ('PARENT ' + (row.parent || ''));
                    document.getElementById('gsCreateBuyerLink').value = row.buyer_link || '';
                    document.getElementById('gsCreateBudget').value = '1';
                    document.getElementById('gsCreateCpcBid').value = '0.50';
                    document.getElementById('gsCreatePriority').value = '0';
                    document.getElementById('gsCreateMerchantId').value = String(row.default_merchant_id || 198980051);
                    document.getElementById('gsCreateFeedLabel').value = 'US';
                    document.getElementById('gsCreateError').classList.add('d-none');
                    document.getElementById('gsCreateError').textContent = '';
                    renderCreateChildren(row);
                    if (!(Array.isArray(row.children) && row.children.some(function (c) {
                        return (c.merchant_item_id || '').indexOf('shopify_us_') === 0;
                    }))) {
                        document.getElementById('gsCreateError').textContent =
                            'No Merchant Center Item IDs resolved. Enter shopify_us_{productId}_{variantId} for each child, then select them.';
                        document.getElementById('gsCreateError').classList.remove('d-none');
                    }
                    syncBuyerLinkOpen();
                    var modal = getCreateModal();
                    if (modal) {
                        modal.show();
                    } else {
                        window.alert('Bootstrap modal is not available. Please hard-refresh the page.');
                    }
                }

                function getAiNegModal() {
                    var el = document.getElementById('gsAiNegModal');
                    if (!el) { return null; }
                    if (window.bootstrap && typeof bootstrap.Modal === 'function') {
                        return bootstrap.Modal.getOrCreateInstance(el);
                    }
                    return null;
                }

                // [{ text, source: 'ai'|'manual' }]
                var aiNegSuggestedCache = [];
                var aiNegExistingCache = [];
                var aiNegMeta = { parent: '', target_sku: '', product_title: '' };

                function normalizeNegItem(kw, source) {
                    if (kw && typeof kw === 'object') {
                        return {
                            text: String(kw.text || '').trim(),
                            source: kw.source === 'manual' ? 'manual' : 'ai'
                        };
                    }
                    return {
                        text: String(kw || '').trim(),
                        source: source === 'manual' ? 'manual' : 'ai'
                    };
                }

                function mergeUniqueNegItems(base, extra) {
                    var seen = {};
                    var out = [];
                    [base, extra].forEach(function (list) {
                        (Array.isArray(list) ? list : []).forEach(function (kw) {
                            var item = normalizeNegItem(kw);
                            if (!item.text) { return; }
                            var key = item.text.toLowerCase();
                            if (seen[key]) { return; }
                            seen[key] = true;
                            out.push(item);
                        });
                    });
                    return out;
                }

                function getSuggestedTexts() {
                    return aiNegSuggestedCache.map(function (i) { return i.text; }).filter(Boolean);
                }

                function renderSuggestedList() {
                    var suggestedWrap = document.getElementById('gsAiNegSuggestedWrap');
                    var suggestedEl = document.getElementById('gsAiNegSuggested');
                    var suggestedCountEl = document.getElementById('gsAiNegSuggestedCount');
                    var subtitle = document.getElementById('gsAiNegSubtitle');
                    var texts = getSuggestedTexts();

                    if (suggestedCountEl) { suggestedCountEl.textContent = String(texts.length); }
                    suggestedWrap.classList.remove('d-none');
                    suggestedEl.innerHTML = texts.length
                        ? aiNegSuggestedCache.map(function (item, idx) {
                            var badge = item.source === 'manual'
                                ? '<span class="badge bg-success-subtle text-success border">Manual</span>'
                                : '<span class="badge bg-danger-subtle text-danger border">AI</span>';
                            return '<li class="list-group-item py-1 px-2 small d-flex justify-content-between align-items-center gap-2">'
                                + '<span class="flex-grow-1">' + esc(item.text) + '</span>'
                                + badge
                                + '<button type="button" class="btn btn-link btn-sm text-danger p-0 gs-ai-neg-del" data-idx="' + idx + '" title="Remove keyword">'
                                + '<i class="fa fa-trash"></i></button>'
                                + '</li>';
                        }).join('')
                        : '<li class="list-group-item py-2 px-2 small text-muted">No keywords yet. Generate AI suggestions or add manually.</li>';
                    suggestedEl.dataset.copyText = texts.join('\n');

                    subtitle.textContent = (aiNegMeta.parent ? ('Parent: ' + aiNegMeta.parent) : '')
                        + (aiNegMeta.target_sku ? (' · SKU: ' + aiNegMeta.target_sku) : '')
                        + (aiNegMeta.product_title ? (' · ' + aiNegMeta.product_title) : '')
                        + ' · ' + texts.length + ' to push'
                        + (aiNegExistingCache.length ? (' · ' + aiNegExistingCache.length + ' already on Amz') : '');
                }

                function addManualNegativeKeyword(raw) {
                    var text = String(raw || '').trim().replace(/\s+/g, ' ');
                    if (!text) { return false; }
                    var exists = aiNegSuggestedCache.some(function (i) {
                        return i.text.toLowerCase() === text.toLowerCase();
                    });
                    if (exists) {
                        return false;
                    }
                    aiNegSuggestedCache.push({ text: text, source: 'manual' });
                    renderSuggestedList();
                    return true;
                }

                function exportNegativesCsv() {
                    var rows = [['keyword', 'source']];
                    aiNegSuggestedCache.forEach(function (item) {
                        rows.push([item.text, item.source || 'ai']);
                    });
                    if (document.getElementById('gsAiNegIncludeAmazon').checked) {
                        aiNegExistingCache.forEach(function (kw) {
                            var t = String(kw || '').trim();
                            if (!t) { return; }
                            var already = aiNegSuggestedCache.some(function (i) {
                                return i.text.toLowerCase() === t.toLowerCase();
                            });
                            if (!already) {
                                rows.push([t, 'amazon']);
                            }
                        });
                    }
                    if (rows.length <= 1) {
                        window.alert('No keywords to export.');
                        return;
                    }
                    var csv = rows.map(function (r) {
                        return r.map(function (cell) {
                            var s = String(cell == null ? '' : cell);
                            if (/[",\n]/.test(s)) {
                                return '"' + s.replace(/"/g, '""') + '"';
                            }
                            return s;
                        }).join(',');
                    }).join('\n');
                    var parent = (aiNegMeta.parent || 'parent').replace(/[^\w\-]+/g, '_');
                    var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                    var url = URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = 'negative-keywords-' + parent + '-' + new Date().toISOString().slice(0, 10) + '.csv';
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                }

                function renderAiNegatives(payload, options) {
                    options = options || {};
                    var existing = Array.isArray(payload.existing) ? payload.existing : [];
                    var suggested = Array.isArray(payload.suggested) ? payload.suggested : [];
                    var incoming = suggested.map(function (kw) { return normalizeNegItem(kw, 'ai'); });

                    if (options.append) {
                        // Keep existing manual/AI items; append new AI ones.
                        aiNegSuggestedCache = mergeUniqueNegItems(aiNegSuggestedCache, incoming);
                    } else {
                        // Regenerate replaces AI items but keeps manually added keywords.
                        var manuals = aiNegSuggestedCache.filter(function (i) { return i.source === 'manual'; });
                        aiNegSuggestedCache = mergeUniqueNegItems(manuals, incoming);
                    }

                    aiNegExistingCache = existing.slice();
                    aiNegMeta = {
                        parent: payload.parent || aiNegMeta.parent || '',
                        target_sku: payload.target_sku || aiNegMeta.target_sku || '',
                        product_title: payload.product_title || aiNegMeta.product_title || ''
                    };

                    var existingWrap = document.getElementById('gsAiNegExistingWrap');
                    var existingEl = document.getElementById('gsAiNegExisting');
                    var existingCountEl = document.getElementById('gsAiNegExistingCount');

                    if (existingCountEl) { existingCountEl.textContent = String(existing.length); }
                    if (existing.length) {
                        existingWrap.classList.remove('d-none');
                        existingEl.textContent = existing.join(', ');
                    } else {
                        existingWrap.classList.add('d-none');
                        existingEl.textContent = '';
                    }

                    renderSuggestedList();
                }

                function setAiNegBusy(busy) {
                    document.getElementById('gsAiNegRegenBtn').disabled = !!busy;
                    document.getElementById('gsAiNegAddMoreBtn').disabled = !!busy;
                }

                function runAiNegatives(options) {
                    options = options || {};
                    var append = !!options.append;
                    var parent = document.getElementById('gsCreateParent').value || '';
                    var targetSku = document.getElementById('gsCreateTargetSku').value || '';
                    var campaignName = document.getElementById('gsCreateCampaignName').value || '';
                    var buyerLink = document.getElementById('gsCreateBuyerLink').value || '';
                    var ideas = (document.getElementById('gsAiNegIdeas').value || '').trim();
                    var loading = document.getElementById('gsAiNegLoading');
                    var errEl = document.getElementById('gsAiNegError');
                    var suggestedWrap = document.getElementById('gsAiNegSuggestedWrap');
                    var existingWrap = document.getElementById('gsAiNegExistingWrap');

                    if (append && !ideas) {
                        errEl.textContent = 'Enter ideas above, then click Add more from ideas.';
                        errEl.classList.remove('d-none');
                        return;
                    }

                    errEl.classList.add('d-none');
                    errEl.textContent = '';
                    if (!append) {
                        suggestedWrap.classList.add('d-none');
                        existingWrap.classList.add('d-none');
                        // Keep manual keywords across regenerate.
                        aiNegSuggestedCache = aiNegSuggestedCache.filter(function (i) {
                            return i && i.source === 'manual';
                        });
                    }
                    loading.classList.remove('d-none');
                    setAiNegBusy(true);

                    postJson(aiNegUrl, {
                        parent: parent,
                        target_sku: targetSku,
                        campaign_name: campaignName,
                        buyer_link: buyerLink,
                        ideas: ideas,
                        already_suggested: append ? getSuggestedTexts() : getSuggestedTexts(),
                        mode: append ? 'add_more' : 'generate'
                    }).then(function (out) {
                        loading.classList.add('d-none');
                        setAiNegBusy(false);
                        if (out.ok && out.body && out.body.ok) {
                            renderAiNegatives(out.body, { append: append });
                        } else {
                            errEl.textContent = (out.body && out.body.message) || 'Failed to generate negative keywords.';
                            errEl.classList.remove('d-none');
                            if (append && aiNegSuggestedCache.length) {
                                suggestedWrap.classList.remove('d-none');
                            }
                        }
                    }).catch(function () {
                        loading.classList.add('d-none');
                        setAiNegBusy(false);
                        errEl.textContent = 'Network error generating negative keywords.';
                        errEl.classList.remove('d-none');
                        if (append && aiNegSuggestedCache.length) {
                            suggestedWrap.classList.remove('d-none');
                        }
                    });
                }

                document.getElementById('gsCreateAiNegLink').addEventListener('click', function (e) {
                    e.preventDefault();
                    var modal = getAiNegModal();
                    if (!modal) {
                        window.alert('Could not open AI negatives modal.');
                        return;
                    }
                    document.getElementById('gsAiNegIdeas').value = '';
                    document.getElementById('gsAiNegManualInput').value = '';
                    aiNegSuggestedCache = [];
                    aiNegExistingCache = [];
                    aiNegMeta = {
                        parent: document.getElementById('gsCreateParent').value || '',
                        target_sku: document.getElementById('gsCreateTargetSku').value || '',
                        product_title: ''
                    };
                    var pushOk = document.getElementById('gsAiNegPushOk');
                    if (pushOk) {
                        pushOk.classList.add('d-none');
                        pushOk.textContent = '';
                    }
                    var pushErr = document.getElementById('gsAiNegError');
                    if (pushErr) {
                        pushErr.classList.add('d-none');
                        pushErr.textContent = '';
                    }
                    modal.show();
                    runAiNegatives({ append: false });
                });
                document.getElementById('gsAiNegRegenBtn').addEventListener('click', function () {
                    runAiNegatives({ append: false });
                });
                document.getElementById('gsAiNegAddMoreBtn').addEventListener('click', function () {
                    runAiNegatives({ append: true });
                });
                document.getElementById('gsAiNegCopyBtn').addEventListener('click', function () {
                    var text = document.getElementById('gsAiNegSuggested').dataset.copyText || '';
                    if (!text) { return; }
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(text).then(function () {
                            window.alert('Copied ' + text.split('\n').filter(Boolean).length + ' negative keyword(s).');
                        });
                    }
                });
                document.getElementById('gsAiNegExportBtn').addEventListener('click', function () {
                    exportNegativesCsv();
                });
                document.getElementById('gsAiNegManualAddBtn').addEventListener('click', function () {
                    var input = document.getElementById('gsAiNegManualInput');
                    var errEl = document.getElementById('gsAiNegError');
                    errEl.classList.add('d-none');
                    var ok = addManualNegativeKeyword(input.value);
                    if (!ok) {
                        errEl.textContent = (input.value || '').trim()
                            ? 'Keyword is empty or already in the list.'
                            : 'Enter a keyword to add.';
                        errEl.classList.remove('d-none');
                        return;
                    }
                    input.value = '';
                    input.focus();
                });
                document.getElementById('gsAiNegManualInput').addEventListener('keydown', function (e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        document.getElementById('gsAiNegManualAddBtn').click();
                    }
                });
                document.getElementById('gsAiNegSuggested').addEventListener('click', function (e) {
                    var btn = e.target.closest('.gs-ai-neg-del');
                    if (!btn) { return; }
                    var idx = Number(btn.getAttribute('data-idx'));
                    if (!isFinite(idx) || idx < 0 || idx >= aiNegSuggestedCache.length) { return; }
                    aiNegSuggestedCache.splice(idx, 1);
                    renderSuggestedList();
                });

                document.getElementById('gsAiNegPushBtn').addEventListener('click', function () {
                    var btn = document.getElementById('gsAiNegPushBtn');
                    var errEl = document.getElementById('gsAiNegError');
                    var okEl = document.getElementById('gsAiNegPushOk');
                    errEl.classList.add('d-none');
                    errEl.textContent = '';
                    okEl.classList.add('d-none');
                    okEl.textContent = '';

                    var keywords = getSuggestedTexts();
                    var includeAmazon = !!document.getElementById('gsAiNegIncludeAmazon').checked;
                    if (!keywords.length && !includeAmazon) {
                        errEl.textContent = 'Add keywords (AI or manual), or enable Amz KW(-) negatives.';
                        errEl.classList.remove('d-none');
                        return;
                    }

                    var payload = {
                        parent: document.getElementById('gsCreateParent').value || '',
                        campaign_name: document.getElementById('gsCreateCampaignName').value || '',
                        campaign_id: lastCreatedCampaignId || '',
                        keywords: keywords,
                        include_amazon: includeAmazon,
                        match_type: document.getElementById('gsAiNegMatchType').value || 'PHRASE'
                    };
                    if (!payload.parent) {
                        errEl.textContent = 'Parent is missing. Open Create from a parent row first.';
                        errEl.classList.remove('d-none');
                        return;
                    }

                    btn.disabled = true;
                    btn.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i> Pushing…';
                    postJson(pushNegUrl, payload).then(function (out) {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fa fa-cloud-upload-alt me-1"></i> Push Negative Keywords';
                        if (out.ok && out.body && out.body.ok) {
                            if (out.body.campaign_id) {
                                lastCreatedCampaignId = String(out.body.campaign_id);
                            }
                            okEl.textContent = out.body.message || 'Negative keywords pushed.';
                            okEl.classList.remove('d-none');
                        } else {
                            errEl.textContent = (out.body && out.body.message) || 'Failed to push negative keywords.';
                            errEl.classList.remove('d-none');
                        }
                    }).catch(function () {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fa fa-cloud-upload-alt me-1"></i> Push Negative Keywords';
                        errEl.textContent = 'Network error pushing negative keywords.';
                        errEl.classList.remove('d-none');
                    });
                });

                function syncBuyerLinkOpen() {
                    var href = (document.getElementById('gsCreateBuyerLink').value || '').trim();
                    var a = document.getElementById('gsCreateBuyerLinkOpen');
                    if (!a) { return; }
                    if (href) {
                        a.href = href;
                        a.classList.remove('disabled');
                    } else {
                        a.href = '#';
                        a.classList.add('disabled');
                    }
                }
                document.getElementById('gsCreateBuyerLink').addEventListener('input', syncBuyerLinkOpen);

                document.getElementById('gsCreateSelectAll').addEventListener('click', function () {
                    document.querySelectorAll('#gsCreateChildrenBody .gs-child-check').forEach(function (cb) {
                        cb.checked = true;
                    });
                    syncCreateAiHiddenFromSelection();
                });
                document.getElementById('gsCreateSelectNone').addEventListener('click', function () {
                    document.querySelectorAll('#gsCreateChildrenBody .gs-child-check').forEach(function (cb) {
                        cb.checked = false;
                    });
                    syncCreateAiHiddenFromSelection();
                });
                document.getElementById('gsCreateChildrenBody').addEventListener('change', function (e) {
                    if (e.target && e.target.classList.contains('gs-child-check')) {
                        syncCreateAiHiddenFromSelection();
                    }
                });

                document.getElementById('gsCreateSubmitBtn').addEventListener('click', function () {
                    var btn = document.getElementById('gsCreateSubmitBtn');
                    var errEl = document.getElementById('gsCreateError');
                    errEl.classList.add('d-none');
                    errEl.textContent = '';

                    syncCreateAiHiddenFromSelection();
                    var children = collectSelectedChildren();
                    var payload = {
                        parent: document.getElementById('gsCreateParent').value,
                        campaign_name: document.getElementById('gsCreateCampaignName').value,
                        buyer_link: document.getElementById('gsCreateBuyerLink').value,
                        budget_amount: parseFloat(document.getElementById('gsCreateBudget').value) || 1,
                        cpc_bid: parseFloat(document.getElementById('gsCreateCpcBid').value) || 0.5,
                        campaign_priority: parseInt(document.getElementById('gsCreatePriority').value, 10) || 0,
                        merchant_id: parseInt(document.getElementById('gsCreateMerchantId').value, 10) || 0,
                        feed_label: document.getElementById('gsCreateFeedLabel').value || 'US',
                        children: children
                    };

                    if (!payload.parent || !payload.merchant_id || !(payload.campaign_name || '').trim()) {
                        errEl.textContent = 'Parent, campaign name, and merchant ID are required.';
                        errEl.classList.remove('d-none');
                        return;
                    }
                    if (!children.length) {
                        errEl.textContent = 'Select at least one child SKU to include in the campaign.';
                        errEl.classList.remove('d-none');
                        return;
                    }
                    for (var i = 0; i < children.length; i++) {
                        var c = children[i];
                        if (!c.target_sku) {
                            errEl.textContent = 'Each selected row needs a Target SKU.';
                            errEl.classList.remove('d-none');
                            return;
                        }
                        if ((c.item_id || '').indexOf('shopify_us_') !== 0) {
                            errEl.textContent = 'Item ID for "' + c.target_sku + '" must look like shopify_us_{productId}_{variantId}.';
                            errEl.classList.remove('d-none');
                            return;
                        }
                    }

                    btn.disabled = true;
                    btn.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i> Creating…';

                    postJson(createUrl, payload).then(function (out) {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fa fa-plus me-1"></i> Create campaign';
                        if (out.ok && out.body && out.body.ok) {
                            var sku = out.body.sku || ('PARENT ' + payload.parent);
                            var r = table.getRow(sku);
                            if (r) {
                                r.update({ campaigns: out.body.campaigns || [] });
                            }
                            if (out.body.campaign && out.body.campaign.campaign_id) {
                                lastCreatedCampaignId = String(out.body.campaign.campaign_id);
                            }
                            updateMissingBadge();
                            var modalHide = getCreateModal();
                            if (modalHide) { modalHide.hide(); }
                            window.alert(out.body.message || 'Campaign created.');
                        } else {
                            errEl.textContent = (out.body && out.body.message) || 'Failed to create campaign.';
                            errEl.classList.remove('d-none');
                        }
                    }).catch(function () {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fa fa-plus me-1"></i> Create campaign';
                        errEl.textContent = 'Network error creating campaign.';
                        errEl.classList.remove('d-none');
                    });
                });
            }

            // Load table immediately; campaign list is fetched only when + is clicked.
            buildTable();
        })();
    </script>
@endsection
