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
    var waiting = true;
    var onVisible = null;
    var bus = null;
    var tabId = Math.random().toString(36).slice(2) + Date.now().toString(36);

    function storageKey() {
        return 'follow-up-nudge:' + cfg.userId + ':' + cfg.today;
    }
    function lockKey() {
        return 'follow-up-nudge-lock:' + cfg.userId + ':' + cfg.today;
    }
    function cookieName() {
        return 'fu_nudge_' + cfg.userId;
    }
    function readCookie() {
        var prefix = cookieName() + '=';
        var parts = String(document.cookie || '').split(';');
        for (var i = 0; i < parts.length; i++) {
            var part = parts[i].replace(/^\s+/, '');
            if (part.indexOf(prefix) === 0) return decodeURIComponent(part.slice(prefix.length));
        }
        return '';
    }
    function alreadyShown() {
        if (readCookie() === cfg.today) return true;
        try {
            var v = localStorage.getItem(storageKey());
            return v === '1' || (typeof v === 'string' && v.indexOf('off:') === 0);
        } catch (e) {
            return false;
        }
    }
    function writeCookie() {
        document.cookie = cookieName() + '=' + encodeURIComponent(cfg.today) + '; path=/; max-age=172800; SameSite=Lax';
    }
    function notifyTabs() {
        try { if (bus) bus.postMessage('off'); } catch (e) {}
    }
    function markShown() {
        try { localStorage.setItem(storageKey(), '1'); } catch (e) {}
        writeCookie();
        notifyTabs();
    }
    function markDismissed() {
        try { localStorage.setItem(storageKey(), 'off:' + Date.now()); } catch (e) {}
        writeCookie();
        notifyTabs();
    }
    function stopWaiting() {
        waiting = false;
        if (timer) clearTimeout(timer);
        timer = null;
        if (onVisible) {
            document.removeEventListener('visibilitychange', onVisible);
            onVisible = null;
        }
    }
    function hideIfOpen() {
        var modalEl = document.getElementById('followUpNudgeModal');
        if (!modalEl || typeof bootstrap === 'undefined' || !bootstrap.Modal) return;
        if (!modalEl.classList.contains('show')) return;
        var inst = bootstrap.Modal.getInstance(modalEl);
        if (inst) inst.hide();
    }
    function claimShow(done) {
        if (!waiting || alreadyShown()) {
            done(false);
            return;
        }
        var key = lockKey();
        var token = tabId + ':' + Date.now();
        try {
            var existing = localStorage.getItem(key);
            if (existing) {
                var at = parseInt(String(existing).split(':').pop(), 10);
                if (at && (Date.now() - at) < 15000) {
                    done(false);
                    return;
                }
            }
            localStorage.setItem(key, token);
        } catch (e) {
            done(true);
            return;
        }
        setTimeout(function () {
            try {
                done(waiting && !alreadyShown() && localStorage.getItem(key) === token);
            } catch (e) {
                done(waiting && !alreadyShown());
            }
        }, 60);
    }
    window.addEventListener('storage', function (e) {
        if (!e || e.key !== storageKey() || !e.newValue) return;
        stopWaiting();
        hideIfOpen();
    });
    try {
        bus = new BroadcastChannel('follow-up-nudge:' + cfg.userId + ':' + cfg.today);
        bus.onmessage = function () {
            stopWaiting();
            hideIfOpen();
        };
    } catch (e) {}
    function waitUntilHidden(el, then) {
        if (!waiting || alreadyShown()) return;
        if (el && el.classList.contains('show')) {
            el.addEventListener('hidden.bs.modal', function () {
                if (!waiting || alreadyShown()) return;
                then();
            }, { once: true });
            return;
        }
        then();
    }
    function whenReady(cb) {
        waitUntilHidden(document.getElementById('overdueNudgeModal'), function () {
            setTimeout(function () {
                if (!waiting || alreadyShown()) return;
                waitUntilHidden(document.getElementById('tatNudgeModal'), cb);
            }, 400);
        });
    }
    function showModal() {
        if (!waiting || alreadyShown()) return;
        var modalEl = document.getElementById('followUpNudgeModal');
        if (!modalEl || typeof bootstrap === 'undefined' || !bootstrap.Modal) return;
        markShown();
        stopWaiting();
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
    function present() {
        if (!waiting || alreadyShown()) return;
        if (document.visibilityState !== 'visible') {
            if (onVisible) return;
            onVisible = function () {
                if (document.visibilityState !== 'visible') return;
                document.removeEventListener('visibilitychange', onVisible);
                onVisible = null;
                present();
            };
            document.addEventListener('visibilitychange', onVisible);
            return;
        }
        claimShow(function (won) {
            if (!won || !waiting || alreadyShown()) return;
            whenReady(showModal);
        });
    }

    var modalEl = document.getElementById('followUpNudgeModal');
    if (modalEl) {
        modalEl.addEventListener('hide.bs.modal', function () {
            markDismissed();
        });
    }

    if (alreadyShown()) return;

    var wait = parseInt(cfg.waitMs, 10);
    if (!(wait >= 0)) wait = 0;
    timer = setTimeout(function () {
        timer = null;
        present();
    }, wait);
})();
</script>
@endauth
