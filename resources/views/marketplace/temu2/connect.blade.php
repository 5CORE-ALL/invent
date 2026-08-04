@extends('layouts.vertical', ['title' => $title ?? 'Temu 2 — Connect', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

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
        @include('marketplace._page-heading', ['slug' => 'temu2', 'heading' => 'Temu 2 — Connect', 'mb' => 'mb-3'])

        @include('marketplace.temu2._nav', ['active' => 'connect'])

        @if($credentialsReady ?? false)
            <div class="alert alert-success d-flex align-items-start gap-2">
                <i class="ri-checkbox-circle-line fs-5 mt-1"></i>
                <div>
                    <strong>Credentials found in .env</strong>
                    <p class="mb-0 small"><code>TEMU2_APP_KEY</code>, <code>TEMU2_SECRET_KEY</code>, and <code>TEMU2_ACCESS_TOKEN</code> are configured. Click <strong>Test connection</strong>, then <strong>Test price API</strong>.</p>
                </div>
            </div>
        @else
            <div class="alert alert-warning">
                <strong>Access token missing</strong> — App Key / Secret are set, but <code>TEMU2_ACCESS_TOKEN</code> is empty.
                After authorizing <strong>Inventory Temu 2</strong> in Seller Center, copy the access token and paste it below.
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
                            Credentials are read from <code>.env</code> / <code>config/services.php</code> → <code>temu2</code>.
                            Price fetch uses <code>bg.local.goods.sku.list.price.query</code> and needs <strong>Local Price Management</strong>.
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
                                            <span class="cred-miss">Missing — <code>TEMU2_APP_KEY</code></span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Secret Key</th>
                                    <td>
                                        @if($hasSecretKey ?? false)
                                            <span class="cred-ok"><i class="ri-check-line"></i> Set</span>
                                            <span class="cred-mask text-muted ms-2">{{ $maskedSecretKey }}</span>
                                        @else
                                            <span class="cred-miss">Missing — <code>TEMU2_SECRET_KEY</code></span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Access Token</th>
                                    <td>
                                        @if($hasAccessToken ?? false)
                                            <span class="cred-ok"><i class="ri-check-line"></i> Set</span>
                                            <span class="cred-mask text-muted ms-2">{{ $maskedAccessToken }}</span>
                                        @else
                                            <span class="cred-miss">Missing — <code>TEMU2_ACCESS_TOKEN</code></span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Open API URL</th>
                                    <td><code>{{ $apiBase ?? 'https://openapi-b-us.temu.com/openapi/router' }}</code></td>
                                </tr>
                            </tbody>
                        </table>

                        <div class="mb-3">
                            <label for="temu2-access-token" class="form-label">Paste access token from Seller Center</label>
                            <div class="input-group">
                                <input type="text" id="temu2-access-token" class="form-control" placeholder="Access token after authorizing Inventory Temu 2" autocomplete="off">
                                <button type="button" class="btn btn-success" id="btn-save-temu2-token">
                                    <i class="ri-save-line me-1"></i> Save token
                                </button>
                            </div>
                            <div class="form-text">Seller Center → System Management → Authorization Management → Inventory Temu 2 → copy Access Token after Submit.</div>
                            <div id="temu2-save-token-result" class="small mt-1"></div>
                        </div>

                        <button type="button" class="btn btn-primary" id="btn-test-temu">
                            <i class="ri-plug-line me-1"></i> Test connection
                        </button>
                        <button type="button" class="btn btn-outline-primary" id="btn-test-temu-price">
                            <i class="ri-money-dollar-circle-line me-1"></i> Test price API
                        </button>
                        <span id="temu-test-result" class="ms-2 small"></span>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Permissions for price</h5>
                        <p class="small text-muted mb-2">Your authorization screen already includes what we need:</p>
                        <ul class="small mb-3">
                            <li><strong>Local Price Management</strong> — required for <code>bg.local.goods.sku.list.price.query</code></li>
                            <li><strong>Local Product Management</strong> — goods / SKU list</li>
                            <li><strong>Local Basic Management</strong> — basic Open API access</li>
                        </ul>
                        <p class="small text-muted mb-3">
                            After saving the token: Test price API → then run
                            <code>php artisan app:fetch-temu2-metrics --only=price</code>
                        </p>
                        <a href="{{ route('marketplace.products', 'temu2') }}" class="btn btn-outline-primary btn-sm">Open Listings</a>
                        <a href="{{ route('marketplace.orders', 'temu2') }}" class="btn btn-outline-primary btn-sm">Open Orders</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function postJson(url, body) {
    return fetch(url, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
            'Content-Type': 'application/json',
        },
        body: body ? JSON.stringify(body) : undefined,
    }).then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); });
}

document.getElementById('btn-save-temu2-token')?.addEventListener('click', function () {
    var btn = this;
    var out = document.getElementById('temu2-save-token-result');
    var token = (document.getElementById('temu2-access-token')?.value || '').trim();
    if (!token) {
        out.textContent = 'Paste the access token first.';
        out.className = 'small mt-1 text-danger';
        return;
    }
    btn.disabled = true;
    out.textContent = 'Saving…';
    out.className = 'small mt-1 text-muted';
    postJson('{{ route('marketplace.manager.temu2.save.token') }}', { access_token: token })
        .then(function (res) {
            out.textContent = res.data.message || (res.data.success ? 'Saved' : 'Failed');
            out.className = 'small mt-1 ' + (res.data.success ? 'text-success' : 'text-danger');
            if (res.data.success) {
                setTimeout(function () { window.location.reload(); }, 700);
            }
        })
        .catch(function () {
            out.textContent = 'Save request failed.';
            out.className = 'small mt-1 text-danger';
        })
        .finally(function () { btn.disabled = false; });
});

document.getElementById('btn-test-temu')?.addEventListener('click', function () {
    var btn = this;
    var out = document.getElementById('temu-test-result');
    btn.disabled = true;
    out.textContent = 'Testing…';
    out.className = 'ms-2 small text-muted';
    postJson('{{ route('marketplace.manager.temu2.test') }}')
        .then(function (res) {
            out.textContent = res.data.message || (res.data.success ? 'OK' : 'Failed');
            out.className = 'ms-2 small ' + (res.data.success ? 'text-success' : 'text-danger');
        })
        .catch(function () {
            out.textContent = 'Request failed.';
            out.className = 'ms-2 small text-danger';
        })
        .finally(function () { btn.disabled = false; });
});

document.getElementById('btn-test-temu-price')?.addEventListener('click', function () {
    var btn = this;
    var out = document.getElementById('temu-test-result');
    btn.disabled = true;
    out.textContent = 'Testing price API…';
    out.className = 'ms-2 small text-muted';
    postJson('{{ route('marketplace.manager.temu2.test.price') }}')
        .then(function (res) {
            out.textContent = res.data.message || (res.data.success ? 'OK' : 'Failed');
            out.className = 'ms-2 small ' + (res.data.success ? 'text-success' : 'text-danger');
        })
        .catch(function () {
            out.textContent = 'Price test request failed.';
            out.className = 'ms-2 small text-danger';
        })
        .finally(function () { btn.disabled = false; });
});
</script>
@endsection
