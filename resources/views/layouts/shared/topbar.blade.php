@php
    $__topbarBrand = config('app.name');
@endphp
<!-- ========== Topbar Start ========== -->
<div class="navbar-custom">
    <div class="topbar container-fluid">
        <div class="d-flex align-items-center gap-2 topbar-brand-row">

            <!-- Sidebar Menu Toggle Button -->
            <button class="button-toggle-menu" type="button">
                <i class="ri-menu-line"></i>
            </button>

            <!-- Topbar Brand Logo -->
            <div class="logo-topbar">
                <a href="{{ route('any', 'index') }}" class="logo-light" aria-label="{{ $__topbarBrand }}">
                    @include('layouts.shared.brand-wordmark')
                </a>
                <a href="{{ route('any', 'index') }}" class="logo-dark" aria-label="{{ $__topbarBrand }}">
                    @include('layouts.shared.brand-wordmark')
                </a>
            </div>

            <!-- Horizontal Menu Toggle Button -->
            <button class="navbar-toggle" data-bs-toggle="collapse" data-bs-target="#topnav-menu-content">
                <div class="lines">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </button>

            <!-- Topbar Search Form -->
            <div class="app-search d-none d-lg-block">
                <form>
                    <!-- <div class="input-group">
                        <input type="search" class="form-control" placeholder="Search...">
                        <span class="ri-search-line search-icon text-muted"></span>
                    </div> -->
                </form>
            </div>
        </div>

        {{-- Floating "Team" shortcut: left of the world clocks --}}
        @auth
            <a href="{{ route('team.view') }}" class="topbar-team-btn{{ request()->routeIs('team.view') ? ' is-active' : '' }}"
                title="Team View" aria-label="Open Team View">
                <i class="ri-team-fill"></i>
                <span class="topbar-team-btn__label">Team</span>
            </a>
            {{-- ATE (Auto Task Event) "+ ATE" / "ATE" pills on analytics + active-channels pages (config/ace.php) --}}
            @if(! empty($aceEnabled ?? null) && ! empty($acePage ?? null))
                <div class="topbar-ace d-inline-flex align-items-center">
                    @include('partials.ace-buttons', ['acePage' => $acePage, 'aceSource' => $aceSource ?? null])
                </div>
                <style>
                    .topbar-ace { margin: 0 .5rem 0 .35rem; }
                    .topbar-ace .ace-buttons { flex-wrap: nowrap !important; gap: .35rem !important; }
                    .topbar-ace .ace-btn { padding: 4px 11px; font-size: .74rem; line-height: 1.5; }
                    @media (max-width: 767.98px) { .topbar-ace { display: none !important; } }
                </style>
            @endif
        @endauth

        @include('layouts.shared.world-clocks-inline')

        <style>
            .topbar-team-btn {
                display: inline-flex;
                align-items: center;
                gap: 0.4rem;
                flex-shrink: 0;
                margin: 0 0.5rem;
                padding: 0 14px 0 10px;
                height: 40px;
                border-radius: 999px;
                background: linear-gradient(135deg, #6d28d9, #3b82f6);
                color: #fff;
                font-weight: 800;
                font-size: 0.85rem;
                letter-spacing: 0.02em;
                text-decoration: none;
                box-shadow: 0 6px 16px rgba(79, 70, 229, 0.4);
                animation: topbarTeamFloat 3.2s ease-in-out infinite;
                transition: box-shadow 0.2s ease, filter 0.2s ease;
            }
            .topbar-team-btn:hover { color: #fff; filter: brightness(1.1); box-shadow: 0 10px 22px rgba(79, 70, 229, 0.55); animation-play-state: paused; }
            .topbar-team-btn i { font-size: 1.2rem; line-height: 1; }
            .topbar-team-btn.is-active { box-shadow: 0 0 0 3px rgba(109, 93, 252, 0.3), 0 6px 16px rgba(79, 70, 229, 0.4); }
            @keyframes topbarTeamFloat { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-3px); } }
            @media (max-width: 575.98px) { .topbar-team-btn__label { display: none; } .topbar-team-btn { padding: 0 11px; } }
            @media (prefers-reduced-motion: reduce) { .topbar-team-btn { animation: none; } }
        </style>

        <style>
            .topbar-brand-row {
                min-width: 0;
            }
            .topbar-brand-row .button-toggle-menu {
                flex-shrink: 0;
                width: 44px;
                z-index: 2;
            }
            .logo-topbar {
                float: none !important;
                display: flex !important;
                align-items: center;
                line-height: 1;
                padding: 0 0.15rem 0 0;
                flex-shrink: 0;
            }
            .logo-topbar a {
                text-decoration: none;
            }
            .brand-wordmark {
                display: inline-flex;
                align-items: baseline;
                gap: 0.28em;
                font-family: "Arial Black", Impact, "Franklin Gothic Bold", sans-serif;
                font-style: italic;
                font-weight: 900;
                color: #e10600;
                line-height: 1;
                white-space: nowrap;
                letter-spacing: 0;
            }
            .brand-wordmark__five {
                letter-spacing: 0;
            }
            .brand-wordmark__core {
                letter-spacing: 0.16em;
                margin-right: -0.08em;
            }
            .logo-topbar .brand-wordmark {
                font-size: 26px;
            }
            .logo-topbar .brand-wordmark__core {
                letter-spacing: 0;
                margin-right: 0;
            }
            .leftside-menu .brand-wordmark {
                font-size: 34px;
            }

            .topbar-dar-btn {
                display: inline-flex;
                align-items: center;
                gap: 0.4rem;
                flex-shrink: 0;
                margin-right: 0.5rem;
                padding: 0.4rem 0.85rem;
                border: none;
                cursor: pointer;
                border-radius: 999px;
                background: #2563eb;
                color: #fff;
                font-size: 0.8rem;
                font-weight: 700;
                text-decoration: none;
                box-shadow: 0 2px 6px rgba(37, 99, 235, 0.35);
                transition: background 0.15s ease, transform 0.15s ease, color 0.15s ease;
            }
            .topbar-dar-btn:hover {
                background: #1d4ed8;
                color: #fff;
                transform: translateY(-1px);
            }
            .topbar-dar-btn i { font-size: 0.9rem; }
            .topbar-dar-btn__pct {
                font-variant-numeric: tabular-nums;
                font-weight: 800;
                font-size: 0.92rem;
                line-height: 1;
            }
            .topbar-dar-btn.is-dar-high {
                background: #fce7f3;
                color: #831843;
                box-shadow: 0 2px 6px rgba(131, 24, 67, 0.18);
            }
            .topbar-dar-btn.is-dar-high:hover {
                background: #fbcfe8;
                color: #831843;
            }
            .topbar-dar-btn.is-dar-mid {
                background: #dcfce7;
                color: #166534;
                box-shadow: 0 2px 6px rgba(22, 101, 52, 0.18);
            }
            .topbar-dar-btn.is-dar-mid:hover {
                background: #bbf7d0;
                color: #166534;
            }
            .topbar-dar-btn.is-dar-low {
                background: #fee2e2;
                color: #991b1b;
                box-shadow: 0 2px 6px rgba(153, 27, 27, 0.18);
            }
            .topbar-dar-btn.is-dar-low:hover {
                background: #fecaca;
                color: #991b1b;
            }
            .topbar-review-btn.is-review-none { background: #e0e7ff; color: #3730a3; box-shadow: 0 2px 6px rgba(55, 48, 163, 0.18); }
            .topbar-review-btn.is-review-none:hover { background: #c7d2fe; color: #3730a3; }
            .topbar-review-btn.is-review-high { background: #dcfce7; color: #166534; box-shadow: 0 2px 6px rgba(22, 101, 52, 0.18); }
            .topbar-review-btn.is-review-high:hover { background: #bbf7d0; color: #166534; }
            .topbar-review-btn.is-review-mid { background: #fef3c7; color: #92400e; box-shadow: 0 2px 6px rgba(146, 64, 14, 0.18); }
            .topbar-review-btn.is-review-mid:hover { background: #fde68a; color: #92400e; }
            .topbar-review-btn.is-review-low { background: #fee2e2; color: #991b1b; box-shadow: 0 2px 6px rgba(153, 27, 27, 0.18); }
            .topbar-review-btn.is-review-low:hover { background: #fecaca; color: #991b1b; }
            .topbar-review-btn i { color: #f59e0b; }

            .topbar-incentive-dollar-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 0.35rem;
                position: relative;
                overflow: visible;
                min-width: 32px;
                height: 32px;
                padding: 0 0.65rem;
                border: none;
                border-radius: 999px;
                background: #15803d;
                color: #fff;
                font-weight: 800;
                font-size: 1.05rem;
                line-height: 1;
                cursor: pointer;
                text-decoration: none;
            }
            .topbar-incentive-dollar-btn.has-amount {
                padding-right: 0.45rem;
            }
            .topbar-incentive-amount-badge {
                display: inline-flex;
                align-items: center;
                padding: 0.12em 0.45em;
                border-radius: 999px;
                background: #fef3c7;
                color: #92400e;
                border: 1px solid #fcd34d;
                font-size: 0.68rem;
                font-weight: 800;
                line-height: 1.15;
                white-space: nowrap;
            }
            .topbar-incentive-dollar-btn:hover {
                background: #166534;
                color: #fff;
            }
            .topbar-incentive-dollar-btn.is-cutoff-alert {
                background: #dc2626;
                box-shadow: 0 0 0 2px rgba(220, 38, 38, 0.28);
                animation: ts-inc-alert-pulse 1.15s ease-in-out infinite;
            }
            .topbar-incentive-dollar-btn.is-cutoff-alert:hover {
                background: #b91c1c;
                color: #fff;
            }
            @keyframes ts-inc-alert-pulse {
                0%, 100% { transform: scale(1); box-shadow: 0 0 0 2px rgba(220, 38, 38, 0.28); }
                50% { transform: scale(1.08); box-shadow: 0 0 0 6px rgba(220, 38, 38, 0.18); }
            }
            @media (max-width: 575.98px) {
                .topbar-dar-btn__label { display: none; }
                .topbar-dar-btn { padding: 0.4rem 0.55rem; }
            }

            .topbar-helpdesk-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
                margin-right: 0.5rem;
                width: 42px;
                height: 42px;
                line-height: 0;
            }
            .topbar-helpdesk-btn__icon {
                width: 100%;
                height: 100%;
                object-fit: contain;
                filter: drop-shadow(0 2px 6px rgba(0, 0, 0, 0.18));
                transition: transform 0.2s ease;
            }
            .topbar-helpdesk-btn:hover .topbar-helpdesk-btn__icon {
                transform: scale(1.08);
            }

            .topbar-task-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
                margin-right: 0.5rem;
                width: 42px;
                height: 42px;
                padding: 0;
                border: none;
                border-radius: 50%;
                background: #fff;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
                transition: transform 0.2s ease;
            }
            .topbar-task-btn:hover { transform: scale(1.08); }
            .topbar-task-btn__icon {
                width: 100%;
                height: 100%;
                border-radius: 50%;
                object-fit: cover;
            }

            .topbar-activity-btn,
            .topbar-soi-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 0.28rem;
                flex-shrink: 0;
                margin-right: 0.5rem;
                min-width: 42px;
                height: 42px;
                padding: 0 0.7rem;
                border: none;
                border-radius: 999px;
                background: #f1f5f9;
                color: #334155;
                font-size: 0.78rem;
                font-weight: 800;
                letter-spacing: 0.04em;
                line-height: 1;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.18);
                transition: transform 0.2s ease, background 0.15s ease, color 0.15s ease;
            }
            .topbar-soi-btn:hover { transform: scale(1.08); }
            .topbar-soi-btn__count {
                display: none;
                min-width: 1.15em;
                font-variant-numeric: tabular-nums;
            }
            .topbar-soi-btn.has-points {
                background: #dc2626;
                color: #fff;
                box-shadow: 0 4px 12px rgba(220, 38, 38, 0.35);
            }
            .topbar-soi-btn.has-points:hover {
                background: #b91c1c;
                color: #fff;
            }
            .topbar-soi-btn.has-points .topbar-soi-btn__count {
                display: inline;
            }
            .topbar-ann-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                position: relative;
                flex-shrink: 0;
                margin-right: 0.5rem;
                width: 42px;
                height: 42px;
                padding: 0;
                border: none;
                border-radius: 50%;
                background: #f59e0b;
                color: #fff;
                box-shadow: 0 4px 12px rgba(245, 158, 11, 0.35);
                transition: transform 0.2s ease, background 0.15s ease;
            }
            .topbar-ann-btn:hover { background: #d97706; color: #fff; transform: scale(1.08); }
            .topbar-ann-btn i { font-size: 1.15rem; }
            .topbar-ann-btn__count {
                display: none;
                position: absolute;
                top: -4px;
                right: -4px;
                min-width: 18px;
                height: 18px;
                padding: 0 5px;
                border-radius: 999px;
                background: #dc2626;
                color: #fff;
                font-size: 0.65rem;
                font-weight: 800;
                line-height: 18px;
                text-align: center;
            }
            .topbar-ann-btn.has-posts .topbar-ann-btn__count { display: inline-block; }
            .topbar-chat-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                position: relative;
                flex-shrink: 0;
                margin-right: 0.5rem;
                width: 42px;
                height: 42px;
                padding: 0;
                border: none;
                border-radius: 50%;
                background: #0f766e;
                color: #fff;
                box-shadow: 0 4px 12px rgba(15, 118, 110, 0.35);
                transition: transform 0.2s ease, background 0.15s ease;
            }
            .topbar-chat-btn:hover { background: #0d9488; color: #fff; transform: scale(1.08); }
            .topbar-chat-btn i { font-size: 1.15rem; }
            .topbar-chat-btn__count {
                display: none;
                position: absolute;
                top: -4px;
                right: -4px;
                min-width: 18px;
                height: 18px;
                padding: 0 5px;
                border-radius: 999px;
                background: #facc15;
                color: #1f2937;
                box-shadow: 0 0 0 2px #fff;
                font-size: 0.65rem;
                font-weight: 800;
                line-height: 18px;
                text-align: center;
            }
            .topbar-chat-btn.has-unread .topbar-chat-btn__count { display: inline-block; }
            .topbar-chat-btn__red {
                display: none;
                position: absolute;
                top: -4px;
                left: -4px;
                min-width: 18px;
                height: 18px;
                padding: 0 5px;
                border-radius: 999px;
                background: #dc2626;
                color: #fff;
                box-shadow: 0 0 0 2px #fff;
                font-size: 0.65rem;
                font-weight: 800;
                line-height: 18px;
                text-align: center;
            }
            .topbar-chat-btn.has-unread-red .topbar-chat-btn__red { display: inline-block; }

            .topbar-rr-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                position: relative;
                flex-shrink: 0;
                margin-right: 0.5rem;
                width: 42px;
                height: 42px;
                padding: 0;
                border: none;
                border-radius: 50%;
                background: #dc2626;
                color: #fff;
                box-shadow: 0 4px 12px rgba(220, 38, 38, 0.35);
                transition: transform 0.2s ease, background 0.15s ease;
            }
            .topbar-rr-btn:hover { background: #b91c1c; color: #fff; transform: scale(1.08); }
            .topbar-rr-btn.has-data {
                background: #16a34a;
                box-shadow: 0 4px 12px rgba(22, 163, 74, 0.35);
            }
            .topbar-rr-btn.has-data:hover { background: #15803d; color: #fff; }
            .topbar-rr-btn i { font-size: 1.15rem; }
            .topbar-rr-btn__mark {
                font-size: 0.78rem;
                font-weight: 800;
                letter-spacing: 0.02em;
                line-height: 1;
            }
            .topbar-rr-btn__count {
                display: none;
                position: absolute;
                top: -4px;
                right: -4px;
                min-width: 18px;
                height: 18px;
                padding: 0 5px;
                border-radius: 999px;
                background: #0f172a;
                color: #fff;
                font-size: 0.65rem;
                font-weight: 800;
                line-height: 18px;
                text-align: center;
            }
            .topbar-rr-btn.has-data .topbar-rr-btn__count { display: inline-block; }
            .topbar-rr-btn.is-label {
                width: auto;
                min-width: 42px;
                padding: 0 0.35rem;
                background: transparent;
                color: #1d4ed8;
                box-shadow: none;
                font-size: 0.95rem;
                font-weight: 800;
                letter-spacing: 0.02em;
                line-height: 1;
            }
            .topbar-rr-btn.is-label:hover,
            .topbar-rr-btn.is-label.has-data,
            .topbar-rr-btn.is-label.has-data:hover {
                background: transparent;
                color: #1d4ed8;
                box-shadow: none;
            }

        </style>

        @php
            $topbarChatUnread = (int) ($topbarChatUnread ?? 0);
            $topbarChatUnreadChats = (int) ($topbarChatUnreadChats ?? 0);
        @endphp
        <a href="{{ route('chat.index') }}" id="chatTopbarBtn" target="_blank" rel="noopener"
            class="topbar-chat-btn{{ $topbarChatUnread > 0 ? ' has-unread' : '' }}{{ $topbarChatUnreadChats > 0 ? ' has-unread-red' : '' }}"
            data-chat-unread="{{ $topbarChatUnread }}"
            title="5Core Chat"
            aria-label="5Core Chat{{ $topbarChatUnread > 0 ? ' — '.$topbarChatUnread.' chats' : '' }}">
            <i class="ri-chat-3-fill"></i>
            <span class="topbar-chat-btn__red" title="Unread chats">{{ $topbarChatUnreadChats > 99 ? '99+' : $topbarChatUnreadChats }}</span>
            <span class="topbar-chat-btn__count">{{ $topbarChatUnread > 99 ? '99+' : $topbarChatUnread }}</span>
        </a>

        @php
            $topbarAnnCount = (int) ($topbarAnnCount ?? 0);
        @endphp
        <button type="button" id="annTopbarOpenBtn"
            class="topbar-ann-btn{{ $topbarAnnCount > 0 ? ' has-posts' : '' }}"
            data-ann-count="{{ $topbarAnnCount }}"
            title="5 Core Announcements"
            aria-label="5 Core Announcements{{ $topbarAnnCount > 0 ? ' — '.$topbarAnnCount : '' }}">
            <i class="ri-megaphone-fill"></i>
            <span class="topbar-ann-btn__count">{{ $topbarAnnCount }}</span>
        </button>

        @auth
            @php
                $topbarRrCount = (int) ($topbarRrCount ?? 0);
                $topbarClrrCount = (int) ($topbarClrrCount ?? 0);
                $topbarClrrPercent = (int) ($topbarClrrPercent ?? 0);
                $topbarRrTitle = $topbarRrCount > 0
                    ? 'R&R — '.$topbarRrCount.' item'.($topbarRrCount === 1 ? '' : 's')
                    : 'R&R — no data';
                $topbarClrrTitle = $topbarClrrCount > 0
                    ? 'CL R&R — '.$topbarClrrPercent.'%'
                    : 'CL R&R — no data';
            @endphp
            <button type="button" id="rrTopbarOpenBtn"
                class="topbar-rr-btn is-label{{ $topbarRrCount > 0 ? ' has-data' : '' }}"
                data-rr-count="{{ $topbarRrCount }}"
                title="{{ $topbarRrTitle }}"
                aria-label="{{ $topbarRrTitle }}">
                <span class="topbar-rr-btn__label">R&amp;R</span>
                <span class="topbar-rr-btn__count">{{ $topbarRrCount }}</span>
            </button>
            <button type="button" id="clrrTopbarOpenBtn"
                class="topbar-rr-btn{{ $topbarClrrCount > 0 ? ' has-data' : '' }}"
                data-clrr-count="{{ $topbarClrrCount }}"
                data-clrr-percent="{{ $topbarClrrPercent }}"
                title="{{ $topbarClrrTitle }}"
                aria-label="{{ $topbarClrrTitle }}">
                <span class="topbar-rr-btn__mark">CL</span>
                <span class="topbar-rr-btn__count">{{ $topbarClrrPercent }}%</span>
            </button>
        @endauth

        @php
            $topbarSoiCount = (int) ($topbarSoiCount ?? 0);
        @endphp
        <button type="button" id="activityTopbarBtn"
            class="topbar-activity-btn topbar-soi-btn{{ $topbarSoiCount > 0 ? ' has-points' : '' }}"
            data-soi-count="{{ $topbarSoiCount }}"
            title="Scope of Improvement"
            aria-label="Scope of Improvement{{ $topbarSoiCount > 0 ? ' — '.$topbarSoiCount.' point'.($topbarSoiCount === 1 ? '' : 's') : '' }}">
            <span class="topbar-soi-btn__label">SI</span>
            <span class="topbar-soi-btn__count">{{ $topbarSoiCount }}</span>
        </button>

        @php
            $topbarDarPct = (int) ($topbarDarPct ?? 0);
            $topbarDarCount = (int) ($topbarDarCount ?? 0);
            $topbarDarTarget = (int) ($topbarDarTarget ?? 25);
            $topbarDarBand = (string) ($topbarDarBand ?? 'low');
            $topbarDarShowPct = auth()->check();
            $topbarDarBandClass = '';
            if ($topbarDarShowPct) {
                $topbarDarBandClass = $topbarDarBand === 'high'
                    ? 'is-dar-high'
                    : ($topbarDarBand === 'mid' ? 'is-dar-mid' : 'is-dar-low');
            }
            $topbarDarTitle = $topbarDarShowPct
                ? 'DAR — '.$topbarDarCount.'/'.$topbarDarTarget.' last 30 days ('.$topbarDarPct.'%)'
                : 'Daily Activity Report (DAR)';
        @endphp
        <button type="button" id="darTopbarOpenBtn" class="topbar-dar-btn {{ $topbarDarBandClass }}"
            title="{{ $topbarDarTitle }}"
            aria-label="{{ $topbarDarTitle }}">
            <i class="fas fa-clipboard-list"></i>
            <span class="topbar-dar-btn__label">DAR</span>
            @if ($topbarDarShowPct)
                <span class="topbar-dar-btn__pct">{{ $topbarDarPct }}%</span>
            @endif
        </button>

        @auth
            {{-- My Review: average of all peer reviews I have received (filled by my-review-modal script) --}}
            <button type="button" id="myReviewTopbarBtn" class="topbar-dar-btn topbar-review-btn is-review-none"
                title="My Review — average of reviews received" aria-label="My Review">
                <i class="ri-star-fill"></i>
                <span class="topbar-dar-btn__label">REVIEW</span>
                <span class="topbar-dar-btn__pct" id="myReviewTopbarAvg">&hellip;</span>
            </button>
        @endauth

        @auth
            @if (auth()->user()->is5CoreMember())
                <a href="{{ url('/help-desk-faqs') }}" class="topbar-helpdesk-btn" title="5Core Help Desk" aria-label="Open 5Core Help Desk">
                    <img src="{{ asset('images/chat-icon.png') }}" alt="5Core Help Desk" class="topbar-helpdesk-btn__icon">
                </a>
            @endif
        @endauth

        @unless($hideFloatingTaskButton ?? false)
            <button type="button" id="open-task-form-btn" class="topbar-task-btn" title="Add Task" aria-label="Add Task">
                <img src="{{ asset('assets/css/icondes.jpeg') }}" alt="Add Task" class="topbar-task-btn__icon">
            </button>
        @endunless

        <ul class="topbar-menu d-flex align-items-center gap-3">
            <li class="dropdown d-lg-none">
                <a class="nav-link dropdown-toggle arrow-none" data-bs-toggle="dropdown" href="#" role="button"
                    aria-haspopup="false" aria-expanded="false">
                    <i class="ri-search-line fs-22"></i>
                </a>
                <div class="dropdown-menu dropdown-menu-animated dropdown-lg p-0">
                    <form class="p-3">
                        <input type="search" class="form-control" placeholder="Search ..."
                            aria-label="Recipient's username">
                    </form>
                </div>
            </li>

            <li class="d-none d-sm-inline-block">
                <div class="nav-link" id="light-dark-mode">
                    <i class="ri-moon-line fs-22"></i>
                </div>
            </li>

            @auth
            @php
                $topbarIncTotal = (float) ($topbarIncentiveTotal ?? 0);
                $topbarIncCount = (int) ($topbarIncentiveCount ?? 0);
                $topbarIncAlert = !empty($topbarIncentiveAlert);
                $topbarIncShow = $topbarIncTotal > 0 || $topbarIncCount > 0;
                $topbarIncLabel = '₹'.number_format($topbarIncTotal, 0);
            @endphp
            <li class="d-flex align-items-center">
                <a href="{{ route('incentives.index') }}"
                        id="ts-incentive-header-btn"
                        class="topbar-incentive-dollar-btn{{ $topbarIncShow ? ' has-amount' : '' }}{{ $topbarIncAlert ? ' is-cutoff-alert' : '' }}"
                        data-incentive-amount="{{ $topbarIncTotal }}"
                        title="{{ $topbarIncShow ? 'My incentives · '.$topbarIncLabel : 'My incentives' }}"
                        aria-label="Open my incentives">₹<span id="ts-incentive-header-amount" class="topbar-incentive-amount-badge{{ $topbarIncShow ? '' : ' d-none' }}">{{ $topbarIncLabel }}</span></a>
            </li>
            @endauth
            <li class="dropdown">
                <a class="nav-link dropdown-toggle arrow-none nav-user" data-bs-toggle="dropdown" href="#"
                    role="button" aria-haspopup="false" aria-expanded="false">
                    <span class="account-user-avatar">
                        <!-- <img src="/images/users/avatar-1.jpg" alt="user-image" width="32" class="rounded-circle"> -->
                    </span>
                    <span class="d-lg-block d-none">
                        @php
                            $topbarIsShobha = \App\Support\AttendanceAccess::isShobha();
                            $topbarFraudAlert = null;
                            if ($topbarIsShobha) {
                                try {
                                    $topbarFraudAlert = \App\Support\AttendanceFraudAlerts::forUsers([(int) auth()->id()])[(int) auth()->id()] ?? null;
                                } catch (\Throwable $e) {
                                    $topbarFraudAlert = null;
                                }
                            }
                        @endphp
                        <h5 class="my-0 {{ $topbarFraudAlert ? 'fw-bold' : 'fw-normal' }}"@if($topbarFraudAlert) style="color:#000;font-weight:700;"@endif>{{ auth()->user()->name ?? '5Core' }}@if($topbarFraudAlert)
                            @include('attendance.partials.fraud-alert', ['alert' => null])
                            <span class="fraud-tri {{ $topbarFraudAlert['level'] }}" role="img"
                               title="{{ $topbarFraudAlert['tooltip'] }}"
                               aria-label="Fraud check: {{ $topbarFraudAlert['count'] }} open alert(s), {{ $topbarFraudAlert['level'] }} risk">
                                <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M12 2.2 23.2 21.6H0.8z" fill="#dc2626" stroke="#991b1b" stroke-width="1.2" stroke-linejoin="round"/>
                                    <rect x="11" y="8.6" width="2" height="6.4" rx="1" fill="#fff"/>
                                    <circle cx="12" cy="17.9" r="1.2" fill="#fff"/>
                                </svg>
                            </span>
                        @endif <i
                                class="ri-arrow-down-s-line d-none d-sm-inline-block align-middle"></i></h5>
                    </span>
                </a>
                <div class="dropdown-menu dropdown-menu-end dropdown-menu-animated profile-dropdown">
                    <!-- item-->
                    <div class=" dropdown-header noti-title">
                        <h6 class="text-overflow m-0">Welcome !</h6>
                    </div>

                    <a href="{{ route('profile') }}" class="dropdown-item">
                        <i class="ri-account-circle-line fs-18 align-middle me-1"></i>
                        <span>My Account</span>
                    </a>

                    <!-- <a href="pages-profile.html" class="dropdown-item">
                        <i class="ri-settings-4-line fs-18 align-middle me-1"></i>
                        <span>Settings</span>
                    </a>

                    <a href="pages-faq.html" class="dropdown-item">
                        <i class="ri-customer-service-2-line fs-18 align-middle me-1"></i>
                        <span>Support</span>
                    </a> -->

                    <!-- item-->
                    {{-- <a href="auth-lock-screen.html" class="dropdown-item">
                        <i class="ri-lock-password-line fs-18 align-middle me-1"></i>
                        <span>Lock Screen</span>
                    </a> --}}

                    <!-- item-->
                    <!-- Replace your current logout link with this form -->
                    <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                        @csrf
                    </form>

                    <a href="#" id="ts-logout-link" class="dropdown-item">
                        <i class="ri-logout-box-line fs-18 align-middle me-1"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </li>
        </ul>
    </div>
</div>
<!-- ========== Topbar End ========== -->
@auth
<script>
(function () {
    const btn = document.getElementById('chatTopbarBtn');
    if (!btn || window.__inventChatUnreadPoll) return;
    window.__inventChatUnreadPoll = true;
    function paintRed(r) {
        r = parseInt(r, 10) || 0;
        btn.classList.toggle('has-unread-red', r > 0);
        const el = btn.querySelector('.topbar-chat-btn__red');
        if (el) el.textContent = r > 99 ? '99+' : String(r);
    }
    function paint(n) {
        n = parseInt(n, 10) || 0;
        btn.classList.toggle('has-unread', n > 0);
        btn.dataset.chatUnread = String(n);
        const el = btn.querySelector('.topbar-chat-btn__count');
        if (el) el.textContent = n > 99 ? '99+' : String(n);
        btn.setAttribute('aria-label', n > 0 ? '5Core Chat — ' + n + ' chats' : '5Core Chat');
    }
    async function tick() {
        try {
            const res = await fetch(@json(route('chat.unread')), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!res.ok) return;
            const data = await res.json();
            paint(data.chats || 0);
            paintRed(data.unread_chats || 0);
        } catch (e) {}
    }
    setInterval(tick, 15000);
})();
</script>
@endauth
