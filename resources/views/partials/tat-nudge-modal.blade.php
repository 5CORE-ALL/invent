{{--
    Once-a-day TAT reminder, 1 hour after login.
    GET /tasks/tat-nudge — same L30 TAT and colours as /tasks/summary.
--}}
<style>
    #tatNudgeModal .modal-dialog {
        max-width: 440px;
    }
    #tatNudgeModal .modal-content {
        border: none;
        border-radius: 22px;
        overflow: hidden;
        box-shadow: 0 24px 60px rgba(13, 148, 136, 0.24);
    }
    #tatNudgeModal .modal-body {
        padding: 2rem 1.6rem 1.5rem;
        text-align: center;
        background: linear-gradient(180deg, #ecfdf5 0%, #fff 55%);
    }
    .tat-nudge-emoji {
        font-size: 4.4rem;
        line-height: 1;
        display: block;
        margin-bottom: 0.35rem;
        filter: drop-shadow(0 8px 16px rgba(13, 148, 136, 0.18));
    }
    .tat-nudge-kicker {
        font-size: 0.74rem;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #0f766e;
        margin-bottom: 0.35rem;
    }
    .tat-nudge-score {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 5.2rem;
        padding: 0.32rem 1.1rem;
        border-radius: 999px;
        background: #6ee7b7;
        color: #fff;
        font-size: 2.1rem;
        font-weight: 800;
        line-height: 1.15;
        font-variant-numeric: tabular-nums;
        box-shadow: 0 8px 20px rgba(16, 185, 129, 0.28);
        margin: 0.2rem 0 0.55rem;
    }
    .tat-nudge-score.is-tat-high {
        background: #fff;
        color: #dc2626;
        box-shadow: 0 8px 20px rgba(220, 38, 38, 0.16);
    }
    .tat-nudge-score.is-tat-mid {
        background: #fff;
        color: #15803d;
        box-shadow: 0 8px 20px rgba(21, 128, 61, 0.16);
    }
    .tat-nudge-score.is-tat-low {
        background: #fce7f3;
        color: #831843;
        box-shadow: 0 8px 20px rgba(131, 24, 67, 0.16);
    }
    .tat-nudge-score.is-tat-none {
        background: #e2e8f0;
        color: #475569;
        box-shadow: none;
    }
    .tat-nudge-label {
        font-size: 0.95rem;
        font-weight: 800;
        color: #115e59;
        margin-bottom: 0.85rem;
    }
    .tat-nudge-msg {
        font-size: 1.02rem;
        line-height: 1.5;
        color: #3f3f46;
        margin: 0 0 1.25rem;
        font-weight: 600;
    }
    .tat-nudge-actions {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    .tat-nudge-actions .btn-success {
        background: #0d9488;
        border-color: #0d9488;
        font-weight: 800;
        border-radius: 999px;
        padding: 0.65rem 1rem;
    }
    .tat-nudge-actions .btn-success:hover {
        background: #0f766e;
        border-color: #0f766e;
    }
    .tat-nudge-actions .btn-light {
        border-radius: 999px;
        font-weight: 700;
        color: #115e59;
    }
</style>

<div class="modal fade" id="tatNudgeModal" tabindex="-1" aria-labelledby="tatNudgeTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body">
                <span class="tat-nudge-emoji" aria-hidden="true">🏆</span>
                <div class="tat-nudge-kicker" id="tatNudgeHello">Performance reminder</div>
                <div class="tat-nudge-score is-tat-none" id="tatNudgeScore">—</div>
                <div class="tat-nudge-label" id="tatNudgeLabel">Your TAT · last 30 days</div>
                <p class="tat-nudge-msg" id="tatNudgeMsg"></p>
                <div class="tat-nudge-actions">
                    <a class="btn btn-success" id="tatNudgeGo" href="{{ route('tasks.index') }}">Open my tasks</a>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">I'll finish on time</button>
                </div>
            </div>
        </div>
    </div>
</div>

@auth
<script>
(function () {
    var cfg = {
        userId: @json((int) auth()->id()),
        route: @json(route('tasks.tatNudge.get'))
    };
    if (!cfg.userId) return;

    var timer = null;

    function storageKey(day) {
        return 'tat-nudge:' + cfg.userId + ':' + day;
    }
    function alreadyShown(day) {
        try { return localStorage.getItem(storageKey(day)) === '1'; } catch (e) { return false; }
    }
    function markShown(day) {
        try { localStorage.setItem(storageKey(day), '1'); } catch (e) {}
    }
    function pickMessage(messages) {
        var list = Array.isArray(messages) && messages.length ? messages : [];
        if (!list.length) return 'Accomplish tasks in the allotted time. A strong TAT helps with promotions and incentives.';
        return list[Math.floor(Math.random() * list.length)];
    }
    function bandClass(band) {
        if (band === 'high') return 'is-tat-high';
        if (band === 'mid') return 'is-tat-mid';
        if (band === 'low') return 'is-tat-low';
        return 'is-tat-none';
    }
    function whenReady(cb) {
        var od = document.getElementById('overdueNudgeModal');
        if (od && od.classList.contains('show')) {
            od.addEventListener('hidden.bs.modal', function () { cb(); }, { once: true });
            return;
        }
        cb();
    }
    function showModal(data) {
        var scoreEl = document.getElementById('tatNudgeScore');
        var msgEl = document.getElementById('tatNudgeMsg');
        var helloEl = document.getElementById('tatNudgeHello');
        var labelEl = document.getElementById('tatNudgeLabel');
        var goEl = document.getElementById('tatNudgeGo');
        var modalEl = document.getElementById('tatNudgeModal');
        if (!scoreEl || !msgEl || !modalEl || typeof bootstrap === 'undefined' || !bootstrap.Modal) return;

        var count = parseInt(data.tat_count, 10) || 0;
        scoreEl.className = 'tat-nudge-score ' + bandClass(data.tat_band);
        scoreEl.textContent = data.tat_display || '—';
        msgEl.textContent = pickMessage(data.messages);
        if (helloEl) {
            helloEl.textContent = data.user_name ? ('Hi ' + data.user_name) : 'Performance reminder';
        }
        if (labelEl) {
            labelEl.textContent = count > 0
                ? ('Your TAT · ' + count + ' completed task' + (count === 1 ? '' : 's') + ' · last 30 days')
                : 'Your TAT · last 30 days';
        }
        if (goEl && data.tasks_url) goEl.setAttribute('href', data.tasks_url);
        markShown(data.business_today);
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }

    function schedule(data) {
        if (!data || data.success === false) return;
        if (alreadyShown(data.business_today)) return;
        var wait = parseInt(data.wait_ms, 10);
        if (!(wait >= 0)) wait = 0;
        if (timer) clearTimeout(timer);
        timer = setTimeout(function () {
            whenReady(function () { showModal(data); });
        }, wait);
    }

    fetch(cfg.route, {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    }).then(function (r) { return r.json(); }).then(schedule).catch(function () {});
})();
</script>
@endauth
