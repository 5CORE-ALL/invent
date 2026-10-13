@echo off
title Build 5Core Attendance Installer
cd /d "%~dp0"

echo Installing build dependencies...
call npm install
if errorlevel 1 ( pause & exit /b 1 )

echo Generating app icons...
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\generate-icons.ps1"
if errorlevel 1 ( pause & exit /b 1 )

echo.
echo Building Windows installer (this may take a few minutes)...
call npm run build
if errorlevel 1 ( pause & exit /b 1 )

echo.
echo Done! Installer is in the dist folder:
dir /b dist\*.exe
echo.
echo NEXT STEP - publish it, or the portal keeps serving the previous build and
echo employees are asked to update forever:
echo.
echo   1. Copy dist\5Core-Attendance-Setup.exe and dist\latest.yml to the server's
echo      attendance-agent\dist folder.
echo   2. On the server run:  php artisan attendance:agent-publish
echo.
echo That copies the installer into public/downloads, publishes the auto-update feed,
echo and records the version the portal is allowed to advertise.
pause
