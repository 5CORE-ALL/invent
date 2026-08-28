{{--
  CVR UP/DN rules + column for Amz / eBay / Temu analytics.
  Compare CVR 30 vs CVR 45. Drop → extra discount. Up → reduce discount.
  Include: css | buttons | modals | script | all  + cvrUpDnChannel.
--}}
@php
    $cvrUpDnPart = $cvrUpDnPart ?? 'all';
    $cvrUpDnChannel = $cvrUpDnChannel ?? 'amazon';
@endphp

@if($cvrUpDnPart === 'css' || $cvrUpDnPart === 'all')
        .tabulator-row .tabulator-cell[tabulator-field="cvr_up_dn"],
        .tabulator-row .tabulator-cell[tabulator-field="t_discounts"] {
            padding: 2px 4px !important;
        }
        #cvr-up-dn-btn {
            background: #6366f1;
            border-color: #6366f1;
            color: #fff;
        }
        #cvr-up-dn-btn:hover,
        #cvr-up-dn-btn:focus {
            background: #4f46e5;
            border-color: #4338ca;
            color: #fff;
        }
        .cvr-up-dn-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 12px;
            line-height: 1.2;
            white-space: nowrap;
        }
        .cvr-up-dn-badge.is-up { color: #28a745; }
        .cvr-up-dn-badge.is-down { color: #a00211; }
        .cvr-up-dn-badge.is-zero { color: #ffc107; font-weight: 600; }
        .cvr-up-dn-badge i { font-size: 11px; }
        .cvr-up-dn-pane {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 8px;
            min-height: 100%;
        }
        .cvr-up-dn-pane.is-down {
            background: #f0fdf4;
            border-color: #86efac;
        }
        .cvr-up-dn-pane.is-up {
            background: #fef2f2;
            border-color: #fecaca;
        }
        .cvr-up-dn-pane h6 {
            font-size: 13px;
            font-weight: 700;
            margin: 0 0 6px;
        }
        .cvr-up-dn-pane.is-down h6 { color: #15803d; }
        .cvr-up-dn-pane.is-up h6 { color: #b91c1c; }
        #cvrUpDnModal .cvr-up-dn-input {
            max-width: 78px;
            margin-left: auto;
            text-align: right;
            font-weight: 600;
        }
        #cvrUpDnModal .cvr-up-dn-row-del {
            border: none;
            background: none;
            color: #dc3545;
            padding: 0 4px;
            line-height: 1;
            cursor: pointer;
        }
        #cvrUpDnModal .cvr-up-dn-add-btn {
            font-size: 12px;
            font-weight: 600;
        }
        .cvr-up-dn-pie-wrap {
            display: flex;
            align-items: center;
            gap: 14px;
            margin: 0 0 10px;
            padding: 8px 10px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #f8fafc;
        }
        .cvr-up-dn-pie-canvas-wrap {
            width: 148px;
            height: 148px;
            flex: 0 0 148px;
        }
        .cvr-up-dn-pie-legend {
            flex: 1 1 auto;
            min-width: 0;
        }
        .cvr-up-dn-pie-row {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            line-height: 1.35;
            padding: 3px 0;
        }
        .cvr-up-dn-pie-swatch {
            width: 10px;
            height: 10px;
            border-radius: 2px;
            flex: 0 0 10px;
        }
        .cvr-up-dn-pie-name { flex: 1 1 auto; font-weight: 600; color: #334155; }
        .cvr-up-dn-pie-count { font-weight: 700; min-width: 28px; text-align: right; }
        .cvr-up-dn-pie-pct { color: #64748b; min-width: 28px; text-align: right; }
        .cvr-up-dn-hist-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            border: none;
            padding: 0;
            cursor: pointer;
            flex: 0 0 8px;
            box-shadow: 0 0 0 1px rgba(15, 23, 42, 0.12);
        }
        .cvr-up-dn-hist-dot:hover { transform: scale(1.35); }
        .cvr-up-dn-hist-wrap {
            display: none;
            margin: 0 0 10px;
            padding: 6px 8px 4px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #fff;
        }
        .cvr-up-dn-hist-wrap.is-open { display: block; }
        .cvr-up-dn-hist-canvas-wrap { height: 160px; }
@endif

@if($cvrUpDnPart === 'buttons' || $cvrUpDnPart === 'all')
                    <button type="button" class="btn btn-sm" id="cvr-up-dn-btn"
                        title="CVR 30 vs CVR 45: drop adds discount, up reduces discount. Apply writes S PRC on matching SKUs (with PRMT, CVR Disc, 0 Sold).">
                        CVR UP/DN
                    </button>
@endif

@if($cvrUpDnPart === 'modals' || $cvrUpDnPart === 'all')
    <div class="modal fade" id="cvrUpDnModal" tabindex="-1" aria-labelledby="cvrUpDnModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title fs-6" id="cvrUpDnModalLabel">CVR UP/DN</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-2">
                    <div class="cvr-up-dn-pie-wrap">
                        <div class="cvr-up-dn-pie-canvas-wrap">
                            <canvas id="cvr-up-dn-pie"></canvas>
                        </div>
                        <div class="cvr-up-dn-pie-legend" id="cvr-up-dn-pie-legend"></div>
                    </div>
                    <div class="cvr-up-dn-hist-wrap" id="cvr-up-dn-hist-wrap">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small fw-semibold" id="cvr-up-dn-hist-title">CVR history</span>
                            <button type="button" class="btn-close" id="cvr-up-dn-hist-close" aria-label="Close history" style="font-size:10px;"></button>
                        </div>
                        <div class="cvr-up-dn-hist-canvas-wrap">
                            <canvas id="cvr-up-dn-hist"></canvas>
                        </div>
                    </div>
                    <p class="small text-muted mb-2">
                        Compare <strong>CVR 30</strong> vs <strong>CVR 45</strong>.
                        A drop fills <strong>CVR Up/Dn</strong> with extra discount;
                        an increase reduces it. That value is added to <strong>T Discounts</strong>.
                        First rules: drop → <strong>+3</strong>, up → <strong>−3</strong>.
                        <strong>Down is ignored</strong> when CVR 30 is
                        <strong>0</strong>,
                        <span style="color:#28a745;font-weight:700;">Green (7–13%)</span> or
                        <span style="color:#e83e8c;font-weight:700;">Pink (&gt; 13%)</span>.
                        <strong>UP is ignored</strong> when CVR 30 is
                        <span style="color:#a00211;font-weight:700;">Red (0–7%)</span> or
                        <span style="color:#28a745;font-weight:700;">Green (7–13%)</span>.
                        Add more rows for larger changes.
                        <strong>Apply</strong> saves these rules, then writes <strong>S PRC</strong>
                        on matching SKUs using that SKU’s PRMT, CVR Disc, 0 Sold GROI, and CVR UP/DN.
                    </p>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <div class="cvr-up-dn-pane is-down">
                                <h6>Down</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered align-middle mb-2" id="cvr-up-dn-down-table">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Change ≥</th>
                                                <th class="text-end">Disc</th>
                                                <th style="width:28px;"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="cvr-up-dn-down-tbody"></tbody>
                                    </table>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-success cvr-up-dn-add-btn" id="cvr-up-dn-add-down">
                                    + Add
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="cvr-up-dn-pane is-up">
                                <h6>UP</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered align-middle mb-2" id="cvr-up-dn-up-table">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Change ≥</th>
                                                <th class="text-end">Disc</th>
                                                <th style="width:28px;"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="cvr-up-dn-up-tbody"></tbody>
                                    </table>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-danger cvr-up-dn-add-btn" id="cvr-up-dn-add-up">
                                    + Add
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="small text-muted mt-2" id="cvr-up-dn-status"></div>
                </div>
                <div class="modal-footer py-2 flex-wrap gap-1">
                    <button type="button" class="btn btn-sm btn-primary" id="cvr-up-dn-apply-btn"
                        title="Save CVR UP/DN rules and write S PRC on matching SKUs (PRMT + CVR Disc + 0 Sold + UP/DN)">
                        Apply
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif

@if($cvrUpDnPart === 'script' || $cvrUpDnPart === 'all')
        const CVR_UP_DN_CHANNEL = @json($cvrUpDnChannel);
        const CVR_UP_DN_RULES_URL = '/channel-promo-pricing/' + CVR_UP_DN_CHANNEL + '/cvr-up-dn';
        const CVR_UP_DN_FIELDS = {
            amazon: { recent: ['CVR_L30'], prev: ['CVR_L45'], prev2: ['CVR_L60'], inv: ['INV'] },
            ebay1: { recent: ['SCVR'], prev: ['CVR_45'], prev2: ['CVR_60'], inv: ['INV'] },
            temu: { recent: ['cvr_30', 'cvr_percent'], prev: ['cvr_45'], prev2: [], inv: ['inventory', 'INV'] },
            temu2: { recent: ['cvr_30', 'cvr_percent'], prev: ['cvr_45'], prev2: [], inv: ['inventory', 'INV'] },
        };
        const CVR_UP_DN_DEFAULTS = { down: [{ min: 0, disc: 3 }], up: [{ min: 0, disc: -3 }] };
        let cvrUpDnRules = {
            down: CVR_UP_DN_DEFAULTS.down.map(function(r) { return Object.assign({}, r); }),
            up: CVR_UP_DN_DEFAULTS.up.map(function(r) { return Object.assign({}, r); }),
        };

        function cvrUpDnCsrf() {
            return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        }
        function cvrUpDnRound2(n) {
            return Math.round((Number(n) || 0) * 100) / 100;
        }
        function cvrUpDnToast(type, msg) {
            if (typeof amzPefToast === 'function') amzPefToast(type, msg);
            else if (typeof chPromoToast === 'function') chPromoToast(type, msg);
            else if (typeof showToast === 'function') showToast(type, msg);
            else if (typeof toast === 'function') toast(msg, type);
            else console.log(type, msg);
        }
        function cvrUpDnEscAttr(s) {
            return String(s == null ? '' : s)
                .replace(/&/g, '&amp;')
                .replace(/"/g, '&quot;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
        }
        function cvrUpDnFieldMap() {
            return CVR_UP_DN_FIELDS[CVR_UP_DN_CHANNEL] || CVR_UP_DN_FIELDS.amazon;
        }
        function cvrUpDnReadNum(d, keys) {
            if (!d || !keys) return NaN;
            const list = Array.isArray(keys) ? keys : [keys];
            for (let i = 0; i < list.length; i++) {
                const raw = d[list[i]];
                if (raw === '' || raw == null) continue;
                const n = Number(raw);
                if (isFinite(n)) return n;
            }
            return NaN;
        }
        function cvrUpDnRatioPct(sold, views) {
            const s = Number(sold) || 0;
            const v = Number(views) || 0;
            return v > 0 ? (s / v) * 100 : 0;
        }
        /** Amazon stores CVR_L30 / CVR_L45 as empty — same math as the CVR columns. */
        function cvrUpDnAmazonRecent(d) {
            const stored = cvrUpDnReadNum(d, ['CVR_L30']);
            if (isFinite(stored) && stored !== 0) return stored;
            return cvrUpDnRatioPct(d.A_L30, d.Sess30);
        }
        function cvrUpDnAmazonPrev(d) {
            const stored = cvrUpDnReadNum(d, ['CVR_L45']);
            if (isFinite(stored) && stored !== 0) return stored;
            const aL30 = Number(d.A_L30) || 0;
            const sess30 = Number(d.Sess30) || 0;
            const aL60 = Number(d.units_ordered_l60) || 0;
            const sess60 = Number(d.sessions_l60) || 0;
            return cvrUpDnRatioPct((aL30 + aL60) / 2, (sess30 + sess60) / 2);
        }
        function cvrUpDnRecent(d) {
            if (CVR_UP_DN_CHANNEL === 'amazon') return cvrUpDnAmazonRecent(d);
            const map = cvrUpDnFieldMap();
            const stored = cvrUpDnReadNum(d, map.recent);
            return isFinite(stored) ? stored : 0;
        }
        function cvrUpDnPrev(d) {
            if (CVR_UP_DN_CHANNEL === 'amazon') return cvrUpDnAmazonPrev(d);
            const map = cvrUpDnFieldMap();
            let stored = cvrUpDnReadNum(d, map.prev);
            if (!isFinite(stored)) stored = cvrUpDnReadNum(d, map.prev2);
            return isFinite(stored) ? stored : 0;
        }
        function cvrUpDnInv(d) {
            const n = cvrUpDnReadNum(d, cvrUpDnFieldMap().inv);
            return isFinite(n) ? n : 0;
        }
        function cvrUpDnCloneRules(src) {
            const rules = src && typeof src === 'object' ? src : CVR_UP_DN_DEFAULTS;
            return {
                down: (rules.down || []).map(function(r) { return { min: Number(r.min) || 0, disc: Number(r.disc) || 0 }; }),
                up: (rules.up || []).map(function(r) { return { min: Number(r.min) || 0, disc: Number(r.disc) || 0 }; }),
            };
        }
        function cvrUpDnMatchDisc(mag, sideRules) {
            const rows = Array.isArray(sideRules) ? sideRules : [];
            let best = null;
            for (let i = 0; i < rows.length; i++) {
                const min = Number(rows[i].min) || 0;
                if (mag + 1e-9 >= min && (!best || min >= best.min)) {
                    best = rows[i];
                }
            }
            if (!best) return 0;
            const disc = Number(best.disc);
            return isFinite(disc) ? disc : 0;
        }
        function computeCvrUpDnDetail(d) {
            const empty = { pct: 0, recent: 0, prev: 0, delta: 0, dir: 'flat', tip: 'No CVR change' };
            if (!d || d.is_parent_summary) return empty;
            if (cvrUpDnInv(d) <= 0) {
                return { pct: 0, recent: 0, prev: 0, delta: 0, dir: 'flat', tip: 'INV = 0 — CVR UP/DN not applied' };
            }
            const recent = cvrUpDnRound2(cvrUpDnRecent(d));
            const prev = cvrUpDnRound2(cvrUpDnPrev(d));
            const delta = cvrUpDnRound2(recent - prev);
            if (recent === 0) {
                return {
                    pct: 0,
                    recent: recent,
                    prev: prev,
                    delta: delta,
                    dir: 'flat',
                    tip: 'CVR 30 is 0 — Down rule not applied',
                };
            }
            if (delta === 0) {
                return { pct: 0, recent: recent, prev: prev, delta: 0, dir: 'flat', tip: 'CVR 30 ' + recent + '% = CVR 45 ' + prev + '%' };
            }
            const mag = Math.abs(delta);
            const dir = delta < 0 ? 'down' : 'up';
            // CVR 0 → ignore Down. Red [0, 7) / Green [7, 13] → ignore UP. Green [7, 13] / Pink > 13 → ignore Down.
            const ignoreDown = dir === 'down' && (recent === 0 || recent >= 7);
            const ignoreUp = dir === 'up' && recent >= 0 && recent <= 13;
            if (ignoreDown) {
                const band = recent === 0 ? '0' : (recent > 13 ? 'Pink (> 13%)' : 'Green (7–13%)');
                return {
                    pct: 0,
                    recent: recent,
                    prev: prev,
                    delta: delta,
                    dir: 'flat',
                    tip: 'CVR 30 ' + recent + '% is ' + band + ' — Down rule ignored',
                };
            }
            if (ignoreUp) {
                const band = recent >= 7 ? 'Green (7–13%)' : 'Red (0–7%)';
                return {
                    pct: 0,
                    recent: recent,
                    prev: prev,
                    delta: delta,
                    dir: 'flat',
                    tip: 'CVR 30 ' + recent + '% is ' + band + ' — UP rule ignored',
                };
            }
            const pct = cvrUpDnRound2(cvrUpDnMatchDisc(mag, cvrUpDnRules[dir]));
            const tip = 'CVR 30 ' + recent + '% vs CVR 45 ' + prev + '% → '
                + (dir === 'down' ? 'drop ' : 'up ') + mag + ' pts → '
                + (pct > 0 ? '+' : '') + pct;
            return { pct: pct, recent: recent, prev: prev, delta: delta, dir: dir, tip: tip };
        }
        function computeCvrUpDnPct(d) {
            return computeCvrUpDnDetail(d).pct;
        }
        function fmtCvrUpDnBadge(pct, dir) {
            const n = Number(pct);
            if (!isFinite(n) || n === 0 || dir === 'flat') {
                return '<span class="cvr-up-dn-badge is-zero" title="Same">'
                    + '<i class="fas fa-minus"></i></span>';
            }
            if (dir === 'down') {
                return '<span class="cvr-up-dn-badge is-down">'
                    + '<i class="fas fa-arrow-down"></i> '
                    + (n > 0 ? '+' : '') + n + '</span>';
            }
            return '<span class="cvr-up-dn-badge is-up">'
                + '<i class="fas fa-arrow-up"></i> '
                + (n > 0 ? '+' : '') + n + '</span>';
        }
        function cvrUpDnReadModalSide(side) {
            const rows = [];
            $('#cvr-up-dn-' + side + '-tbody tr').each(function() {
                const min = parseFloat($(this).find('.cvr-up-dn-min').val());
                const disc = parseFloat($(this).find('.cvr-up-dn-disc').val());
                rows.push({
                    min: isFinite(min) && min >= 0 ? min : 0,
                    disc: isFinite(disc) ? disc : 0,
                });
            });
            return rows.length ? rows : CVR_UP_DN_DEFAULTS[side].map(function(r) { return Object.assign({}, r); });
        }
        function cvrUpDnReadModalRules() {
            return {
                down: cvrUpDnReadModalSide('down'),
                up: cvrUpDnReadModalSide('up'),
            };
        }
        function cvrUpDnRenderSide(side, rows) {
            const $tb = $('#cvr-up-dn-' + side + '-tbody').empty();
            const list = (rows && rows.length) ? rows : CVR_UP_DN_DEFAULTS[side];
            list.forEach(function(r, idx) {
                $tb.append(
                    '<tr>'
                    + '<td><input type="number" step="0.01" min="0" class="form-control form-control-sm cvr-up-dn-input cvr-up-dn-min" value="'
                    + cvrUpDnEscAttr(r.min) + '"></td>'
                    + '<td><input type="number" step="0.01" class="form-control form-control-sm cvr-up-dn-input cvr-up-dn-disc" value="'
                    + cvrUpDnEscAttr(r.disc) + '"></td>'
                    + '<td class="text-center"><button type="button" class="cvr-up-dn-row-del" data-side="' + side
                    + '" data-idx="' + idx + '" title="Remove row">&times;</button></td>'
                    + '</tr>'
                );
            });
        }
        function renderCvrUpDnModalTable() {
            cvrUpDnRenderSide('down', cvrUpDnRules.down);
            cvrUpDnRenderSide('up', cvrUpDnRules.up);
        }
        function cvrUpDnAddRow(side) {
            const rows = cvrUpDnReadModalSide(side);
            const last = rows[rows.length - 1] || { min: 0, disc: side === 'down' ? 3 : -3 };
            rows.push({ min: cvrUpDnRound2((Number(last.min) || 0) + 1), disc: Number(last.disc) || 0 });
            cvrUpDnRules[side] = rows;
            cvrUpDnRenderSide(side, rows);
        }
        function cvrUpDnRemoveRow(side, idx) {
            const rows = cvrUpDnReadModalSide(side);
            if (rows.length <= 1) {
                cvrUpDnToast('error', 'Keep at least one ' + (side === 'down' ? 'Down' : 'UP') + ' rule');
                return;
            }
            rows.splice(idx, 1);
            cvrUpDnRules[side] = rows;
            cvrUpDnRenderSide(side, rows);
        }
        function loadCvrUpDnRules() {
            $('#cvr-up-dn-status').text('Loading…');
            return $.ajax({
                url: CVR_UP_DN_RULES_URL,
                method: 'GET',
                headers: { 'Accept': 'application/json' },
                timeout: 12000,
            }).done(function(res) {
                if (res && res.rules) cvrUpDnRules = cvrUpDnCloneRules(res.rules);
                renderCvrUpDnModalTable();
                $('#cvr-up-dn-status').text(res && res.is_default
                    ? 'Using first rules: drop → +3, up → −3.'
                    : 'Loaded saved CVR UP/DN rules.');
            }).fail(function() {
                renderCvrUpDnModalTable();
                $('#cvr-up-dn-status').text('Using first rules: drop → +3, up → −3.');
            });
        }
        function saveCvrUpDnRules(rules) {
            return $.ajax({
                url: CVR_UP_DN_RULES_URL,
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': cvrUpDnCsrf(), 'Accept': 'application/json' },
                data: { rules: rules, _token: cvrUpDnCsrf() },
                timeout: 20000,
            }).done(function(res) {
                if (res && res.rules) cvrUpDnRules = cvrUpDnCloneRules(res.rules);
            });
        }
        function cvrUpDnRedrawColumns() {
            if (typeof table === 'undefined' || !table) return;
            try { table.redraw(true); } catch (e) { /* ignore */ }
        }
        function saveAndApplyCvrUpDn() {
            const rules = cvrUpDnReadModalRules();
            cvrUpDnRules = cvrUpDnCloneRules(rules);
            cvrUpDnRedrawColumns();
            const $btn = $('#cvr-up-dn-apply-btn');
            const html = $btn.html();
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Applying…');
            saveCvrUpDnRules(rules).done(function() {
                $('#cvr-up-dn-status').text('Rules saved. Applying to matching SKUs…');
                if (typeof window.applyCvrUpDnToMatchingSkus === 'function') {
                    Promise.resolve(window.applyCvrUpDnToMatchingSkus()).then(function() {
                        const modalEl = document.getElementById('cvrUpDnModal');
                        if (modalEl && window.bootstrap) bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                    });
                } else {
                    cvrUpDnToast('success', 'CVR UP/DN rules saved');
                    const modalEl = document.getElementById('cvrUpDnModal');
                    if (modalEl && window.bootstrap) bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                }
            }).fail(function() {
                $('#cvr-up-dn-status').text('Rules applied on this page. Save to server failed.');
                cvrUpDnToast('error', 'Applied on page, but save failed');
            }).always(function() {
                $btn.prop('disabled', false).html(html);
            });
        }
        function cvrUpDnColumn() {
            return {
                title: 'CVR Up/Dn',
                field: 'cvr_up_dn',
                width: 86,
                hozAlign: 'center',
                vertAlign: 'middle',
                headerSort: true,
                headerTooltip: 'CVR 30 vs CVR 45. Drop → extra discount. Up → reduce discount. CVR 30 = 0 → Down does not apply. Added to T Discounts.',
                sorter: function(a, b, aRow, bRow) {
                    const av = computeCvrUpDnPct(aRow.getData()) || 0;
                    const bv = computeCvrUpDnPct(bRow.getData()) || 0;
                    return av - bv;
                },
                formatter: function(cell) {
                    const d = cell.getRow().getData() || {};
                    if (d.is_parent_summary) return '';
                    const detail = computeCvrUpDnDetail(d);
                    return '<span title="' + cvrUpDnEscAttr(detail.tip) + '">'
                        + fmtCvrUpDnBadge(detail.pct, detail.dir) + '</span>';
                },
            };
        }
        function tDiscountsColumn(totalFn) {
            return {
                title: 'T Discounts',
                field: 't_discounts',
                width: 92,
                hozAlign: 'center',
                vertAlign: 'middle',
                headerSort: true,
                headerTooltip: 'PRMT + CVR Disc + CVR UP/DN. 0 Sold Sale uses GROI, not this %.',
                sorter: function(a, b, aRow, bRow) {
                    const fn = typeof totalFn === 'function' ? totalFn : function() { return 0; };
                    return (Number(fn(aRow.getData())) || 0) - (Number(fn(bRow.getData())) || 0);
                },
                formatter: function(cell) {
                    const d = cell.getRow().getData() || {};
                    if (d.is_parent_summary) return '';
                    const fn = typeof totalFn === 'function' ? totalFn : function() { return 0; };
                    const n = Number(fn(d));
                    const shown = isFinite(n) ? cvrUpDnRound2(n) : 0;
                    const adj = computeCvrUpDnPct(d);
                    const tip = 'Includes CVR Up/Dn ' + (adj > 0 ? '+' : '') + adj;
                    const cls = shown === 0 ? 'cvr-up-dn-badge is-zero' : 'cvr-up-dn-badge is-down';
                    return '<span class="' + cls + '" title="' + cvrUpDnEscAttr(tip) + '">'
                        + (shown === 0 ? '—' : shown) + '</span>';
                },
            };
        }

        window.computeCvrUpDnPct = computeCvrUpDnPct;
        window.computeCvrUpDnDetail = computeCvrUpDnDetail;
        window.cvrUpDnColumn = cvrUpDnColumn;
        window.tDiscountsColumn = tDiscountsColumn;
        window.loadCvrUpDnRules = loadCvrUpDnRules;

        const CVR_UP_DN_BANDS = [
            { key: 'red', label: 'Red 0–7', color: '#a00211' },
            { key: 'green', label: 'Green 7–13', color: '#28a745' },
            { key: 'pink', label: 'Pink > 13', color: '#e83e8c' },
        ];
        let cvrUpDnPieChart = null;
        let cvrUpDnHistChart = null;
        let cvrUpDnLiveCounts = { red: 0, green: 0, pink: 0 };

        function cvrUpDnBandOf(cvr) {
            const n = Number(cvr);
            if (!isFinite(n) || n < 7) return 'red';
            if (n <= 13) return 'green';
            return 'pink';
        }
        function cvrUpDnCollectBandCounts() {
            const counts = { red: 0, green: 0, pink: 0 };
            if (typeof table === 'undefined' || !table || typeof table.getRows !== 'function') {
                return counts;
            }
            table.getRows('active').forEach(function(row) {
                const d = row.getData() || {};
                if (d.is_parent_summary) return;
                if (cvrUpDnInv(d) <= 0) return;
                counts[cvrUpDnBandOf(cvrUpDnRecent(d))]++;
            });
            return counts;
        }
        function cvrUpDnSnapLocal(counts) {
            try {
                const key = 'cvr_up_dn_hist_' + CVR_UP_DN_CHANNEL;
                const today = new Date().toISOString().slice(0, 10);
                let hist = {};
                try { hist = JSON.parse(localStorage.getItem(key) || '{}') || {}; } catch (e) { hist = {}; }
                hist[today] = counts;
                const keys = Object.keys(hist).sort();
                while (keys.length > 90) {
                    delete hist[keys.shift()];
                }
                localStorage.setItem(key, JSON.stringify(hist));
            } catch (e) { /* ignore */ }
        }
        function cvrUpDnLocalHistory() {
            try {
                const hist = JSON.parse(localStorage.getItem('cvr_up_dn_hist_' + CVR_UP_DN_CHANNEL) || '{}') || {};
                return Object.keys(hist).sort().map(function(date) {
                    const row = hist[date] || {};
                    return {
                        date: date,
                        label: date.slice(5),
                        red: Number(row.red) || 0,
                        green: Number(row.green) || 0,
                        pink: Number(row.pink) || 0,
                    };
                });
            } catch (e) {
                return [];
            }
        }
        function cvrUpDnWithChart(fn) {
            if (typeof Chart !== 'undefined') { fn(); return; }
            if (typeof loadChartJs === 'function') {
                loadChartJs().then(fn).catch(function() {});
                return;
            }
            const s = document.createElement('script');
            s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.8/dist/chart.umd.min.js';
            s.onload = fn;
            document.head.appendChild(s);
        }
        function renderCvrUpDnPie() {
            cvrUpDnLiveCounts = cvrUpDnCollectBandCounts();
            cvrUpDnSnapLocal(cvrUpDnLiveCounts);
            const total = cvrUpDnLiveCounts.red + cvrUpDnLiveCounts.green + cvrUpDnLiveCounts.pink;
            const legend = document.getElementById('cvr-up-dn-pie-legend');
            if (legend) {
                legend.innerHTML = '<div class="cvr-up-dn-pie-row" style="color:#94a3b8;font-size:10px;font-weight:600;">'
                    + '<span class="cvr-up-dn-pie-swatch" style="visibility:hidden;"></span>'
                    + '<span class="cvr-up-dn-pie-name">CVR 30</span>'
                    + '<span class="cvr-up-dn-pie-count">count</span>'
                    + '<span class="cvr-up-dn-pie-pct">of total</span>'
                    + '<span class="cvr-up-dn-hist-dot" style="visibility:hidden;"></span>'
                    + '</div>'
                    + CVR_UP_DN_BANDS.map(function(b) {
                    const n = cvrUpDnLiveCounts[b.key] || 0;
                    const pct = total > 0 ? Math.round((n / total) * 100) : 0;
                    return '<div class="cvr-up-dn-pie-row">'
                        + '<span class="cvr-up-dn-pie-swatch" style="background:' + b.color + ';"></span>'
                        + '<span class="cvr-up-dn-pie-name">' + b.label + '</span>'
                        + '<span class="cvr-up-dn-pie-count">' + n + '</span>'
                        + '<span class="cvr-up-dn-pie-pct" title="' + pct + ' of total">' + pct + '</span>'
                        + '<button type="button" class="cvr-up-dn-hist-dot" data-band="' + b.key + '" '
                        + 'style="background:' + b.color + ';" title="' + b.label + ' daily history"></button>'
                        + '</div>';
                }).join('');
            }
            cvrUpDnWithChart(function() {
                const canvas = document.getElementById('cvr-up-dn-pie');
                if (!canvas || typeof Chart === 'undefined') return;
                if (cvrUpDnPieChart) {
                    cvrUpDnPieChart.destroy();
                    cvrUpDnPieChart = null;
                }
                cvrUpDnPieChart = new Chart(canvas.getContext('2d'), {
                    type: 'pie',
                    data: {
                        labels: CVR_UP_DN_BANDS.map(function(b) { return b.label; }),
                        datasets: [{
                            data: CVR_UP_DN_BANDS.map(function(b) { return cvrUpDnLiveCounts[b.key] || 0; }),
                            backgroundColor: CVR_UP_DN_BANDS.map(function(b) { return b.color; }),
                            borderColor: '#fff',
                            borderWidth: 1,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function(ctx) {
                                        const n = Number(ctx.raw) || 0;
                                        const pct = total > 0 ? Math.round((n / total) * 100) : 0;
                                        return ' ' + n + '  ·  ' + pct + ' of total';
                                    },
                                },
                            },
                        },
                    },
                });
            });
        }
        function cvrUpDnDrawHist(band, rows) {
            const spec = CVR_UP_DN_BANDS.find(function(b) { return b.key === band; }) || CVR_UP_DN_BANDS[0];
            $('#cvr-up-dn-hist-title').text(spec.label + ' count');
            $('#cvr-up-dn-hist-wrap').addClass('is-open');
            cvrUpDnWithChart(function() {
                const canvas = document.getElementById('cvr-up-dn-hist');
                if (!canvas || typeof Chart === 'undefined') return;
                if (cvrUpDnHistChart) {
                    cvrUpDnHistChart.destroy();
                    cvrUpDnHistChart = null;
                }
                const labels = rows.map(function(r) { return r.label || r.date; });
                const values = rows.map(function(r) { return Number(r[band]) || 0; });
                cvrUpDnHistChart = new Chart(canvas.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            data: values,
                            borderColor: spec.color,
                            backgroundColor: spec.color + '22',
                            fill: true,
                            tension: 0.3,
                            borderWidth: 1.5,
                            pointRadius: 3,
                            pointHoverRadius: 5,
                            pointBackgroundColor: spec.color,
                            pointBorderColor: spec.color,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            y: { beginAtZero: true, ticks: { font: { size: 9 }, precision: 0 } },
                            x: { ticks: { maxRotation: 45, minRotation: 45, font: { size: 9 } } },
                        },
                    },
                });
            });
        }
        function cvrUpDnOpenHist(band) {
            const applyToday = function(rows) {
                const list = rows.slice();
                const today = new Date().toISOString().slice(0, 10);
                if (!list.some(function(r) { return r.date === today; })) {
                    list.push({
                        date: today,
                        label: today.slice(5),
                        red: cvrUpDnLiveCounts.red,
                        green: cvrUpDnLiveCounts.green,
                        pink: cvrUpDnLiveCounts.pink,
                    });
                } else {
                    list.forEach(function(r) {
                        if (r.date === today) {
                            r.red = cvrUpDnLiveCounts.red;
                            r.green = cvrUpDnLiveCounts.green;
                            r.pink = cvrUpDnLiveCounts.pink;
                        }
                    });
                }
                return list;
            };
            if (CVR_UP_DN_CHANNEL === 'amazon') {
                $.ajax({
                    url: '/amazon-cvr-band-history',
                    method: 'GET',
                    data: { days: 30 },
                }).done(function(res) {
                    const rows = (res && res.success && Array.isArray(res.data)) ? res.data : cvrUpDnLocalHistory();
                    cvrUpDnDrawHist(band, applyToday(rows));
                }).fail(function() {
                    cvrUpDnDrawHist(band, applyToday(cvrUpDnLocalHistory()));
                });
                return;
            }
            cvrUpDnDrawHist(band, applyToday(cvrUpDnLocalHistory()));
        }

        $(function() {
            $('#cvr-up-dn-btn').off('click.cvrupdn').on('click.cvrupdn', function(e) {
                e.preventDefault();
                const modalEl = document.getElementById('cvrUpDnModal');
                if (!modalEl) return;
                renderCvrUpDnModalTable();
                loadCvrUpDnRules();
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            });
            $('#cvrUpDnModal').off('shown.bs.modal.cvrupdn').on('shown.bs.modal.cvrupdn', function() {
                renderCvrUpDnPie();
            });
            $('#cvrUpDnModal').off('hidden.bs.modal.cvrupdn').on('hidden.bs.modal.cvrupdn', function() {
                $('#cvr-up-dn-hist-wrap').removeClass('is-open');
                if (cvrUpDnPieChart) { cvrUpDnPieChart.destroy(); cvrUpDnPieChart = null; }
                if (cvrUpDnHistChart) { cvrUpDnHistChart.destroy(); cvrUpDnHistChart = null; }
            });
            $(document).off('click.cvrupdn', '.cvr-up-dn-hist-dot').on('click.cvrupdn', '.cvr-up-dn-hist-dot', function() {
                cvrUpDnOpenHist($(this).data('band') || 'red');
            });
            $('#cvr-up-dn-hist-close').off('click.cvrupdn').on('click.cvrupdn', function() {
                $('#cvr-up-dn-hist-wrap').removeClass('is-open');
            });
            $('#cvr-up-dn-apply-btn').off('click.cvrupdn').on('click.cvrupdn', saveAndApplyCvrUpDn);
            $('#cvr-up-dn-add-down').off('click.cvrupdn').on('click.cvrupdn', function() { cvrUpDnAddRow('down'); });
            $('#cvr-up-dn-add-up').off('click.cvrupdn').on('click.cvrupdn', function() { cvrUpDnAddRow('up'); });
            $(document).off('click.cvrupdn', '.cvr-up-dn-row-del').on('click.cvrupdn', '.cvr-up-dn-row-del', function() {
                cvrUpDnRemoveRow($(this).data('side'), Number($(this).data('idx')) || 0);
            });
            loadCvrUpDnRules().then(function() { cvrUpDnRedrawColumns(); }).catch(function() { /* defaults */ });
        });
@endif
