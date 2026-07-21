@extends('layouts.vertical', ['title' => $title ?? 'Alibaba — Connect', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

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
        @include('marketplace._page-heading', ['slug' => 'alibaba', 'heading' => 'Alibaba — Connect', 'mb' => 'mb-3'])

        @include('marketplace.alibaba._nav', ['active' => 'connect'])

        @if($credentialsReady ?? false)
            <div class="alert alert-success d-flex align-items-start gap-2">
                <i class="ri-checkbox-circle-line fs-5 mt-1"></i>
                <div>
                    <strong>Credentials found in .env</strong>
                    <p class="mb-0 small">App key, secret, and access token are configured. Click <strong>Test connection</strong> below to verify the API responds (or wait — it runs automatically).</p>
                </div>
            </div>
        @else
            <div class="alert alert-warning">
                <strong>Setup required</strong> — add missing values to <code>.env</code>, then refresh this page.
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
                        <p class="text-muted mb-3">Credentials are read from <code>.env</code>. Shopify B2C is the source shop. OAuth uses <strong>Alibaba.com ICBU</strong> (<code>oauth.alibaba.com</code>), not AliExpress.</p>

                        <table class="table table-sm table-bordered mb-4">
                            <tbody>
                                <tr>
                                    <th style="width: 200px;">App Key</th>
                                    <td>
                                        @if($hasAppKey)
                                            <span class="cred-ok"><i class="ri-check-line"></i> Set</span>
                                            <span class="cred-mask text-muted ms-2">{{ $maskedAppKey }}</span>
                                        @else
                                            <span class="cred-miss">Missing — <code>ALIBABA_APP_KEY</code></span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>App Secret</th>
                                    <td>
                                        @if($hasAppSecret)
                                            <span class="cred-ok"><i class="ri-check-line"></i> Set</span>
                                            <span class="cred-mask text-muted ms-2">{{ $maskedAppSecret }}</span>
                                        @else
                                            <span class="cred-miss">Missing — <code>ALIBABA_APP_SECRET</code></span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Access Token</th>
                                    <td>
                                        @if($hasToken)
                                            <span class="cred-ok"><i class="ri-check-line"></i> Set</span>
                                            <span class="cred-mask text-muted ms-2">{{ $maskedAccessToken }}</span>
                                        @else
                                            <span class="cred-miss">Missing — <code>ALIBABA_ACCESS_TOKEN</code></span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Refresh Token</th>
                                    <td>
                                        @if($hasRefreshToken ?? false)
                                            <span class="cred-ok"><i class="ri-check-line"></i> Set</span>
                                            <span class="text-muted small ms-1">(optional, for token renewal)</span>
                                        @else
                                            <span class="text-muted">Not set — optional</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>API gateway</th>
                                    <td><code>{{ $gateway ?? 'sync' }}</code> → <code>{{ ($gateway ?? 'sync') === 'rest' ? ($restBase ?? 'https://api-sg.alibaba.com/rest') : ($apiBase ?? 'https://api-sg.alibaba.com/sync') }}</code></td>
                                </tr>
                                <tr>
                                    <th>OAuth redirect</th>
                                    <td><code>{{ $redirectUri ?? config('app.url') }}</code></td>
                                </tr>
                            </tbody>
                        </table>

                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <button type="button" class="btn btn-primary" id="btn-test-connection">
                                <i class="ri-link me-1"></i> Test connection
                            </button>
                            <a href="{{ $authorizeUrl }}" target="_blank" rel="noopener" class="btn btn-outline-secondary">
                                <i class="ri-key-2-line me-1"></i> Re-authorize (new token)
                            </a>
                        </div>

                        <div id="test-result" class="p-3 rounded border bg-light small">
                            @if($credentialsReady ?? false)
                                <span class="text-muted"><i class="ri-loader-4-line"></i> Running connection test…</span>
                            @else
                                <span class="text-muted">Complete .env credentials, then test connection.</span>
                            @endif
                        </div>
                    </div>
                </div>

                @if($credentialsReady ?? false)
                    <div class="card border-success">
                        <div class="card-header bg-success-subtle"><h5 class="card-title mb-0 text-success">Next steps</h5></div>
                        <div class="card-body">
                            <ol class="mb-0">
                                <li class="mb-2">Confirm <strong>Test connection</strong> shows success above.</li>
                                <li class="mb-2">Go to <a href="{{ route('marketplace.products', 'alibaba') }}">Listings</a> → <strong>Sync from Alibaba API</strong> to pull your products.</li>
                                <li class="mb-2">Open <a href="{{ route('marketplace.settings', 'alibaba') }}">Sync Settings</a> → enable inventory / order sync.</li>
                                <li>Order import jobs use the <code>alibaba</code> queue — started automatically by <code>scripts/cron-alibaba-worker.sh</code> (see deploy / crontab).</li>
                            </ol>
                        </div>
                    </div>
                @else
                    <div class="card">
                        <div class="card-header"><h5 class="card-title mb-0">Setup steps</h5></div>
                        <div class="card-body">
                            <ol class="mb-0">
                                <li class="mb-2">Add <code>ALIBABA_APP_KEY</code> and <code>ALIBABA_APP_SECRET</code> to <code>.env</code>.</li>
                                <li class="mb-2">In the Alibaba.com developer portal, set Callback URL to exactly <code>{{ $redirectUri ?? 'https://inventory.5coremanagement.com' }}</code>.</li>
                                <li class="mb-2">Click <strong>Re-authorize</strong>, log in on <strong>Alibaba.com</strong>, then run:<br>
                                    <code>php artisan alibaba:auth-url --exchange=CODE_FROM_REDIRECT --write-env</code></li>
                                <li class="mb-2">Click <strong>Test connection</strong>.</li>
                            </ol>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<script>
function runConnectionTest(auto) {
    var el = document.getElementById('test-result');
    var btn = document.getElementById('btn-test-connection');
    if (!el) return;
    if (!auto && btn) btn.disabled = true;
    el.innerHTML = '<span class="text-muted"><i class="ri-loader-4-line"></i> Testing Alibaba API…</span>';

    fetch('{{ route('marketplace.manager.alibaba.test') }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        },
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        if (data.success) {
            var extra = data.total_products != null ? ' <span class="text-muted">(' + data.total_products + ' product(s) reported)</span>' : '';
            el.innerHTML = '<span class="text-success fw-semibold"><i class="ri-checkbox-circle-line"></i> ' + (data.message || 'Connected') + '</span>' + extra;
            el.className = 'p-3 rounded border border-success bg-success-subtle small';
        } else {
            var html = '<span class="text-danger fw-semibold"><i class="ri-error-warning-line"></i> ' + (data.message || 'Failed') + '</span>';
            if (data.network_error && data.tips && data.tips.length) {
                html += '<ul class="mb-0 mt-2 ps-3">';
                data.tips.forEach(function (t) { html += '<li>' + t + '</li>'; });
                html += '</ul>';
            }
            el.innerHTML = html;
            el.className = 'p-3 rounded border border-danger bg-danger-subtle small';
        }
    })
    .catch(function () {
        el.innerHTML = '<span class="text-danger">Request failed. Check you are logged in and try again.</span>';
        el.className = 'p-3 rounded border border-danger bg-danger-subtle small';
    })
    .finally(function () {
        if (btn) btn.disabled = false;
    });
}

document.getElementById('btn-test-connection')?.addEventListener('click', function () {
    runConnectionTest(false);
});

@if($credentialsReady ?? false)
document.addEventListener('DOMContentLoaded', function () {
    runConnectionTest(true);
});
@endif
</script>
@endsection
