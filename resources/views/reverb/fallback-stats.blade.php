@extends('layouts.vertical', ['title' => 'Reverb Import Fallback Stats', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
    <div class="container-fluid">
        <h4 class="mb-3">Reverb → Shopify import fallback stats</h4>
        <p class="text-muted">
            Every Reverb order is created in Shopify. When variant/SKU or API issues occur, orders are created as custom line items or stored for retry.
        </p>

        @isset($pendingCount)
            <div class="card mb-3">
                <div class="card-body">
                    <h5 class="card-title">Pending Shopify orders</h5>
                    <p class="mb-0">
                        <strong>{{ $pendingCount }}</strong> order(s) waiting for Shopify (stored in <code>pending_shopify_orders</code>).
                        Run <code>php artisan shopify:retry-pending-orders</code> to retry, or wait for the hourly schedule.
                    </p>
                </div>
            </div>
        @endisset

        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Fallback reasons (from logs)</h5>
                <p class="text-muted small mb-0">
                    Search <code>storage/logs/laravel.log</code> for: <code>ReverbOrderPushService: fallback=</code>, <code>ReverbOrderPushService: low inventory</code>, <code>pending_shopify_orders</code>.
                </p>
            </div>
        </div>
    </div>
@endsection
