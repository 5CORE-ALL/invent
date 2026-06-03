@extends('layouts.vertical', ['title' => 'Amz Comp Jungle', 'mode' => 'light'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }

        #bulkEditBtn {
            color: #fff !important;
        }

        .tabulator-row.parent-row {
            background-color: #bde0ff !important;
            font-weight: bold !important;
            min-height: 48px !important;
        }

        .tabulator-row.parent-row .tabulator-cell {
            min-height: 48px !important;
            height: 48px !important;
            padding-top: 8px !important;
            padding-bottom: 8px !important;
            overflow: visible !important;
            vertical-align: middle !important;
        }
    </style>
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="javascript: void(0);">LMP's Master</a></li>
                        <li class="breadcrumb-item active">Amz Comp Jungle</li>
                    </ol>
                </div>
                <h4 class="page-title">Amz Comp Jungle</h4>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3 gap-2 flex-wrap">
                        <button type="button" class="btn btn-primary btn-sm fs-6 p-2" id="bulkEditBtn" disabled>
                            <i class="fas fa-layer-group me-1"></i> Bulk Edit ASINs
                        </button>
                        <span class="badge bg-secondary fs-6 p-2" id="selectedCountBadge" style="color:black;font-weight:bold;">0 selected</span>
                        <span class="badge bg-success fs-6 p-2" id="dataBadge" style="color:black;font-weight:bold;" title="Filled competitor ASINs / (SKU count × 10)">Data: 0%</span>
                        <div class="ms-auto btn-group btn-group-sm" role="group" aria-label="Import">
                            <a href="{{ route('repricer.amz-comp-jungle.import-template') }}" class="btn btn-outline-secondary">
                                <i class="fas fa-download me-1"></i> Template
                            </a>
                            <button type="button" class="btn btn-primary" id="importBtn">
                                <i class="fas fa-file-import me-1"></i> Import
                            </button>
                            <input type="file" id="importFile" accept=".csv,text/csv" style="display: none;">
                        </div>
                    </div>
                    <div id="amz-comp-jungle-table-wrapper" style="height: calc(100vh - 240px); display: flex; flex-direction: column;">
                        <div id="amz-comp-jungle-table" style="flex: 1;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Competitor ASINs Modal -->
<div class="modal fade" id="asinModal" tabindex="-1" aria-labelledby="asinModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="asinModalLabel">SKU</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="asinModalSku">
                <div class="row g-2">
                    @for ($i = 1; $i <= 10; $i++)
                        <div class="col-md-6">
                            <label class="form-label mb-1" for="asin_{{ $i }}">ASIN {{ $i }}</label>
                            <input type="text" class="form-control form-control-sm asin-input" id="asin_{{ $i }}" data-index="{{ $i - 1 }}" placeholder="ASIN {{ $i }}">
                        </div>
                    @endfor
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveAsinsBtn">Save</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            function escAttr(str) {
                return String(str == null ? '' : str)
                    .replace(/&/g, '&amp;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;');
            }

            const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            // Map of SKU -> { search_kw, asins[] }, loaded before the table renders.
            let savedKws = {};
            // Normalised (trimmed + lowercased) lookup so table SKUs still match
            // saved keys even with stray whitespace / casing differences.
            let savedKwsNorm = {};

            function normSku(sku) {
                return String(sku == null ? '' : sku).trim().toLowerCase();
            }

            function rebuildSavedKwsNorm() {
                savedKwsNorm = {};
                Object.keys(savedKws).forEach(function(k) {
                    savedKwsNorm[normSku(k)] = savedKws[k];
                });
            }

            function lookupSaved(sku) {
                if (!sku) return null;
                if (savedKws[sku]) return savedKws[sku];
                return savedKwsNorm[normSku(sku)] || null;
            }

            function getSaved(sku) {
                if (!savedKws[sku]) savedKws[sku] = { search_kw: '', asins: [] };
                savedKwsNorm[normSku(sku)] = savedKws[sku];
                return savedKws[sku];
            }

            function loadSavedKws() {
                return fetch("{{ route('repricer.amz-comp-jungle.kws') }}", {
                        headers: { 'Accept': 'application/json' }
                    })
                    .then(r => r.json())
                    .then(res => {
                        savedKws = (res && res.data) ? res.data : {};
                        rebuildSavedKwsNorm();
                    })
                    .catch(() => { savedKws = {}; savedKwsNorm = {}; });
            }

            // Competitor ASINs sourced from the main Amazon competitor data
            // (amazon_sku_competitors), keyed by a normalised SKU. Used so the
            // table %, competitor columns and badge reflect available data by
            // default — even before anything is saved here.
            let compAsins = {};

            // Match the server-side normalizeSkuKey(): trim, collapse whitespace,
            // uppercase.
            function normCompKey(sku) {
                return String(sku == null ? '' : sku).trim().replace(/\s+/g, ' ').toUpperCase();
            }

            function loadCompetitorAsins() {
                return fetch("{{ route('repricer.amz-comp-jungle.competitor-asins') }}", {
                        headers: { 'Accept': 'application/json' }
                    })
                    .then(r => r.json())
                    .then(res => {
                        compAsins = (res && res.data) ? res.data : {};
                    })
                    .catch(() => { compAsins = {}; });
            }

            function compAsinsFor(sku) {
                if (!sku) return [];
                const key = normCompKey(sku);
                return Array.isArray(compAsins[key]) ? compAsins[key] : [];
            }

            // Merge saved ASINs (priority, keep their slots) with competitor-source
            // ASINs filling the remaining empty slots; dedupe, cap at 10.
            function mergeAsins(savedArr, compArr) {
                const merged = (savedArr || []).slice(0, 10);
                const seen = new Set(
                    merged.filter(function(v) { return v != null && String(v).trim() !== ''; })
                          .map(function(v) { return String(v).trim().toUpperCase(); })
                );
                (compArr || []).forEach(function(a) {
                    const val = String(a == null ? '' : a).trim();
                    if (val === '' || seen.has(val.toUpperCase())) return;
                    let slot = -1;
                    for (let i = 0; i < 10; i++) {
                        if (merged[i] == null || String(merged[i]).trim() === '') { slot = i; break; }
                    }
                    if (slot === -1) return;
                    merged[slot] = val;
                    seen.add(val.toUpperCase());
                });
                return merged;
            }

            // Effective ASINs for a row = saved values, then competitor-source
            // values filling the gaps. Drives the %, competitor columns and badge.
            function effectiveAsins(row) {
                if (!row) return [];
                const saved = Array.isArray(row.asins) ? row.asins : [];
                const sku = row['(Child) sku'] || '';
                return mergeAsins(saved, compAsinsFor(sku));
            }

            function saveAsins(sku, asins) {
                return fetch("{{ route('repricer.amz-comp-jungle.save-asins') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': CSRF_TOKEN,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ sku: sku, asins: asins })
                }).then(r => r.json());
            }

            function saveKw(sku, searchKw) {
                return fetch("{{ route('repricer.amz-comp-jungle.save-kw') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': CSRF_TOKEN,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ sku: sku, search_kw: searchKw })
                }).then(r => r.json());
            }

            // Build the 10 dot columns (1..10) showing green when an ASIN is set, red otherwise.
            function asinDotColumns() {
                const cols = [];
                for (let i = 0; i < 10; i++) {
                    cols.push({
                        title: String(i + 1),
                        field: "asin_slot_" + (i + 1),
                        hozAlign: "center",
                        width: 36,
                        headerSort: false,
                        visible: false,
                        headerTooltip: "Competitor ASIN " + (i + 1),
                        formatter: (function(idx) {
                            return function(cell) {
                                const row = cell.getRow().getData();
                                const sku = row['(Child) sku'] || '';
                                if (!sku || row.is_parent_summary) return '';
                                const asins = effectiveAsins(row);
                                const val = asins[idx];
                                const hasVal = val != null && String(val).trim() !== '';
                                const color = hasVal ? '#28a745' : '#dc3545';
                                const tip = hasVal ? String(val).trim() : 'No ASIN';
                                return `<span title="${escAttr(tip)}" style="display:inline-block;width:10px;height:10px;border-radius:50%;background:${color};"></span>`;
                            };
                        })(i)
                    });
                }
                return cols;
            }

            var table = new Tabulator("#amz-comp-jungle-table", {
                ajaxSorting: false,
                layout: "fitDataStretch",
                pagination: true,
                paginationSize: 100,
                selectableRows: true,
                selectableRowsRangeMode: "click",
                selectableRowsCheck: function(row) {
                    const d = row.getData();
                    return !d.is_parent_summary && !!d['(Child) sku'];
                },
                initialSort: [{ column: "Parent", dir: "asc" }],
                ajaxResponse: function(url, params, response) {
                    const payload = response.data || response;
                    if (Array.isArray(payload)) {
                        payload.forEach(function(row) {
                            const sku = row['(Child) sku'] || '';
                            const saved = lookupSaved(sku);
                            row.search_kw = saved ? (saved.search_kw || '') : '';
                            row.asins = saved && Array.isArray(saved.asins) ? saved.asins : [];
                        });
                    }
                    return payload;
                },
                rowFormatter: function(row) {
                    const data = row.getData();
                    const el = row.getElement();
                    if (data.is_parent_summary === true) {
                        el.classList.add("parent-row");
                    } else {
                        el.classList.remove("parent-row");
                    }
                },
                columns: [
                    {
                        title: "",
                        field: "row_select",
                        formatter: "rowSelection",
                        titleFormatter: "rowSelection",
                        titleFormatterParams: { rowRange: "active" },
                        hozAlign: "center",
                        headerHozAlign: "center",
                        headerSort: false,
                        width: 40,
                        frozen: true,
                        cellClick: function(e, cell) {
                            cell.getRow().toggleSelect();
                        }
                    },
                    {
                        title: "Image",
                        field: "image_path",
                        hozAlign: "center",
                        width: 80,
                        headerSort: false,
                        formatter: function(cell) {
                            const imagePath = cell.getValue();
                            if (imagePath) {
                                return `<img src="${imagePath}" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;" />`;
                            }
                            return '';
                        }
                    },
                    {
                        title: "Parent",
                        field: "Parent",
                        headerFilter: "input",
                        headerFilterPlaceholder: "Search Parent...",
                        cssClass: "text-primary",
                        tooltip: true,
                        frozen: true,
                        width: 150,
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var val = row['Parent'] != null ? row['Parent'] : (row['parent'] != null ? row['parent'] : '');
                            var s = (val != null && val !== '') ? String(val).trim() : '';
                            if (!s && row['(Child) sku']) {
                                var sku = String(row['(Child) sku']).trim();
                                if (sku.toUpperCase().indexOf('PARENT ') === 0) s = sku.slice(7).trim();
                            }
                            return s || '—';
                        }
                    },
                    {
                        title: "SKU",
                        field: "(Child) sku",
                        headerFilter: "input",
                        headerFilterPlaceholder: "Search SKU...",
                        frozen: true,
                        width: 250,
                        formatter: function(cell) {
                            const sku = cell.getValue();
                            const rowData = cell.getRow().getData();
                            if (rowData.is_parent_summary) {
                                return `<span style="font-weight: bold;">${sku}</span>`;
                            }
                            return `<div style="display: flex; align-items: center; gap: 5px;">
                                <span>${sku}</span>
                                <button class="btn btn-sm btn-link copy-sku-btn p-0" data-sku="${escAttr(sku)}" title="Copy SKU">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>`;
                        }
                    },
                    {
                        title: "ASIN",
                        field: "asin",
                        headerFilter: "input",
                        headerFilterPlaceholder: "Search ASIN...",
                        width: 140,
                        formatter: function(cell) {
                            const asin = (cell.getValue() || '').toString().trim();
                            const rowData = cell.getRow().getData();
                            if (rowData.is_parent_summary || !asin) return '';
                            return `<div style="display: flex; align-items: center; gap: 5px;">
                                <a href="https://www.amazon.com/dp/${encodeURIComponent(asin)}" target="_blank" rel="noopener" title="Open ${escAttr(asin)} on Amazon.com">${escAttr(asin)}</a>
                                <button class="btn btn-sm btn-link copy-asin-btn p-0" data-asin="${escAttr(asin)}" title="Copy ASIN">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>`;
                        }
                    },
                    {
                        title: "B/S",
                        field: "buyer_link",
                        hozAlign: "center",
                        width: 70,
                        headerSort: false,
                        headerTooltip: "Buyer / Seller links",
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const buyerLink = rowData.buyer_link || '';
                            const sellerLink = rowData.seller_link || '';
                            if (!buyerLink && !sellerLink) return '-';
                            let html = '<div style="display: flex; justify-content: center; gap: 5px;">';
                            if (buyerLink) {
                                html += `<a href="${buyerLink.replace(/"/g, '&quot;')}" target="_blank" rel="noopener" class="text-decoration-none fw-semibold" style="color: #2c6ed5;" title="Buyer Link">B</a>`;
                            }
                            if (sellerLink) {
                                html += `<a href="${sellerLink.replace(/"/g, '&quot;')}" target="_blank" rel="noopener" class="text-decoration-none fw-semibold" style="color: #47ad77;" title="Seller Link">S</a>`;
                            }
                            html += '</div>';
                            return html;
                        }
                    },
                    {
                        title: "INV",
                        field: "INV",
                        hozAlign: "center",
                        width: 70,
                        sorter: "number",
                        formatter: function(cell) {
                            return Math.round(parseFloat(cell.getValue()) || 0);
                        }
                    },
                    {
                        title: "OV L30",
                        field: "L30",
                        hozAlign: "center",
                        width: 70,
                        sorter: "number",
                        formatter: function(cell) {
                            return Math.round(parseFloat(cell.getValue()) || 0);
                        }
                    },
                    {
                        title: "Dil %",
                        field: "E Dil%",
                        hozAlign: "center",
                        width: 70,
                        sorter: "number",
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const INV = parseFloat(rowData.INV) || 0;
                            const OVL30 = parseFloat(rowData['L30']) || 0;

                            if (INV === 0) return '<span style="color: #6c757d;">0%</span>';

                            const dil = (OVL30 / INV) * 100;
                            let color = '';
                            if (dil < 16.66) color = '#a00211';
                            else if (dil >= 16.66 && dil < 25) color = '#ffc107';
                            else if (dil >= 25 && dil < 50) color = '#28a745';
                            else color = '#e83e8c';

                            return `<span style="color: ${color}; font-weight: 600;">${Math.round(dil)}%</span>`;
                        }
                    },
                    {
                        title: "Search KW",
                        field: "search_kw",
                        width: 220,
                        headerSort: false,
                        editor: "input",
                        editable: function(cell) {
                            // Only allow editing real SKU rows (skip parent summary rows)
                            const row = cell.getRow().getData();
                            return !row.is_parent_summary && !!(row['(Child) sku']);
                        },
                        formatter: function(cell) {
                            const value = cell.getValue();
                            const row = cell.getRow().getData();
                            if (value) {
                                return `<div style="display: flex; align-items: center; gap: 5px;">
                                    <button class="btn btn-sm btn-link copy-kw-btn p-0" data-kw="${escAttr(value)}" title="Copy Search KW">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                    <span>${escAttr(value)}</span>
                                </div>`;
                            }
                            if (row.is_parent_summary || !row['(Child) sku']) return '';
                            return '<span style="color: #b0b0b0; font-style: italic;">Click to add…</span>';
                        },
                        cellEdited: function(cell) {
                            const row = cell.getRow().getData();
                            const sku = row['(Child) sku'] || '';
                            if (!sku) return;
                            const value = cell.getValue() || '';
                            getSaved(sku).search_kw = value;
                            saveKw(sku, value).then(function(res) {
                                if (!res || !res.success) {
                                    console.error('Failed to save Search KW for', sku, res);
                                }
                            }).catch(function(err) {
                                console.error('Error saving Search KW for', sku, err);
                            });
                        }
                    },
                    {
                        title: "Amazon",
                        field: "amazon_link",
                        hozAlign: "center",
                        width: 70,
                        headerSort: false,
                        headerTooltip: "Open on Amazon.com",
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            const asin = (row.asin || '').toString().trim();
                            const kw = (row.search_kw || '').toString().trim();
                            const sku = (row['(Child) sku'] || '').toString().trim();

                            let url = '';
                            if (asin) {
                                url = 'https://www.amazon.com/dp/' + encodeURIComponent(asin);
                            } else if (kw) {
                                url = 'https://www.amazon.com/s?k=' + encodeURIComponent(kw);
                            } else if (sku && !row.is_parent_summary) {
                                url = 'https://www.amazon.com/s?k=' + encodeURIComponent(sku);
                            }

                            if (!url) return '';
                            return `<a href="${url}" target="_blank" rel="noopener" title="Open on Amazon.com" style="color: #ff9900;"><i class="fas fa-external-link-alt"></i></a>`;
                        }
                    },
                    {
                        title: "<span title='Add competitor ASINs'><i class='fas fa-plus'></i></span>",
                        field: "asin_add",
                        hozAlign: "center",
                        width: 45,
                        headerSort: false,
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            const sku = row['(Child) sku'] || '';
                            if (!sku || row.is_parent_summary) return '';
                            return `<button type="button" class="btn btn-sm btn-link p-0 add-asins-btn" data-sku="${escAttr(sku)}" title="Add competitor ASINs"><i class="fas fa-plus-circle" style="color: #2c6ed5; font-size: 15px;"></i></button>`;
                        }
                    },
                    {
                        title: "%",
                        field: "asin_fill_pct",
                        hozAlign: "center",
                        width: 60,
                        headerSort: false,
                        headerTooltip: "Filled competitor ASINs (out of 10)",
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            const sku = row['(Child) sku'] || '';
                            if (!sku || row.is_parent_summary) return '';
                            const asins = effectiveAsins(row);
                            let filled = 0;
                            for (let i = 0; i < 10; i++) {
                                if (asins[i] != null && String(asins[i]).trim() !== '') filled++;
                            }
                            const pct = Math.round((filled / 10) * 100);
                            let color = '#dc3545';
                            if (pct >= 100) color = '#28a745';
                            else if (pct >= 50) color = '#ffc107';
                            return `<span style="color: ${color}; font-weight: 600;" title="${filled}/10">${pct}%</span>`;
                        }
                    },
                    ...asinDotColumns()
                ]
            });

            // Load saved keywords + competitor ASINs first, then fetch the table
            // data so each row is populated with its saved/competitor values.
            Promise.all([loadSavedKws(), loadCompetitorAsins()]).then(function() {
                table.setData("/amazon-data-json");
            });

            // Prevent the Search KW copy button from opening the cell editor.
            document.addEventListener('mousedown', function(e) {
                if (e.target.closest('.copy-kw-btn')) {
                    e.stopPropagation();
                }
            }, true);

            const asinModalEl = document.getElementById('asinModal');
            const asinModal = new bootstrap.Modal(asinModalEl);

            // When set (array of SKUs), the modal is in bulk-edit mode.
            let bulkSkus = null;

            function setAsinInputs(asins) {
                document.querySelectorAll('#asinModal .asin-input').forEach(function(input) {
                    const idx = parseInt(input.getAttribute('data-index'), 10);
                    input.value = (asins && asins[idx] != null) ? asins[idx] : '';
                });
            }

            // Pull the SKU's competitor ASINs from the same source the main Amazon
            // table uses, and fill the modal's empty slots by default (never
            // overwriting values already saved for this SKU).
            function fillCompetitorAsinsFromSource(sku, baseAsins) {
                fetch('/amazon/competitors?sku=' + encodeURIComponent(sku), {
                        headers: { 'Accept': 'application/json' }
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (!res || !res.success || !Array.isArray(res.competitors)) return;
                        // Ignore if the user already moved on to a different SKU.
                        if (document.getElementById('asinModalSku').value !== sku) return;

                        const merged = (baseAsins || []).slice(0, 10);
                        const seen = new Set(
                            merged.filter(function(v) { return v != null && String(v).trim() !== ''; })
                                  .map(function(v) { return String(v).trim().toUpperCase(); })
                        );

                        const compAsins = res.competitors
                            .map(function(c) { return (c && c.asin != null) ? String(c.asin).trim() : ''; })
                            .filter(function(a) { return a !== ''; });

                        compAsins.forEach(function(a) {
                            if (seen.has(a.toUpperCase())) return;
                            let slot = -1;
                            for (let i = 0; i < 10; i++) {
                                if (merged[i] == null || String(merged[i]).trim() === '') { slot = i; break; }
                            }
                            if (slot === -1) return; // all 10 slots full
                            merged[slot] = a;
                            seen.add(a.toUpperCase());
                        });

                        setAsinInputs(merged);
                    })
                    .catch(function() { /* leave saved values as-is on failure */ });
            }

            function openAsinModal(sku) {
                bulkSkus = null;
                document.getElementById('asinModalSku').value = sku;
                document.getElementById('asinModalLabel').textContent = sku;
                const savedForSku = lookupSaved(sku);
                const asins = (savedForSku && Array.isArray(savedForSku.asins)) ? savedForSku.asins : [];
                setAsinInputs(asins);
                asinModal.show();
                fillCompetitorAsinsFromSource(sku, asins);
            }

            function openBulkAsinModal(skus) {
                bulkSkus = skus;
                document.getElementById('asinModalSku').value = '';
                document.getElementById('asinModalLabel').textContent = 'Bulk Edit ASINs (' + skus.length + ' SKUs)';
                setAsinInputs([]);
                asinModal.show();
            }

            function applyAsinsToRow(sku, asins) {
                getSaved(sku).asins = asins;
                const row = table.getRows().find(function(r) {
                    return (r.getData()['(Child) sku'] || '') === sku;
                });
                if (row) {
                    row.update({ asins: asins });
                    row.reformat();
                }
            }

            document.getElementById('saveAsinsBtn').addEventListener('click', function() {
                const asins = [];
                document.querySelectorAll('#asinModal .asin-input').forEach(function(input) {
                    const idx = parseInt(input.getAttribute('data-index'), 10);
                    asins[idx] = (input.value || '').trim();
                });

                const btn = this;
                btn.disabled = true;

                if (bulkSkus && bulkSkus.length) {
                    const skus = bulkSkus.slice();
                    fetch("{{ route('repricer.amz-comp-jungle.save-asins-bulk') }}", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': CSRF_TOKEN,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ skus: skus, asins: asins })
                    }).then(r => r.json()).then(function(res) {
                        if (res && res.success) {
                            skus.forEach(function(sku) { applyAsinsToRow(sku, asins); });
                            updateDataBadge();
                            asinModal.hide();
                        } else {
                            alert('Failed to save ASINs.');
                        }
                    }).catch(function() {
                        alert('Error saving ASINs.');
                    }).finally(function() {
                        btn.disabled = false;
                    });
                    return;
                }

                const sku = document.getElementById('asinModalSku').value;
                if (!sku) { btn.disabled = false; return; }
                saveAsins(sku, asins).then(function(res) {
                    if (res && res.success) {
                        applyAsinsToRow(sku, asins);
                        updateDataBadge();
                        asinModal.hide();
                    } else {
                        alert('Failed to save ASINs.');
                    }
                }).catch(function() {
                    alert('Error saving ASINs.');
                }).finally(function() {
                    btn.disabled = false;
                });
            });

            // Selection handling: update count badge + bulk button state.
            const bulkEditBtn = document.getElementById('bulkEditBtn');
            const selectedCountBadge = document.getElementById('selectedCountBadge');

            function selectedSkus() {
                return table.getSelectedData()
                    .map(function(d) { return d['(Child) sku'] || ''; })
                    .filter(function(s) { return !!s; });
            }

            table.on("rowSelectionChanged", function(data, rows) {
                const count = selectedSkus().length;
                selectedCountBadge.textContent = count + ' selected';
                bulkEditBtn.disabled = count === 0;
            });

            // Data % badge: filled competitor ASINs / (SKU count * 10).
            const dataBadge = document.getElementById('dataBadge');

            function updateDataBadge() {
                let skusWithData = 0;
                let filled = 0;
                table.getData().forEach(function(row) {
                    const sku = row['(Child) sku'] || '';
                    if (!sku || row.is_parent_summary) return;
                    const asins = effectiveAsins(row);
                    let rowFilled = 0;
                    for (let i = 0; i < 10; i++) {
                        if (asins[i] != null && String(asins[i]).trim() !== '') rowFilled++;
                    }
                    if (rowFilled > 0) {
                        skusWithData++;
                        filled += rowFilled;
                    }
                });
                // Base the % only on SKUs that have at least one competitor ASIN,
                // so a few populated SKUs aren't drowned out by the full catalog.
                const total = skusWithData * 10;
                const pct = total > 0 ? Math.round((filled / total) * 100) : 0;
                dataBadge.textContent = 'Data: ' + pct + '%';
                dataBadge.title = filled + ' filled / ' + total + ' slots (' + skusWithData + ' SKUs with data × 10)';
            }

            table.on("dataProcessed", updateDataBadge);
            table.on("dataLoaded", updateDataBadge);
            table.on("renderComplete", updateDataBadge);

            bulkEditBtn.addEventListener('click', function() {
                const skus = selectedSkus();
                if (!skus.length) return;
                openBulkAsinModal(skus);
            });

            // Import from CSV.
            const importBtn = document.getElementById('importBtn');
            const importFile = document.getElementById('importFile');

            importBtn.addEventListener('click', function() {
                importFile.click();
            });

            importFile.addEventListener('change', function() {
                const file = this.files && this.files[0];
                if (!file) return;

                const formData = new FormData();
                formData.append('file', file);

                importBtn.disabled = true;
                const originalHtml = importBtn.innerHTML;
                importBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Importing…';

                fetch("{{ route('repricer.amz-comp-jungle.import') }}", {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': CSRF_TOKEN,
                        'Accept': 'application/json'
                    },
                    body: formData
                }).then(r => r.json()).then(function(res) {
                    if (res && res.success) {
                        alert(res.message || 'Import complete.');
                        // Reload saved data + table so imported values appear.
                        loadSavedKws().then(function() {
                            table.setData("/amazon-data-json");
                        });
                    } else {
                        alert((res && res.message) ? res.message : 'Import failed.');
                    }
                }).catch(function() {
                    alert('Error importing file.');
                }).finally(function() {
                    importBtn.disabled = false;
                    importBtn.innerHTML = originalHtml;
                    importFile.value = '';
                });
            });

            document.addEventListener('click', function(e) {
                const skuBtn = e.target.closest('.copy-sku-btn');
                if (skuBtn) {
                    navigator.clipboard.writeText(skuBtn.getAttribute('data-sku') || '');
                    return;
                }
                const kwBtn = e.target.closest('.copy-kw-btn');
                if (kwBtn) {
                    e.stopPropagation();
                    navigator.clipboard.writeText(kwBtn.getAttribute('data-kw') || '');
                    return;
                }
                const asinBtn = e.target.closest('.copy-asin-btn');
                if (asinBtn) {
                    e.stopPropagation();
                    navigator.clipboard.writeText(asinBtn.getAttribute('data-asin') || '');
                    return;
                }
                const addBtn = e.target.closest('.add-asins-btn');
                if (addBtn) {
                    e.stopPropagation();
                    openAsinModal(addBtn.getAttribute('data-sku') || '');
                }
            });
        });
    </script>
@endsection
