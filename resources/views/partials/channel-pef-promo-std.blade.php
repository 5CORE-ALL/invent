{{-- Std / Push Std Prc only — no PRMT / CPN formulas or push. Used by eBay 2 and eBay 3. --}}
@php
    $channelPromoPart = $channelPromoPart ?? 'all';
    $channelPromoChannel = $channelPromoChannel ?? 'ebay2';
    $channelPromoStdCfg = [
        'ebay2' => [
            'label' => 'eBay2',
            'saveSpriceUrl' => '/save-ebay2-sprice',
            'pushPriceUrl' => '/push-ebay2-price',
        ],
        'ebay3' => [
            'label' => 'eBay3',
            'saveSpriceUrl' => '/ebay3/save-sprice',
            'pushPriceUrl' => '/push-ebay3-price-tabulator',
        ],
    ];
    $channelPromoStdActive = $channelPromoStdCfg[$channelPromoChannel] ?? $channelPromoStdCfg['ebay2'];
@endphp

@if($channelPromoPart === 'css' || $channelPromoPart === 'all')
        .tabulator-row .tabulator-cell[tabulator-field="push_std_prc"] {
            padding: 2px 4px !important;
        }
        @keyframes ch-promo-spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .ebay-push-std-prc-btn .fa-spinner {
            display: inline-block !important;
            animation: ch-promo-spin 0.75s linear infinite !important;
        }
        #ch-promo-push-prc-progress {
            display: none;
            width: 100%;
            min-width: 220px;
            max-width: 420px;
            margin: 4px 0 0;
            padding: 6px 10px;
            border: 1px solid #ffe08a;
            border-radius: 8px;
            background: #fffbeb;
        }
        #ch-promo-push-prc-progress.active {
            display: block;
            position: fixed;
            right: 16px;
            bottom: 16px;
            z-index: 10850;
            min-width: 300px;
            max-width: 440px;
            margin: 0;
            padding: 12px 14px;
            box-shadow: 0 10px 28px rgba(15, 23, 42, 0.18);
        }
        #ch-promo-push-prc-progress.done {
            border-color: #86efac;
            background: #f0fdf4;
        }
        #ch-promo-push-prc-progress .ch-promo-push-prc-progress-head {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 4px;
            font-size: 13px;
            font-weight: 700;
            color: #b45309;
        }
        #ch-promo-push-prc-progress.done .ch-promo-push-prc-progress-head {
            color: #15803d;
        }
        #ch-promo-push-prc-progress-spin { display: none; color: #f59e0b; }
        #ch-promo-push-prc-progress.active:not(.done) #ch-promo-push-prc-progress-spin {
            display: inline-block;
        }
        #ch-promo-push-prc-progress .ch-promo-push-prc-progress-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 6px;
            font-size: 12px;
            line-height: 1.2;
        }
        #ch-promo-push-prc-progress-pct {
            font-weight: 700;
            font-variant-numeric: tabular-nums;
            color: #b45309;
            margin-left: auto;
            min-width: 2.5em;
            text-align: right;
        }
        #ch-promo-push-prc-progress.done #ch-promo-push-prc-progress-pct { color: #15803d; }
        #ch-promo-push-prc-progress-msg {
            color: #64748b;
            flex: 1;
            text-align: left;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        #ch-promo-push-prc-progress .ch-promo-push-prc-bar {
            height: 10px;
            border-radius: 999px;
            background: #fde68a;
            overflow: hidden;
        }
        #ch-promo-push-prc-progress.done .ch-promo-push-prc-bar { background: #bbf7d0; }
        #ch-promo-push-prc-progress .ch-promo-push-prc-bar > span {
            display: block;
            height: 100%;
            width: 0%;
            background: #f59e0b;
            transition: width 0.25s ease, background 0.25s ease;
            border-radius: 999px;
        }
        #ch-promo-push-prc-progress.done .ch-promo-push-prc-bar > span {
            background: #22c55e;
        }
        #ch-promo-push-prc-progress.has-fail.done .ch-promo-push-prc-bar > span {
            background: linear-gradient(90deg, #22c55e 70%, #f59e0b 100%);
        }
@endif

@if($channelPromoPart === 'script' || $channelPromoPart === 'all')
        const CHANNEL_PROMO_CHANNEL = @json($channelPromoChannel ?? 'ebay2');
        const chPromoCfg = {
            label: @json($channelPromoStdActive['label']),
            saveSpriceUrl: @json($channelPromoStdActive['saveSpriceUrl']),
            pushPriceUrl: @json($channelPromoStdActive['pushPriceUrl']),
            skuField: '(Child) sku',
        };
        const CH_PROMO_SAVE_URL = '/channel-promo-pricing/save';

        function chPromoCsrf() {
            return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        }
        function chPromoSku(d) {
            const f = chPromoCfg.skuField || '(Child) sku';
            return String((d && (d[f] || d['(Child) sku'] || d.sku)) || '').trim();
        }
        function chPromoSkuKey(sku) {
            return String(sku == null ? '' : sku).trim().toUpperCase();
        }
        function chPromoIsChildRow(d) {
            return !!(d && !d.is_parent_summary && chPromoSku(d) && String(chPromoSku(d)).indexOf('PARENT') === -1);
        }
        function chPromoEscAttr(s) {
            return String(s == null ? '' : s)
                .replace(/&/g, '&amp;').replace(/"/g, '&quot;')
                .replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }
        function chPromoPaintPushSpinner(btn, title) {
            if (!btn) return;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin" style="font-size:14px;"></i>';
            btn.style.color = '#ffc107';
            if (title) btn.title = title;
        }
        function chPromoRefreshPushCell(row, field, btnSelector, statusKey, title) {
            if (!row) return;
            try {
                const cell = row.getCell && row.getCell(field);
                if (cell && typeof cell.reformat === 'function') cell.reformat();
            } catch (e) { /* ignore */ }
            try {
                const d = row.getData() || {};
                if (String(d[statusKey] || '') !== 'processing') return;
                const el = (row.getElement && row.getElement()) || null;
                const btn = el && el.querySelector && el.querySelector(btnSelector);
                chPromoPaintPushSpinner(btn, title);
            } catch (e) { /* ignore */ }
        }
        function collectChPromoSelectedRows() {
            if (typeof table === 'undefined' || !table) return [];
            const keys = new Set();
            function addSku(s) {
                const k = chPromoSkuKey(s);
                if (k) keys.add(k);
            }
            if (typeof selectedSkus !== 'undefined' && selectedSkus && selectedSkus.forEach) {
                selectedSkus.forEach(addSku);
            }
            if (typeof selectedRows !== 'undefined' && selectedRows && selectedRows.forEach) {
                selectedRows.forEach(addSku);
            }
            try {
                document.querySelectorAll('.sku-select-checkbox:checked').forEach(function(el) {
                    addSku(el.getAttribute('data-sku') || (el.dataset && el.dataset.sku) || '');
                });
            } catch (e) { /* ignore */ }
            if (!keys.size) return [];
            return table.getRows().filter(function(row) {
                const d = row.getData();
                return chPromoIsChildRow(d) && keys.has(chPromoSkuKey(chPromoSku(d)));
            }).map(function(row) {
                return { row: row, d: row.getData() };
            });
        }
        function collectChPromoVisibleRows() {
            if (typeof table === 'undefined' || !table) return [];
            const out = [];
            const seen = new Set();
            function addRow(row) {
                if (!row || typeof row.getData !== 'function') return;
                const d = row.getData();
                if (chPromoIsChildRow(d)) {
                    const k = chPromoSkuKey(chPromoSku(d));
                    if (k && !seen.has(k)) {
                        seen.add(k);
                        out.push({ row: row, d: d });
                    }
                }
                if (typeof row.getTreeChildren === 'function') {
                    (row.getTreeChildren() || []).forEach(addRow);
                }
            }
            (table.getRows('active') || []).forEach(addRow);
            return out;
        }
        function saveChannelPromoFields(sku, fields) {
            return $.ajax({
                url: CH_PROMO_SAVE_URL,
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': chPromoCsrf(), 'Accept': 'application/json' },
                data: Object.assign({
                    channel: CHANNEL_PROMO_CHANNEL,
                    sku: sku,
                    _token: chPromoCsrf(),
                }, fields || {}),
            });
        }
        function setChPromoPushPrcProgress(opts) {
            opts = opts || {};
            const $box = $('#ch-promo-push-prc-progress');
            if (!$box.length) return;
            const total = Number(opts.total) || 0;
            const done = Number(opts.done) || 0;
            const ok = Number(opts.ok) || 0;
            const fail = Number(opts.fail) || 0;
            const active = !!opts.active;
            const pct = (opts.pct != null)
                ? Math.min(100, Number(opts.pct) || 0)
                : (total > 0 ? Math.min(100, Math.round((done / total) * 100)) : 0);
            const finished = !active && total > 0 && (done >= total || pct >= 100);
            const title = opts.title || (finished ? (fail && !ok ? 'Push failed' : 'Pushed') : 'Pushing');
            if (active || finished) $box.addClass('active');
            else $box.removeClass('active');
            $box.toggleClass('done', finished || (!active && pct >= 100));
            $box.toggleClass('has-fail', fail > 0);
            $('#ch-promo-push-prc-progress-title').text(title);
            $('#ch-promo-push-prc-progress-pct').text(pct + '%');
            $('#ch-promo-push-prc-progress-bar').css('width', pct + '%');
            $('#ch-promo-push-prc-cancel-btn').toggle(!!opts.cancelable && !!active);
            let msg = opts.msg || '';
            if (!msg && total) {
                msg = done + '/' + total + ' · ' + ok + ' ok' + (fail ? (' · ' + fail + ' failed') : '');
            }
            $('#ch-promo-push-prc-progress-msg').text(msg || 'Ready');
            if (finished) {
                clearTimeout(setChPromoPushPrcProgress._hideTimer);
                setChPromoPushPrcProgress._hideTimer = setTimeout(function() {
                    if (!$box.hasClass('done')) return;
                    $box.removeClass('active done has-fail');
                    $('#ch-promo-push-prc-progress-bar').css('width', '0%');
                    $('#ch-promo-push-prc-progress-pct').text('0%');
                    $('#ch-promo-push-prc-progress-msg').text('Ready');
                    $('#ch-promo-push-prc-progress-title').text('Pushing');
                    $('#ch-promo-push-prc-cancel-btn').hide();
                }, 8000);
            }
        }
        function pushChannelPriceAjax(sku, price) {
            return $.ajax({
                url: chPromoCfg.pushPriceUrl,
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': chPromoCsrf(), 'Accept': 'application/json' },
                data: { sku: sku, price: price, _token: chPromoCsrf() },
            });
        }
        function chPromoStdPrcRound2(n) {
            const v = Number(n);
            return isFinite(v) ? Math.round(v * 100) / 100 : 0;
        }
        function chPromoStdPrcCurrent(d) {
            return chPromoStdPrcRound2(d && (d.STANDARD_PRICE != null ? d.STANDARD_PRICE : d.standard_price));
        }
        function chPromoStdPrcLastPushed(d) {
            return chPromoStdPrcRound2(d && d.PUSH_STD_PRC_VALUE);
        }
        function chPromoStdPrcIsChild(d) {
            if (!d || d.is_parent_summary || d.is_parent_row) return false;
            if (String(d.Parent || '').toUpperCase().startsWith('PARENT')) return false;
            return !!chPromoSku(d);
        }
        function chPromoStdPrcNeedsPush(d) {
            const std = chPromoStdPrcCurrent(d);
            if (!(std > 0)) return false;
            if (String(d.PUSH_STD_PRC_STATUS || '') === 'error') return true;
            const last = chPromoStdPrcLastPushed(d);
            if (!(last > 0)) return true;
            return last.toFixed(2) !== std.toFixed(2);
        }
        function chPromoStdPrcToast(type, msg) {
            if (typeof showToast !== 'function') {
                console.log(type, msg);
                return;
            }
            showToast(msg, type);
        }
        function chPromoPushStdPrcCollectTargets() {
            const selected = collectChPromoSelectedRows();
            if (selected.length) return selected;
            return collectChPromoVisibleRows();
        }
        function chPromoRefreshPushStdPrcCell(row) {
            chPromoRefreshPushCell(row, 'push_std_prc', '.ebay-push-std-prc-btn', 'PUSH_STD_PRC_STATUS', 'Pushing Std Prc to eBay…');
        }
        function chPromoPaintPushStdPrcSpinner(btn) {
            chPromoPaintPushSpinner(btn, 'Pushing Std Prc to eBay…');
        }
        async function chPromoPushStdPrcOne(row, opts) {
            opts = opts || {};
            const silent = !!opts.silent;
            const force = !!opts.force;
            const d = row.getData() || {};
            const sku = chPromoSku(d);
            const std = chPromoStdPrcCurrent(d);
            if (!sku || !(std > 0)) {
                if (!silent) chPromoStdPrcToast('error', 'Std Prc required');
                chPromoRefreshPushStdPrcCell(row);
                return { ok: false, skipped: true };
            }
            if (!force && !chPromoStdPrcNeedsPush(d)) {
                if (!silent) chPromoStdPrcToast('info', 'Std Prc unchanged since last push for ' + sku);
                chPromoRefreshPushStdPrcCell(row);
                return { ok: true, skipped: true };
            }
            row.update({ PUSH_STD_PRC_STATUS: 'processing', push_std_prc: 'processing' });
            chPromoRefreshPushStdPrcCell(row);
            if (!silent) {
                clearTimeout(setChPromoPushPrcProgress._hideTimer);
                setChPromoPushPrcProgress({
                    active: true, done: 0, total: 1, ok: 0, fail: 0, pct: 20,
                    title: 'Pushing',
                    msg: sku + ' · Std $' + std.toFixed(2),
                });
            }
            try {
                const response = await Promise.resolve(pushChannelPriceAjax(sku, std));
                if (response && response.errors && response.errors.length) {
                    throw new Error(response.errors[0].message || 'API error');
                }
                if (response && response.success === false) {
                    throw new Error(response.message || 'API error');
                }
                row.update({
                    PUSH_STD_PRC_STATUS: 'pushed',
                    PUSH_STD_PRC_VALUE: std,
                    'eBay Price': std,
                    push_std_prc: 'pushed'
                });
                chPromoRefreshPushStdPrcCell(row);
                try {
                    await Promise.resolve(saveChannelPromoFields(sku, {
                        record_push_std_prc: 1,
                        push_std_prc_value: std
                    }));
                } catch (saveErr) {
                    console.warn('Push Std Prc listing ok, but last-push record failed', sku, saveErr);
                }
                if (!silent) {
                    setChPromoPushPrcProgress({
                        active: false, done: 1, total: 1, ok: 1, fail: 0, pct: 100,
                        title: 'Pushed',
                        msg: sku + ' · Std $' + std.toFixed(2),
                    });
                    chPromoStdPrcToast('success', 'Std Prc $' + std.toFixed(2) + ' pushed to eBay for ' + sku);
                }
                return { ok: true };
            } catch (e) {
                row.update({ PUSH_STD_PRC_STATUS: 'error', push_std_prc: 'error' });
                chPromoRefreshPushStdPrcCell(row);
                if (!silent) {
                    const msg = (e && e.responseJSON && (e.responseJSON.message || (e.responseJSON.errors && e.responseJSON.errors[0] && e.responseJSON.errors[0].message)))
                        || (e && e.message)
                        || 'Failed to push Std Prc';
                    setChPromoPushPrcProgress({
                        active: false, done: 1, total: 1, ok: 0, fail: 1, pct: 100,
                        title: 'Push failed',
                        msg: sku + ' · ' + msg,
                    });
                    chPromoStdPrcToast('error', msg + ' (' + sku + ')');
                }
                return { ok: false };
            }
        }
        let chPromoPushStdPrcBusy = false;
        async function chPromoBulkPushStdPrcChanged() {
            if (chPromoPushStdPrcBusy) {
                chPromoStdPrcToast('info', 'Push Std Prc already running');
                return;
            }
            const all = chPromoPushStdPrcCollectTargets();
            const targets = all.filter(function(t) { return chPromoStdPrcNeedsPush(t.d); });
            const skipped = all.length - targets.length;
            if (!targets.length) {
                chPromoStdPrcToast('info', skipped
                    ? ('No Std Prc changes since last push (' + skipped + ' unchanged)')
                    : 'No SKUs to push');
                all.forEach(function(t) { chPromoRefreshPushStdPrcCell(t.row); });
                return;
            }
            if (!confirm(
                'Push Std Prc to eBay for ' + targets.length + ' SKU(s) changed since last push'
                + (skipped ? (' (' + skipped + ' unchanged skipped)') : '') + '?'
            )) {
                all.forEach(function(t) { chPromoRefreshPushStdPrcCell(t.row); });
                return;
            }
            chPromoPushStdPrcBusy = true;
            targets.forEach(function(t) {
                t.row.update({ PUSH_STD_PRC_STATUS: 'processing', push_std_prc: 'processing' });
                chPromoRefreshPushStdPrcCell(t.row);
            });
            let ok = 0, fail = 0;
            clearTimeout(setChPromoPushPrcProgress._hideTimer);
            setChPromoPushPrcProgress({
                active: true, done: 0, total: targets.length, ok: 0, fail: 0, pct: 5,
                title: 'Pushing',
                msg: 'Starting ' + targets.length + ' Std Prc…',
            });
            try {
                for (let i = 0; i < targets.length; i++) {
                    const sku = chPromoSku(targets[i].d);
                    setChPromoPushPrcProgress({
                        active: true, done: i, total: targets.length, ok: ok, fail: fail,
                        title: 'Pushing',
                        msg: sku + ' · Std Prc',
                    });
                    const res = await chPromoPushStdPrcOne(targets[i].row, { silent: true });
                    if (res && res.ok && !res.skipped) ok++;
                    else if (!(res && res.skipped)) fail++;
                }
            } finally {
                chPromoPushStdPrcBusy = false;
                setChPromoPushPrcProgress({
                    active: false, done: targets.length, total: targets.length, ok: ok, fail: fail, pct: 100,
                    title: fail && !ok ? 'Push failed' : 'Pushed',
                    msg: ok + ' ok' + (fail ? (' · ' + fail + ' failed') : ''),
                });
            }
            chPromoStdPrcToast(fail ? 'error' : 'success',
                'Push Std Prc: ' + ok + ' pushed'
                + (fail ? (', ' + fail + ' failed') : '')
                + (skipped ? (', ' + skipped + ' unchanged skipped') : ''));
        }
        function channelPromoPushStdPrcColumn() {
            return {
                title: 'Push Std Prc',
                field: 'push_std_prc',
                width: 72,
                hozAlign: 'center',
                vertAlign: 'middle',
                headerSort: false,
                headerTooltip: 'Push Std Prc — send Std to the live eBay listing price. Only SKUs whose Std changed since the last push are sent. Click this header to bulk selected (or visible) SKUs.',
                titleFormatter: function() {
                    return '<button type="button" class="btn btn-sm p-0 ebay-push-std-prc-header-btn" '
                        + 'title="Bulk Push Std Prc for selected SKUs whose Std changed since last push" '
                        + 'style="border:none;background:none;cursor:pointer;color:#000;'
                        + 'font-weight:700;font-size:11px;line-height:1.15;padding:0;">'
                        + 'Push Std Prc</button>';
                },
                headerClick: function(e) {
                    if (e.target.closest('.ebay-push-std-prc-header-btn')) {
                        e.stopPropagation();
                        e.preventDefault();
                        chPromoBulkPushStdPrcChanged();
                        return false;
                    }
                },
                formatter: function(cell) {
                    const d = cell.getRow().getData() || {};
                    if (!chPromoStdPrcIsChild(d)) return '';
                    const sku = chPromoSku(d);
                    const std = chPromoStdPrcCurrent(d);
                    if (!(std > 0)) {
                        return '<span style="color:#adb5bd;" title="Std Prc required">—</span>';
                    }
                    const status = String(d.PUSH_STD_PRC_STATUS || '');
                    const last = chPromoStdPrcLastPushed(d);
                    const needs = chPromoStdPrcNeedsPush(d);
                    let icon = '<i class="fas fa-upload"></i>';
                    let color = '#FF9900';
                    let tip = 'Push Std $' + std.toFixed(2) + ' to eBay Price';
                    if (status === 'processing') {
                        icon = '<i class="fas fa-spinner fa-spin" style="font-size:14px;"></i>';
                        color = '#ffc107';
                        tip = 'Pushing Std Prc to eBay…';
                    } else if (status === 'error') {
                        icon = '<i class="fa-solid fa-xmark"></i>';
                        color = '#dc3545';
                        tip = 'Last Push Std Prc failed — click to retry';
                    } else if (!needs) {
                        icon = '<i class="fa-solid fa-check-double"></i>';
                        color = '#28a745';
                        tip = 'Already pushed $' + last.toFixed(2)
                            + ' — click to push Std to eBay Price again';
                    } else if (last > 0) {
                        tip = 'Std changed $' + last.toFixed(2) + ' → $' + std.toFixed(2)
                            + ' — click to push to eBay Price';
                    }
                    return '<button type="button" class="btn btn-sm p-0 ebay-push-std-prc-btn" '
                        + 'data-sku="' + chPromoEscAttr(sku) + '" '
                        + 'data-price="' + std.toFixed(2) + '" '
                        + 'title="' + chPromoEscAttr(tip) + '" '
                        + 'style="border:none;background:none;cursor:pointer;color:' + color
                        + ';padding:0;line-height:1;vertical-align:middle;">'
                        + icon + '</button>';
                },
                cellClick: function(e, cell) {
                    const btn = e.target.closest('.ebay-push-std-prc-btn');
                    if (!btn) return;
                    e.stopPropagation();
                    e.preventDefault();
                    if (btn.disabled) return false;
                    const d = cell.getRow().getData() || {};
                    if (String(d.PUSH_STD_PRC_STATUS || '') === 'processing') return false;
                    const selected = collectChPromoSelectedRows();
                    const clickedKey = chPromoSkuKey(chPromoSku(d));
                    if (selected.length > 1 && selected.some(function(t) {
                        return chPromoSkuKey(chPromoSku(t.d)) === clickedKey;
                    })) {
                        chPromoBulkPushStdPrcChanged();
                        return false;
                    }
                    chPromoPushStdPrcOne(cell.getRow(), { force: true });
                    return false;
                },
            };
        }
        window.channelPromoPushStdPrcColumn = channelPromoPushStdPrcColumn;
        window.chPromoPaintPushStdPrcSpinner = chPromoPaintPushStdPrcSpinner;
        window.chPromoRefreshPushStdPrcCell = chPromoRefreshPushStdPrcCell;
        window.channelPromoPricingColumns = function() { return []; };
        window.channelPromoSprcCpnColumn = function() { return null; };
        window.channelPromoPushPrmtColumn = function() { return null; };
        window.channelPromoPushCpnColumn = function() { return null; };
@endif
