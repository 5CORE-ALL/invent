<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $label }} compliance documents - {{ $parent }}</title>
    <style>
        @page { margin: 0; }
        body { font-family: DejaVu Sans, sans-serif; color: #0f2a3d; font-size: 11px; margin: 0; }
        .hero { background: #0f2a3d; padding: 22px 36px 20px; color: #fff; }
        .logo { background: #fff; border-radius: 8px; padding: 6px 12px; display: inline-block; }
        .logo img { height: 34px; }
        .hero h1 { font-size: 19px; margin: 14px 0 3px; color: #fff; }
        .hero .sub { color: #b9cad6; font-size: 11px; }
        .wrap { padding: 22px 36px 30px; }
        .sec { font-size: 9px; text-transform: uppercase; letter-spacing: 1px; color: #64748b; font-weight: bold; margin: 16px 0 8px; }
        table.grid { width: 100%; border-collapse: collapse; }
        table.grid td { padding: 6px 9px; border: 1px solid #dfe8ee; vertical-align: top; }
        table.grid td.k { width: 28%; background: #f1f6f9; font-weight: bold; color: #475569; }
        ul { margin: 0; padding-left: 18px; }
        li { margin-bottom: 4px; }
        .tick { font-size: 12px; }
        .img { margin-top: 10px; }
        .img img { max-width: 220px; max-height: 220px; border: 1px solid #dfe8ee; border-radius: 6px; }
        .foot { margin-top: 18px; font-size: 9px; color: #7b8b97; }
    </style>
</head>
<body>
<div class="hero">
    @if($logo)<span class="logo"><img src="{{ $logo }}" alt="5 Core"></span>@endif
    <h1>{{ $label }} compliance documents required</h1>
    <div class="sub">Requested from {{ $supplier !== '' ? $supplier : 'Supplier' }} &middot; {{ $date }}</div>
</div>

<div class="wrap">
    <table class="grid">
        <tr><td class="k">Parent SKU</td><td>{{ $parent }}</td></tr>
        <tr><td class="k">Supplier</td><td>{{ $supplier !== '' ? $supplier : '-' }}</td></tr>
        <tr><td class="k">Requested by</td><td>{{ $userName }}@if($userEmail !== '') &lt;{{ $userEmail }}&gt;@endif</td></tr>
        <tr><td class="k">Date</td><td>{{ $date }}</td></tr>
    </table>

    @if($image)
        <div class="img"><img src="{{ $image }}" alt="{{ $parent }}"></div>
    @endif

    <div class="sec">Documents required</div>
    <ul>
        @foreach($docs as $d)
            <li><span class="tick">&#9744;</span> {{ $d }}</li>
        @endforeach
    </ul>

    @if(count($skus))
        <div class="sec">SKUs ({{ count($skus) }})</div>
        <ul>
            @foreach(array_slice($skus, 0, 60) as $s)
                <li>{{ $s }}</li>
            @endforeach
            @if(count($skus) > 60)
                <li>... and {{ count($skus) - 60 }} more</li>
            @endif
        </ul>
    @endif

    @if($note !== '')
        <div class="sec">Note</div>
        <div>{!! nl2br(e($note)) !!}</div>
    @endif

    <div class="foot">Please reply to the email with the files attached (PDF or clear images). &mdash; 5Core</div>
</div>
</body>
</html>
