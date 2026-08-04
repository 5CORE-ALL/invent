@extends('layouts.vertical', ['title' => $title ?? 'TikTok Shop — Connect', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

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
        @include('marketplace._page-heading', ['slug' => 'tiktok', 'heading' => 'TikTok Shop — Connect', 'mb' => 'mb-3'])

        @include('marketplace.tiktok._nav', ['active' => 'connect'])

        @if(!empty($flashSuccess))
            <div class="alert alert-success">{{ $flashSuccess }}</div>
        @endif
        @if(!empty($flashError))
            <div class="alert alert-danger">{{ $flashError }}</div>
        @endif

        <div class="alert alert-primary">
            <strong>OAuth:</strong> click <strong>Connect with TikTok Shop</strong>, authorize in Partner Center, then land on
            <code>{{ $redirectUri ?: 'https://inventory.5coremanagement.com/tiktok/connect' }}</code>.
            Redirect URI must match the app setting exactly. Auth codes are single-use.
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">TikTok Shop API</h5>
                        @if($connected)
                            <span class="badge bg-success">Connected</span>
                        @elseif($credentialsReady ?? false)
                            <span class="badge bg-warning text-dark">App ready — authorize</span>
                        @else
                            <span class="badge bg-danger">Missing app keys</span>
                        @endif
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">
                            App credentials from <code>.env</code> (<code>TIKTOK_*</code>).
                            Access token is written by OAuth callback / exchange.
                        </p>

                        <table class="table table-sm table-bordered mb-3">
                            <tbody>
                                <tr>
                                    <th style="width: 180px;">Client Key</th>
                                    <td>
                                        @if($hasClientKey ?? false)
                                            <span class="cred-ok">Set</span>
                                            <span class="cred-mask text-muted ms-2">{{ $maskedClientKey }}</span>
                                        @else
                                            <span class="cred-miss">Missing — <code>TIKTOK_CLIENT_KEY</code></span>
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
                                            <span class="cred-miss">Missing — <code>TIKTOK_CLIENT_SECRET</code></span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Access Token</th>
                                    <td>
                                        @if($hasAccessToken ?? false)
                                            <span class="cred-ok">Set</span>
                                            <span class="cred-mask text-muted ms-2">{{ $maskedAccessToken }}</span>
                                        @else
                                            <span class="cred-miss">Missing — run OAuth</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Shop ID</th>
                                    <td>
                                        @if($hasShopId ?? false)
                                            <span class="cred-ok">Set</span>
                                            <span class="cred-mask text-muted ms-2">{{ $maskedShopId }}</span>
                                        @else
                                            <span class="text-muted">Not set yet</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Redirect URI</th>
                                    <td><code>{{ $redirectUri ?: '—' }}</code></td>
                                </tr>
                            </tbody>
                        </table>

                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            @if(!empty($authorizeUrl))
                                <a href="{{ $authorizeUrl }}" class="btn btn-success">
                                    <i class="ri-link me-1"></i> Connect with TikTok Shop (OAuth)
                                </a>
                            @endif
                            <a href="{{ $exchangeUrl }}" class="btn btn-outline-secondary" target="_blank" rel="noopener">
                                Paste callback / code
                            </a>
                            <button type="button" class="btn btn-primary" id="btn-test-tiktok">
                                <i class="ri-wifi-line me-1"></i> Test connection
                            </button>
                        </div>
                        <div id="tiktok-test-result" class="mt-3 small"></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Sync</h5>
                        <ul class="small text-muted mb-3">
                            <li>Products → <code>tiktok_products</code></li>
                            <li>Orders → <code>tiktok_orders</code></li>
                        </ul>
                        <button type="button" class="btn btn-primary btn-sm mb-1" id="btn-tt-sync-products">Sync products</button>
                        <button type="button" class="btn btn-primary btn-sm mb-1" id="btn-tt-fetch-orders">Fetch orders</button>
                        <div id="tiktok-sync-result" class="small text-muted mt-2"></div>
                        <hr>
                        <a href="{{ route('tiktok.pricing') }}" class="btn btn-outline-primary btn-sm mb-1">TikTok Shop Pricing</a>
                        <a href="{{ route('tiktok.listing.variation.verify') }}" class="btn btn-outline-primary btn-sm mb-1">Listing Variation Verify</a>
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
    const out = document.getElementById('tiktok-test-result');
    const syncOut = document.getElementById('tiktok-sync-result');
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

    document.getElementById('btn-test-tiktok')?.addEventListener('click', async function () {
        const btn = this;
        btn.disabled = true;
        out.innerHTML = '<span class="text-muted">Testing…</span>';
        try {
            const data = await post(@json(route('marketplace.manager.tiktok.test')));
            out.innerHTML = data.success
                ? '<span class="text-success">' + (data.message || 'OK') + '</span>'
                : '<span class="text-danger">' + (data.message || 'Failed') + '</span>';
        } catch (e) {
            out.innerHTML = '<span class="text-danger">' + e.message + '</span>';
        } finally {
            btn.disabled = false;
        }
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

    document.getElementById('btn-tt-sync-products')?.addEventListener('click', function () {
        runSync(this, @json(route('marketplace.manager.tiktok.refresh')), null, 'Syncing products');
    });
    document.getElementById('btn-tt-fetch-orders')?.addEventListener('click', function () {
        runSync(this, @json(route('marketplace.manager.tiktok.fetch.orders')), { days: 60 }, 'Fetching orders');
    });
})();
</script>
@endsection
