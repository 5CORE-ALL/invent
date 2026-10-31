@extends('layouts.vertical', ['title' => 'Marketplaces - Keyword Targeting'])

@section('css')
<meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
<style>
    .amz-fbm-targeting .tabulator { font-size: 13px; border: 1px solid #dee2e6; border-radius: 0 0 8px 8px; }
    .amz-fbm-targeting .tabulator .tabulator-header {
        background: #dbeafe; border-bottom: 1px solid #93c5fd; font-weight: 600;
        position: sticky; top: var(--tz-topbar-height, 70px); z-index: 24;
    }
    .amz-fbm-targeting .tabulator .tabulator-header .tabulator-col .tabulator-col-title {
        font-size: 12.5px; text-align: center; padding: 6px 4px;
    }
    .amz-fbm-targeting .tabulator-row { min-height: 36px; }
    .amz-fbm-targeting .tabulator-row:hover { background-color: #f8fafc !important; }
    .amz-fbm-targeting .tabulator-cell { padding: 6px 8px !important; }
    .amz-fbm-targeting .aft-loadbar { background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:10px 14px; margin-bottom:10px; }
    .amz-fbm-targeting .aft-loadbar-top { display:flex; justify-content:space-between; gap:12px; font-size:.85rem; font-weight:600; color:#1e3a8a; margin-bottom:6px; }
    .amz-fbm-targeting .aft-status-dot { display: inline-block; width: 12px; height: 12px; border-radius: 50%; box-shadow: 0 0 0 2px rgba(0,0,0,.06); vertical-align: middle; }
    .amz-fbm-targeting .aft-low-badge { font-size: .78rem; cursor: pointer; user-select: none; border: 2px solid transparent; }
    .amz-fbm-targeting .aft-low-badge.is-active { border-color: #111827; box-shadow: 0 0 0 2px #fde68a; }
    .amz-fbm-targeting .aft-count-badge {
        display: inline-block; padding: 2px 10px; border-radius: 999px;
        background: #1d4ed8; border: 1px solid #1e40af; color: #fff;
        font-size: 12px; font-weight: 700; cursor: pointer;
    }
    .amz-fbm-targeting .aft-count-badge:hover { background: #1e40af; }
    .amz-fbm-targeting .aft-neg-badge { background: #b91c1c; border-color: #991b1b; }
    .amz-fbm-targeting .aft-neg-badge:hover { background: #991b1b; }
    .amz-fbm-targeting .aft-sneg-badge { background: #9a3412; border-color: #7c2d12; }
    .amz-fbm-targeting .aft-sneg-badge:hover { background: #7c2d12; }
    .amz-fbm-targeting .aft-spec-badge { background: #047857; border-color: #065f46; }
    .amz-fbm-targeting .aft-spec-badge:hover { background: #065f46; }
    #sk-list .sk-item {
        display: flex; align-items: center; justify-content: space-between; gap: 10px;
        padding: 6px 10px; border: 1px solid #a7f3d0; background: #ecfdf5; border-radius: 8px; margin-bottom: 6px; font-size: 13px;
    }
    #sk-list .aft-chip-text { flex: 1; min-width: 0; word-break: break-word; }
    #sk-list .aft-chip-match {
        padding: 2px 8px; border-radius: 999px; background: #047857; color: #fff; font-size: 11px; font-weight: 700;
    }
    .aft-empty { color: #94a3b8; font-size: 13px; }
    /* Targets modal: full screen height (top to bottom), list scrolls inside */
    #aftTargetsModal .modal-dialog { height: 100%; margin: 0 auto; max-width: 820px; }
    #aftTargetsModal .modal-content { height: 100%; border-radius: 0; }
    #aftTargetsModal .modal-body { display: flex; flex-direction: column; min-height: 0; overflow: hidden; padding: 12px 16px; }
    #aftTargetsModal { --aft-cols: 22px minmax(0, 1fr) 96px 84px 84px 76px 100px; }
    #aftTargetsModal.is-neg { --aft-cols: 22px minmax(0, 1fr) 150px 100px; }
    #aftTargetsModal.is-neg .aft-metric { display: none; }
    #aft-scroll { flex: 1 1 auto; min-height: 0; overflow-y: auto; padding-right: 4px; }
    #aft-modal-targets.aft-modal-list { display: flex; flex-direction: column; gap: 6px; padding-top: 6px; }
    #aftTargetsModal .aft-head { position: sticky; top: 0; z-index: 2; background: #fff; padding-bottom: 2px; border-bottom: 2px solid #c7d2fe; }
    #aftTargetsModal .aft-head-row { display: grid; grid-template-columns: var(--aft-cols); align-items: center; gap: 10px; padding: 4px 10px; }
    #aftTargetsModal .aft-head-titles { font-size: 12px; font-weight: 700; color: #334155; }
    #aftTargetsModal .aft-sort { cursor: pointer; user-select: none; white-space: nowrap; }
    #aftTargetsModal .aft-sort:hover { color: #1d4ed8; }
    #aftTargetsModal .aft-sort .aft-arrow { display: inline-block; width: 10px; color: #1d4ed8; }
    #aftTargetsModal .aft-metric { text-align: right; }
    #aftTargetsModal .aft-head-filters .form-control, #aftTargetsModal .aft-head-filters .form-select { padding: 2px 6px; font-size: 12px; height: 26px; }
    #aftTargetsModal .aft-head-filters input.is-bad { border-color: #dc2626; background: #fef2f2; }
    #aftTargetsModal .aft-stats { width: 100%; margin-bottom: 8px; font-size: 12px; text-align: center; border-collapse: collapse; }
    #aftTargetsModal .aft-stats th, #aftTargetsModal .aft-stats td { border: 1px solid #e2e8f0; padding: 3px 8px; }
    #aftTargetsModal .aft-stats thead th { background: #f1f5f9; color: #475569; font-weight: 700; }
    #aftTargetsModal .aft-stats tbody th { text-align: left; background: #f8fafc; color: #334155; }
    #aftTargetsModal .aft-stats tr.aft-stats-sel td, #aftTargetsModal .aft-stats tr.aft-stats-sel th { background: #eff6ff; color: #1d4ed8; font-weight: 700; }
    #aftTargetsModal .aft-toolbar { display: flex; align-items: center; gap: 10px; padding: 4px 10px; margin-bottom: 6px; font-size: 13px; color: #475569; }
    #aftTargetsModal .aft-toolbar .aft-sel-text { flex: 1; }
    #aftTargetsModal .aft-editpanel { display: none; border: 1px solid #fcd34d; background: #fffbeb; border-radius: 8px; padding: 10px 12px; margin-bottom: 8px; font-size: 13px; }
    #aftTargetsModal .aft-editpanel.show { display: block; }
    #aftTargetsModal .aft-editpanel label { font-size: 12px; font-weight: 600; color: #92400e; margin-bottom: 2px; }
    #aft-modal-targets .aft-chip {
        display: grid;
        grid-template-columns: var(--aft-cols);
        align-items: center;
        gap: 10px;
        width: 100%;
        padding: 6px 10px;
        border-radius: 8px;
        background: #eef2ff;
        border: 1px solid #c7d2fe;
        color: #1e293b;
        font-size: 13px;
        line-height: 1.35;
    }
    #aft-modal-targets .aft-chip .aft-metric { font-variant-numeric: tabular-nums; }
    #aft-modal-targets .aft-chip .aft-cvr { font-weight: 700; color: #047857; }
    #aft-modal-targets .aft-chip .aft-acts { display: flex; gap: 2px; justify-content: flex-end; }
    #aft-modal-targets .aft-chip.is-selected { background: #dbeafe; border-color: #60a5fa; }
    #aft-modal-targets .aft-chip.is-ignored { opacity: .6; background: #f1f5f9; border-color: #cbd5e1; }
    #aft-modal-targets .aft-chip.is-ignored .aft-chip-text { text-decoration: line-through; }
    #aft-modal-targets .aft-chip input[type=checkbox], #aftTargetsModal .aft-toolbar input[type=checkbox] { width: 16px; height: 16px; flex-shrink: 0; cursor: pointer; }
    #aft-modal-targets .aft-chip-text { min-width: 0; word-break: break-word; }
    #aft-modal-targets .aft-chip-match {
        justify-self: start;
        padding: 2px 8px;
        border-radius: 999px;
        background: #1d4ed8;
        color: #fff;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.02em;
    }
    #aftTargetsModal .aft-ico {
        flex-shrink: 0; width: 28px; height: 28px; padding: 0; border: 1px solid transparent; border-radius: 6px;
        background: transparent; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; font-size: 13px;
    }
    #aftTargetsModal .aft-ico:disabled { opacity: .4; cursor: not-allowed; }
    #aftTargetsModal .aft-ico-edit { color: #1d4ed8; }
    #aftTargetsModal .aft-ico-edit:hover:not(:disabled) { background: #dbeafe; border-color: #93c5fd; }
    #aftTargetsModal .aft-ico-del { color: #b91c1c; }
    #aftTargetsModal .aft-ico-del:hover:not(:disabled) { background: #fee2e2; border-color: #fca5a5; }
    #aftTargetsModal .aft-ico-ign { color: #b45309; }
    #aftTargetsModal .aft-ico-ign:hover:not(:disabled) { background: #fef3c7; border-color: #fcd34d; }
    #aftTargetsModal .aft-ico-ign.is-on { background: #fde68a; border-color: #f59e0b; }
    .amz-fbm-targeting .kwc-btn {
        display: inline-flex; align-items: center; gap: 6px; padding: 2px 10px; border-radius: 6px;
        background: #fff; border: 1px solid #1d4ed8; color: #1d4ed8; font-size: 12px; font-weight: 700; cursor: pointer;
    }
    .amz-fbm-targeting .kwc-btn:hover { background: #eff6ff; }
    .amz-fbm-targeting .kwc-btn .kwc-n {
        background: #1d4ed8; color: #fff; border-radius: 999px; padding: 0 7px; font-size: 11px; line-height: 18px;
    }
    .amz-fbm-targeting .kwc-btn.kwc-empty .kwc-n { background: #94a3b8; }
    #kwc-current, #kwc-results { max-height: 220px; overflow-y: auto; }
    #kwc-chips .kwc-chip {
        display: inline-flex; align-items: center; gap: 6px; padding: 2px 8px; border-radius: 999px;
        background: #1d4ed8; color: #fff; font-size: 12px; font-weight: 600;
    }
    #kwc-chips .kwc-chip button { background: none; border: 0; color: #fff; padding: 0; line-height: 1; cursor: pointer; }
    #kwc-current .kwc-item, #kwc-results .kwc-item {
        display: flex; align-items: center; justify-content: space-between; gap: 10px;
        padding: 6px 10px; border: 1px solid #c7d2fe; background: #eef2ff; border-radius: 8px; margin-bottom: 6px; font-size: 13px;
    }
    #kwc-results .kwc-item { background: #fff; border-color: #e2e8f0; cursor: pointer; justify-content: flex-start; }
    #kwc-results .kwc-item:hover { background: #f8fafc; }
    #kwc-results .kwc-parent { color: #64748b; font-size: 11px; margin-left: auto; }
</style>
@endsection

@section('content')
<div class="container-fluid mt-4 amz-fbm-targeting">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h4 class="mb-0"><i class="mdi mdi-bullseye-arrow"></i> Keyword Targeting</h4>
                    <button type="button" id="aft-push-kw" class="btn btn-warning btn-sm fw-bold"
                            title="Send the keywords in the app to the KW campaigns and the HL campaigns on Amazon">
                        <i class="fas fa-upload"></i> Push KW to KW + HL Campaigns
                    </button>
                    <span class="small">Campaign names from the same SP reports table as Ads All. Targets from Amazon Ads (keywords + product targets).</span>
                </div>
                <div class="card-body">
                    <div class="mb-3 d-flex gap-2 flex-wrap align-items-center">
                        <input type="text" id="aft-cousin-search" class="form-control form-control-sm"
                               style="max-width: 260px;" placeholder="Search KW cousins..." autocomplete="off"
                               title="Filter by cousin name, cousin parent or cousin campaign">
                        <select id="aft-scope" class="form-select form-select-sm" style="max-width: 190px;" title="Show parent campaigns or single-SKU campaigns">
                            <option value="all">Parent + SKU</option>
                            <option value="parent">Parent</option>
                            <option value="sku">SKU</option>
                        </select>
                        <input type="text" id="aft-parent-search" class="form-control form-control-sm"
                               style="max-width: 170px;" placeholder="Search parents..." autocomplete="off">
                        <input type="text" id="aft-title-search" class="form-control form-control-sm"
                               style="max-width: 240px;" placeholder="Search title..." autocomplete="off">
                        <input type="text" id="aft-search" class="form-control form-control-sm"
                               style="max-width: 240px;" placeholder="Search campaigns..." autocomplete="off">
                        <span class="text-muted small" id="aft-count"></span>
                        <span class="badge bg-danger" id="aft-missing-titles" style="font-size:.78rem;"
                              title="Parents with no title in Product Master">Missing Titles: 0</span>
                        <span class="badge bg-danger" id="aft-missing-cousins" style="font-size:.78rem;"
                              title="Parents with no KW cousins">Missing KW cousins: 0</span>
                        <span class="badge bg-primary aft-low-badge" role="button" tabindex="0" data-low="kw" id="aft-low-kw"
                              title="Campaigns with fewer than 50 keywords. Click to filter.">KW &lt; 50: 0</span>
                        <span class="badge bg-primary aft-low-badge" role="button" tabindex="0" data-low="neg" id="aft-low-neg"
                              title="Campaigns with fewer than 50 negative keywords. Click to filter.">Neg KW &lt; 50: 0</span>
                        <span class="badge bg-primary aft-low-badge" role="button" tabindex="0" data-low="spec" id="aft-low-spec"
                              title="Campaigns with fewer than 10 specific keywords. Click to filter.">Specific KW &lt; 10: 0</span>
                        <span class="badge bg-primary aft-low-badge" role="button" tabindex="0" data-low="sneg" id="aft-low-sneg"
                              title="Campaigns with fewer than 10 specific negative keywords. Click to filter.">Specific neg KW &lt; 10: 0</span>
                        <span class="small text-muted" id="aft-lt-coverage"
                              title="Clicks LT / Sold LT add up the stored daily Amazon Ads campaign reports. Amazon only serves about the last 95 days."></span>
                        <button type="button" id="aft-pull-lt" class="btn btn-sm btn-info ms-auto"
                                title="Fetch missing daily campaign reports (clicks / sold) from the Amazon Ads API for the Clicks LT / Sold LT columns. Runs in the background.">
                            <i class="fas fa-chart-line"></i> Pull LT Clicks / Sold
                        </button>
                        <button type="button" id="aft-pull-kw" class="btn btn-sm btn-success"
                                title="Add keywords that exist in Amazon KW campaigns but not in the app. Existing keywords are kept.">
                            <i class="fas fa-cloud-download-alt"></i> Pull Keywords
                        </button>
                        <button type="button" id="aft-pull-neg" class="btn btn-sm btn-danger"
                                title="Add negative keywords that exist in Amazon KW campaigns but not in the app. Existing negative keywords are kept.">
                            <i class="fas fa-ban"></i> Pull Negative KW
                        </button>
                    </div>
                    <div id="aft-pull-msg" class="small mb-2" style="display:none;"></div>
                    <div id="aft-loadbar" class="aft-loadbar" style="display:none;" role="status" aria-live="polite">
                        <div class="aft-loadbar-top">
                            <span id="aft-loadbar-text">Loading campaigns…</span>
                            <span id="aft-loadbar-eta" class="text-muted"></span>
                        </div>
                        <div class="progress" style="height:10px;">
                            <div id="aft-loadbar-fill" class="progress-bar progress-bar-striped progress-bar-animated" style="width:0%"></div>
                        </div>
                    </div>
                    <div id="aft-table"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="aftTargetsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-bullseye"></i> <span id="aft-modal-title">Targets</span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="aft-modal-msg" class="small mb-1" style="display:none;"></div>
                <table class="aft-stats" id="aft-modal-stats"></table>
                <div class="aft-toolbar">
                    <span class="aft-sel-text" id="aft-sel-text"></span>
                    <button type="button" class="aft-ico aft-ico-edit" id="aft-bulk-edit" title="Edit selected"><i class="fas fa-pen"></i></button>
                    <button type="button" class="aft-ico aft-ico-del" id="aft-bulk-del" title="Delete selected"><i class="fas fa-trash"></i></button>
                    <button type="button" class="aft-ico aft-ico-ign" id="aft-bulk-ign" title="Ignore selected"><i class="fas fa-ban"></i></button>
                </div>
                <div class="aft-editpanel" id="aft-editpanel">
                    <div class="fw-bold mb-2" id="aft-edit-title">Edit</div>
                    <div class="row g-2">
                        <div class="col-12" id="aft-edit-single">
                            <label for="aft-edit-text">Keyword</label>
                            <input type="text" class="form-control form-control-sm" id="aft-edit-text" maxlength="512">
                        </div>
                        <div class="col-6" id="aft-edit-find-wrap">
                            <label for="aft-edit-find">Find text</label>
                            <input type="text" class="form-control form-control-sm" id="aft-edit-find" maxlength="512" placeholder="in every selected keyword">
                        </div>
                        <div class="col-6" id="aft-edit-repl-wrap">
                            <label for="aft-edit-replace">Replace with</label>
                            <input type="text" class="form-control form-control-sm" id="aft-edit-replace" maxlength="512">
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="aft-edit-match">Match type</label>
                            <select class="form-select form-select-sm" id="aft-edit-match"></select>
                        </div>
                        <div class="col-12 col-md-6 d-flex align-items-end justify-content-end gap-2">
                            <button type="button" class="btn btn-sm btn-light border" id="aft-edit-cancel">Cancel</button>
                            <button type="button" class="btn btn-sm btn-primary" id="aft-edit-apply">Apply</button>
                        </div>
                    </div>
                </div>
                <div id="aft-scroll">
                    <div class="aft-head">
                        <div class="aft-head-row aft-head-titles">
                            <input type="checkbox" id="aft-sel-all" title="Select all shown">
                            <span class="aft-sort" data-sort="text">Keyword <span class="aft-arrow"></span></span>
                            <span class="aft-sort" data-sort="match">Match <span class="aft-arrow"></span></span>
                            <span class="aft-sort aft-metric" data-sort="clicks" title="Total clicks, lifetime, across all campaigns in the KW cousin group (stored daily Amazon Ads keyword reports)">Clicks LT <span class="aft-arrow"></span></span>
                            <span class="aft-sort aft-metric" data-sort="sold" title="Total sold, lifetime, across all campaigns in the KW cousin group (stored daily Amazon Ads keyword reports)">Sold LT <span class="aft-arrow"></span></span>
                            <span class="aft-sort aft-metric" data-sort="cvr" title="CVR % = Sold LT / Clicks LT x 100">CVR % <span class="aft-arrow"></span></span>
                            <span></span>
                        </div>
                        <div class="aft-head-row aft-head-filters">
                            <span></span>
                            <input type="text" class="form-control form-control-sm" data-filter="text" placeholder="Filter keyword" autocomplete="off">
                            <select class="form-select form-select-sm" data-filter="match"></select>
                            <input type="text" class="form-control form-control-sm aft-metric" data-filter="clicks" placeholder="e.g. >10" autocomplete="off" title="Number, >n, >=n, <n, <=n, =n or a-b">
                            <input type="text" class="form-control form-control-sm aft-metric" data-filter="sold" placeholder="e.g. >=1" autocomplete="off" title="Number, >n, >=n, <n, <=n, =n or a-b">
                            <input type="text" class="form-control form-control-sm aft-metric" data-filter="cvr" placeholder="e.g. >5" autocomplete="off" title="Number, >n, >=n, <n, <=n, =n or a-b (in %)">
                            <span></span>
                        </div>
                    </div>
                    <div id="aft-modal-targets" class="aft-modal-list"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="pushKwModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="fas fa-upload"></i> Push keywords to Amazon</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-2">
                    Keywords stored in the app are added to every KW campaign, and each parent's keywords (all of its KW campaigns together)
                    are added to its HL campaign. Keywords already live on a campaign (same text + match type) are skipped.
                    Only Broad / Phrase / Exact keywords are sent. Negative keywords are not touched.
                </p>
                <div id="pk-summary" class="fw-semibold mb-2"></div>
                <div id="pk-toolbar" class="d-none align-items-center gap-2 flex-wrap mb-2">
                    <input type="text" id="pk-filter" class="form-control form-control-sm" style="max-width: 260px;"
                           placeholder="Filter campaigns..." autocomplete="off">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="pk-sel-shown" title="Tick every campaign currently shown">Select shown</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="pk-sel-none" title="Untick every campaign currently shown">Clear shown</button>
                    <span class="small text-muted ms-auto" id="pk-sel-text"></span>
                </div>
                <div id="pk-table-wrap" style="max-height: 420px; overflow-y: auto;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Close</button>
                <button type="button" id="pk-confirm" class="btn btn-warning btn-sm fw-bold" disabled>
                    <i class="fas fa-upload"></i> Push now
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="specificModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-key"></i> <span id="sk-heading">Specific KW</span> <span class="fw-normal">- <span id="sk-title"></span></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">
                    <span id="sk-desc">Keywords added by hand for this campaign only. They are never imported from Amazon and never shared with KW cousins.</span>
                </p>
                <div class="row g-2 mb-2">
                    <div class="col-md-9">
                        <textarea id="sk-input" class="form-control form-control-sm" rows="3"
                                  placeholder="Type keywords, one per line (or comma separated)"></textarea>
                    </div>
                    <div class="col-md-3">
                        <select id="sk-match" class="form-select form-select-sm mb-2">
                            <option value="">No match type</option>
                            <option value="BROAD">Broad</option>
                            <option value="PHRASE">Phrase</option>
                            <option value="EXACT">Exact</option>
                        </select>
                        <button type="button" id="sk-add" class="btn btn-success btn-sm w-100">
                            <i class="fas fa-plus"></i> Add
                        </button>
                    </div>
                </div>
                <div id="sk-msg" class="small mb-2" style="display:none;"></div>
                <h6 class="mb-2"><span id="sk-list-heading">Specific keywords</span> <span class="text-muted small" id="sk-count"></span></h6>
                <div id="sk-list" style="max-height: 300px; overflow-y: auto;"></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="kwcModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-link"></i> KW Cousins <span class="fw-normal">- <span id="kwc-title"></span></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">
                    Cousins share the same target keywords and negative keywords. They are copied between all cousins
                    of a group (same keyword + match type is never duplicated).
                </p>
                <h6 class="mb-2">Cousin name</h6>
                <div class="input-group input-group-sm mb-3">
                    <input type="text" id="kwc-name" class="form-control" maxlength="191" placeholder="Name this cousin group (e.g. Cargo Straps)" autocomplete="off">
                    <button type="button" id="kwc-name-save" class="btn btn-primary"><i class="fas fa-save"></i> Save name</button>
                </div>
                <h6 class="mb-2">Current cousins <span class="text-muted small" id="kwc-current-count"></span></h6>
                <div id="kwc-current" class="mb-3"></div>

                <h6 class="mb-2">Add parents as KW cousins <span class="text-muted small fw-normal">(all campaigns under a parent join automatically)</span></h6>
                <input type="text" id="kwc-search" class="form-control form-control-sm mb-2" placeholder="Type to filter parents..." autocomplete="off">
                <div class="d-flex align-items-center gap-3 mb-2 small">
                    <label class="mb-0"><input type="checkbox" id="kwc-all"> Select all shown</label>
                    <a href="#" id="kwc-clear" class="text-decoration-none">Clear selection</a>
                    <span class="text-muted ms-auto" id="kwc-shown"></span>
                </div>
                <div id="kwc-chips" class="d-flex flex-wrap gap-1 mb-2"></div>
                <div id="kwc-results" class="mb-2"></div>
                <div id="kwc-msg" class="small" style="display:none;"></div>
            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" id="kwc-sync" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-sync"></i> Sync keywords now
                </button>
                <div>
                    <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Close</button>
                    <button type="button" id="kwc-add" class="btn btn-sm btn-success" disabled>
                        <i class="fas fa-plus"></i> Add &amp; Sync (<span id="kwc-sel-n">0</span>)
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
(function () {
    const dataUrl = @json(route('amazon.ads.fbm-targeting.data'));
    const searchEl = document.getElementById('aft-search');
    const parentSearchEl = document.getElementById('aft-parent-search');
    const titleSearchEl = document.getElementById('aft-title-search');
    const scopeEl = document.getElementById('aft-scope');
    const cousinSearchEl = document.getElementById('aft-cousin-search');
    const countEl = document.getElementById('aft-count');
    const missingTitlesEl = document.getElementById('aft-missing-titles');
    const missingCousinsEl = document.getElementById('aft-missing-cousins');
    const ltCoverageEl = document.getElementById('aft-lt-coverage');
    const lowBadgeEls = Array.prototype.slice.call(document.querySelectorAll('.aft-low-badge'));
    const lowLabels = { kw: 'KW', neg: 'Neg KW', spec: 'Specific KW', sneg: 'Specific neg KW' };
    let lowFilter = '';
    let searchTimer = null;

    function escapeHtml(s) {
        return String(s ?? '').replace(/[&<>"']/g, function (c) {
            return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]);
        });
    }

    // ---- Loading bar with an estimated time (shown only when a load is slow) ----
    // The estimate is a rolling average of this browser's previous load times (default 6 s the first time).
    const loadBar = (function () {
        const box = document.getElementById('aft-loadbar');
        const fill = document.getElementById('aft-loadbar-fill');
        const eta = document.getElementById('aft-loadbar-eta');
        const text = document.getElementById('aft-loadbar-text');
        const KEY = 'aft_load_ms';
        let started = 0, showTimer = null, tick = null;

        function estimate() {
            const v = Number(localStorage.getItem(KEY));
            return v > 500 ? v : 6000;
        }
        function paint() {
            const est = estimate();
            const elapsed = Date.now() - started;
            let pct;
            if (elapsed < est) {
                pct = (elapsed / est) * 90;
                const left = Math.max(1, Math.ceil((est - elapsed) / 1000));
                eta.textContent = 'about ' + left + 's left';
                text.textContent = 'Loading campaigns…';
            } else {
                // Past the estimate: creep toward 99% so the bar never looks stuck at 100%.
                pct = 90 + 9 * (1 - Math.exp(-(elapsed - est) / (est * 2)));
                eta.textContent = 'almost there · ' + Math.round(elapsed / 1000) + 's elapsed';
                text.textContent = 'Taking longer than usual…';
            }
            fill.style.width = Math.min(99, pct).toFixed(1) + '%';
        }
        return {
            start: function () {
                this.stop(false);
                started = Date.now();
                showTimer = setTimeout(function () {
                    box.style.display = '';
                    paint();
                    tick = setInterval(paint, 250);
                }, 700);
            },
            stop: function (record) {
                if (record && started) {
                    const took = Date.now() - started;
                    const prev = Number(localStorage.getItem(KEY));
                    const next = prev > 0 ? Math.round(prev * 0.6 + took * 0.4) : took;
                    try { localStorage.setItem(KEY, String(next)); } catch (e) { /* storage unavailable */ }
                }
                clearTimeout(showTimer);
                clearInterval(tick);
                showTimer = tick = null;
                started = 0;
                if (box.style.display !== 'none') {
                    fill.style.width = '100%';
                    setTimeout(function () { box.style.display = 'none'; }, 150);
                }
            },
        };
    })();

    // Clicks LT / Sold LT: totals over the whole KW cousin group of the row (hover for how many campaigns had data).
    function ltFormatter(cell) {
        const d = cell.getRow().getData() || {};
        const n = Number(cell.getValue() || 0);
        const total = Number(d.lt_campaigns || 0), withData = Number(d.lt_campaigns_with_data || 0);
        const tip = 'Across ' + total.toLocaleString() + ' campaign' + (total === 1 ? '' : 's') + ' in the KW cousin group (' +
            withData.toLocaleString() + ' with report data)';
        return '<span title="' + escapeHtml(tip) + '"' + (withData === 0 ? ' class="text-muted"' : '') + '>' + n.toLocaleString() + '</span>';
    }

    const table = new Tabulator('#aft-table', {
        layout: 'fitDataFill', // every column sizes to its data (capped below for long text); the table fills any space left
        placeholder: 'Loading campaigns…',
        pagination: true,
        paginationMode: 'remote',
        paginationSize: 50,
        paginationSizeSelector: [25, 50, 100],
        ajaxURL: dataUrl,
        ajaxConfig: 'GET',
        filterMode: 'remote',
        sortMode: 'remote',
        ajaxRequestFunc: function (url, _config, params) {
            const q = new URLSearchParams();
            q.set('page', String(params.page || 1));
            q.set('size', String(params.size || 50));
            const search = (searchEl && searchEl.value || '').trim();
            if (search) q.set('campaign', search);
            const parentQ = (parentSearchEl && parentSearchEl.value || '').trim();
            if (parentQ) q.set('parent', parentQ);
            const titleQ = (titleSearchEl && titleSearchEl.value || '').trim();
            if (titleQ) q.set('title', titleQ);
            const cousinQ = (cousinSearchEl && cousinSearchEl.value || '').trim();
            if (cousinQ) q.set('cousin', cousinQ);
            const scope = scopeEl ? scopeEl.value : 'all';
            if (scope && scope !== 'all') q.set('scope', scope);
            if (lowFilter) q.set('low', lowFilter);
            const srt = Array.isArray(params.sort) && params.sort.length ? params.sort[0] : null;
            if (srt && (srt.field === 'lt_clicks' || srt.field === 'lt_sold')) {
                q.set('sort', srt.field);
                q.set('dir', srt.dir === 'asc' ? 'asc' : 'desc');
            }
            loadBar.start();
            return fetch(url + '?' + q.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (res) {
                if (!res.ok) {
                    loadBar.stop(false);
                    return res.text().then(function (body) {
                        let reason = '';
                        try { reason = (JSON.parse(body) || {}).message || ''; } catch (e) { /* not JSON */ }
                        showPullMsg('Could not load campaigns (server error ' + res.status + ')' + (reason ? ': ' + reason : '. Please try again.'), false);
                        if (pullMsg) pullMsg.dataset.loadErr = '1';
                        throw new Error('Network response was not ok');
                    });
                }
                return res.json();
            }).then(function (json) {
                loadBar.stop(true);
                if (pullMsg && pullMsg.dataset.loadErr) { // clear an earlier load error once a load succeeds
                    delete pullMsg.dataset.loadErr;
                    pullMsg.style.display = 'none';
                }
                return json;
            }, function (err) {
                loadBar.stop(false);
                throw err;
            });
        },
        ajaxResponse: function (_url, _params, response) {
            const total = Number(response.total || 0);
            if (countEl) countEl.textContent = total ? (total.toLocaleString() + ' campaigns') : '';
            if (missingTitlesEl) {
                const mt = Number(response.missing_titles || 0);
                missingTitlesEl.textContent = 'Missing Titles: ' + mt.toLocaleString();
                missingTitlesEl.classList.toggle('bg-danger', mt > 0);
                missingTitlesEl.classList.toggle('bg-success', mt === 0);
                const names = Array.isArray(response.missing_title_parents) ? response.missing_title_parents : [];
                missingTitlesEl.title = mt > 0
                    ? 'Parents with no title in Product Master: ' + names.join(', ') + (mt > names.length ? ' …' : '')
                    : 'Every parent has a title in Product Master';
            }
            if (missingCousinsEl) {
                const mc = Number(response.missing_cousins || 0);
                missingCousinsEl.textContent = 'Missing KW cousins: ' + mc.toLocaleString();
                missingCousinsEl.classList.toggle('bg-danger', mc > 0);
                missingCousinsEl.classList.toggle('bg-success', mc === 0);
                const cn = Array.isArray(response.missing_cousin_parents) ? response.missing_cousin_parents : [];
                missingCousinsEl.title = mc > 0
                    ? 'Parents with no KW cousins: ' + cn.join(', ') + (mc > cn.length ? ' …' : '')
                    : 'Every parent has KW cousins';
            }
            if (ltCoverageEl) {
                const c = response.lt_coverage || {};
                const days = Number(c.days || 0);
                ltCoverageEl.textContent = days
                    ? 'LT data: ' + c.from + ' → ' + c.to + ' (' + days.toLocaleString() + ' days)' + (c.syncing ? ' · pulling from Amazon…' : '')
                    : 'LT data: none stored yet' + (c.syncing ? ' · pulling from Amazon…' : '');
                ltCoverageEl.classList.toggle('text-danger', days === 0);
                ltCoverageEl.classList.toggle('text-muted', days > 0);
            }
            lowBadgeEls.forEach(function (el) {
                const k = el.getAttribute('data-low');
                const n = Number((response.low_counts || {})[k] || 0);
                const lim = Number((response.low_limits || {})[k] || 0);
                el.innerHTML = escapeHtml(lowLabels[k]) + ' &lt; ' + lim + ': ' + n.toLocaleString();
                el.classList.toggle('bg-primary', n === 0 || lowFilter === k);
                el.classList.toggle('bg-warning', n > 0 && lowFilter !== k);
                el.classList.toggle('text-dark', n > 0 && lowFilter !== k);
                el.classList.toggle('is-active', lowFilter === k);
            });
            return {
                data: response.data || [],
                last_page: response.last_page || 1,
            };
        },
        rowFormatter: function (row) {
            const d = row.getData() || {};
            const el = row.getElement();
            // Visual group break whenever a new parent starts.
            el.style.borderTop = d.parent_first ? '2px solid #93c5fd' : '';
        },
        columns: [
            {
                title: 'KWC Cousin',
                field: 'cousin_name',
                minWidth: 110,
                maxWidth: 240,
                headerSort: false,
                formatter: function (cell) {
                    const n = String(cell.getValue() || '').trim();
                    return n ? '<strong class="text-primary">' + escapeHtml(n) + '</strong>' : '';
                },
            },
            {
                title: 'Parents',
                field: 'parent',
                minWidth: 90,
                maxWidth: 220,
                headerSort: false,
                formatter: function (cell) {
                    // Autofilled on every campaign row; the first row of a parent group is bold, the rest are lighter.
                    const d = cell.getRow().getData() || {};
                    const p = String(d.parent || '').trim();
                    if (!p) return '<span class="aft-empty">No parent</span>';
                    return d.parent_first
                        ? '<strong>' + escapeHtml(p) + '</strong>'
                        : '<span class="text-muted">' + escapeHtml(p) + '</span>';
                },
            },
            {
                title: 'Title',
                field: 'title',
                minWidth: 180,
                maxWidth: 420,
                headerSort: false,
                formatter: function (cell) {
                    const d = cell.getRow().getData() || {};
                    const t = String(d.title || '').trim();
                    if (!t) return '<span class="aft-empty">-</span>';
                    // Autofilled on every row; later rows of a parent are clamped to 2 lines (full title on hover).
                    const clamp = d.parent_first
                        ? ''
                        : 'display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;';
                    return '<div style="white-space:normal;line-height:1.3;' + clamp + '" title="' + escapeHtml(t) + '">' + escapeHtml(t) + '</div>';
                },
            },
            {
                title: 'Campaign name',
                field: 'campaign_name',
                minWidth: 150,
                maxWidth: 260,
                headerSort: false,
                formatter: function (cell) {
                    const raw = String(cell.getValue() || '');
                    const name = escapeHtml(raw);
                    const id = escapeHtml((cell.getRow().getData() || {}).campaign_id || '');
                    // One line each; a longer name is cut with "…" (hover for the full name).
                    return '<div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="' + name + '"><strong>' + name + '</strong>' +
                        (id ? '<div class="text-muted" style="font-size:11px;">' + id + '</div>' : '') +
                        '</div>';
                },
            },
            {
                title: 'Status',
                field: 'status',
                minWidth: 80,
                hozAlign: 'center',
                headerSort: false,
                formatter: function (cell) {
                    const st = String(cell.getValue() || '').trim().toUpperCase();
                    if (!st) return '<span class="aft-empty">-</span>';
                    // Dot only: Enabled = green, Paused = yellow, anything else = gray (hover for the text).
                    const color = st === 'ENABLED' ? '#16a34a' : (st === 'PAUSED' ? '#eab308' : '#9ca3af');
                    const label = st.charAt(0) + st.slice(1).toLowerCase();
                    return '<span class="aft-status-dot" style="background:' + color + ';" title="' + escapeHtml(label) + '" aria-label="' + escapeHtml(label) + '"></span>';
                },
            },
            {
                title: 'KW cousins',
                field: 'cousin_count',
                minWidth: 100,
                hozAlign: 'center',
                headerSort: false,
                formatter: function (cell) {
                    const n = Number(cell.getValue() || 0);
                    return '<span class="kwc-btn' + (n ? '' : ' kwc-empty') + '" title="Manage KW cousins">KWC <span class="kwc-n">' + n + '</span></span>';
                },
                cellClick: function (_e, cell) {
                    openCousinsModal(cell.getRow().getData() || {});
                },
            },
            {
                title: 'Clicks LT',
                field: 'lt_clicks',
                minWidth: 90,
                hozAlign: 'right',
                headerSort: true,
                headerSortStartingDir: 'desc',
                sorter: 'number',
                headerTooltip: 'Total clicks, lifetime, across every campaign in this row\'s KW cousin group (its own parent + cousin parents). From the stored daily Amazon Ads campaign reports.',
                formatter: ltFormatter,
            },
            {
                title: 'Sold LT',
                field: 'lt_sold',
                minWidth: 90,
                hozAlign: 'right',
                headerSort: true,
                headerSortStartingDir: 'desc',
                sorter: 'number',
                headerTooltip: 'Total units sold, lifetime, across every campaign in this row\'s KW cousin group. From the stored daily Amazon Ads campaign reports.',
                formatter: ltFormatter,
            },
            {
                title: 'Targets',
                field: 'target_count',
                minWidth: 80,
                hozAlign: 'center',
                headerSort: false,
                formatter: function (cell) {
                    const n = Number(cell.getValue() || 0);
                    return '<span class="aft-count-badge" title="View targets">' + n.toLocaleString() + '</span>';
                },
                cellClick: function (_e, cell) {
                    openTargetsModal(cell.getRow().getData() || {}, 'kw');
                },
            },
            {
                title: 'Negative KW',
                field: 'negative_count',
                minWidth: 100,
                hozAlign: 'center',
                headerSort: false,
                formatter: function (cell) {
                    const n = Number(cell.getValue() || 0);
                    return '<span class="aft-count-badge aft-neg-badge" title="View negative keywords">' + n.toLocaleString() + '</span>';
                },
                cellClick: function (_e, cell) {
                    openTargetsModal(cell.getRow().getData() || {}, 'neg');
                },
            },
            {
                title: 'Specific KW',
                field: 'specific_count',
                minWidth: 100,
                hozAlign: 'center',
                headerSort: false,
                formatter: function (cell) {
                    const n = Number(cell.getValue() || 0);
                    return '<span class="aft-count-badge aft-spec-badge" title="View / add specific keywords">' + n.toLocaleString() + '</span>';
                },
                cellClick: function (_e, cell) {
                    openSpecificModal(cell.getRow().getData() || {}, 'kw');
                },
            },
            {
                title: 'S Negative KW',
                field: 'specific_neg_count',
                minWidth: 110,
                hozAlign: 'center',
                headerSort: false,
                formatter: function (cell) {
                    const n = Number(cell.getValue() || 0);
                    return '<span class="aft-count-badge aft-sneg-badge" title="View / add specific negative keywords">' + n.toLocaleString() + '</span>';
                },
                cellClick: function (_e, cell) {
                    openSpecificModal(cell.getRow().getData() || {}, 'neg');
                },
            },
        ],
    });

    // ---- Targets modal: select, edit, delete, ignore (all work on the whole selection) ----
    const TGT_URL = @json(route('amazon.ads.fbm-targeting.targets.bulk'));
    const TGT_METRICS_URL = @json(route('amazon.ads.fbm-targeting.targets.metrics'));
    const tgt = {
        campaignId: '', name: '', kind: 'kw', items: [], selected: new Set(), busy: false,
        raw: { targets: [], ignored: [] }, metrics: {}, metricsLoading: false,
        sort: { col: 'cvr', dir: 'desc' },
        filters: { text: '', match: '', clicks: '', sold: '', cvr: '' },
    };

    function parseTargetLabel(raw) {
        raw = String(raw || '').trim();
        const m = raw.match(/^(.*)\s+\(([A-Za-z][A-Za-z0-9_/ -]{1,20})\)$/);
        return { text: m ? m[1].trim() : raw, match: m ? m[2].trim().toUpperCase() : '' };
    }

    function targetKey(text, match) {
        return String(text || '').toLowerCase().replace(/\s+/g, ' ').trim() + '|' + String(match || '').toUpperCase();
    }

    function buildTargetItems() {
        const ign = new Set((tgt.raw.ignored || []).map(String));
        tgt.items = (tgt.raw.targets || []).map(function (label) {
            const p = parseTargetLabel(label);
            const m = tgt.metrics[targetKey(p.text, p.match)] || { clicks: 0, sold: 0 };
            const clicks = Number(m.clicks || 0), sold = Number(m.sold || 0);
            return {
                label: String(label), text: p.text, match: p.match, ignored: ign.has(String(label)),
                clicks: clicks, sold: sold, cvr: clicks > 0 ? (sold / clicks) * 100 : null,
            };
        });
        const have = new Set(tgt.items.map(function (i) { return i.label; }));
        tgt.selected.forEach(function (l) { if (!have.has(l)) tgt.selected.delete(l); });
    }

    function setTargetItems(targets, ignored) {
        tgt.raw = { targets: targets || [], ignored: ignored || [] };
        buildTargetItems();
    }

    function loadTargetMetrics() {
        if (tgt.kind === 'neg' || !tgt.campaignId) return Promise.resolve();
        const cid = tgt.campaignId;
        tgt.metricsLoading = true;
        renderTargetsModal();
        return fetch(TGT_METRICS_URL + '?campaign_id=' + encodeURIComponent(cid), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        }).then(function (res) { return res.json(); }).then(function (b) {
            if (cid !== tgt.campaignId) return;
            tgt.metrics = (b && b.metrics) || {};
            const n = Number((b && b.campaigns) || 0);
            if (n > 1) showModalMsg('Clicks LT / Sold LT are lifetime totals across ' + n.toLocaleString() + ' campaigns in this KW cousin group.', true);
        }).catch(function () {
            showModalMsg('Could not load clicks / sold. Showing zeros.', false);
        }).finally(function () {
            tgt.metricsLoading = false;
            buildTargetItems();
            renderTargetsModal();
        });
    }

    // "5", ">5", ">=5", "<5", "<=5", "=5", "2-8" -> test function; null = empty, false = invalid
    function numTest(expr) {
        expr = String(expr || '').replace(/%/g, '').trim();
        if (!expr) return null;
        let m = expr.match(/^(>=|<=|>|<|=)?\s*(-?\d+(?:\.\d+)?)$/);
        if (m) {
            const n = parseFloat(m[2]), op = m[1] || '=';
            return function (v) {
                if (v === null) return false;
                if (op === '>') return v > n;
                if (op === '>=') return v >= n;
                if (op === '<') return v < n;
                if (op === '<=') return v <= n;
                return Math.abs(v - n) < 1e-9 || (op === '=' && Math.round(v * 10) / 10 === n);
            };
        }
        m = expr.match(/^(-?\d+(?:\.\d+)?)\s*-\s*(-?\d+(?:\.\d+)?)$/);
        if (m) {
            const lo = Math.min(parseFloat(m[1]), parseFloat(m[2])), hi = Math.max(parseFloat(m[1]), parseFloat(m[2]));
            return function (v) { return v !== null && v >= lo && v <= hi; };
        }
        return false;
    }

    function visibleTargetItems() {
        const f = tgt.filters;
        const text = f.text.trim().toLowerCase();
        const tests = {};
        ['clicks', 'sold', 'cvr'].forEach(function (k) {
            const t = numTest(f[k]);
            tests[k] = typeof t === 'function' ? t : null;
        });
        const list = tgt.items.filter(function (i) {
            if (text && i.text.toLowerCase().indexOf(text) < 0) return false;
            if (f.match && (i.match || '-') !== f.match) return false;
            if (tests.clicks && !tests.clicks(i.clicks)) return false;
            if (tests.sold && !tests.sold(i.sold)) return false;
            if (tests.cvr && !tests.cvr(i.cvr)) return false;
            return true;
        });
        const col = tgt.sort.col, dir = tgt.sort.dir === 'asc' ? 1 : -1;
        list.sort(function (a, b) {
            let r;
            if (col === 'text' || col === 'match') {
                r = String(a[col]).localeCompare(String(b[col]), undefined, { numeric: true, sensitivity: 'base' }) * dir;
            } else {
                const av = a[col], bv = b[col];
                if (av === null && bv === null) r = 0;
                else if (av === null) return 1; // no data always last
                else if (bv === null) return -1;
                else r = (av - bv) * dir;
            }
            if (r) return r;
            return (b.clicks - a.clicks) || a.text.localeCompare(b.text, undefined, { sensitivity: 'base' });
        });
        return list;
    }

    function nounFor(n) {
        return (tgt.kind === 'neg' ? 'negative keyword' : 'target') + (n === 1 ? '' : 's');
    }

    function renderTargetsModal() {
        const modalEl = document.getElementById('aftTargetsModal');
        modalEl.classList.toggle('is-neg', tgt.kind === 'neg');
        const isNeg = tgt.kind === 'neg';
        const items = tgt.items;
        const selItems = items.filter(function (i) { return tgt.selected.has(i.label); });
        const shown = visibleTargetItems();

        // ---- counts table (All / Selected) ----
        const sumUp = function (list) {
            const o = { total: list.length, ignored: 0, clicks: 0, sold: 0, by: {} };
            list.forEach(function (i) {
                if (i.ignored) o.ignored++;
                o.clicks += i.clicks;
                o.sold += i.sold;
                const k = i.match || '-';
                o.by[k] = (o.by[k] || 0) + 1;
            });
            o.cvr = o.clicks > 0 ? (o.sold / o.clicks) * 100 : null;
            return o;
        };
        const all = sumUp(items);
        const sel = sumUp(selItems);
        const order = ['EXACT', 'PHRASE', 'BROAD', 'NEGATIVE_EXACT', 'NEGATIVE_PHRASE'];
        const types = Object.keys(all.by).sort(function (a, b) {
            const ia = order.indexOf(a), ib = order.indexOf(b);
            return (ia < 0 ? 99 : ia) - (ib < 0 ? 99 : ib) || a.localeCompare(b);
        });
        const typeLabel = function (t) { return t === '-' ? 'No match' : t.charAt(0) + t.slice(1).toLowerCase().replace(/_/g, ' '); };
        const fmtCvr = function (v) { return v === null ? '-' : v.toFixed(1) + '%'; };
        const row = function (cls, label, c) {
            return '<tr class="' + cls + '"><th>' + label + '</th><td>' + c.total.toLocaleString() + '</td>' +
                types.map(function (t) { return '<td>' + (c.by[t] || 0).toLocaleString() + '</td>'; }).join('') +
                '<td>' + c.ignored.toLocaleString() + '</td>' +
                (isNeg ? '' : '<td>' + c.clicks.toLocaleString() + '</td><td>' + c.sold.toLocaleString() + '</td><td>' + fmtCvr(c.cvr) + '</td>') + '</tr>';
        };
        document.getElementById('aft-modal-stats').innerHTML =
            '<thead><tr><th></th><th>Total</th>' + types.map(function (t) { return '<th>' + escapeHtml(typeLabel(t)) + '</th>'; }).join('') + '<th>Ignored</th>' +
            (isNeg ? '' : '<th>Clicks LT</th><th>Sold LT</th><th>CVR %</th>') + '</tr></thead>' +
            '<tbody>' + row('', 'All', all) + row('aft-stats-sel', 'Selected', sel) + '</tbody>';

        const titleEl = document.getElementById('aft-modal-title');
        if (titleEl) titleEl.textContent = tgt.name + (isNeg ? ' - Negative KW' : '');

        // ---- header: sort arrows + match filter options ----
        modalEl.querySelectorAll('.aft-sort').forEach(function (el) {
            const on = el.dataset.sort === tgt.sort.col;
            el.querySelector('.aft-arrow').textContent = on ? (tgt.sort.dir === 'asc' ? '▲' : '▼') : '';
        });
        const matchSel = modalEl.querySelector('select[data-filter="match"]');
        const present = Object.keys(all.by).sort();
        if (tgt.filters.match && present.indexOf(tgt.filters.match) < 0) tgt.filters.match = '';
        matchSel.innerHTML = '<option value="">All</option>' + present.map(function (t) {
            return '<option value="' + escapeHtml(t) + '"' + (tgt.filters.match === t ? ' selected' : '') + '>' + escapeHtml(typeLabel(t)) + '</option>';
        }).join('');
        ['clicks', 'sold', 'cvr'].forEach(function (k) {
            const inp = modalEl.querySelector('input[data-filter="' + k + '"]');
            inp.classList.toggle('is-bad', numTest(tgt.filters[k]) === false);
        });

        // ---- rows ----
        const listEl = document.getElementById('aft-modal-targets');
        if (items.length === 0) {
            listEl.innerHTML = '<span class="aft-empty">' + (isNeg ? 'No negative keywords yet' : 'No targets yet') + '</span>';
        } else if (shown.length === 0) {
            listEl.innerHTML = '<span class="aft-empty">No rows match the filters</span>';
        } else {
            listEl.innerHTML = shown.map(function (i) {
                const idx = items.indexOf(i);
                const on = tgt.selected.has(i.label);
                return '<div class="aft-chip' + (on ? ' is-selected' : '') + (i.ignored ? ' is-ignored' : '') + '" data-idx="' + idx + '">' +
                    '<input type="checkbox" class="aft-pick"' + (on ? ' checked' : '') + '>' +
                    '<span class="aft-chip-text">' + escapeHtml(i.text) + '</span>' +
                    '<span>' + (i.match ? '<span class="aft-chip-match">' + escapeHtml(i.match) + '</span>' : '') + '</span>' +
                    '<span class="aft-metric">' + (tgt.metricsLoading ? '…' : i.clicks.toLocaleString()) + '</span>' +
                    '<span class="aft-metric">' + (tgt.metricsLoading ? '…' : i.sold.toLocaleString()) + '</span>' +
                    '<span class="aft-metric aft-cvr">' + (tgt.metricsLoading ? '…' : fmtCvr(i.cvr)) + '</span>' +
                    '<span class="aft-acts">' +
                    '<button type="button" class="aft-ico aft-ico-edit" data-act="edit" title="Edit"><i class="fas fa-pen"></i></button>' +
                    '<button type="button" class="aft-ico aft-ico-del" data-act="delete" title="Delete"><i class="fas fa-trash"></i></button>' +
                    '<button type="button" class="aft-ico aft-ico-ign' + (i.ignored ? ' is-on' : '') + '" data-act="ignore" title="' + (i.ignored ? 'Un-ignore' : 'Ignore') + '"><i class="fas fa-ban"></i></button>' +
                    '</span></div>';
            }).join('');
        }

        // ---- select-all (rows shown), toolbar ----
        const n = selItems.length;
        const shownSel = shown.filter(function (i) { return tgt.selected.has(i.label); }).length;
        const allBox = document.getElementById('aft-sel-all');
        allBox.checked = shown.length > 0 && shownSel === shown.length;
        allBox.indeterminate = shownSel > 0 && shownSel < shown.length;
        allBox.disabled = shown.length === 0;
        document.getElementById('aft-sel-text').textContent = (n ? n.toLocaleString() + ' selected - ' : '') +
            shown.length.toLocaleString() + ' of ' + items.length.toLocaleString() + ' shown' +
            (n ? '' : ' - select ' + nounFor(2) + ' to edit, delete or ignore in bulk');
        ['aft-bulk-edit', 'aft-bulk-del', 'aft-bulk-ign'].forEach(function (id) {
            document.getElementById(id).disabled = n === 0 || tgt.busy;
        });
        const ign = document.getElementById('aft-bulk-ign');
        const allIgn = n > 0 && selItems.every(function (i) { return i.ignored; });
        ign.classList.toggle('is-on', allIgn);
        ign.title = (allIgn ? 'Un-ignore selected' : 'Ignore selected') + (n ? ' (' + n + ')' : '');
        document.getElementById('aft-bulk-edit').title = 'Edit selected' + (n ? ' (' + n + ')' : '');
        document.getElementById('aft-bulk-del').title = 'Delete selected' + (n ? ' (' + n + ')' : '');
    }

    function targetsToAct(idx) {
        const i = tgt.items[idx];
        if (i && tgt.selected.has(i.label)) {
            return tgt.items.filter(function (x) { return tgt.selected.has(x.label); });
        }
        return i ? [i] : [];
    }

    function openTargetsModal(row, kind) {
        tgt.kind = kind === 'neg' ? 'neg' : 'kw';
        tgt.campaignId = String(row.campaign_id || '');
        tgt.name = row.campaign_name || 'Campaign';
        tgt.selected = new Set();
        tgt.metrics = {};
        tgt.sort = tgt.kind === 'neg' ? { col: 'text', dir: 'asc' } : { col: 'cvr', dir: 'desc' }; // CVR highest to lowest by default
        tgt.filters = { text: '', match: '', clicks: '', sold: '', cvr: '' };
        document.querySelectorAll('#aftTargetsModal [data-filter]').forEach(function (el) { el.value = ''; });
        showModalMsg('', true);
        setTargetItems(tgt.kind === 'neg' ? row.negatives : row.targets, tgt.kind === 'neg' ? row.ignored_negatives : row.ignored_targets);
        closeEditPanel();
        renderTargetsModal();
        loadTargetMetrics();
        const modalEl = document.getElementById('aftTargetsModal');
        if (modalEl && window.bootstrap && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        } else if (window.jQuery) {
            window.jQuery(modalEl).modal('show');
        }
    }

    function showModalMsg(text, ok) {
        const el = document.getElementById('aft-modal-msg');
        el.style.display = text ? 'block' : 'none';
        el.className = 'small mb-1 ' + (ok ? 'text-success' : 'text-danger');
        el.textContent = text || '';
    }

    function closeEditPanel() {
        document.getElementById('aft-editpanel').classList.remove('show');
    }

    let editItems = [];
    function openEditPanel(list) {
        if (!list.length) return;
        editItems = list;
        const single = list.length === 1;
        document.getElementById('aft-edit-title').textContent = 'Edit ' + list.length.toLocaleString() + ' ' + nounFor(list.length) +
            (single ? '' : ' (find & replace in the keyword text, and/or change the match type)');
        document.getElementById('aft-edit-single').style.display = single ? '' : 'none';
        document.getElementById('aft-edit-find-wrap').style.display = single ? 'none' : '';
        document.getElementById('aft-edit-repl-wrap').style.display = single ? 'none' : '';
        document.getElementById('aft-edit-text').value = single ? list[0].text : '';
        document.getElementById('aft-edit-find').value = '';
        document.getElementById('aft-edit-replace').value = '';
        const opts = tgt.kind === 'neg' ? ['NEGATIVE_EXACT', 'NEGATIVE_PHRASE'] : ['BROAD', 'PHRASE', 'EXACT'];
        const matchSel = document.getElementById('aft-edit-match');
        matchSel.innerHTML = '<option value="">Keep match type' + (single && list[0].match ? ' (' + escapeHtml(list[0].match) + ')' : '') + '</option>' +
            opts.map(function (o) { return '<option value="' + o + '">' + o + '</option>'; }).join('');
        document.getElementById('aft-editpanel').classList.add('show');
        const focusEl = single ? document.getElementById('aft-edit-text') : document.getElementById('aft-edit-find');
        if (focusEl) focusEl.focus();
    }

    function runTargetAction(action, list, extra) {
        if (tgt.busy || !list.length) return;
        tgt.busy = true;
        renderTargetsModal();
        const body = Object.assign({
            kind: tgt.kind,
            action: action,
            campaign_id: tgt.campaignId,
            labels: list.map(function (i) { return i.label; }),
        }, extra || {});
        fetch(TGT_URL, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify(body),
        }).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (b) { return { ok: res.ok && b.success !== false, body: b }; });
        }).then(function (r) {
            const b = r.body || {};
            if (!r.ok) {
                alert(b.message || (b.errors ? Object.values(b.errors).flat().join('\n') : 'Could not save. Please try again.'));
                return;
            }
            if (action === 'delete' || action === 'edit') {
                list.forEach(function (i) { tgt.selected.delete(i.label); });
            }
            setTargetItems(tgt.kind === 'neg' ? b.negatives : b.targets, tgt.kind === 'neg' ? b.ignored_negatives : b.ignored_targets);
            closeEditPanel();
            if (b.message) showModalMsg(b.message, true);
            if (typeof table !== 'undefined' && table && table.replaceData) table.replaceData(); // refresh the grid counts / badges
            if (action === 'edit') loadTargetMetrics(); // edited keywords have a new text
        }).catch(function () {
            alert('Network error. Please try again.');
        }).finally(function () {
            tgt.busy = false;
            renderTargetsModal();
        });
    }

    function actOn(action, list) {
        if (!list.length) return;
        if (action === 'edit') { openEditPanel(list); return; }
        if (action === 'delete') {
            if (!confirm('Delete ' + list.length.toLocaleString() + ' ' + nounFor(list.length) + '? This also applies to the KW cousins / sibling campaigns that share these keywords, and they will not be re-imported.')) return;
            runTargetAction('delete', list);
            return;
        }
        // ignore toggles: un-ignore when everything chosen is already ignored
        const allIgn = list.every(function (i) { return i.ignored; });
        runTargetAction(allIgn ? 'unignore' : 'ignore', list);
    }

    (function wireTargetsModal() {
        const listEl = document.getElementById('aft-modal-targets');
        listEl.addEventListener('click', function (e) {
            const chip = e.target.closest('.aft-chip');
            if (!chip) return;
            const idx = Number(chip.dataset.idx);
            const btn = e.target.closest('[data-act]');
            if (btn) {
                actOn(btn.dataset.act, targetsToAct(idx));
                return;
            }
            if (e.target.classList.contains('aft-pick')) {
                const label = tgt.items[idx].label;
                if (e.target.checked) tgt.selected.add(label); else tgt.selected.delete(label);
                renderTargetsModal();
            }
        });
        document.getElementById('aft-sel-all').addEventListener('change', function (e) {
            // selects / clears the rows currently shown (after filters); other selections are kept
            visibleTargetItems().forEach(function (i) {
                if (e.target.checked) tgt.selected.add(i.label); else tgt.selected.delete(i.label);
            });
            renderTargetsModal();
        });
        document.querySelectorAll('#aftTargetsModal .aft-sort').forEach(function (el) {
            el.addEventListener('click', function () {
                const col = el.dataset.sort;
                if (tgt.sort.col === col) {
                    tgt.sort.dir = tgt.sort.dir === 'asc' ? 'desc' : 'asc';
                } else {
                    tgt.sort = { col: col, dir: (col === 'text' || col === 'match') ? 'asc' : 'desc' };
                }
                renderTargetsModal();
            });
        });
        document.querySelectorAll('#aftTargetsModal [data-filter]').forEach(function (el) {
            el.addEventListener(el.tagName === 'SELECT' ? 'change' : 'input', function () {
                tgt.filters[el.dataset.filter] = el.value;
                renderTargetsModal();
            });
        });
        const selected = function () { return tgt.items.filter(function (i) { return tgt.selected.has(i.label); }); };
        document.getElementById('aft-bulk-edit').addEventListener('click', function () { actOn('edit', selected()); });
        document.getElementById('aft-bulk-del').addEventListener('click', function () { actOn('delete', selected()); });
        document.getElementById('aft-bulk-ign').addEventListener('click', function () { actOn('ignore', selected()); });
        document.getElementById('aft-edit-cancel').addEventListener('click', closeEditPanel);
        document.getElementById('aft-edit-apply').addEventListener('click', function () {
            runTargetAction('edit', editItems, {
                text: document.getElementById('aft-edit-text').value,
                find: document.getElementById('aft-edit-find').value,
                replace: document.getElementById('aft-edit-replace').value,
                match_type: document.getElementById('aft-edit-match').value,
            });
        });
    })();

    const pullMsg = document.getElementById('aft-pull-msg');
    const csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    const pullDefs = [
        {
            btn: document.getElementById('aft-pull-kw'),
            url: @json(route('amazon.ads.fbm-targeting.pull-keywords')),
            what: 'keywords',
        },
        {
            btn: document.getElementById('aft-pull-neg'),
            url: @json(route('amazon.ads.fbm-targeting.pull-negatives')),
            what: 'negative keywords',
        },
    ];

    function showPullMsg(text, ok) {
        if (!pullMsg) return;
        pullMsg.style.display = 'block';
        pullMsg.className = 'small mb-2 ' + (ok ? 'text-success' : 'text-danger');
        pullMsg.textContent = text;
    }

    pullDefs.forEach(function (def) {
        if (!def.btn) return;
        def.btn.addEventListener('click', function () {
            if (!confirm('Pull ' + def.what + ' from all Amazon KW campaigns? Only ones missing in the app will be added; existing ones are kept.')) return;
            const original = def.btn.innerHTML;
            pullDefs.forEach(function (d) { if (d.btn) d.btn.disabled = true; });
            def.btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Pulling…';
            showPullMsg('Pulling ' + def.what + ' from Amazon, this can take a minute…', true);
            fetch(def.url, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf,
                },
            }).then(function (res) {
                return res.json().catch(function () { return {}; }).then(function (body) {
                    return { ok: res.ok && body.success !== false, body: body };
                });
            }).then(function (r) {
                const b = r.body || {};
                if (r.ok) {
                    showPullMsg((b.message || 'Done.') + ' (' + (b.campaigns || 0) + ' KW campaigns, ' + (b.fetched || 0) + ' ' + def.what + ' fetched)', true);
                    table.setPage(1);
                } else {
                    showPullMsg(b.message || 'Pull failed.', false);
                }
            }).catch(function (e) {
                showPullMsg('Pull failed: ' + e.message, false);
            }).finally(function () {
                pullDefs.forEach(function (d) { if (d.btn) d.btn.disabled = false; });
                def.btn.innerHTML = original;
            });
        });
    });

    // Pull missing daily campaign reports (clicks / sold) from the Amazon Ads API for the LT columns.
    (function () {
        const btn = document.getElementById('aft-pull-lt');
        if (!btn) return;
        btn.addEventListener('click', function () {
            if (!confirm('Fetch missing daily campaign reports from the Amazon Ads API (up to ~95 days back)? It runs in the background and can take several minutes.')) return;
            const original = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Starting…';
            fetch(@json(route('amazon.ads.fbm-targeting.lifetime.sync')), {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf },
            }).then(function (res) {
                return res.json().catch(function () { return {}; }).then(function (b) {
                    showPullMsg(b.message || (res.ok ? 'Started.' : 'Could not start the pull.'), res.ok && b.success !== false);
                });
            }).catch(function (e) {
                showPullMsg('Could not start the pull: ' + e.message, false);
            }).finally(function () {
                btn.disabled = false;
                btn.innerHTML = original;
            });
        });
    })();

    // ---------- KW cousins ----------
    const cousinUrls = {
        campaigns: @json(route('amazon.ads.fbm-targeting.cousins.campaigns')),
        add: @json(route('amazon.ads.fbm-targeting.cousins.add')),
        remove: @json(route('amazon.ads.fbm-targeting.cousins.remove')),
        sync: @json(route('amazon.ads.fbm-targeting.cousins.sync')),
        name: @json(route('amazon.ads.fbm-targeting.cousins.name')),
    };
    const kwcState = { campaign: '', cousins: [], selected: new Set(), parents: [], shown: [] };
    // kwcState.cousins = [{parent, campaigns: [...]}] (one entry per cousin parent)
    const kwcEl = {
        modal: document.getElementById('kwcModal'),
        title: document.getElementById('kwc-title'),
        current: document.getElementById('kwc-current'),
        currentCount: document.getElementById('kwc-current-count'),
        search: document.getElementById('kwc-search'),
        results: document.getElementById('kwc-results'),
        msg: document.getElementById('kwc-msg'),
        add: document.getElementById('kwc-add'),
        sel: document.getElementById('kwc-sel-n'),
        sync: document.getElementById('kwc-sync'),
        chips: document.getElementById('kwc-chips'),
        name: document.getElementById('kwc-name'),
        nameSave: document.getElementById('kwc-name-save'),
        all: document.getElementById('kwc-all'),
        clear: document.getElementById('kwc-clear'),
        shown: document.getElementById('kwc-shown'),
    };

    function kwcMsg(text, ok) {
        kwcEl.msg.style.display = text ? 'block' : 'none';
        kwcEl.msg.className = 'small ' + (ok ? 'text-success' : 'text-danger');
        kwcEl.msg.textContent = text || '';
    }

    function kwcPost(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify(body),
        }).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (b) {
                return { ok: res.ok && b.success !== false, body: b };
            });
        });
    }

    function kwcRenderCurrent() {
        const list = kwcState.cousins;
        kwcEl.currentCount.textContent = list.length ? '(' + list.length + ')' : '';
        if (!list.length) {
            kwcEl.current.innerHTML = '<span class="aft-empty">No cousins yet</span>';
            return;
        }
        kwcEl.current.innerHTML = list.map(function (c, i) {
            const n = (c.campaigns || []).length;
            const label = c.parent ? c.parent : (c.campaigns || [''])[0];
            const sub = c.parent ? '<span class="text-muted small ms-2">' + n + ' campaign' + (n === 1 ? '' : 's') + '</span>' : '';
            return '<div class="kwc-item" title="' + escapeHtml((c.campaigns || []).join(', ')) + '"><span><strong>' + escapeHtml(label) + '</strong>' + sub + '</span>' +
                '<button type="button" class="btn btn-sm btn-outline-danger py-0 kwc-remove" data-i="' + i + '" title="Remove cousin">&times;</button></div>';
        }).join('');
    }

    function kwcUpdateSel() {
        kwcEl.sel.textContent = String(kwcState.selected.size);
        kwcEl.add.disabled = kwcState.selected.size === 0;
        const picked = Array.from(kwcState.selected);
        kwcEl.chips.innerHTML = picked.map(function (p) {
            return '<span class="kwc-chip">' + escapeHtml(p) + '<button type="button" class="kwc-chip-x" data-p="' + escapeHtml(p) + '" title="Unselect">&times;</button></span>';
        }).join('');
        const shown = kwcState.shown;
        const allOn = shown.length > 0 && shown.every(function (p) { return kwcState.selected.has(p.parent); });
        kwcEl.all.checked = allOn;
        kwcEl.all.disabled = shown.length === 0;
    }

    // Instant, client-side filter over the parents loaded once per open.
    function kwcRenderResults() {
        const q = (kwcEl.search.value || '').trim().toLowerCase();
        const terms = q.split(/\s+/).filter(Boolean);
        kwcState.shown = kwcState.parents.filter(function (p) {
            const hay = String(p.parent).toLowerCase();
            return terms.every(function (t) { return hay.indexOf(t) !== -1; });
        });
        kwcEl.shown.textContent = kwcState.shown.length.toLocaleString() + ' of ' + kwcState.parents.length.toLocaleString() + ' parents';
        if (!kwcState.shown.length) {
            kwcEl.results.innerHTML = '<span class="aft-empty">No parents match</span>';
        } else {
            kwcEl.results.innerHTML = kwcState.shown.map(function (p) {
                const checked = kwcState.selected.has(p.parent) ? ' checked' : '';
                return '<label class="kwc-item"><input type="checkbox" class="kwc-pick" value="' + escapeHtml(p.parent) + '"' + checked + '>' +
                    '<span>' + escapeHtml(p.parent) + '</span>' +
                    '<span class="kwc-parent">' + Number(p.campaigns || 0) + ' campaign' + (Number(p.campaigns) === 1 ? '' : 's') + '</span></label>';
            }).join('');
        }
        kwcUpdateSel();
    }

    function kwcLoadResults() {
        const url = cousinUrls.campaigns + '?exclude=' + encodeURIComponent(kwcState.campaign);
        kwcEl.results.innerHTML = '<span class="aft-empty">Loading…</span>';
        fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json().then(function (b) { return { ok: r.ok, body: b }; }); })
            .then(function (r) {
                if (!r.ok || !Array.isArray(r.body.parents)) throw new Error(r.body.message || 'bad response');
                kwcState.parents = r.body.parents;
                // Drop selections that are no longer available.
                const avail = new Set(kwcState.parents.map(function (p) { return p.parent; }));
                Array.from(kwcState.selected).forEach(function (p) { if (!avail.has(p)) kwcState.selected.delete(p); });
                kwcRenderResults();
            })
            .catch(function (e) {
                kwcEl.results.innerHTML = '<span class="text-danger small">Could not load parents: ' + escapeHtml(e.message) + '</span>';
            });
    }

    function openCousinsModal(row) {
        kwcState.campaign = row.campaign_name || '';
        kwcState.cousins = Array.isArray(row.cousin_parents) ? row.cousin_parents.slice() : [];
        kwcState.selected = new Set();
        kwcState.parents = [];
        kwcState.shown = [];
        kwcEl.title.textContent = kwcState.campaign;
        kwcEl.name.value = row.cousin_name || '';
        kwcEl.search.value = '';
        kwcMsg('', true);
        kwcRenderCurrent();
        kwcUpdateSel();
        kwcLoadResults();
        if (window.bootstrap && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(kwcEl.modal).show();
        } else if (window.jQuery) {
            window.jQuery(kwcEl.modal).modal('show');
        }
    }

    function kwcApplyAffected(affected) {
        const me = (affected || []).find(function (a) {
            return String(a.campaign).toUpperCase() === kwcState.campaign.toUpperCase();
        });
        if (me) {
            kwcState.cousins = me.cousin_parents || [];
            kwcEl.name.value = me.cousin_name || '';
        }
        kwcRenderCurrent();
        table.setPage(table.getPage() || 1);
    }

    kwcEl.search.addEventListener('input', kwcRenderResults);

    kwcEl.all.addEventListener('change', function () {
        kwcState.shown.forEach(function (p) {
            if (kwcEl.all.checked) kwcState.selected.add(p.parent); else kwcState.selected.delete(p.parent);
        });
        kwcRenderResults();
    });

    kwcEl.clear.addEventListener('click', function (e) {
        e.preventDefault();
        kwcState.selected = new Set();
        kwcRenderResults();
    });

    kwcEl.chips.addEventListener('click', function (e) {
        const x = e.target.closest('.kwc-chip-x');
        if (!x) return;
        kwcState.selected.delete(x.dataset.p);
        kwcRenderResults();
    });

    kwcEl.results.addEventListener('change', function (e) {
        const t = e.target;
        if (!t || !t.classList.contains('kwc-pick')) return;
        if (t.checked) kwcState.selected.add(t.value); else kwcState.selected.delete(t.value);
        kwcUpdateSel();
    });

    kwcEl.current.addEventListener('click', function (e) {
        const btn = e.target.closest('.kwc-remove');
        if (!btn) return;
        const entry = kwcState.cousins[Number(btn.dataset.i)];
        if (!entry) return;
        const label = entry.parent ? 'parent "' + entry.parent + '" (' + (entry.campaigns || []).length + ' campaigns)' : '"' + (entry.campaigns || [''])[0] + '"';
        if (!confirm('Remove ' + label + ' from this cousin group? Keywords already synced stay.')) return;
        const body = entry.parent
            ? { campaign: kwcState.campaign, parent: entry.parent }
            : { campaign: kwcState.campaign, cousin: (entry.campaigns || [''])[0] };
        kwcPost(cousinUrls.remove, body).then(function (r) {
            if (r.ok) {
                kwcApplyAffected(r.body.affected);
                kwcMsg(r.body.message || 'Removed.', true);
                kwcLoadResults();
            } else {
                kwcMsg(r.body.message || 'Could not remove.', false);
            }
        });
    });

    kwcEl.nameSave.addEventListener('click', function () {
        const original = kwcEl.nameSave.innerHTML;
        kwcEl.nameSave.disabled = true;
        kwcPost(cousinUrls.name, { campaign: kwcState.campaign, name: (kwcEl.name.value || '').trim() }).then(function (r) {
            if (r.ok) {
                kwcApplyAffected(r.body.affected);
                kwcMsg(r.body.message || 'Saved.', true);
            } else {
                kwcMsg((r.body && (r.body.message || (r.body.errors && Object.values(r.body.errors)[0]))) || 'Could not save name.', false);
            }
        }).catch(function (e) {
            kwcMsg('Failed: ' + e.message, false);
        }).finally(function () {
            kwcEl.nameSave.disabled = false;
            kwcEl.nameSave.innerHTML = original;
        });
    });

    kwcEl.name.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); kwcEl.nameSave.click(); }
    });

    kwcEl.add.addEventListener('click', function () {
        const picks = Array.from(kwcState.selected);
        if (!picks.length) return;
        const original = kwcEl.add.innerHTML;
        kwcEl.add.disabled = true;
        kwcEl.add.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Syncing…';
        kwcMsg('Saving cousins and syncing keywords…', true);
        kwcPost(cousinUrls.add, { campaign: kwcState.campaign, parents: picks, name: (kwcEl.name.value || '').trim() }).then(function (r) {
            if (r.ok) {
                kwcState.selected = new Set();
                kwcApplyAffected(r.body.affected);
                kwcMsg(r.body.message || 'Saved.', true);
                kwcLoadResults();
            } else {
                kwcMsg((r.body && (r.body.message || (r.body.errors && Object.values(r.body.errors)[0]))) || 'Could not add cousins.', false);
            }
        }).catch(function (e) {
            kwcMsg('Failed: ' + e.message, false);
        }).finally(function () {
            kwcEl.add.innerHTML = original;
            kwcUpdateSel();
        });
    });

    kwcEl.sync.addEventListener('click', function () {
        const original = kwcEl.sync.innerHTML;
        kwcEl.sync.disabled = true;
        kwcEl.sync.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Syncing…';
        kwcMsg('Syncing keywords…', true);
        kwcPost(cousinUrls.sync, { campaign: kwcState.campaign }).then(function (r) {
            kwcMsg(r.body.message || (r.ok ? 'Synced.' : 'Sync failed.'), r.ok);
            if (r.ok) kwcApplyAffected(r.body.affected);
        }).catch(function (e) {
            kwcMsg('Failed: ' + e.message, false);
        }).finally(function () {
            kwcEl.sync.disabled = false;
            kwcEl.sync.innerHTML = original;
        });
    });

    // ---------- Push KW to KW + HL campaigns ----------
    const pushUrl = @json(route('amazon.ads.fbm-targeting.push-keywords'));
    const pkBtn = document.getElementById('aft-push-kw');
    const pkConfirm = document.getElementById('pk-confirm');
    const pkSummary = document.getElementById('pk-summary');
    const pkWrap = document.getElementById('pk-table-wrap');
    const pkModalEl = document.getElementById('pushKwModal');

    function pkPost(dryRun, selected) {
        return fetch(pushUrl, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify(selected ? { dry_run: dryRun, selected: selected } : { dry_run: dryRun }),
        }).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (b) {
                return { ok: res.ok && b.success !== false, body: b };
            });
        });
    }

    // Check results: one row per campaign with a checkbox; only ticked campaigns are pushed.
    const pkEl = {
        toolbar: document.getElementById('pk-toolbar'),
        filter: document.getElementById('pk-filter'),
        selText: document.getElementById('pk-sel-text'),
        selShown: document.getElementById('pk-sel-shown'),
        selNone: document.getElementById('pk-sel-none'),
    };
    const pkState = { rows: [], selected: new Set(), filter: '' };
    const pkKey = function (r) { return String(r.type) + '|' + String(r.campaign_id); };

    function pkShownRows() {
        const q = pkState.filter.trim().toLowerCase();
        if (!q) return pkState.rows;
        return pkState.rows.filter(function (r) {
            return String(r.campaign_name || '').toLowerCase().indexOf(q) >= 0 || String(r.type || '').toLowerCase() === q || String(r.parent || '').toLowerCase().indexOf(q) >= 0;
        });
    }

    function pkUpdateSelection() {
        let camps = 0, kws = 0;
        pkState.rows.forEach(function (r) {
            if (pkState.selected.has(pkKey(r))) { camps++; kws += Number(r.to_add || 0); }
        });
        pkEl.selText.textContent = camps.toLocaleString() + ' of ' + pkState.rows.length.toLocaleString() + ' campaigns selected · ' + kws.toLocaleString() + ' keyword(s)';
        pkConfirm.innerHTML = '<i class="fas fa-upload"></i> Push selected (' + camps.toLocaleString() + ')';
        pkConfirm.disabled = camps === 0;
        const all = document.getElementById('pk-all');
        if (all) {
            const shown = pkShownRows();
            const n = shown.filter(function (r) { return pkState.selected.has(pkKey(r)); }).length;
            all.checked = shown.length > 0 && n === shown.length;
            all.indeterminate = n > 0 && n < shown.length;
        }
    }

    function pkDraw() {
        const rows = pkShownRows();
        if (!rows.length) {
            pkWrap.innerHTML = '<span class="aft-empty">No campaigns match the filter</span>';
            pkUpdateSelection();
            return;
        }
        pkWrap.innerHTML = '<table class="table table-sm table-bordered align-middle mb-0"><thead class="table-light"><tr>' +
            '<th style="width:36px"><input type="checkbox" id="pk-all" title="Select all shown"></th>' +
            '<th style="width:70px">Type</th><th>Campaign</th><th style="width:140px" class="text-end">To add</th></tr></thead><tbody>' +
            rows.map(function (r) {
                const k = pkKey(r);
                return '<tr><td><input type="checkbox" class="pk-row" data-key="' + escapeHtml(k) + '"' + (pkState.selected.has(k) ? ' checked' : '') + '></td>' +
                    '<td><span class="badge ' + (r.type === 'HL' ? 'bg-info' : 'bg-primary') + '">' + escapeHtml(r.type) + '</span></td>' +
                    '<td>' + escapeHtml(r.campaign_name) + '</td>' +
                    '<td class="text-end">' + Number(r.to_add || 0).toLocaleString() + '</td></tr>';
            }).join('') + '</tbody></table>';
        pkUpdateSelection();
    }

    function pkRender(body, done) {
        const rows = body.rows || [];
        if (!rows.length) {
            pkEl.toolbar.classList.add('d-none');
            pkEl.toolbar.classList.remove('d-flex');
            pkWrap.innerHTML = '<span class="aft-empty">Nothing to push. Every campaign already has these keywords.</span>';
            return;
        }
        if (!done) {
            pkState.rows = rows;
            pkState.selected = new Set(rows.map(pkKey)); // everything ticked to start with
            pkState.filter = '';
            pkEl.filter.value = '';
            pkEl.toolbar.classList.remove('d-none');
            pkEl.toolbar.classList.add('d-flex');
            pkDraw();
            return;
        }
        pkEl.toolbar.classList.add('d-none');
        pkEl.toolbar.classList.remove('d-flex');
        pkWrap.innerHTML = '<table class="table table-sm table-bordered align-middle mb-0"><thead class="table-light"><tr>' +
            '<th style="width:70px">Type</th><th>Campaign</th><th style="width:140px" class="text-end">Added</th>' +
            '<th style="width:90px" class="text-end">Failed</th><th>Note</th></tr></thead><tbody>' +
            rows.map(function (r) {
                return '<tr><td><span class="badge ' + (r.type === 'HL' ? 'bg-info' : 'bg-primary') + '">' + escapeHtml(r.type) + '</span></td>' +
                    '<td>' + escapeHtml(r.campaign_name) + '</td>' +
                    '<td class="text-end">' + Number(r.added || 0) + ' / ' + Number(r.to_add || 0) + '</td>' +
                    '<td class="text-end ' + (r.failed ? 'text-danger fw-bold' : '') + '">' + Number(r.failed || 0) + '</td><td class="small">' + escapeHtml(r.message || '') + '</td>' +
                    '</tr>';
            }).join('') + '</tbody></table>';
    }

    pkWrap.addEventListener('change', function (e) {
        const t = e.target;
        if (t && t.id === 'pk-all') {
            pkShownRows().forEach(function (r) { t.checked ? pkState.selected.add(pkKey(r)) : pkState.selected.delete(pkKey(r)); });
            pkDraw();
        } else if (t && t.classList && t.classList.contains('pk-row')) {
            const k = t.getAttribute('data-key');
            t.checked ? pkState.selected.add(k) : pkState.selected.delete(k);
            // Rows that point at the same Amazon campaign id (renamed campaigns, parents sharing one HL) move together.
            pkWrap.querySelectorAll('.pk-row').forEach(function (cb) { cb.checked = pkState.selected.has(cb.getAttribute('data-key')); });
            pkUpdateSelection();
        }
    });
    pkEl.filter.addEventListener('input', function () { pkState.filter = pkEl.filter.value; pkDraw(); });
    pkEl.selShown.addEventListener('click', function () { pkShownRows().forEach(function (r) { pkState.selected.add(pkKey(r)); }); pkDraw(); });
    pkEl.selNone.addEventListener('click', function () { pkShownRows().forEach(function (r) { pkState.selected.delete(pkKey(r)); }); pkDraw(); });

    function pkOpen() {
        pkSummary.className = 'fw-semibold mb-2';
        pkSummary.textContent = 'Checking what is missing on Amazon…';
        pkWrap.innerHTML = '<span class="aft-empty">Loading…</span>';
        pkConfirm.disabled = true;
        pkConfirm.innerHTML = '<i class="fas fa-upload"></i> Push selected';
        pkEl.toolbar.classList.add('d-none');
        pkEl.toolbar.classList.remove('d-flex');
        if (window.bootstrap && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(pkModalEl).show();
        } else if (window.jQuery) {
            window.jQuery(pkModalEl).modal('show');
        }
        fetch(@json(route('amazon.ads.fbm-targeting.push-status')), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (res) { return res.json(); })
            .catch(function () { return {}; })
            .then(function (st) {
                if (st && st.status === 'running') { pkPollPush(); return null; }
                return pkPost(true);
            }).then(function (r) {
            if (!r) return;
            const b = r.body || {};
            if (!r.ok) {
                pkSummary.className = 'fw-semibold mb-2 text-danger';
                pkSummary.textContent = b.message || 'Could not check Amazon.';
                pkWrap.innerHTML = '';
                return;
            }
            pkSummary.textContent = b.message || '';
            pkRender(b, false); // enables / labels the button from the ticked campaigns
            const t = b.totals || {};
            if ((Number(t.kw_to_add || 0) + Number(t.hl_to_add || 0)) === 0) pkConfirm.disabled = true;
        }).catch(function (e) {
            pkSummary.className = 'fw-semibold mb-2 text-danger';
            pkSummary.textContent = 'Failed: ' + e.message;
        });
    }

    if (pkBtn) pkBtn.addEventListener('click', pkOpen);

    if (pkConfirm) {
        pkConfirm.addEventListener('click', function () {
            const selected = Array.from(pkState.selected);
            if (!selected.length) return;
            if (!confirm('This adds keywords to ' + selected.length.toLocaleString() + ' selected campaign(s) on Amazon. Continue?')) return;
            const original = pkConfirm.innerHTML;
            pkConfirm.disabled = true;
            pkConfirm.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Pushing…';
            pkSummary.className = 'fw-semibold mb-2';
            pkSummary.textContent = 'Starting the push…';
            // The push runs in the background on the server (it takes many minutes); poll its progress here.
            pkPost(false, selected).then(function (r) {
                const b = r.body || {};
                if (!r.ok) {
                    pkSummary.className = 'fw-semibold mb-2 text-danger';
                    pkSummary.textContent = b.message || 'Push failed.';
                    pkWrap.innerHTML = '';
                    pkConfirm.disabled = false;
                    pkConfirm.innerHTML = original;
                    return;
                }
                pkEl.toolbar.classList.add('d-none');
                pkEl.toolbar.classList.remove('d-flex');
                pkPollPush(original);
            }).catch(function (e) {
                pkSummary.className = 'fw-semibold mb-2 text-danger';
                pkSummary.textContent = 'Push failed: ' + e.message;
                pkConfirm.disabled = false;
                pkConfirm.innerHTML = original;
            });
        });
    }

    // Poll the background push until it is done / failed; shows live progress against the keywords found by the check.
    const pushStatusUrl = @json(route('amazon.ads.fbm-targeting.push-status'));
    let pkPollTimer = null;
    function pkPollPush(originalBtnHtml) {
        clearTimeout(pkPollTimer);
        pkConfirm.disabled = true;
        pkConfirm.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Pushing…';
        const tick = function () {
            fetch(pushStatusUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (res) { return res.json(); })
                .then(function (b) {
                    const t = b.totals || {};
                    if (b.status === 'running') {
                        const sent = Number(t.added || 0) + Number(t.failed || 0);
                        pkSummary.className = 'fw-semibold mb-2';
                        pkSummary.textContent = 'Pushing to Amazon in the background… ' + sent.toLocaleString() + ' keyword(s) processed' +
                            (t.failed ? ' (' + Number(t.failed).toLocaleString() + ' rejected)' : '') + (b.message ? ' · ' + b.message : '') +
                            '. You can close this window; reopen it to check progress.';
                        pkPollTimer = setTimeout(tick, 4000);
                        return;
                    }
                    if (b.status === 'done' || b.status === 'error') {
                        pkSummary.className = 'fw-semibold mb-2 ' + (b.status === 'error' ? 'text-danger' : (t.failed ? 'text-warning' : 'text-success'));
                        pkSummary.textContent = b.message || (b.status === 'done' ? 'Done.' : 'Push failed.');
                        if (b.status === 'done') pkRender(b, true); else pkWrap.innerHTML = '';
                        pkConfirm.innerHTML = originalBtnHtml || '<i class="fas fa-upload"></i> Push now';
                        pkConfirm.disabled = b.status === 'done';
                        return;
                    }
                    pkPollTimer = setTimeout(tick, 4000);
                })
                .catch(function () { pkPollTimer = setTimeout(tick, 6000); }); // transient network error: keep polling
        };
        tick();
    }

    // ---------- Specific KW (manual, independent of cousins) ----------
    const specificUrls = {
        add: @json(route('amazon.ads.fbm-targeting.specific.add')),
        remove: @json(route('amazon.ads.fbm-targeting.specific.remove')),
    };
    const skState = { campaignId: '', list: [], kind: 'kw' };
    const skOptions = {
        kw: [['', 'No match type'], ['BROAD', 'Broad'], ['PHRASE', 'Phrase'], ['EXACT', 'Exact']],
        neg: [['', 'No match type'], ['NEGATIVE_PHRASE', 'Negative phrase'], ['NEGATIVE_EXACT', 'Negative exact']],
    };
    const skEl = {
        modal: document.getElementById('specificModal'),
        title: document.getElementById('sk-title'),
        input: document.getElementById('sk-input'),
        match: document.getElementById('sk-match'),
        add: document.getElementById('sk-add'),
        msg: document.getElementById('sk-msg'),
        list: document.getElementById('sk-list'),
        count: document.getElementById('sk-count'),
        heading: document.getElementById('sk-heading'),
        listHeading: document.getElementById('sk-list-heading'),
        desc: document.getElementById('sk-desc'),
    };

    function skMsg(text, ok) {
        skEl.msg.style.display = text ? 'block' : 'none';
        skEl.msg.className = 'small mb-2 ' + (ok ? 'text-success' : 'text-danger');
        skEl.msg.textContent = text || '';
    }

    function skPost(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify(body),
        }).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (b) {
                return { ok: res.ok && b.success !== false, body: b };
            });
        });
    }

    function skRender() {
        const list = skState.list;
        skEl.count.textContent = list.length ? '(' + list.length + ')' : '';
        if (!list.length) {
            skEl.list.innerHTML = '<span class="aft-empty">' + (skState.kind === 'neg' ? 'No specific negative keywords yet' : 'No specific keywords yet') + '</span>';
            return;
        }
        skEl.list.innerHTML = list.map(function (k) {
            return '<div class="sk-item"><span class="aft-chip-text">' + escapeHtml(k.keyword) + '</span>' +
                (k.match_type ? '<span class="aft-chip-match">' + escapeHtml(k.match_type) + '</span>' : '') +
                '<button type="button" class="btn btn-sm btn-outline-danger py-0 sk-remove" data-id="' + Number(k.id) + '" title="Remove">&times;</button></div>';
        }).join('');
    }

    function skRefreshGrid() {
        table.setPage(table.getPage() || 1);
    }

    function openSpecificModal(row, kind) {
        const neg = kind === 'neg';
        skState.kind = neg ? 'neg' : 'kw';
        skState.campaignId = String(row.campaign_id || '');
        const src = neg ? row.specific_neg : row.specific;
        skState.list = Array.isArray(src) ? src.slice() : [];
        skEl.heading.textContent = neg ? 'S Negative KW' : 'Specific KW';
        skEl.listHeading.textContent = neg ? 'Specific negative keywords' : 'Specific keywords';
        skEl.desc.textContent = neg
            ? 'Negative keywords added by hand for this campaign only. They are never imported from Amazon and never shared with KW cousins.'
            : 'Keywords added by hand for this campaign only. They are never imported from Amazon and never shared with KW cousins.';
        skEl.match.innerHTML = skOptions[skState.kind].map(function (o) {
            return '<option value="' + o[0] + '">' + o[1] + '</option>';
        }).join('');
        skEl.title.textContent = row.campaign_name || '';
        skEl.input.value = '';
        skEl.match.value = '';
        skMsg('', true);
        skRender();
        if (window.bootstrap && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(skEl.modal).show();
        } else if (window.jQuery) {
            window.jQuery(skEl.modal).modal('show');
        }
    }

    skEl.add.addEventListener('click', function () {
        const text = (skEl.input.value || '').trim();
        if (!text) { skMsg('Enter at least one keyword.', false); return; }
        skEl.add.disabled = true;
        skPost(specificUrls.add, { campaign_id: skState.campaignId, keywords: text, match_type: skEl.match.value, kind: skState.kind })
            .then(function (r) {
                const b = r.body || {};
                if (r.ok) {
                    skState.list = b.specific || [];
                    skEl.input.value = '';
                    skRender();
                    skMsg(b.message || 'Added.', true);
                    skRefreshGrid();
                } else {
                    skMsg(b.message || (b.errors && Object.values(b.errors)[0]) || 'Could not add.', false);
                }
            })
            .catch(function (e) { skMsg('Failed: ' + e.message, false); })
            .finally(function () { skEl.add.disabled = false; });
    });

    skEl.list.addEventListener('click', function (e) {
        const btn = e.target.closest('.sk-remove');
        if (!btn) return;
        skPost(specificUrls.remove, { campaign_id: skState.campaignId, id: Number(btn.dataset.id), kind: skState.kind }).then(function (r) {
            const b = r.body || {};
            if (r.ok) {
                skState.list = b.specific || [];
                skRender();
                skMsg(b.message || 'Removed.', true);
                skRefreshGrid();
            } else {
                skMsg(b.message || 'Could not remove.', false);
            }
        });
    });

    if (cousinSearchEl) {
        cousinSearchEl.addEventListener('input', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function () { table.setPage(1); }, 300);
        });
    }

    // Re-fit the column widths to each newly loaded page / search result.
    table.on('dataLoaded', function () {
        setTimeout(function () { try { table.redraw(true); } catch (e) { /* table not ready */ } }, 0);
    });

    // Click a "low count" badge to show only those campaigns; click again to clear.
    lowBadgeEls.forEach(function (el) {
        const toggle = function () {
            const k = el.getAttribute('data-low');
            lowFilter = lowFilter === k ? '' : k;
            table.setPage(1);
        };
        el.addEventListener('click', toggle);
        el.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(); }
        });
    });

    if (scopeEl) {
        scopeEl.addEventListener('change', function () { table.setPage(1); });
    }

    [searchEl, parentSearchEl, titleSearchEl].forEach(function (el) {
        if (!el) return;
        el.addEventListener('input', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function () { table.setPage(1); }, 300);
        });
    });
})();
</script>
@endsection
