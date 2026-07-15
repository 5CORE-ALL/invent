@extends('layouts.vertical', ['title' => $title ?? 'Newegg — Connect', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

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
        @include('marketplace._page-heading', ['slug' => 'newegg', 'heading' => 'Newegg — Connect', 'mb' => 'mb-3'])

        @include('marketplace.newegg._nav', ['active' => 'connect'])

        @if($credentialsReady ?? false)
            <div class="alert alert-success d-flex align-items-start gap-2">
                <i class="ri-checkbox-circle-line fs-5 mt-1"></i>
                <div>
                    <strong>Credentials found in .env</strong>
                    <p class="mb-0 small">Seller ID, API key, and secret key are configured. Click <strong>Test connection</strong> to verify (Service Status API).</p>
                </div>
            </div>
        @else
            <div class="alert alert-warning">
                <strong>Setup required</strong> — add <code>NEWEGG_SELLER_ID</code>, <code>NEWEGG_API_KEY</code>, and <code>NEWEGG_SECRET_KEY</code> to <code>.env</code>, then refresh.
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
                            Credentials are read from <code>.env</code>. Shopify B2C is the source shop.
                            Calls go to <code>{{ $apiBase ?? 'https://api.newegg.com' }}</code>.
                            The calling server IP must be whitelisted in the Newegg Seller Portal (Cloudflare).
                        </p>

                        <table class="table table-sm table-bordered mb-4">
                            <tbody>
                                <tr>
                                    <th style="width: 200px;">Seller ID</th>
                                    <td>
                                        @if($hasSellerId ?? false)
                                            <span class="cred-ok"><i class="ri-check-line"></i> Set</span>
                                            <span class="cred-mask text-muted ms-2">{{ $maskedSellerId }}</span>
                                        @else
                                            <span class="cred-miss">Missing — <code>NEWEGG_SELLER_ID</code></span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>API Key</th>
                                    <td>
                                        @if($hasApiKey ?? false)
                                            <span class="cred-ok"><i class="ri-check-line"></i> Set</span>
                                            <span class="cred-mask text-muted ms-2">{{ $maskedApiKey }}</span>
                                        @else
                                            <span class="cred-miss">Missing — <code>NEWEGG_API_KEY</code></span>
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
                                            <span class="cred-miss">Missing — <code>NEWEGG_SECRET_KEY</code></span>
                                        @endif
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <button type="button" class="btn btn-primary" id="btn-test-newegg">
                            <i class="ri-wifi-line"></i> Test connection
                        </button>
                        <div id="newegg-test-result" class="mt-3 small"></div>
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
    const btn = document.getElementById('btn-test-newegg');
    const out = document.getElementById('newegg-test-result');
    if (!btn) return;
    async function runTest() {
        out.innerHTML = '<span class="text-muted">Testing…</span>';
        try {
            const res = await fetch(@json(route('marketplace.manager.newegg.test')), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
            });
            const data = await res.json();
            if (data.success) {
                out.innerHTML = '<span class="text-success">' + (data.message || 'OK') + '</span>';
            } else {
                out.innerHTML = '<span class="text-danger">' + (data.message || 'Failed') + '</span>';
            }
        } catch (e) {
            out.innerHTML = '<span class="text-danger">' + e.message + '</span>';
        }
    }
    btn.addEventListener('click', runTest);
})();
</script>
@endsection
