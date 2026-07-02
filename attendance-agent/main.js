const {
    app, BrowserWindow, Tray, Menu, nativeImage, powerMonitor,
    desktopCapturer, screen, ipcMain, shell,
} = require('electron');
const path = require('path');
const os = require('os');
const { execFile } = require('child_process');
const { promisify } = require('util');
const axios = require('axios');
const FormData = require('form-data');
const Store = require('electron-store');

const execFileAsync = promisify(execFile);
const store = new Store();
const AGENT_VERSION = '1.1.0';

const gotTheLock = app.requestSingleInstanceLock();
if (!gotTheLock) {
    app.quit();
}

const MACHINE_ID = store.get('machineId') || `${os.hostname()}-${os.userInfo().username}`.slice(0, 120);
store.set('machineId', MACHINE_ID);

const IGNORED_PROCESSES = new Set([
    'powershell', 'pwsh', 'cmd', 'conhost', 'windowsterminal',
    'electron', 'searchhost', 'shellexperiencehost', 'applicationframehost',
]);

let tray = null;
let win = null;
let idlePromptWin = null;
let heartbeatTimer = null;
let screenshotTimer = null;
let windowPollTimer = null;
let idleCheckTimer = null;
let uiTickTimer = null;
let lastLive = { title: '', process: '', app: '' };
let lastKnownWindow = { title: '', process: '' };
let lastSessionStats = { active_seconds: 0, idle_seconds: 0, break_seconds: 0 };
let lastSessionMeta = null;
let lastHeartbeatSentAt = Date.now();
let idlePromptOpen = false;
let idleAtPromptOpen = 0;
let idlePromptPhase = null;
let idlePromptStatsTimer = null;
let idlePromptAutoTimeout = null;
let activityState = 'working';
let localStats = { active: 0, idle: 0, break: 0 };
let dailyStats = { active: 0, idle: 0, break: 0, date: '', date_label: '' };
let sessionStartedAtMs = null;
let apiQueue = Promise.resolve();
let trackingStarting = false;

function enqueueApi(fn) {
    const run = apiQueue.then(() => fn()).catch((e) => { throw e; });
    apiQueue = run.catch(() => {});
    return run;
}

function jsonApi(timeoutMs = 15000) {
    const token = store.get('token');
    return axios.create({
        baseURL: getAgentApiPath(),
        timeout: timeoutMs,
        headers: {
            Authorization: token ? `Bearer ${token}` : undefined,
            'X-Machine-Id': MACHINE_ID,
            Accept: 'application/json',
            'Content-Type': 'application/json',
        },
    });
}

function uploadApi(timeoutMs = 120000) {
    const token = store.get('token');
    return axios.create({
        baseURL: getAgentApiPath(),
        timeout: timeoutMs,
        maxBodyLength: Infinity,
        maxContentLength: Infinity,
        headers: {
            Authorization: token ? `Bearer ${token}` : undefined,
            'X-Machine-Id': MACHINE_ID,
            Accept: 'application/json',
        },
    });
}

function api() {
    return jsonApi(15000);
}

let config = {
    heartbeat_interval_seconds: 15,
    screenshot_interval_seconds: 30,
    idle_threshold_seconds: 30,
    idle_prompt_seconds: 30,
    idle_prompt_timeout_seconds: 60,
    screenshots_enabled: true,
};

function getApiBase() {
    const fromEnv = process.env.FIVECORE_API_URL;
    const fromStore = store.get('apiUrl');
    let base = (fromEnv || fromStore || 'https://inventory.5coremanagement.com').replace(/\/+$/, '');
    base = base.replace(/\/api$/i, '').replace(/\/attendance$/i, '');
    return base;
}

function getAgentApiPath() {
    return `${getApiBase()}/attendance/desktop-api`;
}

function isIgnoredProcess(name) {
    const n = String(name || '').toLowerCase().replace(/\.exe$/i, '');
    if (!n) return true;
    if (IGNORED_PROCESSES.has(n)) return true;
    return n.includes('5core') || n.includes('attendance');
}

function applyWindowResult(result) {
    const title = String(result?.title || '').trim();
    const process = String(result?.process || '').trim();
    if (!title && !process) {
        return false;
    }
    if (process && !isIgnoredProcess(process)) {
        lastKnownWindow = { title, process };
        lastLive = { title, process, app: process };
        return true;
    }
    if (title) {
        lastKnownWindow = { title, process: lastKnownWindow.process || process };
        lastLive = {
            title,
            process: lastKnownWindow.process || process,
            app: lastKnownWindow.process || process || title,
        };
        return Boolean(lastKnownWindow.process || process);
    }
    return false;
}

async function getActiveWindow() {
    if (process.platform !== 'win32') {
        return { title: '', process: '' };
    }
    try {
        const script = path.join(__dirname, 'scripts', 'get-active-window.ps1');
        const { stdout } = await execFileAsync(
            'powershell.exe',
            ['-NoProfile', '-NonInteractive', '-WindowStyle', 'Hidden', '-ExecutionPolicy', 'Bypass', '-File', script],
            { timeout: 6000, windowsHide: true }
        );
        const parts = stdout.trim().split('|||');
        return { title: parts[0] || '', process: parts[1] || '' };
    } catch {
        return lastKnownWindow;
    }
}

async function pollActiveWindow() {
    const result = await getActiveWindow();
    if (!applyWindowResult(result) && lastKnownWindow.process) {
        lastLive = { ...lastKnownWindow, app: lastKnownWindow.process };
    }
}

async function captureScreenshot() {
    const displays = screen.getAllDisplays();
    if (!displays.length) return null;

    const minX = Math.min(...displays.map((d) => d.bounds.x));
    const minY = Math.min(...displays.map((d) => d.bounds.y));
    const maxX = Math.max(...displays.map((d) => d.bounds.x + d.bounds.width));
    const maxY = Math.max(...displays.map((d) => d.bounds.y + d.bounds.height));
    const totalW = maxX - minX;
    const totalH = maxY - minY;

    const scale = Math.min(1, 3840 / totalW, 2160 / totalH);
    const outW = Math.max(1, Math.round(totalW * scale));
    const outH = Math.max(1, Math.round(totalH * scale));

    const thumbW = Math.max(...displays.map((d) => d.size.width));
    const thumbH = Math.max(...displays.map((d) => d.size.height));

    const sources = await desktopCapturer.getSources({
        types: ['screen'],
        thumbnailSize: { width: thumbW, height: thumbH },
    });

    const canvas = Buffer.alloc(outW * outH * 4, 15);

    for (const display of displays) {
        const source = sources.find((s) => s.display_id === String(display.id))
            || sources[displays.indexOf(display)];
        if (!source?.thumbnail || source.thumbnail.isEmpty()) continue;

        const bmp = source.thumbnail.toBitmap();
        const sz = source.thumbnail.getSize();
        const dx = Math.round((display.bounds.x - minX) * scale);
        const dy = Math.round((display.bounds.y - minY) * scale);
        const dw = Math.round(display.bounds.width * scale);
        const dh = Math.round(display.bounds.height * scale);
        blitBitmap(canvas, outW, outH, bmp, sz.width, sz.height, dx, dy, dw, dh);
    }

    return nativeImage.createFromBitmap(canvas, { width: outW, height: outH }).toJPEG(75);
}

function blitBitmap(dst, dstW, dstH, src, srcW, srcH, dx, dy, targetW, targetH) {
    const scaleX = srcW / Math.max(1, targetW);
    const scaleY = srcH / Math.max(1, targetH);
    for (let y = 0; y < targetH; y += 1) {
        for (let x = 0; x < targetW; x += 1) {
            const tx = dx + x;
            const ty = dy + y;
            if (tx < 0 || ty < 0 || tx >= dstW || ty >= dstH) continue;
            const sx = Math.min(srcW - 1, Math.floor(x * scaleX));
            const sy = Math.min(srcH - 1, Math.floor(y * scaleY));
            const si = (sy * srcW + sx) * 4;
            const di = (ty * dstW + tx) * 4;
            dst[di] = src[si];
            dst[di + 1] = src[si + 1];
            dst[di + 2] = src[si + 2];
            dst[di + 3] = 255;
        }
    }
}

function todayPayload() {
    return {
        active_seconds: dailyStats.active,
        idle_seconds: dailyStats.idle,
        break_seconds: dailyStats.break,
        date: dailyStats.date,
        date_label: dailyStats.date_label,
    };
}

function mergeServerStats(data) {
    if (data.active_seconds !== undefined) {
        localStats.active = Math.max(localStats.active, data.active_seconds);
        lastSessionStats.active_seconds = localStats.active;
    }
    if (data.idle_seconds !== undefined) {
        localStats.idle = Math.max(localStats.idle, data.idle_seconds);
        lastSessionStats.idle_seconds = localStats.idle;
    }
    if (data.break_seconds !== undefined) {
        localStats.break = Math.max(localStats.break, data.break_seconds);
        lastSessionStats.break_seconds = localStats.break;
    }
    if (data.activity_state) {
        activityState = data.activity_state;
    }
    if (data.today) {
        dailyStats.active = Math.max(dailyStats.active, data.today.active_seconds ?? 0);
        dailyStats.idle = Math.max(dailyStats.idle, data.today.idle_seconds ?? 0);
        dailyStats.break = Math.max(dailyStats.break, data.today.break_seconds ?? 0);
        dailyStats.date = data.today.date ?? dailyStats.date;
        dailyStats.date_label = data.today.date_label ?? dailyStats.date_label;
    }
    if (lastSessionMeta) {
        lastSessionMeta = {
            ...lastSessionMeta,
            active_seconds: localStats.active,
            idle_seconds: localStats.idle,
            break_seconds: localStats.break,
            activity_state: activityState,
        };
    }
}

function pushTodayToRenderer() {
    if (!win || win.isDestroyed()) return;
    win.webContents.send('today-update', todayPayload());
}

function pushStatsToUi() {
    if (!win || win.isDestroyed()) return;
    const systemIdle = powerMonitor.getSystemIdleTime();
    win.webContents.send('stats-update', {
        title: lastLive.title,
        process: lastLive.process,
        app: lastLive.process,
        idle_seconds: systemIdle,
        activity_state: activityState,
        session: lastSessionMeta,
        active_seconds: localStats.active,
        idle_seconds_total: localStats.idle,
        break_seconds: localStats.break,
        started_at: lastSessionMeta?.started_at,
        today: {
            active_seconds: dailyStats.active,
            idle_seconds: dailyStats.idle,
            break_seconds: dailyStats.break,
            date: dailyStats.date,
            date_label: dailyStats.date_label,
        },
    });
}

function tickLocalStats() {
    if (!lastSessionMeta) return;

    if (lastSessionMeta.status === 'paused' || activityState === 'break') {
        localStats.break += 1;
        dailyStats.break += 1;
        pushStatsToUi();
        return;
    }

    if (lastSessionMeta.status !== 'active') return;

    if (activityState === 'idle') {
        localStats.idle += 1;
        dailyStats.idle += 1;
    } else {
        localStats.active += 1;
        dailyStats.active += 1;
    }
    pushStatsToUi();
}

function closeIdlePrompt() {
    idlePromptOpen = false;
    idlePromptPhase = null;
    if (idlePromptAutoTimeout) {
        clearTimeout(idlePromptAutoTimeout);
        idlePromptAutoTimeout = null;
    }
    if (idlePromptStatsTimer) {
        clearInterval(idlePromptStatsTimer);
        idlePromptStatsTimer = null;
    }
    if (idlePromptWin && !idlePromptWin.isDestroyed()) {
        idlePromptWin.close();
    }
    idlePromptWin = null;
}

function idlePromptStatsPayload() {
    const promptIdle = Math.max(0, localStats.idle - idleAtPromptOpen);
    return {
        session_idle_seconds: localStats.idle,
        prompt_idle_seconds: promptIdle,
        system_idle_seconds: powerMonitor.getSystemIdleTime(),
    };
}

function pushIdlePromptStats() {
    if (!idlePromptWin || idlePromptWin.isDestroyed()) return;
    if (idlePromptPhase === 'confirm') {
        const systemIdle = powerMonitor.getSystemIdleTime();
        const promptAt = config.idle_prompt_seconds || 30;
        if (systemIdle < promptAt) {
            handleIdleResponse('yes');
            return;
        }
    }
    idlePromptWin.webContents.send('idle-prompt-stats', idlePromptStatsPayload());
}

function startIdlePromptStatsPush() {
    if (idlePromptStatsTimer) clearInterval(idlePromptStatsTimer);
    pushIdlePromptStats();
    idlePromptStatsTimer = setInterval(pushIdlePromptStats, 1000);
}

function revertIdleSincePrompt() {
    const over = Math.max(0, localStats.idle - idleAtPromptOpen);
    if (over > 0) {
        localStats.idle -= over;
        dailyStats.idle = Math.max(0, dailyStats.idle - over);
    }
}

async function handleIdleResponse(choice) {
    if (choice === 'timeout') {
        handleIdleTimeout();
        return;
    }

    closeIdlePrompt();

    if (choice === 'yes') {
        revertIdleSincePrompt();
        activityState = 'working';
        if (lastSessionMeta) {
            lastSessionMeta.activity_state = 'working';
        }
        sendHeartbeat(true).catch(() => {});
        pushStatsToUi();
        return;
    }

    if (choice === 'no') {
        activityState = 'break';
        try {
            await enqueueApi(() => jsonApi(15000).post('/pause'));
            if (lastSessionMeta) {
                lastSessionMeta = { ...lastSessionMeta, status: 'paused', activity_state: 'break' };
            }
            stopMonitoring();
            if (!uiTickTimer) uiTickTimer = setInterval(tickLocalStats, 1000);
            updateTray();
            pushStatsToUi();
            if (win && !win.isDestroyed()) {
                win.webContents.send('session-paused');
            }
        } catch (e) {
            console.error('pause failed', e.message);
        }
        return;
    }

    if (choice === 'ok') {
        activityState = 'idle';
        if (lastSessionMeta) {
            lastSessionMeta.activity_state = 'idle';
        }
        const systemIdle = powerMonitor.getSystemIdleTime();
        const promptAt = config.idle_prompt_seconds || 30;
        if (systemIdle < promptAt) {
            activityState = 'working';
            if (lastSessionMeta) lastSessionMeta.activity_state = 'working';
        }
        sendHeartbeat(true).catch(() => {});
        pushStatsToUi();
        return;
    }
}

function handleIdleTimeout() {
    if (idlePromptAutoTimeout) {
        clearTimeout(idlePromptAutoTimeout);
        idlePromptAutoTimeout = null;
    }
    idlePromptPhase = 'idle';
    activityState = 'idle';
    if (lastSessionMeta) {
        lastSessionMeta.activity_state = 'idle';
    }
    sendHeartbeat(true).catch(() => {});
    pushStatsToUi();
    if (idlePromptWin && !idlePromptWin.isDestroyed()) {
        idlePromptWin.setTitle('Idle time');
        idlePromptWin.setContentSize(332, 228, false);
        idlePromptWin.webContents.send('idle-prompt-phase', { phase: 'idle' });
    }
}

function showIdlePrompt() {
    if (idlePromptOpen || !lastSessionMeta || lastSessionMeta.status !== 'active') return;
    if (activityState !== 'working') return;

    idlePromptOpen = true;
    idlePromptPhase = 'confirm';
    idleAtPromptOpen = localStats.idle;
    activityState = 'idle';
    if (lastSessionMeta) {
        lastSessionMeta.activity_state = 'idle';
    }
    pushStatsToUi();

    const timeoutSec = config.idle_prompt_timeout_seconds || 60;

    idlePromptWin = new BrowserWindow({
        width: 332,
        height: 288,
        useContentSize: true,
        resizable: false,
        minimizable: false,
        maximizable: false,
        alwaysOnTop: true,
        skipTaskbar: false,
        frame: true,
        title: '5Core Attendance',
        autoHideMenuBar: true,
        focusable: true,
        webPreferences: {
            preload: path.join(__dirname, 'idle-prompt-preload.js'),
            contextIsolation: true,
            nodeIntegration: false,
            sandbox: false,
        },
    });

    idlePromptWin.loadFile(path.join(__dirname, 'renderer', 'idle-prompt.html'), {
        query: { timeout: String(timeoutSec) },
    });

    idlePromptWin.once('ready-to-show', () => {
        if (!idlePromptWin || idlePromptWin.isDestroyed()) return;
        idlePromptWin.show();
        idlePromptWin.focus();
        if (process.platform === 'win32') {
            idlePromptWin.setAlwaysOnTop(true, 'screen-saver');
            idlePromptWin.moveTop();
        }
        startIdlePromptStatsPush();
    });
    idlePromptWin.on('closed', () => {
        idlePromptWin = null;
        idlePromptOpen = false;
        if (idlePromptStatsTimer) {
            clearInterval(idlePromptStatsTimer);
            idlePromptStatsTimer = null;
        }
        if (idlePromptAutoTimeout) {
            clearTimeout(idlePromptAutoTimeout);
            idlePromptAutoTimeout = null;
        }
    });

    idlePromptAutoTimeout = setTimeout(() => {
        if (idlePromptOpen) handleIdleTimeout();
    }, timeoutSec * 1000);
}

function checkIdlePrompt() {
    if (!lastSessionMeta || lastSessionMeta.status !== 'active' || idlePromptOpen) return;

    const systemIdle = powerMonitor.getSystemIdleTime();
    const promptAt = config.idle_prompt_seconds || 30;

    // Do not pop the prompt until the user has actually been idle long enough.
    if (systemIdle < promptAt) {
        if (activityState === 'idle' && !idlePromptOpen) {
            activityState = 'working';
            if (lastSessionMeta) lastSessionMeta.activity_state = 'working';
            pushStatsToUi();
        }
        return;
    }

    if (activityState === 'working') {
        showIdlePrompt();
    }
}

function stopMonitoring() {
    if (heartbeatTimer) { clearInterval(heartbeatTimer); heartbeatTimer = null; }
    if (screenshotTimer) { clearInterval(screenshotTimer); screenshotTimer = null; }
    if (windowPollTimer) { clearInterval(windowPollTimer); windowPollTimer = null; }
    if (idleCheckTimer) { clearInterval(idleCheckTimer); idleCheckTimer = null; }
    closeIdlePrompt();
}

function stopTracking() {
    stopMonitoring();
    if (uiTickTimer) { clearInterval(uiTickTimer); uiTickTimer = null; }
    updateTrayTooltip('Not tracking');
}

async function sendHeartbeat(force = false) {
    if (!store.get('token')) return;
    if (!lastSessionMeta || lastSessionMeta.status !== 'active') return;

    const now = Date.now();
    const elapsed = Math.max(1, Math.min(120, Math.round((now - lastHeartbeatSentAt) / 1000)));
    if (!force && elapsed < 5) return;
    lastHeartbeatSentAt = now;

    return enqueueApi(async () => {
        try {
            await pollActiveWindow();
            const systemIdle = powerMonitor.getSystemIdleTime();
            const isWorking = activityState === 'working' && systemIdle < (config.idle_prompt_seconds || 30);

            const { data } = await jsonApi(20000).post('/heartbeat', {
                is_active: isWorking,
                activity_state: activityState,
                idle_seconds: systemIdle,
                elapsed_seconds: elapsed,
                window_title: lastLive.title,
                app_name: lastLive.process,
                process_name: lastLive.process,
                agent_version: AGENT_VERSION,
            });

            mergeServerStats(data);
            pushStatsToUi();
        } catch (e) {
            console.error('heartbeat failed', e.message);
        }
    });
}

async function sendScreenshot() {
    if (!store.get('token') || !config.screenshots_enabled) return;
    if (!lastSessionMeta || lastSessionMeta.status !== 'active') return;

    return enqueueApi(async () => {
        try {
            await pollActiveWindow();
            const buf = await captureScreenshot();
            if (!buf) return;
            const idle = powerMonitor.getSystemIdleTime();
            const form = new FormData();
            form.append('screenshot', buf, { filename: 'screen.jpg', contentType: 'image/jpeg' });
            form.append('window_title', lastLive.title);
            form.append('app_name', lastLive.process);
            form.append('idle_seconds', String(idle));
            form.append('activity_state', activityState);
            await uploadApi(120000).post('/screenshot', form, { headers: form.getHeaders() });
        } catch (e) {
            console.error('screenshot failed', e.message);
        }
    });
}

async function refreshConfig() {
    try {
        const { data } = await enqueueApi(() => jsonApi(10000).get('/config'));
        if (data.config) config = { ...config, ...data.config };
    } catch (_) {}
}

function resetLocalStats(session, today) {
    lastSessionMeta = session;
    if (session?.started_at) {
        sessionStartedAtMs = new Date(session.started_at).getTime();
    }
    localStats.active = session?.active_seconds || 0;
    localStats.idle = session?.idle_seconds || 0;
    localStats.break = session?.break_seconds || 0;
    if (today != null) {
        dailyStats = {
            active: today.active_seconds ?? 0,
            idle: today.idle_seconds ?? 0,
            break: today.break_seconds ?? 0,
            date: today.date ?? '',
            date_label: today.date_label ?? '',
        };
    }
    if (!session) {
        activityState = 'off';
    } else {
        activityState = session.activity_state
            || (session.status === 'paused' ? 'break' : 'working');
    }
    lastHeartbeatSentAt = Date.now();
}

function startTracking() {
    if (trackingStarting || heartbeatTimer) return;
    trackingStarting = true;
    stopTracking();
    trackingStarting = false;

    refreshConfig().catch(() => {});

    const hb = (config.heartbeat_interval_seconds || 15) * 1000;
    const ss = (config.screenshot_interval_seconds || 30) * 1000;

    pollActiveWindow().catch(() => {});
    windowPollTimer = setInterval(() => { pollActiveWindow().catch(() => {}); }, 3000);
    heartbeatTimer = setInterval(() => { sendHeartbeat().catch(() => {}); }, hb);
    screenshotTimer = setInterval(() => { sendScreenshot().catch(() => {}); }, ss);
    idleCheckTimer = setInterval(checkIdlePrompt, 2000);
    uiTickTimer = setInterval(tickLocalStats, 1000);

    setTimeout(() => { sendHeartbeat(true).catch(() => {}); }, 1500);
    setTimeout(() => { sendScreenshot().catch(() => {}); }, 5000);

    updateTrayTooltip('Tracking active');
}

async function fetchSessionState() {
    if (!store.get('token')) {
        return { loggedIn: false };
    }
    try {
        const { data } = await enqueueApi(() => jsonApi(15000).get('/status'));
        if (!data || typeof data !== 'object') {
            throw new Error('Server returned an invalid response. Check the server URL.');
        }
        const session = data.session ?? null;
        if (data.today != null) {
            resetLocalStats(session, data.today);
        } else {
            resetLocalStats(session, null);
        }
        if (session?.status === 'active') {
            startTracking();
        } else {
            stopTracking();
        }
        if (data.config) config = { ...config, ...data.config };
        const today = todayPayload();
        pushTodayToRenderer();
        pushStatsToUi();
        return {
            loggedIn: true,
            user: store.get('user'),
            session,
            today,
            live: { ...lastLive, today },
            config: data.config || config,
        };
    } catch (err) {
        const status = err.response?.status;
        const message = err.response?.data?.message
            || (status === 401 ? 'Session expired. Please sign in again.' : null)
            || (err.code === 'ECONNREFUSED' ? 'Cannot reach server. Is Laravel running?' : null)
            || err.message
            || 'Could not load session';
        if (status === 401) {
            store.delete('token');
            store.delete('user');
            return { loggedIn: false, error: message };
        }
        const today = todayPayload();
        return {
            loggedIn: !!store.get('token'),
            user: store.get('user'),
            session: lastSessionMeta,
            today,
            live: { ...lastLive, today },
            error: message,
        };
    }
}

function updateTrayTooltip(text) {
    if (tray) {
        const user = store.get('user');
        const name = user?.name || '5Core Attendance';
        tray.setToolTip(`${name} — ${text}`);
    }
}

function buildTrayMenu() {
    return Menu.buildFromTemplate([
        { label: 'Open Dashboard', click: () => showWindow() },
        { type: 'separator' },
        {
            label: 'Clock In',
            click: async () => {
                const { data } = await api().post('/clock-in', { work_location: 'wfh' });
                resetLocalStats(data.session, data.today);
                startTracking();
                updateTray();
                pushStatsToUi();
            },
        },
        {
            label: 'Clock Out',
            click: async () => {
                await api().post('/clock-out');
                lastSessionMeta = null;
                stopTracking();
                updateTray();
            },
        },
        { label: 'Take a Break', click: async () => { await api().post('/pause'); activityState = 'break'; stopTracking(); updateTray(); } },
        { label: 'Resume Work', click: async () => { await api().post('/resume'); activityState = 'working'; startTracking(); updateTray(); } },
        { type: 'separator' },
        { label: 'Open Web Portal', click: () => shell.openExternal(`${getApiBase()}/attendance`) },
        { type: 'separator' },
        {
            label: 'Sign Out',
            click: async () => {
                store.delete('token');
                store.delete('user');
                stopTracking();
                showWindow();
                updateTray();
            },
        },
        { label: 'Quit', click: () => { app.isQuitting = true; app.quit(); } },
    ]);
}

function updateTray() {
    if (tray) tray.setContextMenu(buildTrayMenu());
}

function showWindow() {
    if (!win) createWindow();
    const open = async () => {
        if (store.get('token')) {
            const state = await fetchSessionState().catch(() => null);
            if (state && !state.loggedIn) {
                if (win && !win.isDestroyed()) {
                    win.show();
                    win.focus();
                    win.webContents.send('app-show');
                }
                return;
            }
            pushTodayToRenderer();
            pushStatsToUi();
        }
        if (win && !win.isDestroyed()) {
            win.show();
            win.focus();
            win.webContents.send('app-show');
        }
    };
    if (win.webContents.isLoading()) {
        win.webContents.once('did-finish-load', () => { open().catch(() => {}); });
    } else {
        open().catch(() => {});
    }
}

function enableAutoLaunch() {
    const settings = {
        openAtLogin: true,
        openAsHidden: true,
        name: '5Core Attendance',
    };
    if (!app.isPackaged) {
        settings.path = process.execPath;
        settings.args = [path.resolve(__dirname)];
    }
    app.setLoginItemSettings(settings);
    store.set('autoLaunch', true);
}

function getTrayIcon() {
    const candidates = [
        path.join(__dirname, 'assets', 'icon-16.png'),
        path.join(__dirname, 'assets', 'tray-icon.png'),
        path.join(__dirname, 'assets', 'icon-32.png'),
        path.join(__dirname, 'assets', 'icon.png'),
        path.join(__dirname, 'assets', 'icon.ico'),
    ];
    for (const iconPath of candidates) {
        try {
            let image = nativeImage.createFromPath(iconPath);
            if (image.isEmpty()) continue;
            if (process.platform === 'win32') {
                const size = image.getSize();
                if (size.width !== 16 || size.height !== 16) {
                    image = image.resize({ width: 16, height: 16, quality: 'best' });
                }
            }
            return image;
        } catch {
            // try next
        }
    }
    // 16x16 blue/purple gradient circle (valid PNG)
    return nativeImage.createFromDataURL(
        'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAA0klEQVR42mNgGAWjYBSMglEw'
        + 'CkbBKBgFgwHUgP///2f4//8/AwMDA8P///8ZGBgYGP7//8/w58+f/3///mX4+/cvw9+//x'
        + 'j+/fvH8O/fP4Z///4x/Pv3j+Hvnz8Mf/78Yfjz5w/Dnz9/GP78+cPw58+f/3///mX4+/cv'
        + 'w9+//xn+/fvH8O/fP4Z///4x/Pv3j+Hvnz8Mf/78Yfjz5w/Dnz9/GP78+cPw58+f/3//'
        + '/mX4+/cvw9+//xn+/fvH8O/fP4Z///4x/Pv3j+Hvnz8Mf/78Yfjz5w/Dnz9/GAUAAAD/'
        + 'SZIL7gAAAABJRU5ErkJggg=='
    );
}

function createWindow() {
    win = new BrowserWindow({
        width: 560,
        height: 520,
        minWidth: 520,
        minHeight: 520,
        maxHeight: 520,
        show: false,
        resizable: false,
        frame: true,
        autoHideMenuBar: true,
        title: '5Core Attendance',
        icon: path.join(__dirname, 'assets', 'icon.png'),
        webPreferences: {
            preload: path.join(__dirname, 'preload.js'),
            contextIsolation: true,
            nodeIntegration: false,
        },
    });
    win.loadFile(path.join(__dirname, 'renderer', 'index.html'));
    win.on('close', (e) => {
        if (!app.isQuitting) {
            e.preventDefault();
            win.hide();
        }
    });
}

function createTray() {
    const icon = getTrayIcon();
    tray = new Tray(icon);
    if (process.platform === 'win32') {
        tray.setImage(icon);
    }
    tray.setToolTip('5Core Attendance');
    tray.on('double-click', showWindow);
    tray.on('click', () => showWindow());
    updateTray();
}

ipcMain.on('idle-prompt-response', (_e, choice) => {
    if (choice === 'ok') {
        handleIdleResponse('ok');
        return;
    }
    handleIdleResponse(choice === 'yes' ? 'yes' : 'no');
});

ipcMain.on('idle-prompt-resize', (_e, { width, height }) => {
    if (!idlePromptWin || idlePromptWin.isDestroyed()) return;
    if (width > 0 && height > 0) {
        idlePromptWin.setContentSize(Math.round(width), Math.round(height), false);
    }
});

ipcMain.handle('idle-prompt-config', () => ({
    timeout_seconds: config.idle_prompt_timeout_seconds || 60,
    ...idlePromptStatsPayload(),
}));

// --- IPC ---

ipcMain.handle('getSetup', () => {
    const apiUrl = getApiBase();
    const configured = !!(store.get('apiUrl') || process.env.FIVECORE_API_URL || store.get('token'));
    return { apiUrl, configured };
});

ipcMain.handle('saveSetup', async (_e, { apiUrl }) => {
    const url = String(apiUrl || '').trim().replace(/\/+$/, '').replace(/\/api$/i, '').replace(/\/attendance$/i, '');
    if (!url || !/^https?:\/\//i.test(url)) {
        return { ok: false, message: 'Enter a valid URL starting with http:// or https://' };
    }
    try {
        const { data } = await axios.get(`${url}/attendance/desktop-api/ping`, {
            timeout: 10000,
            headers: { Accept: 'application/json' },
            validateStatus: (s) => s < 500,
        });
        if (!data?.ok) {
            return { ok: false, message: 'Server responded but attendance API not found. Deploy latest code.' };
        }
    } catch (err) {
        const status = err.response?.status;
        const serverMsg = err.response?.data?.message;
        if (status === 404) {
            return { ok: false, message: 'Attendance API not found (404). Use base URL only, e.g. http://127.0.0.1:8000' };
        }
        if (status === 401) {
            return { ok: false, message: serverMsg || 'Server returned Unauthenticated (401). Restart Laravel after updating routes.' };
        }
        return { ok: false, message: err.code === 'ECONNREFUSED' ? 'Cannot reach server. Is Laravel running?' : (serverMsg || err.message || 'Connection failed') };
    }
    store.set('apiUrl', url);
    enableAutoLaunch();
    return { ok: true };
});

ipcMain.handle('login', async (_e, { email, password }) => {
    try {
        const { data } = await axios.post(`${getAgentApiPath()}/login`, {
            email, password,
            machine_id: MACHINE_ID,
            device_name: os.hostname(),
            os_name: process.platform,
            os_version: os.release(),
            agent_version: AGENT_VERSION,
        }, { timeout: 20000 });
        store.set('token', data.token);
        store.set('user', data.user);
        store.set('device', data.device);
        if (data.config) config = { ...config, ...data.config };
        enableAutoLaunch();
        updateTray();
        updateTrayTooltip('Signed in — clock in to start');
        showWindow();
        fetchSessionState().catch(() => {});
        return { ok: true, user: data.user };
    } catch (err) {
        const msg = err.response?.data?.message
            || err.response?.data?.errors?.email?.[0]
            || (err.response?.status === 404 ? 'Login API not found (404). Re-save server URL on setup screen.' : null)
            || (err.code === 'ECONNREFUSED' ? 'Cannot reach server. Check the URL.' : err.message);
        return { ok: false, message: msg };
    }
});

ipcMain.handle('signOut', async () => {
    store.delete('token');
    store.delete('user');
    stopTracking();
    updateTray();
    return { ok: true };
});

ipcMain.handle('getState', async () => fetchSessionState());

ipcMain.handle('clockIn', async (_e, { work_location } = {}) => {
    try {
        const { data } = await enqueueApi(() =>
            jsonApi(20000).post('/clock-in', { work_location: work_location || 'wfh' })
        );
        resetLocalStats(data.session, data.today);
        activityState = 'working';
        startTracking();
        updateTray();
        pushTodayToRenderer();
        pushStatsToUi();
        return { ok: true, session: data.session };
    } catch (err) {
        const msg = err.response?.data?.message || err.message || 'Clock in failed';
        return { ok: false, message: msg };
    }
});

ipcMain.handle('clockOut', async () => {
    try {
        const { data } = await enqueueApi(() => jsonApi(20000).post('/clock-out'));
        lastSessionMeta = null;
        localStats = { active: 0, idle: 0, break: 0 };
        if (data?.today) {
            dailyStats = {
                active: data.today.active_seconds ?? 0,
                idle: data.today.idle_seconds ?? 0,
                break: data.today.break_seconds ?? 0,
                date: data.today.date ?? '',
                date_label: data.today.date_label ?? '',
            };
        }
        stopTracking();
        updateTray();
        pushTodayToRenderer();
        pushStatsToUi();
        return { ok: true, today: todayPayload() };
    } catch (err) {
        return { ok: false, message: err.message || 'Clock out failed' };
    }
});

ipcMain.handle('pause', async () => {
    try {
        await enqueueApi(() => jsonApi(15000).post('/pause'));
        activityState = 'break';
        if (lastSessionMeta) {
            lastSessionMeta = { ...lastSessionMeta, status: 'paused', activity_state: 'break' };
        }
        stopMonitoring();
        if (!uiTickTimer) uiTickTimer = setInterval(tickLocalStats, 1000);
        updateTray();
        pushStatsToUi();
        return { ok: true, session: lastSessionMeta };
    } catch (err) {
        return { ok: false, message: err.message };
    }
});

ipcMain.handle('resume', async () => {
    try {
        const { data } = await enqueueApi(() => jsonApi(15000).post('/resume'));
        activityState = 'working';
        if (lastSessionMeta) {
            lastSessionMeta = { ...lastSessionMeta, status: 'active', activity_state: 'working' };
        }
        if (data.session) {
            resetLocalStats(data.session, data.today);
        }
        startTracking();
        updateTray();
        pushStatsToUi();
        return { ok: true, session: lastSessionMeta };
    } catch (err) {
        return { ok: false, message: err.message };
    }
});

ipcMain.handle('minimizeToTray', () => {
    if (win) win.hide();
});

ipcMain.handle('openPortal', () => {
    shell.openExternal(`${getApiBase()}/attendance`);
});

app.on('second-instance', () => showWindow());

app.whenReady().then(async () => {
    enableAutoLaunch();
    createTray();
    createWindow();

    await new Promise((resolve) => {
        if (!win || win.isDestroyed()) {
            resolve();
            return;
        }
        if (win.webContents.isLoading()) {
            win.webContents.once('did-finish-load', resolve);
        } else {
            resolve();
        }
    });

    if (store.get('token')) {
        await fetchSessionState().catch(() => {});
        pushTodayToRenderer();
        pushStatsToUi();
    }
    if (win) win.hide();
});

app.on('before-quit', () => { app.isQuitting = true; stopTracking(); });
app.on('window-all-closed', (e) => e.preventDefault());
