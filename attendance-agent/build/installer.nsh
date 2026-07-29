; Force upgrade of the existing 5Core Attendance install (same appId),
; instead of leaving an old copy and creating a second install.
!include "LogicLib.nsh"

!macro customInit
  ; Stop the running app so files can be replaced.
  nsExec::ExecToLog 'taskkill /F /IM "5Core Attendance.exe" /T'
  Pop $0
  Sleep 1200

  ; Remove any previous install registered under our appId (per-user / per-machine).
  StrCpy $R9 ""

  ReadRegStr $R9 HKCU "Software\Microsoft\Windows\CurrentVersion\Uninstall\com.5core.attendance" "UninstallString"
  ${If} $R9 == ""
    ReadRegStr $R9 HKCU "Software\Microsoft\Windows\CurrentVersion\Uninstall\{com.5core.attendance}" "UninstallString"
  ${EndIf}
  ${If} $R9 == ""
    ReadRegStr $R9 HKLM "Software\Microsoft\Windows\CurrentVersion\Uninstall\com.5core.attendance" "UninstallString"
  ${EndIf}
  ${If} $R9 == ""
    ReadRegStr $R9 HKLM "Software\Microsoft\Windows\CurrentVersion\Uninstall\{com.5core.attendance}" "UninstallString"
  ${EndIf}
  ${If} $R9 == ""
    ReadRegStr $R9 HKCU "Software\Microsoft\Windows\CurrentVersion\Uninstall\5Core Attendance" "UninstallString"
  ${EndIf}
  ${If} $R9 == ""
    ReadRegStr $R9 HKLM "Software\Microsoft\Windows\CurrentVersion\Uninstall\5Core Attendance" "UninstallString"
  ${EndIf}

  ${If} $R9 != ""
    ; UninstallString already includes the exe path (and often /currentuser).
    ; Keep app data (login) — deleteAppDataOnUninstall is false in package.json.
    ExecWait '$R9 /S'
    Sleep 1500
  ${EndIf}
!macroend
