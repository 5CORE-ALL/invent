/**
 * Browser-side activity tracker for attendance monitoring.
 * Sends heartbeats while user has an active clock-in session.
 */
window.AttendanceTracker = (function () {
    let timer = null;
    let lastActivity = Date.now();
    let idleThreshold = 120;
    let interval = 60;
    let baseUrl = '';
    let csrf = '';
    let enabled = false;

    function bindActivityListeners() {
        ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart', 'click'].forEach(evt => {
            document.addEventListener(evt, () => { lastActivity = Date.now(); }, { passive: true });
        });
    }

    function idleSeconds() {
        return Math.floor((Date.now() - lastActivity) / 1000);
    }

    function isActive() {
        return idleSeconds() < idleThreshold;
    }

    async function sendHeartbeat() {
        if (!enabled) return;
        try {
            const payload = {
                is_active: isActive(),
                idle_seconds: idleSeconds(),
                window_title: document.title || null,
                page_url: location.pathname + location.search
            };
            await fetch(baseUrl + '/heartbeat', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            });
        } catch (e) {
            // silent — network blips are normal
        }
    }

    async function checkStatus() {
        try {
            const r = await fetch(baseUrl + '/status', {
                headers: { 'Accept': 'application/json' }
            });
            const data = await r.json();
            enabled = !!(data.has_session && data.monitoring_enabled !== false && data.session?.status === 'active');
            if (data.heartbeat_interval) interval = data.heartbeat_interval;
            if (data.idle_threshold) idleThreshold = data.idle_threshold;
            return data;
        } catch (e) {
            enabled = false;
            return null;
        }
    }

    function startTimer() {
        if (timer) clearInterval(timer);
        timer = setInterval(async () => {
            await checkStatus();
            if (enabled) await sendHeartbeat();
        }, interval * 1000);
    }

    return {
        async init(opts) {
            baseUrl = opts.baseUrl || '/attendance';
            csrf = opts.csrf || '';
            bindActivityListeners();
            await checkStatus();
            if (enabled) await sendHeartbeat();
            startTimer();
        }
    };
})();
