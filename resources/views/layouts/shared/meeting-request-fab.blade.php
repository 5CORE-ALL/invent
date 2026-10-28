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
                        <label class="form-label fw-semibold d-block">When</label>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="mrqWhen" id="mrqWhenNow" value="now" checked>
                            <label class="form-check-label" for="mrqWhenNow">Start now</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="mrqWhen" id="mrqWhenLater" value="later">
                            <label class="form-check-label" for="mrqWhenLater">Schedule for later</label>
                        </div>
                        <input type="datetime-local" id="mrqWhenAt" class="form-control mt-2 mrq-hidden" style="max-width:260px">
                    </div>
                    <div class="mrq-note"><i class="ri-record-circle-line me-1"></i>Every meeting is <strong>recorded</strong>. AI writes the minutes and creates follow-up tasks afterwards.</div>
                </div>

                {{-- STEP 2: review & confirm --}}
                <div id="mrqStepReview" class="mrq-hidden">
                    <div class="alert alert-danger py-2 mrq-hidden" id="mrqError2"></div>
                    <p class="mb-1 fw-semibold">Please review and confirm your meeting request</p>
                    <dl class="mrq-review mb-3">
                        <dt>Title</dt><dd id="mrqRvTitle"></dd>
                        <dt>Agenda</dt><dd id="mrqRvAgenda"></dd>
                        <dt>Members (<span id="mrqRvCount">0</span>)</dt><dd id="mrqRvMembers"></dd>
                        <dt>When</dt><dd id="mrqRvWhen"></dd>
                    </dl>
                    <div class="mrq-note"><span id="mrqRvNotify"></span> They can accept or decline from the meeting icon. The meeting will be recorded.</div>
                </div>

                {{-- STEP 3: sent --}}
                <div id="mrqStepDone" class="mrq-hidden text-center py-3">
                    <div class="mrq-success-icon"><i class="ri-checkbox-circle-fill"></i></div>
                    <h5 class="mt-2 mb-1" id="mrqDoneTitle">Meeting request sent</h5>
                    <p class="text-muted mb-2" id="mrqDoneText"></p>
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
                <a class="btn btn-success mrq-hidden" id="mrqBtnStart" href="#"><i class="ri-vidicon-line me-1"></i>Start meeting now</a>
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
            sent: @json(route('meetings.sent'))
        };
        var CSRF = @json(csrf_token());
        var $ = function (id) { return document.getElementById(id); };
        var hide = function (id, h) { $(id).classList.toggle('mrq-hidden', h !== false); };
        var members = null, selected = {}, step = 'form', tab = 'new', lastPayload = null;

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
                renderMembers();
            }).catch(function () { $('mrqMembers').innerHTML = '<div class="text-danger small py-2">Could not load team members.</div>'; });
        }
        function renderMembers() {
            var q = $('mrqSearch').value.trim().toLowerCase();
            var html = '';
            (members || []).forEach(function (m) {
                var hay = (m.name + ' ' + m.designation).toLowerCase();
                if (q && hay.indexOf(q) === -1) return;
                html += '<label><input type="checkbox" value="' + m.id + '"' + (selected[m.id] ? ' checked' : '') + '>'
                    + '<span>' + esc(m.name) + ' <small>' + esc(m.designation) + '</small></span></label>';
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
        document.querySelectorAll('input[name="mrqWhen"]').forEach(function (r) {
            r.addEventListener('change', function () { hide('mrqWhenAt', $('mrqWhenNow').checked); });
        });

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
            return {
                title: $('mrqTitleInput').value.trim(),
                agenda: $('mrqAgenda').value.trim(),
                invitee_ids: Object.keys(selected).map(Number),
                when: $('mrqWhenNow').checked ? 'now' : 'later',
                // exact instant, so the time is right whatever timezone the browser / server are in
                scheduled_at: $('mrqWhenAt').value ? new Date($('mrqWhenAt').value).toISOString() : null
            };
        }

        $('mrqBtnReview').addEventListener('click', function () {
            var p = collect();
            if (!p.title) return showErr('mrqError', 'Please enter a meeting title.');
            if (p.agenda.length < 5) return showErr('mrqError', 'Please add a meeting agenda.');
            if (!p.invitee_ids.length) return showErr('mrqError', 'Add at least one member to the meeting.');
            if (p.when === 'later') {
                if (!p.scheduled_at) return showErr('mrqError', 'Pick a date and time, or choose "Start now".');
                if (new Date(p.scheduled_at).getTime() < Date.now() - 5 * 60000) return showErr('mrqError', 'The meeting time cannot be in the past.');
            }
            lastPayload = p;
            $('mrqRvTitle').textContent = p.title;
            $('mrqRvAgenda').textContent = p.agenda;
            $('mrqRvCount').textContent = p.invitee_ids.length;
            $('mrqRvMembers').textContent = Object.keys(selected).map(function (id) { return selected[id]; }).join(', ');
            $('mrqRvWhen').textContent = p.when === 'now' ? 'Start now (you join the room immediately)'
                : new Date(p.scheduled_at).toLocaleString([], { dateStyle: 'full', timeStyle: 'short' });
            $('mrqRvNotify').textContent = p.invitee_ids.length + ' member' + (p.invitee_ids.length === 1 ? '' : 's') + ' will get this request.';
            setStep('review');
        });
        $('mrqBtnBack').addEventListener('click', function () { setStep('form'); });

        $('mrqBtnConfirm').addEventListener('click', function () {
            var btn = this; btn.disabled = true; btn.textContent = 'Sending…';
            call('POST', URLS.request, lastPayload).then(function (res) {
                btn.disabled = false; btn.innerHTML = '<i class="ri-send-plane-fill me-1"></i>Confirm &amp; send';
                if (!res.ok || !res.data.ok) return showErr('mrqError2', firstError(res));
                var d = res.data;
                $('mrqDoneText').textContent = d.when === 'now'
                    ? d.meeting.invited + ' member' + (d.meeting.invited === 1 ? ' has' : 's have') + ' been notified. Start the meeting and they can join when they accept.'
                    : '"' + d.meeting.title + '" is scheduled for ' + d.meeting.scheduled_at + '. ' + d.meeting.invited + ' member' + (d.meeting.invited === 1 ? ' has' : 's have') + ' been notified.';
                $('mrqBtnStart').href = d.room_url; $('mrqBtnLobby').href = d.meetings_url;
                var inv = $('mrqDoneInvitees'); inv.innerHTML = '';
                (d.meeting.invitees || []).forEach(function (n) { var b = document.createElement('span'); b.className = 'badge bg-warning text-dark'; b.textContent = n + ' · waiting for reply'; inv.appendChild(b); });
                setStep('done');
                hide('mrqBtnStart', d.when !== 'now');
                // reset form for next time
                $('mrqTitleInput').value = ''; $('mrqAgenda').value = ''; selected = {}; $('mrqWhenAt').value = '';
                $('mrqWhenNow').checked = true; hide('mrqWhenAt', true); renderChips(); renderMembers();
            }).catch(function () {
                btn.disabled = false; btn.innerHTML = '<i class="ri-send-plane-fill me-1"></i>Confirm &amp; send';
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
                    div.innerHTML = '<h6>' + esc(m.title) + (m.live ? ' <span class="badge bg-success">Live now</span>' : '') + '</h6>'
                        + '<div class="small text-muted mb-1">' + esc(m.scheduled_at || (m.live ? 'happening now' : 'Start now / no time set')) + '</div>'
                        + (m.agenda ? '<div class="small mb-2" style="white-space:pre-line"><span class="text-muted">Agenda:</span> ' + esc(m.agenda) + '</div>' : '')
                        + '<div class="small mb-1"><strong>' + counts.accepted + '</strong> accepted &middot; <strong>' + counts.declined + '</strong> declined &middot; <strong>' + counts.pending + '</strong> waiting</div>'
                        + '<div class="mb-2">' + (chips || '<span class="text-muted small">No members invited.</span>') + '</div>'
                        + '<a class="btn btn-sm ' + (m.live ? 'btn-success' : 'btn-primary') + '" href="' + esc(m.room_url) + '">' + (m.live ? 'Join meeting' : 'Start meeting') + '</a>';
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
            div.innerHTML = '<h6>' + esc(r.title) + (r.live ? ' <span class="badge bg-success">Live now</span>' : '') + '</h6>'
                + '<div class="small text-muted mb-1">From <strong>' + esc(r.host || '—') + '</strong> &middot; '
                + esc(r.scheduled_at || (r.live ? 'happening now' : 'time not set')) + '</div>'
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
                            + (res.data.live ? ' <a class="btn btn-success btn-sm ms-2" href="' + esc(res.data.room_url) + '">Join now</a>' : ' The host will start the meeting at the scheduled time.');
                    } else {
                        result.innerHTML = '<span class="text-secondary"><i class="ri-close-circle-fill"></i> You declined.</span>';
                    }
                    poll(false);
                }).catch(function () {
                    actions.querySelectorAll('button').forEach(function (b) { b.disabled = false; });
                    result.innerHTML = '<span class="text-danger">Network problem. Please try again.</span>';
                });
            }
            btn('<i class="ri-check-line"></i> Accept', 'btn-success', function () { answer('accepted'); });
            btn('<i class="ri-close-line"></i> Decline', 'btn-outline-danger', function () { answer('declined'); });
            if (r.live) btn('Join now', 'btn-outline-primary', function () { window.location.href = r.room_url; });
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
