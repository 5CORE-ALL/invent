@extends('layouts.vertical', ['title' => 'Dynamic Tasks', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        .dt-lead { color: #64748b; font-size: 13px; margin: 2px 0 0; }
        .dt-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 16px; margin-bottom: 12px; }
        .dt-title { font-weight: 700; color: #0f172a; }
        .dt-body { white-space: pre-wrap; font-size: 13px; color: #1e293b; margin-top: 4px; }
        .dt-meta { font-size: 11.5px; color: #64748b; }
        .dt-chip { display: inline-block; padding: 2px 9px; margin: 2px 4px 2px 0; border-radius: 999px; background: #e0f2fe; color: #075985; font-size: 12px; font-weight: 600; cursor: pointer; border: 0; }
        .dt-chip:hover { background: #bae6fd; }
        .dt-users { max-height: 220px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 8px; padding: 6px 10px; }
        .dt-users label { display: block; font-size: 13px; margin: 0; padding: 2px 0; cursor: pointer; }
        .dt-table th { font-size: 12px; text-transform: uppercase; letter-spacing: .03em; color: #475569; white-space: nowrap; }
        .dt-table td { font-size: 13px; vertical-align: top; }
        .dt-empty { text-align: center; color: #94a3b8; padding: 28px 0; font-size: 13px; }
    </style>
@endsection

@section('content')
    <div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center my-3 gap-2">
            <div>
                <h4 class="mb-0">Dynamic Tasks</h4>
                <p class="dt-lead">
                    Pre-saved tasks that are created for pre-saved people from data / events on different pages.
                    Every task is assigned by <b>{{ $assignorName }}</b> ({{ $assignorEmail }}).
                </p>
            </div>
            <button type="button" class="btn btn-primary" id="dtNewRuleBtn"><i class="ri-add-line me-1"></i>New task rule</button>
        </div>

        <ul class="nav nav-tabs mb-3" id="dtTabs">
            <li class="nav-item"><button class="nav-link active" data-tab="rules" type="button">Task rules</button></li>
            <li class="nav-item"><button class="nav-link" data-tab="log" type="button">Created tasks</button></li>
        </ul>

        <div data-pane="rules">
            <div class="table-responsive">
                <table class="table table-sm align-middle dt-table">
                    <thead>
                        <tr>
                            <th>Rule</th><th>Page / event</th><th>Condition</th><th>Assign to</th>
                            <th>Task</th><th>Frequency</th><th>Created</th><th>Status</th><th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="dtRulesBody"><tr><td colspan="9" class="dt-empty">Loading…</td></tr></tbody>
                </table>
            </div>
        </div>
        <div data-pane="log" class="d-none"><div id="dtLog" class="dt-empty">Loading…</div></div>
    </div>

    <div class="modal fade" id="dtRuleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="dtRuleModalTitle">Task rule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger d-none" id="dtRuleError"></div>
                    <input type="hidden" id="dtRuleId">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Rule name</label>
                        <input type="text" class="form-control" id="dtName" maxlength="150" placeholder="e.g. Clear overdue tasks">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Page / event (when to create the task)</label>
                        <select class="form-select" id="dtEvent"></select>
                        <div class="form-text" id="dtEventHint"></div>
                    </div>

                    <div class="mb-3" id="dtConditionWrap">
                        <label class="form-label fw-semibold">Condition <span class="text-muted fw-normal">(optional, leave metric empty to always create)</span></label>
                        <div class="row g-2">
                            <div class="col-md-6"><select class="form-select" id="dtMetric"></select></div>
                            <div class="col-md-3">
                                <select class="form-select" id="dtOperator">
                                    @foreach ($operators as $op)
                                        <option value="{{ $op }}">{{ $op }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3"><input type="number" step="any" class="form-control" id="dtThreshold" placeholder="value"></div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Task title</label>
                        <input type="text" class="form-control" id="dtTitle" maxlength="190" placeholder="e.g. Clear your {overdue_count} overdue tasks">
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-semibold">Task description</label>
                        <textarea class="form-control" id="dtDescription" rows="4" maxlength="5000" placeholder="What should the person do? Click a placeholder below to insert live data."></textarea>
                    </div>
                    <div class="mb-3">
                        <div class="dt-meta mb-1">Insert live data (replaced with real values when the task is created):</div>
                        <div id="dtChips"></div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Group</label>
                            <input type="text" class="form-control" id="dtGroup" maxlength="120" placeholder="optional">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Priority</label>
                            <select class="form-select" id="dtPriority">
                                @foreach ($priorities as $k => $label)
                                    <option value="{{ $k }}" @selected($k === 'normal')>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">ETC (minutes)</label>
                            <input type="number" min="1" class="form-control" id="dtEta" value="10">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">TID in (days)</label>
                            <input type="number" min="0" max="365" class="form-control" id="dtTid" value="0">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Page link <span class="text-muted fw-normal">(optional, shown on the task)</span></label>
                            <input type="text" class="form-control" id="dtLink" maxlength="2048" placeholder="https://… or the page URL the task is about">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Assign to (pre-saved users)</label>
                        <input type="text" class="form-control form-control-sm mb-1" id="dtUserSearch" placeholder="Search people…">
                        <div class="dt-users" id="dtUsers">
                            @foreach ($users as $u)
                                <label data-search="{{ strtolower($u->name.' '.$u->email) }}">
                                    <input type="checkbox" class="dt-user-cb" value="{{ $u->id }}"> {{ $u->name }} <span class="text-muted">({{ $u->email }})</span>
                                </label>
                            @endforeach
                        </div>
                        <div class="dt-meta mt-1">Page-data events (tasks, attendance) create a separate task per person, based only on their own data.</div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">How often</label>
                            <select class="form-select" id="dtFrequency">
                                @foreach ($frequencies as $k => $label)
                                    <option value="{{ $k }}">{{ str_replace('Only once per person', 'Create only once per person', str_replace('At most once', 'Create at most once', $label)) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="dtSkipOpen" checked>
                                    <label class="form-check-label" for="dtSkipOpen">Don't create another while the previous one is still open</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="dtActive" checked>
                                    <label class="form-check-label" for="dtActive">Rule is active</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="dt-meta mt-3">Assignor: <b>{{ $assignorName }}</b>. TID = today + the days above; the due date is TID + 5 days (same as the Tasks page).</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="dtSaveBtn">Save rule</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="dtPreviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Preview (no task is created)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="dtPreviewBody"></div>
            </div>
        </div>
    </div>
@endsection

@section('script-after-vite')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const EVENTS = @json($events);
    const ASSIGNOR = @json($assignorName);
    const URLS = {
        rules: @json(route('dynamic-tasks.rules')),
        log: @json(route('dynamic-tasks.log')),
        base: @json(url('dynamic-tasks')),
    };
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const $ = (id) => document.getElementById(id);
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

    async function api(url, method, body) {
        const res = await fetch(url, {
            method: method || 'GET',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            body: body ? JSON.stringify(body) : undefined,
            credentials: 'same-origin',
        });
        let json = {};
        try { json = await res.json(); } catch (e) {}
        if (!res.ok) {
            const first = json.errors ? Object.values(json.errors)[0][0] : null;
            throw new Error(first || json.message || ('Request failed (' + res.status + ')'));
        }
        return json;
    }

    const loaders = { rules: loadRules, log: loadLog };
    function showTab(name) {
        document.querySelectorAll('#dtTabs .nav-link').forEach((b) => b.classList.toggle('active', b.dataset.tab === name));
        document.querySelectorAll('[data-pane]').forEach((p) => p.classList.toggle('d-none', p.dataset.pane !== name));
        loaders[name]();
    }
    document.querySelectorAll('#dtTabs .nav-link').forEach((b) => b.addEventListener('click', () => showTab(b.dataset.tab)));

    let rulesCache = [];
    async function loadRules() {
        const body = $('dtRulesBody');
        try {
            const { data } = await api(URLS.rules);
            rulesCache = data;
            if (!data.length) { body.innerHTML = '<tr><td colspan="9" class="dt-empty">No rules yet. Click "New task rule".</td></tr>'; return; }
            body.innerHTML = data.map((r) => {
                const cond = r.metric ? `${esc(r.metric_label || r.metric)} ${esc(r.operator)} ${esc(r.threshold)}` : '<span class="text-muted">Always</span>';
                const names = r.recipient_names.slice(0, 4).map(esc).join(', ') + (r.recipient_names.length > 4 ? ` +${r.recipient_names.length - 4} more` : '');
                return `<tr>
                    <td><div class="fw-semibold">${esc(r.name)}</div></td>
                    <td>${esc(r.page)}<div class="dt-meta">${esc(r.event_label)}</div></td>
                    <td>${cond}</td>
                    <td>${names}</td>
                    <td>${esc(r.title)}<div class="dt-meta">${esc(r.priority)} · ${esc(r.eta_minutes)} min${r.task_group ? ' · ' + esc(r.task_group) : ''}</div></td>
                    <td>${r.manual ? 'On demand' : esc(r.frequency)}${r.skip_if_open ? '<div class="dt-meta">skips while open</div>' : ''}</td>
                    <td>${r.creations_count}<div class="dt-meta">${r.last_run_at ? 'Last run ' + esc(r.last_run_at) : 'Never run'}</div></td>
                    <td><span class="badge ${r.is_active ? 'bg-success' : 'bg-secondary'}">${r.is_active ? 'Active' : 'Paused'}</span></td>
                    <td class="text-end text-nowrap">
                        <button class="btn btn-sm btn-light" data-act="preview" data-id="${r.id}">Preview</button>
                        <button class="btn btn-sm btn-success" data-act="run" data-id="${r.id}">${r.manual ? 'Create now' : 'Run now'}</button>
                        <button class="btn btn-sm btn-light" data-act="edit" data-id="${r.id}">Edit</button>
                        <button class="btn btn-sm btn-light" data-act="toggle" data-id="${r.id}">${r.is_active ? 'Pause' : 'Resume'}</button>
                        <button class="btn btn-sm btn-outline-danger" data-act="delete" data-id="${r.id}">Delete</button>
                    </td></tr>`;
            }).join('');
        } catch (e) { body.innerHTML = `<tr><td colspan="9" class="dt-empty">${esc(e.message)}</td></tr>`; }
    }

    async function loadLog() {
        const box = $('dtLog');
        try {
            const { data } = await api(URLS.log);
            if (!data.length) { box.className = 'dt-empty'; box.textContent = 'No tasks have been created by rules yet.'; return; }
            box.className = '';
            box.innerHTML = '<div class="table-responsive"><table class="table table-sm dt-table"><thead><tr><th>Created</th><th>Rule</th><th>Assigned to</th><th>Task</th><th>Status</th></tr></thead><tbody>'
                + data.map((c) => `<tr><td class="text-nowrap">${esc(c.created_at)}</td><td>${esc(c.rule)}</td><td>${esc(c.assignee)}</td>
                    <td>${esc(c.title)}${c.task_id ? ` <span class="dt-meta">#${c.task_id}</span>` : ''}</td><td>${esc(c.status)}</td></tr>`).join('')
                + '</tbody></table></div>';
        } catch (e) { box.textContent = e.message; }
    }

    const modal = new bootstrap.Modal($('dtRuleModal'));
    const previewModal = new bootstrap.Modal($('dtPreviewModal'));
    let lastField = $('dtDescription');
    $('dtTitle').addEventListener('focus', () => { lastField = $('dtTitle'); });
    $('dtDescription').addEventListener('focus', () => { lastField = $('dtDescription'); });

    $('dtEvent').innerHTML = EVENTS.map((e) => `<option value="${esc(e.key)}">${esc(e.page)}: ${esc(e.label)}</option>`).join('');

    function applyEvent() {
        const ev = EVENTS.find((e) => e.key === $('dtEvent').value) || EVENTS[0];
        $('dtEventHint').textContent = ev.manual
            ? 'Tasks are created only when you press "Create now" on the rule. One task for every selected person.'
            : (ev.scope === 'user' ? 'Checked every 30 minutes. Each person only gets a task about their own data.' : 'Checked every 30 minutes.');
        $('dtConditionWrap').classList.toggle('d-none', ev.manual);
        $('dtMetric').innerHTML = '<option value="">(no condition, always create)</option>'
            + Object.entries(ev.metrics).map(([k, l]) => `<option value="${esc(k)}">${esc(l)}</option>`).join('');
        $('dtChips').innerHTML = Object.entries(ev.placeholders).map(([k, l]) =>
            `<button type="button" class="dt-chip" data-ph="${esc(k)}" title="${esc(l)}">{${esc(k)}}</button>`).join('');
    }
    $('dtEvent').addEventListener('change', applyEvent);
    $('dtChips').addEventListener('click', (e) => {
        const chip = e.target.closest('[data-ph]');
        if (!chip) return;
        const f = lastField, t = '{' + chip.dataset.ph + '}';
        const s = f.selectionStart ?? f.value.length, en = f.selectionEnd ?? f.value.length;
        f.value = f.value.slice(0, s) + t + f.value.slice(en);
        f.focus(); f.selectionStart = f.selectionEnd = s + t.length;
    });
    $('dtUserSearch').addEventListener('input', (e) => {
        const q = e.target.value.toLowerCase().trim();
        document.querySelectorAll('#dtUsers label').forEach((l) => { l.style.display = !q || l.dataset.search.includes(q) ? '' : 'none'; });
    });

    function openRule(rule) {
        $('dtRuleError').classList.add('d-none');
        $('dtRuleModalTitle').textContent = rule ? 'Edit task rule' : 'New task rule';
        $('dtRuleId').value = rule ? rule.id : '';
        $('dtName').value = rule?.name || '';
        $('dtEvent').value = rule?.event_key || EVENTS[0].key;
        applyEvent();
        $('dtMetric').value = rule?.metric || '';
        $('dtOperator').value = rule?.operator || '>=';
        $('dtThreshold').value = rule?.threshold ?? '';
        $('dtTitle').value = rule?.title || '';
        $('dtDescription').value = rule?.description || '';
        $('dtGroup').value = rule?.task_group || '';
        $('dtPriority').value = rule?.priority || 'normal';
        $('dtEta').value = rule?.eta_minutes ?? 10;
        $('dtTid').value = rule?.tid_in_days ?? 0;
        $('dtLink').value = rule?.link || '';
        $('dtFrequency').value = rule?.frequency || 'daily';
        $('dtSkipOpen').checked = rule ? !!rule.skip_if_open : true;
        $('dtActive').checked = rule ? !!rule.is_active : true;
        const ids = new Set((rule?.recipient_user_ids || []).map(Number));
        document.querySelectorAll('.dt-user-cb').forEach((cb) => { cb.checked = ids.has(Number(cb.value)); });
        $('dtUserSearch').value = ''; $('dtUserSearch').dispatchEvent(new Event('input'));
        modal.show();
    }
    $('dtNewRuleBtn').addEventListener('click', () => openRule(null));

    $('dtSaveBtn').addEventListener('click', async () => {
        const id = $('dtRuleId').value;
        const metric = $('dtMetric').value;
        const payload = {
            name: $('dtName').value.trim(),
            event_key: $('dtEvent').value,
            metric: metric || null,
            operator: metric ? $('dtOperator').value : null,
            threshold: metric && $('dtThreshold').value !== '' ? Number($('dtThreshold').value) : null,
            title: $('dtTitle').value.trim(),
            description: $('dtDescription').value.trim(),
            task_group: $('dtGroup').value.trim(),
            priority: $('dtPriority').value,
            eta_minutes: Number($('dtEta').value) || 0,
            tid_in_days: Number($('dtTid').value) || 0,
            link: $('dtLink').value.trim(),
            recipient_user_ids: [...document.querySelectorAll('.dt-user-cb:checked')].map((cb) => Number(cb.value)),
            frequency: $('dtFrequency').value,
            skip_if_open: $('dtSkipOpen').checked,
            is_active: $('dtActive').checked,
        };
        const btn = $('dtSaveBtn'); btn.disabled = true;
        try {
            await api(id ? URLS.base + '/rules/' + id : URLS.rules, id ? 'PUT' : 'POST', payload);
            modal.hide(); loadRules();
        } catch (e) {
            const box = $('dtRuleError'); box.textContent = e.message; box.classList.remove('d-none');
        } finally { btn.disabled = false; }
    });

    $('dtRulesBody').addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-act]');
        if (!btn) return;
        const id = btn.dataset.id, rule = rulesCache.find((r) => String(r.id) === id);
        try {
            if (btn.dataset.act === 'edit') return openRule(rule);
            if (btn.dataset.act === 'toggle') { await api(URLS.base + '/rules/' + id + '/toggle', 'POST'); return loadRules(); }
            if (btn.dataset.act === 'delete') {
                if (!confirm('Delete this rule? Tasks that were already created are kept.')) return;
                await api(URLS.base + '/rules/' + id, 'DELETE'); return loadRules();
            }
            if (btn.dataset.act === 'preview') {
                const r = await api(URLS.base + '/rules/' + id + '/preview', 'POST');
                const note = { duplicate: 'already created for this period, will be skipped', open: 'previous task still open, will be skipped' };
                $('dtPreviewBody').innerHTML = r.matches.length
                    ? r.matches.map((m) => `<div class="dt-card"><div class="dt-meta">Assign to: <b>${esc(m.name)}</b> · by ${esc(ASSIGNOR)}${m.skipped ? ' · ' + esc(note[m.skipped]) : ''}</div>
                        <div class="dt-title">${esc(m.title)}</div><div class="dt-body">${esc(m.description)}</div></div>`).join('')
                    : `<div class="dt-empty">Nobody matches right now (${r.no_match} checked, condition not met).</div>`;
                return previewModal.show();
            }
            if (btn.dataset.act === 'run') {
                if (!confirm(rule.manual ? 'Create this task for all selected people now?' : 'Evaluate this rule now and create tasks for everyone who matches?')) return;
                const r = await api(URLS.base + '/rules/' + id + '/run', 'POST');
                alert(`Tasks created: ${r.created}\nAlready created for this period: ${r.skipped_duplicates}\nPrevious task still open: ${r.skipped_open}\nCondition not met: ${r.no_match}`);
                return loadRules();
            }
        } catch (err) { alert(err.message); }
    });

    loadRules();
});
</script>
@endsection
