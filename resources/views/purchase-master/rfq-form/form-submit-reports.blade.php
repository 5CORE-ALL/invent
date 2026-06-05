@extends('layouts.vertical', ['title' => $form->name . ' Form Reports'])
@section('css')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
<style>
    /* Pagination styling */
    .tabulator .tabulator-footer .tabulator-paginator .tabulator-page {
        padding: 8px 16px;
        margin: 0 4px;
        border-radius: 6px;
        font-size: 0.95rem;
        font-weight: 500;
        transition: all 0.2s;
    }

    .tabulator .tabulator-footer .tabulator-paginator .tabulator-page:hover {
        background: #e0eaff;
        color: #2563eb;
    }

    .tabulator .tabulator-footer .tabulator-paginator .tabulator-page.active {
        background: #2563eb;
        color: white;
    }

    #img-popup {
        pointer-events: none;
    }

    .thumb-img {
        height: 30px;
        width: 30px;
        margin: 2px;
        border-radius: 4px;
        cursor: pointer;
        transition: all 0.2s ease-in-out;
        position: relative;
    }

    .thumb-img:hover::after {
        content: '';
        position: fixed;
        top: 50%;
        left: 50%;
        width: auto;
        height: auto;
        max-width: 80vw;
        max-height: 80vh;
        transform: translate(-50%, -50%);
        background: url(attr(src)) no-repeat center/contain;
        border: 2px solid #ccc;
        border-radius: 8px;
        z-index: 9999;
    }
</style>
@endsection
@section('content')
@include('layouts.shared.page-title', ['page_title' => $form->name . ' Form Reports', 'sub_title' => $form->name . ' Form Reports'])

@if (Session::has('flash_message'))
    <div class="alert alert-primary bg-primary text-white alert-dismissible fade show" role="alert"
        style="background-color: #03a744 !important; color: #fff !important;">
        {{ Session::get('flash_message') }}
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="row">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-end align-items-center mb-3 gap-2">
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        @include('purchase-master.partials.page-info-toolbar', ['pageKey' => 'rfq_form_reports'])
                        <button id="export-btn" class="btn btn-sm btn-success">
                            <i class="fas fa-file-excel"></i> Export Excel
                        </button>
                    </div>
                </div>
                <div id="form-reports-table"></div>
            </div>
        </div>
    </div>
</div>

@endsection
@section('script')
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<!-- SheetJS for Excel Export -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>
    // Replace a broken/missing image with a small "N/A" placeholder instead of a broken icon
    window.rfqImgError = function(img){
        img.onerror = null;
        img.src = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="34" height="34"><rect width="100%25" height="100%25" fill="%23f1f1f1" stroke="%23dcdcdc"/><text x="50%25" y="56%25" font-size="8" text-anchor="middle" fill="%23999">N/A</text></svg>';
        img.style.cursor = 'default';
        img.classList.remove('thumb-img');
    };

    function escapeHtml(str){
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // Detect image/file links inside a text value and render them inline.
    // Images load automatically (browser downloads + shows them); files become download links.
    function renderMediaValue(text){
        const urlRegex = /(https?:\/\/[^\s,;"'<>]+|\/storage\/[^\s,;"'<>]+)/gi;
        if(!urlRegex.test(text)){
            return escapeHtml(text);
        }
        urlRegex.lastIndex = 0;

        return text.replace(urlRegex, function(url){
            const clean = url.replace(/[).,]+$/, '');
            const lower = clean.split('?')[0].toLowerCase();
            const isImg = /\.(jpe?g|png|gif|webp|bmp|svg|avif)$/.test(lower);
            const isFile = /\.(pdf|docx?|xlsx?|pptx?|zip|rar|7z|csv|txt|ai|psd|cdr|eps)$/.test(lower);

            if(isImg){
                return `<img src="${clean}" class="thumb-img" onerror="rfqImgError(this)" style="width:34px; height:34px; margin:2px; border-radius:4px; cursor:pointer;">`;
            } else if(isFile){
                const name = clean.split('/').pop().split('?')[0];
                return `<a href="${clean}" target="_blank" rel="noopener noreferrer" download
                            class="btn btn-sm btn-outline-secondary" title="Download ${escapeHtml(name)}">
                            <i class="fa-solid fa-download"></i>
                        </a>`;
            }
            return `<a href="${clean}" target="_blank" rel="noopener noreferrer"
                        class="btn btn-sm btn-outline-info" title="Open link">
                        <i class="fa-solid fa-link"></i>
                    </a>`;
        });
    }

    document.addEventListener('DOMContentLoaded', function () {

        const table = new Tabulator("#form-reports-table", {
            ajaxURL: "/rfq-form/reports-data/{{ $form->id }}",
            ajaxConfig: "GET",
            layout: "fitData",
            pagination: false,
            movableColumns: false,
            resizableColumns: true,
            height: "600px",
            columns: [],
            ajaxResponse: function(url, params, response){
                const submissions = (response.data || []).map(r => r.data || {});
                if(submissions.length === 0){
                    table.setColumns([]);
                    return [];
                }

                const humanize = k => k.replace(/([A-Z])/g, ' $1').replace(/^./, str => str.toUpperCase());

                // Ordered union of attribute keys across all submissions (priority first)
                const priorityKeys = ['supplierName','companyName','supplierLink','productName','additionalPhotos'];
                const orderedKeys = [];
                const seen = {};
                priorityKeys.forEach(k => {
                    if(submissions.some(s => s.hasOwnProperty(k))){ orderedKeys.push(k); seen[k] = true; }
                });
                submissions.forEach(s => {
                    Object.keys(s).forEach(k => {
                        if(!seen[k]){ seen[k] = true; orderedKeys.push(k); }
                    });
                });

                // Build columns: reference columns, then the attribute label, then one column per supplier
                const columns = [
                    {
                        title: "Target",
                        field: "__target",
                        frozen: true,
                        headerSort: false,
                        width: 110,
                        editor: "input",
                        hozAlign: "left",
                        formatter: function(cell){
                            const v = cell.getValue();
                            return (v === null || v === undefined || v === '') ? '-' : v;
                        }
                    },
                    {
                        title: "Last Purchase",
                        field: "__last_purchase",
                        frozen: true,
                        headerSort: false,
                        width: 130,
                        editor: "input",
                        hozAlign: "left",
                        formatter: function(cell){
                            const v = cell.getValue();
                            return (v === null || v === undefined || v === '') ? '-' : v;
                        }
                    },
                    {
                        title: "Field",
                        field: "__field",
                        frozen: true,
                        headerSort: false,
                        width: 200,
                        formatter: function(cell){ return `<strong>${cell.getValue()}</strong>`; }
                    }
                ];

                submissions.forEach((s, idx) => {
                    const supName = (s.supplierName && String(s.supplierName).trim() !== '')
                        ? s.supplierName
                        : ('Supplier ' + (idx + 1));
                    columns.push({
                        title: supName,
                        field: 'sup_' + idx,
                        headerSort: false,
                        minWidth: 180,
                        hozAlign: "left",
                        formatter: function(cell){
                            const v = cell.getValue();
                            if(v === null || v === undefined || v === '') return '-';
                            if(typeof v !== 'string') return v;
                            if(/<[a-z]/i.test(v)) return v; // already rendered HTML (photos/links)
                            return renderMediaValue(v);
                        }
                    });
                });

                table.setColumns(columns);

                // Build rows: one per attribute (supplierName is shown as the column header)
                const rows = orderedKeys
                    .filter(k => k !== 'supplierName')
                    .map(k => {
                        const row = { __field: humanize(k) };
                        submissions.forEach((s, idx) => {
                            let val = s[k];
                            if(k === 'additionalPhotos'){
                                val = (Array.isArray(val) && val.length)
                                    ? val.map((img, i) => `<img src="/storage/${img}" class="thumb-img" data-img-id="${i}" onerror="rfqImgError(this)" style="width:30px; height:30px; margin:2px; border-radius:4px; cursor:pointer;">`).join('')
                                    : '-';
                            } else if(k === 'supplierLink'){
                                val = val ? `<a href="${val}" target="_blank" class="btn btn-sm btn-outline-info"><i class="fa-solid fa-link"></i></a>` : '-';
                            } else if(val === null || val === undefined || val === ''){
                                val = '-';
                            }
                            row['sup_' + idx] = val;
                        });
                        return row;
                    });

                return rows;
            },
        });

        // ===== Auto-translate Chinese text to English =====
        const translationCache = new Map();

        function containsChinese(s){
            return typeof s === 'string' && /[\u3400-\u4dbf\u4e00-\u9fff\uf900-\ufaff]/.test(s);
        }

        async function translateText(text){
            if(translationCache.has(text)) return translationCache.get(text);
            try {
                const res = await fetch('https://translate.googleapis.com/translate_a/single?client=gtx&sl=zh-CN&tl=en&dt=t&q=' + encodeURIComponent(text));
                const data = await res.json();
                const translated = (data && Array.isArray(data[0]))
                    ? data[0].map(seg => seg[0]).join('')
                    : text;
                translationCache.set(text, translated);
                return translated;
            } catch(e){
                return text;
            }
        }

        async function autoTranslateReport(){
            // Translate supplier column headers
            for(const col of table.getColumns()){
                const field = col.getField();
                const def = col.getDefinition();
                if(field && field.startsWith('sup_') && containsChinese(def.title)){
                    const t = await translateText(def.title);
                    if(t && t !== def.title){
                        try { col.updateDefinition({ title: t }); } catch(e){}
                    }
                }
            }

            // Translate cell values (skip cells that contain HTML such as photos/links)
            for(const row of table.getRows()){
                const data = row.getData();
                const update = {};
                for(const key in data){
                    if(!key.startsWith('sup_')) continue;
                    const val = data[key];
                    if(containsChinese(val) && !/[<>]/.test(val)){
                        const t = await translateText(val);
                        if(t && t !== val) update[key] = t;
                    }
                }
                if(Object.keys(update).length){
                    row.update(update);
                }
            }
        }

        table.on("dataLoaded", function(){
            autoTranslateReport();
        });


        let popup;

        document.addEventListener('mouseover', function(e){
            if(e.target.classList.contains('thumb-img')){
                const src = e.target.src;

                if(popup) popup.remove();

                popup = document.createElement('div');
                popup.id = 'img-popup';
                popup.style.position = 'fixed';
                popup.style.top = '50%';
                popup.style.left = '50%';
                popup.style.transform = 'translate(-50%, -50%)';
                popup.style.zIndex = 9999;
                popup.style.padding = '10px';
                popup.style.background = '#fff';
                popup.style.border = '2px solid #ccc';
                popup.style.borderRadius = '8px';
                popup.innerHTML = `<img src="${src}" style="max-width:80vw; max-height:80vh;">`;

                document.body.appendChild(popup);
            }
        });

        document.addEventListener('mouseout', function(e){
            if(e.target.classList.contains('thumb-img')){
                if(popup) {
                    popup.remove();
                    popup = null;
                }
            }
        });

        document.getElementById("export-btn").addEventListener("click", function () {
            let allData = table.getData("active");

            if (allData.length === 0) {
                alert("No data available to export!");
                return;
            }

            const stripHtml = (html) => {
                const d = document.createElement('div');
                d.innerHTML = (html === null || html === undefined) ? '' : String(html);
                return (d.textContent || d.innerText || '').trim();
            };

            const cols = table.getColumns().map(c => ({
                field: c.getField(),
                title: c.getDefinition().title
            }));

            let exportData = allData.map(row => {
                const obj = {};
                cols.forEach(c => {
                    if(!c.field) return;
                    obj[c.title] = stripHtml(row[c.field]);
                });
                return obj;
            });

            let formName = "{{ $form->name }}";

            let ws = XLSX.utils.json_to_sheet(exportData);
            let wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, "Report");

            XLSX.writeFile(wb, `${formName}_form_report.xlsx`);
        });


    });
</script>

@endsection