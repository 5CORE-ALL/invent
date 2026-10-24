{{--
    Task Summary quick actions (president only): "Send message" / "Create task" for one team member.
    Presets are the saved rules from /dynamic-messages and /dynamic-tasks, pre-filled with the
    member's live data. Title / text stay editable before sending. Tasks are assigned by Amarjit.
--}}
<style>
    .ts-member-actions .ts-dyn-task-btn:hover { color: #16a34a; }
    .ts-member-actions .ts-dyn-msg-btn:hover { color: #2563eb; }
    #tsDynModal .ts-dyn-note { font-size: 12px; color: #64748b; }
    #tsDynModal .ts-dyn-warn { font-size: 12px; color: #b45309; }
    #tsDynModal .ts-dyn-err { color: #dc2626; font-size: 13px; min-height: 1.2rem; }
</style>
<div class="modal fade" id="tsDynModal" tabindex="-1" aria-labelledby="tsDynTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="tsDynTitle">Send message</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="ts-dyn-note mb-2" id="tsDynSub"></div>
                <div class="mb-3">
                    <label class="form-label fw-semibold" for="tsDynPreset">Preset</label>
                    <select class="form-select" id="tsDynPreset"></select>
                    <div class="ts-dyn-warn mt-1 d-none" id="tsDynWarn">No live data for this person on that page yet, so some {placeholders} stay as written.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold" for="tsDynHead" id="tsDynHeadLabel">Title</label>
                    <input type="text" class="form-control" id="tsDynHead" maxlength="190">
                </div>
                <div class="mb-2">
                    <label class="form-label fw-semibold" for="tsDynText" id="tsDynTextLabel">Message</label>
                    <textarea class="form-control" id="tsDynText" rows="5"></textarea>
                </div>
                <div class="ts-dyn-note" id="tsDynMeta"></div>
                <div class="ts-dyn-err mt-2" id="tsDynErr"></div>
            </div>
            <div class="modal-footer">
                <a href="{{ route('dynamic-messages.index') }}" class="btn btn-link me-auto" id="tsDynManage" target="_blank" rel="noopener">Manage presets</a>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="tsDynSubmit" disabled>Send</button>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    var URLS = {
        presets: @json(route('dynamic-quick.presets')),
        message: @json(route('dynamic-quick.message')),
        task: @json(route('dynamic-quick.task')),
        manageMessages: @json(route('dynamic-messages.index')),
        manageTasks: @json(route('dynamic-tasks.index'))
    };
    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var csrf = csrfMeta ? csrfMeta.getAttribute('content') : @json(csrf_token());
    var modal = null;
    var state = { type: 'message', userId: 0, name: '', data: null };
    var $ = function (id) { return document.getElementById(id); };

    function ensureModal() {
        if (!modal && typeof bootstrap !== 'undefined') { modal = new bootstrap.Modal($('tsDynModal')); }
        return modal;
    }

    function items() { return state.data ? (state.type === 'task' ? state.data.tasks : state.data.messages) : []; }
    function current() {
        var id = Number($('tsDynPreset').value);
        return items().filter(function (i) { return i.id === id; })[0] || null;
    }

    function fillFromPreset() {
        var p = current();
        $('tsDynWarn').classList.toggle('d-none', !p || p.data_available);
        if (!p) { $('tsDynHead').value = ''; $('tsDynText').value = ''; $('tsDynMeta').textContent = ''; $('tsDynSubmit').disabled = true; return; }
        $('tsDynHead').value = p.title;
        $('tsDynText').value = state.type === 'task' ? p.description : p.body;
        $('tsDynMeta').textContent = state.type === 'task'
            ? 'Priority: ' + p.priority + ' · ETC: ' + p.eta_minutes + ' min · TID in ' + p.tid_in_days + ' day(s)' + (p.task_group ? ' · Group: ' + p.task_group : '') + ' · Assigned by ' + state.data.assignor
            : (p.allow_reply ? 'Recipient may send an optional reply to ' + state.data.assignor + '. ' : '') + 'Recipient must tick a mandatory confirmation check-box.';
        $('tsDynSubmit').disabled = false;
    }

    function open(type, userId, name) {
        var m = ensureModal();
        if (!m) { alert('Page is still loading, please try again.'); return; }
        state = { type: type, userId: userId, name: name, data: null };
        var isTask = type === 'task';
        $('tsDynTitle').textContent = (isTask ? 'Create task for ' : 'Send message to ') + name;
        $('tsDynSub').textContent = isTask ? 'Pick a preset task. It is created immediately and assigned by Amarjit.' : 'Pick a preset message. It pops up for the person on their next page load.';
        $('tsDynHeadLabel').textContent = isTask ? 'Task title' : 'Title';
        $('tsDynTextLabel').textContent = isTask ? 'Task description' : 'Message';
        $('tsDynSubmit').textContent = isTask ? 'Create task' : 'Send message';
        $('tsDynManage').href = isTask ? URLS.manageTasks : URLS.manageMessages;
        $('tsDynManage').textContent = isTask ? 'Manage task presets' : 'Manage message presets';
        $('tsDynErr').textContent = '';
        $('tsDynPreset').innerHTML = '<option>Loading…</option>';
        $('tsDynSubmit').disabled = true;
        $('tsDynHead').value = ''; $('tsDynText').value = ''; $('tsDynMeta').textContent = '';
        m.show();

        fetch(URLS.presets + '?user_id=' + encodeURIComponent(userId), { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json().then(function (j) { if (!r.ok) throw new Error(j.message || 'Could not load presets.'); return j; }); })
            .then(function (j) {
                state.data = j;
                var list = items();
                if (!list.length) {
                    $('tsDynPreset').innerHTML = '<option value="">No presets saved yet</option>';
                    $('tsDynErr').textContent = 'No ' + (isTask ? 'task' : 'message') + ' presets yet. Create one with "' + $('tsDynManage').textContent + '".';
                    return;
                }
                $('tsDynPreset').innerHTML = list.map(function (i) {
                    var label = i.name + (i.is_active ? '' : ' (paused)');
                    return '<option value="' + i.id + '">' + label.replace(/&/g, '&amp;').replace(/</g, '&lt;') + '</option>';
                }).join('');
                fillFromPreset();
            })
            .catch(function (e) { $('tsDynPreset').innerHTML = '<option value="">-</option>'; $('tsDynErr').textContent = e.message; });
    }

    $('tsDynPreset').addEventListener('change', fillFromPreset);

    $('tsDynSubmit').addEventListener('click', function () {
        var p = current();
        if (!p) return;
        var title = $('tsDynHead').value.trim(), text = $('tsDynText').value.trim();
        if (!title) { $('tsDynErr').textContent = 'Title is required.'; return; }
        if (state.type === 'message' && !text) { $('tsDynErr').textContent = 'Message text is required.'; return; }
        var btn = $('tsDynSubmit'); btn.disabled = true; $('tsDynErr').textContent = '';
        var body = state.type === 'task'
            ? { user_id: state.userId, rule_id: p.id, title: title, description: text }
            : { user_id: state.userId, rule_id: p.id, title: title, body: text };
        fetch(state.type === 'task' ? URLS.task : URLS.message, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(body)
        }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
          .then(function (res) {
              if (!res.ok) {
                  var first = res.j.errors ? Object.values(res.j.errors)[0][0] : null;
                  throw new Error(first || res.j.message || 'Request failed.');
              }
              modal.hide();
              alert(res.j.message || 'Done.');
          })
          .catch(function (e) { $('tsDynErr').textContent = e.message; btn.disabled = false; });
    });

    document.addEventListener('click', function (e) {
        var b = e.target && e.target.closest && e.target.closest('.ts-dyn-task-btn, .ts-dyn-msg-btn');
        if (!b) return;
        e.preventDefault(); e.stopPropagation();
        var tr = b.closest('tr.task-summary-row');
        if (!tr) return;
        var uid = Number(tr.getAttribute('data-user-id') || 0);
        if (!uid) { alert('No user record found for this row.'); return; }
        open(b.classList.contains('ts-dyn-task-btn') ? 'task' : 'message', uid, tr.getAttribute('data-sort-member') || 'this member');
    });
})();
</script>
