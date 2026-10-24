@extends('layouts.vertical', ['title' => 'Dynamic Messages', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        .dm-lead { color: #64748b; font-size: 13px; margin: 2px 0 0; }
        .dm-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 16px; margin-bottom: 12px; }
        .dm-card--unread { border-left: 4px solid #f59e0b; }
        .dm-title { font-weight: 700; color: #0f172a; }
        .dm-body { white-space: pre-wrap; font-size: 13px; color: #1e293b; margin-top: 4px; }
        .dm-meta { font-size: 11.5px; color: #64748b; }
        .dm-reply { background: #f1f5f9; border-radius: 8px; padding: 8px 10px; font-size: 12.5px; margin-top: 8px; white-space: pre-wrap; }
        .dm-chip { display: inline-block; padding: 2px 9px; margin: 2px 4px 2px 0; border-radius: 999px; background: #e0f2fe; color: #075985; font-size: 12px; font-weight: 600; cursor: pointer; border: 0; }
        .dm-chip:hover { background: #bae6fd; }
        .dm-users { max-height: 220px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 8px; padding: 6px 10px; }
        .dm-users label { display: block; font-size: 13px; margin: 0; padding: 2px 0; cursor: pointer; }
        .dm-badge { display: inline-block; min-width: 18px; padding: 0 6px; border-radius: 999px; background: #ef4444; color: #fff; font-size: 11px; font-weight: 700; text-align: center; margin-left: 4px; }
        .dm-table th { font-size: 12px; text-transform: uppercase; letter-spacing: .03em; color: #475569; white-space: nowrap; }
        .dm-table td { font-size: 13px; vertical-align: top; }
        .dm-empty { text-align: center; color: #94a3b8; padding: 28px 0; font-size: 13px; }
    </style>
@endsection

@section('content')
    <div class="container-fluid" id="dmApp">
        <div class="d-flex flex-wrap justify-content-between align-items-center my-3 gap-2">
            <div>
                <h4 class="mb-0">Dynamic Messages</h4>
                <p class="dm-lead">
                    Pre-saved messages that are sent to pre-saved people based on data / events from different pages.
                    @unless ($canManage)
                        Only {{ config('dynamic_messages.editor_email') }} can create or edit them.
                    @endunless
                </p>
            </div>
            @if ($canManage)
                <button type="button" class="btn btn-primary" id="dmNewRuleBtn"><i class="ri-add-line me-1"></i>New message rule</button>
            @endif
        </div>

        <ul class="nav nav-tabs mb-3" id="dmTabs">
            <li class="nav-item"><button class="nav-link active" data-tab="mine" type="button">My messages</button></li>
            @if ($canManage)
                <li class="nav-item"><button class="nav-link" data-tab="rules" type="button">Message rules</button></li>
                <li class="nav-item"><button class="nav-link" data-tab="log" type="button">Delivery log</button></li>
            @endif
            @if ($canSeeReplies)
                <li class="nav-item"><button class="nav-link" data-tab="replies" type="button">Replies <span class="dm-badge d-none" id="dmReplyBadge">0</span></button></li>
            @endif
        </ul>

        <div data-pane="mine"><div id="dmMine" class="dm-empty">Loading…</div></div>

        @if ($canManage)
            <div data-pane="rules" class="d-none">
                <div class="table-responsive">
                    <table class="table table-sm align-middle dm-table">
                        <thead>
                            <tr>
                                <th>Rule</th><th>Page / event</th><th>Condition</th><th>Recipients</th>
                                <th>Frequency</th><th>Reply</th><th>Sent / Acked</th><th>Status</th><th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="dmRulesBody"><tr><td colspan="9" class="dm-empty">Loading…</td></tr></tbody>
                    </table>
                </div>
            </div>
            <div data-pane="log" class="d-none"><div id="dmLog" class="dm-empty">Loading…</div></div>
        @endif

        @if ($canSeeReplies)
            <div data-pane="replies" class="d-none"><div id="dmReplies" class="dm-empty">Loading…</div></div>
        @endif
    </div>

    @if ($canManage)
        <div class="modal fade" id="dmRuleModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="dmRuleModalTitle">Message rule</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-danger d-none" id="dmRuleError"></div>
                        <input type="hidden" id="dmRuleId">

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Rule name</label>
                            <input type="text" class="form-control" id="dmName" maxlength="150" placeholder="e.g. Overdue tasks nudge">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Page / event (when to send)</label>
                            <select class="form-select" id="dmEvent"></select>
                            <div class="form-text" id="dmEventHint"></div>
                        </div>

                        <div class="mb-3" id="dmConditionWrap">
                            <label class="form-label fw-semibold">Condition <span class="text-muted fw-normal">(optional, leave metric empty to always send)</span></label>
                            <div class="row g-2">
                                <div class="col-md-6"><select class="form-select" id="dmMetric"></select></div>
                                <div class="col-md-3">
                                    <select class="form-select" id="dmOperator">
                                        @foreach ($operators as $op)
                                            <option value="{{ $op }}">{{ $op }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3"><input type="number" step="any" class="form-control" id="dmThreshold" placeholder="value"></div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Message title</label>
                            <input type="text" class="form-control" id="dmTitle" maxlength="190" placeholder="e.g. {first_name}, you have {overdue_count} overdue tasks">
                        </div>
                        <div class="mb-2">
                            <label class="form-label fw-semibold">Message text</label>
                            <textarea class="form-control" id="dmBody" rows="5" maxlength="3000" placeholder="Write the pre-saved message. Click a placeholder below to insert live data."></textarea>
                        </div>
                        <div class="mb-3">
                            <div class="dm-meta mb-1">Insert live data (replaced with real values when sent):</div>
                            <div id="dmChips"></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Send to (pre-saved users)</label>
                            <input type="text" class="form-control form-control-sm mb-1" id="dmUserSearch" placeholder="Search people…">
                            <div class="dm-users" id="dmUsers">
                                @foreach ($users as $u)
                                    <label data-search="{{ strtolower($u->name.' '.$u->email) }}">
                                        <input type="checkbox" class="dm-user-cb" value="{{ $u->id }}"> {{ $u->name }} <span class="text-muted">({{ $u->email }})</span>
                                    </label>
                                @endforeach
                            </div>
                            <div class="dm-meta mt-1">Page-data events (tasks, attendance) are sent to each person only about their own data.</div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">How often</label>
                                <select class="form-select" id="dmFrequency">
                                    @foreach ($frequencies as $k => $label)
                                        <option value="{{ $k }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                <div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="dmAllowReply">
                                        <label class="form-check-label" for="dmAllowReply">Allow optional reply to {{ $replyLabel }}</label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="dmActive" checked>
                                        <label class="form-check-label" for="dmActive">Rule is active</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="dm-meta mt-3">
                            Recipients must always tick a mandatory confirmation check-box ("I have read this message") before the message closes.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="dmSaveBtn">Save rule</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="dmPreviewModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Preview (nothing is sent)</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" id="dmPreviewBody"></div>
                </div>
            </div>
        </div>
    @endif
@endsection

@section('script-after-vite')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const CAN_MANAGE = @json($canManage);
    const CAN_REPLIES = @json($canSeeReplies);
    const EVENTS = @json($events);
    const REPLY_LABEL = @json($replyLabel);
    const URLS = {
        mine: @json(route('dynamic-messages.mine')),
        rules: @json(route('dynamic-messages.rules')),
        log: @json(route('dynamic-messages.log')),
        replies: @json(route('dynamic-messages.replies')),
        base: @json(url('dynamic-messages')),
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

    // ---------------------------------------------------------------- tabs
    const loaders = { mine: loadMine, rules: loadRules, log: loadLog, replies: loadReplies };
    function showTab(name) {
        document.querySelectorAll('#dmTabs .nav-link').forEach((b) => b.classList.toggle('active', b.dataset.tab === name));
        document.querySelectorAll('[data-pane]').forEach((p) => p.classList.toggle('d-none', p.dataset.pane !== name));
        loaders[name] && loaders[name]();
    }
    document.querySelectorAll('#dmTabs .nav-link').forEach((b) => b.addEventListener('click', () => showTab(b.dataset.tab)));

    // ---------------------------------------------------------------- my messages
    async function loadMine() {
        const box = $('dmMine');
        try {
            const { data } = await api(URLS.mine);
            if (!data.length) { box.className = 'dm-empty'; box.textContent = 'No messages yet.'; return; }
            box.className = '';
            box.innerHTML = data.map((m) => `
                <div class="dm-card ${m.acknowledged_at ? '' : 'dm-card--unread'}">
                    <div class="d-flex justify-content-between flex-wrap gap-1">
                        <div class="dm-title">${esc(m.title)}</div>
                        <div class="dm-meta">${esc(m.page || '')} · ${esc(m.sent_at)}</div>
                    </div>
                    <div class="dm-body">${esc(m.body)}</div>
                    <div class="dm-meta mt-2">${m.acknowledged_at ? 'Confirmed ' + esc(m.acknowledged_at) : 'Awaiting your confirmation (it will pop up on your next page load)'}</div>
                    ${m.reply ? `<div class="dm-reply"><b>Your reply to ${esc(REPLY_LABEL)}:</b><br>${esc(m.reply)}</div>` : ''}
                </div>`).join('');
        } catch (e) { box.textContent = e.message; }
    }

    // ---------------------------------------------------------------- rules
    let rulesCache = [];
    async function loadRules() {
        if (!CAN_MANAGE) return;
        const body = $('dmRulesBody');
        try {
            const { data } = await api(URLS.rules);
            rulesCache = data;
            if (!data.length) { body.innerHTML = '<tr><td colspan="9" class="dm-empty">No rules yet. Click "New message rule".</td></tr>'; return; }
            body.innerHTML = data.map((r) => {
                const cond = r.metric ? `${esc(r.metric_label || r.metric)} ${esc(r.operator)} ${esc(r.threshold)}` : '<span class="text-muted">Always</span>';
                const names = r.recipient_names.slice(0, 4).map(esc).join(', ') + (r.recipient_names.length > 4 ? ` +${r.recipient_names.length - 4} more` : '');
                return `<tr>
                    <td><div class="fw-semibold">${esc(r.name)}</div><div class="dm-meta">${esc(r.title)}</div></td>
                    <td>${esc(r.page)}<div class="dm-meta">${esc(r.event_label)}</div></td>
                    <td>${cond}</td>
                    <td>${names}</td>
                    <td>${r.manual ? 'On demand' : esc(r.frequency)}</td>
                    <td>${r.allow_reply ? 'Allowed' : '-'}</td>
                    <td>${r.messages_count} / ${r.acknowledged_count}<div class="dm-meta">${r.last_run_at ? 'Last run ' + esc(r.last_run_at) : 'Never run'}</div></td>
                    <td><span class="badge ${r.is_active ? 'bg-success' : 'bg-secondary'}">${r.is_active ? 'Active' : 'Paused'}</span></td>
                    <td class="text-end text-nowrap">
                        <button class="btn btn-sm btn-light" data-act="preview" data-id="${r.id}">Preview</button>
                        <button class="btn btn-sm btn-success" data-act="run" data-id="${r.id}">${r.manual ? 'Send now' : 'Run now'}</button>
                        <button class="btn btn-sm btn-light" data-act="edit" data-id="${r.id}">Edit</button>
                        <button class="btn btn-sm btn-light" data-act="toggle" data-id="${r.id}">${r.is_active ? 'Pause' : 'Resume'}</button>
                        <button class="btn btn-sm btn-outline-danger" data-act="delete" data-id="${r.id}">Delete</button>
                    </td></tr>`;
            }).join('');
        } catch (e) { body.innerHTML = `<tr><td colspan="9" class="dm-empty">${esc(e.message)}</td></tr>`; }
    }

    if (CAN_MANAGE) {
        const modal = new bootstrap.Modal($('dmRuleModal'));
        const previewModal = new bootstrap.Modal($('dmPreviewModal'));
        let lastField = $('dmBody');
        $('dmTitle').addEventListener('focus', () => { lastField = $('dmTitle'); });
        $('dmBody').addEventListener('focus', () => { lastField = $('dmBody'); });

        $('dmEvent').innerHTML = EVENTS.map((e) => `<option value="${esc(e.key)}">${esc(e.page)}: ${esc(e.label)}</option>`).join('');

        function applyEvent(keepMetric) {
            const ev = EVENTS.find((e) => e.key === $('dmEvent').value) || EVENTS[0];
            $('dmEventHint').textContent = ev.manual
                ? 'Sent only when you press "Send now" on the rule. Goes to every selected person.'
                : (ev.scope === 'user' ? 'Checked every 30 minutes. Each person only gets a message about their own data.' : 'Checked every 30 minutes.');
            $('dmConditionWrap').classList.toggle('d-none', ev.manual);
            const prev = keepMetric ? $('dmMetric').value : '';
            $('dmMetric').innerHTML = '<option value="">(no condition, always send)</option>'
                + Object.entries(ev.metrics).map(([k, l]) => `<option value="${esc(k)}">${esc(l)}</option>`).join('');
            $('dmMetric').value = prev;
            $('dmChips').innerHTML = Object.entries(ev.placeholders).map(([k, l]) =>
                `<button type="button" class="dm-chip" data-ph="${esc(k)}" title="${esc(l)}">{${esc(k)}}</button>`).join('');
        }
        $('dmEvent').addEventListener('change', () => applyEvent(false));
        $('dmChips').addEventListener('click', (e) => {
            const chip = e.target.closest('[data-ph]');
            if (!chip) return;
            const f = lastField, t = '{' + chip.dataset.ph + '}';
            const s = f.selectionStart ?? f.value.length, en = f.selectionEnd ?? f.value.length;
            f.value = f.value.slice(0, s) + t + f.value.slice(en);
            f.focus(); f.selectionStart = f.selectionEnd = s + t.length;
        });
        $('dmUserSearch').addEventListener('input', (e) => {
            const q = e.target.value.toLowerCase().trim();
            document.querySelectorAll('#dmUsers label').forEach((l) => { l.style.display = !q || l.dataset.search.includes(q) ? '' : 'none'; });
        });

        function openRule(rule) {
            $('dmRuleError').classList.add('d-none');
            $('dmRuleModalTitle').textContent = rule ? 'Edit message rule' : 'New message rule';
            $('dmRuleId').value = rule ? rule.id : '';
            $('dmName').value = rule?.name || '';
            $('dmEvent').value = rule?.event_key || EVENTS[0].key;
            applyEvent(false);
            $('dmMetric').value = rule?.metric || '';
            $('dmOperator').value = rule?.operator || '>=';
            $('dmThreshold').value = rule?.threshold ?? '';
            $('dmTitle').value = rule?.title || '';
            $('dmBody').value = rule?.body || '';
            $('dmFrequency').value = rule?.frequency || 'daily';
            $('dmAllowReply').checked = !!rule?.allow_reply;
            $('dmActive').checked = rule ? !!rule.is_active : true;
            const ids = new Set((rule?.recipient_user_ids || []).map(Number));
            document.querySelectorAll('.dm-user-cb').forEach((cb) => { cb.checked = ids.has(Number(cb.value)); });
            $('dmUserSearch').value = ''; $('dmUserSearch').dispatchEvent(new Event('input'));
            modal.show();
        }
        $('dmNewRuleBtn').addEventListener('click', () => openRule(null));

        $('dmSaveBtn').addEventListener('click', async () => {
            const id = $('dmRuleId').value;
            const metric = $('dmMetric').value;
            const payload = {
                name: $('dmName').value.trim(),
                event_key: $('dmEvent').value,
                metric: metric || null,
                operator: metric ? $('dmOperator').value : null,
                threshold: metric && $('dmThreshold').value !== '' ? Number($('dmThreshold').value) : null,
                title: $('dmTitle').value.trim(),
                body: $('dmBody').value.trim(),
                recipient_user_ids: [...document.querySelectorAll('.dm-user-cb:checked')].map((cb) => Number(cb.value)),
                frequency: $('dmFrequency').value,
                allow_reply: $('dmAllowReply').checked,
                is_active: $('dmActive').checked,
            };
            const btn = $('dmSaveBtn'); btn.disabled = true;
            try {
                await api(id ? URLS.base + '/rules/' + id : URLS.rules, id ? 'PUT' : 'POST', payload);
                modal.hide(); loadRules();
            } catch (e) {
                const box = $('dmRuleError'); box.textContent = e.message; box.classList.remove('d-none');
            } finally { btn.disabled = false; }
        });

        $('dmRulesBody').addEventListener('click', async (e) => {
            const btn = e.target.closest('[data-act]');
            if (!btn) return;
            const id = btn.dataset.id, rule = rulesCache.find((r) => String(r.id) === id);
            try {
                if (btn.dataset.act === 'edit') return openRule(rule);
                if (btn.dataset.act === 'toggle') { await api(URLS.base + '/rules/' + id + '/toggle', 'POST'); return loadRules(); }
                if (btn.dataset.act === 'delete') {
                    if (!confirm('Delete this rule? Already delivered messages are kept.')) return;
                    await api(URLS.base + '/rules/' + id, 'DELETE'); return loadRules();
                }
                if (btn.dataset.act === 'preview') {
                    const r = await api(URLS.base + '/rules/' + id + '/preview', 'POST');
                    $('dmPreviewBody').innerHTML = r.matches.length
                        ? r.matches.map((m) => `<div class="dm-card"><div class="dm-meta">To: <b>${esc(m.name)}</b>${m.duplicate ? ' · already sent for this period (will be skipped)' : ''}</div>
                            <div class="dm-title">${esc(m.title)}</div><div class="dm-body">${esc(m.body)}</div></div>`).join('')
                        : `<div class="dm-empty">Nobody matches right now (${r.no_match} checked, condition not met).</div>`;
                    return previewModal.show();
                }
                if (btn.dataset.act === 'run') {
                    if (!confirm(rule.manual ? 'Send this message to all selected people now?' : 'Evaluate this rule now and send to everyone who matches?')) return;
                    const r = await api(URLS.base + '/rules/' + id + '/run', 'POST');
                    alert(`Sent: ${r.created}\nAlready sent for this period: ${r.skipped_duplicates}\nCondition not met: ${r.no_match}`);
                    return loadRules();
                }
            } catch (err) { alert(err.message); }
        });
    }

    // ---------------------------------------------------------------- log / replies
    async function loadLog() {
        const box = $('dmLog');
        try {
            const { data } = await api(URLS.log);
            if (!data.length) { box.className = 'dm-empty'; box.textContent = 'Nothing has been delivered yet.'; return; }
            box.className = '';
            box.innerHTML = '<div class="table-responsive"><table class="table table-sm dm-table"><thead><tr><th>Sent</th><th>Rule</th><th>To</th><th>Message</th><th>Confirmed</th><th>Reply</th></tr></thead><tbody>'
                + data.map((m) => `<tr><td class="text-nowrap">${esc(m.sent_at)}</td><td>${esc(m.rule)}</td><td>${esc(m.recipient)}</td>
                    <td><b>${esc(m.title)}</b><div class="dm-meta" style="white-space:pre-wrap">${esc(m.body)}</div></td>
                    <td class="text-nowrap">${m.acknowledged_at ? '✔ ' + esc(m.acknowledged_at) : '<span class="text-warning">Pending</span>'}</td>
                    <td style="white-space:pre-wrap">${m.reply ? esc(m.reply) : (m.allow_reply ? '<span class="text-muted">-</span>' : '')}</td></tr>`).join('')
                + '</tbody></table></div>';
        } catch (e) { box.textContent = e.message; }
    }

    async function loadReplies() {
        if (!CAN_REPLIES) return;
        const box = $('dmReplies');
        try {
            const { data, unseen_count } = await api(URLS.replies);
            const badge = $('dmReplyBadge');
            badge.textContent = unseen_count; badge.classList.toggle('d-none', !unseen_count);
            if (!data.length) { box.className = 'dm-empty'; box.textContent = 'No replies yet.'; return; }
            box.className = '';
            box.innerHTML = data.map((r) => `
                <div class="dm-card ${r.seen ? '' : 'dm-card--unread'}">
                    <div class="d-flex justify-content-between flex-wrap gap-1">
                        <div class="dm-title">${esc(r.from)} <span class="dm-meta">replied · ${esc(r.replied_at)}</span></div>
                        ${r.seen ? '<span class="dm-meta">Seen</span>' : `<button class="btn btn-sm btn-light" data-seen="${r.id}">Mark as seen</button>`}
                    </div>
                    <div class="dm-body">${esc(r.reply)}</div>
                    <div class="dm-reply"><b>${esc(r.message_title)}</b><br>${esc(r.message_body)}</div>
                </div>`).join('');
        } catch (e) { box.textContent = e.message; }
    }
    if (CAN_REPLIES) {
        $('dmReplies').addEventListener('click', async (e) => {
            const b = e.target.closest('[data-seen]');
            if (!b) return;
            try { await api(URLS.base + '/replies/' + b.dataset.seen + '/seen', 'POST'); loadReplies(); } catch (err) { alert(err.message); }
        });
        api(URLS.replies).then((r) => { const badge = $('dmReplyBadge'); badge.textContent = r.unseen_count; badge.classList.toggle('d-none', !r.unseen_count); }).catch(() => {});
    }

    loadMine();
});
</script>
@endsection
