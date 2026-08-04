@extends('layouts.vertical', ['title' => $title ?? 'Shein — Settings', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

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
        <a href="{{ route('marketplace.manager.show', 'shein') }}" class="text-muted small"><i class="ri-arrow-left-line"></i> Shein Manager</a>
        @include('marketplace._page-heading', ['slug' => 'shein', 'heading' => 'Shein Sync Settings'])
        <p class="text-muted mb-3">Configure how Shopify (source) syncs with Shein for pricing, inventory, and orders.</p>

        @include('marketplace.shein._nav', ['active' => 'settings'])

        <form id="shein-settings-form">
            @csrf

            <div class="settings-section">
                <div class="settings-section-header">Pricing</div>
                <div class="settings-section-body">
                    <div class="sync-toggle-row">
                        <label class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="pricing[price_sync]" value="1" {{ ($settings['pricing']['price_sync'] ?? false) ? 'checked' : '' }}>
                            <span class="form-check-label">Sync prices from Shopify to Shein</span>
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
                            <span class="form-check-label">Sync stock quantities from Shopify to Shein</span>
                        </label>
                    </div>
                    <div class="row g-2 mt-2">
                        <div class="col-auto">
                            <label class="form-label small">Qty % of Shopify</label>
                            <input type="number" class="form-control form-control-sm" name="inventory[quantity_calc_percent]" value="{{ $settings['inventory']['quantity_calc_percent'] ?? 100 }}" min="0" max="100" style="width: 100px;">
                        </div>
                    </div>
                    <div class="form-text mt-2">Always uses <strong>live Shopify</strong> stock. Shopify 0/− → marketplace <strong>0</strong> (never forced to 1). Draft / inactive / unpublished listings are never stocked or activated.</div>
                    <input type="hidden" name="inventory[min_quantity]" value="0">
                </div>
            </div>

            <div class="settings-section">
                <div class="settings-section-header">Orders</div>
                <div class="settings-section-body">
                    <div class="sync-toggle-row">
                        <label class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="order[fetch_orders]" value="1" {{ ($settings['order']['fetch_orders'] ?? true) ? 'checked' : '' }}>
                            <span class="form-check-label">Fetch orders from Shein on schedule</span>
                        </label>
                        <div class="form-text ms-4">When on, the 15‑minute schedule pulls Shein orders into our DB. Manual <strong>Fetch from Shein</strong> on the Orders page always works.</div>
                    </div>
                    <div class="sync-toggle-row">
                        <label class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="order[auto_accept_on_shein]" value="1" {{ ($settings['order']['auto_accept_on_shein'] ?? false) ? 'checked' : '' }}>
                            <span class="form-check-label">Automatically accept Pending Shein orders</span>
                        </label>
                        <div class="form-text ms-4">When on, Pending orders are accepted on Shein (Pending → To Be Shipped) during the 15‑minute sync. Required before address/shipping steps work on Shein. You can still accept a single order from the order detail page.</div>
                    </div>
                    <div class="sync-toggle-row">
                        <label class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="order[auto_import_to_shopify]" value="1" {{ ($settings['order']['auto_import_to_shopify'] ?? true) ? 'checked' : '' }}>
                            <span class="form-check-label">Automatically import Shein orders to Shopify</span>
                        </label>
                        <div class="form-text ms-4">ON by default. New Shein orders are queued to Shopify on the 15‑minute schedule.</div>
                    </div>
                    <div class="sync-toggle-row">
                        <label class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="order[import_paid_orders_only]" value="1" {{ ($settings['order']['import_paid_orders_only'] ?? false) ? 'checked' : '' }}>
                            <span class="form-check-label">Only auto-import paid orders</span>
                        </label>
                        <div class="form-text ms-4">When on, unpaid / payment-pending Shein orders stay in our DB and are not queued or manually pushed to Shopify. Turn this off to import unpaid orders.</div>
                    </div>
                    <div class="sync-toggle-row">
                        <label class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="order[sync_address_to_shopify]" value="1" {{ ($settings['order']['sync_address_to_shopify'] ?? true) ? 'checked' : '' }}>
                            <span class="form-check-label">Automatically sync Shein customer / shipping address to Shopify</span>
                        </label>
                        <div class="form-text ms-4">ON by default. Every 15 minutes the app fills missing Shopify shipping/billing/customer address from Shein — no manual Pull needed.</div>
                    </div>
                    <div class="sync-toggle-row">
                        <label class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="order[push_tracking_to_shein]" value="1" {{ ($settings['order']['push_tracking_to_shein'] ?? true) ? 'checked' : '' }}>
                            <span class="form-check-label">Automatically push Shopify tracking numbers to Shein</span>
                        </label>
                        <div class="form-text ms-4">ON by default. Every 5 minutes the app reads Shopify fulfillments (after you print/download a label) and uploads the tracking number to Shein (<code>import-batch-multiple-express</code>). Pending orders are accepted first when needed. You can still push per order from the order detail page.</div>
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
                        <div class="form-text">Shein orders import here. Default is main B2C store (same as <code>shopify_skus</code>).</div>
                    </div>
                    <div class="mt-2">
                        <label class="form-label small">Shopify channel handle (source_name)</label>
                        <input type="text" class="form-control form-control-sm" name="order[shopify_source_name]" value="{{ $settings['order']['shopify_source_name'] ?? 'shein' }}" style="max-width: 400px;">
                        <div class="form-text">Case-sensitive handle registered in your Shopify app’s <strong>Marketplace extension</strong> (Partner Dashboard). Shopify shows this as the channel name (e.g. Shein) instead of the app name (5core).</div>
                    </div>
                    <div class="mt-2">
                        <label class="form-label small">Shopify channel display label</label>
                        <input type="text" class="form-control form-control-sm" name="order[shopify_source_display_name]" value="{{ $settings['order']['shopify_source_display_name'] ?? 'Shein' }}" style="max-width: 400px;">
                        <div class="form-text">Used in our dry-run preview only. Shopify Admin uses the label from your registered Marketplace handle.</div>
                    </div>
                    <div class="mt-2">
                        <label class="form-label small">Shopify order tags (comma-separated)</label>
                        <input type="text" class="form-control form-control-sm" name="order[shopify_order_tags]" value="{{ implode(', ', $settings['order']['shopify_order_tags'] ?? ['shein']) }}" style="max-width: 400px;">
                    </div>
                </div>
            </div>

            <div class="settings-section">
                <div class="settings-section-header">Listings</div>
                <div class="settings-section-body">
                    <p class="text-muted small mb-2">Sync Shein link map only reads Shein and saves SKU mappings locally — it never creates listings on Shein. When <strong>Auto-link</strong> is on, this also runs hourly on schedule.</p>
                    <div class="sync-toggle-row">
                        <label class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="listings[auto_link_by_sku]" value="1" {{ ($settings['listings']['auto_link_by_sku'] ?? true) ? 'checked' : '' }}>
                            <span class="form-check-label">Auto-link listings by SKU match</span>
                        </label>
                        <div class="form-text ms-4">When on, refresh Shein SKU ↔ product_id mappings hourly (same as manual Sync Shein link map). Manual sync on Listings always works.</div>
                    </div>
                    <div class="sync-toggle-row">
                        <label class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="listings[create_products_on_shein]" value="1" {{ ($settings['listings']['create_products_on_shein'] ?? false) ? 'checked' : '' }}>
                            <span class="form-check-label">Create new listings on Shein from Shopify <span class="text-muted">(off for testing — not implemented yet)</span></span>
                        </label>
                    </div>
                    <div class="sync-toggle-row">
                        <label class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="listings[sync_title]" value="1" {{ ($settings['listings']['sync_title'] ?? false) ? 'checked' : '' }}>
                            <span class="form-check-label">Push title updates to existing Shein listings</span>
                        </label>
                    </div>
                    <div class="sync-toggle-row">
                        <label class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="listings[sync_images]" value="1" {{ ($settings['listings']['sync_images'] ?? false) ? 'checked' : '' }}>
                            <span class="form-check-label">Push image updates to existing Shein listings</span>
                        </label>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" id="btn-save-settings">Save settings</button>
            <button type="button" class="btn btn-outline-secondary ms-2" id="btn-sync-inventory-now">
                <i class="ri-refresh-line"></i> Sync inventory now
            </button>
            <button type="button" class="btn btn-outline-warning ms-2" id="btn-sync-tracking-now">
                <i class="ri-truck-line"></i> Sync tracking now
            </button>
            <span id="save-status" class="ms-2 small"></span>
        </form>
    </div>
</div>

<script>
document.getElementById('shein-settings-form')?.addEventListener('submit', function (e) {
    e.preventDefault();
    var status = document.getElementById('save-status');
    var btn = document.getElementById('btn-save-settings');
    status.textContent = 'Saving…';
    btn.disabled = true;

    var formData = new FormData(this);
    fetch('{{ route('marketplace.settings.save', 'shein') }}', {
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
    status.textContent = 'Queueing inventory sync…';
    status.className = 'ms-2 small text-muted';
    fetch('{{ route('marketplace.manager.shein.sync.inventory') }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        },
    })
    .then(function (r) {
        return r.json().then(function (data) {
            return { ok: r.ok, status: r.status, data: data };
        }).catch(function () {
            return { ok: false, status: r.status, data: { message: 'Server returned non-JSON (often a timeout). Sync may still be running in the background.' } };
        });
    })
    .then(function (res) {
        var data = res.data || {};
        status.textContent = data.message || (res.ok ? 'Queued.' : 'Failed');
        status.className = 'ms-2 small ' + (res.ok && data.success !== false ? 'text-success' : 'text-danger');
    })
    .catch(function () {
        status.textContent = 'Sync request failed (network). Check that the marketplace-manager queue worker is running.';
        status.className = 'ms-2 small text-danger';
    })
    .finally(function () { btn.disabled = false; });
});

document.getElementById('btn-sync-tracking-now')?.addEventListener('click', function () {
    var btn = this;
    var status = document.getElementById('save-status');
    btn.disabled = true;
    status.textContent = 'Queueing tracking sync…';
    status.className = 'ms-2 small text-muted';
    fetch('{{ route('marketplace.manager.shein.sync.tracking') }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        },
    })
    .then(function (r) {
        return r.json().then(function (data) {
            return { ok: r.ok, data: data };
        }).catch(function () {
            return { ok: false, data: { message: 'Server returned non-JSON.' } };
        });
    })
    .then(function (res) {
        var data = res.data || {};
        status.textContent = data.message || (res.ok ? 'Queued.' : 'Failed');
        status.className = 'ms-2 small ' + (res.ok && data.success !== false ? 'text-success' : 'text-danger');
    })
    .catch(function () {
        status.textContent = 'Tracking sync request failed (network).';
        status.className = 'ms-2 small text-danger';
    })
    .finally(function () { btn.disabled = false; });
});
</script>
@endsection
