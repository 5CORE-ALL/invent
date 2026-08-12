{{--
  Dil vs PRMT / CVR vs CPN + PRMT% / CPN% / Appr / DSC% for Amazon tabulator.
  Same rules store + endpoints as /pricing-errors-fix (pef_dil_vs_prmt / pef_cvr_vs_cpn).
  Amazon path: discount SPRICE via /save-amazon-sprice (no eBay Marketing APIs).
--}}
@php $amazonPefPromoPart = $amazonPefPromoPart ?? 'all'; @endphp

@if($amazonPefPromoPart === 'css' || $amazonPefPromoPart === 'all')
        /* Dil vs PRMT / CVR vs CPN — same UX as /pricing-errors-fix */
        .amz-pef-promo-cell {
            font-size: inherit;
            font-weight: 600;
            color: #64748b;
        }
        .amz-pef-promo-cell.has-val { color: #0f172a; }
        .tabulator-row .tabulator-cell[tabulator-field="prmt_pct"],
        .tabulator-row .tabulator-cell[tabulator-field="cpn_pct"],
        .tabulator-row .tabulator-cell[tabulator-field="dsc"],
        .tabulator-row .tabulator-cell[tabulator-field="appr"] {
            padding: 2px 4px !important;
        }
        #pef-dil-prmt-table .pef-dil-prmt-input,
        #pef-cvr-cpn-table .pef-cvr-cpn-input {
            max-width: 90px;
            margin-left: auto;
            text-align: right;
            font-weight: 600;
        }
        #amz-dil-vs-prmt-btn {
            background: #20c997;
            border-color: #20c997;
            color: #fff;
        }
        #amz-dil-vs-prmt-btn:hover {
            background: #1aa179;
            border-color: #1aa179;
            color: #fff;
        }
        #amz-push-prmt-btn {
            background: #198754;
            border-color: #198754;
            color: #fff;
        }
        #amz-push-prmt-btn:hover {
            background: #157347;
            border-color: #146c43;
            color: #fff;
        }
        #amz-cvr-vs-cpn-btn {
            color: #20c997;
            border-color: #20c997;
            background: #fff;
        }
        #amz-cvr-vs-cpn-btn:hover {
            background: rgba(32, 201, 151, 0.08);
            color: #1aa179;
            border-color: #1aa179;
        }
        #amz-push-cpn-btn {
            color: #fff;
            border-color: #20c997;
            background: #20c997;
        }
        #amz-push-cpn-btn:hover {
            background: #1aa179;
            border-color: #1aa179;
            color: #fff;
        }
        /* CVR vs CPN modal — light purple background */
        #pefCvrVsCpnModal .modal-content {
            background: #f3e8ff;
            border-color: #e9d5ff;
        }
        #pefCvrVsCpnModal .modal-header,
        #pefCvrVsCpnModal .modal-footer {
            background: #f3e8ff;
            border-color: #e9d5ff;
        }
        #pefCvrVsCpnModal .modal-body {
            background: #f3e8ff;
        }
        #pefCvrVsCpnModal .table {
            background: #fff;
        }
        #pefCvrVsCpnModal .table thead.table-light th {
            background: #ede9fe;
        }
@endif

@if($amazonPefPromoPart === 'buttons' || $amazonPefPromoPart === 'all')
                    <button type="button" class="btn btn-sm" id="amz-dil-vs-prmt-btn"
                        title="Dil% slabs vs PRMT% rules — edit and apply as PRMT % (same store as pricing-errors-fix)">
                        <i class="fas fa-sliders-h"></i> Dil vs PRMT
                    </button>
                    <button type="button" class="btn btn-sm btn-success" id="amz-push-prmt-btn"
                        title="Apply Dil→PRMT rules, then push changed prices to Amazon via Listings API. Selected rows if checked; otherwise all visible. Skips unchanged prices.">
                        <i class="fas fa-upload"></i> Push Prmt%
                    </button>
                    <button type="button" class="btn btn-sm" id="amz-cvr-vs-cpn-btn"
                        title="CVR% slabs vs CPN% rules — edit and apply as CPN % (same store as pricing-errors-fix)">
                        CVR vs CPN
                    </button>
                    <button type="button" class="btn btn-sm" id="amz-push-cpn-btn"
                        title="Apply CVR→CPN rules (snap to Amazon coupons 5% / 10%, 1 coupon per day), then push changed prices via Amazon Listings API. Selected if checked; else all visible. Skips unchanged.">
                        <i class="fas fa-upload"></i> Push CPN%
                    </button>
@endif

@if($amazonPefPromoPart === 'modals' || $amazonPefPromoPart === 'all')
    {{-- CVR vs CPN: same model/datasource as /pricing-errors-fix --}}
    <div class="modal fade" id="pefCvrVsCpnModal" tabindex="-1" aria-labelledby="pefCvrVsCpnModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title fs-6" id="pefCvrVsCpnModalLabel">
                        <i class="fas fa-percentage me-1"></i> CVR vs CPN
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-2">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0" id="pef-cvr-cpn-table">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:55%;">CVR%</th>
                                    <th style="width:45%;" class="text-end">CPN %</th>
                                </tr>
                            </thead>
                            <tbody id="pef-cvr-cpn-tbody"></tbody>
                        </table>
                    </div>
                    <div class="small text-muted mt-2" id="pef-cvr-cpn-status"></div>
                </div>
                <div class="modal-footer py-2 flex-wrap gap-1">
                    <button type="button" class="btn btn-sm btn-primary" id="pef-cvr-cpn-apply-btn"
                        title="Save CVR→CPN rules, then apply CPN% — selected rows if checked, otherwise all visible">
                        Apply
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Dil vs PRMT: same model/datasource as /pricing-errors-fix --}}
    <div class="modal fade" id="pefDilVsPrmtModal" tabindex="-1" aria-labelledby="pefDilVsPrmtModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title fs-6" id="pefDilVsPrmtModalLabel">
                        <i class="fas fa-sliders-h me-1"></i> Dil vs PRMT
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-2">
                    <p class="small text-muted mb-2">
                        Map Dil% slabs to PRMT%. First-time defaults: <strong>&gt; 100% → 0</strong> up to
                        <strong>0–10% → 10</strong>. <strong>Apply</strong> saves rules (shared with pricing-errors-fix)
                        and fills <strong>PRMT %</strong> from each row’s Dil% / discounts <strong>S PRC</strong>.
                        If <strong>INV = 0</strong>, PRMT% is forced to <strong>0</strong>.
                    </p>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0" id="pef-dil-prmt-table">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:55%;">Dil%</th>
                                    <th style="width:45%;" class="text-end">PRMT %</th>
                                </tr>
                            </thead>
                            <tbody id="pef-dil-prmt-tbody"></tbody>
                        </table>
                    </div>
                    <div class="small text-muted mt-2" id="pef-dil-prmt-status"></div>
                </div>
                <div class="modal-footer py-2 flex-wrap gap-1">
                    <button type="button" class="btn btn-sm btn-primary" id="pef-dil-prmt-apply-btn"
                        title="Save Dil→PRMT rules, then apply PRMT% — selected rows if checked, otherwise all visible">
                        Apply
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif

@if($amazonPefPromoPart === 'script' || $amazonPefPromoPart === 'all')
        // ==================== Dil vs PRMT / CVR vs CPN (same as /pricing-errors-fix) ====================
        const PEF_DIL_PRMT_DEFAULTS = [
            { key: '0-10', label: '0–10%', prmt: 10 },
            { key: '10-20', label: '10–20%', prmt: 9 },
            { key: '20-30', label: '20–30%', prmt: 8 },
            { key: '30-40', label: '30–40%', prmt: 7 },
            { key: '40-50', label: '40–50%', prmt: 6 },
            { key: '50-60', label: '50–60%', prmt: 5 },
            { key: '60-70', label: '60–70%', prmt: 4 },
            { key: '70-80', label: '70–80%', prmt: 3 },
            { key: '80-90', label: '80–90%', prmt: 2 },
            { key: '90-100', label: '90–100%', prmt: 1 },
            { key: 'gt-100', label: '> 100%', prmt: 0 },
        ];
        const PEF_CVR_CPN_DEFAULTS = [
            { key: 'eq-0', label: '0%', cpn: 10 },
            { key: '0.01-1', label: '0.01–1%', cpn: 9 },
            { key: '1-1.5', label: '1–1.5%', cpn: 8 },
            { key: '1.5-2', label: '1.5–2%', cpn: 7 },
            { key: '2-3', label: '2–3%', cpn: 6 },
            { key: '3-4', label: '3–4%', cpn: 5 },
            { key: '4-5', label: '4–5%', cpn: 4 },
            { key: '5-6', label: '5–6%', cpn: 3 },
            { key: '6-6.5', label: '6–6.5%', cpn: 2 },
            { key: '6.5-7', label: '6.5–7%', cpn: 1 },
            { key: 'gt-7', label: '> 7%', cpn: 0 },
        ];

        let pefDilPrmtRules = PEF_DIL_PRMT_DEFAULTS.map(function(r) { return Object.assign({}, r); });
        let pefCvrCpnRules = PEF_CVR_CPN_DEFAULTS.map(function(r) { return Object.assign({}, r); });

        function amzPefCsrf() {
            return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        }
        function amzPefRound2(n) {
            return Math.round((Number(n) || 0) * 100) / 100;
        }
        function amzPefToast(type, msg) {
            if (typeof showToast === 'function') showToast(type, msg);
            else if (typeof toast === 'function') toast(msg, type);
            else console.log(type, msg);
        }
        function amzPefSku(d) {
            return String((d && (d['(Child) sku'] || d.sku)) || '').trim();
        }
        function amzPefIsChildRow(d) {
            return !!(d && !d.is_parent_summary && amzPefSku(d) && String(amzPefSku(d)).indexOf('PARENT') === -1);
        }
        function amzPefDil(d) {
            const inv = Number(d.INV) || 0;
            if (inv === 0) return 0;
            const ovl30 = Number(d['L30']) || 0;
            return (ovl30 / inv) * 100;
        }
        function amzPefCvr(d) {
            let cvr = Number(d.CVR_L30);
            if (isFinite(cvr) && cvr >= 0) return cvr;
            const views = Number(d.Sess30) || 0;
            const l30 = Number(d['A_L30'] != null ? d['A_L30'] : d['L30']) || 0;
            return views > 0 ? amzPefRound2((l30 / views) * 100) : 0;
        }
        function amzPefInv(d) {
            return Number(d.INV) || 0;
        }
        function parseAmzPefPercentAmount(raw) {
            const s = String(raw == null ? '' : raw).trim();
            if (!s) return null;
            const num = parseFloat(s.replace(/[%$,\s]/g, '').replace(',', '.'));
            if (!isFinite(num) || num === 0) return null;
            return { type: 'percent', value: Math.abs(num) };
        }
        function parseAmzPefPercentAllowZero(raw) {
            const s = String(raw == null ? '' : raw).trim();
            if (s === '') return null;
            const num = parseFloat(s.replace(/[%$,\s]/g, '').replace(',', '.'));
            if (!isFinite(num) || num < 0) return null;
            return { type: 'percent', value: Math.abs(num) };
        }
        function applyAmzPromoToSpriceBase(base, promo) {
            if (!(base > 0) || !promo) return null;
            const next = base * (1 - (promo.value / 100));
            return Math.max(0.01, amzPefRound2(next));
        }
        function getAmzDiscountBase(d, appliedKey) {
            let base = Number(d.SPRICE) > 0 ? Number(d.SPRICE) : Number(d.price) || 0;
            const prev = Number(d[appliedKey] || 0) || 0;
            if (prev > 0 && prev < 100) base = base / (1 - (prev / 100));
            return amzPefRound2(base);
        }
        function fmtAmzPefPromoCell(value, placeholder) {
            if (value === null || value === undefined || value === '') {
                return '<span class="amz-pef-promo-cell">' + placeholder + '</span>';
            }
            return '<span class="amz-pef-promo-cell has-val">' + String(value) + '</span>';
        }
        function amzPefEscAttr(s) {
            if (typeof escAttr === 'function') return escAttr(s);
            return String(s == null ? '' : s)
                .replace(/&/g, '&amp;')
                .replace(/"/g, '&quot;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
        }
        /** Colored history dot (PDT daily roll-on) for PRMT% / CPN% columns */
        function amzPefPromoHistoryDotHtml(sku, metric, pct) {
            if (!sku) return '';
            const n = Number(pct);
            const has = isFinite(n) && n > 0;
            let color = '#adb5bd';
            let label = metric;
            if (metric === 'cpn') { color = has ? '#20c997' : '#adb5bd'; label = 'CPN%'; }
            else if (metric === 'prmt') { color = has ? '#0d6efd' : '#adb5bd'; label = 'PRMT%'; }
            else if (metric === 'push_prc') { color = has ? '#FF9900' : '#adb5bd'; label = 'Push Prc'; }
            const tip = label
                + (has ? (metric === 'push_prc' ? (' = $' + Number(n).toFixed(2)) : (' = ' + n + '%')) : '')
                + ' — click for daily history (PDT)';
            return '<button type="button" class="btn btn-sm p-0 view-sku-chart amz-pef-hist-dot align-middle" '
                + 'data-sku="' + amzPefEscAttr(sku) + '" data-metric="' + amzPefEscAttr(metric) + '" '
                + 'title="' + amzPefEscAttr(tip) + '" '
                + 'style="border:none;background:none;cursor:pointer;padding:0;line-height:1;vertical-align:middle;">'
                + '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;'
                + 'background:' + color + ';flex-shrink:0;"></span></button>';
        }
        function saveAmzSpriceFromPromo(row, sprice, silent, extra) {
            const d = row.getData();
            const sku = amzPefSku(d);
            const val = amzPefRound2(sprice);
            extra = extra || {};
            if (!sku) return;
            const payload = { sku: sku, _token: amzPefCsrf() };
            if (val > 0) {
                payload.sprice = val;
                row.update({ SPRICE: val });
            }
            if (extra.prmt_pct !== undefined && extra.prmt_pct !== null) {
                payload.prmt_pct = Number(extra.prmt_pct) || 0;
            }
            if (extra.cpn_pct !== undefined && extra.cpn_pct !== null) {
                payload.cpn_pct = Number(extra.cpn_pct) || 0;
            }
            if (payload.sprice === undefined && payload.prmt_pct === undefined && payload.cpn_pct === undefined) {
                return;
            }
            $.ajax({
                url: '/save-amazon-sprice',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': amzPefCsrf(), 'Accept': 'application/json' },
                data: payload,
                success: function(response) {
                    const updates = {};
                    if (response.data !== undefined) updates.SPRICE = response.data;
                    if (response.sgpft_percent !== undefined) updates.SGPFT = response.sgpft_percent;
                    if (response.spft_percent !== undefined) updates['Spft%'] = response.spft_percent;
                    if (response.sroi_percent !== undefined) updates.SROI = response.sroi_percent;
                    if (response.sgroi_percent !== undefined) updates.SGROI = response.sgroi_percent;
                    if (response.prmt_pct !== undefined && response.prmt_pct !== null) {
                        updates.prmt_pct = String(response.prmt_pct);
                        updates._prmt_pct_applied = Number(response.prmt_pct) || 0;
                    }
                    if (response.cpn_pct !== undefined && response.cpn_pct !== null) {
                        updates.cpn_pct = String(response.cpn_pct);
                        updates._cpn_pct_applied = Number(response.cpn_pct) || 0;
                    }
                    if (Object.keys(updates).length) row.update(updates);
                    if (!silent) amzPefToast('success', 'S PRC updated');
                },
                error: function() {
                    if (!silent) amzPefToast('error', 'Failed to save S PRC');
                }
            });
        }
        function collectAmzPefSelectedRows() {
            if (!table) return [];
            const effective = new Set();
            if (typeof selectedSkus !== 'undefined' && selectedSkus && selectedSkus.forEach) {
                selectedSkus.forEach(function(s) { if (s) effective.add(String(s)); });
            }
            if (typeof selectedRows !== 'undefined' && selectedRows && selectedRows.forEach) {
                selectedRows.forEach(function(s) { if (s) effective.add(String(s)); });
            }
            if (!effective.size) return [];
            return table.getRows().filter(function(row) {
                const d = row.getData();
                return amzPefIsChildRow(d) && effective.has(amzPefSku(d));
            }).map(function(row) {
                return { row: row, d: row.getData() };
            });
        }
        function collectAmzPefVisibleRows() {
            if (!table) return [];
            return table.getRows('active').filter(function(row) {
                return amzPefIsChildRow(row.getData());
            }).map(function(row) {
                return { row: row, d: row.getData() };
            });
        }

        function pefDilSlabKey(dil) {
            const n = Number(dil);
            if (!isFinite(n) || n < 0) return '0-10';
            if (n > 100) return 'gt-100';
            if (n >= 90) return '90-100';
            if (n >= 80) return '80-90';
            if (n >= 70) return '70-80';
            if (n >= 60) return '60-70';
            if (n >= 50) return '50-60';
            if (n >= 40) return '40-50';
            if (n >= 30) return '30-40';
            if (n >= 20) return '20-30';
            if (n >= 10) return '10-20';
            return '0-10';
        }
        function pefPrmtForDil(dil) {
            const key = pefDilSlabKey(dil);
            const rule = pefDilPrmtRules.find(function(r) { return r.key === key; });
            if (!rule) return 0;
            const n = Number(rule.prmt);
            return isFinite(n) && n >= 0 ? n : 0;
        }
        function pefCvrSlabKey(cvr) {
            const n = Number(cvr);
            if (!isFinite(n) || n <= 0) return 'eq-0';
            if (n > 7) return 'gt-7';
            if (n >= 6.5) return '6.5-7';
            if (n >= 6) return '6-6.5';
            if (n >= 5) return '5-6';
            if (n >= 4) return '4-5';
            if (n >= 3) return '3-4';
            if (n >= 2) return '2-3';
            if (n >= 1.5) return '1.5-2';
            if (n >= 1) return '1-1.5';
            return '0.01-1';
        }
        function pefCpnForCvr(cvr) {
            const key = pefCvrSlabKey(cvr);
            const rule = pefCvrCpnRules.find(function(r) { return r.key === key; });
            if (!rule) return 0;
            const n = Number(rule.cpn);
            return isFinite(n) && n >= 0 ? n : 0;
        }

        function renderDilPrmtModalTable() {
            const $tb = $('#pef-dil-prmt-tbody').empty();
            pefDilPrmtRules.forEach(function(r, idx) {
                const prmt = isFinite(Number(r.prmt)) ? Number(r.prmt) : 0;
                $tb.append(
                    '<tr data-key="' + String(r.key).replace(/"/g, '&quot;') + '">'
                    + '<td>' + String(r.label || r.key) + '</td>'
                    + '<td class="text-end">'
                    + '<input type="number" class="form-control form-control-sm pef-dil-prmt-input" '
                    + 'min="0" step="0.1" value="' + prmt + '" data-idx="' + idx + '">'
                    + '</td></tr>'
                );
            });
        }
        function readDilPrmtRulesFromModal() {
            $('#pef-dil-prmt-tbody tr').each(function() {
                const key = String($(this).attr('data-key') || '');
                const val = parseFloat($(this).find('.pef-dil-prmt-input').val());
                const rule = pefDilPrmtRules.find(function(r) { return r.key === key; });
                if (!rule) return;
                rule.prmt = (isFinite(val) && val >= 0) ? val : 0;
            });
            return pefDilPrmtRules.map(function(r) {
                return { key: r.key, label: r.label, prmt: Number(r.prmt) || 0 };
            });
        }
        async function loadDilPrmtRules() {
            $('#pef-dil-prmt-status').text('Loading…');
            try {
                const res = await $.ajax({
                    url: '/pricing-errors-fix-dil-prmt',
                    method: 'GET',
                    dataType: 'json',
                });
                if (res && Array.isArray(res.rules) && res.rules.length) {
                    pefDilPrmtRules = res.rules.map(function(r) { return Object.assign({}, r); });
                }
                renderDilPrmtModalTable();
                $('#pef-dil-prmt-status').text(res && res.is_default
                    ? 'Using first-time defaults (0–10). Apply to save & apply.'
                    : 'Loaded saved Dil vs PRMT rules (shared with pricing-errors-fix).');
            } catch (e) {
                renderDilPrmtModalTable();
                $('#pef-dil-prmt-status').text('Could not load saved rules — showing defaults.');
            }
        }
        function saveDilPrmtRules() {
            const rules = readDilPrmtRulesFromModal();
            return $.ajax({
                url: '/pricing-errors-fix-dil-prmt',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': amzPefCsrf(), 'Accept': 'application/json' },
                data: { rules: rules, _token: amzPefCsrf() },
            }).then(function(res) {
                if (res && Array.isArray(res.rules)) {
                    pefDilPrmtRules = res.rules.map(function(r) { return Object.assign({}, r); });
                    renderDilPrmtModalTable();
                }
                $('#pef-dil-prmt-status').text('Saved (shared with pricing-errors-fix).');
                return res;
            });
        }

        function renderCvrCpnModalTable() {
            const $tb = $('#pef-cvr-cpn-tbody').empty();
            pefCvrCpnRules.forEach(function(r, idx) {
                const cpn = isFinite(Number(r.cpn)) ? Number(r.cpn) : 0;
                $tb.append(
                    '<tr data-key="' + String(r.key).replace(/"/g, '&quot;') + '">'
                    + '<td>' + String(r.label || r.key) + '</td>'
                    + '<td class="text-end">'
                    + '<input type="number" class="form-control form-control-sm pef-cvr-cpn-input" '
                    + 'min="0" step="0.1" value="' + cpn + '" data-idx="' + idx + '">'
                    + '</td></tr>'
                );
            });
        }
        function readCvrCpnRulesFromModal() {
            $('#pef-cvr-cpn-tbody tr').each(function() {
                const key = String($(this).attr('data-key') || '');
                const val = parseFloat($(this).find('.pef-cvr-cpn-input').val());
                const rule = pefCvrCpnRules.find(function(r) { return r.key === key; });
                if (!rule) return;
                rule.cpn = (isFinite(val) && val >= 0) ? val : 0;
            });
            return pefCvrCpnRules.map(function(r) {
                return { key: r.key, label: r.label, cpn: Number(r.cpn) || 0 };
            });
        }
        async function loadCvrCpnRules() {
            $('#pef-cvr-cpn-status').text('Loading…');
            try {
                const res = await $.ajax({
                    url: '/pricing-errors-fix-cvr-cpn',
                    method: 'GET',
                    dataType: 'json',
                });
                if (res && Array.isArray(res.rules) && res.rules.length) {
                    pefCvrCpnRules = res.rules.map(function(r) { return Object.assign({}, r); });
                }
                renderCvrCpnModalTable();
                $('#pef-cvr-cpn-status').text(res && res.is_default
                    ? 'Using first-time defaults (0–10). Apply to save & apply.'
                    : 'Loaded saved CVR vs CPN rules (shared with pricing-errors-fix).');
            } catch (e) {
                renderCvrCpnModalTable();
                $('#pef-cvr-cpn-status').text('Could not load saved rules — showing defaults.');
            }
        }
        function saveCvrCpnRules() {
            const rules = readCvrCpnRulesFromModal();
            return $.ajax({
                url: '/pricing-errors-fix-cvr-cpn',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': amzPefCsrf(), 'Accept': 'application/json' },
                data: { rules: rules, _token: amzPefCsrf() },
            }).then(function(res) {
                if (res && Array.isArray(res.rules)) {
                    pefCvrCpnRules = res.rules.map(function(r) { return Object.assign({}, r); });
                    renderCvrCpnModalTable();
                }
                $('#pef-cvr-cpn-status').text('Saved (shared with pricing-errors-fix).');
                return res;
            });
        }

        async function saveAndApplyDilPrmt() {
            const selected = collectAmzPefSelectedRows();
            let targets = selected;
            let label = 'selected';
            if (!targets.length) {
                targets = collectAmzPefVisibleRows();
                label = 'all visible';
                if (!targets.length) {
                    amzPefToast('error', 'No rows to apply');
                    return;
                }
                if (!confirm('No rows selected — save rules and apply Dil→PRMT % to all ' + targets.length + ' visible row(s)?')) {
                    return;
                }
            }
            const $btn = $('#pef-dil-prmt-apply-btn');
            const html = $btn.html();
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Applying…');
            try {
                await saveDilPrmtRules();
                await applyDilPrmtToTargets(targets, label);
            } catch (xhr) {
                amzPefToast('error', 'Save failed: ' + ((xhr && xhr.responseJSON && xhr.responseJSON.message) || 'error'));
            } finally {
                $btn.prop('disabled', false).html(html);
            }
        }

        async function saveAndApplyCvrCpn() {
            const selected = collectAmzPefSelectedRows();
            let targets = selected;
            let label = 'selected';
            if (!targets.length) {
                targets = collectAmzPefVisibleRows();
                label = 'all visible';
                if (!targets.length) {
                    amzPefToast('error', 'No rows to apply');
                    return;
                }
                if (!confirm('No rows selected — save rules and apply CVR→CPN % to all ' + targets.length + ' visible row(s)?')) {
                    return;
                }
            }
            const $btn = $('#pef-cvr-cpn-apply-btn');
            const html = $btn.html();
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Applying…');
            try {
                await saveCvrCpnRules();
                await applyCvrCpnToTargets(targets, label);
            } catch (xhr) {
                amzPefToast('error', 'Save failed: ' + ((xhr && xhr.responseJSON && xhr.responseJSON.message) || 'error'));
            } finally {
                $btn.prop('disabled', false).html(html);
            }
        }

        async function applyDilPrmtToTargets(targets, label) {
            readDilPrmtRulesFromModal();
            if (!targets.length) {
                amzPefToast('error', 'No rows to apply');
                return;
            }
            let ok = 0;
            let skipped = 0;
            for (let i = 0; i < targets.length; i++) {
                const item = targets[i];
                const d = item.row.getData();
                if (!amzPefIsChildRow(d)) { skipped++; continue; }
                const dil = amzPefDil(d);
                const prmt = amzPefInv(d) === 0 ? 0 : pefPrmtForDil(dil);
                if (!(prmt > 0)) {
                    item.row.update({ prmt_pct: String(prmt), _prmt_pct_applied: 0 });
                    saveAmzSpriceFromPromo(item.row, Number(d.SPRICE) || 0, true, { prmt_pct: prmt });
                    skipped++;
                    continue;
                }
                const promo = { type: 'percent', value: prmt };
                const base = getAmzDiscountBase(d, '_prmt_pct_applied');
                const newPrice = applyAmzPromoToSpriceBase(base, promo);
                if (!(base > 0) || !(newPrice > 0)) { skipped++; continue; }
                item.row.update({
                    prmt_pct: String(prmt),
                    _prmt_pct_applied: prmt,
                    SPRICE: newPrice,
                });
                saveAmzSpriceFromPromo(item.row, newPrice, true, { prmt_pct: prmt });
                ok++;
            }
            amzPefToast(
                (ok ? 'success' : 'error'),
                'Dil vs PRMT (' + label + '): PRMT % → ' + ok + ' row(s)'
                    + (skipped ? ('; skipped ' + skipped) : '') + '.'
            );
            if (table) table.redraw(true);
        }

        async function applyCvrCpnToTargets(targets, label) {
            readCvrCpnRulesFromModal();
            if (!targets.length) {
                amzPefToast('error', 'No rows to apply');
                return;
            }
            let ok = 0;
            let skipped = 0;
            for (let i = 0; i < targets.length; i++) {
                const item = targets[i];
                const d = item.row.getData();
                if (!amzPefIsChildRow(d)) { skipped++; continue; }
                const cvr = amzPefCvr(d);
                const cpn = amzPefInv(d) === 0 ? 0 : pefCpnForCvr(cvr);
                if (!(cpn > 0)) {
                    item.row.update({ cpn_pct: String(cpn), _cpn_pct_applied: 0 });
                    saveAmzSpriceFromPromo(item.row, Number(d.SPRICE) || 0, true, { cpn_pct: cpn });
                    skipped++;
                    continue;
                }
                const promo = { type: 'percent', value: cpn };
                const base = getAmzDiscountBase(d, '_cpn_pct_applied');
                const newPrice = applyAmzPromoToSpriceBase(base, promo);
                if (!(base > 0) || !(newPrice > 0)) { skipped++; continue; }
                item.row.update({
                    cpn_pct: String(cpn),
                    _cpn_pct_applied: cpn,
                    SPRICE: newPrice,
                });
                saveAmzSpriceFromPromo(item.row, newPrice, true, { cpn_pct: cpn });
                ok++;
            }
            amzPefToast(
                (ok ? 'success' : 'error'),
                'CVR vs CPN (' + label + '): CPN % → ' + ok + ' row(s)'
                    + (skipped ? ('; skipped ' + skipped) : '') + '.'
            );
            if (table) table.redraw(true);
        }

        function amzPefLmpDiffAmount(d) {
            const price = Number(d.price) || 0;
            const lmp = Number(d.lmp_price) || 0;
            if (!(price > 0) || !(lmp > 0)) return null;
            const amt = amzPefRound2(price - lmp);
            return amt > 0 ? amt : null;
        }
        function clearAmzApprDiscount(row, opts) {
            opts = opts || {};
            const d = row.getData();
            const prev = Number(d._dsc_applied) || 0;
            const patch = {
                appr: false,
                _appr_lmp: null,
                dsc: '',
                _dsc_applied: 0,
            };
            if (prev > 0 && prev < 100 && Number(d.SPRICE) > 0) {
                patch.SPRICE = amzPefRound2(Number(d.SPRICE) / (1 - (prev / 100)));
            }
            row.update(patch);
            if (opts.save && patch.SPRICE != null && Number(patch.SPRICE) > 0) {
                saveAmzSpriceFromPromo(row, patch.SPRICE, true);
            }
            if (opts.redraw && table) table.redraw(true);
        }
        function applyAmzApprDiscount(row) {
            const d = row.getData();
            const amt = amzPefLmpDiffAmount(d);
            const lmp = Number(d.lmp_price);
            if (!(amt > 0) || !(lmp > 0)) {
                row.update({ appr: false, _appr_lmp: null });
                amzPefToast('error', 'Appr needs Price > LMP');
                if (table) table.redraw(true);
                return false;
            }
            const base = getAmzDiscountBase(d, '_dsc_applied');
            if (!(base > 0)) {
                row.update({ appr: false, _appr_lmp: null });
                amzPefToast('error', 'No S PRC/Price to discount');
                if (table) table.redraw(true);
                return false;
            }
            let pct = amzPefRound2((amt / base) * 100);
            if (!(pct > 0) || pct >= 100) {
                row.update({ appr: false, _appr_lmp: null });
                amzPefToast('error', 'Appr DSC % out of range');
                if (table) table.redraw(true);
                return false;
            }
            const newPrice = applyAmzPromoToSpriceBase(base, { type: 'percent', value: pct });
            if (!(newPrice > 0)) {
                row.update({ appr: false, _appr_lmp: null });
                amzPefToast('error', 'No S PRC/Price to discount');
                if (table) table.redraw(true);
                return false;
            }
            row.update({
                appr: true,
                _appr_lmp: amzPefRound2(lmp),
                dsc: String(pct),
                _dsc_applied: pct,
                SPRICE: newPrice,
            });
            saveAmzSpriceFromPromo(row, newPrice, true);
            if (table) table.redraw(true);
            return true;
        }
        async function applyAmzPefPromoFromCell(cell, kind) {
            const fieldMeta = {
                prmt: { field: 'prmt_pct', appliedKey: '_prmt_pct_applied', label: 'PRMT %', allowZero: true },
                cpn: { field: 'cpn_pct', appliedKey: '_cpn_pct_applied', label: 'CPN %', allowZero: true },
                dsc: { field: 'dsc', appliedKey: '_dsc_applied', label: 'DSC %', allowZero: false },
            }[kind];
            if (!fieldMeta) return;
            const editedRow = cell.getRow();
            const raw = cell.getValue();
            const promo = fieldMeta.allowZero
                ? parseAmzPefPercentAllowZero(raw)
                : parseAmzPefPercentAmount(raw);
            if (!promo) {
                if (String(raw == null ? '' : raw).trim() !== '') {
                    amzPefToast('error', 'Enter ' + fieldMeta.label + ' (e.g. 10)');
                }
                return;
            }
            let targets = [{ row: editedRow, d: editedRow.getData() }];
            const selected = collectAmzPefSelectedRows();
            const editedSku = amzPefSku(editedRow.getData());
            if (selected.length > 1 && selected.some(function(t) { return amzPefSku(t.d) === editedSku; })) {
                targets = selected;
            }
            const displayVal = String(promo.value);
            let ok = 0;
            let skipped = 0;
            for (let i = 0; i < targets.length; i++) {
                const item = targets[i];
                const d = item.row.getData();
                if (!amzPefIsChildRow(d)) { skipped++; continue; }
                if (!(promo.value > 0)) {
                    const patch = {};
                    patch[fieldMeta.field] = '0';
                    patch[fieldMeta.appliedKey] = 0;
                    if (kind === 'dsc') {
                        patch.appr = false;
                        patch._appr_lmp = null;
                    }
                    item.row.update(patch);
                    if (kind === 'prmt' || kind === 'cpn') {
                        const extra = {};
                        extra[kind === 'prmt' ? 'prmt_pct' : 'cpn_pct'] = 0;
                        saveAmzSpriceFromPromo(item.row, Number(d.SPRICE) || 0, true, extra);
                    }
                    skipped++;
                    continue;
                }
                const base = getAmzDiscountBase(d, fieldMeta.appliedKey);
                const newPrice = applyAmzPromoToSpriceBase(base, promo);
                if (!(base > 0) || !(newPrice > 0)) { skipped++; continue; }
                const patch = {};
                patch[fieldMeta.field] = displayVal;
                patch[fieldMeta.appliedKey] = promo.value;
                patch.SPRICE = newPrice;
                if (kind === 'dsc') {
                    patch.appr = false;
                    patch._appr_lmp = null;
                }
                item.row.update(patch);
                const extra = {};
                if (kind === 'prmt') extra.prmt_pct = promo.value;
                if (kind === 'cpn') extra.cpn_pct = promo.value;
                saveAmzSpriceFromPromo(item.row, newPrice, true, extra);
                ok++;
            }
            amzPefToast(
                ok ? 'success' : 'error',
                fieldMeta.label + ' → ' + ok + ' row(s)' + (skipped ? ('; skipped ' + skipped) : '')
            );
            if (table) table.redraw(true);
        }

        function amazonPefPromoColumns() {
            return [
                {
                    title: 'PRMT %',
                    field: 'prmt_pct',
                    width: 72,
                    hozAlign: 'center',
                    vertAlign: 'middle',
                    headerSort: false,
                    editable: function(cell) {
                        return amzPefIsChildRow(cell.getRow().getData());
                    },
                    editor: 'input',
                    headerTooltip: '% less on S PRC. Also filled by Dil vs PRMT. Dot = PDT daily history.',
                    formatter: function(cell) {
                        const d = cell.getRow().getData() || {};
                        if (d.is_parent_summary) return '';
                        const sku = amzPefSku(d);
                        const val = cell.getValue();
                        const dot = amzPefPromoHistoryDotHtml(sku, 'prmt', val);
                        return '<span style="display:inline-flex;align-items:center;justify-content:center;gap:3px;">'
                            + dot + fmtAmzPefPromoCell(val, '%') + '</span>';
                    },
                    cellClick: function(e) {
                        if (e.target.closest('.view-sku-chart') || e.target.closest('.amz-pef-hist-dot')) {
                            e.stopPropagation();
                            return false;
                        }
                    },
                    cellEdited: function(cell) {
                        applyAmzPefPromoFromCell(cell, 'prmt');
                    },
                },
                {
                    title: 'CPN %',
                    field: 'cpn_pct',
                    width: 70,
                    hozAlign: 'center',
                    vertAlign: 'middle',
                    headerSort: false,
                    editable: function(cell) {
                        return amzPefIsChildRow(cell.getRow().getData());
                    },
                    editor: 'input',
                    headerTooltip: '% less on S PRC. Also filled by CVR vs CPN. Dot = PDT daily history.',
                    formatter: function(cell) {
                        const d = cell.getRow().getData() || {};
                        if (d.is_parent_summary) return '';
                        const sku = amzPefSku(d);
                        const val = cell.getValue();
                        const dot = amzPefPromoHistoryDotHtml(sku, 'cpn', val);
                        return '<span style="display:inline-flex;align-items:center;justify-content:center;gap:3px;">'
                            + dot + fmtAmzPefPromoCell(val, '%') + '</span>';
                    },
                    cellClick: function(e) {
                        if (e.target.closest('.view-sku-chart') || e.target.closest('.amz-pef-hist-dot')) {
                            e.stopPropagation();
                            return false;
                        }
                    },
                    cellEdited: function(cell) {
                        applyAmzPefPromoFromCell(cell, 'cpn');
                    },
                },
                {
                    title: 'Appr',
                    field: 'appr',
                    width: 48,
                    hozAlign: 'center',
                    vertAlign: 'middle',
                    headerSort: false,
                    headerTooltip: 'Approve — ticks to put LMP gap (Price − LMP) as DSC % off S PRC.',
                    formatter: function(cell) {
                        const d = cell.getRow().getData();
                        if (!amzPefIsChildRow(d)) return '';
                        const checked = d.appr ? 'checked' : '';
                        const sku = amzPefSku(d).replace(/"/g, '&quot;');
                        return '<input type="checkbox" class="amz-pef-appr-cb" data-sku="' + sku + '" ' + checked
                            + ' title="Approve LMP gap → DSC %">';
                    },
                },
                {
                    title: 'DSC %',
                    field: 'dsc',
                    width: 56,
                    hozAlign: 'center',
                    vertAlign: 'middle',
                    headerSort: false,
                    editable: function(cell) {
                        return amzPefIsChildRow(cell.getRow().getData());
                    },
                    editor: 'input',
                    headerTooltip: '% less on S PRC. Filled by Appr (Price − LMP as %) or edit manually.',
                    formatter: function(cell) {
                        const d = cell.getRow().getData() || {};
                        if (d.is_parent_summary) return '';
                        return fmtAmzPefPromoCell(cell.getValue(), '%');
                    },
                    cellEdited: function(cell) {
                        applyAmzPefPromoFromCell(cell, 'dsc');
                    },
                },
                {
                    title: 'Push Prc',
                    field: 'push_prc',
                    width: 78,
                    hozAlign: 'center',
                    vertAlign: 'middle',
                    headerSort: false,
                    headerTooltip: 'Push Std Prc to Amazon with PRMT% + CPN% applied. Dot = PDT push history.',
                    formatter: function(cell) {
                        const d = cell.getRow().getData() || {};
                        if (!amzPefIsChildRow(d)) return '';
                        const sku = amzPefSku(d);
                        const pushPrice = computeAmzPushPrcFromStd(d);
                        const status = String(d.PUSH_PRC_STATUS || '');
                        const histVal = d.PUSH_PRC_VALUE != null ? d.PUSH_PRC_VALUE : pushPrice;
                        const dot = amzPefPromoHistoryDotHtml(sku, 'push_prc', histVal);
                        if (!(pushPrice > 0)) {
                            return '<span style="display:inline-flex;align-items:center;justify-content:center;gap:4px;">'
                                + dot + '<span style="color:#adb5bd;" title="Set Std Prc first">—</span></span>';
                        }
                        let icon = '<i class="fas fa-upload"></i>';
                        let color = '#FF9900';
                        let tip = 'Push Std $' + pushPrice.toFixed(2) + ' to Amazon (with PRMT% + CPN%)';
                        if (status === 'pushed') {
                            icon = '<i class="fa-solid fa-check-double"></i>';
                            color = '#28a745';
                            tip = 'Pushed $' + (Number(d.PUSH_PRC_VALUE) || pushPrice).toFixed(2) + ' — click to push again';
                        } else if (status === 'error') {
                            icon = '<i class="fa-solid fa-xmark"></i>';
                            color = '#dc3545';
                            tip = 'Last push failed — click to retry';
                        } else if (status === 'processing') {
                            icon = '<i class="fas fa-spinner fa-spin"></i>';
                            color = '#ffc107';
                            tip = 'Pushing…';
                        }
                        const asin = (d.asin != null && String(d.asin).trim() !== '')
                            ? amzPefEscAttr(String(d.asin).trim()) : '';
                        return '<span style="display:inline-flex;align-items:center;justify-content:center;gap:4px;">'
                            + dot
                            + '<button type="button" class="btn btn-sm p-0 amz-push-prc-btn" '
                            + 'data-sku="' + amzPefEscAttr(sku) + '" data-asin="' + asin + '" '
                            + 'data-price="' + pushPrice.toFixed(2) + '" '
                            + 'title="' + amzPefEscAttr(tip) + '" '
                            + 'style="border:none;background:none;cursor:pointer;color:' + color
                            + ';padding:0;line-height:1;vertical-align:middle;">'
                            + icon + '</button></span>';
                    },
                    cellClick: function(e) {
                        if (e.target.closest('.view-sku-chart') || e.target.closest('.amz-pef-hist-dot')
                            || e.target.closest('.amz-push-prc-btn')) {
                            e.stopPropagation();
                            return false;
                        }
                    },
                },
            ];
        }

        /** Std Prc × (1 − PRMT%/100) × (1 − CPN%/100) */
        function computeAmzPushPrcFromStd(d) {
            const std = Number(d.STANDARD_PRICE) || 0;
            if (!(std > 0)) return null;
            const prmt = Math.max(0, Number(d.prmt_pct != null ? d.prmt_pct : d._prmt_pct_applied) || 0);
            const cpn = Math.max(0, Number(d.cpn_pct != null ? d.cpn_pct : d._cpn_pct_applied) || 0);
            let price = std;
            if (prmt > 0 && prmt < 100) price = price * (1 - (prmt / 100));
            if (cpn > 0 && cpn < 100) price = price * (1 - (cpn / 100));
            price = amzPefRound2(price);
            return price >= 0.01 ? price : null;
        }

        function pushAmzStdPrcWithPromos($btn, row) {
            const d = row.getData();
            const sku = amzPefSku(d);
            const price = computeAmzPushPrcFromStd(d);
            const asin = (d.asin && String(d.asin).trim() !== '') ? String(d.asin).trim() : '';
            if (!sku || !(price > 0)) {
                amzPefToast('error', 'Set Std Prc first (and optional PRMT%/CPN%)');
                return;
            }
            const prmt = Math.max(0, Number(d.prmt_pct != null ? d.prmt_pct : d._prmt_pct_applied) || 0);
            const cpn = Math.max(0, Number(d.cpn_pct != null ? d.cpn_pct : d._cpn_pct_applied) || 0);
            if (!confirm(
                'Push Std Prc to Amazon for ' + sku + '?\n\n'
                + 'Std: $' + Number(d.STANDARD_PRICE).toFixed(2) + '\n'
                + 'PRMT%: ' + prmt + '\n'
                + 'CPN%: ' + cpn + '\n'
                + '→ Push price: $' + price.toFixed(2)
            )) return;

            const html = $btn.html();
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
            row.update({ PUSH_PRC_STATUS: 'processing' });

            $.ajax({
                url: '/apply-amazon-price',
                method: 'POST',
                timeout: 120000,
                headers: { 'X-CSRF-TOKEN': amzPefCsrf(), 'Accept': 'application/json' },
                data: {
                    sku: sku,
                    price: price,
                    asin: asin || null,
                    push_shopify: false,
                    update_amazon_min_price: true,
                    _token: amzPefCsrf(),
                },
            }).done(function(response) {
                if (response && response.errors && response.errors.length) {
                    row.update({ PUSH_PRC_STATUS: 'error' });
                    amzPefToast('error', (response.errors[0] && response.errors[0].message) || 'Push failed');
                    $btn.prop('disabled', false).html(html);
                    if (table) table.redraw(true);
                    return;
                }
                // Persist SPRICE + Push Prc daily history (PDT)
                $.ajax({
                    url: '/save-amazon-sprice',
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': amzPefCsrf(), 'Accept': 'application/json' },
                    data: {
                        sku: sku,
                        sprice: price,
                        prmt_pct: prmt,
                        cpn_pct: cpn,
                        record_push_prc: 1,
                        _token: amzPefCsrf(),
                    },
                }).done(function(saveRes) {
                    const updates = {
                        SPRICE: price,
                        SPRICE_STATUS: 'pushed',
                        PUSH_PRC_STATUS: 'pushed',
                        PUSH_PRC_VALUE: price,
                        prmt_pct: String(prmt),
                        cpn_pct: String(cpn),
                        _prmt_pct_applied: prmt,
                        _cpn_pct_applied: cpn,
                    };
                    if (saveRes && saveRes.sgpft_percent !== undefined) updates.SGPFT = saveRes.sgpft_percent;
                    if (saveRes && saveRes.spft_percent !== undefined) updates['Spft%'] = saveRes.spft_percent;
                    if (saveRes && saveRes.sroi_percent !== undefined) updates.SROI = saveRes.sroi_percent;
                    if (saveRes && saveRes.sgroi_percent !== undefined) updates.SGROI = saveRes.sgroi_percent;
                    row.update(updates);
                    amzPefToast('success', 'Pushed $' + price.toFixed(2) + ' (Std + PRMT/CPN) for ' + sku);
                }).fail(function() {
                    row.update({
                        SPRICE: price,
                        SPRICE_STATUS: 'pushed',
                        PUSH_PRC_STATUS: 'pushed',
                        PUSH_PRC_VALUE: price,
                    });
                    amzPefToast('success', 'Pushed $' + price.toFixed(2) + ' to Amazon (local save pending)');
                }).always(function() {
                    $btn.prop('disabled', false);
                    if (table) table.redraw(true);
                });
            }).fail(function(xhr) {
                row.update({ PUSH_PRC_STATUS: 'error' });
                amzPefToast('error', 'Push Prc failed: ' + (
                    (xhr.responseJSON && xhr.responseJSON.errors && xhr.responseJSON.errors[0]
                        && xhr.responseJSON.errors[0].message)
                    || (xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error))
                    || 'error'
                ));
                $btn.prop('disabled', false).html(html);
                if (table) table.redraw(true);
            });
        }

        function initAmazonPefPromoUi() {
            $('#amz-dil-vs-prmt-btn').off('click.amzpef').on('click.amzpef', function() {
                const modalEl = document.getElementById('pefDilVsPrmtModal');
                if (!modalEl) return;
                renderDilPrmtModalTable();
                loadDilPrmtRules();
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            });

            // Push Prmt% → Dil rules → Amazon Listings API (only changed prices)
            $('#amz-push-prmt-btn').off('click.amzpef').on('click.amzpef', function() {
                if (!table) {
                    amzPefToast('error', 'Load data first');
                    return;
                }
                const selected = collectAmzPefSelectedRows();
                let skus = selected.map(function(t) { return amzPefSku(t.d); }).filter(Boolean);
                let scopeLabel = 'selected';
                if (!skus.length) {
                    skus = collectAmzPefVisibleRows().map(function(t) { return amzPefSku(t.d); }).filter(Boolean);
                    scopeLabel = 'all visible';
                }
                if (!skus.length) {
                    amzPefToast('error', 'No rows to push');
                    return;
                }
                if (!confirm(
                    'Push Prmt% to Amazon for ' + skus.length + ' ' + scopeLabel + ' SKU(s)?\n\n'
                    + 'Uses Dil vs PRMT rules, then pushes via Amazon Listings API.\n'
                    + 'Unchanged prices are skipped.'
                )) return;

                const $btn = $('#amz-push-prmt-btn');
                const html = $btn.html();
                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Pushing…');
                $.ajax({
                    url: '/amazon-dil-prmt-push',
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': amzPefCsrf(), 'Accept': 'application/json' },
                    data: { skus: skus, _token: amzPefCsrf() },
                    timeout: 0,
                }).done(function(res) {
                    const st = (res && res.stats) || {};
                    amzPefToast(
                        (st.push_failed > 0 && !(st.pushed > 0)) ? 'error' : 'success',
                        (res && res.message) || 'Push Prmt% done'
                    );
                    // Refresh grid SPRICE / status for affected rows when possible
                    if (table && Array.isArray(skus)) {
                        skus.forEach(function(sku) {
                            const row = table.getRows().find(function(r) {
                                return amzPefSku(r.getData()) === sku;
                            });
                            if (!row) return;
                            // Mark UI PRMT from Dil so column reflects the rule after push
                            const d = row.getData();
                            const dil = amzPefDil(d);
                            const prmt = amzPefInv(d) === 0 ? 0 : pefPrmtForDil(dil);
                            row.update({ prmt_pct: String(prmt), _prmt_pct_applied: prmt });
                        });
                        table.redraw(true);
                    }
                }).fail(function(xhr) {
                    amzPefToast('error', 'Push Prmt% failed: ' + (
                        (xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error)) || 'error'
                    ));
                }).always(function() {
                    $btn.prop('disabled', false).html(html);
                });
            });
            $('#pef-dil-prmt-apply-btn').off('click.amzpef').on('click.amzpef', saveAndApplyDilPrmt);

            $('#amz-cvr-vs-cpn-btn').off('click.amzpef').on('click.amzpef', function() {
                const modalEl = document.getElementById('pefCvrVsCpnModal');
                if (!modalEl) return;
                renderCvrCpnModalTable();
                loadCvrCpnRules();
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            });

            // Push CPN% → CVR rules → 5%/10% coupons (1/day) → Amazon Listings API (only changed)
            $('#amz-push-cpn-btn').off('click.amzpef').on('click.amzpef', function() {
                if (!table) {
                    amzPefToast('error', 'Load data first');
                    return;
                }
                const selected = collectAmzPefSelectedRows();
                let skus = selected.map(function(t) { return amzPefSku(t.d); }).filter(Boolean);
                let scopeLabel = 'selected';
                if (!skus.length) {
                    skus = collectAmzPefVisibleRows().map(function(t) { return amzPefSku(t.d); }).filter(Boolean);
                    scopeLabel = 'all visible';
                }
                if (!skus.length) {
                    amzPefToast('error', 'No rows to push');
                    return;
                }
                if (!confirm(
                    'Push CPN% to Amazon for ' + skus.length + ' ' + scopeLabel + ' SKU(s)?\n\n'
                    + 'Uses CVR vs CPN rules, snaps to coupons 5% / 10% (1 coupon per day),\n'
                    + 'then pushes via Amazon Listings API.\n'
                    + 'Unchanged prices are skipped.'
                )) return;

                const $btn = $('#amz-push-cpn-btn');
                const html = $btn.html();
                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Pushing…');
                $.ajax({
                    url: '/amazon-cvr-cpn-push',
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': amzPefCsrf(), 'Accept': 'application/json' },
                    data: { skus: skus, _token: amzPefCsrf() },
                    timeout: 0,
                }).done(function(res) {
                    const st = (res && res.stats) || {};
                    amzPefToast(
                        (st.push_failed > 0 && !(st.pushed > 0)) ? 'error' : 'success',
                        (res && res.message) || 'Push CPN% done'
                    );
                    if (table && Array.isArray(skus)) {
                        skus.forEach(function(sku) {
                            const row = table.getRows().find(function(r) {
                                return amzPefSku(r.getData()) === sku;
                            });
                            if (!row) return;
                            const d = row.getData();
                            const cvr = amzPefCvr(d);
                            let cpn = amzPefInv(d) === 0 ? 0 : pefCpnForCvr(cvr);
                            // Snap to Amazon coupon tiers {0, 5, 10}
                            if (cpn > 0 && cpn <= 5) cpn = 5;
                            else if (cpn > 5) cpn = 10;
                            else cpn = 0;
                            row.update({ cpn_pct: String(cpn), _cpn_pct_applied: cpn });
                        });
                        table.redraw(true);
                    }
                }).fail(function(xhr) {
                    amzPefToast('error', 'Push CPN% failed: ' + (
                        (xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error)) || 'error'
                    ));
                }).always(function() {
                    $btn.prop('disabled', false).html(html);
                });
            });

            $('#pef-cvr-cpn-apply-btn').off('click.amzpef').on('click.amzpef', saveAndApplyCvrCpn);

            $(document).off('change.amzpef', '.amz-pef-appr-cb').on('change.amzpef', '.amz-pef-appr-cb', function() {
                if (!table) return;
                const sku = String($(this).attr('data-sku') || '');
                if (!sku) return;
                const row = table.getRows().find(function(r) {
                    return amzPefSku(r.getData()) === sku;
                });
                if (!row) return;
                if ($(this).is(':checked')) {
                    applyAmzApprDiscount(row);
                } else {
                    clearAmzApprDiscount(row, { save: true, redraw: true });
                }
            });

            // Push Prc — Std + PRMT% + CPN% → Amazon Listings our_price
            $(document).off('click.amzpef', '.amz-push-prc-btn').on('click.amzpef', '.amz-push-prc-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                if (!table) return;
                const $btn = $(this);
                if ($btn.prop('disabled')) return;
                const sku = String($btn.attr('data-sku') || '');
                const row = table.getRows().find(function(r) {
                    return amzPefSku(r.getData()) === sku;
                });
                if (!row) {
                    amzPefToast('error', 'Row not found');
                    return;
                }
                pushAmzStdPrcWithPromos($btn, row);
            });
        }
@endif
