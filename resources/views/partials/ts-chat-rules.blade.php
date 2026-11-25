{{-- Chat Rules modal for /tasks/summary (directors only). Opened by #ts-chat-rule-btn. --}}
<style>
    .ts-chat-rule-btn {
        display: inline-flex; align-items: center; gap: 6px;
        background: #16a34a; color: #fff; border: 0; border-radius: 8px;
        padding: 5px 12px; font-size: 0.82rem; font-weight: 600; line-height: 1.4;
        box-shadow: 0 1px 2px rgba(22, 163, 74, 0.35);
        transition: background .15s ease, transform .15s ease;
    }
    .ts-chat-rule-btn:hover { background: #15803d; color: #fff; transform: translateY(-1px); }
    .ts-chat-rule-btn:focus-visible { outline: 3px solid rgba(22, 163, 74, 0.4); outline-offset: 2px; }

    #tsChatRuleModal .modal-content { border: 0; border-radius: 16px; overflow: hidden; }
    #tsChatRuleModal .modal-header { background: linear-gradient(135deg, #14532d, #16a34a); color: #fff; border: 0; padding: 1rem 1.25rem; }
    #tsChatRuleModal .modal-title { font-weight: 700; display: flex; align-items: center; gap: 8px; }
    #tsChatRuleModal .modal-sub { font-size: .78rem; opacity: .85; margin-top: 2px; }
    #tsChatRuleModal .btn-close { filter: invert(1) grayscale(1) brightness(2); }
    #tsChatRuleModal .modal-body { background: #f8fafc; padding: 1.1rem 1.25rem; }
    .crx-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 12px 14px; margin-bottom: 10px; display: flex; gap: 12px; align-items: flex-start; }
    .crx-card.is-off { opacity: .6; }
    .crx-card h6 { margin: 0 0 3px; font-weight: 700; }
    .crx-cond { font-size: .84rem; color: #334155; }
    .crx-cond b { color: #15803d; }
    .crx-chips { display: flex; flex-wrap: wrap; gap: 5px; margin-top: 6px; }
    .crx-chip { font-size: .7rem; font-weight: 600; padding: 2px 9px; border-radius: 99px; background: #ecfdf5; color: #166534; border: 1px solid #bbf7d0; }
    .crx-chip.is-gray { background: #f1f5f9; color: #475569; border-color: #e2e8f0; }
    .crx-actions { margin-left: auto; display: flex; gap: 6px; flex-wrap: wrap; justify-content: flex-end; }
    .crx-label { font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #475569; margin-bottom: 4px; display: block; }
    .crx-hint { font-size: .75rem; color: #64748b; margin-top: 3px; }
    .crx-section { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 14px; margin-bottom: 12px; }
    .crx-users { max-height: 170px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 10px; padding: 6px 10px; margin-top: 8px; background: #f8fafc; }
    .crx-users label { display: flex; align-items: center; gap: 8px; font-size: .84rem; padding: 2px 0; margin: 0; cursor: pointer; }
    .crx-ph { border: 1px dashed #86efac; background: #f0fdf4; color: #166534; border-radius: 8px; font-size: .74rem; font-weight: 600; padding: 2px 8px; cursor: pointer; }
    .crx-ph:hover { background: #dcfce7; }
    .crx-preview { background: #0f172a; color: #e2e8f0; border-radius: 12px; padding: 12px 14px; font-size: .84rem; }
    .crx-preview .crx-bubble { background: #16a34a; color: #fff; border-radius: 14px 14px 14px 4px; padding: 8px 12px; margin: 8px 0; white-space: pre-wrap; max-width: 520px; }
    .crx-preview ul { list-style: none; margin: 8px 0 0; padding: 0; max-height: 150px; overflow-y: auto; }
    .crx-preview li { display: flex; justify-content: space-between; gap: 10px; padding: 2px 0; border-bottom: 1px solid rgba(255,255,255,.08); }
    .crx-status { font-size: .8rem; border-radius: 10px; padding: 8px 12px; margin-bottom: 10px; }
    .crx-status.is-ok { background: #dcfce7; color: #166534; }
    .crx-status.is-err { background: #fee2e2; color: #991b1b; }
    .crx-empty { text-align: center; color: #64748b; padding: 30px 10px; }
</style>

<div class="modal fade" id="tsChatRuleModal" tabindex="-1" aria-labelledby="tsChatRuleTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="tsChatRuleTitle"><i class="ri-robot-2-line" aria-hidden="true"></i> Chat Rules</h5>
                    <div class="modal-sub" id="crxSub">The 5 Core Bot messages a member when a Task Summary number matches your rule.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="crxStatus" class="crx-status d-none" role="status"></div>

                {{-- List view --}}
                <div id="crxListView">
                    <div class="d-flex align-items-center mb-3">
                        <div class="text-muted small">Each rule checks the numbers on this page and sends the bot message once per person, at the set time.</div>
                        <button type="button" class="btn btn-sm btn-success ms-auto" id="crxNewBtn"><i class="ri-add-line"></i> New rule</button>
                    </div>
                    <div id="crxList"><div class="crx-empty">Loading…</div></div>
                </div>

                {{-- Form view --}}
                <form id="crxForm" class="d-none" autocomplete="off" novalidate>
                    <input type="hidden" id="crxId" value="">
                    <div class="crx-section">
                        <label class="crx-label" for="crxName">Rule name</label>
                        <input type="text" class="form-control" id="crxName" maxlength="120" placeholder="e.g. Overdue reminder">
                    </div>

                    <div class="crx-section">
                        <span class="crx-label">When this column</span>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <select class="form-select" id="crxMetric" aria-label="Column"></select>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" id="crxOperator" aria-label="Condition"></select>
                            </div>
                            <div class="col-md-3">
                                <input type="number" step="any" class="form-control" id="crxThreshold" value="1" aria-label="Number">
                            </div>
                        </div>
                        <div class="crx-hint" id="crxMetricHint"></div>
                    </div>

                    <div class="crx-section">
                        <span class="crx-label">Send to</span>
                        <div class="d-flex gap-3">
                            <label class="form-check m-0"><input class="form-check-input" type="radio" name="crxAudience" value="all" checked> <span class="form-check-label">All members</span></label>
                            <label class="form-check m-0"><input class="form-check-input" type="radio" name="crxAudience" value="users"> <span class="form-check-label">Selected members</span></label>
                        </div>
                        <div id="crxUsersWrap" class="d-none">
                            <input type="search" class="form-control form-control-sm mt-2" id="crxUserSearch" placeholder="Search members…">
                            <div class="crx-users" id="crxUsers"></div>
                            <div class="crx-hint"><span id="crxUserCount">0</span> selected</div>
                        </div>
                    </div>

                    <div class="crx-section">
                        <div class="d-flex align-items-center mb-1">
                            <label class="crx-label m-0" for="crxMessage">Bot message</label>
                            <button type="button" class="btn btn-link btn-sm ms-auto p-0" id="crxSuggest">Use suggested message</button>
                        </div>
                        <textarea class="form-control" id="crxMessage" rows="4" maxlength="1500" placeholder="Write what the bot should say"></textarea>
                        <div class="d-flex flex-wrap gap-1 mt-2">
                            <span class="crx-hint me-1">Insert:</span>
                            <button type="button" class="crx-ph" data-ph="{first_name}">first name</button>
                            <button type="button" class="crx-ph" data-ph="{name}">full name</button>
                            <button type="button" class="crx-ph" data-ph="{value}">their number</button>
                            <button type="button" class="crx-ph" data-ph="{threshold}">rule number</button>
                            <button type="button" class="crx-ph" data-ph="{metric}">column</button>
                        </div>
                    </div>

                    <div class="crx-section">
                        <span class="crx-label">When to send</span>
                        <div class="row g-2 align-items-center">
                            <div class="col-md-3">
                                <input type="time" class="form-control" id="crxTime" value="10:00" aria-label="Send time">
                                <div class="crx-hint" id="crxTz"></div>
                            </div>
                            <div class="col-md-5">
                                <select class="form-select" id="crxFrequency" aria-label="How often">
                                    <option value="daily">At most once a day per person</option>
                                    <option value="weekly">At most once a week per person</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-check m-0"><input class="form-check-input" type="checkbox" id="crxWeekdays" checked> <span class="form-check-label">Weekdays only</span></label>
                            </div>
                        </div>
                        <label class="form-check form-switch mt-3 mb-0"><input class="form-check-input" type="checkbox" role="switch" id="crxActive" checked> <span class="form-check-label">Rule is on</span></label>
                    </div>

                    <div class="crx-section">
                        <div class="d-flex align-items-center">
                            <span class="crx-label m-0">Preview</span>
                            <button type="button" class="btn btn-sm btn-outline-success ms-auto" id="crxPreviewBtn">Who would get this?</button>
                        </div>
                        <div class="crx-preview mt-2 d-none" id="crxPreview"></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer" id="crxFooter">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal" id="crxCloseBtn">Close</button>
                <button type="button" class="btn btn-light d-none" id="crxBackBtn">Back</button>
                <button type="button" class="btn btn-success d-none" id="crxSaveBtn">Save rule</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var URLS = {
        index: @json(route('chat.rules.index')),
        store: @json(route('chat.rules.store')),
        preview: @json(route('chat.rules.preview')),
        base: @json(url('/chat-rules'))
    };
    var modalEl = document.getElementById('tsChatRuleModal');
    if (!modalEl) return;
    var state = { rules: [], metrics: [], operators: [], users: [], tz: '', loaded: false, editing: null, lastSuggested: '' };
    var $ = function (id) { return document.getElementById(id); };
    var esc = function (v) { return String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); };
    var csrf = function () { var m = document.querySelector('meta[name="csrf-token"]'); return m ? m.getAttribute('content') : ''; };

    function api(method, url, body) {
        return fetch(url, {
            method: method,
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
            body: body ? JSON.stringify(body) : undefined
        }).then(function (r) {
            return r.json().catch(function () { return { success: false, message: 'Unexpected response (' + r.status + ').' }; })
                .then(function (j) { if (!r.ok && !j.message) j.message = 'Request failed (' + r.status + ').'; return j; });
        });
    }

    function status(msg, ok) {
        var el = $('crxStatus');
        if (!msg) { el.classList.add('d-none'); return; }
        el.textContent = msg;
        el.className = 'crx-status ' + (ok ? 'is-ok' : 'is-err');
    }

    function showView(view) {
        var form = view === 'form';
        $('crxListView').classList.toggle('d-none', form);
        $('crxForm').classList.toggle('d-none', !form);
        $('crxBackBtn').classList.toggle('d-none', !form);
        $('crxSaveBtn').classList.toggle('d-none', !form);
        $('crxCloseBtn').classList.toggle('d-none', form);
        $('crxSub').textContent = form
            ? (state.editing ? 'Edit rule' : 'New rule') + ' — pick a column, set the number, write the message.'
            : 'The 5 Core Bot messages a member when a Task Summary number matches your rule.';
        if (!form) status('');
    }

    function metricLabel(key) {
        var m = state.metrics.filter(function (x) { return x.key === key; })[0];
        return m ? m.label : key;
    }

    function renderList() {
        var list = $('crxList');
        if (!state.rules.length) {
            list.innerHTML = '<div class="crx-empty"><i class="ri-chat-settings-line" style="font-size:2rem"></i><div class="mt-2">No chat rules yet.<br>Press <b>New rule</b> to message people automatically.</div></div>';
            return;
        }
        list.innerHTML = state.rules.map(function (r) {
            var who = r.audience === 'users' ? (r.user_ids.length + ' selected member' + (r.user_ids.length === 1 ? '' : 's')) : 'All members';
            var freq = r.frequency === 'weekly' ? 'weekly' : 'daily';
            return '<div class="crx-card' + (r.is_active ? '' : ' is-off') + '" data-id="' + r.id + '">' +
                '<div class="form-check form-switch mt-1"><input class="form-check-input crx-toggle" type="checkbox" role="switch" ' + (r.is_active ? 'checked' : '') + ' aria-label="Rule on or off"></div>' +
                '<div style="min-width:0"><h6>' + esc(r.name) + '</h6>' +
                '<div class="crx-cond">' + esc(r.metric_label) + ' ' + esc(r.operator_label) + ' <b>' + esc(r.threshold) + '</b></div>' +
                '<div class="crx-chips"><span class="crx-chip">' + esc(who) + '</span>' +
                '<span class="crx-chip is-gray">' + esc(r.send_time) + ' ' + esc(state.tz) + (r.weekdays_only ? ' · weekdays' : '') + '</span>' +
                '<span class="crx-chip is-gray">' + freq + '</span>' +
                (r.last_run_at ? '<span class="crx-chip is-gray">Last run ' + esc(r.last_run_at) + ' · sent ' + esc(r.last_sent_count) + '</span>' : '<span class="crx-chip is-gray">Never run</span>') +
                '</div></div>' +
                '<div class="crx-actions">' +
                '<button type="button" class="btn btn-sm btn-outline-success crx-run" title="Send now to everyone who matches">Run now</button>' +
                '<button type="button" class="btn btn-sm btn-light crx-edit">Edit</button>' +
                '<button type="button" class="btn btn-sm btn-light text-danger crx-del" title="Delete rule"><i class="ri-delete-bin-line"></i></button>' +
                '</div></div>';
        }).join('');
    }

    function load() {
        return api('GET', URLS.index).then(function (j) {
            if (!j.success) { status(j.message || 'Could not load the rules.', false); $('crxList').innerHTML = ''; return; }
            state.rules = j.rules; state.metrics = j.metrics; state.operators = j.operators; state.users = j.users; state.tz = j.timezone || ''; state.loaded = true;
            renderList();
        }).catch(function () { status('Could not load the rules.', false); });
    }

    function fillSelects() {
        var groups = {};
        state.metrics.forEach(function (m) { (groups[m.group] = groups[m.group] || []).push(m); });
        $('crxMetric').innerHTML = Object.keys(groups).map(function (g) {
            return '<optgroup label="' + esc(g) + '">' + groups[g].map(function (m) { return '<option value="' + esc(m.key) + '">' + esc(m.label) + '</option>'; }).join('') + '</optgroup>';
        }).join('');
        $('crxOperator').innerHTML = state.operators.map(function (o) { return '<option value="' + esc(o.key) + '">' + esc(o.label) + '</option>'; }).join('');
        $('crxTz').textContent = state.tz;
    }

    function metricInfo() { return state.metrics.filter(function (x) { return x.key === $('crxMetric').value; })[0] || {}; }

    function syncMetricHint(applyMessage) {
        var info = metricInfo();
        $('crxMetricHint').textContent = info.hint || '';
        var current = $('crxMessage').value.trim();
        if (applyMessage && (current === '' || current === state.lastSuggested)) {
            $('crxMessage').value = info.default_message || '';
        }
        state.lastSuggested = info.default_message || '';
    }

    function renderUsers() {
        var q = ($('crxUserSearch').value || '').toLowerCase();
        var chosen = {};
        (state.chosenUsers || []).forEach(function (id) { chosen[id] = true; });
        $('crxUsers').innerHTML = state.users.filter(function (u) { return !q || u.name.toLowerCase().indexOf(q) !== -1; }).map(function (u) {
            return '<label><input type="checkbox" class="crx-user" value="' + u.id + '"' + (chosen[u.id] ? ' checked' : '') + '> ' + esc(u.name) + '</label>';
        }).join('') || '<div class="crx-hint">No members found.</div>';
        $('crxUserCount').textContent = (state.chosenUsers || []).length;
    }

    function audience() {
        var el = document.querySelector('input[name="crxAudience"]:checked');
        return el ? el.value : 'all';
    }

    function openForm(rule) {
        state.editing = rule || null;
        state.chosenUsers = rule ? (rule.user_ids || []).slice() : [];
        fillSelects();
        $('crxId').value = rule ? rule.id : '';
        $('crxName').value = rule ? rule.name : '';
        $('crxMetric').value = rule ? rule.metric : (state.metrics[0] ? state.metrics[0].key : '');
        $('crxOperator').value = rule ? rule.operator : '>=';
        $('crxThreshold').value = rule ? rule.threshold : 1;
        $('crxTime').value = rule ? rule.send_time : '10:00';
        $('crxFrequency').value = rule ? rule.frequency : 'daily';
        $('crxWeekdays').checked = rule ? !!rule.weekdays_only : true;
        $('crxActive').checked = rule ? !!rule.is_active : true;
        document.querySelector('input[name="crxAudience"][value="' + (rule ? rule.audience : 'all') + '"]').checked = true;
        $('crxUsersWrap').classList.toggle('d-none', audience() !== 'users');
        $('crxUserSearch').value = '';
        $('crxMessage').value = rule ? rule.message : '';
        state.lastSuggested = '';
        syncMetricHint(!rule);
        renderUsers();
        $('crxPreview').classList.add('d-none');
        status('');
        showView('form');
    }

    function collect() {
        return {
            id: $('crxId').value ? Number($('crxId').value) : null,
            name: $('crxName').value.trim(),
            metric: $('crxMetric').value,
            operator: $('crxOperator').value,
            threshold: $('crxThreshold').value,
            audience: audience(),
            user_ids: state.chosenUsers || [],
            message: $('crxMessage').value.trim(),
            send_time: $('crxTime').value,
            frequency: $('crxFrequency').value,
            weekdays_only: $('crxWeekdays').checked,
            is_active: $('crxActive').checked
        };
    }

    function preview() {
        var box = $('crxPreview');
        var btn = $('crxPreviewBtn');
        btn.disabled = true; btn.textContent = 'Checking…';
        api('POST', URLS.preview, collect()).then(function (j) {
            btn.disabled = false; btn.textContent = 'Who would get this?';
            box.classList.remove('d-none');
            if (!j.success) { box.innerHTML = '<span style="color:#fca5a5">' + esc(j.message || 'Could not preview.') + '</span>'; return; }
            if (!j.matched) { box.innerHTML = 'Nobody matches this rule right now.'; return; }
            var first = j.people[0];
            box.innerHTML = '<b>' + j.matched + '</b> member' + (j.matched === 1 ? '' : 's') + ' match' + (j.already ? ' · ' + j.already + ' already got it in this period' : '') +
                '<div class="crx-bubble">' + esc(first.message) + '</div>' +
                '<ul>' + j.people.map(function (p) { return '<li><span>' + esc(p.name) + '</span><span>' + esc(p.value) + (p.status === 'already_sent' ? ' · already sent' : '') + '</span></li>'; }).join('') + '</ul>' +
                (j.people_total > j.people.length ? '<div class="mt-1" style="opacity:.7">Showing ' + j.people.length + ' of ' + j.people_total + '.</div>' : '');
        }).catch(function () { btn.disabled = false; btn.textContent = 'Who would get this?'; box.classList.remove('d-none'); box.textContent = 'Could not preview.'; });
    }

    function save() {
        var btn = $('crxSaveBtn');
        btn.disabled = true;
        api('POST', URLS.store, collect()).then(function (j) {
            btn.disabled = false;
            if (!j.success) { status(j.message || 'Could not save the rule.', false); $('crxForm').scrollIntoView({ block: 'start' }); modalEl.querySelector('.modal-body').scrollTop = 0; return; }
            return load().then(function () { showView('list'); status('Rule saved.', true); });
        }).catch(function () { btn.disabled = false; status('Could not save the rule.', false); });
    }

    function ruleById(id) { return state.rules.filter(function (r) { return r.id === id; })[0]; }

    modalEl.addEventListener('show.bs.modal', function () {
        showView('list');
        load();
    });
    $('crxNewBtn').addEventListener('click', function () { openForm(null); });
    $('crxBackBtn').addEventListener('click', function () { showView('list'); });
    $('crxSaveBtn').addEventListener('click', save);
    $('crxPreviewBtn').addEventListener('click', preview);
    $('crxMetric').addEventListener('change', function () { syncMetricHint(true); });
    $('crxSuggest').addEventListener('click', function () { $('crxMessage').value = metricInfo().default_message || ''; state.lastSuggested = $('crxMessage').value; });
    document.querySelectorAll('input[name="crxAudience"]').forEach(function (r) {
        r.addEventListener('change', function () { $('crxUsersWrap').classList.toggle('d-none', audience() !== 'users'); });
    });
    $('crxUserSearch').addEventListener('input', renderUsers);
    $('crxUsers').addEventListener('change', function (e) {
        if (!e.target.classList.contains('crx-user')) return;
        var id = Number(e.target.value);
        var set = (state.chosenUsers || []).filter(function (x) { return x !== id; });
        if (e.target.checked) set.push(id);
        state.chosenUsers = set;
        $('crxUserCount').textContent = set.length;
    });
    document.querySelectorAll('.crx-ph').forEach(function (b) {
        b.addEventListener('click', function () {
            var ta = $('crxMessage');
            var s = ta.selectionStart == null ? ta.value.length : ta.selectionStart;
            var e = ta.selectionEnd == null ? s : ta.selectionEnd;
            ta.value = ta.value.slice(0, s) + b.dataset.ph + ta.value.slice(e);
            ta.focus(); ta.selectionStart = ta.selectionEnd = s + b.dataset.ph.length;
        });
    });

    $('crxList').addEventListener('click', function (e) {
        var card = e.target.closest('.crx-card');
        if (!card) return;
        var id = Number(card.dataset.id);
        var rule = ruleById(id);
        if (!rule) return;

        if (e.target.closest('.crx-edit')) { openForm(rule); return; }

        if (e.target.closest('.crx-del')) {
            if (!window.confirm('Delete the rule "' + rule.name + '"? Messages already sent stay in chat.')) return;
            api('DELETE', URLS.base + '/' + id).then(function (j) {
                if (j.success) { status('Rule deleted.', true); load(); } else { status(j.message || 'Could not delete.', false); }
            });
            return;
        }

        if (e.target.closest('.crx-run')) {
            if (!window.confirm('Send "' + rule.name + '" now to everyone who matches?\nPeople who already got it in this period will not get it again.')) return;
            var btn = e.target.closest('.crx-run');
            btn.disabled = true; btn.textContent = 'Sending…';
            api('POST', URLS.base + '/' + id + '/run').then(function (j) {
                btn.disabled = false; btn.textContent = 'Run now';
                if (!j.success) { status(j.message || 'Could not run the rule.', false); return; }
                status('Sent to ' + j.sent + ' member' + (j.sent === 1 ? '' : 's') + ' (' + j.matched + ' matched' + (j.already ? ', ' + j.already + ' already had it' : '') + ').', true);
                load();
            }).catch(function () { btn.disabled = false; btn.textContent = 'Run now'; status('Could not run the rule.', false); });
        }
    });

    $('crxList').addEventListener('change', function (e) {
        if (!e.target.classList.contains('crx-toggle')) return;
        var card = e.target.closest('.crx-card');
        var id = Number(card.dataset.id);
        api('POST', URLS.base + '/' + id + '/toggle').then(function (j) {
            if (!j.success) { status(j.message || 'Could not change the rule.', false); e.target.checked = !e.target.checked; return; }
            var rule = ruleById(id); if (rule) rule.is_active = j.is_active;
            card.classList.toggle('is-off', !j.is_active);
        }).catch(function () { e.target.checked = !e.target.checked; });
    });

    var openBtn = document.getElementById('ts-chat-rule-btn');
    if (openBtn) {
        openBtn.addEventListener('click', function () {
            if (window.bootstrap && bootstrap.Modal) {
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            } else if (window.jQuery) {
                window.jQuery(modalEl).modal('show');
            }
        });
    }
})();
</script>
