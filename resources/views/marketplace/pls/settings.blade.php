@extends('layouts.vertical', ['title' => $title ?? 'Shopify PLS — Settings', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
<style>
    .settings-section { border: 1px solid #e9ecef; border-radius: 8px; margin-bottom: 1.5rem; overflow: hidden; }
    .settings-section-header { background: #f8f9fa; padding: 12px 16px; font-weight: 600; }
    .settings-section-body { padding: 16px; }
    .sync-toggle-row { padding: 10px 0; border-bottom: 1px solid #f0f0f0; }
    .sync-toggle-row:last-child { border-bottom: none; }
</style>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <a href="{{ route('marketplace.manager.show', 'pls') }}" class="text-muted small"><i class="ri-arrow-left-line"></i> Shopify PLS Manager</a>
        @include('marketplace._page-heading', ['slug' => 'pls', 'heading' => 'Shopify PLS Sync Settings', 'mb' => 'mb-3'])
        <p class="text-muted mb-3">PLS is a Shopify store. Catalog/pricing/sales pull via Admin API. B2C→PLS inventory push is not implemented.</p>

        @include('marketplace.pls._nav', ['active' => 'settings'])

        <form id="pls-settings-form">
            @csrf
            <div class="settings-section">
                <div class="settings-section-header">Pricing</div>
                <div class="settings-section-body">
                    <div class="sync-toggle-row">
                        <label class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="pricing[price_sync]" value="1" {{ ($settings['pricing']['price_sync'] ?? false) ? 'checked' : '' }}>
                            <span class="form-check-label">Sync prices from B2C to PLS <span class="text-muted">(push not implemented)</span></span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="settings-section">
                <div class="settings-section-header">Inventory</div>
                <div class="settings-section-body">
                    <div class="sync-toggle-row">
                        <label class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="inventory[inventory_sync]" value="1" {{ ($settings['inventory']['inventory_sync'] ?? false) ? 'checked' : '' }}>
                            <span class="form-check-label">Sync stock from B2C to PLS <span class="text-muted">(push not implemented)</span></span>
                        </label>
                    </div>
                    <input type="hidden" name="inventory[min_quantity]" value="0">
                </div>
            </div>

            <div class="settings-section">
                <div class="settings-section-header">Orders / Sales</div>
                <div class="settings-section-body">
                    <div class="sync-toggle-row">
                        <label class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="order[fetch_orders]" value="1" {{ ($settings['order']['fetch_orders'] ?? true) ? 'checked' : '' }}>
                            <span class="form-check-label">Fetch PLS sales on schedule</span>
                        </label>
                    </div>
                    <div class="mt-2">
                        <label class="form-label small">Shopify tags (reference)</label>
                        <input type="text" class="form-control form-control-sm" name="order[shopify_order_tags]" value="{{ implode(', ', $settings['order']['shopify_order_tags'] ?? ['pls']) }}" style="max-width:400px;">
                    </div>
                    <input type="hidden" name="order[shopify_store]" value="prolightsounds">
                    <input type="hidden" name="order[shopify_source_name]" value="pls">
                    <input type="hidden" name="order[shopify_source_display_name]" value="Shopify PLS">
                </div>
            </div>

            <div class="settings-section">
                <div class="settings-section-header">Listings</div>
                <div class="settings-section-body">
                    <div class="sync-toggle-row">
                        <label class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="listings[auto_link_by_sku]" value="1" {{ ($settings['listings']['auto_link_by_sku'] ?? true) ? 'checked' : '' }}>
                            <span class="form-check-label">Auto-link by SKU</span>
                        </label>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" id="btn-save-settings">Save settings</button>
            <button type="button" class="btn btn-outline-secondary ms-2" id="btn-sync-inventory-now">Sync inventory now</button>
            <button type="button" class="btn btn-outline-warning ms-2" id="btn-sync-tracking-now">Sync tracking now</button>
            <span id="save-status" class="ms-2 small"></span>
        </form>
    </div>
</div>
@endsection

@section('script')
<script>
(function () {
    const status = document.getElementById('save-status');
    const csrf = '{{ csrf_token() }}';

    document.getElementById('pls-settings-form')?.addEventListener('submit', function (e) {
        e.preventDefault();
        const btn = document.getElementById('btn-save-settings');
        btn.disabled = true;
        status.textContent = 'Saving…';
        status.className = 'ms-2 small text-muted';
        fetch(@json(route('marketplace.settings.save', 'pls')), {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: new FormData(this),
        }).then(r => r.json()).then(data => {
            status.textContent = data.message || (data.success ? 'Saved.' : 'Error');
            status.className = 'ms-2 small ' + (data.success ? 'text-success' : 'text-danger');
        }).catch(() => {
            status.textContent = 'Request failed.';
            status.className = 'ms-2 small text-danger';
        }).finally(() => { btn.disabled = false; });
    });

    function postAction(url, label) {
        status.textContent = label;
        status.className = 'ms-2 small text-muted';
        return fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        }).then(r => r.json()).then(data => {
            status.textContent = data.message || (data.success ? 'OK' : 'Failed');
            status.className = 'ms-2 small ' + (data.success ? 'text-success' : 'text-danger');
        }).catch(() => {
            status.textContent = 'Request failed.';
            status.className = 'ms-2 small text-danger';
        });
    }

    document.getElementById('btn-sync-inventory-now')?.addEventListener('click', function () {
        const btn = this; btn.disabled = true;
        postAction(@json(route('marketplace.manager.pls.sync.inventory')), 'Checking inventory sync…').finally(() => { btn.disabled = false; });
    });
    document.getElementById('btn-sync-tracking-now')?.addEventListener('click', function () {
        const btn = this; btn.disabled = true;
        postAction(@json(route('marketplace.manager.pls.sync.tracking')), 'Checking tracking sync…').finally(() => { btn.disabled = false; });
    });
})();
</script>
@endsection
