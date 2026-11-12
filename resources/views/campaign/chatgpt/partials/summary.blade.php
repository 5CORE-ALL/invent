{{-- ChatGPT Ads: account-level L30 badges from /chatgpt-ads/summary (OpenAI Advertiser API). Runs inside partials/ads-tabulator-page's script. --}}
(function() {
    const box = document.getElementById('ads-summary-stats');
    if (!box) return;
    function badge(cls, text, title) {
        const s = document.createElement('span');
        s.className = 'badge ' + cls;
        s.textContent = text;
        if (title) s.title = title;
        box.appendChild(s);
        return s;
    }
    fetch(@json(route('chatgpt.ads.summary')), { headers: { 'Accept': 'application/json' } })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (!d || !d.connected) {
                badge('bg-danger', 'ChatGPT Ads API: not connected', (d && d.message) || '');
                return;
            }
            const cur = (d.account && d.account.currency) || 'USD';
            const acct = d.account || {};
            badge('bg-dark', 'Account: ' + (acct.name || acct.id || ''), 'Ad account ' + (acct.id || '') + ' · ' + (acct.status || '') + ' · ' + (acct.timezone || ''));
            badge('bg-secondary', 'Campaigns: ' + Number(d.campaigns || 0).toLocaleString(), 'Campaigns in the ad account');
            badge('bg-info text-dark', 'Impr L' + d.days + ': ' + Number(d.impressions || 0).toLocaleString(), 'Account impressions, last ' + d.days + ' days');
            badge('bg-warning text-dark', 'Clicks L' + d.days + ': ' + Number(d.clicks || 0).toLocaleString(), 'Account clicks, last ' + d.days + ' days');
            badge('bg-success', 'Spend L' + d.days + ': ' + (cur === 'USD' ? '$' : cur + ' ') + Number(d.spend || 0).toLocaleString(undefined, { maximumFractionDigits: 2 }), 'Account spend, last ' + d.days + ' days');
        })
        .catch(function() { badge('bg-danger', 'ChatGPT Ads API: request failed'); });
})();
