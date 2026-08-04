@extends('layouts.vertical', ['title' => $title ?? 'TikTok Shop — Settings', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
<style>
    .settings-section { border: 1px solid #e9ecef; border-radius: 8px; margin-bottom: 1.5rem; overflow: hidden; }
    .settings-section-header { background: #f8f9fa; padding: 12px 16px; font-weight: 600; }
    .settings-section-body { padding: 16px; }
    .sync-toggle-row { padding: 10px 0; border-bottom: 1px solid #f0f0f0; }
    .sync-toggle-row:last-child { border-bottom: none; }
    .settings-sub { margin-left: 2rem; padding: 6px 0; }
    .settings-sub .form-label { font-size: 0.82rem; }
</style>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <a href="{{ route('marketplace.manager.show', 'tiktok') }}" class="text-muted small"><i class="ri-arrow-left-line"></i> TikTok Shop Manager</a>
        @include('marketplace._page-heading', ['slug' => 'tiktok', 'heading' => 'TikTok Shop Sync Settings', 'mb' => 'mb-3'])
        <p class="text-muted mb-3">Configure inventory push (Shopify → TikTok Shop), order fetch + import, tracking push, address sync, and listings auto-link.</p>

        @include('marketplace.tiktok._nav', ['active' => 'settings'])

        <form id="tiktok-settings-form">
            @csrf

            {{-- INVENTORY --}}
            <div class="settings-section">
                <div class="settings-section-header">Inventory (Shopify → TikTok Shop)</div>
                <div class="settings-section-body">
                    <div class="sync-toggle-row">
                        <label class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="inventory[inventory_sync]" value="1" {{ ($settings['inventory']['inventory_sync'] ?? false) ? 'checked' : '' }}>
                            <span class="form-check-label">Sync stock quantities from Shopify to TikTok Shop</span>
                        </label>
                    </div>
                    <div class="row g-2 mt-2">
                        <div class="col-auto">
                            <label class="form-label small">Qty % of Shopify</label>
                            <input type="number" class="form-control form-control-sm" name="inventory[quantity_calc_percent]"
                                   value="{{ $settings['inventory']['quantity_calc_percent'] ?? 100 }}"
                                   min="0" max="100" style="width: 100px;">
                        </div>
                    </div>
                    <div class="form-text mt-2">Always uses <strong>live Shopify</strong> stock. Shopify 0/− → marketplace <strong>0</strong>. Requires <code>sku_id</code> from product sync.</div>
                    <input type="hidden" name="inventory[min_quantity]" value="0">
                    <input type="hidden" name="inventory[max_quantity]" value="">
                </div>
            </div>

            {{-- PRICING --}}
            <div class="settings-section">
                <div class="settings-section-header">Pricing</div>
                <div class="settings-section-body">
                    <div class="sync-toggle-row">
                        <label class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="pricing[price_sync]" value="1" {{ ($settings['pricing']['price_sync'] ?? false) ? 'checked' : '' }}>
                            <span class="form-check-label">Sync prices from Shopify to TikTok Shop <span class="text-muted">(future)</span></span>
                        </label>
                    </div>
                    <div class="sync-toggle-row">
                        <label class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="pricing[use_sale_price]" value="1" {{ ($settings['pricing']['use_sale_price'] ?? false) ? 'checked' : '' }}>
                            <span class="form-check-label">Use Shopify sale_price (else compare_at_price)</span>
                        </label>
                    </div>
                </div>
            </div>

            {{-- ORDERS --}}
            <div class="settings-section">
                <div class="settings-section-header">Orders (TikTok Shop → Shopify)</div>
                <div class="settings-section-body">
                    <div class="sync-toggle-row">
                        <label class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="order[fetch_orders]" value="1" {{ ($settings['order']['fetch_orders'] ?? true) ? 'checked' : '' }}>
                            <span class="form-check-label">Fetch orders from TikTok Shop on schedule</span>
                        </label>
                        <div class="form-text ms-4">When on, the 15‑minute schedule pulls TikTok Shop orders into our DB. Manual <strong>Fetch</strong> on the Orders page always works.</div>
                    </div>
                    <div class="sync-toggle-row">
                        <label class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="order[auto_import_to_shopify]" value="1" {{ ($settings['order']['auto_import_to_shopify'] ?? true) ? 'checked' : '' }}>
                            <span class="form-check-label">Automatically import TikTok Shop orders to Shopify</span>
                        </label>
                        <div class="form-text ms-4">ON by default. New orders are queued to Shopify on the 15‑minute schedule.</div>
                    </div>
                    <div class="sync-toggle-row">
                        <label class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="order[import_paid_orders_only]" value="1" {{ ($settings['order']['import_paid_orders_only'] ?? false) ? 'checked' : '' }}>
                            <span class="form-check-label">Only auto-import paid orders</span>
                        </label>
                        <div class="form-text ms-4">When on, unpaid / payment-pending orders stay in our DB and are not queued or manually pushed to Shopify.</div>
                    </div>
                    <div class="sync-toggle-row">
                        <label class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="order[sync_address_to_shopify]" value="1" {{ ($settings['order']['sync_address_to_shopify'] ?? true) ? 'checked' : '' }}>
                            <span class="form-check-label">Automatically sync TikTok Shop customer / shipping address to Shopify</span>
                        </label>
                        <div class="form-text ms-4">ON by default. Every 15 minutes the app fills missing Shopify shipping/billing/customer address from TikTok Shop.</div>
                    </div>
                    <div class="sync-toggle-row">
                        <label class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="order[push_tracking_to_tiktok]" value="1" {{ ($settings['order']['push_tracking_to_tiktok'] ?? true) ? 'checked' : '' }}>
                            <span class="form-check-label">Automatically push Shopify tracking numbers to TikTok Shop</span>
                        </label>
                        <div class="form-text ms-4">ON by default. Every 5 minutes the app reads Shopify fulfillments and marks the order shipped on TikTok Shop.</div>
                    </div>
                    <div class="sync-toggle-row">
                        <label class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="order[keep_order_number_from_channel]" value="1" {{ ($settings['order']['keep_order_number_from_channel'] ?? true) ? 'checked' : '' }}>
                            <span class="form-check-label">Keep TikTok order number in Shopify (#TT-…)</span>
                        </label>
                    </div>

                    <hr class="my-3">

                    <div class="mt-2">
                        <label class="form-label small">Shopify import store</label>
                        <select class="form-select form-select-sm" name="order[shopify_store]" style="max-width: 400px;">
                            @php $shopifyStore = $settings['order']['shopify_store'] ?? 'main'; @endphp
                            <option value="main" {{ $shopifyStore === 'main' ? 'selected' : '' }}>Main B2C</option>
                            <option value="5core" {{ $shopifyStore === '5core' ? 'selected' : '' }}>5Core</option>
                            <option value="business" {{ $shopifyStore === 'business' ? 'selected' : '' }}>Business 5Core</option>
                            <option value="prolightsounds" {{ $shopifyStore === 'prolightsounds' ? 'selected' : '' }}>ProLightSounds</option>
                        </select>
                    </div>
                    <div class="mt-2">
                        <label class="form-label small">Shopify order tags</label>
                        <input type="text" class="form-control form-control-sm" name="order[shopify_order_tags]"
                               value="{{ implode(', ', $settings['order']['shopify_order_tags'] ?? ['tiktok']) }}"
                               style="max-width: 400px;">
                    </div>
                    <div class="mt-2">
                        <label class="form-label small">Channel handle (source_name)</label>
                        <input type="text" class="form-control form-control-sm" name="order[shopify_source_name]"
                               value="{{ $settings['order']['shopify_source_name'] ?? 'tiktok' }}"
                               style="max-width: 400px;">
                    </div>
                    <div class="mt-2">
                        <label class="form-label small">Channel display name</label>
                        <input type="text" class="form-control form-control-sm" name="order[shopify_source_display_name]"
                               value="{{ $settings['order']['shopify_source_display_name'] ?? 'TikTok Shop' }}"
                               style="max-width: 400px;">
                    </div>
                </div>
            </div>

            {{-- LISTINGS --}}
            <div class="settings-section">
                <div class="settings-section-header">Listings</div>
                <div class="settings-section-body">
                    <div class="sync-toggle-row">
                        <label class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="listings[auto_link_by_sku]" value="1" {{ ($settings['listings']['auto_link_by_sku'] ?? true) ? 'checked' : '' }}>
                            <span class="form-check-label">Auto-link listings by SKU (during hourly product sync)</span>
                        </label>
                    </div>
                    <div class="sync-toggle-row">
                        <label class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="listings[create_products_on_tiktok]" value="1" {{ ($settings['listings']['create_products_on_tiktok'] ?? false) ? 'checked' : '' }}>
                            <span class="form-check-label">Create products on TikTok Shop from Shopify <span class="text-muted">(not implemented yet)</span></span>
                        </label>
                    </div>
                    <div class="sync-toggle-row">
                        <label class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="listings[sync_title]" value="1" {{ ($settings['listings']['sync_title'] ?? false) ? 'checked' : '' }}>
                            <span class="form-check-label">Sync titles from Shopify to TikTok Shop</span>
                        </label>
                    </div>
                    <div class="sync-toggle-row">
                        <label class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="listings[sync_images]" value="1" {{ ($settings['listings']['sync_images'] ?? false) ? 'checked' : '' }}>
                            <span class="form-check-label">Sync images from Shopify to TikTok Shop</span>
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

    document.getElementById('tiktok-settings-form')?.addEventListener('submit', function (e) {
        e.preventDefault();
        const btn = document.getElementById('btn-save-settings');
        btn.disabled = true;
        status.textContent = 'Saving…';
        status.className = 'ms-2 small text-muted';
        fetch(@json(route('marketplace.settings.save', 'tiktok')), {
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
        postAction(@json(route('marketplace.manager.tiktok.sync.inventory')), 'Queuing inventory sync…').finally(() => { btn.disabled = false; });
    });
    document.getElementById('btn-sync-tracking-now')?.addEventListener('click', function () {
        const btn = this; btn.disabled = true;
        postAction(@json(route('marketplace.manager.tiktok.sync.tracking')), 'Queuing tracking sync…').finally(() => { btn.disabled = false; });
    });
})();
</script>
@endsection
