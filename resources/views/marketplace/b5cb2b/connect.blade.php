@extends('layouts.vertical', ['title' => $title ?? 'Business 5 Core (B2B) — Connect', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
<div class="row">
    <div class="col-12">
        <a href="{{ route('marketplace.manager.index') }}" class="text-muted small"><i class="ri-arrow-left-line"></i> Marketplace Manager</a>
        @include('marketplace._page-heading', ['slug' => 'b5cb2b', 'heading' => 'Business 5 Core (B2B) — Connect', 'mb' => 'mb-3'])
        @include('marketplace.b5cb2b._nav', ['active' => 'connect'])

        @if($credentialsReady ?? false)
            <div class="alert alert-success">Credentials found in <code>.env</code>. Click <strong>Test connection</strong> to verify the Laravel sync API.</div>
        @else
            <div class="alert alert-warning">Add <code>BUSINESS5CORE_B2B_API_URL</code> and <code>BUSINESS5CORE_B2B_API_KEY</code> to <code>.env</code>, then refresh.</div>
        @endif

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
                <p class="text-muted">Auth header is <code>X-Api-Key</code>. Calls go to <code>{{ $apiBase }}</code> (<code>/api/listings</code>, <code>/api/inventory</code>, <code>/api/orders</code>).</p>
                <table class="table table-sm table-bordered mb-3">
                    <tr>
                        <th style="width:220px">Store URL</th>
                        <td>
                            @if($hasUrl ?? false)
                                <span class="text-success">Set</span>
                                <code class="ms-2">{{ $apiBase }}</code>
                            @else
                                Missing — <code>BUSINESS5CORE_B2B_API_URL</code>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th>API Key</th>
                        <td>
                            @if($hasKey ?? false)
                                <span class="text-success">Set</span>
                                <span class="text-muted ms-2">{{ $maskedKey }}</span>
                            @else
                                Missing — <code>BUSINESS5CORE_B2B_API_KEY</code>
                            @endif
                        </td>
                    </tr>
                </table>
                <button type="button" class="btn btn-primary" id="b5c-test-btn">Test connection</button>
                <div class="small text-muted mt-2" id="b5c-test-status"></div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
$('#b5c-test-btn').on('click', function () {
    const $btn = $(this).prop('disabled', true);
    $('#b5c-test-status').text('Testing…');
    $.post("{{ route('marketplace.manager.b5cb2b.test') }}", {_token: '{{ csrf_token() }}'})
        .done(function (res) { $('#b5c-test-status').text(res.message || 'OK'); })
        .fail(function (xhr) { $('#b5c-test-status').text((xhr.responseJSON && xhr.responseJSON.message) || 'Connection failed'); })
        .always(function () { $btn.prop('disabled', false); });
});
</script>
@endsection
