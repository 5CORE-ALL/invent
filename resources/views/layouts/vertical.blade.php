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
</head>

<body>
    <!-- Begin page -->
    <div class="wrapper">

        @include('layouts.shared/topbar')
        @include('layouts.shared/left-sidebar')

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