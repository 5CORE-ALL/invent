/**
 * Shared yellow-triangle "P" column — expand a PARENT row to show its child SKUs.
 * Used across marketplace analytics / pricing Tabulator pages.
 *
 * Usage:
 *   ParentExpand.configure({
 *     parentField: 'Parent',          // or 'parent'
 *     skuField: '(Child) sku',        // or 'sku'
 *     getTable: () => table,
 *     getDataset: () => allTableData,
 *     setDataset: (rows) => { allTableData = rows; }, // optional
 *     onAfterExpand: () => { updateSummary && updateSummary(); },
 *     onCollapse: () => { applyFilters(); },          // restore filters after collapse
 *   });
 *   ParentExpand.bind();
 *   // In columns (after Parent): ParentExpand.columnDef(),
 *   // In ajaxResponse/dataLoaded: ParentExpand.captureDataset(response.data);
 */
(function (window) {
    'use strict';

    var state = {
        expandedKey: null,
        dataset: [],
        opts: {
            parentField: 'Parent',
            skuField: '(Child) sku',
            btnClass: 'pm-parent-expand-btn',
            getTable: null,
            getDataset: null,
            setDataset: null,
            onAfterExpand: null,
            onCollapse: null,
            isParentRow: null,
            parentKeyFromRow: null
        },
        bound: false
    };

    function configure(opts) {
        if (!opts || typeof opts !== 'object') return ParentExpand;
        Object.keys(opts).forEach(function (k) {
            if (opts[k] !== undefined) state.opts[k] = opts[k];
        });
        return ParentExpand;
    }

    function getTable() {
        return typeof state.opts.getTable === 'function' ? state.opts.getTable() : null;
    }

    function getDataset() {
        if (typeof state.opts.getDataset === 'function') {
            var d = state.opts.getDataset();
            if (Array.isArray(d) && d.length) return d;
        }
        return state.dataset;
    }

    function captureDataset(rows) {
        if (!Array.isArray(rows)) return;
        state.dataset = rows;
        if (typeof state.opts.setDataset === 'function') {
            state.opts.setDataset(rows);
        }
    }

    function normalizeKey(val) {
        return String(val == null ? '' : val).trim().replace(/^PARENT\s+/i, '').trim();
    }

    function defaultIsParentRow(data) {
        if (!data) return false;
        if (data.is_parent_summary === true || data.is_parent_row === true || data.is_parent === true) {
            return true;
        }
        var skuField = state.opts.skuField;
        var parentField = state.opts.parentField;
        var sku = String(data[skuField] != null ? data[skuField] : (data.SKU || data.sku || '')).toUpperCase();
        if (sku.includes('PARENT')) return true;
        var p = data[parentField];
        return !!(p && String(p).toUpperCase().startsWith('PARENT'));
    }

    function isParentRow(data) {
        if (typeof state.opts.isParentRow === 'function') return !!state.opts.isParentRow(data);
        return defaultIsParentRow(data);
    }

    function defaultParentKeyFromRow(row) {
        if (!row) return '';
        var parentField = state.opts.parentField;
        var skuField = state.opts.skuField;
        var fromParent = normalizeKey(row[parentField]);
        var sku = String(row[skuField] != null ? row[skuField] : (row.SKU || row.sku || '')).trim();
        if (sku.toUpperCase().includes('PARENT')) {
            return fromParent || normalizeKey(sku);
        }
        return fromParent;
    }

    function parentKeyFromRow(row) {
        if (typeof state.opts.parentKeyFromRow === 'function') {
            return normalizeKey(state.opts.parentKeyFromRow(row));
        }
        return defaultParentKeyFromRow(row);
    }

    function yellowSvg() {
        var uid = 'pe' + Math.random().toString(36).slice(2, 9);
        return (
            '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">' +
            '<defs>' +
            '<linearGradient id="' + uid + 'g" x1="4" y1="2" x2="20" y2="22" gradientUnits="userSpaceOnUse">' +
            '<stop offset="0%" stop-color="#FFE566"/>' +
            '<stop offset="45%" stop-color="#FFC107"/>' +
            '<stop offset="100%" stop-color="#F59E0B"/>' +
            '</linearGradient>' +
            '<linearGradient id="' + uid + 's" x1="6" y1="3" x2="14" y2="14" gradientUnits="userSpaceOnUse">' +
            '<stop offset="0%" stop-color="#FFFFFF" stop-opacity="0.75"/>' +
            '<stop offset="55%" stop-color="#FFFFFF" stop-opacity="0.12"/>' +
            '<stop offset="100%" stop-color="#FFFFFF" stop-opacity="0"/>' +
            '</linearGradient>' +
            '</defs>' +
            '<path d="M8.2 4.8c-.9-.55-2.05.1-2.05 1.15v12.1c0 1.05 1.15 1.7 2.05 1.15l10.2-6.05c.85-.5.85-1.8 0-2.3L8.2 4.8z" fill="url(#' + uid + 'g)"/>' +
            '<path d="M8.2 4.8c-.9-.55-2.05.1-2.05 1.15v12.1c0 1.05 1.15 1.7 2.05 1.15l10.2-6.05c.85-.5.85-1.8 0-2.3L8.2 4.8z" fill="url(#' + uid + 's)"/>' +
            '<path d="M8.2 4.8c-.9-.55-2.05.1-2.05 1.15v12.1c0 1.05 1.15 1.7 2.05 1.15l10.2-6.05c.85-.5.85-1.8 0-2.3L8.2 4.8z" fill="none" stroke="#D97706" stroke-opacity="0.35" stroke-width="0.8"/>' +
            '</svg>'
        );
    }

    function columnDef(extra) {
        var btnClass = state.opts.btnClass || 'pm-parent-expand-btn';
        var def = {
            title: 'P',
            field: '_parent_expand',
            headerSort: false,
            hozAlign: 'center',
            frozen: true,
            width: 36,
            minWidth: 36,
            download: false,
            formatter: function (cell) {
                var rowData = cell.getRow().getData();
                var playIcon = yellowSvg();
                if (!isParentRow(rowData)) {
                    return '<span class="pm-parent-sku-dot no-parent" title="">' + playIcon + '</span>';
                }
                var parentKey = parentKeyFromRow(rowData);
                if (!parentKey) {
                    return '<span class="pm-parent-sku-dot no-parent" title="No parent key">' + playIcon + '</span>';
                }
                var parentEsc = String(parentKey).replace(/"/g, '&quot;');
                var isExpanded =
                    (state.expandedKey &&
                        normalizeKey(state.expandedKey).toUpperCase() === parentKey.toUpperCase()) ||
                    rowData._expanded === true;
                var expandedCls = isExpanded ? ' is-expanded' : '';
                return (
                    '<span class="pm-parent-sku-dot ' +
                    btnClass +
                    expandedCls +
                    '" data-parent="' +
                    parentEsc +
                    '" title="Show all SKUs for parent: ' +
                    parentEsc +
                    '">' +
                    playIcon +
                    '</span>'
                );
            }
        };
        if (extra && typeof extra === 'object') {
            Object.keys(extra).forEach(function (k) {
                def[k] = extra[k];
            });
        }
        return def;
    }

    function showExpanded(parentKey) {
        var key = normalizeKey(parentKey);
        var table = getTable();
        var all = getDataset();
        if (!key || !table || !all || !all.length) return;

        var keyU = key.toUpperCase();
        var parentField = state.opts.parentField;
        var parentRow = all.find(function (r) {
            return isParentRow(r) && parentKeyFromRow(r).toUpperCase() === keyU;
        });
        var childRows = all.filter(function (r) {
            if (isParentRow(r)) return false;
            return normalizeKey(r[parentField]).toUpperCase() === keyU;
        });

        var displayData = childRows.slice();
        if (parentRow) {
            parentRow._expanded = true;
            displayData.push(parentRow);
        }

        state.expandedKey = key;
        if (typeof table.clearFilter === 'function') table.clearFilter(true);
        if (typeof table.clearSort === 'function') table.clearSort();
        table.setData(displayData).then(function () {
            if (typeof state.opts.onAfterExpand === 'function') state.opts.onAfterExpand(key, displayData);
        });
    }

    function collapse() {
        var was = state.expandedKey;
        state.expandedKey = null;
        var all = getDataset();
        if (all && all.length) {
            all.forEach(function (r) {
                if (r) r._expanded = false;
            });
        }
        var table = getTable();
        if (table && all && all.length && typeof table.getDataCount === 'function') {
            if (table.getDataCount() !== all.length) {
                table.setData(all).then(function () {
                    if (typeof state.opts.onCollapse === 'function') state.opts.onCollapse(was);
                });
                return;
            }
        }
        if (typeof state.opts.onCollapse === 'function') state.opts.onCollapse(was);
    }

    function isExpanded() {
        return !!state.expandedKey;
    }

    function getExpandedKey() {
        return state.expandedKey;
    }

    /** Call at start of applyFilters — clears expand and restores full data if needed. */
    function beforeFilters(done) {
        if (!state.expandedKey) {
            if (typeof done === 'function') done();
            return;
        }
        state.expandedKey = null;
        var all = getDataset();
        var table = getTable();
        if (all && all.length) {
            all.forEach(function (r) {
                if (r) r._expanded = false;
            });
        }
        if (table && all && all.length && table.getDataCount() !== all.length) {
            table.setData(all).then(function () {
                if (typeof done === 'function') done();
            });
            return;
        }
        if (typeof done === 'function') done();
    }

    function bind() {
        if (state.bound) return ParentExpand;
        state.bound = true;
        var btnClass = state.opts.btnClass || 'pm-parent-expand-btn';
        document.addEventListener('click', function (e) {
            var t = e.target;
            if (!t) return;
            var btn = t.closest ? t.closest('.' + btnClass) : null;
            if (!btn) return;
            e.preventDefault();
            e.stopPropagation();
            var parentKey = String(btn.getAttribute('data-parent') || '').trim();
            if (!parentKey) return;
            if (
                state.expandedKey &&
                normalizeKey(state.expandedKey).toUpperCase() === normalizeKey(parentKey).toUpperCase()
            ) {
                collapse();
                return;
            }
            showExpanded(parentKey);
        });
        return ParentExpand;
    }

    var ParentExpand = {
        configure: configure,
        captureDataset: captureDataset,
        setDataset: captureDataset,
        getDataset: getDataset,
        normalizeKey: normalizeKey,
        isParentRow: isParentRow,
        parentKeyFromRow: parentKeyFromRow,
        yellowSvg: yellowSvg,
        columnDef: columnDef,
        expand: showExpanded,
        collapse: collapse,
        isExpanded: isExpanded,
        getExpandedKey: getExpandedKey,
        beforeFilters: beforeFilters,
        bind: bind
    };

    window.ParentExpand = ParentExpand;
})(window);
