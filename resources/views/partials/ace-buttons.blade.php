{{-- ATE (Auto Task Event) buttons + modals.
     Built-in pages include it directly: @include('partials.ace-buttons', ['acePage' => 'users'])  (users | active_channels)
     Analytics / active-channels pages get it from the top bar (see config/ace.php): acePage = hashed page key,
     aceSource = that page's data endpoint (null = detect from the page's Tabulator table).
       - "+ ATE": create a rule (any logged-in user).
       - "ATE":   every rule for this page; the owner can edit / pause / run / delete their own. --}}
@php
    $aceUid = 'ace_'.preg_replace('/[^a-z0-9]/i', '', (string) ($acePage ?? 'page'));
@endphp

<style>
    .ace-btn { border: 0; border-radius: 999px; font-weight: 700; font-size: .8rem; padding: 6px 14px; color: #fff; white-space: nowrap; transition: transform .15s ease, box-shadow .15s ease; }
    .ace-btn:hover { transform: translateY(-1px); color: #fff; }
    .ace-btn-new { background: linear-gradient(135deg, #7c3aed, #3b82f6); box-shadow: 0 4px 12px rgba(99, 102, 241, .35); }
    .ace-btn-list { background: linear-gradient(135deg, #0f766e, #14b8a6); box-shadow: 0 4px 12px rgba(15, 118, 110, .3); }
    .ace-modal .modal-header { background: linear-gradient(120deg, #6d28d9, #3b82f6); color: #fff; border: 0; }
    .ace-modal .btn-close { filter: invert(1); }
    /* The create / edit form uses the full page height (the body scrolls, header + buttons stay put). */
    #{{ $aceUid }}_newModal .modal-dialog { height: calc(100vh - 1rem); max-height: none; margin-top: .5rem; margin-bottom: .5rem; }
    #{{ $aceUid }}_newModal .modal-content { height: 100%; max-height: 100%; }
    .ace-modal .modal-content { border: 0; border-radius: 16px; overflow: hidden; }
    .ace-sec { background: #f8faff; border: 1px solid #e5eaf7; border-radius: 12px; padding: 12px 14px; margin-bottom: 12px; }
    .ace-sec-title { font-weight: 800; color: #3730a3; font-size: .85rem; letter-spacing: .03em; text-transform: uppercase; margin-bottom: 8px; }
    .ace-cond { display: flex; gap: 6px; align-items: center; margin-bottom: 6px; }
    .ace-cond .ace-c-col { flex: 5; } .ace-cond .ace-c-op { flex: 3; } .ace-cond .ace-c-val { flex: 4; }
    .ace-cond .ace-c-del { border: 0; background: #fee2e2; color: #b91c1c; border-radius: 50%; width: 26px; height: 26px; line-height: 1; flex-shrink: 0; }
    .ace-cond .ace-c-del:hover { background: #b91c1c; color: #fff; }
    .ace-cpick { min-width: 0; }
    .ace-cpick .ace-cp-btn { text-align: left; cursor: pointer; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .ace-cp-menu { border: 1px solid #dbe3f3; border-radius: 8px; background: #fff; padding: 6px; margin-top: 4px; }
    .ace-cp-list { max-height: 220px; overflow: auto; margin-top: 4px; }
    .ace-cp-item { display: flex; align-items: center; gap: 8px; padding: 3px 6px; margin: 0; font-size: .82rem; cursor: pointer; border-radius: 6px; }
    .ace-cp-item:hover { background: #eef2ff; }
    .ace-cp-links a { font-size: .75rem; font-weight: 600; cursor: pointer; text-decoration: none; }
    .ace-pick { border: 1px solid #dbe3f3; border-radius: 8px; background: #fff; padding: 6px; }
    #{{ $aceUid }}_alist { max-height: 140px; overflow: auto; }
    .ace-pick-item { display: flex; align-items: center; gap: 8px; padding: 3px 6px; margin: 0; font-size: .82rem; cursor: pointer; border-radius: 6px; }
    .ace-pick-item:hover { background: #eef2ff; }
    .ace-trow { display: flex; gap: 6px; margin-bottom: 6px; }
    .ace-trow .ace-t-del { border: 0; background: #fee2e2; color: #b91c1c; border-radius: 6px; width: 31px; flex-shrink: 0; line-height: 1; }
    .ace-hint { font-size: .75rem; color: #64748b; }
    .ace-rule { border: 1px solid #e5eaf7; border-radius: 12px; padding: 12px 14px; margin-bottom: 10px; background: #fff; }
    .ace-rule.is-paused { opacity: .65; background: #f8fafc; }
    .ace-rule-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; flex-wrap: wrap; }
    .ace-rule-name { font-weight: 800; color: #0f172a; }
    .ace-badge { font-size: .68rem; font-weight: 800; border-radius: 999px; padding: 2px 9px; color: #fff; }
    .ace-badge-on { background: #16a34a; } .ace-badge-off { background: #94a3b8; }
    .ace-badge-type { background: #6366f1; }
    .ace-kv { font-size: .8rem; color: #334155; margin-top: 4px; }
    .ace-kv b { color: #0f172a; }
    .ace-runs { margin: 8px 0 0; padding-left: 18px; font-size: .75rem; color: #475569; }
    .ace-runs .ace-fail { color: #b91c1c; }
</style>

<div class="d-inline-flex align-items-center gap-2 flex-wrap ace-buttons">
    <button type="button" class="ace-btn ace-btn-new" id="{{ $aceUid }}_newBtn" title="Create an auto task event (one-time task)">
        <i class="ri-add-line me-1"></i> ATE
    </button>
    <button type="button" class="ace-btn ace-btn-list" id="{{ $aceUid }}_listBtn" title="Show all Auto Task Events for this page">
        <i class="ri-list-check-2 me-1"></i> ATE
    </button>
</div>

{{-- Create modal --}}
<div class="modal fade ace-modal" id="{{ $aceUid }}_newModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-0" id="{{ $aceUid }}_newTitle">&#9889; Auto Task Event</h5>
                    <small class="opacity-75" id="{{ $aceUid }}_newSub">Create a one-time task automatically when a column matches</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Rule name</label>
                    <input type="text" class="form-control" id="{{ $aceUid }}_name" maxlength="120" placeholder="e.g. Chase missing docs">
                </div>

                <div class="ace-sec">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                        <div class="ace-sec-title mb-0" id="{{ $aceUid }}_secTitle">1 &middot; When this column&hellip;</div>
                        <div class="btn-group btn-group-sm d-none" role="group" id="{{ $aceUid }}_kindWrap" aria-label="What triggers this event">
                            <input type="radio" class="btn-check" name="{{ $aceUid }}_kind" id="{{ $aceUid }}_kc" value="col" checked>
                            <label class="btn btn-outline-primary" for="{{ $aceUid }}_kc">Column block</label>
                            <input type="radio" class="btn-check" name="{{ $aceUid }}_kind" id="{{ $aceUid }}_kb" value="badge">
                            <label class="btn btn-outline-primary" for="{{ $aceUid }}_kb">Badges block</label>
                        </div>
                    </div>

                    <div id="{{ $aceUid }}_blockCols">
                        <div id="{{ $aceUid }}_conds"></div>
                        <div class="d-flex align-items-center gap-2 mt-2 flex-wrap">
                            <button type="button" class="btn btn-sm btn-outline-primary" id="{{ $aceUid }}_addCond" title="Add another condition">
                                <i class="ri-add-line"></i> Add condition
                            </button>
                            <select class="form-select form-select-sm d-none" style="width:auto" id="{{ $aceUid }}_mode" title="How the conditions combine">
                                <option value="all">Match ALL conditions (AND)</option>
                                <option value="any">Match ANY condition (OR)</option>
                            </select>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="{{ $aceUid }}_previewBtn">Preview matches</button>
                        </div>
                        <div class="ace-hint mt-2" id="{{ $aceUid }}_preview"></div>
                    </div>

                    <div id="{{ $aceUid }}_blockBadges" class="d-none">
                        <div id="{{ $aceUid }}_bconds"></div>
                        <div class="d-flex align-items-center gap-2 mt-2 flex-wrap">
                            <button type="button" class="btn btn-sm btn-outline-primary" id="{{ $aceUid }}_addBadge" title="Add another badge condition">
                                <i class="ri-add-line"></i> Add condition
                            </button>
                            <select class="form-select form-select-sm d-none" style="width:auto" id="{{ $aceUid }}_bmode" title="How the conditions combine">
                                <option value="all">Match ALL conditions (AND)</option>
                                <option value="any">Match ANY condition (OR)</option>
                            </select>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="{{ $aceUid }}_bpreviewBtn">Check badges now</button>
                        </div>
                        <div class="ace-hint mt-2" id="{{ $aceUid }}_bpreview"></div>
                        <div class="ace-hint mt-1">Uses the badges at the top of this page. It is checked in the browser while someone has this page open, so the task is created once per checked period the first time the condition is met.</div>
                    </div>

                    <div class="mt-2 pt-2 border-top" id="{{ $aceUid }}_freqWrap">
                        <span class="fw-semibold small me-2">Check</span>
                        <div class="form-check form-check-inline mb-0"><input class="form-check-input ace-freq" type="checkbox" value="daily" id="{{ $aceUid }}_fd" checked><label class="form-check-label" for="{{ $aceUid }}_fd">Daily</label></div>
                        <div class="form-check form-check-inline mb-0"><input class="form-check-input ace-freq" type="checkbox" value="weekly" id="{{ $aceUid }}_fw"><label class="form-check-label" for="{{ $aceUid }}_fw">Weekly</label></div>
                        <div class="form-check form-check-inline mb-0"><input class="form-check-input ace-freq" type="checkbox" value="monthly" id="{{ $aceUid }}_fm"><label class="form-check-label" for="{{ $aceUid }}_fm">Monthly</label></div>
                        <div class="ace-hint" id="{{ $aceUid }}_freqHint">A task is created once per matching row in each checked period (day / week / month).</div>
                    </div>
                </div>

                <div class="ace-sec">
                    <div class="ace-sec-title">2 &middot; Create this task</div>
                    <div class="row g-2 mb-2">
                        <div class="col-md-5">
                            <label class="form-label fw-semibold mb-1">Group</label>
                            <input type="text" class="form-control form-control-sm" id="{{ $aceUid }}_group" maxlength="255" placeholder="Task group">
                        </div>
                        <div class="col-md-7">
                            <label class="form-label fw-semibold mb-1">Link (L1) <span class="ace-hint fw-normal">(auto-filled with this page)</span></label>
                            <input type="text" class="form-control form-control-sm" id="{{ $aceUid }}_link" maxlength="2048" placeholder="https://…">
                        </div>
                    </div>
                    <div class="mb-2">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <label class="form-label fw-semibold mb-0">Task <span class="text-danger">*</span></label>
                            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-semibold" id="{{ $aceUid }}_addTitle"><i class="ri-add-line"></i> Add task</button>
                        </div>
                        <div id="{{ $aceUid }}_titles"></div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-semibold mb-1">Description</label>
                        <textarea class="form-control form-control-sm" id="{{ $aceUid }}_desc" rows="2" maxlength="5000" placeholder="Optional. Placeholders work here too."></textarea>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-semibold mb-1">Assignee(s) <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm mb-1" id="{{ $aceUid }}_amode"></select>
                        <div id="{{ $aceUid }}_aUserWrap">
                            <div class="ace-pick">
                                <div class="d-flex gap-2 mb-1">
                                    <input type="text" class="form-control form-control-sm" id="{{ $aceUid }}_asearch" placeholder="Search…" autocomplete="off">
                                    <button type="button" class="btn btn-sm btn-outline-secondary text-nowrap" id="{{ $aceUid }}_aall">Select all</button>
                                </div>
                                <div id="{{ $aceUid }}_alist"></div>
                            </div>
                            <div class="ace-hint mt-1" id="{{ $aceUid }}_acount">None selected</div>
                            <div class="mt-1 d-none" id="{{ $aceUid }}_splitWrap">
                                <span class="small fw-semibold me-2">More than one assignee:</span>
                                <div class="form-check form-check-inline mb-0"><input class="form-check-input" type="radio" name="{{ $aceUid }}_split" id="{{ $aceUid }}_sj" value="0" checked><label class="form-check-label" for="{{ $aceUid }}_sj">Joint <span class="ace-hint">(one shared task)</span></label></div>
                                <div class="form-check form-check-inline mb-0"><input class="form-check-input" type="radio" name="{{ $aceUid }}_split" id="{{ $aceUid }}_ss" value="1"><label class="form-check-label" for="{{ $aceUid }}_ss">Split <span class="ace-hint">(each gets a copy)</span></label></div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-md-6"><label class="form-label mb-1 small fw-semibold">Assignor <span class="text-danger">*</span></label><select class="form-select form-select-sm" id="{{ $aceUid }}_assignor"></select></div>
                        <div class="col-md-3"><label class="form-label mb-1 small fw-semibold">ETC (min) <span class="text-danger">*</span></label><input type="number" class="form-control form-control-sm" id="{{ $aceUid }}_etc" min="0" max="100000" value="10"></div>
                        <div class="col-md-3"><label class="form-label mb-1 small fw-semibold">Corrective Action</label>
                            <label for="{{ $aceUid }}_ca" class="d-flex align-items-center gap-2 mb-0 border rounded px-2 bg-white" style="height:31px;cursor:pointer;">
                                <input type="checkbox" class="form-check-input m-0" id="{{ $aceUid }}_ca">
                                <img src="{{ asset('assets/images/task-ca-icon.png') }}" alt="" style="width:20px;height:20px;object-fit:contain;"> <span class="small">CA</span>
                            </label></div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-semibold mb-1">Frequency <span class="ace-hint fw-normal">(pick one or more)</span></label><br>
                        <div class="form-check form-check-inline mb-0"><input class="form-check-input ace-tfreq" type="checkbox" value="one_time" id="{{ $aceUid }}_t1" checked><label class="form-check-label" for="{{ $aceUid }}_t1">One Time</label></div>
                        <div class="form-check form-check-inline mb-0"><input class="form-check-input ace-tfreq" type="checkbox" value="daily" id="{{ $aceUid }}_t2"><label class="form-check-label" for="{{ $aceUid }}_t2">Daily</label></div>
                        <div class="form-check form-check-inline mb-0"><input class="form-check-input ace-tfreq" type="checkbox" value="weekly" id="{{ $aceUid }}_t3"><label class="form-check-label" for="{{ $aceUid }}_t3">Weekly</label></div>
                        <div class="form-check form-check-inline mb-0"><input class="form-check-input ace-tfreq" type="checkbox" value="monthly" id="{{ $aceUid }}_t4"><label class="form-check-label" for="{{ $aceUid }}_t4">Monthly</label></div>
                        <div class="ace-hint">Daily / Weekly / Monthly schedule a recurring task template, like the task manager does.</div>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-md-6"><label class="form-label mb-1 small fw-semibold">Priority</label>
                            <select class="form-select form-select-sm" id="{{ $aceUid }}_prio"><option value="low">Low</option><option value="normal" selected>Normal</option><option value="high">High</option></select></div>
                        <div class="col-md-6"><label class="form-label mb-1 small fw-semibold">Due in (days)</label><input type="number" class="form-control form-control-sm" id="{{ $aceUid }}_due" min="0" max="365" value="1"></div>
                    </div>

                    <button type="button" class="btn btn-outline-secondary btn-sm w-100" id="{{ $aceUid }}_moreBtn"><i class="ri-arrow-down-s-line" id="{{ $aceUid }}_moreIcon"></i> More</button>
                    <div class="row g-2 mt-1 d-none" id="{{ $aceUid }}_more">
                        <div class="col-md-6"><label class="form-label mb-1 small fw-semibold">L2</label><input type="text" class="form-control form-control-sm" id="{{ $aceUid }}_o_l2" maxlength="2048" placeholder="L2"></div>
                        <div class="col-md-6"><label class="form-label mb-1 small fw-semibold">Training</label><input type="text" class="form-control form-control-sm" id="{{ $aceUid }}_o_training_link" maxlength="2048" placeholder="Training"></div>
                        <div class="col-md-6"><label class="form-label mb-1 small fw-semibold">Video</label><input type="text" class="form-control form-control-sm" id="{{ $aceUid }}_o_video_link" maxlength="2048" placeholder="Video"></div>
                        <div class="col-md-6"><label class="form-label mb-1 small fw-semibold">Form</label><input type="text" class="form-control form-control-sm" id="{{ $aceUid }}_o_form_link" maxlength="2048" placeholder="Form"></div>
                        <div class="col-md-6"><label class="form-label mb-1 small fw-semibold">Report</label><input type="text" class="form-control form-control-sm" id="{{ $aceUid }}_o_form_report_link" maxlength="2048" placeholder="Report"></div>
                        <div class="col-md-6"><label class="form-label mb-1 small fw-semibold">Checklist</label><input type="text" class="form-control form-control-sm" id="{{ $aceUid }}_o_checklist_link" maxlength="2048" placeholder="Checklist"></div>
                        <div class="col-md-6"><label class="form-label mb-1 small fw-semibold">PL</label><input type="text" class="form-control form-control-sm" id="{{ $aceUid }}_o_pl" maxlength="2048" placeholder="PL"></div>
                        <div class="col-md-6"><label class="form-label mb-1 small fw-semibold">SOP</label><input type="text" class="form-control form-control-sm" id="{{ $aceUid }}_o_process" maxlength="2048" placeholder="SOP"></div>
                    </div>
                </div>
                <div class="text-danger small d-none" id="{{ $aceUid }}_err"></div>
            </div>
            <div class="modal-footer">
                <small class="text-muted me-auto" id="{{ $aceUid }}_foot">A task is created once per matching row in each checked period.</small>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="{{ $aceUid }}_save">Create ATE</button>
            </div>
        </div>
    </div>
</div>

{{-- Details modal --}}
<div class="modal fade ace-modal" id="{{ $aceUid }}_listModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-0">&#9889; ATE Details</h5>
                    <small class="opacity-75">Everyone can view all Auto Task Events here &middot; you can edit, pause, run and delete your own</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="{{ $aceUid }}_activeOnly" checked>
                    <label class="form-check-label" for="{{ $aceUid }}_activeOnly">Show active only</label>
                </div>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="{{ $aceUid }}_mineOnly">
                    <label class="form-check-label" for="{{ $aceUid }}_mineOnly">Show only mine</label>
                </div>
                <div id="{{ $aceUid }}_list"><div class="text-center text-muted py-4">Loading&hellip;</div></div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var PAGE = @json($acePage ?? '');
    var UID = @json($aceUid);
    var CSRF = @json(csrf_token());
    var URLS = { meta: @json(route('ace.meta')), index: @json(route('ace.index')), preview: @json(route('ace.preview')),
                 store: @json(route('ace.store')), base: @json(url('/ace')), badgeRules: @json(route('ace.badge.rules')) };
    var SERVER_SOURCE = @json($aceSource ?? null);
    var DYNAMIC = /^p_/.test(PAGE);          // analytics / active-channels page: rows come from its data endpoint
    var meta = null, rules = [], source = null, titles = {}, editing = null;
    var titleOrder = [];
    var kind = 'col', colsReady = !DYNAMIC, colsPromise = null, lastFetch = 0;
    var BADGE_OPS = [['gt', 'is greater than'], ['lt', 'is less than'], ['gte', 'is at least'], ['lte', 'is at most'], ['equals', 'is'], ['not_equals', 'is not']];

    function $(s) { return document.getElementById(UID + '_' + s); }
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function api(url, opts) {
        opts = opts || {};
        opts.credentials = 'same-origin';
        opts.headers = Object.assign({ 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF }, opts.headers || {});
        return fetch(url, opts).then(function (r) { return r.json().then(function (d) { d._ok = r.ok; return d; }); });
    }
    function post(url, body, method) {
        return api(url, { method: method || 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body || {}) });
    }
    function modal(id) { return bootstrap.Modal.getOrCreateInstance($(id)); }

    // Modals live in <body> so table / card stacking contexts can't clip them.
    ['newModal', 'listModal'].forEach(function (m) { if ($(m)) document.body.appendChild($(m)); });

    function opt(v, t) { return '<option value="' + esc(v) + '">' + esc(t) + '</option>'; }

    // The page's data endpoint: declared by the server, window.ACE_DATA_URL, or the Tabulator table's ajaxURL.
    function pageTables() {
        var out = [];
        if (!(window.Tabulator && Tabulator.findTable)) return out;
        document.querySelectorAll('.tabulator').forEach(function (el) {
            try { var t = Tabulator.findTable(el)[0]; if (t) out.push(t); } catch (e) { /* not a table */ }
        });
        return out;
    }
    function detectSource() {
        var u = SERVER_SOURCE || window.ACE_DATA_URL || null;
        if (!u) {
            pageTables().some(function (t) {
                var a = t.options && t.options.ajaxURL;
                if (typeof a === 'string' && a) { u = a; return true; }
                return false;
            });
        }
        if (!u) return null;
        try { var x = new URL(u, location.href); return x.origin === location.origin ? x.pathname + x.search : null; } catch (e) { return null; }
    }
    // Column headings exactly as the page's table shows them (field -> plain-text title).
    function tableTitles() {
        var map = {};
        titleOrder = [];
        function walk(defs) {
            (defs || []).forEach(function (d) {
                if (d.columns) { walk(d.columns); return; }
                if (!d.field || map[d.field]) return;
                var txt = new DOMParser().parseFromString(String(d.title || ''), 'text/html').body.textContent.replace(/\s+/g, ' ').trim();
                if (txt) { map[d.field] = txt; titleOrder.push(d.field); }
            });
        }
        pageTables().forEach(function (t) { try { walk(t.getColumnDefinitions()); } catch (e) { /* ignore */ } });
        return map;
    }
    // The page's columns in the order its own table (and its "Columns" checkbox menu) shows them.
    function colList() {
        var byKey = {};
        meta.columns.forEach(function (c) { byKey[c.key] = { key: c.key, label: titles[c.key] || c.label }; });
        if (!DYNAMIC || !titleOrder.length) return meta.columns.map(function (c) { return byKey[c.key]; });
        var out = [];
        titleOrder.forEach(function (k) { if (byKey[k]) { out.push(byKey[k]); delete byKey[k]; } });
        meta.columns.forEach(function (c) { if (byKey[c.key]) out.push(byKey[c.key]); });
        return out;
    }

    // Base options (users, operators ...). On analytics pages the page's data is NOT read here, so the
    // form opens instantly; the columns are fetched by loadColumns() only when a Column rule needs them.
    function loadMeta() {
        if (meta) return Promise.resolve(meta);
        var q = '?page=' + encodeURIComponent(PAGE);
        if (DYNAMIC) q += '&lite=1';
        return api(URLS.meta + q).then(function (d) {
            if (!d.ok) throw new Error(d.message || 'Could not load Auto Task Event options');
            meta = d;
            colsReady = !DYNAMIC;
            titles = {};
            return d;
        });
    }
    function loadColumns() {
        if (colsReady) return Promise.resolve();
        if (colsPromise) return colsPromise;
        source = detectSource();
        if (!source) return Promise.reject(new Error('Auto Task Events could not find the data table on this page, so the Column block is not available here. Use the Badges block.'));
        colsPromise = api(URLS.meta + '?page=' + encodeURIComponent(PAGE) + '&source=' + encodeURIComponent(source)).then(function (d) {
            if (!d.ok) throw new Error(d.message || 'Could not read this page\'s columns.');
            meta.columns = d.columns; meta.placeholders = d.placeholders;
            titles = tableTitles();
            colsReady = true;
        }).catch(function (e) { colsPromise = null; throw e; });
        return colsPromise;
    }

    // ---- conditions (one row per condition; "+ Add condition" adds more) ----
    function colItemHtml(key, label, checked) {
        return '<label class="ace-cp-item" data-name="' + esc(String(label).toLowerCase()) + '"><input type="checkbox" class="form-check-input m-0 ace-cp-cb" value="' + esc(key) + '" data-label="' + esc(label) + '"' + (checked ? ' checked' : '') + '> <span>' + esc(label) + '</span></label>';
    }
    // A condition row: a checkbox list of the page's columns (tick one or more), an operator and a value.
    function condRowHtml() {
        var items = colList().map(function (c) { return colItemHtml(c.key, c.label, false); }).join('');
        var ops = meta.operators.map(function (o) { return opt(o.key, o.label); }).join('');
        return '<div class="ace-cond" style="align-items:flex-start">' +
            '<div class="ace-c-col ace-cpick">' +
                '<button type="button" class="form-select form-select-sm ace-cp-btn">Select columns…</button>' +
                '<div class="ace-cp-menu d-none">' +
                    '<input type="text" class="form-control form-control-sm ace-cp-search" placeholder="Search columns…" autocomplete="off">' +
                    '<div class="ace-cp-links d-flex justify-content-between mt-1"><a class="ace-cp-all">Select all</a><a class="ace-cp-none">Clear</a></div>' +
                    '<div class="ace-cp-list">' + items + '</div>' +
                '</div>' +
            '</div>' +
            '<select class="form-select form-select-sm ace-c-op">' + ops + '</select>' +
            '<input type="text" class="form-control form-control-sm ace-c-val" placeholder="value" maxlength="500">' +
            '<button type="button" class="ace-c-del" title="Remove this condition">&times;</button></div>';
    }
    function rowCols(r) {
        return Array.prototype.map.call(r.querySelectorAll('.ace-cp-cb:checked'), function (c) { return { key: c.value, label: c.getAttribute('data-label') }; });
    }
    function syncPickLabel(r) {
        var cols = rowCols(r), b = r.querySelector('.ace-cp-btn');
        b.textContent = !cols.length ? 'Select columns…' : (cols.length === 1 ? cols[0].label : cols.length + ' columns: ' + cols.slice(0, 2).map(function (c) { return c.label; }).join(', ') + (cols.length > 2 ? '…' : ''));
        b.title = cols.map(function (c) { return c.label; }).join(', ');
    }
    function addCond() {
        $('conds').insertAdjacentHTML('beforeend', condRowHtml());
        syncConds();
    }
    function syncConds() {
        var rows = $('conds').querySelectorAll('.ace-cond');
        rows.forEach(function (r) {
            var op = r.querySelector('.ace-c-op').value;
            r.querySelector('.ace-c-val').disabled = (op === 'empty' || op === 'not_empty');
            r.querySelector('.ace-c-del').style.visibility = rows.length > 1 ? 'visible' : 'hidden';
            syncPickLabel(r);
        });
        var multi = rows.length > 1 || Array.prototype.some.call(rows, function (r) { return rowCols(r).length > 1; });
        $('mode').classList.toggle('d-none', !multi);
    }
    function readFreqs() {
        return Array.prototype.map.call($('freqWrap').querySelectorAll('.ace-freq:checked'), function (c) { return c.value; });
    }
    // One condition per ticked column (a row with 3 columns ticked = 3 conditions, joined by the ALL / ANY switch).
    function readConds() {
        var out = [];
        $('conds').querySelectorAll('.ace-cond').forEach(function (r) {
            var op = r.querySelector('.ace-c-op').value, val = r.querySelector('.ace-c-val').value;
            rowCols(r).forEach(function (c) { out.push({ column_key: c.key, label: c.label, operator: op, value: val }); });
        });
        return out;
    }
    function emptyColRows() {
        return Array.prototype.filter.call($('conds').querySelectorAll('.ace-cond'), function (r) { return !rowCols(r).length; }).length;
    }

    // ---- badges block: the badges shown at the top of the page, with their live numbers ----
    function slugOf(t) { return String(t || '').toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, ''); }
    // "Total Sales: $12,345" -> {label:'Total Sales', value:12345}; "CVR 3.2%" -> {label:'CVR', value:3.2}
    function parseBadge(text) {
        var t = String(text || '').replace(/\s+/g, ' ').trim();
        var NUM = '([-+]?\\s*[$\u20b9\u20ac\u00a3]?\\s*[-+]?\\d[\\d,]*(?:\\.\\d+)?)\\s*([kKmMbB%]?)';
        var m = t.match(new RegExp('^(.*?)\\s*[:=]\\s*' + NUM)) || t.match(new RegExp('^(.*?)\\s+' + NUM)) || t.match(new RegExp('^()' + NUM));
        if (!m) return null;
        var n = parseFloat(String(m[2]).replace(/[^0-9.\-]/g, ''));
        if (isNaN(n)) return null;
        var suf = (m[3] || '').toLowerCase();
        if (suf === 'k') n *= 1e3; else if (suf === 'm') n *= 1e6; else if (suf === 'b') n *= 1e9;
        return { label: String(m[1] || '').replace(/[:=\s]+$/, '').trim(), value: Math.round(n * 1e6) / 1e6, text: t };
    }
    function readBadges() {
        var root = document.querySelector('.content-page') || document.body, seen = {}, out = [];
        root.querySelectorAll('.badge').forEach(function (el) {
            if (el.closest('.tabulator, .modal, table, .dropdown-menu, .offcanvas, nav, .navbar-custom, .leftside-menu')) return;
            if (el.classList.contains('d-none')) return;
            var cs = window.getComputedStyle(el);
            if (cs.display === 'none' || cs.visibility === 'hidden') return;
            var p = parseBadge(el.textContent);
            if (!p) return;
            var label = p.label || (el.id ? el.id.replace(/[-_]+/g, ' ').replace(/\s*badge$/i, '').trim() : '');
            var key = el.id || (el.getAttribute('data-metric') ? 'm:' + el.getAttribute('data-metric') : (slugOf(label) ? 'l:' + slugOf(label) : ''));
            if (!key || seen[key]) return;
            seen[key] = 1;
            out.push({ key: key.slice(0, 80), label: (label || key).slice(0, 120), value: p.value, text: p.text });
        });
        return out;
    }
    function fmtNum(n) { return Number(n).toLocaleString(undefined, { maximumFractionDigits: 2 }); }
    function badgeRowHtml(list) {
        var bs = list.length ? list.map(function (b) { return '<option value="' + esc(b.key) + '">' + esc(b.label + '  (now ' + fmtNum(b.value) + ')') + '</option>'; }).join('')
                             : '<option value="">No badges found on this page</option>';
        var ops = BADGE_OPS.map(function (o) { return opt(o[0], o[1]); }).join('');
        return '<div class="ace-cond">' +
            '<select class="form-select form-select-sm ace-c-col">' + bs + '</select>' +
            '<select class="form-select form-select-sm ace-c-op">' + ops + '</select>' +
            '<input type="text" class="form-control form-control-sm ace-c-val" placeholder="number" maxlength="40">' +
            '<button type="button" class="ace-c-del" title="Remove this condition">&times;</button></div>';
    }
    function addBadgeCond() {
        $('bconds').insertAdjacentHTML('beforeend', badgeRowHtml(readBadges()));
        syncBadgeConds();
    }
    function syncBadgeConds() {
        var rows = $('bconds').querySelectorAll('.ace-cond');
        rows.forEach(function (r) { r.querySelector('.ace-c-del').style.visibility = rows.length > 1 ? 'visible' : 'hidden'; });
        $('bmode').classList.toggle('d-none', rows.length < 2);
    }
    function readBadgeConds() {
        return Array.prototype.map.call($('bconds').querySelectorAll('.ace-cond'), function (r) {
            var sel = r.querySelector('.ace-c-col');
            var lbl = sel.selectedIndex >= 0 ? sel.options[sel.selectedIndex].text.replace(/\s+\(now [^)]*\)\s*$/, '') : '';
            return { column_key: sel.value, label: lbl, operator: r.querySelector('.ace-c-op').value, value: r.querySelector('.ace-c-val').value };
        }).filter(function (c) { return c.column_key; });
    }
    function cmpNum(op, a, b) {
        a = parseFloat(a); b = parseFloat(String(b).replace(/[\s,$%\u20b9\u20ac\u00a3]/g, ''));
        if (isNaN(a) || isNaN(b)) return false;
        switch (op) {
            case 'gt': return a > b; case 'lt': return a < b; case 'gte': return a >= b; case 'lte': return a <= b;
            case 'equals': return Math.abs(a - b) < 1e-9; case 'not_equals': return Math.abs(a - b) >= 1e-9;
        }
        return false;
    }
    function evalConds(conds, mode, map) {
        if (!conds.length) return false;
        var hits = conds.map(function (c) { return map.hasOwnProperty(c.column_key) && cmpNum(c.operator, map[c.column_key], c.value); });
        return mode === 'any' ? hits.some(Boolean) : hits.every(Boolean);
    }
    function badgeCheckNow() {
        var out = $('bpreview'), conds = readBadgeConds(), cur = readBadges(), map = {};
        cur.forEach(function (b) { map[b.key] = b.value; });
        if (!conds.length) { out.textContent = 'No badge selected.'; return; }
        var lines = conds.map(function (c) {
            var opLabel = (BADGE_OPS.filter(function (o) { return o[0] === c.operator; })[0] || [0, c.operator])[1];
            var has = map.hasOwnProperty(c.column_key);
            return c.label + ' is now ' + (has ? fmtNum(map[c.column_key]) : '(not on this page)') + ' — needs ' + opLabel + ' ' + (c.value || '?') + ' → ' + (has && cmpNum(c.operator, map[c.column_key], c.value) ? '✔ met' : '✘ not met');
        });
        var all = evalConds(conds, $('bmode').value, map);
        out.innerHTML = lines.map(esc).join('<br>') + '<br><b>' + (all ? 'Condition is met right now.' : 'Condition is not met right now.') + '</b>';
    }

    // Column block <-> Badges block (a rule uses one of them)
    function setKind(k) {
        kind = k === 'badge' ? 'badge' : 'col';
        $('kc').checked = kind === 'col'; $('kb').checked = kind === 'badge';
        $('blockCols').classList.toggle('d-none', kind !== 'col');
        $('blockBadges').classList.toggle('d-none', kind !== 'badge');
        $('secTitle').innerHTML = kind === 'badge' ? '1 &middot; When these badges&hellip;' : '1 &middot; When this column&hellip;';
        $('newSub').textContent = kind === 'badge' ? 'Create a task automatically when a badge at the top of the page meets a condition' : 'Create a one-time task automatically when a column matches';
        var msg = kind === 'badge' ? 'A task is created once per checked period (day / week / month) when the badge condition is met.' : 'A task is created once per matching row in each checked period.';
        $('freqHint').textContent = msg; $('foot').textContent = msg;
    }

    // ---- task titles (+ Add task) ----
    function addTitle(v) {
        $('titles').insertAdjacentHTML('beforeend', '<div class="ace-trow"><input type="text" class="form-control form-control-sm ace-t-input" maxlength="1000" placeholder="e.g. Upload docs for {name}"><button type="button" class="ace-t-del" title="Remove">&times;</button></div>');
        var rows = $('titles').querySelectorAll('.ace-trow');
        if (v) rows[rows.length - 1].querySelector('input').value = v;
        syncTitles();
    }
    function syncTitles() {
        var rows = $('titles').querySelectorAll('.ace-trow');
        rows.forEach(function (r) { r.querySelector('.ace-t-del').style.visibility = rows.length > 1 ? 'visible' : 'hidden'; });
    }
    function readTitles() {
        return Array.prototype.map.call($('titles').querySelectorAll('.ace-t-input'), function (i) { return i.value.trim(); }).filter(Boolean);
    }

    // ---- assignee multi-picker ----
    function buildAssignees() {
        $('alist').innerHTML = meta.users.map(function (u) {
            return '<label class="ace-pick-item" data-name="' + esc(u.name.toLowerCase()) + '"><input type="checkbox" class="form-check-input m-0 ace-a" value="' + u.id + '"> <span>' + esc(u.name) + '</span></label>';
        }).join('');
        $('asearch').value = '';
        syncAssign();
    }
    function readAssignees() {
        return Array.prototype.map.call($('alist').querySelectorAll('.ace-a:checked'), function (c) { return parseInt(c.value, 10); });
    }
    function syncAssign() {
        var n = readAssignees().length;
        $('acount').textContent = n ? n + ' selected' : 'None selected';
        $('splitWrap').classList.toggle('d-none', n < 2);
    }
    function readTFreq() {
        return Array.prototype.map.call($('newModal').querySelectorAll('.ace-tfreq:checked'), function (c) { return c.value; });
    }
    var OPT_KEYS = ['l2', 'training_link', 'video_link', 'form_link', 'form_report_link', 'checklist_link', 'pl', 'process'];

    function fillForm() {
        buildAssignees();
        // Like the task manager: only task admins can pick another assignor; everyone else is the assignor.
        $('assignor').innerHTML = meta.users.filter(function (u) { return meta.is_admin || u.id === meta.me; }).map(function (u) { return opt(u.id, u.name); }).join('');
        $('assignor').disabled = !meta.is_admin;
        if (meta.me) $('assignor').value = String(meta.me);
        var modes = opt('fixed', 'A specific person');
        if (PAGE === 'users') modes += opt('row_user', 'The person in the row');
        $('amode').innerHTML = modes;
        syncForm();
    }

    function syncForm() {
        $('aUserWrap').style.display = $('amode').value === 'fixed' ? '' : 'none';
    }

    function showErr(msg) { var e = $('err'); e.textContent = msg || ''; e.classList.toggle('d-none', !msg); }

    function resetForm() {
        ['name', 'desc', 'group'].forEach(function (k) { $(k).value = ''; });
        $('titles').innerHTML = ''; addTitle('');
        OPT_KEYS.forEach(function (k) { $('o_' + k).value = ''; });
        $('etc').value = 10; $('ca').checked = false; $('sj').checked = true;
        $('t1').checked = true; $('t2').checked = false; $('t3').checked = false; $('t4').checked = false;
        $('more').classList.add('d-none'); $('moreIcon').className = 'ri-arrow-down-s-line';
        // Auto-fill the link with the page this rule is being created on (still editable).
        $('link').value = location.origin + location.pathname + location.search;
        $('fd').checked = true; $('fw').checked = false; $('fm').checked = false;
        $('prio').value = 'normal'; $('due').value = 1; $('preview').textContent = ''; $('bpreview').textContent = '';
        $('mode').value = 'all'; $('bmode').value = 'all';
    }

    // Build the condition rows of the active block (from a saved rule when editing).
    function buildBadgeBlock(f) {
        $('bconds').innerHTML = '';
        var list = f && f.trigger_type === 'badge' && f.conditions && f.conditions.length ? f.conditions : [{}];
        var cur = readBadges();
        list.forEach(function (c) {
            $('bconds').insertAdjacentHTML('beforeend', badgeRowHtml(cur));
            var row = $('bconds').lastElementChild, sel = row.querySelector('.ace-c-col');
            if (c.column_key) {
                if (!Array.prototype.some.call(sel.options, function (x) { return x.value === c.column_key; })) {
                    if (sel.options.length === 1 && !sel.options[0].value) sel.innerHTML = '';
                    sel.insertAdjacentHTML('beforeend', opt(c.column_key, (c.label || c.column_key) + '  (not on this page now)'));
                }
                sel.value = c.column_key;
            }
            if (c.operator) row.querySelector('.ace-c-op').value = c.operator;
            row.querySelector('.ace-c-val').value = c.value || '';
        });
        if (f && f.trigger_type === 'badge') $('bmode').value = f.match_mode || 'all';
        syncBadgeConds();
    }
    function buildColumnBlock(f) {
        $('conds').innerHTML = '<div class="ace-hint py-2">Loading this page\'s columns…</div>';
        loadColumns().then(function () {
            $('conds').innerHTML = '';
            var saved = f && f.trigger_type !== 'badge' && f.conditions && f.conditions.length ? f.conditions : [];
            // Conditions with the same operator + value come back as one row with several columns ticked.
            var groups = [];
            saved.forEach(function (c) {
                var g = groups.filter(function (x) { return x.operator === c.operator && (x.value || '') === (c.value || ''); })[0];
                if (!g) { g = { operator: c.operator, value: c.value || '', cols: [] }; groups.push(g); }
                g.cols.push(c);
            });
            if (!groups.length) groups = [{ cols: [] }];
            groups.forEach(function (g) {
                addCond();
                var row = $('conds').lastElementChild, list = row.querySelector('.ace-cp-list');
                g.cols.forEach(function (c) {
                    var cb = Array.prototype.filter.call(list.querySelectorAll('.ace-cp-cb'), function (x) { return x.value === c.column_key; })[0];
                    if (!cb) { list.insertAdjacentHTML('beforeend', colItemHtml(c.column_key, c.label || c.column_key, true)); } else { cb.checked = true; }
                });
                if (g.operator) row.querySelector('.ace-c-op').value = g.operator;
                row.querySelector('.ace-c-val').value = g.value || '';
            });
            if (f && f.trigger_type !== 'badge') $('mode').value = f.match_mode || 'all';
            syncConds();
        }).catch(function (e) {
            $('conds').innerHTML = '<div class="text-danger small py-2">' + esc(e.message || 'Could not load columns.') + '</div>';
        });
    }

    // Fill the form from a saved rule (edit).
    function applyForm(f) {
        var o = f.task_options || {};
        $('name').value = f.name || '';
        var fr = f.frequencies && f.frequencies.length ? f.frequencies : ['daily'];
        $('fd').checked = fr.indexOf('daily') !== -1; $('fw').checked = fr.indexOf('weekly') !== -1; $('fm').checked = fr.indexOf('monthly') !== -1;

        $('titles').innerHTML = '';
        (o.titles && o.titles.length ? o.titles : [f.task_title || '']).forEach(function (t) { addTitle(t); });
        $('desc').value = f.task_description || '';
        $('group').value = f.task_group || '';
        $('link').value = f.task_link || '';

        if (Array.prototype.some.call($('amode').options, function (x) { return x.value === f.assignee_mode; })) $('amode').value = f.assignee_mode;
        var ids = o.assignee_ids && o.assignee_ids.length ? o.assignee_ids : (f.assignee_id ? [f.assignee_id] : []);
        $('alist').querySelectorAll('.ace-a').forEach(function (c) { c.checked = ids.indexOf(parseInt(c.value, 10)) !== -1; });
        (o.split ? $('ss') : $('sj')).checked = true;
        if (o.assignor_id && Array.prototype.some.call($('assignor').options, function (x) { return x.value === String(o.assignor_id); })) $('assignor').value = String(o.assignor_id);
        $('etc').value = o.etc_minutes != null ? o.etc_minutes : 10;
        $('ca').checked = !!o.is_corrective_action;
        var tf = o.task_frequency && o.task_frequency.length ? o.task_frequency : ['one_time'];
        $('t1').checked = tf.indexOf('one_time') !== -1; $('t2').checked = tf.indexOf('daily') !== -1;
        $('t3').checked = tf.indexOf('weekly') !== -1; $('t4').checked = tf.indexOf('monthly') !== -1;
        $('prio').value = f.priority || 'normal';
        $('due').value = f.due_in_days != null ? f.due_in_days : 1;
        var anyMore = false;
        OPT_KEYS.forEach(function (k) { $('o_' + k).value = o[k] || ''; if (o[k]) anyMore = true; });
        $('more').classList.toggle('d-none', !anyMore); $('moreIcon').className = anyMore ? 'ri-arrow-up-s-line' : 'ri-arrow-down-s-line';
        syncAssign(); syncForm();
    }

    // rule = a saved rule from the list (edit) or nothing (create)
    function openNew(rule) {
        editing = rule && rule.id ? rule : null;
        loadMeta().then(function () {
            fillForm();
            resetForm();
            if (editing && editing.form) applyForm(editing.form);
            // Badges are only on analytics pages; the Column block is the default.
            $('kindWrap').classList.toggle('d-none', !DYNAMIC);
            var f = editing && editing.form ? editing.form : null;
            setKind(DYNAMIC && f && f.trigger_type === 'badge' ? 'badge' : 'col');
            buildBadgeBlock(f);
            buildColumnBlock(f);
            $('newTitle').innerHTML = editing ? '&#9998; Edit ATE' : '&#9889; Auto Task Event';
            $('save').textContent = editing ? 'Save changes' : 'Create ATE';
            showErr('');
            syncForm();
            modal('newModal').show();
        }).catch(function (e) { alert(e.message); });
    }

    function save() {
        showErr('');
        var body = {
            page: PAGE, trigger: kind === 'badge' ? 'badge' : 'column', source: DYNAMIC && kind === 'col' ? source : null, page_label: (document.title || '').replace(/\s+/g, ' ').trim().slice(0, 160),
            name: $('name').value.trim(),
            conditions: kind === 'badge' ? readBadgeConds() : readConds(), match_mode: (kind === 'badge' ? $('bmode') : $('mode')).value, frequencies: readFreqs(),
            task_title: readTitles()[0] || '', task_description: $('desc').value, task_group: $('group').value.trim(), task_link: $('link').value.trim(),
            priority: $('prio').value, due_in_days: parseInt($('due').value, 10) || 0,
            assignee_mode: $('amode').value, assignee_id: null
        };
        var assignees = $('amode').value === 'fixed' ? readAssignees() : [];
        body.assignee_id = assignees[0] || null;
        var opts = {
            titles: readTitles(), assignee_ids: assignees, split: assignees.length > 1 && $('ss').checked,
            assignor_id: $('assignor').value || null, etc_minutes: parseInt($('etc').value, 10),
            is_corrective_action: $('ca').checked, task_frequency: readTFreq()
        };
        if (isNaN(opts.etc_minutes)) opts.etc_minutes = 10;
        OPT_KEYS.forEach(function (k) { opts[k] = $('o_' + k).value.trim(); });
        body.task_options = opts;
        if (!body.name) return showErr('Give the rule a name.');
        if (kind === 'col' && emptyColRows()) return showErr('Tick at least one column in every condition.');
        if (!body.conditions.length) return showErr(kind === 'badge' ? 'Pick a badge for the condition (this page has none to pick).' : 'Add at least one condition.');
        if (body.conditions.length > 60) return showErr('Too many conditions (max 60). Untick some columns.');
        if (!body.frequencies.length) return showErr('Tick at least one of Daily, Weekly or Monthly.');
        for (var i = 0; i < body.conditions.length; i++) {
            var c = body.conditions[i];
            if (kind === 'badge' && c.operator !== 'empty' && isNaN(parseFloat(String(c.value).replace(/[\s,$%\u20b9\u20ac\u00a3]/g, '')))) return showErr('Enter a number for condition ' + (i + 1) + '.');
            if (kind !== 'badge' && c.operator !== 'empty' && c.operator !== 'not_empty' && !String(c.value).trim()) return showErr('Enter a value for condition ' + (i + 1) + ' (or use “is empty” / “is not empty”).');
        }
        if (!body.task_title) return showErr('Enter the task.');
        if ($('amode').value === 'fixed' && !assignees.length) return showErr('Select at least one assignee.');
        if (!opts.assignor_id) return showErr('Select the assignor.');
        if (!opts.task_frequency.length) return showErr('Pick at least one task frequency (One Time, Daily, Weekly or Monthly).');
        var btn = $('save'); btn.disabled = true;
        (editing ? post(URLS.base + '/' + editing.id, body, 'PUT') : post(URLS.store, body)).then(function (d) {
            if (!d._ok || !d.ok) {
                var m = d.message || 'Could not save the rule.';
                if (d.errors) m = Object.keys(d.errors).map(function (k) { return d.errors[k][0]; }).join(' ');
                return showErr(m);
            }
            lastFetch = 0; // the badge watcher re-reads the rules soon
            modal('newModal').hide();
            openList();
        }).catch(function () { showErr('Could not save the rule.'); }).finally(function () { btn.disabled = false; });
    }

    function preview() {
        var out = $('preview');
        if (!readConds().length) { out.textContent = 'Tick at least one column first.'; return; }
        out.textContent = 'Checking…';
        post(URLS.preview, { page: PAGE, source: DYNAMIC ? source : null, conditions: readConds(), match_mode: $('mode').value }).then(function (d) {
            if (!d.ok) { out.textContent = d.message || 'Could not preview.'; return; }
            out.textContent = d.matches + ' of ' + d.total + ' rows match now' + (d.sample.length ? ' — ' + d.sample.join(', ') + (d.matches > d.sample.length ? '…' : '') : '') + '.';
        }).catch(function () { out.textContent = 'Could not preview.'; });
    }

    // ---- details ----
    function renderList() {
        var activeOnly = $('activeOnly').checked;
        var mineOnly = $('mineOnly').checked;
        var list = rules.filter(function (r) { return (!activeOnly || r.is_active) && (!mineOnly || r.mine); });
        if (!list.length) {
            $('list').innerHTML = '<div class="text-center text-muted py-4">' + (rules.length ? 'No Auto Task Events match these filters. Untick “Show active only” / “Show only mine” to see more.' : 'No Auto Task Events for this page yet.') + '</div>';
            return;
        }
        $('list').innerHTML = list.map(function (r) {
            var runs = r.recent.length ? '<ul class="ace-runs">' + r.recent.map(function (x) {
                return '<li class="' + (x.status === 'failed' ? 'ace-fail' : '') + '">' + esc(x.label) + ' — ' +
                    (x.status === 'failed' ? 'failed: ' + esc(x.error || '') : (x.task_id ? 'task #' + x.task_id : 'scheduled')) + ' <span class="text-muted">(' + esc(x.at) + ')</span></li>';
            }).join('') + '</ul>' : '';
            var actions = r.mine ? '<div class="d-flex gap-1 flex-wrap">' +
                '<button class="btn btn-sm btn-outline-dark" data-act="edit" data-id="' + r.id + '"><i class="ri-edit-line"></i> Edit</button>' +
                '<button class="btn btn-sm btn-outline-secondary" data-act="toggle" data-id="' + r.id + '">' + (r.is_active ? 'Pause' : 'Resume') + '</button>' +
                (r.trigger_type === 'badge' ? '' : '<button class="btn btn-sm btn-outline-primary" data-act="run" data-id="' + r.id + '">Run now</button>') +
                '<button class="btn btn-sm btn-outline-danger" data-act="del" data-id="' + r.id + '"><i class="ri-delete-bin-line"></i></button></div>'
                : '<span class="ace-hint">View only &middot; owned by ' + esc(r.owner || 'someone else') + '</span>';
            return '<div class="ace-rule' + (r.is_active ? '' : ' is-paused') + '"><div class="ace-rule-head">' +
                '<div><span class="ace-rule-name">' + esc(r.name) + '</span> <span class="ace-badge ' + (r.is_active ? 'ace-badge-on' : 'ace-badge-off') + '">' + (r.is_active ? 'ACTIVE' : 'PAUSED') + '</span> ' +
                (r.trigger_type === 'badge' ? '<span class="ace-badge ace-badge-type">BADGES</span> ' : '') + (r.mine ? '<span class="ace-badge ace-badge-type">MINE</span>' : '') + '</div>' + actions + '</div>' +
                (r.condition ? '<div class="ace-kv"><b>When:</b> ' + esc(r.condition) + ' <span class="text-muted">· checked ' + esc(r.frequency) + (r.trigger_type === 'badge' ? ' while the page is open' : '') + '</span></div>' : '') +
                '<div class="ace-kv"><b>Creates:</b> ' + esc(r.task_title) + (r.task_group ? ' <span class="ace-badge ace-badge-type">' + esc(r.task_group) + '</span>' : '') + ' <span class="text-muted">· ' + esc(r.priority) + ' · due in ' + r.due_in_days + 'd · assigned to ' + esc(r.assignee) + '</span></div>' +
                '<div class="ace-kv"><b>Task:</b> ' + esc(r.task_frequency || 'One Time') + (r.task_extras ? ' <span class="text-muted">· ' + esc(r.task_extras) + '</span>' : '') + '</div>' +
                (r.task_link ? '<div class="ace-kv"><b>Link:</b> <a href="' + esc(r.task_link) + '" target="_blank" rel="noopener">' + esc(r.task_link) + '</a></div>' : '') +
                '<div class="ace-kv"><b>Tasks created:</b> ' + r.tasks_created + (r.failed ? ' <span class="text-danger">(' + r.failed + ' failed)</span>' : '') +
                ' <span class="text-muted">· last run ' + esc(r.last_run_at || 'never') + ' · created ' + esc(r.created_at || '') + (r.created_by ? ' by ' + esc(r.created_by) : '') + '</span></div>' + runs + '</div>';
        }).join('');
    }

    function openList() {
        $('list').innerHTML = '<div class="text-center text-muted py-4">Loading…</div>';
        modal('listModal').show();
        api(URLS.index + '?page=' + encodeURIComponent(PAGE)).then(function (d) {
            if (!d.ok) { $('list').innerHTML = '<div class="alert alert-danger mb-0">' + esc(d.message || 'Could not load Auto Task Events.') + '</div>'; return; }
            rules = d.rules;
            renderList();
        }).catch(function () { $('list').innerHTML = '<div class="alert alert-danger mb-0">Could not load Auto Task Events.</div>'; });
    }

    // ---- wiring ----
    $('newBtn').addEventListener('click', function () { openNew(null); });
    $('listBtn').addEventListener('click', openList);
    $('save').addEventListener('click', save);
    $('previewBtn').addEventListener('click', preview);
    $('addCond').addEventListener('click', addCond);
    $('conds').addEventListener('change', syncConds);
    $('conds').addEventListener('click', function (e) {
        var btn = e.target.closest('.ace-cp-btn');
        if (btn) {
            var menu = btn.parentNode.querySelector('.ace-cp-menu'), opening = menu.classList.contains('d-none');
            $('conds').querySelectorAll('.ace-cp-menu').forEach(function (m) { m.classList.add('d-none'); });
            menu.classList.toggle('d-none', !opening);
            if (opening) { var si = menu.querySelector('.ace-cp-search'); si.value = ''; si.dispatchEvent(new Event('input')); si.focus(); }
            return;
        }
        var all = e.target.closest('.ace-cp-all, .ace-cp-none');
        if (all) {
            var on = all.classList.contains('ace-cp-all');
            all.closest('.ace-cp-menu').querySelectorAll('.ace-cp-item').forEach(function (l) { if (l.style.display !== 'none') l.querySelector('input').checked = on; });
            syncConds();
        }
    });
    $('conds').addEventListener('input', function (e) {
        if (!e.target.classList.contains('ace-cp-search')) return;
        var q = e.target.value.trim().toLowerCase();
        e.target.closest('.ace-cp-menu').querySelectorAll('.ace-cp-item').forEach(function (l) { l.style.display = !q || l.getAttribute('data-name').indexOf(q) !== -1 ? '' : 'none'; });
    });
    document.addEventListener('click', function (e) {
        if (e.target.closest('.ace-cpick')) return;
        var c = $('conds'); if (c) c.querySelectorAll('.ace-cp-menu').forEach(function (m) { m.classList.add('d-none'); });
    });
    $('addBadge').addEventListener('click', addBadgeCond);
    $('bpreviewBtn').addEventListener('click', badgeCheckNow);
    $('bconds').addEventListener('change', syncBadgeConds);
    $('bconds').addEventListener('click', function (e) {
        var d = e.target.closest('.ace-c-del'); if (!d) return;
        d.closest('.ace-cond').remove();
        syncBadgeConds();
    });
    $('kindWrap').addEventListener('change', function (e) { if (e.target && e.target.name === UID + '_kind') setKind(e.target.value); });
    $('conds').addEventListener('click', function (e) {
        var d = e.target.closest('.ace-c-del'); if (!d) return;
        d.closest('.ace-cond').remove();
        syncConds();
    });
    $('activeOnly').addEventListener('change', renderList);
    $('mineOnly').addEventListener('change', renderList);
    $('amode').addEventListener('change', syncForm);
    $('addTitle').addEventListener('click', function () { addTitle(''); });
    $('titles').addEventListener('click', function (e) {
        var d = e.target.closest('.ace-t-del'); if (!d) return;
        d.closest('.ace-trow').remove(); syncTitles();
    });
    $('alist').addEventListener('change', syncAssign);
    $('asearch').addEventListener('input', function () {
        var q = this.value.trim().toLowerCase();
        $('alist').querySelectorAll('.ace-pick-item').forEach(function (l) { l.style.display = !q || l.getAttribute('data-name').indexOf(q) !== -1 ? '' : 'none'; });
    });
    $('aall').addEventListener('click', function () {
        var vis = Array.prototype.filter.call($('alist').querySelectorAll('.ace-pick-item'), function (l) { return l.style.display !== 'none'; });
        var all = vis.every(function (l) { return l.querySelector('input').checked; });
        vis.forEach(function (l) { l.querySelector('input').checked = !all; });
        syncAssign();
    });
    $('moreBtn').addEventListener('click', function () {
        var hidden = $('more').classList.toggle('d-none');
        $('moreIcon').className = hidden ? 'ri-arrow-down-s-line' : 'ri-arrow-up-s-line';
    });
    $('list').addEventListener('click', function (e) {
        var b = e.target.closest('button[data-act]'); if (!b) return;
        var id = b.getAttribute('data-id'), act = b.getAttribute('data-act');
        if (act === 'edit') {
            var rule = rules.filter(function (r) { return String(r.id) === id; })[0];
            if (!rule) return;
            var lm = $('listModal');
            lm.addEventListener('hidden.bs.modal', function once() { lm.removeEventListener('hidden.bs.modal', once); openNew(rule); });
            modal('listModal').hide();
            return;
        }
        if (act === 'del' && !confirm('Delete this Auto Task Event? Tasks it already created stay.')) return;
        b.disabled = true;
        var req = act === 'del' ? post(URLS.base + '/' + id, {}, 'DELETE') : post(URLS.base + '/' + id + '/' + act, {});
        req.then(function (d) {
            if (!d.ok) { alert(d.message || 'Action failed.'); return; }
            lastFetch = 0;
            if (act === 'run') alert(d.created + ' task(s) created.');
            openList();
        }).catch(function () { alert('Action failed.'); }).finally(function () { b.disabled = false; });
    });

    // ---- badge rules: checked here, in the browser, while this page is open ----
    // The page's real badges are read; when a rule's condition holds on two reads in a row (so the
    // initial "0" placeholders while the table loads can't trigger it), the server creates the task
    // once per day / week / month.
    if (DYNAMIC) {
        var pending = [], firedHere = {}, streak = {}, started = Date.now(), busy = false;

        function tablesLoaded() {
            if (Date.now() - started > 45000) return true; // some pages legitimately have no rows
            return pageTables().every(function (t) {
                try { return typeof t.getDataCount !== 'function' || t.getDataCount() > 0; } catch (e) { return true; }
            });
        }
        function fireRule(rule, vals) {
            firedHere[rule.id] = true;
            post(URLS.base + '/' + rule.id + '/badge-fire', { page: PAGE, values: vals.map(function (b) { return { key: b.key, label: b.label, value: b.value }; }) })
                .catch(function () { delete firedHere[rule.id]; streak[rule.id] = 0; });
        }
        function tick() {
            if (document.hidden || busy) return;
            var go = function () {
                if (!pending.length || !tablesLoaded()) return;
                var vals = readBadges(), map = {};
                vals.forEach(function (b) { map[b.key] = b.value; });
                pending.forEach(function (rule) {
                    if (firedHere[rule.id]) return;
                    if (evalConds(rule.conditions, rule.match_mode, map)) {
                        streak[rule.id] = (streak[rule.id] || 0) + 1;
                        if (streak[rule.id] >= 2) fireRule(rule, vals);
                    } else {
                        streak[rule.id] = 0;
                    }
                });
            };
            if (Date.now() - lastFetch > 300000) { // (re)load the rules that still need a task, every 5 min
                busy = true; lastFetch = Date.now();
                api(URLS.badgeRules + '?page=' + encodeURIComponent(PAGE)).then(function (d) {
                    pending = d && d.ok ? d.rules : [];
                }).catch(function () { /* try again later */ }).finally(function () { busy = false; go(); });
            } else {
                go();
            }
        }
        setTimeout(function loop() { tick(); setTimeout(loop, 8000); }, 8000);
    }
})();
</script>
