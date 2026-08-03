@extends('layouts.vertical', ['title' => 'Masters Barcode', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
<link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
<style>
    #masters-barcode-table.tabulator {
        width: 100%;
    }
    #masters-barcode-table .tabulator-header {
        background: linear-gradient(180deg, #eef3fb 0%, #e3ebf8 100%);
        border-bottom: 1px solid #c5d4ea;
    }
    #masters-barcode-table .tabulator-header .tabulator-col {
        background: transparent;
        border-right: 1px solid #d7e0ef;
    }
    #masters-barcode-table .tabulator-header .tabulator-col-content .tabulator-col-title {
        color: #1a3d7c;
        font-weight: 700;
        font-size: 0.9rem;
        text-align: center;
    }
    #masters-barcode-table .tabulator-row .tabulator-cell {
        padding: 10px 8px;
        overflow: visible;
    }
    #masters-barcode-table .tabulator-tableholder {
        overflow: auto !important;
    }
    #masters-barcode-table .mb-cell {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        min-height: 88px;
    }
    #masters-barcode-table .mb-cell--sku {
        justify-content: flex-start;
        padding-left: 8px;
    }
    .mb-product-img {
        width: 56px;
        height: 56px;
        object-fit: cover;
        border-radius: 6px;
        border: 1px solid #e5e7eb;
        background: #f8fafc;
        display: block;
    }
    .mb-barcode-hover {
        position: relative;
        display: inline-flex;
        cursor: pointer;
    }
    .mb-barcode-square {
        width: 130px;
        min-height: 118px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        background: #fff;
        display: inline-flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 8px 6px;
        box-sizing: border-box;
        vertical-align: middle;
        gap: 4px;
    }
    .mb-barcode-square svg,
    .mb-barcode-square canvas,
    .mb-barcode-square img {
        max-width: 110px;
        max-height: 48px;
        object-fit: contain;
        display: block;
        flex-shrink: 0;
    }
    .mb-barcode-sku-top {
        font-size: 10px;
        font-weight: 700;
        color: #1a3d7c;
        line-height: 1.2;
        text-align: center;
        width: 100%;
        word-break: break-word;
        white-space: normal;
        max-height: 2.4em;
        overflow: hidden;
    }
    .mb-barcode-upc-bottom {
        font-size: 10px;
        font-weight: 600;
        color: #374151;
        line-height: 1.2;
        text-align: center;
        width: 100%;
        word-break: break-all;
    }
    .mb-barcode-empty {
        width: 130px;
        min-height: 118px;
        border: 1px dashed #cbd5e1;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #94a3b8;
        font-size: 11px;
        background: #f8fafc;
        vertical-align: middle;
    }
    .mb-barcode-hover:hover .mb-barcode-square {
        box-shadow: 0 0 0 2px #4dd0e1;
    }
    #mbBarcodeModal.modal .modal-dialog {
        max-width: 440px;
        margin: 1.75rem auto;
    }
    #mbBarcodeModal .modal-content {
        border-radius: 14px;
        border: 1px solid #c5d4ea;
        box-shadow: 0 16px 40px rgba(15, 23, 42, 0.28);
        overflow: hidden;
    }
    #mbBarcodeModal .modal-header {
        border-bottom: 1px solid #e8eef8;
        padding: 12px 16px;
    }
    #mbBarcodeModal .modal-body {
        padding: 28px 28px 32px;
        text-align: center;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 16px;
    }
    #mbBarcodeModalSku {
        font-size: 36px; /* 2x of previous 18px when space allows */
        font-weight: 700;
        color: #1a3d7c;
        line-height: 1.25;
        text-align: center;
        width: 100%;
        word-break: break-word;
        white-space: normal;
    }
    #mbBarcodeModalMedia {
        width: 100%;
        min-height: 140px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        padding: 16px;
    }
    #mbBarcodeModalMedia img,
    #mbBarcodeModalMedia svg,
    #mbBarcodeModalMedia canvas {
        max-width: 100%;
        max-height: 180px;
        object-fit: contain;
        display: block;
        margin: 0 auto;
    }
    #mbBarcodeModalCode {
        font-size: 16px;
        font-weight: 600;
        color: #1a3d7c;
        word-break: break-all;
        text-align: center;
        width: 100%;
        letter-spacing: 0.04em;
    }
</style>
@endsection

@section('content')
@include('layouts.shared.page-title', ['page_title' => 'Masters Barcode', 'sub_title' => 'Product Masters'])

<div class="row">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <div>
                        <h4 class="mb-0">Masters Barcode</h4>
                        <small class="text-muted">Image, SKU, and barcode from UPC</small>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-sm btn-success" id="mbAutogenerateBtn">
                            <i class="fas fa-barcode me-1"></i>Autogenerate from UPC
                        </button>
                        <span class="badge bg-primary-subtle text-primary" id="mbCount">0</span>
                    </div>
                </div>
                <div class="row g-2 align-items-end mb-3">
                    <div class="col-md-4 col-lg-3">
                        <label for="mbSkuSearch" class="form-label fw-semibold mb-1">Search SKU</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" id="mbSkuSearch" class="form-control" placeholder="Type SKU…">
                        </div>
                    </div>
                    <div class="col-md-4 col-lg-3">
                        <label for="mbParentSearch" class="form-label fw-semibold mb-1">Search Parent</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" id="mbParentSearch" class="form-control" placeholder="Type Parent…">
                        </div>
                    </div>
                    <div class="col-md-4 col-lg-3">
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
        </div>
    </div>
</div>

{{-- Centered barcode preview modal (opens on click) --}}
<div class="modal fade" id="mbBarcodeModal" tabindex="-1" aria-labelledby="mbBarcodeModalSku" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title mb-0 text-muted">Barcode</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="mbBarcodeModalSku"></div>
                <div id="mbBarcodeModalMedia"></div>
                <div id="mbBarcodeModalCode"></div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const dataUrl = @json(route('masters.barcode.data'));
    const autoUrl = @json(route('masters.barcode.autogenerate'));
    let table = null;

    function escapeHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    const barcodeModalEl = document.getElementById('mbBarcodeModal');
    const barcodeModal = bootstrap.Modal.getOrCreateInstance(barcodeModalEl, {
        backdrop: true,
        keyboard: true,
    });

    function renderBarcodeCell(data) {
        const img = (data.barcode_image || '').trim();
        const code = (data.barcode || data.upc || '').trim();
        const sku = (data.sku || '').trim();
        const safeSku = escapeHtml(sku);
        const safeCode = escapeHtml(code);
        const skuTop = `<div class="mb-barcode-sku-top">${safeSku || '—'}</div>`;
        const upcBottom = `<div class="mb-barcode-upc-bottom">${safeCode || '—'}</div>`;

        if (!img && !code) {
            return '<div class="mb-barcode-empty">No barcode</div>';
        }

        const media = img
            ? `<img src="${escapeHtml(img)}" alt="Barcode" loading="lazy"
                    onerror="this.style.display='none';">`
            : `<svg class="mb-barcode-svg" data-barcode="${safeCode}"></svg>`;

        return `
            <div class="mb-barcode-hover"
                 role="button"
                 tabindex="0"
                 title="View barcode"
                 data-sku="${safeSku}"
                 data-code="${safeCode}"
                 data-img="${escapeHtml(img)}">
                <div class="mb-barcode-square">
                    ${skuTop}
                    ${media}
                    ${upcBottom}
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
        const scope = root || document;
        scope.querySelectorAll('.mb-barcode-svg').forEach((svg) => {
            paintBarcodeSvg(svg, (svg.getAttribute('data-barcode') || '').trim(), false);
        });
    }

    function fitModalSkuFont() {
        const skuEl = document.getElementById('mbBarcodeModalSku');
        if (!skuEl) return;
        const base = 18;
        const target = base * 2; // 2x when space allows
        const min = base;
        const maxWidth = skuEl.parentElement
            ? skuEl.parentElement.clientWidth
            : skuEl.clientWidth;

        skuEl.style.whiteSpace = 'nowrap';
        skuEl.style.fontSize = target + 'px';

        // If 2x overflows, step down (normal proportions) until it fits.
        let size = target;
        while (size > min && skuEl.scrollWidth > maxWidth) {
            size -= 1;
            skuEl.style.fontSize = size + 'px';
        }

        // Allow wrap only for very long SKUs that still don't fit at min size.
        if (skuEl.scrollWidth > maxWidth) {
            skuEl.style.whiteSpace = 'normal';
            skuEl.style.fontSize = min + 'px';
        }
    }

    function showBarcodeModal(el) {
        const sku = el.getAttribute('data-sku') || '';
        const code = el.getAttribute('data-code') || '';
        const img = el.getAttribute('data-img') || '';

        // 1) SKU on top
        const skuEl = document.getElementById('mbBarcodeModalSku');
        skuEl.textContent = sku || '—';
        skuEl.style.fontSize = '';
        skuEl.style.whiteSpace = '';
        // 3) UPC / code below
        document.getElementById('mbBarcodeModalCode').textContent = code || '—';

        // 2) Barcode image in the middle
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
        barcodeModalEl.addEventListener('shown.bs.modal', fitModalSkuFont, { once: true });
        setTimeout(fitModalSkuFont, 40);
    }

    document.getElementById('mbAutogenerateBtn').addEventListener('click', function () {
        if (!confirm('Generate and save barcode images from UPC for all SKUs missing barcodes?')) {
            return;
        }
        const btn = this;
        btn.disabled = true;
        const original = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Generating…';
        fetch(autoUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({ only_missing: true }),
        })
        .then(r => r.json())
        .then(resp => {
            alert(resp.message || 'Done');
            if (table) table.replaceData();
        })
        .catch(() => alert('Autogenerate failed'))
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = original;
        });
    });

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
        const imageFilter = document.getElementById('mbImageFilter').value || 'all';

        table.setFilter(function (data) {
            if (skuQ && !String(data.sku || '').toLowerCase().includes(skuQ)) {
                return false;
            }
            if (parentQ && !String(data.parent || '').toLowerCase().includes(parentQ)) {
                return false;
            }
            if (imageFilter === 'missing' && hasSkuImage(data)) {
                return false;
            }
            if (imageFilter === 'has' && !hasSkuImage(data)) {
                return false;
            }
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
                    if (!src) {
                        return '<div class="mb-cell"><span class="text-muted">—</span></div>';
                    }
                    return `<div class="mb-cell"><img src="${src}" class="mb-product-img" alt="SKU" loading="lazy" onerror="this.outerHTML='<span class=\\'text-muted\\'>—</span>'"></div>`;
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
                    if (!val) {
                        return '<div class="mb-cell"><span class="text-muted">—</span></div>';
                    }
                    return `<div class="mb-cell"><span>${val}</span></div>`;
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
                    return `<div class="mb-cell"><span class="fw-semibold">${cell.getValue() || ''}</span></div>`;
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
                e.stopPropagation();
                showBarcodeModal(el);
            });
            el.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    showBarcodeModal(el);
                }
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
    document.getElementById('mbImageFilter').addEventListener('change', applyFilters);
});
</script>
@endsection
