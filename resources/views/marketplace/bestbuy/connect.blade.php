@extends('layouts.vertical', ['title' => $title ?? 'Best Buy — Connect', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

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
        @include('marketplace._page-heading', ['slug' => 'bestbuy', 'heading' => 'Best Buy — Connect', 'mb' => 'mb-3'])

        @include('marketplace.bestbuy._nav', ['active' => 'connect'])

        @if($credentialsReady ?? false)
            <div class="alert alert-success d-flex align-items-start gap-2">
                <i class="ri-checkbox-circle-line fs-5 mt-1"></i>
                <div>
                    <strong>Credentials found in .env</strong>
                    <p class="mb-0 small">Mirakl Connect OAuth (<code>MACY_CLIENT_ID</code> / <code>MACY_CLIENT_SECRET</code>) and/or <code>BESTBUY_MCM_API_KEY</code> is configured. Click <strong>Test connection</strong> to verify Mirakl Connect for channel <code>bestbuyusa</code>.</p>
                </div>
            </div>
        @else
            <div class="alert alert-warning">
                <strong>Setup required</strong> — add Macy Mirakl Connect OAuth (<code>MACY_CLIENT_ID</code> + <code>MACY_CLIENT_SECRET</code>) or <code>BESTBUY_MCM_API_KEY</code> to <code>.env</code>, then refresh.
            </div>
        @endif

        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Mirakl Connect (Best Buy USA)</h5>
                        @if($connected)
                            <span class="badge bg-success">Connected</span>
                        @else
                            <span class="badge bg-warning text-dark">Incomplete</span>
                        @endif
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">
                            Best Buy uses Mirakl Connect channel <code>bestbuyusa</code> with shared Macy OAuth credentials.
                            Shopify B2C is the source shop for inventory sync and order import.
                        </p>

                        <table class="table table-sm table-bordered mb-4">
                            <tbody>
                                <tr>
                                    <th style="width: 200px;">Mirakl Connect OAuth</th>
                                    <td>
                                        @if($hasMiraklOAuth ?? false)
                                            <span class="cred-ok"><i class="ri-check-line"></i> Set</span>
                                            <span class="cred-mask text-muted ms-2">client_id {{ $maskedMacyClientId ?? '—' }}</span>
                                        @else
                                            <span class="cred-miss">Missing — <code>MACY_CLIENT_ID</code> + <code>MACY_CLIENT_SECRET</code></span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Best Buy MCM Key</th>
                                    <td>
                                        @if($hasMcmApiKey ?? false)
                                            <span class="cred-ok"><i class="ri-check-line"></i> Set</span>
                                            <span class="cred-mask text-muted ms-2">{{ $maskedMcmApiKey }}</span>
                                        @else
                                            <span class="text-muted">Optional — <code>BESTBUY_MCM_API_KEY</code></span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>MCM Base URL</th>
                                    <td><code>{{ $mcmBaseUrl ?? 'https://bestbuyus-prod.mirakl.net' }}</code></td>
                                </tr>
                            </tbody>
                        </table>

                        <button type="button" class="btn btn-primary" id="btn-test-bb">
                            <i class="ri-plug-line me-1"></i> Test connection
                        </button>
                        <span id="bb-test-result" class="ms-2 small"></span>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Marketplace Manager stack</h5>
                        <ul class="small text-muted mb-3">
                            <li>SKU link map (Mirakl Connect → bestbuy_usa_products)</li>
                            <li>Inventory sync (Shopify → Best Buy)</li>
                            <li>Order fetch (mirakl:daily) + Shopify import</li>
                            <li>Settings + scheduled jobs on <code>mm-bestbuy</code></li>
                        </ul>
                        <a href="{{ route('marketplace.products', 'bestbuy') }}" class="btn btn-outline-primary btn-sm">Open Listings</a>
                        <a href="{{ route('marketplace.orders', 'bestbuy') }}" class="btn btn-outline-primary btn-sm">Open Orders</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('btn-test-bb')?.addEventListener('click', function () {
    var btn = this;
    var out = document.getElementById('bb-test-result');
    btn.disabled = true;
    out.textContent = 'Testing…';
    out.className = 'ms-2 small text-muted';
    fetch('{{ route('marketplace.manager.bestbuy.test') }}', {
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
