@extends('layouts.vertical', ['title' => $title ?? 'Faire — Connect', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

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
        @include('marketplace._page-heading', ['slug' => 'faire', 'heading' => 'Faire — Connect', 'mb' => 'mb-3'])

        @include('marketplace.faire._nav', ['active' => 'connect'])

        @if(!empty($flashSuccess))
            <div class="alert alert-success">{{ $flashSuccess }}</div>
        @endif
        @if(!empty($flashError))
            <div class="alert alert-danger">{{ $flashError }}</div>
        @endif

        <div class="alert alert-primary">
            <strong>Recommended:</strong> paste your Faire brand <strong>API access token</strong> below (Developer Portal), then Test connection.
            OAuth “Connect” often returns browser <strong>400</strong> if the app is already installed or portal scopes don’t match.
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-3">
                    <div class="card-header"><h5 class="card-title mb-0">1) Paste access token</h5></div>
                    <div class="card-body">
                        <p class="text-muted small mb-2">
                            Faire Developer Portal → your app / brand API token.
                            Saved as <code>FAIRE_ACCESS_TOKEN</code> with <code>FAIRE_AUTH_MODE=api_key</code>.
                        </p>
                        <div class="input-group mb-2">
                            <input type="text" class="form-control" id="faire-access-token-input" placeholder="Paste Faire access token" autocomplete="off">
                            <button type="button" class="btn btn-success" id="btn-save-faire-token">Save token</button>
                        </div>
                        <button type="button" class="btn btn-primary" id="btn-test-faire">
                            <i class="ri-wifi-line"></i> Test connection
                        </button>
                        <div id="faire-test-result" class="mt-3 small"></div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">2) OAuth (optional)</h5>
                        @if($connected)
                            <span class="badge bg-success">Token present</span>
                        @else
                            <span class="badge bg-warning text-dark">No token</span>
                        @endif
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">
                            App ID / secret from .env. Redirect must be exactly
                            <code>{{ $redirectUrl ?: 'http://127.0.0.1:8000/faire/callback' }}</code>.
                            Scopes are taken from the Faire Developer Portal app (not hard-coded).
                        </p>

                        <table class="table table-sm table-bordered mb-3">
                            <tbody>
                                <tr>
                                    <th style="width: 180px;">App ID</th>
                                    <td>
                                        @if($hasAppId ?? false)
                                            <span class="cred-ok">Set</span>
                                            <span class="cred-mask text-muted ms-2">{{ $maskedAppId }}</span>
                                        @else
                                            <span class="cred-miss">Missing</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>App Secret</th>
                                    <td>
                                        @if($hasAppSecret ?? false)
                                            <span class="cred-ok">Set</span>
                                            <span class="cred-mask text-muted ms-2">{{ $maskedAppSecret }}</span>
                                        @else
                                            <span class="cred-miss">Missing</span>
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
                                            <span class="cred-miss">Missing</span>
                                        @endif
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <div class="d-flex flex-wrap gap-2">
                            @if(!empty($authorizeUrl))
                                <a href="{{ $authorizeUrl }}" class="btn btn-outline-success" target="_blank" rel="noopener">
                                    Connect with Faire (OAuth)
                                </a>
                            @endif
                            <button type="button" class="btn btn-outline-danger" id="btn-revoke-faire">Revoke token</button>
                        </div>
                        <p class="text-muted small mt-2 mb-0">
                            If OAuth shows 400: uninstall this app in Faire Brand Portal → Settings → Apps, then retry — or use paste token above.
                        </p>
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
    const out = document.getElementById('faire-test-result');
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
        out.innerHTML = data.success
            ? '<span class="text-success">' + (data.message || 'OK') + '</span>'
            : '<span class="text-danger">' + (data.message || 'Failed') + '</span>';
    }

    document.getElementById('btn-test-faire')?.addEventListener('click', async function () {
        out.innerHTML = '<span class="text-muted">Testing…</span>';
        try { show(await postJson(@json(route('marketplace.manager.faire.test')))); }
        catch (e) { out.innerHTML = '<span class="text-danger">' + e.message + '</span>'; }
    });

    document.getElementById('btn-revoke-faire')?.addEventListener('click', async function () {
        out.innerHTML = '<span class="text-muted">Revoking…</span>';
        try {
            const data = await postJson(@json(route('marketplace.manager.faire.revoke')));
            show(data);
            if (data.success) setTimeout(function () { window.location.reload(); }, 700);
        } catch (e) { out.innerHTML = '<span class="text-danger">' + e.message + '</span>'; }
    });

    document.getElementById('btn-save-faire-token')?.addEventListener('click', async function () {
        const token = (document.getElementById('faire-access-token-input').value || '').trim();
        if (!token) {
            out.innerHTML = '<span class="text-danger">Paste a token first.</span>';
            return;
        }
        out.innerHTML = '<span class="text-muted">Saving…</span>';
        try {
            const data = await postJson(@json(route('marketplace.manager.faire.save.token')), { access_token: token });
            show(data);
            if (data.success) setTimeout(function () { window.location.reload(); }, 700);
        } catch (e) { out.innerHTML = '<span class="text-danger">' + e.message + '</span>'; }
    });
})();
</script>
@endsection
