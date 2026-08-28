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
            const LmpIgnore = {
                header: function() {
                    return '<th class="text-center" title="Ignore for L1">Ignore</th>';
                },
                checkbox: function(item, marketplace, sku) {
                    const id = item && item.id != null ? String(item.id) : '';
                    const ignored = !!(item && item.ignored);
                    return '<input type="checkbox" class="form-check-input lmp-ignore-cb" title="Ignore for L1"'
                        + (ignored ? ' checked' : '')
                        + ' data-id="' + lmpIgnoreEsc(id) + '"'
                        + ' data-marketplace="' + lmpIgnoreEsc(marketplace || '') + '"'
                        + ' data-sku="' + lmpIgnoreEsc(sku || '') + '">';
                },
                l1: function(list, priceField) {
                    let l1 = null;
                    (list || []).forEach(function(item) {
                        if (!item || item.ignored) return;
                        const tp = lmpIgnoreItemPrice(item, priceField);
                        if (tp > 0 && (l1 === null || tp < l1)) l1 = tp;
                    });
                    return l1;
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
                        $cb.prop('disabled', true);
                        $.ajax({
                            url: '/cvr-master-lmp-ignore',
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': lmpIgnoreCsrf(), 'Accept': 'application/json' },
                            data: { id: id, marketplace: marketplace, sku: sku, ignored: ignored ? 1 : 0 },
                            success: function(res) {
                                $cb.prop('disabled', false);
                                if (res && res.success) {
                                    if (typeof opts.onToggled === 'function') {
                                        opts.onToggled(id, ignored, $cb);
                                    }
                                    lmpIgnoreToast('success', res.message || (ignored ? 'Ignored for L1' : 'Included in L1'));
                                } else {
                                    $cb.prop('checked', !ignored);
                                    lmpIgnoreToast('error', (res && res.error) || 'Failed to update ignore');
                                }
                            },
                            error: function(xhr) {
                                $cb.prop('disabled', false);
                                $cb.prop('checked', !ignored);
                                lmpIgnoreToast('error', (xhr.responseJSON && xhr.responseJSON.error) || 'Failed to update ignore');
                            }
                        });
                    });
                },
            };
            global.LmpIgnore = LmpIgnore;
        })(window);
@endif
