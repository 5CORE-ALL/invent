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
        font-size: 12.5px; text-align: center; padding: 6px 4px; white-space: normal; line-height: 1.2;
    }
    .amz-fbm-targeting .tabulator-row { min-height: 36px; }
    .amz-fbm-targeting .tabulator-row:hover { background-color: #f8fafc !important; }
    .amz-fbm-targeting .tabulator-cell { padding: 6px 5px !important; }
    .amz-fbm-targeting .aft-loadbar { background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:10px 14px; margin-bottom:10px; }
    .amz-fbm-targeting .aft-loadbar-top { display:flex; justify-content:space-between; gap:12px; font-size:.85rem; font-weight:600; color:#1e3a8a; margin-bottom:6px; }
    .amz-fbm-targeting .tabulator-row.aft-has-panel { flex-wrap: wrap; }
    .amz-fbm-targeting .aft-sku-panel { flex: 0 0 100%; width: 100%; display: block; box-sizing: border-box; clear: both; white-space: normal; text-align: left;
        padding: 8px 14px 10px; background: #f8fafc; border-top: 1px dashed #94a3b8; font-size: 12px; cursor: default; }
    .amz-fbm-targeting .aft-sku-panel table { width: 100%; border-collapse: collapse; background: #fff; }
    .amz-fbm-targeting .aft-sku-panel th, .amz-fbm-targeting .aft-sku-panel td { border: 1px solid #e2e8f0; padding: 3px 8px; vertical-align: middle; }
    .amz-fbm-targeting .aft-sku-panel thead th { background: #e0ecff; color: #334155; font-weight: 700; }
    .amz-fbm-targeting .aft-sku-panel img { width: 34px; height: 34px; object-fit: cover; border-radius: 4px; }
    .amz-fbm-targeting .aft-sku-btn { border: 1px solid #93c5fd; background: #eff6ff; color: #1d4ed8; border-radius: 6px; padding: 1px 8px; font-size: 12px; cursor: pointer; }
    .amz-fbm-targeting .aft-sku-btn.is-open { background: #1d4ed8; color: #fff; }
    .amz-fbm-targeting .aft-status-dot { display: inline-block; width: 12px; height: 12px; border-radius: 50%; box-shadow: 0 0 0 2px rgba(0,0,0,.06); vertical-align: middle; }
    .amz-fbm-targeting .aft-mag { cursor: pointer; color: #1d4ed8; font-size: 13px; padding: 3px; border-radius: 4px; vertical-align: middle; }
    .amz-fbm-targeting .aft-mag:hover { background: #dbeafe; color: #1e40af; }
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
                    <div class="d-flex gap-2 align-items-center flex-wrap">
                        <button type="button" id="aft-push-sel" class="btn btn-success btn-sm fw-bold" disabled
                                title="Tick campaigns in the grid. A check shows what is missing; after you confirm, their live keywords are pulled from Amazon and the missing ones pushed (KW campaigns and their parents' HL campaigns), in the background with progress.">
                            <i class="fas fa-check-square"></i> Push / Pull selected (0)
                        </button>
                        <button type="button" id="aft-sel-clear" class="btn btn-outline-light btn-sm d-none" title="Clear the selection">Clear</button>
                        <button type="button" id="aft-undo-btn" class="btn btn-outline-light btn-sm fw-bold"
                                title="Undo a previous push: archive on Amazon the keywords it sent to KW campaigns">
                            <i class="fas fa-undo"></i> Undo a push
                        </button>
                    </div>
                    <span class="small">Campaign names from the same SP reports table as Ads All. Targets from Amazon Ads (keywords + product targets).</span>
                </div>
                <div class="card-body">
                    <div class="mb-3 d-flex gap-2 flex-wrap align-items-center">
                        <div class="btn-group btn-group-sm" role="group" id="aft-type-group" aria-label="Campaign type">
                            <button type="button" class="btn btn-primary active" data-type="" title="Show every campaign type (clears the KW / PT / HL selection)">All</button>
                            <button type="button" class="btn btn-outline-primary" data-type="kw" title="KW campaigns (Sponsored Products keyword campaigns)">KW</button>
                            <button type="button" class="btn btn-outline-primary" data-type="pt" title="PT campaigns (Sponsored Products product targeting)">PT</button>
                            <button type="button" class="btn btn-outline-primary" data-type="hl" title="HL / HEAD campaigns (Sponsored Brands headline)">HL / Head</button>
                        </div>
                        <input type="text" id="aft-cousin-search" class="form-control form-control-sm"
                               style="max-width: 260px;" placeholder="Search KW cousins..." autocomplete="off"
                               title="Filter by cousin name, cousin parent or cousin campaign">
                        <select id="aft-status" class="form-select form-select-sm" style="max-width: 150px;" title="Campaign status (same values as Ads All)">
                            <option value="" selected>All statuses</option>
                            <option value="ENABLED">Enabled</option>
                            <option value="PAUSED">Paused</option>
                            <option value="ARCHIVED">Archived</option>
                        </select>
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
                            <i class="fas fa-chart-line"></i> Pull LT
                        </button>
                        <button type="button" id="aft-pull-kw" class="btn btn-sm btn-success"
                                title="Add keywords that exist in Amazon KW campaigns but not in the app. Existing keywords are kept.">
                            <i class="fas fa-cloud-download-alt"></i> Pull KW
                        </button>
                        <button type="button" id="aft-pull-neg" class="btn btn-sm btn-danger"
                                title="Add negative keywords that exist in Amazon KW campaigns but not in the app. Existing negative keywords are kept.">
                            <i class="fas fa-ban"></i> Pull Neg KW
                        </button>
                    </div>
                    <div id="aft-pull-msg" class="small mb-2" style="display:none;"></div>
                    <div id="aft-sync-panel" class="mb-2 p-2 border rounded bg-light" style="display:none;" role="status" aria-live="polite">
                        <div class="d-flex justify-content-between small fw-semibold">
                            <span id="aft-sync-text">Sync…</span>
                            <span id="aft-sync-pct" class="text-muted"></span>
                        </div>
                        <div class="progress my-1" style="height:10px;">
                            <div id="aft-sync-fill" class="progress-bar progress-bar-striped progress-bar-animated" style="width:0%"></div>
                        </div>
                        <div id="aft-sync-counts" class="small text-muted"></div>
                        <div id="aft-sync-rows" class="small mt-2" style="display:none; max-height:260px; overflow:auto;"></div>
                    </div>
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
                <button type="button" id="aft-push-campaign" class="btn btn-warning btn-sm fw-bold ms-auto me-2"
                    title="Add this campaign's keywords that are missing on Amazon, through the Amazon Ads API">
                    <i class="fas fa-upload"></i> Push to Amazon
                </button>
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
                    Each ticked KW campaign gets its own keywords plus the real keywords of all the OTHER ticked campaigns that it is still missing - whatever their parent or KW cousin group (up to 40 ticked campaigns; un-ticked campaigns are never touched; keywords copied from KW cousins are pushed to the campaign that holds them), and each parent's keywords (all of its KW campaigns together)
                    are added to its HL campaign. Keywords already live on a campaign (same text + match type) are skipped.
                    Only Broad / Phrase / Exact keywords are sent. Negative keywords are not touched.
                </p>
                <div id="pk-summary" class="fw-semibold mb-2"></div>
                <div id="pk-toolbar" class="d-none align-items-center gap-2 flex-wrap mb-2">
                    <input type="text" id="pk-filter" class="form-control form-control-sm" style="max-width: 260px;"
                           placeholder="Filter campaigns..." autocomplete="off">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="pk-sel-shown" title="Tick every campaign currently shown">Select shown</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="pk-sel-none" title="Untick every campaign currently shown">Clear shown</button>
                    <button type="button" class="btn btn-sm btn-outline-primary d-none" id="pk-sel-mine"
                            title="Keep only the campaigns you ticked in the grid (and the HL of their parents); untick the linked KW cousin campaigns that were added">Only my selected</button>
                    <button type="button" class="btn btn-sm btn-outline-warning d-none" id="pk-sel-cousins"
                            title="Also tick the linked KW cousin campaigns (marked cousin)">Include linked cousins</button>
                    <button type="button" class="btn btn-sm btn-outline-info d-none" id="pk-sel-hl"
                            title="Also tick the HL (HEAD) campaigns of the parents of the ticked campaigns">Include HL</button>
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

<div class="modal fade" id="undoPushModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-undo"></i> Undo a push</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2">
                    Archives on Amazon the keywords a push sent to KW campaigns (only keywords created by that push; older identical
                    keywords are never touched) and removes them from the app. Archiving is permanent. HL (HEAD) keywords are not covered.
                </p>
                <div id="undo-runs"><span class="text-muted">Loading…</span></div>
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
    const statusEl = document.getElementById('aft-status');
    const typeGroupEl = document.getElementById('aft-type-group');
    const typeSel = new Set(); // any of 'kw', 'pt', 'hl'; empty = all types
    let typeFilter = ''; // comma separated typeSel, sent to the server
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

    // ---------- SKUs of a campaign, shown under its row (same data as "Campaign SKUs" on /amazon-ads/all) ----------
    const SKU_URL = @json(route('amazon.ads.campaign-skus'));

    function skuState(st) {
        const s = String(st || '').trim().toUpperCase();
        if (!s) return '<span class="aft-empty">-</span>';
        const color = s === 'ENABLED' ? '#16a34a' : (s === 'PAUSED' ? '#dc2626' : (s === 'ARCHIVED' ? '#ca8a04' : '#6b7280'));
        return '<strong style="color:' + color + ';">' + escapeHtml(s) + '</strong>';
    }

    function money(v) {
        return v === null || v === undefined || v === '' || isNaN(Number(v)) ? '<span class="aft-empty">—</span>' : '$' + Number(v).toFixed(2);
    }

    // Effective state of a SKU: an ad can be ENABLED on Amazon while its campaign is PAUSED / ARCHIVED - it is not running then.
    function skuEffectiveState(adState, campaignState) {
        const ad = String(adState || '').trim().toUpperCase();
        const camp = String(campaignState || '').trim().toUpperCase();
        if (ad === 'ENABLED' && camp && camp !== 'ENABLED') {
            return skuState(camp) + '<div class="text-muted" style="font-size:11px;font-weight:400;" title="The ad itself is enabled on Amazon, but its campaign is ' + escapeHtml(camp.toLowerCase()) + ', so it is not running">ad enabled · campaign ' + escapeHtml(camp.toLowerCase()) + '</div>';
        }
        return skuState(ad);
    }

    function skuRowsHtml(list, offAd, campaignState) {
        return list.map(function (s) {
            const rating = s.amz_avg_rating !== null && s.amz_avg_rating !== undefined
                ? '★ ' + Number(s.amz_avg_rating).toFixed(1) + ' (' + Number(s.amz_review_count || 0).toLocaleString() + ')'
                : '<span class="aft-empty">—</span>';
            const inv = s.inv === null || s.inv === undefined ? '<span class="aft-empty">—</span>'
                : '<span style="' + (Number(s.inv) <= 0 ? 'color:#dc2626;font-weight:700;' : '') + '">' + Number(s.inv).toLocaleString() + '</span>';
            return '<tr>' +
                '<td style="width:48px;text-align:center;">' + (s.image ? '<img src="' + escapeHtml(s.image) + '" alt="">' : '') + '</td>' +
                '<td style="text-align:right;">' + inv + '</td>' +
                '<td><strong>' + escapeHtml(s.sku || '') + '</strong></td>' +
                '<td>' + escapeHtml(s.asin || '') + '</td>' +
                '<td style="text-align:right;">' + money(s.price) + '</td>' +
                '<td style="text-align:right;">' + (s.lmp !== null && s.lmp !== undefined ? '<span style="color:#16a34a;">' + money(s.lmp) + '</span>' : '<span class="aft-empty">—</span>') + '</td>' +
                '<td>' + rating + '</td>' +
                '<td>' + (offAd ? '<span class="text-muted">Not on this ad</span>' : skuEffectiveState(s.state, campaignState)) + '</td></tr>';
        }).join('');
    }

    function skuPanelHtml(d, body) {
        const onAd = Array.isArray(body.skus) ? body.skus : [];
        const parentRows = Array.isArray(body.parent_skus) ? body.parent_skus : [];
        const st = String(d.status || '').trim().toUpperCase();
        const head = '<div class="mb-1"><strong>' + escapeHtml(d.campaign_name || '') + '</strong> · campaign status: ' + skuState(st) +
            ' · ' + onAd.length.toLocaleString() + ' SKU' + (onAd.length === 1 ? '' : 's') + ' on the ad' +
            (body.parent_family ? ' · parent family <strong>' + escapeHtml(body.parent_family) + '</strong>' + (parentRows.length ? ' (' + parentRows.length.toLocaleString() + ' more SKU' + (parentRows.length === 1 ? '' : 's') + ' not on this ad)' : '') : '') +
            '</div>';
        if (!onAd.length && !parentRows.length) return head + '<span class="aft-empty">No SKUs found for this campaign.</span>';
        return head + '<table><thead><tr><th></th><th>Inv</th><th>SKU</th><th>ASIN</th><th>Item Price</th><th>LMP</th><th>Reviews</th><th>State</th></tr></thead><tbody>' +
            skuRowsHtml(onAd, false, st) + skuRowsHtml(parentRows, true, st) + '</tbody></table>';
    }

    function toggleSkuPanel(row, btn) {
        const el = row.getElement();
        const old = el.querySelector(':scope > .aft-sku-panel');
        if (old) {
            old.remove();
            el.classList.remove('aft-has-panel');
            if (btn) btn.classList.remove('is-open');
            row.normalizeHeight();
            return;
        }
        const d = row.getData() || {};
        const panel = document.createElement('div');
        panel.className = 'aft-sku-panel';
        panel.innerHTML = '<span class="text-muted">Loading SKUs…</span>';
        el.classList.add('aft-has-panel');
        el.appendChild(panel);
        if (btn) btn.classList.add('is-open');
        row.normalizeHeight();
        const url = SKU_URL + '?campaign_id=' + encodeURIComponent(d.campaign_id || '') + '&campaign_name=' + encodeURIComponent(d.campaign_name || '');
        fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (res) { return res.json().then(function (b) { return { ok: res.ok, body: b }; }); })
            .then(function (r) {
                panel.innerHTML = r.ok ? skuPanelHtml(d, r.body || {}) : '<span class="text-danger">' + escapeHtml((r.body && r.body.message) || 'Could not load SKUs.') + '</span>';
            })
            .catch(function () { panel.innerHTML = '<span class="text-danger">Could not load SKUs.</span>'; })
            .finally(function () { row.normalizeHeight(); });
    }

    // Floating tooltip with the full product title (the Title column is narrow and clamped to 2 lines).
    let titleTipEl = null;
    function showTitleTip(text, e) {
        if (!text) return;
        if (!titleTipEl) {
            titleTipEl = document.createElement('div');
            titleTipEl.style.cssText = 'position:fixed;z-index:20000;max-width:460px;padding:8px 10px;background:#111827;color:#fff;' +
                'font-size:13px;line-height:1.4;border-radius:6px;box-shadow:0 4px 14px rgba(0,0,0,.3);pointer-events:none;white-space:normal;display:none;';
            document.body.appendChild(titleTipEl);
        }
        titleTipEl.textContent = text;
        titleTipEl.style.display = 'block';
        moveTitleTip(e);
    }
    function moveTitleTip(e) {
        if (!titleTipEl || titleTipEl.style.display === 'none') return;
        const pad = 14;
        let x = e.clientX + pad;
        let y = e.clientY + pad;
        const w = titleTipEl.offsetWidth, h = titleTipEl.offsetHeight;
        if (x + w > window.innerWidth - 8) x = Math.max(8, e.clientX - w - pad);
        if (y + h > window.innerHeight - 8) y = Math.max(8, e.clientY - h - pad);
        titleTipEl.style.left = x + 'px';
        titleTipEl.style.top = y + 'px';
    }
    function hideTitleTip() { if (titleTipEl) titleTipEl.style.display = 'none'; }

    // Toolbar badges (missing titles / cousins, LT coverage, "low" counts) from a data response.
    function applyBadges(response) {
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
    }

    // Refresh only the given campaigns (by name) in place: no page reload, no scroll / page / selection reset.
    // The server returns just those rows; the badges are refreshed from the same response.
    const rowRefreshBusy = { n: 0 };
    function refreshRows(names) {
        names = Array.from(new Set((names || []).filter(Boolean)));
        if (!names.length || typeof table === 'undefined' || !table) return Promise.resolve();
        const chunks = [];
        for (let i = 0; i < names.length; i += 25) chunks.push(names.slice(i, i + 25));
        rowRefreshBusy.n++;
        return Promise.all(chunks.map(function (chunk, ci) {
            const q = new URLSearchParams();
            q.set('page', '1');
            q.set('size', '100');
            chunk.forEach(function (n) { q.append('names[]', n); });
            return fetch(dataUrl + '?' + q.toString(), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (res) { return res.ok ? res.json() : null; })
                .then(function (json) {
                    if (!json || !Array.isArray(json.data)) return;
                    // parent_first only makes sense for the whole page, not for this handful of rows: keep the current one.
                    const rows = json.data.map(function (d) { const c = Object.assign({}, d); delete c.parent_first; return c; });
                    if (rows.length) table.updateData(rows);
                    if (ci === 0) applyBadges(json);
                })
                .catch(function () { /* the next refresh will try again */ });
        })).finally(function () { rowRefreshBusy.n--; });
    }
    // Refresh the campaigns currently shown in the grid.
    function refreshVisibleRows() {
        if (typeof table === 'undefined' || !table) return Promise.resolve();
        return refreshRows(table.getRows().map(function (r) { return (r.getData() || {}).id; }));
    }

    const table = new Tabulator('#aft-table', {
        layout: 'fitColumns', // all columns fit in the screen width (no sideways scroll): short columns are fixed, text columns share the rest
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
            const statusQ = statusEl ? statusEl.value : '';
            if (statusQ) q.set('status', statusQ);
            if (typeFilter) q.set('type', typeFilter);
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
            applyBadges(response);
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
                title: '',
                formatter: 'rowSelection',
                titleFormatter: 'rowSelection',
                titleFormatterParams: { rowRange: 'active' },
                hozAlign: 'center',
                headerHozAlign: 'center',
                headerSort: false,
                width: 42,
                minWidth: 42,
                cellClick: function (e, cell) {
                    if (e.target && e.target.tagName === 'INPUT') return; // the checkbox handles itself
                    cell.getRow().toggleSelect();
                },
            },
            {
                title: 'KWC Cousin',
                field: 'cousin_name',
                minWidth: 80,
                widthGrow: 1,
                headerSort: false,
                formatter: function (cell) {
                    const n = String(cell.getValue() || '').trim();
                    return n ? '<strong class="text-primary">' + escapeHtml(n) + '</strong>' : '';
                },
            },
            {
                title: 'Parents',
                field: 'parent',
                minWidth: 60,
                widthGrow: 1,
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
                minWidth: 100,
                widthGrow: 2,
                headerSort: false,
                formatter: function (cell) {
                    const d = cell.getRow().getData() || {};
                    const t = String(d.title || '').trim();
                    if (!t) return '<span class="aft-empty">-</span>';
                    // Narrow column: one line cut with "…"; hovering shows the full title.
                    return '<div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + escapeHtml(t) + '</div>';
                },
                cellMouseEnter: function (e, cell) {
                    const t = String(((cell.getRow().getData() || {}).title) || '').trim();
                    showTitleTip(t, e);
                },
                cellMouseMove: function (e) { moveTitleTip(e); },
                cellMouseLeave: function () { hideTitleTip(); },
            },
            {
                title: 'Campaign name',
                field: 'campaign_name',
                minWidth: 120,
                widthGrow: 2,
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
                width: 56,
                hozAlign: 'center',
                headerSort: false,
                formatter: function (cell) {
                    const st = String(cell.getValue() || '').trim().toUpperCase();
                    if (!st) return '<span class="aft-empty">-</span>';
                    // Dot only: Enabled = green, Paused = yellow, anything else = gray (hover for the text).
                    const color = st === 'ENABLED' ? '#16a34a' : (st === 'PAUSED' ? '#dc2626' : (st === 'ARCHIVED' ? '#eab308' : '#9ca3af'));
                    const label = st.charAt(0) + st.slice(1).toLowerCase();
                    return '<span class="aft-status-dot" style="background:' + color + ';" title="' + escapeHtml(label) + '" aria-label="' + escapeHtml(label) + '"></span>';
                },
            },
            {
                title: 'SKUs',
                field: 'campaign_id',
                width: 76,
                hozAlign: 'center',
                headerSort: false,
                formatter: function () {
                    return '<button type="button" class="aft-sku-btn" title="Show the SKUs advertised in this campaign under the row">SKUs ▾</button>';
                },
                cellClick: function (e, cell) {
                    const btn = e.target.closest('.aft-sku-btn');
                    if (btn) toggleSkuPanel(cell.getRow(), btn);
                },
            },
            {
                title: 'KW cousins',
                field: 'cousin_count',
                width: 80,
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
                width: 72,
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
                width: 72,
                hozAlign: 'right',
                headerSort: true,
                headerSortStartingDir: 'desc',
                sorter: 'number',
                headerTooltip: 'Total units sold, lifetime, across every campaign in this row\'s KW cousin group. From the stored daily Amazon Ads campaign reports.',
                formatter: ltFormatter,
            },
            {
                title: 'Target Amz',
                field: 'target_count',
                width: 76,
                hozAlign: 'center',
                headerSort: false,
                formatter: function (cell) {
                    const n = Number(cell.getValue() || 0);
                    // The number is only what is really on Amazon; keywords copied from KW cousins that still wait for a push
                    // are not counted - they are only mentioned in the tooltip.
                    const p = Number((cell.getRow().getData() || {}).target_pending || 0);
                    const tip = 'View targets' + (p > 0 ? ' (' + p.toLocaleString() + ' more copied from KW cousins, not pushed to Amazon yet)' : '');
                    return '<span class="aft-count-badge" title="' + tip + '">' + n.toLocaleString() + '</span>';
                },
                cellClick: function (_e, cell) {
                    openTargetsModal(cell.getRow().getData() || {}, 'kw');
                },
            },
            {
                title: 'App Target',
                field: 'app_target_count',
                width: 92,
                hozAlign: 'center',
                headerSort: false,
                headerTooltip: 'Keywords still to be pushed to Amazon: in the app (copied from KW cousins or added / edited by hand) but not on Amazon yet. Deleted and ignored keywords are not counted. Drops to 0 after a push.',
                formatter: function (cell) {
                    const n = Number(cell.getValue() || 0);
                    const tip = n
                        ? n.toLocaleString() + ' keyword(s) wait to be pushed to Amazon (Broad / Phrase / Exact; deleted and ignored ones are not counted).'
                        : 'Nothing waiting to be pushed to Amazon.';
                    // amber while something is waiting, plain when everything is on Amazon; cursor stays normal: only the magnifier is clickable
                    const style = ' style="cursor:default;' + (n ? 'background:#fef3c7;color:#92400e;border:1px solid #fcd34d;' : '') + '"';
                    return '<span class="aft-count-badge" title="' + tip + '"' + style + '>' + n.toLocaleString() + '</span>' +
                        ' <i class="fas fa-search aft-mag" role="button" tabindex="0" title="View the targets of this campaign" aria-label="View targets"></i>';
                },
                cellClick: function (e, cell) {
                    // only the magnifying glass opens the targets
                    if (!e.target || !e.target.closest || !e.target.closest('.aft-mag')) return;
                    openTargetsModal(cell.getRow().getData() || {}, 'kw');
                },
            },
            {
                title: 'Negative KW',
                field: 'negative_count',
                width: 76,
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
                width: 76,
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
                width: 80,
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
        const pushCampBtn = document.getElementById('aft-push-campaign');
        if (pushCampBtn) pushCampBtn.style.display = tgt.kind === 'neg' ? 'none' : '';
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
            refreshVisibleRows(); // refresh the grid counts / badges in place
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
    // Campaign ids ticked in the grid (set by the bulk-push wiring below); used by the Pull buttons.
    let pickedIdsFn = function () { return []; };
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

    // Pulling works on the ticked campaigns only: the buttons are off until something is ticked.
    let pullBusy = false;
    function pullSyncButtons() {
        if (pullBusy) return;
        const n = pickedIdsFn().length;
        pullDefs.forEach(function (d) {
            if (!d.btn || !d.label) return;
            d.btn.innerHTML = d.label();
            d.btn.disabled = n === 0;
            d.btn.title = n ? d.baseTitle : 'Tick campaigns in the grid first: pulling works on the selected campaigns only.';
        });
    }

    const pullStatusUrl = @json(route('amazon.ads.fbm-targeting.pull-status'));
    pullDefs.forEach(function (def) {
        if (!def.btn) return;
        const kind = def.what === 'keywords' ? 'kw' : 'neg';
        const baseHtml = def.btn.innerHTML;
        // With campaigns ticked in the grid, the pull covers only those (button shows the count).
        def.label = function () {
            const n = pickedIdsFn().length;
            return n ? baseHtml + ' (' + n.toLocaleString() + ' selected)' : baseHtml;
        };
        def.baseTitle = def.btn.title || '';

        function finish() {
            pullBusy = false;
            pullSyncButtons();
        }

        function applyStatus(b) {
            if (b.status === 'done') {
                showPullMsg((b.message || 'Done.') + ' (' + (b.campaigns || 0) + ' KW campaign' + (Number(b.campaigns) === 1 ? '' : 's') + ', ' + (b.fetched || 0) + ' ' + def.what + ' fetched)', true);
                refreshVisibleRows(); // update the rows in place, no page reload
                finish();
                return true;
            }
            if (b.status === 'error') {
                showPullMsg(b.message || 'Pull failed.', false);
                finish();
                return true;
            }
            showPullMsg((b.message || 'Pulling…') + ' (running in the background, you can keep working)', true);
            return false;
        }

        function poll() {
            fetch(pullStatusUrl + '?kind=' + kind, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (res) { return res.json(); })
                .then(function (b) { if (!applyStatus(b)) setTimeout(poll, 4000); })
                .catch(function () { setTimeout(poll, 6000); }); // a dropped poll must not kill the pull
        }

        def.btn.addEventListener('click', function () {
            const sel = pickedIdsFn();
            if (!sel.length) { showPullMsg('Tick the campaigns to pull first: pulling works on the selected campaigns only.', false); return; }
            const scopeText = 'ONLY the ' + sel.length.toLocaleString() + ' selected campaign' + (sel.length === 1 ? '' : 's') + ' (HL / PT campaigns among them are skipped)';
            if (!confirm('Pull ' + def.what + ' from ' + scopeText + '? Only ones missing in the app will be added; existing ones are kept.')) return;
            pullBusy = true;
            pullDefs.forEach(function (d) { if (d.btn) d.btn.disabled = true; });
            def.btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Pulling…';
            showPullMsg('Starting the pull of ' + def.what + ' from Amazon…', true);
            fetch(def.url, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify(sel.length ? { campaign_ids: sel } : {}),
            }).then(function (res) {
                return res.json().then(function (body) { return { ok: res.ok && body.success !== false, body: body }; },
                    function () { return { ok: false, body: { message: 'Pull failed to start (HTTP ' + res.status + ').' } }; });
            }).then(function (r) {
                if (!r.ok) {
                    showPullMsg(r.body.message || 'Pull failed.', false);
                    finish();
                    return;
                }
                poll();
            }).catch(function (e) {
                showPullMsg('Pull failed: ' + e.message, false);
                finish();
            });
        });
    });

    pullSyncButtons();

    // Sync with Amazon: pull + push in chunks on the server; the page only polls a tiny status endpoint.
    const syncStartUrl = @json(route('amazon.ads.fbm-targeting.sync-start'));
    const syncStatusUrl = @json(route('amazon.ads.fbm-targeting.sync-status'));
    const syncEl = {
        panel: document.getElementById('aft-sync-panel'),
        text: document.getElementById('aft-sync-text'),
        pct: document.getElementById('aft-sync-pct'),
        fill: document.getElementById('aft-sync-fill'),
        counts: document.getElementById('aft-sync-counts'),
        rows: document.getElementById('aft-sync-rows'),
        all: document.getElementById('aft-sync-all'),
        sel: document.getElementById('aft-sync-sel'),
    };
    let syncTimer = null;
    let syncRunning = false;
    let syncLiveSig = '';
    let syncLiveAt = 0;
    function syncLock(on) {
        syncRunning = on;
        if (syncEl.all) syncEl.all.disabled = on;
        if (syncEl.sel) syncEl.sel.disabled = on || !(syncEl.sel.dataset.n > 0);
    }
    function syncPaint(b) {
        if (!syncEl.panel) return false;
        const n = function (v) { return Number(v || 0).toLocaleString(); };
        syncEl.panel.style.display = 'block';
        const pct = Math.max(0, Math.min(100, Number(b.percent || 0)));
        syncEl.fill.style.width = pct + '%';
        syncEl.pct.textContent = pct + '%';
        syncEl.counts.textContent = 'Campaigns ' + n(b.done) + ' / ' + n(b.campaigns) +
            ' · pulled from Amazon: ' + n(b.pulled) + ' · to push: ' + n(b.to_add) + ' · pushed: ' + n(b.added) +
            (b.failed ? ' · rejected: ' + n(b.failed) : '');
        syncEl.text.textContent = b.message || 'Sync…';
        // What was pushed, per campaign (and what needed nothing), so the result is visible and not only a total.
        if (syncEl.rows) {
            const esc = function (v) { return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]; }); };
            const pushed = b.rows || [];
            const same = (b.up_to_date || []).filter(function (u) { return !u.note; });
            if (!pushed.length && !same.length) {
                syncEl.rows.style.display = 'none';
                syncEl.rows.innerHTML = '';
            } else {
                syncEl.rows.style.display = 'block';
                syncEl.rows.innerHTML = '<table class="table table-sm table-bordered mb-0 bg-white"><thead class="table-light"><tr><th style="width:60px">Type</th><th>Campaign</th>' +
                    '<th class="text-end">To add</th><th class="text-end">Pushed</th><th class="text-end">Rejected</th><th>Note</th></tr></thead><tbody>' +
                    pushed.map(function (r) {
                        return '<tr><td>' + esc(r.type) + '</td><td>' + esc(r.campaign_name) + '</td><td class="text-end">' + n(r.to_add) + '</td><td class="text-end ' + (r.added ? 'text-success fw-semibold' : '') + '">' + n(r.added) +
                            '</td><td class="text-end ' + (r.failed ? 'text-danger fw-semibold' : '') + '">' + n(r.failed) + '</td><td>' + esc(r.message || '') + '</td></tr>';
                    }).join('') +
                    same.map(function (u) {
                        return '<tr class="text-muted"><td>' + esc(u.type || 'KW') + '</td><td>' + esc(u.campaign_name) + '</td><td class="text-end">0</td><td class="text-end">0</td><td class="text-end">0</td><td>up to date' +
                            (u.keywords ? ' (' + n(u.keywords) + ' keywords)' : '') + '</td></tr>';
                    }).join('') + '</tbody></table>';
            }
        }
        if (b.status === 'done' || b.status === 'error') {
            syncEl.fill.classList.remove('progress-bar-animated', 'progress-bar-striped');
            syncEl.fill.classList.toggle('bg-danger', b.status === 'error');
            syncEl.fill.classList.toggle('bg-success', b.status === 'done');
            syncEl.text.className = b.status === 'error' ? 'text-danger' : (b.failed ? 'text-warning' : 'text-success');
            syncLock(false);
            // Refresh the rows on screen in place (no page reload), so the result can be checked right away.
            if (b.status === 'done') refreshVisibleRows();
            return true;
        }
        // While it runs, refresh the visible rows whenever the progress moved (at most every 6 s).
        const sig = [b.done, b.pulled, b.added, b.failed, b.chunk, b.phase].join('|');
        if (sig !== syncLiveSig && Date.now() - syncLiveAt > 6000 && !rowRefreshBusy.n) {
            syncLiveSig = sig;
            syncLiveAt = Date.now();
            refreshVisibleRows();
        }
        syncEl.fill.classList.add('progress-bar-animated', 'progress-bar-striped');
        syncEl.fill.classList.remove('bg-danger', 'bg-success');
        syncEl.text.className = '';
        syncLock(true);
        return false;
    }
    function syncPoll() {
        clearTimeout(syncTimer);
        fetch(syncStatusUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (res) { return res.json(); })
            .then(function (b) {
                if (b.status === 'idle') { syncLock(false); return; }
                if (!syncPaint(b)) syncTimer = setTimeout(syncPoll, 1500);
            })
            .catch(function () { syncTimer = setTimeout(syncPoll, 6000); });
    }
    function syncStart(ids) {
        const what = ids && ids.length ? ids.length.toLocaleString() + ' selected campaign(s) only' : 'ALL KW campaigns';
        if (!confirm('Pull from Amazon and push the missing keywords for ' + what + '? It runs in the background, chunk by chunk.')) return;
        syncStartRaw(ids && ids.length ? { campaign_ids: ids } : {});
    }
    // payload: { campaign_ids?, selected? }. selected = the ticks of the push check ("KW|id" / "HL|id").
    function syncStartRaw(payload) {
        syncLock(true);
        syncPaint({ status: 'running', percent: 0, message: 'Starting…' });
        if (syncEl.panel && syncEl.panel.scrollIntoView) syncEl.panel.scrollIntoView({ block: 'center', behavior: 'smooth' });
        fetch(syncStartUrl, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify(payload),
        }).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (b) { return { ok: res.ok && b.success !== false, body: b }; });
        }).then(function (r) {
            if (!r.ok) {
                syncPaint({ status: 'error', percent: 0, message: r.body.message || 'Could not start the sync.' });
                return;
            }
            syncPoll();
        }).catch(function (e) {
            syncPaint({ status: 'error', percent: 0, message: 'Could not start the sync: ' + e.message });
        });
    }
    if (syncEl.all) syncEl.all.addEventListener('click', function () { syncStart(null); });

    // Undo a push: list the logged push runs, archive the chosen run's keywords on Amazon (background job + progress bar).
    (function () {
        const btn = document.getElementById('aft-undo-btn');
        const modalEl = document.getElementById('undoPushModal');
        const box = document.getElementById('undo-runs');
        if (!btn || !modalEl || !box) return;
        const esc = function (v) { return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]; }); };
        const showModal = function (on) {
            if (window.bootstrap && bootstrap.Modal) { const m = bootstrap.Modal.getOrCreateInstance(modalEl); on ? m.show() : m.hide(); }
            else if (window.jQuery) { window.jQuery(modalEl).modal(on ? 'show' : 'hide'); }
        };
        btn.addEventListener('click', function () {
            box.innerHTML = '<span class="text-muted">Loading…</span>';
            showModal(true);
            fetch(@json(route('amazon.ads.fbm-targeting.push-runs')), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (res) { return res.json(); })
                .then(function (b) {
                    const runs = b.runs || [];
                    if (!runs.length) { box.innerHTML = '<span class="text-muted">No logged pushes yet. Pushes made from now on can be undone here.</span>'; return; }
                    box.innerHTML = '<table class="table table-sm table-bordered align-middle mb-0"><thead class="table-light"><tr><th>Pushed at</th><th>By</th>' +
                        '<th class="text-end">Campaigns</th><th class="text-end">Keywords</th><th class="text-end">Already undone</th><th></th></tr></thead><tbody>' +
                        runs.map(function (r) {
                            const left = r.keywords - r.undone;
                            return '<tr><td>' + esc(r.pushed_at) + '</td><td>' + esc(r.pushed_by || '') + '</td><td class="text-end">' + Number(r.campaigns).toLocaleString() +
                                '</td><td class="text-end">' + Number(r.keywords).toLocaleString() + '</td><td class="text-end">' + Number(r.undone).toLocaleString() + '</td><td class="text-end">' +
                                (left > 0 ? '<button type="button" class="btn btn-sm btn-danger undo-run" data-run="' + esc(r.run_token) + '" data-left="' + left + '">Undo (' + left.toLocaleString() + ')</button>' : '<span class="text-muted">done</span>') +
                                '</td></tr>';
                        }).join('') + '</tbody></table>';
                })
                .catch(function (e) { box.innerHTML = '<span class="text-danger">Could not load the pushes: ' + esc(e.message) + '</span>'; });
        });
        box.addEventListener('click', function (e) {
            const t = e.target.closest ? e.target.closest('.undo-run') : null;
            if (!t) return;
            if (!confirm('Archive on Amazon the ' + Number(t.getAttribute('data-left')).toLocaleString() + ' keyword(s) of this push? Archived keywords cannot be re-enabled.')) return;
            showModal(false);
            syncLock(true);
            syncPaint({ status: 'running', percent: 0, message: 'Starting the undo…' });
            if (syncEl.panel && syncEl.panel.scrollIntoView) syncEl.panel.scrollIntoView({ block: 'center', behavior: 'smooth' });
            fetch(@json(route('amazon.ads.fbm-targeting.push-undo')), {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ run_token: t.getAttribute('data-run') }),
            }).then(function (res) { return res.json().catch(function () { return {}; }).then(function (b) { return { ok: res.ok && b.success !== false, body: b }; }); })
              .then(function (r) {
                  if (!r.ok) { syncPaint({ status: 'error', percent: 0, message: r.body.message || 'Could not start the undo.' }); return; }
                  syncPoll();
              })
              .catch(function (e) { syncPaint({ status: 'error', percent: 0, message: 'Could not start the undo: ' + e.message }); });
        });
    })();
    syncPoll(); // pick up a sync that is already running (e.g. after a page reload)

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
        // Parent name only: the typed text must match the parent exactly or be its beginning ("04 CS" finds "04 CS", not "104 CS").
        const needle = q.replace(/\s+/g, ' ');
        kwcState.shown = kwcState.parents.filter(function (p) {
            const hay = String(p.parent).toLowerCase().replace(/\s+/g, ' ').trim();
            return needle === '' || hay.indexOf(needle) === 0;
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

    // KW campaign ids ticked in the grid for a bulk push; null = the normal push of every campaign.
    let pkBulk = null;

    function pkPost(dryRun, selected) {
        return fetch(pushUrl, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify(Object.assign({ dry_run: dryRun },
                selected ? { selected: selected } : {},
                pkBulk && pkBulk.length ? { campaign_ids: pkBulk } : {})),
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
        selMine: document.getElementById('pk-sel-mine'),
        selCousins: document.getElementById('pk-sel-cousins'),
        selHl: document.getElementById('pk-sel-hl'),
    };
    const pkState = { rows: [], selected: new Set(), filter: '', upToDate: [] };
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

    // Bulk push: KW rows that were not ticked in the grid come from KW cousin links of the ticked campaigns.
    function pkIsCousinRow(r) {
        return !!(pkBulk && pkBulk.length && r.type === 'KW' && pkBulk.indexOf(String(r.campaign_id)) === -1);
    }
    function pkOnlyMine() {
        const parents = new Set();
        pkState.rows.forEach(function (r) {
            if (r.type === 'KW' && !pkIsCousinRow(r)) String(r.parent || '').split(',').forEach(function (x) { if (x.trim()) parents.add(x.trim().toUpperCase()); });
        });
        pkState.selected = new Set();
        pkState.rows.forEach(function (r) {
            if (r.type === 'KW' && !pkIsCousinRow(r)) pkState.selected.add(pkKey(r)); // HL rows stay unticked until asked for
        });
        pkDraw();
    }
    function pkIncludeHl() {
        const parents = new Set();
        pkState.rows.forEach(function (r) {
            if (r.type === 'KW' && pkState.selected.has(pkKey(r))) String(r.parent || '').split(',').forEach(function (x) { if (x.trim()) parents.add(x.trim().toUpperCase()); });
        });
        pkState.rows.forEach(function (r) {
            if (r.type !== 'HL') return;
            const ps = String(r.parent || '').split(',').map(function (x) { return x.trim().toUpperCase(); });
            if (ps.some(function (x) { return parents.has(x); })) pkState.selected.add(pkKey(r));
        });
        pkDraw();
    }

    // Selected KW campaigns that already have all of their own keywords on Amazon: shown greyed so none looks missing.
    function pkUpToDateRows() {
        const q = pkState.filter.trim().toLowerCase();
        return (pkState.upToDate || []).filter(function (u) { return !q || String(u.campaign_name).toLowerCase().indexOf(q) !== -1; }).map(function (u) {
            return '<tr class="text-muted"><td><input type="checkbox" disabled title="Nothing to push: this campaign already has all the keywords it should (its own + the other ticked campaigns) on Amazon"></td><td><span class="badge bg-secondary">' + escapeHtml(u.type || 'KW') + '</span></td>' +
                '<td>' + escapeHtml(u.type === 'HL' && !u.campaign_id ? 'Parent ' + u.campaign_name : u.campaign_name) + '</td>' +
                (u.note
                    ? '<td class="text-end text-warning" title="This parent has no HEAD / HL campaign linked, so nothing can be pushed to one">' + escapeHtml(u.note) + '</td></tr>'
                    : '<td class="text-end" title="Nothing missing: every keyword this campaign should have is live on Amazon">up to date' + (u.keywords ? ' <span class="text-muted">(' + Number(u.keywords).toLocaleString() + ' keywords)</span>' : '') + '</td></tr>');
        }).join('');
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
                    '<td>' + escapeHtml(r.campaign_name) + (pkIsCousinRow(r) ? ' <span class="badge bg-warning text-dark" title="Not ticked in the grid - added because it is linked as a KW cousin of a ticked campaign">cousin</span>' : '') + '</td>' +
                    '<td class="text-end">' + Number(r.to_add || 0).toLocaleString() + '</td></tr>';
            }).join('') + pkUpToDateRows() + '</tbody></table>';
        pkUpdateSelection();
    }

    function pkRender(body, done) {
        const rows = body.rows || [];
        if (!done) {
            // always start from this check's result: a previous check's rows must never be redrawn
            pkState.upToDate = body.up_to_date || [];
            pkState.rows = rows;
            pkState.selected = new Set();
            pkState.filter = '';
        }
        if (!rows.length && !done && pkState.upToDate.length) {
            pkEl.toolbar.classList.add('d-none');
            pkEl.toolbar.classList.remove('d-flex');
            pkWrap.innerHTML = '<div class="aft-empty mb-2">Nothing to push: every selected campaign already has all the keywords it should (its own + the other ticked campaigns) on Amazon.</div>' +
                '<table class="table table-sm table-bordered align-middle mb-0"><tbody>' + pkUpToDateRows() + '</tbody></table>';
            return;
        }
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
    if (pkEl.selMine) pkEl.selMine.addEventListener('click', pkOnlyMine);
    if (pkEl.selHl) pkEl.selHl.addEventListener('click', pkIncludeHl);
    if (pkEl.selCousins) pkEl.selCousins.addEventListener('click', function () {
        pkState.rows.forEach(function (r) { pkState.selected.add(pkKey(r)); });
        pkDraw();
    });
    pkEl.selNone.addEventListener('click', function () { pkShownRows().forEach(function (r) { pkState.selected.delete(pkKey(r)); }); pkDraw(); });

    // The check runs in the background on the server (it reads every campaign's live keywords from Amazon);
    // poll it until it is done, with a visible timer so the window never looks frozen.
    const pushCheckUrl = @json(route('amazon.ads.fbm-targeting.push-check'));
    let pkOpenSeq = 0;
    function pkWaitCheck(token, mine) {
        const startedAt = Date.now();
        return new Promise(function (resolve) {
            const tick = function () {
                if (mine !== pkOpenSeq) { resolve(null); return; }
                const secs = Math.round((Date.now() - startedAt) / 1000);
                pkSummary.textContent = 'Checking what is missing on Amazon… ' + secs + 's (large selections take about a minute)';
                if (secs > 600) {
                    resolve({ ok: false, body: { message: 'The check took too long. Close this window and try again.' } });
                    return;
                }
                fetch(pushCheckUrl + '?token=' + encodeURIComponent(token), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function (res) { return res.json(); })
                    .then(function (b) {
                        if (b.status === 'done') { resolve({ ok: true, body: b }); return; }
                        if (b.status === 'error') { resolve({ ok: false, body: b }); return; }
                        setTimeout(tick, 2000);
                    })
                    .catch(function () { setTimeout(tick, 3000); });
            };
            tick();
        });
    }

    function pkOpen() {
        const mine = ++pkOpenSeq;
        pkSummary.className = 'fw-semibold mb-2';
        pkSummary.textContent = 'Checking what is missing on Amazon…';
        pkWrap.innerHTML = '<span class="aft-empty">Loading…</span>';
        pkState.rows = [];
        pkState.upToDate = [];
        pkState.selected = new Set();
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
                return pkPost(true).then(function (r) {
                    return (r.ok && r.body && r.body.check) ? pkWaitCheck(r.body.token, mine) : r;
                });
            }).then(function (r) {
            if (mine !== pkOpenSeq) return; // the window was reopened meanwhile
            if (!r) return;
            const b = r.body || {};
            if (!r.ok) {
                pkSummary.className = 'fw-semibold mb-2 text-danger';
                pkSummary.textContent = b.message || 'Could not check Amazon.';
                pkWrap.innerHTML = '';
                return;
            }
            const cousinRows = (b.rows || []).filter(pkIsCousinRow).length;
            pkSummary.textContent = (pkBulk ? 'Bulk push of ' + pkBulk.length.toLocaleString() + ' selected campaign(s)' +
                (cousinRows ? ' (' + cousinRows.toLocaleString() + ' linked KW cousin campaign(s) are listed but NOT ticked - "Include linked cousins" adds them)' : '') + '. ' + (function () { const n = (b.up_to_date || []).filter(function (u) { return !u.note; }).length, w = (b.up_to_date || []).length - n; return (n ? n.toLocaleString() + ' campaign(s) already have every keyword they should (own + the other ticked campaigns) on Amazon (shown greyed). ' : '') + (w ? w.toLocaleString() + ' parent(s) have no HL campaign linked. ' : ''); })() + 'Only the ticked rows are pushed.' : (b.message || ''));
            const bulk = !!(pkBulk && pkBulk.length);
            const showCousinTools = bulk && cousinRows > 0;
            const hasHl = (b.rows || []).some(function (r) { return r.type === 'HL'; });
            if (pkEl.selMine) pkEl.selMine.classList.toggle('d-none', !bulk);
            if (pkEl.selCousins) pkEl.selCousins.classList.toggle('d-none', !showCousinTools);
            if (pkEl.selHl) pkEl.selHl.classList.toggle('d-none', !(bulk && hasHl));
            pkRender(b, false); // enables / labels the button from the ticked campaigns
            if (bulk && (b.rows || []).length) pkOnlyMine(); // default: ONLY what was ticked in the grid; linked cousins and HL stay unticked until asked for
            const t = b.totals || {};
            if ((Number(t.kw_to_add || 0) + Number(t.hl_to_add || 0)) === 0) pkConfirm.disabled = true;
        }).catch(function (e) {
            pkSummary.className = 'fw-semibold mb-2 text-danger';
            pkSummary.textContent = 'Failed: ' + e.message;
        });
    }

    // With campaigns ticked in the grid, this is the same check + push as "Pull + Push selected" (only those campaigns);
    // with nothing ticked it is the push of every campaign.
    if (pkBtn) pkBtn.addEventListener('click', function () {
        const sel = pickedIdsFn();
        pkBulk = sel.length ? sel : null;
        pkOpen();
    });

    if (pkConfirm) {
        pkConfirm.addEventListener('click', function () {
            const selected = Array.from(pkState.selected);
            if (!selected.length) return;
            if (!confirm('This pulls the live keywords of ' + selected.length.toLocaleString() + ' selected campaign(s) from Amazon and then adds the missing keywords to them. It runs in the background, chunk by chunk. Continue?')) return;
            // Pull + push in chunks with live progress (same job as the Sync buttons). Needs at least one KW campaign tick.
            const kwIds = selected.filter(function (k) { return k.indexOf('KW|') === 0; }).map(function (k) { return k.slice(3); });
            if (kwIds.length) {
                if (window.bootstrap && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(pkModalEl).hide();
                } else if (window.jQuery) {
                    window.jQuery(pkModalEl).modal('hide');
                }
                syncStartRaw({ campaign_ids: kwIds, selected: selected });
                return;
            }
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
                        pkPollTimer = setTimeout(tick, 2000);
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
                    pkPollTimer = setTimeout(tick, 2000);
                })
                .catch(function () { pkPollTimer = setTimeout(tick, 6000); }); // transient network error: keep polling
        };
        tick();
    }

    // "Push to Amazon" inside the Targets modal: push only this campaign's missing keywords.
    (function wirePushCampaign() {
        const btn = document.getElementById('aft-push-campaign');
        if (!btn) return;
        const label = '<i class="fas fa-upload"></i> Push to Amazon';
        const finish = function (text, ok) {
            btn.disabled = false;
            btn.innerHTML = label;
            showModalMsg(text, ok);
        };
        btn.addEventListener('click', function () {
            if (!tgt.campaignId) return;
            if (!confirm('Add the keywords of "' + tgt.name + '" that are missing on Amazon, through the Amazon Ads API?')) return;
            const cid = tgt.campaignId;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Pushing…';
            showModalMsg('Pushing to Amazon…', true);
            pkPost(false, ['KW|' + cid]).then(function (r) {
                if (!r.ok) { finish((r.body && r.body.message) || 'Push failed.', false); return; }
                const tick = function () {
                    fetch(pushStatusUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                        .then(function (res) { return res.json(); })
                        .then(function (b) {
                            if (b.status === 'running') {
                                showModalMsg('Pushing to Amazon… ' + (b.message || ''), true);
                                setTimeout(tick, 1500);
                                return;
                            }
                            const mine = (b.rows || []).filter(function (x) { return x.type === 'KW' && String(x.campaign_id) === cid; });
                            if (b.status === 'error') { finish(b.message || 'Push failed.', false); return; }
                            if (!mine.length) { finish('Nothing to push: every keyword of this campaign is already on Amazon.', true); return; }
                            const added = mine.reduce(function (s, x) { return s + Number(x.added || 0); }, 0);
                            const failed = mine.reduce(function (s, x) { return s + Number(x.failed || 0); }, 0);
                            const note = (mine.find(function (x) { return x.message; }) || {}).message || '';
                            finish('Pushed to Amazon: ' + added.toLocaleString() + ' keyword(s) added' + (failed ? ', ' + failed.toLocaleString() + ' failed' + (note ? ' (' + note + ')' : '') : '') + '.', !failed);
                        })
                        .catch(function () { setTimeout(tick, 5000); });
                };
                tick();
            }).catch(function (e) { finish('Push failed: ' + e.message, false); });
        });
    })();

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

    // ---------- Bulk push: tick campaigns, then push only those ----------
    (function wireBulkPush() {
        const selBtn = document.getElementById('aft-push-sel');
        const clearBtn = document.getElementById('aft-sel-clear');
        if (!selBtn) return;
        const picked = new Map(); // grid row id -> KW campaign id (survives paging / filtering)
        const ids = function () {
            const set = new Set();
            picked.forEach(function (cid) { if (cid) set.add(String(cid)); });
            return Array.from(set);
        };
        pickedIdsFn = ids;
        const paint = function () {
            const n = ids().length;
            pullSyncButtons();
            if (pkBtn) {
                pkBtn.innerHTML = '<i class="fas fa-upload"></i> Push KW to KW + HL Campaigns' + (n ? ' (' + n.toLocaleString() + ' selected)' : '');
                pkBtn.title = n
                    ? 'Check and push only the ' + n.toLocaleString() + ' ticked campaign(s) to Amazon'
                    : 'Send the keywords in the app to ALL KW campaigns and the HL campaigns on Amazon';
            }
            selBtn.innerHTML = '<i class="fas fa-check-square"></i> Push / Pull selected (' + n.toLocaleString() + ')';
            selBtn.disabled = n === 0;
            clearBtn.classList.toggle('d-none', n === 0);
            if (syncEl.sel) {
                syncEl.sel.dataset.n = n;
                syncEl.sel.disabled = n === 0 || syncRunning;
                syncEl.sel.innerHTML = '<i class="fas fa-sync-alt"></i> Sync selected (' + n.toLocaleString() + ')';
            }
        };
        if (syncEl.sel) syncEl.sel.addEventListener('click', function () { const list = ids(); if (list.length) syncStart(list); });
        table.on('rowSelected', function (row) {
            const d = row.getData() || {};
            picked.set(String(d.id), String(d.campaign_id || ''));
            paint();
        });
        table.on('rowDeselected', function (row) {
            const d = row.getData() || {};
            picked.delete(String(d.id));
            paint();
        });
        clearBtn.addEventListener('click', function () {
            picked.clear();
            table.deselectRow();
            paint();
        });
        selBtn.addEventListener('click', function () {
            const list = ids();
            if (!list.length) return;
            pkBulk = list;
            pkOpen();
        });
    })();

    if (statusEl) {
        statusEl.addEventListener('change', function () { table.setPage(1); });
    }
    if (scopeEl) {
        scopeEl.addEventListener('change', function () { table.setPage(1); });
    }
    if (typeGroupEl) {
        typeGroupEl.addEventListener('click', function (e) {
            const btn = e.target.closest('button[data-type]');
            if (!btn) return;
            const t = btn.getAttribute('data-type') || '';
            // "All" clears the selection; KW / PT / HL toggle and can be combined.
            if (t === '') {
                typeSel.clear();
            } else if (typeSel.has(t)) {
                typeSel.delete(t);
            } else {
                typeSel.add(t);
            }
            typeFilter = Array.from(typeSel).join(',');
            typeGroupEl.querySelectorAll('button[data-type]').forEach(function (b) {
                const bt = b.getAttribute('data-type') || '';
                const on = bt === '' ? typeSel.size === 0 : typeSel.has(bt);
                b.classList.toggle('active', on);
                b.classList.toggle('btn-primary', on);
                b.classList.toggle('btn-outline-primary', !on);
            });
            table.setPage(1);
        });
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
