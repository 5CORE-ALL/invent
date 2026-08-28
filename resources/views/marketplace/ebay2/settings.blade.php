@extends('layouts.vertical', ['title' => $title ?? 'eBay 2 — Settings', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
<style>
    .settings-section { border: 1px solid #e9ecef; border-radius: 8px; margin-bottom: 1.5rem; overflow: hidden; }
    .settings-section-header { background: #f8f9fa; padding: 12px 16px; font-weight: 600; }
    .settings-section-body { padding: 16px; }
    .sync-toggle-row { padding: 10px 0; border-bottom: 1px solid #f0f0f0; }
    .sync-toggle-row:last-child { border-bottom: none; }
    .ebay2-inv-overlay {
        position: fixed;
        inset: 0;
        z-index: 2050;
        background: rgba(15, 23, 42, .48);
        display: none;
        align-items: center;
        justify-content: center;
        padding: 16px;
    }
    .ebay2-inv-overlay.is-on { display: flex; }
    .ebay2-inv-overlay-card {
        background: #fff;
        border-radius: 16px;
        padding: 36px 44px;
        text-align: center;
        box-shadow: 0 18px 50px rgba(15, 23, 42, .28);
        min-width: 280px;
        max-width: 440px;
    }
    .ebay2-inv-overlay-title {
        margin-top: 16px;
        font-size: 18px;
        font-weight: 700;
        color: #1e3a8a;
    }
    .ebay2-inv-overlay-sub {
        margin-top: 8px;
        font-size: 13px;
        color: #64748b;
        line-height: 1.4;
    }
</style>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <a href="{{ route('marketplace.manager.show', 'ebay2') }}" class="text-muted small"><i class="ri-arrow-left-line"></i> eBay 2 Manager</a>
        @include('marketplace._page-heading', ['slug' => 'ebay2', 'heading' => 'eBay 2 Sync Settings'])
        <p class="text-muted mb-3">Configure how Shopify (source) syncs with eBay 2 for pricing, inventory, and orders.</p>

        @include('marketplace.ebay2._nav', ['active' => 'settings'])

        <form id="ebay2-settings-form">
            @csrf

            <div class="settings-section">
                <div class="settings-section-header">Pricing</div>
                <div class="settings-section-body">
                    <div class="sync-toggle-row">
                        <label class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="pricing[price_sync]" value="1" {{ ($settings['pricing']['price_sync'] ?? false) ? 'checked' : '' }}>
                            <span class="form-check-label">Sync prices from Shopify to eBay 2</span>
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
                            <span class="form-check-label">Sync stock quantities from Shopify to eBay 2</span>
                        </label>
                    </div>
                    <div class="row g-2 mt-2">
                        <div class="col-auto">
                            <label class="form-label small">Qty % of Shopify</label>
                            <input type="number" class="form-control form-control-sm" name="inventory[quantity_calc_percent]" value="{{ $settings['inventory']['quantity_calc_percent'] ?? 100 }}" min="0" max="100" style="width: 100px;">
                        </div>
                    </div>
                    <div class="form-text mt-2">Every linked listing on this marketplace uses this %. Example: Shopify 100 with 20% → eBay 2 qty 20. Always uses <strong>live Shopify</strong> stock. Shopify 0/− → marketplace <strong>0</strong> (never forced to 1). Draft / inactive / unpublished listings are never stocked or activated.</div>
                    <input type="hidden" name="inventory[min_quantity]" value="0">
                </div>
            </div>

            <div class="settings-section">
                <div class="settings-section-header">Orders</div>
                <div class="settings-section-body">
                    <div class="sync-toggle-row">
                        <label class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="order[fetch_orders]" value="1" {{ ($settings['order']['fetch_orders'] ?? true) ? 'checked' : '' }}>
                            <span class="form-check-label">Fetch orders from eBay 2 on schedule</span>
                        </label>
                        <div class="form-text ms-4">When on, the 15‑minute schedule pulls eBay 2 orders into our DB. Manual <strong>Fetch from eBay 2</strong> on the Orders page always works.</div>
                    </div>
                    <div class="sync-toggle-row">
                        <label class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="order[auto_import_to_shopify]" value="1" {{ ($settings['order']['auto_import_to_shopify'] ?? true) ? 'checked' : '' }}>
                            <span class="form-check-label">Automatically import eBay 2 orders to Shopify</span>
                        </label>
                        <div class="form-text ms-4">ON by default. New eBay 2 orders are queued to Shopify on the 15‑minute schedule.</div>
                    </div>
                    <div class="sync-toggle-row">
                        <label class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="order[import_paid_orders_only]" value="1" {{ ($settings['order']['import_paid_orders_only'] ?? false) ? 'checked' : '' }}>
                            <span class="form-check-label">Only auto-import paid orders</span>
                        </label>
                        <div class="form-text ms-4">When on, unpaid / payment-pending eBay 2 orders stay in our DB and are not queued or manually pushed to Shopify. Turn this off to import unpaid orders.</div>
                    </div>
                    <div class="sync-toggle-row">
                        <label class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="order[sync_address_to_shopify]" value="1" {{ ($settings['order']['sync_address_to_shopify'] ?? true) ? 'checked' : '' }}>
                            <span class="form-check-label">Automatically sync eBay 2 customer / shipping address to Shopify</span>
                        </label>
                        <div class="form-text ms-4">ON by default. Every 15 minutes the app fills missing Shopify shipping/billing/customer address from eBay 2 — no manual Pull needed.</div>
                    </div>
                    <div class="sync-toggle-row">
                        <label class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="order[push_tracking_to_ebay2]" value="1" {{ ($settings['order']['push_tracking_to_ebay2'] ?? true) ? 'checked' : '' }}>
                            <span class="form-check-label">Automatically push Shopify tracking numbers to eBay 2</span>
                        </label>
                        <div class="form-text ms-4">ON by default. Every 5 minutes the app reads Shopify fulfillments (after you print/download a label) and ships the order on eBay 2 — no manual push needed. You can still push per order from the order detail page.</div>
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
                        <div class="form-text">eBay 2 orders import here. Default is main B2C store (same as <code>shopify_skus</code>).</div>
                    </div>
                    <div class="mt-2">
                        <label class="form-label small">Shopify channel handle (source_name)</label>
                        <input type="text" class="form-control form-control-sm" name="order[shopify_source_name]" value="{{ $settings['order']['shopify_source_name'] ?? 'ebay2' }}" style="max-width: 400px;">
                        <div class="form-text">Case-sensitive handle registered in your Shopify app’s <strong>Marketplace extension</strong> (Partner Dashboard). Shopify shows this as the channel name (e.g. eBay 2) instead of the app name (5core).</div>
                    </div>
                    <div class="mt-2">
                        <label class="form-label small">Shopify channel display label</label>
                        <input type="text" class="form-control form-control-sm" name="order[shopify_source_display_name]" value="{{ $settings['order']['shopify_source_display_name'] ?? 'eBay 2' }}" style="max-width: 400px;">
                        <div class="form-text">Used in our dry-run preview only. Shopify Admin uses the label from your registered Marketplace handle.</div>
                    </div>
                    <div class="mt-2">
                        <label class="form-label small">Shopify order tags (comma-separated)</label>
                        <input type="text" class="form-control form-control-sm" name="order[shopify_order_tags]" value="{{ implode(', ', $settings['order']['shopify_order_tags'] ?? ['ebay2']) }}" style="max-width: 400px;">
                    </div>
                </div>
            </div>

            <div class="settings-section">
                <div class="settings-section-header">Listings</div>
                <div class="settings-section-body">
                    <p class="text-muted small mb-2">Sync eBay 2 link map only reads eBay 2 and saves SKU mappings locally — it never creates listings on eBay 2. When <strong>Auto-link</strong> is on, this also runs hourly on schedule.</p>
                    <div class="sync-toggle-row">
                        <label class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="listings[auto_link_by_sku]" value="1" {{ ($settings['listings']['auto_link_by_sku'] ?? true) ? 'checked' : '' }}>
                            <span class="form-check-label">Auto-link listings by SKU match</span>
                        </label>
                        <div class="form-text ms-4">When on, refresh eBay 2 SKU ↔ product_id mappings hourly (same as manual Sync eBay 2 link map). Manual sync on Listings always works.</div>
                    </div>
                    <div class="sync-toggle-row">
                        <label class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="listings[create_products_on_ebay2]" value="1" {{ ($settings['listings']['create_products_on_ebay2'] ?? false) ? 'checked' : '' }}>
                            <span class="form-check-label">Create new listings on eBay 2 from Shopify <span class="text-muted">(off for testing — not implemented yet)</span></span>
                        </label>
                    </div>
                    <div class="sync-toggle-row">
                        <label class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="listings[sync_title]" value="1" {{ ($settings['listings']['sync_title'] ?? false) ? 'checked' : '' }}>
                            <span class="form-check-label">Push title updates to existing eBay 2 listings</span>
                        </label>
                    </div>
                    <div class="sync-toggle-row">
                        <label class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="listings[sync_images]" value="1" {{ ($settings['listings']['sync_images'] ?? false) ? 'checked' : '' }}>
                            <span class="form-check-label">Push image updates to existing eBay 2 listings</span>
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
        <div id="ebay2InvSyncOverlay" class="ebay2-inv-overlay" aria-live="polite" aria-busy="true">
            <div class="ebay2-inv-overlay-card">
                <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
                <div class="ebay2-inv-overlay-title" id="ebay2InvSyncTitle">Syncing inventory…</div>
                <div class="ebay2-inv-overlay-sub" id="ebay2InvSyncText">Applying the saved Qty % of Shopify to eBay 2.</div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('ebay2-settings-form')?.addEventListener('submit', function (e) {
    e.preventDefault();
    var status = document.getElementById('save-status');
    var btn = document.getElementById('btn-save-settings');
    status.textContent = 'Saving…';
    btn.disabled = true;

    var formData = new FormData(this);
    fetch('{{ route('marketplace.settings.save', 'ebay2') }}', {
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

(function () {
    var overlay = document.getElementById('ebay2InvSyncOverlay');
    var titleEl = document.getElementById('ebay2InvSyncTitle');
    var textEl = document.getElementById('ebay2InvSyncText');
    var statusUrl = @json(\Illuminate\Support\Facades\Route::has('marketplace.manager.ebay2.sync.inventory.status') ? route('marketplace.manager.ebay2.sync.inventory.status') : url('/marketplace-manager/ebay2/sync-inventory/status'));
    var pollTimer = null;
    var pollCount = 0;

    function setOverlay(on, title, text) {
        if (titleEl && title) titleEl.textContent = title;
        if (textEl && text) textEl.textContent = text;
        if (overlay) overlay.classList.toggle('is-on', !!on);
    }

    function stopPoll() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    function pollStatus() {
        pollCount += 1;
        if (pollCount > 720) {
            stopPoll();
            setOverlay(false);
            var stuck = document.getElementById('save-status');
            if (stuck) {
                stuck.textContent = 'Sync is still running in the background. Refresh later to confirm.';
                stuck.className = 'ms-2 small text-muted';
            }
            return;
        }
        fetch(statusUrl, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            var p = (data && data.progress) ? data.progress : {};
            var state = String(p.state || 'idle');
            var pct = p.qty_percent != null ? p.qty_percent : '';
            var msg = p.message || '';
            if (state === 'done' || state === 'failed') {
                stopPoll();
                setOverlay(false);
                var status = document.getElementById('save-status');
                if (status) {
                    status.textContent = msg || (state === 'failed' ? 'Inventory sync failed.' : 'Inventory sync finished.');
                    status.className = 'ms-2 small ' + (state === 'failed' ? 'text-danger' : 'text-success');
                }
                return;
            }
            setOverlay(true, 'Syncing inventory…', msg || ('Applying ' + pct + '% of live Shopify to eBay 2. Please wait.'));
        })
        .catch(function () {});
    }

    document.getElementById('btn-sync-inventory-now')?.addEventListener('click', function () {
        var btn = this;
        var status = document.getElementById('save-status');
        var form = document.getElementById('ebay2-settings-form');
        btn.disabled = true;
        status.textContent = 'Saving inventory rule and starting sync…';
        status.className = 'ms-2 small text-muted';
        setOverlay(true, 'Syncing inventory…', 'Saving the Qty % rule, then matching eBay 2 to live Shopify.');
        var formData = form ? new FormData(form) : new FormData();
        fetch('{{ route('marketplace.manager.ebay2.sync.inventory') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
            body: formData,
        })
        .then(function (r) {
            return r.json().then(function (data) {
                return { ok: r.ok, data: data };
            }).catch(function () {
                return { ok: false, data: { message: 'Server returned non-JSON (often a timeout). Sync may still be running in the background.' } };
            });
        })
        .then(function (res) {
            var data = res.data || {};
            if (!res.ok || data.success === false) {
                stopPoll();
                setOverlay(false);
                status.textContent = data.message || 'Failed';
                status.className = 'ms-2 small text-danger';
                return;
            }
            var pct = data.qty_percent != null ? data.qty_percent : '';
            setOverlay(true, 'Syncing inventory…', data.message || ('Applying ' + pct + '% of live Shopify to every linked listing.'));
            status.textContent = data.message || 'Syncing…';
            status.className = 'ms-2 small text-muted';
            stopPoll();
            pollCount = 0;
            pollStatus();
            pollTimer = setInterval(pollStatus, 2500);
        })
        .catch(function () {
            stopPoll();
            setOverlay(false);
            status.textContent = 'Sync request failed (network). Check that the marketplace-manager queue worker is running.';
            status.className = 'ms-2 small text-danger';
        })
        .finally(function () { btn.disabled = false; });
    });
})();

document.getElementById('btn-sync-tracking-now')?.addEventListener('click', function () {
    var btn = this;
    var status = document.getElementById('save-status');
    btn.disabled = true;
    status.textContent = 'Queueing tracking sync…';
    status.className = 'ms-2 small text-muted';
    fetch('{{ route('marketplace.manager.ebay2.sync.tracking') }}', {
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
