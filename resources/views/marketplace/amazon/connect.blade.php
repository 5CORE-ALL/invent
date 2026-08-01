@extends('layouts.vertical', ['title' => $title ?? 'Amazon — Connect', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

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
        @include('marketplace._page-heading', ['slug' => 'amazon', 'heading' => 'Amazon — Connect', 'mb' => 'mb-3'])

        @include('marketplace.amazon._nav', ['active' => 'connect'])

        @if($credentialsReady ?? false)
            <div class="alert alert-success d-flex align-items-start gap-2">
                <i class="ri-checkbox-circle-line fs-5 mt-1"></i>
                <div>
                    <strong>Credentials found in .env</strong>
                    <p class="mb-0 small">Amazon SP-API keys are configured. Click <strong>Test connection</strong> to verify the access token.</p>
                </div>
            </div>
        @else
            <div class="alert alert-warning">
                <strong>Setup required</strong> — add Amazon SP-API client id/secret, refresh token, and seller id to <code>.env</code>, then refresh.
            </div>
        @endif

        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">API Connection</h5>
                        @if($connected)
                            <span class="badge bg-success">Credentials OK</span>
                        @else
                            <span class="badge bg-warning text-dark">Incomplete</span>
                        @endif
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">
                            Credentials are read from <code>.env</code> / <code>config/services.php</code> → <code>amazon_sp</code>.
                            Orders are pulled via Selling Partner API Orders.
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
                                            <span class="cred-miss">Missing</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Client Secret</th>
                                    <td>
                                        @if($hasClientSecret ?? false)
                                            <span class="cred-ok"><i class="ri-check-line"></i> Set</span>
                                            <span class="cred-mask text-muted ms-2">{{ $maskedClientSecret }}</span>
                                        @else
                                            <span class="cred-miss">Missing</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Refresh Token</th>
                                    <td>
                                        @if($hasRefreshToken ?? false)
                                            <span class="cred-ok"><i class="ri-check-line"></i> Set</span>
                                            <span class="cred-mask text-muted ms-2">{{ $maskedRefreshToken }}</span>
                                        @else
                                            <span class="cred-miss">Missing</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Seller ID</th>
                                    <td>
                                        @if($hasSellerId ?? false)
                                            <span class="cred-ok"><i class="ri-check-line"></i> Set</span>
                                            <span class="cred-mask text-muted ms-2">{{ $maskedSellerId }}</span>
                                        @else
                                            <span class="cred-miss">Missing</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Marketplace ID</th>
                                    <td><code>{{ $marketplaceId ?? '—' }}</code></td>
                                </tr>
                                <tr>
                                    <th>API Base</th>
                                    <td><code>{{ $apiBase ?? 'https://sellingpartnerapi-na.amazon.com' }}</code></td>
                                </tr>
                            </tbody>
                        </table>

                        <button type="button" class="btn btn-primary" id="btn-test-amazon">
                            <i class="ri-plug-line me-1"></i> Test connection
                        </button>
                        <span id="amazon-test-result" class="ms-2 small"></span>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Orders hub</h5>
                        <p class="text-muted small mb-3">
                            Marketplace Manager for Amazon focuses on <strong>order fetch + visibility</strong>
                            (same SP-API source as <code>/amazon/daily-sales</code>).
                        </p>
                        <a href="{{ route('marketplace.orders', 'amazon') }}" class="btn btn-outline-primary btn-sm">
                            Open Orders
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('btn-test-amazon')?.addEventListener('click', function () {
    var btn = this;
    var out = document.getElementById('amazon-test-result');
    btn.disabled = true;
    out.textContent = 'Testing…';
    out.className = 'ms-2 small text-muted';
    fetch('{{ route('marketplace.manager.amazon.test') }}', {
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
