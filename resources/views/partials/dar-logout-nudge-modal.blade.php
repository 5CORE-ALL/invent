{{--
    Once-a-day DAR reminder when the user clicks Logout.
    Uses the same L30 DAR % and pink / green / red bands as /tasks/summary.
--}}
@auth
<style>
    #darLogoutNudgeModal .modal-dialog { max-width: 440px; }
    #darLogoutNudgeModal .modal-content {
        border: none;
        border-radius: 22px;
        overflow: hidden;
        box-shadow: 0 24px 60px rgba(15, 23, 42, 0.22);
    }
    #darLogoutNudgeModal .modal-body {
        position: relative;
        padding: 2rem 1.6rem 1.75rem;
        text-align: center;
        background: linear-gradient(180deg, #fff7ed 0%, #fff 55%);
    }
    .dar-nudge-emoji {
        font-size: 4.2rem;
        line-height: 1;
        display: block;
        margin-bottom: 0.35rem;
    }
    .dar-nudge-kicker {
        font-size: 0.74rem;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #9a3412;
        margin-bottom: 0.45rem;
    }
    .dar-nudge-pct {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 5.2rem;
        padding: 0.35rem 1.05rem;
        border-radius: 999px;
        font-size: 2rem;
        font-weight: 800;
        line-height: 1.15;
        font-variant-numeric: tabular-nums;
        margin: 0.15rem 0 0.45rem;
    }
    .dar-nudge-pct.is-dar-high { background: #fce7f3; color: #831843; }
    .dar-nudge-pct.is-dar-mid { background: #dcfce7; color: #166534; }
    .dar-nudge-pct.is-dar-low { background: #fee2e2; color: #991b1b; }
    .dar-nudge-label {
        font-size: 0.92rem;
        font-weight: 800;
        color: #7c2d12;
        margin-bottom: 0.8rem;
    }
    .dar-nudge-msg {
        font-size: 1.02rem;
        line-height: 1.5;
        color: #3f3f46;
        margin: 0 0 1.25rem;
        font-weight: 600;
    }
    .dar-nudge-actions {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    .dar-nudge-actions .btn {
        border-radius: 999px;
        font-weight: 800;
        padding: 0.65rem 1rem;
    }
</style>

<div class="modal fade" id="darLogoutNudgeModal" tabindex="-1" aria-labelledby="darLogoutNudgeTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body">
                <button type="button" class="btn-close position-absolute top-0 end-0 m-3" id="darLogoutNudgeContinue" aria-label="Close"></button>
                <span class="dar-nudge-emoji" aria-hidden="true">🔔</span>
                <div class="dar-nudge-kicker">DAR reminder</div>
                <h5 class="fw-bold mb-2" style="color:#1e293b;">Fill your DAR before logout</h5>
                <p class="dar-nudge-msg mb-0">File today's Daily Activity Report before you leave.</p>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var cfg = {
        userId: @json((int) auth()->id()),
        today: @json(\App\Support\TaskBusinessTime::today()->toDateString())
    };
    if (!cfg.userId) return;

    function storageKey() {
        return 'dar-logout-nudge:' + cfg.userId + ':' + cfg.today;
    }
    function alreadyShown() {
        try { return localStorage.getItem(storageKey()) === '1'; } catch (e) { return false; }
    }
    function markShown() {
        try { localStorage.setItem(storageKey(), '1'); } catch (e) {}
    }
    function submitLogout() {
        var form = document.getElementById('logout-form');
        if (form) form.submit();
    }
    function showNudge() {
        var modalEl = document.getElementById('darLogoutNudgeModal');
        if (!modalEl || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
            submitLogout();
            return;
        }
        markShown();
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }

    window.tsDarLogoutNudge = {
        requestLogout: function () {
            if (alreadyShown()) {
                submitLogout();
                return;
            }
            showNudge();
        }
    };

    document.addEventListener('click', function (e) {
        var t = e.target;
        if (!t || !t.closest) return;
        if (t.closest('#ts-logout-link')) {
            e.preventDefault();
            window.tsDarLogoutNudge.requestLogout();
            return;
        }
        if (t.closest('#darLogoutNudgeContinue')) {
            e.preventDefault();
            submitLogout();
        }
    });
})();
</script>
@endauth
