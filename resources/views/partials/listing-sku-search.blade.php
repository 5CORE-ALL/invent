{{--
    SKU search for the /listing-<marketplace> pages (Missing L, Listed, Pending ...).
    Adds a search box to the filter bar and ANDs it with whatever filter the page applies
    (Data Type, INV, NRL/REQ, Buyer Link, Listed, Missing L badge), so it works together with them.
    Matches the SKU and the Parent; separate several SKUs with commas.
--}}
<script>
(function () {
    function norm(s) { return String(s == null ? '' : s).toLowerCase().replace(/\u00a0/g, ' ').trim(); }
    function compact(s) { return norm(s).replace(/[^a-z0-9]/g, ''); }

    function buildInput() {
        if (document.getElementById('listing-sku-search')) return document.getElementById('listing-sku-search');
        var input = document.createElement('input');
        input.type = 'search';
        input.id = 'listing-sku-search';
        input.placeholder = 'Search SKU…';
        input.autocomplete = 'off';
        input.setAttribute('aria-label', 'Search SKU');
        input.title = 'Search SKU or Parent. Separate several SKUs with commas.';
        input.style.cssText = 'flex:0 0 auto;width:170px;max-width:170px;height:30px;font-size:11px;font-weight:600;'
            + 'padding:2px 8px;border:1px solid #cbd5e1;border-radius:5px;background:#fff;color:#334155;margin:0 4px;';
        var anchor = document.getElementById('row-data-type');
        if (anchor && anchor.parentNode) {
            anchor.parentNode.insertBefore(input, anchor);
        } else {
            var tbl = document.querySelector('.tabulator');
            if (!tbl || !tbl.parentNode) return null;
            input.style.margin = '0 0 8px 0';
            input.style.width = '240px';
            input.style.maxWidth = '240px';
            input.style.display = 'block';
            tbl.parentNode.insertBefore(input, tbl);
        }
        return input;
    }

    function attach(table, input) {
        var pageFilter = null;
        var terms = [];
        var origSetFilter = table.setFilter.bind(table);
        var origClearFilter = table.clearFilter.bind(table);
        // If the page already applied its filter before this attached, pick it up.
        try {
            var existing = table.getFilters ? table.getFilters() : [];
            if (existing && existing.length === 1 && typeof existing[0].field === 'function') {
                pageFilter = existing[0].field;
            }
        } catch (e) { /* table not built yet; the page's next setFilter call is captured below */ }

        function matches(data) {
            if (!terms.length) return true;
            var sku = norm(data && data.sku), skuC = compact(data && data.sku);
            var parent = norm(data && (data.parent != null ? data.parent : data.Parent)), parentC = compact(data && (data.parent != null ? data.parent : data.Parent));
            for (var i = 0; i < terms.length; i++) {
                var t = terms[i];
                if (sku.indexOf(t.raw) !== -1 || parent.indexOf(t.raw) !== -1) return true;
                if (t.compact && (skuC.indexOf(t.compact) !== -1 || parentC.indexOf(t.compact) !== -1)) return true;
            }
            return false;
        }
        function composed(data, params) {
            return (!pageFilter || pageFilter(data, params)) && matches(data);
        }
        function apply() {
            if (pageFilter || terms.length) origSetFilter(composed); else origClearFilter();
        }

        // The page re-applies its own filters through setFilter(fn); keep the search ANDed to them.
        table.setFilter = function (field) {
            if (typeof field === 'function' && arguments.length === 1) {
                pageFilter = field;
                return origSetFilter(composed);
            }
            pageFilter = null;
            return origSetFilter.apply(table, arguments);
        };
        table.clearFilter = function () {
            pageFilter = null;
            if (terms.length) return origSetFilter(composed);
            return origClearFilter.apply(table, arguments);
        };

        var timer = null;
        input.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(function () {
                terms = input.value.split(/[,\n;]+/).map(norm).filter(Boolean).map(function (raw) {
                    return { raw: raw, compact: compact(raw) };
                });
                apply();
            }, 200);
        });
    }

    function start() {
        var input = buildInput();
        if (!input) return;
        var tries = 0;
        var poll = setInterval(function () {
            tries++;
            var found = null;
            if (window.Tabulator && typeof Tabulator.findTable === 'function') {
                var els = document.querySelectorAll('.tabulator');
                for (var i = 0; i < els.length && !found; i++) {
                    var t = Tabulator.findTable(els[i]);
                    if (t && t[0]) found = t[0];
                }
            }
            if (found) {
                clearInterval(poll);
                attach(found, input);
            } else if (tries > 800) {
                clearInterval(poll);
            }
        }, 25);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})();
</script>
{{--
    SKU copy for every /listing-<marketplace> page (Missing L, Listed ...).
    Root cause of the dead copy icon: the Tabulator SKU cells call stopPropagation() on a click on the icon
    (to keep the row from being selected), so the page's delegated document click handler never ran. This
    handler runs in the capture phase, before Tabulator sees the click, so the copy always happens.
    It also adds the icon to SKU cells of pages that only showed plain text.
--}}
<style>
    .lsc-btn { display: inline-flex; align-items: center; justify-content: center; margin-left: 6px; padding: 0 2px;
        border: 0; background: transparent; color: #64748b; cursor: pointer; line-height: 1; vertical-align: middle; flex: 0 0 auto; }
    .lsc-btn:hover { color: #0f172a; }
    .lsc-btn.is-copied, .copy-sku-btn.is-copied { color: #16a34a; }
    .lsc-btn svg { width: 12px; height: 12px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
    .lsc-toast { position: fixed; right: 18px; bottom: 18px; z-index: 20000; background: #0f172a; color: #fff; font-size: 12px;
        font-weight: 600; padding: 8px 12px; border-radius: 6px; box-shadow: 0 4px 14px rgba(0,0,0,.25); }
</style>
<script>
(function () {
    var ICON = '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>';
    var CELLS = '.tabulator-cell[tabulator-field="sku"],.tabulator-cell[tabulator-field="SKU"],.tabulator-cell[tabulator-field="Sku"]';

    function toast(text) {
        var old = document.querySelector('.lsc-toast');
        if (old) old.remove();
        var el = document.createElement('div');
        el.className = 'lsc-toast';
        el.textContent = text;
        document.body.appendChild(el);
        setTimeout(function () { el.remove(); }, 1600);
    }

    function flash(btn) {
        btn.classList.add('is-copied');
        setTimeout(function () { btn.classList.remove('is-copied'); }, 1200);
    }

    function copyText(text, btn) {
        var done = function () { toast('Copied: ' + text); if (btn) flash(btn); };
        var fallback = function () {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.setAttribute('readonly', '');
            ta.style.cssText = 'position:fixed;top:0;left:0;opacity:0;';
            document.body.appendChild(ta);
            ta.focus();
            ta.select();
            var ok = false;
            try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
            document.body.removeChild(ta);
            if (ok) done(); else toast('Could not copy SKU');
        };
        if (window.isSecureContext && navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(done).catch(fallback);
        } else {
            fallback();
        }
    }

    // Capture phase on window: runs before Tabulator's cell handlers and before any page handler.
    window.addEventListener('click', function (e) {
        var btn = e.target && e.target.closest ? e.target.closest('.copy-sku-btn, .lsc-btn') : null;
        if (!btn) return;
        var sku = String(btn.getAttribute('data-sku') || '').trim();
        if (!sku) return;
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        copyText(sku, btn);
    }, true);

    var queued = false;
    function decorate() {
        queued = false;
        var cells = document.querySelectorAll(CELLS);
        for (var i = 0; i < cells.length; i++) {
            var cell = cells[i];
            if (cell.querySelector('.copy-sku-btn, .lsc-btn')) continue;
            var sku = (cell.textContent || '').replace(/\u00a0/g, ' ').trim();
            if (!sku || sku === '-' || sku === '—') continue;
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'lsc-btn';
            btn.title = 'Copy SKU';
            btn.setAttribute('aria-label', 'Copy SKU');
            btn.setAttribute('data-sku', sku);
            btn.innerHTML = ICON;
            cell.appendChild(btn);
        }
    }
    function schedule() {
        if (queued) return;
        queued = true;
        (window.requestAnimationFrame || setTimeout)(decorate);
    }

    function start() {
        new MutationObserver(schedule).observe(document.body, { childList: true, subtree: true });
        schedule();
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})();
</script>
@include('partials.listing-refresh-button')
@include('partials.listing-parent-picker')
