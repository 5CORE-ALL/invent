{{--
    Shared "Columns" panel for Tabulator pages (same panel as /amazon-tabulator-view).

    Include this INSIDE the page's <script>, after `table` (the Tabulator instance) exists and after you removed the page's own
    buildColumnDropdown / saveColumnVisibilityToServer / applyColumnVisibilityFromServer and the old menu listeners.
    Define these two plain objects right before the @include (same scope):

        const tcmConfig = {
            channel: 'ebay1_tabulator',                 // required: saved layout key (shared /tabulator-column-visibility endpoint)
            groups: [{ key: 'general', label: 'General' }, ...],   // required: built-in headers, in order (last one = fallback)
            visibilityUrl: '/tabulator-column-visibility',        // optional
            orderUrl: '/tabulator-column-order',                  // optional
            menuId: 'column-dropdown-menu',                       // optional
            toggleId: 'columnVisibilityDropdown',                 // optional
            skipFields: [],                                       // optional: fields never listed in the panel
            showAll: false,                                       // optional: add a "Show all" button
            redrawOnChange: false,                                // optional: table.redraw(true) after a change
            legacyUrl: null,                                      // optional: old GET endpoint used to seed a first-time layout
            legacyCategoryStorageKey: null                        // optional: old localStorage key { "field||title": header }
        };
        const tcmHooks = {                                        // every hook is optional
            classify(field, title, def) { return 'general'; },    // default header of a column
            skip(field, title, def) { return false; },            // true = leave the column out of the panel
            forceVisible(field, def) { return undefined; },       // true / false = force the column on / off after a layout loads
            afterVisibilityChange() {},                           // runs after columns were shown / hidden
            legacyApply(col, field, legacyMap) {}                 // used with legacyUrl
        };
--}}
const tcm = (function () {
    const cfg = (typeof tcmConfig !== 'undefined' && tcmConfig) ? tcmConfig : {};
    const hooks = (typeof tcmHooks !== 'undefined' && tcmHooks) ? tcmHooks : {};
    const CHANNEL = String(cfg.channel || 'tabulator');
    const VIS_URL = cfg.visibilityUrl || '/tabulator-column-visibility';
    const ORDER_URL = cfg.orderUrl || '/tabulator-column-order';
    const MENU_ID = cfg.menuId || 'column-dropdown-menu';
    const TOGGLE_ID = cfg.toggleId || 'columnVisibilityDropdown';
    const CSRF = '{{ csrf_token() }}';
    const SKIP_FIELDS = new Set(Array.isArray(cfg.skipFields) ? cfg.skipFields : []);
    const GROUPS = (Array.isArray(cfg.groups) && cfg.groups.length) ? cfg.groups : [{ key: 'other', label: 'Other' }];
    const BASE_KEYS = GROUPS.map(function (g) { return g.key; });
    const BASE_LABELS = {};
    GROUPS.forEach(function (g) { BASE_LABELS[g.key] = g.label; });
    const FALLBACK_GROUP = BASE_KEYS.indexOf(cfg.fallbackGroup) !== -1 ? cfg.fallbackGroup : BASE_KEYS[BASE_KEYS.length - 1];
    const P = 'tcm_' + CHANNEL.replace(/[^a-zA-Z0-9_]/g, '_') + '_';

    /* ---------- styles (added once for every page) ---------- */
    if (!document.getElementById('tcm-css')) {
        const st = document.createElement('style');
        st.id = 'tcm-css';
        st.textContent = `
        #column-dropdown-menu.show {
            position: fixed !important; top: 0 !important; left: 0 !important; right: 0 !important;
            inset: 0 0 auto 0 !important; transform: none !important;
            width: 100vw; min-width: 100vw; max-width: 100vw; max-height: 85vh; margin: 0 !important;
            border-radius: 0 0 8px 8px; z-index: 2000; overflow-y: auto; padding: 0.6rem 1rem 0.75rem;
            box-shadow: 0 6px 24px rgba(0, 0, 0, 0.25);
        }
        #column-dropdown-menu > li.col-vis-full { list-style: none; }
        #column-dropdown-menu .col-vis-groups { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 10px; list-style: none; margin: 0; padding: 0; }
        #column-dropdown-menu .col-vis-group { background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 6px; padding: 6px; min-height: 120px; display: flex; flex-direction: column; }
        #column-dropdown-menu .col-vis-toolbar { display: flex; gap: 6px; align-items: center; margin-bottom: 8px; flex-wrap: wrap; }
        #column-dropdown-menu .col-vis-toolbar .col-vis-search { flex: 1 1 200px; }
        #column-dropdown-menu .col-vis-toolbar .col-vis-newname { flex: 0 1 170px; }
        #column-dropdown-menu .col-vis-group-custom { background: #f3f0ff; border-color: #d9d0f5; }
        #column-dropdown-menu .col-vis-group-tools { display: flex; gap: 4px; margin-bottom: 6px; }
        #column-dropdown-menu .col-vis-group-tools .col-vis-group-search { flex: 1 1 auto; min-width: 0; font-size: 0.72rem; }
        #column-dropdown-menu .col-vis-group-tools .btn { padding: 0 6px; line-height: 1.6; font-size: 0.7rem; }
        #column-dropdown-menu .col-vis-group-results { border: 1px solid #d9d0f5; border-radius: 4px; background: #fff; margin-bottom: 6px; max-height: 150px; overflow-y: auto; }
        #column-dropdown-menu .col-vis-result { display: flex; align-items: center; gap: 6px; width: 100%; margin: 0; padding: 3px 6px; font-size: 0.78rem; cursor: pointer; }
        #column-dropdown-menu .col-vis-result span { flex: 1 1 auto; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        #column-dropdown-menu .col-vis-result input { margin: 0; flex-shrink: 0; }
        #column-dropdown-menu .col-vis-result-foot { position: sticky; bottom: 0; display: flex; justify-content: space-between; align-items: center; gap: 6px; padding: 4px 6px; background: #f8f9fa; border-top: 1px solid #e9ecef; }
        #column-dropdown-menu .col-vis-result-all { display: flex; align-items: center; gap: 4px; margin: 0; font-size: 0.75rem; cursor: pointer; }
        #column-dropdown-menu .col-vis-result-foot .btn { padding: 0 8px; font-size: 0.72rem; }
        #column-dropdown-menu .col-vis-result:hover { background: #eef5ff; }
        #column-dropdown-menu .col-vis-result small { color: #868e96; white-space: nowrap; }
        #column-dropdown-menu .col-vis-noresult { padding: 4px 6px; font-size: 0.75rem; color: #868e96; }
        #column-dropdown-menu .col-vis-group.col-vis-drop-over { border-color: #0d6efd; background: #eef5ff; box-shadow: inset 0 0 0 1px rgba(13, 110, 253, 0.25); }
        #column-dropdown-menu .col-vis-group-title { display: flex; align-items: center; gap: 6px; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #495057; margin: 0 0 6px; padding: 2px 4px; border-bottom: 1px solid #dee2e6; user-select: none; cursor: pointer; }
        #column-dropdown-menu .col-vis-group-title input[type="checkbox"] { margin: 0; flex-shrink: 0; cursor: pointer; }
        #column-dropdown-menu .col-vis-group-list { flex: 1; min-height: 60px; max-height: 320px; overflow-y: auto; margin: 0; padding: 0; list-style: none; }
        #column-dropdown-menu .col-vis-item { list-style: none; margin: 0; padding: 0; border-radius: 4px; cursor: grab; display: flex; align-items: center; }
        #column-dropdown-menu .col-vis-item:active { cursor: grabbing; }
        #column-dropdown-menu .col-vis-item > label { flex: 1 1 auto; min-width: 0; display: flex; align-items: center; gap: 6px; padding: 3px 5px; cursor: pointer; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin: 0; font-size: 0.8rem; user-select: none; }
        #column-dropdown-menu .col-vis-item > label input[type="checkbox"] { margin: 0; flex-shrink: 0; width: 14px; height: 14px; }
        #column-dropdown-menu .col-vis-item > label:hover { background: rgba(0, 0, 0, 0.04); border-radius: 3px; }
        #column-dropdown-menu .col-vis-item.col-vis-selected { background: #dbe7ff; border-radius: 4px; box-shadow: inset 0 0 0 1px #6ea8fe; }
        #column-dropdown-menu .col-vis-group.col-vis-active { box-shadow: 0 0 0 2px #6f42c1; }
        #column-dropdown-menu .col-vis-item.col-vis-drop-before { box-shadow: inset 0 2px 0 0 #0d6efd; }
        #column-dropdown-menu .col-vis-item.col-vis-drop-after { box-shadow: inset 0 -2px 0 0 #0d6efd; }
        #column-dropdown-menu .col-vis-item.col-vis-dragging { opacity: 0.55; }
        #column-dropdown-menu .col-vis-hint { font-size: 0.72rem; margin: -2px 0 8px; }
        #column-dropdown-menu .col-vis-status { color: #198754; font-weight: 600; }
        #column-dropdown-menu .col-vis-item-x { flex: 0 0 auto; border: 0; background: none; color: #dc3545; font-size: 1rem; line-height: 1; padding: 0 6px; cursor: pointer; opacity: 0.55; }
        #column-dropdown-menu .col-vis-item-x:hover { opacity: 1; background: rgba(220, 53, 69, 0.1); border-radius: 3px; }
        @@media (max-width: 768px) { #column-dropdown-menu .col-vis-groups { grid-template-columns: repeat(2, minmax(140px, 1fr)); } }
        `.split('#column-dropdown-menu').join('#' + MENU_ID);
        document.head.appendChild(st);
    }

    /* ---------- small helpers ---------- */
    function store(key, fallback) {
        try { const v = JSON.parse(localStorage.getItem(P + key) || 'null'); return v === null || v === undefined ? fallback : v; }
        catch (e) { return fallback; }
    }
    function save(key, value) { try { localStorage.setItem(P + key, JSON.stringify(value)); } catch (e) { /* ignore */ } }
    function isObj(o) { return o && typeof o === 'object' && !Array.isArray(o); }
    function menuEl() { return document.getElementById(MENU_ID); }

    // Your own headers: [{ key: 'u_xxxx', label }]
    function loadGroups() {
        const parsed = store('groups_v1', []);
        return (Array.isArray(parsed) ? parsed : []).filter(function (g) {
            return g && /^u_[a-z0-9]+$/.test(String(g.key || '')) && String(g.label || '').trim();
        }).map(function (g) { return { key: String(g.key), label: String(g.label).trim().slice(0, 30) }; });
    }
    function saveGroups(list) { save('groups_v1', list || []); }
    function allKeys() { return BASE_KEYS.concat(loadGroups().map(function (g) { return g.key; })); }
    function isCustom(key) { return BASE_KEYS.indexOf(key) === -1; }
    // New names given to the built-in headers: { key: label }
    function loadLabels() { const p = store('labels_v1', {}); return isObj(p) ? p : {}; }
    function saveLabels(map) { save('labels_v1', map || {}); }
    function labelOf(key) {
        if (BASE_LABELS[key]) { return String(loadLabels()[key] || '').trim() || BASE_LABELS[key]; }
        const g = loadGroups().filter(function (x) { return x.key === key; })[0];
        return g ? g.label : key;
    }

    function plainTitle(def) {
        const field = def && def.field ? String(def.field) : '';
        const raw = (def && def.title != null) ? def.title : field;
        const t = String(raw).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
        return t || field;
    }
    function isSkipped(field, title, def) {
        if (!field || SKIP_FIELDS.has(field)) return true;
        try { return typeof hooks.skip === 'function' && !!hooks.skip(field, title, def); } catch (e) { return false; }
    }
    function classify(field, title, def) {
        let cat = null;
        try { if (typeof hooks.classify === 'function') cat = hooks.classify(field, title, def); } catch (e) { cat = null; }
        return BASE_KEYS.indexOf(cat) !== -1 ? cat : FALLBACK_GROUP;
    }

    // Column moved to another header: { field: header key }  (imports the page's old per-browser placements once)
    function loadCats() {
        const p = store('cats_v1', null);
        if (isObj(p)) return p;
        const out = {};
        if (cfg.legacyCategoryStorageKey) {
            try {
                const old = JSON.parse(localStorage.getItem(cfg.legacyCategoryStorageKey) || '{}');
                if (isObj(old)) {
                    Object.keys(old).forEach(function (k) {
                        const field = String(k).split('||')[0];
                        if (field && BASE_KEYS.indexOf(old[k]) !== -1) out[field] = old[k];
                    });
                }
            } catch (e) { /* ignore */ }
        }
        save('cats_v1', out);
        return out;
    }
    function saveCats(map) { save('cats_v1', map || {}); }
    // A column also shown under other headers: { field: [header keys] }
    function loadExtras() { const p = store('extra_v1', {}); return isObj(p) ? p : {}; }
    function saveExtras(map) { save('extra_v1', map || {}); }
    function extraAdd(field, cat) {
        const ex = loadExtras();
        const list = Array.isArray(ex[field]) ? ex[field] : [];
        if (list.indexOf(cat) === -1) list.push(cat);
        ex[field] = list;
        saveExtras(ex);
    }
    function extraRemove(field, cat) {
        const ex = loadExtras();
        if (!Array.isArray(ex[field])) return;
        ex[field] = ex[field].filter(function (c) { return c !== cat; });
        if (!ex[field].length) delete ex[field];
        saveExtras(ex);
    }
    // Order of the columns inside each header: { header key: [fields] }
    function loadOrder() { const p = store('order_v1', {}); return isObj(p) ? p : {}; }
    function saveHeaderOrder(list) {
        if (!list) return;
        const ord = loadOrder();
        ord[list.dataset.category] = Array.from(list.children).filter(function (li) { return li.classList.contains('col-vis-item'); })
            .map(function (li) { return li.dataset.field; });
        save('order_v1', ord);
    }
    function applyHeaderOrder(lists) {
        const ord = loadOrder();
        Object.keys(lists).forEach(function (cat) {
            const saved = ord[cat];
            if (!Array.isArray(saved)) return;
            const rank = function (f) { const i = saved.indexOf(f); return i < 0 ? 1e6 : i; };
            Array.from(lists[cat].children).map(function (li, i) { return { li: li, i: i }; })
                .sort(function (a, b) { return (rank(a.li.dataset.field) - rank(b.li.dataset.field)) || (a.i - b.i); })
                .forEach(function (o) { lists[cat].appendChild(o.li); });
        });
    }

    let dropRef = null;          // { li, after }: where a dragged column will land
    let activeCat = '';          // header that Ctrl+V pastes into
    let flashMsg = '';           // one-time message shown after the panel is rebuilt
    let applyingOrder = false;
    let orderSaveTimer = null;
    let arrangeTimer = null;

    function afterChange() {
        try { if (typeof hooks.afterVisibilityChange === 'function') hooks.afterVisibilityChange(); } catch (e) { console.error(e); }
        if (cfg.redrawOnChange) { try { table.redraw(true); } catch (e) { /* ignore */ } }
    }
    function setFieldVisible(field, visible) {
        table.getColumns().forEach(function (col) {
            if (col.getDefinition().field !== field) return;
            if (visible) col.show(); else col.hide();
        });
    }
    function syncField(menu, field, checked) {
        menu.querySelectorAll('.col-vis-item input.col-vis-field-toggle').forEach(function (cb) {
            if ((cb.getAttribute('data-field') || cb.value) === field) cb.checked = checked;
        });
        menu.querySelectorAll('.col-vis-group').forEach(syncHeaderCheckbox);
    }
    function syncHeaderCheckbox(groupEl) {
        if (!groupEl) return;
        const headerCb = groupEl.querySelector('.col-vis-group-toggle');
        const itemCbs = groupEl.querySelectorAll('.col-vis-item input[type="checkbox"]');
        if (!headerCb) return;
        if (!itemCbs.length) { headerCb.checked = false; headerCb.indeterminate = false; return; }
        let checked = 0;
        itemCbs.forEach(function (cb) { if (cb.checked) checked++; });
        headerCb.checked = checked === itemCbs.length;
        headerCb.indeterminate = checked > 0 && checked < itemCbs.length;
    }

    /* ---------- drag & drop ---------- */
    function clearDropMarks(root) {
        (root || document).querySelectorAll('.col-vis-drop-before, .col-vis-drop-after').forEach(function (el) {
            el.classList.remove('col-vis-drop-before', 'col-vis-drop-after');
        });
    }
    function bindDrag(li, groupEls) {
        li.draggable = true;
        li.addEventListener('dragstart', function (e) {
            e.stopPropagation();
            li.classList.add('col-vis-dragging');
            e.dataTransfer.setData('text/plain', li.dataset.field || '');
            e.dataTransfer.setData('text/col-vis-field', li.dataset.field || '');
            e.dataTransfer.setData('text/col-vis-from', li.dataset.group || '');
            e.dataTransfer.effectAllowed = 'move';
        });
        li.addEventListener('dragover', function (e) {
            e.preventDefault();
            const r = li.getBoundingClientRect();
            const after = (e.clientY - r.top) > r.height / 2;
            clearDropMarks(li.parentNode);
            li.classList.add(after ? 'col-vis-drop-after' : 'col-vis-drop-before');
            dropRef = { li: li, after: after };
        });
        li.addEventListener('dragend', function () {
            dropRef = null;
            clearDropMarks(menuEl());
            li.classList.remove('col-vis-dragging');
            Object.keys(groupEls).forEach(function (k) { groupEls[k].classList.remove('col-vis-drop-over'); });
        });
    }
    function bindDropZone(group, list) {
        [group, list].forEach(function (zone) {
            zone.addEventListener('dragover', function (e) {
                e.preventDefault();
                e.stopPropagation();
                group.classList.add('col-vis-drop-over');
                if (!e.target.closest || !e.target.closest('.col-vis-item')) dropRef = null;
                e.dataTransfer.dropEffect = 'move';
            });
            zone.addEventListener('dragleave', function (e) {
                if (!group.contains(e.relatedTarget)) group.classList.remove('col-vis-drop-over');
            });
            zone.addEventListener('drop', function (e) {
                e.preventDefault();
                e.stopPropagation();
                group.classList.remove('col-vis-drop-over');
                const field = e.dataTransfer.getData('text/col-vis-field') || e.dataTransfer.getData('text/plain');
                if (!field) return;
                const menu = menuEl();
                const from = e.dataTransfer.getData('text/col-vis-from');
                const li = menu ? (menu.querySelector('.col-vis-item[data-field="' + CSS.escape(field) + '"][data-group="' + CSS.escape(from) + '"]')
                    || menu.querySelector('.col-vis-item[data-field="' + CSS.escape(field) + '"]')) : null;
                if (!li) return;
                const nextCat = group.dataset.category;
                if (!nextCat) return;
                const ref = (dropRef && list.contains(dropRef.li) && dropRef.li !== li) ? dropRef : null;
                const place = function () {
                    if (ref) list.insertBefore(li, ref.after ? ref.li.nextSibling : ref.li);
                    else list.appendChild(li);
                };
                dropRef = null;
                clearDropMarks(menu);
                if (li.dataset.group === nextCat) {            // same header: only the order changes
                    place();
                    saveHeaderOrder(list);
                    scheduleArrangement();
                    return;
                }
                if (list.querySelector('.col-vis-item[data-field="' + CSS.escape(field) + '"]')) return; // already under that header
                const fromGroup = li.closest('.col-vis-group');
                const prevCat = li.dataset.group;
                place();
                li.dataset.group = nextCat;
                const cb = li.querySelector('input[type="checkbox"]');
                if (cb) cb.dataset.group = nextCat;
                if (li.dataset.copy === '1') {
                    extraRemove(field, prevCat);
                    extraAdd(field, nextCat);
                } else {
                    const cats = loadCats();
                    cats[field] = nextCat;
                    saveCats(cats);
                }
                syncHeaderCheckbox(fromGroup);
                syncHeaderCheckbox(group);
                saveHeaderOrder(list);
                saveHeaderOrder(fromGroup ? fromGroup.querySelector('.col-vis-group-list') : null);
                scheduleArrangement();
            });
        });
    }

    /* ---------- build the panel ---------- */
    function build() {
        const menu = menuEl();
        if (!menu || !table) return;
        // The page's old markup may cap the height; the panel sizes itself.
        menu.style.maxHeight = '';
        menu.style.overflowY = '';
        menu.innerHTML = '';

        const catOverrides = loadCats();
        const extras = loadExtras();
        const keys = allKeys();
        const lists = {};
        const groupEls = {};

        const toolbarLi = document.createElement('li');
        toolbarLi.className = 'col-vis-full';
        toolbarLi.innerHTML = '<div class="col-vis-toolbar">'
            + '<input type="search" class="form-control form-control-sm col-vis-search" placeholder="Quick search columns…" autocomplete="off">'
            + '<input type="text" class="form-control form-control-sm col-vis-newname" maxlength="30" placeholder="New header name" autocomplete="off">'
            + '<button type="button" class="btn btn-sm btn-primary col-vis-addgroup" title="Add a new header (column group)">+ Add header</button>'
            + (cfg.showAll ? '<button type="button" class="btn btn-sm btn-outline-secondary col-vis-showall" title="Show every column">Show all</button>' : '')
            + '<button type="button" class="btn btn-sm btn-success col-vis-save ms-auto" title="Arrange the table columns as shown here, save, and close">Save &amp; Close</button>'
            + '</div>'
            + '<div class="col-vis-hint"><span class="col-vis-status"></span>'
            + '<span class="text-muted">⌘/Ctrl + click a column to select (Shift = range) · ⌘/Ctrl+C copy · ⌘/Ctrl+V paste into the highlighted header · drag a column to move or re-order it</span></div>';
        menu.appendChild(toolbarLi);
        if (flashMsg) {
            toolbarLi.querySelector('.col-vis-status').textContent = flashMsg + ' ';
            flashMsg = '';
        }

        const groupsLi = document.createElement('li');
        groupsLi.className = 'col-vis-full';
        const groupsWrap = document.createElement('div');
        groupsWrap.className = 'col-vis-groups';

        keys.forEach(function (cat) {
            const group = document.createElement('div');
            group.className = 'col-vis-group' + (isCustom(cat) ? ' col-vis-group-custom' : '');
            group.dataset.category = cat;
            if (cat === activeCat) group.classList.add('col-vis-active');

            const titleEl = document.createElement('label');
            titleEl.className = 'col-vis-group-title';
            const groupCb = document.createElement('input');
            groupCb.type = 'checkbox';
            groupCb.className = 'col-vis-group-toggle';
            groupCb.dataset.group = cat;
            groupCb.title = 'Select / deselect all in ' + labelOf(cat);
            titleEl.appendChild(groupCb);
            titleEl.appendChild(document.createTextNode(labelOf(cat)));
            group.appendChild(titleEl);

            const tools = document.createElement('div');
            tools.className = 'col-vis-group-tools';
            tools.innerHTML = '<input type="search" class="form-control form-control-sm col-vis-group-search" placeholder="Search &amp; add column…" autocomplete="off" data-category="' + cat + '">'
                + '<button type="button" class="btn btn-sm btn-outline-secondary col-vis-rename" data-category="' + cat + '" title="Rename this header"><i class="fas fa-pen"></i></button>'
                + (isCustom(cat)
                    ? '<button type="button" class="btn btn-sm btn-outline-danger col-vis-delgroup" data-category="' + cat + '" title="Delete this header (its columns go back to their normal header)"><i class="fas fa-trash-alt"></i></button>'
                    : '');
            group.appendChild(tools);
            const results = document.createElement('div');
            results.className = 'col-vis-group-results';
            results.style.display = 'none';
            group.appendChild(results);

            const list = document.createElement('ul');
            list.className = 'col-vis-group-list';
            list.dataset.category = cat;
            group.appendChild(list);
            groupsWrap.appendChild(group);
            lists[cat] = list;
            groupEls[cat] = group;
            bindDropZone(group, list);
        });

        table.getColumns().forEach(function (col) {
            const def = col.getDefinition();
            const field = def.field;
            const title = plainTitle(def);
            if (isSkipped(field, title, def)) return;
            let cat = catOverrides[field];
            if (keys.indexOf(cat) === -1) cat = classify(field, title, def);
            const isVisible = col.isVisible();

            const makeItem = function (itemCat, isCopy) {
                const li = document.createElement('li');
                li.className = 'col-vis-item';
                li.dataset.field = field;
                li.dataset.group = itemCat;
                if (isCopy) li.dataset.copy = '1';

                const label = document.createElement('label');
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.value = field;
                checkbox.setAttribute('data-field', field);
                checkbox.className = 'col-vis-field-toggle';
                checkbox.dataset.group = itemCat;
                checkbox.checked = isVisible;
                label.appendChild(checkbox);
                label.appendChild(document.createTextNode(' ' + title));
                label.title = title + ' (drag to another header)';
                li.appendChild(label);
                const xBtn = document.createElement('button');
                xBtn.type = 'button';
                xBtn.className = 'col-vis-item-x';
                xBtn.innerHTML = '&times;';
                xBtn.dataset.field = field;
                xBtn.title = isCopy ? 'Remove from this header' : 'Remove from this header (in a normal header: hide the column)';
                li.appendChild(xBtn);
                bindDrag(li, groupEls);
                lists[itemCat].appendChild(li);
            };
            makeItem(cat, false);
            const extraCats = extras[field];
            if (Array.isArray(extraCats)) {
                extraCats.forEach(function (c) { if (c !== cat && lists[c]) makeItem(c, true); });
            }
        });

        applyHeaderOrder(lists);
        Object.keys(loadOrder()).forEach(function (cat) { if (lists[cat]) saveHeaderOrder(lists[cat]); });
        keys.forEach(function (cat) { syncHeaderCheckbox(groupEls[cat]); });

        groupsLi.appendChild(groupsWrap);
        menu.appendChild(groupsLi);
    }

    /* ---------- server: visibility + order ---------- */
    function fetchJson(url) {
        return fetch(url, { method: 'GET', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF } })
            .then(function (res) { return res.json(); });
    }
    function saveVisibility() {
        const visibility = {};
        table.getColumns().forEach(function (col) {
            const def = col.getDefinition();
            const field = def.field;
            if (field && !isSkipped(field, plainTitle(def), def)) visibility[field] = col.isVisible();
        });
        fetch(VIS_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify({ channel: CHANNEL, visibility: visibility })
        }).catch(function (err) { console.error('Error saving column visibility:', err); });
    }
    function forceVisibility() {
        if (typeof hooks.forceVisible !== 'function') return;
        table.getColumns().forEach(function (col) {
            const def = col.getDefinition();
            const field = def.field;
            if (!field) return;
            let v;
            try { v = hooks.forceVisible(field, def); } catch (e) { v = undefined; }
            if (v === true) col.show(); else if (v === false) col.hide();
        });
    }
    function loadVisibility() {
        return fetchJson(VIS_URL + '?channel=' + encodeURIComponent(CHANNEL)).then(function (saved) {
            if (isObj(saved) && Object.keys(saved).length) {
                table.getColumns().forEach(function (col) {
                    const def = col.getDefinition();
                    const field = def.field;
                    if (!field || isSkipped(field, plainTitle(def), def)) return;
                    if (Object.prototype.hasOwnProperty.call(saved, field)) {
                        if (saved[field]) col.show(); else col.hide();
                    }
                });
                return;
            }
            if (!cfg.legacyUrl || typeof hooks.legacyApply !== 'function') return;
            // First time on the shared panel: start from the layout saved by the old Columns menu.
            return fetchJson(cfg.legacyUrl).then(function (legacy) {
                legacy = isObj(legacy) ? legacy : {};
                table.getColumns().forEach(function (col) {
                    const field = col.getDefinition().field;
                    if (!field || SKIP_FIELDS.has(field)) return;
                    try { hooks.legacyApply(col, field, legacy); } catch (e) { console.error(e); }
                });
            });
        });
    }
    function currentOrder() {
        if (!table) return [];
        return table.getColumns().map(function (col) { return col.getField(); }).filter(function (f) { return !!f; });
    }
    function saveOrderToServer() {
        if (!table || applyingOrder) return;
        const order = currentOrder();
        if (!order.length) return;
        fetch(ORDER_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify({ channel: CHANNEL, order: order })
        }).catch(function (err) { console.error('Error saving column order:', err); });
    }
    function scheduleOrderSave() {
        if (orderSaveTimer) clearTimeout(orderSaveTimer);
        orderSaveTimer = setTimeout(saveOrderToServer, 350);
    }
    function moveColumnsToOrder(order) {
        if (!table || !Array.isArray(order)) return;
        applyingOrder = true;
        try {
            for (let i = 0; i < order.length; i++) {
                const field = order[i];
                const cols = table.getColumns().filter(function (c) { return !!c.getField(); });
                const currentIdx = cols.findIndex(function (c) { return c.getField() === field; });
                if (currentIdx === i || currentIdx < 0) continue;
                if (i === 0) {
                    const firstField = cols[0].getField();
                    if (firstField && firstField !== field) table.moveColumn(field, firstField, false);
                } else if (order[i - 1]) {
                    table.moveColumn(field, order[i - 1], true);
                }
            }
        } catch (err) {
            console.error('Error arranging columns:', err);
        } finally {
            applyingOrder = false;
        }
    }
    /** Apply the saved table column order; columns missing from it stay next to their neighbour. */
    function applySavedOrder(order) {
        if (!table || !Array.isArray(order) || !order.length) return;
        const existing = currentOrder();
        if (!existing.length) return;
        const valid = [];
        const seen = {};
        order.forEach(function (f) {
            if (!f || seen[f] || existing.indexOf(f) === -1) return;
            seen[f] = true;
            valid.push(f);
        });
        existing.forEach(function (f) {
            if (seen[f]) return;
            seen[f] = true;
            let inserted = false;
            const idx = existing.indexOf(f);
            for (let j = idx - 1; j >= 0; j--) {
                const prevIdx = valid.indexOf(existing[j]);
                if (prevIdx !== -1) { valid.splice(prevIdx + 1, 0, f); inserted = true; break; }
            }
            if (!inserted) valid.unshift(f);
        });
        moveColumnsToOrder(valid);
    }
    /**
     * Table column order from the panel: a header you made (or whose columns you re-ordered) becomes one block of columns
     * in the table, in the order shown in the panel, starting where its left-most column already was.
     */
    function arrangementGroups() {
        const groups = [];
        const catsMap = loadCats();
        const extras = loadExtras();
        const ord = loadOrder();
        loadGroups().forEach(function (g) {
            const members = new Set();
            Object.keys(catsMap).forEach(function (f) { if (catsMap[f] === g.key) members.add(f); });
            Object.keys(extras).forEach(function (f) { if (Array.isArray(extras[f]) && extras[f].indexOf(g.key) !== -1) members.add(f); });
            const saved = Array.isArray(ord[g.key]) ? ord[g.key] : [];
            const list = saved.filter(function (f) { return members.has(f); });
            members.forEach(function (f) { if (list.indexOf(f) === -1) list.push(f); });
            if (list.length) groups.push(list);
        });
        BASE_KEYS.forEach(function (cat) {
            if (!Array.isArray(ord[cat]) || !ord[cat].length) return;
            const own = ord[cat].filter(function (f) { return !(Array.isArray(extras[f]) && extras[f].indexOf(cat) !== -1); });
            if (own.length) groups.push(own);
        });
        return groups;
    }
    function applyArrangement() {
        if (!table) return;
        const groups = arrangementGroups();
        if (!groups.length) return;
        let order = currentOrder();
        if (!order.length) return;
        const consumed = new Set();
        groups.forEach(function (list) {
            const have = new Set(order);
            const L = [];
            list.forEach(function (f) { if (have.has(f) && !consumed.has(f) && L.indexOf(f) === -1) L.push(f); });
            L.forEach(function (f) { consumed.add(f); });
            if (L.length < 2) return;
            const inL = new Set(L);
            const next = [];
            let placed = false;
            order.forEach(function (f) {
                if (inL.has(f)) {
                    if (!placed) { placed = true; L.forEach(function (x) { next.push(x); }); }
                } else {
                    next.push(f);
                }
            });
            order = next;
        });
        moveColumnsToOrder(order);
    }
    function scheduleArrangement() {
        if (arrangeTimer) clearTimeout(arrangeTimer);
        arrangeTimer = setTimeout(function () {
            applyArrangement();
            scheduleOrderSave();
        }, 250);
    }
    function loadOrderFromServer() {
        return fetchJson(ORDER_URL + '?channel=' + encodeURIComponent(CHANNEL))
            .then(function (resp) {
                if (resp && resp.success && Array.isArray(resp.order) && resp.order.length) applySavedOrder(resp.order);
            })
            .catch(function (err) { console.error('Error loading column order:', err); });
    }
    /** Saved visibility, then saved order, then the panel itself. Safe to call more than once. */
    function applyAll() {
        return Promise.resolve(loadVisibility())
            .catch(function (err) { console.error('Error applying column visibility:', err); })
            .then(function () { forceVisibility(); afterChange(); return loadOrderFromServer(); })
            .then(function () { applyArrangement(); })
            .then(function () { build(); });
    }

    /* ---------- panel events ---------- */
    function init() {
        const menu = menuEl();
        if (!menu || menu.dataset.tcmBound === '1') return;
        menu.dataset.tcmBound = '1';

        // Tick / untick a column or a whole header
        menu.addEventListener('change', function (e) {
            if (e.target.type !== 'checkbox') return;
            if (e.target.closest('.col-vis-group-results')) return;     // pick list, not on / off switches
            if (e.target.classList.contains('col-vis-group-toggle')) {
                const checked = e.target.checked;
                const groupEl = e.target.closest('.col-vis-group');
                const itemCbs = groupEl ? groupEl.querySelectorAll('.col-vis-item input[type="checkbox"]') : [];
                itemCbs.forEach(function (cb) {
                    const field = cb.getAttribute('data-field') || cb.value;
                    cb.checked = checked;
                    setFieldVisible(field, checked);
                    syncField(menu, field, checked);
                });
                e.target.indeterminate = false;
                afterChange();
                saveVisibility();
                return;
            }
            const field = e.target.getAttribute('data-field') || e.target.value;
            if (!field || !table.getColumn(field)) return;
            setFieldVisible(field, e.target.checked);
            syncField(menu, field, e.target.checked);
            afterChange();
            saveVisibility();
        });

        // ---- headers: add / rename / delete, search & add ----
        function moveColToGroup(field, cat) {
            const li = menu.querySelector('.col-vis-item[data-field="' + CSS.escape(field) + '"]:not([data-copy="1"])');
            const group = menu.querySelector('.col-vis-group[data-category="' + CSS.escape(cat) + '"]');
            if (!li || !group) return;
            const fromGroup = li.closest('.col-vis-group');
            group.querySelector('.col-vis-group-list').appendChild(li);
            li.dataset.group = cat;
            const cb = li.querySelector('input[type="checkbox"]');
            if (cb) cb.dataset.group = cat;
            const cats = loadCats();
            cats[field] = cat;
            saveCats(cats);
            syncHeaderCheckbox(fromGroup);
            syncHeaderCheckbox(group);
            saveHeaderOrder(group.querySelector('.col-vis-group-list'));
            saveHeaderOrder(fromGroup ? fromGroup.querySelector('.col-vis-group-list') : null);
            scheduleArrangement();
        }
        function addHeaderFromToolbar() {
            const input = menu.querySelector('.col-vis-newname');
            const name = input ? input.value.trim().slice(0, 30) : '';
            if (!name) { if (input) input.focus(); return; }
            const list = loadGroups();
            const dup = list.concat(BASE_KEYS.map(function (k) { return { label: labelOf(k) }; }))
                .some(function (g) { return String(g.label).toLowerCase() === name.toLowerCase(); });
            if (dup) { alert('A header named "' + name + '" already exists.'); return; }
            list.push({ key: 'u_' + Date.now().toString(36) + Math.random().toString(36).slice(2, 5), label: name });
            saveGroups(list);
            build();
        }

        // Ticked results per header. They stay in the list while you search for something else, until "Add selected".
        const pickedByGroup = {};
        function pickedSet(cat) { return pickedByGroup[cat] || (pickedByGroup[cat] = new Set()); }
        function paintGroupResults(searchEl) {
            const group = searchEl.closest('.col-vis-group');
            const box = group ? group.querySelector('.col-vis-group-results') : null;
            if (!box) return;
            const cat = group.dataset.category;
            const picked = pickedSet(cat);
            const q = searchEl.value.trim().toLowerCase();

            const inHeader = {};
            menu.querySelectorAll('.col-vis-item').forEach(function (li) {
                if (li.dataset.group === cat) inHeader[li.dataset.field] = true;
            });
            Array.from(picked).forEach(function (f) { if (inHeader[f]) picked.delete(f); });

            const infoFor = function (field) {
                const li = menu.querySelector('.col-vis-item:not([data-copy="1"])[data-field="' + CSS.escape(field) + '"]');
                if (!li) return null;
                return { field: field, text: ((li.querySelector('label') || li).textContent || '').trim(), from: labelOf(li.dataset.group) };
            };
            const rows = [];
            const seen = {};
            Array.from(picked).forEach(function (f) {
                const info = infoFor(f);
                if (info) { rows.push(info); seen[f] = true; } else { picked.delete(f); }
            });
            if (q) {
                let extra = 0;
                menu.querySelectorAll('.col-vis-item:not([data-copy="1"])').forEach(function (li) {
                    const f = li.dataset.field;
                    if (inHeader[f] || seen[f] || extra >= 40) return;
                    const text = ((li.querySelector('label') || li).textContent || '').trim();
                    if (text.toLowerCase().indexOf(q) !== -1) {
                        seen[f] = true;
                        extra++;
                        rows.push({ field: f, text: text, from: labelOf(li.dataset.group) });
                    }
                });
            }
            box.innerHTML = '';
            if (!rows.length) {
                if (!q) { box.style.display = 'none'; return; }
                box.innerHTML = '<div class="col-vis-noresult">No column found</div>';
                box.style.display = '';
                return;
            }
            rows.forEach(function (h) {
                const row = document.createElement('label');
                row.className = 'col-vis-result';
                const cb = document.createElement('input');
                cb.type = 'checkbox';
                cb.className = 'col-vis-result-cb';
                cb.dataset.field = h.field;
                cb.checked = picked.has(h.field);
                const name = document.createElement('span');
                name.textContent = h.text;
                const from = document.createElement('small');
                from.textContent = h.from;
                row.appendChild(cb); row.appendChild(name); row.appendChild(from);
                box.appendChild(row);
            });
            const foot = document.createElement('div');
            foot.className = 'col-vis-result-foot';
            foot.innerHTML = '<label class="col-vis-result-all"><input type="checkbox" class="col-vis-result-cb-all"> All</label>'
                + '<button type="button" class="btn btn-sm btn-primary col-vis-add-selected" disabled>Add selected</button>';
            box.appendChild(foot);
            box.style.display = '';
            syncResultFoot(box);
        }
        function syncResultFoot(box) {
            const cbs = box.querySelectorAll('.col-vis-result-cb');
            const n = Array.from(cbs).filter(function (c) { return c.checked; }).length;
            const all = box.querySelector('.col-vis-result-cb-all');
            const btn = box.querySelector('.col-vis-add-selected');
            if (all) { all.checked = cbs.length > 0 && n === cbs.length; all.indeterminate = n > 0 && n < cbs.length; }
            if (btn) { btn.disabled = n === 0; btn.textContent = n ? ('Add selected (' + n + ')') : 'Add selected'; }
        }

        menu.addEventListener('input', function (e) {
            const t = e.target;
            if (t.classList && t.classList.contains('col-vis-search')) {
                const q = t.value.trim().toLowerCase();       // quick search: hide the columns that do not match, in every header
                menu.querySelectorAll('.col-vis-item').forEach(function (li) {
                    li.style.display = (!q || ((li.querySelector('label') || li).textContent || '').toLowerCase().indexOf(q) !== -1) ? '' : 'none';
                });
            } else if (t.classList && t.classList.contains('col-vis-group-search')) {
                paintGroupResults(t);
            }
        });
        menu.addEventListener('change', function (e) {
            const t = e.target;
            if (!t.classList) return;
            const group = t.closest('.col-vis-group');
            const box = t.closest('.col-vis-group-results');
            if (!group || !box) return;
            const picked = pickedSet(group.dataset.category);
            if (t.classList.contains('col-vis-result-cb')) {
                if (t.checked) picked.add(t.dataset.field); else picked.delete(t.dataset.field);
            } else if (t.classList.contains('col-vis-result-cb-all')) {
                box.querySelectorAll('.col-vis-result-cb').forEach(function (c) {
                    c.checked = t.checked;
                    if (t.checked) picked.add(c.dataset.field); else picked.delete(c.dataset.field);
                });
            } else { return; }
            syncResultFoot(box);
        });
        menu.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && e.target.classList && e.target.classList.contains('col-vis-newname')) {
                e.preventDefault();
                addHeaderFromToolbar();
            }
        });

        // ---- select columns (Ctrl/Cmd + click, Shift = range), Ctrl+C copy, Ctrl+V paste into the highlighted header ----
        let colClipboard = [];
        let lastSelectedLi = null;
        function setActiveHeader(cat) {
            activeCat = cat || '';
            menu.querySelectorAll('.col-vis-group').forEach(function (g) {
                g.classList.toggle('col-vis-active', g.dataset.category === activeCat);
            });
        }
        function flashStatus(msg) {
            const el = menu.querySelector('.col-vis-status');
            if (el) el.textContent = msg + ' ';
        }
        menu.addEventListener('mousedown', function (e) {
            const g = e.target.closest ? e.target.closest('.col-vis-group') : null;
            if (g) setActiveHeader(g.dataset.category);
        });
        menu.addEventListener('click', function (e) {
            const lab = e.target.closest ? e.target.closest('.col-vis-item > label') : null;
            if (!lab) return;
            const li = lab.closest('.col-vis-item');
            if (e.ctrlKey || e.metaKey || e.shiftKey) {
                e.preventDefault();                     // select only, do not tick / untick the column
                if (e.shiftKey && lastSelectedLi && lastSelectedLi.parentNode === li.parentNode) {
                    const items = Array.from(li.parentNode.children);
                    const a = items.indexOf(lastSelectedLi), b = items.indexOf(li);
                    items.slice(Math.min(a, b), Math.max(a, b) + 1).forEach(function (x) { x.classList.add('col-vis-selected'); });
                } else {
                    li.classList.toggle('col-vis-selected');
                    lastSelectedLi = li;
                }
                const g = li.closest('.col-vis-group');
                if (g) setActiveHeader(g.dataset.category);
            } else {
                menu.querySelectorAll('.col-vis-item.col-vis-selected').forEach(function (x) { x.classList.remove('col-vis-selected'); });
            }
        });
        document.addEventListener('keydown', function (e) {
            if (!menu.classList.contains('show')) return;
            if (!(e.ctrlKey || e.metaKey)) return;
            const t = e.target;
            if (t && ((t.tagName === 'INPUT' && t.type !== 'checkbox') || t.tagName === 'TEXTAREA' || t.isContentEditable)) return;
            const k = String(e.key || '').toLowerCase();
            if (k === 'c') {
                const fields = [];
                menu.querySelectorAll('.col-vis-item.col-vis-selected').forEach(function (li) {
                    if (fields.indexOf(li.dataset.field) === -1) fields.push(li.dataset.field);
                });
                if (!fields.length) return;
                e.preventDefault();
                colClipboard = fields;
                flashStatus('Copied ' + fields.length + ' column' + (fields.length === 1 ? '' : 's') + '.');
            } else if (k === 'v') {
                if (!colClipboard.length) return;
                e.preventDefault();
                const group = activeCat ? menu.querySelector('.col-vis-group[data-category="' + CSS.escape(activeCat) + '"]') : null;
                if (!group) { flashStatus('Click a header first, then paste.'); return; }
                let added = 0;
                colClipboard.forEach(function (f) {
                    if (group.querySelector('.col-vis-item[data-field="' + CSS.escape(f) + '"]')) return;
                    extraAdd(f, activeCat);
                    added++;
                });
                if (!added) { flashStatus('Already in ' + labelOf(activeCat) + '.'); return; }
                scheduleArrangement();
                flashMsg = 'Pasted ' + added + ' column' + (added === 1 ? '' : 's') + ' into ' + labelOf(activeCat) + '.';
                build();
            }
        });

        // ---- buttons ----
        menu.addEventListener('click', function (e) {
            const saveBtn = e.target.closest('.col-vis-save');
            if (saveBtn) {
                e.preventDefault();
                if (arrangeTimer) { clearTimeout(arrangeTimer); arrangeTimer = null; }
                applyArrangement();
                saveVisibility();
                saveOrderToServer();
                if (cfg.redrawOnChange) { try { table.redraw(true); } catch (err) { /* ignore */ } }
                const tg = document.getElementById(TOGGLE_ID);
                if (tg && window.bootstrap && bootstrap.Dropdown) bootstrap.Dropdown.getOrCreateInstance(tg).hide();
                else if (tg) tg.click();
                return;
            }
            if (e.target.closest('.col-vis-addgroup')) { e.preventDefault(); addHeaderFromToolbar(); return; }
            if (e.target.closest('.col-vis-showall')) {
                e.preventDefault();
                table.getColumns().forEach(function (col) {
                    const def = col.getDefinition();
                    if (!def.field || isSkipped(def.field, plainTitle(def), def)) return;
                    col.show();
                });
                forceVisibility();                       // columns a page always keeps hidden / shown stay that way
                afterChange();
                saveVisibility();
                build();
                return;
            }

            const xBtn = e.target.closest('.col-vis-item-x');
            if (xBtn) {
                e.preventDefault();
                e.stopPropagation();
                const li = xBtn.closest('.col-vis-item');
                if (!li) return;
                const field = li.dataset.field;
                if (li.dataset.copy === '1') {
                    // A copy of the column under this header: just take it out of here (the column and its own header stay).
                    extraRemove(field, li.dataset.group);
                    const grp = li.closest('.col-vis-group');
                    li.remove();
                    syncHeaderCheckbox(grp);
                    saveHeaderOrder(grp ? grp.querySelector('.col-vis-group-list') : null);
                    scheduleArrangement();
                } else if (isCustom(li.dataset.group)) {
                    // Your header: take the column out, it goes back to its normal header.
                    const col = table.getColumn(field);
                    const def = col ? col.getDefinition() : { field: field };
                    moveColToGroup(field, classify(field, plainTitle(def), def));
                    const cats = loadCats();
                    delete cats[field];
                    saveCats(cats);
                } else {
                    // Normal header: hide the column in the table (tick it again to bring it back).
                    setFieldVisible(field, false);
                    syncField(menu, field, false);
                    afterChange();
                    saveVisibility();
                }
                return;
            }

            const addSel = e.target.closest('.col-vis-add-selected');
            if (addSel) {
                e.preventDefault();
                const group = addSel.closest('.col-vis-group');
                if (!group) return;
                const cat = group.dataset.category;
                Array.from(pickedSet(cat)).forEach(function (f) { extraAdd(f, cat); });   // added as a copy: the column stays under its own header too
                pickedSet(cat).clear();
                scheduleArrangement();
                build();
                return;
            }

            const renBtn = e.target.closest('.col-vis-rename');
            if (renBtn) {
                e.preventDefault();
                const key = renBtn.dataset.category;
                const name = prompt('Header name', labelOf(key));
                if (name === null) return;
                const clean = name.trim().slice(0, 30);
                if (!clean) return;
                if (isCustom(key)) {
                    const list = loadGroups();
                    list.forEach(function (g) { if (g.key === key) g.label = clean; });
                    saveGroups(list);
                } else {
                    const labels = loadLabels();
                    labels[key] = clean;
                    saveLabels(labels);
                }
                build();
                return;
            }

            const delBtn = e.target.closest('.col-vis-delgroup');
            if (delBtn) {
                e.preventDefault();
                const key = delBtn.dataset.category;
                if (!confirm('Delete the header "' + labelOf(key) + '"? Its columns go back to their normal header (they stay as they are in the table).')) return;
                saveGroups(loadGroups().filter(function (g) { return g.key !== key; }));
                const cats = loadCats();
                Object.keys(cats).forEach(function (f) { if (cats[f] === key) delete cats[f]; });
                saveCats(cats);
                const ex = loadExtras();
                Object.keys(ex).forEach(function (f) { extraRemove(f, key); });
                scheduleArrangement();
                build();
            }
        });

        // Last: keep the panel open for every click inside it (Bootstrap would otherwise close it on buttons / labels).
        menu.addEventListener('click', function (e) { e.stopPropagation(); });
    }

    if (typeof table !== 'undefined' && table && typeof table.on === 'function') {
        table.on('columnMoved', function () {
            if (applyingOrder) return;
            // A column dragged in the table header: the panel follows (saved order inside each header = table order).
            const tableOrder = currentOrder();
            const trank = function (f) { const i = tableOrder.indexOf(f); return i < 0 ? 1e6 : i; };
            const ord = loadOrder();
            Object.keys(ord).forEach(function (cat) {
                if (!Array.isArray(ord[cat])) return;
                ord[cat] = ord[cat].map(function (f, i) { return { f: f, i: i }; })
                    .sort(function (a, b) { return (trank(a.f) - trank(b.f)) || (a.i - b.i); })
                    .map(function (o) { return o.f; });
            });
            save('order_v1', ord);
            scheduleOrderSave();
            build();
        });
    }
    if (menuEl()) init();
    else document.addEventListener('DOMContentLoaded', init);     // script ran before the panel's HTML

    return { build: build, saveVisibility: saveVisibility, applyAll: applyAll, saveOrder: saveOrderToServer };
})();

// Same names the pages already call.
function buildColumnDropdown() { return tcm.build(); }
function saveColumnVisibilityToServer() { return tcm.saveVisibility(); }
function applyColumnVisibilityFromServer() { return tcm.applyAll(); }
