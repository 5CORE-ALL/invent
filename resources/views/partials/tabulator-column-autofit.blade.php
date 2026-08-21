{{-- Shared analytics-table autofit: full cell values, horizontal scroll instead of "…" --}}
(function chPromoInstallColumnAutofit() {
    if (window.chPromoAutofitColumns) return;

    window.chPromoAutofitColumns = function(table) {
        if (!table || typeof table.getColumns !== 'function') return;
        const skip = {
            image_path: 1, image: 1, _select: 1, _push: 1, parent_expand: 1
        };
        table.getColumns().forEach(function(col) {
            try {
                if (!col.isVisible()) return;
                const field = col.getField() || '';
                const def = col.getDefinition() || {};
                if (skip[field] || skip[def.field]) return;
                if (typeof col.setWidth === 'function') col.setWidth(true);
                const measured = col.getWidth() || 0;
                let floor = Number(def.minWidth) || 68;
                if (/price|profit|roi|prc|lmp|groi|gpft|sprice|percent/i.test(field) && floor < 80) {
                    floor = 80;
                }
                const next = Math.max(measured + 8, floor);
                if (next > measured) col.setWidth(Math.min(next, 260));
            } catch (e) { /* ignore */ }
        });
    };

    window.chPromoBindTableAutofit = function(table) {
        if (!table || table._chPromoAutofitBound) return;
        table._chPromoAutofitBound = true;
        const run = function() {
            clearTimeout(table._chPromoAutofitTimer);
            table._chPromoAutofitTimer = setTimeout(function() {
                window.chPromoAutofitColumns(table);
            }, 80);
        };
        try {
            if (table.on) {
                table.on('dataLoaded', run);
                table.on('pageLoaded', run);
                table.on('tableBuilt', run);
            }
        } catch (e) { /* ignore */ }
        run();
    };

    function chPromoIsMainAnalyticsTable(el, opts) {
        const node = typeof el === 'string' ? document.querySelector(el) : el;
        if (!node) return false;
        if (node.closest && node.closest('.modal')) return false;
        const id = String(node.id || '');
        if (/modal|chart|history|dropdown|lmp-entries|dil-prmt|cvr-cpn|zero-sold|gt-sold/i.test(id)) {
            return false;
        }
        const cols = (opts && opts.columns && opts.columns.length) || 0;
        if (cols > 0 && cols < 8) return false;
        return true;
    }

    function wrapTabulator() {
        if (!window.Tabulator || window.Tabulator.__chPromoAutofitWrapped) return !!window.Tabulator;
        const Orig = window.Tabulator;
        try {
            window.Tabulator = class extends Orig {
                constructor(el, options) {
                    const opts = Object.assign({}, options || {});
                    const main = chPromoIsMainAnalyticsTable(el, opts);
                    if (main && (opts.layout === 'fitDataStretch' || opts.layout === 'fitColumns')) {
                        opts.layout = 'fitData';
                        if (opts.layoutColumnsOnNewData == null) opts.layoutColumnsOnNewData = true;
                    }
                    if (main && opts.columnDefaults && Number(opts.columnDefaults.minWidth) > 0
                        && Number(opts.columnDefaults.minWidth) < 64) {
                        opts.columnDefaults = Object.assign({}, opts.columnDefaults, { minWidth: 64 });
                    }
                    super(el, opts);
                    if (main) {
                        const self = this;
                        setTimeout(function() { window.chPromoBindTableAutofit(self); }, 0);
                    }
                }
            };
            window.Tabulator.__chPromoAutofitWrapped = true;
        } catch (err) {
            console.warn('tabulator column autofit wrap skipped', err);
        }
        return true;
    }

    if (!wrapTabulator()) {
        let n = 0;
        const t = setInterval(function() {
            n += 1;
            if (wrapTabulator() || n > 80) clearInterval(t);
        }, 50);
    }
})();
