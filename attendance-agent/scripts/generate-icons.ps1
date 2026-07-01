Add-Type -AssemblyName System.Drawing
$dir = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$assets = Join-Path $dir 'assets'

function New-AppIconBitmap([int]$size) {
    $bmp = New-Object System.Drawing.Bitmap $size, $size
    $g = [System.Drawing.Graphics]::FromImage($bmp)
    $g.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::AntiAlias
    $g.Clear([System.Drawing.Color]::FromArgb(255, 15, 23, 42))
    $brush = New-Object System.Drawing.Drawing2D.LinearGradientBrush (
        (New-Object System.Drawing.Rectangle 0, 0, $size, $size),
        [System.Drawing.Color]::FromArgb(255, 59, 130, 246),
        [System.Drawing.Color]::FromArgb(255, 139, 92, 246),
        45
    )
    $pad = [int]($size * 0.1)
    $g.FillEllipse($brush, $pad, $pad, $size - 2 * $pad, $size - 2 * $pad)
    $white = New-Object System.Drawing.SolidBrush ([System.Drawing.Color]::White)
    $cx = [int]($size * 0.5)
    $g.FillRectangle($white, $cx - [int]($size * 0.04), [int]($size * 0.28), [int]($size * 0.08), [int]($size * 0.34))
    $g.FillEllipse($white, $cx - [int]($size * 0.06), $cx - [int]($size * 0.06), [int]($size * 0.12), [int]($size * 0.12))
    $g.Dispose()
    return $bmp
}

foreach ($size in @(16, 32, 48, 256)) {
    $bmp = New-AppIconBitmap $size
    $bmp.Save((Join-Path $assets "icon-$size.png"), [System.Drawing.Imaging.ImageFormat]::Png)
    $bmp.Dispose()
}

Copy-Item (Join-Path $assets 'icon-32.png') (Join-Path $assets 'tray-icon.png') -Force
Copy-Item (Join-Path $assets 'icon-256.png') (Join-Path $assets 'icon.png') -Force

$icon256 = [System.Drawing.Bitmap]::FromFile((Join-Path $assets 'icon-256.png'))
$hIcon = $icon256.GetHicon()
$icon = [System.Drawing.Icon]::FromHandle($hIcon)
$fs = [System.IO.File]::Create((Join-Path $assets 'icon.ico'))
$icon.Save($fs)
$fs.Close()
$icon256.Dispose()

Write-Host 'Generated icons in' $assets
