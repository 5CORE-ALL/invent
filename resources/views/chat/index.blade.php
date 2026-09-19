@extends('layouts.vertical', ['title' => 'Chat', 'sidenav' => 'condensed', 'skipHighcharts' => true])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        .content-page .content .container-fluid { padding-bottom: 0; }
        .invent-chat {
            display: flex;
            height: calc(100vh - 150px);
            min-height: 520px;
            margin: 0 -0.75rem 0.75rem;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
        }
        .invent-chat-nav {
            width: 280px;
            flex-shrink: 0;
            background: #1a2332;
            color: #cbd5e1;
            display: flex;
            flex-direction: column;
        }
        .invent-chat-nav__head {
            padding: 16px 16px 12px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .invent-chat-nav__title {
            margin: 0;
            color: #fff;
            font-size: 1.05rem;
            font-weight: 800;
        }
        .invent-chat-nav__hint {
            margin: 4px 0 0;
            font-size: 11px;
            color: #94a3b8;
        }
        .invent-chat-search {
            margin: 10px 12px 0;
        }
        .invent-chat-search input {
            width: 100%;
            border: 0;
            border-radius: 8px;
            background: #243044;
            color: #e2e8f0;
            padding: 8px 10px;
            font-size: 13px;
        }
        .invent-chat-search input::placeholder { color: #7c8aa0; }
        .invent-chat-nav__scroll {
            flex: 1;
            overflow: auto;
            padding: 10px 0 16px;
        }
        .invent-chat-sec {
            padding: 10px 16px 4px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .06em;
            text-transform: uppercase;
            color: #7c8aa0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .invent-chat-sec button {
            border: 0;
            background: transparent;
            color: #94a3b8;
            padding: 0;
            line-height: 1;
        }
        .invent-chat-item {
            display: flex;
            align-items: center;
            gap: 8px;
            width: 100%;
            border: 0;
            background: transparent;
            color: #cbd5e1;
            text-align: left;
            padding: 7px 16px;
            font-size: 13.5px;
        }
        .invent-chat-item:hover,
        .invent-chat-item.is-active { background: #2a3a52; color: #fff; }
        .invent-chat-item.is-unread { font-weight: 800; color: #fff; }
        .invent-chat-item__hash { color: #7c8aa0; width: 14px; }
        .invent-chat-item img {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            object-fit: cover;
        }
        .invent-chat-item__name { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .invent-chat-item__badge {
            min-width: 18px;
            height: 18px;
            padding: 0 5px;
            border-radius: 999px;
            background: #ef4444;
            color: #fff;
            font-size: 10px;
            font-weight: 800;
            line-height: 18px;
            text-align: center;
        }
        .invent-chat-main {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            background: #f8fafc;
        }
        .invent-chat-main__head {
            padding: 12px 18px;
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
        }
        .invent-chat-main__name { margin: 0; font-size: 16px; font-weight: 800; color: #0f172a; }
        .invent-chat-main__topic { margin: 2px 0 0; font-size: 12px; color: #64748b; }
        .invent-chat-feed {
            flex: 1;
            overflow: auto;
            padding: 16px 20px;
        }
        .invent-chat-empty {
            color: #64748b;
            text-align: center;
            margin-top: 20vh;
        }
        .invent-chat-msg {
            display: flex;
            gap: 10px;
            margin-bottom: 14px;
        }
        .invent-chat-msg.is-bot .invent-chat-msg__avatar {
            background: #0f766e;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 11px;
        }
        .invent-chat-msg__avatar {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            object-fit: cover;
            flex-shrink: 0;
            background: #e2e8f0;
        }
        .invent-chat-msg__meta { font-size: 12px; color: #64748b; }
        .invent-chat-msg__meta strong { color: #0f172a; font-size: 13.5px; margin-right: 6px; }
        .invent-chat-msg__body {
            margin-top: 2px;
            color: #1e293b;
            font-size: 14px;
            line-height: 1.45;
            word-break: break-word;
        }
        .invent-chat-mention {
            color: #0f766e;
            background: #ccfbf1;
            border-radius: 4px;
            padding: 0 3px;
            font-weight: 700;
        }
        .invent-chat-msg__file { margin-top: 8px; }
        .invent-chat-msg__file img { max-width: 320px; max-height: 240px; border-radius: 8px; }
        .invent-chat-composer {
            padding: 12px 16px 16px;
            background: #fff;
            border-top: 1px solid #e2e8f0;
        }
        .invent-chat-composer__box {
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 8px 10px;
            background: #fff;
        }
        .invent-chat-composer textarea {
            width: 100%;
            border: 0;
            resize: none;
            min-height: 44px;
            max-height: 140px;
            outline: none;
            font-size: 14px;
        }
        .invent-chat-composer__row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }
        .invent-chat-composer__hint { font-size: 11px; color: #94a3b8; }
        .invent-chat-send {
            border: 0;
            border-radius: 8px;
            background: #0f766e;
            color: #fff;
            font-weight: 700;
            padding: 6px 12px;
        }
        .invent-chat-pick {
            position: relative;
        }
        .invent-chat-pick__list {
            display: none;
            position: absolute;
            left: 12px;
            right: 12px;
            top: 74px;
            z-index: 5;
            background: #fff;
            color: #0f172a;
            border-radius: 8px;
            max-height: 240px;
            overflow: auto;
            box-shadow: 0 12px 30px rgba(0,0,0,.2);
        }
        .invent-chat-pick__list.is-open { display: block; }
        .invent-chat-pick__list button {
            display: flex;
            width: 100%;
            border: 0;
            background: #fff;
            text-align: left;
            padding: 8px 10px;
            gap: 8px;
            align-items: center;
        }
        .invent-chat-pick__list button:hover { background: #f1f5f9; }
        .invent-chat-pick__list img { width: 22px; height: 22px; border-radius: 50%; }
        @media (max-width: 768px) {
            .invent-chat { flex-direction: column; height: calc(100vh - 120px); }
            .invent-chat-nav { width: 100%; height: 38%; }
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared/page-title', ['page_title' => 'Chat', 'sub_title' => 'Team'])

    <div class="invent-chat" id="inventChat">
        <aside class="invent-chat-nav">
            <div class="invent-chat-nav__head">
                <h1 class="invent-chat-nav__title">5Core Chat</h1>
                <p class="invent-chat-nav__hint">Channels, DMs, and @invent</p>
            </div>
            <div class="invent-chat-search invent-chat-pick">
                <input type="search" id="chatPeopleSearch" placeholder="Find people or start a DM" autocomplete="off">
                <div class="invent-chat-pick__list" id="chatPeopleList"></div>
            </div>
            <div class="invent-chat-nav__scroll" id="chatNavScroll">
                <div class="invent-chat-sec">Invent Bot</div>
                <div id="chatBotList"></div>
                <div class="invent-chat-sec">
                    <span>Channels</span>
                    @if ($canManageChannels)
                        <button type="button" id="chatNewChannelBtn" title="New channel">+</button>
                    @endif
                </div>
                <div id="chatChannelList"></div>
                <div class="invent-chat-sec">Direct messages</div>
                <div id="chatDmList"></div>
            </div>
        </aside>
        <section class="invent-chat-main">
            <div class="invent-chat-main__head">
                <h2 class="invent-chat-main__name" id="chatRoomName">Select a conversation</h2>
                <p class="invent-chat-main__topic" id="chatRoomTopic">Try /task, /overdue, /dar, /si, or /help</p>
            </div>
            <div class="invent-chat-feed" id="chatFeed">
                <div class="invent-chat-empty">Pick Invent Bot, a channel, or a teammate to start.</div>
            </div>
            <form class="invent-chat-composer" id="chatComposer" hidden>
                <div class="invent-chat-composer__box">
                    <textarea id="chatBody" rows="2" placeholder="Message…  /task Buy tape @name high"></textarea>
                    <div class="invent-chat-composer__row">
                        <label class="invent-chat-composer__hint">
                            <input type="file" id="chatFile" hidden>
                            <button type="button" class="btn btn-sm btn-light" id="chatAttachBtn">Attach</button>
                            <span id="chatFileName"></span>
                            · Enter to send
                        </label>
                        <button type="submit" class="invent-chat-send">Send</button>
                    </div>
                </div>
            </form>
        </section>
    </div>

    @if ($canManageChannels)
        <div class="modal fade" id="chatChannelModal" tabindex="-1">
            <div class="modal-dialog">
                <form class="modal-content" id="chatChannelForm">
                    <div class="modal-header">
                        <h5 class="modal-title">New channel</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label">Name</label>
                        <input class="form-control mb-2" name="name" required maxlength="80" placeholder="ops-alerts">
                        <label class="form-label">Topic</label>
                        <input class="form-control mb-2" name="topic" maxlength="255">
                        <label class="form-label">Type</label>
                        <select class="form-select mb-2" name="type">
                            <option value="public">Public — everyone</option>
                            <option value="private">Private — chosen people</option>
                        </select>
                        <label class="form-label">Members (private)</label>
                        <select class="form-select" name="member_ids[]" id="chatChannelMembers" multiple size="8"></select>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-success">Create</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection

@section('script')
<script>
(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const directory = @json($directory);
    const startChannel = Number(new URLSearchParams(location.search).get('channel') || 0);
    const lists = {
        bot: document.getElementById('chatBotList'),
        channel: document.getElementById('chatChannelList'),
        dm: document.getElementById('chatDmList'),
    };
    const feed = document.getElementById('chatFeed');
    const composer = document.getElementById('chatComposer');
    const bodyEl = document.getElementById('chatBody');
    const fileEl = document.getElementById('chatFile');
    const fileNameEl = document.getElementById('chatFileName');
    const peopleSearch = document.getElementById('chatPeopleSearch');
    const peopleList = document.getElementById('chatPeopleList');
    const memberSelect = document.getElementById('chatChannelMembers');
    let channels = [];
    let activeId = 0;
    let lastId = 0;
    let pollTimer = null;

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    async function api(url, opts) {
        const res = await fetch(url, Object.assign({
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }, opts || {}));
        if (!res.ok) {
            let msg = 'Request failed';
            try { const j = await res.json(); msg = j.message || msg; } catch (e) {}
            throw new Error(msg);
        }
        return res.json();
    }

    function renderNav() {
        lists.bot.innerHTML = '';
        lists.channel.innerHTML = '';
        lists.dm.innerHTML = '';
        channels.forEach(function (ch) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'invent-chat-item' + (ch.id === activeId ? ' is-active' : '') + (ch.unread > 0 ? ' is-unread' : '');
            btn.dataset.id = String(ch.id);
            const prefix = ch.type === 'dm' || ch.type === 'bot' ? '' : '<span class="invent-chat-item__hash">#</span>';
            const avatar = ch.avatar ? '<img src="' + esc(ch.avatar) + '" alt="">' : (ch.type === 'bot' ? '<i class="ri-robot-2-line"></i>' : '');
            const badge = ch.unread > 0 ? '<span class="invent-chat-item__badge">' + ch.unread + '</span>' : '';
            btn.innerHTML = avatar + prefix + '<span class="invent-chat-item__name">' + esc(ch.name) + '</span>' + badge;
            btn.addEventListener('click', function () { openChannel(ch.id); });
            if (ch.type === 'bot') lists.bot.appendChild(btn);
            else if (ch.type === 'dm') lists.dm.appendChild(btn);
            else lists.channel.appendChild(btn);
        });
        updateTopbarUnread(channels.reduce(function (n, ch) { return n + (ch.unread || 0); }, 0));
    }

    function updateTopbarUnread(n) {
        const btn = document.getElementById('chatTopbarBtn');
        if (!btn) return;
        const count = btn.querySelector('.topbar-chat-btn__count');
        btn.classList.toggle('has-unread', n > 0);
        btn.dataset.chatUnread = String(n);
        if (count) count.textContent = n > 99 ? '99+' : String(n);
    }

    function appendMessages(rows, replace) {
        if (replace) feed.innerHTML = '';
        if (!rows.length && replace) {
            feed.innerHTML = '<div class="invent-chat-empty">No messages yet. Say hello or try /help</div>';
            return;
        }
        rows.forEach(function (m) {
            if (document.getElementById('chat-msg-' + m.id)) return;
            const wrap = document.createElement('div');
            wrap.id = 'chat-msg-' + m.id;
            wrap.className = 'invent-chat-msg' + (m.is_bot ? ' is-bot' : '');
            const avatar = m.is_bot
                ? '<div class="invent-chat-msg__avatar">@i</div>'
                : '<img class="invent-chat-msg__avatar" src="' + esc(m.avatar || '') + '" alt="">';
            let file = '';
            if (m.attachment_url) {
                file = m.attachment_is_image
                    ? '<div class="invent-chat-msg__file"><a href="' + esc(m.attachment_url) + '" target="_blank"><img src="' + esc(m.attachment_url) + '" alt=""></a></div>'
                    : '<div class="invent-chat-msg__file"><a href="' + esc(m.attachment_url) + '" target="_blank">' + esc(m.attachment_name || 'File') + '</a></div>';
            }
            wrap.innerHTML = avatar + '<div><div class="invent-chat-msg__meta"><strong>' + esc(m.name) + '</strong>' + esc(m.created_label || '') + '</div><div class="invent-chat-msg__body">' + (m.html || esc(m.body || '')) + '</div>' + file + '</div>';
            feed.appendChild(wrap);
            lastId = Math.max(lastId, Number(m.id) || 0);
        });
        feed.scrollTop = feed.scrollHeight;
    }

    async function openChannel(id) {
        activeId = id;
        lastId = 0;
        const ch = channels.find(function (c) { return c.id === id; });
        document.getElementById('chatRoomName').textContent = ch ? ((ch.type === 'public' || ch.type === 'private') ? '#' + ch.name : ch.name) : 'Chat';
        document.getElementById('chatRoomTopic').textContent = (ch && ch.topic) ? ch.topic : 'Try /task, /overdue, /dar, /si, or /help';
        composer.hidden = false;
        renderNav();
        const data = await api('/chat/channels/' + id + '/messages');
        appendMessages(data.messages || [], true);
        history.replaceState(null, '', '/chat?channel=' + id);
        loadInbox(false);
    }

    async function loadInbox(openFirst) {
        const data = await api('/chat/inbox');
        channels = data.channels || [];
        renderNav();
        if (openFirst && !activeId && channels.length) {
            const preferred = channels.find(function (c) { return c.id === startChannel; })
                || channels.find(function (c) { return c.type === 'bot'; })
                || channels[0];
            await openChannel(preferred.id);
        }
    }

    async function poll() {
        if (!activeId) return;
        try {
            const data = await api('/chat/channels/' + activeId + '/messages?after=' + lastId);
            if (data.messages && data.messages.length) appendMessages(data.messages, false);
            await loadInbox(false);
        } catch (e) {}
    }

    composer.addEventListener('submit', async function (e) {
        e.preventDefault();
        if (!activeId) return;
        const fd = new FormData();
        fd.append('body', bodyEl.value);
        if (fileEl.files[0]) fd.append('file', fileEl.files[0]);
        try {
            const data = await api('/chat/channels/' + activeId + '/messages', { method: 'POST', body: fd });
            bodyEl.value = '';
            fileEl.value = '';
            fileNameEl.textContent = '';
            appendMessages(data.messages || [], false);
            loadInbox(false);
        } catch (err) {
            alert(err.message || 'Could not send.');
        }
    });

    bodyEl.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            composer.requestSubmit();
        }
    });

    document.getElementById('chatAttachBtn').addEventListener('click', function () { fileEl.click(); });
    fileEl.addEventListener('change', function () {
        fileNameEl.textContent = fileEl.files[0] ? fileEl.files[0].name : '';
    });

    function renderPeople(q) {
        const query = String(q || '').toLowerCase().trim();
        const rows = directory.filter(function (u) {
            if (!query) return false;
            return (u.name || '').toLowerCase().indexOf(query) >= 0 || (u.email || '').toLowerCase().indexOf(query) >= 0;
        }).slice(0, 12);
        peopleList.innerHTML = rows.map(function (u) {
            return '<button type="button" data-user="' + u.id + '"><img src="' + esc(u.avatar || '') + '" alt=""><span>' + esc(u.name) + '</span></button>';
        }).join('');
        peopleList.classList.toggle('is-open', rows.length > 0);
        peopleList.querySelectorAll('button').forEach(function (btn) {
            btn.addEventListener('click', async function () {
                const data = await api('/chat/dms', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({ user_id: Number(btn.dataset.user) })
                });
                peopleSearch.value = '';
                peopleList.classList.remove('is-open');
                await loadInbox(false);
                openChannel(data.channel_id);
            });
        });
    }

    peopleSearch.addEventListener('input', function () { renderPeople(peopleSearch.value); });
    document.addEventListener('click', function (e) {
        if (!e.target.closest('.invent-chat-pick')) peopleList.classList.remove('is-open');
    });

    if (memberSelect) {
        directory.forEach(function (u) {
            const opt = document.createElement('option');
            opt.value = u.id;
            opt.textContent = u.name;
            memberSelect.appendChild(opt);
        });
    }

    const newBtn = document.getElementById('chatNewChannelBtn');
    if (newBtn && window.bootstrap) {
        newBtn.addEventListener('click', function () {
            window.bootstrap.Modal.getOrCreateInstance(document.getElementById('chatChannelModal')).show();
        });
    }

    const channelForm = document.getElementById('chatChannelForm');
    if (channelForm) {
        channelForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            const fd = new FormData(channelForm);
            const data = await api('/chat/channels', { method: 'POST', body: fd });
            window.bootstrap.Modal.getOrCreateInstance(document.getElementById('chatChannelModal')).hide();
            channelForm.reset();
            await loadInbox(false);
            openChannel(data.channel_id);
        });
    }

    loadInbox(true).catch(function (err) {
        feed.innerHTML = '<div class="invent-chat-empty">' + esc(err.message) + '</div>';
    });
    pollTimer = setInterval(poll, 3000);
})();
</script>
@endsection
