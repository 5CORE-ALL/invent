(function () {
    if (window.__listingPageToolsInit) return;
    window.__listingPageToolsInit = true;

    (function rewriteListingPublishUrls() {
        const c = window.listingPageConfig || {};
        const channel = String(c.channel || '').replace(/[^a-z0-9]/gi, '');
        if (!channel) return;
        const url = '/listing_' + channel + '/save-status';
        c.saveStatusUrl = url;
        c.previewUrl = url;
        c.publishUrl = url;
        window.listingPageConfig = c;
    })();

    function cfg() {
        return window.listingPageConfig || {};
    }

    function csrf() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    function escapeHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function notify(type, message) {
        showPublishStatus(type === 'danger' ? 'error' : 'success', message);
    }

    function ensurePublishStatusOverlay() {
        let el = document.getElementById('listing-publish-status');
        if (el) return el;
        el = document.createElement('div');
        el.id = 'listing-publish-status';
        el.className = 'listing-publish-status-overlay';
        el.hidden = true;
        el.innerHTML = '<div class="listing-publish-status-card" role="alertdialog" aria-modal="true">' +
            '<div id="listing-publish-status-icon" class="listing-publish-status-icon"></div>' +
            '<h3 id="listing-publish-status-title">Publishing…</h3>' +
            '<p id="listing-publish-status-message" class="listing-publish-status-message"></p>' +
            '<button type="button" class="btn btn-primary" id="listing-publish-status-close" hidden>Close</button>' +
            '</div>';
        document.body.appendChild(el);
        return el;
    }

    function hidePublishStatus() {
        const el = document.getElementById('listing-publish-status');
        if (!el) return;
        el.hidden = true;
        el.classList.remove('is-loading', 'is-success', 'is-error');
    }

    function showPublishStatus(state, message, title) {
        const el = ensurePublishStatusOverlay();
        const icon = document.getElementById('listing-publish-status-icon');
        const titleEl = document.getElementById('listing-publish-status-title');
        const msgEl = document.getElementById('listing-publish-status-message');
        const closeBtn = document.getElementById('listing-publish-status-close');
        el.classList.remove('is-loading', 'is-success', 'is-error');
        el.classList.add(state === 'error' ? 'is-error' : (state === 'success' ? 'is-success' : 'is-loading'));
        if (icon) {
            icon.innerHTML = state === 'loading'
                ? '<i class="fas fa-spinner fa-spin"></i>'
                : (state === 'success' ? '<i class="fas fa-check"></i>' : '<i class="fas fa-exclamation-triangle"></i>');
        }
        if (titleEl) {
            titleEl.textContent = title || (state === 'loading' ? 'Publishing…' : (state === 'success' ? 'Published' : 'Publish failed'));
        }
        if (msgEl) msgEl.textContent = String(message || '');
        if (closeBtn) closeBtn.hidden = state === 'loading';
        if (el.parentNode !== document.body) {
            document.body.appendChild(el);
        }
        el.hidden = false;
    }

    function findTable() {
        const id = cfg().tableId;
        if (!id || typeof Tabulator === 'undefined') return null;
        if (typeof Tabulator.findTable === 'function') {
            const found = Tabulator.findTable('#' + id);
            if (Array.isArray(found) && found[0]) return found[0];
            if (found && !Array.isArray(found)) return found;
        }
        return null;
    }

    function waitForTable(cb) {
        let n = 0;
        const t = setInterval(function () {
            const table = findTable();
            n += 1;
            if (table || n > 80) {
                clearInterval(t);
                if (table) cb(table);
            }
        }, 250);
    }

    function csvCell(value) {
        const text = String(value ?? '');
        if (/[",\r\n]/.test(text)) return '"' + text.replace(/"/g, '""') + '"';
        return text;
    }

    function exportFiltered(table) {
        const rows = (table.getData('active') || []).filter(function (row) {
            return !row.is_parent;
        });
        if (!rows.length) {
            notify('danger', 'No filtered rows to export.');
            return;
        }
        const header = ['parent', 'sku', 'INV', 'nr_req', 'listed', 'buyer_link', 'seller_link'];
        const lines = [header.join(',')];
        rows.forEach(function (row) {
            const listed = row.listed === 'Pending' ? 'Missing L' : (row.listed || '');
            const nrReq = row.nr_req === 'NR' ? 'NRL' : (row.nr_req || '');
            lines.push([
                csvCell(row.parent), csvCell(row.sku), csvCell(row.INV),
                csvCell(nrReq), csvCell(listed),
                csvCell(row.buyer_link), csvCell(row.seller_link)
            ].join(','));
        });
        const listedFilter = document.getElementById('listed-filter');
        const suffix = listedFilter && listedFilter.value === 'Pending' ? 'missing_l' : 'filtered';
        const blob = new Blob(['\uFEFF' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = (cfg().exportName || 'listing') + '_' + suffix + '.csv';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(link.href);
        notify('success', 'Exported ' + rows.length + ' filtered row' + (rows.length === 1 ? '' : 's') + '.');
    }

    function copySku(text, btn) {
        const done = function () {
            notify('success', 'Copied: ' + text);
            if (!btn) return;
            btn.classList.add('is-copied');
            const icon = btn.querySelector('i');
            if (icon) {
                icon.classList.remove('fa-copy');
                icon.classList.add('fa-check');
                setTimeout(function () {
                    btn.classList.remove('is-copied');
                    icon.classList.remove('fa-check');
                    icon.classList.add('fa-copy');
                }, 1200);
            }
        };
        if (window.isSecureContext && navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(done).catch(fallback);
        } else {
            fallback();
        }

        function fallback() {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.setAttribute('readonly', '');
            ta.style.position = 'fixed';
            ta.style.top = '0';
            ta.style.left = '0';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.focus();
            ta.select();
            try {
                document.execCommand('copy');
                done();
            } catch (err) {
                notify('danger', 'Could not copy SKU');
            }
            document.body.removeChild(ta);
        }
    }

    function skuFormatter(cell) {
        const sku = String(cell.getValue() || '').trim();
        if (!sku) return '';
        const safe = escapeHtml(sku);
        return '<span class="sku-cell"><span class="sku-cell-text">' + safe +
            '</span><button type="button" class="copy-sku-btn" data-sku="' + safe +
            '" title="Copy SKU"><i class="fas fa-copy"></i></button></span>';
    }

    function publishFormatter(cell) {
        const data = cell.getRow().getData();
        if (data.is_parent) return '';
        const listed = String(data.listed || '');
        const goodsId = String(data.goods_id || data.listing_id || data.eBay_item_id || data.item_id || data.product_id || '').trim();
        if (listed === 'Listed' || goodsId) {
            return '<span class="text-muted" title="Already listed">—</span>';
        }
        const nrReq = String(data.nr_req || 'REQ');
        if (nrReq === 'NR' || nrReq === 'NRL') {
            return '<span class="text-muted" title="NRL SKUs are not published">—</span>';
        }
        const sku = String(data.sku || '').trim();
        if (!sku) return '';
        const label = escapeHtml(cfg().channelLabel || 'marketplace');
        return '<button type="button" class="listing-mp-publish-btn" data-sku="' + escapeHtml(sku) +
            '" title="Choose single listing or your own variation group for ' +
            label + '"><i class="fas fa-cloud-upload-alt"></i> Publish</button>';
    }

    let previewSeedSkus = [];
    let lastPreviewGroups = [];
    let reverbCatTimer = null;
    let reverbCatXhr = null;
    let ebayCatTimer = null;
    let ebayCatXhr = null;
    let tiktokCatTimer = null;
    let tiktokCatXhr = null;
    let sheinCatTimer = null;
    let sheinCatXhr = null;
    let wayfairCatTimer = null;
    let wayfairCatXhr = null;

    function selectedPublishMode() {
        const checked = document.querySelector('input[name="listing-publish-mode"]:checked');
        const mode = String((checked && checked.value) || cDefaultPublishMode()).toLowerCase();
        return mode === 'variation' ? 'variation' : 'single';
    }

    function cDefaultPublishMode() {
        const raw = String(cfg().defaultPublishMode || 'single').toLowerCase();
        return raw === 'variation' ? 'variation' : 'single';
    }

    function isAliexpressChannel() {
        return String(cfg().channel || '').toLowerCase() === 'aliexpress';
    }

    function isWayfairChannel() {
        return String(cfg().channel || '').toLowerCase() === 'wayfair';
    }

    function isReverbChannel() {
        const c = String(cfg().channel || '').toLowerCase();
        return c === 'reverb' || c === 'reverbcom';
    }

    function isEbayChannel() {
        const c = String(cfg().channel || '').toLowerCase().replace(/[\s_-]/g, '');
        return c === 'ebay' || c === 'ebay1' || c === 'ebayone'
            || c === 'ebay2' || c === 'ebaytwo'
            || c === 'ebay3' || c === 'ebaythree';
    }

    function isTiktokChannel() {
        const c = String(cfg().channel || '').toLowerCase().replace(/[\s_-]/g, '');
        return c === 'tiktok' || c === 'tiktokshop' || c === 'tiktok1'
            || c === 'tiktok2' || c === 'tiktokshop2' || c === 'tiktoktwo';
    }

    function isSheinChannel() {
        return String(cfg().channel || '').toLowerCase().replace(/[\s_-]/g, '') === 'shein';
    }

    function selectedCategoryId() {
        if (isWayfairChannel()) {
            const wf = document.getElementById('listing-publish-wayfair-class-id');
            if (wf) return String(wf.value || '').replace(/\D+/g, '');
        }
        if (isEbayChannel()) {
            const ebayId = document.getElementById('listing-publish-ebay-category-id');
            if (ebayId) return String(ebayId.value || '').replace(/\D+/g, '');
        }
        if (isTiktokChannel()) {
            const tt = document.getElementById('listing-publish-tiktok-category-id');
            if (tt) return String(tt.value || '').replace(/\D+/g, '');
        }
        if (isSheinChannel()) {
            const shein = document.getElementById('listing-publish-shein-category-id');
            if (shein) return String(shein.value || '').replace(/\D+/g, '');
        }
        const el = document.getElementById('listing-publish-category-id');
        return el ? String(el.value || '').replace(/\D+/g, '') : '';
    }

    function selectedCategoryName() {
        if (isReverbChannel()) {
            const reverbEl = document.getElementById('listing-publish-reverb-category-name');
            if (reverbEl) return String(reverbEl.value || '').trim();
        }
        if (isEbayChannel()) {
            const ebayEl = document.getElementById('listing-publish-ebay-category-name');
            if (ebayEl) return String(ebayEl.value || '').trim();
        }
        if (isTiktokChannel()) {
            const tt = document.getElementById('listing-publish-tiktok-category-name');
            if (tt) return String(tt.value || '').trim();
        }
        if (isSheinChannel()) {
            const shein = document.getElementById('listing-publish-shein-category-name');
            if (shein) return String(shein.value || '').trim();
        }
        if (isWayfairChannel()) {
            const wf = document.getElementById('listing-publish-wayfair-class-name');
            if (wf) return String(wf.value || '').trim();
        }
        const el = document.getElementById('listing-publish-category-name');
        return el ? String(el.value || '').trim() : '';
    }

    function selectedCategoryUuid() {
        const el = document.getElementById('listing-publish-category-uuid');
        return el ? String(el.value || '').trim() : '';
    }

    function applySuggestedCategory(suggested) {
        const pathEl = document.getElementById('listing-publish-reverb-category-path');
        const uuidEl = document.getElementById('listing-publish-category-uuid');
        const nameEl = document.getElementById('listing-publish-reverb-category-name');
        const typed = nameEl && nameEl.dataset.userTyped === '1'
            ? String(nameEl.value || '').trim()
            : '';
        if (typed.length >= 2) {
            searchReverbCategories(typed);
            return;
        }
        const path = String((suggested && suggested.path) || '').trim();
        const id = String((suggested && suggested.id) || '').trim();
        const name = String((suggested && suggested.name) || '').trim();
        const leaf = path.split(/[>\/|]/).pop().trim();
        if (uuidEl) uuidEl.value = id;
        if (nameEl && !nameEl.value.trim()) nameEl.value = name || leaf || '';
        if (pathEl) {
            pathEl.textContent = path || (id
                ? 'Reverb category matched from the product type.'
                : (name
                    ? 'Using the product category. Pick a Reverb match below.'
                    : 'Type a category name to search Reverb.'));
        }
        const query = String((nameEl && nameEl.value) || name || leaf || '').trim();
        if (query.length >= 2) searchReverbCategories(query);
        else hideReverbCategoryResults();
    }

    function hideReverbCategoryResults() {
        const box = document.getElementById('listing-publish-reverb-category-results');
        if (!box) return;
        box.classList.remove('is-open');
        box.innerHTML = '';
    }

    function showReverbCategoryResults(html) {
        const box = document.getElementById('listing-publish-reverb-category-results');
        if (!box) return;
        box.innerHTML = html;
        box.classList.add('is-open');
        box.hidden = false;
    }

    function reverbCategorySearchUrl() {
        return cfg().categorySearchUrl || '/listing-manager/ebay/categories';
    }

    function searchReverbCategories(query) {
        query = String(query || '').trim();
        const box = document.getElementById('listing-publish-reverb-category-results');
        if (!box || !isReverbChannel()) return;
        if (query.length < 2) {
            hideReverbCategoryResults();
            return;
        }
        showReverbCategoryResults('<div class="listing-publish-cat-empty">Searching Reverb categories…</div>');
        if (reverbCatXhr && reverbCatXhr.abort) reverbCatXhr.abort();
        reverbCatXhr = $.ajax({
            url: reverbCategorySearchUrl(),
            type: 'GET',
            data: {
                q: query,
                channel: 'reverb',
                title: query
            },
            dataType: 'json',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            success: function (res) {
                const rows = (res && res.categories) || [];
                if (!rows.length) {
                    showReverbCategoryResults('<div class="listing-publish-cat-empty">' +
                        escapeHtml((res && res.message) || 'No Reverb categories matched.') + '</div>');
                    return;
                }
                showReverbCategoryResults(rows.map(function (row) {
                    return '<button type="button" class="listing-publish-cat-item" data-id="' +
                        escapeHtml(row.id || '') + '" data-path="' + escapeHtml(row.path || '') + '">' +
                        escapeHtml(row.path || row.id || '') + '</button>';
                }).join(''));
            },
            error: function (xhr, status) {
                if (status === 'abort') return;
                showReverbCategoryResults('<div class="listing-publish-cat-empty">' +
                    escapeHtml(ajaxError(xhr) || 'Category search failed.') + '</div>');
            }
        });
    }

    function scheduleReverbCategorySearch(query) {
        clearTimeout(reverbCatTimer);
        reverbCatTimer = setTimeout(function () { searchReverbCategories(query); }, 280);
    }

    function pickReverbCategory(id, path) {
        const uuidEl = document.getElementById('listing-publish-category-uuid');
        const nameEl = document.getElementById('listing-publish-reverb-category-name');
        const pathEl = document.getElementById('listing-publish-reverb-category-path');
        if (uuidEl) uuidEl.value = String(id || '').trim();
        if (nameEl) nameEl.value = String(path || '').trim();
        if (pathEl) pathEl.textContent = String(path || '').trim() || 'Reverb category selected.';
        hideReverbCategoryResults();
    }

    function applySuggestedWayfairCategory(suggested) {
        const pathEl = document.getElementById('listing-publish-wayfair-category-path');
        const idEl = document.getElementById('listing-publish-wayfair-class-id');
        const nameEl = document.getElementById('listing-publish-wayfair-class-name');
        const typed = nameEl && nameEl.dataset.userTyped === '1'
            ? String(nameEl.value || '').trim()
            : '';
        if (typed.length >= 2) {
            searchWayfairClasses(typed);
            return;
        }
        const path = String((suggested && suggested.path) || '').trim();
        const rawId = String((suggested && suggested.id) || '').replace(/\D+/g, '');
        const id = rawId === '0' ? '' : rawId;
        const name = String((suggested && suggested.name) || '').trim();
        const leaf = path.split(/[>\-\/|]/).pop().trim();
        if (idEl && id) idEl.value = id;
        if (nameEl && !String(nameEl.value || '').trim()) nameEl.value = name || leaf || '';
        if (pathEl) {
            pathEl.textContent = path || (id
                ? 'Wayfair class matched from a listed sibling or title.'
                : 'No class matched yet. Type a Wayfair class name, then pick one from the list.');
        }
        const query = String((nameEl && nameEl.value) || name || leaf || '').trim();
        if (query.length >= 2) searchWayfairClasses(query);
        else hideWayfairClassResults();
    }

    function hideWayfairClassResults() {
        const box = document.getElementById('listing-publish-wayfair-class-results');
        if (!box) return;
        box.classList.remove('is-open');
        box.innerHTML = '';
    }

    function showWayfairClassResults(html) {
        const box = document.getElementById('listing-publish-wayfair-class-results');
        if (!box) return;
        box.innerHTML = html;
        box.classList.add('is-open');
        box.hidden = false;
    }

    function searchWayfairClasses(query) {
        query = String(query || '').trim();
        const box = document.getElementById('listing-publish-wayfair-class-results');
        if (!box || !isWayfairChannel()) return;
        if (query.length < 2) {
            hideWayfairClassResults();
            return;
        }
        showWayfairClassResults('<div class="listing-publish-cat-empty">Searching Wayfair classes…</div>');
        if (wayfairCatXhr && wayfairCatXhr.abort) wayfairCatXhr.abort();
        wayfairCatXhr = $.ajax({
            url: cfg().categorySearchUrl || '/listing-manager/ebay/categories',
            type: 'GET',
            data: {
                q: query,
                channel: cfg().channel || 'wayfair',
                title: query
            },
            dataType: 'json',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            success: function (res) {
                const rows = (res && res.categories) || [];
                if (!rows.length) {
                    showWayfairClassResults('<div class="listing-publish-cat-empty">' +
                        escapeHtml((res && res.message) || 'No Wayfair classes matched.') + '</div>');
                    return;
                }
                showWayfairClassResults(rows.map(function (row) {
                    return '<button type="button" class="listing-publish-cat-item listing-publish-wayfair-cat-item" data-id="' +
                        escapeHtml(row.id || '') + '" data-path="' + escapeHtml(row.path || '') + '">' +
                        escapeHtml(row.path || row.id || '') + '</button>';
                }).join(''));
            },
            error: function (xhr, status) {
                if (status === 'abort') return;
                showWayfairClassResults('<div class="listing-publish-cat-empty">' +
                    escapeHtml(ajaxError(xhr) || 'Class search failed.') + '</div>');
            }
        });
    }

    function scheduleWayfairClassSearch(query) {
        clearTimeout(wayfairCatTimer);
        wayfairCatTimer = setTimeout(function () { searchWayfairClasses(query); }, 280);
    }

    function pickWayfairClass(id, path) {
        const idEl = document.getElementById('listing-publish-wayfair-class-id');
        const nameEl = document.getElementById('listing-publish-wayfair-class-name');
        const pathEl = document.getElementById('listing-publish-wayfair-category-path');
        if (idEl) idEl.value = String(id || '').replace(/\D+/g, '');
        if (nameEl) nameEl.value = String(path || '').trim();
        if (pathEl) pathEl.textContent = String(path || '').trim() || 'Wayfair class selected.';
        hideWayfairClassResults();
    }

    function applySuggestedEbayCategory(suggested) {
        const pathEl = document.getElementById('listing-publish-ebay-category-path');
        const idEl = document.getElementById('listing-publish-ebay-category-id');
        const nameEl = document.getElementById('listing-publish-ebay-category-name');
        const typed = nameEl && nameEl.dataset.userTyped === '1'
            ? String(nameEl.value || '').trim()
            : '';
        if (typed.length >= 2) {
            searchEbayCategories(typed);
            return;
        }
        const path = String((suggested && suggested.path) || '').trim();
        const id = String((suggested && suggested.id) || '').replace(/\D+/g, '');
        const name = String((suggested && suggested.name) || '').trim();
        const leaf = path.split(/[>\/|]/).pop().trim();
        if (idEl && id) idEl.value = id;
        if (nameEl && !String(nameEl.value || '').trim()) nameEl.value = name || leaf || '';
        if (pathEl) {
            pathEl.textContent = path || (id
                ? 'eBay category matched from a listed sibling or title.'
                : 'No category matched yet. Type an eBay category name, or publish and we will try from the title.');
        }
        const query = String((nameEl && nameEl.value) || name || leaf || '').trim();
        if (query.length >= 2) searchEbayCategories(query);
        else hideEbayCategoryResults();
    }

    function hideEbayCategoryResults() {
        const box = document.getElementById('listing-publish-ebay-category-results');
        if (!box) return;
        box.classList.remove('is-open');
        box.innerHTML = '';
    }

    function showEbayCategoryResults(html) {
        const box = document.getElementById('listing-publish-ebay-category-results');
        if (!box) return;
        box.innerHTML = html;
        box.classList.add('is-open');
        box.hidden = false;
    }

    function searchEbayCategories(query) {
        query = String(query || '').trim();
        const box = document.getElementById('listing-publish-ebay-category-results');
        if (!box || !isEbayChannel()) return;
        if (query.length < 2) {
            hideEbayCategoryResults();
            return;
        }
        showEbayCategoryResults('<div class="listing-publish-cat-empty">Searching eBay categories…</div>');
        if (ebayCatXhr && ebayCatXhr.abort) ebayCatXhr.abort();
        ebayCatXhr = $.ajax({
            url: cfg().categorySearchUrl || '/listing-manager/ebay/categories',
            type: 'GET',
            data: {
                q: query,
                channel: cfg().channel || 'ebaytwo',
                title: query
            },
            dataType: 'json',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            success: function (res) {
                const rows = (res && res.categories) || [];
                if (!rows.length) {
                    showEbayCategoryResults('<div class="listing-publish-cat-empty">' +
                        escapeHtml((res && res.message) || 'No eBay categories matched.') + '</div>');
                    return;
                }
                showEbayCategoryResults(rows.map(function (row) {
                    return '<button type="button" class="listing-publish-cat-item listing-publish-ebay-cat-item" data-id="' +
                        escapeHtml(row.id || '') + '" data-path="' + escapeHtml(row.path || '') + '">' +
                        escapeHtml(row.path || row.id || '') + '</button>';
                }).join(''));
            },
            error: function (xhr, status) {
                if (status === 'abort') return;
                showEbayCategoryResults('<div class="listing-publish-cat-empty">' +
                    escapeHtml(ajaxError(xhr) || 'Category search failed.') + '</div>');
            }
        });
    }

    function scheduleEbayCategorySearch(query) {
        clearTimeout(ebayCatTimer);
        ebayCatTimer = setTimeout(function () { searchEbayCategories(query); }, 280);
    }

    function pickEbayCategory(id, path) {
        const idEl = document.getElementById('listing-publish-ebay-category-id');
        const nameEl = document.getElementById('listing-publish-ebay-category-name');
        const pathEl = document.getElementById('listing-publish-ebay-category-path');
        if (idEl) idEl.value = String(id || '').replace(/\D+/g, '');
        if (nameEl) nameEl.value = String(path || '').trim();
        if (pathEl) pathEl.textContent = String(path || '').trim() || 'eBay category selected.';
        hideEbayCategoryResults();
    }

    function applySuggestedTiktokCategory(suggested) {
        const pathEl = document.getElementById('listing-publish-tiktok-category-path');
        const idEl = document.getElementById('listing-publish-tiktok-category-id');
        const nameEl = document.getElementById('listing-publish-tiktok-category-name');
        const typed = nameEl && nameEl.dataset.userTyped === '1'
            ? String(nameEl.value || '').trim()
            : '';
        if (typed.length >= 2) {
            searchTiktokCategories(typed);
            return;
        }
        const path = String((suggested && suggested.path) || '').trim();
        const id = String((suggested && suggested.id) || '').replace(/\D+/g, '');
        const name = String((suggested && suggested.name) || '').trim();
        const leaf = path.split(/[>\-\/|]/).pop().trim();
        if (idEl && id) idEl.value = id;
        if (nameEl && !String(nameEl.value || '').trim()) nameEl.value = name || leaf || '';
        if (pathEl) {
            pathEl.textContent = path || (id
                ? 'TikTok category matched from a listed sibling or title.'
                : 'No category matched yet. Type a TikTok category name, or publish and we will try from the title.');
        }
        const query = String((nameEl && nameEl.value) || name || leaf || '').trim();
        if (query.length >= 2) searchTiktokCategories(query);
        else hideTiktokCategoryResults();
    }

    function hideTiktokCategoryResults() {
        const box = document.getElementById('listing-publish-tiktok-category-results');
        if (!box) return;
        box.classList.remove('is-open');
        box.innerHTML = '';
    }

    function showTiktokCategoryResults(html) {
        const box = document.getElementById('listing-publish-tiktok-category-results');
        if (!box) return;
        box.innerHTML = html;
        box.classList.add('is-open');
        box.hidden = false;
    }

    function searchTiktokCategories(query) {
        query = String(query || '').trim();
        const box = document.getElementById('listing-publish-tiktok-category-results');
        if (!box || !isTiktokChannel()) return;
        if (query.length < 2) {
            hideTiktokCategoryResults();
            return;
        }
        showTiktokCategoryResults('<div class="listing-publish-cat-empty">Searching TikTok categories…</div>');
        if (tiktokCatXhr && tiktokCatXhr.abort) tiktokCatXhr.abort();
        tiktokCatXhr = $.ajax({
            url: cfg().categorySearchUrl || '/listing-manager/ebay/categories',
            type: 'GET',
            data: {
                q: query,
                channel: cfg().channel || 'tiktokshop2',
                title: query
            },
            dataType: 'json',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            success: function (res) {
                const rows = (res && res.categories) || [];
                if (!rows.length) {
                    showTiktokCategoryResults('<div class="listing-publish-cat-empty">' +
                        escapeHtml((res && res.message) || 'No TikTok categories matched.') + '</div>');
                    return;
                }
                showTiktokCategoryResults(rows.map(function (row) {
                    return '<button type="button" class="listing-publish-cat-item listing-publish-tiktok-cat-item" data-id="' +
                        escapeHtml(row.id || '') + '" data-path="' + escapeHtml(row.path || '') + '">' +
                        escapeHtml(row.path || row.id || '') + '</button>';
                }).join(''));
            },
            error: function (xhr, status) {
                if (status === 'abort') return;
                showTiktokCategoryResults('<div class="listing-publish-cat-empty">' +
                    escapeHtml(ajaxError(xhr) || 'Category search failed.') + '</div>');
            }
        });
    }

    function scheduleTiktokCategorySearch(query) {
        clearTimeout(tiktokCatTimer);
        tiktokCatTimer = setTimeout(function () { searchTiktokCategories(query); }, 280);
    }

    function pickTiktokCategory(id, path) {
        const idEl = document.getElementById('listing-publish-tiktok-category-id');
        const nameEl = document.getElementById('listing-publish-tiktok-category-name');
        const pathEl = document.getElementById('listing-publish-tiktok-category-path');
        if (idEl) idEl.value = String(id || '').replace(/\D+/g, '');
        if (nameEl) nameEl.value = String(path || '').trim();
        if (pathEl) pathEl.textContent = String(path || '').trim() || 'TikTok category selected.';
        hideTiktokCategoryResults();
    }

    function applySuggestedSheinCategory(suggested) {
        const pathEl = document.getElementById('listing-publish-shein-category-path');
        const idEl = document.getElementById('listing-publish-shein-category-id');
        const nameEl = document.getElementById('listing-publish-shein-category-name');
        const typed = nameEl && nameEl.dataset.userTyped === '1'
            ? String(nameEl.value || '').trim()
            : '';
        if (typed.length >= 2) {
            searchSheinCategories(typed);
            return;
        }
        const path = String((suggested && suggested.path) || '').trim();
        const id = String((suggested && suggested.id) || '').replace(/\D+/g, '');
        const name = String((suggested && suggested.name) || '').trim();
        const leaf = path.split(/[>\-\/|]/).pop().trim();
        if (idEl && id) idEl.value = id;
        if (nameEl && !String(nameEl.value || '').trim()) nameEl.value = name || leaf || '';
        if (pathEl) {
            pathEl.textContent = path || (id
                ? 'Shein category matched from a listed sibling or title.'
                : 'No category matched yet. Type a Shein category name, or publish and we will try from the title.');
        }
        const query = String((nameEl && nameEl.value) || name || leaf || '').trim();
        if (query.length >= 2) searchSheinCategories(query);
        else hideSheinCategoryResults();
    }

    function hideSheinCategoryResults() {
        const box = document.getElementById('listing-publish-shein-category-results');
        if (!box) return;
        box.classList.remove('is-open');
        box.innerHTML = '';
    }

    function showSheinCategoryResults(html) {
        const box = document.getElementById('listing-publish-shein-category-results');
        if (!box) return;
        box.innerHTML = html;
        box.classList.add('is-open');
        box.hidden = false;
    }

    function searchSheinCategories(query) {
        query = String(query || '').trim();
        const box = document.getElementById('listing-publish-shein-category-results');
        if (!box || !isSheinChannel()) return;
        if (query.length < 2) {
            hideSheinCategoryResults();
            return;
        }
        showSheinCategoryResults('<div class="listing-publish-cat-empty">Searching Shein categories…</div>');
        if (sheinCatXhr && sheinCatXhr.abort) sheinCatXhr.abort();
        sheinCatXhr = $.ajax({
            url: cfg().categorySearchUrl || '/listing-manager/ebay/categories',
            type: 'GET',
            data: {
                q: query,
                channel: cfg().channel || 'shein',
                title: query
            },
            dataType: 'json',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            success: function (res) {
                const rows = (res && res.categories) || [];
                if (!rows.length) {
                    showSheinCategoryResults('<div class="listing-publish-cat-empty">' +
                        escapeHtml((res && res.message) || 'No Shein categories matched.') + '</div>');
                    return;
                }
                showSheinCategoryResults(rows.map(function (row) {
                    return '<button type="button" class="listing-publish-cat-item listing-publish-shein-cat-item" data-id="' +
                        escapeHtml(row.id || '') + '" data-path="' + escapeHtml(row.path || '') + '">' +
                        escapeHtml(row.path || row.id || '') + '</button>';
                }).join(''));
            },
            error: function (xhr, status) {
                if (status === 'abort') return;
                showSheinCategoryResults('<div class="listing-publish-cat-empty">' +
                    escapeHtml(ajaxError(xhr) || 'Category search failed.') + '</div>');
            }
        });
    }

    function scheduleSheinCategorySearch(query) {
        clearTimeout(sheinCatTimer);
        sheinCatTimer = setTimeout(function () { searchSheinCategories(query); }, 280);
    }

    function pickSheinCategory(id, path) {
        const idEl = document.getElementById('listing-publish-shein-category-id');
        const nameEl = document.getElementById('listing-publish-shein-category-name');
        const pathEl = document.getElementById('listing-publish-shein-category-path');
        if (idEl) idEl.value = String(id || '').replace(/\D+/g, '');
        if (nameEl) nameEl.value = String(path || '').trim();
        if (pathEl) pathEl.textContent = String(path || '').trim() || 'Shein category selected.';
        hideSheinCategoryResults();
    }

    function applySuggestedAliexpressCategory(suggested) {
        const pathEl = document.getElementById('listing-publish-aliexpress-category-path');
        const idEl = document.getElementById('listing-publish-category-id');
        const path = String((suggested && suggested.path) || '').trim();
        const id = String((suggested && suggested.id) || '').replace(/\D+/g, '');
        if (idEl) idEl.value = id;
        if (!pathEl) return;
        pathEl.textContent = path || (id
            ? 'AliExpress category matched from the product type.'
            : 'No category matched yet. Type a category name below, or publish and we will try from the title.');
    }

    function selectedWeightLb() {
        if (isTiktokChannel()) {
            const tt = document.getElementById('listing-publish-tiktok-weight-lb');
            const raw = tt ? String(tt.value || '').replace(',', '.').trim() : '';
            const n = parseFloat(raw);
            return n > 0 ? String(n) : '';
        }
        if (isSheinChannel()) {
            const shein = document.getElementById('listing-publish-shein-weight-lb');
            const raw = shein ? String(shein.value || '').replace(',', '.').trim() : '';
            const n = parseFloat(raw);
            return n > 0 ? String(n) : '';
        }
        const el = document.getElementById('listing-publish-weight-lb');
        const raw = el ? String(el.value || '').replace(',', '.').trim() : '';
        const n = parseFloat(raw);
        return n > 0 ? String(n) : '';
    }

    function resetAliexpressWeightInput() {
        const input = document.getElementById('listing-publish-weight-lb');
        const row = document.getElementById('listing-publish-aliexpress-weight');
        const note = document.getElementById('listing-publish-weight-note');
        if (input) {
            input.value = '';
            delete input.dataset.userTyped;
        }
        if (row) row.classList.remove('is-missing');
        if (note) note.textContent = 'Looking up Dim/Wt Master…';
    }

    function applySuggestedAliexpressPackage(pkg) {
        const row = document.getElementById('listing-publish-aliexpress-weight');
        const input = document.getElementById('listing-publish-weight-lb');
        const note = document.getElementById('listing-publish-weight-note');
        if (row) row.hidden = !isAliexpressChannel();
        if (!isAliexpressChannel()) return;
        const typed = input && input.dataset.userTyped === '1'
            ? String(input.value || '').trim()
            : '';
        const lb = String((pkg && (pkg.weight_lb || pkg.weightLb)) || '').trim();
        const has = !!(pkg && pkg.has_weight && lb);
        if (input && typed === '') {
            input.value = has ? lb : '';
        }
        const current = selectedWeightLb();
        if (row) row.classList.toggle('is-missing', !current);
        if (note) {
            if (current && (typed !== '' || has)) {
                note.textContent = has && typed === ''
                    ? 'From Dim/Wt Master. You can change it before publishing.'
                    : 'Using the weight you typed. AliExpress US Package weight is in pounds.';
            } else {
                note.textContent = 'Not on Dim/Wt Master. Type the shipping weight in pounds.';
            }
        }
    }

    function applyPublishModeUi() {
        const box = document.getElementById('listing-publish-mode-box');
        if (box) box.style.display = '';
        const catBox = document.getElementById('listing-publish-aliexpress-category');
        if (catBox) catBox.hidden = !isAliexpressChannel();
        const wayfairBox = document.getElementById('listing-publish-wayfair-category');
        if (wayfairBox) wayfairBox.hidden = !isWayfairChannel();
        const reverbBox = document.getElementById('listing-publish-reverb-category');
        if (reverbBox) reverbBox.hidden = !isReverbChannel();
        if (isReverbChannel()) applySuggestedCategory(null);
        if (isAliexpressChannel()) {
            applySuggestedAliexpressCategory(null);
            applySuggestedAliexpressPackage(null);
        }
        if (isWayfairChannel()) applySuggestedWayfairCategory(null);
        const ebayBox = document.getElementById('listing-publish-ebay-category');
        if (ebayBox) ebayBox.hidden = !isEbayChannel();
        if (isEbayChannel()) applySuggestedEbayCategory(null);
        const tiktokBox = document.getElementById('listing-publish-tiktok-category');
        if (tiktokBox) tiktokBox.hidden = !isTiktokChannel();
        if (isTiktokChannel()) applySuggestedTiktokCategory(null);
        const sheinBox = document.getElementById('listing-publish-shein-category');
        if (sheinBox) sheinBox.hidden = !isSheinChannel();
        if (isSheinChannel()) applySuggestedSheinCategory(null);
        updateModalCopy();
    }

    function updateModalCopy() {
        const title = document.getElementById('listingPublishModalLabel');
        const note = document.getElementById('listing-publish-modal-note')
            || document.querySelector('#listingPublishModal .listing-publish-modal-note');
        const btn = document.getElementById('listing-publish-confirm');
        const single = selectedPublishMode() === 'single';
        if (title) title.textContent = single ? 'Publish single listings' : 'Publish variation listing';
        if (note) {
            note.textContent = single
                ? 'Each checked SKU becomes its own marketplace listing. Suggested siblings are hidden in this mode.'
                : 'One marketplace listing per group. Only the SKUs you check are included — suggested siblings stay off until you check them.';
        }
        if (btn && !btn.disabled) {
            btn.innerHTML = single
                ? '<i class="fas fa-cloud-upload-alt"></i> Publish listing(s)'
                : '<i class="fas fa-cloud-upload-alt"></i> Publish variation(s)';
        }
    }

    function childShouldCheck(child) {
        if (child.status !== 'will_publish') return false;
        if (typeof child.selected === 'boolean') return child.selected;
        const sku = String(child.sku || '').trim();
        return previewSeedSkus.indexOf(sku) !== -1;
    }

    function skuParentsMap(skus) {
        const table = findTable();
        const map = {};
        const want = {};
        (skus || []).forEach(function (sku) { want[String(sku).trim()] = true; });
        if (!table) return map;
        (table.getData() || []).forEach(function (row) {
            const sku = String(row.sku || '').trim();
            if (!sku || !want[sku]) return;
            map[sku] = String(row.parent || '').trim();
        });
        return map;
    }

    function showModal() {
        const el = document.getElementById('listingPublishModal');
        if (!el) return;
        if (window.bootstrap && bootstrap.Modal) bootstrap.Modal.getOrCreateInstance(el).show();
        else if (window.$) $(el).modal('show');
    }

    function hideModal() {
        const el = document.getElementById('listingPublishModal');
        if (!el) return;
        if (window.bootstrap && bootstrap.Modal) bootstrap.Modal.getOrCreateInstance(el).hide();
        else if (window.$) $(el).modal('hide');
    }

    function statusLabel(status, reason) {
        if (status === 'will_publish') {
            return '<span class="listing-publish-status is-publish">Will publish</span>';
        }
        return '<span class="listing-publish-status is-skip">' + escapeHtml(reason || 'Skipped') + '</span>';
    }

    function renderGroups(groups) {
        lastPreviewGroups = groups || [];
        const box = document.getElementById('listing-publish-groups');
        const confirm = document.getElementById('listing-publish-confirm');
        if (!box) return;
        if (!groups || !groups.length) {
            box.innerHTML = '<p class="text-muted mb-0">No Missing L children to publish.</p>';
            if (confirm) confirm.disabled = true;
            return;
        }
        const single = selectedPublishMode() === 'single';
        let html = '';
        let canPublish = false;
        groups.forEach(function (group, gi) {
            const parent = String(group.parent || 'Standalone');
            const children = group.children || [];
            const selectedCount = children.filter(childShouldCheck).length;
            html += '<div class="listing-publish-group" data-group-index="' + gi + '" data-parent="' + escapeHtml(parent) + '">';
            html += '<div class="listing-publish-group-head">' + escapeHtml(parent) + ' · ' + selectedCount +
                (single
                    ? ' selected for single listing' + (selectedCount === 1 ? '' : 's')
                    : ' selected for this variation') + '</div>';
            html += '<table class="table table-sm mb-0"><thead><tr><th style="width:36px;"></th><th>SKU</th><th>INV</th><th>In this listing</th><th>Status</th></tr></thead><tbody>';
            children.forEach(function (child) {
                const sku = String(child.sku || '');
                const publishable = child.status === 'will_publish';
                const checked = childShouldCheck(child);
                if (checked) canPublish = true;
                const role = checked
                    ? '<span class="listing-publish-role is-picked">Your pick</span>'
                    : '<span class="listing-publish-role is-suggested">Suggested sibling</span>';
                html += '<tr><td><input type="checkbox" class="listing-publish-sku-check" data-sku="' +
                    escapeHtml(sku) + '"' + (checked ? ' checked' : '') + (publishable ? '' : ' disabled') + '></td>';
                html += '<td>' + escapeHtml(sku) + '</td><td>' + escapeHtml(String(child.inv ?? 0)) + '</td>';
                html += '<td>' + role + '</td>';
                html += '<td>' + statusLabel(child.status, child.reason) + '</td></tr>';
            });
            html += '</tbody></table></div>';
        });
        box.innerHTML = html;
        if (confirm) confirm.disabled = !canPublish;
        const progress = document.getElementById('listing-publish-progress');
        if (progress) progress.textContent = '';
        updateModalCopy();
    }

    function selectedGroups() {
        const groups = [];
        document.querySelectorAll('#listing-publish-groups .listing-publish-group').forEach(function (el) {
            const parent = String(el.getAttribute('data-parent') || (el.querySelector('.listing-publish-group-head')?.textContent || '').split(' · ')[0] || '').trim();
            const skus = [];
            el.querySelectorAll('.listing-publish-sku-check:checked:not(:disabled)').forEach(function (cb) {
                const sku = String(cb.getAttribute('data-sku') || '').trim();
                if (sku) skus.push(sku);
            });
            if (skus.length) groups.push({ parent: parent, skus: skus });
        });
        return groups;
    }

    function groupsForPublish() {
        const groups = selectedGroups();
        if (selectedPublishMode() !== 'single') return groups;
        const out = [];
        groups.forEach(function (group) {
            group.skus.forEach(function (sku) {
                out.push({ parent: sku, skus: [sku] });
            });
        });
        return out;
    }

    function ajaxError(xhr) {
        if (!xhr) return 'Request failed.';
        if (xhr.statusText === 'timeout') {
            return 'AliExpress took too long to respond. Refresh Missing L before trying again — the listing may already be created.';
        }
        if (xhr.status === 0) return 'Timed out or the connection dropped. Try again.';
        let json = xhr.responseJSON;
        if (!json && xhr.responseText) {
            try { json = JSON.parse(xhr.responseText); } catch (e) { json = null; }
        }
        if (json && (json.message || json.error)) return String(json.message || json.error);
        if (xhr.status === 419) return 'Session expired. Refresh the page.';
        if (xhr.status === 405) return 'Publish route is blocked. Hard-refresh the page and try again.';
        if (xhr.status === 502 || xhr.status === 504) {
            return 'AliExpress took too long to respond. Refresh Missing L before trying again — the listing may already be created.';
        }
        if (xhr.status === 500) return 'Server error during publish. Try again.';
        if (xhr.status) return 'Request failed (HTTP ' + xhr.status + ').';
        return 'Request failed.';
    }

    function actionUrl() {
        const c = cfg();
        if (c.saveStatusUrl) {
            return c.saveStatusUrl;
        }
        const channel = String(c.channel || '').replace(/[^a-z0-9]/gi, '');
        if (channel) {
            return '/listing_' + channel + '/save-status';
        }
        return c.previewUrl || '/listing-publish-preview';
    }

    function openPreview(skus) {
        const unique = [];
        const seen = {};
        (skus || []).forEach(function (sku) {
            sku = String(sku || '').trim();
            if (!sku || seen[sku]) return;
            seen[sku] = true;
            unique.push(sku);
        });
        if (!unique.length) {
            notify('danger', 'Select at least one SKU.');
            return;
        }
        previewSeedSkus = unique.slice();
        resetAliexpressWeightInput();
        applyPublishModeUi();
        const box = document.getElementById('listing-publish-groups');
        if (box) box.innerHTML = '<p class="text-muted mb-0">Loading listing preview…</p>';
        const confirm = document.getElementById('listing-publish-confirm');
        if (confirm) confirm.disabled = true;
        showModal();
        requestPreview(unique);
    }

    function requestPreview(skus) {
        const c = cfg();
        $.ajax({
            url: actionUrl(),
            type: 'POST',
            data: {
                skus: skus,
                sku_parents: skuParentsMap(skus),
                preview: 1,
                channel: c.channel || '',
                mode: selectedPublishMode()
            },
            headers: { 'X-CSRF-TOKEN': csrf() },
            success: function (response) {
                renderGroups((response && response.groups) || []);
                if (isReverbChannel()) applySuggestedCategory(response && response.suggested_category);
                if (isAliexpressChannel()) {
                    applySuggestedAliexpressCategory(response && response.suggested_category);
                    applySuggestedAliexpressPackage(response && response.suggested_package);
                }
                if (isWayfairChannel()) applySuggestedWayfairCategory(response && response.suggested_category);
                if (isEbayChannel()) applySuggestedEbayCategory(response && response.suggested_category);
                if (isTiktokChannel()) applySuggestedTiktokCategory(response && response.suggested_category);
                if (isSheinChannel()) applySuggestedSheinCategory(response && response.suggested_category);
            },
            error: function (xhr) {
                hideModal();
                notify('danger', ajaxError(xhr) || 'Could not build listing preview.');
            }
        });
    }

    function markListed(table, skus, goodsId) {
        if (!table || !skus || !skus.length) return;
        const want = {};
        skus.forEach(function (sku) { want[String(sku).trim()] = true; });
        (table.getRows() || []).forEach(function (row) {
            const data = row.getData() || {};
            if (!want[String(data.sku || '').trim()]) return;
            row.update({
                goods_id: goodsId,
                listing_id: goodsId,
                eBay_item_id: goodsId,
                listed: 'Listed'
            });
        });
        if (typeof calculateTotals === 'function') calculateTotals();
    }

    function publishGroup(skus, parent) {
        const c = cfg();
        return $.ajax({
            url: actionUrl(),
            type: 'POST',
            data: {
                skus: skus,
                confirmed: 1,
                publish: 1,
                channel: c.channel || '',
                mode: selectedPublishMode(),
                parent: parent || '',
                category_id: (isWayfairChannel() || isEbayChannel() || isTiktokChannel() || isSheinChannel()) ? selectedCategoryId() : (selectedCategoryName() ? '' : selectedCategoryId()),
                category_name: selectedCategoryName(),
                category_uuid: selectedCategoryUuid(),
                weight_lb: (isAliexpressChannel() || isTiktokChannel() || isSheinChannel()) ? selectedWeightLb() : ''
            },
            headers: { 'X-CSRF-TOKEN': csrf() },
            timeout: 300000
        });
    }

    function enhanceTable(table) {
        try {
            table.updateColumnDefinition('sku', {
                minWidth: 180,
                formatter: skuFormatter,
                cellClick: function (e) {
                    if (e.target.closest && e.target.closest('.copy-sku-btn')) e.stopPropagation();
                }
            });
        } catch (err) {}

        const hasPublish = (table.getColumns() || []).some(function (col) {
            const def = col.getDefinition ? col.getDefinition() : {};
            return def.field === 'publish_to_marketplace' || def.field === 'publish_to_temu2';
        });
        if (!hasPublish && !(document.getElementById('temu2PublishModal') && cfg().channel === 'temu2')) {
            try {
                table.addColumn({
                    title: 'Publish to ' + (cfg().channelLabel || 'marketplace'),
                    field: 'publish_to_marketplace',
                    hozAlign: 'center',
                    headerHozAlign: 'center',
                    headerSort: false,
                    width: 150,
                    formatter: publishFormatter
                });
            } catch (err) {}
        }
    }

    function bindUi(table) {
        const wrap = cfg().wrap || document;
        $(document).off('click.listingPageTools', (typeof wrap === 'string' ? wrap + ' ' : '') + '.copy-sku-btn');
        $(document).on('click.listingPageTools', '.copy-sku-btn', function (e) {
            e.preventDefault();
            e.stopPropagation();
            const sku = $(this).attr('data-sku') || '';
            if (sku) copySku(sku, this);
        });

        $('#export-btn').off('click').on('click.listingPageTools', function (e) {
            e.preventDefault();
            e.stopPropagation();
            exportFiltered(table);
        });

        $(document).off('click.listingPageTools', '.listing-mp-publish-btn, .temu2-publish-btn')
            .on('click.listingPageTools', '.listing-mp-publish-btn, .temu2-publish-btn', function (e) {
                if (document.getElementById('temu2PublishModal') && $(this).hasClass('temu2-publish-btn')) {
                    return;
                }
                e.preventDefault();
                e.stopPropagation();
                const sku = String($(this).data('sku') || $(this).attr('data-sku') || '').trim();
                if (sku) openPreview([sku]);
            });

        $('#bulk-publish-btn').off('click.listingPageTools').on('click.listingPageTools', function () {
            if (document.getElementById('temu2PublishModal') && cfg().channel === 'temu2') {
                return;
            }
            const selected = (table.getSelectedData() || [])
                .map(function (row) { return String(row.sku || '').trim(); })
                .filter(function (sku) { return sku && String(sku).toUpperCase().indexOf('PARENT') === -1; });
            if (!selected.length) {
                notify('danger', 'Select one or more SKUs first.');
                return;
            }
            openPreview(selected);
        });

        $(document).off('change.listingPageTools', 'input[name="listing-publish-mode"]')
            .on('change.listingPageTools', 'input[name="listing-publish-mode"]', function () {
                updateModalCopy();
                if (previewSeedSkus.length) {
                    const box = document.getElementById('listing-publish-groups');
                    if (box) box.innerHTML = '<p class="text-muted mb-0">Updating preview…</p>';
                    requestPreview(previewSeedSkus);
                } else if (lastPreviewGroups.length) {
                    renderGroups(lastPreviewGroups);
                }
            });

        $(document).off('input.listingPageTools', '#listing-publish-weight-lb')
            .on('input.listingPageTools', '#listing-publish-weight-lb', function () {
                this.dataset.userTyped = '1';
                applySuggestedAliexpressPackage({
                    has_weight: !!selectedWeightLb(),
                    weight_lb: selectedWeightLb()
                });
            });

        $(document).off('change.listingPageTools', '.listing-publish-sku-check')
            .on('change.listingPageTools', '.listing-publish-sku-check', function () {
                const $group = $(this).closest('.listing-publish-group');
                const parent = String($group.attr('data-parent') || '').trim();
                const selectedCount = $group.find('.listing-publish-sku-check:checked:not(:disabled)').length;
                const single = selectedPublishMode() === 'single';
                const $role = $(this).closest('tr').find('.listing-publish-role');
                if ($role.length) {
                    if (this.checked && !this.disabled) {
                        $role.removeClass('is-suggested').addClass('is-picked').text('Your pick');
                    } else {
                        $role.removeClass('is-picked').addClass('is-suggested').text('Suggested sibling');
                    }
                }
                $group.find('.listing-publish-group-head').text(
                    parent + ' · ' + selectedCount +
                    (single ? ' selected for single listing' + (selectedCount === 1 ? '' : 's') : ' selected for this variation')
                );
                const anyChecked = $('#listing-publish-groups .listing-publish-sku-check:checked:not(:disabled)').length > 0;
                $('#listing-publish-confirm').prop('disabled', !anyChecked);
            });

        $('#listing-publish-confirm').off('click.listingPageTools').on('click.listingPageTools', function () {
            const $btn = $(this);
            if ($btn.prop('disabled')) return;
            const groups = groupsForPublish();
            if (!groups.length) {
                notify('danger', 'No Missing L children selected to publish.');
                return;
            }
            if (isAliexpressChannel() && !selectedWeightLb()) {
                notify('danger', 'Enter package weight in pounds. Dim/Wt Master has no weight for this SKU.');
                const weightEl = document.getElementById('listing-publish-weight-lb');
                if (weightEl) weightEl.focus();
                return;
            }
            const originalHtml = $btn.html();
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Publishing');
            $('#listingPublishModal .btn-close, #listingPublishModal [data-bs-dismiss="modal"]').prop('disabled', true);
            $('#listing-publish-mode-box input').prop('disabled', true);
            showPublishStatus('loading', 'Publishing ' + groups[0].parent + ' (1/' + groups.length + ')…');
            let index = 0;
            const ok = [];
            const fail = [];
            function next() {
                if (index >= groups.length) {
                    $btn.prop('disabled', false).html(originalHtml);
                    $('#listingPublishModal .btn-close, #listingPublishModal [data-bs-dismiss="modal"]').prop('disabled', false);
                    $('#listing-publish-mode-box input').prop('disabled', false);
                    const progress = document.getElementById('listing-publish-progress');
                    if (progress) progress.textContent = '';
                    if (fail.length) {
                        showPublishStatus('error', (ok.length ? ok.join('\n') + '\n\n' : '') + fail.join('\n'));
                    } else {
                        showPublishStatus('success', ok.join('\n') || 'Published.');
                        hideModal();
                    }
                    return;
                }
                const group = groups[index];
                index += 1;
                const progress = document.getElementById('listing-publish-progress');
                const label = 'Publishing ' + group.parent + ' (' + index + '/' + groups.length + ')…';
                if (progress) progress.textContent = label;
                showPublishStatus('loading', label);
                publishGroup(group.skus, group.parent).done(function (response) {
                    const goodsId = String((response && response.goods_id) || '').trim();
                    const listedSkus = (response && response.skus) || group.skus;
                    if (goodsId) markListed(table, listedSkus, goodsId);
                    ok.push((response && response.message) ? response.message : ('Published ' + group.parent + '.'));
                    next();
                }).fail(function (xhr) {
                    fail.push(group.parent + ': ' + ajaxError(xhr));
                    next();
                });
            }
            next();
        });
    }

    function bindReverbCategorySearch() {
        function onReverbCategoryTyped(el) {
            if (!el) return;
            el.dataset.userTyped = '1';
            const uuidEl = document.getElementById('listing-publish-category-uuid');
            if (uuidEl) uuidEl.value = '';
            const pathEl = document.getElementById('listing-publish-reverb-category-path');
            const q = String(el.value || '').trim();
            if (pathEl) {
                pathEl.textContent = q
                    ? 'Searching Reverb for “' + q + '”…'
                    : 'Type a Reverb category name, then pick one from the list.';
            }
            scheduleReverbCategorySearch(q);
        }

        $(document).off('input.listingPageTools', '#listing-publish-reverb-category-name')
            .on('input.listingPageTools', '#listing-publish-reverb-category-name', function () {
                onReverbCategoryTyped(this);
            });

        $(document).off('keyup.listingPageTools', '#listing-publish-reverb-category-name')
            .on('keyup.listingPageTools', '#listing-publish-reverb-category-name', function () {
                onReverbCategoryTyped(this);
            });

        $(document).off('focus.listingPageTools', '#listing-publish-reverb-category-name')
            .on('focus.listingPageTools', '#listing-publish-reverb-category-name', function () {
                const q = String(this.value || '').trim();
                if (q.length >= 2) searchReverbCategories(q);
            });

        $('#listingPublishModal').off('shown.bs.modal.listingPageTools')
            .on('shown.bs.modal.listingPageTools', function () {
                const nameEl = document.getElementById('listing-publish-reverb-category-name');
                if (nameEl) nameEl.dataset.userTyped = '';
            });

        $(document).off('click.listingPageTools', '.listing-publish-cat-item')
            .on('click.listingPageTools', '.listing-publish-cat-item', function (e) {
                if (this.classList.contains('listing-publish-ebay-cat-item')
                    || this.classList.contains('listing-publish-tiktok-cat-item')
                    || this.classList.contains('listing-publish-shein-cat-item')
                    || this.classList.contains('listing-publish-wayfair-cat-item')) {
                    return;
                }
                e.preventDefault();
                e.stopPropagation();
                pickReverbCategory($(this).attr('data-id'), $(this).attr('data-path'));
            });

        $(document).off('mousedown.listingPageTools.reverbCat')
            .on('mousedown.listingPageTools.reverbCat', function (e) {
                if (!e.target.closest || e.target.closest('.listing-publish-cat-wrap')) return;
                hideReverbCategoryResults();
                hideEbayCategoryResults();
                hideTiktokCategoryResults();
                hideSheinCategoryResults();
                hideWayfairClassResults();
            });
    }

    function bindTiktokCategorySearch() {
        function onTiktokCategoryTyped(el) {
            if (!el) return;
            el.dataset.userTyped = '1';
            const idEl = document.getElementById('listing-publish-tiktok-category-id');
            if (idEl) idEl.value = '';
            const pathEl = document.getElementById('listing-publish-tiktok-category-path');
            const q = String(el.value || '').trim();
            if (pathEl) {
                pathEl.textContent = q
                    ? 'Searching TikTok for “' + q + '”…'
                    : 'Type a TikTok category name, then pick one from the list.';
            }
            scheduleTiktokCategorySearch(q);
        }

        $(document).off('input.listingPageToolsTiktok', '#listing-publish-tiktok-category-name')
            .on('input.listingPageToolsTiktok', '#listing-publish-tiktok-category-name', function () {
                onTiktokCategoryTyped(this);
            });

        $(document).off('focus.listingPageToolsTiktok', '#listing-publish-tiktok-category-name')
            .on('focus.listingPageToolsTiktok', '#listing-publish-tiktok-category-name', function () {
                const q = String(this.value || '').trim();
                if (q.length >= 2) searchTiktokCategories(q);
            });

        $('#listingPublishModal').off('shown.bs.modal.listingPageToolsTiktok')
            .on('shown.bs.modal.listingPageToolsTiktok', function () {
                const nameEl = document.getElementById('listing-publish-tiktok-category-name');
                if (nameEl) nameEl.dataset.userTyped = '';
            });

        $(document).off('click.listingPageToolsTiktok', '.listing-publish-tiktok-cat-item')
            .on('click.listingPageToolsTiktok', '.listing-publish-tiktok-cat-item', function (e) {
                e.preventDefault();
                e.stopPropagation();
                pickTiktokCategory($(this).attr('data-id'), $(this).attr('data-path'));
            });
    }

    function bindSheinCategorySearch() {
        function onSheinCategoryTyped(el) {
            if (!el) return;
            el.dataset.userTyped = '1';
            const idEl = document.getElementById('listing-publish-shein-category-id');
            if (idEl) idEl.value = '';
            const pathEl = document.getElementById('listing-publish-shein-category-path');
            const q = String(el.value || '').trim();
            if (pathEl) {
                pathEl.textContent = q
                    ? 'Searching Shein for “' + q + '”…'
                    : 'Type a Shein category name, then pick one from the list.';
            }
            scheduleSheinCategorySearch(q);
        }

        $(document).off('input.listingPageToolsShein', '#listing-publish-shein-category-name')
            .on('input.listingPageToolsShein', '#listing-publish-shein-category-name', function () {
                onSheinCategoryTyped(this);
            });

        $(document).off('focus.listingPageToolsShein', '#listing-publish-shein-category-name')
            .on('focus.listingPageToolsShein', '#listing-publish-shein-category-name', function () {
                const q = String(this.value || '').trim();
                if (q.length >= 2) searchSheinCategories(q);
            });

        $('#listingPublishModal').off('shown.bs.modal.listingPageToolsShein')
            .on('shown.bs.modal.listingPageToolsShein', function () {
                const nameEl = document.getElementById('listing-publish-shein-category-name');
                if (nameEl) nameEl.dataset.userTyped = '';
            });

        $(document).off('click.listingPageToolsShein', '.listing-publish-shein-cat-item')
            .on('click.listingPageToolsShein', '.listing-publish-shein-cat-item', function (e) {
                e.preventDefault();
                e.stopPropagation();
                pickSheinCategory($(this).attr('data-id'), $(this).attr('data-path'));
            });
    }

    function bindEbayCategorySearch() {
        function onEbayCategoryTyped(el) {
            if (!el) return;
            el.dataset.userTyped = '1';
            const idEl = document.getElementById('listing-publish-ebay-category-id');
            if (idEl) idEl.value = '';
            const pathEl = document.getElementById('listing-publish-ebay-category-path');
            const q = String(el.value || '').trim();
            if (pathEl) {
                pathEl.textContent = q
                    ? 'Searching eBay for “' + q + '”…'
                    : 'Type an eBay category name, then pick one from the list.';
            }
            scheduleEbayCategorySearch(q);
        }

        $(document).off('input.listingPageToolsEbay', '#listing-publish-ebay-category-name')
            .on('input.listingPageToolsEbay', '#listing-publish-ebay-category-name', function () {
                onEbayCategoryTyped(this);
            });

        $(document).off('keyup.listingPageToolsEbay', '#listing-publish-ebay-category-name')
            .on('keyup.listingPageToolsEbay', '#listing-publish-ebay-category-name', function () {
                onEbayCategoryTyped(this);
            });

        $(document).off('focus.listingPageToolsEbay', '#listing-publish-ebay-category-name')
            .on('focus.listingPageToolsEbay', '#listing-publish-ebay-category-name', function () {
                const q = String(this.value || '').trim();
                if (q.length >= 2) searchEbayCategories(q);
            });

        $('#listingPublishModal').off('shown.bs.modal.listingPageToolsEbay')
            .on('shown.bs.modal.listingPageToolsEbay', function () {
                const nameEl = document.getElementById('listing-publish-ebay-category-name');
                if (nameEl) nameEl.dataset.userTyped = '';
            });

        $(document).off('click.listingPageToolsEbay', '.listing-publish-ebay-cat-item')
            .on('click.listingPageToolsEbay', '.listing-publish-ebay-cat-item', function (e) {
                e.preventDefault();
                e.stopPropagation();
                pickEbayCategory($(this).attr('data-id'), $(this).attr('data-path'));
            });
    }

    function bindWayfairClassSearch() {
        function onWayfairClassTyped(el) {
            if (!el) return;
            el.dataset.userTyped = '1';
            const idEl = document.getElementById('listing-publish-wayfair-class-id');
            if (idEl) idEl.value = '';
            const pathEl = document.getElementById('listing-publish-wayfair-category-path');
            const q = String(el.value || '').trim();
            if (pathEl) {
                pathEl.textContent = q
                    ? 'Searching Wayfair for “' + q + '”…'
                    : 'Type a Wayfair class name, then pick one from the list.';
            }
            scheduleWayfairClassSearch(q);
        }

        $(document).off('input.listingPageToolsWayfair', '#listing-publish-wayfair-class-name')
            .on('input.listingPageToolsWayfair', '#listing-publish-wayfair-class-name', function () {
                onWayfairClassTyped(this);
            });

        $(document).off('focus.listingPageToolsWayfair', '#listing-publish-wayfair-class-name')
            .on('focus.listingPageToolsWayfair', '#listing-publish-wayfair-class-name', function () {
                const q = String(this.value || '').trim();
                if (q.length >= 2) searchWayfairClasses(q);
            });

        $('#listingPublishModal').off('shown.bs.modal.listingPageToolsWayfair')
            .on('shown.bs.modal.listingPageToolsWayfair', function () {
                const nameEl = document.getElementById('listing-publish-wayfair-class-name');
                if (nameEl) nameEl.dataset.userTyped = '';
            });

        $(document).off('click.listingPageToolsWayfair', '.listing-publish-wayfair-cat-item')
            .on('click.listingPageToolsWayfair', '.listing-publish-wayfair-cat-item', function (e) {
                e.preventDefault();
                e.stopPropagation();
                pickWayfairClass($(this).attr('data-id'), $(this).attr('data-path'));
            });
    }

    $(function () {
        bindReverbCategorySearch();
        bindEbayCategorySearch();
        bindTiktokCategorySearch();
        bindSheinCategorySearch();
        bindWayfairClassSearch();
        $(document).off('click.listingPageTools', '#listing-publish-status-close')
            .on('click.listingPageTools', '#listing-publish-status-close', function () {
                hidePublishStatus();
            });
        if (!cfg().tableId) return;
        waitForTable(function (table) {
            enhanceTable(table);
            bindUi(table);
        });
    });
})();
