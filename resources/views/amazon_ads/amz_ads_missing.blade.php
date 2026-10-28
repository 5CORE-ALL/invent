@extends('layouts.vertical', ['title' => 'Amz Ads Missing', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .amz-ads-missing .tabulator .tabulator-header .tabulator-col {
            font-size: 0.8rem;
        }
        .amz-ads-missing .tabulator-row .tabulator-cell {
            font-size: 0.82rem;
        }
        .amz-ads-missing .parent-row {
            background-color: #fffef2;
        }
        .amz-ads-missing .parent-copy-btn {
            cursor: pointer;
            color: #868e96;
            margin-left: 6px;
        }
        .amz-ads-missing .parent-copy-btn:hover {
            color: #1971c2;
        }
        .amz-ads-missing .amz-missing-badge {
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
        .amz-ads-missing .amz-missing-badge--parent {
            background-color: #1971c2;
        }
        .amz-ads-missing .amz-missing-badge--pt,
        .amz-ads-missing .amz-missing-badge--kw,
        .amz-ads-missing .amz-missing-badge--hl {
            background-color: #dc2626;
        }
        .amz-ads-missing .amz-missing-badge--missing {
            background-color: #868e96;
        }
        .amz-ads-missing .amz-missing-badge--missing.is-alert {
            background-color: #dc2626;
        }
        .amz-ads-missing .amz-missing-badge.is-active {
            box-shadow: 0 0 0 2px #fff, 0 0 0 4px #dc2626;
        }
        .amz-badge-panel {
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
        .amz-badge-panel.d-none {
            display: none !important;
        }
        .amz-badge-panel .amz-badge-panel-title {
            font-weight: 700;
            font-size: 0.85rem;
            margin-bottom: 6px;
            border-bottom: 1px solid #e9ecef;
            padding-bottom: 4px;
        }
        .amz-badge-panel .amz-badge-panel-list {
            overflow-y: auto;
        }
        .amz-badge-panel .amz-badge-panel-item {
            font-size: 0.78rem;
            padding: 2px 2px;
            border-bottom: 1px dashed #f1f3f5;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .amz-badge-panel .amz-badge-panel-empty {
            color: #868e96;
            font-size: 0.78rem;
        }
        .amz-ads-missing .link-chip {
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
        .amz-ads-missing .link-chip .chip-x {
            cursor: pointer;
            color: #868e96;
            margin-left: 2px;
        }
        .amz-ads-missing .link-chip .chip-x:hover {
            color: #495057;
        }
        .amz-ads-missing .link-chip .chip-trash {
            cursor: pointer;
            color: #e03131;
            margin-left: 2px;
        }
        .amz-ads-missing .link-chip .chip-trash:hover {
            color: #c92a2a;
        }
        .amz-ads-missing .link-chip .chip-created {
            color: #2f9e44;
            cursor: help;
            margin-left: 2px;
        }
        .amz-ads-missing .amz-create-btn {
            border: 1px solid #2f9e44;
            background: #fff;
            color: #2f9e44;
            border-radius: 6px;
            padding: 0 8px;
            line-height: 1.4;
            cursor: pointer;
            font-weight: 700;
        }
        .amz-ads-missing .amz-create-btn:hover {
            background: #ebfbee;
        }
        .amz-ads-missing .amz-create-btn:disabled {
            opacity: 0.55;
            cursor: not-allowed;
        }
        .amz-ads-missing .campaign-dot {
            display: inline-block;
            width: 9px;
            height: 9px;
            border-radius: 50%;
            border: 1px solid rgba(0, 0, 0, 0.15);
            flex: 0 0 auto;
        }
        .amz-ads-missing .campaign-dot-green {
            background-color: #16a34a;
        }
        .amz-ads-missing .campaign-dot-red {
            background-color: #dc2626;
        }
        .amz-pva-added { color: #16a34a; font-weight: 700; }
        .amz-pva-missing { color: #dc2626; font-weight: 700; }
        .amz-pva-over { color: #ea580c; font-weight: 700; }
        .amz-pva-extra { color: #2563eb; font-weight: 700; }
        .amz-pva-archived { color: #6b7280; font-weight: 700; }
        .amz-pva-na { color: #94a3b8; }
        .amz-pva-wrap { display: inline-flex; flex-direction: column; align-items: center; gap: 4px; line-height: 1.25; }
        .amz-pva-skus { display: inline-block; font-size: 10px; font-weight: 700; max-width: 240px; white-space: normal; word-break: break-word; padding: 2px 6px; border-radius: 4px; line-height: 1.35; text-align: left; }
        .amz-pva-skus--missing { color: #991b1b; background: #fee2e2; border: 1px solid #fca5a5; }
        .amz-pva-skus--over { color: #9a3412; background: #ffedd5; border: 1px solid #fdba74; }
        .amz-pva-skus--extra { color: #1e3a8a; background: #dbeafe; border: 1px solid #93c5fd; }
        .amz-pva-skus--archived { color: #374151; background: #e5e7eb; border: 1px solid #9ca3af; }
        .amz-ads-missing .link-add-btn {
            border: 1px solid #adb5bd;
            background: #fff;
            border-radius: 6px;
            padding: 0 6px;
            line-height: 1.4;
            cursor: pointer;
            color: #2f9e44;
        }
        .amz-ads-missing .link-add-btn:hover {
            background: #f1f3f5;
        }
        .amz-campaign-picker {
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
        .amz-campaign-picker.d-none {
            display: none !important;
        }
        .amz-campaign-picker .amz-picker-list {
            overflow-y: auto;
            margin-top: 6px;
        }
        .amz-campaign-picker .amz-picker-option {
            padding: 4px 6px;
            font-size: 0.78rem;
            cursor: pointer;
            border-radius: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .amz-campaign-picker .amz-picker-option:hover {
            background: #e7f5ff;
        }
        .amz-campaign-picker .amz-picker-empty {
            padding: 6px;
            color: #868e96;
            font-size: 0.78rem;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared/page-title', ['sub_title' => 'Amz Ads', 'page_title' => 'Amz Ads Missing'])

    <div class="row amz-ads-missing">
        <div class="col-12">
            <div class="card">
                <div class="card-body p-2">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <div class="amz-missing-badge amz-missing-badge--parent" id="amzParentWrap" title="Parent: total number of parent rows.">
                            <span class="amz-missing-badge-label">Parent</span>
                            <span class="amz-missing-badge-value tabular-nums" id="amzParentValue">0</span>
                        </div>
                        <div class="amz-missing-badge amz-missing-badge--missing" id="amzMissingWrap" title="Missing All: Missing KW + Missing PT + Missing HL (in-stock rows in the current view).">
                            <span class="amz-missing-badge-label">Missing All</span>
                            <span class="amz-missing-badge-value tabular-nums" id="amzMissingValue">0</span>
                        </div>
                        <div class="amz-missing-badge amz-missing-badge--kw" id="amzMissingKwWrap" role="button" tabindex="0" aria-pressed="false" title="Missing KW: in-stock rows (inventory > 0) with no linked KW campaign. Click to show only those rows; click again to clear.">
                            <span class="amz-missing-badge-label">Missing KW</span>
                            <span class="amz-missing-badge-value tabular-nums" id="amzMissingKwValue">0</span>
                        </div>
                        <div class="amz-missing-badge amz-missing-badge--pt" id="amzMissingPtWrap" role="button" tabindex="0" aria-pressed="false" title="Missing PT: in-stock rows (inventory > 0) with no linked PT campaign. Click to show only those rows; click again to clear.">
                            <span class="amz-missing-badge-label">Missing PT</span>
                            <span class="amz-missing-badge-value tabular-nums" id="amzMissingPtValue">0</span>
                        </div>
                        <div class="amz-missing-badge amz-missing-badge--hl" id="amzMissingHlWrap" role="button" tabindex="0" aria-pressed="false" title="Missing HL: in-stock rows (inventory > 0) with no linked HL (Headline / Sponsored Brands) campaign. Click to show only those rows; click again to clear.">
                            <span class="amz-missing-badge-label">Missing HL</span>
                            <span class="amz-missing-badge-value tabular-nums" id="amzMissingHlValue">0</span>
                        </div>
                        <span class="small text-muted ms-2" id="amzKwCountStatus"></span>
                        <button type="button" class="btn btn-outline-secondary btn-sm ms-auto" id="amzKwCountReloadBtn" title="Load keyword / target counts from Amazon for rows that don't have one yet">
                            <i class="fa fa-sync me-1"></i> Counts
                        </button>
                        <div class="dropdown">
                            <button type="button" class="btn btn-outline-primary btn-sm" id="amzColumnsBtn" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" title="Show / hide table columns. Your choice is saved in this browser.">
                                <i class="fa fa-columns me-1"></i> Columns
                            </button>
                            <div class="dropdown-menu dropdown-menu-end p-2" id="amzColumnsMenu" style="min-width: 240px; max-height: 70vh; overflow-y: auto;">
                                <div class="d-flex gap-1 mb-2">
                                    <button type="button" class="btn btn-outline-secondary btn-sm flex-fill" id="amzColsShowAll">Show all</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm flex-fill" id="amzColsHideAll">Hide all</button>
                                </div>
                                <div id="amzColumnsList"></div>
                                <div class="text-muted small mt-2">Saved automatically for this browser.</div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-success btn-sm" id="amzMissingExportBtn" title="Export the current (filtered) view to CSV">
                            <i class="fa fa-download me-1"></i> Export
                        </button>
                    </div>
                    <div id="amzAdsMissingTable"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('modal')
    <div class="modal fade" id="amzHlModal" tabindex="-1" aria-labelledby="amzHlModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="amzHlModalLabel">Create / Update Amz HL Campaign</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2 small mb-3">
                        <strong>CT</strong> creates <strong>one PAUSED</strong> Sponsored Brands (HL) campaign for the parent
                        (e.g. <code>PARENT PMX HL</code>). Brand name, logo, headline and landing page are copied from an existing HL campaign of this account.
                        <strong>UP</strong> adds the selected child ASINs to the creative of an HL campaign already linked to this parent.
                    </div>
                    <input type="hidden" id="amzHlParent">
                    <div id="amzHlModeBox" class="border rounded p-2 mb-2 small d-none">
                        <div class="d-flex flex-wrap align-items-center gap-3">
                            <label class="mb-0"><input type="radio" class="form-check-input me-1" name="amzHlMode" value="new" checked>Create new campaign (CT)</label>
                            <label class="mb-0"><input type="radio" class="form-check-input me-1" name="amzHlMode" value="update">Update existing campaign (UP)</label>
                            <select class="form-select form-select-sm w-auto d-none" id="amzHlUpdateCampaign"></select>
                        </div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-md-4">
                            <label class="form-label small mb-1">Parent</label>
                            <input type="text" class="form-control form-control-sm" id="amzHlParentDisplay" readonly>
                        </div>
                        <div class="col-md-8 amz-hl-new-only">
                            <label class="form-label small mb-1">Campaign name</label>
                            <input type="text" class="form-control form-control-sm" id="amzHlCampaignName" maxlength="128" placeholder="PARENT PMX HL">
                        </div>
                        <div class="col-md-4 amz-hl-new-only">
                            <label class="form-label small mb-1">Daily budget ($)</label>
                            <input type="number" class="form-control form-control-sm" id="amzHlBudget" min="1" step="0.01" value="3">
                        </div>
                        <div class="col-md-8 amz-hl-new-only">
                            <label class="form-label small mb-1">Headline <span class="text-muted">(optional - leave empty to keep the template's)</span></label>
                            <input type="text" class="form-control form-control-sm" id="amzHlHeadline" maxlength="50">
                        </div>
                    </div>
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <label class="form-label small mb-0 fw-semibold">Child SKUs on the HL creative <span class="text-muted fw-normal" id="amzHlChildCount"></span></label>
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-secondary" id="amzHlSelectAll">Select all</button>
                            <button type="button" class="btn btn-outline-secondary" id="amzHlSelectNone">Select none</button>
                        </div>
                    </div>
                    <div class="table-responsive border rounded" style="max-height: 280px;">
                        <table class="table table-sm table-hover mb-0 align-middle">
                            <thead class="table-light sticky-top">
                                <tr><th style="width:36px;"></th><th>Seller SKU</th><th>ASIN</th><th style="width:60px;">Inv</th></tr>
                            </thead>
                            <tbody id="amzHlChildrenBody"></tbody>
                        </table>
                    </div>
                    <div class="amz-hl-new-only mt-3">
                        <label class="form-label small mb-1 fw-semibold">Keywords <span class="text-muted fw-normal">(optional, one per line; added to the campaign as enabled keywords)</span></label>
                        <textarea class="form-control form-control-sm" id="amzHlKeywords" rows="4" placeholder="keyword one&#10;keyword two"></textarea>
                        <div class="row g-2 mt-1">
                            <div class="col-md-4">
                                <select class="form-select form-select-sm" id="amzHlMatch">
                                    <option value="PHRASE" selected>Phrase</option>
                                    <option value="BROAD">Broad</option>
                                    <option value="EXACT">Exact</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <input type="number" class="form-control form-control-sm" id="amzHlBid" min="0.1" step="0.01" value="0.60" title="Bid ($)">
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-danger py-2 small mt-3 mb-0 d-none" id="amzHlError"></div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary btn-sm" id="amzHlSubmitBtn"><i class="fa fa-plus me-1"></i> Create campaign</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="amzCreateCampaignModal" tabindex="-1" aria-labelledby="amzCreateCampaignModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="amzCreateCampaignModalLabel">Create Amz SP Campaign (KW / MANUAL)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2 small mb-3" id="amzCreateCampaignHint">
                        Creates <strong>one PAUSED</strong> Sponsored Products <strong>KW / MANUAL</strong> campaign for the parent
                        (e.g. <code>PARENT PMX KW</code>). Selected child SKUs become product ads
                        (Amz seller rule: ads use seller <code>sku</code>; ASIN is shown for verification).
                        Campaign negative keywords support Phrase / Exact only (not Broad).
                    </div>
                    <form id="amzCreateCampaignForm">
                        <input type="hidden" id="amzCreateParent" name="parent">
                        <input type="hidden" id="amzCreateType" name="type" value="KW">
                        {{-- Shown only when this parent already has a linked campaign of this type: add keywords / targets to it instead of creating a new one. --}}
                        <div id="amzModeBox" class="border rounded p-2 mb-2 small d-none">
                            <div class="d-flex flex-wrap align-items-center gap-3">
                                <label class="mb-0"><input type="radio" class="form-check-input me-1" name="amzCreateMode" value="new" checked>Create new campaign</label>
                                <label class="mb-0"><input type="radio" class="form-check-input me-1" name="amzCreateMode" value="update">Update existing campaign</label>
                                <select class="form-select form-select-sm w-auto d-none" id="amzUpdateCampaign"></select>
                            </div>
                            <div class="text-muted mt-1 d-none" id="amzUpdateHint">
                                Adds the keywords / ASIN targets below to the selected campaign, or applies the auto targeting group changes (duplicates are skipped). Budget, strategy and product ads are not changed; the selected child SKUs are only used to load Amazon's suggestions.
                            </div>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-md-4">
                                <label class="form-label small mb-1">Parent</label>
                                <input type="text" class="form-control form-control-sm" id="amzCreateParentDisplay" readonly>
                            </div>
                            <div class="col-md-8 amz-new-only">
                                <label class="form-label small mb-1">Campaign name</label>
                                <input type="text" class="form-control form-control-sm" id="amzCreateCampaignName" name="campaign_name" required placeholder="PARENT PMX KW" maxlength="128">
                            </div>
                            <div class="col-md-4 amz-new-only">
                                <label class="form-label small mb-1">Daily budget ($)</label>
                                <input type="number" class="form-control form-control-sm" id="amzCreateBudget" name="budget_amount" min="1" step="0.01" value="3">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small mb-1">Default bid ($)</label>
                                <input type="number" class="form-control form-control-sm" id="amzCreateDefaultBid" name="default_bid" min="0.02" step="0.01" value="0.60">
                            </div>
                            <div class="col-md-4 amz-new-only">
                                <label class="form-label small mb-1">Campaign bidding strategy</label>
                                <select class="form-select form-select-sm" id="amzCreateStrategy" name="bidding_strategy">
                                    <option value="LEGACY_FOR_SALES">Dynamic bids - down only</option>
                                    <option value="AUTO_FOR_SALES">Dynamic bids - up and down</option>
                                    <option value="MANUAL">Fixed bids</option>
                                </select>
                            </div>
                            <div class="col-md-4 amz-new-only">
                                <label class="form-label small mb-1">Top of search (first page) +%</label>
                                <input type="number" class="form-control form-control-sm" id="amzCreateTopPct" name="top_of_search_pct" min="0" max="900" step="1" value="0">
                            </div>
                            <div class="col-md-4 amz-new-only">
                                <label class="form-label small mb-1">Product pages +%</label>
                                <input type="number" class="form-control form-control-sm" id="amzCreateProdPct" name="product_pages_pct" min="0" max="900" step="1" value="0">
                            </div>
                        </div>

                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <label class="form-label small mb-0 fw-semibold">
                                Child SKUs in this campaign
                                <span class="text-muted fw-normal" id="amzCreateChildCount"></span>
                            </label>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-secondary" id="amzCreateSelectAll">Select all</button>
                                <button type="button" class="btn btn-outline-secondary" id="amzCreateSelectNone">Select none</button>
                            </div>
                        </div>
                        <div class="table-responsive border rounded" style="max-height: 320px;">
                            <table class="table table-sm table-hover mb-0 align-middle" id="amzCreateChildrenTable">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th style="width:36px;"></th>
                                        <th style="min-width:140px;">Seller SKU</th>
                                        <th style="min-width:140px;">ASIN</th>
                                        <th style="width:60px;">Inv</th>
                                    </tr>
                                </thead>
                                <tbody id="amzCreateChildrenBody"></tbody>
                            </table>
                        </div>
                        <input type="hidden" id="amzCreateTargetSku" value="">

                        {{-- Keyword targeting: same flow as the Amazon console (Suggested / Enter list / Upload file). KW campaigns only. --}}
                        <div id="amzKwSection" class="mt-3">
                            <div class="fw-semibold small mb-1">
                                Keyword targeting
                                <span class="text-muted fw-normal">— keywords come from Amazon's own suggestions, your list or a file (no AI)</span>
                            </div>
                            <div class="row g-2">
                                <div class="col-lg-7">
                                    <div class="border rounded">
                                        <ul class="nav nav-tabs small px-2 pt-1" role="tablist">
                                            <li class="nav-item"><button type="button" class="nav-link active py-1 px-2" data-bs-toggle="tab" data-bs-target="#amzKwPaneSug">Suggested</button></li>
                                            <li class="nav-item"><button type="button" class="nav-link py-1 px-2" data-bs-toggle="tab" data-bs-target="#amzKwPaneIdeas">Search ideas</button></li>
                                            <li class="nav-item"><button type="button" class="nav-link py-1 px-2" data-bs-toggle="tab" data-bs-target="#amzKwPaneList">Enter list</button></li>
                                            <li class="nav-item"><button type="button" class="nav-link py-1 px-2" data-bs-toggle="tab" data-bs-target="#amzKwPaneFile">Upload file</button></li>
                                        </ul>
                                        <div class="p-2 border-bottom d-flex flex-wrap align-items-center gap-2 small">
                                            <span class="fw-semibold">Bid</span>
                                            <select class="form-select form-select-sm w-auto" id="amzKwBidMode">
                                                <option value="suggested">Suggested bid</option>
                                                <option value="custom">Custom bid</option>
                                                <option value="default">Default bid</option>
                                            </select>
                                            <input type="number" class="form-control form-control-sm d-none" style="width:90px;" id="amzKwCustomBid" min="0.02" step="0.01" value="0.60" placeholder="$">
                                            <span class="text-muted" id="amzKwBidHint">Amazon's suggested bid per match type (default bid if none).</span>
                                        </div>
                                        <div class="tab-content">
                                            <div class="tab-pane fade show active" id="amzKwPaneSug">
                                                <div class="p-2 d-flex flex-wrap align-items-center gap-3 small border-bottom">
                                                    <span class="fw-semibold">Filter by</span>
                                                    <label class="form-check-label"><input type="checkbox" class="form-check-input me-1 amz-kw-filter" value="BROAD" checked>Broad</label>
                                                    <label class="form-check-label"><input type="checkbox" class="form-check-input me-1 amz-kw-filter" value="PHRASE" checked>Phrase</label>
                                                    <label class="form-check-label"><input type="checkbox" class="form-check-input me-1 amz-kw-filter" value="EXACT" checked>Exact</label>
                                                    <span class="fw-semibold ms-2">Sort by</span>
                                                    <select class="form-select form-select-sm w-auto" id="amzKwSort">
                                                        <option value="CONVERSIONS">Orders</option>
                                                        <option value="CLICKS">Clicks</option>
                                                        <option value="DEFAULT">Relevance</option>
                                                    </select>
                                                    <input type="text" class="form-control form-control-sm" style="width:150px;" id="amzKwSugSearch" placeholder="Search keywords">
                                                    <button type="button" class="btn btn-outline-secondary btn-sm ms-auto" id="amzKwLoadBtn"><i class="fa fa-sync me-1"></i>Reload</button>
                                                </div>
                                                <div class="px-2 py-1 small text-muted d-flex justify-content-between">
                                                    <span id="amzKwSugStatus">Select child SKUs, then load Amazon's suggestions.</span>
                                                    <a href="#" id="amzKwAddAll" class="fw-semibold">Add all</a>
                                                </div>
                                                <div style="max-height:300px; overflow:auto;">
                                                    <table class="table table-sm align-middle mb-0 small">
                                                        <thead class="table-light sticky-top"><tr><th>Keyword <span class="text-muted fw-normal">IS | IR</span></th><th>Match type</th><th class="text-end">Sugg. bid <span class="text-muted fw-normal">(range)</span></th><th class="text-end"></th></tr></thead>
                                                        <tbody id="amzKwSugBody"></tbody>
                                                    </table>
                                                </div>
                                            </div>
                                            <div class="tab-pane fade" id="amzKwPaneIdeas">
                                                <div class="p-2 small border-bottom">
                                                    <div class="input-group input-group-sm">
                                                        <input type="text" class="form-control" id="amzKwSeed" placeholder="Type keywords (comma separated), e.g. woofer, 15 inch subwoofer">
                                                        <button type="button" class="btn btn-primary" id="amzKwSeedBtn"><i class="fa fa-search me-1"></i>Get ideas</button>
                                                    </div>
                                                    <div class="d-flex flex-wrap align-items-center gap-3 mt-2">
                                                        <span class="fw-semibold">Add as</span>
                                                        <label><input type="checkbox" class="form-check-input me-1 amz-kw-idea-mt" value="BROAD">Broad</label>
                                                        <label><input type="checkbox" class="form-check-input me-1 amz-kw-idea-mt" value="PHRASE" checked>Phrase</label>
                                                        <label><input type="checkbox" class="form-check-input me-1 amz-kw-idea-mt" value="EXACT">Exact</label>
                                                        <span class="text-muted">Ideas come from the Keyword Planner (same as Google Shopping). The bid is the Bid setting above.</span>
                                                    </div>
                                                </div>
                                                <div class="px-2 py-1 small text-muted d-flex justify-content-between">
                                                    <span id="amzKwIdeaStatus">Type a keyword and press Get ideas.</span>
                                                    <a href="#" id="amzKwIdeaAddAll" class="fw-semibold">Add all</a>
                                                </div>
                                                <div style="max-height:300px; overflow:auto;">
                                                    <table class="table table-sm align-middle mb-0 small">
                                                        <thead class="table-light sticky-top"><tr><th>Keyword</th><th class="text-end">Avg monthly</th><th>Competition</th><th class="text-end">Avg CPC</th><th></th></tr></thead>
                                                        <tbody id="amzKwIdeaBody"></tbody>
                                                    </table>
                                                </div>
                                            </div>
                                            <div class="tab-pane fade" id="amzKwPaneList">
                                                <div class="p-2 small">
                                                    <textarea class="form-control form-control-sm" id="amzKwListText" rows="7" placeholder="Enter one keyword per line (or separate with commas)"></textarea>
                                                    <div class="d-flex flex-wrap align-items-center gap-3 mt-2">
                                                        <span class="fw-semibold">Match types</span>
                                                        <label><input type="checkbox" class="form-check-input me-1 amz-kw-list-mt" value="BROAD">Broad</label>
                                                        <label><input type="checkbox" class="form-check-input me-1 amz-kw-list-mt" value="PHRASE" checked>Phrase</label>
                                                        <label><input type="checkbox" class="form-check-input me-1 amz-kw-list-mt" value="EXACT">Exact</label>
                                                        <button type="button" class="btn btn-primary btn-sm ms-auto" id="amzKwListAddBtn">Add keywords</button>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="tab-pane fade" id="amzKwPaneFile">
                                                <div class="p-2 small">
                                                    <input type="file" class="form-control form-control-sm" id="amzKwFile" accept=".csv,.txt,text/csv,text/plain">
                                                    <div class="form-text">One keyword per line, or a CSV (the first column is used; a "keyword" header row is skipped).</div>
                                                    <div class="d-flex flex-wrap align-items-center gap-3 mt-2">
                                                        <span class="fw-semibold">Match types</span>
                                                        <label><input type="checkbox" class="form-check-input me-1 amz-kw-file-mt" value="BROAD">Broad</label>
                                                        <label><input type="checkbox" class="form-check-input me-1 amz-kw-file-mt" value="PHRASE" checked>Phrase</label>
                                                        <label><input type="checkbox" class="form-check-input me-1 amz-kw-file-mt" value="EXACT">Exact</label>
                                                        <button type="button" class="btn btn-primary btn-sm ms-auto" id="amzKwFileAddBtn">Add keywords</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-5">
                                    <div class="border rounded h-100">
                                        <div class="p-2 border-bottom d-flex justify-content-between align-items-center small">
                                            <span class="fw-bold"><span id="amzKwAddedCount">0</span> added</span>
                                            <span class="d-flex align-items-center gap-1">
                                                <input type="number" class="form-control form-control-sm py-0" style="width:78px;" id="amzKwBulkBid" min="0.02" step="0.01" placeholder="Bid $">
                                                <a href="#" id="amzKwBulkBidApply" title="Set this bid for every added keyword">Set all</a>
                                                <a href="#" id="amzKwClearAll" class="text-danger ms-2">Remove all</a>
                                            </span>
                                        </div>
                                        <div style="max-height:372px; overflow:auto;">
                                            <table class="table table-sm align-middle mb-0 small">
                                                <thead class="table-light sticky-top"><tr><th>Keyword</th><th>Match</th><th style="width:84px;">Bid ($)</th><th style="width:28px;"></th></tr></thead>
                                                <tbody id="amzKwAddedBody"></tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Product targeting for PT campaigns: same two-panel layout as Keyword targeting. --}}
                        <div id="amzPtSection" class="mt-3 d-none">
                            <div class="fw-semibold small mb-1">
                                Product targeting
                                <span class="text-muted fw-normal">— Amazon's suggested products, its automatic groups, your list or a file</span>
                            </div>
                            <div class="row g-2">
                                <div class="col-lg-7">
                                    <div class="border rounded">
                                        <ul class="nav nav-tabs small px-2 pt-1" role="tablist" id="amzPtTabs">
                                            <li class="nav-item"><button type="button" class="nav-link active py-1 px-2" data-bs-toggle="tab" data-bs-target="#amzPtPaneSug" data-pt-mode="MANUAL">Suggested</button></li>
                                            <li class="nav-item"><button type="button" class="nav-link py-1 px-2" data-bs-toggle="tab" data-bs-target="#amzPtPaneAuto" data-pt-mode="AUTO">Auto groups</button></li>
                                            <li class="nav-item"><button type="button" class="nav-link py-1 px-2" data-bs-toggle="tab" data-bs-target="#amzPtPaneList" data-pt-mode="MANUAL">Enter list</button></li>
                                            <li class="nav-item"><button type="button" class="nav-link py-1 px-2" data-bs-toggle="tab" data-bs-target="#amzPtPaneFile" data-pt-mode="MANUAL">Upload file</button></li>
                                        </ul>
                                        <div class="p-2 border-bottom d-flex flex-wrap align-items-center gap-2 small">
                                            <span class="fw-semibold">Bid</span>
                                            <select class="form-select form-select-sm w-auto" id="amzPtBidMode">
                                                <option value="default">Default bid</option>
                                                <option value="custom">Custom bid</option>
                                            </select>
                                            <input type="number" class="form-control form-control-sm d-none" style="width:90px;" id="amzPtBid" min="0.02" step="0.01" value="0.60" placeholder="$">
                                            <span class="text-muted" id="amzPtBidHint">The default bid of the campaign, used for what you add next.</span>
                                        </div>
                                        <div class="tab-content">
                                            <div class="tab-pane fade show active" id="amzPtPaneSug">
                                                <div class="p-2 d-flex flex-wrap align-items-center gap-3 small border-bottom">
                                                    <input type="text" class="form-control form-control-sm" style="width:180px;" id="amzPtSugSearch" placeholder="Search ASIN or title">
                                                    <button type="button" class="btn btn-outline-secondary btn-sm ms-auto" id="amzPtLoadBtn"><i class="fa fa-sync me-1"></i>Reload</button>
                                                </div>
                                                <div class="px-2 py-1 small text-muted d-flex justify-content-between">
                                                    <span id="amzPtSugStatus">Select child SKUs, then load Amazon's suggestions.</span>
                                                    <a href="#" id="amzPtSugAddAll" class="fw-semibold">Add all</a>
                                                </div>
                                                <div style="max-height:300px; overflow:auto;">
                                                    <table class="table table-sm align-middle mb-0 small">
                                                        <thead class="table-light sticky-top"><tr><th>Product</th><th>Why Amazon suggests it</th><th class="text-end"></th></tr></thead>
                                                        <tbody id="amzPtSugBody"></tbody>
                                                    </table>
                                                </div>
                                            </div>
                                            <div class="tab-pane fade" id="amzPtPaneAuto">
                                                <div class="px-2 py-1 small text-muted d-flex justify-content-between">
                                                    <span>Amazon matches shoppers automatically. Groups you remove from the list on the right are paused.</span>
                                                    <a href="#" id="amzPtAutoAddAll" class="fw-semibold">Add all</a>
                                                </div>
                                                <div style="max-height:300px; overflow:auto;">
                                                    <table class="table table-sm align-middle mb-0 small">
                                                        <thead class="table-light sticky-top"><tr><th>Targeting group</th><th class="text-end"></th></tr></thead>
                                                        <tbody id="amzPtAutoBody"></tbody>
                                                    </table>
                                                </div>
                                            </div>
                                            <div class="tab-pane fade" id="amzPtPaneList">
                                                <div class="p-2 small">
                                                    <textarea class="form-control form-control-sm" id="amzPtAsinText" rows="7" placeholder="Enter one ASIN per line (or separate with commas)"></textarea>
                                                    <div class="d-flex flex-wrap align-items-center gap-3 mt-2">
                                                        <span class="text-muted">ASIN targets need a MANUAL campaign: adding ASINs here creates a manual product-targeting campaign.</span>
                                                        <button type="button" class="btn btn-primary btn-sm ms-auto" id="amzPtAddBtn">Add ASINs</button>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="tab-pane fade" id="amzPtPaneFile">
                                                <div class="p-2 small">
                                                    <input type="file" class="form-control form-control-sm" id="amzPtFile" accept=".csv,.txt,text/csv,text/plain">
                                                    <div class="form-text">One ASIN per line, or a CSV (all 10-character ASINs in the file are added).</div>
                                                    <div class="d-flex flex-wrap align-items-center gap-3 mt-2">
                                                        <button type="button" class="btn btn-primary btn-sm ms-auto" id="amzPtFileAddBtn">Add ASINs</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-5">
                                    <div class="border rounded h-100">
                                        <div class="p-2 border-bottom d-flex justify-content-between align-items-center small">
                                            <span class="fw-bold"><span id="amzPtAddedCount">0</span> added</span>
                                            <span class="d-flex align-items-center gap-1">
                                                <input type="number" class="form-control form-control-sm py-0" style="width:78px;" id="amzPtBulkBid" min="0.02" step="0.01" placeholder="Bid $">
                                                <a href="#" id="amzPtBulkBidApply" title="Set this bid for every added target">Set all</a>
                                                <a href="#" id="amzPtClearAll" class="text-danger ms-2">Remove all</a>
                                            </span>
                                        </div>
                                        <div style="max-height:372px; overflow:auto;">
                                            <table class="table table-sm align-middle mb-0 small">
                                                <thead class="table-light sticky-top"><tr><th>Target</th><th>Type</th><th style="width:84px;">Bid ($)</th><th style="width:28px;"></th></tr></thead>
                                                <tbody id="amzPtAddedBody"></tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-text small mt-1" id="amzCreateTypeNote">
                            KW / MANUAL campaigns are created with the keywords you add above.
                        </div>
                    </form>
                    <div class="text-danger small mt-2 d-none" id="amzCreateError"></div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success btn-sm" id="amzCreateSubmitBtn">
                        <i class="fa fa-plus me-1"></i> Create campaign
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="amzKwListModal" tabindex="-1" aria-labelledby="amzKwListModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="amzKwListModalLabel">Keywords / targets</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2 small">
                        <select class="form-select form-select-sm w-auto d-none" id="amzKwListCampaign"></select>
                        <select class="form-select form-select-sm w-auto" id="amzKwListMatch">
                            <option value="">All match types</option>
                            <option value="BROAD">Broad</option>
                            <option value="PHRASE">Phrase</option>
                            <option value="EXACT">Exact</option>
                        </select>
                        <select class="form-select form-select-sm w-auto" id="amzKwListState">
                            <option value="">Enabled + paused</option>
                            <option value="ENABLED">Enabled</option>
                            <option value="PAUSED">Paused</option>
                        </select>
                        <input type="text" class="form-control form-control-sm" style="width:200px;" id="amzKwListSearch" placeholder="Search keywords">
                        <span class="text-muted" id="amzKwListSummary"></span>
                    </div>
                    <div class="text-danger small mb-2 d-none" id="amzKwListError"></div>
                    <div class="border rounded" style="max-height:60vh; overflow:auto;">
                        <table class="table table-sm table-hover align-middle mb-0 small">
                            <thead class="table-light sticky-top">
                                <tr><th style="width:50px;">#</th><th id="amzKwListThKeyword">Keyword</th><th id="amzKwListThMatch">Match</th><th class="text-end">Bid ($)</th><th>State</th><th class="amz-kwlist-camp-col">Campaign</th></tr>
                            </thead>
                            <tbody id="amzKwListBody"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="amzKwListRefreshBtn"><i class="fa fa-sync me-1"></i>Refresh</button>
                    <button type="button" class="btn btn-outline-success btn-sm" id="amzKwListCsvBtn"><i class="fa fa-download me-1"></i>Download CSV</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="amzAiNegModal" tabindex="-1" aria-labelledby="amzAiNegModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="amzAiNegModalLabel">AI Negative Keywords (Amz SP)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-2" id="amzAiNegSubtitle">Generating suggestions for this product…</p>
                    <div id="amzAiNegLoading" class="text-center py-4 d-none">
                        <i class="fa fa-spinner fa-spin fa-2x text-primary"></i>
                        <div class="small text-muted mt-2">Asking AI for negative keywords…</div>
                    </div>
                    <div id="amzAiNegError" class="alert alert-danger py-2 small d-none"></div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold mb-1" for="amzAiNegIdeas">Your ideas (optional)</label>
                        <textarea class="form-control form-control-sm" id="amzAiNegIdeas" rows="2"
                            placeholder="e.g. block karaoke, wedding DJ, DJ controller, karaoke mic…"></textarea>
                        <div class="form-text small">Add themes or sample negatives; AI will expand them into more keywords.</div>
                    </div>
                    <div id="amzAiNegExistingWrap" class="mb-3 d-none">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <div class="fw-semibold small">Already on Amz KW(-) for this parent</div>
                            <span class="badge text-bg-secondary" id="amzAiNegExistingCount">0</span>
                        </div>
                        <div id="amzAiNegExisting" class="border rounded p-2 small bg-light" style="max-height:120px;overflow:auto;"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold mb-1" for="amzAiNegManualInput">Add manual negative keyword</label>
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control" id="amzAiNegManualInput"
                                placeholder="Type a keyword and press Add (or Enter)">
                            <button type="button" class="btn btn-outline-success" id="amzAiNegManualAddBtn" title="Add to list">
                                <i class="fa fa-plus me-1"></i> Add
                            </button>
                        </div>
                    </div>
                    <div id="amzAiNegSuggestedWrap" class="d-none">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <div class="fw-semibold small">
                                Negatives to push
                                <span class="badge text-bg-danger ms-1" id="amzAiNegSuggestedCount">0</span>
                            </div>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-secondary" id="amzAiNegCopyBtn" title="Copy all">
                                    <i class="fa fa-copy me-1"></i> Copy
                                </button>
                                <button type="button" class="btn btn-outline-secondary" id="amzAiNegExportBtn" title="Export keywords CSV">
                                    <i class="fa fa-file-csv me-1"></i> Export CSV
                                </button>
                            </div>
                        </div>
                        <ul id="amzAiNegSuggested" class="list-group list-group-flush border rounded" style="max-height:280px;overflow:auto;"></ul>
                    </div>
                    <div class="border rounded p-2 mt-3 bg-light" id="amzAiNegPushWrap">
                        <div class="fw-semibold small mb-2">Push to Amz SP campaign (campaign-level)</div>
                        <div class="row g-2 align-items-end">
                            <div class="col-md-5">
                                <label class="form-label small mb-1" for="amzAiNegMatchType">Match type</label>
                                <select class="form-select form-select-sm" id="amzAiNegMatchType">
                                    <option value="PHRASE" selected>Phrase (NEGATIVE_PHRASE)</option>
                                    <option value="EXACT">Exact (NEGATIVE_EXACT)</option>
                                </select>
                            </div>
                            <div class="col-md-7">
                                <div class="form-check mt-3">
                                    <input class="form-check-input" type="checkbox" id="amzAiNegIncludeExisting" checked>
                                    <label class="form-check-label small" for="amzAiNegIncludeExisting">
                                        Also push existing Amz KW(-) negatives for this parent
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="form-text small mt-1">
                            Pushes campaign negatives to the PT/AUTO campaign for this parent (create it first). Broad is not available at campaign level on Amz.
                        </div>
                        <div class="text-success small mt-2 d-none" id="amzAiNegPushOk"></div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="amzAiNegAddMoreBtn" title="Generate more using your ideas">
                        <i class="fa fa-plus me-1"></i> Add more from ideas
                    </button>
                    <button type="button" class="btn btn-primary btn-sm" id="amzAiNegRegenBtn">
                        <i class="fa fa-sync me-1"></i> Regenerate
                    </button>
                    <button type="button" class="btn btn-success btn-sm" id="amzAiNegPushBtn" title="Push negatives to Amz Ads">
                        <i class="fa fa-cloud-upload-alt me-1"></i> Push Negative Keywords
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="amzAiPosModal" tabindex="-1" aria-labelledby="amzAiPosModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="amzAiPosModalLabel">AI Positive Keywords (Amz SP)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-2">Suggest bid-on keywords for a MANUAL / KW campaign.</p>
                    <div id="amzAiPosLoading" class="text-center py-4 d-none">
                        <i class="fa fa-spinner fa-spin fa-2x text-success"></i>
                        <div class="small text-muted mt-2">Asking AI for positive keywords…</div>
                    </div>
                    <div id="amzAiPosError" class="alert alert-danger py-2 small d-none"></div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold mb-1" for="amzAiPosIdeas">Your ideas (optional)</label>
                        <textarea class="form-control form-control-sm" id="amzAiPosIdeas" rows="2"
                            placeholder="e.g. pa horn, portable horn speaker, outdoor megaphone…"></textarea>
                        <div class="form-text small">Add themes; AI will expand them into high-intent positives.</div>
                    </div>
                    <div id="amzAiPosExistingWrap" class="mb-3 d-none">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <div class="fw-semibold small">Already on Amz KW(+) for this parent</div>
                            <span class="badge text-bg-secondary" id="amzAiPosExistingCount">0</span>
                        </div>
                        <div id="amzAiPosExisting" class="border rounded p-2 small bg-light" style="max-height:120px;overflow:auto;"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold mb-1" for="amzAiPosManualInput">Add manual positive keyword</label>
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control" id="amzAiPosManualInput"
                                placeholder="Type a keyword and press Add (or Enter)">
                            <button type="button" class="btn btn-outline-success" id="amzAiPosManualAddBtn" title="Add to list">
                                <i class="fa fa-plus me-1"></i> Add
                            </button>
                        </div>
                    </div>
                    <div id="amzAiPosSuggestedWrap" class="d-none">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <div class="fw-semibold small">
                                Positives to push
                                <span class="badge text-bg-success ms-1" id="amzAiPosSuggestedCount">0</span>
                            </div>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-secondary" id="amzAiPosCopyBtn" title="Copy all">
                                    <i class="fa fa-copy me-1"></i> Copy
                                </button>
                                <button type="button" class="btn btn-outline-secondary" id="amzAiPosExportBtn" title="Export keywords CSV">
                                    <i class="fa fa-file-csv me-1"></i> Export CSV
                                </button>
                            </div>
                        </div>
                        <ul id="amzAiPosSuggested" class="list-group list-group-flush border rounded" style="max-height:280px;overflow:auto;"></ul>
                    </div>
                    <div class="border rounded p-2 mt-3 bg-light">
                        <div class="fw-semibold small mb-2">Push to Amz SP ad group (positive keywords)</div>
                        <div class="row g-2 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label small mb-1" for="amzAiPosMatchType">Match type</label>
                                <select class="form-select form-select-sm" id="amzAiPosMatchType">
                                    <option value="PHRASE" selected>Phrase</option>
                                    <option value="BROAD">Broad</option>
                                    <option value="EXACT">Exact</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-1" for="amzAiPosBid">Bid ($)</label>
                                <input type="number" class="form-control form-control-sm" id="amzAiPosBid" min="0.02" step="0.01" value="0.50">
                            </div>
                            <div class="col-md-5">
                                <div class="form-check mt-3">
                                    <input class="form-check-input" type="checkbox" id="amzAiPosIncludeExisting" checked>
                                    <label class="form-check-label small" for="amzAiPosIncludeExisting">
                                        Also push existing Amz KW(+) for this parent
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="form-text small mt-1">
                            Requires a KW/MANUAL campaign. Duplicates are skipped.
                        </div>
                        <div class="text-success small mt-2 d-none" id="amzAiPosPushOk"></div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-outline-success btn-sm" id="amzAiPosAddMoreBtn">
                        <i class="fa fa-plus me-1"></i> Add more from ideas
                    </button>
                    <button type="button" class="btn btn-success btn-sm" id="amzAiPosRegenBtn">
                        <i class="fa fa-sync me-1"></i> Regenerate
                    </button>
                    <button type="button" class="btn btn-primary btn-sm" id="amzAiPosPushBtn">
                        <i class="fa fa-cloud-upload-alt me-1"></i> Push Positive Keywords
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        (function () {
            var missingDataUrl = @json(route('amazon.ads.missing.data'));
            var campaignsUrl = @json(route('amazon.ads.missing.campaigns'));
            var linkUrl = @json(route('amazon.ads.missing.link'));
            var unlinkUrl = @json(route('amazon.ads.missing.unlink'));
            var createUrl = @json(route('amazon.ads.missing.create'));
            var updateUrl = @json(route('amazon.ads.missing.update-campaign'));
            var kwCountUrl = @json(route('amazon.ads.missing.keyword-counts'));
            var kwListUrl = @json(route('amazon.ads.missing.keyword-list'));
            // KW K Count / PT Count columns: key (campaign id, or "n:<name>" when only the name is linked) => {total, enabled, paused}
            var cntState = {
                kw: { map: {}, loading: false, field: 'kw' },
                pt: { map: {}, loading: false, field: 'pt' },
                hl: { map: {}, loading: false, field: 'hl' }
            };
            // Counts survive a page reload: kept in localStorage for 30 minutes (same as the server-side cache).
            var CNT_STORE_KEY = 'amzMissingCounts.v1';
            var CNT_TTL_MS = 30 * 60 * 1000;
            var cntTs = { kw: {}, pt: {}, hl: {} };
            function cntHydrate() {
                try {
                    var raw = JSON.parse(window.localStorage.getItem(CNT_STORE_KEY) || '{}');
                    var now = Date.now();
                    ['kw', 'pt', 'hl'].forEach(function (kind) {
                        var src = raw[kind] || {};
                        Object.keys(src).forEach(function (k) {
                            var e = src[k];
                            if (e && e.t && e.t.total != null && now - e.ts < CNT_TTL_MS) {
                                cntState[kind].map[k] = e.t;
                                cntTs[kind][k] = e.ts;
                            }
                        });
                    });
                } catch (err) { /* storage unavailable or corrupt: just reload from Amazon */ }
            }
            function cntPersist(kind, keys) {
                try {
                    var raw = JSON.parse(window.localStorage.getItem(CNT_STORE_KEY) || '{}');
                    raw[kind] = raw[kind] || {};
                    var now = Date.now();
                    (keys || Object.keys(cntState[kind].map)).forEach(function (k) {
                        var t = cntState[kind].map[k];
                        if (t && t.total != null) { raw[kind][k] = { t: t, ts: now }; cntTs[kind][k] = now; }
                    });
                    window.localStorage.setItem(CNT_STORE_KEY, JSON.stringify(raw));
                } catch (err) { /* ignore quota / private mode */ }
            }
            cntHydrate();
            function kwRefKey(c) { return c && c.campaign_id ? String(c.campaign_id) : 'n:' + String((c && c.campaign_name) || ''); }
            function kwRowRefs(d, kind) {
                return (Array.isArray(d && d[cntState[kind].field]) ? d[cntState[kind].field] : []).map(function (c) {
                    return { key: kwRefKey(c), campaign_id: c.campaign_id ? String(c.campaign_id) : '', campaign_name: c.campaign_name || '' };
                }).filter(function (r) { return r.key !== 'n:'; });
            }
            function kwCountForRow(d, kind) {
                var refs = kwRowRefs(d, kind);
                if (!refs.length) { return null; }
                var sum = 0;
                for (var i = 0; i < refs.length; i++) {
                    var c = cntState[kind].map[refs[i].key];
                    if (!c || c.total == null) { return null; }
                    sum += c.total;
                }
                return sum;
            }
            // Column definition shared by "KW K Count" (keywords) and "PT Count" (targets).
            function countColumnDef(kind, title, tip) {
                return {
                    title: title, field: '_' + kind + '_count', width: 110,
                    hozAlign: 'center', headerHozAlign: 'center',
                    headerSort: true,
                    sorter: function (a, b, aRow, bRow) {
                        var x = kwCountForRow(aRow.getData(), kind), y = kwCountForRow(bRow.getData(), kind);
                        x = x == null ? -1 : x; y = y == null ? -1 : y;
                        return x - y;
                    },
                    headerTooltip: tip,
                    formatter: function (cell) {
                        var d = cell.getData();
                        var st = cntState[kind];
                        var refs = kwRowRefs(d, kind);
                        if (!refs.length) { return '<span class="text-muted">—</span>'; }
                        var n = kwCountForRow(d, kind);
                        var attrs = ' data-kind="' + kind + '" data-sku="' + esc(d.sku || '') + '"';
                        if (n == null) {
                            var bad = refs.some(function (r) { return st.map[r.key] && st.map[r.key].unresolved; });
                            if (bad) { return '<a href="#" class="amz-kwcount text-muted"' + attrs + ' title="No Amazon campaign id found - click to try">?</a>'; }
                            return st.loading
                                ? '<i class="fa fa-spinner fa-spin text-muted" title="Loading from Amazon…"></i>'
                                : '<a href="#" class="amz-kwcount text-muted"' + attrs + ' title="Click to load from Amazon"><i class="fa fa-sync"></i></a>';
                        }
                        var en = 0, pa = 0;
                        refs.forEach(function (r) { var c = st.map[r.key] || {}; en += c.enabled || 0; pa += c.paused || 0; });
                        return '<a href="#" class="amz-kwcount fw-semibold"' + attrs + ' title="Enabled ' + en + ' / Paused ' + pa + ' - click to view">'
                            + Number(n).toLocaleString('en-US') + '</a>';
                    },
                    accessorDownload: function (value, data) { var n = kwCountForRow(data, kind); return n == null ? '' : n; }
                };
            }
            var kwSuggestUrl = @json(route('amazon.ads.missing.keyword-suggestions'));
            var kwIdeasUrl = @json(route('amazon.ads.missing.keyword-ideas'));
            var csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
            var lastCreatedCampaignId = '';
            var lastCreatedAdGroupId = '';
            var lastCreateRowData = null;
            var aiNegSuggestedCache = [];
            var aiNegExistingCache = [];
            var aiNegMeta = { parent: '', target_sku: '', product_title: '' };
            var aiPosSuggestedCache = [];
            var aiPosExistingCache = [];
            var aiPosMeta = { parent: '', target_sku: '', product_title: '' };

            function esc(str) {
                return String(str == null ? '' : str)
                    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            }

            function onlyParentsFilter(data) {
                return data.is_parent === true;
            }

            // Sort PT/KW columns by number of linked campaigns (blank rows first when ascending).
            function linkCountSorter(a, b) {
                var la = Array.isArray(a) ? a.length : 0;
                var lb = Array.isArray(b) ? b.length : 0;
                return la - lb;
            }

            // Header filter for Inventory: All / In Stock (>0) / Zero Inv (<=0).
            function inventoryHeaderFilter(headerValue, rowValue) {
                var inv = Number(rowValue) || 0;
                if (headerValue === 'in') {
                    return inv > 0;
                }
                if (headerValue === 'zero') {
                    return inv <= 0;
                }
                return true;
            }

            // Header filter for PT/KW: All / Missing (blank) / Linked.
            function missingHeaderFilter(headerValue, rowValue) {
                var len = Array.isArray(rowValue) ? rowValue.length : 0;
                if (headerValue === 'missing') {
                    return len === 0;
                }
                if (headerValue === 'linked') {
                    return len > 0;
                }
                return true;
            }

            // Flatten a PT/KW link array into a plain, comma-separated list of campaign names for CSV export.
            function linkNamesAccessor(value) {
                if (!Array.isArray(value)) {
                    return '';
                }
                return value.map(function (c) { return c && c.campaign_name ? c.campaign_name : ''; })
                    .filter(function (n) { return n !== ''; })
                    .join(', ');
            }

            function statusDot(c) {
                var dot = c && c.dot;
                if (dot !== 'green' && dot !== 'red') { return ''; }
                var status = c.status || (dot === 'green' ? 'ENABLED' : 'PAUSED');
                var title = dot === 'green' ? 'Enabled' : 'Paused';
                if (status) { title = status.charAt(0) + status.slice(1).toLowerCase(); }
                return '<span class="campaign-dot campaign-dot-' + dot + '" title="' + esc(title) + '"></span>';
            }

            function createdTooltip(c) {
                var when = (c && c.created_at) ? String(c.created_at) : '';
                var who = (c && c.created_by) ? String(c.created_by) : '';
                if (when && who) {
                    return 'Created ' + when + ' by ' + who;
                }
                if (when) {
                    return 'Created ' + when;
                }
                if (who) {
                    return 'Created by ' + who;
                }
                return 'Created on this page';
            }

            function createdCheck(c) {
                if (!c || !c.page_created) {
                    return '';
                }
                return ' <i class="fa fa-check-circle chip-created" title="' + esc(createdTooltip(c)) + '"></i>';
            }

            function chipsFormatter(type) {
                return function (cell) {
                    var d = cell.getData();
                    var list = (type === 'PT' ? d.pt : (type === 'HL' ? d.hl : d.kw)) || [];
                    var chips = list.map(function (c) {
                        // HL (Sponsored Brands) campaigns can be linked / unlinked here; archiving stays in Amazon Ads.
                        var canArchive = type !== 'HL' && !!(c && (c.campaign_id || c.campaign_name));
                        var chipTitle = c.page_created
                            ? (c.campaign_name + ' — ' + createdTooltip(c))
                            : c.campaign_name;
                        return '<span class="link-chip" title="' + esc(chipTitle) + '">'
                            + statusDot(c)
                            + esc(c.campaign_name)
                            + createdCheck(c)
                            + ' <i class="fa fa-times chip-x" title="Unlink only" data-id="' + c.id + '" data-sku="' + esc(d.sku) + '"></i>'
                            + (canArchive
                                ? ' <i class="fa fa-trash chip-trash" title="Archive campaign in Amz Ads"'
                                    + ' data-sku="' + esc(d.sku) + '"'
                                    + ' data-id="' + esc(c.id || '') + '"'
                                    + ' data-type="' + esc(type) + '"'
                                    + ' data-campaign-id="' + esc(c.campaign_id || '') + '"'
                                    + ' data-campaign-name="' + esc(c.campaign_name || '') + '"></i>'
                                : '')
                            + '</span>';
                    }).join('');
                    // Campaigns are linked automatically (created here, or named "PARENT … KW / PT" on Amazon): no manual + button.
                    return chips;
                };
            }

            // ---- Parent Vs Ads SKU KW / PT: Amazon Ads variation check (each child SKU of the parent is in the parent's KW / PT campaign) ----
            var amzPvaUrl = @json(route('amazon.ads.parent-vs-ads'));
            var amzPvaMap = null;       // UPPER(parent) -> {kw: {...}, pt: {...}}
            var amzPvaLoading = false;
            var amzPvaFailed = false;
            function amzPvaEsc(s) {
                return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            }
            function amzPvaNorm(s) { return String(s || '').replace(/\s+/g, ' ').trim().toUpperCase(); }
            function amzPvaLoad(force, done) {
                if (amzPvaLoading) { return; }
                if (amzPvaMap && !force) { if (done) { done(); } return; }
                amzPvaLoading = true;
                fetch(amzPvaUrl + (force ? '?refresh=1' : ''), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                }).then(function (res) { return res.json(); }).then(function (j) {
                    amzPvaMap = (j && j.data && typeof j.data === 'object') ? j.data : {};
                    amzPvaFailed = !(j && j.ok);
                }).catch(function () {
                    amzPvaMap = {};
                    amzPvaFailed = true;
                }).then(function () {
                    amzPvaLoading = false;
                    if (done) { done(); }
                });
            }
            function amzPvaList(f, k) { return Array.isArray(f && f[k]) ? f[k] : []; }
            function amzPvaHover(type, f) {
                var parts = [];
                var camps = amzPvaList(f, 'added_campaigns');
                var added = amzPvaList(f, 'added_skus');
                if (camps.length) { parts.push('Added campaigns (' + camps.length + '): ' + camps.join(', ')); }
                else if (added.length) { parts.push('Added SKUs (' + added.length + '): ' + added.join(', ')); }
                if (amzPvaList(f, 'missing_skus').length) { parts.push('Missing: ' + amzPvaList(f, 'missing_skus').join(', ')); }
                if (amzPvaList(f, 'over_skus').length) { parts.push('Over: ' + amzPvaList(f, 'over_skus').join(', ')); }
                if (amzPvaList(f, 'extra_skus').length) { parts.push('Extra: ' + amzPvaList(f, 'extra_skus').join(', ')); }
                if (amzPvaList(f, 'archived_extra_skus').length) { parts.push('Archived: ' + amzPvaList(f, 'archived_extra_skus').join(', ')); }
                return type.toUpperCase() + (parts.length ? ' — ' + parts.join(' | ') : ' siblings OK');
            }
            function amzPvaCellHtml(type, f) {
                if (!f || f.ad_status == null || !f.ad_label || f.ad_label === '—') { return '<span class="amz-pva-na">--</span>'; }
                var status = f.ad_status;
                var missing = parseInt(f.missing, 10) || 0;
                var extra = parseInt(f.extra, 10) || 0;
                var archivedExtra = parseInt(f.archived_extra, 10) || 0;
                var cls = 'amz-pva-missing';
                var main = amzPvaEsc(f.ad_label);
                if (status === 'ok') { cls = 'amz-pva-added'; main = '<i class="fas fa-check"></i> ' + main; }
                else if (status === 'extra' && missing === 0) { cls = 'amz-pva-extra'; }
                else if (status === 'archived_extra' && missing === 0 && extra === 0) { cls = 'amz-pva-archived'; }
                else if (status === 'over' && missing === 0 && extra === 0 && archivedExtra === 0) { cls = 'amz-pva-over'; }
                var lines = '';
                if (amzPvaList(f, 'missing_skus').length) { lines += '<span class="amz-pva-skus amz-pva-skus--missing">Missing: ' + amzPvaEsc(amzPvaList(f, 'missing_skus').join(', ')) + '</span>'; }
                if (amzPvaList(f, 'over_skus').length) { lines += '<span class="amz-pva-skus amz-pva-skus--over">Over: ' + amzPvaEsc(amzPvaList(f, 'over_skus').join(', ')) + '</span>'; }
                if (amzPvaList(f, 'extra_skus').length) { lines += '<span class="amz-pva-skus amz-pva-skus--extra">Extra: ' + amzPvaEsc(amzPvaList(f, 'extra_skus').join(', ')) + '</span>'; }
                if (amzPvaList(f, 'archived_extra_skus').length) { lines += '<span class="amz-pva-skus amz-pva-skus--archived">Archived: ' + amzPvaEsc(amzPvaList(f, 'archived_extra_skus').join(', ')) + '</span>'; }
                return '<span class="amz-pva-wrap" title="' + amzPvaEsc(amzPvaHover(type, f)) + '"><span class="' + cls + '">' + main + '</span>' + lines + '</span>';
            }
            // Cell content for a parent + type ('kw' | 'pt'); shows "…" until the summary has loaded.
            function amzPvaCell(parent, type) {
                if (!parent) { return '<span class="amz-pva-na">--</span>'; }
                if (amzPvaMap === null) { return '<span class="amz-pva-na">…</span>'; }
                var entry = amzPvaMap[amzPvaNorm(parent)];
                return amzPvaCellHtml(type, entry ? entry[type] : null);
            }

            // ---- Parent Vs Ads SKU HL: loaded from Amazon (linked HL campaigns' live SB ads) and kept fresh automatically ----
            var amzHlPvaUrl = @json(route('amazon.ads.missing.hl-parent-vs-ads'));
            var amzHlPvaMap = null;     // UPPER(parent) -> {hl: {...}}
            var amzHlPvaLoading = false;
            var amzHlPvaTable = null;
            function amzHlPvaLoad(force) {
                if (amzHlPvaLoading) { return; }
                amzHlPvaLoading = true;
                fetch(amzHlPvaUrl + (force ? '?refresh=1' : ''), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                }).then(function (res) { return res.json(); }).then(function (j) {
                    amzHlPvaMap = (j && j.data && typeof j.data === 'object') ? j.data : {};
                }).catch(function () {
                    if (amzHlPvaMap === null) { amzHlPvaMap = {}; }
                }).then(function () {
                    amzHlPvaLoading = false;
                    if (amzHlPvaTable) { amzHlPvaTable.getRows().forEach(function (r) { try { r.reformat(); } catch (e) {} }); }
                });
            }
            function amzHlPvaCell(parent) {
                if (!parent) { return '<span class="amz-pva-na">--</span>'; }
                if (amzHlPvaMap === null) { return '<span class="amz-pva-na">…</span>'; }
                var entry = amzHlPvaMap[amzPvaNorm(parent)];
                return amzPvaCellHtml('hl', entry ? entry.hl : null);
            }

            function postForm(url, payload) {
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
                    return res.json().then(function (b) { return { ok: res.ok, status: res.status, body: b }; })
                        .catch(function () { return { ok: false, status: res.status, body: {} }; });
                });
            }


            // ---- Keyword targeting (Amazon suggestions / entered list / uploaded file) for KW campaigns ----
            var kwSuggestions = [];   // [{keyword, is, ir, bids:{BROAD,PHRASE,EXACT}}] from Amazon
            var kwAdded = [];         // [{text, match_type, bid}]
            var kwSugSeq = 0;

            function kwDefaultBid() {
                return Math.max(0.02, parseFloat((document.getElementById('amzCreateDefaultBid') || {}).value) || 0.60);
            }
            function kwResolveBid(sugBid) {
                var mode = (document.getElementById('amzKwBidMode') || {}).value || 'suggested';
                var v = null;
                if (mode === 'suggested' && sugBid != null && isFinite(sugBid)) { v = Number(sugBid); }
                else if (mode === 'custom') { v = parseFloat((document.getElementById('amzKwCustomBid') || {}).value); }
                if (v == null || !isFinite(v)) { v = kwDefaultBid(); }
                return Math.max(0.02, Math.round(v * 100) / 100);
            }
            function kwFmtShare(v) {
                if (v == null || !isFinite(v)) { return '—'; }
                var pct = v <= 1 ? v * 100 : v;
                return pct.toFixed(2) + '%';
            }
            function kwRangeHtml(row, mt) {
                var r = row.ranges && row.ranges[mt];
                if (!r || (r[0] == null && r[1] == null)) { return ''; }
                return '<div class="text-muted" style="font-size:11px;">$' + (r[0] == null ? '—' : Number(r[0]).toFixed(2))
                    + ' - $' + (r[1] == null ? '—' : Number(r[1]).toFixed(2)) + '</div>';
            }
            function kwIsAdded(text, mt) {
                var k = String(text).toLowerCase() + '|' + mt;
                return kwAdded.some(function (a) { return (a.text.toLowerCase() + '|' + a.match_type) === k; });
            }
            function kwAdd(text, mt, bid) {
                text = String(text || '').replace(/\s+/g, ' ').trim();
                if (!text || kwIsAdded(text, mt)) { return false; }
                kwAdded.push({ text: text, match_type: mt, bid: bid });
                return true;
            }
            function kwActiveFilters() {
                var out = [];
                document.querySelectorAll('.amz-kw-filter').forEach(function (cb) { if (cb.checked) { out.push(cb.value); } });
                return out;
            }
            function kwRenderAdded() {
                var body = document.getElementById('amzKwAddedBody');
                if (!body) { return; }
                document.getElementById('amzKwAddedCount').textContent = String(kwAdded.length);
                body.innerHTML = kwAdded.map(function (k, i) {
                    return '<tr><td>' + esc(k.text) + '</td><td>' + esc(k.match_type.charAt(0) + k.match_type.slice(1).toLowerCase()) + '</td>'
                        + '<td><input type="number" class="form-control form-control-sm py-0 amz-kw-bid" data-i="' + i + '" min="0.02" step="0.01" value="' + Number(k.bid).toFixed(2) + '"></td>'
                        + '<td><a href="#" class="text-danger amz-kw-del" data-i="' + i + '" title="Remove">&times;</a></td></tr>';
                }).join('');
            }
            function kwRenderSuggestions() {
                var body = document.getElementById('amzKwSugBody');
                if (!body) { return; }
                var filters = kwActiveFilters();
                var order = ['BROAD', 'PHRASE', 'EXACT'];
                var html = '';
                var q = String((document.getElementById('amzKwSugSearch') || {}).value || '').toLowerCase().trim();
                kwSuggestions.forEach(function (row, ki) {
                    if (q && String(row.keyword).toLowerCase().indexOf(q) === -1) { return; }
                    var mts = order.filter(function (mt) { return filters.indexOf(mt) !== -1 && row.bids && row.bids[mt] != null; });
                    if (!mts.length) { return; }
                    mts.forEach(function (mt, j) {
                        var added = kwIsAdded(row.keyword, mt);
                        html += '<tr>';
                        if (j === 0) {
                            html += '<td rowspan="' + mts.length + '"><div class="fw-semibold">' + esc(row.keyword) + '</div>'
                                + '<div class="text-muted" style="font-size:11px;">IS: ' + esc(kwFmtShare(row.is)) + ' | IR: ' + esc(row.ir == null ? '—' : row.ir) + '</div></td>';
                        }
                        html += '<td>' + esc(mt.charAt(0) + mt.slice(1).toLowerCase()) + '</td>'
                            + '<td class="text-end">$' + Number(row.bids[mt]).toFixed(2) + kwRangeHtml(row, mt) + '</td>'
                            + '<td class="text-end">' + (added
                                ? '<span class="text-muted fst-italic">Added</span>'
                                : '<a href="#" class="fw-semibold amz-kw-add" data-ki="' + ki + '" data-mt="' + mt + '">Add</a>') + '</td></tr>';
                    });
                });
                body.innerHTML = html || '<tr><td colspan="4" class="text-muted text-center py-3">No suggestions to show.</td></tr>';
            }
            function kwSetStatus(msg, isErr) {
                var el = document.getElementById('amzKwSugStatus');
                if (!el) { return; }
                el.textContent = msg;
                el.classList.toggle('text-danger', !!isErr);
            }
            function kwLoadSuggestions(force) {
                var children = collectSelectedChildren();
                var btn = document.getElementById('amzKwLoadBtn');
                if (!children.length) {
                    kwSuggestions = [];
                    kwRenderSuggestions();
                    kwSetStatus('Select at least one child SKU with an ASIN to load Amazon suggestions.', false);
                    return;
                }
                var seq = ++kwSugSeq;
                kwSetStatus('Loading suggestions from Amazon…', false);
                if (btn) { btn.disabled = true; }
                postForm(kwSuggestUrl, {
                    children: children,
                    sort: (document.getElementById('amzKwSort') || {}).value || 'CONVERSIONS',
                    refresh: force === true
                }).then(function (out) {
                    if (seq !== kwSugSeq) { return; }
                    if (btn) { btn.disabled = false; }
                    if (out.ok && out.body && out.body.ok) {
                        kwSuggestions = Array.isArray(out.body.keywords) ? out.body.keywords : [];
                        kwSetStatus(kwSuggestions.length
                            ? (kwSuggestions.length + ' keywords suggested by Amazon.')
                            : (out.body.message || 'Amazon returned no suggestions.'), false);
                    } else {
                        kwSuggestions = [];
                        kwSetStatus((out.body && out.body.message) || ('Could not load Amazon suggestions (HTTP ' + out.status + '). If this is 404, run php artisan route:clear on the server.'), true);
                    }
                    kwRenderSuggestions();
                }).catch(function () {
                    if (seq !== kwSugSeq) { return; }
                    if (btn) { btn.disabled = false; }
                    kwSetStatus('Network error loading Amazon suggestions.', true);
                });
            }
            function kwResetForOpen() {
                ptResetForOpen();
                kwSuggestions = [];
                kwAdded = [];
                var sq = document.getElementById('amzKwSugSearch'); if (sq) { sq.value = ''; }
                var bb = document.getElementById('amzKwBulkBid'); if (bb) { bb.value = ''; }
                var st = document.getElementById('amzCreateStrategy'); if (st) { st.value = 'LEGACY_FOR_SALES'; }
                var tp = document.getElementById('amzCreateTopPct'); if (tp) { tp.value = '0'; }
                var pp = document.getElementById('amzCreateProdPct'); if (pp) { pp.value = '0'; }
                kwRenderSuggestions();
                kwRenderAdded();
                kwIdeas = [];
                kwRenderIdeas();
                var seedEl = document.getElementById('amzKwSeed'); if (seedEl) { seedEl.value = ''; }
                var ideaSt = document.getElementById('amzKwIdeaStatus'); if (ideaSt) { ideaSt.textContent = 'Type a keyword and press Get ideas.'; ideaSt.classList.remove('text-danger'); }
                var ta = document.getElementById('amzKwListText'); if (ta) { ta.value = ''; }
                var f = document.getElementById('amzKwFile'); if (f) { f.value = ''; }
                kwSetStatus('Select child SKUs, then load Amazon\'s suggestions.', false);
            }
            function kwParseList(text, isCsv) {
                var parts = String(text || '').split(/\r?\n/);
                var out = [];
                parts.forEach(function (line, idx) {
                    var cells = isCsv ? [line.split(',')[0]] : line.split(',');
                    cells.forEach(function (c) {
                        var t = String(c || '').replace(/^["']|["']$/g, '').replace(/\s+/g, ' ').trim();
                        if (!t) { return; }
                        if (isCsv && idx === 0 && /^(keyword|keywords|keyword text|search term)$/i.test(t)) { return; }
                        out.push(t);
                    });
                });
                return out;
            }
            function kwAddTexts(texts, mts) {
                var added = 0, skipped = 0;
                texts.forEach(function (t) {
                    if (t.length > 80 || t.split(' ').length > 10) { skipped++; return; }
                    mts.forEach(function (mt) {
                        if (kwAdd(t, mt, kwResolveBid(null))) { added++; }
                    });
                });
                kwRenderAdded();
                kwRenderSuggestions();
                return { added: added, skipped: skipped };
            }
            function kwSelectedMts(cls) {
                var out = [];
                document.querySelectorAll('.' + cls).forEach(function (cb) { if (cb.checked) { out.push(cb.value); } });
                return out;
            }
            function kwWire() {
                var bidMode = document.getElementById('amzKwBidMode');
                if (!bidMode) { return; }
                bidMode.addEventListener('change', function () {
                    document.getElementById('amzKwCustomBid').classList.toggle('d-none', bidMode.value !== 'custom');
                    document.getElementById('amzKwBidHint').textContent = bidMode.value === 'suggested'
                        ? 'Amazon\'s suggested bid per match type (default bid if none).'
                        : (bidMode.value === 'custom' ? 'Same bid for every keyword you add next.' : 'The default bid of the campaign.');
                });
                document.getElementById('amzKwLoadBtn').addEventListener('click', function () { kwLoadSuggestions(true); });
                document.getElementById('amzKwSort').addEventListener('change', function () { kwLoadSuggestions(false); });
                document.querySelectorAll('.amz-kw-filter').forEach(function (cb) { cb.addEventListener('change', kwRenderSuggestions); });
                document.getElementById('amzKwSugSearch').addEventListener('input', kwRenderSuggestions);
                document.getElementById('amzKwBulkBidApply').addEventListener('click', function (e) {
                    e.preventDefault();
                    var v = parseFloat((document.getElementById('amzKwBulkBid') || {}).value);
                    if (!isFinite(v) || v < 0.02) { window.alert('Enter a bid of at least $0.02.'); return; }
                    v = Math.round(v * 100) / 100;
                    kwAdded.forEach(function (k) { k.bid = v; });
                    kwRenderAdded();
                });
                ptWire();
                document.getElementById('amzKwSugBody').addEventListener('click', function (e) {
                    var a = e.target.closest ? e.target.closest('.amz-kw-add') : null;
                    if (!a) { return; }
                    e.preventDefault();
                    var row = kwSuggestions[Number(a.getAttribute('data-ki'))];
                    var mt = a.getAttribute('data-mt');
                    if (row && mt) {
                        kwAdd(row.keyword, mt, kwResolveBid(row.bids ? row.bids[mt] : null));
                        kwRenderAdded();
                        kwRenderSuggestions();
                    }
                });
                document.getElementById('amzKwAddAll').addEventListener('click', function (e) {
                    e.preventDefault();
                    var filters = kwActiveFilters();
                    kwSuggestions.forEach(function (row) {
                        ['BROAD', 'PHRASE', 'EXACT'].forEach(function (mt) {
                            if (filters.indexOf(mt) !== -1 && row.bids && row.bids[mt] != null) {
                                kwAdd(row.keyword, mt, kwResolveBid(row.bids[mt]));
                            }
                        });
                    });
                    kwRenderAdded();
                    kwRenderSuggestions();
                });
                document.getElementById('amzKwClearAll').addEventListener('click', function (e) {
                    e.preventDefault();
                    kwAdded = [];
                    kwRenderAdded();
                    kwRenderSuggestions();
                });
                var addedBody = document.getElementById('amzKwAddedBody');
                addedBody.addEventListener('click', function (e) {
                    var d = e.target.closest ? e.target.closest('.amz-kw-del') : null;
                    if (!d) { return; }
                    e.preventDefault();
                    kwAdded.splice(Number(d.getAttribute('data-i')), 1);
                    kwRenderAdded();
                    kwRenderSuggestions();
                    kwRenderIdeas();
                });
                addedBody.addEventListener('change', function (e) {
                    var inp = e.target;
                    if (!inp || !inp.classList || !inp.classList.contains('amz-kw-bid')) { return; }
                    var item = kwAdded[Number(inp.getAttribute('data-i'))];
                    var v = parseFloat(inp.value);
                    if (item) { item.bid = (isFinite(v) && v >= 0.02) ? Math.round(v * 100) / 100 : kwDefaultBid(); }
                    kwRenderAdded();
                });
                document.getElementById('amzKwListAddBtn').addEventListener('click', function () {
                    var mts = kwSelectedMts('amz-kw-list-mt');
                    var ta = document.getElementById('amzKwListText');
                    if (!mts.length) { window.alert('Pick at least one match type.'); return; }
                    var r = kwAddTexts(kwParseList(ta.value, false), mts);
                    ta.value = '';
                    if (r.skipped) { window.alert(r.skipped + ' keyword(s) skipped: Amazon allows up to 80 characters / 10 words.'); }
                });
                document.getElementById('amzKwFileAddBtn').addEventListener('click', function () {
                    var mts = kwSelectedMts('amz-kw-file-mt');
                    var input = document.getElementById('amzKwFile');
                    var file = input.files && input.files[0];
                    if (!mts.length) { window.alert('Pick at least one match type.'); return; }
                    if (!file) { window.alert('Choose a .csv or .txt file first.'); return; }
                    var reader = new FileReader();
                    reader.onload = function () {
                        var r = kwAddTexts(kwParseList(String(reader.result || ''), /\.csv$/i.test(file.name)), mts);
                        input.value = '';
                        if (r.skipped) { window.alert(r.skipped + ' keyword(s) skipped: Amazon allows up to 80 characters / 10 words.'); }
                    };
                    reader.readAsText(file);
                });
            }


            // ---- Product targeting (PT campaigns): auto targeting groups or ASIN targets ----
            var PT_GROUPS = [
                { type: 'QUERY_HIGH_REL_MATCHES', label: 'Close match', desc: 'Shoppers searching for terms closely related to your product' },
                { type: 'QUERY_BROAD_REL_MATCHES', label: 'Loose match', desc: 'Shoppers searching for terms loosely related to your product' },
                { type: 'ASIN_SUBSTITUTE_RELATED', label: 'Substitutes', desc: 'Detail pages of products similar to yours' },
                { type: 'ASIN_ACCESSORY_RELATED', label: 'Complements', desc: 'Detail pages of products that complement yours' }
            ];
            var ptAutoAdded = [];   // [{type, bid}]
            var ptAsins = [];       // [{asin, bid}]
            var ptSuggestions = []; // [{asin, themes[]}] from Amazon
            var ptSugSeq = 0;
            var ptSuggestUrl = @json(route('amazon.ads.missing.product-suggestions'));
            function ptSetStatus(msg, isErr) {
                var el = document.getElementById('amzPtSugStatus');
                if (!el) { return; }
                el.textContent = msg;
                el.classList.toggle('text-danger', !!isErr);
            }
            var ptMeta = {};        // asin -> {title, image} (from Amazon's catalog)
            function ptIsAdded(asin) { return ptAsins.some(function (x) { return x.asin === asin; }); }
            // Thumbnail + title + ASIN (links to the Amazon product page).
            function ptProductCell(asin) {
                var m = ptMeta[asin] || {};
                var img = m.image
                    ? '<img src="' + esc(m.image) + '" alt="" loading="lazy" referrerpolicy="no-referrer" style="width:40px;height:40px;object-fit:contain;flex:0 0 40px;">'
                    : '<div class="bg-light border rounded" style="width:40px;height:40px;flex:0 0 40px;"></div>';
                var title = m.title
                    ? '<div style="font-size:12px;line-height:1.25;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;" title="' + esc(m.title) + '">' + esc(m.title) + '</div>'
                    : '';
                return '<div class="d-flex align-items-center gap-2">' + img
                    + '<div style="min-width:0;">' + title
                    + '<a href="https://www.amazon.com/dp/' + encodeURIComponent(asin) + '" target="_blank" rel="noopener" class="fw-semibold text-decoration-none" style="font-size:11px;">' + esc(asin) + '</a></div></div>';
            }
            function ptRenderSuggestions() {
                var body = document.getElementById('amzPtSugBody');
                if (!body) { return; }
                var q = String((document.getElementById('amzPtSugSearch') || {}).value || '').toLowerCase().trim();
                var html = '';
                ptSuggestions.forEach(function (row, i) {
                    if (q && (String(row.asin) + ' ' + String((ptMeta[row.asin] || {}).title || '')).toLowerCase().indexOf(q) === -1) { return; }
                    html += '<tr><td>' + ptProductCell(row.asin) + '</td>'
                        + '<td class="text-muted" style="font-size:11px;">' + esc((row.themes || []).join(' · ') || '—') + '</td>'
                        + '<td class="text-end">' + (ptIsAdded(row.asin) ? '<span class="text-muted fst-italic">Added</span>'
                            : '<a href="#" class="fw-semibold amz-pt-sug-add" data-i="' + i + '">Add</a>') + '</td></tr>';
                });
                body.innerHTML = html || '<tr><td colspan="3" class="text-muted text-center py-3">No suggestions to show.</td></tr>';
            }
            function ptLoadSuggestions(force) {
                var children = collectSelectedChildren();
                var btn = document.getElementById('amzPtLoadBtn');
                if (!children.length) {
                    ptSuggestions = [];
                    ptRenderSuggestions();
                    ptSetStatus('Select at least one child SKU with an ASIN to load Amazon suggestions.', false);
                    return;
                }
                var seq = ++ptSugSeq;
                ptSetStatus('Loading suggestions from Amazon…', false);
                if (btn) { btn.disabled = true; }
                postForm(ptSuggestUrl, { children: children, refresh: force === true }).then(function (out) {
                    if (seq !== ptSugSeq) { return; }
                    if (btn) { btn.disabled = false; }
                    if (out.ok && out.body && out.body.ok) {
                        ptSuggestions = Array.isArray(out.body.products) ? out.body.products : [];
                        ptSuggestions.forEach(function (p) {
                            if (p.title || p.image) { ptMeta[p.asin] = { title: p.title || '', image: p.image || '' }; }
                        });
                        ptSetStatus(ptSuggestions.length
                            ? (ptSuggestions.length + ' products suggested by Amazon.')
                            : (out.body.message || 'Amazon returned no suggestions.'), false);
                    } else {
                        ptSuggestions = [];
                        ptSetStatus((out.body && out.body.message) || ('Could not load Amazon suggestions (HTTP ' + out.status + '). If this is 404, run php artisan route:clear on the server.'), true);
                    }
                    ptRenderSuggestions();
                }).catch(function () {
                    if (seq !== ptSugSeq) { return; }
                    if (btn) { btn.disabled = false; }
                    ptSetStatus('Network error loading Amazon suggestions.', true);
                });
            }
            // The active tab decides the mode: "Auto groups" = AUTO campaign, "Enter list" / "Upload file" = ASIN targets (MANUAL).
            function ptMode() {
                var t = document.querySelector('#amzPtTabs .nav-link.active');
                return t && t.getAttribute('data-pt-mode') === 'MANUAL' ? 'MANUAL' : 'AUTO';
            }
            function ptResolveBid() {
                var mode = (document.getElementById('amzPtBidMode') || {}).value || 'default';
                var v = mode === 'custom' ? parseFloat((document.getElementById('amzPtBid') || {}).value) : null;
                if (v == null || !isFinite(v) || v < 0.02) { v = kwDefaultBid(); }
                return Math.round(v * 100) / 100;
            }
            function ptGroupLabel(type) {
                var g = PT_GROUPS.filter(function (x) { return x.type === type; })[0];
                return g ? g.label : type;
            }
            function ptRender() {
                var auto = ptMode() === 'AUTO';
                var left = document.getElementById('amzPtAutoBody');
                if (left) {
                    left.innerHTML = PT_GROUPS.map(function (g) {
                        var added = ptAutoAdded.some(function (a) { return a.type === g.type; });
                        return '<tr><td><div class="fw-semibold">' + esc(g.label) + '</div><div class="text-muted" style="font-size:11px;">' + esc(g.desc) + '</div></td>'
                            + '<td class="text-end">' + (added ? '<span class="text-muted fst-italic">Added</span>'
                                : '<a href="#" class="fw-semibold amz-pt-auto-add" data-type="' + g.type + '">Add</a>') + '</td></tr>';
                    }).join('');
                }
                var rows = auto
                    ? ptAutoAdded.map(function (a, i) { return { label: esc(ptGroupLabel(a.type)), type: 'Auto group', bid: a.bid, i: i }; })
                    : ptAsins.map(function (a, i) { return { label: ptProductCell(a.asin), type: 'ASIN', bid: a.bid, i: i }; });
                ptRenderSuggestions();
                var cnt = document.getElementById('amzPtAddedCount'); if (cnt) { cnt.textContent = String(rows.length); }
                var body = document.getElementById('amzPtAddedBody');
                if (body) {
                    body.innerHTML = rows.length ? rows.map(function (r) {
                        return '<tr><td>' + r.label + '</td><td>' + esc(r.type) + '</td>'
                            + '<td><input type="number" class="form-control form-control-sm py-0 amz-pt-bid" data-i="' + r.i + '" min="0.02" step="0.01" value="' + Number(r.bid).toFixed(2) + '"></td>'
                            + '<td><a href="#" class="text-danger amz-pt-del" data-i="' + r.i + '" title="Remove">&times;</a></td></tr>';
                    }).join('') : '<tr><td colspan="4" class="text-muted text-center py-3">' + (auto ? 'No auto groups: they will all be paused.' : 'No ASINs added yet.') + '</td></tr>';
                }
            }
            function ptResetForOpen() {
                ptAutoAdded = PT_GROUPS.map(function (g) { return { type: g.type, bid: kwDefaultBid() }; });
                ptAsins = [];
                ptSuggestions = [];
                ptSugSeq++;
                var psq = document.getElementById('amzPtSugSearch'); if (psq) { psq.value = ''; }
                ptSetStatus('Select child SKUs, then load Amazon\'s suggestions.', false);
                var firstTab = document.querySelector('#amzPtTabs .nav-link');
                if (firstTab && window.bootstrap) { bootstrap.Tab.getOrCreateInstance(firstTab).show(); }
                ['amzPtAsinText', 'amzPtBulkBid'].forEach(function (id) { var el = document.getElementById(id); if (el) { el.value = ''; } });
                var bm = document.getElementById('amzPtBidMode'); if (bm) { bm.value = 'default'; }
                var cb = document.getElementById('amzPtBid'); if (cb) { cb.value = '0.60'; cb.classList.add('d-none'); }
                var f = document.getElementById('amzPtFile'); if (f) { f.value = ''; }
                ptRender();
            }
            function ptPayload() {
                var mode = ptMode();
                var out = { pt_mode: mode, pt_groups: [], pt_targets: [] };
                if (mode === 'AUTO') {
                    out.pt_groups = PT_GROUPS.map(function (g) {
                        var a = ptAutoAdded.filter(function (x) { return x.type === g.type; })[0];
                        return { type: g.type, enabled: !!a, bid: a ? a.bid : null };
                    });
                } else {
                    out.pt_targets = ptAsins.map(function (a) { return { asin: a.asin, bid: a.bid }; });
                }
                return out;
            }
            function ptAddAsins(texts) {
                var bid = ptResolveBid();
                var bad = 0;
                texts.forEach(function (t) {
                    var a = String(t || '').toUpperCase().trim();
                    if (!a) { return; }
                    if (!/^[A-Z0-9]{10}$/.test(a)) { bad++; return; }
                    if (!ptAsins.some(function (x) { return x.asin === a; })) { ptAsins.push({ asin: a, bid: bid }); }
                });
                ptRender();
                if (bad) { window.alert(bad + ' value(s) skipped: an ASIN is 10 letters / digits.'); }
            }
            function ptWire() {
                var addBtn = document.getElementById('amzPtAddBtn');
                if (!addBtn) { return; }
                document.querySelectorAll('#amzPtTabs .nav-link').forEach(function (t) {
                    t.addEventListener('shown.bs.tab', function () { ptRender(); syncPtModeUi(); });
                });
                var ptBidMode = document.getElementById('amzPtBidMode');
                ptBidMode.addEventListener('change', function () {
                    document.getElementById('amzPtBid').classList.toggle('d-none', ptBidMode.value !== 'custom');
                    document.getElementById('amzPtBidHint').textContent = ptBidMode.value === 'custom'
                        ? 'Same bid for every target you add next.'
                        : 'The default bid of the campaign, used for what you add next.';
                });
                document.getElementById('amzPtLoadBtn').addEventListener('click', function () { ptLoadSuggestions(true); });
                document.getElementById('amzPtSugSearch').addEventListener('input', ptRenderSuggestions);
                document.getElementById('amzPtSugBody').addEventListener('click', function (e) {
                    var a = e.target.closest ? e.target.closest('.amz-pt-sug-add') : null;
                    if (!a) { return; }
                    e.preventDefault();
                    var row = ptSuggestions[Number(a.getAttribute('data-i'))];
                    if (row && !ptIsAdded(row.asin)) { ptAsins.push({ asin: row.asin, bid: ptResolveBid() }); }
                    ptRender();
                });
                document.getElementById('amzPtSugAddAll').addEventListener('click', function (e) {
                    e.preventDefault();
                    var q = String((document.getElementById('amzPtSugSearch') || {}).value || '').toLowerCase().trim();
                    var bid = ptResolveBid();
                    ptSuggestions.forEach(function (row) {
                        if (q && (String(row.asin) + ' ' + String((ptMeta[row.asin] || {}).title || '')).toLowerCase().indexOf(q) === -1) { return; }
                        if (!ptIsAdded(row.asin)) { ptAsins.push({ asin: row.asin, bid: bid }); }
                    });
                    ptRender();
                });
                document.getElementById('amzPtAutoBody').addEventListener('click', function (e) {
                    var a = e.target.closest ? e.target.closest('.amz-pt-auto-add') : null;
                    if (!a) { return; }
                    e.preventDefault();
                    var type = a.getAttribute('data-type');
                    if (!ptAutoAdded.some(function (x) { return x.type === type; })) { ptAutoAdded.push({ type: type, bid: ptResolveBid() }); }
                    ptRender();
                });
                document.getElementById('amzPtAutoAddAll').addEventListener('click', function (e) {
                    e.preventDefault();
                    PT_GROUPS.forEach(function (g) {
                        if (!ptAutoAdded.some(function (x) { return x.type === g.type; })) { ptAutoAdded.push({ type: g.type, bid: ptResolveBid() }); }
                    });
                    ptRender();
                });
                addBtn.addEventListener('click', function () {
                    var ta = document.getElementById('amzPtAsinText');
                    if (ta.value.trim()) {
                        ptAddAsins(ta.value.split(/[\s,;]+/));
                        ta.value = '';
                    }
                });
                document.getElementById('amzPtFileAddBtn').addEventListener('click', function () {
                    var fileInput = document.getElementById('amzPtFile');
                    var file = fileInput.files && fileInput.files[0];
                    if (!file) { window.alert('Choose a .csv or .txt file first.'); return; }
                    var reader = new FileReader();
                    reader.onload = function () {
                        ptAddAsins(String(reader.result || '').split(/[\s,;]+/));
                        fileInput.value = '';
                    };
                    reader.readAsText(file);
                });
                document.getElementById('amzPtClearAll').addEventListener('click', function (e) {
                    e.preventDefault();
                    if (ptMode() === 'AUTO') { ptAutoAdded = []; } else { ptAsins = []; }
                    ptRender();
                });
                document.getElementById('amzPtBulkBidApply').addEventListener('click', function (e) {
                    e.preventDefault();
                    var v = parseFloat((document.getElementById('amzPtBulkBid') || {}).value);
                    if (!isFinite(v) || v < 0.02) { window.alert('Enter a bid of at least $0.02.'); return; }
                    v = Math.round(v * 100) / 100;
                    (ptMode() === 'AUTO' ? ptAutoAdded : ptAsins).forEach(function (x) { x.bid = v; });
                    ptRender();
                });
                var addedBody = document.getElementById('amzPtAddedBody');
                addedBody.addEventListener('click', function (e) {
                    var d = e.target.closest ? e.target.closest('.amz-pt-del') : null;
                    if (!d) { return; }
                    e.preventDefault();
                    (ptMode() === 'AUTO' ? ptAutoAdded : ptAsins).splice(Number(d.getAttribute('data-i')), 1);
                    ptRender();
                });
                addedBody.addEventListener('change', function (e) {
                    var inp = e.target;
                    if (!inp || !inp.classList || !inp.classList.contains('amz-pt-bid')) { return; }
                    var item = (ptMode() === 'AUTO' ? ptAutoAdded : ptAsins)[Number(inp.getAttribute('data-i'))];
                    var v = parseFloat(inp.value);
                    if (item) { item.bid = (isFinite(v) && v >= 0.02) ? Math.round(v * 100) / 100 : kwDefaultBid(); }
                    ptRender();
                });
            }
            function syncPtModeUi() {
                var title = document.getElementById('amzCreateCampaignModalLabel');
                if (title && currentCreateType() === 'PT') {
                    title.textContent = 'Create Amz SP Campaign (PT / ' + (ptMode() === 'MANUAL' ? 'MANUAL ASIN targets' : 'AUTO') + ')';
                }
            }

            // ---- Search ideas tab: type seed keywords -> Keyword Planner ideas ----
            var kwIdeas = [];
            function kwIdeaMts() { return kwSelectedMts('amz-kw-idea-mt'); }
            function kwRenderIdeas() {
                var body = document.getElementById('amzKwIdeaBody');
                if (!body) { return; }
                var mts = kwIdeaMts();
                body.innerHTML = kwIdeas.length ? kwIdeas.map(function (r, i) {
                    var all = mts.length && mts.every(function (mt) { return kwIsAdded(r.keyword, mt); });
                    return '<tr><td class="fw-semibold">' + esc(r.keyword) + '</td>'
                        + '<td class="text-end">' + (r.avg_monthly == null ? '—' : Number(r.avg_monthly).toLocaleString('en-US')) + '</td>'
                        + '<td>' + esc(r.competition || '—') + '</td>'
                        + '<td class="text-end">' + (r.avg_cpc == null ? '—' : '$' + Number(r.avg_cpc).toFixed(2)) + '</td>'
                        + '<td class="text-end">' + (all ? '<span class="text-muted fst-italic">Added</span>'
                            : '<a href="#" class="fw-semibold amz-kw-idea-add" data-i="' + i + '">Add</a>') + '</td></tr>';
                }).join('') : '<tr><td colspan="5" class="text-muted text-center py-3">No ideas yet.</td></tr>';
            }
            function kwAddIdea(r) {
                var mts = kwIdeaMts();
                if (!mts.length) { window.alert('Pick at least one match type under "Add as".'); return false; }
                mts.forEach(function (mt) { kwAdd(r.keyword, mt, kwResolveBid(null)); });
                return true;
            }
            function kwLoadIdeas() {
                var seed = (document.getElementById('amzKwSeed') || {}).value || '';
                var st = document.getElementById('amzKwIdeaStatus');
                var btn = document.getElementById('amzKwSeedBtn');
                if (!seed.trim()) { st.textContent = 'Type at least one keyword.'; st.classList.add('text-danger'); return; }
                st.textContent = 'Getting keyword ideas…'; st.classList.remove('text-danger');
                btn.disabled = true;
                postForm(kwIdeasUrl, { seeds: seed }).then(function (out) {
                    btn.disabled = false;
                    if (out.ok && out.body && out.body.ok) {
                        kwIdeas = Array.isArray(out.body.rows) ? out.body.rows : [];
                        st.textContent = kwIdeas.length ? (kwIdeas.length + ' ideas.') : (out.body.message || 'No ideas found.');
                    } else {
                        kwIdeas = [];
                        st.textContent = (out.body && out.body.message) || ('Could not get ideas (HTTP ' + out.status + ').');
                        st.classList.add('text-danger');
                    }
                    kwRenderIdeas();
                }).catch(function () {
                    btn.disabled = false;
                    st.textContent = 'Network error getting ideas.'; st.classList.add('text-danger');
                });
            }
            function kwWireIdeas() {
                var btn = document.getElementById('amzKwSeedBtn');
                if (!btn) { return; }
                btn.addEventListener('click', kwLoadIdeas);
                document.getElementById('amzKwSeed').addEventListener('keydown', function (e) {
                    if (e.key === 'Enter') { e.preventDefault(); kwLoadIdeas(); }
                });
                document.querySelectorAll('.amz-kw-idea-mt').forEach(function (cb) { cb.addEventListener('change', kwRenderIdeas); });
                document.getElementById('amzKwIdeaBody').addEventListener('click', function (e) {
                    var a = e.target.closest ? e.target.closest('.amz-kw-idea-add') : null;
                    if (!a) { return; }
                    e.preventDefault();
                    var r = kwIdeas[Number(a.getAttribute('data-i'))];
                    if (r && kwAddIdea(r)) { kwRenderAdded(); kwRenderIdeas(); kwRenderSuggestions(); }
                });
                document.getElementById('amzKwIdeaAddAll').addEventListener('click', function (e) {
                    e.preventDefault();
                    var ok = true;
                    kwIdeas.forEach(function (r) { if (ok) { ok = kwAddIdea(r); } });
                    kwRenderAdded(); kwRenderIdeas(); kwRenderSuggestions();
                });
            }

            function getCreateModal() {
                var el = document.getElementById('amzCreateCampaignModal');
                return el && window.bootstrap ? bootstrap.Modal.getOrCreateInstance(el) : null;
            }
            function getAiNegModal() {
                var el = document.getElementById('amzAiNegModal');
                return el && window.bootstrap ? bootstrap.Modal.getOrCreateInstance(el) : null;
            }
            function getAiPosModal() {
                var el = document.getElementById('amzAiPosModal');
                return el && window.bootstrap ? bootstrap.Modal.getOrCreateInstance(el) : null;
            }

            function stripCampaignTypeSuffix(name) {
                var n = String(name || '').trim().replace(/\s+/g, ' ');
                // Drop trailing PT/KW (and repeats) so we can re-apply the selected type.
                while (/\s+(PT|KW)$/i.test(n)) {
                    n = n.replace(/\s+(PT|KW)$/i, '').trim();
                }
                return n;
            }

            function applyCampaignTypeSuffix(type) {
                var input = document.getElementById('amzCreateCampaignName');
                if (!input) { return; }
                var t = normalizeCreateType(type);
                var base = stripCampaignTypeSuffix(input.value);
                if (!base) {
                    var parent = document.getElementById('amzCreateParent').value || '';
                    base = parent ? ('PARENT ' + parent) : 'PARENT';
                }
                input.value = base + ' ' + t;
            }

            function normalizeCreateType(type) {
                return String(type || 'KW').toUpperCase() === 'PT' ? 'PT' : 'KW';
            }

            function currentCreateType() {
                return normalizeCreateType((document.getElementById('amzCreateType') || {}).value);
            }

            function syncCreateModalUi(type) {
                type = normalizeCreateType(type);
                var typeInput = document.getElementById('amzCreateType');
                if (typeInput) { typeInput.value = type; }
                var targeting = type === 'PT' ? 'PT / AUTO' : 'KW / MANUAL';
                var example = type === 'PT' ? 'PARENT PMX PT' : 'PARENT PMX KW';
                var title = document.getElementById('amzCreateCampaignModalLabel');
                if (title) { title.textContent = 'Create Amz SP Campaign (' + targeting + ')'; }
                var hint = document.getElementById('amzCreateCampaignHint');
                if (hint) {
                    hint.innerHTML = type === 'PT'
                        ? 'Creates <strong>one PAUSED</strong> Sponsored Products <strong>PT / AUTO</strong> campaign for the parent'
                            + ' (e.g. <code>' + example + '</code>). Selected child SKUs become product ads'
                            + ' (Amz seller rule: ads use seller <code>sku</code>; ASIN is shown for verification).'
                            + ' Choose Amazon\'s automatic targeting groups, or your own ASIN targets, under Product targeting.'
                        : 'Creates <strong>one PAUSED</strong> Sponsored Products <strong>KW / MANUAL</strong> campaign for the parent'
                            + ' (e.g. <code>' + example + '</code>). Selected child SKUs become product ads'
                            + ' (Amz seller rule: ads use seller <code>sku</code>; ASIN is shown for verification).'
                            + ' The keywords you add under Keyword targeting are created in the same step.'
                            + ' Campaign negative keywords support Phrase / Exact only (not Broad).';
                }
                var nameInput = document.getElementById('amzCreateCampaignName');
                if (nameInput) { nameInput.placeholder = example; }
                var note = document.getElementById('amzCreateTypeNote');
                if (note) {
                    note.textContent = type === 'PT'
                        ? 'PT campaigns use the targeting you pick above: the auto groups (PT / AUTO) or ASIN targets (a manual campaign).'
                        : 'KW / MANUAL campaigns are created with the keywords you add above.';
                }
                var kwSec = document.getElementById('amzKwSection');
                if (kwSec) { kwSec.classList.toggle('d-none', type === 'PT'); }
                var ptSec = document.getElementById('amzPtSection');
                if (ptSec) { ptSec.classList.toggle('d-none', type !== 'PT'); }
                var posLink = document.getElementById('amzCreateAiPosLink');
                if (posLink) { posLink.classList.toggle('d-none', type === 'PT'); }
            }

            function pickLinkedCampaignId(d, preferType) {
                var lists = [];
                var t = String(preferType || 'PT').toUpperCase();
                if (t === 'KW') {
                    lists = [d.kw || [], d.pt || []];
                } else {
                    lists = [d.pt || [], d.kw || []];
                }
                for (var i = 0; i < lists.length; i++) {
                    var list = lists[i];
                    for (var j = 0; j < list.length; j++) {
                        var cid = list[j] && list[j].campaign_id ? String(list[j].campaign_id) : '';
                        if (cid) { return cid; }
                    }
                }
                return '';
            }

            function amzMode() {
                var r = document.querySelector('input[name="amzCreateMode"]:checked');
                var box = document.getElementById('amzModeBox');
                return (r && r.value === 'update' && box && !box.classList.contains('d-none')) ? 'update' : 'new';
            }
            function amzSubmitHtml() {
                return amzMode() === 'update'
                    ? '<i class="fa fa-save me-1"></i> Update campaign'
                    : '<i class="fa fa-plus me-1"></i> Create campaign';
            }
            function amzApplyMode() {
                var upd = amzMode() === 'update';
                document.querySelectorAll('.amz-new-only').forEach(function (el) { el.classList.toggle('d-none', upd); });
                var sel = document.getElementById('amzUpdateCampaign'); if (sel) { sel.classList.toggle('d-none', !upd); }
                var hint = document.getElementById('amzUpdateHint'); if (hint) { hint.classList.toggle('d-none', !upd); }
                var btn = document.getElementById('amzCreateSubmitBtn'); if (btn) { btn.innerHTML = amzSubmitHtml(); }
            }
            function amzSetupModeBox(d, type) {
                var box = document.getElementById('amzModeBox');
                if (!box) { return; }
                var src = type === 'PT' ? d.pt : d.kw;
                var list = Array.isArray(src) ? src.filter(function (c) { return c && c.campaign_id; }) : [];
                var sel = document.getElementById('amzUpdateCampaign');
                sel.innerHTML = list.map(function (c) {
                    return '<option value="' + esc(c.campaign_id) + '">' + esc(c.campaign_name || c.campaign_id) + '</option>';
                }).join('');
                box.classList.toggle('d-none', !list.length);
                var rn = document.querySelector('input[name="amzCreateMode"][value="new"]'); if (rn) { rn.checked = true; }
                amzApplyMode();
            }

            function openCreateModal(d, type) {
                type = normalizeCreateType(type);
                lastCreateRowData = d || null;
                // Prefer an already-linked campaign id so Push works without re-create.
                lastCreatedCampaignId = pickLinkedCampaignId(d, type);
                lastCreatedAdGroupId = '';
                syncCreateModalUi(type);
                document.getElementById('amzCreateParent').value = d.parent || '';
                document.getElementById('amzCreateParentDisplay').value = d.parent || '';
                document.getElementById('amzCreateCampaignName').value = d.sku || ('PARENT ' + (d.parent || ''));
                document.getElementById('amzCreateBudget').value = '3';
                document.getElementById('amzCreateDefaultBid').value = '0.60';
                applyCampaignTypeSuffix(type);
                // If a same-type chip already exists, use that campaign name for push targeting.
                var preferList = type === 'PT'
                    ? ((Array.isArray(d.pt) && d.pt.length) ? d.pt : [])
                    : ((Array.isArray(d.kw) && d.kw.length) ? d.kw : []);
                if (preferList.length && preferList[0].campaign_name) {
                    document.getElementById('amzCreateCampaignName').value = preferList[0].campaign_name;
                    applyCampaignTypeSuffix(type);
                }
                document.getElementById('amzCreateTargetSku').value = d.target_sku || '';
                document.getElementById('amzCreateError').classList.add('d-none');
                document.getElementById('amzCreateError').textContent = '';

                var children = Array.isArray(d.children) ? d.children : [];
                var body = document.getElementById('amzCreateChildrenBody');
                body.innerHTML = children.map(function (c, idx) {
                    var sku = c.target_sku || '';
                    var asin = c.asin || '';
                    var inv = (c.inv == null ? 0 : Number(c.inv)) || 0;
                    var disabled = !asin;
                    var checked = !disabled && inv > 0 ? ' checked' : (!disabled && children.length === 1 ? ' checked' : '');
                    return '<tr class="' + (disabled ? 'table-secondary' : '') + '">'
                        + '<td><input type="checkbox" class="form-check-input amz-child-check" data-idx="' + idx + '"'
                        + (disabled ? ' disabled' : '') + checked + '></td>'
                        + '<td class="amz-child-sku">' + esc(sku) + '</td>'
                        + '<td class="amz-child-asin ' + (asin ? '' : 'text-danger') + '">' + esc(asin || '— missing ASIN') + '</td>'
                        + '<td class="text-end">' + inv.toLocaleString('en-US') + '</td>'
                        + '</tr>';
                }).join('');
                document.getElementById('amzCreateChildCount').textContent = '(' + children.length + ')';
                syncCreateAiHiddenFromSelection();
                kwResetForOpen();
                amzSetupModeBox(d || {}, type);
                var modal = getCreateModal();
                if (modal) { modal.show(); }
                if (type === 'KW') { kwLoadSuggestions(); }
                if (type === 'PT') { ptLoadSuggestions(); }
            }

            function collectSelectedChildren() {
                var rows = [];
                document.querySelectorAll('#amzCreateChildrenBody tr').forEach(function (tr) {
                    var cb = tr.querySelector('.amz-child-check');
                    if (!cb || !cb.checked || cb.disabled) { return; }
                    rows.push({
                        target_sku: (tr.querySelector('.amz-child-sku') || {}).textContent || '',
                        asin: ((tr.querySelector('.amz-child-asin') || {}).textContent || '').replace('— missing ASIN', '').trim()
                    });
                });
                return rows;
            }

            function syncCreateAiHiddenFromSelection() {
                var selected = collectSelectedChildren();
                var first = selected[0] || null;
                document.getElementById('amzCreateTargetSku').value = first ? first.target_sku : '';
            }

            function buildTable(campaignNames) {
                var table = new Tabulator('#amzAdsMissingTable', {
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
                    initialSort: [{ column: 'parent', dir: 'asc' }],
                    rowFormatter: function (row) {
                        if (row.getData().is_parent) {
                            row.getElement().classList.add('parent-row');
                        }
                    },
                    columns: [
                        {
                            title: 'Parent', field: 'parent', headerFilter: 'input', headerFilterPlaceholder: 'Search Parent...',
                            cssClass: 'text-primary', widthGrow: 1, minWidth: 280, frozen: true, tooltip: true,
                            hozAlign: 'center', headerHozAlign: 'center',
                            formatter: function (cell) {
                                var v = cell.getValue() || '';
                                return '<span class="parent-name">' + esc(v) + '</span>'
                                    + ' <i class="fa fa-copy parent-copy-btn" role="button" tabindex="0" title="Copy parent name" data-parent="' + esc(v) + '"></i>';
                            }
                        },
                        {
                            title: 'Inventory', field: 'inventory', width: 130,
                            hozAlign: 'right', headerHozAlign: 'right',
                            headerSort: true, sorter: 'number',
                            headerFilter: 'list',
                            headerFilterParams: { values: { '': 'All', in: 'In Stock', zero: 'Zero Inv' } },
                            headerFilterFunc: inventoryHeaderFilter,
                            formatter: function (cell) {
                                var v = cell.getValue();
                                return (v == null || v === '') ? '' : Number(v).toLocaleString('en-US');
                            }
                        },
                        {
                            title: 'CT/UP KW', field: '_create_kw', width: 100,
                            headerTooltip: 'CT = Create a new KW / MANUAL campaign. UP = Update an existing linked KW campaign (add keywords). Click + on a row to choose.',
                            hozAlign: 'center', headerHozAlign: 'center',
                            headerSort: false,
                            formatter: function (cell) {
                                var d = cell.getData();
                                var hasKw = Array.isArray(d.kw) && d.kw.length > 0;
                                var title = hasKw
                                    ? 'KW already linked — click to create another MANUAL campaign (CT) or add keywords to the existing one (UP)'
                                    : 'CT: create Amz KW / MANUAL SP campaign for this parent';
                                return '<button type="button" class="amz-create-btn" data-sku="' + esc(d.sku || '') + '" data-type="KW" title="' + esc(title) + '">'
                                    + '<i class="fa fa-plus"></i></button>';
                            }
                        },
                        countColumnDef('kw', 'KW K Count', 'Keywords (enabled + paused) in the linked KW campaign(s), counted through the Amazon Ads API. Click to see them.'),
                        {
                            title: 'Campaign KW', field: 'kw', widthGrow: 2, minWidth: 340,
                            hozAlign: 'center', headerHozAlign: 'center',
                            headerSort: true, sorter: linkCountSorter,
                            headerFilter: 'list',
                            headerFilterParams: { values: { '': 'All', missing: 'Missing', linked: 'Linked' } },
                            headerFilterFunc: missingHeaderFilter,
                            formatter: chipsFormatter('KW'),
                            accessorDownload: linkNamesAccessor
                        },
                        {
                            title: 'Parent Vs Ads SKU KW', field: '_pva_kw', width: 150,
                            headerTooltip: 'The Amazon Ads variation check: every child SKU of this parent that needs ads is in the parent\'s KW campaign (2/2 = all present). Hover a value for the SKU lists.',
                            hozAlign: 'center', headerHozAlign: 'center', headerSort: false,
                            formatter: function (cell) { return amzPvaCell(cell.getData().parent, 'kw'); }
                        },
                        {
                            title: 'CT/UP PT', field: '_create_pt', width: 100,
                            headerTooltip: 'CT = Create a new PT campaign (auto groups or ASIN targets). UP = Update an existing linked PT campaign (add targets / auto groups). Click + on a row to choose.',
                            hozAlign: 'center', headerHozAlign: 'center',
                            headerSort: false,
                            formatter: function (cell) {
                                var d = cell.getData();
                                var hasPt = Array.isArray(d.pt) && d.pt.length > 0;
                                var title = hasPt
                                    ? 'PT already linked — click to create another PT campaign (CT) or add targets to the existing one (UP)'
                                    : 'CT: create Amz PT SP campaign for this parent';
                                return '<button type="button" class="amz-create-btn" data-sku="' + esc(d.sku || '') + '" data-type="PT" title="' + esc(title) + '">'
                                    + '<i class="fa fa-plus"></i></button>';
                            }
                        },
                        countColumnDef('pt', 'PT Count', 'Targets (auto groups + ASIN / category targets, enabled + paused) in the linked PT campaign(s), counted through the Amazon Ads API. Click to see them.'),
                        {
                            title: 'Campaign PT', field: 'pt', widthGrow: 2, minWidth: 340,
                            hozAlign: 'center', headerHozAlign: 'center',
                            headerSort: true, sorter: linkCountSorter,
                            headerFilter: 'list',
                            headerFilterParams: { values: { '': 'All', missing: 'Missing', linked: 'Linked' } },
                            headerFilterFunc: missingHeaderFilter,
                            formatter: chipsFormatter('PT'),
                            accessorDownload: linkNamesAccessor
                        },
                        {
                            title: 'Parent Vs Ads SKU PT', field: '_pva_pt', width: 150,
                            headerTooltip: 'The Amazon Ads variation check: every child SKU of this parent that needs ads is in the parent\'s PT campaign (2/2 = all present). Hover a value for the SKU lists.',
                            hozAlign: 'center', headerHozAlign: 'center', headerSort: false,
                            formatter: function (cell) { return amzPvaCell(cell.getData().parent, 'pt'); }
                        },
                        {
                            title: 'CT/UP HL', field: '_create_hl', width: 100,
                            headerTooltip: 'CT = Create a new HL (Sponsored Brands) campaign, PAUSED, with the brand logo / headline copied from an existing HL campaign. UP = Add the in-stock child ASINs to an existing linked HL campaign.',
                            hozAlign: 'center', headerHozAlign: 'center',
                            headerSort: false,
                            formatter: function (cell) {
                                var d = cell.getData();
                                var hasHl = Array.isArray(d.hl) && d.hl.length > 0;
                                var title = hasHl
                                    ? 'HL already linked — click to create another HL campaign (CT) or add child ASINs to the existing one (UP)'
                                    : 'CT: create Amz HL (Sponsored Brands) campaign for this parent';
                                return '<button type="button" class="amz-create-btn" data-sku="' + esc(d.sku || '') + '" data-type="HL" title="' + esc(title) + '">'
                                    + '<i class="fa fa-plus"></i></button>';
                            }
                        },
                        countColumnDef('hl', 'HL Count', 'Keywords + product targets (enabled + paused) in the linked HL campaign(s), counted through the Amazon Ads API. Click to see them.'),
                        {
                            title: 'Campaign HL', field: 'hl', widthGrow: 2, minWidth: 340,
                            headerTooltip: 'HL = Headline (Sponsored Brands) campaign named "PARENT {parent} HL" or "PARENT {parent} HEAD" on Amazon; linked automatically.',
                            hozAlign: 'center', headerHozAlign: 'center',
                            headerSort: true, sorter: linkCountSorter,
                            headerFilter: 'list',
                            headerFilterParams: { values: { '': 'All', missing: 'Missing', linked: 'Linked' } },
                            headerFilterFunc: missingHeaderFilter,
                            formatter: chipsFormatter('HL'),
                            accessorDownload: linkNamesAccessor
                        },
                        {
                            title: 'Parent Vs Ads SKU HL', field: '_pva_hl', width: 150,
                            headerTooltip: 'Every in-stock child SKU of this parent has its ASIN on the parent\'s HL campaign creative (2/2 = all present). Refreshed from Amazon automatically. Hover a value for the SKU lists.',
                            hozAlign: 'center', headerHozAlign: 'center', headerSort: false,
                            formatter: function (cell) { return amzHlPvaCell(cell.getData().parent); }
                        }
                    ]
                });

                table.on('tableBuilt', function () {
                    table.setFilter(onlyParentsFilter);
                    // Parent Vs Ads SKU HL: load now, then re-check against Amazon every 5 minutes while the page is open.
                    amzHlPvaTable = table;
                    amzHlPvaLoad(false);
                    setInterval(function () { if (!document.hidden) { amzHlPvaLoad(true); } }, 5 * 60 * 1000);
                    // Parent Vs Ads SKU KW / PT (Amazon Ads variation check), loaded once and painted into the cells.
                    amzPvaLoad(false, function () {
                        table.getRows().forEach(function (r) { try { r.reformat(); } catch (e) {} });
                    });
                });

                // ---- Columns: show / hide, saved in this browser (localStorage) ----
                var COLS_STORE_KEY = 'amzMissingColumns.v1';
                var COLS_LOCKED = { parent: true };   // the Parent column always stays visible
                function colsLoadSaved() {
                    try {
                        var v = JSON.parse(window.localStorage.getItem(COLS_STORE_KEY) || '{}');
                        return (v && typeof v === 'object' && !Array.isArray(v)) ? v : {};
                    } catch (err) { return {}; }
                }
                function colsSave() {
                    var map = {};
                    table.getColumns().forEach(function (col) {
                        var f = col.getField();
                        if (!f || COLS_LOCKED[f]) { return; }
                        map[f] = col.isVisible();
                    });
                    try { window.localStorage.setItem(COLS_STORE_KEY, JSON.stringify(map)); } catch (err) { /* private mode / quota */ }
                }
                function colsApplySaved() {
                    var saved = colsLoadSaved();
                    table.getColumns().forEach(function (col) {
                        var f = col.getField();
                        if (!f || COLS_LOCKED[f] || !Object.prototype.hasOwnProperty.call(saved, f)) { return; }
                        if (saved[f]) { col.show(); } else { col.hide(); }
                    });
                }
                function colsRenderMenu() {
                    var list = document.getElementById('amzColumnsList');
                    if (!list) { return; }
                    list.innerHTML = table.getColumns().filter(function (col) {
                        var f = col.getField();
                        return f && !COLS_LOCKED[f];
                    }).map(function (col) {
                        var f = col.getField();
                        var title = String(col.getDefinition().title || f);
                        return '<label class="d-flex align-items-center gap-2 py-1 px-1 mb-0 small" style="cursor:pointer;">'
                            + '<input type="checkbox" class="form-check-input mt-0 amz-col-toggle" data-field="' + esc(f) + '"' + (col.isVisible() ? ' checked' : '') + '>'
                            + '<span>' + esc(title) + '</span></label>';
                    }).join('');
                }
                function colsSetAll(visible) {
                    table.getColumns().forEach(function (col) {
                        var f = col.getField();
                        if (!f || COLS_LOCKED[f]) { return; }
                        if (visible) { col.show(); } else { col.hide(); }
                    });
                    colsSave();
                    colsRenderMenu();
                }
                var colsList = document.getElementById('amzColumnsList');
                if (colsList) {
                    colsList.addEventListener('change', function (e) {
                        var cb = e.target && e.target.classList && e.target.classList.contains('amz-col-toggle') ? e.target : null;
                        if (!cb) { return; }
                        var col = table.getColumn(cb.getAttribute('data-field'));
                        if (!col) { return; }
                        if (cb.checked) { col.show(); } else { col.hide(); }
                        colsSave();
                    });
                }
                document.getElementById('amzColsShowAll').addEventListener('click', function () { colsSetAll(true); });
                document.getElementById('amzColsHideAll').addEventListener('click', function () { colsSetAll(false); });
                table.on('tableBuilt', function () {
                    colsApplySaved();
                    colsRenderMenu();
                    kwCountsReformat();
                });

                // Export the current (filtered) view to CSV.
                var exportBtn = document.getElementById('amzMissingExportBtn');
                if (exportBtn) {
                    exportBtn.addEventListener('click', function () {
                        var stamp = new Date().toISOString().slice(0, 10);
                        table.download('csv', 'amazon-ads-missing-' + stamp + '.csv');
                    });
                }


                // ---- KW K Count / PT Count columns: counts of the linked campaigns, loaded through the Amazon Ads API ----
                function kwCountsReformat() {
                    table.getRows().forEach(function (r) { try { r.reformat(); } catch (e) {} });
                }
                function kwCountStatus(msg, isErr) {
                    var el = document.getElementById('amzKwCountStatus');
                    if (!el) { return; }
                    el.textContent = msg || '';
                    el.classList.toggle('text-danger', !!isErr);
                }
                // kind: 'kw' | 'pt'. refsOverride: load exactly these refs (refreshing them); otherwise every linked campaign not loaded yet.
                var kwLastData = null;
                function amzLoadCounts(kind, refsOverride, onDone) {
                    var st = cntState[kind];
                    var label = kind === 'pt' ? 'target' : (kind === 'hl' ? 'HL keyword / target' : 'keyword');
                    if (st.loading) { if (onDone) { onDone(); } return; }
                    var all = {};
                    if (refsOverride) {
                        refsOverride.forEach(function (r) { all[r.key] = r; });
                    } else {
                        var source = (kwLastData && kwLastData.length) ? kwLastData : table.getData();
                        source.forEach(function (d) {
                            kwRowRefs(d, kind).forEach(function (r) {
                                var known = st.map[r.key];
                                var fresh = known && !known.unresolved && cntTs[kind][r.key] && (Date.now() - cntTs[kind][r.key] < CNT_TTL_MS);
                                if (!fresh) { all[r.key] = r; }
                            });
                        });
                    }
                    var refs = Object.keys(all).map(function (k) { return all[k]; });
                    if (!refs.length) { if (onDone) { onDone(); } return; }
                    var chunks = [];
                    for (var i = 0; i < refs.length; i += 40) { chunks.push(refs.slice(i, i + 40)); }
                    var done = 0;
                    st.loading = true;
                    kwCountsReformat();
                    kwCountStatus('Loading ' + label + ' counts from Amazon… 0/' + refs.length, false);

                    function finish(msg, isErr) {
                        st.loading = false;
                        kwCountsReformat();
                        kwCountStatus(msg, isErr);
                        if (onDone) { onDone(isErr); }
                    }
                    function next() {
                        if (!chunks.length) { finish('', false); return; }
                        var chunk = chunks.shift();
                        postForm(kwCountUrl, { campaigns: chunk, refresh: !!refsOverride, kind: kind }).then(function (out) {
                            var body = out.body || {};
                            if (body.counts) {
                                Object.keys(body.counts).forEach(function (k) { st.map[k] = body.counts[k]; });
                                cntPersist(kind, Object.keys(body.counts));
                            }
                            (body.unresolved || []).forEach(function (k) { st.map[k] = { total: null, unresolved: true }; });
                            if (!out.ok || body.ok === false) {
                                finish(body.message || ('Could not load ' + label + ' counts (HTTP ' + out.status + '). Use the Counts button to retry.'), true);
                                return;
                            }
                            done += chunk.length;
                            kwCountStatus('Loading ' + label + ' counts from Amazon… ' + done + '/' + refs.length, false);
                            kwCountsReformat();
                            next();
                        }).catch(function () {
                            finish('Network error loading ' + label + ' counts. Use the Counts button to retry.', true);
                        });
                    }
                    next();
                }
                // Keywords first, then targets, then HL (one after the other: Amazon throttles parallel calls).
                function amzLoadAllCounts(data) {
                    kwLastData = Array.isArray(data) ? data : null;
                    amzLoadCounts('kw', null, function (err) {
                        if (!err) { amzLoadCounts('pt', null, function (err2) { if (!err2) { amzLoadCounts('hl', null, null); } }); }
                    });
                }
                var kwCountReloadBtn = document.getElementById('amzKwCountReloadBtn');
                if (kwCountReloadBtn) { kwCountReloadBtn.addEventListener('click', amzLoadAllCounts); }
                table.on('dataLoaded', function (data) { amzLoadAllCounts(data); });
                // Show the counts remembered from before the reload as soon as the rows exist.
                table.on('renderComplete', function () { if (!cntState.kw.loading && !cntState.pt.loading && !cntState.hl.loading) { kwCountsReformat(); } });

                // ---- Keyword list modal (click a KW K Count) ----
                var kwListState = { kind: 'kw', rows: [], campaigns: [], refs: [], parent: '' };
                function kwListNoun(plural) { return kwListState.kind === 'pt' ? (plural ? 'targets' : 'target') : (kwListState.kind === 'hl' ? (plural ? 'keywords / targets' : 'keyword / target') : (plural ? 'keywords' : 'keyword')); }
                function kwListTypeLabel(t) {
                    if (kwListState.kind === 'hl' && (t === 'ASIN' || t === 'CATEGORY')) { return t === 'ASIN' ? 'ASIN' : 'Category'; }
                    if (kwListState.kind === 'pt') { return t === 'AUTO' ? 'Auto group' : (t === 'ASIN' ? 'ASIN' : (t === 'CATEGORY' ? 'Category' : (t || '—'))); }
                    return t ? t.charAt(0) + t.slice(1).toLowerCase() : '—';
                }
                function getKwListModal() {
                    var el = document.getElementById('amzKwListModal');
                    return el && window.bootstrap ? bootstrap.Modal.getOrCreateInstance(el) : null;
                }
                function kwListFiltered() {
                    var camp = (document.getElementById('amzKwListCampaign') || {}).value || '';
                    var mt = (document.getElementById('amzKwListMatch') || {}).value || '';
                    var st = (document.getElementById('amzKwListState') || {}).value || '';
                    var q = String((document.getElementById('amzKwListSearch') || {}).value || '').toLowerCase().trim();
                    return kwListState.rows.filter(function (r) {
                        return (!camp || r.campaign_id === camp)
                            && (!mt || r.match_type === mt)
                            && (!st || r.state === st)
                            && (!q || String(r.keyword).toLowerCase().indexOf(q) !== -1);
                    });
                }
                function kwListRender() {
                    var body = document.getElementById('amzKwListBody');
                    if (!body) { return; }
                    var rows = kwListFiltered();
                    var multi = kwListState.campaigns.length > 1;
                    document.querySelectorAll('.amz-kwlist-camp-col').forEach(function (el) { el.classList.toggle('d-none', !multi); });
                    var shown = rows.slice(0, 1000);
                    body.innerHTML = shown.length ? shown.map(function (r, i) {
                        var mtLabel = kwListTypeLabel(r.match_type);
                        return '<tr><td class="text-muted">' + (i + 1) + '</td><td>' + esc(r.keyword) + '</td><td>' + esc(mtLabel) + '</td>'
                            + '<td class="text-end">' + (r.bid == null ? '—' : Number(r.bid).toFixed(2)) + '</td>'
                            + '<td>' + (r.state === 'ENABLED' ? '<span class="text-success">Enabled</span>' : '<span class="text-muted">' + esc(r.state ? r.state.charAt(0) + r.state.slice(1).toLowerCase() : '—') + '</span>') + '</td>'
                            + (multi ? '<td>' + esc(r.campaign_name) + '</td>' : '<td class="d-none amz-kwlist-camp-col"></td>') + '</tr>';
                    }).join('') : '<tr><td colspan="6" class="text-muted text-center py-3">No ' + kwListNoun(true) + ' to show.</td></tr>';
                    var en = 0, pa = 0;
                    rows.forEach(function (r) { if (r.state === 'ENABLED') { en++; } else if (r.state === 'PAUSED') { pa++; } });
                    document.getElementById('amzKwListSummary').textContent = rows.length.toLocaleString('en-US') + ' ' + kwListNoun(true) + ' (' + en + ' enabled, ' + pa + ' paused)'
                        + (rows.length > shown.length ? ' - showing the first ' + shown.length.toLocaleString('en-US') + ', narrow the filters to see the rest' : '');
                }
                function kwListLoad() {
                    var errEl = document.getElementById('amzKwListError');
                    errEl.classList.add('d-none');
                    document.getElementById('amzKwListBody').innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4"><i class="fa fa-spinner fa-spin me-1"></i>Loading ' + kwListNoun(true) + ' from Amazon…</td></tr>';
                    document.getElementById('amzKwListSummary').textContent = '';
                    postForm(kwListUrl, { campaigns: kwListState.refs, kind: kwListState.kind }).then(function (out) {
                        var b = out.body || {};
                        if (!out.ok || !b.ok) {
                            kwListState.rows = [];
                            kwListRender();
                            errEl.textContent = b.message || ('Could not load ' + kwListNoun(true) + ' (HTTP ' + out.status + '). If this is 404, run php artisan route:clear on the server.');
                            errEl.classList.remove('d-none');
                            return;
                        }
                        kwListState.rows = b.keywords || [];
                        kwListState.campaigns = b.campaigns || [];
                        var sel = document.getElementById('amzKwListCampaign');
                        sel.innerHTML = '<option value="">All campaigns</option>' + kwListState.campaigns.map(function (c) {
                            return '<option value="' + esc(c.campaign_id) + '">' + esc(c.campaign_name || c.campaign_id) + '</option>';
                        }).join('');
                        sel.classList.toggle('d-none', kwListState.campaigns.length < 2);
                        // Keep the table column in sync with what Amazon just returned.
                        kwListState.campaigns.forEach(function (c) {
                            var mine = kwListState.rows.filter(function (r) { return r.campaign_id === c.campaign_id; });
                            var t = { total: mine.length, enabled: 0, paused: 0 };
                            mine.forEach(function (r) { if (r.state === 'ENABLED') { t.enabled++; } else if (r.state === 'PAUSED') { t.paused++; } });
                            (c.keys || []).forEach(function (k) { cntState[kwListState.kind].map[k] = t; });
                            cntPersist(kwListState.kind, c.keys || []);
                        });
                        kwCountsReformat();
                        if (b.truncated) {
                            errEl.textContent = 'Amazon returned ' + Number(b.total).toLocaleString('en-US') + ' ' + kwListNoun(true) + '; only the first 5,000 are listed.';
                            errEl.classList.remove('d-none');
                        }
                        kwListRender();
                    }).catch(function () {
                        errEl.textContent = 'Network error loading ' + kwListNoun(true) + '.';
                        errEl.classList.remove('d-none');
                    });
                }
                function kwListOpen(d, kind) {
                    kind = kind === 'pt' ? 'pt' : (kind === 'hl' ? 'hl' : 'kw');
                    var refs = kwRowRefs(d, kind);
                    if (!refs.length) { return; }
                    kwListState = { kind: kind, rows: [], campaigns: [], refs: refs, parent: d.parent || '' };
                    document.getElementById('amzKwListModalLabel').textContent = (kind === 'pt' ? 'Targets - ' : (kind === 'hl' ? 'HL keywords / targets - ' : 'Keywords - ')) + (d.parent || d.sku || '');
                    document.getElementById('amzKwListThKeyword').textContent = kind === 'pt' ? 'Target' : (kind === 'hl' ? 'Keyword / Target' : 'Keyword');
                    document.getElementById('amzKwListThMatch').textContent = kind === 'pt' ? 'Type' : (kind === 'hl' ? 'Match / Type' : 'Match');
                    document.getElementById('amzKwListSearch').placeholder = kind === 'pt' ? 'Search targets / ASINs' : (kind === 'hl' ? 'Search keywords / targets' : 'Search keywords');
                    document.getElementById('amzKwListMatch').innerHTML = kind === 'pt'
                        ? '<option value="">All types</option><option value="ASIN">ASIN</option><option value="AUTO">Auto group</option><option value="CATEGORY">Category</option>'
                        : kind === 'hl'
                        ? '<option value="">All types</option><option value="BROAD">Broad</option><option value="PHRASE">Phrase</option><option value="EXACT">Exact</option><option value="ASIN">ASIN</option><option value="CATEGORY">Category</option>'
                        : '<option value="">All match types</option><option value="BROAD">Broad</option><option value="PHRASE">Phrase</option><option value="EXACT">Exact</option>';
                    ['amzKwListMatch', 'amzKwListState'].forEach(function (id) { document.getElementById(id).value = ''; });
                    document.getElementById('amzKwListSearch').value = '';
                    document.getElementById('amzKwListCampaign').classList.add('d-none');
                    var m = getKwListModal();
                    if (m) { m.show(); }
                    kwListLoad();
                }
                var tableEl = document.getElementById('amzAdsMissingTable');
                if (tableEl) {
                    tableEl.addEventListener('click', function (e) {
                        var a = e.target.closest ? e.target.closest('.amz-kwcount') : null;
                        if (!a) { return; }
                        e.preventDefault();
                        var row = table.getRow(a.getAttribute('data-sku'));
                        if (row) { kwListOpen(row.getData(), a.getAttribute('data-kind')); }
                        if (!cntState.kw.loading && !cntState.pt.loading && !cntState.hl.loading) { amzLoadAllCounts(); }
                    });
                }
                ['amzKwListCampaign', 'amzKwListMatch', 'amzKwListState'].forEach(function (id) {
                    document.getElementById(id).addEventListener('change', kwListRender);
                });
                document.getElementById('amzKwListSearch').addEventListener('input', kwListRender);
                document.getElementById('amzKwListRefreshBtn').addEventListener('click', kwListLoad);
                document.getElementById('amzKwListCsvBtn').addEventListener('click', function () {
                    var rows = kwListFiltered();
                    var csv = [kwListState.kind === 'pt' ? 'Campaign,Target,Type,Bid,State' : kwListState.kind === 'hl' ? 'Campaign,Keyword / Target,Match / Type,Bid,State' : 'Campaign,Keyword,Match type,Bid,State'].concat(rows.map(function (r) {
                        return [r.campaign_name, r.keyword, r.match_type, r.bid == null ? '' : r.bid, r.state].map(function (v) {
                            return '"' + String(v == null ? '' : v).replace(/"/g, '""') + '"';
                        }).join(',');
                    })).join('\n');
                    var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                    var url = URL.createObjectURL(blob);
                    var link = document.createElement('a');
                    link.href = url;
                    link.download = 'amazon-' + kwListState.kind + '-' + String(kwListState.parent || 'keywords').replace(/[^a-z0-9]+/gi, '-') + '.csv';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    URL.revokeObjectURL(url);
                });

                // ---- CT/UP HL: create a PAUSED HL (Sponsored Brands) campaign, or add child ASINs to a linked one ----
                var hlCreateUrl = @json(route('amazon.ads.missing.hl-create'));
                var hlUpdateUrl = @json(route('amazon.ads.missing.hl-update'));
                function hlModalInstance() {
                    var el = document.getElementById('amzHlModal');
                    return el && window.bootstrap ? bootstrap.Modal.getOrCreateInstance(el) : null;
                }
                function hlMode() {
                    var r = document.querySelector('input[name="amzHlMode"]:checked');
                    var box = document.getElementById('amzHlModeBox');
                    return (r && r.value === 'update' && box && !box.classList.contains('d-none')) ? 'update' : 'new';
                }
                function hlSubmitHtml() {
                    return hlMode() === 'update'
                        ? '<i class="fa fa-save me-1"></i> Update campaign'
                        : '<i class="fa fa-plus me-1"></i> Create campaign';
                }
                function hlApplyMode() {
                    var upd = hlMode() === 'update';
                    document.querySelectorAll('.amz-hl-new-only').forEach(function (el) { el.classList.toggle('d-none', upd); });
                    var sel = document.getElementById('amzHlUpdateCampaign'); if (sel) { sel.classList.toggle('d-none', !upd); }
                    document.getElementById('amzHlSubmitBtn').innerHTML = hlSubmitHtml();
                }
                function hlSelectedChildren() {
                    var rows = [];
                    document.querySelectorAll('#amzHlChildrenBody tr').forEach(function (tr) {
                        var cb = tr.querySelector('.amz-hl-child-check');
                        if (!cb || !cb.checked || cb.disabled) { return; }
                        rows.push({
                            target_sku: (tr.querySelector('.amz-hl-child-sku') || {}).textContent || '',
                            asin: ((tr.querySelector('.amz-hl-child-asin') || {}).textContent || '').replace('— missing ASIN', '').trim()
                        });
                    });
                    return rows;
                }
                function hlShowError(msg) {
                    var el = document.getElementById('amzHlError');
                    el.textContent = msg || '';
                    el.classList.toggle('d-none', !msg);
                }
                function openHlModal(d) {
                    d = d || {};
                    document.getElementById('amzHlParent').value = d.parent || '';
                    document.getElementById('amzHlParentDisplay').value = d.parent || '';
                    document.getElementById('amzHlCampaignName').value = (d.sku || ('PARENT ' + (d.parent || ''))) + ' HL';
                    document.getElementById('amzHlBudget').value = '3';
                    document.getElementById('amzHlHeadline').value = '';
                    document.getElementById('amzHlKeywords').value = '';
                    hlShowError('');
                    var children = Array.isArray(d.children) ? d.children : [];
                    document.getElementById('amzHlChildrenBody').innerHTML = children.map(function (c, idx) {
                        var asin = c.asin || '';
                        var inv = (c.inv == null ? 0 : Number(c.inv)) || 0;
                        var disabled = !asin;
                        var checked = !disabled && inv > 0 ? ' checked' : (!disabled && children.length === 1 ? ' checked' : '');
                        return '<tr class="' + (disabled ? 'table-secondary' : '') + '">'
                            + '<td><input type="checkbox" class="form-check-input amz-hl-child-check" data-idx="' + idx + '"' + (disabled ? ' disabled' : '') + checked + '></td>'
                            + '<td class="amz-hl-child-sku">' + esc(c.target_sku || '') + '</td>'
                            + '<td class="amz-hl-child-asin ' + (asin ? '' : 'text-danger') + '">' + esc(asin || '— missing ASIN') + '</td>'
                            + '<td class="text-end">' + inv.toLocaleString('en-US') + '</td></tr>';
                    }).join('');
                    document.getElementById('amzHlChildCount').textContent = '(' + children.length + ')';
                    var linked = (Array.isArray(d.hl) ? d.hl : []).filter(function (c) { return c && c.campaign_id; });
                    document.getElementById('amzHlUpdateCampaign').innerHTML = linked.map(function (c) {
                        return '<option value="' + esc(c.campaign_id) + '">' + esc(c.campaign_name || c.campaign_id) + '</option>';
                    }).join('');
                    document.getElementById('amzHlModeBox').classList.toggle('d-none', !linked.length);
                    var rn = document.querySelector('input[name="amzHlMode"][value="new"]'); if (rn) { rn.checked = true; }
                    hlApplyMode();
                    var m = hlModalInstance();
                    if (m) { m.show(); }
                }
                document.querySelectorAll('input[name="amzHlMode"]').forEach(function (r) { r.addEventListener('change', hlApplyMode); });
                document.getElementById('amzHlSelectAll').addEventListener('click', function () {
                    document.querySelectorAll('#amzHlChildrenBody .amz-hl-child-check').forEach(function (cb) { if (!cb.disabled) { cb.checked = true; } });
                });
                document.getElementById('amzHlSelectNone').addEventListener('click', function () {
                    document.querySelectorAll('#amzHlChildrenBody .amz-hl-child-check').forEach(function (cb) { cb.checked = false; });
                });
                document.getElementById('amzHlSubmitBtn').addEventListener('click', function () {
                    var btn = document.getElementById('amzHlSubmitBtn');
                    var parent = document.getElementById('amzHlParent').value;
                    var children = hlSelectedChildren();
                    hlShowError('');
                    if (!parent) { hlShowError('Parent is required.'); return; }
                    if (!children.length) { hlShowError('Select at least one child SKU with a valid ASIN.'); return; }
                    var updating = hlMode() === 'update';
                    var payload;
                    var url;
                    if (updating) {
                        url = hlUpdateUrl;
                        payload = { parent: parent, campaign_id: (document.getElementById('amzHlUpdateCampaign') || {}).value || '', children: children };
                        if (!payload.campaign_id) { hlShowError('Pick the campaign to update.'); return; }
                    } else {
                        url = hlCreateUrl;
                        var match = document.getElementById('amzHlMatch').value || 'PHRASE';
                        var bid = parseFloat(document.getElementById('amzHlBid').value) || 0.60;
                        var kws = String(document.getElementById('amzHlKeywords').value || '').split(/\r?\n/).map(function (t) { return t.replace(/\s+/g, ' ').trim(); })
                            .filter(function (t, i, a) { return t !== '' && a.indexOf(t) === i; })
                            .map(function (t) { return { text: t, match_type: match, bid: bid }; });
                        payload = {
                            parent: parent,
                            campaign_name: document.getElementById('amzHlCampaignName').value,
                            budget_amount: parseFloat(document.getElementById('amzHlBudget').value) || 3,
                            headline: document.getElementById('amzHlHeadline').value,
                            children: children,
                            keywords: kws
                        };
                        if (!(payload.campaign_name || '').trim()) { hlShowError('Campaign name is required.'); return; }
                    }
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i> ' + (updating ? 'Updating…' : 'Creating…');
                    postForm(url, payload).then(function (out) {
                        btn.disabled = false;
                        btn.innerHTML = hlSubmitHtml();
                        var b = out.body || {};
                        // The campaign may exist (and be linked) even when a later step failed: always refresh the row.
                        if (b.sku && b.hl) {
                            var r = table.getRow(b.sku);
                            if (r) { r.update({ pt: b.pt || [], kw: b.kw || [], hl: b.hl || [] }); }
                            updateMissingBadges();
                            amzHlPvaLoad(true);
                            amzLoadCounts('hl', null, null);
                        }
                        if (out.ok && b.ok) {
                            var mm = hlModalInstance();
                            if (mm) { mm.hide(); }
                            window.alert(b.message || (updating ? 'HL campaign updated.' : 'HL campaign created.'));
                        } else {
                            hlShowError(b.message || ('Failed (HTTP ' + out.status + ').'));
                        }
                    }).catch(function () {
                        btn.disabled = false;
                        btn.innerHTML = hlSubmitHtml();
                        hlShowError('Network error.');
                    });
                });

                // Count blank PT / KW cells across the current (filtered) view.
                function updateMissingBadges(rowsData) {
                    var rows = rowsData || table.getData('active');
                    var pt = 0;
                    var kw = 0;
                    var hl = 0;
                    rows.forEach(function (r) {
                        // Skip parents with no inventory — they shouldn't inflate the missing count.
                        if (!r || (Number(r.inventory) || 0) <= 0) { return; }
                        if (!r.pt || !r.pt.length) { pt++; }
                        if (!r.kw || !r.kw.length) { kw++; }
                        if (!r.hl || !r.hl.length) { hl++; }
                    });
                    var total = pt + kw + hl;
                    var ptEl = document.getElementById('amzMissingPtValue');
                    var kwEl = document.getElementById('amzMissingKwValue');
                    var missingEl = document.getElementById('amzMissingValue');
                    var missingWrap = document.getElementById('amzMissingWrap');
                    if (ptEl) { ptEl.textContent = Number(pt).toLocaleString('en-US'); }
                    if (kwEl) { kwEl.textContent = Number(kw).toLocaleString('en-US'); }
                    var hlEl = document.getElementById('amzMissingHlValue');
                    if (hlEl) { hlEl.textContent = Number(hl).toLocaleString('en-US'); }
                    if (missingEl) { missingEl.textContent = Number(total).toLocaleString('en-US'); }
                    if (missingWrap) { missingWrap.classList.toggle('is-alert', total > 0); }
                }
                // Total parent rows (whole dataset, independent of filters).
                function updateParentBadge() {
                    var all = table.getData();
                    var parents = all.reduce(function (n, r) { return n + (r && r.is_parent ? 1 : 0); }, 0);
                    var el = document.getElementById('amzParentValue');
                    if (el) { el.textContent = Number(parents).toLocaleString('en-US'); }
                }

                function headerFilterValue(field) {
                    try {
                        return table.getHeaderFilterValue(field) || '';
                    } catch (e) {
                        return '';
                    }
                }

                var MISSING_TYPES = ['kw', 'pt', 'hl'];
                var MISSING_WRAP_IDS = { kw: 'amzMissingKwWrap', pt: 'amzMissingPtWrap', hl: 'amzMissingHlWrap' };
                function otherMissingTypes(type) {
                    return MISSING_TYPES.filter(function (t) { return t !== type; });
                }

                function isMissingTypeBadgeFilterOn(type) {
                    return headerFilterValue(type) === 'missing'
                        && headerFilterValue('inventory') === 'in'
                        && otherMissingTypes(type).every(function (o) { return headerFilterValue(o) !== 'missing'; });
                }

                function syncMissingTypeBadgeActive(type) {
                    var wrap = document.getElementById(MISSING_WRAP_IDS[type]);
                    if (!wrap) { return; }
                    var on = isMissingTypeBadgeFilterOn(type);
                    wrap.classList.toggle('is-active', on);
                    wrap.setAttribute('aria-pressed', on ? 'true' : 'false');
                }

                function syncMissingColumnBadgeActive() {
                    MISSING_TYPES.forEach(syncMissingTypeBadgeActive);
                }

                function toggleMissingTypeFilter(type) {
                    badgePanel.classList.add('d-none');
                    if (isMissingTypeBadgeFilterOn(type)) {
                        table.setHeaderFilterValue(type, '');
                        table.setHeaderFilterValue('inventory', '');
                    } else {
                        otherMissingTypes(type).forEach(function (o) { table.setHeaderFilterValue(o, ''); });
                        table.setHeaderFilterValue('inventory', 'in');
                        table.setHeaderFilterValue(type, 'missing');
                    }
                }

                function bindMissingColumnBadge(wrapId, type) {
                    var el = document.getElementById(wrapId);
                    if (!el) { return; }
                    el.addEventListener('click', function () {
                        toggleMissingTypeFilter(type);
                    });
                    el.addEventListener('keydown', function (e) {
                        if (e.key === 'Enter' || e.key === ' ') {
                            e.preventDefault();
                            toggleMissingTypeFilter(type);
                        }
                    });
                }

                // dataFiltered gives the filtered RowComponents reliably (fires on initial filter, toggle, and header filters).
                table.on('dataFiltered', function (filters, rows) {
                    updateMissingBadges((rows || []).map(function (rc) { return rc.getData(); }));
                    updateParentBadge();
                    syncMissingColumnBadgeActive();
                });

                // Clicking a badge shows the list of parents behind its count.
                var badgePanel = document.createElement('div');
                badgePanel.className = 'amz-badge-panel d-none';
                badgePanel.innerHTML = '<div class="amz-badge-panel-title"></div><div class="amz-badge-panel-list"></div>';
                document.body.appendChild(badgePanel);
                var badgePanelTitle = badgePanel.querySelector('.amz-badge-panel-title');
                var badgePanelList = badgePanel.querySelector('.amz-badge-panel-list');

                function openBadgePanel(anchorEl, title, names) {
                    badgePanelTitle.textContent = title + ' (' + names.length + ')';
                    badgePanelList.innerHTML = names.length
                        ? names.map(function (n) { return '<div class="amz-badge-panel-item" title="' + esc(n) + '">' + esc(n) + '</div>'; }).join('')
                        : '<div class="amz-badge-panel-empty">Nothing to show</div>';
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
                bindBadge('amzParentWrap', 'Parents', function () {
                    return parentNamesFrom(table.getData(), function (r) { return r.is_parent; });
                });
                bindBadge('amzMissingWrap', 'Missing All', function () {
                    return parentNamesFrom(table.getData('active'), function (r) {
                        if (!r || (Number(r.inventory) || 0) <= 0) { return false; }
                        return (!r.pt || !r.pt.length) || (!r.kw || !r.kw.length) || (!r.hl || !r.hl.length);
                    });
                });
                bindMissingColumnBadge('amzMissingKwWrap', 'kw');
                bindMissingColumnBadge('amzMissingPtWrap', 'pt');
                bindMissingColumnBadge('amzMissingHlWrap', 'hl');

                document.addEventListener('click', function (e) {
                    if (badgePanel.classList.contains('d-none')) { return; }
                    if (badgePanel.contains(e.target) || e.target.closest('.amz-missing-badge')) { return; }
                    badgePanel.classList.add('d-none');
                });
                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape') { badgePanel.classList.add('d-none'); }
                });

                var tableEl = document.getElementById('amzAdsMissingTable');

                // Floating campaign picker: "+" opens it, matches against the SP campaign list,
                // and clicking an option saves the link.
                var picker = document.createElement('div');
                picker.className = 'amz-campaign-picker d-none';
                picker.innerHTML = '<input type="text" class="form-control form-control-sm amz-picker-input" placeholder="Search campaign...">'
                    + '<div class="amz-picker-list"></div>';
                document.body.appendChild(picker);
                var pickerInput = picker.querySelector('.amz-picker-input');
                var pickerList = picker.querySelector('.amz-picker-list');
                var pickerCtx = { sku: null, type: null };

                function renderPickerList(filter) {
                    var f = (filter || '').toLowerCase();
                    var matches = campaignNames.filter(function (n) {
                        return n && (!f || String(n).toLowerCase().indexOf(f) !== -1);
                    }).slice(0, 100);
                    if (!matches.length) {
                        pickerList.innerHTML = '<div class="amz-picker-empty">No matching campaigns</div>';
                        return;
                    }
                    pickerList.innerHTML = matches.map(function (n) {
                        return '<div class="amz-picker-option" data-name="' + esc(n) + '" title="' + esc(n) + '">' + esc(n) + '</div>';
                    }).join('');
                }

                function openPicker(btn, sku, type) {
                    pickerCtx.sku = sku;
                    pickerCtx.type = type;
                    var rect = btn.getBoundingClientRect();
                    picker.style.top = (window.scrollY + rect.bottom + 2) + 'px';
                    picker.style.left = (window.scrollX + rect.left) + 'px';
                    picker.classList.remove('d-none');
                    pickerInput.value = '';
                    renderPickerList('');
                    pickerInput.focus();
                }

                function closePicker() {
                    picker.classList.add('d-none');
                    pickerCtx.sku = null;
                    pickerCtx.type = null;
                }

                pickerInput.addEventListener('input', function () {
                    renderPickerList(this.value);
                });

                pickerList.addEventListener('click', function (e) {
                    var opt = e.target.closest('.amz-picker-option');
                    if (!opt) {
                        return;
                    }
                    var name = opt.getAttribute('data-name');
                    var sku = pickerCtx.sku;
                    var type = pickerCtx.type;
                    closePicker();
                    postForm(linkUrl, { sku: sku, type: type, campaign_name: name }).then(function (out) {
                        var r = table.getRow(sku);
                        if (out.ok && out.body && r) {
                            r.update({ pt: out.body.pt, kw: out.body.kw, hl: out.body.hl || [] });
                            updateMissingBadges();
                        } else if (!out.ok) {
                            window.alert('Failed to link campaign.');
                        }
                    });
                });

                document.addEventListener('click', function (e) {
                    if (picker.classList.contains('d-none')) {
                        return;
                    }
                    if (picker.contains(e.target) || e.target.closest('.link-add-btn')) {
                        return;
                    }
                    closePicker();
                });
                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape') {
                        closePicker();
                    }
                });

                tableEl.addEventListener('click', function (e) {
                    // Copy icon — copy the parent name to the clipboard.
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

                    var createBtn = e.target.closest('.amz-create-btn');
                    if (createBtn) {
                        e.preventDefault();
                        e.stopPropagation();
                        var createSku = createBtn.getAttribute('data-sku') || '';
                        var createType = createBtn.getAttribute('data-type') || 'KW';
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
                        if (createRow && createType === 'HL') {
                            openHlModal(createRow.getData());
                        } else if (createRow) {
                            openCreateModal(createRow.getData(), createType);
                        } else {
                            window.alert('Could not load row data for Create.');
                        }
                        return;
                    }

                    // "+" — open the campaign picker for this row + type.
                    var addBtn = e.target.closest('.link-add-btn');
                    if (addBtn) {
                        openPicker(addBtn, addBtn.getAttribute('data-sku'), addBtn.getAttribute('data-type'));
                        return;
                    }

                    // "x" — unlink only (local).
                    var x = e.target.closest('.chip-x');
                    if (x) {
                        var id = x.getAttribute('data-id');
                        var sku2 = x.getAttribute('data-sku');
                        postForm(unlinkUrl, { id: Number(id) }).then(function (out) {
                            if (out.ok && out.body) {
                                var r = table.getRow(sku2);
                                if (r) {
                                    r.update({ pt: out.body.pt, kw: out.body.kw, hl: out.body.hl || [] });
                                }
                                updateMissingBadges();
                            }
                        });
                        return;
                    }

                    // Trash — archive in Amazon Ads + unlink.
                    var trash = e.target.closest('.chip-trash');
                    if (trash) {
                        var delSku = trash.getAttribute('data-sku') || '';
                        var delName = trash.getAttribute('data-campaign-name') || '';
                        var delCid = trash.getAttribute('data-campaign-id') || '';
                        var delLinkId = trash.getAttribute('data-id') || '';
                        var delType = trash.getAttribute('data-type') || '';
                        var confirmMsg = 'Archive campaign in Amz Ads?\n\n'
                            + (delName || delCid)
                            + '\n\nAmazon does not hard-delete campaigns — this sets state to ARCHIVED and unlinks it here.';
                        if (!window.confirm(confirmMsg)) {
                            return;
                        }
                        trash.style.opacity = '0.4';
                        postForm(deleteUrl, {
                            sku: delSku,
                            campaign_id: delCid,
                            campaign_name: delName,
                            link_id: delLinkId ? Number(delLinkId) : null,
                            type: delType || null
                        }).then(function (out) {
                            trash.style.opacity = '';
                            if (out.ok && out.body && out.body.ok) {
                                var rDel = table.getRow(delSku);
                                if (rDel) {
                                    rDel.update({ pt: out.body.pt || [], kw: out.body.kw || [], hl: out.body.hl || [] });
                                }
                                updateMissingBadges();
                                window.alert(out.body.message || 'Campaign archived.');
                            } else {
                                window.alert((out.body && out.body.message) || 'Failed to archive campaign.');
                            }
                        }).catch(function () {
                            trash.style.opacity = '';
                            window.alert('Network error archiving campaign.');
                        });
                    }
                });

                function normalizeNegItem(kw, source) {
                    return { text: String(kw || '').trim(), source: source || 'ai' };
                }
                function mergeUniqueNegItems(a, b) {
                    var out = [];
                    var seen = {};
                    (a || []).concat(b || []).forEach(function (item) {
                        var t = (item && item.text) ? String(item.text).trim() : '';
                        if (!t) { return; }
                        var key = t.toLowerCase();
                        if (seen[key]) { return; }
                        seen[key] = true;
                        out.push({ text: t, source: item.source || 'ai' });
                    });
                    return out;
                }
                function getSuggestedTexts() {
                    return aiNegSuggestedCache.map(function (i) { return i.text; }).filter(Boolean);
                }
                function renderSuggestedList() {
                    var wrap = document.getElementById('amzAiNegSuggestedWrap');
                    var list = document.getElementById('amzAiNegSuggested');
                    var countEl = document.getElementById('amzAiNegSuggestedCount');
                    if (countEl) { countEl.textContent = String(aiNegSuggestedCache.length); }
                    if (!aiNegSuggestedCache.length) {
                        wrap.classList.add('d-none');
                        list.innerHTML = '';
                        list.dataset.copyText = '';
                        return;
                    }
                    wrap.classList.remove('d-none');
                    list.innerHTML = aiNegSuggestedCache.map(function (item, idx) {
                        var badge = item.source === 'manual'
                            ? '<span class="badge text-bg-success ms-1">manual</span>'
                            : '<span class="badge text-bg-primary ms-1">ai</span>';
                        return '<li class="list-group-item d-flex justify-content-between align-items-center py-1 px-2">'
                            + '<span class="small">' + esc(item.text) + badge + '</span>'
                            + '<button type="button" class="btn btn-sm btn-link text-danger amz-ai-neg-del p-0" data-idx="' + idx + '" title="Remove">'
                            + '<i class="fa fa-times"></i></button></li>';
                    }).join('');
                    list.dataset.copyText = getSuggestedTexts().join('\n');
                }
                function addManualNegativeKeyword(raw) {
                    var text = String(raw || '').trim();
                    if (!text) { return { ok: false, reason: 'empty' }; }
                    var key = text.toLowerCase();
                    // Block duplicates already in the push list (AI or manual).
                    for (var i = 0; i < aiNegSuggestedCache.length; i++) {
                        if (String(aiNegSuggestedCache[i].text || '').toLowerCase() === key) {
                            return { ok: false, reason: 'list' };
                        }
                    }
                    // Block duplicates already on Amazon KW(-) for this parent.
                    for (var j = 0; j < aiNegExistingCache.length; j++) {
                        if (String(aiNegExistingCache[j] || '').toLowerCase() === key) {
                            return { ok: false, reason: 'amazon' };
                        }
                    }
                    aiNegSuggestedCache = mergeUniqueNegItems(
                        aiNegSuggestedCache,
                        [normalizeNegItem(text, 'manual')]
                    );
                    renderSuggestedList();
                    return { ok: true };
                }
                function exportNegativesCsv() {
                    var rows = [['keyword', 'source']].concat(
                        aiNegSuggestedCache.map(function (i) { return [i.text, i.source || 'ai']; })
                    );
                    if (document.getElementById('amzAiNegIncludeExisting').checked && aiNegExistingCache.length) {
                        aiNegExistingCache.forEach(function (kw) {
                            rows.push([kw, 'amazon_kw']);
                        });
                    }
                    var csv = rows.map(function (r) {
                        return r.map(function (cell) {
                            var s = String(cell == null ? '' : cell);
                            if (/[",\n]/.test(s)) { return '"' + s.replace(/"/g, '""') + '"'; }
                            return s;
                        }).join(',');
                    }).join('\n');
                    var parent = (aiNegMeta.parent || 'parent').replace(/[^\w\-]+/g, '_');
                    var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                    var url = URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = 'amazon-negative-keywords-' + parent + '-' + new Date().toISOString().slice(0, 10) + '.csv';
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
                        aiNegSuggestedCache = mergeUniqueNegItems(aiNegSuggestedCache, incoming);
                    } else {
                        var manuals = aiNegSuggestedCache.filter(function (i) { return i.source === 'manual'; });
                        aiNegSuggestedCache = mergeUniqueNegItems(manuals, incoming);
                    }
                    aiNegExistingCache = existing.slice();
                    aiNegMeta = {
                        parent: payload.parent || aiNegMeta.parent || '',
                        target_sku: payload.target_sku || aiNegMeta.target_sku || '',
                        product_title: payload.product_title || aiNegMeta.product_title || ''
                    };
                    var existingWrap = document.getElementById('amzAiNegExistingWrap');
                    var existingEl = document.getElementById('amzAiNegExisting');
                    var existingCountEl = document.getElementById('amzAiNegExistingCount');
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
                    document.getElementById('amzAiNegRegenBtn').disabled = !!busy;
                    document.getElementById('amzAiNegAddMoreBtn').disabled = !!busy;
                }
                function runAiNegatives(options) {
                    options = options || {};
                    var append = !!options.append;
                    var parent = document.getElementById('amzCreateParent').value || '';
                    var targetSku = document.getElementById('amzCreateTargetSku').value || '';
                    var campaignName = document.getElementById('amzCreateCampaignName').value || '';
                    var ideas = (document.getElementById('amzAiNegIdeas').value || '').trim();
                    var loading = document.getElementById('amzAiNegLoading');
                    var errEl = document.getElementById('amzAiNegError');
                    var suggestedWrap = document.getElementById('amzAiNegSuggestedWrap');
                    var existingWrap = document.getElementById('amzAiNegExistingWrap');
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
                        aiNegSuggestedCache = aiNegSuggestedCache.filter(function (i) {
                            return i && i.source === 'manual';
                        });
                    }
                    loading.classList.remove('d-none');
                    setAiNegBusy(true);
                    postForm(aiNegUrl, {
                        parent: parent,
                        target_sku: targetSku,
                        campaign_name: campaignName,
                        ideas: ideas,
                        already_suggested: getSuggestedTexts(),
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
                    });
                }

                (document.getElementById('amzCreateAiNegLink') || document.createElement('a')).addEventListener('click', function (e) {
                    e.preventDefault();
                    var modal = getAiNegModal();
                    if (!modal) {
                        window.alert('Could not open AI negatives modal.');
                        return;
                    }
                    document.getElementById('amzAiNegIdeas').value = '';
                    document.getElementById('amzAiNegManualInput').value = '';
                    aiNegSuggestedCache = [];
                    aiNegExistingCache = [];
                    aiNegMeta = {
                        parent: document.getElementById('amzCreateParent').value || '',
                        target_sku: document.getElementById('amzCreateTargetSku').value || '',
                        product_title: ''
                    };
                    var pushOk = document.getElementById('amzAiNegPushOk');
                    if (pushOk) { pushOk.classList.add('d-none'); pushOk.textContent = ''; }
                    var pushErr = document.getElementById('amzAiNegError');
                    if (pushErr) { pushErr.classList.add('d-none'); pushErr.textContent = ''; }
                    modal.show();
                    runAiNegatives({ append: false });
                });
                document.getElementById('amzAiNegRegenBtn').addEventListener('click', function () {
                    runAiNegatives({ append: false });
                });
                document.getElementById('amzAiNegAddMoreBtn').addEventListener('click', function () {
                    runAiNegatives({ append: true });
                });
                document.getElementById('amzAiNegCopyBtn').addEventListener('click', function () {
                    var text = document.getElementById('amzAiNegSuggested').dataset.copyText || '';
                    if (!text) { return; }
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(text).then(function () {
                            window.alert('Copied ' + text.split('\n').filter(Boolean).length + ' negative keyword(s).');
                        });
                    }
                });
                document.getElementById('amzAiNegExportBtn').addEventListener('click', function () {
                    exportNegativesCsv();
                });
                document.getElementById('amzAiNegManualAddBtn').addEventListener('click', function () {
                    var input = document.getElementById('amzAiNegManualInput');
                    var errEl = document.getElementById('amzAiNegError');
                    var okEl = document.getElementById('amzAiNegPushOk');
                    errEl.classList.add('d-none');
                    if (okEl) { okEl.classList.add('d-none'); }
                    var result = addManualNegativeKeyword(input.value);
                    if (!result || !result.ok) {
                        if (result && result.reason === 'amazon') {
                            errEl.textContent = 'Duplicate — already on Amz KW(-) for this parent. Not added.';
                        } else if (result && result.reason === 'list') {
                            errEl.textContent = 'Duplicate — already in the negatives list. Not added.';
                        } else {
                            errEl.textContent = 'Enter a keyword to add.';
                        }
                        errEl.classList.remove('d-none');
                        return;
                    }
                    input.value = '';
                    input.focus();
                });
                document.getElementById('amzAiNegManualInput').addEventListener('keydown', function (e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        document.getElementById('amzAiNegManualAddBtn').click();
                    }
                });
                document.getElementById('amzAiNegSuggested').addEventListener('click', function (e) {
                    var btn = e.target.closest('.amz-ai-neg-del');
                    if (!btn) { return; }
                    var idx = Number(btn.getAttribute('data-idx'));
                    if (!isFinite(idx) || idx < 0 || idx >= aiNegSuggestedCache.length) { return; }
                    aiNegSuggestedCache.splice(idx, 1);
                    renderSuggestedList();
                });
                document.getElementById('amzAiNegPushBtn').addEventListener('click', function () {
                    var btn = document.getElementById('amzAiNegPushBtn');
                    var errEl = document.getElementById('amzAiNegError');
                    var okEl = document.getElementById('amzAiNegPushOk');
                    errEl.classList.add('d-none');
                    errEl.textContent = '';
                    okEl.classList.add('d-none');
                    okEl.textContent = '';
                    // Dedupe push list (case-insensitive). Skip manual/AI terms already on Amazon KW(-)
                    // when "Also push existing" is checked — those are sent once via include_existing.
                    var includeExisting = !!document.getElementById('amzAiNegIncludeExisting').checked;
                    var existingLookup = {};
                    (aiNegExistingCache || []).forEach(function (kw) {
                        var k = String(kw || '').trim().toLowerCase();
                        if (k) { existingLookup[k] = true; }
                    });
                    var seenPush = {};
                    var keywords = [];
                    getSuggestedTexts().forEach(function (kw) {
                        var t = String(kw || '').trim();
                        if (!t) { return; }
                        var key = t.toLowerCase();
                        if (seenPush[key]) { return; }
                        if (includeExisting && existingLookup[key]) { return; }
                        seenPush[key] = true;
                        keywords.push(t);
                    });
                    if (!keywords.length && !includeExisting) {
                        errEl.textContent = 'Add keywords (AI or manual), or enable existing KW(-) negatives.';
                        errEl.classList.remove('d-none');
                        return;
                    }
                    var payload = {
                        parent: document.getElementById('amzCreateParent').value || '',
                        campaign_name: document.getElementById('amzCreateCampaignName').value || '',
                        campaign_id: lastCreatedCampaignId || '',
                        keywords: keywords,
                        include_existing: includeExisting,
                        match_type: document.getElementById('amzAiNegMatchType').value || 'PHRASE'
                    };
                    if (!payload.parent) {
                        errEl.textContent = 'Parent is missing. Open Create from a parent row first.';
                        errEl.classList.remove('d-none');
                        return;
                    }
                    if (!payload.campaign_id) {
                        // Soft warn only — backend still tries link/SP-report lookup by name.
                        console.info('Push negatives: no local campaign_id yet; resolving by campaign name / links.');
                    }
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i> Pushing…';
                    postForm(pushNegUrl, payload).then(function (out) {
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

                // ---- AI Positive Keywords (mirror negatives) ----
                function getPosSuggestedTexts() {
                    return aiPosSuggestedCache.map(function (i) { return i.text; }).filter(Boolean);
                }
                function renderPosSuggestedList() {
                    var wrap = document.getElementById('amzAiPosSuggestedWrap');
                    var list = document.getElementById('amzAiPosSuggested');
                    var countEl = document.getElementById('amzAiPosSuggestedCount');
                    if (countEl) { countEl.textContent = String(aiPosSuggestedCache.length); }
                    if (!aiPosSuggestedCache.length) {
                        wrap.classList.add('d-none');
                        list.innerHTML = '';
                        list.dataset.copyText = '';
                        return;
                    }
                    wrap.classList.remove('d-none');
                    list.innerHTML = aiPosSuggestedCache.map(function (item, idx) {
                        var badge = item.source === 'manual'
                            ? '<span class="badge text-bg-success ms-1">manual</span>'
                            : '<span class="badge text-bg-primary ms-1">ai</span>';
                        return '<li class="list-group-item d-flex justify-content-between align-items-center py-1 px-2">'
                            + '<span class="small">' + esc(item.text) + badge + '</span>'
                            + '<button type="button" class="btn btn-sm btn-link text-danger amz-ai-pos-del p-0" data-idx="' + idx + '" title="Remove">'
                            + '<i class="fa fa-times"></i></button></li>';
                    }).join('');
                    list.dataset.copyText = getPosSuggestedTexts().join('\n');
                }
                function addManualPositiveKeyword(raw) {
                    var text = String(raw || '').trim();
                    if (!text) { return { ok: false, reason: 'empty' }; }
                    var key = text.toLowerCase();
                    for (var i = 0; i < aiPosSuggestedCache.length; i++) {
                        if (String(aiPosSuggestedCache[i].text || '').toLowerCase() === key) {
                            return { ok: false, reason: 'list' };
                        }
                    }
                    for (var j = 0; j < aiPosExistingCache.length; j++) {
                        if (String(aiPosExistingCache[j] || '').toLowerCase() === key) {
                            return { ok: false, reason: 'amazon' };
                        }
                    }
                    aiPosSuggestedCache = mergeUniqueNegItems(
                        aiPosSuggestedCache,
                        [normalizeNegItem(text, 'manual')]
                    );
                    renderPosSuggestedList();
                    return { ok: true };
                }
                function exportPositivesCsv() {
                    var rows = [['keyword', 'source']].concat(
                        aiPosSuggestedCache.map(function (i) { return [i.text, i.source || 'ai']; })
                    );
                    if (document.getElementById('amzAiPosIncludeExisting').checked && aiPosExistingCache.length) {
                        aiPosExistingCache.forEach(function (kw) { rows.push([kw, 'amazon_kw']); });
                    }
                    var csv = rows.map(function (r) {
                        return r.map(function (cell) {
                            var s = String(cell == null ? '' : cell);
                            if (/[",\n]/.test(s)) { return '"' + s.replace(/"/g, '""') + '"'; }
                            return s;
                        }).join(',');
                    }).join('\n');
                    var parent = (aiPosMeta.parent || 'parent').replace(/[^\w\-]+/g, '_');
                    var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                    var url = URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = 'amazon-positive-keywords-' + parent + '-' + new Date().toISOString().slice(0, 10) + '.csv';
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                }
                function renderAiPositives(payload, options) {
                    options = options || {};
                    var existing = Array.isArray(payload.existing) ? payload.existing : [];
                    var suggested = Array.isArray(payload.suggested) ? payload.suggested : [];
                    var incoming = suggested.map(function (kw) { return normalizeNegItem(kw, 'ai'); });
                    if (options.append) {
                        aiPosSuggestedCache = mergeUniqueNegItems(aiPosSuggestedCache, incoming);
                    } else {
                        var manuals = aiPosSuggestedCache.filter(function (i) { return i.source === 'manual'; });
                        aiPosSuggestedCache = mergeUniqueNegItems(manuals, incoming);
                    }
                    aiPosExistingCache = existing.slice();
                    aiPosMeta = {
                        parent: payload.parent || aiPosMeta.parent || '',
                        target_sku: payload.target_sku || aiPosMeta.target_sku || '',
                        product_title: payload.product_title || aiPosMeta.product_title || ''
                    };
                    var existingWrap = document.getElementById('amzAiPosExistingWrap');
                    var existingEl = document.getElementById('amzAiPosExisting');
                    var existingCountEl = document.getElementById('amzAiPosExistingCount');
                    if (existingCountEl) { existingCountEl.textContent = String(existing.length); }
                    if (existing.length) {
                        existingWrap.classList.remove('d-none');
                        existingEl.textContent = existing.join(', ');
                    } else {
                        existingWrap.classList.add('d-none');
                        existingEl.textContent = '';
                    }
                    renderPosSuggestedList();
                }
                function setAiPosBusy(busy) {
                    document.getElementById('amzAiPosRegenBtn').disabled = !!busy;
                    document.getElementById('amzAiPosAddMoreBtn').disabled = !!busy;
                }
                function runAiPositives(options) {
                    options = options || {};
                    var append = !!options.append;
                    var parent = document.getElementById('amzCreateParent').value || '';
                    var targetSku = document.getElementById('amzCreateTargetSku').value || '';
                    var campaignName = document.getElementById('amzCreateCampaignName').value || '';
                    var ideas = (document.getElementById('amzAiPosIdeas').value || '').trim();
                    var loading = document.getElementById('amzAiPosLoading');
                    var errEl = document.getElementById('amzAiPosError');
                    var suggestedWrap = document.getElementById('amzAiPosSuggestedWrap');
                    var existingWrap = document.getElementById('amzAiPosExistingWrap');
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
                        aiPosSuggestedCache = aiPosSuggestedCache.filter(function (i) {
                            return i && i.source === 'manual';
                        });
                    }
                    loading.classList.remove('d-none');
                    setAiPosBusy(true);
                    postForm(aiPosUrl, {
                        parent: parent,
                        target_sku: targetSku,
                        campaign_name: campaignName,
                        ideas: ideas,
                        already_suggested: getPosSuggestedTexts(),
                        mode: append ? 'add_more' : 'generate'
                    }).then(function (out) {
                        loading.classList.add('d-none');
                        setAiPosBusy(false);
                        if (out.ok && out.body && out.body.ok) {
                            renderAiPositives(out.body, { append: append });
                        } else {
                            errEl.textContent = (out.body && out.body.message) || 'Failed to generate positive keywords.';
                            errEl.classList.remove('d-none');
                            if (append && aiPosSuggestedCache.length) {
                                suggestedWrap.classList.remove('d-none');
                            }
                        }
                    }).catch(function () {
                        loading.classList.add('d-none');
                        setAiPosBusy(false);
                        errEl.textContent = 'Network error generating positive keywords.';
                        errEl.classList.remove('d-none');
                    });
                }

                (document.getElementById('amzCreateAiPosLink') || document.createElement('a')).addEventListener('click', function (e) {
                    e.preventDefault();
                    if (currentCreateType() === 'PT') {
                        window.alert('Positive keywords require a KW / MANUAL campaign. Use CT/UP KW.');
                        return;
                    }
                    lastCreatedCampaignId = lastCreatedCampaignId
                        || pickLinkedCampaignId(lastCreateRowData || {}, 'KW')
                        || '';
                    var modal = getAiPosModal();
                    if (!modal) {
                        window.alert('Could not open AI positives modal.');
                        return;
                    }
                    document.getElementById('amzAiPosIdeas').value = '';
                    document.getElementById('amzAiPosManualInput').value = '';
                    aiPosSuggestedCache = [];
                    aiPosExistingCache = [];
                    aiPosMeta = {
                        parent: document.getElementById('amzCreateParent').value || '',
                        target_sku: document.getElementById('amzCreateTargetSku').value || '',
                        product_title: ''
                    };
                    var pushOk = document.getElementById('amzAiPosPushOk');
                    if (pushOk) { pushOk.classList.add('d-none'); pushOk.textContent = ''; }
                    var pushErr = document.getElementById('amzAiPosError');
                    if (pushErr) { pushErr.classList.add('d-none'); pushErr.textContent = ''; }
                    modal.show();
                    runAiPositives({ append: false });
                });
                document.getElementById('amzAiPosRegenBtn').addEventListener('click', function () {
                    runAiPositives({ append: false });
                });
                document.getElementById('amzAiPosAddMoreBtn').addEventListener('click', function () {
                    runAiPositives({ append: true });
                });
                document.getElementById('amzAiPosCopyBtn').addEventListener('click', function () {
                    var text = document.getElementById('amzAiPosSuggested').dataset.copyText || '';
                    if (!text) { return; }
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(text).then(function () {
                            window.alert('Copied ' + text.split('\n').filter(Boolean).length + ' positive keyword(s).');
                        });
                    }
                });
                document.getElementById('amzAiPosExportBtn').addEventListener('click', function () {
                    exportPositivesCsv();
                });
                document.getElementById('amzAiPosManualAddBtn').addEventListener('click', function () {
                    var input = document.getElementById('amzAiPosManualInput');
                    var errEl = document.getElementById('amzAiPosError');
                    errEl.classList.add('d-none');
                    var result = addManualPositiveKeyword(input.value);
                    if (!result || !result.ok) {
                        if (result && result.reason === 'amazon') {
                            errEl.textContent = 'Duplicate — already on Amz KW(+) for this parent. Not added.';
                        } else if (result && result.reason === 'list') {
                            errEl.textContent = 'Duplicate — already in the positives list. Not added.';
                        } else {
                            errEl.textContent = 'Enter a keyword to add.';
                        }
                        errEl.classList.remove('d-none');
                        return;
                    }
                    input.value = '';
                    input.focus();
                });
                document.getElementById('amzAiPosManualInput').addEventListener('keydown', function (e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        document.getElementById('amzAiPosManualAddBtn').click();
                    }
                });
                document.getElementById('amzAiPosSuggested').addEventListener('click', function (e) {
                    var btn = e.target.closest('.amz-ai-pos-del');
                    if (!btn) { return; }
                    var idx = Number(btn.getAttribute('data-idx'));
                    if (!isFinite(idx) || idx < 0 || idx >= aiPosSuggestedCache.length) { return; }
                    aiPosSuggestedCache.splice(idx, 1);
                    renderPosSuggestedList();
                });
                document.getElementById('amzAiPosPushBtn').addEventListener('click', function () {
                    var btn = document.getElementById('amzAiPosPushBtn');
                    var errEl = document.getElementById('amzAiPosError');
                    var okEl = document.getElementById('amzAiPosPushOk');
                    errEl.classList.add('d-none');
                    errEl.textContent = '';
                    okEl.classList.add('d-none');
                    okEl.textContent = '';
                    var includeExisting = !!document.getElementById('amzAiPosIncludeExisting').checked;
                    var existingLookup = {};
                    (aiPosExistingCache || []).forEach(function (kw) {
                        var k = String(kw || '').trim().toLowerCase();
                        if (k) { existingLookup[k] = true; }
                    });
                    var seenPush = {};
                    var keywords = [];
                    getPosSuggestedTexts().forEach(function (kw) {
                        var t = String(kw || '').trim();
                        if (!t) { return; }
                        var key = t.toLowerCase();
                        if (seenPush[key]) { return; }
                        if (includeExisting && existingLookup[key]) { return; }
                        seenPush[key] = true;
                        keywords.push(t);
                    });
                    if (!keywords.length && !includeExisting) {
                        errEl.textContent = 'Add keywords (AI or manual), or enable existing KW(+) positives.';
                        errEl.classList.remove('d-none');
                        return;
                    }
                    var payload = {
                        parent: document.getElementById('amzCreateParent').value || '',
                        campaign_name: document.getElementById('amzCreateCampaignName').value || '',
                        campaign_id: lastCreatedCampaignId || '',
                        ad_group_id: lastCreatedAdGroupId || '',
                        keywords: keywords,
                        include_existing: includeExisting,
                        match_type: document.getElementById('amzAiPosMatchType').value || 'PHRASE',
                        bid: parseFloat(document.getElementById('amzAiPosBid').value) || 0.5
                    };
                    if (!payload.parent) {
                        errEl.textContent = 'Parent is missing. Open Create from a parent row first.';
                        errEl.classList.remove('d-none');
                        return;
                    }
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i> Pushing…';
                    postForm(pushPosUrl, payload).then(function (out) {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fa fa-cloud-upload-alt me-1"></i> Push Positive Keywords';
                        if (out.ok && out.body && out.body.ok) {
                            if (out.body.campaign_id) {
                                lastCreatedCampaignId = String(out.body.campaign_id);
                            }
                            if (out.body.ad_group_id) {
                                lastCreatedAdGroupId = String(out.body.ad_group_id);
                            }
                            okEl.textContent = out.body.message || 'Positive keywords pushed.';
                            okEl.classList.remove('d-none');
                        } else {
                            errEl.textContent = (out.body && out.body.message) || 'Failed to push positive keywords.';
                            errEl.classList.remove('d-none');
                        }
                    }).catch(function () {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fa fa-cloud-upload-alt me-1"></i> Push Positive Keywords';
                        errEl.textContent = 'Network error pushing positive keywords.';
                        errEl.classList.remove('d-none');
                    });
                });

                kwWire();
                kwWireIdeas();
                document.getElementById('amzCreateSelectAll').addEventListener('click', function () {
                    document.querySelectorAll('#amzCreateChildrenBody .amz-child-check').forEach(function (cb) {
                        if (!cb.disabled) { cb.checked = true; }
                    });
                    syncCreateAiHiddenFromSelection();
                });
                document.getElementById('amzCreateSelectNone').addEventListener('click', function () {
                    document.querySelectorAll('#amzCreateChildrenBody .amz-child-check').forEach(function (cb) {
                        cb.checked = false;
                    });
                    syncCreateAiHiddenFromSelection();
                });
                document.getElementById('amzCreateChildrenBody').addEventListener('change', function (e) {
                    if (e.target && e.target.classList.contains('amz-child-check')) {
                        syncCreateAiHiddenFromSelection();
                        if (currentCreateType() === 'KW') { kwSetStatus('SKU selection changed — click Reload to refresh Amazon\'s suggestions.', false); }
                    }
                });

                document.querySelectorAll('input[name="amzCreateMode"]').forEach(function (r) { r.addEventListener('change', amzApplyMode); });

                document.getElementById('amzCreateSubmitBtn').addEventListener('click', function () {
                    var btn = document.getElementById('amzCreateSubmitBtn');
                    var errEl = document.getElementById('amzCreateError');
                    errEl.classList.add('d-none');
                    errEl.textContent = '';
                    syncCreateAiHiddenFromSelection();

                    // Update an existing campaign: add the picked keywords / negatives to it.
                    if (amzMode() === 'update') {
                        var updPayload = {
                            parent: document.getElementById('amzCreateParent').value,
                            campaign_id: (document.getElementById('amzUpdateCampaign') || {}).value || '',
                            default_bid: parseFloat(document.getElementById('amzCreateDefaultBid').value) || 0.60,
                            keywords: currentCreateType() === 'KW'
                                ? kwAdded.map(function (k) { return { text: k.text, match_type: k.match_type, bid: k.bid }; })
                                : []
                        };
                        if (currentCreateType() === 'PT') {
                            Object.assign(updPayload, ptPayload());
                            // When updating, only change the groups left in the list; never pause the others.
                            updPayload.pt_groups = (updPayload.pt_groups || []).filter(function (g) { return g.enabled; });
                        }
                        if (!updPayload.campaign_id) {
                            errEl.textContent = 'Pick the campaign to update.';
                            errEl.classList.remove('d-none');
                            return;
                        }
                        if (currentCreateType() === 'KW' && !updPayload.keywords.length) {
                            errEl.textContent = 'Add at least one keyword to update the campaign.';
                            errEl.classList.remove('d-none');
                            return;
                        }
                        if (currentCreateType() === 'PT' && updPayload.pt_mode === 'AUTO' && !updPayload.pt_groups.length) {
                            errEl.textContent = 'Add at least one auto targeting group to update the campaign.';
                            errEl.classList.remove('d-none');
                            return;
                        }
                        if (currentCreateType() === 'PT' && updPayload.pt_mode === 'MANUAL' && !updPayload.pt_targets.length) {
                            errEl.textContent = 'Add at least one valid ASIN to update the campaign.';
                            errEl.classList.remove('d-none');
                            return;
                        }
                        btn.disabled = true;
                        btn.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i> Updating…';
                        postForm(updateUrl, updPayload).then(function (out) {
                            btn.disabled = false;
                            btn.innerHTML = amzSubmitHtml();
                            if (out.ok && out.body && out.body.ok) {
                                kwAdded = [];
                                ptAsins = [];
                                kwRenderAdded();
                                kwRenderSuggestions();
                                ptRender();
                                amzLoadCounts(currentCreateType() === 'PT' ? 'pt' : 'kw', [{ key: String(updPayload.campaign_id), campaign_id: String(updPayload.campaign_id), campaign_name: '' }]);
                                window.alert(out.body.message || 'Campaign updated.');
                            } else {
                                errEl.textContent = (out.body && out.body.message) || ('Failed to update campaign (HTTP ' + out.status + ').');
                                errEl.classList.remove('d-none');
                            }
                        }).catch(function () {
                            btn.disabled = false;
                            btn.innerHTML = amzSubmitHtml();
                            errEl.textContent = 'Network error updating campaign.';
                            errEl.classList.remove('d-none');
                        });
                        return;
                    }

                    var children = collectSelectedChildren();
                    var payload = {
                        parent: document.getElementById('amzCreateParent').value,
                        campaign_name: document.getElementById('amzCreateCampaignName').value,
                        budget_amount: parseFloat(document.getElementById('amzCreateBudget').value) || 3,
                        default_bid: parseFloat(document.getElementById('amzCreateDefaultBid').value) || 0.60,
                        type: currentCreateType(),
                        children: children,
                        keywords: currentCreateType() === 'KW'
                            ? kwAdded.map(function (k) { return { text: k.text, match_type: k.match_type, bid: k.bid }; })
                            : [],
                        bidding_strategy: document.getElementById('amzCreateStrategy').value || 'LEGACY_FOR_SALES',
                        top_of_search_pct: parseInt(document.getElementById('amzCreateTopPct').value, 10) || 0,
                        product_pages_pct: parseInt(document.getElementById('amzCreateProdPct').value, 10) || 0
                    };
                    if (payload.type === 'PT') { Object.assign(payload, ptPayload()); }
                    if (!payload.parent || !(payload.campaign_name || '').trim()) {
                        errEl.textContent = 'Parent and campaign name are required.';
                        errEl.classList.remove('d-none');
                        return;
                    }
                    if (!children.length) {
                        errEl.textContent = 'Select at least one child SKU with a valid ASIN.';
                        errEl.classList.remove('d-none');
                        return;
                    }
                    if (payload.type === 'KW' && !payload.keywords.length) {
                        errEl.textContent = 'Add at least one keyword in Keyword targeting (Suggested, Enter list or Upload file) before creating a KW campaign.';
                        errEl.classList.remove('d-none');
                        return;
                    }
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i> Creating…';
                    postForm(createUrl, payload).then(function (out) {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fa fa-plus me-1"></i> Create campaign';
                        if (out.ok && out.body && out.body.ok) {
                            var sku = out.body.sku || ('PARENT ' + payload.parent);
                            var r = table.getRow(sku);
                            if (r) {
                                r.update({ pt: out.body.pt || [], kw: out.body.kw || [], hl: out.body.hl || [] });
                            }
                            if (out.body.campaign_id) {
                                lastCreatedCampaignId = String(out.body.campaign_id);
                            }
                            if (out.body.ad_group_id) {
                                lastCreatedAdGroupId = String(out.body.ad_group_id);
                            }
                            if (out.body.campaign_name) {
                                document.getElementById('amzCreateCampaignName').value = out.body.campaign_name;
                            }
                            updateMissingBadges();
                            // Keep Create modal open so AI Negatives/Positives → Push can use the new campaign id.
                            var followUp = '';
                            window.alert((out.body.message || 'Campaign created.') + followUp);
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

            // Load the SP campaign list first (for the picker), then build the grid.
            fetch(campaignsUrl, { headers: { 'Accept': 'application/json' } })
                .then(function (res) { return res.json(); })
                .then(function (json) {
                    var names = (json && Array.isArray(json.data)) ? json.data.map(function (c) { return c.campaign_name; }) : [];
                    buildTable(names);
                })
                .catch(function () { buildTable([]); });
        })();
    </script>
@endsection
