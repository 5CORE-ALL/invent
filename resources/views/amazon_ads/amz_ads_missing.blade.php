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
        .amz-ads-missing .amz-missing-badge--kw {
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
                        <div class="amz-missing-badge amz-missing-badge--missing" id="amzMissingWrap" title="Missing: Missing PT + Missing KW (in-stock rows in the current view).">
                            <span class="amz-missing-badge-label">Missing</span>
                            <span class="amz-missing-badge-value tabular-nums" id="amzMissingValue">0</span>
                        </div>
                        <div class="amz-missing-badge amz-missing-badge--pt" id="amzMissingPtWrap" title="Missing PT: in-stock rows (inventory > 0) in the current view with no linked PT campaign.">
                            <span class="amz-missing-badge-label">Missing PT</span>
                            <span class="amz-missing-badge-value tabular-nums" id="amzMissingPtValue">0</span>
                        </div>
                        <div class="amz-missing-badge amz-missing-badge--kw" id="amzMissingKwWrap" role="button" tabindex="0" aria-pressed="false" title="Missing KW: in-stock rows (inventory > 0) with no linked KW campaign. Click to show only those rows; click again to clear.">
                            <span class="amz-missing-badge-label">Missing KW</span>
                            <span class="amz-missing-badge-value tabular-nums" id="amzMissingKwValue">0</span>
                        </div>
                        <button type="button" class="btn btn-success btn-sm ms-auto" id="amzMissingExportBtn" title="Export the current (filtered) view to CSV">
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
    <div class="modal fade" id="amzCreateCampaignModal" tabindex="-1" aria-labelledby="amzCreateCampaignModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="amzCreateCampaignModalLabel">Create Amz SP Campaign (KW / MANUAL)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2 small mb-3">
                        Creates <strong>one PAUSED</strong> Sponsored Products <strong>KW / MANUAL</strong> campaign for the parent
                        (e.g. <code>PARENT PMX KW</code>). Selected child SKUs become product ads
                        (Amz seller rule: ads use seller <code>sku</code>; ASIN is shown for verification).
                        Campaign negative keywords support Phrase / Exact only (not Broad).
                    </div>
                    <form id="amzCreateCampaignForm">
                        <input type="hidden" id="amzCreateParent" name="parent">
                        <div class="row g-2 mb-2">
                            <div class="col-md-4">
                                <label class="form-label small mb-1">Parent</label>
                                <input type="text" class="form-control form-control-sm" id="amzCreateParentDisplay" readonly>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label small mb-1">Campaign name</label>
                                <input type="text" class="form-control form-control-sm" id="amzCreateCampaignName" name="campaign_name" required placeholder="PARENT PMX" maxlength="128">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small mb-1">Daily budget ($)</label>
                                <input type="number" class="form-control form-control-sm" id="amzCreateBudget" name="budget_amount" min="1" step="0.01" value="3">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small mb-1">Default bid ($)</label>
                                <input type="number" class="form-control form-control-sm" id="amzCreateDefaultBid" name="default_bid" min="0.02" step="0.01" value="0.60">
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

                        <div class="mt-2 d-flex flex-wrap gap-3">
                            <a href="#" id="amzCreateAiNegLink" class="small fw-semibold">
                                <i class="fa fa-magic me-1"></i> Generate AI negative keywords
                            </a>
                            <a href="#" id="amzCreateAiPosLink" class="small fw-semibold text-success">
                                <i class="fa fa-magic me-1"></i> Generate AI positive keywords
                            </a>
                        </div>
                        <div class="form-text small mt-1">
                            Campaigns are created as KW/MANUAL so positive keywords can be pushed.
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
            var deleteUrl = @json(route('amazon.ads.missing.delete'));
            var aiNegUrl = @json(route('amazon.ads.missing.ai-negatives'));
            var pushNegUrl = @json(route('amazon.ads.missing.push-negatives'));
            var aiPosUrl = @json(route('amazon.ads.missing.ai-positives'));
            var pushPosUrl = @json(route('amazon.ads.missing.push-positives'));
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

            function chipsFormatter(type) {
                return function (cell) {
                    var d = cell.getData();
                    var list = (type === 'PT' ? d.pt : d.kw) || [];
                    var chips = list.map(function (c) {
                        var canArchive = !!(c && (c.campaign_id || c.campaign_name));
                        return '<span class="link-chip" title="' + esc(c.campaign_name) + '">'
                            + statusDot(c)
                            + esc(c.campaign_name)
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
                    return chips
                        + '<button type="button" class="link-add-btn" data-sku="' + esc(d.sku) + '" data-type="' + type + '" title="Link the selected campaign as ' + type + '"><i class="fa fa-plus"></i></button>';
                };
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
                }).then(function (res) { return res.json().then(function (b) { return { ok: res.ok, body: b }; }); });
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
                var t = String(type || 'PT').toUpperCase() === 'KW' ? 'KW' : 'PT';
                var base = stripCampaignTypeSuffix(input.value);
                if (!base) {
                    var parent = document.getElementById('amzCreateParent').value || '';
                    base = parent ? ('PARENT ' + parent) : 'PARENT';
                }
                input.value = base + ' ' + t;
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

            function openCreateModal(d) {
                lastCreateRowData = d || null;
                // Prefer an already-linked campaign id so Push works without re-create.
                lastCreatedCampaignId = pickLinkedCampaignId(d, 'KW');
                lastCreatedAdGroupId = '';
                document.getElementById('amzCreateParent').value = d.parent || '';
                document.getElementById('amzCreateParentDisplay').value = d.parent || '';
                document.getElementById('amzCreateCampaignName').value = d.sku || ('PARENT ' + (d.parent || ''));
                document.getElementById('amzCreateBudget').value = '3';
                document.getElementById('amzCreateDefaultBid').value = '0.60';
                applyCampaignTypeSuffix('KW');
                // If a KW chip already exists, use that campaign name for push targeting.
                var preferList = (Array.isArray(d.kw) && d.kw.length) ? d.kw : [];
                if (preferList.length && preferList[0].campaign_name) {
                    document.getElementById('amzCreateCampaignName').value = preferList[0].campaign_name;
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
                var modal = getCreateModal();
                if (modal) { modal.show(); }
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
                            cssClass: 'text-primary', widthGrow: 1, tooltip: true,
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
                            title: 'Create', field: 'sku', width: 80,
                            hozAlign: 'center', headerHozAlign: 'center',
                            headerSort: false,
                            formatter: function (cell) {
                                var d = cell.getData();
                                var hasPt = Array.isArray(d.pt) && d.pt.length > 0;
                                var title = hasPt
                                    ? 'PT already linked — create another AUTO campaign?'
                                    : 'Create Amz AUTO SP campaign for this parent';
                                return '<button type="button" class="amz-create-btn" data-sku="' + esc(d.sku || '') + '" title="' + esc(title) + '">'
                                    + '<i class="fa fa-plus"></i></button>';
                            }
                        },
                        {
                            title: 'Campaign KW', field: 'kw', widthGrow: 2,
                            hozAlign: 'center', headerHozAlign: 'center',
                            headerSort: true, sorter: linkCountSorter,
                            headerFilter: 'list',
                            headerFilterParams: { values: { '': 'All', missing: 'Missing', linked: 'Linked' } },
                            headerFilterFunc: missingHeaderFilter,
                            formatter: chipsFormatter('KW'),
                            accessorDownload: linkNamesAccessor
                        },
                        {
                            title: 'Campaign PT', field: 'pt', widthGrow: 2,
                            hozAlign: 'center', headerHozAlign: 'center',
                            headerSort: true, sorter: linkCountSorter,
                            headerFilter: 'list',
                            headerFilterParams: { values: { '': 'All', missing: 'Missing', linked: 'Linked' } },
                            headerFilterFunc: missingHeaderFilter,
                            formatter: chipsFormatter('PT'),
                            accessorDownload: linkNamesAccessor
                        }
                    ]
                });

                table.on('tableBuilt', function () {
                    table.setFilter(onlyParentsFilter);
                });

                // Export the current (filtered) view to CSV.
                var exportBtn = document.getElementById('amzMissingExportBtn');
                if (exportBtn) {
                    exportBtn.addEventListener('click', function () {
                        var stamp = new Date().toISOString().slice(0, 10);
                        table.download('csv', 'amazon-ads-missing-' + stamp + '.csv');
                    });
                }

                // Count blank PT / KW cells across the current (filtered) view.
                function updateMissingBadges(rowsData) {
                    var rows = rowsData || table.getData('active');
                    var pt = 0;
                    var kw = 0;
                    rows.forEach(function (r) {
                        // Skip parents with no inventory — they shouldn't inflate the missing count.
                        if (!r || (Number(r.inventory) || 0) <= 0) { return; }
                        if (!r.pt || !r.pt.length) { pt++; }
                        if (!r.kw || !r.kw.length) { kw++; }
                    });
                    var total = pt + kw;
                    var ptEl = document.getElementById('amzMissingPtValue');
                    var kwEl = document.getElementById('amzMissingKwValue');
                    var missingEl = document.getElementById('amzMissingValue');
                    var missingWrap = document.getElementById('amzMissingWrap');
                    if (ptEl) { ptEl.textContent = Number(pt).toLocaleString('en-US'); }
                    if (kwEl) { kwEl.textContent = Number(kw).toLocaleString('en-US'); }
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

                function isMissingKwBadgeFilterOn() {
                    try {
                        return table.getHeaderFilterValue('kw') === 'missing'
                            && table.getHeaderFilterValue('inventory') === 'in';
                    } catch (e) {
                        return false;
                    }
                }

                function syncMissingKwBadgeActive() {
                    var kwWrap = document.getElementById('amzMissingKwWrap');
                    if (!kwWrap) { return; }
                    var on = isMissingKwBadgeFilterOn();
                    kwWrap.classList.toggle('is-active', on);
                    kwWrap.setAttribute('aria-pressed', on ? 'true' : 'false');
                }

                function toggleMissingKwFilter() {
                    badgePanel.classList.add('d-none');
                    if (isMissingKwBadgeFilterOn()) {
                        table.setHeaderFilterValue('kw', '');
                        table.setHeaderFilterValue('inventory', '');
                    } else {
                        table.setHeaderFilterValue('inventory', 'in');
                        table.setHeaderFilterValue('kw', 'missing');
                    }
                }

                // dataFiltered gives the filtered RowComponents reliably (fires on initial filter, toggle, and header filters).
                table.on('dataFiltered', function (filters, rows) {
                    updateMissingBadges((rows || []).map(function (rc) { return rc.getData(); }));
                    updateParentBadge();
                    syncMissingKwBadgeActive();
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
                bindBadge('amzMissingWrap', 'Missing', function () {
                    return parentNamesFrom(table.getData('active'), function (r) {
                        if (!r || (Number(r.inventory) || 0) <= 0) { return false; }
                        return (!r.pt || !r.pt.length) || (!r.kw || !r.kw.length);
                    });
                });
                bindBadge('amzMissingPtWrap', 'Missing PT', function () {
                    return parentNamesFrom(table.getData('active'), function (r) { return (Number(r.inventory) || 0) > 0 && (!r.pt || !r.pt.length); });
                });
                var missingKwWrap = document.getElementById('amzMissingKwWrap');
                if (missingKwWrap) {
                    missingKwWrap.addEventListener('click', function () {
                        toggleMissingKwFilter();
                    });
                    missingKwWrap.addEventListener('keydown', function (e) {
                        if (e.key === 'Enter' || e.key === ' ') {
                            e.preventDefault();
                            toggleMissingKwFilter();
                        }
                    });
                }

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
                            r.update({ pt: out.body.pt, kw: out.body.kw });
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
                                    r.update({ pt: out.body.pt, kw: out.body.kw });
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
                                    rDel.update({ pt: out.body.pt || [], kw: out.body.kw || [] });
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

                document.getElementById('amzCreateAiNegLink').addEventListener('click', function (e) {
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

                document.getElementById('amzCreateAiPosLink').addEventListener('click', function (e) {
                    e.preventDefault();
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
                    }
                });

                document.getElementById('amzCreateSubmitBtn').addEventListener('click', function () {
                    var btn = document.getElementById('amzCreateSubmitBtn');
                    var errEl = document.getElementById('amzCreateError');
                    errEl.classList.add('d-none');
                    errEl.textContent = '';
                    syncCreateAiHiddenFromSelection();
                    var children = collectSelectedChildren();
                    var payload = {
                        parent: document.getElementById('amzCreateParent').value,
                        campaign_name: document.getElementById('amzCreateCampaignName').value,
                        budget_amount: parseFloat(document.getElementById('amzCreateBudget').value) || 3,
                        default_bid: parseFloat(document.getElementById('amzCreateDefaultBid').value) || 0.60,
                        type: 'KW',
                        children: children
                    };
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
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i> Creating…';
                    postForm(createUrl, payload).then(function (out) {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fa fa-plus me-1"></i> Create campaign';
                        if (out.ok && out.body && out.body.ok) {
                            var sku = out.body.sku || ('PARENT ' + payload.parent);
                            var r = table.getRow(sku);
                            if (r) {
                                r.update({ pt: out.body.pt || [], kw: out.body.kw || [] });
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
                            window.alert((out.body.message || 'Campaign created.')
                                + '\n\nYou can now Generate AI negative/positive keywords and Push.');
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
