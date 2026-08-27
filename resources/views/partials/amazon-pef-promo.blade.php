{{--
  Dil vs PRMT + PRMT% / CVR Disc. / Push Prc for Amazon tabulator.
  Dil vs PRMT rules store is shared across all marketplaces (dil_vs_prmt_shared).
  Amazon path: discount SPRICE via /save-amazon-sprice (no eBay Marketing APIs).
--}}
@php
    $amazonPefPromoPart = $amazonPefPromoPart ?? 'all';
    $amazonPageReloadPushEnabled = \App\Http\Controllers\MarketPlace\ChannelPromoPricingController::isPageReloadPushEnabled('amazon');
@endphp

@if($amazonPefPromoPart === 'css' || $amazonPefPromoPart === 'all')
        /* Dil vs PRMT / CVR Disc — same UX as /pricing-errors-fix */
        .amz-pef-promo-cell {
            font-size: inherit;
            font-weight: 600;
            color: #64748b;
        }
        .amz-pef-promo-cell.has-val { color: #0f172a; }
        .tabulator-row .tabulator-cell[tabulator-field="prmt_pct"],
        .tabulator-row .tabulator-cell[tabulator-field="cvr_discount"] {
            padding: 2px 4px !important;
        }
        /* Mint badge for CVR Disc. */
        .amz-cvr-discount-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 3px;
            color: #20c997;
            font-weight: 700;
            font-size: 12px;
            line-height: 1.2;
            white-space: nowrap;
        }
        .amz-cvr-discount-badge i {
            color: #20c997 !important;
            font-size: 11px !important;
        }
        .amz-cvr-discount-badge.is-zero {
            color: #adb5bd;
            font-weight: 600;
        }
        .amz-cvr-discount-badge.is-zero i {
            color: #adb5bd !important;
        }
        #pef-dil-prmt-table .pef-dil-prmt-input,
        #amz-cvr-disc-table .amz-cvr-disc-input {
            max-width: 90px;
            margin-left: auto;
            text-align: right;
            font-weight: 600;
        }
        #amz-prmt-menu-btn {
            background: #198754;
            border-color: #198754;
            color: #fff;
        }
        #amz-prmt-menu-btn:hover,
        #amz-prmt-menu-btn:focus,
        #amz-prmt-menu-btn.show {
            background: #157347;
            border-color: #146c43;
            color: #fff;
        }
        #amz-cvr-disc-menu-btn {
            background: #20c997;
            border-color: #20c997;
            color: #fff;
        }
        #amz-cvr-disc-menu-btn:hover,
        #amz-cvr-disc-menu-btn:focus,
        #amz-cvr-disc-menu-btn.show {
            background: #1aa179;
            border-color: #1aa179;
            color: #fff;
        }
        #amz-zero-sold-btn {
            background: #e83e8c;
            border-color: #e83e8c;
            color: #fff;
        }
        #amz-zero-sold-btn:hover,
        #amz-zero-sold-btn:focus {
            background: #d63384;
            border-color: #d63384;
            color: #fff;
        }
        #amz-zero-sold-table .amz-zero-sold-roi-input {
            max-width: 90px;
            margin-left: auto;
            text-align: right;
            font-weight: 600;
        }
        #amz-sprice-recalc-btn {
            background: #0d6efd;
            border-color: #0d6efd;
            color: #fff;
        }
        #amz-sprice-recalc-btn:hover,
        #amz-sprice-recalc-btn:focus {
            background: #0b5ed7;
            border-color: #0a58ca;
            color: #fff;
        }
        #amz-sprice-recalc-btn:disabled {
            opacity: 0.65;
        }
        @include('partials.cvr-up-dn', ['cvrUpDnPart' => 'css', 'cvrUpDnChannel' => 'amazon'])
        /* Push Prc job progress — yellow while running, green at 100% */
        #amz-push-prc-progress {
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
        #amz-push-prc-progress.active { display: block; }
        #amz-push-prc-progress.done {
            border-color: #86efac;
            background: #f0fdf4;
        }
        #amz-push-prc-progress .amz-push-prc-progress-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 4px;
            font-size: 12px;
            line-height: 1.2;
        }
        #amz-push-prc-progress-pct {
            font-weight: 700;
            font-variant-numeric: tabular-nums;
            color: #b45309;
            min-width: 2.5em;
        }
        #amz-push-prc-progress.done #amz-push-prc-progress-pct {
            color: #15803d;
        }
        #amz-push-prc-progress-msg {
            color: #64748b;
            flex: 1;
            text-align: right;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        #amz-push-prc-progress .amz-push-prc-bar {
            height: 10px;
            border-radius: 999px;
            background: #fde68a;
            overflow: hidden;
        }
        #amz-push-prc-progress.done .amz-push-prc-bar {
            background: #bbf7d0;
        }
        #amz-push-prc-progress .amz-push-prc-bar > span {
            display: block;
            height: 100%;
            width: 0%;
            background: #f59e0b; /* yellow — in progress */
            transition: width 0.2s ease, background 0.25s ease;
            border-radius: 999px;
        }
        #amz-push-prc-progress.done .amz-push-prc-bar > span {
            background: #22c55e; /* green — complete */
        }
        #amz-push-prc-progress.has-fail.done .amz-push-prc-bar > span {
            background: linear-gradient(90deg, #22c55e 70%, #f59e0b 100%);
        }
        .tabulator .tabulator-tableholder {
            overflow-x: auto !important;
        }
        .tabulator .tabulator-row .tabulator-cell {
            white-space: nowrap !important;
            text-overflow: clip !important;
        }
        @include('partials.analytics-column-visibility', ['colVisPart' => 'css'])
        .amz-reload-push-switch {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex: 0 0 auto;
            white-space: nowrap;
            padding: 4px 10px 4px 12px;
            border: 1px solid #86efac;
            border-radius: 999px;
            background: #f0fdf4;
            font-size: 12px;
            font-weight: 700;
            color: #15803d;
            line-height: 1.2;
            cursor: pointer;
            user-select: none;
            margin: 0;
        }
        .amz-reload-push-switch.is-off {
            border-color: #cbd5e1;
            background: #f8fafc;
            color: #64748b;
        }
        .amz-reload-push-switch .amz-reload-push-text {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .amz-reload-push-switch .amz-reload-push-state {
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .04em;
            text-transform: uppercase;
            color: #16a34a;
        }
        .amz-reload-push-switch.is-off .amz-reload-push-state {
            color: #94a3b8;
        }
        .amz-reload-push-switch > input[type="checkbox"] {
            appearance: none;
            -webkit-appearance: none;
            position: relative !important;
            float: none !important;
            left: auto !important;
            margin: 0 !important;
            flex: 0 0 36px;
            width: 36px;
            height: 20px;
            border: 0;
            border-radius: 999px;
            background: #86efac;
            box-shadow: inset 0 0 0 1px #4ade80;
            cursor: pointer;
        }
        .amz-reload-push-switch.is-off > input[type="checkbox"] {
            background: #cbd5e1;
            box-shadow: inset 0 0 0 1px #94a3b8;
        }
        .amz-reload-push-switch > input[type="checkbox"]::after {
            content: '';
            position: absolute;
            top: 2px;
            left: 2px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #fff;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .25);
            transition: left .15s ease;
        }
        .amz-reload-push-switch > input[type="checkbox"]:checked::after {
            left: 18px;
        }
        @push('page-title-after')
            <label class="amz-reload-push-switch is-off"
                id="amz-reload-push-wrap"
                title="Page load never pushes to Amazon. Use Push Prc, Dil vs PRMT, or the daily cron.">
                <span class="amz-reload-push-text">
                    Push on reload
                    <span class="amz-reload-push-state" id="amz-reload-push-label">Off</span>
                </span>
                <input type="checkbox" role="switch" id="amz-reload-push-switch" disabled>
            </label>
        @endpush
@endif

@if($amazonPefPromoPart === 'buttons' || $amazonPefPromoPart === 'all')
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm dropdown-toggle" id="amz-prmt-menu-btn"
                            data-bs-toggle="dropdown" aria-expanded="false"
                            title="Dil vs PRMT rules + Push Prmt% to Amazon">
                            <i class="fas fa-sliders-h"></i> Prmt%
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="amz-prmt-menu-btn">
                            <li>
                                <a class="dropdown-item" href="#" id="amz-dil-vs-prmt-btn">
                                    <i class="fas fa-sliders-h me-1 text-success"></i> Dil vs PRMT…
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="#" id="amz-push-prmt-btn">
                                    <i class="fas fa-upload me-1 text-success"></i> Push Prmt%
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm dropdown-toggle" id="amz-cvr-disc-menu-btn"
                            data-bs-toggle="dropdown" aria-expanded="false"
                            title="CVR Disc. column rules">
                            CVR Disc
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="amz-cvr-disc-menu-btn">
                            <li>
                                <a class="dropdown-item" href="#" id="amz-cvr-disc-rules-btn">
                                    Edit CVR Disc rules…
                                </a>
                            </li>
                        </ul>
                    </div>
                    <button type="button" class="btn btn-sm" id="amz-zero-sold-btn"
                        title="0 Sold: Dil color (Red / Green / Pink) → Target GROI%. Amazon has its own rule table. Apply sets S PRC so SGROI equals the target when A L30 = 0.">
                        0 Sold
                    </button>
                    @include('partials.cvr-up-dn', ['cvrUpDnPart' => 'buttons', 'cvrUpDnChannel' => 'amazon'])
                    <div id="amz-push-prc-progress" aria-live="polite" title="Push Prc background job — survives refresh; you can queue more SKUs anytime">
                        <div class="amz-push-prc-progress-meta">
                            <span id="amz-push-prc-progress-pct">0%</span>
                            <span id="amz-push-prc-progress-msg">Ready</span>
                            <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1" id="amz-push-prc-cancel-btn"
                                style="display:none;font-size:11px;line-height:1.2;" title="Cancel remaining Push Prc jobs">
                                Cancel
                            </button>
                        </div>
                        <div class="amz-push-prc-bar"><span id="amz-push-prc-progress-bar"></span></div>
                    </div>
@endif

@if($amazonPefPromoPart === 'modals' || $amazonPefPromoPart === 'all')
    {{-- CVR Disc: Amazon-only rules store amazon_cvr_vs_disc --}}
    <div class="modal fade" id="amzCvrDiscModal" tabindex="-1" aria-labelledby="amzCvrDiscModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title fs-6" id="amzCvrDiscModalLabel">
                        CVR Disc rules
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-2">
                    <p class="small text-muted mb-2">
                        Map CVR% slabs to <strong>CVR Disc.</strong> % (no 0% slab).
                        Used by the CVR Disc. column and Push Prc Sale discount.
                        <strong>CVR = 0%</strong> maps to <strong>0</strong> Disc%.
                    </p>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0" id="amz-cvr-disc-table">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:55%;">CVR%</th>
                                    <th style="width:45%;" class="text-end">Disc %</th>
                                </tr>
                            </thead>
                            <tbody id="amz-cvr-disc-tbody"></tbody>
                        </table>
                    </div>
                    <div class="small text-muted mt-2" id="amz-cvr-disc-status"></div>
                </div>
                <div class="modal-footer py-2 flex-wrap gap-1">
                    <button type="button" class="btn btn-sm btn-primary" id="amz-cvr-disc-apply-btn"
                        title="Save CVR Disc rules and write S PRC on matching SKUs (with PRMT, 0 Sold, CVR UP/DN)">
                        Apply
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="amzZeroSoldModal" tabindex="-1" aria-labelledby="amzZeroSoldModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title fs-6" id="amzZeroSoldModalLabel">
                        <i class="fas fa-sliders-h me-1"></i> 0 Sold
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-2">
                    <p class="small text-muted mb-2">
                        Map <strong>Dil color</strong> to <strong>Target GROI%</strong>
                        (<strong style="color:#a00211;">Red &lt;25% → 50</strong>,
                        <strong style="color:#28a745;">Green 25–50% → 60</strong>,
                        <strong style="color:#e83e8c;">Pink 50%+ → 70</strong> first time).
                        Amazon has its own rule table.
                        The <strong>0 Sold</strong> column shows the target when <strong>A L30 = 0</strong>.
                        <strong>Apply</strong> sets <strong>S PRC</strong> so
                        <strong>SGROI = Target GROI%</strong>
                        (<code>S PRC = (LP × (1 + GROI%/100) + Ship) / 0.80</code>).
                    </p>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0" id="amz-zero-sold-table">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:55%;">Dil Color</th>
                                    <th style="width:45%;" class="text-end">Target GROI%</th>
                                </tr>
                            </thead>
                            <tbody id="amz-zero-sold-tbody"></tbody>
                        </table>
                    </div>
                    <div class="small text-muted mt-2" id="amz-zero-sold-status"></div>
                </div>
                <div class="modal-footer py-2 flex-wrap gap-1">
                    <button type="button" class="btn btn-sm btn-primary" id="amz-zero-sold-save-btn"
                        title="Save Dil color → Target GROI% for Amazon only">
                        <i class="fas fa-save me-1"></i> Save Rule
                    </button>
                    <button type="button" class="btn btn-sm btn-primary" id="amz-zero-sold-apply-btn"
                        title="Save rules, then set S PRC so SGROI = Target on A L30 = 0 SKUs">
                        Apply
                    </button>
                </div>
            </div>
        </div>
    </div>

    @include('partials.cvr-up-dn', ['cvrUpDnPart' => 'modals', 'cvrUpDnChannel' => 'amazon'])

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
                        Shared Dil vs PRMT for <strong>all marketplaces</strong>:
                        <strong>0.01–3%</strong> … <strong>21–24%</strong>, then <strong>24–25%</strong>
                        (Dil 0% gets no PRMT). Save here updates eBay, Shopify, and every other Dil vs PRMT page.
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
        @include('partials.tabulator-column-autofit')
        @include('partials.analytics-column-visibility', ['colVisPart' => 'script'])
        // ==================== Dil vs PRMT / CVR Disc (same Dil store as /pricing-errors-fix) ====================
        const PEF_DIL_PRMT_DEFAULTS = (function() {
            const rules = [];
            let prmt = 12;
            for (let min = 0; min < 24; min += 3) {
                const max = min + 3;
                rules.push({
                    key: min + '-' + max,
                    label: min === 0 ? '0.01–3%' : (min + '–' + max + '%'),
                    prmt: prmt
                });
                prmt -= 1;
            }
            rules.push({ key: '24-25', label: '24–25%', prmt: 1 });
            return rules;
        })();
        const AMZ_CVR_DISC_DEFAULTS = [
            { key: '0.01-1', label: '0.01–1%', disc: 9 },
            { key: '1-1.5', label: '1–1.5%', disc: 8 },
            { key: '1.5-2', label: '1.5–2%', disc: 7 },
            { key: '2-3', label: '2–3%', disc: 6 },
            { key: '3-4', label: '3–4%', disc: 5 },
            { key: '4-5', label: '4–5%', disc: 4 },
            { key: '5-6', label: '5–6%', disc: 3 },
            { key: '6-6.5', label: '6–6.5%', disc: 2 },
            { key: '6.5-7', label: '6.5–7%', disc: 1 },
            { key: 'gt-7', label: '> 7%', disc: 0 },
        ];

        let pefDilPrmtRules = PEF_DIL_PRMT_DEFAULTS.map(function(r) { return Object.assign({}, r); });
        let amzCvrDiscRules = AMZ_CVR_DISC_DEFAULTS.map(function(r) { return Object.assign({}, r); });
        const AMZ_ZERO_SOLD_DEFAULTS = [
            { key: 'red', label: 'Red Dil (<25%)', groi: 50 },
            { key: 'green', label: 'Green Dil (25–50%)', groi: 60 },
            { key: 'pink', label: 'Pink Dil (50%+)', groi: 70 },
        ];
        let amzZeroSoldRules = AMZ_ZERO_SOLD_DEFAULTS.map(function(r) { return Object.assign({}, r); });
        let amzPageReloadPushEnabled = false;

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
            const stored = d && d.CVR_L30;
            if (stored !== '' && stored != null) {
                const cvr = Number(stored);
                if (isFinite(cvr) && cvr >= 0) return cvr;
            }
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
        /** Colored history dot (PDT daily roll-on) for PRMT% / Push Prc columns */
        function amzPefPromoHistoryDotHtml(sku, metric, pct) {
            if (!sku) return '';
            const n = Number(pct);
            const has = isFinite(n) && n > 0;
            let color = '#adb5bd';
            let label = metric;
            if (metric === 'prmt') { color = has ? '#0d6efd' : '#adb5bd'; label = 'PRMT%'; }
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
            if (payload.sprice === undefined && payload.prmt_pct === undefined) {
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
            const effective = (typeof selectedRows !== 'undefined' && selectedRows)
                ? selectedRows
                : ((typeof selectedSkus !== 'undefined' && selectedSkus) ? selectedSkus : null);
            if (!effective || !effective.size) return [];
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
            if (!isFinite(n) || n < 0.01) return 'none';
            if (n > 25) return 'gt-25';
            if (n >= 24) return '24-25';
            if (n >= 21) return '21-24';
            if (n >= 18) return '18-21';
            if (n >= 15) return '15-18';
            if (n >= 12) return '12-15';
            if (n >= 9) return '9-12';
            if (n >= 6) return '6-9';
            if (n >= 3) return '3-6';
            return '0-3';
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
        /** CVR → Disc% from amazon_cvr_vs_disc (CVR Disc column / Push Prc). */
        function amzDiscForCvr(cvr) {
            const key = pefCvrSlabKey(cvr);
            const rule = amzCvrDiscRules.find(function(r) { return r.key === key; });
            if (!rule) return 0;
            const n = Number(rule.disc);
            return isFinite(n) && n >= 0 ? n : 0;
        }
        /** CVR → CVR Disc. % (INV=0 → 0). Uses amazon_cvr_vs_disc rules only. */
        function computeAmzCvrDiscountPct(d) {
            if (!amzPefIsChildRow(d)) return null;
            if (amzPefInv(d) === 0) return 0;
            return amzDiscForCvr(amzPefCvr(d));
        }
        function fmtAmzCvrDiscountBadge(pct) {
            const n = Number(pct);
            if (!isFinite(n) || n <= 0) {
                return '<span class="amz-cvr-discount-badge is-zero" title="No CVR Disc">—</span>';
            }
            return '<span class="amz-cvr-discount-badge" title="CVR Disc rule → ' + n + '%">'
                + n + '%</span>';
        }
        function amzDilColorBand(dil) {
            const n = Number(dil);
            if (!isFinite(n) || n < 25) return 'red';
            if (n < 50) return 'green';
            return 'pink';
        }
        function amzDilColorHex(band) {
            if (band === 'red') return '#a00211';
            if (band === 'green') return '#28a745';
            if (band === 'pink') return '#e83e8c';
            return '#6c757d';
        }
        function amzIsZeroSoldRow(d) {
            if (!amzPefIsChildRow(d)) return false;
            if (amzPefInv(d) <= 0) return false;
            const sold = Number(d['A_L30'] != null ? d['A_L30'] : d.A_L30) || 0;
            return !(sold > 0);
        }
        function amzZeroSoldGroiForRow(d) {
            if (!amzIsZeroSoldRow(d)) return null;
            const key = amzDilColorBand(amzPefDil(d));
            const rule = amzZeroSoldRules.find(function(r) { return r.key === key; });
            const n = rule ? Number(rule.groi) : 0;
            return isFinite(n) ? n : 0;
        }
        function amzSpriceFromTargetGroi(d, roiPct) {
            const lp = parseFloat(d.LP_productmaster) || 0;
            if (!(lp > 0)) return 0;
            const ship = parseFloat(d.Ship_productmaster) || 0;
            const roi = isFinite(Number(roiPct)) ? Number(roiPct) : 0;
            const price = (lp * (1 + roi / 100) + ship) / 0.80;
            return (isFinite(price) && price > 0) ? amzPefRound2(price) : 0;
        }
        function renderAmzZeroSoldModalTable() {
            const $tb = $('#amz-zero-sold-tbody').empty();
            amzZeroSoldRules.forEach(function(r, idx) {
                const groi = isFinite(Number(r.groi)) ? Number(r.groi) : 0;
                const hex = amzDilColorHex(r.key);
                $tb.append(
                    '<tr data-key="' + String(r.key).replace(/"/g, '&quot;') + '">'
                    + '<td><span style="color:' + hex + ';font-weight:700;">'
                    + '<i class="fas fa-circle me-1" style="font-size:0.65em;"></i>'
                    + String(r.label || r.key) + '</span></td>'
                    + '<td class="text-end">'
                    + '<input type="number" class="form-control form-control-sm amz-zero-sold-roi-input" '
                    + 'step="0.1" value="' + groi + '" data-idx="' + idx + '">'
                    + '</td></tr>'
                );
            });
        }
        function readAmzZeroSoldRulesFromModal() {
            $('#amz-zero-sold-tbody tr').each(function() {
                const key = String($(this).attr('data-key') || '');
                const val = parseFloat($(this).find('.amz-zero-sold-roi-input').val());
                const rule = amzZeroSoldRules.find(function(r) { return r.key === key; });
                if (!rule) return;
                rule.groi = isFinite(val) ? val : 0;
            });
            return amzZeroSoldRules.map(function(r) {
                return { key: r.key, label: r.label, groi: Number(r.groi) || 0 };
            });
        }
        async function loadAmzZeroSoldRules() {
            $('#amz-zero-sold-status').text('Loading…');
            try {
                const res = await $.ajax({
                    url: '/channel-promo-pricing/amazon/zero-sold-prc',
                    method: 'GET',
                    dataType: 'json',
                });
                if (res && Array.isArray(res.rules) && res.rules.length) {
                    amzZeroSoldRules = res.rules.map(function(r) { return Object.assign({}, r); });
                }
                renderAmzZeroSoldModalTable();
                $('#amz-zero-sold-status').text(res && res.is_default
                    ? 'Using first-time defaults (Red 50 / Green 60 / Pink 70). Save Rule to keep Amazon’s table.'
                    : 'Loaded saved 0 Sold rules for Amazon.');
            } catch (e) {
                renderAmzZeroSoldModalTable();
                $('#amz-zero-sold-status').text('Could not load saved rules — using defaults.');
            }
        }
        function saveAmzZeroSoldRules() {
            const rules = readAmzZeroSoldRulesFromModal();
            return $.ajax({
                url: '/channel-promo-pricing/amazon/zero-sold-prc',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': amzPefCsrf(), 'Accept': 'application/json' },
                data: { rules: rules, _token: amzPefCsrf() },
            }).then(function(res) {
                if (res && Array.isArray(res.rules)) {
                    amzZeroSoldRules = res.rules.map(function(r) { return Object.assign({}, r); });
                    renderAmzZeroSoldModalTable();
                }
                $('#amz-zero-sold-status').text('Saved.');
                return res;
            });
        }
        async function applyAmzZeroSoldToTargets(targets, label) {
            readAmzZeroSoldRulesFromModal();
            applyAmzCombinedPlanToTargets(targets, label, {
                toastLabel: '0 Sold',
                match: function(d) {
                    return amzIsZeroSoldRow(d) && (parseFloat(d.LP_productmaster) || 0) > 0;
                },
            });
        }

        function renderAmzCvrDiscModalTable() {
            const $tb = $('#amz-cvr-disc-tbody').empty();
            amzCvrDiscRules.forEach(function(r, idx) {
                const disc = isFinite(Number(r.disc)) ? Number(r.disc) : 0;
                $tb.append(
                    '<tr data-key="' + String(r.key).replace(/"/g, '&quot;') + '">'
                    + '<td>' + String(r.label || r.key) + '</td>'
                    + '<td class="text-end">'
                    + '<input type="number" class="form-control form-control-sm amz-cvr-disc-input" '
                    + 'min="0" step="0.1" value="' + disc + '" data-idx="' + idx + '">'
                    + '</td></tr>'
                );
            });
        }
        function readAmzCvrDiscRulesFromModal() {
            $('#amz-cvr-disc-tbody tr').each(function() {
                const key = String($(this).attr('data-key') || '');
                const val = parseFloat($(this).find('.amz-cvr-disc-input').val());
                const rule = amzCvrDiscRules.find(function(r) { return r.key === key; });
                if (!rule) return;
                rule.disc = (isFinite(val) && val >= 0) ? val : 0;
            });
            return amzCvrDiscRules.map(function(r) {
                return { key: r.key, label: r.label, disc: Number(r.disc) || 0 };
            });
        }
        async function loadAmzCvrDiscRules() {
            $('#amz-cvr-disc-status').text('Loading…');
            try {
                const res = await $.ajax({
                    url: '/amazon-cvr-disc',
                    method: 'GET',
                    dataType: 'json',
                });
                if (res && Array.isArray(res.rules) && res.rules.length) {
                    amzCvrDiscRules = res.rules.map(function(r) { return Object.assign({}, r); })
                        .filter(function(r) { return r.key !== 'eq-0'; });
                }
                renderAmzCvrDiscModalTable();
                $('#amz-cvr-disc-status').text(res && res.is_default
                    ? 'Using defaults (not saved yet).'
                    : 'Loaded saved CVR Disc rules.');
            } catch (e) {
                renderAmzCvrDiscModalTable();
                $('#amz-cvr-disc-status').text('Could not load saved rules — using defaults.');
            }
        }
        function saveAmzCvrDiscRules() {
            const rules = readAmzCvrDiscRulesFromModal();
            return $.ajax({
                url: '/amazon-cvr-disc',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': amzPefCsrf(), 'Accept': 'application/json' },
                data: { rules: rules, _token: amzPefCsrf() },
            }).then(function(res) {
                if (res && Array.isArray(res.rules)) {
                    amzCvrDiscRules = res.rules.map(function(r) { return Object.assign({}, r); })
                        .filter(function(r) { return r.key !== 'eq-0'; });
                    renderAmzCvrDiscModalTable();
                }
                return res;
            });
        }
        async function saveAndApplyAmzCvrDisc() {
            const $btn = $('#amz-cvr-disc-apply-btn');
            const html = $btn.html();
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
            try {
                await saveAmzCvrDiscRules();
                const picked = collectAmzPromoApplyTargets('No rows to apply CVR Disc');
                if (!picked.cancelled) {
                    applyAmzCombinedPlanToTargets(picked.targets, picked.label, {
                        toastLabel: 'CVR Disc',
                        match: function(d) {
                            return (Number(computeAmzCvrDiscountPct(d)) || 0) > 0;
                        },
                    });
                    $('#amz-cvr-disc-status').text('Saved. Applied to matching SKUs.');
                } else {
                    $('#amz-cvr-disc-status').text('Saved. CVR Disc. column updated.');
                    if (table) {
                        try { table.getColumn('cvr_discount') && table.redraw(true); } catch (e) { /* ignore */ }
                    }
                }
                const modalEl = document.getElementById('amzCvrDiscModal');
                if (modalEl) bootstrap.Modal.getOrCreateInstance(modalEl).hide();
            } catch (e) {
                amzPefToast('error', 'Failed to save CVR Disc rules');
                $('#amz-cvr-disc-status').text('Save failed.');
            } finally {
                $btn.prop('disabled', false).html(html);
            }
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
                    ? 'Using first-time defaults (0.01–3 … 21–24, 24–25). Save applies to all marketplaces.'
                    : 'Loaded shared Dil vs PRMT rules (all marketplaces).');
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
                $('#pef-dil-prmt-status').text('Saved — shared Dil vs PRMT for all marketplaces.');
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

        async function applyDilPrmtToTargets(targets, label) {
            readDilPrmtRulesFromModal();
            applyAmzCombinedPlanToTargets(targets, label, {
                toastLabel: 'Dil vs PRMT',
                writePrmtOnInvZero: true,
                skipWhenNoSale: true,
            });
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
                    if (kind === 'prmt') {
                        saveAmzSpriceFromPromo(item.row, Number(d.SPRICE) || 0, true, { prmt_pct: 0 });
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
                    title: 'CVR Disc.',
                    field: 'cvr_discount',
                    width: 64,
                    hozAlign: 'center',
                    vertAlign: 'middle',
                    headerSort: true,
                    headerTooltip: 'CVR Disc. — from CVR Disc rules. INV=0 → 0%. Read-only.',
                    sorter: function(a, b, aRow, bRow) {
                        const av = computeAmzCvrDiscountPct(aRow.getData()) || 0;
                        const bv = computeAmzCvrDiscountPct(bRow.getData()) || 0;
                        return av - bv;
                    },
                    formatter: function(cell) {
                        const d = cell.getRow().getData() || {};
                        if (d.is_parent_summary) return '';
                        if (!amzPefIsChildRow(d)) return '';
                        const pct = computeAmzCvrDiscountPct(d);
                        const cvr = amzPefCvr(d);
                        const base = Number(d.STANDARD_PRICE) > 0
                            ? Number(d.STANDARD_PRICE)
                            : (Number(d.price) || 0);
                        const dollars = (pct > 0 && base > 0) ? amzPefRound2(base * (pct / 100)) : 0;
                        const tip = 'CVR ' + (isFinite(cvr) ? cvr.toFixed(1) : '0') + '%'
                            + ' → discount ' + (pct || 0) + '%'
                            + (dollars > 0 ? (' ≈ $' + dollars.toFixed(2) + ' off Std/Price') : '');
                        return '<span title="' + amzPefEscAttr(tip) + '">'
                            + fmtAmzCvrDiscountBadge(pct) + '</span>';
                    },
                },
                {
                    title: '0 Sold',
                    field: 'zero_sold',
                    width: 100,
                    hozAlign: 'center',
                    vertAlign: 'middle',
                    headerSort: true,
                    headerTooltip: '0 Sold target price from Dil color → GROI% (Red 50 / Green 60 / Pink 70). Format: $price (GROI). N/A when A L30 > 0 or no LP.',
                    sorter: function(a, b, aRow, bRow) {
                        const ad = aRow.getData() || {};
                        const bd = bRow.getData() || {};
                        const av = amzSpriceFromTargetGroi(ad, amzZeroSoldGroiForRow(ad));
                        const bv = amzSpriceFromTargetGroi(bd, amzZeroSoldGroiForRow(bd));
                        return (av || 0) - (bv || 0);
                    },
                    formatter: function(cell) {
                        const d = cell.getRow().getData() || {};
                        if (d.is_parent_summary || !amzPefIsChildRow(d)) return '';
                        const roi = amzZeroSoldGroiForRow(d);
                        const lp = parseFloat(d.LP_productmaster) || 0;
                        const price = amzSpriceFromTargetGroi(d, roi);
                        if (!amzIsZeroSoldRow(d) || !(lp > 0) || roi == null || !(price > 0)) {
                            return '<span style="color:#999;">N/A</span>';
                        }
                        const band = amzDilColorBand(amzPefDil(d));
                        const hex = amzDilColorHex(band);
                        const label = band === 'red' ? 'Red' : (band === 'green' ? 'Green' : 'Pink');
                        return '<div class="d-flex flex-column align-items-center justify-content-center" '
                            + 'style="gap:1px;line-height:1.1;" title="0 Sold · '
                            + label + ' Dil → $' + price.toFixed(2) + ' at ' + roi + '% GROI">'
                            + '<span style="color:' + hex + ';font-weight:600;font-size:14px;">$' + price.toFixed(2) + '</span>'
                            + '<span style="color:#007bff;font-weight:600;">(' + Math.round(roi) + ')</span>'
                            + '</div>';
                    },
                },
                ...(typeof cvrUpDnColumn === 'function' ? [cvrUpDnColumn()] : []),
                ...(typeof tDiscountsColumn === 'function' ? [tDiscountsColumn(computeAmzTDiscountsPct)] : []),
                {
                    title: 'Push Prc',
                    field: 'push_prc',
                    width: 78,
                    hozAlign: 'center',
                    vertAlign: 'middle',
                    headerSort: false,
                    headerTooltip: 'Push Prc: Your=Std. 0 Sold → Sale=GROI. Sold → Sale=Std−(PRMT+CVR Disc+CVR UP/DN). Sale=Biz=Min. Dot = PDT history.',
                    formatter: function(cell) {
                        const d = cell.getRow().getData() || {};
                        if (!amzPefIsChildRow(d)) return '';
                        const sku = amzPefSku(d);
                        const plan = computeAmzPushPrcPlan(d);
                        const status = String(d.PUSH_PRC_STATUS || '');
                        const histVal = d.PUSH_PRC_VALUE != null ? d.PUSH_PRC_VALUE : (plan ? plan.effective : null);
                        const dot = amzPefPromoHistoryDotHtml(sku, 'push_prc', histVal);
                        if (!plan || !(plan.std > 0)) {
                            return '<span style="display:inline-flex;align-items:center;justify-content:center;gap:4px;">'
                                + dot + '<span style="color:#adb5bd;" title="Set Std Prc first">—</span></span>';
                        }
                        let icon = '<i class="fas fa-upload"></i>';
                        let color = '#FF9900';
                        const tipSale = plan.sale != null ? plan.sale : plan.std;
                        let tip = 'Your $' + plan.std.toFixed(2)
                            + ' · Sale $' + tipSale.toFixed(2)
                            + formatAmzPushPrcDiscNote(plan)
                            + ' · Max $' + plan.max.toFixed(2)
                            + ' · Min $' + plan.min.toFixed(2)
                            + ' · Biz $' + plan.business.toFixed(2);
                        if (status === 'pushed') {
                            icon = '<i class="fa-solid fa-check-double"></i>';
                            color = '#28a745';
                            tip = 'Pushed — click to push again. Last effective $'
                                + (Number(d.PUSH_PRC_VALUE) || plan.effective).toFixed(2);
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
                            + 'data-price="' + plan.effective.toFixed(2) + '" '
                            + 'title="' + amzPefEscAttr(tip) + '" '
                            + 'style="border:none;background:none;cursor:pointer;color:' + color
                            + ';padding:0;line-height:1;vertical-align:middle;">'
                            + icon + '</button></span>';
                    },
                    cellClick: function(e, cell) {
                        // Tabulator cellClick runs before document bubble; stopPropagation
                        // would block the delegated .amz-push-prc-btn handler — run push here.
                        const btn = e.target.closest('.amz-push-prc-btn');
                        if (btn) {
                            e.stopPropagation();
                            e.preventDefault();
                            if (btn.disabled) return false;
                            // Multi-select → bulk push all selected (same as toolbar Push Prc)
                            const selected = collectAmzPefSelectedRows();
                            const clickedSku = amzPefSku(cell.getRow().getData());
                            if (selected.length > 1 && selected.some(function(t) {
                                return amzPefSku(t.d) === clickedSku;
                            })) {
                                bulkPushAmzPrcSelected();
                                return false;
                            }
                            pushAmzStdPrcWithPromos($(btn), cell.getRow());
                            return false;
                        }
                        if (e.target.closest('.view-sku-chart') || e.target.closest('.amz-pef-hist-dot')) {
                            e.stopPropagation();
                            return false;
                        }
                    },
                },
            ];
        }

        /**
         * Live rule stack for this SKU (not stale stored PRMT).
         * PRMT = Dil slab (INV=0 or Dil>25 → 0)
         * CVR Disc = CVR slab (INV=0 or CVR≤0 → 0)
         * 0 Sold = A L30=0 and INV>0 → GROI target price
         * CVR UP/DN = CVR 30 vs 45 (INV=0 → 0)
         */
        function computeAmzLivePrmtPct(d) {
            if (!amzPefIsChildRow(d) || amzPefInv(d) === 0) return 0;
            if (typeof pefPrmtForDil !== 'function') return 0;
            const n = Number(pefPrmtForDil(amzPefDil(d)));
            return isFinite(n) && n > 0 ? n : 0;
        }
        function computeAmzRuleStack(d) {
            const prmt = computeAmzLivePrmtPct(d);
            const cvrDisc = Math.max(0, Number(typeof computeAmzCvrDiscountPct === 'function' ? computeAmzCvrDiscountPct(d) : 0) || 0);
            const cvrUpDn = (typeof computeCvrUpDnPct === 'function') ? (Number(computeCvrUpDnPct(d)) || 0) : 0;
            const zeroSold = typeof amzIsZeroSoldRow === 'function' && amzIsZeroSoldRow(d);
            const zeroSoldGroi = zeroSold ? amzZeroSoldGroiForRow(d) : null;
            const zeroSoldPrice = zeroSold ? amzSpriceFromTargetGroi(d, zeroSoldGroi) : null;
            const totalDisc = amzPefRound2(Math.min(99.99, Math.max(0, prmt + cvrDisc + cvrUpDn)));
            return {
                prmt: prmt,
                cvrDisc: cvrDisc,
                cvrUpDn: cvrUpDn,
                zeroSold: !!zeroSold,
                zeroSoldGroi: zeroSoldGroi,
                zeroSoldPrice: (zeroSoldPrice > 0) ? zeroSoldPrice : null,
                totalDisc: totalDisc,
            };
        }
        function formatAmzPushPrcDiscNote(plan) {
            const parts = [];
            if (plan.zeroSold) {
                parts.push('0 Sold GROI ' + (plan.zeroSoldGroi != null ? plan.zeroSoldGroi : '') + '%');
                return parts.length ? ' (' + parts.join(' + ') + ')' : '';
            }
            if (plan.prmt) parts.push('PRMT ' + plan.prmt + '%');
            if (plan.cvrDisc) parts.push('CVR Disc ' + plan.cvrDisc + '%');
            if (plan.cvrUpDn) parts.push('CVR UP/DN ' + (plan.cvrUpDn > 0 ? '+' : '') + plan.cvrUpDn + '%');
            return parts.length ? ' (' + parts.join(' + ') + ')' : '';
        }
        /**
         * Push Prc plan per SKU:
         *  0 Sold → Sale = GROI target (0 Sold owns price; UP/DN does not stack)
         *  Sold   → Sale = Std × (1 − (PRMT + CVR Disc + CVR UP/DN)/100)
         *  Your = Std; Sale = Business = Min
         */
        function computeAmzTDiscountsPct(d) {
            const stack = computeAmzRuleStack(d);
            if (stack.zeroSold) return 0;
            return stack.totalDisc;
        }
        function computeAmzPushPrcPlan(d) {
            const std = Number(d.STANDARD_PRICE) || 0;
            if (!(std > 0)) return null;
            const stack = computeAmzRuleStack(d);
            let sale = null;
            if (stack.zeroSold && stack.zeroSoldPrice != null) {
                sale = stack.zeroSoldPrice;
                if (!(sale >= 0.01)) sale = null;
            } else if (stack.totalDisc > 0 && stack.totalDisc < 100) {
                sale = amzPefRound2(std * (1 - (stack.totalDisc / 100)));
                if (!(sale >= 0.01) || sale >= std) sale = null;
            }
            const saleBase = sale != null ? sale : amzPefRound2(std);
            const max = amzPefRound2(std * 1.10);
            const min = saleBase;
            const business = saleBase;
            const effective = sale != null ? sale : std;
            return {
                std: amzPefRound2(std),
                sale: sale,
                max: max,
                min: min,
                business: business,
                prmt: stack.prmt,
                cvrDisc: stack.cvrDisc,
                cvrUpDn: stack.cvrUpDn,
                zeroSold: stack.zeroSold,
                zeroSoldGroi: stack.zeroSoldGroi,
                totalDisc: stack.totalDisc,
                effective: effective,
            };
        }
        /** @deprecated use computeAmzPushPrcPlan — kept for any leftover callers */
        function computeAmzPushPrcFromStd(d) {
            const plan = computeAmzPushPrcPlan(d);
            return plan ? plan.effective : null;
        }

        function collectAmzPromoApplyTargets(emptyMsg) {
            const selected = collectAmzPefSelectedRows();
            if (selected.length) {
                return { targets: selected, label: 'selected', cancelled: false };
            }
            const visible = collectAmzPefVisibleRows();
            if (!visible.length) {
                amzPefToast('error', emptyMsg || 'No rows to apply');
                return { targets: [], label: 'all visible', cancelled: true };
            }
            if (!confirm('No rows selected — apply to all ' + visible.length + ' visible row(s)?')) {
                return { targets: [], label: 'all visible', cancelled: true };
            }
            return { targets: visible, label: 'all visible', cancelled: false };
        }

        /**
         * Write S PRC from the live Push Prc stack (PRMT + CVR Disc + 0 Sold + CVR UP/DN).
         * Each SKU uses its own matching data. Local save only — no Amazon API.
         */
        function applyAmzCombinedPlanToTargets(targets, label, opts) {
            opts = opts || {};
            const matchFn = typeof opts.match === 'function' ? opts.match : null;
            const toastLabel = opts.toastLabel || 'Rules';
            let ok = 0;
            let skipped = 0;
            let unmatched = 0;
            if (!targets.length) {
                amzPefToast('error', toastLabel + ': no rows to apply');
                return 0;
            }
            for (let i = 0; i < targets.length; i++) {
                const item = targets[i];
                const d = item.row.getData();
                if (!amzPefIsChildRow(d)) { skipped++; continue; }
                if (amzPefInv(d) === 0) {
                    if (opts.writePrmtOnInvZero) {
                        item.row.update({ prmt_pct: '0', _prmt_pct_applied: 0 });
                        saveAmzSpriceFromPromo(item.row, Number(d.SPRICE) || 0, true, { prmt_pct: 0 });
                    }
                    skipped++;
                    continue;
                }
                if (matchFn && !matchFn(d)) { unmatched++; continue; }
                const plan = computeAmzPushPrcPlan(d);
                if (!plan || !(plan.effective > 0)) { skipped++; continue; }
                if (opts.skipWhenNoSale && plan.sale == null) {
                    item.row.update({
                        prmt_pct: String(plan.prmt),
                        _prmt_pct_applied: plan.prmt,
                    });
                    saveAmzSpriceFromPromo(item.row, Number(d.SPRICE) || 0, true, { prmt_pct: plan.prmt });
                    skipped++;
                    continue;
                }
                applyAmzPushPrcToSpriceRow(item.row, plan, null);
                saveAmzSpriceFromPromo(item.row, plan.effective, true, { prmt_pct: plan.prmt });
                ok++;
            }
            if (table) {
                try { table.redraw(true); } catch (e) { /* ignore */ }
            }
            amzPefToast(
                ok ? 'success' : 'error',
                toastLabel + ' (' + label + '): S PRC → ' + ok + ' matching SKU(s)'
                    + (unmatched ? ('; ' + unmatched + ' no match') : '')
                    + (skipped ? ('; skipped ' + skipped) : '') + '.'
            );
            return ok;
        }

        function applyCvrUpDnToMatchingSkus() {
            const picked = collectAmzPromoApplyTargets('No rows to apply CVR UP/DN');
            if (picked.cancelled) return;
            applyAmzCombinedPlanToTargets(picked.targets, picked.label, {
                toastLabel: 'CVR UP/DN',
                match: function(d) {
                    if (amzIsZeroSoldRow(d)) return false;
                    return (typeof computeCvrUpDnPct === 'function')
                        && (Number(computeCvrUpDnPct(d)) || 0) !== 0;
                },
            });
        }
        window.applyCvrUpDnToMatchingSkus = applyCvrUpDnToMatchingSkus;

        /** Apply result price to S PRC + margin columns (SGPFT / SGROI / SROI / Spft%). */
        function applyAmzPushPrcToSpriceRow(row, plan, saveRes) {
            const updates = {
                SPRICE: plan.effective,
                has_custom_sprice: true,
                PUSH_PRC_VALUE: plan.effective,
                prmt_pct: String(plan.prmt),
                _prmt_pct_applied: plan.prmt,
            };
            if (plan.zeroSold && plan.zeroSoldGroi != null) {
                updates.ZERO_SOLD_PRC_GROI = plan.zeroSoldGroi;
            }
            if (saveRes && saveRes.sgpft_percent !== undefined) updates.SGPFT = saveRes.sgpft_percent;
            if (saveRes && saveRes.spft_percent !== undefined) updates['Spft%'] = saveRes.spft_percent;
            if (saveRes && saveRes.sroi_percent !== undefined) updates.SROI = saveRes.sroi_percent;
            if (saveRes && saveRes.sgroi_percent !== undefined) updates.SGROI = saveRes.sgroi_percent;
            row.update(updates);
            try { row.reformat(); } catch (e) { /* ignore */ }
        }

        function saveAmzPushPrcSprice(sku, plan, opts) {
            opts = opts || {};
            const data = {
                sku: sku,
                sprice: plan.effective,
                prmt_pct: plan.prmt,
                _token: amzPefCsrf(),
            };
            if (opts.recordPushPrc) data.record_push_prc = 1;
            return $.ajax({
                url: '/save-amazon-sprice',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': amzPefCsrf(), 'Accept': 'application/json' },
                data: data,
            });
        }

        /**
         * Clear S PRC then autofill from Push Prc formula (no Amazon push).
         * Selected rows if any; otherwise all visible child rows with Std Prc.
         */
        function clearAndAutopopulateAmzSprice() {
            if (!table) {
                amzPefToast('error', 'Load data first');
                return;
            }
            let targets = collectAmzPefSelectedRows();
            let scopeLabel = 'selected';
            if (!targets.length) {
                targets = collectAmzPefVisibleRows();
                scopeLabel = 'all visible';
            }
            let skippedInv = 0;
            const ready = targets.filter(function(t) {
                if (amzPefInv(t.d) === 0) {
                    skippedInv++;
                    return false;
                }
                const plan = computeAmzPushPrcPlan(t.d);
                return plan && plan.std > 0;
            });
            if (!ready.length) {
                amzPefToast(
                    'error',
                    skippedInv
                        ? 'No rows to refill (skipped ' + skippedInv + ' with INV = 0)'
                        : 'No rows with Std Prc to refill'
                );
                return;
            }
            if (!confirm(
                'Clear S PRC and refill for ' + ready.length + ' ' + scopeLabel + ' SKU(s)?'
                + (skippedInv ? ('\n(Skip ' + skippedInv + ' with INV = 0)') : '')
                + '\n\nFormula (same as Push Prc, no Amazon push):\n'
                + '0 Sold → GROI target\n'
                + 'Sold → Std × (1 − (PRMT + CVR Disc + CVR UP/DN)/100)\n'
                + 'If no rule → S PRC = Std'
            )) return;

            const $btn = $('#amz-sprice-recalc-btn');
            const html = $btn.html();
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>…');

            // Clear first (grid)
            ready.forEach(function(t) {
                t.row.update({
                    SPRICE: 0,
                    SGPFT: 0,
                    'Spft%': 0,
                    SROI: 0,
                    SGROI: 0,
                    has_custom_sprice: false,
                    SPRICE_STATUS: null,
                });
            });
            if (table) table.redraw(true);

            let ok = 0;
            let fail = 0;
            let i = 0;
            function next() {
                if (i >= ready.length) {
                    $btn.prop('disabled', false).html(html);
                    if (table) table.redraw(true);
                    amzPefToast(
                        fail && !ok ? 'error' : 'success',
                        'sprice ?: ' + ok + ' filled'
                            + (fail ? (', ' + fail + ' failed') : '')
                            + (skippedInv ? (', ' + skippedInv + ' skipped INV=0') : '')
                    );
                    return;
                }
                const item = ready[i++];
                const plan = computeAmzPushPrcPlan(item.row.getData());
                $btn.html('<i class="fas fa-spinner fa-spin"></i> ' + i + '/' + ready.length);
                if (!plan || !(plan.effective > 0)) {
                    fail++;
                    next();
                    return;
                }
                saveAmzPushPrcSprice(amzPefSku(item.d), plan, { recordPushPrc: false })
                    .done(function(saveRes) {
                        applyAmzPushPrcToSpriceRow(item.row, plan, saveRes);
                        ok++;
                    })
                    .fail(function() {
                        applyAmzPushPrcToSpriceRow(item.row, plan, null);
                        fail++;
                    })
                    .always(function() { next(); });
            }
            next();
        }

        let amzPushPrcPollTimer = null;
        let amzPushPrcLastToastKey = '';

        function planToAmzPushPrcQueueItem(d, plan) {
            const asin = (d.asin && String(d.asin).trim() !== '') ? String(d.asin).trim() : null;
            return {
                sku: amzPefSku(d),
                asin: asin,
                std: plan.std,
                sale: plan.sale,
                max: plan.max,
                min: plan.min,
                business: plan.business,
                effective: plan.effective,
                prmt: plan.prmt,
                cpn: 0,
                cvr_disc: plan.cvrDisc,
                cvr_up_dn: plan.cvrUpDn,
                zero_sold: !!plan.zeroSold,
            };
        }

        /** Push Prc progress bar — yellow while jobs run, green at 100%. Survives refresh via server poll. */
        function setAmzPushPrcProgress(opts) {
            opts = opts || {};
            const $box = $('#amz-push-prc-progress');
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

            if (active || finished) $box.addClass('active');
            else $box.removeClass('active');

            $box.toggleClass('done', finished || (!active && pct >= 100));
            $box.toggleClass('has-fail', fail > 0);

            $('#amz-push-prc-progress-pct').text(pct + '%');
            $('#amz-push-prc-progress-bar').css('width', pct + '%');
            $('#amz-push-prc-cancel-btn').toggle(!!active);

            let msg = opts.msg || '';
            if (!msg && total) {
                msg = done + '/' + total + ' jobs · ' + ok + ' ok'
                    + (fail ? (' · ' + fail + ' failed') : '');
            }
            $('#amz-push-prc-progress-msg').text(msg || 'Ready');

            if (finished) {
                clearTimeout(setAmzPushPrcProgress._hideTimer);
                setAmzPushPrcProgress._hideTimer = setTimeout(function() {
                    if (!$box.hasClass('done')) return;
                    $box.removeClass('active done has-fail');
                    $('#amz-push-prc-progress-bar').css('width', '0%');
                    $('#amz-push-prc-progress-pct').text('0%');
                    $('#amz-push-prc-progress-msg').text('Ready');
                    $('#amz-push-prc-cancel-btn').hide();
                }, 8000);
            }
        }

        function applyAmzPushPrcTaskStatusesToTable(tasks) {
            if (!table || !Array.isArray(tasks)) return;
            const bySku = {};
            tasks.forEach(function(t) {
                if (t && t.sku) bySku[String(t.sku).toUpperCase()] = t;
            });
            table.getRows().forEach(function(row) {
                const d = row.getData();
                if (!amzPefIsChildRow(d)) return;
                const sku = amzPefSku(d).toUpperCase();
                const t = bySku[sku];
                if (!t) return;
                const st = String(t.status || '');
                if (st === 'ok') {
                    row.update({
                        PUSH_PRC_STATUS: 'pushed',
                        PUSH_PRC_VALUE: t.effective != null ? t.effective : d.PUSH_PRC_VALUE,
                        SPRICE: t.effective != null ? t.effective : d.SPRICE,
                        has_custom_sprice: true,
                    });
                } else if (st === 'failed') {
                    row.update({ PUSH_PRC_STATUS: 'error' });
                } else if (st === 'pushing') {
                    row.update({ PUSH_PRC_STATUS: 'processing' });
                } else if (st === 'pending' || st === 'queued') {
                    row.update({ PUSH_PRC_STATUS: 'processing' });
                }
            });
            try { table.redraw(true); } catch (e) { /* ignore */ }
        }

        function stopAmzPushPrcPoll() {
            if (amzPushPrcPollTimer) {
                clearInterval(amzPushPrcPollTimer);
                amzPushPrcPollTimer = null;
            }
        }

        function pollAmzPushPrcStatus() {
            $.ajax({
                url: '/amazon-push-prc-status',
                method: 'GET',
                headers: { 'Accept': 'application/json' },
                timeout: 20000,
            }).done(function(resp) {
                if (!resp) return;
                const active = !!resp.active;
                const total = Number(resp.total) || 0;
                const done = Number(resp.done_count) || 0;
                const ok = Number(resp.ok_count) || 0;
                const fail = Number(resp.fail_count) || 0;
                const pct = Number(resp.pct) || 0;
                const jobStatus = resp.job && resp.job.status ? String(resp.job.status) : 'idle';

                if (total > 0 || active) {
                    setAmzPushPrcProgress({
                        active: active,
                        done: done,
                        total: total,
                        ok: ok,
                        fail: fail,
                        pct: pct,
                        msg: resp.message || (resp.job && resp.job.last_message) || '',
                    });
                }
                applyAmzPushPrcTaskStatusesToTable(resp.tasks || []);

                if (!active) {
                    stopAmzPushPrcPoll();
                    const toastKey = jobStatus + '|' + ok + '|' + fail + '|' + total;
                    if (total > 0 && toastKey !== amzPushPrcLastToastKey
                        && (jobStatus === 'completed' || jobStatus === 'failed')) {
                        amzPushPrcLastToastKey = toastKey;
                        amzPefToast(
                            fail && !ok ? 'error' : 'success',
                            resp.message || ('Push Prc: ' + ok + ' ok' + (fail ? (', ' + fail + ' failed') : ''))
                        );
                    }
                }
            }).fail(function() {
                // Keep polling — worker may still be fine
            });
        }

        function startAmzPushPrcPoll() {
            stopAmzPushPrcPoll();
            amzPushPrcPollTimer = setInterval(pollAmzPushPrcStatus, 3000);
            pollAmzPushPrcStatus();
        }

        /** Queue SKUs for background Push Prc (append-safe while a job is running). */
        function queueAmzPushPrcItems(items, opts) {
            opts = opts || {};
            if (!items || !items.length) {
                if (!opts.silent) amzPefToast('error', 'Nothing to queue');
                return Promise.resolve(null);
            }
            clearTimeout(setAmzPushPrcProgress._hideTimer);
            setAmzPushPrcProgress({
                active: true,
                done: 0,
                total: items.length,
                ok: 0,
                fail: 0,
                msg: 'Queuing ' + items.length + ' SKU(s)…',
            });

            return $.ajax({
                url: '/amazon-push-prc',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': amzPefCsrf(), 'Accept': 'application/json' },
                data: {
                    _token: amzPefCsrf(),
                    items: items,
                },
                timeout: 60000,
            }).done(function(resp) {
                if (!opts.silent) {
                    amzPefToast(
                        'success',
                        (resp && resp.message)
                            || ('Queued ' + items.length + ' Push Prc job(s) — safe to refresh')
                    );
                } else {
                    amzPefToast(
                        'success',
                        'Reload push: ' + items.length + ' SKU(s) queued'
                    );
                }
                if (resp) {
                    setAmzPushPrcProgress({
                        active: !!resp.active,
                        done: Number(resp.done_count) || 0,
                        total: Number(resp.total) || items.length,
                        ok: Number(resp.ok_count) || 0,
                        fail: Number(resp.fail_count) || 0,
                        pct: Number(resp.pct) || 0,
                        msg: resp.message || '',
                    });
                }
                startAmzPushPrcPoll();
            }).fail(function(xhr) {
                const msg = (xhr.responseJSON && xhr.responseJSON.message)
                    || 'Could not queue Push Prc';
                amzPefToast('error', msg);
                setAmzPushPrcProgress({ active: false, done: 0, total: 0, msg: msg });
            });
        }

        function pushAmzStdPrcWithPromos($btn, row) {
            // Multi-select → queue every selected SKU
            const selected = collectAmzPefSelectedRows();
            const clickedSku = amzPefSku(row.getData());
            const clickedSelected = selected.some(function(t) {
                return amzPefSku(t.d) === clickedSku;
            });
            if (selected.length > 1 && clickedSelected) {
                bulkPushAmzPrcSelected();
                return;
            }

            const d = row.getData();
            const sku = amzPefSku(d);
            const plan = computeAmzPushPrcPlan(d);
            if (!sku || !plan || !(plan.std > 0)) {
                amzPefToast('error', 'Set Std Prc first (optional PRMT% / CVR Discount for Sale)');
                return;
            }
            const liveEq = (typeof amazonListingPriceEqualsSprice === 'function')
                ? amazonListingPriceEqualsSprice(d, plan.effective)
                : (Number(d.price) > 0 && Number(plan.effective) > 0
                    && Math.round(Number(d.price) * 100) === Math.round(Number(plan.effective) * 100));
            if (liveEq) {
                amzPefToast('success', sku + ': Price already equals S PRC — left unchanged');
                return;
            }
            const confirmSale = plan.sale != null ? plan.sale : plan.std;
            if (!confirm(
                'Queue Push Prc for ' + sku + ' in background?\n\n'
                + 'Your $' + plan.std.toFixed(2)
                + ' · Sale $' + confirmSale.toFixed(2)
                + formatAmzPushPrcDiscNote(plan)
                + '\nMax $' + plan.max.toFixed(2)
                + ' · Min $' + plan.min.toFixed(2)
                + ' · Biz $' + plan.business.toFixed(2)
                + '\n\nYou can refresh or queue more SKUs while it runs.'
            )) return;

            row.update({ PUSH_PRC_STATUS: 'processing' });
            applyAmzPushPrcToSpriceRow(row, plan, null);
            queueAmzPushPrcItems([planToAmzPushPrcQueueItem(d, plan)]);
        }

        function bulkPushAmzPrcSelected() {
            if (!table) {
                amzPefToast('error', 'Load data first');
                return;
            }
            const targets = collectAmzPefSelectedRows();
            if (!targets.length) {
                amzPefToast('error', 'Select one or more SKUs first');
                return;
            }
            const ready = [];
            let alreadyEqual = 0;
            targets.forEach(function(t) {
                const d = t.row.getData();
                const plan = computeAmzPushPrcPlan(d);
                if (!plan || !(plan.std > 0)) return;
                const liveEq = (typeof amazonListingPriceEqualsSprice === 'function')
                    ? amazonListingPriceEqualsSprice(d, plan.effective)
                    : (Number(d.price) > 0 && Number(plan.effective) > 0
                        && Math.round(Number(d.price) * 100) === Math.round(Number(plan.effective) * 100));
                if (liveEq) {
                    alreadyEqual++;
                    return;
                }
                ready.push({ row: t.row, d: d, plan: plan });
            });
            const skipped = targets.length - ready.length - alreadyEqual;
            if (!ready.length) {
                amzPefToast(
                    alreadyEqual ? 'success' : 'error',
                    alreadyEqual
                        ? 'Price already equals S PRC — left unchanged'
                        : 'Selected SKUs need Std Prc set'
                );
                return;
            }
            if (!confirm(
                'Queue Push Prc for ' + ready.length + ' selected SKU(s) in background?'
                + (skipped ? ('\n(' + skipped + ' skipped — no Std Prc)') : '')
                + (alreadyEqual ? ('\n(' + alreadyEqual + ' left unchanged — Price = S PRC)') : '')
                + '\n\nEach SKU uses its own PRMT, CVR Disc, 0 Sold GROI, and CVR UP/DN.'
                + '\nYour=Std; 0 Sold Sale=GROI; Sold Sale=Std−(PRMT+CVR Disc+UP/DN);'
                + '\nSale=Business=Min'
                + '\n\nSafe to refresh — progress continues. You can select more and queue again.'
            )) return;

            const items = ready.map(function(r) {
                r.row.update({ PUSH_PRC_STATUS: 'processing' });
                applyAmzPushPrcToSpriceRow(r.row, r.plan, null);
                return planToAmzPushPrcQueueItem(r.d, r.plan);
            });
            if (table) table.redraw(true);
            queueAmzPushPrcItems(items);
        }

        function cancelAmzPushPrcJob() {
            if (!confirm('Cancel remaining Push Prc jobs?')) return;
            $.ajax({
                url: '/amazon-push-prc-cancel',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': amzPefCsrf(), 'Accept': 'application/json' },
                data: { _token: amzPefCsrf() },
            }).done(function(resp) {
                amzPefToast('success', (resp && resp.message) || 'Push Prc cancelled');
                pollAmzPushPrcStatus();
            }).fail(function(xhr) {
                amzPefToast('error', (xhr.responseJSON && xhr.responseJSON.message) || 'Cancel failed');
            });
        }

        function amzPageReloadPushAllowed() {
            return false;
        }
        function syncAmzReloadPushSwitchUi() {
            const on = amzPageReloadPushAllowed();
            const $wrap = $('#amz-reload-push-wrap');
            const $sw = $('#amz-reload-push-switch');
            $wrap.toggleClass('is-off', !on);
            $('#amz-reload-push-label').text(on ? 'On' : 'Off');
            if ($sw.length && $sw.prop('checked') !== on) $sw.prop('checked', on);
        }
        function saveAmzPageReloadPush(enabled) {
            amzPageReloadPushEnabled = !!enabled;
            syncAmzReloadPushSwitchUi();
            return $.ajax({
                url: '/channel-promo-pricing/amazon/page-reload-push',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': amzPefCsrf(), 'Accept': 'application/json' },
                data: { _token: amzPefCsrf(), enabled: enabled ? 1 : 0 },
            });
        }
        function amzPefNearlyEqual(a, b) {
            return Math.abs(Number(a) - Number(b)) < 0.005;
        }
        function collectAmzReloadPushItems() {
            const seen = {};
            const items = [];
            function consider(d) {
                if (!amzPefIsChildRow(d)) return;
                const sku = amzPefSku(d);
                const key = sku.toUpperCase();
                if (!sku || seen[key]) return;
                const plan = computeAmzPushPrcPlan(d);
                if (!plan || !(plan.effective > 0)) return;
                const live = amzPefRound2(Number(d.price) || 0);
                if (!(live > 0) || amzPefNearlyEqual(plan.effective, live)) return;
                seen[key] = true;
                items.push(planToAmzPushPrcQueueItem(d, plan));
            }
            if (typeof table !== 'undefined' && table && typeof table.getRows === 'function') {
                table.getRows().forEach(function(row) {
                    consider(row.getData());
                });
            }
            const extra = (typeof allTableData !== 'undefined' && Array.isArray(allTableData))
                ? allTableData
                : [];
            extra.forEach(function(d) { consider(d); });
            return items;
        }
        function amzTryQueuePushOnReload() {
            window._amzReloadPushQueued = true;
            return;
        }
        function bindAmzReloadPushOnTable() {
            return;
        }

        function initAmazonPefPromoUi() {
            syncAmzReloadPushSwitchUi();
            $('#amz-reload-push-switch').off('change.amzReload');

            // Prefetch Dil / CVR Disc rules
            if (typeof loadDilPrmtRules === 'function') loadDilPrmtRules();
            if (typeof loadAmzCvrDiscRules === 'function') {
                Promise.resolve(loadAmzCvrDiscRules()).then(function() {
                    if (table) {
                        try { table.getColumn('cvr_discount') && table.redraw(true); } catch (e) { /* ignore */ }
                    }
                }).catch(function() { /* defaults still work */ });
            }
            if (typeof loadAmzZeroSoldRules === 'function') {
                Promise.resolve(loadAmzZeroSoldRules()).then(function() {
                    if (table) {
                        try { table.getColumn('zero_sold') && table.redraw(true); } catch (e) { /* ignore */ }
                    }
                }).catch(function() { /* defaults */ });
            }

            // Resume background Push Prc progress after refresh
            $('#amz-push-prc-cancel-btn').off('click.amzpef').on('click.amzpef', function(e) {
                e.preventDefault();
                cancelAmzPushPrcJob();
            });
            pollAmzPushPrcStatus();
            // If a job is still active, keep polling (pollAmzPushPrcStatus starts interval when active)
            $.ajax({
                url: '/amazon-push-prc-status',
                method: 'GET',
                headers: { 'Accept': 'application/json' },
                timeout: 15000,
            }).done(function(resp) {
                if (resp && resp.active) startAmzPushPrcPoll();
            });

            $('#amz-dil-vs-prmt-btn').off('click.amzpef').on('click.amzpef', function(e) {
                e.preventDefault();
                const modalEl = document.getElementById('pefDilVsPrmtModal');
                if (!modalEl) return;
                renderDilPrmtModalTable();
                loadDilPrmtRules();
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            });

            // Push Prmt% → Dil rules → Amazon Listings API (only changed prices)
            $('#amz-push-prmt-btn').off('click.amzpef').on('click.amzpef', function(e) {
                e.preventDefault();
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

                const $btn = $('#amz-prmt-menu-btn');
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

            // CVR Disc badge → amazon_cvr_vs_disc rules (column CVR Disc.)
            $('#amz-cvr-disc-rules-btn').off('click.amzpef').on('click.amzpef', function(e) {
                e.preventDefault();
                const modalEl = document.getElementById('amzCvrDiscModal');
                if (!modalEl) return;
                renderAmzCvrDiscModalTable();
                loadAmzCvrDiscRules();
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            });
            $('#amz-cvr-disc-apply-btn').off('click.amzpef').on('click.amzpef', saveAndApplyAmzCvrDisc);

            $('#amz-zero-sold-btn').off('click.amzpef').on('click.amzpef', function(e) {
                e.preventDefault();
                const modalEl = document.getElementById('amzZeroSoldModal');
                if (!modalEl) return;
                renderAmzZeroSoldModalTable();
                loadAmzZeroSoldRules();
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            });
            $('#amz-zero-sold-save-btn').off('click.amzpef').on('click.amzpef', async function(e) {
                e.preventDefault();
                const $btn = $(this);
                const html = $btn.html();
                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving…');
                try {
                    await saveAmzZeroSoldRules();
                    if (table) {
                        try { table.redraw(true); } catch (err) { /* ignore */ }
                    }
                    amzPefToast('success', '0 Sold rules saved for Amazon.');
                } catch (xhr) {
                    amzPefToast('error', 'Save failed: ' + ((xhr && xhr.responseJSON && xhr.responseJSON.message) || 'error'));
                } finally {
                    $btn.prop('disabled', false).html(html);
                }
            });
            $('#amz-zero-sold-apply-btn').off('click.amzpef').on('click.amzpef', async function(e) {
                e.preventDefault();
                let targets = collectAmzPefSelectedRows();
                let label = 'selected';
                if (!targets.length) {
                    targets = collectAmzPefVisibleRows();
                    label = 'all visible';
                }
                targets = targets.filter(function(t) {
                    const d = t.d || t.row.getData();
                    return amzIsZeroSoldRow(d) && (parseFloat(d.LP_productmaster) || 0) > 0;
                });
                if (!targets.length) {
                    amzPefToast('error', 'No 0 Sold rows (A L30 = 0, INV > 0, LP > 0) to price');
                    return;
                }
                if (label === 'all visible' && !confirm(
                    'No rows selected — apply 0 Sold Target GROI% to ' + targets.length + ' visible 0 Sold row(s)?'
                )) {
                    return;
                }
                const $btn = $(this);
                const html = $btn.html();
                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving…');
                try {
                    await saveAmzZeroSoldRules();
                    await applyAmzZeroSoldToTargets(targets, label);
                } catch (xhr) {
                    amzPefToast('error', 'Save failed: ' + ((xhr && xhr.responseJSON && xhr.responseJSON.message) || 'error'));
                } finally {
                    $btn.prop('disabled', false).html(html);
                }
            });

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

            // Push Prc — Std + PRMT + CVR Disc + 0 Sold + CVR UP/DN → Amazon Listings
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

            // sprice ? — clear + refill S PRC from Push Prc formula (no Amazon push)
            $('#amz-sprice-recalc-btn').off('click.amzpef').on('click.amzpef', function(e) {
                e.preventDefault();
                clearAndAutopopulateAmzSprice();
            });
        }

        @include('partials.cvr-up-dn', ['cvrUpDnPart' => 'script', 'cvrUpDnChannel' => 'amazon'])
@endif
