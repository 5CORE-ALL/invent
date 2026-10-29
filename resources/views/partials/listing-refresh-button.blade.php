{{--
    "Refresh listings" button for every /listing-<marketplace> page.
    Pulls the live listing data from the marketplace (ended / deleted / new listings), rebuilds the
    Missing L and Listed counts, then reloads the page. The same refresh also runs automatically once a day.
    Included from partials.listing-sku-search, so each listing page gets it without editing the page.
--}}
<script>
(function () {
    var m = location.pathname.match(/\/listing-([a-z0-9]+)\/?$/i);
    if (!m) return;
    var slug = m[1].toLowerCase();
    if (slug === 'manager' || slug === 'mirror' || slug.indexOf('audit') === 0) return;

    var base = '{{ url('/listing-refresh') }}/' + encodeURIComponent(slug);
    var token = '{{ csrf_token() }}';
    var btn = null, pollTimer = null;

    function build() {
        if (document.getElementById('listing-refresh-btn')) return document.getElementById('listing-refresh-btn');
        btn = document.createElement('button');
        btn.type = 'button';
        btn.id = 'listing-refresh-btn';
        btn.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh listings';
        btn.title = 'Fetch the latest listings from the marketplace now (also runs automatically once a day)';
        btn.style.cssText = 'flex:0 0 auto;height:30px;font-size:11px;font-weight:700;padding:2px 10px;'
            + 'border:1px solid #2563eb;border-radius:5px;background:#eff6ff;color:#1d4ed8;margin:0 4px;cursor:pointer;white-space:nowrap;';
        var input = document.getElementById('listing-sku-search');
        var anchor = document.getElementById('row-data-type');
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

    function setBusy(text) {
        btn.disabled = true;
        btn.style.opacity = '.7';
        btn.style.cursor = 'wait';
        btn.innerHTML = '<i class="fas fa-sync-alt fa-spin"></i> ' + text;
    }
    function setIdle() {
        btn.disabled = false;
        btn.style.opacity = '1';
        btn.style.cursor = 'pointer';
        btn.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh listings';
    }

    function summary(st) {
        return (st.steps || []).map(function (s) { return (s.ok ? '✔ ' : '✖ ') + s.label + ': ' + s.message; }).join('\n');
    }

    function handle(st, userStarted) {
        if (!st) return;
        if (st.state === 'running') {
            setBusy(st.message && st.message !== 'Queued…' ? st.message.slice(0, 40) : 'Refreshing…');
            if (!pollTimer) pollTimer = setInterval(poll, 4000);
            return;
        }
        if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
        setIdle();
        if (st.finished_at) btn.title = 'Last refreshed ' + new Date(st.finished_at).toLocaleString() + '\n' + summary(st);
        if (!userStarted && !btn.dataset.waiting) return;
        if (st.state === 'done') {
            btn.dataset.waiting = '';
            btn.innerHTML = '<i class="fas fa-check"></i> Updated';
            setTimeout(function () { location.reload(); }, 1200);
        } else if (st.state === 'failed') {
            btn.dataset.waiting = '';
            alert('Refresh finished with problems:\n\n' + (summary(st) || st.message || 'Unknown error'));
            location.reload();
        }
    }

    function poll() {
        fetch(base + '/status', { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (st) { handle(st, true); })
            .catch(function () {});
    }

    function onClick() {
        setBusy('Starting…');
        btn.dataset.waiting = '1';
        fetch(base, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (r) { return r.json(); })
          .then(function (st) { handle(st, true); })
          .catch(function () { setIdle(); alert('Could not start the refresh. Please try again.'); });
    }

    function start() {
        var tries = 0;
        var wait = setInterval(function () {
            tries++;
            // Wait for the SKU search box so the button sits right next to it.
            if (document.getElementById('listing-sku-search') || tries > 40) {
                clearInterval(wait);
                if (!build()) return;
                fetch(base + '/status', { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (st) {
                        if (st && st.state === 'running') btn.dataset.waiting = '1';
                        handle(st, false);
                    })
                    .catch(function () {});
            }
        }, 100);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})();
</script>
