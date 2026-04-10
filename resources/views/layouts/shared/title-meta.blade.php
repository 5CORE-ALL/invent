<meta charset="utf-8" />
@php($__docBrand = config('app.name'))
<title>{{ $title }} | {{ $__docBrand }}</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta content="{{ $__docBrand }} — inventory, sales, purchases, accounts, and reports." name="description" />
<meta content="Techzaa" name="author" />
<meta name="csrf-token" content="{{ csrf_token() }}">
<script>window.__LaravelCsrfToken = @json(csrf_token());</script>
<?php
    /** @var string|null $favicon */
    /** @var string|null $faviconType */
    $faviconHref = $favicon ?? '/images/favicon.ico';
    $faviconMime = $faviconType ?? null;
    if ($faviconMime === null) {
        $path = parse_url($faviconHref, PHP_URL_PATH) ?: $faviconHref;
        $faviconMime = str_ends_with(strtolower((string) $path), '.svg') ? 'image/svg+xml' : 'image/x-icon';
    }
?>
<!-- App favicon (per-section overrides via $favicon / $faviconType) -->
<link rel="icon" href="{{ $faviconHref }}" type="{{ $faviconMime }}">
<link rel="shortcut icon" href="{{ $faviconHref }}">