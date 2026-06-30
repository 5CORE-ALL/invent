const $ = (id) => document.getElementById(id);

const views = {
    setup: $('setupView'),
    login: $('loginView'),
    dash: $('dashView'),
};

/** @type {{ session: object|null, activeSeconds: number, idleSeconds: number, syncedAt: number, pausedAt: number|null }} */
let clock = {
    session: null,
    activeSeconds: 0,
    idleSeconds: 0,
    syncedAt: 0,
    pausedAt: null,
};

let localTick = null;

function showView(name) {
    Object.values(views).forEach((v) => v.classList.remove('active'));
    views[name]?.classList.add('active');
}

function showError(el, msg) {
    if (!msg) {
        el.classList.remove('show');
        el.textContent = '';
        return;
    }
    el.textContent = msg;
    el.classList.add('show');
}

function formatDuration(seconds) {
    const s = Math.max(0, Math.floor(seconds || 0));
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    const sec = s % 60;
    if (h > 0) {
        return `${h}:${String(m).padStart(2, '0')}:${String(sec).padStart(2, '0')}`;
    }
    return `${m}:${String(sec).padStart(2, '0')}`;
}

function formatHms(seconds) {
    const s = Math.max(0, Math.floor(seconds || 0));
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    const sec = s % 60;
    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(sec).padStart(2, '0')}`;
}

function wallClockElapsed(session) {
    if (!session?.started_at) return 0;
    const start = new Date(session.started_at).getTime();
    if (session.status === 'paused' && clock.pausedAt) {
        return Math.floor((clock.pausedAt - start) / 1000);
    }
    return Math.floor((Date.now() - start) / 1000);
}

function projectedActiveIdle() {
    let active = clock.activeSeconds;
    let idle = clock.idleSeconds;
    if (clock.session?.status === 'active' && clock.syncedAt > 0) {
        const extra = Math.floor((Date.now() - clock.syncedAt) / 1000);
        active += extra;
    }
    return { active, idle };
}

function applyClockToUi(live) {
    const session = clock.session;
    const status = session?.status || 'off';
    const badge = $('statusBadge');
    const statusText = $('statusText');

    badge.className = 'status-badge ';
    if (status === 'active') {
        badge.className += 'status-active';
        statusText.textContent = 'Clocked in — tracking';
    } else if (status === 'paused') {
        badge.className += 'status-paused';
        statusText.textContent = 'Paused';
    } else {
        badge.className += 'status-off';
        statusText.textContent = 'Not clocked in';
    }

    $('timerDisplay').textContent = formatHms(wallClockElapsed(session));
    const { active, idle } = projectedActiveIdle();
    $('activeTime').textContent = formatDuration(active);
    $('idleTime').textContent = formatDuration(idle);

    if (live) {
        $('currentApp').textContent = live.process || live.app || '—';
        $('currentWindow').textContent = live.title || (status === 'active' ? 'Monitoring…' : 'Start your day with Clock In');
    }

    const clockedIn = status === 'active' || status === 'paused';
    $('btnClockIn').style.display = clockedIn ? 'none' : 'block';
    $('btnClockOut').style.display = clockedIn ? 'block' : 'none';
    $('btnPause').style.display = status === 'active' ? 'block' : 'none';
    $('btnResume').style.display = status === 'paused' ? 'block' : 'none';
    $('locationField').style.display = clockedIn ? 'none' : 'block';
}

function syncClockFromSession(session, live) {
    if (!session) {
        clock.session = null;
        clock.activeSeconds = 0;
        clock.idleSeconds = 0;
        clock.syncedAt = 0;
        clock.pausedAt = null;
        stopLocalTick();
        applyClockToUi(live);
        return;
    }

    const wasPaused = clock.session?.status === 'paused';
    clock.session = { ...session };

    if (session.active_seconds !== undefined) {
        clock.activeSeconds = session.active_seconds;
        clock.idleSeconds = session.idle_seconds || 0;
        clock.syncedAt = Date.now();
    }

    if (session.status === 'paused') {
        if (!wasPaused || !clock.pausedAt) {
            clock.pausedAt = Date.now();
        }
        stopLocalTick();
    } else if (session.status === 'active') {
        clock.pausedAt = null;
        startLocalTick();
    } else {
        clock.pausedAt = null;
        stopLocalTick();
    }

    applyClockToUi(live);
}

function startLocalTick() {
    if (localTick) return;
    localTick = setInterval(() => applyClockToUi(null), 1000);
}

function stopLocalTick() {
    if (localTick) {
        clearInterval(localTick);
        localTick = null;
    }
}

function updateDashboard(state, live) {
    if (live?.session) {
        syncClockFromSession(live.session, live);
        return;
    }
    if (state.session) {
        syncClockFromSession(state.session, live);
        return;
    }
    applyClockToUi(live);
}

async function refresh() {
    const setup = await window.agent.getSetup();
    if (!setup.configured) {
        $('apiUrl').value = setup.apiUrl || '';
        showView('setup');
        return;
    }

    const state = await window.agent.getState();
    if (!state.loggedIn) {
        syncClockFromSession(null);
        showView('login');
        return;
    }

    $('userName').textContent = state.user?.name || 'Employee';
    $('userEmail').textContent = state.user?.email || '';
    syncClockFromSession(state.session, state.live || {});
    showView('dash');
}

async function init() {
    $('btnSaveSetup').onclick = async () => {
        const url = $('apiUrl').value.trim();
        if (!url) {
            showError($('setupError'), 'Please enter the server URL.');
            return;
        }
        $('btnSaveSetup').disabled = true;
        const r = await window.agent.saveSetup({ apiUrl: url });
        $('btnSaveSetup').disabled = false;
        if (!r.ok) {
            showError($('setupError'), r.message || 'Could not save.');
            return;
        }
        showError($('setupError'), '');
        await refresh();
    };

    $('btnLogin').onclick = async () => {
        showError($('loginError'), '');
        $('btnLogin').disabled = true;
        $('btnLogin').textContent = 'Signing in…';
        const r = await window.agent.login({
            email: $('email').value.trim(),
            password: $('password').value,
        });
        $('btnLogin').disabled = false;
        $('btnLogin').textContent = 'Sign In';
        if (!r.ok) {
            showError($('loginError'), r.message || 'Login failed.');
            return;
        }
        await refresh();
    };

    $('btnChangeServer').onclick = async () => {
        await window.agent.signOut();
        const setup = await window.agent.getSetup();
        $('apiUrl').value = setup.apiUrl || '';
        showView('setup');
    };

    $('btnSignOut').onclick = async () => {
        if (!confirm('Sign out of 5Core Attendance?')) return;
        await window.agent.signOut();
        $('password').value = '';
        await refresh();
    };

    $('btnClockIn').onclick = async () => {
        $('btnClockIn').disabled = true;
        $('btnClockIn').textContent = 'Clocking in…';
        const r = await window.agent.clockIn({ work_location: $('workLocation').value });
        $('btnClockIn').disabled = false;
        $('btnClockIn').textContent = '▶ Clock In';
        if (!r.ok) {
            alert(r.message || 'Clock in failed. Is Laravel running?');
            return;
        }
        if (r.session) {
            syncClockFromSession(r.session);
            showView('dash');
        }
        refresh().catch(() => {});
    };

    $('btnClockOut').onclick = async () => {
        if (!confirm('Clock out and stop tracking?')) return;
        await window.agent.clockOut();
        syncClockFromSession(null);
        await refresh();
    };

    $('btnPause').onclick = async () => {
        const r = await window.agent.pause();
        if (r.session) {
            syncClockFromSession(r.session);
        } else if (clock.session) {
            clock.session.status = 'paused';
            clock.pausedAt = Date.now();
            stopLocalTick();
            applyClockToUi(null);
        }
        refresh().catch(() => {});
    };

    $('btnResume').onclick = async () => {
        const r = await window.agent.resume();
        if (r.session) {
            syncClockFromSession(r.session);
        } else if (clock.session) {
            clock.session.status = 'active';
            clock.pausedAt = null;
            startLocalTick();
            applyClockToUi(null);
        }
        refresh().catch(() => {});
    };

    $('btnMinimize').onclick = () => window.agent.minimizeToTray();

    window.agent.onStats((live) => {
        if (!views.dash.classList.contains('active')) return;
        if (live.session) {
            clock.session = { ...clock.session, ...live.session };
        }
        if (live.active_seconds !== undefined) {
            clock.activeSeconds = live.active_seconds;
            clock.idleSeconds = live.idle_seconds_total ?? live.idle_seconds ?? 0;
            clock.syncedAt = Date.now();
        }
        applyClockToUi(live);
    });

    setInterval(() => {
        if (views.dash.classList.contains('active')) refresh();
    }, 60000);

    await refresh();
}

init();
