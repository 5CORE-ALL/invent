<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Quotation form — {{ $req->container_number }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #eef4f7; }
        .qf-card { max-width: 900px; margin: 28px auto; }
        .qf-card .card { border: 1px solid #e3ebf0 !important; border-radius: 22px; overflow: hidden; box-shadow: 0 24px 60px -34px rgba(15, 42, 61, .6) !important; }
        .qf-head {
            position: relative; color: #fff; padding: 26px 30px 28px !important; min-height: 190px;
            background: #0f2a3d url('{{ asset('images/quotations-cargo-ship.jpg') }}') right 56% / cover no-repeat;
        }
        .qf-head::before { content: ''; position: absolute; inset: 0;
            background: linear-gradient(95deg, rgba(10,32,48,.92) 0%, rgba(10,32,48,.7) 45%, rgba(10,32,48,.08) 100%); }
        .qf-head > * { position: relative; }
        .qf-logo { display: inline-flex; background: #fff; border-radius: 14px; padding: 9px 18px; box-shadow: 0 10px 24px -12px rgba(0,0,0,.5); }
        .qf-logo img { height: 38px; display: block; }
        .qf-head h5 { font-size: 1.5rem; margin-top: 16px; }
        .qf-card .card-body { padding: 26px 30px; }
        .qf-card .form-control { border-radius: 12px; border-color: #e3ebf0; }
        .qf-card .form-control:focus { border-color: #2a9d9f; box-shadow: 0 0 0 .22rem rgba(42,157,159,.16); }
        .qf-card .btn-primary, .qf-card .btn-success { border-radius: 999px; font-weight: 600; padding: 9px 26px; }
        .qf-card .btn-primary { background: #2a9d9f; border-color: #2a9d9f; }
        .qf-card .alert { border: 0; border-radius: 14px; }
        .qf-section { font-size: .8rem; text-transform: uppercase; letter-spacing: .06em; color: #64748b; font-weight: 700; }
        .qf-locked { background: #f8fafc !important; }
    </style>
</head>
<body>
<div class="qf-card px-2">
    <div class="card shadow-sm border-0">
        <div class="card-header qf-head border-0">
            <span class="qf-logo"><img src="{{ asset('images/5core_logo_tight.png') }}" alt="5 Core"></span>
            <h5 class="mb-1 fw-bold">Quotation form — {{ $req->container_number }}</h5>
            <div class="small opacity-75">Requested for {{ $req->forwarder_name }}</div>
        </div>
        <div class="card-body">
            @if ($saved)
                <div class="alert alert-success">Thank you — your quotation has been received.</div>
            @endif
            @if (! $open)
                <div class="alert alert-warning mb-0">This quotation request is now closed.</div>
            @else
                @if ($existing && ! $saved)
                    <div class="alert alert-info py-2">
                        You submitted a quote on {{ optional($req->submitted_at)->format('d M Y, H:i') }}.
                        You can update it below.
                    </div>
                @endif
                @if ($errors->any())
                    <div class="alert alert-danger py-2">
                        @foreach ($errors->all() as $e) <div>{{ $e }}</div> @endforeach
                    </div>
                @endif

                <div class="qf-section mb-2">Container details (provided by 5Core)</div>
                <div class="row g-3 mb-4">
                    @foreach ([
                        'Container' => $details['container_number'],
                        'Container Size' => $details['container_size'],
                        'Gross Weight' => $details['gross_weight'],
                        'Load Port' => $details['loading_port'],
                        'Via Port' => $details['via_port'],
                        'Discharge Port' => $details['discharge_port'],
                    ] as $label => $value)
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">{{ $label }}</label>
                            <input type="text" class="form-control qf-locked" value="{{ $value }}" readonly disabled>
                        </div>
                    @endforeach
                    <div class="col-12">
                        <label class="form-label fw-semibold small">Pickup Address</label>
                        <textarea class="form-control qf-locked" rows="2" readonly disabled>{{ $details['pickup_address'] }}</textarea>
                    </div>
                </div>

                <form method="POST" action="{{ route('container.quote.form.submit', $req->token) }}" autocomplete="off">
                    @csrf
                    <div class="qf-section mb-2">Your quotation</div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Forwarder</label>
                            <input type="text" class="form-control qf-locked" value="{{ $req->forwarder_name }}" readonly disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Shipping Line <span class="text-danger">*</span></label>
                            <input type="text" name="shipping_line" class="form-control" maxlength="255" required
                                value="{{ old('shipping_line', $existing->shipping_line ?? '') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Est Dt Sail <span class="text-danger">*</span></label>
                            <input type="date" name="est_dt_sail" class="form-control" required
                                value="{{ old('est_dt_sail', $existing && $existing->est_dt_sail ? substr((string) $existing->est_dt_sail, 0, 10) : '') }}">
                        </div>
                        <div class="col-md-6"></div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold small">Pickup Charges ($) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" name="pickup_charges" id="qfPickup" class="form-control" required
                                value="{{ old('pickup_charges', $existing->pickup_charges ?? '') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold small">Document Charges ($)</label>
                            <input type="number" step="0.01" min="0" name="document_charges" id="qfDocument" class="form-control"
                                value="{{ old('document_charges', $existing->document_charges ?? '') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold small">Freight ($) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" name="freight" id="qfFreight" class="form-control" required
                                value="{{ old('freight', $existing->freight ?? '') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold small">Total ($) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" name="total" id="qfTotal" class="form-control fw-bold" required
                                value="{{ old('total', $existing->total ?? '') }}">
                            <div class="form-text">Incl. of all charges</div>
                        </div>
                    </div>
                    <div class="mt-4 d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary px-4" style="background:#3bc0c3;border-color:#3bc0c3;">
                            {{ $existing ? 'Update quotation' : 'Submit quotation' }}
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</div>
<script>
    (function () {
        // Total is entered by the forwarder (incl. of all charges); the sum of pickup + freight
        // is only a minimum, so warn if the total is lower than that.
        var p = document.getElementById('qfPickup'), d = document.getElementById('qfDocument'), f = document.getElementById('qfFreight'), t = document.getElementById('qfTotal');
        if (!p || !d || !f || !t) return;
        function sum() { return Math.round(((parseFloat(p.value) || 0) + (parseFloat(d.value) || 0) + (parseFloat(f.value) || 0)) * 100) / 100; }
        function check() {
            var v = parseFloat(t.value);
            t.setCustomValidity(!isNaN(v) && v < sum() ? 'Total must include all charges (at least Pickup + Document + Freight).' : '');
        }
        // Total fills itself with Pickup + Freight; type over it if there are other charges (then it stays as typed).
        var manual = t.value !== '' && Math.round(parseFloat(t.value) * 100) / 100 !== sum();
        function auto() {
            if (manual) return;
            t.value = (p.value === '' && d.value === '' && f.value === '') ? '' : sum();
        }
        p.addEventListener('input', function () { auto(); check(); });
        d.addEventListener('input', function () { auto(); check(); });
        f.addEventListener('input', function () { auto(); check(); });
        t.addEventListener('input', function () {
            manual = t.value.trim() !== '';
            if (!manual) auto();
            check();
        });
        auto(); check();
    })();
</script>
</body>
</html>