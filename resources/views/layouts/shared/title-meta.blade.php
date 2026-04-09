<meta charset="utf-8" />
@php($__docBrand = config('app.name'))
<title>{{ $title }} | {{ $__docBrand }}</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta content="{{ $__docBrand }} — inventory, sales, purchases, accounts, and reports." name="description" />
<meta content="Techzaa" name="author" />
<meta name="csrf-token" content="{{ csrf_token() }}">
<script>window.__LaravelCsrfToken = @json(csrf_token());</script>
<!-- App favicon -->
<link rel="shortcut icon" href="/images/favicon.ico">