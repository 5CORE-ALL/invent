@php
    $marketplaceApiConfigured = $marketplaceApiConfigured
        ?? app(\App\Services\Support\MarketplaceApiConfigService::class)->configuredMap();
@endphp
<style>
    .bp-mp-stack--no-api,
    .marketplace-btn--no-api {
        cursor: not-allowed;
        opacity: .45;
        filter: grayscale(.7);
    }
    .bp-mp-stack--no-api:hover .marketplace-btn {
        transform: none;
        box-shadow: none;
    }
</style>
<script>
(function () {
    const MAP = @json($marketplaceApiConfigured);

    function normalizeChannelKey(name) {
        if (!name) return '';
        let key = String(name).toLowerCase().trim().replace(/\s+/g, ' ');
        const aliases = { 'tiktok shop 2': 'tiktok 2', 'depop.com': 'depop' };
        if (aliases[key]) key = aliases[key];
        return key.replace(/[\s\-&/]+/g, '');
    }

    function lookupConfigured(mp) {
        if (!mp) return false;
        const raw = String(mp);
        const candidates = [raw, raw.toLowerCase(), normalizeChannelKey(raw)];
        for (let i = 0; i < candidates.length; i++) {
            const c = candidates[i];
            if (Object.prototype.hasOwnProperty.call(MAP, c)) {
                return !!MAP[c];
            }
        }
        return false;
    }

    window.isMarketplaceApiConfigured = function (mp) {
        return lookupConfigured(mp);
    };

    window.alertMarketplaceApiNotConfigured = function (mp, label) {
        const name = label || mp || 'Marketplace';
        alert(name + ' API is not configured.');
    };

    window.marketplaceApiGuard = function (mp, label) {
        if (lookupConfigured(mp)) {
            return true;
        }
        window.alertMarketplaceApiNotConfigured(mp, label);
        return false;
    };

    window.filterConfiguredMarketplaces = function (list) {
        return (list || []).filter(function (mp) { return lookupConfigured(mp); });
    };

    window.mpPushTileState = function (mp, opts) {
        opts = opts || {};
        const label = opts.label || mp || 'Marketplace';
        const implemented = opts.implemented !== false;
        const configured = lookupConfigured(mp);
        let title = label;
        if (!implemented) {
            title = label + ' push is not implemented yet';
        } else if (!configured) {
            title = label + '. API not configured.';
        } else if (opts.extraTitle) {
            title = opts.extraTitle;
        } else if (opts.statusHint) {
            title = label + '. ' + opts.statusHint + '. Click to push.';
        }
        return {
            configured: configured,
            implemented: implemented,
            disabled: !implemented || !configured,
            noApiClass: (implemented && configured) ? '' : ' bp-mp-stack--no-api',
            title: title,
        };
    };
})();
</script>
