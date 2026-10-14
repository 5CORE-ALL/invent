{{--
  One Min NPFT "!" rule for every analytics page.
  Included by channel-std-prc-vs-dil (all channel pages via channel-pef-promo)
  and amazon-std-prc-vs-dil (Amazon via amazon-pef-promo).
  Parts: css, script.
--}}
@php $minNpftMarkPart = $minNpftMarkPart ?? 'script'; @endphp

@if($minNpftMarkPart === 'css' || $minNpftMarkPart === 'all')
        .ch-npft-mark { color: #dc3545; font-weight: 800; margin-left: 3px; font-size: 14px; line-height: 1; }
@endif

@if($minNpftMarkPart === 'script' || $minNpftMarkPart === 'all')
        window.minNpftSpriceMark = window.minNpftSpriceMark || (function() {
            function floorPct() {
                if (typeof chStdMinNpftNow === 'function') {
                    const n = Number(chStdMinNpftNow());
                    if (n > 0) return n;
                }
                if (typeof amzStdMinNpftNow === 'function') {
                    const n = Number(amzStdMinNpftNow());
                    if (n > 0) return n;
                }
                if (typeof chStdMinNpft !== 'undefined' && Number(chStdMinNpft) > 0) return Number(chStdMinNpft);
                if (typeof amzStdMinNpft !== 'undefined' && Number(amzStdMinNpft) > 0) return Number(amzStdMinNpft);
                return 10;
            }
            function snpftOf(d) {
                if (!d) return null;
                if (typeof ebayDilComputedSnpft === 'function') {
                    const v = ebayDilComputedSnpft(d);
                    if (v != null && isFinite(v)) return v;
                }
                if (typeof chStdShownSnpftForMark === 'function') {
                    const v = chStdShownSnpftForMark(d);
                    if (v != null && isFinite(v)) return v;
                }
                if (typeof amazonComputeSpft === 'function') {
                    const v = amazonComputeSpft(d);
                    if (v != null && isFinite(v)) return v;
                }
                if (typeof amzStdShownSpriceForMark === 'function' && typeof amzStdNpftAt === 'function') {
                    const shown = Number(amzStdShownSpriceForMark(d));
                    if (shown > 0) return amzStdNpftAt(shown, d);
                }
                return null;
            }
            function isClearance(d) {
                if (typeof chStdIsClearance === 'function' && chStdIsClearance(d)) return true;
                if (typeof amzStdIsClearance === 'function' && amzStdIsClearance(d)) return true;
                if (typeof amzIsClearanceSku === 'function' && amzIsClearanceSku(d)) return true;
                return false;
            }
            function shouldMark(d) {
                if (!d || d.is_parent_summary || d.is_parent || d.is_parent_row) return false;
                if (isClearance(d)) return false;
                const floor = floorPct();
                if (!(floor > 0)) return false;
                const snpft = snpftOf(d);
                if (snpft == null || !isFinite(snpft)) return false;
                return Math.round(snpft) === Math.round(floor);
            }
            function spriceColumns(tbl) {
                if (!tbl || typeof tbl.getColumns !== 'function') return [];
                let cols = [];
                try { cols = tbl.getColumns(true) || []; } catch (e) { return []; }
                return cols.filter(function(col) {
                    const def = (col && col.getDefinition) ? (col.getDefinition() || {}) : {};
                    const title = String(def.title || '').replace(/<[^>]+>/g, '').trim();
                    const field = String(def.field || '');
                    return /^(s\s*prc|sprice)$/i.test(title) || /^(sprice|sprc|SPRICE)$/i.test(field);
                });
            }
            function stamp(tbl) {
                tbl = tbl || ((typeof table !== 'undefined') ? table : null);
                if (!tbl) return;
                const floor = floorPct();
                spriceColumns(tbl).forEach(function(col) {
                    if (!col || typeof col.getCells !== 'function') return;
                    col.getCells().forEach(function(cell) {
                        const el = cell.getElement ? cell.getElement() : null;
                        if (!el) return;
                        const row = cell.getRow ? cell.getRow() : null;
                        const d = row && row.getData ? row.getData() : null;
                        const on = shouldMark(d);
                        let mark = el.querySelector('.ch-npft-mark');
                        if (!on) {
                            if (mark) mark.remove();
                            return;
                        }
                        if (!mark) {
                            mark = document.createElement('span');
                            mark.className = 'ch-npft-mark';
                            mark.textContent = '!';
                            el.appendChild(mark);
                        }
                        mark.title = 'SNPFT is at Min NPFT ' + floor + '%';
                    });
                });
            }
            function bind(tries) {
                const tbl = (typeof table !== 'undefined') ? table : null;
                if (!tbl || typeof tbl.on !== 'function') {
                    if ((tries || 0) < 40) setTimeout(function() { bind((tries || 0) + 1); }, 250);
                    return;
                }
                if (!tbl._minNpftMarkBound) {
                    tbl._minNpftMarkBound = true;
                    try { tbl.on('renderComplete', function() { stamp(tbl); }); } catch (e) { /* ignore */ }
                }
                stamp(tbl);
            }
            return {
                floor: floorPct,
                snpft: snpftOf,
                shouldMark: shouldMark,
                stamp: stamp,
                bind: bind,
            };
        })();
@endif
