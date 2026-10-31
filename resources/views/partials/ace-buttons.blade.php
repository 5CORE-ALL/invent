{{-- ACE (Automated Chat Event) buttons + modals.
     Include on a page with: @include('partials.ace-buttons', ['acePage' => 'users'])   (users | active_channels)
       - "Auto Chat Event": create a rule that makes a one-time task from a column condition and/or a chat keyword.
       - "ACE Details": the rules for this page (active ones by default) and the tasks they created. --}}
@php
    $aceCanManage = \App\Support\SuperAdminAccess::isTaskAdmin(auth()->user());
    $aceUid = 'ace_'.preg_replace('/[^a-z0-9]/i', '', (string) ($acePage ?? 'page'));
@endphp

<style>
    .ace-btn { border: 0; border-radius: 999px; font-weight: 700; font-size: .8rem; padding: 6px 14px; color: #fff; white-space: nowrap; transition: transform .15s ease, box-shadow .15s ease; }
    .ace-btn:hover { transform: translateY(-1px); color: #fff; }
    .ace-btn-new { background: linear-gradient(135deg, #7c3aed, #3b82f6); box-shadow: 0 4px 12px rgba(99, 102, 241, .35); }
    .ace-btn-list { background: linear-gradient(135deg, #0f766e, #14b8a6); box-shadow: 0 4px 12px rgba(15, 118, 110, .3); }
    .ace-modal .modal-header { background: linear-gradient(120deg, #6d28d9, #3b82f6); color: #fff; border: 0; }
    .ace-modal .btn-close { filter: invert(1); }
    .ace-modal .modal-content { border: 0; border-radius: 16px; overflow: hidden; }
    .ace-sec { background: #f8faff; border: 1px solid #e5eaf7; border-radius: 12px; padding: 12px 14px; margin-bottom: 12px; }
    .ace-sec-title { font-weight: 800; color: #3730a3; font-size: .85rem; letter-spacing: .03em; text-transform: uppercase; margin-bottom: 8px; }
    .ace-chip { display: inline-block; margin: 2px 4px 2px 0; padding: 2px 9px; font-size: .72rem; font-weight: 700; color: #4338ca; background: #eef2ff; border: 0; border-radius: 999px; cursor: pointer; }
    .ace-chip:hover { background: #4f46e5; color: #fff; }
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
    @if($aceCanManage)
        <button type="button" class="ace-btn ace-btn-new" id="{{ $aceUid }}_newBtn" title="Create an automated chat event (one-time task)">
            <i class="ri-flashlight-fill me-1"></i> Auto Chat Event
        </button>
    @endif
    <button type="button" class="ace-btn ace-btn-list" id="{{ $aceUid }}_listBtn" title="Show ACE rules for this page">
        <i class="ri-list-check-2 me-1"></i> ACE Details
    </button>
</div>

{{-- Create modal --}}
<div class="modal fade ace-modal" id="{{ $aceUid }}_newModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-0">&#9889; Auto Chat Event</h5>
                    <small class="opacity-75">Create a one-time task automatically when a column matches and/or the chat asks for it</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Rule name</label>
                    <input type="text" class="form-control" id="{{ $aceUid }}_name" maxlength="120" placeholder="e.g. Chase missing docs">
                </div>

                <div class="ace-sec">
                    <div class="ace-sec-title">1 &middot; When this column&hellip;</div>
                    <div class="row g-2">
                        <div class="col-md-5"><select class="form-select form-select-sm" id="{{ $aceUid }}_col"></select></div>
                        <div class="col-md-3"><select class="form-select form-select-sm" id="{{ $aceUid }}_op"></select></div>
                        <div class="col-md-4"><input type="text" class="form-control form-control-sm" id="{{ $aceUid }}_val" placeholder="value" maxlength="500"></div>
                    </div>
                    <div class="d-flex align-items-center gap-2 mt-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="{{ $aceUid }}_previewBtn">Preview matches</button>
                        <span class="ace-hint" id="{{ $aceUid }}_preview"></span>
                    </div>
                </div>

                <div class="ace-sec">
                    <div class="ace-sec-title">2 &middot; &hellip;and/or when the chat says</div>
                    <div class="row g-2">
                        <div class="col-md-5"><select class="form-select form-select-sm" id="{{ $aceUid }}_chan"></select></div>
                        <div class="col-md-7"><input type="text" class="form-control form-control-sm" id="{{ $aceUid }}_kw" maxlength="255" placeholder="keyword(s), comma separated, e.g. docs, urgent"></div>
                    </div>
                    <div class="ace-hint mt-2" id="{{ $aceUid }}_trigHint"></div>
                </div>

                <div class="ace-sec">
                    <div class="ace-sec-title">3 &middot; Create this one-time task</div>
                    <div class="mb-2">
                        <label class="form-label fw-semibold mb-1">Task title</label>
                        <input type="text" class="form-control form-control-sm" id="{{ $aceUid }}_title" maxlength="1000" placeholder="e.g. Upload docs for {name}">
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-semibold mb-1">Description</label>
                        <textarea class="form-control form-control-sm" id="{{ $aceUid }}_desc" rows="2" maxlength="5000" placeholder="Optional. Placeholders work here too."></textarea>
                    </div>
                    <div class="mb-2">
                        <span class="ace-hint">Insert a field into the focused box:</span>
                        <div id="{{ $aceUid }}_chips"></div>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-4"><label class="form-label mb-1 small fw-semibold">Group</label><input type="text" class="form-control form-control-sm" id="{{ $aceUid }}_group" maxlength="255"></div>
                        <div class="col-md-4"><label class="form-label mb-1 small fw-semibold">Priority</label>
                            <select class="form-select form-select-sm" id="{{ $aceUid }}_prio"><option value="low">Low</option><option value="normal" selected>Normal</option><option value="high">High</option></select></div>
                        <div class="col-md-4"><label class="form-label mb-1 small fw-semibold">Due in (days)</label><input type="number" class="form-control form-control-sm" id="{{ $aceUid }}_due" min="0" max="365" value="1"></div>
                        <div class="col-md-6"><label class="form-label mb-1 small fw-semibold">Assign to</label><select class="form-select form-select-sm" id="{{ $aceUid }}_amode"></select></div>
                        <div class="col-md-6" id="{{ $aceUid }}_aUserWrap"><label class="form-label mb-1 small fw-semibold">Person</label><select class="form-select form-select-sm" id="{{ $aceUid }}_auser"></select></div>
                    </div>
                </div>
                <div class="text-danger small d-none" id="{{ $aceUid }}_err"></div>
            </div>
            <div class="modal-footer">
                <small class="text-muted me-auto">Each row / message creates its task only once.</small>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="{{ $aceUid }}_save">Create ACE</button>
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
                    <h5 class="modal-title mb-0">&#9889; ACE Details</h5>
                    <small class="opacity-75">Automated Chat Events for this page</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="{{ $aceUid }}_activeOnly" checked>
                    <label class="form-check-label" for="{{ $aceUid }}_activeOnly">Show active only</label>
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
                 store: @json(route('ace.store')), base: @json(url('/ace')) };
    var CAN_MANAGE = @json($aceCanManage);
    var meta = null, rules = [], lastFocus = null;

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

    function loadMeta() {
        if (meta) return Promise.resolve(meta);
        return api(URLS.meta + '?page=' + encodeURIComponent(PAGE)).then(function (d) {
            if (!d.ok) throw new Error(d.message || 'Could not load ACE options');
            meta = d;
            return d;
        });
    }

    function noneCol() { return opt('', '— no column condition —'); }
    function fillForm() {
        $('col').innerHTML = noneCol() + meta.columns.map(function (c) { return opt(c.key, c.label); }).join('');
        $('op').innerHTML = meta.operators.map(function (o) { return opt(o.key, o.label); }).join('');
        $('chan').innerHTML = opt('', 'Any channel') + meta.channels.map(function (c) { return opt(c.id, '#' + c.name); }).join('');
        $('auser').innerHTML = meta.users.map(function (u) { return opt(u.id, u.name); }).join('');
        var modes = opt('fixed', 'A specific person');
        if (PAGE === 'users') modes += opt('row_user', 'The person in the row');
        modes += opt('chat_sender', 'The chat message sender (chat trigger)');
        $('amode').innerHTML = modes;
        $('chips').innerHTML = meta.placeholders.map(function (p) { return '<button type="button" class="ace-chip" data-ph="' + esc(p) + '">{' + esc(p) + '}</button>'; }).join('');
        syncForm();
    }

    function syncForm() {
        var hasCol = !!$('col').value, op = $('op').value;
        $('op').disabled = !hasCol;
        $('val').disabled = !hasCol || op === 'empty' || op === 'not_empty';
        $('previewBtn').disabled = !hasCol;
        $('aUserWrap').style.display = $('amode').value === 'fixed' ? '' : 'none';
        var hasKw = $('kw').value.trim() !== '';
        $('trigHint').textContent = hasCol && hasKw ? 'Fires when a matching chat message is sent: one task per row that matches the column condition.'
            : hasKw ? 'Fires when a matching chat message is sent: one task for that message.'
            : hasCol ? 'Checked every 5 minutes: one task per row that matches the column condition.'
            : 'Pick a column condition, a chat keyword, or both.';
    }

    function showErr(msg) { var e = $('err'); e.textContent = msg || ''; e.classList.toggle('d-none', !msg); }

    function openNew() {
        loadMeta().then(function () {
            fillForm();
            ['name', 'val', 'kw', 'title', 'desc', 'group'].forEach(function (k) { $(k).value = ''; });
            $('prio').value = 'normal'; $('due').value = 1; $('col').value = ''; $('preview').textContent = '';
            showErr('');
            syncForm();
            modal('newModal').show();
        }).catch(function (e) { alert(e.message); });
    }

    function save() {
        showErr('');
        var body = {
            page: PAGE, name: $('name').value.trim(),
            column_key: $('col').value || null, operator: $('col').value ? $('op').value : null, value: $('val').value,
            chat_channel_id: $('chan').value || null, chat_keyword: $('kw').value.trim(),
            task_title: $('title').value.trim(), task_description: $('desc').value, task_group: $('group').value.trim(),
            priority: $('prio').value, due_in_days: parseInt($('due').value, 10) || 0,
            assignee_mode: $('amode').value, assignee_id: $('amode').value === 'fixed' ? ($('auser').value || null) : null
        };
        if (!body.name) return showErr('Give the rule a name.');
        if (!body.task_title) return showErr('Enter the task title.');
        var btn = $('save'); btn.disabled = true;
        post(URLS.store, body).then(function (d) {
            if (!d._ok || !d.ok) {
                var m = d.message || 'Could not create the rule.';
                if (d.errors) m = Object.keys(d.errors).map(function (k) { return d.errors[k][0]; }).join(' ');
                return showErr(m);
            }
            modal('newModal').hide();
            openList();
        }).catch(function () { showErr('Could not create the rule.'); }).finally(function () { btn.disabled = false; });
    }

    function preview() {
        var out = $('preview'); out.textContent = 'Checking…';
        post(URLS.preview, { page: PAGE, column_key: $('col').value, operator: $('op').value, value: $('val').value }).then(function (d) {
            if (!d.ok) { out.textContent = d.message || 'Could not preview.'; return; }
            out.textContent = d.matches + ' of ' + d.total + ' rows match now' + (d.sample.length ? ' — ' + d.sample.join(', ') + (d.matches > d.sample.length ? '…' : '') : '') + '.';
        }).catch(function () { out.textContent = 'Could not preview.'; });
    }

    // ---- details ----
    var TYPE_LABEL = { column: 'Column', chat: 'Chat', chat_column: 'Chat + Column' };
    function renderList() {
        var activeOnly = $('activeOnly').checked;
        var list = rules.filter(function (r) { return !activeOnly || r.is_active; });
        if (!list.length) {
            $('list').innerHTML = '<div class="text-center text-muted py-4">' + (rules.length ? 'No active ACE rules. Untick “Show active only” to see paused ones.' : 'No ACE rules for this page yet.') + '</div>';
            return;
        }
        var canManage = CAN_MANAGE;
        $('list').innerHTML = list.map(function (r) {
            var runs = r.recent.length ? '<ul class="ace-runs">' + r.recent.map(function (x) {
                return '<li class="' + (x.status === 'failed' ? 'ace-fail' : '') + '">' + esc(x.label) + ' — ' +
                    (x.status === 'failed' ? 'failed: ' + esc(x.error || '') : (x.task_id ? 'task #' + x.task_id : 'queued')) + ' <span class="text-muted">(' + esc(x.at) + ')</span></li>';
            }).join('') + '</ul>' : '';
            var actions = canManage ? '<div class="d-flex gap-1 flex-wrap">' +
                '<button class="btn btn-sm btn-outline-secondary" data-act="toggle" data-id="' + r.id + '">' + (r.is_active ? 'Pause' : 'Resume') + '</button>' +
                (r.trigger_type === 'column' ? '<button class="btn btn-sm btn-outline-primary" data-act="run" data-id="' + r.id + '">Run now</button>' : '') +
                '<button class="btn btn-sm btn-outline-danger" data-act="del" data-id="' + r.id + '"><i class="ri-delete-bin-line"></i></button></div>' : '';
            return '<div class="ace-rule' + (r.is_active ? '' : ' is-paused') + '"><div class="ace-rule-head">' +
                '<div><span class="ace-rule-name">' + esc(r.name) + '</span> <span class="ace-badge ' + (r.is_active ? 'ace-badge-on' : 'ace-badge-off') + '">' + (r.is_active ? 'ACTIVE' : 'PAUSED') + '</span> ' +
                '<span class="ace-badge ace-badge-type">' + esc(TYPE_LABEL[r.trigger_type] || r.trigger_type) + '</span></div>' + actions + '</div>' +
                (r.condition ? '<div class="ace-kv"><b>When:</b> ' + esc(r.condition) + '</div>' : '') +
                (r.chat ? '<div class="ace-kv"><b>Chat:</b> ' + esc(r.chat) + '</div>' : '') +
                '<div class="ace-kv"><b>Creates:</b> ' + esc(r.task_title) + ' <span class="text-muted">· ' + esc(r.priority) + ' · due in ' + r.due_in_days + 'd · assigned to ' + esc(r.assignee) + '</span></div>' +
                '<div class="ace-kv"><b>Tasks created:</b> ' + r.tasks_created + (r.failed ? ' <span class="text-danger">(' + r.failed + ' failed)</span>' : '') +
                ' <span class="text-muted">· last run ' + esc(r.last_run_at || 'never') + ' · created ' + esc(r.created_at || '') + (r.created_by ? ' by ' + esc(r.created_by) : '') + '</span></div>' + runs + '</div>';
        }).join('');
    }

    function openList() {
        $('list').innerHTML = '<div class="text-center text-muted py-4">Loading…</div>';
        modal('listModal').show();
        loadMeta().catch(function () {}).then(function () {
            return api(URLS.index + '?page=' + encodeURIComponent(PAGE));
        }).then(function (d) {
            if (!d.ok) { $('list').innerHTML = '<div class="alert alert-danger mb-0">' + esc(d.message || 'Could not load ACE rules.') + '</div>'; return; }
            rules = d.rules;
            renderList();
        }).catch(function () { $('list').innerHTML = '<div class="alert alert-danger mb-0">Could not load ACE rules.</div>'; });
    }

    // ---- wiring ----
    if ($('newBtn')) $('newBtn').addEventListener('click', openNew);
    $('listBtn').addEventListener('click', openList);
    $('save').addEventListener('click', save);
    $('previewBtn').addEventListener('click', preview);
    $('activeOnly').addEventListener('change', renderList);
    ['col', 'op', 'amode'].forEach(function (k) { $(k).addEventListener('change', syncForm); });
    $('kw').addEventListener('input', syncForm);
    ['title', 'desc'].forEach(function (k) { $(k).addEventListener('focus', function () { lastFocus = this; }); });
    $('chips').addEventListener('click', function (e) {
        var b = e.target.closest('.ace-chip'); if (!b) return;
        var el = lastFocus || $('title');
        var at = el.selectionStart == null ? el.value.length : el.selectionStart;
        el.value = el.value.slice(0, at) + '{' + b.getAttribute('data-ph') + '}' + el.value.slice(el.selectionEnd == null ? at : el.selectionEnd);
        el.focus();
    });
    $('list').addEventListener('click', function (e) {
        var b = e.target.closest('button[data-act]'); if (!b) return;
        var id = b.getAttribute('data-id'), act = b.getAttribute('data-act');
        if (act === 'del' && !confirm('Delete this ACE rule? Tasks it already created stay.')) return;
        b.disabled = true;
        var req = act === 'del' ? post(URLS.base + '/' + id, {}, 'DELETE') : post(URLS.base + '/' + id + '/' + act, {});
        req.then(function (d) {
            if (!d.ok) { alert(d.message || 'Action failed.'); return; }
            if (act === 'run') alert(d.created + ' task(s) created.');
            openList();
        }).catch(function () { alert('Action failed.'); }).finally(function () { b.disabled = false; });
    });
})();
</script>
