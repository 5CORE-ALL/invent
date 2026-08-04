@extends('layouts.vertical', ['title' => $title ?? 'TikTok 2 — Order', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
<div class="row">
    <div class="col-12">
        <a href="{{ route('marketplace.orders', 'tiktok2') }}" class="text-muted small"><i class="ri-arrow-left-line"></i> TikTok 2 Orders</a>
        @include('marketplace._page-heading', ['slug' => 'tiktok2', 'heading' => 'Order '.$line->order_id, 'mb' => 'mb-3'])
        @include('marketplace.tiktok2._nav', ['active' => 'orders'])

        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3"><div class="text-muted small">Status</div><div>{{ $line->order_status ?? '—' }}</div></div>
                    <div class="col-md-3"><div class="text-muted small">Created</div><div>{{ $line->order_created_at ? $line->order_created_at->format('M d, Y H:i') : '—' }}</div></div>
                    <div class="col-md-3"><div class="text-muted small">Currency</div><div>{{ $line->currency ?? '—' }}</div></div>
                    <div class="col-md-3"><div class="text-muted small">Order amount</div><div>{{ is_numeric($line->order_amount) ? number_format((float) $line->order_amount, 2) : '—' }}</div></div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">Line items ({{ $lines->count() }})</h5></div>
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>SKU</th>
                            <th>Product</th>
                            <th>Qty</th>
                            <th>Sale price</th>
                            <th>Line status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($lines as $row)
                            <tr>
                                <td><code>{{ $row->seller_sku ?? '—' }}</code></td>
                                <td>{{ $row->product_name ?? '—' }}</td>
                                <td>{{ $row->quantity ?? 1 }}</td>
                                <td>{{ is_numeric($row->sale_price) ? number_format((float) $row->sale_price, 2) : '—' }}</td>
                                <td>{{ $row->line_status ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
