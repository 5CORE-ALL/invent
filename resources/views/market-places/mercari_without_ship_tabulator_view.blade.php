@extends('layouts.vertical', ['title' => 'Mercari w/o Ship - Analytics', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Mercari w/o Ship - Analytics',
        'sub_title' => '',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                @if (session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif
                @if (session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
                    <!-- Increase / Decrease / Same Price S Price controls -->
                    <button id="price-mode-btn" type="button" class="btn btn-sm btn-secondary"
                        title="Cycle: Off → Decrease → Increase → Same Price → Off">
                        <i class="fas fa-exchange-alt"></i> Price %
                    </button>
                    <div id="discount-input-container" class="align-items-center gap-2" style="display: none;">
                        <span id="adjust-input-label" class="text-muted small d-none">Same Price ($):</span>
                        <span id="adjust-type-select-wrap">
                        <select id="adjust-type-select" class="form-select form-select-sm" style="width: 130px;">
                            <option value="percentage">Percentage (%)</option>
                            <option value="value">Value ($)</option>
                        </select>
                        </span>
                        <input type="number" id="adjust-amount-input" class="form-control form-control-sm"
                            placeholder="e.g. 10 or 2.50" step="0.1" min="0" style="width: 160px;">
                        <button id="apply-adjust-btn" class="btn btn-sm btn-success">
                            <i class="fas fa-check"></i> Apply
                        </button>
                        <span id="adjust-selected-count" class="text-muted small"></span>
                    </div>

                    {{-- Target ROI% bulk control — back-solves S Price for selected rows so SROI = Target ROI%.
                         Mercari W/O Ship's SPFT / SROI do NOT include shipping (matches the inline cellEdited handler
                         and the Apply % button below). Formula: sprice = LP × (1 + ROI%/100) / factor --}}
                    <div class="d-inline-flex align-items-center gap-1 p-1 border rounded bg-light"
                        id="target-roi-controls"
                        title="Target ROI% — sets S Price = LP × (1 + Target ROI%/100) / factor on every selected row (back-solves so SROI column equals the target)">
                        <label for="target-roi-input" class="form-label mb-0 small fw-bold text-nowrap">
                            Target ROI%:
                        </label>
                        <input type="number" id="target-roi-input" class="form-control form-control-sm text-end"
                            placeholder="e.g. 30" step="0.1" style="width: 80px;"
                            title="Target ROI% applied to all selected rows when you click 'Apply S Price'">
                        <button id="apply-target-roi-btn" class="btn btn-sm btn-success" type="button"
                            title="Compute & save S Price = LP × (1 + Target ROI%/100) / factor for every selected row">
                            <i class="fas fa-calculator"></i> Apply S Price
                        </button>
                    </div>

                    {{-- Target GPFT% bulk control — back-solves S Price for selected rows so SPFT = Target GPFT%.
                         Formula: sprice = LP / (factor − GPFT%/100). Target GPFT% must be < factor*100. --}}
                    <div class="d-inline-flex align-items-center gap-1 p-1 border rounded bg-light"
                        id="target-gpft-controls"
                        title="Target GPFT% — sets S Price = LP / (factor − Target GPFT%/100) on every selected row (back-solves so SPFT column equals the target)">
                        <label for="target-gpft-input" class="form-label mb-0 small fw-bold text-nowrap">
                            Target GPFT%:
                        </label>
                        <input type="number" id="target-gpft-input" class="form-control form-control-sm text-end"
                            placeholder="e.g. 30" step="0.1" style="width: 80px;"
                            title="Target GPFT% applied to all selected rows when you click 'Apply S Price'. Must be less than each row's take-home factor.">
                        <button id="apply-target-gpft-btn" class="btn btn-sm btn-success" type="button"
                            title="Compute & save S Price = LP / (factor − Target GPFT%/100) for every selected row">
                            <i class="fas fa-calculator"></i> Apply S Price
                        </button>
                    </div>

                    {{-- Sold dropdown (mirrors Amazon tabulator + Mercari w/Ship + every other /pricing page).
                         Backed by the `sold` field (Mercari w/o Ship L30 sold qty — shown in the
                         "L30" column on this page; OV L30 lives in the `L30` field). --}}
                    <select id="sold-filter" class="form-select form-select-sm" style="width: 120px;"
                            title="Filter by Mercari L30 sold quantity">
                        <option value="all">Sold</option>
                        <option value="sold">Sold &gt; 0</option>
                        <option value="zero">0 Sold</option>
                    </select>

                    <span class="badge bg-success fs-6 p-2" id="avg-pft-badge" style="color: #fff; font-weight: bold;">PFT: 0%</span>
                    <span class="badge bg-primary fs-6 p-2" id="avg-roi-badge" style="color: #fff; font-weight: bold;">ROI: 0%</span>
                    <span class="badge bg-secondary fs-6 p-2" id="missing-l-badge" style="color: #fff; font-weight: bold; cursor: pointer;" title="Click to filter: Price = 0 and NR/REQ = REQ">Missing L: 0</span>
                    <span class="badge bg-warning fs-6 p-2" id="revenue-badge" style="color: #000; font-weight: bold;" title="Total sales (Price × L30 sold)">Revenue: $0.00</span>

                    <button type="button" class="btn btn-sm btn-primary ms-auto" data-bs-toggle="modal"
                        data-bs-target="#priceSoldUploadModal" title="Upload Price &amp; Sold">
                        <i class="fas fa-upload"></i>
                    </button>
                </div>

                <!-- Price & Sold Upload Modal -->
                <div class="modal fade" id="priceSoldUploadModal" tabindex="-1"
                    aria-labelledby="priceSoldUploadModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form action="{{ route('mercari.woship.price-sold.import') }}" method="POST"
                                enctype="multipart/form-data">
                                @csrf
                                <div class="modal-header">
                                    <h5 class="modal-title" id="priceSoldUploadModalLabel">Upload Price &amp; Sold</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label for="woshipPriceSoldFile" class="form-label">Select file (.xlsx, .xls, .csv)</label>
                                        <input type="file" id="woshipPriceSoldFile" name="excel_file"
                                            class="form-control" accept=".xlsx,.xls,.csv" required>
                                    </div>
                                    <a href="{{ route('mercari.woship.price-sold.sample') }}"
                                        class="btn btn-sm btn-outline-secondary">
                                        <i class="fas fa-download"></i> Download Sample
                                    </a>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-upload"></i> Upload
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <input type="text" id="sku-search" class="form-control mb-3"
                    placeholder="Search by Parent or SKU..." style="width: 100%;">

                <div id="mercari-without-ship-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <div id="mercari-without-ship-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
    <script>
        let table;

        document.addEventListener('DOMContentLoaded', function() {
            table = new Tabulator("#mercari-without-ship-table", {
                ajaxURL: "{{ route('mercari.woship.tabulator.data') }}",
                ajaxResponse: function(url, params, response) {
                    const payload = response.data || response;
                    updateBadges(payload);
                    return payload;
                },
                layout: "fitDataStretch",
                pagination: true,
                paginationSize: 100,
                placeholder: "No Data Available",
                selectableRows: true,
                columns: [
                    {
                        field: "_select",
                        formatter: "rowSelection",
                        titleFormatter: "rowSelection",
                        headerSort: false,
                        hozAlign: "center",
                        width: 40,
                        frozen: true,
                        visible: false
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
                        visible: false,
                        formatter: function(cell) {
                            const val = cell.getValue();
                            return (val != null && String(val).trim() !== '') ? String(val).trim() : '—';
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
                        title: "SKU",
                        field: "sku",
                        headerFilter: "input",
                        headerFilterPlaceholder: "Search SKU...",
                        frozen: true,
                        width: 250
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
                        title: "Dil",
                        field: "Dil",
                        hozAlign: "center",
                        width: 60,
                        sorter: function(a, b, aRow, bRow) {
                            const calcDil = (row) => {
                                const inv = parseFloat(row.INV) || 0;
                                const l30 = parseFloat(row.L30) || 0;
                                return inv === 0 ? 0 : (l30 / inv) * 100;
                            };
                            return calcDil(aRow.getData()) - calcDil(bRow.getData());
                        },
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const INV = parseFloat(rowData.INV) || 0;
                            const OVL30 = parseFloat(rowData.L30) || 0;

                            if (INV === 0) return '<span style="color: #6c757d;">0%</span>';

                            const dil = (OVL30 / INV) * 100;
                            let color = '';
                            if (dil < 16.66) color = '#a00211';
                            else if (dil < 25) color = '#ffc107';
                            else if (dil < 50) color = '#28a745';
                            else color = '#e83e8c';

                            return `<span style="color: ${color}; font-weight: 600;">${Math.round(dil)}%</span>`;
                        }
                    },
                    {
                        title: "Price",
                        field: "price",
                        hozAlign: "center",
                        width: 80,
                        sorter: "number",
                        formatter: function(cell) {
                            const value = parseFloat(cell.getValue()) || 0;
                            return '$' + value.toFixed(2);
                        }
                    },
                    {
                        title: "Missing L",
                        field: "missing_l",
                        hozAlign: "center",
                        headerSort: false,
                        width: 90,
                        visible: false,
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            const price = parseFloat(row.price) || 0;
                            const nr = row.nr_req || '';
                            if (price === 0 && nr === 'REQ') {
                                return '<span style="color: #dc3545; font-weight: bold; background-color: #ffe6e6; padding: 2px 6px; border-radius: 3px;">M</span>';
                            }
                            return '';
                        }
                    },
                    {
                        title: "L30",
                        field: "sold",
                        hozAlign: "center",
                        width: 70,
                        sorter: "number",
                        formatter: function(cell) {
                            return Math.round(parseFloat(cell.getValue()) || 0);
                        }
                    },
                    {
                        title: "PFT",
                        field: "PFT",
                        hozAlign: "center",
                        width: 70,
                        sorter: "number",
                        formatter: function(cell) {
                            const value = parseFloat(cell.getValue()) || 0;
                            const color = value < 0 ? '#dc3545' : (value < 10 ? '#ffc107' : '#28a745');
                            return `<span style="color: ${color}; font-weight: 600;">${Math.round(value)}%</span>`;
                        }
                    },
                    {
                        title: "ROI",
                        field: "ROI",
                        hozAlign: "center",
                        width: 70,
                        sorter: "number",
                        formatter: function(cell) {
                            const value = parseFloat(cell.getValue()) || 0;
                            const color = value < 0 ? '#dc3545' : (value < 40 ? '#ffc107' : '#28a745');
                            return `<span style="color: ${color}; font-weight: 600;">${Math.round(value)}%</span>`;
                        }
                    },
                    {
                        title: "S Price",
                        field: "sprice",
                        hozAlign: "center",
                        width: 90,
                        editor: "number",
                        editorParams: { min: 0, step: 0.01 },
                        formatter: function(cell) {
                            const v = cell.getValue();
                            return (v === null || v === '' || isNaN(parseFloat(v))) ? '—' : '$' + parseFloat(v).toFixed(2);
                        },
                        cellEdited: function(cell) {
                            const row = cell.getRow();
                            const d = row.getData();
                            saveMercariStatus(d.sku, { sprice: cell.getValue() });

                            const sprice = parseFloat(cell.getValue()) || 0;
                            const lp = parseFloat(d.lp) || 0;
                            const factor = parseFloat(d.factor) || 1;
                            const spft = sprice > 0 ? ((sprice * factor - lp) / sprice) * 100 : 0;
                            const sroi = lp > 0 ? ((sprice * factor - lp) / lp) * 100 : 0;
                            row.update({ SPFT: Math.round(spft * 100) / 100, SROI: Math.round(sroi * 100) / 100 });
                        }
                    },
                    {
                        title: "SPFT",
                        field: "SPFT",
                        hozAlign: "center",
                        width: 70,
                        sorter: "number",
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            if (row.sprice === null || row.sprice === '' || isNaN(parseFloat(row.sprice))) return '—';
                            const value = parseFloat(cell.getValue()) || 0;
                            const color = value < 0 ? '#dc3545' : (value < 10 ? '#ffc107' : '#28a745');
                            return `<span style="color: ${color}; font-weight: 600;">${Math.round(value)}%</span>`;
                        }
                    },
                    {
                        title: "SROI",
                        field: "SROI",
                        hozAlign: "center",
                        width: 70,
                        sorter: "number",
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            if (row.sprice === null || row.sprice === '' || isNaN(parseFloat(row.sprice))) return '—';
                            const value = parseFloat(cell.getValue()) || 0;
                            const color = value < 0 ? '#dc3545' : (value < 40 ? '#ffc107' : '#28a745');
                            return `<span style="color: ${color}; font-weight: 600;">${Math.round(value)}%</span>`;
                        }
                    },
                    {
                        title: "NR/REQ",
                        field: "nr_req",
                        hozAlign: "center",
                        width: 90,
                        editor: "list",
                        editorParams: { values: { "REQ": "REQ", "NR": "NR" } },
                        formatter: function(cell) {
                            const v = cell.getValue();
                            if (!v) return '—';
                            const color = v === 'REQ' ? '#28a745' : '#dc3545';
                            return `<span title="${v}" style="display:inline-block;width:12px;height:12px;border-radius:50%;background:${color};"></span>`;
                        },
                        cellEdited: function(cell) {
                            const d = cell.getRow().getData();
                            saveMercariStatus(d.sku, { nr_req: cell.getValue() });
                        }
                    },
                    {
                        title: "B/S",
                        field: "buyer_link",
                        hozAlign: "center",
                        headerSort: false,
                        width: 90,
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            let html = '';
                            if (row.buyer_link) {
                                html += `<a href="${row.buyer_link}" target="_blank" style="color:#007bff;text-decoration:underline;margin-right:6px;">B</a>`;
                            }
                            if (row.seller_link) {
                                html += `<a href="${row.seller_link}" target="_blank" style="color:#28a745;text-decoration:underline;">S</a>`;
                            }
                            return html || '—';
                        }
                    }
                ],
            });

            // Update the adjust panel whenever row selection changes
            table.on("rowSelectionChanged", function(data, rows) {
                updateAdjustPanel();
            });

            const searchInput = document.getElementById('sku-search');
            if (searchInput) {
                // Funnel into applyAllFilters() so SKU search stacks with the Missing L
                // badge and the Sold dropdown (used to overwrite them with setFilter).
                searchInput.addEventListener('keyup', applyAllFilters);
            }

            // Sold dropdown change — same funnel, stacks with the other two filters.
            const soldFilterEl = document.getElementById('sold-filter');
            if (soldFilterEl) soldFilterEl.addEventListener('change', applyAllFilters);

            // Price % toggle — cycle Off → Decrease → Increase → Off
            const priceModeBtn = document.getElementById('price-mode-btn');
            if (priceModeBtn) {
                priceModeBtn.addEventListener('click', function() {
                    if (!decreaseModeActive && !increaseModeActive && !samePriceModeActive) {
                        decreaseModeActive = true;  increaseModeActive = false; samePriceModeActive = false;
                    } else if (decreaseModeActive) {
                        decreaseModeActive = false; increaseModeActive = true;  samePriceModeActive = false;
                    } else if (increaseModeActive) {
                        decreaseModeActive = false; increaseModeActive = false; samePriceModeActive = true;
                    } else {
                        decreaseModeActive = false; increaseModeActive = false; samePriceModeActive = false;
                    }
                    syncPriceModeUi();
                });
            }

            // Increase / Decrease / Same Price S Price — apply to selected rows
            const applyBtn = document.getElementById('apply-adjust-btn');
            if (applyBtn) {
                applyBtn.addEventListener('click', function() {
                    const mode = samePriceModeActive ? 'same' : (increaseModeActive ? 'increase' : 'decrease');
                    const type = document.getElementById('adjust-type-select').value;
                    const amount = parseFloat(document.getElementById('adjust-amount-input').value);

                    const selectedRows = table.getSelectedRows();
                    if (selectedRows.length === 0) {
                        alert('Please select at least one row.');
                        return;
                    }
                    if (isNaN(amount) || amount <= 0) {
                        alert(samePriceModeActive ? 'Please enter a valid price.' : 'Please enter a valid amount.');
                        return;
                    }

                    selectedRows.forEach(function(row) {
                        const d = row.getData();
                        const basePrice = parseFloat(d.price) || 0;

                        // Decrease / Increase need a positive base Price; Same Price applies regardless.
                        if (mode !== 'same' && basePrice <= 0) return;

                        let newPrice;
                        if (mode === 'same') {
                            newPrice = Math.max(0.01, amount);
                        } else if (type === 'percentage') {
                            const decimal = amount / 100;
                            newPrice = mode === 'decrease' ? basePrice * (1 - decimal) : basePrice * (1 + decimal);
                        } else {
                            newPrice = mode === 'decrease' ? Math.max(0.01, basePrice - amount) : basePrice + amount;
                        }
                        newPrice = Math.max(0.01, newPrice);
                        newPrice = roundToRetailPrice(newPrice);
                        // Auto-bump to .49 only when Decrease/Increase would equal the source.
                        // Same Price honors the typed value exactly.
                        if (mode !== 'same' && newPrice.toFixed(2) === basePrice.toFixed(2)) {
                            newPrice = roundToRetailPrice49(newPrice);
                        }
                        newPrice = parseFloat(newPrice.toFixed(2));

                        // recompute SPFT/SROI from new sprice
                        const lp = parseFloat(d.lp) || 0;
                        const factor = parseFloat(d.factor) || 1;
                        const spft = newPrice > 0 ? ((newPrice * factor - lp) / newPrice) * 100 : 0;
                        const sroi = lp > 0 ? ((newPrice * factor - lp) / lp) * 100 : 0;

                        row.update({
                            sprice: newPrice,
                            SPFT: Math.round(spft * 100) / 100,
                            SROI: Math.round(sroi * 100) / 100
                        });
                        saveMercariStatus(d.sku, { sprice: newPrice });
                    });
                });
            }

            /*
             * Target ROI% / Target GPFT% bulk apply (Mercari W/O Ship, margin = per-row `factor`, default 1)
             * ----------------------------------------------------------------------------------------------
             * Back-solves S Price so the resulting SROI / SPFT column matches the entered target.
             * Mercari W/O Ship's SPFT / SROI formulas (used in the inline cellEdited handler and
             * the Apply % / Same Price button above) do NOT include shipping:
             *     SPFT% = ((sprice * factor − lp) / sprice) * 100
             *     SROI% = ((sprice * factor − lp) / lp)     * 100
             *   → sprice = lp * (1 + ROI%/100) / factor
             *   → sprice = lp / (factor − GPFT%/100)
             * Selection uses Tabulator's native getSelectedRows() (matches the existing Apply %
             * flow). Each row is persisted via saveMercariStatus(sku, { sprice }) so the same
             * /mercari-without-ship-tabulator/save-status endpoint stores the new S Price.
             * Plain 2-decimal rounding — no .99 / .49 retail snapping — because snapping would
             * shift the achieved SROI / SPFT off the user-typed target.
             */
            function applyMercariWoShipTargetBackSolve(computeFn, labelPrefix) {
                const selectedRows = table.getSelectedRows();
                if (selectedRows.length === 0) {
                    alert('Please select at least one row first (turn on Price % to reveal checkboxes).');
                    return;
                }

                let updatedCount  = 0;
                let skippedNoLp   = 0;
                let skippedHigh   = 0;

                selectedRows.forEach(function (row) {
                    const d  = row.getData();
                    const lp = parseFloat(d.lp) || 0;
                    if (lp <= 0) { skippedNoLp++; return; }
                    const factor = parseFloat(d.factor) || 1;

                    const computed = computeFn(lp, factor);
                    if (computed == null) { skippedHigh++; return; }
                    const newPrice = +computed.toFixed(2);
                    if (!isFinite(newPrice) || newPrice <= 0) return;

                    const spft = newPrice > 0 ? ((newPrice * factor - lp) / newPrice) * 100 : 0;
                    const sroi = lp > 0       ? ((newPrice * factor - lp) / lp)     * 100 : 0;

                    row.update({
                        sprice: newPrice,
                        SPFT: Math.round(spft * 100) / 100,
                        SROI: Math.round(sroi * 100) / 100
                    });
                    saveMercariStatus(d.sku, { sprice: newPrice });
                    updatedCount++;
                });

                if (updatedCount === 0) {
                    if (skippedHigh > 0) {
                        alert(labelPrefix + ' too high — must be less than each row\'s take-home factor.');
                    } else {
                        alert('No selected rows have a usable LP > 0.');
                    }
                    return;
                }

                let note = '';
                if (skippedNoLp > 0) note += ' (' + skippedNoLp + ' skipped — no LP)';
                if (skippedHigh > 0) note += ' (' + skippedHigh + ' skipped — target ≥ factor)';
                if (window.toastr) {
                    toastr.success(labelPrefix + ' applied to ' + updatedCount + ' SKU(s)' + note);
                } else {
                    console.info(labelPrefix + ' applied to ' + updatedCount + ' SKU(s)' + note);
                }
            }

            const applyTargetRoiBtn = document.getElementById('apply-target-roi-btn');
            if (applyTargetRoiBtn) {
                applyTargetRoiBtn.addEventListener('click', function () {
                    const rawInput = document.getElementById('target-roi-input').value;
                    const targetRoiPct = parseFloat(String(rawInput).replace(',', '.'));

                    if (rawInput === '' || rawInput == null) { alert('Please enter a Target ROI%.'); return; }
                    if (!isFinite(targetRoiPct))             { alert('Target ROI% must be a number.'); return; }

                    const roiMultiplier = 1 + (targetRoiPct / 100);
                    applyMercariWoShipTargetBackSolve(function (lp, factor) {
                        return (lp * roiMultiplier) / factor;
                    }, 'Target ROI ' + targetRoiPct + '%');
                });
            }

            const applyTargetGpftBtn = document.getElementById('apply-target-gpft-btn');
            if (applyTargetGpftBtn) {
                applyTargetGpftBtn.addEventListener('click', function () {
                    const rawInput = document.getElementById('target-gpft-input').value;
                    const targetGpftPct = parseFloat(String(rawInput).replace(',', '.'));

                    if (rawInput === '' || rawInput == null) { alert('Please enter a Target GPFT%.'); return; }
                    if (!isFinite(targetGpftPct))            { alert('Target GPFT% must be a number.'); return; }

                    const targetFraction = targetGpftPct / 100;
                    applyMercariWoShipTargetBackSolve(function (lp, factor) {
                        const denom = factor - targetFraction;
                        if (denom <= 0) return null; // signals "target ≥ factor" skip
                        return lp / denom;
                    }, 'Target GPFT ' + targetGpftPct + '%');
                });
            }

            const targetRoiInput = document.getElementById('target-roi-input');
            if (targetRoiInput) {
                targetRoiInput.addEventListener('keypress', function (e) {
                    if (e.which === 13 || e.keyCode === 13) applyTargetRoiBtn && applyTargetRoiBtn.click();
                });
            }
            const targetGpftInput = document.getElementById('target-gpft-input');
            if (targetGpftInput) {
                targetGpftInput.addEventListener('keypress', function (e) {
                    if (e.which === 13 || e.keyCode === 13) applyTargetGpftBtn && applyTargetGpftBtn.click();
                });
            }

            // Missing L badge — click to toggle. Filter logic now lives in applyAllFilters()
            // so Missing L stacks with the SKU search and the Sold dropdown (used to
            // overwrite them via direct setFilter/clearFilter calls).
            const missingLBadge = document.getElementById('missing-l-badge');
            if (missingLBadge) {
                missingLBadge.addEventListener('click', function() {
                    missingLFilterActive = !missingLFilterActive;
                    const mCol = table.getColumn('missing_l');
                    if (missingLFilterActive) {
                        if (mCol) mCol.show();
                        missingLBadge.classList.remove('bg-secondary');
                        missingLBadge.classList.add('bg-dark');
                    } else {
                        if (mCol) mCol.hide();
                        missingLBadge.classList.remove('bg-dark');
                        missingLBadge.classList.add('bg-secondary');
                    }
                    applyAllFilters();
                });
            }
        });

        // Round to retail pricing (same as ebay-tabulator-view)
        function roundToRetailPrice(price) {
            price = parseFloat(price) || 0;
            if (price < 20.99) {
                return +price.toFixed(2);
            }
            const roundedDollar = Math.ceil(price);
            return +(roundedDollar - 0.01).toFixed(2);
        }
        // .49 endings fallback — used when .99 would match the current price
        function roundToRetailPrice49(price) {
            price = parseFloat(price) || 0;
            if (price < 20.99) {
                return +price.toFixed(2);
            }
            const roundedDollar = Math.ceil(price);
            return +(roundedDollar - 0.51).toFixed(2);
        }

        let missingLFilterActive = false;
        let decreaseModeActive = false;
        let increaseModeActive = false;
        let samePriceModeActive = false;

        // Show the adjust panel only when a mode is active AND rows are selected
        function updateAdjustPanel() {
            const container = document.getElementById('discount-input-container');
            const countEl = document.getElementById('adjust-selected-count');
            const selectedCount = (typeof table !== 'undefined' && table.getSelectedRows)
                ? table.getSelectedRows().length
                : 0;
            const modeOn = decreaseModeActive || increaseModeActive || samePriceModeActive;
            if (countEl) countEl.textContent = selectedCount ? (selectedCount + ' selected') : '';
            if (container) container.style.display = (modeOn && selectedCount > 0) ? 'flex' : 'none';
        }

        // Swap the adjust-input panel between %/$ and Same Price modes.
        function syncAdjustInputUi() {
            const wrap = document.getElementById('adjust-type-select-wrap');
            const label = document.getElementById('adjust-input-label');
            const input = document.getElementById('adjust-amount-input');
            const applyBtn = document.getElementById('apply-adjust-btn');
            if (samePriceModeActive) {
                if (wrap) wrap.style.display = 'none';
                if (label) label.classList.remove('d-none');
                if (input) {
                    input.setAttribute('placeholder', 'Enter price (e.g. 19.99)');
                    input.setAttribute('step', '0.01');
                }
                if (applyBtn) applyBtn.innerHTML = '<i class="fas fa-check"></i> Apply Same Price';
            } else {
                if (wrap) wrap.style.display = '';
                if (label) label.classList.add('d-none');
                if (input) {
                    input.setAttribute('placeholder', 'e.g. 10 or 2.50');
                    input.setAttribute('step', '0.1');
                }
                if (applyBtn) applyBtn.innerHTML = '<i class="fas fa-check"></i> Apply';
            }
        }

        function syncPriceModeUi() {
            const btn = document.getElementById('price-mode-btn');
            const selectCol = (typeof table !== 'undefined' && table.getColumn) ? table.getColumn('_select') : null;

            btn.classList.remove('btn-secondary', 'btn-danger', 'btn-success', 'btn-info');

            if (decreaseModeActive) {
                btn.classList.add('btn-danger');
                btn.innerHTML = '<i class="fas fa-arrow-down"></i> Decrease ON';
                if (selectCol) selectCol.show();
            } else if (increaseModeActive) {
                btn.classList.add('btn-success');
                btn.innerHTML = '<i class="fas fa-arrow-up"></i> Increase ON';
                if (selectCol) selectCol.show();
            } else if (samePriceModeActive) {
                btn.classList.add('btn-info');
                btn.innerHTML = '<i class="fas fa-equals"></i> Same Price ON';
                if (selectCol) selectCol.show();
            } else {
                btn.classList.add('btn-secondary');
                btn.innerHTML = '<i class="fas fa-exchange-alt"></i> Price %';
                if (selectCol) selectCol.hide();
                if (typeof table !== 'undefined' && table.deselectRow) table.deselectRow();
            }
            syncAdjustInputUi();
            updateAdjustPanel();
        }

        function missingLFilter(row) {
            const price = parseFloat(row.price) || 0;
            const nr = row.nr_req || '';
            return price === 0 && nr === 'REQ';
        }

        // Unified filter — combines SKU/Parent search, the Missing L badge toggle, and
        // the Sold dropdown into one Tabulator filter so they STACK instead of
        // overwriting each other (matches the Mercari w/Ship pattern). All three filter
        // entry points (search keyup, badge click, Sold dropdown change) call this.
        function applyAllFilters() {
            if (typeof table === 'undefined' || !table || !table.setFilter) return;

            const searchEl = document.getElementById('sku-search');
            const skuSearch = (searchEl ? (searchEl.value || '') : '').trim().toLowerCase();

            const soldEl = document.getElementById('sold-filter');
            const soldFilter = soldEl ? soldEl.value : 'all';

            table.setFilter(function(row) {
                // SKU / Parent search
                if (skuSearch) {
                    const sku = String(row.sku || '').toLowerCase();
                    const parent = String(row.Parent || '').toLowerCase();
                    if (sku.indexOf(skuSearch) === -1 && parent.indexOf(skuSearch) === -1) return false;
                }

                // Missing L (price = 0 and NR/REQ = REQ) — only when the badge is toggled on
                if (missingLFilterActive && !missingLFilter(row)) return false;

                // Sold filter (Mercari w/o Ship L30 sold qty — `sold` field).
                if (soldFilter && soldFilter !== 'all') {
                    const soldQty = parseFloat(row.sold) || 0;
                    if (soldFilter === 'sold' && !(soldQty > 0))   return false;
                    if (soldFilter === 'zero' && !(soldQty === 0)) return false;
                }

                return true;
            });
        }

        function updateBadges(data) {
            data = data || [];
            let pftSum = 0, roiSum = 0, count = 0, missingL = 0, revenue = 0;
            data.forEach(function(row) {
                // Missing L: price = 0 and NR/REQ = REQ
                const nr = row.nr_req || '';
                const price = parseFloat(row.price) || 0;
                if (price === 0 && nr === 'REQ') {
                    missingL++;
                }

                // Revenue: price × L30 sold units
                revenue += price * (parseFloat(row.sold) || 0);

                if (price <= 0) return; // only rows with a price contribute
                pftSum += parseFloat(row.PFT) || 0;
                roiSum += parseFloat(row.ROI) || 0;
                count++;
            });
            const avgPft = count > 0 ? pftSum / count : 0;
            const avgRoi = count > 0 ? roiSum / count : 0;
            document.getElementById('avg-pft-badge').textContent = 'PFT: ' + Math.round(avgPft) + '%';
            document.getElementById('avg-roi-badge').textContent = 'ROI: ' + Math.round(avgRoi) + '%';
            document.getElementById('missing-l-badge').textContent = 'Missing L: ' + missingL;
            document.getElementById('revenue-badge').textContent = 'Revenue: $' + revenue.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function saveMercariStatus(sku, payload) {
            const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            fetch("{{ route('mercari.woship.tabulator.save-status') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token
                },
                body: JSON.stringify(Object.assign({ sku: sku }, payload))
            }).catch(function(err) {
                console.error('Failed to save status', err);
            });
        }
    </script>
@endsection
