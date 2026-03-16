<!-- Mobile Splash Screen (Shows on app launch) -->
<div id="mobile-splash" class="d-md-none">
    <div class="splash-content">
        <div class="splash-logo">
            <i class="mdi mdi-checkbox-marked-circle-outline"></i>
        </div>
        <h3>Invent</h3>
        <p>Task Manager</p>
        <div class="splash-loader">
            <div class="loader-spinner"></div>
        </div>
    </div>
</div>

<style>
#mobile-splash {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    z-index: 99999;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.splash-content {
    text-align: center;
}

.splash-logo i {
    font-size: 80px;
    color: white;
    animation: pulse 2s infinite;
}

.splash-content h3 {
    font-size: 32px;
    font-weight: bold;
    margin: 20px 0 5px 0;
    color: white;
}

.splash-content p {
    font-size: 16px;
    color: rgba(255,255,255,0.8);
    margin-bottom: 30px;
}

.loader-spinner {
    width: 40px;
    height: 40px;
    margin: 0 auto;
    border: 4px solid rgba(255,255,255,0.3);
    border-top-color: white;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes pulse {
    0%, 100% {
        transform: scale(1);
        opacity: 1;
    }
    50% {
        transform: scale(1.1);
        opacity: 0.8;
    }
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>

<script>
// Hide splash after page loads
window.addEventListener('load', function() {
    setTimeout(function() {
        const splash = document.getElementById('mobile-splash');
        if (splash) {
            splash.style.opacity = '0';
            splash.style.transition = 'opacity 0.5s';
            setTimeout(function() {
                splash.style.display = 'none';
            }, 500);
        }
    }, 1000); // Show for 1 second
});
</script>
