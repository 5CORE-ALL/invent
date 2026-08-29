{{-- LMP Ignore checkbox — same as /ebay2-tabulator-view. Excludes a competitor from L1. --}}
@php
    $lmpIgnorePart = $lmpIgnorePart ?? 'all';
    $lmpIgnoreModal = $lmpIgnoreModal ?? '#lmpModal';
@endphp
@if($lmpIgnorePart === 'css' || $lmpIgnorePart === 'all')
        {{ $lmpIgnoreModal }} tr.lmp-ignored-row {
            opacity: 0.55;
            background: #f1f3f5 !important;
        }
        {{ $lmpIgnoreModal }} tr.lmp-ignored-row td {
            text-decoration: line-through;
            text-decoration-color: #adb5bd;
        }
        {{ $lmpIgnoreModal }} tr.lmp-ignored-row td:last-child,
        {{ $lmpIgnoreModal }} tr.lmp-ignored-row .lmp-ignore-cb {
            text-decoration: none;
        }
        {{ $lmpIgnoreModal }} .lmp-ignore-cb {
            cursor: pointer;
            width: 1.1em;
            height: 1.1em;
        }
@endif
@if($lmpIgnorePart === 'script' || $lmpIgnorePart === 'all')
        (function(global) {
            if (global.LmpIgnore) return;
            function lmpIgnoreCsrf() {
                return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            }
            function lmpIgnoreToast(type, msg) {
                if (typeof showToast === 'function') {
                    try { showToast(type, msg); } catch (e) { showToast(msg, type); }
                } else if (typeof chPromoToast === 'function') {
                    chPromoToast(type, msg);
                } else {
                    console.log(type, msg);
                }
            }
            function lmpIgnoreEsc(s) {
                return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
            }
            function lmpIgnoreItemPrice(item, priceField) {
                if (!item) return 0;
                if (priceField && item[priceField] != null && item[priceField] !== '') {
                    const n = parseFloat(item[priceField]);
                    if (n > 0) return n;
                }
                const total = parseFloat(item.total_price);
                if (total > 0) return total;
                const price = parseFloat(item.price) || 0;
                const ship = parseFloat(item.shipping_cost) || 0;
                return price + ship;
            }
            function lmpIgnoreIsIgnored(item) {
                if (!item) return false;
                const v = item.ignored;
                if (v === true || v === 1 || v === '1') return true;
                if (typeof v === 'string') {
                    return ['true', 'yes', 'on'].indexOf(v.toLowerCase().trim()) !== -1;
                }
                return false;
            }
            function lmpIgnoreSkuKey(s) {
                return String(s || '').replace(/\s+/g, ' ').trim().toUpperCase();
            }
            const LmpIgnore = {
                header: function() {
                    return '<th class="text-center" title="Ignore for L1">Ignore</th>';
                },
                checkbox: function(item, marketplace, sku) {
                    const id = item && item.id != null ? String(item.id) : '';
                    const ignored = lmpIgnoreIsIgnored(item);
                    return '<input type="checkbox" class="form-check-input lmp-ignore-cb" title="Ignore for L1"'
                        + (ignored ? ' checked' : '')
                        + ' data-id="' + lmpIgnoreEsc(id) + '"'
                        + ' data-marketplace="' + lmpIgnoreEsc(marketplace || '') + '"'
                        + ' data-sku="' + lmpIgnoreEsc(sku || '') + '">';
                },
                isIgnored: lmpIgnoreIsIgnored,
                l1: function(list, priceField) {
                    let l1 = null;
                    (list || []).forEach(function(item) {
                        if (!item || lmpIgnoreIsIgnored(item)) return;
                        const tp = lmpIgnoreItemPrice(item, priceField);
                        if (tp > 0 && (l1 === null || tp < l1)) l1 = tp;
                    });
                    return l1;
                },
                activeCount: function(list, priceField) {
                    let n = 0;
                    (list || []).forEach(function(item) {
                        if (!item || lmpIgnoreIsIgnored(item)) return;
                        if (lmpIgnoreItemPrice(item, priceField) > 0) n++;
                    });
                    return n;
                },
                ignoredPrice: function(list, priceField) {
                    let p = null;
                    (list || []).forEach(function(item) {
                        if (!item || !lmpIgnoreIsIgnored(item)) return;
                        const tp = lmpIgnoreItemPrice(item, priceField);
                        if (tp > 0 && (p === null || tp < p)) p = tp;
                    });
                    return p;
                },
                effectiveLmp: function(row) {
                    if (!row) return 0;
                    const entries = Array.isArray(row.lmp_entries) ? row.lmp_entries : [];
                    if (entries.length) {
                        const l1 = LmpIgnore.l1(entries);
                        return l1 != null && l1 > 0 ? l1 : 0;
                    }
                    return parseFloat(row.lmp_price) || 0;
                },
                applyToRow: function(row) {
                    if (!row || row.is_parent_summary) return row;
                    const entries = Array.isArray(row.lmp_entries) ? row.lmp_entries : [];
                    if (!entries.length) return row;
                    const l1 = LmpIgnore.l1(entries);
                    row.lmp_price = l1;
                    row.lmp_entries_total = LmpIgnore.activeCount(entries);
                    row.lmp_ignored_price = (l1 == null) ? LmpIgnore.ignoredPrice(entries) : null;
                    return row;
                },
                applyDataset: function(rows) {
                    (function walk(list) {
                        (list || []).forEach(function(row) {
                            LmpIgnore.applyToRow(row);
                            if (row && Array.isArray(row._children) && row._children.length) {
                                walk(row._children);
                            }
                        });
                    })(rows);
                    return rows;
                },
                markLocal: function(competitors, id, ignored) {
                    const list = competitors || [];
                    const target = list.find(function(c) { return String(c.id) === String(id); });
                    const itemId = target ? String(target.item_id || '').toLowerCase().trim() : '';
                    list.forEach(function(c) {
                        const sameId = String(c.id) === String(id);
                        const sameItem = itemId !== '' && String(c.item_id || '').toLowerCase().trim() === itemId;
                        if (sameId || sameItem) c.ignored = ignored ? 1 : 0;
                    });
                    return list;
                },
                columnHtml: function(rowData, opts) {
                    opts = opts || {};
                    if (window.ParentExpand && opts.parentAvg !== false) {
                        const avgHtml = ParentExpand.parentAvgLmpHtml(rowData, opts.parentOpts || {});
                        if (avgHtml !== null) return avgHtml;
                    }
                    const sku = String((rowData && rowData['(Child) sku']) || '');
                    const linkedSkus = Array.isArray(rowData.linked_lmp_skus) ? rowData.linked_lmp_skus : [];
                    const entries = Array.isArray(rowData.lmp_entries) ? rowData.lmp_entries : [];
                    const l1 = entries.length ? LmpIgnore.l1(entries) : (parseFloat(rowData.lmp_price) || null);
                    const active = entries.length ? LmpIgnore.activeCount(entries) : (parseInt(rowData.lmp_entries_total, 10) || 0);
                    const ignoredPrice = entries.length
                        ? LmpIgnore.ignoredPrice(entries)
                        : (parseFloat(rowData.lmp_ignored_price) || 0);
                    const currentPrice = parseFloat((rowData && rowData['eBay Price']) || 0);
                    const skuAttr = lmpIgnoreEsc(sku);
                    const linkedAttr = lmpIgnoreEsc(JSON.stringify(linkedSkus));
                    const open = '<a href="#" class="view-lmp-competitors" data-sku="' + skuAttr
                        + '" data-linked-skus="' + linkedAttr
                        + '" style="color:inherit;text-decoration:none;cursor:pointer;white-space:nowrap;" title="Open LMP competitors">';
                    if (l1) {
                        let color = 'inherit';
                        if (opts.colorVsPrice) color = (l1 < currentPrice) ? '#dc3545' : '#28a745';
                        const count = active > 0
                            ? ' <span style="color:#007bff;font-weight:500;font-size:12px;">(' + active + ')</span>'
                            : '';
                        return open + '<span style="color:' + color + ';font-weight:600;font-size:14px;">$'
                            + Number(l1).toFixed(2) + '</span>' + count + '</a>';
                    }
                    if (ignoredPrice > 0) {
                        return open.replace('Open LMP competitors', 'Ignored LMP — not counted')
                            + '<span style="text-decoration:line-through;color:#94a3b8;font-weight:600;font-size:14px;">$'
                            + Number(ignoredPrice).toFixed(2) + '</span>'
                            + ' <i class="fas fa-times" style="color:#dc3545;font-size:10px;margin-left:3px;" title="Ignored — not counted"></i></a>';
                    }
                    if (active > 0) {
                        return '<a href="#" class="view-lmp-competitors" data-sku="' + skuAttr
                            + '" data-linked-skus="' + linkedAttr
                            + '" style="color:#007bff;text-decoration:none;cursor:pointer;font-size:12px;" title="Open LMP competitors">('
                            + active + ')</a>';
                    }
                    return '<a href="#" class="view-lmp-competitors" data-sku="' + skuAttr
                        + '" data-linked-skus="' + linkedAttr
                        + '" style="color:#007bff;text-decoration:none;cursor:pointer;font-size:12px;" title="Add LMP competitors">—</a>';
                },
                patchGrid: function(opts) {
                    opts = opts || {};
                    const entries = Array.isArray(opts.competitors) ? opts.competitors : null;
                    const l1 = entries ? LmpIgnore.l1(entries) : null;
                    const active = entries ? LmpIgnore.activeCount(entries) : null;
                    const ignoredPrice = (l1 == null && entries) ? LmpIgnore.ignoredPrice(entries) : null;
                    const targets = {};
                    const add = function(s) {
                        const k = lmpIgnoreSkuKey(s);
                        if (k) targets[k] = true;
                    };
                    add(opts.sku);
                    (opts.linkedSkus || []).forEach(add);
                    const apply = function(d) {
                        if (!d || d.is_parent_summary) return false;
                        const key = lmpIgnoreSkuKey(d['(Child) sku'] || d.sku);
                        if (!targets[key]) return false;
                        d.lmp_price = l1;
                        d.lmp_ignored_price = ignoredPrice;
                        if (entries) {
                            d.lmp_entries = entries;
                            d.lmp_entries_total = active;
                        }
                        return true;
                    };
                    const walkData = function(list) {
                        (list || []).forEach(function(d) {
                            apply(d);
                            if (d && Array.isArray(d._children)) walkData(d._children);
                        });
                    };
                    if (Array.isArray(opts.dataset)) walkData(opts.dataset);
                    const table = opts.table;
                    if (!table || !table.getRows) return;
                    let rows = [];
                    try { rows = table.getRows('all') || table.getRows() || []; } catch (e) { rows = table.getRows() || []; }
                    const updateRow = function(row) {
                        const d = row.getData();
                        if (!apply(d)) return;
                        const upd = { lmp_price: d.lmp_price, lmp_ignored_price: d.lmp_ignored_price };
                        if (entries) {
                            upd.lmp_entries = d.lmp_entries;
                            upd.lmp_entries_total = d.lmp_entries_total;
                        }
                        try { row.update(upd); } catch (e) { /* tabulator module warning */ }
                    };
                    rows.forEach(function(row) {
                        updateRow(row);
                        if (typeof row.getTreeChildren === 'function') {
                            (row.getTreeChildren() || []).forEach(updateRow);
                        }
                    });
                },
                bind: function(opts) {
                    opts = opts || {};
                    const modal = opts.modal || '#lmpModal';
                    const sel = modal + ' .lmp-ignore-cb';
                    if ($(document).data('lmpIgnoreBound:' + sel)) return;
                    $(document).data('lmpIgnoreBound:' + sel, 1);
                    $(document).on('change', sel, function() {
                        const $cb = $(this);
                        const id = $cb.attr('data-id') || $cb.data('id');
                        const marketplace = String($cb.attr('data-marketplace') || $cb.data('marketplace') || opts.marketplace || '').toLowerCase();
                        const sku = $cb.attr('data-sku') || $cb.data('sku') || (typeof opts.sku === 'function' ? opts.sku() : '') || '';
                        const ignored = $cb.is(':checked');
                        const competitors = typeof opts.competitors === 'function' ? opts.competitors() : null;
                        if (competitors) LmpIgnore.markLocal(competitors, id, ignored);
                        if (typeof opts.onToggled === 'function') opts.onToggled(id, ignored, $cb);
                        $(sel + '[data-id="' + id + '"]').prop('disabled', true);
                        $.ajax({
                            url: '/cvr-master-lmp-ignore',
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': lmpIgnoreCsrf(), 'Accept': 'application/json' },
                            data: { id: id, marketplace: marketplace, sku: sku, ignored: ignored ? 1 : 0 },
                            success: function(res) {
                                $(sel + '[data-id="' + id + '"]').prop('disabled', false);
                                if (res && res.success) {
                                    lmpIgnoreToast('success', res.message || (ignored ? 'Ignored for L1' : 'Included in L1'));
                                } else {
                                    if (competitors) LmpIgnore.markLocal(competitors, id, !ignored);
                                    if (typeof opts.onToggled === 'function') opts.onToggled(id, !ignored, $cb);
                                    lmpIgnoreToast('error', (res && res.error) || 'Failed to update ignore');
                                }
                            },
                            error: function(xhr) {
                                if (competitors) LmpIgnore.markLocal(competitors, id, !ignored);
                                if (typeof opts.onToggled === 'function') opts.onToggled(id, !ignored, $cb);
                                lmpIgnoreToast('error', (xhr.responseJSON && xhr.responseJSON.error) || 'Failed to update ignore');
                            }
                        });
                    });
                },
            };
            global.LmpIgnore = LmpIgnore;
        })(window);
@endif
