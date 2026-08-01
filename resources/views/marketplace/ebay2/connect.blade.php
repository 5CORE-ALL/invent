@extends('layouts.vertical', ['title' => $title ?? 'eBay 2 — Connect', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

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
        @include('marketplace._page-heading', ['slug' => 'ebay2', 'heading' => 'eBay 2 — Connect', 'mb' => 'mb-3'])

        @include('marketplace.ebay2._nav', ['active' => 'connect'])

        @if($credentialsReady ?? false)
            <div class="alert alert-success d-flex align-items-start gap-2">
                <i class="ri-checkbox-circle-line fs-5 mt-1"></i>
                <div>
                    <strong>Credentials found in .env</strong>
                    <p class="mb-0 small">EBAY_2_* keys are configured. Click <strong>Test connection</strong> to verify (bearer token + GetItem).</p>
                </div>
            </div>
        @else
            <div class="alert alert-warning">
                <strong>Setup required</strong> — add <code>EBAY_2_APP_ID</code>, <code>EBAY_2_CERT_ID</code>, <code>EBAY_2_DEV_ID</code>, and <code>EBAY_2_REFRESH_TOKEN</code> to <code>.env</code>, then refresh.
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
                            Credentials are read from <code>.env</code> / <code>config/services.php</code> → <code>ebay2</code>.
                            Shopify B2C is the source shop. Trading + Sell Fulfillment call <code>{{ $apiBase ?? 'https://api.ebay.com' }}</code>.
                        </p>

                        <table class="table table-sm table-bordered mb-4">
                            <tbody>
                                <tr>
                                    <th style="width: 200px;">App ID</th>
                                    <td>
                                        @if($hasAppId ?? false)
                                            <span class="cred-ok"><i class="ri-check-line"></i> Set</span>
                                            <span class="cred-mask text-muted ms-2">{{ $maskedAppId }}</span>
                                        @else
                                            <span class="cred-miss">Missing — <code>EBAY_2_APP_ID</code></span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Cert ID</th>
                                    <td>
                                        @if($hasCertId ?? false)
                                            <span class="cred-ok"><i class="ri-check-line"></i> Set</span>
                                            <span class="cred-mask text-muted ms-2">{{ $maskedCertId }}</span>
                                        @else
                                            <span class="cred-miss">Missing — <code>EBAY_2_CERT_ID</code></span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Dev ID</th>
                                    <td>
                                        @if($hasDevId ?? false)
                                            <span class="cred-ok"><i class="ri-check-line"></i> Set</span>
                                            <span class="cred-mask text-muted ms-2">{{ $maskedDevId }}</span>
                                        @else
                                            <span class="cred-miss">Missing — <code>EBAY_2_DEV_ID</code></span>
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
                                            <span class="cred-miss">Missing — <code>EBAY_2_REFRESH_TOKEN</code></span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>API Base</th>
                                    <td><code>{{ $apiBase ?? 'https://api.ebay.com' }}</code></td>
                                </tr>
                            </tbody>
                        </table>

                        <button type="button" class="btn btn-primary" id="ebay2-test-connection">
                            <i class="ri-plink"></i> Test connection
                        </button>
                        <div id="ebay2-test-result" class="mt-3" style="display:none;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
(function () {
    const btn = document.getElementById('ebay2-test-connection');
    const box = document.getElementById('ebay2-test-result');
    if (!btn || !box) return;
    btn.addEventListener('click', async function () {
        btn.disabled = true;
        box.style.display = 'block';
        box.className = 'mt-3 alert alert-info';
        box.textContent = 'Testing…';
        try {
            const res = await fetch('{{ route('marketplace.manager.ebay2.test') }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'Accept': 'application/json',
                },
            });
            const data = await res.json();
            box.className = 'mt-3 alert ' + (data.success ? 'alert-success' : 'alert-danger');
            box.textContent = data.message || (data.success ? 'OK' : 'Failed');
        } catch (e) {
            box.className = 'mt-3 alert alert-danger';
            box.textContent = e.message || 'Request failed';
        } finally {
            btn.disabled = false;
        }
    });
})();
</script>
@endsection
