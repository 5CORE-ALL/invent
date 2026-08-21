{{--
  Shared analytics Columns menu — Reverb style:
  Show All / Show Default + Basic / Pricing / Advertisement / Other groups.
  Include: css | script | all
--}}
@php $colVisPart = $colVisPart ?? 'all'; @endphp

@if($colVisPart === 'css' || $colVisPart === 'all')
        .analytics-col-vis-menu.show,
        .analytics-col-vis-menu.column-dropdown-multicol,
        #column-dropdown-menu.analytics-col-vis-menu {
            min-width: min(92vw, 720px);
            max-width: min(96vw, 780px);
            max-height: 70vh;
            overflow-y: auto;
            padding: 0.4rem 0.5rem 0.55rem;
            column-count: unset !important;
        }
        .analytics-col-vis-menu > li.col-vis-full,
        .analytics-col-vis-menu > li.column-dropdown-span-all {
            list-style: none;
            column-span: all;
        }
        .analytics-col-vis-menu .col-vis-groups {
            display: grid;
            grid-template-columns: repeat(4, minmax(140px, 1fr));
            gap: 8px;
            list-style: none;
            margin: 0;
            padding: 0;
        }
        .analytics-col-vis-menu .col-vis-group {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 6px;
            min-height: 120px;
            display: flex;
            flex-direction: column;
        }
        .analytics-col-vis-menu .col-vis-group.col-vis-drop-over {
            border-color: #0d6efd;
            background: #eef5ff;
            box-shadow: inset 0 0 0 1px rgba(13, 110, 253, 0.25);
        }
        .analytics-col-vis-menu .col-vis-group-title {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #495057;
            margin: 0 0 6px;
            padding: 2px 4px;
            border-bottom: 1px solid #dee2e6;
            user-select: none;
            cursor: pointer;
        }
        .analytics-col-vis-menu .col-vis-group-title input[type="checkbox"] {
            margin: 0;
            flex-shrink: 0;
            cursor: pointer;
        }
        .analytics-col-vis-menu .col-vis-group-list {
            flex: 1;
            min-height: 60px;
            max-height: 320px;
            overflow-y: auto;
            margin: 0;
            padding: 0;
            list-style: none;
        }
        .analytics-col-vis-menu .col-vis-item {
            list-style: none;
            margin: 0;
            padding: 0;
            border-radius: 4px;
            cursor: grab;
        }
        .analytics-col-vis-menu .col-vis-item:active { cursor: grabbing; }
        .analytics-col-vis-menu .col-vis-item.col-vis-dragging { opacity: 0.55; }
        .analytics-col-vis-menu .col-vis-item > label {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 3px 5px;
            cursor: pointer;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin: 0;
            font-size: 0.8rem;
            user-select: none;
        }
        .analytics-col-vis-menu .col-vis-item > label input[type="checkbox"] {
            margin: 0;
            flex-shrink: 0;
            width: 14px;
            height: 14px;
        }
        .analytics-col-vis-menu .col-vis-item > label:hover {
            background: rgba(0, 0, 0, 0.04);
            border-radius: 3px;
        }
        @media (max-width: 720px) {
            .analytics-col-vis-menu .col-vis-groups {
                grid-template-columns: repeat(2, minmax(120px, 1fr));
            }
        }
@endif

@if($colVisPart === 'script' || $colVisPart === 'all')
        (function () {
            if (window.AnalyticsColVis) return;
            const KEYS = ['basic', 'pricing', 'advertisement', 'other'];
            const LABELS = { basic: 'Basic', pricing: 'Pricing', advertisement: 'Advertisement', other: 'Other' };
            const instances = {};

            function plainTitle(def, field) {
                if (typeof window.channelPromoColVisTitle === 'function') {
                    try {
                        const t = window.channelPromoColVisTitle(def, field);
                        if (t) return t;
                    } catch (e) { /* ignore */ }
                }
                const raw = (def && def.title != null) ? def.title : field;
                return String(raw).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim() || String(field || '');
            }

            function classify(field, title) {
                const f = String(field || '');
                const t = String(title || field || '').replace(/<[^>]*>/g, '').toLowerCase();
                const blob = (f + ' ' + t).toLowerCase();
                if (
                    /^(spend|ad_|acos|roas|impressions|t_clicks|bump|bid|campaign|ads_percent|missing_ad|push_bump|re_bid|api_rec)/i.test(f) ||
                    /\b(ads?|spend|acos|roas|impressions|clicks?|bump|bid|campaign|missing ad)\b/i.test(t)
                ) return 'advertisement';
                if (
                    /price|prc|lmp|roi|gpft|gprft|npft|nroi|sprice|sroi|profit|prmt|cpn|ship|sales|std|base_price|recovery|lp_|pft/i.test(blob)
                ) return 'pricing';
                if (
                    t === 'p' || t === 'img' || t === 'image' ||
                    /image|parent|sku|inv|dil|views?|cvr|nr_?req|map|links?|stock|ovl|l30|goods|missing/i.test(blob)
                ) return 'basic';
                return 'other';
            }

            function itemKey(field, title) {
                return String(field || '') + '||' + String(title || field || '');
            }

            function loadOverrides(storageKey) {
                try {
                    const raw = localStorage.getItem(storageKey);
                    const parsed = raw ? JSON.parse(raw) : {};
                    return (parsed && typeof parsed === 'object') ? parsed : {};
                } catch (e) { return {}; }
            }

            function saveOverrides(storageKey, map) {
                try { localStorage.setItem(storageKey, JSON.stringify(map || {})); } catch (e) {}
            }

            function syncGroupHeader(groupEl) {
                if (!groupEl) return;
                const headerCb = groupEl.querySelector('.col-vis-group-toggle');
                const itemCbs = groupEl.querySelectorAll('.col-vis-item input[type="checkbox"]');
                if (!headerCb || !itemCbs.length) return;
                let checked = 0;
                itemCbs.forEach(function (cb) { if (cb.checked) checked++; });
                headerCb.checked = checked === itemCbs.length;
                headerCb.indeterminate = checked > 0 && checked < itemCbs.length;
            }

            function skipField(field, skip) {
                if (!field) return true;
                if (skip.indexOf(field) !== -1) return true;
                return false;
            }

            function rebuild(inst, savedMap) {
                const table = inst.getTable();
                const menu = document.getElementById(inst.menuId);
                if (!menu || !table || typeof table.getColumns !== 'function') return;
                menu.classList.add('analytics-col-vis-menu', 'column-dropdown-multicol');
                const map = (savedMap && typeof savedMap === 'object') ? savedMap : {};
                const skip = inst.skipFields;
                const alwaysHidden = inst.alwaysHidden;
                const overrides = loadOverrides(inst.storageKey);

                menu.innerHTML = '';

                const showAllLi = document.createElement('li');
                showAllLi.className = 'dropdown-item column-dropdown-span-all';
                showAllLi.innerHTML = '<a class="fw-bold" href="#" data-analytics-col-vis="show-all" style="text-decoration:none;color:inherit;">'
                    + '<i class="fa fa-eye"></i> Show All Columns</a>';
                menu.appendChild(showAllLi);

                const showDefaultLi = document.createElement('li');
                showDefaultLi.className = 'dropdown-item column-dropdown-span-all';
                showDefaultLi.innerHTML = '<a class="fw-bold" href="#" data-analytics-col-vis="show-default" style="text-decoration:none;color:inherit;">'
                    + '<i class="fa fa-undo"></i> Show Default Columns</a>';
                menu.appendChild(showDefaultLi);

                const divider = document.createElement('li');
                divider.className = 'column-dropdown-span-all';
                divider.innerHTML = '<hr class="dropdown-divider">';
                menu.appendChild(divider);

                const groupsLi = document.createElement('li');
                groupsLi.className = 'col-vis-full';
                const groupsWrap = document.createElement('div');
                groupsWrap.className = 'col-vis-groups';
                const lists = {};
                const groupEls = {};

                KEYS.forEach(function (cat) {
                    const group = document.createElement('div');
                    group.className = 'col-vis-group';
                    group.dataset.category = cat;
                    const titleEl = document.createElement('label');
                    titleEl.className = 'col-vis-group-title';
                    const groupCb = document.createElement('input');
                    groupCb.type = 'checkbox';
                    groupCb.className = 'col-vis-group-toggle';
                    groupCb.dataset.group = cat;
                    groupCb.title = 'Select / deselect all in ' + LABELS[cat];
                    titleEl.appendChild(groupCb);
                    titleEl.appendChild(document.createTextNode(LABELS[cat]));
                    group.appendChild(titleEl);
                    const list = document.createElement('ul');
                    list.className = 'col-vis-group-list';
                    list.dataset.category = cat;
                    group.appendChild(list);
                    groupsWrap.appendChild(group);
                    lists[cat] = list;
                    groupEls[cat] = group;

                    [group, list].forEach(function (zone) {
                        zone.addEventListener('dragover', function (e) {
                            e.preventDefault();
                            e.stopPropagation();
                            group.classList.add('col-vis-drop-over');
                            e.dataTransfer.dropEffect = 'move';
                        });
                        zone.addEventListener('dragleave', function (e) {
                            if (!group.contains(e.relatedTarget)) group.classList.remove('col-vis-drop-over');
                        });
                        zone.addEventListener('drop', function (e) {
                            e.preventDefault();
                            e.stopPropagation();
                            group.classList.remove('col-vis-drop-over');
                            const key = e.dataTransfer.getData('text/col-vis-key');
                            if (!key) return;
                            const next = loadOverrides(inst.storageKey);
                            next[key] = cat;
                            saveOverrides(inst.storageKey, next);
                            rebuild(inst, null);
                        });
                    });
                });

                table.getColumns().forEach(function (col) {
                    const def = col.getDefinition() || {};
                    const field = def.field || col.getField();
                    if (skipField(field, skip) || alwaysHidden.indexOf(field) !== -1) return;
                    const title = (typeof inst.plainTitle === 'function')
                        ? inst.plainTitle(def, field)
                        : plainTitle(def, field);
                    if (!title) return;
                    const key = itemKey(field, title);
                    let cat = overrides[key];
                    if (KEYS.indexOf(cat) === -1) cat = (inst.classify || classify)(field, title);
                    const isVisible = map.hasOwnProperty(field) ? (map[field] !== false) : col.isVisible();

                    const li = document.createElement('li');
                    li.className = 'col-vis-item';
                    li.draggable = true;
                    li.dataset.itemKey = key;
                    li.dataset.field = field;
                    li.dataset.group = cat;
                    li.addEventListener('dragstart', function (e) {
                        e.stopPropagation();
                        li.classList.add('col-vis-dragging');
                        e.dataTransfer.setData('text/col-vis-key', key);
                        e.dataTransfer.effectAllowed = 'move';
                    });
                    li.addEventListener('dragend', function () {
                        li.classList.remove('col-vis-dragging');
                        menu.querySelectorAll('.col-vis-drop-over').forEach(function (el) {
                            el.classList.remove('col-vis-drop-over');
                        });
                    });

                    const label = document.createElement('label');
                    const checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.value = field;
                    checkbox.setAttribute('data-field', field);
                    checkbox.className = 'col-vis-field-toggle';
                    checkbox.dataset.group = cat;
                    checkbox.checked = !!isVisible;
                    label.appendChild(checkbox);
                    label.appendChild(document.createTextNode(' ' + title));
                    label.title = title + ' (drag to another header)';
                    li.appendChild(label);
                    lists[cat].appendChild(li);
                });

                KEYS.forEach(function (cat) { syncGroupHeader(groupEls[cat]); });
                groupsLi.appendChild(groupsWrap);
                menu.appendChild(groupsLi);
            }

            function setColumnVisible(table, field, visible) {
                try {
                    const col = table.getColumn(field);
                    if (!col) return;
                    if (visible) col.show();
                    else col.hide();
                } catch (e) { /* ignore */ }
            }

            function bindMenu(inst) {
                const menu = document.getElementById(inst.menuId);
                if (!menu || menu._analyticsColVisBound) return;
                menu._analyticsColVisBound = true;
                menu.classList.add('analytics-col-vis-menu');

                const toggle = document.querySelector('[data-bs-toggle="dropdown"][aria-labelledby="' + inst.menuId + '"], [aria-controls="' + inst.menuId + '"]');
                const btn = document.getElementById(inst.menuId.replace('-menu', 'VisibilityDropdown'))
                    || document.querySelector('[data-bs-toggle="dropdown"][id*="olumn"]');
                if (btn && !btn.getAttribute('data-bs-auto-close')) {
                    btn.setAttribute('data-bs-auto-close', 'outside');
                }

                menu.addEventListener('click', function (e) {
                    if (e.target.closest('label') || (e.target && e.target.type === 'checkbox')) {
                        e.stopPropagation();
                    }
                    const showAll = e.target.closest('[data-analytics-col-vis="show-all"]');
                    const showDefault = e.target.closest('[data-analytics-col-vis="show-default"]');
                    if (!showAll && !showDefault) return;
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    const table = inst.getTable();
                    if (!table) return;
                    table.getColumns().forEach(function (col) {
                        const def = col.getDefinition() || {};
                        const field = def.field || col.getField();
                        if (skipField(field, inst.skipFields) || inst.alwaysHidden.indexOf(field) !== -1) return;
                        if (showAll) {
                            col.show();
                            return;
                        }
                        const designedOn = def.visible !== false;
                        if (designedOn) col.show();
                        else col.hide();
                    });
                    rebuild(inst, null);
                    if (typeof inst.onSave === 'function') inst.onSave();
                }, true);

                menu.addEventListener('change', function (e) {
                    if (!e.target || e.target.type !== 'checkbox') return;
                    if (!e.target.classList.contains('col-vis-group-toggle')
                        && !e.target.classList.contains('col-vis-field-toggle')) return;
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    const table = inst.getTable();
                    if (!table) return;
                    if (e.target.classList.contains('col-vis-group-toggle')) {
                        const checked = e.target.checked;
                        const groupEl = e.target.closest('.col-vis-group');
                        const itemCbs = groupEl ? groupEl.querySelectorAll('.col-vis-item input[type="checkbox"]') : [];
                        itemCbs.forEach(function (cb) {
                            const field = cb.getAttribute('data-field') || cb.value;
                            cb.checked = checked;
                            setColumnVisible(table, field, checked);
                        });
                        e.target.indeterminate = false;
                        if (typeof inst.onSave === 'function') inst.onSave();
                        return;
                    }
                    const field = e.target.getAttribute('data-field') || e.target.dataset.field;
                    if (!field) return;
                    setColumnVisible(table, field, !!e.target.checked);
                    syncGroupHeader(e.target.closest('.col-vis-group'));
                    if (typeof inst.onSave === 'function') inst.onSave();
                }, true);
            }

            window.AnalyticsColVis = {
                install: function (opts) {
                    opts = opts || {};
                    const menuId = opts.menuId || 'column-dropdown-menu';
                    const inst = {
                        menuId: menuId,
                        getTable: typeof opts.getTable === 'function' ? opts.getTable : function () { return opts.table; },
                        storageKey: opts.storageKey || (menuId + '_col_cats_v1'),
                        skipFields: opts.skipFields || ['_select'],
                        alwaysHidden: opts.alwaysHidden || [],
                        onSave: opts.onSave || function () {},
                        classify: opts.classify || classify,
                        plainTitle: opts.plainTitle || null,
                    };
                    instances[menuId] = inst;
                    bindMenu(inst);
                    return {
                        rebuild: function (savedMap) { rebuild(inst, savedMap || null); }
                    };
                },
                rebuild: function (savedMap, menuId) {
                    const id = menuId || 'column-dropdown-menu';
                    const inst = instances[id];
                    if (!inst) return false;
                    rebuild(inst, savedMap || null);
                    return true;
                }
            };
        })();
@endif
