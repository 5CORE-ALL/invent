/**
 * Shared Temu ads dynamic rules for /temu/ads and /temu-decrease.
 * 1) Last 7 days clicks < 70 → red. Threshold is editable and persisted.
 * 2) Budget and Bidding Target ROAS used to stop/tighten ads. Default 8.
 */
(function (global) {
    'use strict';

    var CLICKS_STORAGE_KEY = 'temu_ads_l7_clicks_red_below';
    var ROAS_STORAGE_KEY = 'temu_ads_target_roas_bidding';
    var SLABS_STORAGE_KEY = 'temu_ads_pause_run_slabs';
    var INV_ZERO_STORAGE_KEY = 'temu_ads_pause_run_inv_zero';
    var DEFAULT_BELOW = 70;
    var DEFAULT_TARGET_ROAS = 8;
    var DEFAULT_PAUSE_RUN_SLABS = [
        { min: 0, max: 69, action: 'run' },
        { min: 70, max: null, action: 'pause' },
    ];
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
        autoPauseCron: true,
    };

    function persistLocal() {
        try {
            localStorage.setItem(CLICKS_STORAGE_KEY, String(rules.l7ClicksRedBelow));
            localStorage.setItem(ROAS_STORAGE_KEY, String(rules.targetRoasBidding));
            localStorage.setItem(SLABS_STORAGE_KEY, JSON.stringify(rules.pauseRunSlabs));
            localStorage.setItem(INV_ZERO_STORAGE_KEY, rules.pauseRunInvZero ? '1' : '0');
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

    function computedPauseRunAction(row) {
        if (rowInv(row) <= 0) {
            return 'pause';
        }
        return actionFromSlabs(row && row.clicks_l7 != null ? row.clicks_l7 : 0);
    }

    function pauseRunAction(row) {
        if (rowInv(row) <= 0) {
            return 'pause';
        }
        if (row && (row.pause_run === 'pause' || row.pause_run === 'run')) {
            return row.pause_run;
        }
        return actionFromSlabs(row && row.clicks_l7 != null ? row.clicks_l7 : 0);
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

    function pauseRunResultHtml(row) {
        if (!row || row.pause_run_ok == null) return '';
        if (row.pause_run_ok) {
            return '<span class="temu-pause-run-ok" title="Success"><i class="fas fa-check"></i></span>';
        }
        return '<span class="temu-pause-run-fail" title="' + escapeAttr(row.pause_run_error || 'Failed') + '"><i class="fas fa-times"></i></span>';
    }

    function paintCronToggle(btn, statusEl, enabled) {
        if (btn) {
            btn.dataset.enabled = enabled ? '1' : '0';
            if (enabled) {
                btn.className = 'btn btn-sm btn-warning';
                btn.innerHTML = '<i class="fas fa-pause me-1"></i>Pause Cron';
                btn.title = 'Daily auto-pause cron is ON. Click to pause it.';
            } else {
                btn.className = 'btn btn-sm btn-success';
                btn.innerHTML = '<i class="fas fa-play me-1"></i>Run Cron Daily';
                btn.title = 'Daily auto-pause cron is PAUSED. Click to run it daily.';
            }
        }
        if (statusEl) {
            statusEl.textContent = enabled
                ? 'Daily cron: ON — auto-pause after L7 fetch and at 16:10 IST.'
                : 'Daily cron: PAUSED — scheduled auto-pause will not run.';
            statusEl.className = enabled ? 'small mt-2 text-success' : 'small mt-2 text-danger';
        }
    }

    function bindCronToggleButton(btn, statusEl) {
        if (!btn) return;
        paintCronToggle(btn, statusEl, rules.autoPauseCron !== false);
        btn.addEventListener('click', function () {
            var next = btn.dataset.enabled !== '1';
            var label = next ? 'turn ON daily auto-pause cron' : 'PAUSE daily auto-pause cron';
            if (!confirm((next ? 'Run Cron Daily' : 'Pause Cron') + '?\n\nThis will ' + label + '.')) {
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
                if (data.pause_run_slabs != null) {
                    setPauseRunSlabs(data.pause_run_slabs, false);
                }
                if (data.pause_run_inv_zero != null) {
                    setPauseRunInvZero(!!data.pause_run_inv_zero, false);
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
        pauseRunButtonHtml: pauseRunButtonHtml,
        pauseRunResultHtml: pauseRunResultHtml,
        pushPauseRun: pushPauseRun,
        ruleSummaryText: ruleSummaryText,
        onChange: onChange,
        loadFromServer: loadFromServer,
        setUrls: function (getUrl, saveUrl, pauseUrl, toggleUrl, cronUrl) {
            rules.getUrl = getUrl;
            rules.saveUrl = saveUrl;
            if (pauseUrl) rules.pauseUrl = pauseUrl;
            if (toggleUrl) rules.toggleUrl = toggleUrl;
            if (cronUrl) rules.cronUrl = cronUrl;
            loadFromServer(getUrl);
        },
    };
})(window);
