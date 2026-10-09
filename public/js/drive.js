/* 5Core Drive front-end */
(function () {
    'use strict';

    const C = window.DRIVE_CONFIG;
    const $ = (s, r = document) => r.querySelector(s);
    const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));

    const CAT = {
        folder: { icon: 'ri-folder-3-fill', color: '#f5b400', label: 'Folder' },
        image: { icon: 'ri-image-2-fill', color: '#ef4444', label: 'Image' },
        video: { icon: 'ri-movie-2-fill', color: '#8b5cf6', label: 'Video' },
        audio: { icon: 'ri-music-2-fill', color: '#ec4899', label: 'Audio' },
        pdf: { icon: 'ri-file-pdf-2-fill', color: '#dc2626', label: 'PDF' },
        doc: { icon: 'ri-file-word-2-fill', color: '#2563eb', label: 'Document' },
        sheet: { icon: 'ri-file-excel-2-fill', color: '#16a34a', label: 'Spreadsheet' },
        slide: { icon: 'ri-file-ppt-2-fill', color: '#f97316', label: 'Presentation' },
        archive: { icon: 'ri-file-zip-fill', color: '#a16207', label: 'Archive' },
        text: { icon: 'ri-file-text-fill', color: '#64748b', label: 'Text' },
        other: { icon: 'ri-file-3-fill', color: '#94a3b8', label: 'File' },
    };
    const STORAGE_COLORS = { images: '#ef4444', videos: '#8b5cf6', documents: '#3b82f6', audio: '#ec4899', other: '#94a3b8' };
    const FOLDER_COLORS = ['#f5b400', '#ef4444', '#f97316', '#22c55e', '#14b8a6', '#06b6d4', '#3b82f6', '#6366f1', '#8b5cf6', '#ec4899', '#64748b'];
    const VIEW_TITLES = { my: 'My Drive', shared: 'Shared with me', shared_by_me: 'Shared by me', recent: 'Recent', starred: 'Starred', trash: 'Trash', search: 'Search results' };
    const DOC_CATS = ['doc', 'text', 'slide', 'pdf', 'sheet'];

    const S = {
        view: 'my',
        folder: null,
        folderInfo: null,
        root: 'my',
        items: [],
        breadcrumbs: [],
        selected: new Set(),
        anchor: null,
        filter: 'all',
        q: '',
        sort: readJson('drvSort', { key: 'name', dir: 'asc' }),
        layout: localStorage.getItem('drvLayout') || 'grid',
        details: localStorage.getItem('drvDetails') === '1',
        clipboard: null,
        loadSeq: 0,
        versionTarget: null,
    };

    const app = $('#drive-app');
    const content = $('#drv-content');

    /* ================================================================== */
    /* Utilities                                                           */
    /* ================================================================== */

    function readJson(key, fallback) {
        try { return JSON.parse(localStorage.getItem(key)) || fallback; } catch (e) { return fallback; }
    }
    function esc(v) {
        return String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }
    function fmtSize(b) {
        b = Number(b) || 0;
        if (b < 1024) return b + ' B';
        const u = ['KB', 'MB', 'GB', 'TB'];
        let i = -1;
        do { b /= 1024; i++; } while (b >= 1024 && i < u.length - 1);
        return (b >= 100 ? b.toFixed(0) : b.toFixed(1)) + ' ' + u[i];
    }
    function fmtDate(iso, long) {
        if (!iso) return '—';
        const d = new Date(iso), now = new Date();
        const time = d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
        if (!long && d.toDateString() === now.toDateString()) return time;
        const opts = { month: 'short', day: 'numeric' };
        if (d.getFullYear() !== now.getFullYear() || long) opts.year = 'numeric';
        return d.toLocaleDateString([], opts) + (long ? ', ' + time : '');
    }
    function avatarColor(s) {
        let h = 0;
        for (const ch of String(s || '')) h = (h * 31 + ch.charCodeAt(0)) % 360;
        return `hsl(${h} 62% 48%)`;
    }
    function initials(name) {
        const p = String(name || '?').trim().split(/[\s@._-]+/).filter(Boolean);
        return ((p[0] || '?')[0] + (p[1] ? p[1][0] : '')).toUpperCase();
    }
    function avatar(nameOrEmail, cls = '') {
        return `<span class="drv-avatar ${cls}" style="background:${avatarColor(nameOrEmail)}" title="${esc(nameOrEmail)}">${esc(initials(nameOrEmail))}</span>`;
    }
    function iconFor(item, extraStyle = '') {
        const c = CAT[item.category] || CAT.other;
        let icon = c.icon;
        if (item.type === 'folder' && item.shared) icon = 'ri-folder-shared-fill';
        const color = item.type === 'folder' && item.color ? item.color : c.color;
        return `<i class="${icon}" style="color:${color};${extraStyle}"></i>`;
    }
    function uuid() {
        if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
        return 'u' + Date.now().toString(36) + Math.random().toString(36).slice(2, 12);
    }
    function debounce(fn, ms) {
        let t;
        return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); };
    }
    function qs(obj) {
        const p = new URLSearchParams();
        Object.entries(obj || {}).forEach(([k, v]) => {
            if (v === null || v === undefined || v === '') return;
            if (Array.isArray(v)) v.forEach(x => p.append(k + '[]', x));
            else p.append(k, v);
        });
        return p.toString();
    }
    async function api(path, opts = {}) {
        const o = {
            method: opts.method || (opts.body !== undefined ? 'POST' : 'GET'),
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': C.csrf, 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        };
        let url = C.base + path;
        if (opts.query) url += '?' + qs(opts.query);
        if (opts.body !== undefined) {
            o.headers['Content-Type'] = 'application/json';
            o.body = JSON.stringify(opts.body);
        }
        const r = await fetch(url, o);
        let data = null;
        try { data = await r.json(); } catch (e) { /* non-JSON */ }
        if (!r.ok) {
            const msg = (data && (data.errors ? Object.values(data.errors)[0][0] : data.message)) || `Request failed (${r.status})`;
            throw new Error(r.status === 419 ? 'Your session expired. Please reload the page.' : msg);
        }
        return data;
    }
    async function copyText(text, label) {
        try {
            await navigator.clipboard.writeText(text);
        } catch (e) {
            const ta = document.createElement('textarea');
            ta.value = text;
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            ta.remove();
        }
        toast(label || 'Copied to clipboard');
    }
    function toast(msg, opts = {}) {
        const el = document.createElement('div');
        el.className = 'drv-toast' + (opts.err ? ' err' : '');
        el.innerHTML = `<span>${esc(msg)}</span>`;
        if (opts.action) {
            const b = document.createElement('button');
            b.textContent = opts.action.label;
            b.onclick = () => { el.remove(); opts.action.fn(); };
            el.appendChild(b);
        }
        $('#drv-toasts').appendChild(el);
        setTimeout(() => el.remove(), opts.ms || (opts.err ? 6000 : 3500));
    }
    function fail(e) { toast(e.message || String(e), { err: true }); }
    function modal(id) { return bootstrap.Modal.getOrCreateInstance(document.getElementById(id)); }
    function isTyping(e) {
        const t = e.target;
        return t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.tagName === 'SELECT' || t.isContentEditable);
    }
    function byId(id) { return S.items.find(i => i.id === id); }
    function selItems() { return S.items.filter(i => S.selected.has(i.id)); }

    /** Where new uploads / folders go: uuid, null (My Drive root) or false (not allowed). */
    function currentParent() {
        if (S.folderInfo) return S.folderInfo.can_edit ? S.folderInfo.id : false;
        return null;
    }
    function isCurrentListing(parent) {
        if (S.folderInfo) return S.folderInfo.id === parent;
        return parent === null && S.view === 'my' && !S.q;
    }

    /* ================================================================== */
    /* Routing / loading                                                   */
    /* ================================================================== */

    function onHash() {
        const parts = location.hash.replace(/^#\/?/, '').split('/');
        const a = parts[0], b = parts[1];
        S.selected.clear();
        S.filter = 'all';
        $$('.drv-chip').forEach(c => c.classList.toggle('active', c.dataset.filter === 'all'));
        if (a !== 'search') { S.q = ''; $('#drv-search').value = ''; }
        if (a === 'folder' && b) {
            S.folder = b;
        } else if (a === 'item' && b) {
            openItemLink(b);
            return;
        } else {
            S.folder = null;
            S.view = VIEW_TITLES[a] && a !== 'search' ? a : 'my';
        }
        load();
    }

    async function load(opts = {}) {
        const seq = ++S.loadSeq;
        if (!opts.silent) $('#drv-loading').classList.add('show');
        try {
            const query = S.folder ? { folder: S.folder } : (S.q ? { view: 'search', q: S.q } : { view: S.view });
            const data = await api('/api/list', { query });
            if (seq !== S.loadSeq) return;
            S.items = data.items;
            S.breadcrumbs = data.breadcrumbs || [];
            S.root = data.root;
            S.folderInfo = data.folder;
            S.selected = new Set([...S.selected].filter(id => byId(id)));
            render();
        } catch (e) {
            if (seq !== S.loadSeq) return;
            fail(e);
            if (S.folder) { location.hash = '#/my'; }
        } finally {
            if (seq === S.loadSeq) $('#drv-loading').classList.remove('show');
        }
    }
    const refresh = debounce(() => { load({ silent: true }); loadStats(); }, 500);

    async function openItemLink(id) {
        try {
            const it = await api('/api/items/' + id);
            if (it.type === 'folder') { location.hash = '#/folder/' + id; return; }
            if (it.parent) { S.folder = it.parent; } else { S.folder = null; S.view = it.is_owner ? 'my' : 'shared'; }
            history.replaceState(null, '', S.folder ? '#/folder/' + S.folder : '#/' + S.view);
            await load();
            S.selected = new Set([it.id]);
            updateSelection();
            openPreview(byId(it.id) || it);
        } catch (e) {
            fail(e);
            location.hash = '#/my';
        }
    }

    async function loadStats() {
        try {
            const s = await api('/api/stats');
            const total = Math.max(1, Object.values(s.by_category).reduce((a, b) => a + b, 0));
            $('#drv-storage-bar').innerHTML = Object.entries(STORAGE_COLORS)
                .filter(([k]) => s.by_category[k])
                .map(([k, c]) => `<span style="width:${(s.by_category[k] / total * 100).toFixed(2)}%;background:${c}" title="${k}: ${fmtSize(s.by_category[k])}"></span>`).join('');
            $('#drv-storage-total').textContent = fmtSize(s.total) + ' used';
            $('#drv-storage-files').textContent = `${s.files} files · ${s.folders} folders`;
            $('#drv-storage-legend').innerHTML = Object.entries(STORAGE_COLORS)
                .map(([k, c]) => `<span><b style="background:${c}"></b>${k[0].toUpperCase() + k.slice(1)} ${s.by_category[k] ? fmtSize(s.by_category[k]) : '0'}</span>`).join('')
                + (s.versions ? `<span><b style="background:#cbd5e1"></b>Versions ${fmtSize(s.versions)}</span>` : '');
        } catch (e) { /* non-critical */ }
    }

    /* ================================================================== */
    /* Rendering                                                           */
    /* ================================================================== */

    function visibleItems() {
        let list = S.items.slice();
        if (S.filter !== 'all') {
            list = list.filter(i => S.filter === 'folder' ? i.type === 'folder'
                : S.filter === 'docs' ? DOC_CATS.includes(i.category) : i.category === S.filter);
        }
        const { key, dir } = S.sort;
        const m = dir === 'asc' ? 1 : -1;
        const keepServerOrder = (S.view === 'recent' || S.view === 'trash') && !S.folder && !S.q && key === 'name';
        list.sort((a, b) => {
            if (a.type !== b.type) return a.type === 'folder' ? -1 : 1;
            if (keepServerOrder) return 0;
            if (key === 'size') return (a.size - b.size) * m;
            if (key === 'updated_at') return (new Date(a.updated_at) - new Date(b.updated_at)) * m;
            return a.name.localeCompare(b.name, undefined, { numeric: true, sensitivity: 'base' }) * m;
        });
        return list;
    }

    function render() {
        app.dataset.layout = S.layout;
        $$('[data-layout-btn]').forEach(b => b.classList.toggle('active', b.dataset.layoutBtn === S.layout));
        $$('#drv-sort-menu [data-sort]').forEach(a => a.classList.toggle('active', a.dataset.sort === S.sort.key));
        $$('#drv-sort-menu [data-dir]').forEach(a => a.classList.toggle('active', a.dataset.dir === S.sort.dir));
        const navView = S.folder ? (S.root === 'my' ? 'my' : 'shared') : (S.q ? '' : S.view);
        $$('.drv-nav-link').forEach(a => a.classList.toggle('active', a.dataset.view === navView));
        $('#drv-new-btn').disabled = currentParent() === false;

        renderCrumbs();

        const items = visibleItems();
        let html = '';
        if (S.view === 'trash' && !S.folder && !S.q) {
            html += `<div class="alert alert-light border d-flex align-items-center gap-2 py-2 mb-2" style="border-radius:12px">
                <i class="ri-information-line fs-5 text-primary"></i>
                <span class="flex-grow-1 small">Items stay in Trash until you delete them forever. Only items you own appear here.</span>
                ${S.items.length ? '<button class="btn btn-sm btn-outline-danger" data-act="empty-trash"><i class="ri-delete-bin-2-line"></i> Empty trash</button>' : ''}
            </div>`;
        }
        if (!items.length) {
            html += emptyState();
        } else if (S.layout === 'list') {
            html += renderList(items);
        } else {
            html += renderGrid(items);
        }
        content.innerHTML = html;
        updateSelection();
    }

    function renderCrumbs() {
        const el = $('#drv-crumbs');
        if (S.q) {
            el.innerHTML = `<span class="drv-crumb current">Results for “${esc(S.q)}”</span>`;
            return;
        }
        if (!S.folder) {
            el.innerHTML = `<span class="drv-crumb current" ${S.view === 'my' ? 'data-crumb=""' : ''}>${esc(VIEW_TITLES[S.view])}</span>`;
            return;
        }
        const rootLabel = S.root === 'my' ? 'My Drive' : 'Shared with me';
        const rootAttr = S.root === 'my' ? 'data-crumb=""' : '';
        let html = `<button class="drv-crumb" ${rootAttr} data-nav="${S.root === 'my' ? '#/my' : '#/shared'}">${rootLabel}</button>`;
        let crumbs = S.breadcrumbs;
        if (crumbs.length > 4) {
            html += `<i class="ri-arrow-right-s-line drv-crumb-sep"></i><span class="drv-crumb" title="${esc(crumbs.slice(0, -3).map(c => c.name).join(' / '))}">…</span>`;
            crumbs = crumbs.slice(-3);
        }
        crumbs.forEach((c, idx) => {
            const last = idx === crumbs.length - 1;
            html += `<i class="ri-arrow-right-s-line drv-crumb-sep"></i>`;
            html += last
                ? `<button class="drv-crumb current" data-crumb="${esc(c.id)}" data-folder-menu="1">${esc(c.name)} <i class="ri-arrow-down-s-line drv-crumb-menu"></i></button>`
                : `<button class="drv-crumb" data-crumb="${esc(c.id)}" data-nav="#/folder/${esc(c.id)}">${esc(c.name)}</button>`;
        });
        el.innerHTML = html;
    }

    function badges(i) {
        let b = '';
        if (i.shared) b += '<i class="ri-group-line" title="Shared"></i>';
        if (i.link_access === 'view') b += '<i class="ri-link" title="Anyone with the link"></i>';
        if (i.starred) b += '<i class="ri-star-fill" title="Starred"></i>';
        return b ? `<span class="drv-badges">${b}</span>` : '';
    }
    function draggableAttr(i) {
        return S.view === 'trash' && !S.folder ? '' : 'draggable="true"';
    }

    function renderGrid(items) {
        const folders = items.filter(i => i.type === 'folder');
        const files = items.filter(i => i.type !== 'folder');
        let html = '';
        if (folders.length) {
            html += `<div class="drv-section-title">Folders <span class="count">${folders.length}</span></div><div class="drv-grid-folders">`;
            html += folders.map(i => `
                <div class="drv-folder drv-item" data-id="${esc(i.id)}" data-type="folder" ${draggableAttr(i)} title="${esc(i.name)}">
                    ${iconFor(i, 'font-size:26px')}
                    <span class="drv-name">${esc(i.name)}</span>
                    ${badges(i)}
                    <button class="drv-icon-btn sm drv-kebab" data-kebab title="More actions"><i class="ri-more-2-fill"></i></button>
                </div>`).join('');
            html += '</div>';
        }
        if (files.length) {
            html += `<div class="drv-section-title">Files <span class="count">${files.length}</span></div><div class="drv-grid-files">`;
            html += files.map(i => {
                const c = CAT[i.category] || CAT.other;
                let thumb;
                if (i.thumb_url) thumb = `<img src="${esc(i.thumb_url)}" loading="lazy" alt="" onerror="this.replaceWith(Object.assign(document.createElement('i'),{className:'${c.icon} drv-big-icon',style:'color:${c.color}'}))">`;
                else thumb = `<i class="${c.icon} drv-big-icon" style="color:${c.color}"></i>`;
                if (i.category === 'video') thumb += '<span class="drv-play"><i class="ri-play-fill"></i></span>';
                const ext = i.extension ? `<span class="drv-ext">${esc(i.extension)}</span>` : '';
                return `
                <div class="drv-card drv-item" data-id="${esc(i.id)}" data-type="file" ${draggableAttr(i)} title="${esc(i.name)}">
                    <span class="drv-check"><i class="ri-check-line"></i></span>
                    <div class="drv-card-head">
                        ${iconFor(i, 'font-size:18px')}
                        <span class="drv-name">${esc(i.name)}</span>
                        ${badges(i)}
                        <button class="drv-icon-btn sm drv-kebab" data-kebab title="More actions"><i class="ri-more-2-fill"></i></button>
                    </div>
                    <div class="drv-thumb">${thumb}${ext}</div>
                </div>`;
            }).join('');
            html += '</div>';
        }
        return html;
    }

    function renderList(items) {
        const arrow = k => S.sort.key === k ? `<i class="ri-arrow-${S.sort.dir === 'asc' ? 'up' : 'down'}-line"></i>` : '';
        const showTrashed = S.view === 'trash' && !S.folder;
        let html = `<table class="drv-table"><thead><tr>
            <th data-sort-col="name">Name ${arrow('name')}</th>
            <th>Owner</th>
            <th data-sort-col="updated_at">${showTrashed ? 'Trashed' : 'Last modified'} ${arrow('updated_at')}</th>
            <th data-sort-col="size">File size ${arrow('size')}</th>
            <th style="width:150px"></th>
        </tr></thead><tbody>`;
        html += items.map(i => {
            const ownerName = i.owner ? (i.owner.id === C.me.id ? 'me' : i.owner.name) : '—';
            const nameIcon = i.thumb_url
                ? `<img class="drv-row-thumb" src="${esc(i.thumb_url)}" loading="lazy" alt="">`
                : iconFor(i);
            const actions = showTrashed
                ? `<button class="drv-icon-btn sm" data-row-act="restore" title="Restore"><i class="ri-arrow-go-back-line"></i></button>`
                : `${i.can_edit ? '<button class="drv-icon-btn sm" data-row-act="share" title="Share"><i class="ri-user-add-line"></i></button>' : ''}
                   <button class="drv-icon-btn sm" data-row-act="download" title="Download"><i class="ri-download-2-line"></i></button>
                   <button class="drv-icon-btn sm" data-row-act="star" title="${i.starred ? 'Remove from starred' : 'Add to starred'}"><i class="${i.starred ? 'ri-star-fill text-warning' : 'ri-star-line'}"></i></button>`;
            return `<tr class="drv-item" data-id="${esc(i.id)}" data-type="${i.type}" ${draggableAttr(i)}>
                <td><div class="drv-row-name">${nameIcon}<span class="drv-name" title="${esc(i.name)}">${esc(i.name)}</span>${badges(i)}</div></td>
                <td><div class="d-flex align-items-center gap-2">${i.owner ? avatar(i.owner.name) : ''}<span class="drv-muted">${esc(ownerName)}</span></div></td>
                <td class="drv-muted">${fmtDate(showTrashed ? i.trashed_at : i.updated_at)}</td>
                <td class="drv-muted">${i.type === 'folder' ? '—' : fmtSize(i.size)}</td>
                <td class="text-end"><span class="drv-row-actions">${actions}</span><button class="drv-icon-btn sm" data-kebab title="More actions"><i class="ri-more-2-fill"></i></button></td>
            </tr>`;
        }).join('');
        return html + '</tbody></table>';
    }

    function emptyState() {
        if (S.filter !== 'all' && S.items.length) {
            return `<div class="drv-empty"><div class="drv-empty-art"><i class="ri-filter-off-line"></i></div><h5>Nothing matches this filter</h5><p>Try another type or choose “All”.</p></div>`;
        }
        const states = {
            search: ['ri-search-eye-line', 'No results', 'Try a different name or keyword.'],
            shared: ['ri-group-line', 'Nothing shared with you yet', 'Files and folders others share with your email will show up here.'],
            shared_by_me: ['ri-share-forward-line', 'You haven’t shared anything', 'Share a file or turn on its link to see it here.'],
            recent: ['ri-time-line', 'No recent files', 'Files you upload or edit will appear here.'],
            starred: ['ri-star-smile-line', 'No starred files', 'Add stars to things you want to find easily later.'],
            trash: ['ri-delete-bin-6-line', 'Trash is empty', 'Items moved to Trash will appear here.'],
            folder: ['ri-folder-open-line', 'This folder is empty', 'Drop files here or use the “New” button.'],
            my: ['ri-upload-cloud-2-line', 'Welcome to 5Core Drive', 'Drag & drop images, videos, docs or whole folders here — or click “New”.'],
        };
        const key = S.q ? 'search' : (S.folder ? 'folder' : S.view);
        const [icon, title, sub] = states[key] || states.my;
        const cta = (key === 'my' || (key === 'folder' && currentParent() !== false))
            ? `<div class="d-flex gap-2 justify-content-center mt-3">
                 <button class="btn drv-btn-primary" data-act="upload-files"><i class="ri-upload-2-line"></i> Upload files</button>
                 <button class="btn btn-light" data-act="new-folder"><i class="ri-folder-add-line"></i> New folder</button>
               </div>` : '';
        return `<div class="drv-empty"><div class="drv-empty-art"><i class="${icon}"></i></div><h5>${title}</h5><p>${sub}</p>${cta}</div>`;
    }

    /* ================================================================== */
    /* Selection                                                           */
    /* ================================================================== */

    function updateSelection() {
        $$('.drv-item', content).forEach(el => {
            el.classList.toggle('selected', S.selected.has(el.dataset.id));
            el.classList.toggle('cut', !!(S.clipboard && S.clipboard.mode === 'cut' && S.clipboard.ids.includes(el.dataset.id)));
        });
        const n = S.selected.size;
        app.classList.toggle('has-selection', n > 0);
        const bar = $('#drv-selbar');
        bar.hidden = n === 0;
        if (n) {
            $('#drv-sel-count').textContent = `${n} selected`;
            const items = selItems();
            $('#drv-sel-actions').innerHTML = actionsFor(items)
                .filter(a => a.bar && !a.disabled)
                .map((a, idx) => `<button class="drv-icon-btn" data-bar-idx="${idx}" title="${esc(a.label)}"><i class="${a.icon}"></i></button>`).join('')
                + `<button class="drv-icon-btn" data-bar-more title="More actions"><i class="ri-more-2-fill"></i></button>`;
            const barActions = actionsFor(items).filter(a => a.bar && !a.disabled);
            $$('#drv-sel-actions [data-bar-idx]').forEach(b => b.onclick = () => barActions[+b.dataset.barIdx].fn());
            $('#drv-sel-actions [data-bar-more]').onclick = e => {
                const r = e.currentTarget.getBoundingClientRect();
                showCtx(r.left, r.bottom + 4, actionsFor(items));
            };
        }
        if (S.details) renderDetails();
    }

    function clickSelect(id, e) {
        const order = visibleItems().map(i => i.id);
        if (e.shiftKey && S.anchor && order.includes(S.anchor)) {
            const [a, b] = [order.indexOf(S.anchor), order.indexOf(id)].sort((x, y) => x - y);
            if (!(e.ctrlKey || e.metaKey)) S.selected.clear();
            order.slice(a, b + 1).forEach(x => S.selected.add(x));
        } else if (e.ctrlKey || e.metaKey || (e.target.closest && e.target.closest('.drv-check'))) {
            S.selected.has(id) ? S.selected.delete(id) : S.selected.add(id);
            S.anchor = id;
        } else {
            S.selected = new Set([id]);
            S.anchor = id;
        }
        updateSelection();
    }

    /* ================================================================== */
    /* Actions                                                             */
    /* ================================================================== */

    function actionsFor(items) {
        if (!items.length) return [];
        const one = items.length === 1 ? items[0] : null;
        const allEdit = items.every(i => i.can_edit);
        const allOwner = items.every(i => i.is_owner);
        const files = items.filter(i => i.type !== 'folder');
        const inTrash = items.every(i => i.trashed);
        const A = [];

        if (inTrash) {
            A.push({ icon: 'ri-arrow-go-back-line', label: 'Restore', bar: true, fn: () => restoreItems(items) });
            A.push({ icon: 'ri-delete-bin-2-line', label: 'Delete forever', danger: true, bar: true, fn: () => deleteForever(items) });
            return A;
        }

        if (one) {
            A.push({ icon: one.type === 'folder' ? 'ri-folder-open-line' : 'ri-eye-line', label: one.type === 'folder' ? 'Open' : 'Preview', fn: () => openItem(one) });
            if (one.editable_text && one.can_edit) A.push({ icon: 'ri-edit-2-line', label: 'Edit', fn: () => openEditor(one) });
            A.push({ sep: true });
        }
        if (one && one.can_edit) A.push({ icon: 'ri-user-add-line', label: 'Share', bar: true, fn: () => openShare(one) });
        if (one) A.push({ icon: 'ri-link', label: 'Get link', bar: true, fn: () => openShare(one, true) });
        if (files.length && allEdit && files.length === items.length) A.push({ icon: 'ri-links-line', label: files.length > 1 ? 'Copy direct links (for listings)' : 'Copy direct link (for listings)', fn: () => bulkLinks(files) });
        A.push({ icon: 'ri-download-2-line', label: 'Download', bar: true, fn: () => download(items) });
        A.push({ sep: true });
        if (one && one.can_edit) A.push({ icon: 'ri-edit-line', label: 'Rename', fn: () => renameItem(one) });
        if (allEdit) A.push({ icon: 'ri-folder-transfer-line', label: 'Move to…', bar: true, fn: () => openMove(items, 'move') });
        A.push({ icon: 'ri-file-copy-2-line', label: 'Make a copy', fn: () => copyItems(items) });
        A.push({ icon: 'ri-folders-line', label: 'Copy to…', fn: () => openMove(items, 'copy') });
        if (allEdit) A.push({ icon: 'ri-scissors-cut-line', label: 'Cut (Ctrl+X)', fn: () => setClipboard(items, 'cut') });
        A.push({ icon: 'ri-clipboard-line', label: 'Copy (Ctrl+C)', fn: () => setClipboard(items, 'copy') });
        const allStarred = items.every(i => i.starred);
        A.push({ icon: allStarred ? 'ri-star-off-line' : 'ri-star-line', label: allStarred ? 'Remove from starred' : 'Add to starred', bar: true, fn: () => starItems(items, !allStarred) });
        if (one && one.type === 'folder' && one.can_edit) A.push({ icon: 'ri-palette-line', label: 'Change color', keepOpen: true, fn: () => showColorMenu(one) });
        if (one && one.type !== 'folder' && one.can_edit) A.push({ icon: 'ri-upload-cloud-line', label: 'Upload new version', fn: () => pickVersion(one) });
        if (one) A.push({ icon: 'ri-information-line', label: 'Details & activity', fn: () => { openDetails(); } });
        A.push({ sep: true });
        if (allOwner) {
            A.push({ icon: 'ri-delete-bin-6-line', label: 'Move to trash', danger: true, bar: true, fn: () => trashItems(items) });
        } else {
            A.push({ icon: 'ri-delete-bin-6-line', label: 'Move to trash', danger: true, disabled: true, fn: () => {} });
            A.push({ note: 'Only the owner can delete shared items.' });
        }
        return A;
    }

    function openItem(item) {
        if (!item) return;
        if (item.type === 'folder') {
            if (item.trashed) { toast('Restore this folder to open it.'); return; }
            location.hash = '#/folder/' + item.id;
        } else {
            openPreview(item);
        }
    }

    async function newFolder(parent = currentParent()) {
        if (parent === false) { toast('You only have view access here.', { err: true }); return null; }
        const res = await promptDialog('New folder', 'Untitled folder', { okText: 'Create', colors: true });
        if (!res) return null;
        try {
            const r = await api('/api/folders', { body: { name: res.value, parent, color: res.color } });
            toast(`Folder “${r.item.name}” created`);
            if (isCurrentListing(parent)) refresh(); else if (parent === null && S.view !== 'my') toast('Added to My Drive');
            return r.item;
        } catch (e) { fail(e); return null; }
    }

    async function newTextFile(ext) {
        const parent = currentParent();
        if (parent === false) { toast('You only have view access here.', { err: true }); return; }
        const defaults = { txt: 'Untitled document.txt', csv: 'Untitled sheet.csv', md: 'Untitled note.md', html: 'Untitled snippet.html' };
        const res = await promptDialog('New file', defaults[ext] || 'Untitled.txt', { okText: 'Create', selectBase: true });
        if (!res) return;
        try {
            const starter = ext === 'csv' ? 'SKU,Title,Price,Image URL\n' : '';
            const r = await api('/api/text-files', { body: { name: res.value, parent, content: starter } });
            if (isCurrentListing(parent)) refresh();
            openEditor(r.item);
        } catch (e) { fail(e); }
    }

    async function renameItem(item) {
        const res = await promptDialog('Rename', item.name, { okText: 'Rename', selectBase: item.type !== 'folder' });
        if (!res || res.value === item.name) return;
        try {
            const r = await api(`/api/items/${item.id}/rename`, { body: { name: res.value } });
            item.name = r.name;
            render();
            toast('Renamed');
        } catch (e) { fail(e); }
    }

    async function moveItems(ids, target, label) {
        ids = ids.filter(id => id !== target);
        if (!ids.length) return;
        try {
            const r = await api('/api/items/move', { body: { ids, target } });
            if (r.moved) {
                toast(`Moved ${r.moved} item${r.moved > 1 ? 's' : ''} to ${label || 'folder'}`);
            }
            if (r.skipped && r.skipped.length) toast('Not moved: ' + r.skipped.join('; '), { err: true, ms: 8000 });
            S.selected.clear();
            refresh();
        } catch (e) { fail(e); }
    }

    async function copyItems(items, target) {
        try {
            const body = { ids: items.map(i => i.id) };
            if (target !== undefined) body.target = target;
            const r = await api('/api/items/copy', { body });
            toast(`Copied ${r.copied} item${r.copied > 1 ? 's' : ''}`);
            refresh();
        } catch (e) { fail(e); }
    }

    async function starItems(items, starred) {
        try {
            await api('/api/items/star', { body: { ids: items.map(i => i.id), starred } });
            items.forEach(i => { i.starred = starred; });
            if (S.view === 'starred' && !S.folder && !starred) refresh(); else render();
        } catch (e) { fail(e); }
    }

    async function trashItems(items) {
        const own = items.filter(i => i.is_owner);
        if (!own.length) { toast('Only the owner can delete shared items.', { err: true }); return; }
        try {
            const ids = own.map(i => i.id);
            await api('/api/items/trash', { body: { ids } });
            S.selected.clear();
            toast(`${ids.length} item${ids.length > 1 ? 's' : ''} moved to trash`, {
                action: { label: 'Undo', fn: () => api('/api/items/restore', { body: { ids } }).then(refresh).catch(fail) },
                ms: 7000,
            });
            refresh();
        } catch (e) { fail(e); }
    }

    async function restoreItems(items) {
        try {
            await api('/api/items/restore', { body: { ids: items.map(i => i.id) } });
            S.selected.clear();
            toast('Restored');
            refresh();
        } catch (e) { fail(e); }
    }

    async function deleteForever(items) {
        const ok = await confirmDialog('Delete forever?', `<p class="mb-0">${items.length === 1 ? `“${esc(items[0].name)}”` : items.length + ' items'} will be deleted forever, including everything inside folders and all saved versions. This can’t be undone.</p>`,
            [{ label: 'Cancel', cls: 'btn-light' }, { label: 'Delete forever', cls: 'btn-danger', value: true }]);
        if (!ok) return;
        try {
            await api('/api/items/delete', { body: { ids: items.map(i => i.id) } });
            S.selected.clear();
            toast('Deleted forever');
            refresh();
        } catch (e) { fail(e); }
    }

    async function emptyTrash() {
        const ok = await confirmDialog('Empty trash?', '<p class="mb-0">All items in Trash will be deleted forever. This can’t be undone.</p>',
            [{ label: 'Cancel', cls: 'btn-light' }, { label: 'Empty trash', cls: 'btn-danger', value: true }]);
        if (!ok) return;
        try { await api('/api/trash/empty', { body: {} }); toast('Trash emptied'); refresh(); } catch (e) { fail(e); }
    }

    function download(items) {
        if (items.length === 1 && items[0].type !== 'folder') {
            window.location.href = items[0].download_url;
            return;
        }
        window.location.href = C.base + '/zip?' + qs({ ids: items.map(i => i.id) });
        toast('Preparing zip…');
    }

    async function bulkLinks(files) {
        try {
            const r = await api('/api/items/links', { body: { ids: files.map(i => i.id) } });
            if (!r.links.length) { toast('No links could be created.', { err: true }); return; }
            files.forEach(f => { f.link_access = 'view'; });
            render();
            const text = r.links.map(l => l.url).join('\n');
            if (r.links.length === 1) { copyText(text, 'Direct link copied — anyone with it can view'); return; }
            $('#drv-links-text').value = text;
            modal('drv-links').show();
        } catch (e) { fail(e); }
    }

    function setClipboard(items, mode) {
        S.clipboard = { ids: items.map(i => i.id), mode, names: items.map(i => i.name) };
        updateSelection();
        toast(`${items.length} item${items.length > 1 ? 's' : ''} ${mode === 'cut' ? 'cut' : 'copied'} — open a folder and press Ctrl+V`);
    }

    async function paste() {
        if (!S.clipboard) return;
        const parent = currentParent();
        if (parent === false) { toast('You only have view access here.', { err: true }); return; }
        const { ids, mode } = S.clipboard;
        if (mode === 'cut') {
            S.clipboard = null;
            await moveItems(ids, parent, S.folderInfo ? S.folderInfo.name : 'My Drive');
        } else {
            await copyItems(ids.map(id => ({ id })), parent);
        }
    }

    function pickVersion(item) {
        S.versionTarget = item;
        $('#drv-input-version').value = '';
        $('#drv-input-version').click();
    }

    /* ================================================================== */
    /* Context menu                                                        */
    /* ================================================================== */

    const ctx = $('#drv-ctx');
    function showCtx(x, y, actions) {
        if (!actions.length) return;
        ctx.innerHTML = '';
        let lastSep = true;
        actions.forEach(a => {
            if (a.sep) {
                if (!lastSep) ctx.appendChild(document.createElement('hr'));
                lastSep = true;
                return;
            }
            lastSep = false;
            if (a.note) {
                const n = document.createElement('div');
                n.className = 'drv-ctx-note';
                n.textContent = a.note;
                ctx.appendChild(n);
                return;
            }
            const b = document.createElement('button');
            b.className = a.danger ? 'danger' : '';
            b.disabled = !!a.disabled;
            b.innerHTML = `<i class="${a.icon}"></i><span>${esc(a.label)}</span>${a.keepOpen ? '<i class="ri-arrow-right-s-line ms-auto"></i>' : ''}`;
            b.onclick = e => {
                if (a.keepOpen) { e.stopPropagation(); a.fn(); return; }
                hideCtx();
                a.fn();
            };
            ctx.appendChild(b);
        });
        if (ctx.lastElementChild && ctx.lastElementChild.tagName === 'HR') ctx.lastElementChild.remove();
        placeCtx(x, y);
    }

    function placeCtx(x, y) {
        ctx.hidden = false;
        ctx.scrollTop = 0;
        const r = ctx.getBoundingClientRect();
        ctx.style.left = Math.max(8, Math.min(x, window.innerWidth - r.width - 8)) + 'px';
        ctx.style.top = Math.max(8, Math.min(y, window.innerHeight - r.height - 8)) + 'px';
    }

    function showColorMenu(item) {
        const r = ctx.getBoundingClientRect();
        const current = item.color || FOLDER_COLORS[0];
        ctx.innerHTML = `
            <button type="button" data-back><i class="ri-arrow-left-s-line"></i><span>Folder color</span></button>
            <hr>
            <div class="drv-color-grid">
                ${FOLDER_COLORS.map(c => `<button type="button" class="drv-color-dot ${current === c ? 'active' : ''}" data-color="${c}" style="--dot:${c}" title="${c}"><i class="ri-check-line"></i></button>`).join('')}
            </div>
            <div class="drv-color-preview"><i class="ri-folder-3-fill" style="color:${current}"></i><span>${esc(item.name)}</span></div>`;
        ctx.querySelector('[data-back]').onclick = e => { e.stopPropagation(); showCtx(r.left, r.top, actionsFor(selItems())); };
        const preview = ctx.querySelector('.drv-color-preview i');
        ctx.querySelectorAll('[data-color]').forEach(b => {
            b.onmouseenter = () => { preview.style.color = b.dataset.color; };
            b.onmouseleave = () => { preview.style.color = current; };
            b.onclick = async e => {
                e.stopPropagation();
                hideCtx();
                try {
                    await api(`/api/items/${item.id}/meta`, { body: { color: b.dataset.color } });
                    item.color = b.dataset.color;
                    render();
                    toast('Folder color updated');
                } catch (err) { fail(err); }
            };
        });
        placeCtx(r.left, r.top);
    }
    function hideCtx() { ctx.hidden = true; }

    function backgroundActions() {
        const parent = currentParent();
        const A = [];
        if (parent !== false && S.view !== 'trash') {
            A.push({ icon: 'ri-folder-add-line', label: 'New folder', fn: () => newFolder() });
            A.push({ icon: 'ri-file-upload-line', label: 'Upload files', fn: () => $('#drv-input-files').click() });
            A.push({ icon: 'ri-folder-upload-line', label: 'Upload folder', fn: () => $('#drv-input-folder').click() });
            A.push({ icon: 'ri-file-text-line', label: 'New text document', fn: () => newTextFile('txt') });
            if (S.clipboard) A.push({ icon: 'ri-clipboard-fill', label: `Paste ${S.clipboard.ids.length} item(s)`, fn: paste });
        }
        if (S.folderInfo) {
            A.push({ sep: true });
            if (S.folderInfo.can_edit) A.push({ icon: 'ri-user-add-line', label: 'Share this folder', fn: () => openShare(S.folderInfo) });
            A.push({ icon: 'ri-download-2-line', label: 'Download folder', fn: () => download([S.folderInfo]) });
        }
        A.push({ sep: true });
        A.push({ icon: 'ri-refresh-line', label: 'Refresh', fn: () => { load(); loadStats(); } });
        return A;
    }

    /* ================================================================== */
    /* Dialogs                                                             */
    /* ================================================================== */

    function promptDialog(title, value, opts = {}) {
        return new Promise(resolve => {
            const el = $('#drv-prompt');
            const input = $('#drv-prompt-input');
            const sw = $('#drv-prompt-colors');
            let color = null, done = false;
            $('#drv-prompt-title').textContent = title;
            $('#drv-prompt-ok').textContent = opts.okText || 'OK';
            input.value = value || '';
            sw.hidden = !opts.colors;
            if (opts.colors) {
                sw.innerHTML = FOLDER_COLORS.map(c => `<button type="button" class="drv-swatch ${c === FOLDER_COLORS[0] ? 'active' : ''}" data-c="${c}" style="background:${c}"></button>`).join('');
                sw.querySelectorAll('[data-c]').forEach(b => b.onclick = () => {
                    sw.querySelectorAll('.drv-swatch').forEach(x => x.classList.remove('active'));
                    b.classList.add('active');
                    color = b.dataset.c;
                });
            }
            const onShown = () => {
                input.focus();
                const dot = value.lastIndexOf('.');
                if (opts.selectBase && dot > 0) input.setSelectionRange(0, dot); else input.select();
            };
            const form = $('#drv-prompt-form');
            form.onsubmit = e => {
                e.preventDefault();
                const v = input.value.trim();
                if (!v) return;
                done = true;
                modal('drv-prompt').hide();
                resolve({ value: v, color });
            };
            el.addEventListener('shown.bs.modal', onShown, { once: true });
            el.addEventListener('hidden.bs.modal', () => { if (!done) resolve(null); }, { once: true });
            modal('drv-prompt').show();
        });
    }

    function confirmDialog(title, bodyHtml, buttons) {
        return new Promise(resolve => {
            const el = $('#drv-confirm');
            let result = null;
            $('#drv-confirm-title').textContent = title;
            $('#drv-confirm-body').innerHTML = bodyHtml;
            const footer = $('#drv-confirm-footer');
            footer.innerHTML = '';
            buttons.forEach(b => {
                const btn = document.createElement('button');
                btn.className = 'btn ' + (b.cls || 'btn-light');
                btn.textContent = b.label;
                btn.onclick = () => { result = b.value ?? null; modal('drv-confirm').hide(); };
                footer.appendChild(btn);
            });
            el.addEventListener('hidden.bs.modal', () => resolve(result), { once: true });
            modal('drv-confirm').show();
        });
    }

    /* ================================================================== */
    /* Share                                                               */
    /* ================================================================== */

    async function openShare(item, focusLink) {
        $('#drv-share-name').textContent = item.name;
        $('#drv-share-body').innerHTML = '<div class="text-center py-5"><div class="drv-spinner mx-auto"></div></div>';
        modal('drv-share').show();
        try {
            const d = await api('/api/items/' + item.id);
            renderShare(d, focusLink);
        } catch (e) { modal('drv-share').hide(); fail(e); }
    }

    function renderShare(d, focusLink) {
        const body = $('#drv-share-body');
        const kind = d.type === 'folder' ? 'folder' : 'file';
        const emails = [];
        const linkOn = d.link_access === 'view';

        const peopleRows = [];
        if (d.owner) {
            peopleRows.push(`<div class="drv-people-row">${avatar(d.owner.name, 'lg')}<div class="who"><b>${esc(d.owner.name)}${d.owner.id === C.me.id ? ' (you)' : ''}</b><small>${esc(d.owner.email)}</small></div><span class="text-muted small fw-semibold">Owner</span></div>`);
        }
        d.shares.forEach(s => {
            const control = d.is_owner
                ? `<select class="form-select form-select-sm" data-share-role="${s.id}"><option value="viewer" ${s.role === 'viewer' ? 'selected' : ''}>Viewer</option><option value="editor" ${s.role === 'editor' ? 'selected' : ''}>Editor</option></select>
                   <button class="drv-icon-btn sm text-danger" data-share-remove="${s.id}" title="Remove access"><i class="ri-user-unfollow-line"></i></button>`
                : `<span class="text-muted small text-capitalize">${esc(s.role)}</span>`;
            peopleRows.push(`<div class="drv-people-row">${avatar(s.name || s.email, 'lg')}<div class="who"><b>${esc(s.name || s.email)}${s.email === String(C.me.email).toLowerCase() ? ' (you)' : ''}</b><small>${esc(s.email)}${s.registered ? '' : ' · not a 5Core user yet'}</small></div>${control}</div>`);
        });
        (d.inherited_shares || []).forEach(s => {
            peopleRows.push(`<div class="drv-people-row opacity-75">${avatar(s.email, 'lg')}<div class="who"><b>${esc(s.email)}</b><small>Access from folder “${esc(s.from)}”</small></div><span class="text-muted small text-capitalize">${esc(s.role)}</span></div>`);
        });

        const isImage = d.category === 'image';
        body.innerHTML = `
            ${d.can_edit ? `
            <div class="drv-share-input">
                <div class="drv-emails" id="shr-emails">
                    <input type="text" id="shr-input" placeholder="Add people by name or email" autocomplete="off">
                    <div class="drv-suggest" id="shr-suggest" hidden></div>
                </div>
                <select id="shr-role" class="form-select" style="width:auto;height:48px;border-radius:12px">
                    <option value="viewer">Viewer</option><option value="editor">Editor</option>
                </select>
            </div>
            <div id="shr-compose" class="mt-3" hidden>
                <div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="shr-notify" checked><label class="form-check-label" for="shr-notify">Notify people by email</label></div>
                <textarea class="form-control" id="shr-msg" rows="2" placeholder="Message (optional)"></textarea>
                <div class="text-end mt-2"><button class="btn drv-btn-primary" id="shr-send"><i class="ri-send-plane-2-line"></i> Share</button></div>
            </div>` : `<div class="alert alert-light border small mb-0"><i class="ri-eye-line me-1"></i> You have view access. Ask the owner to share it with others.</div>`}

            <div class="drv-people">
                <h6 class="fw-semibold mb-1">People with access</h6>
                ${peopleRows.join('')}
            </div>

            <div class="drv-general">
                <h6 class="fw-semibold mb-2">General access</h6>
                <div class="drv-general-row">
                    <span class="drv-general-icon ${linkOn ? 'on' : ''}"><i class="${linkOn ? 'ri-earth-line' : 'ri-lock-line'}"></i></span>
                    <div class="flex-grow-1">
                        <select class="form-select form-select-sm fw-semibold" id="shr-access" style="width:auto;border:0;background-color:transparent;padding-left:0" ${(!d.can_edit || (linkOn && !d.is_owner)) ? 'disabled' : ''}>
                            <option value="none" ${!linkOn ? 'selected' : ''}>Restricted</option>
                            <option value="view" ${linkOn ? 'selected' : ''}>Anyone with the link</option>
                        </select>
                        <div class="small text-muted">${linkOn ? `Anyone on the internet with the link can view${d.type === 'folder' ? ' and download files in this folder' : ''}.` : 'Only people with access can open with the link.'}</div>
                    </div>
                    ${linkOn && d.is_owner ? '<button class="btn btn-sm btn-light" id="shr-reset" title="Old links stop working"><i class="ri-refresh-line"></i> Reset link</button>' : ''}
                </div>
                ${linkOn ? `
                    <div class="drv-link-label">Share page</div>
                    <div class="drv-link-box"><input class="form-control form-control-sm" readonly value="${esc(d.public_url)}"><button class="btn btn-sm btn-outline-primary" data-copy="${esc(d.public_url)}"><i class="ri-file-copy-line"></i> Copy</button><a class="btn btn-sm btn-light" href="${esc(d.public_url)}" target="_blank" rel="noopener"><i class="ri-external-link-line"></i></a></div>
                    ${d.direct_url ? `
                    <div class="drv-link-label">Direct file link — use in listings, image URL fields &amp; sheets</div>
                    <div class="drv-link-box"><input class="form-control form-control-sm drv-mono" readonly value="${esc(d.direct_url)}"><button class="btn btn-sm btn-outline-primary" data-copy="${esc(d.direct_url)}"><i class="ri-file-copy-line"></i> Copy</button></div>` : ''}
                    ${isImage && d.direct_url ? `
                    <div class="drv-link-label">HTML embed</div>
                    <div class="drv-link-box"><input class="form-control form-control-sm drv-mono" readonly value="${esc(`<img src="${d.direct_url}" alt="${d.name}">`)}"><button class="btn btn-sm btn-outline-primary" data-copy="${esc(`<img src="${d.direct_url}" alt="${d.name}">`)}"><i class="ri-file-copy-line"></i> Copy</button></div>` : ''}
                ` : ''}
                <div class="drv-link-label">Internal link (only people with access)</div>
                <div class="drv-link-box"><input class="form-control form-control-sm" readonly value="${esc(d.internal_url)}"><button class="btn btn-sm btn-outline-secondary" data-copy="${esc(d.internal_url)}"><i class="ri-file-copy-line"></i> Copy</button></div>
            </div>

            <div class="drv-share-note"><i class="ri-shield-keyhole-line"></i><span>Only the owner can remove people, lower access, turn off the link or delete this ${kind}. Editors can add people and upload, but can’t delete.</span></div>
        `;

        body.querySelectorAll('[data-copy]').forEach(b => b.onclick = () => copyText(b.dataset.copy));

        const access = $('#shr-access', body);
        if (access) access.onchange = async () => {
            try {
                await api(`/api/items/${d.id}/link`, { body: { access: access.value } });
                const local = byId(d.id); if (local) local.link_access = access.value;
                render();
                const fresh = await api('/api/items/' + d.id);
                renderShare(fresh, true);
                if (access.value === 'view' && fresh.direct_url) copyText(fresh.direct_url, 'Link enabled and direct link copied');
            } catch (e) { fail(e); access.value = d.link_access; }
        };
        const reset = $('#shr-reset', body);
        if (reset) reset.onclick = async () => {
            const ok = await confirmDialog('Reset link?', '<p class="mb-0">The current link will stop working everywhere it’s been used (including listings). A new link will be created.</p>',
                [{ label: 'Cancel', cls: 'btn-light' }, { label: 'Reset link', cls: 'btn-danger', value: true }]);
            if (!ok) { modal('drv-share').show(); return; }
            try {
                await api(`/api/items/${d.id}/link`, { body: { access: 'view', regenerate: true } });
                openShare(d, true);
            } catch (e) { fail(e); }
        };
        body.querySelectorAll('[data-share-role]').forEach(sel => sel.onchange = async () => {
            try { await api(`/api/items/${d.id}/share/${sel.dataset.shareRole}/role`, { body: { role: sel.value } }); toast('Access updated'); } catch (e) { fail(e); }
        });
        body.querySelectorAll('[data-share-remove]').forEach(btn => btn.onclick = async () => {
            try {
                await api(`/api/items/${d.id}/share/${btn.dataset.shareRemove}/remove`, { body: {} });
                toast('Access removed');
                renderShare(await api('/api/items/' + d.id));
                refresh();
            } catch (e) { fail(e); }
        });

        if (!d.can_edit) return;

        const box = $('#shr-emails', body), input = $('#shr-input', body), sug = $('#shr-suggest', body), compose = $('#shr-compose', body);
        const valid = e => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e);
        let sugItems = [], sugIdx = -1;
        const drawChips = () => {
            box.querySelectorAll('.drv-email-chip').forEach(c => c.remove());
            emails.forEach((em, idx) => {
                const chip = document.createElement('span');
                chip.className = 'drv-email-chip' + (valid(em) ? '' : ' invalid');
                chip.innerHTML = `${esc(em)}<button type="button">&times;</button>`;
                chip.querySelector('button').onclick = () => { emails.splice(idx, 1); drawChips(); };
                box.insertBefore(chip, input);
            });
            compose.hidden = emails.length === 0;
        };
        const addEmails = raw => {
            String(raw).split(/[\s,;]+/).map(s => s.trim().toLowerCase()).filter(Boolean)
                .forEach(e => { if (!emails.includes(e)) emails.push(e); });
            input.value = '';
            sug.hidden = true;
            drawChips();
        };
        const fetchSug = debounce(async q => {
            if (!q) { sug.hidden = true; return; }
            try {
                sugItems = (await api('/api/users', { query: { q } })).filter(u => !emails.includes(String(u.email).toLowerCase()));
                sugIdx = sugItems.length ? 0 : -1;
                drawSug();
            } catch (e) { /* ignore */ }
        }, 200);
        const drawSug = () => {
            if (!sugItems.length) { sug.hidden = true; return; }
            sug.innerHTML = sugItems.map((u, i) => `<div data-i="${i}" class="${i === sugIdx ? 'active' : ''}">${avatar(u.name)}<span><b>${esc(u.name)}</b><small>${esc(u.email)}</small></span></div>`).join('');
            sug.hidden = false;
            sug.querySelectorAll('[data-i]').forEach(el => el.onmousedown = ev => { ev.preventDefault(); addEmails(sugItems[+el.dataset.i].email); input.focus(); });
        };
        box.onclick = () => input.focus();
        input.oninput = () => fetchSug(input.value.trim());
        input.onkeydown = e => {
            if (!sug.hidden && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) {
                e.preventDefault();
                sugIdx = (sugIdx + (e.key === 'ArrowDown' ? 1 : -1) + sugItems.length) % sugItems.length;
                drawSug();
            } else if (e.key === 'Enter' || e.key === ',' || e.key === ';' || e.key === 'Tab') {
                if (!sug.hidden && sugIdx >= 0) { e.preventDefault(); addEmails(sugItems[sugIdx].email); }
                else if (input.value.trim()) { e.preventDefault(); addEmails(input.value); }
            } else if (e.key === 'Backspace' && !input.value && emails.length) {
                emails.pop(); drawChips();
            } else if (e.key === 'Escape' && !sug.hidden) {
                e.stopPropagation(); sug.hidden = true;
            }
        };
        input.onpaste = e => {
            const t = (e.clipboardData || window.clipboardData).getData('text');
            if (/[\s,;]/.test(t)) { e.preventDefault(); addEmails(t); }
        };
        input.onblur = () => setTimeout(() => { if (input.value.trim() && valid(input.value.trim())) addEmails(input.value); sug.hidden = true; }, 150);

        $('#shr-send', body).onclick = async () => {
            if (input.value.trim()) addEmails(input.value);
            const bad = emails.filter(e => !valid(e));
            if (bad.length) { toast('Fix invalid emails: ' + bad.join(', '), { err: true }); return; }
            if (!emails.length) return;
            const btn = $('#shr-send', body);
            btn.disabled = true;
            try {
                const r = await api(`/api/items/${d.id}/share`, {
                    body: { emails: emails.slice(), role: $('#shr-role', body).value, notify: $('#shr-notify', body).checked, message: $('#shr-msg', body).value },
                });
                toast(r.added.length ? `Shared with ${r.added.length} ${r.added.length > 1 ? 'people' : 'person'}` : 'Access updated');
                renderShare(await api('/api/items/' + d.id));
                refresh();
            } catch (e) { fail(e); btn.disabled = false; }
        };

        if (!focusLink) setTimeout(() => input.focus(), 250);
    }

    /* ================================================================== */
    /* Move / copy dialog                                                  */
    /* ================================================================== */

    const MV = { items: [], mode: 'move', parent: null, selected: null, canEdit: true, crumbs: [] };

    function openMove(items, mode) {
        MV.items = items;
        MV.mode = mode;
        MV.selected = null;
        MV.parent = S.folderInfo && S.folderInfo.can_edit ? S.folderInfo.id : null;
        $('#drv-move-title').textContent = `${mode === 'move' ? 'Move' : 'Copy'} ${items.length === 1 ? '“' + items[0].name + '”' : items.length + ' items'}`;
        modal('drv-move').show();
        loadMoveFolders();
    }

    async function loadMoveFolders() {
        const list = $('#drv-move-list');
        list.innerHTML = '<div class="text-center py-5"><div class="drv-spinner mx-auto"></div></div>';
        try {
            const r = await api('/api/folders', { query: { parent: MV.parent } });
            MV.canEdit = r.can_edit;
            MV.crumbs = r.breadcrumbs;
            const moving = new Set(MV.items.map(i => i.id));
            $('#drv-move-crumbs').innerHTML = `<button data-mv="">My Drive</button>` + r.breadcrumbs.map(c =>
                `<i class="ri-arrow-right-s-line text-muted"></i><button data-mv="${esc(c.id)}">${esc(c.name)}</button>`).join('');
            list.innerHTML = r.folders.length ? r.folders.map(f => `
                <div class="drv-move-row ${moving.has(f.id) ? 'disabled' : ''}" data-f="${esc(f.id)}" data-name="${esc(f.name)}">
                    <i class="ri-folder-3-fill" style="color:${f.color || '#f5b400'}"></i><span>${esc(f.name)}</span>
                    <i class="ri-arrow-right-s-line go" data-enter="${esc(f.id)}" title="Open"></i>
                </div>`).join('')
                : '<div class="text-center text-muted py-5"><i class="ri-folder-open-line fs-1 d-block mb-2"></i>No folders here</div>';
            $$('#drv-move-crumbs [data-mv]').forEach(b => b.onclick = () => { MV.parent = b.dataset.mv || null; MV.selected = null; loadMoveFolders(); });
            $$('#drv-move-list [data-f]').forEach(row => {
                row.onclick = e => {
                    if (e.target.closest('[data-enter]')) { MV.parent = row.dataset.f; MV.selected = null; loadMoveFolders(); return; }
                    $$('#drv-move-list .drv-move-row').forEach(x => x.classList.remove('active'));
                    row.classList.add('active');
                    MV.selected = { id: row.dataset.f, name: row.dataset.name };
                    updateMoveBtn();
                };
                row.ondblclick = () => { MV.parent = row.dataset.f; MV.selected = null; loadMoveFolders(); };
            });
            updateMoveBtn();
        } catch (e) { fail(e); }
    }

    function updateMoveBtn() {
        const btn = $('#drv-move-ok');
        const verb = MV.mode === 'move' ? 'Move' : 'Copy';
        btn.textContent = MV.selected ? `${verb} to “${MV.selected.name}”` : `${verb} here`;
        btn.disabled = !MV.canEdit;
    }

    $('#drv-move-ok').onclick = async () => {
        const target = MV.selected ? MV.selected.id : MV.parent;
        const label = MV.selected ? MV.selected.name : (MV.crumbs.length ? MV.crumbs[MV.crumbs.length - 1].name : 'My Drive');
        modal('drv-move').hide();
        if (MV.mode === 'move') await moveItems(MV.items.map(i => i.id), target, label);
        else await copyItems(MV.items, target);
    };
    $('#drv-move-newfolder').onclick = async () => {
        const name = window.prompt('New folder name', 'Untitled folder');
        if (!name) return;
        try {
            await api('/api/folders', { body: { name, parent: MV.parent } });
            loadMoveFolders();
        } catch (e) { fail(e); }
    };

    /* ================================================================== */
    /* Preview                                                             */
    /* ================================================================== */

    const PV = { item: null, list: [] };

    function openPreview(item) {
        PV.list = visibleItems().filter(i => i.type !== 'folder');
        if (!PV.list.find(i => i.id === item.id)) PV.list = [item];
        showPreview(item);
        $('#drv-preview').hidden = false;
    }

    async function showPreview(item) {
        PV.item = item;
        const c = CAT[item.category] || CAT.other;
        $('#drv-preview-icon').innerHTML = `<i class="${c.icon}" style="color:${c.color}"></i>`;
        $('#drv-preview-name').textContent = item.name;
        const acts = [];
        if (item.editable_text && item.can_edit) acts.push(['ri-edit-2-line', 'Edit', () => { closePreview(); openEditor(item); }]);
        if (item.can_edit) acts.push(['ri-user-add-line', 'Share', () => openShare(item)]);
        acts.push(['ri-link', 'Get link', () => openShare(item, true)]);
        acts.push(['ri-download-2-line', 'Download', () => download([item])]);
        if (item.view_url) acts.push(['ri-external-link-line', 'Open in new tab', () => window.open(item.view_url, '_blank', 'noopener')]);
        acts.push(['ri-close-line', 'Close (Esc)', closePreview]);
        const wrap = $('#drv-preview-actions');
        wrap.innerHTML = '';
        acts.forEach(([icon, label, fn]) => {
            const b = document.createElement('button');
            b.className = 'drv-icon-btn';
            b.title = label;
            b.innerHTML = `<i class="${icon}"></i>`;
            b.onclick = fn;
            wrap.appendChild(b);
        });

        const idx = PV.list.findIndex(i => i.id === item.id);
        $('#drv-preview-prev').style.visibility = idx > 0 ? 'visible' : 'hidden';
        $('#drv-preview-next').style.visibility = idx >= 0 && idx < PV.list.length - 1 ? 'visible' : 'hidden';

        const stage = $('#drv-preview-stage');
        const none = `<div class="drv-preview-none"><i class="${c.icon}" style="color:${c.color}"></i><h5 class="text-white">No preview available</h5><p>${esc(item.name)} · ${fmtSize(item.size)}</p><button class="btn drv-btn-primary" id="drv-pv-dl"><i class="ri-download-2-line"></i> Download</button></div>`;
        const url = item.view_url;
        if (!url) {
            stage.innerHTML = none;
        } else if (item.category === 'image') {
            stage.innerHTML = `<img src="${esc(url)}" alt="${esc(item.name)}">`;
        } else if (item.category === 'video') {
            stage.innerHTML = `<video src="${esc(url)}" controls autoplay playsinline></video>`;
        } else if (item.category === 'audio') {
            stage.innerHTML = `<div class="text-center"><i class="ri-music-2-fill" style="font-size:110px;color:#ec4899"></i><br><audio src="${esc(url)}" controls autoplay></audio></div>`;
        } else if (item.category === 'pdf') {
            stage.innerHTML = `<iframe src="${esc(url)}" title="${esc(item.name)}"></iframe>`;
        } else if (item.editable_text) {
            stage.innerHTML = '<div class="drv-spinner"></div>';
            try {
                const r = await api(`/api/items/${item.id}/content`);
                if (PV.item !== item) return;
                const pre = document.createElement('pre');
                pre.textContent = r.content || '(empty file)';
                stage.innerHTML = '';
                stage.appendChild(pre);
            } catch (e) { stage.innerHTML = none; }
        } else {
            stage.innerHTML = none;
        }
        const dl = $('#drv-pv-dl');
        if (dl) dl.onclick = () => download([item]);
    }

    function stepPreview(dir) {
        const idx = PV.list.findIndex(i => i.id === PV.item.id);
        const next = PV.list[idx + dir];
        if (next) showPreview(next);
    }
    function closePreview() {
        $('#drv-preview').hidden = true;
        $('#drv-preview-stage').innerHTML = '';
        PV.item = null;
    }
    $('#drv-preview-prev').onclick = () => stepPreview(-1);
    $('#drv-preview-next').onclick = () => stepPreview(1);
    $('#drv-preview-stage').addEventListener('click', e => { if (e.target.id === 'drv-preview-stage') closePreview(); });

    /* ================================================================== */
    /* Text editor                                                         */
    /* ================================================================== */

    const ED = { item: null, dirty: false };

    async function openEditor(item) {
        ED.item = item;
        ED.dirty = false;
        $('#drv-editor-name').textContent = item.name;
        $('#drv-editor-status').textContent = 'Loading…';
        const ta = $('#drv-editor-text');
        ta.value = '';
        ta.readOnly = true;
        modal('drv-editor').show();
        try {
            const r = await api(`/api/items/${item.id}/content`);
            ta.value = r.content;
            ta.readOnly = !r.can_edit;
            $('#drv-editor-save').disabled = !r.can_edit;
            $('#drv-editor-status').textContent = r.can_edit ? '' : 'View only';
            setTimeout(() => ta.focus(), 200);
        } catch (e) { fail(e); modal('drv-editor').hide(); }
    }
    async function saveEditor() {
        if (!ED.item) return;
        const btn = $('#drv-editor-save');
        btn.disabled = true;
        $('#drv-editor-status').textContent = 'Saving…';
        try {
            await api(`/api/items/${ED.item.id}/content`, { body: { content: $('#drv-editor-text').value } });
            ED.dirty = false;
            $('#drv-editor-status').innerHTML = '<i class="ri-check-line text-success"></i> Saved';
            refresh();
        } catch (e) { fail(e); $('#drv-editor-status').textContent = 'Not saved'; }
        btn.disabled = false;
    }
    $('#drv-editor-save').onclick = saveEditor;
    $('#drv-editor-text').addEventListener('input', () => { ED.dirty = true; $('#drv-editor-status').textContent = 'Unsaved changes'; });
    $('#drv-editor-text').addEventListener('keydown', e => {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') { e.preventDefault(); saveEditor(); }
        if (e.key === 'Tab') {
            e.preventDefault();
            const t = e.target, s = t.selectionStart;
            t.setRangeText('    ', s, t.selectionEnd, 'end');
            ED.dirty = true;
        }
    });
    $('#drv-editor').addEventListener('hide.bs.modal', e => {
        if (ED.dirty && !window.confirm('You have unsaved changes. Close anyway?')) e.preventDefault();
    });

    /* ================================================================== */
    /* Details panel                                                       */
    /* ================================================================== */

    const DT = { id: null, data: null, tab: 'details' };

    function openDetails() {
        S.details = true;
        localStorage.setItem('drvDetails', '1');
        app.classList.add('with-details');
        $('#drv-toggle-details').classList.add('active');
        renderDetails();
    }
    function closeDetails() {
        S.details = false;
        localStorage.setItem('drvDetails', '0');
        app.classList.remove('with-details');
        $('#drv-toggle-details').classList.remove('active');
    }

    async function renderDetails(force) {
        const sel = selItems();
        const target = sel.length === 1 ? sel[0] : (sel.length === 0 ? S.folderInfo : null);
        const body = $('#drv-details-body');
        if (!target) {
            DT.id = null;
            $('#drv-details-title').textContent = sel.length ? `${sel.length} items selected` : (VIEW_TITLES[S.q ? 'search' : S.view] || 'Details');
            const size = sel.reduce((a, i) => a + (i.size || 0), 0);
            body.innerHTML = sel.length
                ? `<div class="drv-details-preview"><i class="ri-stack-line" style="color:#6366f1"></i></div><dl class="drv-dl"><dt>Items</dt><dd>${sel.length}</dd><dt>Total size</dt><dd>${fmtSize(size)}</dd></dl>`
                : `<div class="drv-empty py-4"><div class="drv-empty-art" style="width:110px;height:110px;font-size:48px"><i class="ri-information-line"></i></div><p>Select a file or folder to see its details, who has access, versions and activity.</p></div>`;
            return;
        }
        if (DT.id === target.id && DT.data && !force) { drawDetails(); return; }
        DT.id = target.id;
        DT.data = null;
        $('#drv-details-title').textContent = target.name;
        body.innerHTML = '<div class="text-center py-5"><div class="drv-spinner mx-auto"></div></div>';
        try {
            const d = await api('/api/items/' + target.id);
            if (DT.id !== target.id) return;
            DT.data = d;
            drawDetails();
        } catch (e) { body.innerHTML = `<p class="text-danger">${esc(e.message)}</p>`; }
    }

    function drawDetails() {
        const d = DT.data;
        const body = $('#drv-details-body');
        const c = CAT[d.category] || CAT.other;
        $('#drv-details-title').textContent = d.name;
        const preview = d.thumb_url ? `<img src="${esc(d.thumb_url)}" alt="">` : `<i class="${d.type === 'folder' ? (d.shared ? 'ri-folder-shared-fill' : c.icon) : c.icon}" style="color:${d.type === 'folder' && d.color ? d.color : c.color}"></i>`;
        const people = [d.owner ? d.owner.name : null, ...d.shares.map(s => s.name || s.email)].filter(Boolean);
        const tabs = [['details', 'Details'], ['activity', 'Activity']];
        if (d.type !== 'folder') tabs.push(['versions', `Versions (${d.versions.length + 1})`]);
        if (!tabs.find(t => t[0] === DT.tab)) DT.tab = 'details';

        let panel = '';
        if (DT.tab === 'details') {
            panel = `
                <h6 class="fw-semibold">Who has access</h6>
                <div class="drv-access-avatars">${people.slice(0, 10).map(p => avatar(p, 'lg')).join('')}${people.length > 10 ? `<span class="drv-avatar lg" style="background:#94a3b8">+${people.length - 10}</span>` : ''}</div>
                <div class="small text-muted mb-2">${d.link_access === 'view' ? '<i class="ri-earth-line"></i> Anyone with the link can view' : '<i class="ri-lock-line"></i> Restricted'}${d.inherited_shares.length ? ` · ${d.inherited_shares.length} via parent folder` : ''}</div>
                ${d.can_edit ? `<button class="btn btn-sm btn-light mb-3" data-dt="share"><i class="ri-user-add-line"></i> Manage access</button>` : ''}
                <h6 class="fw-semibold mt-2">${d.type === 'folder' ? 'Folder' : 'File'} details</h6>
                <dl class="drv-dl">
                    <dt>Type</dt><dd>${d.type === 'folder' ? 'Folder' : esc(c.label + (d.extension ? ' (.' + d.extension + ')' : ''))}</dd>
                    ${d.type !== 'folder' ? `<dt>Size</dt><dd>${fmtSize(d.size)}</dd>` : ''}
                    <dt>Location</dt><dd>${esc(d.location)}</dd>
                    <dt>Owner</dt><dd>${esc(d.owner ? d.owner.name : '—')}</dd>
                    ${d.created_by ? `<dt>Created by</dt><dd>${esc(d.created_by)}</dd>` : ''}
                    <dt>Modified</dt><dd>${fmtDate(d.updated_at, true)}</dd>
                    <dt>Created</dt><dd>${fmtDate(d.created_at, true)}</dd>
                    <dt>Your access</dt><dd class="text-capitalize">${esc(d.role)}</dd>
                </dl>
                <h6 class="fw-semibold mt-3">Description</h6>
                <textarea class="form-control form-control-sm" id="dt-desc" rows="3" placeholder="${d.can_edit ? 'Add a description…' : 'No description'}" ${d.can_edit ? '' : 'readonly'}>${esc(d.description || '')}</textarea>
            `;
        } else if (DT.tab === 'activity') {
            panel = d.activity.length ? `<ul class="drv-activity">${d.activity.map(a => `
                <li>${avatar(a.by || '?')}<div><div><b>${esc(a.by || 'Someone')}</b> ${esc(a.action)}</div>${a.details ? `<div class="small">${esc(a.details)}</div>` : ''}<div class="when">${fmtDate(a.at, true)}</div></div></li>`).join('')}</ul>`
                : '<p class="text-muted">No activity yet.</p>';
        } else {
            panel = `
                ${d.can_edit ? `<button class="btn btn-sm drv-btn-primary mb-3" data-dt="version"><i class="ri-upload-cloud-line"></i> Upload new version</button>` : ''}
                <div class="drv-version" style="background:color-mix(in srgb,#4f46e5 10%,transparent)">
                    <i class="ri-checkbox-circle-fill text-success fs-5"></i>
                    <div class="flex-grow-1"><b>Current version</b><div class="small text-muted">${fmtSize(d.size)} · ${fmtDate(d.updated_at, true)}</div></div>
                    <a class="drv-icon-btn sm" href="${esc(d.download_url)}" title="Download"><i class="ri-download-2-line"></i></a>
                </div>
                ${d.versions.map(v => `
                <div class="drv-version">
                    <i class="ri-history-line fs-5 text-muted"></i>
                    <div class="flex-grow-1" style="min-width:0"><div class="drv-name">${esc(v.name)}</div><div class="small text-muted">${fmtSize(v.size)} · ${fmtDate(v.created_at, true)}${v.by ? ' · ' + esc(v.by) : ''}</div></div>
                    <a class="drv-icon-btn sm" href="${esc(v.download_url)}" title="Download"><i class="ri-download-2-line"></i></a>
                    ${d.can_edit ? `<button class="drv-icon-btn sm" data-restore-version="${v.id}" title="Restore this version"><i class="ri-arrow-go-back-line"></i></button>` : ''}
                </div>`).join('')}
                ${!d.versions.length ? '<p class="text-muted small mt-2">Older versions appear here when you upload a new version, replace a file with the same name, or save edits.</p>' : ''}
            `;
        }

        body.innerHTML = `
            <div class="drv-details-preview">${preview}</div>
            <div class="drv-tabs">${tabs.map(([k, l]) => `<button class="drv-tab ${DT.tab === k ? 'active' : ''}" data-tab="${k}">${l}</button>`).join('')}</div>
            ${panel}`;

        body.querySelectorAll('[data-tab]').forEach(b => b.onclick = () => { DT.tab = b.dataset.tab; drawDetails(); });
        const share = body.querySelector('[data-dt="share"]');
        if (share) share.onclick = () => openShare(d);
        const ver = body.querySelector('[data-dt="version"]');
        if (ver) ver.onclick = () => pickVersion(d);
        const desc = body.querySelector('#dt-desc');
        if (desc && d.can_edit) desc.onchange = async () => {
            try { await api(`/api/items/${d.id}/meta`, { body: { description: desc.value } }); d.description = desc.value; toast('Description saved'); } catch (e) { fail(e); }
        };
        body.querySelectorAll('[data-restore-version]').forEach(b => b.onclick = async () => {
            try {
                await api(`/api/items/${d.id}/versions/${b.dataset.restoreVersion}/restore`, { body: {} });
                toast('Version restored — the previous file was kept as a version');
                renderDetails(true);
                refresh();
            } catch (e) { fail(e); }
        });
    }

    /* ================================================================== */
    /* Uploads                                                             */
    /* ================================================================== */

    const UP = { queue: [], running: false, done: 0, failed: 0 };

    async function startUpload(entries, parent, opts = {}) {
        if (parent === false) { toast('You only have view access to this folder.', { err: true }); return; }
        entries = entries.filter(e => e.file);
        if (!entries.length) return;
        const tooBig = entries.filter(e => e.file.size > C.maxFileBytes);
        if (tooBig.length) {
            toast(`${tooBig.length} file(s) are larger than ${fmtSize(C.maxFileBytes)} and were skipped.`, { err: true });
            entries = entries.filter(e => e.file.size <= C.maxFileBytes);
        }

        let conflict = 'keep';
        if (!opts.replace && isCurrentListing(parent)) {
            const existing = new Set(S.items.filter(i => i.type !== 'folder').map(i => i.name.toLowerCase()));
            const clashes = entries.filter(e => !e.rel.includes('/') && existing.has(e.file.name.toLowerCase()));
            if (clashes.length) {
                const choice = await confirmDialog('Upload options',
                    `<p>${clashes.length === 1 ? `“${esc(clashes[0].file.name)}” already exists` : `${clashes.length} files already exist`} in this location.</p>
                     <p class="small text-muted mb-0"><b>Replace</b> saves into the existing file and keeps the old one in version history, so links stay the same. <b>Keep both</b> uploads as a new file.</p>`,
                    [{ label: 'Cancel', cls: 'btn-light' }, { label: 'Keep both', cls: 'btn-outline-primary', value: 'keep' }, { label: 'Replace existing', cls: 'drv-btn-primary', value: 'replace' }]);
                if (!choice) return;
                conflict = choice;
            }
        }

        if (parent === null && !isCurrentListing(null) && !opts.replace) toast('Uploading to My Drive');

        entries.forEach(e => {
            const job = { id: uuid(), file: e.file, rel: e.rel, parent, conflict, replace: opts.replace || null, status: 'queued', progress: 0, xhr: null };
            UP.queue.push(job);
            addUploadRow(job);
        });
        $('#drv-uploads').hidden = false;
        $('#drv-uploads').classList.remove('min');
        updateUploadTitle();
        if (!UP.running) processQueue();
    }

    function addUploadRow(job) {
        const cat = guessCat(job.file);
        const row = document.createElement('div');
        row.className = 'drv-up';
        row.id = 'up-' + job.id;
        row.innerHTML = `<i class="${CAT[cat].icon}" style="color:${CAT[cat].color};font-size:22px"></i>
            <div class="info"><div class="nm" title="${esc(job.rel)}">${esc(job.rel)}</div><div class="meta">${fmtSize(job.file.size)} · <span class="pct">Waiting…</span></div><div class="bar"><span style="width:0%"></span></div></div>
            <button class="drv-icon-btn sm st" title="Cancel"><i class="ri-close-circle-line"></i></button>`;
        row.querySelector('.st').onclick = () => {
            if (job.status === 'done' || job.status === 'error') return;
            job.status = 'cancelled';
            if (job.xhr) job.xhr.abort();
            setRow(job, 'Cancelled', 'err', 'ri-forbid-line');
        };
        $('#drv-uploads-list').prepend(row);
    }

    function guessCat(file) {
        const t = file.type || '', ext = (file.name.split('.').pop() || '').toLowerCase();
        if (t.startsWith('image/')) return 'image';
        if (t.startsWith('video/')) return 'video';
        if (t.startsWith('audio/')) return 'audio';
        if (ext === 'pdf') return 'pdf';
        if (['xls', 'xlsx', 'csv'].includes(ext)) return 'sheet';
        if (['doc', 'docx'].includes(ext)) return 'doc';
        if (['zip', 'rar', '7z'].includes(ext)) return 'archive';
        return 'other';
    }

    function setRow(job, text, cls, icon) {
        const row = $('#up-' + job.id);
        if (!row) return;
        row.querySelector('.pct').textContent = text;
        if (icon) {
            const st = row.querySelector('.st');
            st.className = 'drv-icon-btn sm st ' + (cls || '');
            st.innerHTML = `<i class="${icon}"></i>`;
            st.title = '';
        }
        row.querySelector('.bar span').style.width = (job.status === 'done' ? 100 : job.progress) + '%';
    }

    function updateUploadTitle() {
        const pending = UP.queue.filter(j => j.status === 'queued' || j.status === 'uploading').length;
        $('#drv-uploads-title').textContent = pending
            ? `Uploading ${pending} item${pending > 1 ? 's' : ''}…`
            : `${UP.done} upload${UP.done !== 1 ? 's' : ''} complete${UP.failed ? ` · ${UP.failed} failed` : ''}`;
    }

    async function processQueue() {
        UP.running = true;
        let job;
        while ((job = UP.queue.find(j => j.status === 'queued'))) {
            job.status = 'uploading';
            updateUploadTitle();
            try {
                await uploadJob(job);
                if (job.status === 'cancelled') continue;
                job.status = 'done';
                UP.done++;
                setRow(job, job.replace || job.conflict === 'replace' ? 'Saved as new version' : 'Uploaded', 'ok', 'ri-checkbox-circle-fill');
                if (isCurrentListing(job.parent) || job.replace) refresh();
                if (job.replace && DT.id === job.replace) renderDetails(true);
            } catch (e) {
                if (job.status === 'cancelled') continue;
                job.status = 'error';
                UP.failed++;
                setRow(job, e.message || 'Failed', 'err', 'ri-error-warning-fill');
            }
            updateUploadTitle();
        }
        UP.running = false;
        updateUploadTitle();
        loadStats();
    }

    async function uploadJob(job) {
        const size = job.file.size;
        const chunk = Math.max(256 * 1024, C.chunkSize);
        const total = Math.max(1, Math.ceil(size / chunk));
        const uploadId = uuid();
        for (let i = 0; i < total; i++) {
            if (job.status === 'cancelled') return;
            const blob = job.file.slice(i * chunk, Math.min(size, (i + 1) * chunk));
            const fd = new FormData();
            fd.append('upload_id', uploadId);
            fd.append('chunk_index', i);
            fd.append('total_chunks', total);
            fd.append('name', job.file.name);
            fd.append('relative_path', job.rel);
            if (job.parent) fd.append('parent', job.parent);
            if (job.replace) fd.append('replace', job.replace);
            fd.append('conflict', job.conflict);
            fd.append('chunk', blob, 'chunk');
            let attempt = 0;
            for (;;) {
                try {
                    await sendChunk(job, fd, loaded => {
                        job.progress = Math.min(99, ((i * chunk + loaded) / Math.max(1, size)) * 100);
                        setRow(job, `${Math.floor(job.progress)}%`);
                    });
                    break;
                } catch (e) {
                    if (job.status === 'cancelled') return;
                    if (e.permanent || ++attempt >= 4) throw e;
                    setRow(job, `Retrying (${attempt})…`);
                    await new Promise(r => setTimeout(r, 800 * attempt));
                }
            }
        }
    }

    function sendChunk(job, fd, onProgress) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            job.xhr = xhr;
            xhr.open('POST', C.base + '/api/upload');
            xhr.setRequestHeader('X-CSRF-TOKEN', C.csrf);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.upload.onprogress = e => { if (e.lengthComputable) onProgress(e.loaded); };
            xhr.onload = () => {
                let data = null;
                try { data = JSON.parse(xhr.responseText); } catch (e) { /* ignore */ }
                if (xhr.status >= 200 && xhr.status < 300 && data && data.ok) { resolve(data); return; }
                const msg = (data && (data.errors ? Object.values(data.errors)[0][0] : data.message))
                    || (xhr.status === 413 ? 'File chunk too large for the server' : `Upload failed (${xhr.status})`);
                const err = new Error(xhr.status === 419 ? 'Session expired — reload the page' : msg);
                err.permanent = xhr.status >= 400 && xhr.status < 500 && xhr.status !== 408 && xhr.status !== 429;
                reject(err);
            };
            xhr.onerror = () => reject(new Error('Network error'));
            xhr.onabort = () => reject(new Error('Cancelled'));
            xhr.send(fd);
        });
    }

    $('#drv-uploads-min').onclick = () => $('#drv-uploads').classList.toggle('min');
    $('#drv-uploads-close').onclick = () => {
        const active = UP.queue.some(j => j.status === 'queued' || j.status === 'uploading');
        if (active && !window.confirm('Cancel all remaining uploads?')) return;
        UP.queue.forEach(j => { if (j.status === 'queued' || j.status === 'uploading') { j.status = 'cancelled'; if (j.xhr) j.xhr.abort(); } });
        UP.queue = [];
        UP.done = 0;
        UP.failed = 0;
        $('#drv-uploads-list').innerHTML = '';
        $('#drv-uploads').hidden = true;
    };
    window.addEventListener('beforeunload', e => {
        if (UP.queue.some(j => j.status === 'queued' || j.status === 'uploading')) { e.preventDefault(); e.returnValue = ''; }
    });

    $('#drv-input-files').onchange = e => {
        startUpload([...e.target.files].map(f => ({ file: f, rel: f.name })), currentParent());
        e.target.value = '';
    };
    $('#drv-input-folder').onchange = e => {
        startUpload([...e.target.files].map(f => ({ file: f, rel: f.webkitRelativePath || f.name })), currentParent());
        e.target.value = '';
    };
    $('#drv-input-version').onchange = e => {
        const f = e.target.files[0];
        if (f && S.versionTarget) startUpload([{ file: f, rel: f.name }], null, { replace: S.versionTarget.id });
        S.versionTarget = null;
        e.target.value = '';
    };

    /* Dropped folders: walk directory entries */
    function readEntries(reader) {
        return new Promise(res => reader.readEntries(res, () => res([])));
    }
    async function walkEntry(entry, path) {
        if (!entry) return [];
        if (entry.isFile) {
            return new Promise(res => entry.file(f => res([{ file: f, rel: path + f.name }]), () => res([])));
        }
        if (entry.isDirectory) {
            const reader = entry.createReader();
            let all = [], batch;
            do { batch = await readEntries(reader); all = all.concat(batch); } while (batch.length);
            const nested = await Promise.all(all.map(e => walkEntry(e, path + entry.name + '/')));
            return nested.flat();
        }
        return [];
    }
    async function entriesFromDrop(dt) {
        const items = dt.items ? [...dt.items].filter(i => i.kind === 'file') : [];
        const entries = items.map(i => (i.webkitGetAsEntry ? i.webkitGetAsEntry() : null));
        if (entries.length && entries.every(Boolean)) {
            return (await Promise.all(entries.map(en => walkEntry(en, '')))).flat();
        }
        return [...dt.files].map(f => ({ file: f, rel: f.name }));
    }

    /* ================================================================== */
    /* Drag & drop (internal move + external upload)                       */
    /* ================================================================== */

    const DRAG_TYPE = 'application/x-5core-drive';
    let dragIds = null;
    let extDragDepth = 0;
    const main = $('#drv-main');
    const dropzone = $('#drv-dropzone');

    const isExternal = e => e.dataTransfer && [...e.dataTransfer.types].includes('Files') && !dragIds;

    content.addEventListener('dragstart', e => {
        const el = e.target.closest('.drv-item[draggable]');
        if (!el) return;
        if (!S.selected.has(el.dataset.id)) { S.selected = new Set([el.dataset.id]); updateSelection(); }
        dragIds = [...S.selected];
        e.dataTransfer.effectAllowed = 'copyMove';
        e.dataTransfer.setData(DRAG_TYPE, JSON.stringify(dragIds));
        const one = byId(el.dataset.id);
        if (dragIds.length === 1 && one && one.type !== 'folder') {
            const abs = new URL(one.download_url, location.href).href;
            try { e.dataTransfer.setData('DownloadURL', `${one.mime || 'application/octet-stream'}:${one.name}:${abs}`); } catch (err) { /* unsupported */ }
        }
        const ghost = document.createElement('div');
        ghost.style.cssText = 'position:fixed;top:-100px;left:-100px;padding:8px 14px;border-radius:10px;background:#4f46e5;color:#fff;font:600 13px system-ui;box-shadow:0 8px 20px rgba(0,0,0,.3)';
        ghost.textContent = dragIds.length === 1 ? (one ? one.name : '1 item') : `${dragIds.length} items`;
        document.body.appendChild(ghost);
        e.dataTransfer.setDragImage(ghost, 10, 10);
        setTimeout(() => ghost.remove(), 0);
        $$('.drv-item.selected', content).forEach(x => x.classList.add('dragging'));
    });
    document.addEventListener('dragend', () => {
        dragIds = null;
        $$('.dragging').forEach(x => x.classList.remove('dragging'));
        $$('.drop-hover').forEach(x => x.classList.remove('drop-hover'));
    });

    function dropTargetFrom(e) {
        const folderEl = e.target.closest && e.target.closest('.drv-item[data-type="folder"]');
        if (folderEl && !(S.view === 'trash' && !S.folder)) {
            const f = byId(folderEl.dataset.id);
            if (f && !f.trashed) return { el: folderEl, id: f.id, name: f.name, canEdit: f.can_edit };
        }
        const crumb = e.target.closest && e.target.closest('[data-crumb]');
        if (crumb) {
            const id = crumb.dataset.crumb || null;
            if (id === S.folder && S.folderInfo) return { el: crumb, id, name: S.folderInfo.name, canEdit: S.folderInfo.can_edit };
            return { el: crumb, id, name: crumb.textContent.trim(), canEdit: true };
        }
        const root = e.target.closest && e.target.closest('.drv-drop-root');
        if (root) return { el: root, id: null, name: 'My Drive', canEdit: true };
        return null;
    }

    document.addEventListener('dragover', e => {
        const t = dropTargetFrom(e);
        $$('.drop-hover').forEach(x => { if (!t || x !== t.el) x.classList.remove('drop-hover'); });
        if (dragIds) {
            if (t && !dragIds.includes(t.id) && t.id !== (S.folder || (S.view === 'my' ? null : undefined))) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                t.el.classList.add('drop-hover');
            }
            return;
        }
        if (isExternal(e) && (main.contains(e.target) || (t && t.el.classList.contains('drv-drop-root')))) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'copy';
            if (t) {
                t.el.classList.add('drop-hover');
                $('#drv-dropzone-sub').textContent = `to “${t.name}”`;
            } else {
                $('#drv-dropzone-sub').textContent = S.folderInfo ? `to “${S.folderInfo.name}”` : 'to My Drive';
            }
        }
    });

    main.addEventListener('dragenter', e => {
        if (!isExternal(e)) return;
        extDragDepth++;
        dropzone.classList.add('show');
    });
    main.addEventListener('dragleave', e => {
        if (!isExternal(e)) return;
        extDragDepth = Math.max(0, extDragDepth - 1);
        if (!extDragDepth) dropzone.classList.remove('show');
    });

    document.addEventListener('drop', async e => {
        const t = dropTargetFrom(e);
        $$('.drop-hover').forEach(x => x.classList.remove('drop-hover'));
        extDragDepth = 0;
        dropzone.classList.remove('show');

        if (dragIds) {
            e.preventDefault();
            const ids = dragIds;
            dragIds = null;
            if (t && !ids.includes(t.id)) {
                if (!t.canEdit) { toast('You only have view access to that folder.', { err: true }); return; }
                moveItems(ids, t.id, t.name);
            }
            return;
        }
        if (!e.dataTransfer || ![...e.dataTransfer.types].includes('Files')) return;
        if (!main.contains(e.target) && !(t && t.el.classList.contains('drv-drop-root'))) return;
        e.preventDefault();
        const entriesPromise = entriesFromDrop(e.dataTransfer);
        let parent;
        if (t) parent = t.canEdit ? t.id : false;
        else parent = currentParent();
        startUpload(await entriesPromise, parent);
    });
    // Stop the browser from opening files dropped outside the drive area.
    window.addEventListener('dragover', e => { if (e.dataTransfer && [...e.dataTransfer.types].includes('Files')) e.preventDefault(); });
    window.addEventListener('drop', e => { if (e.dataTransfer && [...e.dataTransfer.types].includes('Files')) e.preventDefault(); });

    /* ================================================================== */
    /* Event wiring                                                        */
    /* ================================================================== */

    content.addEventListener('click', e => {
        const act = e.target.closest('[data-act]');
        if (act) {
            const a = act.dataset.act;
            if (a === 'upload-files') $('#drv-input-files').click();
            if (a === 'new-folder') newFolder();
            if (a === 'empty-trash') emptyTrash();
            return;
        }
        const th = e.target.closest('[data-sort-col]');
        if (th) {
            const k = th.dataset.sortCol;
            S.sort = { key: k, dir: S.sort.key === k && S.sort.dir === 'asc' ? 'desc' : 'asc' };
            localStorage.setItem('drvSort', JSON.stringify(S.sort));
            render();
            return;
        }
        const el = e.target.closest('.drv-item');
        if (!el) { S.selected.clear(); updateSelection(); return; }
        const item = byId(el.dataset.id);
        const rowAct = e.target.closest('[data-row-act]');
        if (rowAct) {
            const a = rowAct.dataset.rowAct;
            if (a === 'share') openShare(item);
            if (a === 'download') download([item]);
            if (a === 'star') starItems([item], !item.starred);
            if (a === 'restore') restoreItems([item]);
            return;
        }
        if (e.target.closest('[data-kebab]')) {
            if (!S.selected.has(item.id)) { S.selected = new Set([item.id]); S.anchor = item.id; updateSelection(); }
            const r = e.target.closest('[data-kebab]').getBoundingClientRect();
            showCtx(r.left, r.bottom + 4, actionsFor(selItems()));
            return;
        }
        clickSelect(item.id, e);
    });
    content.addEventListener('dblclick', e => {
        const el = e.target.closest('.drv-item');
        if (el && !e.target.closest('[data-kebab],[data-row-act]')) openItem(byId(el.dataset.id));
    });
    $('#drv-body').addEventListener('contextmenu', e => {
        e.preventDefault();
        const el = e.target.closest('.drv-item');
        if (el) {
            if (!S.selected.has(el.dataset.id)) { S.selected = new Set([el.dataset.id]); S.anchor = el.dataset.id; updateSelection(); }
            showCtx(e.clientX, e.clientY, actionsFor(selItems()));
        } else {
            S.selected.clear();
            updateSelection();
            showCtx(e.clientX, e.clientY, backgroundActions());
        }
    });
    $('#drv-body').addEventListener('click', e => {
        if (e.target === $('#drv-body')) { S.selected.clear(); updateSelection(); }
    });
    document.addEventListener('click', e => { if (!ctx.contains(e.target) && !e.target.closest('[data-kebab],[data-bar-more]')) hideCtx(); });
    window.addEventListener('blur', hideCtx);
    window.addEventListener('resize', hideCtx);
    $('#drv-body').addEventListener('scroll', hideCtx);

    $('#drv-crumbs').addEventListener('click', e => {
        const nav = e.target.closest('[data-nav]');
        if (nav) { location.hash = nav.dataset.nav; return; }
        const menu = e.target.closest('[data-folder-menu]');
        if (menu && S.folderInfo) {
            const r = menu.getBoundingClientRect();
            const A = backgroundActions().filter(a => !a.label || a.label !== 'Refresh');
            if (S.folderInfo.can_edit) A.push({ icon: 'ri-edit-line', label: 'Rename folder', fn: () => renameItem(S.folderInfo).then(() => load({ silent: true })) });
            A.push({ icon: 'ri-information-line', label: 'Folder details', fn: () => { S.selected.clear(); openDetails(); } });
            showCtx(r.left, r.bottom + 4, A);
        }
    });

    $$('.drv-chip').forEach(c => c.onclick = () => {
        S.filter = c.dataset.filter;
        $$('.drv-chip').forEach(x => x.classList.toggle('active', x === c));
        render();
    });
    $$('[data-layout-btn]').forEach(b => b.onclick = () => {
        S.layout = b.dataset.layoutBtn;
        localStorage.setItem('drvLayout', S.layout);
        render();
    });
    $$('#drv-sort-menu [data-sort]').forEach(a => a.onclick = e => {
        e.preventDefault();
        S.sort.key = a.dataset.sort;
        localStorage.setItem('drvSort', JSON.stringify(S.sort));
        render();
    });
    $$('#drv-sort-menu [data-dir]').forEach(a => a.onclick = e => {
        e.preventDefault();
        S.sort.dir = a.dataset.dir;
        localStorage.setItem('drvSort', JSON.stringify(S.sort));
        render();
    });
    $('#drv-toggle-details').onclick = () => (S.details ? closeDetails() : openDetails());
    $('#drv-details-close').onclick = closeDetails;

    $$('[data-action]').forEach(a => a.onclick = e => {
        e.preventDefault();
        const act = a.dataset.action;
        if (act === 'new-folder') newFolder();
        if (act === 'upload-files') $('#drv-input-files').click();
        if (act === 'upload-folder') $('#drv-input-folder').click();
        if (act === 'new-file') newTextFile(a.dataset.ext);
    });

    $('[data-sel="clear"]').onclick = () => { S.selected.clear(); updateSelection(); };

    const search = $('#drv-search');
    const runSearch = debounce(() => {
        const q = search.value.trim();
        S.q = q;
        S.selected.clear();
        if (q) { S.folder = null; history.replaceState(null, '', '#/search'); load(); } else { onHash(); }
    }, 350);
    search.addEventListener('input', runSearch);
    search.addEventListener('keydown', e => { if (e.key === 'Escape') { search.value = ''; runSearch(); search.blur(); } });

    $('#drv-links-copy').onclick = () => copyText($('#drv-links-text').value, 'All links copied');

    document.addEventListener('keydown', e => {
        const previewOpen = !$('#drv-preview').hidden;
        if (previewOpen) {
            if (e.key === 'Escape') closePreview();
            if (e.key === 'ArrowLeft') stepPreview(-1);
            if (e.key === 'ArrowRight') stepPreview(1);
            return;
        }
        if (document.querySelector('.modal.show') || isTyping(e)) return;
        const items = selItems();
        const mod = e.ctrlKey || e.metaKey;
        if (e.key === '/') { e.preventDefault(); search.focus(); return; }
        if (e.key === 'Escape') { hideCtx(); S.selected.clear(); updateSelection(); return; }
        if (mod && e.key.toLowerCase() === 'a') { e.preventDefault(); S.selected = new Set(visibleItems().map(i => i.id)); updateSelection(); return; }
        if (mod && e.key.toLowerCase() === 'v') { e.preventDefault(); paste(); return; }
        if (!items.length) return;
        if (e.key === 'Delete') {
            e.preventDefault();
            items.every(i => i.trashed) ? deleteForever(items) : trashItems(items);
        } else if (e.key === 'F2' && items.length === 1 && items[0].can_edit) {
            e.preventDefault(); renameItem(items[0]);
        } else if (e.key === 'Enter' && items.length === 1) {
            openItem(items[0]);
        } else if (mod && e.key.toLowerCase() === 'c') {
            setClipboard(items, 'copy');
        } else if (mod && e.key.toLowerCase() === 'x' && items.every(i => i.can_edit)) {
            setClipboard(items, 'cut');
        } else if (e.key === 's' && !mod) {
            starItems(items, !items.every(i => i.starred));
        }
    });

    window.addEventListener('hashchange', onHash);

    /* ================================================================== */
    /* Boot                                                                */
    /* ================================================================== */

    function boot() {
        if (S.details) openDetails();
        if (!location.hash) history.replaceState(null, '', '#/my');
        onHash();
        loadStats();
    }
    if (document.readyState === 'complete') boot();
    else window.addEventListener('load', boot);
})();
