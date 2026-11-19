/**
 * Downloads always include EVERY column that exists on the page, not just the ones currently shown.
 *
 * Tabulator's own export (table.download / downloadToTab) skips hidden columns. Many pages also build their own
 * CSV from `table.getColumns().filter(col => col.isVisible())`. Both are fixed here, once, for every page:
 *
 *  1. Tabulator downloads: every column that has a field and a title is exported, shown or hidden.
 *     (Columns are still left out when they set `download: false` or `hideFromColumnPicker: true`.)
 *  2. window.tabDownloadColumns(table): the column list for hand-written exports. Use it instead of
 *     `table.getColumns().filter(col => col.isVisible())`.
 *
 * Load in <head> (layouts/vertical.blade.php does). It works whether it loads before or after tabulator.min.js.
 */
(function (global) {
    'use strict';

    function hasTitle(def) {
        var t = def && def.title;
        if (typeof t !== 'string') return false;
        return t.replace(/<[^>]*>/g, '').trim() !== '';
    }

    /** True for a hidden column that is a real, user-facing column of the page. */
    function isHiddenButAvailable(def, field) {
        if (!def || !field) return false;
        if (def.download === false) return false;           // explicit opt-out (action columns etc.)
        if (def.hideFromColumnPicker) return false;         // technical helper column
        if (String(field).charAt(0) === '_') return false;  // _select, _edit_row ...
        return hasTitle(def);                               // untitled columns are helpers, not data
    }

    /**
     * Columns a hand-written export should include: all visible columns plus all hidden-but-available ones,
     * in the table's column order. Returns Tabulator ColumnComponents.
     */
    global.tabDownloadColumns = function (table) {
        if (!table || typeof table.getColumns !== 'function') return [];
        return table.getColumns().filter(function (col) {
            var field = col.getField();
            if (!field || field === '_select') return false;
            var def = (typeof col.getDefinition === 'function' ? col.getDefinition() : null) || {};
            if (def.download === false) return false;
            if (col.isVisible()) return true;
            return isHiddenButAvailable(def, field);
        });
    };

    function install(T) {
        if (!T) return false;
        if (T.__downloadAllColumnsInstalled) return true;
        try {
            // Same trick tabulator-sort-defaults.js uses: this makes Tabulator register its modules.
            if (typeof T.extendModule === 'function') T.extendModule('download', 'downloaders', {});
            var bindings = T.moduleBindings;
            var Exp = bindings && bindings.export;
            if (!Exp || !Exp.prototype || typeof Exp.prototype.columnVisCheck !== 'function') return false;

            var original = Exp.prototype.columnVisCheck;
            Exp.prototype.columnVisCheck = function (column) {
                var include = original.call(this, column);
                if (include) return include;
                // Only change downloads. Print, clipboard and HTML output keep their own rules.
                if (this.colVisProp !== 'download') return include;
                var def = column && column.definition;
                if (!def || def.download !== undefined) return include;   // explicit true/false/function wins
                if (column.isGroup || column.visible) return include;
                return isHiddenButAvailable(def, column.field) ? true : include;
            };
            T.__downloadAllColumnsInstalled = true;
            return true;
        } catch (e) {
            if (global.console && console.warn) console.warn('[download-all-columns] not installed:', e);
            return false;
        }
    }

    function boot() {
        if (install(global.Tabulator)) return;
        // Tabulator not loaded yet: install the moment the page assigns window.Tabulator.
        var current;
        try {
            Object.defineProperty(global, 'Tabulator', {
                configurable: true,
                enumerable: true,
                get: function () { return current; },
                set: function (value) {
                    current = value;
                    try {
                        // Back to a plain property, so nothing else sees a difference.
                        Object.defineProperty(global, 'Tabulator', {
                            value: value, writable: true, configurable: true, enumerable: true
                        });
                    } catch (e) { /* keep the accessor */ }
                    install(value);
                }
            });
        } catch (e) { /* fall back to the load hooks below */ }
        // Safety net if the property trap is not allowed.
        global.addEventListener('DOMContentLoaded', function () { install(global.Tabulator); });
        global.addEventListener('load', function () { install(global.Tabulator); });
    }

    boot();
})(window);
