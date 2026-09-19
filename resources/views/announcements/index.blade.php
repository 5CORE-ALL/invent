@extends('layouts.vertical', ['title' => 'Announcement', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        #announcementTable .tabulator-header {
            background: #1abc9c;
        }
        #announcementTable .tabulator-header .tabulator-col {
            background: #1abc9c;
            border-right: 1px solid rgba(255, 255, 255, 0.25);
        }
        #announcementTable .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            color: #0f172a;
            font-weight: 700;
            font-size: 13px;
        }
        #announcementTable .tabulator-cell {
            padding: 10px 14px !important;
            white-space: normal !important;
        }
        .ann-message {
            font-size: 13px;
            color: #0f172a;
            line-height: 1.45;
            white-space: pre-wrap;
        }
        .ann-date-pill {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 999px;
            background: #e0f2fe;
            color: #075985;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }
        .ann-thumbs {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        .ann-user-avatar {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid #e2e8f0;
            flex-shrink: 0;
            background: #fff;
        }
        .ann-posted-by {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
        }
        .ann-cell-with-user {
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        .ann-cell-with-user__body {
            min-width: 0;
            flex: 1;
        }
        .ann-table-comments {
            margin-top: 10px;
            padding-top: 8px;
            border-top: 1px dashed #e2e8f0;
        }
        .ann-table-comment {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            margin-bottom: 6px;
        }
        .ann-table-comment img,
        .ann-table-comment-form img {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid #e2e8f0;
            flex-shrink: 0;
        }
        .ann-table-comment__body {
            background: #f8fafc;
            border-radius: 8px;
            padding: 4px 8px;
            min-width: 0;
            flex: 1;
        }
        .ann-table-comment__name {
            font-size: 11px;
            font-weight: 800;
            color: #334155;
        }
        .ann-table-comment__text {
            font-size: 12px;
            color: #0f172a;
            white-space: pre-wrap;
        }
        .ann-table-comment-form {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 6px;
        }
        .ann-table-comment-form input {
            flex: 1;
            min-width: 0;
            border: 1px solid #cbd5e1;
            border-radius: 999px;
            padding: 4px 10px;
            font-size: 12px;
        }
        .ann-table-comment-form button {
            border: 0;
            border-radius: 999px;
            background: #1d4ed8;
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            padding: 4px 10px;
        }
        .ann-thumbs img {
            width: 72px;
            height: 72px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            cursor: zoom-in;
            background: #fff;
        }
        .ann-action-btn {
            background: transparent;
            border: 0;
            padding: 4px 6px;
            cursor: pointer;
            font-size: 15px;
            border-radius: 6px;
        }
        .ann-action-btn.is-edit { color: #2563eb; }
        .ann-action-btn.is-edit:hover { background: #dbeafe; }
        .ann-action-btn.is-post { color: #d97706; }
        .ann-action-btn.is-post:hover { background: #fef3c7; }
        .ann-action-btn.is-post.is-live { color: #15803d; }
        .ann-action-btn.is-post.is-live:hover { background: #dcfce7; }
        .ann-action-btn.is-delete { color: #dc2626; }
        .ann-action-btn.is-delete:hover { background: #fee2e2; }
        .ann-input-box {
            border: 1px solid #ced4da;
            border-radius: 8px;
            background: #fff;
            overflow: hidden;
        }
        .ann-input-box #ann_message {
            border: 0;
            border-radius: 0;
            box-shadow: none;
            resize: vertical;
        }
        .ann-input-box #ann_message:focus {
            box-shadow: none;
        }
        .ann-keep-item {
            position: relative;
            display: block;
            margin: 0;
            padding: 10px 12px 12px;
            border-top: 1px solid #e2e8f0;
            background: #fff;
        }
        .ann-keep-item img {
            display: block;
            width: 100%;
            height: auto;
            max-height: 360px;
            object-fit: contain;
            object-position: center;
            border-radius: 8px;
            background: #f8fafc;
        }
        .ann-keep-item__remove {
            position: absolute;
            top: 16px;
            right: 18px;
            width: 28px;
            height: 28px;
            border: 0;
            border-radius: 999px;
            background: rgba(15, 23, 42, 0.72);
            color: #fff;
            line-height: 1;
            font-size: 16px;
            cursor: pointer;
        }
        .ann-field-label {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }
        .ann-pen-btns {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .ann-pen-btn {
            width: 32px;
            height: 32px;
            padding: 0;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #fff;
            color: #334155;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
        }
        .ann-pen-btn:hover { background: #f1f5f9; }
        .ann-pen-btn.is-ai {
            background: linear-gradient(135deg, #6d28d9, #8b5cf6);
            border-color: transparent;
            color: #fff;
        }
        .ann-pen-btn.is-ai:hover { filter: brightness(1.05); color: #fff; }
        .ann-pen-btn:disabled { opacity: 0.6; cursor: wait; }
        .ann-ai-box {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 10px 12px;
            background: #f8fafc;
        }
        .ann-kind-toggle .btn {
            min-width: 72px;
        }
        .ann-viewed-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 0;
            border-radius: 999px;
            padding: 4px 10px;
            background: #e0f2fe;
            color: #075985;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }
        .ann-viewed-btn:hover { background: #bae6fd; }
        .ann-viewed-btn.is-active { background: #0369a1; color: #fff; }
        .ann-viewed-btn__count {
            min-width: 18px;
            height: 18px;
            border-radius: 999px;
            background: #0ea5e9;
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            padding: 0 5px;
        }
        .ann-viewed-btn.is-active .ann-viewed-btn__count { background: #fff; color: #0369a1; }
        .ann-viewers-panel {
            display: none;
            margin-bottom: 16px;
        }
        .ann-viewers-panel.is-open { display: block; }
        .ann-viewers-panel__head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 10px;
        }
        .ann-viewers-panel__title {
            margin: 0;
            font-size: 14px;
            font-weight: 700;
            color: #0f172a;
        }
        .ann-viewers-col {
            border-radius: 12px;
            padding: 12px 14px;
            min-height: 92px;
        }
        .ann-viewers-col.is-viewed {
            background: #dcfce7;
            border: 1px solid #86efac;
        }
        .ann-viewers-col.is-not-viewed {
            background: #fee2e2;
            border: 1px solid #fca5a5;
        }
        .ann-viewers-col h6 {
            margin: 0 0 8px;
            font-size: 13px;
            font-weight: 800;
        }
        .ann-viewers-col.is-viewed h6 { color: #166534; }
        .ann-viewers-col.is-not-viewed h6 { color: #991b1b; }
        .ann-viewer-chip {
            display: inline-flex;
            align-items: center;
            margin: 0 6px 6px 0;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
        }
        .ann-viewers-col.is-viewed .ann-viewer-chip {
            background: #166534;
            color: #fff;
        }
        .ann-viewers-col.is-not-viewed .ann-viewer-chip {
            background: #b91c1c;
            color: #fff;
        }
        .ann-viewers-empty {
            font-size: 12px;
            font-weight: 600;
            opacity: 0.75;
        }
    </style>
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between">
                <h4 class="page-title mb-0">
                    <i class="ri-megaphone-line me-2 text-primary"></i>Announcement
                </h4>
            </div>
        </div>
    </div>

    <div class="ann-viewers-panel" id="annViewersPanel">
        <div class="ann-viewers-panel__head">
            <h5 class="ann-viewers-panel__title" id="annViewersTitle">Viewed By</h5>
            <button type="button" class="btn btn-sm btn-light" id="annViewersCloseBtn">Close</button>
        </div>
        <div class="row g-3">
            <div class="col-md-6">
                <div class="ann-viewers-col is-viewed">
                    <h6>Viewers <span id="annViewersCount"></span></h6>
                    <div id="annViewersList"></div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="ann-viewers-col is-not-viewed">
                    <h6>Non Viewers <span id="annNonViewersCount"></span></h6>
                    <div id="annNonViewersList"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="header-title mb-0">Announcement</h4>
                    @if ($canAnnounce)
                        <button type="button" class="btn btn-sm btn-primary" id="annAddBtn">
                            <i class="ri-add-line me-1"></i> Add Announcement
                        </button>
                    @endif
                </div>
                <div class="card-body">
                    <div id="announcementTable"></div>
                </div>
            </div>
        </div>
    </div>

    @if ($canAnnounce)
        <div class="modal fade" id="annModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
                <form class="modal-content" id="annForm" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="annModalTitle">New Announcement</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="ann_id" name="id">
                        <div class="mb-3">
                            <label for="ann_date" class="form-label">Date <span class="text-danger">*</span></label>
                            <input type="date" id="ann_date" name="announced_on" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <div class="ann-field-label mb-1">
                                <label for="ann_message" class="form-label mb-0">Announcement</label>
                                <div class="ann-pen-btns">
                                    <button type="button" class="ann-pen-btn" id="annEditPenBtn" title="Edit announcement text">
                                        <i class="ri-pencil-line"></i>
                                    </button>
                                    <button type="button" class="ann-pen-btn is-ai" id="annAiPenBtn" title="Generate with AI">
                                        <i class="ri-magic-line"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="ann-input-box">
                                <textarea id="ann_message" name="message" class="form-control" rows="4" maxlength="5000" placeholder="Write the announcement, or generate it with AI…"></textarea>
                                <div id="annKeepImages"></div>
                            </div>
                        </div>
                        <div class="ann-ai-box mb-3">
                            <label for="ann_ai_prompt" class="form-label">AI prompt</label>
                            <textarea id="ann_ai_prompt" class="form-control" rows="2" maxlength="2000" placeholder="Describe the announcement text or image you want…"></textarea>
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mt-2">
                                <div class="btn-group ann-kind-toggle" role="group" aria-label="AI result type">
                                    <input type="radio" class="btn-check" name="ann_ai_kind" id="annAiKindText" value="text" checked>
                                    <label class="btn btn-outline-secondary btn-sm" for="annAiKindText">Text</label>
                                    <input type="radio" class="btn-check" name="ann_ai_kind" id="annAiKindImage" value="image">
                                    <label class="btn btn-outline-secondary btn-sm" for="annAiKindImage">Image</label>
                                </div>
                                <button type="button" class="btn btn-sm btn-primary" id="annAiRunBtn">
                                    <i class="ri-magic-line me-1"></i> Generate
                                </button>
                            </div>
                            <div class="form-text">Text and images both go into the Announcement box above.</div>
                        </div>
                        <div class="mb-0">
                            <input type="file" id="ann_images" name="images[]" class="form-control" accept="image/*" multiple>
                            <div class="form-text">Optional upload. Generated or uploaded images appear only in the Announcement box.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="annSaveBtn">Save</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection

@section('script-after-vite')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        $(function () {
            const csrf = $('meta[name="csrf-token"]').attr('content');
            const canAnnounce = @json((bool) $canAnnounce);
            const dataUrl = @json(route('announcements.data'));
            const storeUrl = @json(route('announcements.store'));
            const aiUrl = @json(route('announcements.ai'));
            const updateBase = @json(url('announcements/update'));
            const deleteBase = @json(url('announcements/delete'));
            const postBase = @json(url('announcements/post'));
            const viewersBase = @json(url('announcements'));
            @php
                $annMe = auth()->user();
                $annMeAvatar = asset('images/users/avatar-2.jpg');
                if ($annMe && trim((string) $annMe->avatar) !== '') {
                    $annMeAvatar = str_starts_with((string) $annMe->avatar, 'http')
                        ? $annMe->avatar
                        : asset('storage/'.$annMe->avatar);
                }
            @endphp
            const currentUser = @json([
                'name' => $annMe?->name ?: 'You',
                'avatar' => $annMeAvatar,
            ]);

            function escapeHtml(s) {
                const d = document.createElement('div');
                d.textContent = s == null ? '' : String(s);
                return d.innerHTML;
            }

            function renderTableComment(comment) {
                return '<div class="ann-table-comment">'
                    + '<img src="' + escapeHtml(comment.user_avatar || currentUser.avatar) + '" alt="" class="no-img-hover">'
                    + '<div class="ann-table-comment__body">'
                    + '<div class="ann-table-comment__name">' + escapeHtml(comment.user_name || 'User') + '</div>'
                    + '<div class="ann-table-comment__text">' + escapeHtml(comment.comment || '') + '</div>'
                    + '</div></div>';
            }

            function renderTableComments(row) {
                const comments = row.comments || [];
                return '<div class="ann-table-comments">'
                    + '<div class="ann-table-comment-list">' + comments.map(renderTableComment).join('') + '</div>'
                    + '<form class="ann-table-comment-form" data-id="' + row.id + '">'
                    + '<img src="' + escapeHtml(currentUser.avatar) + '" alt="" class="no-img-hover">'
                    + '<input type="text" maxlength="2000" placeholder="Write a comment…">'
                    + '<button type="submit">Comment</button>'
                    + '</form></div>';
            }

            function posterAvatar(row) {
                const url = row && row.posted_by_avatar ? row.posted_by_avatar : '';
                if (!url) return '';
                return '<img src="' + escapeHtml(url) + '" alt="" class="ann-user-avatar no-img-hover" loading="lazy">';
            }

            function renderImages(images) {
                if (!images || !images.length) return '<span class="text-muted">—</span>';
                return '<div class="ann-thumbs">' + images.map(function (img) {
                    return '<a href="' + escapeHtml(img.url) + '" target="_blank" rel="noopener">'
                        + '<img src="' + escapeHtml(img.url) + '" alt="Announcement image" loading="lazy">'
                        + '</a>';
                }).join('') + '</div>';
            }

            const columns = [
                {
                    title: 'Date',
                    field: 'announced_on',
                    width: 140,
                    hozAlign: 'center',
                    sorter: 'date',
                    formatter: function (cell) {
                        const row = cell.getRow().getData();
                        return '<span class="ann-date-pill">' + escapeHtml(row.announced_on_display || row.announced_on || '—') + '</span>';
                    },
                },
                {
                    title: 'Announcement',
                    field: 'message',
                    minWidth: 320,
                    formatter: function (cell) {
                        const row = cell.getRow().getData();
                        const text = row.message ? '<div class="ann-message">' + escapeHtml(row.message) + '</div>' : '';
                        const imgs = renderImages(row.images);
                        const imgHtml = (row.images && row.images.length) ? imgs : '';
                        if (!text && !imgHtml && !(row.comments || []).length) return '<span class="text-muted">—</span>';
                        return '<div class="ann-cell-with-user">'
                            + posterAvatar(row)
                            + '<div class="ann-cell-with-user__body">' + text + imgHtml + renderTableComments(row) + '</div>'
                            + '</div>';
                    },
                },
                {
                    title: 'Posted by',
                    field: 'posted_by',
                    width: 180,
                    formatter: function (cell) {
                        const row = cell.getRow().getData();
                        return '<span class="ann-posted-by">'
                            + posterAvatar(row)
                            + '<span>' + escapeHtml(row.posted_by || '—') + '</span>'
                            + '</span>';
                    },
                },
            ];

            if (canAnnounce) {
                columns.push({
                    title: 'Actions',
                    field: 'id',
                    width: 130,
                    hozAlign: 'center',
                    headerSort: false,
                    formatter: function (cell) {
                        const row = cell.getRow().getData();
                        const id = row.id;
                        const posted = !!row.posted;
                        return '<button type="button" class="ann-action-btn is-post' + (posted ? ' is-live' : '') + '" data-id="' + id + '" title="' + (posted ? 'Remove from notice board' : 'Post to 5 Core Announcements for all users') + '"><i class="ri-send-plane-fill"></i></button>'
                            + '<button type="button" class="ann-action-btn is-edit" data-id="' + id + '" title="Edit announcement"><i class="ri-pencil-line"></i></button>'
                            + '<button type="button" class="ann-action-btn is-delete" data-id="' + id + '" title="Delete from this page and all user pages"><i class="ri-delete-bin-line"></i></button>';
                    },
                });
            }

            columns.push({
                title: 'Viewed By',
                field: 'viewed_count',
                width: 150,
                hozAlign: 'center',
                headerSort: false,
                formatter: function (cell) {
                    const row = cell.getRow().getData();
                    const count = Number(row.viewed_count || 0);
                    return '<button type="button" class="ann-viewed-btn" data-id="' + row.id + '" title="Show viewers and non viewers">'
                        + '<i class="ri-eye-line"></i> Viewed By'
                        + '<span class="ann-viewed-btn__count">' + count + '</span>'
                        + '</button>';
                },
            });

            function renderPeople(list) {
                if (!list || !list.length) {
                    return '<div class="ann-viewers-empty">None</div>';
                }
                return list.map(function (person) {
                    return '<span class="ann-viewer-chip">' + escapeHtml(person.name) + '</span>';
                }).join('');
            }

            function closeViewersPanel() {
                document.getElementById('annViewersPanel').classList.remove('is-open');
                document.querySelectorAll('.ann-viewed-btn.is-active').forEach(function (btn) {
                    btn.classList.remove('is-active');
                });
            }

            function openViewersPanel(id, trigger) {
                const panel = document.getElementById('annViewersPanel');
                document.getElementById('annViewersList').innerHTML = '<div class="ann-viewers-empty">Loading…</div>';
                document.getElementById('annNonViewersList').innerHTML = '<div class="ann-viewers-empty">Loading…</div>';
                document.getElementById('annViewersCount').textContent = '';
                document.getElementById('annNonViewersCount').textContent = '';
                panel.classList.add('is-open');
                document.querySelectorAll('.ann-viewed-btn.is-active').forEach(function (btn) {
                    btn.classList.remove('is-active');
                });
                if (trigger) trigger.classList.add('is-active');
                panel.scrollIntoView({ behavior: 'smooth', block: 'start' });

                fetch(viewersBase + '/' + id + '/viewers', {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin',
                }).then(function (res) { return res.json(); }).then(function (json) {
                    document.getElementById('annViewersTitle').textContent =
                        'Viewed By' + (json.announced_on ? ' — ' + json.announced_on : '');
                    document.getElementById('annViewersCount').textContent = json.viewed_count != null ? '(' + json.viewed_count + ')' : '';
                    document.getElementById('annNonViewersCount').textContent = json.not_viewed_count != null ? '(' + json.not_viewed_count + ')' : '';
                    document.getElementById('annViewersList').innerHTML = renderPeople(json.viewers || []);
                    document.getElementById('annNonViewersList').innerHTML = renderPeople(json.non_viewers || []);
                }).catch(function () {
                    document.getElementById('annViewersList').innerHTML = '<div class="ann-viewers-empty">Could not load viewers.</div>';
                    document.getElementById('annNonViewersList').innerHTML = '';
                });
            }

            const table = new Tabulator('#announcementTable', {
                ajaxURL: dataUrl,
                ajaxResponse: function (_url, _params, response) {
                    return response.data || [];
                },
                layout: 'fitColumns',
                placeholder: 'No announcements yet.',
                columns: columns,
                initialSort: [{ column: 'announced_on', dir: 'desc' }],
            });

            document.getElementById('announcementTable').addEventListener('click', function (e) {
                const viewedBtn = e.target.closest('.ann-viewed-btn');
                if (!viewedBtn) return;
                e.stopPropagation();
                openViewersPanel(viewedBtn.getAttribute('data-id'), viewedBtn);
            });
            document.getElementById('announcementTable').addEventListener('submit', function (e) {
                const form = e.target.closest('.ann-table-comment-form');
                if (!form) return;
                e.preventDefault();
                e.stopPropagation();
                const id = form.getAttribute('data-id');
                const input = form.querySelector('input');
                const text = (input && input.value || '').trim();
                if (!text) {
                    if (input) input.focus();
                    return;
                }
                const btn = form.querySelector('button');
                if (btn) btn.disabled = true;
                $.ajax({
                    url: viewersBase + '/' + id + '/comments',
                    method: 'POST',
                    data: { _token: csrf, comment: text },
                }).done(function (json) {
                    if (!json || !json.comment) return;
                    const list = form.parentElement.querySelector('.ann-table-comment-list');
                    if (list) list.insertAdjacentHTML('beforeend', renderTableComment(json.comment));
                    if (input) input.value = '';
                    const row = table.getRows().map(function (r) { return r.getData(); })
                        .find(function (r) { return String(r.id) === String(id); });
                    if (row) {
                        row.comments = (row.comments || []).concat([json.comment]);
                    }
                }).fail(function (xhr) {
                    alert((xhr.responseJSON && xhr.responseJSON.message) || 'Could not add comment.');
                }).always(function () {
                    if (btn) btn.disabled = false;
                });
            });
            document.getElementById('annViewersCloseBtn').addEventListener('click', closeViewersPanel);

            if (!canAnnounce) {
                return;
            }

            const modalEl = document.getElementById('annModal');
            const modal = new bootstrap.Modal(modalEl);
            const form = document.getElementById('annForm');

            function todayYmd() {
                const d = new Date();
                const m = String(d.getMonth() + 1).padStart(2, '0');
                const day = String(d.getDate()).padStart(2, '0');
                return d.getFullYear() + '-' + m + '-' + day;
            }

            let keepImages = [];

            function renderKeepImages(images) {
                keepImages = (images || []).slice();
                const box = document.getElementById('annKeepImages');
                if (!keepImages.length) {
                    box.innerHTML = '';
                    return;
                }
                box.innerHTML = keepImages.map(function (img, index) {
                    return '<div class="ann-keep-item">'
                        + '<input type="hidden" name="keep_images[]" value="' + escapeHtml(img.path) + '">'
                        + '<img src="' + escapeHtml(img.url) + '" alt="Announcement image">'
                        + '<button type="button" class="ann-keep-item__remove" data-index="' + index + '" title="Remove image">&times;</button>'
                        + '</div>';
                }).join('');
            }

            function addKeepImage(img) {
                if (!img || !img.path) return;
                keepImages = keepImages.filter(function (item) { return item.path !== img.path; });
                keepImages.push(img);
                renderKeepImages(keepImages);
            }

            document.getElementById('annKeepImages').addEventListener('click', function (e) {
                const btn = e.target.closest('.ann-keep-item__remove');
                if (!btn) return;
                const index = parseInt(btn.getAttribute('data-index'), 10);
                if (Number.isNaN(index)) return;
                keepImages.splice(index, 1);
                renderKeepImages(keepImages);
            });

            function openCreate() {
                form.reset();
                document.getElementById('ann_id').value = '';
                document.getElementById('ann_date').value = todayYmd();
                document.getElementById('ann_ai_prompt').value = '';
                document.getElementById('annAiKindText').checked = true;
                document.getElementById('annModalTitle').textContent = 'New Announcement';
                renderKeepImages([]);
                modal.show();
            }

            function openEdit(row) {
                form.reset();
                document.getElementById('ann_id').value = row.id;
                document.getElementById('ann_date').value = row.announced_on || todayYmd();
                document.getElementById('ann_message').value = row.message || '';
                document.getElementById('ann_ai_prompt').value = '';
                document.getElementById('annAiKindText').checked = true;
                document.getElementById('annModalTitle').textContent = 'Edit Announcement';
                renderKeepImages(row.images || []);
                modal.show();
            }

            document.getElementById('annAddBtn').addEventListener('click', openCreate);

            document.getElementById('annEditPenBtn').addEventListener('click', function () {
                const area = document.getElementById('ann_message');
                area.focus();
                const len = area.value.length;
                area.setSelectionRange(len, len);
            });

            function runAnnouncementAi() {
                const prompt = (document.getElementById('ann_ai_prompt').value || '').trim();
                if (!prompt) {
                    document.getElementById('ann_ai_prompt').focus();
                    alert('Enter an AI prompt first.');
                    return;
                }
                let kind = document.querySelector('input[name="ann_ai_kind"]:checked')?.value || 'text';
                if (kind !== 'image' && /\b(image|img|poster|banner|graphic|picture|photo|illustration|flyer|artwork)\b/i.test(prompt)) {
                    kind = 'image';
                    document.getElementById('annAiKindImage').checked = true;
                }
                const aiBtn = document.getElementById('annAiPenBtn');
                const runBtn = document.getElementById('annAiRunBtn');
                aiBtn.disabled = true;
                runBtn.disabled = true;
                runBtn.innerHTML = kind === 'image'
                    ? '<i class="ri-loader-4-line me-1"></i> Creating image…'
                    : '<i class="ri-loader-4-line me-1"></i> Generating…';
                $.ajax({
                    url: aiUrl,
                    method: 'POST',
                    data: { _token: csrf, prompt: prompt, kind: kind },
                }).done(function (res) {
                    if (res.kind === 'image' && res.image) {
                        addKeepImage(res.image);
                        document.getElementById('annAiKindImage').checked = true;
                    } else if (res.text) {
                        document.getElementById('ann_message').value = res.text;
                    }
                }).fail(function (xhr) {
                    alert((xhr.responseJSON && xhr.responseJSON.message) || 'AI generate failed.');
                }).always(function () {
                    aiBtn.disabled = false;
                    runBtn.disabled = false;
                    runBtn.innerHTML = '<i class="ri-magic-line me-1"></i> Generate';
                });
            }

            document.getElementById('annAiPenBtn').addEventListener('click', runAnnouncementAi);
            document.getElementById('annAiRunBtn').addEventListener('click', runAnnouncementAi);

            function setTopbarAnnCount(count) {
                const btn = document.getElementById('annTopbarOpenBtn');
                if (!btn) return;
                const next = Math.max(0, parseInt(count, 10) || 0);
                btn.setAttribute('data-ann-count', String(next));
                btn.classList.toggle('has-posts', next > 0);
                const countEl = btn.querySelector('.topbar-ann-btn__count');
                if (countEl) countEl.textContent = String(next);
            }

            function updateTopbarAnnCount(delta) {
                const btn = document.getElementById('annTopbarOpenBtn');
                if (!btn || !delta) return;
                setTopbarAnnCount((parseInt(btn.getAttribute('data-ann-count'), 10) || 0) + delta);
            }

            document.getElementById('announcementTable').addEventListener('click', function (e) {
                const postBtn = e.target.closest('.ann-action-btn.is-post');
                const editBtn = e.target.closest('.ann-action-btn.is-edit');
                const delBtn = e.target.closest('.ann-action-btn.is-delete');
                if (postBtn) {
                    const wasLive = postBtn.classList.contains('is-live');
                    $.ajax({
                        url: postBase + '/' + postBtn.getAttribute('data-id'),
                        method: 'POST',
                        data: { _token: csrf },
                    }).done(function (res) {
                        table.replaceData();
                        if (res && res.unread_count != null) {
                            setTopbarAnnCount(res.unread_count);
                        } else {
                            updateTopbarAnnCount(res.posted ? (wasLive ? 0 : 1) : (wasLive ? -1 : 0));
                        }
                    }).fail(function (xhr) {
                        alert((xhr.responseJSON && xhr.responseJSON.message) || 'Could not post this announcement.');
                    });
                    return;
                }
                if (editBtn) {
                    const row = table.getRows().map(function (r) { return r.getData(); })
                        .find(function (r) { return String(r.id) === String(editBtn.getAttribute('data-id')); });
                    if (row) openEdit(row);
                    return;
                }
                if (delBtn) {
                    if (!confirm('Delete this announcement from this page and from every user notice board?')) return;
                    const delId = delBtn.getAttribute('data-id');
                    $.ajax({
                        url: deleteBase + '/' + delId,
                        method: 'POST',
                        data: { _token: csrf },
                    }).done(function (res) {
                        if (res && res.unread_count != null) {
                            const btn = document.getElementById('annTopbarOpenBtn');
                            if (btn) {
                                const next = Math.max(0, parseInt(res.unread_count, 10) || 0);
                                btn.setAttribute('data-ann-count', String(next));
                                btn.classList.toggle('has-posts', next > 0);
                                const countEl = btn.querySelector('.topbar-ann-btn__count');
                                if (countEl) countEl.textContent = String(next);
                            }
                        }
                        if (window.AnnouncementBoard && typeof window.AnnouncementBoard.remove === 'function') {
                            window.AnnouncementBoard.remove(delId);
                        }
                        table.replaceData();
                    }).fail(function (xhr) {
                        alert((xhr.responseJSON && xhr.responseJSON.message) || 'Could not delete.');
                    });
                }
            });

            function persistAnnouncement(options) {
                const opts = options || {};
                const id = document.getElementById('ann_id').value;
                const data = new FormData(form);
                data.delete('keep_images[]');
                keepImages.forEach(function (img) {
                    if (img && img.path) data.append('keep_images[]', img.path);
                });
                if (!id) {
                    data.delete('id');
                }
                const btn = document.getElementById('annSaveBtn');
                if (!opts.silent && btn) btn.disabled = true;
                return $.ajax({
                    url: id ? (updateBase + '/' + id) : storeUrl,
                    method: 'POST',
                    data: data,
                    processData: false,
                    contentType: false,
                    headers: { 'X-CSRF-TOKEN': csrf },
                }).done(function (res) {
                    if (res && res.id) {
                        document.getElementById('ann_id').value = res.id;
                        document.getElementById('annModalTitle').textContent = 'Edit Announcement';
                    }
                    if (res && res.row && res.row.images) {
                        renderKeepImages(res.row.images);
                    }
                    table.replaceData();
                    if (!opts.silent) modal.hide();
                }).fail(function (xhr) {
                    if (opts.silent) return;
                    let msg = 'Could not save.';
                    if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                    else if (xhr.responseJSON && xhr.responseJSON.errors) {
                        msg = Object.values(xhr.responseJSON.errors).flat().join('\n');
                    }
                    alert(msg);
                }).always(function () {
                    if (!opts.silent && btn) btn.disabled = false;
                });
            }

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                persistAnnouncement();
            });
        });
    </script>
@endsection
