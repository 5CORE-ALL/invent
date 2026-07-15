@extends('layouts.vertical', ['title' => 'YouTube Missing Ads', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .yt-missing .tabulator .tabulator-header .tabulator-col { font-size: 0.8rem; }
        .yt-missing .tabulator-row .tabulator-cell { font-size: 0.85rem; }
        .yt-missing .parent-chip {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: #e7f5ff;
            color: #1971c2;
            border: 1px solid #a5d8ff;
            border-radius: 10px;
            padding: 1px 7px;
            margin: 1px 2px;
            font-size: 0.72rem;
            white-space: nowrap;
        }
        .yt-missing .parent-chip .chip-x { cursor: pointer; color: #e03131; }
        .yt-missing .link-add-btn {
            border: 1px solid #adb5bd;
            background: #fff;
            border-radius: 6px;
            padding: 0 6px;
            line-height: 1.4;
            cursor: pointer;
            color: #2f9e44;
        }
        .yt-missing .link-add-btn:hover { background: #f1f3f5; }
        .yt-parent-picker {
            position: absolute;
            z-index: 2000;
            width: 320px;
            max-height: 300px;
            background: #fff;
            border: 1px solid #ced4da;
            border-radius: 6px;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.18);
            padding: 6px;
            display: flex;
            flex-direction: column;
        }
        .yt-parent-picker.d-none { display: none !important; }
        .yt-parent-picker .yt-picker-list { overflow-y: auto; margin-top: 6px; }
        .yt-parent-picker .yt-picker-option {
            padding: 4px 6px;
            font-size: 0.78rem;
            cursor: pointer;
            border-radius: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .yt-parent-picker .yt-picker-option:hover { background: #e7f5ff; }
        .yt-parent-picker .yt-picker-empty {
            padding: 6px;
            color: #868e96;
            font-size: 0.78rem;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared/page-title', ['sub_title' => 'Google Ads', 'page_title' => 'YouTube Missing Ads'])

    <div class="row yt-missing">
        <div class="col-12">
            <div class="card">
                <div class="card-body p-2">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <div class="badge bg-primary" id="ytGrandparentCountWrap" title="Total grandparents">
                            Grandparent <span id="ytGrandparentCount">0</span>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="ytCreateGrandparentBtn" title="Create a new grandparent">
                            <i class="fa fa-plus me-1"></i> Grandparent
                        </button>
                        <button type="button" class="btn btn-success btn-sm ms-auto" id="ytMissingExportBtn">
                            <i class="fa fa-download me-1"></i> Export
                        </button>
                    </div>
                    <div id="ytMissingAdsTable"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="ytCreateGrandparentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create Grandparent</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label for="ytCreateGrandparentInput" class="form-label mb-1">Grandparent name</label>
                    <input type="text" id="ytCreateGrandparentInput" class="form-control" placeholder="Enter grandparent..." autocomplete="off">
                    <div class="form-text small text-danger d-none" id="ytCreateGrandparentError"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="ytCreateGrandparentSaveBtn">Create</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        (function () {
            var dataUrl = @json(route('google.youtube.ads.missing.data'));
            var createGrandparentUrl = @json(route('google.youtube.ads.missing.grandparent.create'));
            var searchUrl = @json(route('google.youtube.ads.missing.parents.search'));
            var linkUrl = @json(route('google.youtube.ads.missing.parents.link'));
            var unlinkUrl = @json(route('google.youtube.ads.missing.parents.unlink'));
            var csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

            function esc(str) {
                return String(str == null ? '' : str)
                    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            }

            function postJson(url, payload) {
                return fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(payload)
                }).then(function (res) {
                    return res.json().then(function (body) { return { ok: res.ok, body: body }; });
                });
            }

            function parentsFormatter(cell) {
                var d = cell.getData();
                var list = Array.isArray(d.parents) ? d.parents : [];
                var chips = list.map(function (p) {
                    return '<span class="parent-chip" title="' + esc(p.parent) + '">' + esc(p.parent)
                        + ' <i class="fa fa-times chip-x" data-id="' + p.id + '" data-grandparent="' + esc(d.grandparent) + '" title="Remove parent"></i></span>';
                }).join('');
                return chips
                    + '<button type="button" class="link-add-btn" data-grandparent="' + esc(d.grandparent) + '" title="Add parent(s)">'
                    + '<i class="fa fa-plus"></i></button>';
            }

            function parentsDownload(value) {
                if (!Array.isArray(value)) { return ''; }
                return value.map(function (p) { return p && p.parent ? p.parent : ''; }).filter(Boolean).join(', ');
            }

            var table = new Tabulator('#ytMissingAdsTable', {
                ajaxURL: dataUrl,
                ajaxResponse: function (url, params, response) {
                    return (response && Array.isArray(response.data)) ? response.data : (response || []);
                },
                index: 'id',
                layout: 'fitColumns',
                height: 'calc(100vh - 220px)',
                pagination: true,
                paginationSize: 100,
                paginationSizeSelector: [25, 50, 100, 200],
                placeholder: 'No grandparent data found',
                initialSort: [{ column: 'grandparent', dir: 'asc' }],
                columns: [
                    {
                        title: 'Grandparent',
                        field: 'grandparent',
                        headerFilter: 'input',
                        headerFilterPlaceholder: 'Search Grandparent...',
                        cssClass: 'text-primary',
                        widthGrow: 1,
                        tooltip: true
                    },
                    {
                        title: 'Parents',
                        field: 'parents',
                        widthGrow: 2,
                        hozAlign: 'left',
                        headerHozAlign: 'center',
                        headerFilter: 'input',
                        headerFilterPlaceholder: 'Search Parent...',
                        headerFilterFunc: function (headerValue, rowValue) {
                            if (!headerValue) { return true; }
                            var q = String(headerValue).toLowerCase();
                            var list = Array.isArray(rowValue) ? rowValue : [];
                            return list.some(function (p) {
                                return p && p.parent && String(p.parent).toLowerCase().indexOf(q) !== -1;
                            });
                        },
                        formatter: parentsFormatter,
                        accessorDownload: parentsDownload
                    }
                ]
            });

            table.on('dataLoaded', function (data) {
                var el = document.getElementById('ytGrandparentCount');
                if (el) {
                    el.textContent = Number((data || []).length).toLocaleString('en-US');
                }
            });

            document.getElementById('ytMissingExportBtn').addEventListener('click', function () {
                table.download('csv', 'youtube-grandparents-' + new Date().toISOString().slice(0, 10) + '.csv');
            });

            function updateGrandparentCount() {
                var el = document.getElementById('ytGrandparentCount');
                if (el) {
                    el.textContent = Number(table.getData().length).toLocaleString('en-US');
                }
            }

            var createModalEl = document.getElementById('ytCreateGrandparentModal');
            var createModal = createModalEl && window.bootstrap
                ? bootstrap.Modal.getOrCreateInstance(createModalEl)
                : null;
            var createInput = document.getElementById('ytCreateGrandparentInput');
            var createError = document.getElementById('ytCreateGrandparentError');

            document.getElementById('ytCreateGrandparentBtn').addEventListener('click', function () {
                createError.classList.add('d-none');
                createError.textContent = '';
                createInput.value = '';
                if (createModal) { createModal.show(); }
                setTimeout(function () { createInput.focus(); }, 200);
            });

            function saveNewGrandparent() {
                var name = (createInput.value || '').trim();
                createError.classList.add('d-none');
                createError.textContent = '';
                if (!name) {
                    createError.textContent = 'Enter a grandparent name.';
                    createError.classList.remove('d-none');
                    return;
                }
                postJson(createGrandparentUrl, { grandparent: name }).then(function (out) {
                    if (out.ok && out.body && out.body.ok && out.body.row) {
                        table.addData([out.body.row], true);
                        updateGrandparentCount();
                        if (createModal) { createModal.hide(); }
                    } else {
                        createError.textContent = (out.body && out.body.message) || 'Failed to create grandparent.';
                        createError.classList.remove('d-none');
                    }
                });
            }

            document.getElementById('ytCreateGrandparentSaveBtn').addEventListener('click', saveNewGrandparent);
            createInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    saveNewGrandparent();
                }
            });

            // Floating quick-search parent picker
            var picker = document.createElement('div');
            picker.className = 'yt-parent-picker d-none';
            picker.innerHTML = '<input type="text" class="form-control form-control-sm yt-picker-input" placeholder="Quick search parent...">'
                + '<div class="yt-picker-list"></div>';
            document.body.appendChild(picker);
            var pickerInput = picker.querySelector('.yt-picker-input');
            var pickerList = picker.querySelector('.yt-picker-list');
            var pickerGrandparent = '';
            var searchTimer = null;

            function closePicker() {
                picker.classList.add('d-none');
                pickerGrandparent = '';
                pickerInput.value = '';
                pickerList.innerHTML = '';
            }

            function openPicker(btn, grandparent) {
                pickerGrandparent = grandparent;
                var rect = btn.getBoundingClientRect();
                picker.style.top = (window.scrollY + rect.bottom + 2) + 'px';
                picker.style.left = (window.scrollX + rect.left) + 'px';
                picker.classList.remove('d-none');
                pickerInput.value = '';
                pickerList.innerHTML = '<div class="yt-picker-empty">Type to search parents…</div>';
                pickerInput.focus();
            }

            function renderPickerResults(items) {
                if (!items.length) {
                    pickerList.innerHTML = '<div class="yt-picker-empty">No matching parents</div>';
                    return;
                }
                pickerList.innerHTML = items.map(function (p) {
                    return '<div class="yt-picker-option" data-parent="' + esc(p.parent) + '" title="' + esc(p.parent) + '">'
                        + esc(p.parent) + '</div>';
                }).join('');
            }

            function runSearch(q) {
                if (!q) {
                    pickerList.innerHTML = '<div class="yt-picker-empty">Type to search parents…</div>';
                    return;
                }
                pickerList.innerHTML = '<div class="yt-picker-empty">Searching…</div>';
                fetch(searchUrl + '?q=' + encodeURIComponent(q), {
                    headers: { 'Accept': 'application/json' }
                })
                    .then(function (res) { return res.json(); })
                    .then(function (json) {
                        renderPickerResults((json && Array.isArray(json.data)) ? json.data : []);
                    })
                    .catch(function () {
                        pickerList.innerHTML = '<div class="yt-picker-empty">Search failed</div>';
                    });
            }

            pickerInput.addEventListener('input', function () {
                var q = this.value.trim();
                clearTimeout(searchTimer);
                searchTimer = setTimeout(function () { runSearch(q); }, 200);
            });

            pickerList.addEventListener('click', function (e) {
                var opt = e.target.closest('.yt-picker-option');
                if (!opt || !pickerGrandparent) { return; }
                var parent = opt.getAttribute('data-parent') || '';
                postJson(linkUrl, { grandparent: pickerGrandparent, parent: parent }).then(function (out) {
                    if (out.ok && out.body && out.body.ok) {
                        var row = table.getRows().find(function (r) {
                            return r.getData().grandparent === pickerGrandparent;
                        });
                        if (row) {
                            row.update({ parents: out.body.parents || [] });
                        }
                        // Keep picker open so multiple parents can be added quickly
                        pickerInput.focus();
                        pickerInput.select();
                    } else {
                        window.alert((out.body && out.body.message) || 'Failed to add parent.');
                    }
                });
            });

            document.addEventListener('click', function (e) {
                if (picker.classList.contains('d-none')) { return; }
                if (picker.contains(e.target) || e.target.closest('.link-add-btn')) { return; }
                closePicker();
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') { closePicker(); }
            });

            document.getElementById('ytMissingAdsTable').addEventListener('click', function (e) {
                var addBtn = e.target.closest('.link-add-btn');
                if (addBtn) {
                    openPicker(addBtn, addBtn.getAttribute('data-grandparent') || '');
                    return;
                }
                var x = e.target.closest('.chip-x');
                if (x) {
                    var id = Number(x.getAttribute('data-id'));
                    var gp = x.getAttribute('data-grandparent') || '';
                    postJson(unlinkUrl, { id: id }).then(function (out) {
                        if (out.ok && out.body && out.body.ok) {
                            var row = table.getRows().find(function (r) {
                                return r.getData().grandparent === gp;
                            });
                            if (row) {
                                row.update({ parents: out.body.parents || [] });
                            }
                        }
                    });
                }
            });
        })();
    </script>
@endsection
