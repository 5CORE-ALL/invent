@php
    $ftNowSlug = $fetchTrackingMarketplace ?? $slug ?? '';
    $ftNowUrl = $fetchTrackingUrl ?? ($ftNowSlug !== '' ? url('marketplace/'.$ftNowSlug.'/orders/fetch-tracking-now') : '');
    $ftNowStatusUrl = $fetchTrackingStatusUrl ?? ($ftNowSlug !== ''
        ? url('marketplace/'.$ftNowSlug.'/orders/fetch-tracking-now/status')
        : ($ftNowUrl !== '' ? rtrim($ftNowUrl, '/').'/status' : ''));
@endphp
@if($ftNowUrl !== '')
<button type="button" class="btn btn-sm btn-outline-warning btn-mm-fetch-tracking-now"
    data-url="{{ $ftNowUrl }}"
    data-status-url="{{ $ftNowStatusUrl }}"
    title="Fulfill unfulfilled Shopify copies from Veeqo/GOFO/marketplace tracking, then push tracking to every channel. The Shopify customer is not emailed.">
    <i class="ri-truck-line"></i> Fetch tracking now
</button>
@once
<style>
    .mm-ft-overlay {
        position: fixed; inset: 0; z-index: 2000;
        background: rgba(15, 23, 42, .55);
        display: none; align-items: center; justify-content: center;
        padding: 16px;
    }
    .mm-ft-overlay.is-on { display: flex; }
    .mm-ft-card {
        width: min(440px, 100%);
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 16px 40px rgba(0,0,0,.2);
        padding: 22px 22px 18px;
        text-align: center;
    }
    .mm-ft-card h5 { margin: 0 0 6px; font-size: 17px; }
    .mm-ft-pct { font-size: 34px; font-weight: 700; color: #f7b84b; line-height: 1.1; margin: 8px 0 10px; }
    .mm-ft-bar { height: 12px; background: #f1f3f5; border-radius: 999px; overflow: hidden; }
    .mm-ft-bar > span {
        display: block; height: 100%; width: 0;
        background: linear-gradient(90deg, #f7b84b, #0ab39c);
        transition: width .35s ease;
    }
    .mm-ft-msg { color: #495057; font-size: 13px; margin: 12px 0 0; min-height: 36px; }
    .mm-ft-spin { display: inline-block; animation: mm-ft-spin 1s linear infinite; }
    @keyframes mm-ft-spin { to { transform: rotate(360deg); } }
</style>
<div class="mm-ft-overlay" id="mmFetchTrackingOverlay" aria-live="polite">
    <div class="mm-ft-card">
        <h5><i class="ri-loader-4-line mm-ft-spin"></i> Fetching tracking</h5>
        <div class="mm-ft-pct" id="mmFetchTrackingPct">1%</div>
        <div class="mm-ft-bar"><span id="mmFetchTrackingBar"></span></div>
        <p class="mm-ft-msg" id="mmFetchTrackingMsg">Starting…</p>
    </div>
</div>
<script>
(function () {
    var csrf = @json(csrf_token());
    var overlay = document.getElementById('mmFetchTrackingOverlay');
    var pctEl = document.getElementById('mmFetchTrackingPct');
    var barEl = document.getElementById('mmFetchTrackingBar');
    var msgEl = document.getElementById('mmFetchTrackingMsg');
    var timer = null;
    var activeBtn = null;

    function paint(percent, message, done) {
        var pct = Math.max(0, Math.min(100, parseInt(percent, 10) || 0));
        if (pctEl) pctEl.textContent = pct + '%';
        if (barEl) barEl.style.width = pct + '%';
        if (msgEl) msgEl.textContent = message || (done ? 'Done.' : 'Working…');
        if (overlay) overlay.classList.add('is-on');
    }

    function hideSoon() {
        setTimeout(function () {
            if (overlay) overlay.classList.remove('is-on');
            if (activeBtn) activeBtn.disabled = false;
            activeBtn = null;
        }, 1800);
    }

    function poll(statusUrl, runId) {
        var url = statusUrl + (statusUrl.indexOf('?') >= 0 ? '&' : '?') + 'run=' + encodeURIComponent(runId || '');
        fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var status = String((data && data.status) || '');
                paint(data && data.percent, data && data.message, status === 'done' || status === 'failed');
                if (status === 'done' || status === 'failed') {
                    if (timer) clearInterval(timer);
                    timer = null;
                    hideSoon();
                    return;
                }
            })
            .catch(function () {});
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.btn-mm-fetch-tracking-now');
        if (!btn || btn.disabled) return;
        var url = btn.getAttribute('data-url');
        var statusUrl = btn.getAttribute('data-status-url') || '';
        if (!url) return;
        e.preventDefault();
        activeBtn = btn;
        btn.disabled = true;
        paint(1, 'Starting tracking catch-up…', false);
        fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json'
            },
            credentials: 'same-origin',
            body: '{}'
        })
        .then(function (r) {
            return r.text().then(function (text) {
                var data = {};
                try { data = text ? JSON.parse(text) : {}; } catch (err) {
                    data = { message: r.status === 419 ? 'Session expired. Refresh the page and try again.' : ('HTTP ' + r.status) };
                }
                return { ok: r.ok, data: data };
            });
        })
        .then(function (res) {
            if (!res.ok) {
                paint(0, (res.data && res.data.message) || 'Could not start tracking.', true);
                hideSoon();
                return;
            }
            var runId = (res.data && (res.data.run_id || res.data.runId)) || '';
            paint((res.data && res.data.percent) || 2, (res.data && res.data.message) || 'Queued…', false);
            if (timer) clearInterval(timer);
            if (!statusUrl) {
                hideSoon();
                return;
            }
            poll(statusUrl, runId);
            timer = setInterval(function () { poll(statusUrl, runId); }, 1500);
        })
        .catch(function (err) {
            paint(0, 'Request failed: ' + (err && err.message ? err.message : 'network error'), true);
            hideSoon();
        });
    });
})();
</script>
@endonce
@endif
