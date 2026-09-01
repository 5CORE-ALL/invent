@if(in_array(($linkTab ?? ''), ['mismatch', 'linked_mismatch'], true))
    <button type="button" class="btn btn-sm btn-warning" id="btn-sync-mismatch-now" data-scope="{{ $linkTab }}">
        <i class="ri-upload-2-line"></i> Push Shopify inventory
    </button>
@endif
