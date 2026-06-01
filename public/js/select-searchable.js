/**
 * Makes any <select class="select-searchable"> searchable (filterable list).
 * Requires select-searchable.css
 *
 * API:
 *   SelectSearchable.init(root?)     — enhance all matching selects under root (default document)
 *   SelectSearchable.refresh(sel)   — after options change
 *   SelectSearchable.destroy(sel)   — remove widget, leave plain <select>
 */
(function (global) {
    'use strict';

    var installed = false;

    function closeWrap(wrap) {
        if (!wrap) {
            return;
        }
        var panel = wrap.querySelector('.select-searchable-panel');
        var btn = wrap.querySelector('.select-searchable-display');
        if (panel) {
            panel.hidden = true;
        }
        if (btn) {
            btn.setAttribute('aria-expanded', 'false');
        }
        wrap.classList.remove('is-ss-open');
    }

    function closeAllExcept(exceptWrap) {
        document.querySelectorAll('.select-searchable-wrap.is-ss-open').forEach(function (w) {
            if (w !== exceptWrap) {
                closeWrap(w);
            }
        });
    }

    function ensureGlobalHandlers() {
        if (installed) {
            return;
        }
        installed = true;
        document.addEventListener('click', function (e) {
            document.querySelectorAll('.select-searchable-wrap').forEach(function (wrap) {
                if (!wrap.contains(e.target)) {
                    closeWrap(wrap);
                }
            });
        });
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') {
                return;
            }
            document.querySelectorAll('.select-searchable-wrap.is-ss-open').forEach(closeWrap);
        });
    }

    function destroy(select) {
        if (!select || !select.classList.contains('select-searchable')) {
            return;
        }
        var wrap = select.closest('.select-searchable-wrap');
        if (!wrap || !wrap.parentNode) {
            return;
        }
        select.classList.remove('select-searchable-native');
        delete select.dataset.ssBound;
        wrap.parentNode.insertBefore(select, wrap);
        wrap.remove();
    }

    function initOne(select) {
        if (!select || !select.classList.contains('select-searchable')) {
            return;
        }
        if (select.dataset.ssBound === '1') {
            destroy(select);
        }

        ensureGlobalHandlers();

        var parent = select.parentNode;
        var wrap = document.createElement('div');
        wrap.className = 'select-searchable-wrap';

        var display = document.createElement('button');
        display.type = 'button';
        display.className = 'select-searchable-display form-select form-select-sm';
        display.setAttribute('aria-haspopup', 'listbox');
        display.setAttribute('aria-expanded', 'false');

        var panel = document.createElement('div');
        panel.className = 'select-searchable-panel';
        panel.hidden = true;
        panel.setAttribute('role', 'presentation');

        var search = document.createElement('input');
        search.type = 'search';
        search.className = 'form-control form-control-sm select-searchable-filter';
        search.placeholder = 'Type to search…';
        search.setAttribute('autocomplete', 'off');
        search.setAttribute('aria-label', 'Filter options');

        var list = document.createElement('ul');
        list.className = 'select-searchable-list';
        list.setAttribute('role', 'listbox');

        parent.insertBefore(wrap, select);
        wrap.appendChild(select);
        wrap.appendChild(display);
        wrap.appendChild(panel);
        panel.appendChild(search);
        panel.appendChild(list);

        select.classList.add('select-searchable-native');
        select.dataset.ssBound = '1';

        function currentLabel() {
            var opt = select.options[select.selectedIndex];
            return opt ? opt.textContent.trim() : '';
        }

        function syncDisplay() {
            var t = currentLabel();
            display.textContent = t || '—';
        }

        function renderList(filterText) {
            var q = (filterText || '').trim().toLowerCase();
            list.innerHTML = '';
            var count = 0;
            Array.prototype.forEach.call(select.options, function (opt) {
                if (opt.disabled) {
                    return;
                }
                var label = opt.textContent.trim();
                if (q && label.toLowerCase().indexOf(q) === -1) {
                    return;
                }
                count++;
                var li = document.createElement('li');
                li.className = 'select-searchable-item';
                li.setAttribute('role', 'option');
                li.dataset.value = opt.value;
                li.textContent = label;
                if (opt.selected) {
                    li.classList.add('is-active');
                    li.setAttribute('aria-selected', 'true');
                }
                list.appendChild(li);
            });
            if (count === 0) {
                var empty = document.createElement('li');
                empty.className = 'select-searchable-empty';
                empty.setAttribute('role', 'presentation');
                empty.textContent = 'No matches';
                list.appendChild(empty);
            }
        }

        function openPanel() {
            closeAllExcept(wrap);
            panel.hidden = false;
            wrap.classList.add('is-ss-open');
            display.setAttribute('aria-expanded', 'true');
            search.value = '';
            renderList('');
            setTimeout(function () {
                search.focus();
            }, 0);
        }

        function togglePanel(e) {
            if (e) {
                e.stopPropagation();
            }
            if (panel.hidden) {
                openPanel();
            } else {
                closeWrap(wrap);
            }
        }

        display.addEventListener('click', togglePanel);

        search.addEventListener('input', function () {
            renderList(search.value);
        });

        search.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                e.preventDefault();
                closeWrap(wrap);
                display.focus();
            }
        });

        list.addEventListener('click', function (e) {
            var li = e.target.closest('.select-searchable-item');
            if (!li || li.classList.contains('select-searchable-empty')) {
                return;
            }
            var val = li.dataset.value;
            select.value = val;
            select.dispatchEvent(new Event('input', { bubbles: true }));
            select.dispatchEvent(new Event('change', { bubbles: true }));
            syncDisplay();
            closeWrap(wrap);
        });

        select.addEventListener('change', syncDisplay);

        syncDisplay();
        renderList('');
    }

    function initAll(root) {
        var scope = root && root.querySelectorAll ? root : document;
        var list = scope.querySelectorAll
            ? scope.querySelectorAll('select.select-searchable')
            : document.querySelectorAll('select.select-searchable');
        Array.prototype.forEach.call(list, function (sel) {
            if (!sel.closest('.select-searchable-wrap')) {
                initOne(sel);
            }
        });
    }

    function refresh(select) {
        if (!select) {
            return;
        }
        destroy(select);
        initOne(select);
    }

    var api = {
        init: initAll,
        refresh: refresh,
        destroy: destroy,
    };

    global.SelectSearchable = api;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initAll();
        });
    } else {
        initAll();
    }
})(typeof window !== 'undefined' ? window : this);
