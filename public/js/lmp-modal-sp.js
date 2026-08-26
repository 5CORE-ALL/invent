/**
 * Shared SP (Standard Price) UI for all LMP competitor modals:
 *  1) SP input box at top (skipped when Amazon already has #lmpModalSpInput)
 *  2) SP column in competitor tables (after Price / Total), synced with the input
 * Loads/saves amazon_data_view.STANDARD_PRICE via /amazon-standard-price + /save-amazon-sprice.
 */
(function () {
    'use strict';

    var SP_BOX_HTML =
        '<div class="card mb-3 border-primary lmp-modal-sp-box">' +
            '<div class="card-body py-2">' +
                '<div class="row g-2 align-items-end">' +
                    '<div class="col-auto">' +
                        '<label class="form-label mb-0 small fw-bold">Std Prc</label>' +
                        '<input type="number" class="form-control form-control-sm text-end fw-bold lmp-modal-sp-input" ' +
                            'step="0.01" min="0.01" placeholder="0.00" style="width: 7rem;" ' +
                            'title="Std Prc">' +
                    '</div>' +
                '</div>' +
            '</div>' +
        '</div>';

    // Compact SP control for Comparison LMP modal header (centered, saves vertical space)
    var SP_HEADER_HTML =
        '<div class="lmp-modal-sp-header-wrap" title="Standard Price (manual). Saves to SP for this SKU and Sku Link LMP siblings.">' +
            '<label class="lmp-modal-sp-header-label mb-0" for="comparison-lmp-modal-sp-input">SP</label>' +
            '<input type="number" id="comparison-lmp-modal-sp-input" ' +
                'class="form-control form-control-sm text-end fw-bold lmp-modal-sp-input" ' +
                'step="0.01" min="0.01" placeholder="0.00" ' +
                'title="Manual Standard Price — use when LMP cannot be determined. Saves to SP / STD PRC.">' +
        '</div>';

    var SP_TH_HTML =
        '<th class="text-center lmp-sp-col-th" style="width:70px;" ' +
        'title="Standard Price (SP) — from SP input above. Blank unless filled when LMP cannot be determined.">SP</th>';

    var observers = new WeakMap();

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function toast(msg, type) {
        if (typeof window.showToast === 'function') {
            try { window.showToast(msg, type || 'success'); return; } catch (e) {}
        }
        if (window.jQuery && typeof window.toastr !== 'undefined') {
            try { window.toastr[type === 'error' ? 'error' : 'success'](msg); return; } catch (e) {}
        }
    }

    function isLmpCompetitorModal(el) {
        if (!el || !el.id) return false;
        if (el.getAttribute && el.getAttribute('data-skip-lmp-sp') === '1') return false;
        var id = String(el.id).toLowerCase();
        // Sku Link manager (link SKUs together) — not a competitor LMP modal
        if (id === 'skulinklmpmodal') return false;
        if (id.indexOf('upload') !== -1) return false;
        if (id.indexOf('history') !== -1) return false;
        if (id.indexOf('rule') !== -1) return false;
        return (
            id.indexOf('lmpmodal') !== -1 ||
            id.indexOf('lmp_modal') !== -1 ||
            id.indexOf('lmpdetails') !== -1 ||
            id === 'competitorsmodal' ||
            id.indexOf('competitorsmodal') !== -1
        );
    }

    function readSkuText(el) {
        if (!el) return '';
        var t = String(
            (el.tagName === 'INPUT' || el.tagName === 'SELECT' || el.tagName === 'TEXTAREA')
                ? (el.value || '')
                : (el.textContent || '')
        ).trim();
        if (!t || t === '—' || t === '-' || t.toUpperCase().indexOf('PARENT') !== -1) return '';
        return t;
    }

    function resolveSku(modal) {
        if (!modal) return '';
        var selectors = [
            '#lmpSku',
            '#lmpModalSku',
            '#aeLmpModalSku',
            '#sheinLmpSku',
            '#ttLmpSku',
            '#neLmpSku',
            '#comparison-lmp-modal-sku',
            '#toa-lmp-modal-sku',
            '#toaLmpModalSku',
            '#amzCvrLmpSku',
            '#sku-link-lmp-lmp-sku',
            '#sku-link-lmp-amz-lmp-sku',
            '[data-lmp-sku]',
            '.lmp-modal-sku',
            '#lmp-sku'
        ];
        var i, el, t;
        for (i = 0; i < selectors.length; i++) {
            t = readSkuText(modal.querySelector(selectors[i]));
            if (t) return t;
        }
        var candidates = modal.querySelectorAll('[id]');
        for (i = 0; i < candidates.length; i++) {
            el = candidates[i];
            var id = String(el.id || '').toLowerCase();
            if (
                (id.indexOf('lmpsku') !== -1 || id.indexOf('lmp-sku') !== -1 || id.indexOf('lmp_sku') !== -1)
                && id.indexOf('add') === -1
                && id.indexOf('comp') === -1
            ) {
                t = readSkuText(el);
                if (t) return t;
            }
        }
        if (modal.dataset && modal.dataset.sku) {
            return String(modal.dataset.sku).trim();
        }
        var title = modal.querySelector('.modal-title');
        if (title) {
            var text = String(title.textContent || '').trim();
            var m = text.match(/LMP:\s*(.+)$/i)
                || text.match(/SKU:\s*(.+)$/i)
                || text.match(/for\s+SKU:\s*(.+)$/i)
                || text.match(/for\s+([A-Za-z0-9][\w\-]*)$/i);
            if (m && m[1]) {
                var sku = String(m[1]).trim();
                if (sku && sku.indexOf(' ') === -1) return sku;
            }
        }
        return '';
    }

    function getSpInput(modal) {
        if (!modal) return null;
        return modal.querySelector('#lmpModalSpInput') || modal.querySelector('.lmp-modal-sp-input');
    }

    function formatSpDisplay(value) {
        var n = parseFloat(value);
        if (isFinite(n) && n > 0) return '$' + n.toFixed(2);
        return '-';
    }

    function readSpValue(modal) {
        var input = getSpInput(modal);
        if (!input) return null;
        var n = parseFloat(input.value);
        return (isFinite(n) && n > 0) ? n : null;
    }

    function ensureSpBox(modal) {
        if (!modal) return null;
        // Amazon (and any page) that already has the full SP UI — don't duplicate the box
        if (modal.querySelector('#lmpModalSpInput')) {
            return modal.querySelector('#lmpModalSpInput');
        }

        var isComparisonLmp = modal.id === 'comparisonLmpModal';

        // Comparison page: keep SP in the modal header center (remove legacy body card if present)
        if (isComparisonLmp) {
            var bodyBox = modal.querySelector('.modal-body .lmp-modal-sp-box');
            if (bodyBox) {
                bodyBox.remove();
            }
            var headerExisting = modal.querySelector('.lmp-modal-sp-header-wrap .lmp-modal-sp-input');
            if (headerExisting) {
                return headerExisting;
            }
            var header = modal.querySelector('.modal-header');
            if (!header) {
                return null;
            }
            var closeBtn = header.querySelector('.btn-close');
            if (closeBtn) {
                closeBtn.insertAdjacentHTML('beforebegin', SP_HEADER_HTML);
            } else {
                header.insertAdjacentHTML('beforeend', SP_HEADER_HTML);
            }
            return modal.querySelector('.lmp-modal-sp-header-wrap .lmp-modal-sp-input');
        }

        var existing = modal.querySelector('.lmp-modal-sp-input');
        if (existing) return existing;
        var body = modal.querySelector('.modal-body');
        if (!body) return null;
        body.insertAdjacentHTML('afterbegin', SP_BOX_HTML);
        return modal.querySelector('.lmp-modal-sp-input');
    }

    function setInputValue(input, value) {
        if (!input) return;
        if (value != null && isFinite(value) && Number(value) > 0) {
            input.value = Number(value).toFixed(2);
        } else {
            input.value = '';
        }
    }

    function syncSpColumnCells(modal) {
        if (!modal) return;
        var text = formatSpDisplay(readSpValue(modal));
        modal.querySelectorAll('.lmp-sp-cell').forEach(function (cell) {
            cell.textContent = text;
        });
    }

    function headerText(th) {
        return String(th.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
    }

    function tableHasSpColumn(table) {
        if (!table || !table.tHead) return false;
        if (table.querySelector('.lmp-sp-col-th, .lmp-sp-cell')) return true;
        var ths = table.tHead.querySelectorAll('th');
        for (var i = 0; i < ths.length; i++) {
            if (headerText(ths[i]) === 'sp') return true;
        }
        return false;
    }

    function isCompetitorTable(table) {
        if (!table || !table.tHead) return false;
        // Skip tiny form-only tables
        var ths = table.tHead.querySelectorAll('th');
        if (!ths.length) return false;
        var joined = Array.prototype.map.call(ths, headerText).join('|');
        return /price|total|image|title|asin|item|seller|shipping|p\+s|del|rating/.test(joined);
    }

    function findInsertAfterIndex(ths) {
        var preferred = ['price', 'total', 'p+s', 'p + s'];
        var i, p, text;
        for (p = 0; p < preferred.length; p++) {
            for (i = 0; i < ths.length; i++) {
                text = headerText(ths[i]);
                if (text === preferred[p] || text.indexOf(preferred[p]) === 0) {
                    return i;
                }
            }
        }
        // Before Actions / Ignore if present
        for (i = 0; i < ths.length; i++) {
            text = headerText(ths[i]);
            if (text === 'actions' || text === 'ignore' || text === 'ign' || text === '') {
                return Math.max(0, i - 1);
            }
        }
        return ths.length - 1;
    }

    function ensureSpColumnInTable(modal, table) {
        if (!modal || !table || !isCompetitorTable(table) || tableHasSpColumn(table)) return false;

        var headerRow = table.tHead.querySelector('tr');
        if (!headerRow) return false;
        var ths = headerRow.querySelectorAll('th');
        if (!ths.length) return false;

        var afterIdx = findInsertAfterIndex(ths);
        var refTh = ths[afterIdx];
        if (!refTh) return false;
        refTh.insertAdjacentHTML('afterend', SP_TH_HTML);

        var insertAt = afterIdx + 1;
        var spText = formatSpDisplay(readSpValue(modal));
        var rows = table.tBodies && table.tBodies[0]
            ? table.tBodies[0].rows
            : table.querySelectorAll('tbody tr');

        Array.prototype.forEach.call(rows, function (tr) {
            // Skip spacer / message rows with a single colspan cell
            if (tr.cells.length === 1 && tr.cells[0].colSpan > 1) {
                tr.cells[0].colSpan = tr.cells[0].colSpan + 1;
                return;
            }
            var cell = document.createElement('td');
            cell.className = 'text-center fw-bold lmp-sp-cell';
            cell.textContent = spText;
            if (tr.cells[insertAt]) {
                tr.insertBefore(cell, tr.cells[insertAt]);
            } else {
                tr.appendChild(cell);
            }
        });

        // Mark so we don't double-process this exact DOM table node
        table.classList.add('lmp-sp-col-ready');
        return true;
    }

    function ensureSpColumns(modal) {
        if (!modal) return;
        var tables = modal.querySelectorAll('table');
        for (var i = 0; i < tables.length; i++) {
            ensureSpColumnInTable(modal, tables[i]);
        }
        syncSpColumnCells(modal);
    }

    function observeModalTables(modal) {
        if (!modal || observers.has(modal)) return;
        var body = modal.querySelector('.modal-body') || modal;
        var timer = null;
        var obs = new MutationObserver(function () {
            if (timer) clearTimeout(timer);
            timer = setTimeout(function () {
                ensureSpColumns(modal);
            }, 50);
        });
        obs.observe(body, { childList: true, subtree: true });
        observers.set(modal, obs);
    }

    function loadStandardPrice(modal, input) {
        var sku = resolveSku(modal);
        if (!sku || !input) return;
        input.dataset.lmpSpSku = sku;

        var url = '/amazon-standard-price?sku=' + encodeURIComponent(sku);
        function apply(res) {
            setInputValue(input, res && res.standard_price);
            syncSpColumnCells(modal);
        }
        if (window.jQuery) {
            window.jQuery.getJSON(url)
                .done(apply)
                .fail(function () { apply(null); });
            return;
        }
        fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(apply)
            .catch(function () { apply(null); });
    }

    function saveStandardPrice(input) {
        var modal = input.closest('.modal');
        var sku = (input.dataset.lmpSpSku || resolveSku(modal) || '').trim();
        var raw = String(input.value || '').trim();
        if (!sku) {
            toast('SKU not found for SP save', 'error');
            return;
        }
        if (raw === '') {
            syncSpColumnCells(modal);
            return;
        }
        var std = parseFloat(raw);
        if (!isFinite(std) || std <= 0) {
            input.value = '';
            syncSpColumnCells(modal);
            return;
        }

        input.style.borderColor = '#20c997';
        var payload = {
            sku: sku,
            sprice: std,
            is_standard_price: 1,
            _token: csrfToken()
        };

        function onSuccess(response) {
            var saved = parseFloat((response && (response.data || response.STANDARD_PRICE)) || std) || std;
            setInputValue(input, saved);
            syncSpColumnCells(modal);
            input.style.borderColor = '#28a745';
            var n = (response && Array.isArray(response.applied_skus)) ? response.applied_skus.length : 1;
            toast(n > 1 ? ('SP saved for ' + n + ' linked SKUs') : 'SP saved', 'success');
            document.dispatchEvent(new CustomEvent('lmp-modal-sp-saved', {
                detail: {
                    sku: sku,
                    standard_price: saved,
                    applied_skus: (response && response.applied_skus) || [sku]
                }
            }));
            setTimeout(function () { input.style.borderColor = ''; }, 800);
        }

        function onError() {
            input.style.borderColor = '#dc3545';
            toast('Failed to save SP', 'error');
        }

        if (window.jQuery) {
            window.jQuery.ajax({
                url: '/save-amazon-sprice',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken() },
                data: payload,
                success: onSuccess,
                error: onError
            });
            return;
        }

        fetch('/save-amazon-sprice', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(payload)
        })
            .then(function (r) {
                if (!r.ok) throw new Error('save failed');
                return r.json();
            })
            .then(onSuccess)
            .catch(onError);
    }

    function onModalShown(modal) {
        if (!isLmpCompetitorModal(modal)) return;

        var input = ensureSpBox(modal);
        observeModalTables(modal);
        ensureSpColumns(modal);

        if (input && !modal.querySelector('#lmpModalSpInput')) {
            // Shared box only — Amazon loads SP itself from row data
            loadStandardPrice(modal, input);
            [150, 400, 900].forEach(function (ms) {
                setTimeout(function () {
                    var sku = resolveSku(modal);
                    if (sku && sku !== (input.dataset.lmpSpSku || '')) {
                        loadStandardPrice(modal, input);
                    } else if (!(input.dataset.lmpSpSku || '').trim()) {
                        loadStandardPrice(modal, input);
                    }
                    ensureSpColumns(modal);
                }, ms);
            });
        } else {
            // Amazon / pages with native SP — still ensure column sync + late table inject
            [150, 400, 900].forEach(function (ms) {
                setTimeout(function () {
                    ensureSpColumns(modal);
                    syncSpColumnCells(modal);
                }, ms);
            });
        }
    }

    document.addEventListener('shown.bs.modal', function (e) {
        onModalShown(e.target);
    }, true);

    if (window.jQuery) {
        window.jQuery(document).on('shown.bs.modal', '.modal', function () {
            onModalShown(this);
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') return;
        if (!e.target || !e.target.classList) return;
        if (!e.target.classList.contains('lmp-modal-sp-input') && e.target.id !== 'lmpModalSpInput') return;
        e.preventDefault();
        e.target.blur();
    });

    document.addEventListener('focusout', function (e) {
        if (!e.target || !e.target.classList) return;
        // Only auto-save the shared injected input (Amazon has its own blur handler)
        if (!e.target.classList.contains('lmp-modal-sp-input')) return;
        saveStandardPrice(e.target);
    });

    document.addEventListener('input', function (e) {
        if (!e.target || !e.target.classList) return;
        if (!e.target.classList.contains('lmp-modal-sp-input') && e.target.id !== 'lmpModalSpInput') return;
        var modal = e.target.closest('.modal');
        syncSpColumnCells(modal);
    });

    window.LmpModalSp = {
        refresh: function (modalEl) {
            if (!modalEl) return;
            if (modalEl.getAttribute && modalEl.getAttribute('data-skip-lmp-sp') === '1') return;
            var input = ensureSpBox(modalEl);
            observeModalTables(modalEl);
            ensureSpColumns(modalEl);
            if (input && !modalEl.querySelector('#lmpModalSpInput')) {
                loadStandardPrice(modalEl, input);
            } else {
                syncSpColumnCells(modalEl);
            }
        },
        resolveSku: resolveSku,
        syncColumn: syncSpColumnCells
    };
})();
