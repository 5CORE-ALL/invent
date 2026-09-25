@extends('layouts.vertical', ['title' => $title ?? 'Business 5 Core (B2B) — Orders', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
<div class="row">
    <div class="col-12">
        <a href="{{ route('marketplace.manager.show', 'b5cb2b') }}" class="text-muted small"><i class="ri-arrow-left-line"></i> B5C B2B Manager</a>
        @include('marketplace._page-heading', ['slug' => 'b5cb2b', 'heading' => 'Business 5 Core (B2B) — Orders'])
        @include('marketplace.b5cb2b._nav', ['active' => 'orders'])

        <div class="d-flex flex-wrap gap-2 mb-3">
            <form method="get" class="d-flex gap-2">
                <input type="text" name="q" value="{{ $search }}" class="form-control form-control-sm" placeholder="Order ID, email, status">
                <button class="btn btn-sm btn-outline-secondary">Search</button>
            </form>
            <button type="button" class="btn btn-sm btn-primary" id="b5c-fetch-orders">Fetch orders</button>
            <button type="button" class="btn btn-sm btn-outline-primary" id="b5c-push-shopify">Push to Shopify</button>
            <button type="button" class="btn btn-sm btn-outline-success" id="b5c-sync-tracking">Push tracking</button>
            <span class="small text-muted align-self-center" id="b5c-orders-status"></span>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Status</th>
                            <th>Customer</th>
                            <th>Total</th>
                            <th>Tracking</th>
                            <th>Shopify</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($orders as $row)
                            <tr>
                                <td><a href="{{ url('/marketplace/b5cb2b/orders/'.$row->store_order_id) }}">{{ $row->channelOrderNumber() }}</a></td>
                                <td>{{ $row->status }}</td>
                                <td>{{ $row->customer_name }}<br><span class="text-muted small">{{ $row->customer_email }}</span></td>
                                <td>{{ $row->currency }} {{ $row->total }}</td>
                                <td>{{ $row->tracking_reference ?: '—' }}</td>
                                <td>{{ $row->shopify_order_id ?: '—' }}</td>
                                <td>{{ optional($row->ordered_at)->format('Y-m-d H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-muted">No orders yet. Click Fetch orders.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-body">{{ $orders->links() }}</div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
$('#b5c-fetch-orders').on('click', function () {
    const $s = $('#b5c-orders-status').text('Queueing…');
    $.post("{{ route('marketplace.manager.b5cb2b.fetch.orders') }}", {_token: '{{ csrf_token() }}'})
        .done(function (res) { $s.text(res.message || 'Queued'); })
        .fail(function (xhr) { $s.text((xhr.responseJSON && xhr.responseJSON.message) || 'Failed'); });
});
$('#b5c-push-shopify').on('click', function () {
    const $s = $('#b5c-orders-status').text('Queueing Shopify…');
    $.post("{{ route('marketplace.manager.b5cb2b.push.shopify') }}", {_token: '{{ csrf_token() }}'})
        .done(function (res) { $s.text(res.message || 'Queued'); })
        .fail(function (xhr) { $s.text((xhr.responseJSON && xhr.responseJSON.message) || 'Failed'); });
});
$('#b5c-sync-tracking').on('click', function () {
    const $s = $('#b5c-orders-status').text('Pushing tracking…');
    $.post("{{ route('marketplace.manager.b5cb2b.sync.tracking') }}", {_token: '{{ csrf_token() }}'})
        .done(function (res) { $s.text(res.message || 'Done'); })
        .fail(function (xhr) { $s.text((xhr.responseJSON && xhr.responseJSON.message) || 'Failed'); });
});
</script>
@endsection
