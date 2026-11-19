const MOBILE_UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1';

function amazonUrl(url) {
    try {
        const parsed = new URL(url);
        if (!/(^|\.)amazon\.(com|in|co\.uk|de|ca)$/i.test(parsed.hostname)) return '';
        if (!/^\/(dp|gp)\//.test(parsed.pathname)) return '';
        if (parsed.protocol !== 'https:') return '';
        return parsed.toString();
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

chrome.runtime.onMessageExternal.addListener(function (msg, sender, sendResponse) {
    const url = amazonUrl(msg && msg.url);
    if (!msg || msg.type !== 'open-mobile' || !url) {
        sendResponse({ ok: false });
        return;
    }
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
    return true;
});

chrome.tabs.onRemoved.addListener(function (tabId) {
    chrome.declarativeNetRequest.updateSessionRules({ removeRuleIds: [tabId] });
});
