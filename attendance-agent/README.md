# 5Core Attendance Desktop Agent

Windows app for employee clock-in, app tracking, idle time, and screenshots.

## For employees (installed app)

1. Run **`5Core Attendance Setup 1.0.0.exe`** from IT (one-time install)
2. Allow permissions if Windows asks (screen capture for monitoring)
3. Open **5Core Attendance** from Start Menu or Desktop
4. Enter server URL once → sign in → **Clock In**
5. Click **—** to hide to system tray — **close the window, not the app**
6. No CMD / terminal window needed

## For IT — build the installer

```bat
cd attendance-agent
build-installer.bat
```

Output: `dist\5Core Attendance Setup 1.0.0.exe` — distribute this to all employees.

Portable (no install): `npm run build:portable` → `dist\5Core-Attendance-Portable.exe`

## Dev mode (no CMD window)

Double-click **`Start-5Core-Attendance.bat`** — launches via VBS (hidden console).

Or: `npm start`

## Server URL

Base URL only, e.g. `http://127.0.0.1:8000` or `https://inventory.5coremanagement.com`

API: `{URL}/attendance/desktop-api/`
