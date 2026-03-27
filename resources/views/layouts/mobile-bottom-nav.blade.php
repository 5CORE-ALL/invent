<!-- Mobile Bottom Navigation (Only visible on mobile) -->
<nav class="mobile-bottom-nav d-md-none">
    <a href="{{ url('/') }}" class="nav-item {{ request()->is('/') ? 'active' : '' }}">
        <i class="mdi mdi-view-dashboard"></i>
        <span>Home</span>
    </a>
    <a href="{{ route('tasks.index') }}" class="nav-item {{ request()->is('tasks*') ? 'active' : '' }}">
        <i class="mdi mdi-format-list-checks"></i>
        <span>Tasks</span>
    </a>
    <a href="{{ route('tasks.create') }}" class="nav-item nav-item-center {{ request()->is('tasks/create') ? 'active' : '' }}">
        <i class="mdi mdi-plus-circle"></i>
        <span>Create</span>
    </a>
    <a href="#" class="nav-item">
        <i class="mdi mdi-bell"></i>
        <span>Alerts</span>
    </a>
    <a href="#" class="nav-item" onclick="toggleMobileMenu()">
        <i class="mdi mdi-menu"></i>
        <span>Menu</span>
    </a>
</nav>
<div id="mobile-sidebar-overlay" class="d-md-none" onclick="toggleMobileMenu(true)"></div>

<style>
/* Mobile Bottom Navigation */
.mobile-bottom-nav {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    height: 65px;
    background: #ffffff;
    border-top: 1px solid #e3e6f0;
    display: flex;
    justify-content: space-around;
    align-items: center;
    z-index: 1000;
    box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
    padding: 0;
}

.mobile-bottom-nav .nav-item {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    color: #8094ae;
    transition: all 0.3s ease;
    padding: 8px 0;
    position: relative;
}

.mobile-bottom-nav .nav-item i {
    font-size: 24px;
    margin-bottom: 4px;
    transition: all 0.3s ease;
}

.mobile-bottom-nav .nav-item span {
    font-size: 11px;
    font-weight: 500;
}

.mobile-bottom-nav .nav-item.active {
    color: #667eea;
}

.mobile-bottom-nav .nav-item.active i {
    transform: scale(1.1);
}

/* Center create button */
.mobile-bottom-nav .nav-item-center {
    color: #667eea;
}

.mobile-bottom-nav .nav-item-center i {
    font-size: 32px;
    color: #667eea;
}

/* Add padding to content when bottom nav is visible */
@media (max-width: 767.98px) {
    .content-page {
        padding-bottom: 80px !important;
    }
    
    /* Reuse full desktop sidebar as mobile drawer */
    .leftside-menu {
        display: block !important;
        position: fixed !important;
        top: 0 !important;
        left: -100vw !important;
        width: 88vw !important;
        max-width: 340px !important;
        height: 100dvh !important;
        z-index: 1045 !important;
        transition: left 0.25s ease !important;
        box-shadow: none !important;
        transform: none !important;
        visibility: hidden !important;
        opacity: 0 !important;
        pointer-events: none !important;
        display: flex !important;
        flex-direction: column !important;
    }

    body.mobile-menu-open .leftside-menu {
        left: 0 !important;
        margin-left: 0 !important;
        opacity: 1 !important;
        visibility: visible !important;
        pointer-events: auto !important;
        box-shadow: 6px 0 20px rgba(0, 0, 0, 0.25) !important;
    }

    body.mobile-menu-open {
        overflow: hidden;
    }

    #mobile-sidebar-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.45);
        z-index: 1040;
    }

    body.mobile-menu-open #mobile-sidebar-overlay {
        display: block;
    }

    .leftside-menu #leftside-menu-container {
        flex: 1 1 auto !important;
        min-height: 0 !important;
        height: auto !important;
    }
    
    /* Make content full width on mobile */
    .content-page {
        margin-left: 0 !important;
    }
}

/* PWA standalone mode detection */
@media all and (display-mode: standalone) {
    .mobile-bottom-nav {
        padding-bottom: env(safe-area-inset-bottom);
        height: calc(65px + env(safe-area-inset-bottom));
    }
}
</style>

<script>
function toggleMobileMenu(forceClose = false) {
    if (window.innerWidth >= 768) {
        return;
    }

    const body = document.body;
    const html = document.documentElement;
    const isOpen = body.classList.contains('mobile-menu-open');

    if (forceClose || isOpen) {
        body.classList.remove('mobile-menu-open');
        html.classList.remove('sidebar-enable');
        const themeBackdrop = document.getElementById('custom-backdrop');
        if (themeBackdrop) {
            themeBackdrop.remove();
        }
        body.style.overflow = null;
        body.style.paddingRight = null;
        return;
    }

    // Force expanded sidebar mode so menu labels are shown.
    html.setAttribute('data-sidenav-size', 'full');
    body.classList.add('mobile-menu-open');
    html.classList.add('sidebar-enable');
}

// Auto close mobile drawer when leaving mobile viewport.
window.addEventListener('resize', function () {
    if (window.innerWidth >= 768) {
        document.body.classList.remove('mobile-menu-open');
        document.documentElement.classList.remove('sidebar-enable');
    }
});

// Intercept topbar hamburger on mobile to avoid theme conflict.
document.addEventListener('DOMContentLoaded', function () {
    const topbarBtn = document.querySelector('.button-toggle-menu');
    if (!topbarBtn) {
        return;
    }

    topbarBtn.addEventListener('click', function (event) {
        if (window.innerWidth < 768) {
            event.preventDefault();
            event.stopImmediatePropagation();
            toggleMobileMenu();
        }
    }, true);
});
</script>
