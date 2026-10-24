{{--
    Hover explainer for the Temu pages (same card as /ebay-tabulator-view): hovering a S Promo Prc / S PRC or a Temu Price cell
    lists every rule and exception behind that number. Applied rules are green, rules that did not apply are grey.

    Script-only partial. Include it inside the page's <script>, after partials.ebay-sprc-dil (it reads its helpers).
    Kind 'nto' is for New Temu One / New Temu Two, 'temu3' is for Temu 3, 'amazon' is for /amazon-tabulator-view.
    Then add to the two columns:
        cellMouseEnter: function(e, cell) { temuRuleTipShow('sprice' | 'price', cell); },
        cellMouseLeave: function() { temuRuleTipScheduleHide(); },
--}}
@php $temuRuleHoverKind = $temuRuleHoverKind ?? 'nto'; @endphp
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

        /** Steps 1–4: Dil slab, CVR overlay, Diff rule and the final Target SNROI. Returns the target, or null when none. */
        function temuRuleDilTargetSteps(d, steps) {
            if (typeof ebayDgIsChild !== 'function' || !ebayDgIsChild(d) || !(ebayDgInv(d) > 0)) {
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
                    'Diff ' + diffPct.toFixed(1) + '% (Std Prc ' + temuRuleMoney(std) + ' vs Temu Price ' + temuRuleMoney(price) + ') is above 20% → <b>+'
                    + diffAdj + '</b> to Target SNROI'));
            } else {
                steps.push(temuRuleStep('skip', '3 · Diff rule',
                    diffPct === null ? 'Needs both Std Prc and Temu Price. Not applied.'
                        : ('Diff ' + diffPct.toFixed(1) + '% is not above 20%. No +10')));
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
                steps.push(temuRuleStep(diff > 20 || diff < -5 ? 'warn' : 'ok', '3 · vs Std Prc',
                    'Std Prc ' + temuRuleMoney(o.std) + ' vs Temu Price → Diff <b>' + diff.toFixed(1) + '%</b>'
                    + (diff > 20 ? ' (above 20%: Sprc Dil adds +10 to the Target SNROI)' : (diff < -5 ? ' (Price is above Std Prc)' : ' (normal)')) + '.'));
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
                steps.push(temuRuleStep('ok', '3 · Diff rule', 'Diff ' + diffPct.toFixed(1) + '% is above 20% → <b>+' + diffAdj + '</b> to Target NROI'));
            } else {
                steps.push(temuRuleStep('skip', '3 · Diff rule',
                    diffPct === null ? 'Needs both Std Prc and Price. Not applied.' : ('Diff ' + diffPct.toFixed(1) + '% is not above 20%. No +10')));
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
                    steps.push(temuRuleStep(diff > 20 || diff < -5 ? 'warn' : 'ok', '3 · vs Std Prc',
                        'Std Prc ' + temuRuleMoney(std) + ' vs Price → Diff <b>' + diff.toFixed(1) + '%</b>'
                        + (diff > 20 ? ' (above 20%: Sprc Dil adds +10 to the Target NROI)' : (diff < -5 ? ' (Price is above Std Prc)' : ' (normal)')) + '.'));
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
@elseif($temuRuleHoverKind === 'temu3')
        /** S PRC on Temu 3: every rule and exception behind the number. */
        function temuRuleExplainSprice(d) {
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
