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
const AGENT_VERSION = '1.0.0';

const gotTheLock = app.requestSingleInstanceLock();
if (!gotTheLock) {
    app.quit();
}

const MACHINE_ID = store.get('machineId') || `${os.hostname()}-${os.userInfo().username}`.slice(0, 120);
store.set('machineId', MACHINE_ID);

let tray = null;
let win = null;
let heartbeatTimer = null;
let screenshotTimer = null;
let statsTimer = null;
let lastLive = { title: '', process: '', app: '' };
let lastSessionStats = { active_seconds: 0, idle_seconds: 0 };
let lastSessionMeta = null; // { id, status, started_at, ... }
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
    heartbeat_interval_seconds: 60,
    screenshot_interval_seconds: 300,
    idle_threshold_seconds: 120,
    screenshots_enabled: true,
};

function getApiBase() {
    const fromEnv = process.env.FIVECORE_API_URL;
    const fromStore = store.get('apiUrl');
    let base = (fromEnv || fromStore || 'http://127.0.0.1:8000').replace(/\/+$/, '');
    // Strip accidental suffixes employees might paste
    base = base.replace(/\/api$/i, '').replace(/\/attendance$/i, '');
    return base;
}

function getAgentApiPath() {
    return `${getApiBase()}/attendance/desktop-api`;
}

async function getActiveWindow() {
    if (process.platform !== 'win32') {
        return { title: '', process: '' };
    }
    try {
        const script = path.join(__dirname, 'scripts', 'get-active-window.ps1');
        const { stdout } = await execFileAsync('powershell.exe', ['-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', script], { timeout: 8000 });
        const parts = stdout.trim().split('|||');
        return { title: parts[0] || '', process: parts[1] || '' };
    } catch {
        return { title: '', process: '' };
    }
}

async function captureScreenshot() {
    const display = screen.getPrimaryDisplay();
    const { width, height } = display.size;
    const sources = await desktopCapturer.getSources({
        types: ['screen'],
        thumbnailSize: { width: Math.min(width, 1920), height: Math.min(height, 1080) },
    });
    const src = sources[0];
    if (!src) return null;
    return src.thumbnail.toJPEG(75);
}

function pushStatsToUi() {
    if (!win || win.isDestroyed()) return;
    const idle = powerMonitor.getSystemIdleTime();
    win.webContents.send('stats-update', {
        title: lastLive.title,
        process: lastLive.process,
        app: lastLive.process,
        idle_seconds: idle,
        session: lastSessionMeta,
        active_seconds: lastSessionStats.active_seconds,
        idle_seconds_total: lastSessionStats.idle_seconds,
    });
}

function stopTracking() {
    if (heartbeatTimer) { clearInterval(heartbeatTimer); heartbeatTimer = null; }
    if (screenshotTimer) { clearInterval(screenshotTimer); screenshotTimer = null; }
    if (statsTimer) { clearInterval(statsTimer); statsTimer = null; }
    updateTrayTooltip('Not tracking');
}

async function sendHeartbeat() {
    if (!store.get('token')) return;
    return enqueueApi(async () => {
        try {
            const idle = powerMonitor.getSystemIdleTime();
            const active = await getActiveWindow();
            lastLive = { title: active.title, process: active.process };
            const threshold = config.idle_threshold_seconds || 120;
            const { data } = await jsonApi(20000).post('/heartbeat', {
                is_active: idle < threshold,
                idle_seconds: idle,
                window_title: active.title,
                app_name: active.process,
                process_name: active.process,
                agent_version: AGENT_VERSION,
            });
            if (data.active_seconds !== undefined) {
                lastSessionStats.active_seconds = data.active_seconds;
                lastSessionStats.idle_seconds = data.idle_seconds || 0;
                if (lastSessionMeta) {
                    lastSessionMeta.active_seconds = data.active_seconds;
                    lastSessionMeta.idle_seconds = data.idle_seconds || 0;
                }
            }
            pushStatsToUi();
        } catch (e) {
            console.error('heartbeat failed', e.message);
        }
    });
}

async function sendScreenshot() {
    if (!store.get('token') || !config.screenshots_enabled) return;
    return enqueueApi(async () => {
        try {
            const buf = await captureScreenshot();
            if (!buf) return;
            const idle = powerMonitor.getSystemIdleTime();
            const active = await getActiveWindow();
            const form = new FormData();
            form.append('screenshot', buf, { filename: 'screen.jpg', contentType: 'image/jpeg' });
            form.append('window_title', active.title);
            form.append('app_name', active.process);
            form.append('idle_seconds', String(idle));
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

function startTracking() {
    if (trackingStarting || heartbeatTimer) return;
    trackingStarting = true;
    stopTracking();
    trackingStarting = false;

    refreshConfig().catch(() => {});

    const hb = (config.heartbeat_interval_seconds || 60) * 1000;
    const ss = (config.screenshot_interval_seconds || 300) * 1000;
    heartbeatTimer = setInterval(() => { sendHeartbeat().catch(() => {}); }, hb);
    screenshotTimer = setInterval(() => { sendScreenshot().catch(() => {}); }, ss);
    statsTimer = setInterval(pushStatsToUi, 3000);

    // Stagger first uploads so clock-in response is not blocked behind them
    setTimeout(() => { sendHeartbeat().catch(() => {}); }, 2000);
    setTimeout(() => { sendScreenshot().catch(() => {}); }, 20000);

    updateTrayTooltip('Tracking active');
}

async function fetchSessionState() {
    if (!store.get('token')) {
        return { loggedIn: false };
    }
    try {
        const { data } = await enqueueApi(() => jsonApi(15000).get('/status'));
        const session = data.session;
        lastSessionMeta = session;
        if (session) {
            lastSessionStats.active_seconds = session.active_seconds || 0;
            lastSessionStats.idle_seconds = session.idle_seconds || 0;
        }
        if (session?.status === 'active') {
            startTracking();
        } else {
            stopTracking();
        }
        return {
            loggedIn: true,
            user: store.get('user'),
            session,
            live: lastLive,
            config: data.config || config,
        };
    } catch {
        return { loggedIn: true, user: store.get('user'), session: lastSessionMeta, live: lastLive };
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
                await api().post('/clock-in', { work_location: 'wfh' });
                await startTracking();
                updateTray();
                if (win) win.webContents.send('stats-update', lastLive);
            },
        },
        {
            label: 'Clock Out',
            click: async () => {
                await api().post('/clock-out');
                stopTracking();
                updateTray();
            },
        },
        { label: 'Pause', click: async () => { await api().post('/pause'); stopTracking(); updateTray(); } },
        { label: 'Resume', click: async () => { await api().post('/resume'); await startTracking(); updateTray(); } },
        { type: 'separator' },
        {
            label: 'Open Web Portal',
            click: () => shell.openExternal(`${getApiBase()}/attendance`),
        },
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
    win.show();
    win.focus();
}

function createWindow() {
    win = new BrowserWindow({
        width: 400,
        height: 680,
        minWidth: 380,
        minHeight: 600,
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
    const iconPath = path.join(__dirname, 'assets', 'icon.png');
    let icon;
    try {
        icon = nativeImage.createFromPath(iconPath);
        if (icon.isEmpty()) throw new Error('empty');
    } catch {
        icon = nativeImage.createFromDataURL(
            'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAAMUlEQVQ4T2NkYGD4z0ABYBw1gGE0DBgNwxhGwwCGBQYGhv8M/xkYGP4zDAEACaMBAx2QJ1YAAAAASUVORK5CYII='
        );
    }
    tray = new Tray(icon.resize({ width: 16, height: 16 }));
    tray.setToolTip('5Core Attendance');
    tray.on('double-click', showWindow);
    tray.on('click', () => showWindow());
    updateTray();
}

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
        const { data } = await axios.get(`${url}/attendance/desktop-api/ping`, { timeout: 10000 });
        if (!data?.ok) {
            return { ok: false, message: 'Server responded but attendance API not found. Deploy latest code.' };
        }
    } catch (err) {
        const status = err.response?.status;
        if (status === 404) {
            return { ok: false, message: 'Attendance API not found (404). Use base URL only, e.g. http://127.0.0.1:8000' };
        }
        return { ok: false, message: err.code === 'ECONNREFUSED' ? 'Cannot reach server. Is Laravel running?' : (err.message || 'Connection failed') };
    }
    store.set('apiUrl', url);
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
        app.setLoginItemSettings({ openAtLogin: true, openAsHidden: true });
        updateTray();
        updateTrayTooltip('Signed in — clock in to start');
        return { ok: true };
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
        lastSessionMeta = data.session || lastSessionMeta;
        startTracking();
        updateTray();
        return { ok: true, session: data.session };
    } catch (err) {
        const msg = err.response?.data?.message || err.message || 'Clock in failed';
        return { ok: false, message: msg };
    }
});

ipcMain.handle('clockOut', async () => {
    try {
        await enqueueApi(() => jsonApi(20000).post('/clock-out'));
        lastSessionMeta = null;
        stopTracking();
        updateTray();
        return { ok: true };
    } catch (err) {
        return { ok: false, message: err.message || 'Clock out failed' };
    }
});

ipcMain.handle('pause', async () => {
    try {
        await enqueueApi(() => jsonApi(15000).post('/pause'));
        if (lastSessionMeta) lastSessionMeta = { ...lastSessionMeta, status: 'paused' };
        stopTracking();
        updateTray();
        pushStatsToUi();
        return { ok: true, session: lastSessionMeta };
    } catch (err) {
        return { ok: false, message: err.message };
    }
});

ipcMain.handle('resume', async () => {
    try {
        await enqueueApi(() => jsonApi(15000).post('/resume'));
        if (lastSessionMeta) lastSessionMeta = { ...lastSessionMeta, status: 'active' };
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
    createTray();
    createWindow();

    if (store.get('token')) {
        try {
            await fetchSessionState();
            if (win) win.hide();
        } catch {
            store.delete('token');
            showWindow();
        }
    } else {
        showWindow();
    }
});

app.on('before-quit', () => { app.isQuitting = true; stopTracking(); });
app.on('window-all-closed', (e) => e.preventDefault());
