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
{{-- Optional 'chClrMetric' => 'clearance_count' adds a rolling-history dot (page must support ebay1-badge-chart, e.g. /ebay-tabulator-view). --}}
@php $chClrMetric = $chClrMetric ?? null; @endphp
<span class="badge fs-6 p-2{{ $chClrMetric ? ' ebay1-badge-chart' : '' }}" id="{{ $chClrBadgeId }}"
    @if($chClrMetric) data-filter-badge="1" data-metric="{{ $chClrMetric }}" data-live-value="" data-format="number" @endif
    style="background-color:#dc3545;color:#fff;font-weight:700;cursor:pointer;"
    title="C = Clearance SKUs (Inv days clearance = YES). Click to show only those SKUs. Click again to clear."
    aria-label="Clearance SKUs">@if($chClrMetric)<span class="summary-trend-dot none" data-metric="{{ $chClrMetric }}" title="Rolling history"></span>@endif C 0</span>
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

            /**
             * All S PRC capping icons, same rules as /amazon-tabulator-view (copied from its S PRC cell).
             * raw = the rule price before any cap, shown = that price after the LMP cap and the Std Prc maximum.
             *   red triangle    : raw reached LMP (capped at LMP, or LMP not applied because SNROI at LMP < 20%)
             *   orange triangle : LMP is above Std Prc and the price stopped at Std Prc (review the Std price)
             *   brown triangle  : the price was capped at Std Prc
             * An orange or brown cap replaces the red one. `color` is the value colour to use ('' = none).
             * o = { raw, shown, lmp, std, sgroiAtLmp }
             */
            function capFlags(o) {
                var raw = Number(o.raw) || 0;
                var shown = Number(o.shown) || 0;
                var lmpNow = Number(o.lmp) || 0;
                var stdNow = Number(o.std) || 0;
                var sgroiAtLmp = (o.sgroiAtLmp == null || !isFinite(o.sgroiAtLmp)) ? null : Number(o.sgroiAtLmp);
                var wouldHitLmp = lmpNow > 0 && raw + 0.0001 >= lmpNow;
                var appliedLmp = wouldHitLmp && shown + 0.0001 <= lmpNow + 0.0001;
                var atOrAboveLmp = wouldHitLmp;
                var lmpAboveStd = lmpNow > 0 && stdNow > 0 && lmpNow + 0.0001 > stdNow;
                var stdCappedHighLmp = lmpAboveStd && raw + 0.0001 > stdNow && shown + 0.0001 <= stdNow + 0.0001;
                var stdCapped = !lmpAboveStd && !appliedLmp && stdNow > 0 && raw + 0.0001 > stdNow && shown + 0.0001 <= stdNow + 0.0001;
                var tri = function (color, title) {
                    return '<i class="fas fa-exclamation-triangle" style="color:' + color + ';font-size:10px;margin-left:3px;" title="' + title + '"></i>';
                };
                var redTri = (atOrAboveLmp && !stdCappedHighLmp && !stdCapped)
                    ? (appliedLmp
                        ? tri('#dc3545', 'S PRC capped at LMP $' + lmpNow.toFixed(2))
                        : tri('#dc3545', 'LMP $' + lmpNow.toFixed(2) + ' not applied — SGROI would be '
                            + (sgroiAtLmp != null ? sgroiAtLmp.toFixed(1) : '?') + '% (&lt; 20%)'))
                    : '';
                var reviewTri = stdCappedHighLmp
                    ? tri('#fd7e14', 'LMP $' + lmpNow.toFixed(2) + ' is above Std Prc $' + stdNow.toFixed(2) + ' — S PRC capped at Std. Review Std price.')
                    : '';
                var stdTri = stdCapped
                    ? tri('#b45309', 'S PRC capped at Std Prc $' + stdNow.toFixed(2))
                    : '';
                var color = '';
                if (atOrAboveLmp && !stdCappedHighLmp && !stdCapped) color = '#dc3545';
                else if (stdCappedHighLmp) color = '#fd7e14';
                else if (stdCapped) color = '#b45309';
                return {
                    atOrAboveLmp: atOrAboveLmp, appliedLmp: appliedLmp, stdCapped: stdCapped, stdCappedHighLmp: stdCappedHighLmp,
                    color: color, redTri: redTri, reviewTri: reviewTri, stdTri: stdTri
                };
            }

            /** Stock units on a row (Shopify INV on every analytics page). */
            function rowInv(d) {
                var n = parseFloat(d && (d.INV != null ? d.INV : d.inv));
                return isFinite(n) ? n : 0;
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
                var n = 0, inv = 0;
                try {
                    rows().forEach(function (d) {
                        if (!isClearance(d)) return;
                        n++;
                        inv += rowInv(d);
                    });
                } catch (e) { /* ignore */ }
                var $b = $('#' + ctx.badgeId);
                // Keep the history dot (it sits before the label) when the page gave the badge one.
                var $dot = $b.find('.summary-trend-dot').detach();
                $b.text('C ' + n.toLocaleString() + (ctx.showInv ? ' · INV ' + Math.round(inv).toLocaleString() : ''));
                if ($dot.length) $b.prepend($dot);
                if (ctx.showInv) {
                    $b.attr('title', 'C = Clearance SKUs (Inv days clearance = YES): ' + n.toLocaleString() + ' SKUs holding '
                        + Math.round(inv).toLocaleString() + ' units of INV. Click to show only those SKUs. Click again to clear. Click dot for rolling history.');
                }
                // Count / INV over every row (not only the filtered ones), for the page's history report.
                if (typeof ctx.onCount === 'function') {
                    var allN = 0, allInv = 0;
                    try {
                        var t = ctx.getTable ? ctx.getTable() : null;
                        var all = t && typeof t.getData === 'function' ? t.getData() : [];
                        (Array.isArray(all) ? all : []).forEach(function (d) {
                            if (!isClearance(d)) return;
                            allN++;
                            allInv += rowInv(d);
                        });
                    } catch (e) { /* ignore */ }
                    ctx.onCount(allN, Math.round(allInv));
                }
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
                $(document).off('click.chClr').on('click.chClr', '#' + ctx.badgeId, function (e) {
                    // A click on the history dot opens the chart, it does not filter.
                    if (e && e.target && $(e.target).closest('.summary-trend-dot').length) return;
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
                capFlags: capFlags,
                refresh: refresh,
                filterOn: function () { return filterOn; },
                filterFn: function (d) { return isClearance(d); }
            };
        })();
@endif
