@echo off
title Close 5Core Attendance for install
echo.
echo Closing 5Core Attendance so Setup can continue...
echo Do NOT close the Setup window. After this finishes, click Retry.
echo.

reg delete "HKCU\Software\Microsoft\Windows\CurrentVersion\Run" /v "5Core Attendance" /f >nul 2>&1
reg delete "HKCU\Software\Microsoft\Windows\CurrentVersion\Run" /v "electron.app.5Core Attendance" /f >nul 2>&1

if exist "%LOCALAPPDATA%\Programs\5Core Attendance\5Core Attendance.exe" (
  start "" /wait "%LOCALAPPDATA%\Programs\5Core Attendance\5Core Attendance.exe" --quit
)

taskkill /F /T /IM "5Core Attendance.exe" >nul 2>&1
taskkill /F /T /IM "5Core-Attendance.exe" >nul 2>&1

echo Done. Click Retry on the installer.
echo If Retry still appears, click Cancel, then run 5Core-Attendance-Setup.exe again.
echo.
pause
