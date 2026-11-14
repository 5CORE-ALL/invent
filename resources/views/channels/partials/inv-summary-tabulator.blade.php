{{--
    INV Summary tabulator for /all-marketplace-master.

    Rows:
      - Metrics: inv, Inv@SP, Inv@LP, TAT, Avail (read from the INV badges above)
      - Inv@SP bands: one row per DIL band + Total (from the same slices as the band cards)

    Public API (window.AmmInvSummaryTabulator):
      .render(slices)  store the latest Inv@SP band slices and redraw
      .refresh()       redraw from the badges + last slices
    The table also redraws by itself whenever an INV badge value changes.
--}}
<div class="amm-inv-summary-tabulator-wrap" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:8px 10px;">
    <div class="amm-inv-block-subtitle">INV Summary</div>
    <div id="amm-inv-summary-tabulator"></div>
</div>

<script>
    (function() {
        var TABLE_ID = 'amm-inv-summary-tabulator';
        var table = null;
        var lastSlices = [];
        var refreshTimer = null;

        var TONES = {
            zero: { bg: '#e5e7eb', fg: '#374151' },
            red1: { bg: '#fecaca', fg: '#7f1d1d' },
            red2: { bg: '#fee2e2', fg: '#991b1b' },
            green: { bg: '#dcfce7', fg: '#14532d' },
            pink: { bg: '#fce7f3', fg: '#9d174d' }
        };

        var METRICS = [
            { id: 'm_inv', item: 'inv', sel: '.badge[data-metric="inventory"]', kind: 'money', bg: '#fcd34d', fg: '#000' },
            { id: 'm_sp', item: 'Inv@SP', sel: '.badge[data-metric="inv_at_sp"]', kind: 'money', bg: '#22c55e', fg: '#000' },
            { id: 'm_lp', item: 'Inv@LP', sel: '.badge[data-metric="inv_at_lp"]', kind: 'money', bg: '#ef4444', fg: '#fff' },
            { id: 'm_tat', item: 'TAT', sel: '.badge[data-metric="tat"]', kind: 'num', bg: '#ef4444', fg: '#fff' },
            { id: 'm_avail', item: 'Avail', sel: '[data-metric="fa_avail"]', kind: 'pct', bg: '#fcd34d', fg: '#000' }
        ];

        function kCompact(n) {
            var v = Math.round(Number(n) || 0);
            if (Math.abs(v) >= 1000) return '$' + Math.round(v / 1000).toLocaleString('en-US') + 'K';
            return '$' + v.toLocaleString('en-US');
        }

        // Exact value kept on the badge; falls back to the number in the badge text.
        function badgeValue(selector) {
            var el = document.querySelector(selector);
            if (!el) return null;
            var raw = el.getAttribute('data-exact-value');
            if (raw !== null && raw !== '') {
                var n = parseFloat(raw);
                return isFinite(n) ? n : null;
            }
            var m = String(el.textContent || '').replace(/,/g, '').match(/-?\d+(\.\d+)?/);
            if (!m) return null;
            var t = parseFloat(m[0]);
            return isFinite(t) ? t : null;
        }

        function buildRows() {
            var rows = METRICS.map(function(m) {
                return {
                    id: m.id, section: 'Metrics', item: m.item, kind: m.kind, bg: m.bg, fg: m.fg,
                    count: null, value: badgeValue(m.sel), pct: null
                };
            });

            var total = lastSlices.reduce(function(sum, s) { return sum + (Number(s.inv_at_sp) || 0); }, 0);
            var skuTotal = 0;
            lastSlices.forEach(function(s) {
                var val = Number(s.inv_at_sp) || 0;
                var t = TONES[s.key] || TONES.red1;
                skuTotal += Number(s.count) || 0;
                rows.push({
                    id: 'b_' + s.key, section: 'Inv@SP bands', item: s.label, kind: 'money',
                    bg: t.bg, fg: t.fg, dot: s.color,
                    count: Number(s.count) || 0, value: val, pct: total > 0 ? Math.round((val / total) * 100) : 0
                });
            });
            if (lastSlices.length) {
                rows.push({
                    id: 'b_total', section: 'Inv@SP bands', item: 'Total', kind: 'money', isTotal: true,
                    count: skuTotal, value: total, pct: total > 0 ? 100 : 0
                });
            }
            return rows;
        }

        function draw() {
            var el = document.getElementById(TABLE_ID);
            if (!el || typeof Tabulator === 'undefined') return;
            var rows = buildRows();

            if (table) {
                table.setData(rows);
                return;
            }

            table = new Tabulator(el, {
                layout: 'fitColumns',
                data: rows,
                height: false,
                index: 'id',
                groupBy: 'section',
                groupHeader: function(value) { return '<span style="font-weight:800;">' + value + '</span>'; },
                columns: [
                    {
                        title: 'Item', field: 'item', headerSort: false, widthGrow: 2,
                        formatter: function(cell) {
                            var d = cell.getData();
                            if (d.isTotal) return '<span style="font-weight:800;">Total</span>';
                            var dot = d.dot
                                ? '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;margin-right:6px;background:' + d.dot + ';"></span>'
                                : '';
                            return '<span style="display:inline-block;padding:2px 10px;border-radius:6px;font-weight:800;background:'
                                + d.bg + ';color:' + d.fg + ';">' + dot + d.item + '</span>';
                        }
                    },
                    {
                        title: 'SKUs', field: 'count', hozAlign: 'right', headerSort: false,
                        formatter: function(cell) { var v = cell.getValue(); return v === null || v === undefined ? '' : v; }
                    },
                    {
                        title: 'Value', field: 'value', hozAlign: 'right', headerSort: false,
                        formatter: function(cell) {
                            var d = cell.getData();
                            var v = cell.getValue();
                            if (v === null || v === undefined) return '-';
                            if (d.kind === 'pct') return Math.round(v) + '%';
                            if (d.kind === 'num') return Number(v).toFixed(2);
                            return kCompact(v);
                        }
                    },
                    {
                        title: '% of total', field: 'pct', hozAlign: 'right', headerSort: false,
                        formatter: function(cell) { var v = cell.getValue(); return v === null || v === undefined ? '' : v + '%'; }
                    }
                ]
            });
        }

        function scheduleRefresh() {
            clearTimeout(refreshTimer);
            refreshTimer = setTimeout(draw, 80);
        }

        // Redraw whenever an INV badge changes (TAT, inv, Inv@SP, Inv@LP, Avail are filled in at different times).
        function watchBadges() {
            if (typeof MutationObserver === 'undefined') return;
            var observer = new MutationObserver(scheduleRefresh);
            METRICS.forEach(function(m) {
                var el = document.querySelector(m.sel);
                if (el) {
                    observer.observe(el, {
                        attributes: true,
                        attributeFilter: ['data-exact-value'],
                        childList: true,
                        characterData: true,
                        subtree: true
                    });
                }
            });
        }

        window.AmmInvSummaryTabulator = {
            render: function(slices) {
                if (Array.isArray(slices)) lastSlices = slices;
                draw();
            },
            refresh: draw
        };

        watchBadges();
    })();
</script>
