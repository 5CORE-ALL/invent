<!-- PWA Install Button -->
<div id="pwa-install-container" style="display: none; position: fixed; bottom: 20px; right: 20px; z-index: 9999;">
    <button onclick="installPWA()" class="btn btn-primary btn-lg shadow-lg" style="border-radius: 50px; padding: 15px 30px;">
        <i class="mdi mdi-download me-2"></i>
        <strong>Install App</strong>
    </button>
</div>

<script>
    // Show install button when PWA is installable
    window.addEventListener('beforeinstallprompt', (e) => {
        document.getElementById('pwa-install-container').style.display = 'block';
    });
    
    // Hide button after install
    window.addEventListener('appinstalled', () => {
        document.getElementById('pwa-install-container').style.display = 'none';
        
        // Show success message
        const notification = document.createElement('div');
        notification.className = 'alert alert-success alert-dismissible position-fixed';
        notification.style.cssText = 'top: 20px; right: 20px; z-index: 10000; min-width: 300px;';
        notification.innerHTML = `
            <i class="mdi mdi-check-circle me-2"></i>
            <strong>App Installed Successfully!</strong>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.appendChild(notification);
        setTimeout(() => notification.remove(), 3000);
    });
</script>
