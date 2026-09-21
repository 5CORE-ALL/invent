{{--
    Once-a-day daily task follow-up reminder, 1 hour after login.
    Uses the same login-hour clock as the TAT nudge.
--}}
<style>
    #followUpNudgeModal .modal-dialog {
        max-width: min(920px, 96vw);
    }
    #followUpNudgeModal .modal-content {
        border: none;
        border-radius: 18px;
        overflow: hidden;
        box-shadow: 0 24px 60px rgba(15, 23, 42, 0.28);
        background: #0b1f3a;
    }
    #followUpNudgeModal .modal-header {
        border: 0;
        padding: 0.7rem 1rem 0.55rem;
        background: #0b1f3a;
        color: #fff;
    }
    #followUpNudgeModal .modal-title {
        font-size: 0.95rem;
        font-weight: 800;
        letter-spacing: 0.02em;
    }
    #followUpNudgeModal .btn-close {
        filter: invert(1);
        opacity: 0.85;
    }
    #followUpNudgeModal .modal-body {
        padding: 0;
        background: #0b1f3a;
    }
    #followUpNudgeModal .fu-nudge-img {
        display: block;
        width: 100%;
        height: auto;
        max-height: min(78vh, 680px);
        object-fit: contain;
        background: #0b1f3a;
    }
    #followUpNudgeModal .modal-footer {
        border: 0;
        padding: 0.75rem 1rem 1rem;
        background: #0b1f3a;
        justify-content: center;
        gap: 0.5rem;
    }
    #followUpNudgeModal .modal-footer .btn {
        border-radius: 999px;
        font-weight: 800;
        padding: 0.55rem 1.15rem;
        min-width: 140px;
    }
</style>

<div class="modal fade" id="followUpNudgeModal" tabindex="-1" aria-labelledby="followUpNudgeTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="followUpNudgeTitle">Daily task follow-up</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <img
                    class="fu-nudge-img"
                    src="{{ asset('assets/images/daily-task-follow-up.jpg') }}"
                    alt="Daily task follow-up: your responsibility, your success. Follow up today, get it done."
                >
            </div>
            <div class="modal-footer">
                <a class="btn btn-warning text-dark" href="{{ route('tasks.index') }}">Open my tasks</a>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Got it</button>
            </div>
        </div>
    </div>
</div>

@auth
<script>
(function () {
    var cfg = {
        userId: @json((int) auth()->id()),
        today: @json(\App\Support\TaskBusinessTime::today()->toDateString()),
        waitMs: @json(\App\Support\UserTatNudge::waitMs())
    };
    if (!cfg.userId) return;

    var timer = null;

    function storageKey() {
        return 'follow-up-nudge:' + cfg.userId + ':' + cfg.today;
    }
    function alreadyShown() {
        try { return localStorage.getItem(storageKey()) === '1'; } catch (e) { return false; }
    }
    function markShown() {
        try { localStorage.setItem(storageKey(), '1'); } catch (e) {}
    }
    function waitUntilHidden(el, then) {
        if (el && el.classList.contains('show')) {
            el.addEventListener('hidden.bs.modal', function () { then(); }, { once: true });
            return;
        }
        then();
    }
    function whenReady(cb) {
        waitUntilHidden(document.getElementById('overdueNudgeModal'), function () {
            setTimeout(function () {
                waitUntilHidden(document.getElementById('tatNudgeModal'), cb);
            }, 400);
        });
    }
    function showModal() {
        var modalEl = document.getElementById('followUpNudgeModal');
        if (!modalEl || typeof bootstrap === 'undefined' || !bootstrap.Modal) return;
        markShown();
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }

    if (alreadyShown()) return;

    var wait = parseInt(cfg.waitMs, 10);
    if (!(wait >= 0)) wait = 0;
    timer = setTimeout(function () {
        whenReady(showModal);
    }, wait);
})();
</script>
@endauth
