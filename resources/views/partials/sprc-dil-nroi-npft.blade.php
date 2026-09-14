{{--
  Shared by ebay-sprc-dil (every Dil tabulator).
  If the page has no NROI / NPFT column, add them next to GROI / GPFT.
  Ads% = 0 → NROI shows GROI, NPFT shows GPFT.
  Ads% > 0 → NROI / NPFT use the net formula.
--}}
        function ebayDilColInfo(col) {
            const def = (col && col.getDefinition) ? (col.getDefinition() || {}) : {};
            return { col: col, title: String(def.title || ''), field: String(def.field || '') };
        }
        function ebayDilColMatch(info, re) {
            return re.test(info.title) || re.test(info.field);
        }
        function ebayDilIsNroiCol(info) { return ebayDilColMatch(info, /nroi/i); }
        function ebayDilIsNpftCol(info) { return ebayDilColMatch(info, /npft/i); }
        function ebayDilIsGroiCol(info) {
            if (ebayDilIsNroiCol(info) || /sgroi/i.test(info.title) || /sgroi/i.test(info.field)) return false;
            return /groi/i.test(info.title) || /^(groi%?|roi%?)$/i.test(info.field);
        }
        function ebayDilIsGpftCol(info) {
            if (ebayDilIsNpftCol(info) || /sgpft/i.test(info.title) || /sgpft/i.test(info.field)) return false;
            if (/gpft\s*\$/i.test(info.title) || /gpft\$/i.test(info.field)) return false;
            return /gpft/i.test(info.title) || /^(gpft%?|pft_pct|pft %)$/i.test(info.field);
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
        function ebayDilRowGroiValue(d) {
            const n = ebayDilFirstNumber(d, ['GROI%', 'GROI', 'groi', 'ROI%', 'roi', 'SROI']);
            return n == null ? 0 : n;
        }
        function ebayDilRowGpftValue(d) {
            const n = ebayDilFirstNumber(d, ['GPFT%', 'GPFT', 'gpft', 'pft_pct', 'PFT %', 'PFT%']);
            return n == null ? 0 : n;
        }
        function ebayDilRowListedPrice(d) {
            return ebayDilFirstNumber(d, [
                'Price', 'price', 'eBay Price', 'EBAY PRICE', 'listed_price', 'Shopify Price', 'Sp. Price'
            ]) || 0;
        }
        function ebayDilComputedNroi(d) {
            const ads = (typeof ebayDilAdsPct === 'function') ? ebayDilAdsPct() : 0;
            const groi = ebayDilRowGroiValue(d);
            if (!(ads > 0)) return groi;
            const price = ebayDilRowListedPrice(d);
            const lp = parseFloat(d && d.LP_productmaster) || 0;
            if (!(price > 0) || !(lp > 0)) return groi;
            const ship = (typeof ebayDgExcludeShip === 'function' && ebayDgExcludeShip())
                ? 0
                : (parseFloat(d && d.Ship_productmaster) || 0);
            const margin = (typeof ebayDilTakehomeMargin === 'function') ? ebayDilTakehomeMargin(d) : 0.80;
            return ((price * margin - lp - ship - price * ads / 100) / lp) * 100;
        }
        function ebayDilComputedNpft(d) {
            const ads = (typeof ebayDilAdsPct === 'function') ? ebayDilAdsPct() : 0;
            const gpft = ebayDilRowGpftValue(d);
            return (ads > 0) ? (gpft - ads) : gpft;
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
                if (d.is_parent || d.is_parent_summary || d._children) {
                    if (d.is_parent || d.is_parent_summary) return '<span style="color:#6c757d;">–</span>';
                }
                const v = kind === 'npft' ? ebayDilComputedNpft(d) : ebayDilComputedNroi(d);
                if (!isFinite(v)) return '';
                const ads = (typeof ebayDilAdsPct === 'function') ? ebayDilAdsPct() : 0;
                const tip = ads > 0
                    ? (kind === 'npft' ? ('NPFT = GPFT − Ads% (' + ads + ')') : ('NROI = net of Ads% (' + ads + ')'))
                    : (kind === 'npft' ? 'NPFT = GPFT (no Ads%)' : 'NROI = GROI (no Ads%)');
                const color = ebayDilPctColor(v, kind === 'npft' ? 'gpft' : 'groi');
                return '<span title="' + String(tip).replace(/"/g, '&quot;') + '" style="color:' + color + ';font-weight:600;">'
                    + Math.round(v) + '%</span>';
            };
        }
        function ebayDilNetColumnDef(title, field, kind) {
            return {
                title: title,
                field: field,
                hozAlign: 'center',
                sorter: 'number',
                width: 58,
                headerTooltip: kind === 'npft'
                    ? (ebayDilAdsPct() > 0 ? 'NPFT = GPFT − Ads%.' : 'NPFT = GPFT (this page has no Ads%).')
                    : (ebayDilAdsPct() > 0 ? 'NROI = net of Ads%.' : 'NROI = GROI (this page has no Ads%).'),
                formatter: ebayDilNetColFormatter(kind),
                accessorDownload: function(value, d) {
                    const v = kind === 'npft' ? ebayDilComputedNpft(d || {}) : ebayDilComputedNroi(d || {});
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
            const hasNroi = infos.some(ebayDilIsNroiCol);
            const hasNpft = infos.some(ebayDilIsNpftCol);
            const groi = infos.find(ebayDilIsGroiCol);
            const gpft = infos.find(ebayDilIsGpftCol);
            if (hasNroi && hasNpft) {
                tbl._ebayDilNetColsAdded = true;
                return true;
            }
            if (!groi && !gpft && hasNroi && hasNpft) {
                tbl._ebayDilNetColsAdded = true;
                return true;
            }
            try {
                if (!hasNroi && groi && groi.field && typeof tbl.addColumn === 'function') {
                    tbl.addColumn(ebayDilNetColumnDef('NROI', 'NROI', 'nroi'), false, groi.field);
                } else if (!hasNroi && gpft && gpft.field && typeof tbl.addColumn === 'function') {
                    tbl.addColumn(ebayDilNetColumnDef('NROI', 'NROI', 'nroi'), false, gpft.field);
                }
                if (!hasNpft && gpft && gpft.field && typeof tbl.addColumn === 'function') {
                    tbl.addColumn(ebayDilNetColumnDef('NPFT', 'NPFT', 'npft'), false, gpft.field);
                } else if (!hasNpft && groi && groi.field && typeof tbl.addColumn === 'function') {
                    tbl.addColumn(ebayDilNetColumnDef('NPFT', 'NPFT', 'npft'), false, groi.field);
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
            setTimeout(run, 2000);
        }
        window.ebayDilEnsureNetColumns = ebayDilEnsureNetColumns;
        window.ebayDilComputedNroi = ebayDilComputedNroi;
        window.ebayDilComputedNpft = ebayDilComputedNpft;
        if (typeof jQuery !== 'undefined') {
            jQuery(ebayDilBindNetColumns);
        } else {
            ebayDilBindNetColumns();
        }
