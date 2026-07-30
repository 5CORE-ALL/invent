@extends('layouts.vertical', ['title' => $title ?? 'Shein — Connect', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

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
        @include('marketplace._page-heading', ['slug' => 'shein', 'heading' => 'Shein — Connect', 'mb' => 'mb-3'])

        @include('marketplace.shein._nav', ['active' => 'connect'])

        @if($credentialsReady ?? false)
            <div class="alert alert-success d-flex align-items-start gap-2">
                <i class="ri-checkbox-circle-line fs-5 mt-1"></i>
                <div>
                    <strong>Credentials found in .env</strong>
                    <p class="mb-0 small">Open Key ID and secret key are configured. Click <strong>Test connection</strong> to verify (product/query or full-detail).</p>
                </div>
            </div>
        @else
            <div class="alert alert-warning">
                <strong>Setup required</strong> — add <code>SHEIN_OPEN_KEY_ID</code> and <code>SHEIN_SECRET_KEY</code> to <code>.env</code>, then refresh.
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
                            Calls go to <code>{{ $apiBase ?? 'https://openapi.sheincorp.com' }}</code>.
                        </p>

                        <table class="table table-sm table-bordered mb-4">
                            <tbody>
                                <tr>
                                    <th style="width: 200px;">Open Key ID</th>
                                    <td>
                                        @if($hasOpenKeyId ?? false)
                                            <span class="cred-ok"><i class="ri-check-line"></i> Set</span>
                                            <span class="cred-mask text-muted ms-2">{{ $maskedOpenKeyId }}</span>
                                        @else
                                            <span class="cred-miss">Missing — <code>SHEIN_OPEN_KEY_ID</code></span>
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
                                            <span class="cred-miss">Missing — <code>SHEIN_SECRET_KEY</code></span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>API Base</th>
                                    <td><code>{{ $apiBase ?? 'https://openapi.sheincorp.com' }}</code></td>
                                </tr>
                            </tbody>
                        </table>

                        <button type="button" class="btn btn-primary" id="shein-test-connection">
                            <i class="ri-plink"></i> Test connection
                        </button>
                        <div id="shein-test-result" class="mt-3" style="display:none;"></div>
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
    const btn = document.getElementById('shein-test-connection');
    const box = document.getElementById('shein-test-result');
    if (!btn || !box) return;
    btn.addEventListener('click', async function () {
        btn.disabled = true;
        box.style.display = 'block';
        box.className = 'mt-3 alert alert-info';
        box.textContent = 'Testing…';
        try {
            const res = await fetch('{{ route('marketplace.manager.shein.test') }}', {
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
