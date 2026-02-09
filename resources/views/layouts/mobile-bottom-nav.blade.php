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
    
    /* Hide desktop sidebar on mobile */
    .leftside-menu {
        display: none !important;
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
function toggleMobileMenu() {
    // Create mobile menu overlay
    const existingMenu = document.getElementById('mobile-menu-overlay');
    if (existingMenu) {
        existingMenu.remove();
        return;
    }
    
    const menuOverlay = document.createElement('div');
    menuOverlay.id = 'mobile-menu-overlay';
    menuOverlay.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.5);
        z-index: 9998;
        animation: fadeIn 0.3s;
    `;
    
    const menuPanel = document.createElement('div');
    menuPanel.style.cssText = `
        position: fixed;
        right: 0;
        top: 0;
        bottom: 0;
        width: 280px;
        background: white;
        z-index: 9999;
        animation: slideInRight 0.3s;
        overflow-y: auto;
        box-shadow: -2px 0 10px rgba(0,0,0,0.2);
    `;
    
    menuPanel.innerHTML = `
        <div style="padding: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h5 style="margin: 0;">Menu</h5>
                <button onclick="toggleMobileMenu()" style="background: transparent; border: none; color: white; font-size: 24px;">
                    <i class="mdi mdi-close"></i>
                </button>
            </div>
            <div style="margin-top: 10px; font-size: 14px;">{{ Auth::user()->name ?? 'User' }}</div>
        </div>
        <div style="padding: 15px;">
            <a href="{{ url('/') }}" style="display: block; padding: 12px; color: #333; text-decoration: none; border-bottom: 1px solid #eee;">
                <i class="mdi mdi-view-dashboard me-2"></i> Dashboard
            </a>
            <a href="{{ route('tasks.index') }}" style="display: block; padding: 12px; color: #333; text-decoration: none; border-bottom: 1px solid #eee;">
                <i class="mdi mdi-format-list-checks me-2"></i> All Tasks
            </a>
            <a href="{{ route('tasks.create') }}" style="display: block; padding: 12px; color: #333; text-decoration: none; border-bottom: 1px solid #eee;">
                <i class="mdi mdi-plus-circle me-2"></i> Create Task
            </a>
            <a href="#" style="display: block; padding: 12px; color: #333; text-decoration: none; border-bottom: 1px solid #eee;">
                <i class="mdi mdi-cog me-2"></i> Settings
            </a>
            <a href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();" 
               style="display: block; padding: 12px; color: #dc3545; text-decoration: none;">
                <i class="mdi mdi-logout me-2"></i> Logout
            </a>
            <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                @csrf
            </form>
        </div>
    `;
    
    menuOverlay.onclick = toggleMobileMenu;
    document.body.appendChild(menuOverlay);
    document.body.appendChild(menuPanel);
}

// Add animations
const style = document.createElement('style');
style.textContent = `
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    @keyframes slideInRight {
        from { transform: translateX(100%); }
        to { transform: translateX(0); }
    }
`;
document.head.appendChild(style);
</script>
