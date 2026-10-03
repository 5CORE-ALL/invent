<div class="col-auto">
    <label class="form-label small">Max Cap</label>
    <input type="number" class="form-control form-control-sm" name="inventory[max_quantity]"
           value="{{ $settings['inventory']['max_quantity'] ?? '' }}"
           min="1" step="1" placeholder="No cap" style="width: 110px;"
           title="When Shopify / CP Master stock is at or above this number, this marketplace gets exactly this qty. Below it, Qty % applies. Leave empty for no cap.">
</div>
<div class="col-12">
    <div class="form-text">
        Max Cap: stock at or above the cap → this marketplace gets exactly the cap; below it, Qty % of Shopify applies (rounded down). Empty = no cap. Example: cap 100, 20% → Shopify 200 gives 100, Shopify 90 gives 18.
    </div>
</div>
