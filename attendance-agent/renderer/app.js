const $ = (id) => document.getElementById(id);

const views = { setup: $('setupView'), login: $('loginView'), dash: $('dashView') };

let state = {
    session: null,
    sessionStartedAtMs: 0,
    sessionActive: 0,
    sessionIdle: 0,
    sessionBreak: 0,
    daily: { active: 0, idle: 0, break: 0, date: '', date_label: '' },
    activityState: 'off',
};

let sessionFrozenSeconds = null;
let localTick = null;
let midnightTimer = null;

function showView(name) {
    Object.values(views).forEach((v) => v.classList.remove('active'));
    views[name]?.classList.add('active');
}

function showError(el, msg) {
    if (!msg) { el.classList.remove('show'); el.textContent = ''; return; }
    el.textContent = msg;
    el.classList.add('show');
}

function formatHms(seconds) {
    const s = Math.max(0, Math.floor(seconds || 0));
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    const sec = s % 60;
    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(sec).padStart(2, '0')}`;
}

function sessionElapsed() {
    if (!state.session) return 0;
    if (state.session.status === 'paused' || state.activityState === 'break') {
        return sessionFrozenSeconds ?? (state.sessionActive + state.sessionIdle);
    }
    return state.sessionActive + state.sessionIdle;
}

function currentStatusLabel() {
    const status = state.session?.status || 'off';
    if (!state.session || status === 'off') return { cls: 'status-off', text: 'Off duty' };
    if (status === 'paused' || state.activityState === 'break') {
        return { cls: 'status-paused', text: 'On break' };
    }
    if (state.activityState === 'idle') {
        return { cls: 'status-idle', text: 'IDLE' };
    }
    return { cls: 'status-active', text: 'Active — working' };
}

function applyDailyFromToday(today) {
    if (today == null) return;
    state.daily = {
        active: Number(today.active_seconds ?? 0),
        idle: Number(today.idle_seconds ?? 0),
        break: Number(today.break_seconds ?? 0),
        date: today.date ?? state.daily.date ?? '',
        date_label: today.date_label ?? state.daily.date_label ?? '',
    };
    renderDailyDom();
}

function renderDailyDom() {
    if ($('todayDate')) {
        $('todayDate').textContent = state.daily.date_label || '—';
    }
    if ($('dailyActive')) $('dailyActive').textContent = formatHms(state.daily.active);
    if ($('dailyIdle')) $('dailyIdle').textContent = formatHms(state.daily.idle);
    if ($('dailyBreak')) $('dailyBreak').textContent = formatHms(state.daily.break);
}

let lastSystemIdleSeconds = 0;
let idleThresholdSeconds = 30;

function applyUi(live) {
    if (live?.idle_threshold_seconds !== undefined) {
        idleThresholdSeconds = Math.max(5, Number(live.idle_threshold_seconds) || 30);
    }
    if (live?.idle_seconds !== undefined) {
        lastSystemIdleSeconds = Math.max(0, Number(live.idle_seconds) || 0);
    }
    if (live?.activity_state) state.activityState = live.activity_state;
    if (live?.today) {
        applyDailyFromToday({
            active_seconds: Math.max(state.daily.active, Number(live.today.active_seconds ?? 0)),
            idle_seconds: Math.max(state.daily.idle, Number(live.today.idle_seconds ?? 0)),
            break_seconds: Math.max(state.daily.break, Number(live.today.break_seconds ?? 0)),
            date_label: live.today.date_label ?? state.daily.date_label,
            date: live.today.date ?? state.daily.date,
        });
    }
    if (live?.active_seconds !== undefined && state.session
        && state.session.status !== 'paused' && state.activityState !== 'break') {
        state.sessionActive = live.active_seconds;
    }
    if (live?.idle_seconds_total !== undefined && state.session
        && state.session.status !== 'paused' && state.activityState !== 'break') {
        state.sessionIdle = live.idle_seconds_total;
    }
    if (live?.break_seconds !== undefined && state.session) {
        state.sessionBreak = live.break_seconds;
    }
    if (live?.session) {
        const wasBreak = state.session?.status === 'paused' || state.activityState === 'break';
        state.session = { ...state.session, ...live.session };
        if (live.session.started_at) {
            state.sessionStartedAtMs = new Date(live.session.started_at).getTime();
        }
        const nowBreak = live.session.status === 'paused' || live.session.activity_state === 'break';
        if (nowBreak && !wasBreak) {
            sessionFrozenSeconds = state.sessionActive + state.sessionIdle;
        } else if (!nowBreak && wasBreak) {
            sessionFrozenSeconds = null;
        }
    }

    const onBreak = state.session?.status === 'paused' || state.activityState === 'break';
    if (onBreak && sessionFrozenSeconds === null && state.session) {
        sessionFrozenSeconds = state.sessionActive + state.sessionIdle;
    }

    // Prefer activity_state; also turn red from system idle so the UI can't lag behind.
    const isIdle = !!state.session
        && state.session.status === 'active'
        && !onBreak
        && (state.activityState === 'idle' || lastSystemIdleSeconds >= idleThresholdSeconds);

    if (isIdle && state.activityState !== 'idle') {
        state.activityState = 'idle';
    }

    const { cls, text } = isIdle
        ? { cls: 'status-idle', text: 'IDLE' }
        : currentStatusLabel();
    const badge = $('statusBadge');
    badge.className = `status-badge ${cls}`;
    $('statusText').textContent = text;

    $('sessionTimer').textContent = state.session ? formatHms(sessionElapsed()) : '00:00:00';
    $('heroCard')?.classList.toggle('is-idle', isIdle);
    $('sessionRow')?.classList.toggle('is-idle', isIdle);
    $('sessionRow')?.classList.toggle('frozen', onBreak);
    $('idleStatBox')?.classList.toggle('is-idle', isIdle);
    if (onBreak) {
        if ($('sessionLabel')) $('sessionLabel').textContent = 'Session paused';
    } else if (isIdle) {
        if ($('sessionLabel')) $('sessionLabel').textContent = 'IDLE';
    } else if ($('sessionLabel')) {
        $('sessionLabel').textContent = 'Current session';
    }
    renderDailyDom();

    $('activeStatBox')?.classList.toggle('stat-live', state.session?.status === 'active' && !isIdle && !onBreak);
    $('idleStatBox')?.classList.toggle('stat-live', isIdle);
    $('breakStatBox')?.classList.toggle('stat-live', onBreak);

    const clockedIn = state.session && (state.session.status === 'active' || state.session.status === 'paused');
    $('actionsZone')?.classList.toggle('actions-bottom', clockedIn);
    $('clockInWrap').style.display = clockedIn ? 'none' : 'flex';
    $('activeActions').style.display = clockedIn ? 'flex' : 'none';
    $('btnClockOut').style.display = clockedIn ? 'inline-flex' : 'none';
    $('btnPause').style.display = state.session?.status === 'active' ? 'inline-flex' : 'none';
    $('btnResume').style.display = state.session?.status === 'paused' ? 'inline-flex' : 'none';
}

function syncFromServer({ session, today, live }) {
    state.session = session ? { ...session } : null;
    state.sessionStartedAtMs = session?.started_at ? new Date(session.started_at).getTime() : 0;
    state.sessionActive = session?.active_seconds ?? 0;
    state.sessionIdle = session?.idle_seconds ?? 0;
    state.sessionBreak = session?.break_seconds ?? 0;
    state.activityState = session?.activity_state
        || (session?.status === 'paused' ? 'break' : session ? 'working' : 'off');

    if (session?.status === 'paused') {
        sessionFrozenSeconds = (session.active_seconds ?? 0) + (session.idle_seconds ?? 0);
        stopLocalTick();
    } else if (session?.status === 'active') {
        sessionFrozenSeconds = null;
        startLocalTick();
    } else {
        sessionFrozenSeconds = null;
        stopLocalTick();
    }

    if (today != null) {
        applyDailyFromToday(today);
    }

    applyUi({ ...(live || {}), today: today ?? undefined });
    scheduleMidnightRefresh();
}

function startLocalTick() {
    if (localTick) return;
    localTick = setInterval(() => applyUi(null), 1000);
}

function stopLocalTick() {
    if (localTick) { clearInterval(localTick); localTick = null; }
}

function scheduleMidnightRefresh() {
    if (midnightTimer) clearTimeout(midnightTimer);
    const now = new Date();
    const next = new Date(now);
    next.setHours(24, 0, 0, 0);
    const ms = next - now;
    midnightTimer = setTimeout(() => {
        state.daily = { active: 0, idle: 0, break: 0, date: '', date_label: '' };
        refresh().catch(() => {});
    }, ms + 500);
}

async function refresh(opts = {}) {
    const setup = await window.agent.getSetup();
    if (!setup.configured) {
        $('apiUrl').value = setup.apiUrl || '';
        showView('setup');
        return;
    }

    const res = await window.agent.getState();
    if (!res.loggedIn) {
        syncFromServer({ session: null, today: null });
        showView('login');
        if (res.error) showError($('loginError'), res.error);
        return;
    }

    $('userName').textContent = res.user?.name || opts.user?.name || 'Employee';
    $('userEmail').textContent = res.user?.email || opts.user?.email || '';
    syncFromServer({ session: res.session, today: res.today, live: res.live || {} });
    renderDailyDom();
    showView('dash');
    showError($('loginError'), '');
}

function showUpdateOverlay(payload) {
    const overlay = $('updateOverlay');
    const banner = $('updateBanner');
    if (!payload || payload.available === false || !payload.latest_version) {
        overlay?.classList.add('hidden');
        overlay?.setAttribute('aria-hidden', 'true');
        banner?.classList.add('hidden');
        return;
    }

    if ($('updateCurrent')) $('updateCurrent').textContent = `v${payload.current_version || '—'}`;
    if ($('updateLatest')) $('updateLatest').textContent = `v${payload.latest_version || '—'}`;
    if ($('updateMessage') && payload.message) $('updateMessage').textContent = payload.message;
    if ($('updateBannerVersions')) {
        $('updateBannerVersions').textContent = `v${payload.current_version || '—'} → v${payload.latest_version}`;
    }

    // Small banner always stays visible while an update exists.
    banner?.classList.remove('hidden');

    const showModal = payload.force_show === true || payload.snoozed === false;
    if (showModal && !payload.snoozed) {
        overlay?.classList.remove('hidden');
        overlay?.setAttribute('aria-hidden', 'false');
    } else {
        overlay?.classList.add('hidden');
        overlay?.setAttribute('aria-hidden', 'true');
    }
}

async function init() {
    if (typeof window.agent.onToday === 'function') {
        window.agent.onToday((today) => applyDailyFromToday(today));
    }

    if (typeof window.agent.onStats === 'function') {
        window.agent.onStats((live) => {
            if (live?.today) applyDailyFromToday(live.today);
            if (!views.dash.classList.contains('active')) return;
            applyUi(live);
        });
    }

    if (typeof window.agent.onSessionPaused === 'function') {
        window.agent.onSessionPaused(() => refresh().catch(() => {}));
    }

    if (typeof window.agent.onShow === 'function') {
        window.agent.onShow(() => refresh().catch(() => {}));
    }

    if (typeof window.agent.onUpdateAvailable === 'function') {
        window.agent.onUpdateAvailable((payload) => showUpdateOverlay(payload));
    }

    if (typeof window.agent.onForcedSignOut === 'function') {
        window.agent.onForcedSignOut((payload) => {
            syncFromServer({ session: null, today: null });
            showView('login');
            showError($('loginError'), payload?.message || 'You were signed out by an administrator.');
        });
    }

    const openUpdate = async (btn) => {
        if (btn) btn.disabled = true;
        try {
            await window.agent.openUpdateDownload();
        } finally {
            if (btn) btn.disabled = false;
        }
    };
    if ($('btnUpdateNow')) {
        $('btnUpdateNow').onclick = () => openUpdate($('btnUpdateNow'));
    }
    if ($('btnUpdateBanner')) {
        $('btnUpdateBanner').onclick = () => openUpdate($('btnUpdateBanner'));
    }
    if ($('btnUpdateLater')) {
        $('btnUpdateLater').onclick = async () => {
            await window.agent.snoozeUpdate();
        };
    }

    $('btnSaveSetup').onclick = async () => {
        const url = $('apiUrl').value.trim();
        if (!url) { showError($('setupError'), 'Please enter the server URL.'); return; }
        $('btnSaveSetup').disabled = true;
        const r = await window.agent.saveSetup({ apiUrl: url });
        $('btnSaveSetup').disabled = false;
        if (!r.ok) { showError($('setupError'), r.message || 'Could not save.'); return; }
        showError($('setupError'), '');
        await refresh();
    };

    $('btnLogin').onclick = async () => {
        showError($('loginError'), '');
        $('btnLogin').disabled = true;
        $('btnLogin').textContent = 'Signing in…';
        try {
            const r = await window.agent.login({
                email: $('email').value.trim(),
                password: $('password').value,
            });
            if (!r.ok) {
                showError($('loginError'), r.message || 'Login failed.');
                return;
            }
            $('userName').textContent = r.user?.name || 'Employee';
            $('userEmail').textContent = r.user?.email || '';
            showView('dash');
            await refresh({ user: r.user });
        } catch (err) {
            showError($('loginError'), err?.message || 'Sign in failed.');
        } finally {
            $('btnLogin').disabled = false;
            $('btnLogin').textContent = 'Sign In';
        }
    };

    $('btnGoogleLogin').onclick = async () => {
        showError($('loginError'), '');
        $('btnGoogleLogin').disabled = true;
        $('btnGoogleLoginLabel').textContent = 'Waiting for Google sign-in…';
        try {
            const r = await window.agent.googleLogin();
            if (!r.ok) {
                showError($('loginError'), r.message || 'Google sign-in failed.');
                return;
            }
            $('userName').textContent = r.user?.name || 'Employee';
            $('userEmail').textContent = r.user?.email || '';
            showView('dash');
            await refresh({ user: r.user });
        } catch (err) {
            showError($('loginError'), err?.message || 'Google sign-in failed.');
        } finally {
            $('btnGoogleLogin').disabled = false;
            $('btnGoogleLoginLabel').textContent = 'Sign in with Google';
        }
    };

    $('btnChangeServer').onclick = async () => {
        await window.agent.signOut();
        $('apiUrl').value = (await window.agent.getSetup()).apiUrl || '';
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
        const r = await window.agent.clockIn();
        $('btnClockIn').disabled = false;
        if (!r.ok) { alert(r.message || 'Clock in failed.'); return; }
        await refresh();
    };

    $('btnClockOut').onclick = async () => {
        if (!confirm('Clock out and stop tracking?')) return;
        await window.agent.clockOut();
        await refresh();
    };

    $('btnPause').onclick = async () => {
        await window.agent.pause();
        await refresh();
    };

    $('btnResume').onclick = async () => {
        await window.agent.resume();
        await refresh();
    };

    $('btnMinimize').onclick = () => window.agent.minimizeToTray();

    await refresh();
}

init().catch((err) => {
    console.error('init failed', err);
    const loginError = document.getElementById('loginError');
    if (loginError) {
        loginError.textContent = 'App failed to start. Please restart the app.';
        loginError.classList.add('show');
    }
});
