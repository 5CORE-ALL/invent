@extends('layouts.vertical', ['title' => $title ?? 'Wayfair — Connect', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
<style>
    .cred-ok { color: #0ab39c; }
    .cred-miss { color: #f06548; }
    .cred-mask { font-family: ui-monospace, monospace; font-size: 0.9rem; letter-spacing: 0.02em; }
</style>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <a href="{{ route('marketplace.manager.index') }}" class="text-muted small"><i class="ri-arrow-left-line"></i> Marketplace Manager</a>
        @include('marketplace._page-heading', ['slug' => 'wayfair', 'heading' => 'Wayfair — Connect', 'mb' => 'mb-3'])

        @include('marketplace.wayfair._nav', ['active' => 'connect'])

        @if($credentialsReady ?? false)
            <div class="alert alert-success d-flex align-items-start gap-2">
                <i class="ri-checkbox-circle-line fs-5 mt-1"></i>
                <div>
                    <strong>Credentials found in .env</strong>
                    <p class="mb-0 small"><code>WAYFAIR_CLIENT_ID</code> and <code>WAYFAIR_CLIENT_SECRET</code> are configured. Click <strong>Test connection</strong> to verify OAuth.</p>
                </div>
            </div>
        @else
            <div class="alert alert-warning">
                <strong>Setup required</strong> — add <code>WAYFAIR_CLIENT_ID</code> and <code>WAYFAIR_CLIENT_SECRET</code> to <code>.env</code>, then refresh.
            </div>
        @endif

        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Wayfair API Connection</h5>
                        @if($connected)
                            <span class="badge bg-success">Connected</span>
                        @else
                            <span class="badge bg-warning text-dark">Incomplete</span>
                        @endif
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">
                            OAuth client credentials from <code>.env</code> / <code>config/services.php</code> → <code>wayfair</code>.
                            Shopify B2C is the source shop for inventory sync and order import.
                        </p>

                        <table class="table table-sm table-bordered mb-4">
                            <tbody>
                                <tr>
                                    <th style="width: 200px;">Client ID</th>
                                    <td>
                                        @if($hasClientId ?? false)
                                            <span class="cred-ok"><i class="ri-check-line"></i> Set</span>
                                            <span class="cred-mask text-muted ms-2">{{ $maskedClientId }}</span>
                                        @else
                                            <span class="cred-miss">Missing — <code>WAYFAIR_CLIENT_ID</code></span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Client Secret</th>
                                    <td>
                                        @if($credentialsReady ?? false)
                                            <span class="cred-ok"><i class="ri-check-line"></i> Set</span>
                                            <span class="text-muted ms-2">(hidden)</span>
                                        @else
                                            <span class="cred-miss">Missing — <code>WAYFAIR_CLIENT_SECRET</code></span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Supplier ID</th>
                                    <td><code>{{ $supplierId ?? '—' }}</code> <span class="text-muted small">(<code>WAYFAIR_SUPPLIER_ID</code>)</span></td>
                                </tr>
                            </tbody>
                        </table>

                        <button type="button" class="btn btn-primary" id="btn-test-wf">
                            <i class="ri-plug-line me-1"></i> Test connection
                        </button>
                        <span id="wf-test-result" class="ms-2 small"></span>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Marketplace Manager stack</h5>
                        <ul class="small text-muted mb-3">
                            <li>SKU link map (wayfair_pricing_prices + listing statuses)</li>
                            <li>Inventory sync (Shopify → Wayfair local stock)</li>
                            <li>Order fetch (wayfair:daily POs) + Shopify import stub</li>
                            <li>Settings + scheduled jobs on <code>mm-wayfair</code></li>
                        </ul>
                        <a href="{{ route('marketplace.products', 'wayfair') }}" class="btn btn-outline-primary btn-sm">Open Listings</a>
                        <a href="{{ route('marketplace.orders', 'wayfair') }}" class="btn btn-outline-primary btn-sm">Open Orders</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('btn-test-wf')?.addEventListener('click', function () {
    var btn = this;
    var out = document.getElementById('wf-test-result');
    btn.disabled = true;
    out.textContent = 'Testing…';
    out.className = 'ms-2 small text-muted';
    fetch('{{ route('marketplace.manager.wayfair.test') }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        },
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        out.textContent = data.message || (data.success ? 'OK' : 'Failed');
        out.className = 'ms-2 small ' + (data.success ? 'text-success' : 'text-danger');
    })
    .catch(function () {
        out.textContent = 'Request failed.';
        out.className = 'ms-2 small text-danger';
    })
    .finally(function () { btn.disabled = false; });
});
</script>
@endsection
