@auth
<style>
    .invent-bot-root {
        position: fixed;
        inset: 0;
        z-index: 1090;
        pointer-events: none;
    }
    .invent-bot-root.is-open { pointer-events: auto; }
    .invent-bot-backdrop {
        position: absolute;
        inset: 0;
        background: rgba(15, 23, 42, 0.28);
        opacity: 0;
        transition: opacity .18s ease;
    }
    .invent-bot-root.is-open .invent-bot-backdrop { opacity: 1; }
    .invent-bot-panel {
        position: absolute;
        top: 12px;
        right: 12px;
        bottom: 12px;
        width: min(420px, calc(100vw - 24px));
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 18px 50px rgba(15, 23, 42, 0.22);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        transform: translateX(110%);
        transition: transform .2s ease;
    }
    .invent-bot-root.is-open .invent-bot-panel { transform: none; }
    .invent-bot-head {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 14px;
        background: #0f766e;
        color: #fff;
    }
    .invent-bot-head__avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        object-fit: cover;
        background: #fff;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        flex-shrink: 0;
    }
    .invent-bot-head__avatar img { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; background: #fff; }
    .invent-bot-head__text { flex: 1; min-width: 0; }
    .invent-bot-head__name { font-weight: 800; font-size: 15px; line-height: 1.2; }
    .invent-bot-head__sub { font-size: 11px; opacity: .85; }
    .invent-bot-head__close {
        border: 0;
        background: transparent;
        color: #fff;
        font-size: 22px;
        line-height: 1;
        padding: 0 4px;
    }
    .invent-bot-people {
        padding: 8px 10px 0;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
    }
    .invent-bot-people input {
        width: 100%;
        border: 1px solid #dbe3ee;
        border-radius: 8px;
        padding: 7px 10px;
        font-size: 13px;
    }
    .invent-bot-people__list {
        display: flex;
        gap: 6px;
        overflow-x: auto;
        padding: 8px 0;
    }
    .invent-bot-chip {
        border: 1px solid #dbe3ee;
        background: #fff;
        border-radius: 999px;
        padding: 4px 8px 4px 4px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 12px;
        color: #0f172a;
        white-space: nowrap;
        flex-shrink: 0;
    }
    .invent-bot-chip img, .invent-bot-chip__bot {
        width: 22px;
        height: 22px;
        border-radius: 50%;
        object-fit: cover;
        background: #fff;
    }
    .invent-bot-chip__bot {
        background: #0f766e;
        color: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 10px;
        font-weight: 800;
    }
    .invent-bot-chip.is-active { border-color: #0f766e; background: #ccfbf1; }
    .invent-bot-chip.is-unread { font-weight: 800; }
    .invent-bot-feed {
        flex: 1;
        overflow: auto;
        padding: 14px 12px;
        background: #f1f5f9;
    }
    .invent-bot-empty { text-align: center; color: #64748b; margin-top: 18vh; font-size: 13px; }
    .invent-bot-msg { display: flex; gap: 8px; margin-bottom: 12px; max-width: 100%; }
    .invent-bot-msg.is-mine { flex-direction: row-reverse; }
    .invent-bot-msg__avatar {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        object-fit: cover;
        background: #0f766e;
        color: #fff;
        font-size: 10px;
        font-weight: 800;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    img.invent-bot-msg__avatar { background: #fff; }
    .invent-bot-msg__bubble {
        max-width: 80%;
        background: #fff;
        border-radius: 12px;
        padding: 8px 10px;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
    }
    .invent-bot-msg.is-mine .invent-bot-msg__bubble { background: #ccfbf1; }
    .invent-bot-msg.is-bot .invent-bot-msg__bubble { background: #fff; border: 1px solid #d1fae5; }
    .invent-bot-msg__meta { font-size: 11px; color: #64748b; margin-bottom: 2px; }
    .invent-bot-msg__body { font-size: 13.5px; color: #0f172a; line-height: 1.45; word-break: break-word; }
    .invent-bot-msg__file { margin-top: 6px; }
    .invent-bot-msg__file img { max-width: 220px; border-radius: 8px; }
    .invent-chat-mention { color: #0f766e; background: #ccfbf1; border-radius: 4px; padding: 0 3px; font-weight: 700; }
    .invent-bot-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        padding: 8px 10px 0;
        background: #fff;
    }
    .invent-bot-actions button {
        border: 1px solid #99f6e4;
        background: #f0fdfa;
        color: #115e59;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 700;
        padding: 4px 10px;
    }
    .invent-bot-composer {
        padding: 10px;
        background: #fff;
        border-top: 1px solid #e2e8f0;
    }
    .invent-bot-composer textarea {
        width: 100%;
        border: 1px solid #dbe3ee;
        border-radius: 10px;
        min-height: 44px;
        max-height: 120px;
        padding: 8px 10px;
        resize: none;
        font-size: 14px;
    }
    .invent-bot-composer__row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-top: 6px;
        gap: 8px;
    }
    .invent-bot-composer__row button[type="submit"] {
        border: 0;
        background: #0f766e;
        color: #fff;
        border-radius: 8px;
        font-weight: 700;
        padding: 6px 12px;
    }
    .invent-bot-file { font-size: 11px; color: #64748b; }
</style>

<div class="invent-bot-root" id="inventChatRoot" hidden>
    <div class="invent-bot-backdrop" id="inventChatBackdrop"></div>
    <aside class="invent-bot-panel" role="dialog" aria-label="5 Core Bot">
        <div class="invent-bot-head">
            <div class="invent-bot-head__avatar" id="inventChatHeadAvatar"><img src="{{ asset('assets/images/5core-bot-logo.png') }}" alt="5 Core Bot"></div>
            <div class="invent-bot-head__text">
                <div class="invent-bot-head__name" id="inventChatHeadName">5 Core Bot</div>
                <div class="invent-bot-head__sub" id="inventChatHeadSub">Ask for a task, overdue, DAR, or SI</div>
            </div>
            <button type="button" class="invent-bot-head__close" id="inventChatClose" aria-label="Close">&times;</button>
        </div>
        <div class="invent-bot-people">
            <input type="search" id="inventChatSearch" placeholder="Message a teammate" autocomplete="off">
            <div class="invent-bot-people__list" id="inventChatPeople"></div>
        </div>
        <div class="invent-bot-feed" id="inventChatFeed">
            <div class="invent-bot-empty">Opening 5 Core Bot…</div>
        </div>
        <div class="invent-bot-actions" id="inventChatActions">
            <button type="button" data-send="overdue">Overdue</button>
            <button type="button" data-send="dar">DAR</button>
            <button type="button" data-send="si">SI</button>
            <button type="button" data-task="1">New task</button>
        </div>
        <form class="invent-bot-composer" id="inventChatComposer">
            <textarea id="inventChatBody" rows="2" placeholder="Ask 5 Core Bot…  e.g. Create a task Buy tape @aman"></textarea>
            <div class="invent-bot-composer__row">
                <label class="invent-bot-file">
                    <input type="file" id="inventChatFile" hidden>
                    <button type="button" class="btn btn-sm btn-light" id="inventChatAttach">Attach</button>
                    <span id="inventChatFileName"></span>
                </label>
                <button type="submit">Send</button>
            </div>
        </form>
    </aside>
</div>

<script>
(function () {
    if (window.InventChat) return;
    const csrf = @json(csrf_token());
    const root = document.getElementById('inventChatRoot');
    const feed = document.getElementById('inventChatFeed');
    const peopleEl = document.getElementById('inventChatPeople');
    const searchEl = document.getElementById('inventChatSearch');
    const bodyEl = document.getElementById('inventChatBody');
    const fileEl = document.getElementById('inventChatFile');
    const fileNameEl = document.getElementById('inventChatFileName');
    const composer = document.getElementById('inventChatComposer');
    const actions = document.getElementById('inventChatActions');
    let channels = [];
    let directory = [];
    let activeId = 0;
    let lastId = 0;
    let pollTimer = null;
    let opened = false;
    let taskMode = false;
    const meId = {{ (int) auth()->id() }};
    const botName = @json(\App\Support\ChatWorkspace::BOT_NAME);
    const botAvatar = @json(\App\Support\ChatWorkspace::botAvatarUrl());

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

    function visibleChannels() {
        return channels.filter(function (ch) { return ch.type === 'bot' || ch.type === 'dm'; });
    }

    function paintPeople(query) {
        const q = String(query || '').toLowerCase().trim();
        const chips = [];
        visibleChannels().forEach(function (ch) {
            chips.push({
                id: ch.id,
                type: ch.type,
                name: ch.name,
                avatar: ch.avatar,
                unread: ch.unread || 0,
                channel: true
            });
        });
        if (q) {
            directory.forEach(function (u) {
                const hay = ((u.name || '') + ' ' + (u.email || '')).toLowerCase();
                if (hay.indexOf(q) < 0) return;
                const existing = channels.find(function (c) { return c.type === 'dm' && Number(c.peer_id) === Number(u.id); });
                if (existing) return;
                chips.push({ userId: u.id, name: u.name, avatar: u.avatar, unread: 0, channel: false });
            });
        }
        peopleEl.innerHTML = chips.map(function (c) {
            const active = c.channel && c.id === activeId;
            const avatar = c.type === 'bot'
                ? '<img src="' + esc(c.avatar || botAvatar) + '" alt="' + esc(botName) + '">'
                : '<img src="' + esc(c.avatar || '') + '" alt="">';
            const badge = c.unread > 0 ? ' · ' + c.unread : '';
            return '<button type="button" class="invent-bot-chip' + (active ? ' is-active' : '') + (c.unread ? ' is-unread' : '') + '" data-channel="' + (c.channel ? c.id : '') + '" data-user="' + (c.userId || '') + '">' + avatar + esc(c.name) + badge + '</button>';
        }).join('');
        peopleEl.querySelectorAll('button').forEach(function (btn) {
            btn.addEventListener('click', async function () {
                if (btn.dataset.user) {
                    const data = await api('/chat/dms', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                        body: JSON.stringify({ user_id: Number(btn.dataset.user) })
                    });
                    searchEl.value = '';
                    await loadInbox();
                    openChannel(data.channel_id);
                    return;
                }
                openChannel(Number(btn.dataset.channel));
            });
        });
        updateTopbar(channels.reduce(function (n, ch) { return n + (ch.unread || 0); }, 0));
    }

    function updateTopbar(n) {
        const btn = document.getElementById('chatTopbarBtn');
        if (!btn) return;
        n = parseInt(n, 10) || 0;
        btn.classList.toggle('has-unread', n > 0);
        btn.dataset.chatUnread = String(n);
        const el = btn.querySelector('.topbar-chat-btn__count');
        if (el) el.textContent = n > 99 ? '99+' : String(n);
    }

    function setHead(ch) {
        const nameEl = document.getElementById('inventChatHeadName');
        const subEl = document.getElementById('inventChatHeadSub');
        const av = document.getElementById('inventChatHeadAvatar');
        const isBot = !ch || ch.type === 'bot';
        nameEl.textContent = isBot ? botName : (ch.name || 'Chat');
        subEl.textContent = isBot ? 'Ask for a task, overdue, DAR, or SI' : 'Direct message';
        if (isBot) {
            av.innerHTML = '<img src="' + esc(botAvatar) + '" alt="' + esc(botName) + '">';
        } else if (ch && ch.avatar) {
            av.innerHTML = '<img src="' + esc(ch.avatar) + '" alt="">';
        } else {
            av.innerHTML = '';
        }
        actions.style.display = isBot ? '' : 'none';
        bodyEl.placeholder = isBot
            ? 'Ask 5 Core Bot…  e.g. Create a task Buy tape @aman'
            : 'Message ' + (ch.name || '');
    }

    function appendMessages(rows, replace) {
        if (replace) feed.innerHTML = '';
        if (!rows.length && replace) {
            feed.innerHTML = '<div class="invent-bot-empty">Say hello, or tap Overdue / DAR / SI.</div>';
            return;
        }
        rows.forEach(function (m) {
            if (document.getElementById('invent-bot-msg-' + m.id)) return;
            const mine = !m.is_bot && Number(m.user_id) === meId;
            const wrap = document.createElement('div');
            wrap.id = 'invent-bot-msg-' + m.id;
            wrap.className = 'invent-bot-msg' + (m.is_bot ? ' is-bot' : '') + (mine ? ' is-mine' : '');
            const avatar = m.is_bot
                ? '<img class="invent-bot-msg__avatar" src="' + esc(m.avatar || botAvatar) + '" alt="' + esc(botName) + '">'
                : '<img class="invent-bot-msg__avatar" src="' + esc(m.avatar || '') + '" alt="">';
            let file = '';
            if (m.attachment_url) {
                file = m.attachment_is_image
                    ? '<div class="invent-bot-msg__file"><a href="' + esc(m.attachment_url) + '" target="_blank"><img src="' + esc(m.attachment_url) + '" alt=""></a></div>'
                    : '<div class="invent-bot-msg__file"><a href="' + esc(m.attachment_url) + '" target="_blank">' + esc(m.attachment_name || 'File') + '</a></div>';
            }
            wrap.innerHTML = avatar + '<div class="invent-bot-msg__bubble"><div class="invent-bot-msg__meta">' + esc(m.is_bot ? (m.name || botName) : (m.name || '')) + ' · ' + esc(m.created_label || '') + '</div><div class="invent-bot-msg__body">' + (m.html || esc(m.body || '')) + '</div>' + file + '</div>';
            feed.appendChild(wrap);
            lastId = Math.max(lastId, Number(m.id) || 0);
        });
        feed.scrollTop = feed.scrollHeight;
    }

    async function openChannel(id) {
        activeId = id;
        lastId = 0;
        const ch = channels.find(function (c) { return c.id === id; });
        setHead(ch);
        paintPeople(searchEl.value);
        const data = await api('/chat/channels/' + id + '/messages');
        appendMessages(data.messages || [], true);
    }

    async function loadInbox() {
        const data = await api('/chat/inbox');
        channels = data.channels || [];
        if (data.directory) directory = data.directory;
        paintPeople(searchEl.value);
        return channels;
    }

    async function sendBody(text) {
        if (!activeId) return;
        const fd = new FormData();
        const value = String(text == null ? bodyEl.value : text);
        fd.append('body', taskMode && value && !/^\s*task\b/i.test(value) ? ('task ' + value) : value);
        if (fileEl.files[0]) fd.append('file', fileEl.files[0]);
        const data = await api('/chat/channels/' + activeId + '/messages', { method: 'POST', body: fd });
        bodyEl.value = '';
        fileEl.value = '';
        fileNameEl.textContent = '';
        taskMode = false;
        bodyEl.placeholder = 'Ask 5 Core Bot…  e.g. Create a task Buy tape @aman';
        appendMessages(data.messages || [], false);
        loadInbox();
    }

    async function poll() {
        if (!opened || !activeId) return;
        try {
            const data = await api('/chat/channels/' + activeId + '/messages?after=' + lastId);
            if (data.messages && data.messages.length) appendMessages(data.messages, false);
            await loadInbox();
        } catch (e) {}
    }

    async function open() {
        root.hidden = false;
        requestAnimationFrame(function () { root.classList.add('is-open'); });
        opened = true;
        try {
            await loadInbox();
            const bot = channels.find(function (c) { return c.type === 'bot'; }) || visibleChannels()[0];
            if (bot && !activeId) await openChannel(bot.id);
            else if (activeId) await openChannel(activeId);
        } catch (err) {
            feed.innerHTML = '<div class="invent-bot-empty">' + esc(err.message) + '</div>';
        }
        if (!pollTimer) pollTimer = setInterval(poll, 4000);
        bodyEl.focus();
    }

    function close() {
        root.classList.remove('is-open');
        opened = false;
        setTimeout(function () { if (!opened) root.hidden = true; }, 200);
    }

    document.getElementById('inventChatClose').addEventListener('click', close);
    document.getElementById('inventChatBackdrop').addEventListener('click', close);
    document.getElementById('inventChatAttach').addEventListener('click', function () { fileEl.click(); });
    fileEl.addEventListener('change', function () {
        fileNameEl.textContent = fileEl.files[0] ? fileEl.files[0].name : '';
    });
    searchEl.addEventListener('input', function () { paintPeople(searchEl.value); });
    composer.addEventListener('submit', async function (e) {
        e.preventDefault();
        try { await sendBody(); } catch (err) { alert(err.message || 'Could not send.'); }
    });
    bodyEl.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            composer.requestSubmit();
        }
    });
    actions.addEventListener('click', function (e) {
        const btn = e.target.closest('button');
        if (!btn) return;
        if (btn.dataset.task) {
            taskMode = true;
            bodyEl.placeholder = 'Task title, then @name if needed';
            bodyEl.focus();
            return;
        }
        if (btn.dataset.send) sendBody(btn.dataset.send);
    });

    document.addEventListener('click', function (e) {
        const openBtn = e.target.closest('#chatTopbarBtn, .js-invent-chat-open');
        if (!openBtn) return;
        e.preventDefault();
        open();
    });

    window.InventChat = { open: open, close: close };

    if (location.pathname === '/chat') {
        open();
    }
})();
</script>
@endauth
