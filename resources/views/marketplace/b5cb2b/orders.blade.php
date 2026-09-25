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
                            <th>SKU</th>
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
                            <tr data-store-id="{{ $row->store_order_id }}" data-shopify="{{ $row->shopify_order_id }}" data-label="{{ $row->channelOrderNumber() }}">
                                <td><a href="{{ url('/marketplace/b5cb2b/orders/'.$row->store_order_id) }}">{{ $row->channelOrderNumber() }}</a></td>
                                <td class="text-nowrap">
                                    @forelse($row->displayLines() as $line)
                                        <div>{{ $line['sku'] !== '' ? $line['sku'] : '—' }}</div>
                                    @empty
                                        —
                                    @endforelse
                                </td>
                                <td>{{ $row->status }}</td>
                                <td>{{ $row->customer_name }}<br><span class="text-muted small">{{ $row->customer_email }}</span></td>
                                <td>{{ $row->currency }} {{ $row->total }}</td>
                                <td>{{ $row->tracking_reference ?: '—' }}</td>
                                <td class="b5c-shopify-cell">{{ $row->shopify_order_id ?: '—' }}</td>
                                <td>{{ optional($row->ordered_at)->format('Y-m-d H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-muted">No orders yet. Click Fetch orders.</td></tr>
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
function b5cPushUnlinked() {
    if (window.b5cPushing) {
        return;
    }
    var rows = [];
    $('tr[data-store-id]').each(function () {
        if (!$(this).attr('data-shopify')) {
            rows.push($(this));
        }
    });
    var $s = $('#b5c-orders-status');
    if (!rows.length) {
        $s.text('Every order is already in Shopify.');
        return;
    }
    window.b5cPushing = true;
    var ok = 0;
    var fail = 0;
    function next() {
        if (!rows.length) {
            window.b5cPushing = false;
            $s.text('Shopify: ' + ok + ' sent' + (fail ? ', ' + fail + ' failed' : '') + '.');
            return;
        }
        var $tr = rows.shift();
        $s.text('Sending ' + $tr.attr('data-label') + ' to Shopify…');
        $.post("{{ route('marketplace.orders.push', 'b5cb2b') }}", {
            _token: '{{ csrf_token() }}',
            order_id: $tr.attr('data-store-id')
        }).done(function (res) {
            if (res.shopify_order_id) {
                ok++;
                $tr.attr('data-shopify', res.shopify_order_id);
                $tr.find('.b5c-shopify-cell').text(res.shopify_order_id);
            } else {
                fail++;
                $tr.find('.b5c-shopify-cell').text(res.message || 'Failed');
            }
            setTimeout(next, 2000);
        }).fail(function (xhr) {
            fail++;
            $tr.find('.b5c-shopify-cell').text((xhr.responseJSON && xhr.responseJSON.message) || 'Failed');
            setTimeout(next, 2000);
        });
    }
    next();
}
$('#b5c-push-shopify').on('click', b5cPushUnlinked);
b5cPushUnlinked();
$('#b5c-sync-tracking').on('click', function () {
    const $s = $('#b5c-orders-status').text('Pushing tracking…');
    $.post("{{ route('marketplace.manager.b5cb2b.sync.tracking') }}", {_token: '{{ csrf_token() }}'})
        .done(function (res) { $s.text(res.message || 'Done'); })
        .fail(function (xhr) { $s.text((xhr.responseJSON && xhr.responseJSON.message) || 'Failed'); });
});
</script>
@endsection
