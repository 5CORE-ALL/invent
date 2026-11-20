const MOBILE_UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1';

function phonePageUrl(url) {
    try {
        const parsed = new URL(url);
        if (parsed.protocol !== 'https:') return '';
        const host = parsed.hostname.toLowerCase();
        const amazon = /(^|\.)amazon\.(com|in|co\.uk|de|ca)$/.test(host) && /^\/(dp|gp)\//.test(parsed.pathname);
        const newegg = /(^|\.)newegg\.com$/.test(host) && /^\/p\//.test(parsed.pathname);
        const alibaba = /(^|\.)alibaba\.com$/.test(host) && /\/product-detail\//.test(parsed.pathname);
        const temu = /(^|\.)temu\.com$/.test(host) && (/\/goods\.html$/i.test(parsed.pathname) || /-g-\d+/.test(parsed.pathname) || /(?:^|[?&])goods_id=\d+/.test(parsed.search));
        return (amazon || newegg || alibaba || temu) ? parsed.toString() : '';
    } catch (e) {
        return '';
    }
}

function phoneRule(tabId, headers) {
    return {
        id: tabId,
        priority: 1,
        action: { type: 'modifyHeaders', requestHeaders: headers },
        condition: {
            tabIds: [tabId],
            resourceTypes: ['main_frame', 'sub_frame', 'stylesheet', 'script', 'image', 'font', 'object', 'xmlhttprequest', 'media', 'other']
        }
    };
}

const UA_ONLY = [{ header: 'User-Agent', operation: 'set', value: MOBILE_UA }];
const UA_AND_HINTS = UA_ONLY.concat([
    { header: 'sec-ch-ua-mobile', operation: 'set', value: '?1' },
    { header: 'sec-ch-ua-platform', operation: 'set', value: '"iOS"' }
]);

function applyRule(tabId, headers, done) {
    chrome.declarativeNetRequest.updateSessionRules({
        removeRuleIds: [tabId],
        addRules: [phoneRule(tabId, headers)]
    }, function () {
        done(chrome.runtime.lastError ? chrome.runtime.lastError.message : '');
    });
}

function setTemuUs(done) {
    if (!chrome.cookies) {
        done();
        return;
    }
    chrome.cookies.getAll({ domain: 'temu.com' }, function (all) {
        const doomed = (all || []).filter(function (c) {
            return c.name === 'region' || c.name === 'currency';
        });
        const write = function () {
            const pairs = [['region', '211'], ['currency', 'USD'], ['language', 'en']];
            let left = pairs.length;
            pairs.forEach(function (pair) {
                chrome.cookies.set({
                    url: 'https://www.temu.com/',
                    name: pair[0],
                    value: pair[1],
                    domain: '.temu.com',
                    path: '/',
                    secure: true,
                    expirationDate: Math.floor(Date.now() / 1000) + 31536000
                }, function () {
                    left -= 1;
                    if (left === 0) done();
                });
            });
        };
        if (!doomed.length) {
            write();
            return;
        }
        let left = doomed.length;
        doomed.forEach(function (c) {
            const host = String(c.domain || 'www.temu.com').replace(/^\./, '');
            chrome.cookies.remove({
                url: (c.secure ? 'https://' : 'http://') + host + (c.path || '/'),
                name: c.name
            }, function () {
                left -= 1;
                if (left === 0) write();
            });
        });
    });
}

chrome.runtime.onMessageExternal.addListener(function (msg, sender, sendResponse) {
    if (msg && msg.type === 'temu-us') {
        setTemuUs(function () { sendResponse({ ok: true }); });
        return true;
    }
    const url = phonePageUrl(msg && msg.url);
    if (!msg || msg.type !== 'open-mobile' || !url) {
        sendResponse({ ok: false });
        return;
    }
    const openPhone = function () {
        chrome.windows.create({
            url: 'about:blank',
            type: 'popup',
            width: 430,
            height: 900,
            focused: true
        }, function (win) {
            const tabId = win && win.tabs && win.tabs[0] ? win.tabs[0].id : 0;
            if (!tabId) {
                sendResponse({ ok: false, error: 'No window' });
                return;
            }
            applyRule(tabId, UA_AND_HINTS, function (err) {
                const go = function (error) {
                    chrome.tabs.update(tabId, { url: url }, function () {
                        sendResponse({ ok: !error && !chrome.runtime.lastError, error: error || (chrome.runtime.lastError && chrome.runtime.lastError.message) || '' });
                    });
                };
                if (!err) {
                    go('');
                    return;
                }
                applyRule(tabId, UA_ONLY, go);
            });
        });
    };
    if (/(^|\.)temu\.com$/i.test(new URL(url).hostname)) {
        setTemuUs(openPhone);
    } else {
        openPhone();
    }
    return true;
});

chrome.tabs.onRemoved.addListener(function (tabId) {
    chrome.declarativeNetRequest.updateSessionRules({ removeRuleIds: [tabId] });
});
