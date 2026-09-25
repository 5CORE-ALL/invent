@extends('layouts.vertical', ['title' => $title ?? 'B5C B2B order', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
<div class="row">
    <div class="col-12">
        <a href="{{ route('marketplace.orders', 'b5cb2b') }}" class="text-muted small"><i class="ri-arrow-left-line"></i> Orders</a>
        @include('marketplace._page-heading', ['slug' => 'b5cb2b', 'heading' => 'Order '.$order->channelOrderNumber()])
        @include('marketplace.b5cb2b._nav', ['active' => 'orders'])
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="small text-muted" id="b5c-order-push-status"></span>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="b5c-push-this-order">Push SKU to Shopify</button>
                </div>
                <table class="table table-sm">
                    <tr><th>Status</th><td>{{ $order->status }}</td></tr>
                    <tr><th>Customer</th><td>{{ $order->customer_name }} / {{ $order->customer_email }}</td></tr>
                    <tr><th>Total</th><td>{{ $order->currency }} {{ $order->total }}</td></tr>
                    <tr><th>Tracking</th><td>{{ $order->tracking_reference ?: '—' }}</td></tr>
                    <tr><th>Shopify</th><td>{{ $order->shopify_order_id ?: '—' }}</td></tr>
                </table>
                @php $lines = $order->displayLines(); @endphp
                @if($lines)
                    <h6 class="mt-3">Lines</h6>
                    <table class="table table-sm">
                        <thead><tr><th>SKU</th><th>Name</th><th>Qty</th><th>Price</th></tr></thead>
                        <tbody>
                            @foreach($lines as $line)
                                <tr>
                                    <td>{{ $line['sku'] !== '' ? $line['sku'] : '—' }}</td>
                                    <td>{{ $line['name'] }}</td>
                                    <td>{{ $line['qty'] }}</td>
                                    <td>{{ $line['price'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
$('#b5c-push-this-order').on('click', function () {
    const $s = $('#b5c-order-push-status').text('Pushing SKU to Shopify…');
    $.post("{{ route('marketplace.orders.push', 'b5cb2b') }}", {
        _token: '{{ csrf_token() }}',
        order_id: '{{ $order->store_order_id }}'
    }).done(function (res) {
        $s.text(res.message || 'Pushed');
        if (res.success) {
            window.location.reload();
        }
    }).fail(function (xhr) {
        $s.text((xhr.responseJSON && xhr.responseJSON.message) || 'Failed');
    });
});
</script>
@endsection
