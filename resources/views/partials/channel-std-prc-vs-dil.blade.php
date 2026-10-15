{{-- Std prc vs dil for every analytics page. Same slabs as Amazon. Parts: css, buttons, modals, script. --}}
@php $channelStdPrcPart = $channelStdPrcPart ?? 'all'; @endphp

@if($channelStdPrcPart === 'css' || $channelStdPrcPart === 'all')
        #chStdPrcModal .modal-dialog {
            width: calc(100vw - 1.25rem);
            max-width: calc(100vw - 1.25rem);
            height: calc(100vh - 1.25rem);
            max-height: calc(100vh - 1.25rem);
            margin: 0.625rem auto;
        }
        #chStdPrcModal .modal-content { height: 100%; max-height: 100%; border: 0; border-radius: 16px; overflow: hidden; box-shadow: 0 24px 60px rgba(15, 23, 42, 0.18); }
        #chStdPrcModal .modal-header { background: #fff; border-bottom: 1px solid #e8eef5; padding: 14px 18px; }
        #chStdPrcModal .modal-title { font-weight: 700; color: #0f172a; }
        #chStdPrcModal .ch-sp-sub { color: #64748b; font-size: 12px; margin-top: 2px; }
        #chStdPrcModal .modal-body { background: #f4f7fb; padding: 14px 16px 16px; }
        #chStdPrcModal .modal-footer { background: #fff; border-top: 1px solid #e8eef5; }
        #chStdPrcModal .ch-sp-cols { display: flex; gap: 12px; align-items: stretch; width: 100%; min-width: 0; }
        #chStdPrcModal .ch-sp-col { flex: 1 1 0; min-width: 0; display: flex; flex-direction: column; border: 1px solid #e6edf5; border-radius: 14px; padding: 12px; background: #fff; }
        #chStdPrcModal .ch-sp-col-head { display: flex; align-items: center; justify-content: space-between; gap: 6px; margin-bottom: 8px; }
        #chStdPrcModal .ch-sp-col-head .ch-sp-pie-title { margin-bottom: 0; }
        #chStdPrcModal .ch-sp-min-btn { border: 0; background: #f1f5f9; color: #334155; width: 22px; height: 22px; border-radius: 6px; padding: 0; line-height: 1; font-weight: 700; flex-shrink: 0; }
        #chStdPrcModal .ch-sp-min-btn:hover { background: #e2e8f0; }
        #chStdPrcModal .ch-sp-col.is-min { flex: 0 0 40px; padding: 10px 4px; cursor: pointer; }
        #chStdPrcModal .ch-sp-col.is-min > :not(.ch-sp-col-head) { display: none !important; }
        #chStdPrcModal .ch-sp-col.is-min .ch-sp-col-head { flex-direction: column; justify-content: flex-start; height: 100%; margin: 0; gap: 10px; }
        #chStdPrcModal .ch-sp-col.is-min .ch-sp-pie-title { writing-mode: vertical-rl; transform: rotate(180deg); white-space: nowrap; }
        #chStdPrcModal .ch-sp-pie-title { font-weight: 700; font-size: 13px; margin-bottom: 8px; color: #0f172a; }
        #chStdPrcModal .ch-sp-pie-canvas { position: relative; width: 100%; height: 150px; }
        #chStdPrcModal .ch-sp-pie-canvas canvas { display: block; width: 100% !important; height: 150px !important; }
        #chStdPrcModal .ch-sp-pie-legend { width: 100%; font-size: 12px; margin: 8px 0; color: #334155; }
        #chStdPrcModal .ch-sp-leg-row { display: grid; grid-template-columns: 8px minmax(0, 1fr) auto auto 8px; gap: 6px; align-items: center; padding: 2px 0; }
        #chStdPrcModal .ch-sp-swatch { width: 8px; height: 8px; border-radius: 50%; }
        #chStdPrcModal .ch-sp-leg-pct { color: #94a3b8; min-width: 2.2rem; text-align: right; }
        #chStdPrcModal .ch-sp-hist-dot { width: 8px; height: 8px; border-radius: 50%; border: none; padding: 0; cursor: pointer; justify-self: end; box-shadow: 0 0 0 1px rgba(15, 23, 42, 0.12); }
        #chStdPrcModal .ch-sp-hist-dot:hover { transform: scale(1.35); }
        #chStdPrcModal .ch-sp-hist-wrap { display: none; margin: 0 0 12px; padding: 8px 10px 6px; border: 1px solid #e6edf5; border-radius: 12px; background: #fff; }
        #chStdPrcModal .ch-sp-hist-wrap.is-open { display: block; }
        #chStdPrcModal .ch-sp-hist-canvas-wrap { height: 180px; }
        #chStdPrcModal .ch-sp-col .table { font-size: 12px; margin-bottom: 0; }
        #chStdPrcModal .ch-sp-col .table th, #chStdPrcModal .ch-sp-col .table td { padding: 5px 6px; vertical-align: middle; }
        #chStdPrcModal .ch-sp-col .table thead th { background: #f8fafc; color: #64748b; font-size: 10px; letter-spacing: 0.04em; text-transform: uppercase; }
        #chStdPrcModal .ch-sp-input {
            width: 52px;
            min-width: 52px;
            max-width: 52px;
            margin: 0 auto;
            display: inline-block;
            box-sizing: border-box;
            text-align: center;
            font-weight: 600;
            font-size: 13px;
            line-height: 24px;
            height: 28px;
            padding: 0 4px;
            color: #0f172a;
            background: #fff;
            border-radius: 7px;
            -moz-appearance: textfield;
            appearance: textfield;
        }
        #chStdPrcModal .ch-sp-input::-webkit-outer-spin-button,
        #chStdPrcModal .ch-sp-input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        #chStdPrcModal td.text-end .ch-sp-input { margin-left: auto; margin-right: 0; }
        #chStdPrcModal .ch-sp-cvr-thresh { display: inline-flex; align-items: center; gap: 4px; color: #64748b; font-weight: 700; }
        #chStdPrcModal .ch-sp-cvr-thresh .ch-sp-input { width: 52px; margin: 0; }
        #chStdPrcModal .ch-sp-count { font-weight: 700; text-align: center; }
        #chStdPrcModal .ch-sp-when { display: inline-flex; align-items: center; gap: 6px; font-weight: 600; white-space: nowrap; }
        #chStdPrcModal .ch-sp-when::before { content: ''; width: 8px; height: 8px; border-radius: 50%; background: #94a3b8; }
        #chStdPrcModal .ch-sp-when-down2::before { background: #9f1239; }
        #chStdPrcModal .ch-sp-when-down::before { background: #dc3545; }
        #chStdPrcModal .ch-sp-when-up::before { background: #198754; }
        #chStdPrcModal .ch-sp-when-up2::before { background: #14532d; }
        #chStdPrcModal .ch-sp-npft { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 12px; padding: 10px 14px; border: 1px solid #e6edf5; border-radius: 14px; background: #fff; font-size: 13px; color: #334155; }
        @include('partials.min-npft-sprice-mark', ['minNpftMarkPart' => 'css'])
        #chStdPrcModal .ch-sp-npft .ch-sp-input { width: 64px; min-width: 64px; max-width: 64px; margin: 0; }
        #chStdPrcModal .ch-sp-margins { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 12px; margin-bottom: 12px; }
        #chStdPrcModal .ch-sp-margin { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 16px; border: 1px solid #e6edf5; border-radius: 14px; padding: 12px 16px; background: #fff; }
        #chStdPrcModal .ch-sp-margin strong { display: block; font-size: 26px; letter-spacing: -0.03em; }
        #chStdPrcModal .ch-sp-margin strong .ch-sp-sales-lbl { font-size: 13px; font-weight: 600; letter-spacing: 0; color: #64748b; margin-right: 6px; }
        #chStdPrcModal .ch-sp-margin > div { flex: 1 1 100%; min-width: 0; }
        #chStdPrcModal .ch-sp-margin-badges { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 6px; margin-top: 8px; }
        #chStdPrcModal .ch-sp-margin-badges .badge { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 1px; width: 100%; height: 44px; padding: 4px 6px; font-weight: 600; white-space: nowrap; }
        #chStdPrcModal .ch-sp-margin-badges .ch-sp-badge-lbl { font-size: 10px; font-weight: 600; opacity: .75; text-transform: uppercase; letter-spacing: .03em; }
        #chStdPrcModal .ch-sp-margin-badges .ch-sp-badge-val { font-size: 13px; font-weight: 700; }
        #chStdPrcModal .ch-sp-add { margin-top: auto; width: 100%; border-radius: 8px; border-style: dashed; font-weight: 600; }
        #chStdPrcModal .ch-sp-del { width: 22px; height: 22px; padding: 0; border-radius: 6px; color: #94a3b8; }
        #ch-std-prc-btn {
            background: #FD5E00;
            border-color: #FD5E00;
            color: #000;
            font-weight: 600;
        }
        #ch-std-prc-btn:hover, #ch-std-prc-btn:focus, #ch-std-prc-btn:active {
            background: #e45500;
            border-color: #e45500;
            color: #000;
        }
@endif

@if($channelStdPrcPart === 'buttons' || $channelStdPrcPart === 'all')
                    <button type="button" class="btn btn-sm" id="ch-std-prc-btn" title="Std Prc minus Age, Dil, B Disc, 0 Sold, CVR, and Review discounts. Same slabs as Amazon.">
                        <i class="fas fa-tags"></i> Std prc vs dil
                    </button>
@endif

@if($channelStdPrcPart === 'modals' || $channelStdPrcPart === 'all')
    @php
        $channelStdPageName = match ($channelPromoChannel ?? '') {
            'temu', 'newtemuone' => 'Temu 1',
            'newtemutwo', 'temu2' => 'Temu 2',
            'temu3' => 'Temu 3',
            'ebay1' => 'eBay 1',
            'ebay2' => 'eBay 2',
            'ebay2op' => 'eBay 2 OP',
            'ebay3' => 'eBay 3',
            'macys', 'macy' => 'Macys',
            'purchasing_power' => 'Purchasing Power',
            'wayfair' => 'Wayfair',
            'reverb' => 'Reverb',
            'doba' => 'Doba',
            'doba_withoutship' => 'Doba Pickup',
            'aliexpress' => 'AliExpress',
            'shein' => 'Shein',
            'faire' => 'Faire',
            'tiktok' => 'TikTok',
            'tiktok2' => 'TikTok 2',
            'fb_marketplace' => 'FB Marketplace',
            'topdawg' => 'TopDawg',
            'shopify_b2c' => 'Shopify B2C',
            'shopify_b2b' => 'Shopify B2B',
            'bestbuy' => 'Best Buy',
            'newegg' => 'Newegg',
            'walmart' => 'Walmart',
            'pls' => 'PLS',
            'depop' => 'Depop',
            'vinted' => 'Vinted',
            'instagram' => 'Instagram Shop',
            'alibaba' => 'Alibaba',
            'mercari_wship' => 'Mercari w Ship',
            'mercari_woship' => 'Mercari Pickup',
            '' => '',
            default => ucwords(str_replace('_', ' ', (string) $channelPromoChannel)),
        };
    @endphp
    <div class="modal fade" id="chStdPrcModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title fs-6 mb-0"><i class="fas fa-tags me-1"></i> Std prc vs dil</h5>
                        <div class="ch-sp-sub">S PRC = Std Prc − Age − Dil − B Disc − 0 Sold − CVR − Reviews.@if(($channelPromoChannel ?? '') === 'shopify_b2b') Shopify B2B then subtracts the Ship column.@endif 0 Sold applies only when sold qty is 0. Std Prc under $15 uses half of Age, Dil, 0 Sold, CVR, and Review discounts. B Disc stays at the full Disc %. S PRC is raised when NPFT% would fall under Min NPFT %. Clearance SKUs stay on the discount price.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="ch-sp-margins">
                        <div class="ch-sp-margin">
                            <div>
                                <div class="small text-muted" id="ch-sp-margin-l30-label">Projected with OV L30 Sales Data</div>
                                <strong id="ch-sp-margin-l30">—</strong>
                                <div class="ch-sp-margin-badges" id="ch-sp-margin-l30-sub"></div>
                            </div>
                        </div>
                        @if(in_array(($channelPromoChannel ?? ''), ['doba', 'doba_withoutship'], true))
                        <div class="ch-sp-margin">
                            <div>
                                <div class="small text-muted" title="Doba L30 units x L30 average sold price, at the current margin, LP and ship. Counts every SKU with Doba sales, including SKUs whose stock is now 0, plus Doba orders that have no row in this table. Not affected by the discount rules below.">Current {{ $channelStdPageName }} L30</div>
                                <strong id="ch-sp-margin-cur">—</strong>
                                <div class="ch-sp-margin-badges" id="ch-sp-margin-cur-sub"></div>
                            </div>
                        </div>
                        @endif
                        <div class="ch-sp-margin">
                            <div>
                                <div class="small text-muted">Projected with {{ $channelStdPageName !== '' ? $channelStdPageName.' ' : '' }}L30</div>
                                <strong id="ch-sp-margin-pg">—</strong>
                                <div class="ch-sp-margin-badges" id="ch-sp-margin-pg-sub"></div>
                            </div>
                        </div>
                        <div class="ch-sp-margin">
                            <div>
                                <div class="small text-muted">Projected with Total Inv</div>
                                <strong id="ch-sp-margin-inv">—</strong>
                                <div class="ch-sp-margin-badges" id="ch-sp-margin-inv-sub"></div>
                            </div>
                        </div>
                    </div>
                    <div class="ch-sp-npft">
                        <label class="fw-semibold mb-0" for="ch-sp-min-npft">Min NPFT %</label>
                        <input type="number" id="ch-sp-min-npft" class="form-control form-control-sm ch-sp-input" min="0" max="99" step="0.1" value="10" title="S PRC is raised so NPFT% stays at or above this. 0 turns the floor off.">
                        <span class="text-muted">S PRC stays at or above this NPFT%. Clearance SKUs are ignored (<strong id="ch-sp-min-npft-ignored">0</strong>). <span style="color:#dc3545;font-weight:700;">Applied <strong id="ch-sp-min-npft-applied">0</strong></span></span>
                    </div>
                    <div class="ch-sp-hist-wrap" id="ch-sp-hist-wrap">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small fw-semibold" id="ch-sp-hist-title">Daily history</span>
                            <button type="button" class="btn-close" id="ch-sp-hist-close" aria-label="Close history" style="font-size:10px;"></button>
                        </div>
                        <div class="ch-sp-hist-canvas-wrap"><canvas id="ch-sp-hist"></canvas></div>
                    </div>
                    <div class="ch-sp-cols">
                        <div class="ch-sp-col" data-ch-sp-panel="dil">
                            <div class="ch-sp-col-head"><div class="ch-sp-pie-title">Dil</div><button type="button" class="ch-sp-min-btn" title="Minimize Dil" aria-label="Minimize Dil">−</button></div>
                            <div class="ch-sp-pie-canvas"><canvas id="ch-sp-pie-dil"></canvas></div>
                            <div class="ch-sp-pie-legend" id="ch-sp-leg-dil"></div>
                            <div class="table-responsive"><table class="table table-sm table-bordered mb-0"><thead class="table-light"><tr><th class="text-center">From</th><th class="text-center">To</th><th class="text-center">Count</th><th class="text-end">Disc %</th><th></th></tr></thead><tbody id="ch-sp-dil-tbody"></tbody></table></div>
                            <button type="button" class="btn btn-sm btn-outline-primary ch-sp-add" id="ch-sp-dil-add">Add slab</button>
                        </div>
                        <div class="ch-sp-col" data-ch-sp-panel="rev">
                            <div class="ch-sp-col-head"><div class="ch-sp-pie-title">Reviews</div><button type="button" class="ch-sp-min-btn" title="Minimize Reviews" aria-label="Minimize Reviews">−</button></div>
                            <div class="ch-sp-pie-canvas"><canvas id="ch-sp-pie-rev"></canvas></div>
                            <div class="ch-sp-pie-legend" id="ch-sp-leg-rev"></div>
                            <div class="d-flex justify-content-between align-items-center mb-2"><label class="small fw-semibold mb-0" for="ch-sp-review-max">Max reviews</label><input type="number" id="ch-sp-review-max" class="form-control form-control-sm ch-sp-input" min="1" step="1" value="4"></div>
                            <div class="table-responsive"><table class="table table-sm table-bordered mb-0"><thead class="table-light"><tr><th class="text-center">From</th><th class="text-center">To</th><th class="text-center">Count</th><th class="text-end">Disc %</th><th></th></tr></thead><tbody id="ch-sp-rev-tbody"></tbody></table></div>
                            <button type="button" class="btn btn-sm btn-outline-primary ch-sp-add" id="ch-sp-rev-add">Add range</button>
                        </div>
                        <div class="ch-sp-col" data-ch-sp-panel="buss">
                            <div class="ch-sp-col-head"><div class="ch-sp-pie-title" title="Std Prc ranges. Disc % updates the B Disc column.">B Disc</div><button type="button" class="ch-sp-min-btn" title="Minimize B Disc" aria-label="Minimize B Disc">−</button></div>
                            <div class="ch-sp-pie-canvas"><canvas id="ch-sp-pie-buss"></canvas></div>
                            <div class="ch-sp-pie-legend" id="ch-sp-leg-buss"></div>
                            <div class="table-responsive"><table class="table table-sm table-bordered mb-0"><thead class="table-light"><tr><th class="text-center">From</th><th class="text-center">To</th><th class="text-center">Count</th><th class="text-end">Disc %</th><th></th></tr></thead><tbody id="ch-sp-buss-tbody"></tbody></table></div>
                            <button type="button" class="btn btn-sm btn-outline-primary ch-sp-add" id="ch-sp-buss-add">Add range</button>
                        </div>
                        <div class="ch-sp-col" data-ch-sp-panel="zs">
                            <div class="ch-sp-col-head"><div class="ch-sp-pie-title" title="Disc % when this page's sold qty is 0. Sold &gt; 0 stays 0%.">0 Sold</div><button type="button" class="ch-sp-min-btn" title="Minimize 0 Sold" aria-label="Minimize 0 Sold">−</button></div>
                            <div class="ch-sp-pie-canvas"><canvas id="ch-sp-pie-zs"></canvas></div>
                            <div class="ch-sp-pie-legend" id="ch-sp-leg-zs"></div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="small fw-semibold mb-0" for="ch-sp-zs-disc">Disc %</label>
                                <input type="number" id="ch-sp-zs-disc" class="form-control form-control-sm ch-sp-input" min="0" step="0.1" value="0">
                            </div>
                            <div class="small text-muted">0 sold SKUs: <strong id="ch-sp-zs-count">0</strong></div>
                        </div>
                        <div class="ch-sp-col" data-ch-sp-panel="cvr">
                            <div class="ch-sp-col-head"><div class="ch-sp-pie-title">CVR up / down</div><button type="button" class="ch-sp-min-btn" title="Minimize CVR" aria-label="Minimize CVR">−</button></div>
                            <div class="ch-sp-pie-canvas"><canvas id="ch-sp-pie-cvr"></canvas></div>
                            <div class="ch-sp-pie-legend" id="ch-sp-leg-cvr"></div>
                            <div class="table-responsive"><table class="table table-sm table-bordered mb-0" id="ch-sp-cvr-table"><thead class="table-light"><tr><th>When</th><th class="text-center">CVR%</th><th class="text-end">Disc %</th><th class="text-center">Count</th></tr></thead>
                                <tbody>
                                    <tr><td><span class="ch-sp-when ch-sp-when-down2">Down</span></td><td class="text-center"><span class="ch-sp-cvr-thresh">&lt;<input type="number" min="0" step="0.1" class="form-control form-control-sm ch-sp-input ch-sp-cvr-down2-lt" value="4"></span></td><td class="text-end"><input type="number" min="0" step="0.1" class="form-control form-control-sm ch-sp-input ch-sp-cvr-down2-disc" value="0"></td><td class="ch-sp-count" id="ch-sp-cvr-down2-count">0</td></tr>
                                    <tr><td><span class="ch-sp-when ch-sp-when-down">Down</span></td><td class="text-center"><span class="ch-sp-cvr-thresh">&lt;<input type="number" min="0" step="0.1" class="form-control form-control-sm ch-sp-input ch-sp-cvr-down-lt" value="7"></span></td><td class="text-end"><input type="number" min="0" step="0.1" class="form-control form-control-sm ch-sp-input ch-sp-cvr-down-disc" value="0"></td><td class="ch-sp-count" id="ch-sp-cvr-down-count">0</td></tr>
                                    <tr><td><span class="ch-sp-when">Flat</span></td><td class="text-center text-muted">mid</td><td class="text-end"><input type="number" min="0" step="0.1" class="form-control form-control-sm ch-sp-input ch-sp-cvr-flat-disc" value="0"></td><td class="ch-sp-count" id="ch-sp-cvr-flat-count">0</td></tr>
                                    <tr><td><span class="ch-sp-when ch-sp-when-up">Up</span></td><td class="text-center"><span class="ch-sp-cvr-thresh">&gt;<input type="number" min="0" step="0.1" class="form-control form-control-sm ch-sp-input ch-sp-cvr-up-gt" value="10"></span></td><td class="text-end"><input type="number" min="0" step="0.1" class="form-control form-control-sm ch-sp-input ch-sp-cvr-up-disc" value="0"></td><td class="ch-sp-count" id="ch-sp-cvr-up-count">0</td></tr>
                                    <tr><td><span class="ch-sp-when ch-sp-when-up2">Up</span></td><td class="text-center"><span class="ch-sp-cvr-thresh">&gt;<input type="number" min="0" step="0.1" class="form-control form-control-sm ch-sp-input ch-sp-cvr-up2-gt" value="15"></span></td><td class="text-end"><input type="number" min="0" step="0.1" class="form-control form-control-sm ch-sp-input ch-sp-cvr-up2-disc" value="0"></td><td class="ch-sp-count" id="ch-sp-cvr-up2-count">0</td></tr>
                                </tbody>
                            </table></div>
                        </div>
                        <div class="ch-sp-col" data-ch-sp-panel="age">
                            <div class="ch-sp-col-head"><div class="ch-sp-pie-title">Age Days</div><button type="button" class="ch-sp-min-btn" title="Minimize Age Days" aria-label="Minimize Age Days">−</button></div>
                            <div class="ch-sp-pie-canvas"><canvas id="ch-sp-pie-age"></canvas></div>
                            <div class="ch-sp-pie-legend" id="ch-sp-leg-age"></div>
                            <div class="table-responsive"><table class="table table-sm table-bordered mb-0"><thead class="table-light"><tr><th class="text-center">From</th><th class="text-center">To</th><th class="text-center">Count</th><th class="text-end">Disc %</th><th></th></tr></thead><tbody id="ch-sp-age-tbody"></tbody></table></div>
                            <button type="button" class="btn btn-sm btn-outline-primary ch-sp-add" id="ch-sp-age-add">Add range</button>
                        </div>
                        <div class="ch-sp-col" data-ch-sp-panel="all">
                            <div class="ch-sp-col-head"><div class="ch-sp-pie-title">All discounts</div><button type="button" class="ch-sp-min-btn" title="Minimize All discounts" aria-label="Minimize All discounts">−</button></div>
                            <div class="ch-sp-pie-canvas"><canvas id="ch-sp-pie-all"></canvas></div>
                            <div class="ch-sp-pie-legend" id="ch-sp-leg-all"></div>
                            <div class="table-responsive"><table class="table table-sm table-bordered mb-0"><thead class="table-light"><tr><th>Discount</th><th class="text-center">Count</th></tr></thead><tbody id="ch-sp-all-tbody"></tbody></table></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="small text-muted me-auto" id="ch-sp-status"></div>
                    <button type="button" class="btn btn-sm btn-primary" id="ch-sp-apply"><i class="fas fa-save me-1"></i> Save and Apply</button>
                </div>
            </div>
        </div>
    </div>
@endif

@if($channelStdPrcPart === 'script' || $channelStdPrcPart === 'all')
@php
    $chStdPageMaps = app(\App\Http\Controllers\ProductMaster\InvDaysController::class)
        ->stdPrcPageMaps(! in_array($channelPromoChannel ?? '', ['ebay1', 'ebay2', 'ebay3'], true));
@endphp
        const CH_STD_DIL_DEFAULTS = [
            { min: 0, max: 0, disc: 0 }, { min: 0.1, max: 10, disc: 0 }, { min: 10, max: 25, disc: 0 },
            { min: 25, max: 50, disc: 0 }, { min: 50, max: 100, disc: 0 }, { min: 100, max: 9999, disc: 0 },
        ];
        const CH_STD_AGE_DEFAULTS = [
            { min: 0, max: 30, disc: 0 }, { min: 31, max: 60, disc: 0 }, { min: 61, max: 90, disc: 0 },
            { min: 91, max: 180, disc: 0 }, { min: 181, max: 365, disc: 0 }, { min: 366, max: 9999, disc: 0 },
        ];
        const CH_STD_CVR_DEFAULT = { down2_lt: 4, down2_disc: 0, down_lt: 7, down_disc: 0, up_gt: 10, up_disc: 0, up2_gt: 15, up2_disc: 0, flat_disc: 0 };
        const CH_STD_REV_DEFAULTS = [{ min: 1, max: 2, disc: 4 }, { min: 2, max: 3, disc: 4 }];
        const CH_STD_BUSS_DEFAULTS = [{ min: 0, max: 15, disc: 0 }, { min: 15, max: 50, disc: 0 }, { min: 50, max: 9999, disc: 0 }];
        let chStdZeroSoldDisc = 0;
        let chStdMinNpft = 10;
        const CH_STD_CLEARANCE_URL = @json(route('inv.days.clearance.yes'));
        let chStdClearanceSet = null;
        let chStdClearanceLoading = null;
        const CH_STD_COLORS = ['#6f42c1', '#3b82f6', '#14b8a6', '#22c55e', '#84cc16', '#eab308', '#f59e0b', '#ea580c', '#dc3545', '#e83e8c', '#0ea5e9', '#64748b'];
        let chStdDil = CH_STD_DIL_DEFAULTS.map(function(r) { return Object.assign({}, r); });
        let chStdAge = CH_STD_AGE_DEFAULTS.map(function(r) { return Object.assign({}, r); });
        let chStdRev = CH_STD_REV_DEFAULTS.map(function(r) { return Object.assign({}, r); });
        let chStdBuss = CH_STD_BUSS_DEFAULTS.map(function(r) { return Object.assign({}, r); });
        let chStdCvr = Object.assign({}, CH_STD_CVR_DEFAULT);
        let chStdReviewMax = 4;
        const chStdCharts = {};
        let chStdPieGen = 0;
        let chStdHistChart = null;
        let chStdHistLive = {};

        function chStdEsc(s) {
            return String(s == null ? '' : s).replace(/[&<>"']/g, function(ch) {
                return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch];
            });
        }
        function chStdNormRange(r) {
            let min = Number(r && r.min), max = Number(r && r.max);
            if (!isFinite(min) || !isFinite(max)) return null;
            if (max < min) { const t = min; min = max; max = t; }
            let disc = Number(r && r.disc);
            if (!isFinite(disc) || disc < 0) disc = 0;
            if (disc > 100) disc = 100;
            return { min: min, max: max, disc: disc };
        }
        function chStdNormCvr(raw) {
            const out = Object.assign({}, CH_STD_CVR_DEFAULT);
            if (!raw) return out;
            const hadDown2 = raw.down2_disc !== undefined && raw.down2_disc !== null && raw.down2_disc !== '';
            const hadUp2 = raw.up2_disc !== undefined && raw.up2_disc !== null && raw.up2_disc !== '';
            ['down2_lt', 'down_lt', 'up_gt', 'up2_gt', 'down2_disc', 'down_disc', 'up_disc', 'up2_disc', 'flat_disc'].forEach(function(key) {
                const n = Number(raw[key]);
                if (!isFinite(n) || n < 0) return;
                out[key] = key.indexOf('disc') !== -1 && n > 100 ? 100 : n;
            });
            if (!hadDown2) out.down2_disc = out.down_disc;
            if (!hadUp2) out.up2_disc = out.up_disc;
            return out;
        }
        function chStdRowStd(d) {
            return (typeof chPromoStdBase === 'function') ? (Number(chPromoStdBase(d)) || 0) : 0;
        }
        function chStdLowFactor(std) {
            const n = Number(std);
            return (isFinite(n) && n > 0 && n < 15) ? 0.5 : 1;
        }
        function chStdScaleDisc(std, disc) {
            const n = Number(disc);
            if (!isFinite(n)) return 0;
            const factor = chStdLowFactor(std);
            if (factor === 1) return n;
            return Math.round(n * factor * 100) / 100;
        }
        function chStdRangeDisc(value, rules, allowNegative) {
            const n = Number(value);
            if (!isFinite(n) || (!allowNegative && n < 0) || !rules || !rules.length) return 0;
            const last = rules.length - 1;
            for (let i = 0; i < rules.length; i++) {
                const rule = rules[i];
                if (!rule) continue;
                let min = Number(rule.min), max = Number(rule.max);
                if (!isFinite(min) || !isFinite(max)) continue;
                if (max < min) { const t = min; min = max; max = t; }
                const hit = Math.abs(max - min) < 0.00001 ? Math.abs(n - min) < 0.00001 : (n >= min && (i === last ? n <= max : n < max));
                if (!hit) continue;
                const disc = Number(rule.disc);
                return isFinite(disc) && disc > 0 ? disc : 0;
            }
            return 0;
        }
        function chStdNum(d, keys) {
            if (!d) return 0;
            for (let i = 0; i < keys.length; i++) {
                const n = Number(d[keys[i]]);
                if (isFinite(n)) return n;
            }
            return 0;
        }
        // Same Amazon A L30 / Sess30 / L60 / OV L30 / INV map the cron pricer reads.
        let chStdAmzBySku = @json((object) ($chStdPageMaps['amazon'] ?? []));
        function chStdLoadAmzMap() {
            return $.Deferred().resolve(chStdAmzBySku).promise();
        }
        /** eBay 1 / 2 / 3 price from their own row (INV, OV L30, CVR 30 / 45, eBay L30), never the Amazon map. */
        function chStdIsEbay() {
            const ch = (typeof CHANNEL_PROMO_CHANNEL === 'string') ? CHANNEL_PROMO_CHANNEL : '';
            return ch === 'ebay1' || ch === 'ebay2' || ch === 'ebay3';
        }
        function chStdEbayCvr45(d) {
            if (d && d.CVR_45 != null && d.CVR_45 !== '' && isFinite(Number(d.CVR_45))) return Number(d.CVR_45);
            return (chStdNum(d, ['SCVR']) + chStdNum(d, ['CVR_60'])) / 2;
        }
        function chStdAmzMetric(d) {
            if (chStdIsEbay() || !chStdAmzBySku || !d) return null;
            const hit = chStdAmzBySku[chStdSkuKey(chStdRowSku(d))];
            if (!hit || !hit.length) return null;
            return {
                a30: Number(hit[0]) || 0,
                s30: Number(hit[1]) || 0,
                a60: Number(hit[2]) || 0,
                s60: Number(hit[3]) || 0,
                ov: Number(hit[4]) || 0,
                inv: Number(hit[5]) || 0,
            };
        }
        function chStdCvrInputs(d) {
            const m = chStdAmzMetric(d);
            if (m) return m;
            return {
                a30: chStdNum(d, ['A_L30', 'a_l30', 'al30', 'AL30']),
                s30: chStdNum(d, ['Sess30', 'sess30', 'sessions_l30']),
                a60: chStdNum(d, ['units_ordered_l60', 'a_l60', 'al60']),
                s60: chStdNum(d, ['sessions_l60', 'sess60']),
            };
        }
        function chStdSkipsCvr() {
            const ch = (typeof CHANNEL_PROMO_CHANNEL === 'string') ? CHANNEL_PROMO_CHANNEL : '';
            return ch === 'bestbuy';
        }
        if (chStdSkipsCvr()) {
            $('[data-ch-sp-panel="cvr"]').hide();
        }
        function chStdCvrLive(d) {
            if (chStdSkipsCvr()) return 0;
            if (chStdIsEbay()) return chStdNum(d, ['SCVR']);
            const inp = chStdCvrInputs(d);
            return inp.s30 > 0 ? (inp.a30 / inp.s30) * 100 : 0;
        }
        function chStdCvrTrend(d) {
            if (chStdSkipsCvr()) return 'flat';
            if (chStdIsEbay()) {
                const cvr30 = chStdNum(d, ['SCVR']);
                const cvr45 = chStdEbayCvr45(d);
                if (!(cvr30 > 0) || cvr30 < cvr45 - 0.1) return 'down';
                if (cvr30 > cvr45 + 0.1) return 'up';
                return 'flat';
            }
            const inp = chStdCvrInputs(d);
            const cvr = inp.s30 > 0 ? (inp.a30 / inp.s30) * 100 : 0;
            const sess45 = (inp.s30 + inp.s60) / 2;
            const cvr45 = sess45 > 0 ? (((inp.a30 + inp.a60) / 2) / sess45) * 100 : 0;
            if (cvr === 0 || cvr < cvr45 - 0.1) return 'down';
            if (cvr > cvr45 + 0.1) return 'up';
            return 'flat';
        }
        function chStdCvrSlabs(cfg) {
            const down = [
                { key: 'down2', lt: Number(cfg.down2_lt), disc: Number(cfg.down2_disc) || 0 },
                { key: 'down', lt: Number(cfg.down_lt), disc: Number(cfg.down_disc) || 0 },
            ].filter(function(s) { return isFinite(s.lt) && s.lt > 0; });
            down.sort(function(a, b) { return a.lt - b.lt; });
            const up = [
                { key: 'up', gt: Number(cfg.up_gt), disc: Number(cfg.up_disc) || 0 },
                { key: 'up2', gt: Number(cfg.up2_gt), disc: Number(cfg.up2_disc) || 0 },
            ].filter(function(s) { return isFinite(s.gt) && s.gt >= 0; });
            up.sort(function(a, b) { return b.gt - a.gt; });
            return { down: down, up: up };
        }
        function chStdCvrMatch(d, cfg) {
            const cvr = chStdCvrLive(d);
            const trend = chStdCvrTrend(d);
            const slabs = chStdCvrSlabs(cfg);
            if (trend === 'down') {
                for (let i = 0; i < slabs.down.length; i++) if (cvr < slabs.down[i].lt) return slabs.down[i];
            }
            if (trend === 'up') {
                for (let i = 0; i < slabs.up.length; i++) if (cvr > slabs.up[i].gt) return slabs.up[i];
            }
            return null;
        }
        // Same Shopify age clock the cron pricer reads (kept warm by inv-days:warm-age-map).
        let chStdAgeBySku = @json((object) ($chStdPageMaps['age_days'] ?? []));
        function chStdSkuKey(sku) {
            return String(sku == null ? '' : sku).replace(/\u00a0/g, ' ').replace(/\s+/g, ' ').trim().toLowerCase();
        }
        function chStdRowSku(d) {
            if (!d) return '';
            if (typeof chPromoSku === 'function') {
                const fromPromo = chPromoSku(d);
                if (fromPromo) return fromPromo;
            }
            return d['(Child) sku'] || d.sku || d.SKU || d.seller_sku || '';
        }
        function chStdStockForAge(d) {
            const m = chStdAmzMetric(d);
            if (m) return m.inv;
            const ch = (typeof CHANNEL_PROMO_CHANNEL === 'string') ? CHANNEL_PROMO_CHANNEL : '';
            if ((ch === 'doba' || ch === 'doba_withoutship') && d && d.shopify_inv != null && d.shopify_inv !== '') {
                return Number(d.shopify_inv) || 0;
            }
            if (typeof chPromoInv === 'function') return Number(chPromoInv(d)) || 0;
            if (d && d.INV != null && d.INV !== '') return Number(d.INV) || 0;
            if (d && d.inv != null && d.inv !== '') return Number(d.inv) || 0;
            if (d && d.shopify_inv != null && d.shopify_inv !== '') return Number(d.shopify_inv) || 0;
            return 0;
        }
        function chStdLoadAgeMap() {
            return $.Deferred().resolve(chStdAgeBySku).promise();
        }
        function chStdAgeDays(d) {
            let raw = d && (d.age_days != null && d.age_days !== '' ? d.age_days : d.age);
            if ((raw == null || raw === '') && chStdAgeBySku && d) {
                const hit = chStdAgeBySku[chStdSkuKey(chStdRowSku(d))];
                if (hit != null && hit !== '') {
                    raw = hit;
                    d.age_days = hit;
                }
            }
            const n = Number(raw);
            return isFinite(n) ? n : null;
        }
        function chStdReviews(d) {
            return chStdNum(d, ['review_count', 'reviews', 'Reviews', 'rating_count', 'ratings']);
        }
        function chStdUsesMinNpft() {
            return typeof CHANNEL_PROMO_CHANNEL !== 'undefined' && CHANNEL_PROMO_CHANNEL !== '';
        }
        /** This page's own sold-units field (CHANNEL_PROMO_CFG.soldField), e.g. 'doba L30', 'TT L30', 'al30'. */
        function chStdPageSoldField() {
            try {
                if (typeof chPromoCfg !== 'undefined' && chPromoCfg && chPromoCfg.soldField) return String(chPromoCfg.soldField);
            } catch (e) { /* chPromoCfg still in TDZ */ }
            return '';
        }
        function chStdSiteL30(d) {
            if (!d) return 0;
            // Marketplace pages must project with their own units, never the overall (OV) units.
            const soldField = chStdPageSoldField();
            if (soldField && d[soldField] != null && d[soldField] !== '') {
                const own = Number(d[soldField]);
                return isFinite(own) && own > 0 ? own : 0;
            }
            if (typeof CHANNEL_PROMO_CHANNEL !== 'undefined' && CHANNEL_PROMO_CHANNEL === 'bestbuy') {
                const n = Number(d['BB L30']);
                return isFinite(n) && n > 0 ? n : 0;
            }
            if (typeof CHANNEL_PROMO_CHANNEL !== 'undefined' && CHANNEL_PROMO_CHANNEL === 'purchasing_power') {
                const n = Number(d['PP L30']);
                return isFinite(n) && n > 0 ? n : 0;
            }
            // Other channels: this page's own L30 is `L30` when a separate `ov_l30` (CP Master OV L30) exists; otherwise fall back to OV L30.
            if (d.ov_l30 != null && d.ov_l30 !== '' && d.L30 != null && d.L30 !== '') {
                const n = Number(d.L30);
                return isFinite(n) && n > 0 ? n : 0;
            }
            return chStdOvL30(d);
        }
        function chStdOvL30(d) {
            if (!d) return 0;
            return (typeof chPromoOvL30 === 'function') ? (Number(chPromoOvL30(d)) || 0) : 0;
        }
        function chStdMinNpftNow() {
            if (!chStdUsesMinNpft()) return 0;
            const $inp = $('#ch-sp-min-npft');
            if (!$inp.length) return chStdMinNpft;
            const n = Number($inp.val());
            if (!isFinite(n)) return chStdMinNpft;
            return Math.min(99, Math.max(0, n));
        }
        function chStdClearanceKey(sku) {
            return String(sku || '').replace(/\u00a0/g, ' ').replace(/\s+/g, ' ').trim().toUpperCase();
        }
        function chStdIsClearance(d) {
            if (!chStdClearanceSet) return false;
            return !!chStdClearanceSet[chStdClearanceKey(chStdRowSku(d))];
        }
        function chStdLoadClearance() {
            if (!chStdUsesMinNpft()) return $.Deferred().resolve().promise();
            if (chStdClearanceSet) return $.Deferred().resolve(chStdClearanceSet).promise();
            if (chStdClearanceLoading) return chStdClearanceLoading;
            chStdClearanceLoading = $.ajax({
                url: CH_STD_CLEARANCE_URL,
                method: 'GET',
                headers: { 'Accept': 'application/json' },
            }).then(function(res) {
                const set = {};
                ((res && res.skus) || []).forEach(function(sku) { set[chStdClearanceKey(sku)] = true; });
                chStdClearanceSet = set;
                return set;
            }, function() {
                chStdClearanceSet = {};
                return chStdClearanceSet;
            });
            return chStdClearanceLoading;
        }
        window.chStdIsClearance = chStdIsClearance;
        window.chStdMinNpftNow = chStdMinNpftNow;
        window.chStdLoadClearance = chStdLoadClearance;
        function chStdAdsFrac() {
            if (typeof chPromoAdsFrac !== 'function') return 0;
            const n = Number(chPromoAdsFrac());
            return isFinite(n) && n > 0 ? n : 0;
        }
        function chStdNpftAt(price, d) {
            const p = Number(price) || 0;
            if (!(p > 0)) return 0;
            const margin = chStdMargin(d);
            const lp = (typeof chPromoLp === 'function') ? (Number(chPromoLp(d)) || 0) : 0;
            const ship = chStdExcludesShip() ? 0 : ((typeof chPromoShipCost === 'function') ? (Number(chPromoShipCost(d)) || 0) : 0);
            return ((p * margin - ship - lp) / p) * 100 - (chStdAdsFrac() * 100);
        }
        function chStdPriceForMinNpft(d, minNpft) {
            const margin = chStdMargin(d);
            const denom = margin - chStdAdsFrac() - (Number(minNpft) || 0) / 100;
            if (!(denom > 0.0001)) return 0;
            const lp = (typeof chPromoLp === 'function') ? (Number(chPromoLp(d)) || 0) : 0;
            const ship = chStdExcludesShip() ? 0 : ((typeof chPromoShipCost === 'function') ? (Number(chPromoShipCost(d)) || 0) : 0);
            const raw = (lp + ship) / denom;
            if (!(raw > 0)) return 0;
            return Math.ceil(raw * 100 - 1e-6) / 100;
        }
        function chStdRaiseToMinNpft(price, d, minNpft) {
            if (!chStdUsesMinNpft() || chStdIsClearance(d)) return price;
            const floorPct = Number(minNpft);
            const current = Number(price) || 0;
            if (!(floorPct > 0) || !(current > 0)) return current;
            if (chStdNpftAt(current, d) >= floorPct - 0.001) return current;
            const raised = chStdPriceForMinNpft(d, floorPct);
            return raised > current ? raised : current;
        }
        function chStdPriceBeforeMinNpft(d, draft) {
            if (!d) return 0;
            if (typeof chPromoIsChildRow === 'function' && !chPromoIsChildRow(d)) return 0;
            if (!(chStdStockForAge(d) > 0) && !chStdRowIsZeroSold(d)) return 0;
            const std = (typeof chPromoStdBase === 'function') ? chPromoStdBase(d) : 0;
            if (!(std > 0)) return 0;
            const sum = chStdSumDisc(d, draft || chStdDraftNow());
            let raw = std * (1 - Math.min(99.99, sum) / 100);
            if (typeof CHANNEL_PROMO_CHANNEL !== 'undefined' && CHANNEL_PROMO_CHANNEL === 'shopify_b2b') {
                const ship = (typeof chPromoShipCost === 'function') ? (Number(chPromoShipCost(d)) || 0) : 0;
                raw -= ship;
            }
            return Math.round(raw * 100) / 100;
        }
        /** S PRC the cell actually paints (saved on eBay; live rule price on other channels). */
        function chStdShownSpriceForMark(d) {
            if (!d) return 0;
            if (typeof ebayDisplayedSprice === 'function') {
                const n = Number(ebayDisplayedSprice(d));
                if (n > 0) return n;
            }
            if (typeof ebay2DisplayedSprice === 'function') {
                const n = Number(ebay2DisplayedSprice(d));
                if (n > 0) return n;
            }
            if (typeof ebay3DisplayedSprice === 'function') {
                const n = Number(ebay3DisplayedSprice(d));
                if (n > 0) return n;
            }
            if (typeof chStdPriceForRow === 'function') {
                const n = Number(chStdPriceForRow(d));
                if (n > 0) return n;
            }
            const saved = Number(d.SPRICE_SERVER != null ? d.SPRICE_SERVER : (d.SPRICE || d.sprice));
            return saved > 0 ? saved : 0;
        }
        /** Same SNPFT% the table cell paints (0 decimals). */
        function chStdShownSnpftForMark(d) {
            if (!d) return null;
            if (typeof ebayDilComputedSnpft === 'function') {
                const dil = ebayDilComputedSnpft(d);
                if (dil != null && isFinite(dil)) return dil;
            }
            if (typeof ebayComputeSgpftFromSprice === 'function') {
                const sgpft = ebayComputeSgpftFromSprice(d);
                if (sgpft == null || !isFinite(sgpft)) return null;
                const ads = (typeof EBAY_CHANNEL_ADS_PCT !== 'undefined') ? (parseFloat(EBAY_CHANNEL_ADS_PCT) || 0) : (chStdAdsFrac() * 100);
                return sgpft - ads;
            }
            if (typeof ebay2ComputeSgpftFromSprice === 'function') {
                const sgpft = ebay2ComputeSgpftFromSprice(d);
                if (sgpft == null || !isFinite(sgpft)) return null;
                const ads = (typeof EBAY2_CHANNEL_ADS_PCT !== 'undefined') ? (parseFloat(EBAY2_CHANNEL_ADS_PCT) || 0) : (chStdAdsFrac() * 100);
                return sgpft - ads;
            }
            if (typeof ebay3ComputeSgpftFromSprice === 'function') {
                const sgpft = ebay3ComputeSgpftFromSprice(d);
                if (sgpft == null || !isFinite(sgpft)) return null;
                const ads = (typeof EBAY3_CHANNEL_ADS_PCT !== 'undefined') ? (parseFloat(EBAY3_CHANNEL_ADS_PCT) || 0) : (chStdAdsFrac() * 100);
                return sgpft - ads;
            }
            const shown = chStdShownSpriceForMark(d);
            if (!(shown > 0)) return null;
            return chStdNpftAt(shown, d);
        }
        function chStdMinNpftWasApplied(d, draft) {
            if (window.minNpftSpriceMark && typeof window.minNpftSpriceMark.shouldMark === 'function') {
                return window.minNpftSpriceMark.shouldMark(d);
            }
            if (!chStdUsesMinNpft() || !d || chStdIsClearance(d)) return false;
            const floor = (draft && draft.minNpft != null) ? Number(draft.minNpft) : Number(chStdMinNpft);
            if (!(floor > 0)) return false;
            const snpft = chStdShownSnpftForMark(d);
            if (snpft == null || !isFinite(snpft)) return false;
            return Math.round(snpft) === Math.round(floor);
        }
        window.chStdMinNpftWasApplied = chStdMinNpftWasApplied;
        function chStdNpftMarkHtml(d) {
            if (!chStdMinNpftWasApplied(d)) return '';
            const pct = Number(chStdMinNpft);
            return '<span class="ch-npft-mark" title="SNPFT is at Min NPFT ' + pct + '%">!</span>';
        }
        window.chStdNpftMarkHtml = chStdNpftMarkHtml;
        function chStdExcludesShip() {
            if (typeof CHANNEL_PROMO_CHANNEL === 'undefined') return false;
            return CHANNEL_PROMO_CHANNEL === 'shopify_b2b'
                || CHANNEL_PROMO_CHANNEL === 'faire'
                || CHANNEL_PROMO_CHANNEL === 'wayfair'
                || CHANNEL_PROMO_CHANNEL === 'topdawg'
                || CHANNEL_PROMO_CHANNEL === 'depop'
                || CHANNEL_PROMO_CHANNEL === 'mercari_woship';
        }
        function chStdSumDisc(d, draft) {
            draft = draft || { dil: chStdDil, age: chStdAge, cvr: chStdCvr, reviews: chStdRev, reviewMax: chStdReviewMax, buss: chStdBussRulesLive(), zeroSoldDisc: chStdZeroSoldDisc };
            // Amazon INV = 0 still allows the page 0 Sold discount (AB L30 = 0, page INV > 0).
            if (!(chStdStockForAge(d) > 0)) {
                if (!chStdRowIsZeroSold(d)) return 0;
                const stdOnly = chStdRowStd(d);
                const zsOnly = Number(draft.zeroSoldDisc != null ? draft.zeroSoldDisc : chStdZeroSoldDisc) || 0;
                return Math.min(99.99, Math.max(0, chStdScaleDisc(stdOnly, zsOnly)));
            }
            const std = chStdRowStd(d);
            const dil = chStdDilPct(d);
            const ageDisc = chStdAgeDisc(d, draft.age);
            const dilDisc = chStdScaleDisc(std, chStdRangeDisc(dil, draft.dil));
            const reviews = chStdReviews(d);
            let revDisc = 0;
            if (reviews > 0 && reviews < draft.reviewMax) {
                for (let i = 0; i < draft.reviews.length; i++) {
                    const rule = draft.reviews[i];
                    if (reviews >= rule.min && reviews <= rule.max) { revDisc = Number(rule.disc) || 0; break; }
                }
            }
            revDisc = chStdScaleDisc(std, revDisc);
            const cvrDisc = chStdScaleDisc(std, chStdCvrDiscPct(d, draft));
            const bussRules = draft.buss || chStdBuss;
            const bussDisc = chStdRangeDisc(std, bussRules);
            const zsRaw = chStdRowIsZeroSold(d) ? (Number(draft.zeroSoldDisc != null ? draft.zeroSoldDisc : chStdZeroSoldDisc) || 0) : 0;
            const zsDisc = chStdScaleDisc(std, zsRaw);
            return Math.min(99.99, Math.max(0, ageDisc + dilDisc + cvrDisc + revDisc + bussDisc + zsDisc));
        }
        function chStdFullDataset() {
            if (typeof window !== 'undefined' && Array.isArray(window.allTableData) && window.allTableData.length) return window.allTableData;
            if (typeof allTableData !== 'undefined' && Array.isArray(allTableData) && allTableData.length) return allTableData;
            if (typeof abAllRows !== 'undefined' && Array.isArray(abAllRows) && abAllRows.length) return abAllRows;
            return null;
        }
        function chStdRows() {
            const out = [];
            const take = function(d) {
                if (typeof chPromoIsChildRow === 'function' && !chPromoIsChildRow(d)) return;
                if (!(chStdStockForAge(d) > 0) && !chStdRowIsZeroSold(d)) return;
                out.push(d);
            };
            const full = chStdFullDataset();
            if (full) {
                full.forEach(take);
                if (out.length) return out;
            }
            if (typeof chPromoEachTableRow === 'function') chPromoEachTableRow(function(row, d) { take(d); });
            return out;
        }
        /**
         * "Current <page> L30": this page's actual sold units x the L30 average sold price, with today's
         * margin / LP / ship. Includes SKUs whose stock is now 0 (chStdRows() drops those) and ignores the draft rules.
         */
        // Doba L30 totals from doba_daily_data (same numbers as /doba/daily-sales), used only to find orders that
        // have no row in this table (see chStdAddUnmatchedSales).
        let chStdCurrentServer = null;
        let chStdCurrentServerLoading = false;
        function chStdLoadCurrentServer() {
            if (chStdCurrentServer || chStdCurrentServerLoading) return;
            const ch = (typeof CHANNEL_PROMO_CHANNEL === 'string') ? CHANNEL_PROMO_CHANNEL : '';
            if (ch !== 'doba' && ch !== 'doba_withoutship') return;
            chStdCurrentServerLoading = true;
            $.ajax({
                url: ch === 'doba_withoutship' ? '/doba/summary-metrics-withoutship' : '/doba/summary-metrics',
                method: 'GET',
                headers: { 'Accept': 'application/json' },
            }).done(function(res) {
                const x = res && res.success && res.data ? res.data : null;
                if (!x) return;
                chStdCurrentServer = {
                    units: Number(x.total_quantity) || 0,
                    sales: Number(x.total_sales) || 0,
                    cogs: Number(x.total_cogs) || 0,
                    gross: Number(x.total_pft) || 0,
                };
                if (typeof chStdRefresh === 'function') chStdRefresh();
            }).always(function() { chStdCurrentServerLoading = false; });
        }
        /**
         * Doba orders whose SKU is not a row in this table (e.g. a sold-out SKU removed from the product master)
         * never reach the row sum above. Add them back as: server L30 totals minus what the rows already hold,
         * priced at the rows' own average margin and COGS ratio (no LP / ship is known for them).
         */
        function chStdAddUnmatchedSales(b, adsPct) {
            const s = chStdCurrentServer;
            if (!s || !(b.sales > 0)) return b;
            const extraUnits = s.units - b.units;
            const extraSales = s.sales - b.sales;
            if (!(extraUnits >= 1) || !(extraSales > 1)) return b;
            const k = extraSales / b.sales;
            const grossAdd = b.gross * k;
            b.gross += grossAdd;
            b.net += grossAdd - (extraSales * (Number(adsPct) || 0) / 100);
            b.cogs += b.cogs * k;
            b.sales += extraSales;
            b.units += extraUnits;
            return b;
        }
        function chStdCurrentSiteBucket(adsPct) {
            const b = { gross: 0, net: 0, sales: 0, cogs: 0, units: 0 };
            const noShip = chStdExcludesShip() || (typeof CHANNEL_PROMO_CHANNEL !== 'undefined' && CHANNEL_PROMO_CHANNEL === 'doba_withoutship');
            const take = function(d) {
                if (!d || d.is_parent_summary) return;
                if (typeof chPromoIsChildRow === 'function' && !chPromoIsChildRow(d)) return;
                const units = chStdSiteL30(d);
                if (!(units > 0)) return;
                let price = Number(d.l30_avg_price);
                if (!(isFinite(price) && price > 0)) price = (typeof chPromoPrice === 'function') ? (Number(chPromoPrice(d)) || 0) : 0;
                if (!(price > 0)) return;
                const lp = (typeof chPromoLp === 'function') ? chPromoLp(d) : 0;
                const ship = noShip ? 0 : ((typeof chPromoShipCost === 'function') ? chPromoShipCost(d) : 0);
                const gross = (price * chStdMargin(d)) - ship - lp;
                const net = gross - (price * (Number(adsPct) || 0) / 100);
                b.gross += gross * units; b.net += net * units; b.sales += price * units; b.cogs += lp * units; b.units += units;
            };
            const full = chStdFullDataset();
            if (full) full.forEach(take);
            else if (typeof chPromoEachTableRow === 'function') chPromoEachTableRow(function(row, d) { take(d); });
            return b;
        }
        function chStdMoney(n) {
            const v = Number(n) || 0;
            return (v < 0 ? '-' : '') + '$' + Math.abs(v).toLocaleString('en-US', { maximumFractionDigits: 0 });
        }
        function chStdMargin(d) {
            if (typeof chPromoTakehomeMargin === 'function') {
                const m = Number(chPromoTakehomeMargin(d));
                if (isFinite(m) && m > 0) return m;
            }
            return 0.80;
        }
        function chStdReadRanges(tbody, minCls, maxCls, discCls) {
            const rules = [];
            $(tbody).find('tr').each(function() {
                const rule = chStdNormRange({ min: $(this).find(minCls).val(), max: $(this).find(maxCls).val(), disc: $(this).find(discCls).val() });
                if (rule) rules.push(rule);
            });
            return rules;
        }
        function chStdReadDraft() {
            return {
                dil: chStdReadRanges('#ch-sp-dil-tbody', '.ch-sp-dil-min', '.ch-sp-dil-max', '.ch-sp-dil-disc'),
                age: chStdReadRanges('#ch-sp-age-tbody', '.ch-sp-age-min', '.ch-sp-age-max', '.ch-sp-age-disc'),
                reviews: chStdReadRanges('#ch-sp-rev-tbody', '.ch-sp-rev-min', '.ch-sp-rev-max', '.ch-sp-rev-disc'),
                buss: chStdReadRanges('#ch-sp-buss-tbody', '.ch-sp-buss-min', '.ch-sp-buss-max', '.ch-sp-buss-disc'),
                zeroSoldDisc: (function() { const n = Number($('#ch-sp-zs-disc').val()); return isFinite(n) ? Math.min(100, Math.max(0, n)) : 0; })(),
                minNpft: chStdMinNpftNow(),
                reviewMax: (function() { const n = parseInt($('#ch-sp-review-max').val(), 10); return isFinite(n) && n > 0 ? n : 4; })(),
                cvr: chStdNormCvr({
                    down2_lt: $('#chStdPrcModal .ch-sp-cvr-down2-lt').val(), down2_disc: $('#chStdPrcModal .ch-sp-cvr-down2-disc').val(),
                    down_lt: $('#chStdPrcModal .ch-sp-cvr-down-lt').val(), down_disc: $('#chStdPrcModal .ch-sp-cvr-down-disc').val(),
                    up_gt: $('#chStdPrcModal .ch-sp-cvr-up-gt').val(), up_disc: $('#chStdPrcModal .ch-sp-cvr-up-disc').val(),
                    up2_gt: $('#chStdPrcModal .ch-sp-cvr-up2-gt').val(), up2_disc: $('#chStdPrcModal .ch-sp-cvr-up2-disc').val(),
                    flat_disc: $('#chStdPrcModal .ch-sp-cvr-flat-disc').val(),
                }),
            };
        }
        function chStdRangeRow(prefix, rule) {
            return '<tr><td class="text-center"><input type="number" step="0.1" class="form-control form-control-sm ch-sp-input ' + prefix + '-min" value="' + rule.min + '"></td>'
                + '<td class="text-center"><input type="number" step="0.1" class="form-control form-control-sm ch-sp-input ' + prefix + '-max" value="' + rule.max + '"></td>'
                + '<td class="ch-sp-count ' + prefix + '-count">0</td>'
                + '<td class="text-end"><input type="number" min="0" step="0.1" class="form-control form-control-sm ch-sp-input ' + prefix + '-disc" value="' + rule.disc + '"></td>'
                + '<td class="text-center"><button type="button" class="btn btn-sm ch-sp-del ' + prefix + '-del" title="Remove">&times;</button></td></tr>';
        }
        // `money` (optional): { key: dollars }. When given, the legend shows SKU count + $ off instead of count + %,
        // and the daily history keeps recording the $ off value.
        function chStdLegend(el, slices, counts, chart, money) {
            const total = slices.reduce(function(sum, s) { return sum + (counts[s.key] || 0); }, 0);
            $(el).html(slices.map(function(s) {
                const n = counts[s.key] || 0;
                const pct = total > 0 ? Math.round((n / total) * 100) : 0;
                chStdHistLive[chart + ':' + s.key] = money ? Math.round(Number(money[s.key]) || 0) : n;
                const third = money ? chStdMoney(money[s.key]) : (pct + '%');
                return '<div class="ch-sp-leg-row"><span class="ch-sp-swatch" style="background:' + s.color + '"></span><span>' + chStdEsc(s.label) + '</span><strong>' + n + '</strong><span class="ch-sp-leg-pct">' + third + '</span>'
                    + '<button type="button" class="ch-sp-hist-dot" data-chart="' + chStdEsc(chart) + '" data-band="' + chStdEsc(s.key) + '" data-label="' + chStdEsc(s.label) + '" data-color="' + chStdEsc(s.color) + '" style="background:' + s.color + ';" title="' + chStdEsc(s.label) + ' daily history"></button></div>';
            }).join(''));
        }
        function chStdDrawBar(id, slices, counts) {
            const canvas = document.getElementById(id);
            if (!canvas || typeof Chart === 'undefined') return;
            const bound = (typeof Chart.getChart === 'function') ? Chart.getChart(canvas) : chStdCharts[id];
            if (bound) bound.destroy();
            const total = slices.reduce(function(sum, s) { return sum + (counts[s.key] || 0); }, 0);
            chStdCharts[id] = new Chart(canvas.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: slices.map(function(s) { return s.label; }),
                    datasets: [{
                        data: slices.map(function(s) { return counts[s.key] || 0; }),
                        backgroundColor: slices.map(function(s) { return s.color; }),
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
        function chStdDrawBars(jobs) {
            const gen = ++chStdPieGen;
            const draw = function() {
                if (gen !== chStdPieGen) return;
                jobs.forEach(function(job) { chStdDrawBar(job.id, job.slices, job.counts); });
            };
            if (typeof window.loadChartJs === 'function') window.loadChartJs().then(draw).catch(function() {});
            else draw();
        }
        function chStdInRange(value, rule, isLast) {
            const n = Number(value);
            if (!rule || !isFinite(n)) return false;
            let min = Number(rule.min), max = Number(rule.max);
            if (max < min) { const t = min; min = max; max = t; }
            if (Math.abs(max - min) < 0.00001) return Math.abs(n - min) < 0.00001;
            return n >= min && (isLast ? n <= max : n < max);
        }
        function chStdRefresh() {
            const draft = chStdReadDraft();
            const dilCounts = { outside: 0 }, ageCounts = { none: 0 }, revCounts = { none: 0 }, bussCounts = { outside: 0 };
            const cvrCounts = { down2: 0, down: 0, flat: 0, up: 0, up2: 0 };
            draft.dil.forEach(function(r, i) { dilCounts['d' + i] = 0; });
            draft.age.forEach(function(r, i) { ageCounts['a' + i] = 0; });
            draft.reviews.forEach(function(r, i) { revCounts['r' + i] = 0; });
            draft.buss.forEach(function(r, i) { bussCounts['u' + i] = 0; });
            const dollars = { age: 0, dil: 0, cvr: 0, rev: 0, buss: 0, zs: 0 };
            let clearanceIgnored = 0;
            const pctTotals = { age: 0, dil: 0, cvr: 0, rev: 0, buss: 0, zs: 0 };
            const skuHits = { age: 0, dil: 0, cvr: 0, rev: 0, buss: 0, zs: 0, all: 0 };
            let npftApplied = 0;
            const zsCounts = { zero: 0, sold: 0 };
            const l30 = { gross: 0, net: 0, sales: 0, cogs: 0, units: 0 };
            const pgB = { gross: 0, net: 0, sales: 0, cogs: 0, units: 0 };
            const invB = { gross: 0, net: 0, sales: 0, cogs: 0, units: 0 };
            const ads = (typeof chPromoAdsFrac === 'function') ? ((Number(chPromoAdsFrac()) || 0) * 100) : 0;
            chStdRows().forEach(function(d) {
                const std = (typeof chPromoStdBase === 'function') ? chPromoStdBase(d) : 0;
                const dilVal = chStdDilPct(d);
                const invOk = chStdStockForAge(d) > 0;
                let dilIdx = -1;
                for (let i = 0; i < draft.dil.length; i++) if (chStdInRange(dilVal, draft.dil[i], i === draft.dil.length - 1)) { dilIdx = i; break; }
                if (dilIdx >= 0) dilCounts['d' + dilIdx] += 1; else dilCounts.outside += 1;
                const ageVal = chStdAgeDays(d);
                let ageIdx = -1;
                if (ageVal != null) {
                    for (let i = 0; i < draft.age.length; i++) if (chStdInRange(ageVal, draft.age[i], i === draft.age.length - 1)) { ageIdx = i; break; }
                }
                if (ageIdx >= 0) ageCounts['a' + ageIdx] += 1; else ageCounts.none += 1;
                const reviews = chStdReviews(d);
                let revIdx = -1;
                if (reviews > 0 && reviews < draft.reviewMax) {
                    for (let i = 0; i < draft.reviews.length; i++) {
                        if (reviews >= draft.reviews[i].min && reviews <= draft.reviews[i].max) { revIdx = i; break; }
                    }
                }
                if (revIdx >= 0) revCounts['r' + revIdx] += 1; else revCounts.none += 1;
                const stdHit = Number(std) || 0;
                let bussIdx = -1;
                for (let i = 0; i < draft.buss.length; i++) if (chStdInRange(stdHit, draft.buss[i], i === draft.buss.length - 1)) { bussIdx = i; break; }
                if (bussIdx >= 0) bussCounts['u' + bussIdx] += 1; else bussCounts.outside += 1;
                const hit = chStdCvrMatch(d, draft.cvr);
                cvrCounts[hit ? hit.key : 'flat'] += 1;
                const ageDisc = invOk ? chStdScaleDisc(std, ageIdx >= 0 ? (Number(draft.age[ageIdx].disc) || 0) : 0) : 0;
                const dilDisc = invOk ? chStdScaleDisc(std, dilIdx >= 0 ? (Number(draft.dil[dilIdx].disc) || 0) : 0) : 0;
                const revDisc = chStdScaleDisc(std, revIdx >= 0 ? (Number(draft.reviews[revIdx].disc) || 0) : 0);
                const bussDisc = bussIdx >= 0 ? (Number(draft.buss[bussIdx].disc) || 0) : 0;
                const cvrDisc = invOk ? chStdScaleDisc(std, Math.max(0, hit ? (Number(hit.disc) || 0) : (Number(draft.cvr.flat_disc) || 0))) : 0;
                const zeroSold = invOk && chStdRowIsZeroSold(d);
                if (zeroSold) zsCounts.zero += 1; else zsCounts.sold += 1;
                const zsDisc = zeroSold ? chStdScaleDisc(std, Number(draft.zeroSoldDisc) || 0) : 0;
                if (ageDisc > 0) { skuHits.age++; dollars.age += std * ageDisc / 100; pctTotals.age += ageDisc; }
                if (dilDisc > 0) { skuHits.dil++; dollars.dil += std * dilDisc / 100; pctTotals.dil += dilDisc; }
                if (cvrDisc > 0) { skuHits.cvr++; dollars.cvr += std * cvrDisc / 100; pctTotals.cvr += cvrDisc; }
                if (revDisc > 0) { skuHits.rev++; dollars.rev += std * revDisc / 100; pctTotals.rev += revDisc; }
                if (bussDisc > 0) { skuHits.buss++; dollars.buss += std * bussDisc / 100; pctTotals.buss += bussDisc; }
                if (zsDisc > 0) { skuHits.zs++; dollars.zs += std * zsDisc / 100; pctTotals.zs += zsDisc; }
                const sum = Math.min(99.99, ageDisc + dilDisc + cvrDisc + revDisc + bussDisc + zsDisc);
                if (sum > 0) skuHits.all++;
                if (chStdUsesMinNpft() && chStdIsClearance(d)) clearanceIgnored++;
                if (chStdMinNpftWasApplied(d, draft)) npftApplied++;
                if (std > 0) {
                    let sprice = Math.round(std * (1 - sum / 100) * 100) / 100;
                    sprice = chStdRaiseToMinNpft(sprice, d, draft.minNpft);
                    const lp = (typeof chPromoLp === 'function') ? chPromoLp(d) : 0;
                    const ship = chStdExcludesShip() ? 0 : ((typeof chPromoShipCost === 'function') ? chPromoShipCost(d) : 0);
                    const gross = (sprice * chStdMargin(d)) - ship - lp;
                    const net = gross - (sprice * ads / 100);
                    const units = chStdOvL30(d);
                    const pgUnits = chStdSiteL30(d);
                    const inv = (typeof chPromoInv === 'function') ? chPromoInv(d) : 0;
                    l30.gross += gross * units; l30.net += net * units; l30.sales += sprice * units; l30.cogs += lp * units; l30.units += units;
                    pgB.gross += gross * pgUnits; pgB.net += net * pgUnits; pgB.sales += sprice * pgUnits; pgB.cogs += lp * pgUnits; pgB.units += pgUnits;
                    invB.gross += gross * inv; invB.net += net * inv; invB.sales += sprice * inv; invB.cogs += lp * inv; invB.units += inv;
                }
            });
            // Sales this page already made on SKUs whose stock is now 0 still belong in "Projected with <page> L30".
            // chStdRows() skips them, so add them here. They get no Age / Dil / CVR / 0 Sold disc (same as a stock-less
            // row in the loop above), only Reviews + B Disc, and they stay out of the counts and charts.
            (function() {
                const addSoldOut = function(d) {
                    if (!d || d.is_parent_summary) return;
                    if (typeof chPromoIsChildRow === 'function' && !chPromoIsChildRow(d)) return;
                    if (chStdStockForAge(d) > 0) return;
                    const pgUnits = chStdSiteL30(d);
                    if (!(pgUnits > 0)) return;
                    const std = (typeof chPromoStdBase === 'function') ? chPromoStdBase(d) : 0;
                    if (!(std > 0)) return;
                    const reviews = chStdReviews(d);
                    let revDisc = 0;
                    if (reviews > 0 && reviews < draft.reviewMax) {
                        for (let i = 0; i < draft.reviews.length; i++) {
                            if (reviews >= draft.reviews[i].min && reviews <= draft.reviews[i].max) { revDisc = chStdScaleDisc(std, Number(draft.reviews[i].disc) || 0); break; }
                        }
                    }
                    let bussDisc = 0;
                    for (let i = 0; i < draft.buss.length; i++) {
                        if (chStdInRange(std, draft.buss[i], i === draft.buss.length - 1)) { bussDisc = Number(draft.buss[i].disc) || 0; break; }
                    }
                    const sum = Math.min(99.99, revDisc + bussDisc);
                    let sprice = Math.round(std * (1 - sum / 100) * 100) / 100;
                    sprice = chStdRaiseToMinNpft(sprice, d, draft.minNpft);
                    const lp = (typeof chPromoLp === 'function') ? chPromoLp(d) : 0;
                    const ship = chStdExcludesShip() ? 0 : ((typeof chPromoShipCost === 'function') ? chPromoShipCost(d) : 0);
                    const gross = (sprice * chStdMargin(d)) - ship - lp;
                    const net = gross - (sprice * ads / 100);
                    pgB.gross += gross * pgUnits; pgB.net += net * pgUnits; pgB.sales += sprice * pgUnits; pgB.cogs += lp * pgUnits; pgB.units += pgUnits;
                };
                const full = chStdFullDataset();
                if (full) full.forEach(addSoldOut);
                else if (typeof chPromoEachTableRow === 'function') chPromoEachTableRow(function(row, d) { addSoldOut(d); });
            })();
            const countKey = { dil: 'd', age: 'a', rev: 'r', buss: 'u' };
            const countMap = { dil: dilCounts, age: ageCounts, rev: revCounts, buss: bussCounts };
            ['dil', 'age', 'rev', 'buss'].forEach(function(prefix) {
                $('#ch-sp-' + prefix + '-tbody tr').each(function(i) { $(this).find('.ch-sp-' + prefix + '-count').text(countMap[prefix][countKey[prefix] + i] || 0); });
            });
            $('#ch-sp-cvr-down2-count').text(cvrCounts.down2);
            $('#ch-sp-cvr-down-count').text(cvrCounts.down);
            $('#ch-sp-cvr-flat-count').text(cvrCounts.flat);
            $('#ch-sp-cvr-up-count').text(cvrCounts.up);
            $('#ch-sp-cvr-up2-count').text(cvrCounts.up2);
            $('#ch-sp-zs-count').text(zsCounts.zero);
            const ignoredEl = document.getElementById('ch-sp-min-npft-ignored');
            if (ignoredEl) ignoredEl.textContent = chStdClearanceSet ? String(clearanceIgnored) : '…';
            const appliedEl = document.getElementById('ch-sp-min-npft-applied');
            if (appliedEl) appliedEl.textContent = String(npftApplied);
            chStdStampNpftMarks();
            const dilSlices = draft.dil.map(function(r, i) { return { key: 'd' + i, label: r.min + '–' + r.max, color: CH_STD_COLORS[i % CH_STD_COLORS.length] }; }).concat([{ key: 'outside', label: 'Outside', color: '#cbd5e1' }]);
            const ageSlices = draft.age.map(function(r, i) { return { key: 'a' + i, label: r.min + '–' + r.max, color: CH_STD_COLORS[i % CH_STD_COLORS.length] }; }).concat([{ key: 'none', label: 'No age', color: '#cbd5e1' }]);
            const revSlices = draft.reviews.map(function(r, i) { return { key: 'r' + i, label: r.min + '–' + r.max, color: CH_STD_COLORS[i % CH_STD_COLORS.length] }; }).concat([{ key: 'none', label: 'No disc', color: '#cbd5e1' }]);
            const bussSlices = draft.buss.map(function(r, i) { return { key: 'u' + i, label: r.min + '–' + r.max, color: CH_STD_COLORS[i % CH_STD_COLORS.length] }; }).concat([{ key: 'outside', label: 'Outside', color: '#cbd5e1' }]);
            const cvrSlices = [
                { key: 'down2', label: 'Down < ' + draft.cvr.down2_lt + '%', color: '#9f1239' },
                { key: 'down', label: 'Down < ' + draft.cvr.down_lt + '%', color: '#dc3545' },
                { key: 'flat', label: 'Flat', color: '#94a3b8' },
                { key: 'up', label: 'Up > ' + draft.cvr.up_gt + '%', color: '#198754' },
                { key: 'up2', label: 'Up > ' + draft.cvr.up2_gt + '%', color: '#14532d' },
            ];
            const allSlices = [
                { key: 'age', label: 'Age', color: '#d97706' }, { key: 'dil', label: 'Dil', color: '#6f42c1' },
                { key: 'cvr', label: 'CVR', color: '#20c997' }, { key: 'rev', label: 'Reviews', color: '#7c3aed' },
                { key: 'buss', label: 'B Disc', color: '#0d6efd' },
                { key: 'zs', label: '0 Sold', color: '#f59e0b' },
            ];
            // SKU count per discount type (bars + legend count). $ off is shown next to it, not a percentage.
            const allCounts = { age: skuHits.age, dil: skuHits.dil, cvr: skuHits.cvr, rev: skuHits.rev, buss: skuHits.buss, zs: skuHits.zs };
            const zsSlices = [
                { key: 'zero', label: '0 sold', color: '#f59e0b' },
                { key: 'sold', label: 'Sold', color: '#94a3b8' },
            ];
            chStdHistLive = {};
            chStdLegend('#ch-sp-leg-dil', dilSlices, dilCounts, 'dil');
            chStdLegend('#ch-sp-leg-rev', revSlices, revCounts, 'rev');
            chStdLegend('#ch-sp-leg-buss', bussSlices, bussCounts, 'buss');
            chStdLegend('#ch-sp-leg-zs', zsSlices, zsCounts, 'zs');
            chStdLegend('#ch-sp-leg-cvr', cvrSlices, cvrCounts, 'cvr');
            chStdLegend('#ch-sp-leg-age', ageSlices, ageCounts, 'age');
            chStdLegend('#ch-sp-leg-all', allSlices, allCounts, 'all', dollars);
            chStdDrawBars([
                { id: 'ch-sp-pie-dil', slices: dilSlices, counts: dilCounts },
                { id: 'ch-sp-pie-rev', slices: revSlices, counts: revCounts },
                { id: 'ch-sp-pie-buss', slices: bussSlices, counts: bussCounts },
                { id: 'ch-sp-pie-zs', slices: zsSlices, counts: zsCounts },
                { id: 'ch-sp-pie-cvr', slices: cvrSlices, counts: cvrCounts },
                { id: 'ch-sp-pie-age', slices: ageSlices, counts: ageCounts },
                { id: 'ch-sp-pie-all', slices: allSlices, counts: allCounts },
            ]);
            const body = [['Age', skuHits.age], ['Dil', skuHits.dil], ['B Disc', skuHits.buss], ['0 Sold', skuHits.zs], ['CVR', skuHits.cvr], ['Reviews', skuHits.rev]].map(function(row) {
                return '<tr><td>' + row[0] + '</td><td class="text-center">' + row[1] + '</td></tr>';
            });
            body.push('<tr class="fw-semibold"><td>All</td><td class="text-center">' + skuHits.all + '</td></tr>');
            $('#ch-sp-all-tbody').html(body.join(''));
            function marginBadge(label, value, cls) {
                return '<span class="badge border ' + cls + '"><span class="ch-sp-badge-lbl">' + chStdEsc(label) + '</span><span class="ch-sp-badge-val">' + chStdEsc(value) + '</span></span>';
            }
            function marginBadgesHtml(bucket) {
                const neutral = 'bg-secondary-subtle text-secondary-emphasis';
                const pct = function(n) { return Math.round(n) + '%'; };
                return marginBadge('Units', Math.round(bucket.units).toLocaleString('en-US'), neutral)
                    + marginBadge('Profit', chStdMoney(bucket.gross), bucket.gross < 0 ? 'bg-danger-subtle text-danger-emphasis' : 'bg-success-subtle text-success-emphasis')
                    + marginBadge('GROI', pct(bucket.cogs > 0 ? (bucket.gross / bucket.cogs) * 100 : 0), neutral)
                    + marginBadge('GPFT', pct(bucket.sales > 0 ? (bucket.gross / bucket.sales) * 100 : 0), neutral)
                    + marginBadge('NROI', pct(bucket.cogs > 0 ? (bucket.net / bucket.cogs) * 100 : 0), neutral)
                    + marginBadge('NPFT', pct(bucket.sales > 0 ? (bucket.net / bucket.sales) * 100 : 0), neutral);
            }
            function marginSalesHtml(bucket) {
                return '<span class="ch-sp-sales-lbl">Sales</span>' + chStdEsc(chStdMoney(bucket.sales));
            }
            $('#ch-sp-margin-l30').html(marginSalesHtml(l30)).css('color', '#166534');
            $('#ch-sp-margin-l30-sub').html(marginBadgesHtml(l30));
            $('#ch-sp-margin-pg').html(marginSalesHtml(pgB)).css('color', '#166534');
            $('#ch-sp-margin-pg-sub').html(marginBadgesHtml(pgB));
            if ($('#ch-sp-margin-cur').length) {
                chStdLoadCurrentServer();
                const curB = chStdAddUnmatchedSales(chStdCurrentSiteBucket(ads), ads);
                $('#ch-sp-margin-cur').html(marginSalesHtml(curB)).css('color', '#166534');
                $('#ch-sp-margin-cur-sub').html(marginBadgesHtml(curB));
            }
            $('#ch-sp-margin-inv').html(marginSalesHtml(invB)).css('color', '#166534');
            $('#ch-sp-margin-inv-sub').html(marginBadgesHtml(invB));
            chStdRepaintDiscColumns();
        }
        function chStdPaint() {
            $('#ch-sp-dil-tbody').html(chStdDil.map(function(r) { return chStdRangeRow('ch-sp-dil', r); }).join(''));
            $('#ch-sp-age-tbody').html(chStdAge.map(function(r) { return chStdRangeRow('ch-sp-age', r); }).join(''));
            $('#ch-sp-rev-tbody').html(chStdRev.map(function(r) { return chStdRangeRow('ch-sp-rev', r); }).join(''));
            $('#ch-sp-buss-tbody').html(chStdBuss.map(function(r) { return chStdRangeRow('ch-sp-buss', r); }).join(''));
            $('#ch-sp-zs-disc').val(chStdZeroSoldDisc);
            if ($('#ch-sp-min-npft').length) $('#ch-sp-min-npft').val(chStdMinNpft);
            $('#ch-sp-review-max').val(chStdReviewMax);
            const cfg = chStdNormCvr(chStdCvr);
            $('#chStdPrcModal .ch-sp-cvr-down2-lt').val(cfg.down2_lt);
            $('#chStdPrcModal .ch-sp-cvr-down2-disc').val(cfg.down2_disc);
            $('#chStdPrcModal .ch-sp-cvr-down-lt').val(cfg.down_lt);
            $('#chStdPrcModal .ch-sp-cvr-down-disc').val(cfg.down_disc);
            $('#chStdPrcModal .ch-sp-cvr-up-gt').val(cfg.up_gt);
            $('#chStdPrcModal .ch-sp-cvr-up-disc').val(cfg.up_disc);
            $('#chStdPrcModal .ch-sp-cvr-up2-gt').val(cfg.up2_gt);
            $('#chStdPrcModal .ch-sp-cvr-up2-disc').val(cfg.up2_disc);
            $('#chStdPrcModal .ch-sp-cvr-flat-disc').val(cfg.flat_disc);
            chStdRefresh();
        }
        function chStdLoad() {
            return $.ajax({ url: CH_PROMO_RULES_BASE + '/std-prc-vs-dil', method: 'GET', headers: { 'Accept': 'application/json' } }).done(function(res) {
                if (!res || !res.success) return;
                chStdDil = (res.dil || CH_STD_DIL_DEFAULTS).map(chStdNormRange).filter(Boolean);
                chStdAge = (res.age || CH_STD_AGE_DEFAULTS).map(chStdNormRange).filter(Boolean);
                chStdRev = (res.reviews || CH_STD_REV_DEFAULTS).map(chStdNormRange).filter(Boolean);
                chStdBuss = (res.buss || CH_STD_BUSS_DEFAULTS).map(chStdNormRange).filter(Boolean);
                chStdZeroSoldDisc = isFinite(Number(res.zero_sold_disc)) ? Math.min(100, Math.max(0, Number(res.zero_sold_disc))) : 0;
                if (isFinite(Number(res.min_npft))) chStdMinNpft = Math.min(99, Math.max(0, Number(res.min_npft)));
                if ($('#ch-sp-min-npft').length) $('#ch-sp-min-npft').val(chStdMinNpft);
                chStdStampNpftMarks();
                chStdCvr = chStdNormCvr(res.cvr);
                chStdReviewMax = parseInt(res.review_max, 10) || 4;
                if (!chStdDil.length) chStdDil = CH_STD_DIL_DEFAULTS.map(function(r) { return Object.assign({}, r); });
                if (!chStdAge.length) chStdAge = CH_STD_AGE_DEFAULTS.map(function(r) { return Object.assign({}, r); });
                if (!chStdRev.length) chStdRev = CH_STD_REV_DEFAULTS.map(function(r) { return Object.assign({}, r); });
                if (!chStdBuss.length) chStdBuss = CH_STD_BUSS_DEFAULTS.map(function(r) { return Object.assign({}, r); });
            });
        }
        function chStdTodayKey() {
            try { return new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Los_Angeles' }).format(new Date()); }
            catch (e) { return new Date().toISOString().slice(0, 10); }
        }
        function chStdSaveHistory() {
            const counts = Object.assign({}, chStdHistLive);
            if (!Object.keys(counts).some(function(key) { return Number(counts[key]) > 0; })) return;
            $.ajax({
                url: CH_PROMO_RULES_BASE + '/std-prc-vs-dil-history',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': (typeof chPromoCsrf === 'function' ? chPromoCsrf() : ''), 'Accept': 'application/json' },
                data: { _token: (typeof chPromoCsrf === 'function' ? chPromoCsrf() : ''), counts: counts },
            });
        }
        function chStdPadHistory(rows) {
            const byDate = {};
            (rows || []).forEach(function(r) { if (r && r.date) byDate[r.date] = r; });
            const today = chStdTodayKey();
            const parts = today.split('-').map(Number);
            const end = new Date(Date.UTC(parts[0], (parts[1] || 1) - 1, parts[2] || 1));
            const out = [];
            for (let i = 29; i >= 0; i--) {
                const d = new Date(end);
                d.setUTCDate(d.getUTCDate() - i);
                const key = d.toISOString().slice(0, 10);
                const rec = byDate[key] ? Object.assign({}, byDate[key]) : {};
                rec.date = key; rec.label = key.slice(5);
                out.push(rec);
            }
            const last = out[out.length - 1];
            if (last) Object.assign(last, chStdHistLive, { date: last.date, label: last.label });
            return out;
        }
        function chStdOpenHist(chart, band, label, color) {
            const draw = function(rows) {
                const plot = chStdPadHistory(rows);
                const field = chart + ':' + band;
                $('#ch-sp-hist-title').text(label + (chart === 'all' ? ' · $ off' : ' count') + ' · last 30 days');
                $('#ch-sp-hist-wrap').addClass('is-open');
                const go = function() {
                    const canvas = document.getElementById('ch-sp-hist');
                    if (!canvas || typeof Chart === 'undefined') return;
                    if (chStdHistChart) { chStdHistChart.destroy(); chStdHistChart = null; }
                    chStdHistChart = new Chart(canvas.getContext('2d'), {
                        type: 'line',
                        data: { labels: plot.map(function(r) { return r.label; }), datasets: [{ data: plot.map(function(r) { return Number(r[field]) || 0; }), borderColor: color, backgroundColor: color + '22', fill: true, tension: 0.3, pointRadius: 3 }] },
                        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true }, x: { ticks: { maxRotation: 90, minRotation: 90, font: { size: 8 } } } } },
                    });
                };
                if (typeof window.loadChartJs === 'function') window.loadChartJs().then(go).catch(go); else go();
            };
            $.ajax({ url: CH_PROMO_RULES_BASE + '/std-prc-vs-dil-history', method: 'GET', data: { days: 30 } })
                .done(function(res) { draw((res && res.success && Array.isArray(res.data)) ? res.data : []); })
                .fail(function() { draw([]); });
        }
        function chStdDraftNow() {
            return { dil: chStdDil, age: chStdAge, cvr: chStdCvr, reviews: chStdRev, reviewMax: chStdReviewMax, buss: chStdBuss, zeroSoldDisc: chStdZeroSoldDisc, minNpft: chStdMinNpft };
        }
        function chStdDiscBadge(pct) {
            const n = Number(pct) || 0;
            if (!(n > 0)) return '<span style="color:#adb5bd;font-weight:600;">—</span>';
            const shown = Math.round(n * 10) / 10;
            return '<span style="font-weight:700;color:#0f172a;">' + shown + '</span>';
        }
        let chStdFamilyMap = null;
        let chStdFamilyLen = -1;
        function chStdIsParentRow(d) {
            if (!d) return false;
            if (d.is_parent_summary || d.is_parent_row || d.is_parent) return true;
            if (typeof isEbay2TabulatorParentRow === 'function' && isEbay2TabulatorParentRow(d)) return true;
            const sku = String(d['(Child) sku'] || d.SKU || d.sku || '').toUpperCase();
            return sku.indexOf('PARENT') !== -1;
        }
        function chStdFamilyName(d) {
            if (!d) return '';
            const sku = String(d['(Child) sku'] || d.SKU || d.sku || '').trim();
            if (sku.toUpperCase().indexOf('PARENT') !== -1) {
                const fromSku = sku.replace(/^PARENT\s+/i, '').trim();
                if (fromSku) return fromSku.toUpperCase();
            }
            if (typeof chPromoParentName === 'function') {
                const n = String(chPromoParentName(d) || '').trim();
                if (n) return n.toUpperCase();
            }
            return String(d.Parent || d.parent || '').replace(/^PARENT\s+/i, '').trim().toUpperCase();
        }
        function chStdFamilyKids(parentRow) {
            const name = chStdFamilyName(parentRow);
            if (!name) return [];
            const src = (typeof allTableData !== 'undefined' && Array.isArray(allTableData) && allTableData.length)
                ? allTableData
                : [];
            if (!chStdFamilyMap || chStdFamilyLen !== src.length) {
                const map = {};
                src.forEach(function(d) {
                    if (!d || chStdIsParentRow(d)) return;
                    const key = chStdFamilyName(d);
                    if (!key) return;
                    if (!map[key]) map[key] = [];
                    map[key].push(d);
                });
                chStdFamilyMap = map;
                chStdFamilyLen = src.length;
            }
            return chStdFamilyMap[name] || [];
        }
        function chStdFamilyAvg(parentRow, read) {
            const kids = chStdFamilyKids(parentRow);
            let sum = 0, n = 0;
            kids.forEach(function(d) {
                const v = Number(read(d));
                if (!isFinite(v)) return;
                sum += v;
                n++;
            });
            return n ? (sum / n) : null;
        }
        function chStdFamilySum(parentRow, read) {
            const kids = chStdFamilyKids(parentRow);
            let sum = 0, n = 0;
            kids.forEach(function(d) {
                const v = Number(read(d));
                if (!isFinite(v)) return;
                sum += v;
                n++;
            });
            return n ? sum : null;
        }
        function chStdParentDiscHtml(d, read) {
            const avg = chStdFamilyAvg(d, read);
            if (avg === null) return '';
            const n = chStdFamilyKids(d).length;
            return '<span title="' + chStdEsc('Average of ' + n + ' SKUs') + '">' + chStdDiscBadge(avg) + '</span>';
        }
        function chStdAgeDisc(d, rules) {
            // Same as Amazon analytics: INV = 0 → 0%. Age days come from the Shopify push clock.
            if (!(chStdStockForAge(d) > 0)) return 0;
            const days = chStdAgeDays(d);
            if (days == null) return 0;
            return chStdScaleDisc(chStdRowStd(d), chStdRangeDisc(days, rules || chStdAge));
        }
        function chStdDilPct(d) {
            const m = chStdAmzMetric(d);
            if (m) return m.inv > 0 ? (m.ov / m.inv) * 100 : 0;
            return (typeof chPromoDil === 'function') ? chPromoDil(d) : 0;
        }
        function chStdDilDisc(d) {
            if (!(chStdStockForAge(d) > 0)) return 0;
            return chStdScaleDisc(chStdRowStd(d), chStdRangeDisc(chStdDilPct(d), chStdDil));
        }
        function chStdCvrDiscPct(d, draft) {
            if (chStdSkipsCvr()) return 0;
            if (!(chStdStockForAge(d) > 0)) return 0;
            const cfg = (draft && draft.cvr) ? draft.cvr : chStdCvr;
            const hit = chStdCvrMatch(d, cfg);
            const trend = hit ? (Number(hit.disc) || 0) : (Number(cfg.flat_disc) || 0);
            return Math.max(0, trend);
        }
        function chStdRevDiscPct(d) {
            const reviews = chStdReviews(d);
            if (!(reviews > 0) || reviews >= chStdReviewMax) return 0;
            for (let i = 0; i < chStdRev.length; i++) {
                const rule = chStdRev[i];
                if (reviews >= rule.min && reviews <= rule.max) return chStdScaleDisc(chStdRowStd(d), Number(rule.disc) || 0);
            }
            return 0;
        }
        function chStdBussRulesLive() {
            const modal = document.getElementById('chStdPrcModal');
            if (modal && modal.classList.contains('show')) {
                const live = chStdReadRanges('#ch-sp-buss-tbody', '.ch-sp-buss-min', '.ch-sp-buss-max', '.ch-sp-buss-disc');
                if (live.length) return live;
            }
            return chStdBuss;
        }
        function chStdBussDiscPct(d) {
            if (typeof chPromoIsChildRow === 'function' && !chPromoIsChildRow(d)) return null;
            if (!(chStdStockForAge(d) > 0)) return 0;
            return chStdRangeDisc(chStdRowStd(d), chStdBussRulesLive());
        }
        window.chStdBussDiscPct = chStdBussDiscPct;
        window.analyticsBussDiscountPct = chStdBussDiscPct;
        function chStdRepaintDiscColumns() {
            if (typeof table === 'undefined' || !table || typeof table.getColumn !== 'function') return;
            ['age_discount', 'dil_discount', 'buss_discount', 'zero_sold_discount', 'cvr_discount', 'review_discount', 'sum_discount'].forEach(function(field) {
                let col = null;
                try { col = table.getColumn(field); } catch (e) { col = null; }
                if (!col || typeof col.getCells !== 'function') return;
                col.getCells().forEach(function(cell) {
                    try { if (cell && typeof cell.reformat === 'function') cell.reformat(); } catch (err) { /* ignore */ }
                });
            });
        }
        function chStdAlibabaSoldQty(d) {
            if (!d) return null;
            const raw = d.al30 != null && d.al30 !== '' ? d.al30
                : (d['AB L30'] != null && d['AB L30'] !== '' ? d['AB L30'] : d.AL30);
            const n = Number(raw);
            return isFinite(n) ? n : null;
        }
        function chStdRowIsZeroSold(d) {
            if (!d || d.is_parent_summary) return false;
            if (typeof chPromoIsChildRow === 'function' && !chPromoIsChildRow(d)) return false;
            // Alibaba 0 Sold is AB L30 = 0. OV L30 (field L30) and Amazon A L30 are not Alibaba sales.
            // Use the same stock gate as Age / Dil so a row that already shows those discs also gets 0 Sold.
            if (typeof CHANNEL_PROMO_CHANNEL !== 'undefined' && CHANNEL_PROMO_CHANNEL === 'alibaba') {
                const pageInv = (typeof chPromoInv === 'function') ? (Number(chPromoInv(d)) || 0) : (Number(d.INV) || Number(d.inv) || 0);
                if (!(chStdStockForAge(d) > 0) && !(pageInv > 0)) return false;
                const sold = chStdAlibabaSoldQty(d);
                return sold != null && !(sold > 0);
            }
            if (!(chStdStockForAge(d) > 0)) return false;
            if (typeof chPromoIsZeroSoldRow === 'function') return !!chPromoIsZeroSoldRow(d);
            return !(chStdNum(d, ['sold', 'al30', 'AL30', 'AB L30', 'A_L30', 'E L30', 'RV L30', 'L30', 'l30']) > 0);
        }
        function chStdZeroSoldDiscNow() {
            const modal = document.getElementById('chStdPrcModal');
            if (modal && modal.classList.contains('show')) {
                const n = Number($('#ch-sp-zs-disc').val());
                if (isFinite(n)) return Math.min(100, Math.max(0, n));
            }
            return Number(chStdZeroSoldDisc) || 0;
        }
        function chStdZeroSoldDiscPct(d) {
            if (typeof chPromoIsChildRow === 'function' && !chPromoIsChildRow(d)) return null;
            if (!chStdRowIsZeroSold(d)) return 0;
            return chStdScaleDisc(chStdRowStd(d), chStdZeroSoldDiscNow());
        }
        window.chStdZeroSoldDiscPct = chStdZeroSoldDiscPct;
        function chStdDiscCol(title, field, tip, read) {
            return {
                title: title,
                field: field,
                width: 72,
                hozAlign: 'center',
                vertAlign: 'middle',
                headerSort: true,
                headerTooltip: tip,
                sorter: function(a, b, aRow, bRow) {
                    return (Number(read(aRow.getData() || {})) || 0) - (Number(read(bRow.getData() || {})) || 0);
                },
                formatter: function(cell) {
                    const d = cell.getRow().getData() || {};
                    if (chStdIsParentRow(d)) return chStdParentDiscHtml(d, read);
                    return chStdDiscBadge(read(d));
                },
            };
        }
        function chStdDiscColumns() {
            return [
                Object.assign(chStdDiscCol('Age Disc', 'age_discount', 'Age Disc — promotional % from Age Days slabs in Std prc vs dil. Same Shopify age clock as Amazon. INV = 0 → 0%. Read-only.', chStdAgeDisc), {
                    formatter: function(cell) {
                        const d = cell.getRow().getData() || {};
                        if (chStdIsParentRow(d)) return chStdParentDiscHtml(d, chStdAgeDisc);
                        const pct = chStdAgeDisc(d);
                        const days = chStdAgeDays(d);
                        const tip = (days == null ? 'No age' : (days + ' age days'))
                            + ' → discount ' + (pct || 0) + '%';
                        return '<span title="' + chStdEsc(tip) + '">' + chStdDiscBadge(pct) + '</span>';
                    },
                }),
                Object.assign(chStdDiscCol('Dil Disc', 'dil_discount', 'Dil Disc — same as Amazon: Shopify OV L30 ÷ Shopify INV. INV = 0 → 0%.', chStdDilDisc), {
                    formatter: function(cell) {
                        const d = cell.getRow().getData() || {};
                        if (chStdIsParentRow(d)) return chStdParentDiscHtml(d, chStdDilDisc);
                        const pct = chStdDilDisc(d);
                        const dil = chStdDilPct(d);
                        const tip = 'Dil ' + (isFinite(dil) ? dil.toFixed(1) : '0') + '% → discount ' + (pct || 0) + '%';
                        return '<span title="' + chStdEsc(tip) + '">' + chStdDiscBadge(pct) + '</span>';
                    },
                }),
                Object.assign(chStdDiscCol('B Disc', 'buss_discount', 'B Disc from Std Prc ranges in Std prc vs dil. Full Disc % at every Std Prc, including under $15.', function(d) { return chStdBussDiscPct(d) || 0; }), {
                    visible: true,
                    minWidth: 64,
                    formatter: function(cell) {
                        const d = cell.getRow().getData() || {};
                        if (chStdIsParentRow(d)) return chStdParentDiscHtml(d, function(row) { return chStdBussDiscPct(row) || 0; });
                        const pct = chStdBussDiscPct(d) || 0;
                        const std = chStdRowStd(d);
                        const tip = (std > 0 ? ('Std Prc $' + std.toFixed(2) + ' → ') : '') + 'B Disc ' + pct + '%';
                        return '<span title="' + chStdEsc(tip) + '">' + chStdDiscBadge(pct) + '</span>';
                    },
                }),
                Object.assign(chStdDiscCol('0 Sold', 'zero_sold_discount', '0 Sold discount from Std prc vs dil. Applies only when this page sold qty is 0. INV = 0 → 0%. Std Prc under $15 is 0.5×.', function(d) { return chStdZeroSoldDiscPct(d) || 0; }), { visible: true, minWidth: 64 }),
                Object.assign(chStdDiscCol('CVR Disc.', 'cvr_discount', chStdSkipsCvr() ? 'Best Buy has no session CVR. CVR Disc is off (Amazon A L30 / Sess30 is not used).' : 'CVR Disc — same as Amazon: A L30 ÷ Sess30, CVR 0 is Down. INV = 0 → 0%. Std Prc under $15 is 0.5×.', function(d) { return chStdScaleDisc(chStdRowStd(d), chStdCvrDiscPct(d)); }), {
                    visible: !chStdSkipsCvr(),
                    formatter: function(cell) {
                        const d = cell.getRow().getData() || {};
                        if (chStdIsParentRow(d)) {
                            return chStdParentDiscHtml(d, function(row) {
                                return chStdScaleDisc(chStdRowStd(row), chStdCvrDiscPct(row));
                            });
                        }
                        const pct = chStdScaleDisc(chStdRowStd(d), chStdCvrDiscPct(d));
                        const cvr = chStdCvrLive(d);
                        const tip = 'CVR ' + (isFinite(cvr) ? cvr.toFixed(1) : '0') + '% ' + chStdCvrTrend(d) + ' → discount ' + (pct || 0) + '%';
                        return '<span title="' + chStdEsc(tip) + '">' + chStdDiscBadge(pct) + '</span>';
                    },
                }),
                chStdDiscCol('Rev Disc.', 'review_discount', 'Review discount from Std prc vs dil. Max reviews or above → 0.', chStdRevDiscPct),
                chStdDiscCol('Sum disc', 'sum_discount', 'Age + Dil + B Disc + 0 Sold + CVR + Rev. S PRC = Std Prc × (1 − Sum disc / 100). S PRC is raised when NPFT% would fall under Min NPFT %. Clearance SKUs are not raised.', function(d) { return chStdSumDisc(d); }),
            ];
        }
        function chStdAppendDiscColumns(cols) {
            const out = Array.isArray(cols) ? cols.slice() : [];
            const have = {};
            out.forEach(function(c) { if (c && c.field) have[c.field] = 1; });
            chStdDiscColumns().forEach(function(c) {
                if (!have[c.field]) out.push(c);
            });
            return out;
        }
        function chStdPriceForRow(d, draft) {
            if (!d) return 0;
            if (typeof chPromoIsChildRow === 'function' && !chPromoIsChildRow(d)) return 0;
            if (!(chStdStockForAge(d) > 0) && !chStdRowIsZeroSold(d)) return 0;
            const std = (typeof chPromoStdBase === 'function') ? chPromoStdBase(d) : 0;
            if (!(std > 0)) return 0;
            const sum = chStdSumDisc(d, draft || chStdDraftNow());
            let raw = std * (1 - Math.min(99.99, sum) / 100);
            if (typeof CHANNEL_PROMO_CHANNEL !== 'undefined' && CHANNEL_PROMO_CHANNEL === 'shopify_b2b') {
                const ship = (typeof chPromoShipCost === 'function') ? (Number(chPromoShipCost(d)) || 0) : 0;
                raw -= ship;
            }
            const minNpft = (draft && draft.minNpft != null) ? draft.minNpft : chStdMinNpft;
            raw = chStdRaiseToMinNpft(raw, d, minNpft);
            if (!(raw > 0)) return 0;
            if (chStdIsEbay()) raw = chStdEbayCap(d, raw);
            if (!(raw > 0)) return 0;
            return (typeof chPromoRoundChannelSprice === 'function')
                ? chPromoRoundChannelSprice(raw)
                : Math.round(raw * 100) / 100;
        }
        /**
         * Amazon order after Min NPFT: LMP cap (S PRC ≥ LMP and SGROI at LMP ≥ 20% → LMP, eBay take-home %),
         * then Std Prc is the maximum. Uses the page's own eBay LMP (L1 without ignored competitors).
         */
        function chStdEbayCap(d, price) {
            let cap = null;
            if (CHANNEL_PROMO_CHANNEL === 'ebay1' && typeof ebayCapSpriceToLmp === 'function') cap = ebayCapSpriceToLmp;
            else if (CHANNEL_PROMO_CHANNEL === 'ebay2' && typeof ebay2CapSpriceToLmp === 'function') cap = ebay2CapSpriceToLmp;
            else if (CHANNEL_PROMO_CHANNEL === 'ebay3' && typeof ebay3CapSpriceToLmp === 'function') cap = ebay3CapSpriceToLmp;
            let out = cap ? Number(cap(d, price)) : price;
            if (!isFinite(out) || !(out > 0)) out = price;
            const std = (typeof chPromoStdBase === 'function') ? chPromoStdBase(d) : 0;
            if (std > 0 && out > std + 0.0001) out = std;
            return Math.round(out * 100) / 100;
        }
        window.chStdPriceForRow = chStdPriceForRow;
        if (typeof channelPromoPricingColumns === 'function' && !channelPromoPricingColumns._chStd) {
            const chStdPrevPricingCols = channelPromoPricingColumns;
            channelPromoPricingColumns = function() { return chStdAppendDiscColumns(chStdPrevPricingCols()); };
            channelPromoPricingColumns._chStd = true;
            window.channelPromoPricingColumns = channelPromoPricingColumns;
        }
        if (typeof channelPromoAnalyticsColumns === 'function' && !channelPromoAnalyticsColumns._chStd) {
            const chStdPrevAnalyticsCols = channelPromoAnalyticsColumns;
            channelPromoAnalyticsColumns = function() { return chStdAppendDiscColumns(chStdPrevAnalyticsCols()); };
            channelPromoAnalyticsColumns._chStd = true;
            window.channelPromoAnalyticsColumns = channelPromoAnalyticsColumns;
        }
        let chStdBussPlaced = false;
        function chStdPlaceBussColumn() {
            if (chStdBussPlaced) return;
            if (typeof table === 'undefined' || !table || typeof table.getColumn !== 'function' || typeof table.addColumn !== 'function') return;
            let dil = null;
            try { dil = table.getColumn('dil_discount'); } catch (e) { dil = null; }
            if (!dil) return;
            let buss = null;
            try { buss = table.getColumn('buss_discount'); } catch (e) { buss = null; }
            if (!buss) {
                const spec = chStdDiscColumns().filter(function(c) { return c && c.field === 'buss_discount'; })[0];
                if (!spec) return;
                try { table.addColumn(spec, false, 'dil_discount'); } catch (err) { return; }
            } else if (typeof table.moveColumn === 'function') {
                try { table.moveColumn('buss_discount', 'dil_discount', true); } catch (err) { /* already beside Dil Disc */ }
            }
            try {
                const col = table.getColumn('buss_discount');
                if (col && typeof col.show === 'function' && !chStdPanelMinSet().has('buss')) col.show();
            } catch (err) { /* ignore */ }
            chStdBussPlaced = true;
            setTimeout(function() {
                try {
                    const col = table.getColumn('buss_discount');
                    if (col && typeof col.show === 'function' && !chStdPanelMinSet().has('buss')) col.show();
                } catch (err) { /* ignore */ }
            }, 1600);
        }
        window.chStdPlaceBussColumn = chStdPlaceBussColumn;
        let chStdZeroSoldPlaced = false;
        function chStdPlaceZeroSoldColumn() {
            if (chStdZeroSoldPlaced) return;
            if (typeof table === 'undefined' || !table || typeof table.getColumn !== 'function' || typeof table.addColumn !== 'function') return;
            let anchor = null;
            try { anchor = table.getColumn('buss_discount') || table.getColumn('dil_discount'); } catch (e) { anchor = null; }
            if (!anchor) return;
            const afterField = anchor.getField ? anchor.getField() : 'buss_discount';
            let zs = null;
            try { zs = table.getColumn('zero_sold_discount'); } catch (e) { zs = null; }
            if (!zs) {
                const spec = chStdDiscColumns().filter(function(c) { return c && c.field === 'zero_sold_discount'; })[0];
                if (!spec) return;
                try { table.addColumn(spec, false, afterField); } catch (err) { return; }
            } else if (typeof table.moveColumn === 'function') {
                try { table.moveColumn('zero_sold_discount', afterField, true); } catch (err) { /* already in place */ }
            }
            try {
                const col = table.getColumn('zero_sold_discount');
                if (col && typeof col.show === 'function' && !chStdPanelMinSet().has('zs')) col.show();
            } catch (err) { /* ignore */ }
            chStdZeroSoldPlaced = true;
            setTimeout(function() {
                try {
                    const col = table.getColumn('zero_sold_discount');
                    if (col && typeof col.show === 'function' && !chStdPanelMinSet().has('zs')) col.show();
                } catch (err) { /* ignore */ }
            }, 1600);
        }
        const CH_STD_PANELS = [
            { key: 'dil', field: 'dil_discount' },
            { key: 'rev', field: 'review_discount' },
            { key: 'buss', field: 'buss_discount' },
            { key: 'zs', field: 'zero_sold_discount' },
            { key: 'cvr', field: 'cvr_discount' },
            { key: 'age', field: 'age_discount' },
            { key: 'all', field: 'sum_discount' },
        ];
        function chStdPanelStoreKey() {
            const ch = (typeof CHANNEL_PROMO_CHANNEL !== 'undefined' && CHANNEL_PROMO_CHANNEL) ? CHANNEL_PROMO_CHANNEL : 'channel';
            return 'chStdPanelMin:' + ch;
        }
        function chStdPanelMinSet() {
            try {
                const raw = JSON.parse(localStorage.getItem(chStdPanelStoreKey()) || '[]');
                return new Set(Array.isArray(raw) ? raw : []);
            } catch (e) { return new Set(); }
        }
        function chStdSavePanelMin(set) {
            try { localStorage.setItem(chStdPanelStoreKey(), JSON.stringify(Array.from(set))); } catch (e) { /* ignore */ }
        }
        function chStdPaintPanels() {
            const min = chStdPanelMinSet();
            document.querySelectorAll('#chStdPrcModal [data-ch-sp-panel]').forEach(function(el) {
                const on = min.has(el.getAttribute('data-ch-sp-panel'));
                el.classList.toggle('is-min', on);
                const btn = el.querySelector('.ch-sp-min-btn');
                if (!btn) return;
                const title = (el.querySelector('.ch-sp-pie-title') || {}).textContent || 'rule';
                btn.textContent = on ? '+' : '−';
                btn.title = (on ? 'Maximize ' : 'Minimize ') + title.trim();
                btn.setAttribute('aria-label', btn.title);
            });
        }
        function chStdApplyPanelColumns() {
            if (typeof table === 'undefined' || !table || typeof table.getColumn !== 'function') return;
            const min = chStdPanelMinSet();
            CH_STD_PANELS.forEach(function(p) {
                if (!min.has(p.key)) return;
                let col = null;
                try { col = table.getColumn(p.field); } catch (e) { col = null; }
                if (!col || typeof col.hide !== 'function') return;
                try { col.hide(); } catch (err) { /* ignore */ }
                const cb = document.querySelector('#column-dropdown-menu .col-vis-field-toggle[data-field="' + p.field + '"]');
                if (cb) cb.checked = false;
            });
        }
        function chStdSetPanelMin(key, minimized) {
            const min = chStdPanelMinSet();
            if (minimized) min.add(key); else min.delete(key);
            chStdSavePanelMin(min);
            chStdPaintPanels();
            const panel = CH_STD_PANELS.filter(function(p) { return p.key === key; })[0];
            if (panel && typeof table !== 'undefined' && table && typeof table.getColumn === 'function') {
                let col = null;
                try { col = table.getColumn(panel.field); } catch (e) { col = null; }
                if (col) {
                    try { if (minimized) col.hide(); else if (typeof col.show === 'function') col.show(); } catch (err) { /* ignore */ }
                }
                const cb = document.querySelector('#column-dropdown-menu .col-vis-field-toggle[data-field="' + panel.field + '"]');
                if (cb) cb.checked = !minimized;
            }
            if (!minimized) requestAnimationFrame(function() { chStdRefresh(); });
        }
        function chStdSyncPanelsFromMenu() {
            const min = chStdPanelMinSet();
            let changed = false;
            CH_STD_PANELS.forEach(function(p) {
                const cb = document.querySelector('#column-dropdown-menu .col-vis-field-toggle[data-field="' + p.field + '"]');
                if (!cb) return;
                const shouldMin = !cb.checked;
                if (shouldMin !== min.has(p.key)) {
                    if (shouldMin) min.add(p.key); else min.delete(p.key);
                    changed = true;
                }
            });
            if (!changed) return;
            chStdSavePanelMin(min);
            chStdPaintPanels();
        }
        if (!window._chStdPanelMenuSync) {
            window._chStdPanelMenuSync = true;
            document.addEventListener('change', function(e) {
                const t = e.target;
                if (!t || !t.closest || !t.closest('#column-dropdown-menu')) return;
                if (!t.classList.contains('col-vis-field-toggle') && !t.classList.contains('col-vis-group-toggle')) return;
                setTimeout(chStdSyncPanelsFromMenu, 0);
            }, true);
            document.addEventListener('click', function(e) {
                const t = e.target;
                if (!t || !t.closest) return;
                if (!t.closest('[data-analytics-col-vis="show-all"]') && !t.closest('[data-analytics-col-vis="show-default"]')) return;
                setTimeout(chStdSyncPanelsFromMenu, 0);
            }, true);
        }
        function chStdEnsureColumns() {
            if (typeof table === 'undefined' || !table || typeof table.getColumn !== 'function' || typeof table.addColumn !== 'function') return;
            let exists = false;
            try { exists = !!table.getColumn('sum_discount'); } catch (e) { exists = false; }
            if (!exists) {
                let anchor = null;
                try { anchor = table.getColumn('SPRICE') || table.getColumn('sprice'); } catch (e) { anchor = null; }
                chStdDiscColumns().forEach(function(col) {
                    try {
                        if (anchor) table.addColumn(col, true, anchor);
                        else table.addColumn(col);
                    } catch (err) { /* column already present */ }
                });
            }
            chStdPlaceBussColumn();
            chStdPlaceZeroSoldColumn();
            chStdDropRoiColumn();
            chStdBindNpftMarks();
            chStdPaintPanels();
            chStdApplyPanelColumns();
        }
        let chStdNpftMarksBound = false;
        function chStdStampNpftMarks() {
            if (window.minNpftSpriceMark && typeof window.minNpftSpriceMark.stamp === 'function') {
                window.minNpftSpriceMark.stamp();
                return;
            }
            if (typeof table === 'undefined' || !table || typeof table.getColumn !== 'function') return;
            ['SPRICE', 'sprice'].forEach(function(field) {
                let col = null;
                try { col = table.getColumn(field); } catch (e) { col = null; }
                if (!col || typeof col.getCells !== 'function') return;
                col.getCells().forEach(function(cell) {
                    const el = cell.getElement ? cell.getElement() : null;
                    if (!el) return;
                    const row = cell.getRow ? cell.getRow() : null;
                    const d = row && row.getData ? row.getData() : null;
                    // The "!" sits inside the S PRC cell, so only draw it when the row has an S PRC to explain.
                    const hasSprice = !!(d && ((parseFloat(d.SPRICE) || 0) > 0 || (parseFloat(d.sprice) || 0) > 0));
                    const on = !!(d && hasSprice && chStdMinNpftWasApplied(d));
                    let mark = el.querySelector('.ch-npft-mark');
                    if (!on) {
                        if (mark) mark.remove();
                        return;
                    }
                    if (!mark) {
                        mark = document.createElement('span');
                        mark.className = 'ch-npft-mark';
                        mark.textContent = '!';
                        el.appendChild(mark);
                    }
                    mark.title = 'SNPFT is at Min NPFT ' + Number(chStdMinNpft) + '%';
                });
            });
        }
        function chStdBindNpftMarks() {
            if (typeof table === 'undefined' || !table || typeof table.on !== 'function') return;
            if (!chStdNpftMarksBound) {
                chStdNpftMarksBound = true;
                table.on('renderComplete', chStdStampNpftMarks);
            }
            chStdStampNpftMarks();
        }
        window.chStdStampNpftMarks = chStdStampNpftMarks;
        function chStdDropRoiColumn() {
            if (typeof table === 'undefined' || !table || typeof table.getColumn !== 'function') return;
            let roi = null;
            try { roi = table.getColumn('roi_discount'); } catch (e) { roi = null; }
            if (!roi) return;
            try {
                if (typeof table.deleteColumn === 'function') table.deleteColumn('roi_discount');
                else if (typeof roi.hide === 'function') roi.hide();
            } catch (err) { /* ignore */ }
        }
        function chStdCatalog() {
            const bySku = {};
            const order = [];
            const take = function(d, row) {
                if (!d) return;
                if (typeof chPromoIsChildRow === 'function' && !chPromoIsChildRow(d)) return;
                const sku = (typeof chPromoSku === 'function') ? chPromoSku(d) : '';
                if (!sku) return;
                if (bySku[sku]) {
                    if (row && !bySku[sku].row) bySku[sku].row = row;
                    return;
                }
                const item = { d: d, row: row || null, sku: sku };
                bySku[sku] = item;
                order.push(item);
            };
            const extra = chStdFullDataset() || [];
            extra.forEach(function(d) { take(d, null); });
            if (typeof chPromoEachTableRow === 'function') chPromoEachTableRow(function(row, d) { take(d, row); });
            return order;
        }
        function chStdWritePrices(draft) {
            const updates = [];
            const ebay = chStdIsEbay();
            chStdCatalog().forEach(function(item) {
                const price = chStdPriceForRow(item.d, draft);
                if (!(price > 0)) return;
                if (ebay) {
                    const saved = Number(item.d && item.d.SPRICE_SERVER);
                    if (isFinite(saved) && Math.abs(saved - price) < 0.005) return;
                }
                const patch = (typeof chPromoSpricePatch === 'function')
                    ? chPromoSpricePatch(price)
                    : { SPRICE: price, sprice: price, has_custom_sprice: true };
                if (item.row && typeof chPromoPatchRowData === 'function') chPromoPatchRowData(item.row, patch);
                if (typeof chPromoPatchDatasetSprice === 'function') chPromoPatchDatasetSprice(item.sku, patch);
                updates.push({ sku: item.sku, sprice: price });
            });
            return updates;
        }
        function chStdMarkEbaySaved(chunk) {
            const bySku = {};
            chunk.forEach(function(u) { bySku[chStdSkuKey(u.sku)] = u.sprice; });
            if (typeof chPromoEachTableRow === 'function') {
                chPromoEachTableRow(function(row, d) {
                    const key = chStdSkuKey(chStdRowSku(d));
                    if (Object.prototype.hasOwnProperty.call(bySku, key) && d) d.SPRICE_SERVER = bySku[key];
                });
            }
            if (typeof chPromoPatchDatasetSprice === 'function') {
                chunk.forEach(function(u) { chPromoPatchDatasetSprice(u.sku, { SPRICE_SERVER: u.sprice }); });
            }
            // Blue badge counts on the saved S PRC; recount as each chunk lands, not only after the last one.
            clearTimeout(chStdMarkEbaySaved._t);
            chStdMarkEbaySaved._t = setTimeout(function() {
                if (typeof updateSummary === 'function') {
                    try { updateSummary(); } catch (e) { /* ignore */ }
                }
            }, 300);
        }
        /**
         * eBay: S PRC is saved through the channel's own save endpoint ({ updates }) and never pushed to eBay.
         * Only SKUs whose S PRC differs from the saved value are sent.
         */
        function chStdSaveEbayPrices(updates) {
            const url = (typeof chPromoCfg === 'object' && chPromoCfg) ? chPromoCfg.saveSpriceUrl : '';
            if (!url || !updates.length) return $.Deferred().resolve().promise();
            const csrf = (typeof chPromoCsrf === 'function') ? chPromoCsrf() : '';
            const size = 200;
            let chain = $.Deferred().resolve().promise();
            for (let i = 0; i < updates.length; i += size) {
                const chunk = updates.slice(i, i + size);
                chain = chain.then(function() {
                    const done = $.Deferred();
                    $.ajax({
                        url: url,
                        method: 'POST',
                        contentType: 'application/json',
                        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                        data: JSON.stringify({ _token: csrf, updates: chunk, skip_push: 1 }),
                    }).done(function() {
                        chStdMarkEbaySaved(chunk);
                    }).always(function() {
                        done.resolve();
                    });
                    return done.promise();
                });
            }
            return chain;
        }
        function chStdSavePrices(updates) {
            if (chStdIsEbay()) return chStdSaveEbayPrices(updates);
            if (typeof saveChannelSpriceBatch !== 'function' || !updates.length) {
                return $.Deferred().resolve().promise();
            }
            const size = 200;
            let chain = $.Deferred().resolve().promise();
            for (let i = 0; i < updates.length; i += size) {
                const chunk = updates.slice(i, i + size);
                chain = chain.then(function() { return saveChannelSpriceBatch(chunk, { skip_push: 1 }); });
            }
            return chain;
        }
        function chStdOwnsSprice() {
            // Server-owned channels (AliExpress): a cron saves + pushes S PRC. The page never writes it on open.
            if (window.CH_STD_SERVER_OWNED === true) return false;
            if (document.getElementById('sprice-rule-switch')) {
                return typeof window.spriceActiveRule === 'function' && window.spriceActiveRule() !== 'dil';
            }
            return true;
        }
        function chStdWhenRuleSettled(fn) {
            if (!document.getElementById('sprice-rule-switch')) {
                fn();
                return;
            }
            let tries = 0;
            (function wait() {
                if (window.spriceActiveRuleReady && typeof window.spriceActiveRuleReady.then === 'function') {
                    window.spriceActiveRuleReady.then(fn);
                    return;
                }
                if (tries++ > 40) {
                    fn();
                    return;
                }
                setTimeout(wait, 50);
            })();
        }
        let chStdAutoApplied = false;
        let chStdAutoWaits = 0;
        function chStdScheduleAutoApply() {
            if (chStdAutoApplied) return;
            const extraN = (typeof allTableData !== 'undefined' && Array.isArray(allTableData)) ? allTableData.length : 0;
            const tblN = (typeof table !== 'undefined' && table && typeof table.getDataCount === 'function') ? table.getDataCount() : 0;
            chStdEnsureColumns();
            if (!(extraN > 0) && !(tblN > 0)) {
                if (chStdAutoWaits++ < 120) setTimeout(chStdScheduleAutoApply, 500);
                return;
            }
            $.when(chStdLoad(), chStdLoadAgeMap(), chStdIsEbay() ? null : chStdLoadAmzMap(), chStdLoadClearance()).always(function() {
                chStdWhenRuleSettled(function() {
                    if (chStdAutoApplied) return;
                    chStdAutoApplied = true;
                    chStdEnsureColumns();
                    chStdRepaintDiscColumns();
                    // Rule, Min NPFT and clearance are all loaded now: pages repaint their S PRC "!" marks and header counts.
                    try { $(document).trigger('chStd:settled'); } catch (e) { /* ignore */ }
                    if (!chStdOwnsSprice()) return;
                    const updates = chStdWritePrices(chStdDraftNow());
                const redraw = function() {
                    if (typeof table !== 'undefined' && table && typeof table.redraw === 'function') {
                        try { table.redraw(true); } catch (e) { /* ignore */ }
                    }
                    if (typeof updateSummary === 'function') updateSummary();
                };
                chStdSavePrices(updates).always(function() {
                    redraw();
                    if (updates.length && typeof chPromoToast === 'function') {
                        chPromoToast('success', 'S PRC set from Std prc vs dil (' + updates.length + ')');
                    }
                });
                });
            });
        }
        function chStdApply() {
            const draft = chStdReadDraft();
            if (!draft.dil.length || !draft.age.length) {
                if (typeof chPromoToast === 'function') chPromoToast('error', 'Dil and Age need at least one range');
                return;
            }
            $('#ch-sp-status').text('Saving…');
            $.ajax({
                url: CH_PROMO_RULES_BASE + '/std-prc-vs-dil',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': (typeof chPromoCsrf === 'function' ? chPromoCsrf() : ''), 'Accept': 'application/json' },
                data: { _token: (typeof chPromoCsrf === 'function' ? chPromoCsrf() : ''), dil: draft.dil, age: draft.age, cvr: draft.cvr, reviews: draft.reviews, review_max: draft.reviewMax, buss: draft.buss, zero_sold_disc: draft.zeroSoldDisc, min_npft: draft.minNpft },
            }).done(function(res) {
                if (res && res.dil) chStdDil = res.dil.map(chStdNormRange).filter(Boolean);
                if (res && res.age) chStdAge = res.age.map(chStdNormRange).filter(Boolean);
                if (res && res.reviews) chStdRev = res.reviews.map(chStdNormRange).filter(Boolean);
                if (res && res.buss) chStdBuss = res.buss.map(chStdNormRange).filter(Boolean);
                if (res && isFinite(Number(res.zero_sold_disc))) chStdZeroSoldDisc = Math.min(100, Math.max(0, Number(res.zero_sold_disc)));
                if (res && isFinite(Number(res.min_npft))) chStdMinNpft = Math.min(99, Math.max(0, Number(res.min_npft)));
                if (res && res.cvr) chStdCvr = chStdNormCvr(res.cvr);
                if (res && res.review_max) chStdReviewMax = parseInt(res.review_max, 10) || 4;
                if (!chStdOwnsSprice()) {
                    $('#ch-sp-status').text(window.CH_STD_SERVER_OWNED === true
                        ? 'Saved. The server job applies S PRC from these rules at its next scheduled run (twice daily, IST).'
                        : 'Saved. S PRC follows the ON rule.');
                    chStdRefresh();
                    chStdSaveHistory();
                    return;
                }
                draft.minNpft = chStdMinNpft;
                chStdLoadClearance().always(function() {
                    const updates = chStdWritePrices(draft);
                    const done = function() {
                        chStdAutoApplied = true;
                        $('#ch-sp-status').text(chStdUsesMinNpft()
                            ? ('Saved. S PRC = Std Prc − Sum disc, raised to Min NPFT ' + chStdMinNpft + '% (clearance ignored), on ' + updates.length + ' SKU(s).')
                            : ('Saved. S PRC = Std Prc − Sum disc on ' + updates.length + ' SKU(s).'));
                        chStdRefresh();
                        chStdSaveHistory();
                        if (typeof chPromoToast === 'function') chPromoToast('success', 'S PRC applied from Std prc vs dil');
                    };
                    const pending = chStdSavePrices(updates);
                    if (pending && typeof pending.always === 'function') pending.always(done);
                    else done();
                });
            }).fail(function() {
                $('#ch-sp-status').text('Save failed');
                if (typeof chPromoToast === 'function') chPromoToast('error', 'Could not save Std prc vs dil');
            });
        }
        function bindChStdPrcUi() {
            chStdPaintPanels();
            $(document).off('click.chstdmin').on('click.chstdmin', '#chStdPrcModal .ch-sp-min-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const key = $(this).closest('[data-ch-sp-panel]').attr('data-ch-sp-panel');
                if (!key) return;
                chStdSetPanelMin(key, !$(this).closest('[data-ch-sp-panel]').hasClass('is-min'));
            });
            $(document).off('click.chstdmincol').on('click.chstdmincol', '#chStdPrcModal .ch-sp-col.is-min', function(e) {
                if ($(e.target).closest('.ch-sp-min-btn').length) return;
                const key = $(this).attr('data-ch-sp-panel');
                if (key) chStdSetPanelMin(key, false);
            });
            $('#ch-std-prc-btn').off('click.chstd').on('click.chstd', function(e) {
                e.preventDefault();
                $.when(chStdLoad(), chStdLoadClearance()).always(function() {
                    chStdPaint();
                    const el = document.getElementById('chStdPrcModal');
                    if (el && window.bootstrap && bootstrap.Modal) bootstrap.Modal.getOrCreateInstance(el).show();
                });
            });
            $('#chStdPrcModal').off('shown.bs.modal.chstd').on('shown.bs.modal.chstd', function() {
                chStdRefresh();
                chStdSaveHistory();
            });
            $('#chStdPrcModal').off('hidden.bs.modal.chstd').on('hidden.bs.modal.chstd', function() {
                chStdPieGen++;
                $('#ch-sp-hist-wrap').removeClass('is-open');
                if (chStdHistChart) { chStdHistChart.destroy(); chStdHistChart = null; }
                chStdRepaintDiscColumns();
            });
            $('#ch-sp-apply').off('click.chstd').on('click.chstd', function(e) { e.preventDefault(); chStdApply(); });
            $('#ch-sp-dil-add').off('click.chstd').on('click.chstd', function() {
                const rules = chStdReadRanges('#ch-sp-dil-tbody', '.ch-sp-dil-min', '.ch-sp-dil-max', '.ch-sp-dil-disc');
                const last = rules.length ? rules[rules.length - 1].max : 0;
                rules.push({ min: last, max: last + 10, disc: 0 });
                $('#ch-sp-dil-tbody').html(rules.map(function(r) { return chStdRangeRow('ch-sp-dil', r); }).join(''));
                chStdRefresh();
            });
            $('#ch-sp-age-add').off('click.chstd').on('click.chstd', function() {
                const rules = chStdReadRanges('#ch-sp-age-tbody', '.ch-sp-age-min', '.ch-sp-age-max', '.ch-sp-age-disc');
                const last = rules.length ? rules[rules.length - 1].max : 0;
                rules.push({ min: last + 1, max: last + 30, disc: 0 });
                $('#ch-sp-age-tbody').html(rules.map(function(r) { return chStdRangeRow('ch-sp-age', r); }).join(''));
                chStdRefresh();
            });
            $('#ch-sp-rev-add').off('click.chstd').on('click.chstd', function() {
                const rules = chStdReadRanges('#ch-sp-rev-tbody', '.ch-sp-rev-min', '.ch-sp-rev-max', '.ch-sp-rev-disc');
                const last = rules.length ? rules[rules.length - 1].max : 0;
                rules.push({ min: last, max: last + 1, disc: 4 });
                $('#ch-sp-rev-tbody').html(rules.map(function(r) { return chStdRangeRow('ch-sp-rev', r); }).join(''));
                chStdRefresh();
            });
            $('#ch-sp-buss-add').off('click.chstd').on('click.chstd', function() {
                const rules = chStdReadRanges('#ch-sp-buss-tbody', '.ch-sp-buss-min', '.ch-sp-buss-max', '.ch-sp-buss-disc');
                const last = rules.length ? rules[rules.length - 1].max : 0;
                rules.push({ min: last, max: last + 10, disc: 0 });
                $('#ch-sp-buss-tbody').html(rules.map(function(r) { return chStdRangeRow('ch-sp-buss', r); }).join(''));
                chStdRefresh();
            });
            $(document).off('click.chstddel').on('click.chstddel', '#chStdPrcModal .ch-sp-dil-del, #chStdPrcModal .ch-sp-age-del, #chStdPrcModal .ch-sp-rev-del, #chStdPrcModal .ch-sp-buss-del', function() {
                $(this).closest('tr').remove();
                chStdRefresh();
            });
            $(document).off('input.chstd').on('input.chstd', '#chStdPrcModal input', function() {
                clearTimeout(bindChStdPrcUi._t);
                bindChStdPrcUi._t = setTimeout(chStdRefresh, 180);
            });
            $(document).off('click.chsthist', '#chStdPrcModal .ch-sp-hist-dot').on('click.chsthist', '#chStdPrcModal .ch-sp-hist-dot', function(e) {
                e.preventDefault();
                const $dot = $(this);
                chStdOpenHist(String($dot.attr('data-chart') || ''), String($dot.attr('data-band') || ''), String($dot.attr('data-label') || ''), String($dot.attr('data-color') || '#0f172a'));
            });
            $('#ch-sp-hist-close').off('click.chsthist').on('click.chsthist', function() { $('#ch-sp-hist-wrap').removeClass('is-open'); });
        }
        window.chStdApplyActivePrices = function() {
            if (!chStdOwnsSprice()) return;
            chStdLoadClearance().always(function() {
            const updates = chStdWritePrices(chStdDraftNow());
            const redraw = function() {
                if (typeof table !== 'undefined' && table && typeof table.redraw === 'function') {
                    try { table.redraw(true); } catch (e) { /* ignore */ }
                }
                if (typeof updateSummary === 'function') updateSummary();
                if (typeof window.shopifyB2bRefreshSpriceCells === 'function') window.shopifyB2bRefreshSpriceCells();
            };
            const pending = chStdSavePrices(updates);
            if (pending && typeof pending.always === 'function') pending.always(redraw);
            else redraw();
            });
        };
        @include('partials.min-npft-sprice-mark', ['minNpftMarkPart' => 'script'])
        $(function() {
            bindChStdPrcUi();
            chStdScheduleAutoApply();
            if (window.minNpftSpriceMark && typeof window.minNpftSpriceMark.bind === 'function') {
                setTimeout(function() { window.minNpftSpriceMark.bind(0); }, 0);
            }
            setTimeout(chStdApplyPanelColumns, 800);
            setTimeout(chStdApplyPanelColumns, 2200);
        });
@endif
