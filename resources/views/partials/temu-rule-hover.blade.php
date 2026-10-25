{{--
    Hover explainer for the Temu pages (same card as /ebay-tabulator-view): hovering a S Promo Prc / S PRC or a Temu Price cell
    lists every rule and exception behind that number. Applied rules are green, rules that did not apply are grey.

    Script-only partial. Include it inside the page's <script>, after partials.ebay-sprc-dil (it reads its helpers).
    Kind 'nto' is for New Temu One / New Temu Two, 'temu3' is for Temu 3, 'amazon' is for /amazon-tabulator-view,
    'shopifyb2c' is for /shopify-b2c-pricing (pass 'temuRuleLiveLabel' => 'Price').
    Then add to the two columns:
        cellMouseEnter: function(e, cell) { temuRuleTipShow('sprice' | 'price', cell); },
        cellMouseLeave: function() { temuRuleTipScheduleHide(); },
--}}
@php
    $temuRuleHoverKind = $temuRuleHoverKind ?? 'nto';
    // Name of the live listing price in the Diff step ("Temu Price", or "Price" on Shopify B2C).
    $temuRuleLiveLabel = $temuRuleLiveLabel ?? 'Temu Price';
@endphp
        // ---- Temu rule hover card ----
        let temuRuleTipEl = null;
        let temuRuleTipShowTimer = null;
        let temuRuleTipHideTimer = null;
    
        function temuRuleEsc(s) {
            return String(s == null ? '' : s).replace(/[&<>"]/g, function(c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
            });
        }
        function temuRuleMoney(n) {
            n = Number(n);
            return (isFinite(n) && n > 0) ? '$' + n.toFixed(2) : '—';
        }
        function temuRuleStep(state, title, detail) {
            return { state: state, title: title, detail: detail };
        }
        function temuRulePctText(p) {
            return (1 - p / 100).toFixed(3).replace(/0+$/, '').replace(/\.$/, '');
        }

        /** The Diff rule setting saved in the Sprc Dil modal (default: above 20% adds +10). */
        function temuRuleDiffCfg() {
            const c = (typeof window.ebayDiffAdjCfg === 'function') ? window.ebayDiffAdjCfg() : null;
            return { gt_pct: c && isFinite(Number(c.gt_pct)) ? Number(c.gt_pct) : 20, points: c && isFinite(Number(c.points)) ? Number(c.points) : 10 };
        }
        function temuRuleDiffPts(p) { return (p > 0 ? '+' : '') + p; }

        /** Steps 1–4: Dil slab, CVR overlay, Diff rule and the final Target SNROI. Returns the target, or null when none. */
        function temuRuleDilTargetSteps(d, steps) {
            if (typeof ebayDgIsChild !== 'function' || !ebayDgIsChild(d) || (!(ebayDgInv(d) > 0) && !(typeof ebayDgIsMercari === 'function' && ebayDgIsMercari()))) {
                steps.push(temuRuleStep('skip', 'Sprc Dil', 'Needs a child SKU with INV &gt; 0 and an LP. This SKU has no Dil target.'));
                return null;
            }
            const dil = ebayDgDil(d);
            const exact = ebayDilGroiMatch(dil);
            const rule = exact || ebayDilGroiResolve(dil);
            if (!rule) {
                steps.push(temuRuleStep('skip', 'Sprc Dil', 'No Dil slab matches this SKU, so there is no Target SNROI.'));
                return null;
            }
            steps.push(temuRuleStep('info', '1 · Dil slab',
                'Dil <b>' + Number(dil || 0).toFixed(1) + '%</b> → slab <b>' + temuRuleEsc(rule.label) + '</b>'
                + (!exact ? ' (outside the table, so the nearest slab)' : '') + ' → Target SNROI <b>' + rule.groi + '%</b>'));
            const cvrAdj = ebayDilGroiCvrAdj(d);
            const cvrTxt = Number(ebayDgCvr30(d)).toFixed(1) + '%';
            const trend = ebayDgCvrTrend(d);
            if (cvrAdj) {
                steps.push(temuRuleStep('ok', '2 · CVR overlay',
                    'CVR ' + cvrTxt + ' with ' + trend + ' arrow → <b>' + (cvrAdj > 0 ? '+' : '') + cvrAdj + '</b> to Target SNROI'));
            } else {
                steps.push(temuRuleStep('skip', '2 · CVR overlay',
                    'CVR ' + cvrTxt + (trend ? ' (' + trend + ' arrow)' : '') + ' is inside the thresholds, so no adjustment'));
            }
            const diffAdj = ebayDilGroiDiffAdj(d);
            const std = (typeof window.ebayDiffStdPrice === 'function') ? (Number(window.ebayDiffStdPrice(d)) || 0) : 0;
            const price = (typeof window.ebayDiffLivePrice === 'function') ? (Number(window.ebayDiffLivePrice(d)) || 0) : 0;
            const diffPct = (std > 0 && price > 0) ? ((std - price) / price) * 100 : null;
            if (diffAdj) {
                steps.push(temuRuleStep('ok', '3 · Diff rule',
                    'Diff ' + diffPct.toFixed(1) + '% (Std Prc ' + temuRuleMoney(std) + ' vs {{ $temuRuleLiveLabel }} ' + temuRuleMoney(price) + ') is above ' + temuRuleDiffCfg().gt_pct + '% → <b>'
                    + temuRuleDiffPts(diffAdj) + '</b> to Target SNROI'));
            } else {
                steps.push(temuRuleStep('skip', '3 · Diff rule',
                    diffPct === null ? 'Needs both Std Prc and {{ $temuRuleLiveLabel }}. Not applied.'
                        : ('Diff ' + diffPct.toFixed(1) + '% is not above ' + temuRuleDiffCfg().gt_pct + '%. No ' + temuRuleDiffPts(temuRuleDiffCfg().points))));
            }
            const target = Number(ebayDilGroiApplyCvrAdj(rule.groi, d));
            steps.push(temuRuleStep('info', '4 · Target SNROI', 'Final target <b>' + (isFinite(target) ? target : '—') + '%</b>'));
            return isFinite(target) ? target : null;
        }

        /** The LMP rule step. o = { n, base, lmp, sgroiAtLmp, pct, after }. after is null when the rule did not apply. */
        function temuRuleLmpStep(steps, o) {
            const t = o.n + ' · LMP rule';
            if (!(o.lmp > 0)) {
                steps.push(temuRuleStep('skip', t, 'No LMP available, so the LMP rule does not apply.'));
            } else if (!(o.base > 0) || o.base + 0.0001 < o.lmp) {
                steps.push(temuRuleStep('skip', t, 'Price ' + temuRuleMoney(o.base) + ' is below LMP ' + temuRuleMoney(o.lmp) + '. The price is kept.'));
            } else if (o.after == null) {
                steps.push(temuRuleStep('skip', t,
                    'Price is at or above LMP ' + temuRuleMoney(o.lmp) + ', but SGROI at LMP is '
                    + (o.sgroiAtLmp != null ? o.sgroiAtLmp.toFixed(1) + '%' : 'n/a') + ' (below 20%). The rule is <b>not</b> applied.'));
            } else {
                steps.push(temuRuleStep('ok', t,
                    'Price is at or above LMP ' + temuRuleMoney(o.lmp) + ' and SGROI at LMP is '
                    + (o.sgroiAtLmp != null ? o.sgroiAtLmp.toFixed(1) + '%' : 'n/a') + ' (20% or more) → LMP − ' + o.pct
                    + '% (' + (o.src || 'Dil band') + ') = LMP × ' + temuRulePctText(o.pct) + ' = <b>' + temuRuleMoney(o.after) + '</b>'));
            }
        }

        function temuRuleBandPct(d) {
            const n = (typeof window.ebayLmpReducePctForRow === 'function') ? Number(window.ebayLmpReducePctForRow(d)) : NaN;
            return isFinite(n) && n >= 0 ? n : 2;
        }

        /** Where the listing Price stands vs the price we push, Std Prc and LMP. o = { price, pushLabel, push, live, std, lmp, inv }. */
        function temuRuleExplainPrice(d, o) {
            const steps = [];
            const notes = [];
            const price = Number(o.price) || 0;
            steps.push(temuRuleStep('info', '1 · Source',
                'Temu Price = <b>Base × 1.1364</b> (+$2.99 when that is $26.99 or less), from the base price last pulled from Temu. It changes only when a new price is pushed.'));
            if (o.push > 0) {
                if (o.live > 0 && Math.abs(o.push - o.live) <= 0.009) {
                    steps.push(temuRuleStep('ok', '2 · ' + o.pushLabel, o.pushLabel + ' ' + temuRuleMoney(o.push) + ' is already live on Temu.'));
                } else {
                    steps.push(temuRuleStep('warn', '2 · ' + o.pushLabel,
                        o.pushLabel + ' ' + temuRuleMoney(o.push) + ' is not live yet (Temu holds ' + temuRuleMoney(o.live) + '). It goes live when it is pushed.'));
                }
            } else {
                steps.push(temuRuleStep('skip', '2 · ' + o.pushLabel, 'No ' + o.pushLabel + ' yet, so there is nothing waiting to be pushed.'));
            }
            const diff = (o.std > 0 && price > 0) ? ((o.std - price) / price) * 100 : null;
            if (diff !== null) {
                steps.push(temuRuleStep(diff > temuRuleDiffCfg().gt_pct || diff < -5 ? 'warn' : 'ok', '3 · vs Std Prc',
                    'Std Prc ' + temuRuleMoney(o.std) + ' vs Temu Price → Diff <b>' + diff.toFixed(1) + '%</b>'
                    + (diff > temuRuleDiffCfg().gt_pct ? ' (above ' + temuRuleDiffCfg().gt_pct + '%: Sprc Dil adds ' + temuRuleDiffPts(temuRuleDiffCfg().points) + ' to the Target SNROI)' : (diff < -5 ? ' (Price is above Std Prc)' : ' (normal)')) + '.'));
            } else {
                steps.push(temuRuleStep('skip', '3 · vs Std Prc', 'Needs both Std Prc and Temu Price.'));
            }
            if (o.lmp > 0 && price > 0) {
                const over = o.inv > 0 && price > o.lmp;
                steps.push(temuRuleStep(over ? 'warn' : 'ok', '4 · vs LMP',
                    'LMP ' + temuRuleMoney(o.lmp) + ' · Price is ' + (price > o.lmp ? 'above' : (price < o.lmp ? 'below' : 'equal to')) + ' LMP'
                    + (over ? ' (red triangle).' : '.')));
                if (price < o.lmp * 0.8) notes.push('Price is below 80% of LMP (purple triangle).');
            } else {
                steps.push(temuRuleStep('skip', '4 · vs LMP', 'No LMP available.'));
            }
            if (price <= 0) notes.push('Temu Price is $0. The base price is missing.');
            return {
                title: 'Temu Price ' + temuRuleMoney(price),
                sub: (d['(Child) sku'] || d.sku || d.SKU || '') + ' · where this price stands',
                steps: steps, notes: notes, finalNote: ''
            };
        }

@if($temuRuleHoverKind === 'nto')
        /** S Promo Prc on New Temu One / Two: every rule and exception behind the number. */
        function temuRuleExplainSprice(d) {
            // A SKU under clearance (Inv days clearance = YES) gets no S PRC from any rule: no Dil, Std, Diff, LMP or Amz rule applies.
            if (typeof window.chStdIsClearance === 'function' && window.chStdIsClearance(d)) {
                return { title: 'S PRC — (Clearance)', sub: (d['(Child) sku'] || d.sku || ''), steps: [temuRuleStep('skip', 'Clearance',
                    'This SKU is on the Inv days clearance list (YES), so S PRC shows <b>C</b>. No rule applies and no S PRC is saved or pushed for it.')], notes: [], finalNote: '' };
            }
            const steps = [];
            const notes = [];
            const dilOn = ntoDilRuleOn();
            const std = ntoRowSpriceStart(d) || 0;
            const s12 = ntoRowSTwelvePct(d);
            const maxPromo = std > 0 ? +(std - s12).toFixed(2) : 0;
            const lp = parseFloat(d.lp) || 0;
            const ship = parseFloat(d.temu_ship) || 0;
            const ads = (typeof temuAdsPercentForNet === 'function') ? Number(temuAdsPercentForNet()) || 0 : 0;
            const final = ntoRowSPriceAfterDisc(d);
            const inv = parseFloat(d.inv != null ? d.inv : d.INV) || 0;

            if (dilOn) {
                const target = temuRuleDilTargetSteps(d, steps);
                if (target != null) {
                    const prc = ntoRowDilTargetPrc(d);
                    steps.push(temuRuleStep('ok', '5 · Price from target',
                        '(LP ' + temuRuleMoney(lp) + ' × (1 + ' + target + '%) + Ship ' + temuRuleMoney(ship) + ' + Std ' + temuRuleMoney(std)
                        + ' × Ads ' + ads.toFixed(1) + '%) ÷ 0.95 = <b>' + temuRuleMoney(prc) + '</b>'));
                    if (prc != null && prc >= maxPromo) {
                        steps.push(temuRuleStep('ok', '6 · Std Prc maximum',
                            'Target price ' + temuRuleMoney(prc) + ' is at or above Std Prc − S 12% $ = ' + temuRuleMoney(maxPromo) + ' → held at <b>'
                            + temuRuleMoney(maxPromo) + '</b> with no discount.'));
                    } else {
                        steps.push(temuRuleStep('skip', '6 · Std Prc maximum', 'Target price is below Std Prc − S 12% $ (' + temuRuleMoney(maxPromo) + '). Not held.'));
                    }
                }
            } else {
                const pm = ntoRowPm(d);
                steps.push(temuRuleStep('info', '1 · Std prc vs dil (switch ON)',
                    'S Disc $ = Std Prc ' + temuRuleMoney(std) + ' × Sum disc ' + Number(pm || 0).toFixed(1) + '% (Age + Dil + B Disc + 0 Sold + CVR + Review discounts) = '
                    + temuRuleMoney(ntoRowSDiscAmt(d)) + '.'));
                const cap = ntoRowSnroiCapPrc(d);
                steps.push(cap != null
                    ? temuRuleStep('ok', '2 · SNROI Cap', 'The Dil slab has a Target SNROI → the price that lands on it, ' + temuRuleMoney(cap) + ', replaces the discount price.')
                    : temuRuleStep('skip', '2 · SNROI Cap', 'The SKU\'s Dil slab has no SNROI cap (or LP is missing). The discount price is kept.'));
            }
            const base = ntoRowSPromoPrcBase(d);
            steps.push(temuRuleStep('info', (dilOn ? '7' : '3') + ' · S Promo Prc before LMP',
                'Std Prc ' + temuRuleMoney(std) + ' − S 12% $ ' + temuRuleMoney(s12) + ' − S Disc $ ' + temuRuleMoney(ntoRowSDiscAmt(d)) + ' = <b>' + temuRuleMoney(base) + '</b>'));

            const lmp = (typeof temuLmpRefPrice === 'function') ? temuLmpRefPrice(d) : 0;
            const alert = ntoRowLmpAlertPrc(d);
            const sgroiAtLmp = (lp > 0 && lmp > 0) ? ((lmp * 0.95 - ship - lp) / lp) * 100 : null;
            temuRuleLmpStep(steps, { n: dilOn ? '8' : '4', base: base, lmp: lmp, sgroiAtLmp: sgroiAtLmp, pct: temuRuleBandPct(d), after: alert });

            const shipDisc = ntoRowSShipDisc(d);
            steps.push(temuRuleStep(shipDisc > 0 ? 'ok' : 'skip', (dilOn ? '9' : '5') + ' · S Ship Disc',
                shipDisc > 0 ? ('S Promo Prc ' + temuRuleMoney(final) + ' is $30 or less → $2.99 shipping discount → S Promo Base Prc <b>'
                    + temuRuleMoney(ntoRowSPromoBasePrc(d)) + '</b> (what Push sends).')
                    : ('S Promo Prc is above $30, so no shipping discount. S Promo Base Prc = ' + temuRuleMoney(ntoRowSPromoBasePrc(d)) + '.')));

            if (lmp > 0 && std > 0 && lmp > std + 0.0001) notes.push('LMP ' + temuRuleMoney(lmp) + ' is above Std Prc ' + temuRuleMoney(std) + '. Review the Std price.');
            if (inv <= 0 && dilOn) notes.push('INV is 0, so Sprc Dil does not price this SKU.');
            return {
                title: 'S Promo Prc ' + temuRuleMoney(final),
                sub: (d['(Child) sku'] || d.sku || d.SKU || '') + ' · ' + (dilOn ? 'Sprc Dil rule' : 'Std prc vs dil rule'),
                steps: steps, notes: notes, finalNote: ''
            };
        }
        window.temuRuleExplainers = {
            sprice: temuRuleExplainSprice,
            price: function(d) {
                const lmp = (typeof temuLmpRefPrice === 'function') ? temuLmpRefPrice(d) : 0;
                return temuRuleExplainPrice(d, {
                    price: parseFloat(d.t_price) || 0,
                    pushLabel: 'S Promo Base Prc',
                    push: ntoRowPushPrice(d),
                    live: (typeof temuServerBasePrc === 'function' ? temuServerBasePrc(d) : 0) || 0,
                    std: ntoRowSpriceStart(d) || 0,
                    lmp: lmp,
                    inv: parseFloat(d.inv != null ? d.inv : d.INV) || 0
                });
            }
        };
@elseif($temuRuleHoverKind === 'amazon')
        /** Steps 1–4 on Amazon: Dil slab, CVR overlay, Diff rule and the final Target NROI. Returns the target, or null. */
        function amzRuleDilTargetSteps(d, steps) {
            if (!amzPefIsChildRow(d) || amzPefInv(d) <= 0) {
                steps.push(temuRuleStep('skip', 'Sprc Dil', 'Needs a child SKU with INV &gt; 0 and an LP. This SKU has no Dil target.'));
                return null;
            }
            const dil = amzPefDil(d);
            const rule = amzDilGroiMatch(dil);
            if (!rule) {
                steps.push(temuRuleStep('skip', 'Sprc Dil', 'No Dil slab matches this SKU, so there is no Target NROI.'));
                return null;
            }
            const slabNroi = (rule.nroi != null) ? rule.nroi : rule.groi;
            steps.push(temuRuleStep('info', '1 · Dil slab',
                'Dil <b>' + Number(dil || 0).toFixed(1) + '%</b> → slab <b>' + temuRuleEsc(rule.label) + '</b> → Target NROI <b>' + slabNroi + '%</b>'));
            const cvrAdj = amzDilGroiCvrAdj(d);
            const cvrTxt = Number(amzPefCvrL30Live(d)).toFixed(1) + '%';
            const trend = amzPefCvrTrend(d);
            if (cvrAdj) {
                steps.push(temuRuleStep('ok', '2 · CVR overlay',
                    'CVR ' + cvrTxt + ' with ' + trend + ' arrow → <b>' + (cvrAdj > 0 ? '+' : '') + cvrAdj + '</b> to Target NROI'));
            } else {
                steps.push(temuRuleStep('skip', '2 · CVR overlay',
                    'CVR ' + cvrTxt + (trend ? ' (' + trend + ' arrow)' : '') + ' is inside the thresholds, so no adjustment'));
            }
            const diffAdj = amzDilGroiDiffAdj(d);
            const diffPct = amzStdPrcDiffPct(d);
            if (diffAdj) {
                steps.push(temuRuleStep('ok', '3 · Diff rule', 'Diff ' + diffPct.toFixed(1) + '% is above ' + temuRuleDiffCfg().gt_pct + '% → <b>' + temuRuleDiffPts(diffAdj) + '</b> to Target NROI'));
            } else {
                steps.push(temuRuleStep('skip', '3 · Diff rule',
                    diffPct === null ? 'Needs both Std Prc and Price. Not applied.' : ('Diff ' + diffPct.toFixed(1) + '% is not above ' + temuRuleDiffCfg().gt_pct + '%. No ' + temuRuleDiffPts(temuRuleDiffCfg().points))));
            }
            const target = Number(amzDilGroiApplyCvrAdj(slabNroi, d));
            steps.push(temuRuleStep('info', '4 · Target NROI', 'Final target <b>' + (isFinite(target) ? target : '—') + '%</b>'));
            return isFinite(target) ? target : null;
        }

        /** S PRC on Amazon: every rule and exception behind the number. */
        function temuRuleExplainSprice(d) {
            const steps = [];
            const notes = [];
            const dilOn = typeof window.spriceActiveRule === 'function' && window.spriceActiveRule() === 'dil';
            const final = amazonShownSprice(d);
            const std = amazonStdPrice(d);
            const lmp = lmpWithShipping(d);
            let base = 0;
            let n = 1;
            if (dilOn) {
                const target = amzRuleDilTargetSteps(d, steps);
                const meta = (target != null) ? amzDilGroiMetaForRow(d) : null;
                if (meta && meta.sprc > 0) {
                    base = meta.sprc;
                    const lp = parseFloat(d.LP_productmaster) || 0;
                    const ship = parseFloat(d.Ship_productmaster) || 0;
                    const ads = (typeof amazonAdsPctAtPrice === 'function') ? Number(amazonAdsPctAtPrice(base)) || 0 : (Number(AMAZON_CHANNEL_ADS_PCT) || 0);
                    steps.push(temuRuleStep('ok', '5 · Price from target',
                        '(LP ' + temuRuleMoney(lp) + ' × (1 + ' + target + '%) + Ship ' + temuRuleMoney(ship) + ') ÷ (0.80 − Ads ' + ads.toFixed(1) + '%) = <b>' + temuRuleMoney(base) + '</b>'));
                }
                n = 6;
            } else {
                base = Number(amzDisplayedSprice(d)) || 0;
                const disc = (typeof computeAmzSumDiscountPct === 'function') ? Number(computeAmzSumDiscountPct(d)) : NaN;
                steps.push(temuRuleStep('info', '1 · Std prc vs dil (switch ON)',
                    'S PRC = Std Prc ' + temuRuleMoney(std) + ' × (1 − Sum disc ' + (isFinite(disc) ? disc.toFixed(1) : '0') + '% (Age + Dil + B Disc + 0 Sold + CVR + Review discounts)) = <b>' + temuRuleMoney(base) + '</b>'));
                if (typeof amzIsClearanceSku === 'function' && amzIsClearanceSku(d)) notes.push('Clearance SKU: priced only from the Clearance SNROI block (or shows "C" with no price).');
                n = 2;
            }
            const shouldCap = base > 0 && amazonShouldCapSpriceToLmp(d, base);
            const sg = (lmp > 0) ? amazonSgroiAtPrice(d, lmp) : null;
            let pct = 2;
            if (dilOn) {
                pct = (typeof window.amzLmpReducePctForRow === 'function') ? Number(window.amzLmpReducePctForRow(d)) : 2;
            } else {
                pct = (typeof window.amzStdLmpReducePct === 'function') ? (Number(window.amzStdLmpReducePct(d)) || 0) : 0;
            }
            temuRuleLmpStep(steps, {
                n: String(n), base: base, lmp: lmp, sgroiAtLmp: sg, pct: pct,
                src: dilOn ? 'Dil band, LMP cap table' : 'LMP Reduce block',
                after: shouldCap ? +(lmp * (1 - Math.min(99, Math.max(0, pct)) / 100)).toFixed(2) : null
            });
            const afterLmp = (base > 0 && typeof amazonCapSpriceToLmp === 'function') ? amazonCapSpriceToLmp(d, base) : base;
            const capped = (std > 0 && afterLmp > 0) && Math.abs(afterLmp - final) < 0.0001 && Math.abs(final - std) < 0.0001;
            if (!(std > 0)) {
                steps.push(temuRuleStep('skip', (n + 1) + ' · Std Prc maximum', 'Std Prc is blank, so there is no maximum.'));
            } else if (capped || (final > 0 && Math.abs(final - std) < 0.0001)) {
                steps.push(temuRuleStep('ok', (n + 1) + ' · Std Prc maximum', 'Price is above Std Prc ' + temuRuleMoney(std) + ' → held at <b>' + temuRuleMoney(std) + '</b>.'));
            } else {
                steps.push(temuRuleStep('skip', (n + 1) + ' · Std Prc maximum', 'Price is within Std Prc ' + temuRuleMoney(std) + '. Not capped.'));
            }
            if (lmp > 0 && std > 0 && lmp > std + 0.0001) notes.push('LMP ' + temuRuleMoney(lmp) + ' is above Std Prc ' + temuRuleMoney(std) + ': every price is capped at Std Prc. Review the Std price.');
            if (amzPefInv(d) <= 0) notes.push('INV is 0, so no S PRC is set for this SKU.');
            return {
                title: 'S PRC ' + temuRuleMoney(final),
                sub: (d['(Child) sku'] || '') + ' · ' + (dilOn ? 'Sprc Dil rule' : 'Std prc vs dil rule'),
                steps: steps, notes: notes, finalNote: ''
            };
        }
        window.temuRuleExplainers = {
            sprice: temuRuleExplainSprice,
            price: function(d) {
                const steps = [];
                const notes = [];
                const price = parseFloat(d.price) || 0;
                const shown = amazonShownSprice(d);
                const std = amazonStdPrice(d);
                const lmp = lmpWithShipping(d);
                const inv = amzPefInv(d);
                steps.push(temuRuleStep('info', '1 · Source', 'Price is the <b>live Amazon listing price</b>. It changes only when a new price is pushed to Amazon.'));
                if (shown > 0) {
                    if (Math.abs(shown - price) <= 0.009) {
                        steps.push(temuRuleStep('ok', '2 · S PRC', 'S PRC ' + temuRuleMoney(shown) + ' is already live. Price matches S PRC.'));
                    } else {
                        const st = d.SPRICE_STATUS ? ' Status: <b>' + temuRuleEsc(d.SPRICE_STATUS) + '</b>.' : '';
                        steps.push(temuRuleStep('warn', '2 · S PRC', 'S PRC ' + temuRuleMoney(shown) + ' is not live yet (Price ' + temuRuleMoney(price) + '). It goes live when it is pushed.' + st));
                    }
                } else {
                    steps.push(temuRuleStep('skip', '2 · S PRC', 'No S PRC yet, so there is nothing waiting to be pushed.'));
                }
                const diff = amzStdPrcDiffPct(d);
                if (diff !== null) {
                    steps.push(temuRuleStep(diff > temuRuleDiffCfg().gt_pct || diff < -5 ? 'warn' : 'ok', '3 · vs Std Prc',
                        'Std Prc ' + temuRuleMoney(std) + ' vs Price → Diff <b>' + diff.toFixed(1) + '%</b>'
                        + (diff > temuRuleDiffCfg().gt_pct ? ' (above ' + temuRuleDiffCfg().gt_pct + '%: Sprc Dil adds ' + temuRuleDiffPts(temuRuleDiffCfg().points) + ' to the Target NROI)' : (diff < -5 ? ' (Price is above Std Prc)' : ' (normal)')) + '.'));
                } else {
                    steps.push(temuRuleStep('skip', '3 · vs Std Prc', 'Needs both Std Prc and Price.'));
                }
                if (lmp > 0 && price > 0) {
                    const over = inv > 0 && price > lmp;
                    steps.push(temuRuleStep(over ? 'warn' : 'ok', '4 · vs LMP',
                        'LMP ' + temuRuleMoney(lmp) + ' · Price is ' + (price > lmp ? 'above' : (price < lmp ? 'below' : 'equal to')) + ' LMP' + (over ? ' (red triangle).' : '.')));
                    if (price < lmp * 0.8) notes.push('Price is below 80% of LMP (purple triangle).');
                } else {
                    steps.push(temuRuleStep('skip', '4 · vs LMP', 'No LMP available.'));
                }
                if (price <= 0) notes.push('Price is $0. The listing price is missing.');
                return {
                    title: 'Price ' + temuRuleMoney(price),
                    sub: (d['(Child) sku'] || '') + ' · where this price stands',
                    steps: steps, notes: notes, finalNote: ''
                };
            }
        };
@elseif($temuRuleHoverKind === 'macys')
        /** S PRC on Macys: every rule and exception behind the number. */
        function temuRuleExplainSprice(d) {
            // A SKU under clearance (Inv days clearance = YES) gets no S PRC from any rule: no Dil, Std, Diff, LMP or Amz rule applies.
            if (typeof window.chStdIsClearance === 'function' && window.chStdIsClearance(d)) {
                return { title: 'S PRC — (Clearance)', sub: (d['(Child) sku'] || d.sku || ''), steps: [temuRuleStep('skip', 'Clearance',
                    'This SKU is on the Inv days clearance list (YES), so S PRC shows <b>C</b>. No rule applies and no S PRC is saved or pushed for it.')], notes: [], finalNote: '' };
            }
            const steps = [];
            const notes = [];
            const dilOn = macysDilOn();
            const final = Number(macysShownSprice(d)) || 0;
            const std = parseFloat(d.STANDARD_PRICE) || 0;
            const lmp = macysRowLmp(d);
            const lp = parseFloat(d.LP_productmaster) || 0;
            const ship = parseFloat(d.Ship_productmaster) || 0;
            const margin = getMacysMargin(d);
            const amz = macysAmzPrice(d);
            const sold = parseFloat(d['MC L30']) || 0;
            const sku = d['(Child) sku'] || '';
            const rowSub = sku + ' · ' + (dilOn ? 'Sprc Dil rule' : 'Std prc vs dil rule');
            let price = 0;
            let n = 1;

            if (dilOn) {
                const meta = (typeof ebayDilGroiMetaForRow === 'function') ? ebayDilGroiMetaForRow(d) : null;
                const dil = (typeof ebayDgDil === 'function') ? Number(ebayDgDil(d)) : 0;
                if (!meta) {
                    steps.push(temuRuleStep('info', '1 · Dil slab', 'Dil ' + dil.toFixed(1) + '% is outside the slabs'
                        + (sold > 0 ? ' and the SKU has sales → Std Prc ' + temuRuleMoney(std) + ' is the rule price.' : '. No Sprc Dil price for this SKU.')));
                    price = (sold > 0 && std > 0) ? std : 0;
                    n = 2;
                } else {
                    steps.push(temuRuleStep('ok', '1 · Dil slab',
                        'Dil ' + dil.toFixed(1) + '% (OV L30 ÷ Shopify INV) → ' + temuRuleEsc(meta.label || '') + ' → Target GROI <b>' + meta.slabGroi + '%</b>'
                        + (sold <= 0 ? ' (MC L30 = 0 uses the minimum Target GROI)' : '')));
                    const diffAdj = Number(meta.diffAdj) || 0;
                    const stdD = parseFloat(d.STANDARD_PRICE) || 0;
                    const mc = parseFloat(d['MC Price']) || 0;
                    const diffPct = (stdD > 0 && mc > 0) ? ((stdD - mc) / mc) * 100 : null;
                    steps.push(diffAdj
                        ? temuRuleStep('ok', '2 · Diff rule', 'Diff ' + diffPct.toFixed(1) + '% (Std Prc ' + temuRuleMoney(stdD) + ' vs MC Price ' + temuRuleMoney(mc) + ') is above '
                            + temuRuleDiffCfg().gt_pct + '% → <b>' + temuRuleDiffPts(diffAdj) + '</b> to Target GROI')
                        : temuRuleStep('skip', '2 · Diff rule', diffPct === null ? 'Needs both Std Prc and MC Price. Not applied.'
                            : ('Diff ' + diffPct.toFixed(1) + '% is not above ' + temuRuleDiffCfg().gt_pct + '%. No ' + temuRuleDiffPts(temuRuleDiffCfg().points))));
                    steps.push(temuRuleStep('info', '3 · Target GROI', 'Final target <b>' + meta.groi + '%</b>'));
                    price = Number(meta.rawSprc > 0 ? meta.rawSprc : meta.sprc) || 0;
                    steps.push(temuRuleStep('ok', '4 · Price from target',
                        '(LP ' + temuRuleMoney(lp) + ' × (1 + ' + meta.groi + '%) + Ship ' + temuRuleMoney(ship) + ') ÷ ' + margin.toFixed(2) + ' = <b>' + temuRuleMoney(price) + '</b>'));
                    n = 5;
                }
                // LMP cap: LMP × (1 − x%) by the SKU's Dil band, only when SGROI at LMP is 20% or more.
                const pct = temuRuleBandPct(d);
                const sg = macysSgroiAtPrice(d, lmp);
                const lmpHit = price > 0 && lmp > 0 && price + 0.0001 >= lmp && (sg === null || sg >= 20);
                const afterLmp = lmpHit ? +(lmp * (1 - Math.min(99, Math.max(0, pct)) / 100)).toFixed(2) : null;
                temuRuleLmpStep(steps, { n: String(n), base: price, lmp: lmp, sgroiAtLmp: sg, pct: pct, src: 'Dil band, LMP cap table', after: afterLmp });
                if (afterLmp != null) price = afterLmp;
                n++;
                if (!(std > 0)) {
                    steps.push(temuRuleStep('skip', n + ' · Std Prc maximum', 'Std Prc is blank, so there is no maximum.'));
                } else if (price > std + 0.0001) {
                    steps.push(temuRuleStep('ok', n + ' · Std Prc maximum', 'Price ' + temuRuleMoney(price) + ' is above Std Prc ' + temuRuleMoney(std) + ' → held at <b>' + temuRuleMoney(std) + '</b>.'));
                    price = std;
                } else {
                    steps.push(temuRuleStep('skip', n + ' · Std Prc maximum', 'Price is within Std Prc ' + temuRuleMoney(std) + '. Not capped.'));
                }
                n++;
            } else {
                const base = (typeof window.chStdPriceForRow === 'function') ? (Number(window.chStdPriceForRow(d)) || 0) : 0;
                steps.push(temuRuleStep('info', '1 · Std prc vs dil (switch ON)',
                    'Std Prc ' + temuRuleMoney(std) + ' less the Age, Dil, B Disc, 0 Sold, CVR, Review and LMP discounts (or the SNROI Cap price when the Dil slab has a Target SNROI) = <b>' + temuRuleMoney(base) + '</b>'));
                steps.push(temuRuleStep('skip', '2 · LMP', 'In Std prc vs dil, LMP is a discount (LMP Disc on the SKU\'s Dil slab, already in the price above). It does not cap S PRC.'));
                price = base;
                n = 3;
            }

            // A Price floor, always last: S PRC is never below the Amazon price.
            if (!(amz > 0)) {
                steps.push(temuRuleStep('skip', n + ' · A Price floor', 'No A Price, so no floor.'));
            } else if (price > 0 && price < amz) {
                steps.push(temuRuleStep('ok', n + ' · A Price floor', 'Price ' + temuRuleMoney(price) + ' is below A Price ' + temuRuleMoney(amz) + ' → S PRC = <b>' + temuRuleMoney(amz) + '</b> (Amz).'));
                price = amz;
            } else {
                steps.push(temuRuleStep('skip', n + ' · A Price floor', 'Price is at or above A Price ' + temuRuleMoney(amz) + '. It stays.'));
            }

            if (lmp > 0 && std > 0 && lmp > std + 0.0001) notes.push('LMP ' + temuRuleMoney(lmp) + ' is above Std Prc ' + temuRuleMoney(std) + '. Review the Std price.');
            if (!(parseFloat(d.INV) > 0)) notes.push('INV is 0, so no S PRC is set for this SKU.');
            if (price > 0 && final > 0 && Math.abs(price - final) > 0.009) {
                notes.push('The S PRC shown (' + temuRuleMoney(final) + ') differs from this calculation (' + temuRuleMoney(price) + '). Reload the page.');
            }
            return { title: 'S PRC ' + temuRuleMoney(final), sub: rowSub, steps: steps, notes: notes, finalNote: '' };
        }
        window.temuRuleExplainers = {
            sprice: temuRuleExplainSprice,
            price: function(d) {
                const steps = [];
                const notes = [];
                const price = parseFloat(d['MC Price']) || 0;
                const shown = Number(macysShownSprice(d)) || 0;
                const std = parseFloat(d.STANDARD_PRICE) || 0;
                const lmp = macysRowLmp(d);
                const amz = macysAmzPrice(d);
                const inv = parseFloat(d.INV) || 0;
                steps.push(temuRuleStep('info', '1 · Source', 'MC Price is the <b>live Macy\'s listing price</b>. It changes only when a new price is pushed to Macy\'s.'));
                if (shown > 0) {
                    steps.push(Math.abs(shown - price) <= 0.009
                        ? temuRuleStep('ok', '2 · S PRC', 'S PRC ' + temuRuleMoney(shown) + ' is already live. MC Price matches S PRC.')
                        : temuRuleStep('warn', '2 · S PRC', 'S PRC ' + temuRuleMoney(shown) + ' is not live yet (MC Price ' + temuRuleMoney(price) + '). It goes live when it is pushed.'));
                } else {
                    steps.push(temuRuleStep('skip', '2 · S PRC', 'No S PRC yet, so there is nothing waiting to be pushed.'));
                }
                const diff = macysStdPrcDiffPct(d);
                if (diff !== null) {
                    steps.push(temuRuleStep(diff > temuRuleDiffCfg().gt_pct || diff < -5 ? 'warn' : 'ok', '3 · vs Std Prc',
                        'Std Prc ' + temuRuleMoney(std) + ' vs MC Price → Diff <b>' + diff.toFixed(1) + '%</b>'
                        + (diff > temuRuleDiffCfg().gt_pct ? ' (above ' + temuRuleDiffCfg().gt_pct + '%: Sprc Dil adds ' + temuRuleDiffPts(temuRuleDiffCfg().points) + ' to the Target GROI)' : (diff < -5 ? ' (MC Price is above Std Prc)' : ' (normal)')) + '.'));
                } else {
                    steps.push(temuRuleStep('skip', '3 · vs Std Prc', 'Needs both Std Prc and MC Price.'));
                }
                if (lmp > 0 && price > 0) {
                    const over = inv > 0 && price > lmp;
                    steps.push(temuRuleStep(over ? 'warn' : 'ok', '4 · vs LMP',
                        'LMP ' + temuRuleMoney(lmp) + ' · MC Price is ' + (price > lmp ? 'above' : (price < lmp ? 'below' : 'equal to')) + ' LMP' + (over ? ' (red triangle).' : '.')));
                    if (price < lmp * 0.8) notes.push('MC Price is below 80% of LMP (purple triangle).');
                } else {
                    steps.push(temuRuleStep('skip', '4 · vs LMP', 'No LMP available.'));
                }
                if (amz > 0 && price > 0) {
                    steps.push(temuRuleStep(price < amz ? 'warn' : 'ok', '5 · vs A Price',
                        'A Price ' + temuRuleMoney(amz) + ' · MC Price is ' + (price > amz ? 'above (green)' : (price < amz ? 'below (red)' : 'equal to')) + ' A Price.'));
                } else {
                    steps.push(temuRuleStep('skip', '5 · vs A Price', 'No A Price available.'));
                }
                if (price <= 0) notes.push('MC Price is $0. The listing price is missing.');
                return { title: 'MC Price ' + temuRuleMoney(price), sub: (d['(Child) sku'] || '') + ' · where this price stands', steps: steps, notes: notes, finalNote: '' };
            }
        };
@elseif($temuRuleHoverKind === 'purchasing_power')
        /** S PRC on Purchasing Power: every rule and exception behind the number. Purchasing Power has no LMP, so there is no LMP step. */
        function temuRuleExplainSprice(d) {
            const steps = [];
            const notes = [];
            const dilOn = ppDilOn();
            const final = Number(ppShownSprice(d)) || 0;
            const std = parseFloat(d.STANDARD_PRICE) || 0;
            const lp = parseFloat(d.LP_productmaster) || 0;
            const ship = parseFloat(d.Ship_productmaster) || 0;
            const margin = ppMarginOf(d);
            const amz = ppAmzPrice(d);
            const sold = parseFloat(d['PP L30']) || 0;
            const sku = d['(Child) sku'] || '';
            const rowSub = sku + ' · ' + (dilOn ? 'Sprc Dil rule' : 'Std prc vs dil rule');
            let price = 0;
            let n = 1;

            if (typeof window.chStdIsClearance === 'function' && window.chStdIsClearance(d)) {
                steps.push(temuRuleStep('skip', 'Clearance', 'This SKU is on the Inv days clearance list (YES), so S PRC shows <b>C</b>. No S PRC is saved or pushed for it.'));
                return { title: 'S PRC — (Clearance)', sub: rowSub, steps: steps, notes: notes, finalNote: '' };
            }

            if (dilOn) {
                const meta = (typeof ebayDilGroiMetaForRow === 'function') ? ebayDilGroiMetaForRow(d) : null;
                const dil = (typeof ebayDgDil === 'function') ? Number(ebayDgDil(d)) : 0;
                if (!meta) {
                    steps.push(temuRuleStep('info', '1 · Dil slab', 'Dil ' + dil.toFixed(1) + '% is outside the slabs'
                        + (sold > 0 ? ' and the SKU has sales → Std Prc ' + temuRuleMoney(std) + ' is the rule price.' : '. No Sprc Dil price for this SKU.')));
                    price = (sold > 0 && std > 0) ? std : 0;
                    n = 2;
                } else {
                    steps.push(temuRuleStep('ok', '1 · Dil slab',
                        'Dil ' + dil.toFixed(1) + '% (OV L30 ÷ Shopify INV) → ' + temuRuleEsc(meta.label || '') + ' → Target SNROI <b>' + meta.slabGroi + '%</b>'
                        + (sold <= 0 ? ' (PP L30 = 0 uses the minimum Target)' : '')));
                    const diffAdj = Number(meta.diffAdj) || 0;
                    const pp = parseFloat(d['PP Price']) || 0;
                    const diffPct = (std > 0 && pp > 0) ? ((std - pp) / pp) * 100 : null;
                    steps.push(diffAdj
                        ? temuRuleStep('ok', '2 · Diff rule', 'Diff ' + diffPct.toFixed(1) + '% (Std Prc ' + temuRuleMoney(std) + ' vs PP Price ' + temuRuleMoney(pp) + ') is above '
                            + temuRuleDiffCfg().gt_pct + '% → <b>' + temuRuleDiffPts(diffAdj) + '</b> to the Target')
                        : temuRuleStep('skip', '2 · Diff rule', diffPct === null ? 'Needs both Std Prc and PP Price. Not applied.'
                            : ('Diff ' + diffPct.toFixed(1) + '% is not above ' + temuRuleDiffCfg().gt_pct + '%. No ' + temuRuleDiffPts(temuRuleDiffCfg().points))));
                    steps.push(temuRuleStep('info', '3 · Target SNROI', 'Final target <b>' + meta.groi + '%</b> (Purchasing Power has no Ads%, so SNROI = SGROI)'));
                    price = Number(meta.rawSprc > 0 ? meta.rawSprc : meta.sprc) || 0;
                    steps.push(temuRuleStep('ok', '4 · Price from target',
                        '(LP ' + temuRuleMoney(lp) + ' × (1 + ' + meta.groi + '%) + Ship BB ' + temuRuleMoney(ship) + ') ÷ ' + margin.toFixed(2) + ' = <b>' + temuRuleMoney(price) + '</b>'));
                    n = 5;
                }
                steps.push(temuRuleStep('skip', n + ' · LMP', 'Purchasing Power has no LMP, so no LMP cap applies.'));
                n++;
                if (!(std > 0)) {
                    steps.push(temuRuleStep('skip', n + ' · Std Prc maximum', 'Std Prc is blank, so there is no maximum.'));
                } else if (price > std + 0.0001) {
                    steps.push(temuRuleStep('ok', n + ' · Std Prc maximum', 'Price ' + temuRuleMoney(price) + ' is above Std Prc ' + temuRuleMoney(std) + ' → held at <b>' + temuRuleMoney(std) + '</b>.'));
                    price = std;
                } else {
                    steps.push(temuRuleStep('skip', n + ' · Std Prc maximum', 'Price is within Std Prc ' + temuRuleMoney(std) + '. Not capped.'));
                }
                n++;
            } else {
                const base = (typeof window.chStdPriceForRow === 'function') ? (Number(window.chStdPriceForRow(d)) || 0) : 0;
                steps.push(temuRuleStep('info', '1 · Std prc vs dil (switch ON)',
                    'Std Prc ' + temuRuleMoney(std) + ' less the Age, Dil, B Disc, 0 Sold, CVR and Review discounts (or the SNROI Cap price when the Dil slab has a Target SNROI) = <b>' + temuRuleMoney(base) + '</b>'));
                steps.push(temuRuleStep('skip', '2 · LMP', 'Purchasing Power has no LMP, so no LMP rule applies.'));
                price = base;
                n = 3;
            }

            // A Price floor, always last: S PRC is never below the Amazon price.
            if (!(amz > 0)) {
                steps.push(temuRuleStep('skip', n + ' · A Price floor', 'No A Price, so no floor.'));
            } else if (price > 0 && price < amz) {
                steps.push(temuRuleStep('ok', n + ' · A Price floor', 'Price ' + temuRuleMoney(price) + ' is below A Price ' + temuRuleMoney(amz) + ' → S PRC = <b>' + temuRuleMoney(amz) + '</b> (Amz).'));
                price = amz;
            } else {
                steps.push(temuRuleStep('skip', n + ' · A Price floor', 'Price is at or above A Price ' + temuRuleMoney(amz) + '. It stays.'));
            }

            if (!(parseFloat(d.INV) > 0)) notes.push('INV is 0, so no S PRC is set for this SKU.');
            if (price > 0 && final > 0 && Math.abs(price - final) > 0.009) {
                notes.push('The S PRC shown (' + temuRuleMoney(final) + ') differs from this calculation (' + temuRuleMoney(price) + '). Reload the page.');
            }
            return { title: 'S PRC ' + temuRuleMoney(final), sub: rowSub, steps: steps, notes: notes, finalNote: '' };
        }
        window.temuRuleExplainers = {
            sprice: temuRuleExplainSprice,
            price: function(d) {
                const steps = [];
                const notes = [];
                const price = parseFloat(d['PP Price']) || 0;
                const shown = Number(ppShownSprice(d)) || 0;
                const std = parseFloat(d.STANDARD_PRICE) || 0;
                const amz = ppAmzPrice(d);
                steps.push(temuRuleStep('info', '1 · Source', 'PP Price is the <b>live Purchasing Power listing price</b> (MCM OF21). It changes only when a new price is pushed and synced.'));
                if (shown > 0) {
                    steps.push(Math.abs(shown - price) <= 0.009
                        ? temuRuleStep('ok', '2 · S PRC', 'S PRC ' + temuRuleMoney(shown) + ' is already live. PP Price matches S PRC.')
                        : temuRuleStep('warn', '2 · S PRC', 'S PRC ' + temuRuleMoney(shown) + ' is not live yet (PP Price ' + temuRuleMoney(price) + '). It goes live when it is pushed.'));
                } else {
                    steps.push(temuRuleStep('skip', '2 · S PRC', 'No S PRC yet, so there is nothing waiting to be pushed.'));
                }
                const diff = ppStdPrcDiffPct(d);
                if (diff !== null) {
                    steps.push(temuRuleStep(diff > temuRuleDiffCfg().gt_pct || diff < -5 ? 'warn' : 'ok', '3 · vs Std Prc',
                        'Std Prc ' + temuRuleMoney(std) + ' vs PP Price → Diff <b>' + diff.toFixed(1) + '%</b>'
                        + (diff > temuRuleDiffCfg().gt_pct ? ' (above ' + temuRuleDiffCfg().gt_pct + '%: Sprc Dil adds ' + temuRuleDiffPts(temuRuleDiffCfg().points) + ' to the Target)' : (diff < -5 ? ' (PP Price is above Std Prc)' : ' (normal)')) + '.'));
                } else {
                    steps.push(temuRuleStep('skip', '3 · vs Std Prc', 'Needs both Std Prc and PP Price.'));
                }
                if (amz > 0 && price > 0) {
                    steps.push(temuRuleStep(price < amz ? 'warn' : 'ok', '4 · vs A Price',
                        'A Price ' + temuRuleMoney(amz) + ' · PP Price is ' + (price > amz ? 'above (green)' : (price < amz ? 'below (red)' : 'equal to')) + ' A Price.'));
                } else {
                    steps.push(temuRuleStep('skip', '4 · vs A Price', 'No A Price available.'));
                }
                if (price <= 0) notes.push('PP Price is $0. The listing price is missing.');
                return { title: 'PP Price ' + temuRuleMoney(price), sub: (d['(Child) sku'] || '') + ' · where this price stands', steps: steps, notes: notes, finalNote: '' };
            }
        };
@elseif(in_array($temuRuleHoverKind, ['shopifyb2c', 'shopifyb2b', 'depop', 'wayfair', 'reverb', 'topdawg', 'doba', 'aliexpress', 'faire', 'tiktok', 'mercari', 'pls', 'shein', 'bestbuy', 'newegg'], true))
        @php $sbDepop = in_array($temuRuleHoverKind, ['depop', 'wayfair', 'topdawg', 'faire'], true); $sbB2b = $temuRuleHoverKind === 'shopifyb2b' || $sbDepop; $sbp = $temuRuleHoverKind === 'newegg' ? 'ne' : ($temuRuleHoverKind === 'bestbuy' ? 'bestbuy' : ($temuRuleHoverKind === 'shein' ? 'shein' : ($temuRuleHoverKind === 'pls' ? 'pls' : ($temuRuleHoverKind === 'mercari' ? 'mercWs' : ($temuRuleHoverKind === 'tiktok' ? 'tt' : ($temuRuleHoverKind === 'faire' ? 'fr' : ($temuRuleHoverKind === 'aliexpress' ? 'ae' : ($temuRuleHoverKind === 'doba' ? 'doba' : ($temuRuleHoverKind === 'topdawg' ? 'td' : ($temuRuleHoverKind === 'wayfair' ? 'wf' : ($temuRuleHoverKind === 'reverb' ? 'reverb' : ($sbDepop ? 'dp' : ($sbB2b ? 'shopifyB2b' : 'shopifyB2c'))))))))))))); @endphp
        // Shopify B2C and B2B share this card. B2B: no Ship in the price, the website (business5core.com) is the live Price,
        // no A Price / Amz cap, and Std prc vs dil uses LMP as a discount, not a cap.
        // Depop and Wayfair use this card too (neither has LMP data): no Ship, no Amz cap, LMP is a discount in Std prc vs dil, its own take-home % (not 0.95) and no Ads.
        const SB_IS_B2B = @json($sbB2b);
        const SB_IS_DEPOP = @json($sbDepop);
        const SB_IS_WAYFAIR = @json($temuRuleHoverKind === 'wayfair');
        // Reverb uses this card too: Ship and Ads in the price, LMP cap in both modes, Std Prc maximum, no A Price / Amz cap, Reverb's own take-home %.
        const SB_IS_REVERB = @json($temuRuleHoverKind === 'reverb');
        // TopDawg uses this card too (no LMP data, no Ads, no A Price): take-home % from the row, Ship is only taken off in Std prc vs dil, and clearance SKUs are still priced.
        const SB_IS_TOPDAWG = @json($temuRuleHoverKind === 'topdawg');
        // Doba uses this card too: Ship in the price, no Ads, no LMP data, no A Price / Amz cap, Std Prc maximum, 0.95 take-home.
        const SB_IS_DOBA = @json($temuRuleHoverKind === 'doba');
        // AliExpress uses this card too: Ship in the price, no Ads, LMP cap in both modes (LMP is the lowest landed, non-ignored), Std Prc maximum, no A Price / Amz cap, the AE take-home % (_margin, default 0.89), clearance SKUs priced.
        const SB_IS_AE = @json($temuRuleHoverKind === 'aliexpress');
        // Faire uses this card too: no LMP data, no Ads, no A Price, no Ship in the Sprc Dil price (Ship is only taken off in Std prc vs dil), its own take-home % (_margin, default 0.75), clearance SKUs priced, Std Prc maximum.
        const SB_IS_FAIRE = @json($temuRuleHoverKind === 'faire');
        // TikTok / TikTok 2 use this card too: Ship in the price, Ads% = the listing TACOS% (TikTok 2 = 0), LMP cap in both modes, Std Prc maximum, no A Price / Amz cap, take-home % from the row (default 0.80). Clearance SKUs get no S PRC.
        const SB_IS_TT = @json($temuRuleHoverKind === 'tiktok');
        // Mercari With Ship uses this card too: Ship in the price, no Ads, LMP cap in Sprc Dil only (Std prc vs dil keeps its own price, no LMP cap), Std Prc maximum in Sprc Dil, no A Price / Amz cap, take-home % from the row (factor), no clearance mark.
        const SB_IS_MERC = @json($temuRuleHoverKind === 'mercari');
        // PLS (ProLightSounds) uses this card too: Ship in the price, no Ads, no LMP data, no A Price / Amz cap, Std Prc maximum, take-home % = PLS% from the row, clearance SKUs get no S PRC.
        const SB_IS_PLS = @json($temuRuleHoverKind === 'pls');
        // Shein uses this card too: Ship in the price, no Ads, LMP cap in both modes, Std Prc maximum, no A Price / Amz cap, take-home % from the row (_margin), CVR overlay only with views, Dil outside the slabs uses the nearest slab, clearance SKUs get no S PRC.
        const SB_IS_SHEIN = @json($temuRuleHoverKind === 'shein');
        // Best Buy uses this card too: Ship BB in the price, no Ads, LMP cap in both modes, Std Prc maximum, Amz rule last (never below A Price), take-home % from the row (percentage, default 0.80), clearance SKUs get no S PRC.
        const SB_IS_BB = @json($temuRuleHoverKind === 'bestbuy');
        // Newegg uses this card too: Ship in the price, no Ads, LMP cap = LMP in both modes (the page's own cap, no Dil band reduce), Std Prc maximum, Amz rule last (never below A Price), take-home % from the row (factor), the page saves S PRC from the browser. A clearance SKU gets no rule at all (S PRC shows C).
        const SB_IS_NE = @json($temuRuleHoverKind === 'newegg');
        // Pages with no LMP data show no LMP step.
        const SB_NO_LMP = SB_IS_DEPOP || SB_IS_DOBA || SB_IS_PLS;
        function sbMargin(d) {
            if (SB_IS_REVERB) return (typeof reverbTakeRate === 'function') ? Number(reverbTakeRate(d)) : 0.85;
            if (SB_IS_NE) { return (typeof neRowFactor === 'function') ? neRowFactor(d) : 0.80; }
            if (SB_IS_BB) { if (typeof ebayDilTakehomeMargin === 'function') { const m = Number(ebayDilTakehomeMargin(d)); if (m > 0) return m; } const f = parseFloat(d && (d.percentage != null ? d.percentage : d._margin)); return f > 0 ? (f > 1 ? f / 100 : f) : 0.80; }
            if (SB_IS_SHEIN) { const m = parseFloat(d && d._margin); return m > 0 ? (m > 1 ? m / 100 : m) : 1.0; }
            if (SB_IS_PLS) { if (typeof ebayDilTakehomeMargin === 'function') { const m = Number(ebayDilTakehomeMargin(d)); if (m > 0) return m; } const f = parseFloat(d && (d._margin != null ? d._margin : d.percentage)); return f > 0 ? (f > 1 ? f / 100 : f) : 1.0; }
            if (SB_IS_MERC) { if (typeof ebayDilTakehomeMargin === 'function') { const m = Number(ebayDilTakehomeMargin(d)); if (m > 0) return m; } const f = parseFloat(d && (d.factor != null ? d.factor : d.percentage)); return f > 0 ? (f > 1 ? f / 100 : f) : 0.80; }
            if (SB_IS_TT) { const m = parseFloat(d && d.percentage); return m > 0 ? (m > 1 ? m / 100 : m) : 0.80; }
            if (SB_IS_AE) { const m = parseFloat(d && d._margin); return m > 0 ? (m > 1 ? m / 100 : m) : 0.89; }
            if (!SB_IS_DEPOP) return 0.95;
            const own = parseFloat(SB_IS_TOPDAWG ? (d && d.percentage) : (d && d._margin));
            if (SB_IS_TOPDAWG) return own > 0 ? own : 0.95;
            if (SB_IS_FAIRE) return own > 0 ? (own > 1 ? own / 100 : own) : 0.75;
            if (SB_IS_WAYFAIR) return own > 0 ? own : 0.95;
            const m = (typeof chPromoTakehomeMargin === 'function') ? Number(chPromoTakehomeMargin(d)) : own;
            return m > 0 ? m : 0.87;
        }
        function sbPriceOf(d) { if (SB_IS_REVERB) return parseFloat(d['RV Price']) || 0; if (SB_IS_TOPDAWG) return parseFloat(d['TD Price']) || 0; if (SB_IS_DOBA) return parseFloat(d['doba Price']) || 0; if (SB_IS_AE || SB_IS_FAIRE || SB_IS_MERC || SB_IS_PLS || SB_IS_NE) return parseFloat(d.price) || 0; if (SB_IS_SHEIN) return parseFloat(d.special_offer) || 0; if (SB_IS_BB) return parseFloat(d['BB Price']) || 0; if (SB_IS_TT) return parseFloat(d['TT Price']) || 0; return parseFloat(SB_IS_DEPOP ? (d.price != null ? d.price : d.Price) : d.Price) || 0; }
        /** Rule price before any cap: the one the server saved (SPRICE_RULE_PRICE), else what the rule gives live. */
        function sb2cRuleBasePrice(d, dilOn) {
            const saved = parseFloat(d.SPRICE_RULE_PRICE) || 0;
            if (saved > 0) return saved;
            if (dilOn) {
                const meta = (typeof ebayDilGroiMetaForRow === 'function') ? ebayDilGroiMetaForRow(d) : null;
                return (meta && meta.rawSprc > 0) ? Number(meta.rawSprc) : 0;
            }
            return (typeof window.chStdPriceForRow === 'function') ? (Number(window.chStdPriceForRow(d)) || 0) : 0;
        }

        /** S PRC on Shopify B2C: every rule and exception behind the number. */
        function temuRuleExplainSprice(d) {
            const steps = [];
            const notes = [];
            const dilOn = typeof window.spriceActiveRule === 'function' && window.spriceActiveRule() === 'dil';
            const final = Number({{ $sbp }}ShownSprice(d)) || 0;
            const std = parseFloat(d.STANDARD_PRICE) || 0;
            const lmp = {{ $sbp }}RowLmp(d);
            const lp = parseFloat(d.LP_productmaster != null ? d.LP_productmaster : d.lp) || 0;
            const ship = SB_IS_B2B ? 0 : (parseFloat(d.Ship_productmaster != null ? d.Ship_productmaster : d.ship) || 0);
            const amz = {{ $sbp }}AmzPrice(d);
            const sku = d['(Child) sku'] || d.sku || '';
            const rowTitle = 'S PRC ' + temuRuleMoney(final);
            const rowSub = sku + ' · ' + (dilOn ? 'Sprc Dil rule' : 'Std prc vs dil rule');

            if (window.CH_CLEARANCE_KEEPS_RULES !== true && typeof window.chStdIsClearance === 'function' && window.chStdIsClearance(d)) {
                steps.push(temuRuleStep('skip', 'Clearance',
                    'This SKU is on the Inv days clearance list (YES), so S PRC shows <b>C</b>. No S PRC is saved or pushed for it.'));
                return { title: 'S PRC — (Clearance)', sub: rowSub, steps: steps, notes: notes, finalNote: '' };
            }

            let base = 0;
            let n = 1;
            if (dilOn) {
                const target = temuRuleDilTargetSteps(d, steps);
                const oosMeta = (SB_IS_AE && typeof ebayDilGroiMetaForRow === 'function') ? ebayDilGroiMetaForRow(d) : null;
                if (oosMeta && oosMeta.outOfSlabStd) {
                    // AliExpress: Dil above every slab (INV > 0) prices at Std Prc, and at LMP when Std Prc is above LMP.
                    base = Number(oosMeta.sprc) || 0;
                    steps.push(temuRuleStep('ok', '5 · Out of slab',
                        'Dil is above the last slab → S PRC = Std Prc ' + temuRuleMoney(std) + (oosMeta.lmpCapped ? ', and Std Prc is above LMP → <b>LMP ' + temuRuleMoney(base) + '</b>' : ' = <b>' + temuRuleMoney(base) + '</b>') + '.'));
                }
                const meta = (target != null && typeof ebayDilGroiMetaForRow === 'function') ? ebayDilGroiMetaForRow(d) : null;
                if (meta && meta.rawSprc > 0 && !(oosMeta && oosMeta.outOfSlabStd)) {
                    base = sb2cRuleBasePrice(d, true);
                    const ads = SB_IS_TT ? ((typeof ttDilAdsPct === 'function') ? (Number(ttDilAdsPct(d)) || 0) : 0) : SB_IS_REVERB ? (Number(typeof REVERB_CHANNEL_ADS_PCT !== 'undefined' ? REVERB_CHANNEL_ADS_PCT : 0) || 0) : SB_IS_DOBA ? ((typeof dobaAdsPercentForNet === 'function') ? (Number(dobaAdsPercentForNet()) || 0) : 0) : ((!SB_IS_DEPOP && typeof shopifyChannelAdsPct === 'function') ? (Number(shopifyChannelAdsPct()) || 0) : 0);
                    steps.push(temuRuleStep('ok', '5 · Price from target',
                        '(LP ' + temuRuleMoney(lp) + ' × (1 + ' + target + '%)' + (SB_IS_B2B ? '' : ' + Ship ' + temuRuleMoney(ship)) + ') ÷ (' + sbMargin(d) + (SB_IS_DEPOP ? '' : ' − Ads ' + ads.toFixed(1) + '%') + ') = <b>' + temuRuleMoney(base) + '</b>'));
                }
                n = 6;
            } else {
                base = sb2cRuleBasePrice(d, false);
                steps.push(temuRuleStep('info', '1 · Std prc vs dil (switch ON)',
                    'Std Prc ' + temuRuleMoney(std) + ' less the Age, Dil, B Disc, 0 Sold, CVR and Review discounts' + ((SB_IS_B2B || SB_IS_DOBA || SB_IS_AE) ? ((SB_IS_B2B && !SB_NO_LMP ? ' (LMP Disc too)' : '') + ', then Ship subtracted') : '') + ' (or the SNROI Cap price when the Dil slab has a Target SNROI) = <b>' + temuRuleMoney(base) + '</b>'));
                n = 2;
            }

            // LMP cap: Std prc vs dil → S PRC = LMP. Sprc Dil → LMP × (1 − x%), x by the SKU's Dil band.
            const pct = (dilOn && !SB_IS_NE) ? temuRuleBandPct(d) : 0;
            const sg = (lmp > 0 && lp > 0) ? ((lmp * sbMargin(d) - ship - lp) / lp) * 100 : null;
            const lmpHit = !((SB_IS_B2B || SB_IS_MERC) && !dilOn) && base > 0 && lmp > 0 && base + 0.0001 >= lmp && (sg === null || sg >= 20);
            const afterLmpOnly = lmpHit ? +(lmp * (1 - Math.min(99, Math.max(0, pct)) / 100)).toFixed(2) : null;
            if (SB_NO_LMP) {
                // Depop and Doba have no LMP data: no LMP step.
            } else if (SB_IS_B2B && !dilOn) {
                steps.push(temuRuleStep('skip', n + ' · LMP', 'In Std prc vs dil, LMP is a discount (LMP Disc on the SKU\'s Dil slab, already in the price above). It does not cap S PRC.'));
            } else if (SB_IS_MERC && !dilOn) {
                steps.push(temuRuleStep('skip', n + ' · LMP', 'Std prc vs dil on this page prices off Std Prc through its discounts. LMP does not cap it. (Sprc Dil does cap at LMP by Dil band.)'));
            } else {
                temuRuleLmpStep(steps, {
                    n: String(n), base: base, lmp: lmp, sgroiAtLmp: sg, pct: pct,
                    src: SB_IS_NE ? 'S PRC = LMP' : (dilOn ? 'Dil band, LMP cap table' : 'Std prc vs dil: S PRC = LMP'),
                    after: afterLmpOnly
                });
            }
            const nS = n + (SB_NO_LMP ? -1 : 0);
            let price = ((SB_IS_B2B || SB_IS_MERC) && !dilOn) ? base : (afterLmpOnly != null ? afterLmpOnly : base);

            // Std Prc maximum: Sprc Dil prices from a Target SNROI, so Std Prc caps it. Std prc vs dil may price above Std on purpose.
            if (!(std > 0)) {
                steps.push(temuRuleStep('skip', (nS + 1) + ' · Std Prc maximum', 'Std Prc is blank, so there is no maximum.'));
            } else if (dilOn && price > std + 0.0001) {
                steps.push(temuRuleStep('ok', (nS + 1) + ' · Std Prc maximum', 'Price ' + temuRuleMoney(price) + ' is above Std Prc ' + temuRuleMoney(std) + ' → held at <b>' + temuRuleMoney(std) + '</b>.'));
                price = std;
            } else if (!dilOn) {
                steps.push(temuRuleStep('skip', (nS + 1) + ' · Std Prc maximum', (SB_IS_B2B ? 'Std prc vs dil prices off Std Prc through its discounts, so Std Prc is not a cap here.' : 'Std prc vs dil can price above Std Prc on purpose (negative discount), so Std Prc is not a cap here.')));
            } else {
                steps.push(temuRuleStep('skip', (nS + 1) + ' · Std Prc maximum', 'Price is within Std Prc ' + temuRuleMoney(std) + '. Not capped.'));
            }

            // Amz cap, always last: LMP below A Price or price at/below A Price → A Price; price above A Price stays.
            const lmpBelowAmz = amz > 0 && lmp > 0 && lmp + 0.0001 < amz;
            if (SB_IS_B2B || SB_IS_REVERB || SB_IS_DOBA || SB_IS_AE || SB_IS_TT || SB_IS_MERC || SB_IS_PLS || SB_IS_SHEIN) {
                // Shopify B2B, Reverb, Doba, AliExpress and TikTok have no Amz cap.
            } else if (!(amz > 0)) {
                steps.push(temuRuleStep('skip', (n + 2) + ' · Amz cap', 'No A Price, so no Amz cap.'));
            } else if (lmpBelowAmz) {
                steps.push(temuRuleStep('ok', (n + 2) + ' · Amz cap', 'LMP ' + temuRuleMoney(lmp) + ' is below A Price ' + temuRuleMoney(amz) + ' → S PRC = <b>' + temuRuleMoney(amz) + '</b> (Amz).'));
                price = amz;
            } else if (price > 0 && price <= amz + 0.0001) {
                steps.push(temuRuleStep('ok', (n + 2) + ' · Amz cap', 'Price ' + temuRuleMoney(price) + ' is at or below A Price ' + temuRuleMoney(amz) + ' → S PRC = <b>' + temuRuleMoney(amz) + '</b> (Amz).'));
                price = amz;
            } else {
                steps.push(temuRuleStep('skip', (n + 2) + ' · Amz cap', 'Price is above A Price ' + temuRuleMoney(amz) + '. It stays.'));
            }

            if (lmp > 0 && std > 0 && lmp > std + 0.0001) notes.push('LMP ' + temuRuleMoney(lmp) + ' is above Std Prc ' + temuRuleMoney(std) + '. Review the Std price.');
            if (!(parseFloat(d.INV != null ? d.INV : d.inv) > 0)) notes.push('INV is 0, so no S PRC is set for this SKU.');
            if (!SB_IS_B2B && !SB_IS_MERC && price > 0 && final > 0 && Math.abs(price - final) > 0.009) {
                notes.push('The saved S PRC ' + temuRuleMoney(final) + ' differs from this calculation (' + temuRuleMoney(price)
                    + '). ' + (SB_IS_NE ? 'This page re-saves S PRC from the browser after a rule change; reload in a moment.' : 'The server re-saves S PRC after a rule change; reload in a moment.'));
            }
            return { title: rowTitle, sub: rowSub, steps: steps, notes: notes, finalNote: '' };
        }
        window.temuRuleExplainers = {
            sprice: temuRuleExplainSprice,
            price: function(d) {
                const steps = [];
                const notes = [];
                const price = sbPriceOf(d);
                const shown = Number({{ $sbp }}ShownSprice(d)) || 0;
                const std = parseFloat(d.STANDARD_PRICE) || 0;
                const lmp = {{ $sbp }}RowLmp(d);
                const amz = {{ $sbp }}AmzPrice(d);
                const inv = parseFloat(d.INV != null ? d.INV : d.inv) || 0;
                steps.push(temuRuleStep('info', '1 · Source', (SB_IS_NE ? 'Price is the <b>live Newegg listing price</b>. A pushed price shows here after it is applied on Newegg.' : SB_IS_BB ? 'Price is the <b>Best Buy listed price</b> from the MCM OF21 sync. A pushed price shows here only after the next sync.' : SB_IS_SHEIN ? 'Sp. Price is the <b>live Shein special offer price</b>. It changes only when a new price is pushed to Shein.' : SB_IS_PLS ? 'Price is the <b>live ProLightSounds Shopify price</b>. It changes only when a new price is pushed to ProLightSounds.' : SB_IS_MERC ? 'Price is the <b>Mercari With Ship listing price</b> from the Mercari price / sold upload. It changes only when a new price is uploaded.' : SB_IS_TT ? 'Price is the <b>live TikTok Shop listing price</b> (TT Price). It changes only when a new price is pushed to TikTok.' : SB_IS_FAIRE ? 'Price is the <b>Faire wholesale price</b>. It changes only when a new price is pushed to Faire.' : SB_IS_AE ? 'Price is the <b>live AliExpress listing price</b>. It changes only when a new price is pushed to AliExpress.' : SB_IS_DOBA ? 'Price is the <b>Doba listing price</b> (anticipated income from the Doba sync). It changes only when a new price is pushed to Doba.' : SB_IS_TOPDAWG ? 'Price is the <b>live TopDawg listing price</b> (TD Price). A pushed price goes to TopDawg review first (1–24h) before TD Price follows.' : SB_IS_REVERB ? 'Price is the <b>live Reverb listing price</b> (RV Price). It changes only when a new price is pushed to Reverb.' : SB_IS_WAYFAIR ? 'Price is the <b>Wayfair price</b> from the uploaded price sheet. It changes only when a new price sheet is uploaded.' : SB_IS_DEPOP ? 'Price is the <b>Depop listing price</b> from the Depop upload. It changes only when a new price is uploaded.' : SB_IS_B2B ? 'Price is the <b>live website price</b> (business5core.com). It changes only when a new price is pushed to the website.' : 'Price is the <b>live Shopify B2C listing price</b>. It changes only when a new price is pushed to Shopify.')));
                if (shown > 0) {
                    if (Math.abs(shown - price) <= 0.009) {
                        steps.push(temuRuleStep('ok', '2 · S PRC', 'S PRC ' + temuRuleMoney(shown) + ' is already live. Price matches S PRC.'));
                    } else {
                        const st = d.SPRICE_STATUS ? ' Status: <b>' + temuRuleEsc(d.SPRICE_STATUS) + '</b>.' : '';
                        steps.push(temuRuleStep('warn', '2 · S PRC', 'S PRC ' + temuRuleMoney(shown) + ' is not live yet (Price ' + temuRuleMoney(price) + '). It goes live when it is pushed.' + st));
                    }
                } else {
                    steps.push(temuRuleStep('skip', '2 · S PRC', 'No S PRC yet, so there is nothing waiting to be pushed.'));
                }
                const diff = {{ $sbp }}StdPrcDiffPct(d);
                if (diff !== null) {
                    steps.push(temuRuleStep(diff > temuRuleDiffCfg().gt_pct || diff < -5 ? 'warn' : 'ok', '3 · vs Std Prc',
                        'Std Prc ' + temuRuleMoney(std) + ' vs Price → Diff <b>' + diff.toFixed(1) + '%</b>'
                        + (diff > temuRuleDiffCfg().gt_pct ? ' (above ' + temuRuleDiffCfg().gt_pct + '%: Sprc Dil adds ' + temuRuleDiffPts(temuRuleDiffCfg().points) + ' to the Target SNROI)' : (diff < -5 ? ' (Price is above Std Prc)' : ' (normal)')) + '.'));
                } else {
                    steps.push(temuRuleStep('skip', '3 · vs Std Prc', 'Needs both Std Prc and Price.'));
                }
                if (SB_NO_LMP) {
                    // Depop and Doba have no LMP data: no LMP step.
                } else if (lmp > 0 && price > 0) {
                    const over = inv > 0 && price > lmp;
                    steps.push(temuRuleStep(over ? 'warn' : 'ok', '4 · vs LMP',
                        'LMP ' + temuRuleMoney(lmp) + ' · Price is ' + (price > lmp ? 'above' : (price < lmp ? 'below' : 'equal to')) + ' LMP' + (over ? ' (red triangle).' : '.')));
                    if (price < lmp * 0.8) notes.push('Price is below 80% of LMP (purple triangle).');
                } else {
                    steps.push(temuRuleStep('skip', '4 · vs LMP', 'No LMP available.'));
                }
                if (SB_IS_B2B || SB_IS_REVERB || SB_IS_DOBA || SB_IS_AE || SB_IS_TT || SB_IS_MERC || SB_IS_PLS || SB_IS_SHEIN) {
                    // no A Price on Shopify B2B, Reverb, Doba or AliExpress
                } else if (amz > 0 && price > 0) {
                    steps.push(temuRuleStep(price < amz ? 'warn' : 'ok', '5 · vs A Price',
                        'A Price ' + temuRuleMoney(amz) + ' · Price is ' + (price > amz ? 'above (green)' : (price < amz ? 'below (red)' : 'equal to')) + ' A Price.'));
                } else {
                    steps.push(temuRuleStep('skip', '5 · vs A Price', 'No A Price available.'));
                }
                if (price <= 0) notes.push('Price is $0. The listing price is missing.');
                return {
                    title: 'Price ' + temuRuleMoney(price),
                    sub: (d['(Child) sku'] || '') + ' · where this price stands',
                    steps: steps, notes: notes, finalNote: ''
                };
            }
        };
@elseif($temuRuleHoverKind === 'temu3')
        /** S PRC on Temu 3: every rule and exception behind the number. */
        function temuRuleExplainSprice(d) {
            // A SKU under clearance (Inv days clearance = YES) gets no S PRC from any rule: no Dil, Std, Diff, LMP or Amz rule applies.
            if (typeof window.chStdIsClearance === 'function' && window.chStdIsClearance(d)) {
                return { title: 'S PRC — (Clearance)', sub: (d['(Child) sku'] || d.sku || ''), steps: [temuRuleStep('skip', 'Clearance',
                    'This SKU is on the Inv days clearance list (YES), so S PRC shows <b>C</b>. No rule applies and no S PRC is saved or pushed for it.')], notes: [], finalNote: '' };
            }
            const steps = [];
            const notes = [];
            const dilOn = typeof window.spriceActiveRule === 'function' && window.spriceActiveRule() === 'dil';
            const final = (typeof temuDisplayedSprice === 'function') ? temuDisplayedSprice(d) : 0;
            const rulePrice = (typeof temuDiscountedPrice === 'function') ? temuDiscountedPrice(d) : 0;
            const lmp = (typeof temuLmpRefPrice === 'function') ? temuLmpRefPrice(d) : 0;
            const std = parseFloat(d.STANDARD_PRICE) || 0;

            steps.push(temuRuleStep('info', '1 · Std prc vs dil',
                'S PRC starts at the Std prc vs dil price: Std Prc ' + temuRuleMoney(std) + ' less the Age, Dil, CVR and Review discounts = <b>' + temuRuleMoney(rulePrice) + '</b>.'));
            if (dilOn) {
                steps.push(temuRuleStep('info', 'Sprc Dil (switch ON)', 'The Sprc Dil column and the server run use the Target SNROI below. This cell is still priced from Std prc vs dil.'));
                temuRuleDilTargetSteps(d, steps);
            }
            let after = null;
            let sg = null;
            if (lmp > 0 && rulePrice + 0.0001 >= lmp) {
                const parts = (typeof temuSpriceCalcParts === 'function') ? temuSpriceCalcParts(d, lmp) : null;
                sg = parts && parts.sgroi != null ? Number(parts.sgroi) : null;
                if (sg === null || sg >= 20) after = +(lmp * (1 - temuRuleBandPct(d) / 100)).toFixed(2);
            }
            temuRuleLmpStep(steps, { n: '2', base: rulePrice, lmp: lmp, sgroiAtLmp: sg, pct: temuRuleBandPct(d), after: after });
            const zeroSold = typeof chPromoTemuZeroSoldOwnsSprice === 'function' && chPromoTemuZeroSoldOwnsSprice(d);
            if (zeroSold) notes.push('0 Sold SKU: the 0 Sold rule owns S PRC, so the LMP rule is skipped.');
            if (lmp > 0 && std > 0 && lmp > std + 0.0001) notes.push('LMP ' + temuRuleMoney(lmp) + ' is above Std Prc ' + temuRuleMoney(std) + '. Review the Std price.');
            return {
                title: 'S PRC ' + temuRuleMoney(final),
                sub: (d['(Child) sku'] || d.sku || d.SKU || '') + ' · ' + (dilOn ? 'Sprc Dil switch ON' : 'Std prc vs dil rule'),
                steps: steps, notes: notes, finalNote: ''
            };
        }
        window.temuRuleExplainers = {
            sprice: temuRuleExplainSprice,
            price: function(d) {
                const price = (typeof temu2FullPriceFromRow === 'function') ? temu2FullPriceFromRow(d) : (parseFloat(d.temu_price) || 0);
                return temuRuleExplainPrice(d, {
                    price: price,
                    pushLabel: 'S PRC',
                    push: (typeof temuDisplayedSprice === 'function') ? temuDisplayedSprice(d) : 0,
                    live: parseFloat(d.temu_price) || 0,
                    std: parseFloat(d.STANDARD_PRICE) || 0,
                    lmp: (typeof temuLmpRefPrice === 'function') ? temuLmpRefPrice(d) : 0,
                    inv: parseFloat(d.inv != null ? d.inv : d.INV) || 0
                });
            }
        };
@endif

        function temuRuleTipRender(model) {
            const icon = { ok: '✓', skip: '–', info: 'i', warn: '!' };
            let html = '<div class="temu-rule-tip-head"><div class="temu-rule-tip-title">' + temuRuleEsc(model.title)
                + '</div><div class="temu-rule-tip-sub">' + temuRuleEsc(model.sub) + '</div></div><div class="temu-rule-tip-body">';
            model.steps.forEach(function(s) {
                html += '<div class="temu-rule-step is-' + s.state + '"><span class="temu-rule-ico">' + icon[s.state]
                    + '</span><div><div class="temu-rule-step-title">' + temuRuleEsc(s.title) + '</div><div class="temu-rule-step-detail">'
                    + s.detail + '</div></div></div>';
            });
            if (model.notes.length) {
                html += '<div class="temu-rule-notes"><b>Exceptions</b><ul>'
                    + model.notes.map(function(n) { return '<li>' + n + '</li>'; }).join('') + '</ul></div>';
            }
            if (model.finalNote) html += '<div class="temu-rule-final">' + model.finalNote + '</div>';
            return html + '</div>';
        }
        function temuRuleTipEnsure() {
            if (temuRuleTipEl) return temuRuleTipEl;
            if (!document.getElementById('temu-rule-tip-css')) {
                const st = document.createElement('style');
                st.id = 'temu-rule-tip-css';
                st.textContent = [
                    '#temu-rule-tip{position:fixed;z-index:20000;width:380px;max-width:92vw;max-height:80vh;overflow:auto;background:#fff;border-radius:12px;box-shadow:0 12px 40px rgba(15,23,42,.28);font-size:12px;color:#334155;display:none}',
                    '#temu-rule-tip .temu-rule-tip-head{padding:10px 14px;background:linear-gradient(90deg,#6f42c1,#8b5cf6);color:#fff;border-radius:12px 12px 0 0}',
                    '#temu-rule-tip .temu-rule-tip-title{font-size:15px;font-weight:700}',
                    '#temu-rule-tip .temu-rule-tip-sub{font-size:11px;opacity:.85}',
                    '#temu-rule-tip .temu-rule-tip-body{padding:8px 12px 10px}',
                    '#temu-rule-tip .temu-rule-step{display:flex;gap:8px;padding:5px 0;border-bottom:1px dashed #e2e8f0}',
                    '#temu-rule-tip .temu-rule-ico{flex:0 0 18px;height:18px;border-radius:50%;text-align:center;line-height:18px;font-size:11px;font-weight:700;color:#fff;background:#94a3b8}',
                    '#temu-rule-tip .is-ok .temu-rule-ico{background:#16a34a}',
                    '#temu-rule-tip .is-info .temu-rule-ico{background:#3b82f6}',
                    '#temu-rule-tip .is-warn .temu-rule-ico{background:#f59e0b}',
                    '#temu-rule-tip .is-skip{opacity:.7}',
                    '#temu-rule-tip .temu-rule-step-title{font-weight:700;color:#1e293b}',
                    '#temu-rule-tip .temu-rule-step-detail{line-height:1.35}',
                    '#temu-rule-tip .temu-rule-notes{margin-top:8px;padding:6px 10px;background:#fff7ed;border-radius:8px;color:#9a3412}',
                    '#temu-rule-tip .temu-rule-notes ul{margin:2px 0 0;padding-left:16px}',
                    '#temu-rule-tip .temu-rule-final{margin-top:8px;padding:6px 10px;background:#eff6ff;border-radius:8px;color:#1d4ed8}'
                ].join('');
                document.head.appendChild(st);
            }
            temuRuleTipEl = document.createElement('div');
            temuRuleTipEl.id = 'temu-rule-tip';
            document.body.appendChild(temuRuleTipEl);
            temuRuleTipEl.addEventListener('mouseenter', function() { clearTimeout(temuRuleTipHideTimer); });
            temuRuleTipEl.addEventListener('mouseleave', temuRuleTipScheduleHide);
            return temuRuleTipEl;
        }
        function temuRuleTipScheduleHide() {
            clearTimeout(temuRuleTipShowTimer);
            clearTimeout(temuRuleTipHideTimer);
            temuRuleTipHideTimer = setTimeout(function() {
                if (temuRuleTipEl) temuRuleTipEl.style.display = 'none';
            }, 200);
        }
        function temuRuleTipShow(kind, cell) {
            clearTimeout(temuRuleTipHideTimer);
            clearTimeout(temuRuleTipShowTimer);
            temuRuleTipShowTimer = setTimeout(function() {
                const d = cell.getRow().getData();
                if (!d || d.is_parent_summary || d.is_parent) return;
                const sku = String(d['(Child) sku'] || d.sku || d.SKU || '');
                if (!sku || sku.toUpperCase().indexOf('PARENT') !== -1) return;
                let model;
                try {
                    model = window.temuRuleExplainers[kind](d);
                } catch (e) { return; }
                const el = temuRuleTipEnsure();
                el.innerHTML = temuRuleTipRender(model);
                el.style.display = 'block';
                const r = cell.getElement().getBoundingClientRect();
                const w = el.offsetWidth;
                const h = el.offsetHeight;
                let left = r.right + 8;
                if (left + w > window.innerWidth - 8) left = Math.max(8, r.left - w - 8);
                let top = r.top - 10;
                if (top + h > window.innerHeight - 8) top = Math.max(8, window.innerHeight - h - 8);
                el.style.left = left + 'px';
                el.style.top = Math.max(8, top) + 'px';
            }, 250);
        }
        window.temuRuleTipShow = temuRuleTipShow;
        window.temuRuleTipScheduleHide = temuRuleTipScheduleHide;
