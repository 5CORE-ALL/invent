' Launch 5Core Attendance — always uses latest source (npm start) unless use-built.flag exists
Set fso = CreateObject("Scripting.FileSystemObject")
Set shell = CreateObject("WScript.Shell")
folder = fso.GetParentFolderName(WScript.ScriptFullName)
shell.CurrentDirectory = folder

builtFlag = folder & "\use-built.flag"
unpacked = folder & "\dist\win-unpacked\5Core Attendance.exe"

If fso.FileExists(builtFlag) And fso.FileExists(unpacked) Then
    shell.Run """" & unpacked & """", 1, False
    WScript.Quit 0
End If

' Default: run from source (latest UI + fixes)
shell.Run "cmd /c npm start", 0, False
