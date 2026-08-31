@php
    $mmSlug = $marketplaceSlug ?? request()->route('marketplace') ?? 'amazon';
@endphp
<div class="modal fade" id="mmInstantMapModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title">Link Shopify SKU</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2" id="mmInstantMapHint">
                    Map this Shopify SKU to the marketplace listing (same SKU), then push qty using inventory rules.
                </p>
                <div class="mb-2">
                    <label class="form-label small mb-0">Shopify SKU to link</label>
                    <input type="text" class="form-control form-control-sm" id="mmInstantMapShopifySku" readonly>
                </div>
                <div class="mb-2">
                    <label class="form-label small mb-0">Or pick a different Shopify SKU</label>
                    <input type="text" class="form-control form-control-sm" id="mmInstantMapSearch" placeholder="Search SKU or title…" autocomplete="off">
                    <div id="mmInstantMapSearchResults" class="list-group mt-1 small" style="max-height: 180px; overflow: auto;"></div>
                </div>
                <div class="mb-2" id="mmInstantMapIdWrap" style="display:none;">
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
                <button type="button" class="btn btn-sm btn-primary" id="mmInstantMapSave">
                    <i class="ri-link"></i> Link this SKU
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
    var searchUrl = productsBase + '/shopify-search';
    var fetchUrl = '{{ route("marketplace.manager.refresh.shopify") }}';
    var fetchStatusUrl = '{{ route("marketplace.manager.refresh.shopify.status") }}';
    var pendingId = null;
    var pendingSku = null;
    var searchTimer = null;

    function headers() {
        return {
            'X-CSRF-TOKEN': csrf,
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        };
    }

    function parseJson(r) {
        return r.text().then(function (text) {
            var data = {};
            try { data = text ? JSON.parse(text) : {}; } catch (e) {
                data = { success: false, message: 'Server error (' + r.status + ').' };
            }
            if (typeof data !== 'object' || data === null) {
                data = { success: false, message: 'Invalid server response.' };
            }
            data._http = r.status;
            return data;
        });
    }

    function postLink(id, marketplaceId, skuId) {
        var body = {};
        if (marketplaceId) body.marketplace_id = marketplaceId;
        if (skuId) body.sku_id = skuId;
        return fetch(productsBase + '/' + id + '/link', {
            method: 'POST',
            headers: headers(),
            credentials: 'same-origin',
            body: JSON.stringify(body)
        }).then(parseJson);
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

    function showModal() {
        var modalEl = document.getElementById('mmInstantMapModal');
        if (modalEl && window.bootstrap && window.bootstrap.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
            return true;
        }
        return false;
    }

    function hideModal() {
        var modalEl = document.getElementById('mmInstantMapModal');
        if (modalEl && window.bootstrap && window.bootstrap.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        }
    }

    function openMapModal(id, sku, extra) {
        extra = extra || {};
        pendingId = id;
        pendingSku = sku;
        var hint = document.getElementById('mmInstantMapHint');
        var skuInput = document.getElementById('mmInstantMapShopifySku');
        var idWrap = document.getElementById('mmInstantMapIdWrap');
        var idLabel = document.getElementById('mmInstantMapIdLabel');
        var idInput = document.getElementById('mmInstantMapId');
        var skuWrap = document.getElementById('mmInstantMapSkuIdWrap');
        var skuIdInput = document.getElementById('mmInstantMapSkuId');
        var results = document.getElementById('mmInstantMapSearchResults');
        var search = document.getElementById('mmInstantMapSearch');
        if (skuInput) skuInput.value = sku || '';
        if (search) search.value = '';
        if (results) results.innerHTML = '';
        if (hint) {
            hint.textContent = extra.message
                || ('Link Shopify SKU ' + (sku || '') + ' to this marketplace using the exact SKU, then push inventory.');
        }
        var needId = !!extra.needs_id;
        if (idWrap) idWrap.style.display = needId ? '' : 'none';
        if (idLabel) idLabel.textContent = extra.id_label || 'Marketplace ID';
        if (idInput) {
            idInput.value = extra.product_id || '';
            idInput.placeholder = extra.id_label || 'ID';
        }
        if (skuWrap) skuWrap.style.display = extra.needs_sku_id ? '' : 'none';
        if (skuIdInput) skuIdInput.value = extra.sku_id || '';
        if (!showModal()) {
            if (needId) {
                var pasted = window.prompt((extra.id_label || 'Marketplace ID') + ' for ' + (sku || 'SKU'), extra.product_id || '');
                if (!pasted) return;
                postLink(id, pasted.trim(), null).then(afterLink).catch(function () { alert('Request failed.'); });
                return;
            }
            if (!window.confirm('Link Shopify SKU ' + (sku || '') + ' to this marketplace and push inventory?')) return;
            postLink(id, null, null).then(function (data) {
                if (data.needs_id) {
                    openMapModal(id, sku, data);
                    return;
                }
                afterLink(data);
            }).catch(function () { alert('Request failed.'); });
        }
    }

    function runSearch(q) {
        var box = document.getElementById('mmInstantMapSearchResults');
        if (!box) return;
        if (!q || q.trim().length < 2) {
            box.innerHTML = '';
            return;
        }
        fetch(searchUrl + '?q=' + encodeURIComponent(q.trim()), {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin'
        }).then(parseJson).then(function (data) {
            var rows = data.rows || [];
            if (!rows.length) {
                box.innerHTML = '<div class="list-group-item text-muted">No Shopify SKUs found.</div>';
                return;
            }
            box.innerHTML = rows.map(function (row) {
                var qty = (row.qty === null || row.qty === undefined) ? '' : (' · qty ' + row.qty);
                var sku = String(row.sku || '');
                var title = String(row.title || '').slice(0, 60);
                var esc = function (s) {
                    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
                };
                return '<button type="button" class="list-group-item list-group-item-action js-mm-pick-sku"'
                    + ' data-id="' + row.id + '" data-sku="' + esc(sku) + '">'
                    + '<code>' + esc(sku) + '</code>'
                    + (title ? (' — ' + esc(title)) : '')
                    + qty
                    + '</button>';
            }).join('');
        }).catch(function () {
            box.innerHTML = '<div class="list-group-item text-danger">Search failed.</div>';
        });
    }

    document.getElementById('mmInstantMapSearch')?.addEventListener('input', function () {
        var q = this.value;
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () { runSearch(q); }, 250);
    });

    document.getElementById('mmInstantMapSearchResults')?.addEventListener('click', function (e) {
        var pick = e.target.closest('.js-mm-pick-sku');
        if (!pick) return;
        pendingId = pick.getAttribute('data-id');
        pendingSku = pick.getAttribute('data-sku') || '';
        var skuInput = document.getElementById('mmInstantMapShopifySku');
        if (skuInput) skuInput.value = pendingSku;
        this.innerHTML = '';
        var search = document.getElementById('mmInstantMapSearch');
        if (search) search.value = '';
        var hint = document.getElementById('mmInstantMapHint');
        if (hint) hint.textContent = 'Will link Shopify SKU ' + pendingSku + ' using the exact SKU.';
        var idWrap = document.getElementById('mmInstantMapIdWrap');
        if (idWrap) idWrap.style.display = 'none';
    });

    document.getElementById('mmInstantMapSave')?.addEventListener('click', function () {
        if (!pendingId) {
            alert('Pick a Shopify SKU first.');
            return;
        }
        var btn = this;
        var marketplaceId = (document.getElementById('mmInstantMapId')?.value || '').trim();
        var skuId = (document.getElementById('mmInstantMapSkuId')?.value || '').trim();
        var idWrap = document.getElementById('mmInstantMapIdWrap');
        var idVisible = idWrap && idWrap.style.display !== 'none';
        if (idVisible && !marketplaceId) {
            alert('Enter the marketplace ID, or clear that field and try exact SKU match.');
            return;
        }
        btn.disabled = true;
        var original = btn.innerHTML;
        btn.innerHTML = '<i class="ri-loader-4-line"></i> Linking…';
        postLink(pendingId, marketplaceId || null, skuId || null)
            .then(function (data) {
                if (data.success) {
                    hideModal();
                    afterLink(data);
                    return;
                }
                if (data.needs_id) {
                    openMapModal(pendingId, pendingSku, data);
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

    function onLinkButtonClick(e) {
        var btn = e.target.closest('.js-mm-link-sku');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();
        var id = btn.getAttribute('data-id');
        var sku = btn.getAttribute('data-sku') || '';
        if (!id) {
            alert('This row has no Shopify SKU id.');
            return;
        }
        openMapModal(id, sku, {});
    }

    document.addEventListener('click', onLinkButtonClick, true);

    document.getElementById('btn-fetch-new-listings')?.addEventListener('click', function () {
        var btn = this;
        btn.disabled = true;
        var original = btn.innerHTML;
        btn.innerHTML = '<i class="ri-loader-4-line"></i> Fetching…';

        fetch(fetchUrl, { method: 'POST', headers: headers(), credentials: 'same-origin' })
            .then(parseJson)
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
                    fetch(fetchStatusUrl, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                        .then(parseJson)
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
