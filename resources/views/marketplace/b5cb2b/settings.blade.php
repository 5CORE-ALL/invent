@extends('layouts.vertical', ['title' => $title ?? 'Business 5 Core (B2B) — Settings', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
<div class="row">
    <div class="col-12">
        <a href="{{ route('marketplace.manager.show', 'b5cb2b') }}" class="text-muted small"><i class="ri-arrow-left-line"></i> B5C B2B Manager</a>
        @include('marketplace._page-heading', ['slug' => 'b5cb2b', 'heading' => 'Business 5 Core (B2B) Sync Settings'])
        @include('marketplace.b5cb2b._nav', ['active' => 'settings'])

        <form id="b5c-settings-form">
            @csrf
            <div class="card mb-3">
                <div class="card-header"><strong>Inventory</strong></div>
                <div class="card-body">
                    <label class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="inventory[inventory_sync]" value="1" {{ ($settings['inventory']['inventory_sync'] ?? false) ? 'checked' : '' }}>
                        Sync Shopify qty to the B2B store
                    </label>
                    <label class="form-label">Quantity %</label>
                    <input type="number" min="0" max="100" class="form-control" style="max-width:160px" name="inventory[quantity_calc_percent]" value="{{ $settings['inventory']['quantity_calc_percent'] ?? 100 }}">
                </div>
            </div>
            <div class="card mb-3">
                <div class="card-header"><strong>Orders</strong></div>
                <div class="card-body">
                    <label class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="order[fetch_orders]" value="1" {{ ($settings['order']['fetch_orders'] ?? true) ? 'checked' : '' }}>
                        Fetch orders from the B2B store
                    </label>
                    <label class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="order[push_tracking_to_b5cb2b]" value="1" {{ ($settings['order']['push_tracking_to_b5cb2b'] ?? true) ? 'checked' : '' }}>
                        Push Shopify tracking to the B2B store
                    </label>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Save settings</button>
            <span class="small text-muted ms-2" id="b5c-settings-status"></span>
        </form>
    </div>
</div>
@endsection

@section('script')
<script>
$('#b5c-settings-form').on('submit', function (e) {
    e.preventDefault();
    const data = $(this).serialize();
    $('#b5c-settings-status').text('Saving…');
    $.post("{{ route('marketplace.settings.save', 'b5cb2b') }}", data)
        .done(function (res) { $('#b5c-settings-status').text(res.message || 'Saved'); })
        .fail(function (xhr) { $('#b5c-settings-status').text((xhr.responseJSON && xhr.responseJSON.message) || 'Save failed'); });
});
</script>
@endsection
