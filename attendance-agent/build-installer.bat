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
echo Give employees: dist\5Core Attendance Setup *.exe
pause
