{{-- Temu / Temu 2 / Temu 3: S PRC → live listing via /temu(2|3)/push-price. Does not use save-sprice. --}}
        (function(global) {
            const TEMU_LISTING_CHANNEL = @json($channelPromoChannel ?? '');
            if (TEMU_LISTING_CHANNEL !== 'temu' && TEMU_LISTING_CHANNEL !== 'temu2' && TEMU_LISTING_CHANNEL !== 'temu3') return;

            const TEMU_LISTING_PUSH_URL = ({
                temu: '/temu/push-price',
                temu2: '/temu2/push-price',
                temu3: '/temu3/push-price',
            })[TEMU_LISTING_CHANNEL] || '';
            const TEMU_LISTING_PULL_URL = ({
                temu: '/temu/pull-price',
                temu2: '/temu2/pull-price',
                temu3: '',
            })[TEMU_LISTING_CHANNEL] || '';
            const TEMU_LISTING_PERSIST_URL = ({
                temu: '/temu-pricing/update-price',
                temu2: '/temu2-pricing/update-price',
                temu3: '/temu3-pricing/update-price',
            })[TEMU_LISTING_CHANNEL] || '';
            const TEMU_LISTING_MAX = 2;

            let temuListingQ = [];
            let temuListingInflight = 0;
            let temuListingDone = 0;
            let temuListingOk = 0;
            let temuListingFail = 0;
            let temuListingTotal = 0;
            let temuListingCancelled = false;
            let temuListingBatchOpen = false;
            let temuListingScanScheduled = false;
            const temuListingPushed = new Set();

            function temuListingCsrf() {
                return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            }
            function temuListingAllowed() {
                if (typeof global.chPromoPageReloadPushAllowed === 'function') {
                    return global.chPromoPageReloadPushAllowed();
                }
                if (typeof global.chPushSpriceAutoPushAllowed === 'function') {
                    return global.chPushSpriceAutoPushAllowed();
                }
                const sw = document.getElementById('ch-promo-reload-push-switch');
                return sw ? !!sw.checked : false;
            }
            function temuListingNearly(a, b) {
                return Math.abs(Number(a) - Number(b)) < 0.015;
            }
            function temuListingShownSprice(d, fallback) {
                if (typeof temuDisplayedSprice === 'function' && d) {
                    const shown = parseFloat(temuDisplayedSprice(d));
                    if (shown > 0) return +shown.toFixed(2);
                }
                const n = parseFloat(fallback != null ? fallback : (d && (d.sprice != null ? d.sprice : d.SPRICE)));
                return n > 0 ? +n.toFixed(2) : 0;
            }
            function temuListingPushAmount(d, sprice) {
                if (d && typeof temuListingPushBase === 'function') {
                    const listing = temuListingPushBase(d);
                    if (listing > 0) return +Number(listing).toFixed(2);
                }
                const shown = temuListingShownSprice(d, sprice);
                if (typeof temuPushBaseFromSprice === 'function') {
                    const base = temuPushBaseFromSprice(shown);
                    if (base > 0) return +Number(base).toFixed(2);
                }
                return null;
            }
            function temuListingCurrentBase(d) {
                const b = parseFloat(d && d.base_price);
                return b > 0 ? +b.toFixed(2) : 0;
            }
            function temuListingNeedsPush(d, sprice) {
                return temuListingHasBlueTriangle(d);
            }
            /** Same set as the blue-triangle badge: INV > 0 and shown S PRC ≠ Temu Price. */
            function temuListingHasBlueTriangle(d) {
                if (!d) return false;
                if (typeof temu2HasBlueTriangle === 'function') return !!temu2HasBlueTriangle(d);
                const inv = parseFloat(d.inventory != null ? d.inventory : d.INV) || 0;
                if (!(inv > 0)) return false;
                const sku = String(d.sku || d['(Child) sku'] || '').trim().toUpperCase();
                if (!sku || sku.indexOf('PARENT') !== -1) return false;
                if (d.is_parent || d.is_parent_row || d.is_parent_summary) return false;
                const sprice = temuListingShownSprice(d);
                const temuPrice = (typeof temuRowTemuPrice === 'function')
                    ? temuRowTemuPrice(d)
                    : (parseFloat(d.temu_price_display || d.temu_price) || 0);
                return sprice > 0 && temuPrice > 0 && Math.round(sprice * 100) !== Math.round(temuPrice * 100);
            }
            function temuListingAutoEligible(d) {
                if (!d) return false;
                const pushSt = String(d.push_status || d.PUSH_PRC_STATUS || d.SPRICE_STATUS || '').toLowerCase();
                if (pushSt === 'error' || pushSt === 'failed') return false;
                return temuListingHasBlueTriangle(d);
            }
            function temuListingStatusPatch(status, extra) {
                return Object.assign({
                    push_status: status,
                    PUSH_PRC_STATUS: status,
                    SPRICE_STATUS: status === 'pushing' ? 'pushing' : status,
                    push_prc: status,
                }, extra || {});
            }
            function temuListingTouchRow(row, sku, patch) {
                let target = (row && typeof row.update === 'function') ? row : null;
                if (!target && sku && typeof temuFindTableRowBySku === 'function') {
                    target = temuFindTableRowBySku(sku);
                }
                if (target && typeof target.update === 'function') {
                    try { target.update(patch); } catch (e) { /* ignore */ }
                    try { target.reformat(); } catch (e) { /* ignore */ }
                    return target;
                }
                return null;
            }
            function temuListingSetProgress(active) {
                const total = temuListingTotal;
                const done = temuListingDone;
                const pct = total > 0 ? Math.min(100, Math.round((done / total) * 100)) : 0;
                const finished = !active && total > 0 && done >= total;
                const doneMsg = finished
                    ? (temuListingOk + ' ok' + (temuListingFail ? (' · ' + temuListingFail + ' failed') : ''))
                    : undefined;
                global.temuListingProgressLocked = !!(active || (total > 0 && done < total));
                if (typeof global.setChannelPushSpriceProgress === 'function') {
                    global.setChannelPushSpriceProgress({
                        active: !!active,
                        done: done,
                        total: total,
                        ok: temuListingOk,
                        fail: temuListingFail,
                        pct: pct,
                        title: 'Temu listing',
                        msg: active ? (done + ' / ' + total) : doneMsg,
                    });
                    return;
                }
                const $inline = $('#ch-promo-reload-push-progress');
                if (!$inline.length) return;
                $inline.toggleClass('is-busy', !!active);
                $inline.toggleClass('is-done', !active && total > 0 && done >= total);
                $inline.toggleClass('is-fail', temuListingFail > 0);
                $('#ch-promo-reload-push-progress-pct').text(pct + '%');
                $('#ch-promo-reload-push-progress-bar').css('width', pct + '%');
                $('#ch-promo-reload-push-progress-msg').text(active ? (done + ' / ' + total) : (doneMsg || 'Ready'));
            }
            function temuListingPatchDatasets(sku, patch) {
                const want = String(sku || '').trim();
                if (!want) return;
                const walk = function(arr) {
                    if (!Array.isArray(arr)) return;
                    arr.forEach(function(row) {
                        if (!row) return;
                        if (String(row.sku || '').trim() === want) Object.assign(row, patch);
                        if (Array.isArray(row._children)) walk(row._children);
                    });
                };
                walk(typeof fullDataset !== 'undefined' ? fullDataset : null);
                walk(typeof allTableData !== 'undefined' ? allTableData : null);
                if (global.allTableData && (typeof allTableData === 'undefined' || global.allTableData !== allTableData)) {
                    walk(global.allTableData);
                }
            }
            function temuApplyPushedListingPrice(row, pushBase, data) {
                const base = +Number(pushBase).toFixed(2);
                if (!(base > 0)) return;
                const full = (typeof temu2FullPriceFromBase === 'function')
                    ? +temu2FullPriceFromBase(base).toFixed(2)
                    : +(base * 1.1364).toFixed(2);
                const rPrice = base <= 26.99 ? +(base + 2.99).toFixed(2) : base;
                const patch = temuListingStatusPatch('pushed', {
                    base_price: base,
                    temu_price: rPrice,
                    temu_price_display: full,
                });
                const d = data || ((row && typeof row.getData === 'function') ? row.getData() : null);
                const sku = String((d && d.sku) || (row && row.sku) || '').trim();
                if (!temuListingTouchRow(row, sku, patch) && d) {
                    Object.assign(d, patch);
                }
                temuListingPatchDatasets(sku, patch);
                if (typeof updateSummary === 'function') {
                    clearTimeout(temuApplyPushedListingPrice._sumTimer);
                    temuApplyPushedListingPrice._sumTimer = setTimeout(function() {
                        try { updateSummary(); } catch (e) { /* ignore */ }
                    }, 400);
                }
            }
            function temuListingPersistBase(sku, base) {
                if (!sku || !(base > 0)) return;
                $.ajax({
                    url: TEMU_LISTING_PERSIST_URL,
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': temuListingCsrf(), 'Accept': 'application/json' },
                    data: { sku: sku, base_price: base, _token: temuListingCsrf() },
                });
            }
            function temuListingPullPrice(sku, row, d, pushedBase) {
                if (!sku || !TEMU_LISTING_PULL_URL) return;
                const sent = parseFloat(pushedBase) || 0;
                const shown = temuListingShownSprice(d);
                $.ajax({
                    url: TEMU_LISTING_PULL_URL,
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': temuListingCsrf(), 'Accept': 'application/json' },
                    data: { _token: temuListingCsrf(), sku: sku, skus: [sku] },
                    timeout: 45000,
                }).done(function(resp) {
                    const results = resp && Array.isArray(resp.results) ? resp.results : [];
                    let live = 0;
                    results.forEach(function(r) {
                        if (!r || !r.success) return;
                        const n = parseFloat(r.base_price != null ? r.base_price : r.price);
                        if (n > 0) live = n;
                    });
                    if (!(live > 0)) return;
                    if (shown > 0 && Math.abs(live - shown) < 0.05 && typeof temuPushBaseFromSprice === 'function') {
                        const inverted = temuPushBaseFromSprice(live);
                        if (inverted > 0) live = inverted;
                    }
                    if (sent > 0 && Math.abs(live - sent) <= 0.011) live = sent;
                    temuApplyPushedListingPrice(row, live, d);
                    temuListingPersistBase(sku, live);
                });
            }
            function temuListingPump() {
                if (!temuListingAllowed()) {
                    cancelTemuListingAutopush();
                    if (typeof global.stopChannelPushSpriceNow === 'function') {
                        global.stopChannelPushSpriceNow();
                    }
                    return;
                }
                if (temuListingCancelled) return;
                while (temuListingInflight < TEMU_LISTING_MAX && temuListingQ.length) {
                    const item = temuListingQ.shift();
                    temuListingInflight++;
                    const d = (item.row && typeof item.row.getData === 'function')
                        ? (item.row.getData() || {})
                        : {};
                    temuListingTouchRow(item.row, item.sku, temuListingStatusPatch('pushing'));
                    temuListingSetProgress(true);
                    $.ajax({
                        url: TEMU_LISTING_PUSH_URL,
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': temuListingCsrf(), 'Accept': 'application/json' },
                        data: {
                            _token: temuListingCsrf(),
                            sku: item.sku,
                            price: item.pushBase,
                            as_base: 1,
                            goods_id: d.goods_id || '',
                            sku_id: d.sku_id || '',
                        },
                    }).done(function(resp) {
                        if (resp && resp.success) {
                            temuListingOk++;
                            temuListingPushed.add(String(item.sku).toUpperCase() + '|' + Number(item.pushBase).toFixed(2));
                            temuApplyPushedListingPrice(item.row, item.pushBase, d);
                            temuListingPersistBase(item.sku, item.pushBase);
                            temuListingPullPrice(item.sku, item.row, d, item.pushBase);
                        } else {
                            temuListingFail++;
                            temuListingTouchRow(item.row, item.sku, temuListingStatusPatch('error'));
                        }
                    }).fail(function() {
                        temuListingFail++;
                        temuListingTouchRow(item.row, item.sku, temuListingStatusPatch('error'));
                    }).always(function() {
                        temuListingDone++;
                        temuListingInflight--;
                        const busy = temuListingInflight > 0 || temuListingQ.length > 0;
                        temuListingSetProgress(busy);
                        if (!busy) {
                            temuListingBatchOpen = false;
                            global.temuListingProgressLocked = false;
                            setTimeout(function() {
                                if (temuListingInflight || temuListingQ.length) return;
                                temuListingDone = 0;
                                temuListingOk = 0;
                                temuListingFail = 0;
                                temuListingTotal = 0;
                                temuListingBatchOpen = false;
                                temuListingScanScheduled = false;
                                global.temuListingProgressLocked = false;
                            }, 12000);
                        } else {
                            setTimeout(temuListingPump, 200);
                        }
                    });
                }
            }
            function enqueueTemuListingPushAfterSave(sku, sprice, row, opts) {
                opts = opts || {};
                if (!temuListingAllowed() && !opts.force) return false;
                const tableRow = (row && typeof row.getData === 'function')
                    ? row
                    : ((typeof temuFindTableRowBySku === 'function') ? temuFindTableRowBySku(sku) : null);
                const d = tableRow
                    ? (tableRow.getData() || {})
                    : ((row && typeof row === 'object') ? row : {});
                const shown = temuListingShownSprice(d, sprice);
                const pushAmount = temuListingPushAmount(d, shown);
                if (!sku || !(pushAmount > 0)) return false;
                if (!opts.force && !temuListingAutoEligible(d)) return false;
                const key = String(sku).toUpperCase();
                const dedupe = key + '|' + pushAmount.toFixed(2);
                if (temuListingPushed.has(dedupe)) return false;
                const already = temuListingQ.some(function(it) {
                    return String(it.sku).toUpperCase() === key;
                });
                if (temuListingBatchOpen && !already) return false;
                temuListingQ = temuListingQ.filter(function(it) {
                    return String(it.sku).toUpperCase() !== key;
                });
                temuListingQ.push({ sku: sku, sprice: shown, pushBase: pushAmount, row: tableRow });
                temuListingCancelled = false;
                if (!temuListingBatchOpen && !already) {
                    temuListingTotal = temuListingDone + temuListingQ.length + temuListingInflight;
                }
                if (!opts.deferPump) {
                    temuListingSetProgress(true);
                    temuListingPump();
                }
                return true;
            }
            function temuListingCollectActive(tbl) {
                const seen = {};
                const found = [];
                const rowBySku = {};
                const consider = function(row, d) {
                    if (!d) return;
                    if (d.is_parent || d.is_parent_row || d.is_parent_summary) return;
                    if (typeof isTemu2ParentRow === 'function' && isTemu2ParentRow(d)) return;
                    const sku = String(d.sku || d['(Child) sku'] || '').trim();
                    const key = sku.toUpperCase();
                    if (!sku || key.indexOf('PARENT') !== -1 || seen[key]) return;
                    if (!temuListingAutoEligible(d)) return;
                    seen[key] = true;
                    found.push({ sku: sku, row: row || d });
                };
                if (tbl && typeof tbl.getRows === 'function') {
                    try {
                        (tbl.getRows('active') || []).forEach(function(row) {
                            if (!row || typeof row.getData !== 'function') return;
                            const d = row.getData() || {};
                            const key = String(d.sku || '').trim().toUpperCase();
                            if (key) rowBySku[key] = row;
                        });
                    } catch (e) { /* ignore */ }
                }
                // Filtered leftover only (SKUs + INV>0 + REQ). Unfiltered getData() is ~2× that.
                if (tbl && typeof tbl.getData === 'function') {
                    try {
                        (tbl.getData('active') || []).forEach(function(d) {
                            const key = String((d && d.sku) || '').trim().toUpperCase();
                            consider(rowBySku[key] || null, d);
                        });
                    } catch (e) { /* ignore */ }
                }
                return found;
            }
            function temuListingStartLockedBatch(tbl) {
                const found = temuListingCollectActive(tbl);
                found.forEach(function(it) {
                    enqueueTemuListingPushAfterSave(it.sku, null, it.row, { deferPump: true });
                });
                temuListingTotal = temuListingQ.length + temuListingInflight + temuListingDone;
                temuListingBatchOpen = temuListingTotal > 0;
                global.temuListingProgressLocked = temuListingBatchOpen;
                if (temuListingTotal > 0) {
                    temuListingSetProgress(true);
                    temuListingPump();
                }
                return found.length;
            }
            function scanAndQueueTemuListingPush(tbl) {
                if (!temuListingAllowed()) return 0;
                if (temuListingBatchOpen && (temuListingQ.length || temuListingInflight || temuListingDone)) {
                    return 0;
                }
                tbl = tbl || (typeof table !== 'undefined' ? table : null);
                const readyCount = (tbl && typeof tbl.getDataCount === 'function') ? tbl.getDataCount() : 0;
                if (!(readyCount > 0)) {
                    setTimeout(function() { scanAndQueueTemuListingPush(tbl); }, 400);
                    return 0;
                }
                if (temuListingScanScheduled) return 0;
                temuListingScanScheduled = true;
                setTimeout(function() {
                    if (temuListingBatchOpen && (temuListingQ.length || temuListingInflight || temuListingDone)) {
                        return;
                    }
                    const n = temuListingStartLockedBatch(tbl);
                    if (!(n > 0)) temuListingScanScheduled = false;
                }, 500);
                return 0;
            }
            function cancelTemuListingAutopush() {
                temuListingQ = [];
                temuListingCancelled = true;
                temuListingSetProgress(temuListingInflight > 0);
                if (!temuListingInflight) {
                    temuListingDone = 0;
                    temuListingOk = 0;
                    temuListingFail = 0;
                    temuListingTotal = 0;
                    temuListingBatchOpen = false;
                    temuListingScanScheduled = false;
                    global.temuListingProgressLocked = false;
                    temuListingSetProgress(false);
                }
            }
            function bindTemuListingCancel() {
                const btn = document.getElementById('ch-promo-reload-push-progress-cancel');
                if (!btn || btn.dataset.temuListingBound === '1') return;
                btn.dataset.temuListingBound = '1';
                btn.dataset.bound = '1';
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    if (!confirm('Cancel remaining Temu listing pushes? Already-pushed SKUs stay on Temu.')) return;
                    cancelTemuListingAutopush();
                }, true);
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', bindTemuListingCancel);
            } else {
                bindTemuListingCancel();
            }

            global.enqueueTemuListingPushAfterSave = enqueueTemuListingPushAfterSave;
            global.scanAndQueueTemuListingPush = scanAndQueueTemuListingPush;
            global.temuApplyPushedListingPrice = temuApplyPushedListingPrice;
            global.temuListingPullPrice = temuListingPullPrice;
            global.cancelTemuListingAutopush = cancelTemuListingAutopush;
            global.temuListingNeedsPush = temuListingNeedsPush;
            global.temuListingHasBlueTriangle = temuListingHasBlueTriangle;
        })(window);
