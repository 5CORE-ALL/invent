<!DOCTYPE html>
<html lang="en" data-sidenav-size="{{ $sidenav ?? 'default' }}" data-layout-mode="{{ $layoutMode ?? 'fluid' }}"
    data-layout-position="{{ $position ?? 'fixed' }}" data-menu-color="{{ $menuColor ?? 'dark' }}"
    data-topbar-color="{{ $topbarColor ?? 'light' }}">

<head>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://code.highcharts.com/highcharts.js"></script>
    <script src="https://code.highcharts.com/modules/exporting.js"></script>
    <script src="https://code.highcharts.com/modules/export-data.js"></script>
    <script src="https://code.highcharts.com/modules/accessibility.js"></script>
    <script src="https://code.highcharts.com/themes/adaptive.js"></script>
    @include('layouts.shared/title-meta', ['title' => $title])
    @yield('css')
    @include('layouts.shared/head-css', ['mode' => $mode ?? '', 'demo' => $demo ?? ''])
    
    <!-- PWA Meta Tags -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#667eea">
    <link rel="apple-touch-icon" href="/icon-192.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Invent">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
</head>

<body>
    <!-- Mobile Splash Screen -->
    @include('layouts.mobile-splash')
    
    <!-- Begin page -->
    <div class="wrapper">

        <!-- Desktop Navigation -->
        @include('layouts.shared/topbar')
        @include('layouts.shared/left-sidebar')
        
        <!-- Mobile Header -->
        @include('layouts.mobile-header')

        <div class="content-page">
            <div class="content">

                <!-- Start Content-->
                <div class="container-fluid">
                    @yield('content')
                </div>
                <!-- container -->

            </div>
            <!-- content -->

            @include('layouts.shared/footer')
        </div>

    </div>
    <!-- END wrapper -->

    @yield('modal')

    @include('layouts.shared/right-sidebar')
    
    <!-- Mobile Bottom Navigation -->
    @include('layouts.mobile-bottom-nav')

    @include('layouts.shared/footer-scripts')

    @vite(['resources/js/layout.js', 'resources/js/main.js'])

    @include('components.ai-chat-widget')
    
    <!-- PWA Service Worker Registration with Error Handling -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                // Unregister old service workers first
                navigator.serviceWorker.getRegistrations().then(function(registrations) {
                    for(let registration of registrations) {
                        if (registration.active && registration.active.scriptURL.includes('/sw.js')) {
                            console.log('Updating existing service worker...');
                        }
                    }
                });
                
                // Register new service worker
                navigator.serviceWorker.register('/sw.js')
                    .then(function(registration) {
                        console.log('✓ ServiceWorker registered successfully');
                        
                        // Update on new version
                        registration.addEventListener('updatefound', function() {
                            console.log('ServiceWorker update found!');
                        });
                    })
                    .catch(function(error) {
                        console.warn('⚠️ ServiceWorker registration failed (non-critical):', error);
                        // Don't block app if service worker fails
                    });
            });
        } else {
            console.log('Service Worker not supported in this browser');
        }

        // PWA Install Prompt
        let deferredPrompt;
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            console.log('✓ PWA Install prompt ready');
        });

        // Function to trigger PWA install
        function installPWA() {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then((choiceResult) => {
                    if (choiceResult.outcome === 'accepted') {
                        console.log('✓ User accepted PWA install');
                    }
                    deferredPrompt = null;
                });
            }
        }
    </script>
</body>

</html>
