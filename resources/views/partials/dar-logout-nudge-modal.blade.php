{{--
    Once-a-day DAR reminder when the user clicks Logout.
    Uses the same L30 DAR % and pink / green / red bands as /tasks/summary.
--}}
@auth
@php
    $darPct = (int) ($topbarDarPct ?? 0);
    $darCount = (int) ($topbarDarCount ?? 0);
    $darTarget = (int) ($topbarDarTarget ?? \App\Support\DarL30Metrics::TARGET);
    $darBand = (string) ($topbarDarBand ?? 'low');
    $darBandClass = $darBand === 'high' ? 'is-dar-high' : ($darBand === 'mid' ? 'is-dar-mid' : 'is-dar-low');
@endphp
<style>
    #darLogoutNudgeModal .modal-dialog { max-width: 440px; }
    #darLogoutNudgeModal .modal-content {
        border: none;
        border-radius: 22px;
        overflow: hidden;
        box-shadow: 0 24px 60px rgba(15, 23, 42, 0.22);
    }
    #darLogoutNudgeModal .modal-body {
        padding: 2rem 1.6rem 1.5rem;
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
                <span class="dar-nudge-emoji" aria-hidden="true">🔔</span>
                <div class="dar-nudge-kicker" id="darLogoutNudgeHello">DAR reminder</div>
                <div class="dar-nudge-pct {{ $darBandClass }}" id="darLogoutNudgePct">{{ $darPct }}%</div>
                <div class="dar-nudge-label">Your DAR · {{ $darCount }}/{{ $darTarget }} · keep above 90%</div>
                <p class="dar-nudge-msg" id="darLogoutNudgeMsg"></p>
                <div class="dar-nudge-actions">
                    <button type="button" class="btn btn-warning text-dark" id="darLogoutNudgeFill">Fill DAR</button>
                    <button type="button" class="btn btn-light" id="darLogoutNudgeContinue">Logout anyway</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var cfg = {
        userId: @json((int) auth()->id()),
        userName: @json(auth()->user()->name ?? ''),
        today: @json(\App\Support\TaskBusinessTime::today()->toDateString()),
        messages: @json(\App\Support\UserDarNudge::logoutMessages())
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
    function pickMessage() {
        var list = cfg.messages || [];
        if (!list.length) return 'Fill your DAR and keep it above 90% for promotions and incentives.';
        return list[Math.floor(Math.random() * list.length)];
    }
    function submitLogout() {
        var form = document.getElementById('logout-form');
        if (form) form.submit();
    }
    function openDarModal() {
        if (window.DarModal && typeof window.DarModal.open === 'function') {
            window.DarModal.open(null);
            return;
        }
        var btn = document.getElementById('darTopbarOpenBtn');
        if (btn) btn.click();
    }

    function showNudge() {
        var modalEl = document.getElementById('darLogoutNudgeModal');
        var msgEl = document.getElementById('darLogoutNudgeMsg');
        var helloEl = document.getElementById('darLogoutNudgeHello');
        if (!modalEl || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
            submitLogout();
            return;
        }
        if (msgEl) msgEl.textContent = pickMessage();
        if (helloEl && cfg.userName) helloEl.textContent = 'Hi ' + cfg.userName + ' — reminder';
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
            return;
        }
        if (t.closest('#darLogoutNudgeFill')) {
            e.preventDefault();
            var m = document.getElementById('darLogoutNudgeModal');
            if (m && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                var inst = bootstrap.Modal.getInstance(m);
                if (inst) inst.hide();
            }
            openDarModal();
        }
    });
})();
</script>
@endauth
