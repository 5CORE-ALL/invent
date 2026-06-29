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
})();
</script>
