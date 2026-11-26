{{--
    Page attendance modal (gen-z style) + styles + script.
    Needs the badge partial on the same page and a csrf-token meta tag.
    Usage: @include('partials.page-attendance-modal', ['paKey' => 'order-fulfillment.pending'])
--}}
<style>
    .pa-badge {
        position: relative; display: inline-flex; align-items: center; gap: 7px;
        border: 0; cursor: pointer; color: #fff; font-weight: 700; font-size: 0.86rem; line-height: 1.15;
        padding: 0.3rem 0.8rem 0.3rem 0.65rem; border-radius: 999px; white-space: nowrap;
        background: linear-gradient(135deg, #059669 0%, #06b6d4 55%, #6366f1 100%);
        box-shadow: 0 6px 16px -6px rgba(6, 182, 212, 0.75);
        transition: transform .15s ease, box-shadow .15s ease, filter .15s ease;
    }
    .pa-badge:hover { transform: translateY(-1px) scale(1.03); box-shadow: 0 10px 22px -6px rgba(6, 182, 212, 0.9); filter: saturate(1.15); }
    .pa-badge:active { transform: scale(.97); }
    .pa-badge b { font-variant-numeric: tabular-nums; font-size: 0.95rem; }
    .pa-badge-dot { width: 8px; height: 8px; border-radius: 50%; background: #a7f3d0; box-shadow: 0 0 0 0 rgba(167, 243, 208, .8); animation: pa-pulse 1.8s infinite; }
    .pa-badge.is-pending .pa-badge-dot { background: #fde68a; box-shadow: 0 0 0 0 rgba(253, 230, 138, .85); animation-name: pa-pulse-amber; }
    @keyframes pa-pulse { 70% { box-shadow: 0 0 0 7px rgba(167, 243, 208, 0); } 100% { box-shadow: 0 0 0 0 rgba(167, 243, 208, 0); } }
    @keyframes pa-pulse-amber { 70% { box-shadow: 0 0 0 7px rgba(253, 230, 138, 0); } 100% { box-shadow: 0 0 0 0 rgba(253, 230, 138, 0); } }
    .pa-badge-me { font-size: .68rem; font-weight: 800; padding: 1px 8px; border-radius: 99px; background: #f59e0b; color: #1f1300; animation: pa-bob 1.6s ease-in-out infinite; }
    @keyframes pa-bob { 50% { transform: translateY(-2px); } }

    .pa-modal .modal-dialog { max-width: 980px; }
    .pa-modal .modal-content {
        border: 0; border-radius: 26px; overflow: hidden; color: #e6ecff;
        background: radial-gradient(1100px 480px at 90% -10%, rgba(6, 182, 212, .25), transparent 60%), #0d1224;
        box-shadow: 0 30px 80px -20px rgba(0, 0, 0, .7);
    }
    .pa-head { position: relative; padding: 22px 26px 20px; color: #fff; overflow: hidden; background: linear-gradient(135deg, #059669 0%, #06b6d4 55%, #6366f1 100%); }
    .pa-head::after { content: ''; position: absolute; right: -60px; top: -70px; width: 240px; height: 240px; border-radius: 50%; background: rgba(255,255,255,.15); }
    .pa-head .btn-close { position: absolute; right: 18px; top: 18px; z-index: 3; filter: invert(1) grayscale(1) brightness(2); opacity: .9; }
    .pa-pill { display: inline-flex; align-items: center; gap: 6px; font-size: .68rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; padding: 4px 11px; border-radius: 99px; background: rgba(255,255,255,.22); position: relative; z-index: 1; }
    .pa-head h5 { margin: 8px 0 2px; font-weight: 800; font-size: 1.45rem; letter-spacing: -.01em; position: relative; z-index: 1; color: #fff; }
    .pa-sub { font-size: .78rem; opacity: .92; position: relative; z-index: 1; }
    .pa-stats { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 16px; position: relative; z-index: 1; }
    .pa-stat { background: rgba(255,255,255,.18); backdrop-filter: blur(8px); border-radius: 16px; padding: 9px 15px; min-width: 124px; }
    .pa-stat b { display: block; font-size: 1.35rem; font-weight: 800; line-height: 1.1; font-variant-numeric: tabular-nums; }
    .pa-stat span { font-size: .66rem; text-transform: uppercase; letter-spacing: .08em; opacity: .9; }
    .pa-body { padding: 16px 22px 22px; max-height: 68vh; overflow-y: auto; }
    .pa-tools { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 14px; }
    .pa-spacer { flex: 1; }
    .pa-btn { border: 1px solid rgba(255,255,255,.16); background: transparent; color: #c7d2fe; border-radius: 99px; padding: 6px 14px; font-size: .78rem; font-weight: 700; cursor: pointer; transition: all .15s; }
    .pa-btn:hover { background: rgba(255,255,255,.08); }
    .pa-btn.is-on { background: linear-gradient(135deg, #059669, #06b6d4); color: #fff; border-color: transparent; }
    .pa-msg { display: none; margin-bottom: 12px; padding: 9px 14px; border-radius: 14px; font-size: .8rem; font-weight: 700; }
    .pa-msg.ok { display: block; background: rgba(52, 211, 153, .16); color: #6ee7b7; }
    .pa-msg.err { display: block; background: rgba(251, 113, 133, .16); color: #fda4af; }

    .pa-cta { border-radius: 22px; padding: 18px 20px; margin-bottom: 16px; background: linear-gradient(135deg, rgba(5,150,105,.35), rgba(99,102,241,.35)); border: 1px solid rgba(255,255,255,.16); }
    .pa-cta h6 { margin: 0 0 4px; font-weight: 800; font-size: 1.05rem; color: #fff; }
    .pa-cta p { margin: 0 0 12px; font-size: .78rem; color: rgba(230,236,255,.8); }
    .pa-cta.is-ok { background: rgba(52, 211, 153, .12); border-color: rgba(52, 211, 153, .35); }
    .pa-note { width: 100%; border-radius: 14px; border: 1px solid rgba(255,255,255,.18); background: rgba(0,0,0,.25); color: #fff; padding: 9px 12px; font-size: .82rem; resize: vertical; min-height: 44px; margin-bottom: 10px; }
    .pa-note::placeholder { color: rgba(230,236,255,.45); }
    .pa-go { border: 0; border-radius: 99px; padding: 10px 22px; font-weight: 800; font-size: .92rem; color: #052e2b; cursor: pointer; background: linear-gradient(135deg, #6ee7b7, #67e8f9); box-shadow: 0 10px 24px -8px rgba(103, 232, 249, .8); transition: transform .15s, filter .15s; }
    .pa-go:hover { transform: translateY(-2px) scale(1.02); filter: saturate(1.2); }
    .pa-go:disabled { opacity: .6; cursor: wait; transform: none; }

    .pa-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(290px, 1fr)); gap: 12px; }
    .pa-card { background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.09); border-radius: 20px; padding: 14px 15px; transition: transform .15s, background .15s; }
    .pa-card:hover { background: rgba(255,255,255,.08); transform: translateY(-2px); }
    .pa-card.is-me { border-color: rgba(103, 232, 249, .55); box-shadow: 0 0 0 2px rgba(103, 232, 249, .12); }
    .pa-who { display: flex; align-items: center; gap: 11px; margin-bottom: 10px; }
    .pa-av { flex: 0 0 auto; width: 42px; height: 42px; border-radius: 15px; display: grid; place-items: center; font-weight: 800; font-size: .92rem; color: #fff; }
    .pa-name { font-weight: 800; font-size: .98rem; color: #fff; line-height: 1.15; }
    .pa-meta { font-size: .7rem; color: rgba(230,236,255,.55); }
    .pa-freqs { display: flex; flex-direction: column; gap: 6px; }
    .pa-freq { display: flex; align-items: center; gap: 8px; font-size: .76rem; padding: 6px 10px; border-radius: 12px; background: rgba(0,0,0,.2); }
    .pa-freq .pa-f { font-weight: 800; min-width: 44px; }
    .pa-freq .pa-t { flex: 1; color: rgba(230,236,255,.7); }
    .pa-freq.is-done { background: rgba(52, 211, 153, .13); } .pa-freq.is-done .pa-f { color: #6ee7b7; }
    .pa-freq.is-due { background: rgba(251, 191, 36, .12); } .pa-freq.is-due .pa-f { color: #fcd34d; }
    .pa-freq.is-upcoming .pa-f { color: #a5b4fc; }
    .pa-tag { font-size: .66rem; font-weight: 800; padding: 2px 8px; border-radius: 99px; white-space: nowrap; }
    .pa-tag.done { background: rgba(52, 211, 153, .2); color: #6ee7b7; }
    .pa-tag.due { background: rgba(251, 191, 36, .2); color: #fcd34d; }
    .pa-tag.missed { background: rgba(251, 113, 133, .2); color: #fda4af; }
    .pa-tag.freq { background: rgba(129, 140, 248, .22); color: #c7d2fe; }
    .pa-foot { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; margin-top: 9px; font-size: .7rem; color: rgba(230,236,255,.55); }

    .pa-list { display: flex; flex-direction: column; gap: 6px; }
    .pa-row { display: grid; grid-template-columns: 34px minmax(0,1.2fr) 70px 90px minmax(0,1.6fr) auto; align-items: center; gap: 10px; padding: 8px 12px; border-radius: 14px; background: rgba(255,255,255,.05); font-size: .78rem; }
    .pa-row .pa-av { width: 32px; height: 32px; border-radius: 11px; font-size: .75rem; }
    .pa-row .pa-name { font-size: .84rem; }
    .pa-row small { display: block; color: rgba(230,236,255,.5); font-size: .68rem; }
    .pa-row .pa-note-txt { color: rgba(230,236,255,.75); overflow: hidden; text-overflow: ellipsis; }
    @media (max-width: 760px) { .pa-row { grid-template-columns: 34px minmax(0,1fr) auto; } .pa-row > :nth-child(3), .pa-row > :nth-child(5) { display: none; } }
    .pa-empty { text-align: center; padding: 40px 10px; color: rgba(230,236,255,.7); }
    .pa-empty big { display: block; font-size: 2.3rem; margin-bottom: 6px; }
    .pa-select { background: rgba(0,0,0,.25); color: #fff; border: 1px solid rgba(255,255,255,.18); border-radius: 99px; padding: 5px 12px; font-size: .78rem; }
    .pa-select option { color: #000; }

    .pa-assign { display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr); gap: 14px; }
    @media (max-width: 760px) { .pa-assign { grid-template-columns: 1fr; } }
    .pa-panel { background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.09); border-radius: 20px; padding: 14px 16px; }
    .pa-panel h6 { font-weight: 800; font-size: .86rem; margin: 0 0 10px; color: #fff; }
    .pa-search { width: 100%; border-radius: 99px; border: 1px solid rgba(255,255,255,.18); background: rgba(0,0,0,.25); color: #fff; padding: 6px 14px; font-size: .8rem; margin-bottom: 8px; }
    .pa-users { max-height: 250px; overflow-y: auto; display: flex; flex-direction: column; gap: 4px; padding-right: 4px; }
    .pa-check { display: flex; align-items: center; gap: 10px; padding: 6px 10px; border-radius: 12px; cursor: pointer; font-size: .82rem; font-weight: 700; background: rgba(255,255,255,.04); user-select: none; }
    .pa-check:hover { background: rgba(255,255,255,.09); }
    .pa-check input { accent-color: #06b6d4; width: 16px; height: 16px; }
    .pa-check.big { padding: 11px 12px; font-size: .88rem; }
    .pa-freqs-pick { display: flex; flex-direction: column; gap: 6px; }
    .pa-custom { display: flex; align-items: center; gap: 8px; font-size: .78rem; margin-top: 4px; }
    .pa-custom input { width: 84px; border-radius: 99px; border: 1px solid rgba(255,255,255,.18); background: rgba(0,0,0,.25); color: #fff; padding: 4px 10px; }
    .pa-current { margin-top: 14px; }
    .pa-chips { display: flex; flex-wrap: wrap; gap: 6px; }
    .pa-chip { display: inline-flex; align-items: center; gap: 6px; font-size: .74rem; font-weight: 700; padding: 4px 6px 4px 11px; border-radius: 99px; background: rgba(129, 140, 248, .2); color: #dbe2ff; }
    .pa-chip button { border: 0; width: 20px; height: 20px; border-radius: 50%; line-height: 1; background: rgba(255,255,255,.15); color: #fff; cursor: pointer; }
    .pa-chip button:hover { background: #ef4444; }
    .pa-hint { font-size: .72rem; color: rgba(230,236,255,.55); margin-top: 8px; }
</style>

<div class="modal fade pa-modal" id="pa-modal" tabindex="-1" aria-labelledby="pa-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="pa-head">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <span class="pa-pill"><i class="fas fa-user-check" aria-hidden="true"></i> Check in after your work is done</span>
                <h5 id="pa-title">Page attendance</h5>
                <div class="pa-sub" id="pa-sub">Loading…</div>
                <div class="pa-stats">
                    <div class="pa-stat"><b id="pa-s-people">—</b><span>Responsible people</span></div>
                    <div class="pa-stat"><b id="pa-s-done">—</b><span>Checked in (this period)</span></div>
                    <div class="pa-stat"><b id="pa-s-missed">—</b><span>Missed (30 days)</span></div>
                    <div class="pa-stat"><b id="pa-s-tasks">—</b><span>Tasks generated (30 days)</span></div>
                </div>
            </div>
            <div class="pa-body">
                <div class="pa-tools">
                    <button type="button" class="pa-btn is-on" data-pa-tab="status">Today</button>
                    <button type="button" class="pa-btn" data-pa-tab="history">History</button>
                    <button type="button" class="pa-btn" data-pa-tab="assign" id="pa-tab-assign-btn" style="display:none;"><i class="fas fa-user-plus" aria-hidden="true"></i> Assign people</button>
                    <span class="pa-spacer"></span>
                    <button type="button" class="pa-btn" id="pa-reload" title="Reload"><i class="fas fa-rotate" aria-hidden="true"></i></button>
                </div>
                <div class="pa-msg" id="pa-msg"></div>

                <div id="pa-pane-status">
                    <div id="pa-cta"></div>
                    <div class="pa-grid" id="pa-people"></div>
                </div>

                <div id="pa-pane-history" style="display:none;">
                    <div class="pa-tools">
                        <button type="button" class="pa-btn is-on" data-pa-hf="all">All</button>
                        <button type="button" class="pa-btn" data-pa-hf="done">Done</button>
                        <button type="button" class="pa-btn" data-pa-hf="missed">Missed</button>
                        <span class="pa-spacer"></span>
                        <select class="pa-select" id="pa-hist-person"><option value="">Everyone</option></select>
                    </div>
                    <div class="pa-list" id="pa-history"></div>
                </div>

                <div id="pa-pane-assign" style="display:none;">
                    <div class="pa-assign">
                        <div class="pa-panel">
                            <h6>1 · Who is responsible? <span class="pa-meta">(pick one or more)</span></h6>
                            <input type="search" class="pa-search" id="pa-user-search" placeholder="Search people…" autocomplete="off">
                            <div class="pa-users" id="pa-users"></div>
                        </div>
                        <div class="pa-panel">
                            <h6>2 · How often must they visit? <span class="pa-meta">(pick one or more)</span></h6>
                            <div class="pa-freqs-pick">
                                <label class="pa-check big"><input type="checkbox" name="pa-freq" value="1"> Daily <span class="pa-meta">every day</span></label>
                                <label class="pa-check big"><input type="checkbox" name="pa-freq" value="7"> Every 7 days <span class="pa-meta">weekly</span></label>
                                <label class="pa-check big"><input type="checkbox" name="pa-freq" value="30"> Every 30 days <span class="pa-meta">monthly</span></label>
                            </div>
                            <div class="pa-custom">Custom: every <input type="number" id="pa-custom-days" min="2" max="365" placeholder="days"> days</div>
                            <div class="pa-hint">Every person gets every frequency you tick. If they do not check in within a period, a task is generated for them and recorded in History.</div>
                            <div style="margin-top:12px;"><button type="button" class="pa-go" id="pa-assign-go">Assign</button></div>
                        </div>
                    </div>
                    <div class="pa-panel pa-current">
                        <h6>Currently assigned</h6>
                        <div class="pa-chips" id="pa-current"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const KEY = @json($paKey);
    const BASE = @json(url('/page-attendance')) + '/' + encodeURIComponent(KEY);
    const HOUR_MS = 60 * 60 * 1000;
    const GRADS = [
        'linear-gradient(135deg,#7c3aed,#ec4899)', 'linear-gradient(135deg,#0ea5e9,#6366f1)', 'linear-gradient(135deg,#059669,#06b6d4)',
        'linear-gradient(135deg,#f97316,#ef4444)', 'linear-gradient(135deg,#a855f7,#3b82f6)', 'linear-gradient(135deg,#eab308,#f97316)'
    ];
    let state = null;
    let tab = 'status';
    let histFilter = 'all';
    let busy = false;
    const el = function (id) { return document.getElementById(id); };
    const esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]; }); };
    const csrf = function () { return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''; };
    const grad = function (uid) { return GRADS[Math.abs(uid) % GRADS.length]; };

    async function api(path, method, body) {
        const res = await fetch(BASE + path, {
            method: method || 'GET',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: body ? JSON.stringify(body) : undefined
        });
        let json = null;
        try { json = await res.json(); } catch (e) { /* not json */ }
        if (!res.ok || !json || json.success === false) {
            const err = new Error((json && (json.message || (json.errors && Object.values(json.errors)[0][0]))) || ('Request failed (' + res.status + ')'));
            err.payload = json;
            throw err;
        }
        return json;
    }

    function flash(text, ok) {
        const m = el('pa-msg');
        if (!m) return;
        m.className = 'pa-msg ' + (ok ? 'ok' : 'err');
        m.textContent = text;
        clearTimeout(flash.t);
        flash.t = setTimeout(function () { m.className = 'pa-msg'; }, 5000);
    }

    function mine() { return state && state.me ? state.assignments.filter(function (a) { return a.user_id === state.me.id; }) : []; }

    function paintBadge() {
        const badge = el('pa-badge');
        if (!badge || !state) return;
        const s = state.stats;
        el('pa-badge-count').textContent = s.periods ? (s.done_now + '/' + s.periods) : 'set up';
        const myDue = mine().some(function (a) { return a.status === 'due'; });
        badge.classList.toggle('is-pending', s.pending_now > 0);
        el('pa-badge-me').style.display = myDue ? '' : 'none';
    }

    function paintHead() {
        const s = state.stats;
        el('pa-sub').textContent = state.page.label + ' · ' + state.today + ' (' + state.timezone + ')';
        el('pa-s-people').textContent = s.people;
        el('pa-s-done').textContent = s.done_now + ' / ' + s.periods;
        el('pa-s-missed').textContent = s.missed_30d;
        el('pa-s-tasks').textContent = s.tasks_30d;
        el('pa-tab-assign-btn').style.display = state.is_admin ? '' : 'none';
    }

    function freqRow(a) {
        let text = '';
        if (a.status === 'done') {
            text = 'Checked in' + (a.done_at ? ' ' + a.done_at : '');
        } else if (a.status === 'due') {
            text = a.frequency_days === 1 ? 'Due today' : ('Due by ' + a.period_end + (a.days_left ? ' · ' + a.days_left + ' day' + (a.days_left === 1 ? '' : 's') + ' left' : ' · last day'));
        } else {
            text = 'Starts soon';
        }
        const tag = a.status === 'done' ? '<span class="pa-tag done">✓ done</span>' : (a.status === 'due' ? '<span class="pa-tag due">pending</span>' : '');
        return '<div class="pa-freq is-' + esc(a.status) + '"><span class="pa-f">' + esc(a.frequency) + '</span><span class="pa-t">' + esc(text) + '</span>' + tag + '</div>';
    }

    function paintStatus() {
        // Call to action for the signed-in person.
        const my = mine();
        const cta = el('pa-cta');
        if (!my.length) {
            cta.innerHTML = '';
        } else {
            const due = my.filter(function (a) { return a.status === 'due'; });
            if (due.length) {
                cta.innerHTML = '<div class="pa-cta"><h6>Hey ' + esc(state.me.name.split(' ')[0]) + ' 👋 work on this page done?</h6>'
                    + '<p>Pending: ' + due.map(function (a) { return esc(a.frequency_label); }).join(', ') + '. One tap marks all of them.</p>'
                    + '<textarea class="pa-note" id="pa-note" maxlength="500" placeholder="Optional note — what did you check / finish?"></textarea>'
                    + '<button type="button" class="pa-go" id="pa-checkin">✓ I finished my work — mark attendance</button></div>';
            } else {
                cta.innerHTML = '<div class="pa-cta is-ok"><h6>You are all caught up ✨</h6><p style="margin:0">Your attendance is marked for every period on this page.</p></div>';
            }
        }

        // One card per person.
        const byUser = {};
        state.assignments.forEach(function (a) { (byUser[a.user_id] = byUser[a.user_id] || []).push(a); });
        const ids = Object.keys(byUser);
        if (!ids.length) {
            el('pa-people').innerHTML = '<div class="pa-empty" style="grid-column:1/-1"><big>🫥</big>No one is responsible for this page yet.'
                + (state.is_admin ? '<br>Use <b>Assign people</b> to add someone.' : '') + '</div>';
            return;
        }
        el('pa-people').innerHTML = ids.map(function (uid) {
            const list = byUser[uid];
            const first = list[0];
            const missed = list.reduce(function (n, a) { return n + a.missed_30d; }, 0);
            const last = list.map(function (a) { return a.last_visit; }).filter(Boolean)[0];
            const isMe = state.me && Number(uid) === state.me.id;
            return '<div class="pa-card' + (isMe ? ' is-me' : '') + '">'
                + '<div class="pa-who"><div class="pa-av" style="background:' + grad(Number(uid)) + '">' + esc(first.initials) + '</div>'
                + '<div><div class="pa-name">' + esc(first.name) + (isMe ? ' <span class="pa-tag freq">you</span>' : '') + '</div>'
                + '<div class="pa-meta">' + list.length + ' visit rule' + (list.length === 1 ? '' : 's') + '</div></div></div>'
                + '<div class="pa-freqs">' + list.map(freqRow).join('') + '</div>'
                + '<div class="pa-foot"><span>Last visit: ' + esc(last || 'never') + '</span>'
                + (missed ? '<span class="pa-tag missed">' + missed + ' missed · 30d</span>' : '<span class="pa-tag done">no misses · 30d</span>') + '</div>'
                + '</div>';
        }).join('');
    }

    function paintHistory() {
        const sel = el('pa-hist-person');
        const keep = sel.value;
        const people = {};
        state.history.forEach(function (h) { people[h.user_id] = h.name; });
        sel.innerHTML = '<option value="">Everyone</option>' + Object.keys(people).map(function (id) { return '<option value="' + id + '">' + esc(people[id]) + '</option>'; }).join('');
        sel.value = keep && people[keep] ? keep : '';

        const rows = state.history.filter(function (h) {
            return (histFilter === 'all' || h.status === histFilter) && (!sel.value || String(h.user_id) === sel.value);
        });
        if (!rows.length) {
            el('pa-history').innerHTML = '<div class="pa-empty"><big>📭</big>Nothing recorded yet. Check-ins and missed visits show up here.</div>';
            return;
        }
        el('pa-history').innerHTML = rows.map(function (h) {
            const done = h.status === 'done';
            const extra = done
                ? (h.note ? esc(h.note) : '<span class="pa-meta">no note</span>')
                : (h.task_id ? 'Task generated · #' + h.task_id : '<span class="pa-meta">older gap · no task</span>');
            return '<div class="pa-row"><div class="pa-av" style="background:' + grad(h.user_id) + '">' + esc(h.initials) + '</div>'
                + '<div><div class="pa-name">' + esc(h.name) + '</div><small>' + esc(h.when) + '</small></div>'
                + '<div><span class="pa-tag freq">' + esc(h.frequency) + '</span></div>'
                + '<div><span class="pa-tag ' + (done ? 'done' : 'missed') + '">' + (done ? '✓ done' : '✕ missed') + '</span></div>'
                + '<div class="pa-note-txt">' + extra + '</div>'
                + '<div><small>' + esc(h.period) + '</small></div></div>';
        }).join('');
    }

    function paintAssign() {
        if (!state.is_admin) return;
        const q = (el('pa-user-search').value || '').toLowerCase();
        const checked = {};
        el('pa-users').querySelectorAll('input:checked').forEach(function (i) { checked[i.value] = true; });
        el('pa-users').innerHTML = (state.users || []).filter(function (u) { return !q || u.name.toLowerCase().indexOf(q) !== -1 || checked[u.id]; }).map(function (u) {
            return '<label class="pa-check"><input type="checkbox" value="' + u.id + '"' + (checked[u.id] ? ' checked' : '') + '> ' + esc(u.name) + '</label>';
        }).join('') || '<div class="pa-meta">No people found.</div>';

        el('pa-current').innerHTML = state.assignments.length ? state.assignments.map(function (a) {
            return '<span class="pa-chip">' + esc(a.name) + ' · ' + esc(a.frequency) + '<button type="button" data-pa-remove="' + a.id + '" title="Remove" aria-label="Remove">×</button></span>';
        }).join('') : '<span class="pa-meta">No one yet.</span>';
    }

    function paint() {
        if (!state) return;
        paintBadge();
        paintHead();
        paintStatus();
        paintHistory();
        paintAssign();
        showTab();
    }

    function showTab() {
        ['status', 'history', 'assign'].forEach(function (t) { const p = el('pa-pane-' + t); if (p) p.style.display = t === tab ? '' : 'none'; });
        document.querySelectorAll('[data-pa-tab]').forEach(function (b) { b.classList.toggle('is-on', b.getAttribute('data-pa-tab') === tab); });
    }

    async function load() {
        try {
            state = await api('/state');
            paint();
        } catch (e) {
            const b = el('pa-badge-count');
            if (b) b.textContent = '!';
            if (el('pa-sub')) el('pa-sub').textContent = e.message;
        }
    }

    async function act(fn) {
        if (busy) return;
        busy = true;
        try { await fn(); } catch (e) { flash(e.message, false); if (e.payload && e.payload.assignments) { state = e.payload; paint(); } } finally { busy = false; }
    }

    function openModal(t) {
        tab = t || 'status';
        const m = el('pa-modal');
        if (window.bootstrap && bootstrap.Modal && m) bootstrap.Modal.getOrCreateInstance(m).show();
        if (state) paint();
        load();
    }

    el('pa-badge')?.addEventListener('click', function () { openModal('status'); });
    el('pa-reload')?.addEventListener('click', load);
    el('pa-user-search')?.addEventListener('input', paintAssign);
    el('pa-hist-person')?.addEventListener('change', paintHistory);

    el('pa-modal')?.addEventListener('click', function (e) {
        const t = e.target.closest('[data-pa-tab]');
        if (t) { tab = t.getAttribute('data-pa-tab'); showTab(); return; }
        const f = e.target.closest('[data-pa-hf]');
        if (f) {
            histFilter = f.getAttribute('data-pa-hf');
            document.querySelectorAll('[data-pa-hf]').forEach(function (b) { b.classList.toggle('is-on', b === f); });
            paintHistory();
            return;
        }
        if (e.target.closest('#pa-checkin')) {
            const btn = e.target.closest('#pa-checkin');
            btn.disabled = true;
            act(async function () {
                state = await api('/check-in', 'POST', { note: el('pa-note')?.value || '' });
                paint();
                flash('Attendance marked — thanks! ✅', true);
            }).finally(function () { btn.disabled = false; });
            return;
        }
        const rm = e.target.closest('[data-pa-remove]');
        if (rm) {
            if (!confirm('Remove this person from the page?')) return;
            act(async function () {
                state = await api('/assignments/' + rm.getAttribute('data-pa-remove'), 'DELETE');
                paint();
                flash('Removed.', true);
            });
            return;
        }
        if (e.target.closest('#pa-assign-go')) {
            const users = Array.from(el('pa-users').querySelectorAll('input:checked')).map(function (i) { return Number(i.value); });
            const freqs = Array.from(document.querySelectorAll('input[name="pa-freq"]:checked')).map(function (i) { return Number(i.value); });
            const custom = Number(el('pa-custom-days').value);
            if (custom >= 2 && custom <= 365) freqs.push(Math.round(custom));
            if (!users.length) { flash('Pick at least one person.', false); return; }
            if (!freqs.length) { flash('Pick at least one frequency.', false); return; }
            act(async function () {
                state = await api('/assign', 'POST', { users: users, frequencies: freqs });
                el('pa-users').querySelectorAll('input:checked').forEach(function (i) { i.checked = false; });
                document.querySelectorAll('input[name="pa-freq"]:checked').forEach(function (i) { i.checked = false; });
                el('pa-custom-days').value = '';
                paint();
                flash(state.message || 'Assigned.', true);
            });
        }
    });

    load();
    // Re-read every hour so the badge flips to "your check-in" when a new day / period starts.
    setInterval(load, HOUR_MS);
})();
</script>
