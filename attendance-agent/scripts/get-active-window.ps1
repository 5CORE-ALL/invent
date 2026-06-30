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
