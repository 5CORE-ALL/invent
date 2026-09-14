{{--
  Shared by ebay-sprc-dil (every Dil tabulator). Blade-only — no per-channel SH/service.
  If the page has no SNROI / SNPFT column, add them next to SGROI / SGPFT.
  Ads% = 0 → SNROI shows SGROI, SNPFT shows SGPFT.
  Ads% > 0 → SNROI / SNPFT use the net formula from S PRC.
--}}
        function ebayDilColInfo(col) {
            const def = (col && col.getDefinition) ? (col.getDefinition() || {}) : {};
            return { col: col, title: String(def.title || ''), field: String(def.field || '') };
        }
        function ebayDilIsSnroiCol(info) {
            return /snroi/i.test(info.title) || /snroi/i.test(info.field);
        }
        function ebayDilIsSnpftCol(info) {
            return /snpft/i.test(info.title) || /snpft/i.test(info.field);
        }
        function ebayDilIsSgroiCol(info) {
            if (ebayDilIsSnroiCol(info)) return false;
            return /s\s*groi/i.test(info.title)
                || /sgroi/i.test(info.field)
                || /^s\s*roi/i.test(info.title)
                || /^(sroi|sgroi)$/i.test(info.field);
        }
        function ebayDilIsSgpftCol(info) {
            if (ebayDilIsSnpftCol(info)) return false;
            return /s\s*gpft/i.test(info.title) || /sgpft/i.test(info.field);
        }
        function ebayDilIsSpriceCol(info) {
            return /^(s\s*prc|sprice)$/i.test(String(info.title).trim())
                || /^(sprice|sprc)$/i.test(info.field);
        }
        function ebayDilFirstNumber(d, keys) {
            if (!d) return null;
            for (let i = 0; i < keys.length; i++) {
                const raw = d[keys[i]];
                if (raw == null || raw === '' || raw === '-') continue;
                const n = parseFloat(raw);
                if (isFinite(n)) return n;
            }
            return null;
        }
        function ebayDilRowSprice(d) {
            return ebayDilFirstNumber(d, ['SPRICE', 'sprice', 'SPRC_DIL', 'sprc_dil']) || 0;
        }
        function ebayDilRowShip(d) {
            if (typeof ebayDgExcludeShip === 'function' && ebayDgExcludeShip()) return 0;
            return parseFloat(d && d.Ship_productmaster) || 0;
        }
        function ebayDilRowSgroi(d) {
            const n = ebayDilFirstNumber(d, ['SGROI', 'SROI', 'sgroi', 'sroi']);
            if (n != null) return n;
            const price = ebayDilRowSprice(d);
            const lp = parseFloat(d && d.LP_productmaster) || 0;
            if (!(price > 0) || !(lp > 0)) return 0;
            const margin = (typeof ebayDilTakehomeMargin === 'function') ? ebayDilTakehomeMargin(d) : 0.80;
            return ((price * margin - lp - ebayDilRowShip(d)) / lp) * 100;
        }
        function ebayDilRowSgpft(d) {
            const n = ebayDilFirstNumber(d, ['SGPFT', 'sgpft']);
            if (n != null) return n;
            const price = ebayDilRowSprice(d);
            if (!(price > 0)) return 0;
            const lp = parseFloat(d && d.LP_productmaster) || 0;
            const margin = (typeof ebayDilTakehomeMargin === 'function') ? ebayDilTakehomeMargin(d) : 0.80;
            return ((price * margin - lp - ebayDilRowShip(d)) / price) * 100;
        }
        function ebayDilComputedSnroi(d) {
            const ads = (typeof ebayDilAdsPct === 'function') ? ebayDilAdsPct() : 0;
            const sgroi = ebayDilRowSgroi(d);
            if (!(ads > 0)) return sgroi;
            const price = ebayDilRowSprice(d);
            const lp = parseFloat(d && d.LP_productmaster) || 0;
            if (!(price > 0) || !(lp > 0)) return sgroi;
            const margin = (typeof ebayDilTakehomeMargin === 'function') ? ebayDilTakehomeMargin(d) : 0.80;
            return ((price * margin - lp - ebayDilRowShip(d) - price * ads / 100) / lp) * 100;
        }
        function ebayDilComputedSnpft(d) {
            const ads = (typeof ebayDilAdsPct === 'function') ? ebayDilAdsPct() : 0;
            const sgpft = ebayDilRowSgpft(d);
            return (ads > 0) ? (sgpft - ads) : sgpft;
        }
        function ebayDilPctColor(v, kind) {
            if (kind === 'gpft') {
                if (v < 10) return '#a00211';
                if (v < 15) return '#ffc107';
                if (v < 20) return '#3591dc';
                if (v <= 40) return '#28a745';
                return '#e83e8c';
            }
            if (v < 40) return '#a00211';
            if (v < 75) return '#ffc107';
            if (v < 125) return '#28a745';
            return '#d63384';
        }
        function ebayDilNetColFormatter(kind) {
            return function(cell) {
                const d = (cell.getRow() && cell.getRow().getData) ? (cell.getRow().getData() || {}) : {};
                if (d.is_parent || d.is_parent_summary) {
                    return '<span style="color:#6c757d;">–</span>';
                }
                const v = kind === 'snpft' ? ebayDilComputedSnpft(d) : ebayDilComputedSnroi(d);
                if (!isFinite(v)) return '';
                const ads = (typeof ebayDilAdsPct === 'function') ? ebayDilAdsPct() : 0;
                const tip = ads > 0
                    ? (kind === 'snpft'
                        ? ('SNPFT = SGPFT − Ads% (' + ads + ')')
                        : ('SNROI from S PRC, net of Ads% (' + ads + ')'))
                    : (kind === 'snpft'
                        ? 'SNPFT = SGPFT (this page has no Ads%)'
                        : 'SNROI = SGROI (this page has no Ads%)');
                const color = ebayDilPctColor(v, kind === 'snpft' ? 'gpft' : 'groi');
                return '<span title="' + String(tip).replace(/"/g, '&quot;') + '" style="color:' + color + ';font-weight:600;">'
                    + Math.round(v) + '%</span>';
            };
        }
        function ebayDilNetColumnDef(title, field, kind) {
            const ads = (typeof ebayDilAdsPct === 'function') ? ebayDilAdsPct() : 0;
            return {
                title: title,
                field: field,
                hozAlign: 'center',
                sorter: 'number',
                width: 58,
                headerTooltip: kind === 'snpft'
                    ? (ads > 0 ? 'SNPFT = SGPFT − Ads%.' : 'SNPFT = SGPFT (this page has no Ads%).')
                    : (ads > 0 ? 'SNROI from S PRC, net of Ads%.' : 'SNROI = SGROI (this page has no Ads%).'),
                formatter: ebayDilNetColFormatter(kind),
                accessorDownload: function(value, d) {
                    const v = kind === 'snpft' ? ebayDilComputedSnpft(d || {}) : ebayDilComputedSnroi(d || {});
                    return isFinite(v) ? Math.round(v) : '';
                },
            };
        }
        function ebayDilEnsureNetColumns(tbl) {
            tbl = tbl || ((typeof table !== 'undefined') ? table : null);
            if (!tbl || typeof tbl.getColumns !== 'function') return false;
            if (tbl._ebayDilNetColsAdded) return true;
            let cols = [];
            try { cols = tbl.getColumns(true) || []; } catch (e) { return false; }
            if (!cols.length) return false;
            const infos = cols.map(ebayDilColInfo);
            const hasSnroi = infos.some(ebayDilIsSnroiCol);
            const hasSnpft = infos.some(ebayDilIsSnpftCol);
            const sgroi = infos.find(ebayDilIsSgroiCol);
            const sgpft = infos.find(ebayDilIsSgpftCol);
            const sprice = infos.find(ebayDilIsSpriceCol);
            if (hasSnroi && hasSnpft) {
                tbl._ebayDilNetColsAdded = true;
                return true;
            }
            const after = (sgroi && sgroi.field) || (sgpft && sgpft.field) || (sprice && sprice.field);
            if (!after) return false;
            try {
                if (!hasSnroi && typeof tbl.addColumn === 'function') {
                    tbl.addColumn(ebayDilNetColumnDef('SNROI', 'SNROI', 'snroi'), false, after);
                }
                if (!hasSnpft && typeof tbl.addColumn === 'function') {
                    const npftAfter = (sgpft && sgpft.field) || 'SNROI' || after;
                    tbl.addColumn(ebayDilNetColumnDef('SNPFT', 'SNPFT', 'snpft'), false, npftAfter);
                }
                tbl._ebayDilNetColsAdded = true;
                return true;
            } catch (e) {
                return false;
            }
        }
        function ebayDilBindNetColumns() {
            if (typeof table === 'undefined' || !table || !table.on) {
                setTimeout(ebayDilBindNetColumns, 400);
                return;
            }
            if (table._ebayDilNetColsBound) return;
            table._ebayDilNetColsBound = true;
            const run = function() { ebayDilEnsureNetColumns(table); };
            try { table.on('tableBuilt', run); } catch (e) { /* ignore */ }
            try { table.on('dataLoaded', run); } catch (e) { /* ignore */ }
            run();
            setTimeout(run, 800);
            setTimeout(run, 2500);
        }
        window.ebayDilEnsureNetColumns = ebayDilEnsureNetColumns;
        window.ebayDilComputedSnroi = ebayDilComputedSnroi;
        window.ebayDilComputedSnpft = ebayDilComputedSnpft;
        if (typeof jQuery !== 'undefined') {
            jQuery(ebayDilBindNetColumns);
        } else {
            ebayDilBindNetColumns();
        }
