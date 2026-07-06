/**
 * Turns any <select class="exec-typeahead"> into a typeahead text input:
 * the user types and a live suggestions list drops down (no click-to-open needed).
 * Selecting a suggestion writes the value back to the hidden native <select> and
 * fires input/change events, so existing filter handlers keep working unchanged.
 *
 * Requires exec-typeahead.css
 *
 * API:
 *   ExecTypeahead.init(root?)   — enhance all matching selects under root (default document)
 *   ExecTypeahead.initOne(sel)  — enhance a single <select>
 */
(function (global) {
    'use strict';

    var installed = false;

    function closeWrap(wrap) {
        if (!wrap) {
            return;
        }
        var panel = wrap.querySelector('.exec-typeahead-panel');
        if (panel) {
            panel.hidden = true;
        }
        wrap.classList.remove('is-open');
    }

    function ensureGlobalHandlers() {
        if (installed) {
            return;
        }
        installed = true;
        document.addEventListener('click', function (e) {
            document.querySelectorAll('.exec-typeahead-wrap.is-open').forEach(function (wrap) {
                if (!wrap.contains(e.target)) {
                    closeWrap(wrap);
                }
            });
        });
    }

    function inputClassFor(select) {
        return (select.className || '')
            .replace(/\bexec-typeahead\b/g, '')
            .replace(/\bselect-searchable\b/g, '')
            .replace(/\bform-select-sm\b/g, 'form-control-sm')
            .replace(/\bform-select\b/g, 'form-control')
            .replace(/\s+/g, ' ')
            .trim() + ' exec-typeahead-input';
    }

    function initOne(select) {
        if (!select || select.dataset.etaBound === '1') {
            return;
        }
        ensureGlobalHandlers();
        select.dataset.etaBound = '1';

        var parent = select.parentNode;
        var wrap = document.createElement('div');
        wrap.className = 'exec-typeahead-wrap';

        var input = document.createElement('input');
        input.type = 'text';
        input.className = inputClassFor(select);
        input.setAttribute('autocomplete', 'off');
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.placeholder = select.getAttribute('data-eta-placeholder')
            || select.getAttribute('title')
            || 'Search…';

        var panel = document.createElement('div');
        panel.className = 'exec-typeahead-panel';
        panel.hidden = true;

        var list = document.createElement('ul');
        list.className = 'exec-typeahead-list';
        list.setAttribute('role', 'listbox');

        parent.insertBefore(wrap, select);
        wrap.appendChild(select);
        wrap.appendChild(input);
        wrap.appendChild(panel);
        panel.appendChild(list);

        select.style.display = 'none';
        select.tabIndex = -1;

        var visibleItems = [];
        var activeIndex = -1;

        function currentLabel() {
            var opt = select.options[select.selectedIndex];
            return opt ? opt.textContent.trim() : '';
        }

        function syncInput() {
            var opt = select.options[select.selectedIndex];
            // When the default "all"/empty option is selected, leave the box empty so
            // the placeholder ("Search executive…") shows instead of a label.
            input.value = (opt && opt.value !== '') ? opt.textContent.trim() : '';
        }

        function choose(value, label) {
            select.value = value;
            input.value = label;
            select.dispatchEvent(new Event('input', { bubbles: true }));
            select.dispatchEvent(new Event('change', { bubbles: true }));
            closeWrap(wrap);
        }

        function setActive(i) {
            visibleItems.forEach(function (li, idx) {
                li.classList.toggle('is-active', idx === i);
            });
            activeIndex = i;
            if (visibleItems[i]) {
                visibleItems[i].scrollIntoView({ block: 'nearest' });
            }
        }

        function render(filterText) {
            var q = (filterText || '').trim().toLowerCase();
            list.innerHTML = '';
            visibleItems = [];
            activeIndex = -1;

            Array.prototype.forEach.call(select.options, function (opt) {
                if (opt.disabled) {
                    return;
                }
                var label = opt.textContent.trim();
                if (q && label.toLowerCase().indexOf(q) === -1) {
                    return;
                }
                var li = document.createElement('li');
                li.className = 'exec-typeahead-item';
                li.setAttribute('role', 'option');
                li.dataset.value = opt.value;
                li.textContent = label;
                if (opt.selected) {
                    li.classList.add('is-selected');
                }
                li.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    choose(opt.value, label);
                });
                list.appendChild(li);
                visibleItems.push(li);
            });

            if (visibleItems.length === 0) {
                var empty = document.createElement('li');
                empty.className = 'exec-typeahead-empty';
                empty.textContent = 'No matches';
                list.appendChild(empty);
            }
        }

        function openPanel(filterText) {
            render(filterText);
            panel.hidden = false;
            wrap.classList.add('is-open');
        }

        input.addEventListener('focus', function () {
            openPanel('');
            input.select();
        });

        input.addEventListener('input', function () {
            openPanel(input.value);
        });

        input.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (panel.hidden) {
                    openPanel(input.value);
                }
                setActive(Math.min(activeIndex + 1, visibleItems.length - 1));
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                setActive(Math.max(activeIndex - 1, 0));
            } else if (e.key === 'Enter') {
                if (!panel.hidden && activeIndex >= 0 && visibleItems[activeIndex]) {
                    e.preventDefault();
                    var li = visibleItems[activeIndex];
                    choose(li.dataset.value, li.textContent);
                }
            } else if (e.key === 'Escape') {
                closeWrap(wrap);
                syncInput();
            }
        });

        input.addEventListener('blur', function () {
            // Restore the selected label if the user typed but didn't pick anything.
            setTimeout(syncInput, 150);
        });

        select.addEventListener('change', syncInput);
        // Lets host pages refresh the visible input after they set select.value
        // programmatically (e.g. a "reset filters" button) without re-running filters.
        select.addEventListener('eta:sync', syncInput);

        syncInput();
    }

    function initAll(root) {
        var scope = root && root.querySelectorAll ? root : document;
        scope.querySelectorAll('select.exec-typeahead').forEach(function (sel) {
            if (!sel.closest('.exec-typeahead-wrap')) {
                initOne(sel);
            }
        });
    }

    global.ExecTypeahead = { init: initAll, initOne: initOne };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initAll();
        });
    } else {
        initAll();
    }
})(typeof window !== 'undefined' ? window : this);
