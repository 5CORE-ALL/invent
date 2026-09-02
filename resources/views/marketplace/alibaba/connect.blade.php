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

        @if(!empty($flashSuccess))
            <div class="alert alert-success">{{ $flashSuccess }}</div>
        @endif
        @if(!empty($flashError))
            <div class="alert alert-danger">{{ $flashError }}</div>
        @endif

        @if($credentialsReady ?? false)
            <div class="alert alert-success d-flex align-items-start gap-2">
                <i class="ri-checkbox-circle-line fs-5 mt-1"></i>
                <div>
                    <strong>Credentials found in .env</strong>
                    <p class="mb-0 small">App key, secret, and access token are configured. Click <strong>Test connection</strong> to verify the Alibaba.com ICBU API.</p>
                </div>
            </div>
        @else
            <div class="alert alert-warning">
                <strong>Setup required</strong> — paste an access token below, or complete OAuth. App key and secret must already be in <code>.env</code>.
            </div>
        @endif

        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-3">
                    <div class="card-header"><h5 class="card-title mb-0">1) Paste access token</h5></div>
                    <div class="card-body">
                        <p class="text-muted small mb-2">
                            Fastest: <a href="https://openapi.alibaba.com" target="_blank" rel="noopener">openapi.alibaba.com</a>
                            → <strong>5Core Product Manager</strong> → <strong>Auth Management</strong> → authorize the Alibaba.com seller → copy <code>access_token</code>.
                            Paste it here. Saved as <code>ALIBABA_ACCESS_TOKEN</code>.
                        </p>
                        <div class="input-group mb-2">
                            <input type="text" class="form-control" id="alibaba-access-token-input" placeholder="Paste Alibaba access token" autocomplete="off">
                            <button type="button" class="btn btn-success" id="btn-save-alibaba-token">Save token</button>
                        </div>
                        <button type="button" class="btn btn-primary" id="btn-test-alibaba">
                            <i class="ri-wifi-line"></i> Test connection
                        </button>
                        <div id="alibaba-test-result" class="mt-3 small"></div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">2) OAuth (Alibaba.com ICBU)</h5>
                        @if($connected)
                            <span class="badge bg-success">Token present</span>
                        @else
                            <span class="badge bg-warning text-dark">No token</span>
                        @endif
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info small mb-3">
                            Set <strong>Callback URL</strong> in
                            <a href="https://openapi.alibaba.com" target="_blank" rel="noopener">openapi.alibaba.com</a>
                            → App Overview to exactly:
                            <br>
                            <code class="user-select-all">{{ $redirectUri }}</code>
                        </div>
                        <p class="text-muted small mb-3">
                            Then click <strong>Connect with Alibaba</strong>. After you authorize, Alibaba sends a <code>code</code> here and we exchange it for <code>ALIBABA_ACCESS_TOKEN</code>.
                            Use <code>auth.alibaba.com</code> (Alibaba.com), not <code>auth.1688.com</code>.
                        </p>

                        <table class="table table-sm table-bordered mb-3">
                            <tbody>
                                <tr>
                                    <th style="width: 180px;">App Key</th>
                                    <td>
                                        @if($hasAppKey)
                                            <span class="cred-ok">Set</span>
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
                                            <span class="cred-ok">Set</span>
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
                                            <span class="cred-ok">Set</span>
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
                                            <span class="cred-ok">Set</span>
                                        @else
                                            <span class="text-muted">Not set — optional</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>API gateway</th>
                                    <td><code>{{ $gateway ?? 'rest' }}</code> → <code>{{ ($gateway ?? 'rest') === 'rest' ? ($restBase ?? 'https://api-sg.alibaba.com/rest') : ($apiBase ?? 'https://openapi.alibaba.com/sync') }}</code></td>
                                </tr>
                            </tbody>
                        </table>

                        <div class="d-flex flex-wrap gap-2 mb-3">
                            @if(!empty($authorizeUrl))
                                <a href="{{ $authorizeUrl }}" class="btn btn-outline-success">
                                    Connect with Alibaba (OAuth)
                                </a>
                            @endif
                            <button type="button" class="btn btn-outline-danger" id="btn-revoke-alibaba">Revoke token</button>
                        </div>

                        <p class="text-muted small mb-2">If OAuth opened in a new tab and you landed on a URL with <code>?code=</code>, paste that code here:</p>
                        <div class="input-group">
                            <input type="text" class="form-control" id="alibaba-auth-code-input" placeholder="Paste authorization code" autocomplete="off">
                            <button type="button" class="btn btn-outline-secondary" id="btn-exchange-alibaba-code">Exchange code</button>
                        </div>
                    </div>
                </div>

                @if($credentialsReady ?? false)
                    <div class="card border-success mt-3">
                        <div class="card-header bg-success-subtle"><h5 class="card-title mb-0 text-success">Next steps</h5></div>
                        <div class="card-body">
                            <ol class="mb-0">
                                <li class="mb-2">Confirm <strong>Test connection</strong> shows success above.</li>
                                <li class="mb-2">Go to <a href="{{ route('marketplace.products', 'alibaba') }}">Listings</a> → <strong>Sync Alibaba link map</strong> to pull products.</li>
                                <li class="mb-2">Open <a href="{{ route('marketplace.settings', 'alibaba') }}">Sync Settings</a> → enable inventory / order sync.</li>
                                <li>Order import jobs use the <code>alibaba</code> queue.</li>
                            </ol>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
(function () {
    const out = document.getElementById('alibaba-test-result');
    const csrf = document.querySelector('meta[name="csrf-token"]').content;

    async function postJson(url, body) {
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
        if (!out) return;
        var extra = data.total_products != null ? ' (' + data.total_products + ' product(s) reported)' : '';
        out.innerHTML = data.success
            ? '<span class="text-success">' + (data.message || 'OK') + extra + '</span>'
            : '<span class="text-danger">' + (data.message || 'Failed') + '</span>';
    }

    document.getElementById('btn-test-alibaba')?.addEventListener('click', async function () {
        out.innerHTML = '<span class="text-muted">Testing…</span>';
        try { show(await postJson(@json(route('marketplace.manager.alibaba.test')))); }
        catch (e) { out.innerHTML = '<span class="text-danger">' + e.message + '</span>'; }
    });

    document.getElementById('btn-revoke-alibaba')?.addEventListener('click', async function () {
        out.innerHTML = '<span class="text-muted">Revoking…</span>';
        try {
            const data = await postJson(@json(route('marketplace.manager.alibaba.revoke')));
            show(data);
            if (data.success) setTimeout(function () { window.location.reload(); }, 700);
        } catch (e) { out.innerHTML = '<span class="text-danger">' + e.message + '</span>'; }
    });

    document.getElementById('btn-save-alibaba-token')?.addEventListener('click', async function () {
        const token = (document.getElementById('alibaba-access-token-input').value || '').trim();
        if (!token) {
            out.innerHTML = '<span class="text-danger">Paste a token first.</span>';
            return;
        }
        out.innerHTML = '<span class="text-muted">Saving…</span>';
        try {
            const data = await postJson(@json(route('marketplace.manager.alibaba.save.token')), { access_token: token });
            show(data);
            if (data.success) setTimeout(function () { window.location.reload(); }, 700);
        } catch (e) { out.innerHTML = '<span class="text-danger">' + e.message + '</span>'; }
    });

    document.getElementById('btn-exchange-alibaba-code')?.addEventListener('click', async function () {
        const code = (document.getElementById('alibaba-auth-code-input').value || '').trim();
        if (!code) {
            out.innerHTML = '<span class="text-danger">Paste an authorization code first.</span>';
            return;
        }
        out.innerHTML = '<span class="text-muted">Exchanging…</span>';
        try {
            const data = await postJson(@json(route('marketplace.manager.alibaba.exchange')), { code: code });
            show(data);
            if (data.success) setTimeout(function () { window.location.reload(); }, 700);
        } catch (e) { out.innerHTML = '<span class="text-danger">' + e.message + '</span>'; }
    });

    @if($credentialsReady ?? false)
    document.addEventListener('DOMContentLoaded', async function () {
        if (!out) return;
        out.innerHTML = '<span class="text-muted">Testing…</span>';
        try { show(await postJson(@json(route('marketplace.manager.alibaba.test')))); }
        catch (e) { out.innerHTML = '<span class="text-danger">' + e.message + '</span>'; }
    });
    @endif
})();
</script>
@endsection
