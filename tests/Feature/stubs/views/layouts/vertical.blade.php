{{-- Test-only stand-in for the application layout (the real one queries MySQL-only tables). Same section names. --}}
<!doctype html><html><head><title>{{ $title ?? '' }}</title>@yield('css')</head>
<body>@yield('content')@yield('modal')@yield('script')</body></html>
