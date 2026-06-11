@extends('layouts.vertical', ['title' => 'Newegg Pricing', 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }
        .editable-cell {
            cursor: pointer;
        }
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Newegg Pricing',
        'sub_title' => 'Newegg Pricing & Inventory',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <h4>Newegg Pricing & Inventory</h4>
                <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button"
                            id="columnVisibilityDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa fa-eye"></i> Columns
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="columnVisibilityDropdown" id="column-dropdown-menu"
                            style="max-height: 400px; overflow-y: auto;">
                        </ul>
                    </div>
                    <button id="show-all-columns-btn" class="btn btn-sm btn-outline-secondary">
                        <i class="fa fa-eye"></i> Show All
                    </button>
                    <button type="button" class="btn btn-sm btn-success" id="export-btn">
                        <i class="fa fa-file-excel"></i> Export
                    </button>
                </div>

                <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                    <h6 class="mb-3">Summary Statistics</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-primary fs-6 p-2" id="total-skus-badge" style="color: white; font-weight: bold;">Total SKUs: 0</span>
                        <span class="badge bg-success fs-6 p-2" id="with-price-badge" style="color: white; font-weight: bold;">With Price: 0</span>
                        <span class="badge fs-6 p-2" id="total-inv-badge" style="background-color: #17a2b8; color: white; font-weight: bold;">Total INV: 0</span>
                        <span class="badge bg-warning fs-6 p-2" id="total-ovl30-badge" style="color: black; font-weight: bold;">Total OVL30: 0</span>
                        <span class="badge bg-dark fs-6 p-2" id="total-l30-badge" style="color: white; font-weight: bold;">Total L30: 0</span>
                        <span class="badge fs-6 p-2" id="avg-price-badge" style="background-color: purple; color: white; font-weight: bold;">Avg Price: $0.00</span>
                    </div>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div id="newegg-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <div class="p-2 bg-light border-bottom">
                        <input type="text" id="sku-search" class="form-control form-control-sm"
                            placeholder="Search by SKU or Title...">
                    </div>
                    <div id="newegg-pricing-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Buyer / Seller link modal -->
    <div class="modal fade" id="bsLinkModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Buyer / Seller Links</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="bs-sku">
                    <div class="mb-2"><small class="text-muted">SKU: <span id="bs-sku-label" class="fw-bold"></span></small></div>
                    <div class="mb-3">
                        <label class="form-label">Buyer Link</label>
                        <input type="url" class="form-control" id="bs-buyer-link" placeholder="https://...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Seller Link</label>
                        <input type="url" class="form-control" id="bs-seller-link" placeholder="https://...">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="bs-save-btn">Save</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
    <script>
        let table = null;

        function moneyCol(title, field, visible = true) {
            return {
                title, field, visible,
                hozAlign: "right", sorter: "number",
                formatter: "money",
                formatterParams: { decimal: ".", thousand: ",", symbol: "$", precision: 2 }
            };
        }

        // DIL% = sell-through (OVL30 / INV). Same color buckets as other marketplace pages.
        function dilFormatter(cell) {
            const v = cell.getValue();
            if (v === null || v === undefined) return '<span style="color:#a00211;font-weight:bold;">0%</span>';
            const n = parseFloat(v);
            let color = '#a00211';
            if (n < 16.7) color = '#a00211';
            else if (n < 25) color = '#ffc107';
            else if (n < 50) color = '#28a745';
            else color = '#e83e8c';
            return `<span style="color:${color}; font-weight:bold;">${n.toFixed(0)}%</span>`;
        }

        $(document).ready(function() {
            $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } });

            table = new Tabulator("#newegg-pricing-table", {
                ajaxURL: "{{ route('newegg.pricing.data') }}",
                ajaxSorting: false,
                layout: "fitData",
                responsiveLayout: false,
                pagination: true,
                paginationSize: 100,
                paginationSizeSelector: [10, 25, 50, 100, 200],
                paginationCounter: "rows",
                placeholder: "No Data Available",
                ajaxResponse: function(url, params, response) {
                    return Array.isArray(response) ? response : (response.data || []);
                },
                initialSort: [{ column: "l30", dir: "desc" }],
                columns: [
                    { title: "SKU", field: "sku", frozen: true, headerFilter: "input", headerFilterPlaceholder: "Search SKU...", cssClass: "text-primary fw-bold" },
                    { title: "Title", field: "title", visible: false, tooltip: true },
                    { title: "INV", field: "inv", hozAlign: "center", sorter: "number" },
                    { title: "OVL30", field: "ovl30", hozAlign: "center", sorter: "number" },
                    { title: "DIL %", field: "dil", hozAlign: "center", sorter: "number", formatter: dilFormatter },
                    { title: "N INV", field: "available_quantity", hozAlign: "center", sorter: "number" },
                    moneyCol("Price", "price"),
                    { title: "L30", field: "l30", hozAlign: "center", sorter: "number",
                        formatter: function(cell) {
                            const v = parseInt(cell.getValue()) || 0;
                            return v > 0 ? `<span style="color:#28a745;font-weight:bold;">${v}</span>` : '0';
                        }
                    },
                    moneyCol("LP", "lp", false),
                    moneyCol("Ship", "ship", false),
                    {
                        title: "Pft %", field: "pft_pct", hozAlign: "right", sorter: "number",
                        formatter: function(cell) {
                            const v = cell.getValue();
                            if (v === null || v === undefined) return '';
                            const n = parseFloat(v) || 0;
                            const color = n >= 0 ? '#28a745' : '#dc3545';
                            return `<span style="color:${color};font-weight:bold;">${n.toFixed(1)}%</span>`;
                        }
                    },
                    {
                        title: "ROI %", field: "roi", hozAlign: "right", sorter: "number",
                        formatter: function(cell) {
                            const v = cell.getValue();
                            if (v === null || v === undefined) return '';
                            const n = parseFloat(v) || 0;
                            let color = '#6c757d';
                            if (n < 50) color = '#dc3545';
                            else if (n < 75) color = '#ffc107';
                            else if (n <= 125) color = '#28a745';
                            else color = '#e83e8c';
                            return `<span style="color:${color};font-weight:bold;">${n.toFixed(0)}%</span>`;
                        }
                    },
                    {
                        title: "SPrice", field: "sprice", hozAlign: "right", sorter: "number",
                        editor: "number", editorParams: { min: 0, step: 0.01 },
                        cssClass: "editable-cell",
                        formatter: function(cell) {
                            const v = cell.getValue();
                            if (v === null || v === undefined || v === '') return '<span style="color:#bbb;">—</span>';
                            return '$' + (parseFloat(v) || 0).toFixed(2);
                        }
                    },
                    {
                        title: "SPft %", field: "spft", hozAlign: "right", sorter: "number",
                        formatter: function(cell) {
                            const v = cell.getValue();
                            if (v === null || v === undefined || v === '') return '';
                            const n = parseFloat(v) || 0;
                            const color = n >= 0 ? '#28a745' : '#dc3545';
                            return `<span style="color:${color};font-weight:bold;">${n.toFixed(1)}%</span>`;
                        }
                    },
                    {
                        title: "SROI %", field: "sroi", hozAlign: "right", sorter: "number",
                        formatter: function(cell) {
                            const v = cell.getValue();
                            if (v === null || v === undefined || v === '') return '';
                            const n = parseFloat(v) || 0;
                            let color = '#6c757d';
                            if (n < 50) color = '#dc3545';
                            else if (n < 75) color = '#ffc107';
                            else if (n <= 125) color = '#28a745';
                            else color = '#e83e8c';
                            return `<span style="color:${color};font-weight:bold;">${n.toFixed(0)}%</span>`;
                        }
                    },
                    {
                        title: "NR/REQ", field: "nr", hozAlign: "center",
                        headerSort: false, cssClass: "editable-cell",
                        formatter: function(cell) {
                            const v = cell.getValue() || 'REQ';
                            const color = v === 'NR' ? '#dc3545' : '#28a745';
                            return `<span title="Click to toggle" style="display:inline-block;width:14px;height:14px;border-radius:50%;background:${color};"></span>`;
                        },
                        cellClick: function(e, cell) {
                            const row = cell.getRow();
                            const data = row.getData();
                            const next = (data.nr === 'NR') ? 'REQ' : 'NR';
                            row.update({ nr: next });
                            fetch("{{ route('newegg.pricing.save.nr') }}", {
                                method: "POST",
                                headers: { "Content-Type": "application/json", "X-CSRF-TOKEN": "{{ csrf_token() }}" },
                                body: JSON.stringify({ sku: data.sku, nr: next })
                            })
                            .then(r => r.json())
                            .then(res => { if (!res.success) alert(res.error || "Failed to save NR"); })
                            .catch(() => alert("Failed to save NR"));
                        }
                    },
                    {
                        title: "B/S", field: "bs", hozAlign: "center", headerSort: false,
                        cssClass: "editable-cell",
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            const parts = [];
                            if (d.buyer_link) {
                                parts.push(`<a href="${d.buyer_link}" target="_blank" title="Buyer link" style="font-weight:bold;color:#0d6efd;text-decoration:none;">B</a>`);
                            }
                            if (d.seller_link) {
                                parts.push(`<a href="${d.seller_link}" target="_blank" title="Seller link" style="font-weight:bold;color:#198754;text-decoration:none;">S</a>`);
                            }
                            return parts.join(' / ');
                        },
                        cellClick: function(e, cell) {
                            if (e.target && e.target.tagName === 'A') return; // let links open
                            openBsModal(cell.getRow().getData());
                        }
                    },
                    { title: "Currency", field: "currency", visible: false },
                    {
                        title: "Status", field: "status", hozAlign: "center",
                        formatter: function(cell) {
                            const v = cell.getValue() || '';
                            if (!v) return '';
                            const isActive = v === 'Active';
                            const color = isActive ? '#28a745' : '#dc3545';
                            const letter = isActive ? 'A' : 'I';
                            return `<span title="${v}" style="display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;border-radius:50%;background:${color};color:#fff;font-weight:bold;font-size:12px;">${letter}</span>`;
                        }
                    }
                ]
            });

            // Save SPRICE / NR on edit.
            table.on("cellEdited", function(cell) {
                const field = cell.getField();
                const row = cell.getRow();
                const data = row.getData();

                if (field === "sprice") {
                    fetch("{{ route('newegg.pricing.save.sprice') }}", {
                        method: "POST",
                        headers: { "Content-Type": "application/json", "X-CSRF-TOKEN": "{{ csrf_token() }}" },
                        body: JSON.stringify({ sku: data.sku, sprice: cell.getValue() })
                    })
                    .then(r => r.json())
                    .then(res => {
                        if (res.success) {
                            row.update({ spft: res.spft, sroi: res.sroi, sprice: res.sprice });
                        } else {
                            alert(res.error || "Failed to save SPrice");
                        }
                    })
                    .catch(() => alert("Failed to save SPrice"));
                }
            });

            // Open Buyer/Seller link modal by clicking the B/S cell.
            let bsModal = null;
            function openBsModal(d) {
                d = d || {};
                document.getElementById('bs-sku').value = d.sku || '';
                document.getElementById('bs-sku-label').textContent = d.sku || '';
                document.getElementById('bs-buyer-link').value = d.buyer_link || '';
                document.getElementById('bs-seller-link').value = d.seller_link || '';
                if (!bsModal) bsModal = new bootstrap.Modal(document.getElementById('bsLinkModal'));
                bsModal.show();
            }

            document.getElementById('bs-save-btn').addEventListener('click', function() {
                const sku = document.getElementById('bs-sku').value;
                const buyer = document.getElementById('bs-buyer-link').value.trim();
                const seller = document.getElementById('bs-seller-link').value.trim();
                fetch("{{ route('newegg.pricing.save.links') }}", {
                    method: "POST",
                    headers: { "Content-Type": "application/json", "X-CSRF-TOKEN": "{{ csrf_token() }}" },
                    body: JSON.stringify({ sku: sku, buyer_link: buyer, seller_link: seller })
                })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        const rows = table.searchRows('sku', '=', sku);
                        if (rows.length) {
                            rows[0].update({ buyer_link: res.buyer_link, seller_link: res.seller_link })
                                .then(() => rows[0].reformat());
                        }
                        if (bsModal) bsModal.hide();
                    } else {
                        alert(res.error || "Failed to save links");
                    }
                })
                .catch(() => alert("Failed to save links"));
            });

            $('#sku-search').on('keyup', function() {
                const value = ($(this).val() || '').trim().toLowerCase();
                if (value) {
                    table.setFilter(function(row) {
                        const sku = String(row.sku || '').toLowerCase();
                        const title = String(row.title || '').toLowerCase();
                        return sku.indexOf(value) !== -1 || title.indexOf(value) !== -1;
                    });
                } else {
                    table.clearFilter();
                }
                setTimeout(updateSummary, 100);
            });

            function updateSummary() {
                const data = table.getData("active");
                let totalSkus = 0, withPrice = 0, totalInv = 0, totalOvl30 = 0, totalL30 = 0;
                let totalWeightedPrice = 0, priceCount = 0;

                data.forEach(row => {
                    if (!row.sku) return;
                    totalSkus++;
                    totalInv += parseInt(row.inv) || 0;
                    totalOvl30 += parseInt(row.ovl30) || 0;
                    totalL30 += parseInt(row.l30) || 0;
                    const price = parseFloat(row.price);
                    if (!isNaN(price) && price > 0) {
                        withPrice++;
                        totalWeightedPrice += price;
                        priceCount++;
                    }
                });

                const avgPrice = priceCount > 0 ? totalWeightedPrice / priceCount : 0;

                $('#total-skus-badge').text('Total SKUs: ' + totalSkus.toLocaleString());
                $('#with-price-badge').text('With Price: ' + withPrice.toLocaleString());
                $('#total-inv-badge').text('Total INV: ' + totalInv.toLocaleString());
                $('#total-ovl30-badge').text('Total OVL30: ' + totalOvl30.toLocaleString());
                $('#total-l30-badge').text('Total L30: ' + totalL30.toLocaleString());
                $('#avg-price-badge').text('Avg Price: $' + avgPrice.toFixed(2));
            }

            const COL_URL = '/newegg-pricing-column-visibility';

            function buildColumnDropdown() {
                const menu = document.getElementById("column-dropdown-menu");
                menu.innerHTML = '';
                fetch(COL_URL, { headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' } })
                    .then(r => r.json())
                    .then(savedVisibility => {
                        table.getColumns().forEach(col => {
                            const def = col.getDefinition();
                            if (!def.field) return;
                            const li = document.createElement("li");
                            const label = document.createElement("label");
                            label.style.cssText = "display:block;padding:5px 10px;cursor:pointer;";
                            const checkbox = document.createElement("input");
                            checkbox.type = "checkbox";
                            checkbox.value = def.field;
                            checkbox.checked = savedVisibility[def.field] !== false;
                            checkbox.style.marginRight = "8px";
                            label.appendChild(checkbox);
                            label.appendChild(document.createTextNode(def.title));
                            li.appendChild(label);
                            menu.appendChild(li);
                        });
                    });
            }

            function saveColumnVisibilityToServer() {
                const visibility = {};
                table.getColumns().forEach(col => {
                    const def = col.getDefinition();
                    if (def.field) visibility[def.field] = col.isVisible();
                });
                fetch(COL_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({ visibility })
                });
            }

            function applyColumnVisibilityFromServer() {
                fetch(COL_URL, { headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' } })
                    .then(r => r.json())
                    .then(savedVisibility => {
                        table.getColumns().forEach(col => {
                            const def = col.getDefinition();
                            if (def.field && savedVisibility[def.field] === false) col.hide();
                        });
                    });
            }

            table.on('tableBuilt', function() {
                applyColumnVisibilityFromServer();
                buildColumnDropdown();
            });
            table.on('dataLoaded', updateSummary);
            table.on('dataProcessed', updateSummary);
            table.on('dataFiltered', updateSummary);

            document.getElementById("column-dropdown-menu").addEventListener("change", function(e) {
                if (e.target.type === 'checkbox') {
                    const col = table.getColumn(e.target.value);
                    if (e.target.checked) col.show(); else col.hide();
                    saveColumnVisibilityToServer();
                }
            });

            document.getElementById("show-all-columns-btn").addEventListener("click", function() {
                table.getColumns().forEach(col => col.show());
                buildColumnDropdown();
                saveColumnVisibilityToServer();
            });

            $('#export-btn').on('click', function() {
                table.download("csv", "newegg_pricing.csv");
            });
        });
    </script>
@endsection
