@extends('layouts.vertical', ['title' => $title ?? 'Reverb - Settings', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
<style>
    .settings-section { border: 1px solid #e9ecef; border-radius: 8px; margin-bottom: 1.5rem; overflow: hidden; }
    .settings-section-header { background: #f8f9fa; padding: 12px 16px; font-weight: 600; display: flex; align-items: center; justify-content: space-between; }
    .settings-section-body { padding: 16px; }
    .form-check-input:checked { background-color: #405189; border-color: #405189; }
    .sync-toggle-row { display: flex; align-items: center; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f0f0f0; }
    .sync-toggle-row:last-child { border-bottom: none; }
</style>
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <p class="text-muted mb-3">Sync Reverb with Shopify. Configure pricing, inventory, and order import below.</p>

            <form id="reverb-settings-form">
                @csrf

                {{-- Pricing --}}
                <div class="settings-section">
                    <div class="settings-section-header">
                        <span>Pricing</span>
                        <span>
                            <button type="button" class="btn btn-sm btn-outline-secondary" title="Refresh"><i class="ri-refresh-line"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary ms-1" title="Reset"><i class="ri-restart-line"></i></button>
                        </span>
                    </div>
                    <div class="settings-section-body">
                        <div class="sync-toggle-row">
                            <div>
                                <label class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" name="pricing[price_sync]" value="1" {{ ($settings['pricing']['price_sync'] ?? false) ? 'checked' : '' }}>
                                    <span class="form-check-label">Sync the Prices on Reverb with the Prices on your Shopify automatically</span>
                                </label>
                            </div>
                        </div>
                        <div class="sync-toggle-row">
                            <div>
                                <label class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" name="pricing[use_sale_price]" value="1" {{ ($settings['pricing']['use_sale_price'] ?? false) ? 'checked' : '' }}>
                                    <span class="form-check-label">By default, we use Compare-at Price (if available) for Price Sync. This option allows you to use Sale Price for Price Sync</span>
                                </label>
                            </div>
                        </div>
                        <div class="sync-toggle-row">
                            <div>
                                <label class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" name="pricing[currency_conversion]" value="1" {{ ($settings['pricing']['currency_conversion'] ?? false) ? 'checked' : '' }}>
                                    <span class="form-check-label">Automatically convert the price of listings on Reverb if your Shopify and your channel do not have the same currency.</span>
                                </label>
                                <i class="ri-information-line text-muted ms-1"></i>
                            </div>
                        </div>
                        <div class="mt-3">
                            <strong>Price Rules</strong>
                            <p class="text-muted small mb-2">Automatically adjust the prices of listings on Reverb in relation to the prices on your Shopify</p>
                            <div class="d-flex flex-wrap gap-2 align-items-center">
                                <select class="form-select form-select-sm" name="pricing[adjustment_type]" style="width: auto;">
                                    <option value="increase">Increase</option>
                                    <option value="decrease">Decrease</option>
                                </select>
                                <input type="text" class="form-control form-control-sm" name="pricing[adjustment_value]" placeholder="0" style="width: 80px;">
                                <select class="form-select form-select-sm" name="pricing[adjustment_base]" style="width: auto;">
                                    <option value="product_price">product price by</option>
                                </select>
                                <select class="form-select form-select-sm" name="pricing[adjustment_method]" style="width: auto;">
                                    <option value="fixed">Fixed amount</option>
                                    <option value="percent">Percent</option>
                                </select>
                            </div>
                            <div class="mt-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary">No Rounding</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary ms-1">Price Preview <i class="ri-arrow-down-s-line"></i></button>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Inventory --}}
                <div class="settings-section">
                    <div class="settings-section-header">
                        <span>Inventory</span>
                        <span>
                            <button type="button" class="btn btn-sm btn-outline-secondary"><i class="ri-refresh-line"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary ms-1"><i class="ri-restart-line"></i></button>
                        </span>
                    </div>
                    <div class="settings-section-body">
                        <div class="sync-toggle-row">
                            <div>
                                <label class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" name="inventory[inventory_sync]" value="1" {{ ($settings['inventory']['inventory_sync'] ?? false) ? 'checked' : '' }}>
                                    <span class="form-check-label">Sync the stock quantities from your Shopify to this channel</span>
                                </label>
                                <p class="small text-muted mb-0 mt-1">This plan automatically imports new Orders from Reverb to the eCommerce and syncs updated quantities back to Shopify. Only orders created once enabling inventory sync will be processed.</p>
                                <i class="ri-information-line text-muted"></i>
                            </div>
                        </div>
                        <div class="sync-toggle-row">
                            <label class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" name="inventory[keep_listing_active]" value="1" {{ ($settings['inventory']['keep_listing_active'] ?? false) ? 'checked' : '' }}>
                                <span class="form-check-label">Keep my listing active on Reverb when product is in archived/draft status on Shopify</span>
                            </label>
                        </div>
                        <div class="sync-toggle-row">
                            <label class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" name="inventory[auto_relist]" value="1" {{ ($settings['inventory']['auto_relist'] ?? false) ? 'checked' : '' }}>
                                <span class="form-check-label">Allows automatic relisting items when linked products from Shopify are available</span>
                            </label>
                        </div>
                        <div class="mt-3">
                            <strong>Inventory Rules</strong>
                            <p class="text-muted small mb-2">Automatically adjust the quantities on Reverb in relation to the total quantity on your Shopify</p>
                            <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                                <input type="number" class="form-control form-control-sm" name="inventory[quantity_calc_percent]" value="{{ $settings['inventory']['quantity_calc_percent'] ?? 100 }}" min="0" max="100" style="width: 80px;"> % of available inventory from Shopify
                            </div>
                            <div class="mb-2">
                                <label class="form-label small">For No Manage Stock listings, automatically set quantity to:</label>
                                <input type="number" class="form-control form-control-sm" name="inventory[no_manage_stock_quantity]" value="{{ $settings['inventory']['no_manage_stock_quantity'] ?? 0 }}" style="width: 100px;">
                            </div>
                            <div class="d-flex gap-3 flex-wrap">
                                <div>
                                    <label class="form-label small">Maximum Quantity</label>
                                    <input type="number" class="form-control form-control-sm" name="inventory[max_quantity]" value="{{ $settings['inventory']['max_quantity'] ?? 1 }}" min="0" style="width: 80px;">
                                </div>
                                <div>
                                    <label class="form-label small">Minimum Quantity</label>
                                    <input type="number" class="form-control form-control-sm" name="inventory[min_quantity]" value="{{ $settings['inventory']['min_quantity'] ?? 1 }}" min="0" style="width: 80px;">
                                </div>
                                <div>
                                    <label class="form-label small">Out of Stock Threshold (set out of stock if Shopify qty &lt;)</label>
                                    <input type="number" class="form-control form-control-sm" name="inventory[out_of_stock_threshold]" value="{{ $settings['inventory']['out_of_stock_threshold'] ?? 1 }}" min="0" style="width: 80px;">
                                </div>
                            </div>
                        </div>
                        @if(!empty($locations))
                        <div class="mt-3">
                            <strong>Sync from Shopify Location</strong>
                            <p class="text-muted small">Select one or multiple locations from which you want to sync the Product inventory from Shopify to Reverb.</p>
                            <div class="row">
                                @foreach($locations as $loc)
                                <div class="col-md-6 col-lg-4">
                                    <label class="form-check">
                                        <input type="checkbox" class="form-check-input" name="inventory[shopify_location_ids][]" value="{{ $loc['id'] }}" {{ in_array($loc['id'], $settings['inventory']['shopify_location_ids'] ?? []) ? 'checked' : '' }}>
                                        <span class="form-check-label">{{ $loc['name'] }}</span>
                                    </label>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Order --}}
                <div class="settings-section">
                    <div class="settings-section-header">
                        <span>Order</span>
                        <span>
                            <button type="button" class="btn btn-sm btn-outline-secondary"><i class="ri-refresh-line"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary ms-1"><i class="ri-restart-line"></i></button>
                        </span>
                    </div>
                    <div class="settings-section-body">
                        <div class="sync-toggle-row">
                            <label class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" name="order[skip_shipped_orders]" value="1" {{ ($settings['order']['skip_shipped_orders'] ?? false) ? 'checked' : '' }}>
                                <span class="form-check-label">Ignore all unpaid Orders. Unpaid Orders will not be synced with inventory</span>
                            </label>
                        </div>
                        <div class="sync-toggle-row">
                            <label class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" name="order[import_orders_to_main_store]" value="1" {{ ($settings['order']['import_orders_to_main_store'] ?? false) ? 'checked' : '' }}>
                                <span class="form-check-label">Automatically import orders to Shopify & Sync Order Status. 'Inventory Sync' is required to enable this.</span>
                            </label>
                        </div>
                        <div class="sync-toggle-row">
                            <label class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" name="order[import_orders_with_unlisted_products]" value="1" {{ ($settings['order']['import_orders_with_unlisted_products'] ?? false) ? 'checked' : '' }}>
                                <span class="form-check-label">Import Orders to Shopify even if they contain unlisted products</span>
                            </label>
                            <i class="ri-information-line text-muted"></i>
                        </div>
                        <div class="sync-toggle-row">
                            <label class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" name="order[keep_order_number_from_channel]" value="1" {{ ($settings['order']['keep_order_number_from_channel'] ?? true) ? 'checked' : '' }}>
                                <span class="form-check-label">Import and preserve Order Number from Reverb into Shopify</span>
                            </label>
                        </div>
                        <div class="mt-3">
                            <strong>Tax Rules</strong>
                            <select class="form-select form-select-sm" name="order[tax_rules]" style="width: auto;">
                                <option value="custom" {{ ($settings['order']['tax_rules'] ?? '') === 'custom' ? 'selected' : '' }}>Custom Tax Rate</option>
                            </select>
                            <p class="text-muted small mb-1">You can set custom GST/VAT rates for orders from this channel</p>
                            <label class="form-check">
                                <input type="checkbox" class="form-check-input" name="order[import_orders_without_tax]" value="1" {{ ($settings['order']['import_orders_without_tax'] ?? false) ? 'checked' : '' }}>
                                <span class="form-check-label">Import Orders Without Tax (removes tax when importing orders from Reverb)</span>
                            </label>
                        </div>
                        <div class="mt-3">
                            <strong>Send Email</strong>
                            <div class="sync-toggle-row">
                                <label class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" name="order[order_receipt_email]" value="1" {{ ($settings['order']['order_receipt_email'] ?? true) ? 'checked' : '' }}>
                                    <span class="form-check-label">Order Receipt – Whether the customers receive order confirmations from the Shopify</span>
                                </label>
                            </div>
                            <div class="sync-toggle-row">
                                <label class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" name="order[fulfillment_receipt_email]" value="1" {{ ($settings['order']['fulfillment_receipt_email'] ?? true) ? 'checked' : '' }}>
                                    <span class="form-check-label">Fulfillment Receipt – Whether the customers receive shipping confirmations from the Shopify</span>
                                </label>
                            </div>
                            <div class="sync-toggle-row">
                                <label class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" name="order[marketing_emails]" value="1" {{ ($settings['order']['marketing_emails'] ?? false) ? 'checked' : '' }}>
                                    <span class="form-check-label">Marketing Emails – Whether the customers receive email updates from Shopify</span>
                                </label>
                            </div>
                        </div>
                        <div class="mt-3">
                            <strong>Import Sales With By</strong>
                            <select class="form-select form-select-sm" name="order[import_sales_with_by]" style="width: auto;">
                                <option value="incoming_order_files" {{ ($settings['order']['import_sales_with_by'] ?? '') === 'incoming_order_files' ? 'selected' : '' }}>Incoming order files from Reverb</option>
                            </select>
                        </div>
                        <div class="mt-3">
                            <strong>Sort Order</strong>
                            <div class="d-flex gap-2 align-items-center">
                                <select class="form-select form-select-sm" name="order[sort_order_1]" style="width: auto;">
                                    <option value="latest" {{ ($settings['order']['sort_order'][0] ?? '') === 'latest' ? 'selected' : '' }}>Latest</option>
                                    <option value="oldest">Oldest</option>
                                </select>
                                <select class="form-select form-select-sm" name="order[sort_order_2]" style="width: auto;">
                                    <option value="lowest" {{ ($settings['order']['sort_order'][1] ?? '') === 'lowest' ? 'selected' : '' }}>Lowest</option>
                                    <option value="highest">Highest</option>
                                </select>
                            </div>
                            <p class="text-muted small mb-0">Set the Sort Order for displaying orders</p>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <button type="submit" class="btn btn-primary" id="save-settings-btn">Save Settings</button>
                    <span class="ms-2 text-muted small" id="save-status"></span>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('reverb-settings-form').addEventListener('submit', function(e) {
            e.preventDefault();
            var btn = document.getElementById('save-settings-btn');
            var status = document.getElementById('save-status');
            btn.disabled = true;
            status.textContent = 'Saving...';
            var formData = new FormData(this);
            var data = {};
            formData.forEach(function(value, key) {
                if (key.includes('[')) {
                    var match = key.match(/^(\w+)\[(\w+)\](?:\[(\w+)\])?$/);
                    if (match) {
                        var o = data[match[1]] = data[match[1]] || {};
                        if (match[3]) {
                            if (!o[match[2]]) o[match[2]] = [];
                            o[match[2]].push(value);
                        } else {
                            o[match[2]] = value;
                        }
                    }
                } else {
                    data[key] = value;
                }
            });
            fetch('{{ route('marketplace.settings.save', 'reverb') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': formData.get('_token'),
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(data)
            })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                status.textContent = res.success ? 'Saved.' : (res.message || 'Error');
                btn.disabled = false;
                if (res.success) setTimeout(function() { status.textContent = ''; }, 2000);
            })
            .catch(function() {
                status.textContent = 'Request failed.';
                btn.disabled = false;
            });
        });
    </script>
@endsection
