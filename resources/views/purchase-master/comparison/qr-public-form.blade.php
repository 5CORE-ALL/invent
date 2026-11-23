<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Quotation form — {{ $req->sku }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #eef4f7; }
        .qf-card { max-width: 860px; margin: 28px auto; }
        .qf-card .card { border: 1px solid #e3ebf0 !important; border-radius: 22px; overflow: hidden; box-shadow: 0 24px 60px -34px rgba(15, 42, 61, .6) !important; }
        .qf-head { color: #fff; padding: 24px 30px !important; background: #0f2a3d; }
        .qf-logo { display: inline-flex; background: #fff; border-radius: 14px; padding: 9px 18px; }
        .qf-logo img { height: 38px; display: block; }
        .qf-card .card-body { padding: 26px 30px; }
        .qf-card .form-control { border-radius: 12px; border-color: #e3ebf0; }
        .qf-card .form-control:focus { border-color: #2a9d9f; box-shadow: 0 0 0 .22rem rgba(42,157,159,.16); }
        .qf-card .btn-primary { border-radius: 999px; font-weight: 600; padding: 9px 26px; background: #2a9d9f; border-color: #2a9d9f; }
        .qf-card .alert { border: 0; border-radius: 14px; }
        .qf-section { font-size: .8rem; text-transform: uppercase; letter-spacing: .06em; color: #64748b; font-weight: 700; }
        .qf-item-img { width: 100%; max-width: 240px; aspect-ratio: 1 / 1; object-fit: contain; background: #fff; border: 1px solid #e3ebf0; border-radius: 16px; padding: 8px; }
        .qf-ref { color: #64748b; font-size: .85rem; }
    </style>
</head>
<body>
<div class="qf-card px-2">
    <div class="card shadow-sm border-0">
        <div class="card-header qf-head border-0">
            <span class="qf-logo"><img src="{{ asset('images/5core_logo_tight.png') }}" alt="5 Core"></span>
            <h5 class="mt-3 mb-1 fw-bold">Quotation form — {{ $req->sku }}</h5>
            <div class="small opacity-75">Requested for {{ $req->supplier_name }}</div>
        </div>
        <div class="card-body">
            @if ($saved)
                <div class="alert alert-success">Thank you — your quotation has been received.</div>
            @endif
            @if (! $open)
                <div class="alert alert-warning mb-0">This quotation request is now closed.</div>
            @else
                @if ($req->submitted_at && ! $saved)
                    <div class="alert alert-info py-2">
                        You submitted a quote on {{ $req->submitted_at->format('d M Y, H:i') }}. You can update it below.
                    </div>
                @endif
                @if ($errors->any())
                    <div class="alert alert-danger py-2">
                        @foreach ($errors->all() as $e) <div>{{ $e }}</div> @endforeach
                    </div>
                @endif

                {{-- Part 1: the item (no specifications are shown to the supplier) --}}
                <div class="qf-section mb-2">1. Product</div>
                <div class="mb-4">
                    @if ($image)
                        <img class="qf-item-img" src="{{ $image }}" alt="Product {{ $req->sku }}">
                    @else
                        <div class="qf-item-img d-flex align-items-center justify-content-center text-muted">No image</div>
                    @endif
                    <div class="fw-semibold mt-2">{{ $req->sku }}</div>
                </div>

                {{-- Part 2: the supplier's quotation --}}
                <form method="POST" action="{{ route('comparison.qr.form.submit', $req->token) }}">
                    @csrf
                    <div class="qf-section mb-2">2. Your quotation</div>
                    <table class="table table-bordered align-middle mb-4">
                        <thead class="table-light"><tr><th style="width:40%">Field</th><th>Your quote</th></tr></thead>
                        <tbody>
                        @foreach ($rows as $i => $r)
                            <tr>
                                <td class="fw-semibold">
                                    {{ $r['label'] }}
                                    <input type="hidden" name="labels[{{ $i }}]" value="{{ $r['label'] }}">
                                </td>
                                <td>
                                    <input type="text" class="form-control" name="values[{{ $i }}]" maxlength="500"
                                        value="{{ old('values.'.$i, $r['current']) }}" autocomplete="off" aria-label="{{ $r['label'] }}">
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    <button type="submit" class="btn btn-primary">Submit quotation</button>
                </form>
            @endif
        </div>
    </div>
</div>
</body>
</html>
