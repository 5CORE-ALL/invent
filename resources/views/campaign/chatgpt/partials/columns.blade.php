{{-- ChatGPT Ads: L30 delivery columns (OpenAI Advertiser API). Goes inside the `columns: [...]` of partials/ads-tabulator-page. --}}
{
    title: 'Impr L30',
    field: 'gpt_impressions',
    hozAlign: 'center',
    width: 75,
    sorter: 'number',
    sorterParams: { alignEmptyValues: 'bottom' },
    headerTooltip: 'ChatGPT ad impressions, last 30 days (product feed item_id = SKU).',
    formatter: function(cell) {
        const v = cell.getValue();
        return (v === null || v === undefined) ? '<span style="color:#6c757d;">-</span>' : Math.round(adsNum(v)).toLocaleString();
    }
},
{
    title: 'Clicks L30',
    field: 'gpt_clicks',
    hozAlign: 'center',
    width: 75,
    sorter: 'number',
    sorterParams: { alignEmptyValues: 'bottom' },
    headerTooltip: 'ChatGPT ad clicks, last 30 days.',
    formatter: function(cell) {
        const v = cell.getValue();
        return (v === null || v === undefined) ? '<span style="color:#6c757d;">-</span>' : Math.round(adsNum(v)).toLocaleString();
    }
},
{
    title: 'Spend L30',
    field: 'gpt_spend',
    hozAlign: 'center',
    width: 80,
    sorter: 'number',
    sorterParams: { alignEmptyValues: 'bottom' },
    headerTooltip: 'ChatGPT ad spend, last 30 days (ad account currency).',
    formatter: adsMoney
},
{
    title: 'CTR',
    field: 'gpt_ctr',
    hozAlign: 'center',
    width: 65,
    sorter: 'number',
    sorterParams: { alignEmptyValues: 'bottom' },
    headerTooltip: 'Clicks ÷ impressions (L30).',
    formatter: function(cell) {
        const v = cell.getValue();
        return (v === null || v === undefined) ? '<span style="color:#6c757d;">-</span>' : adsNum(v).toFixed(2) + '%';
    }
},
{
    title: 'CPC',
    field: 'gpt_cpc',
    hozAlign: 'center',
    width: 65,
    sorter: 'number',
    sorterParams: { alignEmptyValues: 'bottom' },
    headerTooltip: 'Spend ÷ clicks (L30).',
    formatter: adsMoney
}
