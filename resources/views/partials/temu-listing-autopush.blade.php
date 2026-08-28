{{-- Temu / Temu 2: S PRC → listing base via /temu(2)/push-price. Does not use save-sprice. --}}
        (function(global) {
            const TEMU_LISTING_CHANNEL = @json($channelPromoChannel ?? '');
            if (TEMU_LISTING_CHANNEL !== 'temu' && TEMU_LISTING_CHANNEL !== 'temu2') return;

            const TEMU_LISTING_PUSH_URL = TEMU_LISTING_CHANNEL === 'temu2' ? '/temu2/push-price' : '/temu/push-price';
            const TEMU_LISTING_PULL_URL = TEMU_LISTING_CHANNEL === 'temu2' ? '/temu2/pull-price' : '/temu/pull-price';
            const TEMU_LISTING_PERSIST_URL = TEMU_LISTING_CHANNEL === 'temu2'
                ? '/temu2-pricing/update-price'
                : '/temu-pricing/update-price';
            const TEMU_LISTING_MAX = 2;

            let temuListingQ = [];
            let temuListingInflight = 0;
            let temuListingDone = 0;
            let temuListingOk = 0;
            let temuListingFail = 0;
            let temuListingTotal = 0;
            let temuListingCancelled = false;
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
            function temuListingPushBase(sprice) {
                if (typeof temuPushBaseFromSprice !== 'function') return null;
                const base = temuPushBaseFromSprice(sprice);
                return (base != null && base > 0) ? +Number(base).toFixed(2) : null;
            }
            function temuListingCurrentBase(d) {
                const b = parseFloat(d && d.base_price);
                return b > 0 ? +b.toFixed(2) : 0;
            }
            function temuListingNeedsPush(d, sprice) {
                const shown = temuListingShownSprice(d, sprice);
                const base = temuListingPushBase(shown);
                if (!(base > 0)) return false;
                return !temuListingNearly(base, temuListingCurrentBase(d));
            }
            function temuListingSetProgress(active) {
                const total = temuListingTotal;
                const done = temuListingDone;
                const pct = total > 0 ? Math.min(100, Math.round((done / total) * 100)) : 0;
                if (typeof global.setChannelPushSpriceProgress === 'function') {
                    global.setChannelPushSpriceProgress({
                        active: !!active,
                        done: done,
                        total: total,
                        ok: temuListingOk,
                        fail: temuListingFail,
                        pct: pct,
                        title: 'Temu listing',
                        msg: active ? (done + ' / ' + total) : undefined,
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
                $('#ch-promo-reload-push-progress-msg').text(active ? (done + ' / ' + total) : (total ? 'Ready' : 'Ready'));
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
                const patch = {
                    base_price: base,
                    temu_price: rPrice,
                    temu_price_display: full,
                    push_status: 'pushed',
                };
                const d = data || ((row && typeof row.getData === 'function') ? row.getData() : null);
                if (row && typeof row.update === 'function') {
                    row.update(patch);
                    try { row.reformat(); } catch (e) { /* ignore */ }
                } else if (d) {
                    Object.assign(d, patch);
                }
                const sku = String((d && d.sku) || '').trim();
                temuListingPatchDatasets(sku, patch);
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
            function temuListingPullPrice(sku, row, d) {
                if (!sku) return;
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
                    temuApplyPushedListingPrice(row, live, d);
                    temuListingPersistBase(sku, live);
                });
            }
            function temuListingPump() {
                if (temuListingCancelled) return;
                while (temuListingInflight < TEMU_LISTING_MAX && temuListingQ.length) {
                    const item = temuListingQ.shift();
                    temuListingInflight++;
                    const d = (item.row && typeof item.row.getData === 'function')
                        ? (item.row.getData() || {})
                        : {};
                    if (item.row && typeof item.row.update === 'function') {
                        try {
                            item.row.update({ push_status: 'pushing' });
                            item.row.reformat();
                        } catch (e) { /* ignore */ }
                    }
                    temuListingSetProgress(true);
                    $.ajax({
                        url: TEMU_LISTING_PUSH_URL,
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': temuListingCsrf(), 'Accept': 'application/json' },
                        data: {
                            _token: temuListingCsrf(),
                            sku: item.sku,
                            price: item.pushBase,
                            goods_id: d.goods_id || '',
                            sku_id: d.sku_id || '',
                        },
                    }).done(function(resp) {
                        if (resp && resp.success) {
                            temuListingOk++;
                            temuListingPushed.add(String(item.sku).toUpperCase() + '|' + Number(item.pushBase).toFixed(2));
                            temuApplyPushedListingPrice(item.row, item.pushBase, d);
                            temuListingPersistBase(item.sku, item.pushBase);
                            temuListingPullPrice(item.sku, item.row, d);
                        } else {
                            temuListingFail++;
                            if (item.row && typeof item.row.update === 'function') {
                                try {
                                    item.row.update({ push_status: 'error' });
                                    item.row.reformat();
                                } catch (e) { /* ignore */ }
                            }
                        }
                    }).fail(function() {
                        temuListingFail++;
                        if (item.row && typeof item.row.update === 'function') {
                            try {
                                item.row.update({ push_status: 'error' });
                                item.row.reformat();
                            } catch (e) { /* ignore */ }
                        }
                    }).always(function() {
                        temuListingDone++;
                        temuListingInflight--;
                        const busy = temuListingInflight > 0 || temuListingQ.length > 0;
                        temuListingSetProgress(busy);
                        if (!busy) {
                            setTimeout(function() {
                                if (temuListingInflight || temuListingQ.length) return;
                                temuListingDone = 0;
                                temuListingOk = 0;
                                temuListingFail = 0;
                                temuListingTotal = 0;
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
                const pushBase = temuListingPushBase(shown);
                if (!sku || !(pushBase > 0)) return false;
                if (!opts.force && !temuListingNeedsPush(d, shown)) return false;
                const key = String(sku).toUpperCase();
                const dedupe = key + '|' + pushBase.toFixed(2);
                if (temuListingPushed.has(dedupe)) return false;
                temuListingQ = temuListingQ.filter(function(it) {
                    return String(it.sku).toUpperCase() !== key;
                });
                temuListingQ.push({ sku: sku, sprice: shown, pushBase: pushBase, row: tableRow });
                temuListingCancelled = false;
                temuListingTotal = temuListingDone + temuListingQ.length + temuListingInflight;
                temuListingSetProgress(true);
                temuListingPump();
                return true;
            }
            function scanAndQueueTemuListingPush(tbl) {
                if (!temuListingAllowed()) return 0;
                tbl = tbl || (typeof table !== 'undefined' ? table : null);
                if (!tbl) return 0;
                let n = 0;
                const walk = function(row) {
                    if (!row || typeof row.getData !== 'function') return;
                    const d = row.getData() || {};
                    if (d.is_parent || d.is_parent_row || d.is_parent_summary) return;
                    const sku = String(d.sku || d['(Child) sku'] || '').trim();
                    if (!sku || sku.toUpperCase().indexOf('PARENT') !== -1) return;
                    if (enqueueTemuListingPushAfterSave(sku, null, row)) n++;
                    if (typeof row.getTreeChildren === 'function') {
                        (row.getTreeChildren() || []).forEach(walk);
                    }
                };
                let rows = [];
                try { rows = tbl.getRows() || []; } catch (e) { rows = []; }
                rows.forEach(walk);
                return n;
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
        })(window);
