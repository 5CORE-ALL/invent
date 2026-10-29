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
@include('partials.listing-refresh-button')
