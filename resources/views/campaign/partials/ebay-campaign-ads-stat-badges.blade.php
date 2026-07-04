@php
    $badgePrefix = $badgePrefix ?? 'eca';
    $badgesUrl = $badgesUrl ?? '';
    $storeSalesTitle = $storeSalesTitle ?? 'eBay L30 store sales';
@endphp

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2">
        <div class="d-flex flex-wrap align-items-center gap-2 overflow-x-auto py-1">
            <span class="ebay-ca-stat-badge ebay-ca-stat-badge--spend">SPEND: <span id="{{ $badgePrefix }}-badge-spend">$0</span></span>
            <span class="ebay-ca-stat-badge ebay-ca-stat-badge--clicks">CLICKS: <span id="{{ $badgePrefix }}-badge-clicks">0</span></span>
            <span class="ebay-ca-stat-badge ebay-ca-stat-badge--sold">SOLD: <span id="{{ $badgePrefix }}-badge-sold">0</span></span>
            <span class="ebay-ca-stat-badge ebay-ca-stat-badge--sales">ADS SALES: <span id="{{ $badgePrefix }}-badge-sales">$0</span></span>
            <span class="ebay-ca-stat-badge ebay-ca-stat-badge--cvr">CVR: <span id="{{ $badgePrefix }}-badge-cvr">0%</span></span>
            <span class="ebay-ca-stat-badge ebay-ca-stat-badge--acos">ACOS: <span id="{{ $badgePrefix }}-badge-acos">0%</span></span>
            <span class="ebay-ca-stat-badge ebay-ca-stat-badge--tcos">TCOS: <span id="{{ $badgePrefix }}-badge-tcos">0%</span></span>
            <span class="ebay-ca-stat-badge ebay-ca-stat-badge--ssales" title="{{ $storeSalesTitle }}">S SALES: <span id="{{ $badgePrefix }}-badge-ssales">$0</span></span>
        </div>
    </div>
</div>

<style>
    .ebay-ca-stat-badge {
        display: inline-block;
        flex-shrink: 0;
        color: #fff;
        font-size: 12px;
        font-weight: 700;
        padding: 6px 12px;
        border-radius: 6px;
        white-space: nowrap;
        line-height: 1.2;
    }
    .ebay-ca-stat-badge--spend  { background: #ef4444; }
    .ebay-ca-stat-badge--clicks { background: #4c7ed8; }
    .ebay-ca-stat-badge--sold   { background: #f59e0b; }
    .ebay-ca-stat-badge--sales  { background: #16a34a; }
    .ebay-ca-stat-badge--cvr    { background: #db2777; }
    .ebay-ca-stat-badge--acos   { background: #ea580c; }
    .ebay-ca-stat-badge--tcos   { background: #7c3aed; }
    .ebay-ca-stat-badge--ssales { background: #0d9488; }
</style>

<script>
    window.loadEbayCampaignAdsStatBadges = window.loadEbayCampaignAdsStatBadges || function (url, prefix) {
        if (!url || !prefix) return;

        $.get(url)
            .done(function (m) {
                if (!m) return;
                const spend = Number(m.spend || 0);
                const clicks = Number(m.clicks || 0);
                const sold = Number(m.sold || 0);
                const sales = Number(m.sales || 0);
                const cvr = Number(m.cvr || 0);
                const acos = Number(m.acos || 0);
                const tcos = Number(m.tcos || 0);
                const netSales = Number(m.net_sales || 0);

                $('#' + prefix + '-badge-spend').text('$' + Math.round(spend).toLocaleString());
                $('#' + prefix + '-badge-clicks').text(Math.round(clicks).toLocaleString());
                $('#' + prefix + '-badge-sold').text(Math.round(sold).toLocaleString());
                $('#' + prefix + '-badge-sales').text('$' + Math.round(sales).toLocaleString());
                $('#' + prefix + '-badge-cvr').text(cvr.toFixed(1) + '%');
                $('#' + prefix + '-badge-acos').text(Math.round(acos) + '%');
                $('#' + prefix + '-badge-tcos').text(Math.round(tcos) + '%');
                $('#' + prefix + '-badge-ssales').text('$' + netSales.toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                }));
            })
            .fail(function () {
                $('#' + prefix + '-badge-spend').text('—');
            });
    };
</script>
