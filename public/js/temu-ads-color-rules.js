/**
 * Shared Temu ads coloring rules for /temu/ads and /temu-decrease.
 * Default: Last 7 days clicks < 70 → red. Threshold is editable and persisted.
 */
(function (global) {
    'use strict';

    var STORAGE_KEY = 'temu_ads_l7_clicks_red_below';
    var DEFAULT_BELOW = 70;
    var RED = '#a00211';
    var listeners = [];

    function parseClicks(v) {
        if (v === null || v === undefined || v === '') return NaN;
        if (typeof v === 'number') return isFinite(v) ? v : NaN;
        var n = parseInt(String(v).replace(/,/g, '').trim(), 10);
        return isFinite(n) ? n : NaN;
    }

    function toInt(v, fallback) {
        var n = parseClicks(v);
        return isFinite(n) && n >= 0 ? n : fallback;
    }

    var rules = {
        l7ClicksRedBelow: toInt(global.localStorage && localStorage.getItem(STORAGE_KEY), DEFAULT_BELOW),
    };

    function persistLocal(n) {
        try {
            localStorage.setItem(STORAGE_KEY, String(n));
        } catch (e) { /* ignore */ }
    }

    function notify() {
        listeners.forEach(function (fn) {
            try { fn(rules); } catch (e) { /* ignore */ }
        });
    }

    function setL7ClicksRedBelow(n, saveRemote) {
        rules.l7ClicksRedBelow = toInt(n, DEFAULT_BELOW);
        persistLocal(rules.l7ClicksRedBelow);
        notify();
        if (saveRemote === false) return;
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
            body: JSON.stringify({ l7_clicks_red_below: rules.l7ClicksRedBelow }),
        }).catch(function () { /* keep local value */ });
    }

    function isLowL7Clicks(clicks) {
        var n = parseClicks(clicks);
        if (!isFinite(n)) return false;
        return n < rules.l7ClicksRedBelow;
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

    function formatL7Clicks(cell, clicks) {
        var n = parseClicks(clicks);
        if (!isFinite(n)) n = 0;
        var el = cell && cell.getElement ? cell.getElement() : null;
        colorL7Clicks(el, n);
        return n.toLocaleString();
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

    function onChange(fn) {
        if (typeof fn === 'function') listeners.push(fn);
    }

    function loadFromServer(url) {
        if (!url) return;
        fetch(url, { headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && data.l7_clicks_red_below != null) {
                    setL7ClicksRedBelow(data.l7_clicks_red_below, false);
                }
            })
            .catch(function () { /* keep local */ });
    }

    global.TemuAdsColorRules = {
        DEFAULT_BELOW: DEFAULT_BELOW,
        RED: RED,
        rules: rules,
        getL7ClicksRedBelow: function () { return rules.l7ClicksRedBelow; },
        setL7ClicksRedBelow: setL7ClicksRedBelow,
        isLowL7Clicks: isLowL7Clicks,
        colorL7Clicks: colorL7Clicks,
        formatL7Clicks: formatL7Clicks,
        bindThresholdInput: bindThresholdInput,
        onChange: onChange,
        loadFromServer: loadFromServer,
        setUrls: function (getUrl, saveUrl) {
            rules.getUrl = getUrl;
            rules.saveUrl = saveUrl;
            loadFromServer(getUrl);
        },
    };
})(window);
