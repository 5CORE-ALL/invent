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
        if (msgEl) msgEl.textContent = withChannelName(String(message || ''));
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
    let neweggCatTimer = null;
    let neweggCatXhr = null;
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

    function isNeweggChannel() {
        const c = String(cfg().channel || '').toLowerCase().replace(/[\s_-]/g, '');
        return c === 'newegg' || c === 'neweggb2c' || c === 'neweggb2b';
    }

    function isAlibabaChannel() {
        return String(cfg().channel || '').toLowerCase() === 'alibaba';
    }

    function selectedCategoryId() {
        if (isAlibabaChannel()) {
            const ab = document.getElementById('listing-publish-alibaba-category-id');
            if (ab) return String(ab.value || '').replace(/\D+/g, '');
        }
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
        if (isNeweggChannel()) {
            const newegg = document.getElementById('listing-publish-newegg-category-id');
            if (newegg) return String(newegg.value || '').replace(/\D+/g, '');
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
        if (isNeweggChannel()) {
            const newegg = document.getElementById('listing-publish-newegg-category-name');
            if (newegg) return String(newegg.value || '').trim();
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
        const hint = String((suggested && suggested.query) || '').trim();
        const leaf = path.split(/[>\-\/|]/).pop().trim();
        if (idEl && id) idEl.value = id;
        if (nameEl && !String(nameEl.value || '').trim()) nameEl.value = name || leaf || hint || '';
        if (pathEl) {
            pathEl.textContent = path || (id
                ? 'Wayfair class matched from a listed sibling or title.'
                : 'No class matched yet. Type a Wayfair class name, then pick one from the list.');
        }
        const query = String((nameEl && nameEl.value) || name || leaf || hint || '').trim();
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
                const idEl = document.getElementById('listing-publish-wayfair-class-id');
                const nameEl = document.getElementById('listing-publish-wayfair-class-name');
                const pathEl = document.getElementById('listing-publish-wayfair-category-path');
                const first = rows[0] || {};
                const firstId = String(first.id || '').replace(/\D+/g, '');
                if (firstId && idEl && !String(idEl.value || '').replace(/\D+/g, '')) {
                    idEl.value = firstId;
                    if (nameEl) nameEl.value = String(first.path || first.name || '').trim();
                    if (pathEl) pathEl.textContent = String(first.path || first.name || '') +
                        ' — selected. Click another class to change.';
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

    function hideNeweggCategoryResults() {
        const box = document.getElementById('listing-publish-newegg-category-results');
        if (!box) return;
        box.classList.remove('is-open');
        box.innerHTML = '';
    }

    function showNeweggCategoryResults(html) {
        const box = document.getElementById('listing-publish-newegg-category-results');
        if (!box) return;
        box.innerHTML = html;
        box.classList.add('is-open');
        box.hidden = false;
    }

    let neweggCategoryCatalog = null;

    function neweggCategoryLabel(row) {
        return String((row && (row.path || row.name || row.id)) || '');
    }

    function renderNeweggCategoryRows(rows) {
        const pathEl = document.getElementById('listing-publish-newegg-category-path');
        const picked = selectedCategoryId();
        if (!rows.length) {
            showNeweggCategoryResults('<div class="listing-publish-cat-empty">No Newegg subcategories matched.</div>');
            if (pathEl && !picked) pathEl.textContent = 'No Newegg subcategories matched.';
            return;
        }
        if (pathEl && !picked) {
            pathEl.textContent = rows.length + (neweggCategoryCatalog && rows.length === neweggCategoryCatalog.length
                ? ' Newegg subcategories from the API. Click one.'
                : ' matching Newegg subcategories. Click one.');
        }
        showNeweggCategoryResults(rows.map(function (row) {
            const label = neweggCategoryLabel(row);
            return '<button type="button" class="listing-publish-cat-item listing-publish-newegg-cat-item" data-id="' +
                escapeHtml(row.id || '') + '" data-path="' + escapeHtml(label) + '">' +
                escapeHtml(label) + '</button>';
        }).join(''));
    }

    function filterNeweggCatalog(query) {
        query = String(query || '').trim().toLowerCase();
        const all = neweggCategoryCatalog || [];
        if (!query) return all.slice();
        return all.filter(function (row) {
            const hay = (String(row.path || '') + ' ' + String(row.name || '') + ' ' + String(row.id || '')).toLowerCase();
            return hay.indexOf(query) !== -1;
        });
    }

    function showLoadedNeweggCategories(query) {
        renderNeweggCategoryRows(filterNeweggCatalog(query));
    }

    function loadNeweggCategories() {
        const box = document.getElementById('listing-publish-newegg-category-results');
        if (!box || !isNeweggChannel()) return;
        const nameEl = document.getElementById('listing-publish-newegg-category-name');
        const query = nameEl ? String(nameEl.value || '').trim() : '';
        if (neweggCategoryCatalog) {
            showLoadedNeweggCategories(query);
            return;
        }
        showNeweggCategoryResults('<div class="listing-publish-cat-empty">Loading Newegg subcategories…</div>');
        if (neweggCatXhr && neweggCatXhr.abort) neweggCatXhr.abort();
        neweggCatXhr = $.ajax({
            url: cfg().categorySearchUrl || '/listing-manager/ebay/categories',
            type: 'GET',
            data: {
                all: 1,
                q: '',
                channel: cfg().channel || 'neweggb2c'
            },
            dataType: 'json',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            success: function (res) {
                neweggCategoryCatalog = (res && res.categories) || [];
                if (!neweggCategoryCatalog.length) {
                    showNeweggCategoryResults('<div class="listing-publish-cat-empty">' +
                        escapeHtml((res && res.message) || 'Newegg returned no subcategories.') + '</div>');
                    return;
                }
                showLoadedNeweggCategories(query);
            },
            error: function (xhr, status) {
                if (status === 'abort') return;
                showNeweggCategoryResults('<div class="listing-publish-cat-empty">' +
                    escapeHtml(ajaxError(xhr) || 'Could not load Newegg categories.') + '</div>');
            }
        });
    }

    function searchNeweggCategories(query) {
        if (!isNeweggChannel()) return;
        if (!neweggCategoryCatalog) {
            loadNeweggCategories();
            return;
        }
        showLoadedNeweggCategories(query);
    }

    function scheduleNeweggCategorySearch(query) {
        clearTimeout(neweggCatTimer);
        neweggCatTimer = setTimeout(function () { searchNeweggCategories(query); }, 280);
    }

    function pickNeweggCategory(id, path) {
        const idEl = document.getElementById('listing-publish-newegg-category-id');
        const nameEl = document.getElementById('listing-publish-newegg-category-name');
        const pathEl = document.getElementById('listing-publish-newegg-category-path');
        if (idEl) idEl.value = String(id || '').replace(/\D+/g, '');
        if (nameEl) nameEl.value = String(path || '').trim();
        if (pathEl) pathEl.textContent = String(path || '').trim() || 'Newegg subcategory selected.';
        hideNeweggCategoryResults();
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
                    ? 'From Dim/Wt Master (lb). We convert to kg and US Package weight for AliExpress.'
                    : 'Using the pounds you typed. We convert to kg and US Package weight for AliExpress.';
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
        const alibabaBox = document.getElementById('listing-publish-alibaba-category');
        if (alibabaBox) alibabaBox.hidden = !isAlibabaChannel();
        const neweggBox = document.getElementById('listing-publish-newegg-category');
        if (neweggBox) neweggBox.hidden = !isNeweggChannel();
        if (isNeweggChannel()) loadNeweggCategories();
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
                : (perParentCategoryMode()
                    ? 'One listing per parent. Choose a category on each parent. Use × to remove a parent from this publish; the others stay.'
                    : 'One marketplace listing per group. Only the SKUs you check are included — suggested siblings stay off until you check them.');
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

    // ---- Per-SKU category (single listing mode) ------------------------------------------------
    // Every SKU in a single listing is its own product, so it gets its own category: auto-detected
    // from the product type, and the user can search and pick a different one per SKU.
    let skuCats = {};
    let skuCatTimer = null;
    let skuCatXhr = null;

    function perSkuCategoryMode() {
        if (selectedPublishMode() !== 'single') return false;
        return isEbayChannel() || isTiktokChannel() || isSheinChannel() || isWayfairChannel() || isReverbChannel();
    }

    function ensureSkuCatStyle() {
        if (document.getElementById('lp-sku-cat-style')) return;
        const st = document.createElement('style');
        st.id = 'lp-sku-cat-style';
        st.textContent =
            'body.lp-per-sku-cat #listingPublishModal .listing-publish-category{display:none !important;}' +
            '.lp-cat-label{font-size:12px;line-height:1.3;word-break:break-word;}' +
            '.lp-cat-label.is-auto{color:#6c757d;font-style:italic;}' +
            '.lp-cat-results{max-height:170px;overflow:auto;border:1px solid #dee2e6;border-radius:4px;background:#fff;margin-top:4px;}' +
            '.lp-cat-item{display:block;width:100%;text-align:left;border:0;border-bottom:1px solid #f1f1f1;background:#fff;padding:4px 6px;font-size:12px;}' +
            '.lp-cat-item:hover{background:#eef4ff;}' +
            '.lp-cat-empty{padding:6px;font-size:12px;color:#6c757d;}';
        document.head.appendChild(st);
    }

    function skuCategoryCell(sku) {
        const cat = skuCats[sku] || {};
        const text = String(cat.path || cat.name || '').trim();
        return '<td class="lp-cat-cell" data-sku="' + escapeHtml(sku) + '" style="min-width:230px;">' +
            '<div class="lp-cat-label' + (text ? '' : ' is-auto') + '">' +
            (text ? escapeHtml(text) : 'Auto-detect from product type') + '</div>' +
            '<button type="button" class="btn btn-link btn-sm p-0 lp-cat-change" data-sku="' + escapeHtml(sku) + '">Change category</button>' +
            '<div class="lp-cat-editor" hidden>' +
            '<input type="text" class="form-control form-control-sm lp-cat-input" placeholder="Search a category…" autocomplete="off">' +
            '<div class="lp-cat-results" hidden></div></div></td>';
    }

    function skuCategorySearch(cell, query) {
        const results = cell.querySelector('.lp-cat-results');
        if (!results) return;
        query = String(query || '').trim();
        if (query.length < 2) {
            results.hidden = true;
            results.innerHTML = '';
            return;
        }
        results.hidden = false;
        results.innerHTML = '<div class="lp-cat-empty">Searching…</div>';
        if (skuCatXhr && skuCatXhr.abort) skuCatXhr.abort();
        skuCatXhr = $.ajax({
            url: cfg().categorySearchUrl || '/listing-manager/ebay/categories',
            type: 'GET',
            data: { q: query, channel: isReverbChannel() ? 'reverb' : (cfg().channel || ''), title: query },
            dataType: 'json',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            success: function (res) {
                const rows = (res && res.categories) || [];
                if (!rows.length) {
                    results.innerHTML = '<div class="lp-cat-empty">' + escapeHtml((res && res.message) || 'No categories matched.') + '</div>';
                    return;
                }
                results.innerHTML = rows.map(function (row) {
                    return '<button type="button" class="lp-cat-item" data-id="' + escapeHtml(row.id || '') +
                        '" data-path="' + escapeHtml(row.path || '') + '">' + escapeHtml(row.path || row.id || '') + '</button>';
                }).join('');
            },
            error: function (xhr, status) {
                if (status === 'abort') return;
                results.innerHTML = '<div class="lp-cat-empty">' + escapeHtml(ajaxError(xhr) || 'Category search failed.') + '</div>';
            }
        });
    }

    function bindSkuCategoryUi() {
        $(document).off('click.lpSkuCat', '.lp-cat-change').on('click.lpSkuCat', '.lp-cat-change', function (e) {
            e.preventDefault();
            const cell = this.closest('.lp-cat-cell');
            const editor = cell && cell.querySelector('.lp-cat-editor');
            if (!editor) return;
            editor.hidden = !editor.hidden;
            if (!editor.hidden) {
                const input = editor.querySelector('.lp-cat-input');
                if (input) input.focus();
            }
        });
        $(document).off('input.lpSkuCat', '.lp-cat-input').on('input.lpSkuCat', '.lp-cat-input', function () {
            const cell = this.closest('.lp-cat-cell');
            const q = this.value;
            clearTimeout(skuCatTimer);
            skuCatTimer = setTimeout(function () { skuCategorySearch(cell, q); }, 280);
        });
        $(document).off('click.lpSkuCat', '.lp-cat-item').on('click.lpSkuCat', '.lp-cat-item', function (e) {
            e.preventDefault();
            const cell = this.closest('.lp-cat-cell');
            if (!cell) return;
            const sku = String(cell.getAttribute('data-sku') || '');
            const path = String(this.getAttribute('data-path') || '').trim();
            const id = String(this.getAttribute('data-id') || '').trim();
            skuCats[sku] = { id: id, path: path, name: path, own: true };
            const label = cell.querySelector('.lp-cat-label');
            if (label) {
                label.textContent = path || id;
                label.classList.remove('is-auto');
            }
            const editor = cell.querySelector('.lp-cat-editor');
            if (editor) editor.hidden = true;
        });
    }

    // Category sent with one SKU's publish request. Empty values make the server detect it for that SKU.
    function perSkuCategoryFor(skus) {
        if (!perSkuCategoryMode() || !skus || skus.length !== 1) return null;
        const cat = skuCats[String(skus[0] || '').trim()] || {};
        const id = String(cat.id || '').trim();
        const name = String(cat.path || cat.name || '').trim();
        if (isReverbChannel()) return { category_id: '', category_name: name, category_uuid: id };
        return { category_id: id.replace(/\D+/g, ''), category_name: name, category_uuid: '' };
    }

    function channelKey() {
        return String(cfg().channel || '').toLowerCase().replace(/[\s_-]/g, '');
    }

    // Marketplaces whose publish window can choose a category. Others keep the close button only.
    function channelHasCategory() {
        const c = channelKey();
        if (isEbayChannel() || isTiktokChannel() || isSheinChannel() || isWayfairChannel()
            || isReverbChannel() || isNeweggChannel() || isAliexpressChannel() || isAlibabaChannel()) {
            return true;
        }
        if (c === 'amazon' || c === 'amazonfba' || c === 'amz' || c === 'amzfbm' || c === 'faire') return true;
        if (c === 'temu' || c === 'temu1' || c === 'temu2' || c === 'temutwo' || c === 'temu3' || c === 'temuthree') return true;
        if (c === 'macys' || c === 'macy' || c === 'bestbuy' || c === 'bestbuyusa' || c === 'purchasingpower') return true;
        if (c === 'topdawg' || c === 'topdawginc') return true;
        return false;
    }

    // Variation mode: each parent is its own listing, so it gets its own category.
    function perParentCategoryMode() {
        if (selectedPublishMode() === 'single') return false;
        return channelHasCategory();
    }

    function ensureParentCatStyle() {
        if (document.getElementById('lp-parent-cat-style')) return;
        const st = document.createElement('style');
        st.id = 'lp-parent-cat-style';
        st.textContent =
            'body.lp-per-parent-cat .lp-shared-cat{display:none !important;}' +
            '.listing-publish-group-head{display:flex;align-items:center;justify-content:space-between;gap:8px;}' +
            '.listing-publish-group-title{min-width:0;}' +
            '.listing-publish-group-close{border:0;background:transparent;color:#64748b;width:28px;height:28px;border-radius:6px;font-size:20px;line-height:1;cursor:pointer;flex:0 0 auto;padding:0;}' +
            '.listing-publish-group-close:hover{background:#fee2e2;color:#b91c1c;}' +
            '.lp-parent-cat{padding:8px 12px 10px;border-bottom:1px solid #e2e8f0;background:#fff;}' +
            '.lp-parent-cat[hidden]{display:none !important;}' +
            '.lp-parent-cat label{display:block;font-weight:700;font-size:12px;margin-bottom:4px;}' +
            '.lp-parent-cat-path{font-weight:700;font-size:12px;color:#0f172a;margin:0 0 4px;line-height:1.35;}' +
            '.lp-parent-cat small{display:block;color:#64748b;margin-top:4px;}' +
            'body.lp-per-parent-cat .listing-publish-group,.temu-publish-group,.temu2-publish-group,.temu3-publish-group{overflow:visible;}' +
            '.temu-publish-group-head,.temu2-publish-group-head,.temu3-publish-group-head{display:flex;align-items:center;justify-content:space-between;gap:8px;}';
        document.head.appendChild(st);
    }

    function groupCountLabel(count, single) {
        return single
            ? ' selected for single listing' + (count === 1 ? '' : 's')
            : ' selected for this variation';
    }

    function parentCategoryBlock(parent, suggested) {
        const cat = suggested || {};
        const id = String(cat.id || '').trim();
        const path = String(cat.path || '').trim();
        const name = String(cat.name || '').trim();
        if (isAlibabaChannel()) {
            const numeric = id.replace(/\D+/g, '');
            return '<div class="lp-parent-cat lp-parent-cat-id" data-parent="' + escapeHtml(parent) + '"' +
                ' data-cat-id="' + escapeHtml(numeric) + '" data-cat-uuid="" data-cat-name="">' +
                '<label>Category id for ' + escapeHtml(parent) + '</label>' +
                '<input type="text" class="form-control form-control-sm lp-parent-cat-input" inputmode="numeric" value="' + escapeHtml(numeric) + '" placeholder="Alibaba category id" autocomplete="off">' +
                '<small>Used only for this parent. Leave blank to copy the category from a listed sibling.</small>' +
                '</div>';
        }
        const leaf = (path || name).split(/[>\/|]/).pop().trim();
        const shown = path || name;
        const uuid = isReverbChannel() ? id : '';
        return '<div class="lp-parent-cat" data-parent="' + escapeHtml(parent) + '"' +
            ' data-cat-id="' + escapeHtml(isReverbChannel() ? '' : id) + '"' +
            ' data-cat-uuid="' + escapeHtml(uuid) + '"' +
            ' data-cat-name="' + escapeHtml(shown) + '">' +
            '<label>Category for ' + escapeHtml(parent) + '</label>' +
            '<div class="lp-parent-cat-path">' + (shown
                ? escapeHtml(shown)
                : 'No category matched yet. Search and pick one for this parent.') + '</div>' +
            '<div class="listing-publish-cat-wrap">' +
            '<input type="text" class="form-control form-control-sm lp-parent-cat-input" value="' + escapeHtml(leaf || shown) + '" placeholder="Search a category…" autocomplete="off">' +
            '<div class="lp-parent-cat-results listing-publish-cat-results"></div>' +
            '</div>' +
            '<small>Used when you create a new parent. Adding to an existing listing keeps that listing’s category.</small>' +
            '</div>';
    }

    let parentCatTimer = null;

    function parentCategorySearch(block, query) {
        const results = block && block.querySelector('.lp-parent-cat-results');
        if (!results) return;
        query = String(query || '').trim();
        if (query.length < 2) {
            results.classList.remove('is-open');
            results.innerHTML = '';
            return;
        }
        results.classList.add('is-open');
        results.innerHTML = '<div class="listing-publish-cat-empty">Searching…</div>';
        if (block._lpCatXhr && block._lpCatXhr.abort) block._lpCatXhr.abort();
        block._lpCatXhr = $.ajax({
            url: cfg().categorySearchUrl || '/listing-manager/ebay/categories',
            type: 'GET',
            data: { q: query, channel: isReverbChannel() ? 'reverb' : (cfg().channel || ''), title: query },
            dataType: 'json',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            success: function (res) {
                const rows = (res && res.categories) || [];
                if (!rows.length) {
                    results.innerHTML = '<div class="listing-publish-cat-empty">' + escapeHtml((res && res.message) || 'No categories matched.') + '</div>';
                    return;
                }
                // Temu: tell the user why names are missing while the category tree is still being downloaded.
                const note = (res && res.message && /^temu[12]?$/.test(String(cfg().channel || '')))
                    ? '<div class="listing-publish-cat-empty">' + escapeHtml(res.message) + '</div>' : '';
                results.innerHTML = note + rows.map(function (row) {
                    return '<button type="button" class="listing-publish-cat-item lp-parent-cat-item" data-id="' + escapeHtml(row.id || '') +
                        '" data-path="' + escapeHtml(row.path || '') + '">' + escapeHtml(row.path || row.id || '') + '</button>';
                }).join('');
            },
            error: function (xhr, status) {
                if (status === 'abort') return;
                results.innerHTML = '<div class="listing-publish-cat-empty">' + escapeHtml(ajaxError(xhr) || 'Category search failed.') + '</div>';
            }
        });
    }

    function applyParentCategoryPick(block, id, path) {
        if (!block) return;
        const cleanId = String(id || '').trim();
        const cleanPath = String(path || '').trim();
        if (isReverbChannel()) {
            block.setAttribute('data-cat-id', '');
            block.setAttribute('data-cat-uuid', cleanId);
        } else {
            block.setAttribute('data-cat-id', cleanId);
            block.setAttribute('data-cat-uuid', '');
        }
        block.setAttribute('data-cat-name', cleanPath);
        const input = block.querySelector('.lp-parent-cat-input');
        if (input) input.value = cleanPath;
        const pathEl = block.querySelector('.lp-parent-cat-path');
        if (pathEl) pathEl.textContent = cleanPath || 'Category selected.';
        const results = block.querySelector('.lp-parent-cat-results');
        if (results) {
            results.classList.remove('is-open');
            results.innerHTML = '';
        }
        const btn = document.getElementById('listing-publish-confirm');
        if (btn) $(btn).data('specificsChecked', false);
    }

    function syncParentCategoryVisibility(groupEl) {
        const cat = groupEl && groupEl.querySelector('.lp-parent-cat');
        if (!cat) return;
        // Always show it. Hiding it while "Add to an existing parent" was selected removed the
        // only category field in this window, including after switching to "Create a new parent".
        cat.hidden = false;
    }

    function mapParentCategory(raw) {
        raw = raw || {};
        const id = String(raw.category_id || '').trim();
        const name = String(raw.category_name || '').trim();
        const uuid = String(raw.category_uuid || '').trim();
        if (!id && !name && !uuid) return null;
        if (isReverbChannel()) return { category_id: '', category_name: name, category_uuid: uuid || id };
        if (/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(id || uuid)) {
            return { category_id: '', category_name: name, category_uuid: id || uuid };
        }
        if (isAlibabaChannel()) return { category_id: id.replace(/\D+/g, ''), category_name: '', category_uuid: '' };
        if (/^\d+$/.test(id)) {
            const c = channelKey();
            const codeAsName = c === 'macys' || c === 'macy' || c === 'bestbuy' || c === 'bestbuyusa' || c === 'purchasingpower';
            return { category_id: id, category_name: codeAsName ? id : name, category_uuid: '' };
        }
        return { category_id: '', category_name: id || name, category_uuid: '' };
    }

    function perParentCategoryFromGroup(group) {
        if (!perParentCategoryMode() || !group) return null;
        return mapParentCategory(group);
    }

    function readParentCategoryEl(groupEl) {
        const catEl = groupEl && groupEl.querySelector ? groupEl.querySelector('.lp-parent-cat') : null;
        if (!catEl || catEl.hidden) return { category_id: '', category_name: '', category_uuid: '' };
        const typed = catEl.querySelector('.lp-parent-cat-input');
        return mapParentCategory({
            category_id: String(catEl.getAttribute('data-cat-id') || '').trim(),
            category_uuid: String(catEl.getAttribute('data-cat-uuid') || '').trim(),
            category_name: String((typed && typed.value) || catEl.getAttribute('data-cat-name') || '').trim()
        }) || { category_id: '', category_name: '', category_uuid: '' };
    }

    function specificsOwnerKey(parent, skus) {
        if (selectedPublishMode() === 'single' && skus && skus.length === 1) return String(skus[0]);
        if (perParentCategoryMode()) return String(parent || '');
        return '';
    }

    function renderGroups(groups) {
        lastPreviewGroups = groups || [];
        ensureSkuCatStyle();
        ensureParentCatStyle();
        document.body.classList.toggle('lp-per-sku-cat', perSkuCategoryMode());
        document.body.classList.toggle('lp-per-parent-cat', perParentCategoryMode());
        const box = document.getElementById('listing-publish-groups');
        const confirm = document.getElementById('listing-publish-confirm');
        if (!box) return;
        if (!groups || !groups.length) {
            box.innerHTML = '<p class="text-muted mb-0">No Missing L children to publish.</p>';
            if (confirm) confirm.disabled = true;
            return;
        }
        const single = selectedPublishMode() === 'single';
        const perSku = perSkuCategoryMode();
        const perParent = perParentCategoryMode();
        let html = '';
        let canPublish = false;
        groups.forEach(function (group, gi) {
            const parent = String(group.parent || 'Standalone');
            const children = group.children || [];
            const selectedCount = children.filter(childShouldCheck).length;
            html += '<div class="listing-publish-group" data-group-index="' + gi + '" data-parent="' + escapeHtml(parent) + '">';
            html += '<div class="listing-publish-group-head">' +
                '<span class="listing-publish-group-title">' + escapeHtml(parent) + ' · ' + selectedCount + groupCountLabel(selectedCount, single) + '</span>' +
                '<button type="button" class="listing-publish-group-close" title="Remove this parent from this publish" aria-label="Remove ' + escapeHtml(parent) + '">&times;</button>' +
                '</div>';
            if (perParent) html += parentCategoryBlock(parent, group.suggested_category);
            html += attachBlock(group, gi, single);
            html += '<table class="table table-sm mb-0"><thead><tr><th style="width:36px;"></th><th>SKU</th>';
            if (!single) html += '<th>Variation Type</th><th>Variation Name</th>';
            html += '<th>INV</th><th>In this listing</th>' + (perSku ? '<th>Category</th>' : '') + '<th>Status</th></tr></thead><tbody>';
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
                html += '<td>' + escapeHtml(sku) + '</td>';
                if (!single) {
                    const type = String(child.variation_type || '').trim();
                    const name = String(child.variation_name || '').trim();
                    html += '<td>' + (type ? escapeHtml(type) : '—') + '</td>';
                    html += '<td>' + (name ? escapeHtml(name) : '—') + '</td>';
                }
                html += '<td>' + escapeHtml(String(child.inv ?? 0)) + '</td>';
                html += '<td>' + role + '</td>';
                if (perSku) html += skuCategoryCell(sku);
                html += '<td>' + statusLabel(child.status, child.reason) + '</td></tr>';
            });
            html += '</tbody></table></div>';
        });
        box.innerHTML = html;
        box.querySelectorAll('.listing-publish-group').forEach(syncParentCategoryVisibility);
        if (confirm) confirm.disabled = !canPublish;
        const progress = document.getElementById('listing-publish-progress');
        if (progress) progress.textContent = '';
        updateModalCopy();
        refreshConfirmState();
    }

    // Variation mode: ask whether the selected SKUs join a parent listing that already exists
    // on this marketplace or start a new parent. Only shown for marketplaces that can attach.
    // Every marketplace gets the picker: search + select any existing parent, or name a new one.
    // eBay / AliExpress attach by listing id (and must be told when the group already has a parent);
    // other marketplaces publish the SKUs under the chosen parent's name.
    function attachBlock(group, gi, single) {
        if (single || !group || !window.ListingParentPicker) return '';
        const supported = !!group.attach_supported;
        return window.ListingParentPicker.block(group, gi, {
            channel: String(cfg().channel || ''),
            label: channelLabel(),
            attachSupported: supported,
            required: supported && (group.existing_parents || []).length > 0
        });
    }

    function panelShown(el) {
        if (!el) return false;
        const st = window.getComputedStyle ? window.getComputedStyle(el) : null;
        return !st || (st.display !== 'none' && st.visibility !== 'hidden');
    }

    // Read the parent choice from what is actually on screen. The list box can paint a row
    // as selected while its value stays empty, so the listing id is also taken from the row text.
    function parentChoiceFromDom(groupEl) {
        const box = groupEl && groupEl.querySelector ? groupEl.querySelector('.lp-parent') : null;
        const pick = window.ListingParentPicker ? window.ListingParentPicker.read(groupEl) : null;
        if (!box) return pick;
        const radios = box.querySelectorAll('.lp-parent-radio');
        let attach = '';
        radios.forEach(function (radio) {
            if (radio.checked) attach = String(radio.value || '');
        });
        const existingPanel = box.querySelector('.lp-existing');
        const newPanel = box.querySelector('.lp-new');
        if (!attach) {
            if (panelShown(newPanel) && !panelShown(existingPanel)) attach = 'new';
            else if (panelShown(existingPanel)) attach = 'existing';
        }
        let existingId = String((pick && pick.existingId) || box.getAttribute('data-chosen-id') || '').trim();
        const select = box.querySelector('.lp-parent-select');
        if (!existingId && select && select.options && select.options.length) {
            const idx = select.selectedIndex >= 0 ? select.selectedIndex : (select.options.length === 1 ? 0 : -1);
            const opt = idx >= 0 ? select.options[idx] : null;
            if (opt) {
                existingId = String(opt.getAttribute('value') || opt.value || '').trim();
                if (!existingId) {
                    const match = String(opt.textContent || '').match(/ID\s+([0-9]+)/);
                    if (match) existingId = match[1];
                }
            }
        }
        const nameInput = box.querySelector('.lp-parent-name');
        const newParent = String((nameInput && nameInput.value) || (pick && pick.newParent) || '').trim();
        const supported = box.getAttribute('data-attach-supported') === '1' || !!(pick && pick.supported);
        const required = box.getAttribute('data-required') === '1';
        const ready = attach === 'new' ? newParent !== ''
            : attach === 'existing' ? existingId !== ''
            : !required;
        return {
            attach: attach,
            supported: supported,
            required: required,
            existingId: existingId,
            existingParent: String((pick && pick.existingParent) || box.getAttribute('data-chosen-parent') || '').trim(),
            newParent: newParent,
            ready: ready
        };
    }

    // The Publish button stays off only when no SKU is ticked. A highlighted parent row counts.
    function refreshConfirmState() {
        const confirm = document.getElementById('listing-publish-confirm');
        if (!confirm) return;
        const anyChecked = $('#listing-publish-groups .listing-publish-sku-check:checked:not(:disabled)').length > 0;
        let pending = false;
        if (anyChecked && selectedPublishMode() !== 'single') {
            document.querySelectorAll('#listing-publish-groups .listing-publish-group').forEach(function (el) {
                if (!el.querySelector('.listing-publish-sku-check:checked:not(:disabled)')) return;
                const pick = parentChoiceFromDom(el);
                if (pick && pick.required && !pick.ready) pending = true;
            });
        }
        confirm.disabled = !anyChecked || pending;
        const progress = document.getElementById('listing-publish-progress');
        if (progress && !confirm.querySelector('.fa-spin')) {
            progress.textContent = (anyChecked && pending)
                ? 'Pick an existing parent listing, or choose "Create a new parent listing" and type its name, to continue.'
                : '';
        }
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
            if (skus.length) {
                const entry = { parent: parent, skus: skus, attach: '', existingId: '', attachSupported: false, hint: '' };
                const pick = parentChoiceFromDom(el);
                if (pick) {
                    entry.attachSupported = pick.supported;
                    if (pick.supported) {
                        entry.attach = pick.attach;
                        entry.existingId = pick.attach === 'existing' ? pick.existingId : '';
                    }
                    // Parent name sent to the marketplace: the name typed for a new parent, or the chosen
                    // existing parent's name (eBay / AliExpress keep the group's own name when attaching by id).
                    if (pick.attach === 'new' && pick.newParent) {
                        entry.hint = pick.newParent;
                    } else if (pick.attach === 'existing' && !pick.supported && pick.existingParent) {
                        entry.hint = pick.existingParent;
                    }
                }
                const catEl = el.querySelector('.lp-parent-cat');
                if (catEl && !catEl.hidden) {
                    entry.category_id = String(catEl.getAttribute('data-cat-id') || '').trim();
                    entry.category_uuid = String(catEl.getAttribute('data-cat-uuid') || '').trim();
                    const typed = catEl.querySelector('.lp-parent-cat-input');
                    entry.category_name = String((typed && typed.value) || catEl.getAttribute('data-cat-name') || '').trim();
                }
                groups.push(entry);
            }
        });
        return groups;
    }

    function groupsForPublish() {
        const groups = selectedGroups();
        // Business 5 Core stores one listing per SKU. Sending a whole parent
        // in one request outlasts the gateway and the dialog says publish failed.
        const oneEach = selectedPublishMode() === 'single' || String(cfg().channel || '').toLowerCase() === 'b5cb2b';
        if (!oneEach) return groups;
        const out = [];
        groups.forEach(function (group) {
            group.skus.forEach(function (sku) {
                out.push({ parent: sku, skus: [sku] });
            });
        });
        return out;
    }

    function channelLabel() {
        return String(cfg().channelLabel || 'The marketplace').trim() || 'The marketplace';
    }

    function withChannelName(text) {
        return String(text || '').replace(/AliExpress/gi, channelLabel());
    }

    function ajaxError(xhr) {
        if (!xhr) return 'Request failed.';
        const marketplace = channelLabel();
        if (xhr.statusText === 'timeout') {
            return marketplace + ' took too long to respond. Refresh Missing L before trying again — the listing may already be created.';
        }
        if (xhr.status === 0) return 'Timed out or the connection dropped. Try again.';
        let json = xhr.responseJSON;
        if (!json && xhr.responseText) {
            try { json = JSON.parse(xhr.responseText); } catch (e) { json = null; }
        }
        if (json && (json.message || json.error)) return withChannelName(String(json.message || json.error));
        if (xhr.status === 419) return 'Session expired. Refresh the page.';
        if (xhr.status === 405) return 'Publish route is blocked. Hard-refresh the page and try again.';
        if (xhr.status === 502 || xhr.status === 504) {
            return marketplace + ' took too long to respond. Refresh Missing L before trying again — the listing may already be created.';
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
        resetEbaySpecifics();
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
                skuCats = {};
                const suggestedMap = (response && response.suggested_categories) || {};
                Object.keys(suggestedMap).forEach(function (k) { skuCats[k] = suggestedMap[k]; });
                renderGroups((response && response.groups) || []);
                if (!perParentCategoryMode()) {
                    if (isReverbChannel()) applySuggestedCategory(response && response.suggested_category);
                    if (isWayfairChannel()) applySuggestedWayfairCategory(response && response.suggested_category);
                    if (isEbayChannel()) applySuggestedEbayCategory(response && response.suggested_category);
                    if (isTiktokChannel()) applySuggestedTiktokCategory(response && response.suggested_category);
                    if (isSheinChannel()) applySuggestedSheinCategory(response && response.suggested_category);
                }
                if (isAliexpressChannel()) {
                    applySuggestedAliexpressCategory(response && response.suggested_category);
                    applySuggestedAliexpressPackage(response && response.suggested_package);
                }
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

    function ebaySpecificsBox() {
        let box = document.getElementById('listing-publish-ebay-specifics');
        if (box) return box;
        const anchor = document.getElementById('listing-publish-ebay-category');
        const groupsBox = document.getElementById('listing-publish-groups');
        if ((!anchor || !anchor.parentNode) && (!groupsBox || !groupsBox.parentNode)) return null;
        box = document.createElement('div');
        box.id = 'listing-publish-ebay-specifics';
        box.className = 'border rounded p-2 mb-2';
        box.style.background = '#fff8e6';
        box.hidden = true;
        if (anchor && anchor.parentNode) {
            anchor.parentNode.insertBefore(box, anchor.nextSibling);
        } else {
            groupsBox.parentNode.insertBefore(box, groupsBox);
        }
        return box;
    }

    function resetEbaySpecifics() {
        const box = document.getElementById('listing-publish-ebay-specifics');
        if (box) {
            box.innerHTML = '';
            box.hidden = true;
        }
    }

    // sku given: only that SKU's fields (single listings, each SKU has its own category and fields).
    // no sku: the shared fields used by variation listings.
    function collectEbaySpecifics(sku) {
        const out = {};
        const want = sku ? String(sku).trim() : '';
        $('#listing-publish-ebay-specifics [data-aspect]').each(function () {
            const own = String(this.getAttribute('data-sku') || '');
            if (own !== want) return;
            const name = String(this.getAttribute('data-aspect') || '').trim();
            const value = String(this.value || '').trim();
            if (name && value) out[name] = value;
        });
        return out;
    }

    function renderEbaySpecifics(missing) {
        const box = ebaySpecificsBox();
        if (!box) return;
        const currentBySku = {};
        $('#listing-publish-ebay-specifics [data-aspect]').each(function () {
            const k = String(this.getAttribute('data-sku') || '');
            const n = String(this.getAttribute('data-aspect') || '');
            const v = String(this.value || '').trim();
            if (v) (currentBySku[k] = currentBySku[k] || {})[n] = v;
        });
        let html = '<div class="fw-semibold mb-1">eBay needs these fields for the category</div>' +
            '<div class="small text-muted mb-2">Not found in our masters, Amazon listing or Shopify. Pick or type a value for each field below, then click Publish again.</div>';
        let lastSku = null;
        missing.forEach(function (m, i) {
            const name = String(m.name || '');
            const skuKey = String(m.sku || '');
            const id = 'listing-publish-ebay-spec-' + i;
            const values = Array.isArray(m.values) ? m.values : [];
            const val = (currentBySku[skuKey] || {})[name] || '';
            const hint = m.suggested ? 'e.g. ' + m.suggested : 'Enter ' + name;
            const skuAttr = skuKey ? ' data-sku="' + escapeHtml(skuKey) + '"' : '';
            if (skuKey && skuKey !== lastSku) {
                const who = selectedPublishMode() === 'single' ? 'SKU ' : 'Parent ';
                html += '<div class="small fw-semibold mt-2 mb-1" style="color:#0d6efd;">' + who + escapeHtml(skuKey) + '</div>';
            }
            lastSku = skuKey;
            html += '<div class="mb-2"><label class="form-label small mb-1" for="' + id + '">' + escapeHtml(name) + ' <span class="text-danger">*</span></label>';
            if (values.length && m.free_text === false) {
                html += '<select class="form-select form-select-sm" id="' + id + '" data-aspect="' + escapeHtml(name) + '"' + skuAttr + '>' +
                    '<option value="">Select ' + escapeHtml(name) + '</option>';
                values.forEach(function (v) {
                    html += '<option value="' + escapeHtml(v) + '"' + (v === val ? ' selected' : '') + '>' + escapeHtml(v) + '</option>';
                });
                html += '</select>';
            } else {
                html += '<input type="text" class="form-control form-control-sm" id="' + id + '" data-aspect="' + escapeHtml(name) + '"' + skuAttr +
                    ' value="' + escapeHtml(val) + '" placeholder="' + escapeHtml(hint) + '"' +
                    (values.length ? ' list="' + id + '-list"' : '') + '>';
                if (values.length) {
                    html += '<datalist id="' + id + '-list">';
                    values.forEach(function (v) { html += '<option value="' + escapeHtml(v) + '">'; });
                    html += '</datalist>';
                }
            }
            html += '</div>';
        });
        box.innerHTML = html;
        box.hidden = false;
        const first = box.querySelector('[data-aspect]');
        if (first) first.focus();
    }

    function checkEbaySpecifics(groups) {
        const c = cfg();
        const skuKeyOf = function (group) { return specificsOwnerKey(group.parent, group.skus); };
        const requests = groups.map(function (group) {
            const own = perSkuCategoryFor(group.skus) || perParentCategoryFromGroup(group);
            const specifics = collectEbaySpecifics(skuKeyOf(group));
            return $.ajax({
                url: actionUrl(),
                type: 'POST',
                data: {
                    action: 'ebay_required_specifics',
                    skus: group.skus,
                    channel: c.channel || '',
                    mode: selectedPublishMode(),
                    parent: group.parent || '',
                    category_id: own ? own.category_id : selectedCategoryId(),
                    category_name: own ? own.category_name : selectedCategoryName(),
                    item_specifics: specifics
                },
                headers: { 'X-CSRF-TOKEN': csrf() },
                timeout: 120000
            });
        });
        const done = $.Deferred();
        $.when.apply($, requests).always(function () {
            const responses = requests.length === 1 ? [arguments] : Array.prototype.slice.call(arguments);
            const byName = {};
            responses.forEach(function (args, idx) {
                const res = args && args[0];
                const key = skuKeyOf(groups[idx] || { skus: [] });
                const specifics = collectEbaySpecifics(key);
                ((res && res.missing) || []).forEach(function (m) {
                    if (m && m.name && !specifics[m.name]) {
                        byName[key + '\u0001' + m.name] = Object.assign({}, m, { sku: key });
                    }
                });
            });
            done.resolve(Object.keys(byName).map(function (k) { return byName[k]; }));
        });
        return done.promise();
    }

    function publishGroup(skus, parent, payload) {
        return $.ajax({
            url: actionUrl(),
            type: 'POST',
            data: payload || publishPayload(skus, parent),
            headers: { 'X-CSRF-TOKEN': csrf() },
            timeout: 300000
        });
    }

    function followSubmittedPublish(group, payload, attempt) {
        if (attempt >= 12) return;
        setTimeout(function () {
            publishGroup(group.skus, group.parent, payload).done(function (response) {
                const goodsId = String((response && response.goods_id) || '').trim();
                if (goodsId) {
                    markListed(findTable(), (response && response.skus) || group.skus, goodsId);
                    const status = document.getElementById('listing-publish-status');
                    if (!status || status.hidden) {
                        showPublishStatus('success', (response && response.message) || ('Published ' + group.parent + '.'));
                    }
                    return;
                }
                if (response && response.submitted) followSubmittedPublish(group, payload, attempt + 1);
            }).fail(function (xhr) {
                const status = document.getElementById('listing-publish-status');
                if (!status || status.hidden) showPublishStatus('error', group.parent + ': ' + ajaxError(xhr));
            });
        }, 30000);
    }

    function publishPayload(skus, parent, group) {
        const c = cfg();
        const payload = publishPayloadBase(skus, parent, c);
        if (perParentCategoryMode()) {
            const ownParent = perParentCategoryFromGroup(group) || { category_id: '', category_name: '', category_uuid: '' };
            payload.category_id = ownParent.category_id;
            payload.category_name = ownParent.category_name;
            payload.category_uuid = ownParent.category_uuid;
        }
        if (isEbayChannel() && perParentCategoryMode()) {
            payload.item_specifics = collectEbaySpecifics(String(parent || ''));
        }
        if (group && selectedPublishMode() !== 'single') {
            if (group.attach && group.attachSupported) {
                payload.attach = group.attach;
                payload.existing_listing_id = group.attach === 'existing' ? (group.existingId || '') : '';
            }
            if (group.hint) payload.parent = group.hint;
        }
        return payload;
    }

    function publishPayloadBase(skus, parent, c) {
        const own = perSkuCategoryFor(skus);
        const base = publishPayloadShared(skus, parent, c);
        if (own) {
            base.category_id = own.category_id;
            base.category_name = own.category_name;
            base.category_uuid = own.category_uuid;
        }
        return base;
    }

    function publishPayloadShared(skus, parent, c) {
        return {
                skus: skus,
                confirmed: 1,
                publish: 1,
                channel: c.channel || '',
                mode: selectedPublishMode(),
                parent: parent || '',
                category_id: (isAliexpressChannel() || isWayfairChannel() || isEbayChannel() || isTiktokChannel() || isSheinChannel() || isNeweggChannel()) ? selectedCategoryId() : (selectedCategoryName() ? '' : selectedCategoryId()),
                category_name: selectedCategoryName(),
                category_uuid: selectedCategoryUuid(),
                weight_lb: (isAliexpressChannel() || isTiktokChannel() || isSheinChannel()) ? selectedWeightLb() : '',
                item_specifics: isEbayChannel()
                    ? collectEbaySpecifics(selectedPublishMode() === 'single' && skus && skus.length === 1 ? skus[0] : '')
                    : {}
        };
    }

    function ownPublishModalId() {
        const channel = String(cfg().channel || '').toLowerCase();
        if (channel === 'temu2' && document.getElementById('temu2PublishModal')) return 'temu2PublishModal';
        if (channel === 'temu3' && document.getElementById('temu3PublishModal')) return 'temu3PublishModal';
        return '';
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

        // A page that declares its own Publish column (listingPageConfig.ownPublishColumn) never gets the generic one.
        if (cfg().ownPublishColumn) {
            try { table.deleteColumn('publish_to_marketplace'); } catch (err) {}
            return;
        }

        const hasPublish = (table.getColumns() || []).some(function (col) {
            const def = col.getDefinition ? col.getDefinition() : {};
            // Any page that already has its own "Publish to ..." column (publish_to_temu, publish_to_temu2, ...)
            // must not get a second generic one.
            return /^publish_to_/.test(String(def.field || ''));
        });
        if (!hasPublish && !ownPublishModalId()) {
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
        bindSkuCategoryUi();
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

        $(document).off('click.listingPageTools', '.listing-mp-publish-btn, .temu2-publish-btn, .temu3-publish-btn')
            .on('click.listingPageTools', '.listing-mp-publish-btn, .temu2-publish-btn, .temu3-publish-btn', function (e) {
                if (ownPublishModalId() && ($(this).hasClass('temu2-publish-btn') || $(this).hasClass('temu3-publish-btn'))) {
                    return;
                }
                e.preventDefault();
                e.stopPropagation();
                const sku = String($(this).data('sku') || $(this).attr('data-sku') || '').trim();
                if (sku) openPreview([sku]);
            });

        $('#bulk-publish-btn').off('click.listingPageTools').on('click.listingPageTools', function () {
            if (ownPublishModalId() || cfg().ownPublishColumn) {
                return; // the page has its own publish modal (Temu 1 / 2 / 3)
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
                $group.find('.listing-publish-group-title').text(
                    parent + ' · ' + selectedCount + groupCountLabel(selectedCount, single)
                );
                refreshConfirmState();
            });

        $(document).off('click.listingPageTools', '.listing-publish-group-close')
            .on('click.listingPageTools', '.listing-publish-group-close', function (e) {
                e.preventDefault();
                e.stopPropagation();
                const group = this.closest('.listing-publish-group, .temu-publish-group, .temu2-publish-group, .temu3-publish-group');
                if (!group) return;
                const box = group.parentElement;
                group.remove();
                resetEbaySpecifics();
                const confirmBtn = document.getElementById('listing-publish-confirm');
                if (confirmBtn) $(confirmBtn).data('specificsChecked', false);
                if (box && !box.querySelector('.listing-publish-group, .temu-publish-group, .temu2-publish-group, .temu3-publish-group')) {
                    box.innerHTML = '<p class="text-muted mb-0">No parents left in this publish. The rows on the page are unchanged.</p>';
                }
                const modal = box && box.closest ? box.closest('.modal') : null;
                const modalConfirm = modal && modal.querySelector('[id$="publish-confirm"]');
                if (modalConfirm && box) {
                    const still = box.querySelector('input[type="checkbox"]:checked:not(:disabled)');
                    modalConfirm.disabled = !still;
                }
                refreshConfirmState();
            });

        $(document).off('input.listingPageTools', '.lp-parent-cat-input')
            .on('input.listingPageTools', '.lp-parent-cat-input', function () {
                const block = this.closest('.lp-parent-cat');
                if (!block) return;
                const q = String(this.value || '').trim();
                if (block.classList.contains('lp-parent-cat-id')) {
                    block.setAttribute('data-cat-id', q.replace(/\D+/g, ''));
                    block.setAttribute('data-cat-uuid', '');
                    return;
                }
                block.setAttribute('data-cat-id', '');
                block.setAttribute('data-cat-uuid', '');
                const pathEl = block.querySelector('.lp-parent-cat-path');
                if (pathEl) {
                    pathEl.textContent = q ? 'Searching for “' + q + '”…' : 'Search and pick a category for this parent.';
                }
                const confirmBtn = document.getElementById('listing-publish-confirm');
                if (confirmBtn) $(confirmBtn).data('specificsChecked', false);
                clearTimeout(parentCatTimer);
                parentCatTimer = setTimeout(function () { parentCategorySearch(block, q); }, 280);
            });

        $(document).off('focus.listingPageTools', '.lp-parent-cat-input')
            .on('focus.listingPageTools', '.lp-parent-cat-input', function () {
                const block = this.closest('.lp-parent-cat');
                if (block && block.classList.contains('lp-parent-cat-id')) return;
                document.querySelectorAll('.lp-parent-cat-results').forEach(function (el) {
                    if (!block || el === block.querySelector('.lp-parent-cat-results')) return;
                    el.classList.remove('is-open');
                    el.innerHTML = '';
                });
                const q = String(this.value || '').trim();
                if (block && q.length >= 2) parentCategorySearch(block, q);
            });

        $(document).off('click.listingPageTools', '.lp-parent-cat-item')
            .on('click.listingPageTools', '.lp-parent-cat-item', function (e) {
                e.preventDefault();
                e.stopPropagation();
                applyParentCategoryPick(this.closest('.lp-parent-cat'), this.getAttribute('data-id'), this.getAttribute('data-path'));
            });

        // The parent picker (listing-parent-picker.js) fires this when its choice changes.
        $(document).off('lp-parent:changed.listingPageTools')
            .on('lp-parent:changed.listingPageTools', function () {
                document.querySelectorAll('#listing-publish-groups .listing-publish-group').forEach(syncParentCategoryVisibility);
                const confirmBtn = document.getElementById('listing-publish-confirm');
                if (confirmBtn) $(confirmBtn).data('specificsChecked', false);
                refreshConfirmState();
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
            if (isNeweggChannel() && perParentCategoryMode()) {
                const missingCat = groups.filter(function (g) { return !String(g.category_id || '').replace(/\D+/g, ''); });
                if (missingCat.length) {
                    notify('danger', 'Pick a Newegg subcategory for: ' + missingCat.map(function (g) { return g.parent; }).join(', ') + '.');
                    return;
                }
            } else if (isNeweggChannel() && !selectedCategoryId()) {
                notify('danger', 'Type a Newegg subcategory and pick one from the list.');
                const catEl = document.getElementById('listing-publish-newegg-category-name');
                if (catEl) catEl.focus();
                return;
            }
            // Adding to an existing eBay parent reuses that listing's category and item specifics.
            const specificsGroups = groups.filter(function (g) { return g.attach !== 'existing'; });
            if (isEbayChannel() && specificsGroups.length && !$btn.data('specificsChecked')) {
                const checkHtml = $btn.html();
                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Checking eBay fields');
                checkEbaySpecifics(specificsGroups).then(function (missing) {
                    $btn.prop('disabled', false).html(checkHtml);
                    if (missing.length) {
                        renderEbaySpecifics(missing);
                        notify('danger', 'eBay needs more details before publishing. Fill ' + missing.map(function (m) { return (m.sku ? m.sku + ': ' : '') + m.name; }).join(', ') + ' in the yellow box of this window, then click Publish again.');
                        return;
                    }
                    $btn.data('specificsChecked', true);
                    $btn.trigger('click');
                });
                return;
            }
            $btn.data('specificsChecked', false);
            const originalHtml = $btn.html();
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Publishing');
            $('#listingPublishModal .btn-close, #listingPublishModal [data-bs-dismiss="modal"]').prop('disabled', true);
            $('#listing-publish-mode-box input').prop('disabled', true);
            showPublishStatus('loading', 'Publishing ' + groups[0].parent + ' (1/' + groups.length + ')…');
            let index = 0;
            const ok = [];
            const fail = [];
            let submitted = false;
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
                        showPublishStatus('success', ok.join('\n') || 'Published.', submitted ? 'Sent to ' + (cfg().channelLabel || 'marketplace') : undefined);
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
                const payload = publishPayload(group.skus, group.parent, group);
                publishGroup(group.skus, group.parent, payload).done(function (response) {
                    const goodsId = String((response && response.goods_id) || '').trim();
                    const listedSkus = (response && response.skus) || group.skus;
                    if (goodsId) markListed(table, listedSkus, goodsId);
                    if (response && response.submitted) {
                        submitted = true;
                        followSubmittedPublish(group, payload, 0);
                    }
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
                    || this.classList.contains('listing-publish-wayfair-cat-item')
                    || this.classList.contains('listing-publish-newegg-cat-item')
                    || this.classList.contains('lp-parent-cat-item')) {
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
                hideNeweggCategoryResults();
                document.querySelectorAll('.lp-parent-cat-results').forEach(function (el) {
                    el.classList.remove('is-open');
                    el.innerHTML = '';
                });
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

    function bindNeweggCategorySearch() {
        function onNeweggCategoryTyped(el) {
            if (!el) return;
            el.dataset.userTyped = '1';
            const idEl = document.getElementById('listing-publish-newegg-category-id');
            if (idEl) idEl.value = '';
            const pathEl = document.getElementById('listing-publish-newegg-category-path');
            const q = String(el.value || '').trim();
            if (pathEl) {
                pathEl.textContent = q
                    ? 'Narrowing the Newegg list…'
                    : 'Loading Newegg subcategories…';
            }
            scheduleNeweggCategorySearch(q);
        }

        $(document).off('input.listingPageToolsNewegg', '#listing-publish-newegg-category-name')
            .on('input.listingPageToolsNewegg', '#listing-publish-newegg-category-name', function () {
                onNeweggCategoryTyped(this);
            });

        $(document).off('keyup.listingPageToolsNewegg', '#listing-publish-newegg-category-name')
            .on('keyup.listingPageToolsNewegg', '#listing-publish-newegg-category-name', function () {
                onNeweggCategoryTyped(this);
            });

        $(document).off('focus.listingPageToolsNewegg', '#listing-publish-newegg-category-name')
            .on('focus.listingPageToolsNewegg', '#listing-publish-newegg-category-name', function () {
                loadNeweggCategories();
            });

        $('#listingPublishModal').off('shown.bs.modal.listingPageToolsNewegg')
            .on('shown.bs.modal.listingPageToolsNewegg', function () {
                const nameEl = document.getElementById('listing-publish-newegg-category-name');
                if (nameEl) nameEl.dataset.userTyped = '';
                if (isNeweggChannel()) loadNeweggCategories();
            });

        $(document).off('click.listingPageToolsNewegg', '.listing-publish-newegg-cat-item')
            .on('click.listingPageToolsNewegg', '.listing-publish-newegg-cat-item', function (e) {
                e.preventDefault();
                e.stopPropagation();
                pickNeweggCategory($(this).attr('data-id'), $(this).attr('data-path'));
            });
    }

    ensureParentCatStyle();
    window.ListingParentCategory = {
        block: parentCategoryBlock,
        read: readParentCategoryEl
    };

    $(function () {
        bindReverbCategorySearch();
        bindEbayCategorySearch();
        bindTiktokCategorySearch();
        bindSheinCategorySearch();
        bindWayfairClassSearch();
        bindNeweggCategorySearch();
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
