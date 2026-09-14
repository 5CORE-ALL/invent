<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Barcode Image</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { margin: 0; font-family: Inter, "Segoe UI", sans-serif; background: #f4f6fb; color: #14213d; }
        .bi-top {
            background: #fff;
            border-bottom: 1px solid #d7e0ef;
            padding: 14px 7%;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .bi-top strong { font-size: 16px; }
        .bi-wrap { padding: 22px 7% 40px; }
        .bi-card { background: #fff; border: 1px solid #d7e0ef; border-radius: 12px; padding: 18px 18px 12px; }
        #masters-barcode-table.tabulator { width: 100%; }
        #masters-barcode-table .tabulator-header {
            background: linear-gradient(180deg, #eef3fb 0%, #e3ebf8 100%);
            border-bottom: 1px solid #c5d4ea;
        }
        #masters-barcode-table .tabulator-header .tabulator-col-content .tabulator-col-title {
            color: #1a3d7c;
            font-weight: 700;
            font-size: 0.9rem;
            text-align: center;
        }
        #masters-barcode-table .tabulator-row .tabulator-cell { padding: 10px 8px; overflow: visible; }
        .mb-cell { display: flex; align-items: center; justify-content: center; width: 100%; min-height: 88px; }
        .mb-product-img {
            width: 56px; height: 56px; object-fit: cover; border-radius: 6px;
            border: 1px solid #e5e7eb; background: #f8fafc; display: block;
        }
        .mb-barcode-hover { position: relative; display: inline-flex; cursor: pointer; }
        .mb-barcode-square {
            width: 130px; min-height: 118px; border: 1px solid #d1d5db; border-radius: 8px;
            background: #fff; display: inline-flex; flex-direction: column; align-items: center;
            justify-content: center; padding: 8px 6px; gap: 4px;
        }
        .mb-barcode-square img, .mb-barcode-square svg { max-width: 110px; max-height: 48px; object-fit: contain; }
        .mb-barcode-sku-top, .mb-barcode-upc-bottom {
            font-size: 10px; font-weight: 700; text-align: center; width: 100%; word-break: break-word;
        }
        .mb-barcode-sku-top { color: #1a3d7c; }
        .mb-barcode-upc-bottom { color: #374151; font-weight: 600; }
        .mb-barcode-empty {
            width: 130px; min-height: 118px; border: 1px dashed #cbd5e1; border-radius: 8px;
            display: inline-flex; align-items: center; justify-content: center; color: #94a3b8; font-size: 11px; background: #f8fafc;
        }
        #mbBarcodeModalSku { font-size: 28px; font-weight: 700; color: #1a3d7c; text-align: center; }
        #mbBarcodeModalMedia {
            min-height: 140px; display: flex; align-items: center; justify-content: center;
            border: 1px solid #e5e7eb; border-radius: 10px; padding: 16px; background: #fff;
        }
        #mbBarcodeModalMedia img, #mbBarcodeModalMedia svg { max-width: 100%; max-height: 180px; }
        #mbBarcodeModalCode { font-weight: 600; color: #1a3d7c; word-break: break-all; }
        @media (max-width: 640px) {
            .bi-top, .bi-wrap { padding-left: 16px; padding-right: 16px; }
        }
    </style>
</head>
<body>
    <header class="bi-top">
        <strong>Barcode Image</strong>
        <span class="text-muted small">View only · Search and filter SKUs</span>
    </header>

    <main class="bi-wrap">
        <div class="bi-card">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <div>
                    <h4 class="mb-0">Masters Barcode</h4>
                    <small class="text-muted">Read-only. Image, SKU, parent, and barcode.</small>
                </div>
                <span class="badge text-bg-primary" id="mbCount">0</span>
            </div>
            <div class="row g-2 align-items-end mb-3">
                <div class="col-md-3">
                    <label for="mbParentSearch" class="form-label fw-semibold mb-1">Search Parent</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" id="mbParentSearch" class="form-control" placeholder="Type Parent…">
                    </div>
                </div>
                <div class="col-md-3">
                    <label for="mbSkuSearch" class="form-label fw-semibold mb-1">Search SKU</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" id="mbSkuSearch" class="form-control" placeholder="Type SKU…">
                    </div>
                </div>
                <div class="col-md-3">
                    <label for="mbBarcodeSearch" class="form-label fw-semibold mb-1">Search Barcode / UPC</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-barcode"></i></span>
                        <input type="text" id="mbBarcodeSearch" class="form-control" placeholder="Type barcode…">
                    </div>
                </div>
                <div class="col-md-3">
                    <label for="mbImageFilter" class="form-label fw-semibold mb-1">SKU Image</label>
                    <select id="mbImageFilter" class="form-select">
                        <option value="all">All</option>
                        <option value="missing">Missing image</option>
                        <option value="has">Has image</option>
                    </select>
                </div>
            </div>
            <div id="masters-barcode-table"></div>
        </div>
    </main>

    <div class="modal fade" id="mbBarcodeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title mb-0 text-muted">Barcode</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center d-flex flex-column align-items-center gap-3">
                    <div id="mbBarcodeModalSku"></div>
                    <div id="mbBarcodeModalMedia" class="w-100"></div>
                    <div id="mbBarcodeModalCode"></div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const dataUrl = @json(route('barcode.image.data'));
        let table = null;

        function escapeHtml(str) {
            return String(str || '')
                .replace(/&/g, '&amp;')
                .replace(/"/g, '&quot;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
        }

        const barcodeModalEl = document.getElementById('mbBarcodeModal');
        const barcodeModal = bootstrap.Modal.getOrCreateInstance(barcodeModalEl);

        function renderBarcodeCell(data) {
            const img = (data.barcode_image || '').trim();
            const code = (data.barcode || data.upc || '').trim();
            const sku = (data.sku || '').trim();
            const safeSku = escapeHtml(sku);
            const safeCode = escapeHtml(code);
            if (!img && !code) {
                return '<div class="mb-barcode-empty">No barcode</div>';
            }
            const media = img
                ? `<img src="${escapeHtml(img)}" alt="Barcode" loading="lazy">`
                : `<svg class="mb-barcode-svg" data-barcode="${safeCode}"></svg>`;
            return `
                <div class="mb-barcode-hover" role="button" tabindex="0"
                     data-sku="${safeSku}" data-code="${safeCode}" data-img="${escapeHtml(img)}">
                    <div class="mb-barcode-square">
                        <div class="mb-barcode-sku-top">${safeSku || '—'}</div>
                        ${media}
                        <div class="mb-barcode-upc-bottom">${safeCode || '—'}</div>
                    </div>
                </div>
            `;
        }

        function paintBarcodeSvg(svg, code, large) {
            if (!svg || !code) return;
            const digits = code.replace(/\D/g, '');
            const format = (digits.length === 11 || digits.length === 12) ? 'UPC' : 'CODE128';
            const opts = {
                format: format,
                displayValue: false,
                margin: 0,
                width: large ? 2.6 : 1.2,
                height: large ? 140 : 48,
                background: '#ffffff',
                lineColor: '#111827',
            };
            try {
                JsBarcode(svg, format === 'UPC' ? digits : code, opts);
            } catch (e) {
                try {
                    JsBarcode(svg, code, Object.assign({}, opts, { format: 'CODE128' }));
                } catch (e2) {
                    svg.outerHTML = '<span class="text-danger small">Invalid</span>';
                }
            }
        }

        function paintBarcodes(root) {
            (root || document).querySelectorAll('.mb-barcode-svg').forEach((svg) => {
                paintBarcodeSvg(svg, (svg.getAttribute('data-barcode') || '').trim(), false);
            });
        }

        function showBarcodeModal(el) {
            const sku = el.getAttribute('data-sku') || '';
            const code = el.getAttribute('data-code') || '';
            const img = el.getAttribute('data-img') || '';
            document.getElementById('mbBarcodeModalSku').textContent = sku || '—';
            document.getElementById('mbBarcodeModalCode').textContent = code || '—';
            const media = document.getElementById('mbBarcodeModalMedia');
            if (img) {
                media.innerHTML = `<img src="${img}" alt="Barcode">`;
            } else if (code) {
                media.innerHTML = `<svg id="mbBarcodeModalSvg"></svg>`;
                paintBarcodeSvg(document.getElementById('mbBarcodeModalSvg'), code, true);
            } else {
                media.innerHTML = '<span class="text-muted">No barcode</span>';
            }
            barcodeModal.show();
        }

        function hasSkuImage(data) {
            return !!(data.image && String(data.image).trim());
        }

        function updateCount() {
            if (!table) return;
            document.getElementById('mbCount').textContent = String(table.getDataCount('active'));
        }

        function applyFilters() {
            if (!table) return;
            const skuQ = (document.getElementById('mbSkuSearch').value || '').trim().toLowerCase();
            const parentQ = (document.getElementById('mbParentSearch').value || '').trim().toLowerCase();
            const barcodeQ = (document.getElementById('mbBarcodeSearch').value || '').trim().toLowerCase();
            const imageFilter = document.getElementById('mbImageFilter').value || 'all';

            table.setFilter(function (data) {
                if (skuQ && !String(data.sku || '').toLowerCase().includes(skuQ)) return false;
                if (parentQ && !String(data.parent || '').toLowerCase().includes(parentQ)) return false;
                if (barcodeQ) {
                    const hay = (String(data.barcode || '') + ' ' + String(data.upc || '')).toLowerCase();
                    if (!hay.includes(barcodeQ)) return false;
                }
                if (imageFilter === 'missing' && hasSkuImage(data)) return false;
                if (imageFilter === 'has' && !hasSkuImage(data)) return false;
                return true;
            });
            updateCount();
        }

        table = new Tabulator('#masters-barcode-table', {
            ajaxURL: dataUrl,
            ajaxResponse: function (url, params, response) {
                return response?.data || [];
            },
            layout: 'fitColumns',
            height: '70vh',
            rowHeight: 140,
            pagination: true,
            paginationSize: 25,
            paginationSizeSelector: [25, 50, 100, 200],
            placeholder: 'No products found',
            columns: [
                {
                    title: 'Image',
                    field: 'image',
                    hozAlign: 'center',
                    headerHozAlign: 'center',
                    width: 110,
                    vertAlign: 'middle',
                    formatter: function (cell) {
                        const src = cell.getValue();
                        if (!src) return '<div class="mb-cell"><span class="text-muted">—</span></div>';
                        return `<div class="mb-cell"><img src="${src}" class="mb-product-img" alt="SKU" loading="lazy"></div>`;
                    },
                },
                {
                    title: 'Parent',
                    field: 'parent',
                    minWidth: 140,
                    widthGrow: 1,
                    hozAlign: 'center',
                    headerHozAlign: 'center',
                    vertAlign: 'middle',
                    formatter: function (cell) {
                        const val = (cell.getValue() || '').trim();
                        return val
                            ? `<div class="mb-cell"><span>${escapeHtml(val)}</span></div>`
                            : '<div class="mb-cell"><span class="text-muted">—</span></div>';
                    },
                },
                {
                    title: 'SKU',
                    field: 'sku',
                    minWidth: 220,
                    widthGrow: 3,
                    hozAlign: 'center',
                    headerHozAlign: 'center',
                    vertAlign: 'middle',
                    formatter: function (cell) {
                        return `<div class="mb-cell"><span class="fw-semibold">${escapeHtml(cell.getValue() || '')}</span></div>`;
                    },
                },
                {
                    title: 'Barcode',
                    field: 'barcode',
                    hozAlign: 'center',
                    headerHozAlign: 'center',
                    width: 160,
                    vertAlign: 'middle',
                    formatter: function (cell) {
                        return `<div class="mb-cell">${renderBarcodeCell(cell.getRow().getData())}</div>`;
                    },
                },
            ],
        });

        table.on('dataLoaded', updateCount);
        table.on('dataFiltered', updateCount);
        table.on('renderComplete', function () {
            const root = document.getElementById('masters-barcode-table');
            paintBarcodes(root);
            root.querySelectorAll('.mb-barcode-hover').forEach((el) => {
                if (el.dataset.zoomBound) return;
                el.dataset.zoomBound = '1';
                el.addEventListener('click', (e) => {
                    e.preventDefault();
                    showBarcodeModal(el);
                });
            });
        });

        let searchTimer = null;
        function onSearchInput() {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(applyFilters, 200);
        }
        document.getElementById('mbSkuSearch').addEventListener('input', onSearchInput);
        document.getElementById('mbParentSearch').addEventListener('input', onSearchInput);
        document.getElementById('mbBarcodeSearch').addEventListener('input', onSearchInput);
        document.getElementById('mbImageFilter').addEventListener('change', applyFilters);
    });
    </script>
</body>
</html>
