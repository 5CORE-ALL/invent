{{--
    Ads Rule PMT + Multi Buy for one eBay account (replaces Dil vs SBid on eBay 2 / 3).
    Include once per part: css (inside <style>), buttons, modals, script (inside the page <script>), columns (inside the Tabulator columns array).
    Needs: $pmtPart, $pmtAccount ('ebay1' | 'ebay2' | 'ebay3').
--}}
@php
    $pmtAccount = $pmtAccount ?? 'ebay1';
    $pmtLabel = ['ebay1' => 'eBay 1', 'ebay2' => 'eBay 2', 'ebay3' => 'eBay 3'][$pmtAccount] ?? 'eBay';
    $pmtBase = ['ebay1' => '/ebay', 'ebay2' => '/ebay2', 'ebay3' => '/ebay3'][$pmtAccount] ?? '/ebay';
    $pmtBenchUrl = $pmtAccount === 'ebay1' ? '/benchmarks/data' : '/'.$pmtAccount.'-benchmarks/data';
    $pmtBenchPage = $pmtAccount === 'ebay1' ? '/benchmarks' : '/'.$pmtAccount.'-benchmarks';
    $pmtExcelPath = $pmtAccount === 'ebay1' ? '/excel' : '/'.$pmtAccount.'-excel';
    // $pmtAdsPage: the Campaign Ads page (one row per ad listing). It shows only the Ads Rule PMT button / window / PMT BID column.
    $pmtAdsPage = ! empty($pmtAdsPage);
    // The old Dil vs SBid S BID is the fallback for a category with no Highest (eBay 1 grid and eBay 1 / 2 Campaign Ads pages).
    $pmtSbidFallback = $pmtSbidFallback ?? ($pmtAccount === 'ebay1');
    $pmtDilNote = in_array($pmtAccount, ['ebay1', 'ebay2'], true) ? ' Dil vs SBid is not pushed for '.$pmtLabel.' while this is on.' : '';
    // eBay 2: the rule is decided per PARENT (family INV / OV L30 / eBay sold), not per child SKU.
    $pmtByParent = $pmtAccount === 'ebay2';
    $pmtUnit = $pmtAdsPage ? 'Listing' : ($pmtByParent ? 'Parent' : 'SKU');
@endphp
@if ($pmtPart === 'css')
        #ebay-bid-tip-box {
            position: fixed; z-index: 99999; display: none; width: 640px; max-width: 94vw; pointer-events: none;
            background: #0f172a; color: #f8fafc; border-radius: 12px; padding: 16px 20px; font-size: 14px; line-height: 1.5;
            box-shadow: 0 14px 40px rgba(15, 23, 42, 0.45); border: 1px solid #1e3a5f;
        }
        #ebay-bid-tip-box .tip-h { color: #93c5fd; font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; margin-top: 12px; padding-bottom: 3px; border-bottom: 1px solid #1e3a5f; margin-bottom: 4px; }
        #ebay-bid-tip-box .tip-h.tip-result { color: #86efac; }
        #ebay-bid-tip-box .tip-line { display: flex; gap: 8px; padding: 1px 0; }
        #ebay-bid-tip-box .tip-n { flex: 0 0 22px; color: #fbbf24; font-weight: 700; text-align: right; }
        #ebay-bid-tip-box .tip-dot { flex: 0 0 22px; color: #64748b; text-align: right; }
        #ebay-bid-tip-box .tip-res { font-size: 17px; font-weight: 700; color: #86efac; }
        #ebay-bid-tip-box .tip-h:first-child { margin-top: 0; }
        #ebay-bid-tip-box .tip-b { white-space: pre-wrap; word-break: break-word; }
        [data-bid-tip] { cursor: help; }
        /* Ads Rule PMT modal (next-gen look) */
        #adsRulePmtModal .modal-dialog { max-width: 98vw; width: 98vw; margin-left: auto; margin-right: auto; }
        @media (min-width: 901px) { #adsRulePmtModal .arp-body { grid-template-columns: 380px minmax(0, 1fr); } }
        .arp-modal { height: 94vh; border: 0; border-radius: 16px; overflow: hidden; box-shadow: 0 24px 60px rgba(15, 23, 42, .28); }
        .arp-header { display: flex; align-items: center; justify-content: space-between; padding: 14px 22px; color: #fff;
            background: linear-gradient(120deg, #1e3a8a 0%, #4f46e5 55%, #7c3aed 100%); }
        .arp-header .btn-close { filter: invert(1) grayscale(1) brightness(2); opacity: .85; }
        .arp-title { display: flex; align-items: center; gap: 12px; }
        .arp-title h5 { font-weight: 700; letter-spacing: .2px; }
        .arp-title-icon { width: 38px; height: 38px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center;
            background: rgba(255, 255, 255, .18); font-size: 16px; }
        .arp-subtitle { font-size: 12px; opacity: .85; }
        .arp-pause { flex: 0 0 auto; background: #fff; border-bottom: 1px solid #e6e8f0; }
        .arp-pause-head { display: flex; align-items: center; gap: 12px; padding: 10px 22px; cursor: pointer; background: #f5f3ff; }
        .arp-pause-icon { width: 30px; height: 30px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;
            background: #ede9fe; color: #6d28d9; font-size: 12px; }
        .arp-pause-body { padding: 8px 22px 12px; max-height: 32vh; overflow-y: auto; }
        .arp-pause-table thead th { font-size: 11px; text-transform: uppercase; letter-spacing: .4px; color: #64748b; border-bottom-width: 1px; }
        .arp-pause-table td { padding-top: 2px; padding-bottom: 2px; }
        .arp-pause-table .form-select, .arp-pause-table .form-control { border-radius: 8px; height: 30px; padding-top: 2px; padding-bottom: 2px; font-size: 13px; }
        .arp-body { flex: 1 1 auto; min-height: 0; display: grid; grid-template-columns: 340px minmax(0, 1fr); gap: 18px; padding: 18px 22px; background: #f6f7fb; }
        .arp-rules, .arp-cats { min-height: 0; display: flex; flex-direction: column; background: #fff; border-radius: 12px;
            border: 1px solid #e6e8f0; box-shadow: 0 1px 2px rgba(15, 23, 42, .04); padding: 14px; }
        .arp-section-title { font-weight: 700; font-size: 13px; text-transform: uppercase; letter-spacing: .6px; color: #334155; margin-bottom: 10px; }
        .arp-muted { text-transform: none; letter-spacing: 0; font-weight: 500; color: #94a3b8; }
        .arp-rule-list { display: flex; flex-direction: column; gap: 8px; overflow-y: auto; min-height: 0; }
        .arp-rule { display: grid; grid-template-columns: 30px 1fr auto; align-items: center; gap: 10px; padding: 9px 10px; border-radius: 10px;
            border: 1px solid #eceef5; background: #fafbff; }
        .arp-rule-num { width: 28px; height: 28px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 12px; color: #fff; background: #4f46e5; }
        .arp-rule.is-1 .arp-rule-num { background: #dc2626; }
        .arp-rule.is-2 .arp-rule-num { background: #16a34a; }
        .arp-rule.is-3 .arp-rule-num { background: #d97706; }
        .arp-rule.is-5 .arp-rule-num { background: #db2777; }
        .arp-rule.is-base .arp-rule-num { background: #94a3b8; }
        .arp-rule-cond { font-size: 12.5px; font-weight: 600; color: #1e293b; line-height: 1.25; }
        .arp-rule-bid { display: inline-block; margin-top: 3px; font-size: 11px; font-weight: 600; color: #4338ca; background: #eef0ff;
            border-radius: 999px; padding: 1px 8px; }
        .arp-rule-count { min-width: 44px; text-align: center; font-weight: 700; font-size: 14px; color: #0f172a; background: #fff;
            border: 1px solid #e2e5ef; border-radius: 8px; padding: 3px 6px; }
        .arp-rule-count small { display: block; font-size: 9px; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: .5px; }
        .arp-rule-total { display: flex; justify-content: space-between; align-items: center; margin-top: 4px; padding: 10px 12px; border-radius: 10px;
            background: #1e293b; color: #fff; font-weight: 600; font-size: 13px; }
        .arp-rule-total b { font-size: 16px; }
        .arp-toolbar { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 10px; }
        .arp-search { position: relative; flex: 1 1 200px; max-width: 260px; }
        .arp-search i { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 12px; }
        .arp-search input { padding-left: 30px; border-radius: 8px; }
        .arp-bulk { max-width: 380px; }
        .arp-summary { margin-left: auto; font-size: 12px; font-weight: 600; color: #4338ca; background: #eef0ff; border-radius: 999px; padding: 4px 12px; }
        .arp-table-wrap { flex: 1 1 auto; min-height: 0; overflow: auto; border: 1px solid #e6e8f0; border-radius: 10px; }
        .arp-table thead th { position: sticky; top: 0; z-index: 2; background: #eef0fb; color: #1e293b; font-size: 12px; text-transform: uppercase;
            letter-spacing: .4px; border-bottom: 1px solid #dfe3f1; }
        .arp-table th.arp-sortable { cursor: pointer; user-select: none; white-space: nowrap; }
        .arp-table th.arp-sortable:hover { background: #e3e7f7; }
        .arp-table th.arp-sortable .arp-sort-ind { display: inline-block; min-width: 14px; margin-left: 4px; color: #4f46e5; font-size: 11px; }
        .arp-table tbody tr:hover { background: #f8f9ff; }
        .arp-table td { font-size: 13px; padding-top: 3px; padding-bottom: 3px; }
        .arp-table .form-control { border-radius: 8px; height: 28px; }
        .arp-table td.arp-yours-sbid { color: #64748b; font-style: italic; font-weight: 500; }
        .arp-table td.arp-yours-ok { background: #dcfce7; box-shadow: inset 0 0 0 1px #86efac; }
        .arp-table input.arp-bench { background: #dcfce7; border-color: #86efac; }
        .arp-table input.arp-default { background: #fef3c7; border-color: #fcd34d; }
        .arp-filter { max-width: 210px; border-radius: 8px; }
        .arp-rule.is-click { cursor: pointer; }
        .arp-rule.is-click:hover { box-shadow: 0 0 0 2px #c7d2fe; }
        .arp-rule.is-active { box-shadow: 0 0 0 2px #6366f1; }
        .mb-buy-card { background: #fff; border: 1px solid #e6e8f0; border-radius: 12px; padding: 14px 10px; box-shadow: 0 1px 2px rgba(15, 23, 42, .04); }
        .mb-buy-title { font-weight: 700; font-size: 15px; color: #047857; margin-bottom: 8px; }
        .mb-buy-hint { font-size: 11px; color: #94a3b8; margin-top: 4px; text-transform: uppercase; letter-spacing: .5px; }
        .arp-switch { color: #fff; padding-left: 2.6em; }
        .arp-switch .form-check-input { width: 2.4em; height: 1.25em; cursor: pointer; }
        .arp-switch .form-check-input:checked { background-color: #22c55e; border-color: #22c55e; }
        .arp-switch .form-check-label { min-width: 24px; cursor: pointer; }
        .arp-mode-note { font-size: 11.5px; line-height: 1.35; border-radius: 8px; padding: 7px 10px; margin-bottom: 10px; }
        .arp-mode-note.is-off { background: #f1f5f9; color: #64748b; }
        .arp-mode-note.is-on { background: #ecfdf5; color: #047857; }
        .arp-footer { display: flex; justify-content: flex-end; gap: 10px; padding: 12px 22px; background: #fff; border-top: 1px solid #e6e8f0; }
        @media (max-width: 900px) {
            .arp-body { grid-template-columns: 1fr; overflow-y: auto; }
            .arp-table-wrap { max-height: 50vh; }
        }
@elseif ($pmtPart === 'buttons')
                    <button type="button" class="btn btn-sm btn-primary pricing-filter-item" id="ads-rule-pmt-btn"
                        title="Set the suggested PMT bid % for each category">
                        <i class="fas fa-bullhorn"></i> Ads Rule PMT
                    </button>
@if (! $pmtAdsPage)
                    <button type="button" class="btn btn-sm btn-success pricing-filter-item" id="multi-buy-btn"
                        title="Set the discount % for Buy 2, Buy 3 and Buy 4">
                        <i class="fas fa-tags"></i> Multi Buy
                    </button>
                    <div class="btn-group pricing-filter-item" id="ebay-cat-add-wrap">
                        <button type="button" class="btn btn-sm btn-warning fw-bold" id="ebay-cat-add-btn"
                            data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false"
                            title="Tick SKUs, click +, pick a category. It fills the Category column of the ticked rows, and PMT BID follows that category's Highest / Lowest.">
                            <i class="fas fa-plus"></i> Category
                        </button>
                        <div class="dropdown-menu p-2" id="ebay-cat-add-menu" style="min-width: 280px; max-height: 420px; overflow-y: auto;">
                            <div class="small fw-semibold mb-1" id="ebay-cat-add-info"></div>
                            <input type="text" class="form-control form-control-sm mb-2" id="ebay-cat-add-search" placeholder="Search category...">
                            <div id="ebay-cat-add-list"></div>
                        </div>
                    </div>
@endif
@elseif ($pmtPart === 'modals')
    <!-- Ads Rule PMT Modal: suggested bid % per category -->
    <div class="modal fade" id="adsRulePmtModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content arp-modal">
                <div class="arp-header">
                    <div class="arp-title">
                        <span class="arp-title-icon"><i class="fas fa-bullhorn"></i></span>
                        <div>
                            <h5 class="mb-0">Ads Rule PMT</h5>
                            <div class="arp-subtitle">Bid range by category &middot; rules decide which bid each {{ $pmtUnit }} gets</div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <div class="form-check form-switch arp-switch mb-0" title="On: the rules below are pushed to eBay automatically (INV 0 pauses the ad). Off: nothing is pushed.">
                            <input class="form-check-input" type="checkbox" role="switch" id="ads-rule-pmt-enabled">
                            <label class="form-check-label fw-semibold" for="ads-rule-pmt-enabled" id="ads-rule-pmt-enabled-label">Off</label>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>
                <!-- Pause conditions: dynamic IF / AND / OR on DIL, L30, INV. A {{ $pmtUnit }} that matches is paused (rule 1). -->
                <div class="arp-pause" id="arp-pause">
                    <div class="arp-pause-head" id="arp-pause-head" title="Click to show / hide the pause conditions">
                        <span class="arp-pause-icon"><i class="fas fa-pause"></i></span>
                        <div class="me-auto">
                            <div class="fw-bold">Pause ads when <span class="arp-muted" id="arp-pause-state"></span></div>
                            <div class="arp-muted" style="font-size: 12px;">DIL, L30 and INV conditions. INV = 0 always pauses. Empty values are ignored. AND is checked before OR.</div>
                        </div>
                        <div class="form-check form-switch mb-0" title="On: a {{ $pmtUnit }} that matches the conditions below is paused. Off: only INV = 0 pauses.">
                            <input class="form-check-input" type="checkbox" role="switch" id="arp-pause-enabled">
                            <label class="form-check-label small fw-semibold text-nowrap" for="arp-pause-enabled">Conditions On</label>
                        </div>
                        <i class="fas fa-chevron-up text-muted" id="arp-pause-chevron"></i>
                    </div>
                    <div class="arp-pause-body" id="arp-pause-body">
                        <table class="table table-sm align-middle mb-1 arp-pause-table">
                            <thead>
                                <tr>
                                    <th style="width: 36px;">#</th>
                                    <th style="width: 110px;">Join</th>
                                    <th>Column</th>
                                    <th>Condition</th>
                                    <th style="width: 130px;">Compare with</th>
                                    <th>Value</th>
                                    <th style="width: 40px;"></th>
                                </tr>
                            </thead>
                            <tbody id="arp-pause-rows"></tbody>
                        </table>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="arp-pause-add" style="border-style: dashed;"><i class="fas fa-plus me-1"></i>Add condition</button>
                        <span class="arp-muted ms-2" style="font-size: 12px;">Saved with <b>Save and Apply</b>.</span>
                    </div>
                </div>
                <div class="arp-body">
                    <!-- Rules -->
                    <aside class="arp-rules">
                        <div class="arp-section-title">Rules <span class="arp-muted">&middot; first match wins</span></div>
                        <div id="ads-rule-pmt-mode-note" class="arp-mode-note is-off">Off. No PMT bids are pushed.</div>
                        <div id="ads-rule-pmt-rules-body" class="arp-rule-list"></div>
                    </aside>
                    <!-- Categories -->
                    <section class="arp-cats">
                        <div class="arp-toolbar">
                            <div class="arp-search">
                                <i class="fas fa-search"></i>
                                <input type="text" class="form-control form-control-sm" id="ads-rule-pmt-search" placeholder="Search category...">
                            </div>
                            <select class="form-select form-select-sm arp-filter" id="ads-rule-pmt-filter" title="Show only the categories of one rule">
                                <option value="">All categories</option>
                                <option value="has">Has a Highest (rule 2)</option>
                                <option value="nomatch">No match (rule 3)</option>
                            </select>
                            <div class="input-group input-group-sm arp-bulk">
                                <input type="number" class="form-control" id="ads-rule-pmt-all-high" min="0" max="100" step="0.01" placeholder="All Highest">
                                <input type="number" class="form-control" id="ads-rule-pmt-all-low" min="0" max="100" step="0.01" placeholder="All Lowest">
                                <button type="button" class="btn btn-outline-primary" id="ads-rule-pmt-apply-all" title="Fill the visible categories (blank fields are left unchanged)">Apply</button>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-success" id="ads-rule-pmt-bench-fill"
                                title="Highest = Top 10%, Lowest = Bottom 10% of the Promoted Listings ads rate (Impressions in eBay search) from {{ $pmtBenchPage }}. Overwrites the visible categories.">
                                <i class="fas fa-magic"></i> Fill from Benchmarks
                            </button>
                            <span class="arp-summary" id="ads-rule-pmt-summary"></span>
                        </div>
                        <div class="arp-table-wrap">
                            <table class="table table-sm align-middle mb-0 arp-table">
                                <thead>
                                    <tr>
                                        <th class="arp-sortable" data-sort="category" title="Click to sort">Category<span class="arp-sort-ind"></span></th>
                                        <th class="text-center arp-sortable" data-sort="count" style="width: 80px;" title="Click to sort">{{ $pmtUnit }}s<span class="arp-sort-ind"></span></th>
                                        <th class="text-center arp-sortable" data-sort="yours" style="width: 110px;" title="Click to sort. Your own Promoted listings ad rate (Yours row, Impressions in eBay search) from the benchmarks. When there is none, the category S BID is shown in grey italics. Read only.">Promoted listings ad rate<span class="arp-sort-ind"></span></th>
                                        <th class="text-center arp-sortable" data-sort="cbid" style="width: 110px;" title="Click to sort. Live C BID of the category: the median current eBay bid of its listings (hover a value for the range). Read only.">CBID<span class="arp-sort-ind"></span></th>
                                        <th class="arp-sortable" data-sort="high" style="width: 140px;" title="Click to sort">Highest (%)<span class="arp-sort-ind"></span></th>
                                        <th class="arp-sortable" data-sort="low" style="width: 140px;" title="Click to sort">Lowest (%)<span class="arp-sort-ind"></span></th>
                                    </tr>
                                </thead>
                                <tbody id="ads-rule-pmt-body"></tbody>
                            </table>
                        </div>
                        <div class="small mt-2 text-muted" id="ads-rule-pmt-bench-status"></div>
                        <div class="small text-danger mt-2 d-none" id="ads-rule-pmt-error"></div>
                    </section>
                </div>
                <div class="arp-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="ads-rule-pmt-save"
                        title="Save the ranges. When the switch is On, the new bids are pushed to eBay right away.">
                        <i class="fa fa-save"></i> Save and Apply
                    </button>
                </div>
            </div>
        </div>
    </div>

@if (! $pmtAdsPage)
    <!-- Multi Buy Rule Modal -->
    <div class="modal fade" id="multiBuyModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content arp-modal" style="height:auto;">
                <div class="arp-header" style="background: linear-gradient(120deg, #047857 0%, #10b981 100%);">
                    <div class="arp-title">
                        <span class="arp-title-icon"><i class="fas fa-tags"></i></span>
                        <div>
                            <h5 class="mb-0">Multi Buy Rule</h5>
                            <div class="arp-subtitle">Discount % when a buyer takes 2, 3 or 4 items</div>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="p-4" style="background:#f6f7fb;">
                    <div class="row g-3 text-center">
                        <div class="col-4">
                            <div class="mb-buy-card">
                                <div class="mb-buy-title">Buy 2</div>
                                <div class="input-group input-group-sm">
                                    <input type="number" class="form-control text-center mb-buy-input" id="multi-buy-2" data-default="1" min="0" max="100" step="0.01" value="1">
                                    <span class="input-group-text">%</span>
                                </div>
                                <div class="mb-buy-hint">off</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="mb-buy-card">
                                <div class="mb-buy-title">Buy 3</div>
                                <div class="input-group input-group-sm">
                                    <input type="number" class="form-control text-center mb-buy-input" id="multi-buy-3" data-default="2" min="0" max="100" step="0.01" value="2">
                                    <span class="input-group-text">%</span>
                                </div>
                                <div class="mb-buy-hint">off</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="mb-buy-card">
                                <div class="mb-buy-title">Buy 4</div>
                                <div class="input-group input-group-sm">
                                    <input type="number" class="form-control text-center mb-buy-input" id="multi-buy-4" data-default="3" min="0" max="100" step="0.01" value="3">
                                    <span class="input-group-text">%</span>
                                </div>
                                <div class="mb-buy-hint">off</div>
                            </div>
                        </div>
                    </div>
                    <div class="small text-muted mt-3">Defaults are 1%, 2% and 3%. <b>Save and Apply</b> puts these discounts on every {{ $pmtLabel }} listing as volume pricing (Buy 2 / Buy 3 / Buy 4). <b>Save</b> only stores them.</div>
                    <div class="small mt-2 d-none" id="multi-buy-status"></div>
                    <div class="small text-warning-emphasis bg-warning-subtle rounded p-2 mt-2 d-none" id="multi-buy-warning">Volume Pricing (Weight + Dil + Std NPFT) has Rules On and Auto push On. Its next run (05:30 / 21:30 IST) will replace these discounts. Turn it off on the Volume Pricing page to keep Multi Buy.</div>
                    <div class="small text-danger mt-2 d-none" id="multi-buy-error"></div>
                </div>
                <div class="arp-footer">
                    <button type="button" class="btn btn-light border me-auto" id="multi-buy-reset">Reset to 1 / 2 / 3</button>
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-outline-success" id="multi-buy-save-only"><i class="fa fa-save"></i> Save</button>
                    <button type="button" class="btn btn-success" id="multi-buy-save" title="Save the discounts and push them to every {{ $pmtLabel }} listing"><i class="fa fa-bolt"></i> Save and Apply</button>
                </div>
            </div>
        </div>
    </div>
@endif
@elseif ($pmtPart === 'script')
            // ---- Hover box for S BID / C BID / PMT BID: where the bid comes from and how it is worked out ----
            const arpEsc = function(text) { return String(text == null ? '' : text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); };
            /**
             * Attributes for a hover-box cell. Either one object { src, data[], steps[], result, pushed, pmtSteps[] }
             * or the short form (source, formula, extra).
             */
            window.ebayBidTipAttrs = function(a, formula, extra) {
                const o = (a && typeof a === 'object') ? a : { src: a, result: formula, pushed: extra };
                return 'data-bid-tip="' + arpEsc(JSON.stringify({
                    src: o.src || '', data: o.data || [], steps: o.steps || [], result: o.result || '', pushed: o.pushed || '', pmtSteps: o.pmtSteps || []
                })) + '"';
            };
            /** What is pushed to eBay for this row: the Ads Rule PMT bid when its switch is on, else the Dil vs SBid S BID (eBay 1). */
            window.ebayBidPushedText = function(row) {
                try {
                    if (typeof window.ebayPmtRuleOn === 'function' && window.ebayPmtRuleOn() && typeof window.ebayPmtRuleBid === 'function') {
                        const r = window.ebayPmtRuleBid(row);
                        if (r) {
                            const head = r.pause ? 'No bid pushed (INV 0, a cost-per-sale ad cannot be paused)' : (r.bid !== null ? Number(r.bid).toFixed(1) + '% is pushed to eBay' : 'No bid pushed');
                            return 'Ads Rule PMT is ON: ' + head + '  [rule ' + r.rule + ']';
                        }
                    }
                    if (typeof getCombinedSbid === 'function') {
                        const res = getCombinedSbid(row);
                        if (res && !res.skip && !res.off && res.bid > 0) return 'Ads Rule PMT is off here: ' + Number(res.bid).toFixed(1) + '% (Dil vs SBid S BID) is pushed';
                    }
                } catch (e) { /* fall through */ }
                return '';
            };
            /** Data + steps of the Ads Rule PMT decision for a row (null when PMT does not apply to it). */
            window.ebayPmtDetail = function(row) {
                try {
                    if (typeof window.ebayPmtRuleBid !== 'function') return null;
                    const r = window.ebayPmtRuleBid(row);
                    return r ? { data: r.data || [], steps: r.steps || [], rule: r.rule, bid: r.bid } : null;
                } catch (e) { return null; }
            };
            (function() {
                if (window.__ebayBidTipWired) return;
                window.__ebayBidTipWired = true;
                let box = null;
                function ensure() {
                    if (!box) { box = document.createElement('div'); box.id = 'ebay-bid-tip-box'; document.body.appendChild(box); }
                    return box;
                }
                function section(h, t, cls) { return t ? '<div class="tip-h ' + (cls || '') + '">' + arpEsc(h) + '</div><div class="tip-b">' + arpEsc(t) + '</div>' : ''; }
                function bullets(h, list) {
                    if (!list || !list.length) return '';
                    return '<div class="tip-h">' + arpEsc(h) + '</div>' + list.map(function(x) { return '<div class="tip-line"><span class="tip-dot">&bull;</span><span class="tip-b">' + arpEsc(x) + '</span></div>'; }).join('');
                }
                function numbered(h, list) {
                    if (!list || !list.length) return '';
                    return '<div class="tip-h">' + arpEsc(h) + '</div>' + list.map(function(x, i) { return '<div class="tip-line"><span class="tip-n">' + (i + 1) + '.</span><span class="tip-b">' + arpEsc(x) + '</span></div>'; }).join('');
                }
                function render(o) {
                    return section('Source', o.src)
                        + bullets('Data used', o.data)
                        + numbered('Steps followed', o.steps)
                        + (o.result ? '<div class="tip-h tip-result">Result</div><div class="tip-b tip-res">' + arpEsc(o.result) + '</div>' : '')
                        + (o.pushed ? '<div class="tip-h">Pushed to eBay</div><div class="tip-b">' + arpEsc(o.pushed) + '</div>' : '')
                        + numbered('Ads Rule PMT steps', o.pmtSteps);
                }
                document.addEventListener('mouseover', function(e) {
                    const el = e.target && e.target.closest ? e.target.closest('[data-bid-tip]') : null;
                    if (!el) return;
                    const b = ensure();
                    let o = {};
                    try { o = JSON.parse(el.getAttribute('data-bid-tip')) || {}; } catch (err) { o = {}; }
                    b.innerHTML = render(o);
                    b.style.display = 'block';
                    const r = el.getBoundingClientRect();
                    const w = b.offsetWidth, h = b.offsetHeight;
                    const left = Math.min(Math.max(8, r.left + r.width / 2 - w / 2), window.innerWidth - w - 8);
                    let top = r.bottom + 8;
                    if (top + h > window.innerHeight - 8) top = Math.max(8, r.top - h - 8);
                    b.style.left = left + 'px';
                    b.style.top = top + 'px';
                });
                document.addEventListener('mouseout', function(e) {
                    const el = e.target && e.target.closest ? e.target.closest('[data-bid-tip]') : null;
                    if (el && box && !(e.relatedTarget && el.contains(e.relatedTarget))) box.style.display = 'none';
                });
                document.addEventListener('scroll', function() { if (box) box.style.display = 'none'; }, true);
            })();

            /** Parent / summary rows get no rule (child SKUs carry Parent = "PARENT xyz", which is just the parent's name). */
            window.ebayPmtIsParent = function(d) {
                if (!d) return true;
                if (d.is_parent_summary === true || d.is_parent_row === true || d.is_parent === true) return true;
                const sku = String(d['(Child) sku'] || d.SKU || d.sku || '').toUpperCase();
                return sku.indexOf('PARENT') !== -1;
            };

            /**
             * Rows the Ads Rule PMT applies to. eBay 2 decides per PARENT (the PARENT row carries the family
             * INV / OV L30 / eBay L30); a child SKU only gets a rule when it has no parent at all.
             * Other accounts decide per SKU (every non-parent row).
             */
            const PMT_BY_PARENT = @json($pmtByParent);
            window.ebayPmtIsRuled = function(d) {
                if (!d) return false;
                // A page can narrow the rows the rule applies to (the Campaign Ads pages: running cost-per-sale ads only).
                if (typeof window.ebayPmtRowActive === 'function' && !window.ebayPmtRowActive(d)) return false;
                if (d.pmt_family_row === true) return true; // Campaign Ads row of eBay 2: carries its parent family's numbers
                if (!PMT_BY_PARENT) return !window.ebayPmtIsParent(d);
                if (d.is_parent_summary === true) return false;
                if (window.ebayPmtIsParent(d)) return true;
                return String(d.Parent || '').trim() === '';
            };

            // ===== Ads Rule PMT: suggested bid (%) per category =====
            (function() {
                const PMT_BIDS_URL = @json($pmtBase.'/campaign-ads/pmt-category-bids');
                const modalEl = document.getElementById('adsRulePmtModal');
                if (!modalEl) return;
                let savedBids = {};
                let ruleOn = false;

                function paintMode() {
                    $('#ads-rule-pmt-enabled').prop('checked', ruleOn);
                    $('#ads-rule-pmt-enabled-label').text(ruleOn ? 'On' : 'Off');
                    $('#ads-rule-pmt-mode-note')
                        .toggleClass('is-on', ruleOn).toggleClass('is-off', !ruleOn)
                        .text(ruleOn
                            ? 'On. Each running {{ $pmtLabel }} ad gets its rule bid (INV 0 pauses it) after Save, when inventory or sales change, and every 30 minutes.{{ $pmtDilNote }}'
                            : 'Off. No PMT bids are pushed.');
                }

                // ---- Rules (first match wins) ----
                //  1. INV = 0                          -> bid 0, ads paused
                //  2. category has a Highest           -> Highest
                //  3. no match                         -> the old Dil vs SBid S Bid (when enabled; else no bid)
                const PMT_SBID_FALLBACK = @json((bool) $pmtSbidFallback);
                const RULE_DEFS = [
                    { key: '1', label: 'INV = 0', bid: '0 &mdash; ads paused' },
                    { key: '2', label: 'Category has a Highest (match)', bid: 'Highest' },
                    { key: '3', label: 'No match for the category', bid: PMT_SBID_FALLBACK ? 'Old rule: S BID' : 'no bid' }
                ];

                function rowInv(row) {
                    return Number(String(row.INV == null ? '' : row.INV).replace(/[,\s\u00A0]/g, '')) || 0;
                }
                function rowDil(row) {
                    // Parent rows (eBay 2) hold the family sums: Dil = family OV L30 ÷ family INV.
                    if (typeof chPromoDil === 'function' && !(PMT_BY_PARENT && window.ebayPmtIsParent(row))) return Number(chPromoDil(row)) || 0;
                    const inv = rowInv(row);
                    return inv > 0 ? ((parseFloat(row.L30) || 0) / inv) * 100 : 0;
                }
                function num(v) {
                    const n = parseFloat(v);
                    return isFinite(n) ? n : null;
                }
                // ---- Pause conditions (dynamic): IF / AND / OR on DIL, L30, INV. A match = rule 1 (ad paused). ----
                const PAUSE_COLS = { dil: 'DIL %', l30: 'L30', inv: 'INV' };
                const PAUSE_OPS = { '<': '< less than', '<=': '<= less or equal', '=': '= equal to', '!=': '!= not equal to', '>=': '>= greater or equal', '>': '> greater than' };
                function defaultPauseConditions() {
                    return [
                        { join: 'AND', column: 'dil', op: '<', mode: 'number', value: '' },
                        { join: 'AND', column: 'l30', op: '<', mode: 'number', value: '' },
                        { join: 'AND', column: 'inv', op: '>', mode: 'number', value: '' }
                    ];
                }
                let pauseRule = { enabled: false, conditions: defaultPauseConditions() };
                function setPauseRule(p) {
                    const conds = (p && Array.isArray(p.conditions)) ? p.conditions.filter(function(c) { return c && PAUSE_COLS[c.column]; }) : [];
                    pauseRule = { enabled: !!(p && p.enabled), conditions: conds.length ? conds : defaultPauseConditions() };
                }
                function pauseCondActive(c) {
                    if (c.mode === 'column') return !!PAUSE_COLS[c.value];
                    return c.value !== '' && c.value != null && isFinite(parseFloat(c.value));
                }
                function pauseVals(row) {
                    return { dil: rowDil(row), l30: parseFloat(row.L30) || 0, inv: rowInv(row) };
                }
                function pauseCmp(left, op, right) {
                    switch (op) {
                        case '<': return left < right;
                        case '<=': return left <= right;
                        case '=': return Math.abs(left - right) < 1e-9;
                        case '!=': return Math.abs(left - right) >= 1e-9;
                        case '>=': return left >= right;
                        case '>': return left > right;
                    }
                    return false;
                }
                function pauseHasConditions() { return pauseRule.conditions.some(pauseCondActive); }
                /** Does the pause rule fire for this row? (AND binds tighter than OR; same logic as the push.) */
                function pauseMatchRow(row) {
                    if (!pauseRule.enabled) return false;
                    const v = pauseVals(row);
                    const groups = [];
                    let cur = null;
                    pauseRule.conditions.forEach(function(c) {
                        if (!pauseCondActive(c)) return;
                        const right = c.mode === 'column' ? v[c.value] : parseFloat(c.value);
                        const hit = pauseCmp(v[c.column], c.op, right);
                        if (cur === null || c.join === 'OR') { cur = []; groups.push(cur); }
                        cur.push(hit);
                    });
                    return groups.some(function(g) { return g.every(Boolean); });
                }
                function pauseColOptions(sel) {
                    return Object.keys(PAUSE_COLS).map(function(k) { return '<option value="' + k + '"' + (k === sel ? ' selected' : '') + '>' + PAUSE_COLS[k] + '</option>'; }).join('');
                }
                function renderPauseRows() {
                    const html = pauseRule.conditions.map(function(c, i) {
                        const joinCell = i === 0
                            ? '<span class="badge" style="background:#ede9fe;color:#6d28d9;">IF</span>'
                            : '<select class="form-select form-select-sm pz-join">' + ['AND', 'OR'].map(function(j) { return '<option' + (c.join === j ? ' selected' : '') + '>' + j + '</option>'; }).join('') + '</select>';
                        const ops = Object.keys(PAUSE_OPS).map(function(k) { return '<option value="' + k + '"' + (c.op === k ? ' selected' : '') + '>' + arpEsc(PAUSE_OPS[k]) + '</option>'; }).join('');
                        const valueCell = c.mode === 'column'
                            ? '<select class="form-select form-select-sm pz-valcol">' + pauseColOptions(c.value) + '</select>'
                            : '<input type="number" step="any" class="form-control form-control-sm pz-valnum" placeholder="e.g. 5" value="' + arpEsc(c.value) + '">';
                        return '<tr data-i="' + i + '"><td class="text-muted">' + (i + 1) + '</td><td>' + joinCell + '</td>'
                            + '<td><select class="form-select form-select-sm pz-col">' + pauseColOptions(c.column) + '</select></td>'
                            + '<td><select class="form-select form-select-sm pz-op">' + ops + '</select></td>'
                            + '<td><select class="form-select form-select-sm pz-mode"><option value="number"' + (c.mode !== 'column' ? ' selected' : '') + '>Number</option><option value="column"' + (c.mode === 'column' ? ' selected' : '') + '>Column</option></select></td>'
                            + '<td>' + valueCell + '</td>'
                            + '<td><button type="button" class="btn btn-sm btn-outline-danger pz-del" title="Remove"' + (pauseRule.conditions.length <= 1 ? ' disabled' : '') + '><i class="fas fa-trash"></i></button></td></tr>';
                    }).join('');
                    $('#arp-pause-rows').html(html);
                    $('#arp-pause-enabled').prop('checked', pauseRule.enabled);
                    paintPauseState();
                }
                function paintPauseState() {
                    $('#arp-pause-state').text(!pauseRule.enabled ? '(off, only INV = 0 pauses)' : (pauseHasConditions() ? '(on)' : '(on, but no condition has a value yet)'));
                }
                /** DOM -> pauseRule (keeps edits not saved yet). */
                function readPauseRows() {
                    const conds = [];
                    $('#arp-pause-rows tr').each(function(i) {
                        const $r = $(this);
                        const mode = $r.find('.pz-mode').val() || 'number';
                        conds.push({
                            join: i === 0 ? 'AND' : ($r.find('.pz-join').val() || 'AND'),
                            column: $r.find('.pz-col').val(),
                            op: $r.find('.pz-op').val(),
                            mode: mode,
                            value: mode === 'column' ? $r.find('.pz-valcol').val() : $r.find('.pz-valnum').val()
                        });
                    });
                    if (conds.length) pauseRule.conditions = conds;
                    pauseRule.enabled = $('#arp-pause-enabled').is(':checked');
                }
                /** Which rule key ('1'..'4') applies to this {{ $pmtUnit }} row. */
                function classifyRow(row) {
                    if (rowInv(row) <= 0) return '1';
                    if (pauseMatchRow(row)) return '1';
                    const saved = savedBids[String(row.excel_category || '').trim()] || {};
                    if (num(saved.highest) !== null) return '2';
                    return '3';
                }
                /** The grid's S BID (old Dil vs SBid rule) of a row, or null when it has none. */
                function rowSbid(row) {
                    try {
                        if (typeof getCombinedSbid !== 'function') return null;
                        const res = getCombinedSbid(row);
                        if (!res || res.skip || res.off || !(Number(res.bid) > 0)) return null;
                        return Number(res.bid);
                    } catch (e) { return null; }
                }
                /**
                 * Suggested PMT bid for a row: { rule, bid, pause, missing, title }
                 * (null for rows the rule does not apply to: child SKUs on eBay 2, PARENT rows elsewhere).
                 * bid is null when there is no match and no old S Bid to fall back on.
                 */
                window.ebayPmtRuleBid = function(row) {
                    if (!row || !window.ebayPmtIsRuled(row)) return null;
                    const rule = classifyRow(row);
                    const cat = String(row.excel_category || '').trim();
                    const saved = savedBids[cat] || {};
                    const out = { rule: rule, bid: null, pause: false, missing: false, title: '', data: [], steps: [] };
                    const fmt = function(v) { return v === null || v === undefined ? 'not set' : v + '%'; };
                    out.data.push('Category: ' + (cat || 'none (no Category Masters category)'));
                    out.data.push((PMT_BY_PARENT ? 'Family INV: ' : 'INV: ') + rowInv(row));
                    out.data.push('Saved for the category: Highest ' + fmt(num(saved.highest)) + ', Lowest ' + fmt(num(saved.lowest)));
                    out.steps.push('Is INV above 0?  ' + (rowInv(row) > 0 ? 'Yes (INV ' + rowInv(row) + ')' : 'No -> bid 0, the ad is left as it is (eBay cannot pause a cost-per-sale ad)'));
                    const pausedByConds = rowInv(row) > 0 && pauseMatchRow(row);
                    if (rowInv(row) > 0 && pauseRule.enabled) out.steps.push('Do the pause conditions match (DIL ' + Math.round(rowDil(row) * 100) / 100 + '%, L30 ' + (parseFloat(row.L30) || 0) + ', INV ' + rowInv(row) + ')?  ' + (pausedByConds ? 'Yes -> bid 0, the ad is paused' : 'No'));
                    if (rowInv(row) > 0 && !pausedByConds) out.steps.push('Does the category have a Highest?  ' + (num(saved.highest) !== null ? 'Yes (' + num(saved.highest) + '%) -> use it' : 'No'));
                    if (rowInv(row) > 0 && !pausedByConds && num(saved.highest) === null) {
                        out.steps.push(PMT_SBID_FALLBACK ? 'No match -> use the old Dil vs SBid S BID of this listing' : 'No match -> no bid is pushed for this ' + @json($pmtUnit));
                    }
                    let calc = null;
                    if (rule === '1') {
                        out.bid = 0; out.pause = true;
                        out.title = (rowInv(row) > 0 ? 'Rule 1: the pause conditions match' : 'Rule 1: INV = ' + rowInv(row) + ' (not above 0)') + '\nBid 0 / pause the ad. eBay cannot pause a cost-per-sale ad, so the ad is left as it is.';
                        return out;
                    }
                    if (rule === '2') {
                        calc = num(saved.highest);
                        out.title = 'Rule 2: category "' + cat + '" has a Highest of ' + calc + '%\nBid = Highest (kept between 2 and 100).';
                    } else if (PMT_SBID_FALLBACK) {
                        calc = rowSbid(row);
                        let old = '';
                        try { old = (typeof getCombinedSbid === 'function' && getCombinedSbid(row).title) || ''; } catch (e) { old = ''; }
                        out.title = 'Rule 3: ' + (cat ? 'category "' + cat + '"' : 'this row has no category') + ' has no Highest\nBid = the old Dil vs SBid S BID' + (old ? ':\n' + old : '');
                    } else {
                        out.title = 'Rule 3: no Highest for ' + (cat ? '"' + cat + '"' : 'this row') + ' — set one in Ads Rule PMT';
                    }
                    if (calc === null) {
                        out.missing = true;
                        if (rule === '3' && PMT_SBID_FALLBACK) out.title += ' (no S BID for this row)';
                        return out;
                    }
                    out.bid = Math.min(100, Math.max(2, Math.round(calc * 100) / 100)); // eBay accepts 2-100
                    out.steps.push('Keep the bid between 2% and 100% (eBay limits) -> ' + Number(out.bid).toFixed(1) + '% is the PMT bid');
                    return out;
                };

                /** Whether the Ads Rule PMT switch is on (read by the S BID / C BID hover boxes). */
                window.ebayPmtRuleOn = function() { return !!ruleOn; };

                /** Re-render the PMT BID column (after bids load / save). */
                function refreshPmtColumn() {
                    try {
                        const t = window.table;
                        const col = t && t.getColumn ? t.getColumn('pmt_rule_bid') : null;
                        if (col) col.getCells().forEach(function(c) { c.reformat(); });
                    } catch (e) { /* table not built yet — formatter reads savedBids when it renders */ }
                    // A page can repaint other cells that depend on the rule (the C Bid sync dots on the Campaign Ads pages).
                    try { if (typeof window.ebayPmtAfterChange === 'function') window.ebayPmtAfterChange(); } catch (e) { /* ignore */ }
                }

                function loadSavedBids() {
                    return fetch(PMT_BIDS_URL, { headers: { 'Accept': 'application/json' } })
                        .then(function(r) { return r.json(); })
                        .then(function(d) { savedBids = (d && d.bids) || {}; ruleOn = !!(d && d.enabled); setPauseRule(d && d.pause_rule); paintMode(); })
                        .catch(function() { savedBids = {}; });
                }

                function currentRows() {
                    return (typeof allTableData !== 'undefined' && Array.isArray(allTableData) && allTableData.length)
                        ? allTableData
                        : (window.table ? window.table.getData('all') : []);
                }

                function renderRuleCounts() {
                    const counts = { '1': 0, '2': 0, '3': 0 };
                    let total = 0;
                    currentRows().forEach(function(row) {
                        if (!window.ebayPmtIsRuled(row)) return;
                        counts[classifyRow(row)]++;
                        total++;
                    });
                    const $b = $('#ads-rule-pmt-rules-body').empty();
                    RULE_DEFS.forEach(function(r) {
                        $b.append(
                            $('<div class="arp-rule">').addClass('is-' + r.key + (r.key === '1' ? '' : ' is-click'))
                                .attr('title', r.key === '1' ? '' : 'Click to show only these categories')
                                .append($('<span class="arp-rule-num">').text(r.key))
                                .append($('<div>')
                                    .append($('<div class="arp-rule-cond">').html(r.key === '1' && pauseRule.enabled && pauseHasConditions() ? 'INV = 0 <i>or</i> pause conditions match' : r.label))
                                    .append($('<span class="arp-rule-bid">').html(r.bid)))
                                .append($('<div class="arp-rule-count">').text(counts[r.key].toLocaleString()).append('<small>{{ $pmtUnit }}s</small>'))
                        );
                    });
                    $b.append(
                        $('<div class="arp-rule-total">')
                            .append('<span>Total {{ $pmtUnit }}s</span>')
                            .append($('<b>').text(total.toLocaleString()))
                    );
                    applyArpFilter(); // repaint the active filter card (the cards were just rebuilt)
                }

                window.ebayPmtRefresh = refreshPmtColumn;
                loadSavedBids().then(refreshPmtColumn);

                // ---- Benchmarks (/benchmarks): "Impressions in eBay search" -> Promoted Listings ads rate ----
                // Highest = Top 10%, Lowest = Bottom 10%, matched to the modal category (= Excel sheet name).
                const BENCH_URL = @json($pmtBenchUrl);
                let benchPromise = null;

                function normCat(v) { return String(v || '').toLowerCase().replace(/\s+/g, ' ').trim(); }
                function benchPct(v) {
                    const n = parseFloat(String(v == null ? '' : v).replace(/[%,$\s]/g, ''));
                    return isFinite(n) ? n : null;
                }
                /** Promoted Listings ads rate column: prefers a title with promoted + ad(s) + rate. */
                function findRateField(columns) {
                    let best = null;
                    let bestScore = 0;
                    (columns || []).forEach(function(c) {
                        const t = String(c.title || '').toLowerCase();
                        let score = 0;
                        if (t.indexOf('promoted') !== -1) score += 2;
                        if (/\bads?\b/.test(t)) score += 1;
                        if (t.indexOf('rate') !== -1) score += 2;
                        if (score >= 4 && score > bestScore) { best = c; bestScore = score; }
                    });
                    return best;
                }
                function loadBenchmarks() {
                    if (benchPromise) return benchPromise;
                    benchPromise = fetch(BENCH_URL, { headers: { 'Accept': 'application/json' } })
                        .then(function(r) { return r.json(); })
                        .then(function(d) {
                            const col = findRateField(d && d.columns);
                            const map = {};
                            if (col) {
                                ((d && d.rows) || []).forEach(function(row) {
                                    if (normCat(row.section).indexOf('impressions in ebay search') === -1) return;
                                    const key = normCat(row.category);
                                    const val = benchPct(row[col.field]);
                                    if (!key || val === null) return;
                                    map[key] = map[key] || {};
                                    if (row.benchmark === 'Top 10%') map[key].highest = val;
                                    else if (row.benchmark === 'Bottom 10%') map[key].lowest = val;
                                    else if (row.benchmark === 'Yours') map[key].yours = String(row[col.field]).trim();
                                });
                            }
                            return { map: map, column: col ? col.title : '' };
                        })
                        .catch(function() {
                            benchPromise = null; // allow a retry next time
                            return { map: {}, column: '', failed: true };
                        });
                    return benchPromise;
                }
                function benchFor(map, category) {
                    const c = normCat(category);
                    if (map[c]) return map[c];
                    // Sheet names are cut at 31 chars, so also accept a prefix match.
                    const keys = Object.keys(map);
                    for (let i = 0; i < keys.length; i++) {
                        const k = keys[i];
                        if (Math.min(k.length, c.length) >= 10 && (c.indexOf(k) === 0 || k.indexOf(c) === 0)) return map[k];
                    }
                    return null;
                }
                let benchMapLoaded = null;

                /**
                 * Green background on the Promoted listings ad rate cell when it agrees with the Highest (%) of its row:
                 * Yours equals Highest.
                 */
                function markYoursOk($tr) {
                    const $c = $tr.find('.arp-yours');
                    const high = parseFloat($tr.find('.ads-rule-pmt-high').val());
                    const yours = $c.hasClass('arp-yours-sbid') ? NaN : parseFloat($c.text());
                    const same = function(a, b) { return isFinite(a) && isFinite(b) && Math.abs(a - b) < 0.005; };
                    const ok = same(yours, high);
                    $c.toggleClass('arp-yours-ok', ok);
                    if (ok) $c.attr('title', 'Yours equals Highest (' + high + '%)');
                }

                /** Promoted listings ad rate cell: the Yours value from the benchmarks, else the category's S BID (grey). */
                function paintYoursCell($tr, hit, sbids) {
                    paintYoursCellText($tr, hit, sbids);
                    markYoursOk($tr);
                }
                function paintYoursCellText($tr, hit, sbids) {
                    const $c = $tr.find('.arp-yours');
                    const yours = hit && hit.yours ? String(hit.yours) : '';
                    if (yours) {
                        $c.text(yours).removeClass('arp-yours-sbid').attr('title', 'Your Promoted listings ad rate (benchmarks)');
                        return;
                    }
                    const sb = sbids ? sbids[$tr.attr('data-category')] : null;
                    if (sb != null) {
                        const via = sbidViaDil[$tr.attr('data-category')];
                        $c.text(sb.toFixed(1) + '%').addClass('arp-yours-sbid').attr('title', via != null
                            ? 'No Yours value and no S BID in the grid for this category: Dil vs SBid slab for its Dil ' + via + '%'
                            : 'No Yours value in the benchmarks: showing the category S BID');
                    } else {
                        $c.text('').removeClass('arp-yours-sbid').attr('title', '');
                    }
                }

                /** Light green on every Highest / Lowest input whose value equals the benchmark of its category. */
                function markBenchMatches($rows) {
                    if (!benchMapLoaded) return;
                    ($rows || $('#ads-rule-pmt-body tr[data-category]')).each(function() {
                        const hit = benchFor(benchMapLoaded, $(this).attr('data-category'));
                        [['.ads-rule-pmt-high', hit && hit.highest], ['.ads-rule-pmt-low', hit && hit.lowest]].forEach(function(p) {
                            const $in = $(this).find(p[0]);
                            const v = $in.val();
                            const match = p[1] != null && v !== '' && Math.abs(parseFloat(v) - p[1]) < 0.005;
                            $in.toggleClass('arp-bench', !!match);
                        }.bind(this));
                    });
                }

                /** Fill Highest / Lowest inputs from the benchmarks. overwrite=false only fills blank inputs. */
                function fillFromBenchmarks(overwrite) {
                    const $status = $('#ads-rule-pmt-bench-status').text('Loading benchmarks...');
                    return loadBenchmarks().then(function(b) {
                        let cats = 0;
                        let missing = 0;
                        let defaulted = 0;
                        const benchOk = !b.failed && !!b.column;
                        $('#ads-rule-pmt-body tr[data-category]' + (overwrite ? ':visible' : '')).each(function() {
                            const hit = benchFor(b.map, $(this).attr('data-category'));
                            if (!hit || (hit.highest == null && hit.lowest == null)) missing++;
                            let touched = false;
                            let usedDefault = false;
                            [['.ads-rule-pmt-high', hit && hit.highest, ARP_DEFAULT_HIGH], ['.ads-rule-pmt-low', hit && hit.lowest, ARP_DEFAULT_LOW]].forEach(function(p) {
                                const $in = $(this).find(p[0]);
                                if (p[1] != null) {
                                    if (overwrite || $in.val() === '') {
                                        $in.val(p[1]).removeClass('arp-default').addClass('arp-bench');
                                        touched = true;
                                    }
                                } else if (benchOk && $in.val() === '') {
                                    // No benchmark for this category: Highest 8 / Lowest 5. Only into a blank input, so a value
                                    // typed by the user (or saved earlier) is never overwritten.
                                    $in.val(p[2]).addClass('arp-default');
                                    usedDefault = true;
                                }
                            }.bind(this));
                            if (touched) cats++;
                            if (usedDefault) defaulted++;
                        });
                        benchMapLoaded = b.map;
                        const sbidsNow = sbidByCategory();
                        $('#ads-rule-pmt-body tr[data-category]').each(function() {
                            paintYoursCell($(this), benchFor(b.map, $(this).attr('data-category')), sbidsNow);
                        });
                        markBenchMatches(); // also saved values that equal the benchmark
                        if (arpSort.dir !== 0) applyArpSort(); // Promoted listings ad rate / Highest / Lowest just changed
                        updateSummary();
                        if (b.failed) $status.text('Could not load {{ $pmtBenchPage }} data.');
                        else if (!b.column) $status.text('No "Promoted Listings ads rate" column found in {{ $pmtBenchPage }} (upload the {{ $pmtLabel }} LQS report in {{ $pmtExcelPath }}).');
                        else $status.text('Benchmarks (' + b.column + ', Impressions in eBay search): filled ' + cats + ' categor' + (cats === 1 ? 'y' : 'ies')
                            + (missing ? ', ' + missing + ' without a benchmark' + (defaulted ? ' (' + defaulted + ' set to the default Highest ' + ARP_DEFAULT_HIGH + ' / Lowest ' + ARP_DEFAULT_LOW + ', yellow)' : '') : '')
                            + '. Light green = value matches the benchmark. Filled values are not saved until you press Save.');
                        applyArpFilter();
                    });
                }
                const ARP_DEFAULT_HIGH = 8;
                const ARP_DEFAULT_LOW = 5;

                /** Distinct categories (+ SKU counts) from the live table data, merged with saved ones. */
                function collectCategories() {
                    const src = (typeof allTableData !== 'undefined' && Array.isArray(allTableData) && allTableData.length)
                        ? allTableData
                        : (table ? table.getData('all') : []);
                    const counts = {};
                    src.forEach(function(row) {
                        if (!window.ebayPmtIsRuled(row)) return;
                        const cat = String(row.excel_category || '').trim();
                        if (cat) counts[cat] = (counts[cat] || 0) + 1;
                    });
                    Object.keys(savedBids).forEach(function(cat) {
                        if (!(cat in counts)) counts[cat] = 0;
                    });
                    return Object.keys(counts).sort(function(a, b) { return a.localeCompare(b); })
                        .map(function(cat) { return { category: cat, count: counts[cat] }; });
                }

                /** Categories whose S BID came from the Dil vs SBid slabs (no row had a grid S BID): { category: Dil % }. */
                let sbidViaDil = {};

                /**
                 * Median S BID (grid S BID column) of the ruled rows of each category, 1 decimal.
                 * A category where no row has an S BID takes the Dil vs SBid slab bid of its overall Dil
                 * (OV L30 / INV of all its rows).
                 */
                function sbidByCategory() {
                    sbidViaDil = {};
                    if (!PMT_SBID_FALLBACK) return {};
                    const buckets = {};
                    const dilAgg = {};
                    currentRows().forEach(function(row) {
                        if (!window.ebayPmtIsRuled(row)) return;
                        const cat = String(row.excel_category || '').trim();
                        if (!cat) return;
                        const v = rowSbid(row);
                        if (v !== null) (buckets[cat] = buckets[cat] || []).push(v);
                        const inv = rowInv(row);
                        if (inv > 0) {
                            const a = dilAgg[cat] = dilAgg[cat] || { inv: 0, sold: 0 };
                            a.inv += inv;
                            a.sold += rowDil(row) * inv / 100;
                        }
                    });
                    const out = {};
                    if (typeof dilSbidDilPart === 'function' && typeof currentDilSbidSlabs !== 'undefined') {
                        Object.keys(dilAgg).forEach(function(cat) {
                            if (buckets[cat] && buckets[cat].length) return;
                            const dil = dilAgg[cat].inv > 0 ? (dilAgg[cat].sold / dilAgg[cat].inv) * 100 : null;
                            if (dil === null || !isFinite(dil)) return;
                            let part = null;
                            try { part = dilSbidDilPart(null, dil); } catch (e) { part = null; }
                            if (part && part.bid > 0) {
                                out[cat] = Math.round(part.bid * 10) / 10;
                                sbidViaDil[cat] = Math.round(dil * 100) / 100;
                            }
                        });
                    }
                    Object.keys(buckets).forEach(function(cat) {
                        const a = buckets[cat].sort(function(x, y) { return x - y; });
                        const m = a.length % 2 ? a[(a.length - 1) / 2] : (a[a.length / 2 - 1] + a[a.length / 2]) / 2;
                        out[cat] = Math.round(m * 10) / 10;
                    });
                    return out;
                }

                /** Live C BID of a row (grids: ca_bid_percentage, Campaign Ads pages: bid_percentage), or null. */
                function rowCBid(row) {
                    const v = parseFloat(row.ca_bid_percentage != null && row.ca_bid_percentage !== '' ? row.ca_bid_percentage : row.bid_percentage);
                    return isFinite(v) && v > 0 ? v : null;
                }

                /** The live C BID of every category: { category: { median, min, max, n } } over its listings that have a bid. */
                function cbidByCategory() {
                    const buckets = {};
                    currentRows().forEach(function(row) {
                        const cat = String(row.excel_category || '').trim();
                        if (!cat) return;
                        const v = rowCBid(row);
                        if (v !== null) (buckets[cat] = buckets[cat] || []).push(v);
                    });
                    const out = {};
                    Object.keys(buckets).forEach(function(cat) {
                        const a = buckets[cat].sort(function(x, y) { return x - y; });
                        const m = a.length % 2 ? a[(a.length - 1) / 2] : (a[a.length / 2 - 1] + a[a.length / 2]) / 2;
                        out[cat] = { median: Math.round(m * 10) / 10, min: a[0], max: a[a.length - 1], n: a.length };
                    });
                    return out;
                }

                // ---- Sorting: click a header (A-Z / low-high, then Z-A / high-low, then back to the default order) ----
                let arpSort = { key: null, dir: 0 }; // dir: 1 = ascending, -1 = descending, 0 = default (category A-Z)
                function arpSortValue($tr, key) {
                    const cells = $tr.children();
                    const numOf = function(txt) {
                        const n = parseFloat(String(txt == null ? '' : txt).replace(/[%,\s]/g, ''));
                        return isFinite(n) ? n : null;
                    };
                    if (key === 'category') return String($tr.attr('data-category') || '').toLowerCase();
                    if (key === 'count') return numOf(cells.eq(1).text());
                    if (key === 'yours') return numOf(cells.eq(2).text());
                    if (key === 'cbid') return numOf(cells.eq(3).text());
                    if (key === 'high') return numOf(cells.eq(4).find('input').val());
                    if (key === 'low') return numOf(cells.eq(5).find('input').val());
                    return null;
                }
                function applyArpSort() {
                    const $body = $('#ads-rule-pmt-body');
                    const rows = $body.children('tr[data-category]').get();
                    const key = arpSort.dir === 0 ? 'category' : arpSort.key;
                    const dir = arpSort.dir === 0 ? 1 : arpSort.dir;
                    const decorated = rows.map(function(tr, i) {
                        return { tr: tr, v: arpSortValue($(tr), key), cat: String($(tr).attr('data-category') || '').toLowerCase(), i: i };
                    });
                    decorated.sort(function(a, b) {
                        const aBlank = a.v === null || a.v === '';
                        const bBlank = b.v === null || b.v === '';
                        if (aBlank !== bBlank) return aBlank ? 1 : -1; // blanks always last, in both directions
                        let c = 0;
                        if (!aBlank) c = typeof a.v === 'string' ? a.v.localeCompare(b.v) : a.v - b.v;
                        if (c !== 0) return c * dir;
                        return a.cat.localeCompare(b.cat); // ties: category A-Z
                    });
                    decorated.forEach(function(d) { $body.append(d.tr); });
                    $('#ads-rule-pmt-body').closest('table').find('th.arp-sortable').each(function() {
                        const on = arpSort.dir !== 0 && $(this).attr('data-sort') === arpSort.key;
                        $(this).find('.arp-sort-ind').text(on ? (arpSort.dir === 1 ? '\u25B2' : '\u25BC') : '');
                    });
                }
                $(document).on('click', '#adsRulePmtModal th.arp-sortable', function() {
                    const key = $(this).attr('data-sort');
                    if (arpSort.key !== key) arpSort = { key: key, dir: 1 };
                    else if (arpSort.dir === 1) arpSort.dir = -1;
                    else arpSort = { key: null, dir: 0 };
                    applyArpSort();
                });

                function renderRows() {
                    const $body = $('#ads-rule-pmt-body').empty();
                    const rows = collectCategories();
                    const sbids = sbidByCategory();
                    const cbids = cbidByCategory();
                    rows.forEach(function(r) {
                        const $tr = $('<tr>').attr('data-category', r.category);
                        $tr.append($('<td>').text(r.category));
                        $tr.append($('<td class="text-center">').text(r.count.toLocaleString()));
                        $tr.append($('<td class="text-center fw-semibold arp-yours">').text('')); // read only; Yours from the benchmarks, else S BID
                        const saved = savedBids[r.category] || {};
                        const hi = saved.highest != null ? saved.highest : '';
                        const lo = saved.lowest != null ? saved.lowest : '';
                        const cb = cbids[r.category];
                        $tr.append($('<td class="text-center fw-semibold arp-cbid">')
                            .text(cb ? cb.median.toFixed(1) + '%' : '')
                            .attr('title', cb
                                ? 'Live C BID of the category: median of ' + cb.n + ' listing' + (cb.n === 1 ? '' : 's') + ' (lowest ' + cb.min.toFixed(1) + '%, highest ' + cb.max.toFixed(1) + '%)'
                                : 'No live C BID for this category'));
                        $tr.append($('<td>').append(
                            $('<input type="number" class="form-control form-control-sm ads-rule-pmt-input ads-rule-pmt-high" min="0" max="100" step="0.01" placeholder="e.g. 9">').val(hi)
                        ));
                        $tr.append($('<td>').append(
                            $('<input type="number" class="form-control form-control-sm ads-rule-pmt-input ads-rule-pmt-low" min="0" max="100" step="0.01" placeholder="e.g. 2">').val(lo)
                        ));
                        paintYoursCell($tr, null, sbids); // S BID until the benchmarks load
                        $body.append($tr);
                    });
                    if (!rows.length) {
                        $body.append('<tr><td colspan="6" class="text-center text-muted">No categories found. Category comes from the {{ $pmtExcelPath }} upload (matched by Item ID).</td></tr>');
                    }
                    applyArpSort();
                    updateSummary();
                    renderRuleCounts();
                    $('#ads-rule-pmt-search').val('');
                    $('#ads-rule-pmt-filter').val('');
                    applyArpFilter();
                }

                function updateSummary() {
                    const $inputs = $('#ads-rule-pmt-body .ads-rule-pmt-input');
                    let filled = 0;
                    let total = 0;
                    $('#ads-rule-pmt-body tr[data-category]').each(function() {
                        total++;
                        const hi = $(this).find('.ads-rule-pmt-high').val();
                        const lo = $(this).find('.ads-rule-pmt-low').val();
                        const bad = hi !== '' && lo !== '' && parseFloat(lo) > parseFloat(hi);
                        $(this).find('.ads-rule-pmt-low').toggleClass('is-invalid', bad);
                        if (hi !== '' || lo !== '') filled++;
                    });
                    $('#ads-rule-pmt-summary').text(filled + ' of ' + total + ' categories have a bid range');
                }

                $('#ads-rule-pmt-btn').on('click', function() {
                    $('#ads-rule-pmt-error').addClass('d-none').text('');
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                    $('#ads-rule-pmt-bench-status').text('');
                    paintMode();
                    loadSavedBids().finally(function() {
                        renderPauseRows();
                        renderRows();
                        fillFromBenchmarks(false); // blank inputs only; saved values are kept
                    });
                });

                /** Search text + rule filter ('' all, 'has' = category has a Highest, 'nomatch' = no Highest, rule 3). */
                function applyArpFilter() {
                    const q = String($('#ads-rule-pmt-search').val() || '').toLowerCase();
                    const f = $('#ads-rule-pmt-filter').val() || '';
                    $('#ads-rule-pmt-body tr[data-category]').each(function() {
                        const hasHigh = $(this).find('.ads-rule-pmt-high').val() !== '';
                        const ok = String($(this).attr('data-category')).toLowerCase().indexOf(q) !== -1
                            && (f === '' || (f === 'has' ? hasHigh : !hasHigh));
                        $(this).toggle(ok);
                    });
                    $('#ads-rule-pmt-rules-body .arp-rule.is-click').each(function() {
                        $(this).toggleClass('is-active', (f === 'has' && $(this).hasClass('is-2')) || (f === 'nomatch' && $(this).hasClass('is-3')));
                    });
                }
                $('#ads-rule-pmt-search').on('input', applyArpFilter);
                // Not on every keystroke in Highest: a row would vanish while it is being typed. Re-checked on change (leaving the field).
                $('#ads-rule-pmt-filter').on('change', applyArpFilter);
                $('#ads-rule-pmt-body').on('change', '.ads-rule-pmt-high', applyArpFilter);
                // Click rule 2 / 3 on the left to filter the categories (click again to clear)
                $('#ads-rule-pmt-rules-body').on('click', '.arp-rule.is-click', function() {
                    const want = $(this).hasClass('is-2') ? 'has' : 'nomatch';
                    $('#ads-rule-pmt-filter').val($('#ads-rule-pmt-filter').val() === want ? '' : want);
                    applyArpFilter();
                });

                $('#ads-rule-pmt-body').on('input', '.ads-rule-pmt-input', function() {
                    markYoursOk($(this).closest('tr'));
                });
                $('#ads-rule-pmt-body').on('input', '.ads-rule-pmt-input', function() {
                    $(this).removeClass('arp-bench arp-default');
                    markBenchMatches($(this).closest('tr')); // green again if the typed value equals the benchmark
                    updateSummary();
                });
                $('#ads-rule-pmt-bench-fill').on('click', function() { fillFromBenchmarks(true); });

                // ---- Pause conditions builder ----
                $('#arp-pause-head').on('click', function(e) {
                    if ($(e.target).closest('.form-check').length) return; // the On switch does not fold the panel
                    $('#arp-pause-body').toggle();
                    $('#arp-pause-chevron').toggleClass('fa-chevron-up fa-chevron-down');
                });
                // Any edit: re-read, repaint the rule counts live (nothing is saved until Save and Apply).
                $('#arp-pause-rows').on('input change', 'select, input', function() { readPauseRows(); paintPauseState(); renderRuleCounts(); });
                $('#arp-pause-enabled').on('change', function() { readPauseRows(); paintPauseState(); renderRuleCounts(); });
                $('#arp-pause-rows').on('change', '.pz-mode', function() {
                    const c = pauseRule.conditions[$(this).closest('tr').data('i')];
                    c.value = c.mode === 'column' ? 'inv' : '';
                    renderPauseRows();
                    renderRuleCounts();
                });
                $('#arp-pause-rows').on('click', '.pz-del', function() {
                    readPauseRows();
                    if (pauseRule.conditions.length > 1) pauseRule.conditions.splice($(this).closest('tr').data('i'), 1);
                    renderPauseRows();
                    renderRuleCounts();
                });
                $('#arp-pause-add').on('click', function() {
                    readPauseRows();
                    pauseRule.conditions.push({ join: 'AND', column: 'dil', op: '<', mode: 'number', value: '' });
                    renderPauseRows();
                });

                $('#ads-rule-pmt-enabled').on('change', function() {
                    const want = !!this.checked;
                    const $box = $(this).prop('disabled', true);
                    const $err = $('#ads-rule-pmt-error').addClass('d-none').text('');
                    fetch(@json($pmtBase.'/campaign-ads/pmt-rule-switch'), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ enabled: want })
                    })
                        .then(function(r) { return r.json().then(function(d) { return { ok: r.ok, d: d }; }); })
                        .then(function(res) {
                            if (!res.ok) throw new Error((res.d && (res.d.error || res.d.message)) || 'Could not change the switch.');
                            ruleOn = !!res.d.enabled;
                            if (typeof showToast === 'function') showToast('success', ruleOn ? 'Ads Rule PMT is On' : 'Ads Rule PMT is Off');
                        })
                        .catch(function(e) { $err.removeClass('d-none').text(e.message || 'Could not change the switch.'); })
                        .finally(function() { $box.prop('disabled', false); paintMode(); refreshPmtColumn(); });
                });

                $('#ads-rule-pmt-apply-all').on('click', function() {
                    const hi = $('#ads-rule-pmt-all-high').val();
                    const lo = $('#ads-rule-pmt-all-low').val();
                    if (hi !== '') $('#ads-rule-pmt-body tr[data-category]:visible .ads-rule-pmt-high').val(hi);
                    if (lo !== '') $('#ads-rule-pmt-body tr[data-category]:visible .ads-rule-pmt-low').val(lo);
                    updateSummary();
                });

                $('#ads-rule-pmt-save').on('click', function() {
                    const bids = {};
                    $('#ads-rule-pmt-body tr[data-category]').each(function() {
                        bids[$(this).attr('data-category')] = {
                            highest: $(this).find('.ads-rule-pmt-high').val(),
                            lowest: $(this).find('.ads-rule-pmt-low').val(),
                            manual: '' // the Manual rate is gone: the old value is cleared on save
                        };
                    });
                    const $btn = $(this).prop('disabled', true);
                    const $err = $('#ads-rule-pmt-error').addClass('d-none').text('');
                    fetch(PMT_BIDS_URL, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ bids: bids, pause_rule: (readPauseRows(), { enabled: pauseRule.enabled, conditions: pauseRule.conditions }) })
                    })
                        .then(function(r) { return r.json().then(function(d) { return { ok: r.ok, d: d }; }); })
                        .then(function(res) {
                            if (!res.ok) {
                                $err.removeClass('d-none').text((res.d && (res.d.error || res.d.message)) || 'Could not save.');
                                return;
                            }
                            savedBids = res.d.bids || {};
                            if (res.d.pause_rule) setPauseRule(res.d.pause_rule);
                            if (typeof res.d.enabled === 'boolean') { ruleOn = res.d.enabled; paintMode(); }
                            if (ruleOn && typeof showToast === 'function') showToast('success', 'Saved. Pushing the new bids to eBay in the background.');
                            refreshPmtColumn();
                            bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                        })
                        .catch(function() { $err.removeClass('d-none').text('Could not save.'); })
                        .finally(function() { $btn.prop('disabled', false); });
                });
            })();

            // ===== Multi Buy rule: discount % for Buy 2 / Buy 3 / Buy 4 =====
            (function() {
                const URL = @json($pmtBase.'/campaign-ads/multi-buy-rule');
                const modalEl = document.getElementById('multiBuyModal');
                if (!modalEl) return;
                const FIELDS = { buy2: '#multi-buy-2', buy3: '#multi-buy-3', buy4: '#multi-buy-4' };
                let saved = { buy2: 1, buy3: 2, buy4: 3 };

                function fill(d) {
                    Object.keys(FIELDS).forEach(function(k) { $(FIELDS[k]).val(d[k]); });
                }
                window.ebayMultiBuyDiscounts = function() { return Object.assign({}, saved); };

                function refreshColumn() {
                    try {
                        const t = window.table;
                        const col = t && t.getColumn ? t.getColumn('multi_buy_rule') : null;
                        if (col) col.getCells().forEach(function(c) { c.reformat(); });
                    } catch (e) { /* table not built yet; the formatter reads `saved` when it renders */ }
                }
                // Load the saved rule on page load so the MULTI BUY column shows it without opening the window.
                fetch(URL, { headers: { 'Accept': 'application/json' } })
                    .then(function(r) { return r.json(); })
                    .then(function(d) { if (d && d.discounts) { saved = d.discounts; refreshColumn(); } })
                    .catch(function() {});

                function paintStatus(d) {
                    const $s = $('#multi-buy-status');
                    const run = d && d.last_run;
                    if (!run) { $s.addClass('d-none').text(''); }
                    else {
                        const cls = run.status === 'ok' ? 'text-success' : (run.status === 'running' ? 'text-primary' : 'text-danger');
                        $s.removeClass('d-none text-success text-primary text-danger').addClass(cls)
                            .text('Last apply (' + (run.at || '') + '): ' + (run.message || run.status));
                    }
                    $('#multi-buy-warning').toggleClass('d-none', !(d && d.volume_pricing_auto));
                }

                $('#multi-buy-btn').on('click', function() {
                    $('#multi-buy-error').addClass('d-none').text('');
                    fill(saved);
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                    fetch(URL, { headers: { 'Accept': 'application/json' } })
                        .then(function(r) { return r.json(); })
                        .then(function(d) { if (d && d.discounts) { saved = d.discounts; fill(saved); } paintStatus(d); })
                        .catch(function() {});
                });

                $('#multi-buy-reset').on('click', function() {
                    fill({ buy2: 1, buy3: 2, buy4: 3 });
                });

                function save(apply) {
                    const discounts = {};
                    Object.keys(FIELDS).forEach(function(k) { discounts[k] = $(FIELDS[k]).val(); });
                    const $btns = $('#multi-buy-save, #multi-buy-save-only').prop('disabled', true);
                    const $err = $('#multi-buy-error').addClass('d-none').text('');
                    fetch(URL, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ discounts: discounts, apply: !!apply })
                    })
                        .then(function(r) { return r.json().then(function(d) { return { ok: r.ok, d: d }; }); })
                        .then(function(res) {
                            if (!res.ok) {
                                $err.removeClass('d-none').text((res.d && (res.d.error || res.d.message)) || 'Could not save.');
                                return;
                            }
                            saved = res.d.discounts || saved;
                            refreshColumn();
                            paintStatus(res.d);
                            if (apply && !res.d.applying) {
                                $err.removeClass('d-none').text('Saved, but the push to eBay could not start. Run: php artisan ebay:multi-buy-apply {{ $pmtAccount }}');
                                return;
                            }
                            if (apply) {
                                $('#multi-buy-status').removeClass('d-none text-success text-danger').addClass('text-primary')
                                    .text('Applying to eBay in the background. Reopen this window in a few minutes to see the result.');
                                if (typeof showToast === 'function') showToast('success', 'Saved. Applying Multi Buy to eBay in the background.');
                                return;
                            }
                            bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                            if (typeof showToast === 'function') showToast('success', 'Multi Buy discounts saved');
                        })
                        .catch(function() { $err.removeClass('d-none').text('Could not save.'); })
                        .finally(function() { $btns.prop('disabled', false); });
                }
                $('#multi-buy-save').on('click', function() { save(true); });
                $('#multi-buy-save-only').on('click', function() { save(false); });
            })();

            // ===== "+" Category: put a category on the ticked SKUs so PMT BID follows it =====
            (function() {
                const CATEGORY_OPTIONS_URL = @json($pmtBase.'/campaign-ads/category-options');
                const CATEGORY_SAVE_URL = @json($pmtBase.'/campaign-ads/category-override');
                const CSRF = @json(csrf_token());
                const menuEl = document.getElementById('ebay-cat-add-menu');
                const btnEl = document.getElementById('ebay-cat-add-btn');
                if (!menuEl || !btnEl) return;
                let serverCategories = [];

                function rowSku(d) {
                    return String((d && (d['(Child) sku'] || d.SKU || d.sku)) || '').trim();
                }
                function rowItemId(d) {
                    return String((d && (d.eBay_item_id || d.listing_id)) || '').trim();
                }
                /** Every grid row (children of parents too) as Tabulator row components. */
                function allRowComponents() {
                    const out = [];
                    const t = window.table;
                    if (!t || typeof t.getRows !== 'function') return out;
                    const walk = function(r) {
                        out.push(r);
                        if (typeof r.getTreeChildren === 'function') {
                            (r.getTreeChildren() || []).forEach(walk);
                        }
                    };
                    t.getRows().forEach(walk);
                    return out;
                }
                /** Ticked rows: the page's selectedSkus set, matched to grid rows (parent rows are never ticked). */
                function selectedRows() {
                    const set = (typeof selectedSkus !== 'undefined' && selectedSkus) ? selectedSkus : new Set();
                    if (!set.size) return [];
                    return allRowComponents().filter(function(r) {
                        const d = r.getData() || {};
                        return set.has(rowSku(d)) && !window.ebayPmtIsParent(d);
                    });
                }
                function esc(v) {
                    return String(v).replace(/[&<>"']/g, function(c) {
                        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
                    });
                }
                function allCategories() {
                    const names = {};
                    serverCategories.forEach(function(c) { names[c] = true; });
                    allRowComponents().forEach(function(r) {
                        const c = String((r.getData() || {}).excel_category || '').trim();
                        if (c) names[c] = true;
                    });
                    return Object.keys(names).sort(function(a, b) { return a.localeCompare(b, undefined, { sensitivity: 'base' }); });
                }
                function paintList() {
                    const rows = selectedRows();
                    const q = String($('#ebay-cat-add-search').val() || '').toLowerCase();
                    $('#ebay-cat-add-info').html(rows.length
                        ? esc(rows.length) + ' SKU' + (rows.length === 1 ? '' : 's') + ' ticked. Pick a category:'
                        : '<span class="text-danger">Tick the SKU checkboxes first.</span>');
                    const list = allCategories().filter(function(c) { return !q || c.toLowerCase().indexOf(q) !== -1; });
                    const $l = $('#ebay-cat-add-list').empty();
                    if (!list.length) {
                        $l.append('<div class="small text-muted px-1">No category found.</div>');
                        return;
                    }
                    list.forEach(function(c) {
                        $l.append($('<button type="button" class="dropdown-item rounded py-1 ebay-cat-add-item">')
                            .prop('disabled', !rows.length)
                            .attr('data-category', c)
                            .text(c));
                    });
                    if (rows.length) {
                        $l.append('<div class="dropdown-divider"></div>');
                        $l.append('<button type="button" class="dropdown-item rounded py-1 text-danger ebay-cat-add-item" data-category="">Clear category</button>');
                    }
                }
                function loadCategories() {
                    return fetch(CATEGORY_OPTIONS_URL, { headers: { 'Accept': 'application/json' } })
                        .then(function(r) { return r.json(); })
                        .then(function(d) { serverCategories = (d && d.categories) || []; })
                        .catch(function() { /* the grid's own categories still list */ });
                }
                function notify(type, msg) {
                    if (typeof showToast === 'function') showToast(type, msg);
                    else alert(msg);
                }

                function apply(category) {
                    const rows = selectedRows();
                    if (!rows.length) { notify('error', 'Tick the SKU checkboxes first.'); return; }
                    const withId = rows.filter(function(r) { return rowItemId(r.getData()) !== ''; });
                    const noId = rows.length - withId.length;
                    if (!withId.length) { notify('error', 'The ticked SKUs have no eBay item id, so no category can be set.'); return; }

                    const changing = withId.filter(function(r) {
                        const cur = String((r.getData() || {}).excel_category || '').trim();
                        return cur !== '' && cur !== category;
                    }).length;
                    if (changing > 0 && !confirm(changing + ' ticked SKU(s) already have another category. '
                        + (category ? 'Change them to "' + category + '"?' : 'Clear the category?'))) {
                        return;
                    }

                    const itemIds = {};
                    withId.forEach(function(r) { itemIds[rowItemId(r.getData())] = true; });
                    $('.ebay-cat-add-item').prop('disabled', true);
                    fetch(CATEGORY_SAVE_URL, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                        body: JSON.stringify({ item_ids: Object.keys(itemIds), category: category })
                    })
                        .then(function(r) { return r.json().then(function(d) { return { ok: r.ok, d: d }; }); })
                        .then(function(res) {
                            if (!res.ok) {
                                notify('error', (res.d && (res.d.error || res.d.message)) || 'Could not save the category.');
                                return;
                            }
                            // Same item id (variations of one listing) all take the category; eBay 2 decides per PARENT, so a blank parent takes it too.
                            const picked = itemIds;
                            allRowComponents().forEach(function(r) {
                                const d = r.getData() || {};
                                if (picked[rowItemId(d)]) {
                                    r.update({ excel_category: category });
                                }
                            });
                            if (PMT_BY_PARENT) {
                                withId.forEach(function(r) {
                                    const parent = typeof r.getTreeParent === 'function' ? r.getTreeParent() : null;
                                    if (parent && parent.getData && !String((parent.getData() || {}).excel_category || '').trim()) {
                                        parent.update({ excel_category: category });
                                    }
                                });
                            }
                            if (typeof window.ebayPmtRefresh === 'function') window.ebayPmtRefresh();
                            bootstrap.Dropdown.getOrCreateInstance(btnEl).hide();
                            notify('success', (category ? '"' + category + '" set on ' : 'Category cleared on ') + withId.length + ' SKU(s)'
                                + (noId ? ' (' + noId + ' skipped: no item id)' : '') + '. PMT BID updated.');
                        })
                        .catch(function() { notify('error', 'Could not save the category.'); })
                        .finally(function() { $('.ebay-cat-add-item').prop('disabled', false); });
                }

                btnEl.addEventListener('show.bs.dropdown', function() {
                    $('#ebay-cat-add-search').val('');
                    paintList();
                    loadCategories().then(paintList);
                });
                $('#ebay-cat-add-search').on('input', paintList);
                $(document).on('click', '.ebay-cat-add-item', function() {
                    apply(String($(this).attr('data-category') || ''));
                });
            })();
@elseif ($pmtPart === 'columns')
                    {
                        title: "PMT BID",
                        field: "pmt_rule_bid",
                        hozAlign: "center",
                        width: 90,
                        headerTooltip: "Suggested PMT bid from Ads Rule PMT: INV 0 = 0 and ads paused; else the category Highest; no match = the old rule S BID (eBay 1).{{ $pmtByParent ? ' Decided per PARENT (family INV, OV L30 and eBay sold) and shown on the PARENT row; every ad listing of that parent gets this bid.' : '' }} Display only.",
                        sorter: function(a, b, aRow, bRow) {
                            const f = function(row) {
                                const r = typeof window.ebayPmtRuleBid === 'function' ? window.ebayPmtRuleBid(row.getData()) : null;
                                return r && r.bid !== null ? r.bid : -1;
                            };
                            return f(aRow) - f(bRow);
                        },
                        formatter: function(cell) {
                            const r = typeof window.ebayPmtRuleBid === 'function' ? window.ebayPmtRuleBid(cell.getRow().getData()) : null;
                            if (!r) return '';
                            const pmtRow = cell.getRow().getData();
                            const tip = typeof window.ebayBidTipAttrs === 'function'
                                ? window.ebayBidTipAttrs({
                                    src: 'Ads Rule PMT: the category rules saved in the Ads Rule PMT window (Highest, else the old rule)',
                                    data: r.data || [],
                                    steps: r.steps || [],
                                    result: r.pause ? 'No bid (INV 0)' : (r.bid !== null ? 'PMT bid ' + Number(r.bid).toFixed(1) + '%' : 'No bid'),
                                    pushed: typeof window.ebayBidPushedText === 'function' ? window.ebayBidPushedText(pmtRow) : ''
                                })
                                : 'title="' + String(r.title || '').replace(/"/g, '&quot;') + '"';
                            if (r.pause) {
                                return '<span ' + tip + ' style="color:#842029; font-weight:700; cursor:help;">0 · PAUSE</span>';
                            }
                            if (r.bid === null) {
                                return '<span ' + tip + ' class="text-muted" style="cursor:help;">—</span>';
                            }
                            return '<span ' + tip + ' style="color:#0d6efd; font-weight:700; cursor:help;">' + Number(r.bid).toFixed(1) + '%</span>';
                        }
                    },
@if (! $pmtAdsPage)
                    {
                        title: "MULTI BUY",
                        field: "multi_buy_rule",
                        hozAlign: "center",
                        width: 150,
                        headerSort: false,
                        headerTooltip: "Multi Buy rule: discount % for Buy 2 / Buy 3 / Buy 4 (set in the Multi Buy window). Save and Apply puts it on the listing as volume pricing.",
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            if (typeof window.ebayPmtIsParent === 'function' && window.ebayPmtIsParent(row)) return '';
                            if (!String(row.eBay_item_id || row.ebay_item_id || row.item_id || '').trim()) {
                                return '<span class="text-muted" title="No eBay listing id">—</span>';
                            }
                            const d = typeof window.ebayMultiBuyDiscounts === 'function' ? window.ebayMultiBuyDiscounts() : null;
                            if (!d) return '';
                            const fmt = function(v) { const n = parseFloat(v); return isFinite(n) ? (Math.round(n * 100) / 100) + '%' : '—'; };
                            return '<span style="color:#047857; font-weight:600; white-space:nowrap;" title="Buy 2 ' + fmt(d.buy2) + ', Buy 3 ' + fmt(d.buy3) + ', Buy 4 ' + fmt(d.buy4) + '">'
                                + '2: ' + fmt(d.buy2) + ' · 3: ' + fmt(d.buy3) + ' · 4: ' + fmt(d.buy4) + '</span>';
                        }
                    },
@endif
@endif
