<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.shared.title-meta', ['title' => $title ?? 'Yesterday'])
    @yield('css')
    @include('layouts.shared.head-css', ['mode' => $mode ?? '', 'demo' => $demo ?? ''])
    <style>
        html, body { height: 100%; }
        body.amm-embed {
            background: #f5f7fa !important;
            margin: 0;
            overflow: hidden;
        }
        body.amm-embed .amm-embed-wrap {
            height: 100vh;
            overflow: auto;
        }
        body.amm-embed .page-title-box,
        body.amm-embed .toast-container { display: none !important; }
        body.amm-embed .row { margin: 0; }
        body.amm-embed .container-fluid { padding: 0; }
        body.amm-embed #marketplace-table.tabulator .tabulator-header { top: 0; }
    </style>
</head>
<body class="amm-embed">
    <div class="amm-embed-wrap">
        @yield('content')
    </div>
    @include('layouts.shared.footer-scripts')
</body>
</html>
