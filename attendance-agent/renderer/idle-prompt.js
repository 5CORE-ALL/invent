(function () {
    const phaseConfirm = document.getElementById('phaseConfirm');
    const phaseIdle = document.getElementById('phaseIdle');
    const btnYes = document.getElementById('btnYes');
    const btnNo = document.getElementById('btnNo');
    const btnOk = document.getElementById('btnOk');
    const idleTimeEl = document.getElementById('idleTime');
    const idleTimeIdleEl = document.getElementById('idleTimeIdle');
    const countdownEl = document.getElementById('countdownTime');
    const iconEl = document.getElementById('icon');

    let confirmCountdownTimer = null;
    let confirmCountdownTick = null;
    let timedOut = false;
    let remainingSec = 60;

    function formatHms(seconds) {
        const s = Math.max(0, Math.floor(seconds || 0));
        const h = Math.floor(s / 3600);
        const m = Math.floor((s % 3600) / 60);
        const sec = s % 60;
        return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(sec).padStart(2, '0')}`;
    }

    function formatCountdown(seconds) {
        const s = Math.max(0, Math.floor(seconds || 0));
        const m = Math.floor(s / 60);
        const sec = s % 60;
        if (m > 0) {
            return `${m}:${String(sec).padStart(2, '0')}`;
        }
        return `0:${String(sec).padStart(2, '0')}`;
    }

    function renderCountdown() {
        if (countdownEl) countdownEl.textContent = formatCountdown(remainingSec);
    }

    function renderIdleTime(stats) {
        if (!stats) return;
        const secs = stats.prompt_idle_seconds ?? stats.session_idle_seconds ?? 0;
        const text = formatHms(secs);
        if (idleTimeEl) idleTimeEl.textContent = text;
        if (idleTimeIdleEl) idleTimeIdleEl.textContent = text;
    }

    function clearConfirmTimers() {
        if (confirmCountdownTimer) {
            clearTimeout(confirmCountdownTimer);
            confirmCountdownTimer = null;
        }
        if (confirmCountdownTick) {
            clearInterval(confirmCountdownTick);
            confirmCountdownTick = null;
        }
    }

    function respond(choice) {
        clearConfirmTimers();
        if (btnYes) btnYes.disabled = true;
        if (btnNo) btnNo.disabled = true;
        if (btnOk) btnOk.disabled = true;
        if (window.idlePrompt && typeof window.idlePrompt.respond === 'function') {
            window.idlePrompt.respond(choice);
        }
    }

    function showIdlePhase() {
        timedOut = true;
        clearConfirmTimers();
        phaseConfirm?.classList.add('hidden');
        phaseIdle?.classList.remove('hidden');
        if (iconEl) iconEl.textContent = '☕';
        document.title = 'Idle time';
        if (window.idlePrompt && typeof window.idlePrompt.resize === 'function') {
            window.idlePrompt.resize(332, 228);
        }
    }

    function startConfirmCountdown(seconds) {
        remainingSec = seconds;
        renderCountdown();
        clearConfirmTimers();
        confirmCountdownTick = setInterval(() => {
            remainingSec -= 1;
            renderCountdown();
            if (remainingSec <= 0) {
                clearInterval(confirmCountdownTick);
                confirmCountdownTick = null;
            }
        }, 1000);
        confirmCountdownTimer = setTimeout(() => {
            if (!timedOut) showIdlePhase();
        }, seconds * 1000);
    }

    if (btnYes) btnYes.addEventListener('click', () => respond('yes'));
    if (btnNo) btnNo.addEventListener('click', () => respond('no'));
    if (btnOk) btnOk.addEventListener('click', () => respond('ok'));

    const params = new URLSearchParams(window.location.search);
    let timeoutSec = parseInt(params.get('timeout') || '60', 10);
    if (!Number.isFinite(timeoutSec) || timeoutSec < 1) {
        timeoutSec = 60;
    }

    if (window.idlePrompt) {
        if (typeof window.idlePrompt.getConfig === 'function') {
            window.idlePrompt.getConfig().then((cfg) => {
                if (cfg?.timeout_seconds) {
                    timeoutSec = parseInt(cfg.timeout_seconds, 10) || timeoutSec;
                }
                renderIdleTime(cfg);
                startConfirmCountdown(timeoutSec);
            }).catch(() => startConfirmCountdown(timeoutSec));
        } else {
            startConfirmCountdown(timeoutSec);
        }

        if (typeof window.idlePrompt.onStats === 'function') {
            window.idlePrompt.onStats((stats) => renderIdleTime(stats));
        }

        if (typeof window.idlePrompt.onPhase === 'function') {
            window.idlePrompt.onPhase((data) => {
                if (data?.phase === 'idle') showIdlePhase();
            });
        }
    } else {
        startConfirmCountdown(timeoutSec);
    }
})();
