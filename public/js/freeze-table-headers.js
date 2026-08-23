/**
 * Freeze (sticky) table header rows on every page.
 * - HTML tables: sticky thead + a viewport-capped scroll wrapper when needed
 * - Tabulator: setHeight to remaining viewport so its built-in header stays put
 * Opt out: add data-no-freeze-header on the table or an ancestor.
 */
(function () {
    const SKIP = '[data-no-freeze-header]';
    const WRAP_SEL = '.table-responsive, .table-container, .table-scroll, .followup-table-scroll';
    let scheduled = 0;

    function chromeBottom() {
        let bottom = 0;
        document.querySelectorAll('.navbar-custom, .mobile-header').forEach(function (el) {
            const s = getComputedStyle(el);
            if (s.display === 'none' || s.visibility === 'hidden') return;
            if (s.position !== 'fixed' && s.position !== 'sticky') return;
            bottom = Math.max(bottom, Math.round(el.getBoundingClientRect().bottom));
        });
        return bottom;
    }

    function remainingHeight(el) {
        const modalBody = el.closest('.modal-body');
        if (modalBody) {
            const top = el.getBoundingClientRect().top - modalBody.getBoundingClientRect().top;
            return Math.max(160, Math.floor(modalBody.clientHeight - top - 8));
        }
        const top = el.getBoundingClientRect().top;
        return Math.max(200, Math.floor(window.innerHeight - top - 12));
    }

    function skipped(el) {
        return !el || (el.closest && el.closest(SKIP));
    }

    function freezeTabulator(el) {
        if (skipped(el) || el.closest('.modal') && !el.closest('.modal.show')) return;
        const inst = el.tabulator;
        const optH = inst && inst.options ? inst.options.height : null;
        if (optH) return;

        const parent = el.parentElement;
        if (parent && parent.style && parent.style.height && parent.style.height !== 'auto') return;

        const h = remainingHeight(el);
        if (el.offsetHeight <= h + 8) return;

        const key = String(h);
        if (el.dataset.freezeH === key) return;
        el.dataset.freezeH = key;
        el.style.height = h + 'px';
        if (inst && typeof inst.setHeight === 'function') {
            try { inst.setHeight(h); } catch (e) { /* ignore */ }
        }
    }

    function freezeWrap(wrap) {
        if (skipped(wrap) || wrap.closest('.tabulator')) return;
        if (wrap.closest('.dropdown-menu, .select2, .flatpickr-calendar, .daterangepicker')) return;

        const h = remainingHeight(wrap);
        if (wrap.scrollHeight <= h + 8) {
            if (wrap.dataset.freezeH) {
                wrap.style.maxHeight = '';
                delete wrap.dataset.freezeH;
            }
            wrap.querySelectorAll('table').forEach(function (table) {
                table.style.setProperty('--app-freeze-top', '0px');
            });
            return;
        }

        const key = String(h);
        if (wrap.dataset.freezeH !== key) {
            wrap.dataset.freezeH = key;
            wrap.style.maxHeight = h + 'px';
            wrap.style.overflow = 'auto';
        }
        wrap.querySelectorAll('table').forEach(function (table) {
            table.style.setProperty('--app-freeze-top', '0px');
        });
    }

    function freezeBareTable(table) {
        if (skipped(table)) return;
        if (table.closest('.tabulator, .dropdown-menu, .select2, .flatpickr-calendar, .daterangepicker')) return;
        if (table.closest(WRAP_SEL)) return;

        const top = chromeBottom();
        table.style.setProperty('--app-freeze-top', top + 'px');
    }

    function run() {
        document.querySelectorAll('.tabulator').forEach(freezeTabulator);
        document.querySelectorAll(WRAP_SEL).forEach(freezeWrap);
        document.querySelectorAll('table').forEach(freezeBareTable);
    }

    function schedule() {
        if (scheduled) return;
        scheduled = requestAnimationFrame(function () {
            scheduled = 0;
            run();
        });
    }

    window.freezeTableHeaders = run;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', schedule);
    } else {
        schedule();
    }
    window.addEventListener('resize', schedule);
    window.addEventListener('load', schedule);

    if (document.body) {
        const obs = new MutationObserver(schedule);
        obs.observe(document.body, { childList: true, subtree: true });
    }
})();
