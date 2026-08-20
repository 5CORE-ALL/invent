; 5Core Attendance is a tray app: it swallows WM_CLOSE and stays running.
; electron-builder's default check then loops:
;   "5Core Attendance cannot be closed. Please close it manually and click Retry"
; Also do not re-run the previous uninstaller here — that silent uninstall fails
; the same way and triggers the same Retry dialog during "Installing…".

!include "LogicLib.nsh"

!macro KillAttendanceApp
  ; Stop Windows login-item restart while files are being replaced.
  DeleteRegValue HKCU "Software\Microsoft\Windows\CurrentVersion\Run" "5Core Attendance"
  DeleteRegValue HKCU "Software\Microsoft\Windows\CurrentVersion\Run" "electron.app.5Core Attendance"
  DeleteRegValue HKCU "Software\Microsoft\Windows\CurrentVersion\Run" "com.5core.attendance"

  ; Ask the running instance to quit (handled by single-instance --quit).
  StrCpy $R8 ""
  ReadRegStr $R8 HKCU "Software\Microsoft\Windows\CurrentVersion\Uninstall\com.5core.attendance" "InstallLocation"
  ${If} $R8 == ""
    ReadRegStr $R8 HKCU "Software\Microsoft\Windows\CurrentVersion\Uninstall\{com.5core.attendance}" "InstallLocation"
  ${EndIf}
  ${If} $R8 == ""
    StrCpy $R8 "$LOCALAPPDATA\Programs\5Core Attendance"
  ${EndIf}
  ${If} ${FileExists} "$R8\5Core Attendance.exe"
    nsExec::ExecToLog '"$R8\5Core Attendance.exe" --quit'
    Pop $0
    Sleep 700
  ${EndIf}

  ; Force-kill. Run through cmd.exe so the space in the image name is quoted correctly.
  nsExec::ExecToLog '"$SYSDIR\cmd.exe" /C taskkill /F /T /IM "5Core Attendance.exe"'
  Pop $0
  nsExec::ExecToLog '"$SYSDIR\cmd.exe" /C taskkill /F /T /IM "5Core-Attendance.exe"'
  Pop $0
  Sleep 900
!macroend

!macro ForgetOldUninstaller
  ; If these keys remain, electron-builder runs the *old* uninstaller during
  ; install. That old uninstaller uses a find.exe process check that often
  ; false-positives and shows the Retry dialog forever.
  DeleteRegKey HKCU "Software\Microsoft\Windows\CurrentVersion\Uninstall\com.5core.attendance"
  DeleteRegKey HKCU "Software\Microsoft\Windows\CurrentVersion\Uninstall\{com.5core.attendance}"
  DeleteRegKey HKCU "Software\Microsoft\Windows\CurrentVersion\Uninstall\5Core Attendance"
  DeleteRegKey HKLM "Software\Microsoft\Windows\CurrentVersion\Uninstall\com.5core.attendance"
  DeleteRegKey HKLM "Software\Microsoft\Windows\CurrentVersion\Uninstall\{com.5core.attendance}"
  DeleteRegKey HKLM "Software\Microsoft\Windows\CurrentVersion\Uninstall\5Core Attendance"
!macroend

!macro customInit
  !insertmacro KillAttendanceApp

  StrCpy $R7 ""
  ReadRegStr $R7 HKCU "Software\Microsoft\Windows\CurrentVersion\Uninstall\com.5core.attendance" "InstallLocation"
  ${If} $R7 == ""
    ReadRegStr $R7 HKCU "Software\Microsoft\Windows\CurrentVersion\Uninstall\{com.5core.attendance}" "InstallLocation"
  ${EndIf}
  ${If} $R7 == ""
    ReadRegStr $R7 HKLM "Software\Microsoft\Windows\CurrentVersion\Uninstall\com.5core.attendance" "InstallLocation"
  ${EndIf}
  ${If} $R7 == ""
    StrCpy $R7 "$LOCALAPPDATA\Programs\5Core Attendance"
  ${EndIf}

  ${If} ${FileExists} "$R7\5Core Attendance.exe"
    Delete "$R7\5Core Attendance.exe"
  ${EndIf}

  !insertmacro ForgetOldUninstaller
  !insertmacro KillAttendanceApp
!macroend

!macro customCheckAppRunning
  ; Replaces electron-builder's FIND_PROCESS / find.exe loop (known false positives).
  !insertmacro KillAttendanceApp
!macroend

!macro customUnInit
  !insertmacro KillAttendanceApp
!macroend

!macro customUnInstallCheck
  ; Old uninstall returning an error must not abort the new install.
!macroend

!macro customUnInstallCheckCurrentUser
!macroend
