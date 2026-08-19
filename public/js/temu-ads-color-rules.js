/**
 * Shared Temu ads dynamic rules for /temu/ads and /temu-decrease.
 * 1) Last 7 days clicks < 70 → red. Threshold is editable and persisted.
 * 2) Budget and Bidding Target ROAS used to stop/tighten ads. Default 8.
 */
(function (global) {
    'use strict';

    var CLICKS_STORAGE_KEY = 'temu_ads_l7_clicks_red_below';
    var ROAS_STORAGE_KEY = 'temu_ads_target_roas_bidding';
    var DEFAULT_BELOW = 70;
    var DEFAULT_TARGET_ROAS = 8;
    var RED = '#a00211';
    var BIDDING_COLOR = '#0d6efd';
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

    var rules = {
        l7ClicksRedBelow: toInt(global.localStorage && localStorage.getItem(CLICKS_STORAGE_KEY), DEFAULT_BELOW),
        targetRoasBidding: toRoas(global.localStorage && localStorage.getItem(ROAS_STORAGE_KEY), DEFAULT_TARGET_ROAS),
    };

    function persistLocal() {
        try {
            localStorage.setItem(CLICKS_STORAGE_KEY, String(rules.l7ClicksRedBelow));
            localStorage.setItem(ROAS_STORAGE_KEY, String(rules.targetRoasBidding));
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

    function isLowL7Clicks(clicks) {
        var n = parseClicks(clicks);
        if (!isFinite(n)) return false;
        return n < rules.l7ClicksRedBelow;
    }

    function isBelowTargetRoas(roas, clicks) {
        if (clicks !== undefined && !isLowL7Clicks(clicks)) return false;
        var n = parseRoas(roas);
        if (!isFinite(n) || n <= 0) return false;
        return n < rules.targetRoasBidding;
    }

    function stopAcosPercent() {
        var target = rules.targetRoasBidding;
        return target > 0 ? (100 / target) : 12.5;
    }

    function isAboveStopAcos(acosPct, clicks) {
        if (clicks !== undefined && !isLowL7Clicks(clicks)) return false;
        var n = parseRoas(acosPct);
        if (!isFinite(n) || n <= 0) return false;
        return n > stopAcosPercent();
    }

    function colorL7Clicks(cellEl, clicks) {
        if (!cellEl) return;
        cellEl.style.color = '';
        cellEl.style.fontWeight = '';
        if (isLowL7Clicks(clicks)) {
            cellEl.style.color = RED;
            cellEl.style.fontWeight = '700';
        }
    }

    function colorRoasBidding(cellEl, roas, clicks) {
        if (!cellEl) return;
        cellEl.style.color = '';
        cellEl.style.fontWeight = '';
        if (isBelowTargetRoas(roas, clicks)) {
            cellEl.style.color = BIDDING_COLOR;
            cellEl.style.fontWeight = '700';
        }
    }

    function colorAcosBidding(cellEl, acosPct, clicks) {
        if (!cellEl) return;
        cellEl.style.color = '';
        cellEl.style.fontWeight = '';
        if (isAboveStopAcos(acosPct, clicks)) {
            cellEl.style.color = BIDDING_COLOR;
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

    function rowL7Clicks(cell) {
        if (!cell || !cell.getRow) return undefined;
        var data = cell.getRow().getData() || {};
        if (data.clicks_l7 != null && data.clicks_l7 !== '') return data.clicks_l7;
        if (data.t_clicks_l7 != null && data.t_clicks_l7 !== '') return data.t_clicks_l7;
        if (data.period === 'L7' && data.clicks != null) return data.clicks;
        if (data.ad_clicks != null) return data.ad_clicks;
        return data.clicks;
    }

    function formatRoasBidding(cell, roas) {
        var n = parseRoas(roas);
        if (!isFinite(n)) n = 0;
        var el = cell && cell.getElement ? cell.getElement() : null;
        colorRoasBidding(el, n, rowL7Clicks(cell));
        return n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function bindThresholdInput(input) {
        if (!input) return;
        input.value = String(rules.l7ClicksRedBelow);
        input.addEventListener('change', function () {
            setL7ClicksRedBelow(input.value, true);
            input.value = String(rules.l7ClicksRedBelow);
        });
        onChange(function () {
            if (document.activeElement !== input) {
                input.value = String(rules.l7ClicksRedBelow);
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

    function ruleSummaryText() {
        return 'L7 < ' + rules.l7ClicksRedBelow + ' → ROAS ' + rules.targetRoasBidding;
    }

    function bindRuleSummary(el) {
        if (!el) return;
        function paint() {
            el.textContent = ruleSummaryText();
        }
        paint();
        onChange(paint);
    }

    function bindAutoPauseButton(btn, statusEl, onDone) {
        if (!btn) return;
        btn.addEventListener('click', function () {
            var rule = ruleSummaryText();
            if (!confirm('Pause all Active Temu ads that match ' + rule + '?\n\nL7 clicks below the threshold and ROAS below Stop ROAS will be set Inactive on Temu.')) {
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
        isBelowTargetRoas: isBelowTargetRoas,
        isAboveStopAcos: isAboveStopAcos,
        stopAcosPercent: stopAcosPercent,
        colorL7Clicks: colorL7Clicks,
        colorRoasBidding: colorRoasBidding,
        colorAcosBidding: colorAcosBidding,
        formatL7Clicks: formatL7Clicks,
        formatRoasBidding: formatRoasBidding,
        bindThresholdInput: bindThresholdInput,
        bindTargetRoasInput: bindTargetRoasInput,
        bindRuleSummary: bindRuleSummary,
        bindAutoPauseButton: bindAutoPauseButton,
        ruleSummaryText: ruleSummaryText,
        onChange: onChange,
        loadFromServer: loadFromServer,
        setUrls: function (getUrl, saveUrl, pauseUrl) {
            rules.getUrl = getUrl;
            rules.saveUrl = saveUrl;
            if (pauseUrl) rules.pauseUrl = pauseUrl;
            loadFromServer(getUrl);
        },
    };
})(window);
