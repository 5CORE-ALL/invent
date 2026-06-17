{{--
    Shared DAR (Daily Activity Report) quick-entry modal.

    Included once from the main layout so the modal is available on every page.
    Opened from the topbar "DAR" button, and reused by the DAR index page for
    add/edit. Expose a small global API: window.DarModal.open(row|null).
--}}
@auth
    <style>
        /* Repeatable task rows in the DAR modal. */
        .dar-task-row {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #fcfdff;
            padding: 12px 14px;
            margin-bottom: 12px;
        }
        .dar-task-row__head {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            margin-bottom: 4px;
        }
        .dar-row-remove {
            border: 0;
            background: transparent;
            color: #dc2626;
            cursor: pointer;
            padding: 2px 6px;
            border-radius: 6px;
            line-height: 1;
        }
        .dar-row-remove:hover { background: #fee2e2; }
        .dar-row-remove[disabled] { opacity: 0.35; cursor: not-allowed; }
        .dar-row-time { height: calc(2 * 1.5em + 0.75rem + 2px); }

        /* Widen the modal ~20% beyond Bootstrap's modal-lg (800px → 960px). */
        @media (min-width: 992px) {
            .dar-modal-wide { max-width: 960px; }
        }

        .dar-modal-tagline {
            font-size: 13px;
            font-weight: 700;
            color: #0d6efd;
            white-space: nowrap;
        }

        .dar-total-time {
            display: inline-flex;
            align-items: center;
            padding: 4px 12px;
            border-radius: 14px;
            background: #eef2ff;
            color: #3730a3;
            font-size: 13px;
            font-weight: 600;
            border: 1px solid #c7d2fe;
            white-space: nowrap;
        }
        .dar-total-time strong { margin-left: 4px; }

        .dar-has-overdue-active .form-check-input {
            border-color: #dc2626;
            background-color: #dc2626;
        }
        .dar-has-overdue-active .form-check-label {
            color: #dc2626;
            font-weight: 700;
        }
    </style>

    {{-- Add / Edit modal --}}
    <div class="modal fade" id="darModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered dar-modal-wide">
            <form class="modal-content" id="darForm">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title d-flex align-items-center gap-2">
                        <span id="darModalTitle">Daily Activity report (DAR)</span>
                        <i class="fas fa-chart-line text-success" title="Performance"></i>
                    </h5>
                    <span class="dar-modal-tagline ms-auto me-3">🚀 Manage Time, Achieve More</span>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="dar_id" name="id">
                    <input type="hidden" name="report_date" id="dar_report_date">
                    <input type="hidden" name="user_id" id="dar_user_id" value="{{ auth()->id() }}">

                    {{-- Repeatable task rows (Task / Time Taken) --}}
                    <div id="darTaskRows" class="mt-3"></div>

                    <div class="mt-2 d-flex align-items-center justify-content-between" id="darAddRowWrap">
                        <button type="button" class="btn btn-sm btn-soft-primary" id="darAddRowBtn">
                            <i class="fas fa-plus me-1"></i> Add more
                        </button>
                        <span class="dar-total-time" id="darTotalTime">
                            <i class="fas fa-clock me-1"></i>Total: <strong>0h 0m</strong>
                        </span>
                    </div>

                    {{-- Overdue declaration --}}
                    <div class="dar-overdue mt-4">
                        <div class="d-flex flex-wrap gap-4">
                            <div class="form-check">
                                <input class="form-check-input dar-overdue-toggle" type="checkbox" id="dar_no_overdue">
                                <label class="form-check-label" for="dar_no_overdue">I have no overdues</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input dar-overdue-toggle" type="checkbox" id="dar_has_overdue">
                                <label class="form-check-label" for="dar_has_overdue" id="dar_has_overdue_label">I have overdues</label>
                            </div>
                        </div>

                        <div class="row g-3 mt-1" id="darOverdueFields" style="display:none;">
                            <div class="col-md-4">
                                <label for="dar_overdue_count" class="form-label">How many Overdue</label>
                                <input type="number" min="0" class="form-control" id="dar_overdue_count" placeholder="e.g. 3">
                            </div>
                            <div class="col-md-4">
                                <label for="dar_overdue_reason" class="form-label">Reason</label>
                                <textarea rows="2" class="form-control" id="dar_overdue_reason" placeholder="Why is it overdue?"></textarea>
                            </div>
                            <div class="col-md-4">
                                <label for="dar_overdue_fix" class="form-label">How do I fix this issue</label>
                                <textarea rows="2" class="form-control" id="dar_overdue_fix" placeholder="Your plan to resolve it"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="darSaveBtn">Submit</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        $(function () {
            const modalEl = document.getElementById('darModal');
            if (!modalEl || typeof bootstrap === 'undefined') return;

            const csrf       = $('meta[name="csrf-token"]').attr('content');
            const storeUrl   = @json(route('dar.store'));
            const updateBase = @json(url('dar/update'));

            const modal = new bootstrap.Modal(modalEl);
            const form  = document.getElementById('darForm');

            function fmtDate(d) {
                const m = ('0' + (d.getMonth() + 1)).slice(-2);
                const day = ('0' + d.getDate()).slice(-2);
                return d.getFullYear() + '-' + m + '-' + day;
            }

            // Default the report date to one day prior (yesterday).
            function yesterdayStr() {
                const d = new Date();
                d.setDate(d.getDate() - 1);
                return fmtDate(d);
            }

            // ---- Repeatable task rows ----
            const taskRowsEl = document.getElementById('darTaskRows');
            const addRowBtn  = document.getElementById('darAddRowBtn');
            const addRowWrap = document.getElementById('darAddRowWrap');

            function buildTaskRow(data) {
                data = data || {};
                const row = document.createElement('div');
                row.className = 'dar-task-row';
                row.innerHTML =
                    '<div class="dar-task-row__head">' +
                        '<button type="button" class="dar-row-remove" title="Remove this task">' +
                            '<i class="fas fa-times"></i>' +
                        '</button>' +
                    '</div>' +
                    '<div class="row g-3 align-items-end">' +
                        '<div class="col-md-6">' +
                            '<label class="form-label">Task</label>' +
                            '<textarea rows="2" class="form-control dar-row-task" placeholder="Describe the task"></textarea>' +
                        '</div>' +
                        '<div class="col-md-6">' +
                            '<label class="form-label">Time (min)</label>' +
                            '<input type="number" step="1" min="0" maxlength="3" class="form-control dar-row-time" placeholder="30">' +
                        '</div>' +
                    '</div>';
                row.querySelector('.dar-row-time').value = (data.time_taken === 0 || data.time_taken) ? data.time_taken : '';
                row.querySelector('.dar-row-task').value = data.task || '';
                return row;
            }

            function renumberRows() {
                const rows = taskRowsEl.querySelectorAll('.dar-task-row');
                rows.forEach(function (r) {
                    const rm = r.querySelector('.dar-row-remove');
                    if (rm) rm.disabled = rows.length <= 1;
                });
            }

            function addTaskRow(data) {
                taskRowsEl.appendChild(buildTaskRow(data));
                renumberRows();
                updateTotalTime();
            }

            function resetTaskRows(rowsData) {
                taskRowsEl.innerHTML = '';
                if (rowsData && rowsData.length) {
                    rowsData.forEach(addTaskRow);
                } else {
                    addTaskRow({});
                }
                updateTotalTime();
            }

            function updateTotalTime() {
                const el = document.getElementById('darTotalTime');
                if (!el) return;
                let totalMin = 0;
                taskRowsEl.querySelectorAll('.dar-row-time').forEach(function (inp) {
                    const v = parseInt(inp.value, 10);
                    if (!isNaN(v) && v > 0) totalMin += v;
                });
                const h = Math.floor(totalMin / 60);
                const m = totalMin % 60;
                const strong = el.querySelector('strong');
                if (strong) strong.textContent = h + 'h ' + m + 'm';
            }

            taskRowsEl.addEventListener('input', function (e) {
                if (e.target.classList.contains('dar-row-time')) {
                    updateTotalTime();
                }
            });

            taskRowsEl.addEventListener('click', function (e) {
                const btn = e.target.closest('.dar-row-remove');
                if (!btn || btn.disabled) return;
                const row = btn.closest('.dar-task-row');
                if (row) row.remove();
                renumberRows();
                updateTotalTime();
            });

            // ---- Overdue declaration (mutually-exclusive tickboxes) ----
            const noOverdueEl     = document.getElementById('dar_no_overdue');
            const hasOverdueEl    = document.getElementById('dar_has_overdue');
            const overdueFieldsEl = document.getElementById('darOverdueFields');
            const overdueCountEl  = document.getElementById('dar_overdue_count');
            const overdueReasonEl = document.getElementById('dar_overdue_reason');
            const overdueFixEl    = document.getElementById('dar_overdue_fix');

            function syncOverdueUI() {
                const on = !!(hasOverdueEl && hasOverdueEl.checked);
                if (overdueFieldsEl) overdueFieldsEl.style.display = on ? '' : 'none';
                if (hasOverdueEl) {
                    const wrap = hasOverdueEl.closest('.form-check');
                    if (wrap) wrap.classList.toggle('dar-has-overdue-active', on);
                }
            }

            function clearOverdueFields() {
                if (overdueCountEl)  overdueCountEl.value = '';
                if (overdueReasonEl) overdueReasonEl.value = '';
                if (overdueFixEl)    overdueFixEl.value = '';
            }

            function resetOverdue() {
                if (noOverdueEl)  noOverdueEl.checked = false;
                if (hasOverdueEl) hasOverdueEl.checked = false;
                clearOverdueFields();
                syncOverdueUI();
            }

            if (noOverdueEl) {
                noOverdueEl.addEventListener('change', function () {
                    if (this.checked && hasOverdueEl) hasOverdueEl.checked = false;
                    clearOverdueFields();
                    syncOverdueUI();
                });
            }
            if (hasOverdueEl) {
                hasOverdueEl.addEventListener('change', function () {
                    if (this.checked && noOverdueEl) noOverdueEl.checked = false;
                    syncOverdueUI();
                });
            }

            if (addRowBtn) {
                addRowBtn.addEventListener('click', function () { addTaskRow({}); });
            }

            function collectTaskRows() {
                const out = [];
                taskRowsEl.querySelectorAll('.dar-task-row').forEach(function (r) {
                    const task = (r.querySelector('.dar-row-task').value || '').trim();
                    const time = (r.querySelector('.dar-row-time').value || '').trim();
                    if (!task && !time) return; // skip fully-empty rows
                    out.push({ task: task, time_taken: time });
                });
                return out;
            }

            // ---- Open / close ----
            function openModal(row) {
                form.reset();
                resetOverdue();
                if (row && row.id) {
                    document.getElementById('darModalTitle').textContent = 'Edit — Daily Activity report (DAR)';
                    document.getElementById('dar_id').value = row.id;
                    document.getElementById('dar_report_date').value = row.report_date || yesterdayStr();
                    document.getElementById('dar_user_id').value = row.user_id || '{{ auth()->id() }}';
                    resetTaskRows([{ task: row.task, time_taken: row.time_taken }]);
                    if (addRowWrap) addRowWrap.style.display = 'none';
                } else {
                    document.getElementById('darModalTitle').textContent = 'Daily Activity report (DAR)';
                    document.getElementById('dar_id').value = '';
                    document.getElementById('dar_report_date').value = yesterdayStr();
                    document.getElementById('dar_user_id').value = '{{ auth()->id() }}';
                    resetTaskRows([]);
                    if (addRowWrap) addRowWrap.style.display = '';
                }
                modal.show();
            }

            // Reload the DAR table if we're on the DAR index page.
            function reloadTableIfPresent() {
                if (window.__darTable && typeof window.__darTable.replaceData === 'function') {
                    window.__darTable.replaceData();
                }
            }

            // ---- Save (create one-or-many, or update single) ----
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                const id         = document.getElementById('dar_id').value;
                const reportDate = document.getElementById('dar_report_date').value;
                const userId     = document.getElementById('dar_user_id').value;
                const btn        = document.getElementById('darSaveBtn');

                if (!reportDate || !userId) {
                    alert('Please select a date and a user.');
                    return;
                }

                const rows = collectTaskRows();
                if (!rows.length) {
                    alert('Please fill in at least one task.');
                    return;
                }

                btn.disabled = true;

                function buildPayload(r) {
                    return {
                        _token: csrf,
                        report_date: reportDate,
                        user_id: userId,
                        task: r.task,
                        time_taken: r.time_taken,
                    };
                }

                function onError(xhr) {
                    let msg = 'Something went wrong.';
                    if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                    alert(msg);
                }

                if (id) {
                    $.post(updateBase + '/' + id, buildPayload(rows[0]), function () {
                        modal.hide();
                        reloadTableIfPresent();
                    }).fail(onError).always(function () {
                        btn.disabled = false;
                    });
                    return;
                }

                const requests = rows.map(function (r) {
                    return $.post(storeUrl, buildPayload(r));
                });
                $.when.apply($, requests).done(function () {
                    modal.hide();
                    reloadTableIfPresent();
                }).fail(onError).always(function () {
                    btn.disabled = false;
                });
            });

            // Expose a tiny global API + topbar trigger.
            window.DarModal = { open: openModal };

            const topbarBtn = document.getElementById('darTopbarOpenBtn');
            if (topbarBtn) {
                topbarBtn.addEventListener('click', function () { openModal(null); });
            }
        });
    </script>
@endauth

