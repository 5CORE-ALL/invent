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
    $pmtDilNote = $pmtAccount === 'ebay1' ? ' Dil vs SBid is not pushed for eBay 1 while this is on.' : '';
    // eBay 2: the rule is decided per PARENT (family INV / OV L30 / eBay sold), not per child SKU.
    $pmtByParent = $pmtAccount === 'ebay2';
    $pmtUnit = $pmtByParent ? 'Parent' : 'SKU';
@endphp
@if ($pmtPart === 'css')
        /* Ads Rule PMT modal (next-gen look) */
        #adsRulePmtModal .modal-dialog { max-width: min(1200px, 96vw); }
        .arp-modal { height: 94vh; border: 0; border-radius: 16px; overflow: hidden; box-shadow: 0 24px 60px rgba(15, 23, 42, .28); }
        .arp-header { display: flex; align-items: center; justify-content: space-between; padding: 14px 22px; color: #fff;
            background: linear-gradient(120deg, #1e3a8a 0%, #4f46e5 55%, #7c3aed 100%); }
        .arp-header .btn-close { filter: invert(1) grayscale(1) brightness(2); opacity: .85; }
        .arp-title { display: flex; align-items: center; gap: 12px; }
        .arp-title h5 { font-weight: 700; letter-spacing: .2px; }
        .arp-title-icon { width: 38px; height: 38px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center;
            background: rgba(255, 255, 255, .18); font-size: 16px; }
        .arp-subtitle { font-size: 12px; opacity: .85; }
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
        .arp-rule.is-2 .arp-rule-num { background: #0891b2; }
        .arp-rule.is-3 .arp-rule-num { background: #16a34a; }
        .arp-rule.is-4 .arp-rule-num { background: #d97706; }
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
        .arp-table tbody tr:hover { background: #f8f9ff; }
        .arp-table td { font-size: 13px; padding-top: 3px; padding-bottom: 3px; }
        .arp-table .form-control { border-radius: 8px; height: 28px; }
        .arp-table input.arp-bench { background: #dcfce7; border-color: #86efac; }
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
                                        <th>Category</th>
                                        <th class="text-center" style="width: 80px;">{{ $pmtUnit }}s</th>
                                        <th class="text-center" style="width: 110px;" title="Your own Promoted listings ad rate (Yours row, Impressions in eBay search) from the benchmarks. Read only.">Promoted listings ad rate</th>
                                        <th class="text-center" style="width: 110px;" title="Type your own Promoted ad rate here. Starts blank; not saved and not used by the rule.">Promoted ad rate Manual</th>
                                        <th style="width: 140px;">Highest (%)</th>
                                        <th style="width: 140px;">Lowest (%)</th>
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
@elseif ($pmtPart === 'script')
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

                // ---- Rules (first match wins; a higher Dil rule overrides a lower one) ----
                //  1. INV = 0                         -> bid 0, ads paused
                //  2. eBay sold (L30) = 0             -> Highest + 1
                //  3. Dil > 25%  and eBay sold > 0    -> Lowest
                //  4. Dil > 50%  and eBay sold > 0    -> Lowest - 1
                //  5. Dil > 100% and eBay sold > 0    -> 2
                //  (sold > 0 and Dil <= 25%)          -> Highest (no rule; base bid)
                const RULE_DEFS = [
                    { key: '1', label: 'INV = 0', bid: '0 &mdash; ads paused' },
                    { key: '2', label: 'eBay sold (L30) = 0', bid: 'Highest + 1' },
                    { key: '3', label: 'Dil &gt; 25% and eBay sold &gt; 0', bid: 'Lowest' },
                    { key: '4', label: 'Dil &gt; 50% and eBay sold &gt; 0', bid: 'Lowest &minus; 1' },
                    { key: '5', label: 'Dil &gt; 100% and eBay sold &gt; 0', bid: '2' },
                    { key: 'base', label: 'eBay sold &gt; 0 and Dil &le; 25% (no rule)', bid: 'Highest' }
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
                /** Which rule key ('1'..'5' / 'base') applies to this {{ $pmtUnit }} row. */
                function classifyRow(row) {
                    if (rowInv(row) <= 0) return '1';
                    const sold = parseFloat(row['eBay L30']) || 0;
                    if (sold <= 0) return '2';
                    const dil = rowDil(row);
                    if (dil > 100) return '5';
                    if (dil > 50) return '4';
                    if (dil > 25) return '3';
                    return 'base';
                }
                function num(v) {
                    const n = parseFloat(v);
                    return isFinite(n) ? n : null;
                }
                /**
                 * Suggested PMT bid for a row: { rule, bid, pause, missing, title }
                 * (null for rows the rule does not apply to: child SKUs on eBay 2, PARENT rows elsewhere).
                 * bid is null when the rule needs a category Highest / Lowest that is not set.
                 */
                window.ebayPmtRuleBid = function(row) {
                    if (!row || !window.ebayPmtIsRuled(row)) return null;
                    const rule = classifyRow(row);
                    const cat = String(row.excel_category || '').trim();
                    const saved = savedBids[cat] || {};
                    const hi = num(saved.highest);
                    const lo = num(saved.lowest);
                    const out = { rule: rule, bid: null, pause: false, missing: false, title: '' };
                    if (rule === '1') {
                        out.bid = 0; out.pause = true; out.title = 'Rule 1: INV = 0 — bid 0, ads paused';
                        return out;
                    }
                    if (rule === '5') {
                        out.bid = 2; out.title = 'Rule 5: Dil > 100% and eBay sold > 0 — bid 2';
                        return out;
                    }
                    let need = null;
                    let calc = null;
                    if (rule === '2') { need = 'Highest'; if (hi !== null) calc = hi + 1; out.title = 'Rule 2: eBay sold = 0 — Highest + 1'; }
                    else if (rule === '3') { need = 'Lowest'; if (lo !== null) calc = lo; out.title = 'Rule 3: Dil > 25% and eBay sold > 0 — Lowest'; }
                    else if (rule === '4') { need = 'Lowest'; if (lo !== null) calc = Math.max(0, lo - 1); out.title = 'Rule 4: Dil > 50% and eBay sold > 0 — Lowest − 1'; }
                    else { need = 'Highest'; if (hi !== null) calc = hi; out.title = 'Dil ≤ 25% and eBay sold > 0 — Highest'; }
                    if (calc === null) {
                        out.missing = true;
                        out.title += ' (' + (cat ? 'set ' + need + ' for "' + cat + '"' : 'no category for this {{ strtolower($pmtUnit) }}') + ')';
                        return out;
                    }
                    out.bid = Math.min(100, Math.max(2, Math.round(calc * 100) / 100)); // eBay accepts 2-100
                    return out;
                };

                /** Re-render the PMT BID column (after bids load / save). */
                function refreshPmtColumn() {
                    try {
                        const t = window.table;
                        const col = t && t.getColumn ? t.getColumn('pmt_rule_bid') : null;
                        if (col) col.getCells().forEach(function(c) { c.reformat(); });
                    } catch (e) { /* table not built yet — formatter reads savedBids when it renders */ }
                }

                function loadSavedBids() {
                    return fetch(PMT_BIDS_URL, { headers: { 'Accept': 'application/json' } })
                        .then(function(r) { return r.json(); })
                        .then(function(d) { savedBids = (d && d.bids) || {}; ruleOn = !!(d && d.enabled); paintMode(); })
                        .catch(function() { savedBids = {}; });
                }

                function currentRows() {
                    return (typeof allTableData !== 'undefined' && Array.isArray(allTableData) && allTableData.length)
                        ? allTableData
                        : (window.table ? window.table.getData('all') : []);
                }

                function renderRuleCounts() {
                    const counts = { '1': 0, '2': 0, '3': 0, '4': 0, '5': 0, 'base': 0 };
                    let total = 0;
                    currentRows().forEach(function(row) {
                        if (!window.ebayPmtIsRuled(row)) return;
                        counts[classifyRow(row)]++;
                        total++;
                    });
                    const $b = $('#ads-rule-pmt-rules-body').empty();
                    RULE_DEFS.forEach(function(r) {
                        $b.append(
                            $('<div class="arp-rule">').addClass('is-' + r.key)
                                .append($('<span class="arp-rule-num">').text(r.key === 'base' ? '–' : r.key))
                                .append($('<div>')
                                    .append($('<div class="arp-rule-cond">').html(r.label))
                                    .append($('<span class="arp-rule-bid">').html(r.bid)))
                                .append($('<div class="arp-rule-count">').text(counts[r.key].toLocaleString()).append('<small>{{ $pmtUnit }}s</small>'))
                        );
                    });
                    $b.append(
                        $('<div class="arp-rule-total">')
                            .append('<span>Total {{ $pmtUnit }}s</span>')
                            .append($('<b>').text(total.toLocaleString()))
                    );
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
                        $('#ads-rule-pmt-body tr[data-category]' + (overwrite ? ':visible' : '')).each(function() {
                            const hit = benchFor(b.map, $(this).attr('data-category'));
                            if (!hit || (hit.highest == null && hit.lowest == null)) { missing++; return; }
                            let touched = false;
                            [['.ads-rule-pmt-high', hit.highest], ['.ads-rule-pmt-low', hit.lowest]].forEach(function(p) {
                                const $in = $(this).find(p[0]);
                                if (p[1] == null) return;
                                if (overwrite || $in.val() === '') {
                                    $in.val(p[1]).addClass('arp-bench');
                                    touched = true;
                                }
                            }.bind(this));
                            if (touched) cats++;
                        });
                        benchMapLoaded = b.map;
                        $('#ads-rule-pmt-body tr[data-category]').each(function() {
                            const hit = benchFor(b.map, $(this).attr('data-category'));
                            $(this).find('.arp-yours').text(hit && hit.yours ? hit.yours : '');
                        });
                        markBenchMatches(); // also saved values that equal the benchmark
                        updateSummary();
                        if (b.failed) $status.text('Could not load {{ $pmtBenchPage }} data.');
                        else if (!b.column) $status.text('No "Promoted Listings ads rate" column found in {{ $pmtBenchPage }} (upload the {{ $pmtLabel }} LQS report in {{ $pmtExcelPath }}).');
                        else $status.text('Benchmarks (' + b.column + ', Impressions in eBay search): filled ' + cats + ' categor' + (cats === 1 ? 'y' : 'ies')
                            + (missing ? ', ' + missing + ' without a benchmark' : '') + '. Light green = value matches the benchmark (filled values are not saved until you press Save).');
                    });
                }

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

                function renderRows() {
                    const $body = $('#ads-rule-pmt-body').empty();
                    const rows = collectCategories();
                    rows.forEach(function(r) {
                        const $tr = $('<tr>').attr('data-category', r.category);
                        $tr.append($('<td>').text(r.category));
                        $tr.append($('<td class="text-center">').text(r.count.toLocaleString()));
                        $tr.append($('<td class="text-center fw-semibold arp-yours">').text('')); // read only; filled from the benchmarks
                        const saved = savedBids[r.category] || {};
                        const hi = saved.highest != null ? saved.highest : '';
                        const lo = saved.lowest != null ? saved.lowest : '';
                        $tr.append($('<td>').append(
                            $('<input type="number" class="form-control form-control-sm ads-rule-pmt-manual" min="0" max="100" step="0.01" placeholder="">')
                        ));
                        $tr.append($('<td>').append(
                            $('<input type="number" class="form-control form-control-sm ads-rule-pmt-input ads-rule-pmt-high" min="0" max="100" step="0.01" placeholder="e.g. 9">').val(hi)
                        ));
                        $tr.append($('<td>').append(
                            $('<input type="number" class="form-control form-control-sm ads-rule-pmt-input ads-rule-pmt-low" min="0" max="100" step="0.01" placeholder="e.g. 2">').val(lo)
                        ));
                        $body.append($tr);
                    });
                    if (!rows.length) {
                        $body.append('<tr><td colspan="6" class="text-center text-muted">No categories found. Category comes from the {{ $pmtExcelPath }} upload (matched by Item ID).</td></tr>');
                    }
                    updateSummary();
                    renderRuleCounts();
                    $('#ads-rule-pmt-search').val('');
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
                        renderRows();
                        fillFromBenchmarks(false); // blank inputs only; saved values are kept
                    });
                });

                $('#ads-rule-pmt-search').on('input', function() {
                    const q = String($(this).val() || '').toLowerCase();
                    $('#ads-rule-pmt-body tr[data-category]').each(function() {
                        $(this).toggle(String($(this).attr('data-category')).toLowerCase().indexOf(q) !== -1);
                    });
                });

                $('#ads-rule-pmt-body').on('input', '.ads-rule-pmt-input', function() {
                    $(this).removeClass('arp-bench');
                    markBenchMatches($(this).closest('tr')); // green again if the typed value equals the benchmark
                    updateSummary();
                });
                $('#ads-rule-pmt-bench-fill').on('click', function() { fillFromBenchmarks(true); });

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
                        .finally(function() { $box.prop('disabled', false); paintMode(); });
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
                            lowest: $(this).find('.ads-rule-pmt-low').val()
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
                        body: JSON.stringify({ bids: bids })
                    })
                        .then(function(r) { return r.json().then(function(d) { return { ok: r.ok, d: d }; }); })
                        .then(function(res) {
                            if (!res.ok) {
                                $err.removeClass('d-none').text((res.d && (res.d.error || res.d.message)) || 'Could not save.');
                                return;
                            }
                            savedBids = res.d.bids || {};
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
                        headerTooltip: "Suggested PMT bid from Ads Rule PMT: INV 0 = 0 and ads paused; eBay sold 0 = Highest + 1; Dil > 25% = Lowest; Dil > 50% = Lowest − 1; Dil > 100% = 2. Uses the category Highest / Lowest.{{ $pmtByParent ? ' Decided per PARENT (family INV, OV L30 and eBay sold) and shown on the PARENT row; every ad listing of that parent gets this bid.' : '' }} Display only.",
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
                            const tip = String(r.title || '').replace(/"/g, '&quot;');
                            if (r.pause) {
                                return '<span title="' + tip + '" style="color:#842029; font-weight:700;">0 · PAUSE</span>';
                            }
                            if (r.bid === null) {
                                return '<span title="' + tip + '" class="text-muted" style="cursor:help;">—</span>';
                            }
                            return '<span title="' + tip + '" style="color:#0d6efd; font-weight:700;">' + Number(r.bid).toFixed(1) + '%</span>';
                        }
                    },
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
