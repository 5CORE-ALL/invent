{{--
    "Fetch listings" button for every /listing-<marketplace> page.
    Opens a window with a loader while the live listing data is pulled from the marketplace
    (ended / deleted / new listings), shows each step as it finishes, rebuilds the Missing L and
    Listed counts, then reloads the page. The same refresh also runs automatically once a day.
    Included from partials.listing-sku-search, so each listing page gets it without editing the page.
--}}
<style>
    #listing-fetch-overlay { position: fixed; inset: 0; z-index: 200000; background: rgba(15, 23, 42, .55); display: none; align-items: center; justify-content: center; }
    #listing-fetch-overlay.is-open { display: flex; }
    #listing-fetch-box { background: #fff; width: 520px; max-width: 94vw; max-height: 86vh; border-radius: 10px; box-shadow: 0 20px 50px rgba(0,0,0,.35); display: flex; flex-direction: column; overflow: hidden; font-size: 13px; }
    #listing-fetch-head { padding: 12px 16px; background: #1d4ed8; color: #fff; font-weight: 700; font-size: 14px; display: flex; justify-content: space-between; align-items: center; }
    #listing-fetch-head button { background: none; border: 0; color: #fff; font-size: 20px; line-height: 1; cursor: pointer; }
    #listing-fetch-body { padding: 16px; overflow-y: auto; }
    #listing-fetch-status { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; font-weight: 600; color: #0f172a; }
    #listing-fetch-spinner { width: 28px; height: 28px; flex: 0 0 28px; border: 3px solid #bfdbfe; border-top-color: #2563eb; border-radius: 50%; animation: listing-fetch-spin .8s linear infinite; }
    #listing-fetch-status.is-done #listing-fetch-spinner { animation: none; border-color: #16a34a; background: #16a34a; position: relative; }
    #listing-fetch-status.is-done #listing-fetch-spinner:after { content: '\2714'; color: #fff; position: absolute; inset: 0; text-align: center; line-height: 22px; font-size: 14px; }
    #listing-fetch-status.is-failed #listing-fetch-spinner { animation: none; border-color: #dc2626; background: #dc2626; position: relative; }
    #listing-fetch-status.is-failed #listing-fetch-spinner:after { content: '!'; color: #fff; position: absolute; inset: 0; text-align: center; line-height: 22px; font-weight: 800; }
    @keyframes listing-fetch-spin { to { transform: rotate(360deg); } }
    #listing-fetch-steps { list-style: none; margin: 0; padding: 0; }
    #listing-fetch-steps li { padding: 6px 8px; border-bottom: 1px solid #e5e7eb; word-break: break-word; }
    #listing-fetch-steps li.ok .mark { color: #16a34a; }
    #listing-fetch-steps li.bad .mark { color: #dc2626; }
    #listing-fetch-steps li.bad { background: #fef2f2; }
    #listing-fetch-steps .msg { display: block; color: #475569; font-size: 12px; margin-left: 18px; }
    #listing-fetch-hint { margin-top: 10px; color: #92400e; background: #fffbeb; border: 1px solid #fcd34d; border-radius: 6px; padding: 6px 8px; font-size: 12px; display: none; }
    #listing-fetch-result { display: none; margin-top: 12px; padding: 10px 12px; background: #f0fdf4; border: 1px solid #86efac; border-radius: 8px; font-weight: 700; color: #14532d; }
    #listing-fetch-foot { padding: 10px 16px; border-top: 1px solid #e5e7eb; text-align: right; background: #f8fafc; }
    #listing-fetch-foot button { border: 1px solid #2563eb; background: #2563eb; color: #fff; border-radius: 5px; padding: 5px 14px; font-weight: 700; cursor: pointer; }
    #listing-fetch-foot button.secondary { background: #fff; color: #1d4ed8; margin-right: 6px; }
</style>
<script>
(function () {
    var m = location.pathname.match(/\/listing-([a-z0-9]+)\/?$/i);
    if (!m) return;
    var slug = m[1].toLowerCase();
    if (slug === 'manager' || slug === 'mirror' || slug.indexOf('audit') === 0) return;

    var base = '{{ url('/listing-refresh') }}/' + encodeURIComponent(slug);
    var token = '{{ csrf_token() }}';
    var BTN_HTML = '<i class="fas fa-cloud-download-alt"></i> Fetch listings';
    var btn = null, pollTimer = null, tickTimer = null, overlay = null;
    var runStartedAt = 0, missingBefore = null, finished = false;

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
        });
    }
    function $id(id) { return document.getElementById(id); }

    function currentMissing() {
        var el = $id('missing-l-badge');
        if (!el) return null;
        var mm = (el.textContent || '').match(/\d[\d,]*/);
        return mm ? parseInt(mm[0].replace(/,/g, ''), 10) : null;
    }

    function build() {
        if ($id('listing-refresh-btn')) return $id('listing-refresh-btn');
        btn = document.createElement('button');
        btn.type = 'button';
        btn.id = 'listing-refresh-btn';
        btn.innerHTML = BTN_HTML;
        btn.title = 'Fetch the current listings from the marketplace and update Missing L (also runs automatically once a day)';
        btn.style.cssText = 'flex:0 0 auto;height:30px;font-size:11px;font-weight:700;padding:2px 10px;'
            + 'border:1px solid #2563eb;border-radius:5px;background:#eff6ff;color:#1d4ed8;margin:0 4px;cursor:pointer;white-space:nowrap;';
        var input = $id('listing-sku-search');
        var anchor = $id('row-data-type');
        if (input && input.parentNode) {
            input.parentNode.insertBefore(btn, input.nextSibling);
            if (input.style.display === 'block') { btn.style.display = 'block'; btn.style.margin = '0 0 8px 0'; }
        } else if (anchor && anchor.parentNode) {
            anchor.parentNode.insertBefore(btn, anchor);
        } else {
            var tbl = document.querySelector('.tabulator');
            if (!tbl || !tbl.parentNode) return null;
            btn.style.display = 'block';
            btn.style.margin = '0 0 8px 0';
            tbl.parentNode.insertBefore(btn, tbl);
        }
        btn.addEventListener('click', onClick);
        return btn;
    }

    function buildOverlay() {
        if (overlay) return overlay;
        overlay = document.createElement('div');
        overlay.id = 'listing-fetch-overlay';
        overlay.innerHTML =
            '<div id="listing-fetch-box" role="dialog" aria-modal="true">' +
              '<div id="listing-fetch-head"><span>Fetch current listings</span><button type="button" id="listing-fetch-x" title="Close">&times;</button></div>' +
              '<div id="listing-fetch-body">' +
                '<div id="listing-fetch-status"><div id="listing-fetch-spinner"></div><div id="listing-fetch-text">Starting…</div></div>' +
                '<ul id="listing-fetch-steps"></ul>' +
                '<div id="listing-fetch-hint"></div>' +
                '<div id="listing-fetch-result"></div>' +
              '</div>' +
              '<div id="listing-fetch-foot"><button type="button" class="secondary" id="listing-fetch-hide">Run in background</button>' +
              '<button type="button" id="listing-fetch-close" style="display:none">Close &amp; reload</button></div>' +
            '</div>';
        document.body.appendChild(overlay);
        $id('listing-fetch-hide').addEventListener('click', closeOverlay);
        $id('listing-fetch-x').addEventListener('click', function () {
            if (finished && !overlay.dataset.err) location.reload(); else closeOverlay();
        });
        $id('listing-fetch-close').addEventListener('click', function () {
            if (overlay.dataset.err) closeOverlay(); else location.reload();
        });
        return overlay;
    }
    function openOverlay() { buildOverlay().classList.add('is-open'); }
    function closeOverlay() { if (overlay) overlay.classList.remove('is-open'); }

    function setBusy(text) {
        btn.disabled = false; // stays clickable: re-opens the progress window
        btn.style.opacity = '.85';
        btn.innerHTML = '<i class="fas fa-sync-alt fa-spin"></i> ' + esc(text);
    }
    function setIdle() {
        btn.disabled = false;
        btn.style.opacity = '1';
        btn.innerHTML = BTN_HTML;
    }

    function summary(st) {
        return (st.steps || []).map(function (s) { return (s.ok ? '✔ ' : '✖ ') + s.label + ': ' + s.message; }).join('\n');
    }

    function render(st) {
        buildOverlay();
        var box = $id('listing-fetch-status');
        var text = $id('listing-fetch-text');
        var list = $id('listing-fetch-steps');
        var hint = $id('listing-fetch-hint');
        var result = $id('listing-fetch-result');

        list.innerHTML = (st.steps || []).map(function (s) {
            return '<li class="' + (s.ok ? 'ok' : 'bad') + '"><span class="mark">' + (s.ok ? '✔' : '✖') + '</span> <b>' + esc(s.label) + '</b>' +
                   '<span class="msg">' + esc(s.message) + '</span></li>';
        }).join('');

        if (st.state === 'running') {
            box.className = '';
            var secs = runStartedAt ? Math.round((Date.now() - runStartedAt) / 1000) : 0;
            var msg = st.message && st.message !== 'Queued…' ? st.message : (st.message || 'Fetching listings…');
            text.textContent = 'Fetching SKUs from the marketplace… ' + secs + 's — ' + msg;
            var queued = !(st.steps && st.steps.length) && (st.message === 'Queued…' || !st.message);
            if (queued && secs > 45) {
                hint.style.display = 'block';
                hint.textContent = 'Still waiting for the background worker to pick this up. It starts as soon as the "listing-refresh" queue worker is free; you can close this window and come back.';
            } else {
                hint.style.display = 'none';
            }
            return;
        }

        hint.style.display = 'none';
        finished = true;
        box.className = st.state === 'done' ? 'is-done' : 'is-failed';
        text.textContent = st.state === 'done' ? 'Listings fetched.' : (st.message || 'Finished with problems.');
        var counts = st.counts || null;
        if (counts) {
            var now = parseInt(counts.Pending, 10) || 0;
            var line = 'Missing L: ' + now + (missingBefore !== null && missingBefore !== now ? ' (was ' + missingBefore + ')' : '') +
                       '  |  Listed: ' + (parseInt(counts.Listed, 10) || 0);
            result.textContent = line;
            result.style.display = 'block';
        }
        $id('listing-fetch-hide').style.display = 'none';
        $id('listing-fetch-close').style.display = '';
    }

    function stopTimers() {
        if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
    }

    var lastState = null;
    function handle(st, userStarted) {
        if (!st) return;
        lastState = st;
        if (st.state === 'running') {
            if (!runStartedAt) runStartedAt = st.started_at ? new Date(st.started_at).getTime() : Date.now();
            setBusy('Fetching…');
            if (!pollTimer) pollTimer = setInterval(poll, 2500);
            if (overlay && overlay.classList.contains('is-open')) render(st);
            return;
        }
        stopTimers();
        setIdle();
        if (st.finished_at) btn.title = 'Last fetched ' + new Date(st.finished_at).toLocaleString() + '\n' + summary(st);
        if (!userStarted && !btn.dataset.waiting) return;
        btn.dataset.waiting = '';
        if (st.state === 'done' || st.state === 'failed') {
            openOverlay();
            render(st);
            btn.innerHTML = st.state === 'done' ? '<i class="fas fa-check"></i> Updated' : BTN_HTML;
        }
    }

    function poll() {
        fetch(base + '/status', { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (st) { handle(st, true); })
            .catch(function () {});
    }

    // 1-second ticker so the elapsed time keeps moving between polls
    function startTicker() {
        if (tickTimer) return;
        tickTimer = setInterval(function () {
            if (lastState && lastState.state === 'running' && overlay && overlay.classList.contains('is-open')) render(lastState);
        }, 1000);
    }

    function onClick() {
        // Already running (or just finished): just show the window again.
        if (lastState && lastState.state === 'running') { openOverlay(); render(lastState); return; }

        finished = false;
        runStartedAt = Date.now();
        missingBefore = currentMissing();
        buildOverlay();
        delete overlay.dataset.err;
        $id('listing-fetch-close').textContent = 'Close & reload';
        $id('listing-fetch-result').style.display = 'none';
        $id('listing-fetch-close').style.display = 'none';
        $id('listing-fetch-hide').style.display = '';
        lastState = { state: 'running', message: 'Starting…', steps: [] };
        openOverlay();
        render(lastState);
        startTicker();
        setBusy('Starting…');
        btn.dataset.waiting = '1';

        fetch(base, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (r) { return r.json(); })
          .then(function (st) { handle(st, true); })
          .catch(function () {
              setIdle();
              lastState = null;
              finished = true;
              var text = $id('listing-fetch-text');
              $id('listing-fetch-status').className = 'is-failed';
              text.textContent = 'Could not start the fetch. Please try again.';
              $id('listing-fetch-hide').style.display = 'none';
              $id('listing-fetch-close').style.display = '';
              $id('listing-fetch-close').textContent = 'Close';
              overlay.dataset.err = '1';
          });
    }

    function start() {
        var tries = 0;
        var wait = setInterval(function () {
            tries++;
            // Wait for the SKU search box so the button sits right next to it.
            if ($id('listing-sku-search') || tries > 40) {
                clearInterval(wait);
                if (!build()) return;
                fetch(base + '/status', { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (st) {
                        if (st && st.state === 'running') { btn.dataset.waiting = '1'; startTicker(); }
                        handle(st, false);
                    })
                    .catch(function () {});
            }
        }, 100);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})();
</script>
