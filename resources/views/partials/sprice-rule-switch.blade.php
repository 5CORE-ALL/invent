{{-- ON/OFF for Sprc Dil vs Std prc vs dil. Normally one rule fills S PRC. With $spriceRuleIndependent (Amazon) each rule is ON / OFF on its own. Parts: buttons (both switches together), dil_switch / std_switch (one switch each, to put inside that rule's own modal), script. --}}
@php
    $spriceRuleSwitchPart = $spriceRuleSwitchPart ?? 'all';
    $spriceRuleChannel = $spriceRuleChannel ?? 'ebay1';
    $spriceRuleIndependent = (bool) ($spriceRuleIndependent ?? false);
@endphp

@if($spriceRuleSwitchPart === 'buttons' || $spriceRuleSwitchPart === 'all')
                    <div class="d-inline-flex align-items-center gap-3 ms-1" id="sprice-rule-switch" data-channel="{{ $spriceRuleChannel }}" title="{{ $spriceRuleIndependent ? 'Each rule is ON / OFF on its own. Sprc Dil ON fills S PRC from Sprc Dil, else Std prc vs dil ON fills it from Std prc vs dil. Both OFF: S PRC is not changed. Cron uses the same choice.' : 'Only the ON rule fills S PRC. Turning one ON turns the other OFF. Cron uses the same choice.' }}">
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="sprice-rule-dil-sw">
                            <label class="form-check-label small" for="sprice-rule-dil-sw">Sprc Dil</label>
                        </div>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="sprice-rule-std-sw" checked>
                            <label class="form-check-label small" for="sprice-rule-std-sw">Std prc vs dil</label>
                        </div>
                    </div>
@endif

@if($spriceRuleSwitchPart === 'dil_switch')
                    {{-- Marker the page scripts look for (the switches sit in separate modals, there is no single wrapper). --}}
                    <span id="sprice-rule-switch" data-channel="{{ $spriceRuleChannel }}" hidden></span>
                    <div class="form-check form-switch mb-0 ms-auto me-3 sprice-rule-sw-single" title="Sprc Dil rule ON / OFF. Independent: it does not change the Std prc vs dil switch. Cron uses the same choice.">
                        <input class="form-check-input" type="checkbox" id="sprice-rule-dil-sw">
                        <label class="form-check-label small fw-semibold" for="sprice-rule-dil-sw">Rule ON / OFF</label>
                    </div>
@endif

@if($spriceRuleSwitchPart === 'std_switch')
                    <div class="form-check form-switch mb-0 ms-auto me-3 sprice-rule-sw-single" title="Std prc vs dil rule ON / OFF. Independent: it does not change the Sprc Dil switch. Cron uses the same choice.">
                        <input class="form-check-input" type="checkbox" id="sprice-rule-std-sw" checked>
                        <label class="form-check-label small fw-semibold" for="sprice-rule-std-sw">Rule ON / OFF</label>
                    </div>
@endif

@if($spriceRuleSwitchPart === 'script' || $spriceRuleSwitchPart === 'all')
        (function() {
            if (window._spriceRuleSwitchBound) return;
            window._spriceRuleSwitchBound = true;
            const SPRICE_RULE_CHANNEL = @json($spriceRuleChannel);
            const SPRICE_RULE_KEY = 'sprice_active_rule_' + SPRICE_RULE_CHANNEL;
            // Independent mode (Amazon): each rule has its own ON / OFF and both may be OFF. The ON rule is dil, std or none.
            const SPRICE_INDEP = @json($spriceRuleIndependent);
            const SPRICE_STATE_KEY = 'sprice_rule_state_' + SPRICE_RULE_CHANNEL;
            function spriceRuleFromState(st) { return st.dil ? 'dil' : (st.std ? 'std' : 'none'); }
            function spriceRuleState() {
                if (window._spriceRuleState) return { dil: !!window._spriceRuleState.dil, std: !!window._spriceRuleState.std };
                try {
                    const raw = localStorage.getItem(SPRICE_STATE_KEY);
                    if (raw) {
                        const o = JSON.parse(raw);
                        if (o && typeof o.dil === 'boolean' && typeof o.std === 'boolean') return { dil: o.dil, std: o.std };
                    }
                } catch (e) { /* default */ }
                return { dil: false, std: true };
            }
            function spriceStoreState(st) {
                window._spriceRuleState = { dil: !!st.dil, std: !!st.std };
                window._spriceActiveRule = spriceRuleFromState(window._spriceRuleState);
                try { localStorage.setItem(SPRICE_STATE_KEY, JSON.stringify(window._spriceRuleState)); } catch (e) { /* ignore */ }
            }
            function spriceRuleCsrf() {
                if (typeof chPromoCsrf === 'function') return chPromoCsrf();
                if (typeof amzPefCsrf === 'function') return amzPefCsrf();
                const m = document.querySelector('meta[name="csrf-token"]');
                return m ? (m.getAttribute('content') || '') : '';
            }
            function spriceActiveRule() {
                if (SPRICE_INDEP) return spriceRuleFromState(spriceRuleState());
                if (window._spriceActiveRule === 'dil' || window._spriceActiveRule === 'std') return window._spriceActiveRule;
                try {
                    const v = localStorage.getItem(SPRICE_RULE_KEY);
                    if (v === 'dil' || v === 'std') return v;
                } catch (e) { /* default */ }
                return 'std';
            }
            function spricePaintRuleSwitch() {
                const rule = spriceActiveRule();
                const dil = document.getElementById('sprice-rule-dil-sw');
                const std = document.getElementById('sprice-rule-std-sw');
                if (SPRICE_INDEP) {
                    const st = spriceRuleState();
                    if (dil) dil.checked = st.dil;
                    if (std) std.checked = st.std;
                    return;
                }
                if (dil) dil.checked = rule === 'dil';
                if (std) std.checked = rule === 'std';
            }
            let spriceRuleResolve = null;
            window.spriceActiveRuleReady = new Promise(function(resolve) { spriceRuleResolve = resolve; });
            function spriceSettleRule(rule, state) {
                if (SPRICE_INDEP) {
                    if (state) spriceStoreState(state);
                    rule = spriceActiveRule();
                } else {
                    rule = rule === 'dil' ? 'dil' : 'std';
                    window._spriceActiveRule = rule;
                    try { localStorage.setItem(SPRICE_RULE_KEY, rule); } catch (e) { /* ignore */ }
                }
                spricePaintRuleSwitch();
                if (typeof window.frRefreshSpriceCells === 'function') {
                    try { window.frRefreshSpriceCells(); } catch (e) { /* ignore */ }
                }
                if (typeof window.mercWosRefreshSpriceCells === 'function') {
                    try { window.mercWosRefreshSpriceCells(); } catch (e) { /* ignore */ }
                }
                if (typeof window.shopifyB2bRefreshSpriceCells === 'function') {
                    try { window.shopifyB2bRefreshSpriceCells(); } catch (e2) { /* ignore */ }
                }
                if (typeof window.macysRefreshSpriceCells === 'function') {
                    try { window.macysRefreshSpriceCells(); } catch (e5) { /* ignore */ }
                }
                if (typeof window.ppRefreshSpriceCells === 'function') {
                    try { window.ppRefreshSpriceCells(); } catch (e6) { /* ignore */ }
                }
                if (typeof window.dpRefreshSpriceCells === 'function') {
                    try { window.dpRefreshSpriceCells(); } catch (e7) { /* ignore */ }
                }
                if (typeof window.wfRefreshSpriceCells === 'function') {
                    try { window.wfRefreshSpriceCells(); } catch (e8) { /* ignore */ }
                }
                if (typeof window.ttRefreshSpriceCells === 'function') {
                    try { window.ttRefreshSpriceCells(); } catch (e14) { /* ignore */ }
                }
                if (typeof window.mercWsRefreshSpriceCells === 'function') {
                    try { window.mercWsRefreshSpriceCells(); } catch (e15) { /* ignore */ }
                }
                if (typeof window.plsRefreshSpriceCells === 'function') {
                    try { window.plsRefreshSpriceCells(); } catch (e16) { /* ignore */ }
                }
                if (typeof window.sheinRefreshSpriceCells === 'function') {
                    try { window.sheinRefreshSpriceCells(); } catch (e17) { /* ignore */ }
                }
                if (typeof window.bestbuyRefreshSpriceCells === 'function') {
                    try { window.bestbuyRefreshSpriceCells(); } catch (e18) { /* ignore */ }
                }
                if (typeof window.aeRefreshSpriceCells === 'function') {
                    try { window.aeRefreshSpriceCells(); } catch (e12) { /* ignore */ }
                }
                if (typeof window.dobaRefreshSpriceCells === 'function') {
                    try { window.dobaRefreshSpriceCells(); } catch (e11) { /* ignore */ }
                }
                if (typeof window.tdRefreshSpriceCells === 'function') {
                    try { window.tdRefreshSpriceCells(); } catch (e10) { /* ignore */ }
                }
                if (typeof window.reverbRefreshSpriceCells === 'function') {
                    try { window.reverbRefreshSpriceCells(); } catch (e9) { /* ignore */ }
                }
                if (typeof window.temu3RefreshSpriceCells === 'function') {
                    try { window.temu3RefreshSpriceCells(); } catch (e19) { /* ignore */ }
                }
                if (spriceRuleResolve) {
                    const done = spriceRuleResolve;
                    spriceRuleResolve = null;
                    done(rule);
                }
            }
            function spriceApplyOnRule() {
                const rule = spriceActiveRule();
                // A rule switch refills S PRC: SKUs cleared with the Sprice button are included again.
                if (window.amzSpriceClearedSkus && typeof window.amzSpriceClearedSkus.clear === 'function') window.amzSpriceClearedSkus.clear();
                // A page can take over the switch: repaint at once from the ON rule, then save in the background.
                if (typeof window.spriceOnRuleSwitched === 'function') {
                    try { window.spriceOnRuleSwitched(rule); } catch (e) { /* ignore */ }
                    return;
                }
                if (typeof window.amzScheduleRuleSpriceSync === 'function') {
                    try { window.amzScheduleRuleSpriceSync(); } catch (e) { /* ignore */ }
                    return;
                }
                if (rule === 'dil') {
                    if (typeof ebayApplySprcDilToTable === 'function') {
                        Promise.resolve(ebayApplySprcDilToTable({ persist: true, push: false })).catch(function() { /* ignore */ });
                    }
                } else if (typeof window.chStdApplyActivePrices === 'function') {
                    window.chStdApplyActivePrices();
                }
                if (typeof window.shopifyB2bRefreshSpriceCells === 'function') {
                    try { window.shopifyB2bRefreshSpriceCells(); } catch (e2) { /* ignore */ }
                }
                if (typeof window.frRefreshSpriceCells === 'function') {
                    try { window.frRefreshSpriceCells(); } catch (e3) { /* ignore */ }
                }
                if (typeof window.mercWosRefreshSpriceCells === 'function') {
                    try { window.mercWosRefreshSpriceCells(); } catch (e4) { /* ignore */ }
                }
            }
            function spriceSetActiveRule(rule, opts) {
                opts = opts || {};
                rule = rule === 'dil' ? 'dil' : 'std';
                const changed = spriceActiveRule() !== rule;
                window._spriceActiveRule = rule;
                try { localStorage.setItem(SPRICE_RULE_KEY, rule); } catch (e) { /* ignore */ }
                spricePaintRuleSwitch();
                if (!opts.skipApply && changed) spriceApplyOnRule();
                if (!opts.skipSave && changed) {
                    $.ajax({
                        url: '/channel-promo-pricing/' + encodeURIComponent(SPRICE_RULE_CHANNEL) + '/sprice-active-rule',
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': spriceRuleCsrf(), 'Accept': 'application/json' },
                        data: { _token: spriceRuleCsrf(), rule: rule },
                    });
                }
                if (changed && typeof showToast === 'function') {
                    showToast(rule === 'dil' ? 'S PRC uses Sprc Dil' : 'S PRC uses Std prc vs dil', 'success');
                }
            }
            /** Independent mode: set one rule's own switch. The other one is not touched. */
            function spriceSetRuleSwitch(which, on) {
                const before = spriceRuleState();
                const beforeRule = spriceRuleFromState(before);
                const next = { dil: before.dil, std: before.std };
                next[which] = !!on;
                if (next.dil === before.dil && next.std === before.std) return;
                spriceStoreState(next);
                spricePaintRuleSwitch();
                // S PRC only needs to be re-filled when the rule that fills it has changed.
                if (spriceRuleFromState(next) !== beforeRule) spriceApplyOnRule();
                $.ajax({
                    url: '/channel-promo-pricing/' + encodeURIComponent(SPRICE_RULE_CHANNEL) + '/sprice-active-rule',
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': spriceRuleCsrf(), 'Accept': 'application/json' },
                    data: { _token: spriceRuleCsrf(), rule: spriceRuleFromState(next), dil: next.dil ? 1 : 0, std: next.std ? 1 : 0 },
                });
                if (typeof showToast === 'function') {
                    const name = which === 'dil' ? 'Sprc Dil' : 'Std prc vs dil';
                    showToast(name + ' rule ' + (on ? 'ON' : 'OFF'), 'success');
                }
            }
            window.spriceActiveRule = spriceActiveRule;
            window.spriceSetActiveRule = spriceSetActiveRule;
            function spriceBindRuleSwitch() {
                spricePaintRuleSwitch();
                spriceSettleRule(spriceActiveRule());
                // Delegated on the document: the two switches may sit in different modals (Amazon) or in one wrapper (eBay etc.).
                $(document).off('change.spricerule').on('change.spricerule', '#sprice-rule-dil-sw, #sprice-rule-std-sw', function() {
                    const picked = this.id === 'sprice-rule-dil-sw' ? 'dil' : 'std';
                    if (SPRICE_INDEP) {
                        spriceSetRuleSwitch(picked, this.checked);
                        return;
                    }
                    if (!this.checked) {
                        spriceSetActiveRule(picked === 'dil' ? 'std' : 'dil');
                        return;
                    }
                    spriceSetActiveRule(picked);
                });
                $.ajax({
                    url: '/channel-promo-pricing/' + encodeURIComponent(SPRICE_RULE_CHANNEL) + '/sprice-active-rule',
                    method: 'GET',
                    headers: { 'Accept': 'application/json' },
                    timeout: 8000,
                }).done(function(res) {
                    // The saved rule is now known (the first settle above only used localStorage / the default).
                    window._spriceRuleFromServer = true;
                    if (SPRICE_INDEP && res && typeof res.dil !== 'undefined' && typeof res.std !== 'undefined') {
                        spriceSettleRule(null, { dil: !!res.dil, std: !!res.std });
                        return;
                    }
                    spriceSettleRule(res && res.rule === 'dil' ? 'dil' : 'std');
                }).fail(function() {
                    window._spriceRuleFromServer = true;
                    spriceSettleRule(spriceActiveRule());
                });
            }
            if (window.jQuery) {
                $(spriceBindRuleSwitch);
            }
        })();
@endif
