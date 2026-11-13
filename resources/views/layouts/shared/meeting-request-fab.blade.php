{{--
    Floating "Request a meeting" button, shown on every page for logged-in users.
    Opens a modal to pick members + write an agenda, shows a review/confirm step before sending,
    and lets invitees accept / decline requests addressed to them.
    Hide on a page with: @extends('layouts.vertical', [..., 'hideMeetingFab' => true])
--}}
@auth
@unless($hideMeetingFab ?? false)
<style>
    #mrqFab {
        position: fixed; right: 20px; bottom: 84px; z-index: 1040;
        width: 54px; height: 54px; border-radius: 50%; border: 0; padding: 0;
        background: linear-gradient(135deg, #0ea5e9 0%, #2563eb 100%); color: #fff; font-size: 26px;
        display: flex; align-items: center; justify-content: center;
        box-shadow: 0 6px 18px rgba(37, 99, 235, .45); cursor: pointer; transition: transform .15s ease, box-shadow .15s ease;
    }
    #mrqFab:hover { transform: translateY(-3px); box-shadow: 0 10px 24px rgba(37, 99, 235, .55); }
    #mrqFab .mrq-badge {
        position: absolute; top: -4px; right: -4px; min-width: 20px; height: 20px; padding: 0 5px; border-radius: 10px;
        background: #dc2626; color: #fff; font-size: 11px; font-weight: 700; line-height: 20px; text-align: center; border: 2px solid #fff;
    }
    #mrqFab.has-pending { animation: mrqRing 2s infinite; }
    @keyframes mrqRing { 0%,100% { box-shadow: 0 6px 18px rgba(37,99,235,.45) } 50% { box-shadow: 0 0 0 10px rgba(37,99,235,.18) } }
    @media (max-width: 991.98px) { #mrqFab { right: 12px; bottom: 146px; width: 50px; height: 50px; } }

    .mrq-toast {
        position: fixed; right: 84px; bottom: 90px; z-index: 1041; max-width: 320px; background: #0f172a; color: #fff;
        border-radius: 10px; padding: 10px 14px; box-shadow: 0 10px 28px rgba(15,23,42,.35); cursor: pointer; font-size: .85rem;
    }
    @media (max-width: 991.98px) { .mrq-toast { right: 72px; bottom: 150px; } }

    #mrqModal .mrq-tabs { display: flex; gap: 6px; border-bottom: 1px solid #e5e9f0; margin: -4px 0 14px; }
    #mrqModal .mrq-tab { border: 0; background: none; padding: 8px 12px; font-weight: 600; color: #64748b; border-bottom: 3px solid transparent; }
    #mrqModal .mrq-tab.active { color: #2563eb; border-bottom-color: #2563eb; }
    #mrqModal .mrq-tab .badge { margin-left: 4px; }
    #mrqModal .mrq-hidden { display: none !important; }
    #mrqModal .mrq-list { max-height: 210px; overflow-y: auto; border: 1px solid #e5e9f0; border-radius: 8px; padding: 4px 10px; }
    #mrqModal .mrq-list label { display: flex; gap: 8px; align-items: center; padding: 4px 0; margin: 0; cursor: pointer; font-weight: 400; }
    #mrqModal .mrq-list small { color: #94a3b8; }
    #mrqModal .mrq-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; min-height: 8px; }
    #mrqModal .mrq-chip { display: inline-flex; align-items: center; gap: 5px; background: #e0ecff; color: #1e40af; border-radius: 14px; padding: 2px 6px 2px 10px; font-size: .8rem; }
    #mrqModal .mrq-chip button { border: 0; background: none; color: inherit; font-size: 15px; line-height: 1; padding: 0 2px; cursor: pointer; }
    #mrqModal .mrq-note { background: #fef2f2; border: 1px solid #fecaca; color: #7f1d1d; border-radius: 8px; padding: 8px 12px; font-size: .84rem; }
    #mrqModal .mrq-review dt { color: #64748b; font-weight: 600; font-size: .8rem; text-transform: uppercase; letter-spacing: .03em; margin-top: 10px; }
    #mrqModal .mrq-review dd { margin: 2px 0 0; color: #0f172a; white-space: pre-line; }
    #mrqModal .mrq-req { border: 1px solid #e5e9f0; border-radius: 10px; padding: 12px 14px; margin-bottom: 10px; }
    #mrqModal .mrq-req h6 { font-weight: 700; margin: 0 0 2px; }
    #mrqModal .mrq-success-icon { font-size: 54px; color: #16a34a; line-height: 1; }
    #mrqModal .mrq-prio { display: inline-flex; align-items: center; gap: 5px; border-radius: 12px; padding: 1px 9px 1px 7px; font-size: .74rem; font-weight: 700; }
    #mrqModal .mrq-prio i { width: 9px; height: 9px; border-radius: 50%; display: inline-block; }
    #mrqModal .mrq-prio.urgent { background: #fee2e2; color: #991b1b; } #mrqModal .mrq-prio.urgent i { background: #dc2626; }
    #mrqModal .mrq-prio.immediate { background: #ffedd5; color: #9a3412; } #mrqModal .mrq-prio.immediate i { background: #f97316; }
    #mrqModal .mrq-prio.normal { background: #dcfce7; color: #166534; } #mrqModal .mrq-prio.normal i { background: #16a34a; }
    #mrqModal .mrq-room { background: #eef2ff; color: #3730a3; border-radius: 8px; padding: 1px 8px; font-size: .74rem; font-weight: 600; }
    #mrqModal .mrq-av { position: relative; display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 50%; overflow: hidden; color: #fff; font-size: 11px; font-weight: 700; flex-shrink: 0; }
    #mrqModal .mrq-av img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
    #mrqModal .mrq-popt { position: relative; margin: 0; }
    #mrqModal .mrq-popt input { position: absolute; opacity: 0; }
    #mrqModal .mrq-popt span { display: inline-flex; align-items: center; gap: 6px; border: 2px solid #e2e8f0; border-radius: 20px; padding: 4px 12px; cursor: pointer; font-weight: 600; font-size: .86rem; }
    #mrqModal .mrq-popt span i { width: 10px; height: 10px; border-radius: 50%; }
    #mrqModal .mrq-popt.urgent span i { background: #dc2626; } #mrqModal .mrq-popt.immediate span i { background: #f97316; } #mrqModal .mrq-popt.normal span i { background: #16a34a; }
    #mrqModal .mrq-popt input:checked + span { border-color: #2563eb; background: #eff6ff; }
    #mrqModal .mrq-gmeet { background: #1a73e8; border-color: #1a73e8; color: #fff; }
    #mrqModal .mrq-gmeet:hover { background: #1557b0; color: #fff; }
</style>

<button type="button" id="mrqFab" data-bs-toggle="modal" data-bs-target="#mrqModal" title="Request a meeting" aria-label="Request a meeting">
    <i class="ri-vidicon-fill"></i>
    <span class="mrq-badge mrq-hidden" id="mrqBadge" style="display:none">0</span>
</button>
<div class="mrq-toast" id="mrqToast" style="display:none" role="status"></div>

<div class="modal fade" id="mrqModal" tabindex="-1" aria-hidden="true" aria-labelledby="mrqTitle">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="mrqTitle"><i class="ri-vidicon-line me-1"></i>Meeting</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mrq-tabs" id="mrqTabs">
                    <button type="button" class="mrq-tab active" data-tab="new">Request a meeting</button>
                    <button type="button" class="mrq-tab" data-tab="sent">Sent by you</button>
                    <button type="button" class="mrq-tab" data-tab="inbox">Requests for you <span class="badge bg-danger mrq-hidden" id="mrqTabBadge">0</span></button>
                </div>

                {{-- STEP 1: form --}}
                <div id="mrqStepForm">
                    <div class="alert alert-danger py-2 mrq-hidden" id="mrqError"></div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Meeting title <span class="text-danger">*</span></label>
                        <input type="text" id="mrqTitleInput" class="form-control" maxlength="255" placeholder="e.g. Amazon ads budget review">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Agenda <span class="text-danger">*</span>
                            <small class="text-muted fw-normal">what do you want to discuss / decide?</small></label>
                        <textarea id="mrqAgenda" class="form-control" rows="4" maxlength="5000"
                                  placeholder="1. Review last week's ACOS&#10;2. Decide budget for next week&#10;3. Assign owners"></textarea>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label fw-semibold mb-0">Add members <span class="text-danger">*</span></label>
                            <input type="search" id="mrqSearch" class="form-control form-control-sm" style="width:210px" placeholder="Search name / designation">
                        </div>
                        <div class="mrq-list" id="mrqMembers"><div class="text-muted small py-2">Loading team…</div></div>
                        <div class="mrq-chips" id="mrqChips"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold d-block">Priority <span class="text-danger">*</span></label>
                        <div class="d-flex flex-wrap gap-2">
                            <label class="mrq-popt urgent"><input type="radio" name="mrqPrio" value="urgent"><span><i></i>🔴 Urgent</span></label>
                            <label class="mrq-popt immediate"><input type="radio" name="mrqPrio" value="immediate"><span><i></i>🟠 Immediate</span></label>
                            <label class="mrq-popt normal"><input type="radio" name="mrqPrio" value="normal" checked><span><i></i>🟢 Normal</span></label>
                        </div>
                    </div>
                    <div class="row g-2 mb-1">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Meeting room <span class="text-danger">*</span></label>
                            <select id="mrqRoom" class="form-select">
                                <option value="">Select room…</option>
                                <option value="1">Meeting Room 1</option><option value="2">Meeting Room 2</option>
                                <option value="3">Meeting Room 3</option><option value="4">Meeting Room 4</option>
                            </select>
                        </div>
                        <div class="col-md-4"><label class="form-label fw-semibold">Date <span class="text-danger">*</span></label><input type="date" id="mrqDate" class="form-control"></div>
                        <div class="col-6 col-md-2"><label class="form-label fw-semibold">Start <span class="text-danger">*</span></label><input type="time" id="mrqStart" class="form-control"></div>
                        <div class="col-6 col-md-2"><label class="form-label fw-semibold">End <span class="text-danger">*</span></label><input type="time" id="mrqEnd" class="form-control"></div>
                    </div>
                    <div class="d-flex justify-content-between flex-wrap mb-1">
                        <small class="text-muted">Times are in <strong id="mrqTz">Asia/Kolkata</strong>.</small>
                        <button type="button" class="btn btn-link btn-sm p-0" id="mrqNowBtn">Use now → +30 min</button>
                    </div>
                    <div class="small mb-3" id="mrqAvail"></div>
                    <div class="mrq-note" style="background:#eff6ff;border-color:#bfdbfe;color:#1e3a8a"><i class="ri-vidicon-fill me-1"></i>A real <strong>Google Meet</strong> link is created through Google Calendar and every member gets a Google Calendar invitation.</div>
                </div>

                {{-- STEP 2: review & confirm --}}
                <div id="mrqStepReview" class="mrq-hidden">
                    <div class="alert alert-danger py-2 mrq-hidden" id="mrqError2"></div>
                    <p class="mb-1 fw-semibold">Please review and confirm your meeting request</p>
                    <dl class="mrq-review mb-3">
                        <dt>Title</dt><dd id="mrqRvTitle"></dd>
                        <dt>Agenda</dt><dd id="mrqRvAgenda"></dd>
                        <dt>Priority</dt><dd id="mrqRvPrio"></dd>
                        <dt>Meeting room</dt><dd id="mrqRvRoom"></dd>
                        <dt>Members (<span id="mrqRvCount">0</span>)</dt><dd id="mrqRvMembers"></dd>
                        <dt>When</dt><dd id="mrqRvWhen"></dd>
                    </dl>
                    <div class="mrq-note"><span id="mrqRvNotify"></span> They can accept or decline from the meeting icon.</div>
                </div>

                {{-- STEP 3: sent --}}
                <div id="mrqStepDone" class="mrq-hidden text-center py-3">
                    <div class="mrq-success-icon"><i class="ri-checkbox-circle-fill"></i></div>
                    <h5 class="mt-2 mb-1" id="mrqDoneTitle">Meeting request sent</h5>
                    <p class="text-muted mb-2" id="mrqDoneText"></p>
                    <div class="alert alert-warning py-2 small mrq-hidden text-start" id="mrqDoneWarn"></div>
                    <div id="mrqDoneInvitees" class="d-flex flex-wrap justify-content-center gap-1"></div>
                    <div class="small text-muted mt-2">Track their answers under the <strong>Sent by you</strong> tab.</div>
                </div>

                {{-- Sent by you --}}
                <div id="mrqSent" class="mrq-hidden">
                    <div id="mrqSentList"><div class="text-muted">Loading…</div></div>
                </div>

                {{-- Inbox --}}
                <div id="mrqInbox" class="mrq-hidden">
                    <div id="mrqInboxList"><div class="text-muted">Loading…</div></div>
                </div>
            </div>
            <div class="modal-footer" id="mrqFooter">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal" id="mrqBtnCancel">Cancel</button>
                <button type="button" class="btn btn-outline-secondary mrq-hidden" id="mrqBtnBack">Back</button>
                <button type="button" class="btn btn-primary" id="mrqBtnReview">Review request</button>
                <button type="button" class="btn btn-success mrq-hidden" id="mrqBtnConfirm"><i class="ri-send-plane-fill me-1"></i>Confirm &amp; send</button>
                <a class="btn mrq-gmeet mrq-hidden" id="mrqBtnStart" href="#" target="_blank" rel="noopener noreferrer"><i class="ri-vidicon-fill me-1"></i>Join Google Meet</a>
                <a class="btn btn-outline-primary mrq-hidden" id="mrqBtnLobby" href="#">View meetings</a>
                <button type="button" class="btn btn-light mrq-hidden" id="mrqBtnClose" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        if (window.self !== window.top) { // never inside an embedded iframe
            var f = document.getElementById('mrqFab'); if (f) f.remove();
            return;
        }
        var URLS = {
            members: @json(route('meetings.members')),
            request: @json(route('meetings.request')),
            requests: @json(route('meetings.requests')),
            sent: @json(route('meetings.sent')),
            availability: @json(route('meetings.availability'))
        };
        var CSRF = @json(csrf_token());
        var $ = function (id) { return document.getElementById(id); };
        var hide = function (id, h) { $(id).classList.toggle('mrq-hidden', h !== false); };
        var members = null, selected = {}, step = 'form', tab = 'new', lastPayload = null, googleOk = true, tz = 'Asia/Kolkata';

        function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
        function call(method, url, body) {
            return fetch(url, {
                method: method, credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: body ? JSON.stringify(body) : undefined
            }).then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { return { ok: r.ok, status: r.status, data: j }; }); });
        }
        function firstError(res) {
            if (res.data && res.data.errors) { var k = Object.keys(res.data.errors)[0]; return res.data.errors[k][0]; }
            return (res.data && res.data.message) || 'Something went wrong. Please try again.';
        }

        // ---------- members picker ----------
        function loadMembers() {
            if (members) return;
            call('GET', URLS.members).then(function (res) {
                members = (res.data && res.data.members) || [];
                if (res.data && res.data.google && res.data.google.ok === false) {
                    googleOk = false;
                    showErr('mrqError', res.data.google.message || 'Google Meet is not configured.');
                    $('mrqBtnReview').disabled = true;
                }
                if (res.data && res.data.timezone) { tz = res.data.timezone; $('mrqTz').textContent = tz; }
                renderMembers();
            }).catch(function () { $('mrqMembers').innerHTML = '<div class="text-danger small py-2">Could not load team members.</div>'; });
        }
        function avatar(m) {
            // photo when the employee has one; initials otherwise (also when the photo fails to load)
            return '<span class="mrq-av" style="background:' + esc(m.colour || '#64748b') + '">' + esc(m.initials || '?')
                + (m.avatar ? '<img src="' + esc(m.avatar) + '" alt="" loading="lazy" onerror="this.remove()">' : '') + '</span>';
        }
        function renderMembers() {
            var q = $('mrqSearch').value.trim().toLowerCase();
            var html = '';
            (members || []).forEach(function (m) {
                var hay = (m.name + ' ' + m.designation).toLowerCase();
                if (q && hay.indexOf(q) === -1) return;
                html += '<label><input type="checkbox" value="' + m.id + '"' + (selected[m.id] ? ' checked' : '') + '>'
                    + avatar(m) + '<span>' + esc(m.name) + ' <small>' + esc(m.designation) + '</small></span></label>';
            });
            $('mrqMembers').innerHTML = html || '<div class="text-muted small py-2">No members match.</div>';
        }
        function renderChips() {
            var box = $('mrqChips'); box.innerHTML = '';
            Object.keys(selected).forEach(function (id) {
                var c = document.createElement('span'); c.className = 'mrq-chip';
                c.appendChild(document.createTextNode(selected[id]));
                var x = document.createElement('button'); x.type = 'button'; x.setAttribute('aria-label', 'Remove'); x.innerHTML = '&times;';
                x.addEventListener('click', function () { delete selected[id]; renderChips(); renderMembers(); });
                c.appendChild(x); box.appendChild(c);
            });
        }
        $('mrqMembers').addEventListener('change', function (e) {
            var cb = e.target; if (!cb || cb.type !== 'checkbox') return;
            var m = (members || []).filter(function (x) { return String(x.id) === cb.value; })[0];
            if (!m) return;
            if (cb.checked) selected[m.id] = m.name; else delete selected[m.id];
            renderChips();
        });
        $('mrqSearch').addEventListener('input', renderMembers);
        function nowIn(plusMin) {
            var d = new Date(Date.now() + (plusMin || 0) * 60000), p = {};
            new Intl.DateTimeFormat('en-GB', { timeZone: tz, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' })
                .formatToParts(d).forEach(function (x) { p[x.type] = x.value; });
            return { date: p.year + '-' + p.month + '-' + p.day, time: p.hour + ':' + p.minute };
        }
        $('mrqNowBtn').addEventListener('click', function () {
            var a = nowIn(0), b = nowIn(30);
            $('mrqDate').value = a.date; $('mrqStart').value = a.time; $('mrqEnd').value = b.date === a.date ? b.time : '23:59';
            checkAvail();
        });
        var availSeq = 0;
        function checkAvail() {
            var room = $('mrqRoom').value, date = $('mrqDate').value, box = $('mrqAvail');
            if (!room || !date) { box.innerHTML = ''; return; }
            var my = ++availSeq;
            call('GET', URLS.availability + '?room_slot=' + encodeURIComponent(room) + '&meeting_date=' + encodeURIComponent(date)).then(function (res) {
                if (my !== availSeq || !res.ok) return;
                var busy = res.data.busy || [];
                box.innerHTML = busy.length
                    ? '<span class="text-danger"><i class="ri-error-warning-line"></i> ' + esc(res.data.room) + ' is already booked:</span> ' + busy.map(function (b) { return '<span class="badge bg-light text-dark border">' + esc(b.from) + ' – ' + esc(b.to) + '</span>'; }).join(' ')
                    : '<span class="text-success"><i class="ri-checkbox-circle-line"></i> ' + esc(res.data.room) + ' is free all day.</span>';
            });
        }
        $('mrqRoom').addEventListener('change', checkAvail);
        $('mrqDate').addEventListener('change', checkAvail);
        var PRIO = { urgent: ['🔴', 'Urgent'], immediate: ['🟠', 'Immediate'], normal: ['🟢', 'Normal'] };
        function prioBadge(p) {
            p = p || { label: 'Normal', hex: '#16a34a' };
            var key = String(p.label || 'Normal').toLowerCase();
            return '<span class="mrq-prio ' + esc(key) + '"><i></i>' + esc(p.label || 'Normal') + '</span>';
        }

        // ---------- steps ----------
        function setStep(s) {
            step = s;
            hide('mrqStepForm', s !== 'form'); hide('mrqStepReview', s !== 'review'); hide('mrqStepDone', s !== 'done');
            hide('mrqBtnCancel', s !== 'form'); hide('mrqBtnReview', s !== 'form');
            hide('mrqBtnBack', s !== 'review'); hide('mrqBtnConfirm', s !== 'review');
            hide('mrqBtnClose', s !== 'done'); hide('mrqBtnLobby', s !== 'done'); hide('mrqBtnStart', true);
            $('mrqError').classList.add('mrq-hidden'); $('mrqError2').classList.add('mrq-hidden');
        }
        function setTab(t) {
            tab = t;
            document.querySelectorAll('#mrqTabs .mrq-tab').forEach(function (b) { b.classList.toggle('active', b.getAttribute('data-tab') === t); });
            var isNew = t === 'new';
            hide('mrqInbox', t !== 'inbox');
            hide('mrqSent', t !== 'sent');
            if (isNew) { setStep(step); }
            else {
                ['mrqStepForm', 'mrqStepReview', 'mrqStepDone'].forEach(function (id) { hide(id, true); });
                ['mrqBtnCancel', 'mrqBtnReview', 'mrqBtnBack', 'mrqBtnConfirm', 'mrqBtnLobby', 'mrqBtnStart'].forEach(function (id) { hide(id, true); });
                hide('mrqBtnClose', false);
                if (t === 'inbox') loadInbox(); else loadSent();
            }
        }
        document.querySelectorAll('#mrqTabs .mrq-tab').forEach(function (b) {
            b.addEventListener('click', function () { setTab(b.getAttribute('data-tab')); });
        });

        function showErr(id, msg) { var e = $(id); e.textContent = msg; e.classList.remove('mrq-hidden'); }

        function collect() {
            var prio = document.querySelector('input[name="mrqPrio"]:checked');
            return {
                title: $('mrqTitleInput').value.trim(),
                agenda: $('mrqAgenda').value.trim(),
                invitee_ids: Object.keys(selected).map(Number),
                priority: prio ? prio.value : 'normal',
                room_slot: $('mrqRoom').value ? Number($('mrqRoom').value) : null,
                meeting_date: $('mrqDate').value,
                start_time: $('mrqStart').value,
                end_time: $('mrqEnd').value
            };
        }

        $('mrqBtnReview').addEventListener('click', function () {
            if (!googleOk) return showErr('mrqError', 'Google Meet is not configured. A manager must connect the Google account first.');
            var p = collect();
            if (!p.title) return showErr('mrqError', 'Please enter a meeting title.');
            if (p.agenda.length < 1) return showErr('mrqError', 'Please add a meeting agenda.');
            if (!p.room_slot) return showErr('mrqError', 'Choose one of the four meeting rooms.');
            if (!p.meeting_date || !p.start_time || !p.end_time) return showErr('mrqError', 'Pick the date, start time and end time.');
            if (p.end_time <= p.start_time) return showErr('mrqError', 'The end time must be after the start time.');
            if (!p.invitee_ids.length) return showErr('mrqError', 'Add at least one member to the meeting.');
            lastPayload = p;
            $('mrqRvTitle').textContent = p.title;
            $('mrqRvAgenda').textContent = p.agenda;
            $('mrqRvPrio').textContent = PRIO[p.priority][0] + ' ' + PRIO[p.priority][1];
            $('mrqRvRoom').textContent = 'Meeting Room ' + p.room_slot + ' (Google Meet)';
            $('mrqRvCount').textContent = p.invitee_ids.length;
            $('mrqRvMembers').textContent = Object.keys(selected).map(function (id) { return selected[id]; }).join(', ');
            $('mrqRvWhen').textContent = p.meeting_date + ', ' + p.start_time + ' – ' + p.end_time + ' (' + tz + ')';
            $('mrqRvNotify').textContent = p.invitee_ids.length + ' member' + (p.invitee_ids.length === 1 ? '' : 's') + ' will get a Google Calendar invitation with the Google Meet link.';
            setStep('review');
        });
        $('mrqBtnBack').addEventListener('click', function () { setStep('form'); });

        $('mrqBtnConfirm').addEventListener('click', function () {
            var btn = this; btn.disabled = true; btn.textContent = 'Creating Google Meet…';
            var reset = function () { btn.disabled = false; btn.innerHTML = '<i class="ri-send-plane-fill me-1"></i>Confirm &amp; send'; };
            call('POST', URLS.request, lastPayload).then(function (res) {
                reset();
                if (!res.ok || !res.data.ok) return showErr('mrqError2', firstError(res));
                var d = res.data;
                $('mrqDoneTitle').textContent = d.meeting.meet_url ? 'Google Meet created' : 'Meeting saved';
                $('mrqDoneText').textContent = '"' + d.meeting.title + '" · ' + d.meeting.room + ' · ' + d.meeting.scheduled_at + '. '
                    + d.meeting.invited + ' member' + (d.meeting.invited === 1 ? ' has' : 's have') + ' been invited through Google Calendar.';
                var warn = $('mrqDoneWarn');
                warn.classList.toggle('mrq-hidden', !d.google_error);
                warn.textContent = d.google_error ? 'Google Meet link not ready: ' + d.google_error : '';
                $('mrqBtnStart').href = d.meeting.meet_url || d.details_url;
                $('mrqBtnLobby').href = d.details_url;
                $('mrqBtnLobby').textContent = 'Open meeting details';
                var inv = $('mrqDoneInvitees'); inv.innerHTML = '';
                (d.meeting.invitees || []).forEach(function (n) { var b = document.createElement('span'); b.className = 'badge bg-warning text-dark'; b.textContent = n + ' · waiting for reply'; inv.appendChild(b); });
                setStep('done');
                hide('mrqBtnStart', !d.meeting.meet_url);
                // reset form for next time
                $('mrqTitleInput').value = ''; $('mrqAgenda').value = ''; selected = {}; $('mrqRoom').value = ''; $('mrqDate').value = ''; $('mrqStart').value = ''; $('mrqEnd').value = ''; $('mrqAvail').innerHTML = '';
                renderChips(); renderMembers();
                poll(false);
            }).catch(function () {
                reset();
                showErr('mrqError2', 'Network problem. Your request was not sent — please try again.');
            });
        });

        // ---------- sent by me: invited members + their answers ----------
        var STATUS = {
            in_room: ['In room', 'bg-success'], left: ['Left', 'bg-secondary'], accepted: ['Accepted', 'bg-success'],
            declined: ['Declined', 'bg-danger'], pending: ['Waiting for reply', 'bg-warning text-dark']
        };
        function loadSent() {
            var box = $('mrqSentList'); box.innerHTML = '<div class="text-muted">Loading…</div>';
            call('GET', URLS.sent).then(function (res) {
                var list = (res.data && res.data.meetings) || [];
                if (!res.ok) { box.innerHTML = '<div class="text-danger">Could not load your meeting requests.</div>'; return; }
                if (!list.length) { box.innerHTML = '<div class="text-muted py-3 text-center">You have no upcoming or live meetings. Use the <strong>Request a meeting</strong> tab to create one.</div>'; return; }
                box.innerHTML = '';
                list.forEach(function (m) {
                    var div = document.createElement('div'); div.className = 'mrq-req';
                    var counts = { accepted: 0, declined: 0, pending: 0 };
                    m.invitees.forEach(function (i) { if (i.status === 'declined') counts.declined++; else if (i.status === 'pending') counts.pending++; else counts.accepted++; });
                    var chips = m.invitees.map(function (i) {
                        var st = STATUS[i.status] || STATUS.pending;
                        return '<span class="badge ' + st[1] + ' me-1 mb-1" style="font-size:.78rem">' + esc(i.name) + ' &middot; ' + st[0] + '</span>';
                    }).join('');
                    var join = m.meet_url
                        ? '<a class="btn btn-sm mrq-gmeet" target="_blank" rel="noopener noreferrer" href="' + esc(m.meet_url) + '"><i class="ri-vidicon-fill"></i> Join Google Meet</a> '
                        : (m.google ? '<span class="badge bg-warning text-dark">Google Meet link ' + (m.google_status === 'failed' ? 'not created' : 'pending') + '</span> ' : '');
                    div.innerHTML = '<h6>' + esc(m.title) + ' ' + prioBadge(m.priority) + (m.live ? ' <span class="badge bg-success">Live now</span>' : '') + '</h6>'
                        + '<div class="small text-muted mb-1">' + esc(m.scheduled_at || (m.live ? 'happening now' : 'No time set')) + (m.room ? ' &middot; <span class="mrq-room">' + esc(m.room) + '</span>' : '') + '</div>'
                        + (m.agenda ? '<div class="small mb-2" style="white-space:pre-line"><span class="text-muted">Agenda:</span> ' + esc(m.agenda) + '</div>' : '')
                        + '<div class="small mb-1"><strong>' + counts.accepted + '</strong> accepted &middot; <strong>' + counts.declined + '</strong> declined &middot; <strong>' + counts.pending + '</strong> waiting</div>'
                        + '<div class="mb-2">' + (chips || '<span class="text-muted small">No members invited.</span>') + '</div>'
                        + join + '<a class="btn btn-sm btn-light" href="' + esc(m.details_url || m.room_url) + '">Details</a>';
                    box.appendChild(div);
                });
            }).catch(function () { box.innerHTML = '<div class="text-danger">Could not load your meeting requests.</div>'; });
        }

        // ---------- inbox (requests for me) ----------
        var seenKey = 'mrqSeenRequests';
        function seenIds() { try { return JSON.parse(localStorage.getItem(seenKey) || '[]'); } catch (e) { return []; } }
        function rememberSeen(ids) { try { localStorage.setItem(seenKey, JSON.stringify(ids.slice(-100))); } catch (e) {} }

        function setBadge(n) {
            var b = $('mrqBadge'), tb = $('mrqTabBadge');
            b.textContent = n; b.style.display = n ? '' : 'none'; b.classList.remove('mrq-hidden');
            tb.textContent = n; tb.classList.toggle('mrq-hidden', !n);
            $('mrqFab').classList.toggle('has-pending', n > 0);
            // same count on the sidebar ("Tasks" menu + "Meeting Room" item)
            document.querySelectorAll('[data-mrq-badge]').forEach(function (el) { el.textContent = n; el.style.display = n ? '' : 'none'; });
        }

        var pollTimer = null;
        function poll(showToast) {
            return call('GET', URLS.requests).then(function (res) {
                if (!res.ok) return;
                var list = res.data.requests || [];
                setBadge(res.data.count || 0);
                if (showToast !== false) {
                    var seen = seenIds();
                    var fresh = list.filter(function (r) { return seen.indexOf(r.id) === -1; });
                    if (fresh.length) {
                        var r = fresh[0];
                        var t = $('mrqToast');
                        t.innerHTML = '<strong>' + esc(r.host || 'Someone') + '</strong> requested a meeting'
                            + (fresh.length > 1 ? ' (+' + (fresh.length - 1) + ' more)' : '') + ':<br>' + esc(r.title);
                        t.style.display = '';
                        t.onclick = function () { t.style.display = 'none'; openModal('inbox'); };
                        setTimeout(function () { t.style.display = 'none'; }, 30000);
                        rememberSeen(seen.concat(fresh.map(function (x) { return x.id; })));
                    }
                }
                return list;
            }).catch(function () {});
        }

        function loadInbox() {
            $('mrqInboxList').innerHTML = '<div class="text-muted">Loading…</div>';
            poll(false).then(function (list) {
                list = list || [];
                rememberSeen(seenIds().concat(list.map(function (x) { return x.id; })));
                if (!list.length) {
                    $('mrqInboxList').innerHTML = '<div class="text-muted py-3 text-center">No pending meeting requests.</div>';
                    return;
                }
                $('mrqInboxList').innerHTML = '';
                list.forEach(function (r) { $('mrqInboxList').appendChild(requestCard(r)); });
            });
        }

        function requestCard(r) {
            var div = document.createElement('div'); div.className = 'mrq-req';
            div.innerHTML = '<h6>' + esc(r.title) + ' ' + prioBadge(r.priority) + (r.live ? ' <span class="badge bg-success">Live now</span>' : '') + '</h6>'
                + '<div class="small text-muted mb-1">From <strong>' + esc(r.host || '—') + '</strong> &middot; '
                + esc(r.scheduled_at || (r.live ? 'happening now' : 'time not set')) + (r.room ? ' &middot; <span class="mrq-room">' + esc(r.room) + '</span>' : '')
                + (r.requested_at ? ' &middot; requested ' + esc(r.requested_at) : '') + '</div>'
                + (r.agenda ? '<div class="small mb-2" style="white-space:pre-line"><span class="text-muted">Agenda:</span> ' + esc(r.agenda) + '</div>' : '')
                + '<div class="d-flex gap-2 flex-wrap" data-actions></div><div class="small mt-2" data-result></div>';
            var actions = div.querySelector('[data-actions]'), result = div.querySelector('[data-result]');
            function btn(label, cls, fn) { var b = document.createElement('button'); b.type = 'button'; b.className = 'btn btn-sm ' + cls; b.innerHTML = label; b.addEventListener('click', fn); actions.appendChild(b); return b; }
            function answer(resp) {
                if (resp === 'declined' && !confirm('Decline "' + r.title + '"? The host will see that you declined.')) return;
                actions.querySelectorAll('button').forEach(function (b) { b.disabled = true; });
                call('POST', r.rsvp_url, { response: resp }).then(function (res) {
                    if (!res.ok || !res.data.ok) {
                        actions.querySelectorAll('button').forEach(function (b) { b.disabled = false; });
                        result.innerHTML = '<span class="text-danger">' + esc(firstError(res)) + '</span>';
                        return;
                    }
                    actions.innerHTML = '';
                    if (resp === 'accepted') {
                        result.innerHTML = '<span class="text-success fw-semibold"><i class="ri-checkbox-circle-fill"></i> You accepted.</span>'
                            + (r.meet_url
                                ? ' <a class="btn btn-sm mrq-gmeet ms-2" target="_blank" rel="noopener noreferrer" href="' + esc(r.meet_url) + '"><i class="ri-vidicon-fill"></i> Join Google Meet</a>'
                                : ' <a class="btn btn-success btn-sm ms-2" href="' + esc(res.data.room_url) + '"><i class="ri-vidicon-line"></i> Meeting details</a>')
                            + (r.google || res.data.live ? '' : '<div class="text-muted mt-1">Not started yet — you can join once the host starts it.</div>');
                    } else {
                        result.innerHTML = '<span class="text-secondary"><i class="ri-close-circle-fill"></i> You declined.</span>';
                    }
                    poll(false);
                }).catch(function () {
                    actions.querySelectorAll('button').forEach(function (b) { b.disabled = false; });
                    result.innerHTML = '<span class="text-danger">Network problem. Please try again.</span>';
                });
            }
            // Join is always offered (joining counts as accepting); Accept only while unanswered.
            if (r.meet_url) {
                var j = document.createElement('a'); j.className = 'btn btn-sm mrq-gmeet'; j.target = '_blank'; j.rel = 'noopener noreferrer'; j.href = r.meet_url;
                j.innerHTML = '<i class="ri-vidicon-fill"></i> Join Google Meet'; actions.appendChild(j);
            } else {
                btn('<i class="ri-vidicon-line"></i> ' + (r.google ? 'Meeting details' : 'Join meeting'), 'btn-primary', function () { window.location.href = r.room_url; });
            }
            if (r.rsvp !== 'accepted') btn('<i class="ri-check-line"></i> Accept', 'btn-success', function () { answer('accepted'); });
            btn('<i class="ri-close-line"></i> Decline', 'btn-outline-danger', function () { answer('declined'); });
            return div;
        }

        // ---------- open / close ----------
        function openModal(t) {
            var el = $('mrqModal');
            if (window.bootstrap && window.bootstrap.Modal) window.bootstrap.Modal.getOrCreateInstance(el).show();
            setTab(t || 'new');
        }
        $('mrqModal').addEventListener('show.bs.modal', function (e) {
            loadMembers();
            if (e.relatedTarget && e.relatedTarget.id === 'mrqFab') {
                // Clicking the floating icon: go straight to pending requests if someone is waiting on you.
                var pending = parseInt($('mrqBadge').textContent, 10) > 0 && $('mrqBadge').style.display !== 'none';
                if (step === 'done') setStep('form');
                setTab(pending ? 'inbox' : 'new');
            } else if (tab === 'new' && step === 'done') { setStep('form'); }
        });
        $('mrqModal').addEventListener('shown.bs.modal', function () { if (tab === 'new' && step === 'form') $('mrqTitleInput').focus(); });
        setStep('form');

        // ---------- background poll for incoming requests ----------
        function startPolling() {
            poll(true);
            pollTimer = setInterval(function () { if (!document.hidden) poll(true); }, 30000);
        }
        document.addEventListener('visibilitychange', function () { if (!document.hidden) poll(true); });
        if (document.readyState === 'complete') startPolling(); else window.addEventListener('load', startPolling);
    })();
</script>
@endunless
@endauth
