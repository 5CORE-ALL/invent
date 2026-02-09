<!-- Mobile Header (Only visible on mobile) -->
<div class="mobile-header d-md-none">
    <div class="mobile-header-content">
        <button class="mobile-menu-btn" onclick="toggleMobileMenu()">
            <i class="mdi mdi-menu"></i>
        </button>
        
        <div class="mobile-logo">
            <h5 class="mb-0">{{ $title ?? 'Invent' }}</h5>
        </div>
        
        <button class="mobile-notification-btn">
            <i class="mdi mdi-bell-outline"></i>
            <span class="notification-badge">3</span>
        </button>
    </div>
</div>

<style>
.mobile-header {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    height: 56px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    z-index: 999;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.mobile-header-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 100%;
    padding: 0 15px;
}

.mobile-menu-btn,
.mobile-notification-btn {
    background: transparent;
    border: none;
    color: white;
    font-size: 24px;
    padding: 8px;
    cursor: pointer;
    position: relative;
}

.mobile-logo h5 {
    color: white;
    font-weight: 600;
    font-size: 18px;
}

.notification-badge {
    position: absolute;
    top: 5px;
    right: 5px;
    background: #ff4757;
    color: white;
    border-radius: 10px;
    padding: 2px 6px;
    font-size: 10px;
    font-weight: bold;
}

/* Add padding to content for fixed header */
@media (max-width: 767.98px) {
    .content-page .content {
        padding-top: 71px !important;
    }
}

/* PWA standalone mode */
@media all and (display-mode: standalone) {
    .mobile-header {
        padding-top: env(safe-area-inset-top);
        height: calc(56px + env(safe-area-inset-top));
    }
}
</style>
