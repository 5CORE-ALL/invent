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
@endif

@if($cvrUpDnPart === 'buttons' || $cvrUpDnPart === 'all')
                    <button type="button" class="btn btn-sm" id="cvr-up-dn-btn"
                        title="CVR 30 vs CVR 45: drop adds discount, up reduces discount. Fills CVR Up/Dn and T Discounts.">
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
                    <p class="small text-muted mb-2">
                        Compare <strong>CVR 30</strong> vs <strong>CVR 45</strong>.
                        A drop fills <strong>CVR Up/Dn</strong> with extra discount;
                        an increase reduces it. That value is added to <strong>T Discounts</strong>.
                        First rules: drop → <strong>+3</strong>, up → <strong>−3</strong>.
                        <strong>Down is ignored</strong> when CVR 30 is
                        <span style="color:#28a745;font-weight:700;">Green (7–13%)</span> or
                        <span style="color:#e83e8c;font-weight:700;">Pink (&gt; 13%)</span>.
                        <strong>UP is ignored</strong> when CVR 30 is
                        <span style="color:#a00211;font-weight:700;">Red (0–7%)</span>.
                        Add more rows for larger changes.
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
                        title="Save CVR UP/DN rules and refresh CVR Up/Dn + T Discounts">
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
            const recent = cvrUpDnRound2(cvrUpDnRecent(d));
            const prev = cvrUpDnRound2(cvrUpDnPrev(d));
            const delta = cvrUpDnRound2(recent - prev);
            const forceDown = recent === 0;
            if (!forceDown && delta === 0) {
                return { pct: 0, recent: recent, prev: prev, delta: 0, dir: 'flat', tip: 'CVR 30 ' + recent + '% = CVR 45 ' + prev + '%' };
            }
            const mag = Math.abs(delta);
            const dir = forceDown || delta < 0 ? 'down' : 'up';
            const ignoreDown = dir === 'down' && recent >= 7;
            const ignoreUp = dir === 'up' && recent >= 0 && recent <= 7;
            if (ignoreDown) {
                const band = recent > 13 ? 'Pink (> 13%)' : 'Green (7–13%)';
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
                return {
                    pct: 0,
                    recent: recent,
                    prev: prev,
                    delta: delta,
                    dir: 'flat',
                    tip: 'CVR 30 ' + recent + '% is Red (0–7%) — UP rule ignored',
                };
            }
            const pct = cvrUpDnRound2(cvrUpDnMatchDisc(mag, cvrUpDnRules[dir]));
            const tip = forceDown
                ? 'CVR 30 is 0 → Down → ' + (pct > 0 ? '+' : '') + pct
                : ('CVR 30 ' + recent + '% vs CVR 45 ' + prev + '% → '
                    + (dir === 'down' ? 'drop ' : 'up ') + mag + ' pts → '
                    + (pct > 0 ? '+' : '') + pct);
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
                $('#cvr-up-dn-status').text('Applied. CVR Up/Dn and T Discounts updated.');
                cvrUpDnToast('success', 'CVR UP/DN applied');
                const modalEl = document.getElementById('cvrUpDnModal');
                if (modalEl && window.bootstrap) bootstrap.Modal.getOrCreateInstance(modalEl).hide();
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
                headerTooltip: 'CVR 30 vs CVR 45. Drop → extra discount. Up → reduce discount. Added to T Discounts.',
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
                headerTooltip: 'Total discount including CVR Up/Dn.',
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

        $(function() {
            $('#cvr-up-dn-btn').off('click.cvrupdn').on('click.cvrupdn', function(e) {
                e.preventDefault();
                const modalEl = document.getElementById('cvrUpDnModal');
                if (!modalEl) return;
                renderCvrUpDnModalTable();
                loadCvrUpDnRules();
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
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
