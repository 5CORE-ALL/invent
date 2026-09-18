    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        let amzTable = null;
        let amzSummaryCache = [];
        let amzAllRows = [];
        let amzApplyingFilters = false;
        const lqsHasAudit = @json(!empty($lqsPage['has_audit']));
        const lqsHasYoast = @json(!empty($lqsPage['has_yoast_seo']));
        const lqsYoastSyncUrl = @json($lqsPage['routes']['seo_sync'] ?? null);

        function amzNotify(msg, type) {
            if (window.toastr) {
                if (type === 'warning') toastr.warning(msg);
                else if (type === 'error') toastr.error(msg);
                else toastr.success(msg);
            } else {
                alert(msg);
            }
        }

        // ── applyFilters ────────────────────────────────────────────────
        function amzIsParentRow(d) {
            return d && (d.is_parent === true || d.is_parent === 1 || d.is_parent === '1');
        }

        function amzRowKey(d) {
            return (amzIsParentRow(d) ? 'p:' : 'c:') + String(d && d.sku != null ? d.sku : '');
        }

        function amzMatchesSearch(d, skuSearch) {
            if (!skuSearch) return true;
            const sku    = String(d.sku || '').toLowerCase();
            const asin   = String(d.asin || '').toLowerCase();
            const parent = String(d.parent || '').toLowerCase();
            return sku.includes(skuSearch) || asin.includes(skuSearch) || parent.includes(skuSearch);
        }

        function amzMatchesInv(d, invFilter) {
            if (invFilter === 'all') return true;
            const inv = parseInt(d.inv, 10) || 0;
            if (invFilter === 'zero') return inv <= 0;
            if (invFilter === 'more') return inv > 0;
            return true;
        }

        function amzMatchesLqs(d, lqsFilter) {
            if (lqsFilter === 'all') return true;
            const lqs = parseFloat(d.lqs);
            const hasLqs = d.lqs !== null && d.lqs !== '' && !isNaN(lqs) && lqs > 0;
            if (lqsFilter === 'missing') return !hasLqs;
            if (!hasLqs) return false;
            if (lqsFilter === '8-10') return lqs >= 8;
            if (lqsFilter === '6-7')  return lqs >= 6 && lqs < 8;
            if (lqsFilter === '4-5')  return lqs >= 4 && lqs < 6;
            if (lqsFilter === '1-3')  return lqs >= 1 && lqs < 4;
            if (lqsFilter === 'below-9') return lqs < 9;
            if (lqsFilter === '80-100') return lqs >= 80;
            if (lqsFilter === '60-79') return lqs >= 60 && lqs < 80;
            if (lqsFilter === '40-59') return lqs >= 40 && lqs < 60;
            if (lqsFilter === '1-39') return lqs >= 1 && lqs < 40;
            if (lqsFilter === 'below-90') return lqs < 90;
            return true;
        }

        function amzYoastRating(value) {
            const rating = String(value || '').toLowerCase();
            return ['good', 'ok', 'bad', 'na'].includes(rating) ? rating : 'na';
        }

        function amzMatchesYoast(d, filter, field) {
            if (!lqsHasYoast || filter === 'all') return true;
            return amzYoastRating(d[field]) === filter;
        }

        function amzYoastLabel(rating) {
            if (rating === 'good') return 'Good';
            if (rating === 'ok') return 'OK';
            if (rating === 'bad') return 'Needs improvement';
            return 'Not analyzed';
        }

        function amzMatchesDil(d, dilColor) {
            if (dilColor === 'all') return true;
            const inv = parseInt(d.inv, 10) || 0;
            const l30 = parseFloat(d.l30) || 0;
            const dil = inv === 0 ? 0 : (l30 / inv) * 100;
            if (dilColor === 'red')   return dil < 25;
            if (dilColor === 'green') return dil >= 25 && dil < 50;
            if (dilColor === 'pink')  return dil >= 50;
            return true;
        }

        function amzGetFilteredRows() {
            const skuSearch = ($('#amz-sku-search').val() || '').toLowerCase().trim();
            const rowType   = $('#amz-row-type-filter').val() || 'all';
            const invFilter = $('#amz-inv-filter').val() || 'all';
            const lqsFilter = $('#amz-score-filter').val() || 'all';
            const seoFilter = $('#amz-seo-filter').val() || 'all';
            const readFilter = $('#amz-read-filter').val() || 'all';
            const dilColor  = ($('.amz-dil-item.active').attr('data-color') || 'all');

            const matchingChildren = [];
            const parentKeys = {};

            amzAllRows.forEach(function(d) {
                if (amzIsParentRow(d)) return;
                if (!amzMatchesSearch(d, skuSearch)) return;
                if (!amzMatchesInv(d, invFilter)) return;
                if (!amzMatchesLqs(d, lqsFilter)) return;
                if (!amzMatchesYoast(d, seoFilter, 'seo_rating')) return;
                if (!amzMatchesYoast(d, readFilter, 'readability_rating')) return;
                if (!amzMatchesDil(d, dilColor)) return;
                matchingChildren.push(d);
                parentKeys[String(d.parent || d.sku)] = true;
            });

            if (rowType === 'skus') {
                return matchingChildren;
            }

            const childSet = new Set(matchingChildren);

            return amzAllRows.filter(function(d) {
                if (amzIsParentRow(d)) {
                    if (rowType === 'skus') return false;
                    return !!parentKeys[String(d.sku || '')];
                }
                if (rowType === 'parents') return false;
                return childSet.has(d);
            });
        }

        function amzApplyFilters() {
            if (!amzTable || amzApplyingFilters) return;

            const filtered = amzGetFilteredRows();
            const current  = amzNormalizeRows(amzTable.getData());
            const sameLen  = current.length === filtered.length;
            const sameKeys = sameLen && current.every(function(row, i) {
                return amzRowKey(row) === amzRowKey(filtered[i]);
            });

            if (!sameKeys) {
                amzApplyingFilters = true;
                try {
                    if (typeof amzTable.replaceData === 'function') {
                        amzTable.replaceData(filtered);
                    } else {
                        amzTable.setData(filtered);
                    }
                } finally {
                    amzApplyingFilters = false;
                }
            }

            amzUpdateSummary(filtered);
        }

        function amzNormalizeRows(input) {
            if (Array.isArray(input)) {
                return input.map(r => (r && typeof r.getData === 'function') ? r.getData() : (r || {}));
            }
            if (input && typeof input === 'object') {
                return Object.values(input).map(r => (r && typeof r.getData === 'function') ? r.getData() : (r || {}));
            }
            return [];
        }

        function amzUpdateSummary(input) {
            let rows;
            if (arguments.length > 0) {
                rows = amzNormalizeRows(input);
            } else if (amzTable && typeof amzTable.getData === 'function') {
                rows = amzNormalizeRows(amzTable.getData());
            } else {
                rows = amzNormalizeRows(amzAllRows.length ? amzAllRows : amzSummaryCache);
            }

            let totalInv = 0, totalL30 = 0, totalSess = 0, totalSold = 0, totalSessions = 0;
            let dilSum = 0, dilCount = 0;
            let lqsSum = 0, lqsCount = 0;
            let ratingSum = 0, ratingCount = 0;
            let lqsBelow9Count = 0;

            rows.forEach(function(row) {
                if (row.is_parent) return;
                const inv    = parseFloat(row.inv)      || 0;
                const l30    = parseFloat(row.l30)      || 0;
                const sess   = parseFloat(row.sessions) || 0;
                const lqs    = parseInt(row.lqs, 10);
                const rating = parseFloat(row.rating)   || 0;

                totalInv      += inv;
                totalL30      += l30;
                totalSess     += sess;
                totalSold     += l30;
                totalSessions += sess;

                if (inv > 0) { dilSum += (l30 / inv) * 100; dilCount++; }
                if (lqs && !isNaN(lqs)) { lqsSum += lqs; lqsCount++; }
                if (rating > 0) { ratingSum += rating; ratingCount++; }
                if (lqs && !isNaN(lqs) && lqs > 0 && lqs < 9) { lqsBelow9Count++; }
            });

            const avgDil    = dilCount    > 0 ? dilSum    / dilCount    : 0;
            const avgLqs    = lqsCount    > 0 ? lqsSum    / lqsCount    : 0;
            const avgRating = ratingCount > 0 ? ratingSum / ratingCount : 0;
            const cvr       = totalSessions > 0 ? (totalSold / totalSessions) * 100 : null;

            $('#amz-total-inv-badge').text('Total INV: ' + Math.round(totalInv));
            $('#amz-total-l30-badge').text('Total L30: ' + Math.round(totalL30));
            $('#amz-total-sess-badge').text('Sessions L30: ' + totalSess.toLocaleString());
            $('#amz-avg-dil-badge').text('DIL: ' + Math.round(avgDil) + '%');
            $('#amz-avg-lqs-badge').text('LQS: ' + (avgLqs > 0 ? (lqsHasAudit ? Math.round(avgLqs) + '%' : avgLqs.toFixed(1)) : '–'));
            $('#amz-avg-rating-badge').text('Rating: ' + (avgRating > 0 ? avgRating.toFixed(1) : '–'));
            $('#amz-cvr-badge').text('CVR: ' + (cvr !== null ? cvr.toFixed(1) + '%' : '–'));
            $('#amz-lqs-below9-badge').text('< 9: ' + lqsBelow9Count);
            amzUpdateYoastDashboard(rows);
        }

        function amzUpdateYoastDashboard(rows) {
            if (!lqsHasYoast) return;
            const seo = {good:0, ok:0, bad:0, na:0};
            const read = {good:0, ok:0, bad:0, na:0};
            const seen = {};
            const source = (amzAllRows && amzAllRows.length) ? amzAllRows : (rows || []);
            source.forEach(function(row) {
                if (row.is_parent) return;
                if ((parseInt(row.inv, 10) || 0) <= 0) return;
                const pid = row.shopify_product_id || ('sku:' + row.sku);
                if (seen[pid]) return;
                seen[pid] = true;
                seo[amzYoastRating(row.seo_rating)]++;
                read[amzYoastRating(row.readability_rating)]++;
            });
            $('#lqs-seo-good').text(seo.good);
            $('#lqs-seo-ok').text(seo.ok);
            $('#lqs-seo-bad').text(seo.bad);
            $('#lqs-seo-na').text(seo.na);
            $('#lqs-read-good').text(read.good);
            $('#lqs-read-ok').text(read.ok);
            $('#lqs-read-bad').text(read.bad);
            $('#lqs-read-na').text(read.na);
        }

        function amzYoastBadgeHtml(score, rating) {
            const label = (score === null || score === undefined || score === '')
                ? amzYoastLabel(rating)
                : (score + ' · ' + amzYoastLabel(rating));
            return `<span class="lqs-yoast-score ${amzYoastRating(rating)}" title="Click for details">${label}</span>`;
        }

        function amzOpenYoastDetail(row) {
            const seoRating = amzYoastRating(row.seo_rating);
            const readRating = amzYoastRating(row.readability_rating);
            $('#lqsYoastSkuLabel').text(row.sku || '');
            $('#lqsYoastSeoBadge')
                .attr('class', 'badge lqs-yoast-score ' + seoRating)
                .text('SEO: ' + (row.seo_score != null ? row.seo_score : '–') + ' · ' + amzYoastLabel(seoRating));
            $('#lqsYoastReadBadge')
                .attr('class', 'badge lqs-yoast-score ' + readRating)
                .text('Readability: ' + (row.readability_score != null ? row.readability_score : '–') + ' · ' + amzYoastLabel(readRating));
            $('#lqsYoastKeyphrase').text(row.focus_keyphrase || 'Not set');
            $('#lqsYoastSeoTitle').text(row.seo_title || '–');
            const findings = Array.isArray(row.seo_findings) ? row.seo_findings : [];
            $('#lqsYoastFindings').text(findings.length ? findings.map((item, i) => (i + 1) + '. ' + item).join('\n') : 'No findings yet.');
            const modalEl = document.getElementById('lqsYoastDetailModal');
            if (modalEl && window.bootstrap) {
                window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
            }
        }

        $(document).ready(function() {
            amzTable = new Tabulator('#amz-lqs-table', {
                ajaxURL: '{{ $lqsPage['routes']['data'] }}',
                ajaxResponse: function(url, params, response) {
                    if (!Array.isArray(response)) {
                        amzNotify((response && response.error) || 'Failed to load {{ $lqsPage['title'] ?? 'LQS' }} data', 'error');
                        amzAllRows = [];
                        amzSummaryCache = [];
                        return [];
                    }
                    amzAllRows = amzNormalizeRows(response);
                    amzSummaryCache = amzAllRows;
                    amzUpdateSummary(amzAllRows);
                    return amzAllRows;
                },
                layout: 'fitDataStretch',
                filterMode: 'local',
                paginationMode: 'local',
                sortMode: 'local',
                pagination: true,
                paginationSize: 100,
                initialSort: [],
                rowFormatter: function(row) {
                    if (row.getData().is_parent === true) {
                        row.getElement().classList.add('amz-lqs-parent-row');
                    }
                },
                columns: [
                    {
                        title: 'Parent',
                        field: 'parent',
                        width: 120,
                        frozen: true,
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '';
                            const v = cell.getValue() || '';
                            if (!v) return '<span style="color:#adb5bd;">–</span>';
                            return `<span style="color:#0d6efd;font-size:11px;font-weight:600;">${v}</span>`;
                        }
                    },
                    {
                        title: 'Image',
                        field: 'image',
                        width: 60,
                        headerSort: false,
                        formatter: function(cell) {
                            const d   = cell.getRow().getData();
                            const src = cell.getValue();
                            if (d.is_parent) return '';
                            const listingUrl = d.listing_url || '';
                            const imgTag = src
                                ? `<img src="${src}" alt="" style="width:44px;height:44px;object-fit:cover;border-radius:4px;" onerror="this.style.display='none'">`
                                : `<span style="color:#adb5bd;font-size:10px;">No img</span>`;
                            return listingUrl
                                ? `<a href="${listingUrl}" target="_blank" title="View listing">${imgTag}</a>`
                                : imgTag;
                        }
                    },
                    {
                        title: 'SKU',
                        field: 'sku',
                        minWidth: 180,
                        frozen: true,
                        formatter: function(cell) {
                            const d   = cell.getRow().getData();
                            const val = cell.getValue() || '';
                            if (d.is_parent) {
                                return `<span style="color:#664d03;font-size:13px;font-weight:700;">${val}</span>`;
                            }
                            const esc = val.replace(/&/g,'&amp;').replace(/</g,'&lt;');
                            return `<span style="font-weight:600;">${esc}</span>`;
                        }
                    },
                    {
                        title: {!! json_encode($lqsPage['listing_label'] ?? 'Listing ID') !!},
                        field: 'asin',
                        width: 110,
                        formatter: function(cell) {
                            const d    = cell.getRow().getData();
                            const listingId = d.listing_id || cell.getValue() || '';
                            const listingUrl = d.listing_url || '';
                            if (d.is_parent || !listingId) return '<span style="color:#adb5bd;">–</span>';
                            if (listingUrl) {
                                return `<a href="${listingUrl}" target="_blank"
                                style="color:#ff9900;font-weight:600;font-size:11px;">${listingId}</a>`;
                            }
                            return `<span style="font-weight:600;font-size:11px;">${listingId}</span>`;
                        }
                    },
                    {
                        title: 'INV',
                        field: 'inv',
                        sorter: 'number',
                        hozAlign: 'center',
                        width: 55,
                        formatter: function(cell) {
                            const d   = cell.getRow().getData();
                            const val = parseInt(cell.getValue(), 10) || 0;
                            if (d.is_parent) return `<span style="font-weight:700;">${val}</span>`;
                            if (val === 0) return `<span style="color:#dc3545;font-weight:600;">0</span>`;
                            return `<span style="font-weight:600;">${val}</span>`;
                        }
                    },
                    {
                        title: 'L30',
                        field: 'l30',
                        sorter: 'number',
                        hozAlign: 'center',
                        width: 55,
                        formatter: function(cell) {
                            const val = parseInt(cell.getValue(), 10) || 0;
                            return `<span style="font-weight:600;">${val}</span>`;
                        }
                    },
                    {
                        title: 'Sessions L30',
                        field: 'sessions',
                        sorter: 'number',
                        hozAlign: 'center',
                        width: 70,
                        formatter: function(cell) {
                            const d   = cell.getRow().getData();
                            if (d.is_parent) return '';
                            const val = parseInt(cell.getValue(), 10) || 0;
                            if (val === 0) return '<span style="color:#adb5bd;">–</span>';
                            return `<span style="font-weight:600;">${val.toLocaleString()}</span>`;
                        }
                    },
                    {
                        title: 'DIL%',
                        field: 'dil_percent',
                        sorter: 'number',
                        hozAlign: 'center',
                        width: 55,
                        formatter: function(cell) {
                            const row   = cell.getRow().getData();
                            const inv   = parseFloat(row.inv) || 0;
                            const l30   = parseFloat(row.l30) || 0;
                            if (inv === 0) return `<span style="color:#6c757d;">0%</span>`;
                            const dil   = (l30 / inv) * 100;
                            const color = dil < 25 ? '#dc3545' : dil < 50 ? '#28a745' : '#e83e8c';
                            return `<span style="color:${color};font-weight:600;">${Math.round(dil)}%</span>`;
                        }
                    },
                    {
                        title: 'Price',
                        field: 'price',
                        sorter: 'number',
                        hozAlign: 'center',
                        width: 65,
                        formatter: function(cell) {
                            const d   = cell.getRow().getData();
                            if (d.is_parent) return '';
                            const val = parseFloat(cell.getValue()) || 0;
                            if (val === 0) return '<span style="color:#adb5bd;">–</span>';
                            return `<span style="font-weight:600;">$${val.toFixed(2)}</span>`;
                        }
                    },
                    {
                        title: 'Rating',
                        field: 'rating',
                        sorter: 'number',
                        hozAlign: 'center',
                        width: 60,
                        formatter: function(cell) {
                            const d   = cell.getRow().getData();
                            if (d.is_parent) return '';
                            const val = parseFloat(cell.getValue()) || 0;
                            if (!val) return '<span style="color:#adb5bd;">–</span>';
                            const color = val >= 4.5 ? '#28a745' : val >= 4.0 ? '#3591dc' : val >= 3.5 ? '#ffc107' : '#dc3545';
                            return `<span style="color:${color};font-weight:600;">★ ${val.toFixed(1)}</span>`;
                        }
                    },
                    {
                        title: 'Reviews',
                        field: 'reviews',
                        sorter: 'number',
                        hozAlign: 'center',
                        width: 65,
                        formatter: function(cell) {
                            const d   = cell.getRow().getData();
                            if (d.is_parent) return '';
                            const val = parseInt(cell.getValue(), 10) || 0;
                            if (!val) return '<span style="color:#adb5bd;">–</span>';
                            return `<span style="font-weight:600;">${val.toLocaleString()}</span>`;
                        }
                    },
                    {
                        title: 'Avg CVR',
                        field: 'cvr',
                        sorter: 'number',
                        hozAlign: 'center',
                        width: 65,
                        formatter: function(cell) {
                            const d   = cell.getRow().getData();
                            const val = parseFloat(cell.getValue());
                            if (val === null || val === undefined || isNaN(val)) {
                                return '<span style="color:#adb5bd;">–</span>';
                            }
                            let color = '#6c757d';
                            if (val >= 15)      color = '#28a745';
                            else if (val >= 10) color = '#3591dc';
                            else if (val >= 5)  color = '#ffc107';
                            else                color = '#dc3545';
                            return `<span style="color:${color};font-weight:700;">${val.toFixed(1)}%</span>`;
                        }
                    },
                    {
                        title: 'LQS',
                        field: 'lqs',
                        sorter: 'number',
                        hozAlign: 'center',
                        width: lqsHasAudit ? 70 : 55,
                        formatter: function(cell) {
                            const d   = cell.getRow().getData();
                            if (d.is_parent) return '';
                            const lqs = parseFloat(cell.getValue());
                            if (!lqs || isNaN(lqs)) return '<span style="color:#adb5bd;">–</span>';
                            let color = '#6c757d';
                            if (lqsHasAudit) {
                                if (lqs >= 80)      color = '#28a745';
                                else if (lqs >= 60) color = '#3591dc';
                                else if (lqs >= 40) color = '#ffc107';
                                else                color = '#dc3545';
                                return `<span style="color:${color};font-weight:700;font-size:13px;">${Math.round(lqs)}%</span>`;
                            }
                            if (lqs >= 8)      color = '#28a745';
                            else if (lqs >= 6) color = '#3591dc';
                            else if (lqs >= 4) color = '#ffc107';
                            else               color = '#dc3545';
                            return `<span style="color:${color};font-weight:700;font-size:13px;">${parseInt(lqs, 10)}</span>`;
                        }
                    },
                    ...(lqsHasYoast ? [
                    {
                        title: 'SEO',
                        field: 'seo_score',
                        sorter: 'number',
                        hozAlign: 'center',
                        width: 88,
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '';
                            return amzYoastBadgeHtml(d.seo_score, d.seo_rating);
                        },
                        cellClick: function(e, cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return;
                            amzOpenYoastDetail(d);
                        }
                    },
                    {
                        title: 'Readability',
                        field: 'readability_score',
                        sorter: 'number',
                        hozAlign: 'center',
                        width: 96,
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '';
                            return amzYoastBadgeHtml(d.readability_score, d.readability_rating);
                        },
                        cellClick: function(e, cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return;
                            amzOpenYoastDetail(d);
                        }
                    }
                    ] : []),
                    {
                        title: 'Action Taken',
                        field: 'has_action',
                        hozAlign: 'center',
                        headerSort: false,
                        width: 72,
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '';
                            const active = d.has_action;
                            const cls    = active ? 'has-action' : 'no-action';
                            const tip    = active
                                ? `Last: ${d.latest_action_user} – ${d.latest_action_date}`
                                : 'No action recorded. Click to add.';
                            return `<span class="amz-action-dot ${cls}" data-sku="${d.sku}" title="${tip}"></span>`;
                        },
                        cellClick: function(e, cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return;
                            amzOpenActionModal(d.sku, cell);
                        }
                    },
                    ...(lqsHasAudit ? [
                    {
                        title: 'Audit',
                        field: 'audit_findings',
                        hozAlign: 'center',
                        headerSort: false,
                        width: 62,
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '';
                            return `<button type="button" class="lqs-audit-btn" title="Audit listing quality">
                                <i class="fas fa-magnifying-glass"></i>
                            </button>`;
                        },
                        cellClick: function(e, cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return;
                            lqsOpenAuditModal(d, cell);
                        }
                    },
                    {
                        title: 'Findings',
                        field: 'audit_suggestions',
                        headerSort: false,
                        minWidth: 220,
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '';
                            const findings = (d.audit_findings || '').toString().trim();
                            const suggestions = (d.audit_suggestions || '').toString().trim();
                            if (!findings && !suggestions) {
                                return '<span style="color:#adb5bd;font-size:10px;">–</span>';
                            }
                            const esc = function(v) {
                                return String(v || '')
                                    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/"/g,'&quot;');
                            };
                            return `<div class="lqs-findings-cell" title="Click to view full audit">
                                <span class="lqs-findings-label">Findings</span>
                                <span class="lqs-findings-text">${esc(findings || '–')}</span>
                                <span class="lqs-suggest-label">Editor</span>
                                <span class="lqs-suggest-text">${esc(suggestions || '–')}</span>
                            </div>`;
                        },
                        cellClick: function(e, cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return;
                            lqsOpenAuditModal(d, cell);
                        }
                    }
                    ] : []),
                    {
                        title: 'History',
                        field: 'latest_action_text',
                        headerSort: false,
                        width: 160,
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent || !d.has_action) {
                                return '<span style="color:#adb5bd;font-size:10px;">–</span>';
                            }
                            return `<div class="amz-history-cell" data-sku="${d.sku}" title="Click to see full history">
                                <span class="amz-hist-user">${d.latest_action_user || ''}</span>
                                <span class="amz-hist-date"> · ${d.latest_action_date || ''}</span><br>
                                <span class="amz-hist-text">${d.latest_action_text || ''}</span>
                                <span class="amz-hist-more">▶ Full history</span>
                            </div>`;
                        },
                        cellClick: function(e, cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent || !d.has_action) return;
                            amzOpenHistoryModal(d.sku);
                        }
                    }
                ],
                dataLoaded: function() {
                    if (amzApplyingFilters) {
                        return;
                    }
                    amzApplyFilters();
                }
            });

            // ── Filter events ──────────────────────────────────────────
            $('#amz-sku-search').on('input', function()      { amzApplyFilters(); });
            $('#amz-row-type-filter').on('change', function() { amzApplyFilters(); });
            $('#amz-inv-filter').on('change',    function()  { amzApplyFilters(); });
            $('#amz-score-filter').on('change',  function()  { amzApplyFilters(); });
            $('#amz-seo-filter').on('change', function() { amzApplyFilters(); });
            $('#amz-read-filter').on('change', function() { amzApplyFilters(); });
            $(document).on('click', '.lqs-yoast-row', function() {
                const kind = $(this).closest('[data-kind]').data('kind');
                const rating = $(this).data('rating');
                const $select = kind === 'read' ? $('#amz-read-filter') : $('#amz-seo-filter');
                const next = $select.val() === rating ? 'all' : rating;
                $select.val(next);
                $('.lqs-yoast-row').removeClass('active');
                if (next !== 'all') {
                    $(this).addClass('active');
                }
                amzApplyFilters();
            });
            $('#lqs-yoast-sync-btn').on('click', function() {
                const $btn = $(this);
                if ($btn.prop('disabled') || !lqsYoastSyncUrl) return;
                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Syncing…');
                $.ajax({
                    url: lqsYoastSyncUrl,
                    method: 'POST',
                    data: { _token: $('meta[name="csrf-token"]').attr('content'), live: 1 },
                    timeout: 180000
                }).done(function(res) {
                    if (!res || !res.success) {
                        amzNotify((res && res.message) || 'Yoast sync failed', 'error');
                        return;
                    }
                    amzNotify('Scored ' + (res.products || 0) + ' Shopify products from ' + (res.source || 'Shopify'));
                    if (amzTable) amzTable.setData('{{ $lqsPage['routes']['data'] }}');
                }).fail(function(xhr) {
                    const msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Yoast sync failed';
                    amzNotify(msg, 'error');
                }).always(function() {
                    $btn.prop('disabled', false).html('<i class="fas fa-magnifying-glass me-1"></i> Sync Yoast scores');
                });
            });

            // DIL dropdown
            $(document).on('click', '.amz-dil-toggle', function(e) {
                e.stopPropagation();
                $(this).closest('.amz-lqs-dropdown').toggleClass('show');
            });
            $(document).on('click', '.amz-dil-item', function(e) {
                e.preventDefault(); e.stopPropagation();
                $('.amz-dil-item').removeClass('active');
                $(this).addClass('active');
                const circle = $(this).find('.amz-sc').clone();
                $('#amz-dil-btn').html('').append(circle).append('DIL%');
                $(this).closest('.amz-lqs-dropdown').removeClass('show');
                amzApplyFilters();
            });
            $(document).on('click', function() {
                $('.amz-lqs-dropdown').removeClass('show');
            });

            $('#amz-refresh-btn').on('click', function() {
                amzTable.setData('{{ $lqsPage['routes']['data'] }}');
            });
            $('#amz-export-btn').on('click', function() {
                amzTable.download('csv', 'lqs_{{ $lqsPage["slug"] ?? "marketplace" }}_data.csv');
            });

            // ── CVR Badge History Chart ───────────────────────────────
            let amzCvrChartInstance = null;
            let amzCvrChartDays     = 32;
            let amzCvrAjax          = null;

            function amzCvrFmt(v) {
                return (Number(v) || 0).toFixed(1) + '%';
            }

            function amzCvrRangeLabel(d) {
                return d === 0 ? 'Lifetime' : ('L' + d);
            }

            function amzRenderCvrChart(data) {
                const labels = data.map(d => d.date);
                const values = data.map(d => Number(d.value) || 0);

                const dataMin = Math.min(...values);
                const dataMax = Math.max(...values);
                const sorted  = [...values].sort((a, b) => a - b);
                const mid     = Math.floor(sorted.length / 2);
                const median  = sorted.length % 2 !== 0
                    ? sorted[mid]
                    : (sorted[mid - 1] + sorted[mid]) / 2;

                const range = dataMax - dataMin || 1;
                const yMin  = Math.max(0, dataMin - range * 0.1);
                const yMax  = dataMax + range * 0.1;

                // Right panel
                document.getElementById('amzCvrHighest').textContent = amzCvrFmt(dataMax);
                document.getElementById('amzCvrMedian').textContent  = amzCvrFmt(median);
                document.getElementById('amzCvrLowest').textContent  = amzCvrFmt(dataMin);

                // Dot colors: green=up, red=down vs previous day
                const dotColors = values.map((v, i) => {
                    if (i === 0) return '#6c757d';
                    return v > values[i - 1] ? '#28a745' : v < values[i - 1] ? '#dc3545' : '#6c757d';
                });

                const labelColors = values.map(v => v === 0 ? '#198754' : v > 0 ? '#dc3545' : '#6c757d');

                // Median dashed line plugin (matches all-marketplace-master)
                const medianLinePlugin = {
                    id: 'amzCvrMedianLine',
                    afterDraw(chart) {
                        const yScale = chart.scales.y;
                        const xScale = chart.scales.x;
                        const c      = chart.ctx;
                        const yPx    = yScale.getPixelForValue(median);
                        c.save();
                        c.setLineDash([6, 4]);
                        c.strokeStyle = '#6c757d';
                        c.lineWidth   = 1.2;
                        c.beginPath();
                        c.moveTo(xScale.left, yPx);
                        c.lineTo(xScale.right, yPx);
                        c.stroke();
                        c.restore();
                    }
                };

                // Value labels plugin (alternating -10 / -20 offsets)
                const valueLabelsPlugin = {
                    id: 'amzCvrValueLabels',
                    afterDatasetsDraw(chart) {
                        const dataset = chart.data.datasets[0];
                        const meta    = chart.getDatasetMeta(0);
                        const c       = chart.ctx;
                        c.save();
                        c.font          = 'bold 11px Inter, system-ui, sans-serif';
                        c.textAlign     = 'center';
                        c.textBaseline  = 'bottom';
                        meta.data.forEach((point, i) => {
                            const offsetY = (i % 2 === 0) ? -10 : -20;
                            c.fillStyle   = labelColors[i];
                            c.fillText(amzCvrFmt(dataset.data[i]), point.x, point.y + offsetY);
                        });
                        c.restore();
                    }
                };

                const ctx = document.getElementById('amzCvrChart').getContext('2d');
                if (amzCvrChartInstance) amzCvrChartInstance.destroy();

                amzCvrChartInstance = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels,
                        datasets: [{
                            label: 'CVR',
                            data: values,
                            backgroundColor: 'rgba(108,117,125,0.08)',
                            borderColor: '#adb5bd',
                            borderWidth: 1.5,
                            fill: true,
                            tension: 0.3,
                            pointRadius: 3,
                            pointHoverRadius: 5,
                            pointBackgroundColor: dotColors,
                            pointBorderColor: dotColors,
                            pointBorderWidth: 1.5
                        }]
                    },
                    plugins: [medianLinePlugin, valueLabelsPlugin],
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        layout: { padding: { top: 26, left: 2, right: 2, bottom: 2 } },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                titleFont: { size: 10 },
                                bodyFont:  { size: 10 },
                                padding:   6,
                                callbacks: {
                                    label: function(ctx) {
                                        const i = ctx.dataIndex;
                                        const parts = ['Value: ' + amzCvrFmt(ctx.raw)];
                                        if (i > 0) {
                                            const diff  = ctx.raw - values[i - 1];
                                            const arrow = diff > 0 ? '▲' : diff < 0 ? '▼' : '▬';
                                            parts.push('vs Yesterday: ' + arrow + ' ' + amzCvrFmt(Math.abs(diff)));
                                        }
                                        if (i >= 7) {
                                            const diff7  = ctx.raw - values[i - 7];
                                            const arrow7 = diff7 > 0 ? '▲' : diff7 < 0 ? '▼' : '▬';
                                            parts.push('vs 7d Ago: ' + arrow7 + ' ' + amzCvrFmt(Math.abs(diff7)));
                                        }
                                        return parts;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                min: yMin, max: yMax,
                                ticks: { font: { size: 9 }, callback: v => amzCvrFmt(v) }
                            },
                            x: {
                                ticks: {
                                    maxRotation: 45, minRotation: 45,
                                    autoSkip: labels.length > 14,
                                    maxTicksLimit: labels.length > 14 ? 14 : labels.length,
                                    font: { size: 8 }
                                }
                            }
                        }
                    }
                });

                $('#amzCvrChartContainer').show();
            }

            function amzLoadCvrChart() {
                if (amzCvrAjax) amzCvrAjax.abort();
                $('#amzCvrChartContainer,#amzCvrNoData').hide();
                $('#amzCvrLoading').show();

                amzCvrAjax = $.ajax({
                    url: '{{ $lqsPage['routes']['cvr'] }}',
                    method: 'GET',
                    data: { days: amzCvrChartDays },
                    success: function(res) {
                        amzCvrAjax = null;
                        $('#amzCvrLoading').hide();
                        if (res.success && res.data && res.data.length) {
                            amzRenderCvrChart(res.data);
                        } else {
                            $('#amzCvrNoData').show();
                        }
                    },
                    error: function() {
                        amzCvrAjax = null;
                        $('#amzCvrLoading').hide();
                        $('#amzCvrNoData').show();
                    }
                });
            }

            $('#amz-cvr-badge').on('click', function() {
                amzCvrChartDays = 32;
                $('#amzCvrChartRange').val('32');
                $('#amzCvrChartTitle').text('{{ $lqsPage["title"] ?? "LQS" }} – CVR (Rolling L32)');
                bootstrap.Modal.getOrCreateInstance(document.getElementById('amzCvrChartModal')).show();
                amzLoadCvrChart();
            });

            // ── Sheet upload ──────────────────────────────────────────
            function amzOpenSheetUpload() {
                $('#amzSheetFile').val('');
                $('#amzSheetUploadErr').addClass('d-none').text('');
                bootstrap.Modal.getOrCreateInstance(document.getElementById('amzSheetUploadModal')).show();
            }
            $('#amz-upload-btn, #amz-sheet-upload-badge').on('click', amzOpenSheetUpload);
            $('#amzSheetUploadSave').on('click', function() {
                const file = document.getElementById('amzSheetFile').files[0];
                if (!file) {
                    $('#amzSheetUploadErr').removeClass('d-none').text('Choose a sheet first.');
                    return;
                }
                const $btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Uploading…');
                const form = new FormData();
                form.append('file', file);
                $.ajax({
                    url: '{{ $lqsPage['routes']['upload'] }}',
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    data: form,
                    processData: false,
                    contentType: false,
                    success: function(res) {
                        if (res.success) {
                            bootstrap.Modal.getInstance(document.getElementById('amzSheetUploadModal')).hide();
                            amzNotify(res.message || 'Sheet imported.', 'success');
                            amzTable.setData('{{ $lqsPage['routes']['data'] }}');
                        } else {
                            $('#amzSheetUploadErr').removeClass('d-none').text(res.message || 'Upload failed.');
                        }
                    },
                    error: function(xhr) {
                        const msg = (xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error)) || 'Upload failed.';
                        $('#amzSheetUploadErr').removeClass('d-none').text(msg);
                    },
                    complete: function() {
                        $btn.prop('disabled', false).html('<i class="fas fa-upload me-1"></i> Upload');
                    }
                });
            });

            $(document).on('change', '#amzCvrChartRange', function() {
                const d = parseInt($(this).val(), 10);
                if (d === amzCvrChartDays) return;
                amzCvrChartDays = d;
                $('#amzCvrChartTitle').text('{{ $lqsPage["title"] ?? "LQS" }} – CVR (Rolling ' + amzCvrRangeLabel(d) + ')');
                amzLoadCvrChart();
            });

            // ── Action Taken modal ────────────────────────────────────
            let amzActionCurrentSku  = null;
            let amzActionCurrentCell = null;

            window.amzOpenActionModal = function(sku, cell) {
                amzActionCurrentSku  = sku;
                amzActionCurrentCell = cell;
                $('#amzActionSkuLabel').text(sku);
                $('#amzActionText').val('').trigger('input');
                $('#amzActionErr').hide();
                bootstrap.Modal.getOrCreateInstance(document.getElementById('amzActionModal')).show();
                setTimeout(() => $('#amzActionText').focus(), 350);
            };

            $('#amzActionText').on('input', function() {
                $('#amzActionCharCount').text($(this).val().length);
            });

            $('#amzActionSaveBtn').on('click', function() {
                const text = $('#amzActionText').val().trim();
                if (!text) {
                    $('#amzActionErr').text('Please enter an action.').show();
                    return;
                }
                const $btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Saving…');

                $.ajax({
                    url: '{{ $lqsPage['routes']['action'] }}',
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    data: { sku: amzActionCurrentSku, action: text },
                    success: function(res) {
                        if (res.success && amzActionCurrentCell) {
                            // Update the row data in Tabulator
                            const row = amzActionCurrentCell.getRow();
                            row.update({
                                has_action:         true,
                                latest_action_text: res.action,
                                latest_action_user: res.user_name,
                                latest_action_date: res.created_at,
                            });
                        }
                        bootstrap.Modal.getInstance(document.getElementById('amzActionModal')).hide();
                        amzNotify('Action saved!', 'success');
                    },
                    error: function() {
                        $('#amzActionErr').text('Failed to save. Please try again.').show();
                    },
                    complete: function() {
                        $btn.prop('disabled', false).html('<i class="fas fa-save me-1"></i> Save');
                    }
                });
            });

            // ── History modal ─────────────────────────────────────────
            window.amzOpenHistoryModal = function(sku) {
                $('#amzHistorySkuLabel').text(sku);
                $('#amzHistoryList').hide().empty();
                $('#amzHistoryEmpty').hide();
                $('#amzHistoryLoading').show();
                bootstrap.Modal.getOrCreateInstance(document.getElementById('amzHistoryModal')).show();

                $.ajax({
                    url: '{{ $lqsPage['routes']['history'] }}/' + encodeURIComponent(sku),
                    method: 'GET',
                    success: function(res) {
                        $('#amzHistoryLoading').hide();
                        if (!res.success || !res.data || !res.data.length) {
                            $('#amzHistoryEmpty').show();
                            return;
                        }
                        const html = res.data.map(e => `
                            <div class="amz-hist-entry">
                                <div class="amz-he-meta">
                                    <i class="fas fa-user-circle me-1"></i>${e.user_name}
                                    &nbsp;·&nbsp;
                                    <i class="fas fa-clock me-1"></i>${e.created_at}
                                </div>
                                <div class="amz-he-text">${e.action}</div>
                            </div>`).join('');
                        $('#amzHistoryList').html(html).show();
                    },
                    error: function() {
                        $('#amzHistoryLoading').hide();
                        $('#amzHistoryEmpty').show();
                    }
                });
            };

            let lqsAuditCurrentCell = null;
            let lqsAuditCurrentSku = '';

            window.lqsOpenAuditModal = function(row, cell) {
                if (!lqsHasAudit) return;
                lqsAuditCurrentCell = cell || null;
                lqsAuditCurrentSku = row.sku || '';
                $('#lqsAuditSkuLabel').text(lqsAuditCurrentSku);
                $('#lqsAuditErr').addClass('d-none').text('');
                $('#lqsAuditPrompt').val('Loading prompt…');
                if (row.audit_findings || row.audit_suggestions || row.lqs) {
                    $('#lqsAuditScoreBadge').text('LQS: ' + (row.lqs ? Math.round(row.lqs) + '%' : '–'));
                    $('#lqsAuditFindings').text(row.audit_findings || '–');
                    $('#lqsAuditSuggestions').text(row.audit_suggestions || '–');
                    $('#lqsAuditResult').show();
                } else {
                    $('#lqsAuditResult').hide();
                }
                bootstrap.Modal.getOrCreateInstance(document.getElementById('lqsAuditModal')).show();
                $.ajax({
                    url: '{{ $lqsPage['routes']['audit_prompt'] ?? '' }}',
                    method: 'GET',
                    success: function(res) {
                        if (res.success && res.prompt) {
                            $('#lqsAuditPrompt').val(res.prompt);
                        }
                    },
                    error: function() {
                        $('#lqsAuditErr').removeClass('d-none').text('Failed to load the audit prompt.');
                    }
                });
            };

            if (lqsHasAudit) {
                $('#lqsAuditSavePromptBtn').on('click', function() {
                    const prompt = ($('#lqsAuditPrompt').val() || '').trim();
                    if (prompt.length < 20) {
                        $('#lqsAuditErr').removeClass('d-none').text('Prompt is too short.');
                        return;
                    }
                    const $btn = $(this).prop('disabled', true);
                    $.ajax({
                        url: '{{ $lqsPage['routes']['audit_prompt_save'] ?? '' }}',
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                        data: { prompt: prompt },
                        success: function(res) {
                            if (res.success) {
                                amzNotify('Audit prompt saved.', 'success');
                            } else {
                                $('#lqsAuditErr').removeClass('d-none').text(res.message || 'Failed to save prompt.');
                            }
                        },
                        error: function() {
                            $('#lqsAuditErr').removeClass('d-none').text('Failed to save prompt.');
                        },
                        complete: function() {
                            $btn.prop('disabled', false);
                        }
                    });
                });

                $('#lqsAuditRunBtn').on('click', function() {
                    const prompt = ($('#lqsAuditPrompt').val() || '').trim();
                    if (!lqsAuditCurrentSku) return;
                    if (prompt.length < 20) {
                        $('#lqsAuditErr').removeClass('d-none').text('Prompt is too short.');
                        return;
                    }
                    const $btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Auditing…');
                    $('#lqsAuditErr').addClass('d-none').text('');
                    $.ajax({
                        url: '{{ $lqsPage['routes']['audit_run'] ?? '' }}',
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                        data: { sku: lqsAuditCurrentSku, prompt: prompt },
                        success: function(res) {
                            if (!res.success) {
                                $('#lqsAuditErr').removeClass('d-none').text(res.message || 'Audit failed.');
                                return;
                            }
                            $('#lqsAuditScoreBadge').text('LQS: ' + Math.round(res.lqs) + '%');
                            $('#lqsAuditFindings').text(res.findings || '–');
                            $('#lqsAuditSuggestions').text(res.suggestions || '–');
                            $('#lqsAuditResult').show();
                            if (lqsAuditCurrentCell) {
                                lqsAuditCurrentCell.getRow().update({
                                    lqs: res.lqs,
                                    audit_findings: res.findings,
                                    audit_suggestions: res.suggestions
                                });
                            }
                            amzAllRows = amzAllRows.map(function(row) {
                                if (String(row.sku || '').toUpperCase() === String(lqsAuditCurrentSku).toUpperCase()) {
                                    return Object.assign({}, row, {
                                        lqs: res.lqs,
                                        audit_findings: res.findings,
                                        audit_suggestions: res.suggestions
                                    });
                                }
                                return row;
                            });
                            amzUpdateSummary();
                            amzNotify('Audit complete. LQS updated.', 'success');
                        },
                        error: function(xhr) {
                            const msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Audit failed.';
                            $('#lqsAuditErr').removeClass('d-none').text(msg);
                        },
                        complete: function() {
                            $btn.prop('disabled', false).html('<i class="fas fa-magnifying-glass me-1"></i> Run Audit');
                        }
                    });
                });
            }

            // ── Badge Trend Chart (all-marketplace-master design) ─────
            let amzBadgeChartInstance = null;
            let amzMetric    = '';
            let amzChartDays = 32;
            let amzChartAjax = null;

            const amzBadgeLabels = {
                total_inv:          'Total INV',
                total_l30:          'Total L30',
                total_sessions:     'Sessions L30',
                avg_dil:            'DIL%',
                avg_lqs:            'LQS',
                avg_rating:         'Rating',
                lqs_below_9_count:  'LQS < 9'
            };

            function amzBadgeFmt(v) {
                const n = Number(v) || 0;
                if (amzMetric === 'avg_lqs')           return n.toFixed(1);
                if (amzMetric === 'avg_dil')           return Math.round(n) + '%';
                if (amzMetric === 'avg_rating')        return n.toFixed(1) + ' ★';
                if (amzMetric === 'lqs_below_9_count') return Math.round(n) + ' SKUs';
                return Math.round(n).toLocaleString('en-US');
            }

            function amzBadgeRangeLabel(d) {
                return d === 0 ? 'Lifetime' : ('L' + d);
            }

            function amzRenderBadgeChart(data) {
                const labels  = data.map(d => d.date);
                const values  = data.map(d => Number(d.value) || 0);
                const dataMin = Math.min(...values);
                const dataMax = Math.max(...values);
                const sorted  = [...values].sort((a, b) => a - b);
                const mid     = Math.floor(sorted.length / 2);
                const median  = sorted.length % 2 !== 0
                    ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;

                const range = dataMax - dataMin || 1;
                const yMin  = Math.max(0, dataMin - range * 0.1);
                const yMax  = dataMax + range * 0.1;

                // Right reference panel
                document.getElementById('amzBadgeHighest').textContent = amzBadgeFmt(dataMax);
                document.getElementById('amzBadgeMedian').textContent  = amzBadgeFmt(median);
                document.getElementById('amzBadgeLowest').textContent  = amzBadgeFmt(dataMin);

                // Dot colors: green=up, red=down vs previous day
                const dotColors = values.map((v, i) =>
                    i === 0 ? '#6c757d' : v > values[i-1] ? '#28a745' : v < values[i-1] ? '#dc3545' : '#6c757d'
                );
                const labelColors = values.map(v => v === 0 ? '#198754' : v > 0 ? '#dc3545' : '#6c757d');

                // Median dashed line plugin
                const medianLinePlugin = {
                    id: 'amzBadgeMedianLine',
                    afterDraw(chart) {
                        const yScale = chart.scales.y, xScale = chart.scales.x, c = chart.ctx;
                        const yPx = yScale.getPixelForValue(median);
                        c.save();
                        c.setLineDash([6, 4]); c.strokeStyle = '#6c757d'; c.lineWidth = 1.2;
                        c.beginPath(); c.moveTo(xScale.left, yPx); c.lineTo(xScale.right, yPx); c.stroke();
                        c.restore();
                    }
                };

                // Value labels plugin (alternating -10 / -20 offsets)
                const valueLabelsPlugin = {
                    id: 'amzBadgeValueLabels',
                    afterDatasetsDraw(chart) {
                        const dataset = chart.data.datasets[0];
                        const meta = chart.getDatasetMeta(0);
                        const c = chart.ctx;
                        c.save();
                        c.font = 'bold 11px Inter, system-ui, sans-serif';
                        c.textAlign = 'center'; c.textBaseline = 'bottom';
                        meta.data.forEach((point, i) => {
                            const offsetY = (i % 2 === 0) ? -10 : -20;
                            c.fillStyle = labelColors[i];
                            c.fillText(amzBadgeFmt(dataset.data[i]), point.x, point.y + offsetY);
                        });
                        c.restore();
                    }
                };

                const ctx = document.getElementById('amzBadgeChart').getContext('2d');
                if (amzBadgeChartInstance) amzBadgeChartInstance.destroy();

                amzBadgeChartInstance = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels,
                        datasets: [{
                            label: amzBadgeLabels[amzMetric] || amzMetric,
                            data: values,
                            backgroundColor: 'rgba(108,117,125,0.08)',
                            borderColor: '#adb5bd',
                            borderWidth: 1.5,
                            fill: true, tension: 0.3,
                            pointRadius: 3, pointHoverRadius: 5,
                            pointBackgroundColor: dotColors,
                            pointBorderColor: dotColors,
                            pointBorderWidth: 1.5
                        }]
                    },
                    plugins: [medianLinePlugin, valueLabelsPlugin],
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        layout: { padding: { top: 26, left: 2, right: 2, bottom: 2 } },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                titleFont: { size: 10 }, bodyFont: { size: 10 }, padding: 6,
                                callbacks: {
                                    label: function(ctx) {
                                        const i = ctx.dataIndex;
                                        const parts = ['Value: ' + amzBadgeFmt(ctx.raw)];
                                        if (i > 0) {
                                            const diff = ctx.raw - values[i - 1];
                                            parts.push('vs Yesterday: ' + (diff > 0 ? '▲' : diff < 0 ? '▼' : '▬') + ' ' + amzBadgeFmt(Math.abs(diff)));
                                        }
                                        if (i >= 7) {
                                            const diff7 = ctx.raw - values[i - 7];
                                            parts.push('vs 7d Ago: ' + (diff7 > 0 ? '▲' : diff7 < 0 ? '▼' : '▬') + ' ' + amzBadgeFmt(Math.abs(diff7)));
                                        }
                                        return parts;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                min: yMin, max: yMax,
                                ticks: { font: { size: 9 }, callback: v => amzBadgeFmt(v) }
                            },
                            x: {
                                ticks: {
                                    maxRotation: 45, minRotation: 45,
                                    autoSkip: labels.length > 14,
                                    maxTicksLimit: labels.length > 14 ? 14 : labels.length,
                                    font: { size: 8 }
                                }
                            }
                        }
                    }
                });

                $('#amzBadgeChartContainer').show();
            }

            function amzLoadBadgeChart() {
                if (!amzMetric) return;
                if (amzChartAjax) amzChartAjax.abort();
                $('#amzBadgeChartContainer,#amzBadgeNoData').hide();
                $('#amzBadgeLoading').show();

                amzChartAjax = $.ajax({
                    url: '{{ $lqsPage['routes']['badge'] }}',
                    method: 'GET',
                    data: { metric: amzMetric, days: amzChartDays },
                    success: function(res) {
                        amzChartAjax = null;
                        $('#amzBadgeLoading').hide();
                        const pts = (res && res.success && Array.isArray(res.data)) ? res.data : [];
                        if (pts.length) {
                            amzRenderBadgeChart(pts);
                        } else {
                            $('#amzBadgeNoData').show();
                        }
                    },
                    error: function() {
                        amzChartAjax = null;
                        $('#amzBadgeLoading').hide();
                        $('#amzBadgeNoData').show();
                    }
                });
            }

            $(document).on('click', '.amz-badge-chart', function() {
                amzMetric    = $(this).data('metric');
                amzChartDays = 32;
                $('#amzBadgeChartRange').val('32');
                $('#amzBadgeChartTitle').text('{{ $lqsPage["title"] ?? "LQS" }} – ' + (amzBadgeLabels[amzMetric] || amzMetric) + ' (Rolling L32)');
                bootstrap.Modal.getOrCreateInstance(document.getElementById('amzBadgeChartModal')).show();
                amzLoadBadgeChart();
            });

            $(document).on('change', '#amzBadgeChartRange', function() {
                const d = parseInt($(this).val(), 10);
                if (d === amzChartDays) return;
                amzChartDays = d;
                $('#amzBadgeChartTitle').text(
                    '{{ $lqsPage["title"] ?? "LQS" }} – ' + (amzBadgeLabels[amzMetric] || amzMetric) + ' (Rolling ' + amzBadgeRangeLabel(d) + ')'
                );
                amzLoadBadgeChart();
            });
        });
    </script>
