@echo off
cd /d "%~dp0"
echo Starting built app from dist\win-unpacked ...
if exist "dist\win-unpacked\5Core Attendance.exe" (
    start "" "dist\win-unpacked\5Core Attendance.exe"
) else (
    echo Built app not found. Run build-installer.bat first.
    pause
)
