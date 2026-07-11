@extends('layouts.vertical', ['title' => $title ?? 'Reverb — Settings', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

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
        <a href="{{ route('marketplace.manager.show', 'reverb') }}" class="text-muted small"><i class="ri-arrow-left-line"></i> Reverb Manager</a>
        @include('marketplace._page-heading', ['slug' => 'reverb', 'heading' => 'Reverb Sync Settings'])
        <p class="text-muted mb-3">Configure how Shopify (source) syncs with Reverb for pricing, inventory, and orders.</p>

        @include('marketplace.reverb._nav', ['active' => 'settings'])

        <form id="reverb-settings-form">
            @csrf

            <div class="settings-section">
                <div class="settings-section-header">Pricing</div>
                <div class="settings-section-body">
                    <div class="sync-toggle-row">
                        <label class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="pricing[price_sync]" value="1" {{ ($settings['pricing']['price_sync'] ?? false) ? 'checked' : '' }}>
                            <span class="form-check-label">Sync prices from Shopify to Reverb</span>
                        </label>
                    </div>
                    <div class="sync-toggle-row">
                        <label class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="pricing[use_sale_price]" value="1" {{ ($settings['pricing']['use_sale_price'] ?? false) ? 'checked' : '' }}>
                            <span class="form-check-label">Use Shopify sale price (instead of compare-at)</span>
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
                            <span class="form-check-label">Sync stock quantities from Shopify to Reverb</span>
                        </label>
                    </div>
                    <div class="row g-2 mt-2">
                        <div class="col-auto">
                            <label class="form-label small">Qty % of Shopify</label>
                            <input type="number" class="form-control form-control-sm" name="inventory[quantity_calc_percent]" value="{{ $settings['inventory']['quantity_calc_percent'] ?? 100 }}" min="0" max="100" style="width: 100px;">
                        </div>
                        <div class="col-auto">
                            <label class="form-label small">Min qty on Reverb</label>
                            <input type="number" class="form-control form-control-sm" name="inventory[min_quantity]" value="{{ $settings['inventory']['min_quantity'] ?? 1 }}" min="0" style="width: 100px;">
                        </div>
                    </div>
                </div>
            </div>

            <div class="settings-section">
                <div class="settings-section-header">Orders</div>
                <div class="settings-section-body">
                    <div class="sync-toggle-row">
                        <label class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="order[fetch_orders]" value="1" {{ ($settings['order']['fetch_orders'] ?? true) ? 'checked' : '' }}>
                            <span class="form-check-label">Fetch orders from Reverb on schedule</span>
                        </label>
                        <div class="form-text ms-4">When on, the 15‑minute schedule pulls Reverb orders into our DB. Manual <strong>Fetch from Reverb</strong> on the Orders page always works.</div>
                    </div>
                    <div class="sync-toggle-row">
                        <label class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="order[auto_import_to_shopify]" value="1" {{ ($settings['order']['auto_import_to_shopify'] ?? false) ? 'checked' : '' }}>
                            <span class="form-check-label">Automatically import Reverb orders to Shopify</span>
                        </label>
                    </div>
                    <div class="mt-2">
                        <label class="form-label small">Shopify import store</label>
                        <select class="form-select form-select-sm" name="order[shopify_store]" style="max-width: 400px;">
                            @php $shopifyStore = $settings['order']['shopify_store'] ?? 'main'; @endphp
                            <option value="main" {{ $shopifyStore === 'main' ? 'selected' : '' }}>Main B2C (5-core)</option>
                            <option value="5core" {{ $shopifyStore === '5core' ? 'selected' : '' }}>5Core store</option>
                            <option value="business" {{ $shopifyStore === 'business' ? 'selected' : '' }}>Business 5Core</option>
                            <option value="prolightsounds" {{ $shopifyStore === 'prolightsounds' ? 'selected' : '' }}>ProLightSounds</option>
                        </select>
                        <div class="form-text">Reverb orders import here. Default is main B2C store (same as <code>shopify_skus</code>).</div>
                    </div>
                    <div class="mt-2">
                        <label class="form-label small">Shopify channel handle (source_name)</label>
                        <input type="text" class="form-control form-control-sm" name="order[shopify_source_name]" value="{{ $settings['order']['shopify_source_name'] ?? 'reverb' }}" style="max-width: 400px;">
                        <div class="form-text">Case-sensitive handle registered in your Shopify app’s <strong>Marketplace extension</strong> (Partner Dashboard). Shopify shows this as the channel name (e.g. Reverb) instead of the app name (5core).</div>
                    </div>
                    <div class="mt-2">
                        <label class="form-label small">Shopify channel display label</label>
                        <input type="text" class="form-control form-control-sm" name="order[shopify_source_display_name]" value="{{ $settings['order']['shopify_source_display_name'] ?? 'Reverb' }}" style="max-width: 400px;">
                        <div class="form-text">Used in our dry-run preview only. Shopify Admin uses the label from your registered Marketplace handle.</div>
                    </div>
                    <div class="mt-2">
                        <label class="form-label small">Shopify order tags (comma-separated)</label>
                        <input type="text" class="form-control form-control-sm" name="order[shopify_order_tags]" value="{{ implode(', ', $settings['order']['shopify_order_tags'] ?? ['reverb']) }}" style="max-width: 400px;">
                    </div>
                </div>
            </div>

            <div class="settings-section">
                <div class="settings-section-header">Listings</div>
                <div class="settings-section-body">
                    <p class="text-muted small mb-2">Sync Reverb link map (Listings page) only reads Reverb and saves SKU mappings locally — it never creates listings on Reverb.</p>
                    <div class="sync-toggle-row">
                        <label class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="listings[auto_link_by_sku]" value="1" {{ ($settings['listings']['auto_link_by_sku'] ?? true) ? 'checked' : '' }}>
                            <span class="form-check-label">Auto-link listings by SKU match</span>
                        </label>
                    </div>
                    <div class="sync-toggle-row">
                        <label class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="listings[create_products_on_reverb]" value="1" {{ ($settings['listings']['create_products_on_reverb'] ?? false) ? 'checked' : '' }}>
                            <span class="form-check-label">Create new listings on Reverb from Shopify <span class="text-muted">(off for testing — not implemented yet)</span></span>
                        </label>
                    </div>
                    <div class="sync-toggle-row">
                        <label class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="listings[sync_title]" value="1" {{ ($settings['listings']['sync_title'] ?? false) ? 'checked' : '' }}>
                            <span class="form-check-label">Push title updates to existing Reverb listings</span>
                        </label>
                    </div>
                    <div class="sync-toggle-row">
                        <label class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="listings[sync_images]" value="1" {{ ($settings['listings']['sync_images'] ?? false) ? 'checked' : '' }}>
                            <span class="form-check-label">Push image updates to existing Reverb listings</span>
                        </label>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" id="btn-save-settings">Save settings</button>
            <button type="button" class="btn btn-outline-secondary ms-2" id="btn-sync-inventory-now">
                <i class="ri-refresh-line"></i> Sync inventory now
            </button>
            <span id="save-status" class="ms-2 small"></span>
        </form>
    </div>
</div>

<script>
document.getElementById('reverb-settings-form')?.addEventListener('submit', function (e) {
    e.preventDefault();
    var status = document.getElementById('save-status');
    var btn = document.getElementById('btn-save-settings');
    status.textContent = 'Saving…';
    btn.disabled = true;

    var formData = new FormData(this);
    fetch('{{ route('marketplace.settings.save', 'reverb') }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        },
        body: formData,
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        status.textContent = data.success ? (data.message || 'Saved.') : (data.message || 'Error');
        status.className = 'ms-2 small ' + (data.success ? 'text-success' : 'text-danger');
    })
    .catch(function () {
        status.textContent = 'Request failed.';
        status.className = 'ms-2 small text-danger';
    })
    .finally(function () { btn.disabled = false; });
});

document.getElementById('btn-sync-inventory-now')?.addEventListener('click', function () {
    var btn = this;
    var status = document.getElementById('save-status');
    btn.disabled = true;
    status.textContent = 'Syncing inventory…';
    fetch('{{ route('marketplace.manager.reverb.sync.inventory') }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        },
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        status.textContent = data.message || (data.success ? 'Synced.' : 'Failed');
        status.className = 'ms-2 small ' + (data.success ? 'text-success' : 'text-danger');
    })
    .catch(function () {
        status.textContent = 'Sync request failed.';
        status.className = 'ms-2 small text-danger';
    })
    .finally(function () { btn.disabled = false; });
});
</script>
@endsection
