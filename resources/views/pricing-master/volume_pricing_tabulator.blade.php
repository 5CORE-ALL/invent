@extends('layouts.vertical', ['title' => 'Volume Pricing', 'sidenav' => 'condensed', 'skipHighcharts' => true])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        #vpt-filter-bar { gap: 6px; align-items: center; }
        #vpt-filter-bar .form-control { height: 28px; font-size: 12px; }
        #vpt-table { min-height: 70vh; }
        /* SPRICE $ column: input + ✓ + GPFT / GROI / NPFT / NROI (same look as /price-increase) */
        .pi-box-wrap { display: flex; align-items: center; gap: 4px; flex-wrap: nowrap; white-space: nowrap; }
        .pi-box-wrap .pi-box-input { width: 78px; min-width: 78px; height: 28px; font-size: 13px; font-weight: 600; padding: 2px 6px; }
        .pi-box-wrap .pi-box-input,
        .pi-box-wrap .pi-box-input:hover,
        .pi-box-wrap .pi-box-input:focus,
        .pi-box-wrap .pi-box-input:focus-visible { border: 0 !important; outline: none !important; box-shadow: none !important; background: transparent !important; }
        .pi-box-wrap .pi-box-go { height: 28px; width: 30px; padding: 0; line-height: 1; flex: 0 0 auto; }
        .pi-box-wrap .pi-box-chips { display: inline-flex; align-items: center; gap: 3px; margin-left: 4px; padding-left: 6px; border-left: 1px solid #cbd5e1; }
        .pi-box-wrap .pi-box-chip { display: inline-flex; align-items: center; gap: 3px; height: 26px; padding: 0 5px; background: #fff; border: 1px solid #ced4da; border-radius: 6px; font-size: 11px; font-weight: 700; }
        .pi-box-wrap .pi-box-lbl { font-size: 9px; font-weight: 700; color: #64748b; }
        .vpt-toast { position: fixed; right: 16px; bottom: 16px; z-index: 20000; min-width: 240px; padding: 8px 12px; border-radius: 8px; color: #fff; font-size: 13px; box-shadow: 0 6px 20px rgba(0,0,0,.2); }
        .vpt-toast.ok { background: #198754; }
        .vpt-toast.err { background: #dc3545; }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Volume Pricing',
        'sub_title' => 'Parent, SKU, INV, Dil, and OV L30',
    ])

    <div class="card shadow-sm">
        <div class="card-body py-2">
            <div class="d-flex flex-wrap align-items-center mb-2" id="vpt-filter-bar">
                <input type="text" id="vpt-parent-search" class="form-control form-control-sm" placeholder="Search Parent..." style="width: 170px;">
                <input type="text" id="vpt-sku-search" class="form-control form-control-sm" placeholder="Search SKU..." style="width: 170px;">
                <select id="vpt-inv-filter" class="form-select form-select-sm" style="width: 130px; height: 28px; font-size: 12px;">
                    <option value="all">INV: All</option>
                    <option value="gt0" selected>INV: &gt; 0</option>
                    <option value="zero">INV: 0</option>
                    <option value="1to10">INV: 1–10</option>
                    <option value="11to50">INV: 11–50</option>
                    <option value="gt50">INV: 50+</option>
                </select>
                <select id="vpt-dil-filter" class="form-select form-select-sm" style="width: 150px; height: 28px; font-size: 12px;" title="Dil = OV L30 ÷ INV">
                    <option value="all">Dil: All</option>
                    <option value="zero">Dil: 0%</option>
                    <option value="lt25">Dil: 1–24%</option>
                    <option value="25to50">Dil: 25–49%</option>
                    <option value="gte50">Dil: 50%+</option>
                </select>
                <span class="badge bg-dark" id="vpt-rows-badge">Rows: 0</span>
                <span class="small text-muted" id="vpt-status">Loading…</span>
            </div>
            <div id="vpt-table"></div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://cdn.jsdelivr.net/npm/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const DATA_URL = @json(route('volume.pricing.data'));
            const status = document.getElementById('vpt-status');
            const badge = document.getElementById('vpt-rows-badge');

            function num(v) {
                const n = Number(v);
                return isFinite(n) ? n : 0;
            }

            // Dil = OV L30 ÷ INV, same as the eBay volume pricing page.
            function dil(d) {
                const inv = num(d && d.inv);
                return inv > 0 ? (num(d.ov_l30) / inv) * 100 : 0;
            }

            const table = new Tabulator('#vpt-table', {
                data: [],
                layout: 'fitDataStretch',
                height: 'calc(100vh - 210px)',
                placeholder: 'Loading rows…',
                pagination: true,
                paginationMode: 'local',
                paginationSize: 100,
                paginationCounter: 'rows',
                columnDefaults: { headerSort: true, resizable: true, vertAlign: 'middle' },
                columns: [
                    { title: 'Parent', field: 'parent', width: 160, frozen: true, headerFilter: 'input', cssClass: 'text-primary' },
                    { title: 'SKU', field: 'sku', width: 200, frozen: true, headerFilter: 'input', cssClass: 'fw-bold text-primary' },
                    { title: 'INV', field: 'inv', hozAlign: 'center', width: 80, sorter: 'number',
                        formatter: function(cell) { return Math.round(num(cell.getValue())); } },
                    { title: 'Dil', field: 'dil', hozAlign: 'center', width: 80,
                        headerTooltip: 'Dil = OV L30 ÷ INV',
                        sorter: function(a, b, aRow, bRow) { return dil(aRow.getData()) - dil(bRow.getData()); },
                        formatter: function(cell) {
                            const v = dil(cell.getRow().getData());
                            let color = '#6c757d';
                            if (v > 0 && v < 25) color = '#a00211';
                            else if (v >= 25 && v < 50) color = '#28a745';
                            else if (v >= 50) color = '#e83e8c';
                            return '<span style="color:' + color + ';font-weight:600;">' + Math.round(v) + '%</span>';
                        } },
                    { title: 'OV L30', field: 'ov_l30', hozAlign: 'center', width: 90, sorter: 'number',
                        formatter: function(cell) { return Math.round(num(cell.getValue())); } },
                    { title: 'SPRICE $', field: 'box_sprice', hozAlign: 'left', headerSort: false, minWidth: 430, width: 440,
                        headerTooltip: 'Type a price and press ✓ (or Enter) to fill every channel by its column rule, save and push (Push All) — same as /price-increase. GPFT / GROI / NPFT / NROI = Amazon percentages on that price.',
                        formatter: function(cell) { return spriceCellEl(cell); } },
                ],
            });

            // ==================== SPRICE $ column ====================
            const typed = {}; // SKU (UPPER) -> text typed and not saved yet

            function toast(msg, type) {
                const el = document.createElement('div');
                el.className = 'vpt-toast ' + (type === 'error' ? 'err' : 'ok');
                el.textContent = msg;
                document.body.appendChild(el);
                setTimeout(function() { el.remove(); }, 3500);
            }
            function skuKey(sku) { return String(sku || '').trim().toUpperCase(); }

            /** The price the box shows: its own saved price, else (never fed) the saved Amazon SPRICE — like /price-increase. */
            function savedPrice(d) {
                const own = parseFloat(d && d.box_sprice);
                if (own > 0) return own;
                const seed = parseFloat(d && d.amazon_sprice);
                return seed > 0 ? seed : 0;
            }

            /** GPFT / GROI / NPFT / NROI of Amazon at this price — same formulas as /price-increase. */
            function metrics(price, d) {
                price = parseFloat(price) || 0;
                const lp = parseFloat(d && d.lp) || 0;
                const ship = parseFloat(d && d.ship) || 0;
                const ads = parseFloat(d && d.amazon_ads) || 0;
                const mAttr = parseFloat(d && d.amazon_margin);
                const margin = isFinite(mAttr) && mAttr > 0 ? mAttr : 0.80;
                if (!(price > 0) || !(lp > 0)) return null;
                const gross = price * 0.80 - ship - lp;
                const gpft = (gross / price) * 100;
                return {
                    price: price, lp: lp, ship: ship, ads: ads, margin: margin,
                    gpft: gpft,
                    groi: (gross / lp) * 100,
                    npft: gpft - ads,
                    nroi: ((price * margin - ship - lp - price * (ads / 100)) / lp) * 100
                };
            }

            function chipsHtml(price, d) {
                const am = metrics(price, d);
                const defs = [['GPFT', am && am.gpft, 'gpft'], ['GROI', am && am.groi, 'groi'],
                              ['NPFT', am && am.npft, 'npft'], ['NROI', am && am.nroi, 'nroi']];
                const title = am
                    ? ('Amazon percentages on $' + am.price.toFixed(2) + ': LP $' + am.lp.toFixed(2) + ', ship $' + am.ship.toFixed(2)
                        + ', Ads ' + am.ads.toFixed(2) + '%, margin ' + Math.round(am.margin * 100) + '%')
                    : 'Enter a price (needs LP) to see GPFT / GROI / NPFT / NROI';
                return defs.map(function(v) {
                    let val = '<span class="text-muted">-</span>';
                    if (am) {
                        val = window.MetricPctColors
                            ? MetricPctColors.htmlFor(v[2], v[1], { decimals: 0, empty: '0%' })
                            : '<span style="color:#dc3545;font-weight:600;">' + Math.round(v[1]) + '%</span>';
                    }
                    return '<span class="pi-box-chip" title="' + title + '"><span class="pi-box-lbl">' + v[0] + '</span>' + val + '</span>';
                }).join('');
            }

            function spriceCellEl(cell) {
                const row = cell.getRow();
                const d = row.getData() || {};
                const key = skuKey(d.sku);
                const saved = savedPrice(d);
                const t = Object.prototype.hasOwnProperty.call(typed, key) ? typed[key] : null;
                const shown = t != null ? t : (saved > 0 ? saved.toFixed(2) : '');

                const wrap = document.createElement('div');
                wrap.className = 'pi-box-wrap';
                wrap.innerHTML =
                    '<input type="number" class="form-control form-control-sm text-end pi-box-input" step="0.01" min="0.01" placeholder="0.00" title="SPRICE $ — press ✓ or Enter to fill every channel, save and push (Push All)">'
                    + '<button type="button" class="btn btn-sm btn-outline-success pi-box-go" title="Push All — fills every channel by its column rule, then saves and pushes them (same as /price-increase SPRICE $ ✓)" aria-label="Push all channels with this SPRICE"><i class="fas fa-check"></i></button>'
                    + '<span class="pi-box-chips"></span>';
                const input = wrap.querySelector('.pi-box-input');
                const btn = wrap.querySelector('.pi-box-go');
                const chips = wrap.querySelector('.pi-box-chips');
                input.value = shown;
                chips.innerHTML = chipsHtml(parseFloat(shown) > 0 ? shown : saved, d);

                ['click', 'mousedown', 'dblclick', 'keyup'].forEach(function(ev) {
                    wrap.addEventListener(ev, function(e) { e.stopPropagation(); });
                });
                input.addEventListener('input', function() {
                    typed[key] = input.value;
                    const p = parseFloat(input.value);
                    chips.innerHTML = chipsHtml(p > 0 ? p : saved, row.getData() || d);
                });
                input.addEventListener('keydown', function(e) {
                    e.stopPropagation();
                    if (e.key === 'Enter') { e.preventDefault(); saveSprice(row, input, btn); }
                });
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    saveSprice(row, input, btn);
                });
                return wrap;
            }

            // ==================== ✓ = Push All (same as /price-increase) ====================
            // The push engine IS the /price-increase page: it is loaded once in a hidden frame and does exactly what its own
            // SPRICE $ ✓ does — fills every channel by its column rule (B2C = X, B2B/C2C = 0.75 × X, B2B without ship =
            // 0.75 × X − Ship, High Shipping = X + Diff, Doba −25% and never pushed), saves each channel's SPRICE, saves the
            // SPRICE $ box value, and pushes in a tracked server background job (failed pushes are retried hourly + e-mailed).
            let bridgeFrame = null;
            let bridgeReady = false;
            let bridgeWaiters = [];
            const pendingPush = {}; // SKU (UPPER) -> { row, btn, startedAt, price }

            function ensureBridge() {
                return new Promise(function(resolve, reject) {
                    if (bridgeReady) { resolve(); return; }
                    bridgeWaiters.push({ resolve: resolve, reject: reject });
                    if (bridgeFrame) return;
                    bridgeFrame = document.createElement('iframe');
                    bridgeFrame.src = @json(url('/price-increase'));
                    bridgeFrame.setAttribute('aria-hidden', 'true');
                    bridgeFrame.tabIndex = -1;
                    bridgeFrame.style.cssText = 'position:fixed;left:-10000px;top:0;width:1280px;height:800px;border:0;opacity:0;pointer-events:none;';
                    document.body.appendChild(bridgeFrame);
                    setTimeout(function() {
                        if (bridgeReady) return;
                        const w = bridgeWaiters; bridgeWaiters = [];
                        w.forEach(function(x) { x.reject(new Error('The push engine (/price-increase) did not load — try again')); });
                        if (bridgeFrame) { bridgeFrame.remove(); bridgeFrame = null; }
                    }, 120000);
                });
            }

            function setBtnBusy(btn, busy) {
                if (!btn) return;
                btn.disabled = !!busy;
                btn.innerHTML = busy ? '<i class="fas fa-spinner fa-spin"></i>' : '<i class="fas fa-check"></i>';
            }

            /** After the job started: follow the server's tracked pushes of this SKU and report the outcome. */
            function followPush(sku, startedAt) {
                const url = @json(url('/price-push-status')) + '?sku=' + encodeURIComponent(sku);
                let tries = 0;
                (function poll() {
                    fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                        .then(function(r) { return r.json(); })
                        .then(function(res) {
                            const rows = Object.values((res && res.rows) || {}).filter(function(r) {
                                return r && r.at && (new Date(r.at).getTime() >= startedAt - 120000);
                            });
                            const waiting = rows.filter(function(r) { return r.status === 'pending' && /^Queued/i.test(String(r.error || '')); });
                            if (waiting.length && ++tries < 400) { setTimeout(poll, 3000); return; }
                            const pushed = rows.filter(function(r) { return r.status === 'pushed'; }).length;
                            const failed = rows.length - pushed - waiting.length;
                            toast(sku + ': pushed ' + pushed + ' channel(s)' + (failed > 0 ? (', ' + failed + ' failed (see Push Pending)') : ''), failed > 0 ? 'error' : 'ok');
                        })
                        .catch(function() { if (++tries < 400) setTimeout(poll, 5000); });
                })();
            }

            window.addEventListener('message', function(ev) {
                if (ev.origin !== window.location.origin) return;
                const m = ev.data;
                if (!m) return;
                if (m.type === 'pi-bridge-ready') {
                    bridgeReady = true;
                    const w = bridgeWaiters; bridgeWaiters = [];
                    w.forEach(function(x) { x.resolve(); });
                    return;
                }
                if (m.type !== 'pi-box-push-result') return;
                const key = skuKey(m.sku);
                const p = pendingPush[key];
                if (!p) return;
                delete pendingPush[key];
                setBtnBusy(p.btn, false);
                if (!m.ok) {
                    toast(m.message || ('Could not push ' + m.sku), 'error');
                    return;
                }
                // The engine saved the SPRICE $ box value too (shared with /price-increase)
                delete typed[key];
                const d = p.row.getData() || {};
                d.box_sprice = p.price;
                p.row.update({ box_sprice: p.price });
                toast(m.sku + ': SPRICE $' + p.price.toFixed(2) + ' saved, pushing to all channels…', 'ok');
                followPush(m.sku, p.startedAt);
            });

            /** ✓ / Enter: Push All for this SKU. */
            function saveSprice(row, input, btn) {
                const d = row.getData() || {};
                const sku = String(d.sku || '');
                const price = parseFloat(String(input.value == null ? '' : input.value).replace(/[$,\s]/g, '').replace(',', '.'));
                if (!isFinite(price) || !(price > 0)) {
                    toast('Enter SPRICE $ greater than 0', 'error');
                    input.focus();
                    return;
                }
                if (!sku) return;
                const key = skuKey(sku);
                if (pendingPush[key]) { toast('A push for ' + sku + ' is still being prepared', 'error'); return; }
                const value = Math.max(0.01, +price.toFixed(2));
                pendingPush[key] = { row: row, btn: btn, startedAt: Date.now(), price: value };
                setBtnBusy(btn, true);
                if (!bridgeReady) toast('Loading the push engine (/price-increase) — the first push can take a moment…', 'ok');

                ensureBridge().then(function() {
                    bridgeFrame.contentWindow.postMessage({
                        type: 'pi-box-push', sku: sku, price: value,
                        data: { inventory: num(d.inv), overall_l30: num(d.ov_l30), dil_percent: dil(d), image_path: '' }
                    }, window.location.origin);
                }).catch(function(err) {
                    delete pendingPush[key];
                    setBtnBusy(btn, false);
                    toast((err && err.message) || 'Could not start the push', 'error');
                });
            }

            function applySearch() {
                const parent = document.getElementById('vpt-parent-search').value.trim().toLowerCase();
                const sku = document.getElementById('vpt-sku-search').value.trim().toLowerCase();
                const mode = dilSelect.value;
                const invMode = invSelect.value;
                table.setFilter(function(d) {
                    if (parent && String(d.parent || '').toLowerCase().indexOf(parent) === -1) return false;
                    if (sku && String(d.sku || '').toLowerCase().indexOf(sku) === -1) return false;

                    const inv = Math.round(num(d.inv));
                    if (invMode === 'gt0' && !(inv > 0)) return false;
                    if (invMode === 'zero' && inv !== 0) return false;
                    if (invMode === '1to10' && !(inv >= 1 && inv <= 10)) return false;
                    if (invMode === '11to50' && !(inv >= 11 && inv <= 50)) return false;
                    if (invMode === 'gt50' && !(inv > 50)) return false;

                    const v = Math.round(dil(d));
                    if (mode === 'zero' && v !== 0) return false;
                    if (mode === 'lt25' && !(v >= 1 && v < 25)) return false;
                    if (mode === '25to50' && !(v >= 25 && v < 50)) return false;
                    if (mode === 'gte50' && v < 50) return false;
                    return true;
                });
                badge.textContent = 'Rows: ' + table.getDataCount('active');
            }
            const dilSelect = document.getElementById('vpt-dil-filter');
            const invSelect = document.getElementById('vpt-inv-filter');
            invSelect.addEventListener('change', applySearch);
            document.getElementById('vpt-parent-search').addEventListener('input', applySearch);
            document.getElementById('vpt-sku-search').addEventListener('input', applySearch);
            dilSelect.addEventListener('change', applySearch);
            fetch(DATA_URL, {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function(res) {
                if (!res.ok) throw new Error('Data failed (' + res.status + ')');
                return res.json();
            }).then(function(payload) {
                const rows = (payload && payload.data) || [];
                return table.setData(rows).then(function() {
                    status.textContent = rows.length + ' rows loaded';
                    applySearch(); // the filters (INV > 0 by default) apply to the loaded rows
                });
            }).catch(function(err) {
                status.textContent = (err && err.message) || 'Could not load data';
            });
        });
    </script>
@endsection
