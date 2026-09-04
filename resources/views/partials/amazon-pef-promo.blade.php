{{--
  CVR Disc. / Rev Disc. / Push Prc / Sprc Dil for Amazon tabulator.
  Sprc Dil: Amazon-only Dil 0.1–25% (5 slabs) → Target GROI% (amazon_dil_vs_groi).
  Amazon path: discount SPRICE via /save-amazon-sprice (no eBay Marketing APIs).
--}}
@php
    $amazonPefPromoPart = $amazonPefPromoPart ?? 'all';
    $amazonPageReloadPushEnabled = \App\Http\Controllers\MarketPlace\ChannelPromoPricingController::isPageReloadPushEnabled('amazon');
@endphp

@if($amazonPefPromoPart === 'css' || $amazonPefPromoPart === 'all')
        /* CVR Disc / Rev Disc — Amazon tabulator promo cells */
        .amz-pef-promo-cell {
            font-size: inherit;
            font-weight: 600;
            color: #64748b;
        }
        .amz-pef-promo-cell.has-val { color: #0f172a; }
        .tabulator-row .tabulator-cell[tabulator-field="cvr_discount"],
        .tabulator-row .tabulator-cell[tabulator-field="review_discount"],
        .tabulator-row .tabulator-cell[tabulator-field="t_discounts"] {
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
        .amz-review-discount-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 3px;
            color: #7c3aed;
            font-weight: 700;
            font-size: 12px;
            line-height: 1.2;
            white-space: nowrap;
        }
        .amz-review-discount-badge.is-zero {
            color: #adb5bd;
            font-weight: 600;
        }
        #amz-cvr-disc-table .amz-cvr-disc-input,
        #amz-review-disc-table .amz-review-disc-input,
        #amz-dil-groi-table .amz-dil-groi-input {
            max-width: 90px;
            margin-left: auto;
            text-align: right;
            font-weight: 600;
        }
        #amzCvrDiscModal .amz-cd-pie-wrap {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            margin: 0 0 10px;
            padding: 8px 10px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #f8fafc;
        }
        #amzCvrDiscModal .amz-cd-pie-canvas-wrap {
            width: 168px;
            height: 168px;
            flex: 0 0 168px;
        }
        #amzCvrDiscModal .amz-cd-pie-legend {
            flex: 1 1 auto;
            min-width: 0;
            max-height: 180px;
            overflow-y: auto;
        }
        #amzCvrDiscModal .amz-cd-pie-row {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            line-height: 1.3;
            padding: 1px 0;
        }
        #amzCvrDiscModal .amz-cd-pie-swatch {
            width: 8px;
            height: 8px;
            border-radius: 2px;
            flex: 0 0 8px;
        }
        #amzCvrDiscModal .amz-cd-pie-name { flex: 1 1 auto; font-weight: 600; color: #334155; }
        #amzCvrDiscModal .amz-cd-pie-count { font-weight: 700; min-width: 24px; text-align: right; }
        #amzCvrDiscModal .amz-cd-pie-pct { color: #64748b; min-width: 24px; text-align: right; }
        #amzCvrDiscModal .amz-cd-hist-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            border: none;
            padding: 0;
            cursor: pointer;
            flex: 0 0 8px;
            box-shadow: 0 0 0 1px rgba(15, 23, 42, 0.12);
        }
        #amzCvrDiscModal .amz-cd-hist-dot:hover { transform: scale(1.35); }
        #amzCvrDiscModal .amz-cd-hist-wrap {
            display: none;
            margin: 0 0 10px;
            padding: 6px 8px 4px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #fff;
        }
        #amzCvrDiscModal .amz-cd-hist-wrap.is-open { display: block; }
        #amzCvrDiscModal .amz-cd-hist-canvas-wrap { height: 160px; }
        #amz-cvr-disc-table .amz-cvr-disc-count {
            font-weight: 700;
            text-align: center;
            white-space: nowrap;
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
        #amz-review-disc-menu-btn {
            background: #7c3aed;
            border-color: #7c3aed;
            color: #fff;
        }
        #amz-review-disc-menu-btn:hover,
        #amz-review-disc-menu-btn:focus,
        #amz-review-disc-menu-btn.show {
            background: #6d28d9;
            border-color: #6d28d9;
            color: #fff;
        }
        #amz-review-disc-table .amz-review-disc-count {
            font-weight: 700;
            text-align: center;
            white-space: nowrap;
        }
        #amz-review-disc-table .amz-review-disc-row-del {
            border: none;
            background: none;
            color: #dc3545;
            padding: 0 4px;
            line-height: 1;
            cursor: pointer;
        }
        #amzReviewDiscModal .amz-rd-add-btn {
            font-size: 12px;
        }
        #amz-dil-groi-table .amz-dil-groi-input {
            max-width: 90px;
            margin-left: auto;
            text-align: right;
            font-weight: 600;
        }
        #amz-dil-groi-table .amz-dg-min,
        #amz-dil-groi-table .amz-dg-max {
            margin-left: 0;
        }
        #amz-dil-groi-table .amz-dil-groi-row-del {
            border: none;
            background: none;
            color: #dc3545;
            padding: 0 4px;
            line-height: 1;
            cursor: pointer;
        }
        #amzDilGroiModal .amz-dg-add-btn {
            font-size: 12px;
        }
        #amz-dil-groi-table .amz-dg-count {
            font-weight: 700;
            text-align: center;
            white-space: nowrap;
        }
        #amzDilGroiModal .amz-dg-pies {
            display: flex;
            gap: 10px;
            margin: 0 0 10px;
            flex-wrap: wrap;
        }
        #amzDilGroiModal .amz-dg-pie-wrap {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            flex: 1 1 280px;
            min-width: 260px;
            padding: 8px 10px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #f8fafc;
        }
        #amzDilGroiModal .amz-dg-pie-canvas-wrap {
            width: 140px;
            height: 140px;
            flex: 0 0 140px;
        }
        #amzDilGroiModal .amz-dg-pie-legend {
            flex: 1 1 auto;
            min-width: 0;
            max-height: 168px;
            overflow-y: auto;
        }
        #amzDilGroiModal .amz-dg-pie-row {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            line-height: 1.3;
            padding: 1px 0;
        }
        #amzDilGroiModal .amz-dg-pie-swatch {
            width: 8px;
            height: 8px;
            border-radius: 2px;
            flex: 0 0 8px;
        }
        #amzDilGroiModal .amz-dg-pie-name { flex: 1 1 auto; font-weight: 600; color: #334155; }
        #amzDilGroiModal .amz-dg-pie-count { font-weight: 700; min-width: 24px; text-align: right; }
        #amzDilGroiModal .amz-dg-pie-pct { color: #64748b; min-width: 24px; text-align: right; }
        #amzDilGroiModal .amz-dg-hist-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            border: none;
            padding: 0;
            cursor: pointer;
            flex: 0 0 8px;
            box-shadow: 0 0 0 1px rgba(15, 23, 42, 0.12);
        }
        #amzDilGroiModal .amz-dg-hist-dot:hover { transform: scale(1.35); }
        #amzDilGroiModal .amz-dg-hist-wrap {
            display: none;
            margin: 0 0 10px;
            padding: 6px 8px 4px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #fff;
        }
        #amzDilGroiModal .amz-dg-hist-wrap.is-open { display: block; }
        #amzDilGroiModal .amz-dg-hist-canvas-wrap { height: 160px; }
        #amzDilGroiModal .amz-dg-rules {
            margin: 0 0 10px;
            padding-left: 1.15rem;
        }
        #amzDilGroiModal .amz-dg-rules li { margin-bottom: 0.28rem; }
        #amzDilGroiModal .amz-dg-rules-title {
            margin: 0 0 4px;
            font-size: 12px;
            font-weight: 700;
            color: #334155;
        }
        #amz-dil-groi-btn {
            background: #6f42c1;
            border-color: #6f42c1;
            color: #fff;
        }
        #amz-dil-groi-btn:hover,
        #amz-dil-groi-btn:focus {
            background: #5a32a3;
            border-color: #5a32a3;
            color: #fff;
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
        .amz-reload-push-cluster {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex: 1 1 auto;
            min-width: 0;
            max-width: 100%;
        }
        .amz-reload-push-progress {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex: 1 1 180px;
            min-width: 160px;
            max-width: 320px;
            padding: 4px 10px;
            border: 1px solid #bfdbfe;
            border-radius: 999px;
            background: #eff6ff;
            font-size: 11px;
            font-weight: 700;
            color: #1d4ed8;
            line-height: 1.2;
        }
        .amz-reload-push-progress.is-busy { border-color: #93c5fd; }
        .amz-reload-push-progress.is-done {
            border-color: #86efac;
            background: #f0fdf4;
            color: #15803d;
        }
        .amz-reload-push-progress.is-fail {
            border-color: #fcd34d;
            background: #fffbeb;
            color: #b45309;
        }
        .amz-reload-push-progress-track {
            flex: 1 1 72px;
            height: 8px;
            min-width: 64px;
            border-radius: 999px;
            background: #bfdbfe;
            overflow: hidden;
        }
        .amz-reload-push-progress-track > span {
            display: block;
            height: 100%;
            width: 0;
            background: #93c5fd;
            border-radius: 999px;
            transition: width .25s ease;
        }
        .amz-reload-push-progress.is-done .amz-reload-push-progress-track > span { background: #22c55e; }
        .amz-reload-push-progress.is-fail .amz-reload-push-progress-track > span {
            background: linear-gradient(90deg, #22c55e 70%, #f59e0b 100%);
        }
        .amz-reload-push-progress-pct {
            flex: 0 0 auto;
            min-width: 2.4em;
            text-align: right;
        }
        .amz-reload-push-progress-msg {
            flex: 0 1 auto;
            min-width: 0;
            max-width: 9.5rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-weight: 600;
            color: #64748b;
        }
        .amz-reload-push-progress.is-done .amz-reload-push-progress-msg { color: #15803d; }
        .amz-reload-push-progress-cancel {
            display: none;
            flex: 0 0 auto;
            padding: 0 6px;
            border: 1px solid #fca5a5;
            border-radius: 999px;
            background: #fff;
            color: #dc2626;
            font-size: 10px;
            font-weight: 800;
            line-height: 16px;
            cursor: pointer;
        }
        .amz-reload-push-progress.is-busy .amz-reload-push-progress-cancel { display: inline-block; }
        @push('page-title-after')
            <div class="amz-reload-push-cluster" id="amz-reload-push-cluster">
                <label class="amz-reload-push-switch{{ $amazonPageReloadPushEnabled ? '' : ' is-off' }}"
                    id="amz-reload-push-wrap"
                    title="When ON, this page queues Push Prc for SKUs whose S PRC ≠ live Price (on load and when you flip the switch). When OFF, only manual Push Prc and the daily cron push. Progress shows in the bar.">
                    <span class="amz-reload-push-text">
                        Push on reload
                        <span class="amz-reload-push-state" id="amz-reload-push-label">{{ $amazonPageReloadPushEnabled ? 'On' : 'Off' }}</span>
                    </span>
                    <input type="checkbox" role="switch" id="amz-reload-push-switch"
                        {{ $amazonPageReloadPushEnabled ? 'checked' : '' }}>
                </label>
                <div id="amz-reload-push-progress" class="amz-reload-push-progress"
                    aria-live="polite" title="Amazon Push Prc progress">
                    <div class="amz-reload-push-progress-track">
                        <span id="amz-reload-push-progress-bar"></span>
                    </div>
                    <span class="amz-reload-push-progress-pct" id="amz-reload-push-progress-pct">0%</span>
                    <span class="amz-reload-push-progress-msg" id="amz-reload-push-progress-msg">Ready</span>
                    <button type="button" class="amz-reload-push-progress-cancel" id="amz-reload-push-progress-cancel">Cancel</button>
                </div>
            </div>
        @endpush
@endif

@if($amazonPefPromoPart === 'buttons' || $amazonPefPromoPart === 'all')
                    @include('partials.sprice-lmp-cap-script')
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
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm dropdown-toggle" id="amz-review-disc-menu-btn"
                            data-bs-toggle="dropdown" aria-expanded="false"
                            title="Review Disc. column rules">
                            Rev Disc
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="amz-review-disc-menu-btn">
                            <li>
                                <a class="dropdown-item" href="#" id="amz-review-disc-rules-btn">
                                    <i class="fas fa-star me-1" style="color:#7c3aed;"></i> Edit Review Disc rules…
                                </a>
                            </li>
                        </ul>
                    </div>
                    <button type="button" class="btn btn-sm" id="amz-dil-groi-btn"
                        title="Dil slabs → Target GROI%. Every INV &gt; 0 SKU uses the Dil-matching slab.">
                        <i class="fas fa-sliders-h"></i> Sprc Dil
                    </button>
@endif

@if($amazonPefPromoPart === 'modals' || $amazonPefPromoPart === 'all')
    {{-- CVR Disc: Amazon-only rules store amazon_cvr_vs_disc --}}
    <div class="modal fade" id="amzCvrDiscModal" tabindex="-1" aria-labelledby="amzCvrDiscModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title fs-6" id="amzCvrDiscModalLabel">
                        CVR Disc rules
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-2">
                    <div class="amz-cd-pie-wrap">
                        <div class="amz-cd-pie-canvas-wrap">
                            <canvas id="amz-cd-pie"></canvas>
                        </div>
                        <div class="amz-cd-pie-legend" id="amz-cd-pie-legend"></div>
                    </div>
                    <div class="amz-cd-hist-wrap" id="amz-cd-hist-wrap">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small fw-semibold" id="amz-cd-hist-title">Slab history</span>
                            <button type="button" class="btn-close" id="amz-cd-hist-close" aria-label="Close history" style="font-size:10px;"></button>
                        </div>
                        <div class="amz-cd-hist-canvas-wrap">
                            <canvas id="amz-cd-hist"></canvas>
                        </div>
                    </div>
                    <p class="small text-muted mb-2">
                        Map CVR% slabs to <strong>CVR Disc.</strong> % (no 0% slab).
                        Used by the CVR Disc. column and Push Prc Sale discount.
                        <strong>CVR = 0%</strong> maps to <strong>0</strong> Disc%.
                    </p>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0" id="amz-cvr-disc-table">
                            <thead class="table-light">
                                <tr>
                                    <th>CVR%</th>
                                    <th class="text-center" style="width:90px;">Count</th>
                                    <th class="text-end" style="width:120px;">Disc %</th>
                                </tr>
                            </thead>
                            <tbody id="amz-cvr-disc-tbody"></tbody>
                        </table>
                    </div>
                    <div class="small text-muted mt-2" id="amz-cvr-disc-status"></div>
                </div>
                <div class="modal-footer py-2 flex-wrap gap-1">
                    <button type="button" class="btn btn-sm btn-primary" id="amz-cvr-disc-apply-btn"
                        title="Save CVR Disc rules and write S PRC on matching SKUs (with 0 Sold)">
                        Apply
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Review Disc: Amazon-only rules store amazon_review_vs_disc --}}
    <div class="modal fade" id="amzReviewDiscModal" tabindex="-1" aria-labelledby="amzReviewDiscModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title fs-6" id="amzReviewDiscModalLabel">
                        <i class="fas fa-star me-1" style="color:#7c3aed;"></i> Review Disc rules
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-2">
                    <p class="small text-muted mb-2">
                        Map <strong>review count</strong> ranges to <strong>Rev Disc.</strong> %
                        (added to T Discounts / S PRC).
                        Defaults: <strong>1–2 → 4%</strong>, <strong>2–3 → 4%</strong>.
                        Review count above <strong>Max reviews</strong> never takes a discount.
                        Add or edit ranges as needed.
                    </p>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <label for="amz-review-disc-max" class="small fw-semibold mb-0 text-nowrap">Max reviews</label>
                        <input type="number" id="amz-review-disc-max" class="form-control form-control-sm"
                            min="1" step="1" value="4" style="width:72px;"
                            title="Discount never applies when review count is greater than this">
                        <span class="small text-muted">No discount when reviews &gt; this value.</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0" id="amz-review-disc-table">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center" style="width:90px;">From</th>
                                    <th class="text-center" style="width:90px;">To</th>
                                    <th class="text-center" style="width:90px;">Count</th>
                                    <th class="text-end" style="width:120px;">Disc %</th>
                                    <th style="width:36px;"></th>
                                </tr>
                            </thead>
                            <tbody id="amz-review-disc-tbody"></tbody>
                        </table>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary amz-rd-add-btn mt-2" id="amz-review-disc-add-btn">
                        <i class="fas fa-plus me-1"></i> Add range
                    </button>
                    <div class="small text-muted mt-2" id="amz-review-disc-status"></div>
                </div>
                <div class="modal-footer py-2 flex-wrap gap-1">
                    <button type="button" class="btn btn-sm btn-primary" id="amz-review-disc-apply-btn"
                        title="Save Review Disc rules and write S PRC on matching SKUs (with CVR Disc, 0 Sold)">
                        Apply
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="amzDilGroiModal" tabindex="-1" aria-labelledby="amzDilGroiModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title fs-6" id="amzDilGroiModalLabel">
                        <i class="fas fa-sliders-h me-1"></i> Dil vs Target GROI — Sprc Dil
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-2">
                    <div class="amz-dg-pies">
                        <div class="amz-dg-pie-wrap">
                            <div class="amz-dg-pie-canvas-wrap">
                                <canvas id="amz-dg-dil-pie"></canvas>
                            </div>
                            <div class="amz-dg-pie-legend" id="amz-dg-dil-legend"></div>
                        </div>
                        <div class="amz-dg-pie-wrap">
                            <div class="amz-dg-pie-canvas-wrap">
                                <canvas id="amz-dg-cvr-pie"></canvas>
                            </div>
                            <div class="amz-dg-pie-legend" id="amz-dg-cvr-legend"></div>
                        </div>
                    </div>
                    <div class="amz-dg-hist-wrap" id="amz-dg-hist-wrap">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small fw-semibold" id="amz-dg-hist-title">Slab history</span>
                            <button type="button" class="btn-close" id="amz-dg-hist-close" aria-label="Close history" style="font-size:10px;"></button>
                        </div>
                        <div class="amz-dg-hist-canvas-wrap">
                            <canvas id="amz-dg-hist"></canvas>
                        </div>
                    </div>
                    <div class="amz-dg-rules-title">Rules — when each condition applies</div>
                    <ul class="small text-muted amz-dg-rules">
                        <li>
                            <strong>When</strong> Dil sits in a From–To range (INV &gt; 0):
                            use that slab’s Target GROI (first match; last slab includes the To value).
                        </li>
                        <li>
                            <strong>When</strong> a price is calculated from a Dil slab match:
                            it auto-applies to <strong>S PRC</strong> and Push Prc Sale.
                        </li>
                        <li>
                            <strong>When</strong> you change the first Target GROI%:
                            later rows fill as first +5, +10, … (increasing down the table).
                        </li>
                        <li>
                            <strong>When</strong> you click <strong>Save and Apply</strong>:
                            Amazon’s table is stored via <strong>API only</strong>, then <strong>S PRC</strong> is written.
                        </li>
                        <li>
                            <strong>When</strong> INV ≤ 0: Count and pies skip that SKU.
                        </li>
                    </ul>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0" id="amz-dil-groi-table">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center" style="width:90px;">From</th>
                                    <th class="text-center" style="width:90px;">To</th>
                                    <th class="text-center" style="width:80px;" title="Child SKUs with INV &gt; 0 whose Dil is in this slab">Count</th>
                                    <th class="text-end" style="width:130px;">Target GROI%</th>
                                    <th style="width:36px;"></th>
                                </tr>
                            </thead>
                            <tbody id="amz-dil-groi-tbody"></tbody>
                        </table>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary amz-dg-add-btn mt-2" id="amz-dil-groi-add-btn">
                        <i class="fas fa-plus me-1"></i> Add slab
                    </button>
                    <div class="small text-muted mt-2" id="amz-dil-groi-status"></div>
                </div>
                <div class="modal-footer py-2 flex-wrap gap-1">
                    <button type="button" class="btn btn-sm btn-primary" id="amz-dil-groi-save-btn"
                        title="Save Dil → Target GROI% slabs via API and apply S PRC on matching SKUs.">
                        <i class="fas fa-save me-1"></i> Save and Apply
                    </button>
                </div>
            </div>
        </div>
    </div>

@endif

@if($amazonPefPromoPart === 'script' || $amazonPefPromoPart === 'all')
        @include('partials.tabulator-column-autofit')
        @include('partials.analytics-column-visibility', ['colVisPart' => 'script'])
        // ==================== CVR Disc / Rev Disc / Sprc Dil ====================
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

        const AMZ_REVIEW_DISC_DEFAULTS = [
            { key: '1-2', min: 1, max: 2, label: '1–2', disc: 4 },
            { key: '2-3', min: 2, max: 3, label: '2–3', disc: 4 },
        ];
        const AMZ_REVIEW_DISC_MAX_DEFAULT = 4;

        let amzCvrDiscRules = AMZ_CVR_DISC_DEFAULTS.map(function(r) { return Object.assign({}, r); });
        let amzReviewDiscRules = AMZ_REVIEW_DISC_DEFAULTS.map(function(r) { return Object.assign({}, r); });
        let amzReviewDiscMax = AMZ_REVIEW_DISC_MAX_DEFAULT;
        const AMZ_CD_SLAB_META = [
            { key: 'eq-0', label: '0', color: '#94a3b8' },
            { key: '0.01-1', label: '0.01–1', color: '#a00211' },
            { key: '1-1.5', label: '1–1.5', color: '#c2410c' },
            { key: '1.5-2', label: '1.5–2', color: '#ea580c' },
            { key: '2-3', label: '2–3', color: '#f59e0b' },
            { key: '3-4', label: '3–4', color: '#eab308' },
            { key: '4-5', label: '4–5', color: '#84cc16' },
            { key: '5-6', label: '5–6', color: '#22c55e' },
            { key: '6-6.5', label: '6–6.5', color: '#14b8a6' },
            { key: '6.5-7', label: '6.5–7', color: '#3b82f6' },
            { key: 'gt-7', label: '> 7', color: '#e83e8c' },
        ];
        let amzCdPieChart = null;
        let amzCdHistChart = null;
        let amzCdLiveCounts = {};
        const AMZ_DIL_GROI_DEFAULTS = [
            { key: '0.1-5', label: '0.1–5%', min: 0.1, max: 5, groi: 50 },
            { key: '5-10', label: '5–10%', min: 5, max: 10, groi: 55 },
            { key: '10-15', label: '10–15%', min: 10, max: 15, groi: 60 },
            { key: '15-20', label: '15–20%', min: 15, max: 20, groi: 65 },
            { key: '20-25', label: '20–25%', min: 20, max: 25, groi: 70 },
        ];
        let amzDilGroiRules = AMZ_DIL_GROI_DEFAULTS.map(function(r) { return Object.assign({}, r); });
        let amzDgDilPieChart = null;
        let amzDgCvrPieChart = null;
        let amzDgHistChart = null;
        let amzDgDilLiveCounts = {};
        let amzDgCvrLiveCounts = {};
        let amzDgDilSlices = [];
        const AMZ_DG_SLAB_COLORS = [
            '#6f42c1', '#3b82f6', '#14b8a6', '#22c55e', '#84cc16',
            '#eab308', '#f59e0b', '#ea580c', '#dc3545', '#e83e8c',
            '#7c3aed', '#0ea5e9',
        ];
        const AMZ_DG_CVR_BANDS = [
            { key: 'down-lt7', label: 'Down · < 7%', color: '#dc3545' },
            { key: 'down-7-10', label: 'Down · 7–10%', color: '#fd7e14' },
            { key: 'down-gt10', label: 'Down · > 10%', color: '#f59e0b' },
            { key: 'flat-lt7', label: 'Flat · < 7%', color: '#94a3b8' },
            { key: 'flat-7-10', label: 'Flat · 7–10%', color: '#64748b' },
            { key: 'flat-gt10', label: 'Flat · > 10%', color: '#475569' },
            { key: 'up-lt7', label: 'UP · < 7%', color: '#86efac' },
            { key: 'up-7-10', label: 'UP · 7–10%', color: '#20c997' },
            { key: 'up-gt10', label: 'UP · > 10%', color: '#198754' },
        ];
        let amzPageReloadPushEnabled = @json($amazonPageReloadPushEnabled ?? false);

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
        function amzPefCvrL30Live(d) {
            const aL30 = Number(d && (d['A_L30'] != null ? d['A_L30'] : d.A_L30)) || 0;
            const sess30 = Number(d && d.Sess30) || 0;
            return sess30 === 0 ? 0 : (aL30 / sess30) * 100;
        }
        function amzPefCvrL45Live(d) {
            const aL30 = Number(d && (d['A_L30'] != null ? d['A_L30'] : d.A_L30)) || 0;
            const sess30 = Number(d && d.Sess30) || 0;
            const aL60 = Number(d && d.units_ordered_l60) || 0;
            const sess60 = Number(d && d.sessions_l60) || 0;
            const sess45 = (sess30 + sess60) / 2;
            if (sess45 === 0) return 0;
            return (((aL30 + aL60) / 2) / sess45) * 100;
        }
        function amzPefCvrTrend(d) {
            const cvr = amzPefCvrL30Live(d);
            const cvr45 = amzPefCvrL45Live(d);
            const tol = 0.1;
            if (cvr === 0 || cvr < cvr45 - tol) return 'down';
            if (cvr > cvr45 + tol) return 'up';
            return 'flat';
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
        /** Colored history dot (PDT daily roll-on) for Push Prc column */
        function amzPefPromoHistoryDotHtml(sku, metric, pct) {
            if (!sku) return '';
            const n = Number(pct);
            const has = isFinite(n) && n > 0;
            let color = '#adb5bd';
            let label = metric;
            if (metric === 'push_prc') { color = has ? '#FF9900' : '#adb5bd'; label = 'Push Prc'; }
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
        function amzPefLmp(d) {
            if (typeof lmpWithShipping === 'function') {
                const n = Number(lmpWithShipping(d));
                if (isFinite(n) && n > 0) return n;
            }
            if (window.SpriceLmpCap && typeof SpriceLmpCap.lmpOf === 'function') {
                const n = Number(SpriceLmpCap.lmpOf(d));
                if (isFinite(n) && n > 0) return n;
            }
            return Number(d && (d.lmp_price || d.lmp || d.LMP)) || 0;
        }
        function amzFinalSpriceToSave(d, sprice) {
            let val = Number(sprice);
            if (!(val > 0)) return 0;
            val = amzPefRound2(val);
            if (typeof amzCapRuleSprice === 'function') val = amzCapRuleSprice(d, val);
            else if (typeof amazonCapSpriceToLmp === 'function') val = amazonCapSpriceToLmp(d, val);
            else if (window.SpriceLmpCap) val = SpriceLmpCap.prepare(d, val);
            return val > 0 ? amzPefRound2(val) : 0;
        }
        function amzMarkPersistedSprice(row, val) {
            if (!row || typeof row.update !== 'function') return;
            const n = Number(val);
            const saved = (isFinite(n) && n > 0) ? amzPefRound2(n) : 0;
            row.update({ _amz_persisted_sprice: saved });
            const d = row.getData();
            if (d) d._amz_persisted_sprice = saved;
        }
        function amzLastPersistedSprice(d) {
            if (d && d._amz_persisted_sprice != null && isFinite(Number(d._amz_persisted_sprice))) {
                return Number(d._amz_persisted_sprice);
            }
            return parseFloat(d && d.SPRICE) || 0;
        }
        function saveAmzSpriceFromPromo(row, sprice, silent, extra) {
            const d = row.getData();
            const sku = amzPefSku(d);
            const val = amzFinalSpriceToSave(d, sprice);
            extra = extra || {};
            if (!sku) return $.Deferred().reject().promise();
            const payload = { sku: sku, sprice: val, _token: amzPefCsrf() };
            if (val > 0) {
                row.update({ SPRICE: val, has_custom_sprice: true });
            } else {
                row.update({ SPRICE: 0, has_custom_sprice: false });
            }
            if (extra.record_push_prc) payload.record_push_prc = 1;
            return $.ajax({
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
                    if (Object.keys(updates).length) row.update(updates);
                    amzMarkPersistedSprice(row, (response.data != null && response.data !== '') ? response.data : val);
                    if (!silent) amzPefToast('success', 'S PRC updated');
                },
                error: function() {
                    if (!silent) amzPefToast('error', 'Failed to save S PRC');
                }
            });
        }

        /** Wipe stored S PRC (persist 0), then insert the discounted or LMP-capped price. */
        function amzPersistClearThenSprice(row, fill, silent, extra) {
            extra = extra || {};
            const d = (row && typeof row.getData === 'function') ? (row.getData() || {}) : {};
            const sku = amzPefSku(d);
            if (!sku) return $.Deferred().resolve().promise();
            fill = amzFinalSpriceToSave(d, fill);
            if (row && typeof row.update === 'function') {
                row.update({ SPRICE: 0, has_custom_sprice: false, SGPFT: 0, SGROI: 0, SROI: 0, 'Spft%': 0 });
                try { row.reformat(); } catch (e) { /* ignore */ }
            }
            const deferred = $.Deferred();
            const wipe = $.ajax({
                url: '/save-amazon-sprice',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': amzPefCsrf(), 'Accept': 'application/json' },
                data: { sku: sku, sprice: 0, _token: amzPefCsrf() },
            });
            const insert = function() {
                const req = saveAmzSpriceFromPromo(row, fill, silent, extra);
                if (req && typeof req.done === 'function') {
                    req.done(function(res) { deferred.resolve(res); })
                        .fail(function(xhr) { deferred.reject(xhr); });
                } else {
                    deferred.resolve(req || null);
                }
            };
            wipe.always(insert);
            return deferred.promise();
        }
        window.amzPersistClearThenSprice = amzPersistClearThenSprice;
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
        function amzPefReviewCount(d) {
            const n = parseInt(d && (d.amz_review_count != null ? d.amz_review_count : d.reviews), 10);
            return isFinite(n) && n > 0 ? n : 0;
        }
        function amzNormalizeReviewDiscRule(r) {
            const min = parseInt(r && r.min, 10);
            const max = parseInt(r && r.max, 10);
            if (!isFinite(min) || !isFinite(max)) return null;
            const lo = Math.min(min, max);
            const hi = Math.max(min, max);
            const disc = Number(r && r.disc);
            return {
                key: lo + '-' + hi,
                min: lo,
                max: hi,
                label: lo + '–' + hi,
                disc: (isFinite(disc) && disc >= 0) ? disc : 0,
            };
        }
        /** Review count → Rev Disc. % (INV=0 or count 0 or count > max → 0). First matching from–to wins. */
        function computeAmzReviewDiscountPct(d) {
            if (!amzPefIsChildRow(d)) return null;
            if (amzPefInv(d) === 0) return 0;
            const count = amzPefReviewCount(d);
            const cap = Number(amzReviewDiscMax);
            const maxRev = (isFinite(cap) && cap > 0) ? cap : AMZ_REVIEW_DISC_MAX_DEFAULT;
            if (!(count > 0) || count > maxRev) return 0;
            for (let i = 0; i < amzReviewDiscRules.length; i++) {
                const rule = amzNormalizeReviewDiscRule(amzReviewDiscRules[i]);
                if (!rule) continue;
                if (count >= rule.min && count <= rule.max) {
                    return rule.disc > 0 ? rule.disc : 0;
                }
            }
            return 0;
        }
        function fmtAmzReviewDiscountBadge(pct) {
            const n = Number(pct);
            if (!isFinite(n) || n <= 0) {
                return '<span class="amz-review-discount-badge is-zero" title="No Review Disc">—</span>';
            }
            return '<span class="amz-review-discount-badge" title="Review Disc rule → ' + n + '%">'
                + n + '</span>';
        }
        function fmtAmzCvrDiscountBadge(pct) {
            const n = Number(pct);
            if (!isFinite(n) || n <= 0) {
                return '<span class="amz-cvr-discount-badge is-zero" title="No CVR Disc">—</span>';
            }
            return '<span class="amz-cvr-discount-badge" title="CVR Disc rule → ' + n + '%">'
                + n + '</span>';
        }
        function amzPefAL30(d) {
            if (!d) return 0;
            const raw = (d.A_L30 != null && d.A_L30 !== '') ? d.A_L30
                : (d['A_L30'] != null && d['A_L30'] !== '' ? d['A_L30']
                    : (d['A L30'] != null && d['A L30'] !== '' ? d['A L30'] : 0));
            const n = Number(raw);
            return isFinite(n) ? n : 0;
        }
        function amzIsZeroSoldRow(d) {
            if (!amzPefIsChildRow(d)) return false;
            if (amzPefInv(d) <= 0) return false;
            return !(amzPefAL30(d) > 0);
        }
        function amzSpriceFromTargetGroi(d, roiPct) {
            const lp = parseFloat(d.LP_productmaster) || 0;
            if (!(lp > 0)) return 0;
            const ship = parseFloat(d.Ship_productmaster) || 0;
            const roi = isFinite(Number(roiPct)) ? Number(roiPct) : 0;
            const price = (lp * (1 + roi / 100) + ship) / 0.80;
            return (isFinite(price) && price > 0) ? amzPefRound2(price) : 0;
        }
        function amzDilGroiFmtNum(n) {
            const x = Number(n);
            if (!isFinite(x)) return '0';
            return amzPefRound2(x).toFixed(2).replace(/0+$/, '').replace(/\.$/, '');
        }
        function amzNormalizeDilGroiRule(raw) {
            if (!raw) return null;
            let min = Number(raw.min);
            let max = Number(raw.max);
            if (!isFinite(min) || !isFinite(max)) {
                const m = String(raw.key || '').match(/^(\d+(?:\.\d+)?)-(\d+(?:\.\d+)?)$/);
                if (!m) return null;
                min = Number(m[1]);
                max = Number(m[2]);
            }
            if (!isFinite(min) || !isFinite(max) || min < 0 || max <= min) return null;
            min = amzPefRound2(min);
            max = amzPefRound2(max);
            let groi = Number(raw.groi);
            if (!isFinite(groi) || groi < 0) groi = 0;
            groi = amzPefRound2(groi);
            const key = amzDilGroiFmtNum(min) + '-' + amzDilGroiFmtNum(max);
            return {
                key: key,
                label: amzDilGroiFmtNum(min) + '–' + amzDilGroiFmtNum(max) + '%',
                min: min,
                max: max,
                groi: groi,
            };
        }
        function amzNormalizeDilGroiList(list) {
            const out = [];
            (Array.isArray(list) ? list : []).forEach(function(item) {
                const rule = amzNormalizeDilGroiRule(item);
                if (rule) out.push(rule);
            });
            out.sort(function(a, b) { return a.min - b.min; });
            return out;
        }
        function amzDilGroiMatchInList(dil, list) {
            const n = Number(dil);
            if (!isFinite(n) || n < 0 || !list || !list.length) return null;
            const last = list.length - 1;
            for (let i = 0; i < list.length; i++) {
                const rule = list[i];
                const hiOk = (i === last) ? (n <= rule.max) : (n < rule.max);
                if (n >= rule.min && hiOk) return rule;
            }
            return null;
        }
        function amzDilGroiMatch(dil) {
            return amzDilGroiMatchInList(dil, amzNormalizeDilGroiList(amzDilGroiRules));
        }
        function amzDilSlabColorInfo(dil) {
            const rules = amzNormalizeDilGroiList(amzDilGroiRules);
            const n = Number(dil);
            if (!isFinite(n) || n < 0 || !rules.length) {
                return { color: '#cbd5e1', label: 'Outside', idx: -1 };
            }
            const last = rules.length - 1;
            for (let i = 0; i < rules.length; i++) {
                const rule = rules[i];
                const hiOk = (i === last) ? (n <= rule.max) : (n < rule.max);
                if (n >= rule.min && hiOk) {
                    return {
                        color: amzDgSlabColor(i),
                        label: rule.label,
                        idx: i,
                        key: rule.key,
                    };
                }
            }
            return { color: '#cbd5e1', label: 'Outside', idx: -1 };
        }
        window.amzDilSlabColorInfo = amzDilSlabColorInfo;
        function amzDgEachInvChild(fn) {
            if (typeof table === 'undefined' || !table) return;
            const rows = (typeof table.getData === 'function') ? (table.getData('all') || []) : [];
            rows.forEach(function(d) {
                if (!amzPefIsChildRow(d) || amzPefInv(d) <= 0) return;
                fn(d);
            });
        }
        function amzDilGroiDisplayRules() {
            const rules = [];
            $('#amz-dil-groi-tbody tr').each(function() {
                const rule = amzNormalizeDilGroiRule({
                    min: parseFloat($(this).find('.amz-dg-min').val()),
                    max: parseFloat($(this).find('.amz-dg-max').val()),
                    groi: parseFloat($(this).find('.amz-dg-groi').val()),
                });
                if (rule) rules.push(rule);
            });
            if (rules.length) return rules;
            return amzNormalizeDilGroiList(amzDilGroiRules);
        }
        function amzDilGroiCvrBandKey(d) {
            const cvr = amzPefCvrL30Live(d);
            const trend = amzPefCvrTrend(d);
            const bucket = cvr < 7 ? 'lt7' : (cvr > 10 ? 'gt10' : '7-10');
            return trend + '-' + bucket;
        }
        function amzDilGroiCollectCounts(list) {
            const rules = list || amzDilGroiDisplayRules();
            const counts = { _outside: 0 };
            rules.forEach(function(r) { counts[r.key] = 0; });
            amzDgEachInvChild(function(d) {
                const rule = amzDilGroiMatchInList(amzPefDil(d), rules);
                if (rule) counts[rule.key] = (counts[rule.key] || 0) + 1;
                else counts._outside++;
            });
            return counts;
        }
        function amzDilGroiCollectCvrCounts() {
            const counts = {};
            AMZ_DG_CVR_BANDS.forEach(function(b) { counts[b.key] = 0; });
            amzDgEachInvChild(function(d) {
                const key = amzDilGroiCvrBandKey(d);
                counts[key] = (counts[key] || 0) + 1;
            });
            return counts;
        }
        function amzDgSlabColor(idx) {
            return AMZ_DG_SLAB_COLORS[idx % AMZ_DG_SLAB_COLORS.length];
        }
        function amzDgHistDotHtml(chart, key, color, label) {
            return '<button type="button" class="amz-dg-hist-dot" data-chart="' + String(chart).replace(/"/g, '&quot;') + '" '
                + 'data-band="' + String(key).replace(/"/g, '&quot;') + '" '
                + 'style="background:' + color + ';" title="' + String(label).replace(/"/g, '&quot;') + ' daily history"></button>';
        }
        function amzDgTodayKey() {
            try {
                return new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Los_Angeles' }).format(new Date());
            } catch (e) {
                return new Date().toISOString().slice(0, 10);
            }
        }
        function amzDgSnapLocal(storeKey, counts) {
            try {
                const today = amzDgTodayKey();
                let hist = {};
                try { hist = JSON.parse(localStorage.getItem(storeKey) || '{}') || {}; } catch (e) { hist = {}; }
                hist[today] = counts;
                const keys = Object.keys(hist).sort();
                while (keys.length > 90) delete hist[keys.shift()];
                localStorage.setItem(storeKey, JSON.stringify(hist));
            } catch (e) { /* ignore */ }
        }
        function amzDgLocalHistory(storeKey) {
            try {
                const hist = JSON.parse(localStorage.getItem(storeKey) || '{}') || {};
                return Object.keys(hist).sort().map(function(date) {
                    return Object.assign({ date: date, label: date.slice(5) }, hist[date] || {});
                });
            } catch (e) {
                return [];
            }
        }
        function amzDgPieLegendHtml(title, slices, counts, chart) {
            const total = slices.reduce(function(sum, s) { return sum + (counts[s.key] || 0); }, 0);
            return '<div class="amz-dg-pie-row" style="color:#94a3b8;font-size:10px;font-weight:600;">'
                + '<span class="amz-dg-pie-swatch" style="visibility:hidden;"></span>'
                + '<span class="amz-dg-pie-name">' + title + '</span>'
                + '<span class="amz-dg-pie-count">count</span>'
                + '<span class="amz-dg-pie-pct">of total</span>'
                + '<span class="amz-dg-hist-dot" style="visibility:hidden;"></span>'
                + '</div>'
                + slices.map(function(s) {
                    const n = counts[s.key] || 0;
                    const pct = total > 0 ? Math.round((n / total) * 100) : 0;
                    return '<div class="amz-dg-pie-row">'
                        + '<span class="amz-dg-pie-swatch" style="background:' + s.color + ';"></span>'
                        + '<span class="amz-dg-pie-name">' + s.label + '</span>'
                        + '<span class="amz-dg-pie-count">' + n + '</span>'
                        + '<span class="amz-dg-pie-pct" title="' + pct + ' of total">' + pct + '</span>'
                        + amzDgHistDotHtml(chart, s.key, s.color, s.label)
                        + '</div>';
                }).join('');
        }
        function amzDgDrawHist(chart, band, rows) {
            const slices = chart === 'cvr' ? AMZ_DG_CVR_BANDS : amzDgDilSlices;
            const spec = slices.find(function(s) { return s.key === band; })
                || { key: band, label: band, color: '#6f42c1' };
            $('#amz-dg-hist-title').text((chart === 'cvr' ? 'CVR ' : 'Dil ') + spec.label + ' count');
            $('#amz-dg-hist-wrap').addClass('is-open');
            const draw = function() {
                const canvas = document.getElementById('amz-dg-hist');
                if (!canvas || typeof Chart === 'undefined') return;
                if (amzDgHistChart) {
                    amzDgHistChart.destroy();
                    amzDgHistChart = null;
                }
                amzDgHistChart = new Chart(canvas.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: rows.map(function(r) { return r.label || r.date; }),
                        datasets: [{
                            data: rows.map(function(r) { return Number(r[band]) || 0; }),
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
            };
            if (typeof window.loadChartJs === 'function') {
                window.loadChartJs().then(draw).catch(draw);
            } else if (typeof Chart !== 'undefined') {
                draw();
            }
        }
        function amzDgOpenHist(chart, band) {
            const storeKey = chart === 'cvr' ? 'amz_dil_groi_cvr_hist' : 'amz_dil_groi_dil_hist';
            const live = chart === 'cvr' ? amzDgCvrLiveCounts : amzDgDilLiveCounts;
            const applyToday = function(rows) {
                const list = (rows || []).slice();
                const today = amzDgTodayKey();
                const rec = Object.assign({ date: today, label: today.slice(5) }, live);
                const last = list[list.length - 1];
                if (last && last.date === today) {
                    Object.assign(last, rec);
                } else {
                    list.push(rec);
                }
                return list;
            };
            amzDgDrawHist(chart, band, applyToday(amzDgLocalHistory(storeKey)));
        }
        function amzDgDrawPie(canvasId, chartRefName, slices, counts) {
            const total = slices.reduce(function(sum, s) { return sum + (counts[s.key] || 0); }, 0);
            const draw = function() {
                const canvas = document.getElementById(canvasId);
                if (!canvas || typeof Chart === 'undefined') return;
                if (chartRefName === 'dil' && amzDgDilPieChart) {
                    amzDgDilPieChart.destroy();
                    amzDgDilPieChart = null;
                }
                if (chartRefName === 'cvr' && amzDgCvrPieChart) {
                    amzDgCvrPieChart.destroy();
                    amzDgCvrPieChart = null;
                }
                const chart = new Chart(canvas.getContext('2d'), {
                    type: 'pie',
                    data: {
                        labels: slices.map(function(s) { return s.label; }),
                        datasets: [{
                            data: slices.map(function(s) { return counts[s.key] || 0; }),
                            backgroundColor: slices.map(function(s) { return s.color; }),
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
                if (chartRefName === 'dil') amzDgDilPieChart = chart;
                else amzDgCvrPieChart = chart;
            };
            const run = function() {
                if (typeof window.loadChartJs === 'function') {
                    window.loadChartJs().then(draw).catch(draw);
                    return;
                }
                if (typeof amzZsWithChart === 'function') {
                    amzZsWithChart(draw);
                    return;
                }
                if (typeof Chart !== 'undefined') draw();
            };
            run();
        }
        function renderAmzDilGroiPies() {
            try {
                const rules = amzDilGroiDisplayRules();
                const dilCounts = amzDilGroiCollectCounts(rules);
                const dilSlices = rules.map(function(r, i) {
                    return { key: r.key, label: r.label, color: amzDgSlabColor(i) };
                });
                if ((dilCounts._outside || 0) > 0 || !dilSlices.length) {
                    dilSlices.push({ key: '_outside', label: 'Outside', color: '#cbd5e1' });
                }
                amzDgDilSlices = dilSlices;
                amzDgDilLiveCounts = dilCounts;
                amzDgSnapLocal('amz_dil_groi_dil_hist', dilCounts);
                const dilLegend = document.getElementById('amz-dg-dil-legend');
                if (dilLegend) dilLegend.innerHTML = amzDgPieLegendHtml('Dil', dilSlices, dilCounts, 'dil');
                amzDgDrawPie('amz-dg-dil-pie', 'dil', dilSlices, dilCounts);

                const cvrCounts = amzDilGroiCollectCvrCounts();
                amzDgCvrLiveCounts = cvrCounts;
                amzDgSnapLocal('amz_dil_groi_cvr_hist', cvrCounts);
                const cvrLegend = document.getElementById('amz-dg-cvr-legend');
                if (cvrLegend) cvrLegend.innerHTML = amzDgPieLegendHtml('CVR Up / Down · CVR%', AMZ_DG_CVR_BANDS, cvrCounts, 'cvr');
                amzDgDrawPie('amz-dg-cvr-pie', 'cvr', AMZ_DG_CVR_BANDS, cvrCounts);

                $('#amz-dil-groi-tbody tr').each(function(i) {
                    const r = rules[i];
                    const $cell = $(this).find('.amz-dg-count');
                    $cell.find('.amz-dg-count-n').text(r ? (dilCounts[r.key] || 0) : 0);
                    $cell.find('.amz-dg-hist-dot').remove();
                    if (r) {
                        $cell.append(' ' + amzDgHistDotHtml('dil', r.key, amzDgSlabColor(i), r.label));
                    }
                });
            } catch (e) {
                console.warn('Sprc Dil pies', e);
            }
        }
        function destroyAmzDilGroiPies() {
            if (amzDgDilPieChart) { amzDgDilPieChart.destroy(); amzDgDilPieChart = null; }
            if (amzDgCvrPieChart) { amzDgCvrPieChart.destroy(); amzDgCvrPieChart = null; }
            if (amzDgHistChart) { amzDgHistChart.destroy(); amzDgHistChart = null; }
            $('#amz-dg-hist-wrap').removeClass('is-open');
        }
        function amzDilGroiMetaForRow(d) {
            if (!amzPefIsChildRow(d) || amzPefInv(d) <= 0) return null;
            const dil = amzPefDil(d);
            const rule = amzDilGroiMatch(dil);
            if (!rule) return null;
            const groi = rule.groi;
            const sprc = amzSpriceFromTargetGroi(d, groi);
            if (!(sprc > 0)) return null;
            return {
                dil: dil,
                key: rule.key,
                label: rule.label,
                groi: groi,
                sprc: sprc,
                zeroSoldMin: false,
            };
        }
        function amzSprcDilForRow(d) {
            const meta = amzDilGroiMetaForRow(d);
            return meta ? meta.sprc : 0;
        }
        /** Sprc Dil owns any INV > 0 SKU whose Dil matches a slab (including 0 Sold). */
        function amzDilGroiOwnsRow(d) {
            if (!d || !amzPefIsChildRow(d) || amzPefInv(d) <= 0) return false;
            const meta = amzDilGroiMetaForRow(d);
            return !!(meta && meta.sprc > 0);
        }
        function amzSkipLmpCapForRow(d, price) {
            if (typeof amazonShouldCapSpriceToLmp === 'function') {
                let raw = Number(price);
                if (!(raw > 0) && typeof computeAmzPushPrcPlan === 'function') {
                    const plan = computeAmzPushPrcPlan(d);
                    raw = (plan && plan.effective > 0) ? plan.effective : 0;
                }
                return !(raw > 0) || !amazonShouldCapSpriceToLmp(d, raw);
            }
            return false;
        }
        window.amzIsZeroSoldRow = amzIsZeroSoldRow;
        window.amzDilGroiOwnsRow = amzDilGroiOwnsRow;
        window.amzSkipLmpCapForRow = amzSkipLmpCapForRow;
        function redrawAmzSprcDilColumn() {
            if (typeof table === 'undefined' || !table) return;
            try {
                const col = table.getColumn('SPRC_DIL');
                if (col) table.redraw(true);
            } catch (e) { /* ignore */ }
        }
        function amzAfterDilGroiRulesChanged() {
            redrawAmzSprcDilColumn();
            if (typeof amzScheduleRuleSpriceSync === 'function') {
                amzScheduleRuleSpriceSync({ force: true, delay: 250 });
            }
        }
        function cascadeAmzDilGroiFromFirst() {
            const $rows = $('#amz-dil-groi-tbody tr');
            if (!$rows.length) return;
            const firstVal = parseFloat($rows.eq(0).find('.amz-dg-groi').val());
            if (!isFinite(firstVal)) return;
            $rows.each(function(i) {
                const groi = firstVal + (i * 5);
                const $inp = $(this).find('.amz-dg-groi');
                if (i > 0) $inp.val(groi);
            });
            readAmzDilGroiRulesFromModal();
        }
        function amzOnDilGroiNumberChanged(inputEl) {
            const first = $('#amz-dil-groi-tbody .amz-dg-groi').get(0);
            if (inputEl === first) {
                cascadeAmzDilGroiFromFirst();
            } else {
                readAmzDilGroiRulesFromModal();
            }
            renderAmzDilGroiPies();
            amzAfterDilGroiRulesChanged();
        }
        function renderAmzDilGroiModalTable() {
            const $tb = $('#amz-dil-groi-tbody');
            if (!$tb.length) return;
            $tb.empty();
            const list = amzNormalizeDilGroiList(amzDilGroiRules);
            amzDilGroiRules = list.length
                ? list
                : AMZ_DIL_GROI_DEFAULTS.map(function(r) { return Object.assign({}, r); });
            const canDelete = amzDilGroiRules.length > 1;
            amzDilGroiRules.forEach(function(r, idx) {
                const first = idx === 0;
                $tb.append(
                    '<tr data-idx="' + idx + '" data-key="' + String(r.key).replace(/"/g, '&quot;') + '">'
                    + '<td><input type="number" min="0" step="0.1" class="form-control form-control-sm amz-dil-groi-input amz-dg-min" value="' + r.min + '"></td>'
                    + '<td><input type="number" min="0" step="0.1" class="form-control form-control-sm amz-dil-groi-input amz-dg-max" value="' + r.max + '"></td>'
                    + '<td class="amz-dg-count"><span class="amz-dg-count-n">0</span></td>'
                    + '<td class="text-end">'
                    + '<input type="number" class="form-control form-control-sm amz-dil-groi-input amz-dg-groi" '
                    + 'step="0.1" value="' + r.groi + '"'
                    + (first ? ' title="Changing this sets following slabs to +5 each"' : '')
                    + '>'
                    + '</td>'
                    + '<td class="text-center">'
                    + (canDelete
                        ? '<button type="button" class="amz-dil-groi-row-del" data-idx="' + idx + '" title="Remove slab">&times;</button>'
                        : '')
                    + '</td></tr>'
                );
            });
            if ($('#amzDilGroiModal').hasClass('show')) {
                renderAmzDilGroiPies();
            } else {
                try {
                    const rules = amzDilGroiDisplayRules();
                    const dilCounts = amzDilGroiCollectCounts(rules);
                    $('#amz-dil-groi-tbody tr').each(function(i) {
                        const r = rules[i];
                        $(this).find('.amz-dg-count-n').text(r ? (dilCounts[r.key] || 0) : 0);
                    });
                } catch (e) { /* ignore */ }
            }
        }
        function readAmzDilGroiRulesFromModal() {
            const rules = [];
            $('#amz-dil-groi-tbody tr').each(function() {
                const rule = amzNormalizeDilGroiRule({
                    min: parseFloat($(this).find('.amz-dg-min').val()),
                    max: parseFloat($(this).find('.amz-dg-max').val()),
                    groi: parseFloat($(this).find('.amz-dg-groi').val()),
                });
                if (rule) rules.push(rule);
            });
            amzDilGroiRules = rules.length
                ? amzNormalizeDilGroiList(rules)
                : AMZ_DIL_GROI_DEFAULTS.map(function(r) { return Object.assign({}, r); });
            return amzDilGroiRules.map(function(r) {
                return { key: r.key, label: r.label, min: r.min, max: r.max, groi: Number(r.groi) || 0 };
            });
        }
        function amzDilGroiAddSlab() {
            readAmzDilGroiRulesFromModal();
            let nextMin = 0.1;
            let lastGroi = 50;
            amzDilGroiRules.forEach(function(r) {
                const hi = Number(r.max);
                if (isFinite(hi) && hi > nextMin) nextMin = hi;
                if (isFinite(Number(r.groi))) lastGroi = Number(r.groi);
            });
            const nextMax = amzPefRound2(nextMin + 5);
            const nextGroi = Math.max(0, amzPefRound2(lastGroi + 5));
            const added = amzNormalizeDilGroiRule({ min: nextMin, max: nextMax, groi: nextGroi });
            if (!added) {
                amzPefToast('error', 'Could not add slab');
                return;
            }
            amzDilGroiRules.push(added);
            amzDilGroiRules = amzNormalizeDilGroiList(amzDilGroiRules);
            renderAmzDilGroiModalTable();
            amzAfterDilGroiRulesChanged();
        }
        function amzDilGroiDeleteSlab(idx) {
            const rules = [];
            $('#amz-dil-groi-tbody tr').each(function() {
                const rule = amzNormalizeDilGroiRule({
                    min: parseFloat($(this).find('.amz-dg-min').val()),
                    max: parseFloat($(this).find('.amz-dg-max').val()),
                    groi: parseFloat($(this).find('.amz-dg-groi').val()),
                });
                if (rule) rules.push(rule);
            });
            if (rules.length <= 1) {
                amzPefToast('error', 'Keep at least one Dil slab');
                return;
            }
            if (!isFinite(idx) || idx < 0 || idx >= rules.length) return;
            rules.splice(idx, 1);
            amzDilGroiRules = amzNormalizeDilGroiList(rules);
            renderAmzDilGroiModalTable();
            amzAfterDilGroiRulesChanged();
        }
        async function loadAmzDilGroiRules() {
            if (!$('#amz-dil-groi-tbody tr').length) {
                renderAmzDilGroiModalTable();
            }
            $('#amz-dil-groi-status').text('Loading saved slabs…');
            try {
                const res = await $.ajax({
                    url: '/channel-promo-pricing/amazon/dil-groi',
                    method: 'GET',
                    dataType: 'json',
                    headers: { 'Accept': 'application/json' },
                });
                const fromServer = amzNormalizeDilGroiList(
                    (res && Array.isArray(res.rules)) ? res.rules
                        : (res && res.rules && Array.isArray(res.rules.rules) ? res.rules.rules : [])
                );
                if (fromServer.length) {
                    amzDilGroiRules = fromServer;
                }
                renderAmzDilGroiModalTable();
                redrawAmzSprcDilColumn();
                if (fromServer.length && !(res && res.is_default)) {
                    $('#amz-dil-groi-status').text('Loaded saved Dil → GROI slabs from API.');
                } else {
                    $('#amz-dil-groi-status').text('Using first-time defaults (0.1–5 → 50 … 20–25 → 70, +5 each). Add or delete slabs, then Save and Apply.');
                }
            } catch (e) {
                renderAmzDilGroiModalTable();
                const reason = (e && e.responseJSON && e.responseJSON.message)
                    || (e && e.status ? ('HTTP ' + e.status) : 'network error');
                $('#amz-dil-groi-status').text('Could not load saved rules from API (' + reason + ') — using defaults.');
            }
        }
        function saveAmzDilGroiRules() {
            const rules = readAmzDilGroiRulesFromModal();
            return $.ajax({
                url: '/channel-promo-pricing/amazon/dil-groi',
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': amzPefCsrf(),
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                data: JSON.stringify({ rules: rules, _token: amzPefCsrf() }),
            }).then(function(res) {
                if (res && Array.isArray(res.rules)) {
                    const saved = amzNormalizeDilGroiList(res.rules);
                    if (saved.length) {
                        amzDilGroiRules = saved;
                    }
                    renderAmzDilGroiModalTable();
                }
                amzAfterDilGroiRulesChanged();
                $('#amz-dil-groi-status').text('Saved via API and applied. S PRC written from Sprc Dil.');
                return res;
            });
        }
        function openAmzDilGroiModal() {
            const modalEl = document.getElementById('amzDilGroiModal');
            if (!modalEl) return;
            renderAmzDilGroiModalTable();
            loadAmzDilGroiRules();
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
        window.amzSprcDilForRow = amzSprcDilForRow;
        window.amzDilGroiMetaForRow = amzDilGroiMetaForRow;
        window.openAmzDilGroiModal = openAmzDilGroiModal;


        function amzCdWithChart(fn) {
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
        function amzCvrDiscCollectCounts() {
            const counts = {};
            AMZ_CD_SLAB_META.forEach(function(s) { counts[s.key] = 0; });
            if (typeof table === 'undefined' || !table || typeof table.getRows !== 'function') {
                return counts;
            }
            table.getRows('active').forEach(function(row) {
                const d = row.getData() || {};
                if (!amzPefIsChildRow(d) || amzPefInv(d) <= 0) return;
                const key = pefCvrSlabKey(amzPefCvr(d));
                counts[key] = (counts[key] || 0) + 1;
            });
            return counts;
        }
        function amzCdSnapLocal(counts) {
            try {
                const key = 'amz_cvr_disc_slab_hist';
                const today = new Date().toISOString().slice(0, 10);
                let hist = {};
                try { hist = JSON.parse(localStorage.getItem(key) || '{}') || {}; } catch (e) { hist = {}; }
                hist[today] = counts;
                const keys = Object.keys(hist).sort();
                while (keys.length > 90) delete hist[keys.shift()];
                localStorage.setItem(key, JSON.stringify(hist));
            } catch (e) { /* ignore */ }
        }
        function amzCdLocalHistory() {
            try {
                const hist = JSON.parse(localStorage.getItem('amz_cvr_disc_slab_hist') || '{}') || {};
                return Object.keys(hist).sort().map(function(date) {
                    return Object.assign({ date: date, label: date.slice(5) }, hist[date] || {});
                });
            } catch (e) {
                return [];
            }
        }
        function amzCdHistDotHtml(key, color, label) {
            return '<button type="button" class="amz-cd-hist-dot" data-band="' + String(key).replace(/"/g, '&quot;') + '" '
                + 'style="background:' + color + ';" title="' + String(label).replace(/"/g, '&quot;') + ' daily history"></button>';
        }
        function renderAmzCvrDiscPie() {
            amzCdLiveCounts = amzCvrDiscCollectCounts();
            amzCdSnapLocal(amzCdLiveCounts);
            const total = AMZ_CD_SLAB_META.reduce(function(sum, s) {
                return sum + (amzCdLiveCounts[s.key] || 0);
            }, 0);
            const legend = document.getElementById('amz-cd-pie-legend');
            if (legend) {
                legend.innerHTML = '<div class="amz-cd-pie-row" style="color:#94a3b8;font-size:10px;font-weight:600;">'
                    + '<span class="amz-cd-pie-swatch" style="visibility:hidden;"></span>'
                    + '<span class="amz-cd-pie-name">CVR slab</span>'
                    + '<span class="amz-cd-pie-count">count</span>'
                    + '<span class="amz-cd-pie-pct">of total</span>'
                    + '<span class="amz-cd-hist-dot" style="visibility:hidden;"></span>'
                    + '</div>'
                    + AMZ_CD_SLAB_META.map(function(s) {
                        const n = amzCdLiveCounts[s.key] || 0;
                        const pct = total > 0 ? Math.round((n / total) * 100) : 0;
                        return '<div class="amz-cd-pie-row">'
                            + '<span class="amz-cd-pie-swatch" style="background:' + s.color + ';"></span>'
                            + '<span class="amz-cd-pie-name">' + s.label + '</span>'
                            + '<span class="amz-cd-pie-count">' + n + '</span>'
                            + '<span class="amz-cd-pie-pct" title="' + pct + ' of total">' + pct + '</span>'
                            + amzCdHistDotHtml(s.key, s.color, s.label)
                            + '</div>';
                    }).join('');
            }
            amzCdWithChart(function() {
                const canvas = document.getElementById('amz-cd-pie');
                if (!canvas || typeof Chart === 'undefined') return;
                if (amzCdPieChart) {
                    amzCdPieChart.destroy();
                    amzCdPieChart = null;
                }
                amzCdPieChart = new Chart(canvas.getContext('2d'), {
                    type: 'pie',
                    data: {
                        labels: AMZ_CD_SLAB_META.map(function(s) { return s.label; }),
                        datasets: [{
                            data: AMZ_CD_SLAB_META.map(function(s) { return amzCdLiveCounts[s.key] || 0; }),
                            backgroundColor: AMZ_CD_SLAB_META.map(function(s) { return s.color; }),
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
            $('#amz-cvr-disc-tbody tr').each(function() {
                const key = String($(this).attr('data-key') || '');
                $(this).find('.amz-cvr-disc-count-n').text(amzCdLiveCounts[key] || 0);
            });
        }
        function amzCdDrawHist(band, rows) {
            const spec = AMZ_CD_SLAB_META.find(function(s) { return s.key === band; }) || AMZ_CD_SLAB_META[0];
            $('#amz-cd-hist-title').text(spec.label + ' count');
            $('#amz-cd-hist-wrap').addClass('is-open');
            amzCdWithChart(function() {
                const canvas = document.getElementById('amz-cd-hist');
                if (!canvas || typeof Chart === 'undefined') return;
                if (amzCdHistChart) {
                    amzCdHistChart.destroy();
                    amzCdHistChart = null;
                }
                amzCdHistChart = new Chart(canvas.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: rows.map(function(r) { return r.label || r.date; }),
                        datasets: [{
                            data: rows.map(function(r) { return Number(r[band]) || 0; }),
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
        function amzCdOpenHist(band) {
            const applyToday = function(rows) {
                const list = rows.slice();
                const today = new Date().toISOString().slice(0, 10);
                const live = Object.assign({}, amzCdLiveCounts);
                if (!list.some(function(r) { return r.date === today; })) {
                    list.push(Object.assign({ date: today, label: today.slice(5) }, live));
                } else {
                    list.forEach(function(r) {
                        if (r.date === today) Object.assign(r, live);
                    });
                }
                return list;
            };
            $.ajax({
                url: '/amazon-cvr-disc-slab-history',
                method: 'GET',
                data: { days: 30 },
            }).done(function(res) {
                const rows = (res && res.success && Array.isArray(res.data)) ? res.data : amzCdLocalHistory();
                amzCdDrawHist(band, applyToday(rows));
            }).fail(function() {
                amzCdDrawHist(band, applyToday(amzCdLocalHistory()));
            });
        }
        function renderAmzCvrDiscModalTable() {
            const counts = amzCvrDiscCollectCounts();
            const $tb = $('#amz-cvr-disc-tbody').empty();
            amzCvrDiscRules.forEach(function(r, idx) {
                const disc = isFinite(Number(r.disc)) ? Number(r.disc) : 0;
                const meta = AMZ_CD_SLAB_META.find(function(s) { return s.key === r.key; });
                const color = meta ? meta.color : '#64748b';
                $tb.append(
                    '<tr data-key="' + String(r.key).replace(/"/g, '&quot;') + '">'
                    + '<td>' + String(r.label || r.key) + '</td>'
                    + '<td class="amz-cvr-disc-count">'
                    + '<span class="amz-cvr-disc-count-n">' + (counts[r.key] || 0) + '</span> '
                    + amzCdHistDotHtml(r.key, color, r.label || r.key)
                    + '</td>'
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
                    amzScheduleRuleSpriceSync({ force: true, delay: 200 });
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

        function amzReviewDiscCollectCounts() {
            const counts = {};
            amzReviewDiscRules.forEach(function(r) {
                const rule = amzNormalizeReviewDiscRule(r);
                if (rule) counts[rule.key] = 0;
            });
            if (typeof table === 'undefined' || !table || typeof table.getRows !== 'function') {
                return counts;
            }
            table.getRows('active').forEach(function(row) {
                const d = row.getData() || {};
                if (!amzPefIsChildRow(d) || amzPefInv(d) === 0) return;
                const count = amzPefReviewCount(d);
                const cap = Number(amzReviewDiscMax);
                const maxRev = (isFinite(cap) && cap > 0) ? cap : AMZ_REVIEW_DISC_MAX_DEFAULT;
                if (!(count > 0) || count > maxRev) return;
                for (let i = 0; i < amzReviewDiscRules.length; i++) {
                    const rule = amzNormalizeReviewDiscRule(amzReviewDiscRules[i]);
                    if (!rule) continue;
                    if (count >= rule.min && count <= rule.max) {
                        counts[rule.key] = (counts[rule.key] || 0) + 1;
                        return;
                    }
                }
            });
            return counts;
        }
        function renderAmzReviewDiscModalTable() {
            const counts = amzReviewDiscCollectCounts();
            const $tb = $('#amz-review-disc-tbody').empty();
            const maxEl = document.getElementById('amz-review-disc-max');
            if (maxEl) maxEl.value = String(amzReviewDiscMax || AMZ_REVIEW_DISC_MAX_DEFAULT);
            if (!amzReviewDiscRules.length) {
                amzReviewDiscRules = AMZ_REVIEW_DISC_DEFAULTS.map(function(r) { return Object.assign({}, r); });
            }
            amzReviewDiscRules.forEach(function(r, idx) {
                const rule = amzNormalizeReviewDiscRule(r) || { min: 1, max: 2, disc: 4, key: '1-2' };
                $tb.append(
                    '<tr data-idx="' + idx + '">'
                    + '<td><input type="number" min="0" step="1" class="form-control form-control-sm amz-review-disc-input amz-rd-min" value="' + rule.min + '"></td>'
                    + '<td><input type="number" min="0" step="1" class="form-control form-control-sm amz-review-disc-input amz-rd-max" value="' + rule.max + '"></td>'
                    + '<td class="amz-review-disc-count">' + (counts[rule.key] || 0) + '</td>'
                    + '<td class="text-end">'
                    + '<input type="number" class="form-control form-control-sm amz-review-disc-input amz-rd-disc" '
                    + 'min="0" step="0.1" value="' + rule.disc + '">'
                    + '</td>'
                    + '<td class="text-center">'
                    + '<button type="button" class="amz-review-disc-row-del" data-idx="' + idx + '" title="Remove range">&times;</button>'
                    + '</td></tr>'
                );
            });
        }
        function readAmzReviewDiscRulesFromModal() {
            const rules = [];
            $('#amz-review-disc-tbody tr').each(function() {
                const min = parseInt($(this).find('.amz-rd-min').val(), 10);
                const max = parseInt($(this).find('.amz-rd-max').val(), 10);
                const disc = parseFloat($(this).find('.amz-rd-disc').val());
                const rule = amzNormalizeReviewDiscRule({ min: min, max: max, disc: disc });
                if (rule) rules.push(rule);
            });
            amzReviewDiscRules = rules.length
                ? rules
                : AMZ_REVIEW_DISC_DEFAULTS.map(function(r) { return Object.assign({}, r); });
            const maxRaw = parseInt($('#amz-review-disc-max').val(), 10);
            amzReviewDiscMax = (isFinite(maxRaw) && maxRaw > 0) ? maxRaw : AMZ_REVIEW_DISC_MAX_DEFAULT;
            return amzReviewDiscRules.map(function(r) {
                return { key: r.key, min: r.min, max: r.max, label: r.label, disc: Number(r.disc) || 0 };
            });
        }
        function amzReviewDiscAddRange() {
            readAmzReviewDiscRulesFromModal();
            let nextMin = 1;
            amzReviewDiscRules.forEach(function(r) {
                const hi = Number(r.max);
                if (isFinite(hi) && hi + 1 > nextMin) nextMin = hi + 1;
            });
            const cap = Number(amzReviewDiscMax) || AMZ_REVIEW_DISC_MAX_DEFAULT;
            if (nextMin > cap) {
                amzPefToast('error', 'Cannot add a range above Max reviews (' + cap + ')');
                return;
            }
            const nextMax = Math.min(nextMin + 1, cap);
            amzReviewDiscRules.push(amzNormalizeReviewDiscRule({ min: nextMin, max: nextMax, disc: 4 }));
            renderAmzReviewDiscModalTable();
        }
        async function loadAmzReviewDiscRules() {
            $('#amz-review-disc-status').text('Loading…');
            try {
                const res = await $.ajax({
                    url: '/amazon-review-disc',
                    method: 'GET',
                    dataType: 'json',
                });
                if (res && Array.isArray(res.rules) && res.rules.length) {
                    amzReviewDiscRules = res.rules.map(function(r) {
                        return amzNormalizeReviewDiscRule(r) || Object.assign({}, r);
                    }).filter(Boolean);
                }
                if (res && isFinite(Number(res.max_reviews)) && Number(res.max_reviews) > 0) {
                    amzReviewDiscMax = Number(res.max_reviews);
                }
                renderAmzReviewDiscModalTable();
                $('#amz-review-disc-status').text(res && res.is_default
                    ? 'Using defaults (1–2 = 4%, 2–3 = 4%; max 4 reviews).'
                    : 'Loaded saved Review Disc rules.');
            } catch (e) {
                renderAmzReviewDiscModalTable();
                $('#amz-review-disc-status').text('Could not load saved rules — using defaults.');
            }
        }
        function saveAmzReviewDiscRules() {
            const rules = readAmzReviewDiscRulesFromModal();
            return $.ajax({
                url: '/amazon-review-disc',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': amzPefCsrf(), 'Accept': 'application/json' },
                data: { rules: rules, max_reviews: amzReviewDiscMax, _token: amzPefCsrf() },
            }).then(function(res) {
                if (res && Array.isArray(res.rules)) {
                    amzReviewDiscRules = res.rules.map(function(r) {
                        return amzNormalizeReviewDiscRule(r) || Object.assign({}, r);
                    }).filter(Boolean);
                    if (isFinite(Number(res.max_reviews)) && Number(res.max_reviews) > 0) {
                        amzReviewDiscMax = Number(res.max_reviews);
                    }
                    renderAmzReviewDiscModalTable();
                }
                return res;
            });
        }
        async function saveAndApplyAmzReviewDisc() {
            const $btn = $('#amz-review-disc-apply-btn');
            const html = $btn.html();
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
            try {
                await saveAmzReviewDiscRules();
                const picked = collectAmzPromoApplyTargets('No rows to apply Review Disc');
                if (!picked.cancelled) {
                    applyAmzCombinedPlanToTargets(picked.targets, picked.label, {
                        toastLabel: 'Review Disc',
                        match: function(d) {
                            return (Number(computeAmzReviewDiscountPct(d)) || 0) > 0;
                        },
                    });
                    $('#amz-review-disc-status').text('Saved. Applied to matching SKUs.');
                } else {
                    $('#amz-review-disc-status').text('Saved. Rev Disc. column updated.');
                    if (table) {
                        try { table.getColumn('review_discount') && table.redraw(true); } catch (e) { /* ignore */ }
                    }
                    amzScheduleRuleSpriceSync({ force: true, delay: 200 });
                }
                const modalEl = document.getElementById('amzReviewDiscModal');
                if (modalEl) bootstrap.Modal.getOrCreateInstance(modalEl).hide();
            } catch (e) {
                amzPefToast('error', 'Failed to save Review Disc rules');
                $('#amz-review-disc-status').text('Save failed.');
            } finally {
                $btn.prop('disabled', false).html(html);
            }
        }

        function amzPefLmpDiffAmount(d) {
            const price = Number(d.price) || 0;
            const lmp = amzPefLmp(d);
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
                amzPersistClearThenSprice(row, patch.SPRICE, true);
            }
            if (opts.redraw && table) table.redraw(true);
        }
        function applyAmzApprDiscount(row) {
            const d = row.getData();
            const amt = amzPefLmpDiffAmount(d);
            const lmp = amzPefLmp(d);
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
            amzPersistClearThenSprice(row, newPrice, true);
            if (table) table.redraw(true);
            return true;
        }
        async function applyAmzPefPromoFromCell(cell, kind) {
            const fieldMeta = {
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
                amzPersistClearThenSprice(item.row, newPrice, true, {});
                ok++;
            }
            amzPefToast(
                ok ? 'success' : 'error',
                fieldMeta.label + ' → ' + ok + ' row(s)' + (skipped ? ('; skipped ' + skipped) : '')
            );
            if (table) table.redraw(true);
            amzScheduleRuleSpriceSync({ force: true, delay: 250 });
        }

        function amazonPefPromoColumns() {
            return [
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
                    title: 'Rev Disc.',
                    field: 'review_discount',
                    width: 70,
                    hozAlign: 'center',
                    vertAlign: 'middle',
                    headerSort: true,
                    headerTooltip: 'Review Disc. — from Review Disc rules (1–2 / 2–3 = 4% by default). Reviews > Max (4) → 0%. INV=0 → 0%. Read-only.',
                    sorter: function(a, b, aRow, bRow) {
                        const av = computeAmzReviewDiscountPct(aRow.getData()) || 0;
                        const bv = computeAmzReviewDiscountPct(bRow.getData()) || 0;
                        return av - bv;
                    },
                    formatter: function(cell) {
                        const d = cell.getRow().getData() || {};
                        if (d.is_parent_summary) return '';
                        if (!amzPefIsChildRow(d)) return '';
                        const pct = computeAmzReviewDiscountPct(d);
                        const count = amzPefReviewCount(d);
                        const base = Number(d.STANDARD_PRICE) > 0
                            ? Number(d.STANDARD_PRICE)
                            : (Number(d.price) || 0);
                        const dollars = (pct > 0 && base > 0) ? amzPefRound2(base * (pct / 100)) : 0;
                        const tip = (count > 0 ? (count + ' review' + (count === 1 ? '' : 's')) : '0 reviews')
                            + ' → discount ' + (pct || 0) + '%'
                            + (count > (Number(amzReviewDiscMax) || AMZ_REVIEW_DISC_MAX_DEFAULT)
                                ? (' (above max ' + (amzReviewDiscMax || AMZ_REVIEW_DISC_MAX_DEFAULT) + ')')
                                : '')
                            + (dollars > 0 ? (' ≈ $' + dollars.toFixed(2) + ' off Std/Price') : '');
                        return '<span title="' + amzPefEscAttr(tip) + '">'
                            + fmtAmzReviewDiscountBadge(pct) + '</span>';
                    },
                },
                ...(typeof tDiscountsColumn === 'function' ? [Object.assign({}, tDiscountsColumn(computeAmzTDiscountsPct), {
                    headerTooltip: 'CVR Disc + Rev Disc. Sprc Dil Sales use GROI, not this %.',
                })] : []),
                {
                    title: 'Push Prc',
                    field: 'push_prc',
                    width: 78,
                    hozAlign: 'center',
                    vertAlign: 'middle',
                    headerSort: true,
                    sorter: function(a, b, aRow, bRow) {
                        const val = function(row) {
                            const plan = (typeof amzPushPrcPlanForQueue === 'function')
                                ? amzPushPrcPlanForQueue(row)
                                : ((typeof computeAmzPushPrcPlan === 'function')
                                    ? computeAmzPushPrcPlan(row)
                                    : null);
                            return (plan && plan.effective > 0) ? plan.effective : 0;
                        };
                        return val(aRow.getData()) - val(bRow.getData());
                    },
                    headerTooltip: 'Push Prc: Your=Std. Sprc Dil (Dil in slab, including 0 Sold) → Sale=GROI. Other rows → Sale=Std−(CVR Disc+Rev Disc), LMP-capped to match S PRC. Sale=Biz=Min. Dot = PDT history.',
                    formatter: function(cell) {
                        const d = cell.getRow().getData() || {};
                        if (!amzPefIsChildRow(d)) return '';
                        const sku = amzPefSku(d);
                        const plan = (typeof amzPushPrcPlanForQueue === 'function')
                            ? amzPushPrcPlanForQueue(d)
                            : computeAmzPushPrcPlan(d);
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
         * Live rule stack for this SKU.
         * CVR Disc = CVR slab (INV=0 or CVR≤0 → 0)
         * Rev Disc = review-count slab (INV=0 or count 0 or count > max → 0)
         * Sprc Dil = Dil slab → Target GROI (every INV > 0 SKU, including 0 Sold)
         */
        function computeAmzRuleStack(d) {
            const cvrDisc = Math.max(0, Number(typeof computeAmzCvrDiscountPct === 'function' ? computeAmzCvrDiscountPct(d) : 0) || 0);
            const reviewDisc = Math.max(0, Number(typeof computeAmzReviewDiscountPct === 'function' ? computeAmzReviewDiscountPct(d) : 0) || 0);
            const zeroSold = typeof amzIsZeroSoldRow === 'function' && amzIsZeroSoldRow(d);
            const dilGroiMeta = (typeof amzDilGroiMetaForRow === 'function')
                ? amzDilGroiMetaForRow(d)
                : null;
            const dilGroi = !!(dilGroiMeta && dilGroiMeta.sprc > 0);
            const totalDisc = amzPefRound2(Math.min(99.99, Math.max(0, cvrDisc + reviewDisc)));
            return {
                prmt: 0,
                cvrDisc: cvrDisc,
                reviewDisc: reviewDisc,
                cvrUpDn: 0,
                zeroSold: !!zeroSold,
                zeroSoldGroi: (zeroSold && dilGroi) ? dilGroiMeta.groi : null,
                zeroSoldPrice: null,
                dilGroi: dilGroi,
                dilGroiGroi: dilGroi ? dilGroiMeta.groi : null,
                dilGroiLabel: dilGroi ? dilGroiMeta.label : null,
                dilGroiPrice: dilGroi ? dilGroiMeta.sprc : null,
                totalDisc: totalDisc,
            };
        }
        function formatAmzPushPrcDiscNote(plan) {
            const parts = [];
            if (plan.dilGroi) {
                parts.push('Sprc Dil GROI ' + (plan.dilGroiGroi != null ? plan.dilGroiGroi : '') + '%'
                    + (plan.dilGroiLabel ? (' · ' + plan.dilGroiLabel) : ''));
                return parts.length ? ' (' + parts.join(' + ') + ')' : '';
            }
            if (plan.cvrDisc) parts.push('CVR Disc ' + plan.cvrDisc + '%');
            if (plan.reviewDisc) parts.push('Rev Disc ' + plan.reviewDisc + '%');
            if (plan.lmpCapped) parts.push('LMP cap');
            return parts.length ? ' (' + parts.join(' + ') + ')' : '';
        }
        /**
         * Push Prc plan per SKU:
         *  Sprc Dil (Dil in slab, including 0 Sold) → Sale = Dil→GROI target (does not stack discounts)
         *  Other  → Sale = Std × (1 − (CVR Disc + Rev Disc)/100)
         *  Your = Std; Sale = Business = Min
         */
        function computeAmzTDiscountsPct(d) {
            const stack = computeAmzRuleStack(d);
            if (stack.dilGroi) return 0;
            return stack.totalDisc;
        }
        function computeAmzPushPrcPlan(d) {
            const std = Number(d.STANDARD_PRICE) || 0;
            const stack = computeAmzRuleStack(d);
            let sale = null;
            if (stack.dilGroi && stack.dilGroiPrice != null) {
                sale = stack.dilGroiPrice;
                if (!(sale >= 0.01)) sale = null;
            } else if (!(std > 0)) {
                return null;
            } else if (stack.totalDisc > 0 && stack.totalDisc < 100) {
                sale = amzPefRound2(std * (1 - (stack.totalDisc / 100)));
                if (!(sale >= 0.01) || sale >= std) sale = null;
            }
            const saleBase = sale != null ? sale : (std > 0 ? amzPefRound2(std) : 0);
            if (!(saleBase > 0)) return null;
            const effective = sale != null ? sale : std;
            if (!(effective > 0)) return null;
            const max = std > 0 ? amzPefRound2(std * 1.10) : saleBase;
            return {
                std: std > 0 ? amzPefRound2(std) : saleBase,
                sale: sale,
                max: max,
                min: saleBase,
                business: saleBase,
                prmt: stack.prmt,
                cvrDisc: stack.cvrDisc,
                reviewDisc: stack.reviewDisc,
                cvrUpDn: stack.cvrUpDn,
                zeroSold: stack.zeroSold,
                zeroSoldGroi: stack.zeroSoldGroi,
                dilGroi: stack.dilGroi,
                dilGroiGroi: stack.dilGroiGroi,
                dilGroiLabel: stack.dilGroiLabel,
                totalDisc: stack.totalDisc,
                effective: effective,
            };
        }
        /**
         * Sale / Min / Biz / effective must match visible S PRC (LMP-capped
         * when LMP is lower and SGROI at LMP is ≥ 20%). Tick used to compare
         * Price to the uncapped sale while S PRC showed LMP — click looked dead.
         */
        function amzLmpAlignPushPrcPlan(plan, d) {
            if (!plan || !(plan.effective > 0)) return plan;
            const origEffective = Number(plan.effective) || 0;
            const capped = (typeof amzFinalSpriceToSave === 'function')
                ? amzFinalSpriceToSave(d, plan.effective)
                : ((typeof amzCapRuleSprice === 'function') ? amzCapRuleSprice(d, plan.effective) : plan.effective);
            if (!(capped > 0)) return plan;
            plan.effective = capped;
            if (plan.sale != null) {
                const saleCap = (typeof amzFinalSpriceToSave === 'function')
                    ? amzFinalSpriceToSave(d, plan.sale)
                    : capped;
                plan.sale = saleCap > 0 ? saleCap : capped;
            } else if (plan.std > 0 && capped + 0.0001 < plan.std) {
                plan.sale = capped;
            }
            const saleBase = plan.sale != null ? plan.sale : capped;
            plan.min = saleBase;
            plan.business = saleBase;
            plan.lmpCapped = (origEffective - capped) > 0.009;
            return plan;
        }
        function amzPushPrcPlanForQueue(d) {
            const plan = computeAmzPushPrcPlan(d);
            return amzLmpAlignPushPrcPlan(plan, d);
        }
        function amzListingAlreadyAtPushTarget(d, plan) {
            const target = (plan && plan.effective > 0)
                ? plan.effective
                : ((typeof amzDisplayedSprice === 'function') ? amzDisplayedSprice(d) : 0);
            if (typeof amazonListingPriceEqualsSprice === 'function') {
                return amazonListingPriceEqualsSprice(d, target);
            }
            return Number(d && d.price) > 0 && target > 0
                && Math.round(Number(d.price) * 100) === Math.round(Number(target) * 100);
        }
        /** @deprecated use computeAmzPushPrcPlan — kept for any leftover callers */
        function computeAmzPushPrcFromStd(d) {
            const plan = computeAmzPushPrcPlan(d);
            return plan ? plan.effective : null;
        }

        function amzCapRuleSprice(d, price) {
            let n = Number(price) || 0;
            if (!(n > 0)) return 0;
            if (typeof amazonCapSpriceToLmp === 'function') {
                n = amazonCapSpriceToLmp(d, n);
            } else {
                const lmp = amzPefLmp(d);
                if (lmp > 0 && n + 0.0001 >= lmp) {
                    let sgroiAtLmp = null;
                    if (typeof amazonSgroiAtPrice === 'function') {
                        sgroiAtLmp = amazonSgroiAtPrice(d, lmp);
                    } else {
                        const lp = parseFloat(d && d.LP_productmaster);
                        if (lp > 0) {
                            const ship = parseFloat(d.Ship_productmaster) || 0;
                            sgroiAtLmp = ((lmp * 0.80 - ship - lp) / lp) * 100;
                        }
                    }
                    if (sgroiAtLmp == null || sgroiAtLmp >= 20) n = lmp;
                }
            }
            return n > 0 ? amzPefRound2(n) : 0;
        }
        /** Live S PRC from Dil / CVR Disc / 0 Sold. Ignores stored SPRICE. */
        function amzLiveRuleSprice(d) {
            if (!d || !amzPefIsChildRow(d) || amzPefInv(d) === 0) return 0;
            const plan = computeAmzPushPrcPlan(d);
            if (!plan || !(plan.effective > 0)) return 0;
            return amzCapRuleSprice(d, plan.effective);
        }
        /** Keep the in-memory row (and allTableData copy) equal to the visible rule price. */
        function amzWriteStoredSpriceOnRow(d, live) {
            if (!d || !(live > 0)) return;
            d.SPRICE = live;
            d.has_custom_sprice = true;
            if (typeof allTableData === 'undefined' || !Array.isArray(allTableData)) return;
            const sku = amzPefSku(d);
            if (!sku) return;
            for (let i = 0; i < allTableData.length; i++) {
                if (amzPefSku(allTableData[i]) === sku) {
                    allTableData[i].SPRICE = live;
                    allTableData[i].has_custom_sprice = true;
                    break;
                }
            }
        }
        /** Visible S PRC = live discounted / LMP-capped rule price. Does not mutate stored SPRICE. */
        function amzDisplayedSprice(d) {
            const live = amzLiveRuleSprice(d);
            if (live > 0) return live;
            const stored = parseFloat(d && d.SPRICE) || 0;
            return stored > 0 ? amzPefRound2(stored) : 0;
        }
        window.amzLiveRuleSprice = amzLiveRuleSprice;
        window.amzDisplayedSprice = amzDisplayedSprice;

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
         * Write S PRC from the live Push Prc stack (CVR Disc + Rev Disc + 0 Sold + Sprc Dil).
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
                    skipped++;
                    continue;
                }
                if (matchFn && !matchFn(d)) { unmatched++; continue; }
                const plan = (typeof amzRuleSpricePlanForRow === 'function')
                    ? amzRuleSpricePlanForRow(d)
                    : computeAmzPushPrcPlan(d);
                if (!plan || !(plan.effective > 0)) { skipped++; continue; }
                plan.effective = amzFinalSpriceToSave(d, plan.effective);
                if (!(plan.effective > 0)) { skipped++; continue; }
                if (opts.skipWhenNoSale && plan.sale == null) {
                    applyAmzPushPrcToSpriceRow(item.row, plan, null);
                    amzPersistClearThenSprice(item.row, plan.effective, true);
                    ok++;
                    continue;
                }
                applyAmzPushPrcToSpriceRow(item.row, plan, null);
                amzPersistClearThenSprice(item.row, plan.effective, true);
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
            amzScheduleRuleSpriceSync({ force: true, delay: 600 });
            return ok;
        }

        /** Apply result price to S PRC + margin columns (SGPFT / SGROI / SROI / Spft%). */
        function applyAmzPushPrcToSpriceRow(row, plan, saveRes) {
            const d = row.getData();
            const finalPrc = amzFinalSpriceToSave(d, plan && plan.effective);
            if (plan) plan.effective = finalPrc;
            if (finalPrc > 0) amzWriteStoredSpriceOnRow(d, finalPrc);
            const updates = {
                SPRICE: finalPrc,
                has_custom_sprice: finalPrc > 0,
                PUSH_PRC_VALUE: finalPrc,
            };
            if (plan.zeroSold && plan.zeroSoldGroi != null) {
                updates.ZERO_SOLD_PRC_GROI = plan.zeroSoldGroi;
            }
            if (plan.dilGroi && plan.dilGroiGroi != null) {
                updates.DIL_GROI_PRC = plan.dilGroiGroi;
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
            if (!table) return $.Deferred().reject().promise();
            const row = table.getRows().find(function(r) {
                return amzPefSku(r.getData()) === sku;
            });
            if (!row) return $.Deferred().reject().promise();
            const extra = {};
            if (opts.recordPushPrc) extra.record_push_prc = 1;
            return amzPersistClearThenSprice(row, plan.effective, true, extra);
        }

        /**
         * Always show/store the live rule S PRC. First pass overwrites stale stored
         * values; later Dil / CVR / 0 Sold / Std / LP changes overwrite again.
         */
        const AMZ_RULE_SPRICE_CLEAR_KEY = 'amzRuleSpriceClearedOnce:v3';
        let amzRuleSpriceSlabsReady = false;
        const amzRuleReadyBits = { cvr: false, rev: false, dilgroi: false };
        let amzRuleSpriceSyncTimer = null;
        let amzRuleSpriceSyncBusy = false;
        let amzRuleSpricePersistQueue = [];
        let amzRuleSpricePersistActive = 0;
        const AMZ_RULE_SPRICE_PERSIST_CONCURRENCY = 8;
        const amzRuleSpricePersistInflight = {};
        const amzRuleSpricePersistLatest = {};

        function amzShouldClearStoredOnce() {
            try { return !localStorage.getItem(AMZ_RULE_SPRICE_CLEAR_KEY); }
            catch (e) { return true; }
        }
        function amzMarkStoredClearedOnce() {
            try { localStorage.setItem(AMZ_RULE_SPRICE_CLEAR_KEY, '1'); }
            catch (e) { /* ignore */ }
        }
        function amzRuleSpriceNeedsOverwrite(stored, live) {
            if (!(live > 0)) return false;
            return Math.abs((Number(stored) || 0) - live) > 0.009;
        }
        function amzRuleSpricePlanForRow(d) {
            const plan = amzPushPrcPlanForQueue(d);
            if (!plan || !(plan.effective > 0)) return null;
            return plan;
        }
        function amzDrainRuleSpricePersist() {
            while (amzRuleSpricePersistActive < AMZ_RULE_SPRICE_PERSIST_CONCURRENCY && amzRuleSpricePersistQueue.length) {
                const job = amzRuleSpricePersistQueue.shift();
                const latest = amzRuleSpricePersistLatest[job.sku];
                if (latest && latest.plan) job.plan = latest.plan;
                amzRuleSpricePersistActive++;
                amzPersistClearThenSprice(job.row, job.plan.effective, true)
                    .done(function(saveRes) {
                        applyAmzPushPrcToSpriceRow(job.row, job.plan, saveRes);
                    })
                    .fail(function() {
                        applyAmzPushPrcToSpriceRow(job.row, job.plan, null);
                    })
                    .always(function() {
                        const saved = job.plan && job.plan.effective;
                        const newest = amzRuleSpricePersistLatest[job.sku];
                        delete amzRuleSpricePersistInflight[job.sku];
                        amzRuleSpricePersistActive--;
                        if (newest && newest.plan && amzRuleSpriceNeedsOverwrite(saved, newest.plan.effective)) {
                            amzEnqueueRuleSpricePersist(newest.row, newest.plan);
                        } else {
                            delete amzRuleSpricePersistLatest[job.sku];
                        }
                        amzDrainRuleSpricePersist();
                    });
            }
        }
        function amzEnqueueRuleSpricePersist(row, plan) {
            const sku = amzPefSku(row.getData());
            if (!sku) return;
            amzRuleSpricePersistLatest[sku] = { row: row, plan: plan, sku: sku };
            if (amzRuleSpricePersistInflight[sku]) return;
            amzRuleSpricePersistInflight[sku] = true;
            amzRuleSpricePersistQueue.push({ row: row, plan: plan, sku: sku });
            amzDrainRuleSpricePersist();
        }
        function amzNoteRuleReady(key) {
            if (key) amzRuleReadyBits[key] = true;
            if (amzRuleReadyBits.cvr && amzRuleReadyBits.rev && amzRuleReadyBits.dilgroi) {
                amzRuleSpriceSlabsReady = true;
                amzScheduleRuleSpriceSync({ delay: 250 });
            }
        }
        function amzApplyRuleSpriceToAllRows(opts) {
            opts = opts || {};
            if (typeof table === 'undefined' || !table || typeof table.getRows !== 'function') return 0;
            if (!amzRuleSpriceSlabsReady && !opts.force) {
                amzScheduleRuleSpriceSync({ delay: 400 });
                return 0;
            }
            const clearOnce = !!opts.clearOnce || amzShouldClearStoredOnce();
            const force = !!opts.force || clearOnce;
            let changed = 0;
            const rows = table.getRows('all') || [];
            rows.forEach(function(row) {
                const d = row.getData();
                if (!amzPefIsChildRow(d) || amzPefInv(d) === 0) return;
                const plan = amzRuleSpricePlanForRow(d);
                if (!plan) return;
                plan.effective = amzFinalSpriceToSave(d, plan.effective);
                if (!(plan.effective > 0)) return;
                const stored = amzLastPersistedSprice(d);
                if (!force && !amzRuleSpriceNeedsOverwrite(stored, plan.effective)) return;
                amzWriteStoredSpriceOnRow(d, plan.effective);
                row.update({
                    SPRICE: plan.effective,
                    has_custom_sprice: true,
                });
                if (plan.zeroSold && plan.zeroSoldGroi != null) {
                    row.update({ ZERO_SOLD_PRC_GROI: plan.zeroSoldGroi });
                }
                if (plan.dilGroi && plan.dilGroiGroi != null) {
                    row.update({ DIL_GROI_PRC: plan.dilGroiGroi });
                }
                amzEnqueueRuleSpricePersist(row, plan);
                changed++;
            });
            if (clearOnce) amzMarkStoredClearedOnce();
            if (changed && table) {
                try { table.redraw(true); } catch (e) { /* ignore */ }
            }
            if (opts.toast) {
                amzPefToast(
                    changed ? 'success' : 'info',
                    changed
                        ? ('S PRC autofilled from rules on ' + changed + ' SKU(s).')
                        : 'S PRC already matches the live rules.'
                );
            }
            return changed;
        }
        function amzScheduleRuleSpriceSync(opts) {
            opts = opts || {};
            clearTimeout(amzRuleSpriceSyncTimer);
            amzRuleSpriceSyncTimer = setTimeout(function() {
                if (amzRuleSpriceSyncBusy) {
                    amzScheduleRuleSpriceSync(opts);
                    return;
                }
                amzRuleSpriceSyncBusy = true;
                try { amzApplyRuleSpriceToAllRows(opts); }
                finally { amzRuleSpriceSyncBusy = false; }
            }, opts.delay != null ? opts.delay : 400);
        }
        function bindAmzRuleSpriceAutofill() {
            if (typeof table === 'undefined' || !table || !table.on) {
                setTimeout(bindAmzRuleSpriceAutofill, 400);
                return;
            }
            if (table._amzRuleSpriceAutofillBound) return;
            table._amzRuleSpriceAutofillBound = true;
            table.on('dataLoaded', function() {
                amzScheduleRuleSpriceSync({ force: amzShouldClearStoredOnce(), delay: 500 });
            });
            try {
                if ((typeof table.getDataCount === 'function' ? table.getDataCount() : 0) > 0) {
                    amzScheduleRuleSpriceSync({ force: amzShouldClearStoredOnce(), delay: 500 });
                }
            } catch (e) { /* wait for dataLoaded */ }
        }
        window.amzScheduleRuleSpriceSync = amzScheduleRuleSpriceSync;
        window.amzApplyRuleSpriceToAllRows = amzApplyRuleSpriceToAllRows;
        window.computeAmzReviewDiscountPct = computeAmzReviewDiscountPct;

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
                + 'Dil in slab → Target GROI from Sprc Dil (including 0 Sold)\n'
                + 'No Dil match → Std × (1 − (CVR Disc + Rev Disc)/100)\n'
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
                const plan = (typeof amzRuleSpricePlanForRow === 'function')
                    ? amzRuleSpricePlanForRow(item.row.getData())
                    : computeAmzPushPrcPlan(item.row.getData());
                $btn.html('<i class="fas fa-spinner fa-spin"></i> ' + i + '/' + ready.length);
                if (!plan || !(plan.effective > 0)) {
                    fail++;
                    next();
                    return;
                }
                plan.effective = amzFinalSpriceToSave(item.row.getData(), plan.effective);
                amzPersistClearThenSprice(item.row, plan.effective, true)
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
        let amzPushPrcPulledKey = '';

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

        /** Push Prc progress — title pill only. Survives refresh via server poll. */
        function setAmzPushPrcProgress(opts) {
            opts = opts || {};
            const $pill = $('#amz-reload-push-progress');
            if (!$pill.length) return;
            const total = Number(opts.total) || 0;
            const done = Number(opts.done) || 0;
            const ok = Number(opts.ok) || 0;
            const fail = Number(opts.fail) || 0;
            const active = !!opts.active;
            const pct = (opts.pct != null)
                ? Math.min(100, Number(opts.pct) || 0)
                : (total > 0 ? Math.min(100, Math.round((done / total) * 100)) : 0);
            const finished = !active && total > 0 && (done >= total || pct >= 100);

            $pill.toggleClass('is-busy', !!active);
            $pill.toggleClass('is-done', !!(finished || (!active && pct >= 100)));
            $pill.toggleClass('is-fail', fail > 0);
            $('#amz-reload-push-progress-pct').text(pct + '%');
            $('#amz-reload-push-progress-bar').css('width', pct + '%');

            let msg = opts.msg || '';
            if (!msg && total) {
                msg = done + '/' + total + ' jobs · ' + ok + ' ok'
                    + (fail ? (' · ' + fail + ' failed') : '');
            }
            $('#amz-reload-push-progress-msg').text(msg || 'Ready');

            if (finished) {
                clearTimeout(setAmzPushPrcProgress._hideTimer);
                setAmzPushPrcProgress._hideTimer = setTimeout(function() {
                    if (!$pill.hasClass('is-done')) return;
                    $pill.removeClass('is-busy is-done is-fail');
                    $('#amz-reload-push-progress-bar').css('width', '0%');
                    $('#amz-reload-push-progress-pct').text('0%');
                    $('#amz-reload-push-progress-msg').text('Ready');
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

        function amzOkSkusFromPushTasks(tasks) {
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
        function applyAmzPulledPrices(results) {
            if (!table || !Array.isArray(results)) return;
            const bySku = {};
            results.forEach(function(r) {
                if (r && r.success && Number(r.price) > 0 && r.sku) {
                    bySku[String(r.sku).toUpperCase()] = Number(r.price);
                }
            });
            if (!Object.keys(bySku).length) return;
            table.getRows().forEach(function(row) {
                const d = row.getData();
                if (!amzPefIsChildRow(d)) return;
                const live = bySku[amzPefSku(d).toUpperCase()];
                if (!(live > 0)) return;
                row.update({ price: live, Price: live });
            });
            try { table.redraw(true); } catch (e) { /* ignore */ }
        }
        function queueAmzPostPushPull(skus) {
            if (!skus || !skus.length) return;
            clearTimeout(queueAmzPostPushPull._t);
            amzPefToast('success', 'Pulling live Amazon Price for ' + skus.length + ' SKU(s)…');
            const expected = {};
            (skus || []).forEach(function(sku) {
                if (!table || !sku) return;
                table.getRows().some(function(row) {
                    const d = row.getData();
                    if (!d || String(amzPefSku(d)).toUpperCase() !== String(sku).toUpperCase()) return false;
                    const want = Number(typeof amzDisplayedSprice === 'function' ? amzDisplayedSprice(d) : d.SPRICE) || 0;
                    if (want > 0) expected[String(sku).toUpperCase()] = want;
                    return true;
                });
            });
            const retryMs = [0, 2000, 4000];
            function runPull(attempt, pending) {
                if (!pending || !pending.length) return;
                $.ajax({
                    url: '/amazon-pull-pushed-prices',
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': amzPefCsrf(), 'Accept': 'application/json' },
                    data: { _token: amzPefCsrf(), skus: pending },
                    timeout: 300000,
                }).done(function(resp) {
                    const results = (resp && resp.results) || [];
                    const fresh = [];
                    const stale = [];
                    results.forEach(function(r) {
                        if (!r || !r.success || !(Number(r.price) > 0) || !r.sku) return;
                        const want = Number(expected[String(r.sku).toUpperCase()]) || 0;
                        if (want > 0 && Math.abs(Number(r.price) - want) > 0.05) {
                            stale.push(r.sku);
                            return;
                        }
                        fresh.push(r);
                    });
                    applyAmzPulledPrices(fresh);
                    if (stale.length && attempt + 1 < retryMs.length) {
                        queueAmzPostPushPull._t = setTimeout(function() {
                            runPull(attempt + 1, stale);
                        }, retryMs[attempt + 1]);
                        return;
                    }
                    const pulled = fresh.length;
                    amzPefToast(
                        pulled ? 'success' : (stale.length ? 'success' : 'error'),
                        pulled
                            ? ('Pulled live Price for ' + pulled + ' SKU(s)')
                            : (stale.length
                                ? ('Pushed ' + skus.length + ' SKU(s) — live Price still catching up')
                                : ((resp && resp.message) || 'Amazon Price pull failed'))
                    );
                }).fail(function(xhr) {
                    if (attempt + 1 < retryMs.length) {
                        queueAmzPostPushPull._t = setTimeout(function() {
                            runPull(attempt + 1, pending);
                        }, retryMs[attempt + 1]);
                        return;
                    }
                    amzPefToast('error', (xhr.responseJSON && xhr.responseJSON.message) || 'Amazon Price pull failed');
                });
            }
            queueAmzPostPushPull._t = setTimeout(function() {
                runPull(0, skus.slice());
            }, 0);
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
                    if (jobStatus === 'completed' && ok > 0 && toastKey !== amzPushPrcPulledKey) {
                        amzPushPrcPulledKey = toastKey;
                        queueAmzPostPushPull(amzOkSkusFromPushTasks(resp.tasks || []));
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
            const plan = amzPushPrcPlanForQueue(d);
            if (!sku || !plan || !(plan.std > 0)) {
                amzPefToast('error', 'Set Std Prc first (optional CVR Discount for Sale)');
                return;
            }
            if (amzListingAlreadyAtPushTarget(d, plan)) {
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
                const plan = amzPushPrcPlanForQueue(d);
                if (!plan || !(plan.std > 0)) return;
                if (amzListingAlreadyAtPushTarget(d, plan)) {
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
                + '\n\nEach SKU uses its own CVR Disc, Rev Disc, and Sprc Dil (Dil-matching slab).'
                + '\nYour=Std; Dil in slab → Sale=GROI (including 0 Sold); else Sale=Std−(CVR Disc+Rev Disc);'
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
            return amzPageReloadPushEnabled !== false;
        }
        window.amzPageReloadPushAllowed = amzPageReloadPushAllowed;
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
                const plan = amzPushPrcPlanForQueue(d);
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
            if (!amzPageReloadPushAllowed()) return;
            if (window._amzReloadPushQueued) return;
            if (!amzRuleSpriceSlabsReady) {
                setTimeout(amzTryQueuePushOnReload, 400);
                return;
            }
            if (amzRuleSpriceSyncBusy || amzRuleSpricePersistActive > 0 || amzRuleSpricePersistQueue.length) {
                setTimeout(amzTryQueuePushOnReload, 600);
                return;
            }
            const items = collectAmzReloadPushItems();
            window._amzReloadPushQueued = true;
            if (!items.length) return;
            queueAmzPushPrcItems(items, { silent: true });
        }
        function bindAmzReloadPushOnTable() {
            if (typeof table === 'undefined' || !table || !table.on) {
                setTimeout(bindAmzReloadPushOnTable, 400);
                return;
            }
            if (table._amzReloadPushBound) return;
            table._amzReloadPushBound = true;
            table.on('dataLoaded', function() {
                window._amzReloadPushQueued = false;
                amzTryQueuePushOnReload();
            });
            try {
                if ((typeof table.getDataCount === 'function' ? table.getDataCount() : 0) > 0) {
                    amzTryQueuePushOnReload();
                }
            } catch (e) { /* wait for dataLoaded */ }
        }

        function initAmazonPefPromoUi() {
            syncAmzReloadPushSwitchUi();
            $('#amz-reload-push-switch').off('change.amzReload').on('change.amzReload', function() {
                const on = !!this.checked;
                const prev = amzPageReloadPushAllowed();
                saveAmzPageReloadPush(on)
                    .done(function() {
                        amzPefToast(
                            'success',
                            on
                                ? 'Push on reload on — SKUs where S PRC ≠ Price will queue here and on refresh.'
                                : 'Push on reload off — only manual Push Prc and the daily cron will push.'
                        );
                        if (!on) return;
                        window._amzReloadPushQueued = false;
                        amzTryQueuePushOnReload();
                    })
                    .fail(function(xhr) {
                        amzPageReloadPushEnabled = prev;
                        syncAmzReloadPushSwitchUi();
                        amzPefToast('error', (xhr.responseJSON && xhr.responseJSON.message) || 'Could not save reload-push switch');
                    });
            });

            // Prefetch CVR Disc / Rev Disc / Sprc Dil rules, then autofill S PRC from live rules
            if (typeof loadAmzCvrDiscRules === 'function') {
                Promise.resolve(loadAmzCvrDiscRules()).then(function() {
                    if (table) {
                        try { table.getColumn('cvr_discount') && table.redraw(true); } catch (e) { /* ignore */ }
                    }
                    amzNoteRuleReady('cvr');
                }).catch(function() { amzNoteRuleReady('cvr'); });
            } else {
                amzNoteRuleReady('cvr');
            }
            if (typeof loadAmzReviewDiscRules === 'function') {
                Promise.resolve(loadAmzReviewDiscRules()).then(function() {
                    if (table) {
                        try { table.getColumn('review_discount') && table.redraw(true); } catch (e) { /* ignore */ }
                    }
                    amzNoteRuleReady('rev');
                }).catch(function() { amzNoteRuleReady('rev'); });
            } else {
                amzNoteRuleReady('rev');
            }
            if (typeof loadAmzDilGroiRules === 'function') {
                Promise.resolve(loadAmzDilGroiRules()).then(function() { amzNoteRuleReady('dilgroi'); }).catch(function() { amzNoteRuleReady('dilgroi'); });
            } else {
                amzNoteRuleReady('dilgroi');
            }
            bindAmzRuleSpriceAutofill();
            bindAmzReloadPushOnTable();

            // Resume background Push Prc progress after refresh
            $('#amz-reload-push-progress-cancel').off('click.amzpef').on('click.amzpef', function(e) {
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

            // CVR Disc badge → amazon_cvr_vs_disc rules (column CVR Disc.)
            $('#amz-cvr-disc-rules-btn').off('click.amzpef').on('click.amzpef', function(e) {
                e.preventDefault();
                const modalEl = document.getElementById('amzCvrDiscModal');
                if (!modalEl) return;
                renderAmzCvrDiscModalTable();
                loadAmzCvrDiscRules();
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            });
            $('#amzCvrDiscModal').off('shown.bs.modal.amzcd').on('shown.bs.modal.amzcd', function() {
                renderAmzCvrDiscPie();
            });
            $('#amzCvrDiscModal').off('hidden.bs.modal.amzcd').on('hidden.bs.modal.amzcd', function() {
                $('#amz-cd-hist-wrap').removeClass('is-open');
                if (amzCdPieChart) { amzCdPieChart.destroy(); amzCdPieChart = null; }
                if (amzCdHistChart) { amzCdHistChart.destroy(); amzCdHistChart = null; }
            });
            $(document).off('click.amzcd', '.amz-cd-hist-dot').on('click.amzcd', '.amz-cd-hist-dot', function() {
                amzCdOpenHist($(this).data('band') || 'eq-0');
            });
            $('#amz-cd-hist-close').off('click.amzcd').on('click.amzcd', function() {
                $('#amz-cd-hist-wrap').removeClass('is-open');
            });
            $('#amz-cvr-disc-apply-btn').off('click.amzpef').on('click.amzpef', saveAndApplyAmzCvrDisc);

            $('#amz-review-disc-rules-btn').off('click.amzpef').on('click.amzpef', function(e) {
                e.preventDefault();
                const modalEl = document.getElementById('amzReviewDiscModal');
                if (!modalEl) return;
                renderAmzReviewDiscModalTable();
                loadAmzReviewDiscRules();
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            });
            $('#amz-review-disc-add-btn').off('click.amzpef').on('click.amzpef', function(e) {
                e.preventDefault();
                amzReviewDiscAddRange();
            });
            $(document).off('click.amzrd', '.amz-review-disc-row-del').on('click.amzrd', '.amz-review-disc-row-del', function() {
                const idx = parseInt($(this).attr('data-idx'), 10);
                readAmzReviewDiscRulesFromModal();
                if (isFinite(idx) && idx >= 0 && idx < amzReviewDiscRules.length) {
                    amzReviewDiscRules.splice(idx, 1);
                }
                renderAmzReviewDiscModalTable();
            });
            $('#amz-review-disc-apply-btn').off('click.amzpef').on('click.amzpef', saveAndApplyAmzReviewDisc);

            $('#amz-dil-groi-btn').off('click.amzpef').on('click.amzpef', function(e) {
                e.preventDefault();
                openAmzDilGroiModal();
            });
            $('#amz-dil-groi-add-btn').off('click.amzpef').on('click.amzpef', function(e) {
                e.preventDefault();
                amzDilGroiAddSlab();
            });
            $(document).off('click.amzdg', '.amz-dil-groi-row-del').on('click.amzdg', '.amz-dil-groi-row-del', function() {
                const idx = parseInt($(this).attr('data-idx'), 10);
                amzDilGroiDeleteSlab(idx);
            });
            $('#amz-dil-groi-save-btn').off('click.amzpef').on('click.amzpef', async function(e) {
                e.preventDefault();
                const $btn = $(this);
                const html = $btn.html();
                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving…');
                try {
                    await saveAmzDilGroiRules();
                    amzPefToast('success', 'Sprc Dil saved and applied');
                } catch (xhr) {
                    amzPefToast('error', 'Save failed: ' + ((xhr && xhr.responseJSON && xhr.responseJSON.message) || 'error'));
                } finally {
                    $btn.prop('disabled', false).html(html);
                }
            });
            $(document).off('input.amzDilGroi change.amzDilGroi', '#amz-dil-groi-tbody .amz-dil-groi-input')
                .on('input.amzDilGroi change.amzDilGroi', '#amz-dil-groi-tbody .amz-dil-groi-input', function() {
                    amzOnDilGroiNumberChanged(this);
                });
            $('#amzDilGroiModal').off('shown.bs.modal.amzdg').on('shown.bs.modal.amzdg', function() {
                setTimeout(function() { renderAmzDilGroiPies(); }, 50);
            });
            $('#amzDilGroiModal').off('hidden.bs.modal.amzdg').on('hidden.bs.modal.amzdg', function() {
                destroyAmzDilGroiPies();
            });
            $(document).off('click.amzdghist', '.amz-dg-hist-dot').on('click.amzdghist', '.amz-dg-hist-dot', function() {
                const chart = String($(this).data('chart') || 'dil');
                const band = String($(this).data('band') || '');
                if (!band) return;
                amzDgOpenHist(chart, band);
            });
            $('#amz-dg-hist-close').off('click.amzdghist').on('click.amzdghist', function() {
                $('#amz-dg-hist-wrap').removeClass('is-open');
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

            // Push Prc — Std + CVR Disc + Rev Disc + 0 Sold → Amazon Listings
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

            // sprice ? — force overwrite stored S PRC from live rules (no Amazon push)
            $('#amz-sprice-recalc-btn').off('click.amzpef').on('click.amzpef', function(e) {
                e.preventDefault();
                amzApplyRuleSpriceToAllRows({ toast: true });
            });
        }
@endif
