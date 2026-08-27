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
                doba_withoutship: 'doba Price',
                tiktok: 'Price',
                tiktok2: 'Price',
                topdawg: 'Price',
                purchasing_power: 'Price',
                aliexpress: 'Price',
                shein: 'Price',
                newegg: 'Price',
                faire: 'Price',
                pls: 'Price',
            })[CH_PUSH_SPRICE_CHANNEL] || 'Price';
            const CH_PUSH_SPRICE_CHUNK = 200;
            let chPushSpriceBuf = {};
            let chPushSpriceTimer = null;
            let chPushSpricePollTimer = null;
            let chPushSpriceLastToastKey = '';
            let chPushSpriceFlushing = false;
            let chPushSpriceExclusive = false;

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
            function chPushSpriceCancelQueued() {
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
                        const toastKey = String(resp.job && resp.job.status) + '|' + resp.ok_count + '|' + resp.fail_count + '|' + resp.total;
                        if (toastKey !== chPushSpriceLastToastKey && (Number(resp.total) || 0) > 0) {
                            chPushSpriceLastToastKey = toastKey;
                            chPushSpriceToast(
                                (Number(resp.fail_count) || 0) && !(Number(resp.ok_count) || 0) ? 'error' : 'success',
                                resp.message || ('S PRC: ' + (resp.ok_count || 0) + ' ok')
                            );
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
            function enqueueChannelPushSpriceAfterSave(sku, price, row) {
                const d = (row && typeof row.getData === 'function') ? (row.getData() || {}) : (row || {});
                let p = chPushSpriceRound2(price);
                if (window.SpriceLmpCap && d) p = SpriceLmpCap.prepare(d, p);
                if (typeof chPromoFloorShopifySpriceToAmz === 'function') {
                    p = chPromoFloorShopifySpriceToAmz(d, p);
                }
                if (!sku || !(p > 0)) return false;
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
                const live = chPushSpriceRound2(d[CH_PUSH_SPRICE_PRICE_FIELD]);
                if (live > 0 && chPushSpriceNearlyEqual(p, live)) return false;
                if (typeof chPromoIsEndedListing === 'function' && chPromoIsEndedListing(d)) return false;
                try {
                    if (row && typeof row.update === 'function') row.update({ SPRICE_STATUS: 'queued' });
                } catch (e) { /* ignore */ }
                enqueueChannelPushSprice([{ sku: sku, price: p }], { exclusive: true });
                return true;
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
            function scanAndQueueChannelPushSprice(tbl, opts) {
                opts = opts || {};
                if (opts.once !== false && opts.silent && window._chPushSpricePageChecked) return;
                if (opts.once !== false && opts.silent) window._chPushSpricePageChecked = true;
                tbl = tbl || (typeof table !== 'undefined' ? table : null);
                if (!tbl) return;
                const persistMissing = opts.persistMissing !== false;
                const jobs = [];
                const saves = [];
                chPushSpriceWalkRows(tbl, function(row, d) {
                    if (!chPushSpriceIsChild(d)) return;
                    if (typeof chPromoIsEndedListing === 'function' && chPromoIsEndedListing(d)) return;
                    const sku = String(d['(Child) sku'] || d.SKU || d.sku || '').trim();
                    if (!sku) return;
                    let fill = 0;
                    if (typeof chPromoLiveSprice === 'function') {
                        fill = chPushSpriceRound2(chPromoLiveSprice(d));
                    } else if (typeof chPromoSpriceFromStdTPromo === 'function') {
                        fill = chPushSpriceRound2(chPromoSpriceFromStdTPromo(d));
                    }
                    if (!(fill > 0)) return;
                    const live = chPushSpriceRound2(d[CH_PUSH_SPRICE_PRICE_FIELD]);
                    if (!(live > 0) || chPushSpriceNearlyEqual(fill, live)) return;
                    const current = chPushSpriceRound2(d.SPRICE);
                    if (persistMissing && CH_PUSH_SPRICE_SAVE && (!(current > 0) || !chPushSpriceNearlyEqual(current, fill))) {
                        try { row.update({ SPRICE: fill, SPRICE_STATUS: 'queued' }); } catch (e) { /* ignore */ }
                        saves.push({ sku: sku, price: fill });
                    }
                    jobs.push({ sku: sku, price: fill });
                });
                if (!jobs.length) return;
                const persistThenQueue = function() {
                    enqueueChannelPushSprice(jobs, { silent: !!opts.silent });
                };
                if (!saves.length) {
                    persistThenQueue();
                    return;
                }
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
                            if (idx >= saves.length && inflight === 0) persistThenQueue();
                            else pump();
                        });
                    }
                }
                pump();
            }

            global.enqueueChannelPushSprice = enqueueChannelPushSprice;
            global.enqueueChannelPushSpriceAfterSave = enqueueChannelPushSpriceAfterSave;
            global.chPushSpriceAutoPushAllowed = chPushSpriceAutoPushAllowed;
            global.scanAndQueueChannelPushSprice = scanAndQueueChannelPushSprice;
            global.startChannelPushSpricePoll = startChannelPushSpricePoll;
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
