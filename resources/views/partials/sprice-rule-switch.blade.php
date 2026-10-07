{{-- ON/OFF for Sprc Dil vs Std prc vs dil. One rule fills S PRC. Parts: buttons, script. --}}
@php
    $spriceRuleSwitchPart = $spriceRuleSwitchPart ?? 'all';
    $spriceRuleChannel = $spriceRuleChannel ?? 'ebay1';
@endphp

@if($spriceRuleSwitchPart === 'buttons' || $spriceRuleSwitchPart === 'all')
                    <div class="d-inline-flex align-items-center gap-3 ms-1" id="sprice-rule-switch" data-channel="{{ $spriceRuleChannel }}" title="Only the ON rule fills S PRC. Turning one ON turns the other OFF. Cron uses the same choice.">
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

@if($spriceRuleSwitchPart === 'script' || $spriceRuleSwitchPart === 'all')
        (function() {
            if (window._spriceRuleSwitchBound) return;
            window._spriceRuleSwitchBound = true;
            const SPRICE_RULE_CHANNEL = @json($spriceRuleChannel);
            const SPRICE_RULE_KEY = 'sprice_active_rule_' + SPRICE_RULE_CHANNEL;
            function spriceRuleCsrf() {
                if (typeof chPromoCsrf === 'function') return chPromoCsrf();
                if (typeof amzPefCsrf === 'function') return amzPefCsrf();
                const m = document.querySelector('meta[name="csrf-token"]');
                return m ? (m.getAttribute('content') || '') : '';
            }
            function spriceActiveRule() {
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
                if (dil) dil.checked = rule === 'dil';
                if (std) std.checked = rule === 'std';
            }
            function spriceApplyOnRule() {
                const rule = spriceActiveRule();
                if (rule === 'dil') {
                    if (typeof ebayApplySprcDilToTable === 'function') {
                        Promise.resolve(ebayApplySprcDilToTable({ persist: true, push: false })).catch(function() { /* ignore */ });
                    }
                } else if (typeof window.chStdApplyActivePrices === 'function') {
                    window.chStdApplyActivePrices();
                }
                if (typeof window.amzScheduleRuleSpriceSync === 'function') {
                    try { window.amzScheduleRuleSpriceSync(); } catch (e) { /* ignore */ }
                }
                if (typeof window.shopifyB2bRefreshSpriceCells === 'function') {
                    try { window.shopifyB2bRefreshSpriceCells(); } catch (e2) { /* ignore */ }
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
            window.spriceActiveRule = spriceActiveRule;
            window.spriceSetActiveRule = spriceSetActiveRule;
            function spriceBindRuleSwitch() {
                spricePaintRuleSwitch();
                $('#sprice-rule-switch').off('change.spricerule').on('change.spricerule', 'input', function() {
                    const picked = this.id === 'sprice-rule-dil-sw' ? 'dil' : 'std';
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
                }).done(function(res) {
                    const server = res && res.rule === 'dil' ? 'dil' : 'std';
                    if (server !== spriceActiveRule()) {
                        window._spriceActiveRule = server;
                        try { localStorage.setItem(SPRICE_RULE_KEY, server); } catch (e) { /* ignore */ }
                        spricePaintRuleSwitch();
                        spriceApplyOnRule();
                    }
                });
            }
            if (window.jQuery) {
                $(spriceBindRuleSwitch);
            }
        })();
@endif
