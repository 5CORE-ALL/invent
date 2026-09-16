@extends('layouts.vertical', ['title' => $title ?? 'Business 5 Core (B2B) — Listings', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
<div class="row">
    <div class="col-12">
        <a href="{{ route('marketplace.manager.show', 'b5cb2b') }}" class="text-muted small"><i class="ri-arrow-left-line"></i> B5C B2B Manager</a>
        @include('marketplace._page-heading', ['slug' => 'b5cb2b', 'heading' => 'Business 5 Core (B2B) — Listings'])
        @include('marketplace.b5cb2b._nav', ['active' => 'products'])

        <div class="d-flex flex-wrap gap-2 mb-3">
            <form method="get" class="d-flex gap-2">
                <input type="text" name="q" value="{{ $search }}" class="form-control form-control-sm" placeholder="Search SKU or title">
                <button class="btn btn-sm btn-outline-secondary">Search</button>
            </form>
            <button type="button" class="btn btn-sm btn-primary" id="b5c-refresh-listings">Refresh listings</button>
            <button type="button" class="btn btn-sm btn-success" id="b5c-sync-inv">Sync inventory from Shopify</button>
            <span class="small text-muted align-self-center" id="b5c-listings-status"></span>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Title</th>
                            <th>Qty</th>
                            <th>In stock</th>
                            <th>Listing ID</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($products as $row)
                            <tr>
                                <td><a href="{{ url('/marketplace/b5cb2b/products/'.$row->id) }}">{{ $row->sku }}</a></td>
                                <td>{{ $row->title }}</td>
                                <td>{{ $row->qty }}</td>
                                <td>{{ $row->in_stock ? 'Yes' : 'No' }}</td>
                                <td>{{ $row->listing_id }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-muted">No listings yet. Click Refresh listings.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-body">{{ $products->links() }}</div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
$('#b5c-refresh-listings').on('click', function () {
    const $s = $('#b5c-listings-status').text('Refreshing…');
    $.post("{{ route('marketplace.manager.b5cb2b.refresh') }}", {_token: '{{ csrf_token() }}'})
        .done(function (res) { $s.text(res.message || 'Done'); location.reload(); })
        .fail(function (xhr) { $s.text((xhr.responseJSON && xhr.responseJSON.message) || 'Refresh failed'); });
});
$('#b5c-sync-inv').on('click', function () {
    const $s = $('#b5c-listings-status').text('Queueing inventory sync…');
    $.post("{{ route('marketplace.manager.b5cb2b.sync.inventory') }}", {_token: '{{ csrf_token() }}'})
        .done(function (res) { $s.text(res.message || 'Queued'); })
        .fail(function (xhr) { $s.text((xhr.responseJSON && xhr.responseJSON.message) || 'Failed'); });
});
</script>
@endsection
