@php
    $dilSbidAccount = $account ?? 'eBay';
@endphp
@if(($part ?? 'modal') === 'modal' && ! empty($extended))
@php
    $dsbTables = [
        'views' => ['title' => 'Views', 'hint' => 'eBay views, L30. Each range adds its S Bid.'],
        'cvr' => ['title' => 'CVR %', 'hint' => 'CVR L30 % = eBay L30 sold ÷ Views × 100. Each range adds its S Bid.'],
        'sold' => ['title' => 'eBay Sold', 'hint' => 'eBay L30 units sold. Each range adds its S Bid.'],
        'npft' => ['title' => 'Std NPFT %', 'hint' => 'Std NPFT % from /lmp-overall: ((Std Prc × 0.70 − ship − LP) ÷ Std Prc) × 100. Needs a Std Prc. Each range adds its S Bid.'],
    ];
@endphp
<div class="modal fade is-off dsb-ext" id="dilSbidRuleModal" tabindex="-1" aria-labelledby="dilSbidRuleModalLabel" aria-hidden="true">
    <style>
        #dilSbidRuleModal .modal-dialog { width: calc(100vw - 1.25rem); max-width: calc(100vw - 1.25rem); height: calc(100vh - 1.25rem); max-height: calc(100vh - 1.25rem); margin: 0.625rem auto; }
        #dilSbidRuleModal .modal-content { height: 100%; max-height: 100%; border: 0; border-radius: 16px; overflow: hidden; box-shadow: 0 24px 60px rgba(15, 23, 42, 0.18); }
        #dilSbidRuleModal .modal-header { background: #fff; border-bottom: 1px solid #e8eef5; padding: 12px 18px; }
        #dilSbidRuleModal .modal-title { font-weight: 700; color: #0f172a; }
        #dilSbidRuleModal .modal-body { background: #f4f7fb; padding: 14px 16px 16px; }
        #dilSbidRuleModal .modal-footer { background: #fff; border-top: 1px solid #e8eef5; }
        #dilSbidRuleModal input[type=number]::-webkit-inner-spin-button,
        #dilSbidRuleModal input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
        #dilSbidRuleModal input[type=number] { -moz-appearance: textfield; appearance: textfield; }
        #dilSbidRuleModal.is-off .dsb-cols { opacity: 0.55; }
        .dil-sbid-switch .form-check-input { width: 2.4em; height: 1.25em; cursor: pointer; }
        .dil-sbid-dot { display: inline-block; width: 9px; height: 9px; border-radius: 50%; margin-left: 6px; vertical-align: middle; }
        .dil-sbid-badge { display: inline-block; min-width: 60px; text-align: center; font-weight: 700; font-size: 11px; padding: 4px 6px; border-radius: 8px; }
        .dil-sbid-es { background: #cff4fc; color: #055160; }
        #dilSbidRuleModal .dsb-sub { color: #64748b; font-size: 12px; margin-top: 2px; }
        #dilSbidRuleModal .dsb-notes { color: #64748b; font-size: 12px; margin: 0 0 12px; padding-left: 1.1rem; }
        #dilSbidRuleModal .modal-body { overflow-x: auto; }
        #dilSbidRuleModal .dsb-cols { display: grid; grid-template-columns: repeat(8, minmax(0, 1fr)); gap: 8px; align-items: stretch; width: 100%; min-width: 1440px; }
        #dilSbidRuleModal .dsb-col { min-width: 0; display: flex; flex-direction: column; border: 1px solid #e6edf5; border-radius: 12px; padding: 8px; background: #fff; }
        #dilSbidRuleModal .dsb-col-sum { border-color: #b6d4fe; box-shadow: 0 0 0 2px rgba(13, 110, 253, 0.08); }
        #dilSbidRuleModal .dsb-title { font-weight: 700; font-size: 13px; margin-bottom: 8px; color: #0f172a; }
        #dilSbidRuleModal .dsb-canvas { position: relative; width: 100%; height: 120px; }
        #dilSbidRuleModal .dsb-canvas canvas { display: block; width: 100% !important; height: 120px !important; }
        #dilSbidRuleModal .dsb-legend { width: 100%; font-size: 11px; margin: 6px 0; color: #334155; }
        #dilSbidRuleModal .dsb-leg-row { display: grid; grid-template-columns: 8px minmax(0, 1fr) auto auto; gap: 4px; align-items: center; padding: 1px 0; }
        #dilSbidRuleModal .dsb-leg-row > span:nth-child(2) { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        #dilSbidRuleModal .dsb-swatch { width: 8px; height: 8px; border-radius: 50%; }
        #dilSbidRuleModal .dsb-leg-pct { color: #94a3b8; min-width: 2rem; text-align: right; }
        #dilSbidRuleModal .dsb-col .table { font-size: 11px; margin-bottom: 0; }
        #dilSbidRuleModal .dsb-col .table th, #dilSbidRuleModal .dsb-col .table td { padding: 3px 2px; vertical-align: middle; }
        #dilSbidRuleModal .dsb-col .table thead th { background: #e7f1fb; color: #1e3a5f; font-size: 9px; letter-spacing: 0.02em; text-transform: uppercase; font-weight: 600; text-align: center; }
        #dilSbidRuleModal .dsb-input { width: 100%; min-width: 0; max-width: 44px; margin: 0 auto; text-align: center; font-weight: 600; font-size: 12px; height: 24px; padding: 0 2px; border-radius: 6px; }
        #dilSbidRuleModal td.text-end .dsb-input { margin-right: 0; margin-left: auto; }
        #dilSbidRuleModal .dsb-count { font-weight: 700; text-align: center; white-space: nowrap; }
        #dilSbidRuleModal .dsb-count .dil-sbid-dot { margin-left: 3px; }
        #dilSbidRuleModal .dsb-add { margin-top: 6px; width: 100%; border-radius: 8px; border-style: dashed; font-weight: 600; font-size: 12px; padding: 2px 4px; }
        #dilSbidRuleModal .dsb-del { width: 18px; height: 18px; padding: 0; line-height: 1; border-radius: 5px; font-size: 12px; }
        #dilSbidRuleModal .dil-sbid-badge { min-width: 0; width: 44px; font-size: 9px; padding: 3px 0; }
        #dilSbidRuleModal .dsb-sum-total td { font-weight: 700; background: #eef5ff; }
        #dil-sbid-cvr-table .dil-sbid-cvr-input,
        #dil-sbid-view-table .dil-sbid-view-input { width: 100%; max-width: 40px; display: inline-block; padding: 0 2px; }
        #dilSbidRuleModal .dsb-cap { display: flex; align-items: center; gap: 6px; white-space: nowrap; color: #334155; font-size: 12px; font-weight: 600; }
        #dilSbidRuleModal .dsb-cap-input { width: 52px; height: 28px; padding: 0 4px; font-weight: 700; text-align: center; }
    </style>
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title fs-6 mb-0" id="dilSbidRuleModalLabel">
                        <i class="fas fa-percent me-2 text-primary"></i>Dil vs SBid
                        <span class="badge bg-secondary ms-2" style="font-size:11px;">{{ $dilSbidAccount }} only</span>
                    </h5>
                    <div class="dsb-sub">S Bid = Dil + Views + CVR + eBay Sold + Std NPFT %, then CVR up / down, then L30 View up / down, then the Min / Max cap. The last card shows the sum that fills the S BID column.</div>
                </div>
                <div class="dsb-cap ms-3 me-2" title="The final S Bid never goes below Min or above Max. eBay only accepts 2–100.">
                    <label class="mb-0" for="dil-sbid-cap-min">Min</label>
                    <input type="number" min="2" max="100" step="0.1" class="form-control form-control-sm dsb-cap-input" id="dil-sbid-cap-min" value="2">
                    <label class="mb-0" for="dil-sbid-cap-max">Max</label>
                    <input type="number" min="2" max="100" step="0.1" class="form-control form-control-sm dsb-cap-input" id="dil-sbid-cap-max" value="100">
                </div>
                <div class="form-check form-switch dil-sbid-switch mb-0 me-3">
                    <input class="form-check-input" type="checkbox" role="switch" id="dil-sbid-enabled">
                    <label class="form-check-label small fw-semibold" for="dil-sbid-enabled" id="dil-sbid-enabled-label">Off</label>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small mb-2" id="dil-sbid-mode-note">Off. S Bid is not changed.</p>
                <ul class="dsb-notes">
                    <li>Count is unique SKUs. Dil is CP Master Dil: round(OV L30 sold ÷ Inventory × 100). Inventory 0 and missing data are not counted. First matching range wins in every table. A range that starts where the one above ended is exclusive on From. The last range is open at the top.</li>
                    <li><strong>Dil 0–0</strong> is SKUs with OV L30 sold = 0. Every Dil slab, including 0–0, uses the S Bid you type. Views, CVR, eBay Sold and Std NPFT % each <strong>add</strong> the S Bid of the range the SKU falls in. A negative value subtracts. A SKU outside every range adds 0.</li>
                    <li>New tables start at 0, so nothing changes until you type an S Bid. L30 View up / down uses the same arrow as the L30 View column (L7 pace vs L30 pace). Its Adj starts at 0. The final bid is rounded to 0.1 and kept between the Min and Max caps (those caps cannot go outside 2 and 100, which eBay accepts).</li>
                    <li>Views, CVR and eBay Sold use <code>ebay_metrics</code> L30, same as the server push. Std NPFT % needs a Std Prc, taken from the Sku Link LMP group when the SKU has none.</li>
                    <li>Saved for {{ $dilSbidAccount }} only. eBay 1, eBay 2 and eBay 3 each keep their own tables. They push the new S Bid on their own when the rules change the bid. The switch must be On.</li>
                </ul>
                <div class="dsb-cols">
                    <div class="dsb-col">
                        <div class="dsb-title" title="CP Master Dil. Each slab uses the S Bid you type.">Dil</div>
                        <div class="dsb-canvas"><canvas id="dil-sbid-chart-dil"></canvas></div>
                        <div class="dsb-legend" id="dil-sbid-leg-dil"></div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle mb-0" id="dil-sbid-table">
                                <thead>
                                    <tr>
                                        <th class="text-center">From</th>
                                        <th class="text-center">To</th>
                                        <th class="text-center" title="Unique SKUs. 0–0 is OV L30 sold = 0 with Inventory.">Count</th>
                                        <th class="text-end">S Bid</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody id="dil-sbid-tbody"></tbody>
                            </table>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary dsb-add" id="dil-sbid-add-btn"><i class="fas fa-plus me-1"></i>Add slab</button>
                    </div>
                    @foreach($dsbTables as $dsbKey => $dsbInfo)
                    <div class="dsb-col">
                        <div class="dsb-title" title="{{ $dsbInfo['hint'] }}">{{ $dsbInfo['title'] }}</div>
                        <div class="dsb-canvas"><canvas id="dil-sbid-chart-{{ $dsbKey }}"></canvas></div>
                        <div class="dsb-legend" id="dil-sbid-leg-{{ $dsbKey }}"></div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th class="text-center">From</th>
                                        <th class="text-center">To</th>
                                        <th class="text-center" title="Unique SKUs in this range.">Count</th>
                                        <th class="text-end">S Bid</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody class="dil-sbid-x-tbody" id="dil-sbid-x-tbody-{{ $dsbKey }}" data-table="{{ $dsbKey }}"></tbody>
                            </table>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary dsb-add dil-sbid-x-add" data-table="{{ $dsbKey }}"><i class="fas fa-plus me-1"></i>Add range</button>
                    </div>
                    @endforeach
                    <div class="dsb-col">
                        <div class="dsb-title" title="Adjusts the summed S Bid. Down = CVR below the threshold and the arrow is down (CVR L30 under CVR L60). Up = CVR above the threshold and the arrow is up. Flat arrows are left alone.">CVR up / down</div>
                        <div class="dsb-canvas"><canvas id="dil-sbid-chart-over"></canvas></div>
                        <div class="dsb-legend" id="dil-sbid-leg-over"></div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle mb-0" id="dil-sbid-cvr-table">
                                <thead>
                                    <tr>
                                        <th>When</th>
                                        <th class="text-center">CVR%</th>
                                        <th class="text-end">Adj S Bid</th>
                                        <th class="text-center" title="Unique SKUs. Down: CVR below the threshold and a down arrow. Up: CVR above the threshold and an up arrow.">Count</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr data-cvr-dir="down">
                                        <td>Down</td>
                                        <td class="text-center">&lt; <input type="number" min="0" step="0.1" class="form-control form-control-sm text-end dil-sbid-cvr-input" id="dil-sbid-cvr-down-lt" value="7"></td>
                                        <td class="text-end"><input type="number" step="1" class="form-control form-control-sm text-end dil-sbid-cvr-input" id="dil-sbid-cvr-down-adj" value="-10"></td>
                                        <td class="dsb-count"><span id="dil-sbid-cvr-down-count">0</span></td>
                                    </tr>
                                    <tr data-cvr-dir="up">
                                        <td>Up</td>
                                        <td class="text-center">&gt; <input type="number" min="0" step="0.1" class="form-control form-control-sm text-end dil-sbid-cvr-input" id="dil-sbid-cvr-up-gt" value="10"></td>
                                        <td class="text-end"><input type="number" step="1" class="form-control form-control-sm text-end dil-sbid-cvr-input" id="dil-sbid-cvr-up-adj" value="10"></td>
                                        <td class="dsb-count"><span id="dil-sbid-cvr-up-count">0</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex gap-1 mt-auto pt-2">
                            <button type="button" class="btn btn-sm btn-outline-primary dsb-add flex-fill mt-0" id="dil-sbid-cvr-add-down" title="Add a Down range. The lowest threshold CVR is under wins."><i class="fas fa-plus me-1"></i>Down range</button>
                            <button type="button" class="btn btn-sm btn-outline-primary dsb-add flex-fill mt-0" id="dil-sbid-cvr-add-up" title="Add an Up range. The highest threshold CVR is over wins."><i class="fas fa-plus me-1"></i>Up range</button>
                        </div>
                    </div>
                    <div class="dsb-col">
                        <div class="dsb-title" title="Adjusts the S Bid after the CVR overlay. Down = L30 views below the threshold and the L30 View arrow is down (L7 pace under L30 pace). Up = L30 views above the threshold and the arrow is up. Flat arrows are left alone.">L30 View up / down</div>
                        <div class="dsb-canvas"><canvas id="dil-sbid-chart-viewover"></canvas></div>
                        <div class="dsb-legend" id="dil-sbid-leg-viewover"></div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle mb-0" id="dil-sbid-view-table">
                                <thead>
                                    <tr>
                                        <th>When</th>
                                        <th class="text-center">Views</th>
                                        <th class="text-end">Adj S Bid</th>
                                        <th class="text-center" title="Unique SKUs. Down: L30 views below the threshold and a down arrow. Up: L30 views above the threshold and an up arrow.">Count</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr data-view-dir="down">
                                        <td>Down</td>
                                        <td class="text-center">&lt; <input type="number" min="0" step="1" class="form-control form-control-sm text-end dil-sbid-view-input" id="dil-sbid-view-down-lt" value="30"></td>
                                        <td class="text-end"><input type="number" step="1" class="form-control form-control-sm text-end dil-sbid-view-input" id="dil-sbid-view-down-adj" value="0"></td>
                                        <td class="dsb-count"><span id="dil-sbid-view-down-count">0</span></td>
                                    </tr>
                                    <tr data-view-dir="up">
                                        <td>Up</td>
                                        <td class="text-center">&gt; <input type="number" min="0" step="1" class="form-control form-control-sm text-end dil-sbid-view-input" id="dil-sbid-view-up-gt" value="30"></td>
                                        <td class="text-end"><input type="number" step="1" class="form-control form-control-sm text-end dil-sbid-view-input" id="dil-sbid-view-up-adj" value="0"></td>
                                        <td class="dsb-count"><span id="dil-sbid-view-up-count">0</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex gap-1 mt-auto pt-2">
                            <button type="button" class="btn btn-sm btn-outline-primary dsb-add flex-fill mt-0" id="dil-sbid-view-add-down" title="Add a Down range. The lowest threshold L30 views is under wins."><i class="fas fa-plus me-1"></i>Down range</button>
                            <button type="button" class="btn btn-sm btn-outline-primary dsb-add flex-fill mt-0" id="dil-sbid-view-add-up" title="Add an Up range. The highest threshold L30 views is over wins."><i class="fas fa-plus me-1"></i>Up range</button>
                        </div>
                    </div>
                    <div class="dsb-col dsb-col-sum">
                        <div class="dsb-title" title="Dil + Views + CVR + eBay Sold + Std NPFT %. This sum, after the CVR up / down adjustment, fills the S BID column.">Sum S Bid</div>
                        <div class="dsb-canvas"><canvas id="dil-sbid-chart-sum"></canvas></div>
                        <div class="dsb-legend" id="dil-sbid-leg-sum"></div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle mb-0" id="dil-sbid-sum-table">
                                <thead>
                                    <tr>
                                        <th>Table</th>
                                        <th class="text-center" title="Unique SKUs where this table adds a non-zero S Bid.">SKUs</th>
                                        <th class="text-end" title="Average S Bid this table adds, over those SKUs.">Avg S Bid</th>
                                    </tr>
                                </thead>
                                <tbody id="dil-sbid-sum-tbody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <p class="small text-danger mb-0 mt-2 d-none" id="dil-sbid-err"></p>
            </div>
            <div class="modal-footer py-2 d-flex justify-content-between">
                <span class="small text-muted" id="dil-sbid-status"></span>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-warning text-dark dil-sbid-push-btn" title="Push the saved Dil vs SBid rule to listings on this page.">
                        <i class="fas fa-cloud-upload-alt me-1"></i>Push SBID
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="dil-sbid-apply-btn">
                        <i class="fas fa-save me-1"></i>Save and Apply
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@elseif(($part ?? 'modal') === 'modal')
<div class="modal fade is-off" id="dilSbidRuleModal" tabindex="-1" aria-labelledby="dilSbidRuleModalLabel" aria-hidden="true">
    <style>
        #dilSbidRuleModal .modal-dialog { max-width: 760px; }
        #dil-sbid-cvr-table .dil-sbid-cvr-input,
        #dil-sbid-view-table .dil-sbid-view-input { width: 88px; display: inline-block; }
        #dil-sbid-table thead th { background: #e7f1fb; color: #1e3a5f; font-weight: 600; }
        #dilSbidRuleModal input[type=number]::-webkit-inner-spin-button,
        #dilSbidRuleModal input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
        #dilSbidRuleModal input[type=number] { -moz-appearance: textfield; appearance: textfield; }
        #dil-sbid-table .form-control { border-radius: 0.55rem; }
        #dilSbidRuleModal.is-off #dil-sbid-table { opacity: 0.55; }
        .dil-sbid-switch .form-check-input { width: 2.4em; height: 1.25em; cursor: pointer; }
        .dil-sbid-dot { display: inline-block; width: 9px; height: 9px; border-radius: 50%; margin-left: 6px; vertical-align: middle; }
        .dil-sbid-badge { display: inline-block; min-width: 72px; text-align: center; font-weight: 700; font-size: 12px; padding: 4px 8px; border-radius: 8px; }
        .dil-sbid-es { background: #cff4fc; color: #055160; }
        .dil-sbid-off { background: #f8d7da; color: #842029; }
        #dilSbidRuleModal .dsb-cap { display: flex; align-items: center; gap: 6px; white-space: nowrap; color: #334155; font-size: 12px; font-weight: 600; }
        #dilSbidRuleModal .dsb-cap-input { width: 56px; height: 28px; padding: 0 4px; font-weight: 700; text-align: center; }
    </style>
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="dilSbidRuleModalLabel">
                    <i class="fas fa-percent me-2 text-primary"></i>Dil vs SBid
                    <span class="badge bg-secondary ms-2" style="font-size:11px;">{{ $dilSbidAccount }} only</span>
                </h5>
                <div class="dsb-cap ms-auto me-2" title="The final S Bid never goes below Min or above Max. eBay only accepts 2–100.">
                    <label class="mb-0 small" for="dil-sbid-cap-min">Min</label>
                    <input type="number" min="2" max="100" step="0.1" class="form-control form-control-sm dsb-cap-input" id="dil-sbid-cap-min" value="2">
                    <label class="mb-0 small" for="dil-sbid-cap-max">Max</label>
                    <input type="number" min="2" max="100" step="0.1" class="form-control form-control-sm dsb-cap-input" id="dil-sbid-cap-max" value="100">
                </div>
                <div class="form-check form-switch dil-sbid-switch mb-0 me-3">
                    <input class="form-check-input" type="checkbox" role="switch" id="dil-sbid-enabled">
                    <label class="form-check-label small fw-semibold" for="dil-sbid-enabled" id="dil-sbid-enabled-label">Off</label>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small mb-2" id="dil-sbid-mode-note">Off. S Bid is not changed.</p>
                <ul class="small text-muted mb-3 ps-3">
                    <li>Count is unique SKUs. Dil is CP Master Dil: round(OV L30 sold ÷ Inventory × 100). Inventory 0 and missing data are not counted. First matching slab wins.</li>
                    <li><strong>0–0</strong> is SKUs with <strong>OV L30 sold = 0</strong>.</li>
                    <li>Every slab, including 0–0, uses the <strong>S Bid %</strong> you type on that row.</li>
                    <li><strong>CVR overlay</strong> then adjusts that S Bid, same as Sprc Dil. Down = CVR is below the threshold and the arrow is down (CVR L30 under CVR L60). Up = CVR is above the threshold and the arrow is up. Flat arrows are left alone.</li>
                    <li><strong>L30 View overlay</strong> then adjusts that S Bid. Down = L30 views below the threshold and the L30 View arrow is down (L7 pace under L30 pace). Up = L30 views above the threshold and the arrow is up. Adj starts at 0.</li>
                    <li><strong>Min / Max</strong> then keep a real S Bid inside that range. They cannot go outside 2 and 100, which eBay accepts. A SKU with no S Bid stays blank.</li>
                    <li>Saved for {{ $dilSbidAccount }} only. eBay, eBay 2, and eBay 3 each keep their own slabs.</li>
                    <li>eBay 1 and eBay 2 push the new S Bid on their own when Dil or CVR changes the bid. The switch must be On. eBay 3 stays manual.</li>
                </ul>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0" id="dil-sbid-table">
                        <thead>
                            <tr>
                                <th class="text-center" style="width:110px;">From</th>
                                <th class="text-center" style="width:110px;">To</th>
                                <th class="text-center" style="width:90px;" title="Unique SKUs. 0–0 is OV L30 sold = 0 with Inventory. Each ad for that SKU uses the same slab.">Count</th>
                                <th class="text-end" style="width:140px;">S Bid</th>
                                <th style="width:36px;"></th>
                            </tr>
                        </thead>
                        <tbody id="dil-sbid-tbody"></tbody>
                    </table>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="dil-sbid-add-btn">
                    <i class="fas fa-plus me-1"></i>Add slab
                </button>
                <div class="fw-semibold small mt-3 mb-1">CVR overlay — S Bid</div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0" id="dil-sbid-cvr-table">
                        <thead>
                            <tr>
                                <th>When</th>
                                <th class="text-center">CVR%</th>
                                <th class="text-end">Adj S Bid</th>
                                <th class="text-center" style="width:80px;" title="Unique SKUs. Down: CVR below the threshold and a down arrow. Up: CVR above the threshold and an up arrow.">Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Down</td>
                                <td class="text-center">
                                    &lt;
                                    <input type="number" min="0" step="0.1" class="form-control form-control-sm text-end dil-sbid-cvr-input" id="dil-sbid-cvr-down-lt" value="7">
                                </td>
                                <td class="text-end">
                                    <input type="number" step="1" class="form-control form-control-sm text-end dil-sbid-cvr-input" id="dil-sbid-cvr-down-adj" value="-10">
                                </td>
                                <td class="text-center fw-semibold"><span id="dil-sbid-cvr-down-count">0</span></td>
                            </tr>
                            <tr>
                                <td>Up</td>
                                <td class="text-center">
                                    &gt;
                                    <input type="number" min="0" step="0.1" class="form-control form-control-sm text-end dil-sbid-cvr-input" id="dil-sbid-cvr-up-gt" value="10">
                                </td>
                                <td class="text-end">
                                    <input type="number" step="1" class="form-control form-control-sm text-end dil-sbid-cvr-input" id="dil-sbid-cvr-up-adj" value="10">
                                </td>
                                <td class="text-center fw-semibold"><span id="dil-sbid-cvr-up-count">0</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="fw-semibold small mt-3 mb-1">L30 View overlay — S Bid</div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0" id="dil-sbid-view-table">
                        <thead>
                            <tr>
                                <th>When</th>
                                <th class="text-center">Views</th>
                                <th class="text-end">Adj S Bid</th>
                                <th class="text-center" style="width:80px;" title="Unique SKUs. Down: L30 views below the threshold and a down arrow. Up: L30 views above the threshold and an up arrow.">Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Down</td>
                                <td class="text-center">
                                    &lt;
                                    <input type="number" min="0" step="1" class="form-control form-control-sm text-end dil-sbid-view-input" id="dil-sbid-view-down-lt" value="30">
                                </td>
                                <td class="text-end">
                                    <input type="number" step="1" class="form-control form-control-sm text-end dil-sbid-view-input" id="dil-sbid-view-down-adj" value="0">
                                </td>
                                <td class="text-center fw-semibold"><span id="dil-sbid-view-down-count">0</span></td>
                            </tr>
                            <tr>
                                <td>Up</td>
                                <td class="text-center">
                                    &gt;
                                    <input type="number" min="0" step="1" class="form-control form-control-sm text-end dil-sbid-view-input" id="dil-sbid-view-up-gt" value="30">
                                </td>
                                <td class="text-end">
                                    <input type="number" step="1" class="form-control form-control-sm text-end dil-sbid-view-input" id="dil-sbid-view-up-adj" value="0">
                                </td>
                                <td class="text-center fw-semibold"><span id="dil-sbid-view-up-count">0</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="small text-danger mb-0 mt-2 d-none" id="dil-sbid-err"></p>
            </div>
            <div class="modal-footer py-2 d-flex justify-content-between">
                <span class="small text-muted" id="dil-sbid-status"></span>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-warning text-dark dil-sbid-push-btn" title="Push the saved Dil vs SBid rule to listings on this page.">
                        <i class="fas fa-cloud-upload-alt me-1"></i>Push SBID
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="dil-sbid-apply-btn">
                        <i class="fas fa-save me-1"></i>Save and Apply
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@elseif(($part ?? 'modal') === 'button')
<button type="button" class="btn btn-sm btn-warning text-dark pricing-filter-item dil-sbid-push-btn"
    title="Push the saved Dil vs SBid rule to listings on this page. Same push as the morning and evening auto-push.">
    <i class="fas fa-cloud-upload-alt me-1"></i>Push SBID
</button>
@else
const DIL_SBID_GET_URL = @json($getUrl);
const DIL_SBID_SAVE_URL = @json($saveUrl);
const DIL_SBID_APPLY_URL = @json($applyUrl);
const DIL_SBID_DEFAULTS = @json(\App\Support\DilVsSbidRule::defaultSlabs());
const DIL_SBID_CVR_DEFAULTS = @json(\App\Support\DilVsSbidRule::defaultCvr());
const DIL_SBID_VIEW_DEFAULTS = @json(\App\Support\DilVsSbidRule::defaultViewOver());
const DIL_SBID_CAP_DEFAULTS = @json(\App\Support\DilVsSbidRule::defaultCap());
const DIL_SBID_COLORS = ['#6f42c1','#3b82f6','#14b8a6','#22c55e','#84cc16','#eab308','#f59e0b','#ea580c','#dc3545','#e83e8c','#7c3aed','#0ea5e9'];
let currentDilSbidSlabs = DIL_SBID_DEFAULTS.map(function(s) { return Object.assign({}, s); });
let currentDilSbidCvr = Object.assign({}, DIL_SBID_CVR_DEFAULTS);
let currentDilSbidView = Object.assign({}, DIL_SBID_VIEW_DEFAULTS);
let currentDilSbidCap = Object.assign({}, DIL_SBID_CAP_DEFAULTS);
var dilSbidEnabled = false;
let dilSbidSaveTimer = null;

// Extended mode (eBay 1 analytics): Views, CVR, eBay Sold and Std NPFT % tables add to the Dil bid.
const DIL_SBID_EXT = @json((bool) ($extended ?? false));
const DIL_SBID_TABLE_DEFAULTS = @json(\App\Support\DilVsSbidRule::defaultTables());
const DIL_SBID_EXT_KEYS = ['views', 'cvr', 'sold', 'npft'];
const DIL_SBID_EXT_LABELS = { views: 'Views', cvr: 'CVR', sold: 'eBay Sold', npft: 'Std NPFT' };
const DIL_SBID_EXT_STEP = { views: 100, cvr: 5, sold: 10, npft: 10 };
const DIL_SBID_SUM_COLORS = { dil: '#6f42c1', views: '#3b82f6', cvr: '#14b8a6', sold: '#f59e0b', npft: '#e83e8c', sum: '#0d6efd' };
function dilSbidCloneTables(src) {
    const out = {};
    DIL_SBID_EXT_KEYS.forEach(function(key) {
        const rows = (src && Array.isArray(src[key])) ? src[key] : DIL_SBID_TABLE_DEFAULTS[key];
        out[key] = rows.map(function(r) {
            const bid = parseFloat(r.bid);
            return { min: parseFloat(r.min) || 0, max: parseFloat(r.max) || 0, bid: isFinite(bid) ? bid : 0 };
        });
    });
    return out;
}
let currentDilSbidTables = dilSbidCloneTables(null);
let dilSbidRepaintTimer = null;
const dilSbidCharts = {};

function dilSbidRefreshGrid() {
    if (typeof table !== 'undefined' && table && table.redraw) table.redraw(true);
    if (typeof ebayPaintForcePush === 'function') ebayPaintForcePush();
}

function campaignSbid(row) {
    return dilSbidOfRow(row);
}
function dilSbidPaintMode() {
    const on = !!dilSbidEnabled;
    const box = document.getElementById('dil-sbid-enabled');
    const label = document.getElementById('dil-sbid-enabled-label');
    const note = document.getElementById('dil-sbid-mode-note');
    const modal = document.getElementById('dilSbidRuleModal');
    if (box) box.checked = on;
    if (label) label.textContent = on ? 'On' : 'Off';
    if (note) {
        note.textContent = on
            ? (DIL_SBID_EXT
                ? 'On. S Bid is the sum of Dil, Views, CVR, eBay Sold and Std NPFT %, then CVR up / down, then L30 View up / down, then the Min / Max cap.'
                : 'On. S Bid uses these Dil slabs, then the CVR overlay, then the L30 View overlay, then the Min / Max cap.')
            : 'Off. S Bid is not changed.';
        note.className = on ? 'small mb-2 text-success' : 'small mb-2 text-muted';
    }
    if (modal) modal.classList.toggle('is-off', !on);
}

function dilSbidMode(i) {
    return 'dynamic';
}
function dilSbidRound(n) {
    return Math.round((Number(n) || 0) * 100) / 100;
}
function dilSbidContains(dil, slab, prevMax, openTop) {
    const min = parseFloat(slab.min);
    const max = openTop ? Infinity : parseFloat(slab.max);
    if (!isFinite(min) || (!openTop && !isFinite(max))) return false;
    if (Math.abs(min) < 1e-7 && Math.abs(max) < 1e-7) return Math.abs(dil) < 1e-7;
    const sharesEdge = prevMax !== null && Math.abs(min - prevMax) < 0.0001;
    const loOk = sharesEdge ? dil > min : dil >= min;
    return loOk && dil <= max;
}
function dilSbidRows() {
    try {
        if (typeof allTableData !== 'undefined' && Array.isArray(allTableData) && allTableData.length) return allTableData;
        if (typeof table !== 'undefined' && table && typeof table.getData === 'function') return table.getData() || [];
    } catch (e) {}
    return [];
}
function dilSbidSkuKey(row) {
    if (!row) return '';
    if (row.sku_matched != null && row.sku_matched !== '') {
        if (!(row.sku_matched == 1 || row.sku_matched === true || row.sku_matched === '1')) return '';
        const sku = String(row.resolved_sku || row.sku || '').trim();
        if (!sku || sku.toUpperCase().indexOf('PARENT') !== -1) return '';
        return sku.toUpperCase();
    }
    if (typeof chPromoIsChildRow === 'function' && !chPromoIsChildRow(row)) return '';
    const child = String((typeof chPromoSku === 'function' ? chPromoSku(row) : (row['(Child) sku'] || row.sku || '')) || '').trim();
    if (!child || child.toUpperCase().indexOf('PARENT') !== -1) return '';
    return child.toUpperCase();
}
function dilSbidMetric(row) {
    if (row && row.sku_matched != null && row.sku_matched !== '') {
        const dil = (typeof dilValue === 'function') ? dilValue(row) : null;
        if (dil === null || !isFinite(dil)) return null;
        const raw = row.shopify_qty;
        if (raw === null || raw === undefined || raw === '') return dil;
        const ovl = Number(raw);
        if (!isFinite(ovl)) return dil;
        if (ovl === 0) return 0;
        if (dil === 0) {
            const inv = Number(row.shopify_inv);
            return inv > 0 ? (ovl / inv) * 100 : null;
        }
        return dil;
    }
    if (typeof chPromoShopifyInv === 'function' && typeof chPromoOvL30 === 'function') {
        const inv = chPromoShopifyInv(row);
        if (!(inv > 0)) return null;
        const ovl = chPromoOvL30(row);
        if (!(ovl > 0)) return 0;
        // Same Dil as the raw campaign page: round(OV L30 / Inv × 100). A sale that
        // rounds to 0% stays on the real ratio so it is not treated as 0 sold.
        const rounded = Math.round((ovl / inv) * 100);
        if (rounded === 0) return (ovl / inv) * 100;
        return rounded;
    }
    return null;
}
function dilSbidCvrNow() {
    const num = function(id, fallback) {
        const el = document.getElementById(id);
        const n = el ? parseFloat(el.value) : NaN;
        return isFinite(n) ? n : fallback;
    };
    const prev = currentDilSbidCvr || {};
    let downMore = Array.isArray(prev.down_more) ? prev.down_more : [];
    let upMore = Array.isArray(prev.up_more) ? prev.up_more : [];
    if (DIL_SBID_EXT) {
        // Extra ranges live in the table. It is the source while the modal is built.
        const readMore = function(dir, edgeKey) {
            const out = [];
            document.querySelectorAll('#dil-sbid-cvr-table tr.dil-sbid-cvr-x[data-dir="' + dir + '"]').forEach(function(tr) {
                const edge = parseFloat(tr.querySelector('.dil-sbid-cvr-x-edge').value);
                const adj = parseFloat(tr.querySelector('.dil-sbid-cvr-x-adj').value);
                const item = { adj: isFinite(adj) ? adj : 0 };
                item[edgeKey] = isFinite(edge) ? Math.max(0, edge) : 0;
                out.push(item);
            });
            return out;
        };
        downMore = readMore('down', 'lt');
        upMore = readMore('up', 'gt');
    }
    currentDilSbidCvr = {
        down_lt: Math.max(0, num('dil-sbid-cvr-down-lt', DIL_SBID_CVR_DEFAULTS.down_lt)),
        down_adj: num('dil-sbid-cvr-down-adj', DIL_SBID_CVR_DEFAULTS.down_adj),
        up_gt: Math.max(0, num('dil-sbid-cvr-up-gt', DIL_SBID_CVR_DEFAULTS.up_gt)),
        up_adj: num('dil-sbid-cvr-up-adj', DIL_SBID_CVR_DEFAULTS.up_adj),
        down_more: downMore,
        up_more: upMore
    };
    return currentDilSbidCvr;
}
function dilSbidPaintCvr() {
    const cfg = currentDilSbidCvr || DIL_SBID_CVR_DEFAULTS;
    const set = function(id, value) {
        const el = document.getElementById(id);
        if (el) el.value = value;
    };
    set('dil-sbid-cvr-down-lt', cfg.down_lt);
    set('dil-sbid-cvr-down-adj', cfg.down_adj);
    set('dil-sbid-cvr-up-gt', cfg.up_gt);
    set('dil-sbid-cvr-up-adj', cfg.up_adj);
    if (DIL_SBID_EXT) dilSbidCvrRenderMore();
}
/**
 * Down / Up ranges in match order. Several Down ranges: the lowest threshold CVR is under wins.
 * Several Up ranges: the highest threshold CVR is over wins. id -1 is the main row.
 */
function dilSbidCvrRules(cfg) {
    const down = [{ edge: parseFloat(cfg.down_lt), adj: parseFloat(cfg.down_adj) || 0, id: -1 }];
    (Array.isArray(cfg.down_more) ? cfg.down_more : []).forEach(function(r, i) {
        down.push({ edge: parseFloat(r.lt), adj: parseFloat(r.adj) || 0, id: i });
    });
    const up = [{ edge: parseFloat(cfg.up_gt), adj: parseFloat(cfg.up_adj) || 0, id: -1 }];
    (Array.isArray(cfg.up_more) ? cfg.up_more : []).forEach(function(r, i) {
        up.push({ edge: parseFloat(r.gt), adj: parseFloat(r.adj) || 0, id: i });
    });
    return {
        down: down.filter(function(r) { return isFinite(r.edge); }).sort(function(a, b) { return a.edge - b.edge; }),
        up: up.filter(function(r) { return isFinite(r.edge); }).sort(function(a, b) { return b.edge - a.edge; })
    };
}
/** The Down or Up range this CVR falls in, or null. */
function dilSbidCvrHit(parts, cfg) {
    if (!parts) return null;
    const trend = dilSbidCvrTrend(parts);
    const rules = dilSbidCvrRules(cfg);
    if (trend === 'down') {
        for (let i = 0; i < rules.down.length; i++) {
            if (parts.cvr < rules.down[i].edge) return { dir: 'down', rule: rules.down[i] };
        }
    } else if (trend === 'up') {
        for (let i = 0; i < rules.up.length; i++) {
            if (parts.cvr > rules.up[i].edge) return { dir: 'up', rule: rules.up[i] };
        }
    }
    return null;
}
function dilSbidCvrMoreRow(dir, idx, edge, adj) {
    const sign = dir === 'down' ? '&lt;' : '&gt;';
    return '<tr class="dil-sbid-cvr-x" data-dir="' + dir + '" data-idx="' + idx + '">'
        + '<td>' + (dir === 'down' ? 'Down' : 'Up') + ' <button type="button" class="btn btn-sm btn-outline-danger dsb-del dil-sbid-cvr-x-del" title="Remove range">&times;</button></td>'
        + '<td class="text-center">' + sign + ' <input type="number" min="0" step="0.1" class="form-control form-control-sm text-end dil-sbid-cvr-input dil-sbid-cvr-x-edge" value="' + dilSbidEsc(edge) + '"></td>'
        + '<td class="text-end"><input type="number" step="1" class="form-control form-control-sm text-end dil-sbid-cvr-input dil-sbid-cvr-x-adj" value="' + dilSbidEsc(adj) + '"></td>'
        + '<td class="dsb-count"><span class="dil-sbid-cvr-x-count">0</span></td>'
        + '</tr>';
}
/** Rebuild the extra Down / Up rows under their main row. */
function dilSbidCvrRenderMore() {
    const table = document.getElementById('dil-sbid-cvr-table');
    if (!table) return;
    table.querySelectorAll('tr.dil-sbid-cvr-x').forEach(function(tr) { tr.remove(); });
    const cfg = currentDilSbidCvr || DIL_SBID_CVR_DEFAULTS;
    [['down', 'lt', cfg.down_more], ['up', 'gt', cfg.up_more]].forEach(function(spec) {
        const main = table.querySelector('tr[data-cvr-dir="' + spec[0] + '"]');
        if (!main) return;
        let anchor = main;
        (Array.isArray(spec[2]) ? spec[2] : []).forEach(function(item, i) {
            anchor.insertAdjacentHTML('afterend', dilSbidCvrMoreRow(spec[0], i, item[spec[1]], item.adj));
            anchor = anchor.nextElementSibling;
        });
    });
}
function dilSbidCvrAddMore(dir) {
    const cfg = dilSbidCvrNow();
    if (dir === 'down') {
        const edges = [cfg.down_lt].concat(cfg.down_more.map(function(r) { return r.lt; }));
        cfg.down_more.push({ lt: Math.max(0, dilSbidRound(Math.min.apply(null, edges) - 3)), adj: cfg.down_adj });
    } else {
        const edges = [cfg.up_gt].concat(cfg.up_more.map(function(r) { return r.gt; }));
        cfg.up_more.push({ gt: dilSbidRound(Math.max.apply(null, edges) + 5), adj: cfg.up_adj });
    }
    dilSbidCvrRenderMore();
    dilSbidPaintCounts();
    dilSbidRefreshGrid();
    dilSbidScheduleSave();
}
function dilSbidCvrDeleteMore(tr) {
    if (!tr) return;
    tr.remove();
    dilSbidCvrNow();
    dilSbidCvrRenderMore();
    dilSbidPaintCounts();
    dilSbidRefreshGrid();
    dilSbidScheduleSave();
}
function dilSbidCvrParts(row) {
    const views = parseFloat(row && row.views) || 0;
    if (!(views > 0)) return null;
    // Analytics rows carry an orders overlay on eBay L30. S Bid matches the raw
    // campaign page, which uses ebay_metrics L30 and L60.
    if (row && row.metric_ebay_l30 != null && row.metric_ebay_l30 !== '') {
        const l30 = parseFloat(row.metric_ebay_l30) || 0;
        const l60 = parseFloat(row.metric_ebay_l60 != null && row.metric_ebay_l60 !== '' ? row.metric_ebay_l60 : row.ebay_l60) || 0;
        return { cvr: (l30 / views) * 100, cvr60: (l60 / views) * 100 };
    }
    if (row && row['eBay L30'] != null) {
        const cvr = (row.SCVR != null && row.SCVR !== '') ? parseFloat(row.SCVR) : ((parseFloat(row['eBay L30']) || 0) / views) * 100;
        const cvr60 = (row.CVR_60 != null && row.CVR_60 !== '') ? parseFloat(row.CVR_60) : ((parseFloat(row['eBay L60']) || 0) / views) * 100;
        return { cvr: isFinite(cvr) ? cvr : 0, cvr60: isFinite(cvr60) ? cvr60 : 0 };
    }
    const l30 = parseFloat(row && row.ebay_l30) || 0;
    const l60 = parseFloat(row && row.ebay_l60) || 0;
    return { cvr: (l30 / views) * 100, cvr60: (l60 / views) * 100 };
}
function dilSbidCvrTrend(parts) {
    if (!parts) return 'flat';
    const tol = 0.1;
    if (parts.cvr === 0 || parts.cvr < parts.cvr60 - tol) return 'down';
    if (parts.cvr > parts.cvr60 + tol) return 'up';
    return 'flat';
}
function dilSbidApplyCvr(bid, row) {
    const cfg = currentDilSbidCvr || DIL_SBID_CVR_DEFAULTS;
    const parts = dilSbidCvrParts(row);
    const trend = dilSbidCvrTrend(parts);
    let adj = 0;
    let why = '';
    const hit = dilSbidCvrHit(parts, cfg);
    if (hit && hit.dir === 'down') {
        adj = hit.rule.adj;
        why = 'CVR Down < ' + hit.rule.edge + '% and down arrow';
    } else if (hit && hit.dir === 'up') {
        adj = hit.rule.adj;
        why = 'CVR Up > ' + hit.rule.edge + '% and up arrow';
    }
    let next = dilSbidRound(bid + adj);
    if (next < 0) next = 0;
    return { bid: next, adj: adj, why: why };
}
function dilSbidViewNow() {
    const num = function(id, fallback) {
        const el = document.getElementById(id);
        const n = el ? parseFloat(el.value) : NaN;
        return isFinite(n) ? n : fallback;
    };
    const prev = currentDilSbidView || {};
    let downMore = Array.isArray(prev.down_more) ? prev.down_more : [];
    let upMore = Array.isArray(prev.up_more) ? prev.up_more : [];
    if (DIL_SBID_EXT) {
        const readMore = function(dir, edgeKey) {
            const out = [];
            document.querySelectorAll('#dil-sbid-view-table tr.dil-sbid-view-x[data-dir="' + dir + '"]').forEach(function(tr) {
                const edge = parseFloat(tr.querySelector('.dil-sbid-view-x-edge').value);
                const adj = parseFloat(tr.querySelector('.dil-sbid-view-x-adj').value);
                const item = { adj: isFinite(adj) ? adj : 0 };
                item[edgeKey] = isFinite(edge) ? Math.max(0, edge) : 0;
                out.push(item);
            });
            return out;
        };
        downMore = readMore('down', 'lt');
        upMore = readMore('up', 'gt');
    }
    currentDilSbidView = {
        down_lt: Math.max(0, num('dil-sbid-view-down-lt', DIL_SBID_VIEW_DEFAULTS.down_lt)),
        down_adj: num('dil-sbid-view-down-adj', DIL_SBID_VIEW_DEFAULTS.down_adj),
        up_gt: Math.max(0, num('dil-sbid-view-up-gt', DIL_SBID_VIEW_DEFAULTS.up_gt)),
        up_adj: num('dil-sbid-view-up-adj', DIL_SBID_VIEW_DEFAULTS.up_adj),
        down_more: downMore,
        up_more: upMore
    };
    return currentDilSbidView;
}
function dilSbidPaintView() {
    const cfg = currentDilSbidView || DIL_SBID_VIEW_DEFAULTS;
    const set = function(id, value) {
        const el = document.getElementById(id);
        if (el) el.value = value;
    };
    set('dil-sbid-view-down-lt', cfg.down_lt);
    set('dil-sbid-view-down-adj', cfg.down_adj);
    set('dil-sbid-view-up-gt', cfg.up_gt);
    set('dil-sbid-view-up-adj', cfg.up_adj);
    if (DIL_SBID_EXT) dilSbidViewRenderMore();
}
function dilSbidViewParts(row) {
    const views = parseFloat(row && row.views) || 0;
    const l7 = parseFloat(row && row.l7_views) || 0;
    return { views: views, l7: l7 };
}
function dilSbidViewTrend(parts) {
    if (!parts) return 'flat';
    const l30Pace = parts.views / 30;
    const l7Pace = parts.l7 / 7;
    const tol = Math.max(0.05, l30Pace * 0.05);
    if (l7Pace > l30Pace + tol) return 'up';
    if (l7Pace < l30Pace - tol) return 'down';
    return 'flat';
}
function dilSbidViewRules(cfg) {
    const down = [{ edge: parseFloat(cfg.down_lt), adj: parseFloat(cfg.down_adj) || 0, id: -1 }];
    (Array.isArray(cfg.down_more) ? cfg.down_more : []).forEach(function(r, i) {
        down.push({ edge: parseFloat(r.lt), adj: parseFloat(r.adj) || 0, id: i });
    });
    const up = [{ edge: parseFloat(cfg.up_gt), adj: parseFloat(cfg.up_adj) || 0, id: -1 }];
    (Array.isArray(cfg.up_more) ? cfg.up_more : []).forEach(function(r, i) {
        up.push({ edge: parseFloat(r.gt), adj: parseFloat(r.adj) || 0, id: i });
    });
    return {
        down: down.filter(function(r) { return isFinite(r.edge); }).sort(function(a, b) { return a.edge - b.edge; }),
        up: up.filter(function(r) { return isFinite(r.edge); }).sort(function(a, b) { return b.edge - a.edge; })
    };
}
function dilSbidViewHit(parts, cfg) {
    if (!parts) return null;
    const trend = dilSbidViewTrend(parts);
    const rules = dilSbidViewRules(cfg);
    if (trend === 'down') {
        for (let i = 0; i < rules.down.length; i++) {
            if (parts.views < rules.down[i].edge) return { dir: 'down', rule: rules.down[i] };
        }
    } else if (trend === 'up') {
        for (let i = 0; i < rules.up.length; i++) {
            if (parts.views > rules.up[i].edge) return { dir: 'up', rule: rules.up[i] };
        }
    }
    return null;
}
function dilSbidViewMoreRow(dir, idx, edge, adj) {
    const sign = dir === 'down' ? '&lt;' : '&gt;';
    return '<tr class="dil-sbid-view-x" data-dir="' + dir + '" data-idx="' + idx + '">'
        + '<td>' + (dir === 'down' ? 'Down' : 'Up') + ' <button type="button" class="btn btn-sm btn-outline-danger dsb-del dil-sbid-view-x-del" title="Remove range">&times;</button></td>'
        + '<td class="text-center">' + sign + ' <input type="number" min="0" step="1" class="form-control form-control-sm text-end dil-sbid-view-input dil-sbid-view-x-edge" value="' + dilSbidEsc(edge) + '"></td>'
        + '<td class="text-end"><input type="number" step="1" class="form-control form-control-sm text-end dil-sbid-view-input dil-sbid-view-x-adj" value="' + dilSbidEsc(adj) + '"></td>'
        + '<td class="dsb-count"><span class="dil-sbid-view-x-count">0</span></td>'
        + '</tr>';
}
function dilSbidViewRenderMore() {
    const table = document.getElementById('dil-sbid-view-table');
    if (!table) return;
    table.querySelectorAll('tr.dil-sbid-view-x').forEach(function(tr) { tr.remove(); });
    const cfg = currentDilSbidView || DIL_SBID_VIEW_DEFAULTS;
    [['down', 'lt', cfg.down_more], ['up', 'gt', cfg.up_more]].forEach(function(spec) {
        const main = table.querySelector('tr[data-view-dir="' + spec[0] + '"]');
        if (!main) return;
        let anchor = main;
        (Array.isArray(spec[2]) ? spec[2] : []).forEach(function(item, i) {
            anchor.insertAdjacentHTML('afterend', dilSbidViewMoreRow(spec[0], i, item[spec[1]], item.adj));
            anchor = anchor.nextElementSibling;
        });
    });
}
function dilSbidViewAddMore(dir) {
    const cfg = dilSbidViewNow();
    if (dir === 'down') {
        const edges = [cfg.down_lt].concat(cfg.down_more.map(function(r) { return r.lt; }));
        cfg.down_more.push({ lt: Math.max(0, dilSbidRound(Math.min.apply(null, edges) - 10)), adj: cfg.down_adj });
    } else {
        const edges = [cfg.up_gt].concat(cfg.up_more.map(function(r) { return r.gt; }));
        cfg.up_more.push({ gt: dilSbidRound(Math.max.apply(null, edges) + 30), adj: cfg.up_adj });
    }
    dilSbidViewRenderMore();
    dilSbidPaintCounts();
    dilSbidRefreshGrid();
    dilSbidScheduleSave();
}
function dilSbidViewDeleteMore(tr) {
    if (!tr) return;
    tr.remove();
    dilSbidViewNow();
    dilSbidViewRenderMore();
    dilSbidPaintCounts();
    dilSbidRefreshGrid();
    dilSbidScheduleSave();
}
function dilSbidApplyView(bid, row) {
    const cfg = currentDilSbidView || DIL_SBID_VIEW_DEFAULTS;
    const parts = dilSbidViewParts(row);
    let adj = 0;
    let why = '';
    const hit = dilSbidViewHit(parts, cfg);
    if (hit && hit.dir === 'down') {
        adj = hit.rule.adj;
        why = 'L30 View Down < ' + hit.rule.edge + ' and down arrow';
    } else if (hit && hit.dir === 'up') {
        adj = hit.rule.adj;
        why = 'L30 View Up > ' + hit.rule.edge + ' and up arrow';
    }
    let next = dilSbidRound(bid + adj);
    if (next < 0) next = 0;
    return { bid: next, adj: adj, why: why };
}
function dilSbidBoundCap(n, fallback) {
    if (!isFinite(n)) return fallback;
    n = Math.round(n * 10) / 10;
    if (n < 2) n = 2;
    if (n > 100) n = 100;
    return n;
}
function dilSbidCapNow(writeBack) {
    const minEl = document.getElementById('dil-sbid-cap-min');
    const maxEl = document.getElementById('dil-sbid-cap-max');
    const fallbackMin = (currentDilSbidCap && currentDilSbidCap.min) || DIL_SBID_CAP_DEFAULTS.min;
    const fallbackMax = (currentDilSbidCap && currentDilSbidCap.max) || DIL_SBID_CAP_DEFAULTS.max;
    let min = dilSbidBoundCap(minEl ? parseFloat(minEl.value) : NaN, fallbackMin);
    let max = dilSbidBoundCap(maxEl ? parseFloat(maxEl.value) : NaN, fallbackMax);
    if (min > max) { const swap = min; min = max; max = swap; }
    currentDilSbidCap = { min: min, max: max };
    if (writeBack) {
        if (minEl) minEl.value = min;
        if (maxEl) maxEl.value = max;
    }
    return currentDilSbidCap;
}
function dilSbidPaintCap() {
    const cap = currentDilSbidCap || DIL_SBID_CAP_DEFAULTS;
    const minEl = document.getElementById('dil-sbid-cap-min');
    const maxEl = document.getElementById('dil-sbid-cap-max');
    if (minEl) minEl.value = cap.min;
    if (maxEl) maxEl.value = cap.max;
}
/** Keep a real S Bid inside Min / Max. 0 stays 0 so a missing bid is not invented. */
function dilSbidApplyCap(bid) {
    if (!(bid > 0)) return 0;
    const cap = currentDilSbidCap || DIL_SBID_CAP_DEFAULTS;
    let next = Math.round((Number(bid) || 0) * 10) / 10;
    if (next < cap.min) next = cap.min;
    if (next > cap.max) next = cap.max;
    if (next < 2) next = 2;
    if (next > 100) next = 100;
    return next;
}
/** Dil slab part of the S Bid: the S Bid typed on the slab Dil falls in. */
function dilSbidDilPart(row, dil) {
    let prevMax = null;
    for (let i = 0; i < currentDilSbidSlabs.length; i++) {
        const slab = currentDilSbidSlabs[i];
        const openTop = i === currentDilSbidSlabs.length - 1;
        if (dilSbidContains(dil, slab, prevMax, openTop)) {
            const typed = parseFloat(slab.bid);
            if (!(isFinite(typed) && typed > 0)) {
                const range = dilSbidRound(parseFloat(slab.min)) + '–' + (openTop ? '∞' : dilSbidRound(parseFloat(slab.max)));
                return { bid: 0, color: '#6c757d', title: '', short: 'Slab 0', miss: 'Dil ' + dilSbidRound(dil) + '% is in the Dil slab ' + range + ', which has no S Bid typed. Type an S Bid % on that slab.' };
            }
            return { bid: typed, color: '#0d6efd', title: 'Dil ' + dilSbidRound(dil) + '% → S Bid ' + typed + '%', miss: '' };
        }
        prevMax = parseFloat(slab.max);
    }
    return { bid: 0, color: '#6c757d', title: '', short: 'No slab', miss: 'Dil ' + dilSbidRound(dil) + '% is not inside any Dil slab. Add a slab that covers it.' };
}
/** Index of the first range holding value. The last range is open at the top. -1 when none. */
function dilSbidIndexIn(slabs, value) {
    if (value === null || value === undefined || !isFinite(value)) return -1;
    let prevMax = null;
    for (let i = 0; i < slabs.length; i++) {
        if (dilSbidContains(value, slabs[i], prevMax, i === slabs.length - 1)) return i;
        prevMax = parseFloat(slabs[i].max);
    }
    return -1;
}
/** Std NPFT % as on /lmp-overall: ((Std × 0.70 − ship − LP) / Std) × 100, 2 decimals. Null without a Std Prc. */
function dilSbidStdNpft(row) {
    const std = parseFloat(row && row.STANDARD_PRICE);
    if (!(std > 0)) return null;
    const lp = parseFloat(row.LP_productmaster);
    const ship = parseFloat(row.Ship_productmaster) || 0;
    const lpVal = (isFinite(lp) && lp > 0) ? lp : 0;
    return Math.round((((std * 0.70 - ship - lpVal) / std) * 100) * 100) / 100;
}
/** Value a row is looked up by in each extra table. Null = no data for that table. */
function dilSbidExtValue(key, row) {
    if (!row) return null;
    if (key === 'views') {
        const v = parseFloat(row.views);
        return isFinite(v) ? v : 0;
    }
    if (key === 'cvr') {
        const parts = dilSbidCvrParts(row);
        return parts ? parts.cvr : null;
    }
    if (key === 'sold') {
        // ebay_metrics L30, same as the server push (the E L30 column can prefer live orders).
        const raw = (row.metric_ebay_l30 != null && row.metric_ebay_l30 !== '') ? row.metric_ebay_l30
            : (row['eBay L30'] != null ? row['eBay L30'] : row.ebay_l30);
        const v = parseFloat(raw);
        return isFinite(v) ? v : 0;
    }
    if (key === 'npft') return dilSbidStdNpft(row);
    return null;
}
/** What each extra table adds for this row: { views: {value, idx, bid}, ... }. */
function dilSbidExtBids(row) {
    const out = {};
    DIL_SBID_EXT_KEYS.forEach(function(key) {
        const slabs = currentDilSbidTables[key] || [];
        const value = dilSbidExtValue(key, row);
        const idx = dilSbidIndexIn(slabs, value);
        const bid = idx >= 0 ? (parseFloat(slabs[idx].bid) || 0) : 0;
        out[key] = { value: value, idx: idx, bid: bid };
    });
    return out;
}
function dilSbidOfRow(row) {
    const dil = dilSbidMetric(row);
    if (dil === null || !isFinite(dil)) {
        if (!dilSbidSkuKey(row)) {
            return { bid: 0, color: '#6c757d', skip: true, off: false, short: 'No SKU', title: 'No S Bid: this row is a parent or has no matched child SKU.' };
        }
        const inv = Number(row && row.shopify_inv);
        if (row && row.shopify_inv !== undefined && row.shopify_inv !== null && row.shopify_inv !== '' && isFinite(inv) && inv <= 0) {
            return { bid: 0, color: '#6c757d', skip: true, off: false, short: 'Inv 0', title: 'No S Bid: Shopify inventory is 0, so Dil (OV L30 ÷ Inventory) cannot be worked out.' };
        }
        return { bid: 0, color: '#6c757d', skip: true, off: false, short: 'No Dil', title: 'No S Bid: CP Master Dil is missing for this SKU (no inventory or OV L30 data).' };
    }
    const part = dilSbidDilPart(row, dil);
    const extra = DIL_SBID_EXT ? dilSbidExtBids(row) : null;
    let extraSum = 0;
    const bits = [];
    if (extra) {
        DIL_SBID_EXT_KEYS.forEach(function(key) {
            const b = extra[key].bid;
            if (!b) return;
            extraSum += b;
            bits.push(DIL_SBID_EXT_LABELS[key] + ' ' + (b > 0 ? '+' : '') + dilSbidRound(b));
        });
    }
    const base = dilSbidRound(part.bid + extraSum);
    if (!(base > 0)) {
        const why = part.miss
            ? part.miss + (DIL_SBID_EXT ? ' Views, CVR, eBay Sold and Std NPFT % add ' + dilSbidRound(extraSum) + '.' : '')
            : 'Dil, Views, CVR, eBay Sold and Std NPFT % add up to ' + base + ' or less, so there is no S Bid.';
        return { bid: 0, color: '#6c757d', skip: true, off: false, short: part.short || 'Sum ≤ 0', title: 'No S Bid: ' + why };
    }
    let title = part.title;
    if (bits.length) {
        title = (part.bid > 0 ? part.title + ' ' : 'Dil 0 ') + bits.join(' ') + ' = ' + base + '%';
    }
    const adj = dilSbidApplyCvr(base, row);
    if (adj.why) {
        const sign = adj.adj > 0 ? '+' : '';
        title += ' ' + sign + adj.adj + ' (' + adj.why + ') → ' + adj.bid + '%';
    }
    const viewAdj = dilSbidApplyView(adj.bid, row);
    if (viewAdj.why) {
        const sign = viewAdj.adj > 0 ? '+' : '';
        title += ' ' + sign + viewAdj.adj + ' (' + viewAdj.why + ') → ' + viewAdj.bid + '%';
    }
    const capped = dilSbidApplyCap(viewAdj.bid);
    if (!(capped > 0)) {
        return { bid: 0, color: '#6c757d', skip: true, off: false, short: 'Sum ≤ 0', title: 'No S Bid: after the overlays the bid is 0 or below.' };
    }
    const tenths = Math.round(viewAdj.bid * 10) / 10;
    if (capped !== tenths) {
        const cap = currentDilSbidCap || DIL_SBID_CAP_DEFAULTS;
        title += ' → cap ' + capped + '% (min ' + cap.min + ' / max ' + cap.max + ')';
    }
    return { bid: capped, color: part.color, skip: false, off: false, title: title };
}
function dilSbidCounts() {
    const counts = currentDilSbidSlabs.map(function() { return 0; });
    const rows = dilSbidRows();
    const seen = {};
    rows.forEach(function(d) {
        const sku = dilSbidSkuKey(d);
        if (!sku || seen[sku]) return;
        const dil = dilSbidMetric(d);
        if (dil === null || !isFinite(dil)) return;
        seen[sku] = true;
        let prevMax = null;
        for (let i = 0; i < currentDilSbidSlabs.length; i++) {
            if (dilSbidContains(dil, currentDilSbidSlabs[i], prevMax, i === currentDilSbidSlabs.length - 1)) {
                counts[i]++;
                break;
            }
            prevMax = parseFloat(currentDilSbidSlabs[i].max);
        }
    });
    return counts;
}
function dilSbidCvrCounts() {
    // down / up: SKUs on the main row. downMore / upMore: SKUs on each extra range. A SKU counts once, on the range that wins.
    const counts = { down: 0, up: 0, downMore: [], upMore: [], downTotal: 0, upTotal: 0 };
    const cfg = currentDilSbidCvr || DIL_SBID_CVR_DEFAULTS;
    (cfg.down_more || []).forEach(function() { counts.downMore.push(0); });
    (cfg.up_more || []).forEach(function() { counts.upMore.push(0); });
    const rows = dilSbidRows();
    const seen = {};
    rows.forEach(function(d) {
        const sku = dilSbidSkuKey(d);
        if (!sku || seen[sku]) return;
        const parts = dilSbidCvrParts(d);
        if (!parts) return;
        seen[sku] = true;
        const hit = dilSbidCvrHit(parts, cfg);
        if (!hit) return;
        if (hit.dir === 'down') {
            counts.downTotal++;
            if (hit.rule.id < 0) counts.down++; else counts.downMore[hit.rule.id]++;
        } else {
            counts.upTotal++;
            if (hit.rule.id < 0) counts.up++; else counts.upMore[hit.rule.id]++;
        }
    });
    return counts;
}
function dilSbidViewCounts() {
    const counts = { down: 0, up: 0, downMore: [], upMore: [], downTotal: 0, upTotal: 0 };
    const cfg = currentDilSbidView || DIL_SBID_VIEW_DEFAULTS;
    (cfg.down_more || []).forEach(function() { counts.downMore.push(0); });
    (cfg.up_more || []).forEach(function() { counts.upMore.push(0); });
    const rows = dilSbidRows();
    const seen = {};
    rows.forEach(function(d) {
        const sku = dilSbidSkuKey(d);
        if (!sku || seen[sku]) return;
        seen[sku] = true;
        const hit = dilSbidViewHit(dilSbidViewParts(d), cfg);
        if (!hit) return;
        if (hit.dir === 'down') {
            counts.downTotal++;
            if (hit.rule.id < 0) counts.down++; else counts.downMore[hit.rule.id]++;
        } else {
            counts.upTotal++;
            if (hit.rule.id < 0) counts.up++; else counts.upMore[hit.rule.id]++;
        }
    });
    return counts;
}
function dilSbidRead() {
    const rows = [];
    document.querySelectorAll('#dil-sbid-tbody tr').forEach(function(tr, i) {
        const min = parseFloat(tr.querySelector('.dil-sbid-min').value);
        const max = parseFloat(tr.querySelector('.dil-sbid-max').value);
        const bidEl = tr.querySelector('.dil-sbid-bid');
        rows.push({
            min: isFinite(min) ? min : 0,
            max: isFinite(max) ? max : 0,
            mode: dilSbidMode(i),
            bid: bidEl ? parseFloat(bidEl.value) : null
        });
    });
    if (rows.length) currentDilSbidSlabs = rows;
    return currentDilSbidSlabs;
}
function renderDilSbidTable() {
    const tbody = document.getElementById('dil-sbid-tbody');
    if (!tbody) return;
    const counts = dilSbidCounts();
    tbody.innerHTML = '';
    currentDilSbidSlabs.forEach(function(slab, i) {
        const mode = dilSbidMode(i);
        const color = DIL_SBID_COLORS[i % DIL_SBID_COLORS.length];
        let bidCell = '';
        const bid = (slab.bid === null || slab.bid === undefined || slab.bid === '') ? '' : slab.bid;
        bidCell = '<input type="number" min="0" step="0.1" class="form-control form-control-sm text-end fw-semibold dsb-input dil-sbid-bid" value="' + bid + '" title="S Bid % for Dil in this slab">';
        const tr = document.createElement('tr');
        tr.innerHTML = ''
            + '<td><input type="number" step="0.1" class="form-control form-control-sm text-end dsb-input dil-sbid-min" value="' + slab.min + '"></td>'
            + '<td><input type="number" step="0.1" class="form-control form-control-sm text-end dsb-input dil-sbid-max" value="' + slab.max + '"></td>'
            + '<td class="text-center fw-semibold"><span class="dil-sbid-count">' + (counts[i] || 0) + '</span> <span class="dil-sbid-dot" style="background:' + color + '"></span></td>'
            + '<td class="text-end">' + bidCell + '</td>'
            + '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger py-0 px-1 dsb-del dil-sbid-del" data-idx="' + i + '" title="Remove slab">&times;</button></td>';
        tbody.appendChild(tr);
    });
    if (DIL_SBID_EXT) dilSbidExtRenderAll();
    dilSbidPaintCounts();
}

// ── Extended mode: Views / CVR / eBay Sold / Std NPFT % tables, charts, and the sum card ──
function dilSbidModalOpen() {
    const modal = document.getElementById('dilSbidRuleModal');
    return !!(modal && modal.classList.contains('show'));
}
function dilSbidEsc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function(ch) {
        return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch];
    });
}
function dilSbidRangeLabel(slab) {
    const a = parseFloat(slab.min);
    const b = parseFloat(slab.max);
    return a === b ? String(a) : (a + '–' + b);
}
/** Unique child SKUs that have a CP Master Dil (inventory > 0). Same set the Dil counts use. */
function dilSbidEligibleRows() {
    const out = [];
    const seen = {};
    dilSbidRows().forEach(function(d) {
        const sku = dilSbidSkuKey(d);
        if (!sku || seen[sku]) return;
        const dil = dilSbidMetric(d);
        if (dil === null || !isFinite(dil)) return;
        seen[sku] = true;
        out.push(d);
    });
    return out;
}
function dilSbidExtCounts(key, rows) {
    const slabs = currentDilSbidTables[key] || [];
    const counts = slabs.map(function() { return 0; });
    let outside = 0;
    rows.forEach(function(row) {
        const idx = dilSbidIndexIn(slabs, dilSbidExtValue(key, row));
        if (idx >= 0) counts[idx]++;
        else outside++;
    });
    return { counts: counts, outside: outside };
}
function dilSbidExtRender(key) {
    const tbody = document.getElementById('dil-sbid-x-tbody-' + key);
    if (!tbody) return;
    const slabs = currentDilSbidTables[key] || [];
    tbody.innerHTML = slabs.map(function(slab, i) {
        const color = DIL_SBID_COLORS[i % DIL_SBID_COLORS.length];
        return '<tr>'
            + '<td><input type="number" step="0.1" class="form-control form-control-sm text-end dsb-input dil-sbid-x-min" value="' + dilSbidEsc(slab.min) + '"></td>'
            + '<td><input type="number" step="0.1" class="form-control form-control-sm text-end dsb-input dil-sbid-x-max" value="' + dilSbidEsc(slab.max) + '"></td>'
            + '<td class="dsb-count"><span class="dil-sbid-x-count">0</span><span class="dil-sbid-dot" style="background:' + color + '"></span></td>'
            + '<td class="text-end"><input type="number" step="0.1" class="form-control form-control-sm text-end fw-semibold dsb-input dil-sbid-x-bid" value="' + dilSbidEsc(slab.bid) + '" title="S Bid % added when the SKU is in this range. Negative subtracts."></td>'
            + '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger dsb-del dil-sbid-x-del" data-table="' + key + '" data-idx="' + i + '" title="Remove range">&times;</button></td>'
            + '</tr>';
    }).join('');
}
function dilSbidExtRenderAll() {
    DIL_SBID_EXT_KEYS.forEach(function(key) {
        const tbody = document.getElementById('dil-sbid-x-tbody-' + key);
        if (!tbody) return;
        // Do not rebuild a table while someone is typing in it. Only counts change.
        if (tbody.contains(document.activeElement) && tbody.children.length === (currentDilSbidTables[key] || []).length) return;
        dilSbidExtRender(key);
    });
}
function dilSbidExtRead(key) {
    const tbody = document.getElementById('dil-sbid-x-tbody-' + key);
    if (!tbody) return;
    const rows = [];
    tbody.querySelectorAll('tr').forEach(function(tr) {
        const min = parseFloat(tr.querySelector('.dil-sbid-x-min').value);
        const max = parseFloat(tr.querySelector('.dil-sbid-x-max').value);
        const bid = parseFloat(tr.querySelector('.dil-sbid-x-bid').value);
        rows.push({ min: isFinite(min) ? min : 0, max: isFinite(max) ? max : 0, bid: isFinite(bid) ? bid : 0 });
    });
    if (rows.length) currentDilSbidTables[key] = rows;
}
function dilSbidExtAdd(key) {
    dilSbidExtRead(key);
    const slabs = currentDilSbidTables[key];
    const step = DIL_SBID_EXT_STEP[key] || 10;
    const last = slabs[slabs.length - 1];
    if (last && parseFloat(last.max) >= 9999) {
        // Split the open top range so the new range is reachable.
        const cut = dilSbidRound(parseFloat(last.min) + step);
        slabs.splice(slabs.length - 1, 0, { min: last.min, max: cut, bid: 0 });
        last.min = cut;
    } else {
        const top = last ? parseFloat(last.max) : 0;
        slabs.push({ min: top, max: dilSbidRound(top + step), bid: 0 });
    }
    dilSbidExtRender(key);
    dilSbidPaintCounts();
    dilSbidRefreshGrid();
    dilSbidScheduleSave();
}
function dilSbidExtDelete(key, idx) {
    dilSbidExtRead(key);
    const slabs = currentDilSbidTables[key];
    if (slabs.length <= 1 || !(idx >= 0 && idx < slabs.length)) return;
    slabs.splice(idx, 1);
    dilSbidExtRender(key);
    dilSbidPaintCounts();
    dilSbidRefreshGrid();
    dilSbidScheduleSave();
}
function dilSbidWithChart(fn) {
    if (window.Chart) { fn(); return; }
    if (typeof window.loadChartJs === 'function') {
        window.loadChartJs().then(fn).catch(function() {});
        return;
    }
    if (!window._dilSbidChartJs) {
        window._dilSbidChartJs = new Promise(function(resolve, reject) {
            const s = document.createElement('script');
            s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.8/dist/chart.umd.min.js';
            s.onload = resolve;
            s.onerror = reject;
            document.head.appendChild(s);
        });
    }
    window._dilSbidChartJs.then(fn).catch(function() {});
}
function dilSbidPaintLegend(id, slices, total) {
    const el = document.getElementById(id);
    if (!el) return;
    const sum = total != null ? total : slices.reduce(function(t, s) { return t + (s.count || 0); }, 0);
    el.innerHTML = slices.map(function(s) {
        const n = s.count || 0;
        const pct = sum > 0 ? Math.round((n / sum) * 100) : 0;
        return '<div class="dsb-leg-row"><span class="dsb-swatch" style="background:' + s.color + '"></span><span>' + dilSbidEsc(s.label) + '</span><strong>' + n + '</strong><span class="dsb-leg-pct">' + pct + '%</span></div>';
    }).join('');
}
function dilSbidDrawBar(job) {
    const canvas = document.getElementById(job.id);
    if (!canvas || typeof Chart === 'undefined') return;
    const bound = (typeof Chart.getChart === 'function') ? Chart.getChart(canvas) : dilSbidCharts[job.id];
    if (bound) bound.destroy();
    const total = job.total != null ? job.total : job.slices.reduce(function(t, s) { return t + (s.count || 0); }, 0);
    dilSbidCharts[job.id] = new Chart(canvas.getContext('2d'), {
        type: 'bar',
        data: {
            labels: job.slices.map(function(s) { return s.label; }),
            datasets: [{
                data: job.slices.map(function(s) { return s.count || 0; }),
                backgroundColor: job.slices.map(function(s) { return s.color; }),
                borderWidth: 0, borderRadius: 2, borderSkipped: 'bottom',
                barPercentage: 0.78, categoryPercentage: 0.72, maxBarThickness: 42,
            }],
        },
        options: {
            responsive: true, maintainAspectRatio: false, animation: false,
            plugins: { legend: { display: false }, tooltip: { callbacks: { label: function(ctx) {
                const n = Number(ctx.raw) || 0;
                return ' ' + n + ' · ' + (total > 0 ? Math.round((n / total) * 100) : 0) + '%';
            } } } },
            scales: {
                x: { grid: { display: false }, border: { display: true, color: '#111827' }, ticks: { display: false } },
                y: { beginAtZero: true, grid: { display: false }, border: { display: true, color: '#111827' }, ticks: { font: { size: 9 }, precision: 0 } },
            },
        },
    });
}
function dilSbidQueueCharts(jobs) {
    clearTimeout(dilSbidRepaintTimer);
    dilSbidRepaintTimer = setTimeout(function() {
        dilSbidWithChart(function() { jobs.forEach(dilSbidDrawBar); });
    }, 120);
}
/** Per table: SKUs with a non-zero S Bid and the average added. Sum = the final bid that fills S BID. */
function dilSbidSumStats(rows) {
    const keys = ['dil'].concat(DIL_SBID_EXT_KEYS);
    const st = { sum: { n: 0, total: 0 } };
    keys.forEach(function(k) { st[k] = { n: 0, total: 0 }; });
    rows.forEach(function(row) {
        const part = dilSbidDilPart(row, dilSbidMetric(row));
        if (part.bid !== 0) { st.dil.n++; st.dil.total += part.bid; }
        const extra = dilSbidExtBids(row);
        DIL_SBID_EXT_KEYS.forEach(function(k) {
            if (extra[k].bid) { st[k].n++; st[k].total += extra[k].bid; }
        });
        const res = dilSbidOfRow(row);
        if (!res.skip && res.bid > 0) { st.sum.n++; st.sum.total += res.bid; }
    });
    return st;
}
function dilSbidExtPaint() {
    if (!DIL_SBID_EXT || !dilSbidModalOpen()) return;
    const rows = dilSbidEligibleRows();
    const jobs = [];

    // Dil
    const dilCounts = dilSbidCounts();
    const dilSlices = currentDilSbidSlabs.map(function(slab, i) {
        return { label: dilSbidRangeLabel(slab), count: dilCounts[i] || 0, color: DIL_SBID_COLORS[i % DIL_SBID_COLORS.length] };
    });
    dilSlices.push({ label: 'Outside', count: Math.max(0, rows.length - dilCounts.reduce(function(t, n) { return t + n; }, 0)), color: '#adb5bd' });
    dilSbidPaintLegend('dil-sbid-leg-dil', dilSlices);
    jobs.push({ id: 'dil-sbid-chart-dil', slices: dilSlices });

    // Views / CVR / eBay Sold / Std NPFT %
    DIL_SBID_EXT_KEYS.forEach(function(key) {
        const slabs = currentDilSbidTables[key] || [];
        const res = dilSbidExtCounts(key, rows);
        const tbody = document.getElementById('dil-sbid-x-tbody-' + key);
        if (tbody) {
            tbody.querySelectorAll('tr').forEach(function(tr, i) {
                const el = tr.querySelector('.dil-sbid-x-count');
                if (el) el.textContent = String(res.counts[i] || 0);
            });
        }
        const slices = slabs.map(function(slab, i) {
            return { label: dilSbidRangeLabel(slab), count: res.counts[i] || 0, color: DIL_SBID_COLORS[i % DIL_SBID_COLORS.length] };
        });
        slices.push({ label: key === 'npft' ? 'No Std Prc / outside' : 'Outside', count: res.outside, color: '#adb5bd' });
        dilSbidPaintLegend('dil-sbid-leg-' + key, slices);
        jobs.push({ id: 'dil-sbid-chart-' + key, slices: slices });
    });

    // CVR up / down
    const cvrCounts = dilSbidCvrCounts();
    let withCvr = 0;
    rows.forEach(function(row) { if (dilSbidCvrParts(row)) withCvr++; });
    const overSlices = [
        { label: 'Down', count: cvrCounts.downTotal || 0, color: '#dc3545' },
        { label: 'Flat / no match', count: Math.max(0, withCvr - (cvrCounts.downTotal || 0) - (cvrCounts.upTotal || 0)), color: '#94a3b8' },
        { label: 'Up', count: cvrCounts.upTotal || 0, color: '#198754' },
        { label: 'No views', count: Math.max(0, rows.length - withCvr), color: '#e2e8f0' },
    ];
    dilSbidPaintLegend('dil-sbid-leg-over', overSlices);
    jobs.push({ id: 'dil-sbid-chart-over', slices: overSlices });

    // L30 View up / down
    const viewCounts = dilSbidViewCounts();
    const viewSlices = [
        { label: 'Down', count: viewCounts.downTotal || 0, color: '#dc3545' },
        { label: 'Flat / no match', count: Math.max(0, rows.length - (viewCounts.downTotal || 0) - (viewCounts.upTotal || 0)), color: '#94a3b8' },
        { label: 'Up', count: viewCounts.upTotal || 0, color: '#198754' },
    ];
    dilSbidPaintLegend('dil-sbid-leg-viewover', viewSlices);
    jobs.push({ id: 'dil-sbid-chart-viewover', slices: viewSlices });

    // Sum S Bid
    const st = dilSbidSumStats(rows);
    const sumRows = [
        { key: 'dil', label: 'Dil' },
        { key: 'views', label: DIL_SBID_EXT_LABELS.views },
        { key: 'cvr', label: DIL_SBID_EXT_LABELS.cvr },
        { key: 'sold', label: DIL_SBID_EXT_LABELS.sold },
        { key: 'npft', label: DIL_SBID_EXT_LABELS.npft + ' %' },
        { key: 'sum', label: 'Sum S Bid' },
    ];
    const avg = function(x) { return x.n > 0 ? (x.total / x.n).toFixed(1) + '%' : '—'; };
    const sumBody = document.getElementById('dil-sbid-sum-tbody');
    if (sumBody) {
        sumBody.innerHTML = sumRows.map(function(r) {
            const x = st[r.key];
            const cls = r.key === 'sum' ? ' class="dsb-sum-total"' : '';
            return '<tr' + cls + '><td><span class="dsb-swatch d-inline-block me-1" style="background:' + DIL_SBID_SUM_COLORS[r.key] + '"></span>' + dilSbidEsc(r.label) + '</td>'
                + '<td class="text-center">' + x.n + '</td><td class="text-end">' + avg(x) + '</td></tr>';
        }).join('');
    }
    const sumSlices = sumRows.map(function(r) {
        return { label: r.label, count: st[r.key].n, color: DIL_SBID_SUM_COLORS[r.key] };
    });
    dilSbidPaintLegend('dil-sbid-leg-sum', sumSlices, rows.length);
    jobs.push({ id: 'dil-sbid-chart-sum', slices: sumSlices, total: rows.length });

    dilSbidQueueCharts(jobs);
}
function dilSbidScheduleSave() {
    clearTimeout(dilSbidSaveTimer);
    dilSbidSaveTimer = setTimeout(function() { dilSbidSave(false); }, 500);
}
function dilSbidPaintCounts() {
    const counts = dilSbidCounts();
    document.querySelectorAll('#dil-sbid-tbody tr').forEach(function(tr, i) {
        const el = tr.querySelector('.dil-sbid-count');
        if (el) el.textContent = String(counts[i] || 0);
    });
    const cvrCounts = dilSbidCvrCounts();
    const down = document.getElementById('dil-sbid-cvr-down-count');
    const up = document.getElementById('dil-sbid-cvr-up-count');
    if (down) down.textContent = String(cvrCounts.down || 0);
    if (up) up.textContent = String(cvrCounts.up || 0);
    ['down', 'up'].forEach(function(dir) {
        const list = dir === 'down' ? cvrCounts.downMore : cvrCounts.upMore;
        document.querySelectorAll('#dil-sbid-cvr-table tr.dil-sbid-cvr-x[data-dir="' + dir + '"]').forEach(function(tr, i) {
            const el = tr.querySelector('.dil-sbid-cvr-x-count');
            if (el) el.textContent = String((list && list[i]) || 0);
        });
    });
    const viewCounts = dilSbidViewCounts();
    const viewDown = document.getElementById('dil-sbid-view-down-count');
    const viewUp = document.getElementById('dil-sbid-view-up-count');
    if (viewDown) viewDown.textContent = String(viewCounts.down || 0);
    if (viewUp) viewUp.textContent = String(viewCounts.up || 0);
    ['down', 'up'].forEach(function(dir) {
        const list = dir === 'down' ? viewCounts.downMore : viewCounts.upMore;
        document.querySelectorAll('#dil-sbid-view-table tr.dil-sbid-view-x[data-dir="' + dir + '"]').forEach(function(tr, i) {
            const el = tr.querySelector('.dil-sbid-view-x-count');
            if (el) el.textContent = String((list && list[i]) || 0);
        });
    });
    if (DIL_SBID_EXT) dilSbidExtPaint();
}
function dilSbidSave(thenApply) {
    const errEl = document.getElementById('dil-sbid-err');
    const statusEl = document.getElementById('dil-sbid-status');
    if (errEl) errEl.classList.add('d-none');
    dilSbidRead();
    for (let i = 0; i < currentDilSbidSlabs.length; i++) {
        if (!(parseFloat(currentDilSbidSlabs[i].max) >= parseFloat(currentDilSbidSlabs[i].min))) {
            if (errEl) {
                errEl.textContent = 'Slab ' + (i + 1) + ': To must be at least From';
                errEl.classList.remove('d-none');
            }
            return;
        }
    }
    const cap = dilSbidCapNow(true);
    if (!(cap.min <= cap.max)) {
        if (errEl) {
            errEl.textContent = 'Min bid cap must be at most Max';
            errEl.classList.remove('d-none');
        }
        return;
    }
    const payload = { slabs: currentDilSbidSlabs, enabled: !!dilSbidEnabled, cvr: dilSbidCvrNow(), views_over: dilSbidViewNow(), cap: cap };
    if (DIL_SBID_EXT) {
        DIL_SBID_EXT_KEYS.forEach(function(key) { dilSbidExtRead(key); });
        for (let k = 0; k < DIL_SBID_EXT_KEYS.length; k++) {
            const key = DIL_SBID_EXT_KEYS[k];
            const slabs = currentDilSbidTables[key] || [];
            for (let i = 0; i < slabs.length; i++) {
                if (!(parseFloat(slabs[i].max) >= parseFloat(slabs[i].min))) {
                    if (errEl) {
                        errEl.textContent = DIL_SBID_EXT_LABELS[key] + ' range ' + (i + 1) + ': To must be at least From';
                        errEl.classList.remove('d-none');
                    }
                    return;
                }
            }
        }
        payload.tables = currentDilSbidTables;
    }
    $.ajax({
        url: DIL_SBID_SAVE_URL,
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        contentType: 'application/json',
        data: JSON.stringify(payload),
        success: function(resp) {
            const before = currentDilSbidSlabs.length;
            if (resp && Array.isArray(resp.slabs) && resp.slabs.length) currentDilSbidSlabs = resp.slabs;
            if (currentDilSbidSlabs.length !== before) renderDilSbidTable();
            dilSbidRefreshGrid();
            dilSbidPaintCounts();
            if (thenApply) dilSbidApply();
            else if (statusEl) statusEl.textContent = 'Saved';
        },
        error: function(xhr) {
            if (errEl) {
                errEl.textContent = (xhr.responseJSON && xhr.responseJSON.error) || 'Could not save';
                errEl.classList.remove('d-none');
            }
        }
    });
}
function dilSbidPaintStatus(resp) {
    if (typeof table === 'undefined' || !table || !resp || !Array.isArray(resp.results)) return;
    const paused = {};
    const running = {};
    resp.results.forEach(function(r) {
        if (!r || r.listing_id == null || r.status !== 'pushed') return;
        const id = String(r.listing_id);
        if (r.bid === 'OFF') paused[id] = true;
        else running[id] = true;
    });
    table.getRows().forEach(function(row) {
        const id = String((row.getData() || {}).listing_id || '');
        if (paused[id]) row.update({ campaign_status: 'PAUSED' });
        else if (running[id]) row.update({ campaign_status: 'RUNNING' });
    });
}
function dilSbidPushButtons() {
    return Array.prototype.slice.call(document.querySelectorAll('#dil-sbid-apply-btn, .dil-sbid-push-btn'));
}
function dilSbidSetPushBusy(on, label) {
    dilSbidPushButtons().forEach(function(btn) {
        btn.disabled = !!on;
        if (!btn.classList.contains('dil-sbid-push-btn')) return;
        if (!btn.getAttribute('data-label')) btn.setAttribute('data-label', btn.innerHTML);
        btn.innerHTML = on
            ? ('<i class="fas fa-spinner fa-spin me-1"></i>' + (label || 'Pushing…'))
            : btn.getAttribute('data-label');
    });
}
function dilSbidApply() {
    const errEl = document.getElementById('dil-sbid-err');
    const statusEl = document.getElementById('dil-sbid-status');
    const ids = [];
    const seenIds = {};
    let alreadyMatching = 0;
    dilSbidRows().forEach(function(d) {
        const id = d && (d.listing_id || d.eBay_item_id || d.ebay_item_id || d.item_id);
        if (!id || seenIds[id]) return;
        seenIds[id] = true;
        // Extended mode: a running ad whose C Bid already equals the S Bid needs no push.
        // The server still checks live before it writes anything.
        if (DIL_SBID_EXT && dilSbidEnabled) {
            const res = dilSbidOfRow(d);
            const live = parseFloat(d.ca_bid_percentage != null ? d.ca_bid_percentage : d.bid_percentage);
            const running = String(d.ca_campaign_status || d.campaign_status || '').trim().toUpperCase() === 'RUNNING';
            if (running && res && !res.skip && res.bid > 0 && isFinite(live)
                && Math.round(live * 10) === Math.round(res.bid * 10)) {
                alreadyMatching++;
                return;
            }
        }
        ids.push(String(id));
    });
    if (!ids.length) {
        const msg = alreadyMatching ? ('All ' + alreadyMatching + ' listings already match') : 'No listings loaded';
        if (statusEl) statusEl.textContent = msg;
        return;
    }
    dilSbidSetPushBusy(true, 'Pushing ' + ids.length + '…');
    if (statusEl) statusEl.textContent = 'Applying ' + ids.length + '…';
    $.ajax({
        url: DIL_SBID_APPLY_URL,
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        contentType: 'application/json',
        data: JSON.stringify({ listing_ids: ids }),
        timeout: 300000,
        success: function(resp) {
            dilSbidSetPushBusy(false);
            if (resp && resp.error) {
                if (errEl) { errEl.textContent = resp.error; errEl.classList.remove('d-none'); }
                if (statusEl) statusEl.textContent = '';
                return;
            }
            const s = resp.success || 0, f = resp.failed || 0, sk = resp.skipped || 0;
            dilSbidPaintStatus(resp);
            if (statusEl) statusEl.textContent = 'Applied: ' + s + ' pushed · ' + f + ' failed · ' + sk + ' skipped';
        },
        error: function(xhr) {
            dilSbidSetPushBusy(false);
            if (errEl) {
                errEl.textContent = (xhr.responseJSON && xhr.responseJSON.error) || xhr.responseText || 'Apply failed';
                errEl.classList.remove('d-none');
            }
        }
    });
}

$.get(DIL_SBID_GET_URL, function(data) {
    if (data && Array.isArray(data.slabs) && data.slabs.length) {
        currentDilSbidSlabs = data.slabs.filter(function(s) { return s && s.mode !== 'auto_off'; });
    }
    dilSbidEnabled = !!(data && data.enabled);
    if (data && data.cvr) currentDilSbidCvr = Object.assign({}, DIL_SBID_CVR_DEFAULTS, data.cvr);
    currentDilSbidView = Object.assign({}, DIL_SBID_VIEW_DEFAULTS, (data && data.views_over) || {});
    currentDilSbidView.down_more = (currentDilSbidView.down_more || []).slice();
    currentDilSbidView.up_more = (currentDilSbidView.up_more || []).slice();
    currentDilSbidCap = Object.assign({}, DIL_SBID_CAP_DEFAULTS, (data && data.cap) || {});
    if (DIL_SBID_EXT) currentDilSbidTables = dilSbidCloneTables(data && data.tables);
    dilSbidPaintCvr();
    dilSbidPaintView();
    dilSbidPaintCap();
    dilSbidPaintMode();
    renderDilSbidTable();
    dilSbidRefreshGrid();
});
document.getElementById('dil-sbid-enabled').addEventListener('change', function() {
    dilSbidEnabled = !!this.checked;
    dilSbidPaintMode();
    dilSbidRefreshGrid();
    clearTimeout(dilSbidSaveTimer);
    dilSbidSave(false);
});
document.getElementById('dilSbidRuleModal').addEventListener('show.bs.modal', function() {
    renderDilSbidTable();
});
document.getElementById('dilSbidRuleModal').addEventListener('shown.bs.modal', function() {
    if (DIL_SBID_EXT) dilSbidExtPaint();
});
if (DIL_SBID_EXT) {
    document.querySelectorAll('#dilSbidRuleModal .dil-sbid-x-tbody').forEach(function(tbody) {
        const key = tbody.getAttribute('data-table');
        tbody.addEventListener('input', function() {
            dilSbidExtRead(key);
            dilSbidPaintCounts();
            dilSbidRefreshGrid();
            dilSbidScheduleSave();
        });
        tbody.addEventListener('click', function(ev) {
            const btn = ev.target.closest('.dil-sbid-x-del');
            if (!btn) return;
            dilSbidExtDelete(key, parseInt(btn.getAttribute('data-idx'), 10));
        });
    });
    document.querySelectorAll('#dilSbidRuleModal .dil-sbid-x-add').forEach(function(btn) {
        btn.addEventListener('click', function() { dilSbidExtAdd(btn.getAttribute('data-table')); });
    });
    document.getElementById('dil-sbid-cvr-add-down').addEventListener('click', function() { dilSbidCvrAddMore('down'); });
    document.getElementById('dil-sbid-cvr-add-up').addEventListener('click', function() { dilSbidCvrAddMore('up'); });
    document.getElementById('dil-sbid-cvr-table').addEventListener('click', function(ev) {
        const btn = ev.target.closest('.dil-sbid-cvr-x-del');
        if (btn) dilSbidCvrDeleteMore(btn.closest('tr'));
    });
    document.getElementById('dil-sbid-view-add-down').addEventListener('click', function() { dilSbidViewAddMore('down'); });
    document.getElementById('dil-sbid-view-add-up').addEventListener('click', function() { dilSbidViewAddMore('up'); });
    document.getElementById('dil-sbid-view-table').addEventListener('click', function(ev) {
        const btn = ev.target.closest('.dil-sbid-view-x-del');
        if (btn) dilSbidViewDeleteMore(btn.closest('tr'));
    });
}
document.getElementById('dil-sbid-tbody').addEventListener('input', function() {
    dilSbidRead();
    dilSbidPaintCounts();
    dilSbidRefreshGrid();
    dilSbidScheduleSave();
});
document.getElementById('dil-sbid-tbody').addEventListener('click', function(ev) {
    const btn = ev.target.closest('.dil-sbid-del');
    if (!btn) return;
    dilSbidRead();
    if (currentDilSbidSlabs.length <= 1) return;
    currentDilSbidSlabs.splice(parseInt(btn.getAttribute('data-idx'), 10), 1);
    renderDilSbidTable();
    dilSbidRefreshGrid();
    dilSbidScheduleSave();
});
document.getElementById('dil-sbid-add-btn').addEventListener('click', function() {
    dilSbidRead();
    let next = 0;
    currentDilSbidSlabs.forEach(function(s) {
        const max = parseFloat(s.max);
        if (isFinite(max) && max > next && max < 9999) next = max;
    });
    const max = next >= 100 ? 9999 : dilSbidRound(next + 10);
    currentDilSbidSlabs.push({ min: next, max: max, mode: 'dynamic', bid: '' });
    renderDilSbidTable();
    dilSbidScheduleSave();
});
document.getElementById('dil-sbid-apply-btn').addEventListener('click', function() {
    clearTimeout(dilSbidSaveTimer);
    dilSbidSave(true);
});
document.querySelectorAll('.dil-sbid-push-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        clearTimeout(dilSbidSaveTimer);
        dilSbidApply();
    });
});
document.getElementById('dil-sbid-cvr-table').addEventListener('input', function() {
    dilSbidCvrNow();
    dilSbidPaintCounts();
    dilSbidRefreshGrid();
    dilSbidScheduleSave();
});
document.getElementById('dil-sbid-view-table').addEventListener('input', function() {
    dilSbidViewNow();
    dilSbidPaintCounts();
    dilSbidRefreshGrid();
    dilSbidScheduleSave();
});
['dil-sbid-cap-min', 'dil-sbid-cap-max'].forEach(function(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.addEventListener('input', function() {
        dilSbidCapNow(false);
        dilSbidRefreshGrid();
        dilSbidScheduleSave();
    });
    el.addEventListener('blur', function() {
        dilSbidCapNow(true);
        dilSbidRefreshGrid();
    });
});
@endif
