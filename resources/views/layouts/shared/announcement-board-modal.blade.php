@auth
    <style>
        #annBoardModal .modal-dialog { max-width: min(860px, 96vw); }
        #annBoardModal .modal-content {
            border: 0;
            overflow: hidden;
            border-radius: 22px;
            background: #0b1020;
            box-shadow: 0 30px 80px rgba(2, 6, 23, 0.45);
        }
        #annBoardModal .ann-board-header {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            background:
                radial-gradient(circle at 12% 0%, rgba(56, 189, 248, 0.35), transparent 42%),
                radial-gradient(circle at 90% 20%, rgba(167, 139, 250, 0.4), transparent 40%),
                linear-gradient(160deg, #111827, #0b1020);
            color: #f8fafc;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            padding: 1rem 1.1rem 0.95rem;
        }
        #annBoardModal .ann-board-header__copy { flex: 1; min-width: 0; }
        #annBoardModal .ann-board-header .modal-title {
            font-weight: 800;
            letter-spacing: -0.02em;
            font-size: 1.15rem;
            margin: 0;
        }
        .ann-board-header__date {
            margin-top: 2px;
            font-size: 12px;
            font-weight: 700;
            color: #7dd3fc;
        }
        .ann-board-header__motive {
            margin-top: 6px;
            font-size: 13px;
            font-weight: 600;
            color: #e2e8f0;
        }
        #annBoardModal .ann-board-header .btn-close {
            filter: invert(1);
            opacity: 0.85;
            margin-left: 0;
        }
        #annBoardBody {
            min-height: 360px;
            max-height: min(74vh, 760px);
            overflow: auto;
            padding: 1rem;
            background:
                radial-gradient(circle at 0% 0%, rgba(56, 189, 248, 0.16), transparent 32%),
                radial-gradient(circle at 100% 100%, rgba(167, 139, 250, 0.18), transparent 36%),
                #0f172a;
        }
        .ann-board-empty {
            text-align: center;
            color: #cbd5e1;
            font-weight: 600;
            padding: 3rem 1rem;
        }
        .ann-board-card {
            background: rgba(255,255,255,0.97);
            border: 1px solid rgba(255,255,255,0.5);
            border-radius: 20px;
            box-shadow: 0 16px 40px rgba(2, 6, 23, 0.28);
            padding: 1rem 1rem 0.9rem;
            margin: 0 auto 0.9rem;
            max-width: 760px;
        }
        .ann-board-card__top {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 0.75rem;
        }
        .ann-board-cal {
            width: 64px;
            flex-shrink: 0;
            border-radius: 16px;
            background: linear-gradient(180deg, #0ea5e9, #4f46e5);
            color: #fff;
            text-align: center;
            padding: 7px 4px 6px;
            line-height: 1.05;
        }
        .ann-board-cal__mon {
            display: block;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            opacity: 0.9;
        }
        .ann-board-cal__day {
            display: block;
            font-size: 22px;
            font-weight: 800;
            margin-top: 2px;
        }
        .ann-board-cal__when {
            display: block;
            margin-top: 2px;
            font-size: 10px;
            font-weight: 700;
            opacity: 0.92;
        }
        .ann-board-card__who {
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 0;
        }
        .ann-board-card__who img {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e0f2fe;
            background: #fff;
        }
        .ann-board-card__name {
            font-weight: 800;
            color: #0f172a;
            font-size: 14px;
        }
        .ann-board-card__when {
            color: #64748b;
            font-size: 12px;
            font-weight: 700;
        }
        .ann-board-motive {
            margin: 0 0 0.75rem;
            padding: 10px 12px 10px 14px;
            border-radius: 14px;
            background: linear-gradient(90deg, #f5f3ff, #ecfeff);
            border-left: 3px solid #7c3aed;
            color: #4c1d95;
            font-size: 14px;
            font-weight: 700;
            line-height: 1.4;
        }
        .ann-board-motive__label {
            display: block;
            font-size: 10px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #7c3aed;
            margin-bottom: 2px;
        }
        .ann-board-card__text {
            white-space: pre-wrap;
            color: #1e293b;
            font-size: 0.96rem;
            line-height: 1.55;
        }
        .ann-board-gallery {
            margin-top: 0.8rem;
            display: grid;
            gap: 8px;
        }
        .ann-board-gallery.has-many {
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        }
        .ann-board-media {
            position: relative;
            display: block;
            border-radius: 16px;
            overflow: hidden;
            background: #020617;
            border: 1px solid #e2e8f0;
        }
        .ann-board-media img {
            display: block;
            width: 100%;
            height: auto;
            max-height: min(52vh, 460px);
            object-fit: contain;
            background: #020617;
            cursor: zoom-in;
        }
        .ann-board-gallery.has-many .ann-board-media img { max-height: 280px; }
        .ann-board-media__tag {
            position: absolute;
            top: 10px;
            left: 10px;
            background: rgba(15, 23, 42, 0.82);
            color: #fff;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.06em;
            border-radius: 999px;
            padding: 3px 8px;
        }
        .ann-board-media.is-broken img { display: none; }
        .ann-board-media.is-broken::after {
            content: 'GIF could not load';
            display: block;
            color: #cbd5e1;
            text-align: center;
            padding: 28px 12px;
            font-size: 13px;
            font-weight: 700;
        }
        .ann-board-card__actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 0.75rem;
        }
        .ann-board-read-btn {
            border: 0;
            border-radius: 999px;
            background: #0f172a;
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            padding: 6px 14px;
            cursor: pointer;
        }
        .ann-board-read-btn:hover { background: #1e293b; }
        .ann-board-read-btn:disabled { opacity: 0.65; cursor: wait; }
        .ann-board-mark-all {
            border: 0;
            background: rgba(255,255,255,0.08);
            color: #e2e8f0;
            font-size: 12px;
            font-weight: 700;
            border-radius: 999px;
            padding: 6px 10px;
            white-space: nowrap;
        }
        .ann-board-mark-all:hover { background: rgba(255,255,255,0.16); }
        .ann-board-comments {
            margin-top: 0.85rem;
            padding-top: 0.75rem;
            border-top: 1px solid #e2e8f0;
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
            border: 1px solid #e2e8f0;
            background: #fff;
            flex-shrink: 0;
        }
        .ann-board-comment__body {
            min-width: 0;
            flex: 1;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 6px 10px;
        }
        .ann-board-comment__name { font-size: 12px; font-weight: 800; color: #0f172a; }
        .ann-board-comment__text { font-size: 13px; color: #1e293b; white-space: pre-wrap; }
        .ann-board-reacts { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
        .ann-board-reacts.is-post { margin-top: 12px; margin-bottom: 2px; }
        .ann-board-react {
            border: 1px solid #e2e8f0;
            background: #fff;
            border-radius: 999px;
            padding: 4px 8px;
            font-size: 16px;
            line-height: 1.2;
            cursor: pointer;
        }
        .ann-board-react.is-mine { background: #e0f2fe; border-color: #38bdf8; }
        .ann-board-react:disabled { opacity: 0.6; cursor: wait; }
        .ann-board-comment-form { display: flex; align-items: center; gap: 8px; margin-top: 8px; }
        .ann-board-comment-form input {
            flex: 1;
            min-width: 0;
            border: 1px solid #cbd5e1;
            border-radius: 999px;
            padding: 6px 12px;
            font-size: 13px;
        }
        .ann-board-comment-form button {
            border: 0;
            border-radius: 999px;
            background: #4f46e5;
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
                    <div class="ann-board-header__copy">
                        <h5 class="modal-title" id="annBoardModalTitle">Notice Board</h5>
                        <div class="ann-board-header__date" id="annBoardToday"></div>
                        <div class="ann-board-header__motive" id="annBoardMotive"></div>
                    </div>
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

            const commentEmojis = ['👍', '❤️', '😂', '😮', '😢', '🙏', '✅', '🔥'];

            function emojiButtons(reactions, dataAttr, dataValue, extraClass) {
                const byEmoji = {};
                (reactions || []).forEach(function (reaction) {
                    byEmoji[reaction.emoji] = reaction;
                });
                return '<div class="ann-board-reacts' + (extraClass ? ' ' + extraClass : '') + '" ' + dataAttr + '="' + dataValue + '">'
                    + commentEmojis.map(function (emoji) {
                        const reaction = byEmoji[emoji] || { count: 0, mine: false };
                        const count = reaction.count ? ' <span>' + reaction.count + '</span>' : '';
                        return '<button type="button" class="ann-board-react' + (reaction.mine ? ' is-mine' : '') + '" ' + dataAttr + '="' + dataValue + '" data-emoji="' + emoji + '" aria-label="Reply ' + emoji + '">' + emoji + count + '</button>';
                    }).join('')
                    + '</div>';
            }

            function renderReacts(comment) {
                return emojiButtons(comment.reactions, 'data-comment', comment.id, '');
            }

            function renderPostReacts(row) {
                return emojiButtons(row.reactions, 'data-ann', row.id, 'is-post');
            }

            function renderComment(comment) {
                return '<div class="ann-board-comment">'
                    + '<img src="' + escapeHtml(comment.user_avatar || currentUser.avatar) + '" alt="" data-hover-zoom>'
                    + '<div class="ann-board-comment__body">'
                    + '<div class="ann-board-comment__name">' + escapeHtml(comment.user_name || 'User') + '</div>'
                    + '<div class="ann-board-comment__text">' + escapeHtml(comment.comment || '') + '</div>'
                    + renderReacts(comment)
                    + '</div></div>';
            }

            function renderComments(row) {
                const comments = row.comments || [];
                return '<div class="ann-board-comments">'
                    + '<div class="ann-board-comment-list">' + comments.map(renderComment).join('') + '</div>'
                    + '<form class="ann-board-comment-form" data-id="' + row.id + '">'
                    + '<img src="' + escapeHtml(currentUser.avatar) + '" alt="" data-hover-zoom>'
                    + '<input type="text" maxlength="2000" placeholder="Write a comment…">'
                    + '<button type="submit">Comment</button>'
                    + '</form></div>';
            }

            const dailyMotives = [
                'Finish what you start.',
                'Clear updates keep everyone fast.',
                'Ask early. Fix early.',
                'One honest status beats ten guesses.',
                'Protect the team\'s time.',
                'Progress you can see is progress you can trust.',
                'Reply today so tomorrow stays light.',
                'Own the next step.',
                'Small fixes compound.',
                'Say the blocker out loud.',
                'Leave the work clearer than you found it.',
                'Momentum is a team habit.'
            ];

            function paintHeader() {
                const now = new Date();
                const dateEl = document.getElementById('annBoardToday');
                const motiveEl = document.getElementById('annBoardMotive');
                if (dateEl) {
                    dateEl.textContent = now.toLocaleDateString(undefined, {
                        weekday: 'long', day: 'numeric', month: 'short', year: 'numeric'
                    });
                }
                if (motiveEl) {
                    const start = new Date(now.getFullYear(), 0, 0);
                    const day = Math.floor((now - start) / 86400000);
                    motiveEl.textContent = dailyMotives[((day % dailyMotives.length) + dailyMotives.length) % dailyMotives.length];
                }
            }

            function relativeWhen(ymd, weekday) {
                if (!ymd) return weekday || '';
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                const bits = String(ymd).split('-');
                if (bits.length < 3) return weekday || '';
                const date = new Date(Number(bits[0]), Number(bits[1]) - 1, Number(bits[2]));
                const diff = Math.round((today - date) / 86400000);
                if (diff === 0) return 'Today';
                if (diff === 1) return 'Yesterday';
                return weekday || '';
            }

            function mediaFrame(url, isGif) {
                return '<a class="ann-board-media' + (isGif ? ' is-gif' : '') + '" data-hover-zoom href="' + escapeHtml(url) + '" target="_blank" rel="noopener">'
                    + (isGif ? '<span class="ann-board-media__tag">GIF</span>' : '')
                    + '<img src="' + escapeHtml(url) + '" alt="' + (isGif ? 'GIF' : 'Announcement image') + '">'
                    + '</a>';
            }

            function renderMedia(row) {
                const frames = [];
                if (row.gif_url) frames.push(mediaFrame(row.gif_url, true));
                (row.images || []).forEach(function (img) {
                    const gif = !!img.gif || /\.gif(\?|$)/i.test(img.url || '');
                    frames.push(mediaFrame(img.url, gif));
                });
                if (!frames.length) return '';
                return '<div class="ann-board-gallery' + (frames.length > 1 ? ' has-many' : '') + '">' + frames.join('') + '</div>';
            }

            function renderBoard(rows) {
                paintHeader();
                if (markAllBtn) markAllBtn.classList.toggle('d-none', !rows || !rows.length);
                if (!rows || !rows.length) {
                    body.innerHTML = '<div class="ann-board-empty">You are all caught up. No new announcements.</div>';
                    return;
                }
                body.innerHTML = rows.map(function (row) {
                    const text = row.message
                        ? '<div class="ann-board-card__text">' + escapeHtml(row.message) + '</div>'
                        : '';
                    const motive = row.motivation
                        ? '<blockquote class="ann-board-motive"><span class="ann-board-motive__label">Motivation</span>' + escapeHtml(row.motivation) + '</blockquote>'
                        : '';
                    const avatar = row.posted_by_avatar
                        ? '<img src="' + escapeHtml(row.posted_by_avatar) + '" alt="" data-hover-zoom>'
                        : '';
                    const when = relativeWhen(row.announced_on, row.date_weekday);
                    return '<article class="ann-board-card" data-id="' + row.id + '">'
                        + '<div class="ann-board-card__top">'
                        + '<div class="ann-board-cal">'
                        + '<span class="ann-board-cal__mon">' + escapeHtml(row.date_month || '') + '</span>'
                        + '<span class="ann-board-cal__day">' + escapeHtml(row.date_day || '') + '</span>'
                        + '<span class="ann-board-cal__when">' + escapeHtml(when) + '</span>'
                        + '</div>'
                        + '<div class="ann-board-card__who">' + avatar
                        + '<div><div class="ann-board-card__name">' + escapeHtml(row.posted_by || '') + '</div>'
                        + '<div class="ann-board-card__when">' + escapeHtml(row.announced_on_display || row.announced_on || '') + '</div></div>'
                        + '</div></div>'
                        + motive
                        + text
                        + renderMedia(row)
                        + renderPostReacts(row)
                        + renderComments(row)
                        + '<div class="ann-board-card__actions">'
                        + '<button type="button" class="ann-board-read-btn" data-id="' + row.id + '">Mark as read</button>'
                        + '</div>'
                        + '</article>';
                }).join('');
                body.querySelectorAll('.ann-board-media img').forEach(function (img) {
                    img.addEventListener('error', function () {
                        const frame = img.closest('.ann-board-media');
                        if (frame) frame.classList.add('is-broken');
                    });
                });
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

            function reactToComment(btn) {
                const commentId = btn.getAttribute('data-comment');
                const announcementId = btn.getAttribute('data-ann');
                const emoji = btn.getAttribute('data-emoji');
                const wrap = btn.parentElement;
                if (wrap) {
                    wrap.querySelectorAll('button').forEach(function (button) { button.disabled = true; });
                }
                const url = announcementId
                    ? commentBase + '/' + announcementId + '/react'
                    : commentBase + '/comments/' + commentId + '/react';
                fetch(url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ emoji: emoji }),
                }).then(function (res) { return res.json().then(function (json) { return { ok: res.ok, json: json }; }); })
                    .then(function (result) {
                        if (!result.ok || !result.json) {
                            throw new Error((result.json && result.json.message) || 'Could not save emoji reply.');
                        }
                        if (announcementId) {
                            if (wrap) wrap.outerHTML = renderPostReacts({ id: announcementId, reactions: result.json.reactions || [] });
                            return;
                        }
                        if (!result.json.comment) {
                            throw new Error((result.json && result.json.message) || 'Could not save emoji reply.');
                        }
                        if (wrap) wrap.outerHTML = renderReacts(result.json.comment);
                    }).catch(function (err) {
                        if (wrap) wrap.querySelectorAll('button').forEach(function (button) { button.disabled = false; });
                        alert(err && err.message ? err.message : 'Could not save emoji reply.');
                    });
            }

            body.addEventListener('click', function (e) {
                const reactBtn = e.target.closest('.ann-board-react');
                if (reactBtn) {
                    reactToComment(reactBtn);
                    return;
                }
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
                    paintHeader();
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
