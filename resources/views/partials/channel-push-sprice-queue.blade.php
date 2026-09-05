{{-- Background S PRC → live eBay listing price. Survives page close. --}}
@php
    $channelPushSpriceChannel = $channelPushSpriceChannel ?? ($channelPromoChannel ?? 'ebay1');
@endphp
        (function(global) {
            const CH_PUSH_SPRICE_CHANNEL = @json($channelPushSpriceChannel);
            const CH_PUSH_SPRICE_LIVE = @json(\App\Services\Support\ChannelPushSpriceRunner::livePushAllowed());
            const CH_PUSH_SPRICE_URL = '/channel-push-sprice/' + encodeURIComponent(CH_PUSH_SPRICE_CHANNEL);
            global._chPushSpriceLiveAllowed = CH_PUSH_SPRICE_LIVE;
            const CH_PUSH_SPRICE_SAVE = ({
                ebay1: '/ebay-one/save-sprice',
                ebay2: '/save-ebay2-sprice',
                ebay2op: '/save-ebay2-sprice',
                ebay3: '/ebay3/save-sprice',
                shopify_b2c: '/shopify/save-sprice',
                shopify_b2b: '/shopify-b2b/save-sprice',
                reverb: '/reverb-save-sprice',
                macys: '/macys-save-sprice-tabulator',
                macy: '/macys-save-sprice-tabulator',
                bestbuy: '/bestbuy-save-sprice',
                walmart: '/save-walmart-sprice',
                wayfair: '/wayfair/pricing-save-sprice',
                temu: '/temu-pricing/save-sprice',
                temu2: '/temu2-pricing/save-sprice',
                doba: '/doba/save-sprice',
                doba_withoutship: '/doba/save-sprice-withoutship',
                tiktok: '/tiktok-save-sprice',
                tiktok2: '/tiktok-2-save-sprice',
                topdawg: '/topdawg-save-sprice',
                purchasing_power: '/pp-save-sprice-tabulator',
                aliexpress: '/aliexpress/save-sprice',
                shein: '/shein/save-sprice',
                newegg: '/newegg-pricing-save-sprice',
                faire: '/faire/pricing-save-sprice',
                pls: '/save-pls-sprice',
                vinted: '/vinted/pricing/save-sprice-tabulator',
                depop: '/depop/pricing/save-sprice',
            })[CH_PUSH_SPRICE_CHANNEL] || '';
            const CH_PUSH_SPRICE_PRICE_FIELD = ({
                ebay1: 'eBay Price',
                ebay2: 'eBay Price',
                ebay2op: 'eBay Price',
                ebay3: 'eBay Price',
                shopify_b2c: 'Price',
                shopify_b2b: 'Price',
                reverb: 'RV Price',
                macys: 'MC Price',
                macy: 'MC Price',
                bestbuy: 'BB Price',
                walmart: 'price',
                wayfair: 'price',
                temu: 'temu_price',
                temu2: 'temu_price',
                doba: 'doba Price',
                doba_withoutship: 'self_pick_price',
                tiktok: 'TT Price',
                tiktok2: 'TT Price',
                topdawg: 'TD Price',
                purchasing_power: 'PP Price',
                aliexpress: 'price',
                shein: 'special_offer',
                newegg: 'price',
                faire: 'price',
                pls: 'price',
                mercari_wship: 'price',
                mercari_woship: 'price',
                fb_marketplace: 'price',
                vinted: 'V Price',
                depop: 'price',
            })[CH_PUSH_SPRICE_CHANNEL] || 'Price';
            const CH_PUSH_SPRICE_CAN_LIVE = ({
                ebay1: 1, ebay2: 1, ebay2op: 1, ebay3: 1,
                shopify_b2c: 1, shopify_b2b: 1,
                reverb: 1, macys: 1, macy: 1, bestbuy: 1, walmart: 1,
                temu: 1, temu2: 1, doba: 1, doba_withoutship: 1,
                tiktok: 1, tiktok2: 1, topdawg: 1, purchasing_power: 1,
                faire: 1, pls: 1, newegg: 1, wayfair: 1, aliexpress: 1, shein: 1,
            })[CH_PUSH_SPRICE_CHANNEL] === 1;
            const CH_PUSH_SPRICE_CAN_PULL = /^(ebay1|ebay2|ebay2op|ebay3|shopify_b2b|shopify_b2c|tiktok|tiktok2)$/.test(CH_PUSH_SPRICE_CHANNEL);
            const CH_PUSH_SPRICE_PULL_DELAY_MS = 0;
            const CH_PUSH_SPRICE_CHUNK = 200;
            const CH_PUSH_SPRICE_PUSH_URL = ({
                ebay1: '/push-ebay-price-tabulator',
                ebay2: '/push-ebay2-price',
                ebay2op: '/push-ebay2-price',
                ebay3: '/push-ebay3-price-tabulator',
                newegg: '/newegg-pricing-push',
            })[CH_PUSH_SPRICE_CHANNEL] || '';
            const CH_PUSH_SPRICE_CLIENT_MAX = 2;
            let chPushSpriceBuf = {};
            let chPushSpriceTimer = null;
            let chPushSpricePollTimer = null;
            let chPushSpriceLastToastKey = '';
            let chPushSpricePulledKey = '';
            let chPushSpriceFlushing = false;
            let chPushSpriceExclusive = false;
            let chPushSpriceExpecting = false;
            let chPushClientQ = [];
            let chPushClientInflight = 0;
            let chPushClientDone = 0;
            let chPushClientOk = 0;
            let chPushClientFail = 0;
            let chPushClientTotal = 0;
            let chPushClientCancelled = false;
            const chPushClientPushed = new Set();
            let chPushClientOkSkus = [];

            function chPushSpriceCsrf() {
                return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            }
            function chPushSpriceRound2(n) {
                return Math.round((Number(n) || 0) * 100) / 100;
            }
            function chPushSpriceNearlyEqual(a, b) {
                return Math.abs(Number(a) - Number(b)) < 0.005;
            }
            function chPushSpriceToast(type, msg) {
                if (typeof showToast === 'function') {
                    try { showToast(type, msg); } catch (e) { showToast(msg, type); }
                } else if (typeof chPromoToast === 'function') {
                    chPromoToast(type, msg);
                } else {
                    console.log(type, msg);
                }
            }
            function chPushSpriceUsesClientPump() {
                return !!CH_PUSH_SPRICE_PUSH_URL;
            }
            function chPushClientBusy() {
                return chPushClientInflight > 0 || chPushClientQ.length > 0;
            }
            function chPushClientSetProgress(active) {
                const total = chPushClientTotal;
                const done = chPushClientDone;
                const pct = total > 0 ? Math.min(100, Math.round((done / total) * 100)) : 0;
                setChannelPushSpriceProgress({
                    active: !!active,
                    done: done,
                    total: total,
                    ok: chPushClientOk,
                    fail: chPushClientFail,
                    pct: pct,
                    title: CH_PUSH_SPRICE_CHANNEL.toUpperCase() + ' listing',
                    msg: active ? (done + ' / ' + total) : undefined,
                });
            }
            function cancelChannelPushSpriceClient() {
                chPushClientQ = [];
                chPushClientCancelled = true;
                chPushClientSetProgress(chPushClientInflight > 0);
                if (chPushClientOkSkus.length) {
                    const toPull = chPushClientOkSkus.slice();
                    chPushClientOkSkus = [];
                    chPushSpricePullAfterPush(toPull);
                }
                if (!chPushClientInflight) {
                    chPushClientDone = 0;
                    chPushClientOk = 0;
                    chPushClientFail = 0;
                    chPushClientTotal = 0;
                    setChannelPushSpriceProgress({
                        active: false,
                        done: 0,
                        total: 0,
                        pct: 0,
                        msg: 'Cancelled',
                    });
                }
            }
            function chPushSpriceCancelQueued() {
                if (chPushSpriceUsesClientPump() && (chPushClientBusy() || chPushClientCancelled)) {
                    if (!confirm('Cancel remaining listing pushes? Already-pushed SKUs stay on the marketplace.')) return;
                    cancelChannelPushSpriceClient();
                    return;
                }
                if (!confirm('Cancel remaining S PRC pushes? Already-queued SKUs that finished stay on the marketplace.')) return;
                $.ajax({
                    url: CH_PUSH_SPRICE_URL + '/cancel',
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': chPushSpriceCsrf(), 'Accept': 'application/json' },
                    data: { _token: chPushSpriceCsrf() },
                }).done(function(resp) {
                    chPushSpriceToast('success', (resp && resp.message) || 'S PRC push cancelled');
                    pollChannelPushSpriceStatus();
                }).fail(function(xhr) {
                    chPushSpriceToast('error', (xhr.responseJSON && xhr.responseJSON.message) || 'Cancel failed');
                });
            }
            function chPushSpriceHasInlineProgress() {
                return !!document.getElementById('ch-promo-reload-push-progress');
            }
            function chPushSpriceBindCancel(id) {
                const el = document.getElementById(id);
                if (!el || el.dataset.bound === '1') return;
                el.dataset.bound = '1';
                el.addEventListener('click', chPushSpriceCancelQueued);
            }
            function chPushSpriceEnsureBox() {
                chPushSpriceBindCancel('ch-promo-reload-push-progress-cancel');
                if (chPushSpriceHasInlineProgress()) return;
                if (document.getElementById('ch-promo-push-sprice-progress')) return;
                const style = document.createElement('style');
                style.textContent = ''
                    + '#ch-promo-push-sprice-progress{display:none;position:fixed;right:16px;bottom:86px;z-index:10860;'
                    + 'min-width:300px;max-width:440px;padding:12px 14px;border:1px solid #bfdbfe;border-radius:8px;'
                    + 'background:#eff6ff;box-shadow:0 10px 28px rgba(15,23,42,.18);font-size:13px}'
                    + '#ch-promo-push-sprice-progress.active{display:block}'
                    + '#ch-promo-push-sprice-progress.done{border-color:#86efac;background:#f0fdf4}'
                    + '#ch-promo-push-sprice-progress .head{display:flex;align-items:center;gap:8px;margin-bottom:4px;font-weight:700;color:#1d4ed8}'
                    + '#ch-promo-push-sprice-progress.done .head{color:#15803d}'
                    + '#ch-promo-push-sprice-progress .meta{display:flex;align-items:center;gap:8px;margin-bottom:6px;font-size:12px;color:#64748b}'
                    + '#ch-promo-push-sprice-progress .bar{height:10px;border-radius:999px;background:#bfdbfe;overflow:hidden}'
                    + '#ch-promo-push-sprice-progress .bar>span{display:block;height:100%;width:0;background:#3b82f6;border-radius:999px;transition:width .25s ease}'
                    + '#ch-promo-push-sprice-progress.done .bar>span{background:#22c55e}'
                    + '#ch-promo-push-sprice-progress.has-fail.done .bar>span{background:linear-gradient(90deg,#22c55e 70%,#f59e0b 100%)}';
                document.head.appendChild(style);
                const box = document.createElement('div');
                box.id = 'ch-promo-push-sprice-progress';
                box.setAttribute('aria-live', 'polite');
                box.innerHTML = ''
                    + '<div class="head"><i class="fas fa-spinner fa-spin"></i>'
                    + '<span id="ch-promo-push-sprice-title">S PRC queue</span>'
                    + '<span id="ch-promo-push-sprice-pct" style="margin-left:auto">0%</span></div>'
                    + '<div class="meta"><span id="ch-promo-push-sprice-msg">Ready</span>'
                    + '<button type="button" id="ch-promo-push-sprice-cancel" class="btn btn-sm btn-outline-danger py-0 px-1"'
                    + ' style="display:none;font-size:11px;line-height:1.2;">Cancel</button></div>'
                    + '<div class="bar"><span id="ch-promo-push-sprice-bar"></span></div>';
                document.body.appendChild(box);
                chPushSpriceBindCancel('ch-promo-push-sprice-cancel');
            }
            function setChannelPushSpriceProgress(opts) {
                opts = opts || {};
                chPushSpriceEnsureBox();
                const total = Number(opts.total) || 0;
                const done = Number(opts.done) || 0;
                const ok = Number(opts.ok) || 0;
                const fail = Number(opts.fail) || 0;
                const active = !!opts.active;
                const pct = (opts.pct != null)
                    ? Math.min(100, Number(opts.pct) || 0)
                    : (total > 0 ? Math.min(100, Math.round((done / total) * 100)) : 0);
                const finished = !active && total > 0 && (done >= total || pct >= 100);
                let msg;
                if (active) {
                    msg = total ? (done + ' / ' + total) : 'Starting…';
                } else if (finished) {
                    msg = (opts.msg && String(opts.msg).trim())
                        ? String(opts.msg)
                        : (done + ' / ' + total + ' · ' + ok + ' ok' + (fail ? (' · ' + fail + ' failed') : ''));
                } else {
                    msg = opts.msg || 'Ready';
                }
                const $inline = $('#ch-promo-reload-push-progress');
                if ($inline.length) {
                    $inline.toggleClass('is-busy', !!active);
                    $inline.toggleClass('is-done', finished || (!active && pct >= 100));
                    $inline.toggleClass('is-fail', fail > 0);
                    $('#ch-promo-reload-push-progress-pct').text(pct + '%');
                    $('#ch-promo-reload-push-progress-bar').css('width', pct + '%');
                    $('#ch-promo-reload-push-progress-msg').text(msg);
                    if (finished) {
                        clearTimeout(setChannelPushSpriceProgress._inlineHideTimer);
                        setChannelPushSpriceProgress._inlineHideTimer = setTimeout(function() {
                            if (!$inline.hasClass('is-done')) return;
                            $inline.removeClass('is-busy is-done is-fail');
                            $('#ch-promo-reload-push-progress-pct').text('0%');
                            $('#ch-promo-reload-push-progress-bar').css('width', '0%');
                            $('#ch-promo-reload-push-progress-msg').text('Ready');
                        }, 10000);
                    }
                    return;
                }
                const $box = $('#ch-promo-push-sprice-progress');
                if (!$box.length) return;
                if (active || finished) $box.addClass('active');
                else $box.removeClass('active');
                $box.toggleClass('done', finished || (!active && pct >= 100));
                $box.toggleClass('has-fail', fail > 0);
                $('#ch-promo-push-sprice-title').text(opts.title || (finished ? (fail && !ok ? 'S PRC failed' : 'S PRC pushed') : 'S PRC queue'));
                $('#ch-promo-push-sprice-pct').text(pct + '%');
                $('#ch-promo-push-sprice-bar').css('width', pct + '%');
                $('#ch-promo-push-sprice-cancel').toggle(!!active);
                $('#ch-promo-push-sprice-msg').text(msg);
                if (finished) {
                    clearTimeout(setChannelPushSpriceProgress._hideTimer);
                    setChannelPushSpriceProgress._hideTimer = setTimeout(function() {
                        if (!$box.hasClass('done')) return;
                        $box.removeClass('active done has-fail');
                    }, 10000);
                }
            }
            function chPushSpriceAutoPushAllowed() {
                if (typeof global.chPromoPageReloadPushAllowed === 'function') {
                    return global.chPromoPageReloadPushAllowed();
                }
                const sw = document.getElementById('ch-promo-reload-push-switch');
                if (sw) return !!sw.checked;
                return true;
            }
            function applyChannelPushSpriceTasks(tasks) {
                if (typeof table === 'undefined' || !table || !Array.isArray(tasks)) return;
                const bySku = {};
                tasks.forEach(function(t) {
                    if (t && t.sku) bySku[String(t.sku).toUpperCase()] = t;
                });
                if (!Object.keys(bySku).length) return;
                function patchRecord(d, t) {
                    if (!d || !t) return false;
                    const st = String(t.status || '');
                    const live = Number(t.ebay_price != null ? t.ebay_price : t.price);
                    const patch = {};
                    let priceChanged = false;
                    if (st === 'ok') {
                        if (d.SPRICE_STATUS !== 'pushed') patch.SPRICE_STATUS = 'pushed';
                        if (live > 0 && !chPushSpriceNearlyEqual(d[CH_PUSH_SPRICE_PRICE_FIELD], live)) {
                            patch[CH_PUSH_SPRICE_PRICE_FIELD] = live;
                            patch['eBay Price'] = live;
                            priceChanged = true;
                        }
                        if ((CH_PUSH_SPRICE_CHANNEL === 'temu' || CH_PUSH_SPRICE_CHANNEL === 'temu2')
                            && live > 0
                            && typeof temuPushBaseFromSprice === 'function') {
                            const base = temuPushBaseFromSprice(live);
                            if (base > 0 && !chPushSpriceNearlyEqual(d.base_price, base)) {
                                patch.base_price = base;
                                patch.temu_price = base <= 26.99 ? +(base + 2.99).toFixed(2) : base;
                                if (typeof temu2FullPriceFromBase === 'function') {
                                    patch.temu_price_display = +temu2FullPriceFromBase(base).toFixed(2);
                                }
                                patch.push_status = 'pushed';
                                priceChanged = true;
                            }
                        }
                    } else if (st === 'failed') {
                        if (d.SPRICE_STATUS !== 'error') patch.SPRICE_STATUS = 'error';
                        const err = String(t.error || t.message || '').toLowerCase();
                        if (err.indexOf('291') !== -1 || err.indexOf('ended listing') !== -1) {
                            if (d.listing_status !== 'ENDED') patch.listing_status = 'ENDED';
                            if (!d.listing_ended) patch.listing_ended = true;
                        }
                    } else if (st === 'pushing' || st === 'pending' || st === 'queued') {
                        if (d.SPRICE_STATUS !== 'queued') patch.SPRICE_STATUS = 'queued';
                    } else {
                        return false;
                    }
                    const keys = Object.keys(patch);
                    if (!keys.length) return null;
                    Object.assign(d, patch);
                    return { kind: priceChanged ? 'price' : 'status', patch: patch };
                }
                let priceChanged = 0;
                let rows = [];
                try { rows = table.getRows() || []; } catch (e) { rows = []; }
                rows.forEach(function walk(row) {
                    const d = row.getData() || {};
                    const sku = String(d['(Child) sku'] || d.SKU || d.sku || '').trim().toUpperCase();
                    const t = sku ? bySku[sku] : null;
                    if (t) {
                        const result = patchRecord(d, t);
                        if (result) {
                            if (result.kind === 'price') priceChanged++;
                            try { row.update(result.patch); } catch (e) { /* ignore */ }
                        }
                    }
                    if (typeof row.getTreeChildren === 'function') {
                        (row.getTreeChildren() || []).forEach(walk);
                    }
                });
                const extra = (typeof window !== 'undefined' && Array.isArray(window.allTableData))
                    ? window.allTableData
                    : [];
                extra.forEach(function(d) {
                    const sku = String((d && (d['(Child) sku'] || d.SKU || d.sku)) || '').trim().toUpperCase();
                    const t = sku ? bySku[sku] : null;
                    if (t) patchRecord(d, t);
                });
                if (priceChanged && typeof updateSummary === 'function') {
                    clearTimeout(applyChannelPushSpriceTasks._sumTimer);
                    applyChannelPushSpriceTasks._sumTimer = setTimeout(function() {
                        try { updateSummary(); } catch (e) { /* ignore */ }
                    }, 400);
                }
            }
            function chPushSpriceOkSkus(tasks) {
                const out = [];
                const seen = {};
                (tasks || []).forEach(function(t) {
                    if (!t || String(t.status) !== 'ok') return;
                    const sku = String(t.sku || '').trim();
                    const key = sku.toUpperCase();
                    if (!sku || seen[key]) return;
                    seen[key] = true;
                    out.push(sku);
                });
                return out;
            }
            function applyChannelPushSpricePullResults(results, expectedBySku) {
                if (!Array.isArray(results) || !results.length) return [];
                const stale = [];
                const tasks = [];
                results.forEach(function(r) {
                    if (!r || !r.success || !(Number(r.price) > 0) || r.skipped) return;
                    const live = Number(r.price);
                    const key = String(r.sku || '').toUpperCase();
                    const expected = expectedBySku && key ? Number(expectedBySku[key]) : 0;
                    if (expected > 0 && Math.abs(live - expected) > 0.05) {
                        stale.push(r.sku);
                        return;
                    }
                    tasks.push({ sku: r.sku, status: 'ok', ebay_price: live, price: live });
                });
                if (tasks.length) {
                    applyChannelPushSpriceTasks(tasks);
                    if (CH_PUSH_SPRICE_CHANNEL === 'shopify_b2c'
                        && typeof global.shopifyB2cApplyLivePriceToRow === 'function') {
                        tasks.forEach(function(t) {
                            if (!t || String(t.status) !== 'ok' || !(Number(t.price) > 0)) return;
                            const row = chPushSpriceFindRowBySku(t.sku);
                            if (row) global.shopifyB2cApplyLivePriceToRow(row, t.price, { SPRICE_STATUS: 'pushed' });
                        });
                    }
                }
                return stale;
            }
            function chPushSpriceExpectedBySku(skus) {
                const out = {};
                (skus || []).forEach(function(sku) {
                    const row = typeof chPushSpriceFindRowBySku === 'function'
                        ? chPushSpriceFindRowBySku(sku)
                        : null;
                    const d = row && typeof row.getData === 'function' ? (row.getData() || {}) : {};
                    const expected = Number(d.SPRICE || d.PUSH_PRC_VALUE || d.sprice || 0);
                    if (expected > 0) out[String(sku).toUpperCase()] = expected;
                });
                return out;
            }
            function chPushSpricePullAfterPush(skus) {
                if (!skus || !skus.length) return;
                if (!CH_PUSH_SPRICE_CAN_PULL) return;
                clearTimeout(chPushSpricePullAfterPush._t);
                const n = skus.length;
                const expectedBySku = chPushSpriceExpectedBySku(skus);
                const retryMs = [0, 2000, 4000];
                function runPull(attempt, pending) {
                    if (!pending || !pending.length) return;
                    $.ajax({
                        url: CH_PUSH_SPRICE_URL + '/pull',
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': chPushSpriceCsrf(), 'Accept': 'application/json' },
                        data: { _token: chPushSpriceCsrf(), skus: pending },
                        timeout: 300000,
                    }).done(function(resp) {
                        const results = resp && Array.isArray(resp.results) ? resp.results : [];
                        const stale = applyChannelPushSpricePullResults(results, expectedBySku);
                        const pulled = Number(resp && resp.ok_count) || 0;
                        const skipped = Number(resp && resp.skip_count) || 0;
                        if (stale.length && attempt + 1 < retryMs.length) {
                            chPushSpricePullAfterPush._t = setTimeout(function() {
                                runPull(attempt + 1, stale);
                            }, retryMs[attempt + 1]);
                            return;
                        }
                        if (pulled > 0 && !stale.length) {
                            chPushSpriceToast('success', 'Pulled live Price for ' + pulled + ' SKU(s)');
                        } else if (stale.length) {
                            chPushSpriceToast('success', 'Pushed ' + n + ' SKU(s) — live Price still catching up');
                        } else if (!skipped) {
                            chPushSpriceToast('error', (resp && resp.message) || 'Live Price pull failed');
                        }
                    }).fail(function(xhr) {
                        if (attempt + 1 < retryMs.length) {
                            chPushSpricePullAfterPush._t = setTimeout(function() {
                                runPull(attempt + 1, pending);
                            }, retryMs[attempt + 1]);
                            return;
                        }
                        chPushSpriceToast('error', (xhr.responseJSON && xhr.responseJSON.message) || 'Live Price pull failed');
                    });
                }
                chPushSpriceToast('success', 'Pulling live Price for ' + n + ' SKU(s)…');
                chPushSpricePullAfterPush._t = setTimeout(function() {
                    runPull(0, skus.slice());
                }, CH_PUSH_SPRICE_PULL_DELAY_MS);
            }
            function stopChannelPushSpricePoll() {
                if (chPushSpricePollTimer) {
                    clearInterval(chPushSpricePollTimer);
                    chPushSpricePollTimer = null;
                }
            }
            function pollChannelPushSpriceStatus() {
                $.ajax({
                    url: CH_PUSH_SPRICE_URL + '/status',
                    method: 'GET',
                    headers: { 'Accept': 'application/json' },
                    timeout: 20000,
                }).done(function(resp) {
                    if (!resp) return;
                    if (chPushSpriceExpecting && !resp.active && !(Number(resp.total) > 0)) {
                        return;
                    }
                    const active = !!resp.active;
                    applyChannelPushSpriceTasks(resp.tasks || []);
                    setChannelPushSpriceProgress({
                        active: active,
                        done: Number(resp.done_count) || 0,
                        total: Number(resp.total) || 0,
                        ok: Number(resp.ok_count) || 0,
                        fail: Number(resp.fail_count) || 0,
                        pct: Number(resp.pct) || 0,
                        title: active ? 'S PRC queue' : ((Number(resp.fail_count) || 0) && !(Number(resp.ok_count) || 0) ? 'S PRC failed' : 'S PRC pushed'),
                        msg: active ? '' : (resp.message || ''),
                    });
                    if (!active) {
                        stopChannelPushSpricePoll();
                        const jobStatus = resp.job && resp.job.status ? String(resp.job.status) : '';
                        const toastKey = jobStatus + '|' + resp.ok_count + '|' + resp.fail_count + '|' + resp.total;
                        if (toastKey !== chPushSpriceLastToastKey && (Number(resp.total) || 0) > 0) {
                            chPushSpriceLastToastKey = toastKey;
                            chPushSpriceToast(
                                (Number(resp.fail_count) || 0) && !(Number(resp.ok_count) || 0) ? 'error' : 'success',
                                resp.message || ('S PRC: ' + (resp.ok_count || 0) + ' ok')
                            );
                        }
                        if (jobStatus === 'completed' && (Number(resp.ok_count) || 0) > 0 && toastKey !== chPushSpricePulledKey) {
                            chPushSpricePulledKey = toastKey;
                            chPushSpricePullAfterPush(chPushSpriceOkSkus(resp.tasks || []));
                        }
                    }
                });
            }
            function startChannelPushSpricePoll() {
                stopChannelPushSpricePoll();
                chPushSpricePollTimer = setInterval(pollChannelPushSpriceStatus, 1500);
                pollChannelPushSpriceStatus();
            }
            function postChannelPushSpriceItems(items, opts) {
                opts = opts || {};
                if (!items || !items.length) return $.Deferred().resolve(null).promise();
                setChannelPushSpriceProgress({
                    active: true,
                    done: 0,
                    total: items.length,
                    ok: 0,
                    fail: 0,
                    pct: 0,
                    title: 'S PRC queue',
                });
                const payload = { _token: chPushSpriceCsrf(), items: items };
                if (opts.exclusive) {
                    payload.exclusive = 1;
                    payload.source = 'after_save';
                }
                return $.ajax({
                    url: CH_PUSH_SPRICE_URL,
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': chPushSpriceCsrf(), 'Accept': 'application/json' },
                    data: payload,
                    timeout: 60000,
                }).done(function(resp) {
                    chPushSpriceExpecting = false;
                    startChannelPushSpricePoll();
                    if (resp) {
                        setChannelPushSpriceProgress({
                            active: !!resp.active,
                            done: Number(resp.done_count) || 0,
                            total: Number(resp.total) || items.length,
                            ok: Number(resp.ok_count) || 0,
                            fail: Number(resp.fail_count) || 0,
                            pct: Number(resp.pct) || 0,
                            title: 'S PRC queue',
                        });
                    }
                }).fail(function(xhr) {
                    chPushSpriceExpecting = false;
                    chPushSpriceToast('error', (xhr.responseJSON && xhr.responseJSON.message) || 'Could not queue S PRC');
                });
            }
            function flushChannelPushSprice() {
                if (chPushSpriceFlushing) {
                    chPushSpriceTimer = setTimeout(flushChannelPushSprice, 250);
                    return;
                }
                const keys = Object.keys(chPushSpriceBuf);
                if (!keys.length) return;
                const items = keys.map(function(k) { return chPushSpriceBuf[k]; });
                const exclusive = chPushSpriceExclusive;
                chPushSpriceBuf = {};
                chPushSpriceExclusive = false;
                chPushSpriceFlushing = true;
                let i = 0;
                function nextChunk() {
                    if (i >= items.length) {
                        chPushSpriceFlushing = false;
                        return;
                    }
                    const chunk = items.slice(i, i + CH_PUSH_SPRICE_CHUNK);
                    i += chunk.length;
                    postChannelPushSpriceItems(chunk, { exclusive: exclusive }).always(nextChunk);
                }
                nextChunk();
            }
            function enqueueChannelPushSprice(items, opts) {
                opts = opts || {};
                if (!CH_PUSH_SPRICE_LIVE) {
                    if (!opts.silent) {
                        chPushSpriceToast('error', 'Live S PRC push is disabled on this environment');
                    }
                    return;
                }
                if (!CH_PUSH_SPRICE_CAN_LIVE) {
                    if (!opts.silent) {
                        chPushSpriceToast('error', 'Live S PRC push is not available for this channel');
                    }
                    return;
                }
                if (opts.exclusive) chPushSpriceExclusive = true;
                if (!items || !items.length) return;
                items.forEach(function(item) {
                    if (!item) return;
                    const sku = String(item.sku || '').trim();
                    const price = chPushSpriceRound2(item.price);
                    if (!sku || !(price > 0)) return;
                    chPushSpriceBuf[sku.toUpperCase()] = { sku: sku, price: price };
                });
                const n = Object.keys(chPushSpriceBuf).length;
                if (!n) return;
                chPushSpriceExpecting = true;
                if (!opts.silent) {
                    setChannelPushSpriceProgress({
                        active: true,
                        done: 0,
                        total: n,
                        ok: 0,
                        fail: 0,
                        pct: 0,
                        title: 'S PRC queue',
                    });
                }
                clearTimeout(chPushSpriceTimer);
                chPushSpriceTimer = setTimeout(flushChannelPushSprice, opts.immediate ? 0 : 180);
            }
            function enqueueChannelPushSpriceAfterSave(sku, price, row, opts) {
                opts = opts || {};
                const isTemu = CH_PUSH_SPRICE_CHANNEL === 'temu' || CH_PUSH_SPRICE_CHANNEL === 'temu2';
                const force = opts.force === true || (isTemu && opts.force !== false);
                const d = (row && typeof row.getData === 'function') ? (row.getData() || {}) : (row || {});
                let p = chPushSpriceRound2(price);
                if (!force) {
                    if (window.SpriceLmpCap && d) p = SpriceLmpCap.prepare(d, p);
                    if (typeof chPromoFloorShopifySpriceToAmz === 'function') {
                        p = chPromoFloorShopifySpriceToAmz(d, p);
                    }
                }
                if (!sku || !(p > 0)) return false;
                if (!CH_PUSH_SPRICE_CAN_LIVE) return false;
                if (!chPushSpriceAutoPushAllowed()) {
                    try {
                        if (row && typeof row.update === 'function') {
                            const status = String(d.SPRICE_STATUS || '');
                            if (status === 'queued' || status === 'processing') {
                                row.update({ SPRICE_STATUS: 'saved' });
                            }
                        }
                    } catch (e) { /* ignore */ }
                    return false;
                }
                if (!force) {
                    const live = chPushSpriceRound2(d[CH_PUSH_SPRICE_PRICE_FIELD]);
                    if (live > 0 && chPushSpriceNearlyEqual(p, live)) return false;
                    if (typeof chPromoIsEndedListing === 'function' && chPromoIsEndedListing(d)) return false;
                }
                try {
                    if (row && typeof row.update === 'function') row.update({ SPRICE_STATUS: 'queued' });
                } catch (e) { /* ignore */ }
                if (chPushSpriceUsesClientPump()) {
                    enqueueChannelPushSpriceClient([{ sku: sku, price: p, row: row }]);
                    return true;
                }
                enqueueChannelPushSprice([{ sku: sku, price: p }], {
                    exclusive: true,
                    immediate: opts.immediate !== false,
                });
                return true;
            }
            function chPushSpriceFindRowBySku(sku) {
                const want = String(sku || '').trim().toUpperCase();
                if (!want) return null;
                let found = null;
                try {
                    const tbl = (typeof table !== 'undefined' && table) ? table : null;
                    if (tbl) {
                        chPushSpriceWalkRows(tbl, function(row, d) {
                            if (found || !d) return;
                            const s = String(d['(Child) sku'] || d.SKU || d.sku || '').trim().toUpperCase();
                            if (s === want) found = row;
                        });
                    }
                } catch (e) { /* ignore */ }
                return found;
            }
            function chPushClientPatchDatasets(sku, patch) {
                const want = String(sku || '').trim().toUpperCase();
                if (!want || !patch) return;
                const walk = function(arr) {
                    if (!Array.isArray(arr)) return;
                    arr.forEach(function(row) {
                        if (!row) return;
                        const s = String(row['(Child) sku'] || row.SKU || row.sku || '').trim().toUpperCase();
                        if (s === want) Object.assign(row, patch);
                        if (Array.isArray(row._children)) walk(row._children);
                    });
                };
                try {
                    if (typeof allTableData !== 'undefined') walk(allTableData);
                } catch (e) { /* ignore */ }
                if (global.allTableData) walk(global.allTableData);
            }
            function chPushClientApplyResult(item, ok, livePrice, errMsg) {
                const live = chPushSpriceRound2(livePrice != null ? livePrice : item.price);
                const patch = {};
                if (ok) {
                    patch.SPRICE_STATUS = 'pushed';
                    patch.PUSH_PRC_STATUS = 'pushed';
                    patch.push_prc = 'pushed';
                    if (live > 0) {
                        patch[CH_PUSH_SPRICE_PRICE_FIELD] = live;
                        patch['eBay Price'] = live;
                        patch.PUSH_PRC_VALUE = live;
                    }
                } else {
                    patch.SPRICE_STATUS = 'error';
                    patch.PUSH_PRC_STATUS = 'error';
                    patch.push_prc = 'error';
                    const err = String(errMsg || '').toLowerCase();
                    if (err.indexOf('291') !== -1 || err.indexOf('ended listing') !== -1) {
                        patch.listing_status = 'ENDED';
                        patch.listing_ended = true;
                    }
                }
                const row = item.row;
                const d = (row && typeof row.getData === 'function') ? (row.getData() || {}) : (item.data || {});
                if (row && typeof row.update === 'function') {
                    try { row.update(patch); } catch (e) { Object.assign(d, patch); }
                    try { row.reformat(); } catch (e) { /* ignore */ }
                } else if (d) {
                    Object.assign(d, patch);
                }
                chPushClientPatchDatasets(item.sku, patch);
            }
            function enqueueChannelPushSpriceClient(items, opts) {
                opts = opts || {};
                if (!CH_PUSH_SPRICE_LIVE) {
                    chPushSpriceToast('error', 'Live S PRC push is disabled on this environment');
                    return 0;
                }
                if (!chPushSpriceUsesClientPump()) return 0;
                let n = 0;
                (items || []).forEach(function(item) {
                    if (!item) return;
                    const sku = String(item.sku || '').trim();
                    const price = chPushSpriceRound2(item.price);
                    if (!sku || !(price > 0)) return;
                    const key = sku.toUpperCase();
                    const dedupe = key + '|' + price.toFixed(2);
                    if (opts.force) chPushClientPushed.delete(dedupe);
                    if (chPushClientPushed.has(dedupe)) return;
                    chPushClientQ = chPushClientQ.filter(function(it) {
                        return String(it.sku).toUpperCase() !== key;
                    });
                    const row = (item.row && typeof item.row.getData === 'function')
                        ? item.row
                        : chPushSpriceFindRowBySku(sku);
                    chPushClientQ.push({ sku: sku, price: price, row: row });
                    n++;
                });
                if (!n && !chPushClientBusy()) return 0;
                chPushClientCancelled = false;
                chPushClientTotal = chPushClientDone + chPushClientQ.length + chPushClientInflight;
                chPushClientSetProgress(true);
                chPushClientPump();
                return n;
            }
            function chPushClientPump() {
                if (chPushClientCancelled) return;
                while (chPushClientInflight < CH_PUSH_SPRICE_CLIENT_MAX && chPushClientQ.length) {
                    const item = chPushClientQ.shift();
                    chPushClientInflight++;
                    if (item.row && typeof item.row.update === 'function') {
                        try { item.row.update({ SPRICE_STATUS: 'queued' }); } catch (e) { /* ignore */ }
                    }
                    chPushClientSetProgress(true);
                    $.ajax({
                        url: CH_PUSH_SPRICE_PUSH_URL,
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': chPushSpriceCsrf(), 'Accept': 'application/json' },
                        data: {
                            _token: chPushSpriceCsrf(),
                            sku: item.sku,
                            price: item.price,
                        },
                    }).done(function(resp) {
                        if (resp && resp.success) {
                            chPushClientOk++;
                            const live = resp.ebay_price != null ? resp.ebay_price
                                : (resp.price != null ? resp.price : item.price);
                            chPushClientPushed.add(String(item.sku).toUpperCase() + '|' + Number(item.price).toFixed(2));
                            if (chPushClientOkSkus.indexOf(item.sku) === -1) {
                                chPushClientOkSkus.push(item.sku);
                            }
                            chPushClientApplyResult(item, true, live, null);
                        } else {
                            chPushClientFail++;
                            chPushClientApplyResult(item, false, null, (resp && resp.message) || 'Push failed');
                        }
                    }).fail(function(xhr) {
                        chPushClientFail++;
                        const msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Push failed';
                        chPushClientApplyResult(item, false, null, msg);
                    }).always(function() {
                        chPushClientDone++;
                        chPushClientInflight--;
                        const busy = chPushClientBusy();
                        chPushClientSetProgress(busy);
                        if (!busy) {
                            if (chPushClientOk > 0) {
                                chPushSpriceToast(
                                    'success',
                                    'S PRC: ' + chPushClientOk + ' ok'
                                    + (chPushClientFail ? (' · ' + chPushClientFail + ' failed') : '')
                                );
                                if (chPushClientOkSkus.length) {
                                    const toPull = chPushClientOkSkus.slice();
                                    chPushClientOkSkus = [];
                                    chPushSpricePullAfterPush(toPull);
                                }
                            } else if (chPushClientFail > 0) {
                                chPushSpriceToast('error', 'S PRC: ' + chPushClientFail + ' failed');
                            }
                            setTimeout(function() {
                                if (chPushClientBusy()) return;
                                chPushClientDone = 0;
                                chPushClientOk = 0;
                                chPushClientFail = 0;
                                chPushClientTotal = 0;
                            }, 12000);
                        } else {
                            setTimeout(chPushClientPump, 200);
                        }
                    });
                }
            }
            function chPushSpriceIsChild(d) {
                if (!d || d.is_parent_summary || d.is_parent_row || d.is_parent) return false;
                const sku = String(d['(Child) sku'] || d.SKU || d.sku || '').trim();
                return !!sku && sku.toUpperCase().indexOf('PARENT') === -1;
            }
            function chPushSpriceWalkRows(tbl, fn) {
                if (!tbl) return;
                const seen = new Set();
                function walk(row) {
                    if (!row || seen.has(row) || typeof row.getData !== 'function') return;
                    seen.add(row);
                    fn(row, row.getData() || {});
                    if (typeof row.getTreeChildren === 'function') {
                        (row.getTreeChildren() || []).forEach(walk);
                    }
                }
                let rows = [];
                try { rows = tbl.getRows() || []; } catch (e) { rows = []; }
                if (!rows.length) {
                    try { rows = tbl.getRows('active') || []; } catch (e) { rows = []; }
                }
                rows.forEach(walk);
            }
            function chPushSpriceDatasetRows() {
                let raw = [];
                try {
                    if (Array.isArray(global.allTableData) && global.allTableData.length) {
                        raw = global.allTableData;
                    }
                } catch (e) { /* ignore */ }
                if (!raw.length) {
                    try {
                        if (typeof allTableData !== 'undefined' && Array.isArray(allTableData) && allTableData.length) {
                            raw = allTableData;
                        }
                    } catch (e) { /* TDZ before let allTableData */ }
                }
                if (!raw.length) return [];
                const flat = [];
                const seen = new Set();
                function walk(d) {
                    if (!d || typeof d !== 'object') return;
                    const sku = String(d['(Child) sku'] || d.SKU || d.sku || '').trim().toUpperCase();
                    if (sku) {
                        if (seen.has(sku)) return;
                        seen.add(sku);
                    }
                    flat.push(d);
                    const kids = d._children;
                    if (Array.isArray(kids) && kids.length) {
                        kids.forEach(walk);
                    }
                }
                raw.forEach(walk);
                return flat;
            }
            function chPushSpriceLiveFromRow(d) {
                if (!d) return 0;
                const raw = d[CH_PUSH_SPRICE_PRICE_FIELD] != null && d[CH_PUSH_SPRICE_PRICE_FIELD] !== ''
                    ? d[CH_PUSH_SPRICE_PRICE_FIELD]
                    : (d.ebay_price != null && d.ebay_price !== ''
                        ? d.ebay_price
                        : (d.Price != null ? d.Price : 0));
                return chPushSpriceRound2(raw);
            }
            function chPushSpriceFillFromRow(d) {
                let fill = 0;
                if (typeof chPromoLiveSprice === 'function') {
                    fill = chPushSpriceRound2(chPromoLiveSprice(d));
                } else if (typeof chPromoSpriceFromStdTPromo === 'function') {
                    fill = chPushSpriceRound2(chPromoSpriceFromStdTPromo(d));
                }
                if (fill > 0) return fill;
                return chPushSpriceRound2(d && (d.SPRICE != null ? d.SPRICE : d.sprice));
            }
            function scanAndQueueChannelPushSprice(tbl, opts) {
                opts = opts || {};
                // Catalog catch-up is opt-in ({ catalog: true }) — same as Temu's scanAndQueueTemuListingPush.
                if (!opts.catalog) return;
                if (opts.once !== false && opts.silent && window._chPushSpricePageChecked) return;
                if (opts.once !== false && opts.silent) window._chPushSpricePageChecked = true;
                if (!chPushSpriceAutoPushAllowed()) return;
                if (!CH_PUSH_SPRICE_LIVE) {
                    if (!opts.silent) {
                        chPushSpriceToast('error', 'Live S PRC push is disabled on this environment');
                    }
                    return;
                }
                if (!tbl) {
                    try {
                        if (typeof table !== 'undefined' && table) tbl = table;
                    } catch (e) { /* TDZ */ }
                }
                const extra = chPushSpriceDatasetRows();
                if (!tbl && !extra.length) return;
                const persistMissing = opts.persistMissing !== false;
                const jobs = [];
                const saves = [];
                const seen = new Set();
                function consider(row, d) {
                    if (!chPushSpriceIsChild(d)) return;
                    if (typeof chPromoIsEndedListing === 'function' && chPromoIsEndedListing(d)) return;
                    const sku = String(d['(Child) sku'] || d.SKU || d.sku || '').trim();
                    const key = sku.toUpperCase();
                    if (!sku || seen.has(key)) return;
                    seen.add(key);
                    const fill = chPushSpriceFillFromRow(d);
                    if (!(fill > 0)) return;
                    const live = chPushSpriceLiveFromRow(d);
                    if (!(live > 0) || chPushSpriceNearlyEqual(fill, live)) return;
                    const current = chPushSpriceRound2(d.SPRICE != null ? d.SPRICE : d.sprice);
                    if (persistMissing && CH_PUSH_SPRICE_SAVE && (!(current > 0) || !chPushSpriceNearlyEqual(current, fill))) {
                        if (row && typeof row.update === 'function') {
                            try { row.update({ SPRICE: fill, sprice: fill, SPRICE_STATUS: 'queued' }); } catch (e) { /* ignore */ }
                        } else if (d) {
                            d.SPRICE = fill;
                            d.sprice = fill;
                            d.SPRICE_STATUS = 'queued';
                        }
                        saves.push({ sku: sku, price: fill });
                    }
                    jobs.push({ sku: sku, price: fill, row: row });
                }
                if (tbl) chPushSpriceWalkRows(tbl, consider);
                extra.forEach(function(d) { if (d) consider(null, d); });
                if (!jobs.length) {
                    if (!opts.silent) {
                        setChannelPushSpriceProgress({
                            active: false,
                            done: 0,
                            total: 0,
                            pct: 0,
                            msg: 'No S PRC to push',
                        });
                    }
                    return;
                }
                if (chPushSpriceUsesClientPump()) {
                    enqueueChannelPushSpriceClient(jobs);
                } else {
                    enqueueChannelPushSprice(jobs, { silent: !!opts.silent });
                }
                if (!saves.length || !CH_PUSH_SPRICE_SAVE) return;
                let idx = 0;
                let inflight = 0;
                const max = 8;
                function pump() {
                    while (inflight < max && idx < saves.length) {
                        const job = saves[idx++];
                        inflight++;
                        $.ajax({
                            url: CH_PUSH_SPRICE_SAVE,
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': chPushSpriceCsrf(), 'Accept': 'application/json' },
                            data: { sku: job.sku, sprice: job.price, skip_push: 1, _token: chPushSpriceCsrf() },
                        }).always(function() {
                            inflight--;
                            if (idx < saves.length || inflight > 0) pump();
                        });
                    }
                }
                pump();
            }

            global.enqueueChannelPushSprice = enqueueChannelPushSprice;
            global.enqueueChannelPushSpriceAfterSave = enqueueChannelPushSpriceAfterSave;
            global.enqueueChannelPushSpriceClient = enqueueChannelPushSpriceClient;
            global.chPushSpriceAutoPushAllowed = chPushSpriceAutoPushAllowed;
            global.scanAndQueueChannelPushSprice = scanAndQueueChannelPushSprice;
            global.startChannelPushSpricePoll = startChannelPushSpricePoll;
            global.setChannelPushSpriceProgress = setChannelPushSpriceProgress;
            global._chPushSpriceChannel = CH_PUSH_SPRICE_CHANNEL;

            if (CH_PUSH_SPRICE_LIVE) {
                $.ajax({
                    url: CH_PUSH_SPRICE_URL + '/status',
                    method: 'GET',
                    headers: { 'Accept': 'application/json' },
                    timeout: 15000,
                }).done(function(resp) {
                    if (resp && resp.active) startChannelPushSpricePoll();
                });
            }
        })(window);
