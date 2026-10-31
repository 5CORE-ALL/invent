{{--
  Amazon coupon rule: AL30 Dil % AND Price → Coupon %  (SKU based).
  A SKU gets the coupon of the first rule row where BOTH its Dil % range and its price range hold.
    AL30 Dil % = A L30 ÷ INV × 100 (INV must be above 0; the 0–0 Dil range means A L30 = 0).
  Parts: button | modal | script.  Used by the Promotions page.
  Styling reuses the .el30cpn-* classes, which come from partials/ebay-zero-sold-coupon (part "modal");
  include that modal part on the same page.
  This stores the rule and tracks SKU counts only. It does not push coupons to Amazon.
--}}
@php
    $amazonCouponPart = $amazonCouponPart ?? 'button';
    $amazonCouponPrefix = 'amazon-dil-price-coupon';
@endphp

@if($amazonCouponPart === 'button')
<button type="button" id="{{ $amazonCouponPrefix }}-btn" class="btn btn-outline-secondary btn-sm el30cpn-btn"
    title="Amazon coupon by AL30 Dil % and price. AL30 Dil % = A L30 ÷ INV × 100.">
    <i class="fas fa-ticket-alt"></i> Amazon Coupon Rule
</button>
@endif

@if($amazonCouponPart === 'modal')
<style>
    /* Single-range counts (Dil only / Price only): lighter than the main "both conditions" count pill. */
    .el30cpn .amz-range-count { display: inline-block; min-width: 46px; padding: 3px 12px; border-radius: 999px; font-weight: 800; font-size: .95rem; text-align: center;
        color: color-mix(in srgb, var(--c, #7c3aed) 80%, #1f2937); background: color-mix(in srgb, var(--c, #7c3aed) 14%, #fff);
        border: 2px solid color-mix(in srgb, var(--c, #7c3aed) 35%, #fff); }
    .el30cpn .amz-th-both { color: #be185d; }
    .el30cpn .amz-push-btn { margin: 6px 0 0 8px; border: 0; border-radius: 999px; padding: 8px 18px; font-weight: 700; color: #fff;
        background: linear-gradient(135deg, #f59e0b, #ef4444); box-shadow: 0 4px 12px rgba(239, 68, 68, .3); }
    .el30cpn .amz-push-btn:disabled { opacity: .55; cursor: not-allowed; box-shadow: none; }
    .el30cpn .amz-push-cancel { margin: 6px 0 0 8px; border: 2px solid #ef4444; background: #fff; color: #ef4444; border-radius: 999px; padding: 6px 14px; font-weight: 700; }
    .el30cpn .amz-push-box { margin-top: 10px; padding: 10px 14px; border-radius: 12px; background: #fff7ed; border: 1px solid #fed7aa; }
    .el30cpn .amz-push-bar { height: 8px; border-radius: 99px; background: #ffedd5; overflow: hidden; }
    .el30cpn .amz-push-fill { height: 100%; width: 0; background: linear-gradient(90deg, #f59e0b, #ef4444); transition: width .4s; }
    .el30cpn .amz-push-msg { margin-top: 6px; font-size: .85rem; font-weight: 600; color: #9a3412; }
    .el30cpn .amz-push-fails { margin-top: 4px; font-size: .78rem; color: #b91c1c; max-height: 90px; overflow: auto; }
</style>
<div class="modal fade el30cpn" id="{{ $amazonCouponPrefix }}-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content el30cpn-content">
            <div class="el30cpn-hero">
                <img class="el30cpn-gif" src="{{ asset('images/ebay-coupon.gif') }}" alt="Coupon">
                <div class="el30cpn-hero-text">
                    <h5 class="modal-title">Amazon Dil % &times; Price Coupons</h5>
                    <div class="el30cpn-sub">Every SKU gets the coupon of the first rule where both its sell-through and its price fall in range.</div>
                    <span class="el30cpn-formula">AL30 Dil % = A L30 ÷ INV × 100 &nbsp;·&nbsp; Price = Amazon price</span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="el30cpn-note">
                    <i class="fas fa-lightbulb"></i>
                    <div>A SKU must match <b>both</b> the Dil % range <b>and</b> the Price range. Rules are checked top to bottom and the first match wins. INV must be above 0. The <b>0–0</b> Dil range is A L30 = 0. Coupon % of <b>0</b> leaves that rule off; Amazon accepts <b>5–80</b>. <b>Dil Count</b> and <b>Price Count</b> show how many SKUs fall in that one range alone; <b>Count (Both)</b> is where both apply. Click a colored dot next to Count (Both) to see that rule's history. Changes save automatically; nothing reaches Amazon until you click <b>Push to Amazon</b>. Amazon's API cannot create Seller Central coupons, so a coupon is applied as the listing price × (1 − %), the same way CVR vs CPN does it.</div>
                </div>
                <div class="table-responsive">
                    <table class="el30cpn-table">
                        <thead>
                            <tr>
                                <th>Dil % From</th>
                                <th>Dil % To</th>
                                <th class="text-center" title="SKUs whose Dil % is in this rule's Dil range, whatever their price">Dil Count</th>
                                <th>Price From ($)</th>
                                <th>Price To ($)</th>
                                <th class="text-center" title="SKUs whose price is in this rule's price range, whatever their Dil %">Price Count</th>
                                <th class="text-center amz-th-both" title="SKUs where both the Dil % range and the Price range apply (first matching rule wins)">Count (Both)</th>
                                <th>Coupon %</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="{{ $amazonCouponPrefix }}-slabs"></tbody>
                    </table>
                </div>
                <button type="button" class="el30cpn-add" id="{{ $amazonCouponPrefix }}-add"><i class="fas fa-plus me-1"></i> Add rule</button>
                <button type="button" class="amz-push-btn" id="{{ $amazonCouponPrefix }}-push"><i class="fas fa-cloud-upload-alt me-1"></i> Push to Amazon</button>
                <button type="button" class="amz-push-cancel" id="{{ $amazonCouponPrefix }}-push-cancel" hidden>Cancel push</button>
                <div class="el30cpn-status" id="{{ $amazonCouponPrefix }}-status"></div>
                <div class="amz-push-box" id="{{ $amazonCouponPrefix }}-push-box" hidden>
                    <div class="amz-push-bar"><div class="amz-push-fill" id="{{ $amazonCouponPrefix }}-push-fill"></div></div>
                    <div class="amz-push-msg" id="{{ $amazonCouponPrefix }}-push-msg"></div>
                    <div class="amz-push-fails" id="{{ $amazonCouponPrefix }}-push-fails"></div>
                </div>
                <div class="el30cpn-hist" id="{{ $amazonCouponPrefix }}-hist">
                    <div class="el30cpn-hist-head">
                        <div class="el30cpn-hist-title"><span class="el30cpn-hist-name"></span><small>SKUs per day (daily snapshot)</small></div>
                        <select class="el30cpn-hist-range" aria-label="Range">
                            <option value="7">7 days</option>
                            <option value="30" selected>30 days</option>
                            <option value="90">90 days</option>
                            <option value="0">All</option>
                        </select>
                        <button type="button" class="el30cpn-x" aria-label="Close history">×</button>
                    </div>
                    <div class="el30cpn-chips"></div>
                    <div class="el30cpn-chart"></div>
                </div>
            </div>
        </div>
    </div>
</div>
@endif

@if($amazonCouponPart === 'script')
(function() {
    const prefix = @json($amazonCouponPrefix);
    const url = @json(route('promotions.amazon.coupon.show'));
    const COLORS = ['#ef4444', '#f97316', '#eab308', '#22c55e', '#06b6d4', '#6366f1', '#a855f7', '#ec4899'];
    const state = { slabs: [], skus: [], hist: null, ready: false, loading: false, saving: false, pending: false };
    let saveTimer = null;
    let histOpenIdx = null;
    let afterLoad = null;

    function csrf() { return $('meta[name="csrf-token"]').attr('content'); }
    function r2(n) { return Math.round((Number(n) || 0) * 100) / 100; }
    function num(v) { const n = Number(v); return isFinite(n) ? n : 0; }
    function couponPct(pct) {
        const n = Math.round(Number(pct) || 0);
        return (n >= 5 && n <= 80) ? n : 0;
    }
    // Same key the server builds: d<min>-<max>|p<min>-<max>
    function ruleKey(s) {
        const a = r2(s.dmin), b = r2(s.dmax), c = r2(s.pmin), d = r2(s.pmax);
        return 'd' + Math.min(a, b) + '-' + Math.max(a, b) + '|p' + Math.min(c, d) + '-' + Math.max(c, d);
    }

    // ---- matching ----
    // sku = [A L30, INV, price]. Each condition on its own, so it can also be counted alone.
    function dilInRange(s, sku) {
        const l30 = sku[0], inv = sku[1];
        if (!(inv > 0)) return false;
        const lo = num(s.dmin), hi = num(s.dmax);
        if (Math.abs(lo) < 1e-7 && Math.abs(hi) < 1e-7) return Math.abs(l30) < 1e-7; // 0–0 = no A L30 sales
        const dil = (l30 / inv) * 100;
        return dil >= lo && dil <= hi;
    }
    function priceInRange(s, sku) {
        const price = sku[2];
        return price > 0 && price >= num(s.pmin) && price <= num(s.pmax);
    }
    // Both conditions must hold; the first matching rule wins.
    function matchIdx(sku) {
        if (!(sku[1] > 0) || !(sku[2] > 0)) return -1;
        for (let i = 0; i < state.slabs.length; i++) {
            if (dilInRange(state.slabs[i], sku) && priceInRange(state.slabs[i], sku)) return i;
        }
        return -1;
    }
    // Count (Both): SKUs where both conditions apply. This is the one saved to history.
    function counts() {
        const t = state.slabs.map(function() { return 0; });
        state.skus.forEach(function(sku) {
            const i = matchIdx(sku);
            if (i >= 0) t[i] += 1;
        });
        return t;
    }
    // Dil Count / Price Count: each range counted on its own (not first-match, rules may overlap).
    function rangeCounts() {
        const dil = state.slabs.map(function() { return 0; });
        const price = state.slabs.map(function() { return 0; });
        state.skus.forEach(function(sku) {
            state.slabs.forEach(function(s, i) {
                if (dilInRange(s, sku)) dil[i] += 1;
                if (priceInRange(s, sku)) price[i] += 1;
            });
        });
        return { dil: dil, price: price };
    }

    // ---- history dots + graph ----
    function todayPt() {
        if (state.hist && state.hist.today) return state.hist.today;
        try { return new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Los_Angeles' }).format(new Date()); }
        catch (e) { return new Date().toISOString().slice(0, 10); }
    }
    function prevCount(key) {
        const days = (state.hist && state.hist.days) || {};
        const today = todayPt();
        const keys = Object.keys(days).sort().reverse();
        for (let i = 0; i < keys.length; i++) {
            if (keys[i] >= today) continue;
            const row = days[keys[i]];
            if (row && row[key] != null && isFinite(Number(row[key]))) return Number(row[key]);
        }
        return null;
    }
    function dotClass(live, prev, invert) {
        if (prev == null || !isFinite(live)) return 'none';
        const d = live - prev;
        let c = d > 0 ? 'up' : (d < 0 ? 'down' : 'flat');
        if (invert && c === 'up') c = 'down';
        else if (invert && c === 'down') c = 'up';
        return c;
    }
    function paintDots(tally) {
        $('#' + prefix + '-slabs tr').each(function(i) {
            const slab = state.slabs[i];
            const $dot = $(this).find('.el30-slab-dot');
            if (!slab || !$dot.length) return;
            const live = tally[i] || 0;
            const prev = prevCount(ruleKey(slab));
            // Dil 0–0 = SKUs with no A L30 sales: fewer of them is the good direction.
            const zero = Math.abs(num(slab.dmin)) < 1e-7 && Math.abs(num(slab.dmax)) < 1e-7;
            const cls = dotClass(live, prev, zero);
            let tip = 'Click for history (no prior day saved yet).';
            if (prev != null) {
                const diff = live - prev;
                tip = (diff > 0 ? 'Up ' + diff : (diff < 0 ? 'Down ' + Math.abs(diff) : 'Same'))
                    + ' vs prior day (' + prev + ' → ' + live + '). Click for history.';
            }
            $dot.attr('class', 'el30-slab-dot ' + cls).attr('title', tip);
        });
        if (histOpenIdx !== null) drawHist();
    }
    function histSeries(key, live) {
        const days = (state.hist && state.hist.days) || {};
        const today = todayPt();
        const out = [];
        Object.keys(days).sort().forEach(function(d) {
            if (d === today) return;
            const row = days[d];
            if (row && row[key] != null && isFinite(Number(row[key]))) out.push({ d: d, v: Number(row[key]) });
        });
        if (live != null) out.push({ d: today, v: live });
        return out;
    }
    function dLabel(ymd) {
        return new Date(ymd + 'T12:00:00').toLocaleDateString('en-US', { month: 'short', day: '2-digit' });
    }
    function chartSvg(pts, color) {
        if (!pts.length) return '<div class="el30cpn-empty">No history yet. Today\'s count is saved automatically.</div>';
        const W = 700, H = 230, pl = 44, pr = 16, pt = 14, pb = 34;
        let max = Math.max.apply(null, pts.map(function(p) { return p.v; }));
        let min = Math.min.apply(null, pts.map(function(p) { return p.v; }));
        if (max === min) { max += 1; min = Math.max(0, min - 1); }
        const pad = (max - min) * 0.15;
        const hi = max + pad, lo = Math.max(0, min - pad);
        const x = function(i) { return pts.length === 1 ? pl + (W - pl - pr) / 2 : pl + (W - pl - pr) * i / (pts.length - 1); };
        const y = function(v) { return pt + (H - pt - pb) * (1 - (v - lo) / (hi - lo)); };
        const gid = 'amzcg-' + prefix;
        let svg = '<svg class="el30cpn-svg" viewBox="0 0 ' + W + ' ' + H + '" style="--c:' + color + ';">'
            + '<defs><linearGradient id="' + gid + '" x1="0" y1="0" x2="0" y2="1">'
            + '<stop offset="0%" stop-color="' + color + '" stop-opacity="0.38"/><stop offset="100%" stop-color="' + color + '" stop-opacity="0.02"/></linearGradient></defs>';
        for (let k = 0; k <= 4; k++) {
            const gv = lo + (hi - lo) * k / 4, gy = y(gv);
            svg += '<line class="grid" x1="' + pl + '" y1="' + gy + '" x2="' + (W - pr) + '" y2="' + gy + '"/>'
                + '<text x="' + (pl - 8) + '" y="' + (gy + 4) + '" text-anchor="end">' + Math.round(gv) + '</text>';
        }
        const line = pts.map(function(p, i) { return (i ? 'L' : 'M') + x(i).toFixed(1) + ',' + y(p.v).toFixed(1); }).join(' ');
        if (pts.length > 1) {
            svg += '<path d="' + line + ' L' + x(pts.length - 1).toFixed(1) + ',' + (H - pb) + ' L' + x(0).toFixed(1) + ',' + (H - pb) + ' Z" fill="url(#' + gid + ')"/>'
                + '<path class="line" pathLength="1" d="' + line + '"/>';
        }
        const step = Math.max(1, Math.ceil(pts.length / 8));
        pts.forEach(function(p, i) {
            svg += '<circle class="pt" cx="' + x(i).toFixed(1) + '" cy="' + y(p.v).toFixed(1) + '" r="5"><title>' + dLabel(p.d) + ': ' + p.v + ' SKUs</title></circle>';
            if (i % step === 0 || i === pts.length - 1) {
                svg += '<text x="' + x(i).toFixed(1) + '" y="' + (H - 12) + '" text-anchor="middle">' + dLabel(p.d) + '</text>';
            }
        });
        return svg + '</svg>';
    }
    function drawHist() {
        const i = histOpenIdx;
        const slab = state.slabs[i];
        const $panel = $('#' + prefix + '-hist');
        if (i === null || !slab) { $panel.removeClass('show'); return; }
        const color = COLORS[i % COLORS.length];
        const tally = counts();
        let pts = histSeries(ruleKey(slab), tally[i] || 0);
        const range = parseInt($panel.find('.el30cpn-hist-range').val(), 10) || 0;
        if (range > 0 && pts.length) {
            const end = new Date(pts[pts.length - 1].d + 'T12:00:00');
            end.setDate(end.getDate() - (range - 1));
            const cut = end.toISOString().slice(0, 10);
            pts = pts.filter(function(p) { return p.d >= cut; });
        }
        $panel.css('--c', color);
        $panel.find('.el30cpn-hist-name').text('SKUs with Dil % ' + slab.dmin + ' – ' + slab.dmax + ' and price $' + slab.pmin + ' – $' + slab.pmax);
        const vals = pts.map(function(p) { return p.v; });
        const last = vals.length ? vals[vals.length - 1] : null;
        const prev = vals.length > 1 ? vals[vals.length - 2] : null;
        let chips = '';
        if (last !== null) chips += '<span class="el30cpn-chip">Now ' + last + '</span>';
        if (prev !== null) {
            const d = last - prev;
            chips += '<span class="el30cpn-chip ' + (d > 0 ? 'up' : (d < 0 ? 'down' : '')) + '">' + (d > 0 ? '▲ +' : (d < 0 ? '▼ ' : '● ')) + d + ' vs prior</span>';
        }
        if (vals.length) {
            chips += '<span class="el30cpn-chip">Peak ' + Math.max.apply(null, vals) + '</span>'
                + '<span class="el30cpn-chip">Low ' + Math.min.apply(null, vals) + '</span>'
                + '<span class="el30cpn-chip">' + vals.length + ' day' + (vals.length === 1 ? '' : 's') + '</span>';
        }
        $panel.find('.el30cpn-chips').html(chips);
        $panel.find('.el30cpn-chart').html(chartSvg(pts, color));
        $panel.addClass('show');
    }

    // ---- table ----
    function numInput(cls, value, step) {
        return '<td><input type="number" step="' + step + '" min="0" class="form-control form-control-sm el30cpn-input ' + cls + '" value="' + value + '"></td>';
    }
    function paintChrome() {
        const tally = counts();
        const rc = rangeCounts();
        $('#' + prefix + '-slabs tr').each(function(i) {
            $(this).find('.amz-count').text(tally[i] || 0);
            $(this).find('.amz-dil-count').text(rc.dil[i] || 0);
            $(this).find('.amz-price-count').text(rc.price[i] || 0);
        });
        paintDots(tally);
    }
    function paintSlabs() {
        const tally = counts();
        const rc = rangeCounts();
        const html = state.slabs.map(function(slab, i) {
            const color = COLORS[i % COLORS.length];
            return '<tr style="--c:' + color + ';">'
                + numInput('amz-dmin', slab.dmin, '0.01')
                + numInput('amz-dmax', slab.dmax, '0.01')
                + '<td class="text-center"><span class="amz-range-count amz-dil-count" title="SKUs in this Dil % range, any price">' + (rc.dil[i] || 0) + '</span></td>'
                + numInput('amz-pmin', slab.pmin, '0.01')
                + numInput('amz-pmax', slab.pmax, '0.01')
                + '<td class="text-center"><span class="amz-range-count amz-price-count" title="SKUs in this price range, any Dil %">' + (rc.price[i] || 0) + '</span></td>'
                + '<td class="text-center"><span class="el30cpn-count" title="SKUs where both conditions apply"><span class="amz-count">' + (tally[i] || 0) + '</span>'
                + '<span class="el30-slab-dot none" data-i="' + i + '" title="Click for history"></span></span></td>'
                + '<td><div class="el30cpn-pct"><input type="number" min="0" max="80" step="1" class="form-control form-control-sm amz-pct" value="' + couponPct(slab.pct) + '"><span>%</span></div></td>'
                + '<td class="text-end"><button type="button" class="el30cpn-del amz-del" data-i="' + i + '" aria-label="Remove rule">×</button></td>'
                + '</tr>';
        }).join('');
        $('#' + prefix + '-slabs').html(html);
        paintChrome();
    }
    function readSlabs() {
        const body = $('#' + prefix + '-slabs tr');
        if (!body.length) return state.slabs.slice();
        const out = [];
        body.each(function() {
            const $r = $(this);
            let dmin = num($r.find('.amz-dmin').val()), dmax = num($r.find('.amz-dmax').val());
            let pmin = Math.max(0, num($r.find('.amz-pmin').val())), pmax = Math.max(0, num($r.find('.amz-pmax').val()));
            if (dmax < dmin) { const t = dmin; dmin = dmax; dmax = t; $r.find('.amz-dmin').val(dmin); $r.find('.amz-dmax').val(dmax); }
            if (pmax < pmin) { const t = pmin; pmin = pmax; pmax = t; $r.find('.amz-pmin').val(pmin); $r.find('.amz-pmax').val(pmax); }
            out.push({ dmin: dmin, dmax: dmax, pmin: pmin, pmax: pmax, pct: couponPct($r.find('.amz-pct').val()) });
        });
        return out.length ? out : state.slabs.slice();
    }
    function modalOpen() {
        const el = document.getElementById(prefix + '-modal');
        return !!(el && el.classList.contains('show'));
    }

    // ---- save (auto, debounced) ----
    function persist() {
        if (!state.ready) return;
        if (state.saving) { state.pending = true; return; }
        state.saving = true;
        const tally = counts();
        const countsPayload = state.slabs.map(function(s, i) {
            return { dmin: s.dmin, dmax: s.dmax, pmin: s.pmin, pmax: s.pmax, count: tally[i] || 0 };
        });
        $('#' + prefix + '-status').text('Saving…');
        $.ajax({
            url: url,
            method: 'POST',
            contentType: 'application/json',
            headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
            data: JSON.stringify({ slabs: state.slabs, counts: countsPayload }),
        }).done(function(res) {
            if (res && res.days) state.hist = { today: res.today, days: res.days };
            $('#' + prefix + '-status').text('Saved. Click Push to Amazon to apply the rules to the listings.');
            paintChrome();
        }).fail(function(xhr) {
            saveFailed = true;
            $('#' + prefix + '-status').text((xhr.responseJSON && xhr.responseJSON.message) || 'Could not save the rule.');
        }).always(function() {
            state.saving = false;
            if (state.pending) { state.pending = false; persist(); return; }
            const waiters = saveWaiters; saveWaiters = [];
            waiters.forEach(function(fn) { fn(!saveFailed); });
        });
    }
    let saveWaiters = [];
    let saveFailed = false;
    // Save right now (skipping the debounce) and call back with true/false once the server has the rules.
    function flushSave(cb) {
        clearTimeout(saveTimer);
        state.slabs = readSlabs();
        saveFailed = false;
        saveWaiters.push(cb);
        persist();
    }
    function scheduleSave() {
        clearTimeout(saveTimer);
        saveTimer = setTimeout(persist, 600);
    }
    function applyFromModal() {
        state.slabs = readSlabs();
        paintChrome();
        scheduleSave();
    }

    // ---- load + open ----
    function load() {
        if (state.loading) return;
        state.loading = true;
        $('#' + prefix + '-status').text('Loading Amazon SKUs…');
        $.get(url, function(res) {
            state.loading = false;
            if (!res || !res.success) {
                $('#' + prefix + '-status').text((res && res.message) || 'Could not load the Amazon coupon rule.');
                return;
            }
            state.slabs = (res.slabs || []).map(function(s) {
                return { dmin: num(s.dmin), dmax: num(s.dmax), pmin: num(s.pmin), pmax: num(s.pmax), pct: couponPct(s.pct) };
            });
            state.skus = res.skus || [];
            state.hist = { today: res.today, days: res.days || {} };
            state.ready = true;
            $('#' + prefix + '-status').text(state.skus.length + ' SKUs with INV and an Amazon price.');
            paintSlabs();
            if (typeof afterLoad === 'function') { const fn = afterLoad; afterLoad = null; fn(); }
        }).fail(function(xhr) {
            state.loading = false;
            $('#' + prefix + '-status').text((xhr.responseJSON && xhr.responseJSON.message) || 'Could not load the Amazon coupon rule.');
            if (typeof afterLoad === 'function') { const fn = afterLoad; afterLoad = null; fn(); }
        });
    }
    function openModal() {
        histOpenIdx = null;
        $('#' + prefix + '-hist').removeClass('show');
        paintSlabs();
        const modalEl = document.getElementById(prefix + '-modal');
        if (modalEl && window.bootstrap) bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }

    // ---- push to Amazon ----
    const pushUrls = {
        preview: @json(route('promotions.amazon.coupon.push.preview')),
        start: @json(route('promotions.amazon.coupon.push.start')),
        status: @json(route('promotions.amazon.coupon.push.status')),
        cancel: @json(route('promotions.amazon.coupon.push.cancel')),
    };
    let pushTimer = null;
    let pushBusy = false;

    function errMsg(xhr, fallback) {
        return (xhr && xhr.responseJSON && xhr.responseJSON.message) || fallback;
    }
    function paintPush(res) {
        const $box = $('#' + prefix + '-push-box');
        const active = !!(res && res.active);
        const total = Number(res && res.total) || 0;
        const done = Number(res && res.done) || 0;
        const show = active || (res && (res.status === 'completed' || res.status === 'cancelled') && total > 0);
        $box.prop('hidden', !show);
        $('#' + prefix + '-push').prop('disabled', active || pushBusy);
        $('#' + prefix + '-push-cancel').prop('hidden', !active);
        if (!show) return;
        $('#' + prefix + '-push-fill').css('width', (total > 0 ? Math.round(done / total * 100) : 0) + '%');
        $('#' + prefix + '-push-msg').text((res.last_message || '') + (total ? '  (' + done + '/' + total + ', ' + (res.fail_count || 0) + ' failed)' : ''));
        const fails = (res.failed || []).map(function(f) {
            return $('<div>').text(f.sku + ': ' + f.error).prop('outerHTML');
        }).join('');
        $('#' + prefix + '-push-fails').html(fails);
    }
    function stopPushPoll() { if (pushTimer) { clearInterval(pushTimer); pushTimer = null; } }
    function pollPush() {
        $.get(pushUrls.status).done(function(res) {
            paintPush(res);
            if (!res.active) stopPushPoll();
        }).fail(function() { stopPushPoll(); });
    }
    function startPushPoll() {
        stopPushPoll();
        pushTimer = setInterval(pollPush, 1500);
        pollPush();
    }
    function confirmText(s) {
        const by = Object.keys(s.by_pct || {}).map(function(p) { return (p === '0' ? 'restore to base' : p + '%') + ' × ' + s.by_pct[p]; }).join(', ');
        const sk = s.skipped || {};
        return 'Push to Amazon?\n\n'
            + s.to_push + ' SKU price(s) will change on live listings:\n'
            + '  • ' + s.discounts + ' discounted (' + by + ')\n'
            + '  • ' + s.restores + ' put back to base (their rule is now 0%)\n'
            + '  • Biggest discount: ' + s.max_pct + '%\n\n'
            + 'Left alone: ' + ((sk.unchanged || 0) + (sk.already_lower || 0)) + ' already at or below the coupon price, '
            + (sk.no_coupon || 0) + ' with no coupon, '
            + (sk.one_per_day || 0) + ' already changed today, '
            + ((sk.no_base || 0) + (sk.no_datasheet || 0) + (sk.negative_snroi || 0) + (sk.invalid || 0)) + ' skipped for other reasons.\n\n'
            + 'Amazon cannot create Seller Central coupons through its API, so each coupon is applied as the listing price × (1 − %).';
    }
    function pushToAmazon() {
        if (pushBusy) return;
        pushBusy = true;
        $('#' + prefix + '-push').prop('disabled', true);
        $('#' + prefix + '-status').text('Saving rules and checking what would change…');
        flushSave(function(saved) {
            if (!saved) { pushBusy = false; $('#' + prefix + '-push').prop('disabled', false); return; }
            $.get(pushUrls.preview).done(function(res) {
                if (!res || !res.success) {
                    $('#' + prefix + '-status').text((res && res.message) || 'Could not check the push.');
                    return;
                }
                const s = res.summary;
                if (!s.to_push) {
                    $('#' + prefix + '-status').text('Nothing to push: every SKU is already at its rule price or has no coupon.');
                    return;
                }
                if (!window.confirm(confirmText(s))) {
                    $('#' + prefix + '-status').text('Push cancelled. Nothing was sent to Amazon.');
                    return;
                }
                $('#' + prefix + '-status').text('Starting push…');
                $.ajax({ url: pushUrls.start, method: 'POST', headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' } })
                    .done(function(r) {
                        $('#' + prefix + '-status').text('Push started in the background; you can close this window.');
                        paintPush(r);
                        startPushPoll();
                    })
                    .fail(function(xhr) {
                        $('#' + prefix + '-status').text(errMsg(xhr, 'Could not start the push.'));
                        if (xhr.status === 409) startPushPoll();
                    });
            }).fail(function(xhr) {
                $('#' + prefix + '-status').text(errMsg(xhr, 'Could not check the push.'));
            }).always(function() {
                pushBusy = false;
                $('#' + prefix + '-push').prop('disabled', false);
            });
        });
    }
    $('#' + prefix + '-push').on('click', pushToAmazon);
    $('#' + prefix + '-push-cancel').on('click', function() {
        if (!window.confirm('Cancel the push? SKUs already pushed stay pushed.')) return;
        $.ajax({ url: pushUrls.cancel, method: 'POST', headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' } })
            .done(function(r) { paintPush(r); stopPushPoll(); });
    });
    $('#' + prefix + '-modal').on('shown.bs.modal', function() {
        // Resume the progress display if a push is running (or just finished).
        $.get(pushUrls.status).done(function(res) {
            paintPush(res);
            if (res.active) startPushPoll();
        });
    }).on('hidden.bs.modal', stopPushPoll);

    $('#' + prefix + '-btn').on('click', function() {
        if (state.ready) { openModal(); return; }
        afterLoad = openModal;
        load();
    });
    $('#' + prefix + '-add').on('click', function() {
        state.slabs = readSlabs();
        const last = state.slabs[state.slabs.length - 1] || { dmax: 0, pmin: 0, pmax: 99999 };
        const dmin = num(last.dmax);
        state.slabs.push({ dmin: dmin, dmax: r2(dmin + 10), pmin: 0, pmax: 99999, pct: 0 });
        paintSlabs();
        scheduleSave();
    });
    $('#' + prefix + '-modal').on('change', '.amz-dmin, .amz-dmax, .amz-pmin, .amz-pmax, .amz-pct', function() {
        if ($(this).hasClass('amz-pct')) $(this).val(couponPct($(this).val()));
        applyFromModal();
    });
    $('#' + prefix + '-modal').on('click', '.amz-del', function() {
        state.slabs = readSlabs();
        if (state.slabs.length <= 1) return;
        state.slabs.splice(Number($(this).attr('data-i')) || 0, 1);
        paintSlabs();
        scheduleSave();
    });
    $('#' + prefix + '-modal').on('click', '.el30-slab-dot', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const i = Number($(this).attr('data-i'));
        histOpenIdx = (histOpenIdx === i && $('#' + prefix + '-hist').hasClass('show')) ? null : i;
        drawHist();
        if (histOpenIdx !== null) {
            const el = document.getElementById(prefix + '-hist');
            if (el && el.scrollIntoView) el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    });
    $('#' + prefix + '-hist').on('change', '.el30cpn-hist-range', drawHist);
    $('#' + prefix + '-hist').on('click', '.el30cpn-x', function() { histOpenIdx = null; drawHist(); });
    $('#' + prefix + '-modal').on('hidden.bs.modal', function() {
        histOpenIdx = null;
        $('#' + prefix + '-hist').removeClass('show');
    });
})();
@endif
