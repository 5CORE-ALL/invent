const {
    app, BrowserWindow, Tray, Menu, nativeImage, powerMonitor,
    desktopCapturer, screen, ipcMain, shell,
} = require('electron');
const path = require('path');
const os = require('os');
const http = require('http');
const crypto = require('crypto');
const { execFile } = require('child_process');
const { promisify } = require('util');
const axios = require('axios');
const FormData = require('form-data');
const Store = require('electron-store');

const execFileAsync = promisify(execFile);
const store = new Store();
const AGENT_VERSION = '1.3.3';
const UPDATE_SNOOZE_MS = 4 * 60 * 60 * 1000;
let updateCheckTimer = null;
let lastUpdatePayload = null;

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

// Inline script so packaged builds work (PowerShell cannot -File scripts inside app.asar).
const ACTIVE_WINDOW_PS = `
Add-Type @"
using System;
using System.Runtime.InteropServices;
using System.Text;
public class WinForeground {
    [DllImport("user32.dll")] public static extern IntPtr GetForegroundWindow();
    [DllImport("user32.dll", CharSet=CharSet.Unicode)] public static extern int GetWindowText(IntPtr hWnd, StringBuilder text, int count);
    [DllImport("user32.dll")] public static extern uint GetWindowThreadProcessId(IntPtr hWnd, out int processId);
}
"@
$h = [WinForeground]::GetForegroundWindow()
$sb = New-Object System.Text.StringBuilder 512
[void][WinForeground]::GetWindowText($h, $sb, 512)
$processId = 0
[void][WinForeground]::GetWindowThreadProcessId($h, [ref]$processId)
$proc = ''
if ($processId -gt 0) {
    $p = Get-Process -Id $processId -ErrorAction SilentlyContinue
    if ($p) { $proc = $p.ProcessName }
}
Write-Output ($sb.ToString() + '|||' + $proc)
`.trim();

let tray = null;
let win = null;
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
    screenshot_interval_seconds: 120,
    idle_threshold_seconds: 30,
    idle_prompt_seconds: 31536000,
    idle_prompt_timeout_seconds: 60,
    screenshots_enabled: true,
    agent_version: AGENT_VERSION,
    download_page_url: '',
    download_url: '',
    update_message: '',
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
    // Keep useful titles even when the process is a host/shell we ignore.
    if (title) {
        const keptProcess = (process && !isIgnoredProcess(process))
            ? process
            : (lastKnownWindow.process || '');
        lastKnownWindow = { title, process: keptProcess || process };
        lastLive = {
            title,
            process: keptProcess || process || title,
            app: keptProcess || process || title,
        };
        return true;
    }
    return false;
}

async function getActiveWindow() {
    if (process.platform !== 'win32') {
        return { title: '', process: '' };
    }
    try {
        const encoded = Buffer.from(ACTIVE_WINDOW_PS, 'utf16le').toString('base64');
        const { stdout } = await execFileAsync(
            'powershell.exe',
            ['-NoProfile', '-NonInteractive', '-WindowStyle', 'Hidden', '-ExecutionPolicy', 'Bypass', '-EncodedCommand', encoded],
            { timeout: 6000, windowsHide: true }
        );
        const parts = String(stdout || '').trim().split('|||');
        return { title: parts[0] || '', process: parts[1] || '' };
    } catch {
        // Dev / unpacked fallback
        try {
            const script = path.join(__dirname, 'scripts', 'get-active-window.ps1');
            const { stdout } = await execFileAsync(
                'powershell.exe',
                ['-NoProfile', '-NonInteractive', '-WindowStyle', 'Hidden', '-ExecutionPolicy', 'Bypass', '-File', script],
                { timeout: 6000, windowsHide: true }
            );
            const parts = String(stdout || '').trim().split('|||');
            return { title: parts[0] || '', process: parts[1] || '' };
        } catch {
            return { title: lastKnownWindow.title || '', process: lastKnownWindow.process || '' };
        }
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
        // Don't let a stale server "working" wipe local idle while the machine is still idle.
        const systemIdle = powerMonitor.getSystemIdleTime();
        const idleAt = config.idle_threshold_seconds || 30;
        if (!(data.activity_state === 'working' && activityState === 'idle' && systemIdle >= idleAt)) {
            activityState = data.activity_state;
        }
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
        idle_threshold_seconds: config.idle_threshold_seconds || 30,
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

    // Re-evaluate idle every second so the timer turns red promptly.
    if (lastSessionMeta.status === 'active' && activityState !== 'break') {
        checkIdleState();
    }

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

function setActivityState(next) {
    if (activityState === next) return;
    activityState = next;
    if (lastSessionMeta) {
        lastSessionMeta.activity_state = next;
    }
    if (next === 'idle') {
        updateTrayTooltip('IDLE');
    } else if (next === 'working') {
        updateTrayTooltip('Tracking active');
    } else if (next === 'break') {
        updateTrayTooltip('On break');
    }
    sendHeartbeat(true).catch(() => {});
    pushStatsToUi();
    updateTray();
}

/**
 * Idle is shown in the main timer UI only — no separate popup window.
 * When system idle exceeds the threshold, mark idle; when activity returns, resume working.
 */
function checkIdleState() {
    if (!lastSessionMeta || lastSessionMeta.status !== 'active') return;
    if (activityState === 'break') return;

    const systemIdle = powerMonitor.getSystemIdleTime();
    // v1.3+: use idle_threshold only. (idle_prompt_seconds is legacy for old installs.)
    const idleAt = config.idle_threshold_seconds || 30;

    if (systemIdle >= idleAt) {
        if (activityState === 'working') {
            setActivityState('idle');
        }
        return;
    }

    if (activityState === 'idle') {
        setActivityState('working');
    }
}

function parseVersionParts(version) {
    return String(version || '0')
        .replace(/^v/i, '')
        .split(/[.+-]/)
        .map((part) => parseInt(part, 10) || 0);
}

/** @returns {number} positive if a > b, negative if a < b, 0 if equal */
function compareVersions(a, b) {
    const left = parseVersionParts(a);
    const right = parseVersionParts(b);
    const len = Math.max(left.length, right.length);
    for (let i = 0; i < len; i += 1) {
        const x = left[i] || 0;
        const y = right[i] || 0;
        if (x > y) return 1;
        if (x < y) return -1;
    }
    return 0;
}

function pushUpdateToUi(payload, { forceShow = false } = {}) {
    if (!win || win.isDestroyed()) return;
    lastUpdatePayload = payload;
    // Always send — renderer shows a small banner even when the modal is snoozed.
    win.webContents.send('update-available', {
        ...(payload || { available: false }),
        force_show: !!forceShow,
        snoozed: !forceShow && !!payload && Date.now() < Number(store.get('updateSnoozeUntil') || 0),
    });
}

function applyAgentUpdateInfo(update, { forceShow = false } = {}) {
    if (!update) {
        pushUpdateToUi(null);
        return null;
    }

    const remoteVersion = update.latest_version || config.agent_version || '';
    if (!remoteVersion || compareVersions(remoteVersion, AGENT_VERSION) <= 0) {
        pushUpdateToUi(null);
        return null;
    }

    if (update.download_page_url) config.download_page_url = update.download_page_url;
    if (update.download_url) config.download_url = update.download_url;
    if (update.message) config.update_message = update.message;
    config.agent_version = remoteVersion;

    const payload = {
        available: true,
        current_version: AGENT_VERSION,
        latest_version: remoteVersion,
        download_page_url: config.download_page_url || `${getApiBase()}/attendance/agent`,
        download_url: config.download_url || `${getApiBase()}/attendance/agent/download`,
        message: config.update_message
            || update.message
            || 'A new version of 5Core Attendance is available. Run the installer to update — no uninstall needed.',
    };

    const snoozeUntil = Number(store.get('updateSnoozeUntil') || 0);
    const snoozed = !forceShow && Date.now() < snoozeUntil;
    if (!snoozed) {
        showWindow();
    }
    pushUpdateToUi(payload, { forceShow: forceShow || !snoozed });
    updateTrayTooltip(`Update available (v${remoteVersion})`);
    return payload;
}

async function checkForUpdate({ forceShow = false } = {}) {
    try {
        if (store.get('token')) {
            await refreshConfig();
            return applyAgentUpdateInfo({
                available: compareVersions(config.agent_version || '', AGENT_VERSION) > 0,
                latest_version: config.agent_version || '',
                download_page_url: config.download_page_url || '',
                download_url: config.download_url || '',
                message: config.update_message || '',
            }, { forceShow });
        }

        if (!getApiBase()) return null;

        const { data } = await axios.get(`${getAgentApiPath()}/ping`, { timeout: 8000 });
        if (data?.version) {
            config.agent_version = data.version;
            config.download_page_url = data.download_page_url || config.download_page_url;
            config.download_url = data.download_url || config.download_url;
            config.update_message = data.update_message || config.update_message;
        }
        return applyAgentUpdateInfo({
            available: compareVersions(data?.version || '', AGENT_VERSION) > 0,
            latest_version: data?.version || '',
            download_page_url: data?.download_page_url || '',
            download_url: data?.download_url || '',
            message: data?.update_message || '',
        }, { forceShow });
    } catch (_) {
        return null;
    }
}

function scheduleUpdateChecks() {
    if (updateCheckTimer) clearInterval(updateCheckTimer);
    updateCheckTimer = setInterval(() => {
        checkForUpdate().catch(() => {});
    }, 6 * 60 * 60 * 1000);
}

function stopMonitoring() {
    if (heartbeatTimer) { clearInterval(heartbeatTimer); heartbeatTimer = null; }
    if (screenshotTimer) { clearInterval(screenshotTimer); screenshotTimer = null; }
    if (windowPollTimer) { clearInterval(windowPollTimer); windowPollTimer = null; }
    if (idleCheckTimer) { clearInterval(idleCheckTimer); idleCheckTimer = null; }
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
            const idleAt = config.idle_threshold_seconds || 30;
            const isWorking = activityState === 'working' && systemIdle < idleAt;

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
            if (data.agent_update) {
                applyAgentUpdateInfo(data.agent_update);
            }
            // Keep local idle in sync when server classifies idle from OS idle time.
            if (data.activity_state === 'idle' && activityState === 'working' && lastSessionMeta?.status === 'active') {
                const systemIdle = powerMonitor.getSystemIdleTime();
                const idleAt = config.idle_threshold_seconds || 30;
                if (systemIdle >= idleAt) {
                    activityState = 'idle';
                    lastSessionMeta.activity_state = 'idle';
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
        return data?.config || null;
    } catch (_) {
        return null;
    }
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
    const ss = (config.screenshot_interval_seconds || 120) * 1000;

    pollActiveWindow().catch(() => {});
    windowPollTimer = setInterval(() => { pollActiveWindow().catch(() => {}); }, 3000);
    heartbeatTimer = setInterval(() => { sendHeartbeat().catch(() => {}); }, hb);
    screenshotTimer = setInterval(() => { sendScreenshot().catch(() => {}); }, ss);
    idleCheckTimer = setInterval(checkIdleState, 2000);
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
        const { data } = await enqueueApi(() => jsonApi(15000).get('/status', {
            params: { agent_version: AGENT_VERSION },
        }));
        if (!data || typeof data !== 'object') {
            throw new Error('Server returned an invalid response. Check the server URL.');
        }
        const session = data.session ?? null;
        if (data.today != null) {
            resetLocalStats(session, data.today);
        } else {
            resetLocalStats(session, null);
        }
        if (data.config) config = { ...config, ...data.config };
        if (session?.status === 'active') {
            startTracking();
        } else {
            stopTracking();
        }
        const today = todayPayload();
        pushTodayToRenderer();
        pushStatsToUi();
        if (data.agent_update) {
            applyAgentUpdateInfo(data.agent_update, { forceShow: true });
        } else {
            checkForUpdate({ forceShow: true }).catch(() => {});
        }
        return {
            loggedIn: true,
            user: store.get('user'),
            session,
            today,
            live: { ...lastLive, today },
            config: data.config || config,
            agent_version: AGENT_VERSION,
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
        {
            label: 'Check for Updates',
            click: () => {
                showWindow();
                checkForUpdate({ forceShow: true }).catch(() => {});
            },
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
        height: 620,
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
    setTimeout(() => { checkForUpdate({ forceShow: true }).catch(() => {}); }, 300);
    return { ok: true };
});

function applyLoginSuccess(data) {
    store.set('token', data.token);
    store.set('user', data.user);
    store.set('device', data.device);
    if (data.config) config = { ...config, ...data.config };
    enableAutoLaunch();
    updateTray();
    updateTrayTooltip('Signed in — clock in to start');
    showWindow();
    fetchSessionState().catch(() => {});
    checkForUpdate({ forceShow: true }).catch(() => {});
}

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
        applyLoginSuccess(data);
        return { ok: true, user: data.user };
    } catch (err) {
        const msg = err.response?.data?.message
            || err.response?.data?.errors?.email?.[0]
            || (err.response?.status === 404 ? 'Login API not found (404). Re-save server URL on setup screen.' : null)
            || (err.code === 'ECONNREFUSED' ? 'Cannot reach server. Check the URL.' : err.message);
        return { ok: false, message: msg };
    }
});

// Google requires desktop apps to authenticate via the system browser (embedded
// webviews are blocked), so we spin up a one-shot local server and capture the
// authorization code Google redirects back to via a loopback URI (RFC 8252).
function createLoopbackServer(expectedState) {
    let resolveCode;
    let rejectCode;
    const codePromise = new Promise((resolve, reject) => {
        resolveCode = resolve;
        rejectCode = reject;
    });

    const server = http.createServer((req, res) => {
        let parsed;
        try {
            parsed = new URL(req.url, 'http://127.0.0.1');
        } catch {
            res.writeHead(400);
            res.end();
            return;
        }
        if (parsed.pathname !== '/callback') {
            res.writeHead(404);
            res.end();
            return;
        }
        const code = parsed.searchParams.get('code');
        const state = parsed.searchParams.get('state');
        const error = parsed.searchParams.get('error');
        const ok = !error && code && state === expectedState;
        res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
        res.end(
            '<html><body style="font-family:sans-serif;text-align:center;padding-top:80px">'
            + `<h2>${ok ? 'Signed in ✓' : 'Sign-in failed'}</h2>`
            + '<p>You can close this window and return to 5Core Attendance.</p>'
            + '</body></html>'
        );
        if (ok) {
            resolveCode(code);
        } else {
            rejectCode(new Error(error || 'Google sign-in was interrupted. Please try again.'));
        }
    });

    const listen = new Promise((resolve, reject) => {
        server.on('error', reject);
        server.listen(0, '127.0.0.1', () => resolve(server.address().port));
    });

    return { server, listen, codePromise };
}

ipcMain.handle('googleLogin', async () => {
    let server;
    try {
        const state = crypto.randomBytes(16).toString('hex');
        const loopback = createLoopbackServer(state);
        server = loopback.server;
        const port = await loopback.listen;
        const redirectUri = `http://127.0.0.1:${port}/callback`;

        // Open portal Google sign-in in the system browser (same OAuth as web login).
        // Google redirects back to the portal, which then sends a one-time code to this loopback.
        const authUrl = new URL(`${getApiBase()}/attendance/desktop-google`);
        authUrl.searchParams.set('redirect_uri', redirectUri);
        authUrl.searchParams.set('state', state);

        await shell.openExternal(authUrl.toString());

        const timeout = new Promise((_, reject) => {
            setTimeout(() => reject(new Error('Sign-in timed out. Please try again.')), 180000);
        });
        const code = await Promise.race([loopback.codePromise, timeout]);

        const { data } = await axios.post(`${getAgentApiPath()}/google-login`, {
            code,
            redirect_uri: redirectUri,
            machine_id: MACHINE_ID,
            device_name: os.hostname(),
            os_name: process.platform,
            os_version: os.release(),
            agent_version: AGENT_VERSION,
        }, { timeout: 20000 });

        applyLoginSuccess(data);
        return { ok: true, user: data.user };
    } catch (err) {
        const msg = err.response?.data?.message
            || err.response?.data?.errors?.email?.[0]
            || err.response?.data?.errors?.redirect_uri?.[0]
            || (err.code === 'ECONNREFUSED' ? 'Cannot reach server. Check the URL.' : err.message);
        return { ok: false, message: msg || 'Google sign-in failed.' };
    } finally {
        if (server) {
            try { server.close(); } catch { /* already closed */ }
        }
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

ipcMain.handle('openUpdateDownload', async () => {
    const page = config.download_page_url || `${getApiBase()}/attendance/agent`;
    await shell.openExternal(page);
    return { ok: true };
});

ipcMain.handle('snoozeUpdate', () => {
    store.set('updateSnoozeUntil', Date.now() + UPDATE_SNOOZE_MS);
    if (lastUpdatePayload) {
        pushUpdateToUi(lastUpdatePayload, { forceShow: false });
    } else {
        pushUpdateToUi(null);
    }
    updateTrayTooltip(lastSessionMeta?.status === 'active' ? 'Tracking active' : 'Running');
    return { ok: true };
});

ipcMain.handle('getAgentVersion', () => ({
    current: AGENT_VERSION,
    latest: config.agent_version || AGENT_VERSION,
}));

app.on('second-instance', () => {
    showWindow();
    checkForUpdate({ forceShow: true }).catch(() => {});
});

app.whenReady().then(async () => {
    enableAutoLaunch();
    createTray();
    createWindow();
    scheduleUpdateChecks();

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

    const update = await checkForUpdate({ forceShow: true }).catch(() => null);
    // Keep window visible when an update is available so the popup is seen.
    if (!update && win && !win.isDestroyed()) {
        win.hide();
    }
});

app.on('before-quit', () => { app.isQuitting = true; stopTracking(); });
app.on('window-all-closed', (e) => e.preventDefault());
