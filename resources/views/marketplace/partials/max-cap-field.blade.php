<div class="col-auto">
    <label class="form-label small">Max Cap</label>
    <input type="number" class="form-control form-control-sm" name="inventory[max_quantity]"
           value="{{ $settings['inventory']['max_quantity'] ?? '' }}"
           min="1" step="1" placeholder="No cap" style="width: 110px;"
           title="When Shopify / CP Master stock is at or above this number, this marketplace gets exactly this qty. Below it, Qty % applies. Leave empty for no cap.">
</div>
<div class="col-auto">
    <label class="form-label small">Qty adjust</label>
    <input type="number" class="form-control form-control-sm" name="inventory[quantity_offset]"
           value="{{ (int) ($settings['inventory']['quantity_offset'] ?? 0) }}"
           min="-999" max="0" step="1" placeholder="0" style="width: 110px;"
           title="Subtract this many units from the qty sent to this marketplace (e.g. -1 sends Shopify qty minus 1, -2 sends minus 2). Never goes below 0. 0 = no adjustment.">
</div>
<div class="col-12">
    <div class="form-text">
        Max Cap: stock at or above the cap → this marketplace gets exactly the cap; below it, Qty % of Shopify applies (rounded down). Empty = no cap. Example: cap 100, 20% → Shopify 200 gives 100, Shopify 90 gives 18.
    </div>
    <div class="form-text">
        Qty adjust: applied last, after Qty % and Max Cap. Enter -1 to send 1 less than Shopify, -2 for 2 less, and so on (never below 0). 0 = send the normal qty. Example: -1 → Shopify 10 sends 9, Shopify 1 sends 0.
    </div>
</div>
