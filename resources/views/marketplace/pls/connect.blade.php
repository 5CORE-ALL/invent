@extends('layouts.vertical', ['title' => $title ?? 'Shopify PLS — Connect', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

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
        @include('marketplace._page-heading', ['slug' => 'pls', 'heading' => 'Shopify PLS — Connect', 'mb' => 'mb-3'])

        @include('marketplace.pls._nav', ['active' => 'connect'])

        @if(!empty($flashSuccess))
            <div class="alert alert-success">{{ $flashSuccess }}</div>
        @endif
        @if(!empty($flashError))
            <div class="alert alert-danger">{{ $flashError }}</div>
        @endif

        <div class="alert alert-primary">
            <strong>Shopify custom app:</strong> PLS uses <code>client_credentials</code> tokens (expire ~24h).
            Prefer <code>PROLIGHTSOUNDS_SHOPIFY_CLIENT_ID</code> + <code>CLIENT_SECRET</code>; Refresh token caches a new access token automatically.
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">ProLightSounds Shopify API</h5>
                        @if($connected)
                            <span class="badge bg-success">Connected</span>
                        @elseif($credentialsReady ?? false)
                            <span class="badge bg-warning text-dark">Creds set — test / refresh</span>
                        @else
                            <span class="badge bg-danger">Missing credentials</span>
                        @endif
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">
                            Config: <code>.env</code> → <code>PROLIGHTSOUNDS_SHOPIFY_*</code> /
                            <code>config/services.php</code> → <code>prolightsounds</code>.
                        </p>

                        <table class="table table-sm table-bordered mb-3">
                            <tbody>
                                <tr>
                                    <th style="width: 200px;">Shop domain</th>
                                    <td>
                                        @if($hasDomain ?? false)
                                            <span class="cred-ok">Set</span>
                                            <code class="ms-2">{{ $domain }}</code>
                                        @else
                                            <span class="cred-miss">Missing — <code>PROLIGHTSOUNDS_SHOPIFY_DOMAIN</code></span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Client ID</th>
                                    <td>
                                        @if($hasClientId ?? false)
                                            <span class="cred-ok">Set</span>
                                            <span class="cred-mask text-muted ms-2">{{ $maskedClientId }}</span>
                                        @else
                                            <span class="cred-miss">Missing — <code>PROLIGHTSOUNDS_SHOPIFY_CLIENT_ID</code></span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Client Secret</th>
                                    <td>
                                        @if($hasClientSecret ?? false)
                                            <span class="cred-ok">Set</span>
                                            <span class="cred-mask text-muted ms-2">{{ $maskedClientSecret }}</span>
                                        @else
                                            <span class="cred-miss">Missing — <code>PROLIGHTSOUNDS_SHOPIFY_CLIENT_SECRET</code></span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Static access token</th>
                                    <td>
                                        @if($hasStaticToken ?? false)
                                            <span class="cred-ok">Set (fallback)</span>
                                            <span class="cred-mask text-muted ms-2">{{ $maskedStaticToken }}</span>
                                        @else
                                            <span class="text-muted">Optional if client credentials are set</span>
                                        @endif
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            @if($hasClientCreds ?? false)
                                <button type="button" class="btn btn-success" id="btn-refresh-pls">
                                    <i class="ri-refresh-line me-1"></i> Refresh token
                                </button>
                            @endif
                            <button type="button" class="btn btn-primary" id="btn-test-pls">
                                <i class="ri-wifi-line me-1"></i> Test connection
                            </button>
                        </div>
                        <div id="pls-test-result" class="mt-3 small"></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Sync</h5>
                        <ul class="small text-muted mb-3">
                            <li>Catalog → <code>shopify_catalog_*</code></li>
                            <li>Pricing → <code>pls_products</code></li>
                            <li>Sales → <code>pls_sales</code></li>
                        </ul>
                        <button type="button" class="btn btn-primary btn-sm mb-1" id="btn-pls-sync-catalog">Sync catalog</button>
                        <button type="button" class="btn btn-primary btn-sm mb-1" id="btn-pls-refresh-pricing">Refresh pricing</button>
                        <button type="button" class="btn btn-outline-primary btn-sm mb-1" id="btn-pls-fetch-sales">Fetch sales</button>
                        <div id="pls-sync-result" class="small text-muted mt-2"></div>
                        <hr>
                        <a href="{{ route('pls.pricing') }}" class="btn btn-outline-primary btn-sm mb-1">PLS Pricing</a>
                        <a href="{{ route('pls.listing.variation.verify') }}" class="btn btn-outline-primary btn-sm mb-1">Listing Variation Verify</a>
                        <a href="{{ route('listing.pls') }}" class="btn btn-outline-secondary btn-sm mb-1">Listing PLS</a>
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
    const out = document.getElementById('pls-test-result');
    const syncOut = document.getElementById('pls-sync-result');
    const csrf = document.querySelector('meta[name="csrf-token"]').content;

    async function post(url, body) {
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: body ? JSON.stringify(body) : undefined,
        });
        return res.json();
    }

    function show(data) {
        out.innerHTML = data.success
            ? '<span class="text-success">' + (data.message || 'OK') + '</span>'
            : '<span class="text-danger">' + (data.message || 'Failed') + '</span>';
    }

    document.getElementById('btn-test-pls')?.addEventListener('click', async function () {
        const btn = this;
        btn.disabled = true;
        out.innerHTML = '<span class="text-muted">Testing…</span>';
        try { show(await post(@json(route('marketplace.manager.pls.test')))); }
        catch (e) { out.innerHTML = '<span class="text-danger">' + e.message + '</span>'; }
        finally { btn.disabled = false; }
    });

    document.getElementById('btn-refresh-pls')?.addEventListener('click', async function () {
        const btn = this;
        btn.disabled = true;
        out.innerHTML = '<span class="text-muted">Refreshing token…</span>';
        try { show(await post(@json(route('marketplace.manager.pls.refresh')))); }
        catch (e) { out.innerHTML = '<span class="text-danger">' + e.message + '</span>'; }
        finally { btn.disabled = false; }
    });

    async function runSync(btn, url, body, label) {
        btn.disabled = true;
        syncOut.className = 'small text-muted mt-2';
        syncOut.textContent = label + '…';
        try {
            const data = await post(url, body);
            syncOut.className = 'small mt-2 ' + (data.success ? 'text-success' : 'text-danger');
            syncOut.textContent = data.message || (data.success ? 'Done' : 'Failed');
        } catch (e) {
            syncOut.className = 'small text-danger mt-2';
            syncOut.textContent = e.message || 'Request failed';
        } finally {
            btn.disabled = false;
        }
    }

    document.getElementById('btn-pls-sync-catalog')?.addEventListener('click', function () {
        runSync(this, @json(route('marketplace.manager.pls.refresh.products')), null, 'Syncing catalog');
    });
    document.getElementById('btn-pls-refresh-pricing')?.addEventListener('click', function () {
        runSync(this, @json(route('marketplace.manager.pls.refresh.pricing')), null, 'Refreshing pricing');
    });
    document.getElementById('btn-pls-fetch-sales')?.addEventListener('click', function () {
        runSync(this, @json(route('marketplace.manager.pls.fetch.orders')), { days: 90 }, 'Fetching sales');
    });
})();
</script>
@endsection
