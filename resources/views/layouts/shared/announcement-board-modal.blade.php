@auth
    <style>
        #annBoardModal .modal-dialog {
            max-width: min(920px, 96vw);
        }
        #annBoardModal .modal-content {
            border: 0;
            overflow: hidden;
            background: #6b4f2a;
        }
        #annBoardModal .ann-board-header {
            background: linear-gradient(180deg, #8b5a2b, #6b4220);
            color: #fff8e7;
            border-bottom: 4px solid #3f2a14;
            padding: 0.85rem 1.1rem;
        }
        #annBoardModal .ann-board-header .modal-title {
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        #annBoardModal .ann-board-header .btn-close {
            filter: invert(1);
            opacity: 0.85;
        }
        #annBoardBody {
            min-height: 360px;
            max-height: min(72vh, 720px);
            overflow: auto;
            padding: 1.1rem;
            background:
                radial-gradient(circle at 20% 20%, rgba(255,255,255,0.08), transparent 28%),
                repeating-linear-gradient(0deg, rgba(0,0,0,0.04) 0 2px, transparent 2px 10px),
                #c4a574;
        }
        .ann-board-empty {
            text-align: center;
            color: #4a3724;
            font-weight: 600;
            padding: 3rem 1rem;
        }
        .ann-board-card {
            background: #fffdf6;
            border: 1px solid rgba(80, 50, 20, 0.18);
            border-radius: 4px;
            box-shadow: 0 8px 18px rgba(50, 30, 10, 0.22);
            padding: 0.95rem 1rem 1rem;
            margin: 0 auto 1rem;
            max-width: 720px;
            position: relative;
        }
        .ann-board-card::before {
            content: '';
            position: absolute;
            top: 8px;
            left: 50%;
            width: 14px;
            height: 14px;
            margin-left: -7px;
            border-radius: 50%;
            background: radial-gradient(circle at 30% 30%, #fb7185, #be123c);
            box-shadow: 0 2px 4px rgba(0,0,0,0.25);
        }
        .ann-board-card__meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            font-size: 0.78rem;
            color: #7c5a32;
            font-weight: 700;
            margin: 0.35rem 0 0.55rem;
        }
        .ann-board-card__poster {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .ann-board-card__poster img {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid #e8dcc8;
            background: #fff;
        }
        .ann-board-card__body {
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        .ann-board-card__body-main {
            min-width: 0;
            flex: 1;
        }
        .ann-board-card__text {
            white-space: pre-wrap;
            color: #1f2937;
            font-size: 0.95rem;
            line-height: 1.5;
        }
        .ann-board-card__imgs {
            margin-top: 0.7rem;
            width: 100%;
            border-radius: 10px;
            overflow: hidden;
            background: #fff;
            border: 1px solid #efe6d6;
        }
        .ann-board-card__imgs a {
            display: block;
            width: 100%;
        }
        .ann-board-card__imgs img {
            display: block;
            width: 100%;
            height: auto;
            max-height: min(58vh, 520px);
            object-fit: contain;
            object-position: center;
        }
        .ann-board-card__imgs.has-many {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 8px;
            overflow: visible;
            background: transparent;
            border: 0;
        }
        .ann-board-card__imgs.has-many img {
            max-height: min(42vh, 360px);
            border-radius: 10px;
            border: 1px solid #efe6d6;
            background: #fff;
        }
        .ann-board-card__actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 0.75rem;
        }
        .ann-board-read-btn {
            border: 0;
            border-radius: 999px;
            background: #166534;
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            padding: 5px 12px;
            cursor: pointer;
        }
        .ann-board-read-btn:hover { background: #15803d; }
        .ann-board-read-btn:disabled { opacity: 0.65; cursor: wait; }
        .ann-board-mark-all {
            border: 0;
            background: transparent;
            color: #fff8e7;
            font-size: 12px;
            font-weight: 700;
            text-decoration: underline;
            text-underline-offset: 2px;
            padding: 0;
        }
        .ann-board-comments {
            margin-top: 0.85rem;
            padding-top: 0.75rem;
            border-top: 1px dashed rgba(80, 50, 20, 0.22);
        }
        .ann-board-comment {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            margin-bottom: 8px;
        }
        .ann-board-comment img,
        .ann-board-comment-form img {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid #e8dcc8;
            background: #fff;
            flex-shrink: 0;
        }
        .ann-board-comment__body {
            min-width: 0;
            flex: 1;
            background: #fff;
            border: 1px solid #efe6d6;
            border-radius: 10px;
            padding: 6px 10px;
        }
        .ann-board-comment__name {
            font-size: 12px;
            font-weight: 800;
            color: #6b4220;
        }
        .ann-board-comment__text {
            font-size: 13px;
            color: #1f2937;
            white-space: pre-wrap;
        }
        .ann-board-comment-form {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 8px;
        }
        .ann-board-comment-form input {
            flex: 1;
            min-width: 0;
            border: 1px solid #d6c4a3;
            border-radius: 999px;
            padding: 6px 12px;
            font-size: 13px;
        }
        .ann-board-comment-form button {
            border: 0;
            border-radius: 999px;
            background: #1d4ed8;
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            padding: 6px 12px;
        }
    </style>

    <div class="modal fade" id="annBoardModal" tabindex="-1" aria-labelledby="annBoardModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header ann-board-header">
                    <h5 class="modal-title mb-0" id="annBoardModalTitle">
                        <i class="ri-megaphone-fill me-2"></i>5 Core Announcements
                    </h5>
                    <button type="button" class="ann-board-mark-all d-none" id="annBoardMarkAllBtn">Mark all as read</button>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="annBoardBody">
                    <div class="ann-board-empty">Loading notice board…</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modalEl = document.getElementById('annBoardModal');
            const body = document.getElementById('annBoardBody');
            const openBtn = document.getElementById('annTopbarOpenBtn');
            if (!modalEl || !body || typeof bootstrap === 'undefined') return;

            const boardUrl = @json(route('announcements.board'));
            const commentBase = @json(url('announcements'));
            const readBase = @json(url('announcements/read'));
            const readAllUrl = @json(route('announcements.read-all'));
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const markAllBtn = document.getElementById('annBoardMarkAllBtn');
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
            const modal = new bootstrap.Modal(modalEl);

            function escapeHtml(s) {
                const d = document.createElement('div');
                d.textContent = s == null ? '' : String(s);
                return d.innerHTML;
            }

            function setTopbarCount(count) {
                if (!openBtn) return;
                const next = Math.max(0, parseInt(count, 10) || 0);
                openBtn.setAttribute('data-ann-count', String(next));
                openBtn.classList.toggle('has-posts', next > 0);
                const countEl = openBtn.querySelector('.topbar-ann-btn__count');
                if (countEl) countEl.textContent = String(next);
            }

            function renderComment(comment) {
                return '<div class="ann-board-comment">'
                    + '<img src="' + escapeHtml(comment.user_avatar || currentUser.avatar) + '" alt="" class="no-img-hover">'
                    + '<div class="ann-board-comment__body">'
                    + '<div class="ann-board-comment__name">' + escapeHtml(comment.user_name || 'User') + '</div>'
                    + '<div class="ann-board-comment__text">' + escapeHtml(comment.comment || '') + '</div>'
                    + '</div></div>';
            }

            function renderComments(row) {
                const comments = row.comments || [];
                return '<div class="ann-board-comments">'
                    + '<div class="ann-board-comment-list">' + comments.map(renderComment).join('') + '</div>'
                    + '<form class="ann-board-comment-form" data-id="' + row.id + '">'
                    + '<img src="' + escapeHtml(currentUser.avatar) + '" alt="" class="no-img-hover">'
                    + '<input type="text" maxlength="2000" placeholder="Write a comment…">'
                    + '<button type="submit">Comment</button>'
                    + '</form></div>';
            }

            function renderBoard(rows) {
                if (markAllBtn) markAllBtn.classList.toggle('d-none', !rows || !rows.length);
                if (!rows || !rows.length) {
                    body.innerHTML = '<div class="ann-board-empty">You are all caught up. No new announcements.</div>';
                    return;
                }
                body.innerHTML = rows.map(function (row) {
                    const text = row.message
                        ? '<div class="ann-board-card__text">' + escapeHtml(row.message) + '</div>'
                        : '';
                    const imageList = row.images || [];
                    const imgs = imageList.map(function (img) {
                        return '<a href="' + escapeHtml(img.url) + '" target="_blank" rel="noopener">'
                            + '<img src="' + escapeHtml(img.url) + '" alt="Announcement image">'
                            + '</a>';
                    }).join('');
                    const avatar = row.posted_by_avatar
                        ? '<img src="' + escapeHtml(row.posted_by_avatar) + '" alt="" class="no-img-hover">'
                        : '';
                    return '<article class="ann-board-card" data-id="' + row.id + '">'
                        + '<div class="ann-board-card__meta">'
                        + '<span>' + escapeHtml(row.announced_on_display || row.announced_on || '') + '</span>'
                        + '<span class="ann-board-card__poster">' + avatar + '<span>' + escapeHtml(row.posted_by || '') + '</span></span>'
                        + '</div>'
                        + '<div class="ann-board-card__body">'
                        + avatar
                        + '<div class="ann-board-card__body-main">'
                        + text
                        + (imgs ? '<div class="ann-board-card__imgs' + (imageList.length > 1 ? ' has-many' : '') + '">' + imgs + '</div>' : '')
                        + '</div></div>'
                        + renderComments(row)
                        + '<div class="ann-board-card__actions">'
                        + '<button type="button" class="ann-board-read-btn" data-id="' + row.id + '">Mark as read</button>'
                        + '</div>'
                        + '</article>';
                }).join('');
            }

            function markRead(id, btn) {
                if (btn) btn.disabled = true;
                fetch(readBase + '/' + id, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    credentials: 'same-origin',
                }).then(function (res) { return res.json(); }).then(function (json) {
                    const card = body.querySelector('.ann-board-card[data-id="' + id + '"]');
                    if (card) card.remove();
                    const left = body.querySelectorAll('.ann-board-card').length;
                    setTopbarCount(json.unread_count != null ? json.unread_count : left);
                    if (!left) renderBoard([]);
                }).catch(function () {
                    if (btn) btn.disabled = false;
                    alert('Could not mark this announcement as read.');
                });
            }

            function loadBoard() {
                body.innerHTML = '<div class="ann-board-empty">Loading notice board…</div>';
                fetch(boardUrl, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                    .then(function (res) { return res.json(); })
                    .then(function (json) {
                        renderBoard(json.data || []);
                        setTopbarCount(json.unread_count != null ? json.unread_count : 0);
                    })
                    .catch(function () {
                        body.innerHTML = '<div class="ann-board-empty">Could not load announcements.</div>';
                    });
            }

            body.addEventListener('click', function (e) {
                const readBtn = e.target.closest('.ann-board-read-btn');
                if (!readBtn) return;
                markRead(readBtn.getAttribute('data-id'), readBtn);
            });

            body.addEventListener('submit', function (e) {
                const form = e.target.closest('.ann-board-comment-form');
                if (!form) return;
                e.preventDefault();
                const id = form.getAttribute('data-id');
                const input = form.querySelector('input');
                const text = (input && input.value || '').trim();
                if (!text) {
                    if (input) input.focus();
                    return;
                }
                const btn = form.querySelector('button');
                if (btn) btn.disabled = true;
                fetch(commentBase + '/' + id + '/comments', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ comment: text }),
                }).then(function (res) { return res.json(); }).then(function (json) {
                    if (!json || !json.comment) {
                        throw new Error((json && json.message) || 'Could not comment.');
                    }
                    const list = form.parentElement.querySelector('.ann-board-comment-list');
                    if (list) list.insertAdjacentHTML('beforeend', renderComment(json.comment));
                    if (input) input.value = '';
                }).catch(function (err) {
                    alert(err && err.message ? err.message : 'Could not add comment.');
                }).finally(function () {
                    if (btn) btn.disabled = false;
                });
            });

            if (markAllBtn) {
                markAllBtn.addEventListener('click', function () {
                    markAllBtn.disabled = true;
                    fetch(readAllUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                        credentials: 'same-origin',
                    }).then(function (res) { return res.json(); }).then(function (json) {
                        renderBoard([]);
                        setTopbarCount(json.unread_count != null ? json.unread_count : 0);
                    }).catch(function () {
                        alert('Could not mark announcements as read.');
                    }).finally(function () {
                        markAllBtn.disabled = false;
                    });
                });
            }

            if (openBtn) {
                openBtn.addEventListener('click', function () {
                    loadBoard();
                    modal.show();
                });
            }

            window.AnnouncementBoard = {
                open: function () { loadBoard(); modal.show(); },
                remove: function (id) {
                    const card = body.querySelector('.ann-board-card[data-id="' + id + '"]');
                    if (card) card.remove();
                    if (!body.querySelector('.ann-board-card')) {
                        renderBoard([]);
                    }
                },
            };
        });
    </script>
@endauth
