@extends('layouts.vertical', ['title' => $title ?? 'Doba — Connect', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

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
        @include('marketplace._page-heading', ['slug' => 'doba', 'heading' => 'Doba — Connect', 'mb' => 'mb-3'])

        @include('marketplace.doba._nav', ['active' => 'connect'])

        @if($credentialsReady ?? false)
            <div class="alert alert-success d-flex align-items-start gap-2">
                <i class="ri-checkbox-circle-line fs-5 mt-1"></i>
                <div>
                    <strong>Credentials found in .env</strong>
                    <p class="mb-0 small"><code>DOBA_APP_KEY</code> and <code>DOBA_PRIVATE_KEY</code> are configured. Click <strong>Test connection</strong> to verify RSA2 signed OpenAPI access.</p>
                </div>
            </div>
        @else
            <div class="alert alert-warning">
                <strong>Setup required</strong> — add <code>DOBA_APP_KEY</code> and <code>DOBA_PRIVATE_KEY</code> to <code>.env</code>, then refresh.
            </div>
        @endif

        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Doba OpenAPI Connection</h5>
                        @if($connected)
                            <span class="badge bg-success">Connected</span>
                        @else
                            <span class="badge bg-warning text-dark">Incomplete</span>
                        @endif
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">
                            RSA2 signed requests to <code>https://openapi.doba.com/api</code> via <code>config/services.doba</code>.
                            Shopify B2C is the source shop for inventory sync and order import.
                        </p>

                        <table class="table table-sm table-bordered mb-4">
                            <tbody>
                                <tr>
                                    <th style="width: 200px;">App Key</th>
                                    <td>
                                        @if($hasAppKey ?? false)
                                            <span class="cred-ok"><i class="ri-check-line"></i> Set</span>
                                            <span class="cred-mask text-muted ms-2">{{ $maskedAppKey }}</span>
                                        @else
                                            <span class="cred-miss">Missing — <code>DOBA_APP_KEY</code></span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Private Key</th>
                                    <td>
                                        @if($hasPrivateKey ?? false)
                                            <span class="cred-ok"><i class="ri-check-line"></i> Set</span>
                                            <span class="text-muted ms-2">(hidden)</span>
                                        @else
                                            <span class="cred-miss">Missing — <code>DOBA_PRIVATE_KEY</code></span>
                                        @endif
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <button type="button" class="btn btn-primary" id="btn-test-doba">
                            <i class="ri-plug-line me-1"></i> Test connection
                        </button>
                        <span id="doba-test-result" class="ms-2 small"></span>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Marketplace Manager stack</h5>
                        <ul class="small text-muted mb-3">
                            <li>SKU link map (<code>doba_metrics</code> via goods/detail)</li>
                            <li>Inventory sync (Shopify → Doba + local stock)</li>
                            <li>Order fetch (<code>doba:daily</code>) + Shopify import stub</li>
                            <li>Settings + scheduled jobs on <code>mm-doba</code></li>
                        </ul>
                        <a href="{{ route('marketplace.products', 'doba') }}" class="btn btn-outline-primary btn-sm">Open Listings</a>
                        <a href="{{ route('marketplace.orders', 'doba') }}" class="btn btn-outline-primary btn-sm">Open Orders</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('btn-test-doba')?.addEventListener('click', function () {
    var btn = this;
    var out = document.getElementById('doba-test-result');
    btn.disabled = true;
    out.textContent = 'Testing…';
    out.className = 'ms-2 small text-muted';
    fetch('{{ route('marketplace.manager.doba.test') }}', {
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
