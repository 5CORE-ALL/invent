<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Quotation form - {{ $container }}</title>
    <style>
        @page { margin: 0; }
        body { font-family: DejaVu Sans, sans-serif; color: #0f2a3d; font-size: 11px; margin: 0; }
        .hero { background: #0f2a3d; padding: 22px 36px 20px; color: #fff; }
        .logo { background: #fff; border-radius: 8px; padding: 6px 12px; display: inline-block; }
        .logo img { height: 34px; }
        .hero h1 { font-size: 20px; margin: 14px 0 3px; color: #fff; }
        .hero .sub { color: #b9cad6; font-size: 11px; }
        .wrap { padding: 22px 36px 30px; }
        .sec { font-size: 9px; text-transform: uppercase; letter-spacing: 1px; color: #64748b; font-weight: bold; margin: 0 0 8px; }
        table.grid { width: 100%; border-collapse: separate; border-spacing: 0 0; margin-bottom: 18px; }
        table.grid td { width: 33.33%; padding: 0 8px 10px 0; vertical-align: top; }
        .lbl { font-size: 9px; color: #64748b; font-weight: bold; margin-bottom: 3px; }
        .val { background: #f1f6f9; border: 1px solid #dfe8ee; border-radius: 6px; padding: 7px 9px; min-height: 14px; }
        .blank { background: #fff; border: 1px solid #9fb3c1; border-radius: 6px; padding: 7px 9px; height: 16px; }
        .full { padding-right: 8px; }
        .cta { margin-top: 6px; background: #e8f6f6; border: 1px solid #b5e0e0; border-radius: 8px; padding: 12px 14px; }
        .cta a { color: #1f7f81; font-weight: bold; word-break: break-all; }
        .foot { margin-top: 16px; font-size: 9px; color: #7b8b97; }
    </style>
</head>
<body>
<div class="hero">
    <span class="logo"><img src="{{ $logo }}" alt="5 Core"></span>
    <h1>Quotation form &mdash; {{ $container }}</h1>
    <div class="sub">Requested for {{ $forwarder }} &middot; {{ $date }}</div>
</div>

<div class="wrap">
    <div class="sec">Container details (provided by 5Core)</div>
    <table class="grid">
        <tr>
            <td><div class="lbl">Container</div><div class="val">{{ $container }}</div></td>
            <td><div class="lbl">Size</div><div class="val">{{ $d['container_size'] ?: '-' }}</div></td>
            <td><div class="lbl">Gross Weight</div><div class="val">{{ $d['gross_weight'] ?: '-' }}</div></td>
        </tr>
        <tr>
            <td><div class="lbl">Loading Port</div><div class="val">{{ $d['loading_port'] ?: '-' }}</div></td>
            <td><div class="lbl">Via Port</div><div class="val">{{ $d['via_port'] ?: '-' }}</div></td>
            <td><div class="lbl">Discharge Port</div><div class="val">{{ $d['discharge_port'] ?: '-' }}</div></td>
        </tr>
        <tr>
            <td colspan="3"><div class="lbl">Pickup Address</div><div class="val">{!! nl2br(e($d['pickup_address'] ?: '-')) !!}</div></td>
        </tr>
    </table>

    <div class="sec">Your quotation</div>
    <table class="grid">
        <tr>
            <td><div class="lbl">Forwarder</div><div class="val">{{ $forwarder }}</div></td>
            <td><div class="lbl">Shipping Line</div><div class="blank"></div></td>
            <td><div class="lbl">Est Dt Sail</div><div class="blank"></div></td>
        </tr>
        <tr>
            <td><div class="lbl">Pickup Charges ($)</div><div class="blank"></div></td>
            <td><div class="lbl">Freight ($)</div><div class="blank"></div></td>
            <td><div class="lbl">Total ($)</div><div class="blank"></div></td>
        </tr>
    </table>

    <div class="cta">
        Please submit your quotation online &mdash; it reaches us instantly:<br>
        <a href="{{ $url }}">{{ $url }}</a>
    </div>
    <div class="foot">Sent by 5Core &middot; sourcing1@5core.com</div>
</div>
</body>
</html>
