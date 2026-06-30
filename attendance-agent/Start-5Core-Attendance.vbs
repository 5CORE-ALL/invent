' Launch 5Core Attendance without showing a CMD window
Set fso = CreateObject("Scripting.FileSystemObject")
Set shell = CreateObject("WScript.Shell")
folder = fso.GetParentFolderName(WScript.ScriptFullName)
shell.CurrentDirectory = folder

' Prefer installed / built app
portable = folder & "\dist\5Core-Attendance-Portable.exe"
If fso.FileExists(portable) Then
    shell.Run """" & portable & """", 1, False
    WScript.Quit 0
End If

' Any setup exe in dist
Set distFolder = fso.GetFolder(folder & "\dist")
On Error Resume Next
For Each f In distFolder.Files
    If LCase(fso.GetExtensionName(f.Name)) = "exe" Then
        shell.Run """" & f.Path & """", 1, False
        WScript.Quit 0
    End If
Next
On Error GoTo 0

' Dev fallback: electron without visible cmd
shell.Run "cmd /c npm start", 0, False
