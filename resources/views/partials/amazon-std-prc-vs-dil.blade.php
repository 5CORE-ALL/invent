{{-- Std prc vs dil: promotional % off standard price. Parts: css, buttons, modals, script. --}}
@php $amazonStdPrcPart = $amazonStdPrcPart ?? 'all'; @endphp

@if($amazonStdPrcPart === 'css' || $amazonStdPrcPart === 'all')
        .amz-age-discount-badge,
        .amz-dil-discount-badge,
        .amz-sum-discount-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 12px;
            line-height: 1.2;
        }
        .amz-age-discount-badge { color: #d97706; }
        .amz-dil-discount-badge { color: #6f42c1; }
        .amz-sum-discount-badge { color: #0f172a; }
        .amz-age-discount-badge.is-zero,
        .amz-dil-discount-badge.is-zero,
        .amz-sum-discount-badge.is-zero { color: #adb5bd; font-weight: 600; }
        .amz-age-discount-badge.is-neg,
        .amz-dil-discount-badge.is-neg,
        .amz-sum-discount-badge.is-neg,
        .amz-cvr-discount-badge.is-neg,
        .amz-review-discount-badge.is-neg { color: #dc3545; }
        #amzStdPrcModal .modal-dialog {
            width: calc(100vw - 1.25rem);
            max-width: calc(100vw - 1.25rem);
            height: calc(100vh - 1.25rem);
            max-height: calc(100vh - 1.25rem);
            margin: 0.625rem auto;
        }
        #amzStdPrcModal .modal-content {
            height: 100%;
            max-height: 100%;
            border: 0;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.18);
        }
        #amzStdPrcModal .modal-header {
            background: #fff;
            border-bottom: 1px solid #e8eef5;
            padding: 14px 18px;
        }
        #amzStdPrcModal .modal-title { font-weight: 700; color: #0f172a; letter-spacing: -0.01em; }
        #amzStdPrcModal .amz-sp-sub { color: #64748b; font-size: 12px; font-weight: 500; margin-top: 2px; }
        #amzStdPrcModal .modal-body {
            background: #f4f7fb;
            overflow-x: auto;
            padding: 14px 16px 16px;
        }
        #amzStdPrcModal .modal-footer {
            background: #fff;
            border-top: 1px solid #e8eef5;
            padding: 10px 16px;
        }
        #amzStdPrcModal .amz-sp-cols {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 12px;
            align-items: stretch;
            width: 100%;
            min-width: 0;
        }
        #amzStdPrcModal .amz-sp-col {
            min-width: 0;
            display: flex;
            flex-direction: column;
            border: 1px solid #e6edf5;
            border-radius: 14px;
            padding: 12px;
            background: #fff;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        }
        #amzStdPrcModal .amz-sp-pie {
            display: flex;
            flex-direction: column;
            align-items: stretch;
            margin-bottom: 10px;
        }
        #amzStdPrcModal .amz-sp-pie-canvas {
            position: relative;
            width: 100%;
            height: 150px;
            flex: 0 0 150px;
        }
        #amzStdPrcModal .amz-sp-pie-canvas canvas {
            display: block;
            width: 100% !important;
            height: 150px !important;
        }
        #amzStdPrcModal .amz-sp-pie-legend {
            width: 100%;
            font-size: 12px;
            margin-top: 8px;
            color: #334155;
        }
        #amzStdPrcModal .amz-sp-pie-title {
            font-weight: 700;
            font-size: 13px;
            margin-bottom: 8px;
            color: #0f172a;
            align-self: flex-start;
            letter-spacing: -0.01em;
        }
        #amzStdPrcModal .amz-sp-leg-row {
            display: grid;
            grid-template-columns: 8px minmax(0, 1fr) auto auto 8px;
            gap: 6px;
            align-items: center;
            padding: 2px 0;
        }
        #amzStdPrcModal .amz-sp-hist-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            border: none;
            padding: 0;
            cursor: pointer;
            justify-self: end;
            box-shadow: 0 0 0 1px rgba(15, 23, 42, 0.12);
        }
        #amzStdPrcModal .amz-sp-hist-dot:hover { transform: scale(1.35); }
        #amzStdPrcModal .amz-sp-hist-wrap {
            display: none;
            margin: 0 0 12px;
            padding: 8px 10px 6px;
            border: 1px solid #e6edf5;
            border-radius: 12px;
            background: #fff;
        }
        #amzStdPrcModal .amz-sp-hist-wrap.is-open { display: block; }
        #amzStdPrcModal .amz-sp-hist-canvas-wrap { height: 180px; }
        #amzStdPrcModal .amz-sp-leg-row strong { font-variant-numeric: tabular-nums; }
        #amzStdPrcModal .amz-sp-leg-pct { color: #94a3b8; font-variant-numeric: tabular-nums; min-width: 2.2rem; text-align: right; }
        #amzStdPrcModal .amz-sp-swatch { width: 8px; height: 8px; border-radius: 50%; }
        #amzStdPrcModal .amz-sp-col .table {
            font-size: 12px;
            margin-bottom: 0;
            border-color: #e8eef5;
        }
        #amzStdPrcModal .amz-sp-col .table thead th {
            background: #f8fafc;
            color: #64748b;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            border-color: #e8eef5;
            white-space: nowrap;
        }
        #amzStdPrcModal .amz-sp-col .table th,
        #amzStdPrcModal .amz-sp-col .table td {
            padding: 5px 6px;
            border-color: #eef2f7;
            vertical-align: middle;
        }
        #amzStdPrcModal .amz-sp-col .table tbody tr:last-child td { border-bottom: 0; }
        #amzStdPrcModal #amz-sp-all-tbody tr.fw-semibold { background: #f8fafc; }
        #amzStdPrcModal .amz-sp-add {
            margin-top: auto;
            width: 100%;
            border-radius: 8px;
            border-style: dashed;
            font-weight: 600;
        }
        #amzStdPrcModal .amz-sp-col .table-responsive { margin-bottom: 8px; }
        #amzStdPrcModal .amz-sp-margins {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 12px;
        }
        #amzStdPrcModal .amz-sp-margin {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            border: 1px solid #e6edf5;
            border-radius: 14px;
            padding: 12px 16px;
            background: #fff;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        }
        #amzStdPrcModal .amz-sp-margin-kicker {
            color: #64748b;
            font-size: 12px;
            font-weight: 600;
        }
        #amzStdPrcModal .amz-sp-margin strong {
            display: block;
            font-size: 26px;
            line-height: 1.15;
            letter-spacing: -0.03em;
            margin: 2px 0;
        }
        #amzStdPrcModal .amz-sp-metric-grid {
            display: grid;
            grid-template-columns: 3.4rem auto auto;
            gap: 4px 12px;
            width: max-content;
            font-size: 12px;
            align-items: center;
        }
        #amzStdPrcModal .amz-sp-metric { display: contents; }
        #amzStdPrcModal .amz-sp-metric .amz-sp-metric-name { color: #64748b; font-weight: 700; }
        #amzStdPrcModal .amz-sp-metric .amz-sp-metric-money { font-weight: 700; text-align: right; font-variant-numeric: tabular-nums; }
        #amzStdPrcModal .amz-sp-metric .amz-sp-metric-pct { font-weight: 700; text-align: right; }
        #amzStdPrcModal .amz-sp-section { font-weight: 700; font-size: 12px; margin: 12px 0 6px; color: #334155; }
        #amzStdPrcModal .amz-sp-input {
            width: 52px;
            min-width: 52px;
            max-width: 52px;
            margin: 0 auto;
            display: inline-block;
            box-sizing: border-box;
            text-align: center;
            font-weight: 600;
            padding: 0 4px;
            font-size: 13px;
            line-height: 24px;
            height: 28px;
            color: #0f172a;
            border-radius: 7px;
            border-color: #dbe3ee;
            background: #fff;
            font-variant-numeric: tabular-nums;
            -moz-appearance: textfield;
            appearance: textfield;
        }
        #amzStdPrcModal .amz-sp-input::-webkit-outer-spin-button,
        #amzStdPrcModal .amz-sp-input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        #amzStdPrcModal .amz-sp-input:focus {
            background: #fff;
            border-color: #93c5fd;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        }
        #amzStdPrcModal td.text-end .amz-sp-input { margin-left: auto; margin-right: 0; }
        #amzStdPrcModal .amz-sp-cvr-thresh {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            color: #64748b;
            font-weight: 700;
        }
        #amzStdPrcModal .amz-sp-cvr-thresh .amz-sp-input { width: 52px; margin: 0; }
        #amzStdPrcModal .amz-sp-count { font-weight: 700; text-align: center; font-variant-numeric: tabular-nums; color: #0f172a; }
        #amzStdPrcModal .amz-sp-when { display: inline-flex; align-items: center; gap: 6px; font-weight: 600; color: #334155; white-space: nowrap; }
        #amzStdPrcModal .amz-sp-when::before {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #94a3b8;
            flex: 0 0 8px;
        }
        #amzStdPrcModal .amz-sp-when-down2::before { background: #9f1239; }
        #amzStdPrcModal .amz-sp-when-down::before { background: #dc3545; }
        #amzStdPrcModal .amz-sp-when-flat::before { background: #94a3b8; }
        #amzStdPrcModal .amz-sp-when-up::before { background: #198754; }
        #amzStdPrcModal .amz-sp-when-up2::before { background: #14532d; }
        #amzStdPrcModal .amz-sp-max {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 8px;
            padding: 6px 8px;
            border-radius: 8px;
            background: #f8fafc;
            border: 1px solid #eef2f7;
        }
        #amzStdPrcModal .amz-sp-max label { font-size: 11px; font-weight: 700; color: #64748b; letter-spacing: 0.03em; text-transform: uppercase; margin: 0; }
        #amzStdPrcModal .amz-sp-norev {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: -2px 0 8px;
            padding: 6px 8px;
            border-radius: 8px;
            background: #f8fafc;
            border: 1px solid #eef2f7;
            font-size: 12px;
            font-weight: 600;
            color: #334155;
        }
        #amzStdPrcModal .amz-sp-norev input { margin: 0; }
        #amzStdPrcModal .amz-sp-del {
            width: 22px;
            height: 22px;
            padding: 0;
            line-height: 1;
            border-radius: 6px;
            color: #94a3b8;
        }
        #amzStdPrcModal .amz-sp-del:hover { color: #dc3545; background: #fef2f2; }
        #amzStdPrcModal #amz-sp-apply { border-radius: 8px; font-weight: 600; padding: 6px 14px; }
@endif

@if($amazonStdPrcPart === 'buttons' || $amazonStdPrcPart === 'all')
                    <button type="button" class="btn btn-sm" id="amz-std-prc-btn"
                        title="Std Prc minus Age, Dil, CVR, and Review promotional discounts. S PRC = Std − Sum disc.">
                        <i class="fas fa-tags"></i> Std prc vs dil
                    </button>
@endif

@if($amazonStdPrcPart === 'modals' || $amazonStdPrcPart === 'all')
    <div class="modal fade" id="amzStdPrcModal" tabindex="-1" aria-labelledby="amzStdPrcModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title fs-6 mb-0" id="amzStdPrcModalLabel">
                            <i class="fas fa-tags me-1"></i> Std prc vs dil
                        </h5>
                        <div class="amz-sp-sub">S PRC = Std Prc − Age − Dil − B Disc − CVR − Reviews. Std Prc under $15 uses half of each rule discount (0.5×).</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="amz-sp-margins">
                        <div class="amz-sp-margin">
                            <div class="amz-sp-margin-head">
                                <div class="amz-sp-margin-kicker">Projected margin · last L30 sales</div>
                                <strong id="amz-sp-margin-l30">—</strong>
                                <div class="small text-muted" id="amz-sp-margin-l30-sub"></div>
                            </div>
                            <div class="amz-sp-metric-grid" id="amz-sp-margin-l30-metrics"></div>
                        </div>
                        <div class="amz-sp-margin">
                            <div class="amz-sp-margin-head">
                                <div class="amz-sp-margin-kicker">Projected margin · total INV</div>
                                <strong id="amz-sp-margin-inv">—</strong>
                                <div class="small text-muted" id="amz-sp-margin-inv-sub"></div>
                            </div>
                            <div class="amz-sp-metric-grid" id="amz-sp-margin-inv-metrics"></div>
                        </div>
                    </div>
                    <div class="amz-sp-hist-wrap" id="amz-sp-hist-wrap">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small fw-semibold" id="amz-sp-hist-title">Daily history</span>
                            <button type="button" class="btn-close" id="amz-sp-hist-close" aria-label="Close history" style="font-size:10px;"></button>
                        </div>
                        <div class="amz-sp-hist-canvas-wrap">
                            <canvas id="amz-sp-hist"></canvas>
                        </div>
                    </div>

                    <div class="amz-sp-cols">
                        <div class="amz-sp-col">
                            <div class="amz-sp-pie">
                                <div class="amz-sp-pie-title">Dil</div>
                                <div class="amz-sp-pie-canvas"><canvas id="amz-sp-pie-dil"></canvas></div>
                                <div class="amz-sp-pie-legend" id="amz-sp-leg-dil"></div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle mb-0" id="amz-sp-dil-table">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-center">From</th>
                                            <th class="text-center">To</th>
                                            <th class="text-center">Count</th>
                                            <th class="text-end">Disc %</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody id="amz-sp-dil-tbody"></tbody>
                                </table>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary amz-sp-add" id="amz-sp-dil-add">Add slab</button>
                        </div>

                        <div class="amz-sp-col">
                            <div class="amz-sp-pie">
                                <div class="amz-sp-pie-title">Reviews</div>
                                <div class="amz-sp-pie-canvas"><canvas id="amz-sp-pie-rev"></canvas></div>
                                <div class="amz-sp-pie-legend" id="amz-sp-leg-rev"></div>
                            </div>
                            <div class="amz-sp-max">
                                <label for="amz-sp-review-max">Max reviews</label>
                                <input type="number" id="amz-sp-review-max" class="form-control form-control-sm amz-sp-input" min="1" step="1" value="4" title="No review discount when reviews are this value or higher">
                            </div>
                            <label class="amz-sp-norev" for="amz-sp-no-reviews-no-disc">
                                <input type="checkbox" id="amz-sp-no-reviews-no-disc" checked>
                                No reviews, no discount
                            </label>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-center">From</th>
                                            <th class="text-center">To</th>
                                            <th class="text-center">Count</th>
                                            <th class="text-end">Disc %</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody id="amz-sp-rev-tbody"></tbody>
                                </table>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary amz-sp-add" id="amz-sp-rev-add">Add range</button>
                        </div>

                        <div class="amz-sp-col">
                            <div class="amz-sp-pie">
                                <div class="amz-sp-pie-title" title="Std Prc ranges. Disc % updates the B Disc column.">B Disc</div>
                                <div class="amz-sp-pie-canvas"><canvas id="amz-sp-pie-buss"></canvas></div>
                                <div class="amz-sp-pie-legend" id="amz-sp-leg-buss"></div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-center">From</th>
                                            <th class="text-center">To</th>
                                            <th class="text-center">Count</th>
                                            <th class="text-end">Disc %</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody id="amz-sp-buss-tbody"></tbody>
                                </table>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary amz-sp-add" id="amz-sp-buss-add">Add range</button>
                        </div>

                        <div class="amz-sp-col">
                            <div class="amz-sp-pie">
                                <div class="amz-sp-pie-title">CVR up / down</div>
                                <div class="amz-sp-pie-canvas"><canvas id="amz-sp-pie-cvr"></canvas></div>
                                <div class="amz-sp-pie-legend" id="amz-sp-leg-cvr"></div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle mb-0" id="amz-sp-cvr-table">
                                    <thead class="table-light">
                                        <tr>
                                            <th>When</th>
                                            <th class="text-center">CVR%</th>
                                            <th class="text-end">Disc %</th>
                                            <th class="text-center">Count</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><span class="amz-sp-when amz-sp-when-down2">Down</span></td>
                                            <td class="text-center"><span class="amz-sp-cvr-thresh">&lt;<input type="number" min="0" step="0.1" class="form-control form-control-sm amz-sp-input amz-sp-cvr-down2-lt" value="4"></span></td>
                                            <td class="text-end"><input type="number" step="any" class="form-control form-control-sm amz-sp-input amz-sp-cvr-down2-disc" value="0"></td>
                                            <td class="amz-sp-count" id="amz-sp-cvr-down2-count">0</td>
                                        </tr>
                                        <tr>
                                            <td><span class="amz-sp-when amz-sp-when-down">Down</span></td>
                                            <td class="text-center"><span class="amz-sp-cvr-thresh">&lt;<input type="number" min="0" step="0.1" class="form-control form-control-sm amz-sp-input amz-sp-cvr-down-lt" value="7"></span></td>
                                            <td class="text-end"><input type="number" step="any" class="form-control form-control-sm amz-sp-input amz-sp-cvr-down-disc" value="0"></td>
                                            <td class="amz-sp-count" id="amz-sp-cvr-down-count">0</td>
                                        </tr>
                                        <tr>
                                            <td><span class="amz-sp-when amz-sp-when-flat">Flat</span></td>
                                            <td class="text-center text-muted">mid</td>
                                            <td class="text-end"><input type="number" step="any" class="form-control form-control-sm amz-sp-input amz-sp-cvr-flat-disc" value="0"></td>
                                            <td class="amz-sp-count" id="amz-sp-cvr-flat-count">0</td>
                                        </tr>
                                        <tr>
                                            <td><span class="amz-sp-when amz-sp-when-up">Up</span></td>
                                            <td class="text-center"><span class="amz-sp-cvr-thresh">&gt;<input type="number" min="0" step="0.1" class="form-control form-control-sm amz-sp-input amz-sp-cvr-up-gt" value="10"></span></td>
                                            <td class="text-end"><input type="number" step="any" class="form-control form-control-sm amz-sp-input amz-sp-cvr-up-disc" value="0"></td>
                                            <td class="amz-sp-count" id="amz-sp-cvr-up-count">0</td>
                                        </tr>
                                        <tr>
                                            <td><span class="amz-sp-when amz-sp-when-up2">Up</span></td>
                                            <td class="text-center"><span class="amz-sp-cvr-thresh">&gt;<input type="number" min="0" step="0.1" class="form-control form-control-sm amz-sp-input amz-sp-cvr-up2-gt" value="15"></span></td>
                                            <td class="text-end"><input type="number" step="any" class="form-control form-control-sm amz-sp-input amz-sp-cvr-up2-disc" value="0"></td>
                                            <td class="amz-sp-count" id="amz-sp-cvr-up2-count">0</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="amz-sp-col">
                            <div class="amz-sp-pie">
                                <div class="amz-sp-pie-title">Age Days</div>
                                <div class="amz-sp-pie-canvas"><canvas id="amz-sp-pie-age"></canvas></div>
                                <div class="amz-sp-pie-legend" id="amz-sp-leg-age"></div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-center">From</th>
                                            <th class="text-center">To</th>
                                            <th class="text-center">Count</th>
                                            <th class="text-end">Disc %</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody id="amz-sp-age-tbody"></tbody>
                                </table>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary amz-sp-add" id="amz-sp-age-add">Add range</button>
                        </div>

                        <div class="amz-sp-col">
                            <div class="amz-sp-pie">
                                <div class="amz-sp-pie-title">All discounts</div>
                                <div class="amz-sp-pie-canvas"><canvas id="amz-sp-pie-all"></canvas></div>
                                <div class="amz-sp-pie-legend" id="amz-sp-leg-all"></div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Discount</th>
                                            <th class="text-center">SKUs</th>
                                            <th class="text-end">Disc %</th>
                                            <th class="text-end">$ off</th>
                                        </tr>
                                    </thead>
                                    <tbody id="amz-sp-all-tbody"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="small text-muted me-auto" id="amz-sp-status"></div>
                    <button type="button" class="btn btn-sm btn-primary" id="amz-sp-apply"
                        title="Save these promotional discounts and write S PRC as Std Prc minus the sum.">
                        <i class="fas fa-save me-1"></i> Save and Apply
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif

@if($amazonStdPrcPart === 'script' || $amazonStdPrcPart === 'all')
        const AMZ_STD_DIL_DEFAULTS = [
            { min: 0, max: 0, disc: 0 },
            { min: 0.1, max: 10, disc: 0 },
            { min: 10, max: 25, disc: 0 },
            { min: 25, max: 50, disc: 0 },
            { min: 50, max: 100, disc: 0 },
            { min: 100, max: 9999, disc: 0 },
        ];
        const AMZ_STD_AGE_DEFAULTS = [
            { min: 0, max: 30, disc: 0 },
            { min: 31, max: 60, disc: 0 },
            { min: 61, max: 90, disc: 0 },
            { min: 91, max: 180, disc: 0 },
            { min: 181, max: 365, disc: 0 },
            { min: 366, max: 9999, disc: 0 },
        ];
        const AMZ_STD_CVR_DEFAULT = { down2_lt: 4, down2_disc: 0, down_lt: 7, down_disc: 0, up_gt: 10, up_disc: 0, up2_gt: 15, up2_disc: 0, flat_disc: 0 };
        const AMZ_STD_BUSS_DEFAULTS = [
            { min: 0, max: 15, disc: 0 },
            { min: 15, max: 50, disc: 0 },
            { min: 50, max: 9999, disc: 0 },
        ];
        const AMZ_STD_PIE_COLORS = ['#6f42c1', '#3b82f6', '#14b8a6', '#22c55e', '#84cc16', '#eab308', '#f59e0b', '#ea580c', '#dc3545', '#e83e8c', '#0ea5e9', '#64748b'];
        let amzStdDilRules = AMZ_STD_DIL_DEFAULTS.map(function(r) { return Object.assign({}, r); });
        let amzStdAgeRules = AMZ_STD_AGE_DEFAULTS.map(function(r) { return Object.assign({}, r); });
        let amzStdBussRules = AMZ_STD_BUSS_DEFAULTS.map(function(r) { return Object.assign({}, r); });
        let amzStdCvrCfg = Object.assign({}, AMZ_STD_CVR_DEFAULT);
        const amzStdPieCharts = {};
        let amzStdPieGen = 0;
        let amzStdApplied = false;
        let amzStdHistChart = null;
        let amzStdHistLive = {};

        function amzStdDisc(raw) {
            const n = Number(raw);
            if (!isFinite(n)) return 0;
            return Math.min(100, Math.max(-100, Math.round(n * 100) / 100));
        }
        function amzStdLowFactor(std) {
            const n = Number(std);
            return (isFinite(n) && n > 0 && n < 15) ? 0.5 : 1;
        }
        function amzStdScaleDisc(std, disc) {
            if (disc == null) return disc;
            const factor = amzStdLowFactor(std);
            if (factor === 1) return disc;
            return amzStdDisc(Number(disc) * factor);
        }
        function fmtAmzStdDiscBadge(pct, kind) {
            const n = Number(pct);
            const cls = kind === 'age' ? 'amz-age-discount-badge'
                : (kind === 'dil' ? 'amz-dil-discount-badge'
                    : (kind === 'cvr' ? 'amz-cvr-discount-badge'
                        : (kind === 'review' ? 'amz-review-discount-badge' : 'amz-sum-discount-badge')));
            if (!isFinite(n) || n === 0) {
                return '<span class="' + cls + ' is-zero">—</span>';
            }
            return '<span class="' + cls + (n < 0 ? ' is-neg' : '') + '">' + n + '</span>';
        }
        function amzStdRangeDisc(value, rules) {
            const n = Number(value);
            if (!isFinite(n) || n < 0 || !rules || !rules.length) return 0;
            const last = rules.length - 1;
            for (let i = 0; i < rules.length; i++) {
                const rule = rules[i];
                if (!rule) continue;
                let min = Number(rule.min);
                let max = Number(rule.max);
                if (!isFinite(min) || !isFinite(max)) continue;
                if (max < min) { const swap = min; min = max; max = swap; }
                const exact = Math.abs(max - min) < 0.00001;
                const hit = exact
                    ? Math.abs(n - min) < 0.00001
                    : (n >= min && (i === last ? n <= max : n < max));
                if (!hit) continue;
                return amzStdDisc(rule.disc);
            }
            return 0;
        }
        function computeAmzAgeDiscountPct(d) {
            if (typeof amzPefIsChildRow === 'function' && !amzPefIsChildRow(d)) return null;
            if (typeof amzPefInv === 'function' && amzPefInv(d) === 0) return 0;
            if (!d || d.age_days == null || d.age_days === '') return 0;
            return amzStdScaleDisc(d.STANDARD_PRICE, amzStdRangeDisc(d.age_days, amzStdAgeRules));
        }
        function computeAmzDilDiscountPct(d) {
            if (typeof amzPefIsChildRow === 'function' && !amzPefIsChildRow(d)) return null;
            if (typeof amzPefInv === 'function' && amzPefInv(d) === 0) return 0;
            const dil = (typeof amzPefDil === 'function') ? amzPefDil(d) : 0;
            return amzStdScaleDisc(d && d.STANDARD_PRICE, amzStdRangeDisc(dil, amzStdDilRules));
        }
        function computeAmzBussDiscountPct(d) {
            if (typeof amzPefIsChildRow === 'function' && !amzPefIsChildRow(d)) return null;
            if (typeof amzPefInv === 'function' && amzPefInv(d) === 0) return 0;
            const std = Number(d && d.STANDARD_PRICE) || 0;
            return amzStdScaleDisc(std, amzStdRangeDisc(std, amzStdBussRules));
        }
        window.computeAmzBussDiscountPct = computeAmzBussDiscountPct;
        window.analyticsBussDiscountPct = computeAmzBussDiscountPct;
        function amzStdCvrSlabs(cfg) {
            cfg = cfg || AMZ_STD_CVR_DEFAULT;
            const down = [
                { key: 'down2', lt: Number(cfg.down2_lt), disc: amzStdDisc(cfg.down2_disc) },
                { key: 'down', lt: Number(cfg.down_lt), disc: amzStdDisc(cfg.down_disc) },
            ].filter(function(s) { return isFinite(s.lt) && s.lt > 0; });
            down.sort(function(a, b) { return a.lt - b.lt; });
            const up = [
                { key: 'up', gt: Number(cfg.up_gt), disc: amzStdDisc(cfg.up_disc) },
                { key: 'up2', gt: Number(cfg.up2_gt), disc: amzStdDisc(cfg.up2_disc) },
            ].filter(function(s) { return isFinite(s.gt) && s.gt >= 0; });
            up.sort(function(a, b) { return b.gt - a.gt; });
            return { down: down, up: up };
        }
        function amzStdCvrMatch(d, cfg) {
            const cvr = (typeof amzPefCvrL30Live === 'function') ? amzPefCvrL30Live(d) : 0;
            const trend = (typeof amzPefCvrTrend === 'function') ? amzPefCvrTrend(d) : 'flat';
            const slabs = amzStdCvrSlabs(cfg);
            if (trend === 'down') {
                for (let i = 0; i < slabs.down.length; i++) {
                    if (cvr < slabs.down[i].lt) return slabs.down[i];
                }
            }
            if (trend === 'up') {
                for (let i = 0; i < slabs.up.length; i++) {
                    if (cvr > slabs.up[i].gt) return slabs.up[i];
                }
            }
            return null;
        }
        function amzStdCvrTrendDisc(d) {
            if (!d || (typeof amzPefInv === 'function' && amzPefInv(d) === 0)) return 0;
            const cfg = amzStdCvrCfg || AMZ_STD_CVR_DEFAULT;
            const hit = amzStdCvrMatch(d, cfg);
            return amzStdScaleDisc(d && d.STANDARD_PRICE, amzStdDisc(hit ? hit.disc : cfg.flat_disc));
        }
        function amzStdCvrBand(d, cfg) {
            const hit = amzStdCvrMatch(d, cfg);
            return hit ? hit.key : 'flat';
        }
        function computeAmzSumDiscountPct(d) {
            if (typeof computeAmzRuleStack !== 'function') return 0;
            const stack = computeAmzRuleStack(d);
            return stack && isFinite(stack.totalDisc) ? stack.totalDisc : 0;
        }
        function amzStdSprice(d) {
            const std = Number(d && d.STANDARD_PRICE) || 0;
            if (!(std > 0)) return 0;
            const pct = Math.min(99.99, Math.max(-100, Number(computeAmzSumDiscountPct(d)) || 0));
            return (typeof amzPefRound2 === 'function')
                ? amzPefRound2(std * (1 - (pct / 100)))
                : Math.round(std * (1 - (pct / 100)) * 100) / 100;
        }
        window.computeAmzAgeDiscountPct = computeAmzAgeDiscountPct;
        window.computeAmzDilDiscountPct = computeAmzDilDiscountPct;
        window.computeAmzSumDiscountPct = computeAmzSumDiscountPct;
        window.amzStdCvrTrendDisc = amzStdCvrTrendDisc;
        window.fmtAmzStdDiscBadge = fmtAmzStdDiscBadge;

        function amzStdNormRange(r) {
            let min = Number(r && r.min);
            let max = Number(r && r.max);
            if (!isFinite(min) || !isFinite(max)) return null;
            if (max < min) { const swap = min; min = max; max = swap; }
            return { min: min, max: max, disc: amzStdDisc(r && r.disc) };
        }
        function amzStdNormCvr(raw) {
            const out = Object.assign({}, AMZ_STD_CVR_DEFAULT);
            if (!raw) return out;
            const hadDown2Disc = raw.down2_disc !== undefined && raw.down2_disc !== null && raw.down2_disc !== '';
            const hadUp2Disc = raw.up2_disc !== undefined && raw.up2_disc !== null && raw.up2_disc !== '';
            ['down2_lt', 'down_lt', 'up_gt', 'up2_gt'].forEach(function(key) {
                const n = Number(raw[key]);
                if (!isFinite(n) || n < 0) return;
                out[key] = n;
            });
            ['down2_disc', 'down_disc', 'up_disc', 'up2_disc', 'flat_disc'].forEach(function(key) {
                if (raw[key] === undefined || raw[key] === null || raw[key] === '') return;
                out[key] = amzStdDisc(raw[key]);
            });
            if (!hadDown2Disc) out.down2_disc = out.down_disc;
            if (!hadUp2Disc) out.up2_disc = out.up_disc;
            return out;
        }
        function amzStdEachInvChild(fn) {
            if (typeof amzDgEachInvChild === 'function') {
                amzDgEachInvChild(fn);
                return;
            }
            const rows = (typeof allTableData !== 'undefined' && Array.isArray(allTableData)) ? allTableData : [];
            rows.forEach(function(d) {
                if (typeof amzPefIsChildRow === 'function' && amzPefIsChildRow(d) && amzPefInv(d) > 0) fn(d);
            });
        }
        function amzStdReadRanges(tbodySel, minCls, maxCls, discCls) {
            const rules = [];
            $(tbodySel).find('tr').each(function() {
                const rule = amzStdNormRange({
                    min: $(this).find(minCls).val(),
                    max: $(this).find(maxCls).val(),
                    disc: $(this).find(discCls).val(),
                });
                if (rule) rules.push(rule);
            });
            return rules;
        }
        function amzStdReadDraft() {
            const cvr = amzStdNormCvr({
                down2_lt: $('#amzStdPrcModal .amz-sp-cvr-down2-lt').val(),
                down2_disc: $('#amzStdPrcModal .amz-sp-cvr-down2-disc').val(),
                down_lt: $('#amzStdPrcModal .amz-sp-cvr-down-lt').val(),
                down_disc: $('#amzStdPrcModal .amz-sp-cvr-down-disc').val(),
                up_gt: $('#amzStdPrcModal .amz-sp-cvr-up-gt').val(),
                up_disc: $('#amzStdPrcModal .amz-sp-cvr-up-disc').val(),
                up2_gt: $('#amzStdPrcModal .amz-sp-cvr-up2-gt').val(),
                up2_disc: $('#amzStdPrcModal .amz-sp-cvr-up2-disc').val(),
                flat_disc: $('#amzStdPrcModal .amz-sp-cvr-flat-disc').val(),
            });
            const reviews = amzStdReadRanges('#amz-sp-rev-tbody', '.amz-sp-rev-min', '.amz-sp-rev-max', '.amz-sp-rev-disc');
            let reviewMax = parseInt($('#amz-sp-review-max').val(), 10);
            if (!isFinite(reviewMax) || reviewMax < 1) reviewMax = 4;
            return {
                dil: amzStdReadRanges('#amz-sp-dil-tbody', '.amz-sp-dil-min', '.amz-sp-dil-max', '.amz-sp-dil-disc'),
                age: amzStdReadRanges('#amz-sp-age-tbody', '.amz-sp-age-min', '.amz-sp-age-max', '.amz-sp-age-disc'),
                cvr: cvr,
                reviews: reviews,
                buss: amzStdReadRanges('#amz-sp-buss-tbody', '.amz-sp-buss-min', '.amz-sp-buss-max', '.amz-sp-buss-disc'),
                reviewMax: reviewMax,
                noReviewsNoDiscount: $('#amz-sp-no-reviews-no-disc').is(':checked'),
            };
        }
        function amzStdRangeRow(prefix, rule) {
            const min = rule.min;
            const max = rule.max;
            const disc = rule.disc;
            return '<tr>'
                + '<td class="text-center"><input type="number" step="0.1" class="form-control form-control-sm amz-sp-input ' + prefix + '-min" value="' + min + '"></td>'
                + '<td class="text-center"><input type="number" step="0.1" class="form-control form-control-sm amz-sp-input ' + prefix + '-max" value="' + max + '"></td>'
                + '<td class="amz-sp-count ' + prefix + '-count">0</td>'
                + '<td class="text-end"><input type="number" step="any" class="form-control form-control-sm amz-sp-input ' + prefix + '-disc" value="' + disc + '"></td>'
                + '<td class="text-center"><button type="button" class="btn btn-sm amz-sp-del ' + prefix + '-del" title="Remove">&times;</button></td>'
                + '</tr>';
        }
        function amzStdPaintRanges(tbody, prefix, rules) {
            const html = (rules || []).map(function(r) { return amzStdRangeRow(prefix, r); }).join('');
            $(tbody).html(html);
        }
        function amzStdPaintCvr(cfg) {
            cfg = amzStdNormCvr(cfg);
            $('#amzStdPrcModal .amz-sp-cvr-down2-lt').val(cfg.down2_lt);
            $('#amzStdPrcModal .amz-sp-cvr-down2-disc').val(cfg.down2_disc);
            $('#amzStdPrcModal .amz-sp-cvr-down-lt').val(cfg.down_lt);
            $('#amzStdPrcModal .amz-sp-cvr-down-disc').val(cfg.down_disc);
            $('#amzStdPrcModal .amz-sp-cvr-up-gt').val(cfg.up_gt);
            $('#amzStdPrcModal .amz-sp-cvr-up-disc').val(cfg.up_disc);
            $('#amzStdPrcModal .amz-sp-cvr-up2-gt').val(cfg.up2_gt);
            $('#amzStdPrcModal .amz-sp-cvr-up2-disc').val(cfg.up2_disc);
            $('#amzStdPrcModal .amz-sp-cvr-flat-disc').val(cfg.flat_disc);
        }
        function amzStdPaintModal() {
            amzStdPaintRanges('#amz-sp-dil-tbody', 'amz-sp-dil', amzStdDilRules);
            amzStdPaintRanges('#amz-sp-age-tbody', 'amz-sp-age', amzStdAgeRules);
            const reviews = (typeof amzReviewDiscRules !== 'undefined' && amzReviewDiscRules.length)
                ? amzReviewDiscRules
                : [{ min: 1, max: 2, disc: 4 }, { min: 2, max: 3, disc: 4 }];
            amzStdPaintRanges('#amz-sp-rev-tbody', 'amz-sp-rev', reviews);
            amzStdPaintRanges('#amz-sp-buss-tbody', 'amz-sp-buss', amzStdBussRules);
            const maxRev = (typeof amzReviewDiscMax !== 'undefined') ? amzReviewDiscMax : 4;
            $('#amz-sp-review-max').val(maxRev);
            const noRev = (typeof amzNoReviewsNoDiscount === 'undefined') ? true : !!amzNoReviewsNoDiscount;
            $('#amz-sp-no-reviews-no-disc').prop('checked', noRev);
            amzStdPaintCvr(amzStdCvrCfg);
            amzStdRefreshModal();
        }
        function amzStdMoney(n) {
            const v = Number(n) || 0;
            const sign = v < 0 ? '-' : '';
            return sign + '$' + Math.abs(v).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
        }
        function amzStdRowMargin(d) {
            const marginRaw = parseFloat(d && d.percentage);
            return (isFinite(marginRaw) && marginRaw > 0) ? marginRaw : 0.80;
        }
        function amzStdUnitProfit(d, sprice) {
            const lp = parseFloat(d && d.LP_productmaster) || 0;
            const ship = parseFloat(d && d.Ship_productmaster) || 0;
            return (sprice * amzStdRowMargin(d)) - ship - lp;
        }
        function amzStdEmptyBucket() {
            return { gross: 0, net: 0, sales: 0, cogs: 0, units: 0 };
        }
        function amzStdMetricHtml(bucket) {
            const sales = bucket.sales;
            const cogs = bucket.cogs;
            const rows = [
                ['GROI', bucket.gross, cogs > 0 ? (bucket.gross / cogs) * 100 : 0, 'groi'],
                ['GPFT', bucket.gross, sales > 0 ? (bucket.gross / sales) * 100 : 0, 'gpft'],
                ['NROI', bucket.net, cogs > 0 ? (bucket.net / cogs) * 100 : 0, 'nroi'],
                ['NPFT', bucket.net, sales > 0 ? (bucket.net / sales) * 100 : 0, 'npft'],
            ];
            return rows.map(function(row) {
                const moneyColor = row[1] < 0 ? '#dc3545' : '#166534';
                let pctHtml = Math.round(row[2]) + '%';
                if (window.MetricPctColors && typeof MetricPctColors.htmlFor === 'function') {
                    const painted = MetricPctColors.htmlFor(row[3], row[2], { decimals: 0, empty: '—' });
                    if (painted) pctHtml = painted;
                }
                return '<div class="amz-sp-metric">'
                    + '<span class="amz-sp-metric-name">' + row[0] + '</span>'
                    + '<span class="amz-sp-metric-money" style="color:' + moneyColor + '">' + amzStdMoney(row[1]) + '</span>'
                    + '<span class="amz-sp-metric-pct">' + pctHtml + '</span>'
                    + '</div>';
            }).join('');
        }
        function amzStdEsc(s) {
            return String(s == null ? '' : s).replace(/[&<>"']/g, function(ch) {
                return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch];
            });
        }
        function amzStdLegend(el, slices, counts, chart) {
            const total = slices.reduce(function(sum, s) { return sum + (counts[s.key] || 0); }, 0);
            const html = slices.map(function(s) {
                const n = counts[s.key] || 0;
                const pct = total > 0 ? Math.round((n / total) * 100) : 0;
                amzStdHistLive[chart + ':' + s.key] = n;
                return '<div class="amz-sp-leg-row"><span class="amz-sp-swatch" style="background:' + s.color + '"></span>'
                    + '<span>' + amzStdEsc(s.label) + '</span><strong>' + n + '</strong><span class="amz-sp-leg-pct">' + pct + '%</span>'
                    + '<button type="button" class="amz-sp-hist-dot" data-chart="' + amzStdEsc(chart) + '" data-band="' + amzStdEsc(s.key) + '" '
                    + 'data-label="' + amzStdEsc(s.label) + '" data-color="' + amzStdEsc(s.color) + '" '
                    + 'style="background:' + s.color + ';" title="' + amzStdEsc(s.label) + ' daily history"></button></div>';
            }).join('');
            $(el).html(html);
        }
        function amzStdTodayKey() {
            try {
                return new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Los_Angeles' }).format(new Date());
            } catch (e) {
                return new Date().toISOString().slice(0, 10);
            }
        }
        function amzStdSaveDailyHistory() {
            const counts = Object.assign({}, amzStdHistLive);
            const any = Object.keys(counts).some(function(key) { return Number(counts[key]) > 0; });
            if (!any) return;
            try {
                const key = 'amz_std_prc_vs_dil_hist';
                const today = amzStdTodayKey();
                let hist = {};
                try { hist = JSON.parse(localStorage.getItem(key) || '{}') || {}; } catch (e) { hist = {}; }
                hist[today] = counts;
                const keys = Object.keys(hist).sort();
                while (keys.length > 90) delete hist[keys.shift()];
                localStorage.setItem(key, JSON.stringify(hist));
            } catch (e) { /* ignore */ }
            $.ajax({
                url: '/amazon-std-prc-vs-dil-history',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': (typeof amzPefCsrf === 'function' ? amzPefCsrf() : ''), 'Accept': 'application/json' },
                data: { _token: (typeof amzPefCsrf === 'function' ? amzPefCsrf() : ''), counts: counts },
            });
        }
        function amzStdLocalHistory() {
            try {
                const hist = JSON.parse(localStorage.getItem('amz_std_prc_vs_dil_hist') || '{}') || {};
                return Object.keys(hist).sort().map(function(date) {
                    return Object.assign({ date: date, label: date.slice(5) }, hist[date] || {});
                });
            } catch (e) {
                return [];
            }
        }
        function amzStdPadHistory(rows) {
            const byDate = {};
            (rows || []).forEach(function(r) { if (r && r.date) byDate[r.date] = r; });
            const today = amzStdTodayKey();
            const parts = today.split('-').map(Number);
            const end = new Date(Date.UTC(parts[0], (parts[1] || 1) - 1, parts[2] || 1));
            const out = [];
            for (let i = 29; i >= 0; i--) {
                const d = new Date(end);
                d.setUTCDate(d.getUTCDate() - i);
                const key = d.toISOString().slice(0, 10);
                const rec = byDate[key] ? Object.assign({}, byDate[key]) : {};
                rec.date = key;
                rec.label = key.slice(5);
                out.push(rec);
            }
            const last = out[out.length - 1];
            if (last) Object.assign(last, amzStdHistLive, { date: last.date, label: last.label });
            return out;
        }
        function amzStdHistLabelsPlugin() {
            return {
                id: 'amzStdHistCountLabels',
                afterDraw: function(chart) {
                    const dataset = chart.data.datasets[0];
                    const meta = chart.getDatasetMeta(0);
                    const c = chart.ctx;
                    if (!dataset || !meta || !meta.data) return;
                    meta.data.forEach(function(point, i) {
                        const val = dataset.data[i];
                        if (val == null || !point) return;
                        const txt = String(Math.round(Number(val) || 0));
                        c.save();
                        c.font = 'bold 10px Inter, system-ui, sans-serif';
                        c.fillStyle = '#111';
                        c.strokeStyle = 'rgba(255,255,255,0.95)';
                        c.lineWidth = 3;
                        c.lineJoin = 'round';
                        c.textAlign = 'center';
                        c.textBaseline = 'bottom';
                        c.strokeText(txt, point.x, point.y - 5);
                        c.fillText(txt, point.x, point.y - 5);
                        c.restore();
                    });
                },
            };
        }
        function amzStdDrawHist(chart, band, label, color, rows) {
            const field = chart + ':' + band;
            const plot = amzStdPadHistory(rows);
            const money = chart === 'all';
            $('#amz-sp-hist-title').text(label + (money ? ' · $ off' : ' count') + ' · last 30 days');
            $('#amz-sp-hist-wrap').addClass('is-open');
            const draw = function() {
                const canvas = document.getElementById('amz-sp-hist');
                if (!canvas || typeof Chart === 'undefined') return;
                if (amzStdHistChart) { amzStdHistChart.destroy(); amzStdHistChart = null; }
                amzStdHistChart = new Chart(canvas.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: plot.map(function(r) { return r.label || r.date; }),
                        datasets: [{
                            data: plot.map(function(r) { return Number(r[field]) || 0; }),
                            borderColor: color,
                            backgroundColor: color + '22',
                            fill: true,
                            tension: 0.3,
                            borderWidth: 1.5,
                            pointRadius: 3,
                            pointHoverRadius: 5,
                            pointBackgroundColor: color,
                            pointBorderColor: color,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        clip: false,
                        layout: { padding: { top: 16, right: 8, bottom: 2 } },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function(ctx) {
                                        const n = Number(ctx.raw) || 0;
                                        return money ? (' ' + amzStdMoney(n)) : (' Count: ' + Math.round(n));
                                    },
                                },
                            },
                        },
                        scales: {
                            y: { beginAtZero: true, ticks: { font: { size: 9 }, precision: 0 } },
                            x: {
                                offset: true,
                                ticks: { maxRotation: 90, minRotation: 90, autoSkip: false, font: { size: 8, weight: '600' } },
                            },
                        },
                    },
                    plugins: [amzStdHistLabelsPlugin()],
                });
            };
            if (typeof window.loadChartJs === 'function') window.loadChartJs().then(draw).catch(draw);
            else draw();
        }
        function amzStdOpenHist(chart, band, label, color) {
            const drawRows = function(rows) { amzStdDrawHist(chart, band, label, color, rows); };
            $.ajax({
                url: '/amazon-std-prc-vs-dil-history',
                method: 'GET',
                data: { days: 30 },
            }).done(function(res) {
                drawRows((res && res.success && Array.isArray(res.data)) ? res.data : amzStdLocalHistory());
            }).fail(function() {
                drawRows(amzStdLocalHistory());
            });
        }
        function amzStdDrawPieNow(id, slices, counts) {
            const canvas = document.getElementById(id);
            if (!canvas || typeof Chart === 'undefined') return;
            const bound = (typeof Chart.getChart === 'function') ? Chart.getChart(canvas) : amzStdPieCharts[id];
            if (bound) bound.destroy();
            amzStdPieCharts[id] = null;
            const total = slices.reduce(function(sum, s) { return sum + (counts[s.key] || 0); }, 0);
            const data = slices.map(function(s) { return counts[s.key] || 0; });
            amzStdPieCharts[id] = new Chart(canvas.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: slices.map(function(s) { return s.label; }),
                    datasets: [{
                        data: data,
                        backgroundColor: slices.map(function(s) { return s.color; }),
                        borderWidth: 0,
                        borderRadius: 2,
                        borderSkipped: 'bottom',
                        barPercentage: 0.78,
                        categoryPercentage: 0.72,
                        maxBarThickness: 42,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#0f172a',
                            padding: 10,
                            cornerRadius: 8,
                            displayColors: true,
                            boxPadding: 4,
                            callbacks: {
                                title: function(items) {
                                    return items && items[0] ? items[0].label : '';
                                },
                                label: function(ctx) {
                                    const n = Number(ctx.raw) || 0;
                                    const pct = total > 0 ? Math.round((n / total) * 100) : 0;
                                    return ' ' + n + ' · ' + pct + '%';
                                },
                            },
                        },
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            border: { display: true, color: '#111827' },
                            ticks: { display: false },
                        },
                        y: {
                            beginAtZero: true,
                            grid: { display: false },
                            border: { display: true, color: '#111827' },
                            ticks: { font: { size: 9 }, precision: 0, color: '#64748b' },
                        },
                    },
                },
            });
        }
        function amzStdDrawPies(jobs) {
            const gen = ++amzStdPieGen;
            const draw = function() {
                if (gen !== amzStdPieGen) return;
                jobs.forEach(function(job) {
                    amzStdDrawPieNow(job.id, job.slices, job.counts);
                });
            };
            if (typeof window.loadChartJs === 'function') {
                window.loadChartJs().then(draw).catch(function() {});
                return;
            }
            draw();
        }
        function amzStdRefreshModal() {
            const draft = amzStdReadDraft();
            const dilCounts = {};
            const ageCounts = {};
            const revCounts = {};
            const bussCounts = {};
            const cvrCounts = { down2: 0, down: 0, flat: 0, up: 0, up2: 0 };
            draft.dil.forEach(function(r, i) { dilCounts['d' + i] = 0; });
            dilCounts.outside = 0;
            draft.age.forEach(function(r, i) { ageCounts['a' + i] = 0; });
            ageCounts.none = 0;
            draft.reviews.forEach(function(r, i) { revCounts['r' + i] = 0; });
            revCounts.none = 0;
            revCounts.noreviews = 0;
            draft.buss.forEach(function(r, i) { bussCounts['u' + i] = 0; });
            bussCounts.outside = 0;
            const dollars = { age: 0, dil: 0, cvr: 0, rev: 0, buss: 0 };
            const pctTotals = { age: 0, dil: 0, cvr: 0, rev: 0, buss: 0 };
            const skuHits = { age: 0, dil: 0, cvr: 0, rev: 0, buss: 0, all: 0 };
            const l30 = amzStdEmptyBucket();
            const inv = amzStdEmptyBucket();
            const adsPct = (typeof amzAmazonAdsPct === 'function') ? amzAmazonAdsPct() : 0;
            amzStdEachInvChild(function(d) {
                const std = Number(d.STANDARD_PRICE) || 0;
                const dilVal = (typeof amzPefDil === 'function') ? amzPefDil(d) : 0;
                let dilIdx = -1;
                for (let i = 0; i < draft.dil.length; i++) {
                    if (amzStdInRange(dilVal, draft.dil[i], i === draft.dil.length - 1)) { dilIdx = i; break; }
                }
                if (dilIdx >= 0) dilCounts['d' + dilIdx] += 1;
                else dilCounts.outside += 1;
                const ageVal = (d.age_days == null || d.age_days === '') ? null : Number(d.age_days);
                let ageIdx = -1;
                if (ageVal != null && isFinite(ageVal)) {
                    for (let i = 0; i < draft.age.length; i++) {
                        if (amzStdInRange(ageVal, draft.age[i], i === draft.age.length - 1)) { ageIdx = i; break; }
                    }
                }
                if (ageIdx >= 0) ageCounts['a' + ageIdx] += 1;
                else ageCounts.none += 1;
                const reviews = (typeof amzPefReviewCount === 'function') ? amzPefReviewCount(d) : 0;
                const reviewQty = parseFloat(d && d.amz_review_count);
                const noReviews = !(isFinite(reviewQty) && reviewQty > 0);
                let revIdx = -1;
                if (draft.noReviewsNoDiscount && noReviews) {
                    revCounts.noreviews += 1;
                } else if (reviews > 0 && reviews < draft.reviewMax) {
                    for (let i = 0; i < draft.reviews.length; i++) {
                        const rule = draft.reviews[i];
                        if (reviews >= rule.min && reviews <= rule.max) { revIdx = i; break; }
                    }
                    if (revIdx >= 0) revCounts['r' + revIdx] += 1;
                    else revCounts.none += 1;
                } else {
                    revCounts.none += 1;
                }
                const stdHit = Number(std) || 0;
                let bussIdx = -1;
                for (let i = 0; i < draft.buss.length; i++) {
                    if (amzStdInRange(stdHit, draft.buss[i], i === draft.buss.length - 1)) { bussIdx = i; break; }
                }
                if (bussIdx >= 0) bussCounts['u' + bussIdx] += 1;
                else bussCounts.outside += 1;
                const band = amzStdCvrBand(d, draft.cvr);
                cvrCounts[band] = (cvrCounts[band] || 0) + 1;

                let ageDisc = ageIdx >= 0 ? amzStdDisc(draft.age[ageIdx].disc) : 0;
                let dilDisc = dilIdx >= 0 ? amzStdDisc(draft.dil[dilIdx].disc) : 0;
                let revDisc = revIdx >= 0 ? amzStdDisc(draft.reviews[revIdx].disc) : 0;
                let bussDisc = bussIdx >= 0 ? amzStdDisc(draft.buss[bussIdx].disc) : 0;
                const cvrHit = amzStdCvrMatch(d, draft.cvr);
                let cvrDisc = amzStdDisc(cvrHit ? cvrHit.disc : draft.cvr.flat_disc);
                ageDisc = amzStdScaleDisc(std, ageDisc);
                dilDisc = amzStdScaleDisc(std, dilDisc);
                revDisc = amzStdScaleDisc(std, revDisc);
                bussDisc = amzStdScaleDisc(std, bussDisc);
                cvrDisc = amzStdScaleDisc(std, cvrDisc);
                if (ageDisc !== 0) { skuHits.age++; dollars.age += std * ageDisc / 100; pctTotals.age += ageDisc; }
                if (dilDisc !== 0) { skuHits.dil++; dollars.dil += std * dilDisc / 100; pctTotals.dil += dilDisc; }
                if (cvrDisc !== 0) { skuHits.cvr++; dollars.cvr += std * cvrDisc / 100; pctTotals.cvr += cvrDisc; }
                if (revDisc !== 0) { skuHits.rev++; dollars.rev += std * revDisc / 100; pctTotals.rev += revDisc; }
                if (bussDisc !== 0) { skuHits.buss++; dollars.buss += std * bussDisc / 100; pctTotals.buss += bussDisc; }
                const sum = Math.min(99.99, Math.max(-100, ageDisc + dilDisc + cvrDisc + revDisc + bussDisc));
                if (sum !== 0) skuHits.all++;
                if (std > 0) {
                    const sprice = Math.round(std * (1 - (sum / 100)) * 100) / 100;
                    const profit = amzStdUnitProfit(d, sprice);
                    const net = profit - (sprice * adsPct / 100);
                    const lp = parseFloat(d.LP_productmaster) || 0;
                    const al30 = (typeof amzPefAL30 === 'function') ? amzPefAL30(d) : 0;
                    const onHand = (typeof amzPefInv === 'function') ? amzPefInv(d) : 0;
                    l30.gross += profit * al30;
                    l30.net += net * al30;
                    l30.sales += sprice * al30;
                    l30.cogs += lp * al30;
                    l30.units += al30;
                    inv.gross += profit * onHand;
                    inv.net += net * onHand;
                    inv.sales += sprice * onHand;
                    inv.cogs += lp * onHand;
                    inv.units += onHand;
                }
            });
            $('#amz-sp-dil-tbody tr').each(function(i) {
                $(this).find('.amz-sp-dil-count').text(dilCounts['d' + i] || 0);
            });
            $('#amz-sp-age-tbody tr').each(function(i) {
                $(this).find('.amz-sp-age-count').text(ageCounts['a' + i] || 0);
            });
            $('#amz-sp-rev-tbody tr').each(function(i) {
                $(this).find('.amz-sp-rev-count').text(revCounts['r' + i] || 0);
            });
            $('#amz-sp-buss-tbody tr').each(function(i) {
                $(this).find('.amz-sp-buss-count').text(bussCounts['u' + i] || 0);
            });
            $('#amz-sp-cvr-down2-count').text(cvrCounts.down2 || 0);
            $('#amz-sp-cvr-down-count').text(cvrCounts.down || 0);
            $('#amz-sp-cvr-flat-count').text(cvrCounts.flat || 0);
            $('#amz-sp-cvr-up-count').text(cvrCounts.up || 0);
            $('#amz-sp-cvr-up2-count').text(cvrCounts.up2 || 0);

            const dilSlices = draft.dil.map(function(r, i) {
                return { key: 'd' + i, label: r.min + '–' + r.max, color: AMZ_STD_PIE_COLORS[i % AMZ_STD_PIE_COLORS.length] };
            }).concat([{ key: 'outside', label: 'Outside', color: '#cbd5e1' }]);
            const ageSlices = draft.age.map(function(r, i) {
                return { key: 'a' + i, label: r.min + '–' + r.max, color: AMZ_STD_PIE_COLORS[i % AMZ_STD_PIE_COLORS.length] };
            }).concat([{ key: 'none', label: 'No age', color: '#cbd5e1' }]);
            const revSlices = draft.reviews.map(function(r, i) {
                return { key: 'r' + i, label: r.min + '–' + r.max, color: AMZ_STD_PIE_COLORS[i % AMZ_STD_PIE_COLORS.length] };
            });
            if (draft.noReviewsNoDiscount) {
                revSlices.push({ key: 'noreviews', label: 'No reviews', color: '#94a3b8' });
            }
            revSlices.push({ key: 'none', label: 'No disc', color: '#cbd5e1' });
            const bussSlices = draft.buss.map(function(r, i) {
                return { key: 'u' + i, label: r.min + '–' + r.max, color: AMZ_STD_PIE_COLORS[i % AMZ_STD_PIE_COLORS.length] };
            }).concat([{ key: 'outside', label: 'Outside', color: '#cbd5e1' }]);
            const cvrSlices = [
                { key: 'down2', label: 'Down < ' + draft.cvr.down2_lt + '%', color: '#9f1239' },
                { key: 'down', label: 'Down < ' + draft.cvr.down_lt + '%', color: '#dc3545' },
                { key: 'flat', label: 'Flat', color: '#94a3b8' },
                { key: 'up', label: 'Up > ' + draft.cvr.up_gt + '%', color: '#198754' },
                { key: 'up2', label: 'Up > ' + draft.cvr.up2_gt + '%', color: '#14532d' },
            ];
            const allSlices = [
                { key: 'age', label: 'Age', color: '#d97706' },
                { key: 'dil', label: 'Dil', color: '#6f42c1' },
                { key: 'cvr', label: 'CVR', color: '#20c997' },
                { key: 'rev', label: 'Reviews', color: '#7c3aed' },
                { key: 'buss', label: 'B Disc', color: '#0d6efd' },
            ];
            const allCounts = {
                age: Math.round(dollars.age),
                dil: Math.round(dollars.dil),
                cvr: Math.round(dollars.cvr),
                rev: Math.round(dollars.rev),
                buss: Math.round(dollars.buss),
            };
            amzStdDrawPies([
                { id: 'amz-sp-pie-dil', slices: dilSlices, counts: dilCounts },
                { id: 'amz-sp-pie-rev', slices: revSlices, counts: revCounts },
                { id: 'amz-sp-pie-buss', slices: bussSlices, counts: bussCounts },
                { id: 'amz-sp-pie-cvr', slices: cvrSlices, counts: cvrCounts },
                { id: 'amz-sp-pie-age', slices: ageSlices, counts: ageCounts },
                { id: 'amz-sp-pie-all', slices: allSlices, counts: allCounts },
            ]);
            amzStdHistLive = {};
            amzStdLegend('#amz-sp-leg-dil', dilSlices, dilCounts, 'dil');
            amzStdLegend('#amz-sp-leg-rev', revSlices, revCounts, 'rev');
            amzStdLegend('#amz-sp-leg-buss', bussSlices, bussCounts, 'buss');
            amzStdLegend('#amz-sp-leg-cvr', cvrSlices, cvrCounts, 'cvr');
            amzStdLegend('#amz-sp-leg-age', ageSlices, ageCounts, 'age');
            amzStdLegend('#amz-sp-leg-all', allSlices, allCounts, 'all');

            const rows = [
                ['Age', skuHits.age, pctTotals.age, dollars.age],
                ['Dil', skuHits.dil, pctTotals.dil, dollars.dil],
                ['B Disc', skuHits.buss, pctTotals.buss, dollars.buss],
                ['CVR', skuHits.cvr, pctTotals.cvr, dollars.cvr],
                ['Reviews', skuHits.rev, pctTotals.rev, dollars.rev],
            ];
            let pctSum = 0;
            let dollarSum = 0;
            const body = rows.map(function(row) {
                pctSum += row[2];
                dollarSum += row[3];
                return '<tr><td>' + row[0] + '</td><td class="text-center">' + row[1]
                    + '</td><td class="text-end">' + row[2] + '</td><td class="text-end">' + amzStdMoney(row[3]) + '</td></tr>';
            });
            body.push('<tr class="fw-semibold"><td>All</td><td class="text-center">' + skuHits.all
                + '</td><td class="text-end">' + pctSum + '</td><td class="text-end">' + amzStdMoney(dollarSum) + '</td></tr>');
            $('#amz-sp-all-tbody').html(body.join(''));

            const l30Pct = l30.sales > 0 ? (l30.gross / l30.sales) * 100 : 0;
            const invPct = inv.sales > 0 ? (inv.gross / inv.sales) * 100 : 0;
            $('#amz-sp-margin-l30').text(amzStdMoney(l30.gross)).css('color', l30.gross < 0 ? '#dc3545' : '#166534');
            $('#amz-sp-margin-l30-sub').text(Math.round(l30.units) + ' units · ' + Math.round(l30Pct) + '% of sales');
            $('#amz-sp-margin-l30-metrics').html(amzStdMetricHtml(l30));
            $('#amz-sp-margin-inv').text(amzStdMoney(inv.gross)).css('color', inv.gross < 0 ? '#dc3545' : '#166534');
            $('#amz-sp-margin-inv-sub').text(Math.round(inv.units) + ' units · ' + Math.round(invPct) + '% of retail');
            $('#amz-sp-margin-inv-metrics').html(amzStdMetricHtml(inv));
        }
        function amzStdInRange(value, rule, isLast) {
            const n = Number(value);
            if (!rule || !isFinite(n)) return false;
            let min = Number(rule.min);
            let max = Number(rule.max);
            if (!isFinite(min) || !isFinite(max)) return false;
            if (max < min) { const swap = min; min = max; max = swap; }
            if (Math.abs(max - min) < 0.00001) return Math.abs(n - min) < 0.00001;
            return n >= min && (isLast ? n <= max : n < max);
        }
        function loadAmzStdPrcRules() {
            return $.ajax({
                url: '/amazon-std-prc-vs-dil',
                method: 'GET',
                headers: { 'Accept': 'application/json' },
            }).done(function(res) {
                if (!res || !res.success) return;
                amzStdDilRules = (res.dil || AMZ_STD_DIL_DEFAULTS).map(function(r) { return amzStdNormRange(r); }).filter(Boolean);
                amzStdAgeRules = (res.age || AMZ_STD_AGE_DEFAULTS).map(function(r) { return amzStdNormRange(r); }).filter(Boolean);
                amzStdCvrCfg = amzStdNormCvr(res.cvr);
                amzStdBussRules = (res.buss || AMZ_STD_BUSS_DEFAULTS).map(function(r) { return amzStdNormRange(r); }).filter(Boolean);
                if (!amzStdDilRules.length) amzStdDilRules = AMZ_STD_DIL_DEFAULTS.map(function(r) { return Object.assign({}, r); });
                if (!amzStdAgeRules.length) amzStdAgeRules = AMZ_STD_AGE_DEFAULTS.map(function(r) { return Object.assign({}, r); });
                if (!amzStdBussRules.length) amzStdBussRules = AMZ_STD_BUSS_DEFAULTS.map(function(r) { return Object.assign({}, r); });
            });
        }
        window.loadAmzStdPrcRules = loadAmzStdPrcRules;
        function amzStdApplyRules() {
            const draft = amzStdReadDraft();
            if (!draft.dil.length || !draft.age.length) {
                if (typeof amzPefToast === 'function') amzPefToast('error', 'Dil and Age need at least one range');
                return;
            }
            amzStdApplied = true;
            amzStdDilRules = draft.dil;
            amzStdAgeRules = draft.age;
            amzStdCvrCfg = draft.cvr;
            amzStdBussRules = draft.buss.length ? draft.buss : amzStdBussRules;
            if (typeof amzReviewDiscRules !== 'undefined') {
                amzReviewDiscRules = draft.reviews.map(function(r) {
                    return (typeof amzNormalizeReviewDiscRule === 'function')
                        ? (amzNormalizeReviewDiscRule(r) || r)
                        : r;
                }).filter(Boolean);
            }
            if (typeof amzReviewDiscMax !== 'undefined') amzReviewDiscMax = draft.reviewMax;
            if (typeof amzNoReviewsNoDiscount !== 'undefined') amzNoReviewsNoDiscount = !!draft.noReviewsNoDiscount;
            const status = $('#amz-sp-status');
            status.text('Saving…');
            const stdSave = $.ajax({
                url: '/amazon-std-prc-vs-dil',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': (typeof amzPefCsrf === 'function' ? amzPefCsrf() : ''), 'Accept': 'application/json' },
                data: { _token: (typeof amzPefCsrf === 'function' ? amzPefCsrf() : ''), dil: draft.dil, age: draft.age, cvr: draft.cvr, buss: draft.buss },
            });
            const revSave = $.ajax({
                url: '/amazon-review-disc',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': (typeof amzPefCsrf === 'function' ? amzPefCsrf() : ''), 'Accept': 'application/json' },
                data: { _token: (typeof amzPefCsrf === 'function' ? amzPefCsrf() : ''), rules: draft.reviews, max_reviews: draft.reviewMax, no_reviews_no_discount: draft.noReviewsNoDiscount ? 1 : 0 },
            });
            $.when(stdSave, revSave).done(function(stdRes) {
                const res = stdRes && stdRes[0] ? stdRes[0] : stdRes;
                if (res && res.dil) amzStdDilRules = res.dil.map(amzStdNormRange).filter(Boolean);
                if (res && res.age) amzStdAgeRules = res.age.map(amzStdNormRange).filter(Boolean);
                if (res && res.cvr) amzStdCvrCfg = amzStdNormCvr(res.cvr);
                if (res && res.buss) amzStdBussRules = res.buss.map(amzStdNormRange).filter(Boolean);
                if (typeof table !== 'undefined' && table && typeof amzTableRedrawPreserveScroll === 'function') {
                    amzTableRedrawPreserveScroll(true);
                }
                const picked = (typeof collectAmzPromoApplyTargets === 'function')
                    ? collectAmzPromoApplyTargets('No rows to price')
                    : { targets: [], cancelled: true, label: '' };
                if (!picked.cancelled && typeof applyAmzCombinedPlanToTargets === 'function') {
                    applyAmzCombinedPlanToTargets(picked.targets, picked.label, { toastLabel: 'Std prc vs dil' });
                }
                status.text('Saved. S PRC = Std Prc − Sum disc.');
                amzStdRefreshModal();
                amzStdSaveDailyHistory();
                if (typeof amzPefToast === 'function') amzPefToast('success', 'Std prc vs dil saved');
            }).fail(function() {
                amzStdApplied = false;
                status.text('Save failed');
                if (typeof amzPefToast === 'function') amzPefToast('error', 'Could not save Std prc vs dil');
            });
        }
        function bindAmzStdPrcUi() {
            $('#amz-std-prc-btn').off('click.amzsp').on('click.amzsp', function(e) {
                e.preventDefault();
                amzStdApplied = false;
                amzStdPaintModal();
                const el = document.getElementById('amzStdPrcModal');
                if (el && window.bootstrap && bootstrap.Modal) bootstrap.Modal.getOrCreateInstance(el).show();
            });
            $('#amzStdPrcModal').off('shown.bs.modal.amzsp').on('shown.bs.modal.amzsp', function() {
                amzStdRefreshModal();
                amzStdSaveDailyHistory();
            });
            $('#amzStdPrcModal').off('hidden.bs.modal.amzsp').on('hidden.bs.modal.amzsp', function() {
                amzStdPieGen++;
                $('#amz-sp-hist-wrap').removeClass('is-open');
                if (amzStdHistChart) { amzStdHistChart.destroy(); amzStdHistChart = null; }
                Object.keys(amzStdPieCharts).forEach(function(id) {
                    if (amzStdPieCharts[id]) { amzStdPieCharts[id].destroy(); amzStdPieCharts[id] = null; }
                });
                if (!amzStdApplied && typeof loadAmzStdPrcRules === 'function') {
                    loadAmzStdPrcRules();
                }
            });
            $('#amz-sp-apply').off('click.amzsp').on('click.amzsp', function(e) {
                e.preventDefault();
                amzStdApplyRules();
            });
            $('#amz-sp-dil-add').off('click.amzsp').on('click.amzsp', function() {
                const rules = amzStdReadRanges('#amz-sp-dil-tbody', '.amz-sp-dil-min', '.amz-sp-dil-max', '.amz-sp-dil-disc');
                const last = rules.length ? rules[rules.length - 1].max : 0;
                rules.push({ min: last, max: last + 10, disc: 0 });
                amzStdPaintRanges('#amz-sp-dil-tbody', 'amz-sp-dil', rules);
                amzStdRefreshModal();
            });
            $('#amz-sp-age-add').off('click.amzsp').on('click.amzsp', function() {
                const rules = amzStdReadRanges('#amz-sp-age-tbody', '.amz-sp-age-min', '.amz-sp-age-max', '.amz-sp-age-disc');
                const last = rules.length ? rules[rules.length - 1].max : 0;
                rules.push({ min: last + 1, max: last + 30, disc: 0 });
                amzStdPaintRanges('#amz-sp-age-tbody', 'amz-sp-age', rules);
                amzStdRefreshModal();
            });
            $('#amz-sp-rev-add').off('click.amzsp').on('click.amzsp', function() {
                const rules = amzStdReadRanges('#amz-sp-rev-tbody', '.amz-sp-rev-min', '.amz-sp-rev-max', '.amz-sp-rev-disc');
                const last = rules.length ? rules[rules.length - 1].max : 0;
                rules.push({ min: last, max: last + 1, disc: 4 });
                amzStdPaintRanges('#amz-sp-rev-tbody', 'amz-sp-rev', rules);
                amzStdRefreshModal();
            });
            $('#amz-sp-buss-add').off('click.amzsp').on('click.amzsp', function() {
                const rules = amzStdReadRanges('#amz-sp-buss-tbody', '.amz-sp-buss-min', '.amz-sp-buss-max', '.amz-sp-buss-disc');
                const last = rules.length ? rules[rules.length - 1].max : 0;
                rules.push({ min: last, max: last + 10, disc: 0 });
                amzStdPaintRanges('#amz-sp-buss-tbody', 'amz-sp-buss', rules);
                amzStdRefreshModal();
            });
            $(document).off('click.amzspdel').on('click.amzspdel', '#amzStdPrcModal .amz-sp-dil-del, #amzStdPrcModal .amz-sp-age-del, #amzStdPrcModal .amz-sp-rev-del, #amzStdPrcModal .amz-sp-buss-del', function() {
                $(this).closest('tr').remove();
                amzStdRefreshModal();
            });
            $(document).off('input.amzsp change.amzsp').on('input.amzsp change.amzsp', '#amzStdPrcModal input', function() {
                clearTimeout(bindAmzStdPrcUi._t);
                bindAmzStdPrcUi._t = setTimeout(amzStdRefreshModal, 180);
            });
            $(document).off('click.amzsphist', '#amzStdPrcModal .amz-sp-hist-dot').on('click.amzsphist', '#amzStdPrcModal .amz-sp-hist-dot', function(e) {
                e.preventDefault();
                const $dot = $(this);
                amzStdOpenHist(String($dot.attr('data-chart') || ''), String($dot.attr('data-band') || ''), String($dot.attr('data-label') || ''), String($dot.attr('data-color') || '#0f172a'));
            });
            $('#amz-sp-hist-close').off('click.amzsphist').on('click.amzsphist', function() {
                $('#amz-sp-hist-wrap').removeClass('is-open');
            });
        }
        $(function() { bindAmzStdPrcUi(); });
@endif
