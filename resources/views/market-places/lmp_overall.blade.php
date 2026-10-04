@extends('layouts.vertical', ['title' => 'LMP Overall', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        #lmp-overall-wrap .tabulator { border: 1px solid #dee2e6; border-radius: 8px; font-size: 12px; }
        #lmp-overall-wrap .tabulator .tabulator-header { background: #f8f9fa; }
        #lmp-overall-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            white-space: normal !important;
            font-size: 11px;
            font-weight: 600;
            text-align: center;
            line-height: 1.2;
            padding: 4px 2px;
        }
        #lmp-overall-wrap .tabulator .tabulator-cell { padding: 4px 6px !important; }
        #lmp-overall-wrap .tabulator-row.tabulator-selected { background: #e7f1ff !important; }
        .lmp-overall-thumb { width: 36px; height: 36px; object-fit: contain; border-radius: 4px; background: #fff; }
        #lmp-overall-wrap .tabulator-row.lmp-overall-parent,
        #lmp-overall-wrap .tabulator-row.lmp-overall-parent .tabulator-cell { background: #fff3cd !important; font-weight: 600; }
        #lmp-overall-wrap .tabulator-row.lmp-overall-parent.tabulator-selected,
        #lmp-overall-wrap .tabulator-row.lmp-overall-parent.tabulator-selected .tabulator-cell { background: #ffe08a !important; }
        .lmp-overall-price { font-weight: 700; color: #198754; }
        .lmp-overall-count { color: #007bff; font-weight: 700; text-decoration: none; cursor: pointer; }
        .lmp-overall-count:hover { text-decoration: underline; }
        #lmpOverallLmpModal .lmp-overall-comp-img { width: 42px; height: 42px; object-fit: contain; background: #fff; border-radius: 4px; }
        #lmpOverallLmpModal tr.lmp-overall-ignored { opacity: 0.55; text-decoration: line-through; }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'LMP Overall',
        'sub_title' => "LMP's Master",
    ])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <span id="lmp-overall-total" class="badge bg-secondary">Total: —</span>
                        <span id="lmp-overall-selected" class="badge bg-primary">Selected: 0</span>
                        <input type="search" id="lmp-overall-search-parent" class="form-control form-control-sm"
                            placeholder="Search parent" autocomplete="off" style="max-width: 200px;">
                        <input type="search" id="lmp-overall-search-sku" class="form-control form-control-sm"
                            placeholder="Search SKU" autocomplete="off" style="max-width: 200px;">
                        <button type="button" id="lmp-overall-refresh" class="btn btn-sm btn-outline-primary" title="Reload">
                            <i class="ri-refresh-line"></i>
                        </button>
                        <span class="text-muted small" id="lmp-overall-status">Loading…</span>
                    </div>
                    <div id="lmp-overall-wrap">
                        <div id="lmp-overall-table"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="lmpOverallStdModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Std Price — <span id="lmp-overall-std-sku"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label small fw-bold" for="lmp-overall-std-input">Std Price</label>
                    <input type="number" class="form-control" id="lmp-overall-std-input" step="0.01" min="0.01" placeholder="0.00">
                    <label class="form-label small fw-bold mt-2" for="lmp-overall-my-lmp-input">My LMP</label>
                    <input type="number" class="form-control" id="lmp-overall-my-lmp-input" step="0.01" min="0.01" placeholder="0.00">
                    <div class="form-text">Saves Std Price and My LMP for this SKU and its Sku Link LMP siblings.</div>
                    <div class="small mt-2" id="lmp-overall-std-msg"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="lmp-overall-std-save">Save</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="lmpOverallLmpModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="lmp-overall-lmp-title">LMP</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0" id="lmp-overall-lmp-body"></div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        (function () {
            function money(value) {
                const n = parseFloat(value);
                if (!isFinite(n) || n <= 0) {
                    return '<span class="text-muted">—</span>';
                }
                return '<span class="lmp-overall-price">$' + n.toFixed(2) + '</span>';
            }

            function countCell(value) {
                return Math.round(parseFloat(value) || 0).toLocaleString('en-US');
            }

            function escHtml(value) {
                return String(value == null ? '' : value)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
            }

            function lmpCell(price, count) {
                const n = parseFloat(price);
                const c = parseInt(count, 10) || 0;
                if ((!isFinite(n) || n <= 0) && c === 0) {
                    return '<span class="text-muted">—</span>';
                }
                let html = '';
                if (isFinite(n) && n > 0) {
                    html += '<span class="lmp-overall-price">$' + n.toFixed(2) + '</span>';
                }
                if (c > 0) {
                    html += ' <a href="#" class="lmp-overall-count" title="View ' + c
                        + ' competitor' + (c === 1 ? '' : 's') + '">(' + c + ')</a>';
                }
                return html;
            }

            function lmpSiteColumn(title, field, countField) {
                return {
                    title: title,
                    field: field,
                    hozAlign: 'center',
                    headerHozAlign: 'center',
                    width: 110,
                    sorter: 'number',
                    formatter: function (cell) {
                        return lmpCell(cell.getValue(), cell.getRow().getData()[countField]);
                    },
                    cellClick: function (e, cell) {
                        e.preventDefault();
                        e.stopPropagation();
                        const data = cell.getRow().getData();
                        if (data.is_parent_summary) return;
                        openLmpModal(data, field);
                    },
                };
            }

            const lmpSites = {
                lmp_amz: { label: 'LMP amz', url: '/amazon/competitors' },
                lmp_ebay: { label: 'LMP ebay', url: '/ebay-lmp-data' },
                lmp_temu: { label: 'LMP temu', url: '/cvr-master-temu-lmp' },
                lmp_google: { label: 'LMP Google', url: '/google-lmp-data' },
            };
            const lmpModalEl = document.getElementById('lmpOverallLmpModal');

            function showBsModal(el) {
                if (!el || !window.bootstrap || !bootstrap.Modal) return null;
                if (el.parentElement !== document.body) document.body.appendChild(el);
                const modal = bootstrap.Modal.getOrCreateInstance(el);
                modal.show();
                return modal;
            }

            function competitorPrice(row) {
                const landed = parseFloat(row.landed_price);
                if (isFinite(landed) && landed > 0) return landed;
                const total = parseFloat(row.total_price);
                if (isFinite(total) && total > 0) return total;
                return parseFloat(row.price) || 0;
            }

            function renderLmpCompetitors(list) {
                const rows = (Array.isArray(list) ? list : []).slice().sort(function (a, b) {
                    return competitorPrice(a) - competitorPrice(b);
                });
                if (!rows.length) {
                    return '<div class="text-muted p-3">No competitors</div>';
                }
                return '<table class="table table-sm align-middle mb-0"><thead class="table-light"><tr>'
                    + '<th style="width:52px;"></th><th>Title</th><th class="text-end" style="width:90px;">Price</th><th style="width:70px;">Link</th>'
                    + '</tr></thead><tbody>'
                    + rows.map(function (row) {
                        const price = competitorPrice(row);
                        const title = row.product_title || row.title || '';
                        const link = row.product_link || row.link || '';
                        const img = row.image
                            ? '<img class="lmp-overall-comp-img" src="' + escHtml(row.image) + '" alt="">'
                            : '';
                        const ignored = row.ignored ? ' class="lmp-overall-ignored"' : '';
                        return '<tr' + ignored + '><td>' + img + '</td><td>' + escHtml(title) + '</td>'
                            + '<td class="text-end"><span class="lmp-overall-price">'
                            + (price > 0 ? ('$' + price.toFixed(2)) : '—') + '</span></td><td>'
                            + (link ? '<a href="' + escHtml(link) + '" target="_blank" rel="noopener">Open</a>' : '—')
                            + '</td></tr>';
                    }).join('')
                    + '</tbody></table>';
            }

            function openLmpModal(row, field) {
                const site = lmpSites[field];
                if (!site || !showBsModal(lmpModalEl)) return;
                const sku = String(row.sku || '').trim();
                document.getElementById('lmp-overall-lmp-title').textContent = site.label + ' — ' + sku;
                document.getElementById('lmp-overall-lmp-body').innerHTML =
                    '<div class="text-center text-muted py-4">Loading competitors…</div>';
                const params = new URLSearchParams();
                params.set('sku', sku);
                (Array.isArray(row.linked_lmp_skus) ? row.linked_lmp_skus : []).forEach(function (linked) {
                    params.append('linked_lmp_skus[]', linked);
                });
                fetch(site.url + '?' + params.toString(), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                }).then(function (r) {
                    return r.json().then(function (body) {
                        if (!r.ok) throw new Error((body && (body.error || body.message)) || 'Failed to load competitors');
                        return body;
                    });
                }).then(function (body) {
                    const raw = body && (body.competitors || body.data) ? (body.competitors || body.data) : [];
                    const list = Array.isArray(raw) ? raw : Object.values(raw || {});
                    document.getElementById('lmp-overall-lmp-body').innerHTML = renderLmpCompetitors(list);
                }).catch(function (err) {
                    document.getElementById('lmp-overall-lmp-body').innerHTML =
                        '<div class="text-danger p-3">' + escHtml(err.message || 'Failed to load competitors') + '</div>';
                });
            }

            function pctCell(value, styleName) {
                if (value == null || value === '') return '<span class="text-muted">—</span>';
                const pct = parseFloat(value);
                if (!isFinite(pct)) return '<span class="text-muted">—</span>';
                const fn = window.MetricPctColors && MetricPctColors[styleName];
                const style = typeof fn === 'function' ? (fn(pct) || '') : '';
                return '<span style="' + style + '">' + Math.round(pct) + '%</span>';
            }

            function percentColumn(title, field, styleName, tip) {
                return {
                    title: title,
                    field: field,
                    hozAlign: 'center',
                    headerHozAlign: 'center',
                    width: 80,
                    sorter: 'number',
                    headerTooltip: tip,
                    formatter: function (cell) {
                        return pctCell(cell.getValue(), styleName);
                    },
                };
            }

            let stdEditRow = null;
            const stdModalEl = document.getElementById('lmpOverallStdModal');

            function openStdModal(row) {
                stdEditRow = row;
                const data = row.getData();
                document.getElementById('lmp-overall-std-sku').textContent = data.sku || '';
                const current = parseFloat(data.std_price);
                const mine = parseFloat(data.my_lmp);
                document.getElementById('lmp-overall-std-input').value =
                    (isFinite(current) && current > 0) ? current.toFixed(2) : '';
                document.getElementById('lmp-overall-my-lmp-input').value =
                    (isFinite(mine) && mine > 0) ? mine.toFixed(2) : '';
                document.getElementById('lmp-overall-std-msg').textContent = '';
                showBsModal(stdModalEl);
            }

            function applyEditToLinkedRows(sku, fields, appliedSkus) {
                const target = String(sku || '').trim().toUpperCase();
                const applied = new Set((appliedSkus || []).map(function (s) {
                    return String(s || '').trim().toUpperCase();
                }).filter(Boolean));
                if (target) applied.add(target);
                const updates = [];
                table.getData().forEach(function (data) {
                    const rowSku = String(data.sku || '').trim().toUpperCase();
                    const linked = Array.isArray(data.linked_lmp_skus) ? data.linked_lmp_skus : [];
                    const inGroup = applied.has(rowSku)
                        || linked.some(function (s) { return String(s || '').trim().toUpperCase() === target; });
                    if (inGroup) updates.push(Object.assign({ sku: data.sku }, fields));
                });
                if (updates.length) table.updateData(updates);
            }

            const table = new Tabulator('#lmp-overall-table', {
                index: 'sku',
                height: '70vh',
                layout: 'fitDataStretch',
                placeholder: 'Loading…',
                selectableRows: true,
                selectableRowsRollingSelection: true,
                pagination: true,
                paginationSize: 100,
                paginationSizeSelector: [50, 100, 250, 500],
                ajaxURL: @json(route('lmp.overall.data')),
                ajaxConfig: 'GET',
                ajaxResponse: function (url, params, response) {
                    const meta = response && response.meta ? response.meta : {};
                    document.getElementById('lmp-overall-status').textContent =
                        'Loaded · ' + (meta.refreshed_at || '') +
                        ' · SKUs: ' + (meta.sku_count || 0).toLocaleString()
                        + ' · Parents: ' + (meta.parent_count || 0).toLocaleString();
                    return (response && response.data) ? response.data : [];
                },
                rowFormatter: function (row) {
                    const el = row.getElement();
                    if (!el) return;
                    if (row.getData().is_parent_summary) el.classList.add('lmp-overall-parent');
                    else el.classList.remove('lmp-overall-parent');
                },
                columns: [
                    {
                        title: 'Select',
                        formatter: 'rowSelection',
                        titleFormatter: 'rowSelection',
                        titleFormatterParams: { rowRange: 'active' },
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        headerSort: false,
                        width: 70,
                        frozen: true,
                    },
                    {
                        title: 'image',
                        field: 'image',
                        hozAlign: 'center',
                        headerSort: false,
                        width: 64,
                        frozen: true,
                        formatter: function (cell) {
                            const url = cell.getValue();
                            if (!url) return '<span class="text-muted">—</span>';
                            const safe = String(url).replace(/"/g, '&quot;');
                            return '<img class="lmp-overall-thumb" src="' + safe + '" alt="">';
                        },
                    },
                    { title: 'parent', field: 'parent', width: 130, frozen: true },
                    { title: 'sku', field: 'sku', width: 150, frozen: true },
                    {
                        title: 'inv',
                        field: 'inv',
                        hozAlign: 'center',
                        width: 70,
                        sorter: 'number',
                        formatter: function (cell) { return countCell(cell.getValue()); },
                    },
                    {
                        title: 'ovl30',
                        field: 'ovl30',
                        hozAlign: 'center',
                        width: 80,
                        sorter: 'number',
                        headerTooltip: 'Overall L30 units from Shopify',
                        formatter: function (cell) { return countCell(cell.getValue()); },
                    },
                    {
                        title: 'dil',
                        field: 'dil',
                        hozAlign: 'center',
                        width: 70,
                        sorter: 'number',
                        headerTooltip: 'dil = ovl30 / inv × 100',
                        formatter: function (cell) {
                            const row = cell.getRow().getData();
                            const inv = parseFloat(row.inv) || 0;
                            const ov = parseFloat(row.ovl30) || 0;
                            if (inv <= 0) return '<span style="color:#6c757d;">0%</span>';
                            const dil = (ov / inv) * 100;
                            let color = '#e83e8c';
                            if (dil < 25) color = '#dc3545';
                            else if (dil < 50) color = '#28a745';
                            return '<span style="color:' + color + ';font-weight:600;">' + Math.round(dil) + '%</span>';
                        },
                    },
                    {
                        title: 'Std Price',
                        field: 'std_price',
                        hozAlign: 'center',
                        width: 90,
                        sorter: 'number',
                        headerTooltip: 'Amazon Standard Price (amazon_data_view.STANDARD_PRICE)',
                        formatter: function (cell) { return money(cell.getValue()); },
                    },
                    {
                        title: 'Edit',
                        field: 'edit',
                        hozAlign: 'center',
                        headerSort: false,
                        width: 52,
                        formatter: function (cell) {
                            if (cell.getRow().getData().is_parent_summary) return '';
                            return '<button type="button" class="btn btn-sm btn-link text-primary p-0" title="Edit"><i class="ri-pencil-line" style="font-size:16px;"></i></button>';
                        },
                        cellClick: function (e, cell) {
                            e.stopPropagation();
                            if (cell.getRow().getData().is_parent_summary) return;
                            openStdModal(cell.getRow());
                        },
                    },
                    {
                        title: 'Avg Price',
                        field: 'avg_price',
                        hozAlign: 'center',
                        width: 90,
                        sorter: 'number',
                        headerTooltip: 'Avg Price from /pricing-master-cvr',
                        formatter: function (cell) { return money(cell.getValue()); },
                    },
                    percentColumn('Avg GPFT%', 'gpft', 'gpftStyle', 'Avg GPFT% from /pricing-master-cvr'),
                    percentColumn('Avg GROI%', 'groi', 'groiStyle', 'Avg GROI% from /pricing-master-cvr'),
                    percentColumn('Avg NPFT%', 'npft', 'npftStyle', 'Avg NPFT% from /pricing-master-cvr'),
                    percentColumn('Avg NROI%', 'nroi', 'nroiStyle', 'Avg NROI% from /pricing-master-cvr'),
                    lmpSiteColumn('LMP amz', 'lmp_amz', 'lmp_amz_count'),
                    lmpSiteColumn('LMP ebay', 'lmp_ebay', 'lmp_ebay_count'),
                    lmpSiteColumn('LMP temu', 'lmp_temu', 'lmp_temu_count'),
                    lmpSiteColumn('LMP Google', 'lmp_google', 'lmp_google_count'),
                    {
                        title: 'OV LMP',
                        field: 'ov_lmp',
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        width: 90,
                        sorter: 'number',
                        headerTooltip: 'Lowest LMP across Amazon, eBay, Temu, and Google',
                        formatter: function (cell) { return money(cell.getValue()); },
                    },
                    {
                        title: 'Avg LMP',
                        field: 'avg_lmp',
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        width: 90,
                        sorter: 'number',
                        headerTooltip: 'Average LMP across Amazon, eBay, Temu, and Google',
                        formatter: function (cell) { return money(cell.getValue()); },
                    },
                    {
                        title: 'My LMP',
                        field: 'my_lmp',
                        hozAlign: 'center',
                        width: 90,
                        sorter: 'number',
                        headerTooltip: 'Manual My LMP (amazon_data_view.MY_LMP)',
                        formatter: function (cell) { return money(cell.getValue()); },
                    },
                ],
            });

            function updateCounts() {
                const selected = table.getSelectedRows().length;
                const active = table.getDataCount('active');
                document.getElementById('lmp-overall-total').textContent = 'Total: ' + active.toLocaleString();
                document.getElementById('lmp-overall-selected').textContent = 'Selected: ' + selected.toLocaleString();
            }

            table.on('dataProcessed', updateCounts);
            table.on('rowSelectionChanged', updateCounts);

            let searchTimer = null;
            function applySearch() {
                const parentTerm = (document.getElementById('lmp-overall-search-parent').value || '').trim().toLowerCase();
                const skuTerm = (document.getElementById('lmp-overall-search-sku').value || '').trim().toLowerCase();
                if (parentTerm === '' && skuTerm === '') {
                    table.clearFilter();
                    return;
                }
                table.setFilter(function (data) {
                    const parentOk = parentTerm === ''
                        || String(data.parent || '').toLowerCase().indexOf(parentTerm) !== -1;
                    const skuOk = skuTerm === ''
                        || String(data.sku || '').toLowerCase().indexOf(skuTerm) !== -1;
                    return parentOk && skuOk;
                });
            }
            ['lmp-overall-search-parent', 'lmp-overall-search-sku'].forEach(function (id) {
                document.getElementById(id).addEventListener('input', function () {
                    clearTimeout(searchTimer);
                    searchTimer = setTimeout(applySearch, 200);
                });
            });

            document.getElementById('lmp-overall-refresh').addEventListener('click', function () {
                document.getElementById('lmp-overall-status').textContent = 'Loading…';
                table.setData();
            });

            document.getElementById('lmp-overall-std-save').addEventListener('click', function () {
                const data = stdEditRow ? stdEditRow.getData() : null;
                const sku = data ? String(data.sku || '').trim() : '';
                const std = parseFloat(document.getElementById('lmp-overall-std-input').value);
                const myRaw = document.getElementById('lmp-overall-my-lmp-input').value.trim();
                const myLmp = myRaw === '' ? null : parseFloat(myRaw);
                const msg = document.getElementById('lmp-overall-std-msg');
                if (!sku || !isFinite(std) || std <= 0) {
                    msg.className = 'small mt-2 text-danger';
                    msg.textContent = 'Enter a Std Price greater than 0.';
                    return;
                }
                if (myLmp !== null && (!isFinite(myLmp) || myLmp <= 0)) {
                    msg.className = 'small mt-2 text-danger';
                    msg.textContent = 'My LMP must be greater than 0, or left blank.';
                    return;
                }
                const btn = this;
                btn.disabled = true;
                msg.className = 'small mt-2 text-muted';
                msg.textContent = 'Saving…';
                fetch(@json(route('lmp.overall.save')), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        sku: sku,
                        std_price: std,
                        my_lmp: myLmp,
                    }),
                }).then(function (r) {
                    return r.json().then(function (body) {
                        if (!r.ok) throw new Error((body && body.error) ? body.error : 'Save failed');
                        return body;
                    });
                }).then(function (body) {
                    applyEditToLinkedRows(sku, {
                        std_price: parseFloat(body.std_price) || std,
                        my_lmp: body.my_lmp,
                    }, body.applied_skus);
                    msg.className = 'small mt-2 text-success';
                    msg.textContent = 'Saved.';
                    setTimeout(function () {
                        if (stdModalEl && window.bootstrap) {
                            bootstrap.Modal.getInstance(stdModalEl)?.hide();
                        }
                    }, 400);
                }).catch(function (err) {
                    msg.className = 'small mt-2 text-danger';
                    msg.textContent = err.message || 'Save failed';
                }).finally(function () {
                    btn.disabled = false;
                });
            });
        })();
    </script>
@endsection
