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
    
    <!-- Mobile App Styles -->
    <style>
        /* Mobile App-Like Styles */
        @media (max-width: 767.98px) {
            /* Remove default margins and padding */
            body {
                margin: 0;
                padding: 0;
                -webkit-tap-highlight-color: transparent;
                -webkit-touch-callout: none;
                -webkit-user-select: none;
                user-select: none;
            }
            
            /* Make inputs selectable */
            input, textarea, select {
                -webkit-user-select: text;
                user-select: text;
            }
            
            /* Hide desktop topbar on mobile */
            .navbar-custom {
                display: none !important;
            }
            
            /* Full screen content */
            .content-page .content {
                padding: 15px 10px 80px 10px !important;
            }
            
            /* Mobile-friendly cards */
            .card {
                border-radius: 15px !important;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1) !important;
                margin-bottom: 15px !important;
            }
            
            /* Mobile-friendly buttons */
            .btn {
                min-height: 44px;
                padding: 12px 20px;
                font-size: 16px;
                border-radius: 12px;
            }
            
            .btn-lg {
                min-height: 50px;
                padding: 15px 25px;
                font-size: 18px;
            }
            
            /* Mobile-friendly form controls */
            .form-control, .form-select {
                min-height: 48px;
                font-size: 16px;
                border-radius: 10px;
                padding: 12px 15px;
            }
            
            .form-control-lg, .form-select-lg {
                min-height: 54px;
                font-size: 18px;
            }
            
            /* Mobile page headers */
            .page-title-box {
                padding: 15px 10px !important;
                margin-bottom: 15px !important;
            }
            
            .page-title {
                font-size: 22px !important;
                margin: 0 !important;
            }
            
            /* Hide breadcrumbs on mobile */
            .breadcrumb {
                display: none !important;
            }
            
            /* Container adjustments */
            .container-fluid {
                padding-left: 0 !important;
                padding-right: 0 !important;
            }
            
            /* Table responsive on mobile */
            .table-responsive {
                border-radius: 12px;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            /* Mobile pull-to-refresh indicator */
            .wrapper {
                overscroll-behavior-y: contain;
            }
            
            /* Smooth scrolling */
            * {
                -webkit-overflow-scrolling: touch;
            }
            
            /* Safe area for notched phones */
            .mobile-bottom-nav {
                padding-bottom: env(safe-area-inset-bottom);
            }
            
            /* Card headers mobile style */
            .card-header {
                border-radius: 15px 15px 0 0 !important;
                padding: 15px !important;
            }
            
            /* Alert boxes mobile */
            .alert {
                border-radius: 12px !important;
                padding: 12px 15px !important;
            }
            
            /* Modal mobile adjustments */
            .modal-content {
                border-radius: 20px 20px 0 0 !important;
            }
            
            .modal.fade .modal-dialog {
                transform: translate(0, 100%);
                transition: transform 0.3s ease-out;
            }
            
            .modal.show .modal-dialog {
                transform: translate(0, 0);
            }
        }
        
        /* PWA Standalone Mode (when installed) */
        @media all and (display-mode: standalone) {
            /* Add top padding for status bar */
            body {
                padding-top: env(safe-area-inset-top);
            }
            
            /* Adjust content */
            .content-page .content {
                padding-top: calc(15px + env(safe-area-inset-top));
            }
        }
        
        /* Landscape mode on mobile */
        @media (max-width: 767.98px) and (orientation: landscape) {
            .mobile-bottom-nav {
                height: 50px;
            }
            
            .mobile-bottom-nav .nav-item i {
                font-size: 20px;
                margin-bottom: 2px;
            }
            
            .mobile-bottom-nav .nav-item span {
                font-size: 10px;
            }
        }
    </style>
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

    <!-- PWA Service Worker Registration -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('/sw.js')
                    .then(function(registration) {
                        console.log('✓ ServiceWorker registered:', registration.scope);
                    })
                    .catch(function(error) {
                        console.log('✗ ServiceWorker registration failed:', error);
                    });
            });
        }

        // PWA Install Prompt
        let deferredPrompt;
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            console.log('✓ PWA Install prompt ready');
            
            // Show custom install button/banner if needed
            // You can add a button here to trigger the install
        });

        // Function to trigger PWA install (call this from a button)
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