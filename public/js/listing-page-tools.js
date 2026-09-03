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
        if (typeof showNotification === 'function') {
            showNotification(type, message);
            return;
        }
        const el = document.createElement('div');
        el.className = 'position-fixed bottom-0 end-0 p-3';
        el.style.zIndex = '1080';
        el.innerHTML = '<div class="alert alert-' + type + ' alert-dismissible fade show">' + escapeHtml(message) +
            '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        document.body.appendChild(el);
        setTimeout(function () { el.remove(); }, type === 'danger' ? 8000 : 3500);
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

    function isReverbChannel() {
        const c = String(cfg().channel || '').toLowerCase();
        return c === 'reverb' || c === 'reverbcom';
    }

    function selectedCategoryId() {
        const el = document.getElementById('listing-publish-category-id');
        return el ? String(el.value || '').replace(/\D+/g, '') : '';
    }

    function selectedCategoryName() {
        if (isReverbChannel()) {
            const reverbEl = document.getElementById('listing-publish-reverb-category-name');
            if (reverbEl) return String(reverbEl.value || '').trim();
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
        const path = String((suggested && suggested.path) || '').trim();
        const id = String((suggested && suggested.id) || '').trim();
        const name = String((suggested && suggested.name) || '').trim();
        const leaf = path.split(/[>\/|]/).pop().trim();
        if (uuidEl) uuidEl.value = id;
        if (nameEl) nameEl.value = name || leaf || '';
        if (!pathEl) return;
        pathEl.textContent = path || (id
            ? 'Reverb category matched from the product type.'
            : (name
                ? 'Using the product category. Type a Reverb name if you want a different one.'
                : 'No Reverb category matched yet. Type a category name below, or publish and we will try from the title.'));
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

    function applyPublishModeUi() {
        const box = document.getElementById('listing-publish-mode-box');
        if (box) box.style.display = '';
        const catBox = document.getElementById('listing-publish-aliexpress-category');
        if (catBox) catBox.hidden = !isAliexpressChannel();
        const reverbBox = document.getElementById('listing-publish-reverb-category');
        if (reverbBox) reverbBox.hidden = !isReverbChannel();
        if (isReverbChannel()) applySuggestedCategory(null);
        if (isAliexpressChannel()) applySuggestedAliexpressCategory(null);
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
        if (xhr.status === 0) return 'Timed out or the connection dropped. Try again.';
        let json = xhr.responseJSON;
        if (!json && xhr.responseText) {
            try { json = JSON.parse(xhr.responseText); } catch (e) { json = null; }
        }
        if (json && (json.message || json.error)) return String(json.message || json.error);
        if (xhr.status === 419) return 'Session expired. Refresh the page.';
        if (xhr.status === 405) return 'Publish route is blocked. Hard-refresh the page and try again.';
        if (xhr.status === 502 || xhr.status === 504) return 'Publish timed out while talking to AliExpress. Try again.';
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
                if (isAliexpressChannel()) applySuggestedAliexpressCategory(response && response.suggested_category);
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
                category_id: selectedCategoryName() ? '' : selectedCategoryId(),
                category_name: selectedCategoryName(),
                category_uuid: selectedCategoryUuid()
            },
            headers: { 'X-CSRF-TOKEN': csrf() },
            timeout: 180000
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

        $(document).off('input.listingPageTools', '#listing-publish-reverb-category-name')
            .on('input.listingPageTools', '#listing-publish-reverb-category-name', function () {
                const uuidEl = document.getElementById('listing-publish-category-uuid');
                if (uuidEl) uuidEl.value = '';
                const pathEl = document.getElementById('listing-publish-reverb-category-path');
                if (pathEl) {
                    pathEl.textContent = String(this.value || '').trim()
                        ? 'Will match this category name on publish.'
                        : 'Type a Reverb category name, or leave blank to use the product type.';
                }
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
            const originalHtml = $btn.html();
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Publishing');
            $('#listingPublishModal .btn-close, #listingPublishModal [data-bs-dismiss="modal"]').prop('disabled', true);
            $('#listing-publish-mode-box input').prop('disabled', true);
            let index = 0;
            const ok = [];
            const fail = [];
            function next() {
                if (index >= groups.length) {
                    $btn.prop('disabled', false).html(originalHtml);
                    $('#listingPublishModal .btn-close, #listingPublishModal [data-bs-dismiss="modal"]').prop('disabled', false);
                    $('#listing-publish-mode-box input').prop('disabled', false);
                    if (ok.length) notify('success', ok.join(' '));
                    if (fail.length) notify('danger', fail.join(' '));
                    if (ok.length && !fail.length) hideModal();
                    return;
                }
                const group = groups[index];
                index += 1;
                const progress = document.getElementById('listing-publish-progress');
                if (progress) progress.textContent = 'Publishing ' + group.parent + ' (' + index + '/' + groups.length + ')…';
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

    $(function () {
        if (!cfg().tableId) return;
        waitForTable(function (table) {
            enhanceTable(table);
            bindUi(table);
        });
    });
})();
