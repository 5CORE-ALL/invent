{{--
    Floating "KPI" icon, shown on every page for logged-in users.
    Opens a modal where the logged-in user picks live badges (metrics) from different pages
    (All Marketplace Master, Forecast Analysis, On Sea Transit, ...) and sees their current values,
    auto-refreshing while the modal is open.

    Data is ALWAYS scoped to the logged-in user (user_id = auth()->id()). It uses the same store as the
    Task Summary "KPI" column (team_member_kpis), so both places stay in sync:
      GET    /tasks/user-kpis      (assigned badges with live values + catalog of available badges)
      POST   /tasks/user-kpis      (add a badge, max 5)
      DELETE /tasks/user-kpis      (remove a badge)

    Hide on a page with: @extends('layouts.vertical', [..., 'hideKpiFab' => true])
--}}
@auth
@unless($hideKpiFab ?? false)
<style>
    #kpiFab {
        position: fixed; right: 20px; bottom: 148px; z-index: 1040;
        width: 54px; height: 54px; border-radius: 50%; border: 0; padding: 0;
        background: linear-gradient(135deg, #f59e0b 0%, #b45309 100%); color: #fff;
        display: flex; flex-direction: column; align-items: center; justify-content: center; line-height: 1;
        box-shadow: 0 6px 18px rgba(180, 83, 9, .45); cursor: pointer; transition: transform .15s ease, box-shadow .15s ease;
    }
    #kpiFab:hover { transform: translateY(-3px); box-shadow: 0 10px 24px rgba(180, 83, 9, .55); }
    #kpiFab i { font-size: 20px; }
    #kpiFab .kpi-fab-text { font-size: 10px; font-weight: 800; letter-spacing: .06em; margin-top: 1px; }
    @media (max-width: 991.98px) { #kpiFab { right: 12px; bottom: 206px; width: 50px; height: 50px; } }

    #kpiFabModal .modal-content { border-radius: 16px; border: none; box-shadow: 0 20px 50px rgba(15, 23, 42, .18); }
    #kpiFabModal .modal-header {
        background: linear-gradient(135deg, #b45309, #f59e0b); color: #fff; border-bottom: 0;
        border-top-left-radius: 16px; border-top-right-radius: 16px;
    }
    #kpiFabModal .modal-header .btn-close { filter: brightness(0) invert(1); }
    #kpiFabModal .kpi-live { font-size: .75rem; opacity: .95; display: flex; align-items: center; gap: 6px; }
    #kpiFabModal .kpi-live-dot { width: 8px; height: 8px; border-radius: 50%; background: #4ade80; animation: kpiPulse 1.6s infinite; }
    #kpiFabModal .kpi-live.is-error .kpi-live-dot { background: #f87171; animation: none; }
    @keyframes kpiPulse { 0%,100% { box-shadow: 0 0 0 0 rgba(74,222,128,.6) } 50% { box-shadow: 0 0 0 6px rgba(74,222,128,0) } }
    #kpiFabModal .kpi-refresh { border: 0; background: rgba(255,255,255,.2); color: #fff; border-radius: 6px; padding: 2px 8px; font-size: .75rem; }
    #kpiFabModal .kpi-refresh:hover { background: rgba(255,255,255,.35); }

    #kpiFabModal .kpi-section-label {
        font-size: .7rem; font-weight: 800; text-transform: uppercase; letter-spacing: .07em; color: #64748b;
        margin: 0 0 .5rem; display: flex; align-items: center; gap: .4rem;
    }
    #kpiFabModal .kpi-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 10px; }
    #kpiFabModal .kpi-card {
        position: relative; border: 1px solid #e5e9f0; border-left: 4px solid #f59e0b; border-radius: 10px;
        padding: 10px 12px; background: #fff; transition: background-color .6s ease;
    }
    #kpiFabModal .kpi-card.kpi-changed { background: #fef3c7; }
    #kpiFabModal .kpi-card-page { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #94a3b8; padding-right: 22px; }
    #kpiFabModal .kpi-card-field { font-size: .82rem; font-weight: 600; color: #475569; }
    #kpiFabModal .kpi-card-value { font-size: 1.6rem; font-weight: 800; color: #0f172a; font-variant-numeric: tabular-nums; line-height: 1.2; }
    #kpiFabModal .kpi-card-delta { font-size: .72rem; font-weight: 700; margin-left: 6px; }
    #kpiFabModal .kpi-card-delta.up { color: #16a34a; }
    #kpiFabModal .kpi-card-delta.down { color: #dc2626; }
    #kpiFabModal .kpi-card-time { font-size: .68rem; color: #94a3b8; }
    #kpiFabModal .kpi-card-remove {
        position: absolute; top: 4px; right: 6px; border: 0; background: none; color: #94a3b8; font-size: 18px; line-height: 1; padding: 0 4px; cursor: pointer;
    }
    #kpiFabModal .kpi-card-remove:hover { color: #dc2626; }
    #kpiFabModal .kpi-empty { font-size: .85rem; color: #94a3b8; padding: .5rem 0; }

    #kpiFabModal .kpi-pool { max-height: 260px; overflow-y: auto; border: 1px solid #e5e9f0; border-radius: 10px; }
    #kpiFabModal .kpi-pool-group { position: sticky; top: 0; background: #f8fafc; font-size: .7rem; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; color: #64748b; padding: 5px 12px; border-bottom: 1px solid #e5e9f0; }
    #kpiFabModal .kpi-pool-item {
        width: 100%; display: flex; align-items: center; justify-content: space-between; gap: 10px; text-align: left;
        border: 0; border-bottom: 1px solid #f1f5f9; background: #fff; padding: 7px 12px; font-size: .84rem; cursor: pointer;
    }
    #kpiFabModal .kpi-pool-item:hover { background: #fffbeb; }
    #kpiFabModal .kpi-pool-item:disabled { opacity: .5; cursor: not-allowed; background: #fff; }
    #kpiFabModal .kpi-pool-item .kpi-pool-val { font-weight: 700; color: #b45309; font-variant-numeric: tabular-nums; white-space: nowrap; }
    #kpiFabModal .kpi-hidden { display: none !important; }

    html[data-bs-theme="dark"] #kpiFabModal .kpi-card,
    html[data-bs-theme="dark"] #kpiFabModal .kpi-pool-item { background: transparent; border-color: rgba(255,255,255,.12); color: inherit; }
    html[data-bs-theme="dark"] #kpiFabModal .kpi-card-value { color: rgba(255,255,255,.95); }
    html[data-bs-theme="dark"] #kpiFabModal .kpi-pool-group { background: rgba(255,255,255,.06); border-color: rgba(255,255,255,.12); }
    html[data-bs-theme="dark"] #kpiFabModal .kpi-pool-item:hover { background: rgba(255,255,255,.06); }
</style>

<button type="button" id="kpiFab" data-bs-toggle="modal" data-bs-target="#kpiFabModal" title="My KPI badges" aria-label="Open my KPI badges">
    <i class="ri-medal-2-line" aria-hidden="true"></i>
    <span class="kpi-fab-text">KPI</span>
</button>

<div class="modal fade" id="kpiFabModal" tabindex="-1" aria-hidden="true" aria-labelledby="kpiFabTitle">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header">
                <div class="flex-grow-1 min-w-0">
                    <h5 class="modal-title mb-1" id="kpiFabTitle"><i class="ri-medal-2-line me-2" aria-hidden="true"></i>My KPI</h5>
                    <div class="kpi-live" id="kpiLive">
                        <span class="kpi-live-dot"></span>
                        <span id="kpiLiveText">Loading…</span>
                        <button type="button" class="kpi-refresh" id="kpiRefresh" title="Refresh now"><i class="ri-refresh-line"></i> Refresh</button>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger py-2 kpi-hidden" id="kpiError" role="alert"></div>

                <div class="kpi-section-label"><i class="ri-bar-chart-box-line"></i> My badges <span id="kpiSlotInfo" class="text-muted fw-normal" style="text-transform:none;letter-spacing:0"></span></div>
                <div class="kpi-cards" id="kpiCards" aria-live="polite"></div>
                <div class="kpi-empty kpi-hidden" id="kpiEmpty">No badges yet. Add some from the list below.</div>

                <div class="kpi-section-label mt-4"><i class="ri-add-circle-line"></i> Add a badge from any page</div>
                <div class="row g-2 mb-2">
                    <div class="col-12 col-sm-5">
                        <select class="form-select form-select-sm" id="kpiPageFilter" aria-label="Filter by page"><option value="">All pages</option></select>
                    </div>
                    <div class="col-12 col-sm-7">
                        <input type="search" class="form-control form-control-sm" id="kpiSearch" placeholder="Search badges…" autocomplete="off">
                    </div>
                </div>
                <div class="kpi-pool" id="kpiPool"><div class="kpi-empty px-3">Loading…</div></div>
                <small class="text-muted d-block mt-2"><i class="ri-information-line me-1"></i>Values refresh automatically every 30 seconds while this window is open. Up to 5 badges.</small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        if (window.self !== window.top) { // never inside an embedded iframe
            var f = document.getElementById('kpiFab'); if (f) f.remove();
            return;
        }
        // Always the logged-in user — never taken from the page or the URL.
        var USER_ID = @json((int) auth()->id());
        var URL_KPIS = @json(route('tasks.userKpis.get'));
        var CSRF = @json(csrf_token());
        var POLL_MS = 30000;

        var modalEl = document.getElementById('kpiFabModal');
        var $ = function (id) { return document.getElementById(id); };
        var show = function (id, v) { $(id).classList.toggle('kpi-hidden', !v); };
        var state = { assigned: [], available: [], max: 5, prev: {}, loaded: false };
        var timer = null, inFlight = false;

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
        function showError(msg) { var e = $('kpiError'); e.textContent = msg || ''; show('kpiError', !!msg); }
        function setLive(ok, text) { $('kpiLive').classList.toggle('is-error', !ok); $('kpiLiveText').textContent = text; }
        function nowStr() { return new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit', second: '2-digit' }); }

        function toNum(v) { var n = parseFloat(String(v == null ? '' : v).replace(/[^0-9.\-]/g, '')); return isNaN(n) ? null : n; }

        // ---------- render ----------
        function renderCount() {
            var n = state.assigned.length;
            $('kpiSlotInfo').textContent = '(' + n + '/' + state.max + ')';
        }
        function renderCards() {
            var box = $('kpiCards'), html = '';
            state.assigned.forEach(function (a) {
                var prev = state.prev[a.key];
                var cur = a.value_display;
                var changed = prev !== undefined && prev !== cur;
                var delta = '';
                if (changed) {
                    var p = toNum(prev), c = toNum(cur);
                    if (p !== null && c !== null && p !== c) {
                        delta = '<span class="kpi-card-delta ' + (c > p ? 'up' : 'down') + '">' + (c > p ? '▲' : '▼') + '</span>';
                    }
                }
                html += '<div class="kpi-card' + (changed ? ' kpi-changed' : '') + '" data-key="' + esc(a.key) + '">'
                    + '<button type="button" class="kpi-card-remove" data-slot="' + a.slot + '" title="Remove" aria-label="Remove ' + esc(a.field_label) + '">&times;</button>'
                    + '<div class="kpi-card-page">' + esc(a.page_label) + '</div>'
                    + '<div class="kpi-card-field">' + esc(a.field_label) + '</div>'
                    + '<div class="kpi-card-value">' + esc(a.value_display) + delta + '</div>'
                    + '<div class="kpi-card-time">' + (a.updated_at ? 'Data as of ' + esc(a.updated_at) : '') + '</div>'
                    + '</div>';
            });
            box.innerHTML = html;
            show('kpiEmpty', state.assigned.length === 0);
            // fade the highlight out
            setTimeout(function () { box.querySelectorAll('.kpi-changed').forEach(function (el) { el.classList.remove('kpi-changed'); }); }, 1500);
            // remember values for the next refresh
            state.prev = {};
            state.assigned.forEach(function (a) { state.prev[a.key] = a.value_display; });
        }
        function renderPageFilter() {
            var sel = $('kpiPageFilter'), cur = sel.value, pages = {};
            state.available.forEach(function (o) { pages[o.page] = o.page_label; });
            var html = '<option value="">All pages</option>';
            Object.keys(pages).sort(function (a, b) { return pages[a].localeCompare(pages[b]); }).forEach(function (p) {
                html += '<option value="' + esc(p) + '">' + esc(pages[p]) + '</option>';
            });
            sel.innerHTML = html;
            sel.value = pages[cur] ? cur : '';
        }
        function renderPool() {
            var page = $('kpiPageFilter').value, q = $('kpiSearch').value.trim().toLowerCase();
            var full = state.assigned.length >= state.max;
            var shown = 0, LIMIT = 300, html = '', lastPage = null;
            var list = state.available.slice().sort(function (a, b) {
                return a.page_label.localeCompare(b.page_label) || a.field_label.localeCompare(b.field_label);
            });
            for (var i = 0; i < list.length; i++) {
                var o = list[i];
                if (page && o.page !== page) continue;
                if (q && (o.label + ' ' + o.field).toLowerCase().indexOf(q) === -1) continue;
                if (shown >= LIMIT) break;
                if (o.page !== lastPage) { html += '<div class="kpi-pool-group">' + esc(o.page_label) + '</div>'; lastPage = o.page; }
                html += '<button type="button" class="kpi-pool-item" data-key="' + esc(o.key) + '"' + (full ? ' disabled' : '') + '>'
                    + '<span>' + esc(o.field_label) + '</span><span class="kpi-pool-val">' + esc(o.value_display) + '</span></button>';
                shown++;
            }
            if (!html) html = '<div class="kpi-empty px-3">' + (state.loaded ? 'No badges match.' : 'Loading…') + '</div>';
            $('kpiPool').innerHTML = html;
        }
        function renderAll() { renderCount(); renderCards(); renderPageFilter(); renderPool(); }

        // ---------- data ----------
        function load(silent) {
            if (inFlight) return Promise.resolve();
            inFlight = true;
            if (!silent) setLive(true, 'Loading…');
            return call('GET', URL_KPIS + '?user_id=' + encodeURIComponent(USER_ID)).then(function (res) {
                if (!res.ok || !res.data || res.data.success === false) {
                    setLive(false, 'Could not refresh');
                    if (!silent || !state.loaded) showError(firstError(res));
                    return;
                }
                showError('');
                state.assigned = res.data.assigned || [];
                state.available = res.data.available || [];
                state.max = res.data.max || 5;
                var first = !state.loaded;
                state.loaded = true;
                if (first) { state.prev = {}; state.assigned.forEach(function (a) { state.prev[a.key] = a.value_display; }); }
                // keep the pool's filter/search state while updating values
                renderCount(); renderCards(); renderPageFilter(); renderPool();
                setLive(true, 'Live · updated ' + nowStr());
            }).catch(function () {
                setLive(false, 'Offline · retrying');
            }).then(function () { inFlight = false; });
        }
        function startPolling() {
            stopPolling();
            timer = setInterval(function () { if (!document.hidden) load(true); }, POLL_MS);
        }
        function stopPolling() { if (timer) { clearInterval(timer); timer = null; } }

        function addBadge(key) {
            showError('');
            call('POST', URL_KPIS, { user_id: USER_ID, key: key }).then(function (res) {
                if (!res.ok || !res.data || !res.data.success) { showError(firstError(res)); return; }
                state.assigned = res.data.assigned || [];
                state.available = state.available.filter(function (o) { return o.key !== key; });
                renderCount(); renderCards(); renderPool();
            }).catch(function () { showError('Network error. Please try again.'); });
        }
        function removeBadge(slot) {
            showError('');
            call('DELETE', URL_KPIS, { user_id: USER_ID, slot: slot }).then(function (res) {
                if (!res.ok || !res.data || !res.data.success) { showError(firstError(res)); return; }
                state.assigned = res.data.assigned || [];
                renderCount(); renderCards();
                // reload so the removed badge returns to the available list
                load(true);
            }).catch(function () { showError('Network error. Please try again.'); });
        }

        // ---------- events ----------
        $('kpiCards').addEventListener('click', function (e) {
            var b = e.target.closest('.kpi-card-remove'); if (!b) return;
            removeBadge(parseInt(b.getAttribute('data-slot'), 10));
        });
        $('kpiPool').addEventListener('click', function (e) {
            var b = e.target.closest('.kpi-pool-item'); if (!b || b.disabled) return;
            addBadge(b.getAttribute('data-key'));
        });
        $('kpiPageFilter').addEventListener('change', renderPool);
        $('kpiSearch').addEventListener('input', renderPool);
        $('kpiRefresh').addEventListener('click', function () { load(false); });

        modalEl.addEventListener('shown.bs.modal', function () { load(false); startPolling(); });
        modalEl.addEventListener('hidden.bs.modal', stopPolling);
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden && modalEl.classList.contains('show')) load(true);
        });
    })();
</script>
@endunless
@endauth
