/**
 * Shared Temu ads dynamic rules for /temu/ads and /temu-decrease.
 * L7 clicks < threshold → Run. L7 clicks ≥ threshold → Pause (red).
 * Pause/Run does not use ROAS vs T ROAS.
 */
(function (global) {
    'use strict';

    var CLICKS_STORAGE_KEY = 'temu_ads_l7_clicks_red_below';
    var ROAS_STORAGE_KEY = 'temu_ads_target_roas_bidding';
    var SLABS_STORAGE_KEY = 'temu_ads_pause_run_slabs';
    var INV_ZERO_STORAGE_KEY = 'temu_ads_pause_run_inv_zero';
    var ROAS_RULE_STORAGE_KEY = 'temu_ads_roas_rule_slabs';
    var DEFAULT_BELOW = 70;
    var DEFAULT_TARGET_ROAS = 8;
    var DEFAULT_PAUSE_RUN_SLABS = [
        { min: 0, max: 69, action: 'run' },
        { min: 70, max: null, action: 'pause' },
    ];
    var DEFAULT_ROAS_RULE_SLABS = [
        { spend_min: 0, spend_max: 0, roas_min: null, roas_max: null, target_roas: 4, style: 'red' },
        { spend_min: 0.01, spend_max: 5.99, roas_min: null, roas_max: null, target_roas: 5, style: 'yellow' },
        { spend_min: 6, spend_max: 9, roas_min: null, roas_max: null, target_roas: 10, style: 'green' },
        { spend_min: 9.01, spend_max: null, roas_min: null, roas_max: null, target_roas: 12, style: 'pink' },
    ];
    var ROAS_RULE_STYLES = {
        red: { color: '#a00211', background: '', weight: '700' },
        yellow: { color: '#111111', background: '#ffc107', weight: '700' },
        green: { color: '#198754', background: '', weight: '700' },
        pink: { color: '#111111', background: '#f9a8d4', weight: '700' },
    };
    var RED = '#a00211';
    var BIDDING_COLOR = '#0d6efd';
    // Dil% bands — same as /temu-decrease and /topdawg-tabulator:
    // red < 25, green 25–50, pink ≥ 50.
    var DIL_COLORS = {
        red: '#a00211',
        green: '#28a745',
        pink: '#e83e8c',
    };
    var listeners = [];

    function parseClicks(v) {
        if (v === null || v === undefined || v === '') return NaN;
        if (typeof v === 'number') return isFinite(v) ? v : NaN;
        var n = parseInt(String(v).replace(/,/g, '').trim(), 10);
        return isFinite(n) ? n : NaN;
    }

    function parseRoas(v) {
        if (v === null || v === undefined || v === '') return NaN;
        if (typeof v === 'number') return isFinite(v) ? v : NaN;
        var n = parseFloat(String(v).replace(/,/g, '').trim());
        return isFinite(n) ? n : NaN;
    }

    function toInt(v, fallback) {
        var n = parseClicks(v);
        return isFinite(n) && n >= 0 ? n : fallback;
    }

    function toRoas(v, fallback) {
        var n = parseRoas(v);
        return isFinite(n) && n >= 0.1 ? Math.round(n * 10) / 10 : fallback;
    }

    function normalizePauseRunSlabs(raw) {
        var list = raw;
        if (typeof raw === 'string') {
            try { list = JSON.parse(raw); } catch (e) { list = null; }
        }
        if (!Array.isArray(list) || !list.length) {
            list = DEFAULT_PAUSE_RUN_SLABS;
        }
        var out = [];
        list.forEach(function (item) {
            if (!item || typeof item !== 'object') return;
            var min = parseInt(item.min, 10);
            if (!isFinite(min) || min < 0) min = 0;
            var max = item.max === null || item.max === '' || item.max === undefined
                ? null
                : parseInt(item.max, 10);
            if (max !== null && (!isFinite(max) || max < min)) max = min;
            var action = String(item.action || '').toLowerCase() === 'run' ? 'run' : 'pause';
            out.push({ min: min, max: max, action: action });
        });
        if (!out.length) {
            DEFAULT_PAUSE_RUN_SLABS.forEach(function (s) { out.push({ min: s.min, max: s.max, action: s.action }); });
        }
        out.sort(function (a, b) { return a.min - b.min; });
        return out;
    }

    function loadLocalSlabs() {
        try {
            return normalizePauseRunSlabs(global.localStorage && localStorage.getItem(SLABS_STORAGE_KEY));
        } catch (e) {
            return normalizePauseRunSlabs(null);
        }
    }

    function parseMoney(v) {
        if (v === null || v === undefined || v === '') return NaN;
        if (typeof v === 'number') return isFinite(v) ? v : NaN;
        var n = parseFloat(String(v).replace(/[$,]/g, '').trim());
        return isFinite(n) ? n : NaN;
    }

    function toMoneyOrNull(v) {
        if (v === null || v === undefined || v === '') return null;
        var n = parseMoney(v);
        if (!isFinite(n) || n < 0) return null;
        return Math.round(n * 100) / 100;
    }

    function toTargetRoasOrNull(v) {
        if (v === null || v === undefined || v === '') return null;
        var n = parseMoney(v);
        if (!isFinite(n)) return null;
        return Math.round(n * 100) / 100;
    }

    function normalizeRoasRuleStyle(style) {
        var s = String(style || '').toLowerCase();
        return ROAS_RULE_STYLES[s] ? s : 'red';
    }

    function normalizeRoasRuleSlabs(raw) {
        var list = raw;
        if (typeof raw === 'string') {
            try { list = JSON.parse(raw); } catch (e) { list = null; }
        }
        if (!Array.isArray(list) || !list.length) {
            list = DEFAULT_ROAS_RULE_SLABS;
        }
        var out = [];
        list.forEach(function (item) {
            if (!item || typeof item !== 'object') return;
            var spendMin = toMoneyOrNull(item.spend_min != null ? item.spend_min : item.min);
            var spendMax = toMoneyOrNull(item.spend_max != null ? item.spend_max : item.max);
            var roasMin = toMoneyOrNull(item.roas_min);
            var roasMax = toMoneyOrNull(item.roas_max);
            var targetRoas = toTargetRoasOrNull(item.target_roas);
            if (spendMin === null && spendMax === null && roasMin === null && roasMax === null && targetRoas === null) return;
            if (spendMax !== null && spendMin !== null && spendMax < spendMin) spendMax = spendMin;
            if (roasMax !== null && roasMin !== null && roasMax < roasMin) roasMax = roasMin;
            out.push({
                spend_min: spendMin,
                spend_max: spendMax,
                roas_min: roasMin,
                roas_max: roasMax,
                target_roas: targetRoas,
                style: normalizeRoasRuleStyle(item.style),
            });
        });
        if (!out.length) {
            DEFAULT_ROAS_RULE_SLABS.forEach(function (s) {
                out.push({
                    spend_min: s.spend_min,
                    spend_max: s.spend_max,
                    roas_min: s.roas_min,
                    roas_max: s.roas_max,
                    target_roas: s.target_roas,
                    style: s.style,
                });
            });
        }
        return migrateLegacyRoasRuleSlabs(out);
    }

    function migrateLegacyRoasRuleSlabs(slabs) {
        var first = slabs[0];
        if (first && first.spend_min === 0 && first.spend_max === 0 && first.target_roas === -3) {
            first = Object.assign({}, first, { target_roas: 4 });
            slabs = [first].concat(slabs.slice(1));
        }
        if (!first || first.spend_min !== 0 || first.spend_max !== 5.99) {
            return slabs;
        }
        return [
            { spend_min: 0, spend_max: 0, roas_min: null, roas_max: null, target_roas: 4, style: 'red' },
            {
                spend_min: 0.01,
                spend_max: 5.99,
                roas_min: first.roas_min,
                roas_max: first.roas_max,
                target_roas: 5,
                style: 'yellow',
            },
        ].concat(slabs.slice(1));
    }

    function loadLocalRoasRuleSlabs() {
        try {
            return normalizeRoasRuleSlabs(global.localStorage && localStorage.getItem(ROAS_RULE_STORAGE_KEY));
        } catch (e) {
            return normalizeRoasRuleSlabs(null);
        }
    }

    function inMoneyRange(n, min, max) {
        if (!isFinite(n)) return false;
        if (min === null && max === null) return false;
        if (min !== null && n < min) return false;
        if (max !== null && n > max) return false;
        return true;
    }

    function styleFromRoasRule(style) {
        return ROAS_RULE_STYLES[normalizeRoasRuleStyle(style)] || ROAS_RULE_STYLES.red;
    }

    function applyRoasRuleStyle(cellEl, styleName) {
        if (!cellEl) return;
        var st = styleFromRoasRule(styleName);
        cellEl.style.color = st.color;
        cellEl.style.backgroundColor = st.background || '';
        cellEl.style.fontWeight = st.weight;
    }

    function clearRoasRuleStyle(cellEl) {
        if (!cellEl) return;
        cellEl.style.color = '';
        cellEl.style.backgroundColor = '';
        cellEl.style.fontWeight = '';
    }

    function matchRoasRuleSlab(value, kind) {
        var n = parseMoney(value);
        if (!isFinite(n)) return null;
        var slabs = normalizeRoasRuleSlabs(rules.roasRuleSlabs);
        for (var i = 0; i < slabs.length; i++) {
            var s = slabs[i];
            if (kind === 'spend' && inMoneyRange(n, s.spend_min, s.spend_max)) return s;
            if (kind === 'roas' && inMoneyRange(n, s.roas_min, s.roas_max)) return s;
        }
        return null;
    }

    function targetRoasForSpend(spend) {
        var n = parseMoney(spend);
        if (!isFinite(n)) n = 0;
        var slab = matchRoasRuleSlab(n, 'spend');
        if (slab && slab.target_roas != null) return slab.target_roas;
        return rules.targetRoasBidding;
    }

    function colorSpend1(cellEl, spend) {
        clearRoasRuleStyle(cellEl);
        var slab = matchRoasRuleSlab(spend, 'spend');
        if (slab) applyRoasRuleStyle(cellEl, slab.style);
    }

    function colorRoasRange(cellEl, roas) {
        var slab = matchRoasRuleSlab(roas, 'roas');
        if (!slab) return false;
        applyRoasRuleStyle(cellEl, slab.style);
        return true;
    }

    function isSpendWithZeroRoas(spend, roas) {
        var s = parseMoney(spend);
        var r = parseRoas(roas);
        if (!isFinite(s) || s <= 0) return false;
        if (!isFinite(r)) r = 0;
        return r === 0;
    }

    function spendRoasAlertStyle(spend, roas) {
        var s = parseMoney(spend);
        var r = parseRoas(roas);
        if (!isFinite(s) || s <= 0) return null;
        if (!isFinite(r)) r = 0;
        if (r === 0) return { color: '#111111', background: '#dc3545' };
        if (r >= 0.01 && r < 5) return { color: '#111111', background: '#ffc107' };
        if (r >= 5 && r <= 10) return { color: '#111111', background: '#198754' };
        if (r > 10) return { color: '#111111', background: '#d63384' };
        return null;
    }

    function colorSpendRoasAlert(cellEl, spend, roas) {
        if (!cellEl) return false;
        var st = spendRoasAlertStyle(spend, roas);
        if (!st) return false;
        cellEl.style.color = st.color;
        cellEl.style.backgroundColor = st.background;
        cellEl.style.fontWeight = '700';
        return true;
    }

    function colorSpendWithZeroRoas(cellEl, spend, roas) {
        return colorSpendRoasAlert(cellEl, spend, roas);
    }

    function spendAcosAlertStyle(spend, acos) {
        var s = parseMoney(spend);
        var a = parseRoas(acos);
        if (!isFinite(s) || s <= 0) return null;
        if (!isFinite(a)) a = 0;
        if (a === 0) return { color: '#111111', background: '#dc3545' };
        if (a >= 0.1 && a <= 10) return { color: '#111111', background: '#d63384' };
        if (a > 10 && a <= 20) return { color: '#111111', background: '#198754' };
        if (a > 20) return { color: '#111111', background: '#ffc107' };
        return null;
    }

    function colorSpendAcosAlert(cellEl, spend, acos) {
        if (!cellEl) return false;
        var st = spendAcosAlertStyle(spend, acos);
        if (!st) return false;
        cellEl.style.color = st.color;
        cellEl.style.backgroundColor = st.background;
        cellEl.style.fontWeight = '700';
        return true;
    }

    function displayAcosPercent(acos, spend) {
        var s = parseMoney(spend);
        var a = parseRoas(acos);
        if (!isFinite(a)) a = 0;
        if (isFinite(s) && s > 0 && a === 0) return 100;
        return a;
    }

    function roasRuleSummaryText() {
        var slabs = normalizeRoasRuleSlabs(rules.roasRuleSlabs);
        var parts = slabs.map(function (s) {
            var spend = '';
            if (s.spend_min !== null || s.spend_max !== null) {
                spend = '$' + (s.spend_min != null ? s.spend_min : '0') +
                    (s.spend_max == null ? '+' : ('–$' + s.spend_max));
            }
            var roas = '';
            if (s.roas_min !== null || s.roas_max !== null) {
                roas = ' ROAS ' + (s.roas_min != null ? s.roas_min : '0') +
                    (s.roas_max == null ? '+' : ('–' + s.roas_max));
            }
            return (spend || roas || 'range') + ' ' + s.style;
        });
        return parts.length ? parts.join(' · ') : 'ROAS Rule';
    }

    function bindRoasRuleSummary(el) {
        if (!el) return;
        function paint() {
            var text = roasRuleSummaryText();
            el.title = text;
            var badge = el.closest ? el.closest('#roas-rule-badge') : null;
            if (badge) badge.title = text;
        }
        paint();
        onChange(paint);
    }

    function loadInvZeroPause() {
        try {
            var raw = global.localStorage && localStorage.getItem(INV_ZERO_STORAGE_KEY);
            if (raw === null || raw === undefined || raw === '') return true;
            return raw === '1' || raw === 'true';
        } catch (e) {
            return true;
        }
    }

    var rules = {
        l7ClicksRedBelow: toInt(global.localStorage && localStorage.getItem(CLICKS_STORAGE_KEY), DEFAULT_BELOW),
        targetRoasBidding: toRoas(global.localStorage && localStorage.getItem(ROAS_STORAGE_KEY), DEFAULT_TARGET_ROAS),
        pauseRunSlabs: loadLocalSlabs(),
        pauseRunInvZero: loadInvZeroPause(),
        roasRuleSlabs: loadLocalRoasRuleSlabs(),
        autoPauseCron: true,
    };

    function applyStoragePrefix(prefix) {
        prefix = String(prefix || 'temu_ads');
        CLICKS_STORAGE_KEY = prefix + '_l7_clicks_red_below';
        ROAS_STORAGE_KEY = prefix + '_target_roas_bidding';
        SLABS_STORAGE_KEY = prefix + '_pause_run_slabs';
        INV_ZERO_STORAGE_KEY = prefix + '_pause_run_inv_zero';
        ROAS_RULE_STORAGE_KEY = prefix + '_roas_rule_slabs';
    }

    function reloadRulesFromStorage() {
        rules.l7ClicksRedBelow = toInt(global.localStorage && localStorage.getItem(CLICKS_STORAGE_KEY), DEFAULT_BELOW);
        rules.targetRoasBidding = toRoas(global.localStorage && localStorage.getItem(ROAS_STORAGE_KEY), DEFAULT_TARGET_ROAS);
        rules.pauseRunSlabs = loadLocalSlabs();
        rules.pauseRunInvZero = loadInvZeroPause();
        rules.roasRuleSlabs = loadLocalRoasRuleSlabs();
    }

    function configureChannel(prefix) {
        applyStoragePrefix(prefix);
        reloadRulesFromStorage();
    }

    function persistLocal() {
        try {
            localStorage.setItem(CLICKS_STORAGE_KEY, String(rules.l7ClicksRedBelow));
            localStorage.setItem(ROAS_STORAGE_KEY, String(rules.targetRoasBidding));
            localStorage.setItem(SLABS_STORAGE_KEY, JSON.stringify(rules.pauseRunSlabs));
            localStorage.setItem(INV_ZERO_STORAGE_KEY, rules.pauseRunInvZero ? '1' : '0');
            localStorage.setItem(ROAS_RULE_STORAGE_KEY, JSON.stringify(rules.roasRuleSlabs));
        } catch (e) { /* ignore */ }
    }

    function notify() {
        listeners.forEach(function (fn) {
            try { fn(rules); } catch (e) { /* ignore */ }
        });
    }

    function saveRemote() {
        var url = rules.saveUrl;
        if (!url) return;
        var token = document.querySelector('meta[name="csrf-token"]');
        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
            },
            body: JSON.stringify({
                l7_clicks_red_below: rules.l7ClicksRedBelow,
                target_roas_bidding: rules.targetRoasBidding,
            }),
        }).catch(function () { /* keep local value */ });
    }

    function setL7ClicksRedBelow(n, doSaveRemote) {
        rules.l7ClicksRedBelow = toInt(n, DEFAULT_BELOW);
        persistLocal();
        notify();
        if (doSaveRemote === false) return;
        saveRemote();
    }

    function setTargetRoasBidding(n, doSaveRemote) {
        rules.targetRoasBidding = toRoas(n, DEFAULT_TARGET_ROAS);
        persistLocal();
        notify();
        if (doSaveRemote === false) return;
        saveRemote();
    }

    function setPauseRunSlabs(slabs, doSaveRemote) {
        rules.pauseRunSlabs = normalizePauseRunSlabs(slabs);
        persistLocal();
        notify();
        if (doSaveRemote === false) return;
        savePauseRunSlabsRemote();
    }

    function savePauseRunSlabsRemote() {
        var url = rules.saveUrl;
        if (!url) return;
        var token = document.querySelector('meta[name="csrf-token"]');
        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
            },
            body: JSON.stringify({
                pause_run_slabs: rules.pauseRunSlabs,
                pause_run_inv_zero: !!rules.pauseRunInvZero,
            }),
        }).catch(function () { /* keep local value */ });
    }

    function setRoasRuleSlabs(slabs, doSaveRemote) {
        rules.roasRuleSlabs = normalizeRoasRuleSlabs(slabs);
        persistLocal();
        notify();
        if (doSaveRemote === false) return;
        saveRoasRuleSlabsRemote();
    }

    function saveRoasRuleSlabsRemote() {
        var url = rules.saveUrl;
        if (!url) return;
        var token = document.querySelector('meta[name="csrf-token"]');
        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
            },
            body: JSON.stringify({
                roas_rule_slabs: rules.roasRuleSlabs,
            }),
        }).catch(function () { /* keep local value */ });
    }

    function setPauseRunInvZero(on, doSaveRemote) {
        rules.pauseRunInvZero = !!on;
        persistLocal();
        notify();
        if (doSaveRemote === false) return;
        savePauseRunSlabsRemote();
    }

    function rowInv(row) {
        if (!row) return 0;
        var v = row.inv != null ? row.inv : row.inventory;
        if (v === null || v === undefined || v === '') return 0;
        var n = parseInt(String(v).replace(/,/g, '').trim(), 10);
        return isFinite(n) ? n : 0;
    }

    function actionFromSlabs(clicks) {
        var n = parseClicks(clicks);
        if (!isFinite(n)) n = 0;
        var slabs = normalizePauseRunSlabs(rules.pauseRunSlabs);
        for (var i = 0; i < slabs.length; i++) {
            var s = slabs[i];
            if (n >= s.min && (s.max == null || n <= s.max)) return s.action;
        }
        return n < rules.l7ClicksRedBelow ? 'run' : 'pause';
    }

    function isLowL7Clicks(clicks) {
        var n = parseClicks(clicks);
        if (!isFinite(n)) return false;
        return n < rules.l7ClicksRedBelow;
    }

    function isHighL7Clicks(clicks) {
        var n = parseClicks(clicks);
        if (!isFinite(n)) return false;
        return n >= rules.l7ClicksRedBelow;
    }

    function rowSpendForTRoas(row) {
        if (!row) return 0;
        if (row.spend_l1 != null && row.spend_l1 !== '') return row.spend_l1;
        return row.spend;
    }

    function resolveTargetRoas(targetRoas, row) {
        if (targetRoas != null && targetRoas !== '') {
            var direct = parseRoas(targetRoas);
            if (isFinite(direct)) return direct;
        }
        if (row && row.t_roas != null && row.t_roas !== '') {
            var stored = parseRoas(row.t_roas);
            if (isFinite(stored)) return stored;
        }
        return targetRoasForSpend(rowSpendForTRoas(row));
    }

    function stopAcosPercent(targetRoas, row) {
        var target = resolveTargetRoas(targetRoas, row);
        return target > 0 ? (100 / target) : 12.5;
    }

    function parseDil(v) {
        if (v === null || v === undefined || v === '') return 0;
        if (typeof v === 'number') return isFinite(v) ? v : 0;
        var n = parseFloat(String(v).replace(/%/g, '').replace(/,/g, '').trim());
        return isFinite(n) ? n : 0;
    }

    function dilBand(dil) {
        var n = parseDil(dil);
        if (n < 25) return 'red';
        if (n < 50) return 'green';
        return 'pink';
    }

    function dilColor(dil) {
        return DIL_COLORS[dilBand(dil)] || DIL_COLORS.red;
    }

    function dilHtml(dil) {
        var n = parseDil(dil);
        return '<span style="color:' + dilColor(n) + ';font-weight:600;">' + Math.round(n) + '%</span>';
    }

    function colorL7Clicks(cellEl, clicks) {
        if (!cellEl) return;
        cellEl.style.color = '';
        cellEl.style.fontWeight = '';
        if (isHighL7Clicks(clicks)) {
            cellEl.style.color = RED;
            cellEl.style.fontWeight = '700';
        }
    }

    function formatL7Clicks(cell, clicks) {
        var n = parseClicks(clicks);
        if (!isFinite(n)) n = 0;
        var el = cell && cell.getElement ? cell.getElement() : null;
        colorL7Clicks(el, n);
        return n.toLocaleString();
    }

    function formatRoasBidding(cell, roas) {
        var n = parseRoas(roas);
        if (!isFinite(n)) n = 0;
        return n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function paintPauseThresholdLabels() {
        var els = document.querySelectorAll('[data-temu-l7-pause-threshold]');
        for (var i = 0; i < els.length; i++) {
            els[i].textContent = String(rules.l7ClicksRedBelow);
        }
    }

    function bindThresholdInput(input) {
        if (!input) return;
        input.value = String(rules.l7ClicksRedBelow);
        paintPauseThresholdLabels();
        input.addEventListener('input', function () {
            var n = toInt(input.value, rules.l7ClicksRedBelow);
            var els = document.querySelectorAll('[data-temu-l7-pause-threshold]');
            for (var i = 0; i < els.length; i++) {
                els[i].textContent = String(n);
            }
        });
        onChange(function () {
            if (document.activeElement !== input) {
                input.value = String(rules.l7ClicksRedBelow);
                paintPauseThresholdLabels();
            }
        });
    }

    function bindSaveRuleButton(btn, statusEl) {
        if (!btn) return;
        btn.addEventListener('click', function () {
            var input = document.getElementById('temu-l7-clicks-red-threshold');
            if (!input) return;
            var prev = rules.l7ClicksRedBelow;
            setL7ClicksRedBelow(input.value, true);
            input.value = String(rules.l7ClicksRedBelow);
            paintPauseThresholdLabels();
            if (statusEl) {
                statusEl.style.display = 'block';
                var changed = prev !== rules.l7ClicksRedBelow;
                statusEl.innerHTML = changed
                    ? '<div class="alert alert-success py-2 mb-0">Saved L7 clicks threshold: ' + rules.l7ClicksRedBelow + '.</div>'
                    : '<div class="alert alert-info py-2 mb-0">No change to save.</div>';
            }
        });
    }

    function bindTargetRoasInput(input) {
        if (!input) return;
        input.value = String(rules.targetRoasBidding);
        input.addEventListener('change', function () {
            setTargetRoasBidding(input.value, true);
            input.value = String(rules.targetRoasBidding);
        });
        onChange(function () {
            if (document.activeElement !== input) {
                input.value = String(rules.targetRoasBidding);
            }
        });
    }

    function computedPauseRunAction(row) {
        if (rules.pauseRunInvZero && rowInv(row) <= 0) {
            return 'pause';
        }
        return actionFromSlabs(row && row.clicks_l7 != null ? row.clicks_l7 : 0);
    }

    function pauseRunAction(row) {
        return computedPauseRunAction(row);
    }

    function pauseRunButtonHtml(row) {
        var action = pauseRunAction(row || {});
        var goodsId = String((row && row.goods_id) || '');
        return '<button type="button" class="temu-pause-run-btn is-' + action + '" data-goods-id="' + goodsId +
            '" data-action="' + action + '" title="' + (action === 'pause' ? 'Pause' : 'Run') + ' — click to ' +
            (action === 'pause' ? 'run' : 'pause') + ' this ad on Temu">' +
            '<span class="temu-pause-run-knob"></span></button>';
    }

    function pushPauseRun(btn, cell, toggleUrl) {
        if (!btn || btn.disabled) return;
        var goodsId = btn.getAttribute('data-goods-id') || '';
        var current = btn.getAttribute('data-action') || 'pause';
        var next = current === 'pause' ? 'run' : 'pause';
        if (!goodsId) return;
        btn.disabled = true;
        var token = document.querySelector('meta[name="csrf-token"]');
        fetch(toggleUrl || '/temu/ads/toggle', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
            },
            body: JSON.stringify({ goods_id: goodsId, action: next }),
        })
            .then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
            .then(function (res) {
                var ok = !!(res.ok && res.data && res.data.success);
                var reason = (res.data && res.data.message) ? String(res.data.message) : 'Temu toggle failed';
                if (cell && cell.getRow) {
                    var patch = {
                        pause_run_ok: ok,
                        pause_run_error: ok ? '' : reason,
                    };
                    if (res.data && res.data.pause_run_history) {
                        patch.pause_run_history = res.data.pause_run_history;
                    }
                    if (ok) {
                        patch.pause_run = next;
                        if (res.data.ad_status) patch.ad_status = res.data.ad_status;
                    }
                    cell.getRow().update(patch);
                }
            })
            .catch(function () {
                if (cell && cell.getRow) {
                    cell.getRow().update({
                        pause_run_ok: false,
                        pause_run_error: 'Temu toggle failed',
                    });
                }
            })
            .then(function () {
                btn.disabled = false;
            });
    }

    function escapeAttr(s) {
        return String(s || '')
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function pauseRunHistoryTitle(row) {
        var hist = row && Array.isArray(row.pause_run_history) ? row.pause_run_history : [];
        if (!hist.length) {
            if (row && row.pause_run_error) return String(row.pause_run_error);
            if (row && row.pause_run_ok) return 'Success';
            return '';
        }
        return hist.slice(0, 12).map(function (h) {
            var when = h && h.at ? String(h.at) : '';
            var act = h && h.action ? String(h.action) : '';
            var state = h && h.ok ? 'OK' : 'Fail';
            var msg = h && h.message ? String(h.message) : '';
            return [when, act, state].filter(Boolean).join(' ') + (msg ? ' — ' + msg : '');
        }).join('\n');
    }

    function pauseRunResultHtml(row) {
        if (!row) return '';
        var hist = Array.isArray(row.pause_run_history) ? row.pause_run_history : [];
        if (row.pause_run_ok == null && !hist.length) return '';
        var title = pauseRunHistoryTitle(row);
        var n = hist.length > 1 ? '<span class="temu-pause-run-hist-n">' + hist.length + '</span>' : '';
        if (row.pause_run_ok) {
            return '<span class="temu-pause-run-ok" title="' + escapeAttr(title || 'Success') + '"><i class="fas fa-check"></i>' + n + '</span>';
        }
        return '<span class="temu-pause-run-fail" title="' + escapeAttr(title || 'Failed') + '"><i class="fas fa-times"></i>' + n + '</span>';
    }

    function runAutoPauseCron(onDone) {
        var token = document.querySelector('meta[name="csrf-token"]');
        return fetch(rules.pauseUrl || '/temu/ads/auto-pause', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
            },
            body: JSON.stringify({}),
        })
            .then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
            .then(function (res) {
                if (typeof onDone === 'function') onDone(res);
                return res;
            })
            .catch(function () {
                var res = { ok: false, data: { success: false, message: 'Temu Pause/Run update failed' } };
                if (typeof onDone === 'function') onDone(res);
                return res;
            });
    }

    function paintCronToggle(btn, statusEl, enabled) {
        if (btn) {
            btn.dataset.enabled = enabled ? '1' : '0';
            btn.innerHTML = '<i class="fas fa-bolt me-1"></i>Auto Cron';
            if (enabled) {
                btn.className = 'btn btn-sm btn-warning';
                btn.title = 'Auto cron is ON. Only rows whose Active/Pause status changes from the click limit are pushed. Click to turn off.';
            } else {
                btn.className = 'btn btn-sm btn-outline-secondary';
                btn.title = 'Auto cron is OFF. Click to turn on.';
            }
        }
        if (statusEl) {
            statusEl.textContent = enabled
                ? 'Daily cron: ON — only rows whose Active/Pause status changes from the click limit, after L7 fetch and at 16:10 IST.'
                : 'Daily cron: OFF — scheduled auto cron will not run.';
            statusEl.className = enabled ? 'small mt-2 text-success' : 'small mt-2 text-danger';
        }
    }

    function bindCronToggleButton(btn, statusEl) {
        if (!btn) return;
        paintCronToggle(btn, statusEl, rules.autoPauseCron !== false);
        btn.addEventListener('click', function () {
            var next = btn.dataset.enabled !== '1';
            var label = next ? 'turn ON Auto Cron' : 'turn OFF Auto Cron';
            if (!confirm('Auto Cron?\n\nThis will ' + label + '.')) {
                return;
            }
            btn.disabled = true;
            var token = document.querySelector('meta[name="csrf-token"]');
            fetch(rules.cronUrl || '/temu/ads/auto-pause-cron', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
                },
                body: JSON.stringify({ enabled: next }),
            })
                .then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
                .then(function (res) {
                    var enabled = !!(res.data && res.data.enabled);
                    if (res.ok && res.data && res.data.success) {
                        rules.autoPauseCron = enabled;
                        paintCronToggle(btn, statusEl, enabled);
                    } else {
                        alert((res.data && res.data.message) ? res.data.message : 'Could not update cron.');
                    }
                })
                .catch(function () {
                    alert('Could not update cron.');
                })
                .then(function () {
                    btn.disabled = false;
                });
        });
    }

    function bindAutoPauseButton(btn, statusEl, onDone) {
        if (!btn) return;
        btn.addEventListener('click', function () {
            if (!confirm('Pause all Active Temu ads that match the L7 click limit?\n\nL7 clicks at or above the threshold will be set Inactive on Temu.')) {
                return;
            }
            btn.disabled = true;
            if (statusEl) {
                statusEl.style.display = 'block';
                statusEl.innerHTML = '<div class="alert alert-info py-2 mb-0"><i class="fas fa-spinner fa-spin me-1"></i> Pausing matching ads…</div>';
            }
            var token = document.querySelector('meta[name="csrf-token"]');
            fetch(rules.pauseUrl || '/temu/ads/auto-pause', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
                },
                body: JSON.stringify({}),
            })
                .then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
                .then(function (res) {
                    var msg = (res.data && res.data.message) ? res.data.message : (res.ok ? 'Done' : 'Pause failed');
                    if (statusEl) {
                        statusEl.innerHTML = '<div class="alert ' + (res.ok && res.data && res.data.success ? 'alert-success' : 'alert-danger') + ' py-2 mb-0">' + msg + '</div>';
                    }
                    if (typeof onDone === 'function') onDone(res.data || {});
                })
                .catch(function () {
                    if (statusEl) {
                        statusEl.innerHTML = '<div class="alert alert-danger py-2 mb-0">Pause failed</div>';
                    }
                })
                .then(function () {
                    btn.disabled = false;
                });
        });
    }

    function pushRoasRule(items, opts) {
        opts = opts || {};
        var url = opts.url || rules.pushRoasUrl || '/temu/ads/push-roas';
        var token = document.querySelector('meta[name="csrf-token"]');
        var body = {
            items: Array.isArray(items) ? items : [],
        };
        if (opts.slabs) body.roas_rule_slabs = opts.slabs;
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
            },
            body: JSON.stringify(body),
        }).then(function (r) {
            return r.json().then(function (data) { return { ok: r.ok, data: data }; });
        });
    }

    function onChange(fn) {
        if (typeof fn === 'function') listeners.push(fn);
    }

    function loadFromServer(url) {
        if (!url) return;
        fetch(url, { headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data) return;
                if (data.l7_clicks_red_below != null) {
                    setL7ClicksRedBelow(data.l7_clicks_red_below, false);
                }
                if (data.target_roas_bidding != null) {
                    setTargetRoasBidding(data.target_roas_bidding, false);
                }
                if (data.pause_run_slabs != null) {
                    setPauseRunSlabs(data.pause_run_slabs, false);
                }
                if (data.pause_run_inv_zero != null) {
                    setPauseRunInvZero(!!data.pause_run_inv_zero, false);
                }
                if (data.roas_rule_slabs != null) {
                    setRoasRuleSlabs(data.roas_rule_slabs, false);
                }
                if (data.auto_pause_cron != null) {
                    rules.autoPauseCron = !!data.auto_pause_cron;
                    paintCronToggle(
                        document.getElementById('temu-ads-cron-toggle-btn'),
                        document.getElementById('temu-ads-cron-status'),
                        rules.autoPauseCron
                    );
                }
            })
            .catch(function () { /* keep local */ });
    }

    global.TemuAdsColorRules = {
        DEFAULT_BELOW: DEFAULT_BELOW,
        DEFAULT_TARGET_ROAS: DEFAULT_TARGET_ROAS,
        RED: RED,
        BIDDING_COLOR: BIDDING_COLOR,
        rules: rules,
        getL7ClicksRedBelow: function () { return rules.l7ClicksRedBelow; },
        setL7ClicksRedBelow: setL7ClicksRedBelow,
        getTargetRoasBidding: function () { return rules.targetRoasBidding; },
        setTargetRoasBidding: setTargetRoasBidding,
        isLowL7Clicks: isLowL7Clicks,
        isHighL7Clicks: isHighL7Clicks,
        stopAcosPercent: stopAcosPercent,
        dilBand: dilBand,
        dilColor: dilColor,
        dilHtml: dilHtml,
        colorL7Clicks: colorL7Clicks,
        formatL7Clicks: formatL7Clicks,
        formatRoasBidding: formatRoasBidding,
        bindThresholdInput: bindThresholdInput,
        bindSaveRuleButton: bindSaveRuleButton,
        bindTargetRoasInput: bindTargetRoasInput,
        bindAutoPauseButton: bindAutoPauseButton,
        bindCronToggleButton: bindCronToggleButton,
        paintCronToggle: paintCronToggle,
        pauseRunAction: pauseRunAction,
        computedPauseRunAction: computedPauseRunAction,
        actionFromSlabs: actionFromSlabs,
        getPauseRunSlabs: function () { return normalizePauseRunSlabs(rules.pauseRunSlabs); },
        setPauseRunSlabs: setPauseRunSlabs,
        getPauseRunInvZero: function () { return !!rules.pauseRunInvZero; },
        setPauseRunInvZero: setPauseRunInvZero,
        normalizePauseRunSlabs: normalizePauseRunSlabs,
        DEFAULT_PAUSE_RUN_SLABS: DEFAULT_PAUSE_RUN_SLABS,
        getRoasRuleSlabs: function () { return normalizeRoasRuleSlabs(rules.roasRuleSlabs); },
        setRoasRuleSlabs: setRoasRuleSlabs,
        normalizeRoasRuleSlabs: normalizeRoasRuleSlabs,
        DEFAULT_ROAS_RULE_SLABS: DEFAULT_ROAS_RULE_SLABS,
        colorSpend1: colorSpend1,
        targetRoasForSpend: targetRoasForSpend,
        colorRoasRange: colorRoasRange,
        colorSpendRoasAlert: colorSpendRoasAlert,
        colorSpendWithZeroRoas: colorSpendWithZeroRoas,
        isSpendWithZeroRoas: isSpendWithZeroRoas,
        colorSpendAcosAlert: colorSpendAcosAlert,
        displayAcosPercent: displayAcosPercent,
        roasRuleSummaryText: roasRuleSummaryText,
        bindRoasRuleSummary: bindRoasRuleSummary,
        pauseRunButtonHtml: pauseRunButtonHtml,
        pauseRunResultHtml: pauseRunResultHtml,
        pauseRunHistoryTitle: pauseRunHistoryTitle,
        pushPauseRun: pushPauseRun,
        runAutoPauseCron: runAutoPauseCron,
        pushRoasRule: pushRoasRule,
        onChange: onChange,
        loadFromServer: loadFromServer,
        configureChannel: configureChannel,
        setUrls: function (getUrl, saveUrl, pauseUrl, toggleUrl, cronUrl, pushRoasUrl) {
            rules.getUrl = getUrl;
            rules.saveUrl = saveUrl;
            if (pauseUrl) rules.pauseUrl = pauseUrl;
            if (toggleUrl) rules.toggleUrl = toggleUrl;
            if (cronUrl) rules.cronUrl = cronUrl;
            if (pushRoasUrl) rules.pushRoasUrl = pushRoasUrl;
            loadFromServer(getUrl);
        },
    };
})(window);
