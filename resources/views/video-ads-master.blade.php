@extends('layouts.vertical', ['title' => 'Video Request & Check', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

    {{-- Tabulator look-and-feel matched to resources/views/usage-images-master.blade.php
         (vertical column headers, no sort triangles, common spacing rules). --}}
    <style>
        .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }

        .tabulator .tabulator-header .tabulator-col .tabulator-col-content {
            text-align: center;
        }

        .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            white-space: nowrap;
            font-size: 12px;
            font-weight: 600;
            color: #1f2937;
            letter-spacing: 0.3px;
            text-align: center;
            width: 100%;
        }

        .tabulator .tabulator-header .tabulator-col.tabulator-sortable .tabulator-col-title {
            padding-right: 0px !important;
        }

        .tabulator-paginator label {
            margin-right: 5px;
        }

        .parent-row {
            background-color: #fffacd !important;
        }

        .copy-sku-btn {
            cursor: pointer;
            padding: 2px 5px;
            margin-left: 5px;
        }

        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }

        /* Video Ads Master accents (kept from the previous version). */
        .vam-link-icon {
            color: #2c6ed5;
            font-size: 16px;
            text-decoration: none;
        }
        .vam-link-icon:hover { color: #0a3d8f; }
        .vam-dash { color: #adb5bd; }

        .vam-target-pill {
            display: inline-block;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            padding: 2px 6px;
            border-radius: 4px;
            margin-right: 6px;
            color: #fff;
        }
        .vam-target-pill.sku    { background: #2c6ed5; }
        .vam-target-pill.parent { background: #16a34a; }
        .vam-target-pill.group  { background: #ea580c; }

        /* Count badges on the toolbar — same shape & size for every kind so
           the strip reads as a uniform set. Width is fixed (min-width) so the
           badges don't change size as numbers grow into the hundreds. */
        .vam-count-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            min-width: 100px;
            height: 32px;
            padding: 0 14px;
            border-radius: 4px;
            color: #fff;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.2px;
            white-space: nowrap;
            line-height: 1;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
        }
        .vam-count-badge span {
            font-weight: 800;
            font-size: 13px;
        }
        .vam-count-badge--sku    { background: #2c6ed5; }
        .vam-count-badge--parent { background: #16a34a; }
        .vam-count-badge--group  { background: #ea580c; }
        .vam-count-badge--total  { background: #6b7280; }
        .vam-count-badge--links  { background: #0ea5e9; }
        .vam-count-badge--missing { background: #dc2626; }

        /* Icon-only button — a fixed 32×32 square showing only the icon.
           Bootstrap's default padding is overridden so the button stays
           compact regardless of its label content. Tooltip (title attribute)
           is the only label cue. */
        .vam-icon-btn {
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            width: 32px;
            min-width: 32px;
            height: 32px;
            padding: 0 !important;
        }

        /* The list-editor popup (Tabulator's built-in autocomplete dropdown)
           needs to clear modal/sticky-header layers. */
        .tabulator-edit-list { z-index: 10500 !important; }
        .tabulator-edit-list .tabulator-edit-list-item.active,
        .tabulator-edit-list .tabulator-edit-list-item:hover { background: #eef4ff !important; }
        /* Multi-select list editor — selected tags stay highlighted so you can
           click several before confirming (Enter / click outside). */
        .tabulator-edit-list .tabulator-edit-list-item.tabulator-selected {
            background: #2c6ed5 !important;
            color: #fff !important;
        }

        /* Subtle hint that data cells are click-to-edit. The action column
           and the # column are excluded so their buttons / numbers don't
           look "editable". */
        #video-ads-master-table .tabulator-cell { cursor: text; }
        #video-ads-master-table .tabulator-cell[tabulator-field="id"],
        #video-ads-master-table .tabulator-cell[tabulator-field="_missing"],
        #video-ads-master-table .tabulator-cell[tabulator-field="_check"],
        #video-ads-master-table .tabulator-cell[tabulator-field="_adcheck"],
        #video-ads-master-table .tabulator-cell[tabulator-field="_actions"],
        #video-ads-master-table .tabulator-cell[tabulator-field="audience"],
        #video-ads-master-table .tabulator-cell[tabulator-field="hook_name"] { cursor: default; }
        #video-ads-master-table .tabulator-cell.tabulator-editing { background: #fff8d6 !important; }

        /* CHECK column — checkbox + who/when meta + history button. */
        .vam-check-wrap {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 2px;
            line-height: 1.15;
        }
        .vam-check-top {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .vam-check-box {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        .vam-check-history {
            color: #6b7280;
            font-size: 13px;
            cursor: pointer;
            text-decoration: none;
        }
        .vam-check-history:hover { color: #2c6ed5; }
        .vam-check-meta {
            font-size: 10px;
            color: #6b7280;
            text-align: center;
            white-space: nowrap;
        }
        .vam-check-meta .vam-check-user { font-weight: 700; color: #16a34a; }
        .vam-check-meta .vam-ad-user { font-weight: 700; color: #2c6ed5; }
        .vam-ad-box {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        /* MISSING column — red dot shown when a row has no valid link. */
        .vam-missing-dot {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #dc2626;
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.18);
        }

        /* AUDIENCE / HOOK multi-select tags (table cells + Select2). */
        .vam-tag {
            display: inline-block;
            font-size: 10px;
            font-weight: 600;
            padding: 2px 7px;
            margin: 1px 3px 1px 0;
            border-radius: 10px;
            white-space: nowrap;
            max-width: 220px;
            overflow: hidden;
            text-overflow: ellipsis;
            vertical-align: middle;
        }
        .vam-tag--audience { background: #e0f2fe; color: #0369a1; }
        .vam-tag--hook     { background: #f3e8ff; color: #7e22ce; }
        .vam-tag-cell {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 4px;
            min-height: 22px;
        }
        .vam-tag-add-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 22px;
            height: 22px;
            padding: 0;
            border: 1px dashed #9ca3af;
            border-radius: 50%;
            background: #fff;
            color: #4b5563;
            font-size: 12px;
            line-height: 1;
            cursor: pointer;
            flex-shrink: 0;
        }
        .vam-tag-add-btn:hover {
            border-color: #2c6ed5;
            color: #2c6ed5;
            background: #eff6ff;
        }
        .vam-pick-option {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 8px 10px;
            border-bottom: 1px solid #eef2f7;
            cursor: pointer;
        }
        .vam-pick-option:last-child { border-bottom: 0; }
        .vam-pick-option:hover { background: #f8fafc; }
        .vam-pick-option.is-checked { background: #eff6ff; }
        .vam-pick-option input { margin-top: 3px; }
        .vam-pick-option-label {
            flex: 1;
            min-width: 0;
            font-size: 13px;
            font-weight: 600;
            color: #111827;
        }
        .vam-pick-option-meta {
            font-size: 11px;
            font-weight: 400;
            color: #6b7280;
            margin-top: 2px;
        }
        .vam-manage-row {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 0;
            border-bottom: 1px solid #eef2f7;
        }
        .vam-manage-row:last-child { border-bottom: 0; }
        .vam-manage-name {
            flex: 1;
            min-width: 0;
            font-weight: 600;
            font-size: 13px;
        }
        .vam-manage-meta {
            font-size: 11px;
            color: #6b7280;
            max-width: 180px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice {
            background: #e0f2fe;
            border-color: #bae6fd;
            color: #0369a1;
            font-size: 12px;
        }
        /* Cell editor Select2 must sit above Tabulator layers. */
        .select2-container--open { z-index: 10600 !important; }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Video Request & Check',
        'sub_title'  => 'Manage video ads with SKU / Parent / Group targets',
    ])

    <div class="toast-container"></div>

    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                {{-- Control Bar — mirrors usage-images-master layout --}}
                <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
                    <button type="button" class="btn btn-sm btn-primary" id="vamAddRowBtn">
                        <i class="fa fa-plus"></i> Add Row
                    </button>

                    <button type="button" class="btn btn-sm btn-success" id="vamImportBtn" title="Upload a CSV of video ads (same headers as the sample template)">
                        <i class="fa fa-upload"></i> Import CSV
                    </button>
                    <a href="{{ route('video.ads.master.export') }}" class="btn btn-sm btn-info text-white" id="vamExportBtn" title="Download all rows as a CSV (same headers as Import)">
                        <i class="fa fa-file-export"></i> Export CSV
                    </a>
                    <a href="{{ route('video.ads.master.sample.csv') }}" class="btn btn-sm btn-outline-secondary vam-icon-btn" id="vamSampleBtn" title="Download a CSV template with 3 example rows">
                        <i class="fa fa-download"></i>
                    </a>
                    <input type="file" id="vamImportFile" accept=".csv,.txt,text/csv,text/plain,application/csv,application/vnd.ms-excel" style="display: none;">

                    {{-- Count badges (SKU / Parent / Group / Total). They all
                         share .vam-count-badge so width and typography stay
                         identical; only the colour modifier differs. --}}
                    <span class="vam-count-badge vam-count-badge--sku"    title="Rows targeting a SKU">
                        SKU: <span id="vamSkuCount">0</span>
                    </span>
                    <span class="vam-count-badge vam-count-badge--parent" title="Rows targeting a Parent">
                        Parent: <span id="vamParentCount">0</span>
                    </span>
                    <span class="vam-count-badge vam-count-badge--group"  title="Rows targeting a Group">
                        Group: <span id="vamGroupCount">0</span>
                    </span>
                    <span class="vam-count-badge vam-count-badge--total"  title="Total required rows">
                        Required: <span id="vamRowCount">0</span>
                    </span>
                    <span class="vam-count-badge vam-count-badge--links"  title="Rows that have a link available">
                        Available: <span id="vamLinkCount">0</span>
                    </span>
                    <span class="vam-count-badge vam-count-badge--missing" title="Rows still missing a link (Required − Available)">
                        Missing: <span id="vamMissingCount">0</span>
                    </span>
                </div>
            </div>

            <div class="card-body" style="padding: 0;">
                <div id="vam-table-wrapper" style="height: calc(100vh - 240px); display: flex; flex-direction: column;">
                    {{-- Sticky search bar --}}
                    <div class="p-2 bg-light border-bottom">
                        <input type="text" id="vamSearch" class="form-control form-control-sm" placeholder="Search across all columns…">
                    </div>

                    <div id="video-ads-master-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal: create / edit a video ad row --}}
    <div class="modal fade" id="vamRowModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #2c6ed5 0%, #1a56b7 100%); color: #fff;">
                    <h5 class="modal-title" id="vamRowModalTitle">
                        <i class="fas fa-video me-2"></i>Add Video Ad
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="vamRowForm" autocomplete="off">
                        <input type="hidden" id="vam_id">

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">SKU / Parent / Group <span class="text-danger">*</span></label>
                                <select id="vam_target_type" class="form-select" required>
                                    <option value="">— Select —</option>
                                    <option value="sku">SKU</option>
                                    <option value="parent">Parent</option>
                                    <option value="group">Group</option>
                                </select>
                            </div>

                            <div class="col-md-8">
                                <label class="form-label fw-semibold">Name</label>
                                <input type="text" id="vam_name" class="form-control" placeholder="Ad name / label">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Channel</label>
                                <input type="text" id="vam_channel" class="form-control" list="vam-channels-list" placeholder="Pick or type a channel">
                                <datalist id="vam-channels-list"></datalist>
                            </div>

                            <div class="col-md-8">
                                <label class="form-label fw-semibold">Audience</label>
                                <div class="d-flex gap-2 align-items-start">
                                    <div class="flex-grow-1">
                                        <select id="vam_audience" class="form-select" multiple></select>
                                    </div>
                                    <button type="button" class="btn btn-outline-secondary" id="vamAudienceManageBtn" title="Manage audience tags (add / edit / delete)">
                                        <i class="fas fa-cog"></i>
                                    </button>
                                </div>
                                <div class="form-text">Pick one or more tags. Type to add a new audience.</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Hook</label>
                                <div class="d-flex gap-2 align-items-start">
                                    <div class="flex-grow-1">
                                        <select id="vam_hook_name" class="form-select" multiple></select>
                                    </div>
                                    <button type="button" class="btn btn-outline-secondary" id="vamHookManageBtn" title="Manage hook tags (add / edit / delete)">
                                        <i class="fas fa-cog"></i>
                                    </button>
                                </div>
                                <div class="form-text">Pick one or more tags. Type to add a new hook.</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Hook Message</label>
                                <input type="text" id="vam_hook" class="form-control" placeholder="Hook copy / message">
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">Link</label>
                                <input type="url" id="vam_link" class="form-control" placeholder="https://…">
                                <div class="form-text">A link icon will be shown in the table when a URL is set.</div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="vamRowSaveBtn">
                        <i class="fas fa-save me-1"></i>Save
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal: add/edit a single HOOK option (name + default message/link). --}}
    <div class="modal fade" id="vamAddHookModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="vamAddHookModalTitle">Add Hook</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="vamEditingHookId" value="">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Hook name <span class="text-danger">*</span></label>
                            <input type="text" id="vamNewHookName" class="form-control" placeholder="e.g. Curiosity Hook" autocomplete="off">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Default Hook Message</label>
                            <input type="text" id="vamNewHookMessage" class="form-control" placeholder="e.g. Tired of cables that crackle?" autocomplete="off">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Default Link</label>
                            <input type="url" id="vamNewHookLink" class="form-control" placeholder="https://…" autocomplete="off">
                            <div class="form-text">When a single hook tag is picked on a row, Hook Message and Link can auto-fill from these defaults.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="vamSaveNewHookBtn">Save</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal: pick one or more AUDIENCE / HOOK tags for a single row. --}}
    <div class="modal fade" id="vamPickTagsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="vamPickTagsTitle">Select options</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="text" id="vamPickTagsSearch" class="form-control form-control-sm mb-3" placeholder="Search options…">
                    <div id="vamPickTagsList" class="border rounded" style="max-height: 360px; overflow: auto;">
                        <div class="text-muted text-center py-3">Loading…</div>
                    </div>
                    <div class="input-group mt-3">
                        <input type="text" id="vamPickTagsNew" class="form-control form-control-sm" placeholder="Add a new option…" autocomplete="off">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="vamPickTagsAddNewBtn">
                            <i class="fas fa-plus me-1"></i>Add
                        </button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="vamPickTagsApplyBtn">
                        <i class="fas fa-check me-1"></i>Apply
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal: manage HOOK tag options (add / edit / delete). --}}
    <div class="modal fade" id="vamHookManageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-anchor me-2"></i>Manage Hooks</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="text-muted small">Hooks used on rows are listed here. Edit to set default message/link.</div>
                        <button type="button" class="btn btn-sm btn-primary" id="vamHookManageAddBtn">
                            <i class="fas fa-plus me-1"></i>Add Hook
                        </button>
                    </div>
                    <div id="vamHookManageList" class="border rounded px-3">
                        <div class="text-muted text-center py-3">Loading…</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal: manage AUDIENCE tag options (add / edit / delete). Options
         are shared via video_ad_audience_options and also seeded from any
         audiences already used on rows. --}}
    <div class="modal fade" id="vamAudienceManageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-users me-2"></i>Manage Audiences</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="input-group mb-3">
                        <input type="text" id="vamAudienceNewName" class="form-control" placeholder="New audience name…" autocomplete="off">
                        <button type="button" class="btn btn-primary" id="vamAudienceAddBtn">
                            <i class="fas fa-plus me-1"></i>Add
                        </button>
                    </div>
                    <div id="vamAudienceManageList" class="border rounded px-3">
                        <div class="text-muted text-center py-3">Loading…</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal: check/uncheck audit trail for a single row. Populated on
         demand from GET /video-ads-master/{id}/check-history. --}}
    <div class="modal fade" id="vamCheckHistoryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-clock-rotate-left me-2"></i>Check History</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="vamCheckHistoryBody">
                        <div class="text-muted text-center py-3">Loading…</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        (function () {
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            // ── State ──────────────────────────────────────────────────────────
            let table              = null;
            let channelOptions     = [];
            let hookOptions        = [];     // [{id, name, hook, link}, …]
            let audienceOptions    = [];     // [{id, name}, …] from video_ad_audience_options
            let rowModal           = null;   // bootstrap.Modal — Add / Edit form
            let addHookModal       = null;   // bootstrap.Modal — add / edit one hook
            let hookManageModal    = null;   // bootstrap.Modal — manage hook tags
            let audienceManageModal = null;  // bootstrap.Modal — manage audience tags
            let pickTagsModal      = null;   // bootstrap.Modal — pick tags for a row cell
            let checkHistoryModal  = null;   // bootstrap.Modal — per-row check audit trail
            let editingId          = null;   // id of the row currently in the form (null = add mode)
            let editingHookId      = null;   // id of hook option being edited in Add Hook modal
            let pickTagsContext    = null;   // { row, field: 'audience'|'hook_name' }

            // ── Helpers ────────────────────────────────────────────────────────
            function escapeHtml(s) {
                if (s === null || s === undefined) return '';
                return String(s)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;');
            }

            function showToast(message, type = 'info') {
                const colors = { success: 'bg-success', error: 'bg-danger', info: 'bg-primary', warning: 'bg-warning' };
                const html = `
                    <div class="toast align-items-center text-white ${colors[type] || colors.info} border-0" role="alert">
                        <div class="d-flex">
                            <div class="toast-body">${escapeHtml(message)}</div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                        </div>
                    </div>`;
                const container = document.querySelector('.toast-container');
                const wrapper = document.createElement('div');
                wrapper.innerHTML = html;
                const el = wrapper.firstElementChild;
                container.appendChild(el);
                const toast = new bootstrap.Toast(el, { delay: 2500 });
                toast.show();
                el.addEventListener('hidden.bs.toast', () => el.remove());
            }

            function isLikelyUrl(value) {
                if (!value) return false;
                const v = String(value).trim();
                return /^(https?:)?\/\//i.test(v) || /^www\./i.test(v);
            }
            function normalizeUrl(value) {
                const v = String(value || '').trim();
                if (!v) return '';
                if (/^https?:\/\//i.test(v)) return v;
                if (/^\/\//.test(v))         return 'https:' + v;
                if (/^www\./i.test(v))       return 'https://' + v;
                return v;
            }

            // ── Formatters ─────────────────────────────────────────────────────

            const TARGET_LABELS = { sku: 'SKU', parent: 'Parent', group: 'Group' };

            function targetFormatter(cell) {
                const type = String(cell.getValue() || '').toLowerCase();
                if (!type) return '<span class="vam-dash">—</span>';
                if (!TARGET_LABELS[type]) return escapeHtml(type);
                return `<span class="vam-target-pill ${type}">${TARGET_LABELS[type]}</span>`;
            }

            function plainFormatter(cell) {
                const v = cell.getValue();
                if (v === null || v === undefined || v === '') return '<span class="vam-dash">—</span>';
                return escapeHtml(v);
            }

            // Strip a trailing " Hook" from labels so choices read as
            // "Content Creator" instead of "Content Creator Hook".
            function displayHookName(name) {
                const s = String(name || '').trim();
                const stripped = s.replace(/\s+hook$/i, '').trim();
                return stripped || s;
            }

            function normalizeHookName(name) {
                return displayHookName(name);
            }

            // AUDIENCE / HOOK multi-tags are stored pipe-delimited ("A | B")
            // so names can contain commas. Existing single-value cells stay
            // as one tag.
            function parseTags(value) {
                if (value === null || value === undefined) return [];
                if (Array.isArray(value)) {
                    return value.map(v => String(v).trim()).filter(Boolean);
                }
                const s = String(value).trim();
                if (!s) return [];
                if (s.charAt(0) === '[') {
                    try {
                        const arr = JSON.parse(s);
                        if (Array.isArray(arr)) {
                            return arr.map(v => String(v).trim()).filter(Boolean);
                        }
                    } catch (_) { /* fall through */ }
                }
                if (s.indexOf('|') !== -1) {
                    return s.split('|').map(v => v.trim()).filter(Boolean);
                }
                return [s];
            }

            function formatTags(tags) {
                const list = (tags || []).map(v => String(v).trim()).filter(Boolean);
                // De-dupe while preserving order.
                const seen = new Set();
                const unique = [];
                list.forEach(t => {
                    if (seen.has(t)) return;
                    seen.add(t);
                    unique.push(t);
                });
                return unique.length ? unique.join(' | ') : '';
            }

            function tagFormatter(modifier) {
                return function (cell) {
                    const tags = parseTags(cell.getValue());
                    const pills = tags.length
                        ? tags.map(t => {
                            const label = modifier === 'hook' ? displayHookName(t) : t;
                            return `<span class="vam-tag vam-tag--${modifier}" title="${escapeHtml(label)}">${escapeHtml(label)}</span>`;
                        }).join('')
                        : '<span class="vam-dash">—</span>';
                    return `<div class="vam-tag-cell">${pills}<button type="button" class="vam-tag-add-btn" title="Choose options" data-tag-field="${modifier === 'hook' ? 'hook_name' : 'audience'}"><i class="fas fa-plus"></i></button></div>`;
                };
            }
            const audienceFormatter = tagFormatter('audience');
            const hookFormatter     = tagFormatter('hook');

            // Open the pick-tags modal for a row's AUDIENCE or HOOK cell.
            function openPickTagsModal(row, field) {
                pickTagsContext = { row, field };
                const isHook = field === 'hook_name';
                document.getElementById('vamPickTagsTitle').textContent = isHook ? 'Select Hooks' : 'Select Audiences';
                document.getElementById('vamPickTagsSearch').value = '';
                document.getElementById('vamPickTagsNew').value = '';
                document.getElementById('vamPickTagsNew').placeholder = isHook ? 'Add a new hook…' : 'Add a new audience…';
                renderPickTagsList();
                pickTagsModal.show();
            }

            // One entry per hook *type* ("Content Creator Hook" and
            // "Content Creator" collapse to a single "Content Creator").
            function uniqueHookOptions() {
                const byKey = new Map();
                (hookOptions || []).forEach(o => {
                    const clean = displayHookName(o.name);
                    const key = clean.toLowerCase();
                    if (!key) return;
                    const prev = byKey.get(key);
                    if (!prev) {
                        byKey.set(key, { ...o, name: clean });
                        return;
                    }
                    byKey.set(key, {
                        ...prev,
                        name: clean,
                        hook: prev.hook || o.hook || '',
                        link: prev.link || o.link || '',
                    });
                });
                return Array.from(byKey.values()).sort((a, b) =>
                    a.name.localeCompare(b.name, undefined, { sensitivity: 'base' })
                );
            }

            function getPickTagOptions() {
                if (!pickTagsContext) return [];
                if (pickTagsContext.field === 'hook_name') {
                    return uniqueHookOptions().map(o => ({ name: o.name }));
                }
                // Audiences: unique by case-insensitive name.
                const byKey = new Map();
                (audienceOptions || []).forEach(o => {
                    const key = String(o.name || '').trim().toLowerCase();
                    if (!key || byKey.has(key)) return;
                    byKey.set(key, { name: o.name });
                });
                return Array.from(byKey.values()).sort((a, b) =>
                    a.name.localeCompare(b.name, undefined, { sensitivity: 'base' })
                );
            }

            function renderPickTagsList() {
                const wrap = document.getElementById('vamPickTagsList');
                if (!wrap || !pickTagsContext) return;

                const isHook = pickTagsContext.field === 'hook_name';
                const selectedRaw = parseTags(pickTagsContext.row.getData()[pickTagsContext.field]);
                const selected = new Set(
                    selectedRaw.map(n => (isHook ? displayHookName(n) : n).toLowerCase())
                );
                const q = (document.getElementById('vamPickTagsSearch').value || '').trim().toLowerCase();
                let options = getPickTagOptions();

                // Include any currently-selected tags that aren't in the master list yet.
                selectedRaw.forEach(name => {
                    const clean = isHook ? displayHookName(name) : name;
                    if (!options.some(o => o.name.toLowerCase() === clean.toLowerCase())) {
                        options = options.concat([{ name: clean }]);
                    }
                });

                if (q) {
                    options = options.filter(o => o.name.toLowerCase().includes(q));
                }

                if (!options.length) {
                    wrap.innerHTML = '<div class="text-muted text-center py-3">No options found.</div>';
                    return;
                }

                wrap.innerHTML = options.map(o => {
                    const checked = selected.has(o.name.toLowerCase()) ? 'checked' : '';
                    const cls = selected.has(o.name.toLowerCase()) ? 'is-checked' : '';
                    return `
                        <label class="vam-pick-option ${cls}">
                            <input type="checkbox" value="${escapeHtml(o.name)}" ${checked}>
                            <span class="vam-pick-option-label">${escapeHtml(o.name)}</span>
                        </label>`;
                }).join('');
            }

            function applyPickTagsSelection() {
                if (!pickTagsContext) return;
                const wrap = document.getElementById('vamPickTagsList');
                const checked = Array.from(wrap.querySelectorAll('input[type="checkbox"]:checked'))
                    .map(el => el.value.trim())
                    .filter(Boolean);
                const { row, field } = pickTagsContext;
                const tags = field === 'hook_name'
                    ? parseTags(checked.map(normalizeHookName))
                    : parseTags(checked);

                row.update({ [field]: tags });

                if (field === 'audience') {
                    tags.forEach(t => {
                        if (!audienceOptionNames().includes(t)) createAudienceOption(t);
                    });
                    const cell = row.getCell('audience');
                    if (cell) persistCell(cell);
                } else {
                    tags.forEach(t => {
                        if (!hookOptionNames().includes(t)) createHookOption(t);
                    });
                    if (tags.length === 1) {
                        const known = findHook(tags[0]) || findHook(checked[0]);
                        if (known) applyHookDefaultsToRow(row, known);
                    }
                    const cell = row.getCell('hook_name');
                    if (cell) persistCell(cell);
                }

                pickTagsModal.hide();
                pickTagsContext = null;
                showToast('Selection saved', 'success');
            }

            function addNewOptionInPickModal() {
                if (!pickTagsContext) return;
                const input = document.getElementById('vamPickTagsNew');
                const name = (input.value || '').trim();
                if (!name) { showToast('Enter a name', 'warning'); return; }

                const done = () => {
                    // Re-render with the new option pre-checked on the working set.
                    const current = parseTags(pickTagsContext.row.getData()[pickTagsContext.field]);
                    if (!current.includes(name)) {
                        pickTagsContext.row.update({
                            [pickTagsContext.field]: current.concat([name]),
                        });
                    }
                    input.value = '';
                    renderPickTagsList();
                    showToast('Option added', 'success');
                };

                if (pickTagsContext.field === 'hook_name') {
                    const normalized = normalizeHookName(name);
                    createHookOption(normalized).then(opt => {
                        if (!opt) return;
                        // Ensure the working selection uses the normalized name.
                        const current = parseTags(pickTagsContext.row.getData().hook_name)
                            .filter(t => t !== name);
                        if (!current.includes(normalized)) current.push(normalized);
                        pickTagsContext.row.update({ hook_name: current });
                        input.value = '';
                        renderPickTagsList();
                        showToast('Option added', 'success');
                    });
                } else {
                    createAudienceOption(name).then(opt => { if (opt) done(); });
                }
            }

            function linkFormatter(cell) {
                const v = cell.getValue();
                if (!v || !String(v).trim()) return '<span class="vam-dash">—</span>';
                const url = normalizeUrl(v);
                if (!isLikelyUrl(url)) return '<span class="vam-dash">—</span>';
                return `<a href="${escapeHtml(url)}" target="_blank" rel="noopener" class="vam-link-icon" title="Open link"><i class="fas fa-link"></i></a>`;
            }

            // MISSING column — shows a red dot when the row has no valid link,
            // making it easy to scan for rows that still need one. Rows that
            // already have a link show nothing.
            function missingFormatter(cell) {
                const row = cell.getRow().getData();
                const hasLink = isLikelyUrl(normalizeUrl(row.link));
                if (hasLink) return '';
                return '<span class="vam-missing-dot" title="Link missing"></span>';
            }

            // Human-friendly datetime. Server sends ISO 8601 (datetime cast);
            // fall back to the raw string if it can't be parsed.
            function formatDateTime(v) {
                if (!v) return '';
                const d = new Date(v);
                if (isNaN(d.getTime())) return String(v);
                return d.toLocaleString();
            }

            // CHECK column — a checkbox reflecting is_checked, the user + time
            // stamp of the last check, and a history button. Clicks are wired
            // in the column's cellClick handler (see initTable).
            function checkFormatter(cell) {
                const row     = cell.getRow().getData();
                const checked = !!row.is_checked;
                const by      = row.checked_by ? escapeHtml(row.checked_by) : '';
                const at      = row.checked_at ? escapeHtml(formatDateTime(row.checked_at)) : '';

                let meta = '';
                if (checked && (by || at)) {
                    meta = `<div class="vam-check-meta" title="${by}${at ? ' • ' + at : ''}">`
                         + (by ? `<span class="vam-check-user">${by}</span>` : '')
                         + (at ? `<br>${at}` : '')
                         + `</div>`;
                }

                return `
                    <div class="vam-check-wrap">
                        <div class="vam-check-top">
                            <input type="checkbox" class="vam-check-box form-check-input" ${checked ? 'checked' : ''} title="Mark row as checked / unchecked">
                            <a href="#" class="vam-check-history" title="View check history"><i class="fas fa-clock-rotate-left"></i></a>
                        </div>
                        ${meta}
                    </div>`;
            }

            // AD column — a checkbox reflecting ad_checked plus the user + time
            // stamp of when the row was marked as an ad. Clicks are wired in
            // the column's cellClick handler (see initTable).
            function adCheckFormatter(cell) {
                const row     = cell.getRow().getData();
                const checked = !!row.ad_checked;
                const by      = row.ad_checked_by ? escapeHtml(row.ad_checked_by) : '';
                const at      = row.ad_checked_at ? escapeHtml(formatDateTime(row.ad_checked_at)) : '';

                let meta = '';
                if (checked && (by || at)) {
                    meta = `<div class="vam-check-meta" title="${by}${at ? ' • ' + at : ''}">`
                         + (by ? `<span class="vam-ad-user">${by}</span>` : '')
                         + (at ? `<br>${at}` : '')
                         + `</div>`;
                }

                return `
                    <div class="vam-check-wrap">
                        <div class="vam-check-top">
                            <input type="checkbox" class="vam-ad-box form-check-input" ${checked ? 'checked' : ''} title="Mark row as an ad">
                        </div>
                        ${meta}
                    </div>`;
            }

            // ── Inline editors ─────────────────────────────────────────────────
            // All three pickers below use Tabulator's built-in `list` editor,
            // which renders its popup attached to <body> so it doesn't get
            // clipped by the small cell.

            const TARGET_TYPE_OPTIONS = [
                { value: 'sku',    label: 'SKU'    },
                { value: 'parent', label: 'Parent' },
                { value: 'group',  label: 'Group'  },
            ];
            function buildChannelLookup() { return (channelOptions || []).map(c => ({ value: c, label: c })); }

            function audienceOptionNames() {
                return (audienceOptions || []).map(o => o.name);
            }

            function setAudienceOptions(list) {
                audienceOptions = Array.isArray(list) ? list : [];
                // Keep the form Select2 in sync whenever the option list changes.
                const sel = document.getElementById('vam_audience');
                let selected = [];
                if (sel) {
                    selected = $(sel).hasClass('select2-hidden-accessible')
                        ? ($(sel).val() || [])
                        : Array.from(sel.selectedOptions).map(o => o.value);
                }
                rebuildAudienceSelect(selected);
                renderAudienceManageList();
            }

            // Rebuild the form's multi-select Select2 from audienceOptions.
            function rebuildAudienceSelect(selected) {
                const sel = document.getElementById('vam_audience');
                if (!sel) return;
                const chosen = Array.isArray(selected) ? selected : parseTags(selected);

                if ($(sel).hasClass('select2-hidden-accessible')) {
                    $(sel).off('select2:select.vamAudience');
                    $(sel).select2('destroy');
                }

                sel.innerHTML = '';
                const names = audienceOptionNames();
                // Include any currently-selected tags that aren't in the master
                // list yet so Select2 can still show them.
                chosen.forEach(name => {
                    if (!names.includes(name)) names.push(name);
                });
                names.forEach(name => {
                    const o = document.createElement('option');
                    o.value = name;
                    o.textContent = name;
                    if (chosen.includes(name)) o.selected = true;
                    sel.appendChild(o);
                });

                $(sel).select2({
                    theme: 'bootstrap-5',
                    placeholder: 'Select one or more audiences…',
                    allowClear: true,
                    tags: true,
                    closeOnSelect: false,
                    width: '100%',
                    dropdownParent: $('#vamRowModal'),
                    createTag: function (params) {
                        const term = $.trim(params.term);
                        if (!term) return null;
                        if (audienceOptionNames().some(n => n.toLowerCase() === term.toLowerCase())) {
                            return null;
                        }
                        return { id: term, text: '+ Add "' + term + '"', newTag: true };
                    },
                });

                // Persist brand-new tags into video_ad_audience_options.
                $(sel).on('select2:select.vamAudience', function (e) {
                    if (!e.params.data.newTag) return;
                    createAudienceOption(e.params.data.id);
                });
            }

            function getFormAudienceValue() {
                const sel = document.getElementById('vam_audience');
                if (!sel) return null;
                const tags = $(sel).hasClass('select2-hidden-accessible')
                    ? ($(sel).val() || [])
                    : Array.from(sel.selectedOptions).map(o => o.value);
                const joined = formatTags(tags);
                return joined === '' ? null : joined;
            }

            function createAudienceOption(name) {
                const trimmed = String(name || '').trim();
                if (!trimmed) return Promise.resolve(null);
                return fetch('/video-ads-master/audience-options', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ name: trimmed }),
                })
                .then(r => r.json().then(j => ({ ok: r.ok, j })))
                .then(({ ok, j }) => {
                    if (!ok || !j.success) {
                        showToast((j && j.message) || 'Failed to add audience', 'error');
                        return null;
                    }
                    if (j.options) setAudienceOptions(j.options);
                    else if (j.option && !audienceOptions.some(o => o.id === j.option.id)) {
                        setAudienceOptions(audienceOptions.concat([j.option]));
                    }
                    return j.option || null;
                })
                .catch(e => {
                    console.warn('Failed to register audience option:', e);
                    showToast('Network error adding audience', 'error');
                    return null;
                });
            }

            function renderAudienceManageList() {
                const wrap = document.getElementById('vamAudienceManageList');
                if (!wrap) return;
                if (!audienceOptions.length) {
                    wrap.innerHTML = '<div class="text-muted text-center py-3">No audiences yet. Add one above.</div>';
                    return;
                }
                wrap.innerHTML = audienceOptions.map(opt => `
                    <div class="vam-manage-row" data-id="${opt.id}">
                        <span class="vam-manage-name">${escapeHtml(opt.name)}</span>
                        <button type="button" class="btn btn-sm btn-outline-primary vam-aud-edit" title="Rename">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger vam-aud-del" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                `).join('');
            }

            function openAudienceManageModal() {
                renderAudienceManageList();
                document.getElementById('vamAudienceNewName').value = '';
                audienceManageModal.show();
            }

            function addAudienceFromManageModal() {
                const input = document.getElementById('vamAudienceNewName');
                const name = (input.value || '').trim();
                if (!name) { showToast('Enter an audience name', 'warning'); return; }
                createAudienceOption(name).then(opt => {
                    if (opt) {
                        input.value = '';
                        showToast('Audience added', 'success');
                    }
                });
            }

            function renameAudienceOption(id, currentName) {
                const next = window.prompt('Rename audience:', currentName);
                if (next === null) return;
                const name = String(next).trim();
                if (!name) { showToast('Name cannot be empty', 'warning'); return; }
                if (name === currentName) return;

                fetch(`/video-ads-master/audience-options/${id}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ name }),
                })
                .then(r => r.json().then(j => ({ ok: r.ok, j })))
                .then(({ ok, j }) => {
                    if (!ok || !j.success) {
                        showToast((j && j.message) || 'Failed to rename', 'error');
                        return;
                    }
                    if (j.options) setAudienceOptions(j.options);
                    // Refresh grid so renamed tags show on rows.
                    refreshTableAudienceCells(currentName, name);
                    showToast('Audience renamed', 'success');
                })
                .catch(e => { console.error(e); showToast('Network error renaming audience', 'error'); });
            }

            function deleteAudienceOption(id, name) {
                if (!confirm(`Delete audience "${name}"? It will be removed from all rows.`)) return;
                fetch(`/video-ads-master/audience-options/${id}`, {
                    method: 'DELETE',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                })
                .then(r => r.json().then(j => ({ ok: r.ok, j })))
                .then(({ ok, j }) => {
                    if (!ok || !j.success) {
                        showToast((j && j.message) || 'Failed to delete', 'error');
                        return;
                    }
                    if (j.options) setAudienceOptions(j.options);
                    refreshTableAudienceCells(name, null);
                    showToast('Audience deleted', 'success');
                })
                .catch(e => { console.error(e); showToast('Network error deleting audience', 'error'); });
            }

            // After rename/delete, rewrite the in-memory table cells so the
            // UI matches the server without a full reload.
            function refreshTableAudienceCells(oldName, newName) {
                if (!table) return;
                table.getRows().forEach(row => {
                    const data = row.getData();
                    const tags = parseTags(data.audience);
                    if (!tags.includes(oldName)) return;
                    const next = newName === null
                        ? tags.filter(t => t !== oldName)
                        : tags.map(t => t === oldName ? newName : t);
                    row.update({ audience: next });
                });
            }

            // Native <select> editor for SKU / PARENT / GROUP — gives a true
            // dropdown experience (no text input, no autocomplete). Tabulator's
            // built-in `list` editor renders an input box that opens a panel
            // on click, which the user found confusing.
            function targetTypeEditor(cell, onRendered, success, cancel) {
                const select = document.createElement('select');
                select.className = 'form-select form-select-sm';
                select.style.cssText = 'width:100%;height:100%;padding:0 4px;font-size:13px;border:none;background-color:#fff8d6;';

                const blank = document.createElement('option');
                blank.value = '';
                blank.textContent = '— Select —';
                select.appendChild(blank);

                TARGET_TYPE_OPTIONS.forEach(opt => {
                    const o = document.createElement('option');
                    o.value = opt.value;
                    o.textContent = opt.label;
                    select.appendChild(o);
                });

                select.value = cell.getValue() || '';

                onRendered(() => {
                    select.focus();
                    // Show the option list immediately on modern browsers.
                    if (typeof select.showPicker === 'function') {
                        try { select.showPicker(); } catch (_) { /* not supported in some browsers */ }
                    }
                });

                let committed = false;
                const commit = () => { if (committed) return; committed = true; success(select.value); };

                select.addEventListener('change', commit);
                select.addEventListener('blur',   commit);
                select.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape') cancel();
                    if (e.key === 'Enter')  commit();
                });

                return select;
            }

            // Lookup lists for Tabulator's multi-select list editor.
            function buildAudienceLookup() {
                return audienceOptionNames().map(n => ({ value: n, label: n }));
            }

            // HOOK — multi-tag Select2 backed by video_ads_hook_options.
            // Options carry optional default message/link for auto-fill when
            // exactly one tag is selected.
            function hookOptionNames() {
                return uniqueHookOptions().map(o => o.name);
            }

            function findHook(name) {
                if (!name) return null;
                const needle = displayHookName(name).toLowerCase();
                return uniqueHookOptions().find(o =>
                    o.name === name || displayHookName(o.name).toLowerCase() === needle
                ) || null;
            }

            function setHookOptions(list) {
                hookOptions = Array.isArray(list) ? list : [];
                const sel = document.getElementById('vam_hook_name');
                let selected = [];
                if (sel) {
                    selected = $(sel).hasClass('select2-hidden-accessible')
                        ? ($(sel).val() || [])
                        : Array.from(sel.selectedOptions).map(o => o.value);
                }
                rebuildHookSelect(selected);
                renderHookManageList();
            }

            function rebuildHookSelect(selected) {
                const sel = document.getElementById('vam_hook_name');
                if (!sel) return;
                const chosen = parseTags(selected).map(normalizeHookName);

                if ($(sel).hasClass('select2-hidden-accessible')) {
                    $(sel).off('select2:select.vamHook');
                    $(sel).select2('destroy');
                }

                sel.innerHTML = '';
                const names = hookOptionNames().slice();
                chosen.forEach(name => {
                    if (!names.some(n => n.toLowerCase() === name.toLowerCase())) names.push(name);
                });
                const chosenKeys = new Set(chosen.map(n => n.toLowerCase()));
                names.forEach(name => {
                    const o = document.createElement('option');
                    o.value = name;
                    o.textContent = name;
                    if (chosenKeys.has(name.toLowerCase())) o.selected = true;
                    sel.appendChild(o);
                });

                $(sel).select2({
                    theme: 'bootstrap-5',
                    placeholder: 'Select one or more hooks…',
                    allowClear: true,
                    tags: true,
                    closeOnSelect: false,
                    width: '100%',
                    dropdownParent: $('#vamRowModal'),
                    createTag: function (params) {
                        const term = $.trim(params.term);
                        if (!term) return null;
                        if (hookOptionNames().some(n => n.toLowerCase() === term.toLowerCase())) {
                            return null;
                        }
                        return { id: term, text: '+ Add "' + term + '"', newTag: true };
                    },
                });

                $(sel).on('select2:select.vamHook', function (e) {
                    if (e.params.data.newTag) createHookOption(e.params.data.id);
                    // Auto-fill message/link when exactly one hook is selected.
                    applyHookDefaultsToForm();
                });
                $(sel).on('select2:unselect.vamHook', applyHookDefaultsToForm);
            }

            function getFormHookValue() {
                const sel = document.getElementById('vam_hook_name');
                if (!sel) return null;
                const tags = $(sel).hasClass('select2-hidden-accessible')
                    ? ($(sel).val() || [])
                    : Array.from(sel.selectedOptions).map(o => o.value);
                const joined = formatTags(tags);
                return joined === '' ? null : joined;
            }

            function createHookOption(name, extras) {
                const trimmed = normalizeHookName(name);
                if (!trimmed) return Promise.resolve(null);
                const body = { name: trimmed };
                if (extras && Object.prototype.hasOwnProperty.call(extras, 'hook')) body.hook = extras.hook;
                if (extras && Object.prototype.hasOwnProperty.call(extras, 'link')) body.link = extras.link;

                return fetch('/video-ads-master/hook-options', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify(body),
                })
                .then(r => r.json().then(j => ({ ok: r.ok, j })))
                .then(({ ok, j }) => {
                    if (!ok || !j.success) {
                        showToast((j && j.message) || 'Failed to add hook', 'error');
                        return null;
                    }
                    if (j.options) setHookOptions(j.options);
                    return j.option || null;
                })
                .catch(e => {
                    console.warn('Failed to register hook option:', e);
                    showToast('Network error adding hook', 'error');
                    return null;
                });
            }

            function renderHookManageList() {
                const wrap = document.getElementById('vamHookManageList');
                if (!wrap) return;
                const unique = uniqueHookOptions();
                if (!unique.length) {
                    wrap.innerHTML = '<div class="text-muted text-center py-3">No hooks yet. Click Add Hook.</div>';
                    return;
                }
                wrap.innerHTML = unique.map(opt => `
                    <div class="vam-manage-row" data-id="${opt.id}">
                        <div class="flex-grow-1 min-w-0">
                            <div class="vam-manage-name">${escapeHtml(opt.name)}</div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary vam-hook-edit" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger vam-hook-del" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                `).join('');
            }

            function openHookManageModal() {
                renderHookManageList();
                hookManageModal.show();
            }

            function openHookEditor(opt) {
                editingHookId = opt && opt.id ? opt.id : null;
                document.getElementById('vamEditingHookId').value = editingHookId || '';
                document.getElementById('vamAddHookModalTitle').textContent = editingHookId ? 'Edit Hook' : 'Add Hook';
                document.getElementById('vamNewHookName').value    = opt ? displayHookName(opt.name || '') : '';
                document.getElementById('vamNewHookMessage').value = opt ? (opt.hook || '') : '';
                document.getElementById('vamNewHookLink').value    = opt ? (opt.link || '') : '';
                addHookModal.show();
            }

            function deleteHookOption(id, name) {
                if (!confirm(`Delete hook "${name}"? It will be removed from all rows.`)) return;
                fetch(`/video-ads-master/hook-options/${id}`, {
                    method: 'DELETE',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                })
                .then(r => r.json().then(j => ({ ok: r.ok, j })))
                .then(({ ok, j }) => {
                    if (!ok || !j.success) {
                        showToast((j && j.message) || 'Failed to delete', 'error');
                        return;
                    }
                    if (j.options) setHookOptions(j.options);
                    refreshTableHookCells(name, null);
                    showToast('Hook deleted', 'success');
                })
                .catch(e => { console.error(e); showToast('Network error deleting hook', 'error'); });
            }

            function refreshTableHookCells(oldName, newName) {
                if (!table) return;
                table.getRows().forEach(row => {
                    const data = row.getData();
                    const tags = parseTags(data.hook_name);
                    if (!tags.includes(oldName)) return;
                    const next = newName === null
                        ? tags.filter(t => t !== oldName)
                        : tags.map(t => t === oldName ? newName : t);
                    row.update({ hook_name: next });
                });
            }

            function buildHookLookup() {
                return hookOptionNames().map(n => ({ value: n, label: displayHookName(n) }));
            }

            // After inline AUDIENCE multi-select: keep tags as an array in the
            // table, register any brand-new values, then persist.
            function audienceEdited(cell) {
                const tags = parseTags(cell.getValue());
                const row  = cell.getRow();
                row.update({ audience: tags });
                tags.forEach(t => {
                    if (!audienceOptionNames().includes(t)) createAudienceOption(t);
                });
                persistCell(cell);
            }

            // After inline HOOK multi-select: same as audience, plus auto-fill
            // Hook Message + Link when exactly one known hook is selected.
            function hookNameEdited(cell) {
                const tags = parseTags(cell.getValue());
                const row  = cell.getRow();
                row.update({ hook_name: tags });
                tags.forEach(t => {
                    if (!hookOptionNames().includes(t)) createHookOption(t);
                });
                if (tags.length === 1) {
                    const known = findHook(tags[0]);
                    if (known) applyHookDefaultsToRow(row, known);
                }
                persistCell(cell);
            }

            // ── Persistence (single-cell PUT) ──────────────────────────────────

            // Saves whichever cell the user just edited. The SKU/PARENT/GROUP
            // column writes to `target_type`; everything else writes to the
            // column's own field name. Badges are refreshed *immediately*
            // (before the network call) so the UI feels instant.
            function persistCell(cell) {
                const row   = cell.getRow();
                const data  = row.getData();
                if (!data.id) return;

                const field = cell.getField();
                const value = cell.getValue();

                const payload = {};
                if (field === '_target') {
                    payload.target_type = (value === '' || value === null) ? null : value;
                    // Keep our local mirror in sync without re-triggering cellEdited.
                    row.update({ target_type: payload.target_type });
                } else if (field === 'audience' || field === 'hook_name') {
                    // Table keeps tags as arrays for multi-select; API gets a
                    // pipe-delimited string.
                    const tags = parseTags(value);
                    payload[field] = formatTags(tags) || null;
                    row.update({ [field]: tags });
                } else {
                    payload[field] = (value === '' ? null : value);
                }

                // Optimistic badge refresh — the row data is already updated
                // locally, so update the counts before the server replies.
                updateCount();

                // Editing the LINK cell changes whether the row is "missing"
                // a link, so refresh that cell's red-dot indicator live.
                if (field === 'link') {
                    const missingCell = row.getCell('_missing');
                    if (missingCell) missingCell.reformat();
                }

                fetch(`/video-ads-master/${data.id}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify(payload),
                })
                .then(r => r.json().then(j => ({ ok: r.ok, j })))
                .then(({ ok, j }) => {
                    if (!ok || !j.success) { showToast((j && j.message) || 'Failed to save', 'error'); return; }
                    updateCount();
                })
                .catch(e => { console.error(e); showToast('Network error while saving', 'error'); });
            }

            // Apply a hook template's default Hook Message + Link onto a row
            // (both the displayed cell and the underlying data), then push
            // those changes through the per-cell save endpoint so they're
            // persisted. Overwrites whatever was there — same semantics as
            // the form's auto-fill.
            function applyHookDefaultsToRow(row, hook) {
                if (!hook) return;
                const updates = {};
                const desiredHook = hook.hook || '';
                const desiredLink = hook.link || '';
                if (row.getCell('hook'))  updates.hook = desiredHook;
                if (row.getCell('link'))  updates.link = desiredLink;
                if (Object.keys(updates).length === 0) return;

                // Push the values into the cells without triggering
                // cellEdited (row.update bypasses editor hooks), then issue a
                // single PUT carrying both changes.
                row.update(updates);
                const missingCell = row.getCell('_missing');
                if (missingCell) missingCell.reformat();
                const id = row.getData().id;
                if (!id) return;

                fetch(`/video-ads-master/${id}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({
                        hook: desiredHook === '' ? null : desiredHook,
                        link: desiredLink === '' ? null : desiredLink,
                    }),
                })
                .catch(e => console.warn('Failed to sync hook defaults to row:', e));
            }

            // ── Modal form helpers ─────────────────────────────────────────────

            // When exactly one known hook tag is selected in the form, copy
            // that hook's default Hook Message + Link into the inputs.
            function applyHookDefaultsToForm() {
                const tags = parseTags(getFormHookValue());
                if (tags.length !== 1) return;
                const hook = findHook(tags[0]);
                if (!hook) return;
                document.getElementById('vam_hook').value = hook.hook || '';
                document.getElementById('vam_link').value = hook.link || '';
            }

            // Repopulates the CHANNEL <datalist>. Called on boot.
            function refreshChannelDatalist() {
                const dl = document.getElementById('vam-channels-list');
                dl.innerHTML = '';
                (channelOptions || []).forEach(c => {
                    const o = document.createElement('option');
                    o.value = c;
                    dl.appendChild(o);
                });
            }

            function resetForm() {
                document.getElementById('vam_id').value           = '';
                document.getElementById('vam_target_type').value  = '';
                document.getElementById('vam_name').value         = '';
                document.getElementById('vam_channel').value      = '';
                document.getElementById('vam_hook').value         = '';
                document.getElementById('vam_link').value         = '';
                rebuildAudienceSelect([]);
                rebuildHookSelect([]);
            }

            function openAddForm() {
                editingId = null;
                resetForm();
                document.getElementById('vamRowModalTitle').innerHTML = '<i class="fas fa-video me-2"></i>Add Video Ad';
                rowModal.show();
            }

            function openEditForm(data) {
                editingId = data.id;
                resetForm();
                document.getElementById('vamRowModalTitle').innerHTML = '<i class="fas fa-video me-2"></i>Edit Video Ad';
                document.getElementById('vam_id').value          = data.id;
                document.getElementById('vam_target_type').value = data.target_type || '';
                document.getElementById('vam_name').value        = data.name        || '';
                document.getElementById('vam_channel').value     = data.channel     || '';
                rebuildAudienceSelect(parseTags(data.audience));
                rebuildHookSelect(parseTags(data.hook_name));
                document.getElementById('vam_hook').value        = data.hook        || '';
                document.getElementById('vam_link').value        = data.link        || '';
                rowModal.show();
            }

            // Collect the form into a clean payload object. Empty strings are
            // sent as null so the server can clear cells when the user blanks
            // them out.
            function collectFormPayload() {
                const v = id => {
                    const raw = (document.getElementById(id).value || '').trim();
                    return raw === '' ? null : raw;
                };
                return {
                    target_type: v('vam_target_type'),
                    name:        v('vam_name'),
                    channel:     v('vam_channel'),
                    audience:    getFormAudienceValue(),
                    hook_name:   getFormHookValue(),
                    hook:        v('vam_hook'),
                    link:        v('vam_link'),
                };
            }

            function saveFormRow() {
                const payload = collectFormPayload();
                if (!payload.target_type) {
                    showToast('Please select SKU / Parent / Group', 'warning');
                    document.getElementById('vam_target_type').focus();
                    return;
                }

                const isEdit = !!editingId;
                const url    = isEdit ? `/video-ads-master/${editingId}` : '/video-ads-master';
                const method = isEdit ? 'PUT' : 'POST';

                fetch(url, {
                    method,
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify(payload),
                })
                .then(r => r.json().then(j => ({ ok: r.ok, j })))
                .then(({ ok, j }) => {
                    if (!ok || !j.success) {
                        showToast((j && j.message) || 'Failed to save', 'error');
                        return;
                    }
                    const row = normalizeRowTags(j.row);

                    if (isEdit) {
                        table.updateRow(row.id, row);
                        showToast('Row updated', 'success');
                    } else {
                        table.addRow(row, true); // prepend
                        showToast('Row added', 'success');
                    }
                    updateCount();
                    rowModal.hide();
                })
                .catch(e => { console.error(e); showToast('Network error while saving', 'error'); });
            }

            // ── Boot ───────────────────────────────────────────────────────────
            document.addEventListener('DOMContentLoaded', function () {
                rowModal            = new bootstrap.Modal(document.getElementById('vamRowModal'));
                addHookModal        = new bootstrap.Modal(document.getElementById('vamAddHookModal'));
                hookManageModal     = new bootstrap.Modal(document.getElementById('vamHookManageModal'));
                audienceManageModal = new bootstrap.Modal(document.getElementById('vamAudienceManageModal'));
                pickTagsModal       = new bootstrap.Modal(document.getElementById('vamPickTagsModal'));
                checkHistoryModal   = new bootstrap.Modal(document.getElementById('vamCheckHistoryModal'));

                fetch('/video-ads-master/data', { headers: { 'Accept': 'application/json' } })
                    .then(r => r.json())
                    .then(payload => {
                        if (!payload.success) {
                            showToast('Failed to load data', 'error');
                            return;
                        }
                        channelOptions = payload.channels       || [];
                        setHookOptions(payload.hook_options || []);
                        setAudienceOptions(payload.audience_options || []);

                        refreshChannelDatalist();
                        initTable(payload.rows || []);
                    })
                    .catch(e => {
                        console.error(e);
                        showToast('Network error loading data', 'error');
                    });

                document.getElementById('vamAddRowBtn').addEventListener('click', openAddForm);
                document.getElementById('vamRowSaveBtn').addEventListener('click', saveFormRow);
                document.getElementById('vamSearch').addEventListener('input', applySearch);

                document.getElementById('vamAudienceManageBtn').addEventListener('click', openAudienceManageModal);
                document.getElementById('vamAudienceAddBtn').addEventListener('click', addAudienceFromManageModal);
                document.getElementById('vamAudienceNewName').addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') { e.preventDefault(); addAudienceFromManageModal(); }
                });
                document.getElementById('vamAudienceManageList').addEventListener('click', (e) => {
                    const row = e.target.closest('.vam-manage-row');
                    if (!row) return;
                    const id = Number(row.getAttribute('data-id'));
                    const name = (audienceOptions.find(o => o.id === id) || {}).name || '';
                    if (e.target.closest('.vam-aud-edit')) {
                        renameAudienceOption(id, name);
                        return;
                    }
                    if (e.target.closest('.vam-aud-del')) {
                        deleteAudienceOption(id, name);
                    }
                });

                // Import CSV flow: button proxies the hidden file input; the
                // change handler kicks off the upload.
                document.getElementById('vamImportBtn').addEventListener('click', () => {
                    document.getElementById('vamImportFile').click();
                });
                document.getElementById('vamImportFile').addEventListener('change', handleImportFile);

                document.getElementById('vamHookManageBtn').addEventListener('click', openHookManageModal);
                document.getElementById('vamHookManageAddBtn').addEventListener('click', () => openHookEditor(null));
                document.getElementById('vamHookManageList').addEventListener('click', (e) => {
                    const row = e.target.closest('.vam-manage-row');
                    if (!row) return;
                    const id = Number(row.getAttribute('data-id'));
                    const opt = hookOptions.find(o => o.id === id) || null;
                    if (e.target.closest('.vam-hook-edit')) {
                        openHookEditor(opt);
                        return;
                    }
                    if (e.target.closest('.vam-hook-del')) {
                        deleteHookOption(id, (opt && opt.name) || '');
                    }
                });

                document.getElementById('vamSaveNewHookBtn').addEventListener('click', saveNewHook);
                document.getElementById('vamNewHookName').addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') { e.preventDefault(); saveNewHook(); }
                });

                document.getElementById('vamPickTagsApplyBtn').addEventListener('click', applyPickTagsSelection);
                document.getElementById('vamPickTagsAddNewBtn').addEventListener('click', addNewOptionInPickModal);
                document.getElementById('vamPickTagsSearch').addEventListener('input', renderPickTagsList);
                document.getElementById('vamPickTagsNew').addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') { e.preventDefault(); addNewOptionInPickModal(); }
                });
                document.getElementById('vamPickTagsList').addEventListener('change', (e) => {
                    const opt = e.target.closest('.vam-pick-option');
                    if (!opt || e.target.type !== 'checkbox') return;
                    opt.classList.toggle('is-checked', e.target.checked);
                });
            });

            // Normalise a server row for the grid: mirror target_type and keep
            // audience / hook_name as tag arrays so the multi-select list
            // editor can pre-tick current choices.
            function normalizeRowTags(r) {
                const row = r || {};
                row._target   = row.target_type || '';
                row.audience  = parseTags(row.audience);
                row.hook_name = parseTags(row.hook_name);
                return row;
            }

            function initTable(rows) {
                rows = (rows || []).map(normalizeRowTags);

                table = new Tabulator('#video-ads-master-table', {
                    data: rows,
                    layout: 'fitData',
                    placeholder: 'No rows yet. Click "Add Row" to start.',
                    pagination: true,
                    paginationSize: 100,
                    paginationSizeSelector: [25, 50, 100, 200, 500],
                    paginationCounter: 'rows',
                    index: 'id',
                    columnDefaults: {
                        headerHozAlign: 'center',
                    },
                    columns: [
                        {
                            title: 'S/P/G', field: '_target',
                            formatter: targetFormatter,
                            editor: targetTypeEditor,
                            cellEdited: persistCell,
                        },
                        {
                            title: 'Target Products', field: 'name',
                            formatter: plainFormatter,
                            editor: 'input',
                            cellEdited: persistCell,
                        },
                        {
                            title: 'Type', field: 'channel',
                            formatter: plainFormatter,
                            editor: 'list',
                            editorParams: {
                                values: buildChannelLookup,
                                autocomplete: true,
                                freetext: true,
                                listOnEmpty: true,
                                clearable: true,
                            },
                            cellEdited: persistCell,
                        },
                        {
                            title: 'Target Audience', field: 'audience',
                            formatter: audienceFormatter,
                            headerSort: false,
                            editable: false,
                            cellClick: (e, cell) => {
                                if (e.target.closest('.vam-tag-add-btn')) {
                                    e.stopPropagation();
                                    openPickTagsModal(cell.getRow(), 'audience');
                                }
                            },
                        },
                        {
                            title: 'HOOK', field: 'hook_name',
                            formatter: hookFormatter,
                            headerSort: false,
                            editable: false,
                            cellClick: (e, cell) => {
                                if (e.target.closest('.vam-tag-add-btn')) {
                                    e.stopPropagation();
                                    openPickTagsModal(cell.getRow(), 'hook_name');
                                }
                            },
                        },
                        {
                            title: 'HOOK MESSAGE', field: 'hook',
                            formatter: plainFormatter,
                            editor: 'input',
                            cellEdited: persistCell,
                        },
                        {
                            title: 'LINK', field: 'link', hozAlign: 'center',
                            formatter: linkFormatter,
                            editor: 'input',
                            cellEdited: persistCell,
                        },
                        {
                            title: 'MISSING', field: '_missing', hozAlign: 'center',
                            headerSort: false,
                            formatter: missingFormatter,
                        },
                        {
                            title: 'CHECK', field: '_check', hozAlign: 'center',
                            headerSort: false,
                            formatter: checkFormatter,
                            cellClick: (e, cell) => {
                                if (e.target.closest('.vam-check-history')) {
                                    e.preventDefault();
                                    showCheckHistory(cell.getRow());
                                    return;
                                }
                                const box = e.target.closest('.vam-check-box');
                                if (box) {
                                    // The browser has already flipped the box;
                                    // box.checked is the desired new state.
                                    toggleCheck(cell.getRow(), box.checked);
                                }
                            },
                        },
                        {
                            title: 'AD', field: '_adcheck', hozAlign: 'center',
                            headerSort: false,
                            formatter: adCheckFormatter,
                            cellClick: (e, cell) => {
                                const box = e.target.closest('.vam-ad-box');
                                if (box) {
                                    // The browser has already flipped the box;
                                    // box.checked is the desired new state.
                                    toggleAdCheck(cell.getRow(), box.checked);
                                }
                            },
                        },
                        {
                            title: '',
                            field: '_actions',
                            hozAlign: 'center',
                            headerSort: false,
                            formatter: () => `
                                <button class="btn btn-sm btn-outline-primary me-1 vam-edit-btn"   title="Edit"><i class="fas fa-edit"></i></button>
                                <button class="btn btn-sm btn-outline-success me-1 vam-copy-btn"  title="Duplicate row"><i class="fas fa-copy"></i></button>
                                <button class="btn btn-sm btn-outline-danger vam-delete-btn"      title="Delete"><i class="fas fa-trash"></i></button>
                            `,
                            cellClick: (e, cell) => {
                                if (e.target.closest('.vam-edit-btn'))   { openEditForm(cell.getRow().getData()); return; }
                                if (e.target.closest('.vam-copy-btn'))   { copyRow(cell.getRow()); return; }
                                if (e.target.closest('.vam-delete-btn')) { deleteRow(cell.getRow()); return; }
                            },
                        },
                    ],
                    dataLoaded:   () => updateCount(),
                    dataChanged:  () => updateCount(),   // covers any setData / addData / etc.
                    rowAdded:     () => updateCount(),
                    rowUpdated:   () => updateCount(),   // covers row.update() from persistCell
                    rowDeleted:   () => updateCount(),
                    dataFiltered: () => updateCount(),   // covers search filter changes
                });

                // Belt-and-braces refresh after the table finishes building —
                // some Tabulator versions don't have `active` data available
                // by the time `dataLoaded` fires.
                table.on('tableBuilt', () => updateCount());
                setTimeout(updateCount, 50);
            }

            function updateCount() {
                if (!table) return;
                // Prefer "active" (post-filter) but fall back to the full set
                // when the active view isn't ready yet — e.g. immediately
                // after the table is first built.
                let rows;
                try { rows = table.getData('active'); } catch (e) { rows = null; }
                if (!rows || rows.length === 0) {
                    const all = table.getData();
                    if (all && all.length) rows = all;
                }
                rows = rows || [];

                let sku = 0, parent = 0, group = 0, links = 0;
                rows.forEach(r => {
                    const t = String(r.target_type || '').toLowerCase();
                    if (t === 'sku')         sku++;
                    else if (t === 'parent') parent++;
                    else if (t === 'group')  group++;
                    if (isLikelyUrl(normalizeUrl(r.link))) links++;
                });
                document.getElementById('vamRowCount').textContent    = rows.length;
                document.getElementById('vamSkuCount').textContent    = sku;
                document.getElementById('vamParentCount').textContent = parent;
                document.getElementById('vamGroupCount').textContent  = group;
                document.getElementById('vamLinkCount').textContent   = links;
                document.getElementById('vamMissingCount').textContent = rows.length - links;
            }

            function applySearch() {
                if (!table) return;
                const q = document.getElementById('vamSearch').value.trim().toLowerCase();
                if (!q) { table.clearFilter(); updateCount(); return; }
                table.setFilter((data) => {
                    const haystack = [
                        data._target, data.name, data.channel,
                        formatTags(data.audience), formatTags(data.hook_name),
                        data.hook, data.link,
                    ].map(v => (v || '').toString().toLowerCase()).join(' | ');
                    return haystack.includes(q);
                });
                updateCount();
            }

            function deleteRow(row) {
                const data = row.getData();
                if (!confirm('Delete this row?')) return;
                fetch(`/video-ads-master/${data.id}`, {
                    method: 'DELETE',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                })
                .then(r => r.json())
                .then(j => {
                    if (!j.success) { showToast(j.message || 'Failed to delete', 'error'); return; }
                    row.delete();
                    updateCount();
                    showToast('Row deleted', 'success');
                })
                .catch(e => { console.error(e); showToast('Network error', 'error'); });
            }

            // Handle CSV import — uploads the file as multipart/form-data,
            // then refreshes the table (the server creates one row per CSV
            // row). The toast surfaces the created/skipped counts; the first
            // few row errors are printed to the console for debugging.
            function handleImportFile(e) {
                const input = e.target;
                const file = input.files && input.files[0];
                if (!file) return;

                const fd = new FormData();
                fd.append('file', file);

                showToast('Uploading ' + file.name + '…', 'info');

                fetch('/video-ads-master/import', {
                    method: 'POST',
                    headers: {
                        // No Content-Type — the browser sets the multipart
                        // boundary automatically. Accept ensures Laravel
                        // returns JSON validation errors instead of a
                        // redirect to the previous page.
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: fd,
                })
                .then(async r => {
                    // Parse the body even on non-2xx — Laravel ships
                    // validation errors as JSON when Accept: application/json.
                    let j = null;
                    const text = await r.text();
                    try { j = text ? JSON.parse(text) : null; } catch (e) { /* not json */ }
                    return { ok: r.ok, status: r.status, j, text };
                })
                .then(({ ok, status, j, text }) => {
                    if (!ok || !j || j.success === false) {
                        // Build the clearest error message we can.
                        let msg = (j && (j.message || j.error)) || `Import failed (HTTP ${status})`;
                        // Laravel validation format: { errors: { field: ["msg"...] } }
                        if (j && j.errors && typeof j.errors === 'object') {
                            const firstField = Object.keys(j.errors)[0];
                            if (firstField && Array.isArray(j.errors[firstField]) && j.errors[firstField][0]) {
                                msg = j.errors[firstField][0];
                            }
                        }
                        console.error('Import failed', { status, body: j || text });
                        showToast(msg, 'error');
                        return;
                    }
                    const created = j.created || 0;
                    const skipped = j.skipped || 0;
                    let msg = `Imported ${created} row(s)`;
                    if (skipped) msg += `, skipped ${skipped}`;
                    showToast(msg, created > 0 ? 'success' : 'warning');

                    if ((j.errors || []).length) {
                        console.warn('Video Ads Master import — row errors:', j.errors);
                    }

                    // Re-pull the full dataset so the new rows + badge counts
                    // appear immediately.
                    reloadTable();
                })
                .catch(err => { console.error(err); showToast('Network error during import', 'error'); })
                .finally(() => { input.value = ''; }); // allow re-import of same file
            }

            // Reloads rows + lookups from the server. Used after CSV import
            // and could be reused for any other server-side bulk mutation.
            function reloadTable() {
                fetch('/video-ads-master/data', { headers: { 'Accept': 'application/json' } })
                    .then(r => r.json())
                    .then(payload => {
                        if (!payload.success) { showToast('Failed to reload data', 'error'); return; }
                        channelOptions = payload.channels     || [];
                        setHookOptions(payload.hook_options || []);
                        setAudienceOptions(payload.audience_options || []);
                        refreshChannelDatalist();
                        table.setData((payload.rows || []).map(normalizeRowTags));
                        updateCount();
                    })
                    .catch(e => { console.error(e); showToast('Network error reloading data', 'error'); });
            }

            // Duplicate a row via POST /video-ads-master/{id}/copy. Server-side
            // replicate() carries every column, returns the fresh row, and we
            // prepend it to the table.
            function copyRow(row) {
                const data = row.getData();
                fetch(`/video-ads-master/${data.id}/copy`, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                })
                .then(r => r.json().then(j => ({ ok: r.ok, j })))
                .then(({ ok, j }) => {
                    if (!ok || !j.success) {
                        showToast((j && j.message) || 'Failed to copy row', 'error');
                        return;
                    }
                    table.addRow(normalizeRowTags(j.row), true); // prepend
                    updateCount();
                    showToast('Row duplicated', 'success');
                })
                .catch(e => { console.error(e); showToast('Network error while copying', 'error'); });
            }

            // Toggle a row's CHECK state. Persists the new state to the
            // server (which stamps user + time and logs history), then
            // mirrors the returned values back onto the row.
            function toggleCheck(row, desired) {
                const data = row.getData();
                if (!data.id) return;

                fetch(`/video-ads-master/${data.id}/check`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ is_checked: !!desired }),
                })
                .then(r => r.json().then(j => ({ ok: r.ok, j })))
                .then(({ ok, j }) => {
                    if (!ok || !j.success) {
                        showToast((j && j.message) || 'Failed to update check', 'error');
                        row.reformat(); // revert the visual toggle
                        return;
                    }
                    row.update({
                        is_checked: j.row.is_checked,
                        checked_by: j.row.checked_by,
                        checked_at: j.row.checked_at,
                    });
                    row.reformat();
                    showToast(j.row.is_checked ? 'Marked as checked' : 'Marked as unchecked', 'success');
                })
                .catch(e => { console.error(e); showToast('Network error while updating check', 'error'); row.reformat(); });
            }

            // Toggle a row's AD state. Persists the new state to the server
            // (which stamps user + time), then mirrors the returned values
            // back onto the row.
            function toggleAdCheck(row, desired) {
                const data = row.getData();
                if (!data.id) return;

                fetch(`/video-ads-master/${data.id}/ad-check`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ ad_checked: !!desired }),
                })
                .then(r => r.json().then(j => ({ ok: r.ok, j })))
                .then(({ ok, j }) => {
                    if (!ok || !j.success) {
                        showToast((j && j.message) || 'Failed to update ad', 'error');
                        row.reformat(); // revert the visual toggle
                        return;
                    }
                    row.update({
                        ad_checked:    j.row.ad_checked,
                        ad_checked_by: j.row.ad_checked_by,
                        ad_checked_at: j.row.ad_checked_at,
                    });
                    row.reformat();
                    showToast(j.row.ad_checked ? 'Marked as ad' : 'Unmarked as ad', 'success');
                })
                .catch(e => { console.error(e); showToast('Network error while updating ad', 'error'); row.reformat(); });
            }

            // Fetch and render the check/uncheck audit trail for a row.
            function showCheckHistory(row) {
                const data = row.getData();
                if (!data.id) return;

                const body = document.getElementById('vamCheckHistoryBody');
                body.innerHTML = '<div class="text-muted text-center py-3">Loading…</div>';
                checkHistoryModal.show();

                fetch(`/video-ads-master/${data.id}/check-history`, { headers: { 'Accept': 'application/json' } })
                    .then(r => r.json())
                    .then(j => {
                        if (!j.success) { body.innerHTML = '<div class="text-danger text-center py-3">Failed to load history.</div>'; return; }
                        const items = j.history || [];
                        if (items.length === 0) {
                            body.innerHTML = '<div class="text-muted text-center py-3">No check activity yet.</div>';
                            return;
                        }
                        const rowsHtml = items.map(h => {
                            const isChecked = !!h.is_checked;
                            const badge = isChecked
                                ? '<span class="badge bg-success">Checked</span>'
                                : '<span class="badge bg-secondary">Unchecked</span>';
                            return `
                                <tr>
                                    <td>${badge}</td>
                                    <td>${escapeHtml(h.username || '—')}</td>
                                    <td>${escapeHtml(formatDateTime(h.created_at))}</td>
                                </tr>`;
                        }).join('');
                        body.innerHTML = `
                            <table class="table table-sm table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>Action</th>
                                        <th>User</th>
                                        <th>When</th>
                                    </tr>
                                </thead>
                                <tbody>${rowsHtml}</tbody>
                            </table>`;
                    })
                    .catch(e => { console.error(e); body.innerHTML = '<div class="text-danger text-center py-3">Network error.</div>'; });
            }

            // Save a hook entry from the Add / Edit Hook modal (name + default
            // message/link). Creates via POST or updates via PUT when editing.
            function saveNewHook() {
                const name    = normalizeHookName(document.getElementById('vamNewHookName').value);
                const hookMsg = document.getElementById('vamNewHookMessage').value.trim();
                const link    = document.getElementById('vamNewHookLink').value.trim();
                if (!name) { showToast('Enter a hook name', 'warning'); return; }

                const id = editingHookId || document.getElementById('vamEditingHookId').value || null;
                const oldName = id
                    ? ((hookOptions.find(o => String(o.id) === String(id)) || {}).name || null)
                    : null;
                const url    = id ? `/video-ads-master/hook-options/${id}` : '/video-ads-master/hook-options';
                const method = id ? 'PUT' : 'POST';

                fetch(url, {
                    method,
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({
                        name: name,
                        hook: hookMsg,
                        link: link,
                    }),
                })
                .then(r => r.json().then(j => ({ ok: r.ok, j })))
                .then(({ ok, j }) => {
                    if (!ok || !j.success) { showToast((j && j.message) || 'Failed to save hook', 'error'); return; }
                    if (j.options) setHookOptions(j.options);
                    if (oldName && oldName !== name) {
                        refreshTableHookCells(oldName, name);
                    }
                    // Keep the new/renamed hook selected on the form if open.
                    const current = parseTags(getFormHookValue());
                    if (oldName && current.includes(oldName)) {
                        rebuildHookSelect(current.map(t => t === oldName ? name : t));
                    } else if (!current.includes(name)) {
                        rebuildHookSelect(current.concat([name]));
                    }
                    applyHookDefaultsToForm();
                    editingHookId = null;
                    document.getElementById('vamEditingHookId').value = '';
                    addHookModal.hide();
                    showToast(id ? 'Hook updated' : 'Hook saved', 'success');
                })
                .catch(e => { console.error(e); showToast('Network error', 'error'); });
            }
        })();
    </script>
@endsection
