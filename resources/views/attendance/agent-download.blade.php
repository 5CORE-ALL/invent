@extends('layouts.vertical', ['title' => $title ?? 'Desktop Agent'])

@section('content')
<div class="container-fluid">
    <div class="card p-4">
        <h4 class="mb-2"><i class="ri-computer-line me-2 text-primary"></i>5Core Attendance Desktop Agent</h4>
        <p class="text-muted">Windows system-tray app for complete employee tracing — clock in/out, app usage, idle time, and screenshots.</p>

        <div class="row g-3 mt-2">
            <div class="col-md-6">
                <h6>For employees (recommended)</h6>
                <ol class="small">
                    <li>IT runs <code>attendance-agent\build-installer.bat</code> once</li>
                    <li>Install <strong>5Core Attendance Setup.exe</strong> on each PC</li>
                    <li>Open from Start Menu → enter server URL → sign in → Clock In</li>
                    <li>Minimize to system tray — no CMD window needed</li>
                </ol>
                <h6 class="mt-3">Dev / testing</h6>
                <ol class="small">
                    <li>Double-click <code>attendance-agent\Start-5Core-Attendance.bat</code> (no visible CMD)</li>
                    <li>Or: <code>cd attendance-agent && npm install && npm start</code></li>
                </ol>
                <p class="small text-muted mb-0">API endpoint: <code>{{ $api_url }}</code> · Agent v{{ $agent_version }}</p>
            </div>
            <div class="col-md-6">
                <h6>What it tracks</h6>
                <ul class="small mb-0">
                    <li>Clock in / out / pause from system tray</li>
                    <li>Active desktop app &amp; window title every 60s</li>
                    <li>System idle time (mouse/keyboard)</li>
                    <li>Screenshot every 5 minutes while clocked in</li>
                    <li>Works across all apps — not limited to this portal</li>
                </ul>
            </div>
        </div>

        <hr>
        <p class="small mb-0"><strong>Note:</strong> Employees must use the desktop agent for full WFH monitoring. Web-only tracking covers portal pages only.</p>
    </div>
</div>
@endsection
