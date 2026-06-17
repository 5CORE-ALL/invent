@extends('layouts.vertical', ['title' => $title ?? 'SKU Image Manager'])

@section('css')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <style>
        .sku-im-wrapper {
            min-height: 70vh;
        }

        .sku-list-table tbody tr {
            cursor: pointer;
        }

        .sku-list-table tbody tr.sku-row-active {
            background: rgba(13, 110, 253, 0.08) !important;
        }

        .sku-list-table tbody tr.sku-row-parent,
        .sku-list-table tbody tr.sku-row-parent>td,
        .sku-list-table tbody tr.sku-row-parent.sku-row-active {
            --bs-table-bg: rgba(13, 110, 253, 0.2);
            background: rgba(13, 110, 253, 0.2) !important;
        }

        .im-dropzone {
            border: 2px dashed #ced4da;
            border-radius: 0.5rem;
            padding: 1.25rem;
            text-align: center;
            background: #f8f9fa;
            transition: border-color .2s, background .2s;
        }

        .im-dropzone.im-dropzone-active {
            border-color: #0d6efd;
            background: #e7f1ff;
        }

        .im-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 0.75rem;
        }

        .im-card {
            border: 1px solid #e9ecef;
            border-radius: 0.375rem;
            overflow: hidden;
            background: #fff;
        }

        .im-card .thumb {
            position: relative;
            aspect-ratio: 1;
            background: #f1f3f5;
        }

        .im-card .thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .im-card .im-meta {
            font-size: 0.7rem;
            padding: 0.45rem 0.5rem;
        }

        .im-card .badges .badge {
            font-size: 0.6rem;
            text-transform: capitalize;
        }

        #im-upload-progress {
            display: none;
        }

        #im-alert.im-alert-multiline {
            white-space: pre-wrap;
        }
    </style>
@endsection

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('layouts.shared.page-title', [
        'page_title' => 'SKU Image Manager',
        'sub_title' => 'Tools',
    ])
    <div class="mb-2">
        <a href="{{ route('sku-images.push-status') }}" class="btn btn-sm btn-outline-secondary">View push status by marketplace</a>
    </div>

    <div class="row sku-im-wrapper g-3">
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="mb-0">SKUs</h5>
                    <span class="badge bg-light text-dark">{{ $products->count() }} items</span>
                </div>
                <div class="card-body p-2">
                    <div class="table-responsive" style="max-height: 65vh; overflow-y: auto;">
                        <table class="table table-sm table-hover mb-0 sku-list-table" id="sku-dt" style="width:100%">
                            <thead class="table-info">
                                <tr>
                                    <th>SKU</th>
                                    <th class="text-end">Inv</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($products as $p)
                                    <tr class="sku-row @if ($p->is_parent_sku) sku-row-parent @endif @if ($p->images_count > 0) text-success @else text-muted @endif"
                                        data-id="{{ $p->id }}">
                                        <td class="fw-semibold text-start">{{ $p->sku }}</td>
                                        <td class="text-end @if ($p->is_parent_sku) fw-bold @endif" style="min-width:4rem">
                                            <span
                                                @if ($p->is_parent_sku) class="badge bg-success text-white" @endif>{{ (int) ($p->inv ?? 0) }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <h5 class="mb-0" id="im-panel-title">Select a SKU</h5>
                    <div class="d-flex flex-wrap gap-2 align-items-start">
                        @if ($pushMarketplaceOptions->isEmpty())
                            <div class="alert alert-warning small py-2 px-3 mb-0" role="alert" style="max-width:26rem">
                                Reverb push is not available: add an active <code class="small">marketplaces</code> row with
                                code <code class="small">reverb</code> (run
                                <code class="small">php artisan migrate --force</code> or
                                <code class="small">php artisan db:seed --class=SkuImageMarketplaceSeeder --force</code>).
                            </div>
                        @endif
                        <select class="form-select form-select-sm" id="im-markets" name="marketplace_ids[]" multiple
                            @if ($pushMarketplaceOptions->isEmpty()) disabled @endif
                            size="{{ min(3, max(1, $pushMarketplaceOptions->count())) }}">
                            @foreach ($pushMarketplaceOptions as $opt)
                                <option value="{{ $opt->id }}">{{ $opt->label }}</option>
                            @endforeach
                        </select>
                        <button type="button" class="btn btn-sm btn-primary" id="im-push" disabled>Push
                            selected</button>
                    </div>
                </div>
            <div class="card-body">
                <p class="text-muted small" id="im-hint">Choose a product on the left, then upload or select images
                    below.</p>
                <p class="text-muted small border-start border-3 border-info ps-2 mb-2">
                    <strong>Reverb photos vs Title Master:</strong> title push sends text in the API only. Image push sends a <em>URL</em>; Reverb’s servers must fetch that file over the public internet.
                    Use HTTPS <code class="small">APP_URL</code> (not <code class="small">localhost</code>) or set <code class="small">REVERB_SKU_IMAGE_PUBLIC_BASE_URL</code> in <code class="small">.env</code> to the site where <code class="small">/storage/…</code> works, then <code class="small">php artisan config:clear</code>.
                </p>

                <div class="mb-3 d-flex flex-wrap align-items-end gap-2">
                    <div>
                        <label class="form-label small mb-1">Upload (jpg, png, webp)</label>
                        <input type="file" class="form-control form-control-sm" id="im-file-input" accept="image/jpeg,image/png,image/webp" multiple
                            disabled>
                    </div>
                    <button type="button" class="btn btn-sm btn-success" id="im-upload-start" disabled>Start upload</button>
                </div>
                <div class="im-dropzone mb-3" id="im-drop" aria-label="Drop images here" role="button" tabindex="0">Drop
                    files here, or <strong>browse</strong> after you select a SKU
                </div>
                <div class="mb-2" id="im-upload-progress">
                    <div class="progress" style="height: 0.5rem;">
                        <div class="progress-bar" id="im-progress-bar" role="progressbar" style="width:0%"></div>
                    </div>
                    <small class="text-muted" id="im-progress-label"></small>
                </div>
                <div id="im-alert" class="alert d-none small py-2" role="alert"></div>
                <div class="form-check mb-2" id="im-select-all-wrap" style="display:none">
                    <input class="form-check-input" type="checkbox" id="im-select-all-cb">
                    <label class="form-check-label small" for="im-select-all-cb">Select all images on page</label>
                </div>
                <div class="im-grid" id="im-grid"></div>
            </div>
        </div>
    </div>
    </div>
@endsection

@section('script')
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script>
        (function() {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const urls = {
                list: @json(url('/sku-images')),
                upload: @json(url('/sku-images/upload')),
                push: @json(url('/sku-images/push')),
            };
            let currentProductId = null;

            const $file = document.getElementById('im-file-input');
            const $drop = document.getElementById('im-drop');
            const $grid = document.getElementById('im-grid');
            const $title = document.getElementById('im-panel-title');
            const $hint = document.getElementById('im-hint');
            const $upBtn = document.getElementById('im-upload-start');
            const $startUpload = $upBtn;
            const $push = document.getElementById('im-push');
            const $bar = document.getElementById('im-progress-bar');
            const $progWrap = document.getElementById('im-upload-progress');
            const $progLab = document.getElementById('im-progress-label');
            const $alert = document.getElementById('im-alert');
            const $markets = document.getElementById('im-markets');
            const $selAllWrap = document.getElementById('im-select-all-wrap');
            const $selAllCb = document.getElementById('im-select-all-cb');

            function alertMsg(type, text, opts) {
                if (!$alert) return;
                const pre = opts && opts.pre;
                $alert.className = 'alert alert-' + (type || 'info') + ' small py-2' + (pre ? ' im-alert-multiline' : '');
                $alert.textContent = text;
                $alert.classList.remove('d-none');
            }

            function clearAlert() {
                if ($alert) $alert.classList.add('d-none');
            }

            function setSelectedProduct(id) {
                currentProductId = id;
                if ($file) {
                    $file.disabled = !id;
                }
                if ($upBtn) {
                    $upBtn.disabled = !id;
                }
                if ($push) {
                    $push.disabled = !id;
                }
            }

            function badgeHtml(badges) {
                return (badges || []).map(b => {
                    return '<span class="badge ' + b.class + '">' + b.label + '</span>';
                }).join(' ');
            }

            function renderImageCard(img) {
                const w = document.createElement('div');
                w.className = 'im-card';
                w.innerHTML = '<div class="thumb"><div class="position-absolute top-0 start-0 m-1 z-1">' +
                    '<input class="form-check-input im-cb" type="checkbox" value="' + img.id + '">' +
                    '</div><a href="' + img.url + '" target="_blank" rel="noopener">' +
                    '<img src="' + img.url + '" alt=""></a></div>' +
                    '<div class="im-meta text-truncate" title="' + (img.file_name || '') + '">' + (img.file_name || '') +
                    '</div>' +
                    '<div class="im-meta"><div class="badges d-flex flex-wrap gap-1">' + badgeHtml(img.badges) +
                    '</div></div>';
                $grid.appendChild(w);
            }

            function clearGrid() {
                if ($grid) {
                    $grid.innerHTML = '';
                }
                if ($selAllWrap) {
                    $selAllWrap.style.display = 'none';
                }
                if ($selAllCb) {
                    $selAllCb.checked = false;
                }
            }

            function loadImagesForProduct(id) {
                if (!id) {
                    return;
                }
                clearGrid();
                clearAlert();
                fetch(urls.list + '/' + id, {
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        }
                    })
                    .then(r => r.json().then(d => ({ ok: r.ok, d })))
                    .then(({
                        ok,
                        d
                    }) => {
                        if (!ok || !d.ok) {
                            alertMsg('danger', d.message || 'Load failed');
                            return;
                        }
                        $title.textContent = d.product.sku;
                        if ($hint) {
                            $hint.textContent = d.product.label;
                        }
                        (d.images || []).forEach(renderImageCard);
                        if ($selAllWrap) {
                            $selAllWrap.style.display = (d.images && d.images.length) ? 'block' : 'none';
                        }
                    })
                    .catch(() => alertMsg('danger', 'Network error while loading images.'));
            }

            // Left table: row click
            const tbl = document.getElementById('sku-dt');
            if (tbl) {
                $('#sku-dt tbody').on('click', 'tr.sku-row', function() {
                    const id = this.getAttribute('data-id');
                    if (!id) {
                        return;
                    }
                    $('#sku-dt tbody tr').removeClass('sku-row-active');
                    $(this).addClass('sku-row-active');
                    setSelectedProduct(id);
                    loadImagesForProduct(id);
                });
            }

            if ($upBtn) {
                $upBtn.addEventListener('click', function() {
                    if (!$file || !currentProductId) {
                        return;
                    }
                    if (!$file.files || !$file.files.length) {
                        alertMsg('warning', 'Choose at least one image file.');
                        return;
                    }
                    doUpload($file.files);
                });
            }

            function doUpload(fileList) {
                if (!currentProductId) {
                    return;
                }
                const fd = new FormData();
                fd.append('product_id', String(currentProductId));
                for (let i = 0; i < fileList.length; i++) {
                    fd.append('images[]', fileList[i]);
                }
                fd.append('_token', csrf);
                if ($progWrap) {
                    $progWrap.style.display = 'block';
                }
                if ($bar) {
                    $bar.style.width = '0%';
                }
                if ($progLab) {
                    $progLab.textContent = 'Uploading…';
                }
                const xhr = new XMLHttpRequest();
                xhr.open('POST', urls.upload);
                xhr.setRequestHeader('X-CSRF-TOKEN', csrf);
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                xhr.upload.addEventListener('progress', e => {
                    if (!e.lengthComputable) {
                        return;
                    }
                    const pct = Math.round((e.loaded / e.total) * 100);
                    if ($bar) {
                        $bar.style.width = pct + '%';
                    }
                    if ($progLab) {
                        $progLab.textContent = pct + '%';
                    }
                });
                xhr.addEventListener('load', () => {
                    if ($bar) {
                        $bar.style.width = '100%';
                    }
                    if ($progLab) {
                        $progLab.textContent = 'Complete';
                    }
                    try {
                        const data = JSON.parse(xhr.responseText);
                        if (xhr.status === 200 && data.ok) {
                            (data.images || []).forEach(i => {
                                if ($grid) {
                                    renderImageCard(i);
                                }
                            });
                            if ($selAllWrap) {
                                $selAllWrap.style.display = 'block';
                            }
                            const row = document.querySelector('tr.sku-row[data-id="' + currentProductId + '"]');
                            if (row) {
                                row.classList.add('text-success');
                                row.classList.remove('text-muted');
                            }
                            alertMsg('success', 'Uploaded ' + (data.images || []).length + ' file(s).');
                            if ($file) {
                                $file.value = '';
                            }
                        } else {
                            alertMsg('danger', (data && data.message) || 'Upload failed (HTTP ' + xhr.status + ')');
                        }
                    } catch (e) {
                        alertMsg('danger', 'Invalid server response from upload.');
                    }
                    if ($progWrap) {
                        setTimeout(() => {
                            $progWrap.style.display = 'none';
                        }, 1200);
                    }
                });
                xhr.addEventListener('error', () => {
                    alertMsg('danger', 'Network error on upload');
                    if ($progWrap) {
                        $progWrap.style.display = 'none';
                    }
                });
                xhr.send(fd);
            }

            if ($file) {
                $file.addEventListener('change', function() {
                    clearAlert();
                });
            }
            if ($drop) {
                $drop.addEventListener('click', function() {
                    if (!currentProductId) {
                        alertMsg('warning', 'Select a SKU in the list first.');
                        return;
                    }
                    if ($file) {
                        $file.click();
                    }
                });
                $drop.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    this.classList.add('im-dropzone-active');
                });
                $drop.addEventListener('dragleave', function() {
                    this.classList.remove('im-dropzone-active');
                });
                $drop.addEventListener('drop', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    this.classList.remove('im-dropzone-active');
                    if (!currentProductId) {
                        alertMsg('warning', 'Select a SKU first.');
                        return;
                    }
                    const files = e.dataTransfer?.files;
                    if (files && files.length) {
                        doUpload(files);
                    }
                });
            }

            if ($push) {
                $push.addEventListener('click', function() {
                    if (!currentProductId) {
                        return;
                    }
                    const ids = Array.from(document.querySelectorAll('.im-cb:checked'))
                        .map(c => parseInt(c.value, 10))
                        .filter(n => n > 0);
                    if (!ids.length) {
                        alertMsg('warning', 'Select at least one image to push.');
                        return;
                    }
                    if (!$markets) {
                        return;
                    }
                    const mids = Array.from($markets.selectedOptions).map(o => parseInt(o.value, 10));
                    if (!mids.length) {
                        alertMsg('warning', 'Select a marketplace (hold Ctrl to choose multiple in some browsers).');
                        return;
                    }
                    clearAlert();
                    $push.disabled = true;
                    fetch(urls.push, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrf
                            },
                            body: JSON.stringify({
                                product_id: parseInt(currentProductId, 10),
                                image_ids: ids,
                                marketplace_ids: mids
                            })
                        })
                        .then(r => r.json().then(d => ({ ok: r.ok, d, status: r.status })))
                        .then(({
                            ok,
                            d,
                            status
                        }) => {
                            if (!ok) {
                                alertMsg('danger', d.message || ('Push failed: ' + status));
                            } else {
                                const n = d.dispatched || 0;
                                const fails = (d.results || []).filter(r => r.status === 'failed');
                                let msg = 'Processed ' + n + ' push(es) immediately (no queue).\n\nOpen SKU Image Push Status for full API JSON.\n\nCLI retry: php artisan sku-images:push-reverb YOUR-SKU --status=all';
                                if (fails.length) {
                                    const resp = fails[0].response || {};
                                    const firstLine = resp.message || resp.error || '';
                                    const hint = resp.data && resp.data.hint ? String(resp.data.hint) : '';
                                    msg = fails.length + ' of ' + n + ' failed.\n\n' + firstLine +
                                        (hint ? '\n\n' + hint : '') + '\n\n—\n\n' + msg;
                                    alertMsg('warning', msg.trim(), { pre: true });
                                } else {
                                    alertMsg('success', msg, { pre: true });
                                }
                            }
                        })
                        .catch(() => alertMsg('danger', 'Network error on push'))
                        .finally(() => {
                            if (currentProductId) {
                                $push.disabled = false;
                            }
                        });
                });
            }

            if ($selAllCb) {
                $selAllCb.addEventListener('change', function() {
                    const on = this.checked;
                    document.querySelectorAll('.im-cb').forEach(c => { c.checked = on; });
                });
            }

            if (tbl) {
                $('#sku-dt').DataTable({
                    ordering: false,
                    pageLength: 25,
                });
            }
        })();
    </script>
@endsection
