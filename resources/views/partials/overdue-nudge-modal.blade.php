{{--
    Once-a-day overdue reminder after login.
    GET /tasks/overdue-nudge
--}}
<style>
    #overdueNudgeModal .modal-dialog {
        max-width: 440px;
    }
    #overdueNudgeModal .modal-content {
        border: none;
        border-radius: 22px;
        overflow: hidden;
        box-shadow: 0 24px 60px rgba(185, 28, 28, 0.28);
    }
    #overdueNudgeModal .modal-body {
        padding: 2rem 1.6rem 1.5rem;
        text-align: center;
        background: linear-gradient(180deg, #fff1f2 0%, #fff 55%);
    }
    .od-nudge-emoji {
        font-size: 4.4rem;
        line-height: 1;
        display: block;
        margin-bottom: 0.35rem;
        filter: drop-shadow(0 8px 16px rgba(220, 38, 38, 0.18));
    }
    .od-nudge-kicker {
        font-size: 0.74rem;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #b91c1c;
        margin-bottom: 0.35rem;
    }
    .od-nudge-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 4.6rem;
        padding: 0.28rem 1rem;
        border-radius: 999px;
        background: #dc2626;
        color: #fff;
        font-size: 2.1rem;
        font-weight: 800;
        line-height: 1.15;
        font-variant-numeric: tabular-nums;
        box-shadow: 0 8px 20px rgba(220, 38, 38, 0.28);
        margin: 0.2rem 0 0.55rem;
    }
    .od-nudge-label {
        font-size: 0.95rem;
        font-weight: 800;
        color: #7f1d1d;
        margin-bottom: 0.85rem;
    }
    .od-nudge-msg {
        font-size: 1.02rem;
        line-height: 1.5;
        color: #3f3f46;
        margin: 0 0 1.25rem;
        font-weight: 600;
    }
    .od-nudge-actions {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    .od-nudge-actions .btn-danger {
        background: #dc2626;
        border-color: #dc2626;
        font-weight: 800;
        border-radius: 999px;
        padding: 0.65rem 1rem;
    }
    .od-nudge-actions .btn-light {
        border-radius: 999px;
        font-weight: 700;
        color: #7f1d1d;
    }
</style>

<div class="modal fade" id="overdueNudgeModal" tabindex="-1" aria-labelledby="overdueNudgeTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body">
                <span class="od-nudge-emoji" aria-hidden="true">😭</span>
                <div class="od-nudge-kicker" id="overdueNudgeHello"></div>
                <div class="od-nudge-count" id="overdueNudgeCount">0</div>
                <div class="od-nudge-label">Overdue tasks</div>
                <p class="od-nudge-msg" id="overdueNudgeMsg"></p>
                <div class="od-nudge-actions">
                    <a class="btn btn-danger" id="overdueNudgeGo" href="{{ route('tasks.index') }}">Clear my overdues</a>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">I'll do it today</button>
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
        route: @json(route('tasks.overdueNudge.get'))
    };
    if (!cfg.userId) return;

    function storageKey(day) {
        return 'overdue-nudge:' + cfg.userId + ':' + day;
    }
    function pickMessage(messages) {
        var list = Array.isArray(messages) && messages.length ? messages : [];
        if (!list.length) return 'Avoid overdues to stay eligible for promotions and incentives.';
        return list[Math.floor(Math.random() * list.length)];
    }
    function showModal(data) {
        var countEl = document.getElementById('overdueNudgeCount');
        var msgEl = document.getElementById('overdueNudgeMsg');
        var helloEl = document.getElementById('overdueNudgeHello');
        var goEl = document.getElementById('overdueNudgeGo');
        var modalEl = document.getElementById('overdueNudgeModal');
        if (!countEl || !msgEl || !modalEl || typeof bootstrap === 'undefined' || !bootstrap.Modal) return;
        var overdue = parseInt(data.overdue, 10) || 0;
        countEl.textContent = overdue.toLocaleString('en-IN');
        msgEl.textContent = pickMessage(data.messages);
        if (helloEl) {
            helloEl.textContent = data.user_name ? ('Hi ' + data.user_name) : 'Overdue reminder';
        }
        if (goEl && data.tasks_url) goEl.setAttribute('href', data.tasks_url);
        try {
            localStorage.setItem(storageKey(data.business_today), '1');
        } catch (e) {}
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }

    function alreadyShown(day) {
        try {
            return localStorage.getItem(storageKey(day)) === '1';
        } catch (e) {
            return false;
        }
    }

    fetch(cfg.route, {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    }).then(function (r) { return r.json(); }).then(function (data) {
        if (!data || data.success === false) return;
        if (alreadyShown(data.business_today)) return;
        showModal(data);
    }).catch(function () {});
})();
</script>
@endauth
