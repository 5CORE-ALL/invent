@extends('layouts.vertical', ['title' => $title ?? 'Desktop Agent'])

@section('css')
<style>
    .da-hero {
        border: 1px solid rgba(0,0,0,.08);
        border-radius: 16px;
        background: linear-gradient(135deg, #eff6ff 0%, #f8fafc 55%, #fff 100%);
        padding: 2rem 2rem 1.75rem;
        overflow: hidden;
        position: relative;
    }
    .da-hero::after {
        content: '';
        position: absolute;
        right: -40px;
        top: -40px;
        width: 200px;
        height: 200px;
        border-radius: 50%;
        background: rgba(37, 99, 235, .08);
        pointer-events: none;
    }
    .da-hero h1 {
        font-size: 1.65rem;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: .5rem;
    }
    .da-hero .lead {
        color: #475569;
        font-size: .95rem;
        max-width: 42rem;
        margin-bottom: 1.25rem;
    }
    .da-badge {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        font-size: .72rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #1d4ed8;
        background: #dbeafe;
        border-radius: 999px;
        padding: .25rem .65rem;
        margin-bottom: .75rem;
    }
    .da-download-btn {
        display: inline-flex;
        align-items: center;
        gap: .5rem;
        font-weight: 600;
        padding: .65rem 1.35rem;
        border-radius: 10px;
        font-size: .95rem;
    }
    .da-meta {
        font-size: .78rem;
        color: #64748b;
        margin-top: .75rem;
    }
    .da-card {
        border: 1px solid rgba(0,0,0,.08);
        border-radius: 12px;
        background: #fff;
        height: 100%;
        padding: 1.25rem;
    }
    .da-card h3 {
        font-size: .95rem;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 1rem;
    }
    .da-steps { list-style: none; padding: 0; margin: 0; }
    .da-step {
        display: flex;
        gap: .85rem;
        padding-bottom: 1.1rem;
        margin-bottom: 1.1rem;
        border-bottom: 1px solid #f1f5f9;
    }
    .da-step:last-child {
        padding-bottom: 0;
        margin-bottom: 0;
        border-bottom: 0;
    }
    .da-step-num {
        flex-shrink: 0;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: #2563eb;
        color: #fff;
        font-size: .8rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .da-step-title {
        font-weight: 600;
        font-size: .88rem;
        color: #0f172a;
        margin-bottom: .2rem;
    }
    .da-step-desc {
        font-size: .8rem;
        color: #64748b;
        line-height: 1.45;
        margin: 0;
    }
    .da-url-box {
        display: flex;
        align-items: center;
        gap: .5rem;
        margin-top: .5rem;
        padding: .45rem .65rem;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: .78rem;
        color: #334155;
        word-break: break-all;
    }
    .da-url-box code { color: #1d4ed8; font-weight: 600; }
    .da-feature {
        display: flex;
        gap: .75rem;
        align-items: flex-start;
        margin-bottom: .85rem;
    }
    .da-feature:last-child { margin-bottom: 0; }
    .da-feature-icon {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        flex-shrink: 0;
    }
    .da-feature-icon.green { background: #dcfce7; color: #16a34a; }
    .da-feature-icon.blue { background: #dbeafe; color: #2563eb; }
    .da-feature-icon.amber { background: #fef3c7; color: #d97706; }
    .da-feature-icon.slate { background: #f1f5f9; color: #475569; }
    .da-feature-title { font-size: .85rem; font-weight: 600; color: #0f172a; }
    .da-feature-desc { font-size: .76rem; color: #64748b; margin: 0; }
    .da-note {
        border-radius: 12px;
        border: 1px solid #bfdbfe;
        background: #eff6ff;
        padding: 1rem 1.15rem;
        font-size: .82rem;
        color: #1e40af;
    }
    .da-faq-item {
        border-bottom: 1px solid #f1f5f9;
        padding: .85rem 0;
    }
    .da-faq-item:last-child { border-bottom: 0; padding-bottom: 0; }
    .da-faq-q { font-weight: 600; font-size: .84rem; color: #0f172a; margin-bottom: .25rem; }
    .da-faq-a { font-size: .78rem; color: #64748b; margin: 0; line-height: 1.5; }
    .da-unavailable {
        border-radius: 10px;
        border: 1px dashed #cbd5e1;
        background: #f8fafc;
        padding: .85rem 1rem;
        font-size: .82rem;
        color: #64748b;
    }
</style>
@endsection

@section('content')
<div class="container-fluid">
    <div class="da-hero mb-4">
        <span class="da-badge"><i class="ri-windows-fill"></i> Windows · v{{ $agent_version }}</span>
        <h1>5Core Attendance — Desktop App</h1>
        <p class="lead mb-0">
            Install this small app on your work computer to clock in, track your work time, and stay connected with your team.
            It runs quietly in the system tray while you work.
        </p>
        <div class="mt-3 position-relative" style="z-index:1">
            @if($download_available)
                <a href="{{ $download_url }}" class="btn btn-primary da-download-btn">
                    <i class="ri-download-cloud-2-line" style="font-size:1.2rem"></i>
                    Download for Windows
                </a>
                <div class="da-meta">
                    File: {{ $download_filename }} · Safe to install on your company PC
                </div>
            @else
                <div class="da-unavailable">
                    <i class="ri-information-line me-1"></i>
                    The installer is not on the server yet. Ask IT to upload
                    <strong>{{ $download_filename }}</strong> to the downloads folder, or contact HR for a copy.
                </div>
            @endif
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-7">
            <div class="da-card">
                <h3><i class="ri-guide-line me-1 text-primary"></i> Setup in 4 steps</h3>
                <ol class="da-steps">
                    <li class="da-step">
                        <span class="da-step-num">1</span>
                        <div>
                            <div class="da-step-title">Download &amp; install</div>
                            <p class="da-step-desc">
                                Click <strong>Download for Windows</strong> above and run the installer.
                                Follow the prompts — it only takes a minute. You may see a Windows security notice;
                                choose <strong>Run anyway</strong> or ask IT if you are unsure.
                            </p>
                        </div>
                    </li>
                    <li class="da-step">
                        <span class="da-step-num">2</span>
                        <div>
                            <div class="da-step-title">Open the app &amp; sign in</div>
                            <p class="da-step-desc">
                                Open <strong>5Core Attendance</strong> from the Start Menu.
                                When asked for the server URL, enter:
                            </p>
                            <div class="da-url-box">
                                <i class="ri-link"></i>
                                <code>{{ $server_url }}</code>
                            </div>
                            <p class="da-step-desc mt-2 mb-0">
                                Then sign in with your usual 5Core email and password.
                            </p>
                        </div>
                    </li>
                    <li class="da-step">
                        <span class="da-step-num">3</span>
                        <div>
                            <div class="da-step-title">Clock in when you start work</div>
                            <p class="da-step-desc">
                                Press <strong>Clock In</strong> at the beginning of your shift.
                                Minimize the window — the app stays in the system tray (near the clock).
                                Double-click the tray icon anytime to see your time or clock out.
                            </p>
                        </div>
                    </li>
                    <li class="da-step">
                        <span class="da-step-num">4</span>
                        <div>
                            <div class="da-step-title">Use Break &amp; Clock Out</div>
                            <p class="da-step-desc mb-0">
                                Take a break with the <strong>Break</strong> button (lunch, tea, etc.) and
                                <strong>Resume</strong> when you return.
                                Always <strong>Clock Out</strong> at the end of your day.
                            </p>
                        </div>
                    </li>
                </ol>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="da-card mb-3">
                <h3><i class="ri-eye-line me-1 text-primary"></i> What the app records</h3>
                <div class="da-feature">
                    <div class="da-feature-icon green"><i class="ri-time-line"></i></div>
                    <div>
                        <div class="da-feature-title">Work time</div>
                        <p class="da-feature-desc">Active, idle, and break minutes while you are clocked in.</p>
                    </div>
                </div>
                <div class="da-feature">
                    <div class="da-feature-icon blue"><i class="ri-window-line"></i></div>
                    <div>
                        <div class="da-feature-title">Apps you use</div>
                        <p class="da-feature-desc">Which program is in focus (e.g. Excel, Chrome) — not what you type.</p>
                    </div>
                </div>
                @if($screenshots_enabled)
                <div class="da-feature">
                    <div class="da-feature-icon amber"><i class="ri-screenshot-2-line"></i></div>
                    <div>
                        <div class="da-feature-title">Periodic screenshots</div>
                        <p class="da-feature-desc">Screen captures while clocked in, visible to your manager in Team Monitoring.</p>
                    </div>
                </div>
                @endif
                <div class="da-feature">
                    <div class="da-feature-icon slate"><i class="ri-shield-check-line"></i></div>
                    <div>
                        <div class="da-feature-title">Only while clocked in</div>
                        <p class="da-feature-desc">Nothing is tracked before Clock In or after Clock Out.</p>
                    </div>
                </div>
            </div>
            <div class="da-note">
                <i class="ri-information-line me-1"></i>
                <strong>Required for remote work.</strong>
                The desktop app must be running and clocked in for your hours to count in Team Monitoring and payroll.
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="da-card">
                <h3><i class="ri-question-line me-1 text-primary"></i> Common questions</h3>
                <div class="da-faq-item">
                    <div class="da-faq-q">Can I close the window?</div>
                    <p class="da-faq-a">Yes. Closing the window only hides it. The app keeps running in the system tray until you Clock Out or quit from the tray menu.</p>
                </div>
                <div class="da-faq-item">
                    <div class="da-faq-q">What if I step away from my desk?</div>
                    <p class="da-faq-a">After a short idle period, a popup asks if you are still working. Answer honestly — idle time is tracked separately from active work.</p>
                </div>
                <div class="da-faq-item">
                    <div class="da-faq-q">Do I need to keep the browser open?</div>
                    <p class="da-faq-a">No. The desktop app works independently. You only need the browser to download it or view Team Monitoring as a manager.</p>
                </div>
                <div class="da-faq-item">
                    <div class="da-faq-q">Something is not working?</div>
                    <p class="da-faq-a">Try signing out and back in, or restart the app from the Start Menu. If the problem continues, contact IT or HR with a screenshot of the error.</p>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="da-card">
                <h3><i class="ri-computer-line me-1 text-primary"></i> Daily checklist</h3>
                <ul class="list-unstyled mb-0" style="font-size:.82rem;color:#475569">
                    <li class="mb-2"><i class="ri-checkbox-circle-fill text-success me-2"></i>Start your PC and open 5Core Attendance</li>
                    <li class="mb-2"><i class="ri-checkbox-circle-fill text-success me-2"></i>Clock In before starting work</li>
                    <li class="mb-2"><i class="ri-checkbox-circle-fill text-success me-2"></i>Use Break for lunch / short breaks</li>
                    <li class="mb-2"><i class="ri-checkbox-circle-fill text-success me-2"></i>Resume after every break</li>
                    <li class="mb-0"><i class="ri-checkbox-circle-fill text-success me-2"></i>Clock Out when your shift ends</li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
