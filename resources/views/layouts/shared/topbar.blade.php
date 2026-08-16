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

        @include('layouts.shared.world-clocks-inline')

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
                transition: background 0.15s ease, transform 0.15s ease;
            }
            .topbar-dar-btn:hover {
                background: #1d4ed8;
                color: #fff;
                transform: translateY(-1px);
            }
            .topbar-dar-btn i { font-size: 0.9rem; }
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

            .topbar-activity-btn {
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
            .topbar-activity-btn:hover { transform: scale(1.08); }
            .topbar-activity-btn__icon {
                width: 100%;
                height: 100%;
                border-radius: 50%;
                object-fit: cover;
            }

        </style>

        <button type="button" id="activityTopbarBtn" class="topbar-activity-btn"
            title="Earn Monthly Increments" aria-label="Earn Monthly Increments">
            <img src="{{ asset('images/rupees-bag-icon.png') }}" alt="Earn Monthly Increments" class="topbar-activity-btn__icon">
        </button>

        <button type="button" id="darTopbarOpenBtn" class="topbar-dar-btn"
            title="Daily Activity Report (DAR)" aria-label="Daily Activity Report (DAR)">
            <i class="fas fa-clipboard-list"></i>
            <span class="topbar-dar-btn__label">DAR</span>
        </button>

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

            <li class="dropdown">
                <a class="nav-link dropdown-toggle arrow-none nav-user" data-bs-toggle="dropdown" href="#"
                    role="button" aria-haspopup="false" aria-expanded="false">
                    <span class="account-user-avatar">
                        <!-- <img src="/images/users/avatar-1.jpg" alt="user-image" width="32" class="rounded-circle"> -->
                    </span>
                    <span class="d-lg-block d-none">
                        <h5 class="my-0 fw-normal">5Core <i
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

                    <a href="#" onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
                        class="dropdown-item">
                        <i class="ri-logout-box-line fs-18 align-middle me-1"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </li>
        </ul>
    </div>
</div>
<!-- ========== Topbar End ========== -->
