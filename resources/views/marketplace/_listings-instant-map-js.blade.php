@php
    $mmSlug = $marketplaceSlug ?? request()->route('marketplace') ?? 'amazon';
@endphp
<div class="modal fade" id="mmInstantMapModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title">Map Shopify SKU</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2" id="mmInstantMapHint"></p>
                <div class="mb-2">
                    <label class="form-label small mb-0" id="mmInstantMapIdLabel">Marketplace ID</label>
                    <input type="text" class="form-control form-control-sm" id="mmInstantMapId" autocomplete="off">
                </div>
                <div class="mb-0" id="mmInstantMapSkuIdWrap" style="display:none;">
                    <label class="form-label small mb-0">TikTok SKU ID</label>
                    <input type="text" class="form-control form-control-sm" id="mmInstantMapSkuId" autocomplete="off">
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm btn-success" id="mmInstantMapSave">
                    <i class="ri-link"></i> Link now
                </button>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    var csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';
    var slug = @json($mmSlug);
    var productsBase = '{{ url("marketplace") }}/' + slug + '/products';
    var fetchUrl = '{{ route("marketplace.manager.refresh.shopify") }}';
    var fetchStatusUrl = '{{ route("marketplace.manager.refresh.shopify.status") }}';
    var pendingId = null;
    var pendingSku = null;

    function headers() {
        return {
            'X-CSRF-TOKEN': csrf,
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        };
    }

    function postLink(id, marketplaceId, skuId) {
        var body = {};
        if (marketplaceId) body.marketplace_id = marketplaceId;
        if (skuId) body.sku_id = skuId;
        return fetch(productsBase + '/' + id + '/link', {
            method: 'POST',
            headers: headers(),
            body: JSON.stringify(body)
        }).then(function (r) {
            return r.json().then(function (data) {
                data._http = r.status;
                return data;
            });
        });
    }

    function afterLink(data) {
        var msg = data.message || (data.success ? 'Linked.' : 'Could not link.');
        if (data.inventory && data.inventory.message) {
            msg += '\n' + data.inventory.message;
        }
        alert(msg);
        if (data.success) {
            location.reload();
        }
    }

    function openMapModal(data, id, sku) {
        pendingId = id;
        pendingSku = sku;
        var hint = document.getElementById('mmInstantMapHint');
        var label = document.getElementById('mmInstantMapIdLabel');
        var input = document.getElementById('mmInstantMapId');
        var skuWrap = document.getElementById('mmInstantMapSkuIdWrap');
        var skuInput = document.getElementById('mmInstantMapSkuId');
        if (hint) hint.textContent = (data.message || '') + (sku ? ' SKU: ' + sku : '');
        if (label) label.textContent = data.id_label || 'Marketplace ID';
        if (input) {
            input.value = data.product_id || '';
            input.placeholder = data.id_label || 'ID';
        }
        if (skuWrap) skuWrap.style.display = data.needs_sku_id ? '' : 'none';
        if (skuInput) skuInput.value = data.sku_id || '';
        var modalEl = document.getElementById('mmInstantMapModal');
        if (modalEl && window.bootstrap) {
            window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
            setTimeout(function () { input && input.focus(); }, 200);
        } else {
            var pasted = window.prompt((data.id_label || 'Marketplace ID') + ' for ' + (sku || 'SKU'), data.product_id || '');
            if (!pasted) return;
            postLink(id, pasted.trim(), null).then(afterLink).catch(function () { alert('Request failed.'); });
        }
    }

    document.getElementById('mmInstantMapSave')?.addEventListener('click', function () {
        if (!pendingId) return;
        var btn = this;
        var marketplaceId = (document.getElementById('mmInstantMapId')?.value || '').trim();
        var skuId = (document.getElementById('mmInstantMapSkuId')?.value || '').trim();
        if (!marketplaceId) {
            alert('Enter the marketplace ID first.');
            return;
        }
        btn.disabled = true;
        postLink(pendingId, marketplaceId, skuId || null)
            .then(function (data) {
                if (data.success) {
                    var modalEl = document.getElementById('mmInstantMapModal');
                    if (modalEl && window.bootstrap) {
                        window.bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                    }
                }
                afterLink(data);
            })
            .catch(function () { alert('Request failed.'); })
            .finally(function () { btn.disabled = false; });
    });

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.js-mm-link-sku');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        var id = btn.getAttribute('data-id');
        var sku = btn.getAttribute('data-sku') || '';
        if (!id) return;
        btn.disabled = true;
        var original = btn.innerHTML;
        btn.innerHTML = '<i class="ri-loader-4-line"></i>';
        postLink(id, null, null)
            .then(function (data) {
                if (data.success) {
                    afterLink(data);
                    return;
                }
                if (data.needs_id) {
                    openMapModal(data, id, sku);
                    return;
                }
                alert(data.message || 'Could not link this SKU.');
            })
            .catch(function () { alert('Request failed.'); })
            .finally(function () {
                btn.disabled = false;
                btn.innerHTML = original;
            });
    });

    document.getElementById('btn-fetch-new-listings')?.addEventListener('click', function () {
        var btn = this;
        btn.disabled = true;
        var original = btn.innerHTML;
        btn.innerHTML = '<i class="ri-loader-4-line"></i> Fetching…';

        fetch(fetchUrl, { method: 'POST', headers: headers() })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success && data.queued !== true) {
                    alert(data.message || 'Could not queue Shopify catalog refresh.');
                    btn.disabled = false;
                    btn.innerHTML = original;
                    return;
                }
                var tries = 0;
                var maxTries = 90;
                function poll() {
                    tries++;
                    fetch(fetchStatusUrl, { headers: { 'Accept': 'application/json' } })
                        .then(function (r) { return r.json(); })
                        .then(function (st) {
                            var status = (st.status && st.status.status) ? st.status.status : '';
                            if (status === 'done' || status === 'partial' || status === 'failed') {
                                if (status === 'failed') {
                                    alert((st.status && st.status.error) ? st.status.error : 'Shopify catalog refresh failed.');
                                    btn.disabled = false;
                                    btn.innerHTML = original;
                                    return;
                                }
                                location.reload();
                                return;
                            }
                            if (tries >= maxTries) {
                                alert('Shopify catalog refresh is still running. Reload in a minute to see new SKUs.');
                                btn.disabled = false;
                                btn.innerHTML = original;
                                return;
                            }
                            btn.innerHTML = '<i class="ri-loader-4-line"></i> Syncing Shopify…';
                            setTimeout(poll, 4000);
                        })
                        .catch(function () {
                            btn.disabled = false;
                            btn.innerHTML = original;
                            alert('Could not check Shopify refresh status.');
                        });
                }
                setTimeout(poll, 2000);
            })
            .catch(function () {
                btn.disabled = false;
                btn.innerHTML = original;
                alert('Could not start Shopify catalog refresh.');
            });
    });
})();
</script>
