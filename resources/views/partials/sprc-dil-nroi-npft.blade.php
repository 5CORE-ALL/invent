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
            if (typeof chPromoTableSprice === 'function') {
                const saved = Number(chPromoTableSprice(d));
                if (saved > 0) return saved;
            }
            if (typeof chPromoSavedOrLiveSprice === 'function') {
                const saved = Number(chPromoSavedOrLiveSprice(d));
                if (saved > 0) return saved;
            }
            return ebayDilFirstNumber(d, ['SPRICE', 'sprice']) || 0;
        }
        function ebayDilRowShip(d) {
            if (typeof ebayDgExcludeShip === 'function' && ebayDgExcludeShip()) return 0;
            if (typeof chPromoShipCost === 'function') {
                try {
                    const s = Number(chPromoShipCost(d));
                    if (isFinite(s) && s >= 0) return s;
                } catch (e) { /* fall through */ }
            }
            return parseFloat(d && (d.Ship_productmaster != null ? d.Ship_productmaster : d.ship)) || 0;
        }
        function ebayDilRowLp(d) {
            if (typeof chPromoLp === 'function') {
                try {
                    const lp = Number(chPromoLp(d));
                    if (isFinite(lp) && lp > 0) return lp;
                } catch (e) { /* fall through */ }
            }
            return parseFloat(d && (d.LP_productmaster != null ? d.LP_productmaster : d.lp)) || 0;
        }
        function ebayDilLiveSMetrics(d) {
            if (typeof aeSpriceMetrics === 'function') {
                const m = aeSpriceMetrics(d) || {};
                return { sgroi: Number(m.sroi) || 0, sgpft: Number(m.sgpft) || 0 };
            }
            const price = ebayDilRowSprice(d);
            const lp = ebayDilRowLp(d);
            if (!(price > 0)) return { sgroi: 0, sgpft: 0 };
            const margin = (typeof ebayDilTakehomeMargin === 'function') ? ebayDilTakehomeMargin(d) : 0.80;
            const ship = ebayDilRowShip(d);
            const gross = price * margin - lp - ship;
            return {
                sgroi: lp > 0 ? (gross / lp) * 100 : 0,
                sgpft: (gross / price) * 100,
            };
        }
        function ebayDilRowSgroi(d) {
            return ebayDilLiveSMetrics(d).sgroi;
        }
        function ebayDilRowSgpft(d) {
            return ebayDilLiveSMetrics(d).sgpft;
        }
        function ebayDilComputedSnroi(d) {
            const ads = (typeof ebayDilAdsPct === 'function') ? ebayDilAdsPct() : 0;
            const sgroi = ebayDilRowSgroi(d);
            if (!(ads > 0)) return sgroi;
            const price = ebayDilRowSprice(d);
            const lp = ebayDilRowLp(d);
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
                const st = (window.MetricPctColors && typeof MetricPctColors.styleForCellColor === 'function')
                    ? MetricPctColors.styleForCellColor(color)
                    : (color === '#ffc107'
                        ? 'color:#000;background-color:#ffc107;font-weight:700;padding:1px 5px;border-radius:3px;'
                        : ('color:' + color + ';font-weight:600;'));
                return '<span title="' + String(tip).replace(/"/g, '&quot;') + '" style="' + st + '">'
                    + Math.round(v) + '%</span>';
            };
        }
        function ebayDilNum(v) {
            const n = Number(v);
            return isFinite(n) ? n : 0;
        }
        function ebayDilLiveSorter(kind) {
            return function(a, b, aRow, bRow) {
                const da = (aRow && aRow.getData) ? (aRow.getData() || {}) : {};
                const db = (bRow && bRow.getData) ? (bRow.getData() || {}) : {};
                let av = 0;
                let bv = 0;
                if (kind === 'sgpft') {
                    av = ebayDilRowSgpft(da);
                    bv = ebayDilRowSgpft(db);
                } else if (kind === 'snpft') {
                    av = ebayDilComputedSnpft(da);
                    bv = ebayDilComputedSnpft(db);
                } else if (kind === 'snroi') {
                    av = ebayDilComputedSnroi(da);
                    bv = ebayDilComputedSnroi(db);
                } else {
                    av = ebayDilRowSgroi(da);
                    bv = ebayDilRowSgroi(db);
                }
                return ebayDilNum(av) - ebayDilNum(bv);
            };
        }
        function ebayDilGrossColFormatter(kind) {
            return function(cell) {
                const d = (cell.getRow() && cell.getRow().getData) ? (cell.getRow().getData() || {}) : {};
                if (d.is_parent || d.is_parent_summary) {
                    return '<span style="color:#6c757d;">–</span>';
                }
                const v = kind === 'sgpft' ? ebayDilRowSgpft(d) : ebayDilRowSgroi(d);
                if (!isFinite(v)) return '';
                const color = ebayDilPctColor(v, kind === 'sgpft' ? 'gpft' : 'groi');
                const st = (window.MetricPctColors && typeof MetricPctColors.styleForCellColor === 'function')
                    ? MetricPctColors.styleForCellColor(color)
                    : (color === '#ffc107'
                        ? 'color:#000;background-color:#ffc107;font-weight:700;padding:1px 5px;border-radius:3px;'
                        : ('color:' + color + ';font-weight:600;'));
                const tip = kind === 'sgpft' ? 'SGPFT from S PRC' : 'SGROI from S PRC';
                return '<span title="' + tip + '" style="' + st + '">' + Math.round(v) + '%</span>';
            };
        }
        function ebayDilSColumnDef(kind) {
            const ads = (typeof ebayDilAdsPct === 'function') ? ebayDilAdsPct() : 0;
            const isNet = kind === 'snroi' || kind === 'snpft';
            const title = kind === 'sgpft' ? 'SGPFT' : (kind === 'snpft' ? 'SNPFT' : (kind === 'snroi' ? 'SNROI' : 'SGROI'));
            return {
                title: title,
                field: title,
                hozAlign: 'center',
                headerSort: true,
                sorter: ebayDilLiveSorter(kind),
                width: 58,
                headerTooltip: kind === 'snpft'
                    ? (ads > 0 ? 'SNPFT = SGPFT − Ads%.' : 'SNPFT = SGPFT (this page has no Ads%).')
                    : (kind === 'snroi'
                        ? (ads > 0 ? 'SNROI from S PRC, net of Ads%.' : 'SNROI = SGROI (this page has no Ads%).')
                        : (kind === 'sgpft' ? 'SGPFT from S PRC.' : 'SGROI from S PRC.')),
                formatter: isNet ? ebayDilNetColFormatter(kind) : ebayDilGrossColFormatter(kind),
                accessorDownload: function(value, d) {
                    let v;
                    if (kind === 'snpft') v = ebayDilComputedSnpft(d || {});
                    else if (kind === 'snroi') v = ebayDilComputedSnroi(d || {});
                    else if (kind === 'sgpft') v = ebayDilRowSgpft(d || {});
                    else v = ebayDilRowSgroi(d || {});
                    return isFinite(v) ? Math.round(v) : '';
                },
            };
        }
        function ebayDilNetColumnDef(title, field, kind) {
            const def = ebayDilSColumnDef(kind);
            if (title) def.title = title;
            if (field) def.field = field;
            return def;
        }
        function ebayDilForceLocalTableModes(tbl) {
            try {
                if (!tbl || !tbl.options) return;
                tbl.options.sortMode = 'local';
                tbl.options.filterMode = 'local';
                tbl.options.paginationMode = 'local';
                tbl.options.ajaxSorting = false;
                if (tbl.modules) {
                    if (tbl.modules.sort) tbl.modules.sort.mode = 'local';
                    if (tbl.modules.filter) tbl.modules.filter.mode = 'local';
                    if (tbl.modules.page) tbl.modules.page.mode = 'local';
                }
            } catch (e) { /* ignore */ }
        }
        function ebayDilMoveColumn(tbl, field, target, after) {
            if (!field || !target || field === target) return;
            try {
                if (typeof tbl.moveColumn === 'function') tbl.moveColumn(field, target, !!after);
            } catch (e) { /* ignore */ }
        }
        function ebayDilColumnIndex(tbl, field) {
            if (!field) return -1;
            let cols = [];
            try { cols = tbl.getColumns(true) || []; } catch (e) { return -1; }
            for (let i = 0; i < cols.length; i++) {
                try {
                    if ((cols[i].getField && cols[i].getField()) === field) return i;
                } catch (e) { /* continue */ }
            }
            return -1;
        }
        function ebayDilPatchSColumn(col, kind) {
            if (!col) return;
            const title = kind === 'sgpft' ? 'SGPFT' : (kind === 'snpft' ? 'SNPFT' : (kind === 'snroi' ? 'SNROI' : 'SGROI'));
            try {
                const def = (col.getDefinition && col.getDefinition()) || {};
                const updates = { title: title, headerSort: true };
                if (typeof def.sorter !== 'function') {
                    updates.sorter = ebayDilLiveSorter(kind);
                }
                if (typeof col.updateDefinition === 'function') {
                    col.updateDefinition(updates);
                } else if (def) {
                    def.title = title;
                    def.headerSort = true;
                    if (typeof def.sorter !== 'function') def.sorter = ebayDilLiveSorter(kind);
                }
            } catch (e) { /* ignore */ }
        }
        function ebayDilReorderSColumns(tbl, fields, afterField) {
            const present = (fields || []).filter(Boolean);
            if (!present.length) return;
            if (afterField) {
                let prev = afterField;
                present.forEach(function(f) {
                    ebayDilMoveColumn(tbl, f, prev, true);
                    prev = f;
                });
                return;
            }
            const idxs = present.map(function(f) {
                return { f: f, i: ebayDilColumnIndex(tbl, f) };
            }).filter(function(x) { return x.i >= 0; });
            if (!idxs.length) return;
            idxs.sort(function(a, b) { return a.i - b.i; });
            if (present[0] !== idxs[0].f) {
                ebayDilMoveColumn(tbl, present[0], idxs[0].f, false);
            }
            let prev = present[0];
            for (let i = 1; i < present.length; i++) {
                ebayDilMoveColumn(tbl, present[i], prev, true);
                prev = present[i];
            }
        }
        function ebayDilFindSCols(tbl) {
            let cols = [];
            try { cols = tbl.getColumns(true) || []; } catch (e) { return null; }
            if (!cols.length) return null;
            const infos = cols.map(ebayDilColInfo);
            return {
                cols: cols,
                infos: infos,
                sgroi: infos.find(ebayDilIsSgroiCol) || null,
                sgpft: infos.find(ebayDilIsSgpftCol) || null,
                snroi: infos.find(ebayDilIsSnroiCol) || null,
                snpft: infos.find(ebayDilIsSnpftCol) || null,
                sprice: infos.find(ebayDilIsSpriceCol) || null,
            };
        }
        function ebayDilNormalizeSColumns(tbl) {
            const found = ebayDilFindSCols(tbl);
            if (!found) return false;
            found.cols.forEach(function(col) {
                const info = ebayDilColInfo(col);
                if (ebayDilIsSnpftCol(info)) ebayDilPatchSColumn(col, 'snpft');
                else if (ebayDilIsSnroiCol(info)) ebayDilPatchSColumn(col, 'snroi');
                else if (ebayDilIsSgpftCol(info)) ebayDilPatchSColumn(col, 'sgpft');
                else if (ebayDilIsSgroiCol(info)) ebayDilPatchSColumn(col, 'sgroi');
            });
            const ordered = ebayDilFindSCols(tbl);
            if (!ordered) return false;
            ebayDilReorderSColumns(
                tbl,
                [
                    ordered.sgroi && ordered.sgroi.field,
                    ordered.sgpft && ordered.sgpft.field,
                    ordered.snroi && ordered.snroi.field,
                    ordered.snpft && ordered.snpft.field,
                ],
                ordered.sprice && ordered.sprice.field
            );
            return true;
        }
        function ebayDilEnsureNetColumns(tbl) {
            tbl = tbl || ((typeof table !== 'undefined') ? table : null);
            if (!tbl || typeof tbl.getColumns !== 'function') return false;
            ebayDilForceLocalTableModes(tbl);
            if (tbl._ebayDilNetColsAdded) {
                ebayDilNormalizeSColumns(tbl);
                return true;
            }
            let found = ebayDilFindSCols(tbl);
            if (!found) return false;
            if (!found.sgroi && !found.sgpft && !found.sprice && !found.snroi && !found.snpft) return false;
            try {
                const addSCol = function(kind, afterField) {
                    const def = ebayDilSColumnDef(kind);
                    if (afterField) tbl.addColumn(def, false, afterField);
                    else tbl.addColumn(def);
                };
                if (!found.sgroi && typeof tbl.addColumn === 'function') {
                    addSCol('sgroi', (found.sprice && found.sprice.field) || (found.sgpft && found.sgpft.field));
                }
                found = ebayDilFindSCols(tbl) || found;
                if (!found.sgpft && typeof tbl.addColumn === 'function') {
                    addSCol('sgpft', (found.sgroi && found.sgroi.field) || (found.sprice && found.sprice.field));
                }
                found = ebayDilFindSCols(tbl) || found;
                if (!found.snroi && typeof tbl.addColumn === 'function') {
                    addSCol('snroi', (found.sgpft && found.sgpft.field) || (found.sgroi && found.sgroi.field));
                }
                found = ebayDilFindSCols(tbl) || found;
                if (!found.snpft && typeof tbl.addColumn === 'function') {
                    addSCol('snpft', (found.snroi && found.snroi.field) || (found.sgpft && found.sgpft.field));
                }
            } catch (e) {
                return false;
            }
            if (!ebayDilNormalizeSColumns(tbl)) return false;
            tbl._ebayDilNetColsAdded = true;
            return true;
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
