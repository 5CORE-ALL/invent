{{--
    Clearance "C" mark, Std Prc cap triangles and the header "C n" badge (same as /amazon-tabulator-view), for eBay 1/2/3 and Temu 1/2/3.

    Two parts:
      @include('partials.channel-clearance-badge', ['chClrPart' => 'badge', 'chClrBadgeId' => 'ebay1-clearance-badge'])   // header badge HTML
      @include('partials.channel-clearance-badge', ['chClrPart' => 'script'])                                              // inside the page <script>

    Clearance = SKU is on the Inv days clearance list (YES). The list comes from chStdLoadClearance() in channel-std-prc-vs-dil,
    so this is display only: it does not change any price. After the table and applyFilters() exist, the page calls
        chClr.init({ badgeId: '...', getTable: function () { return table; }, applyFilters: applyFilters });
    and applyFilters() adds  if (window.chClr && chClr.filterOn()) table.addFilter(chClr.filterFn);
--}}
@php $chClrPart = $chClrPart ?? 'script'; @endphp
@if($chClrPart === 'badge')
<span class="badge fs-6 p-2" id="{{ $chClrBadgeId }}"
    style="background-color:#dc3545;color:#fff;font-weight:700;cursor:pointer;"
    title="C = Clearance SKUs (Inv days clearance = YES). Click to show only those SKUs. Click again to clear."
    aria-label="Clearance SKUs">C 0</span>
@else
        // ---- Clearance "C" + Std cap marks (partials/channel-clearance-badge) ----
        window.chClr = (function () {
            var ctx = null;
            var filterOn = false;

            function isChildRow(d) {
                if (!d || d.is_parent_summary || d.is_parent_row) return false;
                var sku = String(d['(Child) sku'] || d.sku || d.Sku || '').trim().toUpperCase();
                return sku !== '' && sku.indexOf('PARENT') !== 0;
            }
            function isClearance(d) {
                return isChildRow(d) && typeof window.chStdIsClearance === 'function' && !!window.chStdIsClearance(d);
            }
            /** Red round "C" after the S PRC value. */
            function markHtml(d) {
                if (!isClearance(d)) return '';
                return '<span class="ch-clr-mark" title="Clearance (Inv days clearance = YES)" style="display:inline-flex;align-items:center;justify-content:center;width:16px;height:16px;border-radius:50%;background:#dc3545;color:#fff;font-size:10px;font-weight:800;line-height:1;margin-left:3px;">C</span>';
            }
            /**
             * Std Prc cap marks for a S PRC that sits on the Std Prc (Std Prc is the maximum).
             * kind 'review' (orange): LMP is above Std Prc, so S PRC stopped at Std. Review the Std price.
             * kind 'std' (brown): S PRC capped at Std Prc.
             * Returns { kind, color, html }. Empty kind = no Std mark; the page keeps its own LMP triangle.
             */
            function stdMarks(d, shown, lmp) {
                var out = { kind: '', color: '', html: '' };
                if (!window.SpriceLmpCap || typeof SpriceLmpCap.stdOf !== 'function') return out;
                var std = SpriceLmpCap.stdOf(d);
                var s = parseFloat(shown);
                if (!(std > 0) || !(s > 0)) return out;
                if (Math.abs(s - std) > 0.005) return out;
                var l = parseFloat(lmp);
                var lmpAboveStd = l > 0 && l + 0.0001 > std;
                if (lmpAboveStd) {
                    out.kind = 'review';
                    out.color = '#fd7e14';
                    out.html = '<i class="fas fa-exclamation-triangle" style="color:#fd7e14;font-size:10px;margin-left:3px;" title="LMP $'
                        + l.toFixed(2) + ' is above Std Prc $' + std.toFixed(2) + ' — S PRC capped at Std. Review Std price."></i>';
                    return out;
                }
                if (l > 0 && s + 0.0001 >= l) return out;
                out.kind = 'std';
                out.color = '#b45309';
                out.html = '<i class="fas fa-exclamation-triangle" style="color:#b45309;font-size:10px;margin-left:3px;" title="S PRC capped at Std Prc $'
                    + std.toFixed(2) + '"></i>';
                return out;
            }

            function rows() {
                var t = ctx && ctx.getTable ? ctx.getTable() : null;
                if (!t || typeof t.getData !== 'function') return [];
                var active = t.getData('active');
                if (Array.isArray(active) && active.length) return active;
                var all = t.getData();
                return Array.isArray(all) ? all : [];
            }
            function refresh() {
                if (!ctx || !ctx.badgeId) return;
                var n = 0;
                try {
                    rows().forEach(function (d) { if (isClearance(d)) n++; });
                } catch (e) { /* ignore */ }
                var $b = $('#' + ctx.badgeId);
                $b.text('C ' + n.toLocaleString());
                $b.css({ outline: filterOn ? '3px solid #ffc107' : '', outlineOffset: filterOn ? '2px' : '' });
            }
            function repaint() {
                var t = ctx && ctx.getTable ? ctx.getTable() : null;
                if (t && typeof t.redraw === 'function') {
                    try { t.redraw(true); } catch (e) { /* table not built yet */ }
                }
            }
            function init(opts) {
                ctx = opts || {};
                if (!ctx.badgeId) return;
                $(document).off('click.chClr').on('click.chClr', '#' + ctx.badgeId, function () {
                    filterOn = !filterOn;
                    if (typeof ctx.applyFilters === 'function') ctx.applyFilters();
                    refresh();
                });
                // Header badge filters are one at a time: clicking another filter badge clears this one first.
                // Capture phase, so it runs before that badge's own handler calls applyFilters().
                if (!window.chClrCaptureBound) {
                    window.chClrCaptureBound = true;
                    document.addEventListener('click', function (e) {
                        if (!filterOn || !ctx || !e.target || !e.target.closest) return;
                        var b = e.target.closest('.badge[id]');
                        if (!b || b.id === ctx.badgeId || b.id.indexOf('avg-') === 0) return;
                        if (!/triangle|lmp|ended|sold|price|clearance/i.test(b.id)) return;
                        filterOn = false;
                        refresh();
                    }, true);
                }
                // Rule and clearance list are loaded: the cells were redrawn, recount the badge.
                $(document).off('chStd:settled.chClr').on('chStd:settled.chClr', refresh);
                (function loadList(tries) {
                    if (typeof window.chStdLoadClearance !== 'function') {
                        if (tries < 40) setTimeout(function () { loadList(tries + 1); }, 250);
                        return;
                    }
                    window.chStdLoadClearance().always(function () {
                        // Cells painted before the list arrived: repaint so the "C" shows.
                        repaint();
                        refresh();
                    });
                })(0);
            }

            return {
                init: init,
                isClearance: isClearance,
                markHtml: markHtml,
                stdMarks: stdMarks,
                refresh: refresh,
                filterOn: function () { return filterOn; },
                filterFn: function (d) { return isClearance(d); }
            };
        })();
@endif
